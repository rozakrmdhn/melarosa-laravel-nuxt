<?php

namespace App\Services;

use App\Models\GeoportalSyncLog;
use App\Models\Layer;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MartinTileService
{
    public const STATUS_CACHE_KEY = 'martin_tile_server_connection_status';
    public const DEFAULT_SOURCE_ATTRIBUTION = 'Pemerintah Kabupaten Bojonegoro';
    private const LEGACY_DEFAULT_ATTRIBUTION = 'Dinas PU Bina Marga & Penataan Ruang Bojonegoro';

    protected Client $httpClient;
    protected string $baseUrl;

    /**
     * Human-friendly catalog presets for Melarosa core spatial tables.
     */
    private const KNOWN_SOURCES = [
        'bataswilayah_kecamatan' => [
            'name' => 'Batas Wilayah Kecamatan',
            'category' => 'Batas Wilayah',
            'description' => 'Layer vektor MVT batas administrasi kecamatan di Kabupaten Bojonegoro (sumber PostGIS Martin).',
        ],
        'bataswilayah_desa' => [
            'name' => 'Batas Wilayah Desa',
            'category' => 'Batas Wilayah',
            'description' => 'Layer vektor MVT batas administrasi desa dan kelurahan di Kabupaten Bojonegoro (sumber PostGIS Martin).',
        ],
        'bataswilayah_kecamatan_old' => [
            'name' => 'Batas Wilayah Kecamatan (Arsip Lama)',
            'category' => 'Arsip Spasial',
            'description' => 'Tabel arsip batas administrasi kecamatan versi terdahulu dari Martin Tile Server.',
        ],
        'jalan_porosdesa' => [
            'name' => 'Jaringan Jalan Poros Desa',
            'category' => 'Jaringan Jalan',
            'description' => 'Layer vektor MVT ruas jalan poros desa beserta atribut panjang dan kondisi eksisting (sumber PostGIS Martin).',
            'default_visible' => true,
        ],
        'infrastruktur_segmen' => [
            'name' => 'Segmen Infrastruktur Jalan',
            'category' => 'Infrastruktur Jalan',
            'description' => 'Layer vektor MVT segmen perkerasan dan pembangunan infrastruktur jalan desa & lingkungan.',
            'default_visible' => true,
        ],
        'infrastruktur_area' => [
            'name' => 'Area Infrastruktur Wilayah',
            'category' => 'Infrastruktur Jalan',
            'description' => 'Layer vektor MVT poligon area infrastruktur pendukung di Kabupaten Bojonegoro.',
        ],
        'jalan_lingkungan' => [
            'name' => 'Jaringan Jalan Lingkungan (Referensi)',
            'category' => 'Jaringan Jalan',
            'description' => 'Layer vektor MVT data referensi jaringan jalan lingkungan perdesaan.',
        ],
        'jalan_peningkatanstatus' => [
            'name' => 'Jalan Peningkatan Status',
            'category' => 'Jaringan Jalan',
            'description' => 'Layer vektor MVT ruas jalan usulan peningkatan status jalan di Kabupaten Bojonegoro.',
        ],
        'jalan_segmen_pembangunan' => [
            'name' => 'Segmen Pembangunan Jalan (Referensi)',
            'category' => 'Arsip Spasial',
            'description' => 'Layer vektor MVT data sumber segmen pembangunan jalan di PostGIS Martin.',
        ],
        'bkkd_proposal_lokasi' => [
            'name' => 'Lokasi Usulan & Realisasi BKKD',
            'category' => 'Anggaran & BKKD',
            'description' => 'Layer vektor MVT geometri ruas lokasi usulan Bantuan Keuangan Khusus Desa (BKKD).',
        ],
    ];

    public function __construct(?Client $client = null, ?string $baseUrl = null)
    {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('services.martin.url', 'http://localhost:9090')), '/');
        $this->httpClient = $client ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'MelarosaWebGIS/1.0 (Martin-Harvester)',
            ],
        ]);
    }

    /**
     * Purge tile cache on-demand in Martin Tile Server.
     *
     * @param string $sourceId e.g. 'jalan_porosdesa', 'infrastruktur_segmen'
     */
    public static function purgeCache(string $sourceId): bool
    {
        $baseUrl = rtrim((string) config('services.martin.url', 'http://localhost:9090'), '/');
        $endpoint = "{$baseUrl}/cache/{$sourceId}";

        try {
            $response = Http::timeout(3)->delete($endpoint);

            if ($response->successful()) {
                Log::info("Martin cache purged successfully for source: {$sourceId}");
                return true;
            }

            Log::warning("Martin cache purge returned status {$response->status()}: {$response->body()}");
            return false;
        } catch (\Throwable $e) {
            Log::error("Failed to purge Martin cache for {$sourceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check connection status and tile catalog availability on Martin Tile Server.
     */
    public function checkConnection(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && Cache::has(self::STATUS_CACHE_KEY)) {
            return Cache::get(self::STATUS_CACHE_KEY);
        }

        $localCount = Layer::internal()->count();
        $activeLocalCount = Layer::internal()->where('is_active', true)->count();
        $lastSyncLog = GeoportalSyncLog::where('status', 'success')
            ->where('triggered_by', 'like', 'martin_%')
            ->latest()
            ->first();

        $maxSyncedAt = Layer::internal()->max('last_synced_at');
        $lastSyncedAt = $lastSyncLog?->created_at?->toISOString()
            ?? ($maxSyncedAt instanceof Carbon
                ? $maxSyncedAt->toISOString()
                : (is_string($maxSyncedAt) ? Carbon::parse($maxSyncedAt)->toISOString() : null));

        $startTime = microtime(true);
        $connected = false;
        $remoteSources = null;
        $status = 'offline';
        $message = '';
        $responseTimeMs = null;
        $errorMessage = null;

        try {
            $response = $this->httpClient->get("{$this->baseUrl}/catalog", [
                'timeout' => 4,
                'connect_timeout' => 3,
            ]);

            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $payload = json_decode((string) $response->getBody(), true);
                if (is_array($payload) && array_key_exists('tiles', $payload) && is_array($payload['tiles'])) {
                    $connected = true;
                    $status = 'online';
                    $remoteSources = count($payload['tiles']);
                    $message = "Terhubung ke Martin Tile Server ({$remoteSources} sumber vektor tersedia)";
                } else {
                    $errorMessage = 'Respon /catalog Martin tidak valid atau tidak memuat kunci tiles.';
                }
            } else {
                $errorMessage = "Martin Tile Server merespons dengan kode HTTP {$statusCode}.";
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
        }

        if (!$connected) {
            if ($localCount > 0) {
                $status = 'fallback';
                $message = "Menggunakan {$localCount} konfigurasi layer internal tersimpan (Martin Tile Server tidak merespons)";
            } else {
                $status = 'offline';
                $message = 'Koneksi ke Martin Tile Server Terputus';
            }
        }

        $result = [
            'ok' => true,
            'connected' => $connected,
            'status' => $status,
            'message' => $message,
            'service_name' => 'Martin Vector Tile Server (PostGIS MVT)',
            'martin_url' => $this->baseUrl,
            'remote_sources' => $remoteSources,
            'total_local_layers' => $localCount,
            'active_local_layers' => $activeLocalCount,
            'last_synced_at' => $lastSyncedAt,
            'response_time_ms' => $responseTimeMs,
            'error_detail' => $errorMessage,
            'checked_at' => now()->toISOString(),
            'last_sync_log' => $lastSyncLog ? [
                'id' => $lastSyncLog->id,
                'status' => $lastSyncLog->status,
                'records_found' => $lastSyncLog->records_found,
                'records_inserted' => $lastSyncLog->records_inserted,
                'records_updated' => $lastSyncLog->records_updated,
                'duration_seconds' => $lastSyncLog->duration_seconds,
                'created_at' => $lastSyncLog->created_at?->toISOString(),
            ] : null,
        ];

        Cache::put(self::STATUS_CACHE_KEY, $result, now()->addSeconds(60));

        return $result;
    }

    /**
     * Fetch published tile catalog (and optional TileJSON details) from Martin Tile Server.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCatalog(bool $withTileJson = true): array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/catalog", [
            'timeout' => 8,
            'connect_timeout' => 3,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("Gagal mengambil katalog Martin (HTTP {$statusCode}).");
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload) || !isset($payload['tiles']) || !is_array($payload['tiles'])) {
            throw new RuntimeException('Format respon /catalog Martin tidak valid.');
        }

        $tiles = $payload['tiles'];
        ksort($tiles);

        $pgGeomTypes = $this->getPostgisGeometryTypes();
        $internalLayers = Layer::internal()->get();

        $results = [];
        foreach ($tiles as $sourceId => $catalogEntry) {
            if (!is_string($sourceId) || $sourceId === '') {
                continue;
            }

            $entry = is_array($catalogEntry) ? $catalogEntry : [];
            $tileJson = $withTileJson ? $this->fetchSourceTileJson($sourceId) : null;
            $descriptor = $this->describeSource($sourceId, $entry, $tileJson, $pgGeomTypes);
            $existing = $this->findExistingInternalLayer($internalLayers, $sourceId);

            $descriptor['synced'] = $existing !== null;
            $descriptor['local_layer_id'] = $existing?->id;
            $descriptor['local_name'] = $existing?->name;
            $descriptor['local_is_active'] = $existing?->is_active;
            $descriptor['local_default_visible'] = $existing?->default_visible;
            $descriptor['local_last_synced_at'] = $existing?->last_synced_at?->toISOString();

            $results[] = $descriptor;
        }

        return $results;
    }

    /**
     * Run full idempotent sync from Martin Tile Server catalog into local layers table.
     *
     * @param array{sources?: array<int, string>|null, deactivate_missing?: bool, include_tilejson?: bool} $options
     */
    public function sync(string $triggeredBy = 'martin_manual_admin', ?int $userId = null, array $options = []): array
    {
        $normalizedTrigger = str_starts_with($triggeredBy, 'martin_')
            ? $triggeredBy
            : 'martin_' . ltrim($triggeredBy, '_');

        $startTime = microtime(true);
        $syncLog = GeoportalSyncLog::create([
            'triggered_by' => $normalizedTrigger,
            'status' => 'running',
            'user_id' => $userId,
        ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $deactivated = 0;
        $found = 0;

        try {
            $includeTileJson = array_key_exists('include_tilejson', $options)
                ? (bool) $options['include_tilejson']
                : true;

            $catalog = $this->fetchCatalog($includeTileJson);
            $selectedSources = array_values(array_filter(
                array_map('strval', $options['sources'] ?? []),
                static fn (string $id) => $id !== ''
            ));

            if (!empty($selectedSources)) {
                $selectedSet = array_flip($selectedSources);
                $itemsToSync = array_values(array_filter(
                    $catalog,
                    static fn (array $item) => isset($selectedSet[$item['source_id']])
                ));
                $skipped = max(0, count($selectedSources) - count($itemsToSync));
            } else {
                $itemsToSync = $catalog;
            }

            $found = count($itemsToSync);
            $maxOrder = (int) (Layer::max('order') ?? 0);
            $internalLayers = Layer::internal()->get();
            $syncedSourceIds = [];

            foreach ($itemsToSync as $descriptor) {
                $sourceId = $descriptor['source_id'];
                $syncedSourceIds[$sourceId] = true;

                $existing = $this->findExistingInternalLayer($internalLayers, $sourceId);
                $existingMeta = is_array($existing?->csw_metadata) ? $existing->csw_metadata : [];

                $martinMeta = [
                    'source_service' => 'martin',
                    'martin_source_id' => $sourceId,
                    'martin_url' => $this->baseUrl,
                    'content_type' => $descriptor['content_type'],
                    'geom_type' => $descriptor['geom_type'],
                    'martin_description' => $descriptor['raw_description'],
                    'fields' => $descriptor['fields'],
                    'fields_count' => count($descriptor['fields']),
                    'bounds' => $descriptor['bounds'],
                    'tilejson_version' => $descriptor['tilejson_version'],
                    'synced_at' => now()->toISOString(),
                ];

                if ($existing) {
                    $existing->update([
                        'protocol' => 'mvt',
                        'url' => $descriptor['url'],
                        'layer_name' => $sourceId,
                        'source_type' => 'internal',
                        'category' => $existing->category ?: $descriptor['category'],
                        'opd_name' => $this->resolveSyncedAttribution($existing->opd_name),
                        'attribution' => $this->resolveSyncedAttribution($existing->attribution),
                        'description' => $existing->description ?: $descriptor['description'],
                        'csw_metadata' => array_merge($existingMeta, $martinMeta),
                        'style_config' => !empty($existing->style_config)
                            ? $existing->style_config
                            : $descriptor['default_style_config'],
                        'is_synced' => true,
                        'last_synced_at' => now(),
                    ]);
                    $updated++;
                } else {
                    $maxOrder++;
                    $created = Layer::create([
                        'name' => $descriptor['suggested_name'],
                        'category' => $descriptor['category'],
                        'opd_name' => self::DEFAULT_SOURCE_ATTRIBUTION,
                        'protocol' => 'mvt',
                        'url' => $descriptor['url'],
                        'layer_name' => $sourceId,
                        'attribution' => self::DEFAULT_SOURCE_ATTRIBUTION,
                        'description' => $descriptor['description'],
                        'csw_metadata' => $martinMeta,
                        'style_config' => $descriptor['default_style_config'],
                        'source_type' => 'internal',
                        'is_active' => $descriptor['default_active'],
                        'default_visible' => $descriptor['default_visible'],
                        'opacity' => 1.0,
                        'order' => $maxOrder,
                        'is_synced' => true,
                        'last_synced_at' => now(),
                    ]);
                    $internalLayers->push($created);
                    $inserted++;
                }
            }

            if (!empty($options['deactivate_missing']) && empty($selectedSources)) {
                foreach ($internalLayers as $localLayer) {
                    if (!$localLayer->is_synced || !$localLayer->is_active) {
                        continue;
                    }
                    $resolvedSourceId = $this->extractSourceId($localLayer);
                    if ($resolvedSourceId && !isset($syncedSourceIds[$resolvedSourceId])) {
                        $localLayer->update(['is_active' => false]);
                        $deactivated++;
                    }
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            $syncLog->update([
                'status' => 'success',
                'records_found' => $found,
                'records_inserted' => $inserted,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'duration_seconds' => $duration,
            ]);

            Cache::forget(self::STATUS_CACHE_KEY);

            Log::info("Martin catalog sync succeeded: {$inserted} inserted, {$updated} updated, {$skipped} skipped in {$duration}s.");

            $stats = [
                'records_found' => $found,
                'records_inserted' => $inserted,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'records_deactivated' => $deactivated,
                'duration_seconds' => $duration,
            ];

            return [
                'ok' => true,
                'message' => "Sinkronisasi Martin Tile Server berhasil ({$found} sumber ditemukan: {$inserted} baru, {$updated} diperbarui).",
                'summary' => [
                    'found' => $found,
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'deactivated' => $deactivated,
                    'duration_seconds' => $duration,
                ],
                'data' => $stats,
                'log_id' => $syncLog->id,
            ];
        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $syncLog->update([
                'status' => 'failed',
                'records_found' => $found,
                'records_inserted' => $inserted,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'error_message' => $e->getMessage(),
                'duration_seconds' => $duration,
            ]);

            Log::error('Martin catalog sync failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'ok' => false,
                'message' => 'Sinkronisasi Martin Tile Server gagal: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'log_id' => $syncLog->id,
            ];
        }
    }

    /**
     * Fetch TileJSON metadata for a single Martin source without failing the batch on timeout.
     */
    protected function fetchSourceTileJson(string $sourceId): ?array
    {
        try {
            $response = $this->httpClient->get("{$this->baseUrl}/" . rawurlencode($sourceId), [
                'timeout' => 3,
                'connect_timeout' => 2,
            ]);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $decoded = json_decode((string) $response->getBody(), true);
                return is_array($decoded) ? $decoded : null;
            }
        } catch (\Throwable) {
            // Optional metadata enrichment: ignore single-source failure cleanly
        }

        return null;
    }

    /**
     * Resolve PostGIS geometry column types indexed by table name when connected to PostgreSQL.
     *
     * @return array<string, string>
     */
    protected function getPostgisGeometryTypes(): array
    {
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return [];
            }

            $rows = DB::select('SELECT f_table_name, type FROM geometry_columns');
            $map = [];
            foreach ($rows as $row) {
                if (!empty($row->f_table_name) && !empty($row->type)) {
                    $map[(string) $row->f_table_name] = strtoupper((string) $row->type);
                }
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Build a normalized descriptor from a Martin catalog entry + optional TileJSON.
     */
    protected function describeSource(string $sourceId, array $catalogEntry, ?array $tileJson, array $pgGeomTypes): array
    {
        $known = self::KNOWN_SOURCES[$sourceId] ?? null;
        $rawDescription = (string) ($tileJson['description'] ?? $catalogEntry['description'] ?? "public.{$sourceId}.geom");
        $pgGeomType = $pgGeomTypes[$sourceId] ?? null;
        $geomType = $this->resolveGeomType($sourceId, $pgGeomType);

        $fields = [];
        if (isset($tileJson['vector_layers'][0]['fields']) && is_array($tileJson['vector_layers'][0]['fields'])) {
            $fields = $tileJson['vector_layers'][0]['fields'];
        }

        $isLegacy = (bool) preg_match('/_(old|bak|backup|tmp|temp)$/i', $sourceId);
        $suggestedName = $known['name'] ?? Str::title(str_replace('_', ' ', $sourceId));
        $category = $known['category'] ?? $this->inferCategory($sourceId, $isLegacy);
        $description = $known['description']
            ?? "Layer vektor MVT untuk sumber {$sourceId} dari Martin Tile Server ({$rawDescription}).";

        return [
            'source_id' => $sourceId,
            'suggested_name' => $suggestedName,
            'category' => $category,
            'description' => $description,
            'raw_description' => $rawDescription,
            'content_type' => (string) ($catalogEntry['content_type'] ?? 'application/x-protobuf'),
            'geom_type' => $geomType,
            'pg_geom_type' => $pgGeomType,
            'is_legacy' => $isLegacy,
            'default_active' => !$isLegacy,
            'default_visible' => (bool) ($known['default_visible'] ?? false),
            'url' => "/martin/{$sourceId}/{z}/{x}/{y}",
            'fields' => $fields,
            'bounds' => isset($tileJson['bounds']) && is_array($tileJson['bounds']) ? $tileJson['bounds'] : null,
            'tilejson_version' => isset($tileJson['tilejson']) ? (string) $tileJson['tilejson'] : null,
            'default_style_config' => $this->buildDefaultStyleConfig($sourceId, $geomType, $fields),
        ];
    }

    protected function resolveGeomType(string $sourceId, ?string $pgGeomType): string
    {
        if ($pgGeomType) {
            if (str_contains($pgGeomType, 'POLYGON')) {
                return 'polygon';
            }
            if (str_contains($pgGeomType, 'POINT')) {
                return 'point';
            }
            if (str_contains($pgGeomType, 'LINE')) {
                return 'line';
            }
        }

        $lower = strtolower($sourceId);
        if (str_contains($lower, 'bataswilayah') || str_contains($lower, 'area') || str_contains($lower, 'zona')) {
            return 'polygon';
        }
        if (str_contains($lower, 'titik') || str_contains($lower, 'point') || str_contains($lower, 'poi')) {
            return 'point';
        }

        return 'line';
    }

    protected function inferCategory(string $sourceId, bool $isLegacy): string
    {
        if ($isLegacy) {
            return 'Arsip Spasial';
        }

        $lower = strtolower($sourceId);
        if (str_starts_with($lower, 'batas')) {
            return 'Batas Wilayah';
        }
        if (str_contains($lower, 'jalan')) {
            return 'Jaringan Jalan';
        }
        if (str_contains($lower, 'bkkd') || str_contains($lower, 'anggaran')) {
            return 'Anggaran & BKKD';
        }
        if (str_contains($lower, 'infrastruktur')) {
            return 'Infrastruktur Jalan';
        }

        return 'Spasial Internal';
    }

    protected function buildDefaultStyleConfig(string $sourceId, string $geomType, array $fields): array
    {
        $labelField = 'namobj';
        foreach (['nama_ruas', 'nama_desa', 'nama_kecamatan', 'nama_kegiatan', 'nama_jalan', 'namobj', 'kode_ruas'] as $candidate) {
            if (array_key_exists($candidate, $fields)) {
                $labelField = $candidate;
                break;
            }
        }

        if ($geomType === 'polygon') {
            $isBoundary = str_starts_with(strtolower($sourceId), 'batas');
            return [
                'geom_type' => 'polygon',
                'color' => $isBoundary ? '#475569' : '#0284c7',
                'width' => $sourceId === 'bataswilayah_kecamatan' ? 2 : 1.5,
                'dash' => $sourceId === 'bataswilayah_kecamatan' ? 'dash-dot' : ($isBoundary ? 'dash' : 'solid'),
                'fill_color' => $isBoundary ? 'rgba(71, 85, 105, 0.04)' : 'rgba(2, 132, 199, 0.12)',
                'casing' => $isBoundary,
                'casing_color' => 'rgba(148, 163, 184, 0.25)',
                'casing_width' => 5,
                'label' => [
                    'enabled' => true,
                    'field' => $labelField,
                    'min_zoom' => 12,
                    'color' => '#334155',
                    'halo_color' => '#ffffff',
                    'halo_width' => 2,
                    'font_size' => 11,
                    'placement' => 'point',
                    'orientation' => 'horizontal',
                ],
                'rules' => [],
                'zoom_scale' => $isBoundary,
            ];
        }

        if ($geomType === 'point') {
            return [
                'geom_type' => 'point',
                'color' => '#0284c7',
                'width' => 2,
                'point_radius' => 6,
                'point_color' => '#0284c7',
                'point_border_color' => '#ffffff',
                'point_border_width' => 2,
                'label' => [
                    'enabled' => true,
                    'field' => $labelField,
                    'min_zoom' => 14,
                    'color' => '#0f172a',
                    'halo_color' => '#ffffff',
                    'halo_width' => 2,
                    'font_size' => 11,
                    'placement' => 'point',
                ],
                'rules' => [],
                'zoom_scale' => false,
            ];
        }

        $hasConditionField = array_key_exists('kondisi', $fields)
            || in_array($sourceId, ['jalan_porosdesa', 'infrastruktur_segmen'], true);

        if ($hasConditionField) {
            return [
                'geom_type' => 'line',
                'color' => '#10b981',
                'width' => 3,
                'dash' => 'solid',
                'casing' => false,
                'casing_color' => 'rgba(16, 185, 129, 0.25)',
                'casing_width' => 6,
                'fill_color' => 'rgba(16, 185, 129, 0.1)',
                'label' => [
                    'enabled' => true,
                    'field' => $labelField,
                    'min_zoom' => 13.5,
                    'color' => '#064e3b',
                    'halo_color' => '#ffffff',
                    'halo_width' => 3,
                    'font_size' => 11,
                    'placement' => 'line',
                ],
                'rules' => [
                    ['id' => 'r1', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak berat', 'color' => '#ef4444', 'width' => 3.5],
                    ['id' => 'r2', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak ringan', 'color' => '#f97316', 'width' => 3],
                    ['id' => 'r3', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'sedang', 'color' => '#f59e0b', 'width' => 3],
                    ['id' => 'r4', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak', 'color' => '#f97316', 'width' => 3],
                    ['id' => 'r5', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'baik', 'color' => '#10b981', 'width' => 3],
                ],
                'zoom_scale' => true,
            ];
        }

        return [
            'geom_type' => 'line',
            'color' => $sourceId === 'bkkd_proposal_lokasi' ? '#d97706' : '#2563eb',
            'width' => 2.5,
            'dash' => 'solid',
            'casing' => false,
            'fill_color' => 'rgba(37, 99, 235, 0.1)',
            'label' => [
                'enabled' => true,
                'field' => $labelField,
                'min_zoom' => 13,
                'color' => '#1e3a8a',
                'halo_color' => '#ffffff',
                'halo_width' => 3,
                'font_size' => 11,
                'placement' => 'line',
            ],
            'rules' => [],
            'zoom_scale' => true,
        ];
    }

    protected function findExistingInternalLayer(Collection $internalLayers, string $sourceId): ?Layer
    {
        return $internalLayers->first(function (Layer $layer) use ($sourceId) {
            return $this->extractSourceId($layer) === $sourceId;
        });
    }

    protected function resolveSyncedAttribution(?string $current): string
    {
        $normalized = trim((string) $current);
        if ($normalized === '' || $normalized === self::LEGACY_DEFAULT_ATTRIBUTION) {
            return self::DEFAULT_SOURCE_ATTRIBUTION;
        }

        return $normalized;
    }

    protected function extractSourceId(Layer $layer): ?string
    {
        if (!empty($layer->layer_name) && !str_contains($layer->layer_name, ':')) {
            return trim($layer->layer_name);
        }

        if (!empty($layer->url) && preg_match('/\/martin\/([a-zA-Z0-9_\-]+)/', $layer->url, $matches)) {
            return $matches[1];
        }

        return $layer->layer_name ? trim($layer->layer_name) : null;
    }
}

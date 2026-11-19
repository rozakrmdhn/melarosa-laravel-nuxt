<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LayerController extends Controller
{
    /**
     * Display a listing of the layers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $protocol = $request->query('protocol');
        $sourceType = $request->query('source_type');
        $isActive = $request->query('is_active');
        $defaultVisible = $request->query('default_visible');
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 15;
        }

        $query = Layer::query()
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('layer_name', 'ilike', "%{$search}%")
                        ->orWhere('attribution', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($protocol, function ($q, $protocol) {
                $q->where('protocol', $protocol);
            })
            ->when($sourceType, function ($q, $sourceType) {
                if ($sourceType === 'internal') {
                    $q->internal();
                } elseif ($sourceType === 'geoportal') {
                    $q->geoportal();
                } elseif ($sourceType === 'external') {
                    $q->external();
                } else {
                    $q->where('source_type', $sourceType);
                }
            })
            ->when($request->has('is_active') && $isActive !== null && $isActive !== '', function ($q) use ($isActive) {
                $q->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->has('default_visible') && $defaultVisible !== null && $defaultVisible !== '', function ($q) use ($defaultVisible) {
                $q->where('default_visible', filter_var($defaultVisible, FILTER_VALIDATE_BOOLEAN));
            });

        // Statistics for summary badges & source grouping counts
        $summary = [
            'total' => Layer::count(),
            'active' => Layer::where('is_active', true)->count(),
            'default_visible' => Layer::where('is_active', true)->where('default_visible', true)->count(),
            'geoportal' => Layer::geoportal()->count(),
            'internal' => Layer::internal()->count(),
            'external' => Layer::external()->count(),
        ];

        // Ordering: priority by order ASC, then name ASC
        $layers = $query->orderBy('order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'ok' => true,
            'summary' => $summary,
            'data' => $layers->items(),
            'meta' => [
                'current_page' => $layers->currentPage(),
                'last_page' => $layers->lastPage(),
                'per_page' => $layers->perPage(),
                'total' => $layers->total(),
            ],
        ]);
    }

    /**
     * Store a newly created layer in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'protocol' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:500'],
            'layer_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'default_visible' => ['sometimes', 'boolean'],
            'opacity' => ['sometimes', 'numeric', 'between:0,1'],
            'order' => ['nullable', 'integer', 'min:0'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_type' => ['required', 'string', 'max:50'],
            'style_config' => ['nullable', 'array'],
            'is_synced' => ['sometimes', 'boolean'],
        ]);

        if (!isset($validated['order']) || $validated['order'] === null) {
            $maxOrder = Layer::max('order') ?? -1;
            $validated['order'] = $maxOrder + 1;
        }

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['default_visible'] = $validated['default_visible'] ?? false;
        $validated['opacity'] = $validated['opacity'] ?? 1.0;
        $validated['is_synced'] = $validated['is_synced'] ?? false;

        $layer = Layer::create($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Layer baru berhasil ditambahkan.',
            'data' => $layer,
        ], 201);
    }

    /**
     * Display the specified layer.
     */
    public function show(Layer $layer): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $layer,
        ]);
    }

    /**
     * Update the specified layer in storage.
     */
    public function update(Request $request, Layer $layer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'opd_name' => ['nullable', 'string', 'max:200'],
            'protocol' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'max:500'],
            'legend_url' => ['nullable', 'string', 'max:1000'],
            'layer_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'default_visible' => ['sometimes', 'boolean'],
            'opacity' => ['sometimes', 'numeric', 'between:0,1'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_type' => ['sometimes', 'required', 'string', 'max:50'],
            'style_config' => ['nullable', 'array'],
            'is_synced' => ['sometimes', 'boolean'],
        ]);

        $layer->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Data layer berhasil diperbarui.',
            'data' => $layer,
        ]);
    }

    /**
     * Remove the specified layer from storage.
     */
    public function destroy(Layer $layer): JsonResponse
    {
        $layer->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Layer berhasil dihapus.',
        ]);
    }

    /**
     * Fast toggle active status.
     */
    public function toggleActive(Layer $layer): JsonResponse
    {
        $layer->is_active = !$layer->is_active;
        $layer->save();

        return response()->json([
            'ok' => true,
            'message' => $layer->is_active ? 'Layer berhasil diaktifkan.' : 'Layer dinonaktifkan.',
            'data' => $layer,
        ]);
    }

    /**
     * Full non-paginated layer list ordered for stack and active configuration.
     */
    public function stack(): JsonResponse
    {
        $layers = Layer::query()
            ->orderByDesc('is_active')
            ->orderByDesc('default_visible')
            ->orderByDesc('order')
            ->orderBy('name', 'asc')
            ->get()
            ->makeHidden(['csw_metadata']);

        return response()->json([
            'ok' => true,
            'summary' => [
                'total' => $layers->count(),
                'active' => $layers->where('is_active', true)->count(),
                'inactive' => $layers->where('is_active', false)->count(),
                'default_visible' => $layers->where('is_active', true)->where('default_visible', true)->count(),
            ],
            'data' => $layers->values(),
        ]);
    }

    /**
     * Batch reorder and configure active status, default visibility, and opacity for layers.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'uuid', 'exists:layers,id'],
            'items.*.order' => ['required', 'integer', 'min:0'],
            'items.*.is_active' => ['sometimes', 'boolean'],
            'items.*.default_visible' => ['sometimes', 'boolean'],
            'items.*.opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        $updatedCount = 0;

        DB::transaction(function () use ($validated, &$updatedCount) {
            $ids = collect($validated['items'])->pluck('id')->all();
            $layersById = Layer::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($validated['items'] as $item) {
                $layer = $layersById->get($item['id']);
                if (!$layer) {
                    continue;
                }

                $layer->order = (int) $item['order'];

                if (array_key_exists('is_active', $item)) {
                    $layer->is_active = (bool) $item['is_active'];
                    if (!$layer->is_active) {
                        $layer->default_visible = false;
                    }
                }

                if (array_key_exists('default_visible', $item) && $layer->is_active) {
                    $layer->default_visible = (bool) $item['default_visible'];
                }

                if (array_key_exists('opacity', $item)) {
                    $layer->opacity = round((float) $item['opacity'], 2);
                }

                if ($layer->isDirty()) {
                    $layer->save();
                    $updatedCount++;
                }
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Urutan dan konfigurasi layer berhasil disimpan.',
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Public / Authenticated active layers feed for Map Viewer.
     */
    public function activeLayers(): JsonResponse
    {
        $layers = Layer::active()->ordered()->get();

        return response()->json([
            'ok' => true,
            'data' => $layers,
        ]);
    }

    /**
     * Get layer attributes schema and sample feature values for styling & labeling.
     */
    public function attributes(Layer $layer): JsonResponse
    {
        $attributes = [];
        $sampleRows = [];
        $tableName = null;

        // 1. Cek apakah layer merujuk ke tabel internal PostgreSQL (MVT Martin / internal GIS)
        $candidates = array_filter([
            $layer->layer_name,
            preg_match('/\/martin\/([a-zA-Z0-9_\-]+)/', $layer->url ?? '', $m) ? $m[1] : null,
            strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $layer->layer_name ?? '')),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate && Schema::hasTable($candidate)) {
                $tableName = $candidate;
                break;
            }
        }

        if ($tableName) {
            $columns = DB::select("
                SELECT column_name, data_type 
                FROM information_schema.columns 
                WHERE table_schema = 'public' AND table_name = ?
                ORDER BY ordinal_position
            ", [$tableName]);

            $excludedCols = ['geom', 'geometry', 'wkb_geometry', 'created_at', 'updated_at', 'password'];
            $colNames = [];
            foreach ($columns as $col) {
                if (!in_array(strtolower($col->column_name), $excludedCols, true)) {
                    $colNames[] = $col->column_name;
                }
            }

            if (!empty($colNames)) {
                try {
                    $rows = DB::table($tableName)->select($colNames)->limit(25)->get();
                    $sampleRows = $rows->map(fn($r) => (array)$r)->toArray();
                } catch (\Throwable $e) {
                    $sampleRows = [];
                }

                foreach ($columns as $col) {
                    if (in_array(strtolower($col->column_name), $excludedCols, true)) {
                        continue;
                    }
                    $name = $col->column_name;
                    $sampleVal = null;
                    foreach ($sampleRows as $row) {
                        if (isset($row[$name]) && $row[$name] !== null && $row[$name] !== '') {
                            $sampleVal = is_string($row[$name]) ? $row[$name] : json_encode($row[$name]);
                            break;
                        }
                    }

                    if ($sampleVal === null) {
                        try {
                            $val = DB::table($tableName)->whereNotNull($name)->where($name, '!=', '')->value($name);
                            if ($val !== null) {
                                $sampleVal = is_string($val) ? $val : json_encode($val);
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }

                    $uniqueValues = [];
                    $numericValues = [];
                    foreach ($sampleRows as $row) {
                        if (isset($row[$name]) && $row[$name] !== null && $row[$name] !== '') {
                            $strVal = is_string($row[$name]) ? trim($row[$name]) : json_encode($row[$name]);
                            if ($strVal !== '' && !in_array($strVal, $uniqueValues, true) && count($uniqueValues) < 20) {
                                $uniqueValues[] = $strVal;
                            }
                            if (is_numeric($row[$name])) {
                                $numericValues[] = (float) $row[$name];
                            }
                        }
                    }

                    $attributes[] = [
                        'name' => $name,
                        'type' => $col->data_type,
                        'sample_value' => $sampleVal,
                        'unique_values' => $uniqueValues,
                        'min_value' => !empty($numericValues) ? min($numericValues) : null,
                        'max_value' => !empty($numericValues) ? max($numericValues) : null,
                    ];
                }
            }
        }

        // 2. Jika layer merupakan WMS Geoportal atau belum ada atribut internal
        if (empty($attributes) && ($layer->geoportal_id || str_contains(strtolower($layer->url ?? ''), 'geoportal'))) {
            try {
                $cswService = app(\App\Services\Gis\GeoportalCswClientService::class);
                $wfsData = $cswService->fetchLayerAttributes($layer);
                if (!empty($wfsData['attributes'])) {
                    $sampleData = $wfsData['sample_data'] ?? [];
                    foreach ($wfsData['attributes'] as $attr) {
                        $attrName = $attr['name'] ?? '';
                        if (!$attrName) continue;
                        $sampleVal = null;
                        foreach ($sampleData as $sRow) {
                            if (isset($sRow[$attrName]) && $sRow[$attrName] !== null && $sRow[$attrName] !== '') {
                                $sampleVal = (string)$sRow[$attrName];
                                break;
                            }
                        }
                        $uniqueValues = [];
                        $numericValues = [];
                        foreach ($sampleData as $sRow) {
                            if (isset($sRow[$attrName]) && $sRow[$attrName] !== null && $sRow[$attrName] !== '') {
                                $strVal = is_scalar($sRow[$attrName]) ? trim((string) $sRow[$attrName]) : json_encode($sRow[$attrName]);
                                if ($strVal !== '' && !in_array($strVal, $uniqueValues, true) && count($uniqueValues) < 20) {
                                    $uniqueValues[] = $strVal;
                                }
                                if (is_numeric($sRow[$attrName])) {
                                    $numericValues[] = (float) $sRow[$attrName];
                                }
                            }
                        }
                        $attributes[] = [
                            'name' => $attrName,
                            'type' => $attr['type'] ?? 'string',
                            'sample_value' => $sampleVal,
                            'unique_values' => $uniqueValues,
                            'min_value' => !empty($numericValues) ? min($numericValues) : null,
                            'max_value' => !empty($numericValues) ? max($numericValues) : null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore fallback to generic
            }
        }

        // 3. Fallback jika tabel atau WFS belum terhubung
        if (empty($attributes)) {
            $attributes = [
                ['name' => 'namobj', 'type' => 'string', 'sample_value' => $layer->name],
                ['name' => 'nama_desa', 'type' => 'string', 'sample_value' => 'Karangmangu'],
                ['name' => 'nama_ruas', 'type' => 'string', 'sample_value' => 'Ruas Poros Kanten - Mlaten'],
                ['name' => 'wadmkc', 'type' => 'string', 'sample_value' => 'Kecamatan Trucuk'],
                ['name' => 'wadmkk', 'type' => 'string', 'sample_value' => 'Kabupaten Bojonegoro'],
                ['name' => 'kondisi', 'type' => 'string', 'sample_value' => 'Baik'],
                ['name' => 'keterangan', 'type' => 'string', 'sample_value' => 'Jalan Poros Antar Desa'],
                ['name' => 'id', 'type' => 'integer', 'sample_value' => '1'],
            ];
        }

        return response()->json([
            'ok' => true,
            'layer_id' => $layer->id,
            'layer_name' => $layer->name,
            'source_table' => $tableName,
            'data' => $attributes,
        ]);
    }

    /**
     * Query full GeoJSON geometry for a single feature from internal PostGIS table.
     * Used by map viewer for seamless highlight without MVT tile boundary cuts.
     */
    public function featureGeometry(Request $request, string $layer): JsonResponse
    {
        $legacyTableAliases = [
            '01a1589c-8c15-70b6-a4fe-35517f888b36' => 'infrastruktur_segmen',
            '01a1589c-8bf1-7294-9cf7-41ffda7e71f6' => 'jalan_porosdesa',
            '01a15e28-a042-71a0-b111-ed7d9256e5bf' => 'bataswilayah_kabupaten',
        ];

        $layerNameHint = trim((string) ($request->query('layer_name') ?? $request->query('table') ?? ''));

        $layerModel = null;
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $layer)) {
            $layerModel = Layer::find($layer);
        } else {
            $layerModel = Layer::where('layer_name', $layer)->orWhere('name', $layer)->first();
        }

        if (!$layerModel && $layerNameHint !== '') {
            $layerModel = Layer::where('layer_name', $layerNameHint)->orWhere('name', $layerNameHint)->first();
        }

        $tableCandidates = [];
        if ($layerModel) {
            $tableCandidates[] = $layerModel->layer_name;
            if (preg_match('/\/martin\/([a-zA-Z0-9_\-]+)/', $layerModel->url ?? '', $m)) {
                $tableCandidates[] = $m[1];
            }
            $tableCandidates[] = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $layerModel->layer_name ?? ''));
        }

        if ($layerNameHint !== '') {
            $tableCandidates[] = $layerNameHint;
            $tableCandidates[] = strtolower(str_replace(['-', ' '], '_', $layerNameHint));
        }

        if (isset($legacyTableAliases[$layer])) {
            $tableCandidates[] = $legacyTableAliases[$layer];
        }

        $tableCandidates[] = $layer;
        $tableCandidates[] = strtolower(str_replace(['-', ' '], '_', $layer));

        $tableName = null;
        foreach (array_unique(array_filter($tableCandidates)) as $candidate) {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $candidate) && Schema::hasTable($candidate)) {
                $tableName = $candidate;
                break;
            }
        }

        if (!$tableName) {
            return response()->json([
                'ok' => false,
                'message' => 'Layer or underlying table not found',
            ], 404);
        }

        $driver = DB::connection()->getDriverName();
        $geomCol = null;
        $colTypes = [];

        if ($driver === 'pgsql') {
            $cols = DB::select("
                SELECT column_name, udt_name 
                FROM information_schema.columns 
                WHERE table_schema = 'public' AND table_name = ?
            ", [$tableName]);

            foreach ($cols as $c) {
                $colTypes[$c->column_name] = strtolower((string) $c->udt_name);
            }

            $colNames = array_keys($colTypes);
            foreach (['geom', 'geometry', 'the_geom', 'wkb_geometry'] as $candGeom) {
                if (in_array($candGeom, $colNames, true)) {
                    $geomCol = $candGeom;
                    break;
                }
            }

            if (!$geomCol) {
                foreach ($cols as $c) {
                    if (in_array($c->udt_name, ['geometry', 'geography'], true)) {
                        $geomCol = $c->column_name;
                        break;
                    }
                }
            }
        } else {
            $colNames = Schema::getColumnListing($tableName);
            foreach ($colNames as $colName) {
                $colTypes[$colName] = Schema::getColumnType($tableName, $colName);
            }
            foreach (['geom', 'geometry', 'the_geom', 'wkb_geometry'] as $candGeom) {
                if (in_array($candGeom, $colNames, true)) {
                    $geomCol = $candGeom;
                    break;
                }
            }
        }

        if (!$geomCol) {
            return response()->json([
                'ok' => false,
                'message' => 'Spatial geometry column not found in table ' . $tableName,
            ], 422);
        }

        $featureId = $request->query('feature_id') ?? $request->query('id');
        $idColumn = $request->query('id_column');
        $namobj = $request->query('namobj') ?? $request->query('nama');
        $kodeRuas = $request->query('kode_ruas');
        $kecamatanFilter = trim((string) ($request->query('kecamatan') ?? ''));
        $desaFilter = trim((string) ($request->query('desa') ?? ''));

        $isCompatibleValue = static function (string $colType, string $val): bool {
            if ($colType === 'uuid') {
                return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $val) === 1;
            }
            if (in_array($colType, ['int2', 'int4', 'int8', 'integer', 'bigint', 'smallint'], true)) {
                return preg_match('/^-?\d+$/', $val) === 1;
            }
            return true;
        };

        $likeOp = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $fetchRow = static function ($builder) use ($driver, $geomCol) {
            return $driver === 'pgsql'
                ? $builder->selectRaw("ST_AsGeoJSON(ST_Transform({$geomCol}, 4326)) as geojson")->first()
                : $builder->select([$geomCol . ' as geojson'])->first();
        };

        $applyRegionScope = static function ($builder, bool $includeDesa = true) use ($colNames, $likeOp, $kecamatanFilter, $desaFilter): bool {
            $applied = false;
            $kecCol = in_array('kecamatan', $colNames, true) ? 'kecamatan' : (in_array('wadmkc', $colNames, true) ? 'wadmkc' : null);
            $desaCol = in_array('desa', $colNames, true) ? 'desa' : (in_array('wadmkd', $colNames, true) ? 'wadmkd' : null);

            if ($kecCol && $kecamatanFilter !== '') {
                $cleanKec = trim((string) preg_replace('/^kecamatan\s+/i', '', $kecamatanFilter));
                if ($cleanKec !== '') {
                    $builder->where($kecCol, $likeOp, '%' . $cleanKec . '%');
                    $applied = true;
                }
            }
            if ($includeDesa && $desaCol && $desaFilter !== '') {
                $cleanDesa = trim((string) preg_replace('/^(desa|kelurahan)\s+/i', '', $desaFilter));
                if ($cleanDesa !== '') {
                    $builder->where($desaCol, $likeOp, '%' . $cleanDesa . '%');
                    $applied = true;
                }
            }

            return $applied;
        };

        $row = null;
        $attemptedLookup = false;

        if ($featureId !== null && $featureId !== '') {
            $idStr = trim((string) $featureId);
            $idCandidates = ($idColumn && in_array($idColumn, $colNames, true))
                ? [$idColumn]
                : ['id', 'gid', 'objectid', 'OBJECTID', 'fid', 'id_segmen', 'kode_ruas', 'kode_desa', 'kode_kecamatan'];

            foreach ($idCandidates as $idCandidate) {
                if (in_array($idCandidate, $colNames, true) && $isCompatibleValue($colTypes[$idCandidate] ?? 'varchar', $idStr)) {
                    $attemptedLookup = true;
                    $candidateRow = $fetchRow(DB::table($tableName)->where($idCandidate, $idStr));
                    if ($candidateRow && !empty($candidateRow->geojson)) {
                        $row = $candidateRow;
                        break;
                    }
                }
            }
        }

        if ((!$row || empty($row->geojson)) && $kodeRuas !== null && trim((string) $kodeRuas) !== '' && in_array('kode_ruas', $colNames, true)) {
            $kodeStr = trim((string) $kodeRuas);
            if ($isCompatibleValue($colTypes['kode_ruas'] ?? 'varchar', $kodeStr)) {
                $attemptedLookup = true;
                $scopedQuery = DB::table($tableName)->where('kode_ruas', $kodeStr);
                if ($applyRegionScope($scopedQuery, true)) {
                    $row = $fetchRow($scopedQuery);
                }
                if (!$row || empty($row->geojson)) {
                    $row = $fetchRow(DB::table($tableName)->where('kode_ruas', $kodeStr));
                }
            }
        }

        if ((!$row || empty($row->geojson)) && $namobj !== null && trim((string) $namobj) !== '') {
            $availableNameCols = array_values(array_intersect(
                ['namobj', 'nama_ruas', 'nama_desa', 'nama_kecamatan', 'wadmkc', 'wadmkd', 'nama_jalan', 'nama_lokasi', 'wadmkk', 'nama'],
                $colNames
            ));

            if (!empty($availableNameCols)) {
                $attemptedLookup = true;
                $cleanName = trim((string) $namobj);
                $term = '%' . $cleanName . '%';

                $runNameLookup = static function (bool $exact, bool $useDesa, bool $useRegion) use ($tableName, $availableNameCols, $likeOp, $cleanName, $term, $applyRegionScope, $fetchRow) {
                    $q = DB::table($tableName)->where(function ($sub) use ($availableNameCols, $exact, $likeOp, $cleanName, $term) {
                        foreach ($availableNameCols as $nameCol) {
                            if ($exact) {
                                $sub->orWhereRaw("LOWER(TRIM(CAST({$nameCol} AS TEXT))) = LOWER(?)", [$cleanName]);
                            } else {
                                $sub->orWhere($nameCol, $likeOp, $term);
                            }
                        }
                    });
                    if ($useRegion) {
                        $applyRegionScope($q, $useDesa);
                    }
                    return $fetchRow($q);
                };

                if ($kecamatanFilter !== '' || $desaFilter !== '') {
                    $row = $runNameLookup(true, true, true);
                    if (!$row || empty($row->geojson)) {
                        $row = $runNameLookup(false, true, true);
                    }
                    if ((!$row || empty($row->geojson)) && $kecamatanFilter !== '') {
                        $row = $runNameLookup(false, false, true);
                    }
                }
                if (!$row || empty($row->geojson)) {
                    $row = $runNameLookup(true, false, false) ?: $runNameLookup(false, false, false);
                }
            }
        }

        if (!$attemptedLookup) {
            return response()->json([
                'ok' => false,
                'message' => 'No matching feature_id or namobj parameter provided',
            ], 400);
        }

        if (!$row || empty($row->geojson)) {
            return response()->json([
                'ok' => false,
                'message' => 'Feature geometry not found',
            ], 404);
        }

        $geometry = is_string($row->geojson) ? json_decode($row->geojson, true) : $row->geojson;

        return response()->json([
            'ok' => true,
            'table' => $tableName,
            'geometry' => $geometry,
        ]);
    }
}

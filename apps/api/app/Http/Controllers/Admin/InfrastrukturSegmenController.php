<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SplitInfrastrukturSegmenRequest;
use App\Http\Requests\Admin\StoreInfrastrukturSegmenRequest;
use App\Http\Requests\Admin\UpdateInfrastrukturSegmenGeometryRequest;
use App\Http\Requests\Admin\UpdateInfrastrukturSegmenRequest;
use App\Http\Resources\InfrastrukturSegmenResource;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\InfrastrukturSegmen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class InfrastrukturSegmenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', InfrastrukturSegmen::class);

        $user = $request->user();
        $isBappeda = $user && $user->hasRole('verifierBappeda');
        $isAdmin   = $user && $user->hasRole('admin');
        $isRestricted = $user && !$isAdmin && !$isBappeda;

        // Validasi dan proteksi otoritas wilayah kerja pengguna
        $effectiveKecamatanId = $request->filled('id_kecamatan') ? (int) $request->input('id_kecamatan') : null;
        $effectiveDesaId      = $request->filled('id_desa') ? (int) $request->input('id_desa') : null;

        if ($isRestricted) {
            if ($user->id_desa) {
                // Operator Desa terkunci total pada desa dan kecamatannya
                $effectiveDesaId      = (int) $user->id_desa;
                $effectiveKecamatanId = (int) $user->id_kecamatan;
            } elseif ($user->id_kecamatan) {
                // Verifier Kecamatan terkunci pada kecamatannya
                $effectiveKecamatanId = (int) $user->id_kecamatan;
                // Hanya izinkan desa yang benar-benar berada di kecamatan tersebut
                if ($effectiveDesaId) {
                    $validDesa = DB::table('bataswilayah_desa')
                        ->where('id', $effectiveDesaId)
                        ->where('id_kecamatan', $user->id_kecamatan)
                        ->exists();
                    if (!$validDesa) {
                        $effectiveDesaId = null;
                    }
                }
            }
        }

        $baseQuery = InfrastrukturSegmen::query()->forUser($user);

        // Filter tipe infrastruktur
        if ($request->filled('tipe_kode')) {
            $baseQuery->where('infrastruktur_segmen.tipe_kode', $request->input('tipe_kode'));
        }

        // Filter tahun pembangunan
        if ($request->filled('tahun_pembangunan')) {
            $baseQuery->where('infrastruktur_segmen.tahun_pembangunan', $request->input('tahun_pembangunan'));
        } elseif ($request->filled('tahun')) {
            $baseQuery->where('infrastruktur_segmen.tahun_pembangunan', $request->input('tahun'));
        }

        // Filter kondisi fisik
        if ($request->filled('kondisi')) {
            $baseQuery->where('infrastruktur_segmen.kondisi', $request->input('kondisi'));
        }

        // Filter jenis perkerasan
        if ($request->filled('jenis_perkerasan')) {
            $baseQuery->where('infrastruktur_segmen.jenis_perkerasan', $request->input('jenis_perkerasan'));
        } elseif ($request->filled('perkerasan')) {
            $baseQuery->where('infrastruktur_segmen.jenis_perkerasan', $request->input('perkerasan'));
        }

        // Filter status jalan
        if ($request->filled('status_jalan')) {
            $val = $request->input('status_jalan');
            $baseQuery->where(function ($q) use ($val) {
                $q->where('infrastruktur_segmen.status_jalan', $val)
                  ->orWhere('infrastruktur_segmen.status_jalan', 'ilike', '%' . $val . '%');
            });
        }

        // Filter status kondisi (Eksisting / Riwayat)
        if ($request->filled('status_kondisi')) {
            $baseQuery->where('infrastruktur_segmen.status_kondisi', $request->input('status_kondisi'));
        }

        // Filter status aset
        if ($request->filled('status_aset')) {
            $val = $request->input('status_aset');
            $baseQuery->where(function ($q) use ($val) {
                $q->where('infrastruktur_segmen.status_aset', $val)
                  ->orWhere('infrastruktur_segmen.status_aset', 'ilike', '%' . $val . '%');
            });
        }

        // Filter sumber dana
        if ($request->filled('sumber_dana')) {
            $val = $request->input('sumber_dana');
            $baseQuery->where(function ($q) use ($val) {
                $q->where('infrastruktur_segmen.sumber_dana', $val)
                  ->orWhere('infrastruktur_segmen.sumber_dana', 'ilike', '%' . $val . '%');
            });
        }

        // Filter status verifikasi
        if ($request->filled('status_verifikasi')) {
            $baseQuery->where('infrastruktur_segmen.status_verifikasi', $request->input('status_verifikasi'));
        }

        // Filter wilayah yang sudah divalidasi otoritasnya
        if ($effectiveKecamatanId) {
            $baseQuery->where('infrastruktur_segmen.id_kecamatan', $effectiveKecamatanId);
        }

        if ($effectiveDesaId) {
            $baseQuery->where('infrastruktur_segmen.id_desa', $effectiveDesaId);
        }

        // Filter plotting anggaran
        if ($request->filled('plotting_id')) {
            $baseQuery->where('infrastruktur_segmen.plotting_id', $request->input('plotting_id'));
        }

        // Filter Bounding Box (Viewport Peta GIS)
        if ($request->filled('bbox')) {
            $bbox = explode(',', $request->input('bbox'));
            if (count($bbox) === 4) {
                $baseQuery->inBbox((float) $bbox[0], (float) $bbox[1], (float) $bbox[2], (float) $bbox[3]);
            }
        }

        // Search nama objek / keterangan
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $baseQuery->where(function ($q) use ($search) {
                $q->where('infrastruktur_segmen.namobj', 'ilike', $search)
                  ->orWhere('infrastruktur_segmen.keterangan', 'ilike', $search)
                  ->orWhere('infrastruktur_segmen.desa', 'ilike', $search)
                  ->orWhere('infrastruktur_segmen.kecamatan', 'ilike', $search);
            });
        }

        // Format Summary: stats spasial & Bounding Box (BBOX) terfilter untuk auto-fitbounds peta
        if ($request->input('format') === 'summary') {
            $stats = (clone $baseQuery)->whereNotNull('infrastruktur_segmen.geom')->selectRaw("
                COUNT(*) as total_segmen,
                COALESCE(ROUND(SUM(COALESCE(infrastruktur_segmen.panjang, ST_Length(infrastruktur_segmen.geom::geography)))::numeric, 2), 0) as total_panjang_meter,
                COALESCE(ROUND((SUM(COALESCE(infrastruktur_segmen.panjang, ST_Length(infrastruktur_segmen.geom::geography))) / 1000)::numeric, 3), 0) as total_panjang_km,
                ST_XMin(ST_Extent(infrastruktur_segmen.geom)) as min_x,
                ST_YMin(ST_Extent(infrastruktur_segmen.geom)) as min_y,
                ST_XMax(ST_Extent(infrastruktur_segmen.geom)) as max_x,
                ST_YMax(ST_Extent(infrastruktur_segmen.geom)) as max_y
            ")->first();

            $kondisiBreakdown = (clone $baseQuery)
                ->whereNotNull('infrastruktur_segmen.kondisi')
                ->selectRaw("infrastruktur_segmen.kondisi, COUNT(*) as jumlah, ROUND(SUM(COALESCE(infrastruktur_segmen.panjang, ST_Length(infrastruktur_segmen.geom::geography)))::numeric, 2) as panjang_meter")
                ->groupBy('infrastruktur_segmen.kondisi')
                ->orderByDesc('jumlah')
                ->get();

            $bbox = null;
            if ($stats && $stats->min_x !== null && $stats->min_y !== null && $stats->max_x !== null && $stats->max_y !== null) {
                $bbox = [
                    (float) $stats->min_x,
                    (float) $stats->min_y,
                    (float) $stats->max_x,
                    (float) $stats->max_y,
                ];
            } elseif ($effectiveDesaId) {
                $desaStats = DB::table('bataswilayah_desa')
                    ->where('id', $effectiveDesaId)
                    ->whereNotNull('geom')
                    ->selectRaw("ST_XMin(ST_Extent(geom)) as min_x, ST_YMin(ST_Extent(geom)) as min_y, ST_XMax(ST_Extent(geom)) as max_x, ST_YMax(ST_Extent(geom)) as max_y")
                    ->first();
                if ($desaStats && $desaStats->min_x !== null) {
                    $bbox = [
                        (float) $desaStats->min_x,
                        (float) $desaStats->min_y,
                        (float) $desaStats->max_x,
                        (float) $desaStats->max_y,
                    ];
                }
            } elseif ($effectiveKecamatanId) {
                $kecStats = DB::table('bataswilayah_kecamatan')
                    ->where('id', $effectiveKecamatanId)
                    ->whereNotNull('geom')
                    ->selectRaw("ST_XMin(ST_Extent(geom)) as min_x, ST_YMin(ST_Extent(geom)) as min_y, ST_XMax(ST_Extent(geom)) as max_x, ST_YMax(ST_Extent(geom)) as max_y")
                    ->first();
                if ($kecStats && $kecStats->min_x !== null) {
                    $bbox = [
                        (float) $kecStats->min_x,
                        (float) $kecStats->min_y,
                        (float) $kecStats->max_x,
                        (float) $kecStats->max_y,
                    ];
                }
            }

            if (!$bbox) {
                $bbox = [111.445, -7.452, 112.164, -6.981];
            }

            $totalSegmen = (int) ($stats->total_segmen ?? 0);

            return response()->json([
                'ok'                  => true,
                'total_segmen'        => $totalSegmen,
                'total_panjang_meter' => (float) ($stats->total_panjang_meter ?? 0),
                'total_panjang_km'    => (float) ($stats->total_panjang_km ?? 0),
                'kondisi'             => $kondisiBreakdown,
                'bbox'                => $bbox,
            ]);
        }

        $query = (clone $baseQuery)->withGeoJson();

        // Format GeoJSON FeatureCollection untuk layer peta OpenLayers / MapLibre
        if ($request->input('format') === 'geojson') {
            $records = $query->whereNotNull('infrastruktur_segmen.geom')->limit((int) $request->input('limit', 1500))->get();
            $features = $records->map(function ($segmen) {
                if (empty($segmen->geojson)) {
                    return null;
                }
                $geom = json_decode($segmen->geojson);
                if (!$geom || empty($geom->coordinates)) {
                    return null;
                }

                return [
                    'type'       => 'Feature',
                    'id'         => $segmen->id,
                    'geometry'   => $geom,
                    'properties' => [
                        'id'                    => $segmen->id,
                        'tipe_kode'             => $segmen->tipe_kode,
                        'namobj'                => $segmen->namobj,
                        'panjang'               => $segmen->panjang,
                        'panjang_meter_gis'     => $segmen->panjang_meter_gis,
                        'lebar'                 => $segmen->lebar,
                        'jenis_perkerasan'      => $segmen->jenis_perkerasan,
                        'status_jalan'          => $segmen->status_jalan,
                        'kondisi'               => $segmen->kondisi?->value ?? $segmen->kondisi,
                        'status_kondisi'        => $segmen->status_kondisi,
                        'tahun_pembangunan'     => $segmen->tahun_pembangunan,
                        'sumber_dana'           => $segmen->sumber_dana,
                        'status_verifikasi'     => $segmen->status_verifikasi?->value ?? $segmen->status_verifikasi,
                        'status_aset'           => $segmen->status_aset,
                        'desa'                  => $segmen->desa,
                        'kecamatan'             => $segmen->kecamatan,
                        'id_desa'               => $segmen->id_desa,
                        'id_kecamatan'          => $segmen->id_kecamatan,
                        'centroid'              => !empty($segmen->centroid) ? json_decode($segmen->centroid) : null,
                    ],
                ];
            })->filter()->values();

            return response()->json([
                'type'     => 'FeatureCollection',
                'features' => $features,
            ]);
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginated = $query->orderBy('infrastruktur_segmen.created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'ok'   => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::withGeoJson()
            ->with([
                'tipe:id,kode,nama,warna,ikon',
                'kecamatan:id,nama_kecamatan',
                'desa:id,nama_desa',
                'plotting:id,nama_kegiatan,target_pagu_anggaran',
                'creator:uuid,name,email',
                'verifierKecamatan:uuid,name,email',
                'verifierBappeda:uuid,name,email',
                'parent:id,namobj,tipe_kode',
                'children:id,parent_id,namobj,panjang,status_verifikasi',
            ])
            ->where('infrastruktur_segmen.id', $id)
            ->first();

        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('view', $segmen);

        return response()->json([
            'ok'   => true,
            'data' => $segmen,
        ]);
    }

    public function store(StoreInfrastrukturSegmenRequest $request): JsonResponse
    {
        Gate::authorize('create', InfrastrukturSegmen::class);

        $user = $request->user();
        $validated = $request->validated();

        // Auto-lock wilayah jika pengguna memiliki pembatasan wilayah
        if ($user && !$user->hasRole('admin') && !$user->hasRole('verifierBappeda')) {
            if ($user->id_kecamatan) {
                $validated['id_kecamatan'] = $user->id_kecamatan;
            }
            if ($user->id_desa) {
                $validated['id_desa'] = $user->id_desa;
            }
        }

        // Parsing dan validasi GeoJSON geometri
        $geoJsonString = is_string($validated['geometry'])
            ? $validated['geometry']
            : json_encode($validated['geometry']);

        try {
            $parsed = json_decode($geoJsonString, true);
            if (!isset($parsed['type']) || !in_array($parsed['type'], ['LineString', 'MultiLineString'], true)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Tipe geometri harus berupa LineString atau MultiLineString.',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Format GeoJSON geometri tidak valid: ' . $e->getMessage(),
            ], 422);
        }

        // Ambil cache nama wilayah
        $desa = BatasWilayahDesa::find($validated['id_desa']);
        $kecamatan = BatasWilayahKecamatan::find($validated['id_kecamatan']);

        $segmen = new InfrastrukturSegmen();
        $segmen->id = (string) Str::uuid();
        $segmen->fill($validated);
        $segmen->desa = $desa?->nama_desa;
        $segmen->kecamatan = $kecamatan?->nama_kecamatan;
        $segmen->user_id = $user->uuid;
        $segmen->created_by = $user->uuid;
        $segmen->created_by_role = $user->roles->first()?->name ?? 'user';
        $segmen->status_verifikasi = 'draft';

        $pdo = DB::getPdo();
        // Simpan ke PostGIS SRID 4326
        $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON(" . $pdo->quote($geoJsonString) . "), 4326)");
        $segmen->save();

        $fresh = InfrastrukturSegmen::withGeoJson()->where('id', $segmen->id)->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Segmen infrastruktur berhasil disimpan sebagai draft.',
            'data'    => $fresh,
        ], 201);
    }

    public function update(UpdateInfrastrukturSegmenRequest $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('update', $segmen);

        $validated = $request->validated();

        $segmen->fill($validated);

        if (!empty($validated['geometry'])) {
            $geoJsonString = is_string($validated['geometry'])
                ? $validated['geometry']
                : json_encode($validated['geometry']);

            $pdo = DB::getPdo();
            $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON(" . $pdo->quote($geoJsonString) . "), 4326)");

            if (!isset($validated['panjang'])) {
                $lenRes = DB::selectOne("SELECT ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography)::numeric, 2) AS length_meters", [$geoJsonString]);
                if ($lenRes) {
                    $segmen->panjang = (float) ($lenRes->length_meters ?? 0);
                }
            }
        }

        // Perbarui cache wilayah jika id_desa atau id_kecamatan berubah
        if (!empty($validated['id_desa'])) {
            $desa = BatasWilayahDesa::find($validated['id_desa']);
            $segmen->desa = $desa?->nama_desa;
        }

        if (!empty($validated['id_kecamatan'])) {
            $kecamatan = BatasWilayahKecamatan::find($validated['id_kecamatan']);
            $segmen->kecamatan = $kecamatan?->nama_kecamatan;
        }

        $segmen->save();

        $fresh = InfrastrukturSegmen::withGeoJson()->where('id', $segmen->id)->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Segmen infrastruktur berhasil diperbarui.',
            'data'    => $fresh,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('delete', $segmen);

        if ($segmen->children()->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen ini tidak dapat dihapus karena menjadi parent dari segmen lain.',
            ], 422);
        }

        if (DB::table('monitoring_realisasi_items')->where('id_segmen', $id)->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen ini tidak dapat dihapus karena sudah terikat pada Berita Acara monitoring realisasi.',
            ], 422);
        }

        $segmen->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Segmen infrastruktur berhasil dihapus.',
        ]);
    }

    public function updateGeometry(UpdateInfrastrukturSegmenGeometryRequest $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('update', $segmen);

        $geoJsonInput = $request->input('geometry');
        $geoJsonString = is_string($geoJsonInput) ? $geoJsonInput : json_encode($geoJsonInput);

        try {
            $parsed = json_decode($geoJsonString, true);
            if (isset($parsed['type']) && $parsed['type'] === 'Feature' && isset($parsed['geometry'])) {
                $parsed = $parsed['geometry'];
                $geoJsonString = json_encode($parsed);
            }

            if (!isset($parsed['type']) || !in_array($parsed['type'], ['LineString', 'MultiLineString'], true)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Tipe geometri harus berupa LineString atau MultiLineString.',
                ], 422);
            }

            if (empty($parsed['coordinates']) || !is_array($parsed['coordinates']) || count($parsed['coordinates']) < 2) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Geometri harus memiliki minimal 2 titik koordinat.',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Format GeoJSON geometri tidak valid: ' . $e->getMessage(),
            ], 422);
        }

        $lengthResult = DB::selectOne("SELECT ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography)::numeric, 2) AS length_meters", [$geoJsonString]);
        $panjang = (float) ($lengthResult->length_meters ?? 0);

        DB::transaction(function () use ($segmen, $geoJsonString, $panjang) {
            $pdo = DB::getPdo();
            $quotedGeo = $pdo->quote($geoJsonString);

            $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo}), 4326)");
            $segmen->panjang = $panjang;
            $segmen->save();

            app(\App\Services\AuditLogService::class)->log(
                event: 'update_geometry',
                module: 'infrastruktur-segmen',
                auditable: $segmen,
                description: "Memperbarui koordinat geometri spasial segmen \"{$segmen->namobj}\"",
                meta: [
                    'segmen_id' => $segmen->id,
                    'panjang'   => $panjang,
                ]
            );
        });

        $fresh = InfrastrukturSegmen::withGeoJson()->where('id', $segmen->id)->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Geometri segmen berhasil diperbarui.',
            'data'    => $fresh,
        ]);
    }

    public function split(SplitInfrastrukturSegmenRequest $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('split', $segmen);

        if (DB::table('monitoring_realisasi_items')->where('id_segmen', $id)->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen ini sudah terikat pada Berita Acara realisasi sehingga tidak dapat di-split.',
            ], 422);
        }

        $createdFeatures = [];
        $user = $request->user();

        DB::transaction(function () use ($segmen, $request, $user, &$createdFeatures) {
            if ($request->filled('parts')) {
                $p1 = $request->input('parts.0');
                $p2 = $request->input('parts.1');

                $geo1 = is_string($p1['geometry']) ? $p1['geometry'] : json_encode($p1['geometry']);
                $geo2 = is_string($p2['geometry']) ? $p2['geometry'] : json_encode($p2['geometry']);

                $name1 = $p1['namobj'] ?? ($segmen->namobj . ' (Bagian 1)');
                $name2 = $p2['namobj'] ?? ($segmen->namobj . ' (Bagian 2)');

                $pdo = DB::getPdo();
                $quotedGeo1 = $pdo->quote($geo1);
                $quotedGeo2 = $pdo->quote($geo2);

                $len1Result = DB::selectOne("SELECT ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography)::numeric, 2) AS length_meters", [$geo1]);
                $len2Result = DB::selectOne("SELECT ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography)::numeric, 2) AS length_meters", [$geo2]);

                $segmen->namobj = $name1;
                $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo1}), 4326)");
                $segmen->panjang = (float) ($len1Result->length_meters ?? 0);
                $segmen->status_parent = true;
                $segmen->save();

                $newSegmen = new InfrastrukturSegmen();
                $newSegmen->id = (string) Str::uuid();
                $newSegmen->parent_id = $segmen->parent_id;
                $newSegmen->tipe_kode = $segmen->tipe_kode;
                $newSegmen->namobj = $name2;
                $newSegmen->lebar = $segmen->lebar;
                $newSegmen->jenis_perkerasan = $segmen->jenis_perkerasan;
                $newSegmen->status_jalan = $segmen->status_jalan;
                $newSegmen->kondisi = $segmen->kondisi;
                $newSegmen->status_kondisi = $segmen->status_kondisi;
                $newSegmen->tahun_pembangunan = $segmen->tahun_pembangunan;
                $newSegmen->sumber_dana = $segmen->sumber_dana;
                $newSegmen->keterangan = $segmen->keterangan;
                $newSegmen->status_aset = $segmen->status_aset;
                $newSegmen->sumber_data = $segmen->sumber_data;
                $newSegmen->desa = $segmen->desa;
                $newSegmen->kecamatan = $segmen->kecamatan;
                $newSegmen->id_desa = $segmen->id_desa;
                $newSegmen->id_kecamatan = $segmen->id_kecamatan;
                $newSegmen->plotting_id = $segmen->plotting_id;
                $newSegmen->status_verifikasi = 'draft';
                $newSegmen->user_id = $user->uuid;
                $newSegmen->created_by = $user->uuid;
                $newSegmen->created_by_role = $user->roles->first()?->name ?? 'user';
                $newSegmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo2}), 4326)");
                $newSegmen->panjang = (float) ($len2Result->length_meters ?? 0);
                $newSegmen->save();
            } else {
                $point = $request->input('point');
                if (!$point || count($point) < 2) {
                    abort(422, 'Titik koordinat pemotongan (point) wajib disertakan [lon, lat].');
                }
                $lon = (float) $point[0];
                $lat = (float) $point[1];

                $splitQuery = DB::selectOne("
                    SELECT 
                        fraction,
                        ST_AsGeoJSON(ST_LineSubstring(line_geom, 0, fraction)) AS part1_geojson,
                        ROUND((ST_Length(ST_LineSubstring(line_geom, 0, fraction)::geography))::numeric, 2) AS part1_panjang,
                        ST_AsGeoJSON(ST_LineSubstring(line_geom, fraction, 1)) AS part2_geojson,
                        ROUND((ST_Length(ST_LineSubstring(line_geom, fraction, 1)::geography))::numeric, 2) AS part2_panjang
                    FROM (
                        SELECT 
                            line_geom,
                            ST_LineLocatePoint(
                                line_geom, 
                                ST_ClosestPoint(line_geom, ST_SetSRID(ST_Point(?, ?), 4326))
                            ) AS fraction
                        FROM (
                            SELECT 
                                CASE 
                                    WHEN ST_GeometryType(ST_LineMerge(geom)) = 'ST_LineString' THEN ST_LineMerge(geom)
                                    ELSE ST_GeometryN(geom, 1)
                                END AS line_geom
                            FROM infrastruktur_segmen
                            WHERE id = ?
                        ) g
                    ) sub
                ", [$lon, $lat, $segmen->id]);

                if (!$splitQuery || empty($splitQuery->part1_geojson) || empty($splitQuery->part2_geojson)) {
                    abort(422, 'Gagal memproses pemotongan geometri garis segmen.');
                }

                $fraction = (float) ($splitQuery->fraction ?? 0);
                if ($fraction <= 0.005 || $fraction >= 0.995) {
                    abort(422, 'Titik pemotongan terlalu dekat dengan ujung segmen. Silakan pilih titik di badan garis.');
                }

                $name1 = $request->input('part1.namobj') ?: ($segmen->namobj . ' (Bagian 1)');
                $name2 = $request->input('part2.namobj') ?: ($segmen->namobj . ' (Bagian 2)');

                $part1Geo = $splitQuery->part1_geojson;
                $part2Geo = $splitQuery->part2_geojson;

                $pdo = DB::getPdo();
                $quotedGeo1 = $pdo->quote($part1Geo);
                $quotedGeo2 = $pdo->quote($part2Geo);

                $segmen->namobj = $name1;
                $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo1}), 4326)");
                $segmen->panjang = (float) $splitQuery->part1_panjang;
                $segmen->status_parent = true;
                $segmen->save();

                $newSegmen = new InfrastrukturSegmen();
                $newSegmen->id = (string) Str::uuid();
                $newSegmen->parent_id = $segmen->parent_id;
                $newSegmen->tipe_kode = $segmen->tipe_kode;
                $newSegmen->namobj = $name2;
                $newSegmen->lebar = $segmen->lebar;
                $newSegmen->jenis_perkerasan = $segmen->jenis_perkerasan;
                $newSegmen->status_jalan = $segmen->status_jalan;
                $newSegmen->kondisi = $segmen->kondisi;
                $newSegmen->status_kondisi = $segmen->status_kondisi;
                $newSegmen->tahun_pembangunan = $segmen->tahun_pembangunan;
                $newSegmen->sumber_dana = $segmen->sumber_dana;
                $newSegmen->keterangan = $segmen->keterangan;
                $newSegmen->status_aset = $segmen->status_aset;
                $newSegmen->sumber_data = $segmen->sumber_data;
                $newSegmen->desa = $segmen->desa;
                $newSegmen->kecamatan = $segmen->kecamatan;
                $newSegmen->id_desa = $segmen->id_desa;
                $newSegmen->id_kecamatan = $segmen->id_kecamatan;
                $newSegmen->plotting_id = $segmen->plotting_id;
                $newSegmen->status_verifikasi = 'draft';
                $newSegmen->user_id = $user->uuid;
                $newSegmen->created_by = $user->uuid;
                $newSegmen->created_by_role = $user->roles->first()?->name ?? 'user';
                $newSegmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo2}), 4326)");
                $newSegmen->panjang = (float) $splitQuery->part2_panjang;
                $newSegmen->save();
            }

            $createdFeatures['part1'] = InfrastrukturSegmen::withGeoJson()->find($segmen->id);
            $createdFeatures['part2'] = InfrastrukturSegmen::withGeoJson()->find($newSegmen->id);

            app(\App\Services\AuditLogService::class)->log(
                event: 'split',
                module: 'infrastruktur-segmen',
                auditable: $segmen,
                description: "Memecah (split) segmen \"{$segmen->namobj}\" menjadi 2 bagian (\"{$newSegmen->namobj}\")",
                meta: [
                    'original_id' => $segmen->id,
                    'new_part_id' => $newSegmen->id,
                ]
            );
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Segmen berhasil dipecah (split) menjadi 2 bagian.',
            'data'    => $createdFeatures,
        ]);
    }
}



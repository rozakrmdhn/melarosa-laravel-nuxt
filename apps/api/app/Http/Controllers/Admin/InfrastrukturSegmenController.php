<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInfrastrukturSegmenRequest;
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

        // Filter kondisi fisik
        if ($request->filled('kondisi')) {
            $baseQuery->where('infrastruktur_segmen.kondisi', $request->input('kondisi'));
        }

        // Filter jenis perkerasan
        if ($request->filled('jenis_perkerasan')) {
            $baseQuery->where('infrastruktur_segmen.jenis_perkerasan', $request->input('jenis_perkerasan'));
        }

        // Filter status jalan
        if ($request->filled('status_jalan')) {
            $baseQuery->where('infrastruktur_segmen.status_jalan', $request->input('status_jalan'));
        }

        // Filter status kondisi (Eksisting / Riwayat)
        if ($request->filled('status_kondisi')) {
            $baseQuery->where('infrastruktur_segmen.status_kondisi', $request->input('status_kondisi'));
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

            $totalSegmen = (clone $baseQuery)->count();

            return response()->json([
                'ok'                  => true,
                'total_segmen'        => (int) $totalSegmen,
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
}



<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasWilayahKecamatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BatasWilayahKecamatanController extends Controller
{
    /**
     * Display a listing of subdistrict (kecamatan) boundaries.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BatasWilayahKecamatan::class);

        $user = $request->user();
        $query = BatasWilayahKecamatan::query()->forUser($user)->withGeoJson();

        // 1. Text Search Filter (nama kecamatan / pimpinan / nip)
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('bataswilayah_kecamatan.nama_kecamatan', 'ilike', $search)
                  ->orWhere('bataswilayah_kecamatan.nama_pimpinan', 'ilike', $search)
                  ->orWhere('bataswilayah_kecamatan.nip', 'like', $search);
            });
        }

        // 2. Viewport Bounding Box Filter (minX,minY,maxX,maxY)
        if ($request->filled('bbox')) {
            $coords = explode(',', $request->query('bbox'));
            if (count($coords) === 4) {
                $minX = (float) $coords[0];
                $minY = (float) $coords[1];
                $maxX = (float) $coords[2];
                $maxY = (float) $coords[3];
                $query->inBbox($minX, $minY, $maxX, $maxY);
            }
        }

        // 3. Determine format: 'summary', 'table', or 'geojson'
        $format = $request->query('format', 'geojson');

        if ($format === 'summary') {
            $whereClause = "";
            $bindings = [];
            if ($user && !$user->hasRole('admin') && $user->id_kecamatan) {
                $whereClause = "WHERE id = ?";
                $bindings = [$user->id_kecamatan];
            }

            $stats = DB::selectOne("
                SELECT 
                    COUNT(*) as total_kecamatan,
                    COALESCE(ROUND((SUM(ST_Area(geom::geography)) / 10000)::numeric, 2), 0) as total_luas_hektar,
                    ST_XMin(ST_Extent(geom)) as min_x,
                    ST_YMin(ST_Extent(geom)) as min_y,
                    ST_XMax(ST_Extent(geom)) as max_x,
                    ST_YMax(ST_Extent(geom)) as max_y
                FROM bataswilayah_kecamatan
                {$whereClause}
            ", $bindings);

            return response()->json([
                'ok' => true,
                'total_kecamatan' => (int) ($stats->total_kecamatan ?? 0),
                'total_luas_hektar' => (float) ($stats->total_luas_hektar ?? 0),
                'bbox' => [
                    (float) ($stats->min_x ?? 111.4),
                    (float) ($stats->min_y ?? -7.5),
                    (float) ($stats->max_x ?? 112.2),
                    (float) ($stats->max_y ?? -7.0),
                ],
            ]);
        }

        if ($format === 'series' || $format === 'options') {
            $whereClause = "";
            $bindings = [];
            if ($user && !$user->hasRole('admin') && $user->id_kecamatan) {
                $whereClause = "WHERE k.id = ?";
                $bindings = [$user->id_kecamatan];
            }

            $list = DB::select("
                SELECT 
                    k.id,
                    k.nama_kecamatan,
                    k.nama_pimpinan,
                    k.nama_jabatan,
                    COUNT(d.id)::int as jumlah_desa,
                    COALESCE(ROUND((ST_Area(k.geom::geography) / 10000)::numeric, 2), 0) as luas_hektar,
                    ST_XMin(ST_Extent(k.geom)) as min_x,
                    ST_YMin(ST_Extent(k.geom)) as min_y,
                    ST_XMax(ST_Extent(k.geom)) as max_x,
                    ST_YMax(ST_Extent(k.geom)) as max_y
                FROM bataswilayah_kecamatan k
                LEFT JOIN bataswilayah_desa d ON d.id_kecamatan = k.id
                {$whereClause}
                GROUP BY k.id, k.nama_kecamatan, k.nama_pimpinan, k.nama_jabatan, k.geom
                ORDER BY k.nama_kecamatan ASC
            ", $bindings);

            return response()->json([
                'ok' => true,
                'data' => array_map(fn ($row) => [
                    'id' => (int) $row->id,
                    'nama_kecamatan' => $row->nama_kecamatan,
                    'nama_pimpinan' => $row->nama_pimpinan,
                    'nama_jabatan' => $row->nama_jabatan,
                    'jumlah_desa' => (int) $row->jumlah_desa,
                    'luas_hektar' => (float) $row->luas_hektar,
                    'bbox' => [
                        (float) $row->min_x,
                        (float) $row->min_y,
                        (float) $row->max_x,
                        (float) $row->max_y,
                    ],
                ], $list),
            ]);
        }

        if ($format === 'table') {
            $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
            $paginated = $query->orderBy('nama_kecamatan', 'asc')->paginate($perPage);

            return response()->json([
                'ok' => true,
                'data' => $paginated->items(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ]);
        }

        // Default: GeoJSON FeatureCollection
        $items = $query->orderBy('nama_kecamatan', 'asc')->get();
        $features = $items->map(fn (BatasWilayahKecamatan $item) => $item->toGeoJsonFeature())->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Display the specified subdistrict boundary.
     */
    public function show(int $id): JsonResponse
    {
        $kec = BatasWilayahKecamatan::withGeoJson()->find($id);

        if (!$kec) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah kecamatan tidak ditemukan.',
            ], 404);
        }

        $this->authorize('view', $kec);

        return response()->json([
            'ok' => true,
            'data' => $kec->toGeoJsonFeature(),
        ]);
    }

    /**
     * Store a newly created subdistrict boundary in PostGIS.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BatasWilayahKecamatan::class);

        $validator = Validator::make($request->all(), [
            'nama_kecamatan' => 'required|string|max:250',
            'nama_pimpinan' => 'nullable|string|max:255',
            'nama_jabatan' => 'nullable|string|max:255',
            'nip' => 'nullable|string|max:50',
            'pangkat_gol' => 'nullable|string|max:100',
            'geometry' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $geoJsonString = is_string($validated['geometry']) 
            ? $validated['geometry'] 
            : json_encode($validated['geometry']);

        try {
            $isValid = DB::selectOne("SELECT ST_IsValid(ST_GeomFromGeoJSON(?)) as is_valid", [$geoJsonString]);
            if (!$isValid || !$isValid->is_valid) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Geometri GeoJSON tidak valid (invalid PostGIS geometry).',
                ], 422);
            }

            $kec = new BatasWilayahKecamatan();
            $kec->nama_kecamatan = $validated['nama_kecamatan'];
            $kec->nama_pimpinan = $validated['nama_pimpinan'] ?? null;
            $kec->nama_jabatan = $validated['nama_jabatan'] ?? 'Camat';
            $kec->nip = $validated['nip'] ?? null;
            $kec->pangkat_gol = $validated['pangkat_gol'] ?? null;
            $kec->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geoJsonString')), 4326)");
            $kec->save();

            $fresh = BatasWilayahKecamatan::withGeoJson()->find($kec->id);

            return response()->json([
                'ok' => true,
                'message' => 'Batas wilayah kecamatan berhasil ditambahkan.',
                'data' => $fresh->toGeoJsonFeature(),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal menyimpan geometri PostGIS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified subdistrict boundary in PostGIS.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kec = BatasWilayahKecamatan::find($id);

        if (!$kec) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah kecamatan tidak ditemukan.',
            ], 404);
        }

        $this->authorize('update', $kec);

        $validator = Validator::make($request->all(), [
            'nama_kecamatan' => 'sometimes|required|string|max:250',
            'nama_pimpinan' => 'nullable|string|max:255',
            'nama_jabatan' => 'nullable|string|max:255',
            'nip' => 'nullable|string|max:50',
            'pangkat_gol' => 'nullable|string|max:100',
            'geometry' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            if (isset($validated['nama_kecamatan'])) $kec->nama_kecamatan = $validated['nama_kecamatan'];
            if (array_key_exists('nama_pimpinan', $validated)) $kec->nama_pimpinan = $validated['nama_pimpinan'];
            if (array_key_exists('nama_jabatan', $validated)) $kec->nama_jabatan = $validated['nama_jabatan'];
            if (array_key_exists('nip', $validated)) $kec->nip = $validated['nip'];
            if (array_key_exists('pangkat_gol', $validated)) $kec->pangkat_gol = $validated['pangkat_gol'];

            if (!empty($validated['geometry'])) {
                $geoJsonString = is_string($validated['geometry']) 
                    ? $validated['geometry'] 
                    : json_encode($validated['geometry']);

                $isValid = DB::selectOne("SELECT ST_IsValid(ST_GeomFromGeoJSON(?)) as is_valid", [$geoJsonString]);
                if (!$isValid || !$isValid->is_valid) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Geometri GeoJSON tidak valid (invalid PostGIS geometry).',
                    ], 422);
                }

                $kec->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geoJsonString')), 4326)");
            }

            $kec->save();
            $fresh = BatasWilayahKecamatan::withGeoJson()->find($kec->id);

            return response()->json([
                'ok' => true,
                'message' => 'Batas wilayah kecamatan berhasil diperbarui.',
                'data' => $fresh->toGeoJsonFeature(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal memperbarui geometri PostGIS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified subdistrict boundary from PostGIS.
     */
    public function destroy(int $id): JsonResponse
    {
        $kec = BatasWilayahKecamatan::find($id);

        if (!$kec) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah kecamatan tidak ditemukan.',
            ], 404);
        }

        $this->authorize('delete', $kec);

        $kec->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Batas wilayah kecamatan berhasil dihapus.',
        ]);
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasWilayahDesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BatasWilayahDesaController extends Controller
{
    /**
     * Display a listing of village boundaries.
     * Can return a standard GeoJSON FeatureCollection (for WebGIS maps)
     * or a paginated JSON response (for administrative data tables).
     */
    public function index(Request $request): JsonResponse
    {
        $query = BatasWilayahDesa::query()->withGeoJson();

        // 1. Text Search Filter (nama desa / pimpinan)
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('bataswilayah_desa.nama_desa', 'ilike', $search)
                  ->orWhere('bataswilayah_desa.nama_pimpinan', 'ilike', $search)
                  ->orWhere('bataswilayah_desa.nip', 'like', $search);
            });
        }

        // 2. Kecamatan Filter
        if ($request->filled('id_kecamatan')) {
            $query->where('bataswilayah_desa.id_kecamatan', (int) $request->query('id_kecamatan'));
        }

        // 3. Viewport Bounding Box Filter (minX,minY,maxX,maxY)
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

        // 4. Determine format: 'summary', 'table', or 'geojson'
        $format = $request->query('format', 'geojson');

        if ($format === 'summary') {
            $stats = DB::selectOne("
                SELECT 
                    COUNT(*) as total_desa,
                    COALESCE(ROUND((SUM(ST_Area(geom::geography)) / 10000)::numeric, 2), 0) as total_luas_hektar,
                    ST_XMin(ST_Extent(geom)) as min_x,
                    ST_YMin(ST_Extent(geom)) as min_y,
                    ST_XMax(ST_Extent(geom)) as max_x,
                    ST_YMax(ST_Extent(geom)) as max_y
                FROM bataswilayah_desa
            ");

            return response()->json([
                'ok' => true,
                'total_desa' => (int) ($stats->total_desa ?? 0),
                'total_luas_hektar' => (float) ($stats->total_luas_hektar ?? 0),
                'bbox' => [
                    (float) ($stats->min_x ?? 111.4),
                    (float) ($stats->min_y ?? -7.5),
                    (float) ($stats->max_x ?? 112.2),
                    (float) ($stats->max_y ?? -7.0),
                ],
            ]);
        }

        if ($format === 'table') {
            $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
            $paginated = $query->orderBy('nama_desa', 'asc')->paginate($perPage);

            return response()->json([
                'ok' => true,
                'data' => $paginated->items(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ]);
        }

        // Default: GeoJSON FeatureCollection for MapLibre
        $items = $query->orderBy('nama_desa', 'asc')->get();
        $features = $items->map(fn (BatasWilayahDesa $item) => $item->toGeoJsonFeature())->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Display the specified village boundary.
     */
    public function show(int $id): JsonResponse
    {
        $desa = BatasWilayahDesa::withGeoJson()->find($id);

        if (!$desa) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah desa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $desa,
            'feature' => $desa->toGeoJsonFeature(),
        ]);
    }

    /**
     * Store a newly created village boundary in PostGIS.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_desa' => ['required', 'string', 'max:255'],
            'id_kecamatan' => ['nullable', 'integer'],
            'nama_pimpinan' => ['nullable', 'string', 'max:255'],
            'nama_jabatan' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:50'],
            'pangkat_gol' => ['nullable', 'string', 'max:100'],
            'geometry' => ['required'], // GeoJSON geometry array or string
        ]);

        $geoJsonString = is_string($validated['geometry'])
            ? $validated['geometry']
            : json_encode($validated['geometry']);

        // Validate geometry can be parsed by PostGIS
        try {
            $isValidGeom = DB::selectOne(
                'SELECT ST_IsValid(ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(?)), 4326)) as valid',
                [$geoJsonString]
            );

            if (!$isValidGeom || !$isValidGeom->valid) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Geometri poligon tidak valid (terdapat irisan diri atau koordinat salah).',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Format GeoJSON geometri tidak dapat diproses: ' . $e->getMessage(),
            ], 422);
        }

        $desa = new BatasWilayahDesa();
        $desa->nama_desa = $validated['nama_desa'];
        $desa->id_kecamatan = $validated['id_kecamatan'] ?? null;
        $desa->nama_pimpinan = $validated['nama_pimpinan'] ?? null;
        $desa->nama_jabatan = $validated['nama_jabatan'] ?? null;
        $desa->nip = $validated['nip'] ?? null;
        $desa->pangkat_gol = $validated['pangkat_gol'] ?? null;

        // Save geometry directly into PostGIS MultiPolygon SRID 4326
        $desa->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geoJsonString')), 4326)");
        $desa->save();

        $fresh = BatasWilayahDesa::withGeoJson()->find($desa->id);

        return response()->json([
            'ok' => true,
            'message' => 'Batas wilayah desa berhasil disimpan.',
            'data' => $fresh,
            'feature' => $fresh->toGeoJsonFeature(),
        ], 201);
    }

    /**
     * Update the specified village boundary and its geometry vertices.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $desa = BatasWilayahDesa::find($id);

        if (!$desa) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah desa tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_desa' => ['sometimes', 'required', 'string', 'max:255'],
            'id_kecamatan' => ['nullable', 'integer'],
            'nama_pimpinan' => ['nullable', 'string', 'max:255'],
            'nama_jabatan' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:50'],
            'pangkat_gol' => ['nullable', 'string', 'max:100'],
            'geometry' => ['nullable'], // Optional: only if edited on map
        ]);

        if (isset($validated['nama_desa'])) $desa->nama_desa = $validated['nama_desa'];
        if (array_key_exists('id_kecamatan', $validated)) $desa->id_kecamatan = $validated['id_kecamatan'];
        if (array_key_exists('nama_pimpinan', $validated)) $desa->nama_pimpinan = $validated['nama_pimpinan'];
        if (array_key_exists('nama_jabatan', $validated)) $desa->nama_jabatan = $validated['nama_jabatan'];
        if (array_key_exists('nip', $validated)) $desa->nip = $validated['nip'];
        if (array_key_exists('pangkat_gol', $validated)) $desa->pangkat_gol = $validated['pangkat_gol'];

        if (!empty($validated['geometry'])) {
            $geoJsonString = is_string($validated['geometry'])
                ? $validated['geometry']
                : json_encode($validated['geometry']);

            $desa->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geoJsonString')), 4326)");
        }

        $desa->save();

        $fresh = BatasWilayahDesa::withGeoJson()->find($desa->id);

        return response()->json([
            'ok' => true,
            'message' => 'Batas wilayah desa berhasil diperbarui.',
            'data' => $fresh,
            'feature' => $fresh->toGeoJsonFeature(),
        ]);
    }

    /**
     * Split a village polygon boundary into 2 or more new village entities.
     * Used for administrative village division (pemekaran desa).
     */
    public function split(Request $request, int $id): JsonResponse
    {
        $desa = BatasWilayahDesa::find($id);

        if (!$desa) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah desa induk tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'parts' => ['required', 'array', 'min:2'],
            'parts.*.nama_desa' => ['required', 'string', 'max:255'],
            'parts.*.geometry' => ['required'],
            'parts.*.nama_pimpinan' => ['nullable', 'string', 'max:255'],
            'parts.*.nama_jabatan' => ['nullable', 'string', 'max:255'],
            'parts.*.nip' => ['nullable', 'string', 'max:50'],
            'parts.*.pangkat_gol' => ['nullable', 'string', 'max:100'],
        ]);

        $createdFeatures = [];

        DB::transaction(function () use ($desa, $validated, &$createdFeatures) {
            // Part 0 updates the existing village record
            $part0 = $validated['parts'][0];
            $geo0 = is_string($part0['geometry']) ? $part0['geometry'] : json_encode($part0['geometry']);
            
            $desa->nama_desa = $part0['nama_desa'];
            if (isset($part0['nama_pimpinan'])) $desa->nama_pimpinan = $part0['nama_pimpinan'];
            if (isset($part0['nama_jabatan'])) $desa->nama_jabatan = $part0['nama_jabatan'];
            if (isset($part0['nip'])) $desa->nip = $part0['nip'];
            if (isset($part0['pangkat_gol'])) $desa->pangkat_gol = $part0['pangkat_gol'];
            $desa->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geo0')), 4326)");
            $desa->save();

            $createdFeatures[] = BatasWilayahDesa::withGeoJson()->find($desa->id)->toGeoJsonFeature();

            // Part 1+ create new village records
            for ($i = 1; $i < count($validated['parts']); $i++) {
                $part = $validated['parts'][$i];
                $geoPart = is_string($part['geometry']) ? $part['geometry'] : json_encode($part['geometry']);

                $newDesa = new BatasWilayahDesa();
                $newDesa->id_kecamatan = $desa->id_kecamatan;
                $newDesa->nama_desa = $part['nama_desa'];
                $newDesa->nama_pimpinan = $part['nama_pimpinan'] ?? null;
                $newDesa->nama_jabatan = $part['nama_jabatan'] ?? null;
                $newDesa->nip = $part['nip'] ?? null;
                $newDesa->pangkat_gol = $part['pangkat_gol'] ?? null;
                $newDesa->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geoPart')), 4326)");
                $newDesa->save();

                $createdFeatures[] = BatasWilayahDesa::withGeoJson()->find($newDesa->id)->toGeoJsonFeature();
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Pemekaran/split batas wilayah desa berhasil dilakukan.',
            'features' => $createdFeatures,
        ]);
    }

    /**
     * Remove the specified village boundary from PostGIS.
     */
    public function destroy(int $id): JsonResponse
    {
        $desa = BatasWilayahDesa::find($id);

        if (!$desa) {
            return response()->json([
                'ok' => false,
                'message' => 'Data batas wilayah desa tidak ditemukan.',
            ], 404);
        }

        $desa->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Batas wilayah desa berhasil dihapus.',
        ]);
    }

    /**
     * Generate Mapbox Vector Tile (MVT) for batas wilayah desa dataset.
     *
     * @param Request $request
     * @param int $z Level Zoom (0-22)
     * @param int $x Tile X coordinate
     * @param int $y Tile Y coordinate
     * @return \Illuminate\Http\Response
     */
    public function mvt(Request $request, int $z, int $x, int $y)
    {
        // 1. Basic coordinate validation
        $maxTile = (1 << $z) - 1;
        if ($z < 0 || $z > 22 || $x < 0 || $x > $maxTile || $y < 0 || $y > $maxTile) {
            return response('', 204)->header('Content-Type', 'application/x-protobuf');
        }

        // 2. PostGIS MVT Query
        // Uses ST_TileEnvelope for EPSG:3857 tile bounding box
        // and ST_AsMVTGeom to clip and transform geometry to tile coordinates (extent: 4096)
        $sql = "
            WITH bounds AS (
                SELECT ST_TileEnvelope(?, ?, ?) AS geom_3857
            ),
            mvt_geom AS (
                SELECT 
                    b.id,
                    b.id_kecamatan,
                    b.nama_desa,
                    b.nama_pimpinan,
                    b.nama_jabatan,
                    b.nip,
                    b.pangkat_gol,
                    ROUND((ST_Area(b.geom::geography) / 10000)::numeric, 2) as luas_hektar,
                    ROUND((ST_Perimeter(b.geom::geography))::numeric, 2) as keliling_meter,
                    ST_AsMVTGeom(
                        ST_Transform(b.geom, 3857),
                        bounds.geom_3857,
                        4096,
                        64,
                        true
                    ) AS geom
                FROM bataswilayah_desa b, bounds
                WHERE b.geom && ST_Transform(bounds.geom_3857, 4326)
            )
            SELECT ST_AsMVT(mvt_geom.*, 'batas_desa', 4096, 'geom') AS mvt
            FROM mvt_geom;
        ";

        $row = DB::selectOne($sql, [$z, $x, $y]);
        $raw = $row->mvt ?? null;
        $content = is_resource($raw) ? stream_get_contents($raw) : $raw;

        if (empty($content)) {
            return response('', 204)
                ->header('Content-Type', 'application/x-protobuf')
                ->header('Cache-Control', 'public, max-age=86400');
        }

        return response($content, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}

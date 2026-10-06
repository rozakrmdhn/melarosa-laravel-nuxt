<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JalanPorosDesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JalanPorosDesaController extends Controller
{
    /**
     * Display a listing of road segments.
     * Supports ?format=geojson (default), ?format=table (paginated), ?format=summary (stats + bbox).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JalanPorosDesa::class);
        $user = $request->user();
        $isBappeda = $user && $user->hasRole('verifierBappeda');
        $isAdmin   = $user && $user->hasRole('admin');
        $isRestricted = $user && !$isAdmin && !$isBappeda;

        $targetKecId  = $request->filled('id_kecamatan') ? (int) $request->input('id_kecamatan') : ($request->filled('kecamatan') && is_numeric($request->input('kecamatan')) ? (int) $request->input('kecamatan') : null);
        $targetDesaId = $request->filled('id_desa') ? (int) $request->input('id_desa') : ($request->filled('desa') && is_numeric($request->input('desa')) ? (int) $request->input('desa') : null);

        if ($isRestricted) {
            if ($user->id_desa) {
                // Operator Desa terkunci total pada desa dan kecamatannya
                $targetDesaId = (int) $user->id_desa;
                $targetKecId  = (int) $user->id_kecamatan;
            } elseif ($user->id_kecamatan) {
                // Verifier Kecamatan terkunci pada kecamatannya
                $targetKecId = (int) $user->id_kecamatan;
                if ($targetDesaId) {
                    $validDesa = DB::table('bataswilayah_desa')
                        ->where('id', $targetDesaId)
                        ->where('id_kecamatan', $user->id_kecamatan)
                        ->exists();
                    if (!$validDesa) {
                        $targetDesaId = null;
                    }
                }
            }
        }

        $query = JalanPorosDesa::query()->forUser($user);

        // Text search: nama_ruas, desa, kecamatan
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('jalan_porosdesa.nama_ruas', 'ilike', $search)
                  ->orWhere('jalan_porosdesa.desa', 'ilike', $search)
                  ->orWhere('jalan_porosdesa.kecamatan', 'ilike', $search);
            });
        }

        // Kondisi filter
        if ($request->filled('kondisi')) {
            $query->where('jalan_porosdesa.kondisi', $request->query('kondisi'));
        }

        // Perkerasan filter
        if ($request->filled('perkerasan')) {
            $query->where('jalan_porosdesa.perkerasan', $request->query('perkerasan'));
        }

        // Filter wilayah yang sudah divalidasi otoritasnya
        if ($targetDesaId) {
            $query->where('jalan_porosdesa.id_desa', $targetDesaId);
        } elseif ($targetKecId) {
            $query->where('jalan_porosdesa.id_kecamatan', $targetKecId);
        }

        // Viewport bounding box filter
        if ($request->filled('bbox')) {
            $coords = explode(',', $request->query('bbox'));
            if (count($coords) === 4) {
                $query->inBbox(
                    (float) $coords[0],
                    (float) $coords[1],
                    (float) $coords[2],
                    (float) $coords[3]
                );
            }
        }

        $format = $request->query('format', 'geojson');

        if ($format === 'options') {
            $kecamatanQuery = DB::table('bataswilayah_kecamatan')
                ->select('id', 'nama_kecamatan as nama')
                ->orderBy('nama');

            $desaQuery = DB::table('bataswilayah_desa')
                ->select('id', 'id_kecamatan', 'nama_desa as nama')
                ->orderBy('nama');

            if ($request->filled('id_kecamatan')) {
                $desaQuery->where('id_kecamatan', (int) $request->query('id_kecamatan'));
            } elseif ($request->filled('kecamatan') && is_numeric($request->query('kecamatan'))) {
                $desaQuery->where('id_kecamatan', (int) $request->query('kecamatan'));
            }

            $kecamatanList = $kecamatanQuery->get();
            $desaList = $desaQuery->get();

            $kondisiList = DB::table('jalan_porosdesa')
                ->whereNotNull('kondisi')
                ->distinct()
                ->orderBy('kondisi')
                ->pluck('kondisi');

            $perkerasanList = DB::table('jalan_porosdesa')
                ->whereNotNull('perkerasan')
                ->distinct()
                ->orderBy('perkerasan')
                ->pluck('perkerasan');

            return response()->json([
                'ok'         => true,
                'kecamatan'  => $kecamatanList,
                'desa'       => $desaList,
                'kondisi'    => $kondisiList,
                'perkerasan' => $perkerasanList,
            ]);
        }

        if ($format === 'summary') {
            $summaryQuery = DB::table('jalan_porosdesa');
            $kondisiQuery = DB::table('jalan_porosdesa')->whereNotNull('kondisi');

            $applyFilters = function ($q) use ($request) {
                if ($request->filled('search')) {
                    $search = '%' . trim($request->query('search')) . '%';
                    $q->where(function ($sub) use ($search) {
                        $sub->where('nama_ruas', 'ilike', $search)
                            ->orWhere('desa', 'ilike', $search)
                            ->orWhere('kecamatan', 'ilike', $search);
                    });
                }
                if ($request->filled('kondisi')) {
                    $q->where('kondisi', $request->query('kondisi'));
                }
                if ($request->filled('perkerasan')) {
                    $q->where('perkerasan', $request->query('perkerasan'));
                }
                if ($request->filled('kecamatan')) {
                    $val = trim($request->query('kecamatan'));
                    if (is_numeric($val)) {
                        $q->where('id_kecamatan', (int) $val);
                    } else {
                        $q->where('kecamatan', 'ilike', $val);
                    }
                } elseif ($request->filled('id_kecamatan')) {
                    $q->where('id_kecamatan', (int) $request->query('id_kecamatan'));
                }
                if ($request->filled('desa')) {
                    $val = trim($request->query('desa'));
                    if (is_numeric($val)) {
                        $q->where('id_desa', (int) $val);
                    } else {
                        $q->where('desa', 'ilike', $val);
                    }
                } elseif ($request->filled('id_desa')) {
                    $q->where('id_desa', (int) $request->query('id_desa'));
                }
            };

            $applyFilters($summaryQuery);
            $applyFilters($kondisiQuery);

            $stats = $summaryQuery->selectRaw("
                COUNT(*) as total_ruas,
                COALESCE(ROUND(SUM(ST_Length(geom::geography))::numeric, 2), 0) as total_panjang_meter,
                COALESCE(ROUND((SUM(ST_Length(geom::geography)) / 1000)::numeric, 3), 0) as total_panjang_km,
                ST_XMin(ST_Extent(geom)) as min_x,
                ST_YMin(ST_Extent(geom)) as min_y,
                ST_XMax(ST_Extent(geom)) as max_x,
                ST_YMax(ST_Extent(geom)) as max_y
            ")->first();

            $kondisiBreakdown = $kondisiQuery
                ->selectRaw("kondisi, COUNT(*) as jumlah, ROUND(SUM(ST_Length(geom::geography))::numeric, 2) as panjang_meter, ROUND((SUM(ST_Length(geom::geography)) / 1000)::numeric, 3) as panjang_km")
                ->groupBy('kondisi')
                ->orderByDesc('jumlah')
                ->get();

            $maxKodeRuas = (int) DB::table('jalan_porosdesa')->max('kode_ruas');

            return response()->json([
                'ok'                  => true,
                'total_ruas'          => (int) ($stats->total_ruas ?? 0),
                'total_panjang_meter' => (float) ($stats->total_panjang_meter ?? 0),
                'total_panjang_km'    => (float) ($stats->total_panjang_km ?? 0),
                'max_kode_ruas'       => $maxKodeRuas,
                'next_kode_ruas'      => $maxKodeRuas + 1,
                'kondisi'             => $kondisiBreakdown,
                'bbox'            => [
                    (float) ($stats->min_x ?? 111.4),
                    (float) ($stats->min_y ?? -7.5),
                    (float) ($stats->max_x ?? 112.2),
                    (float) ($stats->max_y ?? -7.0),
                ],
            ]);
        }

        if ($format === 'table') {
            // Lightweight query: only scalar attribute columns + centroid point (no full linestring geojson)
            $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

            $tableQuery = (clone $query)->select([
                'jalan_porosdesa.id',
                'jalan_porosdesa.kode_ruas',
                'jalan_porosdesa.nama_ruas',
                'jalan_porosdesa.desa',
                'jalan_porosdesa.kecamatan',
                'jalan_porosdesa.panjang',
                'jalan_porosdesa.lebar',
                'jalan_porosdesa.perkerasan',
                'jalan_porosdesa.kondisi',
                'jalan_porosdesa.status_awal',
                'jalan_porosdesa.status_eksisting',
                'jalan_porosdesa.sumber_data',
                'jalan_porosdesa.id_desa',
                'jalan_porosdesa.id_kecamatan',
                DB::raw('ROUND((ST_Length(jalan_porosdesa.geom::geography))::numeric, 2) as panjang_meter'),
                DB::raw('ST_AsGeoJSON(ST_PointOnSurface(jalan_porosdesa.geom)) as centroid'),
                DB::raw('ST_AsGeoJSON(jalan_porosdesa.geom) as geojson'),
            ]);

            $paginated = $tableQuery->orderBy('nama_ruas', 'asc')->paginate($perPage);

            return response()->json([
                'ok'           => true,
                'data'         => $paginated->items(),
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ]);
        }

        // Default: GeoJSON FeatureCollection for OpenLayers (needs full geometry)
        $items    = $query->withGeoJson()->orderBy('nama_ruas', 'asc')->get();
        $features = $items->map(fn (JalanPorosDesa $item) => $item->toGeoJsonFeature())->values();

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Display the specified road segment.
     */
    public function show(string $id): JsonResponse
    {
        $ruas = JalanPorosDesa::withGeoJson()->where('id', $id)->first();

        if (!$ruas) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jalan poros desa tidak ditemukan.',
            ], 404);
        }

        $this->authorize('view', $ruas);

        return response()->json([
            'ok'      => true,
            'data'    => $ruas,
            'feature' => $ruas->toGeoJsonFeature(),
        ]);
    }

    /**
     * Store a newly created road segment.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', JalanPorosDesa::class);
        $user = $request->user();

        $validated = $request->validate([
            'kode_ruas'        => ['nullable', 'integer'],
            'nama_ruas'        => ['required', 'string', 'max:255'],
            'desa'             => ['nullable', 'string', 'max:255'],
            'kecamatan'        => ['nullable', 'string', 'max:255'],
            'panjang'          => ['nullable', 'numeric'],
            'lebar'            => ['nullable', 'numeric'],
            'perkerasan'       => ['nullable', 'string', 'max:255'],
            'kondisi'          => ['nullable', 'string', 'max:255'],
            'status_awal'      => ['nullable', 'string', 'max:255'],
            'status_eksisting' => ['nullable', 'string', 'max:255'],
            'sumber_data'      => ['nullable', 'string', 'max:255'],
            'id_desa'          => ['nullable', 'integer'],
            'id_kecamatan'     => ['nullable', 'integer'],
            'geometry'         => ['required'],
        ]);

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
                'message' => 'Format GeoJSON geometri tidak dapat diproses: ' . $e->getMessage(),
            ], 422);
        }

        $ruas = new JalanPorosDesa();
        $ruas->id = \Illuminate\Support\Str::uuid()->toString();

        if (empty($validated['kode_ruas'])) {
            $maxKode = (int) DB::table('jalan_porosdesa')->max('kode_ruas');
            $ruas->kode_ruas = $maxKode + 1;
        } else {
            $kode = (int) $validated['kode_ruas'];
            $exists = DB::table('jalan_porosdesa')->where('kode_ruas', $kode)->exists();
            if ($exists) {
                return response()->json([
                    'ok'      => false,
                    'message' => "Kode ruas {$kode} sudah digunakan. Silakan gunakan nomor lain atau kosongkan agar diisi otomatis.",
                ], 422);
            }
            $ruas->kode_ruas = $kode;
        }

        $ruas->nama_ruas        = $validated['nama_ruas'];
        $ruas->desa             = $validated['desa'] ?? null;
        $ruas->kecamatan        = $validated['kecamatan'] ?? null;
        $ruas->panjang          = $validated['panjang'] ?? null;
        $ruas->lebar            = $validated['lebar'] ?? null;
        $ruas->perkerasan       = $validated['perkerasan'] ?? null;
        $ruas->kondisi          = $validated['kondisi'] ?? null;
        $ruas->status_awal      = $validated['status_awal'] ?? null;
        $ruas->status_eksisting = $validated['status_eksisting'] ?? null;
        $ruas->sumber_data      = $validated['sumber_data'] ?? null;

        // Kunci wilayah otomatis sesuai akun pengguna yang dibatasi
        if ($user && !$user->hasRole('admin')) {
            if ($user->id_desa) {
                $validated['id_desa'] = $user->id_desa;
                $validated['id_kecamatan'] = $user->id_kecamatan;
            } elseif ($user->id_kecamatan) {
                $validated['id_kecamatan'] = $user->id_kecamatan;
            }
        }

        $ruas->id_desa          = $validated['id_desa'] ?? null;
        $ruas->id_kecamatan     = $validated['id_kecamatan'] ?? null;
        $pdo = DB::getPdo();
        $ruas->geom             = DB::raw("ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(" . $pdo->quote($geoJsonString) . "), 4326))");
        $ruas->save();

        $fresh = JalanPorosDesa::withGeoJson()->where('id', $ruas->id)->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Ruas jalan poros desa berhasil disimpan.',
            'data'    => $fresh,
            'feature' => $fresh->toGeoJsonFeature(),
        ], 201);
    }

    /**
     * Update the specified road segment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $ruas = JalanPorosDesa::where('id', $id)->first();

        if (!$ruas) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jalan poros desa tidak ditemukan.',
            ], 404);
        }

        $this->authorize('update', $ruas);
        $user = $request->user();

        $validated = $request->validate([
            'kode_ruas'        => ['nullable', 'integer'],
            'nama_ruas'        => ['sometimes', 'required', 'string', 'max:255'],
            'desa'             => ['nullable', 'string', 'max:255'],
            'kecamatan'        => ['nullable', 'string', 'max:255'],
            'panjang'          => ['nullable', 'numeric'],
            'lebar'            => ['nullable', 'numeric'],
            'perkerasan'       => ['nullable', 'string', 'max:255'],
            'kondisi'          => ['nullable', 'string', 'max:255'],
            'status_awal'      => ['nullable', 'string', 'max:255'],
            'status_eksisting' => ['nullable', 'string', 'max:255'],
            'sumber_data'      => ['nullable', 'string', 'max:255'],
            'id_desa'          => ['nullable', 'integer'],
            'id_kecamatan'     => ['nullable', 'integer'],
            'geometry'         => ['nullable'],
        ]);

        if (array_key_exists('kode_ruas', $validated) && $validated['kode_ruas'] !== null) {
            $kode = (int) $validated['kode_ruas'];
            $exists = DB::table('jalan_porosdesa')
                ->where('kode_ruas', $kode)
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'ok'      => false,
                    'message' => "Kode ruas {$kode} sudah digunakan oleh ruas jalan lain.",
                ], 422);
            }
            $ruas->kode_ruas = $kode;
        }

        foreach (['nama_ruas', 'desa', 'kecamatan', 'panjang', 'lebar',
                  'perkerasan', 'kondisi', 'status_awal', 'status_eksisting',
                  'sumber_data', 'id_desa', 'id_kecamatan'] as $field) {
            if (array_key_exists($field, $validated)) {
                $ruas->$field = $validated[$field];
            }
        }

        if (!empty($validated['geometry'])) {
            $geoJsonString = is_string($validated['geometry'])
                ? $validated['geometry']
                : json_encode($validated['geometry']);

            $pdo = DB::getPdo();
            $ruas->geom = DB::raw("ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(" . $pdo->quote($geoJsonString) . "), 4326))");
        }

        $ruas->save();

        $fresh = JalanPorosDesa::withGeoJson()->where('id', $ruas->id)->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Ruas jalan poros desa berhasil diperbarui.',
            'data'    => $fresh,
            'feature' => $fresh->toGeoJsonFeature(),
        ]);
    }

    /**
     * Remove the specified road segment.
     */
    public function destroy(string $id): JsonResponse
    {
        $ruas = JalanPorosDesa::find($id);

        if (!$ruas) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jalan poros desa tidak ditemukan.',
            ], 404);
        }

        $this->authorize('delete', $ruas);

        $ruas->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Ruas jalan poros desa berhasil dihapus.',
        ]);
    }



    /**
     * Split a road segment into two separate segments at a specified point or with pre-split geometries.
     */
    public function split(Request $request, string $id): JsonResponse
    {
        $ruas = JalanPorosDesa::find($id);

        if (!$ruas) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jalan poros desa tidak ditemukan.',
            ], 404);
        }

        $this->authorize('split', $ruas);

        $request->validate([
            'point'             => ['nullable', 'array'],
            'point.*'           => ['numeric'],
            'parts'             => ['nullable', 'array', 'size:2'],
            'parts.*.nama_ruas' => ['required_with:parts', 'string', 'max:255'],
            'parts.*.geometry'  => ['required_with:parts'],
            'part1'             => ['nullable', 'array'],
            'part1.nama_ruas'   => ['nullable', 'string', 'max:255'],
            'part2'             => ['nullable', 'array'],
            'part2.nama_ruas'   => ['nullable', 'string', 'max:255'],
            'part2.kode_ruas'   => ['nullable', 'integer'],
        ]);

        $createdFeatures = [];

        DB::transaction(function () use ($ruas, $request, &$createdFeatures) {
            if ($request->filled('parts')) {
                // Mode 1: Pre-computed parts supplied by request
                $p1 = $request->input('parts.0');
                $p2 = $request->input('parts.1');

                $geo1 = is_string($p1['geometry']) ? $p1['geometry'] : json_encode($p1['geometry']);
                $geo2 = is_string($p2['geometry']) ? $p2['geometry'] : json_encode($p2['geometry']);

                // Update Part 1 (existing record)
                $ruas->nama_ruas = $p1['nama_ruas'] ?? ($ruas->nama_ruas . ' (Bagian 1)');
                $ruas->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geo1')), 4326)");
                $ruas->panjang = DB::raw("ROUND((ST_Length(ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geo1')), 4326)::geography))::numeric, 2)");
                $ruas->save();

                // Create Part 2 (new record)
                $nextKodeRuas = (int) ($p2['kode_ruas'] ?? (JalanPorosDesa::max('kode_ruas') + 1));
                $newRuas = new JalanPorosDesa();
                $newRuas->id = (string) Str::uuid();
                $newRuas->kode_ruas = $nextKodeRuas;
                $newRuas->nama_ruas = $p2['nama_ruas'] ?? ($ruas->nama_ruas . ' (Bagian 2)');
                $newRuas->desa = $ruas->desa;
                $newRuas->kecamatan = $ruas->kecamatan;
                $newRuas->id_desa = $ruas->id_desa;
                $newRuas->id_kecamatan = $ruas->id_kecamatan;
                $newRuas->lebar = $ruas->lebar;
                $newRuas->perkerasan = $ruas->perkerasan;
                $newRuas->kondisi = $ruas->kondisi;
                $newRuas->status_awal = $ruas->status_awal;
                $newRuas->status_eksisting = $ruas->status_eksisting;
                $newRuas->sumber_data = $ruas->sumber_data;
                $newRuas->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geo2')), 4326)");
                $newRuas->panjang = DB::raw("ROUND((ST_Length(ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$geo2')), 4326)::geography))::numeric, 2)");
                $newRuas->save();
            } else {
                // Mode 2: Split by Point coordinates [lon, lat] using PostGIS spatial algorithms
                $point = $request->input('point');
                if (!$point || count($point) < 2) {
                    abort(422, 'Titik koordinat pemotongan (point) wajib diisi.');
                }
                $lon = (float) $point[0];
                $lat = (float) $point[1];

                // Query fraction along line and generate both geometry parts BEFORE updating database
                $splitQuery = DB::selectOne("
                    SELECT 
                        fraction,
                        ST_AsGeoJSON(ST_Multi(ST_LineSubstring(line_geom, 0, fraction))) AS part1_geojson,
                        ROUND((ST_Length(ST_Multi(ST_LineSubstring(line_geom, 0, fraction))::geography))::numeric, 2) AS part1_panjang,
                        ST_AsGeoJSON(ST_Multi(ST_LineSubstring(line_geom, fraction, 1))) AS part2_geojson,
                        ROUND((ST_Length(ST_Multi(ST_LineSubstring(line_geom, fraction, 1))::geography))::numeric, 2) AS part2_panjang
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
                            FROM jalan_porosdesa
                            WHERE id = ?
                        ) g
                    ) sub
                ", [$lon, $lat, $ruas->id]);

                if (!$splitQuery || empty($splitQuery->part1_geojson) || empty($splitQuery->part2_geojson)) {
                    abort(422, 'Gagal memproses pemotongan geometri ruas jalan.');
                }

                $fraction = (float) ($splitQuery->fraction ?? 0);

                // Ensure point is reasonably within the line (not at immediate boundary vertices)
                if ($fraction <= 0.005 || $fraction >= 0.995) {
                    abort(422, 'Titik pemotongan terlalu dekat dengan ujung ruas jalan. Silakan pilih titik di badan jalan.');
                }

                $name1 = $request->input('part1.nama_ruas') ?: ($ruas->nama_ruas . ' (Bagian 1)');
                $name2 = $request->input('part2.nama_ruas') ?: ($ruas->nama_ruas . ' (Bagian 2)');
                $nextKodeRuas = (int) ($request->input('part2.kode_ruas') ?: ((int) JalanPorosDesa::max('kode_ruas') + 1));

                $part1Geo = $splitQuery->part1_geojson;
                $part2Geo = $splitQuery->part2_geojson;

                // 1. Update Part 1 (existing record)
                $ruas->nama_ruas = $name1;
                $ruas->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$part1Geo')), 4326)");
                $ruas->panjang = (float) $splitQuery->part1_panjang;
                $ruas->save();

                // 2. Insert Part 2 (new record)
                $newRuas = new JalanPorosDesa();
                $newRuas->id = (string) Str::uuid();
                $newRuas->kode_ruas = $nextKodeRuas;
                $newRuas->nama_ruas = $name2;
                $newRuas->desa = $ruas->desa;
                $newRuas->kecamatan = $ruas->kecamatan;
                $newRuas->id_desa = $ruas->id_desa;
                $newRuas->id_kecamatan = $ruas->id_kecamatan;
                $newRuas->lebar = $ruas->lebar;
                $newRuas->perkerasan = $ruas->perkerasan;
                $newRuas->kondisi = $ruas->kondisi;
                $newRuas->status_awal = $ruas->status_awal;
                $newRuas->status_eksisting = $ruas->status_eksisting;
                $newRuas->sumber_data = $ruas->sumber_data;
                $newRuas->geom = DB::raw("ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON('$part2Geo')), 4326)");
                $newRuas->panjang = (float) $splitQuery->part2_panjang;
                $newRuas->save();
            }

            $createdFeatures['part1'] = JalanPorosDesa::withGeoJson()->find($ruas->id)?->toGeoJsonFeature();
            $createdFeatures['part2'] = JalanPorosDesa::withGeoJson()->find($newRuas->id)?->toGeoJsonFeature();

            app(\App\Services\AuditLogService::class)->log(
                event: 'split',
                module: 'jalan-poros-desa',
                auditable: $ruas,
                description: "Memecah (split) ruas jalan \"{$ruas->nama_ruas}\" menjadi 2 bagian (\"{$newRuas->nama_ruas}\")",
                meta: [
                    'original_id' => $ruas->id,
                    'new_part_id' => $newRuas->id,
                    'kode_ruas_part1' => $ruas->kode_ruas,
                    'kode_ruas_part2' => $newRuas->kode_ruas,
                ]
            );
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Ruas jalan berhasil dipecah (split) menjadi 2 bagian.',
            'data'    => $createdFeatures,
        ]);
    }
}

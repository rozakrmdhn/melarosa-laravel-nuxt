<?php

namespace App\Services;

use App\Helpers\SpatialHelper;
use App\Models\BkkdProposal;
use App\Models\BkkdProposalLokasi;
use App\Models\InfrastrukturSegmen;
use App\Models\JalanPorosDesa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BkkdSpatialAnalysisService
{
    /**
     * Bangun struktur GeoJSON LineString dari input proposal (geojson langsung, koordinat awal-akhir, titik tunggal + panjang, atau referensi jalan poros).
     */
    public function buildGeoJsonFromProposalInput(array $input): ?array
    {
        if (!empty($input['geojson'])) {
            return $this->normalizeGeoJson($input['geojson']);
        }

        if (isset($input['latitude'], $input['longitude']) && is_numeric($input['latitude']) && is_numeric($input['longitude'])) {
            $lat1 = (float) $input['latitude'];
            $lng1 = (float) $input['longitude'];

            if (isset($input['latitude_akhir'], $input['longitude_akhir']) && is_numeric($input['latitude_akhir']) && is_numeric($input['longitude_akhir'])) {
                $lat2 = (float) $input['latitude_akhir'];
                $lng2 = (float) $input['longitude_akhir'];
                if (abs($lat1 - $lat2) < 0.000001 && abs($lng1 - $lng2) < 0.000001) {
                    $lng2 = round($lng1 + 0.00045, 6); // ~50m
                }
            } else {
                $panjangM = isset($input['panjang']) && is_numeric($input['panjang']) && (float) $input['panjang'] > 0
                    ? (float) $input['panjang']
                    : 100.0;
                $offsetDeg = max(10.0, $panjangM) / 111320.0;
                $lng2 = round($lng1 + $offsetDeg, 6);
                $lat2 = $lat1;
            }

            return [
                'type' => 'LineString',
                'coordinates' => [
                    [$lng1, $lat1],
                    [$lng2, $lat2],
                ],
            ];
        }

        if (!empty($input['id_jalan_poros']) && Schema::hasTable('jalan_porosdesa')) {
            $poros = JalanPorosDesa::withGeoJson()->find($input['id_jalan_poros']);
            if ($poros && !empty($poros->geojson)) {
                $coords = $this->extractMergedRoadCoordinates($poros->geojson);
                if (count($coords) >= 2) {
                    return [
                        'type' => 'LineString',
                        'coordinates' => $coords,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Ekstrak satu atau beberapa lokasi LineString mengikuti geometri Jalan Poros Desa
     * dari input titik awal-akhir atau GeoJSON. Jika lintasan dari titik awal ke titik akhir
     * melintasi / bersimpangan pada 2 (atau lebih) ruas Jalan Poros Desa, otomatis dipecah
     * menjadi beberapa item lokasi per ruas Jalan Poros Desa.
     *
     * @return array<int, array{
     *     id_jalan_poros: string|null,
     *     nama_ruas: string|null,
     *     nama_lokasi: string,
     *     geojson: array{type: string, coordinates: array<int, array<int, float>>},
     *     panjang_gis_m: float
     * }>
     */
    public function extractLocationsFromCoordinatesOrGeoJson(int $idDesa, array $input): array
    {
        if (!empty($input['waypoints']) && is_array($input['waypoints'])) {
            $built = $this->buildLineFromWaypoints($input['waypoints']);
            if ($built !== null) {
                $namaLokasi = trim((string) ($input['nama_lokasi'] ?? $input['lokasi_kegiatan'] ?? $input['judul_kegiatan'] ?? 'Lokasi Usulan'));
                $coordsW = $built['geojson']['coordinates'];
                $firstW = $coordsW[0];
                $lastW = $coordsW[count($coordsW) - 1];

                return [[
                    'id_jalan_poros' => !empty($input['id_jalan_poros']) ? (string) $input['id_jalan_poros'] : null,
                    'nama_ruas' => null,
                    'nama_lokasi' => $namaLokasi !== '' ? $namaLokasi : 'Lokasi Usulan',
                    'sumber_rute' => $built['sumber_rute'],
                    'titik_awal' => ['lat' => round((float) $firstW[1], 6), 'lng' => round((float) $firstW[0], 6)],
                    'titik_akhir' => ['lat' => round((float) $lastW[1], 6), 'lng' => round((float) $lastW[0], 6)],
                    'titik_sebaran' => $built['titik_sebaran'],
                    'geojson' => $built['geojson'],
                    'panjang_gis_m' => $this->calculateGeoJsonLength($built['geojson']),
                ]];
            }
        }

        $baseGeoJson = $this->buildGeoJsonFromProposalInput($input);
        if (!$baseGeoJson) {
            return [];
        }

        $inputCoords = $this->extractCoordinates($baseGeoJson);
        if (count($inputCoords) < 2) {
            return [];
        }

        $baseNamaLokasi = trim((string) ($input['nama_lokasi'] ?? $input['lokasi_kegiatan'] ?? $input['judul_kegiatan'] ?? 'Lokasi Usulan'));
        if ($baseNamaLokasi === '') {
            $baseNamaLokasi = 'Lokasi Usulan';
        }

        $preferredPorosId = !empty($input['id_jalan_poros']) ? (string) $input['id_jalan_poros'] : null;

        // Tentukan titik awal (A) dan titik akhir (B)
        $ptA = [(float) $inputCoords[0][0], (float) $inputCoords[0][1]];
        $ptB = [(float) $inputCoords[count($inputCoords) - 1][0], (float) $inputCoords[count($inputCoords) - 1][1]];

        if (isset($input['latitude'], $input['longitude']) && is_numeric($input['latitude']) && is_numeric($input['longitude'])) {
            $ptA = [(float) $input['longitude'], (float) $input['latitude']];
        }
        if (isset($input['latitude_akhir'], $input['longitude_akhir']) && is_numeric($input['latitude_akhir']) && is_numeric($input['longitude_akhir'])) {
            $ptB = [(float) $input['longitude_akhir'], (float) $input['latitude_akhir']];
        }

        // Hormati geometri hasil reshape manual agar koreksi lokasi tidak tertimpa jalan_porosdesa atau hit ke OpenRouteService
        $requestedSource = isset($input['sumber_rute']) ? trim((string) $input['sumber_rute']) : null;
        $preserveManual = !empty($input['geojson']) && (
            !empty($input['preserve_manual_geometry']) ||
            in_array($requestedSource, ['manual', 'garis_lurus'], true)
        );
        if ($preserveManual) {
            return [[
                'id_jalan_poros' => $preferredPorosId,
                'nama_ruas' => null,
                'nama_lokasi' => $baseNamaLokasi,
                'sumber_rute' => $requestedSource ?: 'manual',
                'titik_awal' => ['lat' => round($ptA[1], 6), 'lng' => round($ptA[0], 6)],
                'titik_akhir' => ['lat' => round($ptB[1], 6), 'lng' => round($ptB[0], 6)],
                'geojson' => $baseGeoJson,
                'panjang_gis_m' => $this->calculateGeoJsonLength($baseGeoJson),
            ]];
        }

        if (!Schema::hasTable('jalan_porosdesa')) {
            return [$this->buildFallbackLocationItem($ptA, $ptB, $baseGeoJson, $preferredPorosId, $baseNamaLokasi, $input)];
        }

        // Ambil kandidat ruas Jalan Poros Desa di desa terkait (serta bbox koridor di PostgreSQL)
        $query = JalanPorosDesa::query()->withGeoJson();
        if (DB::connection()->getDriverName() === 'pgsql') {
            $minLng = min($ptA[0], $ptB[0]) - 0.003;
            $maxLng = max($ptA[0], $ptB[0]) + 0.003;
            $minLat = min($ptA[1], $ptB[1]) - 0.003;
            $maxLat = max($ptA[1], $ptB[1]) + 0.003;

            $query->where(function ($q) use ($idDesa, $preferredPorosId, $minLng, $minLat, $maxLng, $maxLat) {
                $q->where('jalan_porosdesa.id_desa', $idDesa)
                    ->orWhereRaw('jalan_porosdesa.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$minLng, $minLat, $maxLng, $maxLat]);
                if ($preferredPorosId) {
                    $q->orWhere('jalan_porosdesa.id', $preferredPorosId);
                }
            });
        } else {
            $query->where(function ($q) use ($idDesa, $preferredPorosId) {
                $q->where('jalan_porosdesa.id_desa', $idDesa);
                if ($preferredPorosId) {
                    $q->orWhere('jalan_porosdesa.id', $preferredPorosId);
                }
            });
        }

        $porosModels = $query->orderBy('kode_ruas')->get();
        if ($porosModels->isEmpty()) {
            return [$this->buildFallbackLocationItem($ptA, $ptB, $baseGeoJson, $preferredPorosId, $baseNamaLokasi, $input)];
        }

        $candidates = [];
        foreach ($porosModels as $jp) {
            $rawGeom = !empty($jp->geojson) ? $jp->geojson : $jp->geom;
            $roadCoords = $this->extractMergedRoadCoordinates($rawGeom);
            if (count($roadCoords) < 2) {
                continue;
            }

            $projA = $this->projectPointOntoPolyline($ptA, $roadCoords);
            $projB = $this->projectPointOntoPolyline($ptB, $roadCoords);

            $candidates[$jp->id] = [
                'jp' => $jp,
                'coords' => $roadCoords,
                'projA' => $projA,
                'projB' => $projB,
            ];
        }

        if (empty($candidates)) {
            return [$this->buildFallbackLocationItem($ptA, $ptB, $baseGeoJson, $preferredPorosId, $baseNamaLokasi, $input)];
        }

        $snapToleranceM = 80.0;

        // Urutkan kandidat terdekat terhadap titik awal A dan titik akhir B
        $startList = array_values(array_filter($candidates, fn ($c) => $c['projA']['distance_m'] <= $snapToleranceM));
        usort($startList, function ($a, $b) use ($preferredPorosId) {
            $diffA = $a['projA']['distance_m'] - $b['projA']['distance_m'];
            if (abs($diffA) <= 8.0) {
                if ($preferredPorosId && $a['jp']->id === $preferredPorosId) {
                    return -1;
                }
                if ($preferredPorosId && $b['jp']->id === $preferredPorosId) {
                    return 1;
                }
                return $a['projB']['distance_m'] <=> $b['projB']['distance_m'];
            }
            return $diffA <=> 0;
        });

        $endList = array_values(array_filter($candidates, fn ($c) => $c['projB']['distance_m'] <= $snapToleranceM));
        usort($endList, function ($a, $b) use ($preferredPorosId) {
            $diffB = $a['projB']['distance_m'] - $b['projB']['distance_m'];
            if (abs($diffB) <= 8.0) {
                if ($preferredPorosId && $a['jp']->id === $preferredPorosId) {
                    return -1;
                }
                if ($preferredPorosId && $b['jp']->id === $preferredPorosId) {
                    return 1;
                }
                return $a['projA']['distance_m'] <=> $b['projA']['distance_m'];
            }
            return $diffB <=> 0;
        });

        $startCand = $startList[0] ?? null;
        $endCand = $endList[0] ?? null;

        $extractedSegments = [];

        // Skenario 1: Titik A dan Titik B berada pada ruas Jalan Poros Desa yang SAMA
        if ($startCand && $endCand && $startCand['jp']->id === $endCand['jp']->id) {
            $sliced = $this->slicePolylineBetweenProjections(
                $startCand['coords'],
                $startCand['projA'],
                $startCand['projB']
            );
            $len = $this->calculateGeoJsonLength($sliced);
            if (count($sliced) >= 2 && $len >= 1.0) {
                $extractedSegments[] = [
                    'jp' => $startCand['jp'],
                    'coords' => $sliced,
                    'length_m' => $len,
                ];
            }
        }
        // Skenario 2: Titik A berada di Ruas 1 dan Titik B berada di Ruas 2 (bersimpangan / pindah ruas jalan poros desa)
        elseif ($startCand && $endCand && $startCand['jp']->id !== $endCand['jp']->id) {
            $junction = $this->findClosestJunctionBetweenPolylines($startCand['coords'], $endCand['coords']);

            if ($junction['distance_m'] <= 80.0) {
                $projStartEnd = $this->projectPointOntoPolyline($junction['point1'], $startCand['coords']);
                $sliced1 = $this->slicePolylineBetweenProjections($startCand['coords'], $startCand['projA'], $projStartEnd);
                $len1 = $this->calculateGeoJsonLength($sliced1);

                $projEndStart = $this->projectPointOntoPolyline($junction['point2'], $endCand['coords']);
                $sliced2 = $this->slicePolylineBetweenProjections($endCand['coords'], $projEndStart, $endCand['projB']);
                $len2 = $this->calculateGeoJsonLength($sliced2);

                if (count($sliced1) >= 2 && $len1 >= 5.0) {
                    $extractedSegments[] = [
                        'jp' => $startCand['jp'],
                        'coords' => $sliced1,
                        'length_m' => $len1,
                    ];
                }
                if (count($sliced2) >= 2 && $len2 >= 5.0) {
                    $extractedSegments[] = [
                        'jp' => $endCand['jp'],
                        'coords' => $sliced2,
                        'length_m' => $len2,
                    ];
                }
            } else {
                // Cari apakah ada ruas perantara (Ruas Tengah) yang menghubungkan Ruas Awal dan Ruas Akhir
                $midFound = null;
                $jStartMid = null;
                $jMidEnd = null;

                foreach ($candidates as $candId => $cand) {
                    if ($candId === $startCand['jp']->id || $candId === $endCand['jp']->id) {
                        continue;
                    }
                    $j1 = $this->findClosestJunctionBetweenPolylines($startCand['coords'], $cand['coords']);
                    $j2 = $this->findClosestJunctionBetweenPolylines($cand['coords'], $endCand['coords']);
                    if ($j1['distance_m'] <= 80.0 && $j2['distance_m'] <= 80.0) {
                        $midFound = $cand;
                        $jStartMid = $j1;
                        $jMidEnd = $j2;
                        break;
                    }
                }

                if ($midFound && $jStartMid && $jMidEnd) {
                    $s1 = $this->slicePolylineBetweenProjections(
                        $startCand['coords'],
                        $startCand['projA'],
                        $this->projectPointOntoPolyline($jStartMid['point1'], $startCand['coords'])
                    );
                    $s2 = $this->slicePolylineBetweenProjections(
                        $midFound['coords'],
                        $this->projectPointOntoPolyline($jStartMid['point2'], $midFound['coords']),
                        $this->projectPointOntoPolyline($jMidEnd['point1'], $midFound['coords'])
                    );
                    $s3 = $this->slicePolylineBetweenProjections(
                        $endCand['coords'],
                        $this->projectPointOntoPolyline($jMidEnd['point2'], $endCand['coords']),
                        $endCand['projB']
                    );

                    foreach ([
                        ['jp' => $startCand['jp'], 'coords' => $s1],
                        ['jp' => $midFound['jp'], 'coords' => $s2],
                        ['jp' => $endCand['jp'], 'coords' => $s3],
                    ] as $part) {
                        $lenPart = $this->calculateGeoJsonLength($part['coords']);
                        if (count($part['coords']) >= 2 && $lenPart >= 5.0) {
                            $extractedSegments[] = [
                                'jp' => $part['jp'],
                                'coords' => $part['coords'],
                                'length_m' => $lenPart,
                            ];
                        }
                    }
                } else {
                    foreach ([$startCand, $endCand] as $cand) {
                        $sliced = $this->slicePolylineBetweenProjections($cand['coords'], $cand['projA'], $cand['projB']);
                        $len = $this->calculateGeoJsonLength($sliced);
                        if (count($sliced) >= 2 && $len >= 5.0) {
                            $extractedSegments[] = [
                                'jp' => $cand['jp'],
                                'coords' => $sliced,
                                'length_m' => $len,
                            ];
                        }
                    }
                }
            }
        }
        // Skenario 3: Salah satu titik berada pada Jalan Poros Desa
        elseif ($startCand || $endCand) {
            $singleCand = $startCand ?: $endCand;
            $sliced = $this->slicePolylineBetweenProjections($singleCand['coords'], $singleCand['projA'], $singleCand['projB']);
            $len = $this->calculateGeoJsonLength($sliced);
            if (count($sliced) >= 2 && $len >= 5.0) {
                $extractedSegments[] = [
                    'jp' => $singleCand['jp'],
                    'coords' => $sliced,
                    'length_m' => $len,
                ];
            }
        }

        if (empty($extractedSegments)) {
            return [$this->buildFallbackLocationItem($ptA, $ptB, $baseGeoJson, $preferredPorosId, $baseNamaLokasi, $input)];
        }

        $isMulti = count($extractedSegments) > 1;
        $results = [];

        foreach ($extractedSegments as $seg) {
            $jp = $seg['jp'];
            $namaRuas = trim((string) ($jp->nama_ruas ?? ''));
            $namaLokasi = $baseNamaLokasi;

            if ($isMulti && $namaRuas !== '' && stripos($baseNamaLokasi, $namaRuas) === false) {
                $namaLokasi = sprintf('%s (%s)', $baseNamaLokasi, $namaRuas);
            }

            $segCoords = $seg['coords'];
            $firstPt = $segCoords[0];
            $lastPt = $segCoords[count($segCoords) - 1];

            $geoJsonSeg = [
                'type' => 'LineString',
                'coordinates' => $segCoords,
            ];

            $results[] = [
                'id_jalan_poros' => (string) $jp->id,
                'nama_ruas' => $namaRuas !== '' ? $namaRuas : null,
                'nama_lokasi' => $namaLokasi,
                'sumber_rute' => 'jalan_porosdesa',
                'titik_awal' => ['lat' => round((float) $firstPt[1], 6), 'lng' => round((float) $firstPt[0], 6)],
                'titik_akhir' => ['lat' => round((float) $lastPt[1], 6), 'lng' => round((float) $lastPt[0], 6)],
                'geojson' => $geoJsonSeg,
                'panjang_gis_m' => $seg['length_m'],
            ];
        }

        return $results;
    }

    /**
     * Bentuk satu LineString dari daftar titik dinamis (waypoints).
     * Titik dengan is_route=false hanya disimpan sebagai referensi sebaran dan tidak ikut membentuk garis.
     * Segmen menuju titik dengan use_ors=true dirutekan via OpenRouteService; selain itu ditarik langsung (non ORS).
     *
     * @param  array<int, array<string, mixed>>  $waypoints
     * @return array{geojson: array, sumber_rute: string, titik_sebaran: array}|null
     */
    public function buildLineFromWaypoints(array $waypoints): ?array
    {
        $sebaran = [];
        $routePoints = [];

        foreach (array_values($waypoints) as $idx => $wp) {
            if (!is_array($wp) || !isset($wp['lat'], $wp['lng']) || !is_numeric($wp['lat']) || !is_numeric($wp['lng'])) {
                continue;
            }

            $isRoute = !array_key_exists('is_route', $wp) || filter_var($wp['is_route'], FILTER_VALIDATE_BOOLEAN);
            $useOrs = !empty($wp['use_ors']) && filter_var($wp['use_ors'], FILTER_VALIDATE_BOOLEAN);

            $sebaran[] = [
                'urutan' => $idx + 1,
                'label' => isset($wp['label']) ? (string) $wp['label'] : null,
                'lat' => round((float) $wp['lat'], 6),
                'lng' => round((float) $wp['lng'], 6),
                'is_route' => $isRoute,
                'use_ors' => $useOrs,
            ];

            if ($isRoute) {
                $routePoints[] = [
                    'pt' => [round((float) $wp['lng'], 6), round((float) $wp['lat'], 6)],
                    'use_ors' => $useOrs,
                ];
            }
        }

        if (count($routePoints) < 2) {
            return null;
        }

        $merged = [$routePoints[0]['pt']];
        $usedOrs = false;
        $usedDirect = false;

        for ($i = 1; $i < count($routePoints); $i++) {
            $from = $routePoints[$i - 1]['pt'];
            $to = $routePoints[$i]['pt'];
            $segment = null;

            if ($routePoints[$i]['use_ors']) {
                $ors = $this->fetchOpenRouteServiceLineString($from, $to);
                if ($ors !== null) {
                    $segment = $ors['coordinates'];
                    $usedOrs = true;
                }
            }

            if ($segment === null) {
                $segment = [$from, $to];
                $usedDirect = true;
            }

            foreach ($segment as $pt) {
                $last = $merged[count($merged) - 1];
                if (abs($last[0] - (float) $pt[0]) < 1e-7 && abs($last[1] - (float) $pt[1]) < 1e-7) {
                    continue;
                }
                $merged[] = [(float) $pt[0], (float) $pt[1]];
            }
        }

        if (count($merged) < 2) {
            return null;
        }

        $sumber = $usedOrs ? ($usedDirect ? 'manual' : 'openrouteservice') : 'garis_lurus';

        return [
            'geojson' => ['type' => 'LineString', 'coordinates' => $merged],
            'sumber_rute' => $sumber,
            'titik_sebaran' => $sebaran,
        ];
    }

    /**
     * Bangun item lokasi fallback menggunakan OpenRouteService API jika titik berada di luar koridor Jalan Poros Desa.
     */
    protected function buildFallbackLocationItem(
        array $ptA,
        array $ptB,
        array $baseGeoJson,
        ?string $preferredPorosId,
        string $baseNamaLokasi,
        array $input = []
    ): array {
        $coords = $this->extractCoordinates($baseGeoJson);
        $sumberRute = !empty($input['sumber_rute']) ? (string) $input['sumber_rute'] : 'garis_lurus';
        $finalGeoJson = $baseGeoJson;

        if ((!empty($input['skip_ors']) || count($coords) > 2) && empty($input['force_ors'])) {
            $sumberRute = !empty($input['sumber_rute']) ? (string) $input['sumber_rute'] : (!empty($input['skip_ors']) ? 'manual' : 'openrouteservice');
        } else {
            $orsGeoJson = $this->fetchOpenRouteServiceLineString($ptA, $ptB);
            if ($orsGeoJson !== null) {
                $finalGeoJson = $orsGeoJson;
                $coords = $orsGeoJson['coordinates'];
                $sumberRute = 'openrouteservice';
            }
        }

        $firstPt = $coords[0] ?? $ptA;
        $lastPt = $coords[max(0, count($coords) - 1)] ?? $ptB;

        return [
            'id_jalan_poros' => $preferredPorosId,
            'nama_ruas' => null,
            'nama_lokasi' => $baseNamaLokasi,
            'sumber_rute' => $sumberRute,
            'titik_awal' => ['lat' => round((float) $firstPt[1], 6), 'lng' => round((float) $firstPt[0], 6)],
            'titik_akhir' => ['lat' => round((float) $lastPt[1], 6), 'lng' => round((float) $lastPt[0], 6)],
            'geojson' => $finalGeoJson,
            'panjang_gis_m' => $this->calculateGeoJsonLength($finalGeoJson),
        ];
    }

    /**
     * Panggil OpenRouteService Directions V2 GeoJSON API untuk menghasilkan rute jalan (LineString) antara Titik Awal dan Titik Akhir.
     */
    public function fetchOpenRouteServiceLineString(array $ptA, array $ptB): ?array
    {
        $apiKey = trim((string) config('services.openrouteservice.key'));
        if ($apiKey === '') {
            return null;
        }

        if (app()->runningUnitTests() && !config('services.openrouteservice.enable_in_tests', false)) {
            return null;
        }

        $distMeters = $this->pointDistance($ptA, $ptB);
        if ($distMeters < 1.0 || $distMeters > 50000.0) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.openrouteservice.base_url', 'https://api.openrouteservice.org'), '/');
        $endpoint = "{$baseUrl}/v2/directions/driving-car/geojson";

        try {
            $http = Http::timeout(8)->withHeaders([
                'Authorization' => $apiKey,
                'Accept' => 'application/json, application/geo+json, application/gpx+xml, img/png; charset=utf-8',
                'Content-Type' => 'application/json',
            ]);

            if (!app()->isProduction()) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post($endpoint, [
                'coordinates' => [
                    [round((float) $ptA[0], 6), round((float) $ptA[1], 6)],
                    [round((float) $ptB[0], 6), round((float) $ptB[1], 6)],
                ],
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $coords = $json['features'][0]['geometry']['coordinates'] ?? null;

                if (is_array($coords) && count($coords) >= 2) {
                    $cleaned = $this->deduplicateCoordinates($coords);
                    if (count($cleaned) >= 2) {
                        return [
                            'type' => 'LineString',
                            'coordinates' => $cleaned,
                        ];
                    }
                }
            } else {
                Log::warning('OpenRouteService API non-200 response: ' . $response->status());
            }
        } catch (\Throwable $e) {
            Log::warning('OpenRouteService API request failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Hitung panjang garis geodetik (meter) dari koordinat GeoJSON LineString.
     */
    public function calculateGeoJsonLength(array|string $geoJson): float
    {
        $coords = $this->extractCoordinates($geoJson);
        if (count($coords) < 2) {
            return 0.0;
        }

        $totalMeters = 0.0;
        $earthRadius = 6371000.0; // Radius bumi dalam meter

        for ($i = 0; $i < count($coords) - 1; $i++) {
            $p1 = $coords[$i];
            $p2 = $coords[$i + 1];

            $lon1 = deg2rad((float) $p1[0]);
            $lat1 = deg2rad((float) $p1[1]);
            $lon2 = deg2rad((float) $p2[0]);
            $lat2 = deg2rad((float) $p2[1]);

            $dLat = $lat2 - $lat1;
            $dLon = $lon2 - $lon1;

            $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * (sin($dLon / 2) ** 2);
            $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
            $totalMeters += $earthRadius * $c;
        }

        return round($totalMeters, 2);
    }

    /**
     * Format ekspresi geometri untuk penyimpanan database (PostgreSQL vs SQLite).
     */
    public function formatGeomForDatabase(array|string $geoJson): mixed
    {
        $normalized = $this->normalizeGeoJson($geoJson);
        $jsonString = json_encode($normalized, JSON_UNESCAPED_SLASHES);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $pdo = DB::connection()->getPdo();
            return DB::raw("ST_SetSRID(ST_GeomFromGeoJSON(" . $pdo->quote($jsonString) . "), 4326)");
        }

        return $jsonString;
    }

    /**
     * Jalankan analisa tumpang tindih spasial 3 lapis (Tahun T-1, Infrastruktur Segmen, Jalan Poros Desa) untuk 1 lokasi usulan.
     */
    public function analisaLokasi(BkkdProposalLokasi $lokasi): array
    {
        $proposal = $lokasi->proposal ?? BkkdProposal::find($lokasi->id_proposal);
        if (!$proposal) {
            return [
                'status' => 'belum_dianalisa',
                'terdeteksi_overlap_usulan' => false,
                'terdeteksi_overlap_segmen' => false,
                'terhubung_jalan_poros' => false,
                'rekomendasi_bappeda' => 'Proposal induk tidak ditemukan.',
                'referensi_tahun_lalu' => [],
                'referensi_infrastruktur_segmen' => [],
                'referensi_jalan_poros' => [],
                'analyzed_at' => now()->toIso8601String(),
            ];
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        // Hitung ulang panjang_gis_m dari geometri DB terkini agar edit langsung via QGIS/SQL selalu sinkron
        if ($isPgsql && !empty($lokasi->id)) {
            $lenRow = DB::selectOne(
                'SELECT ROUND(ST_Length(geom::geography)::numeric, 2) AS computed_len FROM bkkd_proposal_lokasi WHERE id = :id AND geom IS NOT NULL',
                ['id' => $lokasi->id]
            );
            if ($lenRow && (float) ($lenRow->computed_len ?? 0) > 0) {
                $lokasi->panjang_gis_m = (float) $lenRow->computed_len;
            }
        } elseif (!empty($lokasi->geom)) {
            $computedLen = $this->calculateGeoJsonLength($lokasi->geom);
            if ($computedLen > 0) {
                $lokasi->panjang_gis_m = $computedLen;
            }
        }

        if ($isPgsql) {
            $analysisResult = $this->analisaLokasiPgsql($lokasi, $proposal);
        } else {
            $analysisResult = $this->analisaLokasiSqlite($lokasi, $proposal);
        }

        // Otomatis tautkan id_jalan_poros jika belum terisi dan terdeteksi berada pada koridor master jalan_porosdesa
        // Catatan: id_jaringan_ruas hanya diisi saat lokasi ini sendiri dipromosikan ke master jalan_porosdesa
        if (empty($lokasi->id_jalan_poros) && !empty($analysisResult['referensi_jalan_poros'][0]['id_jalan_poros'])) {
            $lokasi->id_jalan_poros = $analysisResult['referensi_jalan_poros'][0]['id_jalan_poros'];
        }

        $lokasi->status_analisa_spasial = $analysisResult['status'];
        if (is_array($lokasi->ringkasan_analisa) && !empty($lokasi->ringkasan_analisa['titik_sebaran'])) {
            $analysisResult['titik_sebaran'] = $lokasi->ringkasan_analisa['titik_sebaran'];
        }
        $lokasi->ringkasan_analisa = $analysisResult;

        // Evaluasi kelayakan kandidat promosi ke Master Jalan Poros & Desa
        if (Schema::hasTable('jalan_porosdesa')) {
            $validasiPromosi = $this->validasiKandidatJaringanJalan($lokasi, $proposal);
            $lokasi->validasi_promosi_spasial = $validasiPromosi;

            $currentStatus = $lokasi->status_promosi_master ?? BkkdProposalLokasi::PROMOSI_BELUM_DIEVALUASI;
            if (!in_array($currentStatus, [
                BkkdProposalLokasi::PROMOSI_DIAJUKAN,
                BkkdProposalLokasi::PROMOSI_DISETUJUI,
                BkkdProposalLokasi::PROMOSI_DITOLAK,
            ], true)) {
                $lokasi->status_promosi_master = $validasiPromosi['lolos_validasi']
                    ? BkkdProposalLokasi::PROMOSI_KANDIDAT
                    : BkkdProposalLokasi::PROMOSI_TIDAK_PERLU;
            }
        }

        $lokasi->saveQuietly();

        return $analysisResult;
    }

    /**
     * Jalankan analisa spasial untuk seluruh lokasi pada suatu proposal dan perbarui ringkasan induk.
     */
    public function analisaProposal(BkkdProposal $proposal): BkkdProposal
    {
        $lokasiList = $proposal->lokasi()->get();
        $statuses = [];

        foreach ($lokasiList as $lokasi) {
            $res = $this->analisaLokasi($lokasi);
            $statuses[] = $res['status'] ?? 'belum_dianalisa';
        }

        $proposal->jumlah_lokasi = $lokasiList->count();
        $proposal->total_panjang_gis = round((float) $lokasiList->sum('panjang_gis_m'), 2);
        $proposal->status_analisa_spasial = $this->determineOverallStatus($statuses);
        $proposal->saveQuietly();

        return $proposal;
    }

    /**
     * Analisa ulang proposal yang masih berstatus 'belum_dianalisa' (atau seluruh proposal berlokasi),
     * bermanfaat ketika geometri diedit langsung melalui QGIS / database di luar aplikasi.
     *
     * @return array{processed_proposals: int, processed_locations: int}
     */
    public function analisaPendingLocations(?int $tahunAnggaran = null, bool $onlyPending = true, int $limit = 200): array
    {
        $query = BkkdProposal::query()
            ->whereNull('deleted_at')
            ->whereHas('lokasi');

        if ($tahunAnggaran !== null && $tahunAnggaran > 0) {
            $query->where('tahun_anggaran', $tahunAnggaran);
        }

        if ($onlyPending) {
            $query->where(function ($q) {
                $q->where('status_analisa_spasial', 'belum_dianalisa')
                    ->orWhereNull('status_analisa_spasial')
                    ->orWhereHas('lokasi', function ($lq) {
                        $lq->where('status_analisa_spasial', 'belum_dianalisa')
                            ->orWhereNull('status_analisa_spasial');
                    });
            });
        }

        $proposals = $query->orderBy('updated_at', 'desc')->limit(max(1, $limit))->get();

        $processedProposals = 0;
        $processedLocations = 0;

        foreach ($proposals as $proposal) {
            $updated = $this->analisaProposal($proposal);
            $processedProposals++;
            $processedLocations += (int) ($updated->jumlah_lokasi ?? 0);
        }

        return [
            'processed_proposals' => $processedProposals,
            'processed_locations' => $processedLocations,
        ];
    }

    /**
     * Logika analisa tumpang tindih 3 lapis di PostgreSQL/PostGIS.
     */
    protected function analisaLokasiPgsql(BkkdProposalLokasi $lokasi, BkkdProposal $proposal): array
    {
        $idDesa = (int) $proposal->id_desa;
        $tahunAnggaran = (int) $proposal->tahun_anggaran;
        $lokasiId = $lokasi->id;

        $currentTagging = strtoupper((string) ($proposal->tagging_usulan ?: BkkdProposal::TAGGING_PRKPD));

        // 1. Irisan dengan Proposal di Desa yang sama (seluruh tahun: T-n, T, maupun T+n, serta penyandingan PRKPD <-> RKPD)
        $overlappingProposals = DB::select("
            SELECT
                l_prev.id AS id_lokasi_lama,
                l_prev.nama_lokasi AS nama_lokasi_lama,
                l_prev.panjang_gis_m AS panjang_usulan_lama_m,
                p_prev.id AS id_proposal_lama,
                p_prev.nomor_registrasi,
                p_prev.tahun_anggaran,
                COALESCE(p_prev.tagging_usulan, 'PRKPD') AS tagging_usulan_referensi,
                p_prev.judul_kegiatan,
                p_prev.status_analisa_spasial AS status_proposal_lama,
                ROUND(
                    ST_Length(
                        ST_Intersection(
                            l_prev.geom,
                            ST_Buffer(l_curr.geom::geography, 5)::geometry
                        )::geography
                    )::numeric,
                    2
                ) AS panjang_irisan_usulan_m
            FROM bkkd_proposal_lokasi l_prev
            JOIN bkkd_proposal p_prev ON p_prev.id = l_prev.id_proposal
            JOIN bkkd_proposal_lokasi l_curr ON l_curr.id = :lokasiId
            WHERE p_prev.id_desa = :idDesa
              AND p_prev.id::text != :currentProposalId
              AND p_prev.deleted_at IS NULL
              AND ST_Intersects(
                  l_prev.geom,
                  ST_Buffer(l_curr.geom::geography, 5)::geometry
              )
            ORDER BY
                CASE WHEN COALESCE(p_prev.tagging_usulan, 'PRKPD') != :currentTagging THEN 0 ELSE 1 END,
                ABS(p_prev.tahun_anggaran - :tahunAnggaran) ASC,
                panjang_irisan_usulan_m DESC
        ", [
            'lokasiId' => $lokasiId,
            'idDesa' => $idDesa,
            'currentProposalId' => (string) $proposal->id,
            'currentTagging' => $currentTagging,
            'tahunAnggaran' => $tahunAnggaran,
        ]);

        // 2. Irisan langsung dengan Infrastruktur Segmen (Realisasi Fisik)
        // Gunakan buffer lateral 4 m berujung rata (endcap=flat, UTM 49S SRID 32749) agar sambungan ujung tidak ikut teriris
        $overlappingSegmen = DB::select("
            SELECT
                seg.id AS id_segmen,
                seg.tipe_kode,
                seg.namobj,
                seg.kondisi,
                seg.status_kondisi,
                seg.tahun_pembangunan,
                seg.sumber_dana,
                seg.keterangan,
                COALESCE(seg.panjang, ROUND(ST_Length(seg.geom::geography)::numeric, 2)) AS panjang_segmen_m,
                ROUND(
                    ST_Length(
                        ST_Intersection(
                            seg.geom,
                            ST_Transform(ST_Buffer(ST_Transform(l_curr.geom, 32749), 4, 'endcap=flat'), 4326)
                        )::geography
                    )::numeric,
                    2
                ) AS panjang_irisan_m,
                ST_AsGeoJSON(
                    ST_Intersection(
                        seg.geom,
                        ST_Transform(ST_Buffer(ST_Transform(l_curr.geom, 32749), 4, 'endcap=flat'), 4326)
                    )
                ) AS irisan_geojson
            FROM infrastruktur_segmen seg
            JOIN bkkd_proposal_lokasi l_curr ON l_curr.id = :lokasiId
            WHERE seg.id_desa = :idDesa
              AND ST_Intersects(
                  seg.geom,
                  ST_Transform(ST_Buffer(ST_Transform(l_curr.geom, 32749), 4, 'endcap=flat'), 4326)
              )
        ", [
            'lokasiId' => $lokasiId,
            'idDesa' => $idDesa,
        ]);

        // 3. Irisan dengan Master Jalan Poros & Desa (termasuk ruas hasil promosi proposal terverifikasi)
        $overlappingJalanPoros = [];
        if (Schema::hasTable('jalan_porosdesa')) {
            $overlappingJalanPoros = DB::select("
                SELECT
                    jp.id AS id_jalan_poros,
                    jp.id AS id_jaringan_ruas,
                    jp.kode_ruas,
                    jp.nama_ruas,
                    COALESCE(jp.hierarki_jalan, 'poros_desa') AS hierarki_jalan,
                    COALESCE(jp.sumber_data, 'jalan_porosdesa') AS sumber_master,
                    COALESCE(jp.panjang, ROUND(ST_Length(jp.geom::geography)::numeric, 2)) AS panjang_ruas_m,
                    jp.lebar AS lebar_ruas_m,
                    jp.perkerasan,
                    jp.kondisi,
                    jp.status_eksisting,
                    COALESCE(
                        ROUND(
                            ST_Length(
                                ST_Intersection(
                                    jp.geom,
                                    ST_Buffer(l_curr.geom::geography, 10)::geometry
                                )::geography
                            )::numeric,
                            2
                        ),
                        0
                    ) AS panjang_irisan_m,
                    ST_AsGeoJSON(
                        ST_Intersection(
                            jp.geom,
                            ST_Buffer(l_curr.geom::geography, 10)::geometry
                        )
                    ) AS irisan_geojson
                FROM jalan_porosdesa jp
                JOIN bkkd_proposal_lokasi l_curr ON l_curr.id = :lokasiId
                WHERE jp.geom IS NOT NULL
                  AND (
                      jp.id::text = :idJalanPoros
                      OR jp.id::text = :idJaringanRuas
                      OR ST_Intersects(
                          jp.geom,
                          ST_Buffer(l_curr.geom::geography, 10)::geometry
                      )
                  )
                ORDER BY panjang_irisan_m DESC
            ", [
                'lokasiId' => $lokasiId,
                'idJalanPoros' => !empty($lokasi->id_jalan_poros) ? (string) $lokasi->id_jalan_poros : '',
                'idJaringanRuas' => !empty($lokasi->id_jaringan_ruas) ? (string) $lokasi->id_jaringan_ruas : '',
            ]);
        }

        // 4. Irisan dengan Jalan Kabupaten (jalan_kabupaten_luarkota) untuk cek kewenangan
        $overlappingJalanKabupaten = [];
        if (Schema::hasTable('jalan_kabupaten_luarkota')) {
            try {
                $kabCols = Schema::getColumnListing('jalan_kabupaten_luarkota');
                $pickKabCol = function (string $lower, string $legacy, string $fallbackSql = 'NULL::text') use ($kabCols) {
                    if (in_array($lower, $kabCols, true)) {
                        return 'jk.' . $lower;
                    }
                    if (in_array($legacy, $kabCols, true)) {
                        return 'jk."' . $legacy . '"';
                    }
                    return $fallbackSql;
                };

                $kabNamaExpr = $pickKabCol('nama_ruas', 'Nama_Ruas', "'Jalan Kabupaten'::varchar");
                $kabNoExpr = $pickKabCol('no_ruas', 'No_Ruas', 'NULL::double precision');
                $kabPangkalExpr = $pickKabCol('pangkal', 'Pangkal', 'NULL::varchar');
                $kabUjungExpr = $pickKabCol('ujung', 'Ujung', 'NULL::varchar');
                $kabKlasExpr = $pickKabCol('klas_ruas', 'Klas_Ruas', 'NULL::varchar');
                $kabStatusExpr = $pickKabCol('status_ruas', 'Status_Ruas', 'NULL::varchar');
                $kabKecExpr = $pickKabCol('nama_kecamatan', 'Nama_Kecamatan', 'NULL::varchar');
                $kabPanjangExpr = $pickKabCol('panjang', 'Panjang', '0::double precision');

                $overlappingJalanKabupaten = DB::select("
                    SELECT
                        jk.id::text AS id_jalan_kabupaten,
                        {$kabNoExpr} AS no_ruas,
                        COALESCE({$kabNamaExpr}, 'Jalan Kabupaten') AS nama_ruas,
                        {$kabPangkalExpr} AS pangkal,
                        {$kabUjungExpr} AS ujung,
                        {$kabKlasExpr} AS klas_ruas,
                        {$kabStatusExpr} AS status_ruas,
                        {$kabKecExpr} AS kecamatan,
                        COALESCE(NULLIF({$kabPanjangExpr}, 0), ROUND(ST_Length(ST_Transform(jk.geom, 4326)::geography)::numeric, 2)) AS panjang_ruas_m,
                        COALESCE(
                            ROUND(
                                ST_Length(
                                    ST_Intersection(
                                        ST_Transform(jk.geom, 4326),
                                        ST_Transform(ST_Buffer(ST_Transform(l_curr.geom, 32749), 5, 'endcap=flat'), 4326)
                                    )::geography
                                )::numeric,
                                2
                            ),
                            0
                        ) AS panjang_irisan_m,
                        ST_AsGeoJSON(
                            ST_Intersection(
                                ST_Transform(jk.geom, 4326),
                                ST_Transform(ST_Buffer(ST_Transform(l_curr.geom, 32749), 5, 'endcap=flat'), 4326)
                            )
                        ) AS irisan_geojson
                    FROM jalan_kabupaten_luarkota jk
                    JOIN bkkd_proposal_lokasi l_curr ON l_curr.id = :lokasiId
                    WHERE jk.geom IS NOT NULL
                      AND ST_Intersects(
                          ST_Transform(jk.geom, 4326),
                          ST_Transform(ST_Buffer(ST_Transform(l_curr.geom, 32749), 5, 'endcap=flat'), 4326)
                      )
                    ORDER BY panjang_irisan_m DESC
                ", [
                    'lokasiId' => $lokasiId,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Gagal menganalisa irisan jalan_kabupaten_luarkota: ' . $e->getMessage());
            }
        }

        return $this->evaluateOverlapRecords(
            $overlappingProposals,
            $overlappingSegmen,
            $overlappingJalanPoros,
            $lokasi,
            $proposal,
            true,
            $overlappingJalanKabupaten
        );
    }

    /**
     * Logika analisa tumpang tindih 3 lapis di SQLite (lingkungan automated test).
     */
    protected function analisaLokasiSqlite(BkkdProposalLokasi $lokasi, BkkdProposal $proposal): array
    {
        $idDesa = (int) $proposal->id_desa;
        $tahunAnggaran = (int) $proposal->tahun_anggaran;
        $currCoords = $this->extractCoordinates($lokasi->geom);
        $currLength = (float) $lokasi->panjang_gis_m;

        // 1. Irisan dengan Proposal di Desa yang sama (seluruh tahun: T-n, T, maupun T+n, serta penyandingan PRKPD <-> RKPD)
        $currentTagging = strtoupper((string) ($proposal->tagging_usulan ?: BkkdProposal::TAGGING_PRKPD));
        $otherProposals = BkkdProposal::where('id_desa', $idDesa)
            ->when(!empty($proposal->id), fn ($q) => $q->where('id', '!=', $proposal->id))
            ->whereNull('deleted_at')
            ->with('lokasi')
            ->get()
            ->sortBy(function ($p) use ($currentTagging, $tahunAnggaran) {
                $refTagging = strtoupper((string) ($p->tagging_usulan ?: BkkdProposal::TAGGING_PRKPD));
                $isCrossTag = $refTagging !== $currentTagging ? 0 : 1;
                $yearDiff = abs((int) $p->tahun_anggaran - $tahunAnggaran);
                return sprintf('%d-%04d', $isCrossTag, $yearDiff);
            });

        $overlappingRecords = [];
        foreach ($otherProposals as $pPrev) {
            foreach ($pPrev->lokasi as $lPrev) {
                $prevCoords = $this->extractCoordinates($lPrev->geom);
                $overlapMeters = $this->calculateCoordinatesOverlap($currCoords, $prevCoords, $currLength, (float) $lPrev->panjang_gis_m);

                if ($overlapMeters > 0) {
                    $overlappingRecords[] = (object) [
                        'id_lokasi_lama' => $lPrev->id,
                        'nama_lokasi_lama' => $lPrev->nama_lokasi,
                        'panjang_usulan_lama_m' => (float) $lPrev->panjang_gis_m,
                        'id_proposal_lama' => $pPrev->id,
                        'nomor_registrasi' => $pPrev->nomor_registrasi,
                        'tahun_anggaran' => (int) $pPrev->tahun_anggaran,
                        'tagging_usulan_referensi' => strtoupper((string) ($pPrev->tagging_usulan ?: BkkdProposal::TAGGING_PRKPD)),
                        'judul_kegiatan' => $pPrev->judul_kegiatan,
                        'status_proposal_lama' => $pPrev->status_analisa_spasial,
                        'panjang_irisan_usulan_m' => round($overlapMeters, 2),
                    ];
                }
            }
        }

        // 2. Irisan langsung dengan Infrastruktur Segmen
        $overlappingSegmen = [];
        if (Schema::hasTable('infrastruktur_segmen')) {
            $segmens = InfrastrukturSegmen::where('id_desa', $idDesa)->get();
            foreach ($segmens as $seg) {
                $segCoords = $this->extractCoordinates($seg->geom);
                $segLength = (float) ($seg->panjang ?: $this->calculateGeoJsonLength($segCoords));
                $overlapMeters = $this->calculateCoordinatesOverlap($currCoords, $segCoords, $currLength, $segLength);

                if ($overlapMeters > 0) {
                    $kondisiVal = $seg->kondisi instanceof \BackedEnum ? $seg->kondisi->value : (string) $seg->kondisi;
                    $overlappingSegmen[] = (object) [
                        'id_segmen' => $seg->id,
                        'tipe_kode' => $seg->tipe_kode,
                        'namobj' => $seg->namobj ?: ($seg->keterangan ?: 'Segmen Infrastruktur'),
                        'kondisi' => $kondisiVal,
                        'status_kondisi' => $seg->status_kondisi,
                        'tahun_pembangunan' => $seg->tahun_pembangunan ? (int) $seg->tahun_pembangunan : null,
                        'sumber_dana' => $seg->sumber_dana,
                        'keterangan' => $seg->keterangan,
                        'panjang_segmen_m' => round($segLength, 2),
                        'panjang_irisan_m' => round($overlapMeters, 2),
                        'irisan_geojson' => is_string($seg->geom) ? $seg->geom : json_encode($seg->geom),
                    ];
                }
            }
        }

        // 3. Irisan dengan Koridor Master Jalan Poros & Desa (termasuk ruas hasil promosi proposal)
        $overlappingJalanPoros = [];
        if (Schema::hasTable('jalan_porosdesa')) {
            $porosList = JalanPorosDesa::query()
                ->where(function ($q) use ($idDesa) {
                    $q->where('id_desa', $idDesa)->orWhereNull('id_desa');
                })
                ->get();

            foreach ($porosList as $jp) {
                $jpCoords = $this->extractCoordinates($jp->geom);
                $jpLength = (float) ($jp->panjang ?: $this->calculateGeoJsonLength($jpCoords));
                $overlapMeters = $this->calculateCoordinatesOverlap($currCoords, $jpCoords, $currLength, $jpLength);

                $isSelfLinked = ($lokasi->id_jalan_poros && $lokasi->id_jalan_poros === $jp->id)
                    || ($lokasi->id_jaringan_ruas && $lokasi->id_jaringan_ruas === $jp->id);

                if ($overlapMeters > 0 || $isSelfLinked) {
                    $effectiveOverlap = $overlapMeters > 0 ? $overlapMeters : min($currLength, $jpLength);
                    $overlappingJalanPoros[] = (object) [
                        'id_jalan_poros' => $jp->id,
                        'id_jaringan_ruas' => $jp->id,
                        'kode_ruas' => $jp->kode_ruas,
                        'nama_ruas' => $jp->nama_ruas,
                        'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                        'sumber_master' => $jp->sumber_data ?? JalanPorosDesa::SUMBER_SK_PEMDA,
                        'panjang_ruas_m' => round($jpLength, 2),
                        'lebar_ruas_m' => $jp->lebar !== null ? (float) $jp->lebar : null,
                        'perkerasan' => $jp->perkerasan,
                        'kondisi' => $jp->kondisi,
                        'status_eksisting' => $jp->status_eksisting,
                        'panjang_irisan_m' => round($effectiveOverlap, 2),
                        'irisan_geojson' => is_string($jp->geom) ? $jp->geom : json_encode($jp->geom),
                    ];
                }
            }
        }

        // 4. Irisan dengan Jalan Kabupaten (jalan_kabupaten_luarkota)
        $overlappingJalanKabupaten = [];
        if (Schema::hasTable('jalan_kabupaten_luarkota')) {
            $kabList = DB::table('jalan_kabupaten_luarkota')->get();
            foreach ($kabList as $jk) {
                $jkGeom = $jk->geom ?? null;
                if (!$jkGeom) {
                    continue;
                }
                $jkCoords = $this->extractCoordinates($jkGeom);
                $jkLength = (float) (($jk->panjang ?? null) ?: $this->calculateGeoJsonLength($jkCoords));
                $overlapMeters = $this->calculateCoordinatesOverlap($currCoords, $jkCoords, $currLength, $jkLength);
                if ($overlapMeters > 0) {
                    $overlappingJalanKabupaten[] = (object) [
                        'id_jalan_kabupaten' => (string) $jk->id,
                        'no_ruas' => $jk->no_ruas ?? null,
                        'nama_ruas' => $jk->nama_ruas ?? 'Jalan Kabupaten',
                        'pangkal' => $jk->pangkal ?? null,
                        'ujung' => $jk->ujung ?? null,
                        'klas_ruas' => $jk->klas_ruas ?? null,
                        'status_ruas' => $jk->status_ruas ?? null,
                        'kecamatan' => $jk->nama_kecamatan ?? null,
                        'panjang_ruas_m' => round($jkLength, 2),
                        'panjang_irisan_m' => round($overlapMeters, 2),
                        'irisan_geojson' => is_string($jkGeom) ? $jkGeom : json_encode($jkGeom),
                    ];
                }
            }
        }

        return $this->evaluateOverlapRecords(
            $overlappingRecords,
            $overlappingSegmen,
            $overlappingJalanPoros,
            $lokasi,
            $proposal,
            false,
            $overlappingJalanKabupaten
        );
    }

    /**
     * Evaluasi record irisan 3 lapis (Tahun T-1, Infrastruktur Segmen, Jalan Poros Desa) untuk menentukan status dan rekomendasi Bappeda.
     */
    protected function evaluateOverlapRecords(
        array $overlappingRecords,
        array $overlappingSegmen,
        array $overlappingJalanPoros,
        BkkdProposalLokasi $lokasi,
        BkkdProposal $proposal,
        bool $isPgsql,
        array $overlappingJalanKabupaten = []
    ): array {
        $panjangLokasiIni = max(1.0, (float) $lokasi->panjang_gis_m);

        // Format daftar Referensi Infrastruktur Segmen
        $referensiSegmenList = [];
        $totalIrisanSegmenBaik = 0.0;
        $totalIrisanSegmenAll = 0.0;
        foreach ($overlappingSegmen as $seg) {
            $panjangSegmen = (float) $seg->panjang_segmen_m;
            $panjangIrisan = (float) $seg->panjang_irisan_m;
            // Abaikan persinggungan ujung/simpang <= 10 m agar tidak dianggap tumpang tindih segmen terbangun
            if ($panjangIrisan <= 10.0) {
                continue;
            }
            $persenIrisanUsulan = round(min(100.0, ($panjangIrisan / $panjangLokasiIni) * 100), 1);
            $kondisiStr = (string) ($seg->kondisi ?? 'Baik');

            $totalIrisanSegmenAll += $panjangIrisan;
            if (strtolower($kondisiStr) === 'baik') {
                $totalIrisanSegmenBaik += $panjangIrisan;
            }

            $irisanGeoJson = null;
            if (!empty($seg->irisan_geojson)) {
                $irisanGeoJson = is_string($seg->irisan_geojson)
                    ? json_decode($seg->irisan_geojson, true)
                    : $seg->irisan_geojson;
            }

            $referensiSegmenList[] = [
                'id_segmen' => $seg->id_segmen,
                'tipe_kode' => $seg->tipe_kode,
                'namobj' => $seg->namobj ?? 'Segmen Infrastruktur',
                'kondisi' => $kondisiStr,
                'status_kondisi' => $seg->status_kondisi ?? 'Eksisting',
                'tahun_pembangunan' => $seg->tahun_pembangunan ? (int) $seg->tahun_pembangunan : null,
                'sumber_dana' => $seg->sumber_dana,
                'panjang_segmen_m' => $panjangSegmen,
                'panjang_irisan_m' => $panjangIrisan,
                'persen_irisan_usulan' => $persenIrisanUsulan,
                'irisan_geojson' => $irisanGeoJson,
            ];
        }

        // Format daftar Referensi Jalan Poros Desa
        $referensiJalanPorosList = [];
        $totalIrisanJalanPoros = 0.0;
        foreach ($overlappingJalanPoros as $jp) {
            $panjangRuas = max(1.0, (float) $jp->panjang_ruas_m);
            $panjangIrisan = (float) $jp->panjang_irisan_m;
            $persenCakupan = round(min(100.0, ($panjangIrisan / $panjangRuas) * 100), 1);
            $persenMasukKoridor = round(min(100.0, ($panjangIrisan / $panjangLokasiIni) * 100), 1);
            $totalIrisanJalanPoros += $panjangIrisan;

            $irisanPorosGeoJson = null;
            if (!empty($jp->irisan_geojson)) {
                $irisanPorosGeoJson = is_string($jp->irisan_geojson)
                    ? json_decode($jp->irisan_geojson, true)
                    : $jp->irisan_geojson;
            }

            $referensiJalanPorosList[] = [
                'id_jalan_poros' => $jp->id_jalan_poros ?? null,
                'id_jaringan_ruas' => $jp->id_jaringan_ruas ?? null,
                'kode_ruas' => $jp->kode_ruas ?? null,
                'nama_ruas' => $jp->nama_ruas,
                'hierarki_jalan' => $jp->hierarki_jalan ?? 'poros_desa',
                'sumber_master' => $jp->sumber_master ?? 'jalan_porosdesa',
                'panjang_ruas_m' => (float) $jp->panjang_ruas_m,
                'lebar_ruas_m' => $jp->lebar_ruas_m !== null ? (float) $jp->lebar_ruas_m : null,
                'perkerasan' => $jp->perkerasan,
                'kondisi' => $jp->kondisi,
                'status_eksisting' => $jp->status_eksisting,
                'panjang_irisan_m' => $panjangIrisan,
                'persen_cakupan_ruas' => $persenCakupan,
                'persen_masuk_koridor' => $persenMasukKoridor,
                'irisan_geojson' => $irisanPorosGeoJson,
            ];
        }

        // Format daftar Referensi Jalan Kabupaten (jalan_kabupaten_luarkota)
        $referensiJalanKabupatenList = [];
        $totalIrisanJalanKabupaten = 0.0;
        foreach ($overlappingJalanKabupaten as $jk) {
            $panjangIrisan = (float) ($jk->panjang_irisan_m ?? 0);
            $persenMasukKoridor = round(min(100.0, ($panjangIrisan / $panjangLokasiIni) * 100), 1);
            // Abaikan persinggungan kecil di pertigaan/pangkal (< 15 m dan < 10% koridor usulan)
            if ($panjangIrisan < 15.0 && $persenMasukKoridor < 10.0) {
                continue;
            }
            $totalIrisanJalanKabupaten += $panjangIrisan;

            $irisanKabGeoJson = null;
            if (!empty($jk->irisan_geojson)) {
                $irisanKabGeoJson = is_string($jk->irisan_geojson)
                    ? json_decode($jk->irisan_geojson, true)
                    : $jk->irisan_geojson;
            }

            $referensiJalanKabupatenList[] = [
                'id_jalan_kabupaten' => (string) $jk->id_jalan_kabupaten,
                'no_ruas' => $jk->no_ruas !== null ? (string) $jk->no_ruas : null,
                'nama_ruas' => $jk->nama_ruas ?: 'Jalan Kabupaten',
                'pangkal' => $jk->pangkal ?? null,
                'ujung' => $jk->ujung ?? null,
                'klas_ruas' => $jk->klas_ruas ?? null,
                'status_ruas' => $jk->status_ruas ?? null,
                'kecamatan' => $jk->kecamatan ?? null,
                'panjang_ruas_m' => round((float) ($jk->panjang_ruas_m ?? 0), 2),
                'panjang_irisan_m' => round($panjangIrisan, 2),
                'persen_masuk_koridor' => $persenMasukKoridor,
                'irisan_geojson' => $irisanKabGeoJson,
            ];
        }

        $effectiveIrisanSegmen = min($panjangLokasiIni, $totalIrisanSegmenAll);
        $effectiveIrisanPoros = min($panjangLokasiIni, $totalIrisanJalanPoros);
        $effectiveIrisanKabupaten = min($panjangLokasiIni, $totalIrisanJalanKabupaten);
        $sisaBelumTerbangunM = round(max(0.0, $panjangLokasiIni - $effectiveIrisanSegmen), 2);
        $persenIrisanSegmen = round(min(100.0, ($effectiveIrisanSegmen / $panjangLokasiIni) * 100), 1);
        $persenKoridorPoros = round(min(100.0, ($effectiveIrisanPoros / $panjangLokasiIni) * 100), 1);
        $persenKoridorKabupaten = round(min(100.0, ($effectiveIrisanKabupaten / $panjangLokasiIni) * 100), 1);

        $existingSumberRute = is_array($lokasi->ringkasan_analisa) ? ($lokasi->ringkasan_analisa['sumber_rute'] ?? null) : null;
        $sumberRute = $existingSumberRute ?: (!empty($referensiJalanPorosList) ? 'jalan_porosdesa' : 'openrouteservice');

        $metaMetrics = [
            'sumber_rute' => $sumberRute,
            'panjang_usulan_gis_m' => round($panjangLokasiIni, 2),
            'total_irisan_jalan_poros_m' => round($effectiveIrisanPoros, 2),
            'persen_koridor_jalan_poros' => $persenKoridorPoros,
            'total_irisan_jalan_kabupaten_m' => round($effectiveIrisanKabupaten, 2),
            'persen_koridor_jalan_kabupaten' => $persenKoridorKabupaten,
            'total_irisan_segmen_m' => round($effectiveIrisanSegmen, 2),
            'total_irisan_segmen_baik_m' => round(min($panjangLokasiIni, $totalIrisanSegmenBaik), 2),
            'sisa_belum_terbangun_m' => $sisaBelumTerbangunM,
            'persen_irisan_segmen' => $persenIrisanSegmen,
        ];

        $infoPorosSuffix = '';
        if (!empty($referensiJalanPorosList)) {
            $topPoros = $referensiJalanPorosList[0];
            $infoPorosSuffix = sprintf(
                ' Berada pada koridor Jalan Poros Desa "%s" (Panjang ruas: %s m, Kondisi: %s, Cakupan usulan: %s%%).',
                $topPoros['nama_ruas'] ?? 'Ruas Poros',
                $topPoros['panjang_ruas_m'],
                $topPoros['kondisi'] ?: '-',
                $topPoros['persen_cakupan_ruas']
            );
        }
        if (!empty($referensiJalanKabupatenList)) {
            $topKab = $referensiJalanKabupatenList[0];
            $infoPorosSuffix .= sprintf(
                ' Beririsan dengan Jalan Kabupaten "%s" (No. Ruas: %s, Irisan: %s m / %s%%). Periksa kewenangan ruas agar tidak tumpang tindih dengan jalan kabupaten.',
                $topKab['nama_ruas'] ?? 'Jalan Kabupaten',
                $topKab['no_ruas'] ?: '-',
                $topKab['panjang_irisan_m'],
                $topKab['persen_masuk_koridor']
            );
        }

        // Format daftar Referensi Proposal Lintas Tahun (T-n, T, T+n) & Penyandingan PRKPD <-> RKPD
        $currentTahun = (int) $proposal->tahun_anggaran;
        $currentTagging = strtoupper((string) ($proposal->tagging_usulan ?: BkkdProposal::TAGGING_PRKPD));
        $referensiList = [];
        $duplikasiRefs = [];
        $belumTuntasRefs = [];
        $lanjutanRefs = [];

        foreach ($overlappingRecords as $rec) {
            $idLokasiLama = $rec->id_lokasi_lama;
            $panjangUsulanLama = (float) $rec->panjang_usulan_lama_m;
            $panjangIrisan = (float) $rec->panjang_irisan_usulan_m;
            $tahunRef = (int) $rec->tahun_anggaran;
            $refTagging = strtoupper((string) ($rec->tagging_usulan_referensi ?? BkkdProposal::TAGGING_PRKPD));
            $relasiTahun = $tahunRef < $currentTahun
                ? 'sebelumnya'
                : ($tahunRef === $currentTahun ? 'tahun_sama' : 'perencanaan_berikutnya');
            $pasanganTagging = ($currentTagging === 'PRKPD' && $refTagging === 'RKPD')
                ? 'prkpd_vs_rkpd'
                : (($currentTagging === 'RKPD' && $refTagging === 'PRKPD')
                    ? 'rkpd_vs_prkpd'
                    : 'lintas_tahun');

            $panjangRealisasi = $this->getPanjangRealisasiFisik($idLokasiLama, (int) $proposal->id_desa, $isPgsql);
            $persenRealisasi = $panjangUsulanLama > 0 ? round(($panjangRealisasi / $panjangUsulanLama) * 100, 1) : 0.0;
            $sisaBelumTuntas = max(0.0, round($panjangUsulanLama - $panjangRealisasi, 2));

            $refItem = [
                'id_proposal_lama' => $rec->id_proposal_lama,
                'nomor_registrasi' => $rec->nomor_registrasi,
                'tahun_anggaran' => $tahunRef,
                'tagging_usulan' => $refTagging,
                'pasangan_tagging' => $pasanganTagging,
                'relasi_tahun' => $relasiTahun,
                'judul_kegiatan' => $rec->judul_kegiatan,
                'id_lokasi_lama' => $idLokasiLama,
                'nama_lokasi_lama' => $rec->nama_lokasi_lama,
                'panjang_usulan_lama_m' => $panjangUsulanLama,
                'panjang_realisasi_m' => $panjangRealisasi,
                'sisa_belum_tuntas_m' => $sisaBelumTuntas,
                'persen_realisasi' => $persenRealisasi,
                'panjang_irisan_usulan_m' => $panjangIrisan,
            ];
            $referensiList[] = $refItem;

            if ($panjangIrisan <= 10.0) {
                $lanjutanRefs[] = $refItem;
            } elseif ($persenRealisasi >= 90.0) {
                $duplikasiRefs[] = $refItem;
            } else {
                $belumTuntasRefs[] = $refItem;
            }
        }

        $hasDuplikasi = !empty($duplikasiRefs);
        $hasBelumTuntas = !empty($belumTuntasRefs);
        $hasLanjutanRuas = !empty($lanjutanRefs);

        // Jika tidak ada overlap di bkkd_proposal_lokasi lintas tahun, periksa apakah ada overlap langsung dengan Infrastruktur Segmen eksisting
        if (empty($referensiList) && !empty($referensiSegmenList)) {
            $topSeg = $referensiSegmenList[0];
            $persenTerbangun = round(min(100.0, ($totalIrisanSegmenBaik / $panjangLokasiIni) * 100), 1);

            if ($topSeg['panjang_irisan_m'] <= 10.0) {
                return array_merge($metaMetrics, [
                    'status' => 'lanjutan_ruas',
                    'terdeteksi_overlap_usulan' => false,
                    'terdeteksi_overlap_segmen' => true,
                    'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                    'rekomendasi_bappeda' => sprintf(
                        'Lokasi menyambung ujung segmen infrastruktur eksisting "%s" (Tahun %s).%s',
                        $topSeg['namobj'],
                        $topSeg['tahun_pembangunan'] ?: '-',
                        $infoPorosSuffix
                    ),
                    'referensi_tahun_lalu' => [],
                    'referensi_infrastruktur_segmen' => $referensiSegmenList,
                    'referensi_jalan_poros' => $referensiJalanPorosList,
                    'analyzed_at' => now()->toIso8601String(),
                ]);
            }

            if ($persenTerbangun >= 90.0) {
                return array_merge($metaMetrics, [
                    'status' => 'duplikasi_tuntas',
                    'terdeteksi_overlap_usulan' => false,
                    'terdeteksi_overlap_segmen' => true,
                    'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                    'rekomendasi_bappeda' => sprintf(
                        'Peringatan: Lokasi beririsan sepanjang %s m (%s%%) dengan Infrastruktur Segmen "%s" (Tahun %s, Kondisi: %s) yang sudah terbangun tuntas.%s',
                        $topSeg['panjang_irisan_m'],
                        $persenTerbangun,
                        $topSeg['namobj'],
                        $topSeg['tahun_pembangunan'] ?: '-',
                        $topSeg['kondisi'],
                        $infoPorosSuffix
                    ),
                    'referensi_tahun_lalu' => [],
                    'referensi_infrastruktur_segmen' => $referensiSegmenList,
                    'referensi_jalan_poros' => $referensiJalanPorosList,
                    'analyzed_at' => now()->toIso8601String(),
                ]);
            }

            if ($totalIrisanSegmenBaik > 10.0) {
                $sisaBelumTerbangun = max(0.0, round($panjangLokasiIni - $totalIrisanSegmenBaik, 2));
                return array_merge($metaMetrics, [
                    'status' => 'konfirmasi_belum_tuntas',
                    'terdeteksi_overlap_usulan' => false,
                    'terdeteksi_overlap_segmen' => true,
                    'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                    'rekomendasi_bappeda' => sprintf(
                        'Lokasi beririsan sepanjang %s m dengan Infrastruktur Segmen "%s" (realisasi fisik %s%%, sisa %s m belum terbangun). Konfirmasi ke Pemerintah Desa terkait batas segmen lanjutan.%s',
                        $topSeg['panjang_irisan_m'],
                        $topSeg['namobj'],
                        $persenTerbangun,
                        $sisaBelumTerbangun,
                        $infoPorosSuffix
                    ),
                    'referensi_tahun_lalu' => [],
                    'referensi_infrastruktur_segmen' => $referensiSegmenList,
                    'referensi_jalan_poros' => $referensiJalanPorosList,
                    'analyzed_at' => now()->toIso8601String(),
                ]);
            }
        }

        if ($hasDuplikasi) {
            $topRef = $duplikasiRefs[0];
            $rekomendasiDup = match ($topRef['pasangan_tagging'] ?? 'lintas_tahun') {
                'prkpd_vs_rkpd' => sprintf(
                    'Peringatan PRKPD %d vs RKPD %d: Lokasi beririsan sepanjang %s m dengan data RKPD %d (%s) yang telah terealisasi tuntas (%s%%). Berpotensi duplikasi anggaran lintas perencanaan.%s',
                    $currentTahun,
                    $topRef['tahun_anggaran'],
                    $topRef['panjang_irisan_usulan_m'],
                    $topRef['tahun_anggaran'],
                    $topRef['nomor_registrasi'],
                    $topRef['persen_realisasi'],
                    $infoPorosSuffix
                ),
                'rkpd_vs_prkpd' => sprintf(
                    'Peringatan RKPD %d vs PRKPD %d: Lokasi beririsan sepanjang %s m dengan usulan PRKPD %d (%s) yang telah terealisasi tuntas (%s%%). Berpotensi tumpang tindih anggaran.%s',
                    $currentTahun,
                    $topRef['tahun_anggaran'],
                    $topRef['panjang_irisan_usulan_m'],
                    $topRef['tahun_anggaran'],
                    $topRef['nomor_registrasi'],
                    $topRef['persen_realisasi'],
                    $infoPorosSuffix
                ),
                default => sprintf(
                    'Peringatan: Lokasi beririsan sepanjang %s m dengan Usulan %s %d (%s) yang telah terealisasi tuntas (%s%%). Berpotensi tumpang tindih anggaran di titik yang sama.%s',
                    $topRef['panjang_irisan_usulan_m'],
                    $topRef['tagging_usulan'],
                    $topRef['tahun_anggaran'],
                    $topRef['nomor_registrasi'],
                    $topRef['persen_realisasi'],
                    $infoPorosSuffix
                ),
            };

            return array_merge($metaMetrics, [
                'status' => 'duplikasi_tuntas',
                'terdeteksi_overlap_usulan' => true,
                'terdeteksi_overlap_segmen' => !empty($referensiSegmenList),
                'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                'terdeteksi_overlap_kabupaten' => !empty($referensiJalanKabupatenList),
                'rekomendasi_bappeda' => $rekomendasiDup,
                'referensi_tahun_lalu' => $referensiList,
                'referensi_infrastruktur_segmen' => $referensiSegmenList,
                'referensi_jalan_poros' => $referensiJalanPorosList,
                'referensi_jalan_kabupaten' => $referensiJalanKabupatenList,
                'analyzed_at' => now()->toIso8601String(),
            ]);
        }

        if (!empty($referensiJalanKabupatenList)) {
            $topKab = $referensiJalanKabupatenList[0];
            $rekomendasiKab = sprintf(
                'Peringatan Kewenangan Aset: Koridor beririsan dengan Jalan Kabupaten "%s" (No. Ruas: %s, Irisan: %s m / %s%%). BKKD wajib dilaksanakan pada aset Desa, bukan aset atau kewenangan Kabupaten.',
                $topKab['nama_ruas'] ?? 'Jalan Kabupaten',
                $topKab['no_ruas'] ?: '-',
                $topKab['panjang_irisan_m'],
                $topKab['persen_masuk_koridor']
            );

            return array_merge($metaMetrics, [
                'status' => 'kewenangan_kabupaten',
                'terdeteksi_overlap_usulan' => !empty($referensiList),
                'terdeteksi_overlap_segmen' => !empty($referensiSegmenList),
                'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                'terdeteksi_overlap_kabupaten' => true,
                'rekomendasi_bappeda' => $rekomendasiKab,
                'referensi_tahun_lalu' => $referensiList,
                'referensi_infrastruktur_segmen' => $referensiSegmenList,
                'referensi_jalan_poros' => $referensiJalanPorosList,
                'referensi_jalan_kabupaten' => $referensiJalanKabupatenList,
                'analyzed_at' => now()->toIso8601String(),
            ]);
        }

        if ($hasBelumTuntas) {
            $topRef = $belumTuntasRefs[0];
            $pasangan = $topRef['pasangan_tagging'] ?? 'lintas_tahun';

            if ($pasangan === 'prkpd_vs_rkpd') {
                $rekomendasi = sprintf(
                    'Penyandingan PRKPD %d dengan RKPD %d: Lokasi usulan PRKPD %d beririsan sepanjang %s m dengan RKPD Tahun %d (%s - %s) di desa yang sama. Konfirmasi ke Pemerintah Desa agar tidak terjadi tumpang tindih antara PRKPD dan RKPD.%s',
                    $currentTahun,
                    $topRef['tahun_anggaran'],
                    $currentTahun,
                    $topRef['panjang_irisan_usulan_m'],
                    $topRef['tahun_anggaran'],
                    $topRef['nomor_registrasi'],
                    $topRef['judul_kegiatan'],
                    $infoPorosSuffix
                );
            } elseif ($pasangan === 'rkpd_vs_prkpd') {
                $rekomendasi = sprintf(
                    'Penyandingan RKPD %d dengan PRKPD %d: Lokasi usulan RKPD %d beririsan sepanjang %s m dengan usulan PRKPD Tahun %d (%s - %s) di desa yang sama. Konfirmasi apakah usulan RKPD ini merupakan tindak lanjut atau pengajuan ganda dari PRKPD.%s',
                    $currentTahun,
                    $topRef['tahun_anggaran'],
                    $currentTahun,
                    $topRef['panjang_irisan_usulan_m'],
                    $topRef['tahun_anggaran'],
                    $topRef['nomor_registrasi'],
                    $topRef['judul_kegiatan'],
                    $infoPorosSuffix
                );
            } else {
                $rekomendasi = match ($topRef['relasi_tahun']) {
                    'perencanaan_berikutnya' => sprintf(
                        'Peringatan Perencanaan %s Tahun %d: Lokasi beririsan sepanjang %s m dengan proposal %s Tahun %d (%s - %s) di desa yang sama. Konfirmasi ke Pemerintah Desa agar tidak terjadi pengajuan ganda lintas tahun anggaran.%s',
                        $topRef['tagging_usulan'],
                        $topRef['tahun_anggaran'],
                        $topRef['panjang_irisan_usulan_m'],
                        $topRef['tagging_usulan'],
                        $topRef['tahun_anggaran'],
                        $topRef['nomor_registrasi'],
                        $topRef['judul_kegiatan'],
                        $infoPorosSuffix
                    ),
                    'tahun_sama' => sprintf(
                        'Peringatan Pengajuan Tahun Sama (%d): Lokasi beririsan sepanjang %s m dengan proposal %s lain (%s - %s) di desa yang sama pada tahun anggaran %d. Konfirmasi agar tidak terjadi duplikasi pengajuan ganda.%s',
                        $topRef['tahun_anggaran'],
                        $topRef['panjang_irisan_usulan_m'],
                        $topRef['tagging_usulan'],
                        $topRef['nomor_registrasi'],
                        $topRef['judul_kegiatan'],
                        $proposal->tahun_anggaran,
                        $infoPorosSuffix
                    ),
                    default => sprintf(
                        'Lokasi beririsan sepanjang %s m dengan Usulan %s %d (%s) yang realisasi fisiknya baru mencapai %s m dari %s m (sisa %s m belum tuntas). Konfirmasi ke Pemerintah Desa bahwa pengajuan tahun %d merupakan penuntasan sisa volume tahun %d.%s',
                        $topRef['panjang_irisan_usulan_m'],
                        $topRef['tagging_usulan'],
                        $topRef['tahun_anggaran'],
                        $topRef['nomor_registrasi'],
                        $topRef['panjang_realisasi_m'],
                        $topRef['panjang_usulan_lama_m'],
                        $topRef['sisa_belum_tuntas_m'],
                        $proposal->tahun_anggaran,
                        $topRef['tahun_anggaran'],
                        $infoPorosSuffix
                    ),
                };
            }

            return array_merge($metaMetrics, [
                'status' => 'konfirmasi_belum_tuntas',
                'terdeteksi_overlap_usulan' => true,
                'terdeteksi_overlap_segmen' => !empty($referensiSegmenList),
                'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                'terdeteksi_overlap_kabupaten' => !empty($referensiJalanKabupatenList),
                'rekomendasi_bappeda' => $rekomendasi,
                'referensi_tahun_lalu' => $referensiList,
                'referensi_infrastruktur_segmen' => $referensiSegmenList,
                'referensi_jalan_poros' => $referensiJalanPorosList,
                'referensi_jalan_kabupaten' => $referensiJalanKabupatenList,
                'analyzed_at' => now()->toIso8601String(),
            ]);
        }

        if ($hasLanjutanRuas) {
            $topLan = $lanjutanRefs[0];
            $lanMessage = match ($topLan['relasi_tahun']) {
                'perencanaan_berikutnya' => sprintf(
                    'Lokasi bersambung dengan rencana usulan desa Tahun %d (%s).%s',
                    $topLan['tahun_anggaran'],
                    $topLan['nomor_registrasi'],
                    $infoPorosSuffix
                ),
                'tahun_sama' => sprintf(
                    'Lokasi bersambung dengan proposal lain di desa pada tahun anggaran %d (%s).%s',
                    $topLan['tahun_anggaran'],
                    $topLan['nomor_registrasi'],
                    $infoPorosSuffix
                ),
                default => 'Lokasi terhubung melanjutkan ruas pembangunan tahun sebelumnya.' . $infoPorosSuffix,
            };

            return array_merge($metaMetrics, [
                'status' => 'lanjutan_ruas',
                'terdeteksi_overlap_usulan' => false,
                'terdeteksi_overlap_segmen' => !empty($referensiSegmenList),
                'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
                'terdeteksi_overlap_kabupaten' => !empty($referensiJalanKabupatenList),
                'rekomendasi_bappeda' => $lanMessage,
                'referensi_tahun_lalu' => $referensiList,
                'referensi_infrastruktur_segmen' => $referensiSegmenList,
                'referensi_jalan_poros' => $referensiJalanPorosList,
                'referensi_jalan_kabupaten' => $referensiJalanKabupatenList,
                'analyzed_at' => now()->toIso8601String(),
            ]);
        }

        return array_merge($metaMetrics, [
            'status' => 'aman',
            'terdeteksi_overlap_usulan' => false,
            'terdeteksi_overlap_segmen' => !empty($referensiSegmenList),
            'terhubung_jalan_poros' => !empty($referensiJalanPorosList),
            'terdeteksi_overlap_kabupaten' => !empty($referensiJalanKabupatenList),
            'rekomendasi_bappeda' => 'Lokasi baru, tidak ditemukan tumpang tindih dengan proposal di desa pada seluruh tahun anggaran maupun infrastruktur segmen.' . $infoPorosSuffix,
            'referensi_tahun_lalu' => $referensiList,
            'referensi_infrastruktur_segmen' => $referensiSegmenList,
            'referensi_jalan_poros' => $referensiJalanPorosList,
            'referensi_jalan_kabupaten' => $referensiJalanKabupatenList,
            'analyzed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Gabungkan beberapa segmen ekstrak menjadi 1 LineString tunggal ketika mengedit 1 id_lokasi eksisting.
     *
     * @param  array<int, array<string, mixed>>  $extractedList
     * @return array<int, array<string, mixed>>
     */
    public function collapseLocationsToSingleItem(array $extractedList, ?string $preferredNamaLokasi = null): array
    {
        if (count($extractedList) <= 1) {
            return $extractedList;
        }

        $mergedCoords = [];
        foreach ($extractedList as $seg) {
            $coords = $this->extractCoordinates($seg['geojson'] ?? []);
            foreach ($coords as $pt) {
                $lng = (float) $pt[0];
                $lat = (float) $pt[1];
                $last = !empty($mergedCoords) ? $mergedCoords[count($mergedCoords) - 1] : null;
                if ($last && abs($last[0] - $lng) < 1e-7 && abs($last[1] - $lat) < 1e-7) {
                    continue;
                }
                $mergedCoords[] = [$lng, $lat];
            }
        }

        $first = $extractedList[0];
        if (count($mergedCoords) < 2) {
            return [$first];
        }

        $mergedGeoJson = [
            'type' => 'LineString',
            'coordinates' => $mergedCoords,
        ];
        $firstPt = $mergedCoords[0];
        $lastPt = $mergedCoords[count($mergedCoords) - 1];

        return [[
            'id_jalan_poros' => $first['id_jalan_poros'] ?? null,
            'nama_ruas' => $first['nama_ruas'] ?? null,
            'nama_lokasi' => trim((string) $preferredNamaLokasi) !== ''
                ? trim((string) $preferredNamaLokasi)
                : ($first['nama_lokasi'] ?? 'Lokasi Usulan'),
            'sumber_rute' => $first['sumber_rute'] ?? 'jalan_porosdesa',
            'titik_awal' => ['lat' => round($firstPt[1], 6), 'lng' => round($firstPt[0], 6)],
            'titik_akhir' => ['lat' => round($lastPt[1], 6), 'lng' => round($lastPt[0], 6)],
            'geojson' => $mergedGeoJson,
            'panjang_gis_m' => $this->calculateGeoJsonLength($mergedGeoJson),
        ]];
    }

    /**
     * Generate rute LineString (Jalan Poros Desa / OpenRouteService) dari titik awal-akhir dan langsung jalankan preview analisa spasial.
     */
    public function previewRouteAndAnalyze(int $idDesa, int $tahunAnggaran, array $input): array
    {
        $extractedList = $this->extractLocationsFromCoordinatesOrGeoJson($idDesa, $input);

        // Bila mode edit per id_lokasi / single_location_mode, pastikan hasil generate tetap 1 segmen agar tidak terjadi double data
        $editingLokasiId = !empty($input['id_lokasi']) ? (string) $input['id_lokasi'] : null;
        if (($editingLokasiId !== null || !empty($input['single_location_mode'])) && count($extractedList) > 1) {
            $extractedList = $this->collapseLocationsToSingleItem(
                $extractedList,
                isset($input['nama_lokasi']) ? (string) $input['nama_lokasi'] : null
            );
        }

        $results = [];
        $statuses = [];
        $totalLength = 0.0;

        $excludeProposalId = !empty($input['id_proposal']) ? (string) $input['id_proposal'] : null;
        $taggingUsulan = !empty($input['tagging_usulan'])
            ? strtoupper(trim((string) $input['tagging_usulan']))
            : BkkdProposal::TAGGING_PRKPD;

        foreach ($extractedList as $idx => $ext) {
            $ringkasan = $this->analisaGeoJsonPreview(
                idDesa: $idDesa,
                tahunAnggaran: $tahunAnggaran,
                geoJson: $ext['geojson'],
                idJalanPoros: $ext['id_jalan_poros'] ?? null,
                sumberRute: $ext['sumber_rute'] ?? null,
                excludeProposalId: $excludeProposalId,
                taggingUsulan: $taggingUsulan
            );

            $status = $ringkasan['status'] ?? 'aman';
            $statuses[] = $status;
            $totalLength += (float) $ext['panjang_gis_m'];

            $results[] = [
                'id' => $idx === 0 ? $editingLokasiId : null,
                'urutan' => $idx + 1,
                'nama_lokasi' => $ext['nama_lokasi'],
                'id_jalan_poros' => $ext['id_jalan_poros'] ?? ($ringkasan['referensi_jalan_poros'][0]['id_jalan_poros'] ?? null),
                'nama_ruas' => $ext['nama_ruas'] ?? ($ringkasan['referensi_jalan_poros'][0]['nama_ruas'] ?? null),
                'sumber_rute' => $ext['sumber_rute'] ?? ($ringkasan['sumber_rute'] ?? 'jalan_porosdesa'),
                'titik_awal' => $ext['titik_awal'] ?? null,
                'titik_akhir' => $ext['titik_akhir'] ?? null,
                'titik_sebaran' => $ext['titik_sebaran'] ?? null,
                'panjang_gis_m' => (float) $ext['panjang_gis_m'],
                'panjang_rencana_m' => isset($input['panjang_rencana_m']) && count($extractedList) === 1
                    ? (float) $input['panjang_rencana_m']
                    : (float) $ext['panjang_gis_m'],
                'lebar_m' => isset($input['lebar_m']) ? (float) $input['lebar_m'] : null,
                'geojson' => $ext['geojson'],
                'status_analisa_spasial' => $status,
                'ringkasan_analisa' => $ringkasan,
            ];
        }

        return [
            'lokasi_list' => $results,
            'jumlah_lokasi' => count($results),
            'total_panjang_gis' => round($totalLength, 2),
            'status_analisa_spasial' => $this->determineOverallStatus($statuses),
        ];
    }

    /**
     * Analisa spasial langsung terhadap geometri GeoJSON (tanpa menyimpan ke database terlebih dahulu).
     */
    public function analisaGeoJsonPreview(
        int $idDesa,
        int $tahunAnggaran,
        array $geoJson,
        ?string $idJalanPoros = null,
        ?string $sumberRute = null,
        ?string $excludeProposalId = null,
        ?string $taggingUsulan = null
    ): array {
        $normalized = $this->normalizeGeoJson($geoJson);
        $jsonString = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        $panjangGisM = $this->calculateGeoJsonLength($normalized);
        $currentTagging = in_array(strtoupper((string) $taggingUsulan), ['RKPD', 'PRKPD'], true)
            ? strtoupper((string) $taggingUsulan)
            : BkkdProposal::TAGGING_PRKPD;

        $dummyProposal = new BkkdProposal([
            'id_desa' => $idDesa,
            'tahun_anggaran' => $tahunAnggaran,
            'tagging_usulan' => $currentTagging,
        ]);
        if ($excludeProposalId !== null && $excludeProposalId !== '') {
            $dummyProposal->id = $excludeProposalId;
        }

        $dummyLokasi = new BkkdProposalLokasi([
            'id_jalan_poros' => $idJalanPoros,
            'panjang_gis_m' => $panjangGisM,
            'geom' => $jsonString,
            'ringkasan_analisa' => ['sumber_rute' => $sumberRute],
        ]);

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->analisaLokasiSqlite($dummyLokasi, $dummyProposal);
        }

        $excludeSql = ($excludeProposalId !== null && $excludeProposalId !== '')
            ? ' AND p_prev.id::text != :excludeProposalId '
            : '';
        $overlapBindings = [
            'geoJson1' => $jsonString,
            'geoJson2' => $jsonString,
            'idDesa' => $idDesa,
            'currentTagging' => $currentTagging,
            'tahunAnggaran' => $tahunAnggaran,
        ];
        if ($excludeProposalId !== null && $excludeProposalId !== '') {
            $overlapBindings['excludeProposalId'] = $excludeProposalId;
        }

        // PostgreSQL PostGIS preview query lintas seluruh tahun (T-n, T, T+n) & penyandingan PRKPD <-> RKPD
        $overlappingProposals = DB::select("
            SELECT
                l_prev.id AS id_lokasi_lama,
                l_prev.nama_lokasi AS nama_lokasi_lama,
                l_prev.panjang_gis_m AS panjang_usulan_lama_m,
                p_prev.id AS id_proposal_lama,
                p_prev.nomor_registrasi,
                p_prev.tahun_anggaran,
                COALESCE(p_prev.tagging_usulan, 'PRKPD') AS tagging_usulan_referensi,
                p_prev.judul_kegiatan,
                p_prev.status_analisa_spasial AS status_proposal_lama,
                ROUND(
                    ST_Length(
                        ST_Intersection(
                            l_prev.geom,
                            ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson1), 4326)::geography, 5)::geometry
                        )::geography
                    )::numeric,
                    2
                ) AS panjang_irisan_usulan_m
            FROM bkkd_proposal_lokasi l_prev
            JOIN bkkd_proposal p_prev ON p_prev.id = l_prev.id_proposal
            WHERE p_prev.id_desa = :idDesa
              AND p_prev.deleted_at IS NULL
              {$excludeSql}
              AND ST_Intersects(
                  l_prev.geom,
                  ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson2), 4326)::geography, 5)::geometry
              )
            ORDER BY
                CASE WHEN COALESCE(p_prev.tagging_usulan, 'PRKPD') != :currentTagging THEN 0 ELSE 1 END,
                ABS(p_prev.tahun_anggaran - :tahunAnggaran) ASC,
                panjang_irisan_usulan_m DESC
        ", $overlapBindings);

        $overlappingSegmen = DB::select("
            SELECT
                seg.id AS id_segmen,
                seg.tipe_kode,
                seg.namobj,
                seg.kondisi,
                seg.status_kondisi,
                seg.tahun_pembangunan,
                seg.sumber_dana,
                seg.keterangan,
                COALESCE(seg.panjang, ROUND(ST_Length(seg.geom::geography)::numeric, 2)) AS panjang_segmen_m,
                ROUND(
                    ST_Length(
                        ST_Intersection(
                            seg.geom,
                            ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson1), 4326)::geography, 5)::geometry
                        )::geography
                    )::numeric,
                    2
                ) AS panjang_irisan_m,
                ST_AsGeoJSON(
                    ST_Intersection(
                        seg.geom,
                        ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson2), 4326)::geography, 5)::geometry
                    )
                ) AS irisan_geojson
            FROM infrastruktur_segmen seg
            WHERE seg.id_desa = :idDesa
              AND ST_Intersects(
                  seg.geom,
                  ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson3), 4326)::geography, 5)::geometry
              )
        ", [
            'geoJson1' => $jsonString,
            'geoJson2' => $jsonString,
            'geoJson3' => $jsonString,
            'idDesa' => $idDesa,
        ]);

        $overlappingJalanPoros = [];
        if (Schema::hasTable('jalan_porosdesa')) {
            $overlappingJalanPoros = DB::select("
                SELECT
                    jp.id AS id_jalan_poros,
                    jp.kode_ruas,
                    jp.nama_ruas,
                    COALESCE(jp.panjang, ROUND(ST_Length(jp.geom::geography)::numeric, 2)) AS panjang_ruas_m,
                    jp.lebar AS lebar_ruas_m,
                    jp.perkerasan,
                    jp.kondisi,
                    jp.status_eksisting,
                    ROUND(
                        ST_Length(
                            ST_Intersection(
                                jp.geom,
                                ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson1), 4326)::geography, 10)::geometry
                            )::geography
                        )::numeric,
                        2
                    ) AS panjang_irisan_m,
                    ST_AsGeoJSON(
                        ST_Intersection(
                            jp.geom,
                            ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson2), 4326)::geography, 10)::geometry
                        )
                    ) AS irisan_geojson
                FROM jalan_porosdesa jp
                WHERE jp.id::text = :idJalanPoros
                   OR (
                       (jp.id_desa = :idDesa OR jp.id_desa IS NULL)
                       AND ST_Intersects(
                           jp.geom,
                           ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson3), 4326)::geography, 10)::geometry
                       )
                   )
                   OR ST_Intersects(
                       jp.geom,
                       ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(:geoJson4), 4326)::geography, 10)::geometry
                   )
                ORDER BY panjang_irisan_m DESC
            ", [
                'geoJson1' => $jsonString,
                'geoJson2' => $jsonString,
                'geoJson3' => $jsonString,
                'geoJson4' => $jsonString,
                'idDesa' => $idDesa,
                'idJalanPoros' => $idJalanPoros ?: '',
            ]);
        }

        return $this->evaluateOverlapRecords(
            $overlappingProposals,
            $overlappingSegmen,
            $overlappingJalanPoros,
            $dummyLokasi,
            $dummyProposal,
            true
        );
    }

    /**
     * Hitung total panjang fisik yang sudah terealisasi pada tabel infrastruktur_segmen.
     */
    protected function getPanjangRealisasiFisik(string $idLokasiLama, int $idDesa, bool $isPgsql): float
    {
        if ($isPgsql) {
            $realisasiResult = DB::selectOne("
                SELECT
                    COALESCE(
                        ROUND(
                            SUM(
                                ST_Length(
                                    ST_Intersection(
                                        seg.geom,
                                        ST_Buffer(l_prev.geom::geography, 5)::geometry
                                    )::geography
                                )
                            )::numeric,
                            2
                        ),
                        0
                    ) AS panjang_terealisasi_m
                FROM infrastruktur_segmen seg
                JOIN bkkd_proposal_lokasi l_prev ON l_prev.id = :idLokasiLama
                WHERE seg.id_desa = :idDesa
                  AND ST_Intersects(
                      seg.geom,
                      ST_Buffer(l_prev.geom::geography, 5)::geometry
                  )
            ", [
                'idLokasiLama' => $idLokasiLama,
                'idDesa' => $idDesa,
            ]);

            return (float) ($realisasiResult->panjang_terealisasi_m ?? 0.0);
        }

        // SQLite fallback
        $segmens = InfrastrukturSegmen::where('id_desa', $idDesa)->get();
        if ($segmens->isEmpty()) {
            return 0.0;
        }

        $lPrev = BkkdProposalLokasi::find($idLokasiLama);
        if (!$lPrev) {
            return 0.0;
        }

        $prevCoords = $this->extractCoordinates($lPrev->geom);
        $totalRealisasi = 0.0;

        foreach ($segmens as $seg) {
            $segCoords = $this->extractCoordinates($seg->geom);
            $overlap = $this->calculateCoordinatesOverlap($prevCoords, $segCoords, (float) $lPrev->panjang_gis_m, (float) ($seg->panjang ?? 0));
            if ($overlap > 0) {
                $totalRealisasi += $overlap;
            }
        }

        return round($totalRealisasi, 2);
    }

    /**
     * Tentukan prioritas status keseluruhan untuk proposal induk.
     */
    protected function determineOverallStatus(array $childStatuses): string
    {
        if (in_array('duplikasi_tuntas', $childStatuses, true)) {
            return 'duplikasi_tuntas';
        }
        if (in_array('kewenangan_kabupaten', $childStatuses, true)) {
            return 'kewenangan_kabupaten';
        }
        if (in_array('konfirmasi_belum_tuntas', $childStatuses, true)) {
            return 'konfirmasi_belum_tuntas';
        }
        if (in_array('lanjutan_ruas', $childStatuses, true)) {
            return 'lanjutan_ruas';
        }
        if (in_array('aman', $childStatuses, true)) {
            return 'aman';
        }

        return 'belum_dianalisa';
    }

    /**
     * Ekstrak array koordinat dari GeoJSON format array, string, atau DB expression.
     */
    protected function extractCoordinates(mixed $geoJson): array
    {
        if (empty($geoJson)) {
            return [];
        }

        if (is_string($geoJson)) {
            $decoded = json_decode($geoJson, true);
            if (is_array($decoded)) {
                $geoJson = $decoded;
            }
        }

        if (is_array($geoJson)) {
            if (isset($geoJson['geometry']['coordinates'])) {
                return $this->flattenMultiLineCoordinates($geoJson['geometry']);
            }
            if (isset($geoJson['coordinates'])) {
                return $this->flattenMultiLineCoordinates($geoJson);
            }
            if (isset($geoJson[0]) && is_array($geoJson[0]) && isset($geoJson[0][0]) && is_numeric($geoJson[0][0])) {
                return $geoJson;
            }
        }

        return [];
    }

    /**
     * Jika geometri bertipe MultiLineString (misal dari jalan_porosdesa), ratakan menjadi array titik [lng, lat].
     */
    protected function flattenMultiLineCoordinates(array $geom): array
    {
        $type = strtolower((string) ($geom['type'] ?? 'linestring'));
        $coords = $geom['coordinates'] ?? [];
        if ($type === 'multilinestring' && !empty($coords) && is_array($coords[0])) {
            $merged = [];
            foreach ($coords as $line) {
                if (is_array($line)) {
                    foreach ($line as $pt) {
                        $merged[] = $pt;
                    }
                }
            }
            return $merged;
        }

        return is_array($coords) ? $coords : [];
    }

    /**
     * Ekstrak dan gabungkan koordinat jalan (termasuk MultiLineString dengan beberapa sub-ruas)
     * menjadi 1 rantai koordinat berurutan (setara ST_LineMerge).
     */
    protected function extractMergedRoadCoordinates(mixed $geoJson): array
    {
        if (empty($geoJson)) {
            return [];
        }

        if (is_string($geoJson)) {
            $decoded = json_decode($geoJson, true);
            if (is_array($decoded)) {
                $geoJson = $decoded;
            }
        }

        if (!is_array($geoJson)) {
            return [];
        }

        if (isset($geoJson['geometry']) && is_array($geoJson['geometry'])) {
            $geoJson = $geoJson['geometry'];
        }

        $type = strtolower((string) ($geoJson['type'] ?? 'linestring'));
        $coords = $geoJson['coordinates'] ?? [];
        if (empty($coords) || !is_array($coords)) {
            return [];
        }

        if ($type !== 'multilinestring') {
            return $this->deduplicateCoordinates($coords);
        }

        $parts = [];
        foreach ($coords as $line) {
            if (is_array($line) && count($line) >= 2 && is_array($line[0])) {
                $parts[] = array_values($line);
            }
        }

        if (empty($parts)) {
            return [];
        }

        if (count($parts) === 1) {
            return $this->deduplicateCoordinates($parts[0]);
        }

        // Gabungkan sub-line yang saling bersambung (ST_LineMerge di PHP)
        $chain = array_shift($parts);
        while (!empty($parts)) {
            $bestIdx = 0;
            $bestMode = 'append';
            $bestDist = PHP_FLOAT_MAX;

            $chainStart = $chain[0];
            $chainEnd = $chain[count($chain) - 1];

            foreach ($parts as $idx => $part) {
                $partStart = $part[0];
                $partEnd = $part[count($part) - 1];

                $dEndStart = $this->pointDistance($chainEnd, $partStart);
                if ($dEndStart < $bestDist) {
                    $bestDist = $dEndStart;
                    $bestIdx = $idx;
                    $bestMode = 'append';
                }

                $dEndEnd = $this->pointDistance($chainEnd, $partEnd);
                if ($dEndEnd < $bestDist) {
                    $bestDist = $dEndEnd;
                    $bestIdx = $idx;
                    $bestMode = 'append_rev';
                }

                $dStartEnd = $this->pointDistance($chainStart, $partEnd);
                if ($dStartEnd < $bestDist) {
                    $bestDist = $dStartEnd;
                    $bestIdx = $idx;
                    $bestMode = 'prepend';
                }

                $dStartStart = $this->pointDistance($chainStart, $partStart);
                if ($dStartStart < $bestDist) {
                    $bestDist = $dStartStart;
                    $bestIdx = $idx;
                    $bestMode = 'prepend_rev';
                }
            }

            $chosen = $parts[$bestIdx];
            array_splice($parts, $bestIdx, 1);

            if ($bestMode === 'append') {
                $chain = array_merge($chain, $chosen);
            } elseif ($bestMode === 'append_rev') {
                $chain = array_merge($chain, array_reverse($chosen));
            } elseif ($bestMode === 'prepend') {
                $chain = array_merge($chosen, $chain);
            } else {
                $chain = array_merge(array_reverse($chosen), $chain);
            }
        }

        return $this->deduplicateCoordinates($chain);
    }

    /**
     * Proyeksikan sebuah titik [lng, lat] ke segmen terdekat pada polyline jalan (setara ST_LineLocatePoint + ST_ClosestPoint).
     *
     * @return array{
     *     point: array{0: float, 1: float},
     *     distance_m: float,
     *     segment_index: int,
     *     t: float,
     *     along_m: float,
     *     total_length_m: float
     * }
     */
    protected function projectPointOntoPolyline(array $pt, array $polyline): array
    {
        $px = (float) $pt[0];
        $py = (float) $pt[1];

        $minDistM = PHP_FLOAT_MAX;
        $bestProjPt = [(float) $polyline[0][0], (float) $polyline[0][1]];
        $bestSegIdx = 0;
        $bestT = 0.0;
        $bestAlongM = 0.0;
        $cumDistM = 0.0;

        $count = count($polyline);
        for ($i = 0; $i < $count - 1; $i++) {
            $x1 = (float) $polyline[$i][0];
            $y1 = (float) $polyline[$i][1];
            $x2 = (float) $polyline[$i + 1][0];
            $y2 = (float) $polyline[$i + 1][1];

            $segLenM = $this->pointDistance([$x1, $y1], [$x2, $y2]);
            $cosLat = cos(deg2rad(($y1 + $y2) / 2.0));

            $dx = ($x2 - $x1) * $cosLat;
            $dy = $y2 - $y1;
            $lenSq = ($dx * $dx) + ($dy * $dy);

            if ($lenSq > 1e-16) {
                $t = ((($px - $x1) * $cosLat * $dx) + (($py - $y1) * $dy)) / $lenSq;
                $t = max(0.0, min(1.0, $t));
            } else {
                $t = 0.0;
            }

            $projPt = [
                round($x1 + $t * ($x2 - $x1), 7),
                round($y1 + $t * ($y2 - $y1), 7),
            ];

            $distM = $this->pointDistance([$px, $py], $projPt);
            if ($distM < $minDistM) {
                $minDistM = $distM;
                $bestProjPt = $projPt;
                $bestSegIdx = $i;
                $bestT = $t;
                $bestAlongM = $cumDistM + ($t * $segLenM);
            }

            $cumDistM += $segLenM;
        }

        return [
            'point' => $bestProjPt,
            'distance_m' => round($minDistM, 3),
            'segment_index' => $bestSegIdx,
            't' => $bestT,
            'along_m' => round($bestAlongM, 3),
            'total_length_m' => round($cumDistM, 3),
        ];
    }

    /**
     * Potong (extract) sub-LineString pada polyline di antara 2 titik proyeksi
     * dengan mempertahankan seluruh titik sudut (vertex) jalan di antaranya (setara ST_LineSubstring).
     */
    protected function slicePolylineBetweenProjections(array $polyline, array $projStart, array $projEnd): array
    {
        $reverse = false;
        $p1 = $projStart;
        $p2 = $projEnd;

        if ($p1['along_m'] > $p2['along_m']) {
            $p1 = $projEnd;
            $p2 = $projStart;
            $reverse = true;
        }

        $sliced = [$p1['point']];

        for ($idx = $p1['segment_index'] + 1; $idx <= $p2['segment_index']; $idx++) {
            if (isset($polyline[$idx])) {
                $sliced[] = [
                    round((float) $polyline[$idx][0], 7),
                    round((float) $polyline[$idx][1], 7),
                ];
            }
        }

        $sliced[] = $p2['point'];

        if ($reverse) {
            $sliced = array_reverse($sliced);
        }

        $cleaned = $this->deduplicateCoordinates($sliced);
        if (count($cleaned) === 1 && count($sliced) >= 2) {
            return [$sliced[0], $sliced[count($sliced) - 1]];
        }

        return $cleaned;
    }

    /**
     * Cari titik persimpangan (intersection / junction) terdekat antara 2 polyline Jalan Poros Desa.
     *
     * @return array{point1: array{0: float, 1: float}, point2: array{0: float, 1: float}, distance_m: float}
     */
    protected function findClosestJunctionBetweenPolylines(array $poly1, array $poly2): array
    {
        // 1. Cek apakah ada persimpangan segmen-ke-segmen secara langsung (jarak = 0m)
        $c1 = count($poly1);
        $c2 = count($poly2);
        for ($i = 0; $i < $c1 - 1; $i++) {
            for ($j = 0; $j < $c2 - 1; $j++) {
                $inter = $this->findSegmentIntersection($poly1[$i], $poly1[$i + 1], $poly2[$j], $poly2[$j + 1]);
                if ($inter !== null) {
                    return [
                        'point1' => $inter,
                        'point2' => $inter,
                        'distance_m' => 0.0,
                    ];
                }
            }
        }

        $bestDist = PHP_FLOAT_MAX;
        $bestPt1 = [(float) $poly1[0][0], (float) $poly1[0][1]];
        $bestPt2 = [(float) $poly2[0][0], (float) $poly2[0][1]];

        // 2. Proyeksikan setiap vertex poly1 ke poly2
        foreach ($poly1 as $v1) {
            $proj2 = $this->projectPointOntoPolyline($v1, $poly2);
            if ($proj2['distance_m'] < $bestDist) {
                $bestDist = $proj2['distance_m'];
                $bestPt1 = [round((float) $v1[0], 7), round((float) $v1[1], 7)];
                $bestPt2 = $proj2['point'];
            }
        }

        // 3. Proyeksikan setiap vertex poly2 ke poly1
        foreach ($poly2 as $v2) {
            $proj1 = $this->projectPointOntoPolyline($v2, $poly1);
            if ($proj1['distance_m'] < $bestDist) {
                $bestDist = $proj1['distance_m'];
                $bestPt1 = $proj1['point'];
                $bestPt2 = [round((float) $v2[0], 7), round((float) $v2[1], 7)];
            }
        }

        return [
            'point1' => $bestPt1,
            'point2' => $bestPt2,
            'distance_m' => round($bestDist, 3),
        ];
    }

    /**
     * Hitung titik potong (intersection) antara segmen A1-A2 dan B1-B2 jika berpotongan.
     */
    protected function findSegmentIntersection(array $a1, array $a2, array $b1, array $b2): ?array
    {
        $x1 = (float) $a1[0];
        $y1 = (float) $a1[1];
        $x2 = (float) $a2[0];
        $y2 = (float) $a2[1];
        $x3 = (float) $b1[0];
        $y3 = (float) $b1[1];
        $x4 = (float) $b2[0];
        $y4 = (float) $b2[1];

        $denom = (($x1 - $x2) * ($y3 - $y4)) - (($y1 - $y2) * ($x3 - $x4));
        if (abs($denom) < 1e-14) {
            return null;
        }

        $t = ((($x1 - $x3) * ($y3 - $y4)) - (($y1 - $y3) * ($x3 - $x4))) / $denom;
        $u = -((($x1 - $x2) * ($y1 - $y3)) - (($y1 - $y2) * ($x1 - $x3))) / $denom;

        if ($t >= -1e-7 && $t <= 1.0 + 1e-7 && $u >= -1e-7 && $u <= 1.0 + 1e-7) {
            return [
                round($x1 + max(0.0, min(1.0, $t)) * ($x2 - $x1), 7),
                round($y1 + max(0.0, min(1.0, $t)) * ($y2 - $y1), 7),
            ];
        }

        return null;
    }

    /**
     * Bersihkan titik duplikat berurutan yang berhimpitan (< 0.05 meter).
     */
    protected function deduplicateCoordinates(array $coords): array
    {
        $cleaned = [];
        foreach ($coords as $pt) {
            if (!is_array($pt) || count($pt) < 2 || !is_numeric($pt[0]) || !is_numeric($pt[1])) {
                continue;
            }
            $formatted = [round((float) $pt[0], 7), round((float) $pt[1], 7)];
            if (empty($cleaned)) {
                $cleaned[] = $formatted;
                continue;
            }
            $prev = $cleaned[count($cleaned) - 1];
            if ($this->pointDistance($prev, $formatted) >= 0.05) {
                $cleaned[] = $formatted;
            }
        }

        return $cleaned;
    }

    /**
     * Normalisasi GeoJSON ke format standar { type: 'LineString', coordinates: [...] }.
     */
    protected function normalizeGeoJson(array|string $geoJson): array
    {
        if (is_string($geoJson)) {
            $decoded = json_decode($geoJson, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Format GeoJSON tidak valid.');
            }
            $geoJson = $decoded;
        }

        if (isset($geoJson['geometry']) && is_array($geoJson['geometry'])) {
            $geoJson = $geoJson['geometry'];
        }

        $type = strtolower((string) ($geoJson['type'] ?? ''));
        if ($type === 'multilinestring') {
            $coords = $this->flattenMultiLineCoordinates($geoJson);
            $geoJson = [
                'type' => 'LineString',
                'coordinates' => $coords,
            ];
            $type = 'linestring';
        }

        if ($type !== 'linestring') {
            throw new InvalidArgumentException('Geometri lokasi BKKD harus bertipe LineString.');
        }

        if (!isset($geoJson['coordinates']) || !is_array($geoJson['coordinates']) || count($geoJson['coordinates']) < 2) {
            throw new InvalidArgumentException('Koordinat LineString harus memiliki minimal 2 titik [longitude, latitude].');
        }

        return [
            'type' => 'LineString',
            'coordinates' => $geoJson['coordinates'],
        ];
    }

    /**
     * Hitung estimasi overlap antar 2 set koordinat (SQLite fallback helper).
     */
    protected function calculateCoordinatesOverlap(array $coords1, array $coords2, float $len1, float $len2): float
    {
        if (empty($coords1) || empty($coords2)) {
            return 0.0;
        }

        $closePointCount = 0;
        $totalPoints = count($coords1);

        foreach ($coords1 as $p1) {
            foreach ($coords2 as $p2) {
                $d = $this->pointDistance($p1, $p2);
                if ($d <= 20.0) { // Toleransi 20 meter untuk SQLite fallback
                    $closePointCount++;
                    break;
                }
            }
        }

        if ($closePointCount === 0) {
            return 0.0;
        }

        $fraction = $closePointCount / max(1, $totalPoints);
        $overlapLength = $len1 * $fraction;

        // Jika hanya 1 titik ujung bersentuhan
        if ($closePointCount === 1 && $totalPoints > 2) {
            return min(8.0, $overlapLength);
        }

        return max(5.0, round($overlapLength, 2));
    }

    /**
     * Jarak antara 2 titik koordinat dalam meter (Haversine).
     */
    protected function pointDistance(array $p1, array $p2): float
    {
        $lon1 = deg2rad((float) $p1[0]);
        $lat1 = deg2rad((float) $p1[1]);
        $lon2 = deg2rad((float) $p2[0]);
        $lat2 = deg2rad((float) $p2[1]);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * (sin($dLon / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

        return 6371000.0 * $c;
    }

    /**
     * Ambil GeoJSON LineString lokasi secara aman baik di driver PostgreSQL maupun SQLite.
     */
    protected function resolveLokasiGeoJson(BkkdProposalLokasi $lokasi): ?array
    {
        if (!empty($lokasi->geojson)) {
            $raw = is_string($lokasi->geojson) ? json_decode($lokasi->geojson, true) : $lokasi->geojson;
            if (is_array($raw) && !empty($raw['coordinates'])) {
                return $raw;
            }
        }

        if (DB::connection()->getDriverName() === 'pgsql' && !empty($lokasi->id)) {
            $row = DB::selectOne(
                'SELECT ST_AsGeoJSON(geom) AS geojson FROM bkkd_proposal_lokasi WHERE id = :id AND geom IS NOT NULL',
                ['id' => $lokasi->id]
            );
            if ($row && !empty($row->geojson)) {
                $decoded = json_decode($row->geojson, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (!empty($lokasi->geom)) {
            $raw = is_string($lokasi->geom) ? json_decode($lokasi->geom, true) : $lokasi->geom;
            if (is_array($raw) && !empty($raw['coordinates'])) {
                return $raw;
            }
        }

        return null;
    }

    /**
     * Jalankan 4 gerbang validasi spasial sebelum lokasi usulan dapat dipromosikan ke Master Jaringan Jalan.
     *
     * @return array{
     *   lolos_validasi: bool,
     *   rekomendasi_aksi: string,
     *   panjang_valid_m: float,
     *   jumlah_vertex: int,
     *   persen_overlap_master: float,
     *   indikasi_jalan_kabupaten: bool,
     *   nearest_ruas: array|null,
     *   checks: array<int, array{kode: string, label: string, lolos: bool, detail: string}>
     * }
     */
    public function validasiKandidatJaringanJalan(BkkdProposalLokasi $lokasi, ?BkkdProposal $proposal = null): array
    {
        $proposal = $proposal ?? $lokasi->proposal ?? BkkdProposal::find($lokasi->id_proposal);
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $geoJson = $this->resolveLokasiGeoJson($lokasi);
        $coords = $geoJson ? $this->extractCoordinates($geoJson) : [];
        $vertexCount = count($coords);
        $panjangValid = (float) ($lokasi->panjang_gis_m ?: ($geoJson ? $this->calculateGeoJsonLength($geoJson) : 0.0));

        // 1. Kelayakan Geometri Dasar
        $geomValid = $vertexCount >= 2 && $panjangValid >= 20.0;
        $geomDetail = $geomValid
            ? sprintf('Geometri LineString valid dengan panjang %s m (%d titik).', round($panjangValid, 1), $vertexCount)
            : 'Garis minimal sepanjang 20 meter dengan minimal 2 titik koordinat valid.';

        if ($geomValid && $isPgsql && !empty($lokasi->id)) {
            $pgCheck = DB::selectOne(
                'SELECT ST_IsValid(geom) AS is_valid, ST_IsSimple(geom) AS is_simple FROM bkkd_proposal_lokasi WHERE id = :id AND geom IS NOT NULL',
                ['id' => $lokasi->id]
            );
            if ($pgCheck && (!$pgCheck->is_valid || !$pgCheck->is_simple)) {
                $geomValid = false;
                $geomDetail = 'Geometri memiliki self-intersection atau simpul tidak sederhana di PostGIS.';
            }
        }

        // 2. Kualitas Bentuk Koridor (Cegah garis lurus mentah 2 titik menjadi jalan master)
        $sumberRute = strtolower((string) ($lokasi->ringkasan_analisa['sumber_rute'] ?? ''));
        $shapeValid = !($sumberRute === 'garis_lurus' && $vertexCount <= 2);
        $shapeDetail = $shapeValid
            ? sprintf('Bentuk koridor memadai (%d vertex, sumber: %s).', $vertexCount, $sumberRute ?: 'terukur')
            : 'Garis lurus 2 titik (awal-akhir) wajib di-reshape mengikuti bentuk jalan sebenarnya sebelum masuk master ruas.';

        // 3. Verifikasi Batas Administrasi & Wilayah Bojonegoro
        $insideBbox = $vertexCount >= 2;
        foreach ($coords as $pt) {
            $lng = (float) ($pt[0] ?? 0);
            $lat = (float) ($pt[1] ?? 0);
            if ($lng < 111.30 || $lng > 112.40 || $lat < -7.65 || $lat > -6.85) {
                $insideBbox = false;
                break;
            }
        }

        $desaValid = $insideBbox;
        $desaDetail = $insideBbox
            ? 'Koordinat berada di cakupan wilayah Kabupaten Bojonegoro.'
            : 'Koordinat terdeteksi berada di luar batas geografis Kabupaten Bojonegoro.';

        if ($desaValid && $isPgsql && $proposal && !empty($lokasi->id) && Schema::hasTable('bataswilayah_desa')) {
            $desaRatioRow = DB::selectOne("
                SELECT
                    COUNT(d.id) AS has_poly,
                    COALESCE(
                        ROUND(
                            (ST_Length(ST_Intersection(l.geom, d.geom)::geography) / NULLIF(ST_Length(l.geom::geography), 0))::numeric * 100,
                            1
                        ),
                        0
                    ) AS pct_inside
                FROM bkkd_proposal_lokasi l
                LEFT JOIN bataswilayah_desa d ON d.id = :idDesa AND d.geom IS NOT NULL
                WHERE l.id = :lokasiId AND l.geom IS NOT NULL
                GROUP BY l.geom, d.geom
            ", [
                'idDesa' => (int) $proposal->id_desa,
                'lokasiId' => $lokasi->id,
            ]);

            if ($desaRatioRow && (int) ($desaRatioRow->has_poly ?? 0) > 0) {
                $pctInside = (float) ($desaRatioRow->pct_inside ?? 0);
                if ($pctInside < 70.0) {
                    $desaValid = false;
                    $desaDetail = sprintf('Hanya %s%% segmen yang masuk poligon batas desa terpilih (minimal 70%%).', $pctInside);
                } else {
                    $desaDetail = sprintf('%s%% koridor berada di dalam batas administrasi desa.', $pctInside);
                }
            }
        }

        // 4. Cek Duplikasi & Overlap terhadap Master Jalan Eksisting (Poros, Jaringan Ruas, Kabupaten)
        $maxOverlapPct = 0.0;
        $nearestRuas = null;
        $indikasiKabupaten = false;

        $referensiPoros = $lokasi->ringkasan_analisa['referensi_jalan_poros'] ?? [];
        $isSelfPromoted = $lokasi->status_promosi_master === BkkdProposalLokasi::PROMOSI_DISETUJUI;
        foreach ($referensiPoros as $ref) {
            $refId = $ref['id_jaringan_ruas'] ?? $ref['id_jalan_poros'] ?? null;
            if ($isSelfPromoted && !empty($lokasi->id_jaringan_ruas) && $refId === $lokasi->id_jaringan_ruas) {
                continue;
            }
            if ($isSelfPromoted && !empty($lokasi->id_jalan_poros) && ($ref['id_jalan_poros'] ?? null) === $lokasi->id_jalan_poros) {
                continue;
            }
            $pct = (float) ($ref['persen_masuk_koridor'] ?? 0);
            if ($pct > $maxOverlapPct) {
                $maxOverlapPct = $pct;
                $nearestRuas = [
                    'id' => $ref['id_jaringan_ruas'] ?? $ref['id_jalan_poros'] ?? null,
                    'nama_ruas' => $ref['nama_ruas'] ?? 'Ruas Eksisting',
                    'hierarki_jalan' => $ref['hierarki_jalan'] ?? 'poros_desa',
                    'persen_overlap' => $pct,
                ];
            }
        }

        if ($isPgsql && !empty($lokasi->id) && Schema::hasTable('jalan_kabupaten_luarkota')) {
            try {
                $kabCols = Schema::getColumnListing('jalan_kabupaten_luarkota');
                $kabNameExpr = in_array('Nama_Ruas', $kabCols, true)
                    ? 'COALESCE(j."Nama_Ruas", \'Jalan Kabupaten\')'
                    : (in_array('nama_ruas', $kabCols, true) ? 'COALESCE(j.nama_ruas, \'Jalan Kabupaten\')' : '\'Jalan Kabupaten\'');

                $kabRow = DB::selectOne("
                    SELECT
                        {$kabNameExpr} AS nama_ruas,
                        ROUND(
                            (ST_Length(ST_Intersection(l.geom, ST_Buffer(ST_Transform(j.geom, 4326)::geography, 12)::geometry)::geography) / NULLIF(ST_Length(l.geom::geography), 0))::numeric * 100,
                            1
                        ) AS pct_overlap
                    FROM bkkd_proposal_lokasi l
                    JOIN jalan_kabupaten_luarkota j
                        ON j.geom IS NOT NULL
                       AND ST_Intersects(l.geom, ST_Buffer(ST_Transform(j.geom, 4326)::geography, 12)::geometry)
                    WHERE l.id = :lokasiId AND l.geom IS NOT NULL
                    ORDER BY pct_overlap DESC
                    LIMIT 1
                ", ['lokasiId' => $lokasi->id]);

                if ($kabRow && (float) ($kabRow->pct_overlap ?? 0) >= 50.0) {
                    $indikasiKabupaten = true;
                    $maxOverlapPct = max($maxOverlapPct, (float) $kabRow->pct_overlap);
                    $nearestRuas = [
                        'id' => null,
                        'nama_ruas' => $kabRow->nama_ruas,
                        'hierarki_jalan' => 'kabupaten',
                        'persen_overlap' => (float) $kabRow->pct_overlap,
                    ];
                }
            } catch (\Throwable $e) {
                // Kolom tabel eksternal bisa bervariasi antar deployment
            }
        }

        $duplikasiValid = !$indikasiKabupaten && $maxOverlapPct < 80.0;
        if ($indikasiKabupaten) {
            $dupDetail = sprintf(
                'Koridor berimpit %s%% dengan Jalan Kabupaten ("%s"). Tidak dapat dipromosikan sebagai jalan desa tanpa kajian kewenangan.',
                $maxOverlapPct,
                $nearestRuas['nama_ruas'] ?? 'Jalan Kabupaten'
            );
            $rekomendasiAksi = 'periksa_kewenangan_kabupaten';
        } elseif ($maxOverlapPct >= 80.0) {
            $dupDetail = sprintf(
                'Sudah tercover %s%% oleh ruas master "%s". Tautkan lokasi ke ruas tersebut alih-alih membuat ruas baru.',
                $maxOverlapPct,
                $nearestRuas['nama_ruas'] ?? 'Master'
            );
            $rekomendasiAksi = 'tautkan_eksisting';
        } elseif ($maxOverlapPct >= 15.0) {
            $dupDetail = sprintf(
                'Beririsan sebagian (%s%%) dengan "%s". Gunakan mode potong bagian unik agar tidak terjadi ruas ganda.',
                $maxOverlapPct,
                $nearestRuas['nama_ruas'] ?? 'Master'
            );
            $rekomendasiAksi = 'ekstensi_ruas';
        } else {
            $dupDetail = 'Koridor belum terdaftar pada master jalan poros & desa maupun jaringan jalan kabupaten.';
            $rekomendasiAksi = 'ruas_baru';
        }

        $lolosSemua = $geomValid && $shapeValid && $desaValid && $duplikasiValid;
        if (!$lolosSemua && !$indikasiKabupaten && $maxOverlapPct < 80.0) {
            $rekomendasiAksi = 'perlu_perbaikan';
        }

        return [
            'lolos_validasi' => $lolosSemua,
            'rekomendasi_aksi' => $rekomendasiAksi,
            'panjang_valid_m' => round($panjangValid, 2),
            'jumlah_vertex' => $vertexCount,
            'persen_overlap_master' => round($maxOverlapPct, 1),
            'indikasi_jalan_kabupaten' => $indikasiKabupaten,
            'nearest_ruas' => $nearestRuas,
            'checks' => [
                [
                    'kode' => 'geometri_valid',
                    'label' => 'Kelayakan Geometri',
                    'lolos' => $geomValid,
                    'detail' => $geomDetail,
                ],
                [
                    'kode' => 'kualitas_bentuk_koridor',
                    'label' => 'Kualitas Bentuk Koridor',
                    'lolos' => $shapeValid,
                    'detail' => $shapeDetail,
                ],
                [
                    'kode' => 'batas_administrasi_desa',
                    'label' => 'Kesesuaian Batas Wilayah',
                    'lolos' => $desaValid,
                    'detail' => $desaDetail,
                ],
                [
                    'kode' => 'cek_duplikasi_master_jalan',
                    'label' => 'Cek Duplikasi Master Jalan',
                    'lolos' => $duplikasiValid,
                    'detail' => $dupDetail,
                ],
            ],
        ];
    }

    /**
     * Eksekusi validasi akhir dan promosikan geometri lokasi proposal langsung ke Master Jalan Poros & Desa (jalan_porosdesa).
     *
     * @return array{lokasi: BkkdProposalLokasi, jaringan_ruas: JalanPorosDesa|null}
     */
    public function promosikanLokasiKeMasterJaringanJalan(
        BkkdProposalLokasi $lokasi,
        User $validator,
        array $payload
    ): array {
        $proposal = $lokasi->proposal ?? BkkdProposal::findOrFail($lokasi->id_proposal);
        $proposal->loadMissing(['desa:id,nama_desa', 'kecamatan:id,nama_kecamatan']);
        $keputusan = (string) ($payload['keputusan'] ?? 'setujui_baru');

        if ($keputusan === 'tolak') {
            $lokasi->status_promosi_master = BkkdProposalLokasi::PROMOSI_DITOLAK;
            $lokasi->catatan_promosi_master = trim((string) ($payload['catatan'] ?? 'Ditolak oleh verifikator spasial.'));
            $lokasi->divalidasi_promosi_oleh = $validator->uuid;
            $lokasi->divalidasi_promosi_pada = now();
            $lokasi->save();

            return [
                'lokasi' => $lokasi->fresh(),
                'jaringan_ruas' => null,
            ];
        }

        $validation = $this->validasiKandidatJaringanJalan($lokasi, $proposal);
        if (!$validation['lolos_validasi']) {
            $firstFail = collect($validation['checks'])->firstWhere('lolos', false);
            throw new InvalidArgumentException(
                $firstFail['detail'] ?? 'Lokasi gagal memenuhi syarat validasi spasial Master Jalan Poros & Desa.'
            );
        }

        $targetGeoJson = $this->resolveLokasiGeoJson($lokasi);
        if (!$targetGeoJson) {
            throw new InvalidArgumentException('Geometri LineString lokasi tidak ditemukan.');
        }

        // Pada PostGIS, jika mode potong unik dipilih dan terdapat irisan parsial, potong bagian yang sudah ada di master
        if ($keputusan === 'ekstensi_potong_unik' && DB::connection()->getDriverName() === 'pgsql') {
            $diffRow = DB::selectOne("
                WITH master_union AS (
                    SELECT ST_Union(geom) AS ugeom
                    FROM jalan_porosdesa
                    WHERE id_desa = :idDesa AND geom IS NOT NULL
                ),
                diff_geom AS (
                    SELECT
                        CASE
                            WHEN mu.ugeom IS NULL THEN l.geom
                            ELSE ST_CollectionExtract(ST_Difference(l.geom, ST_Buffer(mu.ugeom::geography, 10)::geometry), 2)
                        END AS cleaned_geom
                    FROM bkkd_proposal_lokasi l
                    CROSS JOIN master_union mu
                    WHERE l.id = :lokasiId
                )
                SELECT
                    ST_AsGeoJSON(ST_LineMerge(cleaned_geom)) AS diff_geojson,
                    ROUND(ST_Length(cleaned_geom::geography)::numeric, 2) AS diff_length_m
                FROM diff_geom
                WHERE cleaned_geom IS NOT NULL AND NOT ST_IsEmpty(cleaned_geom)
            ", [
                'idDesa' => (int) $proposal->id_desa,
                'lokasiId' => $lokasi->id,
            ]);

            if ($diffRow && !empty($diffRow->diff_geojson)) {
                $diffLength = (float) ($diffRow->diff_length_m ?? 0);
                if ($diffLength < 20.0) {
                    throw new InvalidArgumentException('Bagian segmen baru setelah pemotongan irisan kurang dari 20 meter; gunakan ruas eksisting.');
                }
                $decodedDiff = json_decode($diffRow->diff_geojson, true);
                if (is_array($decodedDiff)) {
                    $targetGeoJson = $this->normalizeGeoJson($decodedDiff);
                }
            }
        }

        $panjangMasterM = $this->calculateGeoJsonLength($targetGeoJson);
        $hierarki = (string) ($payload['hierarki_jalan'] ?? $lokasi->hierarki_jalan_usulan ?? JalanPorosDesa::HIERARKI_LINGKUNGAN);
        if (!in_array($hierarki, [
            'kabupaten',
            JalanPorosDesa::HIERARKI_KABUPATEN_USULAN,
            JalanPorosDesa::HIERARKI_POROS_DESA,
            JalanPorosDesa::HIERARKI_LINGKUNGAN,
            JalanPorosDesa::HIERARKI_USAHA_TANI,
        ], true)) {
            $hierarki = JalanPorosDesa::HIERARKI_LINGKUNGAN;
        }

        $namaRuas = trim((string) ($payload['nama_ruas'] ?? $lokasi->nama_ruas_usulan ?? $lokasi->nama_lokasi));
        $rawKode = trim((string) ($payload['kode_ruas'] ?? ''));
        if ($rawKode !== '' && ctype_digit($rawKode)) {
            $kodeRuas = (int) $rawKode;
        } else {
            $kodeRuas = ((int) DB::table('jalan_porosdesa')->max('kode_ruas')) + 1;
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $geomValue = $isPgsql
            ? SpatialHelper::geomFromGeoJsonRaw(json_encode($targetGeoJson, JSON_UNESCAPED_SLASHES))
            : json_encode($targetGeoJson, JSON_UNESCAPED_SLASHES);

        $ruas = JalanPorosDesa::create([
            'id' => (string) Str::uuid(),
            'kode_ruas' => $kodeRuas,
            'nama_ruas' => $namaRuas,
            'desa' => $proposal->desa?->nama_desa,
            'kecamatan' => $proposal->kecamatan?->nama_kecamatan,
            'panjang' => round($panjangMasterM, 2),
            'lebar' => $payload['lebar_m'] ?? $lokasi->lebar_m,
            'perkerasan' => $payload['perkerasan'] ?? null,
            'kondisi' => $payload['kondisi'] ?? null,
            'status_awal' => 'eksisting',
            'status_eksisting' => 'terverifikasi',
            'sumber_data' => JalanPorosDesa::SUMBER_PROMOSI_PROPOSAL,
            'hierarki_jalan' => $hierarki,
            'sumber_proposal_id' => $proposal->id,
            'sumber_lokasi_id' => $lokasi->id,
            'validated_by' => $validator->uuid,
            'validated_at' => now(),
            'id_desa' => (int) $proposal->id_desa,
            'id_kecamatan' => (int) $proposal->id_kecamatan,
            'geom' => $geomValue,
        ]);

        $lokasi->id_jalan_poros = $ruas->id;
        $lokasi->id_jaringan_ruas = $ruas->id;
        $lokasi->nama_ruas_usulan = $namaRuas;
        $lokasi->hierarki_jalan_usulan = $hierarki;
        $lokasi->status_promosi_master = BkkdProposalLokasi::PROMOSI_DISETUJUI;
        $lokasi->validasi_promosi_spasial = $validation;
        $lokasi->catatan_promosi_master = $payload['catatan'] ?? null;
        $lokasi->divalidasi_promosi_oleh = $validator->uuid;
        $lokasi->divalidasi_promosi_pada = now();
        $lokasi->save();

        // Segarkan analisa spasial proposal agar id_jalan_poros dan id_jaringan_ruas terhubung di ringkasan
        $this->analisaProposal($proposal);

        return [
            'lokasi' => $lokasi->fresh(['jalanPoros', 'jaringanRuas']),
            'jaringan_ruas' => JalanPorosDesa::withGeoJson()
                ->with(['kecamatanModel:id,nama_kecamatan', 'desaModel:id,nama_desa'])
                ->findOrFail($ruas->id),
        ];
    }
}

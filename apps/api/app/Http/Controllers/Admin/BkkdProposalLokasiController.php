<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBkkdProposalLokasiRequest;
use App\Http\Requests\Admin\UpdateBkkdProposalLokasiRequest;
use App\Http\Resources\BkkdProposalLokasiResource;
use App\Http\Resources\BkkdProposalResource;
use App\Models\BkkdProposal;
use App\Models\BkkdProposalLokasi;
use App\Models\InfrastrukturSegmen;
use App\Models\JalanPorosDesa;
use App\Services\BkkdRiwayatService;
use App\Services\BkkdSpatialAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class BkkdProposalLokasiController extends Controller
{
    public function __construct(
        protected BkkdSpatialAnalysisService $spatialService,
        protected BkkdRiwayatService $riwayatService
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::with(['desa', 'kecamatan'])->findOrFail($id);
        Gate::authorize('view', $proposal);

        $lokasis = BkkdProposalLokasi::where('id_proposal', $proposal->id)
            ->withGeoJson()
            ->with(['jalanPoros:id,kode_ruas,nama_ruas', 'jaringanRuas:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data'])
            ->orderBy('urutan')
            ->get();

        $features = $lokasis->map(function ($lokasi) {
            $geom = $lokasi->geojson;
            if (is_string($geom)) {
                $geom = json_decode($geom, true);
            }

            return [
                'type' => 'Feature',
                'id' => $lokasi->id,
                'geometry' => $geom,
                'properties' => [
                    'id' => $lokasi->id,
                    'urutan' => (int) $lokasi->urutan,
                    'nama_lokasi' => $lokasi->nama_lokasi,
                    'panjang_gis_m' => (float) $lokasi->panjang_gis_m,
                    'panjang_rencana_m' => $lokasi->panjang_rencana_m !== null ? (float) $lokasi->panjang_rencana_m : null,
                    'lebar_m' => $lokasi->lebar_m !== null ? (float) $lokasi->lebar_m : null,
                    'status_analisa_spasial' => $lokasi->status_analisa_spasial,
                    'ringkasan_analisa' => $lokasi->ringkasan_analisa,
                ],
            ];
        });

        $idDesa = (int) $proposal->id_desa;
        $tahunAnggaran = (int) $proposal->tahun_anggaran;
        $isHybrid = $request->input('mode') === 'hybrid';

        if ($isHybrid) {
            $jalanPorosOptions = [];
            if (Schema::hasTable('jalan_porosdesa')) {
                $jalanPorosOptions = JalanPorosDesa::where('id_desa', $idDesa)
                    ->select([
                        'id',
                        'kode_ruas',
                        'nama_ruas',
                        'hierarki_jalan',
                        'sumber_data',
                        'panjang',
                        'lebar',
                        'kondisi',
                        'perkerasan',
                    ])
                    ->orderBy('kode_ruas')
                    ->get()
                    ->map(fn ($jp) => [
                        'id' => $jp->id,
                        'kode_ruas' => $jp->kode_ruas,
                        'nama_ruas' => $jp->nama_ruas,
                        'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                        'sumber_data' => $jp->sumber_data ?? JalanPorosDesa::SUMBER_SK_PEMDA,
                        'panjang' => (float) ($jp->panjang ?? 0),
                        'lebar' => (float) ($jp->lebar ?? 0),
                        'kondisi' => $jp->kondisi,
                        'perkerasan' => $jp->perkerasan,
                    ])
                    ->values()
                    ->all();
            }

            return response()->json([
                'ok' => true,
                'data' => BkkdProposalLokasiResource::collection($lokasis),
                'geojson' => [
                    'type' => 'FeatureCollection',
                    'features' => $features,
                ],
                'layers' => [
                    'mode' => 'hybrid',
                    'martin_sources' => [
                        'batas_desa' => 'bataswilayah_desa',
                        'jalan_poros_desa' => 'jalan_porosdesa',
                        'jalan_kabupaten' => 'jalan_kabupaten_luarkota',
                        'infrastruktur_segmen' => 'infrastruktur_segmen',
                    ],
                    'batas_desa_bbox' => $this->resolveBatasDesaBbox($idDesa),
                ],
                'jalan_poros_options' => $jalanPorosOptions,
                'summary' => [
                    'id_proposal' => $proposal->id,
                    'nomor_registrasi' => $proposal->nomor_registrasi,
                    'tahun_anggaran' => (int) $proposal->tahun_anggaran,
                    'desa' => $proposal->desa?->nama_desa,
                    'kecamatan' => $proposal->kecamatan?->nama_kecamatan,
                    'jumlah_lokasi' => (int) ($proposal->jumlah_lokasi ?? $lokasis->count()),
                    'total_panjang_gis' => (float) ($proposal->total_panjang_gis ?? $lokasis->sum('panjang_gis_m')),
                    'status_analisa_spasial' => $proposal->status_analisa_spasial ?? 'belum_dianalisa',
                ],
            ]);
        }

        // 1. Layer Usulan Lintas Tahun di Desa yang sama (T-n, T, maupun T+n, kecuali proposal ini)
        $prevLokasiFeatures = [];
        $prevLokasis = BkkdProposalLokasi::query()
            ->withGeoJson()
            ->whereHas('proposal', function ($q) use ($idDesa, $proposal) {
                $q->where('id_desa', $idDesa)
                    ->where('id', '!=', $proposal->id)
                    ->whereNull('deleted_at');
            })
            ->with('proposal:id,nomor_registrasi,tahun_anggaran,tagging_usulan,judul_kegiatan,status_analisa_spasial')
            ->get();

        foreach ($prevLokasis as $prevLok) {
            $geom = is_string($prevLok->geojson) ? json_decode($prevLok->geojson, true) : $prevLok->geojson;
            if ($geom) {
                $prevLokasiFeatures[] = [
                    'type' => 'Feature',
                    'id' => $prevLok->id,
                    'geometry' => $geom,
                    'properties' => [
                        'id' => $prevLok->id,
                        'nama_lokasi' => $prevLok->nama_lokasi,
                        'panjang_gis_m' => (float) $prevLok->panjang_gis_m,
                        'nomor_registrasi' => $prevLok->proposal?->nomor_registrasi,
                        'tahun_anggaran' => $prevLok->proposal?->tahun_anggaran,
                        'tagging_usulan' => $prevLok->proposal?->tagging_usulan ?? 'PRKPD',
                        'judul_kegiatan' => $prevLok->proposal?->judul_kegiatan,
                    ],
                ];
            }
        }

        // 2. Layer Infrastruktur Segmen (Realisasi Pembangunan) di Desa yang sama
        $segmenFeatures = [];
        if (Schema::hasTable('infrastruktur_segmen')) {
            $segmens = InfrastrukturSegmen::where('id_desa', $idDesa)
                ->select([
                    'id',
                    'tipe_kode',
                    'namobj',
                    'panjang',
                    'lebar',
                    'kondisi',
                    'status_kondisi',
                    'tahun_pembangunan',
                    'sumber_dana',
                    'keterangan',
                    DB::raw('ST_AsGeoJSON(geom) as geojson'),
                ])
                ->get();

            foreach ($segmens as $seg) {
                $geom = is_string($seg->geojson) ? json_decode($seg->geojson, true) : $seg->geojson;
                if ($geom) {
                    $kondisiVal = $seg->kondisi instanceof \BackedEnum ? $seg->kondisi->value : (string) $seg->kondisi;
                    $segmenFeatures[] = [
                        'type' => 'Feature',
                        'id' => $seg->id,
                        'geometry' => $geom,
                        'properties' => [
                            'id' => $seg->id,
                            'namobj' => $seg->namobj ?: ($seg->keterangan ?: 'Segmen Infrastruktur'),
                            'panjang_meter' => (float) ($seg->panjang ?? 0),
                            'kondisi' => $kondisiVal,
                            'tahun_pembangunan' => $seg->tahun_pembangunan,
                            'sumber_dana' => $seg->sumber_dana,
                        ],
                    ];
                }
            }
        }

        // 3. Layer Master Jalan Poros & Desa di Desa yang sama (pisahkan gaya ruas SK Pemda vs promosi proposal)
        $jalanPorosFeatures = [];
        $jaringanJalanFeatures = [];
        $jalanPorosOptions = [];
        if (Schema::hasTable('jalan_porosdesa')) {
            $porosList = JalanPorosDesa::where('id_desa', $idDesa)
                ->withGeoJson()
                ->orderBy('kode_ruas')
                ->get();

            foreach ($porosList as $jp) {
                $geom = is_string($jp->geojson) ? json_decode($jp->geojson, true) : $jp->geojson;
                $jalanPorosOptions[] = [
                    'id' => $jp->id,
                    'kode_ruas' => $jp->kode_ruas,
                    'nama_ruas' => $jp->nama_ruas,
                    'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                    'sumber_data' => $jp->sumber_data ?? JalanPorosDesa::SUMBER_SK_PEMDA,
                    'panjang' => (float) ($jp->panjang ?? 0),
                    'lebar' => (float) ($jp->lebar ?? 0),
                    'kondisi' => $jp->kondisi,
                    'perkerasan' => $jp->perkerasan,
                    'geojson' => $geom,
                ];

                if ($geom) {
                    if ($jp->sumber_data === JalanPorosDesa::SUMBER_PROMOSI_PROPOSAL) {
                        $jaringanJalanFeatures[] = [
                            'type' => 'Feature',
                            'id' => $jp->id,
                            'geometry' => $geom,
                            'properties' => [
                                'id' => $jp->id,
                                'kode_ruas' => $jp->kode_ruas !== null ? (string) $jp->kode_ruas : null,
                                'nama_ruas' => $jp->nama_ruas,
                                'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                                'panjang_m' => (float) ($jp->panjang ?? 0),
                                'panjang_meter' => (float) ($jp->panjang ?? 0),
                                'lebar_m' => $jp->lebar !== null ? (float) $jp->lebar : null,
                                'kondisi' => $jp->kondisi,
                                'perkerasan' => $jp->perkerasan,
                                'sumber_data' => $jp->sumber_data,
                                'status_validasi' => 'terverifikasi',
                            ],
                        ];
                    } else {
                        $jalanPorosFeatures[] = [
                            'type' => 'Feature',
                            'id' => $jp->id,
                            'geometry' => $geom,
                            'properties' => [
                                'id' => $jp->id,
                                'kode_ruas' => $jp->kode_ruas,
                                'nama_ruas' => $jp->nama_ruas,
                                'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                                'panjang_meter' => (float) ($jp->panjang ?? 0),
                                'kondisi' => $jp->kondisi,
                                'perkerasan' => $jp->perkerasan,
                            ],
                        ];
                    }
                }
            }
        }

        return response()->json([
            'ok' => true,
            'data' => BkkdProposalLokasiResource::collection($lokasis),
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
            'layers' => [
                'usulan_tahun_lalu' => [
                    'type' => 'FeatureCollection',
                    'features' => $prevLokasiFeatures,
                ],
                'infrastruktur_segmen' => [
                    'type' => 'FeatureCollection',
                    'features' => $segmenFeatures,
                ],
                'jalan_poros_desa' => [
                    'type' => 'FeatureCollection',
                    'features' => $jalanPorosFeatures,
                ],
                'jaringan_jalan_ruas' => [
                    'type' => 'FeatureCollection',
                    'features' => $jaringanJalanFeatures,
                ],
                'jalan_kabupaten' => [
                    'type' => 'FeatureCollection',
                    'features' => $this->resolveJalanKabupatenFeatures($idDesa, $proposal->id),
                ],
                'batas_desa' => $this->resolveBatasDesaFeature($idDesa),
            ],
            'jalan_poros_options' => $jalanPorosOptions,
            'summary' => [
                'id_proposal' => $proposal->id,
                'nomor_registrasi' => $proposal->nomor_registrasi,
                'tahun_anggaran' => (int) $proposal->tahun_anggaran,
                'desa' => $proposal->desa?->nama_desa,
                'kecamatan' => $proposal->kecamatan?->nama_kecamatan,
                'jumlah_lokasi' => (int) ($proposal->jumlah_lokasi ?? $lokasis->count()),
                'total_panjang_gis' => (float) ($proposal->total_panjang_gis ?? $lokasis->sum('panjang_gis_m')),
                'status_analisa_spasial' => $proposal->status_analisa_spasial ?? 'belum_dianalisa',
                'catatan_konfirmasi_desa' => $proposal->catatan_konfirmasi_desa,
            ],
        ]);
    }

    public function store(StoreBkkdProposalLokasiRequest $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $user = $request->user();
        $validated = $request->validated();

        $sumberRute = $validated['sumber_rute'] ?? null;
        $preserveManual = array_key_exists('preserve_manual_geometry', $validated) && $validated['preserve_manual_geometry'] !== null
            ? (bool) $validated['preserve_manual_geometry']
            : (!empty($validated['geojson']) || in_array($sumberRute, ['manual', 'garis_lurus'], true));
        $extractedList = $this->spatialService->extractLocationsFromCoordinatesOrGeoJson((int) $proposal->id_desa, [
            'geojson' => $validated['geojson'] ?? null,
            'waypoints' => $validated['waypoints'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'latitude_akhir' => $validated['latitude_akhir'] ?? null,
            'longitude_akhir' => $validated['longitude_akhir'] ?? null,
            'sumber_rute' => $sumberRute,
            'preserve_manual_geometry' => $preserveManual,
            'id_jalan_poros' => $validated['id_jalan_poros'] ?? null,
            'nama_lokasi' => $validated['nama_lokasi'],
        ]);

        if (empty($extractedList) && !empty($validated['geojson'])) {
            $extractedList = [[
                'id_jalan_poros' => $validated['id_jalan_poros'] ?? null,
                'nama_ruas' => null,
                'nama_lokasi' => $validated['nama_lokasi'],
                'sumber_rute' => $validated['sumber_rute'] ?? 'manual',
                'geojson' => $validated['geojson'],
                'panjang_gis_m' => $this->spatialService->calculateGeoJsonLength($validated['geojson']),
            ]];
        }

        $createdIds = DB::transaction(function () use ($proposal, $validated, $extractedList, $user) {
            $nextUrutan = isset($validated['urutan']) ? (int) $validated['urutan'] : (($proposal->lokasi()->max('urutan') ?? 0) + 1);
            $isMulti = count($extractedList) > 1;
            $ids = [];

            foreach ($extractedList as $idx => $ext) {
                $geom = $this->spatialService->formatGeomForDatabase($ext['geojson']);
                $titikSebaran = $ext['titik_sebaran'] ?? ($validated['titik_sebaran'] ?? ($validated['waypoints'] ?? null));

                $lokasi = BkkdProposalLokasi::create([
                    'id_proposal' => $proposal->id,
                    'id_jalan_poros' => $ext['id_jalan_poros'] ?? ($validated['id_jalan_poros'] ?? null),
                    'urutan' => $nextUrutan + $idx,
                    'nama_lokasi' => \Illuminate\Support\Str::limit($ext['nama_lokasi'], 250),
                    'panjang_rencana_m' => $isMulti ? $ext['panjang_gis_m'] : ($validated['panjang_rencana_m'] ?? null),
                    'panjang_gis_m' => $ext['panjang_gis_m'],
                    'lebar_m' => $validated['lebar_m'] ?? null,
                    'ringkasan_analisa' => array_filter([
                        'sumber_rute' => $ext['sumber_rute'] ?? ($validated['sumber_rute'] ?? null),
                        'titik_sebaran' => $titikSebaran,
                    ], fn ($v) => $v !== null),
                    'geom' => $geom,
                    'created_by' => $user->uuid,
                ]);

                $ids[] = $lokasi->id;
            }

            // Jalankan analisa tumpang tindih spasial 3 lapis
            $this->spatialService->analisaProposal($proposal);

            $primaryLokasi = BkkdProposalLokasi::find($ids[0]);
            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'updated',
                alasan: $isMulti
                    ? sprintf('Menambahkan %d lokasi LineString mengikuti persimpangan Jalan Poros Desa ("%s")', count($ids), $validated['nama_lokasi'])
                    : sprintf('Menambahkan titik lokasi LineString "%s" (Panjang GIS: %s m)', $primaryLokasi->nama_lokasi, $primaryLokasi->panjang_gis_m),
                user: $user,
                extraSesudah: [
                    'lokasi_ids' => $ids,
                    'jumlah_lokasi_dibuat' => count($ids),
                    'nama_lokasi' => $primaryLokasi->nama_lokasi,
                    'panjang_gis_m' => $primaryLokasi->panjang_gis_m,
                    'status_analisa_spasial' => $primaryLokasi->status_analisa_spasial,
                ]
            );

            return $ids;
        });

        $createdList = BkkdProposalLokasi::whereIn('id', $createdIds)
            ->withGeoJson()
            ->with('jalanPoros:id,kode_ruas,nama_ruas')
            ->orderBy('urutan')
            ->get();

        $lokasiResult = $createdList->first();

        return response()->json([
            'ok' => true,
            'message' => count($createdIds) > 1
                ? sprintf('Garis usulan melintasi %d ruas Jalan Poros Desa dan otomatis dibagi menjadi %d lokasi LineString.', count($createdIds), count($createdIds))
                : 'Titik lokasi usulan berhasil ditambahkan dan dianalisis spasial.',
            'data' => new BkkdProposalLokasiResource($lokasiResult),
            'lokasi_list' => BkkdProposalLokasiResource::collection($createdList),
            'jumlah_lokasi_dibuat' => $createdList->count(),
        ], 201);
    }

    public function update(UpdateBkkdProposalLokasiRequest $request, string $id, string $lokasiId): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $lokasi = BkkdProposalLokasi::where('id_proposal', $proposal->id)->findOrFail($lokasiId);
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($proposal, $lokasi, $validated, $user) {
            $updateData = [];

            if (isset($validated['nama_lokasi'])) {
                $updateData['nama_lokasi'] = $validated['nama_lokasi'];
            }
            if (isset($validated['urutan'])) {
                $updateData['urutan'] = $validated['urutan'];
            }
            if (array_key_exists('panjang_rencana_m', $validated)) {
                $updateData['panjang_rencana_m'] = $validated['panjang_rencana_m'];
            }
            if (array_key_exists('lebar_m', $validated)) {
                $updateData['lebar_m'] = $validated['lebar_m'];
            }
            if (array_key_exists('id_jalan_poros', $validated)) {
                $updateData['id_jalan_poros'] = $validated['id_jalan_poros'];
            }

            if (!empty($validated['geojson']) || !empty($validated['waypoints']) || (isset($validated['latitude'], $validated['longitude']) && is_numeric($validated['latitude']) && is_numeric($validated['longitude']))) {
                $sumberRute = $validated['sumber_rute'] ?? null;
                $preserveManual = array_key_exists('preserve_manual_geometry', $validated) && $validated['preserve_manual_geometry'] !== null
                    ? (bool) $validated['preserve_manual_geometry']
                    : (!empty($validated['geojson']) || in_array($sumberRute, ['manual', 'garis_lurus'], true));
                $extractedList = $this->spatialService->extractLocationsFromCoordinatesOrGeoJson((int) $proposal->id_desa, [
                    'geojson' => $validated['geojson'] ?? null,
                    'waypoints' => $validated['waypoints'] ?? null,
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'latitude_akhir' => $validated['latitude_akhir'] ?? null,
                    'longitude_akhir' => $validated['longitude_akhir'] ?? null,
                    'id_jalan_poros' => $validated['id_jalan_poros'] ?? $lokasi->id_jalan_poros,
                    'nama_lokasi' => $validated['nama_lokasi'] ?? $lokasi->nama_lokasi,
                    'sumber_rute' => $sumberRute,
                    'preserve_manual_geometry' => $preserveManual,
                ]);

                if (count($extractedList) > 1) {
                    $extractedList = $this->spatialService->collapseLocationsToSingleItem(
                        $extractedList,
                        $validated['nama_lokasi'] ?? $lokasi->nama_lokasi
                    );
                }

                if (!empty($extractedList)) {
                    $firstExt = $extractedList[0];
                    $updateData['nama_lokasi'] = \Illuminate\Support\Str::limit($firstExt['nama_lokasi'], 250);
                    $updateData['id_jalan_poros'] = $firstExt['id_jalan_poros'] ?? ($updateData['id_jalan_poros'] ?? $lokasi->id_jalan_poros);
                    $updateData['panjang_gis_m'] = $firstExt['panjang_gis_m'];
                    $updateData['geom'] = $this->spatialService->formatGeomForDatabase($firstExt['geojson']);
                    $titikSebaran = $firstExt['titik_sebaran'] ?? ($validated['titik_sebaran'] ?? ($validated['waypoints'] ?? null));
                    $extraMeta = array_filter([
                        'sumber_rute' => $firstExt['sumber_rute'] ?? $sumberRute,
                        'titik_sebaran' => $titikSebaran,
                    ], fn ($v) => $v !== null);
                    if (!empty($extraMeta)) {
                        $updateData['ringkasan_analisa'] = array_merge(
                            is_array($lokasi->ringkasan_analisa) ? $lokasi->ringkasan_analisa : [],
                            $extraMeta
                        );
                    }
                } elseif (!empty($validated['geojson'])) {
                    $panjangGis = $this->spatialService->calculateGeoJsonLength($validated['geojson']);
                    $geom = $this->spatialService->formatGeomForDatabase($validated['geojson']);
                    $updateData['panjang_gis_m'] = $panjangGis;
                    $updateData['geom'] = $geom;
                }
            }

            $updateData['updated_by'] = $user->uuid;
            $lokasi->update($updateData);

            // Perbarui analisa tumpang tindih spasial
            $this->spatialService->analisaProposal($proposal);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'updated',
                alasan: sprintf('Memperbarui titik lokasi "%s"', $lokasi->nama_lokasi),
                user: $user,
                extraSesudah: [
                    'lokasi_id' => $lokasi->id,
                    'status_analisa_spasial' => $lokasi->status_analisa_spasial,
                ]
            );
        });

        $lokasiResult = BkkdProposalLokasi::where('id', $lokasi->id)
            ->withGeoJson()
            ->with('jalanPoros:id,kode_ruas,nama_ruas')
            ->first();

        return response()->json([
            'ok' => true,
            'message' => 'Titik lokasi berhasil diperbarui.',
            'data' => new BkkdProposalLokasiResource($lokasiResult),
        ]);
    }

    public function destroy(Request $request, string $id, string $lokasiId): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $lokasi = BkkdProposalLokasi::where('id_proposal', $proposal->id)->findOrFail($lokasiId);
        $user = $request->user();
        $namaLokasi = $lokasi->nama_lokasi;

        DB::transaction(function () use ($proposal, $lokasi, $namaLokasi, $user) {
            $lokasi->delete();
            $this->spatialService->analisaProposal($proposal);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'updated',
                alasan: sprintf('Menghapus titik lokasi "%s"', $namaLokasi),
                user: $user
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Titik lokasi berhasil dihapus.',
        ]);
    }

    public function reanalyze(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $updatedProposal = $this->spatialService->analisaProposal($proposal);

        return response()->json([
            'ok' => true,
            'message' => 'Analisa spasial tumpang tindih berhasil diperbarui.',
            'data' => [
                'status_analisa_spasial' => $updatedProposal->status_analisa_spasial,
                'jumlah_lokasi' => $updatedProposal->jumlah_lokasi,
                'total_panjang_gis' => (float) $updatedProposal->total_panjang_gis,
            ],
        ]);
    }

    /**
     * Sinkronisasi & analisa ulang proposal yang geometrinya diubah langsung di database (mis. via QGIS).
     */
    public function syncPendingAnalysis(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);
        $user = $request->user();
        if (!$user->hasPermissionTo('bkkd-proposal-update') && !$user->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki hak akses untuk sinkronisasi analisa spasial.');
        }

        $validated = $request->validate([
            'tahun_anggaran' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'only_pending' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $result = $this->spatialService->analisaPendingLocations(
            tahunAnggaran: isset($validated['tahun_anggaran']) ? (int) $validated['tahun_anggaran'] : null,
            onlyPending: array_key_exists('only_pending', $validated) ? (bool) $validated['only_pending'] : true,
            limit: isset($validated['limit']) ? (int) $validated['limit'] : 200
        );

        return response()->json([
            'ok' => true,
            'message' => $result['processed_proposals'] > 0
                ? sprintf(
                    'Sinkronisasi selesai: %d proposal (%d lokasi) telah dianalisa ulang.',
                    $result['processed_proposals'],
                    $result['processed_locations']
                )
                : 'Tidak ada proposal berstatus menunggu analisa spasial.',
            'data' => $result,
        ]);
    }

    public function daftarKonfirmasi(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $query = BkkdProposal::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama',
            'konfirmator:uuid,name',
        ])
        ->withCount('lokasi')
        ->forUser($user);

        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
        }

        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', (int) $request->input('id_kecamatan'));
        }

        if ($request->filled('id_desa')) {
            $query->where('id_desa', (int) $request->input('id_desa'));
        }

        if ($request->filled('status_analisa_spasial')) {
            $query->where('status_analisa_spasial', $request->input('status_analisa_spasial'));
        }

        $query->orderBy('created_at', 'desc');
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $proposals = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => BkkdProposalResource::collection($proposals->items()),
            'meta' => [
                'current_page' => $proposals->currentPage(),
                'last_page' => $proposals->lastPage(),
                'per_page' => $proposals->perPage(),
                'total' => $proposals->total(),
            ],
        ]);
    }

    public function simpanKonfirmasiDesa(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $request->validate([
            'catatan_konfirmasi_desa' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $catatan = $request->input('catatan_konfirmasi_desa');

        DB::transaction(function () use ($proposal, $catatan, $user) {
            $proposal->update([
                'catatan_konfirmasi_desa' => $catatan,
                'dikonfirmasi_oleh' => $user->uuid,
                'dikonfirmasi_pada' => now(),
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'updated',
                alasan: 'Menyimpan catatan hasil konfirmasi Bappeda dengan Pemerintah Desa terkait lokasi usulan',
                user: $user,
                extraSesudah: [
                    'catatan_konfirmasi_desa' => $catatan,
                ]
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Catatan konfirmasi Pemerintah Desa berhasil disimpan.',
            'data' => [
                'catatan_konfirmasi_desa' => $proposal->catatan_konfirmasi_desa,
                'dikonfirmasi_oleh' => $user->name,
                'dikonfirmasi_pada' => $proposal->dikonfirmasi_pada?->toIso8601String(),
            ],
        ]);
    }

    public function jalanPorosByDesa(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $idDesa = (int) $request->input('id_desa', 0);
        $isHybrid = $request->input('mode') === 'hybrid';
        if ($idDesa <= 0) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'layers' => [
                    'jalan_poros_desa' => ['type' => 'FeatureCollection', 'features' => []],
                    'infrastruktur_segmen' => ['type' => 'FeatureCollection', 'features' => []],
                    'batas_desa' => null,
                ],
            ]);
        }

        if ($isHybrid) {
            $porosList = collect();
            if (Schema::hasTable('jalan_porosdesa')) {
                $porosList = JalanPorosDesa::where('id_desa', $idDesa)
                    ->select([
                        'id',
                        'kode_ruas',
                        'nama_ruas',
                        'hierarki_jalan',
                        'sumber_data',
                        'panjang',
                        'lebar',
                        'kondisi',
                        'perkerasan',
                    ])
                    ->orderBy('kode_ruas')
                    ->get()
                    ->map(fn ($jp) => [
                        'id' => $jp->id,
                        'kode_ruas' => $jp->kode_ruas,
                        'nama_ruas' => $jp->nama_ruas,
                        'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                        'sumber_data' => $jp->sumber_data ?? JalanPorosDesa::SUMBER_SK_PEMDA,
                        'panjang' => (float) ($jp->panjang ?? 0),
                        'lebar' => (float) ($jp->lebar ?? 0),
                        'kondisi' => $jp->kondisi,
                        'perkerasan' => $jp->perkerasan,
                    ]);
            }

            return response()->json([
                'ok' => true,
                'data' => $porosList,
                'layers' => [
                    'mode' => 'hybrid',
                    'martin_sources' => [
                        'batas_desa' => 'bataswilayah_desa',
                        'jalan_poros_desa' => 'jalan_porosdesa',
                        'jalan_kabupaten' => 'jalan_kabupaten_luarkota',
                        'infrastruktur_segmen' => 'infrastruktur_segmen',
                    ],
                    'batas_desa_bbox' => $this->resolveBatasDesaBbox((int) $idDesa),
                ],
            ]);
        }

        $porosList = collect();
        $jalanPorosFeatures = [];
        $jaringanJalanFeatures = [];
        if (Schema::hasTable('jalan_porosdesa')) {
            $porosList = JalanPorosDesa::where('id_desa', $idDesa)
                ->withGeoJson()
                ->orderBy('kode_ruas')
                ->get()
                ->map(function ($jp) use (&$jalanPorosFeatures, &$jaringanJalanFeatures) {
                    $geom = is_string($jp->geojson) ? json_decode($jp->geojson, true) : $jp->geojson;
                    if ($geom) {
                        if ($jp->sumber_data === JalanPorosDesa::SUMBER_PROMOSI_PROPOSAL) {
                            $jaringanJalanFeatures[] = [
                                'type' => 'Feature',
                                'id' => $jp->id,
                                'geometry' => $geom,
                                'properties' => [
                                    'id' => $jp->id,
                                    'kode_ruas' => $jp->kode_ruas !== null ? (string) $jp->kode_ruas : null,
                                    'nama_ruas' => $jp->nama_ruas,
                                    'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                                    'panjang_m' => (float) ($jp->panjang ?? 0),
                                    'panjang_meter' => (float) ($jp->panjang ?? 0),
                                    'lebar_m' => $jp->lebar !== null ? (float) $jp->lebar : null,
                                    'kondisi' => $jp->kondisi,
                                    'perkerasan' => $jp->perkerasan,
                                    'sumber_data' => $jp->sumber_data,
                                    'status_validasi' => 'terverifikasi',
                                ],
                            ];
                        } else {
                            $jalanPorosFeatures[] = [
                                'type' => 'Feature',
                                'id' => $jp->id,
                                'geometry' => $geom,
                                'properties' => [
                                    'id' => $jp->id,
                                    'kode_ruas' => $jp->kode_ruas,
                                    'nama_ruas' => $jp->nama_ruas,
                                    'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                                    'panjang_meter' => (float) ($jp->panjang ?? 0),
                                    'lebar_meter' => (float) ($jp->lebar ?? 0),
                                    'kondisi' => $jp->kondisi,
                                    'perkerasan' => $jp->perkerasan,
                                ],
                            ];
                        }
                    }
                    return [
                        'id' => $jp->id,
                        'kode_ruas' => $jp->kode_ruas,
                        'nama_ruas' => $jp->nama_ruas,
                        'hierarki_jalan' => $jp->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                        'sumber_data' => $jp->sumber_data ?? JalanPorosDesa::SUMBER_SK_PEMDA,
                        'panjang' => (float) ($jp->panjang ?? 0),
                        'lebar' => (float) ($jp->lebar ?? 0),
                        'kondisi' => $jp->kondisi,
                        'perkerasan' => $jp->perkerasan,
                        'geojson' => $geom,
                    ];
                });
        }

        $segmenFeatures = [];
        if (Schema::hasTable('infrastruktur_segmen')) {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $segmens = InfrastrukturSegmen::where('id_desa', $idDesa)
                ->select([
                    'id',
                    'tipe_kode',
                    'namobj',
                    'panjang',
                    'lebar',
                    'kondisi',
                    'status_kondisi',
                    'tahun_pembangunan',
                    'sumber_dana',
                    'keterangan',
                    $isPgsql ? DB::raw('ST_AsGeoJSON(geom) as geojson') : 'geom as geojson',
                ])
                ->get();

            foreach ($segmens as $seg) {
                $geom = is_string($seg->geojson) ? json_decode($seg->geojson, true) : $seg->geojson;
                if ($geom) {
                    $kondisiVal = $seg->kondisi instanceof \BackedEnum ? $seg->kondisi->value : (string) $seg->kondisi;
                    $segmenFeatures[] = [
                        'type' => 'Feature',
                        'id' => $seg->id,
                        'geometry' => $geom,
                        'properties' => [
                            'id' => $seg->id,
                            'tipe_kode' => $seg->tipe_kode,
                            'namobj' => $seg->namobj ?: ($seg->keterangan ?: 'Segmen Infrastruktur'),
                            'panjang_meter' => (float) ($seg->panjang ?? 0),
                            'kondisi' => $kondisiVal,
                            'tahun_pembangunan' => $seg->tahun_pembangunan,
                            'sumber_dana' => $seg->sumber_dana,
                        ],
                    ];
                }
            }
        }

        $batasDesaFeature = $this->resolveBatasDesaFeature((int) $idDesa);

        return response()->json([
            'ok' => true,
            'data' => $porosList,
            'layers' => [
                'jalan_poros_desa' => [
                    'type' => 'FeatureCollection',
                    'features' => $jalanPorosFeatures,
                ],
                'jaringan_jalan_ruas' => [
                    'type' => 'FeatureCollection',
                    'features' => $jaringanJalanFeatures,
                ],
                'infrastruktur_segmen' => [
                    'type' => 'FeatureCollection',
                    'features' => $segmenFeatures,
                ],
                'jalan_kabupaten' => [
                    'type' => 'FeatureCollection',
                    'features' => $this->resolveJalanKabupatenFeatures((int) $idDesa),
                ],
                'batas_desa' => $batasDesaFeature,
            ],
        ]);
    }

    /**
     * Ambil GeoJSON Feature jalan kabupaten (jalan_kabupaten_luarkota) di sekitar desa / proposal.
     */
    protected function resolveJalanKabupatenFeatures(int $idDesa, ?string $proposalId = null): array
    {
        if (!Schema::hasTable('jalan_kabupaten_luarkota')) {
            return [];
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $rows = DB::table('jalan_kabupaten_luarkota')->get();
            $features = [];
            foreach ($rows as $row) {
                $geom = is_string($row->geom ?? null) ? json_decode($row->geom, true) : ($row->geom ?? null);
                if (!is_array($geom)) {
                    continue;
                }
                $features[] = [
                    'type' => 'Feature',
                    'id' => 'kab-' . $row->id,
                    'geometry' => $geom,
                    'properties' => [
                        'id' => $row->id,
                        'no_ruas' => $row->no_ruas ?? null,
                        'nama_ruas' => $row->nama_ruas ?? 'Jalan Kabupaten',
                        'pangkal' => $row->pangkal ?? null,
                        'ujung' => $row->ujung ?? null,
                        'klas_ruas' => $row->klas_ruas ?? null,
                        'status_ruas' => $row->status_ruas ?? 'Jalan Kabupaten',
                        'kecamatan' => $row->nama_kecamatan ?? null,
                        'panjang_m' => (float) ($row->panjang ?? 0),
                    ],
                ];
            }
            return $features;
        }

        try {
            $kabCols = Schema::getColumnListing('jalan_kabupaten_luarkota');
            $pick = function (string $lower, string $legacy, string $fallbackSql = 'NULL::text') use ($kabCols) {
                if (in_array($lower, $kabCols, true)) {
                    return 'jk.' . $lower;
                }
                if (in_array($legacy, $kabCols, true)) {
                    return 'jk."' . $legacy . '"';
                }
                return $fallbackSql;
            };

            $namaExpr = $pick('nama_ruas', 'Nama_Ruas', "'Jalan Kabupaten'::varchar");
            $noExpr = $pick('no_ruas', 'No_Ruas', 'NULL::double precision');
            $pangkalExpr = $pick('pangkal', 'Pangkal', 'NULL::varchar');
            $ujungExpr = $pick('ujung', 'Ujung', 'NULL::varchar');
            $klasExpr = $pick('klas_ruas', 'Klas_Ruas', 'NULL::varchar');
            $statusExpr = $pick('status_ruas', 'Status_Ruas', "'Jalan Kabupaten'::varchar");
            $kecExpr = $pick('nama_kecamatan', 'Nama_Kecamatan', 'NULL::varchar');
            $panjangExpr = $pick('panjang', 'Panjang', '0::double precision');

            $spatialConds = [];
            $bindings = [];

            if (Schema::hasTable('bataswilayah_desa')) {
                $spatialConds[] = 'EXISTS (SELECT 1 FROM bataswilayah_desa d WHERE d.id = :idDesa AND d.geom IS NOT NULL AND ST_Intersects(ST_Transform(jk.geom, 4326), ST_Buffer(d.geom::geography, 500)::geometry))';
                $bindings['idDesa'] = $idDesa;
            }
            if ($proposalId) {
                $spatialConds[] = 'EXISTS (SELECT 1 FROM bkkd_proposal_lokasi l WHERE l.id_proposal = :proposalId AND l.geom IS NOT NULL AND ST_Intersects(ST_Transform(jk.geom, 4326), ST_Buffer(l.geom::geography, 500)::geometry))';
                $bindings['proposalId'] = $proposalId;
            }

            if (empty($spatialConds)) {
                return [];
            }

            $rows = DB::select("
                SELECT
                    jk.id,
                    {$noExpr} AS no_ruas,
                    COALESCE({$namaExpr}, 'Jalan Kabupaten') AS nama_ruas,
                    {$pangkalExpr} AS pangkal,
                    {$ujungExpr} AS ujung,
                    {$klasExpr} AS klas_ruas,
                    COALESCE({$statusExpr}, 'Jalan Kabupaten') AS status_ruas,
                    {$kecExpr} AS kecamatan,
                    COALESCE(NULLIF({$panjangExpr}, 0), ROUND(ST_Length(ST_Transform(jk.geom, 4326)::geography)::numeric, 2)) AS panjang_m,
                    ST_AsGeoJSON(ST_Force2D(ST_Transform(jk.geom, 4326))) AS geojson
                FROM jalan_kabupaten_luarkota jk
                WHERE jk.geom IS NOT NULL
                  AND (" . implode(' OR ', $spatialConds) . ")
            ", $bindings);

            $features = [];
            foreach ($rows as $row) {
                $geom = !empty($row->geojson) ? json_decode($row->geojson, true) : null;
                if (!is_array($geom)) {
                    continue;
                }
                $features[] = [
                    'type' => 'Feature',
                    'id' => 'kab-' . $row->id,
                    'geometry' => $geom,
                    'properties' => [
                        'id' => $row->id,
                        'no_ruas' => $row->no_ruas,
                        'nama_ruas' => $row->nama_ruas,
                        'pangkal' => $row->pangkal,
                        'ujung' => $row->ujung,
                        'klas_ruas' => $row->klas_ruas,
                        'status_ruas' => $row->status_ruas,
                        'kecamatan' => $row->kecamatan,
                        'panjang_m' => round((float) ($row->panjang_m ?? 0), 2),
                    ],
                ];
            }

            return $features;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ambil bounding box [minX, minY, maxX, maxY] batas desa (ringan untuk mode hybrid Martin MVT).
     */
    protected function resolveBatasDesaBbox(int $idDesa): ?array
    {
        if (!Schema::hasTable('bataswilayah_desa') || DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        $row = DB::selectOne("
            SELECT
                ST_XMin(geom) AS minx,
                ST_YMin(geom) AS miny,
                ST_XMax(geom) AS maxx,
                ST_YMax(geom) AS maxy
            FROM bataswilayah_desa
            WHERE id = :idDesa AND geom IS NOT NULL
        ", ['idDesa' => $idDesa]);

        if (!$row || $row->minx === null) {
            return null;
        }

        return [(float) $row->minx, (float) $row->miny, (float) $row->maxx, (float) $row->maxy];
    }

    /**
     * Ambil GeoJSON Feature batas wilayah desa untuk overlay peta workspace.
     */
    protected function resolveBatasDesaFeature(int $idDesa): ?array
    {
        if (!Schema::hasTable('bataswilayah_desa') || DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        $desaRow = DB::selectOne("
            SELECT id, nama_desa, ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.00005)) AS geojson
            FROM bataswilayah_desa
            WHERE id = :idDesa AND geom IS NOT NULL
        ", ['idDesa' => $idDesa]);

        if (!$desaRow || empty($desaRow->geojson)) {
            return null;
        }

        return [
            'type' => 'Feature',
            'id' => $desaRow->id,
            'geometry' => json_decode($desaRow->geojson, true),
            'properties' => [
                'id' => $desaRow->id,
                'nama_desa' => $desaRow->nama_desa,
            ],
        ];
    }

    /**
     * Generate rute LineString (Jalan Poros Desa / OpenRouteService) dari titik awal-akhir dan kembalikan hasil analisa spasial instan.
     */
    public function generateRoute(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $validated = $request->validate([
            'id_desa' => ['required', 'numeric', 'exists:bataswilayah_desa,id'],
            'id_proposal' => ['nullable', 'string', 'max:36'],
            'tahun_anggaran' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'tagging_usulan' => ['nullable', 'string', 'in:RKPD,PRKPD'],
            'nama_lokasi' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude_akhir' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude_akhir' => ['nullable', 'numeric', 'between:-180,180'],
            'id_lokasi' => ['nullable', 'string', 'max:36'],
            'single_location_mode' => ['nullable', 'boolean'],
            'id_jalan_poros' => ['nullable', 'string', 'max:36'],
            'panjang_rencana_m' => ['nullable', 'numeric', 'min:0'],
            'lebar_m' => ['nullable', 'numeric', 'min:0'],
            'sumber_rute' => ['nullable', 'string', 'max:50'],
            'preserve_manual_geometry' => ['nullable', 'boolean'],
            'skip_ors' => ['nullable', 'boolean'],
            'waypoints' => ['nullable', 'array', 'min:2', 'max:50'],
            'waypoints.*.lat' => ['required_with:waypoints', 'numeric', 'between:-90,90'],
            'waypoints.*.lng' => ['required_with:waypoints', 'numeric', 'between:-180,180'],
            'waypoints.*.label' => ['nullable', 'string', 'max:120'],
            'waypoints.*.is_route' => ['nullable', 'boolean'],
            'waypoints.*.use_ors' => ['nullable', 'boolean'],
            'geojson' => ['nullable'],
        ]);

        $idDesa = (int) $validated['id_desa'];
        $tahunAnggaran = (int) ($validated['tahun_anggaran'] ?? date('Y'));

        $preview = $this->spatialService->previewRouteAndAnalyze($idDesa, $tahunAnggaran, $validated);

        return response()->json([
            'ok' => true,
            'data' => $preview['lokasi_list'][0] ?? null,
            'lokasi_list' => $preview['lokasi_list'],
            'jumlah_lokasi' => $preview['jumlah_lokasi'],
            'total_panjang_gis' => $preview['total_panjang_gis'],
            'status_analisa_spasial' => $preview['status_analisa_spasial'],
        ]);
    }
}


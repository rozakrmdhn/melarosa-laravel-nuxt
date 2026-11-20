<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBkkdProposalRequest;
use App\Http\Requests\Admin\UpdateBkkdProposalRequest;
use App\Http\Resources\BkkdProposalDetailResource;
use App\Http\Resources\BkkdProposalResource;
use App\Http\Resources\BkkdRiwayatResource;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\BkkdProposal;
use App\Models\BkkdProposalLokasi;
use App\Models\BkkdProposalRiwayat;
use App\Models\RefBkkdBidang;
use App\Services\BkkdRiwayatService;
use App\Services\BkkdSpatialAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BkkdProposalController extends Controller
{
    public function __construct(
        protected BkkdRiwayatService $riwayatService,
        protected BkkdSpatialAnalysisService $spatialService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $query = BkkdProposal::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama,id_opd_default',
            'lokasi:id,id_proposal,id_jalan_poros,id_jaringan_ruas,urutan,nama_lokasi,nama_ruas_usulan,hierarki_jalan_usulan,panjang_rencana_m,panjang_gis_m,lebar_m,status_analisa_spasial,status_promosi_master,ringkasan_analisa,validasi_promosi_spasial',
            'lokasi.jaringanRuas:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data',
            'konfirmator:uuid,name',
        ])
        ->select([
            'id',
            'nomor_registrasi',
            'tahun_anggaran',
            'tagging_usulan',
            'id_kecamatan',
            'id_desa',
            'id_bidang',
            'judul_kegiatan',
            'uraian_kegiatan',
            'lokasi_kegiatan',
            'nilai_usulan',
            'jumlah_lokasi',
            'total_panjang_gis',
            'status_analisa_spasial',
            'catatan_konfirmasi_desa',
            'dikonfirmasi_oleh',
            'dikonfirmasi_pada',
            'versi',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ])
        ->forUser($user);

        // Filter tahun anggaran & tagging perencanaan
        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
        }

        if ($request->filled('tagging_usulan')) {
            $query->where('tagging_usulan', strtoupper(trim((string) $request->input('tagging_usulan'))));
        }

        // Filter wilayah
        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', (int) $request->input('id_kecamatan'));
        }

        if ($request->filled('id_desa')) {
            $query->where('id_desa', (int) $request->input('id_desa'));
        }

        // Filter bidang
        if ($request->filled('id_bidang')) {
            $query->where('id_bidang', $request->input('id_bidang'));
        }

        // Filter status analisa spasial (tumpang tindih lintas tahun)
        if ($request->filled('status_analisa_spasial')) {
            $query->where('status_analisa_spasial', $request->input('status_analisa_spasial'));
        }

        // Search
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('judul_kegiatan', 'ilike', $search)
                  ->orWhere('nomor_registrasi', 'ilike', $search)
                  ->orWhere('lokasi_kegiatan', 'ilike', $search);
            });
        }

        // Metrics summary (dari filter aktif) dihitung dalam 1 query SQL efisien
        $summaryQuery = (clone $query)->reorder()->toBase();
        $summaryQuery->columns = null;
        $summaryStats = $summaryQuery->selectRaw("
            COUNT(*) as total_proposal,
            COALESCE(SUM(nilai_usulan), 0) as total_nilai_usulan,
            COALESCE(SUM(jumlah_lokasi), 0) as total_lokasi,
            COALESCE(SUM(total_panjang_gis), 0) as total_panjang_gis,
            COUNT(CASE WHEN status_analisa_spasial = 'aman' THEN 1 END) as jumlah_aman,
            COUNT(CASE WHEN status_analisa_spasial = 'lanjutan_ruas' THEN 1 END) as jumlah_lanjutan_ruas,
            COUNT(CASE WHEN status_analisa_spasial = 'konfirmasi_belum_tuntas' THEN 1 END) as jumlah_konfirmasi_belum_tuntas,
            COUNT(CASE WHEN status_analisa_spasial = 'duplikasi_tuntas' THEN 1 END) as jumlah_duplikasi_tuntas,
            COUNT(CASE WHEN status_analisa_spasial = 'kewenangan_kabupaten' THEN 1 END) as jumlah_kewenangan_kabupaten,
            COUNT(CASE WHEN status_analisa_spasial IS NULL OR status_analisa_spasial = 'belum_dianalisa' THEN 1 END) as jumlah_belum_dianalisa
        ")->first();

        $summary = [
            'total_proposal' => (int) ($summaryStats->total_proposal ?? 0),
            'total_nilai_usulan' => (float) ($summaryStats->total_nilai_usulan ?? 0),
            'total_lokasi' => (int) ($summaryStats->total_lokasi ?? 0),
            'total_panjang_gis' => round((float) ($summaryStats->total_panjang_gis ?? 0), 2),
            'jumlah_aman' => (int) ($summaryStats->jumlah_aman ?? 0),
            'jumlah_lanjutan_ruas' => (int) ($summaryStats->jumlah_lanjutan_ruas ?? 0),
            'jumlah_konfirmasi_belum_tuntas' => (int) ($summaryStats->jumlah_konfirmasi_belum_tuntas ?? 0),
            'jumlah_duplikasi_tuntas' => (int) ($summaryStats->jumlah_duplikasi_tuntas ?? 0),
            'jumlah_kewenangan_kabupaten' => (int) ($summaryStats->jumlah_kewenangan_kabupaten ?? 0),
            'jumlah_belum_dianalisa' => (int) ($summaryStats->jumlah_belum_dianalisa ?? 0),
        ];

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'nomor_registrasi', 'nilai_usulan', 'tahun_anggaran', 'status_analisa_spasial'];

        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

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
            'summary' => $summary,
        ]);
    }

    public function store(StoreBkkdProposalRequest $request): JsonResponse
    {
        Gate::authorize('create', BkkdProposal::class);

        $user = $request->user();
        $validated = $request->validated();

        // Auto-lock wilayah jika pengguna memiliki pembatasan wilayah (desa / kecamatan)
        if ($user && !$user->hasRole('admin') && !$user->hasRole('verifierBappeda')) {
            if ($user->id_kecamatan) {
                $validated['id_kecamatan'] = $user->id_kecamatan;
            }
            if ($user->id_desa) {
                $validated['id_desa'] = $user->id_desa;
            }
        }

        $tahun = (int) $validated['tahun_anggaran'];
        $idDesa = (int) $validated['id_desa'];
        $panjangFallback = $request->input('panjang');
        $lebarFallback = $request->input('lebar');

        $proposal = DB::transaction(function () use ($validated, $tahun, $idDesa, $user, $panjangFallback, $lebarFallback) {
            $desa = BatasWilayahDesa::where('id', $idDesa)->lockForUpdate()->firstOrFail();

            // Generator nomor registrasi berurutan
            $count = BkkdProposal::withTrashed()
                ->where('tahun_anggaran', $tahun)
                ->where('id_desa', $idDesa)
                ->count();

            $nextUrut = $count + 1;
            $kodeWilayah = $desa->id;
            $nomorUrut = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
            $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeWilayah, $nomorUrut);

            while (BkkdProposal::withTrashed()->where('nomor_registrasi', $nomorRegistrasi)->exists()) {
                $nextUrut++;
                $nomorUrut = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
                $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeWilayah, $nomorUrut);
            }

            $spatialInput = [
                'lokasi' => $validated['lokasi'] ?? null,
                'geojson' => $validated['geojson'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'latitude_akhir' => $validated['latitude_akhir'] ?? null,
                'longitude_akhir' => $validated['longitude_akhir'] ?? null,
                'id_jalan_poros' => $validated['id_jalan_poros'] ?? null,
                'panjang' => $panjangFallback,
                'lebar' => $lebarFallback,
                'lokasi_kegiatan' => $validated['lokasi_kegiatan'] ?? null,
                'judul_kegiatan' => $validated['judul_kegiatan'] ?? null,
            ];
            unset(
                $validated['lokasi'],
                $validated['geojson'],
                $validated['latitude'],
                $validated['longitude'],
                $validated['latitude_akhir'],
                $validated['longitude_akhir'],
                $validated['id_jalan_poros']
            );

            $proposal = BkkdProposal::create(array_merge($validated, [
                'nomor_registrasi' => $nomorRegistrasi,
                'tagging_usulan' => $validated['tagging_usulan'] ?? BkkdProposal::TAGGING_PRKPD,
                'versi' => 1,
                'created_by' => $user->uuid,
            ]));

            // Simpan titik / garis koordinat lokasi usulan dan jalankan analisa spasial 3 lapis (Tahun T-1, Infrastruktur Segmen, Jalan Poros Desa)
            $this->syncLokasiSpasialOnProposalSave($proposal, $spatialInput, $user);

            // Catat riwayat pembuatan
            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'created',
                alasan: 'Pencatatan usulan proposal BKKD baru',
                user: $user,
                extraSesudah: [
                    'nomor_registrasi' => $proposal->nomor_registrasi,
                    'judul_kegiatan' => $proposal->judul_kegiatan,
                    'nilai_usulan' => $proposal->nilai_usulan,
                    'jumlah_lokasi' => $proposal->jumlah_lokasi,
                    'total_panjang_gis' => $proposal->total_panjang_gis,
                    'status_analisa_spasial' => $proposal->status_analisa_spasial,
                ]
            );

            return $proposal;
        });

        $proposal->load([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama',
            'lokasi' => fn ($q) => $q->withGeoJson()->with(['jalanPoros:id,kode_ruas,nama_ruas', 'jaringanRuas:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data'])->orderBy('urutan'),
            'konfirmator:uuid,name',
            'creator:uuid,name',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Proposal usulan BKKD berhasil dicatat.',
            'data' => new BkkdProposalDetailResource($proposal),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::withTrashed()->with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama,id_opd_default',
            'lokasi' => fn ($q) => $q->withGeoJson()->with(['jalanPoros:id,kode_ruas,nama_ruas', 'jaringanRuas:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data'])->orderBy('urutan'),
            'konfirmator:uuid,name',
            'creator:uuid,name',
            'updater:uuid,name',
        ])->findOrFail($id);

        Gate::authorize('view', $proposal);

        return response()->json([
            'ok' => true,
            'data' => new BkkdProposalDetailResource($proposal),
        ]);
    }

    public function update(UpdateBkkdProposalRequest $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $user = $request->user();
        $validated = $request->validated();

        $versiKlien = (int) $validated['versi'];
        $alasan = $validated['alasan_perubahan'] ?? null;

        $spatialInput = [
            'lokasi' => $validated['lokasi'] ?? null,
            'geojson' => $validated['geojson'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'latitude_akhir' => $validated['latitude_akhir'] ?? null,
            'longitude_akhir' => $validated['longitude_akhir'] ?? null,
            'id_jalan_poros' => $validated['id_jalan_poros'] ?? null,
            'panjang' => $request->input('panjang'),
            'lebar' => $request->input('lebar'),
            'lokasi_kegiatan' => $validated['lokasi_kegiatan'] ?? null,
            'judul_kegiatan' => $validated['judul_kegiatan'] ?? null,
        ];

        unset(
            $validated['versi'],
            $validated['alasan_perubahan'],
            $validated['lokasi'],
            $validated['geojson'],
            $validated['latitude'],
            $validated['longitude'],
            $validated['latitude_akhir'],
            $validated['longitude_akhir'],
            $validated['id_jalan_poros']
        );

        $updatedProposal = DB::transaction(function () use ($proposal, $validated, $spatialInput, $versiKlien, $alasan, $user) {
            $updated = $this->riwayatService->catatPerubahanProposal(
                proposal: $proposal,
                dataBaru: $validated,
                versiKlien: $versiKlien,
                alasan: $alasan,
                user: $user,
                aksi: 'updated'
            );

            $this->syncLokasiSpasialOnProposalSave($updated, $spatialInput, $user);

            return $updated->fresh();
        });

        $updatedProposal->load([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama,id_opd_default',
            'lokasi' => fn ($q) => $q->withGeoJson()->with(['jalanPoros:id,kode_ruas,nama_ruas', 'jaringanRuas:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data'])->orderBy('urutan'),
            'konfirmator:uuid,name',
            'creator:uuid,name',
            'updater:uuid,name',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Data proposal usulan BKKD berhasil diperbarui.',
            'data' => new BkkdProposalDetailResource($updatedProposal),
        ]);
    }

    /**
     * Sinkronisasi koordinat lokasi usulan (ekstraksi mengikuti geometri Jalan Poros Desa & pecah multi-lokasi jika melintasi >1 ruas)
     * dan jalankan analisa spasial 3 lapis (Tahun T-1, Infrastruktur Segmen, Jalan Poros Desa).
     */
    protected function syncLokasiSpasialOnProposalSave(BkkdProposal $proposal, array $spatialInput, $user): void
    {
        $idDesa = (int) $proposal->id_desa;

        if (!empty($spatialInput['lokasi']) && is_array($spatialInput['lokasi'])) {
            $proposal->lokasi()->delete();
            $urutanCounter = 1;

            foreach (array_values($spatialInput['lokasi']) as $lokItem) {
                if (empty($lokItem['geojson']) && !isset($lokItem['latitude']) && empty($lokItem['waypoints'])) {
                    continue;
                }

                $lokSumber = $lokItem['sumber_rute'] ?? null;
                $extractedItems = $this->spatialService->extractLocationsFromCoordinatesOrGeoJson($idDesa, [
                    'geojson' => $lokItem['geojson'] ?? null,
                    'waypoints' => $lokItem['waypoints'] ?? null,
                    'latitude' => $lokItem['latitude'] ?? null,
                    'longitude' => $lokItem['longitude'] ?? null,
                    'latitude_akhir' => $lokItem['latitude_akhir'] ?? null,
                    'longitude_akhir' => $lokItem['longitude_akhir'] ?? null,
                    'id_jalan_poros' => $lokItem['id_jalan_poros'] ?? null,
                    'nama_lokasi' => $lokItem['nama_lokasi'] ?? ($spatialInput['lokasi_kegiatan'] ?: $proposal->judul_kegiatan),
                    'panjang' => $lokItem['panjang_rencana_m'] ?? ($spatialInput['panjang'] ?? null),
                    'sumber_rute' => $lokSumber,
                    'preserve_manual_geometry' => !empty($lokItem['geojson']) || in_array($lokSumber, ['manual', 'garis_lurus'], true),
                ]);

                foreach ($extractedItems as $ext) {
                    $geom = $this->spatialService->formatGeomForDatabase($ext['geojson']);
                    $titikSebaran = $ext['titik_sebaran'] ?? ($lokItem['titik_sebaran'] ?? ($lokItem['waypoints'] ?? null));

                    BkkdProposalLokasi::create([
                        'id_proposal' => $proposal->id,
                        'id_jalan_poros' => $ext['id_jalan_poros'] ?? ($lokItem['id_jalan_poros'] ?? null),
                        'urutan' => $urutanCounter++,
                        'nama_lokasi' => Str::limit($ext['nama_lokasi'], 250),
                        'panjang_rencana_m' => count($extractedItems) === 1
                            ? ($lokItem['panjang_rencana_m'] ?? ($spatialInput['panjang'] ?? null))
                            : $ext['panjang_gis_m'],
                        'panjang_gis_m' => $ext['panjang_gis_m'],
                        'lebar_m' => $lokItem['lebar_m'] ?? ($spatialInput['lebar'] ?? null),
                        'ringkasan_analisa' => array_filter([
                            'sumber_rute' => $ext['sumber_rute'] ?? ($lokItem['sumber_rute'] ?? null),
                            'titik_sebaran' => $titikSebaran,
                        ], fn ($v) => $v !== null),
                        'geom' => $geom,
                        'created_by' => $user?->uuid,
                    ]);
                }
            }

            $this->spatialService->analisaProposal($proposal);
            return;
        }

        $extractedList = $this->spatialService->extractLocationsFromCoordinatesOrGeoJson($idDesa, $spatialInput);
        if (!empty($extractedList)) {
            $existingCount = $proposal->lokasi()->count();

            if (count($extractedList) === 1 && $existingCount === 1) {
                $ext = $extractedList[0];
                $existingPrimary = $proposal->lokasi()->orderBy('urutan')->first();
                $existingPrimary->update([
                    'id_jalan_poros' => $ext['id_jalan_poros'] ?? $existingPrimary->id_jalan_poros,
                    'nama_lokasi' => Str::limit($ext['nama_lokasi'], 250),
                    'panjang_rencana_m' => $spatialInput['panjang'] ?? $existingPrimary->panjang_rencana_m,
                    'panjang_gis_m' => $ext['panjang_gis_m'],
                    'lebar_m' => $spatialInput['lebar'] ?? $existingPrimary->lebar_m,
                    'geom' => $this->spatialService->formatGeomForDatabase($ext['geojson']),
                    'updated_by' => $user?->uuid,
                ]);
            } else {
                if ($existingCount > 0) {
                    $proposal->lokasi()->delete();
                }

                $isMulti = count($extractedList) > 1;
                foreach ($extractedList as $idx => $ext) {
                    BkkdProposalLokasi::create([
                        'id_proposal' => $proposal->id,
                        'id_jalan_poros' => $ext['id_jalan_poros'] ?? ($spatialInput['id_jalan_poros'] ?? null),
                        'urutan' => $idx + 1,
                        'nama_lokasi' => Str::limit($ext['nama_lokasi'], 250),
                        'panjang_rencana_m' => $isMulti ? $ext['panjang_gis_m'] : ($spatialInput['panjang'] ?? null),
                        'panjang_gis_m' => $ext['panjang_gis_m'],
                        'lebar_m' => $spatialInput['lebar'] ?? null,
                        'geom' => $this->spatialService->formatGeomForDatabase($ext['geojson']),
                        'created_by' => $user?->uuid,
                    ]);
                }
            }

            $this->spatialService->analisaProposal($proposal);
            return;
        }

        if ($proposal->lokasi()->exists()) {
            $this->spatialService->analisaProposal($proposal);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('delete', $proposal);

        $user = $request->user();
        DB::transaction(function () use ($proposal, $user) {
            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'deleted',
                alasan: 'Proposal dihapus sementara (soft delete)',
                user: $user
            );

            $proposal->delete();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Proposal berhasil dihapus.',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $proposal);

        $user = $request->user();
        DB::transaction(function () use ($proposal, $user) {
            $proposal->restore();

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'restored',
                alasan: 'Proposal dipulihkan kembali dari tempat sampah',
                user: $user
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Proposal berhasil dipulihkan.',
        ]);
    }

    public function riwayat(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('view', $proposal);

        $riwayat = $proposal->riwayat()->with('actor:uuid,name,email')->get();

        return response()->json([
            'ok' => true,
            'data' => BkkdRiwayatResource::collection($riwayat),
        ]);
    }

    public function riwayatSemua(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('bkkd-riwayat-view') && !$user->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki hak akses melihat riwayat.');
        }

        $query = BkkdProposalRiwayat::with([
            'proposal.kecamatan:id,nama_kecamatan',
            'proposal.desa:id,nama_desa',
            'actor:uuid,name,email',
        ])->whereHas('proposal', function ($q) use ($user, $request) {
            $q->forUser($user);
            if ($request->filled('tahun_anggaran')) {
                $q->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
            }
            if ($request->filled('id_kecamatan')) {
                $q->where('id_kecamatan', (int) $request->input('id_kecamatan'));
            }
            if ($request->filled('id_desa')) {
                $q->where('id_desa', (int) $request->input('id_desa'));
            }
        });

        if ($request->filled('aksi')) {
            $query->where('aksi', $request->input('aksi'));
        }

        $query->orderByDesc('created_at');
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $results = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => BkkdRiwayatResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        Gate::authorize('create', BkkdProposal::class);

        $filename = 'Template_Import_Proposal_BKKD.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Tahun Anggaran',
                'Kecamatan',
                'Desa',
                'Bidang',
                'Judul Kegiatan',
                'Nilai Usulan (Rp)',
                'Lokasi Kegiatan',
                'Uraian Kegiatan',
            ]);

            fputcsv($handle, [
                2027,
                'Kanor',
                'Pilang',
                'Jalan Poros Desa',
                'Pembangunan Jalan Rabat Beton RT 01 RW 02',
                250000000,
                'Dusun Krajan',
                'Pembangunan jalan poros desa untuk kelancaran transportasi warga',
            ]);

            fputcsv($handle, [
                2027,
                'Kanor',
                'Pilang',
                'Jembatan Desa',
                'Pembangunan Jembatan Dusun Sambong',
                350000000,
                'Dusun Sambong',
                'Pembangunan jembatan penghubung jalan usaha tani',
            ]);

            fclose($handle);
        }, 200, $headers);
    }

    public function importExcel(Request $request): JsonResponse
    {
        Gate::authorize('create', BkkdProposal::class);
        $user = $request->user();

        $request->validate([
            'rows' => ['nullable', 'array', 'max:500'],
            'file' => ['nullable', 'file', 'max:5120'],
        ]);

        $rows = $request->input('rows');

        if (empty($rows) && $request->hasFile('file')) {
            $file = $request->file('file');
            $rows = array_slice($this->parseCsvFile($file->getRealPath()), 0, 500);
        }

        if (empty($rows) || !is_array($rows)) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak ada data usulan yang dapat diimport. Pastikan file Excel/CSV berisi baris data yang valid.',
            ], 422);
        }

        // Cache master data in memory for rapid lookup
        $kecamatans = BatasWilayahKecamatan::all();
        $desas = BatasWilayahDesa::all();
        $bidangs = RefBkkdBidang::all();

        $importedProposals = [];
        $errors = [];
        $generatedBatchNumbers = [];

        DB::transaction(function () use (
            $rows,
            $user,
            $kecamatans,
            $desas,
            $bidangs,
            &$importedProposals,
            &$errors,
            &$generatedBatchNumbers
        ) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;

                // Extract fields with multiple possible alias keys
                $tahunRaw = $row['tahun_anggaran'] ?? $row['Tahun Anggaran'] ?? $row['tahun'] ?? $row['Tahun'] ?? (now()->year + 1);
                $kecamatanRaw = $row['nama_kecamatan'] ?? $row['Kecamatan'] ?? $row['kecamatan'] ?? $row['id_kecamatan'] ?? null;
                $desaRaw = $row['nama_desa'] ?? $row['Desa'] ?? $row['desa'] ?? $row['id_desa'] ?? null;
                $bidangRaw = $row['nama_bidang'] ?? $row['Bidang'] ?? $row['bidang'] ?? $row['id_bidang'] ?? null;
                $judul = trim((string) ($row['judul_kegiatan'] ?? $row['Judul Kegiatan'] ?? $row['judul'] ?? $row['Judul'] ?? ''));
                $nilaiRaw = $row['nilai_usulan'] ?? $row['Nilai Usulan'] ?? $row['Nilai Usulan (Rp)'] ?? $row['nilai'] ?? null;
                $lokasiRaw = $row['lokasi_kegiatan'] ?? $row['Lokasi Kegiatan'] ?? $row['lokasi'] ?? null;
                $uraianRaw = $row['uraian_kegiatan'] ?? $row['Uraian Kegiatan'] ?? $row['uraian'] ?? null;

                if (empty($judul)) {
                    $errors[] = [
                        'baris' => $rowNumber,
                        'judul' => '-',
                        'pesan' => 'Judul kegiatan wajib diisi.',
                    ];
                    continue;
                }

                $tahun = (int) $tahunRaw;
                if ($tahun < 2020 || $tahun > 2100) {
                    $tahun = now()->year + 1;
                }

                // Resolve Kecamatan
                $kecamatan = null;
                if (is_numeric($kecamatanRaw)) {
                    $kecamatan = $kecamatans->firstWhere('id', (int) $kecamatanRaw);
                }
                if (!$kecamatan && !empty($kecamatanRaw)) {
                    $cleanKec = strtolower(trim(preg_replace('/^kecamatan\s+/i', '', (string) $kecamatanRaw)));
                    $kecamatan = $kecamatans->first(function ($k) use ($cleanKec) {
                        return strtolower(trim($k->nama_kecamatan)) === $cleanKec;
                    });
                }

                if (!$kecamatan) {
                    $errors[] = [
                        'baris' => $rowNumber,
                        'judul' => $judul,
                        'pesan' => sprintf('Kecamatan "%s" tidak ditemukan dalam data master.', $kecamatanRaw ?? '-'),
                    ];
                    continue;
                }

                // RBAC: Restricted kecamatan check
                if ($user->id_kecamatan && (int) $kecamatan->id !== (int) $user->id_kecamatan) {
                    $errors[] = [
                        'baris' => $rowNumber,
                        'judul' => $judul,
                        'pesan' => sprintf('Anda tidak memiliki izin menginput proposal di luar wilayah kecamatan Anda (%s).', $kecamatan->nama_kecamatan),
                    ];
                    continue;
                }

                // Resolve Desa
                $desa = null;
                if (is_numeric($desaRaw)) {
                    $desa = $desas->first(fn ($d) => (int) $d->id === (int) $desaRaw && (int) $d->id_kecamatan === (int) $kecamatan->id);
                }
                if (!$desa && !empty($desaRaw)) {
                    $cleanDesa = strtolower(trim(preg_replace('/^desa\s+/i', '', (string) $desaRaw)));
                    $desa = $desas->first(function ($d) use ($cleanDesa, $kecamatan) {
                        return (int) $d->id_kecamatan === (int) $kecamatan->id && strtolower(trim($d->nama_desa)) === $cleanDesa;
                    });
                }

                if (!$desa) {
                    $errors[] = [
                        'baris' => $rowNumber,
                        'judul' => $judul,
                        'pesan' => sprintf('Desa "%s" tidak ditemukan di wilayah Kecamatan %s.', $desaRaw ?? '-', $kecamatan->nama_kecamatan),
                    ];
                    continue;
                }

                // Resolve Bidang secara cerdas (ID, Nama, Alias, Keyword, atau Inferensi Judul)
                $bidang = $this->resolveBidang($bidangRaw, $judul, $bidangs);

                if (!$bidang) {
                    $errors[] = [
                        'baris' => $rowNumber,
                        'judul' => $judul,
                        'pesan' => sprintf('Referensi bidang BKKD "%s" tidak dapat diidentifikasi.', $bidangRaw ?? '-'),
                    ];
                    continue;
                }

                // Format Nilai Usulan
                $nilaiUsulan = null;
                if ($nilaiRaw !== null && $nilaiRaw !== '') {
                    $cleaned = preg_replace('/[^\d.]/', '', str_replace(',', '.', (string) $nilaiRaw));
                    if (is_numeric($cleaned)) {
                        $nilaiUsulan = (float) $cleaned;
                    }
                }

                // Generator Nomor Registrasi
                $idDesa = (int) $desa->id;
                $kodeWilayah = $desa->id;

                $count = BkkdProposal::withTrashed()
                    ->where('tahun_anggaran', $tahun)
                    ->where('id_desa', $idDesa)
                    ->count();

                $nextUrut = $count + 1;
                $nomorUrut = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
                $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeWilayah, $nomorUrut);

                while (
                    BkkdProposal::withTrashed()->where('nomor_registrasi', $nomorRegistrasi)->exists() ||
                    in_array($nomorRegistrasi, $generatedBatchNumbers, true)
                ) {
                    $nextUrut++;
                    $nomorUrut = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
                    $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeWilayah, $nomorUrut);
                }

                $generatedBatchNumbers[] = $nomorRegistrasi;

                $proposal = BkkdProposal::create([
                    'nomor_registrasi' => $nomorRegistrasi,
                    'tahun_anggaran' => $tahun,
                    'id_kecamatan' => (int) $kecamatan->id,
                    'id_desa' => (int) $desa->id,
                    'id_bidang' => $bidang->id,
                    'judul_kegiatan' => $judul,
                    'uraian_kegiatan' => !empty($uraianRaw) ? trim((string) $uraianRaw) : null,
                    'lokasi_kegiatan' => !empty($lokasiRaw) ? trim((string) $lokasiRaw) : sprintf('Desa %s, Kec. %s', $desa->nama_desa, $kecamatan->nama_kecamatan),
                    'nilai_usulan' => $nilaiUsulan,
                    'versi' => 1,
                    'created_by' => $user->uuid,
                ]);

                // Audit history
                $this->riwayatService->catatAksiProposal(
                    proposal: $proposal,
                    aksi: 'created',
                    alasan: 'Import usulan proposal BKKD dari file Excel',
                    user: $user,
                    extraSesudah: [
                        'nomor_registrasi' => $proposal->nomor_registrasi,
                        'judul_kegiatan' => $proposal->judul_kegiatan,
                        'nilai_usulan' => $proposal->nilai_usulan,
                        'status_analisa_spasial' => $proposal->status_analisa_spasial ?? 'belum_dianalisa',
                    ]
                );

                $importedProposals[] = [
                    'id' => $proposal->id,
                    'nomor_registrasi' => $proposal->nomor_registrasi,
                    'judul_kegiatan' => $proposal->judul_kegiatan,
                    'desa' => $desa->nama_desa,
                    'kecamatan' => $kecamatan->nama_kecamatan,
                ];
            }
        });

        $totalSuccess = count($importedProposals);
        $totalFailed = count($errors);

        return response()->json([
            'ok' => true,
            'message' => sprintf('Import selesai: %d proposal berhasil ditambahkan, %d baris dilewati/gagal.', $totalSuccess, $totalFailed),
            'imported_count' => $totalSuccess,
            'failed_count' => $totalFailed,
            'data' => $importedProposals,
            'errors' => $errors,
        ]);
    }

    private function parseCsvFile(string $filePath): array
    {
        $rows = [];
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $rows;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return $rows;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = null;
        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            if (!$header) {
                if (count($data) === 1 && str_contains($data[0], ';')) {
                    $header = str_getcsv($data[0], ';');
                } else {
                    $header = $data;
                }
                continue;
            }

            if (count($data) === 1 && str_contains($data[0], ';')) {
                $data = str_getcsv($data[0], ';');
            }

            $row = [];
            foreach ($header as $idx => $key) {
                $cleanKey = trim($key);
                $row[$cleanKey] = isset($data[$idx]) ? trim($data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Resolusi cerdas referensi Bidang BKKD berdasarkan UUID, nama, kata kunci, alias, maupun inferensi dari judul kegiatan.
     */
    private function resolveBidang(?string $bidangRaw, ?string $judul, $bidangs): ?RefBkkdBidang
    {
        $bidangRawTrimmed = trim((string) $bidangRaw);
        $judulClean = strtolower(trim((string) $judul));

        // 1. Direct UUID Lookup
        if (!empty($bidangRawTrimmed) && Str::isUuid($bidangRawTrimmed)) {
            $found = $bidangs->firstWhere('id', $bidangRawTrimmed);
            if ($found) return $found;
        }

        // 2. Exact Name Match (Case-Insensitive & Whitespace Normalized)
        if (!empty($bidangRawTrimmed)) {
            $cleanInput = strtolower(preg_replace('/\s+/', ' ', $bidangRawTrimmed));
            $found = $bidangs->first(function ($b) use ($cleanInput) {
                return strtolower(trim($b->nama)) === $cleanInput;
            });
            if ($found) return $found;

            // 3. Normalized Match (menghapus awalan 'bkk' atau 'bidang' untuk mencocokkan)
            $withoutPrefix = trim(preg_replace('/^(bkk|bidang)\s+/i', '', $cleanInput));
            $found = $bidangs->first(function ($b) use ($cleanInput, $withoutPrefix) {
                $namaClean = strtolower(trim($b->nama));
                $namaWithoutPrefix = trim(preg_replace('/^(bkk|bidang)\s+/i', '', $namaClean));
                return $namaClean === $withoutPrefix 
                    || (!empty($withoutPrefix) && $namaWithoutPrefix === $withoutPrefix)
                    || str_contains($namaClean, $cleanInput)
                    || (!empty($withoutPrefix) && str_contains($cleanInput, $namaWithoutPrefix));
            });
            if ($found) return $found;
        }

        // 4. Domain Dictionary & Keyword Matching (Mencakup input bidang atau inferensi dari judul kegiatan)
        $textToAnalyze = strtolower($bidangRawTrimmed . ' ' . $judulClean);

        $keywordRules = [
            'BKK JEMBATAN' => ['jembatan', 'box culvert', 'culvert', 'plat beton', 'flyover', 'abutment', 'gelagar'],
            'BKK JALAN' => ['jalan', 'rabat', 'aspal', 'hotmix', 'paving', 'betonisasi', 'poros desa', 'pelebaran jalan'],
            'BKK TPT' => ['tpt', 'talud', 'penahan tanah', 'tembok penahan', 'bronjong', 'plengsengan', 'turap', 'tebing'],
            'Drainase & Sanitasi Lingkungan' => ['drainase', 'saluran air', 'sanitasi', 'parit', 'gorong-gorong', 'selokan', 'spal', 'peresapan'],
            'Sarana & Prasarana Kantor / Balai Desa' => ['balai desa', 'kantor desa', 'pendopo', 'gedung pertemuan', 'gedung serbaguna', 'pagar kantor', 'rehab kantor'],
            'Pemberdayaan Ekonomi & BUMDes' => ['bumdes', 'pasar desa', 'kios', 'wisata desa', 'pujasera', 'ekonomi desa', 'gudang pangan'],
            'Kesehatan Masyarakat & Posyandu' => ['posyandu', 'polindes', 'ponkesdes', 'pustu', 'kesehatan', 'stunting', 'mandi cuci kakus', 'mck'],
            'Jalan Usaha Tani & Ketahanan Pangan' => ['jut', 'jalan usaha tani', 'usaha tani', 'irigasi tersier', 'irigasi desa', 'pompanisasi', 'dam parit', 'embung'],
        ];

        foreach ($keywordRules as $targetNama => $keywords) {
            foreach ($keywords as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $textToAnalyze) || str_contains($textToAnalyze, $kw)) {
                    $found = $bidangs->first(function ($b) use ($targetNama) {
                        return strtolower(trim($b->nama)) === strtolower($targetNama);
                    });
                    if ($found) return $found;
                }
            }
        }

        // 5. Fallback ke BKKD JALAN atau bidang pertama jika tidak terdeteksi
        return $bidangs->firstWhere('nama', 'BKKD JALAN') ?? $bidangs->first();
    }
}

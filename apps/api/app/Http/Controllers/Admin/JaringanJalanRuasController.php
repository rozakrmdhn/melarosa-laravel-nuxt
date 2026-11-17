<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BkkdProposalLokasiResource;
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
use InvalidArgumentException;

class JaringanJalanRuasController extends Controller
{
    public function __construct(
        protected BkkdSpatialAnalysisService $spatialService,
        protected BkkdRiwayatService $riwayatService
    ) {}

    /**
     * Daftar Master Jalan Poros & Desa terpadu beserta GeoJSON.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $query = JalanPorosDesa::withGeoJson()
            ->with([
                'kecamatanModel:id,nama_kecamatan',
                'desaModel:id,nama_desa',
                'sumberProposal:id,nomor_registrasi,tahun_anggaran,judul_kegiatan',
            ])
            ->forUser($user);

        if ($request->filled('hierarki_jalan')) {
            $hierarkiFilter = (string) $request->input('hierarki_jalan');
            if ($hierarkiFilter === JalanPorosDesa::HIERARKI_POROS_DESA) {
                $query->where(function ($q) {
                    $q->where('hierarki_jalan', JalanPorosDesa::HIERARKI_POROS_DESA)
                        ->orWhereNull('hierarki_jalan');
                });
            } elseif (in_array($hierarkiFilter, ['kabupaten', JalanPorosDesa::HIERARKI_KABUPATEN_USULAN], true)) {
                $query->whereIn('hierarki_jalan', ['kabupaten', JalanPorosDesa::HIERARKI_KABUPATEN_USULAN]);
            } else {
                $query->where('hierarki_jalan', $hierarkiFilter);
            }
        }
        if ($request->filled('sumber_data')) {
            $query->where('sumber_data', (string) $request->input('sumber_data'));
        }
        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', (int) $request->input('id_kecamatan'));
        }
        if ($request->filled('id_desa')) {
            $query->where('id_desa', (int) $request->input('id_desa'));
        }
        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $query->where(function ($q) use ($term, $isPgsql) {
                if ($isPgsql) {
                    $q->where('nama_ruas', 'ilike', $term)
                        ->orWhereRaw('CAST(kode_ruas AS TEXT) ILIKE ?', [$term]);
                } else {
                    $q->where('nama_ruas', 'like', $term)
                        ->orWhere('kode_ruas', 'like', $term);
                }
            });
        }

        $statsQuery = (clone $query)->reorder()->toBase();
        $statsQuery->columns = null;
        $statsRow = $statsQuery->selectRaw("
            COUNT(*) as total_ruas,
            COALESCE(SUM(panjang), 0) as total_panjang_m,
            COUNT(CASE WHEN COALESCE(hierarki_jalan, 'poros_desa') = 'poros_desa' THEN 1 END) as jumlah_poros_desa,
            COUNT(CASE WHEN hierarki_jalan = 'lingkungan' THEN 1 END) as jumlah_lingkungan,
            COUNT(CASE WHEN hierarki_jalan = 'usaha_tani' THEN 1 END) as jumlah_usaha_tani,
            COUNT(CASE WHEN hierarki_jalan IN ('kabupaten', 'kabupaten_usulan') THEN 1 END) as jumlah_kabupaten,
            COUNT(CASE WHEN sumber_data = 'promosi_proposal' THEN 1 END) as jumlah_promosi_proposal
        ")->first();

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $items = $query
            ->orderByRaw('(validated_at IS NULL) ASC')
            ->orderByDesc('validated_at')
            ->orderBy('kode_ruas')
            ->paginate($perPage);

        $features = collect($items->items())->map(fn (JalanPorosDesa $ruas) => $this->toMasterFeature($ruas))->values();

        return response()->json([
            'ok' => true,
            'data' => $features,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
            'summary' => [
                'total_ruas' => (int) ($statsRow->total_ruas ?? 0),
                'total_panjang_m' => round((float) ($statsRow->total_panjang_m ?? 0), 2),
                'jumlah_poros_desa' => (int) ($statsRow->jumlah_poros_desa ?? 0),
                'jumlah_lingkungan' => (int) ($statsRow->jumlah_lingkungan ?? 0),
                'jumlah_usaha_tani' => (int) ($statsRow->jumlah_usaha_tani ?? 0),
                'jumlah_kabupaten' => (int) ($statsRow->jumlah_kabupaten ?? 0),
                'jumlah_promosi_proposal' => (int) ($statsRow->jumlah_promosi_proposal ?? 0),
            ],
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Antrean kandidat ruas jalan dari lokasi proposal BKKD untuk validasi Bappeda / Admin.
     */
    public function kandidat(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $statusFilter = trim((string) $request->input('status_promosi', ''));

        $query = BkkdProposalLokasi::withGeoJson()
            ->with([
                'proposal:id,nomor_registrasi,tahun_anggaran,tagging_usulan,judul_kegiatan,id_kecamatan,id_desa',
                'proposal.kecamatan:id,nama_kecamatan',
                'proposal.desa:id,nama_desa',
                'jalanPoros:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data',
                'jaringanRuas:id,kode_ruas,nama_ruas,hierarki_jalan,sumber_data',
            ])
            ->whereHas('proposal', function ($q) use ($user, $request) {
                $q->whereNull('deleted_at')->forUser($user);
                if ($request->filled('id_kecamatan')) {
                    $q->where('id_kecamatan', (int) $request->input('id_kecamatan'));
                }
                if ($request->filled('id_desa')) {
                    $q->where('id_desa', (int) $request->input('id_desa'));
                }
                if ($request->filled('tahun_anggaran')) {
                    $q->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
                }
            });

        if ($statusFilter !== '' && $statusFilter !== 'semua') {
            $query->where('status_promosi_master', $statusFilter);
        } else {
            $query->whereIn('status_promosi_master', [
                BkkdProposalLokasi::PROMOSI_DIAJUKAN,
                BkkdProposalLokasi::PROMOSI_KANDIDAT,
                BkkdProposalLokasi::PROMOSI_DISETUJUI,
                BkkdProposalLokasi::PROMOSI_DITOLAK,
            ]);
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $op) {
                $q->where('nama_lokasi', $op, $term)
                    ->orWhere('nama_ruas_usulan', $op, $term)
                    ->orWhereHas('proposal', function ($pq) use ($term, $op) {
                        $pq->where('judul_kegiatan', $op, $term)
                            ->orWhere('nomor_registrasi', $op, $term);
                    });
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $items = $query
            ->orderByRaw("CASE WHEN status_promosi_master = 'diajukan' THEN 0 WHEN status_promosi_master = 'kandidat' THEN 1 WHEN status_promosi_master = 'ditolak' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        $rows = collect($items->items())->map(function (BkkdProposalLokasi $lok) {
            $resource = (new BkkdProposalLokasiResource($lok))->resolve();
            $resource['proposal'] = $lok->proposal ? [
                'id' => $lok->proposal->id,
                'nomor_registrasi' => $lok->proposal->nomor_registrasi,
                'tahun_anggaran' => (int) $lok->proposal->tahun_anggaran,
                'tagging_usulan' => $lok->proposal->tagging_usulan ?? 'PRKPD',
                'judul_kegiatan' => $lok->proposal->judul_kegiatan,
                'id_kecamatan' => $lok->proposal->id_kecamatan,
                'nama_kecamatan' => $lok->proposal->kecamatan?->nama_kecamatan,
                'id_desa' => $lok->proposal->id_desa,
                'nama_desa' => $lok->proposal->desa?->nama_desa,
            ] : null;
            return $resource;
        })->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Operator / pengusul mengajukan lokasi proposal sebagai kandidat penambahan Master Jaringan Jalan.
     */
    public function ajukan(Request $request, string $id, string $lokasiId): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $lokasi = BkkdProposalLokasi::where('id_proposal', $proposal->id)
            ->where('id', $lokasiId)
            ->withGeoJson()
            ->firstOrFail();

        if ($lokasi->status_promosi_master === BkkdProposalLokasi::PROMOSI_DISETUJUI && !empty($lokasi->id_jaringan_ruas)) {
            return response()->json([
                'ok' => false,
                'message' => 'Lokasi ini sudah tervalidasi dan masuk ke Master Jaringan Jalan.',
            ], 422);
        }

        $validated = $request->validate([
            'nama_ruas_usulan' => ['nullable', 'string', 'max:255'],
            'hierarki_jalan_usulan' => ['nullable', 'string', 'in:kabupaten,poros_desa,lingkungan,usaha_tani'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        // Pastikan analisa spasial 3 lapis terbaru tersedia sebelum validasi promosi
        $this->spatialService->analisaLokasi($lokasi);
        $lokasi->refresh();

        $check = $this->spatialService->validasiKandidatJaringanJalan($lokasi, $proposal);
        $lokasi->validasi_promosi_spasial = $check;

        if (!$check['lolos_validasi']) {
            $lokasi->status_promosi_master = BkkdProposalLokasi::PROMOSI_TIDAK_PERLU;
            $lokasi->save();

            $firstFail = collect($check['checks'])->firstWhere('lolos', false);

            return response()->json([
                'ok' => false,
                'message' => $firstFail['detail'] ?? 'Lokasi belum memenuhi syarat validasi spasial untuk masuk Master Jaringan Jalan.',
                'validasi_promosi_spasial' => $check,
            ], 422);
        }

        $user = $request->user();
        $namaRuas = trim((string) ($validated['nama_ruas_usulan'] ?? $lokasi->nama_ruas_usulan ?? $lokasi->nama_lokasi));
        $hierarki = (string) ($validated['hierarki_jalan_usulan'] ?? $lokasi->hierarki_jalan_usulan ?? JalanPorosDesa::HIERARKI_LINGKUNGAN);

        DB::transaction(function () use ($proposal, $lokasi, $user, $namaRuas, $hierarki, $validated) {
            $lokasi->nama_ruas_usulan = $namaRuas;
            $lokasi->hierarki_jalan_usulan = $hierarki;
            $lokasi->status_promosi_master = BkkdProposalLokasi::PROMOSI_DIAJUKAN;
            $lokasi->catatan_promosi_master = $validated['catatan'] ?? $lokasi->catatan_promosi_master;
            $lokasi->diajukan_promosi_oleh = $user->uuid;
            $lokasi->diajukan_promosi_pada = now();
            $lokasi->save();

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'updated',
                alasan: sprintf('Mengajukan lokasi "%s" ke antrean validasi Master Jaringan Jalan (%s)', $namaRuas, $hierarki),
                user: $user,
                extraSesudah: [
                    'id_lokasi' => $lokasi->id,
                    'status_promosi_master' => BkkdProposalLokasi::PROMOSI_DIAJUKAN,
                    'nama_ruas_usulan' => $namaRuas,
                    'hierarki_jalan_usulan' => $hierarki,
                ]
            );
        });

        $freshLokasi = BkkdProposalLokasi::withGeoJson()->with(['jalanPoros', 'jaringanRuas'])->findOrFail($lokasi->id);

        return response()->json([
            'ok' => true,
            'message' => 'Lokasi memenuhi syarat spasial dan diajukan ke antrean validasi Master Jaringan Jalan.',
            'data' => new BkkdProposalLokasiResource($freshLokasi),
        ]);
    }

    /**
     * Verifikator Bappeda / Admin memvalidasi & mempromosikan kandidat lokasi ke Master Jaringan Jalan.
     */
    public function validasi(Request $request, string $id, string $lokasiId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('verifierBappeda'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya Admin atau Verifikator Bappeda yang berwenang memvalidasi penambahan Master Jaringan Jalan.',
            ], 403);
        }

        $proposal = BkkdProposal::findOrFail($id);
        $lokasi = BkkdProposalLokasi::where('id_proposal', $proposal->id)
            ->where('id', $lokasiId)
            ->withGeoJson()
            ->firstOrFail();

        $validated = $request->validate([
            'keputusan' => ['required', 'string', 'in:setujui_baru,ekstensi_potong_unik,tolak'],
            'nama_ruas' => ['nullable', 'string', 'max:255'],
            'kode_ruas' => ['nullable', 'integer', 'min:1'],
            'hierarki_jalan' => ['nullable', 'string', 'in:kabupaten,kabupaten_usulan,poros_desa,lingkungan,usaha_tani'],
            'lebar_m' => ['nullable', 'numeric', 'min:0'],
            'perkerasan' => ['nullable', 'string', 'max:80'],
            'kondisi' => ['nullable', 'string', 'max:60'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = DB::transaction(function () use ($proposal, $lokasi, $user, $validated) {
                $res = $this->spatialService->promosikanLokasiKeMasterJaringanJalan($lokasi, $user, $validated);

                $this->riwayatService->catatAksiProposal(
                    proposal: $proposal,
                    aksi: 'updated',
                    alasan: $validated['keputusan'] === 'tolak'
                        ? sprintf('Menolak promosi lokasi "%s" ke Master Jaringan Jalan', $lokasi->nama_lokasi)
                        : sprintf('Memvalidasi & menambahkan ruas "%s" (%s) ke Master Jaringan Jalan Bojonegoro', $res['jaringan_ruas']?->nama_ruas, $res['jaringan_ruas']?->kode_ruas),
                    user: $user,
                    extraSesudah: [
                        'id_lokasi' => $lokasi->id,
                        'keputusan' => $validated['keputusan'],
                        'id_jaringan_ruas' => $res['jaringan_ruas']?->id,
                        'kode_ruas' => $res['jaringan_ruas']?->kode_ruas,
                    ]
                );

                return $res;
            });
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $freshLokasi = BkkdProposalLokasi::withGeoJson()->with(['jalanPoros', 'jaringanRuas'])->findOrFail($lokasi->id);

        return response()->json([
            'ok' => true,
            'message' => $validated['keputusan'] === 'tolak'
                ? 'Promosi ruas ditolak.'
                : 'Ruas jalan berhasil divalidasi dan disatukan ke Master Jalan Poros & Desa.',
            'data' => new BkkdProposalLokasiResource($freshLokasi),
            'jaringan_ruas' => $result['jaringan_ruas'] ? $this->toMasterFeature($result['jaringan_ruas']) : null,
        ]);
    }

    /**
     * Perbarui atribut ruas pada Master Jalan Poros & Desa (Admin / Verifikator Bappeda).
     */
    public function update(Request $request, string $ruasId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('verifierBappeda'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya Admin atau Verifikator Bappeda yang dapat mengubah Master Jaringan Jalan.',
            ], 403);
        }

        $ruas = JalanPorosDesa::findOrFail($ruasId);

        $validated = $request->validate([
            'kode_ruas' => ['nullable', 'integer', 'min:1'],
            'nama_ruas' => ['required', 'string', 'max:255'],
            'hierarki_jalan' => ['required', 'string', 'in:kabupaten,kabupaten_usulan,poros_desa,lingkungan,usaha_tani'],
            'lebar_m' => ['nullable', 'numeric', 'min:0'],
            'perkerasan' => ['nullable', 'string', 'max:80'],
            'kondisi' => ['nullable', 'string', 'max:60'],
            'catatan_validasi' => ['nullable', 'string', 'max:1000'],
        ]);

        $ruas->update([
            'kode_ruas' => array_key_exists('kode_ruas', $validated) && $validated['kode_ruas'] !== null
                ? (int) $validated['kode_ruas']
                : $ruas->kode_ruas,
            'nama_ruas' => trim($validated['nama_ruas']),
            'hierarki_jalan' => $validated['hierarki_jalan'],
            'lebar' => array_key_exists('lebar_m', $validated) ? $validated['lebar_m'] : $ruas->lebar,
            'perkerasan' => $validated['perkerasan'] ?? $ruas->perkerasan,
            'kondisi' => $validated['kondisi'] ?? $ruas->kondisi,
            'validated_by' => $user->uuid,
            'validated_at' => now(),
        ]);

        $fresh = JalanPorosDesa::withGeoJson()
            ->with(['kecamatanModel:id,nama_kecamatan', 'desaModel:id,nama_desa', 'sumberProposal:id,nomor_registrasi,tahun_anggaran,judul_kegiatan'])
            ->findOrFail($ruas->id);

        return response()->json([
            'ok' => true,
            'message' => 'Data Master Jalan Poros & Desa berhasil diperbarui.',
            'data' => $this->toMasterFeature($fresh),
        ]);
    }

    /**
     * Hapus ruas hasil promosi dari Master Jalan Poros & Desa (Admin / Verifikator Bappeda).
     */
    public function destroy(Request $request, string $ruasId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('verifierBappeda'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya Admin atau Verifikator Bappeda yang dapat menghapus ruas Master Jaringan Jalan.',
            ], 403);
        }

        $ruas = JalanPorosDesa::findOrFail($ruasId);

        if (Schema::hasTable('infrastruktur_segmen') && InfrastrukturSegmen::where('parent_id', $ruas->id)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'Ruas ini sudah digunakan oleh data segmen infrastruktur dan tidak dapat dihapus langsung.',
            ], 422);
        }

        DB::transaction(function () use ($ruas) {
            BkkdProposalLokasi::where(function ($q) use ($ruas) {
                $q->where('id_jaringan_ruas', $ruas->id)
                    ->orWhere('id_jalan_poros', $ruas->id);
            })->update([
                'id_jalan_poros' => null,
                'id_jaringan_ruas' => null,
                'status_promosi_master' => BkkdProposalLokasi::PROMOSI_KANDIDAT,
            ]);
            $ruas->delete();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Ruas berhasil dihapus dari Master Jalan Poros & Desa.',
        ]);
    }

    /**
     * Konversi JalanPorosDesa ke bentuk GeoJSON Feature standar modul /admin/jaringan-jalan.
     */
    protected function toMasterFeature(JalanPorosDesa $ruas): array
    {
        $feature = $ruas->toGeoJsonFeature();
        $namaKec = $ruas->relationLoaded('kecamatanModel') ? $ruas->kecamatanModel?->nama_kecamatan : null;
        $namaDesa = $ruas->relationLoaded('desaModel') ? $ruas->desaModel?->nama_desa : null;

        $feature['properties'] = array_merge($feature['properties'], [
            'kode_ruas' => $ruas->kode_ruas !== null ? (string) $ruas->kode_ruas : null,
            'hierarki_jalan' => $ruas->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
            'nama_kecamatan' => $namaKec ?? $ruas->kecamatan,
            'nama_desa' => $namaDesa ?? $ruas->desa,
            'panjang_m' => round((float) ($ruas->panjang_meter ?? $ruas->panjang ?? 0), 2),
            'lebar_m' => $ruas->lebar !== null ? (float) $ruas->lebar : null,
            'sumber_data' => $ruas->sumber_data ?? JalanPorosDesa::SUMBER_SK_PEMDA,
            'status_validasi' => 'terverifikasi',
            'sumber_proposal' => $ruas->relationLoaded('sumberProposal') && $ruas->sumberProposal ? [
                'id' => $ruas->sumberProposal->id,
                'nomor_registrasi' => $ruas->sumberProposal->nomor_registrasi,
                'tahun_anggaran' => (int) $ruas->sumberProposal->tahun_anggaran,
                'judul_kegiatan' => $ruas->sumberProposal->judul_kegiatan,
            ] : null,
        ]);

        return $feature;
    }
}

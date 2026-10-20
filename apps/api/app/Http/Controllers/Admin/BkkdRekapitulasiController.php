<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TetapkanTapdRequest;
use App\Http\Resources\BkkdProposalResource;
use App\Models\BatasWilayahDesa;
use App\Models\BkkdProposal;
use App\Services\BkkdRiwayatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BkkdRekapitulasiController extends Controller
{
    public function __construct(
        protected BkkdRiwayatService $riwayatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $tahun = (int) $request->input('tahun_anggaran', now()->year);

        $query = BkkdProposal::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama',
            'verifikasiAktif.opd:id,kode,nama',
            'penetap:uuid,name',
        ])
        ->select([
            'id',
            'nomor_registrasi',
            'tahun_anggaran',
            'id_kecamatan',
            'id_desa',
            'id_bidang',
            'nomor_surat_desa',
            'tanggal_surat_desa',
            'tanggal_diterima',
            'judul_kegiatan',
            'uraian_kegiatan',
            'lokasi_kegiatan',
            'volume',
            'satuan',
            'nilai_usulan',
            'status',
            'catatan',
            'nilai_disetujui_tapd',
            'catatan_tapd',
            'tanggal_penetapan_tapd',
            'versi',
            'created_by',
            'updated_by',
            'ditetapkan_oleh',
            'created_at',
            'updated_at',
            'deleted_at',
        ])
        ->forUser($user)
        ->where('tahun_anggaran', $tahun);

        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', (int) $request->input('id_kecamatan'));
        }

        if ($request->filled('id_desa')) {
            $query->where('id_desa', (int) $request->input('id_desa'));
        }

        $allProposals = $query->orderBy('id_kecamatan')->orderBy('id_desa')->orderBy('created_at')->get();

        // Group by desa
        $groupedByDesa = $allProposals->groupBy('id_desa');

        $desaList = [];
        $totalDesaPengusul = $groupedByDesa->count();
        $totalUsulanMasuk = $allProposals->count();
        $desaTerakomodirTapd = 0;
        $totalPaguRancanganApbd = 0;

        foreach ($groupedByDesa as $idDesa => $proposals) {
            $first = $proposals->first();
            $usulanTerpilih = $proposals->firstWhere('status', 'ditetapkan_tapd');

            if ($usulanTerpilih) {
                $desaTerakomodirTapd++;
                $totalPaguRancanganApbd += (float) ($usulanTerpilih->nilai_disetujui_tapd ?? $usulanTerpilih->nilai_usulan);
            }

            $desaItem = [
                'id_desa' => $idDesa,
                'nama_desa' => $first->desa?->nama_desa ?? ('Desa #' . $idDesa),
                'id_kecamatan' => $first->id_kecamatan,
                'nama_kecamatan' => $first->kecamatan?->nama_kecamatan ?? '',
                'jumlah_usulan' => $proposals->count(),
                'status_pemilihan' => $usulanTerpilih ? 'terpilih' : 'belum_dipilih',
                'usulan_terpilih_id' => $usulanTerpilih?->id,
                'pagu_disetujui_tapd' => $usulanTerpilih ? (float) $usulanTerpilih->nilai_disetujui_tapd : null,
                'proposals' => BkkdProposalResource::collection($proposals),
            ];

            // Filter status_pemilihan
            $filterPemilihan = $request->input('status_pemilihan');
            if ($filterPemilihan === 'sudah_terpilih' && !$usulanTerpilih) {
                continue;
            }
            if ($filterPemilihan === 'belum_dipilih' && $usulanTerpilih) {
                continue;
            }

            $desaList[] = $desaItem;
        }

        return response()->json([
            'ok' => true,
            'tahun_anggaran' => $tahun,
            'summary' => [
                'total_desa_pengusul' => $totalDesaPengusul,
                'total_usulan_masuk' => $totalUsulanMasuk,
                'desa_terakomodir_tapd' => $desaTerakomodirTapd,
                'total_pagu_rancangan_apbd' => $totalPaguRancanganApbd,
            ],
            'data' => $desaList,
        ]);
    }

    public function tetapkanTapd(TetapkanTapdRequest $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::with('desa')->findOrFail($id);
        Gate::authorize('update', $proposal);

        if (!in_array($proposal->status, ['layak', 'layak_bersyarat', 'tidak_terpilih', 'ditetapkan_tapd'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya usulan dengan hasil verifikasi layak atau bersyarat yang dapat dipilih TAPD.',
            ], 422);
        }

        $user = $request->user();
        $nilaiPagu = (float) $request->input('nilai_disetujui_tapd');
        $catatanTapd = $request->input('catatan_tapd');
        $tglPenetapan = $request->input('tanggal_penetapan_tapd', now()->toDateString());

        DB::transaction(function () use ($proposal, $nilaiPagu, $catatanTapd, $tglPenetapan, $user) {
            // 1. Reset proposal lain di desa dan tahun anggaran sama yang berstatus ditetapkan_tapd
            $usulanLain = BkkdProposal::where('tahun_anggaran', $proposal->tahun_anggaran)
                ->where('id_desa', $proposal->id_desa)
                ->where('status', 'ditetapkan_tapd')
                ->where('id', '!=', $proposal->id)
                ->get();

            foreach ($usulanLain as $item) {
                $statusSebelumnya = $item->status;
                $item->update([
                    'status' => 'tidak_terpilih',
                    'nilai_disetujui_tapd' => null,
                    'updated_by' => $user->uuid,
                ]);

                $this->riwayatService->catatAksiProposal(
                    proposal: $item,
                    aksi: 'status_changed',
                    alasan: sprintf('Status dialihkan menjadi tidak terpilih karena TAPD memilih usulan nomor "%s"', $proposal->nomor_registrasi),
                    user: $user,
                    extraSebelum: ['status' => $statusSebelumnya],
                    extraSesudah: ['status' => 'tidak_terpilih']
                );
            }

            // 2. Set proposal terpilih
            $statusSebelum = $proposal->status;
            $proposal->update([
                'status' => 'ditetapkan_tapd',
                'nilai_disetujui_tapd' => $nilaiPagu,
                'catatan_tapd' => $catatanTapd,
                'tanggal_penetapan_tapd' => $tglPenetapan,
                'ditetapkan_oleh' => $user->uuid,
                'updated_by' => $user->uuid,
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'ditetapkan_tapd',
                alasan: 'Ditetapkan oleh TAPD masuk ke dalam Rancangan APBD dengan penyesuaian pagu anggaran',
                user: $user,
                extraSebelum: [
                    'status' => $statusSebelum,
                    'nilai_disetujui_tapd' => $proposal->getOriginal('nilai_disetujui_tapd'),
                ],
                extraSesudah: [
                    'status' => 'ditetapkan_tapd',
                    'nilai_disetujui_tapd' => $nilaiPagu,
                    'catatan_tapd' => $catatanTapd,
                ]
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Usulan berhasil ditetapkan TAPD masuk Rancangan APBD.',
            'data' => new BkkdProposalResource($proposal->fresh()),
        ]);
    }

    public function batalkanTapd(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        if ($proposal->status !== 'ditetapkan_tapd') {
            return response()->json([
                'ok' => false,
                'message' => 'Usulan ini belum ditetapkan oleh TAPD.',
            ], 422);
        }

        $user = $request->user();
        DB::transaction(function () use ($proposal, $user) {
            $verifikasiAktif = $proposal->verifikasiAktif;
            $statusKembali = $verifikasiAktif ? $verifikasiAktif->hasil : 'layak';

            $proposal->update([
                'status' => $statusKembali,
                'nilai_disetujui_tapd' => null,
                'catatan_tapd' => null,
                'tanggal_penetapan_tapd' => null,
                'ditetapkan_oleh' => null,
                'updated_by' => $user->uuid,
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'status_changed',
                alasan: 'Penetapan TAPD dibatalkan, status dikembalikan mengikuti hasil verifikasi OPD',
                user: $user,
                extraSebelum: ['status' => 'ditetapkan_tapd'],
                extraSesudah: ['status' => $statusKembali]
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Penetapan TAPD berhasil dibatalkan.',
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        return $this->export($request);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $tahun = (int) $request->input('tahun_anggaran', now()->year);

        $query = BkkdProposal::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama',
            'verifikasiAktif.opd:id,kode,nama',
            'penetap:uuid,name',
        ])
        ->forUser($user)
        ->where('tahun_anggaran', $tahun)
        ->orderBy('id_kecamatan')
        ->orderBy('id_desa');

        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', (int) $request->input('id_kecamatan'));
        }

        $proposals = $query->get();

        $filename = sprintf('Rekapitulasi_BKKD_%s_%s.csv', $tahun, date('Ymd_His'));

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($proposals) {
            $handle = fopen('php://output', 'w');
            // Byte Order Mark (BOM) for Excel UTF-8 recognition
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header kolom
            fputcsv($handle, [
                'No',
                'Kecamatan',
                'Desa',
                'Nomor Registrasi',
                'Judul Kegiatan',
                'Bidang',
                'Pagu Usulan Desa (Rp)',
                'OPD Pemeriksa',
                'Hasil Telaah OPD',
                'Pagu Rekomendasi OPD (Rp)',
                'Pagu Disetujui TAPD (Rp)',
                'Status Akhir',
                'Catatan TAPD',
                'Tanggal Penetapan TAPD',
            ]);

            $no = 1;
            foreach ($proposals as $p) {
                $verifikasi = $p->verifikasiAktif;
                fputcsv($handle, [
                    $no++,
                    $p->kecamatan?->nama_kecamatan ?? '',
                    $p->desa?->nama_desa ?? '',
                    $p->nomor_registrasi,
                    $p->judul_kegiatan,
                    $p->bidang?->nama ?? '',
                    $p->nilai_usulan,
                    $verifikasi?->opd?->nama ?? '-',
                    $verifikasi?->hasil ?? '-',
                    $verifikasi?->nilai_rekomendasi ?? 0,
                    $p->nilai_disetujui_tapd ?? 0,
                    $p->status,
                    $p->catatan_tapd ?? '',
                    $p->tanggal_penetapan_tapd?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

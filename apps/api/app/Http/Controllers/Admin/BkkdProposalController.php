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
use App\Models\BkkdProposalLampiran;
use App\Models\BkkdProposalRiwayat;
use App\Models\BkkdVerifikasiRiwayat;
use App\Models\RefBkkdBidang;
use App\Models\TemporaryUpload;
use App\Services\BkkdRiwayatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BkkdProposalController extends Controller
{
    public function __construct(
        protected BkkdRiwayatService $riwayatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $query = BkkdProposal::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama,id_opd_default',
            'verifikasiAktif.opd:id,kode,nama',
            'lampiran:id,id_proposal,tipe_sumber,url_link,nama_asli,jenis_dokumen',
        ])
        ->withCount([
            'lampiran as total_lampiran',
            'lampiran as total_lampiran_link' => function ($q) {
                $q->where(function ($sub) {
                    $sub->where('tipe_sumber', 'link')
                        ->orWhereNotNull('url_link');
                });
            },
            'lampiran as total_lampiran_file' => function ($q) {
                $q->where(function ($sub) {
                    $sub->where('tipe_sumber', 'file')
                        ->whereNull('url_link');
                });
            },
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
        ->forUser($user);

        // Filter tahun anggaran
        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
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

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter kelengkapan lampiran / cloud
        if ($request->filled('kelengkapan_berkas')) {
            $kelengkapan = $request->input('kelengkapan_berkas');
            if ($kelengkapan === 'belum_upload') {
                $query->whereDoesntHave('lampiran');
            } elseif ($kelengkapan === 'sudah_upload') {
                $query->whereHas('lampiran');
            } elseif ($kelengkapan === 'ada_cloud') {
                $query->whereHas('lampiran', function ($q) {
                    $q->where('tipe_sumber', 'link')->orWhereNotNull('url_link');
                });
            } elseif ($kelengkapan === 'ada_file') {
                $query->whereHas('lampiran', function ($q) {
                    $q->where('tipe_sumber', 'file')->whereNull('url_link');
                });
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('judul_kegiatan', 'ilike', $search)
                  ->orWhere('nomor_registrasi', 'ilike', $search)
                  ->orWhere('nomor_surat_desa', 'ilike', $search)
                  ->orWhere('lokasi_kegiatan', 'ilike', $search);
            });
        }

        // Metrics summary (dari filter aktif) dihitung dalam 1 query SQL efisien
        $summaryQuery = (clone $query)->reorder()->toBase();
        $summaryQuery->columns = null;
        $summaryStats = $summaryQuery->selectRaw("
            COUNT(*) as total_proposal,
            COALESCE(SUM(nilai_usulan), 0) as total_nilai_usulan,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as jumlah_draft,
            COUNT(CASE WHEN status = 'diajukan_opd' THEN 1 END) as jumlah_diajukan,
            COUNT(CASE WHEN status IN ('layak', 'layak_bersyarat') THEN 1 END) as jumlah_layak,
            COUNT(CASE WHEN status = 'tidak_layak' THEN 1 END) as jumlah_tidak_layak,
            COUNT(CASE WHEN status = 'ditetapkan_tapd' THEN 1 END) as jumlah_ditetapkan,
            COUNT(CASE WHEN NOT EXISTS (SELECT 1 FROM bkkd_proposal_lampiran WHERE bkkd_proposal_lampiran.id_proposal = bkkd_proposal.id) THEN 1 END) as jumlah_belum_upload
        ")->first();

        $summary = [
            'total_proposal' => (int) ($summaryStats->total_proposal ?? 0),
            'total_nilai_usulan' => (float) ($summaryStats->total_nilai_usulan ?? 0),
            'jumlah_draft' => (int) ($summaryStats->jumlah_draft ?? 0),
            'jumlah_diajukan' => (int) ($summaryStats->jumlah_diajukan ?? 0),
            'jumlah_layak' => (int) ($summaryStats->jumlah_layak ?? 0),
            'jumlah_tidak_layak' => (int) ($summaryStats->jumlah_tidak_layak ?? 0),
            'jumlah_ditetapkan' => (int) ($summaryStats->jumlah_ditetapkan ?? 0),
            'belum_upload' => (int) ($summaryStats->jumlah_belum_upload ?? 0),
            'jumlah_belum_upload' => (int) ($summaryStats->jumlah_belum_upload ?? 0),
        ];

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'nomor_registrasi', 'nilai_usulan', 'tahun_anggaran', 'status'];

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
        if (empty($validated['tanggal_diterima'])) {
            $validated['tanggal_diterima'] = now()->toDateString();
        }
        if (!isset($validated['volume']) && isset($validated['volume_target'])) {
            $validated['volume'] = $validated['volume_target'];
        }
        if (!isset($validated['satuan']) && isset($validated['satuan_target'])) {
            $validated['satuan'] = $validated['satuan_target'];
        }
        unset($validated['volume_target'], $validated['satuan_target']);

        $tahun = (int) $validated['tahun_anggaran'];
        $idDesa = (int) $validated['id_desa'];

        $proposal = DB::transaction(function () use ($validated, $tahun, $idDesa, $user) {
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

            $lampiranData = $validated['lampiran'] ?? [];
            unset($validated['lampiran']);

            if (isset($validated['latitude'], $validated['longitude']) && is_numeric($validated['latitude']) && is_numeric($validated['longitude'])) {
                $lat = (float) $validated['latitude'];
                $lng = (float) $validated['longitude'];
                $validated['geom'] = DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)");
            }
            unset($validated['latitude'], $validated['longitude']);

            $proposal = BkkdProposal::create(array_merge($validated, [
                'nomor_registrasi' => $nomorRegistrasi,
                'status' => 'draft',
                'versi' => 1,
                'created_by' => $user->uuid,
            ]));

            // Pindahkan file lampiran dari temporary ke folder permanen proposal
            if (!empty($lampiranData)) {
                foreach ($lampiranData as $item) {
                    $tempPath = $item['path'];
                    if (Storage::disk('local')->exists($tempPath)) {
                        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                        $permanenPath = sprintf('bkkd/%s/%s/%s.%s', $tahun, $proposal->id, Str::uuid7(), $extension);

                        Storage::disk('local')->move($tempPath, $permanenPath);
                        TemporaryUpload::where('path', $tempPath)->delete();

                        BkkdProposalLampiran::create([
                            'id_proposal' => $proposal->id,
                            'jenis_dokumen' => $item['jenis_dokumen'] ?? 'lainnya',
                            'nama_asli' => $item['nama_asli'] ?? basename($tempPath),
                            'path' => $permanenPath,
                            'mime_type' => $item['mime_type'] ?? Storage::disk('local')->mimeType($permanenPath) ?? 'application/octet-stream',
                            'ukuran_byte' => $item['ukuran_byte'] ?? Storage::disk('local')->size($permanenPath) ?? 0,
                            'uploaded_by' => $user->uuid,
                            'created_at' => now(),
                        ]);
                    }
                }
            }

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
                    'status' => $proposal->status,
                ]
            );

            return $proposal;
        });

        $proposal->load([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama',
            'lampiran.uploader:uuid,name',
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
            'proposalInduk.bidang:id,nama',
            'proposalPengganti.bidang:id,nama',
            'lampiran.uploader:uuid,name',
            'verifikasi.opd:id,kode,nama',
            'verifikasi.verifikator:uuid,name',
            'verifikasi.updater:uuid,name',
            'verifikasiAktif.opd:id,kode,nama',
            'creator:uuid,name',
            'updater:uuid,name',
            'penetap:uuid,name',
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
        if (empty($validated['tanggal_diterima'])) {
            $validated['tanggal_diterima'] = $proposal->tanggal_diterima?->format('Y-m-d') ?? now()->toDateString();
        }
        if (!isset($validated['volume']) && isset($validated['volume_target'])) {
            $validated['volume'] = $validated['volume_target'];
        }
        if (!isset($validated['satuan']) && isset($validated['satuan_target'])) {
            $validated['satuan'] = $validated['satuan_target'];
        }
        unset($validated['volume_target'], $validated['satuan_target']);

        $versiKlien = (int) $validated['versi'];
        $alasan = $validated['alasan_perubahan'] ?? null;

        unset($validated['versi'], $validated['alasan_perubahan']);

        if (isset($validated['latitude'], $validated['longitude']) && is_numeric($validated['latitude']) && is_numeric($validated['longitude'])) {
            $lat = (float) $validated['latitude'];
            $lng = (float) $validated['longitude'];
            $validated['geom'] = DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)");
        }
        unset($validated['latitude'], $validated['longitude']);

        $updatedProposal = DB::transaction(function () use ($proposal, $validated, $versiKlien, $alasan, $user) {
            return $this->riwayatService->catatPerubahanProposal(
                proposal: $proposal,
                dataBaru: $validated,
                versiKlien: $versiKlien,
                alasan: $alasan,
                user: $user,
                aksi: 'updated'
            );
        });

        $updatedProposal->load([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'bidang:id,nama,id_opd_default',
            'lampiran.uploader:uuid,name',
            'verifikasi.opd:id,kode,nama',
            'verifikasiAktif.opd:id,kode,nama',
            'creator:uuid,name',
            'updater:uuid,name',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Data proposal usulan BKKD berhasil diperbarui.',
            'data' => new BkkdProposalDetailResource($updatedProposal),
        ]);
    }

    public function ajukan(Request $request, string $id): JsonResponse
    {
        return $this->ajukanOpd($request, $id);
    }

    public function ajukanOpd(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        if (!in_array($proposal->status, ['draft', 'dikembalikan'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya proposal berstatus Draft atau Dikembalikan yang dapat diajukan ke OPD.',
            ], 422);
        }

        $user = $request->user();
        $statusSebelum = $proposal->status;

        DB::transaction(function () use ($proposal, $user, $statusSebelum) {
            $proposal->update([
                'status' => 'diajukan_opd',
                'updated_by' => $user->uuid,
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'status_changed',
                alasan: 'Berkas proposal diajukan ke OPD teknis untuk telaah verifikasi',
                user: $user,
                extraSebelum: ['status' => $statusSebelum],
                extraSesudah: ['status' => 'diajukan_opd']
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Proposal berhasil diajukan ke OPD teknis.',
            'status' => 'diajukan_opd',
        ]);
    }

    public function batalkan(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $alasan = $request->input('alasan') ?? $request->input('alasan_pembatalan');
        if (empty(trim((string) $alasan))) {
            return response()->json([
                'ok' => false,
                'message' => 'Alasan pembatalan proposal wajib diisi.',
                'errors' => ['alasan' => ['Alasan pembatalan proposal wajib diisi.']],
            ], 422);
        }

        $user = $request->user();
        $statusSebelum = $proposal->status;

        DB::transaction(function () use ($proposal, $user, $statusSebelum, $alasan) {
            $proposal->update([
                'status' => 'dibatalkan',
                'updated_by' => $user->uuid,
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'status_changed',
                alasan: $alasan,
                user: $user,
                extraSebelum: ['status' => $statusSebelum],
                extraSesudah: ['status' => 'dibatalkan']
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Proposal usulan BKKD telah dibatalkan.',
            'status' => 'dibatalkan',
        ]);
    }

    public function gantiPapbd(Request $request, string $id): JsonResponse
    {
        $proposalAwal = BkkdProposal::with(['desa', 'kecamatan', 'bidang'])->findOrFail($id);
        Gate::authorize('update', $proposalAwal);

        $request->validate([
            'alasan_pergantian_papbd' => ['required', 'string', 'max:1000'],
            'id_bidang' => ['required', 'uuid', 'exists:ref_bkkd_bidang,id'],
            'judul_kegiatan' => ['required', 'string', 'max:255'],
            'uraian_kegiatan' => ['nullable', 'string'],
            'lokasi_kegiatan' => ['nullable', 'string'],
            'panjang' => ['nullable', 'numeric', 'min:0'],
            'lebar' => ['nullable', 'numeric', 'min:0'],
            'tinggi' => ['nullable', 'numeric', 'min:0'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:30'],
            'nilai_usulan' => ['nullable', 'numeric', 'min:0'],
            'nomor_surat_desa' => ['required', 'string', 'max:150'],
            'tanggal_surat_desa' => ['required', 'date'],
        ]);

        $user = $request->user();
        $alasan = $request->input('alasan_pergantian_papbd');

        $tahun = (int) $proposalAwal->tahun_anggaran;
        $idDesa = (int) $proposalAwal->id_desa;
        $kodeWilayah = $proposalAwal->id_desa;

        $newProposal = DB::transaction(function () use ($proposalAwal, $request, $user, $alasan, $tahun, $idDesa, $kodeWilayah) {
            // 1. Alihkan status proposal RAPBD awal menjadi digantikan_papbd
            $statusSebelum = $proposalAwal->status;
            $proposalAwal->update([
                'status' => 'digantikan_papbd',
                'alasan_pergantian_papbd' => $alasan,
                'updated_by' => $user->uuid,
            ]);

            // 2. Generate Nomor Registrasi baru untuk usulan P-APBD
            $count = BkkdProposal::withTrashed()
                ->where('tahun_anggaran', $tahun)
                ->where('id_desa', $idDesa)
                ->count();

            $nextUrut = $count + 1;
            $nomorUrut = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
            $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeWilayah, $nomorUrut);
            while (BkkdProposal::withTrashed()->where('nomor_registrasi', $nomorRegistrasi)->exists()) {
                $nextUrut++;
                $nomorUrut = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
                $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeWilayah, $nomorUrut);
            }

            // 3. Buat usulan baru P-APBD
            $proposalBaru = BkkdProposal::create([
                'nomor_registrasi' => $nomorRegistrasi,
                'tahun_anggaran' => $tahun,
                'tahap_anggaran' => 'perubahan',
                'id_kecamatan' => (int) $proposalAwal->id_kecamatan,
                'id_desa' => (int) $proposalAwal->id_desa,
                'id_bidang' => $request->input('id_bidang'),
                'id_proposal_induk' => $proposalAwal->id,
                'judul_kegiatan' => $request->input('judul_kegiatan'),
                'uraian_kegiatan' => $request->input('uraian_kegiatan'),
                'lokasi_kegiatan' => $request->input('lokasi_kegiatan') ?? $proposalAwal->lokasi_kegiatan,
                'panjang' => $request->filled('panjang') ? (float) $request->input('panjang') : null,
                'lebar' => $request->filled('lebar') ? (float) $request->input('lebar') : null,
                'tinggi' => $request->filled('tinggi') ? (float) $request->input('tinggi') : null,
                'volume' => $request->filled('volume') ? (float) $request->input('volume') : null,
                'satuan' => $request->input('satuan'),
                'nilai_usulan' => $request->filled('nilai_usulan') ? (float) $request->input('nilai_usulan') : null,
                'nomor_surat_desa' => $request->input('nomor_surat_desa'),
                'tanggal_surat_desa' => $request->input('tanggal_surat_desa'),
                'tanggal_diterima' => now()->toDateString(),
                'status' => 'draft',
                'alasan_pergantian_papbd' => $alasan,
                'versi' => 1,
                'created_by' => $user->uuid,
            ]);

            // 4. Catat riwayat pada proposal lama
            $this->riwayatService->catatAksiProposal(
                proposal: $proposalAwal,
                aksi: 'status_changed',
                alasan: sprintf('Usulan resmi digantikan pada Perubahan APBD (P-APBD) dengan usulan nomor %s. Alasan: %s', $proposalBaru->nomor_registrasi, $alasan),
                user: $user,
                extraSebelum: ['status' => $statusSebelum],
                extraSesudah: [
                    'status' => 'digantikan_papbd',
                    'proposal_pengganti_id' => $proposalBaru->id,
                    'nomor_registrasi_pengganti' => $proposalBaru->nomor_registrasi,
                ]
            );

            // 5. Catat riwayat pada proposal baru
            $this->riwayatService->catatAksiProposal(
                proposal: $proposalBaru,
                aksi: 'created',
                alasan: sprintf('Usulan baru diajukan pada Perubahan APBD (P-APBD) menggantikan usulan nomor %s. Alasan: %s', $proposalAwal->nomor_registrasi, $alasan),
                user: $user,
                extraSesudah: [
                    'tahap_anggaran' => 'perubahan',
                    'proposal_induk_id' => $proposalAwal->id,
                    'nomor_registrasi_induk' => $proposalAwal->nomor_registrasi,
                ]
            );

            return $proposalBaru;
        });

        $newProposal->load(['kecamatan', 'desa', 'bidang', 'proposalInduk']);

        return response()->json([
            'ok' => true,
            'message' => sprintf('Pergantian usulan P-APBD berhasil dicatat. Usulan baru nomor %s telah dibuat.', $newProposal->nomor_registrasi),
            'data' => new BkkdProposalDetailResource($newProposal),
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('delete', $proposal);

        if (!in_array($proposal->status, ['draft', 'dibatalkan'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Hanya proposal berstatus Draft atau Dibatalkan yang dapat dihapus.',
            ], 422);
        }

        $user = $request->user();
        DB::transaction(function () use ($proposal, $user) {
            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'deleted',
                alasan: sprintf('Proposal berstatus "%s" dihapus sementara (soft delete)', $proposal->status),
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

    public function uploadLampiran(Request $request, string $id): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $request->validate([
            'tipe_sumber' => ['nullable', 'string', 'in:file,link'],
            'jenis_dokumen' => ['required', 'string', 'max:50'],
            'file' => ['required_if:tipe_sumber,file', 'nullable', 'file', 'mimes:pdf,xlsx,xls,jpg,jpeg,png', 'max:10240'],
            'url_link' => ['required_if:tipe_sumber,link', 'nullable', 'url', 'max:2048'],
            'nama_asli' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $tipeSumber = $request->input('tipe_sumber', $request->hasFile('file') ? 'file' : 'link');

        if ($tipeSumber === 'link') {
            $urlLink = $request->input('url_link');
            $namaAsli = trim($request->input('nama_asli') ?: '');
            if (empty($namaAsli)) {
                $host = parse_url($urlLink, PHP_URL_HOST) ?: 'Cloud Storage';
                $namaAsli = 'Tautan ' . ucwords(str_replace('_', ' ', $request->input('jenis_dokumen'))) . ' (' . $host . ')';
            }

            $lampiran = BkkdProposalLampiran::create([
                'id_proposal' => $proposal->id,
                'jenis_dokumen' => $request->input('jenis_dokumen'),
                'tipe_sumber' => 'link',
                'url_link' => $urlLink,
                'nama_asli' => $namaAsli,
                'path' => null,
                'mime_type' => 'url',
                'ukuran_byte' => null,
                'uploaded_by' => $user->uuid,
                'created_at' => now(),
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'lampiran_added',
                alasan: sprintf('Menambahkan tautan berkas cloud "%s" (%s): %s', $namaAsli, $request->input('jenis_dokumen'), $urlLink),
                user: $user,
                extraSesudah: [
                    'lampiran_id' => $lampiran->id,
                    'nama_asli' => $namaAsli,
                    'tipe_sumber' => 'link',
                    'url_link' => $urlLink,
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Tautan berkas cloud berhasil disimpan.',
                'data' => [
                    'id' => $lampiran->id,
                    'tipe_sumber' => $lampiran->tipe_sumber,
                    'url_link' => $lampiran->url_link,
                    'jenis_dokumen' => $lampiran->jenis_dokumen,
                    'nama_asli' => $lampiran->nama_asli,
                    'mime_type' => $lampiran->mime_type,
                    'ukuran_byte' => $lampiran->ukuran_byte,
                    'uploaded_by_name' => $user->name,
                    'created_at' => $lampiran->created_at->toIso8601String(),
                ],
            ]);
        }

        $file = $request->file('file');
        $namaAsli = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        $path = sprintf('bkkd/%s/%s/%s.%s', $proposal->tahun_anggaran, $proposal->id, Str::uuid7(), $extension);
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        $lampiran = BkkdProposalLampiran::create([
            'id_proposal' => $proposal->id,
            'jenis_dokumen' => $request->input('jenis_dokumen'),
            'tipe_sumber' => 'file',
            'url_link' => null,
            'nama_asli' => $namaAsli,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'ukuran_byte' => $file->getSize(),
            'uploaded_by' => $user->uuid,
            'created_at' => now(),
        ]);

        $this->riwayatService->catatAksiProposal(
            proposal: $proposal,
            aksi: 'lampiran_added',
            alasan: sprintf('Menambahkan lampiran berkas "%s" (%s)', $namaAsli, $request->input('jenis_dokumen')),
            user: $user,
            extraSesudah: [
                'lampiran_id' => $lampiran->id,
                'nama_asli' => $namaAsli,
                'tipe_sumber' => 'file',
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Lampiran berkas berhasil diunggah.',
            'data' => [
                'id' => $lampiran->id,
                'tipe_sumber' => $lampiran->tipe_sumber,
                'url_link' => $lampiran->url_link,
                'jenis_dokumen' => $lampiran->jenis_dokumen,
                'nama_asli' => $lampiran->nama_asli,
                'mime_type' => $lampiran->mime_type,
                'ukuran_byte' => $lampiran->ukuran_byte,
                'uploaded_by_name' => $user->name,
                'created_at' => $lampiran->created_at->toIso8601String(),
            ],
        ]);
    }

    public function deleteLampiran(Request $request, string $id, string $lampiranId): JsonResponse
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('update', $proposal);

        $lampiran = BkkdProposalLampiran::where('id_proposal', $proposal->id)
            ->where('id', $lampiranId)
            ->firstOrFail();

        $user = $request->user();
        $namaAsli = $lampiran->nama_asli;

        if ($lampiran->path && Storage::disk('local')->exists($lampiran->path)) {
            Storage::disk('local')->delete($lampiran->path);
        }

        $lampiran->delete();

        $this->riwayatService->catatAksiProposal(
            proposal: $proposal,
            aksi: 'lampiran_removed',
            alasan: sprintf('Menghapus lampiran berkas "%s"', $namaAsli),
            user: $user,
            extraSebelum: ['lampiran_id' => $lampiranId, 'nama_asli' => $namaAsli]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Lampiran berkas berhasil dihapus.',
        ]);
    }

    public function downloadLampiran(Request $request, string $id, string $lampiranId): mixed
    {
        $proposal = BkkdProposal::findOrFail($id);
        Gate::authorize('view', $proposal);

        $lampiran = BkkdProposalLampiran::where('id_proposal', $proposal->id)
            ->where('id', $lampiranId)
            ->firstOrFail();

        if ($lampiran->tipe_sumber === 'link' || !empty($lampiran->url_link)) {
            return redirect()->away($lampiran->url_link);
        }

        if (!$lampiran->path || !Storage::disk('local')->exists($lampiran->path)) {
            abort(404, 'File lampiran tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->download($lampiran->path, $lampiran->nama_asli);
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

        $type = $request->input('tipe', 'proposal');

        if ($type === 'verifikasi') {
            $query = BkkdVerifikasiRiwayat::with([
                'verifikasi.proposal.kecamatan:id,nama_kecamatan',
                'verifikasi.proposal.desa:id,nama_desa',
                'verifikasi.opd:id,kode,nama',
                'actor:uuid,name,email',
            ])->whereHas('verifikasi.proposal', function ($q) use ($user, $request) {
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
                'Panjang (m)',
                'Lebar (m)',
                'Tinggi (m)',
                'Volume',
                'Satuan',
                'Lokasi Kegiatan',
                'Uraian Kegiatan',
                'Nomor Surat Desa',
                'Tanggal Surat Desa',
            ]);

            fputcsv($handle, [
                2027,
                'Kanor',
                'Pilang',
                'Jalan Poros Desa',
                'Pembangunan Jalan Rabat Beton RT 01 RW 02',
                250000000,
                1500,
                3.5,
                0.2,
                1500,
                'meter',
                'Dusun Krajan',
                'Pembangunan jalan poros desa untuk kelancaran transportasi warga',
                '400/01/2026',
                '2026-10-15',
            ]);

            fputcsv($handle, [
                2027,
                'Kanor',
                'Pilang',
                'Jembatan Desa',
                'Pembangunan Jembatan Dusun Sambong',
                350000000,
                12,
                4.0,
                null,
                1,
                'unit',
                'Dusun Sambong',
                'Pembangunan jembatan penghubung jalan usaha tani',
                '400/02/2026',
                '2026-10-15',
            ]);

            fclose($handle);
        }, 200, $headers);
    }

    public function importExcel(Request $request): JsonResponse
    {
        Gate::authorize('create', BkkdProposal::class);
        $user = $request->user();

        $rows = $request->input('rows');

        if (empty($rows) && $request->hasFile('file')) {
            $file = $request->file('file');
            $rows = $this->parseCsvFile($file->getRealPath());
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
                $panjangRaw = $row['panjang'] ?? $row['Panjang'] ?? $row['Panjang (m)'] ?? null;
                $lebarRaw = $row['lebar'] ?? $row['Lebar'] ?? $row['Lebar (m)'] ?? null;
                $tinggiRaw = $row['tinggi'] ?? $row['Tinggi'] ?? $row['Tinggi (m)'] ?? null;
                $volumeRaw = $row['volume'] ?? $row['Volume'] ?? null;
                $satuanRaw = $row['satuan'] ?? $row['Satuan'] ?? null;
                $lokasiRaw = $row['lokasi_kegiatan'] ?? $row['Lokasi Kegiatan'] ?? $row['lokasi'] ?? null;
                $uraianRaw = $row['uraian_kegiatan'] ?? $row['Uraian Kegiatan'] ?? $row['uraian'] ?? null;
                $noSuratRaw = $row['nomor_surat_desa'] ?? $row['Nomor Surat Desa'] ?? $row['nomor_surat'] ?? null;
                $tglSuratRaw = $row['tanggal_surat_desa'] ?? $row['Tanggal Surat Desa'] ?? $row['tanggal_surat'] ?? null;

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

                // Format Dimensi & Volume
                $panjang = is_numeric($panjangRaw) ? (float) $panjangRaw : null;
                $lebar = is_numeric($lebarRaw) ? (float) $lebarRaw : null;
                $tinggi = is_numeric($tinggiRaw) ? (float) $tinggiRaw : null;
                $volume = is_numeric($volumeRaw) ? (float) $volumeRaw : null;
                $satuan = !empty($satuanRaw) ? trim((string) $satuanRaw) : null;

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
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tinggi' => $tinggi,
                    'volume' => $volume,
                    'satuan' => $satuan,
                    'nomor_surat_desa' => !empty($noSuratRaw) ? trim((string) $noSuratRaw) : sprintf('Surat Desa %s', $desa->nama_desa),
                    'tanggal_surat_desa' => $this->parseTanggalSurat($tglSuratRaw),
                    'tanggal_diterima' => now()->toDateString(),
                    'status' => 'draft',
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
                        'status' => $proposal->status,
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

        // 5. Fallback ke BKK JALAN atau bidang pertama jika tidak terdeteksi
        return $bidangs->firstWhere('nama', 'BKK JALAN') ?? $bidangs->first();
    }

    /**
     * Resolusi format tanggal (mendukung YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, dan Excel serial date)
     */
    private function parseTanggalSurat(?string $rawDate): string
    {
        if (empty($rawDate)) {
            return now()->toDateString();
        }

        $trimmed = trim((string) $rawDate);

        // Jika berupa angka serial tanggal Excel (misal: 46079)
        if (is_numeric($trimmed) && (int) $trimmed > 20000 && (int) $trimmed < 100000) {
            $unixTimestamp = ((int) $trimmed - 25569) * 86400;
            return gmdate('Y-m-d', $unixTimestamp);
        }

        // Format DD/MM/YYYY atau DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $trimmed, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year = $m[3];
            if (checkdate((int) $month, (int) $day, (int) $year)) {
                return "$year-$month-$day";
            }
        }

        // Format YYYY-MM-DD atau YYYY/MM/DD
        if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $trimmed, $m)) {
            $year = $m[1];
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($m[3], 2, '0', STR_PAD_LEFT);
            if (checkdate((int) $month, (int) $day, (int) $year)) {
                return "$year-$month-$day";
            }
        }

        $time = strtotime($trimmed);
        return $time ? date('Y-m-d', $time) : now()->toDateString();
    }
}

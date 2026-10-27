<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBkkdVerifikasiKolektifRequest;
use App\Http\Requests\Admin\StoreBkkdVerifikasiRequest;
use App\Http\Requests\Admin\UpdateBkkdVerifikasiRequest;
use App\Http\Resources\BkkdRiwayatResource;
use App\Http\Resources\BkkdVerifikasiDokumenResource;
use App\Http\Resources\BkkdVerifikasiResource;
use App\Models\BkkdProposal;
use App\Models\BkkdVerifikasi;
use App\Models\BkkdVerifikasiDokumen;
use App\Models\TemporaryUpload;
use App\Services\BkkdRiwayatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BkkdVerifikasiController extends Controller
{
    public function __construct(
        protected BkkdRiwayatService $riwayatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdVerifikasi::class);

        $user = $request->user();
        $showDeletedProposal = filter_var($request->input('show_deleted_proposal', false), FILTER_VALIDATE_BOOLEAN);

        $query = BkkdVerifikasi::with([
            'dokumen.opd:id,kode,nama',
            'proposal.kecamatan:id,nama_kecamatan',
            'proposal.desa:id,nama_desa',
            'proposal.bidang:id,nama',
            'opd:id,kode,nama',
            'verifikator:uuid,name',
            'updater:uuid,name',
        ])->whereHas('proposal', function ($q) use ($user, $request, $showDeletedProposal) {
            $q->forUser($user);

            if (!$showDeletedProposal) {
                $q->whereNull('bkkd_proposal.deleted_at');
            }

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

        // Filter aktif / superseded
        $showSuperseded = filter_var($request->input('show_superseded', false), FILTER_VALIDATE_BOOLEAN);
        if (!$showSuperseded) {
            $query->where('is_aktif', true);
        }

        // Filter OPD
        if ($request->filled('id_opd')) {
            $query->where('id_opd', $request->input('id_opd'));
        }

        // Filter Hasil
        if ($request->filled('hasil')) {
            $query->where('hasil', $request->input('hasil'));
        }

        // Search
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat_hasil', 'ilike', $search)
                  ->orWhere('catatan_opd', 'ilike', $search)
                  ->orWhereHas('proposal', function ($pq) use ($search) {
                      $pq->where('judul_kegiatan', 'ilike', $search)
                         ->orWhere('nomor_registrasi', 'ilike', $search);
                  });
            });
        }

        $query->orderByDesc('created_at');
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $results = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => BkkdVerifikasiResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function store(StoreBkkdVerifikasiRequest $request, string $proposalId): JsonResponse
    {
        Gate::authorize('create', BkkdVerifikasi::class);

        $proposal = BkkdProposal::with('bidang')->findOrFail($proposalId);
        $user = $request->user();
        $validated = $request->validated();
        $tglSurat = $validated['tanggal_surat_hasil'] ?? $validated['tanggal_verifikasi'] ?? now()->toDateString();
        $tglDiterima = $validated['tanggal_diterima'] ?? $tglSurat;
        $nilaiRekom = $validated['nilai_rekomendasi'] ?? $validated['rekomendasi_anggaran'] ?? null;
        if (in_array($validated['hasil'], ['tidak_layak', 'dikembalikan'])) {
            $nilaiRekom = null;
            $validated['volume_rekomendasi'] = null;
            $validated['panjang_rekomendasi'] = null;
            $validated['lebar_rekomendasi'] = null;
            $validated['tinggi_rekomendasi'] = null;
        }
        $idDokumen = $validated['id_dokumen'] ?? null;
        $dokumenInduk = $idDokumen ? BkkdVerifikasiDokumen::find($idDokumen) : null;
        $idOpd = $validated['id_opd'] ?? $dokumenInduk?->id_opd ?? $proposal->bidang?->id_opd_default;

        if (!$idOpd) {
            return response()->json([
                'ok' => false,
                'message' => 'OPD teknis pemeriksa belum ditentukan untuk bidang usulan proposal ini.',
            ], 422);
        }

        $verifikasi = DB::transaction(function () use ($proposal, $validated, $idOpd, $dokumenInduk, $tglSurat, $tglDiterima, $nilaiRekom, $user) {
            if ($dokumenInduk) {
                $tipeSumber = $dokumenInduk->tipe_sumber ?? 'file';
                $urlLink = $dokumenInduk->url_link;
                $permanenPath = $dokumenInduk->path_dokumen;
                $namaAsli = $dokumenInduk->nama_dokumen_asli;
                $nomorSurat = $dokumenInduk->nomor_surat_hasil;
                $tglSuratFinal = $dokumenInduk->tanggal_surat_hasil?->format('Y-m-d') ?? $tglSurat;
                $tglDiterimaFinal = $dokumenInduk->tanggal_diterima?->format('Y-m-d') ?? $tglDiterima;
            } else {
                $nomorSurat = $validated['nomor_surat_hasil'];
                $tglSuratFinal = $tglSurat;
                $tglDiterimaFinal = $tglDiterima;
                $tipeSumber = $validated['tipe_sumber'] ?? (!empty($validated['url_link']) ? 'link' : 'file');
                $urlLink = $validated['url_link'] ?? null;
                $tempPath = $validated['path_dokumen'] ?? null;
                $namaAsli = $validated['nama_dokumen_asli'] ?? ($tipeSumber === 'link' ? 'Tautan Dokumen Verifikasi' : ($tempPath ? basename($tempPath) : 'dokumen_verifikasi.pdf'));
                $permanenPath = null;

                if ($tipeSumber === 'file') {
                    $permanenPath = $tempPath ?: sprintf('bkkd/%s/%s/verifikasi/%s.pdf', $proposal->tahun_anggaran, $proposal->id, Str::uuid7());
                    // Pindahkan dokumen hasil verifikasi jika dari temporary upload
                    if ($tempPath && Storage::disk('local')->exists($tempPath) && str_starts_with($tempPath, 'temporary/')) {
                        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                        $permanenPath = sprintf('bkkd/%s/%s/verifikasi/%s.%s', $proposal->tahun_anggaran, $proposal->id, Str::uuid7(), $extension);
                        Storage::disk('local')->move($tempPath, $permanenPath);
                        TemporaryUpload::where('path', $tempPath)->delete();
                    }
                }
            }

            // Jika ada verifikasi lama yang aktif, jadikan is_aktif = false dan rekam superseded
            $verifikasiLama = BkkdVerifikasi::where('id_proposal', $proposal->id)
                ->where('is_aktif', true)
                ->first();

            if ($verifikasiLama) {
                $verifikasiLama->update(['is_aktif' => false]);
                $this->riwayatService->catatAksiVerifikasi(
                    verifikasi: $verifikasiLama,
                    aksi: 'superseded',
                    alasan: 'Hasil verifikasi digantikan oleh verifikasi telaah susulan',
                    user: $user
                );
            }

            $verifikasi = BkkdVerifikasi::create([
                'id_proposal' => $proposal->id,
                'id_dokumen' => $dokumenInduk?->id,
                'id_opd' => $idOpd,
                'nomor_surat_hasil' => $nomorSurat,
                'tanggal_surat_hasil' => $tglSuratFinal,
                'tanggal_diterima' => $tglDiterimaFinal,
                'hasil' => $validated['hasil'],
                'nilai_rekomendasi' => $nilaiRekom,
                'volume_rekomendasi' => $validated['volume_rekomendasi'] ?? null,
                'panjang_rekomendasi' => $validated['panjang_rekomendasi'] ?? null,
                'lebar_rekomendasi' => $validated['lebar_rekomendasi'] ?? null,
                'tinggi_rekomendasi' => $validated['tinggi_rekomendasi'] ?? null,
                'catatan_opd' => $validated['catatan_opd'] ?? null,
                'tipe_sumber' => $tipeSumber,
                'url_link' => $tipeSumber === 'link' ? $urlLink : null,
                'path_dokumen' => $permanenPath,
                'nama_dokumen_asli' => $namaAsli,
                'is_aktif' => true,
                'versi' => 1,
                'input_by' => $user->uuid,
            ]);

            // Sinkronisasi status proposal
            $statusSebelum = $proposal->status;
            $proposal->update([
                'status' => $validated['hasil'],
                'updated_by' => $user->uuid,
            ]);

            $this->riwayatService->catatAksiProposal(
                proposal: $proposal,
                aksi: 'status_changed',
                alasan: sprintf('Status usulan disinkronkan mengikuti hasil telaah OPD teknis: "%s"', $validated['hasil']),
                user: $user,
                extraSebelum: ['status' => $statusSebelum],
                extraSesudah: ['status' => $validated['hasil']]
            );

            $this->riwayatService->catatAksiVerifikasi(
                verifikasi: $verifikasi,
                aksi: 'created',
                alasan: 'Pencatatan hasil verifikasi telaah teknis OPD',
                user: $user,
                extraSesudah: [
                    'hasil' => $verifikasi->hasil,
                    'nomor_surat_hasil' => $verifikasi->nomor_surat_hasil,
                    'nilai_rekomendasi' => $verifikasi->nilai_rekomendasi,
                ]
            );

            return $verifikasi;
        });

        $verifikasi->load(['opd:id,kode,nama', 'verifikator:uuid,name']);

        return response()->json([
            'ok' => true,
            'message' => 'Hasil verifikasi telaah teknis OPD berhasil dicatat.',
            'data' => new BkkdVerifikasiResource($verifikasi),
        ], 201);
    }

    public function update(UpdateBkkdVerifikasiRequest $request, string $id): JsonResponse
    {
        $verifikasi = BkkdVerifikasi::with('proposal')->findOrFail($id);
        Gate::authorize('update', $verifikasi);

        if ($verifikasi->proposal?->trashed()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak dapat memperbarui verifikasi karena proposal usulan telah dihapus (soft delete).',
            ], 422);
        }

        $user = $request->user();
        $validated = $request->validated();
        if (isset($validated['tanggal_verifikasi']) && !isset($validated['tanggal_surat_hasil'])) {
            $validated['tanggal_surat_hasil'] = $validated['tanggal_verifikasi'];
        }
        if (isset($validated['rekomendasi_anggaran']) && !isset($validated['nilai_rekomendasi'])) {
            $validated['nilai_rekomendasi'] = $validated['rekomendasi_anggaran'];
        }
        if (in_array($validated['hasil'], ['tidak_layak', 'dikembalikan'])) {
            $validated['nilai_rekomendasi'] = null;
            $validated['volume_rekomendasi'] = null;
        }
        if (empty($validated['tanggal_diterima'])) {
            $validated['tanggal_diterima'] = $verifikasi->tanggal_diterima?->format('Y-m-d') ?? now()->toDateString();
        }
        unset($validated['tanggal_verifikasi'], $validated['rekomendasi_anggaran']);

        $alasan = $validated['alasan_perubahan'];
        unset($validated['alasan_perubahan']);

        $updated = DB::transaction(function () use ($verifikasi, $validated, $alasan, $user) {
            $proposal = $verifikasi->proposal;

            // Cek jika menautkan ke dokumen berita acara induk kolektif
            $idDokumen = $validated['id_dokumen'] ?? null;
            if ($idDokumen) {
                $dokumenInduk = BkkdVerifikasiDokumen::find($idDokumen);
                if ($dokumenInduk) {
                    $validated['id_dokumen'] = $dokumenInduk->id;
                    $validated['id_opd'] = $dokumenInduk->id_opd;
                    $validated['nomor_surat_hasil'] = $dokumenInduk->nomor_surat_hasil;
                    $validated['tanggal_surat_hasil'] = $dokumenInduk->tanggal_surat_hasil?->format('Y-m-d') ?? ($validated['tanggal_surat_hasil'] ?? now()->toDateString());
                    $validated['tanggal_diterima'] = $dokumenInduk->tanggal_diterima?->format('Y-m-d') ?? ($validated['tanggal_diterima'] ?? now()->toDateString());
                    $validated['tipe_sumber'] = $dokumenInduk->tipe_sumber ?? 'file';
                    $validated['url_link'] = $dokumenInduk->url_link;
                    $validated['path_dokumen'] = $dokumenInduk->path_dokumen;
                    $validated['nama_dokumen_asli'] = $dokumenInduk->nama_dokumen_asli;
                }
            } elseif (isset($validated['tipe_sumber']) && $validated['tipe_sumber'] === 'link') {
                $validated['id_dokumen'] = null;
                $validated['path_dokumen'] = null;
                if (empty($validated['nama_dokumen_asli'])) {
                    $validated['nama_dokumen_asli'] = 'Tautan Dokumen Verifikasi';
                }
            } elseif (isset($validated['path_dokumen']) && $validated['path_dokumen'] !== $verifikasi->path_dokumen) {
                $validated['id_dokumen'] = null;
                $tempPath = $validated['path_dokumen'];
                if (Storage::disk('local')->exists($tempPath) && str_starts_with($tempPath, 'temporary/')) {
                    $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                    $permanenPath = sprintf('bkkd/%s/%s/verifikasi/%s.%s', $proposal->tahun_anggaran, $proposal->id, Str::uuid7(), $extension);
                    Storage::disk('local')->move($tempPath, $permanenPath);
                    TemporaryUpload::where('path', $tempPath)->delete();
                    $validated['path_dokumen'] = $permanenPath;
                }
                $validated['tipe_sumber'] = 'file';
                $validated['url_link'] = null;
            }

            $updatedVerifikasi = $this->riwayatService->catatKoreksiVerifikasi(
                verifikasi: $verifikasi,
                dataBaru: $validated,
                alasan: $alasan,
                user: $user
            );

            // Sinkronkan ke status proposal jika status hasil aktif berubah
            if ($updatedVerifikasi->is_aktif && isset($validated['hasil']) && $proposal->status !== $validated['hasil']) {
                $statusSebelum = $proposal->status;
                $proposal->update([
                    'status' => $validated['hasil'],
                    'updated_by' => $user->uuid,
                ]);

                $this->riwayatService->catatAksiProposal(
                    proposal: $proposal,
                    aksi: 'status_changed',
                    alasan: sprintf('Status usulan disinkronkan mengikuti koreksi hasil telaah OPD teknis: "%s"', $validated['hasil']),
                    user: $user,
                    extraSebelum: ['status' => $statusSebelum],
                    extraSesudah: ['status' => $validated['hasil']]
                );
            }

            return $updatedVerifikasi;
        });

        $updated->load(['opd:id,kode,nama', 'verifikator:uuid,name', 'updater:uuid,name']);

        return response()->json([
            'ok' => true,
            'message' => 'Hasil verifikasi berhasil dikoreksi.',
            'data' => new BkkdVerifikasiResource($updated),
        ]);
    }

    public function download(Request $request, string $id): StreamedResponse|RedirectResponse
    {
        $verifikasi = BkkdVerifikasi::with('proposal')->findOrFail($id);
        Gate::authorize('view', $verifikasi);

        if ($verifikasi->tipe_sumber === 'link' || (!empty($verifikasi->url_link) && empty($verifikasi->path_dokumen))) {
            if (!empty($verifikasi->url_link)) {
                return redirect()->away($verifikasi->url_link);
            }
        }

        if (!$verifikasi->path_dokumen || !Storage::disk('local')->exists($verifikasi->path_dokumen)) {
            abort(404, 'File dokumen verifikasi tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->download($verifikasi->path_dokumen, $verifikasi->nama_dokumen_asli);
    }

    public function riwayat(Request $request, string $id): JsonResponse
    {
        $verifikasi = BkkdVerifikasi::findOrFail($id);
        Gate::authorize('view', $verifikasi);

        $riwayat = $verifikasi->riwayat()->with('actor:uuid,name,email')->get();

        return response()->json([
            'ok' => true,
            'data' => BkkdRiwayatResource::collection($riwayat),
        ]);
    }

    public function storeKolektif(StoreBkkdVerifikasiKolektifRequest $request): JsonResponse
    {
        Gate::authorize('create', BkkdVerifikasi::class);

        $user = $request->user();
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $user) {
            $tahun = $validated['tahun_anggaran'] ?? now()->year;
            $tipeSumber = $validated['tipe_sumber'] ?? (!empty($validated['url_link']) ? 'link' : 'file');
            $urlLink = $validated['url_link'] ?? null;
            $tempPath = $validated['path_dokumen'] ?? null;
            $namaAsli = $validated['nama_dokumen_asli'] ?? ($tipeSumber === 'link' ? 'Tautan Dokumen Verifikasi' : ($tempPath ? basename($tempPath) : 'dokumen_verifikasi.pdf'));

            $permanenPath = null;
            if ($tipeSumber === 'file' && $tempPath) {
                $permanenPath = $tempPath;
                if (Storage::disk('local')->exists($tempPath) && str_starts_with($tempPath, 'temporary/')) {
                    $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                    $permanenPath = sprintf('bkkd/%s/verifikasi-dokumen/%s.%s', $tahun, Str::uuid7(), $extension);
                    Storage::disk('local')->move($tempPath, $permanenPath);
                    TemporaryUpload::where('path', $tempPath)->delete();
                }
            }

            $dokumen = BkkdVerifikasiDokumen::create([
                'id_opd' => $validated['id_opd'],
                'nomor_surat_hasil' => $validated['nomor_surat_hasil'],
                'tanggal_surat_hasil' => $validated['tanggal_surat_hasil'],
                'tanggal_diterima' => $validated['tanggal_diterima'] ?? $validated['tanggal_surat_hasil'],
                'tahun_anggaran' => $tahun,
                'tipe_sumber' => $tipeSumber,
                'url_link' => $tipeSumber === 'link' ? $urlLink : null,
                'path_dokumen' => $permanenPath,
                'nama_dokumen_asli' => $namaAsli,
                'catatan_umum' => $validated['catatan_umum'] ?? null,
                'input_by' => $user->uuid,
            ]);

            $savedVerifikasi = [];

            foreach ($validated['items'] as $item) {
                $proposal = BkkdProposal::with('bidang')->lockForUpdate()->findOrFail($item['id_proposal']);

                $nilaiRekom = $item['nilai_rekomendasi'] ?? null;
                if (in_array($item['hasil'], ['tidak_layak', 'dikembalikan'])) {
                    $nilaiRekom = null;
                    $item['volume_rekomendasi'] = null;
                    $item['panjang_rekomendasi'] = null;
                    $item['lebar_rekomendasi'] = null;
                    $item['tinggi_rekomendasi'] = null;
                }

                $verifikasiLama = BkkdVerifikasi::where('id_proposal', $proposal->id)
                    ->where('is_aktif', true)
                    ->first();

                if ($verifikasiLama) {
                    $verifikasiLama->update(['is_aktif' => false]);
                    $this->riwayatService->catatAksiVerifikasi(
                        verifikasi: $verifikasiLama,
                        aksi: 'superseded',
                        alasan: sprintf('Hasil telaah digantikan oleh Berita Acara Kolektif No %s', $dokumen->nomor_surat_hasil),
                        user: $user
                    );
                }

                $verifikasi = BkkdVerifikasi::create([
                    'id_proposal' => $proposal->id,
                    'id_dokumen' => $dokumen->id,
                    'id_opd' => $dokumen->id_opd,
                    'nomor_surat_hasil' => $dokumen->nomor_surat_hasil,
                    'tanggal_surat_hasil' => $dokumen->tanggal_surat_hasil,
                    'tanggal_diterima' => $dokumen->tanggal_diterima,
                    'hasil' => $item['hasil'],
                    'nilai_rekomendasi' => $nilaiRekom,
                    'volume_rekomendasi' => $item['volume_rekomendasi'] ?? null,
                    'panjang_rekomendasi' => $item['panjang_rekomendasi'] ?? null,
                    'lebar_rekomendasi' => $item['lebar_rekomendasi'] ?? null,
                    'tinggi_rekomendasi' => $item['tinggi_rekomendasi'] ?? null,
                    'catatan_opd' => $item['catatan_opd'] ?? null,
                    'tipe_sumber' => $dokumen->tipe_sumber,
                    'url_link' => $dokumen->url_link,
                    'path_dokumen' => $dokumen->path_dokumen,
                    'nama_dokumen_asli' => $dokumen->nama_dokumen_asli,
                    'is_aktif' => true,
                    'versi' => 1,
                    'input_by' => $user->uuid,
                ]);

                $statusSebelum = $proposal->status;
                $proposal->update([
                    'status' => $item['hasil'],
                    'updated_by' => $user->uuid,
                ]);

                $this->riwayatService->catatAksiProposal(
                    proposal: $proposal,
                    aksi: 'status_changed',
                    alasan: sprintf('Sinkronisasi status dari Berita Acara Kolektif: "%s"', $item['hasil']),
                    user: $user,
                    extraSebelum: ['status' => $statusSebelum],
                    extraSesudah: ['status' => $item['hasil']]
                );

                $this->riwayatService->catatAksiVerifikasi(
                    verifikasi: $verifikasi,
                    aksi: 'created',
                    alasan: sprintf('Pencatatan telaah via Berita Acara Kolektif No %s', $dokumen->nomor_surat_hasil),
                    user: $user,
                    extraSesudah: [
                        'hasil' => $verifikasi->hasil,
                        'nomor_surat_hasil' => $verifikasi->nomor_surat_hasil,
                        'nilai_rekomendasi' => $verifikasi->nilai_rekomendasi,
                    ]
                );

                $savedVerifikasi[] = $verifikasi;
            }

            return [
                'dokumen' => $dokumen,
                'total_desa' => count($savedVerifikasi),
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => sprintf('Berita acara kolektif berhasil disimpan untuk %d usulan desa.', $result['total_desa']),
            'data' => [
                'dokumen' => new BkkdVerifikasiDokumenResource($result['dokumen']),
                'total_desa' => $result['total_desa'],
            ],
        ], 201);
    }

    public function dokumenList(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdVerifikasi::class);

        $query = BkkdVerifikasiDokumen::with(['opd:id,kode,nama', 'verifikator:uuid,name'])
            ->withCount(['verifikasi as total_desa' => function ($q) {
                $q->where('is_aktif', true);
            }]);

        if ($request->filled('id_opd')) {
            $query->where('id_opd', $request->input('id_opd'));
        }

        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat_hasil', 'ilike', $search)
                  ->orWhere('catatan_umum', 'ilike', $search);
            });
        }

        $query->orderByDesc('tanggal_surat_hasil')->orderByDesc('created_at');
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $results = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => BkkdVerifikasiDokumenResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function downloadDokumen(string $id): StreamedResponse|RedirectResponse
    {
        Gate::authorize('viewAny', BkkdVerifikasi::class);

        $dokumen = BkkdVerifikasiDokumen::findOrFail($id);

        if ($dokumen->tipe_sumber === 'link' || (!empty($dokumen->url_link) && empty($dokumen->path_dokumen))) {
            if (!empty($dokumen->url_link)) {
                return redirect()->away($dokumen->url_link);
            }
        }

        if (!$dokumen->path_dokumen || !Storage::disk('local')->exists($dokumen->path_dokumen)) {
            abort(404, 'File dokumen berita acara tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->download($dokumen->path_dokumen, $dokumen->nama_dokumen_asli);
    }
}

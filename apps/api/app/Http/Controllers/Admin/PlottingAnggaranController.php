<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlottingAnggaranRequest;
use App\Http\Requests\Admin\UpdatePlottingAnggaranRequest;
use App\Models\InfrastrukturSegmen;
use App\Models\PlottingAnggaran;
use App\Models\RefSumberDana;
use App\Services\JadwalAnggaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PlottingAnggaranController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PlottingAnggaran::class);

        $user = $request->user();
        $query = PlottingAnggaran::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'author:uuid,name,email',
            'proposal:id,nomor_registrasi,judul_kegiatan,tahun_anggaran',
        ])
            ->withSum('segmen as used_panjang_m', 'panjang')
            ->withCount('segmen as segmen_count')
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

        // Filter sumber dana
        if ($request->filled('sumber_dana')) {
            $query->where('sumber_dana', $request->input('sumber_dana'));
        }

        // Filter status penetapan SK/Pagu
        if ($request->filled('status_penetapan')) {
            $query->where('status_penetapan', $request->input('status_penetapan'));
        }

        // Search nama kegiatan / jenis bantuan / nomor SK
        if ($request->filled('search')) {
            $search = '%' . mb_strtolower(trim((string) $request->input('search'))) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama_kegiatan) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(jenis_bantuan) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(COALESCE(lokasi_kegiatan, \'\')) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(COALESCE(nomor_sk, \'\')) LIKE ?', [$search]);
            });
        }

        // Summary metrics
        $cloneForSummary = clone $query;
        $totalPagu = (float) $cloneForSummary->sum('target_pagu_anggaran');
        $totalPanjang = (float) $cloneForSummary->sum('target_panjang_m');
        $totalKegiatan = (int) $cloneForSummary->count();

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('tahun_anggaran', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'ok'      => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'summary' => [
                'total_kegiatan'       => $totalKegiatan,
                'total_pagu_anggaran'  => $totalPagu,
                'total_target_panjang' => $totalPanjang,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $plotting = PlottingAnggaran::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'author:uuid,name,email',
            'segmen:id,plotting_id,namobj,panjang,kondisi,status_verifikasi',
            'monitoringRealisasi:id,id_plotting,nomor_ba,realisasi_panjang,status',
        ])->where('id', $id)->first();

        if (!$plotting) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data plotting anggaran tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('view', $plotting);

        return response()->json([
            'ok'   => true,
            'data' => $plotting,
        ]);
    }

    public function store(StorePlottingAnggaranRequest $request): JsonResponse
    {
        Gate::authorize('create', PlottingAnggaran::class);

        $user = $request->user();
        $validated = $request->validated();

        $isRestricted = $user && !$user->isAdminOrBappeda();
        $isDesaUser = $isRestricted && ($user->hasAnyRole(['operatorDesa', 'operator-desa', 'desa']) || (bool) $user->id_desa);

        if ($isDesaUser && !$this->isDesaCategorySumberDana((string) ($validated['sumber_dana'] ?? ''))) {
            return response()->json([
                'ok'      => false,
                'message' => 'Role Desa hanya diperbolehkan membuat plotting anggaran kategori Desa (Dana Desa / APBDes).',
            ], 403);
        }

        // Auto-lock wilayah jika user memiliki batasan wilayah
        if ($isRestricted) {
            if ($user->id_kecamatan) {
                $validated['id_kecamatan'] = $user->id_kecamatan;
            }
            if ($user->id_desa) {
                $validated['id_desa'] = $user->id_desa;
            }
        }

        if (empty($validated['status_penetapan'])) {
            $validated['status_penetapan'] = 'ditetapkan';
        }
        if (!array_key_exists('target_panjang_m', $validated) || $validated['target_panjang_m'] === null) {
            $validated['target_panjang_m'] = 0;
        }

        $scheduleCheck = app(JadwalAnggaranService::class)->checkAccess(
            $user,
            (int) $validated['tahun_anggaran'],
            isset($validated['id_kecamatan']) ? (int) $validated['id_kecamatan'] : null,
            isset($validated['id_desa']) ? (int) $validated['id_desa'] : null,
            'plotting',
        );
        if (!$scheduleCheck['allowed']) {
            return response()->json([
                'ok'      => false,
                'message' => $scheduleCheck['message'],
            ], 403);
        }

        $plotting = new PlottingAnggaran();
        $plotting->id = (string) Str::uuid();
        $plotting->fill($validated);
        $plotting->user_id = $user->uuid;
        $plotting->save();

        $fresh = PlottingAnggaran::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'proposal:id,nomor_registrasi,judul_kegiatan,tahun_anggaran',
        ])
            ->withSum('segmen as used_panjang_m', 'panjang')
            ->withCount('segmen as segmen_count')
            ->where('id', $plotting->id)
            ->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Plotting anggaran berhasil disimpan.',
            'data'    => $fresh,
        ], 201);
    }

    public function update(UpdatePlottingAnggaranRequest $request, string $id): JsonResponse
    {
        $plotting = PlottingAnggaran::where('id', $id)->first();
        if (!$plotting) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data plotting anggaran tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('update', $plotting);

        $user = $request->user();
        $validated = $request->validated();
        $isRestricted = $user && !$user->isAdminOrBappeda();
        $isDesaUser = $isRestricted && ($user->hasAnyRole(['operatorDesa', 'operator-desa', 'desa']) || (bool) $user->id_desa);

        if ($isDesaUser) {
            $targetSumber = (string) ($validated['sumber_dana'] ?? $plotting->sumber_dana ?? '');
            if (
                !$this->isDesaCategorySumberDana((string) $plotting->sumber_dana) ||
                !$this->isDesaCategorySumberDana($targetSumber)
            ) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Role Desa hanya diperbolehkan mengubah plotting anggaran kategori Desa (Dana Desa / APBDes).',
                ], 403);
            }
            if ($user->id_kecamatan) {
                $validated['id_kecamatan'] = $user->id_kecamatan;
            }
            if ($user->id_desa) {
                $validated['id_desa'] = $user->id_desa;
            }
        }

        if (array_key_exists('target_panjang_m', $validated) && $validated['target_panjang_m'] === null) {
            $validated['target_panjang_m'] = 0;
        }

        $targetTahun = (int) ($validated['tahun_anggaran'] ?? $plotting->tahun_anggaran);
        $targetKec = (int) ($validated['id_kecamatan'] ?? $plotting->id_kecamatan);
        $targetDesa = (int) ($validated['id_desa'] ?? $plotting->id_desa);

        $scheduleCheck = app(JadwalAnggaranService::class)->checkAccess(
            $user,
            $targetTahun,
            $targetKec,
            $targetDesa,
            'plotting',
        );
        if (!$scheduleCheck['allowed']) {
            return response()->json([
                'ok'      => false,
                'message' => $scheduleCheck['message'],
            ], 403);
        }

        $plotting->fill($validated);
        $plotting->save();

        // Sinkronkan sumber_dana dan tahun_pembangunan ke segmen draft/revisi terkait agar tetap konsisten
        if ($plotting->wasChanged(['sumber_dana', 'tahun_anggaran'])) {
            InfrastrukturSegmen::query()
                ->where('plotting_id', $plotting->id)
                ->whereIn('status_verifikasi', ['draft', 'rejected_kecamatan', 'rejected_bappeda'])
                ->update([
                    'sumber_dana'       => $plotting->sumber_dana,
                    'tahun_pembangunan' => $plotting->tahun_anggaran,
                ]);
        }

        $fresh = PlottingAnggaran::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'proposal:id,nomor_registrasi,judul_kegiatan,tahun_anggaran',
        ])
            ->withSum('segmen as used_panjang_m', 'panjang')
            ->withCount('segmen as segmen_count')
            ->where('id', $plotting->id)
            ->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Plotting anggaran berhasil diperbarui.',
            'data'    => $fresh,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $plotting = PlottingAnggaran::where('id', $id)->first();
        if (!$plotting) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data plotting anggaran tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('delete', $plotting);

        $scheduleCheck = app(JadwalAnggaranService::class)->checkAccess(
            $request->user(),
            (int) $plotting->tahun_anggaran,
            $plotting->id_kecamatan ? (int) $plotting->id_kecamatan : null,
            $plotting->id_desa ? (int) $plotting->id_desa : null,
            'plotting',
        );
        if (!$scheduleCheck['allowed']) {
            return response()->json([
                'ok'      => false,
                'message' => $scheduleCheck['message'],
            ], 403);
        }

        if ($plotting->segmen()->exists() || $plotting->monitoringRealisasi()->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data plotting ini tidak dapat dihapus karena sudah memiliki data segmen atau realisasi terkait.',
            ], 422);
        }

        $plotting->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Plotting anggaran berhasil dihapus.',
        ]);
    }

    private function isDesaCategorySumberDana(string $kode): bool
    {
        $upper = strtoupper(trim($kode));
        if ($upper === '') {
            return false;
        }

        if (in_array($upper, ['DANA_DESA', 'DD', 'DDS', 'ADD', 'PADESA', 'SWADAYA'], true)) {
            return true;
        }

        $ref = RefSumberDana::query()
            ->whereRaw('UPPER(kode) = ?', [$upper])
            ->first();

        return $ref !== null && strcasecmp((string) $ref->kategori, 'Desa') === 0;
    }
}

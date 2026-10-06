<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlottingAnggaranRequest;
use App\Http\Requests\Admin\UpdatePlottingAnggaranRequest;
use App\Models\PlottingAnggaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ])->forUser($user);

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

        // Search nama kegiatan / jenis bantuan
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nama_kegiatan', 'ilike', $search)
                  ->orWhere('jenis_bantuan', 'ilike', $search)
                  ->orWhere('lokasi_kegiatan', 'ilike', $search);
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

        // Auto-lock wilayah jika user memiliki batasan wilayah
        if ($user && !$user->hasRole('admin') && !$user->hasRole('verifierBappeda')) {
            if ($user->id_kecamatan) {
                $validated['id_kecamatan'] = $user->id_kecamatan;
            }
            if ($user->id_desa) {
                $validated['id_desa'] = $user->id_desa;
            }
        }

        $plotting = new PlottingAnggaran();
        $plotting->id = (string) Str::uuid();
        $plotting->fill($validated);
        $plotting->user_id = $user->uuid;
        $plotting->save();

        $fresh = PlottingAnggaran::with(['kecamatan:id,nama_kecamatan', 'desa:id,nama_desa'])
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

        $validated = $request->validated();

        $plotting->fill($validated);
        $plotting->save();

        $fresh = PlottingAnggaran::with(['kecamatan:id,nama_kecamatan', 'desa:id,nama_desa'])
            ->where('id', $plotting->id)
            ->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Plotting anggaran berhasil diperbarui.',
            'data'    => $fresh,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $plotting = PlottingAnggaran::where('id', $id)->first();
        if (!$plotting) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data plotting anggaran tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('delete', $plotting);

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
}

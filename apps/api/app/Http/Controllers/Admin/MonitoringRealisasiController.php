<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusMonitoring;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateRevisiMonitoringRequest;
use App\Http\Requests\Admin\StoreMonitoringRealisasiRequest;
use App\Http\Resources\MonitoringRealisasiResource;
use App\Models\MonitoringRealisasi;
use App\Models\MonitoringRealisasiItem;
use App\Models\MonitoringRealisasiRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MonitoringRealisasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MonitoringRealisasi::class);

        $user = $request->user();
        $query = MonitoringRealisasi::with([
            'plotting:id,nama_kegiatan,target_pagu_anggaran,target_panjang_m',
            'segmen:id,namobj,panjang,kondisi',
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'author:uuid,name,email',
        ])->forUser($user);

        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
        }

        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', (int) $request->input('id_kecamatan'));
        }

        if ($request->filled('id_desa')) {
            $query->where('id_desa', (int) $request->input('id_desa'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('id_plotting')) {
            $query->where('id_plotting', $request->input('id_plotting'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nomor_ba', 'ilike', $search)
                  ->orWhere('keterangan', 'ilike', $search);
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('tahun_anggaran', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'ok'   => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $monitoring = MonitoringRealisasi::with([
            'plotting:id,nama_kegiatan,target_pagu_anggaran,target_panjang_m,sumber_dana',
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'author:uuid,name,email',
            'items.segmen.tipe',
            'revisions.author:uuid,name,email',
        ])->where('id', $id)->first();

        if (!$monitoring) {
            return response()->json([
                'ok'      => false,
                'message' => 'Berita Acara monitoring realisasi tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('view', $monitoring);

        return response()->json([
            'ok'   => true,
            'data' => $monitoring,
        ]);
    }

    public function store(StoreMonitoringRealisasiRequest $request): JsonResponse
    {
        Gate::authorize('create', MonitoringRealisasi::class);

        $user = $request->user();
        $validated = $request->validated();

        // Cek keunikan (nomor_ba, tahun_anggaran)
        $exists = MonitoringRealisasi::where('nomor_ba', $validated['nomor_ba'])
            ->where('tahun_anggaran', $validated['tahun_anggaran'])
            ->exists();

        if ($exists) {
            return response()->json([
                'ok'      => false,
                'message' => "Nomor BA '{$validated['nomor_ba']}' sudah pernah digunakan pada tahun anggaran {$validated['tahun_anggaran']}.",
            ], 422);
        }

        return DB::transaction(function () use ($validated, $user) {
            $monitoring = new MonitoringRealisasi();
            $monitoring->id = (string) Str::uuid();
            $monitoring->fill($validated);
            $monitoring->user_id = $user->uuid;
            $monitoring->status = $validated['status'] ?? StatusMonitoring::Draft->value;
            $monitoring->save();

            // Simpan item segmen terkait
            if (!empty($validated['segmen_ids'])) {
                $uniqueSegmenIds = array_unique($validated['segmen_ids']);
                foreach ($uniqueSegmenIds as $segmenId) {
                    MonitoringRealisasiItem::create([
                        'id'            => (string) Str::uuid(),
                        'id_monitoring' => $monitoring->id,
                        'id_segmen'     => $segmenId,
                    ]);
                }
            }

            $fresh = MonitoringRealisasi::with([
                'plotting', 'items.segmen', 'segmen',
            ])->find($monitoring->id);

            return response()->json([
                'ok'      => true,
                'message' => 'Berita Acara monitoring realisasi berhasil disimpan.',
                'data'    => $fresh,
            ], 201);
        });
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $monitoring = MonitoringRealisasi::where('id', $id)->first();
        if (!$monitoring) {
            return response()->json([
                'ok'      => false,
                'message' => 'Berita Acara monitoring realisasi tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('update', $monitoring);

        $validated = $request->validate([
            'nomor_ba'          => ['sometimes', 'string', 'max:255'],
            'id_plotting'       => ['sometimes', 'uuid', 'exists:plotting_anggaran,id'],
            'id_kecamatan'      => ['sometimes', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa'           => ['sometimes', 'integer', 'exists:bataswilayah_desa,id'],
            'tahun_anggaran'    => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'sumber_dana'       => ['sometimes', 'string', 'max:255'],
            'rencana_panjang'   => ['sometimes', 'numeric', 'min:0'],
            'realisasi_panjang' => ['sometimes', 'numeric', 'min:0'],
            'status'            => ['nullable', 'string', 'in:draft,submitted,approved,rejected,reverted'],
            'keterangan'        => ['nullable', 'string'],
            'segmen_ids'        => ['nullable', 'array'],
            'segmen_ids.*'      => ['uuid', 'exists:infrastruktur_segmen,id'],
        ]);

        return DB::transaction(function () use ($monitoring, $validated) {
            $monitoring->fill($validated);
            $monitoring->save();

            if (isset($validated['segmen_ids'])) {
                // Sync segmen items
                $monitoring->items()->delete();
                $uniqueSegmenIds = array_unique($validated['segmen_ids']);
                foreach ($uniqueSegmenIds as $segmenId) {
                    MonitoringRealisasiItem::create([
                        'id'            => (string) Str::uuid(),
                        'id_monitoring' => $monitoring->id,
                        'id_segmen'     => $segmenId,
                    ]);
                }
            }

            $fresh = MonitoringRealisasi::with([
                'plotting', 'items.segmen', 'segmen',
            ])->find($monitoring->id);

            return response()->json([
                'ok'      => true,
                'message' => 'Berita Acara monitoring realisasi berhasil diperbarui.',
                'data'    => $fresh,
            ]);
        });
    }

    /**
     * Merekam revisi dan snapshot perubahan Berita Acara.
     */
    public function revisi(CreateRevisiMonitoringRequest $request, string $id): JsonResponse
    {
        $monitoring = MonitoringRealisasi::where('id', $id)->first();
        if (!$monitoring) {
            return response()->json([
                'ok'      => false,
                'message' => 'Berita Acara monitoring realisasi tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('revisi', $monitoring);

        $validated = $request->validated();
        $user = $request->user();

        $revision = DB::transaction(function () use ($monitoring, $validated, $user) {
            return $monitoring->recordRevision($validated['catatan_revisi'], $user);
        });

        return response()->json([
            'ok'       => true,
            'message'  => 'Berita Acara berhasil dikembalikan untuk revisi (snapshot tersimpan).',
            'data'     => $monitoring->fresh(['plotting', 'items.segmen', 'segmen', 'latestRevision.author:uuid,name,email']),
            'revision' => $revision,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $monitoring = MonitoringRealisasi::where('id', $id)->first();
        if (!$monitoring) {
            return response()->json([
                'ok'      => false,
                'message' => 'Berita Acara monitoring realisasi tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('delete', $monitoring);

        $monitoring->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Berita Acara monitoring realisasi berhasil dihapus.',
        ]);
    }
}

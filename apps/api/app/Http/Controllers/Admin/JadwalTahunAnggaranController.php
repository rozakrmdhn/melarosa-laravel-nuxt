<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasWilayahDesa;
use App\Models\JadwalTahunAnggaran;
use App\Models\JadwalTahunAnggaranWilayah;
use App\Services\JadwalAnggaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JadwalTahunAnggaranController extends Controller
{
    public function __construct(
        private readonly JadwalAnggaranService $jadwalService,
    ) {
    }

    /**
     * Endpoint untuk seluruh role editor: membaca jadwal tahun anggaran aktif sesuai wilayah login.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        $filterKec = $request->filled('id_kecamatan') ? (int) $request->input('id_kecamatan') : null;
        $filterDesa = $request->filled('id_desa') ? (int) $request->input('id_desa') : null;

        $result = $this->jadwalService->periodsForUser($user, $filterKec, $filterDesa);

        return response()->json([
            'ok'         => true,
            'configured' => $result['configured'],
            'is_bypass'  => $result['is_bypass'],
            'data'       => $result['periods'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManager($request->user());

        $jadwals = JadwalTahunAnggaran::with([
            'updater:uuid,name,email',
            'wilayahOverrides.kecamatan:id,nama_kecamatan',
            'wilayahOverrides.desa:id,nama_desa',
            'wilayahOverrides.creator:uuid,name,email',
        ])
            ->orderByDesc('tahun_anggaran')
            ->get();

        return response()->json([
            'ok'   => true,
            'data' => $jadwals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManager($user);

        $validated = $request->validate([
            'tahun_anggaran' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:jadwal_tahun_anggaran,tahun_anggaran'],
            'nama_periode'   => ['required', 'string', 'max:255'],
            'status_jadwal'  => ['required', 'string', Rule::in(['terjadwal', 'dibuka', 'ditutup'])],
            'mulai_at'       => ['nullable', 'date'],
            'selesai_at'     => ['nullable', 'date', 'after_or_equal:mulai_at'],
            'allow_plotting' => ['nullable', 'boolean'],
            'allow_digitasi' => ['nullable', 'boolean'],
            'allow_non_pagu' => ['nullable', 'boolean'],
            'catatan'        => ['nullable', 'string'],
        ]);

        $jadwal = new JadwalTahunAnggaran();
        $jadwal->id = (string) Str::uuid();
        $jadwal->fill($validated);
        $jadwal->allow_plotting = $validated['allow_plotting'] ?? true;
        $jadwal->allow_digitasi = $validated['allow_digitasi'] ?? true;
        $jadwal->allow_non_pagu = $validated['allow_non_pagu'] ?? true;
        $jadwal->updated_by = $user?->uuid;
        $jadwal->save();

        $fresh = JadwalTahunAnggaran::with([
            'updater:uuid,name,email',
            'wilayahOverrides.kecamatan:id,nama_kecamatan',
            'wilayahOverrides.desa:id,nama_desa',
        ])->find($jadwal->id);

        return response()->json([
            'ok'      => true,
            'message' => "Jadwal Tahun Anggaran {$jadwal->tahun_anggaran} berhasil dibuat.",
            'data'    => $fresh,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManager($user);

        $jadwal = JadwalTahunAnggaran::find($id);
        if (!$jadwal) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jadwal tahun anggaran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'tahun_anggaran' => [
                'sometimes',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('jadwal_tahun_anggaran', 'tahun_anggaran')->ignore($jadwal->id, 'id'),
            ],
            'nama_periode'   => ['sometimes', 'string', 'max:255'],
            'status_jadwal'  => ['sometimes', 'string', Rule::in(['terjadwal', 'dibuka', 'ditutup'])],
            'mulai_at'       => ['nullable', 'date'],
            'selesai_at'     => ['nullable', 'date', 'after_or_equal:mulai_at'],
            'allow_plotting' => ['sometimes', 'boolean'],
            'allow_digitasi' => ['sometimes', 'boolean'],
            'allow_non_pagu' => ['sometimes', 'boolean'],
            'catatan'        => ['nullable', 'string'],
        ]);

        $jadwal->fill($validated);
        $jadwal->updated_by = $user?->uuid;
        $jadwal->save();

        $fresh = JadwalTahunAnggaran::with([
            'updater:uuid,name,email',
            'wilayahOverrides.kecamatan:id,nama_kecamatan',
            'wilayahOverrides.desa:id,nama_desa',
        ])->find($jadwal->id);

        return response()->json([
            'ok'      => true,
            'message' => "Jadwal Tahun Anggaran {$jadwal->tahun_anggaran} berhasil diperbarui.",
            'data'    => $fresh,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeManager($request->user());

        $jadwal = JadwalTahunAnggaran::find($id);
        if (!$jadwal) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jadwal tahun anggaran tidak ditemukan.',
            ], 404);
        }

        $jadwal->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Jadwal tahun anggaran berhasil dihapus.',
        ]);
    }

    /**
     * Tambahkan atau perbarui pengaturan khusus (override) untuk salah satu desa atau kecamatan tertentu.
     */
    public function storeOverride(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManager($user);

        $jadwal = JadwalTahunAnggaran::find($id);
        if (!$jadwal) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data jadwal tahun anggaran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'id_kecamatan'   => ['nullable', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa'        => ['nullable', 'integer', 'exists:bataswilayah_desa,id'],
            'mode_akses'     => ['required', 'string', Rule::in(['buka_paksa', 'tutup_paksa'])],
            'mulai_at'       => ['nullable', 'date'],
            'selesai_at'     => ['nullable', 'date', 'after_or_equal:mulai_at'],
            'allow_plotting' => ['nullable', 'boolean'],
            'allow_digitasi' => ['nullable', 'boolean'],
            'allow_non_pagu' => ['nullable', 'boolean'],
            'alasan'         => ['nullable', 'string'],
        ]);

        if (empty($validated['id_kecamatan']) && empty($validated['id_desa'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Pilih minimal satu Desa atau Kecamatan untuk aturan khusus wilayah.',
            ], 422);
        }

        // Lengkapi id_kecamatan otomatis bila hanya id_desa yang dipilih
        if (!empty($validated['id_desa']) && empty($validated['id_kecamatan'])) {
            $desa = BatasWilayahDesa::find($validated['id_desa']);
            if ($desa) {
                $validated['id_kecamatan'] = (int) $desa->id_kecamatan;
            }
        }

        // Hindari duplikasi aturan aktif untuk wilayah yang persis sama pada jadwal ini
        $existing = JadwalTahunAnggaranWilayah::query()
            ->where('jadwal_id', $jadwal->id)
            ->when(
                !empty($validated['id_desa']),
                fn ($q) => $q->where('id_desa', (int) $validated['id_desa']),
                fn ($q) => $q->whereNull('id_desa')->where('id_kecamatan', (int) $validated['id_kecamatan']),
            )
            ->first();

        $override = $existing ?? new JadwalTahunAnggaranWilayah();
        if (!$existing) {
            $override->id = (string) Str::uuid();
            $override->jadwal_id = $jadwal->id;
            $override->created_by = $user?->uuid;
        }
        $override->fill($validated);
        $override->updated_by = $user?->uuid;
        $override->save();

        $fresh = JadwalTahunAnggaranWilayah::with([
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'creator:uuid,name,email',
        ])->find($override->id);

        return response()->json([
            'ok'      => true,
            'message' => 'Pengaturan khusus wilayah berhasil disimpan.',
            'data'    => $fresh,
        ], $existing ? 200 : 201);
    }

    public function destroyOverride(Request $request, string $id, string $overrideId): JsonResponse
    {
        $this->authorizeManager($request->user());

        $override = JadwalTahunAnggaranWilayah::query()
            ->where('jadwal_id', $id)
            ->where('id', $overrideId)
            ->first();

        if (!$override) {
            return response()->json([
                'ok'      => false,
                'message' => 'Data pengaturan wilayah tidak ditemukan.',
            ], 404);
        }

        $override->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Pengaturan khusus wilayah berhasil dihapus.',
        ]);
    }

    private function authorizeManager(?\App\Models\User $user): void
    {
        if (!$user) {
            abort(403, 'Tidak terautentikasi.');
        }

        if ($user->isAdminOrBappeda() || $user->checkPermissionTo('jadwal-anggaran-manage')) {
            return;
        }

        abort(403, 'Hanya Admin atau Bappeda yang berwenang mengatur penjadwalan tahun anggaran.');
    }
}

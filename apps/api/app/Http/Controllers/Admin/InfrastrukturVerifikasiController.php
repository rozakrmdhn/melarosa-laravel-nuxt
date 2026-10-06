<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusVerifikasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyBappedaRequest;
use App\Http\Requests\Admin\VerifyKecamatanRequest;
use App\Models\InfrastrukturSegmen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InfrastrukturVerifikasiController extends Controller
{
    /**
     * Tingkat 1: Desa mengajukan verifikasi segmen (draft -> submitted_desa).
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('submitDesa', $segmen);

        $currentStatus = $segmen->status_verifikasi?->value ?? $segmen->status_verifikasi;
        if (!in_array($currentStatus, [StatusVerifikasi::Draft->value, StatusVerifikasi::RejectedKecamatan->value], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Hanya segmen berstatus draft atau ditolak kecamatan yang dapat diajukan verifikasi.',
            ], 422);
        }

        $segmen->status_verifikasi = StatusVerifikasi::SubmittedDesa->value;
        $segmen->submitted_desa_at = now();
        $segmen->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Segmen berhasil diajukan untuk verifikasi kecamatan.',
            'data'    => $segmen,
        ]);
    }

    /**
     * Tingkat 2: Verifikator Kecamatan memeriksa dan menyetujui / menolak segmen.
     */
    public function verifyKecamatan(VerifyKecamatanRequest $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('verifyKecamatan', $segmen);

        $validated = $request->validated();
        $user = $request->user();

        if ($validated['action'] === 'approve') {
            $segmen->status_verifikasi     = StatusVerifikasi::VerifiedKecamatan->value;
            $segmen->verified_kecamatan_by = $user->uuid;
            $segmen->verified_kecamatan_at = now();
            $segmen->catatan_kecamatan    = $validated['catatan'] ?? null;
            $segmen->save();

            $message = 'Segmen berhasil disetujui di tingkat kecamatan dan diteruskan ke Bappeda.';
        } else {
            $segmen->status_verifikasi     = StatusVerifikasi::RejectedKecamatan->value;
            $segmen->verified_kecamatan_by = $user->uuid;
            $segmen->verified_kecamatan_at = now();
            $segmen->catatan_kecamatan    = $validated['catatan'];
            $segmen->save();

            $message = 'Segmen ditolak oleh kecamatan dan dikembalikan ke desa untuk perbaikan.';
        }

        return response()->json([
            'ok'      => true,
            'message' => $message,
            'data'    => $segmen,
        ]);
    }

    /**
     * Tingkat 3: Verifikator Bappeda mengesahkan final atau menolak segmen.
     */
    public function verifyBappeda(VerifyBappedaRequest $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::where('id', $id)->first();
        if (!$segmen) {
            return response()->json([
                'ok'      => false,
                'message' => 'Segmen infrastruktur tidak ditemukan.',
            ], 404);
        }

        Gate::authorize('verifyBappeda', $segmen);

        $validated = $request->validated();
        $user = $request->user();

        if ($validated['action'] === 'approve') {
            $segmen->status_verifikasi   = StatusVerifikasi::VerifiedBappeda->value;
            $segmen->verified_bappeda_by = $user->uuid;
            $segmen->verified_bappeda_at = now();
            $segmen->catatan_bappeda     = $validated['catatan'] ?? null;
            $segmen->save();

            $message = 'Segmen berhasil disetujui final oleh Bappeda.';
        } else {
            $segmen->status_verifikasi   = StatusVerifikasi::RejectedBappeda->value;
            $segmen->verified_bappeda_by = $user->uuid;
            $segmen->verified_bappeda_at = now();
            $segmen->catatan_bappeda     = $validated['catatan'];
            $segmen->save();

            $message = 'Segmen ditolak oleh Bappeda dan memerlukan perbaikan.';
        }

        return response()->json([
            'ok'      => true,
            'message' => $message,
            'data'    => $segmen,
        ]);
    }
}

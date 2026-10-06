<?php

namespace App\Policies;

use App\Enums\StatusVerifikasi;
use App\Models\InfrastrukturSegmen;
use App\Models\User;

class InfrastrukturSegmenPolicy
{
    private function hasPerm(User $user, array $perms): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        foreach ($perms as $perm) {
            if ($user->hasPermissionTo($perm)) {
                return true;
            }
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, [
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
        ]);
    }

    public function view(User $user, InfrastrukturSegmen $segmen): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasPerm($user, [
            'infrastruktur-segmen-create',
        ]);
    }

    public function update(User $user, InfrastrukturSegmen $segmen): bool
    {
        if (!$this->hasPerm($user, ['infrastruktur-segmen-update'])) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        // Verifier Kecamatan dapat mengelola seluruh segmen di kecamatannya
        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return (int) $segmen->id_kecamatan === (int) $user->id_kecamatan;
        }

        // Operator Desa hanya dapat mengedit segmen desanya saat status draft atau rejected_kecamatan
        if ($user->id_desa) {
            if ((int) $segmen->id_desa !== (int) $user->id_desa) {
                return false;
            }

            return in_array($segmen->status_verifikasi?->value ?? $segmen->status_verifikasi, [
                StatusVerifikasi::Draft->value,
                StatusVerifikasi::RejectedKecamatan->value,
            ], true);
        }

        return false;
    }

    public function delete(User $user, InfrastrukturSegmen $segmen): bool
    {
        // User desa secara default tidak diizinkan menghapus data aset
        if ($user->id_desa) {
            return false;
        }

        if (!$this->hasPerm($user, ['infrastruktur-segmen-delete'])) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        // Verifier Kecamatan hanya dapat menghapus segmen berstatus draft di wilayahnya
        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            if ((int) $segmen->id_kecamatan !== (int) $user->id_kecamatan) {
                return false;
            }

            return in_array($segmen->status_verifikasi?->value ?? $segmen->status_verifikasi, [
                StatusVerifikasi::Draft->value,
                StatusVerifikasi::RejectedKecamatan->value,
            ], true);
        }

        return false;
    }

    /**
     * Otorisasi pengajuan verifikasi oleh Desa.
     */
    public function submitDesa(User $user, InfrastrukturSegmen $segmen): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa && (int) $segmen->id_desa !== (int) $user->id_desa) {
            return false;
        }

        $currentStatus = $segmen->status_verifikasi?->value ?? $segmen->status_verifikasi;

        return in_array($currentStatus, [
            StatusVerifikasi::Draft->value,
            StatusVerifikasi::RejectedKecamatan->value,
        ], true);
    }

    /**
     * Otorisasi verifikasi tingkat Kecamatan (hanya untuk segmen di kecamatannya yang statusnya submitted_desa).
     */
    public function verifyKecamatan(User $user, InfrastrukturSegmen $segmen): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$this->hasPerm($user, ['infrastruktur-segmen-verify-kecamatan'])) {
            return false;
        }

        // Harus berasal dari kecamatan yang sama
        if ((int) $segmen->id_kecamatan !== (int) $user->id_kecamatan) {
            return false;
        }

        // Status harus sudah diajukan oleh desa
        $currentStatus = $segmen->status_verifikasi?->value ?? $segmen->status_verifikasi;

        return $currentStatus === StatusVerifikasi::SubmittedDesa->value;
    }

    /**
     * Otorisasi verifikasi tingkat Bappeda (semua kecamatan, segmen harus sudah lolos verified_kecamatan).
     */
    public function verifyBappeda(User $user, InfrastrukturSegmen $segmen): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$this->hasPerm($user, ['infrastruktur-segmen-verify-bappeda'])) {
            return false;
        }

        // Status harus sudah disetujui kecamatan
        $currentStatus = $segmen->status_verifikasi?->value ?? $segmen->status_verifikasi;

        return $currentStatus === StatusVerifikasi::VerifiedKecamatan->value;
    }
}

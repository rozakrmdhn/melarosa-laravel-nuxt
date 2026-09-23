<?php

namespace App\Policies;

use App\Models\JalanPorosDesa;
use App\Models\User;

class JalanPorosDesaPolicy
{
    /**
     * Helper pemeriksaan Spatie Permissions fleksibel (dot atau dash).
     */
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

    /**
     * Otorisasi melihat daftar jalan poros desa.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, [
            'jalan-poros-desa-view',
            'jalan-poros-desa-manage',
        ]);
    }

    /**
     * Otorisasi melihat detail satu ruas jalan poros desa.
     * Untuk operasi read/get tidak dibatasi wilayah kerja pengguna.
     */
    public function view(User $user, JalanPorosDesa $ruas): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Otorisasi menambah ruas jalan poros desa baru.
     */
    public function create(User $user): bool
    {
        return $this->hasPerm($user, [
            'jalan-poros-desa-create',
            'jalan-poros-desa-manage',
        ]);
    }

    /**
     * Otorisasi memperbarui data atau geometri ruas jalan poros desa.
     */
    public function update(User $user, JalanPorosDesa $ruas): bool
    {
        if (!$this->hasPerm($user, [
            'jalan-poros-desa-update',
            'jalan-poros-desa-manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $ruas->id_desa === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $ruas->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    /**
     * Otorisasi menghapus ruas jalan poros desa.
     */
    public function delete(User $user, JalanPorosDesa $ruas): bool
    {
        // User tingkat desa secara default tidak diizinkan menghapus data infrastruktur jalan
        if ($user->id_desa) {
            return false;
        }

        if (!$this->hasPerm($user, [
            'jalan-poros-desa-delete',
            'jalan-poros-desa-manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_kecamatan) {
            return (int) $ruas->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    /**
     * Otorisasi memecah (split) ruas jalan poros desa.
     */
    public function split(User $user, JalanPorosDesa $ruas): bool
    {
        if (!$this->hasPerm($user, [
            'jalan-poros-desa-split',
            'jalan-poros-desa-manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $ruas->id_desa === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $ruas->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }
}

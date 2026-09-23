<?php

namespace App\Policies;

use App\Models\BatasWilayahKecamatan;
use App\Models\User;

class BatasWilayahKecamatanPolicy
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
            'batas-kecamatan-view',
            'batas-kecamatan-manage',
        ]);
    }

    public function view(User $user, BatasWilayahKecamatan $kecamatan): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_kecamatan) {
            return (int) $kecamatan->id === (int) $user->id_kecamatan;
        }

        return true;
    }

    public function create(User $user): bool
    {
        // User yang dibatasi kecamatan/desa tidak diperkenankan menambah kecamatan baru
        if ($user->id_kecamatan || $user->id_desa) {
            return false;
        }

        return $this->hasPerm($user, [
            'batas-kecamatan-create',
            'batas-kecamatan-manage',
        ]);
    }

    public function update(User $user, BatasWilayahKecamatan $kecamatan): bool
    {
        if (!$this->hasPerm($user, [
            'batas-kecamatan-update',
            'batas-kecamatan-manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_kecamatan) {
            return (int) $kecamatan->id === (int) $user->id_kecamatan;
        }

        return false;
    }

    public function delete(User $user, BatasWilayahKecamatan $kecamatan): bool
    {
        // Hanya admin/pengguna berwenang tanpa batasan wilayah yang dapat menghapus kecamatan
        if ($user->id_kecamatan || $user->id_desa) {
            return false;
        }

        return $this->hasPerm($user, [
            'batas-kecamatan-delete',
            'batas-kecamatan-manage',
        ]);
    }
}

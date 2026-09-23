<?php

namespace App\Policies;

use App\Models\BatasWilayahDesa;
use App\Models\User;

class BatasWilayahDesaPolicy
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
            'batas-desa-view',
            'batas-desa-manage',
        ]);
    }

    public function view(User $user, BatasWilayahDesa $desa): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $desa->id === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $desa->id_kecamatan === (int) $user->id_kecamatan;
        }

        return true;
    }

    public function create(User $user): bool
    {
        // User tingkat desa tidak boleh membuat desa baru
        if ($user->id_desa) {
            return false;
        }

        return $this->hasPerm($user, [
            'batas-desa-create',
            'batas-desa-manage',
        ]);
    }

    public function update(User $user, BatasWilayahDesa $desa): bool
    {
        if (!$this->hasPerm($user, [
            'batas-desa-update',
            'batas-desa-manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $desa->id === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $desa->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    public function delete(User $user, BatasWilayahDesa $desa): bool
    {
        // User tingkat desa tidak boleh menghapus desa
        if ($user->id_desa) {
            return false;
        }

        if (!$this->hasPerm($user, [
            'batas-desa-delete',
            'batas-desa-manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_kecamatan) {
            return (int) $desa->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }
}

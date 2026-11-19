<?php

namespace App\Policies;

use App\Models\PlottingAnggaran;
use App\Models\User;

class PlottingAnggaranPolicy
{
    private function hasPerm(User $user, array $perms): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        foreach ($perms as $perm) {
            if ($user->checkPermissionTo($perm)) {
                return true;
            }
        }

        return false;
    }

    private function isDesaOperator(User $user): bool
    {
        return $user->hasAnyRole(['operatorDesa', 'operator-desa', 'desa']) || (bool) $user->id_desa;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, [
            'plotting-anggaran-view',
            'plotting-anggaran-manage',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
        ]);
    }

    public function view(User $user, PlottingAnggaran $plotting): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->isAdminOrBappeda()) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $plotting->id_desa === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $plotting->id_kecamatan === (int) $user->id_kecamatan;
        }

        return true;
    }

    public function create(User $user): bool
    {
        if ($user->isAdminOrBappeda() || $this->hasPerm($user, ['plotting-anggaran-manage'])) {
            return true;
        }

        return $this->isDesaOperator($user) && $this->hasPerm($user, ['infrastruktur-segmen-create']);
    }

    public function update(User $user, PlottingAnggaran $plotting): bool
    {
        if ($user->isAdminOrBappeda() && $this->hasPerm($user, ['plotting-anggaran-manage'])) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($this->isDesaOperator($user) && $user->id_desa) {
            if (!$this->hasPerm($user, ['plotting-anggaran-manage', 'infrastruktur-segmen-create', 'infrastruktur-segmen-update'])) {
                return false;
            }
            return (int) $plotting->id_desa === (int) $user->id_desa;
        }

        if ($user->id_kecamatan && $this->hasPerm($user, ['plotting-anggaran-manage'])) {
            return (int) $plotting->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    public function delete(User $user, PlottingAnggaran $plotting): bool
    {
        return $this->update($user, $plotting);
    }
}

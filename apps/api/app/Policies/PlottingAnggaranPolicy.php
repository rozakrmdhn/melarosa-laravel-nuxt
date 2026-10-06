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
            if ($user->hasPermissionTo($perm)) {
                return true;
            }
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, [
            'plotting-anggaran-view',
            'plotting-anggaran-manage',
        ]);
    }

    public function view(User $user, PlottingAnggaran $plotting): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasPerm($user, [
            'plotting-anggaran-manage',
        ]);
    }

    public function update(User $user, PlottingAnggaran $plotting): bool
    {
        if (!$this->hasPerm($user, ['plotting-anggaran-manage'])) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return (int) $plotting->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    public function delete(User $user, PlottingAnggaran $plotting): bool
    {
        return $this->update($user, $plotting);
    }
}

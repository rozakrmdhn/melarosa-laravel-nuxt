<?php

namespace App\Policies;

use App\Models\BkkdVerifikasi;
use App\Models\User;

class BkkdVerifikasiPolicy
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
        return $this->hasPerm($user, ['bkkd-verifikasi-view']);
    }

    public function view(User $user, BkkdVerifikasi $verifikasi): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            $verifikasi->loadMissing('proposal');

            return (int) $verifikasi->proposal?->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->hasPerm($user, ['bkkd-verifikasi-manage']);
    }

    public function update(User $user, BkkdVerifikasi $verifikasi): bool
    {
        return $this->hasPerm($user, ['bkkd-verifikasi-manage']);
    }
}

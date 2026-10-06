<?php

namespace App\Policies;

use App\Models\MonitoringRealisasi;
use App\Models\User;

class MonitoringRealisasiPolicy
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
            'monitoring-realisasi-view',
            'monitoring-realisasi-manage',
        ]);
    }

    public function view(User $user, MonitoringRealisasi $monitoring): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasPerm($user, [
            'monitoring-realisasi-manage',
        ]);
    }

    public function update(User $user, MonitoringRealisasi $monitoring): bool
    {
        if (!$this->hasPerm($user, ['monitoring-realisasi-manage'])) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return (int) $monitoring->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    public function delete(User $user, MonitoringRealisasi $monitoring): bool
    {
        return $this->update($user, $monitoring);
    }

    public function revisi(User $user, MonitoringRealisasi $monitoring): bool
    {
        return $this->update($user, $monitoring);
    }
}

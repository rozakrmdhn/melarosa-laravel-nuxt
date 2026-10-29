<?php

namespace App\Policies;

use App\Models\BkkdProposal;
use App\Models\User;

class BkkdProposalPolicy
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
        return $this->hasPerm($user, ['bkkd-proposal-view']);
    }

    public function view(User $user, BkkdProposal $proposal): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return (int) $proposal->id_kecamatan === (int) $user->id_kecamatan;
        }

        if ($user->id_desa) {
            return (int) $proposal->id_desa === (int) $user->id_desa;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->hasPerm($user, ['bkkd-proposal-create']);
    }

    public function update(User $user, BkkdProposal $proposal): bool
    {
        if (!$this->hasPerm($user, ['bkkd-proposal-update'])) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return (int) $proposal->id_kecamatan === (int) $user->id_kecamatan;
        }

        if ($user->id_desa) {
            return (int) $proposal->id_desa === (int) $user->id_desa;
        }

        return false;
    }

    public function delete(User $user, BkkdProposal $proposal): bool
    {
        if (!$this->hasPerm($user, ['bkkd-proposal-delete'])) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return true;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return (int) $proposal->id_kecamatan === (int) $user->id_kecamatan;
        }

        if ($user->id_desa) {
            return (int) $proposal->id_desa === (int) $user->id_desa;
        }

        return false;
    }

    public function restore(User $user, BkkdProposal $proposal): bool
    {
        return $this->delete($user, $proposal);
    }
}

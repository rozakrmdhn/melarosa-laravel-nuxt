<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admin bisa lihat semua user.
     * User dengan permission users-view bisa melihat daftar user.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('users-view');
    }

    /**
     * Lihat detail user spesifik.
     */
    public function view(User $actor, User $target): bool
    {
        if (!$actor->hasPermissionTo('users-view')) {
            return false;
        }

        return $this->withinActorWilayah($actor, $target);
    }

    /**
     * Buat user baru.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('users-create');
    }

    /**
     * Update user.
     */
    public function update(User $actor, User $target): bool
    {
        if (!$actor->hasPermissionTo('users-update')) {
            return false;
        }

        return $this->withinActorWilayah($actor, $target);
    }

    /**
     * Hapus user.
     */
    public function delete(User $actor, User $target): bool
    {
        if (!$actor->hasPermissionTo('users-delete')) {
            return false;
        }

        // Tidak boleh menghapus diri sendiri
        if ($actor->id === $target->id) {
            return false;
        }

        return $this->withinActorWilayah($actor, $target);
    }

    /**
     * Cek apakah target user berada dalam wilayah yang sama dengan actor.
     * Admin (role 'admin') tidak dibatasi wilayah.
     * Actor tanpa id_kecamatan dan id_desa dianggap tidak dibatasi.
     */
    private function withinActorWilayah(User $actor, User $target): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        // Jika actor dibatasi per kecamatan
        if ($actor->id_kecamatan) {
            return $target->id_kecamatan === $actor->id_kecamatan;
        }

        // Jika actor dibatasi per desa
        if ($actor->id_desa) {
            return $target->id_desa === $actor->id_desa;
        }

        return true;
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BkkdPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'bkkd-proposal-view',
            'bkkd-proposal-create',
            'bkkd-proposal-update',
            'bkkd-proposal-delete',
            'bkkd-verifikasi-view',
            'bkkd-verifikasi-manage',
            'bkkd-riwayat-view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Admin receives all permissions
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        // verifierBappeda receives full BKKD operational permissions
        $verifierBappeda = Role::where('name', 'verifierBappeda')->first();
        if ($verifierBappeda) {
            $verifierBappeda->givePermissionTo($permissions);
        }

        // verifierKecamatan receives read-only view permissions
        $verifierKecamatan = Role::where('name', 'verifierKecamatan')->first();
        if ($verifierKecamatan) {
            $verifierKecamatan->givePermissionTo([
                'bkkd-proposal-view',
                'bkkd-verifikasi-view',
            ]);
        }
    }
}

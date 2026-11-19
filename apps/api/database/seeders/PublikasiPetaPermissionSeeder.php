<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PublikasiPetaPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'publikasi-peta-view',
            'publikasi-peta-create',
            'publikasi-peta-update',
            'publikasi-peta-delete',
            'publikasi-peta-manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        $verifierBappeda = Role::where('name', 'verifierBappeda')->first();
        if ($verifierBappeda) {
            $verifierBappeda->givePermissionTo($permissions);
        }
    }
}

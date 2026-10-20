<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Default core permissions & spatial dataset
        $permissions = [
            'dashboard-view',
            'users-view',
            'users-create',
            'users-update',
            'users-delete',
            'roles-view',
            'roles-create',
            'roles-update',
            'roles-delete',
            'roles-manage',
            'permissions-view',
            'permissions-manage',
            'settings-view',
            'settings-manage',
            'audit-logs-view',
            'layers-view',
            'layers-create',
            'layers-update',
            'layers-delete',
            'layers-manage',
            'batas-kecamatan-view',
            'batas-kecamatan-create',
            'batas-kecamatan-update',
            'batas-kecamatan-delete',
            'batas-kecamatan-manage',
            'batas-desa-view',
            'batas-desa-create',
            'batas-desa-update',
            'batas-desa-delete',
            'batas-desa-manage',
            'jalan-poros-desa-view',
            'jalan-poros-desa-create',
            'jalan-poros-desa-update',
            'jalan-poros-desa-delete',
            'jalan-poros-desa-manage',
            'jalan-poros-desa-split',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Give all permissions to admin
        $adminRole->syncPermissions(Permission::all());

        // Give basic permissions to user
        $userRole->syncPermissions(['dashboard-view', 'users-view']);

        // Create or get admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );

        $adminUser->assignRole($adminRole);

        // Seeder Infrastruktur & Verifikasi
        $this->call([
            InfrastrukturPermissionSeeder::class,
            InfrastrukturMasterSeeder::class,
            BkkdPermissionSeeder::class,
            RefOpdSeeder::class,
            RefBkkdBidangSeeder::class,
        ]);
    }
}

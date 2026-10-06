<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InfrastrukturPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Master Tipe Infrastruktur
            'infrastruktur-tipe-view',
            'infrastruktur-tipe-manage',

            // Referensi Sumber Dana
            'ref-sumber-dana-view',
            'ref-sumber-dana-manage',

            // Plotting Anggaran
            'plotting-anggaran-view',
            'plotting-anggaran-manage',

            // Infrastruktur Segmen (Data Spasial Teknis)
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-delete',

            // State Machine Verifikasi
            'infrastruktur-segmen-verify-kecamatan',
            'infrastruktur-segmen-verify-bappeda',

            // Monitoring & Berita Acara Realisasi
            'monitoring-realisasi-view',
            'monitoring-realisasi-manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $verifierBappeda = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $verifierKecamatan = Role::firstOrCreate(['name' => 'verifierKecamatan', 'guard_name' => 'web']);

        // Admin mendapatkan seluruh permission
        $adminRole->syncPermissions(Permission::all());

        // Role verifierBappeda: Mengelola seluruh segmen dan verifikasi tingkat Bappeda
        $verifierBappeda->syncPermissions([
            'infrastruktur-tipe-view',
            'infrastruktur-tipe-manage',
            'ref-sumber-dana-view',
            'ref-sumber-dana-manage',
            'plotting-anggaran-view',
            'plotting-anggaran-manage',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-delete',
            'infrastruktur-segmen-verify-bappeda',
            'monitoring-realisasi-view',
            'monitoring-realisasi-manage',
        ]);

        // Role verifierKecamatan: Mengelola segmen di kecamatannya dan verifikasi seluruh segmen di kecamatannya
        $verifierKecamatan->syncPermissions([
            'infrastruktur-tipe-view',
            'ref-sumber-dana-view',
            'plotting-anggaran-view',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-verify-kecamatan',
            'monitoring-realisasi-view',
        ]);
    }
}

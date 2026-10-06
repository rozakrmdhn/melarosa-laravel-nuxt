<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InfrastrukturMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Master Tipe Infrastruktur
        $tipeList = [
            [
                'kode' => 'jalan_poros',
                'nama' => 'Jalan Poros Desa',
                'deskripsi' => 'Ruas jalan penghubung utama antar desa atau desa ke kecamatan.',
                'ikon' => 'i-lucide-route',
                'warna' => '#10b981',
                'geom_type' => 'LineString',
                'table_name' => 'infrastruktur_segmen',
                'has_segmen' => true,
                'is_active' => true,
                'sort_order' => 1,
                'config' => json_encode([
                    'stroke_width' => 4,
                    'line_dash' => false,
                ]),
            ],
            [
                'kode' => 'jembatan',
                'nama' => 'Jembatan Desa',
                'deskripsi' => 'Konstruksi jembatan penghubung pada ruas jalan desa.',
                'ikon' => 'i-lucide-bridge',
                'warna' => '#3b82f6',
                'geom_type' => 'LineString',
                'table_name' => 'infrastruktur_segmen',
                'has_segmen' => true,
                'is_active' => true,
                'sort_order' => 2,
                'config' => json_encode([
                    'stroke_width' => 5,
                    'line_dash' => false,
                ]),
            ],
            [
                'kode' => 'drainase_tpt',
                'nama' => 'Drainase & TPT',
                'deskripsi' => 'Saluran drainase tepi jalan dan Tembok Penahan Tanah (TPT).',
                'ikon' => 'i-lucide-waves',
                'warna' => '#f59e0b',
                'geom_type' => 'LineString',
                'table_name' => 'infrastruktur_segmen',
                'has_segmen' => true,
                'is_active' => true,
                'sort_order' => 3,
                'config' => json_encode([
                    'stroke_width' => 3,
                    'line_dash' => true,
                ]),
            ],
        ];

        foreach ($tipeList as $tipe) {
            $existing = DB::table('infrastruktur_tipe')->where('kode', $tipe['kode'])->first();
            if ($existing) {
                DB::table('infrastruktur_tipe')->where('kode', $tipe['kode'])->update([
                    'nama' => $tipe['nama'],
                    'deskripsi' => $tipe['deskripsi'],
                    'ikon' => $tipe['ikon'],
                    'warna' => $tipe['warna'],
                    'geom_type' => $tipe['geom_type'],
                    'table_name' => $tipe['table_name'],
                    'has_segmen' => $tipe['has_segmen'],
                    'is_active' => $tipe['is_active'],
                    'sort_order' => $tipe['sort_order'],
                    'config' => $tipe['config'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('infrastruktur_tipe')->insert(array_merge($tipe, [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // 2. Seed Master Referensi Sumber Dana
        $sumberDanaList = [
            [
                'kode' => 'APBD_KAB',
                'nama' => 'APBD Kabupaten',
                'kategori' => 'Daerah',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'kode' => 'DAK_FISIK',
                'nama' => 'Dana Alokasi Khusus (DAK)',
                'kategori' => 'Pusat',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'kode' => 'BKK_DESA',
                'nama' => 'Bantuan Keuangan Khusus (BKK/Samisade)',
                'kategori' => 'Bantuan',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'kode' => 'BANPROV',
                'nama' => 'Bantuan Keuangan Provinsi (Banprov)',
                'kategori' => 'Provinsi',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'kode' => 'DANA_DESA',
                'nama' => 'Dana Desa (DDS / APBDes)',
                'kategori' => 'Desa',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($sumberDanaList as $sd) {
            $existing = DB::table('ref_sumber_dana')->where('kode', $sd['kode'])->first();
            if ($existing) {
                DB::table('ref_sumber_dana')->where('kode', $sd['kode'])->update([
                    'nama' => $sd['nama'],
                    'kategori' => $sd['kategori'],
                    'is_active' => $sd['is_active'],
                    'sort_order' => $sd['sort_order'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('ref_sumber_dana')->insert(array_merge($sd, [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}

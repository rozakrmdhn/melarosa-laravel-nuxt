<?php

namespace Database\Seeders;

use App\Models\RefOpd;
use Illuminate\Database\Seeder;

class RefOpdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $opdList = [
            ['kode' => 'DPUPR', 'nama' => 'Dinas Pekerjaan Umum dan Penataan Ruang'],
            ['kode' => 'DPMD', 'nama' => 'Dinas Pemberdayaan Masyarakat dan Desa'],
            ['kode' => 'DINKES', 'nama' => 'Dinas Kesehatan'],
            ['kode' => 'DISDIK', 'nama' => 'Dinas Pendidikan'],
            ['kode' => 'DISTAN', 'nama' => 'Dinas Pertanian dan Ketahanan Pangan'],
            ['kode' => 'DISKOMINFO', 'nama' => 'Dinas Komunikasi dan Informatika'],
        ];

        foreach ($opdList as $item) {
            RefOpd::firstOrCreate(
                ['kode' => $item['kode']],
                ['nama' => $item['nama'], 'is_active' => true]
            );
        }
    }
}

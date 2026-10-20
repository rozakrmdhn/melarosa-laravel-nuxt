<?php

namespace Database\Seeders;

use App\Models\RefBkkdBidang;
use App\Models\RefOpd;
use Illuminate\Database\Seeder;

class RefBkkdBidangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dpupr = RefOpd::where('kode', 'DPUBMPR')->orWhere('kode', 'DPUPR')->first();
        $dpmd = RefOpd::where('kode', 'DPMD')->first();
        $dinkes = RefOpd::where('kode', 'DINKES')->first();
        $distan = RefOpd::where('kode', 'DISTAN')->first();

        $bidangList = [
            ['nama' => 'BKK JALAN', 'id_opd_default' => $dpupr?->id],
            ['nama' => 'BKK JEMBATAN', 'id_opd_default' => $dpupr?->id],
            ['nama' => 'BKK TPT', 'id_opd_default' => $dpupr?->id],
            ['nama' => 'Infrastruktur Jalan & Jembatan Desa', 'id_opd_default' => $dpupr?->id],
            ['nama' => 'Drainase & Sanitasi Lingkungan', 'id_opd_default' => $dpupr?->id],
            ['nama' => 'Sarana & Prasarana Kantor / Balai Desa', 'id_opd_default' => $dpmd?->id],
            ['nama' => 'Pemberdayaan Ekonomi & BUMDes', 'id_opd_default' => $dpmd?->id],
            ['nama' => 'Kesehatan Masyarakat & Posyandu', 'id_opd_default' => $dinkes?->id],
            ['nama' => 'Jalan Usaha Tani & Ketahanan Pangan', 'id_opd_default' => $distan?->id],
            ['nama' => 'Lainnya / Non-Fisik', 'id_opd_default' => $dpmd?->id],
        ];

        foreach ($bidangList as $item) {
            RefBkkdBidang::firstOrCreate(
                ['nama' => $item['nama']],
                [
                    'id_opd_default' => $item['id_opd_default'],
                    'is_active' => true,
                ]
            );
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinkronkan nilai historis kolom sumber_dana pada infrastruktur_segmen dan plotting_anggaran
     * ('BKK', 'APBD', 'DD') ke kode resmi pada tabel ref_sumber_dana apabila kode target tersedia
     * dan kode singkat lama tidak terdaftar sebagai kode aktif di ref_sumber_dana.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ref_sumber_dana')) {
            return;
        }

        $legacyToRefMap = [
            'BKK'  => 'BKK_DESA',
            'APBD' => 'APBD_KAB',
            'DD'   => 'DANA_DESA',
        ];

        $existingCodes = DB::table('ref_sumber_dana')
            ->pluck('kode')
            ->map(fn ($k) => strtoupper(trim((string) $k)))
            ->all();

        foreach ($legacyToRefMap as $legacyCode => $targetCode) {
            if (in_array($targetCode, $existingCodes, true) && !in_array($legacyCode, $existingCodes, true)) {
                if (Schema::hasTable('infrastruktur_segmen') && Schema::hasColumn('infrastruktur_segmen', 'sumber_dana')) {
                    DB::table('infrastruktur_segmen')
                        ->whereRaw('UPPER(TRIM(sumber_dana)) = ?', [$legacyCode])
                        ->update(['sumber_dana' => $targetCode]);
                }

                if (Schema::hasTable('plotting_anggaran') && Schema::hasColumn('plotting_anggaran', 'sumber_dana')) {
                    DB::table('plotting_anggaran')
                        ->whereRaw('UPPER(TRIM(sumber_dana)) = ?', [$legacyCode])
                        ->update(['sumber_dana' => $targetCode]);
                }
            }
        }
    }

    public function down(): void
    {
        // Data migration back-mapping omitted intentionally to preserve active ref_sumber_dana assignments.
    }
};

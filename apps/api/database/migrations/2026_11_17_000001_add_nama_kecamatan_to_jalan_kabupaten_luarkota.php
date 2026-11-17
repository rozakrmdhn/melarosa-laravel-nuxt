<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('jalan_kabupaten_luarkota')) {
            return;
        }

        Schema::table('jalan_kabupaten_luarkota', function (Blueprint $table) {
            if (!Schema::hasColumn('jalan_kabupaten_luarkota', 'nama_kecamatan')) {
                $table->text('nama_kecamatan')->nullable()->index();
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('bataswilayah_kecamatan')) {
            $hasWadmkc = Schema::hasColumn('bataswilayah_kecamatan', 'WADMKC');
            $kecNameExpr = $hasWadmkc
                ? 'COALESCE(k.nama_kecamatan, k."WADMKC")'
                : 'k.nama_kecamatan';

            DB::statement("
                UPDATE jalan_kabupaten_luarkota j
                SET nama_kecamatan = sub.kecamatan_list
                FROM (
                    SELECT j2.id,
                           string_agg(DISTINCT {$kecNameExpr}, ', ' ORDER BY {$kecNameExpr}) AS kecamatan_list
                    FROM jalan_kabupaten_luarkota j2
                    JOIN bataswilayah_kecamatan k
                      ON k.geom IS NOT NULL
                     AND ST_Intersects(ST_Transform(j2.geom, 4326), ST_Transform(k.geom, 4326))
                    WHERE j2.geom IS NOT NULL
                    GROUP BY j2.id
                ) sub
                WHERE j.id = sub.id
            ");

            try {
                if (class_exists(\App\Services\MartinTileService::class)) {
                    \App\Services\MartinTileService::purgeCache('jalan_kabupaten_luarkota');
                }
            } catch (\Throwable $e) {
                // Abaikan jika layanan Martin sedang tidak aktif
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jalan_kabupaten_luarkota') && Schema::hasColumn('jalan_kabupaten_luarkota', 'nama_kecamatan')) {
            Schema::table('jalan_kabupaten_luarkota', function (Blueprint $table) {
                $table->dropColumn('nama_kecamatan');
            });
        }
    }
};

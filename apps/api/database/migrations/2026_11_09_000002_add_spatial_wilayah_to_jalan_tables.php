<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction so individual DDL and updates run independently.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enrich jalan_kabupaten_luarkota
        if (Schema::hasTable('jalan_kabupaten_luarkota')) {
            Schema::table('jalan_kabupaten_luarkota', function (Blueprint $table) {
                if (!Schema::hasColumn('jalan_kabupaten_luarkota', 'nama_kecamatan')) {
                    $table->text('nama_kecamatan')->nullable()->index();
                }
                if (!Schema::hasColumn('jalan_kabupaten_luarkota', 'nama_desa')) {
                    $table->text('nama_desa')->nullable();
                }
            });

            // Spatial intersection update
            try {
                DB::statement("
                    UPDATE jalan_kabupaten_luarkota j
                    SET nama_kecamatan = sub.kecamatan_list,
                        nama_desa = sub.desa_list
                    FROM (
                        SELECT j2.id,
                               string_agg(DISTINCT COALESCE(k.nama_kecamatan, k.\"WADMKC\"), ', ') AS kecamatan_list,
                               string_agg(DISTINCT d.nama_desa, ', ') AS desa_list
                        FROM jalan_kabupaten_luarkota j2
                        LEFT JOIN bataswilayah_kecamatan k 
                            ON ST_Intersects(ST_Transform(j2.geom, 4326), ST_Transform(k.geom, 4326))
                        LEFT JOIN bataswilayah_desa d 
                            ON ST_Intersects(ST_Transform(j2.geom, 4326), ST_Transform(d.geom, 4326))
                        GROUP BY j2.id
                    ) sub
                    WHERE j.id = sub.id;
                ");
            } catch (\Throwable $e) {
                // Log and continue
            }
        }

        // 2. Enrich jalan_porosdesa if columns don't exist
        if (Schema::hasTable('jalan_porosdesa')) {
            Schema::table('jalan_porosdesa', function (Blueprint $table) {
                if (!Schema::hasColumn('jalan_porosdesa', 'nama_kecamatan')) {
                    $table->text('nama_kecamatan')->nullable()->index();
                }
                if (!Schema::hasColumn('jalan_porosdesa', 'nama_desa')) {
                    $table->text('nama_desa')->nullable();
                }
            });

            try {
                DB::statement("
                    UPDATE jalan_porosdesa j
                    SET nama_kecamatan = sub.kecamatan_list,
                        nama_desa = sub.desa_list
                    FROM (
                        SELECT j2.id,
                               string_agg(DISTINCT COALESCE(k.nama_kecamatan, k.\"WADMKC\"), ', ') AS kecamatan_list,
                               string_agg(DISTINCT d.nama_desa, ', ') AS desa_list
                        FROM jalan_porosdesa j2
                        LEFT JOIN bataswilayah_kecamatan k 
                            ON ST_Intersects(ST_Transform(j2.geom, 4326), ST_Transform(k.geom, 4326))
                        LEFT JOIN bataswilayah_desa d 
                            ON ST_Intersects(ST_Transform(j2.geom, 4326), ST_Transform(d.geom, 4326))
                        GROUP BY j2.id
                    ) sub
                    WHERE j.id = sub.id;
                ");
            } catch (\Throwable $e) {
                // Log and continue
            }
        }

        // Purge Martin tile cache so new properties are included in vector tiles immediately
        try {
            if (class_exists(\App\Services\MartinTileService::class)) {
                \App\Services\MartinTileService::purgeCache('jalan_kabupaten_luarkota');
                \App\Services\MartinTileService::purgeCache('jalan_porosdesa');
            }
        } catch (\Throwable $e) {
            // Ignore if Martin service not running
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('jalan_kabupaten_luarkota')) {
            Schema::table('jalan_kabupaten_luarkota', function (Blueprint $table) {
                if (Schema::hasColumn('jalan_kabupaten_luarkota', 'nama_kecamatan')) {
                    $table->dropColumn('nama_kecamatan');
                }
                if (Schema::hasColumn('jalan_kabupaten_luarkota', 'nama_desa')) {
                    $table->dropColumn('nama_desa');
                }
            });
        }

        if (Schema::hasTable('jalan_porosdesa')) {
            Schema::table('jalan_porosdesa', function (Blueprint $table) {
                if (Schema::hasColumn('jalan_porosdesa', 'nama_kecamatan')) {
                    $table->dropColumn('nama_kecamatan');
                }
                if (Schema::hasColumn('jalan_porosdesa', 'nama_desa')) {
                    $table->dropColumn('nama_desa');
                }
            });
        }
    }
};

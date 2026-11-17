<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan tiap ALTER/view secara independen agar kegagalan view opsional
     * tidak menggugurkan transaksi rename kolom di PostgreSQL.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('jalan_kabupaten_luarkota')) {
            return;
        }

        $columns = Schema::getColumnListing('jalan_kabupaten_luarkota');
        $lowerLookup = array_flip(array_map('strtolower', $columns));

        foreach ($columns as $col) {
            $target = strtolower($col);
            if ($col === $target) {
                continue;
            }

            // Hindari bentrok jika kolom lowercase target sudah ada
            $hasExactTarget = in_array($target, $columns, true);
            if ($hasExactTarget) {
                continue;
            }

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(sprintf(
                    'ALTER TABLE jalan_kabupaten_luarkota RENAME COLUMN "%s" TO "%s"',
                    str_replace('"', '""', $col),
                    str_replace('"', '""', $target)
                ));
            } else {
                DB::statement(sprintf(
                    'ALTER TABLE jalan_kabupaten_luarkota RENAME COLUMN "%s" TO "%s"',
                    $col,
                    $target
                ));
            }
        }

        // Perbarui view gabungan agar memakai kolom lowercase secara eksplisit
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                $parts = [];
                if (Schema::hasTable('jalan_porosdesa')) {
                    $parts[] = "
                        SELECT
                            id::text AS id,
                            kode_ruas::text AS kode_ruas,
                            nama_ruas::varchar(255) AS nama_ruas,
                            COALESCE(hierarki_jalan, 'poros_desa')::varchar(40) AS hierarki_jalan,
                            id_kecamatan,
                            id_desa,
                            COALESCE(panjang, 0)::numeric(12,2) AS panjang_m,
                            COALESCE(lebar, 0)::numeric(6,2) AS lebar_m,
                            COALESCE(sumber_data, 'sk_pemda')::text AS sumber_data,
                            ST_Force2D(ST_Transform(geom, 4326))::geometry(Geometry, 4326) AS geom
                        FROM jalan_porosdesa
                        WHERE geom IS NOT NULL
                    ";
                }

                $kabCols = Schema::getColumnListing('jalan_kabupaten_luarkota');
                $kabNameExpr = in_array('nama_ruas', $kabCols, true)
                    ? 'COALESCE(nama_ruas, \'Jalan Kabupaten\')::varchar(255)'
                    : '\'Jalan Kabupaten\'::varchar(255)';
                $kabCodeExpr = in_array('no_ruas', $kabCols, true) ? 'no_ruas::text' : 'NULL::text';

                $parts[] = "
                    SELECT
                        id::text AS id,
                        {$kabCodeExpr} AS kode_ruas,
                        {$kabNameExpr} AS nama_ruas,
                        'kabupaten'::varchar(40) AS hierarki_jalan,
                        NULL::integer AS id_kecamatan,
                        NULL::bigint AS id_desa,
                        ROUND(ST_Length(ST_Force2D(ST_Transform(geom, 4326))::geography)::numeric, 2) AS panjang_m,
                        NULL::numeric(6,2) AS lebar_m,
                        'sk_pemda'::text AS sumber_data,
                        ST_Force2D(ST_Transform(geom, 4326))::geometry(Geometry, 4326) AS geom
                    FROM jalan_kabupaten_luarkota
                    WHERE geom IS NOT NULL
                ";

                DB::statement('CREATE OR REPLACE VIEW v_jaringan_jalan_bojonegoro AS ' . implode(' UNION ALL ', $parts));
            } catch (\Throwable $e) {
                // View opsional
            }

            try {
                if (class_exists(\App\Services\MartinTileService::class)) {
                    \App\Services\MartinTileService::purgeCache('jalan_kabupaten_luarkota');
                }
            } catch (\Throwable $e) {
                // Abaikan jika Martin tidak aktif
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('jalan_kabupaten_luarkota')) {
            return;
        }

        $reverseMap = [
            'nama_ruas' => 'Nama_Ruas',
            'no_ruas' => 'No_Ruas',
            'pangkal' => 'Pangkal',
            'ujung' => 'Ujung',
            'panjang' => 'Panjang',
            'klas_ruas' => 'Klas_Ruas',
            'kecamatan_' => 'Kecamatan_',
            'status_ruas' => 'Status_Ruas',
        ];

        $columns = Schema::getColumnListing('jalan_kabupaten_luarkota');
        foreach ($reverseMap as $from => $to) {
            if (in_array($from, $columns, true) && !in_array($to, $columns, true)) {
                DB::statement(sprintf('ALTER TABLE jalan_kabupaten_luarkota RENAME COLUMN "%s" TO "%s"', $from, $to));
            }
        }
    }
};

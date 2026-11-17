<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan tiap DDL/view secara independen agar kegagalan view opsional
     * tidak menggugurkan transaksi perubahan skema di PostgreSQL.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('jalan_porosdesa')) {
            Schema::table('jalan_porosdesa', function (Blueprint $table) {
                if (!Schema::hasColumn('jalan_porosdesa', 'hierarki_jalan')) {
                    $table->string('hierarki_jalan', 40)->default('poros_desa')->index();
                }
                if (!Schema::hasColumn('jalan_porosdesa', 'sumber_proposal_id')) {
                    $table->uuid('sumber_proposal_id')->nullable()->index();
                }
                if (!Schema::hasColumn('jalan_porosdesa', 'sumber_lokasi_id')) {
                    $table->uuid('sumber_lokasi_id')->nullable()->index();
                }
                if (!Schema::hasColumn('jalan_porosdesa', 'validated_by')) {
                    $table->uuid('validated_by')->nullable();
                }
                if (!Schema::hasColumn('jalan_porosdesa', 'validated_at')) {
                    $table->timestamp('validated_at')->nullable();
                }
            });
        }

        // Migrasikan data existing dari jaringan_jalan_ruas (jika ada) langsung ke jalan_porosdesa
        if (Schema::hasTable('jaringan_jalan_ruas') && Schema::hasTable('jalan_porosdesa')) {
            $rows = DB::table('jaringan_jalan_ruas')
                ->whereNull('deleted_at')
                ->where('status_validasi', 'terverifikasi')
                ->get();

            $maxKode = (int) DB::table('jalan_porosdesa')->max('kode_ruas');

            foreach ($rows as $row) {
                $exists = DB::table('jalan_porosdesa')->where('id', $row->id)->exists();
                if ($exists) {
                    continue;
                }

                $numericKode = is_numeric($row->kode_ruas) ? (int) $row->kode_ruas : null;
                if (!$numericKode || DB::table('jalan_porosdesa')->where('kode_ruas', $numericKode)->exists()) {
                    $maxKode++;
                    $numericKode = $maxKode;
                }

                $desaNama = null;
                $kecNama = null;
                if ($row->id_desa && Schema::hasTable('villages')) {
                    $desaNama = DB::table('villages')->where('id', $row->id_desa)->value('name');
                }
                if ($row->id_kecamatan && Schema::hasTable('districts')) {
                    $kecNama = DB::table('districts')->where('id', $row->id_kecamatan)->value('name');
                }

                $payload = [
                    'id' => $row->id,
                    'kode_ruas' => $numericKode,
                    'nama_ruas' => $row->nama_ruas,
                    'desa' => $desaNama,
                    'kecamatan' => $kecNama,
                    'panjang' => $row->panjang_m ?? 0,
                    'lebar' => $row->lebar_m,
                    'perkerasan' => $row->perkerasan,
                    'kondisi' => $row->kondisi,
                    'status_awal' => 'eksisting',
                    'status_eksisting' => 'terverifikasi',
                    'sumber_data' => $row->sumber_data ?? 'promosi_proposal',
                    'id_desa' => $row->id_desa,
                    'id_kecamatan' => $row->id_kecamatan,
                    'hierarki_jalan' => $row->hierarki_jalan ?? 'lingkungan',
                    'sumber_proposal_id' => $row->sumber_proposal_id,
                    'sumber_lokasi_id' => $row->sumber_lokasi_id,
                    'validated_by' => $row->validated_by,
                    'validated_at' => $row->validated_at,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ];

                if (Schema::hasColumn('jalan_porosdesa', 'nama_desa')) {
                    $payload['nama_desa'] = $desaNama;
                }
                if (Schema::hasColumn('jalan_porosdesa', 'nama_kecamatan')) {
                    $payload['nama_kecamatan'] = $kecNama;
                }

                if (DB::connection()->getDriverName() === 'pgsql') {
                    $geomExpr = DB::table('jaringan_jalan_ruas')
                        ->where('id', $row->id)
                        ->value(DB::raw('ST_AsText(ST_Multi(ST_Force2D(ST_Transform(geom, 4326))))'));
                    if ($geomExpr) {
                        $payload['geom'] = DB::raw("ST_GeomFromText(" . DB::getPdo()->quote($geomExpr) . ", 4326)");
                    }
                } else {
                    $payload['geom'] = $row->geom;
                }

                DB::table('jalan_porosdesa')->insert($payload);
            }
        }

        if (Schema::hasTable('bkkd_proposal_lokasi')) {
            DB::table('bkkd_proposal_lokasi')
                ->whereNotNull('id_jaringan_ruas')
                ->whereNull('id_jalan_poros')
                ->update([
                    'id_jalan_poros' => DB::raw('id_jaringan_ruas'),
                ]);
        }

        // Perbarui view gabungan jaringan jalan Bojonegoro agar bermuara langsung dari jalan_porosdesa
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
                if (Schema::hasTable('jalan_kabupaten_luarkota')) {
                    $kabCols = Schema::getColumnListing('jalan_kabupaten_luarkota');
                    $kabNameExpr = in_array('Nama_Ruas', $kabCols, true)
                        ? 'COALESCE("Nama_Ruas", \'Jalan Kabupaten\')::varchar(255)'
                        : (in_array('nama_ruas', $kabCols, true) ? 'COALESCE(nama_ruas, \'Jalan Kabupaten\')::varchar(255)' : '\'Jalan Kabupaten\'::varchar(255)');
                    $kabCodeExpr = in_array('No_Ruas', $kabCols, true) ? '"No_Ruas"::text' : 'NULL::text';

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
                }

                if (!empty($parts)) {
                    DB::statement('CREATE OR REPLACE VIEW v_jaringan_jalan_bojonegoro AS ' . implode(' UNION ALL ', $parts));
                }
            } catch (\Throwable $e) {
                // View opsional; perubahan kolom utama tetap aman karena $withinTransaction = false
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jalan_porosdesa')) {
            Schema::table('jalan_porosdesa', function (Blueprint $table) {
                $cols = [
                    'hierarki_jalan',
                    'sumber_proposal_id',
                    'sumber_lokasi_id',
                    'validated_by',
                    'validated_at',
                ];
                $existing = array_values(array_filter($cols, fn ($col) => Schema::hasColumn('jalan_porosdesa', $col)));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};

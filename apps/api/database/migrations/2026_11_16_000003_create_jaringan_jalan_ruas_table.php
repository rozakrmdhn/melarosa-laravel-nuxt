<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan tiap DDL/view secara independen agar kegagalan view opsional
     * tidak menggugurkan transaksi pembuatan tabel utama di PostgreSQL.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('jaringan_jalan_ruas')) {
            Schema::create('jaringan_jalan_ruas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('kode_ruas', 50)->nullable()->index();
                $table->string('nama_ruas', 255);
                $table->string('hierarki_jalan', 40)->default('lingkungan')->index();
                $table->unsignedInteger('id_kecamatan')->nullable()->index();
                $table->unsignedBigInteger('id_desa')->nullable()->index();
                $table->decimal('panjang_m', 12, 2)->default(0);
                $table->decimal('lebar_m', 6, 2)->nullable();
                $table->string('perkerasan', 80)->nullable();
                $table->string('kondisi', 60)->nullable();
                $table->string('sumber_data', 40)->default('promosi_proposal')->index();
                $table->string('status_validasi', 30)->default('terverifikasi')->index();
                $table->uuid('sumber_proposal_id')->nullable()->index();
                $table->uuid('sumber_lokasi_id')->nullable()->index();
                $table->uuid('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->text('catatan_validasi')->nullable();
                $table->timestamps();
                $table->softDeletes();

                if (DB::connection()->getDriverName() !== 'pgsql') {
                    $table->text('geom')->nullable();
                }
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $hasGeomCol = DB::selectOne("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'jaringan_jalan_ruas' AND column_name = 'geom'
            ");
            if (!$hasGeomCol) {
                DB::statement('ALTER TABLE jaringan_jalan_ruas ADD COLUMN geom geometry(LineString, 4326)');
            }
            DB::statement('CREATE INDEX IF NOT EXISTS jaringan_jalan_ruas_geom_gist ON jaringan_jalan_ruas USING GIST (geom)');
        }

        if (Schema::hasTable('bkkd_proposal_lokasi')) {
            Schema::table('bkkd_proposal_lokasi', function (Blueprint $table) {
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'id_jaringan_ruas')) {
                    $table->uuid('id_jaringan_ruas')->nullable()->index()->after('id_jalan_poros');
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'status_promosi_master')) {
                    $table->string('status_promosi_master', 30)->default('belum_dievaluasi')->index()->after('status_analisa_spasial');
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'validasi_promosi_spasial')) {
                    $table->json('validasi_promosi_spasial')->nullable()->after('ringkasan_analisa');
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'nama_ruas_usulan')) {
                    $table->string('nama_ruas_usulan', 255)->nullable()->after('nama_lokasi');
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'hierarki_jalan_usulan')) {
                    $table->string('hierarki_jalan_usulan', 40)->nullable()->after('nama_ruas_usulan');
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'catatan_promosi_master')) {
                    $table->text('catatan_promosi_master')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'diajukan_promosi_oleh')) {
                    $table->uuid('diajukan_promosi_oleh')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'diajukan_promosi_pada')) {
                    $table->timestamp('diajukan_promosi_pada')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'divalidasi_promosi_oleh')) {
                    $table->uuid('divalidasi_promosi_oleh')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal_lokasi', 'divalidasi_promosi_pada')) {
                    $table->timestamp('divalidasi_promosi_pada')->nullable();
                }
            });
        }

        // View gabungan seluruh jaringan jalan terverifikasi di Kabupaten Bojonegoro (untuk PostGIS & Martin)
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                $parts = [];
                if (Schema::hasTable('jalan_porosdesa')) {
                    $parts[] = "
                        SELECT
                            id::text AS id,
                            kode_ruas::text AS kode_ruas,
                            nama_ruas::varchar(255) AS nama_ruas,
                            'poros_desa'::varchar(40) AS hierarki_jalan,
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
                $parts[] = "
                    SELECT
                        id::text AS id,
                        kode_ruas,
                        nama_ruas::varchar(255) AS nama_ruas,
                        hierarki_jalan::varchar(40) AS hierarki_jalan,
                        id_kecamatan,
                        id_desa,
                        panjang_m,
                        lebar_m,
                        sumber_data::text AS sumber_data,
                        ST_Force2D(geom)::geometry(Geometry, 4326) AS geom
                    FROM jaringan_jalan_ruas
                    WHERE deleted_at IS NULL
                      AND status_validasi = 'terverifikasi'
                      AND geom IS NOT NULL
                ";

                DB::statement('CREATE OR REPLACE VIEW v_jaringan_jalan_bojonegoro AS ' . implode(' UNION ALL ', $parts));
            } catch (\Throwable $e) {
                // View opsional; tabel utama tetap aman karena $withinTransaction = false
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS v_jaringan_jalan_bojonegoro');
        }

        if (Schema::hasTable('bkkd_proposal_lokasi')) {
            Schema::table('bkkd_proposal_lokasi', function (Blueprint $table) {
                $cols = [
                    'id_jaringan_ruas',
                    'status_promosi_master',
                    'validasi_promosi_spasial',
                    'nama_ruas_usulan',
                    'hierarki_jalan_usulan',
                    'catatan_promosi_master',
                    'diajukan_promosi_oleh',
                    'diajukan_promosi_pada',
                    'divalidasi_promosi_oleh',
                    'divalidasi_promosi_pada',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('bkkd_proposal_lokasi', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('jaringan_jalan_ruas');
    }
};

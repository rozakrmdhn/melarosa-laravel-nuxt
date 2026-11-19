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
        if (DB::connection()->getDriverName() === 'pgsql') {
            if (Schema::hasTable('rtrw_jaringan_transportasi_ln') && Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'geom')) {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_rtrw_jaringan_transportasi_ln_geom_gist ON rtrw_jaringan_transportasi_ln USING GIST (geom)');
            }
            if (Schema::hasTable('jalan_kabupaten_luarkota') && Schema::hasColumn('jalan_kabupaten_luarkota', 'geom')) {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_jalan_kabupaten_luarkota_geom_gist ON jalan_kabupaten_luarkota USING GIST (geom)');
            }
        }

        if (!Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            Schema::create('rtrw_jaringan_analisa_spasial', function (Blueprint $table) {
                $table->unsignedBigInteger('rtrw_id')->primary();
                $table->string('orde01', 160)->nullable()->index();
                $table->string('orde02', 160)->nullable()->index();
                $table->string('orde03', 160)->nullable();
                $table->string('orde04', 160)->nullable();
                $table->string('nama_segmen', 255)->nullable();
                $table->unsignedBigInteger('id_kecamatan')->nullable()->index();
                $table->string('nama_kecamatan', 160)->nullable();
                $table->unsignedBigInteger('id_desa')->nullable()->index();
                $table->string('nama_desa', 160)->nullable();
                $table->double('panjang_m')->default(0);
                $table->double('poros_overlap_m')->default(0);
                $table->float('poros_overlap_pct')->default(0);
                $table->string('matched_poros_id', 64)->nullable();
                $table->unsignedInteger('matched_poros_kode')->nullable();
                $table->string('matched_poros_nama', 255)->nullable();
                $table->string('matched_poros_hierarki', 64)->nullable();
                $table->double('kab_overlap_m')->default(0);
                $table->float('kab_overlap_pct')->default(0);
                $table->unsignedBigInteger('matched_kab_id')->nullable();
                $table->string('matched_kab_no_ruas', 64)->nullable();
                $table->string('matched_kab_nama', 255)->nullable();
                $table->string('matched_kab_klas', 64)->nullable();
                $table->double('combined_overlap_m')->default(0);
                $table->float('combined_overlap_pct')->default(0);
                $table->double('uncovered_m')->default(0);
                $table->string('status_analisa', 40)->index();
                $table->boolean('already_adopted')->default(false)->index();
                $table->json('bbox_json')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rtrw_jaringan_analisa_spasial');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_rtrw_jaringan_transportasi_ln_geom_gist');
            DB::statement('DROP INDEX IF EXISTS idx_jalan_kabupaten_luarkota_geom_gist');
        }
    }
};

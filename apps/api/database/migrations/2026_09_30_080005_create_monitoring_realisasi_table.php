<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monitoring_realisasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor_ba');
            $table->uuid('id_plotting');
            $table->foreign('id_plotting')->references('id')->on('plotting_anggaran')->cascadeOnUpdate()->restrictOnDelete();
            $table->uuid('id_segmen')->nullable();
            $table->foreign('id_segmen')->references('id')->on('infrastruktur_segmen')->cascadeOnUpdate()->nullOnDelete();
            $table->integer('id_kecamatan');
            $table->foreign('id_kecamatan')->references('id')->on('bataswilayah_kecamatan')->cascadeOnUpdate()->restrictOnDelete();
            $table->bigInteger('id_desa');
            $table->foreign('id_desa')->references('id')->on('bataswilayah_desa')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('tahun_anggaran');
            $table->string('sumber_dana');
            $table->decimal('rencana_panjang', 10, 2)->default(0);
            $table->decimal('realisasi_panjang', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('keterangan')->nullable();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['nomor_ba', 'tahun_anggaran'], 'uniq_monitoring_ba_tahun');
            $table->index(['tahun_anggaran', 'id_kecamatan', 'id_desa'], 'idx_monitoring_tahun_wilayah');
            $table->index('id_plotting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_realisasi');
    }
};

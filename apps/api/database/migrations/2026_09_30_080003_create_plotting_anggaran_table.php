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
        Schema::create('plotting_anggaran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('tahun_anggaran');
            $table->integer('id_kecamatan');
            $table->foreign('id_kecamatan')->references('id')->on('bataswilayah_kecamatan')->cascadeOnUpdate()->restrictOnDelete();
            $table->bigInteger('id_desa');
            $table->foreign('id_desa')->references('id')->on('bataswilayah_desa')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('jenis_bantuan');
            $table->string('nama_kegiatan');
            $table->text('lokasi_kegiatan')->nullable();
            $table->string('sumber_dana');
            $table->decimal('target_pagu_anggaran', 15, 2)->default(0);
            $table->decimal('target_panjang_m', 10, 2)->default(0);
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['tahun_anggaran', 'id_kecamatan', 'id_desa'], 'idx_plotting_tahun_wilayah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plotting_anggaran');
    }
};

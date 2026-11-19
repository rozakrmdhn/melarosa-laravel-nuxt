<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jadwal_tahun_anggaran')) {
            Schema::create('jadwal_tahun_anggaran', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('tahun_anggaran')->unique();
                $table->string('nama_periode', 255);
                $table->string('status_jadwal', 20)->default('ditutup'); // terjadwal | dibuka | ditutup
                $table->timestampTz('mulai_at')->nullable();
                $table->timestampTz('selesai_at')->nullable();
                $table->boolean('allow_plotting')->default(true);
                $table->boolean('allow_digitasi')->default(true);
                $table->boolean('allow_non_pagu')->default(true);
                $table->text('catatan')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->foreign('updated_by')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestampsTz();

                $table->index(['tahun_anggaran', 'status_jadwal'], 'idx_jadwal_ta_status');
            });
        }

        if (!Schema::hasTable('jadwal_tahun_anggaran_wilayah')) {
            Schema::create('jadwal_tahun_anggaran_wilayah', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('jadwal_id');
                $table->foreign('jadwal_id')->references('id')->on('jadwal_tahun_anggaran')->cascadeOnDelete();
                $table->integer('id_kecamatan')->nullable();
                $table->foreign('id_kecamatan')->references('id')->on('bataswilayah_kecamatan')->cascadeOnUpdate()->cascadeOnDelete();
                $table->bigInteger('id_desa')->nullable();
                $table->foreign('id_desa')->references('id')->on('bataswilayah_desa')->cascadeOnUpdate()->cascadeOnDelete();
                $table->string('mode_akses', 20); // buka_paksa | tutup_paksa
                $table->timestampTz('mulai_at')->nullable();
                $table->timestampTz('selesai_at')->nullable();
                $table->boolean('allow_plotting')->nullable();
                $table->boolean('allow_digitasi')->nullable();
                $table->boolean('allow_non_pagu')->nullable();
                $table->text('alasan')->nullable();
                $table->uuid('created_by')->nullable();
                $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->uuid('updated_by')->nullable();
                $table->foreign('updated_by')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestampsTz();

                $table->index(['jadwal_id', 'id_kecamatan', 'id_desa'], 'idx_jadwal_wilayah_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_tahun_anggaran_wilayah');
        Schema::dropIfExists('jadwal_tahun_anggaran');
    }
};

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
        Schema::create('bkkd_verifikasi_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_opd');
            $table->foreign('id_opd')->references('id')->on('ref_opd')->restrictOnDelete();
            $table->string('nomor_surat_hasil', 100);
            $table->date('tanggal_surat_hasil');
            $table->date('tanggal_diterima')->nullable();
            $table->integer('tahun_anggaran')->index();
            $table->string('path_dokumen');
            $table->string('nama_dokumen_asli');
            $table->text('catatan_umum')->nullable();
            $table->uuid('input_by');
            $table->foreign('input_by')->references('uuid')->on('users')->restrictOnDelete();
            $table->uuid('updated_by')->nullable();
            $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['id_opd', 'tahun_anggaran']);
            $table->index('nomor_surat_hasil');
        });

        Schema::table('bkkd_verifikasi', function (Blueprint $table) {
            $table->uuid('id_dokumen')->nullable()->after('id_proposal');
            $table->foreign('id_dokumen')->references('id')->on('bkkd_verifikasi_dokumen')->nullOnDelete();
            $table->index('id_dokumen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bkkd_verifikasi', function (Blueprint $table) {
            $table->dropForeign(['id_dokumen']);
            $table->dropColumn('id_dokumen');
        });

        Schema::dropIfExists('bkkd_verifikasi_dokumen');
    }
};

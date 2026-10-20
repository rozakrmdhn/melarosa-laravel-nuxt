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
        Schema::create('bkkd_verifikasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_proposal');
            $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
            $table->uuid('id_opd');
            $table->foreign('id_opd')->references('id')->on('ref_opd')->restrictOnDelete();
            $table->string('nomor_surat_hasil');
            $table->date('tanggal_surat_hasil');
            $table->date('tanggal_diterima');
            $table->string('hasil'); // layak, layak_bersyarat, tidak_layak, dikembalikan
            $table->decimal('nilai_rekomendasi', 15, 2)->nullable();
            $table->decimal('volume_rekomendasi', 12, 2)->nullable();
            $table->text('catatan_opd')->nullable();
            $table->string('path_dokumen');
            $table->string('nama_dokumen_asli');
            $table->boolean('is_aktif')->default(true);
            $table->integer('versi')->default(1);
            $table->uuid('input_by');
            $table->foreign('input_by')->references('uuid')->on('users')->restrictOnDelete();
            $table->uuid('updated_by')->nullable();
            $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('id_proposal');
            $table->index(['id_proposal', 'is_aktif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkkd_verifikasi');
    }
};

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
        Schema::create('bkkd_proposal_lampiran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_proposal');
            $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
            $table->string('jenis_dokumen'); // surat_permohonan, rab, gambar_teknis, foto_lokasi, lainnya
            $table->string('nama_asli');
            $table->string('path');
            $table->string('mime_type');
            $table->bigInteger('ukuran_byte');
            $table->uuid('uploaded_by');
            $table->foreign('uploaded_by')->references('uuid')->on('users')->restrictOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->index('id_proposal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkkd_proposal_lampiran');
    }
};

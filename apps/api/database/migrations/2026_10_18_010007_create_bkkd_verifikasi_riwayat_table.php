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
        Schema::create('bkkd_verifikasi_riwayat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_verifikasi');
            $table->foreign('id_verifikasi')->references('id')->on('bkkd_verifikasi')->cascadeOnDelete();
            $table->integer('versi');
            $table->string('aksi'); // created, updated, superseded
            $table->jsonb('field_berubah')->nullable();
            $table->jsonb('data_sebelum')->nullable();
            $table->jsonb('data_sesudah')->nullable();
            $table->text('alasan_perubahan')->nullable();
            $table->uuid('changed_by');
            $table->foreign('changed_by')->references('uuid')->on('users')->restrictOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->index(['id_verifikasi', 'versi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkkd_verifikasi_riwayat');
    }
};

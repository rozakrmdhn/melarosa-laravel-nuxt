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
        Schema::create('bkkd_proposal_riwayat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_proposal');
            $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
            $table->integer('versi');
            $table->string('aksi'); // created, updated, status_changed, deleted, restored, lampiran_added, lampiran_removed, ditetapkan_tapd
            $table->jsonb('field_berubah')->nullable();
            $table->jsonb('data_sebelum')->nullable();
            $table->jsonb('data_sesudah')->nullable();
            $table->text('alasan_perubahan')->nullable();
            $table->uuid('changed_by');
            $table->foreign('changed_by')->references('uuid')->on('users')->restrictOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->index(['id_proposal', 'versi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkkd_proposal_riwayat');
    }
};

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
        Schema::create('monitoring_realisasi_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_monitoring');
            $table->foreign('id_monitoring')->references('id')->on('monitoring_realisasi')->cascadeOnDelete();
            $table->text('catatan_revisi')->nullable();
            $table->string('status_sebelum')->nullable();
            $table->jsonb('data_snapshot')->nullable();
            $table->uuid('revised_by');
            $table->foreign('revised_by')->references('uuid')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestampTz('reverted_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('id_monitoring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_realisasi_revisions');
    }
};

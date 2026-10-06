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
        Schema::create('monitoring_realisasi_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_monitoring');
            $table->foreign('id_monitoring')->references('id')->on('monitoring_realisasi')->cascadeOnDelete();
            $table->uuid('id_segmen');
            $table->foreign('id_segmen')->references('id')->on('infrastruktur_segmen')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['id_monitoring', 'id_segmen'], 'uniq_item_monitoring_segmen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_realisasi_items');
    }
};

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
        Schema::create('ref_bkkd_bidang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama')->unique();
            $table->uuid('id_opd_default')->nullable();
            $table->foreign('id_opd_default')->references('id')->on('ref_opd')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_bkkd_bidang');
    }
};

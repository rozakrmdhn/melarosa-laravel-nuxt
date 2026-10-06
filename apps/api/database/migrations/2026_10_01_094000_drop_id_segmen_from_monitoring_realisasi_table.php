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
        Schema::table('monitoring_realisasi', function (Blueprint $table) {
            $table->dropForeign(['id_segmen']);
            $table->dropColumn('id_segmen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitoring_realisasi', function (Blueprint $table) {
            $table->uuid('id_segmen')->nullable()->after('id_plotting');
            $table->foreign('id_segmen')
                ->references('id')
                ->on('infrastruktur_segmen')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }
};

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
        Schema::table('bkkd_proposal', function (Blueprint $table) {
            $table->string('tahap_anggaran', 20)->default('induk')->after('tahun_anggaran');
            $table->uuid('id_proposal_induk')->nullable()->after('id_bidang');
            $table->foreign('id_proposal_induk')->references('id')->on('bkkd_proposal')->nullOnDelete();
            $table->text('alasan_pergantian_papbd')->nullable()->after('catatan');

            $table->index(['tahun_anggaran', 'tahap_anggaran'], 'idx_bkkd_prop_tahap');
            $table->index('id_proposal_induk', 'idx_bkkd_prop_induk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bkkd_proposal', function (Blueprint $table) {
            $table->dropForeign(['id_proposal_induk']);
            $table->dropIndex('idx_bkkd_prop_tahap');
            $table->dropIndex('idx_bkkd_prop_induk');
            $table->dropColumn(['tahap_anggaran', 'id_proposal_induk', 'alasan_pergantian_papbd']);
        });
    }
};

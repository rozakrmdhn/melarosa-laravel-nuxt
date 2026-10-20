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
            $table->index('id_bidang', 'idx_bkkd_prop_bidang');
            $table->index(['id_desa', 'tahun_anggaran'], 'idx_bkkd_prop_desa_tahun');
            $table->index(['status', 'tahun_anggaran'], 'idx_bkkd_prop_status_tahun');
            $table->index('deleted_at', 'idx_bkkd_prop_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bkkd_proposal', function (Blueprint $table) {
            $table->dropIndex('idx_bkkd_prop_bidang');
            $table->dropIndex('idx_bkkd_prop_desa_tahun');
            $table->dropIndex('idx_bkkd_prop_status_tahun');
            $table->dropIndex('idx_bkkd_prop_deleted_at');
        });
    }
};

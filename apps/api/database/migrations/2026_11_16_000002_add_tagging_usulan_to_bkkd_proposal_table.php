<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan atribut tagging_usulan (PRKPD / RKPD) pada bkkd_proposal
     * untuk penyandingan lintas perencanaan RKPD Tahun+N <-> PRKPD Tahun+N.
     */
    public function up(): void
    {
        if (Schema::hasTable('bkkd_proposal') && !Schema::hasColumn('bkkd_proposal', 'tagging_usulan')) {
            Schema::table('bkkd_proposal', function (Blueprint $table) {
                $table->string('tagging_usulan', 20)
                    ->default('PRKPD')
                    ->after('tahun_anggaran')
                    ->index('idx_bkkd_prop_tagging');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bkkd_proposal') && Schema::hasColumn('bkkd_proposal', 'tagging_usulan')) {
            Schema::table('bkkd_proposal', function (Blueprint $table) {
                $table->dropIndex('idx_bkkd_prop_tagging');
                $table->dropColumn('tagging_usulan');
            });
        }
    }
};

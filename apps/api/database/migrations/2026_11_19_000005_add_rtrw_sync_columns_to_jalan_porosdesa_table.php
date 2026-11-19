<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('jalan_porosdesa')) {
            return;
        }

        Schema::table('jalan_porosdesa', function (Blueprint $table) {
            if (!Schema::hasColumn('jalan_porosdesa', 'tahun_survey')) {
                $table->smallInteger('tahun_survey')->nullable()->index();
            }
            if (!Schema::hasColumn('jalan_porosdesa', 'rtrw_source_id')) {
                $table->unsignedBigInteger('rtrw_source_id')->nullable()->index();
            }
        });

        // Tandai data survei awal 2021 yang belum memiliki tahun_survey dan bukan hasil promosi proposal/RTRW
        DB::table('jalan_porosdesa')
            ->whereNull('tahun_survey')
            ->whereNull('sumber_proposal_id')
            ->whereNull('rtrw_source_id')
            ->where(function ($q) {
                $q->whereNull('sumber_data')
                    ->orWhereNotIn('sumber_data', ['promosi_proposal', 'rtrw_orde']);
            })
            ->update(['tahun_survey' => 2021]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('jalan_porosdesa')) {
            return;
        }

        Schema::table('jalan_porosdesa', function (Blueprint $table) {
            if (Schema::hasColumn('jalan_porosdesa', 'rtrw_source_id')) {
                $table->dropColumn('rtrw_source_id');
            }
            if (Schema::hasColumn('jalan_porosdesa', 'tahun_survey')) {
                $table->dropColumn('tahun_survey');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plotting_anggaran')) {
            Schema::table('plotting_anggaran', function (Blueprint $table) {
                if (!Schema::hasColumn('plotting_anggaran', 'nomor_sk')) {
                    $table->string('nomor_sk', 120)->nullable()->after('sumber_dana');
                }
                if (!Schema::hasColumn('plotting_anggaran', 'tanggal_sk')) {
                    $table->date('tanggal_sk')->nullable()->after('nomor_sk');
                }
                if (!Schema::hasColumn('plotting_anggaran', 'status_penetapan')) {
                    $table->string('status_penetapan', 30)->default('ditetapkan')->after('tanggal_sk');
                }
                if (!Schema::hasColumn('plotting_anggaran', 'bkkd_proposal_id')) {
                    $table->uuid('bkkd_proposal_id')->nullable()->after('status_penetapan');
                    if (Schema::hasTable('bkkd_proposal')) {
                        $table->foreign('bkkd_proposal_id')
                            ->references('id')
                            ->on('bkkd_proposal')
                            ->cascadeOnUpdate()
                            ->nullOnDelete();
                    }
                }
            });
        }

        if (Schema::hasTable('infrastruktur_segmen') && !Schema::hasColumn('infrastruktur_segmen', 'is_non_pagu')) {
            Schema::table('infrastruktur_segmen', function (Blueprint $table) {
                $table->boolean('is_non_pagu')->default(false)->after('plotting_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('infrastruktur_segmen') && Schema::hasColumn('infrastruktur_segmen', 'is_non_pagu')) {
            Schema::table('infrastruktur_segmen', function (Blueprint $table) {
                $table->dropColumn('is_non_pagu');
            });
        }

        if (Schema::hasTable('plotting_anggaran')) {
            Schema::table('plotting_anggaran', function (Blueprint $table) {
                if (Schema::hasColumn('plotting_anggaran', 'bkkd_proposal_id')) {
                    try {
                        $table->dropForeign(['bkkd_proposal_id']);
                    } catch (\Throwable $e) {
                        // Ignore if FK does not exist
                    }
                    $table->dropColumn('bkkd_proposal_id');
                }
                foreach (['status_penetapan', 'tanggal_sk', 'nomor_sk'] as $col) {
                    if (Schema::hasColumn('plotting_anggaran', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

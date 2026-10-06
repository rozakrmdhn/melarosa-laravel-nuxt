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
        Schema::table('infrastruktur_segmen', function (Blueprint $table) {
            $table->string('jenis_perkerasan', 100)->nullable()->after('lebar');
            $table->string('status_jalan', 100)->nullable()->after('jenis_perkerasan');

            $table->index('jenis_perkerasan');
            $table->index('status_jalan');
        });

        // Migrasikan data atribut jsonb eksisting ke kolom dedicated
        \Illuminate\Support\Facades\DB::statement("
            UPDATE infrastruktur_segmen
            SET jenis_perkerasan = atribut->>'jenis_perkerasan',
                status_jalan = atribut->>'status_jalan'
            WHERE atribut IS NOT NULL
        ");

        // Sinkronisasi kolom verifikator jika masih kosong dan tersimpan di jsonb atribut
        \Illuminate\Support\Facades\DB::statement("
            UPDATE infrastruktur_segmen
            SET verifikator = atribut->>'verifikator'
            WHERE verifikator IS NULL AND atribut->>'verifikator' IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('infrastruktur_segmen', function (Blueprint $table) {
            $table->dropIndex(['jenis_perkerasan']);
            $table->dropIndex(['status_jalan']);
            $table->dropColumn(['jenis_perkerasan', 'status_jalan']);
        });
    }
};

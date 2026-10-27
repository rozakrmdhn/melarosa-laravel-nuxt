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
        Schema::table('bkkd_verifikasi_dokumen', function (Blueprint $table) {
            $table->string('tipe_sumber', 20)->default('file')->after('tahun_anggaran'); // 'file' or 'link'
            $table->text('url_link')->nullable()->after('tipe_sumber');
            $table->string('path_dokumen')->nullable()->change();
            $table->string('nama_dokumen_asli')->nullable()->change();
        });

        Schema::table('bkkd_verifikasi', function (Blueprint $table) {
            $table->string('tipe_sumber', 20)->default('file')->after('catatan_opd'); // 'file' or 'link'
            $table->text('url_link')->nullable()->after('tipe_sumber');
            $table->string('path_dokumen')->nullable()->change();
            $table->string('nama_dokumen_asli')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bkkd_verifikasi', function (Blueprint $table) {
            $table->dropColumn(['tipe_sumber', 'url_link']);
            $table->string('path_dokumen')->nullable(false)->change();
            $table->string('nama_dokumen_asli')->nullable(false)->change();
        });

        Schema::table('bkkd_verifikasi_dokumen', function (Blueprint $table) {
            $table->dropColumn(['tipe_sumber', 'url_link']);
            $table->string('path_dokumen')->nullable(false)->change();
            $table->string('nama_dokumen_asli')->nullable(false)->change();
        });
    }
};

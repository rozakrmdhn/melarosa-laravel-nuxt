<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bkkd_proposal', function (Blueprint $table) {
            $table->decimal('panjang', 12, 2)->nullable()->after('lokasi_kegiatan');
            $table->decimal('lebar', 12, 2)->nullable()->after('panjang');
            $table->decimal('tinggi', 12, 2)->nullable()->after('lebar');
        });

        Schema::table('bkkd_verifikasi', function (Blueprint $table) {
            $table->decimal('panjang_rekomendasi', 12, 2)->nullable()->after('volume_rekomendasi');
            $table->decimal('lebar_rekomendasi', 12, 2)->nullable()->after('panjang_rekomendasi');
            $table->decimal('tinggi_rekomendasi', 12, 2)->nullable()->after('lebar_rekomendasi');
        });
    }

    public function down(): void
    {
        Schema::table('bkkd_proposal', function (Blueprint $table) {
            $table->dropColumn(['panjang', 'lebar', 'tinggi']);
        });

        Schema::table('bkkd_verifikasi', function (Blueprint $table) {
            $table->dropColumn(['panjang_rekomendasi', 'lebar_rekomendasi', 'tinggi_rekomendasi']);
        });
    }
};

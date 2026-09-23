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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('id_kecamatan')->nullable()->after('avatar');
            $table->bigInteger('id_desa')->nullable()->after('id_kecamatan');
            $table->boolean('status')->default(true)->after('id_desa');

            $table->foreign('id_kecamatan')
                ->references('id')
                ->on('bataswilayah_kecamatan')
                ->nullOnDelete();

            $table->foreign('id_desa')
                ->references('id')
                ->on('bataswilayah_desa')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_kecamatan']);
            $table->dropForeign(['id_desa']);
            $table->dropColumn(['id_kecamatan', 'id_desa', 'status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->string('geoportal_id', 150)->nullable()->unique()->after('id')
                ->comment('ID Record bawaan dari Geoportal Bojonegoro CSW');
            $table->string('category', 150)->nullable()->index()->after('name')
                ->comment('Kategori / Tema / Urusan peta Geoportal');
            $table->string('opd_name', 200)->nullable()->index()->after('category')
                ->comment('Nama OPD / Wali Data dari Geoportal');
            $table->text('legend_url')->nullable()->after('url')
                ->comment('URL WMS GetLegendGraphic Geoportal');
            $table->jsonb('csw_metadata')->nullable()->after('description')
                ->comment('Penyimpanan payload XML/JSON mentah dari CSW');
            $table->timestamp('last_synced_at')->nullable()->after('is_synced')
                ->comment('Waktu terakhir sinkronisasi dari CSW');
        });
    }

    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropColumn([
                'geoportal_id',
                'category',
                'opd_name',
                'legend_url',
                'csw_metadata',
                'last_synced_at',
            ]);
        });
    }
};

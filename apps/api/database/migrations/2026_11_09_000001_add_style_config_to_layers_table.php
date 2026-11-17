<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('layers', 'style_config')) {
            Schema::table('layers', function (Blueprint $table) {
                $table->jsonb('style_config')->nullable()->after('csw_metadata')
                    ->comment('Konfigurasi simbologi dan styling dinamis layer vektor / MVT');
            });
        }

        // Seeding konfigurasi style awal untuk layer MVT bawaan
        $defaultStyles = [
            'infrastruktur_segmen' => [
                'geom_type' => 'line',
                'color' => '#10b981',
                'width' => 3,
                'dash' => 'solid',
                'casing' => false,
                'casing_color' => 'rgba(16, 185, 129, 0.25)',
                'casing_width' => 6,
                'fill_color' => 'rgba(16, 185, 129, 0.1)',
                'label' => [
                    'enabled' => true,
                    'field' => 'namobj',
                    'min_zoom' => 13.5,
                    'color' => '#064e3b',
                    'halo_color' => '#ffffff',
                    'halo_width' => 3,
                ],
                'rules' => [
                    ['id' => 'r1', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak berat', 'color' => '#ef4444', 'width' => 3.5],
                    ['id' => 'r2', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak ringan', 'color' => '#f97316', 'width' => 3],
                    ['id' => 'r3', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'sedang', 'color' => '#f59e0b', 'width' => 3],
                    ['id' => 'r4', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak', 'color' => '#f97316', 'width' => 3],
                    ['id' => 'r5', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'baik', 'color' => '#10b981', 'width' => 3],
                ],
                'zoom_scale' => true,
            ],
            'jalan_porosdesa' => [
                'geom_type' => 'line',
                'color' => '#2563eb',
                'width' => 2.2,
                'dash' => 'solid',
                'casing' => false,
                'casing_color' => 'rgba(37, 99, 235, 0.25)',
                'casing_width' => 5,
                'fill_color' => 'rgba(37, 99, 235, 0.1)',
                'label' => [
                    'enabled' => true,
                    'field' => 'nama_ruas',
                    'min_zoom' => 13.5,
                    'color' => '#1e3a8a',
                    'halo_color' => '#ffffff',
                    'halo_width' => 3,
                ],
                'rules' => [
                    ['id' => 'r1', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak berat', 'color' => '#ef4444', 'width' => 2.8],
                    ['id' => 'r2', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak ringan', 'color' => '#f97316', 'width' => 2.4],
                    ['id' => 'r3', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'sedang', 'color' => '#f59e0b', 'width' => 2.2],
                    ['id' => 'r4', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak', 'color' => '#f97316', 'width' => 2.4],
                    ['id' => 'r5', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'baik', 'color' => '#10b981', 'width' => 2.2],
                ],
                'zoom_scale' => true,
            ],
            'bataswilayah_kabupaten' => [
                'geom_type' => 'polygon',
                'color' => '#475569',
                'width' => 2,
                'dash' => 'dash-dot',
                'fill_color' => 'rgba(71, 85, 105, 0.02)',
                'casing' => true,
                'casing_color' => 'rgba(148, 163, 184, 0.25)',
                'casing_width' => 5.5,
                'label' => [
                    'enabled' => false,
                    'field' => 'wadmkc',
                    'min_zoom' => 11,
                    'color' => '#334155',
                    'halo_color' => '#ffffff',
                    'halo_width' => 2,
                ],
                'rules' => [],
                'zoom_scale' => true,
            ],
            'bataswilayah_desa' => [
                'geom_type' => 'polygon',
                'color' => '#8b5cf6',
                'width' => 1.5,
                'dash' => 'dash',
                'fill_color' => 'rgba(139, 92, 246, 0.08)',
                'casing' => false,
                'label' => [
                    'enabled' => true,
                    'field' => 'namobj',
                    'min_zoom' => 13,
                    'color' => '#5b21b6',
                    'halo_color' => '#ffffff',
                    'halo_width' => 2,
                ],
                'rules' => [],
                'zoom_scale' => true,
            ],
            'jalan_kabupaten_luarkota' => [
                'geom_type' => 'line',
                'color' => '#dc2626',
                'width' => 2.5,
                'dash' => 'solid',
                'casing' => false,
                'label' => [
                    'enabled' => true,
                    'field' => 'nama_ruas',
                    'min_zoom' => 13,
                    'color' => '#991b1b',
                    'halo_color' => '#ffffff',
                    'halo_width' => 3,
                ],
                'rules' => [],
                'zoom_scale' => true,
            ],
        ];

        foreach ($defaultStyles as $layerName => $style) {
            DB::table('layers')
                ->where('layer_name', $layerName)
                ->whereNull('style_config')
                ->update(['style_config' => json_encode($style)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('layers', 'style_config')) {
            Schema::table('layers', function (Blueprint $table) {
                $table->dropColumn('style_config');
            });
        }
    }
};


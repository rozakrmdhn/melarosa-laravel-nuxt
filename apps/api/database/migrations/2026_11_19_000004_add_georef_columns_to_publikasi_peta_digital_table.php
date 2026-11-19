<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publikasi_peta_digital')) {
            return;
        }

        Schema::table('publikasi_peta_digital', function (Blueprint $table) {
            if (!Schema::hasColumn('publikasi_peta_digital', 'is_georeferenced')) {
                $table->boolean('is_georeferenced')->default(false)->after('download_count')->index();
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_method')) {
                $table->string('georef_method', 20)->nullable()->after('is_georeferenced');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_coordinates')) {
                $table->json('georef_coordinates')->nullable()->after('georef_method');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_bbox')) {
                $table->json('georef_bbox')->nullable()->after('georef_coordinates');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_gcps')) {
                $table->json('georef_gcps')->nullable()->after('georef_bbox');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_rms_meters')) {
                $table->double('georef_rms_meters')->nullable()->after('georef_gcps');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_opacity')) {
                $table->float('georef_opacity')->default(0.85)->after('georef_rms_meters');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_image_disk')) {
                $table->string('georef_image_disk', 40)->nullable()->after('georef_opacity');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_image_path')) {
                $table->string('georef_image_path', 500)->nullable()->after('georef_image_disk');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_image_mime')) {
                $table->string('georef_image_mime', 80)->nullable()->after('georef_image_path');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_image_width')) {
                $table->unsignedInteger('georef_image_width')->nullable()->after('georef_image_mime');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georef_image_height')) {
                $table->unsignedInteger('georef_image_height')->nullable()->after('georef_image_width');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georeferenced_at')) {
                $table->timestamp('georeferenced_at')->nullable()->after('georef_image_height');
            }
            if (!Schema::hasColumn('publikasi_peta_digital', 'georeferenced_by')) {
                $table->uuid('georeferenced_by')->nullable()->after('georeferenced_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('publikasi_peta_digital')) {
            return;
        }

        $columns = [
            'is_georeferenced',
            'georef_method',
            'georef_coordinates',
            'georef_bbox',
            'georef_gcps',
            'georef_rms_meters',
            'georef_opacity',
            'georef_image_disk',
            'georef_image_path',
            'georef_image_mime',
            'georef_image_width',
            'georef_image_height',
            'georeferenced_at',
            'georeferenced_by',
        ];

        $existing = array_values(array_filter($columns, fn ($col) => Schema::hasColumn('publikasi_peta_digital', $col)));
        if (!empty($existing)) {
            Schema::table('publikasi_peta_digital', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};

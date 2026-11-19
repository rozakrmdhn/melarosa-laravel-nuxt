<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basemaps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 60)->unique();
            $table->string('nama', 120);
            $table->string('label_singkat', 40);
            $table->string('tagline', 200)->nullable();
            $table->string('provider', 120)->nullable();
            $table->string('tipe', 20)->default('raster'); // raster | vector
            $table->text('url_template');
            $table->text('fallback_url_template')->nullable();
            $table->jsonb('tile_urls')->nullable();
            $table->string('attribution', 255)->nullable();
            $table->string('thumbnail_url', 255)->nullable();
            $table->string('preview_style', 30)->default('esri');
            $table->smallInteger('min_zoom')->default(0);
            $table->smallInteger('max_zoom')->default(19);
            $table->smallInteger('tile_size')->default(256);
            $table->string('cross_origin', 30)->nullable()->default('anonymous');
            $table->boolean('supports_openlayers')->default(true);
            $table->boolean('supports_maplibre')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->uuid('updated_by')->nullable();
            $table->foreign('updated_by')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(['is_active', 'sort_order'], 'idx_basemaps_active_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basemaps');
    }
};

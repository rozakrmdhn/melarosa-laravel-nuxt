<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastruktur_segmen_fotos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('segmen_id')->index();
            $table->foreign('segmen_id')
                ->references('id')
                ->on('infrastruktur_segmen')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->smallInteger('urutan')->default(1);
            $table->string('tahap_label', 60)->default('Kondisi Lapangan');
            $table->string('keterangan', 255)->nullable();
            $table->string('disk', 30)->default('local');
            $table->string('path', 255);
            $table->string('mime_type', 60)->default('image/webp');
            $table->integer('ukuran_byte');
            $table->integer('lebar_px')->nullable();
            $table->integer('tinggi_px')->nullable();
            $table->string('sha256', 64)->index();

            $table->uuid('uploaded_by')->nullable();
            $table->foreign('uploaded_by')
                ->references('uuid')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestampsTz();

            $table->index(['segmen_id', 'urutan'], 'idx_segmen_fotos_urutan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastruktur_segmen_fotos');
    }
};

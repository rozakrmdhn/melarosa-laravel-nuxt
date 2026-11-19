<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publikasi_peta_digital')) {
            return;
        }

        Schema::create('publikasi_peta_digital', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('judul', 255);
            $table->string('slug', 255)->unique();
            $table->string('nomor_dokumen', 100)->nullable();
            $table->string('kategori', 80)->index();
            $table->string('tingkat_wilayah', 20)->index(); // kabupaten | kecamatan | desa
            $table->unsignedBigInteger('id_kecamatan')->nullable()->index();
            $table->string('nama_kecamatan', 120)->nullable();
            $table->unsignedBigInteger('id_desa')->nullable()->index();
            $table->string('nama_desa', 120)->nullable();
            $table->smallInteger('tahun_terbit')->index();
            $table->string('edisi', 60)->nullable();
            $table->string('skala_peta', 40)->nullable();
            $table->text('ringkasan')->nullable();

            // Sumber: file_pdf (disimpan di storage privat) atau external_url (link tervalidasi)
            $table->string('sumber_tipe', 20)->default('file_pdf');
            $table->text('external_url')->nullable();
            $table->string('external_domain', 150)->nullable();
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();

            $table->unsignedBigInteger('download_count')->default(0);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('published_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'tingkat_wilayah', 'id_kecamatan', 'id_desa'], 'idx_pub_peta_wilayah');
            $table->index(['tahun_terbit', 'kategori'], 'idx_pub_peta_tahun_kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publikasi_peta_digital');
    }
};

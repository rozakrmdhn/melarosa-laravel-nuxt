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
        Schema::create('infrastruktur_segmen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipe_kode');
            $table->foreign('tipe_kode')->references('kode')->on('infrastruktur_tipe')->cascadeOnUpdate()->restrictOnDelete();
            
            // Kolom self-ref parent (foreign key ditambahkan terpisah di bawah)
            $table->uuid('parent_id')->nullable();

            // Kolom Geometri PostGIS (LineString SRID 4326)
            $table->geometry('geom', subtype: 'LineString', srid: 4326)->nullable();

            $table->double('panjang')->nullable();
            $table->double('lebar')->nullable();
            $table->string('kondisi')->default('Baik');
            $table->string('status_kondisi')->default('Eksisting');
            $table->integer('tahun_pembangunan')->nullable();
            $table->string('sumber_dana')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('foto_url')->nullable();
            $table->jsonb('atribut')->nullable();

            // Cache nama wilayah
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();

            // Relasi wilayah master
            $table->bigInteger('id_desa');
            $table->foreign('id_desa')->references('id')->on('bataswilayah_desa')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('id_kecamatan');
            $table->foreign('id_kecamatan')->references('id')->on('bataswilayah_kecamatan')->cascadeOnUpdate()->restrictOnDelete();

            $table->string('namobj')->nullable();
            $table->uuid('plotting_id')->nullable();
            $table->foreign('plotting_id')->references('id')->on('plotting_anggaran')->cascadeOnUpdate()->nullOnDelete();

            $table->string('verifikator')->nullable();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();

            $table->boolean('status_parent')->default(false);
            $table->string('sumber_data')->nullable();

            // Status Verifikasi 3 Tingkat
            $table->string('status_verifikasi')->default('draft');
            $table->string('catatan_verifikasi')->nullable();
            $table->uuid('id_entry')->nullable();
            $table->string('status_aset')->nullable();

            // Audit Creator & Verifikasi Desa
            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('created_by_role')->nullable();
            $table->timestampTz('submitted_desa_at')->nullable();

            // Audit Verifikasi Kecamatan
            $table->uuid('verified_kecamatan_by')->nullable();
            $table->foreign('verified_kecamatan_by')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestampTz('verified_kecamatan_at')->nullable();
            $table->text('catatan_kecamatan')->nullable();

            // Audit Verifikasi Bappeda
            $table->uuid('verified_bappeda_by')->nullable();
            $table->foreign('verified_bappeda_by')->references('uuid')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestampTz('verified_bappeda_at')->nullable();
            $table->text('catatan_bappeda')->nullable();

            $table->timestampsTz();

            // Indeks
            $table->spatialIndex('geom');
            $table->index('id_kecamatan');
            $table->index('id_desa');
            $table->index('tipe_kode');
            $table->index('status_verifikasi');
            $table->index('plotting_id');
        });

        // Tambahkan self-referencing foreign key setelah tabel infrastruktur_segmen terbentuk
        Schema::table('infrastruktur_segmen', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('infrastruktur_segmen')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('infrastruktur_segmen', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::dropIfExists('infrastruktur_segmen');
    }
};

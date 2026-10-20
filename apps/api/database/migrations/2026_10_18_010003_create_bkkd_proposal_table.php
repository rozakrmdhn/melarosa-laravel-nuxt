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
        Schema::create('bkkd_proposal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor_registrasi')->unique();
            $table->integer('tahun_anggaran');
            $table->integer('id_kecamatan');
            $table->foreign('id_kecamatan')->references('id')->on('bataswilayah_kecamatan')->restrictOnDelete();
            $table->bigInteger('id_desa');
            $table->foreign('id_desa')->references('id')->on('bataswilayah_desa')->restrictOnDelete();
            $table->uuid('id_bidang');
            $table->foreign('id_bidang')->references('id')->on('ref_bkkd_bidang')->restrictOnDelete();
            $table->string('nomor_surat_desa');
            $table->date('tanggal_surat_desa');
            $table->date('tanggal_diterima');
            $table->string('judul_kegiatan');
            $table->text('uraian_kegiatan')->nullable();
            $table->text('lokasi_kegiatan')->nullable();
            $table->decimal('volume', 12, 2)->nullable();
            $table->string('satuan', 30)->nullable();
            $table->decimal('nilai_usulan', 15, 2);
            $table->string('status')->default('draft');
            $table->text('catatan')->nullable();

            // Atribut penetapan & penyesuaian pagu oleh TAPD
            $table->decimal('nilai_disetujui_tapd', 15, 2)->nullable();
            $table->text('catatan_tapd')->nullable();
            $table->date('tanggal_penetapan_tapd')->nullable();
            $table->uuid('ditetapkan_oleh')->nullable();
            $table->foreign('ditetapkan_oleh')->references('uuid')->on('users')->nullOnDelete();

            // Atribut geometri PostGIS (SRID 4326)
            $table->geometry('geom', srid: 4326)->nullable();

            $table->integer('versi')->default(1);
            $table->uuid('created_by');
            $table->foreign('created_by')->references('uuid')->on('users')->restrictOnDelete();
            $table->uuid('updated_by')->nullable();
            $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tahun_anggaran', 'id_kecamatan', 'id_desa'], 'idx_bkkd_prop_wilayah');
            $table->index('status', 'idx_bkkd_prop_status');
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->spatialIndex('geom');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkkd_proposal');
    }
};

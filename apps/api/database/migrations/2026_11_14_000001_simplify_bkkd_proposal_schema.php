<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merapikan desain skema database Modul Analisis Spasial Proposal BKKD:
     * 1. Menghapus 4 tabel legacy verifikasi & lampiran yang tidak dipakai.
     * 2. Menghapus kolom duplikat catatan_konfirmasi_desa pada bkkd_proposal_lokasi.
     * 3. Menghapus 18 kolom legacy administratif, dimensi lama, dan alur TAPD/OPD/PAPBD pada bkkd_proposal.
     */
    public function up(): void
    {
        // 1. Hapus 4 tabel legacy dalam urutan aman terhadap foreign key
        Schema::dropIfExists('bkkd_verifikasi_riwayat');
        Schema::dropIfExists('bkkd_verifikasi');
        Schema::dropIfExists('bkkd_verifikasi_dokumen');
        Schema::dropIfExists('bkkd_proposal_lampiran');

        // 2. Hapus kolom duplikat pada bkkd_proposal_lokasi
        if (Schema::hasTable('bkkd_proposal_lokasi') && Schema::hasColumn('bkkd_proposal_lokasi', 'catatan_konfirmasi_desa')) {
            Schema::table('bkkd_proposal_lokasi', function (Blueprint $table) {
                $table->dropColumn('catatan_konfirmasi_desa');
            });
        }

        // 3. Rapikan kolom tidak terpakai pada bkkd_proposal
        if (Schema::hasTable('bkkd_proposal')) {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE bkkd_proposal DROP CONSTRAINT IF EXISTS bkkd_proposal_ditetapkan_oleh_foreign');
                DB::statement('ALTER TABLE bkkd_proposal DROP CONSTRAINT IF EXISTS bkkd_proposal_id_proposal_induk_foreign');
                DB::statement('DROP INDEX IF EXISTS idx_bkkd_prop_status');
                DB::statement('DROP INDEX IF EXISTS idx_bkkd_prop_status_tahun');
                DB::statement('DROP INDEX IF EXISTS idx_bkkd_prop_tahap');
                DB::statement('DROP INDEX IF EXISTS idx_bkkd_prop_induk');
                DB::statement('DROP INDEX IF EXISTS bkkd_proposal_geom_spatialindex');
            } else {
                Schema::table('bkkd_proposal', function (Blueprint $table) {
                    foreach (['ditetapkan_oleh', 'id_proposal_induk'] as $fkCol) {
                        if (Schema::hasColumn('bkkd_proposal', $fkCol)) {
                            try {
                                $table->dropForeign([$fkCol]);
                            } catch (\Throwable $e) {
                                // Ignore if foreign key name differs or absent on SQLite
                            }
                        }
                    }

                    foreach ([
                        'idx_bkkd_prop_status',
                        'idx_bkkd_prop_status_tahun',
                        'idx_bkkd_prop_tahap',
                        'idx_bkkd_prop_induk',
                        'bkkd_proposal_geom_spatialindex',
                    ] as $indexName) {
                        if (Schema::hasIndex('bkkd_proposal', $indexName)) {
                            $table->dropIndex($indexName);
                        }
                    }
                });
            }

            $columnsToDrop = array_values(array_filter([
                'nomor_surat_desa',
                'tanggal_surat_desa',
                'tanggal_diterima',
                'panjang',
                'lebar',
                'tinggi',
                'volume',
                'satuan',
                'geom',
                'status',
                'catatan',
                'nilai_disetujui_tapd',
                'catatan_tapd',
                'tanggal_penetapan_tapd',
                'ditetapkan_oleh',
                'tahap_anggaran',
                'id_proposal_induk',
                'alasan_pergantian_papbd',
            ], fn (string $col) => Schema::hasColumn('bkkd_proposal', $col)));

            if (!empty($columnsToDrop)) {
                Schema::table('bkkd_proposal', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }

            if (
                Schema::hasColumn('bkkd_proposal', 'status_analisa_spasial') &&
                !Schema::hasIndex('bkkd_proposal', 'idx_bkkd_prop_analisa_tahun')
            ) {
                Schema::table('bkkd_proposal', function (Blueprint $table) {
                    $table->index(['status_analisa_spasial', 'tahun_anggaran'], 'idx_bkkd_prop_analisa_tahun');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bkkd_proposal')) {
            if (Schema::hasIndex('bkkd_proposal', 'idx_bkkd_prop_analisa_tahun')) {
                Schema::table('bkkd_proposal', function (Blueprint $table) {
                    $table->dropIndex('idx_bkkd_prop_analisa_tahun');
                });
            }

            Schema::table('bkkd_proposal', function (Blueprint $table) {
                if (!Schema::hasColumn('bkkd_proposal', 'nomor_surat_desa')) {
                    $table->string('nomor_surat_desa')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'tanggal_surat_desa')) {
                    $table->date('tanggal_surat_desa')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'tanggal_diterima')) {
                    $table->date('tanggal_diterima')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'panjang')) {
                    $table->decimal('panjang', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'lebar')) {
                    $table->decimal('lebar', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'tinggi')) {
                    $table->decimal('tinggi', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'volume')) {
                    $table->decimal('volume', 12, 2)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'satuan')) {
                    $table->string('satuan', 30)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'geom')) {
                    $table->geometry('geom', subtype: 'linestring', srid: 4326)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'status')) {
                    $table->string('status')->default('draft');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'catatan')) {
                    $table->text('catatan')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'nilai_disetujui_tapd')) {
                    $table->decimal('nilai_disetujui_tapd', 15, 2)->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'catatan_tapd')) {
                    $table->text('catatan_tapd')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'tanggal_penetapan_tapd')) {
                    $table->date('tanggal_penetapan_tapd')->nullable();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'ditetapkan_oleh')) {
                    $table->uuid('ditetapkan_oleh')->nullable();
                    $table->foreign('ditetapkan_oleh')->references('uuid')->on('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'tahap_anggaran')) {
                    $table->string('tahap_anggaran', 20)->default('induk');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'id_proposal_induk')) {
                    $table->uuid('id_proposal_induk')->nullable();
                    $table->foreign('id_proposal_induk')->references('id')->on('bkkd_proposal')->nullOnDelete();
                }
                if (!Schema::hasColumn('bkkd_proposal', 'alasan_pergantian_papbd')) {
                    $table->text('alasan_pergantian_papbd')->nullable();
                }
            });

            Schema::table('bkkd_proposal', function (Blueprint $table) {
                $table->index('status', 'idx_bkkd_prop_status');
                $table->index(['status', 'tahun_anggaran'], 'idx_bkkd_prop_status_tahun');
                $table->index(['tahun_anggaran', 'tahap_anggaran'], 'idx_bkkd_prop_tahap');
                $table->index('id_proposal_induk', 'idx_bkkd_prop_induk');
                if (DB::getDriverName() === 'pgsql') {
                    $table->spatialIndex('geom');
                }
            });
        }

        if (Schema::hasTable('bkkd_proposal_lokasi') && !Schema::hasColumn('bkkd_proposal_lokasi', 'catatan_konfirmasi_desa')) {
            Schema::table('bkkd_proposal_lokasi', function (Blueprint $table) {
                $table->text('catatan_konfirmasi_desa')->nullable();
            });
        }

        if (!Schema::hasTable('bkkd_proposal_lampiran')) {
            Schema::create('bkkd_proposal_lampiran', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('id_proposal');
                $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
                $table->string('jenis_dokumen');
                $table->string('tipe_sumber', 20)->default('file');
                $table->text('url_link')->nullable();
                $table->string('nama_asli');
                $table->string('path')->nullable();
                $table->string('mime_type')->nullable();
                $table->bigInteger('ukuran_byte')->nullable();
                $table->uuid('uploaded_by');
                $table->foreign('uploaded_by')->references('uuid')->on('users')->restrictOnDelete();
                $table->timestampTz('created_at')->nullable();
                $table->index('id_proposal');
            });
        }

        if (!Schema::hasTable('bkkd_verifikasi_dokumen')) {
            Schema::create('bkkd_verifikasi_dokumen', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('id_opd');
                $table->foreign('id_opd')->references('id')->on('ref_opd')->restrictOnDelete();
                $table->string('nomor_surat_hasil', 100);
                $table->date('tanggal_surat_hasil');
                $table->date('tanggal_diterima')->nullable();
                $table->integer('tahun_anggaran')->index();
                $table->string('tipe_sumber', 20)->default('file');
                $table->text('url_link')->nullable();
                $table->string('path_dokumen')->nullable();
                $table->string('nama_dokumen_asli')->nullable();
                $table->text('catatan_umum')->nullable();
                $table->uuid('input_by');
                $table->foreign('input_by')->references('uuid')->on('users')->restrictOnDelete();
                $table->uuid('updated_by')->nullable();
                $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
                $table->timestampsTz();
                $table->index(['id_opd', 'tahun_anggaran']);
                $table->index('nomor_surat_hasil');
            });
        }

        if (!Schema::hasTable('bkkd_verifikasi')) {
            Schema::create('bkkd_verifikasi', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('id_proposal');
                $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
                $table->uuid('id_dokumen')->nullable();
                $table->foreign('id_dokumen')->references('id')->on('bkkd_verifikasi_dokumen')->nullOnDelete();
                $table->uuid('id_opd');
                $table->foreign('id_opd')->references('id')->on('ref_opd')->restrictOnDelete();
                $table->string('nomor_surat_hasil');
                $table->date('tanggal_surat_hasil');
                $table->date('tanggal_diterima');
                $table->string('hasil');
                $table->decimal('nilai_rekomendasi', 15, 2)->nullable();
                $table->decimal('volume_rekomendasi', 12, 2)->nullable();
                $table->decimal('panjang_rekomendasi', 10, 2)->nullable();
                $table->decimal('lebar_rekomendasi', 10, 2)->nullable();
                $table->decimal('tinggi_rekomendasi', 10, 2)->nullable();
                $table->text('catatan_opd')->nullable();
                $table->string('tipe_sumber', 20)->default('file');
                $table->text('url_link')->nullable();
                $table->string('path_dokumen')->nullable();
                $table->string('nama_dokumen_asli')->nullable();
                $table->boolean('is_aktif')->default(true);
                $table->integer('versi')->default(1);
                $table->uuid('input_by');
                $table->foreign('input_by')->references('uuid')->on('users')->restrictOnDelete();
                $table->uuid('updated_by')->nullable();
                $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
                $table->timestampsTz();
                $table->index('id_proposal');
                $table->index('id_dokumen');
                $table->index(['id_proposal', 'is_aktif']);
            });
        }

        if (!Schema::hasTable('bkkd_verifikasi_riwayat')) {
            Schema::create('bkkd_verifikasi_riwayat', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('id_verifikasi');
                $table->foreign('id_verifikasi')->references('id')->on('bkkd_verifikasi')->cascadeOnDelete();
                $table->integer('versi');
                $table->string('aksi');
                $table->jsonb('field_berubah')->nullable();
                $table->jsonb('data_sebelum')->nullable();
                $table->jsonb('data_sesudah')->nullable();
                $table->text('alasan_perubahan')->nullable();
                $table->uuid('changed_by');
                $table->foreign('changed_by')->references('uuid')->on('users')->restrictOnDelete();
                $table->timestampTz('created_at')->nullable();
                $table->index(['id_verifikasi', 'versi']);
            });
        }
    }
};


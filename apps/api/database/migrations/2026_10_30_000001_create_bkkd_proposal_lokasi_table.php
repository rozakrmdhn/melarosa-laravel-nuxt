<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bkkd_proposal')) {
            Schema::table('bkkd_proposal', function (Blueprint $table) {
                if (!Schema::hasColumn('bkkd_proposal', 'jumlah_lokasi')) {
                    $table->integer('jumlah_lokasi')->default(0)->after('lokasi_kegiatan');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'total_panjang_gis')) {
                    $table->decimal('total_panjang_gis', 12, 2)->default(0)->after('jumlah_lokasi');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'status_analisa_spasial')) {
                    $table->string('status_analisa_spasial', 40)->nullable()->index()->after('status');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'catatan_konfirmasi_desa')) {
                    $table->text('catatan_konfirmasi_desa')->nullable()->after('catatan_tapd');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'dikonfirmasi_oleh')) {
                    $table->uuid('dikonfirmasi_oleh')->nullable()->after('catatan_konfirmasi_desa');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'dikonfirmasi_pada')) {
                    $table->timestamp('dikonfirmasi_pada')->nullable()->after('dikonfirmasi_oleh');
                }
            });
        }

        Schema::create('bkkd_proposal_lokasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_proposal')->index();
            $table->uuid('id_jalan_poros')->nullable()->index();
            $table->smallInteger('urutan')->default(1);
            $table->string('nama_lokasi', 255);
            $table->decimal('panjang_rencana_m', 10, 2)->nullable();
            $table->decimal('panjang_gis_m', 10, 2)->default(0);
            $table->decimal('lebar_m', 6, 2)->nullable();
            $table->string('status_analisa_spasial', 40)->default('belum_dianalisa')->index();
            $table->json('ringkasan_analisa')->nullable();
            $table->text('catatan_konfirmasi_desa')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Simpan GeoJSON string pada driver non-PostgreSQL (SQLite testing)
            if (DB::connection()->getDriverName() !== 'pgsql') {
                $table->text('geom')->nullable();
            }

            $table->foreign('id_proposal')
                ->references('id')
                ->on('bkkd_proposal')
                ->onDelete('cascade');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bkkd_proposal_lokasi ADD COLUMN geom geometry(LineString, 4326)');
            DB::statement('CREATE INDEX bkkd_proposal_lokasi_geom_gist ON bkkd_proposal_lokasi USING GIST (geom)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bkkd_proposal_lokasi');

        if (Schema::hasTable('bkkd_proposal')) {
            Schema::table('bkkd_proposal', function (Blueprint $table) {
                $cols = [
                    'jumlah_lokasi',
                    'total_panjang_gis',
                    'status_analisa_spasial',
                    'catatan_konfirmasi_desa',
                    'dikonfirmasi_oleh',
                    'dikonfirmasi_pada',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('bkkd_proposal', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};


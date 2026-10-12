<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            PreventRequestForgery::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('ST_GeomFromGeoJSON', fn ($g = null) => $g);
                $pdo->sqliteCreateFunction('ST_SetSRID', fn ($g = null, $srid = 4326) => $g);
                $pdo->sqliteCreateFunction('ST_Multi', fn ($g = null) => $g);
                $pdo->sqliteCreateFunction('ST_IsValid', fn ($g = null) => 1);
                $pdo->sqliteCreateFunction('ST_Length', fn ($g = null) => 100.0);
                $pdo->sqliteCreateFunction('ST_AsGeoJSON', fn ($g = null) => $g ?? '{}');
                $pdo->sqliteCreateFunction('ST_Centroid', fn ($g = null) => $g ?? '{}');
                $pdo->sqliteCreateFunction('ST_PointOnSurface', fn ($g = null) => $g ?? '{}');
                $pdo->sqliteCreateFunction('ST_Extent', fn ($g = null) => $g ?? 'BOX(111.4 -7.5, 112.2 -7.0)');
                $pdo->sqliteCreateFunction('ST_XMin', fn ($g = null) => 111.4);
                $pdo->sqliteCreateFunction('ST_YMin', fn ($g = null) => -7.5);
                $pdo->sqliteCreateFunction('ST_XMax', fn ($g = null) => 112.2);
                $pdo->sqliteCreateFunction('ST_YMax', fn ($g = null) => -7.0);
            }
            if (!Schema::hasTable('bataswilayah_kecamatan')) {
                Schema::create('bataswilayah_kecamatan', function ($table) {
                    $table->increments('id');
                    $table->string('nama_kecamatan')->nullable();
                    $table->string('nama_pimpinan')->nullable();
                    $table->string('nama_jabatan')->nullable();
                    $table->string('nip')->nullable();
                    $table->string('pangkat_gol')->nullable();
                    $table->timestamps();
                });
            }

            if (!Schema::hasTable('bataswilayah_desa')) {
                Schema::create('bataswilayah_desa', function ($table) {
                    $table->bigIncrements('id');
                    $table->string('nama_desa')->nullable();
                    $table->unsignedInteger('id_kecamatan')->nullable();
                    $table->timestamps();
                });
            }

            if (!Schema::hasTable('jalan_porosdesa')) {
                Schema::create('jalan_porosdesa', function ($table) {
                    $table->uuid('id')->primary();
                    $table->integer('kode_ruas')->nullable();
                    $table->string('nama_ruas')->nullable();
                    $table->string('desa')->nullable();
                    $table->string('kecamatan')->nullable();
                    $table->decimal('panjang', 10, 2)->nullable();
                    $table->decimal('lebar', 5, 2)->nullable();
                    $table->string('perkerasan')->nullable();
                    $table->string('kondisi')->nullable();
                    $table->string('status_awal')->nullable();
                    $table->string('status_eksisting')->nullable();
                    $table->string('sumber_data')->nullable();
                    $table->integer('id_desa')->nullable();
                    $table->integer('id_kecamatan')->nullable();
                    $table->text('geom')->nullable();
                    $table->timestamps();
                });
            }
        }
    }
}

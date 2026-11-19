<?php

namespace Tests\Feature\Bkkd;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\JalanPorosDesa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RtrwJaringanJalanSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $bappedaUser;
    protected User $viewerUser;
    protected BatasWilayahKecamatan $kecamatan;
    protected BatasWilayahDesa $desaTerdata;
    protected BatasWilayahDesa $desaKosong;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['jaringan-jalan-view', 'jaringan-jalan-manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $bappedaRole = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $bappedaRole->syncPermissions(['jaringan-jalan-view', 'jaringan-jalan-manage']);

        $viewerRole = Role::firstOrCreate(['name' => 'verifierKecamatan', 'guard_name' => 'web']);
        $viewerRole->syncPermissions(['jaringan-jalan-view']);

        $this->kecamatan = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522160],
            ['nama_kecamatan' => 'Kalitidu']
        );

        $this->desaTerdata = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522162001],
            ['nama_desa' => 'Kalitidu', 'id_kecamatan' => $this->kecamatan->id]
        );

        $this->desaKosong = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522162021],
            ['nama_desa' => 'Sukoharjo', 'id_kecamatan' => $this->kecamatan->id]
        );

        $this->bappedaUser = User::factory()->create();
        $this->bappedaUser->assignRole($bappedaRole);

        $this->viewerUser = User::factory()->create([
            'id_kecamatan' => $this->kecamatan->id,
        ]);
        $this->viewerUser->assignRole($viewerRole);

        // Baseline survey 2021 row only in desaTerdata
        JalanPorosDesa::create([
            'id' => (string) Str::uuid(),
            'kode_ruas' => 101,
            'nama_ruas' => 'Jalan Poros Kalitidu',
            'desa' => 'Kalitidu',
            'kecamatan' => 'Kalitidu',
            'id_desa' => $this->desaTerdata->id,
            'id_kecamatan' => $this->kecamatan->id,
            'panjang' => 850,
            'hierarki_jalan' => JalanPorosDesa::HIERARKI_POROS_DESA,
            'sumber_data' => 'Data Kecamatan',
            'tahun_survey' => 2021,
        ]);

        if (!Schema::hasTable('rtrw_jaringan_transportasi_ln')) {
            Schema::create('rtrw_jaringan_transportasi_ln', function (Blueprint $table) {
                $table->id();
                $table->text('geom')->nullable();
                $table->string('Nama_Jln')->nullable();
                $table->string('NAMOBJ')->nullable();
                $table->string('ORDE01')->nullable();
                $table->string('ORDE02')->nullable();
                $table->string('ORDE03')->nullable();
                $table->string('ORDE04')->nullable();
                $table->string('STSJRN')->nullable();
                $table->string('SBDATA')->nullable();
                $table->float('panjang_m')->nullable();
                $table->unsignedBigInteger('id_desa')->nullable();
                $table->unsignedBigInteger('id_kecamatan')->nullable();
            });
        }

        DB::table('rtrw_jaringan_transportasi_ln')->insert([
            [
                'geom' => json_encode(['type' => 'LineString', 'coordinates' => [[111.78, -7.14], [111.785, -7.142]]]),
                'Nama_Jln' => 'Jalan Desa Sukoharjo Utara',
                'NAMOBJ' => 'Jalan Desa',
                'ORDE01' => 'Sistem Jaringan Jalan',
                'ORDE02' => 'Jalan Desa',
                'ORDE03' => 'Jalan Desa',
                'ORDE04' => 'Jalan Desa',
                'STSJRN' => 'Eksisting',
                'SBDATA' => 'RTRW Bojonegoro',
                'panjang_m' => 420.5,
                'id_desa' => $this->desaKosong->id,
                'id_kecamatan' => $this->kecamatan->id,
            ],
            [
                'geom' => json_encode(['type' => 'LineString', 'coordinates' => [[111.79, -7.14], [111.795, -7.14]]]),
                'Nama_Jln' => 'Jalur KA Lintas Bojonegoro',
                'NAMOBJ' => 'Jaringan Jalur Kereta Api Antarkota',
                'ORDE01' => 'Sistem Jaringan Kereta Api',
                'ORDE02' => 'Jaringan Jalur Kereta Api (KA)',
                'ORDE03' => 'Jaringan Jalur Kereta Api umum',
                'ORDE04' => 'Jaringan Jalur Kereta Api Antarkota',
                'STSJRN' => 'Eksisting',
                'SBDATA' => 'RTRW Bojonegoro',
                'panjang_m' => 1200,
                'id_desa' => $this->desaKosong->id,
                'id_kecamatan' => $this->kecamatan->id,
            ],
        ]);
    }

    public function test_cakupan_desa_identifies_missing_villages_and_excludes_railway_from_rtrw_list(): void
    {
        $cakupanRes = $this->actingAs($this->viewerUser)
            ->getJson('/api/v1/admin/jaringan-jalan/cakupan-desa?status_cakupan=belum_terdata')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.total_desa', 2)
            ->assertJsonPath('summary.desa_terdata', 1)
            ->assertJsonPath('summary.desa_belum_terdata', 1);

        $this->assertSame('Sukoharjo', $cakupanRes->json('data.0.nama_desa'));
        $this->assertSame(1, $cakupanRes->json('data.0.rtrw_jalan_desa_count'));

        $rtrwRes = $this->actingAs($this->viewerUser)
            ->getJson('/api/v1/admin/jaringan-jalan/rtrw?only_missing_desa=1')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertCount(1, $rtrwRes->json('data'));
        $this->assertSame('Jalan Desa Sukoharjo Utara', $rtrwRes->json('data.0.nama_jln'));
    }

    public function test_bappeda_can_adopt_rtrw_segment_and_skips_duplicates_while_viewer_is_forbidden(): void
    {
        $rtrwId = (int) DB::table('rtrw_jaringan_transportasi_ln')
            ->where('ORDE02', 'Jalan Desa')
            ->value('id');

        $this->actingAs($this->viewerUser)
            ->postJson('/api/v1/admin/jaringan-jalan/rtrw/adopsi', [
                'rtrw_ids' => [$rtrwId],
            ])
            ->assertForbidden();

        $adoptRes = $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/jaringan-jalan/rtrw/adopsi', [
                'rtrw_ids' => [$rtrwId],
                'tahun_survey' => 2026,
                'hierarki_jalan' => 'poros_desa',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.adopted_count', 1)
            ->assertJsonPath('data.skipped_count', 0);

        $this->assertDatabaseHas('jalan_porosdesa', [
            'nama_ruas' => 'Jalan Desa Sukoharjo Utara',
            'id_desa' => $this->desaKosong->id,
            'sumber_data' => JalanPorosDesa::SUMBER_RTRW_ORDE,
            'tahun_survey' => 2026,
            'rtrw_source_id' => $rtrwId,
        ]);

        // Re-running adoption for the same segment must skip cleanly
        $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/jaringan-jalan/rtrw/adopsi', [
                'rtrw_ids' => [$rtrwId],
            ])
            ->assertOk()
            ->assertJsonPath('data.adopted_count', 0)
            ->assertJsonPath('data.skipped_count', 1);
    }

    public function test_spatial_analysis_and_kabupaten_endpoints_classify_poros_kabupaten_and_uncovered_segments(): void
    {
        $porosCoords = [[111.77, -7.13], [111.775, -7.13]];
        $kabCoords = [[111.76, -7.15], [111.765, -7.15]];

        $porosId = (string) JalanPorosDesa::where('kode_ruas', 101)->value('id');
        DB::table('jalan_porosdesa')->where('id', $porosId)->update([
            'geom' => json_encode(['type' => 'LineString', 'coordinates' => $porosCoords]),
        ]);

        $kabId = DB::table('jalan_kabupaten_luarkota')->insertGetId([
            'geom' => json_encode(['type' => 'LineString', 'coordinates' => $kabCoords]),
            'nama_ruas' => 'Kalitidu - Malo',
            'no_ruas' => 42,
            'pangkal' => 'Kalitidu',
            'ujung' => 'Malo',
            'panjang' => 1450,
            'klas_ruas' => 'Kolektor',
            'nama_kecamatan' => 'Kalitidu',
            'status_ruas' => 'Jalan Kabupaten',
        ]);

        $rtrwPorosId = DB::table('rtrw_jaringan_transportasi_ln')->insertGetId([
            'geom' => json_encode(['type' => 'LineString', 'coordinates' => $porosCoords]),
            'Nama_Jln' => 'Segmen RTRW Irisan Poros',
            'NAMOBJ' => 'Jalan Desa',
            'ORDE01' => 'Sistem Jaringan Jalan',
            'ORDE02' => 'Jalan Desa',
            'ORDE03' => 'Jalan Desa',
            'ORDE04' => 'Jalan Desa',
            'STSJRN' => 'Eksisting',
            'SBDATA' => 'RTRW Bojonegoro',
            'panjang_m' => 550,
            'id_desa' => $this->desaTerdata->id,
            'id_kecamatan' => $this->kecamatan->id,
        ]);

        $rtrwKabId = DB::table('rtrw_jaringan_transportasi_ln')->insertGetId([
            'geom' => json_encode(['type' => 'LineString', 'coordinates' => $kabCoords]),
            'Nama_Jln' => 'Segmen RTRW Jalan Kab',
            'NAMOBJ' => 'Jalan Lokal Primer',
            'ORDE01' => 'Sistem Jaringan Jalan',
            'ORDE02' => 'Jalan Lokal Primer',
            'ORDE03' => 'Jalan Kabupaten',
            'ORDE04' => 'Jalan Lokal Primer',
            'STSJRN' => 'Eksisting',
            'SBDATA' => 'RTRW Bojonegoro',
            'panjang_m' => 550,
            'id_desa' => $this->desaTerdata->id,
            'id_kecamatan' => $this->kecamatan->id,
        ]);

        // Viewer cannot trigger recalculation
        $this->actingAs($this->viewerUser)
            ->postJson('/api/v1/admin/jaringan-jalan/analisa-spasial/run')
            ->assertForbidden();

        // Bappeda user can run spatial analysis across all 3 non-railway RTRW segments
        $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/jaringan-jalan/analisa-spasial/run')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.processed_count', 3);

        $summaryRes = $this->actingAs($this->viewerUser)
            ->getJson('/api/v1/admin/jaringan-jalan/analisa-spasial/summary')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('totals.total_segmen', 3)
            ->assertJsonPath('totals.statuses.terdata_poros_desa.count', 1)
            ->assertJsonPath('totals.statuses.terdata_jalan_kabupaten.count', 1)
            ->assertJsonPath('totals.statuses.belum_terdata.count', 1);

        $this->assertSame(1, $summaryRes->json('dataset_overview.jalan_poros_ruas'));
        $this->assertSame(1, $summaryRes->json('dataset_overview.jalan_kabupaten_ruas'));

        $this->actingAs($this->viewerUser)
            ->getJson('/api/v1/admin/jaringan-jalan/analisa-spasial?status_analisa=terdata_poros_desa')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rtrwPorosId);

        $mapIdxRes = $this->actingAs($this->viewerUser)
            ->getJson('/api/v1/admin/jaringan-jalan/analisa-spasial/map-index')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertContains($rtrwKabId, $mapIdxRes->json('ids_by_status.terdata_jalan_kabupaten'));

        $this->actingAs($this->viewerUser)
            ->getJson("/api/v1/admin/jaringan-jalan/analisa-spasial/{$rtrwKabId}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('segment.id', $rtrwKabId)
            ->assertJsonPath('matched_kabupaten.0.nama_ruas', 'Kalitidu - Malo');

        $this->actingAs($this->viewerUser)
            ->getJson('/api/v1/admin/jaringan-jalan/kabupaten')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_ruas', 'Kalitidu - Malo');

        $this->actingAs($this->viewerUser)
            ->getJson("/api/v1/admin/jaringan-jalan/kabupaten/{$kabId}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $kabId)
            ->assertJsonPath('data.no_ruas', '42');

        $this->actingAs($this->viewerUser)
            ->getJson("/api/v1/admin/jaringan-jalan/{$porosId}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.properties.id', $porosId)
            ->assertJsonPath('data.properties.nama_ruas', 'Jalan Poros Kalitidu');
    }
}

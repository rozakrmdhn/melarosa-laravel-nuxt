<?php

namespace Tests\Feature;

use App\Enums\StatusVerifikasi;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\InfrastrukturSegmen;
use App\Models\InfrastrukturTipe;
use App\Models\JalanPorosDesa;
use App\Models\PlottingAnggaran;
use App\Models\RefSumberDana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InfrastrukturSegmenAutoDigitasiTest extends TestCase
{
    use RefreshDatabase;

    protected BatasWilayahKecamatan $kec1;
    protected BatasWilayahKecamatan $kec2;
    protected BatasWilayahDesa $desa1a;
    protected BatasWilayahDesa $desa1b;
    protected BatasWilayahDesa $desa2a;
    protected JalanPorosDesa $road1a;
    protected JalanPorosDesa $road1b;
    protected JalanPorosDesa $road2a;
    protected User $desaUser;
    protected User $kecamatanUser;
    protected User $bappedaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'infrastruktur-tipe-view',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-delete',
            'infrastruktur-segmen-verify-kecamatan',
            'infrastruktur-segmen-verify-bappeda',
            'jalan-poros-desa-view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Role Desa sengaja hanya diberi permission segmen (tanpa jalan-poros-desa-view)
        // untuk memverifikasi akses referensi jalan_porosdesa saat digitasi otomatis.
        $roleDesa = Role::firstOrCreate(['name' => 'operatorDesa', 'guard_name' => 'web']);
        $roleDesa->syncPermissions([
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
        ]);

        $roleKec = Role::firstOrCreate(['name' => 'verifierKecamatan', 'guard_name' => 'web']);
        $roleKec->syncPermissions([
            'infrastruktur-tipe-view',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-verify-kecamatan',
            'jalan-poros-desa-view',
        ]);

        $roleBappeda = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $roleBappeda->syncPermissions($permissions);

        $this->kec1 = BatasWilayahKecamatan::firstOrCreate(['id' => 3522010], ['nama_kecamatan' => 'Kecamatan Satu']);
        $this->kec2 = BatasWilayahKecamatan::firstOrCreate(['id' => 3522020], ['nama_kecamatan' => 'Kecamatan Dua']);

        $this->desa1a = BatasWilayahDesa::firstOrCreate(['id' => 3522010001], ['nama_desa' => 'Desa 1A', 'id_kecamatan' => $this->kec1->id]);
        $this->desa1b = BatasWilayahDesa::firstOrCreate(['id' => 3522010002], ['nama_desa' => 'Desa 1B', 'id_kecamatan' => $this->kec1->id]);
        $this->desa2a = BatasWilayahDesa::firstOrCreate(['id' => 3522020001], ['nama_desa' => 'Desa 2A', 'id_kecamatan' => $this->kec2->id]);

        InfrastrukturTipe::firstOrCreate(
            ['kode' => 'jalan_poros'],
            ['nama' => 'Jalan Poros Desa', 'geom_type' => 'LineString', 'is_active' => true]
        );

        $this->road1a = $this->makeRoad(101, 'Poros Desa 1A', $this->kec1->id, $this->desa1a->id);
        $this->road1b = $this->makeRoad(102, 'Poros Desa 1B', $this->kec1->id, $this->desa1b->id);
        $this->road2a = $this->makeRoad(201, 'Poros Desa 2A', $this->kec2->id, $this->desa2a->id);

        $this->desaUser = User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => $this->desa1a->id,
        ]);
        $this->desaUser->assignRole($roleDesa);

        $this->kecamatanUser = User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => null,
        ]);
        $this->kecamatanUser->assignRole($roleKec);

        $this->bappedaUser = User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'id_kecamatan' => null,
            'id_desa' => null,
        ]);
        $this->bappedaUser->assignRole($roleBappeda);
    }

    private function makeRoad(int $kode, string $nama, int $idKec, int $idDesa): JalanPorosDesa
    {
        $road = new JalanPorosDesa();
        $road->id = (string) Str::uuid();
        $road->kode_ruas = $kode;
        $road->nama_ruas = $nama;
        $road->id_kecamatan = $idKec;
        $road->id_desa = $idDesa;
        $road->kecamatan = 'Kecamatan Test';
        $road->desa = 'Desa Test';
        $road->panjang = 250;
        $road->lebar = 3.5;
        $road->geom = json_encode([
            'type' => 'LineString',
            'coordinates' => [
                [111.8800, -7.1500],
                [111.8810, -7.1505],
                [111.8820, -7.1510],
            ],
        ]);
        $road->save();

        return $road;
    }

    public function test_role_desa_can_lookup_own_roads_and_create_auto_segment_only_within_own_desa(): void
    {
        // Lookup hanya menampilkan ruas desa milik user
        $lookup = $this->actingAs($this->desaUser)
            ->getJson('/api/v1/admin/jalan-poros-desa?format=lookup&limit=2000');

        $lookup->assertOk()->assertJsonPath('ok', true);
        $ids = collect($lookup->json('data'))->pluck('id')->all();
        $this->assertContains($this->road1a->id, $ids);
        $this->assertNotContains($this->road1b->id, $ids);
        $this->assertNotContains($this->road2a->id, $ids);

        // Dapat mengakses detail geometri ruas dan master tipe infrastruktur
        $this->actingAs($this->desaUser)
            ->getJson("/api/v1/admin/jalan-poros-desa/{$this->road1a->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($this->desaUser)
            ->getJson('/api/v1/admin/infrastruktur-tipe?active_only=1')
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Dapat menyimpan segmen hasil digitasi otomatis yang mengacu jalan_porosdesa desanya
        $createRes = $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Segmen Otomatis Desa 1A',
                'parent_id' => $this->road1a->id,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 120.5,
                'sumber_data' => 'Digitasi Otomatis (Ref. Jalan Poros Desa)',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [111.8800, -7.1500],
                        [111.8810, -7.1505],
                    ],
                ],
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.parent_id', $this->road1a->id)
            ->assertJsonPath('data.id_desa', $this->desa1a->id);

        // Ditolak jika mencoba mengacu ruas jalan_porosdesa milik desa lain
        $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Ilegal Desa Lain',
                'parent_id' => $this->road1b->id,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1b->id,
                'panjang' => 100,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [111.8800, -7.1500],
                        [111.8810, -7.1505],
                    ],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_role_desa_cannot_update_verified_or_other_desa_segment(): void
    {
        $verifiedSeg = new InfrastrukturSegmen();
        $verifiedSeg->id = (string) Str::uuid();
        $verifiedSeg->tipe_kode = 'jalan_poros';
        $verifiedSeg->namobj = 'Segmen Sudah Sah';
        $verifiedSeg->id_kecamatan = $this->kec1->id;
        $verifiedSeg->id_desa = $this->desa1a->id;
        $verifiedSeg->status_verifikasi = StatusVerifikasi::VerifiedBappeda->value;
        $verifiedSeg->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"LineString\",\"coordinates\":[[111.88,-7.15],[111.881,-7.151]]}'), 4326)");
        $verifiedSeg->save();

        $this->actingAs($this->desaUser)
            ->putJson("/api/v1/admin/infrastruktur-segmen/{$verifiedSeg->id}", ['namobj' => 'Coba Ubah'])
            ->assertStatus(403);

        $otherDesaDraft = new InfrastrukturSegmen();
        $otherDesaDraft->id = (string) Str::uuid();
        $otherDesaDraft->tipe_kode = 'jalan_poros';
        $otherDesaDraft->namobj = 'Draft Desa 1B';
        $otherDesaDraft->id_kecamatan = $this->kec1->id;
        $otherDesaDraft->id_desa = $this->desa1b->id;
        $otherDesaDraft->status_verifikasi = StatusVerifikasi::Draft->value;
        $otherDesaDraft->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"LineString\",\"coordinates\":[[111.88,-7.15],[111.881,-7.151]]}'), 4326)");
        $otherDesaDraft->save();

        $this->actingAs($this->desaUser)
            ->putJson("/api/v1/admin/infrastruktur-segmen/{$otherDesaDraft->id}", ['namobj' => 'Coba Ubah Desa Lain'])
            ->assertStatus(403);
    }

    public function test_role_kecamatan_can_lookup_kecamatan_roads_and_create_auto_segment(): void
    {
        $lookup = $this->actingAs($this->kecamatanUser)
            ->getJson('/api/v1/admin/jalan-poros-desa?format=lookup&limit=2000');

        $lookup->assertOk();
        $ids = collect($lookup->json('data'))->pluck('id')->all();
        $this->assertContains($this->road1a->id, $ids);
        $this->assertContains($this->road1b->id, $ids);
        $this->assertNotContains($this->road2a->id, $ids);

        // Kecamatan dapat membuat segmen untuk desa di dalam kecamatannya
        $this->actingAs($this->kecamatanUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Segmen Otomatis Kecamatan',
                'parent_id' => $this->road1b->id,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1b->id,
                'panjang' => 150,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [111.8800, -7.1500],
                        [111.8820, -7.1510],
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.id_desa', $this->desa1b->id);

        // Kecamatan ditolak jika mencoba membuat segmen untuk ruas kecamatan lain
        $this->actingAs($this->kecamatanUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Ilegal Kecamatan Lain',
                'parent_id' => $this->road2a->id,
                'id_kecamatan' => $this->kec2->id,
                'id_desa' => $this->desa2a->id,
                'panjang' => 150,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [111.8800, -7.1500],
                        [111.8820, -7.1510],
                    ],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_role_bappeda_can_lookup_all_roads_and_create_auto_segment_cross_kecamatan(): void
    {
        $lookup = $this->actingAs($this->bappedaUser)
            ->getJson('/api/v1/admin/jalan-poros-desa?format=lookup&limit=2000');

        $lookup->assertOk();
        $ids = collect($lookup->json('data'))->pluck('id')->all();
        $this->assertContains($this->road1a->id, $ids);
        $this->assertContains($this->road1b->id, $ids);
        $this->assertContains($this->road2a->id, $ids);

        $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Segmen Otomatis Bappeda',
                'parent_id' => $this->road2a->id,
                'id_kecamatan' => $this->kec2->id,
                'id_desa' => $this->desa2a->id,
                'panjang' => 250,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [111.8800, -7.1500],
                        [111.8810, -7.1505],
                        [111.8820, -7.1510],
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.parent_id', $this->road2a->id)
            ->assertJsonPath('data.id_kecamatan', $this->kec2->id)
            ->assertJsonPath('data.id_desa', $this->desa2a->id);
    }

    public function test_segmen_uses_ref_sumber_dana_and_supports_legacy_bkk_apbd_values(): void
    {
        RefSumberDana::create([
            'id' => (string) Str::uuid(),
            'kode' => 'BKK_DESA',
            'nama' => 'Bantuan Keuangan Khusus (BKK/Samisade)',
            'kategori' => 'Bantuan',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        RefSumberDana::create([
            'id' => (string) Str::uuid(),
            'kode' => 'APBD_KAB',
            'nama' => 'APBD Kabupaten',
            'kategori' => 'Daerah',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Role desa dengan permission infrastruktur-segmen dapat mengakses ref-sumber-dana
        $refRes = $this->actingAs($this->desaUser)
            ->getJson('/api/v1/admin/ref-sumber-dana?active_only=1');

        $refRes->assertOk()
            ->assertJsonPath('ok', true);
        $codes = collect($refRes->json('data'))->pluck('kode')->all();
        $this->assertContains('BKK_DESA', $codes);
        $this->assertContains('APBD_KAB', $codes);

        // Segmen lama tersimpan dengan 'BKK' dan 'APBD' tetap cocok dengan filter kode ref_sumber_dana
        $legacyBkk = new InfrastrukturSegmen();
        $legacyBkk->id = (string) Str::uuid();
        $legacyBkk->tipe_kode = 'jalan_poros';
        $legacyBkk->namobj = 'Segmen Legacy BKK';
        $legacyBkk->id_kecamatan = $this->kec1->id;
        $legacyBkk->id_desa = $this->desa1a->id;
        $legacyBkk->sumber_dana = 'BKK';
        $legacyBkk->status_verifikasi = StatusVerifikasi::Draft->value;
        $legacyBkk->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"LineString\",\"coordinates\":[[111.88,-7.15],[111.881,-7.151]]}'), 4326)");
        $legacyBkk->save();

        $legacyApbd = new InfrastrukturSegmen();
        $legacyApbd->id = (string) Str::uuid();
        $legacyApbd->tipe_kode = 'jalan_poros';
        $legacyApbd->namobj = 'Segmen Legacy APBD';
        $legacyApbd->id_kecamatan = $this->kec1->id;
        $legacyApbd->id_desa = $this->desa1a->id;
        $legacyApbd->sumber_dana = 'APBD';
        $legacyApbd->status_verifikasi = StatusVerifikasi::Draft->value;
        $legacyApbd->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"LineString\",\"coordinates\":[[111.88,-7.15],[111.882,-7.152]]}'), 4326)");
        $legacyApbd->save();

        $filterBkk = $this->actingAs($this->desaUser)
            ->getJson('/api/v1/admin/infrastruktur-segmen?sumber_dana=BKK_DESA');
        $filterBkk->assertOk();
        $bkkIds = collect($filterBkk->json('data'))->pluck('id')->all();
        $this->assertContains($legacyBkk->id, $bkkIds);
        $this->assertNotContains($legacyApbd->id, $bkkIds);

        // Plotting anggaran menurunkan sumber_dana dari ref_sumber_dana jika payload segmen tidak mengirimkannya
        $plotting = PlottingAnggaran::create([
            'id' => (string) Str::uuid(),
            'tahun_anggaran' => 2026,
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => $this->desa1a->id,
            'jenis_bantuan' => 'BKK Desa',
            'nama_kegiatan' => 'Peningkatan Jalan Poros BKK 2026',
            'sumber_dana' => 'BKK_DESA',
            'target_pagu_anggaran' => 300000000,
            'target_panjang_m' => 400,
            'user_id' => $this->bappedaUser->uuid,
        ]);

        $createdFromPlotting = $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Realisasi BKK_DESA',
                'plotting_id' => $plotting->id,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 200,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [111.8800, -7.1500],
                        [111.8810, -7.1505],
                    ],
                ],
            ]);

        $createdFromPlotting->assertStatus(201)
            ->assertJsonPath('data.sumber_dana', 'BKK_DESA')
            ->assertJsonPath('data.tahun_pembangunan', 2026);
    }

    public function test_budget_envelope_rules_sk_bkkd_role_restrictions_and_10_percent_quota(): void
    {
        RefSumberDana::firstOrCreate(
            ['kode' => 'BKK_DESA'],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Bantuan Keuangan Khusus',
                'kategori' => 'Bantuan',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
        RefSumberDana::firstOrCreate(
            ['kode' => 'DANA_DESA'],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Dana Desa (DDS / APBDes)',
                'kategori' => 'Desa',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        // 1. Role Desa ditolak (403) jika mencoba membuat plotting BKK_DESA
        $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran' => 2026,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'jenis_bantuan' => 'BKK Desa',
                'nama_kegiatan' => 'Coba Buat BKK oleh Desa',
                'sumber_dana' => 'BKK_DESA',
                'nomor_sk' => '188/99/KEP/2026',
                'target_pagu_anggaran' => 500000000,
                'target_panjang_m' => 500,
            ])
            ->assertStatus(403);

        // 2. Role Desa diperbolehkan membuat plotting sumber dana kategori Desa (DANA_DESA) di desanya
        $desaPlotRes = $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran' => 2026,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'jenis_bantuan' => 'Dana Desa',
                'nama_kegiatan' => 'Rabat Beton Dusun Timur',
                'sumber_dana' => 'DANA_DESA',
                'nomor_sk' => 'PERDES/02/2026',
                'target_pagu_anggaran' => 120000000,
                'target_panjang_m' => 150,
            ]);
        $desaPlotRes->assertStatus(201)
            ->assertJsonPath('data.sumber_dana', 'DANA_DESA')
            ->assertJsonPath('data.nomor_sk', 'PERDES/02/2026')
            ->assertJsonPath('data.status_penetapan', 'ditetapkan');

        // 3. Role Bappeda membuat plotting BKK dengan status draft dan ditetapkan
        $draftPlot = PlottingAnggaran::create([
            'id' => (string) Str::uuid(),
            'tahun_anggaran' => 2026,
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => $this->desa1a->id,
            'jenis_bantuan' => 'BKK Desa',
            'nama_kegiatan' => 'Draft SK BKK',
            'sumber_dana' => 'BKK_DESA',
            'status_penetapan' => 'draft',
            'target_pagu_anggaran' => 200000000,
            'target_panjang_m' => 200,
            'user_id' => $this->bappedaUser->uuid,
        ]);

        $officialPlot = PlottingAnggaran::create([
            'id' => (string) Str::uuid(),
            'tahun_anggaran' => 2026,
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => $this->desa1a->id,
            'jenis_bantuan' => 'BKK Desa',
            'nama_kegiatan' => 'SK BKK Resmi Jalan Poros 1A',
            'sumber_dana' => 'BKK_DESA',
            'nomor_sk' => '188/105/KEP/BUP/2026',
            'status_penetapan' => 'ditetapkan',
            'target_pagu_anggaran' => 400000000,
            'target_panjang_m' => 200,
            'user_id' => $this->bappedaUser->uuid,
        ]);

        // 4. Wajib pilih plotting kecuali is_non_pagu = true
        $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Tanpa Pagu Padahal Mode Pagu',
                'is_non_pagu' => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 100,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[111.8800, -7.1500], [111.8810, -7.1505]],
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Inventarisasi Eksisting Lama',
                'is_non_pagu' => true,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 100,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[111.8800, -7.1500], [111.8810, -7.1505]],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_non_pagu', true)
            ->assertJsonPath('data.plotting_id', null);

        // 5. Plotting berstatus draft ditolak (422)
        $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Pakai Plotting Draft',
                'plotting_id' => $draftPlot->id,
                'is_non_pagu' => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 100,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[111.8800, -7.1500], [111.8810, -7.1505]],
                ],
            ])
            ->assertStatus(422);

        // 6. Simpan dalam batas toleransi +10% (215m vs target 200m) berhasil dengan warning, dan override sumber_dana ke BKK_DESA
        $tolerableRes = $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Realisasi Toleransi 10%',
                'plotting_id' => $officialPlot->id,
                'is_non_pagu' => false,
                'sumber_dana' => 'APBD_KAB', // mencoba kirim nilai berbeda dari plotting
                'tahun_pembangunan' => 2020, // mencoba kirim tahun berbeda
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 215,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[111.8800, -7.1500], [111.8810, -7.1505]],
                ],
            ]);
        $tolerableRes->assertStatus(201)
            ->assertJsonPath('data.sumber_dana', 'BKK_DESA')
            ->assertJsonPath('data.tahun_pembangunan', 2026);
        $this->assertNotEmpty($tolerableRes->json('quota_warning'));

        // 7. Penambahan segmen berikutnya (10m -> total 225m > 220m batas +10%) ditolak 422
        $this->actingAs($this->desaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode' => 'jalan_poros',
                'namobj' => 'Melebihi Toleransi',
                'plotting_id' => $officialPlot->id,
                'is_non_pagu' => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa' => $this->desa1a->id,
                'panjang' => 10,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[111.8800, -7.1500], [111.8810, -7.1505]],
                ],
            ])
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Dataset;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\JalanPorosDesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JalanPorosDesaTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $desaUser;
    protected User $otherDesaUser;
    protected User $unauthorizedUser;
    protected BatasWilayahKecamatan $kecamatan;
    protected BatasWilayahDesa $desa1;
    protected BatasWilayahDesa $desa2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Siapkan master wilayah dummy
        $this->kecamatan = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522010],
            ['nama_kecamatan' => 'Kecamatan Bojonegoro']
        );

        $this->desa1 = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522010001],
            ['nama_desa' => 'Desa Sukorejo', 'id_kecamatan' => $this->kecamatan->id]
        );

        $this->desa2 = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522010002],
            ['nama_desa' => 'Desa Kauman', 'id_kecamatan' => $this->kecamatan->id]
        );

        // 2. Siapkan permissions Spatie
        $permissions = [
            'jalan-poros-desa-view',
            'jalan-poros-desa-create',
            'jalan-poros-desa-update',
            'jalan-poros-desa-delete',
            'jalan-poros-desa-split',
            'jalan-poros-desa-manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleDesa  = Role::firstOrCreate(['name' => 'operatorDesa', 'guard_name' => 'web']);

        $roleAdmin->syncPermissions($permissions);
        $roleDesa->syncPermissions([
            'jalan-poros-desa-view',
            'jalan-poros-desa-create',
            'jalan-poros-desa-update',
            'jalan-poros-desa-split',
        ]);

        // 3. User Superadmin
        $this->adminUser = User::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'Super Admin GIS',
            'email'    => 'admin_gis@bojonegorokab.go.id',
            'password' => bcrypt('password'),
            'status'   => true,
        ]);
        $this->adminUser->assignRole($roleAdmin);

        // 4. User Operator Desa Sukorejo
        $this->desaUser = User::create([
            'uuid'         => (string) Str::uuid(),
            'name'         => 'Operator Desa Sukorejo',
            'email'        => 'operator_sukorejo@bojonegorokab.go.id',
            'password'     => bcrypt('password'),
            'id_kecamatan' => $this->kecamatan->id,
            'id_desa'      => $this->desa1->id,
            'status'       => true,
        ]);
        $this->desaUser->assignRole($roleDesa);

        // 5. User Operator Desa Kauman
        $this->otherDesaUser = User::create([
            'uuid'         => (string) Str::uuid(),
            'name'         => 'Operator Desa Kauman',
            'email'        => 'operator_kauman@bojonegorokab.go.id',
            'password'     => bcrypt('password'),
            'id_kecamatan' => $this->kecamatan->id,
            'id_desa'      => $this->desa2->id,
            'status'       => true,
        ]);
        $this->otherDesaUser->assignRole($roleDesa);

        // 6. User Tanpa Permission
        $this->unauthorizedUser = User::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'User Polos',
            'email'    => 'polos@bojonegorokab.go.id',
            'password' => bcrypt('password'),
            'status'   => true,
        ]);
    }

    /**
     * Helper membuat dummy record jalan poros desa.
     */
    private function createRuas(array $attributes = []): JalanPorosDesa
    {
        $ruas = new JalanPorosDesa();
        $ruas->id = (string) Str::uuid();
        $ruas->kode_ruas = $attributes['kode_ruas'] ?? (int) (JalanPorosDesa::max('kode_ruas') + 1);
        $ruas->nama_ruas = $attributes['nama_ruas'] ?? 'Ruas Poros Sukorejo Utama';
        $ruas->desa = $attributes['desa'] ?? 'Desa Sukorejo';
        $ruas->kecamatan = $attributes['kecamatan'] ?? 'Kecamatan Bojonegoro';
        $ruas->id_desa = $attributes['id_desa'] ?? $this->desa1->id;
        $ruas->id_kecamatan = $attributes['id_kecamatan'] ?? $this->kecamatan->id;
        $ruas->panjang = $attributes['panjang'] ?? 1250.5;
        $ruas->lebar = $attributes['lebar'] ?? 4.0;
        $ruas->perkerasan = $attributes['perkerasan'] ?? 'Aspal';
        $ruas->kondisi = $attributes['kondisi'] ?? 'Baik';
        $ruas->status_awal = $attributes['status_awal'] ?? 'Poros Desa';
        $ruas->status_eksisting = $attributes['status_eksisting'] ?? 'Jalan Kabupaten';
        $ruas->sumber_data = $attributes['sumber_data'] ?? 'Dinas PU Bina Marga';
        $ruas->geom = DB::raw("ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"LineString\",\"coordinates\":[[111.8901,-7.1501],[111.8950,-7.1550]]}'), 4326))");
        $ruas->save();

        return $ruas;
    }

    // =========================================================================
    // 1. HAPPY PATH (Skenario Sukses)
    // =========================================================================

    public function test_admin_can_list_jalan_poros_desa_as_geojson_feature_collection(): void
    {
        $this->createRuas(['nama_ruas' => 'Ruas A']);
        $this->createRuas(['nama_ruas' => 'Ruas B']);

        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/admin/jalan-poros-desa');

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'id',
                        'properties' => [
                            'id',
                            'kode_ruas',
                            'nama_ruas',
                            'desa',
                            'kecamatan',
                            'kondisi',
                            'perkerasan',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('features'));
    }

    public function test_admin_can_list_jalan_poros_desa_as_table_and_options(): void
    {
        $this->createRuas([
            'nama_ruas'  => 'Ruas Cempaka',
            'kondisi'    => 'Sedang',
            'perkerasan' => 'Rigid Beton',
        ]);

        // 1. Format table (paginated)
        $responseTable = $this->actingAs($this->adminUser)->getJson('/api/v1/admin/jalan-poros-desa?format=table&per_page=10');

        $responseTable->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data',
                'total',
                'current_page',
                'last_page',
                'per_page',
            ])
            ->assertJsonPath('total', 1);

        // 2. Format options (filter master values)
        $responseOptions = $this->actingAs($this->adminUser)->getJson('/api/v1/admin/jalan-poros-desa?format=options');

        $responseOptions->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'kecamatan',
                'desa',
                'kondisi',
                'perkerasan',
            ]);

        $this->assertContains('Sedang', $responseOptions->json('kondisi'));
        $this->assertContains('Rigid Beton', $responseOptions->json('perkerasan'));
    }

    public function test_admin_can_create_new_ruas_jalan_with_valid_linestring(): void
    {
        $payload = [
            'kode_ruas'        => 101,
            'nama_ruas'        => 'Jalan Poros Desa Sukorejo Baru',
            'desa'             => 'Desa Sukorejo',
            'kecamatan'        => 'Kecamatan Bojonegoro',
            'id_desa'          => $this->desa1->id,
            'id_kecamatan'     => $this->kecamatan->id,
            'panjang'          => 850.75,
            'lebar'            => 4.5,
            'perkerasan'       => 'Rigid Beton',
            'kondisi'          => 'Baik',
            'status_awal'      => 'Jalan Lingkungan',
            'status_eksisting' => 'Jalan Poros Desa',
            'sumber_data'      => 'Bappeda Bojonegoro',
            'geometry'         => [
                'type'        => 'LineString',
                'coordinates' => [
                    [111.8901, -7.1501],
                    [111.8920, -7.1520],
                    [111.8950, -7.1550],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/jalan-poros-desa', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nama_ruas', 'Jalan Poros Desa Sukorejo Baru')
            ->assertJsonPath('data.kode_ruas', 101)
            ->assertJsonPath('data.perkerasan', 'Rigid Beton');

        $this->assertDatabaseHas('jalan_porosdesa', [
            'kode_ruas'  => 101,
            'nama_ruas'  => 'Jalan Poros Desa Sukorejo Baru',
            'perkerasan' => 'Rigid Beton',
            'kondisi'    => 'Baik',
        ]);
    }

    public function test_admin_can_show_single_ruas_jalan_detail(): void
    {
        $ruas = $this->createRuas(['nama_ruas' => 'Ruas Khusus Detail']);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $ruas->id)
            ->assertJsonPath('data.nama_ruas', 'Ruas Khusus Detail')
            ->assertJsonStructure([
                'ok',
                'data',
                'feature' => [
                    'type',
                    'id',
                    'properties',
                ],
            ]);
    }

    public function test_admin_can_update_ruas_jalan_attributes_and_geometry(): void
    {
        $ruas = $this->createRuas([
            'nama_ruas'  => 'Ruas Sebelum Diubah',
            'kondisi'    => 'Rusak Ringan',
            'perkerasan' => 'Makadam',
        ]);

        $updatePayload = [
            'nama_ruas'  => 'Ruas Sukorejo Telah Direhabilitasi',
            'kondisi'    => 'Baik',
            'perkerasan' => 'Aspal',
            'panjang'    => 1300.0,
            'geometry'   => [
                'type'        => 'LineString',
                'coordinates' => [
                    [111.8901, -7.1501],
                    [111.8960, -7.1560],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nama_ruas', 'Ruas Sukorejo Telah Direhabilitasi')
            ->assertJsonPath('data.kondisi', 'Baik')
            ->assertJsonPath('data.perkerasan', 'Aspal');

        $this->assertDatabaseHas('jalan_porosdesa', [
            'id'         => $ruas->id,
            'nama_ruas'  => 'Ruas Sukorejo Telah Direhabilitasi',
            'kondisi'    => 'Baik',
            'perkerasan' => 'Aspal',
        ]);
    }

    public function test_admin_can_split_ruas_jalan_into_two_segments(): void
    {
        $ruas = $this->createRuas([
            'kode_ruas' => 50,
            'nama_ruas' => 'Ruas Panjang Bojonegoro',
        ]);

        $splitPayload = [
            'parts' => [
                [
                    'nama_ruas' => 'Ruas Bagian 1 (KM 0 - KM 1)',
                    'geometry'  => [
                        'type'        => 'LineString',
                        'coordinates' => [
                            [111.8901, -7.1501],
                            [111.8925, -7.1525],
                        ],
                    ],
                ],
                [
                    'kode_ruas' => 51,
                    'nama_ruas' => 'Ruas Bagian 2 (KM 1 - KM 2)',
                    'geometry'  => [
                        'type'        => 'LineString',
                        'coordinates' => [
                            [111.8925, -7.1525],
                            [111.8950, -7.1550],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}/split", $splitPayload);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.part1.properties.nama_ruas', 'Ruas Bagian 1 (KM 0 - KM 1)')
            ->assertJsonPath('data.part2.properties.nama_ruas', 'Ruas Bagian 2 (KM 1 - KM 2)');

        // Segmen lama terupdate
        $this->assertDatabaseHas('jalan_porosdesa', [
            'id'        => $ruas->id,
            'nama_ruas' => 'Ruas Bagian 1 (KM 0 - KM 1)',
        ]);

        // Segmen baru terbentuk
        $this->assertDatabaseHas('jalan_porosdesa', [
            'kode_ruas' => 51,
            'nama_ruas' => 'Ruas Bagian 2 (KM 1 - KM 2)',
        ]);
    }

    public function test_admin_can_delete_ruas_jalan(): void
    {
        $ruas = $this->createRuas(['nama_ruas' => 'Ruas Yang Akan Dihapus']);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}");

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('jalan_porosdesa', [
            'id' => $ruas->id,
        ]);
    }

    // =========================================================================
    // 2. VALIDATION & ERROR HANDLING (Negative Path)
    // =========================================================================

    public function test_create_fails_validation_when_nama_ruas_is_missing(): void
    {
        $payload = [
            'geometry' => [
                'type'        => 'LineString',
                'coordinates' => [[111.8901, -7.1501], [111.892, -7.152]],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/jalan-poros-desa', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        $errorFields = collect($response->json('errors'))->pluck('name')->all();
        $this->assertContains('nama_ruas', $errorFields);

        $this->assertDatabaseCount('jalan_porosdesa', 0);
    }

    public function test_create_fails_validation_when_geometry_type_is_invalid(): void
    {
        $payload = [
            'nama_ruas' => 'Jalan Rusak Geometri Polygon',
            'geometry'  => [
                'type'        => 'Polygon', // Geometri harus LineString / MultiLineString
                'coordinates' => [
                    [[111.8901, -7.1501], [111.892, -7.152], [111.8901, -7.1501]],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/jalan-poros-desa', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('jalan_porosdesa', [
            'nama_ruas' => 'Jalan Rusak Geometri Polygon',
        ]);
    }

    public function test_create_fails_validation_when_kode_ruas_is_duplicate(): void
    {
        $this->createRuas(['kode_ruas' => 99]);

        $duplicatePayload = [
            'kode_ruas' => 99,
            'nama_ruas' => 'Ruas Baru Dengan Kode Duplikat',
            'geometry'  => [
                'type'        => 'LineString',
                'coordinates' => [[111.8901, -7.1501], [111.892, -7.152]],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/jalan-poros-desa', $duplicatePayload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('jalan_porosdesa', [
            'nama_ruas' => 'Ruas Baru Dengan Kode Duplikat',
        ]);
    }

    public function test_show_returns_404_when_ruas_jalan_not_found(): void
    {
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/jalan-poros-desa/{$randomUuid}");

        $response->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    // =========================================================================
    // 3. AUTENTIKASI & OTORISASI RBAC
    // =========================================================================

    public function test_guest_cannot_access_jalan_poros_desa(): void
    {
        $response = $this->getJson('/api/v1/admin/jalan-poros-desa');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_or_modify_jalan_poros_desa(): void
    {
        $ruas = $this->createRuas();

        // 1. Tidak punya hak view (403)
        $resList = $this->actingAs($this->unauthorizedUser)->getJson('/api/v1/admin/jalan-poros-desa');
        $resList->assertStatus(403);

        // 2. Tidak punya hak create (403)
        $resCreate = $this->actingAs($this->unauthorizedUser)->postJson('/api/v1/admin/jalan-poros-desa', [
            'nama_ruas' => 'Ilegal Road',
            'geometry'  => ['type' => 'LineString', 'coordinates' => [[111.89, -7.15], [111.90, -7.16]]],
        ]);
        $resCreate->assertStatus(403);

        // 3. Tidak punya hak update (403)
        $resUpdate = $this->actingAs($this->unauthorizedUser)->putJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}", [
            'nama_ruas' => 'Updated Ilegal',
        ]);
        $resUpdate->assertStatus(403);

        // 4. Tidak punya hak delete (403)
        $resDelete = $this->actingAs($this->unauthorizedUser)->deleteJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}");
        $resDelete->assertStatus(403);
    }

    public function test_desa_user_can_only_access_and_update_their_own_village_ruas(): void
    {
        // Ruas milik desa Sukorejo
        $ruasSukorejo = $this->createRuas([
            'nama_ruas' => 'Ruas Sukorejo Mandiri',
            'id_desa'   => $this->desa1->id,
        ]);

        // Ruas milik desa Kauman
        $ruasKauman = $this->createRuas([
            'nama_ruas' => 'Ruas Kauman Mandiri',
            'id_desa'   => $this->desa2->id,
        ]);

        // Operator Sukorejo berhasil update ruas desanya sendiri
        $responseSuccess = $this->actingAs($this->desaUser)
            ->putJson("/api/v1/admin/jalan-poros-desa/{$ruasSukorejo->id}", [
                'nama_ruas' => 'Ruas Sukorejo Terupdate',
            ]);
        $responseSuccess->assertOk();

        // Operator Sukorejo DITOLAK saat mencoba update ruas desa Kauman (403 Policy)
        $responseForbidden = $this->actingAs($this->desaUser)
            ->putJson("/api/v1/admin/jalan-poros-desa/{$ruasKauman->id}", [
                'nama_ruas' => 'Coba Ubah Ruas Desa Lain',
            ]);
        $responseForbidden->assertStatus(403);
    }

    public function test_desa_user_cannot_delete_ruas_jalan(): void
    {
        // Kebijakan proteksi: Operator desa tidak boleh menghapus data ruas jalan
        $ruas = $this->createRuas(['id_desa' => $this->desa1->id]);

        $response = $this->actingAs($this->desaUser)
            ->deleteJson("/api/v1/admin/jalan-poros-desa/{$ruas->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('jalan_porosdesa', [
            'id' => $ruas->id,
        ]);
    }

    // =========================================================================
    // 4. BOUNDARY & EDGE CASES
    // =========================================================================

    public function test_auto_generates_next_kode_ruas_when_kode_ruas_is_empty(): void
    {
        $this->createRuas(['kode_ruas' => 15]);

        $payload = [
            'nama_ruas' => 'Ruas Tanpa Kode Input',
            'geometry'  => [
                'type'        => 'LineString',
                'coordinates' => [[111.8901, -7.1501], [111.892, -7.152]],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/jalan-poros-desa', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode_ruas', 16);

        $this->assertDatabaseHas('jalan_porosdesa', [
            'kode_ruas' => 16,
            'nama_ruas' => 'Ruas Tanpa Kode Input',
        ]);
    }

    public function test_filter_by_kondisi_perkerasan_and_keyword_search(): void
    {
        $this->createRuas([
            'nama_ruas'  => 'Jalan Gajah Mada Poros',
            'kondisi'    => 'Baik',
            'perkerasan' => 'Aspal',
        ]);

        $this->createRuas([
            'nama_ruas'  => 'Jalan Hayam Wuruk Poros',
            'kondisi'    => 'Rusak Berat',
            'perkerasan' => 'Tanah',
        ]);

        // Filter search keyword
        $resSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/jalan-poros-desa?search=Gajah+Mada');
        $resSearch->assertOk();
        $this->assertCount(1, $resSearch->json('features'));

        // Filter kondisi
        $resKondisi = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/jalan-poros-desa?kondisi=Rusak+Berat');
        $resKondisi->assertOk();
        $this->assertCount(1, $resKondisi->json('features'));
        $this->assertEquals('Jalan Hayam Wuruk Poros', $resKondisi->json('features.0.properties.nama_ruas'));
    }
}

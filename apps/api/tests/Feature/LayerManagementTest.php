<?php

namespace Tests\Feature;

use App\Models\Layer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LayerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $viewer;
    protected User $unauthorized;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\Illuminate\Support\Facades\Schema::hasTable('bataswilayah_kecamatan')) {
            \Illuminate\Support\Facades\Schema::create('bataswilayah_kecamatan', function ($table) {
                $table->increments('id');
                $table->string('nama_kecamatan')->nullable();
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('bataswilayah_desa')) {
            \Illuminate\Support\Facades\Schema::create('bataswilayah_desa', function ($table) {
                $table->bigIncrements('id');
                $table->string('nama_desa')->nullable();
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('layers')) {
            \Illuminate\Support\Facades\Schema::create('layers', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('protocol');
                $table->string('url', 500);
                $table->string('layer_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('default_visible')->default(false);
                $table->double('opacity')->default(1.0);
                $table->integer('order')->default(0);
                $table->string('attribution')->nullable();
                $table->text('description')->nullable();
                $table->string('source_type', 50);
                $table->json('style_config')->nullable();
                $table->boolean('is_synced')->default(false);
                $table->timestamps();
            });
        }

        $permissions = [
            'layers-view',
            'layers-create',
            'layers-update',
            'layers-delete',
            'layers-manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->admin = User::create([
            'name' => 'Layer Admin',
            'email' => 'layer_admin@example.com',
            'password' => bcrypt('password'),
            'status' => true,
        ]);
        $this->admin->givePermissionTo($permissions);

        $this->viewer = User::create([
            'name' => 'Layer Viewer',
            'email' => 'layer_viewer@example.com',
            'password' => bcrypt('password'),
            'status' => true,
        ]);
        $this->viewer->givePermissionTo(['layers-view']);

        $this->unauthorized = User::create([
            'name' => 'Plain User',
            'email' => 'plain_user@example.com',
            'password' => bcrypt('password'),
            'status' => true,
        ]);
    }

    public function test_can_list_layers_with_permissions(): void
    {
        Layer::create([
            'name' => 'Batas Desa',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'layer_name' => 'palapa:batas_desa',
            'source_type' => 'geoserver',
            'is_active' => true,
            'default_visible' => true,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->viewer)->getJson('/api/v1/admin/layers');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('summary.default_visible', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthorized_user_cannot_access_admin_layers(): void
    {
        $response = $this->actingAs($this->unauthorized)->getJson('/api/v1/admin/layers');
        $response->assertStatus(403);
    }

    public function test_can_create_layer_and_dispatches_audit_log(): void
    {
        $payload = [
            'name' => 'Jaringan Irigasi',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'layer_name' => 'workspace:irigasi_layer',
            'is_active' => true,
            'default_visible' => false,
            'opacity' => 0.85,
            'source_type' => 'geoserver',
            'attribution' => 'Dinas PU SDA',
            'description' => 'Data layer jaringan irigasi primer dan sekunder.',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/layers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Jaringan Irigasi')
            ->assertJsonPath('data.opacity', 0.85);

        $this->assertDatabaseHas('layers', [
            'name' => 'Jaringan Irigasi',
            'layer_name' => 'workspace:irigasi_layer',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'layers',
            'event' => 'created',
        ]);
    }

    public function test_validation_fails_for_invalid_layer_payload(): void
    {
        $payload = [
            'name' => '',
            'protocol' => '',
            'url' => '',
            'opacity' => 2.5, // max is 1
            'source_type' => '',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/layers', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        $errorFields = collect($response->json('errors'))->pluck('name')->all();
        $this->assertContains('name', $errorFields);
        $this->assertContains('protocol', $errorFields);
        $this->assertContains('url', $errorFields);
        $this->assertContains('opacity', $errorFields);
        $this->assertContains('source_type', $errorFields);
    }

    public function test_can_update_layer(): void
    {
        $layer = Layer::create([
            'name' => 'Layer Awal',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'source_type' => 'geoserver',
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/layers/{$layer->id}", [
            'name' => 'Layer Diperbarui',
            'opacity' => 0.6,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Layer Diperbarui')
            ->assertJsonPath('data.opacity', 0.6);

        $this->assertDatabaseHas('layers', [
            'id' => $layer->id,
            'name' => 'Layer Diperbarui',
        ]);
    }

    public function test_can_toggle_active_status(): void
    {
        $layer = Layer::create([
            'name' => 'Toggle Test Layer',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'source_type' => 'geoserver',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/layers/{$layer->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('layers', [
            'id' => $layer->id,
            'is_active' => false,
        ]);
    }

    public function test_can_reorder_layers(): void
    {
        $layer1 = Layer::create([
            'name' => 'Layer 1',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'source_type' => 'geoserver',
            'order' => 1,
        ]);

        $layer2 = Layer::create([
            'name' => 'Layer 2',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'source_type' => 'geoserver',
            'order' => 2,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/layers/reorder', [
            'items' => [
                ['id' => $layer1->id, 'order' => 10],
                ['id' => $layer2->id, 'order' => 5],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true);

        $this->assertEquals(10, $layer1->fresh()->order);
        $this->assertEquals(5, $layer2->fresh()->order);
    }

    public function test_can_delete_layer(): void
    {
        $layer = Layer::create([
            'name' => 'Layer To Delete',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/geoserver/wms',
            'source_type' => 'geoserver',
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/layers/{$layer->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('layers', [
            'id' => $layer->id,
        ]);
    }

    public function test_active_layers_feed_returns_only_active_ordered_layers(): void
    {
        Layer::create([
            'name' => 'Layer Nonaktif',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/wms',
            'source_type' => 'geoserver',
            'is_active' => false,
            'order' => 1,
        ]);

        Layer::create([
            'name' => 'Layer Aktif B',
            'protocol' => 'xyz',
            'url' => 'https://tile.example.com/{z}/{x}/{y}.png',
            'source_type' => 'xyz_tiles',
            'is_active' => true,
            'order' => 2,
        ]);

        Layer::create([
            'name' => 'Layer Aktif A',
            'protocol' => 'wms',
            'url' => 'https://geoportal.example.com/wms',
            'source_type' => 'geoserver',
            'is_active' => true,
            'order' => 1,
        ]);

        $response = $this->getJson('/api/v1/layers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Layer Aktif A')
            ->assertJsonPath('data.1.name', 'Layer Aktif B');
    }

    public function test_can_create_and_update_mvt_layer_with_dynamic_style_config(): void
    {
        $stylePayload = [
            'geom_type' => 'line',
            'color' => '#10b981',
            'width' => 3.5,
            'dash' => 'solid',
            'casing' => true,
            'casing_color' => 'rgba(16, 185, 129, 0.25)',
            'casing_width' => 6,
            'rules' => [
                ['id' => 'r1', 'field' => 'kondisi', 'operator' => 'contains', 'value' => 'rusak', 'color' => '#ef4444', 'width' => 4],
            ],
        ];

        $payload = [
            'name' => 'Ruas Jalan MVT',
            'protocol' => 'mvt',
            'url' => '/martin/ruas_jalan/{z}/{x}/{y}',
            'layer_name' => 'ruas_jalan',
            'source_type' => 'internal',
            'style_config' => $stylePayload,
        ];

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/admin/layers', $payload);
        $createResponse->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.style_config.color', '#10b981')
            ->assertJsonPath('data.style_config.rules.0.value', 'rusak');

        $layerId = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($this->admin)->putJson("/api/v1/admin/layers/{$layerId}", [
            'style_config' => array_merge($stylePayload, ['color' => '#3b82f6', 'width' => 5]),
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.style_config.color', '#3b82f6')
            ->assertJsonPath('data.style_config.width', 5);

        $this->assertDatabaseHas('layers', [
            'id' => $layerId,
            'name' => 'Ruas Jalan MVT',
        ]);
    }

    public function test_feature_geometry_resolves_layer_uuid_legacy_alias_and_namobj_fallback(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('jalan_porosdesa')) {
            \Illuminate\Support\Facades\Schema::create('jalan_porosdesa', function ($table) {
                $table->increments('id');
                $table->string('nama_ruas')->nullable();
                $table->text('geom')->nullable();
            });
        }

        \Illuminate\Support\Facades\DB::table('jalan_porosdesa')->insert([
            'id' => 7,
            'nama_ruas' => 'Ruas Poros Desa A',
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [[115.1, -2.5], [115.2, -2.6]],
            ]),
        ]);

        $layer = Layer::create([
            'name' => 'Jalan Poros Desa',
            'protocol' => 'MVT',
            'url' => '/martin/jalan_porosdesa/{z}/{x}/{y}',
            'layer_name' => 'jalan_porosdesa',
            'is_active' => true,
            'default_visible' => true,
            'source_type' => 'internal',
            'order' => 3,
        ]);

        $this->getJson("/api/v1/layers/{$layer->id}/feature-geometry?feature_id=7")
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('table', 'jalan_porosdesa')
            ->assertJsonPath('geometry.type', 'LineString');

        // Stale fallback UUID + non-numeric feature_id should not fail; falls back to nama_ruas via layer_name hint
        $this->getJson('/api/v1/layers/01a1589c-8bf1-7294-9cf7-41ffda7e71f6/feature-geometry?feature_id=invalid-id&namobj=Poros%20Desa%20A&layer_name=jalan_porosdesa')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('table', 'jalan_porosdesa')
            ->assertJsonPath('geometry.type', 'LineString');

        // Stale fallback UUID alone resolves via legacy table alias map
        $this->getJson('/api/v1/layers/01a1589c-8bf1-7294-9cf7-41ffda7e71f6/feature-geometry?feature_id=7')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('table', 'jalan_porosdesa');

        // Unknown layer and unknown table still return 404
        $this->getJson('/api/v1/layers/00000000-0000-0000-0000-000000000000/feature-geometry?feature_id=7&layer_name=nonexistent_table')
            ->assertStatus(404)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Layer or underlying table not found');
    }

    public function test_feature_geometry_supports_kode_ruas_and_disambiguates_by_kecamatan_desa(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('jalan_porosdesa')) {
            \Illuminate\Support\Facades\Schema::create('jalan_porosdesa', function ($table) {
                $table->increments('id');
                $table->integer('kode_ruas')->nullable();
                $table->string('nama_ruas')->nullable();
                $table->string('desa')->nullable();
                $table->string('kecamatan')->nullable();
                $table->text('geom')->nullable();
            });
        } else {
            \Illuminate\Support\Facades\Schema::table('jalan_porosdesa', function ($table) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('jalan_porosdesa', 'kode_ruas')) {
                    $table->integer('kode_ruas')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('jalan_porosdesa', 'desa')) {
                    $table->string('desa')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('jalan_porosdesa', 'kecamatan')) {
                    $table->string('kecamatan')->nullable();
                }
            });
        }

        \Illuminate\Support\Facades\DB::table('jalan_porosdesa')->insert([
            [
                'id' => 101,
                'kode_ruas' => 4401,
                'nama_ruas' => 'Jl. Poros Desa',
                'desa' => 'Ngunut',
                'kecamatan' => 'Dander',
                'geom' => json_encode([
                    'type' => 'LineString',
                    'coordinates' => [[111.85, -7.23], [111.86, -7.24]],
                ]),
            ],
            [
                'id' => 102,
                'kode_ruas' => 4402,
                'nama_ruas' => 'Jl. Poros Desa',
                'desa' => 'Sumberejo',
                'kecamatan' => 'Trucuk',
                'geom' => json_encode([
                    'type' => 'LineString',
                    'coordinates' => [[111.87, -7.13], [111.88, -7.14]],
                ]),
            ],
        ]);

        // Lookup scoped by kecamatan & desa picks Trucuk's road instead of Dander's
        $this->getJson('/api/v1/layers/jalan_porosdesa/feature-geometry?namobj=Jl.%20Poros%20Desa&desa=Sumberejo&kecamatan=Trucuk')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('geometry.coordinates.0.0', 111.87);

        // Lookup by kode_ruas query parameter resolves matching road directly
        $this->getJson('/api/v1/layers/jalan_porosdesa/feature-geometry?kode_ruas=4401&desa=Ngunut&kecamatan=Dander')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('geometry.coordinates.0.0', 111.85);
    }

    public function test_can_fetch_full_stack_and_batch_configure_active_layers(): void
    {
        $top = Layer::create([
            'name' => 'Segmen Jalan',
            'protocol' => 'mvt',
            'url' => '/martin/infrastruktur_segmen/{z}/{x}/{y}',
            'source_type' => 'internal',
            'is_active' => true,
            'default_visible' => true,
            'opacity' => 1.0,
            'order' => 5,
        ]);

        $inactive = Layer::create([
            'name' => 'Layer Draft Eksternal',
            'protocol' => 'wms',
            'url' => 'https://example.com/wms',
            'source_type' => 'external',
            'is_active' => false,
            'default_visible' => true,
            'opacity' => 0.8,
            'order' => 0,
        ]);

        $base = Layer::create([
            'name' => 'Batas Kabupaten',
            'protocol' => 'mvt',
            'url' => '/martin/bataswilayah_kabupaten/{z}/{x}/{y}',
            'source_type' => 'internal',
            'is_active' => true,
            'default_visible' => true,
            'opacity' => 0.7,
            'order' => 1,
        ]);

        $stackResponse = $this->actingAs($this->viewer)->getJson('/api/v1/admin/layers/stack');
        $stackResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('summary.inactive', 1)
            ->assertJsonPath('data.0.id', $top->id)
            ->assertJsonPath('data.1.id', $base->id)
            ->assertJsonPath('data.2.id', $inactive->id);

        // Unauthorized viewer cannot mutate stack configuration
        $this->actingAs($this->viewer)->postJson('/api/v1/admin/layers/reorder', [
            'items' => [
                ['id' => $base->id, 'order' => 2, 'is_active' => true],
            ],
        ])->assertStatus(403);

        // Admin batch updates active status, order, default_visible, and opacity atomically
        $saveResponse = $this->actingAs($this->admin)->postJson('/api/v1/admin/layers/reorder', [
            'items' => [
                ['id' => $inactive->id, 'order' => 2, 'is_active' => true, 'default_visible' => true, 'opacity' => 0.65],
                ['id' => $base->id, 'order' => 1, 'is_active' => true, 'default_visible' => false, 'opacity' => 0.9],
                ['id' => $top->id, 'order' => 0, 'is_active' => false, 'default_visible' => true, 'opacity' => 1.0],
            ],
        ]);

        $saveResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('updated_count', 3);

        $this->assertTrue($inactive->fresh()->is_active);
        $this->assertTrue($inactive->fresh()->default_visible);
        $this->assertEquals(0.65, $inactive->fresh()->opacity);
        $this->assertEquals(2, $inactive->fresh()->order);

        $this->assertFalse($base->fresh()->default_visible);
        $this->assertEquals(0.9, $base->fresh()->opacity);
        $this->assertEquals(1, $base->fresh()->order);

        // Deactivated layer forces default_visible to false
        $this->assertFalse($top->fresh()->is_active);
        $this->assertFalse($top->fresh()->default_visible);
        $this->assertEquals(0, $top->fresh()->order);
    }
}

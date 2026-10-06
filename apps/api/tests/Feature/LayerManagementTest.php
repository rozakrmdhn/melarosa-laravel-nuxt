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
}

<?php

namespace Tests\Feature;

use App\Models\Basemap;
use App\Models\User;
use Database\Seeders\BasemapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BasemapManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['layers-view', 'layers-manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['layers-view', 'layers-manage']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);

        $this->regularUser = User::factory()->create();
    }

    public function test_public_endpoint_returns_active_seeded_basemaps(): void
    {
        $this->seed(BasemapSeeder::class);

        $res = $this->getJson('/api/v1/basemaps');
        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('default_kode', 'esri');

        $codes = collect($res->json('data'))->pluck('kode')->all();
        $this->assertContains('esri', $codes);
        $this->assertContains('rbi', $codes);
        $this->assertContains('osm', $codes);
        $this->assertContains('hybrid', $codes);
    }

    public function test_admin_can_crud_set_default_and_non_admin_is_blocked(): void
    {
        $this->seed(BasemapSeeder::class);

        // User tanpa permission ditolak
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/admin/basemaps', [
                'kode'          => 'ilegal',
                'nama'          => 'Ilegal',
                'label_singkat' => 'Ilg',
                'url_template'  => 'https://example.com/{z}/{x}/{y}.png',
            ])
            ->assertStatus(403);

        // Admin menambah basemap kustom
        $createRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/basemaps', [
                'kode'                => 'google_roadmap',
                'nama'                => 'Google Streets',
                'label_singkat'       => 'Streets',
                'provider'            => 'Google',
                'tagline'             => 'Peta jalan Google',
                'tipe'                => 'raster',
                'url_template'        => 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
                'preview_style'       => 'osm',
                'supports_openlayers' => true,
                'supports_maplibre'   => true,
                'is_active'           => true,
            ]);
        $createRes->assertStatus(201);
        $newId = $createRes->json('data.id');

        // Set default -> hanya satu basemap yang is_default = true
        $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/admin/basemaps/{$newId}/set-default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertEquals(1, Basemap::where('is_default', true)->count());
        $this->getJson('/api/v1/basemaps')
            ->assertOk()
            ->assertJsonPath('default_kode', 'google_roadmap');

        // Default basemap tidak bisa dihapus langsung
        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/basemaps/{$newId}")
            ->assertStatus(422);

        // Kembalikan default ke esri lalu hapus google_roadmap
        $esri = Basemap::where('kode', 'esri')->firstOrFail();
        $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/admin/basemaps/{$esri->id}/set-default")
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/basemaps/{$newId}")
            ->assertOk();

        $this->assertDatabaseMissing('basemaps', ['id' => $newId]);
    }
}

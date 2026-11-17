<?php

namespace Tests\Feature;

use App\Models\GeoportalSyncLog;
use App\Models\Layer;
use App\Models\User;
use App\Services\MartinTileService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MartinSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $viewer;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(MartinTileService::STATUS_CACHE_KEY);

        $permissions = [
            'layers-view',
            'layers-create',
            'layers-update',
            'layers-manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo($permissions);

        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo(['layers-view']);

        $this->regularUser = User::factory()->create();
    }

    private function mockCatalogJson(array $extraTiles = []): string
    {
        $tiles = array_merge([
            'bataswilayah_desa' => [
                'content_type' => 'application/x-protobuf',
                'description' => 'public.bataswilayah_desa.geom',
            ],
            'jalan_porosdesa' => [
                'content_type' => 'application/x-protobuf',
                'description' => 'public.jalan_porosdesa.geom',
            ],
            'bataswilayah_kecamatan_old' => [
                'content_type' => 'application/x-protobuf',
                'description' => 'public.bataswilayah_kecamatan_old.geom',
            ],
        ], $extraTiles);

        return json_encode([
            'tiles' => $tiles,
            'sprites' => new \stdClass(),
            'fonts' => new \stdClass(),
            'styles' => new \stdClass(),
        ], JSON_THROW_ON_ERROR);
    }

    private function mockTileJson(string $sourceId, array $fields = []): string
    {
        return json_encode([
            'tilejson' => '3.0.0',
            'tiles' => ["http://localhost:9090/{$sourceId}/{z}/{x}/{y}"],
            'vector_layers' => [
                [
                    'id' => $sourceId,
                    'fields' => $fields,
                ],
            ],
            'bounds' => [111.4, -7.45, 112.16, -6.98],
            'description' => "public.{$sourceId}.geom",
            'name' => $sourceId,
        ], JSON_THROW_ON_ERROR);
    }

    public function test_check_connection_returns_online_when_martin_catalog_is_reachable(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->mockCatalogJson()),
        ]);

        $this->app->instance(
            MartinTileService::class,
            new MartinTileService(new Client(['handler' => HandlerStack::create($mockHandler)]), 'http://localhost:9090')
        );

        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/layers/martin/status?refresh=1');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'online')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.remote_sources', 3);
    }

    public function test_check_connection_returns_fallback_and_offline_appropriately(): void
    {
        $offlineHandler = new MockHandler([
            new Response(502, [], 'Bad Gateway'),
        ]);
        $this->app->instance(
            MartinTileService::class,
            new MartinTileService(new Client(['handler' => HandlerStack::create($offlineHandler)]), 'http://localhost:9090')
        );

        $offlineResponse = $this->getJson('/api/v1/layers/martin/status?refresh=1');
        $offlineResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'offline')
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.total_local_layers', 0);

        Layer::create([
            'name' => 'Layer Internal Lokal',
            'protocol' => 'mvt',
            'url' => '/martin/jalan_porosdesa/{z}/{x}/{y}',
            'layer_name' => 'jalan_porosdesa',
            'source_type' => 'internal',
            'is_active' => true,
            'order' => 1,
        ]);

        $fallbackHandler = new MockHandler([
            new Response(500, [], 'Server Error'),
        ]);
        $this->app->instance(
            MartinTileService::class,
            new MartinTileService(new Client(['handler' => HandlerStack::create($fallbackHandler)]), 'http://localhost:9090')
        );

        $fallbackResponse = $this->getJson('/api/v1/layers/martin/status?refresh=1');
        $fallbackResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'fallback')
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.total_local_layers', 1);
    }

    public function test_martin_sync_creates_layers_and_is_idempotent_preserving_customizations(): void
    {
        // Response order matches ksort(): bataswilayah_desa, bataswilayah_kecamatan_old, jalan_porosdesa
        $firstMockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->mockCatalogJson()),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('bataswilayah_desa', ['nama_desa' => 'varchar'])),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('bataswilayah_kecamatan_old', ['namobj' => 'varchar'])),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('jalan_porosdesa', ['nama_ruas' => 'varchar', 'kondisi' => 'varchar'])),
        ]);

        $firstService = new MartinTileService(
            new Client(['handler' => HandlerStack::create($firstMockHandler)]),
            'http://localhost:9090'
        );

        $result = $firstService->sync('manual_admin', $this->admin->id);

        $this->assertTrue($result['ok']);
        $this->assertEquals(3, $result['summary']['found']);
        $this->assertEquals(3, $result['summary']['inserted']);
        $this->assertEquals(0, $result['summary']['updated']);

        $jalanLayer = Layer::where('layer_name', 'jalan_porosdesa')->first();
        $this->assertNotNull($jalanLayer);
        $this->assertEquals('internal', $jalanLayer->source_type);
        $this->assertEquals('mvt', $jalanLayer->protocol);
        $this->assertEquals('/martin/jalan_porosdesa/{z}/{x}/{y}', $jalanLayer->url);
        $this->assertTrue($jalanLayer->is_active);
        $this->assertTrue($jalanLayer->is_synced);
        $this->assertEquals(MartinTileService::DEFAULT_SOURCE_ATTRIBUTION, $jalanLayer->opd_name);
        $this->assertEquals(MartinTileService::DEFAULT_SOURCE_ATTRIBUTION, $jalanLayer->attribution);
        $this->assertEquals('line', $jalanLayer->style_config['geom_type'] ?? null);
        $this->assertEquals('nama_ruas', $jalanLayer->style_config['label']['field'] ?? null);

        $oldLayer = Layer::where('layer_name', 'bataswilayah_kecamatan_old')->first();
        $this->assertNotNull($oldLayer);
        $this->assertFalse($oldLayer->is_active);

        // Simulate legacy default attribution being upgraded to Pemerintah Kabupaten Bojonegoro while preserving custom attribution
        $oldLayer->update([
            'opd_name' => 'Dinas PU Bina Marga & Penataan Ruang Bojonegoro',
            'attribution' => 'Dinas PU Bina Marga & Penataan Ruang Bojonegoro',
        ]);

        // Admin customizes label, opd_name, default_visible, order, and style_config
        $customStyle = ['geom_type' => 'line', 'color' => '#ff0000', 'width' => 5];
        $jalanLayer->update([
            'name' => 'Ruas Jalan Poros Kustom Admin',
            'opd_name' => 'Bappeda Bojonegoro',
            'default_visible' => true,
            'order' => 99,
            'style_config' => $customStyle,
        ]);

        $secondMockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->mockCatalogJson()),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('bataswilayah_desa', ['nama_desa' => 'varchar'])),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('bataswilayah_kecamatan_old', ['namobj' => 'varchar'])),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('jalan_porosdesa', ['nama_ruas' => 'varchar', 'kondisi' => 'varchar'])),
        ]);

        $secondService = new MartinTileService(
            new Client(['handler' => HandlerStack::create($secondMockHandler)]),
            'http://localhost:9090'
        );

        $secondResult = $secondService->sync('manual_admin', $this->admin->id);

        $this->assertTrue($secondResult['ok']);
        $this->assertEquals(0, $secondResult['summary']['inserted']);
        $this->assertEquals(3, $secondResult['summary']['updated']);
        $this->assertEquals(3, Layer::internal()->count());

        $jalanLayer->refresh();
        $this->assertEquals('Ruas Jalan Poros Kustom Admin', $jalanLayer->name);
        $this->assertEquals('Bappeda Bojonegoro', $jalanLayer->opd_name);
        $this->assertTrue($jalanLayer->default_visible);
        $this->assertEquals(99, $jalanLayer->order);
        $this->assertEquals($customStyle, $jalanLayer->style_config);

        $oldLayer->refresh();
        $this->assertEquals(MartinTileService::DEFAULT_SOURCE_ATTRIBUTION, $oldLayer->opd_name);
        $this->assertEquals(MartinTileService::DEFAULT_SOURCE_ATTRIBUTION, $oldLayer->attribution);

        $log = GeoportalSyncLog::where('id', $secondResult['log_id'])->first();
        $this->assertNotNull($log);
        $this->assertEquals('martin_manual_admin', $log->triggered_by);
        $this->assertEquals('success', $log->status);
    }

    public function test_admin_can_fetch_catalog_and_trigger_selective_sync_via_http(): void
    {
        Layer::create([
            'name' => 'Batas Desa Sudah Ada',
            'protocol' => 'mvt',
            'url' => '/martin/bataswilayah_desa/{z}/{x}/{y}',
            'layer_name' => 'bataswilayah_desa',
            'source_type' => 'internal',
            'is_active' => true,
            'order' => 1,
        ]);

        $catalogMock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->mockCatalogJson()),
        ]);
        $this->app->instance(
            MartinTileService::class,
            new MartinTileService(new Client(['handler' => HandlerStack::create($catalogMock)]), 'http://localhost:9090')
        );

        $catalogResponse = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/layers/martin/catalog?with_tilejson=0');

        $catalogResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 3);

        $items = collect($catalogResponse->json('data'))->keyBy('source_id');
        $this->assertTrue($items['bataswilayah_desa']['synced']);
        $this->assertFalse($items['jalan_porosdesa']['synced']);

        // Selective sync of just jalan_porosdesa via POST endpoint
        $syncMock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->mockCatalogJson()),
        ]);
        $this->app->instance(
            MartinTileService::class,
            new MartinTileService(new Client(['handler' => HandlerStack::create($syncMock)]), 'http://localhost:9090')
        );

        $syncResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/layers/martin/sync', [
                'sources' => ['jalan_porosdesa'],
                'include_tilejson' => false,
            ]);

        $syncResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.found', 1)
            ->assertJsonPath('summary.inserted', 1)
            ->assertJsonPath('summary.updated', 0);

        $historyResponse = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/layers/martin/sync-history');

        $historyResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.triggered_by', 'martin_manual_admin')
            ->assertJsonPath('data.0.source_service', 'martin');
    }

    public function test_unauthorized_user_cannot_sync_martin(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/v1/admin/layers/martin/sync');

        $response->assertStatus(403);
    }

    public function test_martin_sync_command_runs_successfully(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->mockCatalogJson()),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('bataswilayah_desa')),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('bataswilayah_kecamatan_old')),
            new Response(200, ['Content-Type' => 'application/json'], $this->mockTileJson('jalan_porosdesa')),
        ]);

        $this->app->instance(
            MartinTileService::class,
            new MartinTileService(new Client(['handler' => HandlerStack::create($mockHandler)]), 'http://localhost:9090')
        );

        $this->artisan('martin:sync-catalog --trigger=cli')
            ->assertExitCode(0);

        $this->assertEquals(3, Layer::internal()->count());
        $this->assertEquals(1, GeoportalSyncLog::where('triggered_by', 'martin_cli')->where('status', 'success')->count());
    }
}


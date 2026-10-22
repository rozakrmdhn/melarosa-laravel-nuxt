<?php

namespace Tests\Feature;

use App\Models\GeoportalSyncLog;
use App\Models\Layer;
use App\Models\User;
use App\Services\Gis\GeoportalCswClientService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GeoportalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'layers-view',
            'layers-create',
            'layers-manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['layers-view', 'layers-create', 'layers-manage']);

        $this->regularUser = User::factory()->create();
    }

    public function test_csw_service_parses_and_syncs_mock_records(): void
    {
        $mockXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<csw:GetRecordsResponse xmlns:csw="http://www.opengis.net/cat/csw/2.0.2" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dct="http://purl.org/dc/terms/">
  <csw:SearchResults numberOfRecordsMatched="1" numberOfRecordsReturned="1" nextRecord="0">
    <csw:Record>
      <dc:identifier>SAMPLE_LAYER_01</dc:identifier>
      <dc:title>Peta Sampel Uji Coba</dc:title>
      <dc:type>dataset</dc:type>
      <dc:subject>Infrastruktur</dc:subject>
      <dct:references scheme="OGC:WMS">https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms</dct:references>
      <dct:abstract>Deskripsi peta sampel untuk pengujian integrasi.</dct:abstract>
      <dc:publisher>Dinas PU Bina Marga</dc:publisher>
    </csw:Record>
  </csw:SearchResults>
</csw:GetRecordsResponse>
XML;

        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $mockXml),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $service = new GeoportalCswClientService($client);
        $result = $service->sync('test_runner', $this->admin->id);

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['summary']['found']);
        $this->assertEquals(1, $result['summary']['inserted']);

        $layer = Layer::where('geoportal_id', 'SAMPLE_LAYER_01')->first();
        $this->assertNotNull($layer);
        $this->assertEquals('Peta Sampel Uji Coba', $layer->name);
        $this->assertEquals('Infrastruktur', $layer->category);
        $this->assertEquals('Dinas PU Bina Marga', $layer->opd_name);
        $this->assertEquals('palapa:SAMPLE_LAYER_01', $layer->layer_name);
        $this->assertEquals('geoportal', $layer->source_type);
        $this->assertTrue($layer->is_synced);
    }

    public function test_admin_can_access_sync_endpoint(): void
    {
        $mockXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<csw:GetRecordsResponse xmlns:csw="http://www.opengis.net/cat/csw/2.0.2" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dct="http://purl.org/dc/terms/">
  <csw:SearchResults numberOfRecordsMatched="0" numberOfRecordsReturned="0" nextRecord="0" />
</csw:GetRecordsResponse>
XML;

        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $mockXml),
        ]);
        $this->app->instance(
            GeoportalCswClientService::class,
            new GeoportalCswClientService(new Client(['handler' => HandlerStack::create($mockHandler)]))
        );

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/layers/geoportal/sync');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'message', 'summary']);
    }

    public function test_unauthorized_user_cannot_trigger_sync(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/v1/admin/layers/geoportal/sync');

        $response->assertStatus(403);
    }

    public function test_public_can_fetch_geoportal_layers_grouped(): void
    {
        Layer::create([
            'name' => 'Peta Batas Desa',
            'geoportal_id' => 'BATAS_DESA_01',
            'category' => 'Batas Wilayah',
            'opd_name' => 'Bappeda Bojonegoro',
            'protocol' => 'WMS',
            'url' => 'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms',
            'layer_name' => 'palapa:BATAS_DESA_01',
            'source_type' => 'geoportal',
            'is_active' => true,
            'order' => 1,
        ]);

        $response = $this->getJson('/api/v1/layers/geoportal');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.geoportal_id', 'BATAS_DESA_01');
    }

    public function test_public_can_get_layer_metadata(): void
    {
        $layer = Layer::create([
            'name' => 'Peta Tata Ruang',
            'geoportal_id' => 'TATA_RUANG_01',
            'category' => 'Perencanaan',
            'opd_name' => 'Bappeda Bojonegoro',
            'protocol' => 'WMS',
            'url' => 'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms',
            'layer_name' => 'palapa:TATA_RUANG_01',
            'description' => 'Abstrak tata ruang wilayah Bojonegoro',
            'source_type' => 'geoportal',
            'is_active' => true,
            'order' => 1,
        ]);

        $response = $this->getJson("/api/v1/layers/geoportal/{$layer->id}/metadata");

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Peta Tata Ruang')
            ->assertJsonPath('data.category', 'Perencanaan');
    }

    public function test_public_can_get_layer_attributes_endpoint(): void
    {
        $layer = Layer::create([
            'name' => 'Peta Batas Desa',
            'geoportal_id' => 'BATAS_DESA_02',
            'category' => 'Batas Wilayah',
            'opd_name' => 'Bappeda Bojonegoro',
            'protocol' => 'WMS',
            'url' => 'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms',
            'layer_name' => 'palapa:BATAS_DESA_02',
            'source_type' => 'geoportal',
            'is_active' => true,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/v1/layers/geoportal/{$layer->id}/attributes");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'wfs_url',
                    'type_name',
                    'attributes',
                    'sample_data',
                ],
            ]);
    }

    public function test_geoportal_status_returns_online_when_csw_is_reachable(): void
    {
        $mockXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<csw:GetRecordsResponse xmlns:csw="http://www.opengis.net/cat/csw/2.0.2">
  <csw:SearchResults numberOfRecordsMatched="15" numberOfRecordsReturned="0" nextRecord="1" />
</csw:GetRecordsResponse>
XML;

        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $mockXml),
        ]);
        $this->app->instance(
            GeoportalCswClientService::class,
            new GeoportalCswClientService(new Client(['handler' => HandlerStack::create($mockHandler)]))
        );

        $response = $this->getJson('/api/v1/layers/geoportal/status?refresh=1');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'online')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.remote_matched', 15);
    }

    public function test_geoportal_status_returns_fallback_when_csw_fails_but_local_layers_exist(): void
    {
        Layer::create([
            'name' => 'Peta Cadangan Geoportal',
            'geoportal_id' => 'BACKUP_01',
            'category' => 'Batas Wilayah',
            'protocol' => 'WMS',
            'url' => 'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms',
            'layer_name' => 'palapa:BACKUP_01',
            'source_type' => 'geoportal',
            'is_active' => true,
            'order' => 1,
        ]);

        $mockHandler = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);
        $this->app->instance(
            GeoportalCswClientService::class,
            new GeoportalCswClientService(new Client(['handler' => HandlerStack::create($mockHandler)]))
        );

        $response = $this->getJson('/api/v1/layers/geoportal/status?refresh=1');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'fallback')
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.total_local_layers', 1);
    }

    public function test_geoportal_status_returns_offline_when_csw_fails_and_no_local_layers(): void
    {
        $mockHandler = new MockHandler([
            new Response(502, [], 'Bad Gateway'),
        ]);
        $this->app->instance(
            GeoportalCswClientService::class,
            new GeoportalCswClientService(new Client(['handler' => HandlerStack::create($mockHandler)]))
        );

        $response = $this->getJson('/api/v1/layers/geoportal/status?refresh=1');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'offline')
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.total_local_layers', 0);
    }
}

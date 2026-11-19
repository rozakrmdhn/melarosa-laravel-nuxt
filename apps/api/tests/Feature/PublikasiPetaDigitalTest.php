<?php

namespace Tests\Feature;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\PublikasiPetaDigital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublikasiPetaDigitalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected BatasWilayahKecamatan $kecamatan;
    protected BatasWilayahDesa $desa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach ([
            'publikasi-peta-view',
            'publikasi-peta-create',
            'publikasi-peta-update',
            'publikasi-peta-delete',
            'publikasi-peta-manage',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions([
            'publikasi-peta-view',
            'publikasi-peta-create',
            'publikasi-peta-update',
            'publikasi-peta-delete',
            'publikasi-peta-manage',
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);

        $this->regularUser = User::factory()->create();

        $this->kecamatan = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522040],
            ['nama_kecamatan' => 'Dander']
        );

        $this->desa = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522040001],
            ['nama_desa' => 'Ngumpakdalem', 'id_kecamatan' => $this->kecamatan->id]
        );
    }

    public function test_admin_can_upload_valid_pdf_and_public_can_filter_and_download(): void
    {
        $validPdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $pdfFile = UploadedFile::fake()->createWithContent('peta-ngumpakdalem.pdf', $validPdfContent);

        $createRes = $this->actingAs($this->adminUser)->post('/api/v1/admin/publikasi-peta', [
            'judul' => 'Peta Jaringan Jalan Desa Ngumpakdalem',
            'nomor_dokumen' => '050/12/412.201/2026',
            'kategori' => 'Peta Jalan Poros Desa',
            'tingkat_wilayah' => 'desa',
            'id_kecamatan' => $this->kecamatan->id,
            'id_desa' => $this->desa->id,
            'tahun_terbit' => 2026,
            'skala_peta' => '1 : 5.000',
            'ringkasan' => 'Peta terbitan resmi untuk lampiran usulan pembangunan desa.',
            'sumber_tipe' => 'file_pdf',
            'is_published' => '1',
            'file_pdf' => $pdfFile,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nama_desa', 'Ngumpakdalem')
            ->assertJsonPath('data.nama_kecamatan', 'Dander')
            ->assertJsonPath('data.sumber_tipe', 'file_pdf')
            ->assertJsonPath('data.has_file', true);

        $id = $createRes->json('data.id');
        $slug = $createRes->json('data.slug');
        $this->assertArrayNotHasKey('file_path', $createRes->json('data'));

        // Public show works by both UUID and slug
        $this->getJson("/api/v1/publikasi-peta/{$slug}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $id);

        // Public index & filter per desa
        $publicRes = $this->getJson('/api/v1/publikasi-peta?tingkat_wilayah=desa&id_desa=' . $this->desa->id);
        $publicRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Peta Jaringan Jalan Desa Ngumpakdalem');

        // Public download increments download_count and enforces security headers
        $dlRes = $this->get("/api/v1/publikasi-peta/{$id}/download");
        $dlRes->assertOk();
        $dlRes->assertHeader('Content-Type', 'application/pdf');
        $dlRes->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(1, PublikasiPetaDigital::findOrFail($id)->download_count);

        // Unauthorized user cannot create or delete
        $this->actingAs($this->regularUser)
            ->deleteJson("/api/v1/admin/publikasi-peta/{$id}")
            ->assertForbidden();
    }

    public function test_security_rejects_spoofed_pdf_and_dangerous_urls(): void
    {
        // Spoofed .pdf file without %PDF- magic bytes
        $fakePdf = UploadedFile::fake()->createWithContent('evil.pdf', '<script>alert(1)</script>');

        $this->actingAs($this->adminUser)->postJson('/api/v1/admin/publikasi-peta', [
            'judul' => 'Uji File Palsu',
            'kategori' => 'Peta Jalan Poros Desa',
            'tingkat_wilayah' => 'kabupaten',
            'tahun_terbit' => 2026,
            'sumber_tipe' => 'file_pdf',
            'file_pdf' => $fakePdf,
        ])->assertStatus(422);

        // Dangerous javascript: URL and localhost SSRF URL
        $this->actingAs($this->adminUser)->postJson('/api/v1/admin/publikasi-peta', [
            'judul' => 'Uji URL Berbahaya',
            'kategori' => 'Peta Jalan Poros Desa',
            'tingkat_wilayah' => 'kabupaten',
            'tahun_terbit' => 2026,
            'sumber_tipe' => 'external_url',
            'external_url' => 'javascript:alert(1)',
        ])->assertStatus(422);

        $this->actingAs($this->adminUser)->postJson('/api/v1/admin/publikasi-peta', [
            'judul' => 'Uji URL Localhost',
            'kategori' => 'Peta Jalan Poros Desa',
            'tingkat_wilayah' => 'kabupaten',
            'tahun_terbit' => 2026,
            'sumber_tipe' => 'external_url',
            'external_url' => 'http://127.0.0.1/secret.pdf',
        ])->assertStatus(422);

        // Valid external HTTPS URL works and unpublished items are hidden from public
        $validRes = $this->actingAs($this->adminUser)->postJson('/api/v1/admin/publikasi-peta', [
            'judul' => 'Atlas Infrastruktur Bojonegoro 2026',
            'kategori' => 'Atlas & Peta Dasar Wilayah',
            'tingkat_wilayah' => 'kabupaten',
            'tahun_terbit' => 2026,
            'sumber_tipe' => 'external_url',
            'external_url' => 'https://geoportal.bojonegorokab.go.id/files/atlas-2026.pdf',
            'is_published' => false,
        ]);

        $validRes->assertCreated()
            ->assertJsonPath('data.external_domain', 'geoportal.bojonegorokab.go.id')
            ->assertJsonPath('data.is_published', false);

        $this->getJson('/api/v1/publikasi-peta')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $id = $validRes->json('data.id');
        $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/admin/publikasi-peta/{$id}/toggle-publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->getJson('/api/v1/publikasi-peta')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_save_and_clear_maplibre_georeferencing_without_geotiff(): void
    {
        $validPdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $pdfFile = UploadedFile::fake()->createWithContent('peta-desa.pdf', $validPdfContent);

        $createRes = $this->actingAs($this->adminUser)->post('/api/v1/admin/publikasi-peta', [
            'judul' => 'Peta Batas & Jalan Desa Ngumpakdalem',
            'kategori' => 'Peta Batas Administrasi',
            'tingkat_wilayah' => 'desa',
            'id_kecamatan' => $this->kecamatan->id,
            'id_desa' => $this->desa->id,
            'tahun_terbit' => 2026,
            'sumber_tipe' => 'file_pdf',
            'is_published' => '1',
            'file_pdf' => $pdfFile,
        ])->assertCreated();

        $id = $createRes->json('data.id');

        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/publikasi-peta/{$id}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $id);

        $imageFile = UploadedFile::fake()->image('peta-raster.png', 400, 300);

        $coords = [
            [111.84, -7.15],
            [111.89, -7.15],
            [111.89, -7.20],
            [111.84, -7.20],
        ];
        $gcps = [
            ['id' => '1', 'label' => 'GCP-1', 'px' => 10, 'py' => 10, 'lng' => 111.841, 'lat' => -7.151],
            ['id' => '2', 'label' => 'GCP-2', 'px' => 390, 'py' => 10, 'lng' => 111.889, 'lat' => -7.151],
            ['id' => '3', 'label' => 'GCP-3', 'px' => 390, 'py' => 290, 'lng' => 111.889, 'lat' => -7.199],
        ];

        $geoRes = $this->actingAs($this->adminUser)->post("/api/v1/admin/publikasi-peta/{$id}/georeference", [
            'georef_method' => 'affine',
            'georef_coordinates' => json_encode($coords),
            'georef_gcps' => json_encode($gcps),
            'georef_rms_meters' => 1.85,
            'georef_opacity' => 0.8,
            'georef_image' => $imageFile,
        ]);

        $geoRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.is_georeferenced', true)
            ->assertJsonPath('data.georef_method', 'affine')
            ->assertJsonPath('data.georef_image_width', 400)
            ->assertJsonPath('data.georef_image_height', 300)
            ->assertJsonPath('data.has_georef_image', true);

        $this->assertArrayNotHasKey('georef_image_path', $geoRes->json('data'));

        // Public can stream the overlay raster for MapLibre image source
        $imgRes = $this->get("/api/v1/publikasi-peta/{$id}/georef-image");
        $imgRes->assertOk();
        $imgRes->assertHeader('Content-Type', 'image/png');
        $imgRes->assertHeader('X-Content-Type-Options', 'nosniff');

        // Reject invalid coordinates out of WebMercator bounds
        $this->actingAs($this->adminUser)->postJson("/api/v1/admin/publikasi-peta/{$id}/georeference", [
            'georef_method' => 'corners',
            'georef_coordinates' => json_encode([
                [200, -7.15],
                [111.89, -7.15],
                [111.89, -7.20],
                [111.84, -7.20],
            ]),
        ])->assertStatus(422);

        // Unauthorized user cannot save or clear georeferencing
        $this->actingAs($this->regularUser)
            ->deleteJson("/api/v1/admin/publikasi-peta/{$id}/georeference")
            ->assertForbidden();

        // Admin can clear georeferencing cleanly
        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/publikasi-peta/{$id}/georeference")
            ->assertOk()
            ->assertJsonPath('data.is_georeferenced', false)
            ->assertJsonPath('data.has_georef_image', false);

        $this->get("/api/v1/publikasi-peta/{$id}/georef-image")->assertNotFound();
    }
}

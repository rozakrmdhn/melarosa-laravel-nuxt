<?php

namespace Tests\Feature;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\InfrastrukturSegmen;
use App\Models\InfrastrukturTipe;
use App\Models\JadwalTahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InfrastrukturSegmenFotoTest extends TestCase
{
    use RefreshDatabase;

    protected BatasWilayahKecamatan $kec1;
    protected BatasWilayahDesa $desa1a;
    protected BatasWilayahDesa $desa1b;
    protected User $desa1aUser;
    protected User $desa1bUser;
    protected User $bappedaUser;
    protected InfrastrukturSegmen $segmen1a;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $perms = [
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-delete',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $bappedaRole = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $bappedaRole->syncPermissions($perms);

        $desaRole = Role::firstOrCreate(['name' => 'operatorDesa', 'guard_name' => 'web']);
        $desaRole->syncPermissions([
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
        ]);

        $this->kec1 = BatasWilayahKecamatan::create([
            'id' => 1,
            'kode_bps' => '352201',
            'nama_kecamatan' => 'Baureno',
        ]);

        $this->desa1a = BatasWilayahDesa::create([
            'id_kecamatan' => $this->kec1->id,
            'kode_bps' => '35220101',
            'nama_desa' => 'Pasinan',
        ]);

        $this->desa1b = BatasWilayahDesa::create([
            'id_kecamatan' => $this->kec1->id,
            'kode_bps' => '35220102',
            'nama_desa' => 'Kauman',
        ]);

        $this->desa1aUser = User::factory()->create([
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => $this->desa1a->id,
        ]);
        $this->desa1aUser->assignRole($desaRole);

        $this->desa1bUser = User::factory()->create([
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => $this->desa1b->id,
        ]);
        $this->desa1bUser->assignRole($desaRole);

        $this->bappedaUser = User::factory()->create();
        $this->bappedaUser->assignRole($bappedaRole);

        InfrastrukturTipe::firstOrCreate(
            ['kode' => 'jalan_poros'],
            ['nama' => 'Jalan Poros Desa', 'geom_type' => 'LineString', 'is_active' => true],
        );

        $this->segmen1a = InfrastrukturSegmen::create([
            'id'                => (string) Str::uuid(),
            'tipe_kode'         => 'jalan_poros',
            'namobj'            => 'Ruas Pasinan Test',
            'id_kecamatan'      => $this->kec1->id,
            'id_desa'           => $this->desa1a->id,
            'is_non_pagu'       => true,
            'tahun_pembangunan' => 2026,
            'status_verifikasi' => 'draft',
            'user_id'           => $this->desa1aUser->uuid,
            'created_by'        => $this->desa1aUser->uuid,
        ]);
    }

    public function test_upload_sanitizes_image_enforces_max_4_and_serves_privately(): void
    {
        // Upload 4 foto sah
        $stages = ['Titik Awal (0%)', 'Bentang Tengah (50%)', 'Titik Akhir (100%)', 'Detail Kondisi'];
        $fotoIds = [];
        foreach ($stages as $idx => $stage) {
            $img = UploadedFile::fake()->image("survey_{$idx}.jpg", 1200, 900);
            $res = $this->actingAs($this->desa1aUser)
                ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                    'foto'        => $img,
                    'tahap_label' => $stage,
                    'keterangan'  => '<b>Dokumentasi</b> lapangan',
                ]);

            $res->assertStatus(201);
            $res->assertJsonMissingPath('data.path');
            $res->assertJsonMissingPath('data.disk');
            $res->assertJsonMissingPath('data.sha256');
            $this->assertEquals('Dokumentasi lapangan', $res->json('data.keterangan'));
            $this->assertEquals('image/webp', $res->json('data.mime_type'));
            $fotoIds[] = $res->json('data.id');
        }

        // Upload ke-5 harus ditolak (maks 4)
        $img5 = UploadedFile::fake()->image('survey_5.jpg', 800, 600);
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => $img5,
            ])
            ->assertStatus(422);

        // User desa lain ditolak melihat & mengunduh foto privat
        $this->actingAs($this->desa1bUser)
            ->get("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos/{$fotoIds[0]}/file")
            ->assertStatus(403);

        // User desa pemilik diizinkan melihat dengan security headers ketat
        $fileRes = $this->actingAs($this->desa1aUser)
            ->get("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos/{$fotoIds[0]}/file");
        $fileRes->assertOk();
        $this->assertEquals('image/webp', $fileRes->headers->get('Content-Type'));
        $this->assertEquals('nosniff', $fileRes->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString("default-src 'none'", (string) $fileRes->headers->get('Content-Security-Policy'));

        // Pastikan path di private disk benar ada dan terhapus ketika foto dihapus
        $storedPath = DB::table('infrastruktur_segmen_fotos')->where('id', $fotoIds[0])->value('path');
        Storage::disk('local')->assertExists($storedPath);

        $this->actingAs($this->desa1aUser)
            ->deleteJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos/{$fotoIds[0]}")
            ->assertOk();

        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_rejects_malicious_payloads_double_extensions_and_closed_schedule(): void
    {
        // 1. Tolak script PHP menyamar jadi .jpg
        $fakePhp = UploadedFile::fake()->createWithContent('payload.jpg', '<?php echo "hacked"; ?>');
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => $fakePhp,
            ])
            ->assertStatus(422);

        // 2. Tolak SVG / XSS
        $fakeSvg = UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => $fakeSvg,
            ])
            ->assertStatus(422);

        // 3. Tolak double extension mencurigakan walau isi gambar valid
        $doubleExt = UploadedFile::fake()->image('shell.php.png', 400, 400);
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => $doubleExt,
            ])
            ->assertStatus(422);

        // 4. Tolak dimensi terlalu kecil (< 50px)
        $tiny = UploadedFile::fake()->image('tiny.png', 16, 16);
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => $tiny,
            ])
            ->assertStatus(422);

        // 5. Tolak upload oleh Desa saat jadwal tahun anggaran ditutup, Bappeda tetap bisa bypass
        JadwalTahunAnggaran::create([
            'tahun_anggaran' => 2026,
            'nama_periode'   => 'TA 2026 Tutup',
            'status_jadwal'  => 'ditutup',
            'allow_plotting' => true,
            'allow_digitasi' => true,
            'allow_non_pagu' => true,
        ]);

        $validImg = UploadedFile::fake()->image('lapangan.jpg', 800, 600);
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => $validImg,
            ])
            ->assertStatus(403);

        $this->actingAs($this->bappedaUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$this->segmen1a->id}/fotos", [
                'foto' => UploadedFile::fake()->image('bappeda.jpg', 800, 600),
            ])
            ->assertStatus(201);
    }
}

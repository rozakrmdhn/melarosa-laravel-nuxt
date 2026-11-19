<?php

namespace Tests\Feature\Bkkd;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\BkkdProposal;
use App\Models\BkkdProposalLokasi;
use App\Models\JalanPorosDesa;
use App\Models\RefBkkdBidang;
use App\Models\RefOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BkkdJaringanJalanPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected User $bappedaUser;
    protected User $desaUser;
    protected BatasWilayahKecamatan $kecamatan;
    protected BatasWilayahDesa $desa;
    protected RefBkkdBidang $bidang;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'bkkd-proposal-view',
            'bkkd-proposal-create',
            'bkkd-proposal-update',
            'bkkd-proposal-delete',
            'bkkd-riwayat-view',
            'jaringan-jalan-view',
            'jaringan-jalan-manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $bappedaRole = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $bappedaRole->syncPermissions($permissions);

        $desaRole = Role::firstOrCreate(['name' => 'operatorDesa', 'guard_name' => 'web']);
        $desaRole->syncPermissions(['bkkd-proposal-view', 'bkkd-proposal-create', 'bkkd-proposal-update']);

        $this->kecamatan = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522030],
            ['nama_kecamatan' => 'Kecamatan Kalitidu']
        );

        $this->desa = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522030001],
            ['nama_desa' => 'Desa Wotanngare', 'id_kecamatan' => $this->kecamatan->id]
        );

        $opd = RefOpd::create([
            'kode' => 'OPD-PU-JJ',
            'nama' => 'Dinas Pekerjaan Umum Bina Marga',
        ]);

        $this->bidang = RefBkkdBidang::create([
            'nama' => 'Infrastruktur Jalan Lingkungan',
            'id_opd_default' => $opd->id,
        ]);

        $this->bappedaUser = User::factory()->create();
        $this->bappedaUser->assignRole('verifierBappeda');

        $this->desaUser = User::factory()->create([
            'id_kecamatan' => $this->kecamatan->id,
            'id_desa' => $this->desa->id,
        ]);
        $this->desaUser->assignRole('operatorDesa');
    }

    private function createProposal(array $overrides = []): BkkdProposal
    {
        return BkkdProposal::create(array_merge([
            'nomor_registrasi' => 'BKKD/2027/3522030001/' . Str::random(5),
            'tahun_anggaran' => 2027,
            'tagging_usulan' => 'PRKPD',
            'id_kecamatan' => $this->kecamatan->id,
            'id_desa' => $this->desa->id,
            'id_bidang' => $this->bidang->id,
            'judul_kegiatan' => 'Pembangunan Jalan Dusun Wotan',
            'nilai_usulan' => 175000000,
            'versi' => 1,
            'created_by' => $this->desaUser->uuid,
        ], $overrides));
    }

    public function test_spatial_validation_rejects_straight_two_point_line_and_existing_master_duplicate(): void
    {
        $proposal = $this->createProposal();

        // 1. Garis lurus 2 titik tidak boleh masuk master jaringan jalan
        $resStraight = $this->actingAs($this->desaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Garis Lurus Mentah',
            'sumber_rute' => 'garis_lurus',
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => [
                    [111.7800, -7.1700],
                    [111.7820, -7.1700],
                ],
            ],
        ]);

        $resStraight->assertCreated()
            ->assertJsonPath('data.status_promosi_master', 'tidak_perlu')
            ->assertJsonPath('data.validasi_promosi_spasial.lolos_validasi', false);

        $straightLokasiId = $resStraight->json('data.id');
        $this->actingAs($this->desaUser)
            ->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi/{$straightLokasiId}/ajukan-ruas", [
                'nama_ruas_usulan' => 'Jl. Lurus Mentah',
            ])
            ->assertStatus(422);

        // 2. Koridor yang sudah berimpit 100% dengan Jalan Poros Desa ditolak sebagai ruas baru
        $existingGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.7850, -7.1750],
                [111.7860, -7.1750],
                [111.7870, -7.1750],
            ],
        ];
        $jp = new JalanPorosDesa([
            'kode_ruas' => 301,
            'nama_ruas' => 'Jl. Poros Wotanngare Utama',
            'id_desa' => $this->desa->id,
            'id_kecamatan' => $this->kecamatan->id,
            'panjang' => 225,
            'lebar' => 4,
            'geom' => json_encode($existingGeo),
        ]);
        $jp->id = (string) Str::uuid();
        $jp->save();

        $resDupPoros = $this->actingAs($this->desaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Segmen di atas Poros Eksisting',
            'sumber_rute' => 'manual',
            'geojson' => $existingGeo,
        ]);

        $resDupPoros->assertCreated()
            ->assertJsonPath('data.status_promosi_master', 'tidak_perlu')
            ->assertJsonPath('data.validasi_promosi_spasial.lolos_validasi', false)
            ->assertJsonPath('data.validasi_promosi_spasial.rekomendasi_aksi', 'tautkan_eksisting');
    }

    public function test_proposal_corridor_can_be_queued_validated_by_bappeda_and_used_by_next_year_analysis(): void
    {
        $proposal2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'tagging_usulan' => 'PRKPD',
            'judul_kegiatan' => 'Peningkatan Jalan Lingkungan Dusun Krajan',
        ]);

        // Koridor 3 titik (berbelok mengikuti jalan lingkungan di luar jalan_porosdesa)
        $newRoadGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.7900, -7.1800],
                [111.7912, -7.1802],
                [111.7924, -7.1805],
            ],
        ];

        $createRes = $this->actingAs($this->desaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi", [
            'nama_lokasi' => 'Gang Krajan RT 02',
            'lebar_m' => 3.5,
            'sumber_rute' => 'manual',
            'geojson' => $newRoadGeo,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.status_promosi_master', 'kandidat')
            ->assertJsonPath('data.validasi_promosi_spasial.lolos_validasi', true)
            ->assertJsonPath('data.validasi_promosi_spasial.rekomendasi_aksi', 'ruas_baru');

        $lokasiId = $createRes->json('data.id');

        // Operator desa mengajukan masuk Master Jaringan Jalan
        $ajukanRes = $this->actingAs($this->desaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi/{$lokasiId}/ajukan-ruas", [
            'nama_ruas_usulan' => 'Jl. Lingkungan Krajan RT 02',
            'hierarki_jalan_usulan' => 'lingkungan',
        ]);

        $ajukanRes->assertOk()
            ->assertJsonPath('data.status_promosi_master', 'diajukan')
            ->assertJsonPath('data.nama_ruas_usulan', 'Jl. Lingkungan Krajan RT 02');

        // Operator desa dilarang memvalidasi sendiri (403)
        $this->actingAs($this->desaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi/{$lokasiId}/validasi-ruas", [
            'keputusan' => 'setujui_baru',
            'nama_ruas' => 'Jl. Lingkungan Krajan RT 02',
            'hierarki_jalan' => 'lingkungan',
        ])->assertForbidden();

        // Cek muncul di antrean kandidat ruas
        $queueRes = $this->actingAs($this->bappedaUser)->getJson('/api/v1/admin/bkkd/kandidat-ruas?status_promosi=diajukan');
        $queueRes->assertOk();
        $this->assertTrue(collect($queueRes->json('data'))->contains('id', $lokasiId));

        // Bappeda memvalidasi dan mempromosikan ruas ke Master Jaringan Jalan Kabupaten
        $approveRes = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi/{$lokasiId}/validasi-ruas", [
            'keputusan' => 'setujui_baru',
            'nama_ruas' => 'Jl. Lingkungan Krajan RT 02',
            'hierarki_jalan' => 'lingkungan',
            'perkerasan' => 'Beton',
            'kondisi' => 'Rusak Ringan',
        ]);

        $approveRes->assertOk()
            ->assertJsonPath('data.status_promosi_master', 'disetujui')
            ->assertJsonPath('jaringan_ruas.properties.nama_ruas', 'Jl. Lingkungan Krajan RT 02')
            ->assertJsonPath('jaringan_ruas.properties.hierarki_jalan', 'lingkungan');

        $ruasId = $approveRes->json('jaringan_ruas.properties.id');
        $this->assertNotEmpty($ruasId);
        $this->assertDatabaseHas('jalan_porosdesa', [
            'id' => $ruasId,
            'nama_ruas' => 'Jl. Lingkungan Krajan RT 02',
            'hierarki_jalan' => 'lingkungan',
            'sumber_data' => JalanPorosDesa::SUMBER_PROMOSI_PROPOSAL,
            'sumber_proposal_id' => $proposal2027->id,
            'sumber_lokasi_id' => $lokasiId,
        ]);
        $this->assertDatabaseHas('bkkd_proposal_lokasi', [
            'id' => $lokasiId,
            'id_jalan_poros' => $ruasId,
            'id_jaringan_ruas' => $ruasId,
            'status_promosi_master' => 'disetujui',
        ]);

        // Ruas muncul pada endpoint Master Jaringan Jalan (sumber: jalan_porosdesa) & layer desa
        $masterRes = $this->actingAs($this->desaUser)->getJson('/api/v1/admin/bkkd/jaringan-jalan?id_desa=' . $this->desa->id);
        $masterRes->assertOk()
            ->assertJsonPath('summary.total_ruas', 1)
            ->assertJsonPath('summary.jumlah_promosi_proposal', 1);

        $layerRes = $this->actingAs($this->desaUser)->getJson('/api/v1/admin/bkkd/ref-jalan-poros?id_desa=' . $this->desa->id);
        $layerRes->assertOk();
        $this->assertCount(1, $layerRes->json('layers.jaringan_jalan_ruas.features'));

        // Proposal RKPD 2028 baru pada koridor sama otomatis mendeteksi jalan_porosdesa hasil promosi di Layer 1
        $proposal2028 = $this->createProposal([
            'tahun_anggaran' => 2028,
            'tagging_usulan' => 'RKPD',
            'judul_kegiatan' => 'Lanjutan Jalan Lingkungan Krajan',
        ]);

        $nextRes = $this->actingAs($this->desaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2028->id}/lokasi", [
            'nama_lokasi' => 'Segmen Lanjutan Krajan',
            'sumber_rute' => 'manual',
            'geojson' => $newRoadGeo,
        ]);

        $nextRes->assertCreated();
        $refJalan = collect($nextRes->json('data.ringkasan_analisa.referensi_jalan_poros'));
        $this->assertTrue($refJalan->contains(fn ($r) => ($r['id_jalan_poros'] ?? null) === $ruasId && ($r['id_jaringan_ruas'] ?? null) === $ruasId));

        // Endpoint modul standalone /api/v1/admin/jaringan-jalan & update + delete + navigasi sidebar
        $this->actingAs($this->bappedaUser)->getJson('/api/v1/admin/jaringan-jalan')
            ->assertOk()
            ->assertJsonPath('summary.total_ruas', 1);

        $this->actingAs($this->bappedaUser)->getJson('/api/v1/admin/jaringan-jalan/kandidat?status_promosi=disetujui')
            ->assertOk();

        $this->actingAs($this->bappedaUser)->putJson("/api/v1/admin/jaringan-jalan/{$ruasId}", [
            'nama_ruas' => 'Jl. Lingkungan Krajan Utama',
            'hierarki_jalan' => 'poros_desa',
            'kode_ruas' => 405,
        ])->assertOk()
            ->assertJsonPath('data.properties.nama_ruas', 'Jl. Lingkungan Krajan Utama')
            ->assertJsonPath('data.properties.hierarki_jalan', 'poros_desa')
            ->assertJsonPath('data.properties.kode_ruas', '405');

        $navRes = $this->actingAs($this->bappedaUser)->getJson('/api/v1/user');
        $navRes->assertOk();
        $this->assertTrue(collect($navRes->json('user.navigation'))->contains('to', '/admin/jaringan-jalan'));

        $this->actingAs($this->bappedaUser)->deleteJson("/api/v1/admin/jaringan-jalan/{$ruasId}")
            ->assertOk();
        $this->assertDatabaseMissing('jalan_porosdesa', ['id' => $ruasId]);
        $this->assertDatabaseHas('bkkd_proposal_lokasi', [
            'id' => $lokasiId,
            'id_jalan_poros' => null,
            'id_jaringan_ruas' => null,
            'status_promosi_master' => 'kandidat',
        ]);
    }
}

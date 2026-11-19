<?php

namespace Tests\Feature;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\InfrastrukturTipe;
use App\Models\PlottingAnggaran;
use App\Models\RefBkkdBidang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JadwalTahunAnggaranTest extends TestCase
{
    use RefreshDatabase;

    protected BatasWilayahKecamatan $kec1;
    protected BatasWilayahDesa $desa1a;
    protected BatasWilayahDesa $desa1b;
    protected User $desa1aUser;
    protected User $desa1bUser;
    protected User $kec1User;
    protected User $bappedaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $perms = [
            'jadwal-anggaran-view',
            'jadwal-anggaran-manage',
            'plotting-anggaran-view',
            'plotting-anggaran-manage',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-delete',
            'infrastruktur-segmen-verify-kecamatan',
            'bkkd-proposal-view',
            'bkkd-proposal-create',
        ];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $bappedaRole = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $bappedaRole->syncPermissions($perms);

        $kecRole = Role::firstOrCreate(['name' => 'verifierKecamatan', 'guard_name' => 'web']);
        $kecRole->syncPermissions([
            'plotting-anggaran-view',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-delete',
            'infrastruktur-segmen-verify-kecamatan',
            'bkkd-proposal-view',
        ]);

        $desaRole = Role::firstOrCreate(['name' => 'operatorDesa', 'guard_name' => 'web']);
        $desaRole->syncPermissions([
            'plotting-anggaran-view',
            'plotting-anggaran-manage',
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'bkkd-proposal-view',
            'bkkd-proposal-create',
        ]);

        $this->kec1 = BatasWilayahKecamatan::create([
            'id' => 1,
            'kode_bps' => '352201',
            'nama_kecamatan' => 'Baureno',
        ]);

        $this->desa1a = BatasWilayahDesa::create([
            'id' => 101,
            'id_kecamatan' => $this->kec1->id,
            'kode_bps' => '35220101',
            'nama_desa' => 'Pasinan',
        ]);

        $this->desa1b = BatasWilayahDesa::create([
            'id' => 102,
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

        $this->kec1User = User::factory()->create([
            'id_kecamatan' => $this->kec1->id,
            'id_desa' => null,
        ]);
        $this->kec1User->assignRole($kecRole);

        $this->bappedaUser = User::factory()->create();
        $this->bappedaUser->assignRole($bappedaRole);

        InfrastrukturTipe::firstOrCreate(
            ['kode' => 'jalan_poros'],
            ['nama' => 'Jalan Poros Desa', 'geom_type' => 'LineString', 'is_active' => true],
        );
    }

    public function test_bappeda_can_schedule_years_override_specific_desa_and_bypass_always(): void
    {
        // Role Desa tidak berhak membuat jadwal
        $this->actingAs($this->desa1aUser)
            ->postJson('/api/v1/admin/jadwal-anggaran', [
                'tahun_anggaran' => 2026,
                'nama_periode'   => 'Periode Ilegal',
                'status_jadwal'  => 'dibuka',
            ])
            ->assertStatus(403);

        // Bappeda membuat jadwal 2025 (ditutup) dan 2026 (dibuka)
        $res2025 = $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/jadwal-anggaran', [
                'tahun_anggaran' => 2025,
                'nama_periode'   => 'Tahun Anggaran 2025',
                'status_jadwal'  => 'ditutup',
                'allow_plotting' => true,
                'allow_digitasi' => true,
                'allow_non_pagu' => true,
                'catatan'        => 'Periode reguler 2025 telah berakhir.',
            ]);
        $res2025->assertStatus(201);
        $jadwal2025Id = $res2025->json('data.id');

        $res2026 = $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/jadwal-anggaran', [
                'tahun_anggaran' => 2026,
                'nama_periode'   => 'Tahun Anggaran 2026',
                'status_jadwal'  => 'dibuka',
                'allow_plotting' => true,
                'allow_digitasi' => true,
                'allow_non_pagu' => true,
            ]);
        $res2026->assertStatus(201);
        $jadwal2026Id = $res2026->json('data.id');

        // Bappeda dapat membuat plotting 2025 walaupun ditutup (bypass) DAN target_panjang_m boleh dikosongkan (opsional)
        $plot2025ByBappeda = $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran'       => 2025,
                'id_kecamatan'         => $this->kec1->id,
                'id_desa'              => $this->desa1a->id,
                'jenis_bantuan'        => 'BKK Desa',
                'nama_kegiatan'        => 'Koreksi Pagu BKK 2025 Desa Pasinan',
                'sumber_dana'          => 'BKK_DESA',
                'nomor_sk'             => '188/2025/01',
                'target_pagu_anggaran' => 250000000,
                // target_panjang_m sengaja tidak dikirim karena Bappeda hanya tahu pagu Rp
            ]);
        $plot2025ByBappeda->assertStatus(201);
        $plot2025Id = $plot2025ByBappeda->json('data.id');

        // Desa 1A ditolak input segmen pada TA 2025 yang ditutup
        $this->actingAs($this->desa1aUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode'    => 'jalan_poros',
                'namobj'       => 'Segmen 2025 Tertutup',
                'plotting_id'  => $plot2025Id,
                'is_non_pagu'  => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa'      => $this->desa1a->id,
                'panjang'      => 150,
                'geometry'     => ['type' => 'LineString', 'coordinates' => [[111.88, -7.15], [111.881, -7.151]]],
            ])
            ->assertStatus(403);

        // Bappeda tetap bisa bypass input segmen pada TA 2025 kapan saja
        $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode'    => 'jalan_poros',
                'namobj'       => 'Koreksi Bappeda TA 2025',
                'plotting_id'  => $plot2025Id,
                'is_non_pagu'  => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa'      => $this->desa1a->id,
                'panjang'      => 150,
                'geometry'     => ['type' => 'LineString', 'coordinates' => [[111.88, -7.15], [111.881, -7.151]]],
            ])
            ->assertStatus(201);

        // Bappeda memberikan dispensasi 'buka_paksa' TA 2025 khusus untuk Desa 1A (Pasinan)
        $this->actingAs($this->bappedaUser)
            ->postJson("/api/v1/admin/jadwal-anggaran/{$jadwal2025Id}/overrides", [
                'id_kecamatan' => $this->kec1->id,
                'id_desa'      => $this->desa1a->id,
                'mode_akses'   => 'buka_paksa',
                'alasan'       => 'Dispensasi perpanjangan input Desa Pasinan.',
            ])
            ->assertStatus(201);

        // Sekarang Desa 1A BISA mendigitasi segmen TA 2025 (walaupun target_panjang_m = 0), sedangkan Desa 1B tetap ditolak
        $this->actingAs($this->desa1aUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode'    => 'jalan_poros',
                'namobj'       => 'Realisasi Pasinan via Dispensasi',
                'plotting_id'  => $plot2025Id,
                'is_non_pagu'  => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa'      => $this->desa1a->id,
                'panjang'      => 320,
                'geometry'     => ['type' => 'LineString', 'coordinates' => [[111.88, -7.15], [111.882, -7.152]]],
            ])
            ->assertStatus(201);

        $this->actingAs($this->desa1bUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran'       => 2025,
                'id_kecamatan'         => $this->kec1->id,
                'id_desa'              => $this->desa1b->id,
                'jenis_bantuan'        => 'Dana Desa',
                'nama_kegiatan'        => 'Desa Kauman Coba 2025',
                'sumber_dana'          => 'DANA_DESA',
                'target_pagu_anggaran' => 100000000,
            ])
            ->assertStatus(403);

        // Bappeda menutup khusus Desa 1A ('tutup_paksa') pada TA 2026 sementara Desa 1B tetap terbuka
        $this->actingAs($this->bappedaUser)
            ->postJson("/api/v1/admin/jadwal-anggaran/{$jadwal2026Id}/overrides", [
                'id_kecamatan' => $this->kec1->id,
                'id_desa'      => $this->desa1a->id,
                'mode_akses'   => 'tutup_paksa',
                'alasan'       => 'Kunci sementara untuk audit lapangan.',
            ])
            ->assertStatus(201);

        $this->actingAs($this->desa1aUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran'       => 2026,
                'id_kecamatan'         => $this->kec1->id,
                'id_desa'              => $this->desa1a->id,
                'jenis_bantuan'        => 'Dana Desa',
                'nama_kegiatan'        => 'Pasinan Diblokir 2026',
                'sumber_dana'          => 'DANA_DESA',
                'target_pagu_anggaran' => 100000000,
            ])
            ->assertStatus(403);

        $this->actingAs($this->desa1bUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran'       => 2026,
                'id_kecamatan'         => $this->kec1->id,
                'id_desa'              => $this->desa1b->id,
                'jenis_bantuan'        => 'Dana Desa',
                'nama_kegiatan'        => 'Kauman Terbuka 2026',
                'sumber_dana'          => 'DANA_DESA',
                'target_pagu_anggaran' => 100000000,
            ])
            ->assertStatus(201);

        // Pastikan /jadwal-anggaran/active mengembalikan status akurat untuk Desa 1A
        $activeRes = $this->actingAs($this->desa1aUser)->getJson('/api/v1/admin/jadwal-anggaran/active');
        $activeRes->assertOk()->assertJsonPath('configured', true);
        $periods = collect($activeRes->json('data'))->keyBy('tahun_anggaran');
        $this->assertTrue($periods[2025]['effective_open']);
        $this->assertTrue($periods[2025]['is_desa_override']);
        $this->assertFalse($periods[2026]['effective_open']);
        $this->assertContains($this->desa1a->id, $periods[2025]['open_desa_override_ids']);
        $this->assertContains($this->desa1a->id, $periods[2026]['closed_desa_override_ids']);

        // Bappeda tetap mendapat daftar open_desa_override_ids & closed_desa_override_ids untuk penyaringan dropdown
        $bappedaActiveRes = $this->actingAs($this->bappedaUser)->getJson('/api/v1/admin/jadwal-anggaran/active');
        $bappedaActiveRes->assertOk()->assertJsonPath('is_bypass', true);
        $bappedaPeriods = collect($bappedaActiveRes->json('data'))->keyBy('tahun_anggaran');
        $this->assertFalse($bappedaPeriods[2025]['is_open']);
        $this->assertTrue($bappedaPeriods[2025]['effective_open']);
        $this->assertContains($this->desa1a->id, $bappedaPeriods[2025]['open_desa_override_ids']);
        $this->assertContains($this->desa1a->id, $bappedaPeriods[2026]['closed_desa_override_ids']);

        // Saat jadwal ditutup untuk Desa 1A (TA 2026 tutup_paksa):
        // 1) Delete plotting & delete segmen oleh Desa/Kecamatan ditolak (403)
        // 2) Ajukan Verifikasi (submit) & Verifikasi Kecamatan tetap diperbolehkan (200)
        // 3) Admin/Bappeda tetap bypass penuh (boleh hapus kapan saja)
        $closedPlotDesa = $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/plotting-anggaran', [
                'tahun_anggaran'       => 2026,
                'id_kecamatan'         => $this->kec1->id,
                'id_desa'              => $this->desa1a->id,
                'jenis_bantuan'        => 'Dana Desa',
                'nama_kegiatan'        => 'Kunci Hapus TA 2026 Pasinan',
                'sumber_dana'          => 'DANA_DESA',
                'target_pagu_anggaran' => 120000000,
            ])
            ->assertStatus(201);
        $closedPlotId = $closedPlotDesa->json('data.id');

        $this->actingAs($this->desa1aUser)
            ->deleteJson("/api/v1/admin/plotting-anggaran/{$closedPlotId}")
            ->assertStatus(403);

        $draftClosedSeg = $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', [
                'tipe_kode'    => 'jalan_poros',
                'namobj'       => 'Segmen Audit 2026',
                'plotting_id'  => $closedPlotId,
                'is_non_pagu'  => false,
                'id_kecamatan' => $this->kec1->id,
                'id_desa'      => $this->desa1a->id,
                'panjang'      => 180,
                'geometry'     => ['type' => 'LineString', 'coordinates' => [[111.88, -7.15], [111.881, -7.151]]],
            ])
            ->assertStatus(201);
        $draftClosedSegId = $draftClosedSeg->json('data.id');

        $this->actingAs($this->kec1User)
            ->deleteJson("/api/v1/admin/infrastruktur-segmen/{$draftClosedSegId}")
            ->assertStatus(403);

        // Submit & Verify Kecamatan tetap diizinkan meski jadwal edit/hapus ditutup
        $this->actingAs($this->desa1aUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$draftClosedSegId}/submit")
            ->assertOk();

        $this->actingAs($this->kec1User)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$draftClosedSegId}/verify-kecamatan", [
                'action' => 'approve',
            ])
            ->assertOk();

        // Bappeda tetap bisa bypass hapus segmen dan plotting (setelah contoh plotting kosong)
        $this->actingAs($this->bappedaUser)
            ->deleteJson("/api/v1/admin/infrastruktur-segmen/{$draftClosedSegId}")
            ->assertOk();

        $this->actingAs($this->bappedaUser)
            ->deleteJson("/api/v1/admin/plotting-anggaran/{$closedPlotId}")
            ->assertOk();
    }

    public function test_jadwal_does_not_block_bkkd_proposal_submission(): void
    {
        $this->actingAs($this->bappedaUser)
            ->postJson('/api/v1/admin/jadwal-anggaran', [
                'tahun_anggaran' => 2025,
                'nama_periode'   => 'TA 2025 Tutup',
                'status_jadwal'  => 'ditutup',
            ])
            ->assertStatus(201);

        $bidang = RefBkkdBidang::create([
            'id' => (string) Str::uuid(),
            'nama' => 'BKK JALAN',
            'is_active' => true,
        ]);

        // Pengajuan Proposal BKKD TA 2025 oleh Desa tetap diperbolehkan
        $this->actingAs($this->desa1aUser)
            ->postJson('/api/v1/admin/bkkd/proposal', [
                'tahun_anggaran' => 2025,
                'id_kecamatan'   => $this->kec1->id,
                'id_desa'        => $this->desa1a->id,
                'id_bidang'      => $bidang->id,
                'judul_kegiatan' => 'Usulan BKKD 2025 Tidak Terikat Jadwal Segmen',
                'nilai_usulan'   => 300000000,
            ])
            ->assertStatus(201);
    }
}

<?php

namespace Tests\Feature;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\BkkdProposal;
use App\Models\RefBkkdBidang;
use App\Models\RefOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BkkdPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'bkkd-proposal-view', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo('bkkd-proposal-view');
    }

    public function test_bkkd_proposal_index_with_consolidated_summary(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $kecamatan = new BatasWilayahKecamatan();
        $kecamatan->id = 1;
        $kecamatan->nama_kecamatan = 'Kecamatan Test';
        $kecamatan->save();

        $desa = new BatasWilayahDesa();
        $desa->id = 101;
        $desa->id_kecamatan = 1;
        $desa->nama_desa = 'Desa Test';
        $desa->save();

        $opd = RefOpd::create([
            'kode' => 'OPD-PU',
            'nama' => 'Dinas PU',
        ]);

        $bidang = RefBkkdBidang::create([
            'nama' => 'Infrastruktur Jalan',
            'id_opd_default' => $opd->id,
        ]);

        BkkdProposal::create([
            'nomor_registrasi' => 'PROP-2026-001',
            'tahun_anggaran' => 2026,
            'id_kecamatan' => 1,
            'id_desa' => 101,
            'id_bidang' => $bidang->id,
            'nomor_surat_desa' => '123/TEST/2026',
            'tanggal_surat_desa' => '2026-01-01',
            'tanggal_diterima' => '2026-01-02',
            'judul_kegiatan' => 'Pembangunan Jalan Poros',
            'nilai_usulan' => 150000000,
            'status' => 'draft',
            'created_by' => $admin->uuid,
        ]);

        BkkdProposal::create([
            'nomor_registrasi' => 'PROP-2026-002',
            'tahun_anggaran' => 2026,
            'id_kecamatan' => 1,
            'id_desa' => 101,
            'id_bidang' => $bidang->id,
            'nomor_surat_desa' => '124/TEST/2026',
            'tanggal_surat_desa' => '2026-01-01',
            'tanggal_diterima' => '2026-01-02',
            'judul_kegiatan' => 'Pembangunan Jembatan',
            'nilai_usulan' => 250000000,
            'status' => 'diajukan_opd',
            'created_by' => $admin->uuid,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/bkkd/proposal?tahun_anggaran=2026');

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'summary' => [
                'total_proposal' => 2,
                'total_nilai_usulan' => 400000000,
                'jumlah_draft' => 1,
                'jumlah_diajukan' => 1,
                'jumlah_layak' => 0,
                'jumlah_tidak_layak' => 0,
                'jumlah_ditetapkan' => 0,
            ],
        ]);
    }

    public function test_bkkd_agregasi_desa_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $kecamatan = new BatasWilayahKecamatan();
        $kecamatan->id = 1;
        $kecamatan->nama_kecamatan = 'Kecamatan Test';
        $kecamatan->save();

        $desa = new BatasWilayahDesa();
        $desa->id = 101;
        $desa->id_kecamatan = 1;
        $desa->nama_desa = 'Desa Test';
        $desa->save();

        $opd = RefOpd::create([
            'kode' => 'OPD-PU',
            'nama' => 'Dinas PU',
        ]);

        $bidang = RefBkkdBidang::create([
            'nama' => 'Infrastruktur Jalan',
            'id_opd_default' => $opd->id,
        ]);

        BkkdProposal::create([
            'nomor_registrasi' => 'PROP-2026-001',
            'tahun_anggaran' => 2026,
            'id_kecamatan' => 1,
            'id_desa' => 101,
            'id_bidang' => $bidang->id,
            'nomor_surat_desa' => '123/TEST/2026',
            'tanggal_surat_desa' => '2026-01-01',
            'tanggal_diterima' => '2026-01-02',
            'judul_kegiatan' => 'Pembangunan Jalan Poros',
            'nilai_usulan' => 150000000,
            'status' => 'draft',
            'created_by' => $admin->uuid,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/bkkd/agregasi-desa?tahun_anggaran=2026');

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tahun_anggaran' => 2026,
            'summary' => [
                'total_desa' => 1,
                'desa_ada_usulan' => 1,
                'desa_belum_ada_usulan' => 0,
                'total_usulan' => 1,
            ],
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\StatusVerifikasi;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\InfrastrukturSegmen;
use App\Models\InfrastrukturTipe;
use App\Models\MonitoringRealisasi;
use App\Models\PlottingAnggaran;
use App\Models\RefSumberDana;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InfrastrukturVerificationTest extends TestCase
{
    protected User $adminUser;
    protected User $kecamatanUser;
    protected User $bappedaUser;
    protected BatasWilayahKecamatan $kecamatan;
    protected BatasWilayahDesa $desa;

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan permission dan role jika belum ada
        $permissions = [
            'infrastruktur-tipe-view', 'infrastruktur-tipe-manage',
            'ref-sumber-dana-view', 'ref-sumber-dana-manage',
            'plotting-anggaran-view', 'plotting-anggaran-manage',
            'infrastruktur-segmen-view', 'infrastruktur-segmen-create',
            'infrastruktur-segmen-update', 'infrastruktur-segmen-delete',
            'infrastruktur-segmen-verify-kecamatan', 'infrastruktur-segmen-verify-bappeda',
            'monitoring-realisasi-view', 'monitoring-realisasi-manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleKec = Role::firstOrCreate(['name' => 'verifierKecamatan', 'guard_name' => 'web']);
        $roleBappeda = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);

        $roleAdmin->syncPermissions(Permission::all());
        $roleKec->syncPermissions([
            'infrastruktur-segmen-view',
            'infrastruktur-segmen-create',
            'infrastruktur-segmen-update',
            'infrastruktur-segmen-verify-kecamatan',
            'plotting-anggaran-view',
            'ref-sumber-dana-view',
            'infrastruktur-tipe-view',
            'monitoring-realisasi-view',
        ]);
        $roleBappeda->syncPermissions([
            'infrastruktur-tipe-view', 'infrastruktur-tipe-manage',
            'ref-sumber-dana-view', 'ref-sumber-dana-manage',
            'plotting-anggaran-view', 'plotting-anggaran-manage',
            'infrastruktur-segmen-view', 'infrastruktur-segmen-create',
            'infrastruktur-segmen-update', 'infrastruktur-segmen-delete',
            'infrastruktur-segmen-verify-bappeda',
            'monitoring-realisasi-view', 'monitoring-realisasi-manage',
        ]);

        // Wilayah dummy
        $this->kecamatan = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522010],
            ['nama_kecamatan' => 'Kecamatan Uji Coba']
        );

        $this->desa = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522010001],
            ['nama_desa' => 'Desa Uji Coba', 'id_kecamatan' => $this->kecamatan->id]
        );

        // Admin User
        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_test_infra@melarosa.local'],
            [
                'name' => 'Admin Tester',
                'uuid' => (string) Str::uuid(),
                'password' => bcrypt('password'),
            ]
        );
        $this->adminUser->syncRoles(['admin']);

        // Verifier Kecamatan User
        $this->kecamatanUser = User::firstOrCreate(
            ['email' => 'verif_kec_test@melarosa.local'],
            [
                'name' => 'Verifikator Kecamatan Tester',
                'uuid' => (string) Str::uuid(),
                'password' => bcrypt('password'),
                'id_kecamatan' => $this->kecamatan->id,
            ]
        );
        $this->kecamatanUser->syncRoles(['verifierKecamatan']);

        // Verifier Bappeda User
        $this->bappedaUser = User::firstOrCreate(
            ['email' => 'verif_bappeda_test@melarosa.local'],
            [
                'name' => 'Verifikator Bappeda Tester',
                'uuid' => (string) Str::uuid(),
                'password' => bcrypt('password'),
            ]
        );
        $this->bappedaUser->syncRoles(['verifierBappeda']);
    }

    public function test_can_list_infrastruktur_tipe_and_sumber_dana(): void
    {
        $responseTipe = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/infrastruktur-tipe');
        $responseTipe->assertOk()
            ->assertJsonStructure(['ok', 'data']);

        $responseSd = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/ref-sumber-dana');
        $responseSd->assertOk()
            ->assertJsonStructure(['ok', 'data']);
    }

    public function test_can_crud_plotting_anggaran(): void
    {
        // Store
        $payload = [
            'tahun_anggaran'       => 2026,
            'id_kecamatan'         => $this->kecamatan->id,
            'id_desa'              => $this->desa->id,
            'jenis_bantuan'        => 'BKK',
            'nama_kegiatan'        => 'Pembangunan Jalan Poros Dusun A',
            'sumber_dana'          => 'APBD',
            'target_pagu_anggaran' => 250000000,
            'target_panjang_m'     => 500,
        ];

        $responseStore = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/plotting-anggaran', $payload);

        $responseStore->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nama_kegiatan', 'Pembangunan Jalan Poros Dusun A');

        $plottingId = $responseStore->json('data.id');

        // Show
        $responseShow = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/plotting-anggaran/{$plottingId}");
        $responseShow->assertOk()
            ->assertJsonPath('data.id', $plottingId);

        // Update
        $responseUpdate = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/plotting-anggaran/{$plottingId}", [
                'target_panjang_m' => 600,
            ]);
        $responseUpdate->assertOk()
            ->assertJsonPath('data.target_panjang_m', 600);
    }

    public function test_verification_workflow_state_machine(): void
    {
        // Pastikan tipe jalan_poros ada
        InfrastrukturTipe::firstOrCreate(
            ['kode' => 'jalan_poros'],
            ['nama' => 'Jalan Poros Desa', 'geom_type' => 'LineString', 'is_active' => true]
        );

        // 1. Simpan Segmen sebagai Draft
        $segmenPayload = [
            'tipe_kode'         => 'jalan_poros',
            'namobj'            => 'Segmen Uji Coba State Machine',
            'panjang'           => 350.5,
            'kondisi'           => 'Baik',
            'status_kondisi'    => 'Eksisting',
            'tahun_pembangunan' => 2026,
            'id_kecamatan'      => $this->kecamatan->id,
            'id_desa'           => $this->desa->id,
            'geometry'          => [
                'type' => 'LineString',
                'coordinates' => [
                    [111.8901, -7.1501],
                    [111.8920, -7.1520],
                ],
            ],
        ];

        $responseCreate = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/infrastruktur-segmen', $segmenPayload);

        $responseCreate->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status_verifikasi', 'draft');

        $segmenId = $responseCreate->json('data.id');

        // 2. Desa Mengajukan Verifikasi (submit) -> submitted_desa
        $responseSubmit = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$segmenId}/submit");

        $responseSubmit->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status_verifikasi', 'submitted_desa');

        // 3. Kecamatan Menolak dengan Catatan -> rejected_kecamatan
        $responseRejectKec = $this->actingAs($this->kecamatanUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$segmenId}/verify-kecamatan", [
                'action'  => 'reject',
                'catatan' => 'Perbaiki koordinat ujung segmen.',
            ]);

        $responseRejectKec->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status_verifikasi', 'rejected_kecamatan');

        // 4. Desa Mengajukan Ulang (submit kembali) -> submitted_desa
        $responseResubmit = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$segmenId}/submit");

        $responseResubmit->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status_verifikasi', 'submitted_desa');

        // 5. Kecamatan Menyetujui -> verified_kecamatan
        $responseApproveKec = $this->actingAs($this->kecamatanUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$segmenId}/verify-kecamatan", [
                'action'  => 'approve',
                'catatan' => 'Sudah sesuai cek lapangan.',
            ]);

        $responseApproveKec->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status_verifikasi', 'verified_kecamatan');

        // 6. Bappeda Menyetujui Final -> verified_bappeda
        $responseApproveBappeda = $this->actingAs($this->bappedaUser)
            ->postJson("/api/v1/admin/infrastruktur-segmen/{$segmenId}/verify-bappeda", [
                'action'  => 'approve',
                'catatan' => 'Disetujui untuk pembiayaan APBD.',
            ]);

        $responseApproveBappeda->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status_verifikasi', 'verified_bappeda');

        // 7. Cleanup
        InfrastrukturSegmen::where('id', $segmenId)->delete();
    }

    public function test_monitoring_realisasi_with_items_and_revisi(): void
    {
        $plotting = PlottingAnggaran::create([
            'id'                   => (string) Str::uuid(),
            'tahun_anggaran'       => 2026,
            'id_kecamatan'         => $this->kecamatan->id,
            'id_desa'              => $this->desa->id,
            'jenis_bantuan'        => 'BKK',
            'nama_kegiatan'        => 'Kegiatan Test Monitoring',
            'sumber_dana'          => 'APBD',
            'target_pagu_anggaran' => 100000000,
            'target_panjang_m'     => 200,
            'user_id'              => $this->adminUser->uuid,
        ]);

        $segmen = new InfrastrukturSegmen();
        $segmen->id = (string) Str::uuid();
        $segmen->tipe_kode = 'jalan_poros';
        $segmen->namobj = 'Segmen Test Monitoring';
        $segmen->id_kecamatan = $this->kecamatan->id;
        $segmen->id_desa = $this->desa->id;
        $segmen->panjang = 200;
        $segmen->user_id = $this->adminUser->uuid;
        $segmen->created_by = $this->adminUser->uuid;
        $segmen->created_by_role = 'admin';
        $segmen->status_verifikasi = 'verified_bappeda';
        $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"LineString\",\"coordinates\":[[111.89, -7.15],[111.90, -7.16]]}'), 4326)");
        $segmen->save();

        // 1. Buat Berita Acara Realisasi
        $baPayload = [
            'nomor_ba'          => 'BA-TEST-' . Str::random(6),
            'id_plotting'       => $plotting->id,
            'id_kecamatan'      => $this->kecamatan->id,
            'id_desa'           => $this->desa->id,
            'tahun_anggaran'    => 2026,
            'sumber_dana'       => 'APBD',
            'rencana_panjang'   => 200,
            'realisasi_panjang' => 195.5,
            'status'            => 'draft',
            'segmen_ids'        => [$segmen->id],
        ];

        $responseStore = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/monitoring-realisasi', $baPayload);

        $responseStore->assertStatus(201)
            ->assertJsonPath('ok', true);

        $baId = $responseStore->json('data.id');

        // 2. Buat Revisi Snapshot
        $responseRevisi = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/monitoring-realisasi/{$baId}/revisi", [
                'catatan_revisi' => 'Ada koreksi pengukuran ulang fisik lapangan.',
            ]);

        $responseRevisi->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'reverted');

        // 3. Detail BA memuat riwayat revisi
        $responseShow = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/monitoring-realisasi/{$baId}");

        $responseShow->assertOk()
            ->assertJsonCount(1, 'data.revisions');

        // Cleanup
        MonitoringRealisasi::where('id', $baId)->delete();
        $segmen->delete();
        $plotting->delete();
    }
}

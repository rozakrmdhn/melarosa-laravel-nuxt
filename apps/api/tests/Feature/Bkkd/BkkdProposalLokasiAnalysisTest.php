<?php

namespace Tests\Feature\Bkkd;

use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\BkkdProposal;
use App\Models\BkkdProposalLokasi;
use App\Models\InfrastrukturSegmen;
use App\Models\RefBkkdBidang;
use App\Models\RefOpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BkkdProposalLokasiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $bappedaUser;
    protected User $kecamatanUser;
    protected BatasWilayahKecamatan $kecamatan1;
    protected BatasWilayahKecamatan $kecamatan2;
    protected BatasWilayahDesa $desa1;
    protected BatasWilayahDesa $desa2;
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
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        $bappedaRole = Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);
        $bappedaRole->syncPermissions($permissions);

        $kecamatanRole = Role::firstOrCreate(['name' => 'verifierKecamatan', 'guard_name' => 'web']);
        $kecamatanRole->syncPermissions(['bkkd-proposal-view']);

        $this->kecamatan1 = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522010],
            ['nama_kecamatan' => 'Kecamatan Bojonegoro']
        );

        $this->kecamatan2 = BatasWilayahKecamatan::firstOrCreate(
            ['id' => 3522020],
            ['nama_kecamatan' => 'Kecamatan Kapas']
        );

        $this->desa1 = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522010001],
            ['nama_desa' => 'Desa Sukorejo', 'id_kecamatan' => $this->kecamatan1->id]
        );

        $this->desa2 = BatasWilayahDesa::firstOrCreate(
            ['id' => 3522020001],
            ['nama_desa' => 'Desa Plesungan', 'id_kecamatan' => $this->kecamatan2->id]
        );

        $opd = RefOpd::create([
            'kode' => 'OPD-PU',
            'nama' => 'Dinas PU Bina Marga',
        ]);

        $this->bidang = RefBkkdBidang::create([
            'nama' => 'Infrastruktur Jalan',
            'id_opd_default' => $opd->id,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->bappedaUser = User::factory()->create();
        $this->bappedaUser->assignRole('verifierBappeda');

        $this->kecamatanUser = User::factory()->create(['id_kecamatan' => $this->kecamatan2->id]);
        $this->kecamatanUser->assignRole('verifierKecamatan');
    }

    private function createProposal(array $attributes = []): BkkdProposal
    {
        return BkkdProposal::create(array_merge([
            'nomor_registrasi' => 'BKKD/2026/3522010001/' . Str::random(5),
            'tahun_anggaran' => 2026,
            'id_kecamatan' => $this->kecamatan1->id,
            'id_desa' => $this->desa1->id,
            'id_bidang' => $this->bidang->id,
            'judul_kegiatan' => 'Pembangunan Jalan Rabat Beton',
            'nilai_usulan' => 200000000,
            'versi' => 1,
            'created_by' => $this->adminUser->uuid,
        ], $attributes));
    }

    /**
     * Skenario 1: Admin / Verifier Bappeda dapat menambahkan multi-lokasi LineString ke 1 proposal.
     */
    public function test_bappeda_can_add_multiple_linestring_locations_to_single_proposal(): void
    {
        $proposal = $this->createProposal(['tahun_anggaran' => 2027]);

        $geo1 = [
            'type' => 'LineString',
            'coordinates' => [
                [111.8901, -7.1501],
                [111.8920, -7.1520],
            ],
        ];

        $response1 = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Dusun Krajan RT 01',
            'urutan' => 1,
            'panjang_rencana_m' => 300,
            'lebar_m' => 3.0,
            'geojson' => $geo1,
        ]);

        $response1->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nama_lokasi', 'Dusun Krajan RT 01');

        $geo2 = [
            'type' => 'LineString',
            'coordinates' => [
                [111.8930, -7.1530],
                [111.8950, -7.1550],
            ],
        ];

        $response2 = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Dusun Ngemplak RT 04',
            'urutan' => 2,
            'panjang_rencana_m' => 250,
            'lebar_m' => 3.0,
            'geojson' => $geo2,
        ]);

        $response2->assertCreated();

        $this->assertDatabaseCount('bkkd_proposal_lokasi', 2);

        $proposal->refresh();
        $this->assertEquals(2, $proposal->jumlah_lokasi);
        $this->assertGreaterThan(0, (float) $proposal->total_panjang_gis);

        // Uji index endpoint mengembalikan GeoJSON FeatureCollection
        $listResponse = $this->actingAs($this->bappedaUser)->getJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi");
        $listResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('geojson.type', 'FeatureCollection')
            ->assertJsonCount(2, 'geojson.features');
    }

    /**
     * Skenario 2: Analisa tumpang tindih mendeteksi "konfirmasi_belum_tuntas"
     * ketika lokasi 2027 beririsan dengan usulan 2026 yang realisasi fisiknya belum tuntas.
     */
    public function test_spatial_analysis_flags_konfirmasi_belum_tuntas_when_overlapping_2026_proposal_has_incomplete_realization(): void
    {
        // 1. Buat proposal 2026 dengan lokasi
        $proposal2026 = $this->createProposal([
            'tahun_anggaran' => 2026,
            'nomor_registrasi' => 'BKKD/2026/3522010001/001',
        ]);

        $geoShared = [
            'type' => 'LineString',
            'coordinates' => [
                [111.8901, -7.1501],
                [111.8930, -7.1530],
                [111.8960, -7.1560],
            ],
        ];

        $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2026->id}/lokasi", [
            'nama_lokasi' => 'Ruas Dusun Krajan 2026',
            'panjang_rencana_m' => 500,
            'geojson' => $geoShared,
        ])->assertCreated();

        // 2. Buat proposal 2027 pada desa yang sama dengan lokasi beririsan
        $proposal2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'nomor_registrasi' => 'BKKD/2027/3522010001/001',
        ]);

        $response2027 = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi", [
            'nama_lokasi' => 'Lanjutan Ruas Krajan 2027',
            'panjang_rencana_m' => 300,
            'geojson' => $geoShared,
        ]);

        $response2027->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'konfirmasi_belum_tuntas')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', true);

        $proposal2027->refresh();
        $this->assertEquals('konfirmasi_belum_tuntas', $proposal2027->status_analisa_spasial);
    }

    /**
     * Skenario 3: Analisa tumpang tindih mendeteksi "duplikasi_tuntas"
     * ketika lokasi 2027 beririsan dengan usulan 2026 yang realisasi fisiknya sudah 100% terbangun.
     */
    public function test_spatial_analysis_flags_duplikasi_tuntas_when_overlapping_2026_proposal_is_fully_realized(): void
    {
        $proposal2026 = $this->createProposal([
            'tahun_anggaran' => 2026,
            'nomor_registrasi' => 'BKKD/2026/3522010001/002',
        ]);

        $geoShared = [
            'type' => 'LineString',
            'coordinates' => [
                [111.8800, -7.1400],
                [111.8830, -7.1430],
            ],
        ];

        $resLokasi = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2026->id}/lokasi", [
            'nama_lokasi' => 'Ruas Tuntas 2026',
            'panjang_rencana_m' => 400,
            'geojson' => $geoShared,
        ]);
        $resLokasi->assertCreated();
        $panjangGis2026 = $resLokasi->json('data.panjang_gis_m');

        // Buat record realisasi fisik di infrastruktur_segmen yang selesai tuntas (panjang sama)
        \App\Models\InfrastrukturTipe::firstOrCreate(
            ['kode' => 'JALAN'],
            ['nama' => 'Jalan Poros', 'geom_type' => 'LineString', 'is_active' => true]
        );

        $segmen = new InfrastrukturSegmen();
        $segmen->id = (string) Str::uuid();
        $segmen->tipe_kode = 'JALAN';
        $segmen->id_desa = $this->desa1->id;
        $segmen->id_kecamatan = $this->kecamatan1->id;
        $segmen->panjang = $panjangGis2026;
        $segmen->lebar = 3.5;
        $segmen->status_verifikasi = \App\Enums\StatusVerifikasi::VerifiedBappeda;
        $segmen->kondisi = \App\Enums\KondisiSegmen::Baik;
        $segmen->geom = json_encode($geoShared);
        $segmen->save();

        // Ajukan di tahun 2027 pada titik yang sama persis
        $proposal2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'nomor_registrasi' => 'BKKD/2027/3522010001/002',
        ]);

        $response2027 = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi", [
            'nama_lokasi' => 'Pengajuan Ulang Titik Sama 2027',
            'panjang_rencana_m' => 400,
            'geojson' => $geoShared,
        ]);

        $response2027->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'duplikasi_tuntas');

        $proposal2027->refresh();
        $this->assertEquals('duplikasi_tuntas', $proposal2027->status_analisa_spasial);
    }

    /**
     * Skenario 4: Analisa tumpang tindih mendeteksi "aman"
     * ketika lokasi 2027 tidak beririsan dengan usulan tahun-tahun sebelumnya.
     */
    public function test_spatial_analysis_flags_aman_when_location_does_not_overlap_previous_years(): void
    {
        $proposal2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'nomor_registrasi' => 'BKKD/2027/3522010001/003',
        ]);

        $geoBaru = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9500, -7.2100],
                [111.9550, -7.2150],
            ],
        ];

        $response = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi", [
            'nama_lokasi' => 'Ruas Baru Dusun Glagah',
            'panjang_rencana_m' => 600,
            'geojson' => $geoBaru,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'aman')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', false);

        $proposal2027->refresh();
        $this->assertEquals('aman', $proposal2027->status_analisa_spasial);
    }

    /**
     * Skenario 5: Bappeda dapat mencatat hasil konfirmasi Pemerintah Desa
     * terkait alasan pengajuan ulang usulan yang belum tuntas.
     */
    public function test_bappeda_can_record_village_confirmation_notes_for_incomplete_previous_year_proposal(): void
    {
        $proposal = $this->createProposal(['tahun_anggaran' => 2027]);

        $response = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/konfirmasi-desa", [
            'catatan_konfirmasi_desa' => 'Pemerintah Desa mengonfirmasi bahwa pembangunan 2026 baru selesai 300m karena kendala cuaca. Usulan 2027 adalah penuntasan sisa 200m.',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.catatan_konfirmasi_desa', 'Pemerintah Desa mengonfirmasi bahwa pembangunan 2026 baru selesai 300m karena kendala cuaca. Usulan 2027 adalah penuntasan sisa 200m.')
            ->assertJsonPath('data.dikonfirmasi_oleh', $this->bappedaUser->name);

        $proposal->refresh();
        $this->assertNotNull($proposal->catatan_konfirmasi_desa);
        $this->assertEquals($this->bappedaUser->uuid, $proposal->dikonfirmasi_oleh);
        $this->assertNotNull($proposal->dikonfirmasi_pada);
    }

    /**
     * Skenario 6: User verifier kecamatan tidak dapat mengubah lokasi proposal di kecamatan lain (RBAC).
     */
    public function test_unauthorized_verifier_kecamatan_cannot_modify_other_kecamatan_proposal_locations(): void
    {
        // proposal berada di kecamatan1, sedangkan kecamatanUser terikat ke kecamatan2
        $proposal = $this->createProposal(['id_kecamatan' => $this->kecamatan1->id]);

        $response = $this->actingAs($this->kecamatanUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Ruas Ilegal',
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => [
                    [111.8901, -7.1501],
                    [111.8920, -7.1520],
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    /**
     * Skenario 7: Saat menambahkan proposal baru dengan koordinat (awal-akhir / GeoJSON),
     * otomatis membentuk lokasi usulan dan menganalisis terhadap 3 lapis:
     * 1) Usulan Tahun T-1, 2) Infrastruktur Segmen, dan 3) Koridor Jalan Poros Desa.
     */
    public function test_creating_proposal_with_coordinates_auto_analyzes_against_prev_year_infrastruktur_segmen_and_jalan_poros_desa(): void
    {
        $geoCorridor = [
            'type' => 'LineString',
            'coordinates' => [
                [111.8901, -7.1501],
                [111.8930, -7.1530],
            ],
        ];

        // 1. Siapkan master Jalan Poros Desa pada koridor yang sama
        $jalanPorosId = (string) Str::uuid();
        \App\Models\JalanPorosDesa::unguard();
        $jalanPoros = \App\Models\JalanPorosDesa::create([
            'id' => $jalanPorosId,
            'kode_ruas' => 101,
            'nama_ruas' => 'Ruas Poros Sukorejo - Krajan',
            'desa' => $this->desa1->nama_desa,
            'kecamatan' => $this->kecamatan1->nama_kecamatan,
            'panjang' => 800,
            'lebar' => 4.0,
            'perkerasan' => 'Beton',
            'kondisi' => 'Rusak Berat',
            'status_eksisting' => 'Jalan Desa',
            'id_desa' => $this->desa1->id,
            'id_kecamatan' => $this->kecamatan1->id,
            'geom' => json_encode($geoCorridor),
        ]);
        \App\Models\JalanPorosDesa::reguard();

        // 2. Siapkan Usulan Tahun T-1 (2026) pada koridor yang sama
        $proposal2026 = $this->createProposal([
            'tahun_anggaran' => 2026,
            'nomor_registrasi' => 'BKKD/2026/3522010001/099',
        ]);
        $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2026->id}/lokasi", [
            'nama_lokasi' => 'Tahap 1 Poros Sukorejo 2026',
            'panjang_rencana_m' => 500,
            'geojson' => $geoCorridor,
        ])->assertCreated();

        // 3. Siapkan Realisasi Fisik Infrastruktur Segmen (baru terbangun sebagian, misal 200m)
        \App\Models\InfrastrukturTipe::firstOrCreate(
            ['kode' => 'JALAN'],
            ['nama' => 'Jalan Poros', 'geom_type' => 'LineString', 'is_active' => true]
        );
        $segmen = new InfrastrukturSegmen();
        $segmen->id = (string) Str::uuid();
        $segmen->tipe_kode = 'JALAN';
        $segmen->namobj = 'Realisasi Rigid Beton Sukorejo 2026';
        $segmen->id_desa = $this->desa1->id;
        $segmen->id_kecamatan = $this->kecamatan1->id;
        $segmen->panjang = 200;
        $segmen->lebar = 4.0;
        $segmen->tahun_pembangunan = 2026;
        $segmen->sumber_dana = 'BKKD';
        $segmen->status_verifikasi = \App\Enums\StatusVerifikasi::VerifiedBappeda;
        $segmen->kondisi = \App\Enums\KondisiSegmen::Baik;
        $segmen->geom = json_encode([
            'type' => 'LineString',
            'coordinates' => [
                [111.8901, -7.1501],
                [111.8915, -7.1515],
            ],
        ]);
        $segmen->save();

        // 4. Tambahkan Proposal Baru Tahun 2027 langsung dengan koordinat awal & akhir
        $response = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/proposal', [
            'tahun_anggaran' => 2027,
            'id_kecamatan' => $this->kecamatan1->id,
            'id_desa' => $this->desa1->id,
            'id_bidang' => $this->bidang->id,
            'judul_kegiatan' => 'Pembangunan Jalan Rigid Beton Poros Sukorejo Lanjutan',
            'lokasi_kegiatan' => 'Dusun Krajan RT 01 - RT 04',
            'panjang' => 450,
            'lebar' => 4.0,
            'nilai_usulan' => 350000000,
            'latitude' => -7.1501,
            'longitude' => 111.8901,
            'latitude_akhir' => -7.1530,
            'longitude_akhir' => 111.8930,
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.jumlah_lokasi', 1)
            ->assertJsonPath('data.status_analisa_spasial', 'konfirmasi_belum_tuntas')
            ->assertJsonPath('data.lokasi.0.id_jalan_poros', $jalanPoros->id)
            ->assertJsonPath('data.lokasi.0.ringkasan_analisa.terdeteksi_overlap_usulan', true)
            ->assertJsonPath('data.lokasi.0.ringkasan_analisa.terdeteksi_overlap_segmen', true)
            ->assertJsonPath('data.lokasi.0.ringkasan_analisa.terhubung_jalan_poros', true)
            ->assertJsonPath('data.lokasi.0.ringkasan_analisa.referensi_tahun_lalu.0.nomor_registrasi', 'BKKD/2026/3522010001/099')
            ->assertJsonPath('data.lokasi.0.ringkasan_analisa.referensi_infrastruktur_segmen.0.namobj', 'Realisasi Rigid Beton Sukorejo 2026')
            ->assertJsonPath('data.lokasi.0.ringkasan_analisa.referensi_jalan_poros.0.nama_ruas', 'Ruas Poros Sukorejo - Krajan');
    }

    /**
     * Skenario 8: Input titik awal & akhir mengekstrak seluruh titik belokan (vertex) dari Jalan Poros Desa
     * (bukan sekadar garis lurus 2 titik), dan jika bersimpangan melintasi 2 Jalan Poros Desa,
     * otomatis dipecah menjadi 2 lokasi LineString berdasarkan masing-masing Jalan Poros Desa.
     */
    public function test_coordinates_extract_curved_road_geometry_and_split_into_two_locations_when_crossing_two_jalan_poros_desa(): void
    {
        \App\Models\JalanPorosDesa::unguard();

        // Ruas Poros 1: Melengkung dari Barat ke Titik Persimpangan [112.0340, -7.1550] (4 vertex)
        $poros1 = \App\Models\JalanPorosDesa::create([
            'id' => (string) Str::uuid(),
            'kode_ruas' => 201,
            'nama_ruas' => 'Poros Dusun Candi',
            'desa' => $this->desa1->nama_desa,
            'kecamatan' => $this->kecamatan1->nama_kecamatan,
            'panjang' => 450,
            'lebar' => 4.0,
            'perkerasan' => 'Beton',
            'kondisi' => 'Rusak Berat',
            'status_eksisting' => 'Jalan Desa',
            'id_desa' => $this->desa1->id,
            'id_kecamatan' => $this->kecamatan1->id,
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [112.0310, -7.1550],
                    [112.0320, -7.1542], // Belokan 1
                    [112.0330, -7.1556], // Belokan 2
                    [112.0340, -7.1550], // Titik simpang dengan Poros 2
                ],
            ]),
        ]);

        // Ruas Poros 2: Bersimpangan di [112.0340, -7.1550] lalu belok ke Tenggara (4 vertex)
        $poros2 = \App\Models\JalanPorosDesa::create([
            'id' => (string) Str::uuid(),
            'kode_ruas' => 202,
            'nama_ruas' => 'Poros Dusun Nglarangan',
            'desa' => $this->desa1->nama_desa,
            'kecamatan' => $this->kecamatan1->nama_kecamatan,
            'panjang' => 420,
            'lebar' => 3.5,
            'perkerasan' => 'Aspal',
            'kondisi' => 'Rusak Sedang',
            'status_eksisting' => 'Jalan Desa',
            'id_desa' => $this->desa1->id,
            'id_kecamatan' => $this->kecamatan1->id,
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [112.0340, -7.1550], // Titik simpang dengan Poros 1
                    [112.0348, -7.1558], // Belokan 1
                    [112.0355, -7.1565], // Belokan 2
                    [112.0365, -7.1570],
                ],
            ]),
        ]);

        \App\Models\JalanPorosDesa::reguard();

        // Ajukan proposal hanya dengan Titik Awal (di Poros 1) dan Titik Akhir (di Poros 2)
        $response = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/proposal', [
            'tahun_anggaran' => 2027,
            'id_kecamatan' => $this->kecamatan1->id,
            'id_desa' => $this->desa1->id,
            'id_bidang' => $this->bidang->id,
            'judul_kegiatan' => 'Pembangunan Jalan Poros Candi - Nglarangan',
            'lokasi_kegiatan' => 'RT 004 Dusun Candi s.d. RT 002 Dusun Nglarangan',
            'panjang' => 750,
            'lebar' => 4.0,
            'nilai_usulan' => 500000000,
            'latitude' => -7.1550,
            'longitude' => 112.0310,
            'latitude_akhir' => -7.1570,
            'longitude_akhir' => 112.0365,
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.jumlah_lokasi', 2);

        $lokasiData = $response->json('data.lokasi');
        $this->assertCount(2, $lokasiData);

        // Lokasi 1 mengikuti Ruas Poros 1 (memiliki > 2 vertex karena mengikuti belokan jalan poros, bukan garis lurus)
        $this->assertEquals($poros1->id, $lokasiData[0]['id_jalan_poros']);
        $this->assertStringContainsString('Poros Dusun Candi', $lokasiData[0]['nama_lokasi']);
        $this->assertGreaterThan(2, count($lokasiData[0]['geojson']['coordinates']));
        $this->assertEquals([112.032, -7.1542], $lokasiData[0]['geojson']['coordinates'][1]);

        // Lokasi 2 mengikuti Ruas Poros 2 (memiliki > 2 vertex karena mengikuti belokan jalan poros)
        $this->assertEquals($poros2->id, $lokasiData[1]['id_jalan_poros']);
        $this->assertStringContainsString('Poros Dusun Nglarangan', $lokasiData[1]['nama_lokasi']);
        $this->assertGreaterThan(2, count($lokasiData[1]['geojson']['coordinates']));
        $this->assertEquals([112.0348, -7.1558], $lokasiData[1]['geojson']['coordinates'][1]);
    }

    /**
     * Skenario 9: Endpoint generate-route memprioritaskan Jalan Poros Desa jika masuk koridor,
     * dan menggunakan OpenRouteService API jika titik berada di luar Jalan Poros Desa,
     * serta langsung menghasilkan analisa terhadap jalan_porosdesa & infrastruktur_segmen.
     */
    public function test_generate_route_endpoint_uses_jalan_porosdesa_and_falls_back_to_openrouteservice_api(): void
    {
        \Illuminate\Support\Facades\Config::set('services.openrouteservice.key', 'test-ors-api-key');
        \Illuminate\Support\Facades\Config::set('services.openrouteservice.enable_in_tests', true);

        \Illuminate\Support\Facades\Http::fake([
            'api.openrouteservice.org/*' => \Illuminate\Support\Facades\Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => [
                                [111.9800, -7.2500],
                                [111.9812, -7.2508],
                                [111.9825, -7.2518],
                                [111.9840, -7.2530],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 1. Uji di luar koridor jalan_porosdesa -> otomatis memakai OpenRouteService API
        $orsResponse = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/generate-route', [
            'id_desa' => $this->desa1->id,
            'tahun_anggaran' => 2027,
            'nama_lokasi' => 'Rute Lingkungan via OpenRouteService',
            'latitude' => -7.2500,
            'longitude' => 111.9800,
            'latitude_akhir' => -7.2530,
            'longitude_akhir' => 111.9840,
        ]);

        $orsResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('jumlah_lokasi', 1)
            ->assertJsonPath('data.sumber_rute', 'openrouteservice')
            ->assertJsonCount(4, 'data.geojson.coordinates');

        // 2. Uji pembuatan proposal langsung tanpa nomor_surat_desa & tanggal_surat_desa dengan multi-lokasi
        $createStreamlined = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/proposal', [
            'tahun_anggaran' => 2027,
            'id_kecamatan' => $this->kecamatan1->id,
            'id_desa' => $this->desa1->id,
            'id_bidang' => $this->bidang->id,
            'judul_kegiatan' => 'Pembangunan Jalan Lingkungan Multi-Segmen',
            'lokasi' => [
                [
                    'nama_lokasi' => 'Segmen 1 Lingkungan Barat',
                    'sumber_rute' => 'openrouteservice',
                    'geojson' => $orsResponse->json('data.geojson'),
                    'lebar_m' => 3.5,
                ],
                [
                    'nama_lokasi' => 'Segmen 2 Lingkungan Timur',
                    'latitude' => -7.2530,
                    'longitude' => 111.9840,
                    'latitude_akhir' => -7.2550,
                    'longitude_akhir' => 111.9860,
                    'lebar_m' => 3.5,
                ],
            ],
        ]);

        $createStreamlined->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.jumlah_lokasi', 2);
    }

    public function test_manual_geometry_reshape_skips_openrouteservice_and_preserves_vertices(): void
    {
        config([
            'services.openrouteservice.key' => 'dummy-ors-api-key',
            'services.openrouteservice.enable_in_tests' => true,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([], 500),
        ]);

        $manualGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9810, -7.2510],
                [111.9822, -7.2516],
                [111.9835, -7.2528],
            ],
        ];

        // 1. Preview analisa spasial setelah reshape manual tidak boleh memanggil OpenRouteService
        $previewRes = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/generate-route', [
            'id_desa' => $this->desa1->id,
            'tahun_anggaran' => 2027,
            'nama_lokasi' => 'Koridor Gang Barat Hasil Reshape',
            'sumber_rute' => 'manual',
            'preserve_manual_geometry' => true,
            'geojson' => $manualGeo,
        ]);

        $previewRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.sumber_rute', 'manual')
            ->assertJsonCount(3, 'data.geojson.coordinates');

        // 2. Preview 2 titik lurus manual dengan skip_ors=true tidak memanggil OpenRouteService
        $straightGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9810, -7.2510],
                [111.9840, -7.2535],
            ],
        ];

        $straightRes = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/generate-route', [
            'id_desa' => $this->desa1->id,
            'tahun_anggaran' => 2027,
            'nama_lokasi' => 'Garis Lurus Manual',
            'sumber_rute' => 'manual',
            'preserve_manual_geometry' => true,
            'skip_ors' => true,
            'geojson' => $straightGeo,
        ]);

        $straightRes->assertOk()
            ->assertJsonPath('data.sumber_rute', 'manual')
            ->assertJsonCount(2, 'data.geojson.coordinates');

        // 3. Simpan proposal dengan geometri manual mempertahankan vertex asli dan sumber_rute = 'manual'
        $createRes = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/proposal', [
            'tahun_anggaran' => 2027,
            'id_kecamatan' => $this->kecamatan1->id,
            'id_desa' => $this->desa1->id,
            'id_bidang' => $this->bidang->id,
            'judul_kegiatan' => 'Pembangunan Gang Hasil Reshape Manual',
            'lokasi' => [
                [
                    'nama_lokasi' => 'Segmen Manual 3 Titik',
                    'sumber_rute' => 'manual',
                    'geojson' => $manualGeo,
                    'lebar_m' => 3.0,
                ],
            ],
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.jumlah_lokasi', 1);

        $savedLokasi = BkkdProposalLokasi::first();
        $this->assertNotNull($savedLokasi);
        $this->assertSame('manual', $savedLokasi->ringkasan_analisa['sumber_rute'] ?? null);

        // 4. Tambah lokasi ke-2 lalu update HANYA lokasi ke-1 lewat endpoint per-lokasi tanpa mengubah lokasi ke-2 atau hit ORS
        $proposalId = $createRes->json('data.id');
        $addSecondRes = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposalId}/lokasi", [
            'nama_lokasi' => 'Segmen Manual Kedua',
            'urutan' => 2,
            'panjang_rencana_m' => 150,
            'lebar_m' => 3.0,
            'sumber_rute' => 'manual',
            'geojson' => $straightGeo,
        ]);
        $addSecondRes->assertCreated();
        $secondId = $addSecondRes->json('data.id');

        $reshapedFirstGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9810, -7.2510],
                [111.9818, -7.2519],
                [111.9829, -7.2524],
                [111.9838, -7.2531],
            ],
        ];
        $updateSingleRes = $this->actingAs($this->bappedaUser)->putJson("/api/v1/admin/bkkd/proposal/{$proposalId}/lokasi/{$savedLokasi->id}", [
            'nama_lokasi' => 'Segmen Manual 4 Titik Revisi',
            'panjang_rencana_m' => 420,
            'lebar_m' => 3.5,
            'sumber_rute' => 'manual',
            'geojson' => $reshapedFirstGeo,
        ]);

        $updateSingleRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nama_lokasi', 'Segmen Manual 4 Titik Revisi')
            ->assertJsonPath('data.panjang_rencana_m', 420)
            ->assertJsonPath('data.ringkasan_analisa.sumber_rute', 'manual');

        $this->assertDatabaseHas('bkkd_proposal_lokasi', [
            'id' => $secondId,
            'nama_lokasi' => 'Segmen Manual Kedua',
        ]);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_external_db_qgis_geometry_edit_recalculates_length_and_syncs_pending_proposals(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([], 500),
        ]);

        $proposal = $this->createProposal(['tahun_anggaran' => 2027]);

        $lokasi = BkkdProposalLokasi::create([
            'id_proposal' => $proposal->id,
            'urutan' => 1,
            'nama_lokasi' => 'Ruas Edit Eksternal QGIS',
            'panjang_gis_m' => 100.0,
            'status_analisa_spasial' => 'aman',
            'ringkasan_analisa' => ['sumber_rute' => 'manual'],
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [111.9800, -7.2500],
                    [111.9810, -7.2500],
                ],
            ]),
        ]);

        // Simulasikan edit geometri langsung di DB/QGIS (panjang diperbesar drastis tanpa update panjang_gis_m)
        $editedLongGeo = json_encode([
            'type' => 'LineString',
            'coordinates' => [
                [111.9800, -7.2500],
                [111.9890, -7.2500],
            ],
        ]);
        \Illuminate\Support\Facades\DB::table('bkkd_proposal_lokasi')
            ->where('id', $lokasi->id)
            ->update([
                'geom' => $editedLongGeo,
                'status_analisa_spasial' => 'belum_dianalisa',
            ]);

        $syncRes = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/analisa-spasial/sync-pending', [
            'tahun_anggaran' => 2027,
            'only_pending' => true,
        ]);

        $syncRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.processed_proposals', 1)
            ->assertJsonPath('data.processed_locations', 1);

        $lokasi->refresh();
        $proposal->refresh();

        $this->assertGreaterThan(900.0, (float) $lokasi->panjang_gis_m);
        $this->assertNotEquals('belum_dianalisa', $lokasi->status_analisa_spasial);
        $this->assertGreaterThan(900.0, (float) $proposal->total_panjang_gis);

        // Uji Artisan command untuk pemrosesan terjadwal/CLI
        $this->artisan('bkkd:reanalyze-spatial', ['--tahun' => 2027, '--all' => true])
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_edit_regenerate_with_id_lokasi_collapses_segments_and_prevents_double_create(): void
    {
        $proposal = $this->createProposal(['tahun_anggaran' => 2027]);

        $lokasi = BkkdProposalLokasi::create([
            'id_proposal' => $proposal->id,
            'urutan' => 1,
            'nama_lokasi' => 'Ruas Awal Edit',
            'panjang_gis_m' => 120.0,
            'status_analisa_spasial' => 'aman',
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [111.9800, -7.2500],
                    [111.9810, -7.2500],
                ],
            ]),
        ]);

        // Tambah 2 ruas Jalan Poros Desa yang bersimpangan untuk menguji skenario 2 ruas
        \App\Models\JalanPorosDesa::unguard();
        \App\Models\JalanPorosDesa::create([
            'id' => (string) Str::uuid(),
            'id_desa' => $this->desa1->id,
            'kode_ruas' => 101,
            'nama_ruas' => 'Poros Edit A',
            'panjang' => 300,
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [111.8800, -7.1500],
                    [111.8830, -7.1500],
                ],
            ]),
        ]);

        \App\Models\JalanPorosDesa::create([
            'id' => (string) Str::uuid(),
            'id_desa' => $this->desa1->id,
            'kode_ruas' => 102,
            'nama_ruas' => 'Poros Edit B',
            'panjang' => 300,
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [111.8830, -7.1500],
                    [111.8830, -7.1530],
                ],
            ]),
        ]);
        \App\Models\JalanPorosDesa::reguard();

        // Saat mode Edit mengirim id_lokasi + single_location_mode, generate-route tetap mengembalikan 1 item ber-id sama
        $previewRes = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/generate-route', [
            'id_desa' => $this->desa1->id,
            'tahun_anggaran' => 2027,
            'id_lokasi' => $lokasi->id,
            'single_location_mode' => true,
            'nama_lokasi' => 'Ruas Edit Persimpangan',
            'latitude' => -7.1500,
            'longitude' => 111.8805,
            'latitude_akhir' => -7.1525,
            'longitude_akhir' => 111.8830,
        ]);

        $previewRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('jumlah_lokasi', 1)
            ->assertJsonPath('data.id', $lokasi->id);

        // Simpan perubahan per id_lokasi juga hanya memperbarui baris tersebut tanpa double create
        $putRes = $this->actingAs($this->bappedaUser)->putJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi/{$lokasi->id}", [
            'nama_lokasi' => 'Ruas Edit Persimpangan',
            'geojson' => $previewRes->json('data.geojson'),
            'sumber_rute' => 'jalan_porosdesa',
        ]);

        $putRes->assertOk()->assertJsonPath('data.id', $lokasi->id);
        $this->assertEquals(1, BkkdProposalLokasi::where('id_proposal', $proposal->id)->count());
    }

    public function test_spatial_analysis_detects_overlap_with_future_t_plus_n_and_same_year_proposals(): void
    {
        // 1. Desa sudah masuk perencanaan Tahun 2028 (T + 1)
        $proposalFuture2028 = $this->createProposal([
            'tahun_anggaran' => 2028,
            'judul_kegiatan' => 'Perencanaan Betonisasi Dusun Mawar Tahun 2028',
        ]);

        $sharedGeoFuture = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9500, -7.2200],
                [111.9509, -7.2200],
                [111.9518, -7.2200],
            ],
        ];

        $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposalFuture2028->id}/lokasi", [
            'nama_lokasi' => 'Ruas Dusun Mawar (Rencana 2028)',
            'geojson' => $sharedGeoFuture,
        ])->assertCreated();

        // 2. Desa mengusulkan kembali di Tahun 2027 pada ruas yang sama (beririsan dengan perencanaan 2028)
        $proposal2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'judul_kegiatan' => 'Usulan Kembali Dusun Mawar 2027',
        ]);

        $responseFutureOverlap = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal2027->id}/lokasi", [
            'nama_lokasi' => 'Ruas Dusun Mawar (Ajuan 2027)',
            'geojson' => $sharedGeoFuture,
        ]);

        $responseFutureOverlap->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'konfirmasi_belum_tuntas')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', true)
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.tahun_anggaran', 2028)
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.relasi_tahun', 'perencanaan_berikutnya');

        // 3. Desa mengajukan proposal lain pada tahun yang sama (2027) di lokasi yang sama, mengecualikan proposal diri sendiri
        $proposalSameYear2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'judul_kegiatan' => 'Proposal Pengajuan Ganda Tahun Sama 2027',
        ]);

        $responseSameYear = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposalSameYear2027->id}/lokasi", [
            'nama_lokasi' => 'Ruas Dusun Mawar Duplikat 2027',
            'geojson' => $sharedGeoFuture,
        ]);

        $responseSameYear->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'konfirmasi_belum_tuntas')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', true);

        $refs = collect($responseSameYear->json('data.ringkasan_analisa.referensi_tahun_lalu'));
        $this->assertTrue($refs->contains(fn ($r) => $r['tahun_anggaran'] === 2027 && $r['relasi_tahun'] === 'tahun_sama'));
        $this->assertTrue($refs->contains(fn ($r) => $r['tahun_anggaran'] === 2028 && $r['relasi_tahun'] === 'perencanaan_berikutnya'));
        $this->assertFalse($refs->contains(fn ($r) => $r['id_proposal_lama'] === $proposalSameYear2027->id));

        // 4. Uji preview endpoint dengan id_proposal tidak mendeteksi diri sendiri
        $previewSelfRes = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/generate-route', [
            'id_desa' => $this->desa1->id,
            'id_proposal' => $proposalFuture2028->id,
            'tahun_anggaran' => 2028,
            'nama_lokasi' => 'Ruas Baru Tanpa Konflik',
            'latitude' => -7.2900,
            'longitude' => 111.9900,
            'latitude_akhir' => -7.2910,
            'longitude_akhir' => 111.9910,
        ]);

        $previewSelfRes->assertOk()
            ->assertJsonPath('data.status_analisa_spasial', 'aman')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', false);
    }

    public function test_prkpd_and_rkpd_cross_year_pairing_detection(): void
    {
        // Case 1: Usulan PRKPD Tahun + N (2028) disandingkan dengan RKPD Tahun + N (2028)
        $rkpd2028 = $this->createProposal([
            'tahun_anggaran' => 2028,
            'tagging_usulan' => 'RKPD',
            'judul_kegiatan' => 'Ruas RKPD 2028',
        ]);

        $geoPair1 = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9600, -7.2300],
                [111.9609, -7.2300],
                [111.9618, -7.2300],
            ],
        ];

        $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$rkpd2028->id}/lokasi", [
            'nama_lokasi' => 'Ruas Poros RKPD 2028',
            'geojson' => $geoPair1,
        ])->assertCreated();

        $prkpd2028 = $this->createProposal([
            'tahun_anggaran' => 2028,
            'tagging_usulan' => 'PRKPD',
            'judul_kegiatan' => 'Ruas PRKPD 2028',
        ]);

        $resPrkpdVsRkpd = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$prkpd2028->id}/lokasi", [
            'nama_lokasi' => 'Ruas Poros PRKPD 2028',
            'geojson' => $geoPair1,
        ]);

        $resPrkpdVsRkpd->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'konfirmasi_belum_tuntas')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', true)
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.tagging_usulan', 'RKPD')
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.pasangan_tagging', 'prkpd_vs_rkpd');

        // Case 2: Usulan RKPD Tahun + N (2028) disandingkan dengan PRKPD Tahun N (2027)
        $prkpd2027 = $this->createProposal([
            'tahun_anggaran' => 2027,
            'tagging_usulan' => 'PRKPD',
            'judul_kegiatan' => 'Ruas PRKPD 2027',
        ]);

        $geoPair2 = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9700, -7.2400],
                [111.9709, -7.2400],
                [111.9718, -7.2400],
            ],
        ];

        $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$prkpd2027->id}/lokasi", [
            'nama_lokasi' => 'Ruas Poros PRKPD 2027',
            'geojson' => $geoPair2,
        ])->assertCreated();

        $rkpdFuture2028 = $this->createProposal([
            'tahun_anggaran' => 2028,
            'tagging_usulan' => 'RKPD',
            'judul_kegiatan' => 'Ruas RKPD 2028 Cross Check PRKPD 2027',
        ]);

        $resRkpdVsPrkpd = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$rkpdFuture2028->id}/lokasi", [
            'nama_lokasi' => 'Ruas Poros RKPD 2028',
            'geojson' => $geoPair2,
        ]);

        $resRkpdVsPrkpd->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'konfirmasi_belum_tuntas')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_usulan', true)
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.tagging_usulan', 'PRKPD')
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.pasangan_tagging', 'rkpd_vs_prkpd')
            ->assertJsonPath('data.ringkasan_analisa.referensi_tahun_lalu.0.tahun_anggaran', 2027);

        // Case 3: Filter & resource response tagging_usulan
        $listRes = $this->actingAs($this->bappedaUser)->getJson('/api/v1/admin/bkkd/proposal?tahun_anggaran=2028&tagging_usulan=RKPD');
        $listRes->assertOk();
        $ids = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertContains($rkpd2028->id, $ids);
        $this->assertContains($rkpdFuture2028->id, $ids);
        $this->assertNotContains($prkpd2028->id, $ids);
        $this->assertEquals('RKPD', $listRes->json('data.0.tagging_usulan'));
    }

    public function test_spatial_analysis_detects_overlap_with_jalan_kabupaten_luarkota(): void
    {
        $kabGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9850, -7.2550],
                [111.9865, -7.2550],
                [111.9880, -7.2550],
            ],
        ];

        \Illuminate\Support\Facades\DB::table('jalan_kabupaten_luarkota')->insert([
            'id' => 101,
            'geom' => json_encode($kabGeo),
            'nama_ruas' => 'Sumberejo - Kanor',
            'no_ruas' => 10,
            'pangkal' => 'Pertigaan Sumberejo',
            'ujung' => 'Kanor',
            'panjang' => 330.0,
            'klas_ruas' => 'LU',
            'nama_kecamatan' => 'Kanor, Sumberrejo',
            'selected' => false,
            'status_ruas' => 'Jalan Kabupaten',
        ]);

        $proposal = $this->createProposal([
            'tahun_anggaran' => 2027,
            'tagging_usulan' => 'PRKPD',
            'judul_kegiatan' => 'Peningkatan Jalan Berimpit Jalan Kabupaten',
        ]);

        $res = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Segmen Pertigaan Sumberejo',
            'sumber_rute' => 'manual',
            'geojson' => $kabGeo,
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'kewenangan_kabupaten')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_kabupaten', true)
            ->assertJsonPath('data.ringkasan_analisa.referensi_jalan_kabupaten.0.nama_ruas', 'Sumberejo - Kanor')
            ->assertJsonPath('data.ringkasan_analisa.referensi_jalan_kabupaten.0.no_ruas', '10')
            ->assertJsonPath('data.ringkasan_analisa.referensi_jalan_kabupaten.0.klas_ruas', 'LU')
            ->assertJsonPath('data.ringkasan_analisa.referensi_jalan_kabupaten.0.kecamatan', 'Kanor, Sumberrejo');

        $this->assertStringContainsString(
            'aset Desa',
            (string) $res->json('data.ringkasan_analisa.rekomendasi_bappeda')
        );

        $this->assertStringContainsString(
            'Sumberejo - Kanor',
            (string) $res->json('data.ringkasan_analisa.rekomendasi_bappeda')
        );

        $lokRes = $this->actingAs($this->bappedaUser)->getJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi");
        $lokRes->assertOk();
        $this->assertCount(1, $lokRes->json('layers.jalan_kabupaten.features'));
        $this->assertSame('Sumberejo - Kanor', $lokRes->json('layers.jalan_kabupaten.features.0.properties.nama_ruas'));

        // Mode hybrid: geometri proposal tetap GeoJSON penuh, sedangkan layer latar disuplai via Martin MVT
        $hybridRes = $this->actingAs($this->bappedaUser)->getJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi?mode=hybrid");
        $hybridRes->assertOk()
            ->assertJsonPath('layers.mode', 'hybrid')
            ->assertJsonPath('layers.martin_sources.jalan_poros_desa', 'jalan_porosdesa')
            ->assertJsonPath('layers.martin_sources.jalan_kabupaten', 'jalan_kabupaten_luarkota')
            ->assertJsonCount(1, 'geojson.features');

        $hybridRefRes = $this->actingAs($this->bappedaUser)->getJson('/api/v1/admin/bkkd/ref-jalan-poros?id_desa=' . $this->desa1->id . '&mode=hybrid');
        $hybridRefRes->assertOk()
            ->assertJsonPath('layers.mode', 'hybrid')
            ->assertJsonPath('layers.martin_sources.infrastruktur_segmen', 'infrastruktur_segmen');
    }

    public function test_generate_route_supports_dynamic_waypoints_with_reference_only_points(): void
    {
        $res = $this->actingAs($this->bappedaUser)->postJson('/api/v1/admin/bkkd/generate-route', [
            'id_desa' => $this->desa1->id,
            'tahun_anggaran' => 2027,
            'nama_lokasi' => 'Koridor Multi Titik',
            'waypoints' => [
                ['lat' => -7.2800, 'lng' => 111.9900, 'label' => 'Pangkal', 'is_route' => true, 'use_ors' => false],
                ['lat' => -7.2810, 'lng' => 111.9910, 'label' => 'Referensi Saja', 'is_route' => false, 'use_ors' => false],
                ['lat' => -7.2820, 'lng' => 111.9920, 'label' => 'Tengah', 'is_route' => true, 'use_ors' => false],
                ['lat' => -7.2830, 'lng' => 111.9935, 'label' => 'Ujung', 'is_route' => true, 'use_ors' => false],
            ],
        ]);

        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.sumber_rute', 'garis_lurus')
            ->assertJsonCount(3, 'data.geojson.coordinates')
            ->assertJsonCount(4, 'data.titik_sebaran')
            ->assertJsonPath('data.titik_sebaran.1.is_route', false);
    }

    public function test_spatial_analysis_ignores_minor_junction_touch_with_jalan_kabupaten_and_segmen(): void
    {
        // Jalan Kabupaten melintang horizontal di utara
        $kabGeo = [
            'type' => 'LineString',
            'coordinates' => [
                [111.9900, -7.2600],
                [111.9915, -7.2600],
                [111.9930, -7.2600],
            ],
        ];

        \Illuminate\Support\Facades\DB::table('jalan_kabupaten_luarkota')->insert([
            'id' => 102,
            'geom' => json_encode($kabGeo),
            'nama_ruas' => 'Bojonegoro - Padangan',
            'no_ruas' => 11,
            'panjang' => 330.0,
            'klas_ruas' => 'LU',
            'nama_kecamatan' => 'Padangan',
            'selected' => false,
            'status_ruas' => 'Jalan Kabupaten',
        ]);

        // Segmen infrastruktur terbangun juga hanya bersentuhan di ujung pangkal (1 simpul)
        \App\Models\InfrastrukturTipe::firstOrCreate(
            ['kode' => 'JALAN'],
            ['nama' => 'Jalan Poros', 'geom_type' => 'LineString', 'is_active' => true]
        );

        InfrastrukturSegmen::create([
            'id_kecamatan' => $this->kecamatan1->id,
            'id_desa' => $this->desa1->id,
            'tipe_kode' => 'JALAN',
            'namobj' => 'Segmen Simpang Utara',
            'panjang' => 200,
            'kondisi' => 'Baik',
            'tahun_pembangunan' => 2025,
            'geom' => json_encode([
                'type' => 'LineString',
                'coordinates' => [
                    [111.9885, -7.2600],
                    [111.9895, -7.2600],
                    [111.9900, -7.2600],
                ],
            ]),
        ]);

        // Usulan jalan desa 3 titik (~300m) hanya berpangkal pada titik simpang [111.9900, -7.2600] lalu mengarah ke selatan
        $proposal = $this->createProposal([
            'tahun_anggaran' => 2027,
            'tagging_usulan' => 'PRKPD',
            'judul_kegiatan' => 'Pembangunan Jalan Desa Simpang Utara',
        ]);

        $res = $this->actingAs($this->bappedaUser)->postJson("/api/v1/admin/bkkd/proposal/{$proposal->id}/lokasi", [
            'nama_lokasi' => 'Gang Simpang Utara',
            'sumber_rute' => 'manual',
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => [
                    [111.9900, -7.2600],
                    [111.9900, -7.2615],
                    [111.9900, -7.2630],
                ],
            ],
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.status_analisa_spasial', 'aman')
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_kabupaten', false)
            ->assertJsonPath('data.ringkasan_analisa.terdeteksi_overlap_segmen', false)
            ->assertJsonCount(0, 'data.ringkasan_analisa.referensi_jalan_kabupaten')
            ->assertJsonCount(0, 'data.ringkasan_analisa.referensi_infrastruktur_segmen');
    }
}

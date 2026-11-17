# Issue & Blueprint Implementasi: Multi-Lokasi LineString (`bkkd_proposal_lokasi`) & Analisa Tumpang Tindih Spasial Lintas Tahun BKKD

Kembali ke indeks dokumentasi: [docs/README.md](../README.md)

---

> [!IMPORTANT]
> **Panduan Eksekusi untuk Junior Programmer & Model AI Pelaksana**
> Dokumen ini ditulis sebagai *Step-by-Step Implementation Blueprint* yang presisi.
> - Ikuti setiap **Tahap (Task 1 s.d. Task 7)** secara berurutan.
> - Jangan mengubah nama tabel, nama kolom, atau format endpoint yang sudah ditetapkan di dokumen ini.
> - Pastikan kompatibilitas ganda (**PostgreSQL/PostGIS** untuk produksi dan **SQLite in-memory** untuk automated testing `php artisan test`).
> - Setelah selesai mengimplementasikan kode dan test, perbarui status di [docs/README.md](../README.md).

---

## 1. Konteks Bisnis & Tujuan Perubahan

Modul BKKD di dalam aplikasi WebGIS Melarosa **bukan sistem pengajuan berkas e-proposal birokrasi**, melainkan berfungsi sebagai **Spatial Proposal Analyzer (Alat Analisa Spasial Usulan BKKD)** bagi Bappeda:

1. **Relasi `1 Usulan : Banyak Lokasi LineString` (`1:N`)**:
   - Satu usulan kegiatan desa pada tabel `bkkd_proposal` dapat memiliki **banyak titik/ruas lokasi `LineString`** yang disimpan pada tabel anak `bkkd_proposal_lokasi` (misal: Lokasi 1 di Dusun Krajan RT 01, Lokasi 2 di Dusun Ngemplak RT 04).
2. **Evaluasi Usulan Tahun $T$ (misal 2027) terhadap Usulan Tahun $T-1$ (misal 2026) & Realisasi Pembangunan**:
   - Tabel `plotting_anggaran` + `infrastruktur_segmen` menyimpan data **Hasil Realisasi Pembangunan** fisik di lapangan.
   - Tabel `bkkd_proposal` + `bkkd_proposal_lokasi` menyimpan data **Usulan BKKD** (baik yang sudah ditetapkan pada APBD tahun $T-1$ maupun usulan tahun $T$ yang sedang dievaluasi).
   - Ketika lokasi `LineString` usulan tahun $T$ (2027) beririsan (*overlap*) dengan lokasi `LineString` usulan tahun $T-1$ (2026), sistem wajib menghitung berapa meter realisasi fisik (`infrastruktur_segmen`) pada lokasi 2026 tersebut.
   - Hasil analisa ini menjadi **dasar pengambilan keputusan Bappeda untuk melakukan konfirmasi ulang ke Pemerintah Desa**: apakah usulan 2026 di lokasi tersebut memang **belum tuntas realisasinya** sehingga diajukan kembali di 2027, ataukah sudah tuntas 100% (indikasi duplikasi anggaran / *double funding*).

---

## 2. Daftar Pekerjaan (Task Breakdown Checklist)

- [x] **Task 1**: Buat Migration tabel `bkkd_proposal_lokasi` dan kolom agregat spasial pada `bkkd_proposal`. (Selesai & termigrasi di SQLite dan PostgreSQL).
- [x] **Task 2**: Buat Model `BkkdProposalLokasi` dan tambahkan relasi `lokasi()` pada `BkkdProposal`. (Selesai).
- [x] **Task 3**: Buat Service `BkkdSpatialAnalysisService` untuk kalkulasi `ST_Length`, ekstraksi geometri lekukan Jalan Poros Desa, split otomatis jika melintasi 2+ ruas poros desa, dan analisa tumpang tindih spasial 3 lapis (Tahun T-1, Infrastruktur Segmen, Jalan Poros Desa). (Selesai).
- [x] **Task 4**: Buat Controller `BkkdProposalLokasiController`, FormRequest, Resource, dan daftarkan route API. (Selesai).
- [x] **Task 5**: Tulis Automated Feature Tests (`BkkdProposalLokasiAnalysisTest.php`) 8 skenario pengujian dan lolos 100% (57 assertions). (Selesai).
- [x] **Task 6**: Perbarui TypeScript Interfaces (`apps/web/app/types/bkkd.ts`) dan Composable (`apps/web/app/composables/useBkkdApi.ts`). (Selesai).
- [x] **Task 7**: Buat/perbarui komponen UI Nuxt untuk digitasi multi-lokasi `LineString`, peta OpenLayers 4 layer simultan, badge status analisa, dan form catatan konfirmasi desa. (Selesai).

---

## 3. Spesifikasi Teknis Per Tahap

### Task 1: Database Migration (`apps/api/database/migrations/`)

Buat file migration baru: `database/migrations/2026_10_30_000001_create_bkkd_proposal_lokasi_table.php`.

#### A. Penyesuaian Tabel `bkkd_proposal`
Tambahkan kolom ringkasan spasial pada `bkkd_proposal` (gunakan `Schema::hasColumn` agar idempoten):
- `jumlah_lokasi` (`integer`, default `0`)
- `total_panjang_gis` (`decimal(12, 2)`, default `0`)
- `status_analisa_spasial` (`string(40)`, nullable, index) -> nilai: `belum_dianalisa`, `aman`, `lanjutan_ruas`, `konfirmasi_belum_tuntas`, `duplikasi_tuntas`
- `catatan_konfirmasi_desa` (`text`, nullable)
- `dikonfirmasi_oleh` (`uuid`, nullable)
- `dikonfirmasi_pada` (`timestamp`, nullable)

#### B. Skema Tabel Baru `bkkd_proposal_lokasi`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bkkd_proposal')) {
            Schema::table('bkkd_proposal', function (Blueprint $table) {
                if (!Schema::hasColumn('bkkd_proposal', 'jumlah_lokasi')) {
                    $table->integer('jumlah_lokasi')->default(0)->after('lokasi_kegiatan');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'total_panjang_gis')) {
                    $table->decimal('total_panjang_gis', 12, 2)->default(0)->after('jumlah_lokasi');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'status_analisa_spasial')) {
                    $table->string('status_analisa_spasial', 40)->nullable()->index()->after('status');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'catatan_konfirmasi_desa')) {
                    $table->text('catatan_konfirmasi_desa')->nullable()->after('catatan_tapd');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'dikonfirmasi_oleh')) {
                    $table->uuid('dikonfirmasi_oleh')->nullable()->after('catatan_konfirmasi_desa');
                }
                if (!Schema::hasColumn('bkkd_proposal', 'dikonfirmasi_pada')) {
                    $table->timestamp('dikonfirmasi_pada')->nullable()->after('dikonfirmasi_oleh');
                }
            });
        }

        Schema::create('bkkd_proposal_lokasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_proposal')->index();
            $table->uuid('id_jalan_poros')->nullable()->index();
            $table->smallInteger('urutan')->default(1);
            $table->string('nama_lokasi', 255);
            $table->decimal('panjang_rencana_m', 10, 2)->nullable();
            $table->decimal('panjang_gis_m', 10, 2)->default(0);
            $table->decimal('lebar_m', 6, 2)->nullable();
            $table->string('status_analisa_spasial', 40)->default('belum_dianalisa')->index();
            $table->jsonb('ringkasan_analisa')->nullable();
            $table->text('catatan_konfirmasi_desa')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Untuk SQLite testing, simpan WKT/GeoJSON string pada kolom geom jika bukan pgsql
            if (DB::connection()->getDriverName() !== 'pgsql') {
                $table->text('geom')->nullable();
            }

            $table->foreign('id_proposal')
                ->references('id')
                ->on('bkkd_proposal')
                ->onDelete('cascade');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bkkd_proposal_lokasi ADD COLUMN geom geometry(LineString, 4326)');
            DB::statement('CREATE INDEX bkkd_proposal_lokasi_geom_gist ON bkkd_proposal_lokasi USING GIST (geom)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bkkd_proposal_lokasi');

        if (Schema::hasTable('bkkd_proposal')) {
            Schema::table('bkkd_proposal', function (Blueprint $table) {
                $cols = [
                    'jumlah_lokasi',
                    'total_panjang_gis',
                    'status_analisa_spasial',
                    'catatan_konfirmasi_desa',
                    'dikonfirmasi_oleh',
                    'dikonfirmasi_pada',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('bkkd_proposal', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
```

---

### Task 2: Eloquent Model (`apps/api/app/Models/BkkdProposalLokasi.php`)

1. Buat model `App\Models\BkkdProposalLokasi`:
   - Gunakan trait `HasFactory`, `HasUuids`, `Auditable`.
   - Set `$table = 'bkkd_proposal_lokasi'`.
   - Cast `ringkasan_analisa` sebagai `'array'`, `panjang_rencana_m`, `panjang_gis_m`, `lebar_m` sebagai `'decimal:2'`, `urutan` sebagai `'integer'`.
   - Tambahkan relasi:
     - `proposal(): BelongsTo` ke `BkkdProposal::class` (`id_proposal`)
     - `jalanPoros(): BelongsTo` ke `JalanPorosDesa::class` (`id_jalan_poros`)
     - `creator(): BelongsTo` ke `User::class` (`created_by`, `'uuid'`)
   - Tambahkan scope `scopeWithGeoJson(Builder $query)` seperti pada `InfrastrukturSegmen`:
     - Pada `pgsql`: `ST_AsGeoJSON(bkkd_proposal_lokasi.geom) as geojson`, `ST_AsGeoJSON(ST_Centroid(bkkd_proposal_lokasi.geom)) as centroid`
     - Pada `sqlite`: kembalikan kolom `geom as geojson`.
2. Di model `App\Models\BkkdProposal`:
   - Tambahkan kolom baru ke `$fillable` dan `$casts`.
   - Tambahkan relasi:
     ```php
     public function lokasi(): HasMany
     {
         return $this->hasMany(BkkdProposalLokasi::class, 'id_proposal', 'id')->orderBy('urutan');
     }
     ```

---

### Task 3: Service Analisa Spasial (`apps/api/app/Services/BkkdSpatialAnalysisService.php`)

Buat service `App\Services\BkkdSpatialAnalysisService` yang menangani:
1. **Validasi & Konversi GeoJSON `LineString` ke Geometri PostGIS**:
   - Menerima input GeoJSON geometry bertipe `LineString` (minimal 2 titik koordinat `[lng, lat]`).
   - Pada PostgreSQL: simpan menggunakan `ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)` dan hitung `panjang_gis_m` via `ROUND(ST_Length(geom::geography)::numeric, 2)`.
   - Pada SQLite (mode testing): hitung jarak Haversine antar titik koordinat di PHP agar `panjang_gis_m` tetap terisi akurat tanpa ekstensi PostGIS.
2. **Algoritma Analisa Tumpang Tindih (`analisaLokasi(BkkdProposalLokasi $lokasi)` & `analisaProposal(BkkdProposal $proposal)`)**:
   - **Parameter Koridor Buffer**: `$bufferMeter = 5.0` (toleransi pergeseran digitasi garis antar tahun).
   - **Langkah 1: Cari Irisan terhadap Lokasi Usulan Tahun Sebelumnya (`tahun_anggaran < $proposal->tahun_anggaran`) di Desa yang Sama (`id_desa`)**:
     - Query PostGIS:
       ```sql
       SELECT
           l_prev.id AS id_lokasi_lama,
           l_prev.nama_lokasi AS nama_lokasi_lama,
           l_prev.panjang_gis_m AS panjang_usulan_lama_m,
           p_prev.id AS id_proposal_lama,
           p_prev.nomor_registrasi,
           p_prev.tahun_anggaran,
           p_prev.judul_kegiatan,
           p_prev.status AS status_proposal_lama,
           ROUND(
               ST_Length(
                   ST_Intersection(
                       l_prev.geom,
                       ST_Buffer(l_curr.geom::geography, 5)::geometry
                   )::geography
               )::numeric,
               2
           ) AS panjang_irisan_usulan_m
       FROM bkkd_proposal_lokasi l_prev
       JOIN bkkd_proposal p_prev ON p_prev.id = l_prev.id_proposal
       JOIN bkkd_proposal_lokasi l_curr ON l_curr.id = :lokasiId
       WHERE p_prev.id_desa = :idDesa
         AND p_prev.tahun_anggaran < :tahunAnggaran
         AND p_prev.deleted_at IS NULL
         AND p_prev.status NOT IN ('dibatalkan', 'tidak_layak')
         AND ST_Intersects(
             l_prev.geom,
             ST_Buffer(l_curr.geom::geography, 5)::geometry
         )
       ```
   - **Langkah 2: Untuk Setiap Lokasi Usulan Tahun Lalu yang Beririsan, Hitung Realisasi Fisiknya di Tabel `infrastruktur_segmen`**:
     - Query PostGIS terhadap `infrastruktur_segmen` pada desa yang sama (`id_desa`):
       ```sql
       SELECT
           COALESCE(
               ROUND(
                   SUM(
                       ST_Length(
                           ST_Intersection(
                               seg.geom,
                               ST_Buffer(l_prev.geom::geography, 5)::geometry
                           )::geography
                       )
                   )::numeric,
                   2
               ),
               0
           ) AS panjang_terealisasi_m
       FROM infrastruktur_segmen seg
       JOIN bkkd_proposal_lokasi l_prev ON l_prev.id = :idLokasiLama
       WHERE seg.id_desa = :idDesa
         AND ST_Intersects(
             seg.geom,
             ST_Buffer(l_prev.geom::geography, 5)::geometry
         )
       ```
   - **Langkah 3: Tentukan Klasifikasi `status_analisa_spasial`**:
     - Jika **tidak ada** irisan dengan usulan tahun sebelumnya (`panjang_irisan_usulan_m == 0`):
       - Status = `'aman'`.
     - Jika `panjang_irisan_usulan_m > 0` dan `panjang_irisan_usulan_m <= 10.0`:
       - Status = `'lanjutan_ruas'` (hanya bersentuhan ujung-ke-ujung / menyambung segmen).
     - Jika `panjang_irisan_usulan_m > 10.0`:
       - Hitung `$persenRealisasi = ($panjangUsulanLama > 0) ? ($panjangTerealisasi / $panjangUsulanLama) * 100 : 0`.
       - Hitung `$sisaBelumTuntas = max(0, $panjangUsulanLama - $panjangTerealisasi)`.
       - Jika `$persenRealisasi < 90.0` (belum terealisasi atau baru terealisasi sebagian):
         - Status = `'konfirmasi_belum_tuntas'`.
         - Rekomendasi Bappeda: *"Lokasi beririsan sepanjang {X} m dengan Usulan {TahunLalu} ({NomorReg}) yang realisasi fisiknya baru mencapai {Realisasi} m dari {Target} m (sisa {Sisa} m belum tuntas). Konfirmasi ke Pemerintah Desa bahwa pengajuan tahun {TahunIni} merupakan penuntasan sisa volume tahun {TahunLalu}."*
       - Jika `$persenRealisasi >= 90.0`:
         - Status = `'duplikasi_tuntas'`.
         - Rekomendasi Bappeda: *"Peringatan: Lokasi beririsan sepanjang {X} m dengan Usulan {TahunLalu} ({NomorReg}) yang telah terealisasi tuntas ({Persen}%). Berpotensi tumpang tindih anggaran di titik yang sama."*
   - **Langkah 4: Agregasi ke Tabel Induk `bkkd_proposal`**:
     - Update `jumlah_lokasi` = jumlah baris di `bkkd_proposal_lokasi`.
     - Update `total_panjang_gis` = `SUM(panjang_gis_m)`.
     - Update `bkkd_proposal.status_analisa_spasial` menggunakan prioritas tertinggi dari lokasi anaknya:
       1. `duplikasi_tuntas`
       2. `konfirmasi_belum_tuntas`
       3. `lanjutan_ruas`
       4. `aman`
       5. `belum_dianalisa`
   - **Catatan Penting untuk Lingkungan Testing SQLite**:
     - Jika `DB::connection()->getDriverName() === 'sqlite'`, sediakan helper perbandingan koordinat GeoJSON sederhana di PHP (bounding box / jarak titik) di dalam `BkkdSpatialAnalysisService` agar seluruh skenario unit & feature test untuk `aman`, `konfirmasi_belum_tuntas`, dan `duplikasi_tuntas` dapat diuji tanpa membutuhkan server PostgreSQL aktif.

---

### Task 4: Controller, FormRequest, Resource & Routes (`apps/api/`)

1. **Buat Controller**: `app/Http/Controllers/Admin/BkkdProposalLokasiController.php`
   - `index(Request $request, string $proposalId)`: Mengambil seluruh lokasi `LineString` milik proposal dalam format list + GeoJSON `FeatureCollection`, beserta ringkasan analisa spasial.
   - `store(StoreBkkdProposalLokasiRequest $request, string $proposalId)`: Menyimpan 1 lokasi `LineString` baru, menjalankan `BkkdSpatialAnalysisService::analisaProposal()`, dan mencatat audit trail.
   - `update(UpdateBkkdProposalLokasiRequest $request, string $proposalId, string $lokasiId)`: Memperbarui atribut/geometri lokasi atau menyimpan `catatan_konfirmasi_desa`, lalu menjalankan ulang analisa spasial.
   - `destroy(Request $request, string $proposalId, string $lokasiId)`: Menghapus lokasi dan memperbarui agregat induk.
   - `reanalyze(Request $request, string $proposalId)`: Menjalankan ulang analisa spasial untuk seluruh lokasi pada proposal tersebut.
   - `daftarKonfirmasi(Request $request)`: Endpoint rekapitulasi lintas desa (`GET /api/v1/admin/bkkd/analisa-spasial`) untuk memfilter usulan berdasarkan `tahun_anggaran`, `id_kecamatan`, `id_desa`, dan `status_analisa_spasial` (`konfirmasi_belum_tuntas`, `duplikasi_tuntas`, dll.).
   - `simpanKonfirmasiDesa(Request $request, string $proposalId)`: Menyimpan hasil klarifikasi/konfirmasi Bappeda dengan pihak desa (`catatan_konfirmasi_desa`, `dikonfirmasi_oleh`, `dikonfirmasi_pada`).

2. **Daftarkan Route API**:
   Tambahkan pada grup route admin BKKD:
   ```php
   Route::get('bkkd/analisa-spasial', [BkkdProposalLokasiController::class, 'daftarKonfirmasi']);
   Route::get('bkkd/proposal/{id}/lokasi', [BkkdProposalLokasiController::class, 'index']);
   Route::post('bkkd/proposal/{id}/lokasi', [BkkdProposalLokasiController::class, 'store']);
   Route::put('bkkd/proposal/{id}/lokasi/{lokasiId}', [BkkdProposalLokasiController::class, 'update']);
   Route::delete('bkkd/proposal/{id}/lokasi/{lokasiId}', [BkkdProposalLokasiController::class, 'destroy']);
   Route::post('bkkd/proposal/{id}/analisa-spasial', [BkkdProposalLokasiController::class, 'reanalyze']);
   Route::post('bkkd/proposal/{id}/konfirmasi-desa', [BkkdProposalLokasiController::class, 'simpanKonfirmasiDesa']);
   ```

---

### Task 5: Automated Testing (`apps/api/tests/Feature/Bkkd/BkkdProposalLokasiAnalysisTest.php`)

Buat Feature Test dengan `use RefreshDatabase;` yang menguji minimal 6 skenario berikut:
1. `test_bappeda_can_add_multiple_linestring_locations_to_single_proposal(): void`
   - Assert 1 proposal dapat memiliki 2 atau lebih record di `bkkd_proposal_lokasi`, `jumlah_lokasi == 2`, dan `total_panjang_gis > 0`.
2. `test_spatial_analysis_flags_konfirmasi_belum_tuntas_when_overlapping_2026_proposal_has_incomplete_realization(): void`
   - Buat proposal 2026 dengan lokasi `LineString` sepanjang ~500m, tanpa realisasi (atau realisasi sebagian) di `infrastruktur_segmen`.
   - Buat proposal 2027 di desa yang sama dengan koordinat `LineString` yang beririsan.
   - Assert lokasi 2027 otomatis berstatus `konfirmasi_belum_tuntas` dan `ringkasan_analisa.referensi_tahun_lalu` berisi nomor registrasi usulan 2026.
3. `test_spatial_analysis_flags_duplikasi_tuntas_when_overlapping_2026_proposal_is_fully_realized(): void`
   - Buat proposal 2026 + lokasi `LineString`, serta buat record `infrastruktur_segmen` pada koordinat yang sama dengan panjang 100% tuntas.
   - Buat proposal 2027 pada koordinat yang sama.
   - Assert status analisa menjadi `duplikasi_tuntas`.
4. `test_spatial_analysis_flags_aman_when_location_does_not_overlap_previous_years(): void`
   - Buat lokasi pada koordinat baru yang berjauhan dari lokasi 2026.
   - Assert status analisa menjadi `aman`.
5. `test_bappeda_can_record_village_confirmation_notes_for_incomplete_previous_year_proposal(): void`
   - Kirim `POST /api/v1/admin/bkkd/proposal/{id}/konfirmasi-desa` dengan `catatan_konfirmasi_desa`.
   - Assert tersimpan di database beserta `dikonfirmasi_oleh` dan `dikonfirmasi_pada`.
6. `test_unauthorized_verifier_kecamatan_cannot_modify_other_kecamatan_proposal_locations(): void`
   - Uji RBAC isolasi wilayah menghasilkan `403 Forbidden`.

---

### Task 6: Frontend TypeScript & Composable (`apps/web/`)

1. Di `apps/web/app/types/bkkd.ts`, tambahkan:
   ```ts
   export type StatusAnalisaSpasialBkkd =
     | 'belum_dianalisa'
     | 'aman'
     | 'lanjutan_ruas'
     | 'konfirmasi_belum_tuntas'
     | 'duplikasi_tuntas';

   export interface BkkdReferensiOverlapTahunLalu {
     id_proposal_lama: string;
     nomor_registrasi: string;
     tahun_anggaran: number;
     judul_kegiatan: string;
     id_lokasi_lama: string;
     nama_lokasi_lama: string;
     panjang_usulan_lama_m: number;
     panjang_realisasi_m: number;
     sisa_belum_tuntas_m: number;
     persen_realisasi: number;
     panjang_irisan_usulan_m: number;
   }

   export interface BkkdRingkasanAnalisaSpasial {
     status: StatusAnalisaSpasialBkkd;
     terdeteksi_overlap_usulan: boolean;
     rekomendasi_bappeda: string;
     referensi_tahun_lalu: BkkdReferensiOverlapTahunLalu[];
     analyzed_at?: string;
   }

   export interface BkkdProposalLokasi {
     id: string;
     id_proposal: string;
     id_jalan_poros?: string | null;
     urutan: number;
     nama_lokasi: string;
     panjang_rencana_m?: number | null;
     panjang_gis_m: number;
     lebar_m?: number | null;
     status_analisa_spasial: StatusAnalisaSpasialBkkd;
     ringkasan_analisa?: BkkdRingkasanAnalisaSpasial | null;
     catatan_konfirmasi_desa?: string | null;
     geojson?: any;
     centroid?: any;
     created_at: string;
     updated_at: string;
   }
   ```
2. Di `apps/web/app/composables/useBkkdApi.ts`, tambahkan method:
   - `fetchProposalLokasi(proposalId: string)`
   - `createProposalLokasi(proposalId: string, payload: Record<string, any>)`
   - `updateProposalLokasi(proposalId: string, lokasiId: string, payload: Record<string, any>)`
   - `deleteProposalLokasi(proposalId: string, lokasiId: string)`
   - `runProposalSpatialAnalysis(proposalId: string)`
   - `simpanKonfirmasiDesa(proposalId: string, payload: { catatan_konfirmasi_desa: string })`
   - `fetchAnalisaSpasialList(params?: Record<string, any>)`

---

### Task 7: Komponen WebGIS & Halaman Detail Proposal (`apps/web/`)

> [!NOTE]
> Saat mengerjakan komponen UI di Task 7, developer atau AI Agent wajib membaca panduan desain di [docs/DESIGN_ADMIN.md](../DESIGN_ADMIN.md), [docs/DESIGN_MAPS.md](../DESIGN_MAPS.md), dan menerapkan aturan di `AGENTS.md` (`antislop`).

1. **Tab "Lokasi & Analisa Spasial" pada `/admin/bkkd/proposal/[id].vue`**:
   - Menampilkan peta interaktif dengan **3 Layer Simultan**:
     1. Layer Usulan Tahun Lalu ($T-1$, misal 2026) di desa tersebut (garis putus-putus biru/kuning).
     2. Layer Realisasi Pembangunan (`infrastruktur_segmen`) di desa tersebut (garis tebal hijau).
     3. Layer Multi-Lokasi Usulan Saat Ini ($T$, misal 2027) yang dapat ditambah/digambar (`Draw LineString`).
   - Menampilkan daftar titik lokasi (`Lokasi 1`, `Lokasi 2`, dst.) beserta `panjang_gis_m` otomatis dan kartu hasil analisa:
     - Jika status `konfirmasi_belum_tuntas`: tampilkan rincian perbandingan **Panjang Usulan 2026 vs Realisasi 2026 vs Sisa Belum Tuntas**, disertai kotak input **Catatan Konfirmasi Bappeda ke Desa**.
     - Jika status `duplikasi_tuntas`: tampilkan peringatan bahwa ruas tersebut sudah terealisasi tuntas di tahun sebelumnya.
2. **Filter Status Analisa Spasial pada `/admin/bkkd/proposal/index.vue`**:
   - Tambahkan filter dropdown `status_analisa_spasial` (`Perlu Konfirmasi Desa / Belum Tuntas`, `Indikasi Duplikasi`, `Lanjutan Ruas`, `Aman`) agar Bappeda dapat langsung memfilter daftar desa yang perlu dikonfirmasi ulang.

---

### Status Implementasi

Seluruh Task 1 sampai dengan Task 7 telah selesai diimplementasikan:
- [x] **Task 1**: Migrasi Database Skema Multi-Lokasi LineString & Kolom Status Analisa pada Proposal
- [x] **Task 2**: Eloquent Model `BkkdProposalLokasi` & Relasi pada `BkkdProposal`
- [x] **Task 3**: Layanan Analisa Spasial PostGIS & Fallback Evaluator (`BkkdSpatialAnalysisService`)
- [x] **Task 4**: Controller, Form Requests, Resources & Routing API
- [x] **Task 5**: Automated Feature Test Suite (`BkkdProposalLokasiAnalysisTest`, 6/6 scenario passed)
- [x] **Task 6**: Definisi TypeScript & Composable API (`useBkkdApi`)
- [x] **Task 7**: Komponen WebGIS & Integrasi Halaman (`BkkdProposalLokasiTab.vue`, detail `[id].vue`, daftar `index.vue`)



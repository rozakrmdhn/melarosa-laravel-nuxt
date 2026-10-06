# Issue: Fase 2 - Eloquent Models, Enums, & Policies

- **Status**: Completed
- **Fase**: 2 dari 5
- **Komponen**: Eloquent ORM & Authorization (`apps/api`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md#L249-L567)
- **Dependensi**: Fase 1 (Tabel database telah termigrasi)

---

## 1. Deskripsi & Tujuan

Mengimplementasikan kelas-kelas Model Eloquent, PHP Enums, query scope wilayah/spasial, serta Laravel Policies untuk menegakkan aturan bisnis otorisasi per role (`verifierBappeda` vs `verifierKecamatan`).

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [x] Seluruh model mengimplementasikan trait `HasFactory`, `HasUuids`, dan `Auditable`.
- [x] Relasi foreign key ke user (`created_by`, `verified_kecamatan_by`, `verified_bappeda_by`, `user_id`, `revised_by`) berhasil merujuk ke model `User` menggunakan `ownerKey: 'uuid'`.
- [x] Model `InfrastrukturSegmen` memiliki `scopeForUser(?User $user)` yang mengisolasi data:
  - Superadmin & `verifierBappeda`: Dapat mengakses seluruh data segmen di kabupaten.
  - `verifierKecamatan`: Hanya dapat mengakses segmen di wilayah kecamatannya (`id_kecamatan`).
  - User Desa: Hanya dapat mengakses segmen di wilayah desanya (`id_desa`).
- [x] Model `InfrastrukturSegmen` memiliki `scopeWithGeoJson()` yang mengeluarkan atribut `geojson` dan `centroid` menggunakan fungsi bawaan PostGIS.
- [x] `InfrastrukturSegmenPolicy` mengizinkan:
  - `manage` (create/update): Global untuk `verifierBappeda`, terbatas wilayah untuk `verifierKecamatan`.
  - `verifyKecamatan`: Hanya role `verifierKecamatan` pada segmen berstatus `submitted_desa` di kecamatannya.
  - `verifyBappeda`: Hanya role `verifierBappeda` pada segmen berstatus `verified_kecamatan`.

---

## 3. Rincian File yang Dibuat & Dimodifikasi

### A. PHP Enums (`apps/api/app/Enums/`)
1. `KondisiSegmen.php`:
   ```php
   enum KondisiSegmen: string {
       case Baik = 'Baik';
       case Sedang = 'Sedang';
       case RusakRingan = 'Rusak Ringan';
       case RusakBerat = 'Rusak Berat';
   }
   ```
2. `StatusVerifikasi.php`:
   ```php
   enum StatusVerifikasi: string {
       case Draft = 'draft';
       case SubmittedDesa = 'submitted_desa';
       case VerifiedKecamatan = 'verified_kecamatan';
       case RejectedKecamatan = 'rejected_kecamatan';
       case VerifiedBappeda = 'verified_bappeda';
       case RejectedBappeda = 'rejected_bappeda';
   }
   ```
3. `StatusMonitoring.php`:
   ```php
   enum StatusMonitoring: string {
       case Draft = 'draft';
       case Submitted = 'submitted';
       case Approved = 'approved';
       case Rejected = 'rejected';
       case Reverted = 'reverted';
   }
   ```

### B. Eloquent Models (`apps/api/app/Models/`)
1. `InfrastrukturTipe.php`:
   - `$table = 'infrastruktur_tipe'`
   - `$primaryKey = 'id'`, `$incrementing = false`, `$keyType = 'string'`
   - `$auditModule = 'infrastruktur-tipe'`
   - Relasi: `hasMany(InfrastrukturSegmen::class, 'tipe_kode', 'kode')`
2. `InfrastrukturSegmen.php`:
   - `$table = 'infrastruktur_segmen'`
   - Relasi:
     - `tipe`: `belongsTo(InfrastrukturTipe::class, 'tipe_kode', 'kode')`
     - `parent`: `belongsTo(InfrastrukturSegmen::class, 'parent_id', 'id')`
     - `children`: `hasMany(InfrastrukturSegmen::class, 'parent_id', 'id')`
     - `plotting`: `belongsTo(PlottingAnggaran::class, 'plotting_id', 'id')`
     - `kecamatan`: `belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan', 'id')`
     - `desa`: `belongsTo(BatasWilayahDesa::class, 'id_desa', 'id')`
     - `creator`: `belongsTo(User::class, 'created_by', 'uuid')`
     - `verifierKecamatan`: `belongsTo(User::class, 'verified_kecamatan_by', 'uuid')`
     - `verifierBappeda`: `belongsTo(User::class, 'verified_bappeda_by', 'uuid')`
   - Scopes: `scopeForUser()`, `scopeWithGeoJson()`.
3. `PlottingAnggaran.php`:
   - `$table = 'plotting_anggaran'`
   - Relasi: `kecamatan`, `desa`, `author` (ownerKey `uuid`), `segmen`, `monitoringRealisasi`.
4. `MonitoringRealisasi.php`:
   - `$table = 'monitoring_realisasi'`
   - Relasi: `plotting`, `segmenUtama`, `kecamatan`, `desa`, `items`, `revisions`, `author` (ownerKey `uuid`).
5. `MonitoringRealisasiItem.php`:
   - `$table = 'monitoring_realisasi_items'`
   - Relasi: `monitoring`, `segmen`.
6. `MonitoringRealisasiRevision.php`:
   - `$table = 'monitoring_realisasi_revisions'`
   - Relasi: `monitoring`, `author` (ownerKey `uuid`).

### C. Laravel Policies (`apps/api/app/Policies/`)
1. `InfrastrukturSegmenPolicy.php`:
   - `viewAny(User $user)`
   - `view(User $user, InfrastrukturSegmen $segmen)`
   - `create(User $user)`
   - `update(User $user, InfrastrukturSegmen $segmen)`
   - `delete(User $user, InfrastrukturSegmen $segmen)`
   - `verifyKecamatan(User $user, InfrastrukturSegmen $segmen)`:
     - Harus memiliki role `verifierKecamatan` atau `admin`.
     - `(int) $segmen->id_kecamatan === (int) $user->id_kecamatan`.
     - Status segmen harus `submitted_desa`.
   - `verifyBappeda(User $user, InfrastrukturSegmen $segmen)`:
     - Harus memiliki role `verifierBappeda` atau `admin`.
     - Status segmen harus `verified_kecamatan`.
2. `PlottingAnggaranPolicy.php`
3. `MonitoringRealisasiPolicy.php`

---

## 4. Panduan Verifikasi & Testing (CLI)

Gunakan `php artisan tinker` untuk memvalidasi:
```bash
# 1. Cek registrasi Policy
Gate::getPolicyFor(App\Models\InfrastrukturSegmen::class)

# 2. Cek Relasi User OwnerKey UUID
$segmen = App\Models\InfrastrukturSegmen::first();
$segmen->creator; // Menghasilkan model User terkait via kolom uuid

# 3. Cek Scope Spasial PostGIS
App\Models\InfrastrukturSegmen::withGeoJson()->first()->geojson;
```

---

## 5. Checklist Tugas

- [x] Buat file enum `KondisiSegmen.php`, `StatusVerifikasi.php`, `StatusMonitoring.php`
- [x] Buat model `InfrastrukturTipe.php`
- [x] Buat model `InfrastrukturSegmen.php` beserta relasi dan scopes spasial/wilayah
- [x] Buat model `PlottingAnggaran.php`
- [x] Buat model `MonitoringRealisasi.php`, `MonitoringRealisasiItem.php`, `MonitoringRealisasiRevision.php`
- [x] Daftarkan relasi timbal balik di model eksisting (`BatasWilayahKecamatan`, `BatasWilayahDesa`, `User`)
- [x] Buat `InfrastrukturSegmenPolicy.php`
- [x] Buat `PlottingAnggaranPolicy.php` dan `MonitoringRealisasiPolicy.php`
- [x] Daftarkan seluruh policies di `AppServiceProvider.php`

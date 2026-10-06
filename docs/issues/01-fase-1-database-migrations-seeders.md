# Issue: Fase 1 - Database Migrations & Seeders

- **Status**: Completed
- **Fase**: 1 dari 5
- **Komponen**: Database PostgreSQL 16 / PostGIS (`apps/api`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)

---

## 1. Deskripsi & Tujuan

Membangun fondasi struktur tabel fisik di PostgreSQL (`db_melarosa`) dengan integritas referensial foreign key, ekstensi PostGIS geometry, indeks spasial GiST, serta initial seeding untuk peran (`verifierBappeda`, `verifierKecamatan`) dan master data tipe infrastruktur.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [x] Seluruh migrasi database berhasil dieksekusi tanpa error (`php artisan migrate`).
- [x] Tabel PostGIS `infrastruktur_segmen` memiliki kolom geometri dengan spatial index GiST.
- [x] Kolom `id_desa` bertipe `int8` (bigint) pada tabel `plotting_anggaran`, `infrastruktur_segmen`, dan `monitoring_realisasi` (sinkron dengan `bataswilayah_desa.id`).
- [x] Kolom `id_kecamatan` bertipe `int4` (integer) pada seluruh tabel baru.
- [x] Kolom foreign key user (`created_by`, `verified_kecamatan_by`, `verified_bappeda_by`, `user_id`, `revised_by`) bertipe `uuid` yang mereferensikan `users.uuid`.
- [x] Database seeder berhasil menginisiasi permission Spatie, role `verifierBappeda` dan `verifierKecamatan`, serta data awal `infrastruktur_tipe`.

---

## 3. Rincian File Migrasi yang Dibuat

Urutan timestamp file migrasi di `apps/api/database/migrations/`:

| No | File Migrasi | Tabel yang Dihasilkan | Keterangan |
|---|---|---|---|
| 1 | `2026_09_30_150001_create_infrastruktur_tipe_table.php` | `infrastruktur_tipe` | Master jenis infrastruktur (jalan, jembatan, dll) |
| 2 | `2026_09_30_150002_create_ref_sumber_dana_table.php` | `ref_sumber_dana` | Master referensi sumber pendanaan (APBD, DAK, dll) |
| 3 | `2026_09_30_150003_create_plotting_anggaran_table.php` | `plotting_anggaran` | Perencanaan pagu tahunan per desa & kecamatan |
| 4 | `2026_09_30_150004_create_infrastruktur_segmen_table.php` | `infrastruktur_segmen` | Data spasial PostGIS, audit verifikasi 3 tingkat |
| 5 | `2026_09_30_150005_create_monitoring_realisasi_table.php` | `monitoring_realisasi` | Berita Acara & realisasi fisik lapangan |
| 6 | `2026_09_30_150006_create_monitoring_realisasi_items_table.php` | `monitoring_realisasi_items` | Pivot monitoring ke segmen yang dibangun |
| 7 | `2026_09_30_150007_create_monitoring_realisasi_revisions_table.php` | `monitoring_realisasi_revisions` | Riwayat snapshot perubahan Berita Acara |

---

## 4. Spesifikasi Struktur Kolom Tabel Utama

### Tabel `infrastruktur_tipe`
- `id` (uuid, primary)
- `kode` (string, unique)
- `nama` (string)
- `deskripsi` (text, nullable)
- `ikon` (string, nullable)
- `warna` (string, nullable)
- `geom_type` (string, default: `'LineString'`)
- `table_name` (string, nullable)
- `has_segmen` (boolean, default: true)
- `is_active` (boolean, default: true)
- `sort_order` (integer, default: 0)
- `config` (jsonb, nullable)
- `timestamps`

### Tabel `plotting_anggaran`
- `id` (uuid, primary)
- `tahun_anggaran` (integer)
- `id_kecamatan` (integer, foreign ke `bataswilayah_kecamatan.id`)
- `id_desa` (bigint, foreign ke `bataswilayah_desa.id`)
- `jenis_bantuan` (string)
- `nama_kegiatan` (string)
- `lokasi_kegiatan` (text, nullable)
- `sumber_dana` (string)
- `target_pagu_anggaran` (decimal 15, 2)
- `target_panjang_m` (decimal 10, 2)
- `user_id` (uuid, foreign ke `users.uuid`)
- `timestamps`
- Indeks: `(tahun_anggaran, id_kecamatan, id_desa)`

### Tabel `infrastruktur_segmen`
- `id` (uuid, primary)
- `tipe_kode` (string, foreign ke `infrastruktur_tipe.kode`)
- `parent_id` (uuid, foreign self-referencing ke `infrastruktur_segmen.id`, nullable)
- `geom` (`geometry(LineString, 4326)`)
- `panjang` (double, nullable)
- `lebar` (double, nullable)
- `kondisi` (string, check: `'Baik'`, `'Sedang'`, `'Rusak Ringan'`, `'Rusak Berat'`)
- `status_kondisi` (string, check: `'Eksisting'`, `'Riwayat'`)
- `tahun_pembangunan` (integer, nullable)
- `sumber_dana` (string, nullable)
- `keterangan` (text, nullable)
- `foto_url` (string, nullable)
- `atribut` (jsonb, nullable)
- `desa` (string, nullable - cache nama)
- `kecamatan` (string, nullable - cache nama)
- `id_desa` (bigint, foreign ke `bataswilayah_desa.id`)
- `id_kecamatan` (integer, foreign ke `bataswilayah_kecamatan.id`)
- `namobj` (string, nullable)
- `plotting_id` (uuid, foreign ke `plotting_anggaran.id`, nullable)
- `verifikator` (string, nullable)
- `user_id` (uuid, foreign ke `users.uuid`, nullable)
- `status_parent` (boolean, default: false)
- `sumber_data` (string, nullable)
- `status_verifikasi` (string, check: `'draft'`, `'submitted_desa'`, `'verified_kecamatan'`, `'rejected_kecamatan'`, `'verified_bappeda'`, `'rejected_bappeda'`, default: `'draft'`)
- `catatan_verifikasi` (string, nullable)
- `id_entry` (uuid, nullable)
- `status_aset` (string, nullable)
- `created_by` (uuid, foreign ke `users.uuid`, nullable)
- `created_by_role` (string, nullable)
- `submitted_desa_at` (timestamptz, nullable)
- `verified_kecamatan_by` (uuid, foreign ke `users.uuid`, nullable)
- `verified_kecamatan_at` (timestamptz, nullable)
- `catatan_kecamatan` (text, nullable)
- `verified_bappeda_by` (uuid, foreign ke `users.uuid`, nullable)
- `verified_bappeda_at` (timestamptz, nullable)
- `catatan_bappeda` (text, nullable)
- `timestamps`
- Indeks: Spatial GiST pada `geom`, btree pada `id_kecamatan`, `id_desa`, `tipe_kode`, `status_verifikasi`, `plotting_id`.

### Tabel `monitoring_realisasi`
- `id` (uuid, primary)
- `nomor_ba` (string)
- `id_plotting` (uuid, foreign ke `plotting_anggaran.id`)
- `id_segmen` (uuid, foreign ke `infrastruktur_segmen.id`, nullable)
- `id_kecamatan` (integer, foreign ke `bataswilayah_kecamatan.id`)
- `id_desa` (bigint, foreign ke `bataswilayah_desa.id`)
- `tahun_anggaran` (integer)
- `sumber_dana` (string)
- `rencana_panjang` (decimal 10, 2)
- `realisasi_panjang` (decimal 10, 2)
- `status` (string, check: `'draft'`, `'submitted'`, `'approved'`, `'rejected'`, `'reverted'`, default: `'draft'`)
- `keterangan` (text, nullable)
- `user_id` (uuid, foreign ke `users.uuid`)
- `timestamps`
- Unique index: `(nomor_ba, tahun_anggaran)`

### Tabel `monitoring_realisasi_items`
- `id` (uuid, primary)
- `id_monitoring` (uuid, foreign ke `monitoring_realisasi.id`, onDelete cascade)
- `id_segmen` (uuid, foreign ke `infrastruktur_segmen.id`)
- `timestamps`
- Unique index: `(id_monitoring, id_segmen)`

### Tabel `monitoring_realisasi_revisions`
- `id` (uuid, primary)
- `id_monitoring` (uuid, foreign ke `monitoring_realisasi.id`, onDelete cascade)
- `catatan_revisi` (text)
- `status_sebelum` (string)
- `data_snapshot` (jsonb)
- `revised_by` (uuid, foreign ke `users.uuid`)
- `reverted_at` (timestamptz)
- `created_at` (timestamptz)

---

## 5. Rincian Seeder

1. **`InfrastrukturPermissionSeeder.php`**:
   - Menambahkan permission baru:
     - `infrastruktur-segmen-view`, `infrastruktur-segmen-create`, `infrastruktur-segmen-update`, `infrastruktur-segmen-delete`
     - `infrastruktur-segmen-verify-kecamatan`, `infrastruktur-segmen-verify-bappeda`
     - `plotting-anggaran-view`, `plotting-anggaran-manage`
     - `monitoring-realisasi-view`, `monitoring-realisasi-manage`
   - Role `verifierBappeda`: Diberi seluruh permission view, manage, dan `infrastruktur-segmen-verify-bappeda`.
   - Role `verifierKecamatan`: Diberi permission view, create, update (wilayahnya), dan `infrastruktur-segmen-verify-kecamatan`.
2. **`InfrastrukturMasterSeeder.php`**:
   - Default tipe: Jalan Poros Desa (`kode: jalan_poros`), Jembatan Desa (`kode: jembatan`), Drainase/TPT (`kode: drainase_tpt`).
   - Default sumber dana: APBD Kabupaten, DAK Fisik, BKK Desa, Banprov, Dana Desa (DDS).

---

## 6. Checklist Tugas

- [x] Buat migrasi `infrastruktur_tipe`
- [x] Buat migrasi `ref_sumber_dana`
- [x] Buat migrasi `plotting_anggaran`
- [x] Buat migrasi `infrastruktur_segmen` dengan PostGIS geom & spatial index GiST
- [x] Buat migrasi `monitoring_realisasi`
- [x] Buat migrasi `monitoring_realisasi_items`
- [x] Buat migrasi `monitoring_realisasi_revisions`
- [x] Buat seeder permissions, roles, dan master data
- [x] Jalankan `php artisan migrate` dan `php artisan db:seed`
- [x] Verifikasi keberadaan tabel via `php artisan db:show`

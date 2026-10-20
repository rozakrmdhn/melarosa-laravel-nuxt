# Indeks Modul: Administrasi Proposal Bantuan Keuangan Khusus Desa (BKKD)

- **Status**: Ready for Breakdown Implementation
- **Dokumen Induk**: [module_issue_bkkd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/module_issue_bkkd.md)
- **Workspace**: `apps/api` (Laravel 13, PostgreSQL + PostGIS) & `apps/web` (Nuxt 4, Nuxt UI, Pinia)
- **Role Utama**: `verifierBappeda` (Pencatatan penuh), `verifierKecamatan` (Read-only wilayah), `admin` (Super Admin bypass)

---

## 1. Ringkasan Eksekutif

Modul BKKD dibangun untuk mendigitalkan dan menertibkan administrasi usulan bantuan keuangan khusus desa. Alur kerja dimulai dari pencatatan proposal fisik yang masuk ke Bappeda, pengelolaan lampiran berkas hasil pindai, pencatatan hasil verifikasi dari OPD teknis beserta dokumen pendukung (PDF/Excel), penelusuran riwayat versi perubahan proposal maupun verifikasi, serta kesiapan integrasi spasial PostGIS untuk visualisasi peta di fase berikutnya.

Untuk memastikan pengerjaan terarah dan mudah diuji, modul ini dipecah menjadi 6 dokumen issue terfokus pada folder `docs/issues/bkkd/`:

---

## 2. Peta Dokumen Issue Per Fitur

| Kode | Dokumen Issue | Kategori | Fokus Utama |
| :--- | :--- | :--- | :--- |
| **BKKD-00** | [00-skema-database-dan-fondasi.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/00-skema-database-dan-fondasi.md) | Fondasi & Master | Migrasi database, kolom geometri `geom`, model Eloquent, enum status, seeder permission, dan service upload dokumen privat. |
| **BKKD-01** | [01-daftar-proposal-usulan.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/01-daftar-proposal-usulan.md) | Fitur 1 (Transaksi) | Pencatatan proposal berkas fisik, nomor registrasi unik, upload lampiran, filter wilayah/status, dan antarmuka daftar proposal. |
| **BKKD-02** | [02-hasil-verifikasi-proposal.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/02-hasil-verifikasi-proposal.md) | Fitur 2 (Verifikasi) | Pencatatan hasil telaah OPD teknis, upload berkas PDF/Excel hasil, transisi status otomatis, dan penonaktifan hasil lama saat verifikasi ulang. |
| **BKKD-03** | [03-historis-perubahan-usulan.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/03-historis-perubahan-usulan.md) | Fitur 3 (Audit Usulan) | Versioning data usulan proposal, pencatatan alasan perubahan wajib, deteksi konflik simpan (HTTP 409), dan perbandingan diff sebelum vs sesudah. |
| **BKKD-04** | [04-historis-perubahan-verifikasi.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/04-historis-perubahan-verifikasi.md) | Fitur 4 (Audit Verifikasi) | Riwayat koreksi nilai/catatan OPD, riwayat superseded verifikasi ulang, retensi berkas lama, dan portal audit log riwayat terpusat. |
| **BKKD-05** | [05-rekapitulasi-desa-dan-penetapan-tapd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/05-rekapitulasi-desa-dan-penetapan-tapd.md) | Fitur 5 (Keputusan TAPD) | Rekapitulasi usulan dikelompokkan per desa, pemilihan 1 usulan terbaik oleh TAPD masuk Rancangan APBD, dan ekspor lembar kerja Excel (tanpa integrasi modul plotting anggaran). |
| **BKKD-06** | [06-visualisasi-spasial.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/bkkd/06-visualisasi-spasial.md) | Fitur 6 (Roadmap Spasial) | Pemanfaatan kolom `geom` PostGIS, visualisasi choropleth per desa, integrasi layer Martin vector tile, dan canvas Web GIS MapLibre. |

---

## 3. Matriks Hak Akses & Permission

Sesuai arahan teknis terbaru, seluruh pencatatan data BKKD (create, update, delete, verifikasi, penetapan TAPD) dipusatkan hanya kepada operator Bappeda (`verifierBappeda`). Role kecamatan (`verifierKecamatan`) hanya memiliki akses baca terbatas pada wilayah kecamatannya.

| Permission | Keterangan | verifierBappeda | verifierKecamatan |
| :--- | :--- | :---: | :---: |
| `bkkd-proposal-view` | Melihat daftar, rekapitulasi desa, dan detail proposal | Ya | Ya (scope kecamatan) |
| `bkkd-proposal-create` | Mencatat berkas proposal baru | Ya | Tidak |
| `bkkd-proposal-update` | Mengubah data proposal, lampiran, dan penetapan TAPD | Ya | Tidak |
| `bkkd-proposal-delete` | Menghapus dan memulihkan proposal | Ya | Tidak |
| `bkkd-verifikasi-view` | Melihat hasil verifikasi OPD | Ya | Ya (scope kecamatan) |
| `bkkd-verifikasi-manage` | Mencatat dan merevisi hasil verifikasi OPD | Ya | Tidak |
| `bkkd-riwayat-view` | Melihat riwayat perubahan dan diff versi | Ya | Tidak |

Role `admin` secara otomatis melewati pengecekan izin (bypass Gate) untuk keperluan pemeliharaan teknis.

---

## 4. Urutan Eksekusi Rekomendasi

1. **Jalankan BKKD-00**: Buat migrasi, model, enum, seeder izin, dan upload controller untuk dokumen privat.
2. **Jalankan BKKD-01**: Selesaikan fungsionalitas pencatatan proposal dan halaman daftar proposal (`/admin/bkkd/proposal`).
3. **Jalankan BKKD-02**: Terapkan pencatatan hasil verifikasi OPD dan tab verifikasi pada proposal detail.
4. **Jalankan BKKD-03**: Pasang service pencatatan versi usulan dan antarmuka diff riwayat proposal.
5. **Jalankan BKKD-04**: Pasang pelacakan riwayat verifikasi dan portal riwayat global (`/admin/bkkd/riwayat`).
6. **Jalankan BKKD-05**: Bangun halaman rekapitulasi usulan per desa dan fitur pemilihan 1 usulan TAPD untuk Rancangan APBD (`/admin/bkkd/rekapitulasi`).
7. **Perencanaan BKKD-06**: Siapkan kebutuhan peta tematik spasial untuk rilis berikutnya.

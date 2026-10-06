# Issue: Fase 3 - REST API Endpoints & State Machine Verifikasi

- **Status**: Completed
- **Fase**: 3 dari 5
- **Komponen**: REST API, Controllers, Requests, & Services (`apps/api`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Fase 1 (Database) & Fase 2 (Models & Policies)

---

## 1. Deskripsi & Tujuan

Membangun REST API controller, form request validation, integrasi audit logging, serta alur mesin status (state machine) verifikasi bertingkat: **Desa submit** $\rightarrow$ **Verifikasi Kecamatan** $\rightarrow$ **Verifikasi Bappeda**.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [x] Endpoint CRUD untuk `infrastruktur-tipe` dan `ref-sumber-dana` berfungsi dan mengembalikan JSON terstruktur.
- [x] Endpoint CRUD `plotting-anggaran` mendukung filter tahun anggaran, kecamatan, dan desa.
- [x] Endpoint `infrastruktur-segmen` menerima dan menghasilkan geometri standar GeoJSON via fungsi PostGIS (`ST_GeomFromGeoJSON` dan `ST_AsGeoJSON`).
- [x] Endpoint alur verifikasi berjalan sesuai state machine:
  - `POST /api/infrastruktur-segmen/{id}/submit`: Mengubah status dari `draft` menjadi `submitted_desa` serta mengisi timestamp `submitted_desa_at`.
  - `POST /api/infrastruktur-segmen/{id}/verify-kecamatan`: Mengubah status menjadi `verified_kecamatan` atau `rejected_kecamatan`, merekam `verified_kecamatan_by` (UUID user), timestamp, dan catatan revisi.
  - `POST /api/infrastruktur-segmen/{id}/verify-bappeda`: Mengubah status menjadi `verified_bappeda` atau `rejected_bappeda`, merekam `verified_bappeda_by` (UUID user), timestamp, dan catatan bappeda.
- [x] Endpoint `monitoring-realisasi` mendukung penyematan (bind) segmen ke Berita Acara dan pembuatan snapshot revisi di `monitoring_realisasi_revisions`.

---

## 3. Rincian Endpoint API

### A. Master Tipe & Referensi
- `GET /api/infrastruktur-tipe` - Daftar tipe infrastruktur aktif.
- `GET /api/ref-sumber-dana` - Daftar opsi sumber dana untuk form dropdown.

### B. Perencanaan Pagu Anggaran
- `GET /api/plotting-anggaran` - Daftar alokasi anggaran (filter: `tahun_anggaran`, `id_kecamatan`, `id_desa`).
- `POST /api/plotting-anggaran` - Tambah alokasi pagu baru.
- `GET /api/plotting-anggaran/{id}` - Detail alokasi pagu beserta total realisasinya.
- `PUT /api/plotting-anggaran/{id}` - Perbarui alokasi pagu.
- `DELETE /api/plotting-anggaran/{id}` - Hapus alokasi pagu.

### C. Segmen Infrastruktur (Spasial)
- `GET /api/infrastruktur-segmen` - Daftar segmen dengan pagination, pencarian, filter status verifikasi, kondisi, dan bounding box spasial (`bbox`).
- `GET /api/infrastruktur-segmen/{id}` - Detail satu segmen beserta data geometri GeoJSON dan riwayat parent-child.
- `POST /api/infrastruktur-segmen` - Tambah segmen baru (input koordinat GeoJSON).
- `PUT /api/infrastruktur-segmen/{id}` - Update data fisik atau geometri segmen.
- `DELETE /api/infrastruktur-segmen/{id}` - Hapus segmen.

### D. Workflow Verifikasi Berjenjang
- `POST /api/infrastruktur-segmen/{id}/submit`:
  - Request body: `{}`
  - Validasi: Status saat ini harus `draft` atau `rejected_kecamatan`.
- `POST /api/infrastruktur-segmen/{id}/verify-kecamatan`:
  - Request body:
    ```json
    {
      "action": "approve", // atau "reject"
      "catatan": "Kondisi fisik sesuai hasil survei lapangan."
    }
    ```
  - Validasi: `action` in `['approve', 'reject']`. Jika `reject`, `catatan` wajib diisi. User harus memiliki wewenang wilayah pada kecamatan segmen tersebut.
- `POST /api/infrastruktur-segmen/{id}/verify-bappeda`:
  - Request body:
    ```json
    {
      "action": "approve", // atau "reject"
      "catatan": "Disetujui untuk pembiayaan APBD."
    }
    ```
  - Validasi: Status segmen harus `verified_kecamatan`.

### E. Monitoring Realisasi & Berita Acara
- `GET /api/monitoring-realisasi` - Daftar Berita Acara realisasi.
- `POST /api/monitoring-realisasi` - Simpan BA realisasi, mengikat segmen utama dan daftar `segmen_ids` (items).
- `GET /api/monitoring-realisasi/{id}` - Detail BA, daftar segmen item, dan riwayat revisi.
- `POST /api/monitoring-realisasi/{id}/revisi` - Merekam snapshot perubahan Berita Acara ke tabel revisi.

---

## 4. Rincian File Controller & Form Request yang Dibuat

1. **Controllers (`apps/api/app/Http/Controllers/Admin/`)**:
   - `InfrastrukturTipeController.php`
   - `PlottingAnggaranController.php`
   - `InfrastrukturSegmenController.php`
   - `InfrastrukturVerifikasiController.php` (khusus endpoint state machine submit & approve/reject)
   - `MonitoringRealisasiController.php`
2. **Form Requests (`apps/api/app/Http/Requests/Admin/`)**:
   - `StorePlottingAnggaranRequest.php`, `UpdatePlottingAnggaranRequest.php`
   - `StoreInfrastrukturSegmenRequest.php`, `UpdateInfrastrukturSegmenRequest.php`
   - `VerifyKecamatanRequest.php`, `VerifyBappedaRequest.php`
   - `StoreMonitoringRealisasiRequest.php`, `CreateRevisiMonitoringRequest.php`
3. **API Resources (`apps/api/app/Http/Resources/`)**:
   - `InfrastrukturSegmenResource.php` (menghasilkan format Feature GeoJSON standar).
   - `MonitoringRealisasiResource.php`.

---

## 5. Checklist Tugas

- [x] Buat Form Requests untuk validasi input seluruh modul
- [x] Buat `InfrastrukturTipeController.php`
- [x] Buat `PlottingAnggaranController.php`
- [x] Buat `InfrastrukturSegmenController.php` (handling spasial GeoJSON PostGIS)
- [x] Buat `InfrastrukturVerifikasiController.php` (state machine submit, verify kecamatan, verify bappeda)
- [x] Buat `MonitoringRealisasiController.php` (kalkulasi panjang, bind items, snapshot revisi)
- [x] Daftarkan rute API di `apps/api/routes/web.php` (atau `routes/api.php`) di bawah middleware auth
- [x] Uji endpoint verifikasi menggunakan Postman / curl / unit test script

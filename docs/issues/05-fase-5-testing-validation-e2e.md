# Issue: Fase 5 - Pengujian, Validasi Hak Akses, & Simulasi E2E

- **Status**: Ready for Implementation
- **Fase**: 5 dari 5
- **Komponen**: Automated Tests, Security Audit, E2E Simulation (`apps/api` & `apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Fase 1 s/d Fase 4 selesai dikerjakan

---

## 1. Deskripsi & Tujuan

Melakukan pengujian otomatis (automated feature tests), memvalidasi integritas isolasi wilayah kerja, memastikan aturan mesin status verifikasi tidak dapat dilompati atau dimanipulasi, serta melakukan simulasi operasional lengkap dari hulu (Desa) hingga hilir (Berita Acara Realisasi Bappeda).

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Seluruh feature test di Laravel berjalan sukses (`php artisan test`).
- [ ] Pengujian isolasi wilayah terbukti aman:
  - User `verifierKecamatan` A ditolak secara tegas (HTTP 403 Forbidden) saat mencoba mengedit atau memverifikasi segmen di Kecamatan B.
  - User Desa ditolak saat mencoba mengedit segmen yang sudah berstatus `submitted_desa` atau `verified_kecamatan`.
- [ ] Pengujian state machine verifikasi berjalan sesuai urutan:
  - Bappeda tidak dapat menyetujui segmen yang statusnya masih `draft` atau `submitted_desa` (harus melewati `verified_kecamatan`).
  - Penolakan (Reject) wajib menyertakan catatan revisi (`catatan_kecamatan` atau `catatan_bappeda`).
- [ ] Pengujian integritas constraint database:
  - Duplikasi nomor BA pada tahun anggaran yang sama otomatis digagalkan oleh constraint database.
  - Duplikasi segmen dalam satu monitoring otomatis digagalkan.
- [ ] PostGIS spatial query berjalan optimal dengan pemanfaatan indeks GiST.

---

## 3. Rincian File Test yang Dibuat (`apps/api/tests/Feature/`)

1. **`InfrastrukturSegmenCrudTest.php`**:
   - Uji penyimpanan koordinat GeoJSON ke tipe data PostGIS `geometry`.
   - Uji pemanggilan `scopeWithGeoJson()` yang menghasilkan properti `geojson` dan `centroid`.
   - Uji validasi input dimensi fisik (panjang, lebar, kondisi, sumber dana).
2. **`InfrastrukturTerritoryPolicyTest.php`**:
   - Uji `scopeForUser()` untuk superadmin, verifikator Bappeda, verifikator Kecamatan, dan user Desa.
   - Uji otorisasi `InfrastrukturSegmenPolicy` terhadap operasi `update` dan `delete`.
3. **`InfrastrukturVerificationWorkflowTest.php`**:
   - Skenario Alur Sukses (Happy Path):
     - Desa buat `draft` $\rightarrow$ Submit ke Kecamatan $\rightarrow$ Kecamatan setujui $\rightarrow$ Bappeda sahkan final.
   - Skenario Penolakan Kecamatan (Reject Path 1):
     - Kecamatan menolak segmen $\rightarrow$ Status jadi `rejected_kecamatan` $\rightarrow$ Catatan tersimpan $\rightarrow$ Desa dapat mengedit dan submit ulang.
   - Skenario Penolakan Bappeda (Reject Path 2):
     - Bappeda menolak segmen $\rightarrow$ Status jadi `rejected_bappeda` $\rightarrow$ Catatan tersimpan.
   - Skenario Pelanggaran Akses (Unauthorized State Transition):
     - Bappeda mencoba approve segmen yang belum disetujui kecamatan $\rightarrow$ HTTP 422 Unprocessable Entity.
4. **`MonitoringRealisasiTest.php`**:
   - Uji pembuatan Berita Acara dan pengikatan item segmen.
   - Uji duplikasi nomor BA per tahun anggaran $\rightarrow$ database exception / validasi gagal.
   - Uji pembuatan revisi Berita Acara: memastikan snapshot data lama tersimpan di `monitoring_realisasi_revisions`.

---

## 4. Panduan Eksekusi Pengujian

### A. Menjalankan Automated Tests Backend
```bash
cd c:\laragon\www\melarosa-laravel-nuxt\apps\api
php artisan test --filter=Infrastruktur
```

### B. Validasi Performa Indeks Spasial PostGIS
Jalankan query analisis di PostgreSQL:
```sql
EXPLAIN ANALYZE 
SELECT id, ST_AsGeoJSON(geom) 
FROM infrastruktur_segmen 
WHERE geom && ST_MakeEnvelope(106.8, -6.6, 106.9, -6.5, 4326);
```
*Pastikan execution plan menggunakan `Bitmap Index Scan on idx_infrastruktur_segmen_geom_gist`.*

### C. Simulasi End-to-End (Walkthrough UI)
1. **Login User Desa**:
   - Masuk ke menu "Peta Segmen Infrastruktur".
   - Digitasi 1 ruas segmen baru, isi atribut teknis, simpan sebagai `draft`.
   - Klik tombol "Ajukan Verifikasi (Submit Desa)".
2. **Login `verifierKecamatan`**:
   - Buka menu "Verifikasi Segmen".
   - Periksa segmen yang baru diajukan desa.
   - Lakukan peninjauan dan klik "Setujui ke Bappeda".
3. **Login `verifierBappeda`**:
   - Buka menu "Verifikasi Segmen", filter berdasarkan kecamatan terkait.
   - Periksa segmen yang telah lolos verifikasi kecamatan.
   - Klik "Sahkan Final (Verified)".
4. **Login Bagian Perencanaan / Monitoring**:
   - Buka menu "Monitoring & Berita Acara".
   - Buat Berita Acara baru, pilih segmen yang baru diverifikasi Bappeda.
   - Simpan Berita Acara dan verifikasi ringkasan panjang realisasi.

---

## 5. Checklist Tugas

- [ ] Tulis file test `InfrastrukturSegmenCrudTest.php`
- [ ] Tulis file test `InfrastrukturTerritoryPolicyTest.php`
- [ ] Tulis file test `InfrastrukturVerificationWorkflowTest.php`
- [ ] Tulis file test `MonitoringRealisasiTest.php`
- [ ] Jalankan seluruh suite test backend dan pastikan lulus 100%
- [ ] Verifikasi optimasi indeks GiST pada database PostgreSQL
- [ ] Eksekusi skenario simulasi E2E di antarmuka web Nuxt 3
- [ ] Validasi penanganan error dan notifikasi toast di frontend

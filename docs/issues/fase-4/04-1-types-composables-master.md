# Sub-Fase 4.1: Foundation, Types, Composables, & Fitur Master

- **Status**: Completed
- **Fase**: 4.1 dari 5
- **Komponen**: Types, Composables, & Halaman Master Data (`apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Fase 3 (REST API Backend)

---

## 1. Deskripsi & Tujuan

Membangun fondasi frontend sistem monitoring realisasi berupa kontrak antarmuka TypeScript (`types/`), composables API reusable (`useInfrastrukturApi`), komponen selector wilayah bertingkat dengan auto-lock role pengguna, serta antarmuka CRUD untuk dua fitur master data: **Tipe Infrastruktur** dan **Sumber Dana**.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [x] File kontrak tipe data `types/infrastruktur.ts` memuat seluruh struktur data skema (Segmen, Plotting, Monitoring, BA, Revision, Enum Status & Kondisi).
- [x] Composable `useInfrastrukturApi.ts` menyediakan helper method HTTP dengan penanganan otentikasi Sanctum dan error handling terstandar.
- [x] Komponen `WilayahSelector.vue` mengintegrasikan dropdown Kecamatan $\rightarrow$ Desa dengan auto-lock otomatis sesuai `id_kecamatan` dan `id_desa` user yang sedang login.
- [x] Halaman `/admin/infrastruktur-tipe` mampu menampilkan daftar tipe, konfigurasi warna stroke GIS, ikon, serta form modal tambah/edit.
- [x] Halaman `/admin/ref-sumber-dana` mampu mengelola daftar sumber dana anggaran (APBD, BKK, dll.) dengan sortir urutan dan status aktif.

---

## 3. Rincian File yang Dibuat

1. **`apps/web/app/types/infrastruktur.ts`**:
   - `InfrastrukturTipe`: `id`, `kode`, `nama`, `deskripsi`, `ikon`, `warna`, `geom_type`, `has_segmen`, `is_active`, `config`.
   - `RefSumberDana`: `id`, `kode`, `nama`, `kategori`, `is_active`, `sort_order`.
   - `PlottingAnggaran`: `id`, `tahun_anggaran`, `id_kecamatan`, `id_desa`, `nama_kegiatan`, `jenis_bantuan`, `sumber_dana`, `target_pagu_anggaran`, `target_panjang_m`.
   - `InfrastrukturSegmen`: `id`, `tipe_kode`, `namobj`, `panjang`, `lebar`, `kondisi`, `status_verifikasi`, `geom`, `geojson`, dll.
   - `MonitoringRealisasi`: `id`, `nomor_ba`, `id_plotting`, `rencana_panjang`, `realisasi_panjang`, `persentase`, `status`.
   - `MonitoringRealisasiRevision`: `id`, `id_monitoring`, `catatan_revisi`, `status_sebelum`, `data_snapshot`.
   - Types: `KondisiSegmen = 'Baik' | 'Sedang' | 'Rusak Ringan' | 'Rusak Berat'`.
   - Types: `StatusVerifikasi = 'draft' | 'submitted_desa' | 'verified_kecamatan' | 'rejected_kecamatan' | 'verified_bappeda' | 'rejected_bappeda'`.

2. **`apps/web/app/composables/useInfrastrukturApi.ts`**:
   - `fetchTipeList()`, `storeTipe()`, `updateTipe()`, `deleteTipe()`
   - `fetchSumberDanaList()`, `storeSumberDana()`, `updateSumberDana()`, `deleteSumberDana()`
   - `fetchPlottingList()`, `storePlotting()`, `updatePlotting()`, `deletePlotting()`
   - `fetchSegmenList()`, `storeSegmen()`, `updateSegmen()`, `deleteSegmen()`
   - `fetchMonitoringList()`, `storeMonitoring()`, `updateMonitoring()`, `revisiMonitoring()`

3. **`apps/web/app/components/common/WilayahSelector.vue`**:
   - Props: `modelKecamatan`, `modelDesa`, `disabled`, `showLabels`.
   - Auto-lock wewenang: Operator Desa hanya bisa memilih desanya, Verifikator Kecamatan hanya kecamatannya.

4. **`apps/web/app/pages/admin/infrastruktur-tipe/index.vue`**:
   - Halaman tabel manajemen master tipe infrastruktur dan form modal penataan warna peta.

5. **`apps/web/app/pages/admin/ref-sumber-dana/index.vue`**:
   - Halaman tabel manajemen referensi sumber dana anggaran.

---

## 4. Checklist Tugas

- [x] Buat file `apps/web/app/types/infrastruktur.ts`
- [x] Buat file `apps/web/app/composables/useInfrastrukturApi.ts`
- [x] Buat komponen `apps/web/app/components/common/WilayahSelector.vue`
- [x] Buat halaman `apps/web/app/pages/admin/infrastruktur-tipe/index.vue`
- [x] Buat halaman `apps/web/app/pages/admin/ref-sumber-dana/index.vue`
- [x] Uji fungsi fetch dan validasi simpan pada kedua halaman master

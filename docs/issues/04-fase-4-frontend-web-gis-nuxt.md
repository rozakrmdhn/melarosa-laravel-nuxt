# Issue: Fase 4 - Antarmuka Web & GIS Map Canvas (Nuxt 3)

- **Status**: Ready for Implementation
- **Fase**: 4 dari 5
- **Komponen**: Nuxt 3 Frontend, MapLibre GIS, UI Components (`apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Fase 3 (REST API Backend aktif)

---

## 1. Deskripsi & Tujuan

Membangun antarmuka web interaktif menggunakan Nuxt 3, MapLibre GIS Canvas, Tailwind CSS, dan Nuxt UI untuk modul Perencanaan Anggaran, Peta Segmen Spasial, Portal Verifikasi Bertingkat (`verifierKecamatan` dan `verifierBappeda`), serta Berita Acara Realisasi.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Peta GIS mampu merender layer geometri segmen PostGIS secara mulus dengan pewarnaan tematik berdasarkan `kondisi` fisik dan badge status verifikasi.
- [ ] Tersedia tool digitasi di atas peta untuk menggambar garis segmen baru, mengedit titik vertex, dan memotong (split) segmen.
- [ ] Tersedia Halaman Perencanaan Plotting Anggaran dengan filter tahun dan rekapitulasi pagu vs realisasi.
- [ ] Tersedia Portal Verifikasi Khusus:
  - **Portal Kecamatan**: Menampilkan antrean segmen dari seluruh desa di kecamatannya, penampil foto survei, dan tombol "Setujui ke Bappeda" / "Kembalikan ke Desa".
  - **Portal Bappeda**: Memiliki filter dropdown kecamatan untuk meninjau segmen yang telah disetujui kecamatan, serta tombol "Sahkan Final (Verified)" / "Minta Perbaikan".
- [ ] Tersedia Modul Monitoring Realisasi untuk menginput nomor Berita Acara, memilih segmen terkait dari peta, dan melihat riwayat revisi.
- [ ] Menu navigasi baru terdaftar rapi pada sidebar dashboard admin sesuai wewenang role user.

---

## 3. Sub-Fase Implementasi Terfokus

Untuk memastikan penerapan terstruktur dan dapat dieksekusi bertahap secara presisi, Fase 4 dibagi ke dalam 5 dokumen issue sub-fase:

| Sub-Fase | Dokumen & Tautan | Kategori | Ruang Lingkup & Fokus Utama |
|---|---|---|---|
| **Fase 4.1** | [**04-1-types-composables-master.md**](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/fase-4/04-1-types-composables-master.md) | Master & Core | Types TypeScript, Composable API, Selector Wilayah, serta CRUD Master Tipe & Sumber Dana. |
| **Fase 4.2** | [**04-2-plotting-anggaran.md**](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/fase-4/04-2-plotting-anggaran.md) | Transaksi | Kartu KPI Metrik (Pagu & Fisik), filter tahun & wilayah auto-lock, modal form plotting alokasi kegiatan. |
| **Fase 4.3** | [**04-3-infrastruktur-segmen-gis.md**](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/fase-4/04-3-infrastruktur-segmen-gis.md) | Transaksi Spasial | MapLibre GIS 3-panel layout, pewarnaan tematik kondisi, draw/edit LineString verteks, dan inspector panel. |
| **Fase 4.4** | [**04-4-verifikasi-portal.md**](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/fase-4/04-4-verifikasi-portal.md) | Transaksi Workflow | Portal antrean 3 tab (Desa Submit, Verifikasi Kecamatan, Verifikasi Bappeda), drawer review, modal aksi approve/reject. |
| **Fase 4.5** | [**04-5-monitoring-realisasi-ba.md**](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/fase-4/04-5-monitoring-realisasi-ba.md) | Transaksi Pelaporan | Form Berita Acara (BA), picker binding multiple segmen verified, dan drawer riwayat snapshot audit revisi. |

---

## 4. Rincian File yang Dibuat & Dimodifikasi

### A. Tipe Data & Composables (`apps/web/app/`)
1. `types/infrastruktur.ts`:
   - Interface `InfrastrukturTipe`, `InfrastrukturSegmen`, `PlottingAnggaran`, `MonitoringRealisasi`, `MonitoringRealisasiItem`, `MonitoringRealisasiRevision`.
   - Type `StatusVerifikasi`, `KondisiSegmen`.
2. `composables/useInfrastrukturApi.ts`:
   - Wrapper fetch untuk CRUD master tipe, plotting anggaran, segmen spasial, dan monitoring realisasi.
3. `composables/useVerifikasiWorkflow.ts`:
   - State & handler untuk aksi `submitDesa`, `verifyKecamatan`, dan `verifyBappeda`.

### B. Halaman Modul Baru (`apps/web/app/pages/admin/`)
1. `plotting-anggaran/index.vue`:
   - Filter tahun anggaran, seleksi kecamatan & desa (auto-lock untuk user non-admin).
   - Kartu metrik: Total Pagu Anggaran, Total Target Panjang (m), Total Realisasi (m).
   - Modal form tambah/edit alokasi plotting kegiatan.
2. `infrastruktur-segmen/index.vue`:
   - Integrasi Map Canvas MapLibre.
   - Panel kiri: Filter layer, filter kondisi, filter status verifikasi.
   - Panel kanan (Inspector): Info detail segmen terpilih, galeri foto survei, status aset, dan riwayat verifikator.
   - Panel bawah (Atribut Table): Tabel atribut segmen dengan pencarian dan filter cepat.
3. `infrastruktur-segmen/verifikasi.vue`:
   - **Tab Verifikasi Kecamatan**: Antrean segmen berstatus `submitted_desa` pada kecamatan user.
   - **Tab Verifikasi Bappeda**: Antrean segmen berstatus `verified_kecamatan` dengan filter per-kecamatan.
   - Modal dialog persetujuan/penolakan dilengkapi kolom input catatan wajib untuk penolakan.
4. `monitoring-realisasi/index.vue`:
   - Tabel Berita Acara realisasi per tahun anggaran.
   - Modal form Berita Acara: input Nomor BA, tanggal, realisasi panjang lapangan, dan multi-select segmen yang dibangun.
   - Modal riwayat revisi (menampilkan snapshot perbandingan data lama vs baru).

### C. Komponen Pendukung (`apps/web/app/components/`)
1. `components/dataset/infrastruktur-segmen/MapCanvas.vue`:
   - Render vector layer segmen, interaksi klik feature, tool digitasi garis (DrawLineString).
2. `components/dataset/infrastruktur-segmen/RightPanel.vue`:
   - Form editor atribut segmen dan pratinjau foto.
3. `components/dataset/infrastruktur-segmen/VerifikasiModal.vue`:
   - Dialog konfirmasi aksi verifikasi beserta catatan revisi.

### D. Integrasi Navigasi Sidebar
- Update `apps/api/app/Services/AdminNavigationService.php` dan `apps/web/app/composables/useAdminNavigation.ts` untuk memunculkan grup menu:
  - **Infrastruktur & Aset**:
    - "Master Tipe Infrastruktur"
    - "Peta Segmen Infrastruktur"
    - "Verifikasi Segmen" (dengan indikator badge jumlah antrean pending)
  - **Realisasi & Monitoring**:
    - "Perencanaan Plotting Anggaran"
    - "Monitoring & Berita Acara"

---

## 5. Checklist Tugas

- [x] Buat file tipe data TypeScript `types/infrastruktur.ts`
- [ ] Buat composable `useInfrastrukturApi.ts` dan `useVerifikasiWorkflow.ts` (useInfrastrukturApi selesai)
- [x] Buat halaman Plotting Anggaran (`pages/admin/plotting-anggaran/index.vue`)
- [ ] Buat modul peta dan editor segmen (`pages/admin/infrastruktur-segmen/index.vue`)
- [ ] Buat portal antrean verifikasi (`pages/admin/infrastruktur-segmen/verifikasi.vue`)
- [ ] Buat dialog konfirmasi persetujuan & penolakan dengan catatan revisi
- [ ] Buat halaman Berita Acara Monitoring Realisasi (`pages/admin/monitoring-realisasi/index.vue`)
- [ ] Daftarkan menu pada navigasi sidebar admin dengan proteksi permission role
- [ ] Uji responsivitas tampilan desktop dan mobile

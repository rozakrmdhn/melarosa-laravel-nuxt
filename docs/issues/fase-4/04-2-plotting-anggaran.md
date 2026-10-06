# Sub-Fase 4.2: Transaksi Perencanaan Plotting Pagu Anggaran

- **Status**: Completed
- **Fase**: 4.2 dari 5
- **Komponen**: Halaman & Komponen Perencanaan Anggaran (`apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Sub-Fase 4.1 (Types & Composable API)

---

## 1. Deskripsi & Tujuan

Membangun antarmuka modul Perencanaan Plotting Anggaran (`/admin/plotting-anggaran`) untuk menginput dan memonitor alokasi pagu dana pembangunan serta target panjang fisik (meter) sebelum kegiatan teknis dan digitasi segmen dilakukan.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [x] Halaman memuat **3 Kartu Ringkasan Metrik (KPI Cards)**:
  - Total Alokasi Pagu Anggaran (format Rupiah IDR).
  - Total Target Panjang Fisik (Meter).
  - Total Jumlah Kegiatan / Paket Pekerjaan.
- [x] Tersedia filter multi-kriteria: Tahun Anggaran (default tahun berjalan), Kecamatan, Desa, dan pencarian teks bebas nama kegiatan.
- [x] Tabel data menampilkan rincian alokasi, progress realisasi fisik (panjang meter tercapai vs target), dan tombol aksi.
- [x] Form modal plotting mendukung penambahan dan pengeditan data alokasi kegiatan dengan validasi numerik positif dan integrasi dropdown sumber dana master.
- [x] Operator wilayah terkunci pada wilayah administratifnya masing-masing.

---

## 3. Rincian File yang Dibuat

1. **`apps/web/app/pages/admin/plotting-anggaran/index.vue`**:
   - Header halaman dengan tombol "Tambah Plotting Kegiatan" dan pemilih tahun anggaran.
   - Grid kartu metrik statistik agregat dari API backend (`summary`).
   - Filter bar: Dropdown Kecamatan, Dropdown Desa, dan Input Search.
   - Tabel Nuxt UI (`UTable`) dengan pagination dan state loading skeleton.

2. **`apps/web/app/components/plotting-anggaran/PlottingMetricsCard.vue`**:
   - Komponen visual menampilkan kartu angka metrik dilengkapi ikon yang kontras dan format nominal Rupiah.

3. **`apps/web/app/components/plotting-anggaran/PlottingFormModal.vue`**:
   - Modal form berbasis `UModal` untuk `store` dan `update`:
     - Tahun Anggaran (`UInput` integer)
     - Selector Wilayah (`WilayahSelector`)
     - Jenis Bantuan (BKK Desa, DAK, Hibah, APBD Reguler)
     - Nama Kegiatan & Lokasi Pekerjaan
     - Sumber Dana (Dropdown dari `useInfrastrukturApi().fetchSumberDanaList()`)
     - Target Pagu Anggaran (IDR) & Target Panjang Fisik (Meter)
   - Konfirmasi dialog hapus plotting (dengan proteksi jika sudah memiliki segmen terkait).

---

## 4. Checklist Tugas

- [x] Buat komponen `PlottingMetricsCard.vue`
- [x] Buat modal form `PlottingFormModal.vue`
- [x] Susun halaman utama `apps/web/app/pages/admin/plotting-anggaran/index.vue`
- [x] Hubungkan filter tahun anggaran dan wilayah ke endpoint `GET /api/v1/admin/plotting-anggaran`
- [x] Uji responsivitas form dan kalkulasi metrik ringkasan

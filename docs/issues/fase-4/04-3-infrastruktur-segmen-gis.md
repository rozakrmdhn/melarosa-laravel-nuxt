# Sub-Fase 4.3: Transaksi Peta Spasial & Editor Segmen Fisik (GIS Canvas)

- **Status**: Completed
- **Fase**: 4.3 dari 5
- **Komponen**: GIS Map Canvas MapLibre, Editor 3-Panel, & Spatial Draw Tools (`apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Sub-Fase 4.1 (Types) & Sub-Fase 4.2 (Plotting Anggaran)

---

## 1. Deskripsi & Tujuan

Membangun modul Peta GIS Interaktif (`/admin/infrastruktur-segmen`) dengan layout 3-panel (Filter Kiri, Map Canvas Tengah, Inspector Kanan, dan Atribut Bawah) menggunakan **MapLibre GL JS** untuk menampilkan, mendigitasi (`Draw LineString`), dan mengedit segmen fisik infrastruktur dengan pewarnaan tematik berdasarkan kondisi kerusakan dan badge verifikasi.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [x] Peta MapLibre merender layer segmen LineString PostGIS via format GeoJSON FeatureCollection.
- [x] Pewarnaan garis tematik otomatis berdasarkan `kondisi`:
  - **Baik**: Hijau (`#10b981`)
  - **Sedang**: Kuning (`#eab308`)
  - **Rusak Ringan**: Oranye (`#f97316`)
  - **Rusak Berat**: Merah (`#ef4444`)
- [x] Tersedia tool digitasi di atas canvas: Mode Gambar Garis Baru (`LineString`), edit titik verteks garis, dan kalkulasi otomatis panjang geodesik (`panjang_meter_gis`).
- [x] Panel Kanan (Inspector): Menampilkan detail segmen yang diklik di peta (namobj, dimensi panjang x lebar, tahun bangun, plotting terkait, status aset, dan galeri foto survei).
- [x] Form simpan segmen mengikat segmen ke alokasi Plotting Anggaran yang relevan dan menyimpannya sebagai status `draft`.

---

## 3. Rincian File yang Dibuat

1. **`apps/web/app/pages/admin/infrastruktur-segmen/index.vue`**:
   - Container Splitter 3 panel responsif desktop dan mobile bottom sheet.
   - Sinkronisasi state fitur yang dipilih (`selectedSegmenId`), layer aktif, dan bounding box (`bbox`).

2. **`apps/web/app/components/dataset/infrastruktur-segmen/MapCanvas.vue`**:
   - Inisialisasi MapLibre GL instance, basemap switcher (OSM, Satelit, Topo).
   - Layer Vector GeoJSON sumber dari endpoint `GET /api/v1/admin/infrastruktur-segmen?format=geojson`.
   - Tool interaksi: Maplibre GL Draw (atau custom handler click-to-draw garis).
   - Popup ringkas saat hover atau klik segmen garis.

3. **`apps/web/app/components/dataset/infrastruktur-segmen/LeftPanel.vue`**:
   - Filter tipe infrastruktur (Jalan, Jembatan, Drainase).
   - Filter kondisi fisik dan status verifikasi.
   - Filter wilayah (Kecamatan & Desa).
   - Tombol aktifkan mode digitasi "Tambah Segmen Baru".

4. **`apps/web/app/components/dataset/infrastruktur-segmen/RightPanel.vue` (Inspector & Form Editor)**:
   - Tab 1: Ringkasan Informasi Teknis & Galeri Foto.
   - Tab 2: Form Edit Atribut (nama ruas, lebar, kondisi, sumber data, status aset, kaitan plotting).
   - Tombol aksi simpan dan tombol pengajuan verifikasi cepat.

5. **`apps/web/app/components/dataset/infrastruktur-segmen/BottomPanel.vue`**:
   - Tabel ringkas seluruh segmen dalam viewport/filter dengan fitur zoom-to-feature saat baris diklik.

---

## 4. Checklist Tugas

- [x] Buat komponen `MapCanvas.vue` dengan MapLibre GL
- [x] Buat komponen `LeftPanel.vue` untuk filter tematik & tool digitasi
- [x] Buat komponen `RightPanel.vue` untuk inspeksi dan formulir atribut
- [x] Buat komponen `BottomPanel.vue` untuk tabel data atribut
- [x] Rakit seluruh komponen ke dalam `apps/web/app/pages/admin/infrastruktur-segmen/index.vue`
- [x] Uji alur digitasi garis dan penyimpanan koordinat ke PostGIS

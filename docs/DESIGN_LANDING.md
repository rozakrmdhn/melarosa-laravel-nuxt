# Spesifikasi Desain Portal Publik MELAROSA

**Cakupan Halaman Publik**: Beranda (`/`) · Katalog Dataset (`/dataset`) · Geopark (`/geopark` & `/geopark/[slug]`) · Statistik (`/statistik`)  
**Sistem**: MELAROSA (Sistem Informasi Geospasial & Infrastruktur Wilayah Kabupaten Bojonegoro)  
**Versi Dokumen**: 2.1  
**Pembaruan Terakhir**: 29 Oktober 2026  

---

## 1. Peran & Karakter Portal Publik

Portal publik MELAROSA berfungsi sebagai pintu akses terbuka bagi warga, peneliti, perangkat desa, dan instansi pemerintah untuk memeriksa kondisi jaringan jalan poros desa, menelusuri katalog dataset geospasial daerah, serta mempelajari 31 situs Geopark Nasional Bojonegoro.

Seluruh halaman publik dihubungkan oleh bilah navigasi utama (`PublicNavbar.vue`) dengan lima tujuan utama:
1. **Beranda (`/`)**: Ringkasan eksekutif geospasial daerah, pencarian cepat lintas objek, direktori 28 kecamatan, sorotan Geopark, dan pengantar lapisan peta.
2. **Peta (`/maps`)**: Kanvas eksplorasi WebGIS interaktif layar penuh (dibahas khusus pada [DESIGN_MAPS.md](./DESIGN_MAPS.md)).
3. **Katalog Dataset (`/dataset`)**: Direktori dataset dan inspektor metadata standar ISO 19139 / KUGI v5 dari Simpul Jaringan Informasi Geospasial Nasional (JIGN) Kabupaten Bojonegoro.
4. **Geopark (`/geopark` & `/geopark/[slug]`)**: Publikasi jurnal spasial dan artikel ensiklopedia lapangan untuk 31 situs Geopark Nasional Bojonegoro.
5. **Statistik (`/statistik`)**: Halaman analitik visual dan tabel rekapitulasi hierarki 4-level (Kecamatan, Desa, Ruas Jalan, hingga Segmen Fisik).

---

## 2. Prinsip Visual & Tata Letak Portal Publik

### 2.1. Hierarki Permukaan Tonal (*Tonal Surface & Gestalt Hierarchy*)

Portal publik menghindari penggunaan kotak bergaris tepi tebal yang berulang. Pengelompokan informasi dibangun melalui kontras permukaan warna yang lembut:

| Peran Permukaan | Mode Terang (*Light*) | Mode Gelap (*Dark*) | Penggunaan |
|---|---|---|---|
| **Kanvas Halaman** | `#F7F8F5` | `#070B14` | Latar dasar seluruh halaman publik |
| **Permukaan Fokus Utama** | `#FFFFFF` | `#0C1522` | Kartu utama, baris aktif, panel inspeksi kanan, dan isi modal |
| **Kontainer Tonal Sekunder** | `#EAEFE9` / `#F4F7F3` | `#111C2D` | Latar pembungkus grup metrik, daftar pilihan kecamatan, dan *header* tabel |
| **Garis Pemisah Halus** | `#E4EAE3` / `#ECEFEB` | `rgba(255, 255, 255, 0.08)` | Pemisah baris tabel dan batas antar seksi |
| **Aksen Hijau Institusional** | `#176B4D` / `#059669` | `#10B981` | Tombol utama, indikator jalan terbangun, dan status terverifikasi |
| **Aksen Oranye Konstruksi** | `#D97706` / `#F59E0B` | `#FBBF24` | Indikator sisa jalan belum terbangun |

### 2.2. Standar Navigasi, Modal, dan Angka

1. **Reset Posisi Gulir (*Scroll Reset*)**:
   - Perpindahan rute antar halaman publik (`Beranda | Peta | Katalog Dataset | Geopark | Statistik`) secara otomatis mengembalikan posisi gulir ke paling atas (`top: 0, left: 0`).
2. **Lapisan Modal di Atas Navigasi**:
   - `PublicNavbar.vue` berada pada lapisan `z-[70]`.
   - Seluruh dialog modal (`UModal` pada inspektor metadata `/dataset` maupun pratinjau peta ruas `/statistik`) wajib menggunakan `overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs'` dan `content: 'z-[110]'` agar tidak tertutup oleh bilah navigasi.
3. **Presisi Angka & Satuan**:
   - Gunakan kelas `tabular-nums` pada seluruh angka statistik, jarak, persentase, dan koordinat.
   - Angka panjang jalan dalam satuan kilometer (`km`) disajikan konsisten dengan **2 angka di belakang koma** menggunakan format lokal Indonesia (`id-ID`), misalnya `1.424,80 km` atau `60,39 km`.

---

## 3. Spesifikasi Halaman 1: Beranda (`/`)

**Berkas Utama**: [apps/web/app/pages/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/index.vue)

Beranda tersusun atas 7 bagian berurutan dengan komposisi visual yang berbeda sesuai fungsi informasinya:

```text
01 LandingHero        -> Split hero editorial (7:5) + pencarian terpadu + peta pratinjau OpenLayers aktif
02 RegionalMetrics    -> Pita dua tingkat: 4 skala wilayah + 5 metrik realisasi jalan dari API
03 DistrictExplorer   -> Direktori interaktif 28 kecamatan (daftar kiri + panel inspeksi sticky kanan)
04 GeoparkEditorial   -> Galeri jurnal 31 situs Geopark (1 cerita utama 7:5 + 2 sorotan + direktori tonal)
05 SpatialDataStory   -> Simulasi 3D isometrik tumpang susun 4 lapisan peta & pengatur urutan layer
06 MapGuide           -> Panduan praktis 4 langkah membaca dan mengunduh data peta
07 LandingCTA         -> Ajakan penutup menuju kanvas WebGIS (/maps) & katalog dataset (/dataset)
```

### 3.1. Bagian 01: Hero Editorial & Pratinjau Peta (`LandingHero.vue`)
- **Berkas**: [apps/web/app/components/landing/LandingHero.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/LandingHero.vue)
- **Kolom Kiri (Editorial & Pencarian)**:
  - Label pengantar: `Portal Spasial Resmi · Pemerintah Kabupaten Bojonegoro`
  - Judul utama (`H1`): `Peta Terbuka Infrastruktur & Wilayah Bojonegoro`
  - Pencarian cepat terpadu yang memindai 28 Kecamatan, 31 Situs Geopark, 4 Kategori Lapisan Peta, serta input koordinat langsung (`lintang, bujur` WGS 84).
  - Tombol aksi: `Buka Peta Interaktif` (menuju `/maps`) dan `Jelajahi 28 Kecamatan` (menggulir ke `#kecamatan`).
  - Pintasan *chip* kecamatan populer untuk membuka wilayah terkait langsung di peta.
- **Kolom Kanan (Pratinjau Peta Interaktif)**:
  - Kanvas OpenLayers hidup (`#landing-hero-map`) yang menampilkan geometri nyata Kabupaten Bojonegoro.
  - Dilengkapi 3 tombol pengalih tema pratinjau (`Ruas Jalan Poros`, `Geopark Nasional`, `Batas Kecamatan`), indikator koordinat kursor, dan tombol `Layar Penuh` menuju `/maps`.

### 3.2. Bagian 02: Skala Wilayah & Infrastruktur (`RegionalMetrics.vue`)
- **Berkas**: [apps/web/app/components/landing/RegionalMetrics.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/RegionalMetrics.vue)
- **Struktur Dua Tingkat dalam Kontainer Tonal (`bg-[#EAEFE9]/70`)**:
  - **Header**: Label `Skala Wilayah & Infrastruktur` dengan status koneksi data langsung dan tautan `Lihat rincian per kecamatan`.
  - **Baris Pertama (4 Metrik Cakupan Wilayah)**:
    1. `28` Kecamatan (`Wilayah koordinasi pembangunan & layanan publik`)
    2. `430` Desa & Kelurahan (`419 desa dan 11 kelurahan di seluruh kabupaten`)
    3. `2.307 km²` Luas Wilayah (`Bentang daratan di sepanjang lembah Sungai Bengawan Solo`)
    4. `31` Situs Geopark (`17 situs geologi, 5 biologi, dan 9 budaya terverifikasi`)
  - **Baris Kedua (5 Metrik Infrastruktur Jalan Poros dari `/rekap/kecamatan`)**:
    1. `Realisasi Pembangunan Jalan` (persentase capaian dilengkapi bilah progres visual)
    2. `Total Panjang Jalan` (`km`, 2 desimal)
    3. `Sudah Terbangun` (`km`, 2 desimal, kondisi perkerasan baik/terbangun)
    4. `Belum Terbangun` (`km`, 2 desimal, sisa panjang jalan dalam penanganan)
    5. `Ruas & Segmen` (total ruas terdata beserta cakupan desa)

### 3.3. Bagian 03: Direktori Infrastruktur 28 Kecamatan (`DistrictExplorer.vue`)
- **Berkas**: [apps/web/app/components/landing/DistrictExplorer.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/DistrictExplorer.vue)
- **Header**: Label `Eksplorasi Spasial 28 Kecamatan` dan judul `Direktori Infrastruktur Wilayah`.
- **Komposisi 12 Kolom (7 Kolom Daftar + 5 Kolom Panel Detail Sticky)**:
  - **Daftar Kecamatan (Kiri)**: Kontainer tonal dengan pencarian nama kecamatan, pengurutan (`Nama A-Z`, `Panjang Jalan`, `Progres %`), dan baris kecamatan yang menampilkan jumlah desa, jumlah ruas, panjang jalan (`km`, 2 desimal), serta persentase capaian.
  - **Panel Inspeksi Kecamatan (Kanan, Sticky)**: Permukaan putih bersih (`bg-white rounded-2xl`) yang memuat rincian kecamatan terpilih: persentase progres fisik, kartu metrik `Total Panjang Jalan (km)`, `Sudah Terbangun (km)`, `Belum Terbangun (km)`, rincian jumlah desa dan ruas, serta tombol `Buka Kecamatan di Peta Spasial` (`/maps?district=...`).

### 3.4. Bagian 04: Sorotan 31 Situs Geopark Nasional (`GeoparkEditorial.vue`)
- **Berkas**: [apps/web/app/components/landing/GeoparkEditorial.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/GeoparkEditorial.vue)
- **Header & Filter**: Label `Warisan Bumi & Budaya · Bojonegoro`, judul `31 Situs Geopark Nasional`, serta filter pilar (`Semua Situs (31)`, `Warisan Geologi (17)`, `Keragaman Biologi (5)`, `Warisan Budaya (9)`).
- **Hierarki Penyajian**:
  - **Cerita Utama (*Featured Lead Story*, Grid 7:5)**: Menampilkan foto lapangan terverifikasi Wikimedia Commons dengan atribusi lisensi, lokasi desa/kecamatan, koordinat, ringkasan geologi/budaya, tombol `Baca Artikel Lengkap` (`/geopark/{slug}`), dan tombol `Lihat di Peta` (`/maps?geosite={slug}`).
  - **2 Sorotan Pendamping**: Kartu editorial menengah di bawah cerita utama.
  - **Direktori Situs Lainnya**: Daftar tonal ringkas untuk berpindah cepat ke artikel detail situs atau titik peta, ditutup dengan tautan menuju halaman direktori penuh `/geopark`.

### 3.5. Bagian 05, 06, dan 07: Lapisan Spasial, Panduan Peta, dan Penutup
- **`SpatialDataStory.vue`** ([Berkas](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/SpatialDataStory.vue)): Simulasi interaktif 3D isometrik 4 lapisan peta (`Citra Satelit & Basemap`, `Batas Administrasi Desa & Kecamatan`, `Jaringan Jalan Poros & Kondisi`, `Situs Geopark & Titik Objek`) dengan fitur aktif/nonaktif dan pengubah urutan tumpukan layer.
- **`MapGuide.vue`** ([Berkas](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/MapGuide.vue)): Panduan 4 langkah penggunaan WebGIS (`01 Pilih Lapisan Data`, `02 Cari Kecamatan atau Koordinat`, `03 Klik Ruas atau Titik Situs`, `04 Bandingkan & Unduh Atribut`).
- **`LandingCTA.vue`** ([Berkas](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/landing/LandingCTA.vue)): Bagian penutup beranda dengan tombol menuju `/maps` dan `/dataset`.

---

## 4. Spesifikasi Halaman 2: Katalog Dataset (`/dataset`)

**Berkas Utama**: [apps/web/app/pages/dataset/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/dataset/index.vue)  
**Sumber Data**: Endpoint `/geoportal/datasets`, `/geoportal/datasets/{id}/metadata`, dan `/geoportal/datasets/{id}/attributes` (Simpul JIGN Kabupaten Bojonegoro).

### 4.1. Header & Strip Telemetri Simpul JIGN
- **Label Pengantar**: `Simpul Jaringan Informasi Geospasial Nasional (JIGN) · Kabupaten Bojonegoro`
- **Judul Halaman (`H1`)**: `Katalog Dataset & Metadata Geospasial Daerah`
- **Deskripsi**: Menjelaskan integrasi katalog layer spasial resmi Pemerintah Kabupaten Bojonegoro dengan standar **KUGI v5 (Katalog Unsur Geografi Indonesia)**, layanan **OGC WMS/WFS**, serta metadata **ISO 19115 / ISO 19139 (pyCSW 2.6.0)**.
- **Strip Telemetri (5 Metrik)**:
  1. `Dataset Terindeks` (jumlah layer geospasial publik)
  2. `Kategori Tematik` (jumlah klasifikasi KUGI)
  3. `Wali Data OPD` (jumlah dinas/instansi produsen data)
  4. `Standar Metadata` (`ISO 19139 & KUGI v5`)
  5. `Simpul Katalog Induk` (`geoportal.bojonegorokab.go.id`)

### 4.2. Pencarian, Filter Wali Data, dan Direktori Kartu Dataset
- **Bilah Filter Terpadu**:
  - Input pencarian instan berdasarkan judul dataset, abstrak, nama layer teknis, atau kata kunci.
  - Filter *dropdown* **Wali Data OPD** dan pengurutan (`Terbaru Diperbarui`, `Judul Dataset (A-Z)`, `Jumlah Fitur Spasial`).
  - Baris *chip* **Kategori Tematik** beserta jumlah dataset per kategori.
- **Kartu Dataset Tonal**:
  - Menampilkan lencana kategori tematik, kode unsur KUGI, tipe geometri (`Polygon`, `LineString`, `Point`), judul dataset, nama teknis layer, abstrak ringkas, instansi Wali Data OPD, jumlah fitur spasial, sistem koordinat `EPSG:4326 (WGS 84)`, serta pratinjau kolom atribut.
  - Dua tombol aksi utama pada setiap kartu:
    1. `Rincian Metadata & Skema` (membuka modal inspektor 4-tab).
    2. `Buka di Peta` (membuka `/maps?layer={layer_name}`).

### 4.3. Modal Inspektor Metadata 4-Tab (`z-[110]`)
Dialog modal ditempatkan di atas bilah navigasi (`overlay: z-[100]`, `content: z-[110]`) dan menyediakan 4 tab pemeriksaan teknis:
1. **Tab 1: Ringkasan & Layanan OGC**:
   - Menampilkan abstrak lengkap, kata kunci, profil Wali Data OPD, tingkat kualitas data, serta tautan salin cepat endpoint **OGC WMS**, **OGC WFS (GeoJSON)**, dan **CSW ISO 19139 XML**.
2. **Tab 2: Metadata ISO 19139 & Dublin Core**:
   - Menyajikan tabel atribut standar katalog `pyCSW 2.6.0` (`dc:identifier`, `dc:title`, `dc:type`, `dct:modified`, `dc:language`, `dc:creator`, `dc:publisher`, `dct:spatial`, `dc:rights`).
3. **Tab 3: Cakupan Spasial & BBOX WGS 84**:
   - Menampilkan diagram *bounding box* koordinat batas Utara, Selatan, Barat, dan Timur beserta titik tengah (`centroid`) dan tombol salin geometri **WKT Polygon**.
4. **Tab 4: Skema Atribut WFS & Kamus KUGI BIG**:
   - Menampilkan tabel struktur kolom atribut (`Nama Atribut Teknis`, `Label Kamus Data`, `Tipe Data`, dan `Keterangan Unsur KUGI`) yang dimuat dari endpoint `/geoportal/datasets/{id}/attributes`.

---

## 5. Spesifikasi Halaman 3: Direktori & Artikel Geopark (`/geopark` & `/geopark/[slug]`)

**Berkas Indeks**: [apps/web/app/pages/geopark/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/geopark/index.vue)  
**Berkas Artikel Detail**: [apps/web/app/pages/geopark/[slug].vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/geopark/%5Bslug%5D.vue)  
**Sumber Data**: Basis data terkurasi [geoparkData.ts](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/utils/geoparkData.ts) yang memuat 31 situs Geopark Nasional Bojonegoro beserta foto terverifikasi Wikimedia Commons.

### 5.1. Halaman Indeks Direktori (`/geopark`)
- **Header Editorial**:
  - Label pengantar: `Jurnal Spasial & Ensiklopedia Lapangan · Kabupaten Bojonegoro`
  - Judul halaman (`H1`): `Eksplorasi 31 Situs Geopark Nasional Bojonegoro`
- **Strip Ringkasan 3 Pilar Konservasi**:
  - `31` Total Situs Terverifikasi (tersebar di 19 kecamatan)
  - `17` Warisan Geologi (*Geoheritage*, sistem petroleum purba, antiklin, struktur batuan)
  - `5` Keragaman Biologi (*Biodiversity*, ekosistem hutan jati dan konservasi fauna)
  - `9` Warisan Budaya (*Cultural Heritage*, tradisi masyarakat, artefak, dan sejarah lokal)
- **Filter & Pencarian**:
  - Pencarian kata kunci (nama situs, desa/kecamatan, formasi geologi), filter tombol pilar konservasi, dan *dropdown* filter kecamatan.
- **Tata Letak Artikel**:
  - **Cerita Utama (*Featured Lead Article*, Grid 7:5)**: Tampil di bagian atas saat tidak ada filter pencarian aktif, menyajikan foto utama, klasifikasi pilar, waktu baca, koordinat, ringkasan eksekutif, serta tombol `Baca Artikel Lengkap` dan `Tampilkan di Peta`.
  - **Grid Direktori Artikel**: Kartu editorial berorientasi jurnal yang menampilkan foto situs, lencana pilar, nama kecamatan/desa, judul situs, ringkasan, estimasi waktu baca, tautan menuju `/geopark/[slug]`, dan pintasan menuju `/maps?geosite=[slug]`.

### 5.2. Halaman Bacaan Artikel Detail (`/geopark/[slug]`)
- **Masthead Artikel**:
  - Navigasi *breadcrumb* (`Beranda / Geopark / {Nama Situs}`), lencana pilar konservasi, estimasi waktu baca, judul lengkap (`H1`), paragraf pengantar (*lead*), lokasi desa dan kecamatan, serta koordinat format DMS dan desimal (`WGS 84`).
- **Galeri Dokumentasi Terverifikasi**:
  - Menampilkan foto utama dan galeri pendamping dari Wikimedia Commons lengkap dengan nama fotografer dan lisensi resmi (`CC BY-SA`).
- **Batang Tubuh Artikel (*Longform Editorial*)**:
  - Kotak sorotan **Signifikansi & Nilai Warisan Utama**.
  - Seksi artikel bernomor (`01`, `02`, dst.) dengan tipografi bacaan yang nyaman.
  - Blok khusus **Konteks Geologi & Sejarah Pembentukan** serta **Aksesibilitas & Panduan Kunjungan Lapangan**.
- **Sidebar Lembar Fakta Geospasial (Sticky Kanan)**:
  - Tabel ringkas berisi Kode Situs, Klasifikasi Pilar, Kecamatan, Desa/Kelurahan, Koordinat Lintang & Bujur, Elevasi (`mdpl`), Formasi/Era, dan Status Konservasi.
  - Tombol aksi langsung: `Tampilkan Titik di Peta Interaktif` (`/maps?geosite={slug}`), `Salin Koordinat`, dan `Buka Rute di Google Maps`.
- **Navigasi Kelanjutan Bacaan**:
  - Tautan artikel **Sebelumnya / Selanjutnya** (*Prev/Next*) dan daftar **Situs Terkait** pada pilar konservasi yang sama.

---

## 6. Spesifikasi Halaman 4: Statistik & Analitik Infrastruktur Jalan (`/statistik`)

**Berkas Utama**: [apps/web/app/pages/statistik/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/statistik/index.vue)  
**Komponen Modal Peta**: [apps/web/app/components/rekap-jalan/ModalPreviewPeta.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/rekap-jalan/ModalPreviewPeta.vue)  
**Sumber Data**: Composable `useRekapJalanApi()` yang memanggil endpoint `/rekap/kecamatan`, `/rekap/desa`, `/rekap/desa/{id}/ruas`, dan `/rekap/ruas/{id}/segmen` (selaras dengan halaman operasional `/admin/rekap-jalan`).

### 6.1. Header Eksekutif & Sakelar Ruang Lingkup Data
- **Label Pengantar**: `Data Infrastruktur Jalan · Kabupaten Bojonegoro`
- **Judul Halaman (`H1`)**: `Statistik & Analitik Infrastruktur Jalan`
- **Kontrol Ruang Lingkup & Ekspor**:
  - Sakelar ruang lingkup data (*segmented switch*):
    1. `Ruas Jalan Poros Desa (Dalam Database)` (`scope = 'ruas'`)
    2. `Jalan Desa Non-Ruas (Diluar Database)` (`scope = 'non_ruas'`)
  - Tombol `Segarkan Data`, `Unduh CSV`, dan `Unduh Laporan Excel (.xlsx)`.

### 6.2. Baris 4 Kartu KPI Eksekutif
Menampilkan ringkasan agregat seluruh Kabupaten Bojonegoro dengan angka `tabular-nums`:
1. **Total Panjang Jaringan**: Total panjang jalan (`km`, 2 desimal) beserta jumlah kecamatan dan desa/kelurahan.
2. **Sudah Terbangun**: Panjang jalan kondisi terbangun (`km`, 2 desimal), persentase terhadap total jaringan, dan jumlah ruas terdata.
3. **Belum Terbangun**: Sisa panjang jalan yang memerlukan penanganan (`km`, 2 desimal) beserta persentase sisa beban pembangunan.
4. **Capaian Fisik Daerah**: Dilengkapi visualisasi **SVG Ring Gauge** persentase realisasi kabupaten serta rentang capaian kecamatan tertinggi hingga terendah.

### 6.3. Seksi Grafik Komparatif & Klaster Kinerja (Grid 12 Kolom)
Mengombinasikan grafik proporsi fisik di sisi kiri (8 kolom) dengan analisis klaster dan peringkat di sisi kanan (4 kolom):

1. **Grafik Batang Bertumpuk Horizontal (8 Kolom Kiri)**:
   - **Label**: `Grafik Komparasi Spasial Antar Kecamatan`
   - **Judul**: `Proporsi Panjang Terbangun vs Sisa Belum Terbangun (km)`
   - **Urutan Grafik**: Diurutkan berdasarkan **total panjang jalan (`km`) tertinggi ke terendah** (`chartSortedKecamatan`), dengan sakelar tampilan `Semua (28)` atau `14 Teratas`.
   - **Interaksi**: Mengklik baris kecamatan pada grafik akan membuka rincian desa kecamatan tersebut dan menggulir halaman langsung ke baris terkait pada tabel di bawah.
   - **Strip Ringkasan Bawah Grafik**: Menampilkan 3 metrik pembanding (`Jaringan Terpanjang`, `Rata-rata per Kecamatan`, dan `Realisasi Total`) di bagian kaki kartu grafik agar tinggi kolom kiri dan kanan seimbang tanpa menyisakan ruang kosong.
2. **Distribusi Capaian 28 Kecamatan (4 Kolom Kanan Atas)**:
   - Mengelompokkan kecamatan ke dalam 4 klaster interaktif yang dapat diklik untuk menyaring tabel:
     - `Capaian Sangat Tinggi` (`>= 80%`)
     - `Capaian Tinggi` (`60% s/d < 80%`)
     - `Capaian Menengah` (`40% s/d < 60%`)
     - `Prioritas Percepatan` (`< 40%`)
3. **Peringkat Cepat Kecamatan (4 Kolom Kanan Bawah)**:
   - Kartu **5 Kecamatan Progres Tertinggi** (persentase realisasi tertinggi) dan **5 Sisa Belum Terbangun Terbesar** (panjang kilometer belum terbangun terbesar).

### 6.4. Filter Multi-Dimensi & Tabel Hierarki 4-Level
- **Bilah Filter Tabel**:
  - Pencarian nama kecamatan, filter **Tahun Anggaran**, filter **Kondisi Perkerasan** (`Beton / Rigid`, `Aspal / Lapen`, `Paving`, `Tanah / Makadam`), filter **Sumber Pendanaan**, serta pengurutan kecamatan dengan nilai bawaan **`Wilayah Administrasi (A ke Z)`**.
  - Tombol `Buka Semua Desa` dan `Tutup Semua` untuk mempercepat pemeriksaan lintas wilayah.
- **Tabel Rekapitulasi Hierarki 4-Level (`Rincian Pembangunan Infrastruktur Jalan`)**:
  - **Level 1 (Kecamatan)**: Diurutkan secara bawaan berdasarkan kolom **Wilayah Administrasi secara menaik (`asc` A sampai Z)**. Menampilkan jumlah desa, jumlah ruas, total panjang (`km`), panjang terbangun (`km`), sisa belum terbangun (`km`), serta bilah persentase capaian.
  - **Level 2 (Desa / Kelurahan)**: Dimuat saat baris kecamatan dibuka, diurutkan `asc` A-Z berdasarkan nama desa, menampilkan metrik agregat tingkat desa.
  - **Level 3 (Ruas Jalan)**: Dimuat saat baris desa dibuka (`/rekap/desa/{id}/ruas`), diurutkan `asc` A-Z, menampilkan nomor ruas, nama ruas, panjang, capaian, jumlah segmen, dan tombol **Peta** untuk membuka pratinjau geometri ruas.
  - **Level 4 (Segmen Fisik Perkerasan)**: Dimuat saat baris ruas dibuka (`/rekap/ruas/{id}/segmen`), menampilkan rincian per segmen: jenis perkerasan, kondisi fisik, tahun anggaran pembangunan, sumber pendanaan, panjang segmen (`m` / `km`), koordinat awal-akhir, dan tombol pratinjau peta segmen (`ModalPreviewPeta.vue`).
  - **Baris Kaki Tabel (`Total Agregat Kabupaten`)**: Merangkum total desa, total ruas, total panjang jaringan (`km`), total terbangun (`km`), total belum terbangun (`km`), dan persentase realisasi keseluruhan.

---

## 7. Arsitektur Berkas Halaman Publik

```text
apps/web/app/
├── app.vue                                      # Layout induk dengan PublicNavbar & Footer
├── app.config.ts                                # Konfigurasi global Nuxt UI (termasuk z-index modal z-[100]/z-[110])
├── router.options.ts                            # Konfigurasi scrollBehavior (reset top: 0 pada perpindahan halaman)
├── pages/
│   ├── index.vue                                # Halaman 1: Beranda Publik (/)
│   ├── dataset/
│   │   └── index.vue                            # Halaman 2: Katalog Dataset & Metadata JIGN (/dataset)
│   ├── geopark/
│   │   ├── index.vue                            # Halaman 3a: Direktori Editorial 31 Situs Geopark (/geopark)
│   │   └── [slug].vue                           # Halaman 3b: Artikel Detail Situs Geopark (/geopark/[slug])
│   └── statistik/
│       └── index.vue                            # Halaman 4: Statistik & Analitik Infrastruktur Jalan (/statistik)
└── components/
    ├── app/
    │   ├── PublicNavbar.vue                     # Navigasi publik mengambang (z-[70])
    │   └── Footer.vue                           # Footer institusional resmi
    ├── landing/
    │   ├── LandingHero.vue                      # Hero editorial + pratinjau peta OpenLayers
    │   ├── RegionalMetrics.vue                  # Pita skala wilayah & metrik jalan poros
    │   ├── DistrictExplorer.vue                 # Direktori interaktif 28 kecamatan
    │   ├── GeoparkEditorial.vue                 # Sorotan editorial 31 situs Geopark
    │   ├── SpatialDataStory.vue                 # Simulasi 3D isometrik lapisan peta
    │   ├── MapGuide.vue                         # Panduan 4 langkah eksplorasi WebGIS
    │   └── LandingCTA.vue                       # Ajakan penutup menuju peta & katalog dataset
    └── rekap-jalan/
        └── ModalPreviewPeta.vue                 # Modal pratinjau geometri ruas & segmen jalan (z-[110])
```

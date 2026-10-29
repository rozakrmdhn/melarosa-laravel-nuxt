# Arsitektur & Standar Sistem Desain UI/UX: MELAROSA

**Aplikasi**: MELAROSA (Sistem Informasi Geospasial & Infrastruktur Wilayah Kabupaten Bojonegoro)  
**Versi Dokumen**: 2.1  
**Pembaruan Terakhir**: 29 Oktober 2026  
**Status**: Standar Desain Aktif  

---

## 1. Pembagian Spesifikasi Desain Menurut Domain Sistem

Dokumentasi desain MELAROSA dibagi ke dalam 3 dokumen spesifikasi sesuai domain rute dan kebutuhan pengguna di dalam direktori `docs/`:

| Domain Antarmuka | Rute Aplikasi | Dokumen Spesifikasi | Cakupan Halaman & Peran Antarmuka |
|---|---|---|---|
| **1. Portal Publik & Editorial Geospasial** | `/`, `/dataset`, `/geopark`, `/geopark/[slug]`, `/statistik` | [DESIGN_LANDING.md](./DESIGN_LANDING.md) | Beranda publik (`/`), Katalog Dataset & Metadata Simpul JIGN (`/dataset`), Direktori & Artikel Editorial 31 Situs Geopark (`/geopark` & `/geopark/[slug]`), serta Statistik & Analitik Infrastruktur Jalan 4-level (`/statistik`). |
| **2. Peta Spasial WebGIS** | `/maps` | [DESIGN_MAPS.md](./DESIGN_MAPS.md) | Kanvas peta interaktif OpenLayers (`split view` desktop, `bottom sheet` 3 tingkat pada layar seluler, integrasi Martin MVT, OGC WMS/WFS GeoServer, pencari lokasi, pelacakan GPS, dan inspektor atribut). |
| **3. Panel Operasional Admin** | `/admin` | [DESIGN_ADMIN.md](./DESIGN_ADMIN.md) | Ruang kerja pengelolaan data spasial berbasis peran (Superadmin, BAPPEDA, Verifikator Kecamatan, Operator Desa), rekapitulasi jalan poros, manajemen layer, audit log, dan editor geometri. |

---

## 2. Identitas Visual & Prinsip Inti

MELAROSA menyajikan data infrastruktur pekerjaan umum, batas administrasi, dan kawasan Geopark Nasional Bojonegoro melalui antarmuka yang jernih, terstruktur, dan mudah diverifikasi oleh masyarakat maupun perangkat daerah.

### Prinsip Desain Utama

1. **Data Terverifikasi & Bukti Nyata**:
   - Seluruh angka, tabel, dan grafik bersumber dari basis data spasial serta endpoint resmi Pemerintah Kabupaten Bojonegoro: **28** Kecamatan, **430** Desa/Kelurahan, **2.307 km²** Luas Wilayah, jaringan jalan poros desa beserta segmen perkerasan dari endpoint `/rekap/kecamatan`, serta **31** Situs Geopark Nasional Bojonegoro (**17** situs geologi, **5** situs biologi, **9** situs budaya).
   - Jangan menampilkan angka karangan, klaim tanpa rujukan, atau kutipan testimoni fiktif.
2. **Hierarki Permukaan Tonal (*Tonal Surface & Gestalt Hierarchy*)**:
   - Halaman publik menggunakan pergeseran warna latar yang lembut (`#F7F8F5` kanvas utama, `#EAEFE9` kontainer sekunder, `#FFFFFF` permukaan fokus) untuk mengelompokkan informasi secara alami tanpa menumpuk garis tepi (*border*) tebal di setiap kartu.
3. **Kejelasan Kartografi**:
   - Kanvas peta menggunakan *basemap* netral agar garis jaringan jalan, batas desa/kecamatan, dan titik situs Geopark langsung terbaca tanpa gangguan warna latar.
4. **Ergonomi Sentuh & Fungsi Nyata**:
   - Target sentuh kontrol interaktif berukuran minimal `44 x 44 px` pada layar seluler.
   - Setiap tombol, tab, filter, dan tautan wajib menjalankan fungsi nyata.
5. **Navigasi Bersih Antar Halaman**:
   - Setiap perpindahan rute pada halaman publik (`Beranda | Peta | Katalog Dataset | Geopark | Statistik`) mereset posisi gulir kembali ke bagian paling atas (`top: 0, left: 0`).
6. **Gaya Bahasa & Tanda Baca**:
   - Gunakan kalimat bahasa Indonesia yang lugas, spesifik, dan informatif.
   - Hindari penggunaan tanda pisah em dash, klaim promosi berlebihan, atau istilah teknis yang tidak dijelaskan konteksnya.

---

## 3. Token Desain Terpadu (Global Design Tokens)

### 3.1. Palet Warna Sistem

| Token / Peran Visual | Mode Terang (*Light*) | Mode Gelap (*Dark*) | Penggunaan Utama |
|---|---|---|---|
| **Kanvas Portal Publik** | `#F7F8F5` (Warm Alabaster) | `#070B14` (Obsidian Night) | Latar utama halaman `/`, `/dataset`, `/geopark`, dan `/statistik` |
| **Permukaan Fokus / Kartu Aktif** | `#FFFFFF` (Pure White) | `#0C1522` / `#0F172A` | Kartu metrik utama, panel inspeksi, baris tabel aktif, dan dialog modal |
| **Permukaan Tonal Sekunder** | `#EAEFE9` / `#F4F7F3` | `#111C2D` | Kontainer pengelompokan daftar, latar *header* tabel, dan *chip* filter pasif |
| **Garis Pemisah Halus (*Hairline*)** | `#E4EAE3` / `#ECEFEB` | `rgba(255, 255, 255, 0.08)` | Pemisah baris tabel dan batas antar seksi editorial |
| **Teks Utama** | `#14261E` / `#0F172A` | `#F8FAFC` | Judul halaman, angka KPI, dan isi artikel |
| **Teks Sekunder & Metadata** | `#4E5E56` / `#5A6B62` | `#94A3B8` | Keterangan satuan, label kolom, dan abstrak |
| **Aksen Hijau Institusional** | `#176B4D` / `#059669` | `#10B981` | Identitas utama portal publik, jalan terbangun, dan status terverifikasi |
| **Aksen Biru Spasial** | `#2563EB` | `#3B82F6` | Tautan koordinat, layanan OGC WMS/WFS, dan kontrol aktif WebGIS |
| **Aksen Oranye Konstruksi** | `#D97706` / `#F59E0B` | `#FBBF24` | Indikator sisa jalan belum terbangun dan plotting anggaran BKKD |
| **Aksen Merah Peringatan** | `#DC2626` / `#E11D48` | `#F87171` | Kondisi perkerasan rusak berat, prioritas percepatan (`< 40%`), dan status galat |

### 3.2. Standar Tipografi

- **Keluarga Huruf**: `Plus Jakarta Sans` atau `Inter` untuk judul dan antarmuka; keluarga huruf sans-serif proporsional sistem untuk teks bacaan panjang.
- **Angka Tabular (`tabular-nums`)**:
  - Seluruh angka statistik, panjang jalan (`km` dengan 2 angka desimal atau `m`), persentase capaian (`%`), luas wilayah (`km²`), dan koordinat geospasial (`WGS 84`) wajib menggunakan `font-variant-numeric: tabular-nums` (`tabular-nums`) agar sejajar secara vertikal pada tabel maupun grafik.
- **Penggunaan Huruf Monospace**:
  - Jangan gunakan `font-mono` untuk label antarmuka, lencana kategori, atau angka tabel. Huruf monospace hanya digunakan bila menampilkan blok kode atau kueri mentah.

### 3.3. Hierarki Lapisan (*Z-Index Layering*)

Untuk mencegah tumpang tindih antara bilah navigasi mengambang dan jendela dialog pada seluruh halaman publik maupun peta:

| Elemen Antarmuka | Kelas `z-index` | Keterangan |
|---|---|---|
| Konten Halaman & Kartu Sticky | `z-10` s/d `z-30` | Panel detail kecamatan, sidebar artikel Geopark, dan header tabel |
| Navigasi Utama (`PublicNavbar.vue`) | `z-[70]` | Bilah navigasi mengambang di bagian atas layar |
| Overlay Modal (`UModal`) | `z-[100]` | Latar redup (`bg-slate-950/65 backdrop-blur-xs`) yang menutup seluruh halaman termasuk navbar |
| Konten Dialog Modal (`UModal`) | `z-[110]` | Jendela inspektor metadata JIGN (`/dataset`), modal metadata peta, dan pratinjau peta ruas (`ModalPreviewPeta.vue`) |

---

## 4. Standar Kualitas Antarmuka & Penulisan Konten

Seluruh halaman dan komponen wajib memenuhi ketentuan berikut:

| Aspek | Ketentuan Implementasi |
|---|---|
| **Komposisi Layout** | Gunakan tata letak yang sesuai dengan bentuk data: kombinasi *split hero* `7:5`, kartu *featured* editorial, grafik batang bertumpuk horizontal, dan tabel hierarki 4-level. Hindari deretan kartu seragam yang berulang di setiap bagian. |
| **Format Angka & Satuan** | Gunakan format angka lokal Indonesia (`id-ID`, koma untuk desimal dan titik untuk ribuan). Panjang jalan dalam kilometer ditampilkan dengan 2 angka desimal (contoh: `1.424,80 km` atau `60,39 km`). |
| **Label & Teks Aksi (CTA)** | Gunakan kata kerja spesifik yang menjelaskan tujuan halaman atau aksi (contoh: `Buka Peta Interaktif`, `Rincian Metadata & Skema`, `Baca Artikel Lengkap`, `Unduh Laporan Excel`). |
| **Kelengkapan Status Data** | Setiap komponen yang memuat data dari API wajib menangani 3 kondisi: *Loading* (skeleton proporsional), *Empty* (pesan jelas beserta tombol reset filter), dan *Error* (pesan kendala beserta tombol `Coba Lagi`). |
| **Keterbacaan & Kontras** | Pastikan rasio kontras teks memenuhi standar WCAG AA (minimal `4,5:1` untuk teks normal) baik pada mode terang maupun mode gelap. |
| **Responsivitas Seluler** | Hindari *horizontal overflow* pada layar ponsel (`320px` ke atas). Gunakan pembungkus gulir horizontal (`overflow-x-auto`) khusus pada tabel data multi-kolom. |

---

## 5. Direktori Berkas Spesifikasi & Implementasi

1. **Portal Publik & Editorial Geospasial (`/`, `/dataset`, `/geopark`, `/statistik`)**:
   - Dokumen Spesifikasi: [DESIGN_LANDING.md](./DESIGN_LANDING.md)
   - Beranda: [apps/web/app/pages/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/index.vue)
   - Katalog Dataset & Metadata JIGN: [apps/web/app/pages/dataset/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/dataset/index.vue)
   - Direktori & Artikel Geopark: [apps/web/app/pages/geopark/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/geopark/index.vue) dan [apps/web/app/pages/geopark/[slug].vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/geopark/%5Bslug%5D.vue)
   - Statistik & Analitik Infrastruktur Jalan: [apps/web/app/pages/statistik/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/statistik/index.vue)
2. **Peta Spasial WebGIS (`/maps`)**:
   - Dokumen Spesifikasi: [DESIGN_MAPS.md](./DESIGN_MAPS.md)
   - Implementasi: [apps/web/app/pages/maps/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/maps/index.vue) dan [apps/web/app/components/maps/](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/)
3. **Panel Operasional Admin (`/admin`)**:
   - Dokumen Spesifikasi: [DESIGN_ADMIN.md](./DESIGN_ADMIN.md)
   - Implementasi: [apps/web/app/pages/admin.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin.vue) dan [apps/web/app/pages/admin/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/index.vue)

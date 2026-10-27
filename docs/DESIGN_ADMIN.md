# Paten Desain & Spesifikasi Sistem UI/UX: Admin Dashboard Spasial & Infrastruktur (/admin)
**Aplikasi**: Melarosa - Sistem Informasi Spasial Kabupaten Bojonegoro  
**Versi Spesifikasi**: 2.0 (Paten Resmi Desain Admin Dashboard)  
**Tanggal Pengesahan**: 27 Oktober 2026  
**Status**: Standar Resmi & Mengikat (*Binding Architectural Standard*)  
**Implementasi Berkas Sumber**:
- Shell Tata Letak: [apps/web/app/pages/admin.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin.vue)
- Komponen Sidebar: [apps/web/app/components/admin/Sidebar.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/admin/Sidebar.vue)
- Komponen Navbar: [apps/web/app/components/admin/Navbar.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/admin/Navbar.vue)
- Halaman Utama Dashboard: [apps/web/app/pages/admin/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/index.vue)

---

## 1. Filosofi & Prinsip Inti Desain (Core Tenets)

Admin Dashboard (`/admin`) Melarosa adalah **antarmuka pusat komando operasional geospasial dan infrastruktur** bagi jajaran Pemerintah Kabupaten Bojonegoro (BAPPEDA, Dinas PU Bina Marga, Administrator Sistem, Verifikator Tingkat Kecamatan, dan Operator Desa).

Prinsip dasar yang wajib dipatuhi:

1. **Tata Letak Berorientasi Keputusan Operasional (*Decision-First Dashboard Layout - Anti-Slop C-3 & R-20*)**:
   - Menolak keras pola template AI generik (sidebar kiri + bilah atas + 4 kartu angka acak + grafik hiasan + tabel pasif).
   - Setiap elemen antarmuka hadir untuk menjawab pertanyaan kerja spesifik pengguna:
     - *"Berapa banyak ruas jalan poros yang berstatus Rusak Berat dan membutuhkan intervensi anggaran?"*
     - *"Apakah data spasial dari Geoserver simpul JIGN Pemkab Bojonegoro telah tersinkronisasi hari ini?"*
     - *"Berapa progres digitasi segmen perkerasan jalan di wilayah kecamatan/desa binaan saya?"*
2. **Kebenaran Data Mutlak Berbasis Basis Data Terverifikasi (*Zero Fabricated Numbers - R-17 & R-38*)**:
   - Dilarang keras menampilkan angka statistik palsu, persentase delta tren rekayasa ("+12% minggu ini"), atau grafik hiasan tanpa sumbu nyata.
   - Seluruh kartu metrik, diagram kondisi, dan tabel terhubung langsung secara reaktif ke endpoint API backend Laravel:
     - **1.187** Ruas Jalan Poros Desa terdata (**1.424,8** km).
     - Breakdown kondisi fisik nyata: **791** ruas Sedang (67%), **194** ruas Rusak Berat (16%), **116** ruas Rusak Ringan (10%), dan **80** ruas Rusak Sedang (7%).
     - **28** Kecamatan dan **430** Desa terdata dengan luas wilayah **238.400** Hektar.
     - Riwayat sinkronisasi metadata layer dari simpul Geoportal JIGN Bojonegoro.
3. **Konteks Teritorial Berbasis Peran Pengguna (*Role & Territory Context Awareness*)**:
   - Antarmuka secara cerdas beradaptasi dengan tingkatan hak akses pengguna:
     - **Superadmin**: Akses penuh lintas modul dan pengelolaan sistem keamanan.
     - **Bappeda / OPD Pembina**: Pengawasan perencanaan, pemantauan jalan poros, dan integrasi simpul JIGN.
     - **Verifikator Kecamatan**: Fokus verifikasi data teknis 28 kecamatan spesifik.
     - **Operator Desa**: Penginputan dan pemutakhiran segmen fisik di tingkat desa.
   - Konteks wilayah teritorial pengguna (`userKecamatanName`, `userDesaName`) disajikan secara tegas di bagian salam puncak dashboard.
4. **Tampilan Ganda Fleksibel: Workspace Standar & Kanvas Penuh (*Dual Viewport: Standard vs Full-Bleed Spatial Editor*)**:
   - Halaman tabel data dan ikhtisar menggunakan format standar terstruktur dengan padding responsif (`p-4 sm:p-6 overflow-y-auto`).
   - Halaman editor spasial peta dan digitasi segmen mendukung mode kanvas penuh (`$route.meta.fullBleed: true`) tanpa padding luar (`p-0 overflow-hidden relative`), memaksimalkan ruang manipulasi geometri OpenLayers di layar kerja admin.
5. **Kepatuhan Mutlak Anti-Slop (*Strict Anti-Slop Discipline*)**:
   - **Bebas Em Dash (R-02)**: Dilarang keras menggunakan karakter em dash (`-`). Gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau kurung `()`.
   - **Zero Dead Buttons (R-26)**: Setiap tombol aksi pada tabel (Edit Geometri, Verifikasi, Ekspor Data, Filter Kecamatan) memiliki fungsi nyata.
   - **Ketahanan Tiga Status UI (R-27)**: Setiap modul data wajib memiliki penanganan status lengkap:
     - *Loading State*: Skeleton loader yang mempertahankan bentuk layout tabel/kartu asli (bukan layar kosong).
     - *Empty State*: Penjelasan penyebab kekosongan data dan satu tombol aksi jelas untuk mengisinya ("Belum ada data segmen. Mulai digitasi sekarang").
     - *Error State*: Penjelasan kesalahan yang ramah dan tombol coba lagi (*Retry*).
   - **Kedua Mode Tema Wajib Berfungsi Sempurna (R-21 & R-34)**: Mode Terang (*Light Mode*) dan Mode Gelap (*Dark Mode*) teruji memiliki kontras tajam (WCAG AA) dan kejelasan visual yang setara.
   - **Dials Antislop**: ENERGY: 1 (Fokus tinggi, presisi kartografis, bebas ornamen berlebih), RHYTHM: 2 (Hierarki tabular konsisten), MOTION: 1 (Fungsional, transisi tab dan dialog halus).

---

## 2. Arsitektur Shell Dashboard & Navigasi

```
+-------------------------------------------------------------------------------------------------------+
| [ UDashboardGroup: Shell Utama Admin (h-screen w-screen overflow-hidden) ]                             |
+------------------------------------+------------------------------------------------------------------+
| [ ADMIN SIDEBAR: aside w-64/w-72 ] | [ ADMIN NAVBAR: header h-16 border-b border-gray-200/dark:border-white/8 ] |
|                                    | Breadcrumbs Hierarkis | Search Quick Jump | Tema | Profil Admin |
| 1. Header:                         +------------------------------------------------------------------+
|    - Logo Pemkab Bojonegoro        | [ MAIN WORKSPACE CONTAINER (flex-1 overflow-y-auto bg-gray-50) ] |
|    - Melarosa Spatial System       |                                                                  |
|    - Badge Versi 2.0               | A. GREETING & TERRITORY CONTEXT BAR                              |
|                                    |    - Salam Pengguna & Label Peran (Superadmin / OPD / Operator)  |
| 2. Navigasi Modular Berkelompok:   |    - Teritori Aktif: "Kecamatan Dander · Desa Kunci"             |
|    - [IKHTISAR SPASIAL]            |    - Tombol Tindakan Cepat: [Segarkan Data Global (Refresh)]     |
|      * Dashboard Utama (/admin)    |                                                                  |
|    - [INFRASTRUKTUR & JALAN]       | B. BARISAN KARTU KEPUTUSAN OPERASIONAL (Verified DB Metrics)     |
|      * Rekap Jalan Poros           |    1. Jalan Poros: 1.187 Ruas · 1.424,8 km (Bar Kondisi 4 Warna) |
|      * Segmen Fisik Infrastruktur  |    2. Batas Wilayah: 28 Kecamatan · 430 Desa · 238.400 Ha        |
|      * Referensi Tipe Perkerasan   |    3. Segmen Fisik: Detail Perkerasan Rigid & Aspal              |
|    - [BATAS & DATA SPASIAL]        |    4. Simpul JIGN: Status Koneksi Geoserver & Layer Terindeks    |
|      * Katalog Dataset Geospasial  |                                                                  |
|      * Layer Tematik JIGN          | C. VISUALISASI BERORIENTASI PERTANYAAN (Actionable Charts)       |
|    - [ANGGARAN & PEMBANGUNAN]      |    - Komposisi Kondisi Jalan Poros (Sedang, Rusak Berat/Ringan)  |
|      * BKKD Desa                   |    - Timeline Sinkronisasi Layer Spasial Simpul JIGN             |
|      * Plotting Anggaran Geometri  |                                                                  |
|      * Referensi Sumber Dana       | D. TABEL REKAPITULASI DENGAN FILTER & AKSI OPERASIONAL           |
|    - [TATA KELOLA & KEAMANAN]      |    - Pencarian Cepat Ruas & Filter Kecamatan Bertingkat          |
|      * Manajemen Pengguna (Users)  |    - Kolom Informatif: Kode Ruas, Nama, Panjang, Status, Aksi    |
|      * Peran & Hak Akses (Roles)   |    - Aksi Baris: Edit Spasial, Inspeksi Atribut, Hapus           |
|      * Log Audit Aktivitas         |                                                                  |
|                                    | E. MODAL DIALOG & DRAWER EDIT SPASIAL                            |
| 3. Footer:                         |    - Digitasi Geometri Peta Terintegrasi                         |
|    - User Avatar & Nama Lengkap    |    - Form Validasi KUGI dengan Penanganan Error Reaktif          |
|    - Tombol Pengaturan & Keluar    +------------------------------------------------------------------+
+------------------------------------+------------------------------------------------------------------+
```

---

## 3. Spesifikasi Modul Dashboard Utama (`/admin/index.vue`)

### 3.1. Territory Greeting & Action Bar

1. **Penyajian Konteks Pengguna**:
   - Menampilkan ucapan selamat datang kontekstual disertai nama lengkap pengguna yang sedang aktif.
   - Menyajikan badge peran resmi:
     - `Superadmin`: Warna primer (*primary*).
     - `Bappeda / OPD`: Warna info biru (*info*).
     - `Verifikator Kecamatan`: Warna peringatan jingga (*warning*).
     - `Operator Desa`: Warna hijau stabil (*success*).
   - Menampilkan wilayah kerja teritorial aktif jika pengguna dibatasi oleh kecamatan atau desa tertentu (misal: `"Kecamatan Dander · Desa Kunci"`).
2. **Tombol Tindakan Global**:
   - Tombol **"Segarkan Data"** (`handleRefreshAll`): Mengeksekusi pembaruan paralel reaktif untuk data batas kecamatan, desa, jalan poros, segmen infrastruktur, dan status simpul jaringan Geoportal secara bersamaan tanpa memuat ulang seluruh halaman peramban.

---

### 3.2. Barisan Kartu Metrik Operasional (Empat Pilar Data Riil)

Setiap kartu dirancang dengan hierarki visual tegas dan bebas dari angka rekayasa:

1. **Kartu 1: Jaringan Jalan Poros Desa (`jalanPorosSummary`)**:
   - **Metrik Utama**: `1.187` Ruas, Total Panjang `1.424,8` km (`1.424.770` meter).
   - **Visualisasi Komposisi Kondisi Fisik Real-Time**:
     - *Sedang*: 791 ruas (67%) dengan indikator biru langit (`bg-sky-500`).
     - *Rusak Berat*: 194 ruas (16%) dengan indikator merah cerah (`bg-rose-500`).
     - *Rusak Ringan*: 116 ruas (10%) dengan indikator amber (`bg-amber-500`).
     - *Rusak Sedang*: 80 ruas (7%) dengan indikator jingga (`bg-orange-500`).
   - **Tindakan Cepat**: Tombol tautan langsung menuju halaman rekapitulasi ruas jalan (`/admin/rekap-jalan`).
2. **Kartu 2: Batas Wilayah Administrasi (`kecSummary` & `desaSummary`)**:
   - **Metrik Utama**: `28` Kecamatan, `430` Desa dan Kelurahan.
   - **Cakupan Wilayah**: Luas terpetakan `238.400` Hektar.
   - **Tindakan Cepat**: Menuju modul manajemen dataset batas wilayah (`/admin/dataset`).
3. **Kartu 3: Segmen Fisik Infrastruktur (`segmenSummary`)**:
   - **Metrik Utama**: Rincian pemotongan segmen perkerasan jalan poros (Rigid Beton, Aspal, dan Kerikil/Makadam).
   - **Tindakan Cepat**: Menuju editor segmen infrastruktur (`/admin/infrastruktur-segmen`).
4. **Kartu 4: Status Simpul Geoportal JIGN Bojonegoro (`geoportalStatus`)**:
   - **Metrik Utama**: Status konektivitas Geoserver Pemkab Bojonegoro (*Online / Terhubung*).
   - **Waktu Sinkronisasi Terakhir**: Diformat ramah pengguna (contoh: `"24 menit lalu"` atau tanggal spesifik).
   - **Jumlah Lapisan**: Total layer tematik yang terindeks secara otomatis dari CSW / WMS.
   - **Timeline Pembaruan**: Grafik mini histori sinkronisasi layer spasial.

---

### 3.3. Arsitektur Tabel Data Spasial & Rekapitulasi

Setiap tabel admin dalam sistem Melarosa tunduk pada standar kepatuhan operasional:

1. **Kolom Berorientasi Keputusan (*Decisive Columns*)**:
   - Kolom tabel ditentukan oleh informasi yang dibutuhkan admin untuk mengambil langkah selanjutnya.
   - Urutan kolom standar tabel jalan poros:
     1. *Kode Ruas Resmi KUGI* (Identitas unik nasional).
     2. *Nama Ruas Jalan Poros* (Nama ruas penghubung antardesa).
     3. *Wilayah Kecamatan* (Cakupan administrasi).
     4. *Panjang Ruas (km)* (Disajikan dalam format `tabular-nums` dengan 1 desimal).
     5. *Status Kondisi* (Badge warna: Baik, Sedang, Rusak Ringan, Rusak Berat).
     6. *Status Verifikasi* (Draft, Menunggu Verifikasi Kecamatan, Terverifikasi Bappeda).
     7. *Aksi Terpadu* (Edit Atribut, Digitasi Geometri di Peta, Riwayat Perubahan).
2. **Penyaringan & Pencarian Komprehensif (*Multi-Level Spatial Filtering*)**:
   - Kotak pencarian teks instan dengan fitur debouncing 300ms.
   - Dropdown filter hierarkis: Filter Kecamatan -> Filter Desa -> Filter Kondisi -> Filter Status Verifikasi.
   - Tombol *Reset Filter* yang mengembalikan tampilan data secara instan.
3. **Aksi Ekspor Resmi Data Spasial**:
   - Mendukung pengunduhan data tabel dalam format GeoJSON standar (EPSG:4326), Shapefile (format zip kartografis), dan lembar kerja Excel untuk pelaporan dinas.

---

## 4. Log Audit Aktivitas & Keamanan Data Spasial (`/admin/audit-logs`)

Menolak pola slop "aktivitas rekayasa" dengan nama dan avatar fiktif (*Sarah Chen, John Doe*). Sistem Melarosa mencatat jejak audit nyata dari basis data:

1. **Atribut Rekam Jejak Nyata**:
   - *Waktu Kejadian*: Format timestamp presisi dengan penanda waktu relatif (`timeAgo`).
   - *Nama Aktor Pengguna*: Nama asli staf, NIP (jika ada), dan peran resmi.
   - *Alamat IP & User Agent*: Alamat IP jaringan lokal pemerintahan atau publik.
   - *Tipe Tindakan*: `CREATE`, `UPDATE`, `DELETE`, `VERIFY`, `PUBLISH_LAYER`.
   - *Objek Spasial*: Model dan ID target (contoh: `JalanPorosDesa #1187`).
   - *Diff Perubahan*: Perbandingan nilai lama (*old values*) dan nilai baru (*new values*) pada atribut data geospasial.
2. **Keamanan Penghapusan Data Spasial**:
   - Seluruh aksi penghapusan fitur spasial atau ruas jalan poros mewajibkan konfirmasi modal ganda dengan mengetikkan nama objek secara sadar (*two-step confirmation*) untuk mencegah kehilangan data fatal.

---

## 5. Token Desain: Warna, Tipografi & Status

### A. Palet Warna Khusus Admin Dashboard

| Token Warna | Nilai Mode Terang (*Light*) | Nilai Mode Gelap (*Dark*) | Penggunaan |
|---|---|---|---|
| `admin-bg-shell` | `#f3f4f6` (Gray 100) | `#070b14` (Obsidian Admin) | Latar pembungkus shell luar |
| `admin-bg-workspace`| `#f8fafc` (Slate 50 / Gray 50) | `#070b14` (Obsidian Admin) | Kanvas area kerja utama |
| `admin-surface-card`| `#ffffff` (Putih) | `#0c1424` / `#0f172a` | Kartu metrik, panel tabel, modal |
| `admin-border-subtle`| `rgba(229, 231, 235, 0.8)` | `rgba(255, 255, 255, 0.08)` | Garis pemisah tabel dan kartu |
| `admin-text-main` | `#111827` (Gray 900) | `#f9fafb` (Gray 100) | Teks nama ruas, angka metrik |
| `admin-text-muted` | `#6b7280` (Gray 500) | `#9ca3af` (Gray 400) | Label bantuan, timestamp audit |
| `admin-primary` | `#2563eb` (Blue 600) | `#3b82f6` (Blue 500) | Tombol aksi utama, tab aktif |
| `admin-success` | `#10b981` (Emerald 500) | `#10b981` (Emerald 500) | Status terverifikasi, kondisi baik |
| `admin-warning` | `#f59e0b` (Amber 500) | `#fbbf24` (Amber 400) | Status pending, kondisi sedang/ringan |
| `admin-danger` | `#ef4444` (Red 500) | `#f87171` (Red 400) | Kondisi rusak berat, tombol hapus |

### B. Standar Tipografi Admin
- **Keluarga Font**: Inter, Outfit, sans-serif proporsional modern.
- **Standar Angka Kartografis & Statistik**: Seluruh angka numerik (panjang ruas km/meter, jumlah kecamatan, koordinat WGS 84, nominal BKKD) **wajib menggunakan kelas `tabular-nums`** agar perataan angka di kolom tabel tegak lurus sempurna saat dibandingkan baris demi baris.
- **Larangan Font Monospace**: Dilarang keras menggunakan font monospace (`font-mono`) untuk teks keterangan biasa atau nama ruas jalan; gunakan sans-serif proporsional yang elegan dan ergonomis.

---

## 6. Matriks Pengujian & Verifikasi Kepatuhan Anti-Slop

| Kriteria Pengujian | Standar Wajib | Hasil Verifikasi |
|---|---|---|
| **R-01: Palet Warna Terkendali** | Latar netral bersih `#f8fafc` / `#070b14` dengan aksen fungsional terarah. Bebas gradien ungu-biru marketing. | Lolos |
| **R-02: Bebas Em Dash** | Dilarang menggunakan `-`. Gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau kurung `()`. | Lolos |
| **R-03: Aksesibilitas Keyboard & Sentuh** | Target sentuh tombol >= 44x44px. Navigasi penuh melalui tombol keyboard `Tab`, `Enter`, `Escape`. | Lolos |
| **R-17 & R-36: Metrik Berbasis Data Nyata** | Terhubung langsung ke database backend: 1.187 ruas, 1.424,8 km, 28 kecamatan, 430 desa. Zero angka palsu. | Lolos |
| **R-18: Jejak Audit Otentik** | Tabel riwayat aktivitas mencatat data aktual dari basis data audit log, bebas nama dan foto buatan. | Lolos |
| **R-20: Desain Sesuai Fungsi Kerja** | Tata letak dirancang untuk verifikasi spasial, digitasi jalan poros, dan transparansi anggaran daerah. | Lolos |
| **R-21 & R-34: Integritas Kedua Mode Tema** | Mode Terang dan Mode Gelap keduanya teruji memiliki kontras legal WCAG AA (minimal 4.5:1) dan tidak ada komponen yang rusak. | Lolos |
| **R-26: Zero Dead Buttons** | Seluruh tombol aksi tabel, tombol segarkan, dan tombol navigasi memiliki event handler aktif yang berfungsi. | Lolos |
| **R-27: Kelengkapan Tiga Status UI** | Seluruh pemanggilan data memiliki penanganan komprehensif untuk status *Loading*, *Empty*, dan *Error*. | Lolos |

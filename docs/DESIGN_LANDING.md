# Paten Desain & Spesifikasi Sistem UI/UX: Halaman Beranda Publik (/ - Landing Page)
**Aplikasi**: Melarosa - Sistem Informasi Spasial Kabupaten Bojonegoro  
**Versi Spesifikasi**: 2.0 (Paten Resmi Desain Landing Page)  
**Tanggal Pengesahan**: 27 Oktober 2026  
**Status**: Standar Resmi & Mengikat (*Binding Architectural Standard*)  
**Implementasi Berkas Sumber**: [apps/web/app/pages/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/index.vue)

---

## 1. Filosofi & Prinsip Inti Desain (Core Tenets)

Halaman beranda publik (`/`) Melarosa bukan sekadar halaman promosi atau landing page SaaS komersial pada umumnya. Beranda ini adalah **pintu gerbang geospasial resmi pemerintah daerah** yang menyajikan kejelasan transparansi data wilayah Kabupaten Bojonegoro secara berwibawa, modern, dan berorientasi data riil.

Prinsip dasar yang wajib dipatuhi:

1. **Narasi Spasial Mengutamakan Peta (*Geospatial-First Visual Narrative*)**:
   - Area hero memanfaatkan kanvas peta visual resolusi tinggi dengan efek parallax halus (`scrollY * 0.35`) dan vignette obsidian gelap bertingkat, memberikan kesan mendalam (*immersive depth*) bahwa pengguna sedang memasuki ekosistem geospasial terpadu.
2. **Pencarian Spasial Universal Langsung di Hero (*In-Hero Spatial Querying*)**:
   - Pengguna dapat langsung mengetikkan nama dari 28 kecamatan, nama desa, maupun koordinat geografis presisi WGS 84 (format Derajat Desimal / DD atau Derajat Menit Detik / DMS) langsung dari bilah pencarian hero, yang secara otomatis memicu navigasi langsung ke peta interaktif (`/maps`).
3. **Bukti Nyata Berbasis Data Terverifikasi (*Evidence Over Claims - Anti-Slop C-5 & R-38*)**:
   - Seluruh statistik dan angka yang disajikan adalah fakta nyata yang bersumber langsung dari basis data spasial Kabupaten Bojonegoro:
     - **28** Kecamatan resmi.
     - **430** Desa dan Kelurahan.
     - **2.307** km² Luas Wilayah Kabupaten.
     - Proyeksi standar geodesi nasional **WGS 84** (EPSG:4326 / EPSG:3857).
     - **1.187** Ruas Jalan Poros Desa terdata sepanjang **1.424,8** km.
     - **31** Geosite cagar geologi, hayati, dan budaya Geopark Nasional Bojonegoro.
   - **Larangan Keras**: Dilarang memasang testimonial fiktif, avatar rekayasa AI, ulasan buatan, atau metrik pemasaran generik ("10.000+ Pengguna Aktif", "99,9% Uptime", "AI-Powered").
4. **Komposisi Asimetris Berorientasi Konten (*Content-Driven Asymmetric Rhythm*)**:
   - Menolak susunan template klise AI yang seragam (3 kartu identik berturut-turut).
   - Menghadirkan ritme komposisi bervariasi (*RHYTHM 3*):
     - Kartu Unggulan Jaringan Jalan Poros Desa membentang 7 kolom dengan tata letak visual lebar.
     - Kartu Cagar Geologi & Geopark Nasional berdampingan 5 kolom dengan aksen nuansa zamrud (*emerald*).
     - Kartu Pembangunan Daerah BKKD dan Geoportal Wilayah berpasangan seimbang 6 kolom.
5. **Kepatuhan Mutlak Anti-Slop (*Strict Anti-Slop Discipline*)**:
   - **Bebas Em Dash (R-02)**: Dilarang keras menggunakan karakter em dash (`-`). Gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau tanda kurung `()`.
   - **Zero Dead Buttons (R-26)**: Setiap tombol, chip pencarian cepat, kartu navigasi, dan tautan wajib memiliki aksi nyata ke rute sistem. Dilarang menaruh tombol hiasan mati atau tautan hampa (`href="#"`).
   - **Ukuran Sentuh Ergonomis (R-03)**: Seluruh tombol interaktif dan form input memiliki tinggi minimal 44x44 piksel pada perangkat layar sentuh.
   - **Kontras Aksesibilitas Tinggi (R-25)**: Rasio kontras teks terhadap latar belakang memenuhi standar WCAG AA (minimal 4.5:1 untuk teks isi, minimal 3:1 untuk teks judul tebal).
   - **Dials Antislop**: ENERGY: 2 (Elegan, berwibawa, kontras tegas), RHYTHM: 3 (Asimetris kaya variasi), MOTION: 1 (Fungsional, tenang, tanpa animasi loop tak berkesudahan).

---

## 2. Arsitektur Struktur Halaman & Tata Letak

### A. Diagram Komposisi Vertikal Halaman Beranda

```
+-----------------------------------------------------------------------------------+
| [ PUBLIC NAVBAR: Logo Bojonegoro | Peta | Data Spasial | Admin | Tema ] (z-[70])  |
+-----------------------------------------------------------------------------------+
| ═══ SECTION 1: HERO VIEWPORT MAP CANVAS (min-h-[100dvh]) ════════════════════════ |
| [Background Image Peta Konektivitas + Parallax scrollY*0.35 + Obsidian Vignette]  |
|                                                                                   |
|   [Eyebrow Badge: Melarosa GIS: Kabupaten Bojonegoro]                             |
|   H1: Satu Peta Geospasial untuk Konektivitas dan Pembangunan Bojonegoro          |
|   Subheadline: Portal pemetaan 28 kecamatan, jalan poros, geopark & WGS 84        |
|                                                                                   |
|   +---------------------------------------------------------------------------+   |
|   | [FORM PENCARIAN HERO: Input Kecamatan / Desa / Koordinat WGS 84]          |   |
|   | [Tombol: Cari di Peta (Blue 600)]  |  [Tombol: Buka Penuh (Outline)]      |   |
|   +---------------------------------------------------------------------------+   |
|                                                                                   |
|   [Chip Pintasan Jelajahi Cepat: Bojonegoro Kota | Dander | Temayang | ...]       |
|   [Petunjuk Gulir Tenang: "Gulir untuk melihat pilar wilayah" + Chevron Down]     |
+-----------------------------------------------------------------------------------+
| ═══ SECTION 2: ROW METRIK UTAMA REAL DATA (-mt-8 sm:-mt-10) ═════════════════════ |
| [ 28 Kecamatan ]  | [ 430+ Desa/Kelurahan ] | [ 2.307 km² Luas ] | [ WGS 84 Geodesi ]
+-----------------------------------------------------------------------------------+
| ═══ SECTION 3: 4 PILAR SPASIAL & PEMBANGUNAN (Grid Asimetris) ═══════════════════ |
| Header Pilar: "Konektivitas Wilayah, Cagar Geologi, dan Pemerataan Pembangunan"   |
|                                                                                   |
| +-----------------------------------------------+ +-------------------------------+ |
| | [KARTU 1: 7 KOLOM - JALAN POROS DESA]         | | [KARTU 2: 5 KOLOM - GEOPARK]  | |
| | Konektivitas Wilayah · Rigid Beton · Aspal    | | Warisan Geologi · Cagar Alam  | |
| | Pemotongan Segmen Ruas · Akses 430+ Desa      | | Kayangan Api · Teksas Wonocolo| |
| | [CTA: Lihat Peta Jalan Poros ->]              | | [CTA: Jelajahi Situs Geopark] | |
| +-----------------------------------------------+ +-------------------------------+ |
|                                                                                   |
| +-----------------------------------------------+ +-------------------------------+ |
| | [KARTU 3: 6 KOLOM - BKKD & PEMBANGUNAN FISIK] | | [KARTU 4: 6 KOLOM - GEOPORTAL]| |
| | Plotting Anggaran Spasial · Realisasi Desa    | | Simpul Jaringan JIGN · 50+ Lay| |
| | Transparansi Dana Bantuan Khusus              | | Batas Administrasi Wilayah    | |
| | [CTA: Pantau Data BKKD ->]                    | | [CTA: Buka Katalog Layer ->]  | |
| +-----------------------------------------------+ +-------------------------------+ |
+-----------------------------------------------------------------------------------+
| ═══ SECTION 4: SHOWCASE PRATINJAU INTERAKTIF WEBGIS ══════════════════════════════ |
| Ulasan Fitur Kartografi Modern: OpenLayers, MVT Vector Tiles, Split View, GPS     |
+-----------------------------------------------------------------------------------+
| ═══ SECTION 5: FOOTER PORTAL PEMKAB BOJONEGORO ═══════════════════════════════════ |
| Atribusi Resmi BAPPEDA, Tautan Cepat Geoportal, Standar Satu Data Indonesia       |
+-----------------------------------------------------------------------------------+
```

---

## 3. Spesifikasi Detail Tiap Bagian Antarmuka

### 3.1. Hero Section (Kanvas Spasial Penuh & Universal Query)

1. **Kanvas Latar Belakang Citra Parallax**:
   - Berkas gambar: `/images/hero-bojonegoro-connectivity.jpg`.
   - Transformasi visual: `translateY(scrollY * 0.35px) scale(1.1)` dengan akselerasi perangkat keras GPU (`will-change-transform`).
   - Tingkat kecerahan dan kontras: `brightness-[0.72] contrast-[1.2]`.
   - Lapisan pewarnaan obsidian: `#03080f` dengan percampuran `mix-blend-multiply` 40%.
2. **Gradien Pelindung Keterbacaan Teks (*Readability Vignette Shield*)**:
   - Gradien vertikal linier 180°: dari obsidian pekat di atas (`rgba(3,8,15,0.75)`), transparan di tengah layar (50%), hingga transisi pekat di bagian dasar pertemuan konten (`rgba(7,11,20,1)` 100%).
   - Gradien radial elips: memastikan area pusat teks tetap memiliki kontras tinggi tanpa menyilaukan mata pengguna.
3. **Hierarki Tipografi Hero**:
   - **Eyebrow Badge**: Berbentuk kapsul lembut dengan tepi halus `border-blue-400/30 bg-blue-950/40 text-blue-300`, memuat indikator titik statis biru (`size-1.5 rounded-full bg-blue-400`) tanpa animasi denyut (*zero pulsing animation*), teks huruf besar berjarak proporsional: `"Melarosa GIS: Kabupaten Bojonegoro"`.
   - **Headline (H1)**: Teks berukuran responsif `text-[2.2rem] sm:text-[3.2rem] lg:text-[4rem] font-bold leading-[1.04] tracking-[-0.03em] text-white [text-wrap:balance]`:
     > *"Satu Peta Geospasial untuk Konektivitas dan Pembangunan Bojonegoro"*
   - **Subheadline**: Teks berukuran `text-[0.98rem] sm:text-[1.12rem] text-slate-200 leading-relaxed max-w-[42rem]`:
     > *"Portal resmi pemetaan batas 28 kecamatan, kondisi fisik jaringan jalan poros desa, cagar geologi geopark, dan sebaran pembangunan daerah berbasis proyeksi standar WGS 84."*
4. **Formulir Pencarian Hero Multi-Format (`onHeroSearchSubmit`)**:
   - Pembungkus input: Sudut membulat `rounded-2xl border border-white/20 bg-slate-950/80 backdrop-blur-xl p-2 shadow-2xl`.
   - Logika pemrosesan cerdas:
     1. **Format Koordinat**: Menjalankan pembaca regex `parseCoordinateString(q)`. Jika terdeteksi format numerik Lintang dan Bujur (seperti `-7.15, 111.89` atau format DMS), pengguna diarahkan langsung ke rute `/maps/@lat,lng,16z?search=...&pin=1`.
     2. **Format Kecamatan**: Jika kueri cocok dengan salah satu dari 28 kecamatan resmi pada konstanta `KECAMATAN_ENTRIES`, sistem langsung menavigasi ke titik tengah dan tingkat zoom ideal kecamatan tersebut.
     3. **Format Kata Kunci Bebas**: Mengarahkan ke rute `/maps?search=...` untuk pencarian layer dan objek spasial.
5. **Pill Pintasan Wilayah Cepat (*Quick Jump Chips*)**:
   - Tombol pintas ke wilayah utama: Bojonegoro Kota, Dander, Temayang, Wonocolo (Kedewan), dan Padangan.
   - Styling: Tepi transparan `border border-white/15 bg-white/8 hover:bg-white/15 text-slate-200 hover:text-white rounded-lg px-2.5 py-1 text-xs`, target sentuh ergonomis.

---

### 3.2. Barisan Metrik Nyata (Stat Row Bounded Elevation)

1. **Penempatan & Posisi Geometri**:
   - Menggunakan margin negatif atas (`-mt-8 sm:-mt-10`) di atas kontainer utama dengan lapisan bertingkat (`z-20 relative`).
   - Menyajikan 4 kartu metrik presisi dalam tata letak responsif 2 kolom di ponsel dan 4 kolom di layar komputer.
2. **Spesifikasi 4 Kartu Metrik Data Riil**:
   - **Kartu 1**: Nilai `28`, Label `"Kecamatan"`.
   - **Kartu 2**: Nilai `430+`, Label `"Desa & Kelurahan"`.
   - **Kartu 3**: Nilai `2.307`, Suffix `km²`, Label `"Luas Wilayah"`.
   - **Kartu 4**: Nilai `WGS 84`, Label `"Standar Geodesi Nasional"`.
3. **Styling & Efek Ketinggian Visual**:
   - Sudut membulat elegan `rounded-2xl`, latar belakang ganda `bg-white/95 dark:bg-[#0c121e]/90`, garis tepi halus `border border-slate-200/90 dark:border-slate-800/80`, bayangan lembut `shadow-sm backdrop-blur-md`.
   - Mikro-interaksi hover lembut: `hover:-translate-y-0.5 transition-transform duration-200`.

---

### 3.3. Grid Asimetris 4 Pilar Spasial & Pembangunan

Menghindari tata letak kartu yang seragam dan menjemukan, 4 pilar diorganisasikan dalam susunan bervariasi dengan pembagian peran yang tegas:

1. **Pilar 1: Jaringan Jalan Poros Desa (Ukuran 7 Kolom / Flagship)**:
   - **Tema Warna**: Biru Primari (*Blue 600 / Slate*).
   - **Badge Kategori**: `i-lucide-git-branch` dengan label `"Konektivitas Wilayah"`.
   - **Judul**: `"Jaringan Jalan Poros Desa"`.
   - **Deskripsi**: Penjelasan pemetaan perkerasan fisik (Rigid Beton, Aspal, Makadam), segmentasi ruas, dan jalur penghubung antar-desa di seluruh Kabupaten Bojonegoro.
   - **Tags Fitur**: `"Rigid Beton & Aspal"`, `"Pemotongan Segmen Ruas"`, `"Akses 430+ Desa"`.
   - **Tombol Aksi**: Tombol solid warna primer biru `bg-blue-600 hover:bg-blue-500 text-white min-h-[44px]` dengan label kontekstual `"Lihat Peta Jalan Poros"`.
2. **Pilar 2: Cagar Geologi & Geopark Nasional (Ukuran 5 Kolom)**:
   - **Tema Warna**: Zamrud Alami (*Emerald 600 / Dark Forest*).
   - **Badge Kategori**: `i-lucide-mountain` dengan label `"Warisan Geologi"`.
   - **Judul**: `"Cagar Geologi & Geopark Nasional"`.
   - **Deskripsi**: Pemetaan 31 situs keragaman geologi (Kayangan Api, Negeri Minyak Teksas Wonocolo, Waduk Pacal, Antiklin Kawengan) yang terhubung langsung dengan data resmi Geopark Bojonegoro.
   - **Tags Fitur**: `"Kayangan Api"`, `"Teksas Wonocolo"`, `"Waduk Pacal"`.
   - **Tombol Aksi**: Tombol elegan netral dengan aksen hijau zamrud pada saat disentuh kursor (`hover:border-emerald-500 hover:text-emerald-600 min-h-[44px]`), berlabel `"Jelajahi Situs Geopark"`.
3. **Pilar 3: Pembangunan Daerah & BKKD (Ukuran 6 Kolom)**:
   - **Tema Warna**: Amber Keemasan (*Amber 600*).
   - **Badge Kategori**: `i-lucide-landmark` dengan label `"Pemerataan Pembangunan"`.
   - **Judul**: `"Plotting Anggaran & BKKD Desa"`.
   - **Deskripsi**: Transparansi alokasi Bantuan Keuangan Khusus Desa (BKKD) dan infrastruktur fisik berbasis sebaran titik dan poligon lokasi riil.
   - **Tombol Aksi**: Rute menuju modul peta tematik penganggaran.
4. **Pilar 4: Simpul Jaringan Geoportal JIGN & Wilayah (Ukuran 6 Kolom)**:
   - **Tema Warna**: Sian Spasial (*Sky 600*).
   - **Badge Kategori**: `i-lucide-layers` dengan label `"Simpul Jaringan JIGN"`.
   - **Judul**: `"Katalog Layer Geoportal & Batas Wilayah"`.
   - **Deskripsi**: Integrasi standar OGC Web Map Service (WMS) dan Vector Tiles dari simpul jaringan Pemkab Bojonegoro untuk batas administrasi 28 kecamatan dan 430 desa.
   - **Tombol Aksi**: Menuju katalog layer geoportal.

---

## 4. Token Desain: Warna, Tipografi, dan Elevasi

### A. Palet Warna Khusus Landing Page

| Token Warna | Nilai Mode Terang (*Light*) | Nilai Mode Gelap (*Dark*) | Penggunaan |
|---|---|---|---|
| `hero-bg` | `#03080f` (Obsidian Gelap) | `#03080f` (Obsidian Gelap) | Latar belakang hero kanvas |
| `surface-ground` | `#f8fafc` (Slate 50) | `#070b14` (Obsidian Night) | Latar belakang halaman utama |
| `surface-card` | `#ffffff` (Putih Bersih) | `#0c121e` (Dark Navy) | Kartu pilar, kartu metrik |
| `border-card` | `rgba(226, 232, 240, 0.9)` | `rgba(30, 41, 59, 0.8)` | Garis tepi komponen kartu |
| `text-headline` | `#ffffff` (pada Hero) | `#ffffff` (pada Hero) | Judul utama hero section |
| `text-body-hero` | `#e2e8f0` (Slate 200) | `#e2e8f0` (Slate 200) | Sub-judul dan deskripsi hero |
| `text-title-section`| `#0f172a` (Slate 900) | `#f8fafc` (Slate 100) | Judul pilar pada konten utama |
| `text-muted` | `#475569` (Slate 600) | `#94a3b8` (Slate 400) | Deskripsi pilar, label sekunder |
| `accent-primary` | `#2563eb` (Blue 600) | `#3b82f6` (Blue 500) | Tombol cari hero, kartu jalan poros |
| `accent-emerald` | `#16a34a` (Emerald 600) | `#10b981` (Emerald 500) | Aksen kartu Geopark Bojonegoro |
| `accent-amber` | `#d97706` (Amber 600) | `#f59e0b` (Amber 500) | Aksen kartu BKKD & pembangunan |

### B. Standar Tipografi
- **Keluarga Font**: Inter, Outfit, serta sistem sans-serif proporsional.
- **Larangan Font Monospace**: Angka pada metrik statistik wajib menggunakan sans-serif proporsional dengan `tabular-nums` yang bersih dan rapi. Dilarang menggunakan font mono (`font-mono`) pada teks heading atau deskripsi hero.
- **Tingkat Keterbacaan**: Memenuhi standar WCAG AA dengan rasio kontras minimal 4.5:1 terhadap seluruh latar belakang gradien.

---

## 5. Matriks Pengujian & Verifikasi Kepatuhan Anti-Slop

| Kriteria Pengujian | Standar Wajib | Hasil Verifikasi |
|---|---|---|
| **R-01: Palet Warna Berkarakter** | Obsidian gelap `#03080f` dengan aksen fungsional tematik. Dilarang menggunakan gradien ungu-biru generik tanpa identitas. | Lolos |
| **R-02: Bebas Em Dash** | Dilarang menggunakan tanda baca `-`. Gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau kurung `()`. | Lolos |
| **R-03: Tata Letak Responsif Mobile** | Target sentuh tombol >= 44x44px. Zero horizontal overflow di perangkat 320px hingga desktop 4K. | Lolos |
| **R-05: Struktur Konten Asimetris** | Grid bervariasi (7:5 dan 6:6 kolom). Menolak template 3 kartu seragam klise. | Lolos |
| **R-15: CTA Kontekstual Spesifik** | Dilarang menggunakan teks CTA generik ("Get Started", "Learn More"). Gunakan "Cari di Peta", "Lihat Peta Jalan Poros", "Jelajahi Situs Geopark". | Lolos |
| **R-17 & R-36: Metrik Berbasis Data Riil** | 28 Kecamatan, 430 Desa, 2.307 km², 1.187 Ruas Jalan, 31 Geosite. Zero angka rekayasa fiktif. | Lolos |
| **R-18: Bebas Testimonial Palsu** | Tidak ada bagian ulasan/testimonial fiktif yang direkayasa AI. | Lolos |
| **R-19: Animasi Bertujuan Jelas** | Scroll reveal satu kali (IntersectionObserver). Menghilangkan animasi loop tanpa henti atau indikator berkedip. | Lolos |
| **R-26: Zero Dead Buttons** | Seluruh tombol formulir pencarian, tombol pintasan, dan tombol kartu memiliki aksi navigasi valid. | Lolos |

---
version: "2.0"
name: "Melarosa GIS"
description: "Design system untuk landing page dan portal informasi geospasial Kabupaten Bojonegoro. Berbasis Nuxt UI v3, Tailwind CSS v4, dan komponen WebGIS."
colors:
  primary: "blue"
  neutral: "slate"
  background-light: "#FFFFFF"
  background-dark: "#070b14"
  surface-dark: "#0b0f19"
  hero-dark: "#03080f"
typography:
  base:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: 1rem
    fontWeight: 400
  mono:
    fontFamily: "ui-monospace, JetBrains Mono, monospace"
components:
  radius: 0.75rem
dial:
  energy: 1
  rhythm: 2
  motion: 1
---

## Overview

Melarosa GIS adalah portal informasi geospasial resmi Kabupaten Bojonegoro untuk pemetaan batas wilayah administratif (desa dan kecamatan) serta jaringan jalan poros kabupaten. Landing page berfungsi sebagai titik masuk publik yang informatif, modern, dan langsung terhubung ke peta interaktif.

Desain menggabungkan prinsip **modern geospatial portal** (terinspirasi visual lanskap terestrial mandumrimba.org) dengan pendekatan **clean government utility**:
- Serius, terpercaya, dan berbasis data resmi (BPS, BIG, Pemkab).
- Aksesibel dan responsif dari smartphone hingga monitor layar lebar.
- Mendukung tema Light Mode (solid clean white) dan Dark Mode (frosted glass deep dark) secara konsisten.

- **Dial Antislop:** ENERGY 1 (Calm, Utilitarian) / RHYTHM 2 (Varied sections) / MOTION 1 (Functional transitions)
- **Style:** Modern Geospatial, Clean Government, Atmospheric Hero
- **Keywords:** peta, bojonegoro, webgis, jalan poros, batas wilayah, data spasial, wgs84
- **Era:** 2026
- **Theme Support:** Full Light & Dark Mode


## Colors

Sistem warna dikonfigurasi melalui **Nuxt UI v3** di `app.config.ts`:

```ts
ui: {
  colors: {
    primary: 'blue',
    neutral: 'slate',
  }
}
```

### Palet Aktif:

- **Blue 600 / 500 / 400** — Aksi utama (CTA buttons), teks link aktif, dot status, indikator fokus.
- **Blue 50 / Blue 950/50** — Background item navigasi aktif di light / dark mode.
- **Slate 900 / Slate 100** — Teks utama judul di light / dark mode.
- **Slate 600 / Slate 400** — Teks body, subjudul, dan keterangan sekunder.
- **Slate 200/80 / Slate 800/70** — Border kartu, divider, dan garis pemisah.
- **#FFFFFF** — Background dasar light mode & navbar solid pill di light mode.
- **#070b14** — Background dasar dark mode (deep canvas).
- **#0b0f19** — Surface card & drawer dark mode.
- **#03080f** — Background dasar hero canvas (deep night satellite tone).

### Semantic Colors:
- **Primary / Action** → `blue-600` / `blue-500`
- **Muted / Neutral** → `slate-500` / `slate-400`
- **Success** → `emerald-500`
- **Error / Danger** → `red-500` (`border-red-200 dark:border-red-900/60`)


## Floating Navbar System

Navbar menggunakan konsep **Floating Centered Pill Navbar** yang melayang di atas konten (`fixed inset-x-0 top-0 z-50 pt-5`):

### 1. Light Mode
- **Background:** Solid putih murni (`bg-white`).
- **Border & Shadow:** `border border-slate-200/80 shadow-md shadow-slate-900/5`.
- **Saat di-scroll:** Menambah ketegasan bayangan (`shadow-lg shadow-slate-900/8 border-slate-300/80`).
- **Menu Navigasi:** `text-slate-600 hover:text-slate-900 hover:bg-slate-100`, aktif: `text-blue-600 bg-blue-50 font-semibold`.
- **Tombol Masuk:** Solid CTA `bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-4 py-2 font-semibold`.

### 2. Dark Mode (Frosted Glass)
- **Background:** Dark tinted frosted glass (`dark:bg-[#09111e]/75 dark:backdrop-blur-xl`).
- **Border & Shadow:** `dark:border-white/10 dark:shadow-lg dark:shadow-black/30`.
- **Saat di-scroll:** Intensitas meningkat menjadi `dark:bg-[#09111e]/90 dark:border-white/15 dark:shadow-xl`.
- **Menu Navigasi:** `dark:text-slate-300 dark:hover:text-white dark:hover:bg-white/10`, aktif: `dark:text-blue-400 dark:bg-white/10 font-semibold`.
- **Tombol Masuk:** Soft glass button `dark:bg-blue-500/20 dark:border dark:border-blue-400/30 dark:text-blue-300 dark:hover:bg-blue-500/30 dark:hover:text-white`.
- **Pengalih Tema:** Pill halus `dark:border-white/10 dark:bg-white/5 dark:text-slate-300`.

### 3. Fitur Pencarian Dinamis (Expanding Search Bar)
- **Animasi Melebar Navbar (Menu Tetap Tampil):**
  - Status default: Navbar berukuran kompak dengan seluruh menu utama (`Beranda`, `Peta Interaktif`, `GeoStory`, `Katalog Data`) dan tombol pemicu pencarian ("Cari..." + shortcut `Ctrl K`).
  - Saat diklik / aktif: Kotak pencarian memanjang secara mulus (`transition-[width] duration-500 ease-out`), mendorong elemen di sekitarnya sehingga total lebar navbar melebar tanpa menutup menu navigasi sama sekali. Seluruh menu tetap tampil dan dapat diakses.
  - Shortcut keyboard global: `Ctrl+K` / `Cmd+K` untuk toggle, `Escape` atau klik di luar untuk menutup dan mengembalikan navbar ke lebar kompak semula.
- **Dataset & Fitur Pencarian:**
  - 28 Kecamatan resmi Kabupaten Bojonegoro beserta koordinat centroid WGS 84 (langsung mengarahkan peta ke koordinat target).
  - Navigasi halaman cepat (Beranda, Peta Interaktif, GeoStory, Katalog Data).
  - Dukungan input koordinat WGS 84 langsung (`lat, lng` atau `lng, lat`).
  - **Sinkronisasi Parameter URL:** Menggunakan query parameter `?search=` (contoh: `/maps/@-7.2333,111.8500,13z?search=Kecamatan+Dander`) yang tersinkronisasi dua arah dengan pulse marker di peta dan kolom pencarian.
- **Dropdown Rekomendasi:**
  - Floating panel presisi tepat di bawah kotak pencarian sebelah kanan dengan backdrop blur, navigasi panah keyboard `↑↓` dan `Enter`.
  - Empty state yang bersih dan informatif saat tidak ada hasil.

### 4. Mobile Floating Navigation
- Terbagi menjadi:
  - **Pill Kiri:** Logo Bojonegoro GIS (`px-4 py-2.5 rounded-2xl`).
  - **Pill Kanan:** Tombol pencarian cepat (`size-11`) dan hamburger menu (`size-11`).
- Saat pencarian aktif di mobile: Navbar atas berubah menjadi expanded search input penuh (`w-full`) dengan touch target $\ge 44\text{px}$ dan panel dropdown hasil di bawahnya.
- Membuka drawer navigasi samping (`USlideover` side=right) dengan opsi pencarian cepat di bagian atas drawer.

### 5. Indikator Pulse Marker Spasial (Dual-Mode UI/UX)
- **Komponen Penanda Lokasi Pencarian & Titik Klik:**
  - **Light Mode:**
    - Badge Card: Solid clean frosted white (`bg-white/95 text-slate-800 border-slate-200/90 shadow-md shadow-slate-900/10`) dengan caret pointer putih.
    - Dot Indikator: Deep royal blue (`bg-blue-600 animate-pulse`).
    - Radar Ping Wave: Kontras biru lembut (`bg-blue-500/25 border border-blue-500/30 animate-ping`).
    - Core Pin: Titik biru tebal (`bg-blue-600 ring-2 ring-white shadow-md`).
  - **Dark Mode:**
    - Badge Card: Deep night frosted glass (`#09111e/95 dark:backdrop-blur-xl dark:border-white/20 dark:text-white dark:shadow-2xl dark:shadow-black/70`) dengan caret pointer dark glass.
    - Dot Indikator: Electric cyan-blue (`dark:bg-blue-400 animate-pulse`).
    - Radar Ping Wave: Neon radar wave (`dark:bg-blue-400/30 dark:border-blue-400/50 animate-ping`).
    - Core Pin: Glowing electric beacon (`dark:bg-blue-500 dark:ring-2 dark:ring-[#070b14] dark:shadow-[0_0_12px_rgba(59,130,246,0.9)]`).
  - Menampilkan nama wilayah pencarian dan koordinat WGS 84 berformat monospace yang akurat dan terbaca jelas.

### 6. Toggle Panel Layer & Dialog Katalog Data Spasial
- **Floating Panel Layer Toggle (Halaman Peta `/maps`):**
  - Menggantikan panel pencarian mengambang lama; pencarian kini terintegrasi secara eksklusif pada Navbar.
  - Berada di posisi `top-[72px] lg:top-[86px] left-3 sm:left-4 lg:left-5` dengan ukuran ergonomis `min-h-[44px] h-11 px-3.5 sm:px-4 rounded-2xl`.
  - Dilengkapi ikon `i-lucide-layers`, label ("Panel Layer"), sub-label ("Katalog & Wilayah"), dan indikator chevron yang berputar saat panel terbuka.
  - Active state yang kontras: Solid blue `bg-blue-600 text-white` saat panel terbuka, dan frosted glass `bg-white/95 dark:bg-[#09111e]/90` saat tertutup. Dialog Katalog Data diakses terpadu melalui tombol aksi di dalam Panel Layer.
- **Konten Panel Layer (`LeftPanel.vue`):**
  - **Daftar Layer Aktif Dinamis:** Menampilkan kartu item layer yang ditambahkan dari Dialog Katalog Data. Jika daftar kosong, ditampilkan *empty state* dengan tombol pemicu katalog.
  - **Fitur Lengkap Tiap Kartu Layer Item (Sesuai Referensi Visual GIS):**
    1. **Sembunyikan / Tampilkan (Visibilitas):** Tombol toggle `i-lucide-eye` (aktif) dan `i-lucide-eye-off` (nonaktif) pada header dan toolbar kartu.
    2. **Transparansi:** Tombol `i-lucide-sun-medium` membuka slider opasitas inline (`10% - 100%`) disertai tombol preset cepat (`100%`, `75%`, `50%`, `25%`).
    3. **Zoom to:** Tombol `i-lucide-maximize-2` untuk langsung memusatkan peta ke cakupan geografis layer tersebut.
    4. **Filter Data:** Tombol `i-lucide-filter` dengan indikator dot biru saat aktif, membuka panel filter wilayah per-kecamatan (28 kecamatan Bojonegoro) dan filter status fitur.
    5. **Informasi Metadata:** Tombol `i-lucide-info` membuka kartu ringkasan terstruktur berisi Tipe Data, Jumlah Fitur, Sistem Koordinat, Instansi Walidata, Tanggal Pembaruan, dan Deskripsi.
    6. **Hapus Layer Item:** Tombol `i-lucide-trash-2` berwarna merah untuk menghapus layer dari daftar aktif peta.
  - **Aksesibilitas & Sentuhan:** Seluruh tombol tindakan memiliki ukuran target sentuh $\ge 44\text{px}$ dan jarak pemisah yang memadai untuk penggunaan mobile maupun desktop tanpa salah sentuh.
- **Dialog Modal Katalog Data (`KatalogDialog.vue`):**
  - Modal komprehensif berukuran `sm:max-w-4xl` dengan pencarian live dataset, filter kategori berformat chip tabs ("Semua", "Batas Wilayah", "Infrastruktur Jalan", "Tata Ruang & SDA", "Kebencanaan", "Layer Tematik API").
  - Menampilkan status "Sudah di Panel" atau tombol aksi "+ Tambahkan ke Peta". Saat dipilih, layer otomatis ditambahkan ke `activeLayers`, peta diarahkan ke lokasi, dan Panel Layer terbuka seketika.

### 7. Integrasi OpenLayers WMS Tile Layer pada Map Canvas (`MapCanvas.vue`)
- **Arsitektur Rendering WMS:**
  - Menggunakan modul OpenLayers resmi `ol/layer/Tile.js` dan `ol/source/TileWMS.js` yang dimuat secara asinkron (*lazy-loaded* pada client).
  - Menyediakan pool layer WMS aktif (`wmsLayersMap: Map<string, TileLayer>`) yang tersinkronisasi dua arah dengan `props.activeLayers`.
  - Konfigurasi parameter WMS GeoServer Bojonegoro:
    - `TILED: true` (memastikan pembagian ubin peta optimal dan performa panning cepat).
    - `FORMAT: 'image/png'` dan `TRANSPARENT: true` (agar layer tematik bertumpuk transparan di atas basemap pilihan).
    - `serverType: 'geoserver'` dan `crossOrigin: 'anonymous'`.
    - `VERSION: '1.1.1'` (kompatibilitas penuh dengan OGC WMS Geoportal Bojonegoro).
- **Sinkronisasi Reaktif WMS:**
  - **Visibilitas:** Menghubungkan toggle mata pada kartu layer langsung ke `olLayer.setVisible(layer.visible)`.
  - **Transparansi:** Menghubungkan slider opasitas inline langsung ke `olLayer.setOpacity(layer.opacity)`.
  - **Penambahan & Penghapusan:** Menambahkan layer baru dari Katalog Data seketika ke peta dengan penomoran `zIndex` dinamis, serta menghapus layer dari kanvas saat kartu dihapus dari Panel Layer.
  - **Zoom to Extent:** Menggerakkan pandangan peta (`flyTo`) ke titik centroid geografis dataset yang dipilih.




## Hero Section (Full Viewport Responsive)

Hero section berorientasi lanskap visual penuh terinspirasi antarmuka geospatial mandumrimba.org:

- **Dimensi:** `min-h-screen min-h-[100dvh] w-full flex flex-col justify-end overflow-hidden bg-[#03080f]`. Tidak menggunakan margin negatif (`-mt-*`) agar 100% mengisi viewport tanpa celah putih di bawah.
- **Layer Latar Belakang Spasial:**
  1. Parallax Map Canvas (`hero-map-canvas`): SVG garis kontur topografi Bojonegoro, alur sungai Bengawan Solo, dan titik simpul spasial.
  2. Aurora Ambient Glow: Gradien halus atmosferik (`aurora-glow`) dengan animasi drift perlahan.
  3. Gradien Peredup (Horizon & Bottom Fade): Transisi mulus menuju warna latar halaman (`#070b14`).
- **Komposisi Konten (Bottom-Anchored):**
  - **Eyebrow:** `text-[0.72rem] uppercase tracking-[0.2em] text-white/80` disertai titik indikator biru (`bg-blue-400`).
  - **Headline:** `text-[2.2rem] sm:text-[3.2rem] lg:text-[4.2rem] font-bold text-white leading-[0.98]`.
  - **Subheadline:** `text-[0.98rem] sm:text-[1.15rem] leading-relaxed text-white/82 max-w-[40rem]`.
  - **CTAs:**
    - Tombol Utama: "Buka Peta Interaktif" (`bg-blue-500 text-white rounded-full px-6 py-3 shadow-[0_10px_30px_-8px_rgba(59,130,246,0.55)]`).
    - Tombol Sekunder: "Jelajahi Kecamatan" (`border border-white/30 bg-white/10 text-white backdrop-blur-md rounded-full px-6 py-3`).
  - **Metadata Tags:** Kapsul informatif semi-transparan ("Data Resmi", "Akses Publik", "WGS 84", "28 Kecamatan").
  - **Scroll Cue:** Indikator animasi halus di dasar layar: "GULIR UNTUK MENJELAJAHI" + ikon panah ke bawah.


## Content Sections Rhythm

Setelah hero, konten halaman berlanjut dengan ritme struktural berikut:

1. **Stats Row:** Baris 4 metrik data terverifikasi (28 Kecamatan, 430+ Desa & Kelurahan, 2.307 km² Luas Wilayah, WGS 84).
2. **Fitur Portal:** Grid 3 kartu fitur asimetris (Peta Batas Wilayah, Jaringan Jalan Poros, Layer Data Tematik) dengan ikon tematik.
3. **Map CTA Banner:** Banner lanskap dengan motif pola grid kartografi dan ajakan eksplorasi langsung.
4. **Tentang Melarosa (Context Block):** Narasi resmi asal data, metodologi koordinat WGS 84, serta tombol navigasi cepat.


## Typography

- **Font Family:** `ui-sans-serif, system-ui, sans-serif`
- **Monospace:** `font-mono, JetBrains Mono` (digunakan pada koordinat dan metadata layer)
- **Skala Ukuran:**
  - Hero H1: `text-[2.2rem]` (mobile) hingga `text-[4.2rem]` (desktop)
  - Section H2: `text-[2rem] sm:text-[2.4rem] font-bold tracking-tight`
  - Card Title: `text-[0.98rem] font-semibold`
  - Body Text: `text-[0.98rem]` / `text-[1.02rem] leading-relaxed`
  - Caption / Tag: `text-[0.72rem]` / `text-[0.74rem]`
- **App Logo:** `text-2xl font-black` — "Dev" (`text-neutral-700 dark:text-neutral-100`) + "GIS" (`text-primary-500`).


## Layout Structure

- **Framework:** Nuxt 4 + Tailwind CSS v4.
- **Content Container:** `max-w-[1080px] mx-auto px-5` (landing page), `max-w-7xl` (halaman katalog/data).
- **Public Layout (`app.vue`):**
  - Homepage (`/`): Bebas top-padding (`pt-0`), hero berada di bawah floating navbar.
  - Non-homepage (`/maps`, `/katalog-data`, dll): Memiliki spacer atas `pt-[88px]` agar konten tidak tertutup floating navbar.
  - Dedicated `/maps` Layout: `fixed inset-0 overflow-hidden flex flex-col` (peta full canvas).
  - Dedicated `/admin` Layout: `h-screen w-screen overflow-hidden` (standalone admin workspace).


## Motion & Animation

Prinsip: Berfungsi sebagai penuntun visual tanpa mengganggu performa dan tanpa endless pulsing loops dekoratif yang tidak perlu.

- **Staggered Entrance:** Komponen hero menggunakan animasi `--delay` (0ms s/d 360ms) dengan `cubic-bezier(0.16, 1, 0.3, 1)`.
- **Scroll Reveal:** Section konten menggunakan IntersectionObserver (`threshold: 0.12`) dengan transisi `translateY(28px)` -> `0`.
- **Parallax Background:** Canvas peta bergeser lembut saat scroll (`translateY(scrollY * 0.35px) scale(1.1)`).
- **Interactive Feedback:** Hover buttons `-translate-y-px` dengan penyesuaian kecerahan dan bayangan lembut.


## Do's and Don'ts

### Do's:
- Gunakan data geospasial asli dan akurat Kabupaten Bojonegoro.
- Pastikan rasio kontras teks selalu memenuhi standar WCAG AA (minimal 4.5:1 untuk teks normal).
- Jaga target sentuh pada mobile minimal `44px x 44px`.
- Gunakan format link markdown yang valid dan clickable.
- Gunakan nuansa palet blue/slate yang konsisten.

### Don'ts:
- Jangan gunakan karakter em dash (`—`) di teks UI publik sesuai aturan antislop (gunakan koma, titik dua, atau tanda kurung).
- Jangan gunakan margin negatif seperti `-mt-14` yang dapat merusak tinggi viewport hero.
- Jangan gunakan efek glassmorphism berlebihan secara serentak di semua elemen (cukup pada floating navbar sebagai aksen utama).
- Jangan menambahkan ikon AI generik (sparkle, orb, robot) tanpa relevansi kartografi/geospasial.

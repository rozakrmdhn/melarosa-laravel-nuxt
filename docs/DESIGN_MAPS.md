# Paten Desain & Spesifikasi Sistem UI/UX: Halaman Peta Spasial WebGIS (/maps)
**Aplikasi**: Melarosa - Sistem Informasi Spasial Kabupaten Bojonegoro  
**Versi Spesifikasi**: 2.0 (Paten Resmi Desain WebGIS)  
**Tanggal Pengesahan**: 22 Oktober 2026  
**Status**: Standar Resmi & Mengikat (*Binding Architectural Standard*)

---

## 1. Filosofi & Prinsip Inti Desain (Core Tenets)

Halaman peta interaktif (`/maps`) Melarosa didesain dengan standar antarmuka spasial modern kelas dunia (*world-class WebGIS*), mengadopsi kesederhanaan operasional dan kejelasan visual yang terinspirasi dari aplikasi pemantauan spasial *real-time* terkemuka (seperti *Trafik.live* dan sistem transit modern).

Prinsip dasar yang wajib dipatuhi:

1. **Kanvas Peta Bebas Halangan (*Uncluttered Canvas First*)**:
   - Area peta utama adalah panggung utama eksplorasi data. Seluruh bilah mengambang di bawah tengah layar (*floating bottom location bar*) ditiadakan. Kanvas desktop 100% bersih, lapang, dan bebas rintangan visual.
2. **Tata Letak Split Permanen (*Permanent Two-Way Split View*)**:
   - Pada layar desktop, antarmuka terbagi menjadi 2 bagian tetap: Panel Kiri (lebar standar 400px, responsif 350px pada tablet) dan Kanvas Peta (mengisi seluruh sisa layar).
   - Tidak ada tombol toggle untuk membuka dan menutup panel kiri di desktop: kedua bagian selalu aktif dan berdampingan secara permanen.
3. **Kontras Data Tematik Tinggi di Atas Basemap Netral (*High Contrast Thematic Focus*)**:
   - Basemap default menggunakan tema monokromatik lembut (*Carto Positron / Esri Light Gray*) agar data vektor tematik (jalan poros desa, segmen jalan, batas kecamatan, titik fasilitas) menonjol dengan ketajaman warna tinggi tanpa visual noise.
4. **Navigasi Teks Murni Tanpa Ikon & Tanpa Badge (*Pure-Text Minimalist Tabs*)**:
   - Tab navigasi utama terdiri dari tiga pilihan: **"Peta"**, **"Dataset"**, dan **"Geopark"**.
   - Larangan mutlak: Dilarang memasang ikon grafis dan dilarang menampilkan badge angka/counter pada tombol tab menu ini.
5. **Radius 100% pada Tab Menu (*Pure Pill Segmented Control*)**:
   - Seluruh kontainer dan tombol tab menu dirancang dengan sudut lengkung penuh 100% (`rounded-full` / pill shape) untuk visual yang anggun, ergonomis, dan modern.
6. **Arsitektur Modular Berbasis Kartu (*Card Stack Architecture*)**:
   - Informasi disajikan dalam kartu-kartu ringkas bersudut lembut (*rounded-xl / rounded-2xl*) dengan jarak visual (*spacing*) konsisten, hierarki tipografi tegas, dan micro-copy informatif.
7. **Pemuatan Cepat & Indikator Progresif Kanvas Peta (*Instant Sidebar & Progressive Map Loader*)**:
   - Struktur navigasi, header, dan footer Panel Kiri ditampilkan secara instan tanpa menunggu peta siap.
   - Area Kanvas Peta dilengkapi dengan overlay pemuatan kartografis bertahap (*stage progress bar* 0% hingga 100%) untuk mengeliminasi layar kosong (*blank putih*) saat modul OpenLayers dan data spasial diunduh pertama kali.
8. **Kepatuhan Mutlak Anti-Slop (*Strict Anti-Slop Discipline*)**:
   - Seluruh target sentuh (*touch target*) tombol interaktif minimal 44x44 piksel pada layar sentuh.
   - Dilarang membuat tombol mati (*zero dead buttons*): setiap elemen interaktif memiliki fungsi nyata.
   - Dilarang menggunakan karakter em dash (`-`): gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau kurung `()`.
   - Rasio kontras teks memenuhi standar WCAG AA (minimal 4.5:1 terhadap latar belakang).

---

## 2. Arsitektur Tata Letak & Geometri Layar

### A. Tampilan Desktop (Layar >= 768px)

```
+------------------------------------+---------------------------------------------------------------+
| [ PANEL KIRI: SIDEBAR IN-FLOW ]    | [ APP PUBLIC NAVBAR (Floating di atas Kanvas Peta) ]          |
| aside w-[350px] lg:w-[400px]       | Logo Pemkab Bojonegoro | Navigasi Portal | Tema | Login        |
| border-r border-slate-200 / white/8+---------------------------------------------------------------+
| Peta Interaktif (Medium/Semibold)  | [ PANEL KANAN: MapCanvas Container (flex-1 h-full min-w-0) ]  |
| ( Peta ) | ( Dataset ) | ( Geopark )   |                                                               |
| [Tab Menu Pill: Tanpa Garis]       |                                                               |
|                                    |                                                               |
| [ UNIVERSAL SEARCH BAR PERSISTEN ] |                                                               |
| [Input Cari Kecamatan / Layer]     |                                                               |
| - Dropdown Rekomendasi 28 Kec      |                                                               |
| - Tutup lewat tombol X atau Escape |                                [Klaster Navigasi Kanan Bawah] |
|                                    |                                 - Reset Orientasi Kompas (U)  |
| [ SUB-VIEW TAB DINAMIS ]           |                                 - Lokasi Terkini & Speed(GPS) |
| - Daftar Layer / Geoportal         |                                 - Zoom In / Zoom Out          |
| - Banner Koordinat & Info Fitur    |                                                               |
|------------------------------------| [Klaster Tampilan Kiri Bawah]                                 |
| [ FOOTER BOTTOM PANEL KIRI ]       | - Basemap Selector Panel                                      |
| Status Lapisan | Home | Bagikan     | - Toggle Grid Kartografi                                      |
| Data peta © OSM · BAPPEDA          |                                                               |
+------------------------------------+---------------------------------------------------------------+
```

#### Spesifikasi Geometri Desktop:
- **Konsep Tata Letak**: Kontainer pembungkus utama (*wrapper maps*) dengan tata letak split flex 2 bagian in-flow (Panel Kiri + Panel Kanan Map Canvas). Bukan Panel Kiri floating di atas Map Canvas.
- **Header Panel Kiri**: Judul proporsional **"Peta Interaktif"** berukuran medium dengan bobot semibold/bold (`text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100`) di puncak panel dengan padding responsif `px-4 sm:px-5 pt-4 sm:pt-5 pb-2`.
- **Lebar Panel Kiri**: Standar `400px` pada desktop (`lg:w-[400px]`), responsif `350px` pada tablet (`md:w-[350px]`), tersusun berdampingan (*in-flow side-by-side*) dengan Panel Kanan.
- **Padding Konten Panel Kiri**: Menggunakan padding responsif standar `px-4 sm:px-5` pada header, tab menu, search bar, footer bottom, serta seluruh area sub-view isi konten panel (ActiveLayersView, SearchListView, LayerDetailView, SearchItemAboutView) sehingga hierarki dan garis tepi vertikal tersusun selaras tegak lurus di seluruh ukuran layar.
- **Penempatan Panel Kiri**: Komponen semantik `<aside>`, tanpa overlay floating `fixed`, tanpa tombol toggle buka/tutup, dan tanpa tombol close header.
- **Footer Sisi Bawah Panel Kiri**: Komponen *footer bottom* permanen (`border-t border-slate-200/90 dark:border-white/8 bg-slate-50/80 dark:bg-[#070c16]/80 px-4 sm:px-5 py-2.5 sm:py-3`) yang menyajikan indikator status lapisan aktif, tombol aksi cepat *Home* (pusatkan peta ke Bojonegoro), tombol *Bagikan Peta*, serta baris standar atribusi peta (*Data peta © kontributor OpenStreetMap · BAPPEDA Kab. Bojonegoro*).
- **Panel Kanan (Map Canvas)**: Kontainer semantik `<main class="flex-1 h-full min-w-0 relative overflow-hidden">` yang mengisolasi render kanvas OpenLayers khusus pada area sisa layar (`calc(100vw - 400px)` pada desktop / `calc(100vw - 350px)` pada tablet), sehingga titik pusat peta dan interaksi spasial akurat tanpa tertutup panel samping.
- **Offset Header Publik & Hierarki Z-Index Bebas Shadow**:
  - Pada desktop: Kontainer navbar publik menyesuaikan posisi mengambang di atas Panel Kanan (`left-0 md:left-[350px] lg:left-[400px] right-0`).
  - Pada mobile: Posisi mengambang penuh (`inset-x-0`).
  - **Paten Bebas Shadow Lembar Bawah (Z-Index & Stacking Context)**: Header navigasi publik (`PublicNavbar`) diteleportasi langsung ke level root dokumen `<body>` (`<Teleport to="body">`) dengan kelas `z-[70]`. Dengan evaluasi pada stacking context yang sama, header navigasi berada strictly di atas seluruh lembar bawah mobile (`MapMobileBottomSheet` di `z-[39]`, `MapShareBottomSheet` di `z-[46]`, dan `MapBasemapBottomSheet` di `z-50`). Efek bayangan ke atas (*upward box shadow*) maupun translasi lembar bawah pada snap penuh (`full` 92% vh) dijamin selalu berada di bawah header navigasi sehingga tombol identitas logo, pencarian beranda, sakelar tema, dan hamburger menu tetap bersih, kontras tinggi, dan leluasa diklik.
- **Pencarian Header Navbar Khusus Halaman Beranda (*Homepage Exclusive Navbar Search*)**: Fitur pencarian pada header navbar publik (desktop input `Ctrl K` dan tombol pemicu pencarian mobile) dikhususkan hanya aktif pada halaman utama beranda (`/`). Pada halaman peta (`/maps`), fitur pencarian di header navbar dinonaktifkan sepenuhnya dan dipusatkan secara eksklusif di dalam Panel Kiri (desktop) dan Lembar Bawah Mobile (*Mobile Bottom Sheet*) agar kanvas peta lapang dan alur pencarian terfokus tanpa redundansi.
- **Harmonious Split & Utility Grouping pada Kanvas Peta**:
  - **Klaster Kiri Bawah (Display & Layer Group)**: Ditempatkan di sisi kiri bawah kanvas peta (`bottom-4 left-3 sm:left-4` dengan offset dinamis `calc(snap + 16px)` di mobile) dalam tata letak horizontal `flex items-end gap-2`. Mengelompokkan fungsi tampilan lapisan dasar (*BasemapPanel*) dan tombol sakelar Grid Kartografi (*Cartographic Grid & Edge Coordinates Toggle*).
  - **Klaster Kanan Bawah (Navigation Group)**: Ditempatkan di sisi kanan bawah kanvas peta (`bottom-4 right-3 md:right-4`). Hanya berisi kontrol navigasi murni:
    1. **Tombol Arah Mata Angin (North Arrow Compass Dial & Reset Utara)**: Menampilkan orientasi arah Utara secara real-time yang berputar sinkron saat kanvas peta diputar (`transform: rotate(deg)`). Mengklik tombol ini mereset orientasi peta tegak lurus mengarah ke Utara (`rotation = 0, duration: 350ms`).
    2. **Tombol Geolocation (GPS Live Tracking & Speed)**: Penguncian koordinat pengguna real-time dengan pill terpadu kecepatan berkendara (km/j) dan heading cone.
    3. **Tombol Zoom In & Zoom Out**: Pengaturan tingkat perbesaran kanvas peta.
  - **Peniadaan Tombol Share di Kanvas**: Tombol Share ditiadakan dari floating controls kanvas peta karena telah terintegrasi secara permanen dan elegan pada Footer Panel Kiri desktop dan Lembar Bawah Mobile / Share Sheet.

---

### B. Tampilan Mobile (Layar < 768px)

```
+--------------------------------------------------+
| [ Header Ringkas Mobile: Logo Pemkab+Tema+Menu ] |
+--------------------------------------------------+
|                                                  |
|           [ PETA INTERAKTIF LAYAR PENUH ]        |
|               [ Marker Pin Koordinat ]           |
|                                                  |
| [Basemap Switcher Mini: bottom-[calc(snap+16px)]]|
+--------------------------------------------------+
| === [Handle Bar Drag Lembar Bawah (Swipe Bar)] ==|
| ( Peta ) | ( Dataset ) | ( Geopark )               |
| [Tab Menu Pill Tanpa Garis Pembatas Atas/Bawah]  |
|--------------------------------------------------|
| KONDISI 1: Quick Search + Layer Aktif + Pintasan |
| KONDISI 2: Detail Layer & Slider Opasitas        |
| KONDISI 3: Hasil Identifikasi Spasial & Titik    |
+--------------------------------------------------+
```

#### Spesifikasi Lembar Bawah Mobile (*Bottom Sheet*):
- **Arsitektur Minimalis Bebas Header (*Direct Grab Handle & Tab Menu*)**:
  - Lembar bawah mobile meniadakan bilah header konvensional (ikon panel, judul teks tebal, subjudul status, dan tombol aksi maximize/minimize/chevron).
  - Puncak lembar bawah langsung menyajikan bilah geser sentuh (*Grab Handle Pill*) ergonomis yang dapat diketuk untuk beralih mode snap (`min` <-> `peek` <-> `full`) maupun digeser dengan gestur sentuhan *fluid*.
  - Tepat di bawah Grab Handle, langsung tersaji Tab Menu Segmented Control 100% Radius: **"Peta"**, **"Dataset"**, dan **"Geopark"**, diikuti kotak pencarian universal terpadu. Hal ini mengeliminasi pemborosan ruang vertikal (*zero header clutter*), memperluas area pandang kanvas peta hingga ~80% viewport, dan mempercepat alur navigasi pengguna.
- **3 Tingkat Snap Ergonomis (*Snap Levels*)**:
  1. `min` (~150px / default resting height): Tinggi default saat mode mobile device, batasnya menampilkan handle bar, tab segmented control, hingga kotak fitur pencarian secara utuh. Kanvas peta tetap leluasa dijelajahi hingga ~80% viewport.
  2. `peek` (50% viewport height / separuh layar): Mode separuh layar untuk tinjauan cepat lapisan peta, ulasan detail geosite/wilayah, dan eksplorasi spasial interaktif. **Merupakan tingkat snap aktif otomatis saat pengguna melakukan get feature (identifikasi objek peta), filtering wilayah (memilih kecamatan), atau menampilkan geosite geopark**, sehingga objek yang disorot pada kanvas peta tetap terlihat lapang di paruh atas layar.
  3. `full` (92% viewport height): Mode penuh untuk eksplorasi mendalam (detail atribut spasial kompleks, tabel KUGI, atau katalog geoportal). **Juga merupakan tingkat snap standar yang terbuka otomatis saat pengguna mengklik atau mengetik di form pencarian (baik dari mode default `min` maupun separuh layar `peek`)**, dengan ukuran tinggi yang selaras 100% presisi seperti saat ditarik ke atas (*drag-up aligned*).
- **Kompensasi Keyboard Virtual & Penangkapan Interaksi Pencarian (*Virtual Keyboard Stability & Search Auto-Full*)**:
  - Saat kotak pencarian diklik, difokuskan, atau diketik oleh pengguna di perangkat bergerak (baik saat lembar bawah berada di snap default `min` maupun separuh layar `peek`), lembar bawah secara otomatis bertransisi naik membuka **penuh (snap `full` 92% viewport)** dan scroll window dinetralkan mutlak pada `(0, 0)`. Ukuran tinggi penuh ini selaras identik dengan tinggi saat ditarik ke atas.
  - Dasar lembar bawah dijangkarkan secara permanen di bagian bawah layar dengan latar belakang **warna solid (`bg-white dark:bg-[#0b0f19]`)** dan ekstensi perpanjangan dasar (`BOTTOM_BLEED_PX = 120px`), sehingga lembar bawah tidak pernah tampak terangkat atau mengambang saat keyboard virtual muncul. Penyesuaian tinggi keyboard dialokasikan khusus ke `padding-bottom` pada kontainer scroll internal, menjamin hasil pencarian dapat digulir leluasa di atas keyboard.
  - Ketika pengguna mengklik detail active layer (*LayerDetailView*), sistem mempertahankan ukuran lembar bawah yang sedang terbuka (tetap `full` jika sedang di mode penuh, tetap `peek` jika sedang di mode separuh layar, atau membuka `peek` jika di mode default) agar transisi tampilan konsisten (*consistent drawer sizing*).
  - Ketika pengguna memilih salah satu item hasil pencarian atau memicu aksi spasial (seperti **get feature / identifikasi fitur**, **filtering wilayah / memilih kecamatan**, atau **menampilkan geosite geopark**), lembar bawah secara cerdas **turun ke separuh layar (snap `peek` 50% viewport)** agar kanvas peta di atasnya tetap aktif menampilkan objek yang dipilih.
  - Tinggi dasar lembar bawah dihitung berdasarkan `window.innerHeight` (layout viewport stabil), menjamin kanvas peta OpenLayers dan bilah navigasi di latar belakang 100% terkunci diam di posisinya (*zero background drag-up / zero window displacement*).
- **Translasi Anti-Gap (*Zero-Gap Translation*) & Spacer Rata Browser Safari**: Wadah sheet diposisikan tetap di bagian bawah dengan perpanjangan dasar solid (*bottom bleed* 120px) menggunakan transformasi `translateY` murni. Pada saat snap `full`, spacer bagian bawah dinetralkan presisi setinggi 120px (`${BOTTOM_BLEED_PX}px`) dengan warna solid sehingga konten paling bawah duduk pas dan rata tepat di atas bilah URL browser Safari tanpa jarak spacer kosong berlebih.
- **Fisika Pegas & Inersia**: Dilengkapi deteksi kecepatan sentuhan (*velocity flick detection*) dan efek *rubber-band* lembut saat ditarik melampaui batas atas atau bawah.
- **Interaksi Kanvas & Aturan Emas Identifikasi Spasial (*Canvas Tap Dismiss & Gated Identify Rule*)**:
  1. Pada snap `min` (~150px / resting height default): Kanvas peta lapang (~80% layar). Tap pada kanvas berfungsi 100% untuk **Identifikasi Fitur Spasial** (menjalankan query Martin MVT/WMS, menyorot objek dengan Style Selected Feature trayek UI modern, dan menaikkan lembar bawah secara otomatis ke snap `peek` 50% untuk menyajikan hasil). Kanvas bebas dari popup koordinat badge maupun pulse marker radar.
  2. Pada snap `peek` (50% vh) maupun snap `full` (92% vh): Pengguna sedang membaca data/katalog. Tap pada kanvas peta bertindak murni sebagai **Gestur Penutup / Reset (*Dismiss / Collapse Gesture*)** yang langsung menurunkan lembar bawah ke posisi default `min` serta menutup pin seleksi aktif. Query identifikasi fitur spasial dibatalkan sepenuhnya (*bypassed*) sehingga tidak mengganggu alur navigasi pengguna.
  3. **Bebas Backdrop (*Zero Backdrop on Full Snap*)**: Pada saat lembar bawah terbuka penuh (snap `full` 92% vh), latar belakang backdrop gelap/blur ditiadakan (*no backdrop overlay*) sehingga area kanvas peta di atas lembar bawah (area ~8% viewport) tetap terlihat jernih dan bebas distorsi visual. Pengguna tetap dapat mengetuk area kanvas atas tersebut untuk gestur cepat menutup lembar bawah ke posisi `min`.
  4. **Arsitektur Pengurutan Layer Mobile Anti-Redirect (*Mobile Safe Layer Reordering*)**:
     - **Pemberhentian Native HTML5 Drag pada Layar Sentuh**: Properti `:draggable` native HTML5 dinonaktifkan secara otomatis saat terdeteksi perangkat layar sentuh (`!isTouchDevice`), mencegah Chrome di Android memicu penangkapan seleksi teks sistem dan pengalihan paksa (redirect) ke pencarian Google (*Touch-to-Search / Omnibox intent*).
     - **Kontrol 1-Tap Naik dan Turun (*Tactile 1-Tap Reorder Chevrons*)**: Setiap kartu layer aktif dibekali tombol panah kompak Naik (`i-lucide-chevron-up`) dan Turun (`i-lucide-chevron-down`) dengan *expanded hit-target* (memenuhi standar WCAG minimum 44x44px) serta status nonaktif (*disabled visual state*) saat layer berada di puncak atau dasar tumpukan. Aksi 1-tap ini menjamin penataan urutan tumpukan layer berjalan instan tanpa resiko bentrok gestur sentuh vertikal lembar bawah (*zero gesture collision*).
     - **Touch-Drag Mandiri & Aman pada Handle Grip**: Gagang geser (*grip-vertical*) pada mobile menangani event sentuh khusus (`touchstart`, `touchmove`, `touchend`) dengan `event.preventDefault()` dan `event.stopPropagation()`, memblokir seleksi teks browser dan memungkinkan pergeseran urutan secara langsung berbasis elemen spasial aktual (`document.elementFromPoint`).
  5. **Paten Alih Kontekstual Pintar Identifikasi Spasial (*Smart Contextual Identify & Return Memory*)**: Ketika pengguna sedang berada di tab katalog (**Dataset** atau **Geopark**) dan memicu identifikasi fitur dari kanvas peta:
     - Sistem secara otomatis menyimpan tab asal ke dalam memori (`previousTabBeforeIdentify`) dan mengalihkan tab aktif ke **"Peta"** sehingga komponen `IdentifyView` (hasil identifikasi objek, koordinat, instansi, atribut KUGI) tampil utuh, benar, dan berstatus aktif di pill tab Peta.
     - Header lembar bawah memprioritaskan judul **"Identifikasi Fitur"** dengan subjudul nama objek/koordinat.
     - Ketika pengguna menutup inspector (mengeklik tombol "X" atau tap pada kanvas peta untuk dismiss), sistem **secara otomatis mengembalikan tab aktif ke tab asal pengguna ("Dataset" atau "Geopark")** sehingga posisi penelusuran katalog tidak hilang (*zero context loss*).
     - Jika saat inspector terbuka pengguna secara sadar mengeklik tab lain, memori tab asal dinetralkan secara bersih.

#### Posisi Responsif Kontrol Mengambang (*Floating Controls Clearance*):
- **Sinkronisasi Snap Lembar Bawah**: Tombol cepat ganti peta dasar `BasemapPanel` (kiri bawah), bilah status `MapScaleCoordinateBar` (tengah bawah), dan klaster navigasi `MapControls` (kanan bawah) bergerak responsif menempel persis di atas bibir atas Lembar Bawah dengan jarak bersih `16px`:
  1. Pada snap `min` (~150px): `bottom-[calc(150px+env(safe-area-inset-bottom,0px)+16px)]` sehingga kontrol mengambang bertengger rapi tepat di atas kotak pencarian tanpa tertutup lembar bawah.
  2. Pada snap `peek` (50% vh / separuh layar): `bottom-[calc(50dvh+16px)]` sehingga kontrol naik selaras menjaga jarak pandang interaktif.
  3. Pada snap `full` (92% vh): bertransisi lembut menghilang (`opacity-0 pointer-events-none translate-y-4`) di perangkat mobile agar tidak menghalangi lembar katalog data, sementara di desktop tetap terkunci di `md:bottom-4 md:opacity-100 md:pointer-events-auto md:translate-y-0`.
  4. Durasi dan kurva animasi menggunakan `duration-[580ms] cubic-bezier(0.16, 1, 0.3, 1)` identik dengan fisika gerak lembar bawah untuk sinkronisasi pergerakan yang mulus.
- **Harmonisasi Ukuran & Geometri Simetris Kiri-Kanan (*Symmetric Floating Controls Geometry*)**:
  - Klaster kontrol kiri bawah (tombol pusatkan ke batas kabupaten, toggle grid kartografi, dan toggle peta dasar `BasemapPanel`) diselaraskan 100% presisi dengan klaster navigasi kanan bawah (`MapControls`):
    - **Dimensi Standar**: Lebar dan tinggi 44px x 44px (`size-11` / `min-w-[44px] min-h-[44px]`) seragam di kedua sisi kanvas peta.
    - **Kelengkungan Sudut (*Border Radius*)**: `rounded-lg sm:rounded-xl` konsisten di seluruh kontrol mengambang.
    - **Pemisah Antar Tombol (*Divider*)**: Garis netral halus `w-full h-px bg-gray-200/80 dark:bg-slate-800`.
    - **Jarak Antar Grup Tumpukan (*Stack Gap*)**: Spacing vertikal `gap-1.5` yang identik di sisi kiri maupun kanan.
    - **Gaya Ikon**: Ikon navigasi berukuran proporsional `size-4 stroke-[2.25]`.

---

## 3. Standar Segmented Control Tab (Radius 100% & Bebas Ikon/Badge/Garis)

Tab navigasi pada Panel Kiri desktop maupun mobile drawer tunduk pada aturan ketat berikut:

```
( Peta )   |   ( Dataset )   |   ( Geopark )
```

1. **Format Teks Murni**:
   - Tab 1: Label persis `"Peta"`.
   - Tab 2: Label persis `"Dataset"`.
   - Tab 3: Label persis `"Geopark"`.
2. **Larangan Grafis & Notifikasi**:
   - Dilarang menyertakan elemen ikon apapun (seperti `UIcon`, SVG, atau font-icon).
   - Dilarang menampilkan counter angka atau badge informasi apapun (seperti bulatan notifikasi atau pill badge status).
3. **Spesifikasi Radius 100% (*Pure Pill Design*) & Tipografi Proporsional**:
   - Kontainer Tab Rail: `p-1 bg-slate-200/60 dark:bg-slate-800/60 rounded-full (border-radius: 100%) grid grid-cols-3 gap-1`.
   - Ukuran Teks Proporsional: Ditingkatkan secara sengaja menjadi `text-sm font-bold tracking-tight` pada desktop dan `text-sm sm:text-base font-bold tracking-tight` pada perangkat mobile agar label Peta, Dataset, dan Geopark tampil tegas, proporsional, dan nyaman dibaca seketika.
   - Tombol Aktif: `bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs rounded-full (border-radius: 100%) min-h-[44px] select-none text-center`.
   - Tombol Nonaktif: `text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-full (border-radius: 100%) min-h-[44px] select-none text-center`.
   - Aturan radius 100% ini berlaku konsisten baik di Panel Kiri desktop maupun pada lembar bawah mobile.
4. **Header Peta Interaktif & Bebas Garis Pembatas (*Clean Header & Border-Free Tab*)**:
   - Panel Kiri dibuka dengan judul header: **"Peta Interaktif"** (`font-bold`, ukuran medium `text-base sm:text-lg`, huruf pertama kapital).
   - **Arsitektur Tombol Bagikan Tunggal per Perangkat (*Single Focused Share Button per Device Viewport*)**:
     - **Mode Desktop**: Tombol aksi tunggal *"Bagikan"* (`i-lucide-share-2` + teks) ditempatkan rapi di sisi kanan header Panel Kiri sejajar judul "Peta Interaktif", memicu dialog modal `MapShareModal` lengkap dengan pratinjau thumbnail OpenGraph (OG) 16:9, opsi salin tautan, dan ekspor media sosial.
     - **Mode Mobile**: Karena Panel Kiri di mobile disajikan di dalam lembar bawah (*bottom sheet*), tombol Bagikan Peta (`i-lucide-share-2`) diposisikan secara eksklusif pada bilah tab rail lembar bawah untuk akses jempol 1-tap yang ergonomis.
     - Seluruh tombol share redundan (banner promosi pada daftar layer aktif dan floating button di kanvas peta) ditiadakan secara permanen sehingga antarmuka bebas dari tumpukan tombol ganda (*anti-button clutter*).
   - Ditiadakan garis batas (*border divider*) baik di atas maupun di bawah Tab Menu, sehingga alur visual dari header "Peta Interaktif" ke Tab Menu dan bidang konten di bawahnya menyatu mulus (*seamless*).
5. **Pencarian Universal Multi-Kategori pada Konten Menu (*In-Flow Menu Search: Wilayah, Dataset & Infrastruktur*)**:
   - **Inspirasi UI/UX Modern (*Trafik.live / Bus Jakarta Live*)**: Mengadopsi arsitektur pencarian terstruktur berbasis kategori langsung di dalam konten menu peta (*in-flow content*), bukan *floating autocomplete* bertumpuk.
   - **Tiga Klaster Pencarian Terintegrasi**:
     1. **Wilayah & Kecamatan**: 28 kecamatan Kabupaten Bojonegoro beserta ringkasan deskripsi wilayah dan navigasi langsung ke titik pusat wilayah.
     2. **Dataset & Layer Spasial**: Layer aktif pada peta dan seluruh katalog tematik Geoportal JIGN Bojonegoro (WMS, Vector Tiles / MVT, GeoJSON) dilengkapi status indikator `Aktif` dan tombol tambah langsung ke peta.
     3. **Infrastruktur & Landmark**: Objek dan fasilitas penting Bojonegoro (Kayangan Api, Waduk Pacal, Jembatan Sosrodilogo, Blok Cepu Banyu Urip, Geopark Wonocolo, Alun-Alun Bojonegoro, Bendung Gerak, dll).
   - **Tombol Circle X Close (*Explicit Return to Main Map*)**: Pada sisi kanan kotak pencarian, ketika mode pencarian aktif atau terdapat kueri pencarian, ditampilkan tombol lingkaran penutup (*circle x close button*). Mengklik tombol ini langsung menutup area hasil pencarian, membersihkan kueri, dan mengembalikan tampilan panel secara instan ke halaman utama peta (*layers* / daftar layer aktif).
    - **Sub-Halaman Tentang Item Pencarian (*Search Item About / Detail Sub-Page*)**: Mengklik salah satu kartu hasil pencarian (Wilayah & Kecamatan, Dataset & Layer Spasial, atau Infrastruktur & Landmark) membuka sub-halaman "Tentang" dari objek yang dipilih di dalam panel kiri, sekaligus mengarahkan dan memusatkan peta ke koordinat objek secara dinamis.
    - **Header Hero Section Terpadu (*Unified Hero Header Bar*)**:
      - **Avatar Bulat (*Circular Badge*)**: Lingkaran solid berukuran `size-11 rounded-full` di sisi kiri yang menampilkan kode 3 huruf (seperti `BJN`, `BLN`, `DND` untuk wilayah kecamatan, `WMS`, `MVT`, `WFS` untuk protokol dataset, atau kode kategori untuk landmark) dengan kontras tinggi.
      - **Judul & Subjudul (*Bold Title & Context Subtitle*)**: Judul tebal berukuran besar (`text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100`) dan subjudul informatif di bawahnya (`text-xs text-slate-500 dark:text-slate-400`).
      - **Tombol Circle X Close**: Tombol lingkaran penutup (`size-8 rounded-full bg-slate-100 dark:bg-white/10`) di samping kanan atas. Menutup sub-halaman detail SearchItemAboutView mengembalikan pengguna ke state rute SearchListView (daftar pencarian) dengan kueri dan daftar hasil pencarian tetap terjaga (*preserves search query & results*). Dari SearchListView, pengguna dapat menutup pencarian seutuhnya melalui tombol circle X pada bar pencarian untuk kembali ke halaman utama peta.
    - **Tata Letak Permukaan Mulus Tanpa Border Kartu (*Seamless Border-Free Section Architecture*)**:
      - Untuk menghilangkan kelelahan visual akibat tumpukan garis kotak (*anti-border fatigue*), sub-halaman SearchItemAboutView meniadakan pembungkus kartu bergaris batas tebal (`border border-slate-200/80 shadow-xs`) maupun garis pembatas di setiap baris metadata.
      - Konten mengalir secara alami langsung di atas permukaan kanvas panel (`px-4 py-3.5 space-y-5`), memanfaatkan kontras tipografi semibold, latar bernuansa lembut tanpa border (`bg-slate-50 dark:bg-white/[0.03] rounded-xl`), dan jarak vertikal proporsional.
      - **Tombol Aksi Leluasa Tanpa Garis Batas**: Seluruh tombol aksi interaktif (tambah/hapus layer, pusatkan peta ke cakupan/wilayah, dan tombol kembali ke daftar pencarian) tampil bersih dengan warna aksen solid dan tombol netral tanpa border.
    - **Kelengkapan Metadata Dataset Geoportal JIGN**:
      - **Baris Status & Protokol**: Indikator visual aktif/belum ditambahkan beserta badge protokol spasial (`WMS`, `MVT`, dll) dalam alur inline.
      - **Deskripsi & Cakupan Tematik**: Uraian fungsi dataset spasial dan cakupan data tematik dengan tipografi proporsional.
      - **Metadata Spesifikasi Teknis**: Instansi/OPD pengelola, identitas layer teknis (`layer_name`), kategori tematik KUGI, sistem koordinat (EPSG:4326 / EPSG:3857), dan sumber data simpul JIGN Bojonegoro dalam panel lembut bebas garis pemisah.
      - **Simbol Legenda Resmi**: Gambar legenda resmi simpul Geoportal JIGN dari Geoserver GetLegendGraphic dalam bingkai lembut.
    - **Fitur Close Pencarian Eksplisit (*Explicit Close Return*)**: Penutupan konten pencarian dikendalikan secara eksplisit melalui fitur close pencarian: tombol lingkaran penutup (*circle x close button*) pada kotak pencarian maupun di bar hero sub-halaman tentang, tombol navigasi keyboard Escape, pemilihan salah satu item hasil pencarian, atau perpindahan tab menu. Panel pencarian tidak ditutup secara otomatis saat kursor mouse meninggalkan panel (*no auto-close on mouseleave*), sehingga pengguna dapat leluasa mengarahkan kursor dan mengeksplorasi kanvas peta tanpa khawatir konten hasil pencarian tertutup secara mendadak.
    - **Standar Aksesibilitas & Anti-Slop**: Kartu hasil pencarian dan tombol aksi berukuran minimal 44px (`min-h-[44px]`), ikon kategori bersudut bulat penuh, tipografi semibold tegas dengan kontras WCAG AA, dan dukungan navigasi keyboard Escape.

---

## 4. Sistem Wadah Rute & Sub-Komponen Panel Kiri (LeftPanel Container Route Architecture)

Komponen `LeftPanel.vue` berfungsi sebagai **Wadah Rute (*Route Container / Shell*)** untuk seluruh navigasi sub-halaman di sisi panel kiri peta. Seluruh konten isian dari masing-masing sub-halaman dipecah menjadi komponen tersendiri di dalam direktori `components/maps/left-panel/` untuk menjaga kode tetap modular, terisolasi secara status, dan mudah dirawat.

Ketika pengguna masuk ke sub-view, navigasi kembali tidak lagi menggunakan tombol kembali bertingkat beserta icon panah (`< Kembali`), melainkan menggunakan **tombol lingkaran penutup (*circle x close button*)** yang konsisten di sisi kanan atas header hero section untuk menutup sub-view dan kembali langsung ke state rute utama.

### Diagram Arsitektur Rute Panel Kiri:

```
[ LeftPanel.vue (Container / Route Shell) ]
 ├── Header "Peta Interaktif" (Desktop Only)
 ├── Tab Segmented Control Pill: ( Peta ) | ( Dataset ) | ( Geopark )
 ├── Top Bar: Universal Search Input (Mode List)
 └── Body Content Router (<Transition name="tab-fade">):
      ├── [Mode Pencarian Aktif - List]:
      │    └── <SearchListView /> (Wilayah, Dataset & Geosite Geopark)
      ├── [Mode Pencarian Aktif - Detail]:
      │    └── <SearchItemAboutView /> (Hero Header, Foto Banner, Spesifikasi Geopark/JIGN)
      ├── [Tab Peta - Sub-View Router]:
      │    ├── 'layers': <ActiveLayersView /> (Daftar Layer, Drag Reorder, Pintasan)
      │    ├── 'layer-detail': <LayerDetailView /> (Hero Header, Card Borders, Visibilitas, Slider, Filter, [X])
      │    └── 'identify': <IdentifyView /> (Hero Header, Bersihkan Pin, [X], WMS Attributes & Coordinates)
      ├── [Tab Dataset]:
      │    └── <GeoportalLayerSection /> (Katalog Layer Simpul JIGN Bojonegoro)
      └── [Tab Geopark]:
           └── <GeoparkListView /> (Katalog Resmi Geopark: 31 Geosite Terverifikasi, Filter 3 Pilar & Navigasi Peta)
```

Tab Peta dan Navigasi dikelola dengan state reaktif:
```ts
type MainTab = "layers" | "dataset" | "geopark";
type PetaSubView = "layers" | "layer-detail" | "identify";
type SearchSubView = "list" | "detail";
type GeoparkSubView = "list" | "detail";

const localActiveTab = ref<MainTab>("layers");
const petaSubView = ref<PetaSubView>("layers");
const searchSubView = ref<SearchSubView>("list");
const geoparkSubView = ref<GeoparkSubView>("list");
const selectedGeoparkSite = ref<GeoparkSite | null>(null);
```

---

### 4.1. Sub-Komponen 1: `SearchListView.vue` (Daftar Rekomendasi Pencarian)
Komponen penyaji hasil pencarian in-flow terstruktur berdasarkan 3 kelompok:
1. **Wilayah & Kecamatan**: 28 kecamatan Kabupaten Bojonegoro beserta zona, deskripsi, dan bounding box wilayah presisi.
2. **Dataset & Layer Spasial**: Layer aktif pada peta dan katalog Geoportal JIGN Bojonegoro (WMS, Vector Tiles, GeoJSON).
3. **Geosite & Geopark Bojonegoro**: 31 situs resmi dari API Geopark Bojonegoro (Keragaman Geologi, Keragaman Hayati, dan Keragaman Budaya) yang menggantikan dummy landmark.
4. **Paten Navigasi Spasial Wilayah Kecamatan (*Territory Bounding Box Framing - Zero Point Marker Pin*)**:
   - **FitBound Berbasis Bounding Box Poligon Geometri**: Ketika pengguna mencari atau memilih wilayah kecamatan (dari `SearchListView`, tombol *"Pusatkan Peta ke Wilayah Ini"* pada `SearchItemAboutView`, maupun melalui filter wilayah), peta mengeksekusi `fitBbox(k.bbox, 14, 500, snap)` menggunakan koordinat batas wilayah 4-titik presisi `[minLon, minLat, maxLon, maxLat]` yang dikalkulasi langsung dari poligon PostGIS `bataswilayah_kecamatan` (`ST_Extent(geom)`).
   - **Peniadaan Pin Marker Merah (*Zero Point Pin Marker*)**: Pemilihan wilayah kecamatan adalah aksi inspeksi area/teritorial (*territorial framing*), bukan penandaan titik tunggal/fasilitas spesifik. Sistem secara definitif meniadakan dan membersihkan marker pin merah (`clickedCoordinate = null`) dan efek pulse marker (`dismissPulse()`).
   - **Kompensasi Viewport Lembar Bawah Mobile (*Mobile Bottom Sheet Viewport Clearance*)**: Di perangkat ponsel, algoritma `fitBbox` secara otomatis memperhitungkan elevasi lembar bawah pada snap `peek` (50% vh) dengan padding bawah dinamis (`bottom: Math.round(window.innerHeight * 0.50) + 28px`), memastikan seluruh batas poligon kecamatan terbingkai utuh dan nyaman dipandang pada paruh atas kanvas peta terbuka tanpa tertutup lembar bawah.

---

### 4.2. Sub-Komponen 2: `SearchItemAboutView.vue` (Sub-Halaman Tentang Item Pencarian)
Sub-halaman detail ketika salah satu item hasil pencarian diklik dengan format **Tata Letak Mulus Bebas Border Kartu (*Seamless Border-Free Layout*)**:
1. **Hero Header Section**: Avatar lingkaran (`size-11 rounded-full`) berisi kode wilayah (contoh: `BJN`), protokol dataset (`WMS`, `MVT`), atau kode pilar geosite (contoh: `G-01`, `B-01`, `C-01`), judul bold besar, subjudul kontekstual, dan tombol circle x close di kanan atas yang mengembalikan pengguna ke `SearchListView`.
2. **Alur Konten Langsung di Atas Permukaan Panel (*In-Surface Content Flow*)**:
   - *Kasus Wilayah*: Status filter & zona administrasi, ringkasan karakteristik & potensi, serta koordinat numerik dengan tombol salin dalam wadah lembut tanpa border.
   - *Kasus Dataset*: Status keterpautan di peta & badge protokol, deskripsi cakupan, metadata teknis (OPD, layer name, SRS, KUGI, simpul JIGN) dalam panel lembut tanpa garis pemisah, dan simbol legenda resmi.
   - *Kasus Geosite Geopark*: Banner foto resmi geosite, badge pilar keragaman, narasi signifikansi objek, serta titik koordinat geografis presisi dengan tombol salin (format sans-serif murni dengan tabular-nums, bebas font-mono).
3. **Klaster Tombol Aksi Leluasa (*Freestanding Action Buttons*)**: Ditempatkan langsung di bawah konten tanpa garis pembungkus (Pusatkan Peta, Tambahkan / Hapus Layer dari peta, dan Tombol Kembali ke Daftar Pencarian). Menutup halaman detail ini (baik melalui tombol circle X atas maupun tombol kembali di bawah) akan mengembalikan pengguna ke state rute SearchListView dengan query pencarian tetap terjaga.

---

### 4.3. Sub-Komponen 3: `ActiveLayersView.vue` (Daftar Layer Aktif & Eksplorasi)
Tampilan Sub-View 1 Tab Peta:
1. **Sinkronisasi Pencarian Universal (*Live Active Layer Filtering*)**: Filter reaktif daftar layer aktif.
2. **Banner Titik Aktif**: Tombol pintas `"Lihat Fitur"` jika terdapat koordinat pin yang dipilih sebelumnya (`clickedCoordinate !== null`).
3. **Kartu Layer Aktif**:
   - Nomor urutan tumpukan (*stack order badge*) `#1`, `#2`, dst.
   - *Drag handle grip* untuk mengubah urutan layer dengan animasi FLIP yang halus.
   - Dot indikator status visibilitas warna biru.
   - Tombol cepat mata (*Quick Eye Toggle*) untuk menyembunyikan/menampilkan layer tanpa harus masuk ke detail.
   - Mengklik badan kartu memicu navigasi ke `layer-detail`.
4. **Kartu Pintasan Tematik & Eksplorasi Spasial**:
   - **Katalog Dataset**: Kartu akses cepat menuju katalog 50+ layer resmi Pemkab Bojonegoro pada Tab Dataset dengan aksen gradasi biru indigo lembut dan badge "Resmi".
   - **Kategori Geopark Bojonegoro Per Baris Berlatar Foto Asli (*Stacked Photo-Backed Category Rows*)**:
     - Format tata letak per baris (*stacked rows* `space-y-2.5`) menggantikan layout kolom sempit sebelumnya, menghadirkan bidang kartu yang luas, imersif, dan nyaman diklik (min-h: 76px).
     - **Foto Latar Belakang Perwakilan Geosite**: Setiap pilar menampilkan foto aktual situs geosite unggulan secara dinamis (contoh: *Kayangan Api* untuk Geologi, *Perkebunan Belimbing Ngringinrejo* untuk Hayati, dan *Kampung Samin Margomulyo* untuk Budaya) dengan efek *zoom-on-hover* (`scale-105 duration-500`).
     - **Dark Gradient Scrim & Keterbacaan Kontras Tinggi**: Dilapisi scrim gradasi gelap bertingkat (`from-slate-950/95 via-slate-950/80 to-slate-950/45`) yang menjamin keterbacaan teks 100% tajam dan memenuhi standar WCAG AAA.
     - **Badge Pilar & Dot Bercahaya**: Badge kategori dengan warna tematik (Biru Geologi, Hijau Hayati, Amber Budaya), dot status berkedip (*pulse dot*), counter jumlah situs aktual, serta nama situs unggulan yang representatif.
     - Mengklik kartu langsung memicu navigasi ke Tab Geopark dengan filter pilar aktif seketika.
   - **Sentralisasi Tombol Bagikan Peta (*Single Share Point per Device*)**:
     - Seluruh tombol share ganda/redundant (banner promosi di bawah daftar layer aktif dan floating controls di kanvas peta) telah ditiadakan. Tombol aksi *Bagikan Peta* kini dipusatkan secara eksklusif: pada header Panel Kiri untuk Desktop, dan pada tab header lembar bawah untuk Mobile.
   - **Peniadaan Tombol Pusatkan Peta di Panel**: Tombol *"Pusatkan Peta ke Bojonegoro"* pada panel kiri dihapus secara definitif karena fungsi fitbound batas wilayah kabupaten telah tersedia secara permanen melalui tombol toggle utilitas pada sudut kiri bawah kanvas peta (`MapCanvas`).

---

### 4.4. Sub-Komponen 4: `LayerDetailView.vue` (Detail Properti & Kontrol Layer)
Tampilan Sub-View 2 Tab Peta saat kartu layer aktif diklik dengan layout Hero Header dan Card Borders:
1. **Header Hero Section**: Avatar bulat protokol layer (`WMS`/`MVT`), judul nama layer tebal, instansi pengelola, dan tombol lingkaran penutup (*circle x close button*).
2. **Card 1: Kontrol Tampilan**: Switch visibilitas on/off dan slider transparansi real-time dengan preset 25%, 50%, 75%, 100%.
3. **Card 2: Metadata & Spesifikasi Spasial**: Instansi/OPD pembina, layer identitas teknis, protokol, legenda simbol resmi, dan deskripsi.
4. **Card 3: Filter Atribut Per Layer**: Pilihan filter kecamatan untuk segmen data tematik.
5. **Card 4: Aksi Navigasi Spasial**: Tombol `"Zoom ke Cakupan Layer"` dan `"Hapus dari Peta"`.

---

### 4.5. Sub-Komponen 5: `IdentifyView.vue` (Identifikasi Fitur & Koordinat Spasial)
Tampilan hasil inspeksi saat pengguna mengetuk atau mengklik titik pada peta dengan arsitektur UI/UX selaras Detail About View (`SearchItemAboutView.vue`):
1. **Prioritas & Ketahanan Rute (*Route State Resilience*)**: Saat pengguna mengklik kanvas peta (`handleMapClick`), sub-view `identify` dipicu secara reaktif dan otomatis menutup sub-halaman detail pencarian (`SearchItemAboutView`) yang sedang aktif. Watcher `inspectorOpen` dan `clickedCoordinate` secara tegas mereset `isSearchOpen = false`, `searchSubView = 'list'`, dan memindahkan fokus tampilan ke sub-view `identify`.
2. **Header Hero Section Terpadu (*Unified Hero Header Bar*)**:
   - **Avatar Lingkaran (*Circular Badge*)**: Avatar `size-11 rounded-full bg-blue-600 dark:bg-blue-500` dengan ikon status dinamis (`i-lucide-loader-2` saat memuat, `i-lucide-map-pin` saat fitur terdeteksi, atau `i-lucide-crosshair` saat inspeksi koordinat).
   - **Judul & Subjudul (*Bold Title & Context Subtitle*)**: Judul tebal berukuran `text-base sm:text-lg font-bold` menampilkan nama fitur WMS/wilayah yang terdeteksi, dan subjudul kontekstual menampilkan nama layer/instansi atau koordinat WGS 84.
   - **Tombol Aksi Cepat**: Tombol `Bersihkan Pin` (`i-lucide-map-pin-off`) dengan target sentuh ergonomis >= 44x44px dan tombol lingkaran penutup (*circle x close button*) di kanan atas untuk menutup inspeksi dan kembali ke daftar layer aktif.
3. **Penyelarasan Lembar Bawah Mobile (*Mobile Bottom Sheet Auto-Peek*)**:
   - Saat pengguna mengetuk area peta di perangkat mobile, status lembar bawah secara otomatis dinaikkan ke tingkat snap `peek` (50% vh / separuh layar) sehingga atribut fitur yang teridentifikasi dan detail koordinat langsung tampak di hadapan pengguna tanpa perlu digeser manual.
   - Header lembar bawah mobile menyelaraskan judul menjadi `"Identifikasi Fitur"` dengan ikon `i-lucide-crosshair` dan subjudul koordinat presisi.
4. **Integrasi Komponen [BottomLocationBar.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/BottomLocationBar.vue)**: Disematkan secara tertanam (`:is-mobile-drawer="true"`) dengan tab segmented control radius 100% (*pure pill*) berlabel persis `Atribut Fitur` | `Info Lokasi` (bebas dari badge/counter angka, murni tipografi sans-serif proporsional `tabular-nums font-medium`, dilarang menggunakan font mono).
5. **Standar Fitbound Responsif & Bebas Halangan (*Device-Aware Viewport Padding & Feature FitBound*)**:
   - **Eliminasi Pergeseran Kamera Ganda (*Single Decisive FitBound - Zero Camera Jitter*)**: Peta meniadakan animasi kamera prematur pada gestur ketuk awal `handleMapClick`. Transisi kamera dieksekusi tepat SATU KALI secara definitif setelah kueri identifikasi fitur selesai (`handleIdentifyFeatures`). Hal ini mengeliminasi efek kamera melompat/bergeser dua kali (*double pan jitter*) dan menjaga kenyamanan spasial pengguna.
   - **Prioritas Mutlak Layer Teratas & Layer Aktif (*Top of Stack Priority*)**: Fitur diurutkan secara tegas berdasarkan indeks tumpukan layer aktif (`layerIndex` / `zIndex` tertinggi pada posisi paling atas). Garis trase ruas jalan dan titik fasilitas diprioritaskan di atas poligon wilayah, menjamin fitbound selalu membidik objek tematik utama yang tampak di permukaan.
   - **Style Seleksi Fitur Trayek UI Modern (*Modern Transit Route Selection Style*)**: Indikator penanda objek terpilih murni dan sepenuhnya berdasarkan Style Selected Feature tanpa popup koordinat dan tanpa pulse marker. Warna rute terpilih dinamis berdasarkan warna kondisi jalan (Rusak Berat: Merah, Rusak Ringan/Rusak: Oranye, Sedang/Cukup: Amber/Kuning, Baik: Hijau Emerald, Default/Netral: Sky/Cyan). Visual rute dirancang dengan arsitektur multi-layer kontemporer: underlay shadow glow berpendar 14px yang warnanya selaras dengan kondisi, casing halo kontras 8.5px (gelap/putih), core route solid 5px, serta garis solid poros tengah 1.75px untuk kedalaman gloss 3D murni tanpa pola garis putus-putus. Untuk poligon dan titik, seluruh batas perimeter dan nodus juga disajikan secara solid mengikuti warna kondisi. Highlight berpindah secara interaktif saat fitur lain di daftar multi-fitur dipilih, dan segera dibersihkan saat inspeksi ditutup.
   - **Penyesuaian Fitbound Berdasarkan Area Kanvas Peta Terbuka (*Open Map Canvas Viewport Clearance*)**: Di layar ponsel, ketika lembar bawah berada pada posisi `peek` (separuh layar / 50% vh) baik saat pencarian maupun saat inspeksi fitur, kamera peta secara otomatis mengaplikasikan *viewport padding* bawah dinamis (`bottom: Math.round(window.innerHeight * 0.50) + 28px`, `top: 80px`, `left: 24px`, `right: 24px`). Algoritma `fitCoordinateWithPadding`, `fitFeatureExtent`, `fitBbox`, dan `flyTo` memfokuskan titik koordinat maupun cakupan geometri tepat di tengah area kanvas peta yang sedang terbuka (*optical center* paruh atas layar), menjamin objek spasial, marker api geopark, label holo, maupun trayek jalan berada di area pandang terbuka tanpa tertutup lembar bawah.
   - **FitBound Geometri Penuh Ruas & Poligon (*Full Extent Feature Framing*)**: Saat objek teridentifikasi berupa garis (ruas/segmen jalan MVT) atau poligon (kecamatan/zona), sistem mengekstrak `extent` 4-elemen EPSG:4326 dan menjalankan `fitFeatureExtent(extent, 16.5, 550)`. Fitur dibingkai secara utuh dari ujung ke ujung pada batas *zoom* ideal tanpa terpotong batas layar.
   - **Keseimbangan Desktop & Tablet (*Desktop Control Cushioning*)**: Di desktop dan tablet, sistem memperhitungkan kontrol mengambang dengan padding `[80, 64, 52, 48]` sehingga objek berada tepat di tengah area kanvas tanpa bertubrukan dengan *cluster* kontrol kanan atas atau panel kiri *in-flow*.

---

### 4.6. Sub-Komponen 6: `GeoparkListView.vue` (Katalog Geosite Geopark Bojonegoro)
Tampilan Sub-View 1 Tab Geopark yang terintegrasi langsung dengan API resmi Geopark Bojonegoro:
1. **Pencarian Universal Terpadu**: Menggunakan bilah pencarian universal di bagian atas panel kiri yang seragam di semua Tab Menu (Peta, Dataset, Geopark). Kartu katalog hero dan kotak pencarian mandiri ditiadakan agar daftar situs langsung tampak ringkas tanpa memakan ruang vertikal.
2. **Filter Tiga Pilar Keragaman (Segmented Pill 100% Radius, Bebas Angka Badge)**:
   - *Semua*
   - *Geologi*: Antiklin Kawengan, Kayangan Api, Kedung Lantung, Watu Gandul, dsb.
   - *Hayati*: Belimbing Ngringinrejo, Rusa Malo, Hutan Jati Gondang.
   - *Budaya*: Kampung Samin, Makam Wali Kidangan, Petilasan Angling Dharma, serta Warisan Takbenda (Wayang Thengul, Tari Tayub).
   Tombol pilar dirancang murni teks tanpa badge angka counter sesuai standar minimalis WebGIS.
3. **Daftar Kartu Geosite Interaktif (Bebas Duplikasi Badge Nama)**:
   - Dilengkapi foto thumbnail resmi dengan fallback kode unik pilar (`site.code`, misal `G-01`).
   - Badge pilar keragaman tunggal (`site.category`: Keragaman Geologi, Keragaman Hayati, Keragaman Budaya) berpalet warna khusus.
   - Peniadaan badge nama geosite redundan (`site.tag` ditiadakan) untuk mencegah duplikasi visual dengan judul geosite (`site.title`).
   - Teks deskripsi ringkas 2 baris (`line-clamp-2`), koordinat lintang/bujur presisi tabular, tombol `"Detail"` untuk membuka ulasan lengkap, dan tombol `"Peta"` (min-h-[44px]) untuk memusatkan kamera kanvas peta.
4. **Direct Navigation ke Detail**: Mengklik badan kartu geosite membuka tampilan `GeoparkDetailView`.

---

### 4.7. Sub-Komponen 7: `GeoparkDetailView.vue` (Detail Lengkap Geosite Geopark)
Tampilan Sub-View 2 Tab Geopark ketika pengguna memilih salah satu geosite:
1. **Header Hero Section (Selaras dengan SearchItemAboutView & LayerDetailView)**:
   - **Avatar Bulat**: Avatar lingkaran penuh (`size-11 rounded-full`) dengan kode unik situs (`G-01`, `H-01`, `I-01`) dan warna latar sesuai pilar keragaman (biru untuk Geologi, hijau untuk Hayati, amber untuk Budaya).
   - **Judul & Sub-Judul**: Nama situs tebal bersanding dengan sub-judul kategori pilar dan wilayah Bojonegoro (`{{ site.category }} · Bojonegoro`), bebas dari duplikasi nama situs.
   - **Circle X Close Button**: Tombol lingkaran (`size-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20`) dengan ikon silang di sudut kanan atas. Menutup detail ini secara konsisten mengembalikan pengguna ke state rute `GeoparkListView` (`geoparkSubView = 'list'`) tanpa mereset query pencarian atau pilihan filter.
2. **Hero Banner & Galeri Foto Lengkap**:
   - Foto utama resolusi tinggi dengan badge pilar keragaman dan kode unik situs (`G-01`, `H-01`, `I-01`).
   - Galeri foto pendukung (`gambarPendukungUrl` dari API resmi) disajikan dalam carousel thumbnail interaktif dengan modal preview ukuran penuh (*lightbox viewer*).
3. **Identitas & Narasi Ilmiah Resmi**:
   - Tagar geologi/budaya resmi dan nomor urut situs.
   - Judul lengkap dan deskripsi ringkas.
   - Narasi ilmiah lengkap (`narasi_id` format HTML resmi) dirender secara rapi dengan styling tipografi sans-serif proporsional (bebas font-mono).
4. **Koordinat Geografis & Integrasi Peta**:
   - Menampilkan koordinat Lintang dan Bujur dalam format `tabular-nums font-semibold` (bebas font-mono) dengan tombol salin interaktif.
   - Tombol aksi utama `"Pusatkan Peta ke Geosite Ini"` (min-h-[44px]) yang langsung mengarahkan kanvas peta ke titik koordinat situs.
   - Untuk warisan budaya takbenda, disajikan banner informasi khusus yang menjelaskan sifat takbenda dari objek tradisi tersebut.
5. **Tautan Resmi Pemerintah**: Tautan langsung ke portal resmi `https://geopark.bojonegorokab.go.id`.

---

### 4.8. Arsitektur Direct-Hit Endpoint Geopark Bojonegoro
1. **Kendala Browser Direct Fetch**: Endpoint resmi `https://geopark.bojonegorokab.go.id/api/diversity/list-diversity-site-list` menyetel header `Access-Control-Allow-Origin: http://localhost:3000`. Jika dipanggil langsung dari peramban pada port lain (seperti 4000) atau domain produksi, browser secara otomatis memblokir request karena pelanggaran CORS, ditambah verifikasi sertifikat SSL server pemerintah yang ditolak oleh CA bundle bawaan Node.js pada mode SSR.
2. **Solusi Direct-Hit Gateway**: Melarosa mengimplementasikan rute gateway resmi `GET /api/v1/geopark/sites` di backend Laravel (menggunakan `Http::withoutVerifying()`) dengan cache cerdas 15 menit dan on-demand refresh. Nuxt WebGIS memanggil endpoint ini secara dinamis melalui `$http('geopark/sites')` tanpa perlu menyimpan ratusan baris data statis di berkas frontend.

---

### 4.9. Arsitektur Sub-View Pencarian Universal & `SearchItemAboutView.vue`
Pencarian universal di bilah atas Panel Kiri mendukung navigasi terpadu:
1. **Pemisahan Watcher Inspektur vs Koordinat**:
   - Watcher `inspectorOpen` di `LeftPanel.vue` hanya bereaksi terhadap pembukaan eksplisit inspektur fitur (`props.inspectorOpen`), bukan perubahan nilai koordinat (`props.clickedCoordinate`). Hal ini memastikan pemilihan item pencarian (Kecamatan atau Geosite) yang memusatkan kanvas peta tidak menutup paksa sub-view `SearchItemAboutView`.
2. **Dukungan Tiga Kategori Utama**:
   - **Wilayah & Kecamatan**: Menampilkan nama kecamatan, zona, potensi wilayah, titik koordinat WGS 84, serta tombol "Pusatkan Peta ke Wilayah Ini".
   - **Dataset & Layer Spasial**: Menampilkan judul layer, protokol spasial (MVT/WMS/WFS/GEO), status keaktifan di peta, serta tombol aksi tambah atau pusatkan cakupan.
   - **Geosite & Geopark Bojonegoro**: Menampilkan foto resmi situs, kode unik, pilar keragaman, deskripsi signifikansi, titik koordinat geografis dengan tombol salin, tombol "Pusatkan Peta ke Geosite Ini", dan tombol langsung "Buka Ulasan Lengkap di Tab Geopark".
3. **Ketahanan Null-Safety & Perpindahan Instan Antar-View (*Zero Transition Delay / Instant View Switching*)**:
   - Komponen menerapkan computed getters (`itemType`, `itemData`, `kecamatanCoords`, `geoparkCoords`) dan root guard `v-if` yang kokoh terhadap akses properti.
   - Seluruh perpindahan view dan sub-view (Tab Peta, Dataset, Geopark, SearchListView, SearchItemAboutView, LayerDetailView, IdentifyView, GeoparkDetailView) dirancang tanpa wrapper `<Transition>` dan tanpa jeda animasi `mode="out-in"`. Konten berpindah seketika (*instant zero-delay rendering*) untuk performa antarmuka yang sangat responsif, tangkas, dan efisien.
4. **Respon Lembar Bawah Mobile Separuh Layar & Fitbound Terbuka (*Mobile Half-Sheet & Open-Canvas FitBound*)**:
   - Ketika pengguna mengetik atau memfokuskan form pencarian apapun pada perangkat mobile, lembar bawah secara otomatis membuka atau bertengger pada snap `peek` (separuh layar / 50% vh).
   - Pemilihan hasil pencarian (Kecamatan, Dataset, atau Geosite Geopark) mengeksekusi fitbound dengan padding bawah dinamis setinggi lembar bawah (`bottom: Math.round(window.innerHeight * 0.50) + 28px`), sehingga objek spasial langsung terpusat di area kanvas peta yang sedang terbuka di paruh atas layar tanpa terhalang sedikit pun.

---

### 4.10. Klaster Kontrol Kanvas Peta & Kompas Arah Mata Angin (`MapControls.vue`)
Arsitektur kontrol mengambang pada kanvas peta mengadopsi prinsip *Harmonious Split & Utility Grouping*:
1. **Klaster Tampilan Kiri Bawah (*Display & Layer Group*)**:
   - **Susunan Vertikal Terpadu (*Vertical Utility Stack*)**: Disusun bertumpuk secara vertikal (`flex flex-col items-start gap-2`) di sudut kiri bawah kanvas peta (`left-3 sm:left-4`). Desain vertikal ini mencegah tabrakan visual dan tumpang tindih dengan drawer basemap desktop yang membuka ke arah kanan (`left-full`).
   - **Tombol Fitbound Default Batas Wilayah Kabupaten Bojonegoro**: Diletakkan pada puncak tumpukan utilitas (*top-most action*). Tombol squircle berukuran `w-12 sm:w-14 h-11 sm:h-12` (memenuhi standar sentuh ergonomis >= 44x44px) dengan ikon `i-lucide-maximize`. Mengklik tombol ini mereset filter kecamatan aktif (`handleFilterWilayah(null)`) dan menjalankan transisi kamera spasial halus (`mapCanvasRef.value?.fitBbox(MARTIN_SPATIAL_BBOX, 15, 550)`) dengan padding adaptif perangkat, membingkai seluruh cakupan geografis Kabupaten Bojonegoro secara utuh dan terpusat.
   - **Tombol Toggle Grid Kartografi & Koordinat Tepi**: Diletakkan tepat **di atas toggle basemap** (bagian bawah dari stack tombol utilitas). Tombol dengan ikon `i-lucide-grid-3x3` dan state visual aktif (`aria-pressed="true"`, background biru lembut `bg-blue-50/70 dark:bg-blue-950/50` dengan teks `text-blue-600 dark:text-blue-400`) untuk menyalakan atau mematikan graticule garis koordinat lintang/bujur dan label derajat-menit-detik kartografis pada tepi kanvas peta.
   - **BasemapPanel**: Diletakkan di dasar tumpukan (*bottom anchor*). Tombol thumbnail squircle berukuran `size-12 sm:size-14` (`rounded-xl sm:rounded-2xl`) yang selaras presisi dengan stack tombol utilitas di atasnya, dilengkapi pratinjau basemap aktif dan drawer pemilihan basemap horizontal di desktop atau bottom sheet di mobile.
   - **Kompensasi Dinamis Mobile**: Seluruh klaster utilitas kiri bawah bergerak sinkron ke atas secara dinamis mengikuti tingkat snap lembar bawah mobile (`calc(snap + 16px)`) sehingga tidak tertutup oleh bottom sheet drawer.
2. **Klaster Navigasi Kanan Bawah (*Navigation Group*)**:
   - **Tombol Arah Mata Angin (North Arrow Dial & Reset Utara)**:
     - Dilengkapi jarum kompas vektor dua arah (ujung Utara berwarna merah terang `#ef4444` dan ujung Selatan slate netral).
     - Jarum berputar dinamis mengikuti rotasi kanvas peta (`transform: rotate(${deg}deg)`).
     - Menampilkan badge sudut rotasi numerik saat peta berada dalam keadaan miring (`|deg| > 0.5°`).
     - Berfungsi sebagai tombol reset orientasi: mengklik tombol memicu animasi kembali tegak lurus ke Utara bumi (`view.animate({ rotation: 0, duration: 350 })`).
   - **Tombol Geolocation GPS**: Penguncian posisi real-time dengan pill terintegrasi kecepatan berkendara (km/j), heading arah pandang, dan akurasi GPS.
   - **Tombol Zoom In / Zoom Out**: Pengatur perbesaran skala kanvas.
   - **Peniadaan Duplikasi Tombol Share**: Tombol Share dihapus dari kanvas mengambang karena telah tersedia di Footer Panel Kiri dan Mobile Drawer.

---

### 4.11. Keutuhan Geometri MVT Lintas Batas Tile & Reticle Radius Titik WMS
1. **Penyatuan Pecahan Vektor Tile MVT (*Cross-Tile Fragment Stitching*)**:
   - Geometri MVT (seperti jalan poros desa atau segmen infrastruktur) yang terpotong oleh sistem partisi batas tile OpenLayers dikumpulkan secara komprehensif (`collectAllMvtFeatureFragments`) berdasarkan identifier unik (`id`, `id_segmen`, `kode_ruas`, `gid`, `fid`, dsb.).
   - Pecahan-pecahan garis disambungkan secara topologis (`stitchFeatureGeometries`) dengan toleransi sambungan koordinat titik temu 10 meter sehingga membentuk satu kesatuan `LineString` atau `MultiLineString` yang utuh tanpa jeda visual.
   - Dilengkapi *listener* pemuatan tile dinamis (`source.on("tileloadend")`), sehingga saat pengguna melakukan *pan* atau *zoom* dan tile baru masuk ke memori peramban, bagian ruas jalan yang baru termuat otomatis disambungkan ke highlight seleksi.
   - Cakupan *extent* dihitung dari geometri hasil penyatuan penuh, menjamin navigasi kamera *fitbound* mencakup keseluruhan panjang objek spasial tanpa terpotong.
2. **Reticle Garis Radius Luar untuk Fitur Titik WMS (*Outer Radius Reticle for WMS Point Features*)**:
   - Khusus fitur titik (Point) yang teridentifikasi, style seleksi dilengkapi dengan **lingkaran garis radius luar** (`radius: 30px` / diameter 60px) dengan stroke solid warna kondisi (`theme.core`, tebal 2.25px), casing halo kontras (`width: 5px`), dan soft radar fill transparan (`rgba(37, 99, 235, 0.10)`).
   - Garis radius ini melingkari titik WMS di sisi luar sehingga visual simbol atau ikon asli dari layer raster WMS tetap terlihat jelas dan tidak tertutup, sekaligus memberikan penanda target reticle yang presisi dan elegan.

---

### 4.12. Pembatasan Skala Maksimal Fitbound (Maksimal Skala 1:5.000)
1. **Formula Pembatas Resolusi Berdasarkan Skala Kartografis**:
   - Seluruh fungsi penyesuaian kamera spasial (`fitFeatureExtent`, `fitCoordinateWithPadding`, `fitBbox`) dibatasi secara ketat oleh resolusi minimum pada skala 1:5.000:
     $$\text{minResolution} = \frac{5000}{\cos(\text{lat}) \times 3779.527559}$$
   - Pada wilayah Kabupaten Bojonegoro (lintang rata-rata $\approx -7.15^\circ$), batas resolusi ini bersesuaian dengan $\approx 1.33$ meter/piksel atau tingkat zoom $\approx 16.8$.
2. **Eliminasi Zoom-In Berlebih (*Over-Zoom Prevention*)**:
   - Saat pengguna memilih fitur berukuran kecil, segmen pendek, atau titik lokasi, kamera peta tidak akan pernah melakukan zoom in melewati skala 1:5.000.
   - Menjamin pengguna tetap mempertahankan konteks lingkungan spasial di sekitar objek tanpa mengalami disorientasi visual akibat skala peta yang terlalu dekat (seperti 1:1.000 atau zoom 19/20).

---

### 4.13. Normalisasi Sumbu Koordinat & Bbox Sumber Data WMS (GeoServer WMS 1.3.0 Axis Order Normalization)
1. **Akar Masalah Koordinat Terbalik pada WMS**:
   - Sesuai spesifikasi OGC WMS 1.3.0, CRS `EPSG:4326` mendefinisikan urutan sumbu sebagai **Latitude dahulu (Y), baru Longitude (X)**. Sementara spesifikasi GeoJSON (RFC 7946) mewajibkan urutan **Longitude dahulu (X), baru Latitude (Y)**.
   - Saat GeoServer merespons query `GetFeatureInfo` (format `application/json`), objek `f.geometry` mematuhi GeoJSON standar (`[lon, lat]`), sehingga fitur berhasil dirender dengan tepat pada map canvas. Namun atribut `f.bbox` mewarisi urutan sumbu WMS 1.3.0 berupa `[minLat, minLon, maxLat, maxLon]` (atau `[minY, minX, maxY, maxX]`).
   - Akibatnya, jika `f.bbox` langsung disuplai ke `fitFeatureExtent` atau `fitBbox`, OpenLayers mengartikan nilai Lintang (misal `-7.15`) sebagai Bujur, dan nilai Bujur (misal `111.89`) sebagai Lintang (yang melebihi batas legal $\pm 90^\circ$), sehingga kamera peta terlempar ke lokasi salah (seperti samudra atlantis atau kutub).
2. **Solusi Komprehensif di `MapCanvas.vue`**:
   - **Prioritas Geometri GeoJSON (`GeoJSON.readGeometry`)**: Menghitung cakupan *extent* fitur langsung dari objek `f.geometry` yang diuraikan oleh pembaca GeoJSON OpenLayers (`olGeomInstance.getExtent()`), lalu mentransformasikannya dari `EPSG:3857` ke `EPSG:4326`. Hal ini menjamin nilai extent selalu tepat berorientasi `[minLon, minLat, maxLon, maxLat]`.
   - **Koordinat Titik Presisi WMS Point**: Untuk fitur titik, koordinat fitur diambil langsung dari `f.geometry.coordinates` (`[lon, lat]`), memastikan fokus kamera dan reticle lingkaran luar tepat berada di titik inspeksi.
   - **Fungsi Normalisasi Bbox (`normalizeBbox4326`)**: Mendeteksi otomatis jika koordinat terbalik (elemen pertama/ketiga $\le 90^\circ$ dan elemen kedua/keempat $> 90^\circ$, khas wilayah Indonesia dan WMS 1.3.0) serta membalikkan urutan ke `[minLon, minLat, maxLon, maxLat]`.
   - **Koreksi Defensif Kamera Spasial**: `fitFeatureExtent`, `fitCoordinateWithPadding`, `fitBbox`, `flyTo`, dan `setCenterAndZoom` dilengkapi sanitasi defensif otomatis terhadap kemungkinan koordinat terbalik sebelum diproses ke view OpenLayers.

---

### 4.14. Pemisahan Tipe Marker Kanvas Peta & Solusi UI/UX Marker Geopark
1. **Marker Geopark (Status Resting, Animasi Loncat-Loncat Selektif & Label Holo Minimalis)**:
   - **Status Standar Tenang (*Resting State*)**: Seluruh marker situs Geopark pada kanvas peta secara default berada dalam posisi diam (*static resting state*). Hal ini mencegah kelelahan visual akibat puluhan marker bergerak serempak dan mematuhi prinsip *intentional motion* (R-19).
   - **Animasi Loncat-Loncat Selektif (*Targeted Hop on Click / Selection*)**: Animasi loncat-loncat (`@keyframes mr-fire-hop`) HANYA diaktifkan secara selektif pada marker situs yang sedang diklik oleh pengguna pada kanvas peta atau dipilih dari kartu daftar Geopark (`GeoparkListView` atau `SearchItemAboutView`).
   - **Label Nama dengan Efek Holo Minimalis (*Minimalist Holographic Halo Pill*)**:
     - Saat marker Geopark diklik/terpilih, muncul label melayang (*floating pill*) tepat di atas puncak api marker.
     - Memuat kode pilar geosite (misal `G-01`) dan nama lengkap geosite dengan tipografi bersih `text-xs font-semibold` berkontras tinggi (memenuhi standar WCAG AA).
     - Dikelilingi efek **Holo di luarnya** (*holographic halo border & outer glow*): border halus bertema amber/oranye (`border: 1px solid rgba(249, 115, 22, 0.5)`) dipadu soft ambient glow (`box-shadow: 0 0 16px 2px rgba(249, 115, 22, 0.32)`), berlatar belakang kaca buram minimalis (*frosted glass* `backdrop-blur-md bg-white/94 dark:bg-slate-900/94`) dan panah penunjuk (*caret*) di sisi bawah.
     - Label bersifat interaktif (dapat diklik) dan otomatis ditutup saat pengguna menutup panel ulasan detail Geopark atau beralih ke fitur lain.
   - **Fitbound Kamera Spasial Otomatis (*Device-Aware FitBound on Marker Click*)**:
     - Saat marker api atau label Geopark diklik pada kanvas peta, kamera secara otomatis mengeksekusi transisi kamera spasial halus (`fitCoordinateWithPadding`) menuju titik koordinat situs dengan perbesaran ideal (`zoom: site.zoom || 15`, dibatasi skala kartografis maksimal 1:5.000).
     - Mengaplikasikan kompensasi *padding* adaptif (`snapOverride: "peek"` di mobile): menjamin marker, animasi lompat api, dan label holo bertengger presisi pada *optical center* paruh atas layar ponsel bebas halangan (*unobscured upper viewport*), tidak tertutup oleh lembar bawah (*bottom sheet*), serta seimbang di area terbuka desktop.
   - **Standar Aksesibilitas**: Ukuran target interaksi tombol memenuhi standar ergonomi sentuh minimum 44x44px dengan ring fokus keyboard kontras tinggi (`outline: 2px solid white; box-shadow: 0 0 0 4px #1d4ed8`), atribut `aria-label` bahasa Indonesia, serta dukungan penuh `prefers-reduced-motion: reduce` (animasi melompat berhenti bila pengguna mengaktifkan mode hemat gerak).
2. **Marker Standar Pin Ukuran Diperkecil (Kecuali Geopark)**:
   - Digunakan untuk seluruh entitas spasial non-Geopark: hasil GetFeatureInfo WMS, titik hasil pencarian lokasi/ruas jalan, inspeksi koordinat kanvas peta, dan penanda wilayah.
   - Ukuran penanda diperkecil secara proporsional dari ukuran awal (32x44px) menjadi **20px x 28px** agar ramping, tajam, presisi, dan tidak menutupi informasi label serta ruas jalan di kanvas peta.
   - Menggunakan SVG pin standar warna merah `#dc2626` dengan casing putih 1.8px dan titik pusat putih, diposisikan tepat pada ujung bawah pin (`bottom-center`).
   - Dilengkapi animasi drop halus 240ms saat pertama kali muncul (`mr-pin-drop`), dengan properti `pointer-events: none` agar klik pengguna tetap dapat menembus ke fitur peta di bawahnya.
   - Otomatis disembunyikan/ditiadakan saat beralih ke mode Geopark agar tidak terjadi penumpukan atau kerancuan tipe marker.

---


## 5. Token Desain: Warna, Tipografi & Kontras

### A. Palet Warna Permukaan & Garis Batas

| Token | Mode Terang (*Light Mode*) | Mode Gelap (*Dark Mode*) | Kegunaan |
|---|---|---|---|
| `surface-panel` | `#ffffff` (White) | `#0b1322` / `#09111e` | Latar belakang utama sidebar |
| `surface-card` | `#ffffff` | `#0c1424` | Kartu layer, accordion wilayah, dialog |
| `surface-muted` | `#f8fafc` (Slate 50) | `rgba(255,255,255,0.02)` | Kontainer input, item nonaktif |
| `surface-tab-rail` | `rgba(226,232,240,0.6)` | `rgba(30,41,59,0.6)` | Latar segmented control |
| `border-subtle` | `rgba(226,232,240,0.8)` | `rgba(255,255,255,0.08)` | Garis pemisah kartu & baris |
| `text-primary` | `#0f172a` (Slate 900) | `#f8fafc` (Slate 100) | Judul layer, nama objek, teks utama |
### 4.8. Sub-Komponen 8: `MapThumbnailCard.vue` (Thumbnail Peta Dinamis Rasio 16:9)
Sistem penampil pratinjau kartografis dinamis ber-rasio 16:9 pada Panel Kiri dan modal ekspor:
1. **Rasio Aspek 16:9 Murni (*Widescreen Framing*)**: Menghasilkan thumbnail kartografis dengan rasio 16:9 (640x360 atau 1280x720) yang dipotong secara terpusat (*center crop*) dari kanvas peta aktif, menjamin proporsi visual ideal untuk preview, metadata OpenGraph, dan kartu sosial.
2. **Kesesuaian dengan Seluruh Tipe Layer Spasial**: Kompatibel penuh merender tumpukan kanvas OpenLayers yang memuat:
   - *Basemap*: Citra satelit, peta jalan OSM, Esri Topo, RBI BIG, maupun mode transparan.
   - *Layer Vektor Tiles (MVT)*: Garis kondisi jalan dan batas wilayah dari server Martin PostGIS.
   - *Layer WMS/TileWMS*: Layer tematik bersumber dari GeoServer Simpul JIGN Geoportal Bojonegoro.
   - *Vector Layers*: Poligon sorotan wilayah, titik geosite geopark, dan marker seleksi fitur.
3. **Optimasi Performa Tinggi (Debounce & In-Memory LRU Cache)**:
   - **Debounce**: Eksekusi pengambilan gambar ditunda dengan jeda 380-400ms setelah interaksi pengguna (panning, zoom, atau perubahan filter) berhenti, mencegah beban GPU/CPU berlebih.
   - **In-Memory LRU Cache (Maks 30 Entri)**: Setiap hasil render disimpan dalam cache memori dengan kunci deterministik `basemap|zoom|center|extent|wilayah|layers|dims`. Navigasi kembali ke posisi sebelumnya mengembalikan gambar secara instan (0ms).
4. **Resiliensi CORS & Mekanisme Fallback Kartografis (*Tainted Canvas Protection*)**:
   - Memeriksa setiap elemen kanvas lapisan (`isCanvasTainted`). Lapisan eksternal yang tidak memiliki header CORS diisolasi sehingga tidak mengontaminasi kanvas komposit master.
   - Jika seluruh kanvas ter-taint atau ekspor terhambat, sistem secara otomatis mengeksekusi *fallback generator* yang merender kartu kartografis beresolusi 16:9 dengan latar belakang tema gelap, grid koordinat spasial, tanda panah utara, retikel bidik, label koordinat WGS 84, nama wilayah aktif, dan watermark resmi Melarosa.
5. **Aksi Interaktif Terpadu**:
   - Tombol Ambil Ulang Thumbnail (*Refresh* dengan micro-animation spin).
   - Tombol Salin Gambar ke Clipboard (*Direct Binary Image Copy*).
   - Tombol Unduh Berkas PNG (*Direct Download*).
   - Lightbox Dialog Preview untuk memeriksa gambar dalam ukuran besar.

---

## 5. Sistem Desain, Palet Warna & Tipografi

### A. Palet Warna Baku (Design Tokens):
| Token | Nilai Light Mode | Nilai Dark Mode | Penerapan |
|---|---|---|---|
| `surface-panel` | `#ffffff` (Putih murni) | `#09111e` (Navy gelap) | Latar belakang Panel Kiri |
| `surface-card` | `#f8fafc` (Slate 50) | `rgba(255,255,255,0.03)` | Kartu layer, info box, modal |
| `border-subtle` | `#e2e8f0` (Slate 200) | `rgba(255,255,255,0.08)` | Garis pemisah antar kartu |
| `text-primary` | `#0f172a` (Slate 900) | `#f8fafc` (Slate 50) | Judul utama, nama layer |
| `text-secondary` | `#475569` (Slate 600) | `#94a3b8` (Slate 400) | Sub-judul, status, label koordinat |
| `text-tertiary` | `#64748b` (Slate 500) | `#64748b` (Slate 500) | Metadata pendukung, atribusi |
| `accent-primary` | `#2563eb` (Blue 600) | `#3b82f6` (Blue 500) | Status aktif, tombol CTA, pin koordinat |
| `accent-success` | `#10b981` (Emerald 500) | `#10b981` (Emerald 500) | Kemantapan baik, rute aktif |
| `accent-danger` | `#ef4444` (Red 500) | `#f87171` (Red 400) | Tombol hapus layer, bersihkan pin |

### B. Standar Kontras Aksesibilitas (WCAG AA):
- Seluruh teks `text-secondary` dan `text-tertiary` wajib memiliki rasio kontras minimal **4.5:1** terhadap latar belakang (`surface-panel` maupun `surface-card`).
- Dilarang menggunakan teks abu-abu pudar di bawah batas kontras legal.

### C. Tipografi:
- Font keluarga: Outfit, Inter, sans-serif sistem.
- Larangan Font Monospace: Dilarang keras menggunakan font monospaced (`font-mono`) pada antarmuka identifikasi fitur dan info lokasi; gunakan sans-serif proporsional dengan `tabular-nums font-medium` untuk angka koordinat dan datum geospasial.
- Ukuran teks:
  - Header panel: `text-base sm:text-lg font-semibold` (16-18px).
  - Judul kartu layer: `text-xs font-semibold` (12px).
  - Teks metadata: `text-[10px]` atau `text-[11px]` (10-11px).
  - Indikator urutan layer: `text-[9px] font-semibold tabular-nums` (9px).

---

## 6. Protokol Kepatuhan Anti-Slop (Anti-Slop Compliance Matrix)

| Aturan | Standar yang Wajib Dipatuhi | Status Verifikasi |
|---|---|---|
| **R-01: Zero Em Dash** | Dilarang menggunakan karakter em dash (`-`). Gunakan `-`, `,`, `:`, atau `()`. | Wajib & Lolos Otomatis |
| **R-02: Zero Dead Buttons** | Setiap tombol, toggle mata, slider, chip filter, tombol kembali, dan tombol bersihkan wajib memiliki event handler aktif. | Wajib & Lolos Otomatis |
| **R-03: Touch Target Min 44px** | Seluruh tombol interaktif utama di layar ponsel memiliki ukuran sentuh minimal 44x44px. | Wajib & Lolos Otomatis |
| **R-04: Bebas Ikon & Badge pada Tab** | Tab Peta dan Geoportal dilarang menggunakan ikon dan dilarang menampilkan badge angka. | Wajib & Lolos Otomatis |
| **R-05: Clean Desktop Canvas** | Kanvas peta desktop bebas dari floating location bar bawah. | Wajib & Lolos Otomatis |
| **R-06: Perpindahan View Instan & Bebas Jeda (*Instant Zero-Delay Switching*)** | Perpindahan antar view dan sub-view (Tab Peta, Dataset, Geopark, detail layer, identify, rekomendasi pencarian) bersifat instan tanpa jeda animasi out-in; transisi fisik hanya digunakan untuk pergerakan overlay panel lembar bawah. | Wajib & Lolos Otomatis |

---

## 7. Referensi Berkas Sumber Kode

- Komponen Wadah Rute Panel Kiri: [LeftPanel.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/LeftPanel.vue)
- Sub-Komponen Daftar Rekomendasi Pencarian: [left-panel/SearchListView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/SearchListView.vue)
- Sub-Komponen Tentang Item Pencarian: [left-panel/SearchItemAboutView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/SearchItemAboutView.vue)
- Sub-Komponen Daftar Layer Aktif: [left-panel/ActiveLayersView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/ActiveLayersView.vue)
- Modul Thumbnail Peta Dinamis 16:9 (Fitur Share Link & OpenGraph Meta): [composables/useMapThumbnail.ts](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/composables/useMapThumbnail.ts)
- Generator Banner OpenGraph Dinamis: [server/api/og/map.get.ts](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/server/api/og/map.get.ts)
- Sub-Komponen Detail & Kontrol Layer: [left-panel/LayerDetailView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/LayerDetailView.vue)
- Sub-Komponen Identifikasi Fitur Spasial: [left-panel/IdentifyView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/IdentifyView.vue)
- Sub-Komponen Geopark Bojonegoro: [left-panel/GeoparkListView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/GeoparkListView.vue)
- Sub-Komponen Detail Geosite Geopark: [left-panel/GeoparkDetailView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/GeoparkDetailView.vue)
- Composable Geopark Bojonegoro: [composables/useGeoparkSites.ts](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/composables/useGeoparkSites.ts)
- Gateway Endpoint Geopark: `GET /api/v1/geopark/sites` di [apps/api/routes/web.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/routes/web.php)
- Halaman Peta Utama: [pages/maps/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/maps/index.vue)
- Tata Letak Aplikasi: [app.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/app.vue)
- Komponen Inspektur Fitur: [IdentifyFeatures.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/IdentifyFeatures.vue)
- Komponen Bar Lokasi Tertanam: [BottomLocationBar.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/BottomLocationBar.vue)
- Komponen Lembar Bawah Mobile: [MapMobileBottomSheet.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/MapMobileBottomSheet.vue)
- Modal Bagikan Peta Desktop: [MapShareModal.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/MapShareModal.vue)
- Lembar Bawah Bagikan Peta Mobile: [MapShareBottomSheet.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/MapShareBottomSheet.vue)

---

## 8. Paten Thumbnail Peta Dinamis 16:9 untuk Fitur Share Link & OpenGraph Meta

1. **Tujuan Eksklusif**:
   - Thumbnail peta dinamis 16:9 didedikasikan secara khusus untuk **Fitur Share Link (Bagikan Tautan Peta)** dan **Pratinjau OpenGraph / Twitter Cards**, bukan sebagai widget kartu di Tab Menu Active Layer View.
   - Tab Menu Active Layer View (`ActiveLayersView.vue`) dijaga tetap bersih, padat, dan fokus pada manajemen lapisan peta tanpa elemen thumbnail kartu yang memakan ruang vertikal.
2. **Arsitektur Share Link Antarmuka**:
   - Baik di desktop ([MapShareModal.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/MapShareModal.vue)) maupun di mobile ([MapShareBottomSheet.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/MapShareBottomSheet.vue)), dialog berbagi menyajikan kotak input URL peta lengkap beserta tombol "Salin Link" (1-klik copy ke clipboard dengan transisi umpan balik visual "Tersalin!").
   - Pratinjau langsung 16:9 dihasilkan secara *on-demand* dari kanvas peta aktif (`getMapThumbnail`) mengikuti zoom, pusat koordinat, basemap, dan layer tematik aktual.
3. **Penyedia Banner OpenGraph Server-Rendered**:
   - Rute Nitro server [server/api/og/map.get.ts](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/server/api/og/map.get.ts) melayani banner SVG 16:9 (1200x675) dengan koordinat, nama wilayah, dan datum kartografis untuk perayap media sosial (WhatsApp, Telegram, X, Facebook).
   - `useSeoMeta()` di [pages/maps/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/maps/index.vue) secara reaktif menghubungkan URL metadata `og:image` dan `twitter:image` ke endpoint ini.



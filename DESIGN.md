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
| ( Peta )     |    ( Geoportal )    |                                                               |
| [Tab Menu Pill: Tanpa Garis]       |                                [Cluster Kontrol Kanan Atas]   |
|                                    |                                 - Mode 3D                     |
| [ UNIVERSAL SEARCH BAR PERSISTEN ] |                                 - Layer Basemap Switcher      |
| [Input Cari Kecamatan / Layer]     |                                 - Lokasi Terkini (GPS)        |
| - Dropdown Rekomendasi 28 Kec      |                                 - Zoom In / Zoom Out          |
| - Auto-Tutup saat kursor keluar    |                                 - Reset Orientasi Kompas      |
|                                    |                                                               |
| [ SUB-VIEW TAB DINAMIS ]           |                                                               |
| - Daftar Layer / Geoportal         |                                                               |
| - Banner Koordinat & Info Fitur    |                                                               |
|------------------------------------|                                                               |
| [ FOOTER BOTTOM PANEL KIRI ]       | [Floating Basemap Panel: bottom-4 left-3 sm:left-4]           |
| Status Lapisan | Home | Bagikan     |                                                               |
| Data peta © OSM · Dinas PUBMPR      |                                                               |
+------------------------------------+---------------------------------------------------------------+
```

#### Spesifikasi Geometri Desktop:
- **Konsep Tata Letak**: Kontainer pembungkus utama (*wrapper maps*) dengan tata letak split flex 2 bagian in-flow (Panel Kiri + Panel Kanan Map Canvas). Bukan Panel Kiri floating di atas Map Canvas.
- **Header Panel Kiri**: Judul proporsional **"Peta Interaktif"** berukuran medium dengan bobot semibold/bold (`text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100`) di puncak panel dengan padding responsif `px-4 sm:px-5 pt-4 sm:pt-5 pb-2`.
- **Lebar Panel Kiri**: Standar `400px` pada desktop (`lg:w-[400px]`), responsif `350px` pada tablet (`md:w-[350px]`), tersusun berdampingan (*in-flow side-by-side*) dengan Panel Kanan.
- **Padding Konten Panel Kiri**: Menggunakan padding responsif standar `px-4 sm:px-5` pada header, tab menu, search bar, footer bottom, serta seluruh area sub-view isi konten panel (ActiveLayersView, SearchListView, LayerDetailView, SearchItemAboutView) sehingga hierarki dan garis tepi vertikal tersusun selaras tegak lurus di seluruh ukuran layar.
- **Penempatan Panel Kiri**: Komponen semantik `<aside>`, tanpa overlay floating `fixed`, tanpa tombol toggle buka/tutup, dan tanpa tombol close header.
- **Footer Sisi Bawah Panel Kiri**: Komponen *footer bottom* permanen (`border-t border-slate-200/90 dark:border-white/8 bg-slate-50/80 dark:bg-[#070c16]/80 px-4 sm:px-5 py-2.5 sm:py-3`) yang menyajikan indikator status lapisan aktif, tombol aksi cepat *Home* (pusatkan peta ke Bojonegoro), tombol *Bagikan Peta*, serta baris standar atribusi peta (*Data peta © kontributor OpenStreetMap · Dinas PUBMPR Kab. Bojonegoro*).
- **Panel Kanan (Map Canvas)**: Kontainer semantik `<main class="flex-1 h-full min-w-0 relative overflow-hidden">` yang mengisolasi render kanvas OpenLayers khusus pada area sisa layar (`calc(100vw - 400px)` pada desktop / `calc(100vw - 350px)` pada tablet), sehingga titik pusat peta dan interaksi spasial akurat tanpa tertutup panel samping.
- **Offset Header Publik**:
  - Pada desktop: Kontainer navbar publik menyesuaikan posisi mengambang di atas Panel Kanan (`left-0 md:left-[350px] lg:left-[400px] right-0`).
  - Pada mobile: Posisi mengambang penuh (`inset-x-0`).
- **Pencarian Header Navbar Khusus Halaman Beranda (*Homepage Exclusive Navbar Search*)**: Fitur pencarian pada header navbar publik (desktop input `Ctrl K` dan tombol pemicu pencarian mobile) dikhususkan hanya aktif pada halaman utama beranda (`/`). Pada halaman peta (`/maps`), fitur pencarian di header navbar dinonaktifkan sepenuhnya dan dipusatkan secara eksklusif di dalam Panel Kiri (desktop) dan Lembar Bawah Mobile (*Mobile Bottom Sheet*) agar kanvas peta lapang dan alur pencarian terfokus tanpa redundansi.
- **Posisi Kontrol Mengambang Kiri Bawah (Basemap Panel)**:
  - Berada di dalam Panel Kanan dengan posisi `left-3 sm:left-4` (relatif terhadap kontainer kanvas peta, tanpa perlu offset manual 420px).

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
| ( Peta )       |       ( Geoportal )             |
| [Tab Menu Pill Tanpa Garis Pembatas Atas/Bawah]  |
|--------------------------------------------------|
| KONDISI 1: Quick Search + Layer Aktif + Pintasan |
| KONDISI 2: Detail Layer & Slider Opasitas        |
| KONDISI 3: Hasil Identifikasi Spasial & Titik    |
+--------------------------------------------------+
```

#### Spesifikasi Lembar Bawah Mobile (*Bottom Sheet*):
- **3 Tingkat Snap Ergonomis (*Snap Levels*)**:
  1. `min` (~200px / default resting height): Tinggi default saat mode mobile device, batasnya menampilkan handle bar, header ringkas, tab segmented control, hingga kotak fitur pencarian secara utuh. Kanvas peta tetap leluasa dijelajahi hingga ~75% viewport.
  2. `peek` (50% viewport height / separuh layar): Mode separuh layar yang otomatis aktif ketika pengguna mengklik atau memfokuskan kotak input fitur pencarian, menampilkan daftar rekomendasi/hasil pencarian secara ergonomis.
  3. `full` (92% viewport height): Mode penuh untuk eksplorasi detail atribut spasial, tabel KUGI, atau katalog geoportal.
- **Translasi Anti-Gap (*Zero-Gap Translation*)**: Wadah sheet diposisikan tetap di bagian bawah dengan perpanjangan dasar (*bottom bleed* 80px) menggunakan transformasi `translateY` murni, sehingga tidak ada celah kosong di bawah saat ditarik ke atas secara agresif.
- **Fisika Pegas & Inersia**: Dilengkapi deteksi kecepatan sentuhan (*velocity flick detection*) dan efek *rubber-band* lembut saat ditarik melampaui batas atas atau bawah.

#### Posisi Responsif Kontrol Mengambang (*Floating Controls Clearance*):
- **Sinkronisasi Snap Lembar Bawah**: Tombol cepat ganti peta dasar `BasemapPanel` (kiri bawah), bilah status `MapScaleCoordinateBar` (tengah bawah), dan klaster navigasi `MapControls` (kanan bawah) bergerak responsif menempel persis di atas bibir atas Lembar Bawah dengan jarak bersih `16px`:
  1. Pada snap `min` (~200px): `bottom-[calc(200px+env(safe-area-inset-bottom,0px)+16px)]` sehingga kontrol mengambang bertengger rapi tepat di atas kotak pencarian tanpa tertutup lembar bawah.
  2. Pada snap `peek` (50% vh / separuh layar): `bottom-[calc(50dvh+16px)]` sehingga kontrol naik selaras menjaga jarak pandang interaktif.
  3. Pada snap `full` (92% vh): bertransisi lembut menghilang (`opacity-0 pointer-events-none translate-y-4`) di perangkat mobile agar tidak menghalangi lembar katalog data, sementara di desktop tetap terkunci di `md:bottom-4 md:opacity-100 md:pointer-events-auto md:translate-y-0`.
  4. Durasi dan kurva animasi menggunakan `duration-[580ms] cubic-bezier(0.16, 1, 0.3, 1)` identik dengan fisika gerak lembar bawah untuk sinkronisasi pergerakan yang mulus.

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
3. **Spesifikasi Radius 100% (*Pure Pill Design*)**:
   - Kontainer Tab Rail: `p-1 bg-slate-200/60 dark:bg-slate-800/60 rounded-full (border-radius: 100%) grid grid-cols-3 gap-1`.
   - Tombol Aktif: `bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs rounded-full (border-radius: 100%) min-h-[40px] select-none text-center`.
   - Tombol Nonaktif: `text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-full (border-radius: 100%) min-h-[40px] select-none text-center`.
   - Aturan radius 100% ini berlaku konsisten baik di Panel Kiri desktop maupun pada lembar bawah mobile.
4. **Header Peta Interaktif & Bebas Garis Pembatas (*Clean Header & Border-Free Tab*)**:
   - Panel Kiri dibuka dengan judul header: **"Peta Interaktif"** (`font-semibold`, ukuran medium `text-base sm:text-lg`, huruf pertama kapital), tanpa tombol tutup atau toggle.
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
1. **Wilayah & Kecamatan**: 28 kecamatan Kabupaten Bojonegoro beserta zona dan deskripsi.
2. **Dataset & Layer Spasial**: Layer aktif pada peta dan katalog Geoportal JIGN Bojonegoro (WMS, Vector Tiles, GeoJSON).
3. **Geosite & Geopark Bojonegoro**: 31 situs resmi dari API Geopark Bojonegoro (Keragaman Geologi, Keragaman Hayati, dan Keragaman Budaya) yang menggantikan dummy landmark.

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
4. **Kartu Pintasan Tematik & Eksplorasi**:
   - Kartu 1: *"Katalog Geoportal"* (Akses 50+ layer resmi Pemkab Bojonegoro) dengan badge "Resmi".
   - Kartu 2: *"Pusatkan Peta ke Bojonegoro"* (Reset zoom & filter kecamatan).

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

---

### 4.6. Sub-Komponen 6: `GeoparkListView.vue` (Katalog Geosite Geopark Bojonegoro)
Tampilan Sub-View 1 Tab Geopark yang terintegrasi langsung dengan API resmi Geopark Bojonegoro:
1. **Hero Summary Card**: Desain visual kurasi bertema alam geologi (*gradient emerald soft*) dengan ikon `i-lucide-mountain-snow`, judul tegas, counter total 31 situs resmi, dan tombol sinkronisasi live.
2. **Pencarian Cepat In-Tab**: Kotak pencarian langsung untuk memfilter geosite berdasarkan nama, kode (`G-01`), atau tag geologi.
3. **Filter Tiga Pilar Keragaman (Segmented Pill 100% Radius)**:
   - *Semua (31)*
   - *Geologi (20)*: Antiklin Kawengan, Kayangan Api, Kedung Lantung, Watu Gandul, dsb.
   - *Hayati (3)*: Belimbing Ngringinrejo, Rusa Malo, Hutan Jati Gondang.
   - *Budaya (8)*: Kampung Samin, Makam Wali Kidangan, Petilasan Angling Dharma, serta Warisan Takbenda (Wayang Thengul, Tari Tayub).
4. **Daftar Kartu Geosite Interaktif**: Dilengkapi foto thumbnail resmi, kode pilar unik berpalet khusus, teks deskripsi, tombol `"Detail"` untuk membuka tampilan lengkap, dan tombol `"Peta"` (min-h-[44px]) untuk memusatkan peta ke koordinat situs.
5. **Direct Navigation ke Detail**: Mengklik badan kartu geosite membuka tampilan `GeoparkDetailView`.

---

### 4.7. Sub-Komponen 7: `GeoparkDetailView.vue` (Detail Lengkap Geosite Geopark)
Tampilan Sub-View 2 Tab Geopark ketika pengguna memilih salah satu geosite:
1. **Top Bar Navigasi**: Tombol panah kembali (`i-lucide-arrow-left`) dan tombol silang tutup (`i-lucide-x`) dengan target sentuh ergonomis minimal 44x44px. Menutup detail ini secara konsisten mengembalikan pengguna ke state rute `GeoparkListView` (`geoparkSubView = 'list'`) tanpa mereset query pencarian atau pilihan filter.
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
| **R-06: Transisi Halus (No Layout Shift)** | Pergerakan sidebar, header publik, dan pergantian sub-view menggunakan transisi CSS cubic-bezier. | Wajib & Lolos Otomatis |

---

## 7. Referensi Berkas Sumber Kode

- Komponen Wadah Rute Panel Kiri: [LeftPanel.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/LeftPanel.vue)
- Sub-Komponen Daftar Rekomendasi Pencarian: [left-panel/SearchListView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/SearchListView.vue)
- Sub-Komponen Tentang Item Pencarian: [left-panel/SearchItemAboutView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/SearchItemAboutView.vue)
- Sub-Komponen Daftar Layer Aktif: [left-panel/ActiveLayersView.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/left-panel/ActiveLayersView.vue)
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


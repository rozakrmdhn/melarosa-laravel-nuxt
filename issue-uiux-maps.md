# Rencana Implementasi: Redesign UI/UX Halaman Peta Spasial (WebGIS)

Dokumen ini disusun sebagai panduan teknis implementasi langkah demi langkah (*step-by-step engineering guide*) untuk *junior programmer* maupun model AI (seperti GPT-4o-mini, Claude Haiku, atau Gemini Flash). Seluruh instruksi dilengkapi dengan nama berkas spesifik, struktur data TypeScript, desain layout ASCII, serta kriteria pengujian yang jelas.

---

## 1. Ringkasan Eksekutif & Tujuan Proyek

Halaman peta interaktif (`/maps`) saat ini menggunakan panel kiri yang melayang di pojok kiri bawah desktop (*floating panel*), bilah koordinat melayang di bagian bawah tengah layar (*floating bottom location bar*), serta header navigasi publik yang bertengger kaku di bagian atas layar.

Tujuan utama dari perombakan (*redesign*) ini adalah:
1. **Panel Kiri Penuh (*Full-Height Sidebar*)**: Mengubah panel kiri menjadi bilah sisi penuh dari atas ke bawah layar (`h-full` / `inset-y-0 left-0`) pada desktop. Seluruh navigasi anak, manajemen layer, dan inspeksi peta dipusatkan di panel ini.
2. **Penyesuaian Header Responsif**: Header navigasi menyesuaikan posisi secara responsif di sebelah kanan panel kiri (menempel di atas area peta) tanpa menutupi panel kiri, serta tetap responsif di layar perangkat seluler (*mobile*).
3. **Penyederhanaan Tab Menu (Hanya 2 Tab Teks Murni Tanpa Ikon & Badge)**:
   - **Tab Peta**: Label teks murni `"Peta"` (tanpa ikon dan tanpa badge angka/notifikasi), berisi daftar layer aktif (*simple mobile-first*), sub-halaman detail layer, serta hasil identifikasi fitur (*identify feature*).
   - **Tab Geoportal**: Label teks murni `"Geoportal"` (tanpa ikon dan tanpa badge), berisi katalog layer resmi geoportal untuk ditambahkan ke peta.
   - Aturan bebas ikon dan bebas badge ini berlaku mutlak baik pada desktop maupun mobile device.
4. **Navigasi Bertingkat Master-Detail pada Tab Peta**:
   - **Level 1 (Daftar Layer)**: Daftar ringkas kartu layer aktif dengan kontrol cepat visibilitas.
   - **Level 2 (Detail Layer)**: Saat kartu layer diklik, tampilan beralih ke sub-halaman detail yang memuat sub-fitur **Eye** (visibilitas), **Opacity** (transparansi slider), dan **Metadata** (sumber data, atribusi, legenda), lengkap dengan tombol navigasi kembali.
5. **Integrasi Fitur Identifikasi Spasial (*Identify Feature*) ke dalam Tab Menu : Peta**:
   - Saat pengguna mengklik atau mengetuk area peta untuk menginspeksi fitur spasial/koordinat (*identify feature*), hasilnya ditampilkan langsung di dalam **Tab Menu : Peta** pada Panel Kiri.
   - Mengeliminasi bilah melayang di bawah layar desktop (*floating bottom location bar*), sehingga kanvas peta desktop menjadi 100% bersih, luas, dan bebas rintangan visual.
   - Mengeliminasi tab ke-4 'Lokasi' pada *bottom sheet* mobile, sehingga navigasi mobile tetap konsisten dengan 2 tab teks murni ('Peta' dan 'Geoportal').
   - Menyediakan alur navigasi bertingkat yang rapi di dalam Tab Peta: beralih dari daftar layer ke hasil identifikasi dengan tombol kembali `< Kembali ke Daftar Layer` dan tombol bersihkan pin penanda di peta.

---

## 2. Struktur Berkas & Komponen yang Terlibat

Berikut adalah pemetaan berkas utama yang perlu dimodifikasi dan dibuat:

| Berkas | Lokasi | Peran & Tindakan |
|---|---|---|
| `app.vue` | `apps/web/app/app.vue` | Menyesuaikan pembungkus layout khusus `/maps` agar header navigasi dan kanvas peta mendukung sidebar penuh. |
| `pages/maps/index.vue` | `apps/web/app/pages/maps/index.vue` | Mengatur tata letak desktop sidebar penuh, meneruskan state koordinat dan fitur teridentifikasi (`clickedCoordinate`, `locationLabel`, `identifiedFeatures`, `isIdentifyingFeatures`) ke `LeftPanel.vue`, serta menghapus floating `BottomLocationBar` desktop. |
| `LeftPanel.vue` | `apps/web/app/components/maps/LeftPanel.vue` | Merombak tab menu menjadi 2 tab teks murni (Peta dan Geoportal), mengelola 3 sub-tampilan Tab Peta (Daftar Layer, Detail Layer, dan Identifikasi Fitur), serta mengintegrasikan `IdentifyFeatures.vue`. |
| `IdentifyFeatures.vue` | `apps/web/app/components/maps/IdentifyFeatures.vue` | Komponen tabel atribut fitur spasial, metadata KUGI, dan pencarian atribut (digunakan sebagai sub-komponen di dalam sub-tampilan identifikasi pada `LeftPanel.vue`). |
| `LayerDetailView.vue` *(Opsional)* | `apps/web/app/components/maps/LayerDetailView.vue` | Sub-komponen detail layer (Eye, Opacity Slider, Metadata Card, Zoom, Hapus) untuk menjaga kerapian kode. |
| `PublicNavbar.vue` | `apps/web/app/components/app/PublicNavbar.vue` | Penyesuaian styling dan lebar adaptif di sebelah kanan sidebar saat berada di halaman `/maps`. |
| `MapMobileBottomSheet.vue` | `apps/web/app/components/maps/MapMobileBottomSheet.vue` | Menyediakan bottom sheet mobile dengan 2 tab teks murni dan menampilkan sub-tampilan identifikasi pada Tab Peta saat peta disentuh di smartphone. |

---

## 3. Desain Wireframe & Layout Antarmuka

### A. Tampilan Desktop (Layar >= 768px / Desktop Penuh)

```
+-------------------------------------------------------------------------------------------------------+
| [ PANEL KIRI: FULL HEIGHT ] | [ HEADER NAVIGASI PUBLIK (Menyesuaikan di sebelah kanan sidebar) ]      |
|                             | Logo Pemkab Bojonegoro | Pencarian Wilayah (Ctrl K) | Tema | Profil     |
| [2 Tab Menu Utama]          +-------------------------------------------------------------------------+
| [ PETA ]  |  [ GEOPORTAL ]  | [ AREA PETA UTAMA: MapCanvas (Bersih & Lega Tanpa Floating Bar Bawah) ] |
|-----------------------------|                                                                         |
|                             |                                                                         |
| [SUB-VIEW TAB PETA]         |                                                                         |
| (1 dari 3 kemungkinan):     |                                                                         |
| 1. Daftar Layer Aktif       |                                 [Kontrol Peta: Zoom, Compass, Home]    |
| 2. Detail Layer             |                                                                         |
| 3. Hasil Identifikasi Fitur |                                 [Pin Penanda Koordinat di Peta]         |
|                             |                                                                         |
| - Tombol: + Tambah Layer    |                                                                         |
|   (Beralih ke Geoportal)    |                                                                         |
|-----------------------------|                                                                         |
| [Footer / Toggle Collapse]  |                                 [Floating Basemap Switcher Mini]        |
+-------------------------------------------------------------------------------------------------------+
```

### B. Tampilan Sub-Halaman Detail Layer (Di dalam Tab Peta saat Kartu Layer Diklik)

```
+-----------------------------+
| [ < Kembali ke Daftar Layer] |
|-----------------------------|
| Judul: Jalan Poros Desa     |
| Kategori: Infrastruktur PU  |
|-----------------------------|
| [SUB-FITUR 1: VISIBILITAS]  |
| Status Tampil: [ Toggle ON ]|
|-----------------------------|
| [SUB-FITUR 2: TRANSPARANSI] |
| Opasitas: 85%               |
| [======O-----------] Slider |
|-----------------------------|
| [SUB-FITUR 3: METADATA]     |
| - Sumber: Martin Tile Server|
| - Tipe: Vector Tiles (MVT)  |
| - Instansi: Dinas PU Bina M.|
| - Update: 22 Oktober 2026   |
| - Legenda: [Simbol Garis]   |
|-----------------------------|
| [AKSI LAYER]                |
| [ Zoom ke Cakupan Layer ]   |
| [ Hapus dari Peta ]         |
+-----------------------------+
```

### C. Tampilan Sub-Halaman Identifikasi Fitur (Di dalam Tab Peta saat Peta Diklik)

```
+-----------------------------+
| [ < Kembali ] [ X Bersihkan]|
|-----------------------------|
| IDENTIFIKASI FITUR & LOKASI |
| Label: Sukorejo, Bojonegoro |
| Lat: -7.152431, Lng: 111.884|
| [Salin Koordinat] [Bagikan] |
|-----------------------------|
| [FITUR TERIDENTIFIKASI (2)] |
| Tab Fitur: [Desa] [Jalan]   |
|-----------------------------|
| Atribut Objek Spasial:      |
| - Nama Objek: Sukorejo      |
| - Kode Wilayah: 35.22.15    |
| - Luas Wilayah: 142.5 Ha    |
| - KUGI FCODE: BA0010        |
| - Instansi: Bappeda         |
|                             |
| [ Buka di Google Maps ]     |
+-----------------------------+
```

### D. Tampilan Mobile (Layar < 768px / Ponsel)

```
+---------------------------------------+
| [Header Mobile Ringkas: Logo & Menu]  |
+---------------------------------------+
|                                       |
|     [ PETA INTERAKTIF: FULLSCREEN ]   |
|         [ Pin Koordinat Terpilih ]    |
|                                       |
+---------------------------------------+
| === [Handle Bar Drag Lembar Bawah] == |
| Tab Menu: [ Peta ]  |  [ Geoportal ]  |
|---------------------------------------|
| KONDISI A: Daftar Layer Aktif         |
| KONDISI B: Detail Layer Terpilih      |
| KONDISI C: Hasil Identifikasi Fitur   |
| (Dapat ditarik separuh / penuh)       |
+---------------------------------------+
```

---

## 4. Spesifikasi Teknis & Kebutuhan Fungsional

### 4.1. Layout Panel Kiri Penuh (Full-Height Sidebar)

1. **Posisi & Dimensi Desktop**:
   - Panel kiri harus menempel penuh dari sisi atas layar (`top: 0`) hingga bawah layar (`bottom: 0`).
   - Lebar tetap (*fixed width*): `w-[380px]` (atau `w-[400px]` pada monitor resolusi tinggi).
   - Memiliki tombol sembunyikan/ciutkan (*collapse sidebar toggle*) berupa tombol bulat atau tab kecil di batas sisi kanan panel (`border-r`). Saat panel diciutkan, lebar menjadi `w-0` atau `w-14` (mini rail), dan kanvas peta otomatis meluas memenuhi layar.
2. **Header Navigasi Publik**:
   - Pada desktop, header navigasi tidak boleh tertutup oleh panel kiri.
   - Posisi header dibuat melayang (*floating*) atau *fixed* dengan koordinat `left-[380px] right-0` saat panel kiri terbuka, dan `left-0 right-0` saat panel kiri tertutup.
   - Gunakan transisi CSS `transition-[left,width] duration-300 ease-in-out` agar pergerakan header halus saat panel dibuka atau ditutup.

### 4.2. Sistem 2 Tab Menu Utama (Teks Murni Tanpa Ikon & Badge)

Panel Kiri hanya memiliki dua pilihan tab di bagian atas, baik pada layar desktop maupun mobile device:
1. **Tab 1: Peta**
   - Format: Teks murni `"Peta"` (DILARANG menggunakan ikon dan DILARANG menggunakan badge angka/indikator).
   - Menampung seluruh kendali layer aktif, urutan tumpukan (*reorder*), sub-halaman detail layer, serta sub-halaman identifikasi fitur spasial (*identify feature*).
2. **Tab 2: Geoportal**
   - Format: Teks murni `"Geoportal"` (DILARANG menggunakan ikon dan DILARANG menggunakan badge angka/indikator).
   - Menampung katalog data spasial Bojonegoro, filter OPD/kategori, dan tombol tambah layer ke peta.

> [!IMPORTANT]
> **Larangan Keras Ikon & Badge pada Tab Menu (Desktop & Mobile)**:
> Jangan menyertakan ikon apapun (misal `i-lucide-map`, `i-lucide-globe-2`, `UIcon`) dan jangan menampilkan informasi badge apapun (misal badge jumlah layer aktif `{{ activeLayers.length }}`, `UBadge`, atau dot status) pada tab menu Peta maupun Geoportal. Tab menu harus tampil sangat bersih, minimalis, dan elegan hanya berupa teks label murni di semua ukuran layar (desktop dan mobile device).

### 4.3. Tab Peta: Sistem 3 Sub-Tampilan (Sub-Views)

Di dalam Tab Menu Peta, kelola state navigasi lokal dengan tipe:
```ts
type PetaSubView = "layers" | "layer-detail" | "identify";
const petaSubView = ref<PetaSubView>("layers");
```

1. **Sub-View 1: `layers` (Daftar Layer Aktif - Tampilan Utama)**:
   - Menampilkan daftar layer aktif secara sederhana (*simple mobile-first*).
   - Setiap kartu layer berisi:
     - Nomor urut dan *drag grip* untuk mengubah susunan layer (*reorder*).
     - Nama/judul layer (contoh: "Batas Wilayah Desa", "Jalan Poros").
     - Tombol cepat mata (*Quick Eye Toggle*) untuk menyembunyikan/menampilkan layer tanpa harus masuk ke detail.
     - Ikon panah kanan (`i-lucide-chevron-right`) yang mengindikasikan kartu dapat diklik untuk membuka detail.
   - **Indikator Titik Aktif**: Jika pengguna sebelumnya telah mengklik peta dan ada koordinat aktif (`clickedCoordinate !== null`), tampilkan kartu banner kecil di bagian atas daftar layer: *"Titik koordinat aktif dipilih"* dengan tombol pintas *"Lihat Fitur"* untuk kembali ke sub-view `identify`.
   - **Interaksi Klik**: Mengetuk atau mengklik kartu (selain tombol mata) memanggil `selectedLayerId = layer.id` dan mengubah `petaSubView = 'layer-detail'`.
   - **Keadaan Kosong (*Empty State*)**: Jika belum ada layer aktif, tampilkan ilustrasi bersih dengan tombol CTA utama: `"Jelajahi Katalog Geoportal"`, yang jika diklik langsung memindahkan tab aktif ke Tab 2 (Geoportal).

2. **Sub-View 2: `layer-detail` (Detail Layer Aktif)**:
   - Menampilkan sub-fitur lengkap untuk layer yang sedang dipilih:
     - **Header Detail**: Tombol kembali dengan teks `"Daftar Layer"`, ikon `i-lucide-arrow-left`, serta judul layer.
     - **Sub-Fitur 1 (Eye / Visibilitas)**: Switch toggle besar yang jelas (Aktif / Nonaktif di peta).
     - **Sub-Fitur 2 (Opacity / Transparansi)**:
       - Slider rentang (`<input type="range" min="0" max="100">` atau komponen `USlider`).
       - Menampilkan label nilai persentase secara real-time (misal: `85%`).
       - Memperbarui opasitas layer di peta seketika (*instant reactive update*).
     - **Sub-Fitur 3 (Metadata & Atribut Spasial)**:
       - Menyajikan informasi sumber data (MVT / WMS / GeoJSON), URL endpoint, instansi pengelola, tanggal pembaruan, dan legenda/simbologi garis/warna.
     - **Sub-Fitur 4 (Filter Layer, sudah ada di kode)**: pertahankan alat filter kecamatan/status per layer yang kini dipicu `toggleLayerTool` (`ActiveLayerTool`) dan event `update-layer-filter` (`filterKecamatan`, `filterStatus`). Pindahkan ke halaman detail, jangan dihapus.
     - **Aksi Ekstra**:
       - Tombol `"Zoom ke Cakupan Layer"` (`i-lucide-maximize-2`).
       - Tombol `"Hapus Layer dari Peta"` bertema peringatan merah lembut (`i-lucide-trash-2`). Saat dihapus, otomatis kembali ke daftar layer.

3. **Sub-View 3: `identify` (Identifikasi Fitur & Titik Koordinat)**:
   - Pemicu nyata di `pages/maps/index.vue` (jangan membuat event baru): `handleMapClick` (set `clickedCoordinate`, kosongkan `identifiedFeatures`), `handleIdentifyFeatures` (isi hasil dan label `NAMOBJ`), `isIdentifyingFeatures` (dari `@identifying-features`), `handleSelectLocation` (pencarian navbar atau `?search=`), `handleSelectKecamatan`, dan `handleZoomToLayer`. Semua jalur ini sudah mengaktifkan `locationBarOpen = true`; jadikan itu pemicu sub-view `identify`.
   - `locationBarOpen` tetap menjadi satu-satunya penanda "inspector terbuka". Sub-view `identify` tampil jika `locationBarOpen && (clickedCoordinate || identifiedFeatures.length > 0)`.
   - **Logika Otomatis saat Peta Diklik**:
     - Jika panel kiri sedang diciutkan (*collapsed*), otomatis luaskan panel kiri.
     - Jika pengguna sedang berada di tab Geoportal, otomatis alihkan tab aktif ke Tab Peta.
     - Atur `petaSubView = 'identify'`.
     - Pada perangkat mobile, pertahankan perilaku saat ini: `mobileSheetSnap = 'min'` (header ringkas berisi label dan koordinat, peta tetap terlihat). Pengguna menarik header ke `'peek'` (48dvh) atau `'full'`. Nilai `SnapLevel` yang valid hanya `'min' | 'peek' | 'full'`.
   - **Elemen Sub-View Identifikasi**:
     - **Baris Navigasi Atas**:
       - Tombol `< Kembali ke Daftar Layer` (mengubah `petaSubView = 'layers'`).
       - Tombol `Bersihkan Pin` (`i-lucide-map-pin-off` atau `i-lucide-x`) untuk menghapus titik pin di peta dan mereset koordinat terpilih.
     - **Ringkasan Koordinat & Lokasi**:
       - Label nama wilayah/desa hasil reverse geocoding atau nama fitur utama.
       - Koordinat Latitude & Longitude (format 6 angka desimal, contoh: `-7.152431, 111.884120`).
       - Tombol salin koordinat ke clipboard disertai feedback toast/animasi centang.
       - Tombol bagikan peta/lokasi (`i-lucide-share-2`) yang memicu dialog bagikan.
       - Tautan buka di Google Maps (`https://www.google.com/maps?q=lat,lng`).
     - **Daftar Fitur Spasial Teridentifikasi**:
       - Indikator proses pemuatan (*loading skeleton pulse*) saat kueri WMS GetFeatureInfo / Vector Tile sedang berjalan (`isIdentifyingFeatures === true`).
       - Jika tidak ada layer/fitur ditemukan pada titik klik: Tampilkan status kosong informatif: *"Tidak ada objek fitur spasial pada titik ini"*, dengan saran mengetuk layer lain atau mengaktifkan layer dari Geoportal.
       - Jika ditemukan 1 atau lebih fitur spasial: Sematkan komponen [IdentifyFeatures.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/IdentifyFeatures.vue) yang memuat tab pemilih layer fitur, pencarian nama atribut, nilai data, dan metadata KUGI.
      - **Gunakan ulang `BottomLocationBar.vue`, jangan tulis ulang**: komponen ini (684 baris) sudah memuat tab `features`/`coordinates`, proyeksi EPSG:3857, salin koordinat, tautan Google Maps, tombol bagikan, dan sudah punya prop `isMobileDrawer` untuk mode tertanam. Render `<BottomLocationBar :is-mobile-drawer="true" ...>` di dalam sub-view `identify` dengan prop dan event yang sama seperti kini (`clickedCoordinate`, `locationLabel`, `identifiedFeatures`, `isIdentifying`; event `close`, `select-feature`, `share`). Pembungkus melayang desktop saja yang dihapus.

### 4.4. Fitur Wilayah (Kecamatan) yang Harus Dipertahankan

Tab `Wilayah` lama (28 kecamatan, `KECAMATAN_ENTRIES`, pencarian, zona) bukan sekadar pencarian: ia mengubah `selectedKecamatanFilter`, menjalankan `handleFilterWilayah` (memfilter layer MVT `infrastruktur_segmen` dan `jalan_porosdesa`), terhubung ke query URL `?kecamatan=`, dan terbang ke kecamatan lewat `handleSelectKecamatan`. Karena tab hanya boleh 2, pindahkan sebagai bagian **"Filter Wilayah"** (kartu ciut/buka) di atas daftar layer pada sub-view `layers` Tab Peta, lengkap dengan tombol reset (`zoom-to-home`). Event `filter-wilayah`, `select-kecamatan`, `zoom-to-home` tidak boleh dihapus.

---

## 5. Antarmuka Data TypeScript & Komunikasi Event

Berikut adalah kontrak tipe data yang digunakan pada komponen:

`ActiveLayerItem` dan `ActiveLayerTool` sudah ada di `~/types/map-layers`. **Jangan mendefinisikan ulang**; buka berkas itu dan pakai field yang ada. Field yang sudah dipakai kode `/maps`: `id`, `title`, `layer_name`, `protocol`, `source_type`, `martinSource`, `visible`, `opacity`, `expanded`, `activeTool`, `coordinates`, `zoom`, `filterKecamatan`, `filterStatus`. Field metadata di dokumen sebelumnya (`instansi`, `opd_name`, `legend_url`, `created_at`, dan sebagainya) belum tentu ada: cek dahulu, tampilkan hanya yang tersedia, dan tambahkan ke tipe hanya jika memang diperlukan.

### Props pada Panel Kiri (`LeftPanel.vue`)

Props yang SUDAH ada (jangan diganti namanya): `isMobileDrawer`, `activeLayers`, `currentBasemap`, `activeTab`, `selectedKecamatanFilter`. Prop `activeTab` saat ini bertipe `"layers" | "geoportal" | "kecamatan"`. Ubah menjadi `"layers" | "geoportal"` (nilai `"layers"` tetap dipakai sebagai tab Peta; teks tampilannya "Peta"). Tambahkan hanya prop berikut, dengan nama yang sama seperti `BottomLocationBar.vue`:

```ts
import type { IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";

// Tambahan pada defineProps LeftPanel
clickedCoordinate?: [number, number] | null;
locationLabel?: string | null;
identifiedFeatures?: IdentifiedFeature[];
isIdentifying?: boolean;
inspectorOpen?: boolean; // = locationBarOpen dari pages/maps/index.vue
```

### Event Emits pada Panel Kiri (`LeftPanel.vue`)

Event yang SUDAH ada dan wajib dipertahankan: `close`, `zoom-to-home`, `select-kecamatan`, `filter-wilayah`, `reorder-layers`, `zoom-to-layer`, `toggle-layer-visibility`, `update-layer-opacity`, `update-layer-filter`, `remove-layer`, `add-layer`, `update:currentBasemap`. Tambahkan hanya tiga event yang meneruskan event `BottomLocationBar` apa adanya:

```ts
(e: "close-inspector"): void;                              // dari BottomLocationBar @close -> handleCloseInspector
(e: "select-feature", feature: IdentifiedFeature): void;   // -> handleSelectFeature
(e: "share"): void;                                        // -> handleOpenShare
```

Event `toggle-collapse` hanya ditambahkan jika sidebar punya tombol ciut (lihat Fase 1).

---

## 6. Tahapan Pengerjaan Langkah demi Langkah

Petunjuk bagi *junior programmer* atau AI model yang mengimplementasikan fitur ini: kerjakan secara berurutan sesuai fase berikut.

### Fase 1: Penyesuaian Layout Desktop & Pembersihan Floating Bar

1. Buka berkas [apps/web/app/app.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/app.vue):
   - Fakta kode: blok `isMaps` saat ini `fixed inset-0 flex flex-col` dengan `<AppPublicNavbar class="shrink-0 z-[60]">` DI ATAS `<main>` yang memuat halaman. Artinya header berada di atas peta, bukan di sebelah sidebar.
   - Agar sidebar penuh tinggi dengan header di kanan sidebar: buat state bersama `const mapsSidebarWidth = useState<number>("maps_sidebar_width", () => 0)`. Halaman `/maps` mengisinya (380 saat sidebar terbuka di desktop, 0 saat tertutup atau mobile). Di `app.vue`, beri navbar `:style="{ paddingLeft: mapsSidebarWidth + 'px' }"` dengan `transition-[padding] duration-300`. Sidebar di halaman memakai `fixed inset-y-0 left-0 z-[70]` agar menutupi sisi kiri navbar (navbar `z-[60]`).
   - Tambahkan padding kiri yang sama untuk kontrol peta mengambang (`BasemapPanel`, zoom) dan offset `flyTo`/`fitBbox` pada `MapCanvas` agar pin tidak tertutup sidebar.
2. Buka berkas [apps/web/app/pages/maps/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/maps/index.vue):
   - **Hapus pembungkus Floating Bottom Location Bar Desktop** (komentar `4. FLOATING BOTTOM-CENTER LOCATION BAR`, class `hidden md:block absolute bottom-4 ... left-1/2`). `BottomLocationBar` dipindahkan ke dalam `LeftPanel`, bukan dibuang. Perhatikan: `@after-leave="handleInspectorAfterLeave"` pada `<Transition>` itulah yang kini mengosongkan `clickedCoordinate`, `searchedLocationLabel`, dan `identifiedFeatures` setelah animasi tutup di desktop. Pindahkan logika ini (mis. `watch(locationBarOpen)` dengan jeda singkat setara animasi) agar tombol tutup tetap membersihkan pin dan hasil.
   - **Gunakan ulang `leftPanelOpen` (jangan buat `isSidebarCollapsed` baru)**: ref ini sudah ada, bawaan `false`, dikendalikan tombol "Lapisan Peta" pojok kiri bawah (`handleToggleLeftPanelOrMobileDrawer`, `isLeftPanelActive`, `activeLayersSummary`). Di desktop jadikan bawaan `true` dan pertahankan tombol itu sebagai pembuka saat sidebar diciutkan. `handleSelectDataset` sudah memaksa `leftPanelOpen = true`.
   - **Ubah deklarasi pembungkus desktop `LeftPanel`**:
     - Ganti kelas lama: `absolute bottom-[128px] left-3 md:left-4 z-20`
     - Menjadi kelas baru: `absolute inset-y-0 left-0 z-30 h-full w-[380px] bg-white dark:bg-[#0b1322] border-r border-slate-200 dark:border-white/10 shadow-xl transition-all duration-300`
   - **Teruskan State Identifikasi ke `LeftPanel`**:
     - Pasang props: `:clicked-coordinate="clickedCoordinate"`, `:location-label="searchedLocationLabel"`, `:identified-features="identifiedFeatures"`, `:is-identifying="isIdentifyingFeatures"`, `:inspector-open="locationBarOpen"`.
     - Pasang event listeners: `@close-inspector="handleCloseInspector"`, `@share="handleOpenShare"`, `@select-feature="handleSelectFeature"`.
   - **Logika Otomatis saat Klik Peta**: pada `handleMapClick`, `handleSelectLocation`, `handleIdentifyFeatures`, `handleZoomToLayer`, `handleSelectKecamatan` (yang sudah mengisi `locationBarOpen = true`) tambahkan `leftPanelOpen.value = true` untuk desktop. `LeftPanel` sendiri berpindah ke Tab Peta dan sub-view `identify` lewat watch `inspectorOpen` (Fase 3).
   - Kondisi awal: URL `?search=`, `?pin=`, atau `@lat,lng` sudah mengisi `clickedCoordinate` dan `locationBarOpen` saat load, sehingga sidebar langsung terbuka di sub-view `identify`. Itu perilaku yang diinginkan.

### Fase 2: Perombakan Tab Menu Panel Kiri di `apps/web/app/components/maps/LeftPanel.vue`

1. Buka berkas [apps/web/app/components/maps/LeftPanel.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/LeftPanel.vue).
2. Sederhanakan tipe tab aktif menjadi 2 opsi saja, dengan mempertahankan mekanisme yang ada (`props.activeTab`, `localActiveTab`, `currentActiveTab`; di mobile drawer tab dikendalikan dari induk):
   ```ts
   type MainTab = "layers" | "geoportal"; // "layers" ditampilkan sebagai "Peta"
   const localActiveTab = ref<MainTab>(props.activeTab);
   ```
3. Perbarui bagian tombol tab di bagian atas template:
   - Buat 2 tombol tab teks murni yang proporsional (*grid-cols-2*):
     - **Tab Peta**: Label teks murni `"Peta"` (tanpa ikon dan tanpa badge jumlah layer).
     - **Tab Geoportal**: Label teks murni `"Geoportal"` (tanpa ikon dan tanpa badge informasi).
   - Pastikan styling tab bersih, minimalis, dan elegan dengan indikator aktif yang nyaman dibaca (misalnya pill switch halus atau background aktif) tanpa elemen dekoratif ikon atau badge, baik di layar desktop maupun mode mobile device.
4. Hilangkan tab lama `"kecamatan"` dari tombol tab, tetapi PINDAHKAN isinya (daftar `KECAMATAN_ENTRIES`, pencarian, `handleKecamatanClick`, `handleResetWilayahFilter`) menjadi kartu "Filter Wilayah" di sub-view `layers` Tab Peta (lihat bagian 4.4). Jangan menghapus kodenya.

### Fase 3: Pembuatan Navigasi 3 Sub-View pada Tab Peta

1. Di dalam [LeftPanel.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/LeftPanel.vue), kelola sub-view:
   ```ts
   type PetaSubView = "layers" | "layer-detail" | "identify";
   const petaSubView = ref<PetaSubView>("layers");
   const selectedLayerId = ref<string | null>(null);

   const selectedLayer = computed(() =>
     props.activeLayers.find(l => l.id === selectedLayerId.value) || null
   );

   // Buka sub-view identify hanya saat inspector terbuka (locationBarOpen dari induk)
   watch(
     () => props.inspectorOpen,
     (open) => {
       if (open) {
         localActiveTab.value = "layers";
         petaSubView.value = "identify";
       } else if (petaSubView.value === "identify") {
         petaSubView.value = "layers";
       }
     },
     { immediate: true }
   );
   ```
2. Buat kondisi tampilan di dalam Tab Peta:
   - **Kondisi 1: `petaSubView === 'layers'` (Daftar Layer Aktif)**:
     - Daftar kartu ringkas layer aktif dengan tombol toggle mata dan klik untuk membuka detail layer.
     - Jika `clickedCoordinate` tidak kosong, tampilkan bar notifikasi kecil untuk kembali ke sub-view `identify`.
   - **Kondisi 2: `petaSubView === 'layer-detail'` (Detail Layer)**:
     - Tombol kembali `< Kembali ke Daftar Layer` (`petaSubView = 'layers'`).
     - Kontrol Switch Visibilitas, Slider Opasitas, Metadata lengkap, tombol Zoom dan Hapus.
   - **Kondisi 3: `petaSubView === 'identify'` (Identifikasi Fitur & Koordinat)**:
     - Tombol navigasi kembali `< Kembali ke Daftar Layer` dan tombol `Bersihkan Pin` (`emit('close-inspector')`).
     - Koordinat, salin koordinat, bagikan (`emit('share')`), dan tautan Google Maps: sudah disediakan `BottomLocationBar` (lihat 4.3), jangan diduplikasi.
     - Keadaan pemuatan (*loading skeleton pulse*) saat `isIdentifying === true`.
     - Tampilan kosong (*empty state*) jika tidak ada layer pada titik tersebut.
     - Sematan sub-komponen `IdentifyFeatures.vue` jika terdapat fitur terdeteksi.

### Fase 4: Sinkronisasi Tampilan Mobile (Mobile-First Bottom Sheet)

1. Buka berkas [apps/web/app/components/maps/MapMobileBottomSheet.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/MapMobileBottomSheet.vue) dan [apps/web/app/pages/maps/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/maps/index.vue):
   - Tipe saat ini `MobileDrawerTab = "layer" | "geoportal" | "kecamatan" | "inspector"` dengan 4 tombol `grid-cols-4`, ikon `UIcon`, dan badge (`activeLayers.length`, titik biru Wilayah/Lokasi). Ubah menjadi `"layer" | "geoportal"`, `grid-cols-2`, tanpa ikon, tanpa badge, tanpa titik.
   - Hapus blok kedua `BottomLocationBar` mobile (`v-show="mobileDrawerTab === 'inspector'"`) dan semua percabangan `'inspector'`/`'kecamatan'` pada `title`, `subtitle`, `icon` sheet, `openMobileTab`, `handleCloseInspector`, serta `watch(mobileDrawerOpen)` yang menutup inspector. Judul sheet saat `locationBarOpen` memakai `searchedLocationLabel` dan koordinat (sama seperti tab Lokasi sekarang).
   - `LeftPanel` mobile hanya satu instance persisten (`v-show`); teruskan prop identifikasi yang sama dengan desktop.
2. Saat pengguna mengetuk titik di peta pada smartphone (`handleMapClick`, `handleSelectLocation`):
   - Set `mobileDrawerTab = 'layer'` (bukan `'inspector'`), `mobileDrawerOpen = true`, `mobileSheetSnap = 'min'`, dan `locationBarOpen = true`. Sub-view `identify` tampil lewat watch `inspectorOpen`.
   - Pertahankan snap `'min'` agar pin tetap terlihat; `'peek'` pada kode nyata adalah 48dvh, bukan 38vh. Saat ditutup (`handleCloseInspector`), kembali ke `'min'` dan kosongkan koordinat setelah jeda 320 ms seperti sekarang.
   - Tombol mengambang kiri bawah (`BasemapPanel`) sudah mengikuti `mobileSheetSnap` lewat kelas `bottom-[calc(...)]`; jangan ubah.
3. Pastikan target sentuh (*touch target*) seluruh tombol minimal 44x44px sesuai standar `antislop-layoutmobile` (R-03).

### Fase 5: Pengujian & Validasi Hasil Akhir

Lakukan pengujian fungsional menyeluruh:
1. **Uji Identifikasi Peta Desktop**: Klik area peta sembarang -> Tab Peta di Panel Kiri otomatis beralih menampilkan koordinat dan hasil identifikasi fitur -> Tidak ada bilah melayang di bawah tengah peta -> Kanvas peta bersih.
2. **Uji Bersihkan Pin & Kembali**: Klik tombol bersihkan pin -> Marker pin di peta hilang -> Klik tombol kembali -> Tampilan kembali ke daftar layer aktif.
3. **Uji Navigasi Layer**: Menambah layer dari Tab Geoportal -> Layer muncul di Tab Peta -> Klik layer -> Masuk ke detail -> Geser opasitas -> Peta langsung reaktif -> Klik kembali -> Kembali ke daftar layer.
4. **Uji Responsif Mobile**: Ketuk peta pada smartphone -> Bottom sheet membuka Tab Peta pada sub-view identifikasi fitur -> Geser sheet ke atas/bawah berjalan lancar (*smooth spring transition*).
5. **Uji Tab Bebas Ikon & Badge**: Pastikan tab Peta dan Geoportal tidak memiliki ikon dan tidak memiliki badge angka/dot pada resolusi desktop maupun mobile.

---

## 7. Kriteria Keberhasilan (*Acceptance Criteria*)

Tugas ini dinyatakan selesai jika memenuhi seluruh poin berikut:
- [ ] Panel kiri tampil penuh dari atas ke bawah layar (`full-height`) di desktop tanpa terpotong.
- [ ] Header navigasi publik menyesuaikan posisinya di sebelah kanan panel kiri pada mode desktop.
- [ ] Panel kiri hanya memiliki 2 tab menu utama: **Peta** dan **Geoportal**.
- [ ] Tab Menu hanya berupa teks murni ("Peta" dan "Geoportal"), tanpa ikon dan tanpa badge informasi, berlaku konsisten di mode Desktop maupun Mobile device.
- [ ] Tab Peta menampilkan daftar layer aktif dengan tampilan bersih dan ringkas (*mobile-first*).
- [ ] Mengetuk layer akan masuk ke halaman detail layer di dalam panel kiri (visibilitas, slider opasitas, metadata, zoom, hapus).
- [ ] Saat pengguna mengklik titik pada peta, hasil identifikasi fitur (*Identify Feature*) dan koordinat otomatis ditampilkan di dalam **Tab Menu : Peta**.
- [ ] Bilah lokasi melayang (*BottomLocationBar*) di bawah tengah kanvas peta desktop berhasil dihilangkan, kanvas peta bersih dan lega.
- [ ] Tab ke-4 "Lokasi" pada mobile bottom sheet berhasil dihilangkan; antarmuka mobile konsisten menggunakan 2 tab teks murni ("Peta" dan "Geoportal").
- [ ] Tersedia tombol kembali ke daftar layer dan tombol bersihkan pin dari tampilan hasil identifikasi fitur.
- [ ] Menambah layer dari Tab Geoportal otomatis menampilkannya di daftar layer aktif pada Tab Peta.
- [ ] Pada layar ponsel (< 768px), antarmuka bertransformasi menjadi *bottom sheet drawer* yang halus dan responsif.
- [ ] Bebas dari pesan kesalahan (*console errors* atau *TypeScript errors*) dan lolos linting.

---

## 8. Panduan Kualitas Kode & Larangan (Anti-Slop Rules)

Bagi programmer atau model AI yang mengeksekusi instruksi ini:
1. **Dilarang menggunakan karakter em dash (`—`)**: Gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau tanda kurung `()`.
2. **Dilarang membuat tombol mati (*dead buttons*)**: Setiap tombol interaktif (mata, slider, kembali, hapus, zoom, salin koordinat, bagikan, bersihkan pin) harus memiliki fungsi nyata (*event handler*).
3. **Standar Aksesibilitas Touch Target**: Seluruh tombol interaktif wajib memiliki ukuran minimal 44x44 piksel pada perangkat seluler.
4. **Warna & Kontras (WCAG AA)**: Seluruh teks abu-abu harus memiliki kontras minimal 4.5:1 terhadap warna latar belakang, baik pada mode terang (*light mode*) maupun mode gelap (*dark mode*).
5. **Pertahankan Fitur yang Sudah Berjalan**: Jangan merusak integrasi basemap, pencarian koordinat, maupun fitur identifikasi WMS/KUGI (*inspector*) yang sudah aktif.
6. **Larangan Ikon & Badge pada Tab Menu**: Dilarang memasang ikon grafis dan dilarang menampilkan badge angka/counter pada tombol Tab Menu (Peta dan Geoportal), baik di mode desktop maupun mobile device. Tab harus berupa teks murni yang bersih.
7. **Pembersihan Kanvas Desktop**: Jangan biarkan ada bar lokasi melayang di bagian bawah tengah desktop; seluruh tampilan koordinat dan hasil identifikasi harus terpadu rapi di dalam Panel Kiri Tab Peta.

# Dokumen Perencanaan Implementasi: Refactoring UI/UX & Arsitektur `/admin/infrastruktur-segmen`

Dokumen ini disusun sebagai panduan teknis langkah demi langkah (step-by-step) untuk mengimplementasikan perbaikan fitur, UI/UX, dan kualitas kode pada halaman **Editor Spasial Infrastruktur Segmen (`/admin/infrastruktur-segmen`)**.

Panduan ini dirancang secara eksplisit agar dapat dieksekusi langsung oleh **Junior Programmer** atau **AI Coding Model yang lebih hemat biaya** tanpa ambiguitas. Setiap bagian mencantumkan path file, nomor baris, penyebab masalah, kontrak `props`/`emits`, serta contoh kode pengganti.

---

## 0. Konteks Desain & Aturan Wajib (Guardrails)

Sebelum menulis atau mengubah kode apa pun, pelaksana **wajib** mematuhi aturan desain proyek (`docs/DESIGN.md`, `docs/DESIGN_ADMIN.md`, `docs/DESIGN_MAPS.md`) dan aturan kualitas kode berikut:

### Design Read & Parameter Visual
- **Design Read**: Workspace GIS operasional untuk instansi pemerintah daerah (BAPPEDA, Dinas PU, Verifikator Kecamatan, Operator Desa) dengan fokus pada keterbacaan peta, ketepatan data atribut, dan kepadatan informasi yang tenang.
- **Dial Pengaturan**: `ENERGY 1 / RHYTHM 2 / MOTION 1` (Tenang, terstruktur, transisi fungsional cepat `150ms-200ms`, tanpa animasi dekoratif).
- **Alasan Keputusan Desain (One-Line Reasons)**:
  - **Warna**: Menggunakan permukaan `white` / `slate-900` dengan aksen fungsional `blue-600` (`#2563EB`) untuk kontrol aktif dan warna semantik kondisi (`emerald-500`, `amber-500`, `orange-500`, `red-500`) agar tidak bersaing dengan warna polilin di kanvas peta.
  - **Layout**: Menggunakan tata letak full-viewport (`100dvh`, `overflow-hidden`) dengan panel samping yang dapat dibuka-tutup (collapsible) dan panel tabel bawah agar kanvas peta tetap menjadi elemen utama (>= 65% area layar desktop).
  - **Tipografi**: Menggunakan `Plus Jakarta Sans` di seluruh elemen antarmuka dan mengaktifkan `tabular-nums` (`font-variant-numeric: tabular-nums`) pada seluruh angka, koordinat, panjang, persentase, dan nomor halaman tanpa menggunakan `font-mono`.
  - **Spacing**: Menggunakan kepadatan kompak (`p-3`, `gap-2`, tinggi baris tabel `36px-40px`) pada panel samping dan tabel atribut agar operator dapat memindai banyak atribut tanpa sering melakukan scroll.
  - **Cards**: Membatasi maksimal 3 titik data per kartu ringkasan layer dan tidak menyarangkan kartu di dalam kartu (no card-in-card).
  - **Icons**: Menggunakan ikon Lucide (`i-lucide-*`) berukuran konsisten (`size-4` pada tombol standar, `size-3.5` pada indikator kompak) dan selalu menyertakan `aria-label` pada tombol yang hanya berisi ikon.

### Larangan Keras (Hard Prohibitions)
1. **Dilarang menggunakan `font-mono`** pada angka tabel, koordinat, badge jumlah, ID segmen, atau label kategori. Gunakan class `tabular-nums` dengan font sans-serif bawaan.
2. **Dilarang menggunakan karakter em-dash atau en-dash** pada teks antarmuka, placeholder, maupun komentar kode. Gunakan tanda hubung biasa (`-`), titik dua (`:`), atau tanda kurung `()`.
3. **Dilarang menggunakan emoji dekoratif** pada judul, tombol, toast, atau pesan kosong (empty state). Gunakan ikon `UIcon` (`i-lucide-*`).
4. **Dilarang membuat tombol atau ikon interaktif palsu** (seperti ikon drag handle yang tidak bisa di-drag atau tombol toolbar yang selalu `disabled` tanpa aksi).
5. **Wajib menyediakan 3 state data** pada komponen pemuat data: `Loading` (skeleton), `Empty` (pesan jelas + tombol aksi pemulihan), dan `Error` (pesan gagal muat + tombol "Coba Lagi").
6. **Wajib memenuhi area sentuh minimal `44x44px`** pada layar mobile/tablet dan menambahkan `cursor-pointer` pada semua elemen klik non-standar.

---

## 1. Review Halaman Fitur `/admin/infrastruktur-segmen`

### 1.1 Ringkasan Arsitektur Saat Ini
Halaman `/admin/infrastruktur-segmen` adalah aplikasi WebGIS berbasis **OpenLayers** yang menggabungkan:
- **Vector Tiles (MVT)** dari server tile **Martin** untuk merender seluruh segmen infrastruktur fisik dan jalan poros desa secara cepat.
- **GeoJSON Vector Layer** dari API Laravel/PostGIS untuk operasi interaktif seperti gambar segmen baru (`Draw`), ubah geometri (`Modify`), potong segmen (`Split`), dan penyorotan segmen terpilih (`Highlight`).

**Daftar File Utama yang Terlibat:**
1. `apps/web/app/pages/admin/infrastruktur-segmen/index.vue` (2.758 baris) - Orkestrator halaman, state filter, pemanggilan API, topbar, dan modal aksi.
2. `apps/web/app/components/dataset/infrastruktur-segmen/MapCanvas.vue` - Komponen peta OpenLayers, kontrol zoom, basemap switcher, dan interaksi geometri.
3. `apps/web/app/components/dataset/infrastruktur-segmen/LeftPanel.vue` (849 baris) - Panel kontrol layer, digitasi, opasitas, informasi layer, dan legenda.
4. `apps/web/app/components/dataset/infrastruktur-segmen/RightPanel.vue` (749 baris) - Panel inspeksi atribut segmen terpilih dan formulir edit atribut.
5. `apps/web/app/components/dataset/infrastruktur-segmen/BottomPanel.vue` (894 baris) - Panel tabel atribut bawah dengan pencarian, chip filter aktif, paginasi, dan aksi baris.
6. `apps/web/app/components/dataset/infrastruktur-segmen/DialogFilter.vue` (260 baris) & `FilterContent.vue` (522 baris) - Modal dan isi formulir filter lanjutan.
7. `apps/web/app/components/common/WilayahSelector.vue` (182 baris) - Komponen pemilih Kecamatan dan Desa berbasis hak akses role.
8. `apps/web/app/types/infrastruktur.ts` & `apps/web/app/types/dataset-editor.ts` - Definisi tipe TypeScript untuk segmen dan editor dataset.

---

### 1.2 Daftar Temuan Audit (Bug Logika, Performa, dan UI/UX)

Berikut adalah daftar masalah konkret yang ditemukan pada kode saat ini beserta lokasi barisnya:

#### A. Masalah Performa: Dua Instance `<MapCanvas>` Dirender Bersamaan di DOM
- **Lokasi**: `apps/web/app/pages/admin/infrastruktur-segmen/index.vue` baris `1755-1827` (Desktop `hidden lg:flex`) dan baris `1884-1929` (Mobile `lg:hidden`).
- **Masalah**: Penggunaan `hidden lg:flex` dan `lg:hidden` hanya menyembunyikan elemen secara CSS (`display: none`), tetapi Vue tetap melakukan `mount` pada **kedua** komponen `<MapCanvas>` secara bersamaan. Akibatnya, OpenLayers menginisialisasi dua instance peta, membuat request tile MVT dua kali lipat, dan berpotensi menyebabkan konflik referensi `mapCanvasRef`.
- **Solusi**: Gunakan state reaktif `isDesktop` berbasis `useMediaQuery('(min-width: 1024px)')` dari `@vueuse/core` (atau `window.matchMedia`) dengan direktif `v-if="isDesktop"` dan `v-else` agar hanya **satu** `<MapCanvas>` yang hidup di memori pada satu waktu.

#### B. Bug Perhitungan Jumlah Filter Aktif (`else if` pada `filterTahunPembangunan` dan `filterTipe`)
- **Lokasi**:
  - `apps/web/app/pages/admin/infrastruktur-segmen/index.vue` baris `326-327` dan `431-432`
  - `apps/web/app/components/dataset/infrastruktur-segmen/LeftPanel.vue` baris `156-157`
  - `apps/web/app/components/dataset/infrastruktur-segmen/BottomPanel.vue` baris `126-127`
  - `apps/web/app/components/dataset/infrastruktur-segmen/FilterContent.vue` baris `245-246`
- **Masalah**: Kode saat ini menulis:
  ```ts
  if (filterTahunPembangunan.value) count++;
  else if (filterTipe.value) count++;
  ```
  Karena menggunakan `else if`, jika pengguna memilih **Tahun Pembangunan** sekaligus **Tipe Infrastruktur**, maka `filterTipe` tidak dihitung! Selain itu, pada `LeftPanel.vue` (baris `152-165`) dan `BottomPanel.vue` (baris `122-135`), filter `filterPerkerasan` (`selectedPerkerasan`) terlewat dan tidak ikut dihitung sama sekali.
- **Solusi**: Ubah `else if` menjadi `if` terpisah di semua lokasi, dan tambahkan pengecekan `selectedPerkerasan` pada `LeftPanel.vue` serta `BottomPanel.vue`.

#### C. Bug Pemanggilan Ganda `loadTable()` Saat Refresh
- **Lokasi**: `apps/web/app/pages/admin/infrastruktur-segmen/index.vue` baris `801-806`.
- **Masalah**:
  ```ts
  if (bottomPanelOpen.value) {
    tasks.push(loadTable());
  } else {
    loadTable();
  }
  ```
  `loadTable()` dipanggil baik ketika `bottomPanelOpen` bernilai `true` maupun `false`, dan pada baris `1478` (`onMounted`), `loadTable()` dipanggil lagi secara eksplisit setelah `refreshAll(true)`. Hal ini memicu dua request `/api/v1/infrastruktur` yang identik saat halaman pertama kali dibuka.
- **Solusi**: Hapus pemanggilan `loadTable()` duplikat di `onMounted` karena `refreshAll(true)` sudah memanggil `loadTable()`.

#### D. Ketidaksinkronan Opsi `Jenis Perkerasan` dan `Sumber Dana` Antara Form dan Filter
- **Lokasi**:
  - `RightPanel.vue` baris `152` & `index.vue` baris `2627`: menggunakan opsi `['Beton Cor', 'Aspal', 'Paving', 'Makadam', 'Tanah', 'Lainnya']`.
  - `FilterContent.vue` baris `190`: menggunakan opsi `['Aspal', 'Beton', 'Paving', 'Kerikil', 'Tanah']`.
- **Masalah**: Ketika operator menyimpan segmen dengan jenis perkerasan `Beton Cor` atau `Makadam`, segmen tersebut tidak akan pernah ditemukan saat pengguna memfilter dengan opsi `Beton` atau `Kerikil` di Dialog Filter.
- **Solusi**: Buat satu konstanta terpusat di `apps/web/app/types/infrastruktur.ts` yang memuat nilai standar yang didukung penuh oleh database dan API.

#### E. Pelanggaran Tipografi (`font-mono`) dan Aksesibilitas Tombol
- **Lokasi**: Tersebar di `index.vue`, `LeftPanel.vue`, `RightPanel.vue`, `BottomPanel.vue`, `DialogFilter.vue`, dan `SimbologyContent.vue`.
- **Masalah**: Penggunaan class `font-mono` pada badge angka, persentase, panjang meter, koordinat, dan atribut tabel melanggar aturan `DESIGN.md` Bagian 3.2 dan `DESIGN_ADMIN.md` Bagian 5.B. Banyak tombol ikon kecil (`size-6`, `size-7`) tidak memiliki atribut `aria-label`.
- **Solusi**: Ganti `font-mono` dengan `tabular-nums` dan tambahkan `aria-label` deskriptif pada seluruh tombol ikon.

---

## 2. Spesifikasi UI/UX Panel Kiri, Panel Kanan, dan Panel Bottom

### 2.1 Panel Kiri (`apps/web/app/components/dataset/infrastruktur-segmen/LeftPanel.vue`)

#### Masalah Saat Ini pada Panel Kiri:
1. **Duplikasi Filter Wilayah yang Tidak Sinkron (`baris 460-509`)**: Kartu "Wilayah Kerja" di dalam `LeftPanel.vue` menduplikasi `WilayahSelector` yang sudah ada tepat di atasnya pada Topbar (`index.vue` baris `1560`). Lebih buruk lagi, `WilayahSelector` di `LeftPanel.vue` memakai `:show-labels="false"`, sehingga saat Kecamatan/Desa terpilih, pengguna tidak melihat label "Kecamatan" / "Desa", dan perubahannya hanya mengubah filter layer `Segmen Fisik` tanpa menyinkronkan layer `Jalan Poros Desa`.
2. **Kontrol Drag Palsu (`baris 538`)**: Ikon `i-lucide-grip-vertical` memiliki class `cursor-grab active:cursor-grabbing` dan `title="Urutan Layer"`, tetapi tidak ada logika drag-and-drop sama sekali. Ini melanggar aturan anti-slop R-26 (No Fake Controls).
3. **Tombol Toolbar Ke-7 yang Mati Permanen (`baris 711-722`)**: Komentar pada baris `605` menulis `2. Action Toolbar (6 Icons)`, tetapi kode merender 7 kolom tombol, di mana tombol ke-7 (`i-lucide-lock` / `i-lucide-database`) selalu `disabled`.
4. **Data `kondisiStats` Tidak Ditampilkan di Legenda (`baris 49, 195, 799-818`)**: `LeftPanel.vue` menerima prop `kondisiStats` dari `index.vue`, tetapi daftar rincian legenda kondisi (Baik, Sedang, Rusak Ringan, Rusak Berat) hanya menampilkan titik warna dan teks label statis tanpa menampilkan jumlah segmen atau persentase dari `kondisiStats`.

#### Rancangan Perbaikan UI/UX Panel Kiri:
1. **Header & Ringkasan Wilayah Kontekstual**:
   - Ganti dropdown `WilayahSelector` ganda di dalam `LeftPanel.vue` menjadi **Kartu Ringkasan Wilayah Aktif** yang ringkas (menampilkan nama Kecamatan & Desa yang sedang aktif, badge jumlah segmen di wilayah tersebut, serta tombol pintas "Ubah Wilayah" yang membuka/memfokuskan filter wilayah atau tombol "Reset Wilayah" jika sedang difilter).
   - Pada mode mobile (`MobileBottomSheet.vue`), karena Topbar wilayah disembunyikan di layar sempit (`hidden md:flex`), tampilkan `WilayahSelector` dengan `:show-labels="true"` melalui prop `showWilayahSelector?: boolean`.
2. **Kartu Layer (Layer Stack Card)**:
   - Hapus ikon `i-lucide-grip-vertical` palsu. Ganti dengan indikator pemilihan layer yang jelas: klik pada header kartu layer memilih layer tersebut sebagai `activeLayerId`.
   - Batasi badge pada header kartu layer agar tidak penuh sesak di lebar `300px`: cukup tampilkan nama layer, jumlah fitur (`tabular-nums`), dan toggle switch visibilitas (`USwitch`) dengan `aria-label="Tampilkan atau sembunyikan layer"`.
3. **Action Toolbar Layer (6 Tombol Fungsional)**:
   - Hapus tombol ke-7 yang mati permanen. Gunakan grid 6 kolom (`grid-cols-6`) untuk 6 aksi yang benar-benar berfungsi:
     1. **Buka Tabel Atribut** (`i-lucide-table`) - aktif/biru jika tabel terbuka untuk layer tersebut.
     2. **Simbologi & Warna** (`i-lucide-palette`) - membuka dialog simbologi.
     3. **Filter Layer** (`i-lucide-filter`) - membuka dialog filter + menampilkan badge titik/angka jika ada filter aktif.
     4. **Zoom ke Cakupan Layer** (`i-lucide-scan` / `i-lucide-maximize-2`) - memanggil `zoomToLayer`.
     5. **Pengaturan Opasitas** (`i-lucide-sliders-horizontal`) - membuka drawer slider opasitas inline.
     6. **Informasi & Metadata Layer** (`i-lucide-info`) - membuka drawer informasi sumber data & sistem koordinat (status baca-saja/editable ditampilkan di dalam drawer info ini, bukan sebagai tombol mati).
4. **Legenda Kondisi Dinamis**:
   - Pada bagian rincian legenda untuk mode `kondisi`, tampilkan jumlah segmen dan panjang (km) di sebelah kanan setiap label kondisi (`Baik`, `Sedang`, `Rusak Ringan`, `Rusak Berat`) menggunakan `tabular-nums` dari prop `kondisiStats`.
5. **Tipografi**:
   - Ganti seluruh class `font-mono` di `LeftPanel.vue` (baris `451, 567, 577, 581, 600, 681, 740, 767`) menjadi `tabular-nums`.

---

### 2.2 Panel Kanan (`apps/web/app/components/dataset/infrastruktur-segmen/RightPanel.vue`)

#### Masalah Saat Ini pada Panel Kanan:
1. **Redundansi Tombol "Edit" dan Subtab (`baris 318-393`)**: Baris pertama toolbar memiliki tombol "Edit" (baris `339`) yang mengubah `activeTab` antara `'inspector'` dan `'edit'`, sedangkan tepat di bawahnya (baris `374-392`) sudah ada subtab `Informasi Teknis` dan `Edit Formulir` yang melakukan hal yang persis sama.
2. **Izin `canSplit` Disamakan dengan `canEdit` (`baris 349`)**: Tombol "Split Segmen" muncul selama `canEdit` bernilai `true`. Padahal pemotongan segmen hanya valid jika geometri segmen sudah tersimpan dan pengguna memiliki izin edit geometri pada segmen tersebut.
3. **Keterbacaan Tabel Key-Value Buruk (`baris 478-504`)**:
   - Kolom key dan value menggunakan `font-mono` dan `break-all`. Class `break-all` memotong kata bahasa Indonesia di tengah suku kata (contoh: `Perkerasan` terpotong menjadi `Perkeras` dan `an`).
   - ID segmen ditampilkan terpotong `ID: 9a8b7c6d...` tanpa tombol salin (copy).
4. **Formulir Edit Kurang Aman (`baris 564-709`)**:
   - Tidak ada tombol "Batal" di bagian bawah formulir untuk membuang perubahan yang belum disimpan dan kembali ke tab `Informasi Teknis`.
   - Opsi `jenis_perkerasan` (`baris 152`) tidak sinkron dengan filter, dan field `sumber_dana` (`baris 671`) masih berupa input teks bebas (`UInput`) sehingga rentan salah ketik (`apbd`, `APBD Kab`, dll.), padahal di filter menggunakan daftar pilihan standar.

#### Rancangan Perbaikan UI/UX Panel Kanan:
1. **Header & Navigasi Tab yang Bersih**:
   - Pertahankan 2 subtab utama yang jelas di bagian atas: **Informasi Teknis** dan **Edit Atribut** (tab kedua hanya aktif/tampil jika `canEdit` bernilai `true`).
   - Kelompokkan tombol aksi spasial ke dalam satu baris ringkas di bawah subtab:
     - **Zoom Segmen** (`i-lucide-LocateFixed` / `i-lucide-maximize-2`)
     - **Edit Geometri** (`i-lucide-waypoints`, tampil jika `canEdit`)
     - **Potong Segmen / Split** (`i-lucide-scissors`, tampil jika `canSplit`)
     - **Hapus Segmen** (`i-lucide-trash-2`, warna `error`, tampil jika `canDelete`)
   - Untuk segmen berstatus `draft` atau `rejected` (ketika `canSubmit` bernilai `true`), tampilkan banner/tombol aksi utama **Ajukan Verifikasi** (`i-lucide-send`) dengan penjelasan singkat.
2. **Kartu Ringkasan & Tabel Atribut (`Informasi Teknis`)**:
   - Gunakan `tabular-nums` (bukan `font-mono`) untuk nilai Panjang (`m`), Lebar (`m`), Tahun Pembangunan, Koordinat, dan kode segmen.
   - Ganti `break-all` menjadi `break-words` pada sel tabel nilai atribut agar kata tidak terpotong di tengah huruf.
   - Tambahkan tombol **Salin Kode/ID** (`i-lucide-copy` -> berubah menjadi `i-lucide-check` selama 1.5 detik) di samping Kode Segmen / ID.
3. **Formulir Edit Atribut (`Edit Atribut`)**:
   - Gunakan konstanta opsi terpusat (`PERKERASAN_OPTIONS`, `KONDISI_OPTIONS`, `STATUS_JALAN_OPTIONS`, `SUMBER_DANA_OPTIONS`, `STATUS_ASET_OPTIONS`) dari `~/types/infrastruktur`.
   - Ubah input `sumber_dana` dari `UInput` teks bebas menjadi `USelectMenu` menggunakan `SUMBER_DANA_OPTIONS` agar data konsisten dengan filter.
   - Tambahkan footer aksi formulir dengan 2 tombol: **Batal** (mengembalikan nilai form ke data `selectedSegmen` awal dan berpindah ke tab `Informasi Teknis`) dan **Simpan Perubahan** (tombol `primary` dengan status `:loading="editSaving"`).

---

### 2.3 Panel Bottom (`apps/web/app/components/dataset/infrastruktur-segmen/BottomPanel.vue`)

#### Masalah Saat Ini pada Panel Bottom:
1. **Tidak Ada State Error (`R-27 Violation`)**: `BottomPanel.vue` hanya menangani `tableLoading` (baris `693`) dan `tableRows.length === 0` (baris `699`), tetapi tidak memiliki prop `tableError` maupun tampilan gagal muat dengan tombol "Coba Lagi".
2. **Empty State Tanpa Tombol Pemulihan (`baris 699-705`)**: Saat hasil pencarian atau filter kosong (`0` baris), panel hanya menampilkan teks `"Tidak ada data ditemukan untuk layer ini"` tanpa tombol "Reset Filter" atau "Hapus Pencarian".
3. **Chip Filter `Perkerasan` Hilang (`baris 491-691`)**: Meskipun pengguna memfilter berdasarkan `Jenis Perkerasan` di Dialog Filter, `BottomPanel.vue` tidak menerima prop `selectedPerkerasan` dan tidak menampilkan chip filter `Perkerasan`, sehingga pengguna tidak tahu bahwa tabel sedang terfilter oleh perkerasan.
4. **Bug Aksi "Edit" pada Baris Tabel (`baris 810-816` & `index.vue` baris `1873`)**:
   - Di `index.vue` baris `1873`, event `@edit-row="handleZoomToFeature"` dipetakan ke fungsi yang sama dengan `@select-row` dan `@zoom-row`. Akibatnya, saat pengguna mengklik ikon pensil (`Edit`) di baris tabel, Panel Kanan terbuka di tab `inspector` (baca-saja), bukan tab `edit`!
   - Selain itu, visibilitas tombol Edit, Ajukan Verifikasi, dan Hapus di setiap baris tabel hanya mengecek boolean global (`canEdit`, `canSubmit`, `canDelete`), bukan izin per baris (`canEditSegmen(row)`, `canDeleteSegmen(row)`).

#### Rancangan Perbaikan UI/UX Panel Bottom:
1. **Implementasi 3 State Data Lengkap (`Loading`, `Empty`, `Error`)**:
   - Tambahkan prop `tableError?: string | null` pada `BottomPanel.vue`.
   - **State Loading**: Tampilkan skeleton baris tabel yang rapi (5 baris skeleton).
   - **State Error**: Jika `tableError` berisi pesan, tampilkan ikon `i-lucide-alert-circle`, pesan error yang jelas, dan tombol **Coba Lagi** (`@click="emit('refresh')"`).
   - **State Empty**: Jika `tableRows.length === 0`, tampilkan ikon `i-lucide-search-x`, keterangan penyebab (apakah karena filter/pencarian aktif atau memang belum ada data di wilayah tersebut), serta tombol **Atur Ulang Filter & Pencarian** jika `hasActiveFilters || searchInput` bernilai `true`.
2. **Kelengkapan Chip Filter Aktif**:
   - Tambahkan prop `selectedPerkerasan?: string | null` dan `emit('update:selectedPerkerasan', null)` pada `BottomPanel.vue`.
   - Tampilkan chip `Perkerasan: <nilai>` lengkap dengan tombol silang (`x`) untuk menghapus filter tersebut secara langsung dari bar tabel.
   - Perbaiki perhitungan `activeSegmenFilterCount` (`baris 126-127`) agar tidak menggunakan `else if` dan menyertakan `selectedPerkerasan`.
3. **Aksi Baris Tabel yang Presisi**:
   - Buat handler khusus `handleEditFeatureFromTable(row)` di `index.vue` untuk event `@edit-row`: fungsi ini memilih segmen, melakukan zoom ke segmen, membuka `RightPanel`, dan langsung mengaktifkan tab `edit`.
   - Gunakan fungsi evaluasi izin per baris atau teruskan helper check agar tombol Edit/Hapus hanya muncul pada baris yang memang diizinkan untuk pengguna tersebut (dan status verifikasi baris mendukung, misalnya segmen `verified_bappeda` tidak menampilkan tombol Hapus untuk operator desa).
   - Ganti seluruh `font-mono` pada sel angka (`Panjang`, `Lebar`, `Tahun`, nomor urut, dan paginasi) menjadi `tabular-nums`.

---

## 3. Spesifikasi UI/UX Dialog Filter

### 3.1 Audit Komponen Filter Saat Ini
1. **File Duplikat yang Tidak Terpakai (Dead Code)**:
   - `apps/web/app/components/dataset/infrastruktur-segmen/DialogFilterSegmen.vue`
   - `apps/web/app/components/dataset/infrastruktur-segmen/DialogFilterJalanPoros.vue`
   - Kedua file ini tidak pernah diimpor di mana pun karena halaman menggunakan `DialogFilter.vue` + `FilterContent.vue`. Keberadaan kedua file ini membingungkan programmer baru. **Tindakan**: Hapus kedua file tersebut atau konsolidasikan sepenuhnya ke dalam `DialogFilter.vue` dan `FilterContent.vue`.
2. **Bug UX Kritis: Perubahan Filter Langsung Memicu Reload Sebelum Klik "Terapkan Filter"**:
   - Di `index.vue` (baris `2495-2520`), `DialogFilter` terikat langsung menggunakan `v-model:selected-*` ke state utama (`filterTipe`, `filterKondisi`, `filterStatusVerifikasi`, dll.).
   - Karena `index.vue` baris `850` memiliki `watch([filterTipe, filterKondisi, ...], () => { syncQueryToUrl(); refreshAll(true); })`, setiap kali pengguna memilih satu item di dropdown dalam modal filter, peta dan tabel di belakang modal **langsung memuat ulang dan melakukan `fitBounds`**, bahkan sebelum pengguna selesai memilih filter lainnya atau menekan tombol **"Terapkan Filter"**!
   - Lebih buruk lagi, tombol **"Tutup"** di footer modal tidak membatalkan perubahan yang sudah telanjur terjadi, dan tombol **"Terapkan Filter"** justru memicu `refreshAll(true)` untuk kedua kalinya.
3. **Bug UI Kritis: Field `Tipe Infrastruktur` Hilang dari Template `FilterContent.vue`!**
   - Di `FilterContent.vue`, prop `tipeList` (baris `8`), `selectedTipe` (baris `9`), `tipeSelectItems` (baris `91-95`), dan `handleTipeChange` (baris `160-166`) sudah didefinisikan di `<script setup>`, bahkan dihitung di `activeFiltersCount` (baris `246`), **tetapi elemen `<USelectMenu>` untuk Tipe Infrastruktur lupa ditulis di dalam `<template>`** (baris `302-450`)! Akibatnya, pengguna sama sekali tidak bisa memfilter berdasarkan Tipe Infrastruktur dari Dialog Filter.

### 3.2 Rancangan Perbaikan UI/UX Dialog Filter
1. **Pola Staged / Draft Filter State**:
   - Saat `DialogFilter.vue` dibuka (`watch(() => props.open, (isOpen) => ...)`), salin nilai seluruh filter aktif dari parent ke dalam objek state lokal `draftFilters` di dalam `DialogFilter.vue`.
   - Selama pengguna mengubah pilihan di dalam modal (`FilterContent.vue`), perubahan hanya memperbarui `draftFilters` (tanpa memicu request API di `index.vue`).
   - Badge jumlah filter aktif di header modal dan di tab layer (`Segmen Fisik` vs `Jalan Poros Desa`) dihitung secara reaktif dari `draftFilters`.
   - Ketika pengguna menekan tombol **"Terapkan Filter"**:
     - Emit seluruh nilai `draftFilters` ke `index.vue` (atau panggil satu event `apply(draftFilters)`), tutup modal, lalu `index.vue` menjalankan `syncQueryToUrl()`, `refreshAll(true)`, dan `fitAllBounds()` tepat **satu kali**.
   - Ketika pengguna menekan tombol **"Tutup"** (atau klik backdrop/ESC):
     - Tutup modal begitu saja; perubahan pada `draftFilters` dibuang sehingga state filter di halaman tidak berubah.
   - Ketika pengguna menekan tombol **"Atur Ulang"** (Reset):
     - Kosongkan `draftFilters` pada layer yang sedang dipilih (atau semua layer), dan tampilkan indikator bahwa filter telah direset (pengguna tinggal klik "Terapkan Filter", atau sediakan opsi langsung terapkan reset).
2. **Menampilkan Kembali Field `Tipe Infrastruktur` di `FilterContent.vue`**:
   - Pada tab filter `Segmen Fisik`, tambahkan field **Tipe Infrastruktur** menggunakan `<USelectMenu>` yang terhubung ke `tipeSelectItems` dan `handleTipeChange`, ditempatkan di urutan pertama pada bagian "Parameter Teknis Segmen" (sebelum Kondisi Fisik dan Jenis Perkerasan).
3. **Tata Letak & Kemudahan Pemindaian Modal (`DialogFilter.vue` & `FilterContent.vue`)**:
   - Gunakan pengelompokan 3 bagian yang bersih pada `FilterContent.vue`:
     1. **Cakupan Wilayah Administratif** (`WilayahSelector` untuk Kecamatan & Desa).
     2. **Parameter Teknis & Fisik** (Grid 2 kolom pada layar `sm:grid-cols-2`: Tipe Infrastruktur, Kondisi Fisik, Jenis Perkerasan, Tahun Pembangunan).
     3. **Status Tata Kelola & Pendanaan** (Grid 2 kolom: Status Verifikasi, Status Aset, Sumber Dana).
   - Pada setiap field yang sedang memiliki nilai aktif (bukan "Semua"), tampilkan border aksen halus (`ring-1 ring-blue-500/30 bg-blue-50/20`) dan tombol `x` kecil di labelnya untuk menghapus satu field tersebut dengan cepat.

---

## 4. Spesifikasi UI/UX Filter Wilayah

### 4.1 Audit Masalah Filter Wilayah Saat Ini
1. **Bug Penguncian Role (`isKecamatanLocked` / `isDesaLocked`) pada `WilayahSelector.vue`**:
   - Lokasi: `apps/web/app/components/common/WilayahSelector.vue` baris `51-62`.
   - Masalah: `WilayahSelector.vue` mengecek `auth.hasRole('admin') || auth.hasRole('verifierBappeda')`. Padahal di `index.vue` (baris `73`), sistem izin menggunakan `const { isAdmin } = usePermission()` yang menangani berbagai variasi role administrator (seperti `superadmin`, `admin`, dll.). Jika seorang pengguna memiliki role `superadmin` yang terdeteksi oleh `isAdmin()` tetapi string rolenya bukan `'admin'`, maka `WilayahSelector` dapat salah mengunci pilihan wilayahnya.
2. **Desinkronisasi Wilayah Antara Layer `Segmen Fisik` dan `Jalan Poros Desa`**:
   - Lokasi: `apps/web/app/pages/admin/infrastruktur-segmen/index.vue` baris `268-269` (`filterKecamatan`, `filterDesa`) dan baris `278-279` (`filterJalanKecamatan`, `filterJalanDesa`).
   - Masalah: Topbar (`index.vue` baris `1560-1565`) dan `LeftPanel.vue` (`baris 500-505`) hanya mengikat `v-model:kecamatan="filterKecamatan"` dan `v-model:desa="filterDesa"`. Akibatnya:
     - Saat pengguna memilih **Kecamatan Bojonegoro** di Topbar, hanya layer `Segmen Fisik` yang difilter ke Kecamatan Bojonegoro, sedangkan layer referensi `Jalan Poros Desa` tetap menampilkan jalan dari seluruh kabupaten!
     - Saat pengguna beralih ke tab `Jalan Poros Desa` di Dialog Filter, pilihan Kecamatan/Desa di sana berbeda dengan yang ada di Topbar.
3. **Penanganan Nilai `null` ("Semua Kecamatan" / "Semua Desa") pada `USelectMenu`**:
   - Lokasi: `apps/web/app/components/common/WilayahSelector.vue` baris `68` dan `79`.
   - Masalah: Item pertama untuk opsi semua wilayah menggunakan `{ id: null as any, label: 'Semua Kecamatan' }`. Pada Nuxt UI `<USelectMenu value-key="id">`, nilai `id: null` sering dianggap sebagai "belum ada nilai yang dipilih" sehingga komponen hanya menampilkan teks placeholder abu-abu dan pengguna kesulitan memilih kembali "Semua Kecamatan" setelah memilih satu kecamatan, kecuali menggunakan nilai sentinel string seperti `'ALL'` di internal komponen yang dikonversi menjadi `null` saat `emit`.
4. **Kurangnya Umpan Balik Visual Wilayah Terkunci & Tombol Reset Cepat**:
   - Untuk **Operator Desa** (terkunci pada 1 Kecamatan & 1 Desa) dan **Verifikator Kecamatan** (terkunci pada 1 Kecamatan, bisa memilih Desa di dalam kecamatannya), dropdown yang `disabled` hanya terlihat abu-abu tanpa ikon gembok (`i-lucide-lock`) atau badge penjelas mengapa pilihan tersebut dikunci.
   - Untuk **Admin / BAPPEDA**, ketika sudah memilih Kecamatan dan Desa di Topbar, tidak ada tombol 1-klik (`i-lucide-x`) di samping selector Topbar untuk kembali ke cakupan "Semua Wilayah (Kabupaten)" dengan cepat.

### 4.2 Rancangan Perbaikan UI/UX Filter Wilayah
1. **Satu Sumber Kebenaran Wilayah Kerja (Unified Territory Filter)**:
   - Jadikan `filterKecamatan` dan `filterDesa` sebagai **Wilayah Kerja Global** halaman `/admin/infrastruktur-segmen`.
   - Saat `filterKecamatan` atau `filterDesa` berubah (baik dari Topbar maupun dari Dialog Filter), sinkronkan nilainya ke `filterJalanKecamatan` dan `filterJalanDesa` secara otomatis agar layer `Segmen Fisik` dan `Jalan Poros Desa` selalu menampilkan wilayah administratif yang sama.
   - Setiap kali wilayah kerja berubah, panggil `fitAllBounds()` setelah data dimuat agar kamera peta otomatis bergerak membingkai (auto-zoom) ke Kecamatan atau Desa yang dipilih.
2. **Perbaikan Komponen `WilayahSelector.vue`**:
   - Gunakan `usePermission()` (`isAdmin()`) bersama `auth.hasRole('verifierBappeda')` untuk menentukan apakah akses wilayah terbuka penuh.
   - Gunakan konstanta sentinel internal `const ALL_VALUE = '__ALL__'` untuk item `'Semua Kecamatan'` dan `'Semua Desa'` pada `<USelectMenu>`, lalu konversikan `'__ALL__'` menjadi `null` pada getter/setter `computed` (`modelKecamatan` dan `modelDesa`). Dengan cara ini, pengguna selalu dapat memilih kembali "Semua Kecamatan" / "Semua Desa" dengan mulus di Nuxt UI.
   - Tambahkan ikon pendahulu (leading icon): `i-lucide-map-pin` untuk Kecamatan (atau `i-lucide-lock` jika `isKecamatanLocked`) dan `i-lucide-building-2` untuk Desa (atau `i-lucide-lock` jika `isDesaLocked`).
   - Tambahkan tombol **Reset Wilayah** (`UButton` dengan ikon `i-lucide-x`, `aria-label="Reset filter wilayah"`) yang muncul ketika Kecamatan (jika tidak terkunci) atau Desa (jika tidak terkunci) sedang dipilih.
   - Pastikan tinggi kontrol minimal `min-h-[44px] sm:min-h-[34px]` agar nyaman disentuh di layar mobile maupun kompak di topbar desktop.

---

## 5. Tahapan Implementasi Step-by-Step (Panduan untuk Junior Programmer / AI Model)

Ikuti 8 tahapan di bawah ini secara berurutan. Jangan melompati tahapan karena tahapan berikutnya bergantung pada tipe dan komponen dari tahapan sebelumnya.

---

### Tahap 1: Standarisasi Konstanta Opsi di `apps/web/app/types/infrastruktur.ts`

**Tujuan**: Menghilangkan perbedaan nilai dropdown antara formulir buat/edit segmen (`RightPanel.vue`, `index.vue`) dan filter (`FilterContent.vue`).

**File yang diubah**: `apps/web/app/types/infrastruktur.ts`

**Langkah 1.1**: Buka `apps/web/app/types/infrastruktur.ts` dan tambahkan konstanta standar berikut di bagian bawah file:

```ts
export interface SelectOptionItem {
  label: string;
  value: string;
}

export const KONDISI_OPTIONS: SelectOptionItem[] = [
  { label: 'Baik', value: 'baik' },
  { label: 'Sedang', value: 'sedang' },
  { label: 'Rusak Ringan', value: 'rusak_ringan' },
  { label: 'Rusak Berat', value: 'rusak_berat' },
];

export const PERKERASAN_OPTIONS: SelectOptionItem[] = [
  { label: 'Aspal', value: 'Aspal' },
  { label: 'Beton Cor', value: 'Beton Cor' },
  { label: 'Beton', value: 'Beton' },
  { label: 'Paving', value: 'Paving' },
  { label: 'Makadam', value: 'Makadam' },
  { label: 'Kerikil', value: 'Kerikil' },
  { label: 'Tanah', value: 'Tanah' },
  { label: 'Lainnya', value: 'Lainnya' },
];

export const STATUS_JALAN_OPTIONS: SelectOptionItem[] = [
  { label: 'Jalan Desa', value: 'Jalan Desa' },
  { label: 'Jalan Poros Desa', value: 'Jalan Poros Desa' },
  { label: 'Jalan Lingkungan', value: 'Jalan Lingkungan' },
  { label: 'Jalan Kabupaten', value: 'Jalan Kabupaten' },
  { label: 'Lainnya', value: 'Lainnya' },
];

export const SUMBER_DANA_OPTIONS: SelectOptionItem[] = [
  { label: 'APBD', value: 'APBD' },
  { label: 'BKK (Bantuan Keuangan Khusus)', value: 'BKK' },
  { label: 'DD (Dana Desa)', value: 'DD' },
  { label: 'Sektoral', value: 'Sektoral' },
  { label: 'Swadaya', value: 'Swadaya' },
  { label: 'Lainnya', value: 'Lainnya' },
];

export const STATUS_VERIFIKASI_OPTIONS: SelectOptionItem[] = [
  { label: 'Draft', value: 'draft' },
  { label: 'Menunggu Verifikasi', value: 'submitted' },
  { label: 'Terverifikasi Kecamatan', value: 'verified_kec' },
  { label: 'Terverifikasi Bappeda', value: 'verified_bappeda' },
  { label: 'Ditolak', value: 'rejected' },
];

export const STATUS_ASET_OPTIONS: SelectOptionItem[] = [
  { label: 'Draft', value: 'draft' },
  { label: 'Diajukan', value: 'submitted' },
  { label: 'Terverifikasi Kecamatan', value: 'verified_kecamatan' },
  { label: 'Terverifikasi Bappeda', value: 'verified_bappeda' },
  { label: 'Aktif', value: 'active' },
  { label: 'Arsip', value: 'archived' },
  { label: 'Ditolak', value: 'rejected' },
];
```

---

### Tahap 2: Perbaikan Komponen `WilayahSelector.vue`

**Tujuan**: Memperbaiki bug penguncian role (`isAdmin`), memperbaiki pemilihan opsi "Semua Kecamatan/Desa" pada `<USelectMenu>`, menambahkan ikon indikator, dan menambahkan tombol Reset wilayah.

**File yang diubah**: `apps/web/app/components/common/WilayahSelector.vue`

**Langkah 2.1**: Perbarui `<script setup>` pada `apps/web/app/components/common/WilayahSelector.vue`:
- Impor `usePermission` dan gunakan `const { isAdmin } = usePermission()`.
- Gunakan nilai sentinel `const ALL_OPTION_ID = '__ALL__'` untuk item pertama saat `allowAll` bernilai `true`.
- Konversikan `ALL_OPTION_ID` ke `null` saat `emit('update:kecamatan', ...)` dan `emit('update:desa', ...)`.
- Tambahkan computed `canResetWilayah` dan fungsi `handleResetWilayah()`:

```ts
const ALL_OPTION_ID = '__ALL__';

const isKecamatanLocked = computed(() => {
  if (props.disabled) return true;
  if (isAdmin() || auth.hasRole('verifierBappeda')) return false;
  return !!auth.user?.kecamatan_id;
});

const isDesaLocked = computed(() => {
  if (props.disabled) return true;
  if (isAdmin() || auth.hasRole('verifierBappeda') || auth.hasRole('verifierKecamatan')) {
    return false;
  }
  return !!auth.user?.desa_id;
});

const selectedKecamatanModel = computed({
  get: () => modelKecamatan.value ?? (props.allowAll ? ALL_OPTION_ID : undefined),
  set: (val: string | undefined) => {
    const normalized = !val || val === ALL_OPTION_ID ? null : val;
    modelKecamatan.value = normalized;
  },
});

const selectedDesaModel = computed({
  get: () => modelDesa.value ?? (props.allowAll ? ALL_OPTION_ID : undefined),
  set: (val: string | undefined) => {
    const normalized = !val || val === ALL_OPTION_ID ? null : val;
    modelDesa.value = normalized;
  },
});

const canResetWilayah = computed(() => {
  if (!props.allowAll || props.disabled) return false;
  if (!isKecamatanLocked.value && modelKecamatan.value) return true;
  if (!isDesaLocked.value && modelDesa.value) return true;
  return false;
});

function handleResetWilayah() {
  if (!isKecamatanLocked.value) {
    modelKecamatan.value = null;
    modelDesa.value = null;
  } else if (!isDesaLocked.value) {
    modelDesa.value = null;
  }
}
```

**Langkah 2.2**: Pada `kecamatanItems` dan `desaItems` di `WilayahSelector.vue`, ganti `{ id: null as any, label: 'Semua Kecamatan' }` menjadi `{ id: ALL_OPTION_ID, label: 'Semua Kecamatan' }` dan `{ id: ALL_OPTION_ID, label: 'Semua Desa' }`.

**Langkah 2.3**: Pada `<template>` `WilayahSelector.vue`:
- Ikat `<USelectMenu>` ke `v-model="selectedKecamatanModel"` dan `v-model="selectedDesaModel"`.
- Tambahkan prop `:icon="isKecamatanLocked ? 'i-lucide-lock' : 'i-lucide-map-pin'"` pada `<USelectMenu>` Kecamatan, dan `:icon="isDesaLocked ? 'i-lucide-lock' : 'i-lucide-building-2'"` pada `<USelectMenu>` Desa.
- Tambahkan tombol Reset di sebelah kanan dropdown Desa ketika `canResetWilayah` bernilai `true`:
  ```vue
  <UButton
    v-if="canResetWilayah"
    icon="i-lucide-x"
    color="neutral"
    variant="subtle"
    :size="size"
    aria-label="Atur ulang filter wilayah"
    title="Atur ulang filter wilayah"
    class="shrink-0 cursor-pointer"
    @click="handleResetWilayah"
  />
  ```

---

### Tahap 3: Refactor `FilterContent.vue` & `DialogFilter.vue` (Draft State & Field Tipe Infrastruktur)

**Tujuan**:
1. Menghapus file duplikat yang tidak dipakai (`DialogFilterSegmen.vue` dan `DialogFilterJalanPoros.vue`).
2. Menambahkan field `Tipe Infrastruktur` yang hilang di `<template>` `FilterContent.vue`.
3. Memperbaiki bug `else if` pada `activeFiltersCount` di `FilterContent.vue`.
4. Mengimplementasikan **Staged/Draft Filter State** di `DialogFilter.vue` agar perubahan di dalam modal baru diterapkan saat klik **"Terapkan Filter"**.

**File yang dihapus**:
- `apps/web/app/components/dataset/infrastruktur-segmen/DialogFilterSegmen.vue`
- `apps/web/app/components/dataset/infrastruktur-segmen/DialogFilterJalanPoros.vue`

**File yang diubah**:
- `apps/web/app/components/dataset/infrastruktur-segmen/FilterContent.vue`
- `apps/web/app/components/dataset/infrastruktur-segmen/DialogFilter.vue`

**Langkah 3.1 (Perbaikan `FilterContent.vue`)**:
1. Impor `KONDISI_OPTIONS`, `PERKERASAN_OPTIONS`, `STATUS_JALAN_OPTIONS`, `SUMBER_DANA_OPTIONS`, `STATUS_VERIFIKASI_OPTIONS`, dan `STATUS_ASET_OPTIONS` dari `~/types/infrastruktur` untuk menggantikan array hardcoded di baris `182-227`.
2. Perbaiki `activeFiltersCount` pada baris `239-256` (ubah `else if (props.selectedTipe)` menjadi `if (props.selectedTipe)`):
   ```ts
   const activeFiltersCount = computed(() => {
     let count = 0;
     if (selectedFilterLayer.value === 'segmen') {
       if (props.selectedKecamatan && !props.isKecamatanLocked) count++;
       if (props.selectedDesa && !props.isDesaLocked) count++;
       if (props.selectedKondisi) count++;
       if (props.selectedPerkerasan) count++;
       if (props.selectedStatusVerifikasi) count++;
       if (props.selectedStatusAset) count++;
       if (props.selectedTahunPembangunan) count++;
       if (props.selectedTipe) count++;
       if (props.selectedSumberDana) count++;
     } else {
       if (props.selectedJalanKecamatan && !props.isKecamatanLocked) count++;
       if (props.selectedJalanDesa && !props.isDesaLocked) count++;
       if (props.selectedJalanKondisi) count++;
       if (props.selectedJalanPerkerasan) count++;
       if (props.selectedJalanStatus) count++;
       if (props.selectedJalanSumberDana) count++;
       if (props.selectedJalanTahun) count++;
     }
     return count;
   });
   ```
3. Tambahkan komponen `<USelectMenu>` untuk **Tipe Infrastruktur** di `<template>` `FilterContent.vue` tepat di awal blok `<div v-if="selectedFilterLayer === 'segmen'" class="space-y-4">` (sekitar baris `303`):
   ```vue
   <div class="space-y-1.5">
     <div class="flex items-center justify-between">
       <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
         Tipe Infrastruktur
       </label>
       <button
         v-if="selectedTipe"
         type="button"
         class="text-[11px] font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 cursor-pointer"
         @click="emit('update:selectedTipe', null)"
       >
         Reset
       </button>
     </div>
     <USelectMenu
       :model-value="tipeSelectItems.find((i) => i.value === selectedTipe) || tipeSelectItems[0]"
       :items="tipeSelectItems"
       class="w-full"
       size="sm"
       @update:model-value="handleTipeChange"
     />
   </div>
   ```

**Langkah 3.2 (Implementasi Draft State di `DialogFilter.vue`)**:
1. Di `DialogFilter.vue`, buat objek `reactive` bernama `draft` yang menampung salinan seluruh prop `selected*`:
   ```ts
   const draft = reactive({
     selectedKecamatan: props.selectedKecamatan ?? null,
     selectedDesa: props.selectedDesa ?? null,
     selectedTipe: props.selectedTipe ?? null,
     selectedKondisi: props.selectedKondisi ?? null,
     selectedPerkerasan: props.selectedPerkerasan ?? null,
     selectedStatusVerifikasi: props.selectedStatusVerifikasi ?? null,
     selectedStatusAset: props.selectedStatusAset ?? null,
     selectedTahunPembangunan: props.selectedTahunPembangunan ?? null,
     selectedSumberDana: props.selectedSumberDana ?? null,
     selectedJalanKecamatan: props.selectedJalanKecamatan ?? null,
     selectedJalanDesa: props.selectedJalanDesa ?? null,
     selectedJalanKondisi: props.selectedJalanKondisi ?? null,
     selectedJalanPerkerasan: props.selectedJalanPerkerasan ?? null,
     selectedJalanStatus: props.selectedJalanStatus ?? null,
     selectedJalanSumberDana: props.selectedJalanSumberDana ?? null,
     selectedJalanTahun: props.selectedJalanTahun ?? null,
   });

   function syncDraftFromProps() {
     draft.selectedKecamatan = props.selectedKecamatan ?? null;
     draft.selectedDesa = props.selectedDesa ?? null;
     draft.selectedTipe = props.selectedTipe ?? null;
     draft.selectedKondisi = props.selectedKondisi ?? null;
     draft.selectedPerkerasan = props.selectedPerkerasan ?? null;
     draft.selectedStatusVerifikasi = props.selectedStatusVerifikasi ?? null;
     draft.selectedStatusAset = props.selectedStatusAset ?? null;
     draft.selectedTahunPembangunan = props.selectedTahunPembangunan ?? null;
     draft.selectedSumberDana = props.selectedSumberDana ?? null;
     draft.selectedJalanKecamatan = props.selectedJalanKecamatan ?? null;
     draft.selectedJalanDesa = props.selectedJalanDesa ?? null;
     draft.selectedJalanKondisi = props.selectedJalanKondisi ?? null;
     draft.selectedJalanPerkerasan = props.selectedJalanPerkerasan ?? null;
     draft.selectedJalanStatus = props.selectedJalanStatus ?? null;
     draft.selectedJalanSumberDana = props.selectedJalanSumberDana ?? null;
     draft.selectedJalanTahun = props.selectedJalanTahun ?? null;
   }

   watch(
     () => props.open,
     (isOpen) => {
       if (isOpen) {
         syncDraftFromProps();
       }
     },
     { immediate: true },
   );
   ```
2. Saat wilayah diubah di dalam `draft` (baik dari tab `Segmen Fisik` maupun `Jalan Poros Desa`), sinkronkan kedua pasang field wilayah di dalam `draft` agar wilayah administratif tetap satu sumber kebenaran:
   ```ts
   function handleDraftKecamatanChange(val: string | null) {
     draft.selectedKecamatan = val;
     draft.selectedJalanKecamatan = val;
   }
   function handleDraftDesaChange(val: string | null) {
     draft.selectedDesa = val;
     draft.selectedJalanDesa = val;
   }
   ```
3. Ketika tombol **"Atur Ulang"** di klik di dalam modal, reset nilai `draft` (tanpa menutup modal, atau reset `draft` untuk layer aktif).
4. Ketika tombol **"Terapkan Filter"** di klik, barulah `DialogFilter.vue` memancarkan (`emit`) seluruh perubahan dari `draft` ke parent (`index.vue`), memanggil `emit('apply')`, dan menutup modal (`emit('update:open', false)`).
5. Ganti class `font-mono` pada badge jumlah filter aktif di `DialogFilter.vue` (baris `175`) menjadi `tabular-nums`.

---

### Tahap 4: Perbaikan Panel Kiri (`LeftPanel.vue`)

**Tujuan**: Menghapus kontrol palsu, menyederhanakan bagian wilayah agar tidak menduplikasi Topbar secara membingungkan, memperbaiki perhitungan `activeSegmenFilterCount`, menampilkan statistik kondisi aktual di legenda, dan menghapus `font-mono`.

**File yang diubah**: `apps/web/app/components/dataset/infrastruktur-segmen/LeftPanel.vue`

**Langkah 4.1**: Perbaiki perhitungan `activeSegmenFilterCount` pada baris `152-165`:
```ts
const activeSegmenFilterCount = computed(() => {
  let count = 0;
  if (props.selectedKecamatan && !isKecamatanLocked.value) count++;
  if (props.selectedDesa && !isDesaLocked.value) count++;
  if (props.selectedKondisi) count++;
  if (props.selectedPerkerasan) count++;
  if (props.selectedStatusVerifikasi) count++;
  if (props.selectedStatusAset) count++;
  if (props.selectedTahunPembangunan) count++;
  if (props.selectedTipe) count++;
  if (props.selectedSumberDana) count++;
  return count;
});
```

**Langkah 4.2**: Sederhanakan kartu "Wilayah Kerja" pada `LeftPanel.vue` (baris `460-509`):
- Tambahkan prop `showInlineWilayahSelector?: boolean` (default `false` di desktop karena Topbar sudah menampilkan `WilayahSelector`, dan bernilai `true` saat dirender di dalam `MobileBottomSheet.vue`).
- Jika `showInlineWilayahSelector` bernilai `true`, render `<CommonWilayahSelector show-labels allow-all ... />`.
- Jika `showInlineWilayahSelector` bernilai `false` (tampilan desktop), tampilkan **Baris Konteks Wilayah Ringkas** yang memperlihatkan cakupan aktif (misal: `"Semua Wilayah (Kabupaten)"` atau badge wilayah terfilter) dengan tombol `"Ubah di Filter"` (`@click="openLayerFilter(activeLayerId)"`) atau tombol `"Reset"` jika wilayah sedang difilter.

**Langkah 4.3**: Hapus kontrol palsu pada Kartu Layer (`LeftPanel.vue`):
1. Hapus `<UIcon name="i-lucide-grip-vertical" ... />` pada baris `538`. Ganti dengan indikator titik atau ikon layer (`i-lucide-layers`) yang menandakan status terpilih.
2. Pada Action Toolbar (baris `606-723`), ubah grid dari `grid-cols-7` menjadi `grid-cols-6` dan **hapus tombol ke-7** (baris `711-722` yang selalu `disabled`).
3. Pindahkan informasi status layer (`Dapat Diedit` vs ` Layer Referensi Baca-Saja`) ke dalam drawer **Informasi Layer** (`openInfoLayerId === layer.id`, baris `750-776`) sebagai baris metadata kelima.
4. Tambahkan atribut `aria-label` pada ke-6 tombol toolbar layer.

**Langkah 4.4**: Tampilkan angka statistik kondisi pada Legenda Kondisi (`LeftPanel.vue` baris `799-818`):
- Buat helper di `<script setup>` untuk mengambil jumlah segmen dan panjang (km) dari `layer.kondisiStats` berdasarkan kode kondisi (`baik`, `sedang`, `rusak_ringan`, `rusak_berat`).
- Pada bagian `<div v-for="item in getLegendItems(layer)" ...>` (baris `800`), tampilkan jumlah segmen dan panjang di sisi kanan baris legenda dengan class `tabular-nums text-[11px] font-medium text-slate-600 dark:text-slate-400`.

**Langkah 4.5**: Ganti semua kemunculan `font-mono` di `LeftPanel.vue` menjadi `tabular-nums`.

---

### Tahap 5: Perbaikan Panel Kanan (`RightPanel.vue`)

**Tujuan**: Menghapus duplikasi tombol Edit vs Subtab, memisahkan prop `canSplit`, memperbaiki tabel atribut key-value (`tabular-nums`, `break-words`, tombol salin ID), dan menyempurnakan formulir edit atribut.

**File yang diubah**: `apps/web/app/components/dataset/infrastruktur-segmen/RightPanel.vue`

**Langkah 5.1**: Perbarui `Props` pada `RightPanel.vue` (baris `8-19`):
- Tambahkan prop `canSplit?: boolean` dan `initialTab?: 'inspector' | 'edit'`.
- Tambahkan watcher pada `props.initialTab` atau ekspos method `setActiveTab(tab: 'inspector' | 'edit')` menggunakan `defineExpose({ setActiveTab })` agar `index.vue` dapat membuka `RightPanel` langsung di tab `edit` saat pengguna mengklik tombol Edit di baris tabel `BottomPanel`.

```ts
interface Props {
  selectedSegmen?: Record<string, any> | null;
  segmenLoading?: boolean;
  editForm?: Record<string, any>;
  editSaving?: boolean;
  canEdit?: boolean;
  canSplit?: boolean;
  canDelete?: boolean;
  canSubmit?: boolean;
}
```

**Langkah 5.2**: Gunakan konstanta opsi dari `~/types/infrastruktur` pada `RightPanel.vue` (baris `135-153`):
- Impor `KONDISI_OPTIONS`, `PERKERASAN_OPTIONS`, `STATUS_JALAN_OPTIONS`, `SUMBER_DANA_OPTIONS`, dan `STATUS_ASET_OPTIONS` dari `~/types/infrastruktur`.
- Hapus deklarasi array lokal `kondisiOptions`, `statusJalanOptions`, `statusAsetOptions`, dan `jenisPerkerasanOptions` yang duplikat.

**Langkah 5.3**: Rapikan Action Toolbar dan Subtab di `RightPanel.vue` (baris `318-393`):
- Hapus tombol `"Edit"` di baris pertama toolbar (baris `339-348`) karena subtab `Informasi Teknis` dan `Edit Formulir` di bawahnya sudah menangani perpindahan tab dengan jelas.
- Pada tombol `"Split Segmen"` (baris `349`), ubah kondisi `v-if="canEdit"` menjadi `v-if="canSplit ?? canEdit"`.
- Jika `activeTab === 'edit'` diminta tetapi `canEdit` bernilai `false`, paksa `activeTab` kembali ke `'inspector'`.

**Langkah 5.4**: Perbaiki Tabel Key-Value dan Tombol Salin ID (`RightPanel.vue` baris `440-505`):
- Tambahkan state `copiedId = ref(false)` dan fungsi `copySegmentId(text: string)` menggunakan `navigator.clipboard.writeText(text)`.
- Pada baris `441`, tampilkan Kode Segmen / ID dengan `tabular-nums` dan tombol ikon `i-lucide-copy` (`aria-label="Salin ID segmen"`).
- Pada tabel key-value (baris `486-500`):
  - Ganti `font-mono` pada `<td>` key menjadi font sans-serif standar (`font-medium text-slate-500 dark:text-slate-400`).
  - Ganti `font-mono break-all` pada `<td>` value menjadi `tabular-nums break-words text-slate-800 dark:text-slate-200`.

**Langkah 5.5**: Perbaiki Formulir Edit (`RightPanel.vue` baris `564-709`):
- Ubah field **Sumber Dana** (baris `671-677`) dari `<UInput>` menjadi `<USelectMenu>` dengan `:items="SUMBER_DANA_OPTIONS.map(o => o.value)"` agar seragam dengan filter.
- Di bagian bawah form (baris `696-707`), ubah menjadi grid 2 tombol:
  1. Tombol **Batal** (`color="neutral" variant="outline"`, `@click="activeTab = 'inspector'"`)
  2. Tombol **Simpan Perubahan** (`color="primary"`, `:loading="editSaving"`, `@click="emit('save-edit')"`)
- Ganti semua kemunculan `font-mono` di `RightPanel.vue` menjadi `tabular-nums`.

---

### Tahap 6: Perbaikan Panel Bottom (`BottomPanel.vue`)

**Tujuan**: Menambahkan state `Error` dengan tombol "Coba Lagi", menambahkan tombol pemulihan pada state `Empty`, menambahkan chip filter `Perkerasan`, dan memperbaiki aksi baris tabel.

**File yang diubah**: `apps/web/app/components/dataset/infrastruktur-segmen/BottomPanel.vue`

**Langkah 6.1**: Tambahkan prop `tableError` dan `selectedPerkerasan` pada `BottomPanel.vue` (baris `10-40`):
```ts
tableError?: string | null;
selectedPerkerasan?: string | null;
```
Dan tambahkan emit pada `Emits` (baris `42-73`):
```ts
(e: 'update:selectedPerkerasan', value: string | null): void;
```

**Langkah 6.2**: Perbaiki `activeSegmenFilterCount` pada `BottomPanel.vue` (baris `122-135`):
- Ubah `else if (props.selectedTipe)` menjadi `if (props.selectedTipe)`.
- Tambahkan `if (props.selectedPerkerasan) count++;`.

**Langkah 6.3**: Tambahkan Chip Filter `Perkerasan` pada bar filter aktif `BottomPanel.vue` (di sekitar baris `555` setelah chip `Kondisi`):
```vue
<span
  v-if="selectedPerkerasan"
  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60 shrink-0"
>
  Perkerasan: {{ selectedPerkerasan }}
  <button
    type="button"
    class="hover:text-indigo-900 dark:hover:text-white ml-0.5 cursor-pointer"
    aria-label="Hapus filter perkerasan"
    @click="emit('update:selectedPerkerasan', null)"
  >
    <UIcon name="i-lucide-x" class="size-2.5" />
  </button>
</span>
```

**Langkah 6.4**: Implementasikan 3 State Data (`Loading`, `Error`, `Empty`) pada `<tbody>` di `BottomPanel.vue` (baris `693-706`):
```vue
<!-- 1. Loading State -->
<template v-if="tableLoading">
  <tr
    v-for="i in 5"
    :key="`skeleton-${i}`"
    class="border-b border-slate-100 dark:border-slate-800/60"
  >
    <td :colspan="activeLayerId === 'segmen' ? 12 : 10" class="px-3 py-2.5">
      <div class="h-4 w-full animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
    </td>
  </tr>
</template>

<!-- 2. Error State -->
<tr v-else-if="tableError">
  <td
    :colspan="activeLayerId === 'segmen' ? 12 : 10"
    class="px-4 py-8 text-center"
  >
    <div class="flex flex-col items-center justify-center gap-2 max-w-md mx-auto">
      <UIcon name="i-lucide-alert-circle" class="size-6 text-red-500" />
      <p class="text-xs font-medium text-slate-700 dark:text-slate-200">
        {{ tableError }}
      </p>
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="i-lucide-refresh-cw"
        label="Coba Lagi"
        @click="emit('refresh')"
      />
    </div>
  </td>
</tr>

<!-- 3. Empty State -->
<tr v-else-if="tableRows.length === 0">
  <td
    :colspan="activeLayerId === 'segmen' ? 12 : 10"
    class="px-4 py-8 text-center"
  >
    <div class="flex flex-col items-center justify-center gap-2 max-w-md mx-auto">
      <UIcon name="i-lucide-search-x" class="size-6 text-slate-400" />
      <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
        Tidak ada data segmen yang cocok dengan filter atau pencarian saat ini.
      </p>
      <div v-if="hasActiveFilters || searchInput" class="flex items-center gap-2 mt-1">
        <UButton
          v-if="searchInput"
          size="xs"
          color="neutral"
          variant="soft"
          icon="i-lucide-x"
          label="Hapus Pencarian"
          @click="clearSearch"
        />
        <UButton
          v-if="hasActiveFilters"
          size="xs"
          color="primary"
          variant="soft"
          icon="i-lucide-rotate-ccw"
          label="Atur Ulang Filter"
          @click="emit('reset-filters')"
        />
      </div>
    </div>
  </td>
</tr>
```

**Langkah 6.5**: Perbaiki visibilitas tombol aksi per baris (`BottomPanel.vue` baris `804-833`) dan ganti semua `font-mono` menjadi `tabular-nums`:
- Tambahkan helper di `BottomPanel.vue` untuk memeriksa apakah sebuah baris dapat diajukan verifikasi atau dihapus berdasarkan status baris tersebut (`row.status_verifikasi === 'draft' || row.status_verifikasi === 'rejected'`).
- Tambahkan atribut `aria-label` pada setiap tombol aksi baris (`Zoom ke segmen`, `Edit atribut segmen`, `Ajukan verifikasi segmen`, `Hapus segmen`).

---

### Tahap 7: Integrasi, Sinkronisasi Wilayah, dan Optimasi di `index.vue`

**Tujuan**:
1. Merender hanya satu `<MapCanvas>` menggunakan deteksi viewport (`isDesktop`).
2. Menyinkronkan filter wilayah antara `Segmen Fisik` dan `Jalan Poros Desa`.
3. Memperbaiki bug `activeFilterCount` (`else if`) dan pemanggilan ganda `loadTable()`.
4. Menambahkan state `tableError` pada `loadTable()`.
5. Memastikan klik tombol `Edit` di baris tabel membuka `RightPanel` langsung pada tab `edit`.

**File yang diubah**: `apps/web/app/pages/admin/infrastruktur-segmen/index.vue`

**Langkah 7.1 (Single `<MapCanvas>` Instance dengan `isDesktop`)**:
- Tambahkan state reaktif `isDesktop`:
  ```ts
  const isDesktop = ref(true);
  function updateViewportMode() {
    if (import.meta.client) {
      isDesktop.value = window.innerWidth >= 1024;
    }
  }
  onMounted(() => {
    updateViewportMode();
    window.addEventListener('resize', updateViewportMode, { passive: true });
  });
  onBeforeUnmount(() => {
    if (import.meta.client) {
      window.removeEventListener('resize', updateViewportMode);
    }
  });
  ```
- Pada template desktop (`baris 1672`: `<div class="hidden lg:flex flex-1 min-h-0 overflow-hidden relative">`), tambahkan `v-if="isDesktop"`.
- Pada template mobile (`baris 1882`: `<div class="flex lg:hidden flex-1 flex-col overflow-hidden relative">`), ganti menjadi `v-else`.
- Dengan perubahan ini, hanya **satu** `<MapCanvas>` yang dirender di DOM pada satu waktu!

**Langkah 7.2 (Sinkronisasi Wilayah Kerja Global)**:
- Tambahkan watcher pada `filterKecamatan` dan `filterDesa` di `index.vue` agar selalu menyinkronkan `filterJalanKecamatan` dan `filterJalanDesa`:
  ```ts
  watch(filterKecamatan, (val) => {
    if (filterJalanKecamatan.value !== val) {
      filterJalanKecamatan.value = val;
    }
  });

  watch(filterDesa, (val) => {
    if (filterJalanDesa.value !== val) {
      filterJalanDesa.value = val;
    }
  });
  ```
- Saat pengguna mengubah Kecamatan atau Desa dari Topbar, picu `fitAllBounds()` setelah data selesai dimuat agar peta otomatis melakukan zoom ke wilayah yang dipilih.

**Langkah 7.3 (Perbaikan `activeFilterCount` dan `activeSegmenFilterCount` di `index.vue`)**:
- Pada baris `326-327` dan `431-432`, ubah `else if (filterTipe.value) count++;` menjadi `if (filterTipe.value) count++;`.

**Langkah 7.4 (Penanganan `tableError` dan Penghapusan Double Fetch)**:
- Tambahkan `const tableError = ref<string | null>(null);` di dekat `tableLoading` (baris `240`).
- Di dalam `loadTable()` (baris `675-795`):
  - Set `tableError.value = null;` di awal blok `try`.
  - Di dalam blok `catch (err: any)`, set `tableError.value = err?.data?.message || 'Gagal memuat data tabel segmen. Silakan coba lagi.';`.
- Teruskan `:table-error="tableError"` dan `v-model:selected-perkerasan="filterPerkerasan"` ke komponen `<DatasetInfrastrukturSegmenBottomPanel>` (baris `1828-1878`).
- Di dalam `onMounted` (baris `1477-1478`), hapus pemanggilan `loadTable()` yang berdiri sendiri karena `refreshAll(true)` tepat di atasnya sudah memanggil `loadTable()`.

**Langkah 7.5 (Handler `@edit-row` Membuka Tab Edit di Panel Kanan)**:
- Tambahkan ref `rightPanelRef = ref< any >(null)` pada `<DatasetInfrastrukturSegmenRightPanel ref="rightPanelRef" ... />`.
- Buat fungsi `handleEditRowFromTable(row: Record<string, any>)`:
  ```ts
  async function handleEditRowFromTable(row: Record<string, any>) {
    await handleZoomToFeature(row);
    rightPanelOpen.value = true;
    nextTick(() => {
      rightPanelRef.value?.setActiveTab?.('edit');
    });
  }
  ```
- Pada `<DatasetInfrastrukturSegmenBottomPanel>` (baris `1873`), ubah `@edit-row="handleZoomToFeature"` menjadi `@edit-row="handleEditRowFromTable"`.

**Langkah 7.6 (Pembersihan `font-mono` di `index.vue`)**:
- Ganti seluruh class `font-mono` pada template `index.vue` (baris `1527, 1534, 1938, 1960, 2026, 2083, 2162, 2181, 2343, 2364, 2551`) menjadi `tabular-nums`.
- Gunakan konstanta `KONDISI_OPTIONS`, `PERKERASAN_OPTIONS`, `STATUS_JALAN_OPTIONS`, dan `SUMBER_DANA_OPTIONS` dari `~/types/infrastruktur` pada modal Buat Segmen Baru (`createModalOpen`, baris `2595-2685`).

---

### Tahap 8: Verifikasi Build & Checklist Pengujian (QA Checklist)

Setelah menyelesaikan Tahap 1 sampai Tahap 7, jalankan perintah pemeriksaan dan verifikasi manual berikut:

#### 8.1 Perintah Verifikasi Terminal
Jalankan perintah pemeriksaan tipe TypeScript di direktori `apps/web`:
```bash
pnpm --filter web typecheck
```
Pastikan tidak ada error TypeScript pada seluruh file yang diubah.

#### 8.2 Checklist Pengujian Fungsional & UI/UX
1. **Pemeriksaan Instance Peta Tunggal**:
   - [ ] Buka `/admin/infrastruktur-segmen` di browser desktop (`>= 1024px`). Periksa tab Network: pastikan request tile MVT dan GeoJSON hanya dipanggil 1 kali (bukan 2 kali).
   - [ ] Kecilkan ukuran jendela browser ke ukuran mobile (`< 1024px`): pastikan peta tetap tampil normal dan tombol kontrol mobile memiliki ukuran minimal `44x44px`.
2. **Pemeriksaan Filter Wilayah (Topbar, Panel Kiri, Dialog Filter)**:
   - [ ] Login sebagai **Admin / BAPPEDA**: pilih Kecamatan di Topbar -> pastikan dropdown Desa menjadi aktif, peta melakukan zoom ke Kecamatan tersebut, serta layer `Segmen Fisik` dan `Jalan Poros Desa` sama-sama terfilter ke Kecamatan tersebut.
   - [ ] Klik tombol Reset (`x`) di samping selector wilayah Topbar -> pastikan Kecamatan & Desa kembali ke `"Semua Kecamatan"` dan `"Semua Desa"`.
   - [ ] Login sebagai **Operator Desa**: pastikan Kecamatan dan Desa otomatis terkunci pada wilayah tugasnya dengan ikon gembok (`i-lucide-lock`).
3. **Pemeriksaan Dialog Filter (Draft State & Field Lengkap)**:
   - [ ] Buka Dialog Filter -> pastikan field **Tipe Infrastruktur** tampil dan dapat dipilih.
   - [ ] Ubah beberapa filter di dalam modal (misal: Kondisi = `Rusak Berat`, Perkerasan = `Aspal`) -> pastikan peta dan tabel di belakang modal **belum** reload selama modal masih terbuka.
   - [ ] Klik tombol **"Tutup"** -> pastikan perubahan dibatalkan dan peta/tabel tidak berubah.
   - [ ] Buka kembali Dialog Filter, pilih Kondisi = `Rusak Berat` dan Tipe Infrastruktur = `Jalan`, lalu klik **"Terapkan Filter"** -> pastikan badge jumlah filter menampilkan angka `2` dengan benar (bug `else if` sudah teratasi) dan peta/tabel memuat data sesuai filter.
4. **Pemeriksaan Panel Kiri, Panel Kanan, dan Panel Bottom**:
   - [ ] **Panel Kiri**: Pastikan tidak ada ikon drag palsu (`grip-vertical`) maupun tombol ke-7 yang mati permanen; pastikan rincian Legenda Kondisi menampilkan jumlah segmen dan panjang per kondisi.
   - [ ] **Panel Kanan**: Klik satu segmen di peta -> pastikan tombol salin ID berfungsi, teks pada tabel atribut tidak terpotong di tengah kata (`break-words`), dan tab `Edit Formulir` memiliki tombol **Batal** serta pilihan `Sumber Dana` dan `Jenis Perkerasan` yang seragam dengan filter.
   - [ ] **Panel Bottom**: Pastikan chip filter `Perkerasan` muncul saat difilter; klik ikon pensil (`Edit`) pada salah satu baris tabel -> pastikan Panel Kanan terbuka langsung di tab `Edit Formulir`.
   - [ ] **Tipografi**: Pastikan tidak ada lagi penggunaan `font-mono` pada angka tabel, koordinat, dan badge; semua angka menggunakan `tabular-nums`.

# Spesifikasi UI/UX Halaman Beranda Publik MELAROSA

**Aplikasi:** MELAROSA (Monitoring Evaluasi Data Spasial Desa), Sistem Informasi Spasial Kabupaten Bojonegoro  
**Versi Spesifikasi:** 4.1  
**Tanggal:** 29 Oktober 2026  
**Status:** Standar Desain dan Implementasi  
**Implementasi:** `apps/web/app/pages/index.vue`  
**Referensi Visual:** [Mandum Rimba](https://www.mandumrimba.org/id)

---

## 1. Tujuan Redesain

Halaman beranda MELAROSA harus terasa seperti **observatorium pembangunan daerah yang hidup**, bukan dashboard administratif yang dipindahkan ke halaman publik.

Pengalaman visual harus mengajak masyarakat mengenali wilayahnya, memahami perkembangan infrastruktur, menjelajahi kekayaan Geopark, dan menemukan informasi spasial tanpa harus memahami istilah teknis GIS.

Redesain berfokus pada lima perubahan:

1. **Visual-first:** peta, citra wilayah, dan dokumentasi visual menjadi elemen utama, bukan sekadar pelengkap kartu.
2. **Editorial storytelling:** setiap bagian mempunyai ritme, hierarki, dan cara penyampaian yang berbeda.
3. **Spatial exploration:** pengunjung didorong menjelajahi desa, kecamatan, ruas jalan, dan situs Geopark.
4. **Institutional identity:** tampilan profesional, membumi, dan sesuai identitas pemerintahan daerah.
5. **Functional by design:** seluruh elemen interaktif mempunyai aksi nyata dan tujuan yang jelas.

### Prinsip yang tidak boleh dilanggar

- Jangan menggunakan pola grid kartu yang sama secara berulang di seluruh halaman.
- Jangan membungkus setiap informasi ke dalam kotak dengan border, radius, dan bayangan identik.
- Jangan menjadikan statistik sebagai enam kartu kecil yang berdiri sendiri tanpa konteks.
- Jangan memenuhi halaman dengan badge, label kapital, dekorasi gradient, dan ikon yang tidak memiliki fungsi.
- Jangan menggunakan ilustrasi peta generik jika data spasial MELAROSA dapat ditampilkan secara nyata.
- Jangan membuat semua bagian memiliki warna latar, tinggi, dan kepadatan konten yang sama.
- Jangan menggunakan foto, angka, testimoni, atau informasi lokasi yang tidak memiliki sumber yang dapat dipertanggungjawabkan.

---

## 2. Arah Visual: Editorial Geospatial Observatory

Desain menggabungkan karakter portal informasi publik, atlas digital, dan majalah editorial tentang wilayah.

### 2.1 Karakter visual

- **Institusional:** identitas Pemerintah Kabupaten Bojonegoro terlihat jelas tanpa mendominasi konten.
- **Eksploratif:** peta dan informasi lokasi mengundang interaksi.
- **Editorial:** judul besar, komposisi asimetris, ruang kosong, dan narasi singkat membentuk ritme halaman.
- **Dokumenter:** foto wilayah dan situs Geopark digunakan untuk menghadirkan konteks nyata.
- **Kontemporer:** tipografi bersih, transisi halus, garis pemisah tipis, dan animasi seperlunya.

Hindari estetika dashboard generik, neon, cyberpunk, dekorasi berlebihan, dan tampilan yang menyerupai kumpulan kartu statistik.

### 2.2 Sistem warna

| Penggunaan | Warna | Fungsi |
|---|---|---|
| Latar utama terang | `#F7F8F5` | Kanvas halaman yang hangat dan netral |
| Permukaan konten | `#FFFFFF` | Area informasi yang membutuhkan pemisahan |
| Teks utama | `#17251F` | Judul dan isi utama |
| Teks sekunder | `#647067` | Deskripsi dan metadata |
| Hijau institusional | `#176B4D` | Identitas pembangunan dan navigasi |
| Hijau gelap | `#102B22` | Hero alternatif dan section penutup |
| Biru peta | `#2563EB` | Interaksi spasial dan navigasi peta |
| Amber | `#B45309` | Penanda perhatian dan informasi penting |
| Garis pemisah | `#DFE5DE` | Pembatas editorial yang ringan |

Warna status kondisi jalan harus mengikuti legenda resmi yang digunakan pada peta MELAROSA. Jangan menyamakan warna status kondisi jalan dengan warna dekoratif situs Geopark.

Mode gelap mengikuti token warna tersendiri dengan kontras yang setara. Jangan sekadar membalik warna seluruh elemen.

---

## 3. Arsitektur Informasi dan Ritme Halaman

Urutan halaman dirancang untuk membangun pengalaman dari pengenalan wilayah menuju eksplorasi data.

```text
PUBLIC NAVBAR
    |
    v
01. HERO EDITORIAL + PETA WILAYAH
    |
    v
02. ANGKA BOJONEGORO DALAM SATU GARIS INFORMASI
    |
    v
03. CERITA INFRASTRUKTUR
    |
    v
04. JELAJAHI WILAYAH
    |
    v
05. CERITA GEOPARK BOJONEGORO
    |
    v
06. SIMULASI TUMPANG SUSUN LAPISAN (SATU PETA, BANYAK LAPISAN)
    |
    v
07. PANDUAN EKSPLORASI INTERAKTIF
    |
    v
08. CTA DAN FOOTER PEMERINTAH DAERAH
```

Setiap bagian wajib memiliki komposisi visual yang berbeda. Variasi harus berasal dari hierarki informasi, proporsi, media, dan tata letak, bukan dari penambahan dekorasi yang tidak diperlukan.

---

## 4. Section 01: Hero Editorial dan Peta Wilayah

### Tujuan

Memberikan kesan pertama yang kuat sebagai pintu masuk **Geospatial Observatory** Kabupaten Bojonegoro dan memfasilitasi eksplorasi wilayah, infrastruktur desa, serta situs Geopark dalam beberapa detik.

### Komposisi dan Proporsi (Balanced Split Hero)

Gunakan pendekatan **balanced split hero** di dalam kontainer terpusat (`max-w-7xl` / `1.280 px`) dengan proporsi dua kolom pada desktop sekitar **44% : 56%** (`lg:grid-cols-[minmax(0,44fr)_minmax(0,56fr)]`) dan gap `36-40 px` (`lg:gap-9 xl:gap-10`). Pada tablet (`md`), sesuaikan proporsi menjadi `48% : 52%` (`md:grid-cols-[minmax(0,48fr)_minmax(0,52fr)] md:gap-7`). Latar belakang section Hero menggunakan pola garis grid kartografis (*map graticule grid* dua tingkat: grid mayor `96px` dan sub-grid `24px`) yang memperkuat karakter lembar peta observatorium spasial tanpa mengganggu kontras teks.

**Kolom kiri (Editorial & Discovery - `flex flex-col justify-between`):**
Kolom kiri diselaraskan secara vertikal terhadap card peta (`lg:items-stretch`) agar kedua kolom membentuk satu komposisi utuh:
1. **Bagian Atas (Identitas MELAROSA):** Baris identitas institusional (`MELAROSA · Monitoring Evaluasi Data Spasial Desa | Pemerintah Kabupaten Bojonegoro`) tanpa *pill badge* dekoratif atau titik berdenyut.
2. **Bagian Tengah Section (Headline, Deskripsi, dan CTA - `my-auto`):**
   - **Headline utama (H1):** **"Jelajahi Bojonegoro, dalam Satu Peta."** dengan skala tipografi tegas, `line-height` nyaman (`1.12`), dan panjang baris terkendali.
   - **Deskripsi singkat:** Informatif dan langsung menjelaskan nilai halaman tanpa bertele-tele:
     > Temukan infrastruktur pembangunan, dan potensi Kabupaten Bojonegoro.
   - **CTA primer dan sekunder:**
     - CTA primer: **Jelajahi Peta** (`/maps`) menggunakan warna aksen interaksi spasial `#2563EB`.
     - CTA sekunder: **Kenali Geopark** (`#cerita-geopark`) menggunakan gaya outline netral.
3. **Bagian Bawah (Fitur Pencarian Terpadu Fokus):**
   - **Search bar terpadu:** Menerima pencarian nama kecamatan, desa, ruas jalan, situs Geopark, maupun koordinat WGS 84 (format desimal DD atau DMS). Pada desktop (`lg`), daftar saran pencarian muncul ke arah atas (`lg:bottom-full`) agar tidak terpotong batas bawah section.
   - **Penyederhanaan UI Hero:** Tombol cepat (chips) kecamatan ditiadakan untuk menjaga kerapian visual dan fokus antarmuka hero section, sehingga ruang visual didedikasikan penuh pada form pencarian terpadu yang dilengkapi tombol aksi langsung serta pembersihan teks (tombol clear input). Eksplorasi direktori 28 kecamatan secara lengkap difasilitasi khusus pada section Direktori Wilayah (`DistrictExplorer.vue`).

**Kolom kanan (Live Geospatial Preview - Card Peta Terkendali):**
- **Batas Tinggi Card Peta (`lg:max-h-[560px]`):**
  - Batasi tinggi keseluruhan card peta sekitar `520-560 px` pada viewport desktop agar tidak mendominasi tinggi layar.
  - Pertahankan hanya 3 lapis UI utama di dalam card: header ringkas (judul + teks status inline + koordinat WGS 84), kanvas peta aktual beserta kontrol & legenda ringkas, serta footer informasi wilayah satu baris.
- **Kanvas Peta Utama (OpenLayers):**
  - Atur tinggi area kanvas peta secara terpisah dari header dan footer menggunakan rasio visual responsif (`aspect-[4/3] sm:aspect-[16/10] md:aspect-[5/4] lg:aspect-auto lg:h-[395px] xl:h-[415px]`), tanpa *fixed height* kaku di semua breakpoint dan tanpa `transform: scale()`.
  - Menggunakan instance OpenLayers (`ol`) aktual pada sisi klien untuk memuat basemap topografi, layer vektor aktual MELAROSA (`/martin/bataswilayah_kabupaten`, `/martin/jalan_porosdesa`, `/martin/infrastruktur_segmen`), serta layer vektor interaktif untuk *bounding box* kecamatan terpilih, penanda 28 kecamatan, dan titik situs Geopark berkoordinat.
  - Menampilkan hierarki kartografis yang jelas antara:
    1. **Batas administrasi:** garis batas wilayah (`dash-dot`) dan cakupan kecamatan terpilih.
    2. **Jaringan jalan:** trase jalan poros desa dan segmen infrastruktur sesuai warna kondisi resmi.
    3. **Marker lokasi:** titik pusat kecamatan terpilih dan situs Geopark di sekitarnya.
  - Menyediakan kontrol eksplorasi langsung pada kanvas (Zoom In, Zoom Out, Reset Cakupan Kabupaten, dan legenda ringkas).
- **Footer Informasi Wilayah Ringkas:**
  - Ditempatkan sebagai satu baris informasi kontekstual di bagian bawah bingkai peta, menampilkan nama kecamatan terpilih, teks zona wilayah inline (`· Zona {Zona}` tanpa badge berbingkai tambahan), deskripsi singkat, dan koordinat pusat (`tabular-nums`).
  - Menyediakan satu aksi kontekstual **Buka Peta {Nama Kecamatan}** (mengarah ke `/maps/@{lat},{lng},{zoom}z?kecamatan=...`) tanpa menduplikasi tombol CTA primer di kolom kiri.
- **State Pemuatan dan Fallback Kartografis:**
  - Saat peta sedang dimuat, tampilkan indikator pemuatan dengan tinggi kontainer tetap agar tidak memicu pergeseran layout (*layout shift*).
  - Jika peta atau layanan tile belum dapat dimuat, tampilkan fallback kartografis berbasis proyeksi koordinat aktual Bojonegoro yang diberi penanda jelas sebagai pratinjau, disertai tombol yang tetap membuka peta penuh.

### Interaksi Pencarian dan Pemilihan Wilayah

Pencarian mendukung nama kecamatan, desa, ruas jalan, situs Geopark, dan koordinat WGS 84:
1. Gunakan *debounce* (`180ms`) untuk menyaring saran secara efisien serta dukung navigasi keyboard (`ArrowDown`, `ArrowUp`, `Enter`, `Escape`) dan penutupan otomatis saat klik di luar area pencarian.
2. Ketika pengguna memilih saran atau menekan tombol cari, arahkan ke rute `/maps` dengan parameter koordinat, kecamatan, geosite, atau kata kunci yang relevan.
3. Ketika pengguna memilih saran hasil pencarian kecamatan atau mengklik titik kecamatan pada peta pratinjau, perbarui fokus wilayah aktif dan gerakkan (*animate*) kamera peta pratinjau OpenLayers ke koordinat kecamatan tersebut.
4. Tampilkan pesan status yang informatif ketika kata kunci tidak ditemukan pada daftar saran cepat, dengan opsi melanjutkan pencarian penuh di halaman `/maps`.

### Responsivitas Hero, Jarak Navbar, dan Transisi Section

- **Jarak terhadap Floating Navbar (`pt-24 sm:pt-28 lg:pt-28`):** Memberikan ruang bernapas `36-48 px` di bawah *floating navbar* (`~64-72 px`) sehingga baris identitas MELAROSA dan header card peta tidak pernah tertutup navigasi atas.
- **Transisi menuju Section Statistik (`pb-10 sm:pb-12 lg:pb-14` + `border-b`):** Karena tinggi card peta dibatasi sekitar `520-560 px`, transisi menuju barisan angka pembangunan (`RegionalMetrics`) di bawahnya langsung terlihat proporsional tanpa ruang kosong vertikal berlebih.
- **Desktop (`lg` ke atas):** Komposisi *balanced split* `44% : 56%` (`gap 36-40 px`) dengan keseimbangan vertikal utuh antara teks editorial di kiri dan card peta di kanan.
- **Tablet (`md`):** Komposisi dua kolom `48% : 52%` (`gap 28 px`) dengan rasio kanvas peta `5:4` dan form pencarian yang menyesuaikan lebar kolom secara adaptif.
- **Mobile (`< md`):** Susun identitas, headline, CTA, dan pencarian terlebih dahulu, diikuti card peta dengan rasio `4:3` (`sm:aspect-[16/10]`) sehingga pengguna tidak perlu melakukan scrolling berlebihan.
- Seluruh kontrol interaktif (input pencarian, tombol aksi, kontrol zoom peta) wajib memenuhi ukuran sentuh minimal `44 × 44 piksel`.

### Alasan Keputusan Desain Hero (R-31)

- **Palet netral hangat (`#F7F8F5`) dan teks hijau gelap (`#17251F`):** Memberikan kenyamanan baca seperti publikasi cetak/atlas dan membedakan portal publik dari dashboard gelap administratif.
- **Aksen biru peta (`#2563EB`) terfokus pada CTA primer dan sorotan spasial:** Menjaga satu titik fokus aksi utama di layar tanpa membuat seluruh tombol berkompetisi.
- **Komposisi asimetris 5:7 tanpa *nested cards*:** Memberikan ruang kanvas yang luas bagi peta OpenLayers agar berfungsi sebagai observatorium spasial nyata, bukan sekadar ilustrasi di dalam kotak.
- **Pemisahan dengan *whitespace* dan garis batas tipis (`#DFE5DE`):** Menghindari kesan kaku dari kartu berlapis-lapis sekaligus menjaga struktur informasi tetap rapi.

---

## 5. Section 02: Angka Bojonegoro dalam Satu Garis Informasi

### Tujuan

Menyajikan skala wilayah dan infrastruktur tanpa membuat deretan kartu statistik yang monoton.

### Komposisi

Gunakan satu bidang horizontal dengan garis pemisah tipis dan tipografi angka berukuran besar.

Data yang ditampilkan:

- **28** kecamatan.
- **430** desa dan kelurahan.
- **1.187** ruas jalan poros desa.
- **1.424,8 km** panjang jaringan jalan.
- **31** situs Geopark.
- **2.307 km²** luas wilayah.

Jangan memaksa keenam metrik tampil dalam enam kotak identik. Gunakan komposisi editorial dengan ukuran angka yang disesuaikan dengan kepentingannya.

Pada desktop, metrik dapat membentuk satu baris yang responsif. Pada tablet dan ponsel, gunakan dua kolom atau alur horizontal yang tetap mudah dibaca.

Tambahkan keterangan sumber dan tanggal pembaruan data jika tersedia. Angka yang belum diverifikasi atau tidak sesuai dengan data terbaru tidak boleh ditampilkan sebagai fakta terkini.

Angka statistik hanya boleh menjadi tautan jika memiliki tujuan yang jelas, misalnya membuka direktori kecamatan atau peta infrastruktur.

---

## 6. Section 03: Cerita Infrastruktur

### Tujuan

Mengubah data jaringan jalan menjadi informasi yang mudah dipahami masyarakat.

### Komposisi visual

Gunakan layout editorial dua bagian dengan latar hijau gelap atau bidang netral yang kontras dengan hero.

**Bagian utama:**
- Judul besar: **Jalan yang menghubungkan desa-desa Bojonegoro.**
- Narasi singkat mengenai jaringan jalan poros desa.
- Angka total ruas dan panjang jaringan sebagai bagian dari narasi.
- Visual peta jaringan jalan yang berasal dari data spasial sebenarnya.

**Bagian pendamping:**
- Legenda kondisi jalan.
- Jenis permukaan jalan berdasarkan data yang tersedia.
- Ringkasan kondisi atau distribusi wilayah jika perhitungannya tersedia.
- Tautan untuk melihat jaringan jalan pada peta.

Gunakan garis jalan sebagai motif visual utama. Ketebalan, warna, dan pola garis harus mempunyai arti yang konsisten dengan legenda peta.

Jangan mengandalkan warna saja untuk menjelaskan kondisi. Sertakan label teks atau simbol yang dapat dibedakan oleh pengguna dengan gangguan persepsi warna.

### Aturan data

- Jumlah ruas dan panjang jalan harus berasal dari hasil agregasi data yang sama.
- Jangan mencampurkan ruas jalan, segmen jalan, dan panjang jaringan seolah-olah ketiganya merupakan satu jenis metrik.
- Bedakan data kondisi terkini dengan riwayat pembangunan.
- Tampilkan tanggal pembaruan jika tersedia.
- Jangan membuat persentase kondisi jalan tanpa perhitungan yang jelas dan data pendukung.

CTA: **Lihat Jaringan Jalan**

---

## 7. Section 04: Jelajahi Wilayah

### Tujuan

Membantu pengunjung menemukan kecamatan dan memahami karakter wilayahnya.

### Komposisi

Bagian ini harus terasa seperti direktori atlas, bukan katalog produk.

Gunakan:
- Judul editorial dan deskripsi singkat.
- Pencarian kecamatan.
- Filter wilayah jika klasifikasi tersebut sudah ditetapkan secara resmi.
- Daftar kecamatan dalam baris atau panel yang lebih ringan.
- Satu panel pratinjau wilayah yang berubah sesuai pilihan pengguna.

Pada desktop, gunakan layout dua kolom:

- **Kolom direktori:** daftar kecamatan dengan nomor urut, nama, dan ringkasan informasi yang benar-benar tersedia.
- **Kolom peta:** bentuk wilayah kecamatan yang dipilih, lokasi pusat wilayah jika tersedia, serta tindakan membuka peta.

Peta dan daftar harus saling terhubung. Memilih kecamatan akan memperbarui sorotan wilayah dan informasi yang relevan.

Pada ponsel, tampilkan daftar terlebih dahulu dan peta sebagai panel yang dapat dibuka.

### Filter dan status

Direktori harus mendukung:
- Semua kecamatan.
- Pencarian nama kecamatan.
- Filter wilayah jika definisi kategorinya tersedia.
- Penghapusan filter.

Kategori seperti Perkotaan, Timur, Tengah, Selatan, dan Barat tidak boleh dibuat berdasarkan asumsi. Gunakan klasifikasi yang sudah ditentukan dalam data atau kebijakan yang relevan.

Implementasikan tiga keadaan:
- **Loading:** indikator pemuatan yang tidak menggeser layout secara drastis.
- **Empty:** pesan yang menjelaskan tidak ada hasil serta tombol reset.
- **Error:** pesan kegagalan dan tindakan mencoba kembali.

Jangan mengisi kartu dengan karakteristik lokal yang dibuat-buat hanya untuk membuat tampilan terasa penuh.

CTA setiap kecamatan: **Lihat di Peta**

---

## 8. Section 05: Cerita Geopark Bojonegoro

### Tujuan

Memperkenalkan warisan geologi, kekayaan alam, dan budaya Bojonegoro melalui pengalaman visual yang lebih emosional dan edukatif.

### Komposisi visual

Berbeda dari direktori kecamatan, bagian Geopark harus menggunakan pendekatan galeri editorial.

Tampilkan satu situs unggulan dalam komposisi besar, kemudian beberapa situs lainnya dalam grid asimetris dengan ukuran gambar yang bervariasi.

Contoh struktur:

- Satu situs utama dengan foto lebar, judul, kategori, dan narasi singkat.
- Dua situs pendamping dengan gambar vertikal atau persegi.
- Daftar situs lainnya dalam galeri yang dapat dijelajahi.
- Tautan menuju katalog lengkap dan lokasi pada peta.

Foto harus merepresentasikan situs yang disebutkan. Gunakan dokumentasi yang memiliki izin penggunaan dan sumber yang jelas. Jika gambar tidak tersedia, gunakan placeholder yang informatif, bukan foto dari lokasi lain yang berpotensi menyesatkan.

### Kategori

- Warisan geologi dan bumi: 17 situs.
- Kekayaan alam dan hayati: 5 situs.
- Warisan budaya dan sejarah: 9 situs.

Jumlah tersebut harus disinkronkan dengan katalog resmi.

### Interaksi

Filter kategori harus benar-benar mengubah daftar situs. Setiap item menampilkan informasi yang tersedia, termasuk nama, kategori, ringkasan, dan lokasi.

Tindakan yang tersedia:
- Membuka cerita atau detail situs.
- Menampilkan lokasi pada peta.
- Mengembalikan daftar ke tampilan semua situs.

Terapkan status loading, empty, dan error apabila katalog bersumber dari permintaan data.

Jangan menggunakan carousel otomatis yang bergerak terus-menerus. Jika carousel digunakan, sediakan kontrol navigasi yang jelas, dukungan keyboard, dan penghentian gerakan otomatis.

---

## 9. Section 06: Satu Peta, Banyak Lapisan (Simulasi Tumpang Susun Tematik)

### Tujuan

Memberikan pengalaman simulasi interaktif WebGIS mengenai tumpang susun lapisan (*overlay*) tematik multi-sektoral Kabupaten Bojonegoro. Section ini membuktikan keterpaduan infrastruktur jalan poros desa, sumber daya air, batas wilayah administrasi, kawasan pertanian pangan berkelanjutan, serta warisan geologi Geopark dalam representasi visual 3D isometrik dan 2D datar.

### Header dan Identitas Editorial

- **Kicker:** `KETERPADUAN DATA SPASIAL` (huruf kapital tebal dengan warna aksen biru `#2563EB`).
- **Headline utama (H2):** **"Satu Peta, Banyak Lapisan."** (ringkas, tegas, dan mudah dipahami).
- **Deskripsi:** *"Padukan data jalan desa, sungai, batas wilayah, pertanian, dan Geopark Bojonegoro."*
- **CTA Primer:** **Jelajahi Data Spasial** (`/dataset`) dengan ikon basis data.

### Tata Letak Dua Kolom (Equal-Height Grid)

Gunakan grid 12 kolom terpusat (`max-w-7xl`, `lg:grid-cols-12`, `lg:items-stretch`, `gap-8`) agar kartu pratinjau di sebelah kiri dan kartu panel kontrol di sebelah kanan selalu sejajar sama tinggi:

**1. Kolom Kiri (7 Kolom - Pratinjau 3D Isometrik & 2D Flat):**
- **Kartu Pembungkus Luar (`lg:col-span-7 h-full flex flex-col`):**
  - Menggunakan permukaan putih (`bg-white dark:bg-[#0c1522]`) dengan border netral `#DFE5DE` / `dark:border-white/15`.
  - Header kartu memuat judul `SIMULASI TUMPANG SUSUN LAPISAN`, indikator jumlah lapisan aktif (`X dari 5 lapisan aktif`), serta status koneksi data riil (penghitung tile Martin MVT dan WMS Geoportal).
  - Sakelar mode tampilan:
    - **3D Exploded:** proyeksi isometrik `transform: rotateX(60deg) rotateZ(-45deg)` dengan pemisahan bidang melayang vertikal pada sumbu Z.
    - **2D Flat:** proyeksi datar terintegrasi `transform: rotateX(0deg) rotateZ(0deg)` yang menyatukan seluruh lapisan aktif ke dalam satu bidang peta gabungan.
- **Kanvas Gelap (#0B1120) Full-Height (`flex-1`):**
  - Kanvas visual bertema gelap pekat (`#0B1120`) yang mengisi penuh sisa ruang vertikal kartu pembungkus luar (`flex-1`).
  - Dilengkapi grid kartografis halus, pencahayaan radial lembut, dan metadata koordinat pembatas wilayah Kabupaten Bojonegoro (`BBOX: 111.42°BT - 112.16°BT · 7.46°LS - 6.98°LS`).
- **State Kosong (Empty State Terpusat):**
  - Ketika seluruh lapisan tematik dimatikan (`!hasAnyActiveLayer`), basemap topografi otomatis ikut disembunyikan (`opacity-0 translate3d(0, 0, -48px)`).
  - Ditampilkan kartu pesan terpusat (`Tidak Ada Lapisan yang Aktif`) berlatar `slate-900/90 backdrop-blur-md` dengan tombol aksi pemulihan cepat **Tampilkan Lapisan Default** (`activateDefaultLayers`).
- **Panggung 3D Isometrik (.iso-stage) dan Bidang Lapisan (Glassmorphic Planes):**
  - **Basemap Topografi Abu-abu Gelap (#111827):** Terletak pada tingkat paling bawah, memuat kontur topografi SVG, trase Bengawan Solo, dan 28 titik pusat kecamatan Bojonegoro.
  - **5 Bidang Lapisan Tematik Melayang:**
    1. *Jaringan Jalan Poros Desa (Dinas PU Bina Marga):* Warna hijau neon `#10B981`, tipe LineString, terhubung langsung ke Martin Vector Tile (`/martin/jalan_porosdesa` dan `/martin/infrastruktur_segmen`).
    2. *Sungai & Bengawan Solo (Dinas PU Sumber Daya Air):* Warna biru menyala `#3B82F6`, tipe River/Badan Air, terhubung ke OGC WMS GeoServer Geoportal Bojonegoro.
    3. *Batas Administrasi & Kawasan (Bappeda Bojonegoro):* Warna ungu `#8B5CF6`, tipe Poligon Batas Wilayah, terhubung ke Martin MVT (`/martin/bataswilayah_kabupaten`).
    4. *Kawasan Lahan Pertanian (Dinas Pertanian):* Warna kuning/oranye `#F59E0B`, tipe Zona Tematik LP2B & sawah irigasi, terhubung ke WMS GeoServer Geoportal Bojonegoro.
    5. *Titik Situs Geopark (Dinas Kebudayaan & Pariwisata):* Warna pink/magenta `#EC4899`, tipe Point 31 situs terverifikasi asli (`useGeoparkSites`) dengan label nama (Wonocolo, Kayangan Api, Waduk Pacal) dan garis proyeksi laser vertikal menembus tumpukan lapisan.
- **Kalibrasi Gap Vertikal dan Perataan Tengah:**
  - Jarak (*gap*) vertikal sumbu Z pada mode 3D Exploded (`layerGap3D`) dikalibrasi adaptif antara `44 px` hingga `56 px` per tingkat agar tumpukan layer terdistribusi proporsional di dalam kanvas *full-height*.
  - Pada mode 2D Flat, diterapkan *step* vertikal halus `-7 px` per urutan layer dengan elevasi Z terukur sehingga susunan *layer ordering* tetap dapat dibedakan.
  - Titik tumpu panggung (`isoStageDynamicStyle`) mengompensasi separuh total ketinggian Z (`maxZ * 0.44`) agar seluruh lapisan selalu berada tepat di tengah kanvas secara simetris.
- **Bar Informasi Bawah Kanvas:**
  - Menampilkan titik indikator warna untuk tiap lapisan yang aktif, ringkasan urutan lapisan aktif dari atas ke bawah, serta tautan cepat **Buka di WebGIS** (`/maps?tab=dataset`).

**2. Kolom Kanan (5 Kolom - Panel Kontrol & Layer Ordering):**
- **Header Panel:**
  - Memuat judul `Pilih & Urutkan Lapisan Data Tematik` dan keterangan bahwa posisi kartu dari atas ke bawah menentukan urutan lapisan fisik pada kanvas kiri.
  - Dua tombol aksi praktis di sudut kanan atas:
    - **Balik Urutan:** Membalik susunan tumpukan vertikal secara instan (urutan atas menjadi bawah dan sebaliknya).
    - **Aktifkan Semua / Matikan Semua:** Mengontrol visibilitas seluruh 5 lapisan secara serentak.
- **Bar Info Interaksi Ordering:**
  - Menjelaskan bahwa pengguna dapat menyeret kartu (*HTML5 drag-and-drop*) atau mengklik tombol panah naik/turun (↑ / ↓) untuk mengatur Z-Order tumpukan layer.
- **Daftar Kartu Lapisan Interaktif:**
  - **Sakelar Aktif/Nonaktif:** Tombol switch dengan target sentuh minimal 44 × 44 piksel.
  - **Kontrol Posisi Z:** Tombol panah reordering ke atas dan ke bawah dengan visual transisi halus.
  - **Drag-and-Drop Handle:** Dukungan seret kartu untuk memindahkan urutan prioritas visual layer.
  - **Interaksi Hover Sinkron:** Mengarahkan kursor ke kartu kontrol di panel kanan akan memicu efek *glow* terang dan sedikit mengangkat (*lift* +16px Z) bidang 3D yang bersangkutan di kanvas kiri.
  - **Metadata Sumber Data Nyata:** Setiap kartu menampilkan nama instansi wali data, deskripsi fungsi, status Z-Level terkini, tipe geometri, serta endpoint teknis riil (Martin MVT / GeoServer WMS).
- **Footer Panel:**
  - Tautan aksi sekunder **Lihat Seluruh Katalog Geoportal JIGN ({count} Layer)** mengarah ke katalog dataset lengkap `/dataset`.

### Aturan Data dan Keandalan Teknis

- Integrasi Martin Tile Server memanfaatkan OpenLayers VectorTile client-side instance dengan styling dinamis berbasis kondisi jalan dan batas wilayah kabupaten.
- Integrasi layer tematik Geoportal Bojonegoro disinkronkan dengan endpoint WMS resmi Geoportal Bojonegoro / JIGN.
- Titik situs Geopark diproyeksikan langsung dari koordinat 31 situs asli Bojonegoro yang diverifikasi pada `useGeoparkSites`.
- Apabila terjadi kendala jaringan pada layer vektor Martin atau WMS, fallback SVG kartografis akurat tetap aktif tanpa merusak tata letak panggung isometrik.

---

## 10. Section 07: Panduan Eksplorasi Interaktif

### Tujuan

Menunjukkan cara menggunakan peta tanpa menjadikan bagian ini sebagai kumpulan empat kartu tutorial.

### Komposisi

Gunakan layout demonstrasi visual dengan satu panel peta di sisi kanan dan daftar langkah bernomor di sisi kiri.

Langkah yang dijelaskan:

**01. Temukan lokasi**

Cari nama desa, kecamatan, atau ruas jalan.

**02. Pilih informasi**

Aktifkan lapisan yang ingin dilihat melalui kontrol peta.

**03. Buka detail**

Pilih objek pada peta untuk membaca atribut dan informasi yang tersedia.

**04. Ubah tampilan**

Pilih peta dasar atau citra satelit apabila layanan tersebut tersedia.

Setiap langkah dapat dipilih untuk memperbarui ilustrasi atau demonstrasi di panel peta.

Gunakan transisi ringan untuk memperjelas perubahan. Jangan menggunakan animasi dekoratif yang mengganggu pembacaan atau membuat halaman terasa berat.

Jika demonstrasi peta interaktif belum tersedia, gunakan ilustrasi statis yang jelas diberi konteks. Jangan menampilkan kontrol palsu yang seolah-olah dapat digunakan.

---

## 11. Section 08: Ajakan dan Footer

### Ajakan utama

Gunakan bidang penutup yang sederhana dengan latar hijau gelap, judul yang kuat, dan satu tindakan utama.

Contoh:

**Kenali wilayah Anda melalui peta.**

Temukan jaringan jalan, informasi pembangunan, dan data spasial Kabupaten Bojonegoro dalam satu tempat.

CTA: **Buka Peta MELAROSA**

Jangan menambahkan deretan statistik atau kartu baru pada bagian ini.

### Footer

Footer memuat:
- Identitas MELAROSA.
- Pemerintah Kabupaten Bojonegoro.
- Tautan Beranda, Peta, Katalog Data, dan Geopark.
- Informasi sumber data atau kebijakan privasi jika tersedia.
- Informasi kontak atau unit pengelola yang telah diverifikasi.

Jangan mencantumkan alamat, kontak, atau tautan yang belum dipastikan benar.

---

## 12. Sistem Layout dan Tipografi

### Grid dan lebar konten

- Gunakan kontainer utama yang konsisten dengan lebar maksimum sekitar 1.280 hingga 1.440 piksel.
- Gunakan grid 12 kolom untuk menyusun komposisi desktop.
- Terapkan variasi rasio kolom sesuai kebutuhan setiap bagian.
- Gunakan ruang kosong yang cukup di antara bagian editorial.
- Hindari konten yang terlalu melebar sehingga sulit dibaca.
- Pada ponsel, gunakan padding horizontal yang konsisten dan hindari overflow horizontal.

### Tipografi

Gunakan keluarga huruf sans-serif yang mudah dibaca dan mendukung karakter bahasa Indonesia.

Hierarki yang disarankan:
- H1: besar, kuat, dan memiliki pemenggalan baris yang disengaja.
- H2: cukup besar untuk membentuk identitas visual tiap bagian.
- H3: untuk judul item atau subbagian.
- Body: nyaman dibaca dengan panjang baris yang terkendali.
- Metadata: lebih kecil tetapi tetap terbaca.

Gunakan `tabular-nums` untuk angka statistik yang perlu disejajarkan.

Jangan membuat seluruh teks menjadi kapital. Kapitalisasi penuh hanya digunakan untuk kebutuhan identitas atau metadata yang benar-benar sesuai.

### Sudut, garis, dan bayangan

- Gunakan sudut membulat secukupnya, bukan pada setiap elemen.
- Prioritaskan garis pemisah dan perubahan latar untuk membentuk struktur.
- Gunakan bayangan hanya ketika diperlukan untuk menunjukkan kedalaman atau hierarki.
- Jangan memberikan efek hover identik kepada semua elemen.

---

## 13. Interaksi, Aksesibilitas, dan Kinerja

### Interaksi

Setiap kontrol harus mempunyai tujuan yang jelas dan hasil yang terlihat.

- Navigasi mengarah ke halaman yang benar.
- Pencarian menghasilkan pilihan objek yang relevan.
- Filter memperbarui daftar data.
- Tombol reset mengembalikan filter ke kondisi awal.
- Pemilihan lokasi memperbarui peta atau membuka detail.
- Tombol coba kembali mengulangi permintaan yang gagal.

Jangan membuat elemen terlihat seperti tombol jika tidak memiliki aksi.

### Aksesibilitas

- Target sentuh minimal 44 × 44 piksel untuk kontrol utama.
- Rasio kontras teks normal minimal 4,5:1 terhadap latarnya.
- Semua kontrol interaktif dapat digunakan dengan keyboard.
- Fokus keyboard harus terlihat.
- Gunakan struktur heading yang berurutan.
- Berikan nama aksesibel pada tombol ikon.
- Gambar informatif harus memiliki teks alternatif yang sesuai.
- Informasi kondisi jalan tidak boleh disampaikan melalui warna saja.
- Hormati preferensi `prefers-reduced-motion`.

### Kinerja

- Muat peta interaktif ketika diperlukan dan hindari menginisialisasi beberapa instance peta tanpa kebutuhan.
- Gunakan lazy loading untuk gambar di bawah area awal halaman.
- Gunakan ukuran gambar yang sesuai dengan resolusi tampilan.
- Hindari permintaan data berulang akibat perubahan filter.
- Tampilkan skeleton atau placeholder dengan dimensi yang stabil.
- Pastikan kegagalan peta tidak membuat seluruh halaman kehilangan fungsi.
- Hindari animasi yang memicu perhitungan layout secara terus-menerus.

### Mode gelap

Mode gelap harus menggunakan token warna khusus, mempertahankan hierarki visual, dan menjaga keterbacaan peta.

Jangan menggunakan warna gelap yang sama untuk seluruh jenis permukaan. Bedakan latar utama, panel, dan elemen interaktif secukupnya.

---

## 14. Arsitektur Komponen Beranda

Implementasi disusun berdasarkan tanggung jawab komponen mengikuti struktur direktori proyek Nuxt yang ada:

```text
apps/web/app/
├── pages/
│   └── index.vue
├── components/
│   ├── app/
│   │   ├── PublicNavbar.vue
│   │   └── Footer.vue
│   └── landing/
│       ├── LandingHero.vue          # Hero editorial + pratinjau peta OpenLayers terintegrasi
│       ├── RegionalMetrics.vue      # Garis informasi skala wilayah & infrastruktur
│       ├── InfrastructureStory.vue  # Narasi & legenda jaringan jalan poros desa
│       ├── DistrictExplorer.vue     # Direktori interaktif 28 kecamatan
│       ├── GeoparkEditorial.vue     # Galeri editorial 31 situs Geopark
│       ├── SpatialDataStory.vue     # Simulasi 3D isometrik tumpang susun lapisan & layer ordering
│       ├── MapGuide.vue             # Panduan 4 langkah eksplorasi peta
│       └── LandingCTA.vue           # Ajakan penutup menuju peta penuh
```

Komponen yang mengambil data harus memiliki kontrak data yang jelas. Pisahkan logika pemuatan data, keadaan UI, dan presentasi sejauh diperlukan.

Jangan membuat satu komponen besar yang berisi seluruh section, semua permintaan data, dan seluruh interaksi halaman.

---

## 15. Kriteria Penerimaan Implementasi

Redesain dinyatakan memenuhi spesifikasi apabila:

- [ ] Hero memiliki hierarki visual kuat dan menempatkan peta sebagai elemen utama.
- [ ] Halaman tidak terasa seperti dashboard yang terdiri dari kumpulan kartu seragam.
- [ ] Setiap section mempunyai ritme visual berbeda tetapi tetap menggunakan sistem desain yang konsisten.
- [ ] Angka statistik bersumber dari data yang dapat diverifikasi.
- [ ] Pencarian, navigasi, filter, dan CTA berfungsi nyata.
- [ ] Direktori kecamatan dan katalog Geopark memiliki loading, empty, dan error state.
- [ ] Data yang tidak tersedia tidak digantikan oleh konten fiktif.
- [ ] Foto Geopark sesuai dengan lokasi yang dijelaskan dan memiliki sumber yang jelas.
- [ ] Peta dapat digunakan pada desktop maupun perangkat seluler.
- [ ] Mode terang dan gelap tetap terbaca.
- [ ] Kontras, target sentuh, fokus keyboard, dan reduced motion diperhatikan.
- [ ] Tidak ada overflow horizontal pada lebar layar yang didukung.
- [ ] Pemuatan peta dan gambar tidak menghambat konten utama secara berlebihan.
- [ ] Seluruh tautan dan tombol mengarah ke tujuan yang benar.
- [ ] Informasi tentang sumber data, waktu pembaruan, dan cakupan layanan tidak melebihi kemampuan sistem yang sebenarnya.

---

## 16. Instruksi Implementasi

Lakukan redesain pada halaman beranda publik MELAROSA dengan mengikuti spesifikasi ini.

1. Audit implementasi `apps/web/app/pages/index.vue` dan komponen terkait sebelum melakukan perubahan.
2. Pertahankan integrasi API, struktur data, autentikasi, routing, dan perilaku peta yang masih relevan.
3. Ubah hierarki visual, komposisi section, tipografi, jarak, dan cara penyajian data agar tidak monoton.
4. Prioritaskan data spasial aktual untuk peta dan informasi wilayah.
5. Jangan mengarang angka, foto, klasifikasi wilayah, statistik, atau hasil integrasi layanan.
6. Jangan membuat kontrol visual yang tidak berfungsi.
7. Gunakan komponen yang dapat digunakan kembali tanpa memaksakan seluruh section memiliki desain yang sama.
8. Pastikan tampilan responsif dan aksesibel.
9. Jalankan pemeriksaan build, lint, dan pengujian yang tersedia di proyek.
10. Laporkan file yang diubah, fitur yang berfungsi, keterbatasan integrasi, serta hasil pemeriksaan.

**Prinsip akhir:** MELAROSA harus terasa seperti cara masyarakat menjelajahi dan memahami Bojonegoro, bukan sekadar halaman yang menampilkan data pemerintahan. Variasi visual harus memperkuat cerita, peta harus membantu pemahaman, dan setiap interaksi harus memberikan manfaat nyata.

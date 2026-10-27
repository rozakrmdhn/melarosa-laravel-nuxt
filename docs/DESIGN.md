# Paten Desain & Master Sistem Desain UI/UX: Melarosa
**Aplikasi**: Melarosa - Sistem Informasi Geospasial & Infrastruktur Wilayah Kabupaten Bojonegoro  
**Versi Sistem**: 2.0 (Master Design Architecture & System Standards)  
**Tanggal Pengesahan**: 27 Oktober 2026  
**Status**: Standar Resmi & Mengikat (*Binding Architectural Standard*)  
**Disiplin Anti-Slop**: Lolos Otomatis (*Strict Anti-Slop Discipline, Zero Em Dash, Real Verified Evidence*)

---

## 1. Pembagian Spesifikasi Desain Menurut Domain Sistem

Untuk menjaga kejelasan arsitektur, modularitas dokumentasi, dan kemudahan pemeliharaan antarmuka, spesifikasi desain sistem Melarosa secara resmi dipisahkan ke dalam **3 dokumen paten spesifikasi domain** yang tersimpan rapi di dalam direktori `docs/`:

| Domain Antarmuka | Rute Aplikasi | Dokumen Spesifikasi Resmi | Fokus Utama & Peran Antarmuka |
|---|---|---|---|
| **1. Landing Page** | `/` | [DESIGN_LANDING.md](./DESIGN_LANDING.md) | Pintu gerbang geospasial publik, pencarian instan 28 kecamatan & koordinat WGS 84, grid asimetris 4 pilar pembangunan (konektivitas jalan poros, cagar geopark, BKKD, dan geoportal). |
| **2. Admin Dashboard** | `/admin` | [DESIGN_ADMIN.md](./DESIGN_ADMIN.md) | Pusat komando operasional spasial berbasis peran (Superadmin, BAPPEDA, Verifikator Kecamatan, Operator Desa), validasi ruas jalan poros 1.187 ruas, audit log riil, dan mode editor peta full-bleed. |
| **3. Peta Spasial WebGIS** | `/maps` | [DESIGN_MAPS.md](./DESIGN_MAPS.md) | Kanvas peta OpenLayers modern kelas dunia (*split view* desktop 400px, lembar bawah mobile 3 snap level, integrasi Martin MVT, OGC WMS Geoserver, GPS tracking, dan kompas dial). |

---

## 2. Identitas Visual & Filosofi Inti (Brand Identity)

Melarosa dirancang sebagai **sistem informasi geospasial daerah kelas dunia** yang mencerminkan keandalan data teknis pemerintahan, keanggunan kartografi modern, dan transparansi pembangunan Kabupaten Bojonegoro.

### Prinsip Inti Antarmuka:
1. **Bukti Nyata Berbasis Data Sah (*Evidence Over Claims - C-5 & R-38*)**:
   - Seluruh visualisasi dan kartu metrik dibangun atas data terverifikasi: **28** Kecamatan, **430** Desa/Kelurahan, **2.307** km² Luas Wilayah, **1.187** Ruas Jalan Poros (**1.424,8** km), dan **31** Geosite Geopark Nasional Bojonegoro.
   - Dilarang memalsukan angka, menampilkan testimonial fiktif, atau menyematkan klaim kosong.
2. **Kejelasan Hierarki Kartografi (*Cartographic Precision*)**:
   - Peta dan data tematik disajikan di atas basemap monokromatik netral (*Carto Positron / Esri Light Gray*) agar rute infrastruktur dan zona batas wilayah memiliki ketajaman visual tinggi tanpa interferensi warna latar.
3. **Ergonomi Sentuh & Bebas Tombol Mati (*Touch Ergonomics & Zero Dead Controls*)**:
   - Seluruh target interaktif berukuran minimal 44x44 piksel pada perangkat mobile.
   - Setiap elemen yang dapat diklik memiliki fungsi nyata (*zero dead buttons*).
4. **Bebas Em Dash (R-02)**:
   - Dilarang menggunakan karakter em dash (`-`). Gunakan tanda hubung `-`, koma `,`, titik dua `:`, atau kurung `()`.

---

## 3. Token Desain Terpadu (Global Design Tokens)

### 3.1. Palet Warna Resmi Kabupaten Bojonegoro

```
Obsidian Night (#03080f / #070b14) ─── Latar Belakang Kanvas Peta & Shell Admin
Slate Base (#f8fafc / #0f172a)      ─── Permukaan Netral & Tipografi Keterbacaan Tinggi
Royal Blue 600 (#2563eb / #3b82f6)  ─── Aksen Primer, Jalan Poros & Interaksi Utama
Emerald Green 600 (#16a34a)        ─── Warisan Geologi, Cagar Geopark Bojonegoro
Amber Gold 600 (#d97706)           ─── Plotting Anggaran BKKD & Infrastruktur Fisik
Rose Crimson 600 (#e11d48)         ─── Kondisi Jalan Rusak Berat & Peringatan Sistem
```

| Token Global | Mode Terang (*Light*) | Mode Gelap (*Dark*) | Alokasi Penggunaan |
|---|---|---|---|
| `color-canvas-hero` | `#03080f` | `#03080f` | Latar kanvas hero landing page |
| `color-canvas-app` | `#f8fafc` (Slate 50) | `#070b14` (Obsidian) | Kanvas aplikasi peta & admin |
| `color-surface-panel`| `#ffffff` | `#0c1424` / `#0b1322`| Panel kiri WebGIS, sidebar admin |
| `color-surface-card` | `#ffffff` | `#0f172a` | Kartu pilar, kartu metrik, dialog |
| `color-border` | `rgba(226,232,240,0.85)` | `rgba(255,255,255,0.08)`| Garis batas komponen dan tabel |
| `color-primary` | `#2563eb` (Blue 600) | `#3b82f6` (Blue 500) | Tombol aksi utama, tab aktif |
| `color-geopark` | `#16a34a` (Emerald 600)| `#10b981` (Emerald 500)| Geosite Geopark Bojonegoro |
| `color-bkkd` | `#d97706` (Amber 600) | `#f59e0b` (Amber 500) | Plotting anggaran desa |
| `color-danger` | `#ef4444` (Red 500) | `#f87171` (Red 400) | Jalan rusak berat, hapus layer |

### 3.2. Standar Tipografi Global

- **Keluarga Font**: Inter, Outfit, serta font sans-serif proporsional sistem modern.
- **Standar Angka Tabular (`tabular-nums`)**:
  - Seluruh angka koordinat geospasial WGS 84, panjang ruas kilometer/meter, luas wilayah hektar, jumlah desa/kecamatan, dan nominal rupiah anggaran BKKD **wajib disajikan dengan kelas CSS `tabular-nums`**. Hal ini menjamin lebar tiap karakter angka sejajar dan mudah dibaca secara cepat.
- **Larangan Font Monospace**:
  - Dilarang keras menggunakan font monospace (`font-mono`) untuk teks narasi, heading, kartu fitur, atau identifikasi atribut spasial; font mono hanya diizinkan untuk penulisan baris perintah kode teknis atau format data JSON murni.

### 3.3. Sistem Kelengkungan Sudut (*Border Radius Scale*)

- `rounded-full` (100% Pill Radius): Digunakan khusus untuk segmented control tab navigasi (Peta, Dataset, Geopark), chip pencarian cepat kecamatan, avatar status, dan grab handle sentuh mobile.
- `rounded-2xl` (16px) / `rounded-3xl` (24px): Kartu pilar landing page, kontainer formulir pencarian hero, dan modal dialog spasial.
- `rounded-xl` (12px): Kartu layer aktif, kontrol mengambang kanvas peta, dan kartu metrik dashboard.
- `rounded-lg` (8px): Tombol aksi sekunder, input formulir data, dan badge status KUGI.

---

## 4. Matriks Disiplin Anti-Slop Global (R-01 hingga R-38)

Seluruh komponen pada ketiga domain wajib mematuhi matriks kepatuhan antislop berikut:

| Kode Aturan | Ketentuan Kepatuhan Mutlak |
|---|---|
| **R-01: Zero AI Gradient Cliché** | Dilarang menggunakan gradien ungu-biru atau neon tanpa tujuan. Gunakan palet resmi Melarosa (Obsidian, Royal Blue, Slate, Emerald). |
| **R-02: Zero Em Dash** | Dilarang menggunakan `-` pada seluruh teks antarmuka. Gunakan `-`, `,`, `:`, atau `()`. |
| **R-03: Responsif & Target Sentuh** | Target sentuh tombol dan elemen interaktif minimal 44x44 piksel pada layar sentuh ponsel. |
| **R-05: Layout Berorientasi Konten** | Menggunakan grid asimetris (7:5 dan 6:6 kolom). Menolak template 3 kartu identik dan bento box generik. |
| **R-15: CTA Kontekstual** | Tombol aksi memiliki label spesifik ("Cari di Peta", "Lihat Peta Jalan Poros", "Verifikasi Ruas"). |
| **R-17 & R-36: Bukti Data Nyata** | Menolak angka palsu ("10k+ users", "+12% delta"). Menampilkan metrik nyata dari basis data Bojonegoro. |
| **R-18: Bebas Testimonial Fiktif** | Dilarang membuat ulasan atau testimonial rekayasa AI. |
| **R-21 & R-34: Integritas Kedua Mode Tema** | Mode Terang dan Mode Gelap wajib berfungsi 100% dengan kontras legal WCAG AA (minimal 4.5:1). |
| **R-26: Zero Dead Buttons** | Setiap kontrol, link, filter, toggle mata, dan tombol tutup memiliki handler aktif. Dilarang memasang tombol mati. |
| **R-27: Kelengkapan Tiga Status UI** | Setiap komponen data memiliki penanganan status *Loading* (skeleton), *Empty* (solutif), dan *Error* (ramah). |

---

## 5. Direktori Berkas Spesifikasi Lengkap

1. **Halaman Beranda Publik (Landing Page)**:
   - Berkas Spesifikasi: [DESIGN_LANDING.md](./DESIGN_LANDING.md)
   - Implementasi Kode: [apps/web/app/pages/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/index.vue)
2. **Admin Dashboard (Pusat Komando Spasial)**:
   - Berkas Spesifikasi: [DESIGN_ADMIN.md](./DESIGN_ADMIN.md)
   - Implementasi Kode: [apps/web/app/pages/admin.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin.vue) dan [apps/web/app/pages/admin/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/index.vue)
3. **Peta Spasial WebGIS (/maps)**:
   - Berkas Spesifikasi: [DESIGN_MAPS.md](./DESIGN_MAPS.md)
   - Implementasi Kode: [apps/web/app/pages/maps/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/maps/index.vue) dan [apps/web/app/components/maps/](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/maps/)

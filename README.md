# Melarosa GIS

Portal WebGIS dan Sistem Pemantauan Pembangunan Daerah Kabupaten Bojonegoro.

---

## 1. Overview

Melarosa GIS adalah platform geospasial terpadu yang digunakan untuk tata kelola aset infrastruktur dan pemantauan realisasi program pembangunan di Kabupaten Bojonegoro. Sistem ini mencakup 28 kecamatan dan 430 desa/kelurahan, menghubungkan perencanaan spasial dengan alokasi anggaran daerah.

Fokus operasional Melarosa GIS terbagi dalam tiga pilar:

1. **Satu Data Geospasial**: Pengelolaan jaringan jalan poros desa, batas wilayah administratif, dan integrasi katalog layer dari Simpul Jaringan Geoportal Bojonegoro (GeoServer CSW/WMS).
2. **Konektivitas Anggaran dan Titik Fisik**: Penautan usulan Bantuan Keuangan Khusus Desa (BKKD) dan pagu APBD langsung ke koordinat segmen jalan di lapangan.
3. **Pengawasan Pembangunan Bertingkat**: Alur verifikasi realisasi fisik dan serapan dana berjenjang mulai dari Operator Desa, Verifikator Kecamatan, hingga Bappeda dan Dinas Teknis terkait.

---

## 2. Tech Stack

Arsitektur sistem dibangun dalam pola monorepo terpisah antara API service, antarmuka WebGIS, dan tile server vektor:

### Backend API (`apps/api`)
- **Framework**: Laravel 13
- **Bahasa**: PHP 8.4
- **High-Performance Runtime**: Laravel Octane (dukungan Swoole dan RoadRunner)
- **Autentikasi & Otorisasi**: Laravel Sanctum, Spatie Laravel Permission
- **Audit & Deteksi Perangkat**: Matomo DeviceDetector, Trait Audit Trail kustom
- **Pengujian Otomatis**: Pest PHP 4, PHPUnit

### Frontend WebGIS (`apps/web`)
- **Framework**: Nuxt 4 (Vue 3, TypeScript)
- **Pustaka Peta**: MapLibre GL, OpenLayers
- **Komponen Antarmuka & Styling**: Nuxt UI v3, Tailwind CSS v4
- **State Management**: Pinia
- **Keamanan Client**: Nuxt Security (CSP ketat, proteksi header)
- **Pengolahan Dokumen**: SheetJS (XLSX) untuk rekapitulasi data

### Basis Data & Komponen Spasial
- **Database Relasional & GIS**: PostgreSQL 15+ dengan ekstensi PostGIS (SRID 4326 dan kalkulasi sferoid geodetik `geography`)
- **Vector Tile Server**: Martin Tile Server (Rust) untuk streaming Mapbox Vector Tiles (MVT) langsung dari tabel PostGIS
- **In-Memory Cache & Session**: Redis 7+

### Orkestrasi & Perkakas Pengembang
- **Container**: Docker & Docker Compose
- **Task Runner**: Justfile (`just`)

---

## 3. Directory Structure

```text
melarosa-laravel-nuxt/
├── apps/
│   ├── api/                           # Backend REST API (Laravel 13)
│   │   ├── app/
│   │   │   ├── Console/Commands/      # Perintah CLI (sinkronisasi katalog geoportal)
│   │   │   ├── Http/Controllers/      # Controller Admin, BKKD, Jalan, Layer, Auth
│   │   │   ├── Models/                # Model Eloquent PostGIS & Entitas Bisnis
│   │   │   ├── Policies/              # Kebijakan otorisasi Spatie RBAC berjenjang
│   │   │   ├── Services/              # Service Layer (CSW Client, GIS, Audit Log)
│   │   │   └── Traits/                # Trait Auditable
│   │   ├── database/
│   │   │   ├── migrations/            # Skema tabel spasial dan bisnis
│   │   │   └── seeders/               # Data awal referensi, role, dan user demo
│   │   ├── routes/
│   │   │   ├── api.php                # Rute API REST publik dan admin
│   │   │   ├── console.php            # Penjadwalan task cron (02:00 WIB)
│   │   │   └── web.php                # Rute sesi web
│   │   └── tests/                     # Unit & Feature automated tests
│   │
│   └── web/                           # Frontend Portal & WebGIS (Nuxt 4)
│       ├── app/
│       │   ├── components/            # Komponen peta, dialog modal, navigasi, tabel
│       │   │   └── maps/              # Kontrol peta, drawer layer, legenda
│       │   ├── composables/           # Composable state spasial, HTTP client, filter
│       │   ├── layouts/               # Layout halaman publik dan dashboard admin
│       │   ├── pages/                 # Routing Nuxt (Peta /map, Admin /admin/*)
│       │   └── stores/                # Pinia store sesi, wilayah, dan layer
│       ├── nuxt.config.ts             # Konfigurasi modul Nuxt, proxy Nitro, CSP
│       └── server.mjs                 # Server launcher produksi (Node.js ESM)
│
├── docs/                              # Dokumentasi teknis terpusat
│   ├── features/                      # Dokumentasi 11 komponen per fitur modular
│   ├── issues/                        # Rencana pengerjaan milestone sistem
│   └── README.md                      # Indeks matriks dokumentasi teknis
│
├── docker-compose.yml                 # Orkestrasi multi-service (API, Web, Martin, Redis)
├── martin.yaml                        # Konfigurasi sumber layer Martin Tile Server
├── Justfile                           # Task runner otomasi perintah monorepo
├── DESIGN.md                          # Panduan identitas visual dan token antarmuka
├── Installation.md                    # Panduan instalasi dan deployment lokal/server
└── README.md                          # Dokumen utama ini
```

---

## 4. Features

### A. WebGIS & Inventarisasi Aset Jalan Poros Desa
- **Peta Interaktif Spasial**: Visualisasi batas 28 kecamatan dan 430 desa lengkap dengan jaringan jalan poros desa.
- **Kalkulasi Geodetik Otomatis**: Panjang ruas dihitung langsung oleh fungsi PostGIS `ST_Length(geom::geography)` dalam satuan meter, mencegah kesalahan input manual.
- **Pemotongan Segmen Ruas (Split Tool)**: Membagi ruas jalan menjadi dua bagian terpisah berdasarkan koordinat titik klik mouse (*point split*) atau potongan GeoJSON (*pre-split parts*), diselesaikan dalam satu transaksi database atomik.
- **Filter Wilayah dan Kondisi**: Pengelompokan visual berdasarkan perkerasan (Aspal, Rigid Beton, Makadam, Tanah) dan kondisi eksisting (Baik, Sedang, Rusak Ringan, Rusak Berat).
- **Performa Tinggi via Vector Tiles**: Streaming data spasial skala besar ditangani oleh Martin Tile Server dalam format MVT tanpa membebani query relasional.

### B. Sinkronisasi Simpul Jaringan Geoportal
- **Pemanenan Katalog Otomatis**: Terhubung langsung ke endpoint CSW (Catalogue Service for the Web 2.0.2) Geoportal Kabupaten Bojonegoro untuk mengambil metadata layer OGC WMS GeoServer.
- **Sinkronisasi Manual & Terjadwal**: Pemicu sinkronisasi instan via halaman admin `/admin/layers` serta otomasi harian setiap pukul 02:00 WIB melalui command console.
- **Audit Log Pemanenan**: Pencatatan riwayat eksekusi ke tabel `geoportal_sync_logs` dengan rincian metrik jumlah layer ditemukan, ditambahkan, diperbarui, dan durasi eksekusi.
- **Penyajian Layer Tematik Publik**: Katalog peta dapat dibuka di antarmuka publik `/map` lengkap dengan informasi atribusi OPD dan gambar legenda resmi.

### C. Pengelolaan Bantuan Keuangan Khusus Desa (BKKD)
- **Pencatatan Proposal Usulan Desa**: Input proposal pembangunan desa berbasis lokasi titik koordinat dan lampiran dokumen teknis.
- **Verifikasi Bertingkat OPD**: Form telaah teknis dinas terkait, penentuan kelayakan, catatan rekomendasi, dan penyesuaian pagu anggaran.
- **Rekapitulasi Desa & Keputusan TAPD**: Matriks usulan per kecamatan/desa dengan seleksi 1 desa 1 usulan prioritas untuk penetapan Surat Keputusan (SK) APBD, dilengkapi fitur ekspor berkas Excel.
- **Audit Mutasi & Optimistic Locking**: Setiap revisi usulan mencatat alasan perubahan ke histori audit, diproteksi penguncian versi (HTTP 409 Conflict) untuk mencegah penimpaan data antar-verifikator secara bersamaan.

### D. Perencanaan Pagu Anggaran & Realisasi Fisik
- **Plotting Anggaran ke Segmen Jalan**: Menautkan sumber dana (APBD Kabupaten, BKKD, DAK, Bantuan Provinsi) langsung ke ruas fisik jalan di peta.
- **Pelaporan Progres Realisasi**: Input berkala persentase capaian fisik pekerjaan dan realisasi keuangan.
- **Pengarsipan Berita Acara**: Unggah bukti pendukung resmi serah terima pekerjaan (BA-MC, BAP, BAST) dan dokumentasi visual lapangan.
- **Alur Persetujuan Tiga Tahap**: Berkas diverifikasi berurutan dari Desa menuju Kecamatan, lalu disahkan oleh Bappeda.

### E. Administrasi, Hak Akses & Keamanan
- **Role-Based Access Control (RBAC)**: Pengaturan izin akses granular Spatie Permission untuk peran Superadmin, Bappeda, Verifikator Kecamatan, dan Operator Desa.
- **Isolasi Wilayah Operator**: Operator desa terkunci pada wilayah administratif masing-masing untuk menjaga kerahasiaan dan integritas data desa.
- **Pelacakan Sesi Pengguna**: Pemantauan sesi aktif, jenis perangkat, peramban, dan alamat IP melalui modul sesi Laravel Sanctum.
- **Pendaftaran Terkendali**: Penutupan registrasi mandiri publik untuk mencegah akses akun tidak terverifikasi ke sistem pemerintahan.
- **Audit Trail Terpusat**: Setiap operasi penambahan, perubahan, dan penghapusan data tercatat secara otomatis pada tabel `audit_logs`.

---

## 5. Dokumentasi Terkait

- Panduan instalasi dan setup lokal/Docker: [Installation.md](./Installation.md)
- Indeks dokumentasi teknis dan standar pengujian: [docs/README.md](./docs/README.md)
- Dokumentasi fitur spesifik: [docs/features/](./docs/features/)
- Panduan desain antarmuka WebGIS: [DESIGN.md](./DESIGN.md)

# 🌍 Melarosa GIS — Portal Geospasial & Monitoring Infrastruktur

[![Laravel 13](https://img.shields.io/badge/Laravel-v13-ff2e21.svg)](https://laravel.com)
[![Nuxt 4](https://img.shields.io/badge/Nuxt-v4-04C690.svg)](https://nuxt.com)
[![PostGIS](https://img.shields.io/badge/PostGIS-Spatial_Data-336791.svg)](https://postgis.net/)
[![Martin](https://img.shields.io/badge/Martin-Vector_Tiles-F39C12.svg)](https://maplibre.org/martin/)
[![Tailwind CSS 4](https://img.shields.io/badge/TailwindCSS-v4-38B2AC.svg)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-Proprietary-blue.svg)](#lisensi)

**Melarosa GIS** adalah platform enterprise Sistem Informasi Geospasial (WebGIS) dan Monitoring Infrastruktur resmi Pemerintah Kabupaten Bojonegoro. Sistem ini mengintegrasikan pemetaan spasial wilayah administratif (28 Kecamatan, 430+ Desa/Kelurahan), inventarisasi jaringan jalan poros desa, perencanaan plotting anggaran, serta monitoring realisasi fisik & keuangan dengan alur verifikasi berjenjang.

---

<!-- TOC -->

- [Gambaran Umum](#gambaran-umum)
- [Modul & Fitur Utama](#modul--fitur-utama)
- [Arsitektur Layanan & Alokasi Port](#arsitektur-layanan--alokasi-port)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Konfigurasi Environment (Local vs Production)](#konfigurasi-environment-local-vs-production)
- [Panduan Instalasi & Menjalankan](#panduan-instalasi--menjalankan)
    - [1. Standalone (Local Development)](#1-standalone-local-development)
    - [2. Docker Setup](#2-docker-setup)
- [Struktur Proyek Monorepo](#struktur-proyek-monorepo)
- [Peran Pengguna & Alur Kerja (RBAC)](#peran-pengguna--alur-kerja-rbac)
- [Dokumentasi Tambahan](#dokumentasi-tambahan)
- [Lisensi](#lisensi)

<!-- /TOC -->

---

## Gambaran Umum

Kabupaten Bojonegoro memiliki cakupan wilayah seluas lebih dari 2.300 km² yang terbagi menjadi 28 kecamatan dan lebih dari 430 desa/kelurahan. Melarosa GIS dirancang untuk menjawab tantangan pengelolaan data aset jalan, transparansi penganggaran, dan akuntabilitas realisasi pembangunan daerah:

1. **Satu Data Spasial**: Standardisasi batas wilayah dan koordinat jalan poros berbasis proyeksi WGS 84.
2. **Kinerja Tinggi**: Render jutaan titik dan segmen garis secara instan menggunakan Mapbox Vector Tiles (MVT) yang disajikan langsung oleh Martin Tile Server dari database PostGIS.
3. **Akuntabilitas & Transparansi**: Integrasi plotting pagu anggaran per segmen jalan hingga pengawasan progres fisik mingguan/bulanan berjenjang.

---

## Modul & Fitur Utama

### 🗺️ 1. WebGIS Interaktif & Pemetaan Spasial
* **Batas Wilayah Administratif**: Peta batas kecamatan dan desa terintegrasi dari data resmi Pemkab dan BPS.
* **Jaringan Jalan Poros Desa**: Visualisasi segmen jalan lengkap dengan data atribut kondisi perkerasan (Aspal, Rigid Beton, Makadam, Tanah).
* **Martin Tile Server (PostGIS MVT)**: Pemrosesan tile vektor on-the-fly tanpa beban berlebih pada database utama.
* **Fitur Peta Lengkap**:
  * Pilihan basemap beragam (OpenStreetMap, Carto Dark/Light, ESRI World Imagery).
  * Feature inspection / identify popup dengan ringkasan status segmen.
  * Geometri editing: snapping, splitting segmen jalan, dan validasi topologi garis.
  * Simbologi kustom dinamis berdasarkan tipe konstruksi dan sumber pendanaan.

### 💰 2. Perencanaan & Plotting Anggaran
* **Master Tipe Infrastruktur**: Klasifikasi standar konstruksi jalan dan jembatan.
* **Referensi Sumber Dana**: Pengelompokan sumber pendanaan (APBD Kabupaten, BKKD, DAK, Bantuan Keuangan Provinsi, dll.).
* **Plotting Anggaran Per Segmen**: Alokasi pagu anggaran tahun berjalan langsung ditautkan ke segmen fisik di peta.
* **Dashboard Metrik Finansial**: Ringkasan total pagu, jumlah segmen terdanai, dan kalkulasi serapan anggaran secara real-time.

### 📑 3. Monitoring Realisasi & Verifikasi Berjenjang
* **Alur Verifikasi Bertingkat**:
  $$\text{Operator Desa} \longrightarrow \text{Verifikator Kecamatan} \longrightarrow \text{Bappeda / Dinas Teknis}$$
* **Input Progres Fisik & Keuangan**: Pembaruan berkala persentase progres fisik dan realisasi pencairan dana.
* **Unggah Berita Acara (BA)**: Lampiran bukti fisik serah terima pekerjaan (BA-MC, BAP, BAST) dan dokumentasi foto lapangan.
* **Riwayat Revisi (Revision Audit Log)**: Catatan histori perbaikan jika verifikasi ditolak atau memerlukan perbaikan berkas.

### 🛡️ 4. Audit Trail & Keamanan Sistem
* **Automated Audit Logging**: Pencatatan riwayat setiap aksi tambah, ubah, dan hapus pada data spasial maupun administratif menggunakan `Auditable` trait.
* **Role-Based Access Control (RBAC)**: Pengelolaan hak akses granular berbasis Spatie Laravel Permissions (Superadmin, Bappeda, Kecamatan, Desa).
* **Security Hardening**:
  * Proteksi sesi Sanctum dan cookie CSRF.
  * Rate limiting pada endpoint login, register, dan reset password.
  * Kebijakan Content Security Policy (CSP) ketat pada frontend Nuxt.

---

## Arsitektur Layanan & Alokasi Port

Setiap service dikonfigurasi secara dinamis melalui environment variable:

| Service | Port | Default URL | Environment Variable | Keterangan |
| :--- | :---: | :--- | :--- | :--- |
| **Laravel API** | `9000` | `http://localhost:9000` | `SERVER_PORT=9000` / `OCTANE_PORT=9000` | Backend API REST & Laravel Octane (Swoole) |
| **Nuxt UI** | `4000` | `http://localhost:4000` | `PORT=4000` / `NUXT_PORT=4000` / `NITRO_PORT=4000` | Frontend Web & WebGIS Portal |
| **Martin Serve** | `9090` | `http://localhost:9090` | `MARTIN_PORT=9090` / `MARTIN_LISTEN_ADDRESSES=0.0.0.0:9090` | PostGIS Vector Tile Server |
| **Redis** | `6379` | `localhost:6379` | `REDIS_PORT=6379` | Cache, Session Driver & Queue Worker |

---

## Kebutuhan Sistem

- **PHP 8.4+** dengan ekstensi `pdo_pgsql`, `redis`, `swoole` (opsional untuk Octane)
- **Node.js 20+** (atau [Bun](https://bun.com))
- **PostgreSQL 15+** dengan ekstensi **PostGIS** aktif
- **Martin Tile Server** ([Unduh binary](https://github.com/maplibre/martin/releases) atau via Docker)
- **Redis 7+**
- **Docker & Docker Compose** (jika menggunakan deployment container)
- **just** task runner (opsional namun sangat disarankan)

---

## Konfigurasi Environment (Local vs Production)

Konfigurasi lingkungan telah dipisahkan secara terstruktur di seluruh level monorepo:

| Lingkup | Konfigurasi Lokal | Konfigurasi Produksi | File Aktif |
| :--- | :--- | :--- | :--- |
| **Root (Docker/Orchestrator)** | `.env.local` | `.env.production` | `.env` |
| **Backend API (`apps/api/`)** | `apps/api/.env.local` | `apps/api/.env.production` | `apps/api/.env` |
| **Frontend Web (`apps/web/`)** | `apps/web/.env.local` | `apps/web/.env.production` | `apps/web/.env` |

Template acuan tersimpan pada file `.env.*.example`. Anda dapat beralih lingkungan aktif dengan cepat menggunakan task runner:

```bash
# Beralih ke konfigurasi LOCAL
just env-local

# Beralih ke konfigurasi PRODUCTION
just env-prod
```

---

## Panduan Instalasi & Menjalankan

### 1. Standalone (Local Development)

<details open>
<summary><b>Langkah-langkah Instalasi Standalone</b></summary>

#### A. Persiapan Dependensi
```bash
# Install dependensi backend
cd apps/api
composer install

# Install dependensi frontend
cd ../web
npm install
cd ../..
```

#### B. Setup Environment
```bash
# Aktifkan konfigurasi lokal
just env-local
# (Atau salin manual .env.local ke .env di root, apps/api, dan apps/web)
```

#### C. Inisialisasi Database & Key
Pastikan database PostgreSQL `db_melarosa` telah dibuat dengan ekstensi PostGIS:
```sql
CREATE DATABASE db_melarosa;
\c db_melarosa;
CREATE EXTENSION postgis;
```

Jalankan inisialisasi Laravel:
```bash
cd apps/api
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
cd ../..
```

#### D. Menjalankan Layanan
Buka 3 terminal terpisah:

```bash
# Terminal 1: Laravel API (Port 9000)
cd apps/api
php artisan serve
# Atau menggunakan Octane:
# php artisan octane:start

# Terminal 2: Nuxt UI (Port 4000)
cd apps/web
npm run dev

# Terminal 3: Martin Tile Server (Port 9090)
martin --config martin.yaml
```

Akses portal di browser melalui: **`http://localhost:4000`**

</details>

---

### 2. Docker Setup

Jika Anda menggunakan Docker, seluruh stack (*API, Web, Martin, Redis*) sudah terangkum dalam [`docker-compose.yml`](file:///c:/laragon/www/melarosa-laravel-nuxt/docker-compose.yml) dan dikelola via `just`.

```bash
# Inisialisasi awal (salin env, build image, install vendor, migrate, seed)
just init

# Jalankan seluruh stack
just up -d

# Menjalankan migrasi database
just a migrate --seed

# Melihat status container
just status

# Menghentikan container
just stop
```

---

## Struktur Proyek Monorepo

```text
melarosa-laravel-nuxt/
├── apps/
│   ├── api/                      # Backend Service (Laravel 13)
│   │   ├── app/
│   │   │   ├── Http/Controllers/ # Controller Admin, Auth, API Spasial
│   │   │   ├── Models/           # Model Batas Wilayah, Jalan, Infrastruktur, BA
│   │   │   ├── Policies/         # Kebijakan otorisasi verifikasi berjenjang
│   │   │   ├── Services/         # Service layer (Martin, Audit Log, Navigasi)
│   │   │   └── Traits/           # Auditable trait
│   │   ├── config/               # Konfigurasi CORS, Octane, Sanctum, Session
│   │   └── database/migrations/  # Migrasi PostGIS spasial & tabel bisnis
│   │
│   └── web/                      # Frontend Service (Nuxt 4)
│       ├── app/
│       │   ├── components/       # Komponen WebGIS, MapCanvas, Panel Filter, Dialog
│       │   ├── composables/      # Logika interaksi API infrastruktur & GIS
│       │   ├── pages/            # Halaman Publik, Peta Interaktif, & Dashboard Admin
│       │   └── stores/           # Pinia store (Auth, Wilayah, Layer)
│       ├── nuxt.config.ts        # Konfigurasi Nuxt, Proxy Nitro, CSP
│       └── server.mjs            # Production server runner (Node 24 ESM fix)
│
├── docs/                         # Dokumentasi teknis, schema, dan alur verifikasi
├── martin.yaml                   # Konfigurasi Tile Server PostGIS MVT
├── docker-compose.yml            # Orkestrasi container multi-service
├── Justfile                      # Task runner otomasi monorepo
├── DESIGN.md                     # Panduan desain antarmuka & token warna WebGIS
└── README.md                     # Dokumentasi utama proyek
```

---

## Peran Pengguna & Alur Kerja (RBAC)

1. **Superadmin**: Akses penuh konfigurasi sistem, audit log, manajemen user, dan master layer.
2. **Bappeda / Dinas Teknis**:
   - Menetapkan master tipe infrastruktur dan sumber dana.
   - Mengalokasikan plotting pagu anggaran per segmen jalan.
   - Melakukan verifikasi akhir (*Approval Bappeda*) atas laporan realisasi fisik dan Berita Acara.
3. **Kecamatan**:
   - Melakukan verifikasi administratif (*Approval Kecamatan*) atas laporan progres dari desa-desa di bawah naungannya.
   - Memberikan catatan revisi jika data fisik lapangan belum sesuai.
4. **Desa**:
   - Menginputkan usulan atau progres pengerjaan segmen jalan desa.
   - Mengunggah Berita Acara dan dokumentasi foto fisik pekerjaan.
5. **Publik (Tanpa Login)**:
   - Mengakses peta interaktif Kabupaten Bojonegoro.
   - Melihat transparansi jaringan jalan dan status pembangunan daerah.

---

## Dokumentasi Tambahan

- [Panduan Desain Antarmuka (`DESIGN.md`)](file:///c:/laragon/www/melarosa-laravel-nuxt/DESIGN.md)
- [Dokumentasi Fitur Audit Trail (`docs/features/audit-logs.md`)](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/features/audit-logs.md)
- [Skema Monitoring Realisasi & Berita Acara (`monitoring_realisasi_schema.md`)](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)

---

## Lisensi

Sistem ini dikembangkan khusus untuk pengelolaan informasi spasial dan infrastruktur daerah Kabupaten Bojonegoro. Hak cipta dilindungi oleh peraturan dan ketentuan yang berlaku.

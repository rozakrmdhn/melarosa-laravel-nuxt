# Panduan Instalasi Melarosa GIS

Panduan teknis langkah demi langkah untuk menyiapkan, mengonfigurasi, dan menjalankan lingkungan pengembangan (*local development*) maupun lingkungan container Docker Melarosa GIS.

Untuk gambaran umum proyek, arsitektur, dan daftar modul fitur, lihat [README.md](./README.md).

---

## 1. Kebutuhan Sistem

Pastikan perangkat kerja Anda telah terpasang dependensi berikut sebelum memulai instalasi:

| Komponen | Versi Minimal | Keterangan |
|---|---|---|
| **PHP** | 8.4+ | Ekstensi wajib: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `curl`, `redis`, `gd` atau `imagick` |
| **Composer** | 2.7+ | Manajer dependensi PHP |
| **Node.js** | 20+ (atau Bun 1.1+) | Runtime JavaScript/TypeScript untuk frontend Nuxt 4 |
| **PostgreSQL** | 15+ | Wajib mengaktifkan ekstensi geospasial **PostGIS** |
| **Martin Tile Server** | 0.14+ | Binary vector tile server MVT ([Rilis Martin](https://github.com/maplibre/martin/releases)) |
| **Redis** | 7+ | Session driver, cache, dan antrean queue worker |
| **Docker & Compose** | Docker 24+, Compose v2 | Opsional, jika menggunakan instalasi container |
| **Just** | 1.25+ | Opsional, task runner otomasi perintah monorepo ([Instalasi Just](https://github.com/casey/just)) |

---

## 2. Alokasi Port & Arsitektur Layanan

Sistem Melarosa GIS terdiri dari beberapa layanan independen dengan konfigurasi port default:

| Layanan | Port Default | URL Akses | Variabel Lingkungan |
|---|:---:|---|---|
| **Nuxt 4 WebGIS** | `4000` | `http://localhost:4000` | `PORT=4000` / `NUXT_PORT=4000` |
| **Laravel 13 API** | `9000` | `http://localhost:9000` | `SERVER_PORT=9000` / `OCTANE_PORT=9000` |
| **Martin Tile Server** | `9090` | `http://localhost:9090` | `MARTIN_PORT=9090` / `MARTIN_LISTEN_ADDRESSES` |
| **PostgreSQL & PostGIS** | `5432` | `localhost:5432` | `DB_PORT=5432` |
| **Redis** | `6379` | `localhost:6379` | `REDIS_PORT=6379` |

---

## 3. Konfigurasi Environment (.env)

Proyek ini menggunakan konfigurasi multi-environment terpisah antara root monorepo, backend API, dan frontend Web:

| Lingkup | Berkas Acuan | Berkas Aktif | Keterangan |
|---|---|---|---|
| **Root (Orkestrator)** | `.env.local.example` | `.env` | Variabel Docker, port mapping, dan Martin |
| **Backend API** | `apps/api/.env.local.example` | `apps/api/.env` | Database PostgreSQL, Sanctum, Redis, S3 |
| **Frontend Web** | `apps/web/.env.local.example` | `apps/web/.env` | Endpoint API URL, tile server URL, map token |

### Menyiapkan Berkas Environment

Jika Anda menggunakan task runner `just`:
```bash
# Otomatis menyalin .env.local ke .env pada seluruh subdirektori
just env-local
```

Atau salin berkas secara manual:
```bash
# Salin konfigurasi root
cp .env.local.example .env

# Salin konfigurasi backend API
cp apps/api/.env.local.example apps/api/.env

# Salin konfigurasi frontend Web
cp apps/web/.env.local.example apps/web/.env
```

Pastikan kredensial koneksi database pada `apps/api/.env` sesuai dengan konfigurasi PostgreSQL lokal Anda:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_melarosa
DB_USERNAME=postgres
DB_PASSWORD=secret
```

---

## 4. Opsi A: Instalasi Manual (Local Development)

Gunakan metode ini jika Anda menjalankan PHP, Node.js, PostgreSQL, dan Redis langsung pada host mesin Anda (misal: Laragon, Homebrew, atau instalasi native).

### Langkah 1: Persiapan Database PostgreSQL & PostGIS

Masuk ke konsol PostgreSQL (psql / pgAdmin) dan buat database dengan ekstensi PostGIS:
```sql
CREATE DATABASE db_melarosa;
\c db_melarosa;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
```

### Langkah 2: Setup Backend Laravel API

1. Masuk ke direktori backend:
   ```bash
   cd apps/api
   ```

2. Pasang dependensi PHP melalui Composer:
   ```bash
   composer install
   ```

3. Buat kunci enkripsi aplikasi dan tautkan direktori penyimpanan berkas publik:
   ```bash
   php artisan key:generate
   php artisan storage:link
   ```

4. Jalankan migrasi tabel spasial PostGIS dan seeding data awal:
   ```bash
   php artisan migrate --seed
   ```

5. Kembali ke root proyek:
   ```bash
   cd ../..
   ```

### Langkah 3: Setup Frontend Nuxt 4

1. Masuk ke direktori frontend:
   ```bash
   cd apps/web
   ```

2. Pasang dependensi Node.js:
   ```bash
   npm install
   # Atau jika menggunakan bun:
   # bun install
   ```

3. Kembali ke root proyek:
   ```bash
   cd ../..
   ```

### Langkah 4: Menjalankan Seluruh Layanan

Buka 3 jendela terminal terpisah:

**Terminal 1: Backend Laravel API**
```bash
cd apps/api
php artisan serve --port=9000
# Atau gunakan Laravel Octane jika Swoole terpasang:
# php artisan octane:start --port=9000
```

**Terminal 2: Frontend Nuxt WebGIS**
```bash
cd apps/web
npm run dev
# Nuxt akan berjalan di http://localhost:4000
```

**Terminal 3: Martin Tile Server**
Pastikan binary `martin` telah terpasang di sistem PATH, lalu jalankan dari root direktori proyek:
```bash
martin --config martin.yaml
# Martin akan melayani tile MVT di http://localhost:9090
```

---

## 5. Opsi B: Instalasi Menggunakan Docker

Jika Anda menggunakan Docker dan Docker Compose, seluruh komponen (*API, Web, Redis, Martin*) terangkum dalam berkas `docker-compose.yml`.

### Langkah 1: Inisialisasi Otomatis via Just

Jika task runner `just` terpasang:
```bash
# Inisialisasi awal (salin env, build image, install dependensi, key generate, storage link)
just init

# Jalankan seluruh container di latar belakang
just up -d

# Eksekusi migrasi dan database seeder
just a migrate --seed
```

### Langkah 2: Menjalankan Secara Manual via Docker Compose

Jika tidak menggunakan `just`:
```bash
# 1. Pastikan seluruh berkas .env sudah disalin (lihat Bagian 3)

# 2. Build dan jalankan seluruh container
docker compose --env-file apps/api/.env up -d --build

# 3. Jalankan migrasi dan seeding di dalam container API
docker compose --env-file apps/api/.env exec laravelnuxt.api php artisan migrate --seed

# 4. Tautkan storage symlink jika belum ada
docker compose --env-file apps/api/.env exec laravelnuxt.api php artisan storage:link
```

### Perintah Operasional Docker (`just`)

| Perintah | Fungsi |
|---|---|
| `just status` | Menampilkan status running/stopped container |
| `just logs` | Memantau log gabungan container secara realtime |
| `just a <perintah>` | Menjalankan perintah `php artisan` di container API |
| `just test` | Menjalankan pengujian otomatis Pest PHP di container |
| `just stop` | Menghentikan container tanpa menghapus data volume |
| `just down` | Menghentikan dan menghapus container |

---

## 6. Verifikasi Instalasi & Kredensial Awal

Setelah semua layanan berjalan, lakukan pengujian akses:

1. **Frontend Web & WebGIS Portal**:
   - URL: `http://localhost:4000`
   - Buka halaman peta `/map` untuk memastikan layer basemap dan simpul jaringan termuat.

2. **Dashboard Administrasi**:
   - URL: `http://localhost:4000/admin/login`
   - Kredensial akun Super Administrator awal (hasil database seeder):
     - **Email**: `admin@admin.com`
     - **Password**: `password`

3. **Status Tile Server Martin**:
   - Buka `http://localhost:9090/catalog` di browser.
   - Endpoint harus menampilkan daftar sumber tabel PostGIS yang tersedia.

4. **Kesehatan API Backend**:
   - Buka `http://localhost:9000/up` untuk memeriksa health check Laravel.

---

## 7. Catatan Teknis & Pemecahan Masalah (Troubleshooting)

### Galat Driver PostgreSQL (`could not find driver pgsql`)
- **Penyebab**: Ekstensi PostgreSQL PHP belum diaktifkan pada lingkungan host.
- **Solusi**: Buka berkas `php.ini` aktif Anda, hapus tanda titik koma (uncomment) pada baris berikut, lalu restart web server/PHP service:
  ```ini
  extension=pdo_pgsql
  extension=pgsql
  ```

### Galat PostGIS Function (`function st_length does not exist`)
- **Penyebab**: Ekstensi PostGIS belum terpasang atau belum diaktifkan di database `db_melarosa`.
- **Solusi**: Eksekusi perintah `CREATE EXTENSION postgis;` di database yang bersangkutan melalui `psql`.

### Konflik Port Layanan
- Jika port default (`4000`, `9000`, atau `9090`) telah digunakan oleh aplikasi lain di komputer Anda:
  1. Ubah nomor port pada berkas `.env` terkait (misal ubah `PORT=4001` atau `SERVER_PORT=9001`).
  2. Sesuaikan konfigurasi proxy di `apps/web/nuxt.config.ts` agar mengarah ke port baru tersebut.

### Izin Akses Folder Storage Laravel
- Pada lingkungan Linux/macOS, pastikan folder cache dan penyimpanan berkas dapat ditulis:
  ```bash
  chmod -R 775 apps/api/storage apps/api/bootstrap/cache
  ```

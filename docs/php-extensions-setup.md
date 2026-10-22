# 🐘 Panduan Konfigurasi Ekstensi PHP (php.ini)

Panduan ini mendokumentasikan daftar ekstensi PHP dan konfigurasi `php.ini` yang wajib dan disarankan untuk menjalankan backend **Laravel 13 (Melarosa API)**, khususnya pada **PHP 8.4.x** (termasuk versi patch keamanan **8.4.27**) atau versi yang lebih baru.

---

## ⚡ Cara Cepat untuk Pengguna Laragon (Windows)

Jika Anda memperbarui PHP di Laragon dari `php-8.4.22` ke `php-8.4.27`:
1. Ekstrak zip PHP baru ke folder `C:\laragon\bin\php\php-8.4.27-Win32-vs17-x64`.
2. Salin (*copy*) file `php.ini` yang sudah berjalan dari folder lama:
   * **Dari:** `C:\laragon\bin\php\php-8.4.22-Win32-vs17-x64\php.ini`
   * **Ke:** `C:\laragon\bin\php\php-8.4.27-Win32-vs17-x64\php.ini`
3. Buka file `php.ini` baru tersebut, lalu cocokkan dengan daftar ekstensi di bawah ini.
4. Di Laragon: klik kanan ikon tray -> **PHP** -> **Version** -> pilih **php-8.4.27**.
5. Restart Laragon (*Stop* lalu *Start All*).

---

## 📋 Daftar Ekstensi PHP yang Perlu Diaktifkan

Buka file `php.ini`, cari baris-baris berikut, dan pastikan tanda titik koma (`;`) di awal baris telah dihapus:

### 1. Database Driver (PostgreSQL & SQLite)
Aplikasi ini menggunakan PostgreSQL (`db_melarosa`) sebagai database utama dan SQLite untuk pengujian otomatis:

```ini
; Wajib untuk koneksi database PostgreSQL utama
extension=pdo_pgsql
extension=pgsql

; Wajib untuk Unit & Feature Testing (Pest / SQLite in-memory)
extension=pdo_sqlite
extension=sqlite3

; Opsional jika menggunakan database MySQL/MariaDB
extension=pdo_mysql
extension=mysqli
```

### 2. Pengolahan Citra & Media (`intervention/image`)
Aplikasi menggunakan `intervention/image` untuk resize gambar, foto dokumentasi infrastruktur, dan upload berkas:

```ini
; Wajib untuk manipulasi, cropping, dan resize gambar
extension=gd

; Wajib untuk membaca metadata dan rotasi otomatis foto kamera smartphone
extension=exif
```

### 3. Core Laravel & Integrasi Service
Dibutuhkan oleh framework Laravel 13, Laravel Sanctum, Socialite, AWS S3 Flysystem, dan HTTP Client (Guzzle):

```ini
; Komunikasi HTTP keluar (Socialite OAuth, Geoportal API, S3)
extension=curl

; Deteksi MIME type file upload yang aman
extension=fileinfo

; Internasionalisasi, penomoran, format tanggal, dan transliterasi
extension=intl

; Manipulasi string multi-byte UTF-8
extension=mbstring

; Enkripsi token Sanctum, password hashing, dan HTTPS
extension=openssl

; Database Abstraction Layer dasar
extension=pdo

; Perhitungan presisi tinggi (koordinat dan kalkulasi finansial)
extension=bcmath
```

### 4. Manajemen Paket & Arsip (Composer & Export)

```ini
; Wajib agar Composer dapat mengekstrak paket dist berkecepatan tinggi
extension=zip
```

### 5. Optimasi Performa (Zend OPcache)
Di bagian bawah file `php.ini` (bagian `Zend Extensions`):

```ini
zend_extension=opcache
```

---

## ⚙️ Rekomendasi Nilai Direktif `php.ini`

Sesuaikan pengaturan parameter berikut di `php.ini` agar proses migrasi data spasial, testing, dan upload file dokumentasi berjalan optimal:

```ini
; Batas memori PHP (hindari batas default 128M agar proses build / test tidak crash)
memory_limit = 512M

; Batas ukuran upload file (default PHP hanya 2M)
upload_max_filesize = 64M
post_max_size = 64M

; Batas waktu eksekusi script (detik)
max_execution_time = 120
max_input_time = 120

; Timezone server (sesuaikan dengan APP_TIMEZONE di .env)
date.timezone = Asia/Jakarta

; Pengaturan OPcache
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
```

---

## 🐧 Referensi untuk Linux / Docker Deployment (VPS)

Jika aplikasi di-*deploy* ke server Linux (Ubuntu/Debian) atau Docker container:

```bash
# Ubuntu / Debian dengan repository Ondrej Surý (PHP 8.4)
sudo apt update
sudo apt install -y \
  php8.4-cli \
  php8.4-fpm \
  php8.4-pgsql \
  php8.4-sqlite3 \
  php8.4-gd \
  php8.4-curl \
  php8.4-mbstring \
  php8.4-xml \
  php8.4-zip \
  php8.4-bcmath \
  php8.4-intl \
  php8.4-opcache
```

---

## 🔍 Verifikasi Konfigurasi

Jalankan perintah berikut di terminal (PowerShell / Command Prompt) pada direktori `apps/api`:

```powershell
# 1. Periksa versi PHP aktif
php -v

# 2. Periksa apakah modul PostgreSQL sudah aktif
php -m | findstr "pgsql"

# 3. Periksa apakah modul GD (gambar) sudah aktif
php -m | findstr "gd"

# 4. Validasi kesiapan seluruh dependensi Laravel
composer check-platform-reqs
```

Jika semua baris pada `composer check-platform-reqs` berstatus `success`, environment PHP Anda telah terkonfigurasi dengan benar dan aman untuk dijalankan.

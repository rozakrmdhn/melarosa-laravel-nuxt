# Modul Backup & Restore Management

Dokumentasi arsitektur, konfigurasi environment, operasional harian, dan prosedur disaster recovery untuk modul **Backup & Restore Management** pada platform Melarosa (Laravel 13 + Nuxt 4 + PostgreSQL/PostGIS).

## 1. Ringkasan Arsitektur

Modul dirancang terpisah dalam lapisan tanggung jawab agar aman di environment production:

- **Konfigurasi & Storage Privat**
  - [`apps/api/config/backup.php`](../../apps/api/config/backup.php) mengelola lokasi disk lokal (`backups` -> `storage/app/private/backups`), disk remote opsional (`BACKUP_REMOTE_DISK`), binary PostgreSQL (`pg_dump`, `pg_restore`, `psql`), konfigurasi enkripsi, retensi bawaan, dan kontrol keamanan restore.
  - Seluruh arsip backup disimpan di luar direktori publik (`public/`) dan di luar web root.
- **Layanan Inti (`App\Services\Backup\*`)**
  - `BackupEncryptionService`: enkripsi/dekripsi streaming chunked `AES-256-GCM` dengan header `MLRBK001`, per-file HKDF salt, AAD chunk index + flag final untuk menolak tampering maupun pemotongan file.
  - `BackupStorageService`: penyimpanan lokal/remote, proteksi path traversal, perhitungan SHA-256, pembersihan workspace temporary, dan pengukuran kapasitas disk riil.
  - `BackupArchiveBuilder`: pembuatan arsip file dengan filter pengecualian (`backups`, `temporary`, `framework`, `logs`, `cache`, `sessions`, `node_modules`, `vendor`, `*.log`, `*.tmp`), validasi ZipSlip saat ekstraksi, pembuatan `manifest.json` dan `checksums.sha256`.
  - `PostgresCliHelper`: deteksi binary client PostgreSQL lintas OS (Linux/Docker/Windows), injeksi password aman melalui variabel lingkungan `PGPASSWORD` (tidak pernah melalui argumen CLI atau log), serta inspeksi versi server dan ekstensi PostGIS.
  - `BackupService`: orkestrasi backup (`database`, `file`, `full`), antrean lock anti-duplikasi job, retensi cleanup, dan pemeriksaan kesehatan sistem/jadwal.
  - `RestoreService`: validasi integritas pra-restore (SHA-256, dekripsi, internal checksum, versi PG/PostGIS), registrasi upload eksternal aman, pembuatan *pre-restore safety backup* otomatis, eksekusi `pg_restore` ke database terisolasi maupun target maintenance, serta verifikasi pasca-restore (`ST_IsValid` geometri dan jumlah tabel).
- **Antrean, Scheduler & Audit**
  - `RunBackupJob` dan `RunRestoreJob` berjalan asinkron melalui queue Laravel.
  - Perintah CLI: `backup:run`, `backup:cleanup`, dan `backup:check-health` terdaftar di [`apps/api/routes/console.php`](../../apps/api/routes/console.php).
  - Seluruh operasi sensitif dicatat ke `audit_logs` via `AuditLogService` dan mengirim notifikasi ke Super Admin (`admin`).

## 2. Konfigurasi Environment (.env)

Tambahkan variabel berikut pada `.env` Laravel (`apps/api/.env`):

```dotenv
BACKUP_ENABLED=true
BACKUP_DISK=backups
BACKUP_REMOTE_DISK=
BACKUP_REMOTE_PREFIX=melarosa-backups
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_KEY=base64:ISI_DENGAN_KUNCI_32_BYTE_ACAJK
BACKUP_PG_DUMP_BINARY=/usr/bin/pg_dump
BACKUP_PG_RESTORE_BINARY=/usr/bin/pg_restore
BACKUP_PSQL_BINARY=/usr/bin/psql
BACKUP_RETENTION_DATABASE_DAYS=14
BACKUP_RETENTION_FILE_DAYS=7
BACKUP_RETENTION_FULL_DAYS=56
BACKUP_UPLOAD_MAX_SIZE_MB=2048
BACKUP_DOWNLOAD_URL_TTL_MINUTES=10
BACKUP_RESTORE_ALLOW_OVERWRITE_ACTIVE_DB=false
BACKUP_NOTIFICATION_CHANNELS=database,mail
BACKUP_QUEUE_NAME=backups
```

### Menghasilkan `BACKUP_ENCRYPTION_KEY`

Jalankan perintah berikut sekali saat instalasi dan simpan nilainya di secret manager / `.env` terpisah dari lokasi arsip backup:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

> **Penting:** Jangan pernah menyimpan `BACKUP_ENCRYPTION_KEY` di repository Git atau di dalam file arsip backup. Tanpa kunci ini, arsip terenkripsi `.zip.enc` tidak dapat didekripsi.

## 3. Instalasi & Setup Server / Docker

1. Jalankan migrasi tabel backup dan notifikasi:
   ```bash
   php artisan migrate
   php artisan db:seed --class=BackupScheduleSeeder
   ```
2. Pastikan PostgreSQL client tools (`pg_dump`, `pg_restore`, `psql`) terpasang pada runtime (sudah termasuk pada Dockerfile Sail melalui `postgresql-client`).
3. Jalankan worker antrean dan scheduler di server / Docker Compose:
   ```bash
   php artisan queue:work --queue=backups,default --timeout=1800
   php artisan schedule:work
   ```
4. Untuk environment production dengan Nginx, sesuaikan `client_max_body_size` agar selaras dengan `BACKUP_UPLOAD_MAX_SIZE_MB` jika fitur upload arsip besar digunakan.

## 4. Keamanan & Otorisasi

- **Akses Terbatas:** Seluruh route API `/api/v1/admin/backups*` dan halaman `/admin/backups` hanya dapat diakses oleh pengguna dengan role `admin` (Super Admin). Pengguna dengan role lain akan menerima respons `403 Forbidden`, dan menu `Backup & Restore` disembunyikan secara otomatis dari navigasi backend.
- **Unduhan Arsip:** Endpoint unduhan memerlukan sesi Super Admin yang terotentikasi ditambah URL bertanda tangan HMAC dengan masa berlaku terbatas (`download-url` -> `download`). Arsip tetap diunduh dalam format terenkripsi AES-256-GCM.
- **Rate Limiting:** Endpoint mutasi backup dibatasi 10 permintaan/menit dan endpoint validasi/restore dibatasi 5 permintaan/menit.

## 5. Prosedur Operasional Backup & Restore

### Jadwal Bawaan (`Asia/Jakarta`)
- **Database Backup:** Setiap hari pukul `01:00 WIB`, retensi `14` hari.
- **File Storage Backup:** Setiap hari pukul `02:00 WIB`, retensi `7` hari.
- **Full Backup:** Setiap hari Minggu pukul `03:00 WIB`, retensi `56` hari (8 minggu).
- **Retention Cleanup:** Berjalan setiap hari pukul `04:00 WIB` dan melewati arsip yang masih dipakai oleh proses restore aktif.

### Prosedur Restore yang Aman
1. Buka **Admin > Backup & Restore** menggunakan akun Super Admin.
2. Pilih arsip yang tersedia atau unggah file arsip eksternal melalui **Upload & Restore**.
3. Klik **Periksa Integritas** untuk memastikan:
   - Checksum SHA-256 cocok.
   - Dekripsi AES-256-GCM berhasil dengan kunci server.
   - Versi PostgreSQL dan ekstensi PostGIS kompatibel.
4. Uji terlebih dahulu ke **Target Database Terisolasi** (mis. `melarosa_restore_verify`) lalu tinjau hasil tabel dan validasi geometri (`ST_IsValid`) di tab **Riwayat Restore**.
5. Jika diperlukan pemulihan ke database/storage aktif saat jam maintenance:
   - Set `BACKUP_RESTORE_ALLOW_OVERWRITE_ACTIVE_DB=true` sementara pada `.env` server.
   - Pastikan opsi **Pre-Restore Safety Backup** tetap aktif.
   - Ketik frasa konfirmasi `KONFIRMASI RESTORE` sebelum mengeksekusi.
   - Kembalikan `BACKUP_RESTORE_ALLOW_OVERWRITE_ACTIVE_DB=false` setelah pemulihan terverifikasi selesai.

## 6. Hasil Verifikasi & Pengujian

- **Backend Automated Tests (`php artisan test`):** `93 passed (591 assertions)` meliputi:
  - Enkripsi multi-chunk `AES-256-GCM`, deteksi bit korup/potong, dan penolakan kunci tidak valid (`BackupEncryptionServiceTest`).
  - Otorisasi Super Admin (`401`/`403` dan filtering navigasi), backup `database` / `file` (pengecualian `backups`, `temporary`, `logs`) / `full`, signed download, jadwal otomatis & retensi cleanup, proteksi hapus saat restore aktif, upload eksternal, dan konfirmasi restore bertingkat (`BackupManagementTest`).
  - Uji integrasi nyata pada PostgreSQL 16 + PostGIS (`pg_dump -Fc` -> `.zip.enc` -> `pg_restore` ke database terisolasi dengan verifikasi `ST_IsValid`, sequence, dan DDL migrasi `up`/`down`) (`PostgresPostgisBackupRestoreIntegrationTest`).
- **Frontend Production Build (`apps/web`: `npx nuxi build`):** Selesai dengan status exit `0`.
- **Audit UI Antislop Mode 2:** Terdokumentasi pada [`anti-slop/audit-025-2026-11-14.md`](../../anti-slop/audit-025-2026-11-14.md).


# PROMPT IMPLEMENTASI FITUR BACKUP & RESTORE MANAGEMENT
## Laravel + Nuxt + PostgreSQL/PostGIS

### 1. Tujuan Pengembangan

Implementasikan modul **Backup & Restore Management** sebagai bagian dari sistem administrasi aplikasi berbasis Laravel Backend, Nuxt Frontend, dan PostgreSQL/PostGIS.

Modul ini digunakan untuk mengelola pencadangan dan pemulihan data sistem secara terstruktur, aman, otomatis, dan dapat diaudit.

Buat implementasi production-ready dengan memperhatikan keamanan server, integritas database, efisiensi proses, serta kemudahan operasional administrator.

### 2. Prinsip Implementasi

Sebelum melakukan perubahan:

1. Analisis struktur repository dan arsitektur aplikasi yang sudah tersedia.
2. Identifikasi versi Laravel, PHP, Nuxt, PostgreSQL, dan PostGIS.
3. Pelajari pola autentikasi, authorization, API response, database migration, dan struktur service yang sudah digunakan.
4. Identifikasi lokasi penyimpanan dokumen dan file upload.
5. Jangan mengubah arsitektur existing tanpa alasan teknis yang kuat.
6. Gunakan kembali komponen UI, layout, composables, dan service yang sudah tersedia.
7. Jangan membuat data dummy untuk menggantikan proses backup yang sebenarnya.
8. Pastikan seluruh implementasi kompatibel dengan versi dependency yang terpasang.

### 3. Fitur Frontend — Nuxt

Buat halaman administrasi dengan nama:

`Backup & Restore Management`

#### A. Dashboard Backup

Tampilkan informasi:

- Waktu backup terakhir.
- Status backup terakhir.
- Total jumlah backup.
- Total ukuran penyimpanan.
- Jadwal backup berikutnya.
- Status koneksi dan kesehatan proses backup.
- Kapasitas penyimpanan yang tersedia jika dapat diperoleh secara aman.

Gunakan kartu statistik yang ringkas dan profesional.

#### B. Backup Manual

Sediakan tombol untuk menjalankan backup secara manual.

Pilihan jenis backup:

- Database Backup.
- File Backup.
- Full Backup.

Ketentuan:

- Tampilkan dialog konfirmasi.
- Proses backup berjalan secara asynchronous melalui Laravel Queue.
- Tampilkan status proses: Pending, Processing, Completed, Failed.
- Tampilkan notifikasi ketika proses selesai.
- Cegah duplikasi pekerjaan backup yang tidak diperlukan.
- Jangan menjalankan proses backup berat secara langsung pada HTTP request.

#### C. Backup Schedule

Buat halaman pengaturan jadwal backup.

Pengaturan meliputi:

- Enable/Disable Automatic Backup.
- Frekuensi: Daily, Weekly, Monthly.
- Waktu pelaksanaan.
- Jenis backup.
- Retention policy.
- Jumlah backup yang dipertahankan.

Default konfigurasi:

- Database backup: setiap hari pukul 01.00.
- File backup: setiap hari pukul 02.00.
- Full backup: setiap minggu.
- Retensi database: 14 hari.
- Retensi file: 7 hari.
- Retensi full backup: 8 minggu.

Jadwal menggunakan timezone Asia/Jakarta.

#### D. Backup History

Buat tabel riwayat backup dengan kolom:

- Nama backup.
- Jenis backup.
- Tanggal dan waktu.
- Ukuran file.
- Durasi proses.
- Status.
- Pengguna yang menjalankan.
- Action.

Action yang tersedia:

- Detail.
- Download.
- Delete.

Tambahkan pencarian, filter berdasarkan jenis backup, filter status, dan pagination.

#### E. Restore Management

Sediakan fitur pemulihan dari backup yang tersedia.

Alur restore:

1. Administrator memilih file backup.
2. Sistem menampilkan detail backup.
3. Sistem melakukan validasi integritas file.
4. Tampilkan peringatan bahwa restore dapat menggantikan data existing.
5. Administrator melakukan konfirmasi berlapis.
6. Proses restore dijalankan melalui background worker atau prosedur operasional khusus.
7. Sistem mencatat hasil restore.
8. Tampilkan status proses dan laporan hasilnya.

Tambahkan fitur upload backup eksternal dengan validasi format, ukuran, checksum, dan metadata.

Jangan pernah mengizinkan restore dari file yang belum tervalidasi.

### 4. Backend — Laravel

Gunakan pendekatan modular dan Service Layer.

Rekomendasi struktur:

- `app/Services/Backup/BackupService.php`
- `app/Services/Backup/RestoreService.php`
- `app/Services/Backup/BackupStorageService.php`
- `app/Jobs/RunBackupJob.php`
- `app/Jobs/RunRestoreJob.php`
- `app/Console/Commands/BackupCleanupCommand.php`
- `app/Http/Controllers/Api/BackupController.php`
- `app/Http/Requests/Backup/`
- `app/Models/BackupHistory.php`
- `app/Models/BackupSchedule.php`

Sesuaikan dengan konvensi repository existing.

#### A. Database Backup

Gunakan PostgreSQL native utility:

- `pg_dump`
- `pg_restore`
- `pg_dumpall` hanya jika memang dibutuhkan untuk komponen cluster/global objects.

Gunakan format custom PostgreSQL:

`pg_dump -Fc`

Ketentuan:

- Backup seluruh schema dan data yang diperlukan.
- Pertahankan struktur tabel, index, constraint, sequence, foreign key, dan function.
- Sertakan extension PostGIS beserta metadata yang diperlukan.
- Pastikan seluruh data geometri dan atribut spasial tetap utuh.
- Pertimbangkan kebutuhan role dan privilege saat proses restore.
- Simpan informasi versi PostgreSQL dan PostGIS pada metadata backup.
- Gunakan konfigurasi koneksi yang aman.
- Hindari memasukkan password database ke dalam command-line argument atau log.
- Gunakan process execution yang aman dengan argument escaping, timeout, dan validasi exit code.

#### B. File Backup

Backup direktori file penting seperti:

- Storage uploads.
- Dokumen pendukung.
- Lampiran.
- Gambar.
- File hasil unggahan pengguna.

Jangan memasukkan cache, temporary files, session, log, atau direktori dependency yang tidak diperlukan.

Gunakan konfigurasi direktori backup yang fleksibel.

#### C. Full Backup

Gabungkan:

- Database PostgreSQL.
- File aplikasi yang diperlukan.
- Metadata backup.
- Checksum file.

Gunakan struktur arsip yang konsisten dan mudah diverifikasi.

#### D. Encryption

Terapkan enkripsi backup menggunakan standar modern seperti AES-256.

Ketentuan:

- Encryption key tidak disimpan bersama file backup.
- Simpan secret di environment atau secret manager.
- Jangan mencatat secret ke log.
- Verifikasi integritas sebelum proses dekripsi dan restore.

#### E. Backup Retention

Implementasikan pembersihan backup lama secara otomatis.

Ketentuan:

- Berdasarkan jenis backup.
- Berdasarkan jumlah hari atau jumlah file.
- Jangan menghapus backup terbaru yang masih diperlukan.
- Jangan menghapus backup yang sedang digunakan proses restore.
- Catat aktivitas penghapusan.
- Pastikan penghapusan tidak melanggar kebijakan retensi.

### 5. API Endpoint

Buat endpoint REST API dengan autentikasi dan authorization.

Contoh endpoint:

GET `/api/v1/admin/backups`

GET `/api/v1/admin/backups/statistics`

GET `/api/v1/admin/backups/{id}`

POST `/api/v1/admin/backups`

GET `/api/v1/admin/backups/{id}/download`

DELETE `/api/v1/admin/backups/{id}`

GET `/api/v1/admin/backups/schedules`

PUT `/api/v1/admin/backups/schedules`

POST `/api/v1/admin/backups/restore`

POST `/api/v1/admin/backups/upload`

GET `/api/v1/admin/backups/jobs/{id}`

Sesuaikan endpoint dengan standar API existing.

### 6. Database Schema

Buat migration untuk tabel:

**backup_histories**

- id
- backup_name
- backup_type
- storage_disk
- storage_path
- file_size
- checksum
- status
- started_at
- completed_at
- duration_seconds
- triggered_by
- error_message
- metadata
- created_at
- updated_at

**backup_schedules**

- id
- name
- backup_type
- frequency
- scheduled_time
- timezone
- retention_days
- is_active
- last_run_at
- next_run_at
- created_at
- updated_at

**backup_restore_histories**

- id
- backup_history_id
- restored_by
- status
- started_at
- completed_at
- error_message
- metadata
- created_at
- updated_at

Tambahkan index dan foreign key yang sesuai.

Pastikan informasi sensitif tidak tersimpan dalam metadata maupun error log.

### 7. Security & Authorization

Wajib menerapkan:

- Hanya Super Admin yang dapat mengakses modul.
- Validasi authorization di backend, bukan hanya menyembunyikan menu frontend.
- Validasi file upload.
- Validasi path untuk mencegah path traversal.
- Proteksi terhadap command injection.
- Rate limiting untuk operasi sensitif.
- Audit log untuk backup, download, upload, delete, dan restore.
- Konfirmasi berlapis untuk restore.
- Penyimpanan backup di luar public directory.
- Signed URL atau mekanisme download yang aman.
- Backup remote sebagai salinan terpisah dari VPS utama.

Jangan pernah menyediakan endpoint yang memungkinkan administrator menjalankan arbitrary shell command.

### 8. Restore Safety

Restore merupakan operasi kritis.

Implementasikan prosedur:

1. Validasi backup.
2. Verifikasi checksum.
3. Verifikasi kompatibilitas versi PostgreSQL.
4. Verifikasi extension PostGIS.
5. Buat backup kondisi database saat ini sebelum restore.
6. Jalankan restore pada lingkungan terisolasi atau melalui prosedur maintenance.
7. Validasi struktur database setelah restore.
8. Validasi tabel spasial dan data geometri.
9. Catat seluruh hasil restore.

Untuk production, utamakan restore ke database terpisah terlebih dahulu. Jangan langsung menimpa database aktif melalui request HTTP.

### 9. Monitoring & Notification

Tambahkan monitoring:

- Backup berhasil.
- Backup gagal.
- Backup tidak berjalan sesuai jadwal.
- Storage hampir penuh.
- Restore berhasil/gagal.

Gunakan Laravel Notification atau mekanisme notifikasi existing.

### 10. UI/UX Design Direction

Gunakan desain:

- Modern Enterprise Administration.
- Minimalis dan profesional.
- Konsisten dengan design system aplikasi.
- Responsive desktop, tablet, dan mobile.
- Status menggunakan badge.
- Tabel memiliki pagination dan filter.
- Dialog konfirmasi untuk tindakan destruktif.
- Progress indicator untuk proses asynchronous.
- Empty state dan error state yang informatif.

Hindari desain dashboard yang terlalu ramai.

### 11. Testing & Acceptance Criteria

Implementasikan pengujian:

1. Backup database berhasil.
2. Backup file berhasil.
3. Full backup berhasil.
4. Backup dapat diunduh oleh Super Admin.
5. Backup terenkripsi dan checksum valid.
6. Backup otomatis berjalan sesuai jadwal.
7. Retention policy bekerja.
8. Restore berhasil pada database pengujian.
9. Data PostGIS tetap valid setelah restore.
10. Pengguna non-Super Admin ditolak.
11. File backup yang rusak ditolak.
12. Operasi backup dan restore tercatat di audit log.

### 12. Deliverables

Hasil akhir pengembangan harus mencakup:

1. Analisis arsitektur existing.
2. Database migrations.
3. Laravel services dan jobs.
4. Artisan commands dan scheduler.
5. REST API.
6. Nuxt pages dan components.
7. Authorization dan audit log.
8. Konfigurasi environment.
9. Dokumentasi instalasi dan deployment.
10. Dokumentasi backup dan restore.
11. Test cases dan hasil pengujian.

### Instruksi Akhir

Jangan hanya menghasilkan mockup atau contoh kode.

Implementasikan fitur secara bertahap dan benar-benar terintegrasi dengan sistem existing.

Prioritaskan MVP yang berfungsi terlebih dahulu: Manual Backup → Automatic Backup → Backup History → Download → Restore.

Setelah setiap tahap, lakukan pengujian dan laporkan file yang dibuat, file yang diubah, konfigurasi yang diperlukan, serta hasil pengujian.

Jangan melakukan restore, penghapusan data, atau perubahan database production tanpa persetujuan eksplisit administrator.
# Audit Logs

## Tujuan

Menyediakan jejak aktivitas dan riwayat perubahan data penting pada sistem monitoring geospasial—termasuk perubahan geometri spasial, atribut wilayah/infrastruktur, serta manajemen akses pengguna—agar admin dan surveyor dapat menelusuri kronologi perubahan data.

## Fitur Saat Ini

- List audit log
- Filter berdasarkan user, module, event, rentang tanggal, dan pencarian keyword
- Detail audit log
- Perbandingan payload `before` dan `after` (termasuk deteksi perubahan atribut & properti spasial)
- Meta tambahan (misal: koordinat/geometri diff, total feature affected)
- Pencatatan IP address dan User Agent

## Halaman dan Route

- Frontend Nuxt: `/admin/audit-logs`
- API Endpoint: `api/v1/admin/audit-logs` (list) & `api/v1/admin/audit-logs/{id}` (show)

## Permission

- `audit-logs-view` / `audit-logs-access`

## Modul yang Diterapkan

Audit log diterapkan pada modul-modul geospasial dan sistem pendukung berikut:

1. **Batas Wilayah Desa (`batas-wilayah-desa`)**
   - Create, update atribut & batas polygon desa
   - Split geometri batas wilayah desa
   - Hapus data batas desa
2. **Batas Wilayah Kecamatan (`batas-wilayah-kecamatan`)**
   - Create, update atribut & batas wilayah kecamatan
   - Hapus data batas kecamatan
3. **Jalan Poros Desa (`jalan-poros-desa`)**
   - Create, update geometri linestring & kondisi/panjang ruas jalan
   - Split segmen/ruas jalan poros
   - Hapus data ruas jalan
4. **Manajemen Pengguna (`users`)**
   - Tambah pengguna baru, update profil/status
   - Update peran pengguna (assign roles)
   - Hapus akun pengguna
5. **Peran & Hak Akses (`roles` & `permissions`)**
   - Tambah, ubah permission matrix, dan hapus role
6. **Autentikasi & Sesi (`auth`)**
   - Login, logout, dan pemutusan sesi perangkat (device disconnect)

## Integrasi Data

- Tabel `audit_logs`
- Relasi ke model `User` (`user_id`)
- Relasi polymorphic (`auditable_type` & `auditable_id`) ke model yang diaudit (misal: `BatasWilayahDesa`, `BatasWilayahKecamatan`, `JalanPorosDesa`, `User`)

## Efek Bisnis Penting

- **Integritas Data Spasial**: Memastikan setiap pembaruan atau pemecahan (*split*) batas wilayah dan infrastruktur jalan memiliki jejak siapa yang mengubah dan kapan dilakukan.
- **Akuntabilitas & Verifikasi**: Audit log bukan pengganti authorization, melainkan alat verifikasi dan pembuktian jika terjadi sengketa batas atau ketidaksesuaian data spasial di lapangan.

## Batasan Saat Ini

- Cakupan event bergantung pada pemanggilan `AuditLogService` atau model yang telah dipasangi trait otomatisasi (`Auditable`).
- Payload geometri berukuran besar (koordinat kompleks) perlu disederhanakan/diringkas agar tidak membebani ukuran database audit log.

## File Sentral

- `apps/api/app/Http/Controllers/Admin/AuditLogController.php`
- `apps/api/app/Services/AuditLogService.php`
- `apps/api/app/Models/AuditLog.php`


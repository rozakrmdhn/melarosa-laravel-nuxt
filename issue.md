# [TASK] Review & Standarisasi Akses Group (Roles) Berdasarkan Permissions (Frontend & Backend)

> **Dokumen Panduan Implementasi Teknis**  
> **Target Pelaksana:** Junior Programmer / Budget-tier AI Model (Claude 3.5 Haiku, GPT-4o-mini, Gemini 1.5 Flash)  
> **Status:** Siap Dikerjakan (Ready for Implementation)  
> **Prioritas:** Tinggi (Security & Authorization Foundation)  
> **Workspace:** `apps/api` (Laravel 13 + Spatie Permission) & `apps/web` (Nuxt 4 + Pinia + Nuxt UI)

---

## 1. Ringkasan Eksekutif & Latar Belakang

### 1.1 Masalah Saat Ini
Sistem otorisasi Melarosa GIS menggunakan kombinasi **Role (Akses Group)** dan **Permission (Hak Akses)** berbasis package `spatie/laravel-permission` di backend dan composable `usePermission()` / middleware Nuxt di frontend. Namun, saat ini terdapat beberapa celah arsitektur dan inkonsistensi yang menyebabkan error otorisasi:

1. **Inkonsistensi Konvensi Penamaan (Dot Notation vs Kebab-Case):**
   - Di `DatabaseSeeder.php` tersimpan format kebab-case: `'roles-view'`, `'roles-manage'`.
   - Di `apps/api/routes/web.php` middleware memeriksa dot notation: `permission:roles.view`, `roles.manage|roles.create`.
   - Di `AdminNavigationService.php` memeriksa campuran: `'roles.view'`, `'roles.manage'` berdampingan dengan `'users-view'`, `'permissions-view'`.
   - Di frontend `apps/web/app/pages/admin/roles/index.vue` memeriksa: `permission: "roles.view"`.
   - **Dampak Fatal:** Pengguna non-admin (seperti `verifierBappeda` atau `verifierKecamatan`) yang telah diberi permission di database akan selalu mendapat **HTTP 403 Forbidden** atau menu hilang dari sidebar karena string permission tidak cocok!

2. **Missing Permissions pada Seeder:**
   - Izin `dashboard-view` dicek di navigasi dan halaman utama admin, namun belum didaftarkan di seeder awal.
   - Beberapa izin granular (seperti `roles-create`, `roles-edit`, `roles-delete`) belum terdaftar secara eksplisit di seeder.

3. **UI Pengelolaan Akses Group (`/admin/roles`):**
   - Pengelompokan (grouping) permission di modal create/edit role masih mengandalkan parsing string parsial manual (`.` dan `-`).
   - Belum ada fitur bulk toggle *"Pilih Semua"* per modul yang mempercepat kerja admin.
   - Belum ada proteksi visual yang jelas pada role sistem bawaan (`admin`).

### 1.2 Tujuan Pekerjaan
1. Menstandarisasi **100% penamaan permission** ke satu konvensi tunggal: **kebab-case** (`<modul>-<action>`), contoh: `roles-view`, `roles-create`, `roles-update`, `roles-delete`, `dashboard-view`.
2. Menyinkronkan database seeder backend agar idempotent dan lengkap.
3. Menyelaraskan seluruh route middleware backend di `apps/api/routes/web.php`.
4. Menyelaraskan route metadata di Nuxt `apps/web/app/pages/admin/**`.
5. Menyempurnakan UI Matrix Hak Akses pada Halaman Role (`/admin/roles` dan `/admin/roles/[id]`).
6. Memastikan role `admin` aman dari modifikasi tak sengaja dan role yang memiliki anggota tidak bisa dihapus sembarangan.

---

## 2. Master Dictionary Hak Akses (Permissions Taxonomy)

Seluruh permission dalam sistem Melarosa **WAJIB** mengikuti format kebab-case baku berikut:

| Kategori / Modul | Nama Permission (Baku) | Deskripsi Hak Akses | Default Role Assignment |
| :--- | :--- | :--- | :--- |
| **Dashboard & Sistem** | `dashboard-view` | Melihat ringkasan halaman dashboard admin | `admin`, `verifierBappeda`, `verifierKecamatan`, `user` |
| | `audit-logs-view` | Melihat riwayat aktivitas log sistem | `admin` |
| | `settings-view` | Melihat konfigurasi sistem | `admin` |
| | `settings-manage` | Mengubah konfigurasi sistem | `admin` |
| **Manajemen Pengguna** | `users-view` | Melihat daftar akun pengguna | `admin` |
| | `users-create` | Membuat akun pengguna baru | `admin` |
| | `users-update` | Mengubah profil & hak pengguna | `admin` |
| | `users-delete` | Menghapus akun pengguna | `admin` |
| **Akses Group (Roles)** | `roles-view` | Melihat daftar akses group | `admin` |
| | `roles-create` | Menambah akses group baru | `admin` |
| | `roles-update` | Mengubah akses group & izinnya | `admin` |
| | `roles-delete` | Menghapus akses group kustom | `admin` |
| | `roles-manage` | Izin agregat untuk kelola role | `admin` |
| **Hak Akses (Permissions)**| `permissions-view` | Melihat daftar master permissions | `admin` |
| | `permissions-manage`| Mengelola master permissions | `admin` |
| **Katalog Peta (Layers)** | `layers-view` | Melihat konfigurasi layer spasial | `admin`, `verifierBappeda` |
| | `layers-create` | Menambah layer baru | `admin` |
| | `layers-update` | Mengubah urutan/status layer | `admin` |
| | `layers-delete` | Menghapus layer | `admin` |
| | `layers-manage` | Mengelola urutan / reorder layer | `admin` |
| **Batas Wilayah** | `batas-kecamatan-view` | Melihat layer batas kecamatan | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `batas-kecamatan-manage` | Mengelola geometri kecamatan | `admin` |
| | `batas-desa-view` | Melihat layer batas desa | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `batas-desa-manage` | Mengelola geometri & split batas desa| `admin` |
| | `jalan-poros-desa-view` | Melihat peta jalan poros desa | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `jalan-poros-desa-manage`| Mengedit atribut jalan poros desa | `admin` |
| | `jalan-poros-desa-split` | Melakukan pemotongan (split) ruas jalan | `admin` |
| **Master Infrastruktur** | `infrastruktur-tipe-view` | Melihat master tipe infrastruktur | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `infrastruktur-tipe-manage` | Menambah & mengubah tipe infrastruktur| `admin`, `verifierBappeda` |
| | `ref-sumber-dana-view` | Melihat daftar sumber dana | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `ref-sumber-dana-manage` | Mengelola referensi sumber dana | `admin`, `verifierBappeda` |
| **Plotting Anggaran** | `plotting-anggaran-view` | Melihat data plotting anggaran | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `plotting-anggaran-manage` | Menginput alokasi anggaran ruas | `admin`, `verifierBappeda` |
| **Segmen Teknis (Spasial)**| `infrastruktur-segmen-view` | Melihat data teknis & peta segmen | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `infrastruktur-segmen-create` | Menggambar segmen ruas baru | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `infrastruktur-segmen-update` | Mengubah atribut & geometri segmen | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `infrastruktur-segmen-delete` | Menghapus data segmen | `admin`, `verifierBappeda` |
| **State Machine Verifikasi**| `infrastruktur-segmen-verify-kecamatan` | Melakukan verifikasi tingkat Kecamatan | `admin`, `verifierKecamatan` |
| | `infrastruktur-segmen-verify-bappeda` | Melakukan verifikasi tingkat Bappeda | `admin`, `verifierBappeda` |
| **Monitoring Realisasi** | `monitoring-realisasi-view` | Melihat berita acara realisasi fisik | `admin`, `verifierBappeda`, `verifierKecamatan` |
| | `monitoring-realisasi-manage`| Membuat & update berita acara realisasi | `admin`, `verifierBappeda` |

---

## 3. Matriks Hak Akses Per Role Bawaan Sistem

1. **Role: `admin` (Super Administrator)**
   - Role bawaan sistem (`system role`).
   - Memiliki bypass Gate: `isAdmin() === true` selalu diizinkan.
   - Hak akses database: Diberikan **seluruh permission**.
   - Aturan khusus: **TIDAK BISA DIHAPUS** dan **NAMA TIDAK BISA DIUBAH**.

2. **Role: `verifierBappeda` (Verifikator Kabupaten / Bappeda)**
   - Tugas: Melakukan supervisi seluruh kecamatan, memplot anggaran, menyetujui berita acara realisasi, dan melakukan verifikasi akhir segmen.
   - Permissions:
     - `dashboard-view`
     - `infrastruktur-tipe-view`, `infrastruktur-tipe-manage`
     - `ref-sumber-dana-view`, `ref-sumber-dana-manage`
     - `plotting-anggaran-view`, `plotting-anggaran-manage`
     - `infrastruktur-segmen-view`, `infrastruktur-segmen-create`, `infrastruktur-segmen-update`, `infrastruktur-segmen-delete`
     - `infrastruktur-segmen-verify-bappeda`
     - `monitoring-realisasi-view`, `monitoring-realisasi-manage`
     - `batas-kecamatan-view`, `batas-desa-view`, `jalan-poros-desa-view`

3. **Role: `verifierKecamatan` (Verifikator Kecamatan)**
   - Tugas: Melakukan verifikasi awal terhadap usulan segmen di wilayah kecamatannya.
   - Permissions:
     - `dashboard-view`
     - `infrastruktur-tipe-view`, `ref-sumber-dana-view`, `plotting-anggaran-view`
     - `infrastruktur-segmen-view`, `infrastruktur-segmen-create`, `infrastruktur-segmen-update`
     - `infrastruktur-segmen-verify-kecamatan`
     - `monitoring-realisasi-view`
     - `batas-kecamatan-view`, `batas-desa-view`, `jalan-poros-desa-view`

4. **Role: `user` (Pengguna Terdaftar / Publik)**
   - Tugas: Pengguna dasar sistem.
   - Permissions:
     - `dashboard-view`

---

## 4. Rincian Berkas yang Disentuh (File Map)

### Backend (`apps/api`)
1. [apps/api/database/seeders/DatabaseSeeder.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/database/seeders/DatabaseSeeder.php)  
   *Tindakan:* Perbarui daftar master permission, tambahkan `dashboard-view`, `roles-create`, `roles-update`, `roles-delete`.
2. [apps/api/database/seeders/InfrastrukturPermissionSeeder.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/database/seeders/InfrastrukturPermissionSeeder.php)  
   *Tindakan:* Pastikan penugasan permission `verifierBappeda` dan `verifierKecamatan` menggunakan nama standar dan mencakup `dashboard-view`.
3. [apps/api/routes/web.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/routes/web.php)  
   *Tindakan:* Ganti semua middleware `roles.view`, `roles.manage|roles.create`, `roles.manage|roles.edit`, `roles.manage|roles.delete` menjadi kebab-case: `roles-view`, `roles-manage|roles-create`, `roles-manage|roles-update`, `roles-manage|roles-delete`.
4. [apps/api/app/Services/AdminNavigationService.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Services/AdminNavigationService.php)  
   *Tindakan:* Sesuaikan string permission menu `roles.view` & `roles.manage` menjadi `roles-view` & `roles-manage`. Pastikan menu `Dashboard` mengecek `dashboard-view`.
5. [apps/api/app/Http/Controllers/Admin/RoleController.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Http/Controllers/Admin/RoleController.php)  
   *Tindakan:* Pastikan proteksi role `admin` tidak bisa di-update/delete, serta audit log mencatat event dengan benar.

### Frontend (`apps/web`)
1. [apps/web/app/pages/admin/roles/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/roles/index.vue)  
   *Tindakan:* Ganti `definePageMeta` permission menjadi `roles-view`. Perbarui komputasi `canCreate`, `canEdit`, `canDelete` ke format kebab-case. Perbaiki fungsi `getPermissionGroupKey` dan `getPermissionAction`.
2. [apps/web/app/pages/admin/roles/[id].vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/roles/[id].vue)  
   *Tindakan:* Ganti `definePageMeta` permission menjadi `roles-view`. Perbarui `canEdit` ke `roles-update` / `roles-manage`.
3. [apps/web/app/pages/admin/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/index.vue)  
   *Tindakan:* Ganti `dashboard.view` menjadi `dashboard-view`, ganti pengecekan `roles.view` pada quick action card menjadi `roles-view`.

---

## 5. Tahapan Implementasi Langkah-demi-Langkah (Step-by-Step Guide)

> 💡 **Petunjuk untuk Junior / AI:** Ikuti langkah-langkah di bawah secara berurutan. Jangan mengubah file lain di luar instruksi.

---

### Langkah 1: Perbarui Database Seeder Backend
**Tujuan:** Memastikan tabel `permissions` dan relasinya terisi seluruh hak akses baku dan tidak ada duplikasi.

1. Buka berkas [apps/api/database/seeders/DatabaseSeeder.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/database/seeders/DatabaseSeeder.php).
2. Perbarui array `$permissions` pada method `run()`:
   ```php
   // Default core permissions
   $permissions = [
       'dashboard-view',
       'users-view',
       'users-create',
       'users-update',
       'users-delete',
       'roles-view',
       'roles-create',
       'roles-update',
       'roles-delete',
       'roles-manage',
       'permissions-view',
       'permissions-manage',
       'settings-view',
       'settings-manage',
       'audit-logs-view',
       'layers-view',
       'layers-create',
       'layers-update',
       'layers-delete',
       'layers-manage',
   ];
   ```
3. Berikan permission dasar ke role `user`:
   ```php
   $userRole->syncPermissions(['dashboard-view', 'users-view']);
   ```
4. Buka berkas [apps/api/database/seeders/InfrastrukturPermissionSeeder.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/database/seeders/InfrastrukturPermissionSeeder.php).
5. Pada penugasan `$verifierBappeda->syncPermissions([...])` dan `$verifierKecamatan->syncPermissions([...])`, tambahkan:
   - `'dashboard-view'`
   - `'batas-kecamatan-view'`
   - `'batas-desa-view'`
   - `'jalan-poros-desa-view'`
6. Jalankan command seeder di terminal `apps/api`:
   ```powershell
   cd c:\laragon\www\melarosa-laravel-nuxt\apps\api
   php artisan permission:cache-reset
   php artisan db:seed --class=DatabaseSeeder
   ```

---

### Langkah 2: Sinkronisasi Route Middleware di `apps/api/routes/web.php`
**Tujuan:** Mengubah middleware otorisasi dari format dot-notation ke format kebab-case baku.

1. Buka berkas [apps/api/routes/web.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/routes/web.php).
2. Cari bagian rute `roles`:
   ```php
   // SEBELUM:
   Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('admin.roles.index');
   Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('admin.roles.show');
   Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage|roles.create')->name('admin.roles.store');
   Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage|roles.edit')->name('admin.roles.update');
   Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage|roles.delete')->name('admin.roles.destroy');
   ```
3. Ubah menjadi:
   ```php
   // SESUDAH:
   Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles-view')->name('admin.roles.index');
   Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles-view')->name('admin.roles.show');
   Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles-manage|roles-create')->name('admin.roles.store');
   Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles-manage|roles-update')->name('admin.roles.update');
   Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles-manage|roles-delete')->name('admin.roles.destroy');
   ```

---

### Langkah 3: Perbaiki `AdminNavigationService.php`
**Tujuan:** Memastikan item sidebar muncul sesuai permission pengguna yang sedang login.

1. Buka berkas [apps/api/app/Services/AdminNavigationService.php](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Services/AdminNavigationService.php).
2. Perbarui node Dashboard (baris ~20):
   ```php
   [
       'label' => 'Dashboard',
       'icon' => 'i-lucide-inbox',
       'to' => '/admin',
       'exact' => true,
       'permission' => ['dashboard-view'],
   ],
   ```
3. Perbarui node Akses Group (baris ~38):
   ```php
   [
       'label' => 'Akses Group',
       'icon' => 'i-lucide-shield',
       'to' => '/admin/roles',
       'permission' => ['roles-view', 'roles-manage'],
   ],
   ```
4. Perbarui node induk User Management (baris ~26):
   ```php
   'permission' => ['permissions-view', 'roles-view', 'users-view'],
   ```

---

### Langkah 4: Sesuaikan Halaman Nuxt Frontend

#### 4.1 Halaman Dashboard Admin (`apps/web/app/pages/admin/index.vue`)
1. Buka [apps/web/app/pages/admin/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/index.vue).
2. Ubah `definePageMeta`:
   ```ts
   definePageMeta({
     middleware: ["auth", "permission"],
     permission: "dashboard-view",
   });
   ```
3. Cari referensi `roles.view` di array kartu navigasi cepat dan ubah menjadi `roles-view`.

#### 4.2 Halaman Daftar Roles (`apps/web/app/pages/admin/roles/index.vue`)
1. Buka [apps/web/app/pages/admin/roles/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/roles/index.vue).
2. Ubah `definePageMeta` dan tombol aksi:
   ```ts
   definePageMeta({
     middleware: ["auth", "permission"],
     permission: "roles-view",
   });

   const canCreate = computed(() => can('roles-create') || can('roles-manage'));
   const canEdit = computed(() => can('roles-update') || can('roles-manage'));
   const canDelete = computed(() => can('roles-delete') || can('roles-manage'));
   ```
3. Perbaiki fungsi `getPermissionGroupKey` dan `getPermissionAction`:
   ```ts
   // Mapping kata kunci awalan modul ke Group Key
   function getPermissionGroupKey(name: string): string {
     // Modul majemuk dengan tanda hubung
     const prefixes = [
       'infrastruktur-segmen',
       'infrastruktur-tipe',
       'ref-sumber-dana',
       'plotting-anggaran',
       'monitoring-realisasi',
       'batas-kecamatan',
       'batas-desa',
       'jalan-poros-desa',
       'audit-logs',
       'users',
       'roles',
       'permissions',
       'settings',
       'layers',
       'dashboard',
     ];

     for (const prefix of prefixes) {
       if (name.startsWith(prefix)) {
         return prefix;
       }
     }

     return 'general';
   }

   function getPermissionAction(name: string): string {
     const groupKey = getPermissionGroupKey(name);
     if (name.startsWith(groupKey + '-')) {
       return name.substring(groupKey.length + 1);
     }
     return name;
   }
   ```
4. Pastikan `groupMetaMap` mencakup semua modul dengan label bahasa Indonesia yang ramah:
   ```ts
   const groupMetaMap: Record<string, { label: string; icon: string }> = {
     dashboard: { label: "Dashboard", icon: "i-heroicons-home" },
     users: { label: "Manajemen Pengguna", icon: "i-heroicons-users" },
     roles: { label: "Akses Grup (Roles)", icon: "i-heroicons-shield-check" },
     permissions: { label: "Hak Akses (Permissions)", icon: "i-heroicons-key" },
     settings: { label: "Pengaturan Sistem", icon: "i-heroicons-cog-6-tooth" },
     "audit-logs": { label: "Log Audit Sistem", icon: "i-heroicons-clipboard-document-list" },
     layers: { label: "Katalog Layer Spasial", icon: "i-heroicons-square-3-stack-3d" },
     "batas-kecamatan": { label: "Batas Wilayah Kecamatan", icon: "i-heroicons-map" },
     "batas-desa": { label: "Batas Wilayah Desa", icon: "i-heroicons-map" },
     "jalan-poros-desa": { label: "Jalan Poros Desa", icon: "i-heroicons-arrows-pointing-out" },
     "infrastruktur-tipe": { label: "Tipe Infrastruktur", icon: "i-heroicons-tag" },
     "ref-sumber-dana": { label: "Sumber Dana", icon: "i-heroicons-banknotes" },
     "plotting-anggaran": { label: "Plotting Anggaran", icon: "i-heroicons-currency-dollar" },
     "infrastruktur-segmen": { label: "Segmen & Verifikasi Spasial", icon: "i-heroicons-cube" },
     "monitoring-realisasi": { label: "Monitoring & Realisasi", icon: "i-heroicons-document-check" },
   };
   ```

#### 4.3 Halaman Edit Detail Role (`apps/web/app/pages/admin/roles/[id].vue`)
1. Buka [apps/web/app/pages/admin/roles/[id].vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/roles/[id].vue).
2. Ubah `definePageMeta` dan `canEdit`:
   ```ts
   definePageMeta({
     middleware: ["auth", "permission"],
     permission: "roles-view",
   });

   const canEdit = computed(() => can("roles-update") || can("roles-manage"));
   ```
3. Sesuaikan fungsi `getPermissionGroupKey`, `getPermissionAction`, dan `groupMetaMap` sama seperti pada `index.vue`.

---

### Langkah 5: Peningkatan Fitur UI Matriks Hak Akses
**Tujuan:** Memberikan kemudahan bagi pengguna untuk mengelola izin per grup secara efisien dan intuitif.

1. **Tombol "Pilih Semua" (Select All) Per Modul:**
   - Pada setiap card modul izin di modal/halaman edit role, sediakan tombol toggle kecil:
     - Jika semua permission di modul tersebut sudah dicentang -> tombol bertuliskan *"Hapus Semua"*.
     - Jika belum semua dicentang -> tombol bertuliskan *"Pilih Semua"*.
   - Contoh implementasi fungsi bantuan:
     ```ts
     function isGroupAllSelected(group: PermissionGroup): boolean {
       return group.permissions.every(p => formState.selectedPermissions.includes(p.name));
     }

     function toggleGroupSelection(group: PermissionGroup): void {
       const allSelected = isGroupAllSelected(group);
       const groupNames = group.permissions.map(p => p.name);

       if (allSelected) {
         // Hapus semua permission modul ini dari formState
         formState.selectedPermissions = formState.selectedPermissions.filter(
           name => !groupNames.includes(name)
         );
       } else {
         // Tambahkan semua permission modul ini yang belum ada
         const newSelection = new Set([...formState.selectedPermissions, ...groupNames]);
         formState.selectedPermissions = Array.from(newSelection);
       }
     }
     ```
2. **Indikator Counter Badge:**
   - Tampilkan badge di judul modul: `(3 / 4 dipilih)` sehingga admin tahu modul mana saja yang aktif sebagian.
3. **Proteksi UI Role Sistem (`admin`):**
   - Jika role yang sedang diedit adalah `name === 'admin'`:
     - Tampilkan badge informasi: *"Role Bawaan Sistem (Super Administrator) memiliki akses penuh otomatis"*.
     - Nonaktifkan input nama role (`disabled` / `readonly`).
     - Sembunyikan atau disable tombol *"Hapus Akses Grup"*.

---

## 6. Kriteria Penerimaan (Acceptance Criteria - AC)

Pelaksana pekerjaan dianggap **SELESAI** jika semua kriteria berikut terpenuhi:

- [ ] **AC-1 (Database Consistency):** Seluruh permissions terdaftar di tabel `permissions` dalam format kebab-case (`roles-view`, `dashboard-view`, dll.), tanpa adanya format dot-notation (`roles.view`).
- [ ] **AC-2 (Role Assignment Seeding):** Menjalankan `php artisan db:seed --class=DatabaseSeeder` berhasil 100% tanpa error, dan role `admin`, `verifierBappeda`, `verifierKecamatan`, dan `user` memiliki izin yang sesuai dengan tabel matriks bab 3.
- [ ] **AC-3 (Backend Route Guard):** Endpoint `GET /api/v1/admin/roles` terlindungi oleh middleware `permission:roles-view`. Pengguna yang memiliki permission `roles-view` berhasil menerima response 200 OK.
- [ ] **AC-4 (Sidebar Dynamic Navigation):** Menu *"Akses Group"* muncul di sidebar admin hanya untuk pengguna yang memiliki hak `roles-view` atau `roles-manage` (atau role `admin`).
- [ ] **AC-5 (Frontend Route Protection):** Mengakses URL `/admin/roles` langsung dari browser oleh user tanpa permission `roles-view` berhasil ditolak dengan notifikasi toast *"Akses ditolak"* dan di-redirect ke `/admin`.
- [ ] **AC-6 (Interactive Matrix UI):** Modal/Form pembuatan dan pengubahan Akses Group menampilkan izin terkelompok dengan rapi, tombol *"Pilih Semua"* per modul berfungsi normal, dan badge counter menunjukkan jumlah izin yang dipilih secara reaktif.
- [ ] **AC-7 (System Safety Guard):** Mencoba menghapus atau me-rename role `admin` menghasilkan status HTTP 422 dengan pesan penolakan yang ramah.
- [ ] **AC-8 (Zero Regression):** Halaman verifikasi infrastruktur (`/admin/infrastruktur-segmen`) dan plotting anggaran (`/admin/plotting-anggaran`) tetap dapat diakses dengan lancar oleh role `verifierBappeda` dan `verifierKecamatan`.

---

## 7. Prosedur Pengujian & QA (Verification Protocol)

Lakukan pengujian manual menggunakan kredensial akun berikut setelah melakukan implementasi:

### Skenario 1: Verifikasi Super Admin
1. Login dengan email `admin@admin.com` / password `password`.
2. Buka menu **User Management -> Akses Group** (`/admin/roles`).
3. Pastikan daftar role (`admin`, `verifierBappeda`, `verifierKecamatan`, `user`) tampil.
4. Klik tombol **Buat Akses Grup**:
   - Beri nama `test-operator`.
   - Gunakan fitur *"Pilih Semua"* pada modul *Segmen & Verifikasi Spasial*.
   - Simpan. Pastikan role baru tersimpan dan muncul di tabel.
5. Coba hapus role `admin` -> Pastikan muncul pesan error bahwa role sistem tidak dapat dihapus.
6. Hapus role `test-operator` -> Pastikan berhasil dihapus.

### Skenario 2: Verifikasi Verifikator Kecamatan (Non-Admin)
1. Buat user baru dengan role `verifierKecamatan`.
2. Login menggunakan user tersebut.
3. Periksa menu sidebar:
   - Menu **Akses Group** dan **Hak Akses** **TIDAK BOLEH MUNCUL**.
   - Menu **Monitoring Infrastruktur** dan **Plotting Anggaran** **HARUS MUNCUL**.
4. Coba ketikkan URL `/admin/roles` di address bar browser:
   - Harus otomatis mental (redirect) ke `/admin` dengan toast *"Akses ditolak"*.

### Skenario 3: Verifikasi Cache Spatie
Jalankan perintah berikut di console backend untuk memastikan tidak ada cache izin yang usang:
```powershell
cd c:\laragon\www\melarosa-laravel-nuxt\apps\api
php artisan permission:cache-reset
php artisan route:clear
```

---

## 8. Catatan Penting & Pantangan (Do's and Don'ts)

### ✅ WAJIB DILAKUKAN (Do's)
- Selalu gunakan `php artisan permission:cache-reset` setiap kali mengubah data di tabel `permissions` atau `roles`.
- Gunakan nama bahasa Indonesia yang ramah dan baku pada UI label permission.
- Pastikan method `isAdmin()` di `usePermission.ts` selalu menjadi prioritas tertinggi (short-circuit return `true`).
- Catat aktivitas pembuatan, perubahan, dan penghapusan role ke dalam `AuditLogService`.

### ❌ DILARANG KERAS (Don'ts)
- **JANGAN** membuat permission dengan nama campuran (contoh: `roles.create` dan `roles-create` sekaligus di database).
- **JANGAN** menghapus pemeriksaan `if ($role->name === 'admin')` di controller backend.
- **JANGAN** mengubah logika hashing password atau skema tabel relasi Spatie di migrasi bawaan.
- **JANGAN** mematikan middleware `auth` atau `permission` demi membuat rute bisa diakses sementara waktu.

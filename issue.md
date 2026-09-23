# Issue: Implementasi Policy, Scope & Kontrol Aksi Berbasis Permissions pada Jalan Poros Desa

Dokumen perencanaan teknis untuk mengimplementasikan pembatasan akses data spasial jalan poros desa berbasis wilayah (**Kecamatan** dan **Desa**) serta **Spatie Group Permissions** pada:
- **Backend Controller:** `apps/api/app/Http/Controllers/Admin/JalanPorosDesaController.php`
- **Model:** `apps/api/app/Models/JalanPorosDesa.php`
- **Frontend Route:** `/admin/dataset/jalan-poros-desa` (`apps/web/app/pages/admin/dataset/jalan-poros-desa/index.vue`)

> **Target Implementor:** Junior Programmer atau AI Coding Model.  
> Ikuti instruksi langkah demi langkah secara linear dari Bagian A (Backend) hingga Bagian B (Frontend).

---

## Daftar Berkas yang Terlibat

| Sisi | Berkas | Deskripsi Pekerjaan |
|---|---|---|
| **Backend** | [`apps/api/app/Models/JalanPorosDesa.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Models/JalanPorosDesa.php) | Tambahkan relasi `kecamatanModel`, `desaModel`, dan method `scopeForUser()` |
| **Backend** | `apps/api/app/Policies/JalanPorosDesaPolicy.php` | **[BARU]** Buat Policy otorisasi Spatie & wilayah untuk model `JalanPorosDesa` |
| **Backend** | [`apps/api/app/Providers/AppServiceProvider.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Providers/AppServiceProvider.php) | Daftarkan `JalanPorosDesaPolicy` ke `Gate` |
| **Backend** | [`apps/api/app/Http/Controllers/Admin/JalanPorosDesaController.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Http/Controllers/Admin/JalanPorosDesaController.php) | Terapkan `$this->authorize()`, `forUser($user)`, dan proteksi wilayah |
| **Frontend** | [`apps/web/app/pages/admin/dataset/jalan-poros-desa/index.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/dataset/jalan-poros-desa/index.vue) | Integrasikan `usePermission`, auto-lock filter, dan guard aksi |
| **Frontend** | [`apps/web/app/components/dataset/jalan-poros-desa/RightPanel.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/dataset/jalan-poros-desa/RightPanel.vue) | Terapkan izin akses pada tombol Edit, Split, dan Hapus di Inspector Drawer |
| **Frontend** | [`apps/web/app/components/dataset/jalan-poros-desa/BottomPanel.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/dataset/jalan-poros-desa/BottomPanel.vue) | Terapkan izin akses pada tombol aksi per baris di Tabel Atribut |

---

## Aturan Logika Wilayah & Group Permissions (Spatie)

1. **Daftar Spatie Permissions untuk Jalan Poros Desa**:
   - **View:** `jalan-poros-desa-view`, `jalan-poros-desa.view`, `jalan-view`, `jalan-poros-desa-manage`, `jalan-poros-desa.manage`
   - **Create:** `jalan-poros-desa-create`, `jalan-poros-desa.create`, `jalan-poros-desa-manage`, `jalan-poros-desa.manage`
   - **Update / Edit / Split:** `jalan-poros-desa-update`, `jalan-poros-desa.edit`, `jalan-poros-desa-manage`, `jalan-poros-desa.manage`
   - **Delete:** `jalan-poros-desa-delete`, `jalan-poros-desa.delete`, `jalan-poros-desa-manage`, `jalan-poros-desa.manage`
2. **Super Admin (`hasRole('admin')`)**:
   - Memiliki hak akses tak terbatas ke seluruh data jalan poros desa se-kabupaten.
   - Melewati semua pemeriksaan Policy berkat interceptor `Gate::before` di `AppServiceProvider`.
3. **Operator Kecamatan (`$user->id_kecamatan` terisi, `$user->id_desa` kosong)**:
   - **Akses Data (Query/Scope):** Hanya menampilkan data jalan poros desa di dalam kecamatannya (`id_kecamatan === $user->id_kecamatan`).
   - **Aksi Create:** Hanya boleh membuat ruas jalan di dalam kecamatannya.
   - **Aksi Update/Split:** Hanya boleh mengedit/memotong ruas jalan di dalam kecamatannya.
   - **Aksi Delete:** Hanya boleh menghapus ruas jalan di dalam kecamatannya (jika memiliki permission delete).
4. **Operator Desa (`$user->id_desa` terisi)**:
   - **Akses Data (Query/Scope):** Hanya menampilkan data jalan poros desa di dalam desanya (`id_desa === $user->id_desa`).
   - **Aksi Create:** Hanya boleh membuat ruas jalan di desanya (kolom `id_desa` dan `id_kecamatan` terkunci otomatis).
   - **Aksi Update/Split:** Hanya boleh mengedit/memotong ruas jalan di desanya.
   - **Aksi Delete:** Dilarang menghapus ruas jalan (atau hanya di desanya jika memiliki permission delete).

---

## BAGIAN A: BACKEND (`apps/api/`)

### Tahap 1: Tambahkan Relasi & Scope pada Model [`JalanPorosDesa.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Models/JalanPorosDesa.php)

Buka `apps/api/app/Models/JalanPorosDesa.php` dan tambahkan relasi serta method `scopeForUser()`:

```php
use App\Models\User;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;

/**
 * Relasi ke data batas wilayah kecamatan
 */
public function kecamatanModel()
{
    return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan');
}

/**
 * Relasi ke data batas wilayah desa
 */
public function desaModel()
{
    return $this->belongsTo(BatasWilayahDesa::class, 'id_desa');
}

/**
 * Scope query agar otomatis terfilter berdasarkan wilayah user yang login.
 */
public function scopeForUser($query, ?User $user = null)
{
    if (!$user || $user->hasRole('admin')) {
        return $query;
    }

    // Jika user dibatasi tingkat desa
    if ($user->id_desa) {
        return $query->where('jalan_porosdesa.id_desa', $user->id_desa);
    }

    // Jika user dibatasi tingkat kecamatan
    if ($user->id_kecamatan) {
        return $query->where('jalan_porosdesa.id_kecamatan', $user->id_kecamatan);
    }

    return $query;
}
```

---

### Tahap 2: Buat File Policy Baru `JalanPorosDesaPolicy.php`

Buat file baru di `apps/api/app/Policies/JalanPorosDesaPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\JalanPorosDesa;
use App\Models\User;

class JalanPorosDesaPolicy
{
    /**
     * Helper pemeriksaan Spatie Permissions fleksibel (dot atau dash).
     */
    private function hasPerm(User $user, array $perms): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        foreach ($perms as $perm) {
            if ($user->hasPermissionTo($perm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Otorisasi melihat daftar jalan poros desa.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, [
            'jalan-poros-desa-view',
            'jalan-poros-desa.view',
            'jalan-view',
            'jalan-poros-desa-manage',
            'jalan-poros-desa.manage',
        ]);
    }

    /**
     * Otorisasi melihat detail satu ruas jalan poros desa.
     */
    public function view(User $user, JalanPorosDesa $ruas): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $ruas->id_desa === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $ruas->id_kecamatan === (int) $user->id_kecamatan;
        }

        return true;
    }

    /**
     * Otorisasi menambah ruas jalan poros desa baru.
     */
    public function create(User $user): bool
    {
        return $this->hasPerm($user, [
            'jalan-poros-desa-create',
            'jalan-poros-desa.create',
            'jalan-poros-desa-manage',
            'jalan-poros-desa.manage',
        ]);
    }

    /**
     * Otorisasi memperbarui data atau geometri ruas jalan poros desa.
     */
    public function update(User $user, JalanPorosDesa $ruas): bool
    {
        if (!$this->hasPerm($user, [
            'jalan-poros-desa-update',
            'jalan-poros-desa.edit',
            'jalan-poros-desa-manage',
            'jalan-poros-desa.manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_desa) {
            return (int) $ruas->id_desa === (int) $user->id_desa;
        }

        if ($user->id_kecamatan) {
            return (int) $ruas->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }

    /**
     * Otorisasi menghapus ruas jalan poros desa.
     */
    public function delete(User $user, JalanPorosDesa $ruas): bool
    {
        // User tingkat desa secara default tidak diizinkan menghapus data infrastruktur jalan
        if ($user->id_desa) {
            return false;
        }

        if (!$this->hasPerm($user, [
            'jalan-poros-desa-delete',
            'jalan-poros-desa.delete',
            'jalan-poros-desa-manage',
            'jalan-poros-desa.manage',
        ])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id_kecamatan) {
            return (int) $ruas->id_kecamatan === (int) $user->id_kecamatan;
        }

        return false;
    }
}
```

---

### Tahap 3: Daftarkan Policy di [`AppServiceProvider.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Providers/AppServiceProvider.php)

Buka `apps/api/app/Providers/AppServiceProvider.php`:
1. Tambahkan import di atas:
   ```php
   use App\Models\JalanPorosDesa;
   use App\Policies\JalanPorosDesaPolicy;
   ```
2. Di dalam method `boot()` tambahkan:
   ```php
   Gate::policy(JalanPorosDesa::class, JalanPorosDesaPolicy::class);
   ```

---

### Tahap 4: Update [`JalanPorosDesaController.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Http/Controllers/Admin/JalanPorosDesaController.php)

Lakukan penyesuaian pada method-method berikut:

1. **Method `index(Request $request)`**:
   - Di awal method, tambahkan:
     ```php
     $this->authorize('viewAny', JalanPorosDesa::class);
     $user = $request->user();
     ```
   - Terapkan scope pada query:
     ```php
     $query = JalanPorosDesa::query()->forUser($user);
     ```
   - Pada `format === 'summary'`:
     Sesuaikan `$summaryQuery` dan `$kondisiQuery` dengan batasan wilayah user login:
     ```php
     if ($user && !$user->hasRole('admin')) {
         if ($user->id_desa) {
             $summaryQuery->where('id_desa', $user->id_desa);
             $kondisiQuery->where('id_desa', $user->id_desa);
         } elseif ($user->id_kecamatan) {
             $summaryQuery->where('id_kecamatan', $user->id_kecamatan);
             $kondisiQuery->where('id_kecamatan', $user->id_kecamatan);
         }
     }
     ```
   - Pada `format === 'options'`:
     Jika user dibatasi per kecamatan atau desa, batasi dropdown kecamatan & desa agar hanya menampilkan wilayah yang relevan.

2. **Method `show(string $id)`**:
   - Ambil data:
     ```php
     $ruas = JalanPorosDesa::withGeoJson()->where('id', $id)->first();
     if (!$ruas) {
         return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
     }
     $this->authorize('view', $ruas);
     ```

3. **Method `store(Request $request)`**:
   - Tambahkan di awal:
     ```php
     $this->authorize('create', JalanPorosDesa::class);
     $user = $request->user();
     ```
   - Enforce wilayah jika user dibatasi:
     ```php
     if ($user && !$user->hasRole('admin')) {
         if ($user->id_desa) {
             $validated['id_desa'] = $user->id_desa;
             $validated['id_kecamatan'] = $user->id_kecamatan;
         } elseif ($user->id_kecamatan) {
             $validated['id_kecamatan'] = $user->id_kecamatan;
         }
     }
     ```

4. **Method `update(Request $request, string $id)`**:
   - Ambil model lalu otorisasi:
     ```php
     $ruas = JalanPorosDesa::where('id', $id)->firstOrFail();
     $this->authorize('update', $ruas);
     ```
   - Cegah pemindahan ruas jalan ke luar wilayah user yang bersangkutan.

5. **Method `destroy(string $id)`**:
   - Ambil model lalu otorisasi:
     ```php
     $ruas = JalanPorosDesa::where('id', $id)->firstOrFail();
     $this->authorize('delete', $ruas);
     ```

6. **Method `split(Request $request, string $id)`**:
   - Pastikan method `split` juga mengotorisasi update:
     ```php
     $ruas = JalanPorosDesa::where('id', $id)->firstOrFail();
     $this->authorize('update', $ruas);
     ```

---

## BAGIAN B: FRONTEND (`apps/web/`)

### Tahap 5: Integrasi Permissions & State Wilayah di Halaman Utama

File: [`apps/web/app/pages/admin/dataset/jalan-poros-desa/index.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/dataset/jalan-poros-desa/index.vue)

1. **Inisialisasi Store & Permissions**:
   ```typescript
   import { usePermission } from "~/composables/usePermission";
   import { useAuthStore } from "~/stores/auth";

   const auth = useAuthStore();
   const { can, isAdmin } = usePermission();

   // Group Permissions
   const canView = computed(() =>
     can("jalan-poros-desa-view") ||
     can("jalan-poros-desa.view") ||
     can("jalan-view") ||
     can("jalan-poros-desa-manage") ||
     can("jalan-poros-desa.manage")
   );
   const canCreate = computed(() =>
     can("jalan-poros-desa-create") ||
     can("jalan-poros-desa.create") ||
     can("jalan-poros-desa-manage") ||
     can("jalan-poros-desa.manage")
   );
   const canEdit = computed(() =>
     can("jalan-poros-desa-update") ||
     can("jalan-poros-desa.edit") ||
     can("jalan-poros-desa-manage") ||
     can("jalan-poros-desa.manage")
   );
   const canDelete = computed(() =>
     can("jalan-poros-desa-delete") ||
     can("jalan-poros-desa.delete") ||
     can("jalan-poros-desa-manage") ||
     can("jalan-poros-desa.manage")
   );

   // User Territory Context
   const userKecamatanId = computed(() => auth.user?.id_kecamatan ?? null);
   const userDesaId = computed(() => auth.user?.id_desa ?? null);
   const isKecamatanRestricted = computed(() => !isAdmin() && !!userKecamatanId.value);
   const isDesaRestricted = computed(() => !isAdmin() && !!userDesaId.value);
   const isWilayahRestricted = computed(() => !isAdmin() && (isKecamatanRestricted.value || isDesaRestricted.value));
   ```

2. **Fungsi Pemeriksa Otorisasi Aksi Fitur (Feature Guard)**:
   ```typescript
   function canEditFeature(feature: any): boolean {
     if (!canEdit.value || !feature) return false;
     if (isAdmin()) return true;

     const props = feature.properties || feature;
     const fDesaId = Number(props.id_desa);
     const fKecId = Number(props.id_kecamatan);

     if (isDesaRestricted.value) {
       return fDesaId === Number(userDesaId.value);
     }
     if (isKecamatanRestricted.value) {
       return fKecId === Number(userKecamatanId.value);
     }
     return true;
   }

   function canDeleteFeature(feature: any): boolean {
     if (!canDelete.value || !feature) return false;
     if (isAdmin()) return true;

     // Operator desa dilarang menghapus
     if (isDesaRestricted.value) return false;

     const props = feature.properties || feature;
     const fKecId = Number(props.id_kecamatan);

     if (isKecamatanRestricted.value) {
       return fKecId === Number(userKecamatanId.value);
     }
     return true;
   }

   function canSplitFeature(feature: any): boolean {
     return canEditFeature(feature);
   }
   ```

3. **Auto-Lock Filter Wilayah**:
   ```typescript
   // Otomatis kunci filter ke wilayah kerja user
   watch(
     userKecamatanId,
     (newKecId) => {
       if (newKecId && kecamatanOptions.value.length > 0) {
         const match = kecamatanOptions.value.find((k) => k.id === newKecId);
         if (match) tableKecamatan.value = match.nama;
       }
     },
     { immediate: true }
   );

   watch(
     userDesaId,
     (newDesaId) => {
       if (newDesaId && desaOptions.value.length > 0) {
         const match = desaOptions.value.find((d) => d.id === newDesaId);
         if (match) tableDesa.value = match.nama;
       }
     },
     { immediate: true }
   );
   ```

4. **Sembunyikan Tombol "Tambah Data" jika tidak memiliki permission `canCreate`**:
   - Pada baris template header desktop & mobile, pasang `v-if="canCreate"`.

---

### Tahap 6: Update [`RightPanel.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/dataset/jalan-poros-desa/RightPanel.vue) (Inspector Drawer)

1. Tambahkan props hak akses ke `RightPanel.vue`:
   ```typescript
   defineProps<{
     // ...props lama
     canEdit?: boolean;
     canSplit?: boolean;
     canDelete?: boolean;
   }>();
   ```
2. Di dalam file induk `index.vue`:
   Kirimkan props:
   ```html
   <RightPanel
     :selected-feature="selectedFeature"
     :can-edit="canEditFeature(selectedFeature)"
     :can-split="canSplitFeature(selectedFeature)"
     :can-delete="canDeleteFeature(selectedFeature)"
     ...
   />
   ```
3. Di dalam template `RightPanel.vue`:
   - Pasang `v-if="canEdit"` pada tombol `Edit`.
   - Pasang `v-if="canSplit"` pada tombol `Split / Pecah Ruas`.
   - Pasang `v-if="canDelete"` pada tombol `Hapus ruas`.
   - Jika `!canEdit && !canDelete` (misal user desa memilih ruas jalan di desa lain), tampilkan pesan keterangan:
     ```html
     <div v-if="!canEdit && !canDelete" class="text-[11px] text-gray-500 italic p-2 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
       Mode baca: Ruas jalan ini di luar wilayah wewenang Anda.
     </div>
     ```

---

### Tahap 7: Update [`BottomPanel.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/dataset/jalan-poros-desa/BottomPanel.vue) (Tabel Atribut)

1. Tambahkan fungsi validator baris atau props ke `BottomPanel.vue`:
   ```typescript
   defineProps<{
     // ...props lama
     canEditRow?: (row: any) => boolean;
     canSplitRow?: (row: any) => boolean;
     canDeleteRow?: (row: any) => boolean;
   }>();
   ```
2. Di dalam file induk `index.vue`:
   ```html
   <BottomPanel
     :can-edit-row="canEditFeature"
     :can-split-row="canSplitFeature"
     :can-delete-row="canDeleteFeature"
     ...
   />
   ```
3. Di dalam template `BottomPanel.vue` pada `#actions-cell="{ row }"`:
   - Tombol **Edit Ruas**: pasang `v-if="canEditRow ? canEditRow(row.original) : true"`.
   - Tombol **Split / Pecah Ruas**: pasang `v-if="canSplitRow ? canSplitRow(row.original) : true"`.
   - Tombol **Hapus Ruas**: pasang `v-if="canDeleteRow ? canDeleteRow(row.original) : true"`.
   - Jika tidak memiliki izin edit/hapus pada baris tersebut, tampilkan badge kecil `Hanya baca`.

---

### Tahap 8: Proteksi Modal Form & Aksi Mutasi di `index.vue`

1. **Pada `openCreate()`**:
   ```typescript
   function openCreate() {
     if (!canCreate.value) {
       toast.add({ title: "Akses Ditolak", description: "Anda tidak memiliki wewenang menambah data jalan.", color: "error" });
       return;
     }
     // ...
   }
   ```
2. **Pada `openEdit(feature)`**:
   ```typescript
   function openEdit(feature: any) {
     if (!canEditFeature(feature)) {
       toast.add({ title: "Akses Ditolak", description: "Anda hanya boleh mengedit ruas jalan di wilayah Anda.", color: "error" });
       return;
     }
     // ...
   }
   ```
3. **Pada `handleStartSplit(feature)`**:
   ```typescript
   function handleStartSplit(feature: any) {
     if (!canSplitFeature(feature)) {
       toast.add({ title: "Akses Ditolak", description: "Anda tidak diizinkan memotong ruas jalan ini.", color: "error" });
       return;
     }
     // ...
   }
   ```
4. **Pada `confirmDelete(feature)`**:
   ```typescript
   function confirmDelete(feature: any) {
     if (!canDeleteFeature(feature)) {
       toast.add({ title: "Akses Ditolak", description: "Anda tidak memiliki hak menghapus ruas jalan ini.", color: "error" });
       return;
     }
     // ...
   }
   ```
5. **Pada Modal Form (`formState`)**:
   - Jika `isKecamatanRestricted.value`, kunci field dropdown kecamatan (`disabled`).
   - Jika `isDesaRestricted.value`, kunci field dropdown desa dan kecamatan (`disabled`).

---

## Panduan Pengujian (Testing & Verifikasi)

1. **Uji Otorisasi Backend via CLI**:
   ```bash
   C:\laragon\bin\php\php-8.4.22-Win32-vs17-x64\php.exe artisan tinker
   ```
   - Cek `Gate::getPolicyFor(App\Models\JalanPorosDesa::class)` mengembalikan `App\Policies\JalanPorosDesaPolicy`.
   - Cek `App\Models\JalanPorosDesa::forUser($userKecamatan)->count()` hanya menghitung ruas jalan di kecamatan user tersebut.
   - Cek `App\Models\JalanPorosDesa::forUser($userDesa)->count()` hanya menghitung ruas jalan di desa user tersebut.

2. **Uji Hak Akses Frontend**:
   - **Login sebagai Superadmin:**
     - Seluruh tombol aksi (Tambah, Edit, Split, Hapus) aktif di peta maupun tabel.
   - **Login sebagai Operator Kecamatan:**
     - Tombol "Tambah Data" aktif (otomatis terkunci ke kecamatannya).
     - Ruas jalan di kecamatannya dapat diedit dan di-split.
     - Ruas jalan di luar kecamatannya tidak muncul di tabel atau hanya berstatus baca.
   - **Login sebagai Operator Desa:**
     - Hanya ruas jalan di dalam desanya yang memiliki tombol Edit dan Split.
     - Tombol Hapus tidak tersedia untuk operator desa.

---

## Checklist Implementasi

Gunakan daftar centang berikut untuk melacak progres pengerjaan:

### Backend (`apps/api/`)
- [x] Tambahkan relasi `kecamatanModel` dan `desaModel` di [`JalanPorosDesa.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Models/JalanPorosDesa.php)
- [x] Tambahkan method `scopeForUser()` di [`JalanPorosDesa.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Models/JalanPorosDesa.php)
- [x] Buat file `apps/api/app/Policies/JalanPorosDesaPolicy.php`
- [x] Daftarkan policy di [`AppServiceProvider.php`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/api/app/Providers/AppServiceProvider.php)
- [x] Pasang `$this->authorize('viewAny', JalanPorosDesa::class)` dan `forUser($user)` di `JalanPorosDesaController@index`
- [x] Pasang `$this->authorize('view', $ruas)` di `JalanPorosDesaController@show`
- [x] Pasang `$this->authorize('create', JalanPorosDesa::class)` dan auto-lock wilayah di `JalanPorosDesaController@store`
- [x] Pasang `$this->authorize('update', $ruas)` di `JalanPorosDesaController@update` dan `split`
- [x] Pasang `$this->authorize('delete', $ruas)` di `JalanPorosDesaController@destroy`
- [x] Perbarui query raw `format === 'summary'` agar dibatasi sesuai wilayah user login

### Frontend (`apps/web/`)
- [x] Tambahkan integrasi `usePermission()` dan `auth.user` di [`jalan-poros-desa/index.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/dataset/jalan-poros-desa/index.vue)
- [x] Buat helper otorisasi `canEditFeature`, `canDeleteFeature`, dan `canSplitFeature`
- [x] Pasang auto-lock filter kecamatan & desa sesuai wilayah kerja user
- [x] Pasang badge wilayah kerja di header halaman
- [x] Sembunyikan tombol "Tambah Data" (header & mobile dock) jika tidak memiliki `canCreate`
- [x] Update [`RightPanel.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/dataset/jalan-poros-desa/RightPanel.vue) agar tombol Edit, Split, dan Hapus mematuhi hak akses per ruas jalan
- [x] Update [`BottomPanel.vue`](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/components/dataset/jalan-poros-desa/BottomPanel.vue) agar tombol aksi per baris tabel disesuaikan dengan wewenang user
- [x] Pasang validasi otorisasi pada method mutasi: `openCreate`, `openEdit`, `handleStartSplit`, `confirmDelete`
- [x] Kunci dropdown kecamatan/desa pada form modal input ruas jalan bagi user yang dibatasi wilayah

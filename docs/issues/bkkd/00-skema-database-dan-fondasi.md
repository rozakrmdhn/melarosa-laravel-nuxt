# Sub-Issue BKKD-00: Skema Database, Model Eloquent, & Fondasi Upload

- **Status**: Ready for Implementation
- **Kategori**: Fondasi & Master
- **Komponen**: Database Migrations, Seeders, Eloquent Models, Enums, Upload Controller (`apps/api`)
- **Dokumen Terkait**: [module_issue_bkkd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/module_issue_bkkd.md)

---

## 1. Deskripsi & Tujuan

Membangun seluruh fondasi database dan lapisan model untuk modul BKKD:
1. Membuat tabel master referensi OPD (`ref_opd`) dan bidang kegiatan BKKD (`ref_bkkd_bidang`).
2. Membuat tabel transaksi proposal usulan (`bkkd_proposal`) lengkap dengan kolom atribut PostGIS `geom` (tipe geometri umum ber-SRID 4326), tabel berkas lampiran (`bkkd_proposal_lampiran`), tabel hasil verifikasi teknis (`bkkd_verifikasi`), serta tabel riwayat audit versi (`bkkd_proposal_riwayat` dan `bkkd_verifikasi_riwayat`).
3. Mendaftarkan permission standar kebab-case ke seeder sistem, dan menetapkan hak akses tulis hanya kepada `verifierBappeda`.
4. Menyiapkan endpoint upload dokumen administratif yang terproteksi pada disk privat lokal (`UploadController::document`).

---

## 2. Rincian Migrasi Database

Seluruh tabel menggunakan UUID sebagai primary key (`$table->uuid('id')->primary()`), timestamp dengan time-zone (`$table->timestampsTz()`), serta foreign key user merujuk ke kolom `uuid` pada tabel `users`.

### 2.1 Migrasi Master: `ref_opd` & `ref_bkkd_bidang`

1. `ref_opd`:
   ```php
   Schema::create('ref_opd', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->string('kode', 20)->unique();
       $table->string('nama');
       $table->boolean('is_active')->default(true);
       $table->timestampsTz();
   });
   ```

2. `ref_bkkd_bidang`:
   ```php
   Schema::create('ref_bkkd_bidang', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->string('nama')->unique();
       $table->uuid('id_opd_default')->nullable();
       $table->foreign('id_opd_default')->references('id')->on('ref_opd')->nullOnDelete();
       $table->boolean('is_active')->default(true);
       $table->timestampsTz();
   });
   ```

### 2.2 Migrasi Transaksi: `bkkd_proposal` & `bkkd_proposal_lampiran`

1. `bkkd_proposal`:
   ```php
   Schema::create('bkkd_proposal', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->string('nomor_registrasi')->unique();
       $table->integer('tahun_anggaran');
       $table->integer('id_kecamatan');
       $table->foreign('id_kecamatan')->references('id')->on('bataswilayah_kecamatan')->restrictOnDelete();
       $table->bigInteger('id_desa');
       $table->foreign('id_desa')->references('id')->on('bataswilayah_desa')->restrictOnDelete();
       $table->uuid('id_bidang');
       $table->foreign('id_bidang')->references('id')->on('ref_bkkd_bidang')->restrictOnDelete();
       $table->string('nomor_surat_desa');
       $table->date('tanggal_surat_desa');
       $table->date('tanggal_diterima');
       $table->string('judul_kegiatan');
       $table->text('uraian_kegiatan')->nullable();
       $table->text('lokasi_kegiatan')->nullable();
       $table->decimal('volume', 12, 2)->nullable();
       $table->string('satuan', 30)->nullable();
       $table->decimal('nilai_usulan', 15, 2);
       $table->string('status')->default('draft');
       $table->text('catatan')->nullable();
       
       // Kolom penetapan & penyesuaian pagu TAPD (Rancangan APBD)
       $table->decimal('nilai_disetujui_tapd', 15, 2)->nullable();
       $table->text('catatan_tapd')->nullable();
       $table->date('tanggal_penetapan_tapd')->nullable();
       $table->uuid('ditetapkan_oleh')->nullable();
       $table->foreign('ditetapkan_oleh')->references('uuid')->on('users')->nullOnDelete();

       // Standar atribut geometri PostGIS (SRID 4326)
       $table->geometry('geom', srid: 4326)->nullable();
       
       $table->integer('versi')->default(1);
       $table->uuid('created_by');
       $table->foreign('created_by')->references('uuid')->on('users')->restrictOnDelete();
       $table->uuid('updated_by')->nullable();
       $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
       $table->timestampsTz();
       $table->softDeletesTz();

       $table->index(['tahun_anggaran', 'id_kecamatan', 'id_desa'], 'idx_bkkd_prop_wilayah');
       $table->index('status', 'idx_bkkd_prop_status');
       $table->spatialIndex('geom');
   });
   ```

2. `bkkd_proposal_lampiran`:
   ```php
   Schema::create('bkkd_proposal_lampiran', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->uuid('id_proposal');
       $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
       $table->string('jenis_dokumen'); // surat_permohonan, rab, gambar_teknis, foto_lokasi, lainnya
       $table->string('nama_asli');
       $table->string('path');
       $table->string('mime_type');
       $table->bigInteger('ukuran_byte');
       $table->uuid('uploaded_by');
       $table->foreign('uploaded_by')->references('uuid')->on('users')->restrictOnDelete();
       $table->timestampTz('created_at')->nullable();

       $table->index('id_proposal');
   });
   ```

### 2.3 Migrasi Verifikasi: `bkkd_verifikasi`

```php
Schema::create('bkkd_verifikasi', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('id_proposal');
    $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->restrictOnDelete();
    $table->uuid('id_opd');
    $table->foreign('id_opd')->references('id')->on('ref_opd')->restrictOnDelete();
    $table->string('nomor_surat_hasil');
    $table->date('tanggal_surat_hasil');
    $table->date('tanggal_diterima');
    $table->string('hasil'); // layak, layak_bersyarat, tidak_layak, dikembalikan
    $table->decimal('nilai_rekomendasi', 15, 2)->nullable();
    $table->decimal('volume_rekomendasi', 12, 2)->nullable();
    $table->text('catatan_opd')->nullable();
    $table->string('path_dokumen');
    $table->string('nama_dokumen_asli');
    $table->boolean('is_aktif')->default(true);
    $table->integer('versi')->default(1);
    $table->uuid('input_by');
    $table->foreign('input_by')->references('uuid')->on('users')->restrictOnDelete();
    $table->uuid('updated_by')->nullable();
    $table->foreign('updated_by')->references('uuid')->on('users')->nullOnDelete();
    $table->timestampsTz();

    $table->index('id_proposal');
    $table->index(['id_proposal', 'is_aktif']);
});
```

### 2.4 Migrasi Riwayat Snapshot: `bkkd_proposal_riwayat` & `bkkd_verifikasi_riwayat`

```php
Schema::create('bkkd_proposal_riwayat', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('id_proposal');
    $table->foreign('id_proposal')->references('id')->on('bkkd_proposal')->cascadeOnDelete();
    $table->integer('versi');
    $table->string('aksi'); // created, updated, status_changed, deleted, restored, lampiran_added, lampiran_removed
    $table->jsonb('field_berubah')->nullable();
    $table->jsonb('data_sebelum')->nullable();
    $table->jsonb('data_sesudah')->nullable();
    $table->text('alasan_perubahan')->nullable();
    $table->uuid('changed_by');
    $table->foreign('changed_by')->references('uuid')->on('users')->restrictOnDelete();
    $table->timestampTz('created_at')->nullable();

    $table->index(['id_proposal', 'versi']);
});

Schema::create('bkkd_verifikasi_riwayat', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('id_verifikasi');
    $table->foreign('id_verifikasi')->references('id')->on('bkkd_verifikasi')->cascadeOnDelete();
    $table->integer('versi');
    $table->string('aksi'); // created, updated, superseded
    $table->jsonb('field_berubah')->nullable();
    $table->jsonb('data_sebelum')->nullable();
    $table->jsonb('data_sesudah')->nullable();
    $table->text('alasan_perubahan')->nullable();
    $table->uuid('changed_by');
    $table->foreign('changed_by')->references('uuid')->on('users')->restrictOnDelete();
    $table->timestampTz('created_at')->nullable();

    $table->index(['id_verifikasi', 'versi']);
});
```

---

## 3. Enum & Model Eloquent

### 3.1 PHP Enums (`apps/api/app/Enums/`)

1. `StatusBkkdProposal.php`:
   - `Draft = 'draft'` ("Draft")
   - `DiajukanOpd = 'diajukan_opd'` ("Menunggu Hasil OPD")
   - `Dikembalikan = 'dikembalikan'` ("Dikembalikan untuk Perbaikan")
   - `Layak = 'layak'` ("Layak")
   - `LayakBersyarat = 'layak_bersyarat'` ("Layak dengan Catatan")
   - `TidakLayak = 'tidak_layak'` ("Tidak Layak")
   - `DitetapkanTapd = 'ditetapkan_tapd'` ("Masuk Rancangan APBD / Pilihan TAPD")
   - `TidakTerpilih = 'tidak_terpilih'` ("Tidak Terpilih / Cadangan")
   - `Dibatalkan = 'dibatalkan'` ("Dibatalkan")

2. `HasilVerifikasiBkkd.php`:
   - `Layak = 'layak'`
   - `LayakBersyarat = 'layak_bersyarat'`
   - `TidakLayak = 'tidak_layak'`
   - `Dikembalikan = 'dikembalikan'`

### 3.2 Model Eloquent (`apps/api/app/Models/`)

1. `BkkdProposal.php`: Menggunakan trait `HasUuids`, `SoftDeletes`, dan `Auditable`. Relasi ke `BatasWilayahKecamatan`, `BatasWilayahDesa`, `RefBkkdBidang`, `BkkdProposalLampiran`, `BkkdVerifikasi`, dan `BkkdProposalRiwayat`.
2. `BkkdProposalLampiran.php`: Menggunakan trait `HasUuids`. Relasi `belongsTo(BkkdProposal::class)`.
3. `BkkdVerifikasi.php`: Menggunakan trait `HasUuids` dan `Auditable`. Relasi ke `BkkdProposal`, `RefOpd`, dan `BkkdVerifikasiRiwayat`.
4. `BkkdProposalRiwayat.php` & `BkkdVerifikasiRiwayat.php`: Menggunakan trait `HasUuids`. Cast `field_berubah`, `data_sebelum`, dan `data_sesudah` sebagai `array`.

---

## 4. Penanganan Upload Dokumen Privat

Berkas dokumen proposal (PDF, Excel, gambar teknis) tidak boleh disimpan di direktori publik `storage/app/public`.

1. Tambahkan method pada `UploadController`:
   ```php
   public function document(Request $request): JsonResponse
   {
       $request->validate([
           'file' => ['required', 'file', 'mimes:pdf,xlsx,xls,jpg,jpeg,png', 'max:10240'],
           'entity' => ['required', 'string', 'in:bkkd-lampiran,bkkd-verifikasi'],
       ]);

       $extension = $request->file('file')->getClientOriginalExtension();
       $path = $request->file('file')->storeAs(
           'temporary/' . $request->entity,
           implode('.', [Str::uuid7(), $extension]),
           ['disk' => 'local']
       );

       TemporaryUpload::create(['path' => $path]);

       return response()->json([
           'ok' => true,
           'path' => $path,
           'nama_asli' => $request->file('file')->getClientOriginalName(),
           'ukuran_byte' => $request->file('file')->getSize(),
           'mime_type' => $request->file('file')->getMimeType(),
       ]);
   }
   ```
2. Saat form proposal atau verifikasi disimpan permanen, berkas dipindahkan ke folder aman `bkkd/{tahun}/{id_proposal}/` pada disk `local`, dan baris `TemporaryUpload` dihapus.
3. Unduh berkas hanya melalui endpoint khusus `.../download` yang memeriksa hak akses user melalui Policy.

---

## 5. Seeder Hak Akses (`BkkdPermissionSeeder.php`)

Daftarkan permissions baru ke Spatie:
```php
$permissions = [
    'bkkd-proposal-view',
    'bkkd-proposal-create',
    'bkkd-proposal-update',
    'bkkd-proposal-delete',
    'bkkd-verifikasi-view',
    'bkkd-verifikasi-manage',
    'bkkd-riwayat-view',
];

foreach ($permissions as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
}

// Berikan seluruh hak pencatatan & baca kepada verifierBappeda
$verifierBappeda = Role::where('name', 'verifierBappeda')->first();
$verifierBappeda?->givePermissionTo($permissions);

// Berikan hanya hak baca kepada verifierKecamatan
$verifierKecamatan = Role::where('name', 'verifierKecamatan')->first();
$verifierKecamatan?->givePermissionTo([
    'bkkd-proposal-view',
    'bkkd-verifikasi-view',
]);
```

---

## 6. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Seluruh migrasi tabel BKKD berhasil dieksekusi tanpa tabrakan foreign key atau syntax error.
- [ ] Kolom `geom` terdaftar dengan tipe PostGIS SRID 4326 dan memiliki indeks spasial GiST.
- [ ] Seeder `BkkdPermissionSeeder` berhasil menambahkan seluruh permission baku dan menetapkannya ke role `verifierBappeda` serta `verifierKecamatan`.
- [ ] Endpoint `POST /api/v1/admin/upload/document` mampu menerima unggahan file PDF, Excel, dan gambar maksimal 10MB ke direktori privat lokal.
- [ ] Berkas temporer tercatat pada tabel `temporary_uploads`.

# Sub-Issue BKKD-01: Fitur Daftar & Pengelolaan Proposal Usulan BKKD

- **Status**: Ready for Implementation
- **Kategori**: Transaksi Proposal (Fitur 1)
- **Komponen**: REST API Backend & Antarmuka Nuxt UI (`apps/api` & `apps/web`)
- **Dependensi**: BKKD-00 (Skema Database & Fondasi)
- **Dokumen Terkait**: [module_issue_bkkd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/module_issue_bkkd.md)

---

## 1. Deskripsi & Tujuan

Membangun antarmuka dan backend API untuk mengelola usulan proposal BKKD dari desa. Operator Bappeda menerima dokumen fisik permohonan, mencatat rincian usulan (nomor surat desa, tanggal, judul kegiatan, bidang, estimasi pagu anggaran, volume, lokasi kegiatan), mengunggah lampiran dokumen hasil pindai (RAB, surat desa, foto kondisi), serta mengelola siklus status proposal (draft, diajukan ke OPD teknis, atau dibatalkan).

Halaman daftar proposal menyediakan visualisasi ringkasan anggaran, filter multi-dimensi (tahun anggaran, kecamatan, desa, bidang, status verifikasi), pencarian cepat, serta detail proposal terstruktur dalam format tab.

---

## 2. Alur Kerja Bisnis (Workflow)

1. **Penerimaan Berkas**: Operator Bappeda menerima berkas proposal fisik dari desa.
2. **Pencatatan Usulan**: Operator membuka `/admin/bkkd/proposal` dan menekan tombol "Catat Proposal Baru".
3. **Penomoran Otomatis**: Sistem membuat nomor registrasi unik dengan format `BKKD/{tahun}/{kode_desa}/{nomor_urut_3_digit}` (contoh: `BKKD/2026/3201010001/001`).
4. **Unggah Lampiran**: Operator mengunggah hasil pindai berkas fisik proposal (surat permohonan, RAB, gambar rencana).
5. **Pemeriksaan & Pengajuan**: Proposal tersimpan dengan status awal `draft`. Setelah berkas diperiksa lengkap, operator menekan tombol "Ajukan ke OPD Teknis" yang mengubah status menjadi `diajukan_opd`.

---

## 3. Spesifikasi Backend (`apps/api`)

### 3.1 Endpoint REST API (`routes/web.php`)

Semua endpoint berada di bawah prefix `api/v1/admin/`:

| Method | URI | Middleware Permission | Fungsi |
| :--- | :--- | :--- | :--- |
| `GET` | `bkkd/proposal` | `bkkd-proposal-view` | Mengambil daftar proposal dengan filter, pencarian, dan pagination |
| `POST` | `bkkd/proposal` | `bkkd-proposal-create` | Menyimpan proposal baru dan membangkitkan nomor registrasi |
| `GET` | `bkkd/proposal/{id}` | `bkkd-proposal-view` | Mengambil detail lengkap proposal beserta relasi lampiran dan verifikasi |
| `PUT` | `bkkd/proposal/{id}` | `bkkd-proposal-update` | Memperbarui data usulan proposal |
| `DELETE` | `bkkd/proposal/{id}` | `bkkd-proposal-delete` | Menghapus proposal (soft delete) |
| `POST` | `bkkd/proposal/{id}/restore` | `bkkd-proposal-delete` | Memulihkan proposal yang terhapus |
| `POST` | `bkkd/proposal/{id}/ajukan` | `bkkd-proposal-update` | Mengubah status dari `draft` atau `dikembalikan` menjadi `diajukan_opd` |
| `POST` | `bkkd/proposal/{id}/batalkan` | `bkkd-proposal-update` | Membatalkan usulan proposal (`dibatalkan`) |
| `POST` | `bkkd/proposal/{id}/lampiran` | `bkkd-proposal-update` | Menyematkan lampiran dokumen baru ke proposal |
| `DELETE` | `bkkd/proposal/{id}/lampiran/{idLampiran}` | `bkkd-proposal-update` | Menghapus lampiran dokumen |
| `GET` | `bkkd/proposal/{id}/lampiran/{idLampiran}/download` | `bkkd-proposal-view` | Mengunduh file dokumen lampiran (streaming privat) |

### 3.2 Generator Nomor Registrasi Otomatis

Nomor registrasi digenerasi dalam transaksi database untuk mencegah nomor ganda saat terjadi request bersamaan:
```php
DB::transaction(function () use ($request, $desa) {
    $tahun = $request->input('tahun_anggaran');
    $kodeDesa = $desa->id; // atau kode desa baku

    $maxUrut = BkkdProposal::where('tahun_anggaran', $tahun)
        ->where('id_desa', $desa->id)
        ->lockForUpdate()
        ->count();

    $nomorUrut = str_pad($maxUrut + 1, 3, '0', STR_PAD_LEFT);
    $nomorRegistrasi = sprintf('BKKD/%s/%s/%s', $tahun, $kodeDesa, $nomorUrut);
    
    // Simpan proposal...
});
```

### 3.3 Pembatasan Wilayah (Policy Scope)

Pada `BkkdProposalPolicy`:
- Method `viewAny` dan `view`: Jika `user->id_kecamatan` tidak null (seperti role `verifierKecamatan`), query wajib dibatasi hanya pada kecamatan tersebut.
- Method `create`, `update`, `delete`: Hanya diizinkan jika user memiliki role `verifierBappeda` (atau `admin`). Pengguna non-Bappeda ditolak dengan HTTP 403 Forbidden.

---

## 4. Spesifikasi Frontend Nuxt 4 (`apps/web`)

### 4.1 Tipe Data & Composable

1. `apps/web/app/types/bkkd.ts`:
   - `BkkdProposal`: `id`, `nomor_registrasi`, `tahun_anggaran`, `id_kecamatan`, `id_desa`, `judul_kegiatan`, `nilai_usulan`, `status`, `versi`, `lampiran`, `verifikasi_aktif`.
   - `BkkdProposalLampiran`: `id`, `jenis_dokumen`, `nama_asli`, `path`, `ukuran_byte`, `created_at`.
   - `BkkdSummary`: `total_proposal`, `total_nilai_usulan`, `jumlah_draft`, `jumlah_diajukan`, `jumlah_layak`, `jumlah_tidak_layak`.

2. `apps/web/app/composables/useBkkdApi.ts`:
   - `fetchProposals(params)`
   - `fetchProposalDetail(id)`
   - `createProposal(payload)`
   - `updateProposal(id, payload)`
   - `deleteProposal(id)`
   - `ajukanProposal(id)`
   - `batalkanProposal(id)`
   - `uploadLampiran(id, payload)`
   - `deleteLampiran(id, lampiranId)`
   - `downloadLampiran(id, lampiranId)`

### 4.2 Halaman Daftar Proposal (`pages/admin/bkkd/proposal/index.vue`)

- **Bilah Filter**:
  - Filter Tahun Anggaran (default: tahun aktif).
  - Filter Wilayah: Dropdown Kecamatan dan Desa menggunakan komponen `WilayahSelector.vue`.
  - Filter Bidang Kegiatan (`ref_bkkd_bidang`).
  - Filter Status Usulan.
  - Input Pencarian (judul kegiatan, nomor registrasi, nomor surat desa).
- **Kartu Metrik Ringkasan**:
  - Total Proposal Terdata.
  - Total Nilai Usulan (format rupiah: Rp X.XXX.XXX.XXX).
  - Proposal Menunggu Telaah OPD.
  - Proposal Disetujui / Layak.
- **Tabel Data Proposal (`UTable`)**:
  - Kolom: Nomor Registrasi, Desa & Kecamatan, Judul Kegiatan & Bidang, Nilai Usulan, Status Usulan, Tanggal Masuk, Aksi.
  - Kolom Aksi: Tombol "Lihat Detail", "Edit Data", "Ajukan ke OPD", dan "Hapus".
- **State Wajib**: Loading skeleton baris, Empty state informatif jika data nihil, dan Error state dengan tombol muat ulang.

### 4.3 Halaman Detail Proposal (`pages/admin/bkkd/proposal/[id].vue`)

Struktur halaman berbasis tab:
1. **Tab 1: Data Usulan**: Menampilkan seluruh atribut proposal secara terperinci, ringkasan nominal, serta tombol aksi cepat (Ajukan, Batalkan, Edit).
2. **Tab 2: Berkas Lampiran**: Komponen `LampiranUploader.vue` yang menampilkan daftar berkas pendukung, tombol unduh berkas, tombol hapus, dan form unggah berkas baru.
3. **Tab 3: Hasil Verifikasi**: (Dibahas pada BKKD-02).
4. **Tab 4: Riwayat Perubahan**: (Dibahas pada BKKD-03).

### 4.4 Komponen Form Modal (`ProposalFormModal.vue`)

- Input dropdown bertingkat: Kecamatan dan Desa.
- Dropdown Bidang Kegiatan (mengambil dari master `ref_bkkd_bidang`).
- Input Nomor Surat Desa & Tanggal Surat Desa.
- Input Tanggal Berkas Diterima di Bappeda.
- Input Judul Kegiatan & Uraian Kegiatan.
- Input Deskripsi Lokasi (Dusun, RT/RW).
- Input Nilai Usulan: Komponen input angka dengan pemisah ribuan otomatis (formatting currency IDR).
- Input Volume dan Satuan.
- Area upload berkas awal (surat permohonan, RAB).

---

## 5. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Operator Bappeda dapat membuka halaman `/admin/bkkd/proposal`, memfilter berdasarkan tahun dan desa, serta melihat daftar proposal dengan rapi.
- [ ] Tombol "Catat Proposal Baru" membuka modal form, memvalidasi input wajib, dan menyimpan proposal ke database dengan status awal `draft`.
- [ ] Nomor registrasi digenerasi otomatis dengan format `BKKD/{tahun}/{kode_desa}/{nomor_urut_3_digit}` dan terbukti unik.
- [ ] Berkas lampiran berhasil diunggah ke disk privat dan hanya dapat diunduh oleh pengguna yang memiliki permission `bkkd-proposal-view`.
- [ ] Aksi "Ajukan ke OPD" berhasil mengubah status proposal menjadi `diajukan_opd` dan mencatat riwayat transaksi.
- [ ] Pengguna role `verifierKecamatan` hanya dapat melihat daftar proposal dari desanya sendiri dan tidak melihat tombol tambah, ubah, atau hapus.
- [ ] Tampilan tabel dan form modal berfungsi responsif pada layar ponsel tanpa terjadi horizontal overflow.

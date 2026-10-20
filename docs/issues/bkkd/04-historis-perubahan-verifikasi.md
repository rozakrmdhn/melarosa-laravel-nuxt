# Sub-Issue BKKD-04: Fitur Historis Perubahan Verifikasi Usulan BKKD

- **Status**: Ready for Implementation
- **Kategori**: Audit & Akuntabilitas Verifikasi (Fitur 4)
- **Komponen**: Snapshot Verifikasi, Portal Riwayat Terpusat, Slideover Diff (`apps/api` & `apps/web`)
- **Dependensi**: BKKD-02 (Hasil Verifikasi) & BKKD-03 (Historis Usulan)
- **Dokumen Terkait**: [module_issue_bkkd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/module_issue_bkkd.md)

---

## 1. Deskripsi & Tujuan

Membangun mekanisme pelacakan riwayat perubahan untuk hasil verifikasi teknis proposal BKKD, serta menyediakan portal audit log riwayat terpusat pada sistem. Rekomendasi verifikasi OPD menyangkut penetapan kelayakan dan besaran alokasi anggaran daerah, sehingga setiap perubahan keputusan, koreksi nilai pagu, maupun pergantian dokumen telaah wajib tercatat dengan presisi.

Fitur ini menangani dua skenario utama:
1. **Koreksi Administratif**: Operator memperbaiki kekeliruan ketik nomor surat, tanggal, atau nilai rekomendasi pada hasil verifikasi aktif.
2. **Penggantian Hasil Telaah (Superseded)**: Proposal dievaluasi ulang oleh OPD sehingga hasil telaah terdahulu digantikan oleh hasil telaah baru. Seluruh berkas lama tetap tersimpan dan dapat ditelusuri perbandingannya.

---

## 2. Mekanisme Audit Verifikasi

### 2.1 Pencatatan Aksi pada `bkkd_verifikasi_riwayat`

| Nilai Aksi | Kondisi & Pemicu |
| :--- | :--- |
| `created` | Baris hasil verifikasi baru pertama kali disimpan |
| `updated` | Dilakukan koreksi atribut pada baris verifikasi aktif |
| `superseded` | Baris verifikasi lama dinonaktifkan (`is_aktif = false`) karena adanya hasil verifikasi susulan |

### 2.2 Retensi File Dokumen Lama

Saat operator melakukan koreksi yang disertai penggantian berkas dokumen verifikasi (unggah file PDF atau Excel baru):
- Path berkas lama dicatat ke dalam kolom `data_sebelum` pada tabel riwayat.
- File lama **tidak dihapus dari storage**, sehingga arsip historis surat dan lembar evaluasi terdahulu tetap dapat diunduh kapan saja untuk keperluan pemeriksaan atau audit inspektorat daerah.

### 2.3 Portal Riwayat Gabungan Terpusat

Selain dapat dilihat pada masing-masing halaman detail proposal, sistem menyediakan halaman khusus: `/admin/bkkd/riwayat` yang mengagregasikan seluruh riwayat perubahan usulan maupun riwayat verifikasi dari seluruh desa dalam satu tabel audit yang dapat difilter.

---

## 3. Spesifikasi Backend (`apps/api`)

### 3.1 Endpoint REST API (`routes/web.php`)

| Method | URI | Middleware Permission | Fungsi |
| :--- | :--- | :--- | :--- |
| `GET` | `bkkd/verifikasi/{id}/riwayat` | `bkkd-riwayat-view` | Mengambil riwayat versi dari satu hasil verifikasi spesifik |
| `GET` | `bkkd/riwayat` | `bkkd-riwayat-view` | Mengambil log audit riwayat gabungan (proposal dan verifikasi) dengan filter |

### 3.2 Logika Service Koreksi Verifikasi (`BkkdRiwayatService.php`)

```php
public function catatKoreksiVerifikasi(
    BkkdVerifikasi $verifikasi,
    array $dataBaru,
    string $alasan,
    User $user
): BkkdVerifikasi {
    // 1. Wajib mencantumkan alasan koreksi
    if (empty(trim($alasan))) {
        throw ValidationException::withMessages([
            'alasan_perubahan' => ['Alasan koreksi hasil verifikasi wajib diisi.'],
        ]);
    }

    // 2. Ambil perubahan atribut
    $dirtyKeys = [];
    $before = [];
    $after = [];

    foreach ($dataBaru as $key => $value) {
        if ($verifikasi->isDirty($key)) {
            $dirtyKeys[] = $key;
            $before[$key] = $verifikasi->getOriginal($key);
            $after[$key] = $value;
        }
    }

    if (empty($dirtyKeys)) {
        return $verifikasi;
    }

    // 3. Naikkan versi
    $versiBaru = $verifikasi->versi + 1;
    $verifikasi->fill($dataBaru);
    $verifikasi->versi = $versiBaru;
    $verifikasi->updated_by = $user->uuid;
    $verifikasi->save();

    // 4. Rekam riwayat koreksi
    BkkdVerifikasiRiwayat::create([
        'id_verifikasi' => $verifikasi->id,
        'versi' => $versiBaru,
        'aksi' => 'updated',
        'field_berubah' => $dirtyKeys,
        'data_sebelum' => $before,
        'data_sesudah' => $after,
        'alasan_perubahan' => $alasan,
        'changed_by' => $user->uuid,
    ]);

    return $verifikasi;
}
```

---

## 4. Spesifikasi Frontend Nuxt 4 (`apps/web`)

### 4.1 Halaman Audit Riwayat Terpusat (`pages/admin/bkkd/riwayat/index.vue`)

- **Bilah Tab Utama**:
  - Tab 1: **Riwayat Perubahan Usulan Proposal** (sumber data: `bkkd_proposal_riwayat`).
  - Tab 2: **Riwayat Hasil Verifikasi OPD** (sumber data: `bkkd_verifikasi_riwayat`).
- **Filter Log**:
  - Filter Tahun Anggaran.
  - Filter Rentang Tanggal Kejadian (Tanggal Mulai s.d. Tanggal Selesai).
  - Filter Operator Pengubah (`changed_by`).
  - Filter Jenis Aksi (`created`, `updated`, `superseded`, dll.).
  - Input Pencarian (Nomor Registrasi, Judul Usulan, atau Desa).
- **Tabel Data Riwayat (`UTable`)**:
  - Kolom: Waktu Perubahan, Operator Pelaksana, Proposal Terkait (Tautan ke halaman detail), OPD Terkait (pada tab verifikasi), Jenis Aksi (Badge Warna), Versi Ke-, Daftar Field Berubah, Alasan Perubahan, Aksi.
  - Kolom Aksi: Tombol "Buka Rincian Diff".

### 4.2 Laci Rincian Perbandingan (Slideover Diff)

Saat tombol "Buka Rincian Diff" ditekan, terbuka laci samping (`USlideover`):
- Header: Menampilkan informasi versi, waktu perubahan, dan nama operator pelaksana.
- Kotak Kutipan Alasan: Menampilkan teks alasan perubahan secara jelas.
- Tabel Perbandingan Atribut:
  - Menampilkan setiap kolom yang berubah.
  - Menampilkan nilai lama (kotak merah muda/soft) berdampingan dengan nilai baru (kotak hijau muda/soft).
  - Nilai nominal rupiah, tanggal, dan status diformat secara ramah pembaca.
- Tautan Dokumen Lampiran: Jika terjadi pergantian file, sediakan tautan untuk mengunduh dokumen lama vs dokumen baru secara berdampingan.

---

## 5. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Koreksi pada data verifikasi aktif berhasil merekam baris baru pada tabel `bkkd_verifikasi_riwayat` dengan `aksi = 'updated'`.
- [ ] Penggantian hasil verifikasi (verifikasi ulang) menandai hasil lama sebagai `superseded` dan berkas dokumen lama tetap dapat diunduh dari arsip riwayat.
- [ ] Upaya mengoreksi hasil verifikasi tanpa mencantumkan alasan ditolak oleh sistem dengan validasi HTTP 422.
- [ ] Halaman terpusat `/admin/bkkd/riwayat` berhasil menyajikan log riwayat usulan dan verifikasi dengan filter rentang waktu dan operator yang bekerja.
- [ ] Slideover diff mampu memvisualisasikan perbandingan sebelum vs sesudah dengan format mata uang rupiah dan tanggal yang mudah dipahami.
- [ ] Seluruh aktivitas perubahan verifikasi tetap mengalir ke log audit sistem (`Audit Log`) melalui model trait.

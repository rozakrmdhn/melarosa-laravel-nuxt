# Sub-Issue BKKD-03: Fitur Historis Perubahan Usulan BKKD

- **Status**: Ready for Implementation
- **Kategori**: Audit & Akuntabilitas Usulan (Fitur 3)
- **Komponen**: Snapshot Service, Optimistic Lock, Timeline & Diff UI (`apps/api` & `apps/web`)
- **Dependensi**: BKKD-01 (Pengelolaan Proposal)
- **Dokumen Terkait**: [module_issue_bkkd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/module_issue_bkkd.md)

---

## 1. Deskripsi & Tujuan

Membangun mekanisme pelacakan riwayat perubahan (audit trail & versioning) untuk data usulan proposal BKKD. Setiap kali terjadi pembaruan data usulan, perubahan status, maupun penambahan atau penghapusan berkas lampiran, sistem secara otomatis merekam snapshot data sebelum vs sesudah perubahan ke dalam tabel `bkkd_proposal_riwayat`.

Fitur ini menjamin akuntabilitas data anggaran pemerintah:
1. Operator dan pimpinan dapat melihat siapa yang mengubah data, kapan diubah, kolom apa saja yang diubah, dan apa alasan perubahan tersebut.
2. Mencegah timpa-menimpa data antar operator melalui mekanisme optimistic locking (HTTP 409 Conflict).
3. Mewajibkan pencantuman alasan perubahan jika usulan yang diubah sudah melewati tahapan awal (`draft`).

---

## 2. Mekanisme Snapshot & Versioning Usulan

### 2.1 Nomor Versi & Optimistic Locking

- Kolom `versi` pada tabel `bkkd_proposal` dimulai dari angka 1 dan bertambah satu (+1) setiap kali ada perubahan data atribut.
- Setiap request update (`PUT /bkkd/proposal/{id}`) wajib menyertakan nilai `versi` yang sedang dibuka oleh pengguna di antarmuka web.
- Jika nomor versi dari klien tidak sama dengan nomor versi saat ini di database (menandakan operator lain telah lebih dulu menyimpan perubahan), sistem menolak transaksi dengan status **HTTP 409 Conflict**.
- Antarmuka web Nuxt menangkap respons 409 dan menampilkan dialog peringatan informatif dengan tombol "Muat Ulang Data Terbaru".

### 2.2 Aturan Validasi Alasan Perubahan

- Jika proposal masih berstatus `draft`: Pengisian alasan perubahan bersifat opsional, karena berkas masih dalam tahap pengetikan awal.
- Jika proposal sudah berstatus selain `draft` (`diajukan_opd`, `layak`, `dikembalikan`, dll.): Pengisian `alasan_perubahan` **bersifat wajib**. Jika kosong, sistem mengembalikan status **HTTP 422 Unprocessable Entity**.

### 2.3 Jenis Aksi yang Dicatat (`aksi`)

| Nilai Aksi | Pemicu Perubahan |
| :--- | :--- |
| `created` | Proposal pertama kali dicatat dan disimpan |
| `updated` | Atribut proposal diubah (judul, nilai usulan, volume, uraian) |
| `status_changed` | Perpindahan status (misal diajukan ke OPD atau dibatalkan) |
| `deleted` | Proposal dihapus sementara (soft delete) |
| `restored` | Proposal dipulihkan kembali dari tempat sampah |
| `lampiran_added` | Berkas lampiran baru disematkan |
| `lampiran_removed` | Berkas lampiran dihapus dari proposal |

---

## 3. Spesifikasi Backend (`apps/api`)

### 3.1 Service Pengelolaan Riwayat (`BkkdRiwayatService.php`)

```php
namespace App\Services;

use App\Models\BkkdProposal;
use App\Models\BkkdProposalRiwayat;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BkkdRiwayatService
{
    public function catatPerubahanProposal(
        BkkdProposal $proposal,
        array $dataBaru,
        int $versiKlien,
        ?string $alasan,
        User $user
    ): BkkdProposal {
        // 1. Validasi optimistic locking
        if ($versiKlien !== $proposal->versi) {
            throw new ConflictHttpException(
                'Data proposal ini telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk melihat versi terbaru.'
            );
        }

        // 2. Ambil perubahan nyata
        $dirtyKeys = [];
        $before = [];
        $after = [];

        foreach ($dataBaru as $key => $value) {
            if ($proposal->isDirty($key)) {
                $dirtyKeys[] = $key;
                $before[$key] = $proposal->getOriginal($key);
                $after[$key] = $value;
            }
        }

        // Jika tidak ada perubahan nilai, return langsung tanpa menambah versi
        if (empty($dirtyKeys)) {
            return $proposal;
        }

        // 3. Wajibkan alasan jika bukan status draft
        if ($proposal->status !== 'draft' && empty(trim((string)$alasan))) {
            throw ValidationException::withMessages([
                'alasan_perubahan' => ['Alasan perubahan wajib diisi untuk usulan yang sudah diajukan atau diverifikasi.'],
            ]);
        }

        // 4. Naikkan versi dan perbarui data
        $versiBaru = $proposal->versi + 1;
        $proposal->fill($dataBaru);
        $proposal->versi = $versiBaru;
        $proposal->updated_by = $user->uuid;
        $proposal->save();

        // 5. Tulis snapshot riwayat
        BkkdProposalRiwayat::create([
            'id_proposal' => $proposal->id,
            'versi' => $versiBaru,
            'aksi' => 'updated',
            'field_berubah' => $dirtyKeys,
            'data_sebelum' => $before,
            'data_sesudah' => $after,
            'alasan_perubahan' => $alasan,
            'changed_by' => $user->uuid,
        ]);

        return $proposal;
    }
}
```

### 3.2 Endpoint Riwayat Usulan

- `GET /api/v1/admin/bkkd/proposal/{id}/riwayat`
  - Middleware: `permission:bkkd-riwayat-view`
  - Response: Daftar riwayat terurut descending berdasarkan versi (`versi DESC`), memuat nama user pengubah dari relasi `User`, waktu `created_at`, array `field_berubah`, `data_sebelum`, `data_sesudah`, dan teks `alasan_perubahan`.

---

## 4. Spesifikasi Frontend Nuxt 4 (`apps/web`)

### 4.1 Tab Riwayat pada Detail Proposal (`pages/admin/bkkd/proposal/[id].vue`)

Pada detail proposal, Tab 4 menampilkan komponen `RiwayatTimeline.vue`:
- Tampilan garis waktu vertikal yang menyajikan setiap titik versi.
- Tiap titik memuat: Badge Versi (contoh: "Versi 2"), Nama Operator Pengubah, Waktu Perubahan (format tanggal & jam lokal), Badge Aksi ("Diubah", "Diajukan ke OPD", "Lampiran Ditambah"), dan Alasan Perubahan berkotak kutipan khusus.
- Tombol "Lihat Perbandingan (Diff)" untuk membuka rincian nilai yang berganti.

### 4.2 Komponen Perbandingan Nilai (`RiwayatDiffTable.vue`)

Komponen tabel perbandingan kolom yang diubah:
- Kolom Tabel: Nama Atribut, Nilai Sebelum, Nilai Sesudah.
- Format Data Ramah Pengguna:
  - Nilai nominal uang otomatis diformat ke Rupiah (Rp X.XXX.XXX).
  - Format tanggal dikonversi menjadi format Indonesia (DD MMMM YYYY).
  - ID relasi (seperti `id_desa` atau `id_bidang`) diterjemahkan menjadi nama desa atau nama bidang kegiatan, bukan UUID mentah.

### 4.3 Form Input Alasan Perubahan (`AlasanPerubahanField.vue`)

- Komponen textarea yang disematkan pada modal form edit proposal.
- Kondisi muncul: Muncul secara otomatis jika proposal yang diedit memiliki `status !== 'draft'`.
- Teks petunjuk dan label penjelas: *"Jelaskan dasar pertimbangan atau disposisi pimpinan atas perubahan data usulan ini."*

### 4.4 Penanganan Konflik Versi (409 Conflict)

Jika fungsi `updateProposal` pada composable `useBkkdApi.ts` menerima status HTTP 409:
- Munculkan alert / toast peringatan berwarna oranye atau merah.
- Sediakan tombol aksi langsung: *"Muat Ulang Data Terkini"*, yang memanggil kembali `fetchProposalDetail(id)` agar form terisi dengan data terbaru tanpa kehilangan konteks.

---

## 5. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Setiap penyimpanan perubahan proposal sukses menaikkan nomor versi (+1) dan mencatat baris riwayat baru pada `bkkd_proposal_riwayat`.
- [ ] Pengubahan proposal yang sudah berstatus selain draft ditolak dengan HTTP 422 jika operator tidak mengisi alasan perubahan.
- [ ] Saat dua pengguna mengedit proposal yang sama, penyimpanan kedua berhasil dicegah dengan status HTTP 409 Conflict dan memunculkan notifikasi peringatan.
- [ ] Tab Riwayat pada halaman detail proposal berhasil merender riwayat perubahan secara kronologis.
- [ ] Komponen diff mampu menampilkan perbandingan nilai sebelum vs sesudah dengan format angka rupiah dan nama wilayah yang rapi.
- [ ] Perubahan data usulan tetap tercatat juga pada modul global `Audit Log` melalui trait `Auditable`.

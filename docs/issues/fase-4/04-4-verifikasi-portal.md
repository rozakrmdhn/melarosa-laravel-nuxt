# Sub-Fase 4.4: Transaksi Portal Verifikasi Bertingkat (State Machine)

- **Status**: Ready for Implementation
- **Fase**: 4.4 dari 5
- **Komponen**: Portal Verifikasi 3 Tingkat & Modal Decision Review (`apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Sub-Fase 4.3 (Data Segmen Spasial)

---

## 1. Deskripsi & Tujuan

Membangun Portal Verifikasi Bertingkat (`/admin/verifikasi-segmen`) untuk mengelola antrean telaah dan persetujuan survei fisik lapangan secara berjenjang:
1. **Desa**: Mengajukan verifikasi (`submitDesa`: draft / rejected $\rightarrow$ `submitted_desa`).
2. **Kecamatan**: Memeriksa segmen di kecamatannya (`verifyKecamatan`: `submitted_desa` $\rightarrow$ `verified_kecamatan` atau `rejected_kecamatan`).
3. **Bappeda**: Mengesahkan final per-kecamatan se-kabupaten (`verifyBappeda`: `verified_kecamatan` $\rightarrow$ `verified_bappeda` atau `rejected_bappeda`).

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Halaman antrean verifikasi memiliki 3 tab terpisah dengan proteksi hak akses role yang ketat.
- [ ] **Tab 1 (Pengajuan Desa)**: Menampilkan data segmen berstatus `draft` dan `rejected_kecamatan` dengan tombol aksi "Ajukan Verifikasi".
- [ ] **Tab 2 (Verifikasi Kecamatan)**:
  - Hanya menampilkan segmen berstatus `submitted_desa` yang berada di wilayah kecamatan user `verifierKecamatan`.
  - Dilengkapi tombol "Setujui ke Bappeda" dan "Tolak ke Desa".
  - Jika menolak, input **Catatan Revisi** wajib diisi.
- [ ] **Tab 3 (Verifikasi Bappeda)**:
  - Menampilkan segmen berstatus `verified_kecamatan` dari seluruh kabupaten.
  - Memiliki filter dropdown per-kecamatan.
  - Dilengkapi tombol "Sahkan Final (Verified)" dan "Tolak / Minta Perbaikan" dengan catatan.
- [ ] Tersedia Drawer Pratinjau Cepat yang menampilkan peta mini geometri segmen dan foto survei kondisi fisik lapangan.

---

## 3. Rincian File yang Dibuat

1. **`apps/web/app/composables/useVerifikasiWorkflow.ts`**:
   - Handler eksekusi submit dan verifikasi:
     - `submitSegmen(id)`
     - `verifyKecamatan(id, action: 'approve' | 'reject', catatan?: string)`
     - `verifyBappeda(id, action: 'approve' | 'reject', catatan?: string)`
   - State reaktif counter antrean pending untuk badge indikator menu.

2. **`apps/web/app/pages/admin/verifikasi-segmen/index.vue`**:
   - Navigasi tab antrean (`UTabs`):
     - "Pengajuan Desa" (Badge: draft count)
     - "Verifikasi Tingkat Kecamatan" (Badge: submitted_desa count)
     - "Verifikasi Tingkat Bappeda" (Badge: verified_kecamatan count)
   - Tabel antrean lengkap dengan info pelapor, tanggal pengajuan, estimasi biaya plotting, dan foto survei.

3. **`apps/web/app/components/verifikasi/VerifikasiActionModal.vue`**:
   - Modal konfirmasi keputusan (`UModal`):
     - Tampilan status dan nama segmen yang sedang diproses.
     - Pilihan aksi: Setujui (Hijau) atau Tolak (Merah).
     - Textarea input catatan revisi (wajib diisi bila aksi adalah 'Tolak').
     - Validasi form sebelum request diproses ke backend.

4. **`apps/web/app/components/verifikasi/SegmenDetailDrawer.vue`**:
   - Drawer geser kanan (`USlideover`):
     - Peta mini vektor segmen jalan/jembatan.
     - Pratinjau resolusi tinggi foto dokumentasi survei lapangan.
     - Riwayat catatan perbaikan dari verifikator sebelumnya.

---

## 4. Checklist Tugas

- [ ] Buat composable `apps/web/app/composables/useVerifikasiWorkflow.ts`
- [ ] Buat komponen modal keputusan `VerifikasiActionModal.vue`
- [ ] Buat drawer detail telaah fisik `SegmenDetailDrawer.vue`
- [ ] Susun halaman portal verifikasi `apps/web/app/pages/admin/verifikasi-segmen/index.vue`
- [ ] Uji alur state machine submit $\rightarrow$ tolak kecamatan $\rightarrow$ perbaikan desa $\rightarrow$ setujui kecamatan $\rightarrow$ sahkan bappeda

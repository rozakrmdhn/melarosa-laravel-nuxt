# Sub-Fase 4.5: Transaksi Berita Acara & Monitoring Realisasi Lapangan

- **Status**: Ready for Implementation
- **Fase**: 4.5 dari 5
- **Komponen**: Halaman Berita Acara, Binding Items Segmen, & Snapshot Riwayat Revisi (`apps/web`)
- **Referensi Skema**: [`monitoring_realisasi_schema.md`](file:///c:/laragon/www/melarosa-laravel-nuxt/monitoring_realisasi_schema.md)
- **Dependensi**: Sub-Fase 4.2 (Plotting Anggaran) & Sub-Fase 4.4 (Segmen Verified Bappeda)

---

## 1. Deskripsi & Tujuan

Membangun modul Monitoring Realisasi & Berita Acara (`/admin/monitoring-realisasi`) untuk merekam Berita Acara (BA) serah terima fisik lapangan, mengikat multiple segmen infrastruktur berstatus `verified_bappeda` yang telah selesai dibangun, menghitung persentase capaian target fisik, serta mengelola alur pengembalian revisi dengan pencatatan snapshot audit historis.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Tabel Berita Acara menampilkan daftar BA per tahun anggaran, nomor BA, pagu kegiatan, rencana vs realisasi panjang fisik, persentase ketercapaian (%), dan badge status monitoring (`draft`, `submitted`, `approved`, `reverted`).
- [ ] Modal input BA baru memvalidasi keunikan nomor BA per tahun anggaran, menghubungkan ke induk Plotting Anggaran, dan menghitung persentase capaian secara otomatis.
- [ ] Tersedia fitur **Penyematan Segmen Fisik (Binding Items)**: Modal picker untuk memilih satu atau beberapa segmen berstatus `verified_bappeda` yang menjadi cakupan paket BA tersebut.
- [ ] Tersedia fitur **Pengembalian untuk Revisi (`reverted`)**: Modal input catatan koreksi (hasil audit inspektorat/kesalahan ukur) yang secara otomatis merekam snapshot data kondisi lama ke tabel riwayat.
- [ ] Tersedia Drawer Riwayat Snapshot: Menampilkan daftar riwayat revisi dan membandingkan perubahan data sebelum vs sesudah revisi dilakukan.

---

## 3. Rincian File yang Dibuat

1. **`apps/web/app/pages/admin/monitoring-realisasi/index.vue`**:
   - Header modul dengan filter tahun anggaran, status monitoring, dan tombol "Tambah Berita Acara".
   - Tabel Nuxt UI (`UTable`) dengan kolom: Nomor BA, Tahun, Wilayah, Pagu Kegiatan, Rencana (m), Realisasi (m), Persentase Capaian, Status, dan Aksi.

2. **`apps/web/app/components/monitoring-realisasi/BeritaAcaraFormModal.vue`**:
   - Modal form pembuatan dan edit Berita Acara:
     - Nomor Berita Acara (BA)
     - Pemilih Alokasi Plotting Anggaran (Dropdown reaktif)
     - Wilayah (Kecamatan & Desa)
     - Sumber Dana & Tahun Anggaran
     - Rencana Panjang Fisik (m) vs Realisasi Panjang Lapangan (m)
     - Indikator Visual Persentase Capaian (Progress Bar real-time)
     - Keterangan & Catatan Pelaksanaan

3. **`apps/web/app/components/monitoring-realisasi/SegmenPickerModal.vue`**:
   - Modal pemilih segmen fisik (`verified_bappeda`) untuk disematkan ke BA (`monitoring_realisasi_items`):
     - Tabel daftar segmen yang tersedia pada desa/wilayah terkait.
     - Checkbox multi-select dan indikator akumulasi total panjang segmen terpilih.

4. **`apps/web/app/components/monitoring-realisasi/RevisiHistoryDrawer.vue`**:
   - Drawer geser kanan (`USlideover`):
     - Form input aksi pengembalian revisi (Tombol "Kembalikan untuk Revisi" + Catatan Revisi).
     - Timeline historis audit revisi: Waktu koreksi, aktor pengubah, catatan evaluasi, dan JSON snapshot data lama.

---

## 4. Checklist Tugas

- [ ] Buat modal form Berita Acara `BeritaAcaraFormModal.vue`
- [ ] Buat modal picker penyematan segmen `SegmenPickerModal.vue`
- [ ] Buat drawer riwayat dan form revisi `RevisiHistoryDrawer.vue`
- [ ] Susun halaman utama `apps/web/app/pages/admin/monitoring-realisasi/index.vue`
- [ ] Uji alur input BA $\rightarrow$ bind item segmen $\rightarrow$ trigger revisi snapshot $\rightarrow$ periksa riwayat audit

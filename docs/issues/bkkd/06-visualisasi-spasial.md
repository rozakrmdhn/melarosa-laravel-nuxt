# Sub-Issue BKKD-05: Fitur Visualisasi Spasial BKKD (Roadmap Fase 2)

- **Status**: Future Roadmap (Fase 2)
- **Kategori**: Visualisasi Web GIS (Fitur 5)
- **Komponen**: PostGIS, Martin Vector Tile Server, MapLibre GL Canvas (`apps/api` & `apps/web`)
- **Dependensi**: BKKD-00 (Kolom `geom` PostGIS) & BKKD-01 (Pengelolaan Proposal)
- **Dokumen Terkait**: [module_issue_bkkd.md](file:///c:/laragon/www/melarosa-laravel-nuxt/docs/issues/module_issue_bkkd.md)

---

## 1. Deskripsi & Tujuan

Mempersiapkan rancangan arsitektur dan peta jalan implementasi untuk fitur Visualisasi Spasial Usulan BKKD pada Web GIS Melarosa. Visualisasi spasial mempermudah pimpinan daerah, Bappeda, dan verifikator dalam meninjau pemerataan sebaran bantuan keuangan antar desa, memantau konsentrasi usulan fisik, serta menghindari duplikasi alokasi anggaran pada lokasi yang berdekatan.

Fitur ini direncanakan untuk Fase 2, namun seluruh fondasi skema basis data telah dipersiapkan sejak Fase 1 sehingga implementasi peta tidak memerlukan migrasi ulang yang besar.

---

## 2. Kesiapan Fondasi pada Fase 1

Untuk menjamin kompatibilitas ke depan, hal-hal berikut telah diterapkan pada Fase 1:

1. **Kolom Geometri Baku PostGIS**:
   - Tabel `bkkd_proposal` telah memiliki kolom `geom` dengan tipe `geometry(Geometry, 4326) nullable`.
   - Menggunakan tipe `Geometry` umum (fleksibel untuk Point, LineString, maupun Polygon) karena usulan BKKD mencakup seluruh ragam kegiatan:
     - **Point (Titik)**: Pembangunan balai desa, posyandu, sarana air bersih, pengadaan alsintan, atau pelatihan.
     - **LineString (Garis)**: Pembangunan jalan desa, rabat beton, drainase, atau penerangan jalan.
     - **Polygon (Area)**: Kawasan pasar desa, lapangan olahraga, atau penataan kawasan permukiman.
2. **Indeks Spasial GiST**:
   - Kolom `geom` telah dilengkapi indeks spasial `$table->spatialIndex('geom')` agar pencarian berbasis radius (bounding box) dan tiling vektor tetap responsif.
3. **Form Fase 1 Bersifat Opsional**:
   - Pada form pencatatan proposal Fase 1, input koordinat peta belum diwajibkan (nullable) dan baru mengandalkan teks deskripsi `lokasi_kegiatan` (nama dusun, RW/RT).

---

## 3. Desain Arsitektur Visualisasi Spasial (Fase 2)

Penyajian peta spasial BKKD dirancang melalui 2 pendekatan lapisan visual:

### 3.1 Pendekatan Makro: Peta Tematik Choropleth Agregat Desa

Peta sebaran tingkat desa yang tidak memerlukan input titik koordinat manual satu per satu:
- Memanfaatkan layer polygon wilayah yang sudah ada (`bataswilayah_desa`).
- Melakukan agregasi query SQL dinamis berdasarkan tahun anggaran:
  ```sql
  SELECT 
      d.id AS id_desa,
      d.nama_desa,
      d.geom,
      COUNT(p.id) AS total_proposal,
      COALESCE(SUM(p.nilai_usulan), 0) AS total_nilai_usulan,
      COUNT(CASE WHEN p.status = 'layak' THEN 1 END) AS total_layak
  FROM bataswilayah_desa d
  LEFT JOIN bkkd_proposal p ON p.id_desa = d.id AND p.tahun_anggaran = :tahun AND p.deleted_at IS NULL
  GROUP BY d.id, d.nama_desa, d.geom;
  ```
- **Pewarnaan Tematik di Peta (Color Ramps)**:
  - Desa dengan total usulan tinggi diwarnai lebih pekat.
  - Saat polygon desa diklik, muncul popup kartu rekapitulasi usulan BKKD desa tersebut.

### 3.2 Pendekatan Mikro: Sebaran Geometri Lokasi Kegiatan (`geom`)

Peta sebaran fisik kegiatan aktual di lapangan:
- Sumber Geometri: Disajikan melalui tile server Martin (`martin.yaml`) atau endpoint GeoJSON terfilter (`/api/v1/admin/bkkd/geojson`).
- Simbologi Peta:
  - Titik (Point): Ikon penanda sesuai bidang kegiatan (infrastruktur, sarpras, pemberdayaan).
  - Garis (LineString): Garis ruas jalan atau saluran dengan ketebalan dan warna sesuai status kelayakan (Hijau untuk Layak, Kuning untuk Proses, Merah untuk Ditolak).
- Interaksi Tool Digitasi (Input Geometri):
  - Pada modal input proposal Fase 2, disediakan modal peta picker kecil:
    - Tombol "Plot Titik Lokasi" (klik peta untuk menaruh pin koordinat).
    - Tombol "Tarik Garis Ruas" (menggambar polyline panjang jalan).
    - Tombol "Ambil Titik Tengah Desa" (auto-fill centroid polygon desa jika lokasi spesifik belum diketahui).

---

## 4. Penanganan Riwayat Geometri & Ukuran Basis Data

Jika data koordinat `geom` diubah pada Fase 2, penyimpanan koordinat mentah berbobot besar (banyak verteks) ke dalam kolom JSON `data_sebelum` dan `data_sesudah` di tabel riwayat dapat menyebabkan database membengkak.

**Rekomendasi Penanganan:**
1. Pada tabel riwayat `bkkd_proposal_riwayat`, jika kolom `geom` berubah, rekam informasi yang disederhanakan:
   - Catat format ringkas seperti titik tengah (centroid WKT: `POINT(lng lat)`).
   - Catat panjang garis (meter) atau luas area yang berubah.
2. Tidak menyimpan koordinat ribuan titik vertex ke dalam log teks diff riwayat, melainkan cukup menandai bahwa geometri telah diperbarui.

---

## 5. Integrasi Sistem & Menu Peta

1. **Pendaftaran Layer Spasial**:
   - Daftarkan layer baru pada master layer: "Sebaran Usulan BKKD (Makro)" dan "Lokasi Usulan BKKD (Kegiatan)".
2. **Permission Baru Fase 2**:
   - `bkkd-peta-view`: Izin untuk membuka halaman peta visualisasi spasial BKKD.
3. **Filter Peta Terpadu**:
   - Peta dilengkapi kontrol filter: Tahun Anggaran, Bidang Kegiatan, OPD Pemeriksa, dan Status Kelayakan.

---

## 6. Kriteria Kesiapan Eksekusi Fase 2

- [ ] Kolom `geom` dan indeks spasial pada tabel `bkkd_proposal` telah terpasang dengan baik sejak migrasi Fase 1.
- [ ] Endpoint agregasi rekapitulasi spasial per desa siap dikonsumsi oleh layer MapLibre GL.
- [ ] Konfigurasi query spasial terdaftar pada service `martin.yaml` jika menggunakan vector tiles.
- [ ] Tersedia komponen modal picker peta untuk digitasi titik dan garis pada form proposal BKKD.

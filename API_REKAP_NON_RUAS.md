# Dokumentasi Teknis REST API: Rekapan Data Jalan Ruas (Poros Desa) & Non-Ruas

Dokumen ini merupakan panduan teknis dan spesifikasi REST API untuk modul **Rekap Data Jalan (Ruas Poros Desa & Non-Ruas / Segmen Mandiri)** pada sistem WebGIS GIGS Bappeda Bojonegoro. Dokumen ini ditujukan untuk **Tim Frontend** dan integrasi **aplikasi frontend eksternal/portal publik** sebagai acuan implementasi antarmuka hirarki:
$$\text{Kecamatan (Level 1)} \longrightarrow \text{Desa (Level 2)} \longrightarrow \text{Daftar Ruas / Segmen (Level 3)} \longrightarrow \text{Detail Segmen Fisik (Level 4)}$$

---

## 1. Kebijakan Akses & Kredensial (Public Access vs RBAC)

> [!IMPORTANT]
> **Akses Tanpa Kredensial (Public Access / No Auth Token):**
> Seluruh endpoint dengan metode **`GET`** dapat diakses secara langsung **tanpa kredensial** (tanpa header `Authorization: Bearer <token>`). Hal ini dirancang agar data rekapan dapat dengan mudah diintegrasikan ke aplikasi frontend lain, landing page, atau portal keterbukaan informasi publik tanpa hambatan otentikasi.

### Matriks Akses & Metode HTTP:

| Metode | Status Kredensial | Perilaku & Hak Akses |
| :---: | :---: | :--- |
| **`GET`** | **Tanpa Kredensial (Anonymous)** *(Direkomendasikan untuk frontend eksternal)* | **Akses Publik Bebas**: Pengguna dapat meminta data untuk seluruh kabupaten, menyaring kecamatan mana pun melalui parameter query (`?id_kecamatan=...`), dan membuka seluruh data desa hingga segmen fisik tanpa login. |
| **`GET`** | **Dengan Kredensial (Optional Auth)** *(Digunakan pada CMS Internal)* | Jika header `Authorization: Bearer <TOKEN>` dikirimkan, sistem akan mengaktifkan aturan **RBAC Scoping**: <br/>• `super_admin`, `admin`, `operator_bappeda`, `operator_opd`: Akses seluruh kabupaten.<br/>• `operator_kecamatan`: Akses otomatis terkunci pada wilayah kecamatannya (mengakses wilayah lain akan merespons `403 Forbidden`).<br/>• `operator_desa`: Akses otomatis terkunci pada wilayah desanya. |
| **`POST`** | **Wajib Kredensial (`auth: 'jwt'`)** | Operasi mutasi/analisis seperti `POST /v1/analisis/jalan/extract-segmen` **wajib** menyertakan token otentikasi. |

---

## 2. Konsep Data: Ruas (Poros Desa) vs Non-Ruas (Segmen Mandiri)

Sistem WebGIS mengelola dua kategori jalan desa:

```
                                  [DATA INFRASTRUKTUR JALAN]
                                              |
                     +------------------------+------------------------+
                     |                                                 |
             [1. JALAN RUAS]                                  [2. JALAN NON-RUAS]
         (Master SK Poros Desa)                           (Segmen Mandiri / Non-SK)
                     |                                                 |
• Berdasarkan SK penetapan ruas poros desa.      • Segmen fisik tanpa parent ruas (`parent_id IS NULL`).
• Tabel master: `jalan_porosdesa`.               • Meliputi jalan lingkungan, jalan desa mandiri, BKK.
• Memiliki `kode_ruas`, `nama_ruas`, master m.   • Hasil digitasi resmi terverifikasi (`terverifikasi`).
• Hirarki: Ruas -> Rincian Segmen Fisik.         • Hirarki: Langsung berupa segmen jalan mandiri per desa.
```

---

## 3. Matriks Endpoint API: Ruas & Non-Ruas

Backend menyediakan URL dedikasi maupun varian query param untuk fleksibilitas integrasi:

| Level Hirarki | Deskripsi Data | Metode | Endpoint RUAS (Poros Desa) | Endpoint NON-RUAS (Mandiri) |
| :---: | :--- | :---: | :--- | :--- |
| **Level 1** | Agregat per Kecamatan | `GET` | `/v1/rekap/kecamatan` | `/v1/rekap/non-ruas/kecamatan`<br/>*(Alias: `/v1/rekap/kecamatan?kategori=non_ruas`)* |
| **Level 2** | Agregat per Desa | `GET` | `/v1/rekap/desa?id_kecamatan={id}` | `/v1/rekap/non-ruas/desa?id_kecamatan={id}`<br/>*(Alias: `/v1/rekap/desa?id_kecamatan={id}&kategori=non_ruas`)* |
| **Level 3** | Daftar Ruas / Segmen Desa | `GET` | `/v1/rekap/desa/{idDesa}/ruas` | `/v1/rekap/non-ruas/desa/{idDesa}/ruas`<br/>*(Alias: `/v1/rekap/desa/{idDesa}/ruas?kategori=non_ruas`)* |
| **Level 4** | Detail Segmen Fisik | `GET` | `/v1/rekap/ruas/{idRuas}/segmen` | `/v1/rekap/ruas/{idSegmen}/segmen` *(Otomatis mengenali UUID segmen non-ruas)* |

> 💡 **Informasi Prefix URL:** Seluruh endpoint otomatis dapat dipanggil menggunakan prefix `/v1/...` maupun `/api/v1/...`.

---

## 4. Parameter Filter Global (Query Parameters)

Parameter berikut didukung oleh endpoint Ruas dan Non-Ruas:

| Parameter | Tipe | Contoh | Keterangan |
| :--- | :---: | :--- | :--- |
| `id_kecamatan` | Integer | `352213` | Filter ID Kecamatan (misal: Balen `352213`). |
| `id_desa` | BigInt | `3522132019` | Filter ID Desa (misal: Kedungdowo `3522132019`). |
| `kecamatan` | String | `Balen` | Filter pencarian nama kecamatan (*case-insensitive*). |
| `desa` | String | `Kedungdowo` | Filter pencarian nama desa (*case-insensitive*). |
| `tahun` | Integer | `2025` | Filter tahun pelaksanaan pembangunan segmen. |
| `kondisi` | String | `baik` | Filter kondisi perkerasan (`baik`, `sedang`, `rusak ringan`, `rusak berat`). |
| `sumber_dana` | String | `BKK` | Filter sumber dana pembangunan (`BKK`, `APBD`, `DD`, dll.). |
| `status_verifikasi` | String | `terverifikasi` | Default: `terverifikasi`. Kirim `semua` atau `all` untuk seluruh status. |

---

## 5. Rincian Spesifikasi & Contoh Response

### 5.1. Level 1: Agregat Tingkat Kecamatan

Mengembalikan ringkasan rekapitulasi data jalan pada tingkat kecamatan (28 kecamatan se-Kabupaten Bojonegoro).

#### A. Data RUAS (Poros Desa):
- **URL**: `GET /v1/rekap/kecamatan`
- **Akses**: Tanpa Kredensial
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data rekap kecamatan",
  "result": [
    {
      "id_kecamatan": 352213,
      "nama_kecamatan": "Balen",
      "jumlah_desa": 23,
      "jumlah_desa_terbangun": 18,
      "jumlah_ruas": 42,
      "total_panjang": 68420.5,
      "panjang_dibangun": 54200.2,
      "panjang_belum": 14220.3,
      "progress": 79.22,
      "jumlah_segmen": 65
    }
  ]
}
```

#### B. Data NON-RUAS (Segmen Mandiri):
- **URL**: `GET /v1/rekap/non-ruas/kecamatan` *(atau `/v1/rekap/kecamatan?kategori=non_ruas`)*
- **Akses**: Tanpa Kredensial
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data rekap kecamatan non-ruas",
  "result": [
    {
      "id_kecamatan": 352213,
      "nama_kecamatan": "Balen",
      "jumlah_desa": 23,
      "jumlah_desa_terbangun": 15,
      "jumlah_ruas": 29,
      "total_panjang": 10345.6,
      "panjang_dibangun": 10345.6,
      "panjang_belum": 0,
      "progress": 100,
      "jumlah_segmen": 29
    }
  ]
}
```

---

### 5.2. Level 2: Agregat Tingkat Desa

Mengembalikan daftar desa dalam satu kecamatan beserta agregat panjang dan jumlah jalan saat baris kecamatan di-*expand*.

#### A. Data RUAS (Poros Desa):
- **URL**: `GET /v1/rekap/desa?id_kecamatan=352213`
- **Akses**: Tanpa Kredensial
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data rekap desa",
  "result": [
    {
      "id_desa": 3522132019,
      "nama_desa": "Kedungdowo",
      "kecamatan": "Balen",
      "jumlah_ruas": 5,
      "total_panjang": 7450.8,
      "panjang_dibangun": 5920.4,
      "panjang_belum": 1530.4,
      "progress": 79.46,
      "jumlah_segmen": 8
    }
  ]
}
```

#### B. Data NON-RUAS (Segmen Mandiri):
- **URL**: `GET /v1/rekap/non-ruas/desa?id_kecamatan=352213` *(atau `/v1/rekap/desa?id_kecamatan=352213&kategori=non_ruas`)*
- **Akses**: Tanpa Kredensial
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data rekap desa non-ruas",
  "result": [
    {
      "id_desa": 3522132019,
      "nama_desa": "Kedungdowo",
      "kecamatan": "Balen",
      "jumlah_ruas": 6,
      "total_panjang": 1301.8,
      "panjang_dibangun": 1301.8,
      "panjang_belum": 0,
      "progress": 100,
      "jumlah_segmen": 6
    }
  ]
}
```

---

### 5.3. Level 3: Daftar Ruas / Segmen per Desa

Mengembalikan daftar ruas jalan poros desa atau daftar segmen non-ruas saat baris desa di-*expand*.

#### A. Data RUAS (Daftar Ruas Poros Desa):
- **URL**: `GET /v1/rekap/desa/{idDesa}/ruas` (Contoh: `/v1/rekap/desa/3522132019/ruas`)
- **Akses**: Tanpa Kredensial
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data ruas",
  "result": [
    {
      "id": 1420,
      "kode_ruas": "13.019.01",
      "nama_ruas": "Kedungdowo - Kenep",
      "panjang_master": 2450.0,
      "panjang_dibangun": 2100.0,
      "panjang_tersisa": 350.0,
      "progress": 85.71,
      "jumlah_segmen": 3,
      "is_poros": true,
      "geom": {
        "type": "LineString",
        "coordinates": [
          [111.96102, -7.18201],
          [111.96345, -7.18312]
        ]
      }
    }
  ]
}
```

#### B. Data NON-RUAS (Daftar Segmen Mandiri Desa):
- **URL**: `GET /v1/rekap/non-ruas/desa/{idDesa}/ruas` *(atau `/v1/rekap/desa/{idDesa}/ruas?kategori=non_ruas`)*
- **Akses**: Tanpa Kredensial
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data segmen non-ruas",
  "result": [
    {
      "id": "42d36bfc-24fa-46d7-846f-9bd5dbc6df2e",
      "kode_ruas": "-",
      "nama_ruas": "Jalan Lingkungan RT. 02 RW. 01",
      "panjang_master": 127.1,
      "panjang_dibangun": 127.1,
      "panjang_tersisa": 0,
      "progress": 100,
      "jumlah_segmen": 1,
      "panjang": 127.1,
      "lebar": 3,
      "kondisi": "baik",
      "status_kondisi": "Eksisting",
      "tahun_pembangunan": 2025,
      "sumber_dana": "BKK",
      "status_aset": "Pemerintah Desa",
      "status_verifikasi": "terverifikasi",
      "keterangan": "",
      "atribut": {
        "status_jalan": "Jalan Desa",
        "jenis_perkerasan": "Aspal"
      },
      "geom": {
        "type": "LineString",
        "coordinates": [
          [111.96541, -7.18523],
          [111.96620, -7.18535],
          [111.96655, -7.18540]
        ]
      },
      "is_poros": false
    }
  ]
}
```

> 💡 **Keseragaman Format (Harmonisasi Properti):**
> Baik data Ruas maupun Non-Ruas menyertakan field standar: `id`, `kode_ruas`, `nama_ruas`, `panjang_master`, `panjang_dibangun`, `panjang_tersisa`, `progress`, `jumlah_segmen`, `is_poros`, dan `geom`. Dengan begitu, tabel hirarki frontend dapat menggunakan komponen baris yang sama.

---

### 5.4. Level 4: Rincian Segmen Fisik Hasil Pembangunan

Mengembalikan rincian segmen fisik individual (terbangun/riwayat penanganan).

- **URL**: `GET /v1/rekap/ruas/{idRuasOrSegmen}/segmen`
- **Akses**: Tanpa Kredensial
- **Karakteristik**:
  - Jika ID adalah **ID Ruas Poros Desa** (Integer, misal `1420`), mengembalikan seluruh segmen fisik anak yang terhubung ke ruas tersebut.
  - Jika ID adalah **UUID Segmen Non-Ruas** (misal `42d36bfc-24fa-46d7-846f-9bd5dbc6df2e`), server otomatis melakukan *fallback* dan mengembalikan detail segmen tersebut.
- **Contoh Response (`200 OK`)**:
```json
{
  "status": "success",
  "message": "Berhasil mengambil data segmen",
  "result": [
    {
      "id": "11985160-c322-4214-a957-c30eaec2177d",
      "feature_type": "segmen",
      "tahun": 2024,
      "panjang": 650.0,
      "lebar": 3.5,
      "kondisi": "baik",
      "sumber_dana": "BKK",
      "status_kondisi": "Eksisting",
      "keterangan": "Pembangunan T.A. 2024",
      "geom": {
        "type": "LineString",
        "coordinates": [
          [111.96102, -7.18201],
          [111.96250, -7.18260]
        ]
      }
    }
  ]
}
```

---

## 6. Contoh Kode Implementasi Frontend (Axios / Fetch)

Karena seluruh endpoint `GET` berstatus publik tanpa kredensial, pemanggilan API dari frontend lain sangat ringkas dan tidak memerlukan konfigurasi header otentikasi:

### 6.1. Service API JavaScript / TypeScript (Publik Tanpa Token)
```javascript
import axios from 'axios';

const apiPublic = axios.create({
  baseURL: 'http://localhost:3000/v1' // Ganti sesuai base URL API
});

// ==============================
// 1. DATA RUAS (POROS DESA)
// ==============================

// Level 1: Seluruh Kecamatan
export const getRekapRuasKecamatan = async (params = {}) => {
  const res = await apiPublic.get('/rekap/kecamatan', { params });
  return res.data.result;
};

// Level 2: Desa dalam Kecamatan
export const getRekapRuasDesa = async (idKecamatan, params = {}) => {
  const res = await apiPublic.get('/rekap/desa', {
    params: { id_kecamatan: idKecamatan, ...params }
  });
  return res.data.result;
};

// Level 3: Ruas dalam Desa
export const getRekapRuasByDesa = async (idDesa, params = {}) => {
  const res = await apiPublic.get(`/rekap/desa/${idDesa}/ruas`, { params });
  return res.data.result;
};

// Level 4: Segmen dalam Ruas
export const getDetailSegmenRuas = async (idRuas) => {
  const res = await apiPublic.get(`/rekap/ruas/${idRuas}/segmen`);
  return res.data.result;
};

// ==============================
// 2. DATA NON-RUAS (MANDIRI / BKK)
// ==============================

// Level 1: Seluruh Kecamatan Non-Ruas
export const getRekapNonRuasKecamatan = async (params = {}) => {
  const res = await apiPublic.get('/rekap/non-ruas/kecamatan', { params });
  return res.data.result;
};

// Level 2: Desa Non-Ruas dalam Kecamatan
export const getRekapNonRuasDesa = async (idKecamatan, params = {}) => {
  const res = await apiPublic.get('/rekap/non-ruas/desa', {
    params: { id_kecamatan: idKecamatan, ...params }
  });
  return res.data.result;
};

// Level 3: Segmen Non-Ruas dalam Desa
export const getRekapNonRuasByDesa = async (idDesa, params = {}) => {
  const res = await apiPublic.get(`/rekap/non-ruas/desa/${idDesa}/ruas`, { params });
  return res.data.result;
};
```

---

## 7. Rekomendasi Desain Antarmuka Frontend

1. **Tab Switcher (Ruas vs Non-Ruas)**:
   - Sediakan navigasi tab pada bagian atas halaman tabel rekap:
     - 🛣️ **Tab 1: Ruas Jalan Poros Desa (Master SK)**
     - 🛤️ **Tab 2: Jalan Non-Ruas (Segmen Mandiri / BKK)**
2. **Keterangan Badge Kolom**:
   - Untuk data Ruas: Beri badge hijau `Poros Desa` dengan menampilkan `kode_ruas`.
   - Untuk data Non-Ruas: Beri badge abu-abu `Non-Ruas` atau `Segmen Mandiri` dengan menampilkan informasi jenis perkerasan (`atribut.jenis_perkerasan`).
3. **Peta Spasial Interaktif**:
   - Properti `geom` (GeoJSON `LineString`) pada Level 3 dan Level 4 dapat langsung di-pass ke komponen Leaflet / MapLibre GL untuk menampilkan visualisasi garis jalan pada peta secara instan saat pengguna menekan ikon lokasi/peta di setiap baris tabel.

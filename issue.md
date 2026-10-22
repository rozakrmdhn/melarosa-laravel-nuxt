# [FEAT] Integrasi Katalog Peta Geoportal Bojonegoro ke dalam Daftar Layer WebGIS Melarosa

> **Dokumen Panduan Implementasi Teknis (Technical Specification & Task Plan)**  
> **Target Pelaksana:** Senior / Junior Full-Stack Developer & AI Coding Agent  
> **Status:** Siap Dikerjakan (Ready for Implementation)  
> **Prioritas:** Tinggi (Interoperabilitas Satu Data Spasial Daerah)  
> **Workspace:** `apps/api` (Laravel 13 + PostgreSQL/PostGIS) & `apps/web` (Nuxt 4 + OpenLayers 10 + Nuxt UI)  
> **Dokumen Referensi:** [Fitur-Integrasi-Geoportal.md](file:///c:/laragon/www/melarosa-laravel-nuxt/Fitur-Integrasi-Geoportal.md)

---

## 1. Ringkasan Eksekutif & Latar Belakang

### 1.1 Masalah & Kebutuhan Bisnis
Pemerintah Kabupaten Bojonegoro memiliki Simpul Jaringan Geoportal resmi (`geoportal.bojonegorokab.go.id`) yang mempublikasikan berbagai peta tematik sektoral dari berbagai Organisasi Perangkat Daerah (OPD) seperti peta batas wilayah, tata ruang, tutupan lahan, jaringan irigasi, fasilitas umum, dan kebencanaan.

Saat ini pada WebGIS Melarosa:
1. **Redundansi dan Beban Administrasi:** Admin Melarosa harus mengunggah atau mengonfigurasi ulang satu per satu shapefile/layer jika ingin menayangkan layer sektoral eksternal, yang berisiko data menjadi usang (*out-of-sync*).
2. **Ketiadaan Hubungan Simpul Jaringan:** Belum ada integrasi langsung dengan layanan katalog standar OGC CSW (*Catalogue Service for the Web*) milik Geoportal Bojonegoro.
3. **Kebutuhan Analisis Tumpang Susun (*Overlay*):** Tim teknis perencana (Bappeda/Dinas PU) memerlukan visualisasi terpadu untuk menumpangsusunkan layer ruas jalan poros desa dan infrastruktur fisik Melarosa secara langsung dengan peta tematik resmi daerah dalam satu layar kerja (*single pane of glass*).

### 1.2 Tujuan Pekerjaan
1. Membangun modul backend Laravel (`apps/api`) untuk memanen (*harvest*) dan menyinkronkan metadata katalog peta dari layanan OGC CSW Geoportal Bojonegoro (`geoportal.bojonegorokab.go.id/csw/`) secara otomatis terjadwal dan manual.
2. Memperluas skema basis data `layers` guna menampung atribut katalog Geoportal (Kategori/Tema, OPD Wali Data, URL WMS/WFS, Abstrak/Deskripsi, dan Legenda).
3. Mengembangkan antarmuka admin di `/admin/layers` untuk kontrol sinkronisasi katalog, status layer, dan filter sumber layer.
4. Menyediakan antarmuka interaktif pada Panel Layer WebGIS Melarosa (Desktop & Mobile) dengan tab khusus **"Layer Geoportal Bojonegoro"**, fitur pencarian cepat, pengelompokan tema/urusan, kontrol transparansi (*opacity*), dan visualisasi legenda resmi.
5. Mendukung interaksi peta OpenLayers 10: *render overlay* layer WMS eksternal serta identifikasi atribut objek fitur via OGC WMS `GetFeatureInfo` / CORS Proxy dengan tampilan callout popup.

---

## 2. Arsitektur Teknis Integrasi

### 2.1 Diagram Alur Data Integrasi

```
+-----------------------------------------------------------+
|               Geoportal Bojonegoro (Eksternal)            |
|       URL: https://geoportal.bojonegorokab.go.id/         |
+-----------------------------+-----------------------------+
                              |
     1. CSW GetRecords (XML)  |  4. OGC WMS Tiles & GetFeatureInfo
                              v
+-----------------------------+-----------------------------+
|         Backend Melarosa API (Laravel 13 - apps/api)       |
|                                                           |
|  - GeoportalCswClientService (XML Parser & Harvester)     |
|  - Console Command: php artisan geoportal:sync-catalog    |
|  - Task Scheduler: Daily at 02:00 WIB                     |
|  - Controller: Admin/GeoportalLayerController             |
|  - WMS Proxy Controller (CORS & Auth Bypass jika perlu)    |
|  - Database: PostgreSQL (Tabel `layers` & `sync_logs`)    |
+-----------------------------+-----------------------------+
                              |
                              | 2. REST API (/api/layers/geoportal)
                              v
+-----------------------------------------------------------+
|          Frontend Melarosa WebGIS (Nuxt 4 - apps/web)      |
|                                                           |
|  - Admin UI: Tombol Sinkronisasi Manual di /admin/layers  |
|  - Layer Panel: Tab "Geoportal Bojonegoro" + Search +     |
|    Accordion Kategori/OPD                                 |
|  - MapCanvas: OpenLayers TileLayer(TileWMS)               |
|  - Widget Legenda: Dynamic WMS GetLegendGraphic           |
|  - Feature Info: Click -> WMS GetFeatureInfo -> Popup     |
+-----------------------------------------------------------+
```

### 2.2 Spesifikasi Antarmuka OGC CSW 2.0.2 Geoportal
- **Base Endpoint CSW:** `https://geoportal.bojonegorokab.go.id/csw/`
- **Metode Permintaan:** HTTP POST / GET dengan parameter standar OGC CSW 2.0.2:
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <csw:GetRecords
      xmlns:csw="http://www.opengis.net/cat/csw/2.0.2"
      xmlns:ogc="http://www.opengis.net/ogc"
      service="CSW"
      version="2.0.2"
      resultType="results"
      outputSchema="http://www.opengis.net/cat/csw/2.0.2"
      maxRecords="100"
      startPosition="1">
    <csw:Query typeNames="csw:Record">
      <csw:ElementSetName>full</csw:ElementSetName>
    </csw:Query>
  </csw:GetRecords>
  ```
- **Pemetaan Atribut Metadata (Dublin Core / ISO 19139):**
  - `dc:identifier` -> `geoportal_id` (ID unik katalog di Geoportal).
  - `dc:title` -> `name` (Judul Layer).
  - `dc:subject` -> `category` (Tema / Urusan / Bidang).
  - `dc:description` / `dct:abstract` -> `description` (Deskripsi / Abstrak peta).
  - `dc:publisher` / `dc:creator` -> `attribution` & `opd_name` (Wali Data / OPD pemilik).
  - `dc:URI` / `dct:references` (dengan protokol `OGC:WMS`) -> `url` dan `layer_name` (Endpoint WMS & Nama Layer di GeoServer Geoportal).

---

## 3. Desain Basis Data & Skema Model (`apps/api`)

### 3.1 Migration: Penyesuaian Tabel `layers`
Tabel `layers` saat ini sudah memiliki kolom dasar (`name`, `protocol`, `url`, `layer_name`, `is_active`, `default_visible`, `opacity`, `order`, `attribution`, `description`, `source_type`, `is_synced`). Diperlukan penambahan field spesifik Geoportal untuk metadata kaya:

```php
// File: apps/api/database/migrations/2026_10_22_010000_add_geoportal_fields_to_layers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->string('geoportal_id', 100)->nullable()->unique()->after('id')
                ->comment('ID Record bawaan dari Geoportal Bojonegoro CSW');
            $table->string('category', 150)->nullable()->index()->after('name')
                ->comment('Kategori / Tema / Urusan peta Geoportal');
            $table->string('opd_name', 200)->nullable()->index()->after('category')
                ->comment('Nama OPD / Wali Data dari Geoportal');
            $table->text('legend_url')->nullable()->after('url')
                ->comment('URL WMS GetLegendGraphic Geoportal');
            $table->jsonb('csw_metadata')->nullable()->after('description')
                ->comment('Penyimpanan payload XML/JSON mentah dari CSW');
            $table->timestamp('last_synced_at')->nullable()->after('is_synced')
                ->comment('Waktu terakhir sinkronisasi dari CSW');
        });
    }

    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropColumn([
                'geoportal_id',
                'category',
                'opd_name',
                'legend_url',
                'csw_metadata',
                'last_synced_at',
            ]);
        });
    }
};
```

### 3.2 Migration: Tabel Log Sinkronisasi Geoportal
Guna mengaudit riwayat sinkronisasi (kapan dieksekusi, jumlah layer yang berhasil ditarik, dan pesan error jika koneksi CSW gagal):

```php
// File: apps/api/database/migrations/2026_10_22_010001_create_geoportal_sync_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('geoportal_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('triggered_by', 50)->default('scheduler')
                ->comment('scheduler, manual_admin, cli');
            $table->string('status', 30)->default('running')
                ->comment('running, success, failed');
            $table->integer('records_found')->default(0);
            $table->integer('records_inserted')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->float('duration_seconds')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoportal_sync_logs');
    }
};
```

---

## 4. Implementasi Backend (`apps/api`)

### 4.1 Service: `GeoportalCswClientService`
Bertanggung jawab melakukan koneksi HTTP ke CSW Geoportal Bojonegoro, mem-parsing response XML Dublin Core, dan menyimpannya secara idempoten ke tabel `layers`:

- **Path:** `apps/api/app/Services/Gis/GeoportalCswClientService.php`
- **Fitur Utama:**
  1. Pagination traversal `GetRecords` (menggunakan `startPosition` dan `maxRecords`).
  2. Ekstraksi URI WMS / WFS dari tag `<dc:URI>` atau `<dct:references>`.
  3. Pembuatan URL Legenda otomatis: `<wms_url>?SERVICE=WMS&REQUEST=GetLegendGraphic&FORMAT=image/png&LAYER=<layer_name>`.
  4. Penyimpanan idempoten: jika `geoportal_id` sudah ada, perbarui atribut; jika belum ada, masukkan sebagai record baru dengan `source_type = 'geoportal'`.
  5. Timeout & Retry handling (3 kali retry dengan timeout 15 detik).

### 4.2 Artisan Command: `php artisan geoportal:sync-catalog`
- **Path:** `apps/api/app/Console/Commands/SyncGeoportalCatalogCommand.php`
- **Penjadwalan Otomatis:**
  Daftarkan pada `apps/api/routes/console.php`:
  ```php
  Schedule::command('geoportal:sync-catalog')
      ->dailyAt('02:00')
      ->runInBackground()
      ->appendOutputTo(storage_path('logs/geoportal-sync.log'));
  ```

### 4.3 API Endpoints
Daftarkan pada `apps/api/routes/api.php` dan `apps/api/routes/web.php`:

| Method | Endpoint | Middleware / Permission | Deskripsi |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/admin/layers/geoportal/sync` | `auth:sanctum`, `permission:layers-manage` | Trigger sinkronisasi katalog CSW secara manual |
| `GET` | `/api/admin/layers/geoportal/sync-history` | `auth:sanctum`, `permission:layers-view` | Melihat riwayat eksekusi sinkronisasi |
| `GET` | `/api/layers/geoportal` | *Public / Authenticated* | Mengambil daftar layer Geoportal yang aktif dikelompokkan per kategori |
| `GET` | `/api/layers/geoportal/{id}/metadata` | *Public / Authenticated* | Detail informasi metadata lengkap suatu layer |
| `GET` | `/api/layers/geoportal/proxy-feature-info` | *Public / Authenticated* | CORS Reverse Proxy untuk permintaan `GetFeatureInfo` ke Geoserver |

---

## 5. Implementasi Frontend WebGIS (`apps/web`)

### 5.1 Admin Layer Management (`/admin/layers`)
Pada halaman [apps/web/app/pages/admin/layers/index.vue](file:///c:/laragon/www/melarosa-laravel-nuxt/apps/web/app/pages/admin/layers/index.vue):
1. **Filter Tab Sumber Layer:**
   - Filter segmented/tabs: `"Semua"`, `"Internal Melarosa"`, `"Geoportal Bojonegoro"`.
2. **Tombol "Sinkronisasi Katalog Geoportal":**
   - Ditempatkan di header tabel di samping tombol tambah layer manual.
   - Dilengkapi modal dialog konfirmasi yang menampilkan tanggal sinkronisasi terakhir.
   - Menampilkan animasi loading state, total layer yang berhasil disinkronkan, serta toast notifikasi berhasil/gagal.
3. **Badge Indikator Sumber:**
   - Layer dari Geoportal memiliki badge identitas khusus (misal warna `info` / `sky` dengan icon `i-lucide-globe-2`).

### 5.2 Layer Panel di WebGIS Map Canvas
Pada panel layer peta (misal di halaman `/admin/infrastruktur-segmen`, `/admin/dataset/jalan-poros-desa`, maupun peta publik):

1. **Section / Tab Navigasi Khusus:**
   - Tab atau accordion terpisah: **"Layer Geoportal Bojonegoro"**.
2. **Pencarian Cepat (Quick Search):**
   - Kolom pencarian di bagian atas panel untuk menyaring layer Geoportal berdasarkan kata kunci nama atau deskripsi.
3. **Pengelompokan Berdasarkan Kategori / Urusan:**
   - Layer dikelompokkan dalam *collapsible accordion* (contoh: *Batas Wilayah*, *Pekerjaan Umum & Tata Ruang*, *Pertanian*, *Lingkungan Hidup*).
4. **Kontrol Layer & Visualisasi Legenda:**
   - Switch toggle / checkbox aktif untuk masing-masing layer.
   - Ketika aktif:
     - Ditampilkan slider transparansi (*opacity*) 0% - 100%.
     - Ditampilkan gambar **Legenda Resmi** di bawah nama layer menggunakan `legend_url` (`GetLegendGraphic`).
5. **Tombol "Info Metadata":**
   - Tombol icon `i-lucide-info` di sebelah nama layer.
   - Jika diklik, memunculkan modal/drawer berisi: Judul Lengkap, Abstrak/Deskripsi, Wali Data / OPD, Format Layanan, dan Tanggal Sinkronisasi.

### 5.3 Integrasi OpenLayers 10 (`MapCanvas.vue`)
1. **Dynamic Layer Instantiation:**
   - Saat layer Geoportal diaktifkan, buat instance `TileLayer` dengan source `TileWMS`:
     ```typescript
     import TileLayer from 'ol/layer/Tile';
     import TileWMS from 'ol/source/TileWMS';

     const wmsLayer = new TileLayer({
       properties: { id: layerItem.id, source_type: 'geoportal' },
       opacity: layerItem.opacity ?? 0.85,
       visible: true,
       source: new TileWMS({
         url: layerItem.url,
         params: {
           'LAYERS': layerItem.layer_name,
           'TILED': true,
           'VERSION': '1.1.1',
         },
         serverType: 'geoserver',
         transition: 0,
       }),
     });
     ```
2. **Fitur Identifikasi Klik Objek Peta (`GetFeatureInfo`):**
   - Saat peta diklik (`map.on('click')`) dan ada layer Geoportal yang aktif:
     - Hitung URL `GetFeatureInfo`:
       ```typescript
       const viewResolution = view.getResolution();
       const url = wmsSource.getFeatureInfoUrl(
         evt.coordinate,
         viewResolution,
         'EPSG:3857',
         { 'INFO_FORMAT': 'application/json' }
       );
       ```
     - Lakukan `fetch` (melalui proxy backend Melarosa untuk mencegah kendala CORS).
     - Tampilkan atribut fitur di dalam popup callout overlay OpenLayers.

---

## 6. Rencana Tahapan Eksekusi (Actionable Checklist)

### Tahap 1: Backend Database & Client CSW (Hari 1)
- [ ] Buat file migration `add_geoportal_fields_to_layers_table.php` dan `create_geoportal_sync_logs_table.php`.
- [ ] Jalankan `php artisan migrate`.
- [ ] Perbarui model `apps/api/app/Models/Layer.php` dengan `$fillable`, `$casts`, dan scope `scopeGeoportal()`.
- [ ] Buat model `apps/api/app/Models/GeoportalSyncLog.php`.
- [ ] Buat service class `apps/api/app/Services/Gis/GeoportalCswClientService.php` dengan XML parsing `GetRecords`.

### Tahap 2: Scheduler, Proxy & API Endpoints (Hari 2)
- [ ] Buat command `apps/api/app/Console/Commands/SyncGeoportalCatalogCommand.php`.
- [ ] Daftarkan jadwal harian di `apps/api/routes/console.php`.
- [ ] Buat controller `apps/api/app/Http/Controllers/Admin/GeoportalLayerController.php` untuk manual sync & log riwayat.
- [ ] Buat controller endpoint CORS proxy `apps/api/app/Http/Controllers/Gis/WmsProxyController.php` untuk `GetFeatureInfo`.
- [ ] Tulis unit & feature test di `apps/api/tests/Feature/GeoportalIntegrationTest.php`.

### Tahap 3: Admin UI Manajemen & Sinkronisasi (Hari 3)
- [ ] Perbarui antarmuka `apps/web/app/pages/admin/layers/index.vue`:
  - Tambahkan tombol "Sinkronisasi Katalog Geoportal" dengan modal konfirmasi dan status loading.
  - Tambahkan filter tab sumber layer (Semua / Internal / Geoportal).
  - Tampilkan metadata OPD dan kategori pada tabel layer.
- [ ] Uji alur sinkronisasi manual dan verifikasi data tersimpan di tabel admin.

### Tahap 4: WebGIS Layer Panel & Legenda (Hari 4)
- [ ] Kembangkan composable `useGeoportalLayers.ts` di frontend.
- [ ] Buat komponen `GeoportalLayerSection.vue` untuk daftar layer di panel peta.
- [ ] Implementasikan input pencarian cepat (search bar) dengan debounce.
- [ ] Implementasikan pengelompokan *accordion* berdasarkan kategori / OPD.
- [ ] Tambahkan kontrol opacity slider dan penampil gambar legenda dinamis.
- [ ] Buat modal dialog detail metadata (`GeoportalMetadataDialog.vue`).

### Tahap 5: Visualisasi OpenLayers & GetFeatureInfo (Hari 5)
- [ ] Integrasikan pembuatan instance layer WMS pada `MapCanvas.vue`.
- [ ] Hubungkan kontrol visibilitas dan reaktifitas transparansi layer.
- [ ] Pasang listener `map.on('click')` untuk membaca `GetFeatureInfo` dari layer Geoportal yang aktif.
- [ ] Tampilkan hasil query atribut objek dalam kartu popup callout OpenLayers.
- [ ] Tangani error fallback jika server Geoportal lambat / tidak merespons.

---

## 7. Kriteria Penerimaan (Acceptance Criteria)

1. **Sinkronisasi Katalog Otomatis & Manual:**
   - Tombol *"Sinkronisasi Katalog Geoportal"* di admin berhasil memicu pemanenan metadata dari endpoint CSW tanpa error 500.
   - Sinkronisasi bersifat idempoten (tidak menghasilkan duplikasi layer saat dieksekusi berulang kali).
   - Scheduler cron berjalan sesuai jadwal tanpa intervensi manual.
2. **Organisasi & Penelusuran Layer:**
   - Pengguna WebGIS dapat melihat daftar layer Geoportal yang terkelompok rapi sesuai kategori/urusan.
   - Fitur pencarian menyaring nama layer secara instan dan responsif.
3. **Visualisasi Peta & Legenda:**
   - Layer Geoportal yang dicentang langsung ter-render di atas kanvas OpenLayers Melarosa dengan posisi koordinat spasial yang presisi (EPSG:4326 / EPSG:3857).
   - Simbol legenda resmi Geoportal tampil otomatis di bawah nama layer yang aktif.
   - Pengaturan transparansi (*opacity slider*) berfungsi secara real-time pada peta.
4. **Inspeksi Fitur & Metadata:**
   - Mengklik objek peta pada layer Geoportal yang aktif menampilkan popup berisi atribut data resmi dari Geoportal.
   - Tombol info metadata membuka rincian deskripsi, kategori, dan OPD wali data pemilik peta.

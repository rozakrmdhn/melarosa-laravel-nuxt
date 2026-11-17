# DEVELOPMENT PROMPT: GEOSPATIAL ISOMETRIC PIXEL TILE ENGINE

Buat sistem generator peta pixel-art isometrik berbasis data GIS aktual untuk Kecamatan Bojonegoro, Kabupaten Bojonegoro, Jawa Timur.

Tujuan utama adalah menghasilkan peta isometrik pixel-art yang memiliki referensi koordinat geografis dan dapat ditampilkan menggunakan sistem tiled map pada aplikasi Vue.js 3.

## 1. Architecture

Pisahkan sistem menjadi dua bagian:

A. Offline Isometric Tile Generator
- Python
- GeoPandas
- Shapely
- Pillow atau Skia
- PostgreSQL/PostGIS
- Custom Isometric Projection Engine
- Custom Raster Tile Generator

B. Online Tile Viewer
- Vue.js 3
- Composition API
- TypeScript
- Canvas atau PixiJS
- Custom Isometric Tile Loader
- Geographic Coordinate Transformation

## 2. Geographic Data

Area of Interest:
Kecamatan Bojonegoro, Kabupaten Bojonegoro, Jawa Timur.

Gunakan data aktual:
- Administrative boundaries
- Building footprints
- Road network
- River network
- Parks and vegetation
- Water bodies
- Infrastructure data dari PostGIS MELAROSA

Gunakan EPSG:32749 untuk proses koordinat lokal dalam meter.

## 3. Isometric Projection Engine

Buat sistem konversi:
Geographic Coordinates → Projected Coordinates → Isometric Coordinates → Pixel Coordinates.

Implementasikan fungsi:
- geographicToProjected()
- projectedToIsometric()
- isometricToPixel()
- pixelToIsometric()
- isometricToProjected()
- pixelToGeographic()

Pastikan transformasi dua arah konsisten.

Jangan menggunakan longitude dan latitude secara langsung dalam formula grid isometrik.

Simpan parameter:
- Origin coordinate
- Scale factor
- Isometric tile width
- Isometric tile height
- Rotation angle
- Pixel resolution
- Bounding extent

## 4. Pixel-Art Renderer

Buat renderer yang menggambarkan:
- Bangunan sebagai objek pixel-art dengan kedalaman 3D.
- Jalan mengikuti geometri jaringan jalan aktual.
- Sungai Bengawan Solo dengan tekstur pixel-art biru.
- Area hijau dan vegetasi.
- Taman dan ruang terbuka.
- Blok permukiman perkotaan.

Terapkan:
- Limited color palette.
- Nearest-neighbor pixel rendering.
- Consistent pixel scale.
- Isometric lighting.
- Building shadows.
- Layer-based rendering.
- Deterministic geometry rendering.

Hindari menghasilkan objek geografis secara acak.

## 5. Tile Generation

Buat custom tile generator.

Spesifikasi:
- Tile size: 256 × 256 pixel.
- Format: PNG atau WebP.
- Multiple zoom levels.
- Overlap atau metatile rendering untuk mencegah garis sambungan dan objek terpotong.
- Tile indexing konsisten dengan isometric coordinate system.
- Support transparent background jika diperlukan.

Jangan menganggap skema XYZ Web Mercator otomatis berlaku pada proyeksi isometrik.

Buat metadata.json yang memuat:
- Projection definition.
- Geographic extent.
- Isometric extent.
- Tile size.
- Zoom levels.
- Scale per zoom.
- Coordinate transformation parameters.
- Tile indexing scheme.

## 6. Tile Viewer Vue.js

Buat komponen:
- IsometricPixelMap.vue
- composables/useIsometricTiles.ts
- composables/useGeographicTransform.ts
- composables/useTileLoader.ts
- services/isometricTileService.ts
- types/isometric.ts

Fitur:
- Pan dan zoom.
- Lazy tile loading.
- Viewport-based tile requests.
- Tile caching.
- Smooth movement.
- Coordinate inspection.
- Optional geographic marker overlay.
- Responsive rendering.

Pastikan klik pada peta dapat mengembalikan koordinat geografis longitude dan latitude.

## 7. Spatial Accuracy

Ini adalah persyaratan utama.

Setiap objek visual harus memiliki referensi ke objek geografis asal.

Contoh:
- Bangunan menyimpan building_id dan geometri asal.
- Jalan menyimpan road_id dan geometri asal.
- Sungai menyimpan river_id dan geometri asal.

Pastikan transformasi koordinat pixel ke geografis memiliki tingkat kesalahan yang dapat diukur.

Untuk objek yang dirender dengan ekstrusi 3D, gunakan koordinat dasar objek (ground anchor) sebagai referensi geografis, bukan posisi bagian atas bangunan.

## 8. Performance

- Generate tiles secara offline.
- Jangan melakukan rendering seluruh wilayah pada browser.
- Gunakan viewport-based tile loading.
- Cache tiles.
- Gunakan zoom-dependent level of detail.
- Gunakan metatile agar garis bangunan dan jalan tidak terputus pada batas tile.
- Optimalkan ukuran file.

## 9. Expected Output

Hasil akhir:
Peta pixel-art isometrik Kecamatan Bojonegoro dengan tampilan seperti miniature pixel city map.

Peta harus:
- Mengikuti jaringan jalan aktual.
- Mengikuti posisi bangunan sebenarnya.
- Menampilkan Sungai Bengawan Solo.
- Memiliki koordinat geografis.
- Mendukung pan dan zoom.
- Mendukung konversi pixel ke longitude dan latitude.
- Menggunakan tile-based rendering.

Sebelum implementasi, buat desain sistem koordinat, contoh master image, metadata tile, dan pengujian transformasi koordinat.

Jangan langsung membangun viewer sebelum generator dan sistem koordinat berhasil diuji.
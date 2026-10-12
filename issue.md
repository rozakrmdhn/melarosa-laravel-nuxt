# Issue: Perencanaan Fitur Edit Geometry, PATCH Endpoint, dan Split Geometry Infrastruktur Segmen

- **Modul**: Infrastruktur Segmen Spasial (WebGIS)
- **Target Halaman**: `/admin/infrastruktur-segmen`
- **Target Audiens Implementor**: Junior Programmer atau AI Model
- **Status**: Ready for Implementation
- **Prioritas**: High

---

## 1. Ringkasan & Tujuan (Overview)

Dokumen ini adalah panduan spesifikasi dan petunjuk kerja teknis lengkap untuk mengimplementasikan 3 fitur utama pada modul **Infrastruktur Segmen** di aplikasi Melarosa GIS:
1. **Fitur Edit Geometry di Frontend**: Memungkinkan operator memilih segmen garis jalan/infrastruktur yang sudah ada, mengaktifkan mode edit vertex di peta OpenLayers, menggeser/menambah/menghapus titik koordinat, melihat pembaruan panjang geodesik secara real-time, dan menyimpannya.
2. **Endpoint Backend PATCH Edit Geometry**: Endpoint khusus yang aman dan terisolasi (`PATCH /admin/infrastruktur-segmen/{id}/geometry`) untuk memperbarui data koordinat PostGIS, menghitung ulang panjang meter spasial secara otomatis, mencatat log audit, dan melakukan invalidasi cache Martin Tile Server.
3. **Fitur Split Geometry (Frontend & Backend)**: Memungkinkan pemotongan ruas/segmen garis menjadi 2 bagian terpisah baik melalui klik titik potong di badan jalan (*point-based split* dengan PostGIS `ST_LineSubstring`) maupun koordinat pre-split, memperbarui segmen pertama dan membuat record baru untuk segmen kedua lengkap dengan pewarisan atribut serta relasi `parent_id`.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)

- [ ] **AC-1 (Edit Geometry di Peta)**:
  - Pada panel detail (`RightPanel.vue`), terdapat tombol **"Edit Geometri"** jika user memiliki hak akses edit.
  - Saat tombol diklik, peta masuk ke mode edit: garis segmen target dimuat ke canvas dengan titik vertex yang dapat digeser (drag), ditambah (klik garis), dan dihapus (Alt+klik vertex) menggunakan interaksi `ol/interaction/Modify`.
  - Terdapat bilah aksi mengambang (floating bar) di atas peta yang menampilkan status edit, panjang meter terbaru secara real-time, tombol **"Batal"**, dan tombol **"Simpan Geometri"**.
  - Menyimpan geometri akan memanggil endpoint `PATCH /admin/infrastruktur-segmen/{id}/geometry`.
- [ ] **AC-2 (Endpoint PATCH Geometry)**:
  - Route `PATCH /admin/infrastruktur-segmen/{id}/geometry` terdaftar dan terlindungi middleware autentikasi dan policy.
  - Menerima payload GeoJSON (`LineString` atau `MultiLineString`) dengan validasi ketat (minimal 2 pasang titik koordinat valid WGS84).
  - Menghitung ulang kolom `panjang` menggunakan PostGIS `ST_Length(geom::geography)`.
  - Melakukan purge cache Martin MVT tile (`MartinTileService::purgeCache('infrastruktur_segmen')`).
  - Mencatat event `edit_geometry` ke `audit_logs`.
- [ ] **AC-3 (Split Geometry Backend)**:
  - Route `POST /admin/infrastruktur-segmen/{id}/split` terdaftar dan terlindungi policy.
  - Menerima koordinat titik potong `point: [lon, lat]` atau objek `parts: [ { namobj, geometry }, ... ]`.
  - Memotong geometri menggunakan PostGIS (`ST_LineLocatePoint`, `ST_ClosestPoint`, `ST_LineSubstring`).
  - Menolak pemotongan jika titik terlalu dekat dengan ujung ruas ($< 0.5\%$ atau $> 99.5\%$).
  - Memperbarui segmen awal (Part 1) dan membuat entitas segmen baru (Part 2) di dalam satu transaksi database (`DB::transaction`).
  - Menghubungkan relasi: `parent_id` segmen baru mengacu ke segmen asal atau struktur hierarki segmen yang konsisten.
- [ ] **AC-4 (Split Geometry Frontend)**:
  - Pada panel detail (`RightPanel.vue`), terdapat tombol **"Split Segmen"**.
  - Saat diklik, peta masuk ke mode pemotongan: snap aktif pada badan segmen terpilih, kursor berubah menjadi pointer bidik.
  - Saat operator mengklik suatu titik di badan garis segmen, muncul marker visual dan dialog konfirmasi pemotongan (`DialogSplitSegmen.vue` / modal dialog).
  - Dialog menampilkan estimasi/informasi segmen, kolom input nama Part 1 dan nama Part 2 (default: `"{nama} (Bagian 1)"` dan `"{nama} (Bagian 2)"`).
  - Setelah split berhasil dikonfirmasi, MVT layer peta di-refresh, daftar tabel terbarui, dan kedua segmen baru dapat diinspeksi.

---

## 3. Arsitektur File & Komponen

Berikut adalah daftar file yang harus dibuat atau dimodifikasi:

```
melarosa-laravel-nuxt/
├── apps/api/                                      # BACKEND (Laravel 11 + PostGIS)
│   ├── routes/web.php                            # [EDIT] Registrasi route PATCH geometry & POST split
│   ├── app/Http/Requests/Admin/
│   │   ├── UpdateInfrastrukturSegmenGeometryRequest.php # [BUAT] Validasi payload GeoJSON geometry
│   │   └── SplitInfrastrukturSegmenRequest.php   # [BUAT] Validasi point/parts split
│   ├── app/Http/Controllers/Admin/
│   │   └── InfrastrukturSegmenController.php     # [EDIT] Tambah method updateGeometry() & split()
│   └── app/Policies/
│       └── InfrastrukturSegmenPolicy.php         # [EDIT] Tambah otorisasi split()
│
└── apps/web/                                      # FRONTEND (Nuxt 3 + OpenLayers)
    ├── app/types/infrastruktur.ts                # [EDIT] Tambah payload interface split & edit geometry
    ├── app/composables/useInfrastrukturApi.ts    # [EDIT] Tambah fungsi patchGeometry & splitSegmen
    ├── app/components/dataset/infrastruktur-segmen/
    │   ├── MapCanvas.vue                         # [EDIT] Mode Modify geometry & mode singleclick split
    │   ├── RightPanel.vue                        # [EDIT] Tombol "Edit Geometri" & "Split Segmen"
    │   └── DialogSplitSegmen.vue                 # [BUAT/EDIT] Modal dialog konfirmasi nama bagian split
    └── app/pages/admin/infrastruktur-segmen/
        └── index.vue                             # [EDIT] State handler penggabungan map, api, dan dialog
```

---

## 4. Rincian Teknis Backend (Laravel + PostGIS)

### 4.1. Registrasi Route (`apps/api/routes/web.php`)

Tambahkan dua endpoint baru di dalam grup `Route::prefix('admin')` pada blok `// Infrastruktur Segmen (Spasial)`:

```php
// File: apps/api/routes/web.php (sekitar baris 139)

Route::patch('infrastruktur-segmen/{id}/geometry', [InfrastrukturSegmenController::class, 'updateGeometry'])
    ->middleware('permission:infrastruktur-segmen-update')
    ->name('admin.infrastruktur-segmen.update-geometry');

Route::post('infrastruktur-segmen/{id}/split', [InfrastrukturSegmenController::class, 'split'])
    ->middleware('permission:infrastruktur-segmen-update')
    ->name('admin.infrastruktur-segmen.split');
```

---

### 4.2. Form Request: `UpdateInfrastrukturSegmenGeometryRequest.php`

Buat file baru: `apps/api/app/Http/Requests/Admin/UpdateInfrastrukturSegmenGeometryRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInfrastrukturSegmenGeometryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'geometry' => ['required'], // Array GeoJSON atau GeoJSON string
        ];
    }

    public function messages(): array
    {
        return [
            'geometry.required' => 'Data koordinat geometri wajib disertakan.',
        ];
    }
}
```

---

### 4.3. Form Request: `SplitInfrastrukturSegmenRequest.php`

Buat file baru: `apps/api/app/Http/Requests/Admin/SplitInfrastrukturSegmenRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SplitInfrastrukturSegmenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'point'             => ['nullable', 'array', 'size:2'],
            'point.0'           => ['required_with:point', 'numeric', 'between:111.0,113.0'], // Longitude WGS84 Bojonegoro & sekitarnya
            'point.1'           => ['required_with:point', 'numeric', 'between:-8.5,-6.0'],   // Latitude WGS84
            'part1'             => ['nullable', 'array'],
            'part1.namobj'      => ['nullable', 'string', 'max:255'],
            'part2'             => ['nullable', 'array'],
            'part2.namobj'      => ['nullable', 'string', 'max:255'],

            // Opsi parts (pre-computed geometry jika dikirim langsung oleh klien)
            'parts'             => ['nullable', 'array', 'size:2'],
            'parts.*.namobj'    => ['required_with:parts', 'string', 'max:255'],
            'parts.*.geometry'  => ['required_with:parts'],
        ];
    }
}
```

---

### 4.4. Pembaruan Policy (`apps/api/app/Policies/InfrastrukturSegmenPolicy.php`)

Pastikan otorisasi untuk `split` terdefinisi di policy. Karena split mengubah data eksisting dan membuat data baru:

```php
// File: apps/api/app/Policies/InfrastrukturSegmenPolicy.php

public function split(User $user, InfrastrukturSegmen $segmen): bool
{
    // Memerlukan hak akses update dan create
    if (!$this->update($user, $segmen)) {
        return false;
    }

    return $this->create($user);
}
```

---

### 4.5. Implementasi Controller (`InfrastrukturSegmenController.php`)

Buka `apps/api/app/Http/Controllers/Admin/InfrastrukturSegmenController.php` dan tambahkan dua fungsi:

#### 1) Method `updateGeometry`:
```php
public function updateGeometry(UpdateInfrastrukturSegmenGeometryRequest $request, string $id): JsonResponse
{
    $segmen = InfrastrukturSegmen::where('id', $id)->first();
    if (!$segmen) {
        return response()->json([
            'ok'      => false,
            'message' => 'Segmen infrastruktur tidak ditemukan.',
        ], 404);
    }

    Gate::authorize('update', $segmen);

    $geoJsonInput = $request->input('geometry');
    $geoJsonString = is_string($geoJsonInput) ? $geoJsonInput : json_encode($geoJsonInput);

    $parsed = json_decode($geoJsonString, true);
    if (!isset($parsed['type']) || !in_array($parsed['type'], ['LineString', 'MultiLineString'], true)) {
        return response()->json([
            'ok'      => false,
            'message' => 'Tipe geometri harus berupa LineString atau MultiLineString.',
        ], 422);
    }

    if (empty($parsed['coordinates']) || !is_array($parsed['coordinates']) || count($parsed['coordinates']) < 2) {
        return response()->json([
            'ok'      => false,
            'message' => 'Geometri harus memiliki minimal 2 titik koordinat.',
        ], 422);
    }

    DB::transaction(function () use ($segmen, $geoJsonString) {
        $pdo = DB::getPdo();
        $quotedGeo = $pdo->quote($geoJsonString);

        // Update PostGIS geometry & otomatis hitung panjang dari ST_Length(geography)
        $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo}), 4326)");
        $segmen->panjang = DB::raw("ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo}), 4326)::geography)::numeric, 2)");
        $segmen->save();

        // Audit log
        app(\App\Services\AuditLogService::class)->log(
            event: 'update_geometry',
            module: 'infrastruktur-segmen',
            auditable: $segmen,
            description: "Memperbarui koordinat geometri spasial segmen \"{$segmen->namobj}\"",
            meta: [
                'segmen_id' => $segmen->id,
            ]
        );
    });

    $fresh = InfrastrukturSegmen::withGeoJson()->where('id', $segmen->id)->first();

    return response()->json([
        'ok'      => true,
        'message' => 'Geometri segmen berhasil diperbarui.',
        'data'    => $fresh,
    ]);
}
```

#### 2) Method `split`:
```php
public function split(SplitInfrastrukturSegmenRequest $request, string $id): JsonResponse
{
    $segmen = InfrastrukturSegmen::where('id', $id)->first();
    if (!$segmen) {
        return response()->json([
            'ok'      => false,
            'message' => 'Segmen infrastruktur tidak ditemukan.',
        ], 404);
    }

    Gate::authorize('split', $segmen);

    // Cek apakah segmen sudah terikat Berita Acara yang terkunci
    if (DB::table('monitoring_realisasi_items')->where('id_segmen', $id)->exists()) {
        return response()->json([
            'ok'      => false,
            'message' => 'Segmen ini sudah terikat pada Berita Acara realisasi sehingga tidak dapat di-split.',
        ], 422);
    }

    $createdFeatures = [];
    $user = $request->user();

    DB::transaction(function () use ($segmen, $request, $user, &$createdFeatures) {
        if ($request->filled('parts')) {
            // MODE A: Frontend mengirimkan 2 bagian geometri hasil kalkulasi client
            $p1 = $request->input('parts.0');
            $p2 = $request->input('parts.1');

            $geo1 = is_string($p1['geometry']) ? $p1['geometry'] : json_encode($p1['geometry']);
            $geo2 = is_string($p2['geometry']) ? $p2['geometry'] : json_encode($p2['geometry']);

            $name1 = $p1['namobj'] ?? ($segmen->namobj . ' (Bagian 1)');
            $name2 = $p2['namobj'] ?? ($segmen->namobj . ' (Bagian 2)');

            $pdo = DB::getPdo();
            $quotedGeo1 = $pdo->quote($geo1);
            $quotedGeo2 = $pdo->quote($geo2);

            // 1. Update segmen asli menjadi Bagian 1
            $segmen->namobj = $name1;
            $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo1}), 4326)");
            $segmen->panjang = DB::raw("ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo1}), 4326)::geography)::numeric, 2)");
            $segmen->status_parent = true;
            $segmen->save();

            // 2. Insert segmen baru sebagai Bagian 2
            $newSegmen = new InfrastrukturSegmen();
            $newSegmen->id = (string) Str::uuid();
            $newSegmen->parent_id = $segmen->id;
            $newSegmen->tipe_kode = $segmen->tipe_kode;
            $newSegmen->namobj = $name2;
            $newSegmen->lebar = $segmen->lebar;
            $newSegmen->jenis_perkerasan = $segmen->jenis_perkerasan;
            $newSegmen->status_jalan = $segmen->status_jalan;
            $newSegmen->kondisi = $segmen->kondisi;
            $newSegmen->status_kondisi = $segmen->status_kondisi;
            $newSegmen->tahun_pembangunan = $segmen->tahun_pembangunan;
            $newSegmen->sumber_dana = $segmen->sumber_dana;
            $newSegmen->keterangan = $segmen->keterangan;
            $newSegmen->status_aset = $segmen->status_aset;
            $newSegmen->sumber_data = $segmen->sumber_data;
            $newSegmen->desa = $segmen->desa;
            $newSegmen->kecamatan = $segmen->kecamatan;
            $newSegmen->id_desa = $segmen->id_desa;
            $newSegmen->id_kecamatan = $segmen->id_kecamatan;
            $newSegmen->plotting_id = $segmen->plotting_id;
            $newSegmen->status_verifikasi = 'draft';
            $newSegmen->user_id = $user->uuid;
            $newSegmen->created_by = $user->uuid;
            $newSegmen->created_by_role = $user->roles->first()?->name ?? 'user';
            $newSegmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo2}), 4326)");
            $newSegmen->panjang = DB::raw("ROUND(ST_Length(ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo2}), 4326)::geography)::numeric, 2)");
            $newSegmen->save();
        } else {
            // MODE B: Split otomatis menggunakan titik potong PostGIS ST_LineSubstring
            $point = $request->input('point');
            if (!$point || count($point) < 2) {
                abort(422, 'Titik koordinat pemotongan (point) wajib disertakan [lon, lat].');
            }
            $lon = (float) $point[0];
            $lat = (float) $point[1];

            $splitQuery = DB::selectOne("
                SELECT 
                    fraction,
                    ST_AsGeoJSON(ST_LineSubstring(line_geom, 0, fraction)) AS part1_geojson,
                    ROUND((ST_Length(ST_LineSubstring(line_geom, 0, fraction)::geography))::numeric, 2) AS part1_panjang,
                    ST_AsGeoJSON(ST_LineSubstring(line_geom, fraction, 1)) AS part2_geojson,
                    ROUND((ST_Length(ST_LineSubstring(line_geom, fraction, 1)::geography))::numeric, 2) AS part2_panjang
                FROM (
                    SELECT 
                        line_geom,
                        ST_LineLocatePoint(
                            line_geom, 
                            ST_ClosestPoint(line_geom, ST_SetSRID(ST_Point(?, ?), 4326))
                        ) AS fraction
                    FROM (
                        SELECT 
                            CASE 
                                WHEN ST_GeometryType(ST_LineMerge(geom)) = 'ST_LineString' THEN ST_LineMerge(geom)
                                ELSE ST_GeometryN(geom, 1)
                            END AS line_geom
                        FROM infrastruktur_segmen
                        WHERE id = ?
                    ) g
                ) sub
            ", [$lon, $lat, $segmen->id]);

            if (!$splitQuery || empty($splitQuery->part1_geojson) || empty($splitQuery->part2_geojson)) {
                abort(422, 'Gagal memproses pemotongan geometri garis segmen.');
            }

            $fraction = (float) ($splitQuery->fraction ?? 0);
            if ($fraction <= 0.005 || $fraction >= 0.995) {
                abort(422, 'Titik pemotongan terlalu dekat dengan ujung segmen. Silakan pilih titik di badan garis.');
            }

            $name1 = $request->input('part1.namobj') ?: ($segmen->namobj . ' (Bagian 1)');
            $name2 = $request->input('part2.namobj') ?: ($segmen->namobj . ' (Bagian 2)');

            $part1Geo = $splitQuery->part1_geojson;
            $part2Geo = $splitQuery->part2_geojson;

            $pdo = DB::getPdo();
            $quotedGeo1 = $pdo->quote($part1Geo);
            $quotedGeo2 = $pdo->quote($part2Geo);

            // 1. Update segmen asli menjadi Bagian 1
            $segmen->namobj = $name1;
            $segmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo1}), 4326)");
            $segmen->panjang = (float) $splitQuery->part1_panjang;
            $segmen->status_parent = true;
            $segmen->save();

            // 2. Insert segmen baru sebagai Bagian 2
            $newSegmen = new InfrastrukturSegmen();
            $newSegmen->id = (string) Str::uuid();
            $newSegmen->parent_id = $segmen->id;
            $newSegmen->tipe_kode = $segmen->tipe_kode;
            $newSegmen->namobj = $name2;
            $newSegmen->lebar = $segmen->lebar;
            $newSegmen->jenis_perkerasan = $segmen->jenis_perkerasan;
            $newSegmen->status_jalan = $segmen->status_jalan;
            $newSegmen->kondisi = $segmen->kondisi;
            $newSegmen->status_kondisi = $segmen->status_kondisi;
            $newSegmen->tahun_pembangunan = $segmen->tahun_pembangunan;
            $newSegmen->sumber_dana = $segmen->sumber_dana;
            $newSegmen->keterangan = $segmen->keterangan;
            $newSegmen->status_aset = $segmen->status_aset;
            $newSegmen->sumber_data = $segmen->sumber_data;
            $newSegmen->desa = $segmen->desa;
            $newSegmen->kecamatan = $segmen->kecamatan;
            $newSegmen->id_desa = $segmen->id_desa;
            $newSegmen->id_kecamatan = $segmen->id_kecamatan;
            $newSegmen->plotting_id = $segmen->plotting_id;
            $newSegmen->status_verifikasi = 'draft';
            $newSegmen->user_id = $user->uuid;
            $newSegmen->created_by = $user->uuid;
            $newSegmen->created_by_role = $user->roles->first()?->name ?? 'user';
            $newSegmen->geom = DB::raw("ST_SetSRID(ST_GeomFromGeoJSON({$quotedGeo2}), 4326)");
            $newSegmen->panjang = (float) $splitQuery->part2_panjang;
            $newSegmen->save();
        }

        $createdFeatures['part1'] = InfrastrukturSegmen::withGeoJson()->find($segmen->id);
        $createdFeatures['part2'] = InfrastrukturSegmen::withGeoJson()->find($newSegmen->id);

        app(\App\Services\AuditLogService::class)->log(
            event: 'split',
            module: 'infrastruktur-segmen',
            auditable: $segmen,
            description: "Memecah (split) segmen \"{$segmen->namobj}\" menjadi 2 bagian (\"{$newSegmen->namobj}\")",
            meta: [
                'original_id' => $segmen->id,
                'new_part_id' => $newSegmen->id,
            ]
        );
    });

    return response()->json([
        'ok'      => true,
        'message' => 'Segmen berhasil dipecah (split) menjadi 2 bagian.',
        'data'    => $createdFeatures,
    ]);
}
```

---

## 5. Rincian Teknis Frontend (Nuxt 3 + OpenLayers)

### 5.1. Pembaruan Composable API (`apps/web/app/composables/useInfrastrukturApi.ts`)

Buka `apps/web/app/composables/useInfrastrukturApi.ts` dan tambahkan method:

```ts
// File: apps/web/app/composables/useInfrastrukturApi.ts

async function patchSegmenGeometry(id: string, geometry: any) {
  return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}/geometry`, {
    method: 'PATCH',
    body: { geometry },
  });
}

async function splitSegmen(
  id: string,
  payload: {
    point?: [number, number];
    part1?: { namobj?: string };
    part2?: { namobj?: string };
    parts?: Array<{ namobj: string; geometry: any }>;
  }
) {
  return $http<ApiResponse<{ part1: InfrastrukturSegmen; part2: InfrastrukturSegmen }>>(
    `admin/infrastruktur-segmen/${id}/split`,
    {
      method: 'POST',
      body: payload,
    }
  );
}

// Jangan lupa return kedua fungsi pada return block useInfrastrukturApi:
return {
  // ...
  patchSegmenGeometry,
  splitSegmen,
};
```

---

### 5.2. Pembaruan Map Canvas (`apps/web/app/components/dataset/infrastruktur-segmen/MapCanvas.vue`)

Komponen `MapCanvas.vue` perlu mendukung 2 mode interaktif baru:

#### A. State & Emits Tambahan:
```ts
// Emits
const emit = defineEmits<{
  // ... emits yang sudah ada
  (e: 'editGeometrySaved', payload: { id: string; geojson: any; lengthMeters: number }): void;
  (e: 'editGeometryCanceled'): void;
  (e: 'splitPointSelected', payload: { segmen: InfrastrukturSegmen; coordinate: [number, number] }): void;
}>();

// State Edit Geometri
const isEditGeometryMode = ref(false);
const editingSegmenData = ref<InfrastrukturSegmen | null>(null);
const editedFeature = ref<any>(null);
const editedLengthMeters = ref(0);
let editGeometrySource: any = null;
let editGeometryLayer: any = null;
let editModifyInteraction: any = null;
let editSnapInteraction: any = null;
let editGeomListener: any = null;

// State Split Mode
const isSplitMode = ref(false);
const splitSegmenData = ref<InfrastrukturSegmen | null>(null);
const splitSelectedCoord = ref<[number, number] | null>(null);
let splitSource: any = null;
let splitLayer: any = null;
let splitSnapInteraction: any = null;
let splitClickListener: any = null;
```

#### B. Logika Edit Geometri (`startEditGeometry`, `cancelEditGeometry`, `saveEditedGeometry`):
```ts
function startEditGeometry(segmen: InfrastrukturSegmen) {
  if (!mapInstance || !olModules) return;
  cancelDrawing(false);
  cancelSplitMode();
  cancelEditGeometry();

  const rawGeom = (segmen as any).geometry || segmen.geojson || (segmen as any).properties?.geojson;
  if (!rawGeom) {
    emit('log', 'WARN', 'Data geometri segmen tidak ditemukan.');
    return;
  }

  isEditGeometryMode.value = true;
  editingSegmenData.value = segmen;

  const parsedGeom = typeof rawGeom === 'string' ? JSON.parse(rawGeom) : rawGeom;
  const format = new olModules.GeoJSON();
  const olGeom = format.readGeometry(parsedGeom, {
    dataProjection: 'EPSG:4326',
    featureProjection: 'EPSG:3857',
  });

  const feature = new olModules.Feature({ geometry: olGeom });
  editedFeature.value = feature;
  editedLengthMeters.value = Math.round(olModules.sphere.getLength(olGeom));

  editGeometrySource.addFeature(feature);

  // Zoom fit ke segmen yang sedang diedit
  mapInstance.getView().fit(olGeom.getExtent(), {
    padding: [120, 120, 120, 120],
    maxZoom: 18,
    duration: 500,
  });

  // Pasang Modify Interaction
  const { Modify, Style, Circle, Fill, Stroke, Snap } = olModules;
  editModifyInteraction = new Modify({
    source: editGeometrySource,
    style: new Style({
      image: new Circle({
        radius: 7,
        fill: new Fill({ color: '#3b82f6' }),
        stroke: new Stroke({ color: '#ffffff', width: 2.5 }),
      }),
    }),
  });

  editGeomListener = () => {
    const g = feature.getGeometry();
    if (g) {
      editedLengthMeters.value = Math.round(olModules.sphere.getLength(g));
    }
  };
  olGeom.on('change', editGeomListener);

  mapInstance.addInteraction(editModifyInteraction);

  // Snapping ke network sekitarnya
  if (snapSource) {
    editSnapInteraction = new Snap({ source: snapSource, pixelTolerance: 12 });
    mapInstance.addInteraction(editSnapInteraction);
  }

  emit('log', 'INFO', `Mode edit geometri aktif untuk: "${segmen.namobj}". Geser titik vertex.`);
}

function cancelEditGeometry() {
  if (editModifyInteraction && mapInstance) {
    mapInstance.removeInteraction(editModifyInteraction);
    editModifyInteraction = null;
  }
  if (editSnapInteraction && mapInstance) {
    mapInstance.removeInteraction(editSnapInteraction);
    editSnapInteraction = null;
  }
  if (editGeomListener && editedFeature.value) {
    editedFeature.value.getGeometry()?.un('change', editGeomListener);
    editGeomListener = null;
  }
  if (editGeometrySource) {
    editGeometrySource.clear();
  }
  isEditGeometryMode.value = false;
  editingSegmenData.value = null;
  editedFeature.value = null;
  editedLengthMeters.value = 0;
  emit('editGeometryCanceled');
}

function saveEditedGeometry() {
  if (!editedFeature.value || !editingSegmenData.value || !olModules) return;

  const geom = editedFeature.value.getGeometry();
  const format = new olModules.GeoJSON();
  const geojsonObj = format.writeGeometryObject(geom, {
    featureProjection: 'EPSG:3857',
    dataProjection: 'EPSG:4326',
  });
  const length = Math.round(olModules.sphere.getLength(geom));

  emit('editGeometrySaved', {
    id: editingSegmenData.value.id,
    geojson: geojsonObj,
    lengthMeters: length,
  });

  cancelEditGeometry();
}
```

#### C. Logika Split Mode (`startSplitMode`, `cancelSplitMode`):
```ts
function startSplitMode(segmen: InfrastrukturSegmen) {
  if (!mapInstance || !olModules) return;
  cancelDrawing(false);
  cancelEditGeometry();
  cancelSplitMode();

  const rawGeom = (segmen as any).geometry || segmen.geojson || (segmen as any).properties?.geojson;
  if (!rawGeom) {
    emit('log', 'WARN', 'Geometri segmen tidak valid untuk split.');
    return;
  }

  isSplitMode.value = true;
  splitSegmenData.value = segmen;

  const parsedGeom = typeof rawGeom === 'string' ? JSON.parse(rawGeom) : rawGeom;
  const format = new olModules.GeoJSON();
  const olGeom = format.readGeometry(parsedGeom, {
    dataProjection: 'EPSG:4326',
    featureProjection: 'EPSG:3857',
  });

  const roadFeat = new olModules.Feature({ geometry: olGeom });
  splitSource.addFeature(roadFeat);

  mapInstance.getView().fit(olGeom.getExtent(), {
    padding: [100, 100, 100, 100],
    maxZoom: 18,
    duration: 500,
  });

  const { Snap } = olModules;
  splitSnapInteraction = new Snap({
    source: splitSource,
    edge: true,
    vertex: true,
    pixelTolerance: 15,
  });
  mapInstance.addInteraction(splitSnapInteraction);

  splitClickListener = (evt: any) => {
    if (!isSplitMode.value) return;
    const rawCoord = evt.coordinate;
    const snappedCoord = olGeom?.getClosestPoint ? olGeom.getClosestPoint(rawCoord) : rawCoord;
    const [lon, lat] = olModules.toLonLat(snappedCoord);
    splitSelectedCoord.value = [lon, lat];

    // Bersihkan marker lama
    const markers = splitSource.getFeatures().filter((f: any) => f.get('isMarker'));
    markers.forEach((m: any) => splitSource.removeFeature(m));

    // Pasang marker titik potong
    const marker = new olModules.Feature({
      geometry: new olModules.Point(snappedCoord),
    });
    marker.set('isMarker', true);
    splitSource.addFeature(marker);

    emit('splitPointSelected', {
      segmen,
      coordinate: [lon, lat],
    });
  };

  mapInstance.on('singleclick', splitClickListener);
  emit('log', 'INFO', `Klik pada garis "${segmen.namobj}" di lokasi titik pemotongan.`);
}

function cancelSplitMode() {
  if (splitClickListener && mapInstance) {
    mapInstance.un('singleclick', splitClickListener);
    splitClickListener = null;
  }
  if (splitSnapInteraction && mapInstance) {
    mapInstance.removeInteraction(splitSnapInteraction);
    splitSnapInteraction = null;
  }
  if (splitSource) {
    splitSource.clear();
  }
  isSplitMode.value = false;
  splitSegmenData.value = null;
  splitSelectedCoord.value = null;
}
```

#### D. Floating Status Banner di Peta:
Tambahkan overlay banner di bagian atas peta (`MapCanvas.vue` template) saat mode edit atau split aktif:
```html
<!-- Floating Bar Mode Edit Geometri -->
<div
  v-if="isEditGeometryMode"
  class="absolute top-3 left-1/2 -translate-x-1/2 z-30 flex items-center gap-3 px-4 py-2 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md rounded-xl shadow-lg border border-blue-500/40 text-xs text-gray-800 dark:text-gray-100"
>
  <div class="flex items-center gap-1.5 font-medium text-blue-600 dark:text-blue-400">
    <UIcon name="i-lucide-pen-tool" class="size-4 animate-pulse" />
    <span>Mode Edit Geometri: <strong>{{ editingSegmenData?.namobj }}</strong></span>
  </div>
  <UBadge :label="`${editedLengthMeters} m`" color="primary" variant="subtle" size="xs" class="font-mono" />
  <div class="flex items-center gap-1.5 ml-2">
    <UButton size="xs" color="neutral" variant="ghost" label="Batal" @click="cancelEditGeometry" />
    <UButton size="xs" color="primary" icon="i-lucide-check" label="Simpan Geometri" @click="saveEditedGeometry" />
  </div>
</div>

<!-- Floating Bar Mode Split -->
<div
  v-if="isSplitMode"
  class="absolute top-3 left-1/2 -translate-x-1/2 z-30 flex items-center gap-3 px-4 py-2 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md rounded-xl shadow-lg border border-amber-500/40 text-xs text-gray-800 dark:text-gray-100"
>
  <div class="flex items-center gap-1.5 font-medium text-amber-600 dark:text-amber-400">
    <UIcon name="i-lucide-scissors" class="size-4 animate-bounce" />
    <span>Klik badan garis untuk memilih titik potong: <strong>{{ splitSegmenData?.namobj }}</strong></span>
  </div>
  <UButton size="xs" color="neutral" variant="ghost" label="Batal Split" @click="cancelSplitMode" />
</div>
```

Expose kedua method melalui `defineExpose`:
```ts
defineExpose({
  // ... expose sebelumnya
  startEditGeometry,
  cancelEditGeometry,
  startSplitMode,
  cancelSplitMode,
  isEditGeometryMode,
  isSplitMode,
});
```

---

### 5.3. Pembaruan Panel Detail (`RightPanel.vue`)

Buka `apps/web/app/components/dataset/infrastruktur-segmen/RightPanel.vue`:
1. Tambahkan emit:
   ```ts
   const emit = defineEmits<{
     // ...
     (e: 'editGeometry', segmen: InfrastrukturSegmen): void;
     (e: 'splitSegmen', segmen: InfrastrukturSegmen): void;
   }>();
   ```
2. Pada toolbar tindakan (di bawah tombol Zoom dan Edit Form), tambahkan tombol **Edit Geometri** dan **Split**:
   ```html
   <!-- Di dalam flex toolbar tindakan -->
   <UButton
     v-if="canEdit"
     icon="i-lucide-pen-tool"
     label="Edit Geometri"
     size="xs"
     color="neutral"
     variant="subtle"
     class="flex-1 justify-center cursor-pointer"
     title="Edit titik vertex garis segmen di peta"
     @click="emit('editGeometry', selectedSegmen)"
   />

   <UButton
     v-if="canEdit"
     icon="i-lucide-scissors"
     label="Split"
     size="xs"
     color="neutral"
     variant="subtle"
     class="flex-1 justify-center cursor-pointer"
     title="Potong segmen menjadi 2 bagian"
     @click="emit('splitSegmen', selectedSegmen)"
   />
   ```

---

### 5.4. Dialog Konfirmasi Pemotongan (`DialogSplitSegmen.vue`)

Buat komponen modal: `apps/web/app/components/dataset/infrastruktur-segmen/DialogSplitSegmen.vue`

```vue
<script setup lang="ts">
import type { InfrastrukturSegmen } from '~/types/infrastruktur';

interface Props {
  open: boolean;
  segmen: InfrastrukturSegmen | null;
  splitPoint: [number, number] | null;
  loading?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'confirm', payload: { part1Name: string; part2Name: string }): void;
  (e: 'cancel'): void;
}>();

const part1Name = ref('');
const part2Name = ref('');

watch(
  () => props.segmen,
  (s) => {
    if (s) {
      part1Name.value = `${s.namobj || 'Segmen'} (Bagian 1)`;
      part2Name.value = `${s.namobj || 'Segmen'} (Bagian 2)`;
    }
  },
  { immediate: true }
);

function handleConfirm() {
  if (!part1Name.value.trim() || !part2Name.value.trim()) return;
  emit('confirm', {
    part1Name: part1Name.value.trim(),
    part2Name: part2Name.value.trim(),
  });
}
</script>

<template>
  <UModal
    :open="open"
    title="Konfirmasi Pemotongan (Split) Segmen"
    :ui="{ content: 'sm:max-w-md' }"
    @update:open="(val) => emit('update:open', val)"
  >
    <template #body>
      <div class="space-y-4 text-xs text-gray-700 dark:text-gray-300">
        <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-1.5">
          <p class="font-medium text-gray-900 dark:text-gray-100">{{ segmen?.namobj }}</p>
          <div class="flex items-center gap-4 text-gray-500">
            <span>Panjang: <strong>{{ segmen?.panjang ?? segmen?.panjang_meter_gis }} m</strong></span>
            <span>Tipe: <strong>{{ segmen?.tipe_kode }}</strong></span>
          </div>
          <p v-if="splitPoint" class="font-mono text-[11px] text-blue-600 dark:text-blue-400">
            Titik Potong: {{ splitPoint[0].toFixed(6) }}, {{ splitPoint[1].toFixed(6) }}
          </p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Nama Bagian 1</label>
            <UInput v-model="part1Name" placeholder="Nama Ruas Bagian 1" size="sm" class="w-full" />
          </div>

          <div>
            <label class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Nama Bagian 2 (Baru)</label>
            <UInput v-model="part2Name" placeholder="Nama Ruas Bagian 2" size="sm" class="w-full" />
          </div>
        </div>

        <p class="text-[11px] text-gray-400 leading-relaxed">
          Pemotongan akan memperbarui data geometri segmen pertama dan membuat entitas segmen baru untuk bagian kedua dengan mewarisi properti wilayah dan teknis segmen asal.
        </p>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton color="neutral" variant="ghost" size="sm" label="Batal" @click="emit('cancel')" />
        <UButton
          color="primary"
          size="sm"
          icon="i-lucide-scissors"
          label="Konfirmasi Split"
          :loading="loading"
          @click="handleConfirm"
        />
      </div>
    </template>
  </UModal>
</template>
```

---

### 5.5. Orchestrasi Halaman Induk (`pages/admin/infrastruktur-segmen/index.vue`)

Hubungkan semua interaksi di file halaman utama:
1. Panggil `mapCanvasRef.value?.startEditGeometry(segmen)` saat event `editGeometry` diterima dari `RightPanel`.
2. Saat event `editGeometrySaved` diterima dari `MapCanvas`:
   - Tampilkan loading state.
   - Panggil `api.patchSegmenGeometry(id, geojson)`.
   - Toast notifikasi sukses `"Geometri berhasil diperbarui"`.
   - Jalankan `mapCanvasRef.value?.refreshVectorTiles()` untuk memuat ulang ubin MVT di kanvas peta.
   - Refresh data tabel atribut dan ringkasan metrik.
3. Panggil `mapCanvasRef.value?.startSplitMode(segmen)` saat event `splitSegmen` diterima dari `RightPanel`.
4. Saat event `splitPointSelected` diterima dari `MapCanvas`:
   - Simpan titik potong ke state lokal `selectedSplitPoint.value = coordinate`.
   - Buka modal dialog `isSplitDialogOpen.value = true`.
5. Saat dialog di-confirm:
   - Panggil `api.splitSegmen(segmen.id, { point: selectedSplitPoint.value, part1: { namobj: part1Name }, part2: { namobj: part2Name } })`.
   - Tutup modal dan matikan split mode: `mapCanvasRef.value?.cancelSplitMode()`.
   - Refresh peta, tabel, dan summary.
   - Toast notifikasi sukses.

---

## 6. Urutan Implementasi Bertahap (Step-by-Step Execution)

Ikuti urutan langkah kerja berikut secara berurutan:

```
[Langkah 1: Backend Routes & Form Requests]
  ├── Edit: apps/api/routes/web.php
  ├── Buat: apps/api/app/Http/Requests/Admin/UpdateInfrastrukturSegmenGeometryRequest.php
  └── Buat: apps/api/app/Http/Requests/Admin/SplitInfrastrukturSegmenRequest.php

[Langkah 2: Backend Policy & Controller]
  ├── Edit: apps/api/app/Policies/InfrastrukturSegmenPolicy.php
  └── Edit: apps/api/app/Http/Controllers/Admin/InfrastrukturSegmenController.php

[Langkah 3: Uji Coba API Backend via Manual Test]
  ├── Test PATCH admin/infrastruktur-segmen/{id}/geometry
  └── Test POST admin/infrastruktur-segmen/{id}/split

[Langkah 4: Frontend API Composable]
  └── Edit: apps/web/app/composables/useInfrastrukturApi.ts

[Langkah 5: Frontend MapCanvas (Interaksi OpenLayers)]
  └── Edit: apps/web/app/components/dataset/infrastruktur-segmen/MapCanvas.vue

[Langkah 6: Frontend RightPanel & Dialog Split]
  ├── Edit: apps/web/app/components/dataset/infrastruktur-segmen/RightPanel.vue
  └── Buat: apps/web/app/components/dataset/infrastruktur-segmen/DialogSplitSegmen.vue

[Langkah 7: Integrasi Halaman Induk & Verifikasi E2E]
  └── Edit: apps/web/app/pages/admin/infrastruktur-segmen/index.vue
```

---

## 7. Catatan Penting & Hal Rawan Salah (Edge Cases & Gotchas)

1. **Urutan Koordinat Spasial**:
   - Format PostGIS dan GeoJSON adalah **`[Longitude, Latitude]`** (bukan `[Lat, Long]`).
   - Koordinat Kabupaten Bojonegoro: Longitude berkisar `111.4° - 112.2°`, Latitude berkisar `-7.5° - -7.0°`. Jika angka latitude dan longitude terbalik, PostGIS akan melempar error atau segmen terbang ke kutub.
2. **Kalkulasi Panjang Geodesik**:
   - Selalu lakukan kalkulasi panjang menggunakan tipe data `::geography` di PostGIS:
     `ROUND(ST_Length(ST_SetSRID(...)::geography)::numeric, 2)`
     agar panjang dalam satuan **meter** permukaan bumi yang presisi, bukan satuan derajat (derajat jika memakai `geometry`).
3. **Invalidasi Cache Martin MVT**:
   - Saat geometri di-update atau di-split, database PostgreSQL berubah, namun Martin Tile Server mungkin masih meng-cache tile lama.
   - Panggil `MartinTileService::purgeCache('infrastruktur_segmen')` di backend (sudah terpasang di model event `booted`).
   - Di frontend, tambahkan cache buster `tileVersion.value = Date.now()` pada `MapCanvas.vue` saat me-refresh ubin MVT.
4. **Proteksi Garis Tunggal vs MultiLineString**:
   - Di PostGIS split query, gunakan:
     `CASE WHEN ST_GeometryType(ST_LineMerge(geom)) = 'ST_LineString' THEN ST_LineMerge(geom) ELSE ST_GeometryN(geom, 1) END`
     untuk mengantisipasi data yang tersimpan bertipe `MultiLineString`.
5. **Batas Toleransi Split**:
   - Validasi nilai `fraction` ($0.005 < fraction < 0.995$). Jika operator mengklik terlalu dekat dengan ujung awal atau akhir segmen, batalkan dengan pesan *"Titik pemotongan terlalu dekat dengan ujung segmen"*.
6. **Integritas Relasi Berita Acara (BA)**:
   - Segmen yang sudah terikat pada Berita Acara realisasi yang disahkan **dilarang di-split** karena akan merusak perhitungan fisik realisasi anggaran yang telah diaudit.

---

## 8. Skenario Pengujian (Testing Checklist)

Lakukan pengujian manual berikut setelah selesai mengoding:

- [ ] **Skenario 1: Edit Geometri**:
  1. Buka `/admin/infrastruktur-segmen`.
  2. Klik salah satu garis segmen di peta atau tabel.
  3. Klik tombol **"Edit Geometri"** di panel kanan.
  4. Amati banner biru muncul di atas peta dan titik-titik vertex muncul di sepanjang garis.
  5. Geser salah satu titik vertex. Pastikan angka meter pada banner berubah.
  6. Klik tombol **"Simpan Geometri"**.
  7. Pastikan toast berhasil muncul, garis di peta berubah mengikuti bentuk baru, dan data panjang di panel kanan terbarui.
- [ ] **Skenario 2: Batal Edit Geometri**:
  1. Klik **"Edit Geometri"**, geser titik sembarang, lalu klik **"Batal"**.
  2. Garis harus kembali ke posisi semula tanpa tersimpan ke server.
- [ ] **Skenario 3: Split Segmen Normal**:
  1. Pilih salah satu segmen yang panjangnya $\ge 200\text{ meter}$.
  2. Klik tombol **"Split"** di panel kanan.
  3. Klik di tengah badan jalan.
  4. Amati pin marker titik potong muncul dan modal dialog terbuka.
  5. Isi Nama Bagian 1 dan Nama Bagian 2, lalu klik **"Konfirmasi Split"**.
  6. Pastikan muncul 2 entitas segmen terpisah pada peta dan tabel dengan total panjang mendekati panjang awal.
- [ ] **Skenario 4: Split Ujung Ruas (Edge Case Validation)**:
  1. Klik tombol **"Split"**, lalu klik persis di ujung titik awal atau akhir ruas.
  2. Server harus menolak dengan pesan error yang ramah (HTTP 422: Titik pemotongan terlalu dekat dengan ujung segmen).

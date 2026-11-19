<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\PublikasiPetaDigital;
use App\Services\PublikasiPetaSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PublikasiPetaDigitalController extends Controller
{
    public function __construct(
        private readonly PublikasiPetaSecurityService $securityService,
    ) {
    }

    /**
     * Daftar publikasi peta digital yang dipublikasikan untuk portal publik & perangkat desa.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $query = PublikasiPetaDigital::query()
            ->where('is_published', true)
            ->orderByDesc('tahun_terbit')
            ->orderByDesc('published_at')
            ->orderBy('judul');

        $this->applyFilters($query, $request);

        $perPage = min(max((int) $request->integer('per_page', 12), 1), 60);
        $paginated = $query->paginate($perPage);

        $years = PublikasiPetaDigital::query()
            ->where('is_published', true)
            ->select('tahun_terbit')
            ->distinct()
            ->orderByDesc('tahun_terbit')
            ->pluck('tahun_terbit')
            ->filter()
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'available_years' => $years,
                'categories' => PublikasiPetaDigital::KATEGORI_LIST,
            ],
        ]);
    }

    /**
     * Daftar dropdown Kecamatan & Desa (publik, ringan, tanpa geometri).
     */
    public function publicWilayahOptions(Request $request): JsonResponse
    {
        $kecamatanList = BatasWilayahKecamatan::query()
            ->select(['id', 'nama_kecamatan'])
            ->orderBy('nama_kecamatan')
            ->get();

        $desaList = [];
        if ($request->filled('id_kecamatan')) {
            $idKec = $request->integer('id_kecamatan');
            $desaList = BatasWilayahDesa::query()
                ->select(['id', 'id_kecamatan', 'nama_desa'])
                ->where('id_kecamatan', $idKec)
                ->orderBy('nama_desa')
                ->get();
        }

        return response()->json([
            'ok' => true,
            'kecamatan' => $kecamatanList,
            'desa' => $desaList,
        ]);
    }

    /**
     * Detail 1 terbitan peta digital untuk publik.
     */
    public function publicShow(string $idOrSlug): JsonResponse
    {
        $item = $this->findByIdOrSlug($idOrSlug, true);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Terbitan peta digital tidak ditemukan.'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $item,
        ]);
    }

    /**
     * Unduh / Pratinjau PDF atau Redirect Link Eksternal dengan pencatatan download_count.
     */
    public function publicDownload(Request $request, string $idOrSlug): Response
    {
        $isAuthenticatedManager = $request->user() !== null && (
            $request->user()->hasRole('admin')
            || $request->user()->can('publikasi-peta-view')
            || $request->user()->can('publikasi-peta-manage')
            || $request->user()->can('layers-view')
            || $request->user()->can('batas-desa-view')
        );

        $item = $this->findByIdOrSlug($idOrSlug, !$isAuthenticatedManager);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Terbitan peta digital tidak ditemukan.'], 404);
        }

        if ($item->sumber_tipe === 'external_url') {
            if (empty($item->external_url)) {
                return response()->json(['ok' => false, 'message' => 'Tautan peta digital belum tersedia.'], 404);
            }

            $validatedUrl = $this->securityService->validateExternalUrl($item->external_url);
            $item->increment('download_count');

            if ($request->boolean('meta') || $request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'sumber_tipe' => 'external_url',
                    'external_url' => $validatedUrl['url'],
                    'external_domain' => $validatedUrl['domain'],
                    'download_count' => $item->download_count,
                ]);
            }

            return redirect()->away($validatedUrl['url']);
        }

        $disk = $item->file_disk ?: 'local';
        if (empty($item->file_path) || !Storage::disk($disk)->exists($item->file_path)) {
            return response()->json(['ok' => false, 'message' => 'Berkas PDF terbitan tidak ditemukan di penyimpanan.'], 404);
        }

        $inline = $request->boolean('inline');
        if (!$inline) {
            $item->increment('download_count');
        }

        $fullPath = Storage::disk($disk)->path($item->file_path);
        $downloadName = $this->securityService->buildSafeDownloadFilename($item);

        $headers = [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-transform, max-age=300',
        ];

        if ($inline) {
            $headers['Content-Disposition'] = 'inline; filename="' . $downloadName . '"';
            return response()->file($fullPath, $headers);
        }

        return response()->download($fullPath, $downloadName, $headers);
    }

    /**
     * Stream gambar raster georeferencing untuk overlay MapLibre GL JS (tanpa GeoTIFF).
     */
    public function publicGeorefImage(Request $request, string $idOrSlug): Response
    {
        $isAuthenticatedManager = $request->user() !== null && (
            $request->user()->hasRole('admin')
            || $request->user()->can('publikasi-peta-view')
            || $request->user()->can('publikasi-peta-manage')
            || $request->user()->can('layers-view')
            || $request->user()->can('batas-desa-view')
        );

        $item = $this->findByIdOrSlug($idOrSlug, !$isAuthenticatedManager);
        if (!$item || !$item->is_georeferenced || empty($item->georef_image_path)) {
            return response()->json(['ok' => false, 'message' => 'Gambar georeferencing tidak ditemukan.'], 404);
        }

        $disk = $item->georef_image_disk ?: 'local';
        if (!Storage::disk($disk)->exists($item->georef_image_path)) {
            return response()->json(['ok' => false, 'message' => 'Berkas gambar georeferencing tidak tersedia di penyimpanan.'], 404);
        }

        $fullPath = Storage::disk($disk)->path($item->georef_image_path);
        $mime = $item->georef_image_mime ?: 'image/png';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Daftar terbitan peta digital untuk Admin / Bappeda.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PublikasiPetaDigital::query()
            ->with(['creator:uuid,name', 'updater:uuid,name'])
            ->orderByDesc('tahun_terbit')
            ->orderByDesc('updated_at');

        $this->applyFilters($query, $request);

        if ($request->filled('is_published')) {
            $query->where('is_published', filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'categories' => PublikasiPetaDigital::KATEGORI_LIST,
            ],
        ]);
    }

    /**
     * Detail 1 terbitan peta digital untuk workspace admin (/admin/publikasi-peta/{id}).
     */
    public function show(string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::with(['creator:uuid,name', 'updater:uuid,name'])->find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $record,
        ]);
    }

    /**
     * Buat terbitan peta digital baru (Upload PDF privat atau Link URL).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, true);
        $sumberTipe = $validated['sumber_tipe'];

        $id = Str::uuid7()->toString();
        $slug = $this->generateUniqueSlug($validated['judul'], $validated['tahun_terbit'], $validated['id_desa'] ?? null);

        $wilayahMeta = $this->resolveWilayahMetadata(
            $validated['tingkat_wilayah'],
            $validated['id_kecamatan'] ?? null,
            $validated['id_desa'] ?? null
        );

        $fileFields = [
            'file_disk' => null,
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'file_size' => null,
            'checksum_sha256' => null,
            'external_url' => null,
            'external_domain' => null,
        ];

        $storedRelativePath = null;
        $storedDisk = 'local';

        if ($sumberTipe === 'file_pdf') {
            $pdfFile = $request->file('file_pdf');
            $pdfMeta = $this->securityService->validatePdf($pdfFile);
            $stored = $this->securityService->storePrivatePdf($pdfFile, $id);
            $storedRelativePath = $stored['path'];
            $storedDisk = $stored['disk'];

            $fileFields['file_disk'] = $stored['disk'];
            $fileFields['file_path'] = $stored['path'];
            $fileFields['original_filename'] = $pdfMeta['safe_name'];
            $fileFields['mime_type'] = $pdfMeta['mime'];
            $fileFields['file_size'] = $pdfMeta['size'];
            $fileFields['checksum_sha256'] = $pdfMeta['sha256'];
        } else {
            $urlMeta = $this->securityService->validateExternalUrl((string) ($validated['external_url'] ?? ''));
            $fileFields['external_url'] = $urlMeta['url'];
            $fileFields['external_domain'] = $urlMeta['domain'];
        }

        $isPublished = array_key_exists('is_published', $validated)
            ? filter_var($validated['is_published'], FILTER_VALIDATE_BOOLEAN)
            : true;

        try {
            $record = DB::transaction(function () use ($id, $slug, $validated, $wilayahMeta, $fileFields, $isPublished, $request) {
                $item = new PublikasiPetaDigital();
                $item->id = $id;
                $item->fill([
                    'judul' => trim($validated['judul']),
                    'slug' => $slug,
                    'nomor_dokumen' => isset($validated['nomor_dokumen']) ? trim((string) $validated['nomor_dokumen']) ?: null : null,
                    'kategori' => trim($validated['kategori']),
                    'tingkat_wilayah' => $validated['tingkat_wilayah'],
                    'id_kecamatan' => $wilayahMeta['id_kecamatan'],
                    'nama_kecamatan' => $wilayahMeta['nama_kecamatan'],
                    'id_desa' => $wilayahMeta['id_desa'],
                    'nama_desa' => $wilayahMeta['nama_desa'],
                    'tahun_terbit' => (int) $validated['tahun_terbit'],
                    'edisi' => isset($validated['edisi']) ? trim((string) $validated['edisi']) ?: null : null,
                    'skala_peta' => isset($validated['skala_peta']) ? trim((string) $validated['skala_peta']) ?: null : null,
                    'ringkasan' => isset($validated['ringkasan']) ? trim((string) $validated['ringkasan']) ?: null : null,
                    'sumber_tipe' => $validated['sumber_tipe'],
                    'external_url' => $fileFields['external_url'],
                    'external_domain' => $fileFields['external_domain'],
                    'file_disk' => $fileFields['file_disk'],
                    'file_path' => $fileFields['file_path'],
                    'original_filename' => $fileFields['original_filename'],
                    'mime_type' => $fileFields['mime_type'],
                    'file_size' => $fileFields['file_size'],
                    'checksum_sha256' => $fileFields['checksum_sha256'],
                    'is_published' => $isPublished,
                    'published_at' => $isPublished ? now() : null,
                    'created_by' => $request->user()?->uuid,
                    'updated_by' => $request->user()?->uuid,
                ]);
                $item->save();

                return $item;
            });
        } catch (\Throwable $e) {
            if ($storedRelativePath) {
                Storage::disk($storedDisk)->delete($storedRelativePath);
            }
            throw $e;
        }

        return response()->json([
            'ok' => true,
            'message' => 'Publikasi peta digital berhasil disimpan.',
            'data' => $record->load(['creator:uuid,name', 'updater:uuid,name']),
        ], 201);
    }

    /**
     * Perbarui metadata, URL, atau ganti file PDF publikasi peta digital.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        $validated = $this->validatePayload($request, false, $record);
        $sumberTipe = $validated['sumber_tipe'] ?? $record->sumber_tipe;

        $wilayahMeta = $this->resolveWilayahMetadata(
            $validated['tingkat_wilayah'] ?? $record->tingkat_wilayah,
            array_key_exists('id_kecamatan', $validated) ? $validated['id_kecamatan'] : $record->id_kecamatan,
            array_key_exists('id_desa', $validated) ? $validated['id_desa'] : $record->id_desa
        );

        $oldDisk = $record->file_disk ?: 'local';
        $oldPathToDelete = null;
        $newPathToCleanupOnError = null;

        if ($sumberTipe === 'file_pdf') {
            if ($request->hasFile('file_pdf')) {
                $pdfFile = $request->file('file_pdf');
                $pdfMeta = $this->securityService->validatePdf($pdfFile);
                $stored = $this->securityService->storePrivatePdf($pdfFile, $record->id);
                $newPathToCleanupOnError = $stored['path'];
                $oldPathToDelete = $record->file_path;

                $record->file_disk = $stored['disk'];
                $record->file_path = $stored['path'];
                $record->original_filename = $pdfMeta['safe_name'];
                $record->mime_type = $pdfMeta['mime'];
                $record->file_size = $pdfMeta['size'];
                $record->checksum_sha256 = $pdfMeta['sha256'];
            } elseif (empty($record->file_path)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Berkas PDF wajib diunggah untuk sumber tipe File PDF.',
                ], 422);
            }

            $record->external_url = null;
            $record->external_domain = null;
        } else {
            $urlMeta = $this->securityService->validateExternalUrl((string) ($validated['external_url'] ?? $record->external_url ?? ''));
            $oldPathToDelete = $record->file_path;
            $record->external_url = $urlMeta['url'];
            $record->external_domain = $urlMeta['domain'];
            $record->file_disk = null;
            $record->file_path = null;
            $record->original_filename = null;
            $record->mime_type = null;
            $record->file_size = null;
            $record->checksum_sha256 = null;
        }

        if (isset($validated['judul']) && trim($validated['judul']) !== $record->judul) {
            $record->slug = $this->generateUniqueSlug(
                $validated['judul'],
                (int) ($validated['tahun_terbit'] ?? $record->tahun_terbit),
                $wilayahMeta['id_desa'],
                $record->id
            );
        }

        $isPublished = array_key_exists('is_published', $validated)
            ? filter_var($validated['is_published'], FILTER_VALIDATE_BOOLEAN)
            : $record->is_published;

        $record->fill([
            'judul' => isset($validated['judul']) ? trim($validated['judul']) : $record->judul,
            'nomor_dokumen' => array_key_exists('nomor_dokumen', $validated)
                ? (trim((string) $validated['nomor_dokumen']) ?: null)
                : $record->nomor_dokumen,
            'kategori' => isset($validated['kategori']) ? trim($validated['kategori']) : $record->kategori,
            'tingkat_wilayah' => $validated['tingkat_wilayah'] ?? $record->tingkat_wilayah,
            'id_kecamatan' => $wilayahMeta['id_kecamatan'],
            'nama_kecamatan' => $wilayahMeta['nama_kecamatan'],
            'id_desa' => $wilayahMeta['id_desa'],
            'nama_desa' => $wilayahMeta['nama_desa'],
            'tahun_terbit' => isset($validated['tahun_terbit']) ? (int) $validated['tahun_terbit'] : $record->tahun_terbit,
            'edisi' => array_key_exists('edisi', $validated)
                ? (trim((string) $validated['edisi']) ?: null)
                : $record->edisi,
            'skala_peta' => array_key_exists('skala_peta', $validated)
                ? (trim((string) $validated['skala_peta']) ?: null)
                : $record->skala_peta,
            'ringkasan' => array_key_exists('ringkasan', $validated)
                ? (trim((string) $validated['ringkasan']) ?: null)
                : $record->ringkasan,
            'sumber_tipe' => $sumberTipe,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($record->published_at ?? now()) : null,
            'updated_by' => $request->user()?->uuid,
        ]);

        try {
            $record->save();
        } catch (\Throwable $e) {
            if ($newPathToCleanupOnError) {
                Storage::disk('local')->delete($newPathToCleanupOnError);
            }
            throw $e;
        }

        if ($oldPathToDelete && $oldPathToDelete !== $record->file_path) {
            Storage::disk($oldDisk)->delete($oldPathToDelete);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Publikasi peta digital berhasil diperbarui.',
            'data' => $record->fresh(['creator:uuid,name', 'updater:uuid,name']),
        ]);
    }

    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        $record->is_published = !$record->is_published;
        $record->published_at = $record->is_published ? ($record->published_at ?? now()) : null;
        $record->updated_by = $request->user()?->uuid;
        $record->save();

        return response()->json([
            'ok' => true,
            'message' => $record->is_published
                ? 'Terbitan peta digital dipublikasikan ke portal publik.'
                : 'Terbitan peta digital diarsipkan (disembunyikan dari publik).',
            'data' => $record->fresh(['creator:uuid,name', 'updater:uuid,name']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        $disk = $record->file_disk ?: 'local';
        $path = $record->file_path;
        $georefDisk = $record->georef_image_disk ?: 'local';
        $georefPath = $record->georef_image_path;

        $record->delete();

        if ($path) {
            Storage::disk($disk)->delete($path);
        }
        if ($georefPath) {
            Storage::disk($georefDisk)->delete($georefPath);
        }

        return response()->json([
            'ok' => true,
            'message' => "Publikasi \"{$record->judul}\" berhasil dihapus.",
        ]);
    }

    /**
     * Ambil GeoJSON batas wilayah desa/kecamatan untuk referensi MapLibre GL JS saat georeferencing.
     */
    public function wilayahBoundary(string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        $feature = null;
        try {
            if ($record->id_desa && method_exists(BatasWilayahDesa::class, 'scopeWithGeoJson')) {
                $desa = BatasWilayahDesa::withGeoJson()->find($record->id_desa);
                if ($desa) {
                    $feature = $desa->toGeoJsonFeature();
                }
            } elseif ($record->id_kecamatan && method_exists(BatasWilayahKecamatan::class, 'scopeWithGeoJson')) {
                $kec = BatasWilayahKecamatan::withGeoJson()->find($record->id_kecamatan);
                if ($kec) {
                    $feature = $kec->toGeoJsonFeature();
                }
            }
        } catch (\Throwable) {
            $feature = null;
        }

        return response()->json([
            'ok' => true,
            'feature' => $feature,
        ]);
    }

    /**
     * Simpan koordinat georeferencing MapLibre GL JS (4 sudut + GCP + gambar raster overlay), tanpa menghasilkan GeoTIFF.
     */
    public function saveGeoreference(Request $request, string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'georef_method' => ['required', 'string', Rule::in(PublikasiPetaDigital::GEOREF_METHODS)],
            'georef_coordinates' => ['required'],
            'georef_gcps' => ['nullable'],
            'georef_rms_meters' => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'georef_opacity' => ['nullable', 'numeric', 'min:0.1', 'max:1'],
            'georef_image' => [
                empty($record->georef_image_path) ? 'required' : 'nullable',
                'file',
                'max:' . PublikasiPetaDigital::MAX_GEOREF_IMAGE_KB,
            ],
        ], [
            'georef_method.required' => 'Metode transformasi georeferencing wajib dipilih.',
            'georef_coordinates.required' => 'Koordinat 4 sudut peta wajib tersedia.',
            'georef_image.required' => 'Gambar raster peta (PNG/JPG/WebP) wajib disediakan untuk overlay MapLibre.',
        ]);

        $coordMeta = $this->securityService->validateGeorefCoordinates($validated['georef_coordinates']);
        $gcps = $this->securityService->sanitizeGeorefGcps($validated['georef_gcps'] ?? []);

        $oldDisk = $record->georef_image_disk ?: 'local';
        $oldPath = $record->georef_image_path;
        $newPathToCleanup = null;

        if ($request->hasFile('georef_image')) {
            $imgFile = $request->file('georef_image');
            $imgMeta = $this->securityService->validateGeorefImage($imgFile);
            $stored = $this->securityService->storePrivateGeorefImage($imgFile, $record->id, $imgMeta['ext']);
            $newPathToCleanup = $stored['path'];

            $record->georef_image_disk = $stored['disk'];
            $record->georef_image_path = $stored['path'];
            $record->georef_image_mime = $imgMeta['mime'];
            $record->georef_image_width = $imgMeta['width'];
            $record->georef_image_height = $imgMeta['height'];
        }

        $record->is_georeferenced = true;
        $record->georef_method = $validated['georef_method'];
        $record->georef_coordinates = $coordMeta['coordinates'];
        $record->georef_bbox = $coordMeta['bbox'];
        $record->georef_gcps = $gcps;
        $record->georef_rms_meters = isset($validated['georef_rms_meters']) ? round((float) $validated['georef_rms_meters'], 2) : null;
        $record->georef_opacity = isset($validated['georef_opacity']) ? round((float) $validated['georef_opacity'], 2) : 0.85;
        $record->georeferenced_at = now();
        $record->georeferenced_by = $request->user()?->uuid;
        $record->updated_by = $request->user()?->uuid;

        try {
            $record->save();
        } catch (\Throwable $e) {
            if ($newPathToCleanup) {
                Storage::disk('local')->delete($newPathToCleanup);
            }
            throw $e;
        }

        if ($newPathToCleanup && $oldPath && $oldPath !== $record->georef_image_path) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Koordinat georeferencing MapLibre GL JS berhasil disimpan.',
            'data' => $record->fresh(['creator:uuid,name', 'updater:uuid,name']),
        ]);
    }

    /**
     * Hapus data georeferencing beserta gambar raster overlay dari publikasi.
     */
    public function clearGeoreference(Request $request, string $id): JsonResponse
    {
        $record = PublikasiPetaDigital::find($id);
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Publikasi peta digital tidak ditemukan.'], 404);
        }

        $oldDisk = $record->georef_image_disk ?: 'local';
        $oldPath = $record->georef_image_path;

        $record->is_georeferenced = false;
        $record->georef_method = null;
        $record->georef_coordinates = null;
        $record->georef_bbox = null;
        $record->georef_gcps = null;
        $record->georef_rms_meters = null;
        $record->georef_opacity = 0.85;
        $record->georef_image_disk = null;
        $record->georef_image_path = null;
        $record->georef_image_mime = null;
        $record->georef_image_width = null;
        $record->georef_image_height = null;
        $record->georeferenced_at = null;
        $record->georeferenced_by = null;
        $record->updated_by = $request->user()?->uuid;
        $record->save();

        if ($oldPath) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Data georeferencing berhasil dihapus.',
            'data' => $record->fresh(['creator:uuid,name', 'updater:uuid,name']),
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = '%' . mb_strtolower(trim((string) $request->input('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(judul) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(nomor_dokumen, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(nama_desa, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(nama_kecamatan, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(kategori, \'\')) LIKE ?', [$term]);
            });
        }

        if ($request->filled('tingkat_wilayah') && in_array($request->input('tingkat_wilayah'), PublikasiPetaDigital::TINGKAT_WILAYAH, true)) {
            $query->where('tingkat_wilayah', $request->input('tingkat_wilayah'));
        }

        if ($request->filled('id_kecamatan')) {
            $query->where('id_kecamatan', $request->integer('id_kecamatan'));
        }

        if ($request->filled('id_desa')) {
            $query->where('id_desa', $request->integer('id_desa'));
        }

        if ($request->filled('tahun_terbit')) {
            $query->where('tahun_terbit', $request->integer('tahun_terbit'));
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', trim((string) $request->input('kategori')));
        }

        if ($request->filled('sumber_tipe') && in_array($request->input('sumber_tipe'), PublikasiPetaDigital::SUMBER_TIPE, true)) {
            $query->where('sumber_tipe', $request->input('sumber_tipe'));
        }

        if ($request->filled('is_georeferenced')) {
            $query->where('is_georeferenced', filter_var($request->input('is_georeferenced'), FILTER_VALIDATE_BOOLEAN));
        }
    }

    private function findByIdOrSlug(string $idOrSlug, bool $publishedOnly): ?PublikasiPetaDigital
    {
        $query = PublikasiPetaDigital::query();
        if ($publishedOnly) {
            $query->where('is_published', true);
        }

        if (Str::isUuid($idOrSlug)) {
            return $query->where('id', $idOrSlug)->first();
        }

        return $query->where('slug', $idOrSlug)->first();
    }

    private function validatePayload(Request $request, bool $isCreate, ?PublikasiPetaDigital $existing = null): array
    {
        $sumberTipe = (string) ($request->input('sumber_tipe') ?? $existing?->sumber_tipe ?? 'file_pdf');

        $rules = [
            'judul' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'kategori' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:80'],
            'tingkat_wilayah' => [$isCreate ? 'required' : 'sometimes', 'string', Rule::in(PublikasiPetaDigital::TINGKAT_WILAYAH)],
            'id_kecamatan' => ['nullable', 'integer', 'min:1'],
            'id_desa' => ['nullable', 'integer', 'min:1'],
            'tahun_terbit' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:2000', 'max:2100'],
            'edisi' => ['nullable', 'string', 'max:60'],
            'skala_peta' => ['nullable', 'string', 'max:40'],
            'ringkasan' => ['nullable', 'string', 'max:2000'],
            'sumber_tipe' => [$isCreate ? 'required' : 'sometimes', 'string', Rule::in(PublikasiPetaDigital::SUMBER_TIPE)],
            'external_url' => [
                $sumberTipe === 'external_url' ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
            'file_pdf' => [
                $sumberTipe === 'file_pdf' && ($isCreate || empty($existing?->file_path)) ? 'required' : 'nullable',
                'file',
                'mimes:pdf',
                'max:' . PublikasiPetaDigital::MAX_PDF_KB,
            ],
            'is_published' => ['nullable', 'boolean'],
        ];

        $messages = [
            'judul.required' => 'Judul peta digital wajib diisi.',
            'kategori.required' => 'Kategori publikasi wajib dipilih.',
            'tingkat_wilayah.required' => 'Tingkat cakupan wilayah wajib dipilih.',
            'tahun_terbit.required' => 'Tahun terbit wajib diisi.',
            'external_url.required' => 'Link URL peta digital wajib diisi untuk tipe Link URL.',
            'file_pdf.required' => 'Berkas PDF wajib diunggah untuk tipe Upload File PDF.',
            'file_pdf.mimes' => 'Format berkas harus berupa PDF (.pdf).',
            'file_pdf.max' => 'Ukuran file PDF maksimal 25 MB.',
        ];

        $validated = $request->validate($rules, $messages);

        $tingkat = $validated['tingkat_wilayah'] ?? $existing?->tingkat_wilayah ?? 'kabupaten';
        $idKec = array_key_exists('id_kecamatan', $validated) ? $validated['id_kecamatan'] : $existing?->id_kecamatan;
        $idDesa = array_key_exists('id_desa', $validated) ? $validated['id_desa'] : $existing?->id_desa;

        if ($tingkat === 'kecamatan' && empty($idKec)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_kecamatan' => ['Kecamatan wajib dipilih untuk cakupan tingkat kecamatan.'],
            ]);
        }

        if ($tingkat === 'desa' && (empty($idKec) || empty($idDesa))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_desa' => ['Kecamatan dan Desa wajib dipilih untuk terbitan peta digital desa.'],
            ]);
        }

        return $validated;
    }

    /**
     * @return array{id_kecamatan: int|null, nama_kecamatan: string|null, id_desa: int|null, nama_desa: string|null}
     */
    private function resolveWilayahMetadata(string $tingkat, ?int $idKecamatan, ?int $idDesa): array
    {
        if ($tingkat === 'kabupaten') {
            return [
                'id_kecamatan' => null,
                'nama_kecamatan' => null,
                'id_desa' => null,
                'nama_desa' => null,
            ];
        }

        $kec = $idKecamatan ? BatasWilayahKecamatan::find($idKecamatan) : null;
        if (!$kec) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_kecamatan' => ['Data kecamatan tidak ditemukan.'],
            ]);
        }

        if ($tingkat === 'kecamatan') {
            return [
                'id_kecamatan' => (int) $kec->id,
                'nama_kecamatan' => $kec->nama_kecamatan,
                'id_desa' => null,
                'nama_desa' => null,
            ];
        }

        $desa = $idDesa
            ? BatasWilayahDesa::where('id', $idDesa)->where('id_kecamatan', $kec->id)->first()
            : null;
        if (!$desa) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_desa' => ['Data desa tidak ditemukan atau tidak berada pada kecamatan terpilih.'],
            ]);
        }

        return [
            'id_kecamatan' => (int) $kec->id,
            'nama_kecamatan' => $kec->nama_kecamatan,
            'id_desa' => (int) $desa->id,
            'nama_desa' => $desa->nama_desa,
        ];
    }

    private function generateUniqueSlug(string $judul, int $tahun, ?int $idDesa, ?string $ignoreId = null): string
    {
        $base = Str::slug($judul . '-' . $tahun . ($idDesa ? "-{$idDesa}" : ''));
        if ($base === '') {
            $base = 'peta-digital-' . $tahun;
        }
        $base = Str::limit($base, 220, '');

        $slug = $base;
        $counter = 2;
        while (
            PublikasiPetaDigital::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

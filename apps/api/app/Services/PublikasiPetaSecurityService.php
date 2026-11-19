<?php

namespace App\Services;

use App\Models\PublikasiPetaDigital;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublikasiPetaSecurityService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'applications/vnd.pdf',
    ];

    private const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_PNG => ['mime' => 'image/png', 'ext' => 'png'],
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'ext' => 'webp'],
    ];

    /**
     * Validasi ketat file PDF sebelum disimpan: ukuran, ekstensi, finfo MIME, dan magic bytes %PDF-.
     *
     * @return array{size: int, mime: string, sha256: string, safe_name: string}
     */
    public function validatePdf(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'file_pdf' => ['File PDF gagal diunggah atau rusak.'],
            ]);
        }

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0 || $size > PublikasiPetaDigital::MAX_PDF_BYTES) {
            throw ValidationException::withMessages([
                'file_pdf' => ['Ukuran file PDF wajib antara 1 byte hingga 25 MB.'],
            ]);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'pdf') {
            throw ValidationException::withMessages([
                'file_pdf' => ['Hanya berkas berekstensi .pdf yang diizinkan.'],
            ]);
        }

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            throw ValidationException::withMessages([
                'file_pdf' => ['File PDF tidak dapat dibaca oleh server.'],
            ]);
        }

        $finfoMime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $finfoMime = finfo_file($finfo, $realPath) ?: null;
                finfo_close($finfo);
            }
        }

        $detectedMime = strtolower((string) ($finfoMime ?: $file->getMimeType() ?: ''));
        if (!in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file_pdf' => ['Isi berkas bukan dokumen PDF yang valid (MIME terdeteksi: ' . ($detectedMime ?: 'tidak diketahui') . ').'],
            ]);
        }

        // Magic bytes inspection: pastikan header benar-benar diawali signature %PDF-
        $fh = fopen($realPath, 'rb');
        if ($fh === false) {
            throw ValidationException::withMessages([
                'file_pdf' => ['Gagal memverifikasi header file PDF.'],
            ]);
        }
        $header = fread($fh, 1024) ?: '';
        fclose($fh);

        if (!str_contains(substr($header, 0, 16), '%PDF-')) {
            throw ValidationException::withMessages([
                'file_pdf' => ['Tanda tangan file (%PDF-) tidak ditemukan. Unggahan ditolak demi keamanan.'],
            ]);
        }

        $rawOriginal = (string) $file->getClientOriginalName();
        $baseName = pathinfo($rawOriginal, PATHINFO_FILENAME);
        $safeBase = Str::slug($baseName ?: 'peta-digital');
        $safeName = ($safeBase !== '' ? $safeBase : 'peta-digital') . '.pdf';

        return [
            'size' => $size,
            'mime' => 'application/pdf',
            'sha256' => hash_file('sha256', $realPath) ?: '',
            'safe_name' => Str::limit($safeName, 180, '.pdf'),
        ];
    }

    /**
     * Simpan PDF ke private disk (local) dengan nama UUID agar aman dari path traversal & direct public execution.
     *
     * @return array{disk: string, path: string}
     */
    public function storePrivatePdf(UploadedFile $file, string $entityId): array
    {
        $disk = 'local';
        $yearSegment = now()->format('Y');
        $directory = "publikasi-peta/{$yearSegment}/{$entityId}";
        $filename = Str::uuid7()->toString() . '.pdf';
        $relativePath = "{$directory}/{$filename}";

        Storage::disk($disk)->putFileAs($directory, $file, $filename);

        return [
            'disk' => $disk,
            'path' => $relativePath,
        ];
    }

    /**
     * Validasi URL eksternal: wajib http/https publik, tolak skema berbahaya (javascript:, data:, file:) dan host lokal/privat.
     *
     * @return array{url: string, domain: string}
     */
    public function validateExternalUrl(string $rawUrl): array
    {
        $trimmed = trim($rawUrl);
        if ($trimmed === '' || strlen($trimmed) > 2000) {
            throw ValidationException::withMessages([
                'external_url' => ['Link URL peta digital tidak valid atau terlalu panjang.'],
            ]);
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $trimmed) === 1) {
            throw ValidationException::withMessages([
                'external_url' => ['Link URL mengandung karakter tidak valid atau spasi.'],
            ]);
        }

        $parsed = parse_url($trimmed);
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));

        if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
            throw ValidationException::withMessages([
                'external_url' => ['URL wajib menggunakan protokol https:// atau http:// yang valid.'],
            ]);
        }

        if (
            in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            throw ValidationException::withMessages([
                'external_url' => ['URL tidak boleh mengarah ke alamat internal atau localhost.'],
            ]);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw ValidationException::withMessages([
                    'external_url' => ['URL IP privat atau reserved tidak diizinkan.'],
                ]);
            }
        }

        return [
            'url' => $trimmed,
            'domain' => $host,
        ];
    }

    public function buildSafeDownloadFilename(PublikasiPetaDigital $item): string
    {
        $parts = array_filter([
            $item->judul,
            $item->nama_desa ? "desa-{$item->nama_desa}" : null,
            $item->nama_kecamatan ? "kec-{$item->nama_kecamatan}" : null,
            $item->tahun_terbit ? (string) $item->tahun_terbit : null,
        ]);

        $slug = Str::slug(implode(' ', $parts));
        if ($slug === '') {
            $slug = 'peta-digital';
        }

        return Str::limit($slug, 140, '') . '.pdf';
    }

    /**
     * Validasi ketat gambar raster peta (PNG/JPEG/WebP) untuk overlay georeferencing MapLibre GL JS.
     *
     * @return array{mime: string, ext: string, width: int, height: int, size: int}
     */
    public function validateGeorefImage(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'georef_image' => ['Gambar raster peta gagal diunggah atau rusak.'],
            ]);
        }

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0 || $size > PublikasiPetaDigital::MAX_GEOREF_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'georef_image' => ['Ukuran gambar peta maksimal 15 MB.'],
            ]);
        }

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            throw ValidationException::withMessages([
                'georef_image' => ['Gambar raster peta tidak dapat dibaca oleh server.'],
            ]);
        }

        $info = @getimagesize($realPath);
        if ($info === false || !isset($info[0], $info[1], $info[2])) {
            throw ValidationException::withMessages([
                'georef_image' => ['Berkas bukan gambar raster valid (PNG/JPG/WebP).'],
            ]);
        }

        $imageType = (int) $info[2];
        if (!isset(self::ALLOWED_IMAGE_TYPES[$imageType])) {
            throw ValidationException::withMessages([
                'georef_image' => ['Format gambar hanya mendukung PNG, JPG, atau WebP.'],
            ]);
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 32 || $height < 32 || $width > 12000 || $height > 12000) {
            throw ValidationException::withMessages([
                'georef_image' => ['Dimensi gambar harus antara 32px hingga 12.000px.'],
            ]);
        }

        $meta = self::ALLOWED_IMAGE_TYPES[$imageType];
        return [
            'mime' => $meta['mime'],
            'ext' => $meta['ext'],
            'width' => $width,
            'height' => $height,
            'size' => $size,
        ];
    }

    /**
     * Simpan gambar overlay georeferencing ke private storage disk.
     *
     * @return array{disk: string, path: string}
     */
    public function storePrivateGeorefImage(UploadedFile $file, string $entityId, string $ext): array
    {
        $disk = 'local';
        $yearSegment = now()->format('Y');
        $directory = "publikasi-peta/georef/{$yearSegment}/{$entityId}";
        $filename = Str::uuid7()->toString() . '.' . $ext;
        $relativePath = "{$directory}/{$filename}";

        Storage::disk($disk)->putFileAs($directory, $file, $filename);

        return [
            'disk' => $disk,
            'path' => $relativePath,
        ];
    }

    /**
     * Validasi 4 sudut koordinat MapLibre GL JS image source: [[lng,lat], [lng,lat], [lng,lat], [lng,lat]].
     *
     * @return array{coordinates: array<int, array<int, float>>, bbox: array<int, float>}
     */
    public function validateGeorefCoordinates(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($raw) || count($raw) !== 4) {
            throw ValidationException::withMessages([
                'georef_coordinates' => ['Koordinat georeferencing wajib berisi tepat 4 titik sudut [lng, lat].'],
            ]);
        }

        $coords = [];
        $lngs = [];
        $lats = [];

        foreach ($raw as $idx => $pair) {
            if (!is_array($pair) || count($pair) !== 2) {
                throw ValidationException::withMessages([
                    'georef_coordinates' => ["Titik sudut ke-" . ($idx + 1) . " harus berbentuk [longitude, latitude]."],
                ]);
            }
            $lng = (float) $pair[0];
            $lat = (float) $pair[1];

            if (!is_finite($lng) || !is_finite($lat) || $lng < -180.0 || $lng > 180.0 || $lat < -85.05113 || $lat > 85.05113) {
                throw ValidationException::withMessages([
                    'georef_coordinates' => ["Koordinat sudut ke-" . ($idx + 1) . " berada di luar rentang geografis WGS84 / WebMercator."],
                ]);
            }

            $lngRounded = round($lng, 7);
            $latRounded = round($lat, 7);
            $coords[] = [$lngRounded, $latRounded];
            $lngs[] = $lngRounded;
            $lats[] = $latRounded;
        }

        $minLng = min($lngs);
        $maxLng = max($lngs);
        $minLat = min($lats);
        $maxLat = max($lats);

        if (($maxLng - $minLng) < 0.00001 && ($maxLat - $minLat) < 0.00001) {
            throw ValidationException::withMessages([
                'georef_coordinates' => ['Keempat titik sudut peta terlalu berdekatan atau berimpit.'],
            ]);
        }

        return [
            'coordinates' => $coords,
            'bbox' => [$minLng, $minLat, $maxLng, $maxLat],
        ];
    }

    /**
     * Sanitasi daftar titik ikat (GCP) yang dikirim dari UI georeferencer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeGeorefGcps(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach (array_slice($raw, 0, 50) as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            $px = isset($item['px']) ? (float) $item['px'] : null;
            $py = isset($item['py']) ? (float) $item['py'] : null;
            $lng = isset($item['lng']) ? (float) $item['lng'] : null;
            $lat = isset($item['lat']) ? (float) $item['lat'] : null;

            if ($px === null || $py === null || $lng === null || $lat === null) {
                continue;
            }
            if (!is_finite($px) || !is_finite($py) || !is_finite($lng) || !is_finite($lat)) {
                continue;
            }
            if ($lng < -180.0 || $lng > 180.0 || $lat < -85.05113 || $lat > 85.05113) {
                continue;
            }

            $clean[] = [
                'id' => isset($item['id']) ? (string) Str::limit((string) $item['id'], 40, '') : (string) ($idx + 1),
                'label' => isset($item['label']) ? (string) Str::limit((string) $item['label'], 60, '') : 'GCP-' . ($idx + 1),
                'px' => round($px, 2),
                'py' => round($py, 2),
                'lng' => round($lng, 7),
                'lat' => round($lat, 7),
            ];
        }

        return $clean;
    }
}

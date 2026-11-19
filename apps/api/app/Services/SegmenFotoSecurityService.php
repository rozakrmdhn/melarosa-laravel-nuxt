<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class SegmenFotoSecurityService
{
    public const MAX_FILE_BYTES = 5_242_880; // 5 MB

    public const MIN_DIMENSION_PX = 50;

    public const MAX_DIMENSION_PX = 8000;

    public const MAX_PIXEL_AREA = 36_000_000; // ~36 MP decompression-bomb guard

    public const TARGET_MAX_EDGE_PX = 1600;

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
    ];

    /**
     * Memvalidasi keaslian biner gambar, mencegah decompression-bomb & file polyglot,
     * lalu me-reencode ulang menjadi WebP terkompresi agar seluruh metadata EXIF/payload berbahaya terhapus.
     *
     * @return array{binary: string, width: int, height: int, size: int, sha256: string, mime: string}
     */
    public function sanitizeAndReencode(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            throw ValidationException::withMessages([
                'foto' => ['File foto tidak dapat dibaca oleh server.'],
            ]);
        }

        // Tolak nama file mencurigakan (double extension seperti shell.php.jpg / script.svg.png)
        $clientName = (string) $file->getClientOriginalName();
        if (preg_match('/\.(php\d*|phtml|phar|svg|html?|js|exe|sh|bat|cmd|py|pl|cgi)\b/i', $clientName)) {
            throw ValidationException::withMessages([
                'foto' => ['Nama file mengandung ekstensi berbahaya dan ditolak sistem keamanan.'],
            ]);
        }

        $fileSize = (int) ($file->getSize() ?: filesize($realPath));
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages([
                'foto' => ['Ukuran foto tidak valid atau melebihi batas maksimal 5 MB.'],
            ]);
        }

        // Verifikasi MIME dari magic byte konten aktual, bukan sekadar header upload
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->file($realPath));
        if (!in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'foto' => ['Format gambar tidak valid. Gunakan foto asli berformat JPG, PNG, atau WebP.'],
            ]);
        }

        // Baca dimensi biner sebelum decode untuk mencegah pixel-flood / decompression bomb
        $imageInfo = @getimagesize($realPath);
        if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1]) || empty($imageInfo[2])) {
            throw ValidationException::withMessages([
                'foto' => ['Struktur gambar rusak atau bukan file gambar yang sah.'],
            ]);
        }

        [$origW, $origH, $imageType] = [(int) $imageInfo[0], (int) $imageInfo[1], (int) $imageInfo[2]];
        if (!in_array($imageType, self::ALLOWED_IMAGE_TYPES, true)) {
            throw ValidationException::withMessages([
                'foto' => ['Tipe gambar tidak diizinkan (hanya JPG, PNG, WebP).'],
            ]);
        }

        if (
            $origW < self::MIN_DIMENSION_PX ||
            $origH < self::MIN_DIMENSION_PX ||
            $origW > self::MAX_DIMENSION_PX ||
            $origH > self::MAX_DIMENSION_PX ||
            ($origW * $origH) > self::MAX_PIXEL_AREA
        ) {
            throw ValidationException::withMessages([
                'foto' => ['Resolusi foto berada di luar batas aman (min 50x50 px, maks 8000x8000 px).'],
            ]);
        }

        // Decode dan encode ulang bersih ke WebP (menghapus EXIF GPS, komentar biner, dan trailing payload)
        try {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->decode($realPath);
            $image->scaleDown(self::TARGET_MAX_EDGE_PX, self::TARGET_MAX_EDGE_PX);
            $encoded = $image->encode(new WebpEncoder(quality: 82));
            $binary = (string) $encoded;
            $outW = $image->width();
            $outH = $image->height();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'foto' => ['Gambar gagal diproses ulang oleh sanitizer server. Pastikan file foto tidak korup.'],
            ]);
        }

        if ($binary === '') {
            throw ValidationException::withMessages([
                'foto' => ['Hasil sanitasi gambar kosong.'],
            ]);
        }

        return [
            'binary' => $binary,
            'width'  => $outW,
            'height' => $outH,
            'size'   => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'mime'   => 'image/webp',
        ];
    }

    public function sanitizeCaption(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $clean = trim(strip_tags($raw));
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean) ?? '';
        if ($clean === '') {
            return null;
        }

        return mb_substr($clean, 0, 200);
    }
}

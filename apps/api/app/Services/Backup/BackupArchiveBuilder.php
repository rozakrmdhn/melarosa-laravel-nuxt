<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Pengemasan dan ekstraksi aman arsip backup (ZipSlip-safe, exclusion-aware, verifikasi checksum internal).
 */
class BackupArchiveBuilder
{
    /**
     * @param  array<string, string>  $includePaths  Peta nama root => absolute directory path
     * @param  list<string>  $excludePatterns
     * @return array{files_count: int, total_bytes: int}
     */
    public function createFilesZip(string $destinationZipPath, array $includePaths, array $excludePatterns): array
    {
        $zip = new ZipArchive;
        if ($zip->open($destinationZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Gagal membuat arsip ZIP untuk file storage.');
        }

        $filesCount = 0;
        $totalBytes = 0;

        try {
            $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);

            foreach ($includePaths as $rootAlias => $rootDir) {
                if (! is_string($rootDir) || $rootDir === '' || ! is_dir($rootDir)) {
                    continue;
                }

                $realRoot = realpath($rootDir);
                if ($realRoot === false) {
                    continue;
                }

                $dirIterator = new RecursiveDirectoryIterator($realRoot, RecursiveDirectoryIterator::SKIP_DOTS);
                $filterIterator = new RecursiveCallbackFilterIterator(
                    $dirIterator,
                    function (SplFileInfo $current) use ($realRoot, $excludePatterns): bool {
                        if ($current->isLink()) {
                            return false;
                        }

                        return ! $this->isExcluded($current, $realRoot, $excludePatterns);
                    }
                );

                $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);

                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if (! $file->isFile() || ! $file->isReadable()) {
                        continue;
                    }

                    $realFilePath = $file->getRealPath();
                    if ($realFilePath === false || ! str_starts_with($realFilePath, $realRoot)) {
                        continue;
                    }

                    $relativeWithinRoot = ltrim(
                        str_replace('\\', '/', substr($realFilePath, strlen($realRoot))),
                        '/'
                    );
                    $entryName = $this->assertSafeZipEntry("{$rootAlias}/{$relativeWithinRoot}");

                    if (! $zip->addFile($realFilePath, $entryName)) {
                        throw new BackupException("Gagal menambahkan file ke arsip backup: {$entryName}");
                    }

                    $filesCount++;
                    $totalBytes += (int) $file->getSize();
                }
            }

            if ($filesCount === 0) {
                $zip->addFromString('.storage-manifest.json', json_encode([
                    'empty' => true,
                    'files_count' => 0,
                    'generated_at' => now()->toIso8601String(),
                ], JSON_PRETTY_PRINT));
            }
        } finally {
            $zip->close();
        }

        return [
            'files_count' => $filesCount,
            'total_bytes' => $totalBytes,
        ];
    }

    /**
     * Menggabungkan artefak backup beserta manifest.json dan checksums.sha256 ke dalam satu arsip ZIP utama.
     *
     * @param  array<string, string>  $artifactFiles  Peta entry name => absolute local path
     * @param  array<string, mixed>  $manifestData
     */
    public function createPackageZip(string $packageZipPath, array $artifactFiles, array $manifestData): void
    {
        $checksumLines = [];
        $artifactChecksums = [];

        foreach ($artifactFiles as $entryName => $absPath) {
            $safeEntry = $this->assertSafeZipEntry($entryName);
            if (! is_file($absPath) || ! is_readable($absPath)) {
                throw new BackupException("Artefak backup tidak ditemukan: {$safeEntry}");
            }

            $sha = hash_file('sha256', $absPath);
            if ($sha === false) {
                throw new BackupException("Gagal menghitung checksum artefak: {$safeEntry}");
            }

            $artifactChecksums[$safeEntry] = [
                'sha256' => $sha,
                'size' => (int) filesize($absPath),
            ];
            $checksumLines[] = "{$sha}  {$safeEntry}";
        }

        $manifestPayload = array_merge($manifestData, [
            'format_version' => '1.0',
            'artifacts' => $artifactChecksums,
        ]);

        $manifestJson = json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($manifestJson === false) {
            throw new BackupException('Gagal membuat manifest JSON arsip backup.');
        }

        $checksumLines[] = hash('sha256', $manifestJson).'  manifest.json';
        $checksumText = implode("\n", $checksumLines)."\n";

        $zip = new ZipArchive;
        if ($zip->open($packageZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Gagal membuka arsip paket backup.');
        }

        try {
            $zip->addFromString('manifest.json', $manifestJson);
            $zip->addFromString('checksums.sha256', $checksumText);

            foreach ($artifactFiles as $entryName => $absPath) {
                $safeEntry = $this->assertSafeZipEntry($entryName);
                if (! $zip->addFile($absPath, $safeEntry)) {
                    throw new BackupException("Gagal memasukkan artefak {$safeEntry} ke paket backup.");
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Memeriksa struktur paket ZIP secara aman, mengekstrak bila diminta, dan memverifikasi checksum internal.
     *
     * @return array{manifest: ?array<string, mixed>, checksums_verified: bool, entries: list<string>, has_database_dump: bool, has_files_zip: bool}
     */
    public function inspectAndVerifyPackage(string $zipPath, ?string $extractDir = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new BackupException('Arsip backup ZIP korup atau format tidak dikenali.');
        }

        $entries = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $entryName = (string) $stat['name'];
                $entries[] = $this->assertSafeZipEntry($entryName);
            }

            $manifestRaw = $zip->getFromName('manifest.json');
            $checksumRaw = $zip->getFromName('checksums.sha256');

            $manifest = null;
            if (is_string($manifestRaw) && $manifestRaw !== '') {
                $decoded = json_decode($manifestRaw, true);
                if (is_array($decoded)) {
                    $manifest = $decoded;
                }
            }

            if ($extractDir !== null) {
                File::ensureDirectoryExists($extractDir);
                $this->extractEntriesSafely($zip, $extractDir, $entries);
            }

            $checksumsVerified = true;
            if (is_string($checksumRaw) && trim($checksumRaw) !== '') {
                $checksumsVerified = $this->verifyZipChecksums($zip, $checksumRaw);
            } elseif ($manifest !== null) {
                $checksumsVerified = false;
            }
        } finally {
            $zip->close();
        }

        return [
            'manifest' => $manifest,
            'checksums_verified' => $checksumsVerified,
            'entries' => $entries,
            'has_database_dump' => in_array('database.dump', $entries, true) || in_array('database.sql', $entries, true),
            'has_files_zip' => in_array('files.zip', $entries, true),
        ];
    }

    /**
     * Mengekstrak isi files.zip dengan proteksi ZipSlip dan mempertahankan folder terproteksi (backups, temporary, framework, logs).
     *
     * @param  array<string, string>  $targetMap  Peta prefix ('public' => dir, 'private' => dir)
     * @param  list<string>  $protectedSegments
     * @return array{restored_files: int, skipped_files: int}
     */
    public function extractFilesZipToTargets(string $filesZipPath, array $targetMap, array $protectedSegments = ['backups', 'temporary', 'framework', 'logs']): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filesZipPath) !== true) {
            throw new BackupException('File arsip files.zip korup atau tidak dapat dibuka.');
        }

        $restoredFiles = 0;
        $skippedFiles = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }

                $safeEntry = $this->assertSafeZipEntry($name);
                if ($safeEntry === '.storage-manifest.json') {
                    continue;
                }

                $parts = explode('/', $safeEntry, 2);
                if (count($parts) !== 2) {
                    $skippedFiles++;
                    continue;
                }

                [$rootAlias, $relativePath] = $parts;
                if (! isset($targetMap[$rootAlias])) {
                    $skippedFiles++;
                    continue;
                }

                $firstSegment = explode('/', $relativePath)[0] ?? '';
                if (in_array(strtolower($firstSegment), $protectedSegments, true)) {
                    $skippedFiles++;
                    continue;
                }

                $baseTarget = $targetMap[$rootAlias];
                File::ensureDirectoryExists($baseTarget);
                $destinationPath = $baseTarget.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                File::ensureDirectoryExists(dirname($destinationPath));
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new BackupException("Gagal membaca entry {$safeEntry} dari files.zip.");
                }

                $out = @fopen($destinationPath, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new BackupException("Gagal menulis file hasil restore ke {$destinationPath}.");
                }

                try {
                    stream_copy_to_stream($stream, $out);
                } finally {
                    fclose($stream);
                    fclose($out);
                }

                $restoredFiles++;
            }
        } finally {
            $zip->close();
        }

        return [
            'restored_files' => $restoredFiles,
            'skipped_files' => $skippedFiles,
        ];
    }

    public function assertSafeZipEntry(string $entryName): string
    {
        $normalized = str_replace('\\', '/', $entryName);
        if (
            $normalized === ''
            || str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:/', $normalized)
        ) {
            throw new BackupException("Entry arsip tidak aman terdeteksi: {$entryName}");
        }

        foreach (explode('/', trim($normalized, '/')) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new BackupException("Path traversal (ZipSlip) terdeteksi pada entry arsip: {$entryName}");
            }
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $excludePatterns
     */
    private function isExcluded(SplFileInfo $file, string $realRoot, array $excludePatterns): bool
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return true;
        }

        $relative = ltrim(str_replace('\\', '/', substr($realPath, strlen($realRoot))), '/');
        if ($relative === '') {
            return false;
        }

        $segments = explode('/', $relative);
        $baseName = $file->getFilename();

        foreach ($excludePatterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            foreach ($segments as $segment) {
                if (strcasecmp($segment, $pattern) === 0 || fnmatch($pattern, $segment, FNM_CASEFOLD)) {
                    return true;
                }
            }

            if (fnmatch($pattern, $baseName, FNM_CASEFOLD) || fnmatch($pattern, $relative, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $entries
     */
    private function extractEntriesSafely(ZipArchive $zip, string $extractDir, array $entries): void
    {
        foreach ($entries as $entryName) {
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            $targetFile = $extractDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryName);
            File::ensureDirectoryExists(dirname($targetFile));

            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                throw new BackupException("Gagal mengekstrak entry arsip: {$entryName}");
            }

            $out = @fopen($targetFile, 'wb');
            if ($out === false) {
                fclose($stream);
                throw new BackupException("Gagal menulis file temporary hasil ekstraksi: {$entryName}");
            }

            try {
                stream_copy_to_stream($stream, $out);
            } finally {
                fclose($stream);
                fclose($out);
            }
        }
    }

    private function verifyZipChecksums(ZipArchive $zip, string $checksumText): bool
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($checksumText)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^([a-fA-F0-9]{64})\s{1,2}(.+)$/', $line, $matches)) {
                return false;
            }

            $expectedHash = strtolower($matches[1]);
            $entryName = $this->assertSafeZipEntry(trim($matches[2]));
            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                return false;
            }

            $ctx = hash_init('sha256');
            while (! feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    fclose($stream);

                    return false;
                }
                if ($chunk !== '') {
                    hash_update($ctx, $chunk);
                }
            }
            fclose($stream);

            if (! hash_equals($expectedHash, hash_final($ctx))) {
                return false;
            }
        }

        return true;
    }
}


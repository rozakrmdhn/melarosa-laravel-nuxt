<?php

namespace App\Services\Backup;

use App\Models\BackupHistory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pengelolaan penyimpanan arsip backup lokal (private), opsional remote storage (S3),
 * verifikasi SHA-256, proteksi path traversal, dan pemantauan kapasitas disk.
 */
class BackupStorageService
{
    public function localDiskName(): string
    {
        return (string) config('backup.disk', 'backups');
    }

    public function remoteDiskName(): ?string
    {
        $remote = config('backup.remote_disk');

        return is_string($remote) && trim($remote) !== '' ? trim($remote) : null;
    }

    public function localDisk(): Filesystem
    {
        return Storage::disk($this->localDiskName());
    }

    public function remoteDisk(): ?Filesystem
    {
        $name = $this->remoteDiskName();

        return $name !== null ? Storage::disk($name) : null;
    }

    public function assertSafeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if (
            $normalized === ''
            || str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:/', $normalized)
        ) {
            throw new BackupException('Path arsip backup tidak valid.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new BackupException('Path traversal terdeteksi pada path arsip backup.');
            }
        }

        return $normalized;
    }

    public function computeSha256(string $absolutePath): string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new BackupException('File tidak ditemukan untuk penghitungan checksum SHA-256.');
        }

        $hash = @hash_file('sha256', $absolutePath);
        if ($hash === false) {
            throw new BackupException('Gagal menghitung checksum SHA-256 arsip backup.');
        }

        return $hash;
    }

    public function createTempWorkspace(string $prefix = 'job'): string
    {
        $base = rtrim((string) config('backup.temp_directory', storage_path('app/private/temporary/backups')), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($base);

        $dir = $base.DIRECTORY_SEPARATOR.$prefix.'_'.Str::uuid()->toString();
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function cleanupTempWorkspace(string $directory): void
    {
        if ($directory !== '' && is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }

    /**
     * Menyimpan file arsip dari temporary workspace ke disk privat (serta replikasi remote jika aktif).
     *
     * @return array{disk: string, file_path: string, file_size: int, checksum_sha256: string, remote_disk: ?string, remote_path: ?string, remote_error: ?string}
     */
    public function storeArchive(string $sourceFilePath, string $relativeTargetPath, bool $replicateRemote = false): array
    {
        $safeRelative = $this->assertSafeRelativePath($relativeTargetPath);
        $diskName = $this->localDiskName();
        $disk = $this->localDisk();

        $checksum = $this->computeSha256($sourceFilePath);
        $fileSize = (int) filesize($sourceFilePath);

        $directory = dirname($safeRelative);
        if ($directory !== '.' && $directory !== '') {
            $disk->makeDirectory($directory);
        }

        $stream = @fopen($sourceFilePath, 'rb');
        if ($stream === false) {
            throw new BackupException('Gagal membaca arsip temporary untuk penyimpanan permanen.');
        }

        try {
            $written = $disk->writeStream($safeRelative, $stream);
            if (! $written) {
                throw new BackupException('Gagal menyimpan arsip backup ke storage disk.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $remoteDiskName = null;
        $remotePath = null;
        $remoteError = null;

        if ($replicateRemote && $this->remoteDiskName() !== null) {
            try {
                $remote = $this->remoteDisk();
                $prefix = trim((string) config('backup.remote_prefix', 'melarosa-backups'), '/');
                $targetRemotePath = $prefix !== '' ? "{$prefix}/{$safeRelative}" : $safeRelative;
                $readStream = $disk->readStream($safeRelative);
                if ($readStream !== false && $remote !== null) {
                    try {
                        $remote->writeStream($targetRemotePath, $readStream);
                        $remoteDiskName = $this->remoteDiskName();
                        $remotePath = $targetRemotePath;
                    } finally {
                        if (is_resource($readStream)) {
                            fclose($readStream);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $remoteError = 'Replikasi remote storage gagal: '.$e->getMessage();
            }
        }

        return [
            'disk' => $diskName,
            'file_path' => $safeRelative,
            'file_size' => $fileSize,
            'checksum_sha256' => $checksum,
            'remote_disk' => $remoteDiskName,
            'remote_path' => $remotePath,
            'remote_error' => $remoteError,
        ];
    }

    /**
     * Menyalin arsip backup ke direktori temporary agar aman diproses tanpa mengubah file sumber.
     */
    public function materializeToTemp(BackupHistory $backup, string $tempWorkspace): string
    {
        if (! $backup->file_path) {
            throw new BackupException('Path file backup belum tersedia.');
        }

        $safeRelative = $this->assertSafeRelativePath($backup->file_path);
        $disk = Storage::disk($backup->disk ?: $this->localDiskName());
        $tempFile = $tempWorkspace.DIRECTORY_SEPARATOR.basename($backup->file_name ?: $safeRelative);

        $sourceStream = null;
        if ($disk->exists($safeRelative)) {
            $sourceStream = $disk->readStream($safeRelative);
        } elseif ($backup->remote_disk && $backup->remote_path) {
            $remote = Storage::disk($backup->remote_disk);
            if ($remote->exists($backup->remote_path)) {
                $sourceStream = $remote->readStream($backup->remote_path);
            }
        }

        if (! is_resource($sourceStream)) {
            throw new BackupException('File arsip backup tidak ditemukan di local maupun remote storage.');
        }

        $dest = @fopen($tempFile, 'wb');
        if ($dest === false) {
            fclose($sourceStream);
            throw new BackupException('Gagal menyiapkan file kerja temporary untuk verifikasi arsip.');
        }

        try {
            stream_copy_to_stream($sourceStream, $dest);
        } finally {
            fclose($sourceStream);
            fclose($dest);
        }

        return $tempFile;
    }

    public function openReadableStream(BackupHistory $backup)
    {
        if (! $backup->file_path) {
            throw new BackupException('File backup tidak tersedia untuk diunduh.');
        }

        $safeRelative = $this->assertSafeRelativePath($backup->file_path);
        $disk = Storage::disk($backup->disk ?: $this->localDiskName());

        if ($disk->exists($safeRelative)) {
            $stream = $disk->readStream($safeRelative);
            if (is_resource($stream)) {
                return $stream;
            }
        }

        if ($backup->remote_disk && $backup->remote_path) {
            $remote = Storage::disk($backup->remote_disk);
            if ($remote->exists($backup->remote_path)) {
                $stream = $remote->readStream($backup->remote_path);
                if (is_resource($stream)) {
                    return $stream;
                }
            }
        }

        throw new BackupException('File arsip backup tidak ditemukan pada storage.');
    }

    public function deleteBackupFiles(BackupHistory $backup): void
    {
        if ($backup->file_path) {
            $safeRelative = $this->assertSafeRelativePath($backup->file_path);
            $disk = Storage::disk($backup->disk ?: $this->localDiskName());
            if ($disk->exists($safeRelative)) {
                $disk->delete($safeRelative);
            }
        }

        if ($backup->remote_disk && $backup->remote_path) {
            try {
                $remote = Storage::disk($backup->remote_disk);
                if ($remote->exists($backup->remote_path)) {
                    $remote->delete($backup->remote_path);
                }
            } catch (\Throwable) {
                // Tetap lanjut menghapus entri jika remote sudah tidak tersedia
            }
        }
    }

    /**
     * Mengukur kapasitas nyata disk storage lokal yang menampung direktori backup.
     *
     * @return array<string, mixed>
     */
    public function getDiskMetrics(): array
    {
        $diskName = $this->localDiskName();
        $diskConfig = config("filesystems.disks.{$diskName}", []);
        $rootPath = (string) ($diskConfig['root'] ?? storage_path('app/private/backups'));

        File::ensureDirectoryExists($rootPath);

        $isWritable = is_writable($rootPath);
        $freeBytes = @disk_free_space($rootPath);
        $totalBytes = @disk_total_space($rootPath);

        $free = is_numeric($freeBytes) ? (int) $freeBytes : null;
        $total = is_numeric($totalBytes) && $totalBytes > 0 ? (int) $totalBytes : null;
        $used = ($total !== null && $free !== null) ? max(0, $total - $free) : null;
        $usedPercent = ($total !== null && $used !== null && $total > 0)
            ? round(($used / $total) * 100, 1)
            : null;

        $warningPercent = (int) config('backup.health.disk_warning_percent', 85);
        $minFreeBytes = (int) config('backup.health.min_free_disk_bytes', 536870912);
        $lowDiskWarning = ($usedPercent !== null && $usedPercent >= $warningPercent)
            || ($free !== null && $free < $minFreeBytes);

        return [
            'disk' => $diskName,
            'root_exists' => is_dir($rootPath),
            'is_writable' => $isWritable,
            'free_bytes' => $free,
            'total_bytes' => $total,
            'used_bytes' => $used,
            'used_percent' => $usedPercent,
            'low_disk_warning' => $lowDiskWarning,
            'remote_disk' => $this->remoteDiskName(),
        ];
    }
}


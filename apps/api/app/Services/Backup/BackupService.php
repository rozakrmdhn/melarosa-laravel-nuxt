<?php

namespace App\Services\Backup;

use App\Models\BackupHistory;
use App\Models\BackupSchedule;
use App\Models\User;
use App\Notifications\BackupStatusNotification;
use App\Services\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Orkestrasi eksekusi backup database PostgreSQL/PostGIS, file storage, full backup,
 * kebijakan retensi, kesehatan sistem, dan pencatatan audit log.
 */
class BackupService
{
    public function __construct(
        private readonly BackupEncryptionService $encryption,
        private readonly BackupStorageService $storage,
        private readonly BackupArchiveBuilder $archiveBuilder,
        private readonly PostgresCliHelper $pgCli,
        private readonly AuditLogService $auditLog,
    ) {}

    public function hasActiveBackupJob(): bool
    {
        return BackupHistory::query()
            ->whereIn('status', BackupHistory::ACTIVE_STATUSES)
            ->exists();
    }

    /**
     * Mendaftarkan antrean backup baru dengan lock atomik untuk mencegah duplikasi job bersamaan.
     *
     * @param  array<string, mixed>  $params
     */
    public function createPendingBackup(array $params, ?User $actor = null): BackupHistory
    {
        $lock = Cache::lock('backup:create_pending', 10);

        return $lock->block(5, function () use ($params, $actor): BackupHistory {
            if ($this->hasActiveBackupJob()) {
                throw new BackupException('Masih terdapat proses backup yang sedang berjalan. Tunggu hingga selesai sebelum membuat job baru.');
            }

            $type = (string) ($params['backup_type'] ?? BackupHistory::TYPE_DATABASE);
            if (! in_array($type, BackupHistory::TYPES, true)) {
                throw new BackupException("Tipe backup '{$type}' tidak valid.");
            }

            $triggerType = (string) ($params['trigger_type'] ?? BackupHistory::TRIGGER_MANUAL);
            $encrypt = (bool) ($params['encrypt'] ?? config('backup.encryption.enabled', true));
            $retentionDays = isset($params['retention_days'])
                ? (int) $params['retention_days']
                : (int) config("backup.retention.{$type}", 14);

            $now = now((string) config('backup.timezone', 'Asia/Jakarta'));
            $timestamp = $now->format('Ymd_His');
            $shortId = substr(Str::uuid()->toString(), 0, 8);
            $ext = $encrypt ? 'zip.enc' : 'zip';
            $fileName = "melarosa_{$type}_{$timestamp}_{$shortId}.{$ext}";

            $dbConfig = $this->pgCli->getConnectionConfig();

            return BackupHistory::create([
                'backup_name' => (string) ($params['backup_name'] ?? "Backup ".ucfirst($type)." {$now->format('d/m/Y H:i')}"),
                'backup_type' => $type,
                'trigger_type' => $triggerType,
                'status' => BackupHistory::STATUS_PENDING,
                'disk' => $this->storage->localDiskName(),
                'file_name' => $fileName,
                'is_encrypted' => $encrypt,
                'encryption_algorithm' => $encrypt ? (string) config('backup.encryption.cipher', 'aes-256-gcm') : null,
                'database_name' => $type === BackupHistory::TYPE_FILE ? null : $dbConfig['database'],
                'includes_files' => in_array($type, [BackupHistory::TYPE_FILE, BackupHistory::TYPE_FULL], true),
                'retention_days' => $retentionDays,
                'created_by' => $actor?->id,
                'metadata' => [
                    'progress' => 0,
                    'current_step' => 'Menunggu antrean worker',
                    'notes' => $params['notes'] ?? null,
                    'replicate_to_remote' => (bool) ($params['replicate_to_remote'] ?? false),
                    'schedule_id' => $params['schedule_id'] ?? null,
                    'is_verified' => false,
                ],
            ]);
        });
    }

    /**
     * Mengeksekusi backup nyata berdasarkan record BackupHistory.
     */
    public function executeBackup(BackupHistory $history): BackupHistory
    {
        $execLock = Cache::lock('backup:execute:'.$history->id, 1800);
        if (! $execLock->get()) {
            throw new BackupException('Job backup ini sedang dieksekusi oleh worker lain.');
        }

        $workspace = $this->storage->createTempWorkspace('bk_'.substr($history->id, 0, 8));
        $startedAt = now();

        try {
            $this->updateProgress($history, BackupHistory::STATUS_PROCESSING, 10, 'Menyiapkan lingkungan kerja backup', [
                'started_at' => $startedAt,
            ]);

            $includeDb = in_array($history->backup_type, [BackupHistory::TYPE_DATABASE, BackupHistory::TYPE_FULL], true);
            $includeFiles = in_array($history->backup_type, [BackupHistory::TYPE_FILE, BackupHistory::TYPE_FULL], true);

            $artifacts = [];
            $dbMeta = null;
            $filesSummary = null;

            if ($includeDb) {
                $this->updateProgress($history, BackupHistory::STATUS_PROCESSING, 30, 'Melakukan dump database PostgreSQL/PostGIS');
                $dbMeta = $this->pgCli->inspectDatabaseMetadata();
                $dumpResult = $this->createDatabaseArtifact($workspace, $dbMeta);
                $artifacts[$dumpResult['entry']] = $dumpResult['path'];
            }

            if ($includeFiles) {
                $this->updateProgress($history, BackupHistory::STATUS_PROCESSING, 55, 'Mengompresi file storage aplikasi');
                $filesZipPath = $workspace.DIRECTORY_SEPARATOR.'files.zip';
                $filesSummary = $this->archiveBuilder->createFilesZip(
                    $filesZipPath,
                    (array) config('backup.files.include_paths', []),
                    (array) config('backup.files.exclude_patterns', [])
                );
                $artifacts['files.zip'] = $filesZipPath;
            }

            $this->updateProgress($history, BackupHistory::STATUS_PROCESSING, 75, 'Membentuk manifest dan mengemas arsip');

            $packageZipPath = $workspace.DIRECTORY_SEPARATOR.'package.zip';
            $manifestData = [
                'backup_id' => $history->id,
                'backup_name' => $history->backup_name,
                'backup_type' => $history->backup_type,
                'trigger_type' => $history->trigger_type,
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
                'timezone' => config('backup.timezone', 'Asia/Jakarta'),
                'created_at' => $startedAt->toIso8601String(),
                'includes_database' => $includeDb,
                'includes_files' => $includeFiles,
                'database' => $dbMeta,
                'files_summary' => $filesSummary,
            ];

            $this->archiveBuilder->createPackageZip($packageZipPath, $artifacts, $manifestData);

            $finalArtifactPath = $packageZipPath;
            if ($history->is_encrypted) {
                $this->updateProgress($history, BackupHistory::STATUS_PROCESSING, 85, 'Mengenkripsi arsip backup (AES-256-GCM)');
                $encryptedPath = $workspace.DIRECTORY_SEPARATOR.'package.zip.enc';
                $this->encryption->encryptFile($packageZipPath, $encryptedPath);
                @unlink($packageZipPath);
                $finalArtifactPath = $encryptedPath;
            }

            $this->updateProgress($history, BackupHistory::STATUS_PROCESSING, 92, 'Menyimpan arsip ke storage privat');

            $tz = (string) config('backup.timezone', 'Asia/Jakarta');
            $subDir = now($tz)->format('Y/m');
            $relativeTargetPath = "{$subDir}/{$history->file_name}";
            $replicateRemote = (bool) data_get($history->metadata, 'replicate_to_remote', false);

            $stored = $this->storage->storeArchive($finalArtifactPath, $relativeTargetPath, $replicateRemote);

            $completedAt = now();
            $retentionDays = $history->retention_days ?: (int) config("backup.retention.{$history->backup_type}", 14);
            $expiresAt = $retentionDays > 0 ? $completedAt->copy()->addDays($retentionDays) : null;

            $existingMeta = $history->metadata ?? [];
            $history->update([
                'status' => BackupHistory::STATUS_COMPLETED,
                'disk' => $stored['disk'],
                'remote_disk' => $stored['remote_disk'],
                'remote_path' => $stored['remote_path'],
                'file_path' => $stored['file_path'],
                'file_size' => $stored['file_size'],
                'checksum_sha256' => $stored['checksum_sha256'],
                'database_name' => $dbMeta['database'] ?? $history->database_name,
                'database_version' => $dbMeta['database_version'] ?? null,
                'postgis_version' => $dbMeta['postgis_version'] ?? null,
                'completed_at' => $completedAt,
                'duration_seconds' => max(0, (int) $startedAt->diffInSeconds($completedAt)),
                'expires_at' => $expiresAt,
                'error_message' => null,
                'metadata' => array_merge($existingMeta, [
                    'progress' => 100,
                    'current_step' => 'Backup selesai',
                    'tables_count' => $dbMeta['tables_count'] ?? 0,
                    'geometry_tables_count' => count($dbMeta['geometry_tables'] ?? []),
                    'extensions' => $dbMeta['extensions'] ?? [],
                    'files_count' => $filesSummary['files_count'] ?? 0,
                    'files_uncompressed_bytes' => $filesSummary['total_bytes'] ?? 0,
                    'remote_warning' => $stored['remote_error'],
                    'is_verified' => true,
                ]),
            ]);

            $history->refresh();
            $this->updateRelatedScheduleStatus($history, 'completed');

            $this->auditLog->log(
                'backup.completed',
                'backups',
                $history,
                "Backup {$history->backup_type} selesai ({$history->file_name})",
                null,
                [
                    'backup_type' => $history->backup_type,
                    'trigger_type' => $history->trigger_type,
                    'file_size' => $history->file_size,
                    'checksum_sha256' => $history->checksum_sha256,
                    'is_encrypted' => $history->is_encrypted,
                ],
                ['duration_seconds' => $history->duration_seconds],
                $history->creator
            );

            $this->notifySuperAdmins(
                'Backup Berhasil Diselesaikan',
                "Backup {$history->backup_type} ({$history->file_name}) berhasil dibuat.",
                'info',
                ['backup_id' => $history->id, 'backup_type' => $history->backup_type]
            );

            return $history;
        } catch (\Throwable $e) {
            $safeMessage = $this->pgCli->sanitizeOutput($e->getMessage());
            $completedAt = now();

            $history->update([
                'status' => BackupHistory::STATUS_FAILED,
                'completed_at' => $completedAt,
                'duration_seconds' => max(0, (int) $startedAt->diffInSeconds($completedAt)),
                'error_message' => mb_substr($safeMessage, 0, 2000),
                'metadata' => array_merge($history->metadata ?? [], [
                    'current_step' => 'Backup gagal',
                ]),
            ]);

            $history->refresh();
            $this->updateRelatedScheduleStatus($history, 'failed');

            $this->auditLog->log(
                'backup.failed',
                'backups',
                $history,
                "Backup {$history->backup_type} gagal: ".mb_substr($safeMessage, 0, 180),
                null,
                ['status' => BackupHistory::STATUS_FAILED],
                ['error' => mb_substr($safeMessage, 0, 500)],
                $history->creator
            );

            $this->notifySuperAdmins(
                'Backup Gagal Dijalankan',
                "Proses backup {$history->backup_type} gagal: ".mb_substr($safeMessage, 0, 220),
                'error',
                ['backup_id' => $history->id, 'backup_type' => $history->backup_type]
            );

            if ($e instanceof BackupException) {
                throw $e;
            }

            throw new BackupException($safeMessage, 0, $e);
        } finally {
            $this->storage->cleanupTempWorkspace($workspace);
            $execLock->release();
        }
    }

    /**
     * Menghapus arsip dan riwayat backup secara aman.
     */
    public function deleteBackup(BackupHistory $backup, ?User $actor = null): void
    {
        if ($backup->isInUseByActiveRestore()) {
            throw new BackupException('Backup tidak dapat dihapus karena sedang digunakan dalam proses restore aktif.');
        }

        $snapshot = [
            'backup_name' => $backup->backup_name,
            'backup_type' => $backup->backup_type,
            'file_name' => $backup->file_name,
            'file_size' => $backup->file_size,
            'checksum_sha256' => $backup->checksum_sha256,
        ];

        $this->storage->deleteBackupFiles($backup);

        $this->auditLog->log(
            'backup.deleted',
            'backups',
            $backup,
            "Menghapus arsip backup {$backup->file_name}",
            $snapshot,
            null,
            null,
            $actor
        );

        $backup->delete();
    }

    /**
     * Menjalankan pembersihan arsip kadaluwarsa sesuai kebijakan retensi per jadwal/backup.
     *
     * @return array{deleted_count: int, freed_bytes: int, skipped_count: int, deleted_ids: list<string>}
     */
    public function cleanupExpiredBackups(?User $actor = null): array
    {
        $now = now();
        $candidates = BackupHistory::query()
            ->where('status', BackupHistory::STATUS_COMPLETED)
            ->where(function ($query) use ($now) {
                $query->where('expires_at', '<=', $now)
                    ->orWhere(function ($q) use ($now) {
                        $q->whereNull('expires_at')
                            ->whereNotNull('retention_days')
                            ->whereNotNull('completed_at')
                            ->where('retention_days', '>', 0)
                            ->where('completed_at', '<=', $now);
                    });
            })
            ->get();

        $deletedCount = 0;
        $freedBytes = 0;
        $skippedCount = 0;
        $deletedIds = [];

        foreach ($candidates as $backup) {
            if ($backup->expires_at === null && $backup->completed_at !== null && $backup->retention_days > 0) {
                $computedExpiry = $backup->completed_at->copy()->addDays($backup->retention_days);
                if ($computedExpiry->isFuture()) {
                    continue;
                }
            }

            if ($backup->isInUseByActiveRestore()) {
                $skippedCount++;
                continue;
            }

            $size = (int) $backup->file_size;
            $id = $backup->id;

            $this->storage->deleteBackupFiles($backup);
            $backup->delete();

            $deletedCount++;
            $freedBytes += $size;
            $deletedIds[] = $id;
        }

        if ($deletedCount > 0) {
            $this->auditLog->log(
                'backup.retention_cleanup',
                'backups',
                null,
                "Retention cleanup menghapus {$deletedCount} arsip backup kadaluwarsa.",
                null,
                [
                    'deleted_count' => $deletedCount,
                    'freed_bytes' => $freedBytes,
                    'skipped_count' => $skippedCount,
                ],
                ['deleted_ids' => $deletedIds],
                $actor
            );
        }

        return [
            'deleted_count' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'skipped_count' => $skippedCount,
            'deleted_ids' => $deletedIds,
        ];
    }

    /**
     * Mengambil statistik riil dashboard, jadwal aktif, kapasitas storage, dan status koneksi database/binary.
     *
     * @return array<string, mixed>
     */
    public function getStatisticsAndHealth(): array
    {
        BackupSchedule::ensureDefaultsExist();

        $totalBackups = BackupHistory::count();
        $completedBackups = BackupHistory::where('status', BackupHistory::STATUS_COMPLETED)->count();
        $failedBackups = BackupHistory::where('status', BackupHistory::STATUS_FAILED)->count();
        $activeJobs = BackupHistory::whereIn('status', BackupHistory::ACTIVE_STATUSES)->count();
        $totalBackupSizeBytes = (int) BackupHistory::where('status', BackupHistory::STATUS_COMPLETED)->sum('file_size');

        $lastCompleted = BackupHistory::query()
            ->where('status', BackupHistory::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->first();

        $nextSchedule = BackupSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->first();

        $diskMetrics = $this->storage->getDiskMetrics();

        $dbHealthy = false;
        $dbMeta = null;
        $dbError = null;
        try {
            $dbMeta = $this->pgCli->inspectDatabaseMetadata();
            $dbHealthy = true;
        } catch (\Throwable $e) {
            $dbError = $this->pgCli->sanitizeOutput($e->getMessage());
        }

        $pgDumpBin = $this->pgCli->resolveBinary('pg_dump');
        $pgRestoreBin = $this->pgCli->resolveBinary('pg_restore');
        $encryptionConfigured = $this->encryption->isConfigured();

        $overdueSchedules = $this->detectOverdueSchedules();

        return [
            'summary' => [
                'total_backups' => $totalBackups,
                'completed_backups' => $completedBackups,
                'failed_backups' => $failedBackups,
                'active_jobs' => $activeJobs,
                'total_archive_size_bytes' => $totalBackupSizeBytes,
                'last_backup' => $lastCompleted ? [
                    'id' => $lastCompleted->id,
                    'backup_name' => $lastCompleted->backup_name,
                    'backup_type' => $lastCompleted->backup_type,
                    'status' => $lastCompleted->status,
                    'file_size' => $lastCompleted->file_size,
                    'completed_at' => $lastCompleted->completed_at?->toIso8601String(),
                    'is_encrypted' => $lastCompleted->is_encrypted,
                ] : null,
                'next_schedule' => $nextSchedule ? [
                    'id' => $nextSchedule->id,
                    'name' => $nextSchedule->name,
                    'backup_type' => $nextSchedule->backup_type,
                    'frequency' => $nextSchedule->frequency,
                    'run_time' => $nextSchedule->run_time,
                    'timezone' => $nextSchedule->timezone,
                    'next_run_at' => $nextSchedule->next_run_at?->toIso8601String(),
                ] : null,
            ],
            'storage' => $diskMetrics,
            'health' => [
                'database_connected' => $dbHealthy,
                'database_driver' => $dbMeta['driver'] ?? null,
                'database_name' => $dbMeta['database'] ?? null,
                'database_version' => $dbMeta['database_version'] ?? null,
                'postgis_version' => $dbMeta['postgis_version'] ?? null,
                'database_error' => $dbError,
                'pg_dump_available' => $pgDumpBin !== null,
                'pg_restore_available' => $pgRestoreBin !== null,
                'encryption_enabled' => (bool) config('backup.encryption.enabled', true),
                'encryption_key_configured' => $encryptionConfigured,
                'overdue_schedules_count' => $overdueSchedules->count(),
                'overdue_schedules' => $overdueSchedules->map(fn (BackupSchedule $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'backup_type' => $s->backup_type,
                    'next_run_at' => $s->next_run_at?->toIso8601String(),
                ])->values()->all(),
                'overall_status' => ($dbHealthy && $diskMetrics['is_writable'] && (! config('backup.encryption.enabled', true) || $encryptionConfigured) && ! $diskMetrics['low_disk_warning'] && $overdueSchedules->isEmpty())
                    ? 'healthy'
                    : 'warning',
            ],
        ];
    }

    /**
     * Mengambil daftar jadwal aktif yang sudah memasuki waktu eksekusi.
     *
     * @return Collection<int, BackupSchedule>
     */
    public function getDueSchedules(): Collection
    {
        BackupSchedule::ensureDefaultsExist();
        $now = now();

        return BackupSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('backup_type')
            ->get();
    }

    /**
     * Memeriksa alert kesehatan backup (jadwal terlewat & disk hampir penuh) untuk notifikasi admin.
     *
     * @return array<string, mixed>
     */
    public function checkHealthAlerts(): array
    {
        $disk = $this->storage->getDiskMetrics();
        $overdue = $this->detectOverdueSchedules();

        if ($disk['low_disk_warning']) {
            $this->notifySuperAdmins(
                'Peringatan Kapasitas Storage Backup',
                'Kapasitas disk penyimpanan backup mendekati batas aman (terpakai '.($disk['used_percent'] ?? '?').'%).',
                'warning',
                ['storage' => $disk]
            );
        }

        if ($overdue->isNotEmpty()) {
            $names = $overdue->pluck('name')->implode(', ');
            $this->notifySuperAdmins(
                'Peringatan Jadwal Backup Terlewat',
                "Jadwal backup berikut melewati waktu eksekusi tanpa penyelesaian terbaru: {$names}.",
                'warning',
                ['overdue_schedule_ids' => $overdue->pluck('id')->all()]
            );
        }

        return [
            'low_disk_warning' => $disk['low_disk_warning'],
            'overdue_count' => $overdue->count(),
        ];
    }

    /**
     * @return Collection<int, BackupSchedule>
     */
    private function detectOverdueSchedules(): Collection
    {
        $graceMinutes = max(15, (int) config('backup.health.schedule_grace_minutes', 120));
        $cutoff = now()->subMinutes($graceMinutes);

        return BackupSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<', $cutoff)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $dbMeta
     * @return array{entry: string, path: string}
     */
    private function createDatabaseArtifact(string $workspace, array $dbMeta): array
    {
        $driver = (string) ($dbMeta['driver'] ?? '');
        $connCfg = $this->pgCli->getConnectionConfig();

        if ($driver === 'pgsql') {
            $pgDump = $this->pgCli->getBinaryOrFail('pg_dump');
            $dumpPath = $workspace.DIRECTORY_SEPARATOR.'database.dump';

            $args = [
                $pgDump,
                '-Fc',
                '--no-owner',
                '--no-acl',
                '-h',
                $connCfg['host'],
                '-p',
                $connCfg['port'],
                '-U',
                $connCfg['username'],
                '-d',
                $connCfg['database'],
                '-f',
                $dumpPath,
            ];

            $this->pgCli->runProcess($args, $connCfg);

            if (! is_file($dumpPath) || filesize($dumpPath) < 16) {
                throw new BackupException('Hasil dump database pg_dump tidak valid atau kosong.');
            }

            $magic = @file_get_contents($dumpPath, false, null, 0, 5);
            if ($magic !== 'PGDMP') {
                throw new BackupException('Header file hasil pg_dump bukan format PostgreSQL custom archive (PGDMP).');
            }

            return ['entry' => 'database.dump', 'path' => $dumpPath];
        }

        // Hanya dipakai saat Pest Feature HTTP test berjalan dengan koneksi SQLite in-memory.
        if (app()->runningUnitTests() && $driver === 'sqlite') {
            return $this->exportSqliteSnapshotForTests($workspace);
        }

        throw new BackupException("Driver database '{$driver}' tidak didukung untuk backup database. Gunakan PostgreSQL.");
    }

    /**
     * Mengekspor skema dan isi tabel nyata dari koneksi SQLite khusus konteks unit testing.
     *
     * @return array{entry: string, path: string}
     */
    private function exportSqliteSnapshotForTests(string $workspace): array
    {
        $connName = (string) config('backup.connection', config('database.default'));
        $conn = DB::connection($connName);
        $pdo = $conn->getPdo();
        $sqlPath = $workspace.DIRECTORY_SEPARATOR.'database.sql';

        $lines = ["-- Melarosa SQLite test snapshot\n"];
        $tables = $conn->select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        foreach ($tables as $table) {
            if (! is_string($table->sql)) {
                continue;
            }
            $lines[] = $table->sql.";\n";
            $rows = $conn->table($table->name)->get();
            foreach ($rows as $row) {
                $arr = (array) $row;
                $cols = implode(', ', array_map(fn ($c) => '"'.$c.'"', array_keys($arr)));
                $vals = implode(', ', array_map(function ($v) use ($pdo) {
                    if ($v === null) {
                        return 'NULL';
                    }
                    if (is_bool($v)) {
                        return $v ? '1' : '0';
                    }
                    if (is_int($v) || is_float($v)) {
                        return (string) $v;
                    }

                    return $pdo->quote((string) $v);
                }, array_values($arr)));
                $lines[] = "INSERT INTO \"{$table->name}\" ({$cols}) VALUES ({$vals});\n";
            }
        }

        file_put_contents($sqlPath, implode('', $lines));

        return ['entry' => 'database.sql', 'path' => $sqlPath];
    }

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    private function updateProgress(BackupHistory $history, string $status, int $percent, string $step, array $extraAttributes = []): void
    {
        $meta = array_merge($history->metadata ?? [], [
            'progress' => $percent,
            'current_step' => $step,
        ]);

        $history->update(array_merge($extraAttributes, [
            'status' => $status,
            'metadata' => $meta,
        ]));
    }

    private function updateRelatedScheduleStatus(BackupHistory $history, string $status): void
    {
        $scheduleId = data_get($history->metadata, 'schedule_id');
        if (! is_string($scheduleId) || $scheduleId === '') {
            return;
        }

        $schedule = BackupSchedule::find($scheduleId);
        if ($schedule === null) {
            return;
        }

        $schedule->update([
            'last_run_at' => now(),
            'last_status' => $status,
            'next_run_at' => $schedule->computeNextRunAt(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifySuperAdmins(string $title, string $message, string $severity = 'info', array $context = []): void
    {
        try {
            $admins = User::role('admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new BackupStatusNotification($title, $message, $severity, $context));
            }
        } catch (\Throwable) {
            // Kegagalan pengiriman notifikasi tidak boleh menggagalkan pencatatan status backup utama
        }
    }
}


<?php

namespace App\Services\Backup;

use App\Models\BackupHistory;
use App\Models\BackupRestoreHistory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Validasi arsip backup, pengunggahan backup eksternal, dan eksekusi restore
 * database PostgreSQL/PostGIS serta file storage dengan pengamanan berlapis.
 */
class RestoreService
{
    public function __construct(
        private readonly BackupEncryptionService $encryption,
        private readonly BackupStorageService $storage,
        private readonly BackupArchiveBuilder $archiveBuilder,
        private readonly PostgresCliHelper $pgCli,
        private readonly BackupService $backupService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function hasActiveRestoreJob(): bool
    {
        return BackupRestoreHistory::query()
            ->whereIn('status', BackupRestoreHistory::ACTIVE_STATUSES)
            ->exists();
    }

    /**
     * Memeriksa integritas arsip, checksum SHA-256, dekripsi, manifest, dan kompatibilitas versi PostgreSQL/PostGIS.
     *
     * @return array<string, mixed>
     */
    public function validateBackupSource(BackupHistory $backup): array
    {
        if ($backup->status !== BackupHistory::STATUS_COMPLETED) {
            throw new BackupException('Hanya arsip backup berstatus completed yang dapat divalidasi untuk restore.');
        }

        $workspace = $this->storage->createTempWorkspace('val_'.substr($backup->id, 0, 8));

        try {
            $materializedPath = $this->storage->materializeToTemp($backup, $workspace);
            $actualChecksum = $this->storage->computeSha256($materializedPath);
            $checksumVerified = filled($backup->checksum_sha256)
                ? hash_equals(strtolower((string) $backup->checksum_sha256), strtolower($actualChecksum))
                : true;

            $warnings = [];
            $errors = [];

            if (! $checksumVerified) {
                $errors[] = 'Checksum SHA-256 file arsip tidak cocok dengan catatan metadata (kemungkinan korup atau dimodifikasi).';
            }

            $decryptionVerified = null;
            $payloadPath = $materializedPath;

            if ($backup->is_encrypted || $this->encryption->isEncryptedFile($materializedPath)) {
                try {
                    $decryptedPath = $workspace.DIRECTORY_SEPARATOR.'decrypted_payload.bin';
                    $this->encryption->decryptFile($materializedPath, $decryptedPath);
                    $decryptionVerified = true;
                    $payloadPath = $decryptedPath;
                } catch (BackupException $e) {
                    $decryptionVerified = false;
                    $errors[] = $e->getMessage();
                }
            }

            $archiveFormat = null;
            $manifest = null;
            $internalChecksumsVerified = null;
            $hasDatabaseDump = false;
            $hasFilesZip = false;
            $tablesCount = data_get($backup->metadata, 'tables_count');
            $filesCount = data_get($backup->metadata, 'files_count');

            if (empty($errors)) {
                $magic = @file_get_contents($payloadPath, false, null, 0, 5) ?: '';
                if (str_starts_with($magic, 'PK')) {
                    $archiveFormat = 'melarosa-zip';
                    $inspection = $this->archiveBuilder->inspectAndVerifyPackage($payloadPath);
                    $manifest = $inspection['manifest'];
                    $internalChecksumsVerified = $inspection['checksums_verified'];
                    $hasDatabaseDump = $inspection['has_database_dump'];
                    $hasFilesZip = $inspection['has_files_zip'];

                    if (! $internalChecksumsVerified) {
                        $errors[] = 'Verifikasi checksum internal arsip (checksums.sha256) gagal.';
                    }
                    if (is_array($manifest)) {
                        $tablesCount = data_get($manifest, 'database.tables_count', $tablesCount);
                        $filesCount = data_get($manifest, 'files_summary.files_count', $filesCount);
                    }
                } elseif ($magic === 'PGDMP') {
                    $archiveFormat = 'pg-custom-dump';
                    $hasDatabaseDump = true;
                    $internalChecksumsVerified = true;
                    if ($this->pgCli->resolveBinary('pg_restore') !== null) {
                        $pgRestore = $this->pgCli->getBinaryOrFail('pg_restore');
                        $cfg = $this->pgCli->getConnectionConfig();
                        $this->pgCli->runProcess([$pgRestore, '-l', $payloadPath], $cfg);
                    }
                } else {
                    $errors[] = 'Format isi arsip backup tidak dikenali (bukan paket Melarosa ZIP atau PostgreSQL PGDMP).';
                }
            }

            $serverMeta = null;
            $compatible = true;
            try {
                $serverMeta = $this->pgCli->inspectDatabaseMetadata();
            } catch (\Throwable $e) {
                $warnings[] = 'Metadata server database target tidak dapat diperiksa: '.$this->pgCli->sanitizeOutput($e->getMessage());
            }

            $sourcePgVersion = data_get($manifest, 'database.database_version', $backup->database_version);
            $sourcePostgisVersion = data_get($manifest, 'database.postgis_version', $backup->postgis_version);
            $serverPgVersion = $serverMeta['database_version'] ?? null;
            $serverPostgisVersion = $serverMeta['postgis_version'] ?? null;

            $sourceMajor = $this->extractMajorVersion($sourcePgVersion);
            $serverMajor = $this->extractMajorVersion($serverPgVersion);
            if ($sourceMajor !== null && $serverMajor !== null && $sourceMajor > $serverMajor) {
                $compatible = false;
                $errors[] = "Versi PostgreSQL sumber ({$sourcePgVersion}) lebih tinggi dari server target ({$serverPgVersion}).";
            }

            if ($sourcePostgisVersion !== null && ($serverMeta['driver'] ?? null) === 'pgsql' && $serverPostgisVersion === null) {
                $compatible = false;
                $errors[] = 'Backup mengandung data spasial PostGIS namun ekstensi PostGIS belum terpasang di server target.';
            }

            $valid = empty($errors) && $checksumVerified && ($decryptionVerified !== false);

            return [
                'valid' => $valid,
                'compatible' => $compatible,
                'checksum_verified' => $checksumVerified,
                'actual_checksum_sha256' => $actualChecksum,
                'decryption_verified' => $decryptionVerified,
                'internal_checksums_verified' => $internalChecksumsVerified,
                'archive_format' => $archiveFormat,
                'backup_type' => $backup->backup_type,
                'has_database_dump' => $hasDatabaseDump,
                'has_files_zip' => $hasFilesZip,
                'source_pg_version' => $sourcePgVersion,
                'source_postgis_version' => $sourcePostgisVersion,
                'server_pg_version' => $serverPgVersion,
                'server_postgis_version' => $serverPostgisVersion,
                'tables_count' => $tablesCount,
                'files_count' => $filesCount,
                'warnings' => $warnings,
                'errors' => $errors,
            ];
        } finally {
            $this->storage->cleanupTempWorkspace($workspace);
        }
    }

    /**
     * Memvalidasi file backup eksternal yang diunggah administrator dan mendaftarkannya ke backup_histories.
     */
    public function registerUploadedBackup(UploadedFile $file, User $actor, ?string $notes = null): BackupHistory
    {
        $originalName = basename((string) $file->getClientOriginalName());
        $lowerName = strtolower($originalName);
        $allowedExt = (array) config('backup.upload.allowed_extensions', ['enc', 'zip', 'dump']);
        $matchesExt = false;
        foreach ($allowedExt as $ext) {
            if (str_ends_with($lowerName, '.'.strtolower($ext))) {
                $matchesExt = true;
                break;
            }
        }

        if (! $matchesExt) {
            throw new BackupException('Ekstensi file backup tidak didukung. Gunakan .zip.enc, .enc, .zip, atau .dump.');
        }

        $workspace = $this->storage->createTempWorkspace('upl');

        try {
            $tempUploaded = $workspace.DIRECTORY_SEPARATOR.'uploaded_input.bin';
            if (! copy($file->getRealPath(), $tempUploaded)) {
                throw new BackupException('Gagal menyalin file backup yang diunggah ke ruang kerja validasi.');
            }

            $isEncrypted = $this->encryption->isEncryptedFile($tempUploaded);
            $payloadPath = $tempUploaded;

            if ($isEncrypted) {
                $decryptedPath = $workspace.DIRECTORY_SEPARATOR.'decrypted_upload.bin';
                $this->encryption->decryptFile($tempUploaded, $decryptedPath);
                $payloadPath = $decryptedPath;
            }

            $magic = @file_get_contents($payloadPath, false, null, 0, 5) ?: '';
            $backupType = BackupHistory::TYPE_DATABASE;
            $includesFiles = false;
            $dbName = null;
            $dbVersion = null;
            $postgisVersion = null;
            $extraMeta = [
                'uploaded_original_name' => Str::limit($originalName, 180, ''),
                'uploaded_by' => $actor->id,
                'notes' => $notes,
                'is_verified' => true,
            ];

            if (str_starts_with($magic, 'PK')) {
                $inspection = $this->archiveBuilder->inspectAndVerifyPackage($payloadPath);
                if (! $inspection['checksums_verified']) {
                    throw new BackupException('Checksum internal arsip upload tidak cocok atau file korup.');
                }

                $manifest = $inspection['manifest'] ?? [];
                $hasDb = $inspection['has_database_dump'];
                $hasFiles = $inspection['has_files_zip'];

                if (! $hasDb && ! $hasFiles) {
                    throw new BackupException('Arsip ZIP tidak berisi database.dump maupun files.zip yang valid.');
                }

                $backupType = match (true) {
                    $hasDb && $hasFiles => BackupHistory::TYPE_FULL,
                    $hasFiles => BackupHistory::TYPE_FILE,
                    default => BackupHistory::TYPE_DATABASE,
                };
                $includesFiles = $hasFiles;
                $dbName = data_get($manifest, 'database.database');
                $dbVersion = data_get($manifest, 'database.database_version');
                $postgisVersion = data_get($manifest, 'database.postgis_version');
                $extraMeta['tables_count'] = data_get($manifest, 'database.tables_count');
                $extraMeta['files_count'] = data_get($manifest, 'files_summary.files_count');
            } elseif ($magic === 'PGDMP') {
                if ($this->pgCli->resolveBinary('pg_restore') !== null) {
                    $pgRestore = $this->pgCli->getBinaryOrFail('pg_restore');
                    $cfg = $this->pgCli->getConnectionConfig();
                    $this->pgCli->runProcess([$pgRestore, '-l', $payloadPath], $cfg);
                }
                $backupType = BackupHistory::TYPE_DATABASE;
            } else {
                throw new BackupException('File upload bukan arsip backup Melarosa atau dump PostgreSQL yang valid.');
            }

            $extSuffix = match (true) {
                $isEncrypted && str_starts_with($magic, 'PK') => 'zip.enc',
                $isEncrypted => 'dump.enc',
                str_starts_with($magic, 'PK') => 'zip',
                default => 'dump',
            };

            $safeName = 'upload_'.now((string) config('backup.timezone', 'Asia/Jakarta'))->format('Ymd_His').'_'.substr(Str::uuid()->toString(), 0, 8).'.'.$extSuffix;
            $relativeTarget = 'uploads/'.now()->format('Y/m').'/'.$safeName;

            $stored = $this->storage->storeArchive($tempUploaded, $relativeTarget, false);

            $history = BackupHistory::create([
                'backup_name' => 'Upload Eksternal: '.pathinfo($originalName, PATHINFO_FILENAME),
                'backup_type' => $backupType,
                'trigger_type' => BackupHistory::TRIGGER_UPLOADED,
                'status' => BackupHistory::STATUS_COMPLETED,
                'disk' => $stored['disk'],
                'file_path' => $stored['file_path'],
                'file_name' => $safeName,
                'file_size' => $stored['file_size'],
                'checksum_sha256' => $stored['checksum_sha256'],
                'is_encrypted' => $isEncrypted,
                'encryption_algorithm' => $isEncrypted ? (string) config('backup.encryption.cipher', 'aes-256-gcm') : null,
                'database_name' => $dbName,
                'database_version' => $dbVersion,
                'postgis_version' => $postgisVersion,
                'includes_files' => $includesFiles,
                'started_at' => now(),
                'completed_at' => now(),
                'duration_seconds' => 0,
                'retention_days' => (int) config("backup.retention.{$backupType}", 14),
                'expires_at' => now()->addDays((int) config("backup.retention.{$backupType}", 14)),
                'metadata' => $extraMeta,
                'created_by' => $actor->id,
            ]);

            $this->auditLog->log(
                'backup.uploaded',
                'backups',
                $history,
                "Mengunggah dan memvalidasi arsip backup eksternal ({$safeName})",
                null,
                [
                    'original_name' => $extraMeta['uploaded_original_name'],
                    'backup_type' => $backupType,
                    'is_encrypted' => $isEncrypted,
                    'checksum_sha256' => $stored['checksum_sha256'],
                ],
                null,
                $actor
            );

            return $history;
        } finally {
            $this->storage->cleanupTempWorkspace($workspace);
        }
    }

    /**
     * Mendaftarkan job restore setelah seluruh validasi keamanan, scope, dan target terpenuhi.
     *
     * @param  array<string, mixed>  $options
     */
    public function createPendingRestore(BackupHistory $backup, array $options, ?User $actor = null): BackupRestoreHistory
    {
        $lock = Cache::lock('backup:create_restore', 10);

        return $lock->block(5, function () use ($backup, $options, $actor): BackupRestoreHistory {
            if ($this->hasActiveRestoreJob() || $this->backupService->hasActiveBackupJob()) {
                throw new BackupException('Masih ada proses backup atau restore lain yang sedang berlangsung.');
            }

            if ($backup->status !== BackupHistory::STATUS_COMPLETED) {
                throw new BackupException('Backup yang dipilih belum berstatus selesai/siap.');
            }

            $scope = (string) ($options['restore_scope'] ?? $backup->backup_type);
            if (! in_array($scope, [BackupHistory::TYPE_DATABASE, BackupHistory::TYPE_FILE, BackupHistory::TYPE_FULL], true)) {
                throw new BackupException('Scope restore tidak valid.');
            }

            if ($backup->backup_type === BackupHistory::TYPE_FILE && $scope !== BackupHistory::TYPE_FILE) {
                throw new BackupException('Backup bertipe file tidak dapat digunakan untuk restore database.');
            }
            if ($backup->backup_type === BackupHistory::TYPE_DATABASE && $scope !== BackupHistory::TYPE_DATABASE) {
                throw new BackupException('Backup bertipe database tidak memiliki arsip file storage.');
            }

            $connCfg = $this->pgCli->getConnectionConfig();
            $activeDb = $connCfg['database'];
            $targetMode = (string) ($options['target_mode'] ?? BackupRestoreHistory::TARGET_MODE_ISOLATED);

            if (! in_array($targetMode, [BackupRestoreHistory::TARGET_MODE_ISOLATED, BackupRestoreHistory::TARGET_MODE_ACTIVE], true)) {
                throw new BackupException('Target mode restore tidak valid.');
            }

            $targetDb = null;
            if (in_array($scope, [BackupHistory::TYPE_DATABASE, BackupHistory::TYPE_FULL], true)) {
                if ($targetMode === BackupRestoreHistory::TARGET_MODE_ACTIVE) {
                    if (! config('backup.restore.allow_overwrite_active_db', false)) {
                        throw new BackupException(
                            'Restore langsung menimpa database aktif diblokir oleh kebijakan keamanan. Gunakan target terisolasi atau aktifkan BACKUP_RESTORE_ALLOW_OVERWRITE_ACTIVE_DB saat maintenance.'
                        );
                    }
                    $targetDb = $activeDb;
                } else {
                    $requestedTarget = (string) ($options['target_database'] ?? 'melarosa_restore_verify');
                    $targetDb = $this->pgCli->assertValidIdentifier($requestedTarget);
                    if ($targetDb === $activeDb) {
                        throw new BackupException('Target database terisolasi tidak boleh sama dengan database aktif.');
                    }
                }
            }

            $validation = $this->validateBackupSource($backup);
            if (! ($validation['valid'] ?? false)) {
                $reason = implode(' ', (array) ($validation['errors'] ?? ['Validasi arsip gagal.']));
                throw new BackupException("Restore dibatalkan karena validasi arsip gagal: {$reason}");
            }

            return BackupRestoreHistory::create([
                'backup_history_id' => $backup->id,
                'source_file_name' => $backup->file_name,
                'restore_scope' => $scope,
                'target_database' => $targetDb,
                'target_mode' => $targetMode,
                'status' => BackupRestoreHistory::STATUS_PENDING,
                'validation_report' => [
                    'preflight' => $validation,
                    'create_safety_backup' => (bool) ($options['create_safety_backup'] ?? config('backup.restore.create_safety_backup', true)),
                ],
                'executed_by' => $actor?->id,
            ]);
        });
    }

    /**
     * Mengeksekusi proses restore dengan preflight verification, safety backup, pg_restore, dan verifikasi PostGIS.
     */
    public function executeRestore(BackupRestoreHistory $restoreHistory): BackupRestoreHistory
    {
        $lock = Cache::lock('backup:execute_restore:'.$restoreHistory->id, 1800);
        if (! $lock->get()) {
            throw new BackupException('Proses restore ini sedang dieksekusi oleh worker lain.');
        }

        $workspace = $this->storage->createTempWorkspace('rst_'.substr($restoreHistory->id, 0, 8));
        $startedAt = now();

        try {
            $backup = $restoreHistory->backupHistory;
            if ($backup === null) {
                throw new BackupException('Record sumber backup sudah tidak tersedia.');
            }

            $restoreHistory->update([
                'status' => BackupRestoreHistory::STATUS_PROCESSING,
                'started_at' => $startedAt,
            ]);

            $preflight = $this->validateBackupSource($backup);
            if (! ($preflight['valid'] ?? false)) {
                throw new BackupException(implode(' ', (array) ($preflight['errors'] ?? ['Arsip backup tidak valid.'])));
            }

            // Buat pre-restore safety backup otomatis sebelum menyentuh target aktif.
            $shouldCreateSafety = (bool) data_get(
                $restoreHistory->validation_report,
                'create_safety_backup',
                config('backup.restore.create_safety_backup', true)
            );
            if ($restoreHistory->target_mode === BackupRestoreHistory::TARGET_MODE_ACTIVE && $shouldCreateSafety) {
                $safetyPending = $this->backupService->createPendingBackup([
                    'backup_type' => $restoreHistory->restore_scope === BackupHistory::TYPE_FILE
                        ? BackupHistory::TYPE_FILE
                        : BackupHistory::TYPE_FULL,
                    'trigger_type' => BackupHistory::TRIGGER_PRE_RESTORE,
                    'backup_name' => 'Pre-Restore Safety Backup ('.now()->format('d/m/Y H:i').')',
                    'retention_days' => (int) config('backup.retention.pre_restore_safety_days', 30),
                ], $restoreHistory->executor);

                $safetyBackup = $this->backupService->executeBackup($safetyPending);
                $restoreHistory->update(['pre_restore_backup_id' => $safetyBackup->id]);
            }

            $materialized = $this->storage->materializeToTemp($backup, $workspace);
            $payloadPath = $materialized;
            if ($backup->is_encrypted || $this->encryption->isEncryptedFile($materialized)) {
                $payloadPath = $workspace.DIRECTORY_SEPARATOR.'decrypted_restore.bin';
                $this->encryption->decryptFile($materialized, $payloadPath);
            }

            $extractDir = $workspace.DIRECTORY_SEPARATOR.'extracted';
            $magic = @file_get_contents($payloadPath, false, null, 0, 5) ?: '';
            $databaseDumpPath = null;
            $filesZipPath = null;

            if (str_starts_with($magic, 'PK')) {
                $inspection = $this->archiveBuilder->inspectAndVerifyPackage($payloadPath, $extractDir);
                if (! $inspection['checksums_verified']) {
                    throw new BackupException('Checksum internal arsip gagal diverifikasi saat restore.');
                }
                if (is_file($extractDir.DIRECTORY_SEPARATOR.'database.dump')) {
                    $databaseDumpPath = $extractDir.DIRECTORY_SEPARATOR.'database.dump';
                } elseif (is_file($extractDir.DIRECTORY_SEPARATOR.'database.sql')) {
                    $databaseDumpPath = $extractDir.DIRECTORY_SEPARATOR.'database.sql';
                }
                if (is_file($extractDir.DIRECTORY_SEPARATOR.'files.zip')) {
                    $filesZipPath = $extractDir.DIRECTORY_SEPARATOR.'files.zip';
                }
            } elseif ($magic === 'PGDMP') {
                $databaseDumpPath = $payloadPath;
            } else {
                throw new BackupException('Format paket restore tidak valid.');
            }

            $dbReport = null;
            if (in_array($restoreHistory->restore_scope, [BackupHistory::TYPE_DATABASE, BackupHistory::TYPE_FULL], true)) {
                if ($databaseDumpPath === null) {
                    throw new BackupException('Artefak dump database tidak ditemukan di dalam arsip backup.');
                }
                $dbReport = $this->restoreDatabaseArtifact($databaseDumpPath, $restoreHistory);
            }

            $filesReport = null;
            if (in_array($restoreHistory->restore_scope, [BackupHistory::TYPE_FILE, BackupHistory::TYPE_FULL], true)) {
                if ($filesZipPath === null) {
                    throw new BackupException('Artefak files.zip tidak ditemukan di dalam arsip backup.');
                }
                $filesReport = $this->restoreFilesArtifact($filesZipPath, $restoreHistory);
            }

            $completedAt = now();
            $finalReport = array_merge($restoreHistory->validation_report ?? [], [
                'preflight' => $preflight,
                'database_verification' => $dbReport,
                'files_verification' => $filesReport,
                'verified_at' => $completedAt->toIso8601String(),
            ]);

            $restoreHistory->update([
                'status' => BackupRestoreHistory::STATUS_COMPLETED,
                'validation_report' => $finalReport,
                'completed_at' => $completedAt,
                'duration_seconds' => max(0, (int) $startedAt->diffInSeconds($completedAt)),
                'error_message' => null,
            ]);

            $restoreHistory->refresh();

            $this->auditLog->log(
                'backup.restored',
                'backups',
                $restoreHistory,
                "Restore {$restoreHistory->restore_scope} dari {$restoreHistory->source_file_name} selesai ({$restoreHistory->target_mode})",
                null,
                [
                    'restore_scope' => $restoreHistory->restore_scope,
                    'target_mode' => $restoreHistory->target_mode,
                    'target_database' => $restoreHistory->target_database,
                    'pre_restore_backup_id' => $restoreHistory->pre_restore_backup_id,
                ],
                $finalReport,
                $restoreHistory->executor
            );

            $this->backupService->notifySuperAdmins(
                'Proses Restore Backup Selesai',
                "Restore {$restoreHistory->restore_scope} ({$restoreHistory->source_file_name}) ke target {$restoreHistory->target_mode} berhasil diverifikasi.",
                'info',
                ['restore_id' => $restoreHistory->id, 'target_database' => $restoreHistory->target_database]
            );

            return $restoreHistory;
        } catch (\Throwable $e) {
            $safeMessage = $this->pgCli->sanitizeOutput($e->getMessage());
            $completedAt = now();

            $restoreHistory->update([
                'status' => BackupRestoreHistory::STATUS_FAILED,
                'completed_at' => $completedAt,
                'duration_seconds' => max(0, (int) $startedAt->diffInSeconds($completedAt)),
                'error_message' => mb_substr($safeMessage, 0, 2000),
            ]);

            $this->auditLog->log(
                'backup.restore_failed',
                'backups',
                $restoreHistory,
                "Restore gagal: ".mb_substr($safeMessage, 0, 180),
                null,
                ['status' => BackupRestoreHistory::STATUS_FAILED],
                ['error' => mb_substr($safeMessage, 0, 500)],
                $restoreHistory->executor
            );

            $this->backupService->notifySuperAdmins(
                'Proses Restore Backup Gagal',
                "Restore dari {$restoreHistory->source_file_name} gagal: ".mb_substr($safeMessage, 0, 220),
                'error',
                ['restore_id' => $restoreHistory->id]
            );

            if ($e instanceof BackupException) {
                throw $e;
            }

            throw new BackupException($safeMessage, 0, $e);
        } finally {
            $this->storage->cleanupTempWorkspace($workspace);
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreDatabaseArtifact(string $dumpPath, BackupRestoreHistory $restoreHistory): array
    {
        $cfg = $this->pgCli->getConnectionConfig();

        if ($cfg['driver'] === 'pgsql') {
            $targetDb = $this->pgCli->assertValidIdentifier((string) $restoreHistory->target_database);
            $pgRestore = $this->pgCli->getBinaryOrFail('pg_restore');
            $psql = $this->pgCli->getBinaryOrFail('psql');

            if ($restoreHistory->target_mode === BackupRestoreHistory::TARGET_MODE_ISOLATED) {
                $this->ensureIsolatedPostgresDatabase($psql, $cfg, $targetDb);
            }

            $args = [
                $pgRestore,
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-acl',
                '-h',
                $cfg['host'],
                '-p',
                $cfg['port'],
                '-U',
                $cfg['username'],
                '-d',
                $targetDb,
                $dumpPath,
            ];

            $this->pgCli->runProcess($args, $cfg);

            return $this->verifyPostgresPostRestore($cfg, $targetDb);
        }

        if (app()->runningUnitTests() && $cfg['driver'] === 'sqlite' && str_ends_with($dumpPath, '.sql')) {
            return $this->verifySqliteTestRestore($dumpPath, (string) $restoreHistory->target_database);
        }

        throw new BackupException("Driver database '{$cfg['driver']}' tidak mendukung restore pg_restore.");
    }

    /**
     * Menyiapkan database terisolasi yang bersih tanpa menyentuh database aktif aplikasi.
     *
     * @param  array<string, mixed>  $cfg
     */
    private function ensureIsolatedPostgresDatabase(string $psql, array $cfg, string $targetDb): void
    {
        if ($targetDb === $cfg['database']) {
            throw new BackupException('Operasi database terisolasi tidak boleh menargetkan database aktif.');
        }

        // Hapus database preview lama jika sudah ada agar pg_restore mengembalikan kondisi bersih.
        $dropArgs = [
            $psql,
            '-v',
            'ON_ERROR_STOP=1',
            '-h',
            $cfg['host'],
            '-p',
            $cfg['port'],
            '-U',
            $cfg['username'],
            '-d',
            'postgres',
            '-c',
            sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE);', $targetDb),
        ];

        $this->pgCli->runProcess($dropArgs, $cfg);

        $createArgs = [
            $psql,
            '-v',
            'ON_ERROR_STOP=1',
            '-h',
            $cfg['host'],
            '-p',
            $cfg['port'],
            '-U',
            $cfg['username'],
            '-d',
            'postgres',
            '-c',
            sprintf('CREATE DATABASE "%s";', $targetDb),
        ];

        $this->pgCli->runProcess($createArgs, $cfg);
    }

    /**
     * Memverifikasi hasil restore PostgreSQL: daftar tabel, ekstensi PostGIS, dan validitas geometri.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function verifyPostgresPostRestore(array $cfg, string $targetDb): array
    {
        $connName = 'backup_verify_'.Str::random(6);
        $baseCfg = config('database.connections.'.(string) config('backup.connection', 'pgsql'));
        config([
            "database.connections.{$connName}" => array_merge($baseCfg, [
                'database' => $targetDb,
            ]),
        ]);

        try {
            $meta = $this->pgCli->inspectDatabaseMetadata($connName);
            $conn = DB::connection($connName);
            $geometryValidation = [];

            if (config('backup.restore.verify_postgis_geometry', true) && ! empty($meta['geometry_tables'])) {
                $limit = (int) config('backup.restore.max_geometry_sample_tables', 15);
                foreach (array_slice($meta['geometry_tables'], 0, $limit) as $geoTable) {
                    $tableParts = explode('.', (string) $geoTable['table'], 2);
                    if (count($tableParts) !== 2) {
                        continue;
                    }
                    $schema = $this->pgCli->assertValidIdentifier($tableParts[0]);
                    $table = $this->pgCli->assertValidIdentifier($tableParts[1]);
                    $column = $this->pgCli->assertValidIdentifier((string) $geoTable['column']);

                    $stats = $conn->selectOne(sprintf(
                        'SELECT COUNT(*)::int AS total_rows, COUNT(*) FILTER (WHERE "%s" IS NOT NULL AND NOT ST_IsValid("%s"))::int AS invalid_geometries FROM "%s"."%s"',
                        $column,
                        $column,
                        $schema,
                        $table
                    ));

                    $geometryValidation[] = [
                        'table' => "{$schema}.{$table}",
                        'column' => $column,
                        'total_rows' => (int) ($stats?->total_rows ?? 0),
                        'invalid_geometries' => (int) ($stats?->invalid_geometries ?? 0),
                    ];
                }
            }

            return [
                'target_database' => $targetDb,
                'tables_count' => $meta['tables_count'],
                'database_version' => $meta['database_version'],
                'postgis_version' => $meta['postgis_version'],
                'extensions' => $meta['extensions'],
                'geometry_tables_count' => count($meta['geometry_tables'] ?? []),
                'geometry_validation' => $geometryValidation,
            ];
        } finally {
            DB::purge($connName);
            config(["database.connections.{$connName}" => null]);
        }
    }

    /**
     * Memverifikasi dump SQL SQLite hasil test ke database SQLite temporary terpisah.
     *
     * @return array<string, mixed>
     */
    private function verifySqliteTestRestore(string $sqlPath, string $targetLabel): array
    {
        $sql = file_get_contents($sqlPath);
        if ($sql === false || trim($sql) === '') {
            throw new BackupException('Isi file dump SQL uji kosong.');
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec($sql);

        $stmt = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $tablesCount = $stmt ? (int) $stmt->fetchColumn() : 0;

        return [
            'target_database' => $targetLabel,
            'tables_count' => $tablesCount,
            'database_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'postgis_version' => null,
            'geometry_validation' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreFilesArtifact(string $filesZipPath, BackupRestoreHistory $restoreHistory): array
    {
        if ($restoreHistory->target_mode === BackupRestoreHistory::TARGET_MODE_ISOLATED) {
            $previewBase = rtrim((string) config('backup.temp_directory', storage_path('app/private/temporary/backups')), DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR.'restore_preview'.DIRECTORY_SEPARATOR.$restoreHistory->id;
            File::ensureDirectoryExists($previewBase);

            $result = $this->archiveBuilder->extractFilesZipToTargets($filesZipPath, [
                'public' => $previewBase.DIRECTORY_SEPARATOR.'public',
                'private' => $previewBase.DIRECTORY_SEPARATOR.'private',
            ]);

            return [
                'mode' => 'isolated_preview',
                'preview_directory' => 'temporary/backups/restore_preview/'.$restoreHistory->id,
                'restored_files' => $result['restored_files'],
                'skipped_files' => $result['skipped_files'],
            ];
        }

        $includePaths = (array) config('backup.files.include_paths', []);
        $result = $this->archiveBuilder->extractFilesZipToTargets($filesZipPath, $includePaths);

        return [
            'mode' => 'active_storage',
            'restored_files' => $result['restored_files'],
            'skipped_files' => $result['skipped_files'],
        ];
    }

    private function extractMajorVersion(?string $versionString): ?int
    {
        if ($versionString === null || trim($versionString) === '') {
            return null;
        }

        if (preg_match('/^(\d+)/', trim($versionString), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}


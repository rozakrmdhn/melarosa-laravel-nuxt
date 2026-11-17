<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\RestoreBackupRequest;
use App\Http\Requests\Backup\StoreBackupRequest;
use App\Http\Requests\Backup\UpdateBackupScheduleRequest;
use App\Http\Requests\Backup\UploadBackupRequest;
use App\Http\Requests\Backup\ValidateBackupRequest;
use App\Jobs\RunBackupJob;
use App\Jobs\RunRestoreJob;
use App\Models\BackupHistory;
use App\Models\BackupRestoreHistory;
use App\Models\BackupSchedule;
use App\Services\AuditLogService;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStorageService;
use App\Services\Backup\RestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller API untuk pengelolaan Backup & Restore Management (khusus Super Admin).
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly RestoreService $restoreService,
        private readonly BackupStorageService $storageService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(5, min(100, $request->integer('per_page', 15)));
        $search = trim((string) $request->input('search', ''));
        $type = $request->input('backup_type');
        $status = $request->input('status');
        $trigger = $request->input('trigger_type');

        $query = BackupHistory::query()
            ->with('creator:id,name,email')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('backup_name', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('checksum_sha256', 'like', "%{$search}%");
                });
            })
            ->when(filled($type), fn ($q) => $q->where('backup_type', $type))
            ->when(filled($status), fn ($q) => $q->where('status', $status))
            ->when(filled($trigger), fn ($q) => $q->where('trigger_type', $trigger))
            ->when(filled($request->input('date_from')), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when(filled($request->input('date_to')), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->latest('created_at');

        return response()->json([
            'ok' => true,
            'data' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    public function statistics(): JsonResponse
    {
        $stats = $this->backupService->getStatisticsAndHealth();
        $stats['recent_restores_count'] = BackupRestoreHistory::count();
        $stats['last_restore'] = BackupRestoreHistory::query()
            ->latest('created_at')
            ->first(['id', 'source_file_name', 'restore_scope', 'target_mode', 'target_database', 'status', 'completed_at']);

        return response()->json([
            'ok' => true,
            'data' => $stats,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $backup = BackupHistory::query()
            ->with(['creator:id,name,email', 'restoreHistories' => fn ($q) => $q->latest('created_at')->limit(10)])
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $backup,
        ]);
    }

    public function store(StoreBackupRequest $request): JsonResponse
    {
        try {
            $history = $this->backupService->createPendingBackup($request->validated(), $request->user());

            $this->auditLog->log(
                'backup.queued',
                'backups',
                $history,
                "Memicu backup manual bertipe {$history->backup_type}",
                null,
                [
                    'backup_type' => $history->backup_type,
                    'is_encrypted' => $history->is_encrypted,
                    'retention_days' => $history->retention_days,
                ],
                null,
                $request->user()
            );

            RunBackupJob::dispatch($history->id);

            return response()->json([
                'ok' => true,
                'message' => 'Proses backup berhasil masuk ke antrean.',
                'data' => $history->fresh('creator:id,name,email'),
            ], 202);
        } catch (BackupException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function downloadUrl(string $id): JsonResponse
    {
        $backup = BackupHistory::findOrFail($id);
        if ($backup->status !== BackupHistory::STATUS_COMPLETED || ! $backup->file_path) {
            return response()->json([
                'ok' => false,
                'message' => 'Arsip backup belum selesai atau tidak tersedia untuk diunduh.',
            ], 422);
        }

        $ttlMinutes = max(1, (int) config('backup.download.signed_url_ttl_minutes', 10));
        $signedRelativeUrl = URL::temporarySignedRoute(
            'admin.backups.download',
            now()->addMinutes($ttlMinutes),
            ['id' => $backup->id],
            absolute: false
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'url' => $signedRelativeUrl,
                'expires_in_minutes' => $ttlMinutes,
                'file_name' => $backup->file_name,
            ],
        ]);
    }

    public function download(Request $request, string $id): StreamedResponse|JsonResponse
    {
        if (! $request->hasValidSignature(false)) {
            return response()->json([
                'ok' => false,
                'message' => 'Signed URL unduhan backup tidak valid atau sudah kadaluwarsa.',
            ], 403);
        }

        $backup = BackupHistory::findOrFail($id);
        if ($backup->status !== BackupHistory::STATUS_COMPLETED || ! $backup->file_path) {
            return response()->json([
                'ok' => false,
                'message' => 'File backup tidak tersedia.',
            ], 404);
        }

        try {
            $stream = $this->storageService->openReadableStream($backup);
        } catch (BackupException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        $this->auditLog->log(
            'backup.downloaded',
            'backups',
            $backup,
            "Mengunduh arsip backup {$backup->file_name}",
            null,
            ['file_name' => $backup->file_name, 'is_encrypted' => $backup->is_encrypted],
            null,
            $request->user()
        );

        $safeDownloadName = basename($backup->file_name);

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, $safeDownloadName, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $backup = BackupHistory::findOrFail($id);

        try {
            $this->backupService->deleteBackup($backup, $request->user());

            return response()->json([
                'ok' => true,
                'message' => 'Arsip backup berhasil dihapus.',
            ]);
        } catch (BackupException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function schedules(): JsonResponse
    {
        BackupSchedule::ensureDefaultsExist();

        $schedules = BackupSchedule::query()
            ->with('updater:id,name')
            ->get()
            ->sortBy(fn (BackupSchedule $item) => match ($item->backup_type) {
                BackupHistory::TYPE_DATABASE => 1,
                BackupHistory::TYPE_FILE => 2,
                BackupHistory::TYPE_FULL => 3,
                default => 99,
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $schedules,
        ]);
    }

    public function updateSchedules(UpdateBackupScheduleRequest $request): JsonResponse
    {
        BackupSchedule::ensureDefaultsExist();
        $validated = $request->validated();

        $updated = DB::transaction(function () use ($validated, $request) {
            $results = [];

            foreach ($validated['schedules'] as $item) {
                $schedule = BackupSchedule::firstOrNew(['backup_type' => $item['backup_type']]);
                $before = $schedule->exists ? $schedule->only([
                    'is_active', 'frequency', 'cron_expression', 'run_time', 'day_of_week', 'day_of_month',
                    'timezone', 'retention_days', 'encryption_enabled', 'replicate_to_remote',
                ]) : null;

                $schedule->fill([
                    'name' => $item['name'] ?? ($schedule->name ?: 'Jadwal '.ucfirst($item['backup_type'])),
                    'is_active' => (bool) $item['is_active'],
                    'frequency' => $item['frequency'],
                    'cron_expression' => $item['frequency'] === BackupSchedule::FREQUENCY_CRON ? ($item['cron_expression'] ?? null) : null,
                    'run_time' => $item['run_time'],
                    'day_of_week' => $item['day_of_week'] ?? null,
                    'day_of_month' => $item['day_of_month'] ?? null,
                    'timezone' => $item['timezone'] ?? (string) config('backup.timezone', 'Asia/Jakarta'),
                    'retention_days' => (int) $item['retention_days'],
                    'encryption_enabled' => (bool) ($item['encryption_enabled'] ?? true),
                    'replicate_to_remote' => (bool) ($item['replicate_to_remote'] ?? false),
                    'updated_by' => $request->user()?->id,
                ]);

                $schedule->next_run_at = $schedule->is_active ? $schedule->computeNextRunAt() : null;
                $schedule->save();

                $this->auditLog->log(
                    'backup.schedule_updated',
                    'backups',
                    $schedule,
                    "Memperbarui jadwal backup {$schedule->backup_type}",
                    $before,
                    $schedule->only([
                        'is_active', 'frequency', 'cron_expression', 'run_time', 'day_of_week', 'day_of_month',
                        'timezone', 'retention_days', 'encryption_enabled', 'replicate_to_remote',
                    ]),
                    null,
                    $request->user()
                );

                $results[] = $schedule->fresh('updater:id,name');
            }

            return $results;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Konfigurasi jadwal backup berhasil disimpan.',
            'data' => $updated,
        ]);
    }

    public function restores(Request $request): JsonResponse
    {
        $perPage = max(5, min(100, $request->integer('per_page', 10)));

        $restores = BackupRestoreHistory::query()
            ->with([
                'backupHistory:id,backup_name,backup_type',
                'preRestoreBackup:id,backup_name,file_name',
                'executor:id,name,email',
            ])
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'ok' => true,
            'data' => $restores,
        ]);
    }

    public function validateBackup(ValidateBackupRequest $request): JsonResponse
    {
        $backup = BackupHistory::findOrFail($request->validated('backup_id'));

        try {
            $report = $this->restoreService->validateBackupSource($backup);

            return response()->json([
                'ok' => true,
                'data' => $report,
            ]);
        } catch (BackupException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function restore(RestoreBackupRequest $request): JsonResponse
    {
        $backup = BackupHistory::findOrFail($request->validated('backup_id'));

        try {
            $restoreHistory = $this->restoreService->createPendingRestore(
                $backup,
                $request->validated(),
                $request->user()
            );

            $this->auditLog->log(
                'backup.restore_queued',
                'backups',
                $restoreHistory,
                "Menjadwalkan restore {$restoreHistory->restore_scope} dari {$backup->file_name}",
                null,
                [
                    'restore_scope' => $restoreHistory->restore_scope,
                    'target_mode' => $restoreHistory->target_mode,
                    'target_database' => $restoreHistory->target_database,
                ],
                null,
                $request->user()
            );

            RunRestoreJob::dispatch($restoreHistory->id);

            return response()->json([
                'ok' => true,
                'message' => 'Proses restore berhasil dikirim ke antrean.',
                'data' => $restoreHistory->fresh([
                    'backupHistory:id,backup_name,backup_type',
                    'preRestoreBackup:id,backup_name,file_name',
                    'executor:id,name,email',
                ]),
            ], 202);
        } catch (BackupException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function upload(UploadBackupRequest $request): JsonResponse
    {
        try {
            $history = $this->restoreService->registerUploadedBackup(
                $request->file('backup_file'),
                $request->user(),
                $request->input('notes')
            );

            return response()->json([
                'ok' => true,
                'message' => 'File backup eksternal berhasil divalidasi dan didaftarkan.',
                'data' => $history->fresh('creator:id,name,email'),
            ], 201);
        } catch (BackupException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function jobStatus(string $id): JsonResponse
    {
        $backup = BackupHistory::with('creator:id,name,email')->find($id);
        if ($backup !== null) {
            return response()->json([
                'ok' => true,
                'kind' => 'backup',
                'data' => $backup,
            ]);
        }

        $restore = BackupRestoreHistory::with([
            'backupHistory:id,backup_name,backup_type',
            'preRestoreBackup:id,backup_name,file_name',
            'executor:id,name,email',
        ])->find($id);

        if ($restore !== null) {
            return response()->json([
                'ok' => true,
                'kind' => 'restore',
                'data' => $restore,
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => 'Status job backup atau restore tidak ditemukan.',
        ], 404);
    }
}


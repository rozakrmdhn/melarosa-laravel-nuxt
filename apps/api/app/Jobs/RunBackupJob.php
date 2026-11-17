<?php

namespace App\Jobs;

use App\Models\BackupHistory;
use App\Services\Backup\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job antrean asinkron untuk menjalankan proses backup database, file storage, atau full backup.
 */
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $backupHistoryId)
    {
        if ($conn = config('backup.queue.connection')) {
            $this->onConnection((string) $conn);
        }

        $this->onQueue((string) config('backup.queue.name', 'default'));
    }

    public function handle(BackupService $backupService): void
    {
        $history = BackupHistory::find($this->backupHistoryId);
        if ($history === null || $history->status === BackupHistory::STATUS_COMPLETED) {
            return;
        }

        $backupService->executeBackup($history);
    }
}


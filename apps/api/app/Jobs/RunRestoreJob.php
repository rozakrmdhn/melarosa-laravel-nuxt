<?php

namespace App\Jobs;

use App\Models\BackupRestoreHistory;
use App\Services\Backup\RestoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job antrean asinkron untuk menjalankan proses validasi dan restore arsip backup.
 */
class RunRestoreJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $restoreHistoryId)
    {
        if ($conn = config('backup.queue.connection')) {
            $this->onConnection((string) $conn);
        }

        $this->onQueue((string) config('backup.queue.name', 'default'));
    }

    public function handle(RestoreService $restoreService): void
    {
        $restoreHistory = BackupRestoreHistory::find($this->restoreHistoryId);
        if ($restoreHistory === null || $restoreHistory->status === 'completed') {
            return;
        }

        $restoreService->executeRestore($restoreHistory);
    }
}


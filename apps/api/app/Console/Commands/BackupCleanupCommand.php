<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Menghapus arsip backup lama yang melewati masa retensi tanpa menyentuh backup yang sedang direstore.
 */
class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup';

    protected $description = 'Membersihkan arsip backup yang telah melewati periode retensi';

    public function handle(BackupService $backupService): int
    {
        $summary = $backupService->cleanupExpiredBackups();

        $this->info(sprintf(
            'Cleanup selesai: %d backup dihapus, %d byte dibebaskan, %d dilewati.',
            $summary['deleted_count'],
            $summary['freed_bytes'],
            $summary['skipped_count']
        ));

        return self::SUCCESS;
    }
}


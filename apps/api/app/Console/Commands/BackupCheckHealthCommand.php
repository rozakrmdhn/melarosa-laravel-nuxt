<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Memeriksa status jadwal backup yang terlewat dan kapasitas disk penyimpanan backup.
 */
class BackupCheckHealthCommand extends Command
{
    protected $signature = 'backup:check-health';

    protected $description = 'Memeriksa kesehatan storage backup dan mendeteksi jadwal backup yang terlewat';

    public function handle(BackupService $backupService): int
    {
        $result = $backupService->checkHealthAlerts();

        $this->info(sprintf(
            'Pemeriksaan kesehatan backup selesai (low_disk_warning=%s, overdue=%d).',
            $result['low_disk_warning'] ? 'ya' : 'tidak',
            $result['overdue_count']
        ));

        return self::SUCCESS;
    }
}


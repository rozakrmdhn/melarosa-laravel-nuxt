<?php

namespace App\Console\Commands;

use App\Jobs\RunBackupJob;
use App\Models\BackupHistory;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Menjalankan backup manual via CLI atau mengevaluasi jadwal backup otomatis yang jatuh tempo.
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run
        {--type=database : Tipe backup (database, file, full)}
        {--scheduled : Evaluasi dan jalankan jadwal backup yang jatuh tempo}
        {--sync : Jalankan secara sinkron tanpa menunggu queue worker}';

    protected $description = 'Menjalankan proses backup database/file atau memproses jadwal backup otomatis';

    public function handle(BackupService $backupService): int
    {
        if (! config('backup.enabled', true)) {
            $this->info('Modul backup dinonaktifkan melalui konfigurasi.');

            return self::SUCCESS;
        }

        if ($this->option('scheduled')) {
            return $this->runScheduled($backupService);
        }

        $type = (string) $this->option('type');
        if (! in_array($type, BackupHistory::TYPES, true)) {
            $this->error("Tipe backup '{$type}' tidak valid. Gunakan: database, file, atau full.");

            return self::FAILURE;
        }

        try {
            $history = $backupService->createPendingBackup([
                'backup_type' => $type,
                'trigger_type' => BackupHistory::TRIGGER_MANUAL,
            ]);

            if ($this->option('sync')) {
                $backupService->executeBackup($history);
                $this->info("Backup {$type} selesai: {$history->file_name}");
            } else {
                RunBackupJob::dispatch($history->id);
                $this->info("Backup {$type} dikirim ke antrean (ID: {$history->id}).");
            }

            return self::SUCCESS;
        } catch (BackupException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function runScheduled(BackupService $backupService): int
    {
        if ($backupService->hasActiveBackupJob()) {
            $this->info('Masih ada job backup yang aktif; evaluasi jadwal ditunda ke siklus berikutnya.');

            return self::SUCCESS;
        }

        $dueSchedules = $backupService->getDueSchedules();
        if ($dueSchedules->isEmpty()) {
            return self::SUCCESS;
        }

        // Ambil satu jadwal due per siklus agar job tidak tumpang tindih satu sama lain.
        $schedule = $dueSchedules->first();

        try {
            $history = $backupService->createPendingBackup([
                'backup_type' => $schedule->backup_type,
                'trigger_type' => BackupHistory::TRIGGER_SCHEDULED,
                'backup_name' => $schedule->name.' ('.now($schedule->timezone)->format('d/m/Y H:i').')',
                'encrypt' => $schedule->encryption_enabled,
                'retention_days' => $schedule->retention_days,
                'replicate_to_remote' => $schedule->replicate_to_remote,
                'schedule_id' => $schedule->id,
            ]);

            $schedule->update([
                'next_run_at' => $schedule->computeNextRunAt(now($schedule->timezone)->addMinute()),
                'last_status' => $this->option('sync') ? 'processing' : 'queued',
            ]);

            if ($this->option('sync')) {
                $backupService->executeBackup($history);
                $this->info("Jadwal '{$schedule->name}' selesai dijalankan.");
            } else {
                RunBackupJob::dispatch($history->id);
                $this->info("Jadwal '{$schedule->name}' dikirim ke antrean (ID: {$history->id}).");
            }

            return self::SUCCESS;
        } catch (BackupException $e) {
            $this->error("Gagal menjadwalkan '{$schedule->name}': {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}


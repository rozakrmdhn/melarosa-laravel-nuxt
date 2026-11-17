<?php

namespace App\Console\Commands;

use App\Services\MartinTileService;
use Illuminate\Console\Command;

class SyncMartinCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'martin:sync-catalog
        {--trigger=cli : Execution source (cli|scheduler|manual_admin)}
        {--source=* : Optional specific Martin source IDs to sync}
        {--deactivate-missing : Deactivate synced Martin layers missing from remote catalog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi katalog layer vektor (MVT) dari Martin Tile Server ke dalam basis data Melarosa.';

    /**
     * Execute the console command.
     */
    public function handle(MartinTileService $martinService): int
    {
        $trigger = (string) $this->option('trigger');
        $sources = array_values(array_filter((array) $this->option('source')));
        $deactivateMissing = (bool) $this->option('deactivate-missing');

        $this->info("Memulai sinkronisasi katalog Martin Tile Server (trigger: {$trigger})...");

        $result = $martinService->sync($trigger, null, [
            'sources' => $sources,
            'deactivate_missing' => $deactivateMissing,
        ]);

        if ($result['ok']) {
            $summary = $result['summary'] ?? [];
            $this->info($result['message'] ?? 'Sinkronisasi selesai.');
            $this->table(
                ['Total Ditemukan', 'Baru Masuk', 'Diperbarui', 'Dilewati', 'Dinonaktifkan', 'Durasi'],
                [[
                    $summary['found'] ?? 0,
                    $summary['inserted'] ?? 0,
                    $summary['updated'] ?? 0,
                    $summary['skipped'] ?? 0,
                    $summary['deactivated'] ?? 0,
                    ($summary['duration_seconds'] ?? 0) . ' detik',
                ]]
            );

            return self::SUCCESS;
        }

        $this->error($result['message'] ?? 'Sinkronisasi gagal.');
        if (!empty($result['error'])) {
            $this->line('<fg=red>Detail: ' . $result['error'] . '</>');
        }

        return self::FAILURE;
    }
}


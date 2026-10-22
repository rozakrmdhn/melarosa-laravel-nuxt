<?php

namespace App\Console\Commands;

use App\Services\Gis\GeoportalCswClientService;
use Illuminate\Console\Command;

class SyncGeoportalCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geoportal:sync-catalog {--trigger=cli : Trigger source (cli, scheduler, manual_admin)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Harvest and synchronize map catalog from Bojonegoro Geoportal CSW into Melarosa layers';

    /**
     * Execute the console command.
     */
    public function handle(GeoportalCswClientService $cswService): int
    {
        $trigger = (string) $this->option('trigger');
        $this->info("Starting Geoportal CSW catalog synchronization (trigger: {$trigger})...");

        $result = $cswService->sync($trigger);

        if ($result['ok']) {
            $this->info($result['message']);
            $this->table(
                ['Found', 'Inserted', 'Updated', 'Skipped', 'Duration (s)'],
                [[
                    $result['summary']['found'],
                    $result['summary']['inserted'],
                    $result['summary']['updated'],
                    $result['summary']['skipped'],
                    $result['summary']['duration_seconds'],
                ]]
            );
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        return Command::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\BkkdSpatialAnalysisService;
use Illuminate\Console\Command;

class ReanalyzeBkkdSpatialCommand extends Command
{
    protected $signature = 'bkkd:reanalyze-spatial
        {--tahun= : Filter tahun anggaran proposal}
        {--all : Analisa ulang seluruh proposal berlokasi (bukan hanya yang belum_dianalisa)}
        {--limit=200 : Jumlah maksimal proposal per eksekusi}';

    protected $description = 'Analisa ulang spasial BKKD untuk geometri yang diedit langsung di PostgreSQL/QGIS (tanpa hit OpenRouteService)';

    public function handle(BkkdSpatialAnalysisService $spatialService): int
    {
        $tahun = $this->option('tahun') ? (int) $this->option('tahun') : null;
        $onlyPending = !$this->option('all');
        $limit = max(1, (int) $this->option('limit'));

        $result = $spatialService->analisaPendingLocations(
            tahunAnggaran: $tahun,
            onlyPending: $onlyPending,
            limit: $limit
        );

        $this->info(sprintf(
            'Selesai memproses %d proposal (%d lokasi LineString).',
            $result['processed_proposals'],
            $result['processed_locations']
        ));

        return self::SUCCESS;
    }
}

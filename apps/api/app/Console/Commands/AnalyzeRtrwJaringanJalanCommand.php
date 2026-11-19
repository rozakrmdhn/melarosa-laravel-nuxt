<?php

namespace App\Console\Commands;

use App\Services\RtrwJaringanJalanService;
use Illuminate\Console\Command;

/**
 * Jalankan analisa spasial batch rtrw_jaringan_transportasi_ln terhadap jalan_porosdesa dan jalan_kabupaten_luarkota.
 */
class AnalyzeRtrwJaringanJalanCommand extends Command
{
    protected $signature = 'jaringan-jalan:analisa-spasial {--kecamatan= : Batasi ke ID kecamatan tertentu} {--batch=500 : Jumlah segmen per batch}';

    protected $description = 'Hitung ulang analisa spasial rtrw_jaringan_transportasi_ln terhadap jalan_porosdesa dan jalan_kabupaten_luarkota';

    public function handle(RtrwJaringanJalanService $service): int
    {
        $kecId = $this->option('kecamatan') ? (int) $this->option('kecamatan') : null;
        $batch = max(100, min(2000, (int) $this->option('batch')));

        $this->info('Memulai analisa spasial RTRW Jaringan Transportasi vs Jalan Poros Desa & Jalan Kabupaten...');
        $result = $service->runSpatialAnalysis([
            'id_kecamatan' => $kecId,
            'batch_size' => $batch,
        ]);

        $this->info(sprintf(
            'Analisa selesai: %d segmen diproses dalam %.2f detik.',
            $result['processed_count'],
            $result['duration_seconds']
        ));

        foreach ($result['status_counts'] as $status => $count) {
            $this->line("  - {$status}: {$count}");
        }

        return self::SUCCESS;
    }
}

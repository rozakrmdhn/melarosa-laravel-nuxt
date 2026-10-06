<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MartinTileService
{
    /**
     * Purge tile cache on-demand in Martin Tile Server.
     *
     * @param string $sourceId e.g. 'jalan_porosdesa', 'infrastruktur_segmen'
     * @return bool
     */
    public static function purgeCache(string $sourceId): bool
    {
        $baseUrl = rtrim(config('services.martin.url', 'http://localhost:9090'), '/');
        $endpoint = "{$baseUrl}/cache/{$sourceId}";

        try {
            $response = Http::timeout(3)->delete($endpoint);

            if ($response->successful()) {
                Log::info("Martin cache purged successfully for source: {$sourceId}");
                return true;
            }

            Log::warning("Martin cache purge returned status {$response->status()}: {$response->body()}");
            return false;
        } catch (\Throwable $e) {
            Log::error("Failed to purge Martin cache for {$sourceId}: " . $e->getMessage());
            return false;
        }
    }
}

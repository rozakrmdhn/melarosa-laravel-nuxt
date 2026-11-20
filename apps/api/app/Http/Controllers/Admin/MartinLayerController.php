<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoportalSyncLog;
use App\Services\MartinTileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MartinLayerController extends Controller
{
    /**
     * Check real-time connection status & local layer counts for Martin Tile Server.
     */
    public function status(Request $request, MartinTileService $martinService): JsonResponse
    {
        $refresh = $request->boolean('refresh');
        $status = $martinService->checkConnection($refresh);

        return response()->json([
            'ok' => true,
            'data' => $status,
        ]);
    }

    /**
     * Preview live sources available on Martin Tile Server catalog along with sync state.
     */
    public function catalog(Request $request, MartinTileService $martinService): JsonResponse
    {
        try {
            $withTileJson = $request->boolean('with_tilejson', true);
            $items = $martinService->fetchCatalog($withTileJson);

            return response()->json([
                'ok' => true,
                'martin_url' => rtrim((string) config('services.martin.url', 'http://localhost:9090'), '/'),
                'data' => $items,
                'total' => count($items),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Martin catalog fetch error: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Gagal membaca katalog dari Martin Tile Server.',
                'martin_url' => rtrim((string) config('services.martin.url', 'http://localhost:9090'), '/'),
                'data' => [],
                'total' => 0,
            ], 502);
        }
    }

    /**
     * Trigger idempotent synchronization from Martin Tile Server into layers table.
     */
    public function sync(Request $request, MartinTileService $martinService): JsonResponse
    {
        $validated = $request->validate([
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', 'max:150'],
            'deactivate_missing' => ['sometimes', 'boolean'],
            'include_tilejson' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->user()?->id ? (int) $request->user()->id : null;
        $result = $martinService->sync('martin_manual_admin', $userId, $validated);

        $status = $result['ok'] ? 200 : 502;
        return response()->json($result, $status);
    }

    /**
     * Get recent Martin Tile Server sync execution logs.
     */
    public function syncHistory(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 15), 1), 50);

        $logs = GeoportalSyncLog::with('user:id,name,email')
            ->where('triggered_by', 'like', 'martin_%')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (GeoportalSyncLog $log) {
                $item = $log->toArray();
                $item['source_service'] = 'martin';
                return $item;
            });

        $lastSuccess = GeoportalSyncLog::where('status', 'success')
            ->where('triggered_by', 'like', 'martin_%')
            ->latest()
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $logs,
            'last_success_at' => $lastSuccess?->created_at?->toISOString(),
            'total_runs' => GeoportalSyncLog::where('triggered_by', 'like', 'martin_%')->count(),
        ]);
    }
}


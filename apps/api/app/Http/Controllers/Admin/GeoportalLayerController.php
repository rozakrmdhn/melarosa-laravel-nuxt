<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoportalSyncLog;
use App\Services\Gis\GeoportalCswClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoportalLayerController extends Controller
{
    /**
     * Trigger manual synchronization from Geoportal CSW.
     */
    public function sync(Request $request, GeoportalCswClientService $cswService): JsonResponse
    {
        $userId = $request->user()?->id ? (int) $request->user()->id : null;
        $result = $cswService->sync('manual_admin', $userId);

        $status = $result['ok'] ? 200 : 502;
        return response()->json($result, $status);
    }

    /**
     * Get recent sync execution history logs.
     */
    public function syncHistory(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 15), 1), 50);
        $source = (string) $request->query('source', 'all');

        $query = GeoportalSyncLog::with('user:id,name,email');
        $successQuery = GeoportalSyncLog::where('status', 'success');
        $totalQuery = GeoportalSyncLog::query();

        if ($source === 'martin') {
            $query->where('triggered_by', 'like', 'martin_%');
            $successQuery->where('triggered_by', 'like', 'martin_%');
            $totalQuery->where('triggered_by', 'like', 'martin_%');
        } elseif ($source === 'geoportal') {
            $query->where('triggered_by', 'not like', 'martin_%');
            $successQuery->where('triggered_by', 'not like', 'martin_%');
            $totalQuery->where('triggered_by', 'not like', 'martin_%');
        }

        $logs = $query
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (GeoportalSyncLog $log) {
                $item = $log->toArray();
                $item['source_service'] = str_starts_with((string) $log->triggered_by, 'martin_')
                    ? 'martin'
                    : 'geoportal';
                return $item;
            });

        $lastSuccess = $successQuery->latest()->first();

        return response()->json([
            'ok' => true,
            'data' => $logs,
            'last_success_at' => $lastSuccess?->created_at?->toISOString(),
            'total_runs' => $totalQuery->count(),
        ]);
    }
}

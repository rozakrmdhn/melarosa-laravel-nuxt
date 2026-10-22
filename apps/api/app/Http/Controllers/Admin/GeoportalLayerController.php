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
        $limit = min((int) $request->query('limit', 15), 50);

        $logs = GeoportalSyncLog::with('user:id,name,email')
            ->latest()
            ->limit($limit)
            ->get();

        $lastSuccess = GeoportalSyncLog::where('status', 'success')
            ->latest()
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $logs,
            'last_success_at' => $lastSuccess?->created_at?->toISOString(),
            'total_runs' => GeoportalSyncLog::count(),
        ]);
    }
}

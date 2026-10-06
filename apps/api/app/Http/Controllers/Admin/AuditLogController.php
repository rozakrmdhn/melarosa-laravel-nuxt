<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'module' => $request->input('module'),
            'event' => $request->input('event'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $auditLogs = AuditLog::query()
            ->with('user:id,name,email')
            ->filter($filters)
            ->latest('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'ok' => true,
            'data' => $auditLogs,
            'options' => [
                'users' => User::query()->select('id', 'name')->orderBy('name')->get(),
                'modules' => AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
                'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
            ],
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user:id,name,email');

        return response()->json([
            'ok' => true,
            'data' => $auditLog,
        ]);
    }
}

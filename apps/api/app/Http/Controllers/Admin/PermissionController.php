<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of all permissions.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::withCount('roles')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ok' => true,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_\.\-]+$/',
                'unique:permissions,name',
            ],
        ], [
            'name.regex' => 'The permission name should only contain letters, numbers, dots, dashes, and underscores.',
        ]);

        $permission = Permission::create([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => 'web',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Permission created successfully.',
            'permission' => $permission->loadCount('roles'),
        ], 201);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Permission deleted successfully.',
        ]);
    }
}

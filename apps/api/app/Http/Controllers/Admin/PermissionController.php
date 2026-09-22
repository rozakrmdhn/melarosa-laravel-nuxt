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
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $permissions = Permission::withCount('roles')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
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
            'name.required' => 'Nama hak akses wajib diisi.',
            'name.unique' => 'Nama hak akses sudah digunakan.',
            'name.regex' => 'Nama hak akses hanya boleh berisi huruf, angka, titik (.), strip (-), dan garis bawah (_).',
        ]);

        $permission = Permission::create([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => 'web',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Hak akses berhasil ditambahkan.',
            'permission' => $permission->loadCount('roles'),
        ], 201);
    }

    /**
     * Update the specified permission in storage.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_\.\-]+$/',
                'unique:permissions,name,' . $permission->id,
            ],
        ], [
            'name.required' => 'Nama hak akses wajib diisi.',
            'name.unique' => 'Nama hak akses sudah digunakan.',
            'name.regex' => 'Nama hak akses hanya boleh berisi huruf, angka, titik (.), strip (-), dan garis bawah (_).',
        ]);

        $permission->update([
            'name' => strtolower(trim($validated['name'])),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Hak akses berhasil diperbarui.',
            'permission' => $permission->loadCount('roles'),
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Hak akses berhasil dihapus.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $permissions = Permission::orderBy('name')->get();

        return response()->json([
            'ok' => true,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Akses grup berhasil dibuat.',
            'role' => $role->load('permissions')->loadCount('users'),
        ], 201);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Prevent renaming super admin role
        if ($role->name === 'admin' && $validated['name'] !== 'admin') {
            return response()->json([
                'ok' => false,
                'message' => 'Nama akses grup sistem (admin) tidak dapat diubah.',
            ], 422);
        }

        $role->update(['name' => $validated['name']]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Akses grup berhasil diperbarui.',
            'role' => $role->load('permissions')->loadCount('users'),
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'admin') {
            return response()->json([
                'ok' => false,
                'message' => 'Akses grup sistem (admin) tidak dapat dihapus.',
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak dapat menghapus akses grup yang masih memiliki pengguna terdaftar. Silakan pindahkan pengguna terlebih dahulu.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Akses grup berhasil dihapus.',
        ]);
    }
}


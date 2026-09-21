<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    /**
     * Display a listing of users with their roles.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $users = User::with(['roles:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->select(['id', 'name', 'email', 'avatar', 'created_at'])
            ->latest()
            ->paginate(15);

        $roles = Role::select(['id', 'name'])->orderBy('name')->get();

        return response()->json([
            'ok' => true,
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Update roles for a specific user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ], [
            'roles.required' => 'At least one role must be assigned to the user.',
            'roles.min' => 'At least one role must be assigned to the user.',
        ]);

        // Prevent self-demotion from admin
        if ($request->user()->id === $user->id && $request->user()->hasRole('admin') && !in_array('admin', $validated['roles'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'You cannot remove the admin role from your own account.',
            ], 422);
        }

        $user->syncRoles($validated['roles']);

        return response()->json([
            'ok' => true,
            'message' => 'User roles updated successfully.',
            'user' => $user->load(['roles:id,name']),
        ]);
    }
}

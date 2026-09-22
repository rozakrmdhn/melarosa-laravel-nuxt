<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a paginated listing of users with roles and search/filter.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $role = $request->query('role');

        $users = User::with(['roles:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, function ($query, $role) {
                $query->role($role);
            })
            ->select(['id', 'uuid', 'name', 'email', 'avatar', 'email_verified_at', 'created_at'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        $roles = Role::select(['id', 'name'])->orderBy('name')->get();

        return response()->json([
            'ok' => true,
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ], [
            'roles.required' => 'Pilih setidaknya satu role untuk pengguna.',
            'roles.min' => 'Pilih setidaknya satu role untuk pengguna.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Tandai email sudah terverifikasi saat dibuat oleh admin
        $user->markEmailAsVerified();

        $user->syncRoles($validated['roles']);

        return response()->json([
            'ok' => true,
            'message' => 'Pengguna berhasil ditambahkan.',
            'user' => $user->load(['roles:id,name']),
        ], 201);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ], [
            'roles.required' => 'Pilih setidaknya satu role untuk pengguna.',
            'roles.min' => 'Pilih setidaknya satu role untuk pengguna.',
        ]);

        // Cegah admin mencabut role admin dari akunnya sendiri
        if ($request->user()->id === $user->id && $request->user()->hasRole('admin') && !in_array('admin', $validated['roles'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Anda tidak dapat mencabut role admin dari akun Anda sendiri.',
            ], 422);
        }

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);
        $user->syncRoles($validated['roles']);

        return response()->json([
            'ok' => true,
            'message' => 'Data pengguna berhasil diperbarui.',
            'user' => $user->load(['roles:id,name']),
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Cegah menghapus akun sendiri
        if ($request->user()->id === $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Pengguna berhasil dihapus.',
        ]);
    }

    /**
     * Fast update roles for a specific user.
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ], [
            'roles.required' => 'Pilih setidaknya satu role untuk pengguna.',
            'roles.min' => 'Pilih setidaknya satu role untuk pengguna.',
        ]);

        if ($request->user()->id === $user->id && $request->user()->hasRole('admin') && !in_array('admin', $validated['roles'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Anda tidak dapat mencabut role admin dari akun Anda sendiri.',
            ], 422);
        }

        $user->syncRoles($validated['roles']);

        return response()->json([
            'ok' => true,
            'message' => 'Role pengguna berhasil diperbarui.',
            'user' => $user->load(['roles:id,name']),
        ]);
    }
}

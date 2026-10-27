<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a paginated listing of users with roles, wilayah, and search/filter.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $search = $request->query('search');
        $role = $request->query('role');
        $actor = $request->user();
        $cutoff = now()->subMinutes(5)->timestamp;

        $users = User::with([
            'roles:id,name',
            'kecamatan:id,nama_kecamatan',
            'desa:id,nama_desa',
            'latestSession',
        ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, function ($query, $role) {
                $query->role($role);
            })
            // Filter status: ?status=1 (aktif), ?status=0 (nonaktif)
            ->when($request->has('status') && $request->query('status') !== null && $request->query('status') !== '', function ($query) use ($request) {
                $query->where('status', filter_var($request->query('status'), FILTER_VALIDATE_BOOLEAN));
            })
            // Filter online saja
            ->when($request->boolean('online_only'), function ($query) use ($cutoff) {
                $query->whereHas('sessions', function ($q) use ($cutoff) {
                    $q->where('last_activity', '>=', $cutoff);
                });
            })
            // Filter wilayah jika actor bukan admin (pembatasan wilayah actor)
            ->when(!$actor->hasRole('admin') && $actor->id_kecamatan, function ($query) use ($actor) {
                $query->where('id_kecamatan', $actor->id_kecamatan);
            })
            ->when(!$actor->hasRole('admin') && $actor->id_desa, function ($query) use ($actor) {
                $query->where('id_desa', $actor->id_desa);
            })
            // Filter query opsional untuk admin/user berwenang
            ->when($request->filled('id_kecamatan'), function ($query) use ($request) {
                $query->where('id_kecamatan', (int) $request->query('id_kecamatan'));
            })
            ->when($request->filled('id_desa'), function ($query) use ($request) {
                $query->where('id_desa', (int) $request->query('id_desa'));
            })
            ->select([
                'id',
                'uuid',
                'name',
                'email',
                'avatar',
                'id_kecamatan',
                'id_desa',
                'status',
                'email_verified_at',
                'created_at',
            ])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        // Format status online dan aktivitas terakhir per pengguna
        $users->getCollection()->transform(function ($user) use ($cutoff, $actor) {
            $latest = $user->latestSession;
            $lastActivity = $latest ? (int) $latest->last_activity : null;
            $carbon = $lastActivity ? Carbon::createFromTimestamp($lastActivity) : null;
            $isOnline = $lastActivity !== null && $lastActivity >= $cutoff;

            $user->is_online = $isOnline;
            $user->last_activity = $carbon?->toIso8601String();
            $user->last_activity_human = $carbon?->diffForHumans();
            $user->is_current_user = $user->id === $actor->id;

            unset($user->latestSession);

            return $user;
        });

        $roles = Role::select(['id', 'name'])->orderBy('name')->get();

        return response()->json([
            'ok' => true,
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Display a list of currently active users based on recent session activity.
     */
    public function activeUsers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $thresholdMinutes = max(1, min(60, $request->integer('threshold', 5)));
        $cutoff = now()->subMinutes($thresholdMinutes)->timestamp;
        $search = $request->query('search');

        $query = User::query()
            ->whereHas('sessions', function ($q) use ($cutoff) {
                $q->where('last_activity', '>=', $cutoff);
            })
            ->with([
                'roles:id,name',
                'kecamatan:id,nama_kecamatan',
                'desa:id,nama_desa',
                'sessions' => function ($q) use ($cutoff) {
                    $q->where('last_activity', '>=', $cutoff)
                      ->orderBy('last_activity', 'desc');
                },
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            // Filter wilayah jika actor bukan admin (pembatasan wilayah actor)
            ->when(!$actor->hasRole('admin') && $actor->id_kecamatan, function ($query) use ($actor) {
                $query->where('id_kecamatan', $actor->id_kecamatan);
            })
            ->when(!$actor->hasRole('admin') && $actor->id_desa, function ($query) use ($actor) {
                $query->where('id_desa', $actor->id_desa);
            });

        $activeUsers = $query->get()->map(function ($user) use ($actor) {
            $latestSession = $user->sessions->first();
            $deviceName = 'Tidak diketahui';
            if ($latestSession && $latestSession->user_agent) {
                try {
                    $deviceName = Utils::getDeviceNameFromDetector(
                        Utils::getDeviceDetectorByUserAgent($latestSession->user_agent)
                    );
                } catch (\Throwable $e) {
                    $deviceName = 'Perangkat Tidak Dikenal';
                }
            }

            $lastActivityTimestamp = $latestSession ? (int) $latestSession->last_activity : null;
            $lastActivityCarbon = $lastActivityTimestamp ? Carbon::createFromTimestamp($lastActivityTimestamp) : null;

            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'roles' => $user->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
                'kecamatan' => $user->kecamatan ? ['id' => $user->kecamatan->id, 'nama_kecamatan' => $user->kecamatan->nama_kecamatan] : null,
                'desa' => $user->desa ? ['id' => $user->desa->id, 'nama_desa' => $user->desa->nama_desa] : null,
                'status' => $user->status,
                'is_current_user' => $user->id === $actor->id,
                'active_sessions_count' => $user->sessions->count(),
                'latest_session' => $latestSession ? [
                    'ip_address' => $latestSession->ip_address,
                    'device_name' => $deviceName,
                    'last_activity' => $lastActivityCarbon?->toIso8601String(),
                    'last_activity_human' => $lastActivityCarbon?->diffForHumans(),
                ] : null,
            ];
        });

        return response()->json([
            'ok' => true,
            'threshold_minutes' => $thresholdMinutes,
            'total_active' => $activeUsers->count(),
            'users' => $activeUsers,
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'ok' => true,
            'user' => $user->load(['roles:id,name', 'kecamatan:id,nama_kecamatan', 'desa:id,nama_desa']),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'id_kecamatan' => ['nullable', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa' => ['nullable', 'integer', 'exists:bataswilayah_desa,id'],
            'status' => ['nullable', 'boolean'],
        ], [
            'roles.required' => 'Pilih setidaknya satu role untuk pengguna.',
            'roles.min' => 'Pilih setidaknya satu role untuk pengguna.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'id_kecamatan' => $validated['id_kecamatan'] ?? null,
            'id_desa' => $validated['id_desa'] ?? null,
            'status' => $validated['status'] ?? true,
        ]);

        // Tandai email sudah terverifikasi saat dibuat oleh admin
        $user->markEmailAsVerified();

        $user->syncRoles($validated['roles']);

        return response()->json([
            'ok' => true,
            'message' => 'Pengguna berhasil ditambahkan.',
            'user' => $user->load(['roles:id,name', 'kecamatan:id,nama_kecamatan', 'desa:id,nama_desa']),
        ], 201);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'id_kecamatan' => ['nullable', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa' => ['nullable', 'integer', 'exists:bataswilayah_desa,id'],
            'status' => ['sometimes', 'boolean'],
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

        $statusBefore = (bool) $user->status;
        $rolesBefore = $user->roles->pluck('name')->sort()->values()->all();

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (array_key_exists('id_kecamatan', $validated)) {
            $userData['id_kecamatan'] = $validated['id_kecamatan'];
        }

        if (array_key_exists('id_desa', $validated)) {
            $userData['id_desa'] = $validated['id_desa'];
        }

        if (array_key_exists('status', $validated)) {
            $userData['status'] = $validated['status'];
        }

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);
        $user->syncRoles($validated['roles']);

        $rolesAfter = collect($validated['roles'])->sort()->values()->all();

        $auditLog = app(AuditLogService::class);

        if (array_key_exists('status', $userData) && $statusBefore !== (bool) $userData['status']) {
            $auditLog->log(
                event: 'user.status.changed',
                module: 'security',
                auditable: $user,
                description: 'Status akun pengguna "' . $user->name . '" diubah menjadi: '
                    . ($userData['status'] ? 'Aktif' : 'Nonaktif') . '.',
                before: ['status' => $statusBefore],
                after: ['status' => (bool) $userData['status']],
            );
        }

        if (!empty($validated['password'])) {
            $auditLog->log(
                event: 'user.password.reset.admin',
                module: 'security',
                auditable: $user,
                description: 'Password pengguna "' . $user->name . '" direset oleh admin.',
            );
        }

        if ($rolesBefore !== $rolesAfter) {
            $auditLog->log(
                event: 'user.roles.updated',
                module: 'security',
                auditable: $user,
                description: 'Role pengguna "' . $user->name . '" diperbarui.',
                before: ['roles' => $rolesBefore],
                after: ['roles' => $rolesAfter],
            );
        }

        return response()->json([
            'ok' => true,
            'message' => 'Data pengguna berhasil diperbarui.',
            'user' => $user->load(['roles:id,name', 'kecamatan:id,nama_kecamatan', 'desa:id,nama_desa']),
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

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
        $this->authorize('update', $user);

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

        $rolesBefore = $user->roles->pluck('name')->sort()->values()->all();

        $user->syncRoles($validated['roles']);

        $rolesAfter = collect($validated['roles'])->sort()->values()->all();

        app(AuditLogService::class)->log(
            event: 'user.roles.updated',
            module: 'security',
            auditable: $user,
            description: 'Role pengguna "' . $user->name . '" diperbarui.',
            before: ['roles' => $rolesBefore],
            after:  ['roles' => $rolesAfter],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Role pengguna berhasil diperbarui.',
            'user' => $user->load(['roles:id,name', 'kecamatan:id,nama_kecamatan', 'desa:id,nama_desa']),
        ]);
    }
}

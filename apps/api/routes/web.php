<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BatasWilayahDesaController;
use App\Http\Controllers\Admin\BatasWilayahKecamatanController;
use App\Http\Controllers\Admin\InfrastrukturSegmenController;
use App\Http\Controllers\Admin\InfrastrukturTipeController;
use App\Http\Controllers\Admin\InfrastrukturVerifikasiController;
use App\Http\Controllers\Admin\JalanPorosDesaController;
use App\Http\Controllers\Admin\LayerController;
use App\Http\Controllers\Admin\MonitoringRealisasiController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlottingAnggaranController;
use App\Http\Controllers\Admin\RefSumberDanaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['ok' => true, 'message' => 'Welcome to the API'];
});

Route::prefix('api/v1')->group(function () {
    Route::get('sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show'])->name('sanctum.csrf-cookie.alias');
    Route::get('login/{provider}/redirect', [AuthController::class, 'redirect'])->name('login.provider.redirect');
    Route::get('login/{provider}/callback', [AuthController::class, 'callback'])->name('login.provider.callback');
    Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('forgot-password', [AuthController::class, 'sendResetPasswordLink'])->middleware('throttle:5,1')->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.store');
    Route::post('verification-notification', [AuthController::class, 'verificationNotification'])->middleware('throttle:verification-notification')->name('verification.send');
    Route::get('verify-email/{uuid}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::middleware(["auth"])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('devices/disconnect', [AuthController::class, 'deviceDisconnect'])->name('devices.disconnect');
        Route::get('devices', [AuthController::class, 'devices'])->name('devices');
        Route::get('user', [AuthController::class, 'user'])->name('user');

        Route::post('account/update', [AccountController::class, 'update'])->name('account.update');
        Route::post('account/password', [AccountController::class, 'password'])->name('account.password');

        Route::middleware(['throttle:uploads'])->group(function () {
            Route::post('upload', [UploadController::class, 'image'])->name('upload.image');
        });

        Route::prefix('admin')->group(function () {
            Route::get('navigation', function (\Illuminate\Http\Request $request, \App\Services\AdminNavigationService $navService) {
                return response()->json([
                    'ok' => true,
                    'data' => $navService->getFilteredMenu($request->user()),
                ]);
            })->name('admin.navigation');

            Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('admin.roles.index');
            Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('admin.roles.show');
            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage|roles.create')->name('admin.roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage|roles.edit')->name('admin.roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage|roles.delete')->name('admin.roles.destroy');

            Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions-view')->name('admin.permissions.index');
            Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions-manage|permissions-create')->name('admin.permissions.store');
            Route::put('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions-manage|permissions-update')->name('admin.permissions.update');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions-manage|permissions-delete')->name('admin.permissions.destroy');

            Route::get('users', [UserController::class, 'index'])->middleware('permission:users-view')->name('admin.users.index');
            Route::post('users', [UserController::class, 'store'])->middleware('permission:users-create')->name('admin.users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users-view')->name('admin.users.show');
            Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users-update')->name('admin.users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users-delete')->name('admin.users.destroy');
            Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('permission:users-update')->name('admin.users.roles.update');

            // Spatial Editor Routes: Batas Wilayah Desa
            Route::get('batas-wilayah-desa', [BatasWilayahDesaController::class, 'index'])->middleware('permission:batas-desa-view|batas-desa-manage')->name('admin.batas-desa.index');
            Route::post('batas-wilayah-desa', [BatasWilayahDesaController::class, 'store'])->middleware('permission:batas-desa-create|batas-desa-manage')->name('admin.batas-desa.store');
            Route::get('batas-wilayah-desa/{id}', [BatasWilayahDesaController::class, 'show'])->name('admin.batas-desa.show');
            Route::put('batas-wilayah-desa/{id}', [BatasWilayahDesaController::class, 'update'])->middleware('permission:batas-desa-update|batas-desa-manage')->name('admin.batas-desa.update');
            Route::delete('batas-wilayah-desa/{id}', [BatasWilayahDesaController::class, 'destroy'])->middleware('permission:batas-desa-delete|batas-desa-manage')->name('admin.batas-desa.destroy');
            Route::post('batas-wilayah-desa/{id}/split', [BatasWilayahDesaController::class, 'split'])->middleware('permission:batas-desa-update|batas-desa-manage')->name('admin.batas-desa.split');

            // Spatial Editor Routes: Batas Wilayah Kecamatan
            Route::get('batas-wilayah-kecamatan', [BatasWilayahKecamatanController::class, 'index'])->middleware('permission:batas-kecamatan-view|batas-kecamatan-manage')->name('admin.batas-kecamatan.index');
            Route::post('batas-wilayah-kecamatan', [BatasWilayahKecamatanController::class, 'store'])->middleware('permission:batas-kecamatan-create|batas-kecamatan-manage')->name('admin.batas-kecamatan.store');
            Route::get('batas-wilayah-kecamatan/{id}', [BatasWilayahKecamatanController::class, 'show'])->middleware('permission:batas-kecamatan-view|batas-kecamatan-manage')->name('admin.batas-kecamatan.show');
            Route::put('batas-wilayah-kecamatan/{id}', [BatasWilayahKecamatanController::class, 'update'])->middleware('permission:batas-kecamatan-update|batas-kecamatan-manage')->name('admin.batas-kecamatan.update');
            Route::delete('batas-wilayah-kecamatan/{id}', [BatasWilayahKecamatanController::class, 'destroy'])->middleware('permission:batas-kecamatan-delete|batas-kecamatan-manage')->name('admin.batas-kecamatan.destroy');

            // Spatial Editor Routes: Jalan Poros Desa
            Route::get('jalan-poros-desa', [JalanPorosDesaController::class, 'index'])->middleware('permission:jalan-poros-desa-view|jalan-poros-desa.view|jalan-view|jalan-poros-desa-manage|jalan-poros-desa.manage')->name('admin.jalan-poros-desa.index');
            Route::post('jalan-poros-desa', [JalanPorosDesaController::class, 'store'])->middleware('permission:jalan-poros-desa-create|jalan-poros-desa-manage')->name('admin.jalan-poros-desa.store');
            Route::get('jalan-poros-desa/{id}', [JalanPorosDesaController::class, 'show'])->name('admin.jalan-poros-desa.show');
            Route::put('jalan-poros-desa/{id}', [JalanPorosDesaController::class, 'update'])->middleware('permission:jalan-poros-desa-update|jalan-poros-desa-manage')->name('admin.jalan-poros-desa.update');
            Route::delete('jalan-poros-desa/{id}', [JalanPorosDesaController::class, 'destroy'])->middleware('permission:jalan-poros-desa-delete|jalan-poros-desa-manage')->name('admin.jalan-poros-desa.destroy');
            Route::post('jalan-poros-desa/{id}/split', [JalanPorosDesaController::class, 'split'])->middleware('permission:jalan-poros-desa-split|jalan-poros-desa-manage')->name('admin.jalan-poros-desa.split');

            // Audit Logs Routes
            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs-view')->name('admin.audit-logs.index');
            Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit-logs-view')->name('admin.audit-logs.show');

            // Layer Management Routes
            Route::get('layers', [LayerController::class, 'index'])->middleware('permission:layers-view')->name('admin.layers.index');
            Route::post('layers', [LayerController::class, 'store'])->middleware('permission:layers-create|layers-manage')->name('admin.layers.store');
            Route::post('layers/reorder', [LayerController::class, 'reorder'])->middleware('permission:layers-update|layers-manage')->name('admin.layers.reorder');
            Route::get('layers/{layer}', [LayerController::class, 'show'])->middleware('permission:layers-view')->name('admin.layers.show');
            Route::put('layers/{layer}', [LayerController::class, 'update'])->middleware('permission:layers-update|layers-manage')->name('admin.layers.update');
            Route::delete('layers/{layer}', [LayerController::class, 'destroy'])->middleware('permission:layers-delete|layers-manage')->name('admin.layers.destroy');
            Route::patch('layers/{layer}/toggle-active', [LayerController::class, 'toggleActive'])->middleware('permission:layers-update|layers-manage')->name('admin.layers.toggle-active');

            // Infrastruktur Master Tipe
            Route::get('infrastruktur-tipe', [InfrastrukturTipeController::class, 'index'])->middleware('permission:infrastruktur-tipe-view|infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.index');
            Route::get('infrastruktur-tipe/{id}', [InfrastrukturTipeController::class, 'show'])->middleware('permission:infrastruktur-tipe-view|infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.show');
            Route::post('infrastruktur-tipe', [InfrastrukturTipeController::class, 'store'])->middleware('permission:infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.store');
            Route::put('infrastruktur-tipe/{id}', [InfrastrukturTipeController::class, 'update'])->middleware('permission:infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.update');
            Route::delete('infrastruktur-tipe/{id}', [InfrastrukturTipeController::class, 'destroy'])->middleware('permission:infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.destroy');

            // Referensi Sumber Dana
            Route::get('ref-sumber-dana', [RefSumberDanaController::class, 'index'])->middleware('permission:ref-sumber-dana-view|ref-sumber-dana-manage')->name('admin.ref-sumber-dana.index');
            Route::get('ref-sumber-dana/{id}', [RefSumberDanaController::class, 'show'])->middleware('permission:ref-sumber-dana-view|ref-sumber-dana-manage')->name('admin.ref-sumber-dana.show');
            Route::post('ref-sumber-dana', [RefSumberDanaController::class, 'store'])->middleware('permission:ref-sumber-dana-manage')->name('admin.ref-sumber-dana.store');
            Route::put('ref-sumber-dana/{id}', [RefSumberDanaController::class, 'update'])->middleware('permission:ref-sumber-dana-manage')->name('admin.ref-sumber-dana.update');
            Route::delete('ref-sumber-dana/{id}', [RefSumberDanaController::class, 'destroy'])->middleware('permission:ref-sumber-dana-manage')->name('admin.ref-sumber-dana.destroy');

            // Plotting Anggaran
            Route::get('plotting-anggaran', [PlottingAnggaranController::class, 'index'])->middleware('permission:plotting-anggaran-view|plotting-anggaran-manage')->name('admin.plotting-anggaran.index');
            Route::post('plotting-anggaran', [PlottingAnggaranController::class, 'store'])->middleware('permission:plotting-anggaran-manage')->name('admin.plotting-anggaran.store');
            Route::get('plotting-anggaran/{id}', [PlottingAnggaranController::class, 'show'])->middleware('permission:plotting-anggaran-view|plotting-anggaran-manage')->name('admin.plotting-anggaran.show');
            Route::put('plotting-anggaran/{id}', [PlottingAnggaranController::class, 'update'])->middleware('permission:plotting-anggaran-manage')->name('admin.plotting-anggaran.update');
            Route::delete('plotting-anggaran/{id}', [PlottingAnggaranController::class, 'destroy'])->middleware('permission:plotting-anggaran-manage')->name('admin.plotting-anggaran.destroy');

            // Infrastruktur Segmen (Spasial)
            Route::get('infrastruktur-segmen', [InfrastrukturSegmenController::class, 'index'])->middleware('permission:infrastruktur-segmen-view')->name('admin.infrastruktur-segmen.index');
            Route::post('infrastruktur-segmen', [InfrastrukturSegmenController::class, 'store'])->middleware('permission:infrastruktur-segmen-create')->name('admin.infrastruktur-segmen.store');
            Route::get('infrastruktur-segmen/{id}', [InfrastrukturSegmenController::class, 'show'])->middleware('permission:infrastruktur-segmen-view')->name('admin.infrastruktur-segmen.show');
            Route::put('infrastruktur-segmen/{id}', [InfrastrukturSegmenController::class, 'update'])->middleware('permission:infrastruktur-segmen-update')->name('admin.infrastruktur-segmen.update');
            Route::delete('infrastruktur-segmen/{id}', [InfrastrukturSegmenController::class, 'destroy'])->middleware('permission:infrastruktur-segmen-delete')->name('admin.infrastruktur-segmen.destroy');

            // State Machine Verifikasi Segmen
            Route::post('infrastruktur-segmen/{id}/submit', [InfrastrukturVerifikasiController::class, 'submit'])->name('admin.infrastruktur-segmen.submit');
            Route::post('infrastruktur-segmen/{id}/verify-kecamatan', [InfrastrukturVerifikasiController::class, 'verifyKecamatan'])->middleware('permission:infrastruktur-segmen-verify-kecamatan')->name('admin.infrastruktur-segmen.verify-kecamatan');
            Route::post('infrastruktur-segmen/{id}/verify-bappeda', [InfrastrukturVerifikasiController::class, 'verifyBappeda'])->middleware('permission:infrastruktur-segmen-verify-bappeda')->name('admin.infrastruktur-segmen.verify-bappeda');

            // Monitoring Realisasi (Berita Acara)
            Route::get('monitoring-realisasi', [MonitoringRealisasiController::class, 'index'])->middleware('permission:monitoring-realisasi-view|monitoring-realisasi-manage')->name('admin.monitoring-realisasi.index');
            Route::post('monitoring-realisasi', [MonitoringRealisasiController::class, 'store'])->middleware('permission:monitoring-realisasi-manage')->name('admin.monitoring-realisasi.store');
            Route::get('monitoring-realisasi/{id}', [MonitoringRealisasiController::class, 'show'])->middleware('permission:monitoring-realisasi-view|monitoring-realisasi-manage')->name('admin.monitoring-realisasi.show');
            Route::put('monitoring-realisasi/{id}', [MonitoringRealisasiController::class, 'update'])->middleware('permission:monitoring-realisasi-manage')->name('admin.monitoring-realisasi.update');
            Route::delete('monitoring-realisasi/{id}', [MonitoringRealisasiController::class, 'destroy'])->middleware('permission:monitoring-realisasi-manage')->name('admin.monitoring-realisasi.destroy');
            Route::post('monitoring-realisasi/{id}/revisi', [MonitoringRealisasiController::class, 'revisi'])->middleware('permission:monitoring-realisasi-manage')->name('admin.monitoring-realisasi.revisi');
        });
    });

    // Map Viewer Active Layers Feed (Public / Map accessible)
    Route::get('layers', [LayerController::class, 'activeLayers'])->name('layers.active');

    // WMS GetFeatureInfo Proxy
    Route::get('wms/feature-info', function (\Illuminate\Http\Request $request) {
        $url = $request->query('url');
        if (!$url) {
            return response()->json(['error' => 'URL WMS parameter is required', 'features' => []], 400);
        }

        // SSRF protection: only allow requests to whitelisted WMS hosts
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        $scheme = strtolower($parsed['scheme'] ?? '');

        $allowedHostPatterns = [
            '/^(.+\.)?bojonegorokab\.go\.id$/',
            '/^(.+\.)?geoportal\.ina-sdi\.or\.id$/',
            '/^(.+\.)?geo\.kominfo\.go\.id$/',
            '/^(.+\.)?tanahair\.indonesia\.go\.id$/',
        ];

        $isAllowed = collect($allowedHostPatterns)->contains(fn($pattern) => preg_match($pattern, $host) === 1);
        if (!$isAllowed || !in_array($scheme, ['http', 'https'])) {
            return response()->json(['features' => [], 'error' => 'WMS host not allowed'], 403);
        }

        // Block private/loopback IP ranges
        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return response()->json(['features' => [], 'error' => 'WMS host not allowed'], 403);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => true])
                ->timeout(6)
                ->get($url);

            if ($response->successful()) {
                return $response->json() ?? ['type' => 'FeatureCollection', 'features' => [], 'raw' => $response->body()];
            }
            return response()->json(['features' => [], 'error' => 'WMS request failed: ' . $response->status()], $response->status());
        } catch (\Throwable $e) {
            return response()->json(['features' => [], 'error' => 'WMS request failed'], 500);
        }
    })->name('wms.feature-info');

    // KUGI (Katalog Unsur Geografi Indonesia) API Proxy dengan Cache 24 Jam
    Route::get('kugi/feature-type', function (\Illuminate\Http\Request $request) {
        $code = trim((string) $request->query('code'));
        if (!$code) {
            return response()->json(['error' => 'Parameter code (FCODE) is required', 'data' => []], 400);
        }

        $codeUpper = strtoupper($code);
        $cacheKey = 'kugi_feature_type_' . $codeUpper;

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($codeUpper) {
            try {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->timeout(8)
                    ->get('https://kugi.ina-sdi.or.id/kugiapi/featuretypegetbycode', [
                        'code' => $codeUpper,
                    ]);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }
                return [];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("KUGI API fetch error for code [{$codeUpper}]: " . $e->getMessage());
                return [];
            }
        });

        return response()->json([
            'ok' => true,
            'code' => $codeUpper,
            'data' => $data,
        ]);
    })->name('kugi.feature-type');
});


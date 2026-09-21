<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\BatasWilayahDesaController;
use App\Http\Controllers\Admin\BatasWilayahKecamatanController;
use App\Http\Controllers\Admin\JalanPorosDesaController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['ok' => true, 'message' => 'Welcome to the API'];
});

Route::prefix('api/v1')->group(function () {
    Route::get('login/{provider}/redirect', [AuthController::class, 'redirect'])->name('login.provider.redirect');
    Route::get('login/{provider}/callback', [AuthController::class, 'callback'])->name('login.provider.callback');
    Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('forgot-password', [AuthController::class, 'sendResetPasswordLink'])->middleware('throttle:5,1')->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
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

        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('admin.roles.index');
            Route::post('roles', [RoleController::class, 'store'])->name('admin.roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');

            Route::get('permissions', [PermissionController::class, 'index'])->name('admin.permissions.index');
            Route::post('permissions', [PermissionController::class, 'store'])->name('admin.permissions.store');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('admin.permissions.destroy');

            Route::get('users', [UserRoleController::class, 'index'])->name('admin.users.index');
            Route::put('users/{user}/roles', [UserRoleController::class, 'update'])->name('admin.users.roles.update');

            // Spatial Editor Routes: Batas Wilayah Desa
            Route::get('batas-wilayah-desa', [BatasWilayahDesaController::class, 'index'])->name('admin.batas-desa.index');
            Route::post('batas-wilayah-desa', [BatasWilayahDesaController::class, 'store'])->name('admin.batas-desa.store');
            Route::get('batas-wilayah-desa/mvt/{z}/{x}/{y}.pbf', [BatasWilayahDesaController::class, 'mvt'])->name('admin.batas-desa.mvt');
            Route::get('batas-wilayah-desa/{id}', [BatasWilayahDesaController::class, 'show'])->name('admin.batas-desa.show');
            Route::put('batas-wilayah-desa/{id}', [BatasWilayahDesaController::class, 'update'])->name('admin.batas-desa.update');
            Route::delete('batas-wilayah-desa/{id}', [BatasWilayahDesaController::class, 'destroy'])->name('admin.batas-desa.destroy');
            Route::post('batas-wilayah-desa/{id}/split', [BatasWilayahDesaController::class, 'split'])->name('admin.batas-desa.split');

            // Spatial Editor Routes: Batas Wilayah Kecamatan
            Route::get('batas-wilayah-kecamatan', [BatasWilayahKecamatanController::class, 'index'])->name('admin.batas-kecamatan.index');
            Route::post('batas-wilayah-kecamatan', [BatasWilayahKecamatanController::class, 'store'])->name('admin.batas-kecamatan.store');
            Route::get('batas-wilayah-kecamatan/mvt/{z}/{x}/{y}.pbf', [BatasWilayahKecamatanController::class, 'mvt'])->name('admin.batas-kecamatan.mvt');
            Route::get('batas-wilayah-kecamatan/{id}', [BatasWilayahKecamatanController::class, 'show'])->name('admin.batas-kecamatan.show');
            Route::put('batas-wilayah-kecamatan/{id}', [BatasWilayahKecamatanController::class, 'update'])->name('admin.batas-kecamatan.update');
            Route::delete('batas-wilayah-kecamatan/{id}', [BatasWilayahKecamatanController::class, 'destroy'])->name('admin.batas-kecamatan.destroy');

            // Spatial Editor Routes: Jalan Poros Desa
            Route::get('jalan-poros-desa', [JalanPorosDesaController::class, 'index'])->name('admin.jalan-poros-desa.index');
            Route::post('jalan-poros-desa', [JalanPorosDesaController::class, 'store'])->name('admin.jalan-poros-desa.store');
            Route::get('jalan-poros-desa/mvt/{z}/{x}/{y}.pbf', [JalanPorosDesaController::class, 'mvt'])->name('admin.jalan-poros-desa.mvt');
            Route::get('jalan-poros-desa/{id}', [JalanPorosDesaController::class, 'show'])->name('admin.jalan-poros-desa.show');
            Route::put('jalan-poros-desa/{id}', [JalanPorosDesaController::class, 'update'])->name('admin.jalan-poros-desa.update');
            Route::delete('jalan-poros-desa/{id}', [JalanPorosDesaController::class, 'destroy'])->name('admin.jalan-poros-desa.destroy');
            Route::post('jalan-poros-desa/{id}/split', [JalanPorosDesaController::class, 'split'])->name('admin.jalan-poros-desa.split');
        });
    });

    // Public / Tile-accessible Dataset MVT Vector Tiles
    Route::get('dataset/mvt/batas-wilayah-desa/{z}/{x}/{y}.pbf', [BatasWilayahDesaController::class, 'mvt'])->name('dataset.mvt.batas-desa');
    Route::get('dataset/mvt/batas-wilayah-kecamatan/{z}/{x}/{y}.pbf', [BatasWilayahKecamatanController::class, 'mvt'])->name('dataset.mvt.batas-kecamatan');
    Route::get('dataset/mvt/jalan-poros-desa/{z}/{x}/{y}.pbf', [JalanPorosDesaController::class, 'mvt'])->name('dataset.mvt.jalan-poros-desa');
});

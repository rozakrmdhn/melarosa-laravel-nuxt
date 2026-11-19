<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BasemapController;
use App\Http\Controllers\Admin\BatasWilayahDesaController;
use App\Http\Controllers\Admin\BatasWilayahKecamatanController;
use App\Http\Controllers\Admin\BkkdAgregasiDesaController;
use App\Http\Controllers\Admin\BkkdProposalController;
use App\Http\Controllers\Admin\BkkdProposalLokasiController;
use App\Http\Controllers\Admin\InfrastrukturSegmenController;
use App\Http\Controllers\Admin\InfrastrukturSegmenFotoController;
use App\Http\Controllers\Admin\InfrastrukturTipeController;
use App\Http\Controllers\Admin\InfrastrukturVerifikasiController;
use App\Http\Controllers\Admin\JadwalTahunAnggaranController;
use App\Http\Controllers\Admin\JalanPorosDesaController;
use App\Http\Controllers\Admin\JaringanJalanRuasController;
use App\Http\Controllers\Admin\GeoportalLayerController;
use App\Http\Controllers\Admin\LayerController;
use App\Http\Controllers\Admin\MartinLayerController;
use App\Http\Controllers\Gis\GeoportalPublicLayerController;
use App\Http\Controllers\Admin\MonitoringRealisasiController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlottingAnggaranController;
use App\Http\Controllers\Admin\PublikasiPetaDigitalController;
use App\Http\Controllers\Admin\RefBkkdController;
use App\Http\Controllers\Admin\RefSumberDanaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['ok' => true, 'message' => 'Welcome to the API'];
});

Route::prefix('api/v1')->middleware(['throttle:api'])->group(function () {
    Route::get('sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show'])->name('sanctum.csrf-cookie.alias');
    Route::get('login/{provider}/redirect', [AuthController::class, 'redirect'])->name('login.provider.redirect');
    Route::get('login/{provider}/callback', [AuthController::class, 'callback'])->name('login.provider.callback');
    Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('forgot-password', [AuthController::class, 'sendResetPasswordLink'])->middleware('throttle:5,1')->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.store');
    Route::post('verification-notification', [AuthController::class, 'verificationNotification'])->middleware('throttle:verification-notification')->name('verification.send');
    Route::get('verify-email/{uuid}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    // Basemap publik dinamis untuk seluruh peta OpenLayers & MapLibreGLJS
    Route::get('basemaps', [BasemapController::class, 'publicIndex'])->name('public.basemaps.index');

    // Publikasi Peta Digital (Portal Publik & Desa)
    Route::get('publikasi-peta', [PublikasiPetaDigitalController::class, 'publicIndex'])->name('public.publikasi-peta.index');
    Route::get('publikasi-peta/wilayah-options', [PublikasiPetaDigitalController::class, 'publicWilayahOptions'])->name('public.publikasi-peta.wilayah');
    Route::get('publikasi-peta/{idOrSlug}', [PublikasiPetaDigitalController::class, 'publicShow'])->name('public.publikasi-peta.show');
    Route::get('publikasi-peta/{idOrSlug}/download', [PublikasiPetaDigitalController::class, 'publicDownload'])->name('public.publikasi-peta.download');
    Route::get('publikasi-peta/{idOrSlug}/georef-image', [PublikasiPetaDigitalController::class, 'publicGeorefImage'])->name('public.publikasi-peta.georef-image');

    Route::middleware(["auth"])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('devices/disconnect', [AuthController::class, 'deviceDisconnect'])->name('devices.disconnect');
        Route::get('devices', [AuthController::class, 'devices'])->name('devices');
        Route::get('user', [AuthController::class, 'user'])->name('user');

        Route::post('account/update', [AccountController::class, 'update'])->name('account.update');
        Route::post('account/password', [AccountController::class, 'password'])->name('account.password');

        Route::middleware(['throttle:uploads'])->group(function () {
            Route::post('upload', [UploadController::class, 'image'])->name('upload.image');
            Route::post('upload/document', [UploadController::class, 'document'])->name('upload.document');
        });

        Route::prefix('admin')->group(function () {
            Route::get('navigation', function (\Illuminate\Http\Request $request, \App\Services\AdminNavigationService $navService) {
                return response()->json([
                    'ok' => true,
                    'data' => $navService->getFilteredMenu($request->user()),
                ]);
            })->name('admin.navigation');

            Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles-view')->name('admin.roles.index');

            // Pengaturan Basemap Dinamis
            Route::get('basemaps', [BasemapController::class, 'index'])->middleware('permission:layers-view|layers-manage|settings-view|settings-manage')->name('admin.basemaps.index');
            Route::post('basemaps', [BasemapController::class, 'store'])->middleware('permission:layers-manage|settings-manage')->name('admin.basemaps.store');
            Route::put('basemaps/{id}', [BasemapController::class, 'update'])->middleware('permission:layers-manage|settings-manage')->name('admin.basemaps.update');
            Route::patch('basemaps/{id}/set-default', [BasemapController::class, 'setDefault'])->middleware('permission:layers-manage|settings-manage')->name('admin.basemaps.set-default');
            Route::delete('basemaps/{id}', [BasemapController::class, 'destroy'])->middleware('permission:layers-manage|settings-manage')->name('admin.basemaps.destroy');

            // Publikasi Peta Digital (Admin CRUD + Upload PDF Aman + Georeferencing MapLibre)
            Route::get('publikasi-peta', [PublikasiPetaDigitalController::class, 'index'])->middleware('permission:layers-view|layers-manage|batas-desa-view|batas-desa-manage|settings-view|settings-manage')->name('admin.publikasi-peta.index');
            Route::post('publikasi-peta', [PublikasiPetaDigitalController::class, 'store'])->middleware(['permission:layers-manage|batas-desa-manage|settings-manage', 'throttle:uploads'])->name('admin.publikasi-peta.store');
            Route::get('publikasi-peta/{id}', [PublikasiPetaDigitalController::class, 'show'])->middleware('permission:layers-view|layers-manage|batas-desa-view|batas-desa-manage|settings-view|settings-manage')->name('admin.publikasi-peta.show');
            Route::put('publikasi-peta/{id}', [PublikasiPetaDigitalController::class, 'update'])->middleware(['permission:layers-manage|batas-desa-manage|settings-manage', 'throttle:uploads'])->name('admin.publikasi-peta.update');
            Route::post('publikasi-peta/{id}', [PublikasiPetaDigitalController::class, 'update'])->middleware(['permission:layers-manage|batas-desa-manage|settings-manage', 'throttle:uploads'])->name('admin.publikasi-peta.update-post');
            Route::get('publikasi-peta/{id}/wilayah-boundary', [PublikasiPetaDigitalController::class, 'wilayahBoundary'])->middleware('permission:layers-view|layers-manage|batas-desa-view|batas-desa-manage|settings-view|settings-manage')->name('admin.publikasi-peta.wilayah-boundary');
            Route::post('publikasi-peta/{id}/georeference', [PublikasiPetaDigitalController::class, 'saveGeoreference'])->middleware(['permission:layers-manage|batas-desa-manage|settings-manage', 'throttle:uploads'])->name('admin.publikasi-peta.georeference.save');
            Route::delete('publikasi-peta/{id}/georeference', [PublikasiPetaDigitalController::class, 'clearGeoreference'])->middleware('permission:layers-manage|batas-desa-manage|settings-manage')->name('admin.publikasi-peta.georeference.clear');
            Route::patch('publikasi-peta/{id}/toggle-publish', [PublikasiPetaDigitalController::class, 'togglePublish'])->middleware('permission:layers-manage|batas-desa-manage|settings-manage')->name('admin.publikasi-peta.toggle-publish');
            Route::delete('publikasi-peta/{id}', [PublikasiPetaDigitalController::class, 'destroy'])->middleware('permission:layers-manage|batas-desa-manage|settings-manage')->name('admin.publikasi-peta.destroy');
            Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles-view')->name('admin.roles.show');
            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles-manage|roles-create')->name('admin.roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles-manage|roles-update')->name('admin.roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles-manage|roles-delete')->name('admin.roles.destroy');

            Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions-view')->name('admin.permissions.index');
            Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions-manage|permissions-create')->name('admin.permissions.store');
            Route::put('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions-manage|permissions-update')->name('admin.permissions.update');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions-manage|permissions-delete')->name('admin.permissions.destroy');

            Route::get('users', [UserController::class, 'index'])->middleware('permission:users-view')->name('admin.users.index');
            Route::get('users/active', [UserController::class, 'activeUsers'])->middleware('permission:users-view')->name('admin.users.active');
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
            Route::get('jalan-poros-desa', [JalanPorosDesaController::class, 'index'])->middleware('permission:jalan-poros-desa-view|jalan-poros-desa.view|jalan-view|jalan-poros-desa-manage|jalan-poros-desa.manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.jalan-poros-desa.index');
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
            Route::get('layers/stack', [LayerController::class, 'stack'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.stack.v1');
            Route::post('layers', [LayerController::class, 'store'])->middleware('permission:layers-create|layers-manage')->name('admin.layers.store');
            Route::post('layers/reorder', [LayerController::class, 'reorder'])->middleware('permission:layers-update|layers-manage')->name('admin.layers.reorder');
            Route::get('layers/{layer}', [LayerController::class, 'show'])->middleware('permission:layers-view')->name('admin.layers.show');
            Route::put('layers/{layer}', [LayerController::class, 'update'])->middleware('permission:layers-update|layers-manage')->name('admin.layers.update');
            Route::delete('layers/{layer}', [LayerController::class, 'destroy'])->middleware('permission:layers-delete|layers-manage')->name('admin.layers.destroy');
            Route::patch('layers/{layer}/toggle-active', [LayerController::class, 'toggleActive'])->middleware('permission:layers-update|layers-manage')->name('admin.layers.toggle-active');
            Route::get('layers/{layer}/attributes', [LayerController::class, 'attributes'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.attributes');

            // Infrastruktur Master Tipe
            Route::get('infrastruktur-tipe', [InfrastrukturTipeController::class, 'index'])->middleware('permission:infrastruktur-tipe-view|infrastruktur-tipe-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.infrastruktur-tipe.index');
            Route::get('infrastruktur-tipe/{id}', [InfrastrukturTipeController::class, 'show'])->middleware('permission:infrastruktur-tipe-view|infrastruktur-tipe-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.infrastruktur-tipe.show');
            Route::post('infrastruktur-tipe', [InfrastrukturTipeController::class, 'store'])->middleware('permission:infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.store');
            Route::put('infrastruktur-tipe/{id}', [InfrastrukturTipeController::class, 'update'])->middleware('permission:infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.update');
            Route::delete('infrastruktur-tipe/{id}', [InfrastrukturTipeController::class, 'destroy'])->middleware('permission:infrastruktur-tipe-manage')->name('admin.infrastruktur-tipe.destroy');

            // Referensi Sumber Dana
            Route::get('ref-sumber-dana', [RefSumberDanaController::class, 'index'])->middleware('permission:ref-sumber-dana-view|ref-sumber-dana-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update|plotting-anggaran-view|plotting-anggaran-manage')->name('admin.ref-sumber-dana.index');
            Route::get('ref-sumber-dana/{id}', [RefSumberDanaController::class, 'show'])->middleware('permission:ref-sumber-dana-view|ref-sumber-dana-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update|plotting-anggaran-view|plotting-anggaran-manage')->name('admin.ref-sumber-dana.show');
            Route::post('ref-sumber-dana', [RefSumberDanaController::class, 'store'])->middleware('permission:ref-sumber-dana-manage')->name('admin.ref-sumber-dana.store');
            Route::put('ref-sumber-dana/{id}', [RefSumberDanaController::class, 'update'])->middleware('permission:ref-sumber-dana-manage')->name('admin.ref-sumber-dana.update');
            Route::delete('ref-sumber-dana/{id}', [RefSumberDanaController::class, 'destroy'])->middleware('permission:ref-sumber-dana-manage')->name('admin.ref-sumber-dana.destroy');

            // Jadwal Tahun Anggaran & Pengaturan Wilayah Desa
            Route::get('jadwal-anggaran/active', [JadwalTahunAnggaranController::class, 'active'])->middleware('permission:jadwal-anggaran-view|jadwal-anggaran-manage|plotting-anggaran-view|plotting-anggaran-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.jadwal-anggaran.active');
            Route::get('jadwal-anggaran', [JadwalTahunAnggaranController::class, 'index'])->middleware('permission:jadwal-anggaran-view|jadwal-anggaran-manage|plotting-anggaran-manage')->name('admin.jadwal-anggaran.index');
            Route::post('jadwal-anggaran', [JadwalTahunAnggaranController::class, 'store'])->middleware('permission:jadwal-anggaran-manage|plotting-anggaran-manage')->name('admin.jadwal-anggaran.store');
            Route::put('jadwal-anggaran/{id}', [JadwalTahunAnggaranController::class, 'update'])->middleware('permission:jadwal-anggaran-manage|plotting-anggaran-manage')->name('admin.jadwal-anggaran.update');
            Route::delete('jadwal-anggaran/{id}', [JadwalTahunAnggaranController::class, 'destroy'])->middleware('permission:jadwal-anggaran-manage|plotting-anggaran-manage')->name('admin.jadwal-anggaran.destroy');
            Route::post('jadwal-anggaran/{id}/overrides', [JadwalTahunAnggaranController::class, 'storeOverride'])->middleware('permission:jadwal-anggaran-manage|plotting-anggaran-manage')->name('admin.jadwal-anggaran.overrides.store');
            Route::delete('jadwal-anggaran/{id}/overrides/{overrideId}', [JadwalTahunAnggaranController::class, 'destroyOverride'])->middleware('permission:jadwal-anggaran-manage|plotting-anggaran-manage')->name('admin.jadwal-anggaran.overrides.destroy');

            // Plotting Anggaran
            Route::get('plotting-anggaran', [PlottingAnggaranController::class, 'index'])->middleware('permission:plotting-anggaran-view|plotting-anggaran-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.plotting-anggaran.index');
            Route::post('plotting-anggaran', [PlottingAnggaranController::class, 'store'])->middleware('permission:plotting-anggaran-manage|infrastruktur-segmen-create')->name('admin.plotting-anggaran.store');
            Route::get('plotting-anggaran/{id}', [PlottingAnggaranController::class, 'show'])->middleware('permission:plotting-anggaran-view|plotting-anggaran-manage|infrastruktur-segmen-view|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.plotting-anggaran.show');
            Route::put('plotting-anggaran/{id}', [PlottingAnggaranController::class, 'update'])->middleware('permission:plotting-anggaran-manage|infrastruktur-segmen-create|infrastruktur-segmen-update')->name('admin.plotting-anggaran.update');
            Route::delete('plotting-anggaran/{id}', [PlottingAnggaranController::class, 'destroy'])->middleware('permission:plotting-anggaran-manage')->name('admin.plotting-anggaran.destroy');

            // Infrastruktur Segmen (Spasial)
            Route::get('infrastruktur-segmen', [InfrastrukturSegmenController::class, 'index'])->middleware('permission:infrastruktur-segmen-view')->name('admin.infrastruktur-segmen.index');
            Route::post('infrastruktur-segmen', [InfrastrukturSegmenController::class, 'store'])->middleware('permission:infrastruktur-segmen-create')->name('admin.infrastruktur-segmen.store');
            Route::get('infrastruktur-segmen/{id}', [InfrastrukturSegmenController::class, 'show'])->middleware('permission:infrastruktur-segmen-view')->name('admin.infrastruktur-segmen.show');
            Route::put('infrastruktur-segmen/{id}', [InfrastrukturSegmenController::class, 'update'])->middleware('permission:infrastruktur-segmen-update')->name('admin.infrastruktur-segmen.update');
            Route::patch('infrastruktur-segmen/{id}/geometry', [InfrastrukturSegmenController::class, 'updateGeometry'])->middleware('permission:infrastruktur-segmen-update')->name('admin.infrastruktur-segmen.update-geometry');
            Route::post('infrastruktur-segmen/{id}/split', [InfrastrukturSegmenController::class, 'split'])->middleware('permission:infrastruktur-segmen-update')->name('admin.infrastruktur-segmen.split');
            Route::delete('infrastruktur-segmen/{id}', [InfrastrukturSegmenController::class, 'destroy'])->middleware('permission:infrastruktur-segmen-delete')->name('admin.infrastruktur-segmen.destroy');

            // Dokumentasi Foto Lapangan Segmen (Privat + Sanitasi Ketat)
            Route::get('infrastruktur-segmen/{id}/fotos', [InfrastrukturSegmenFotoController::class, 'index'])->middleware('permission:infrastruktur-segmen-view')->name('admin.infrastruktur-segmen.fotos.index');
            Route::post('infrastruktur-segmen/{id}/fotos', [InfrastrukturSegmenFotoController::class, 'store'])->middleware(['permission:infrastruktur-segmen-update', 'throttle:uploads'])->name('admin.infrastruktur-segmen.fotos.store');
            Route::delete('infrastruktur-segmen/{id}/fotos/{fotoId}', [InfrastrukturSegmenFotoController::class, 'destroy'])->middleware('permission:infrastruktur-segmen-update')->name('admin.infrastruktur-segmen.fotos.destroy');
            Route::get('infrastruktur-segmen/{id}/fotos/{fotoId}/file', [InfrastrukturSegmenFotoController::class, 'file'])->middleware('permission:infrastruktur-segmen-view')->name('admin.infrastruktur-segmen.fotos.file');

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

            // Audit Logs
            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs-view|audit-logs-access|audit-logs-manage')->name('admin.audit-logs.index');
            Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit-logs-view|audit-logs-access|audit-logs-manage')->name('admin.audit-logs.show');

            // Layer Management
            Route::get('layers', [LayerController::class, 'index'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.index');
            Route::get('layers/stack', [LayerController::class, 'stack'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.stack');
            Route::post('layers', [LayerController::class, 'store'])->middleware('permission:layers-create|layers-manage')->name('admin.layers.store');
            Route::post('layers/reorder', [LayerController::class, 'reorder'])->middleware('permission:layers-edit|layers-update|layers-manage')->name('admin.layers.reorder');
            Route::post('layers/geoportal/sync', [GeoportalLayerController::class, 'sync'])->middleware('permission:layers-create|layers-update|layers-manage')->name('admin.layers.geoportal.sync');
            Route::get('layers/geoportal/sync-history', [GeoportalLayerController::class, 'syncHistory'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.geoportal.history');
            Route::get('layers/martin/status', [MartinLayerController::class, 'status'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.martin.status');
            Route::get('layers/martin/catalog', [MartinLayerController::class, 'catalog'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.martin.catalog');
            Route::post('layers/martin/sync', [MartinLayerController::class, 'sync'])->middleware('permission:layers-create|layers-update|layers-manage')->name('admin.layers.martin.sync');
            Route::get('layers/martin/sync-history', [MartinLayerController::class, 'syncHistory'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.martin.history');
            Route::get('layers/{layer}', [LayerController::class, 'show'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.show');
            Route::put('layers/{layer}', [LayerController::class, 'update'])->middleware('permission:layers-edit|layers-update|layers-manage')->name('admin.layers.update');
            Route::delete('layers/{layer}', [LayerController::class, 'destroy'])->middleware('permission:layers-delete|layers-manage')->name('admin.layers.destroy');
            Route::patch('layers/{layer}/toggle-active', [LayerController::class, 'toggleActive'])->middleware('permission:layers-edit|layers-update|layers-manage')->name('admin.layers.toggle-active');
            Route::get('layers/{layer}/attributes', [LayerController::class, 'attributes'])->middleware('permission:layers-view|layers-manage')->name('admin.layers.attributes.v2');

            // Master Referensi BKKD
            Route::get('ref-bkkd/opd', [RefBkkdController::class, 'opd'])->name('admin.ref-bkkd.opd');
            Route::get('ref-bkkd/bidang', [RefBkkdController::class, 'bidang'])->name('admin.ref-bkkd.bidang');

            // BKKD Proposal
            Route::get('bkkd/proposal', [BkkdProposalController::class, 'index'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.proposal.index');
            Route::post('bkkd/proposal', [BkkdProposalController::class, 'store'])->middleware('permission:bkkd-proposal-create')->name('admin.bkkd.proposal.store');
            Route::get('bkkd/proposal/import/template', [BkkdProposalController::class, 'importTemplate'])->middleware('permission:bkkd-proposal-create')->name('admin.bkkd.proposal.import.template');
            Route::post('bkkd/proposal/import', [BkkdProposalController::class, 'importExcel'])->middleware('permission:bkkd-proposal-create')->name('admin.bkkd.proposal.import');
            Route::get('bkkd/proposal/{id}', [BkkdProposalController::class, 'show'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.proposal.show');
            Route::put('bkkd/proposal/{id}', [BkkdProposalController::class, 'update'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.update');
            Route::delete('bkkd/proposal/{id}', [BkkdProposalController::class, 'destroy'])->middleware('permission:bkkd-proposal-delete')->name('admin.bkkd.proposal.destroy');
            Route::post('bkkd/proposal/{id}/restore', [BkkdProposalController::class, 'restore'])->middleware('permission:bkkd-proposal-delete')->name('admin.bkkd.proposal.restore');
            Route::get('bkkd/proposal/{id}/riwayat', [BkkdProposalController::class, 'riwayat'])->middleware('permission:bkkd-riwayat-view')->name('admin.bkkd.proposal.riwayat');

            // BKKD Multi-Lokasi LineString & Analisa Spasial
            Route::get('bkkd/ref-jalan-poros', [BkkdProposalLokasiController::class, 'jalanPorosByDesa'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.ref-jalan-poros');
            Route::post('bkkd/generate-route', [BkkdProposalLokasiController::class, 'generateRoute'])->middleware('permission:bkkd-proposal-view|bkkd-proposal-create|bkkd-proposal-update')->name('admin.bkkd.generate-route');
            Route::get('bkkd/analisa-spasial', [BkkdProposalLokasiController::class, 'daftarKonfirmasi'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.analisa-spasial.index');
            Route::post('bkkd/analisa-spasial/sync-pending', [BkkdProposalLokasiController::class, 'syncPendingAnalysis'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.analisa-spasial.sync-pending');
            Route::get('bkkd/proposal/{id}/lokasi', [BkkdProposalLokasiController::class, 'index'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.proposal.lokasi.index');
            Route::post('bkkd/proposal/{id}/lokasi', [BkkdProposalLokasiController::class, 'store'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.lokasi.store');
            Route::put('bkkd/proposal/{id}/lokasi/{lokasiId}', [BkkdProposalLokasiController::class, 'update'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.lokasi.update');
            Route::delete('bkkd/proposal/{id}/lokasi/{lokasiId}', [BkkdProposalLokasiController::class, 'destroy'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.lokasi.destroy');
            Route::post('bkkd/proposal/{id}/analisa-spasial', [BkkdProposalLokasiController::class, 'reanalyze'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.analisa-spasial');
            Route::post('bkkd/proposal/{id}/konfirmasi-desa', [BkkdProposalLokasiController::class, 'simpanKonfirmasiDesa'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.konfirmasi-desa');

            // Modul Standalone Master Jaringan Jalan Kabupaten Bojonegoro
            Route::get('jaringan-jalan', [JaringanJalanRuasController::class, 'index'])->middleware('permission:bkkd-proposal-view|jalan-poros-desa-view')->name('admin.jaringan-jalan.index');
            Route::get('jaringan-jalan/kandidat', [JaringanJalanRuasController::class, 'kandidat'])->middleware('permission:bkkd-proposal-view|jalan-poros-desa-view')->name('admin.jaringan-jalan.kandidat');
            Route::put('jaringan-jalan/{ruasId}', [JaringanJalanRuasController::class, 'update'])->middleware('permission:bkkd-proposal-update|jalan-poros-desa-manage')->name('admin.jaringan-jalan.update');
            Route::delete('jaringan-jalan/{ruasId}', [JaringanJalanRuasController::class, 'destroy'])->middleware('permission:bkkd-proposal-update|jalan-poros-desa-manage')->name('admin.jaringan-jalan.destroy');
            Route::post('jaringan-jalan/promosi/{id}/lokasi/{lokasiId}/ajukan', [JaringanJalanRuasController::class, 'ajukan'])->middleware('permission:bkkd-proposal-update')->name('admin.jaringan-jalan.promosi.ajukan');
            Route::post('jaringan-jalan/promosi/{id}/lokasi/{lokasiId}/validasi', [JaringanJalanRuasController::class, 'validasi'])->middleware('permission:bkkd-proposal-update')->name('admin.jaringan-jalan.promosi.validasi');

            // Alias BKKD untuk Master Jaringan Jalan
            Route::get('bkkd/jaringan-jalan', [JaringanJalanRuasController::class, 'index'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.jaringan-jalan.index');
            Route::get('bkkd/kandidat-ruas', [JaringanJalanRuasController::class, 'kandidat'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.kandidat-ruas.index');
            Route::post('bkkd/proposal/{id}/lokasi/{lokasiId}/ajukan-ruas', [JaringanJalanRuasController::class, 'ajukan'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.lokasi.ajukan-ruas');
            Route::post('bkkd/proposal/{id}/lokasi/{lokasiId}/validasi-ruas', [JaringanJalanRuasController::class, 'validasi'])->middleware('permission:bkkd-proposal-update')->name('admin.bkkd.proposal.lokasi.validasi-ruas');

            // BKKD Agregasi Wilayah Desa
            Route::get('bkkd/agregasi-desa', [BkkdAgregasiDesaController::class, 'index'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.agregasi-desa.index');
            Route::get('bkkd/agregasi-desa/export', [BkkdAgregasiDesaController::class, 'export'])->middleware('permission:bkkd-proposal-view')->name('admin.bkkd.agregasi-desa.export');

            // BKKD Global Riwayat Log
            Route::get('bkkd/riwayat', [BkkdProposalController::class, 'riwayatSemua'])->middleware('permission:bkkd-riwayat-view')->name('admin.bkkd.riwayat.index');

            // Backup & Restore Management (Super Admin only)
            Route::prefix('backups')->middleware('role:admin')->group(function () {
                Route::get('/', [BackupController::class, 'index'])->name('admin.backups.index');
                Route::get('statistics', [BackupController::class, 'statistics'])->name('admin.backups.statistics');
                Route::get('schedules', [BackupController::class, 'schedules'])->name('admin.backups.schedules');
                Route::put('schedules', [BackupController::class, 'updateSchedules'])->middleware('throttle:backup-mutations')->name('admin.backups.schedules.update');
                Route::get('restores', [BackupController::class, 'restores'])->name('admin.backups.restores');
                Route::get('jobs/{id}', [BackupController::class, 'jobStatus'])->whereUuid('id')->name('admin.backups.jobs.status');
                Route::post('/', [BackupController::class, 'store'])->middleware('throttle:backup-mutations')->name('admin.backups.store');
                Route::post('validate', [BackupController::class, 'validateBackup'])->middleware('throttle:backup-restore')->name('admin.backups.validate');
                Route::post('restore', [BackupController::class, 'restore'])->middleware('throttle:backup-restore')->name('admin.backups.restore');
                Route::post('upload', [BackupController::class, 'upload'])->middleware('throttle:backup-mutations')->name('admin.backups.upload');
                Route::get('{id}/download-url', [BackupController::class, 'downloadUrl'])->whereUuid('id')->name('admin.backups.download-url');
                Route::get('{id}/download', [BackupController::class, 'download'])->whereUuid('id')->name('admin.backups.download');
                Route::get('{id}', [BackupController::class, 'show'])->whereUuid('id')->name('admin.backups.show');
                Route::delete('{id}', [BackupController::class, 'destroy'])->middleware('throttle:backup-mutations')->whereUuid('id')->name('admin.backups.destroy');
            });
        });
    });

    // Map Viewer Active Layers Feed (Public / Map accessible)
    Route::get('layers', [LayerController::class, 'activeLayers'])->name('layers.active');
    Route::get('layers/martin/status', [MartinLayerController::class, 'status'])->name('layers.martin.status');
    Route::get('layers/{layer}/feature-geometry', [LayerController::class, 'featureGeometry'])->name('layers.feature-geometry');
    Route::get('layers/geoportal', [GeoportalPublicLayerController::class, 'index'])->name('layers.geoportal.index');
    Route::get('layers/geoportal/status', [GeoportalPublicLayerController::class, 'status'])->name('layers.geoportal.status');
    Route::get('layers/geoportal/{layer}/metadata', [GeoportalPublicLayerController::class, 'metadata'])->name('layers.geoportal.metadata');
    Route::get('layers/geoportal/{layer}/attributes', [GeoportalPublicLayerController::class, 'attributes'])->name('layers.geoportal.attributes');
    Route::get('layers/geoportal/{layer}/csw-xml', [GeoportalPublicLayerController::class, 'cswXml'])->name('layers.geoportal.cswXml');

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
            $http = \Illuminate\Support\Facades\Http::timeout(10);
            if (!app()->isProduction()) {
                $http = $http->withoutVerifying();
            }
            $response = $http->get($url);

            if ($response->successful()) {
                return $response->json() ?? ['type' => 'FeatureCollection', 'features' => [], 'raw' => $response->body()];
            }
            return response()->json(['features' => [], 'error' => 'WMS request failed: ' . $response->status()], $response->status());
        } catch (\Throwable $e) {
            return response()->json(['features' => [], 'error' => 'WMS request failed: ' . $e->getMessage()], 500);
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
                $http = \Illuminate\Support\Facades\Http::timeout(8);
                if (!app()->isProduction()) {
                    $http = $http->withoutVerifying();
                }
                $response = $http->get('https://kugi.ina-sdi.or.id/kugiapi/featuretypegetbycode', [
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

    // Geopark Bojonegoro API Proxy dengan Cache 15 Menit & Direct Hit ke Endpoint Resmi
    Route::get('geopark/sites', function (\Illuminate\Http\Request $request) {
        $force = (bool) $request->query('refresh', false);
        $cacheKey = 'geopark_bojonegoro_sites_list_v2';

        if ($force && $request->user()) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 900, function () {
            try {
                $http = \Illuminate\Support\Facades\Http::timeout(12)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'MelarosaWebGIS/1.0',
                    ]);
                if (!app()->isProduction()) {
                    $http = $http->withoutVerifying();
                }
                $response = $http->get('https://geopark.bojonegorokab.go.id/api/diversity/list-diversity-site-list');

                if ($response->successful()) {
                    $json = $response->json();
                    return $json['data'] ?? [];
                }
                return [];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Geopark API fetch error: ' . $e->getMessage());
                return [];
            }
        });

        return response()->json([
            'ok' => true,
            'count' => count($data),
            'data' => $data,
        ]);
    })->name('geopark.sites');
});


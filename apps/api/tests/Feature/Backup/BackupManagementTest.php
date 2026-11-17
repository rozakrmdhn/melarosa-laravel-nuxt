<?php

use App\Models\BackupHistory;
use App\Models\BackupRestoreHistory;
use App\Models\BackupSchedule;
use App\Models\User;
use App\Services\Backup\BackupArchiveBuilder;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'verifierBappeda', 'guard_name' => 'web']);

    $this->testRoot = storage_path('app/private/temporary/pest_backup_'.Str::uuid());
    $this->backupStorageDir = $this->testRoot.'/backups_disk';
    $this->tempWorkDir = $this->testRoot.'/work_temp';
    $this->publicFilesDir = $this->testRoot.'/storage_public';
    $this->privateFilesDir = $this->testRoot.'/storage_private';

    File::ensureDirectoryExists($this->backupStorageDir);
    File::ensureDirectoryExists($this->tempWorkDir);
    File::ensureDirectoryExists($this->publicFilesDir.'/avatars');
    File::ensureDirectoryExists($this->privateFilesDir.'/lampiran');
    File::ensureDirectoryExists($this->privateFilesDir.'/backups');
    File::ensureDirectoryExists($this->privateFilesDir.'/temporary');
    File::ensureDirectoryExists($this->privateFilesDir.'/logs');

    file_put_contents($this->publicFilesDir.'/avatars/demo.txt', 'avatar-content-ok');
    file_put_contents($this->privateFilesDir.'/lampiran/dokumen.pdf', 'pdf-content-ok');
    // File ini harus selalu dikecualikan saat File Backup
    file_put_contents($this->privateFilesDir.'/backups/old.enc', 'should-be-excluded');
    file_put_contents($this->privateFilesDir.'/temporary/tmp.bin', 'should-be-excluded');
    file_put_contents($this->privateFilesDir.'/logs/laravel.log', 'should-be-excluded');

    config([
        'queue.default' => 'sync',
        'filesystems.disks.backups.root' => $this->backupStorageDir,
        'backup.disk' => 'backups',
        'backup.temp_directory' => $this->tempWorkDir,
        'backup.encryption.enabled' => true,
        'backup.encryption.key' => 'base64:'.base64_encode(random_bytes(32)),
        'backup.files.include_paths' => [
            'public' => $this->publicFilesDir,
            'private' => $this->privateFilesDir,
        ],
        'backup.restore.allow_overwrite_active_db' => false,
        'backup.notifications.channels' => ['database'],
    ]);

    Storage::forgetDisk('backups');

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create(['email_verified_at' => now()]);
    $this->regularUser->assignRole('user');

    $this->verifierUser = User::factory()->create(['email_verified_at' => now()]);
    $this->verifierUser->assignRole('verifierBappeda');
});

afterEach(function () {
    File::deleteDirectory($this->testRoot);
});

it('menolak akses seluruh endpoint backup bagi user non-Super Admin dan memfilter navigasi', function () {
    // Non-autentikasi ditolak 401
    $this->getJson('/api/v1/admin/backups')->assertStatus(401);

    // Role user maupun verifier ditolak 403 di seluruh endpoint
    foreach ([$this->regularUser, $this->verifierUser] as $actor) {
        $this->actingAs($actor)->getJson('/api/v1/admin/backups')->assertStatus(403);
        $this->actingAs($actor)->getJson('/api/v1/admin/backups/statistics')->assertStatus(403);
        $this->actingAs($actor)->postJson('/api/v1/admin/backups', ['backup_type' => 'database'])->assertStatus(403);
        $this->actingAs($actor)->getJson('/api/v1/admin/backups/schedules')->assertStatus(403);
        $this->actingAs($actor)->putJson('/api/v1/admin/backups/schedules', ['schedules' => []])->assertStatus(403);
        $this->actingAs($actor)->postJson('/api/v1/admin/backups/validate', ['backup_id' => Str::uuid()->toString()])->assertStatus(403);
        $this->actingAs($actor)->postJson('/api/v1/admin/backups/restore', [])->assertStatus(403);

        // Menu navigasi admin tidak boleh menyertakan Backup & Restore untuk non-admin
        $navRes = $this->actingAs($actor)->getJson('/api/v1/admin/navigation')->assertOk();
        $labels = collect($navRes->json('data'))->pluck('label')->all();
        expect($labels)->not->toContain('Backup & Restore');
    }

    // Super Admin (admin) melihat menu Backup & Restore
    $adminNav = $this->actingAs($this->admin)->getJson('/api/v1/admin/navigation')->assertOk();
    expect(collect($adminNav->json('data'))->pluck('label')->all())->toContain('Backup & Restore');
});

it('menjalankan backup database, file (tanpa cache/log/backups), dan full backup terenkripsi lengkap dengan audit log & notifikasi', function () {
    // 1. Database backup
    $dbRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'database',
        'backup_name' => 'Snapshot DB Uji',
    ]);
    $dbRes->assertStatus(202);
    $dbId = $dbRes->json('data.id');
    $dbBackup = BackupHistory::findOrFail($dbId);
    expect($dbBackup->status)->toBe(BackupHistory::STATUS_COMPLETED)
        ->and($dbBackup->is_encrypted)->toBeTrue()
        ->and($dbBackup->checksum_sha256)->not->toBeNull()
        ->and(Storage::disk('backups')->exists($dbBackup->file_path))->toBeTrue();

    // 2. File backup dengan verifikasi exclusion
    $fileRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'file',
        'backup_name' => 'Snapshot Files Uji',
        'encrypt' => false,
    ]);
    $fileRes->assertStatus(202);
    $fileBackup = BackupHistory::findOrFail($fileRes->json('data.id'));
    expect($fileBackup->status)->toBe(BackupHistory::STATUS_COMPLETED)
        ->and($fileBackup->metadata['files_count'])->toBe(2);

    // Pastikan isi files.zip hanya memuat avatar & lampiran, bukan backups/temporary/logs
    $pkgInspect = app(BackupArchiveBuilder::class)->inspectAndVerifyPackage(
        Storage::disk('backups')->path($fileBackup->file_path),
        $this->testRoot.'/inspect_file_pkg'
    );
    expect($pkgInspect['checksums_verified'])->toBeTrue()
        ->and($pkgInspect['has_files_zip'])->toBeTrue();

    $filesZip = new ZipArchive;
    $filesZip->open($this->testRoot.'/inspect_file_pkg/files.zip');
    $names = [];
    for ($i = 0; $i < $filesZip->numFiles; $i++) {
        $names[] = $filesZip->getNameIndex($i);
    }
    $filesZip->close();

    expect($names)->toContain('public/avatars/demo.txt')
        ->and($names)->toContain('private/lampiran/dokumen.pdf')
        ->and(implode(',', $names))->not->toContain('should-be-excluded')
        ->and(implode(',', $names))->not->toContain('laravel.log');

    // 3. Full backup terenkripsi
    $fullRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'full',
        'backup_name' => 'Full Snapshot Uji',
    ]);
    $fullRes->assertStatus(202);
    $fullBackup = BackupHistory::findOrFail($fullRes->json('data.id'));
    expect($fullBackup->status)->toBe(BackupHistory::STATUS_COMPLETED)
        ->and($fullBackup->includes_files)->toBeTrue()
        ->and($fullBackup->is_encrypted)->toBeTrue();

    // Notifikasi database & Audit log tercatat
    expect($this->admin->notifications()->count())->toBeGreaterThanOrEqual(3);
    $this->assertDatabaseHas('audit_logs', [
        'module' => 'backups',
        'event' => 'backup.completed',
        'auditable_id' => $fullBackup->id,
    ]);
});

it('mengunduh arsip menggunakan signed URL terotentikasi dan memblokir unduhan tanpa signature sah', function () {
    $res = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'file',
    ])->assertStatus(202);

    $backupId = $res->json('data.id');

    // Tanpa signature ditolak 403
    $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/backups/{$backupId}/download")
        ->assertStatus(403);

    // Signed URL berhasil streaming arsip dan mencatat audit log
    $signedRes = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/backups/{$backupId}/download-url")
        ->assertOk();

    $signedUrl = $signedRes->json('data.url');
    $dl = $this->actingAs($this->admin)->get($signedUrl);
    $dl->assertOk();
    expect($dl->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $this->assertDatabaseHas('audit_logs', [
        'module' => 'backups',
        'event' => 'backup.downloaded',
        'auditable_id' => $backupId,
    ]);
});

it('mendeteksi arsip backup yang korup dan menggagalkan restore dengan pesan aman', function () {
    $res = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'database',
    ])->assertStatus(202);

    $backup = BackupHistory::findOrFail($res->json('data.id'));

    // Validasi awal harus lulus
    $validOk = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/validate', [
        'backup_id' => $backup->id,
    ])->assertOk();
    expect($validOk->json('data.valid'))->toBeTrue()
        ->and($validOk->json('data.checksum_verified'))->toBeTrue()
        ->and($validOk->json('data.decryption_verified'))->toBeTrue();

    // Rusak 1 byte arsip pada disk
    $absPath = Storage::disk('backups')->path($backup->file_path);
    $bytes = file_get_contents($absPath);
    $bytes[100] = chr(ord($bytes[100]) ^ 0x55);
    file_put_contents($absPath, $bytes);

    $validCorrupt = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/validate', [
        'backup_id' => $backup->id,
    ])->assertOk();
    expect($validCorrupt->json('data.valid'))->toBeFalse()
        ->and($validCorrupt->json('data.checksum_verified'))->toBeFalse();

    // Upaya restore arsip korup wajib ditolak
    $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/restore', [
        'backup_id' => $backup->id,
        'restore_scope' => 'database',
        'target_mode' => 'isolated',
        'target_database' => 'melarosa_restore_verify',
        'confirm_understood' => true,
        'confirmation_phrase' => 'KONFIRMASI RESTORE',
    ])->assertStatus(422);
});

it('mengelola jadwal otomatis, menolak penghapusan arsip yang sedang direstore, dan menjalankan retensi cleanup', function () {
    $schedRes = $this->actingAs($this->admin)->getJson('/api/v1/admin/backups/schedules')->assertOk();
    expect($schedRes->json('data'))->toHaveCount(3);

    $this->actingAs($this->admin)->putJson('/api/v1/admin/backups/schedules', [
        'schedules' => [
            [
                'backup_type' => 'database',
                'name' => 'Backup Database Malam',
                'is_active' => true,
                'frequency' => 'daily',
                'run_time' => '01:30',
                'retention_days' => 21,
                'encryption_enabled' => true,
                'replicate_to_remote' => false,
            ],
        ],
    ])->assertOk();

    $schedule = BackupSchedule::where('backup_type', 'database')->firstOrFail();
    expect($schedule->run_time)->toBe('01:30')
        ->and($schedule->retention_days)->toBe(21);

    // Paksa jadwal jatuh tempo & jalankan artisan scheduler command
    $schedule->update(['next_run_at' => now()->subMinutes(5)]);
    $this->artisan('backup:run', ['--scheduled' => true, '--sync' => true])->assertSuccessful();
    expect(BackupHistory::where('trigger_type', BackupHistory::TRIGGER_SCHEDULED)->count())->toBe(1);

    // Buat 2 arsip kadaluwarsa: 1 bebas dihapus, 1 sedang dipakai restore aktif
    $expiredFree = BackupHistory::where('trigger_type', BackupHistory::TRIGGER_SCHEDULED)->firstOrFail();
    $expiredFree->update(['expires_at' => now()->subDay()]);

    $busyRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'file',
    ])->assertStatus(202);
    $expiredBusy = BackupHistory::findOrFail($busyRes->json('data.id'));
    $expiredBusy->update(['expires_at' => now()->subDay()]);

    BackupRestoreHistory::create([
        'backup_history_id' => $expiredBusy->id,
        'source_file_name' => $expiredBusy->file_name,
        'restore_scope' => 'file',
        'target_mode' => 'isolated',
        'status' => BackupRestoreHistory::STATUS_PROCESSING,
        'executed_by' => $this->admin->id,
    ]);

    // Hapus manual backup yang sedang aktif direstore ditolak 409
    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/backups/{$expiredBusy->id}")->assertStatus(409);

    // Retention cleanup menghapus yang bebas namun melewati yang sedang dipakai restore
    $this->artisan('backup:cleanup')->assertSuccessful();
    expect(BackupHistory::find($expiredFree->id))->toBeNull()
        ->and(BackupHistory::find($expiredBusy->id))->not->toBeNull();
});

it('memvalidasi upload arsip backup eksternal serta konfirmasi bertingkat dan eksekusi restore aman', function () {
    // Upload file bukan backup harus ditolak
    $fakeFile = UploadedFile::fake()->createWithContent('malicious.php', '<?php echo "nope";');
    $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/upload', [
        'backup_file' => $fakeFile,
    ])->assertStatus(422);

    // Buat arsip valid lalu upload salinannya
    $fullRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups', [
        'backup_type' => 'full',
    ])->assertStatus(202);
    $origBackup = BackupHistory::findOrFail($fullRes->json('data.id'));
    $origPath = Storage::disk('backups')->path($origBackup->file_path);

    $uploadCopyPath = $this->testRoot.'/upload_copy.zip.enc';
    copy($origPath, $uploadCopyPath);
    $uploaded = new UploadedFile($uploadCopyPath, 'eksternal_full.zip.enc', 'application/octet-stream', null, true);

    $uplRes = $this->actingAs($this->admin)->post('/api/v1/admin/backups/upload', [
        'backup_file' => $uploaded,
        'notes' => 'Arsip DR external',
    ], ['Accept' => 'application/json']);
    $uplRes->assertStatus(201);
    $uploadedId = $uplRes->json('data.id');

    // Restore tanpa konfirmasi teks wajib gagal 422
    $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/restore', [
        'backup_id' => $uploadedId,
        'restore_scope' => 'full',
        'target_mode' => 'isolated',
        'target_database' => 'melarosa_restore_verify',
        'confirm_understood' => true,
        'confirmation_phrase' => 'SALAH',
    ])->assertStatus(422);

    // Restore langsung menimpa active DB diblokir karena allow_overwrite_active_db=false
    $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/restore', [
        'backup_id' => $uploadedId,
        'restore_scope' => 'full',
        'target_mode' => 'active',
        'confirm_understood' => true,
        'confirmation_phrase' => 'KONFIRMASI RESTORE',
    ])->assertStatus(422);

    // Restore ke target terisolasi berhasil lengkap beserta verifikasi file & SQL
    $restoreRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/backups/restore', [
        'backup_id' => $uploadedId,
        'restore_scope' => 'full',
        'target_mode' => 'isolated',
        'target_database' => 'melarosa_restore_verify',
        'confirm_understood' => true,
        'confirmation_phrase' => 'KONFIRMASI RESTORE',
    ]);
    $restoreRes->assertStatus(202);
    $restoreId = $restoreRes->json('data.id');
    $restoreRecord = BackupRestoreHistory::findOrFail($restoreId);

    expect($restoreRecord->status)->toBe(BackupRestoreHistory::STATUS_COMPLETED)
        ->and(data_get($restoreRecord->validation_report, 'files_verification.restored_files'))->toBe(2)
        ->and(data_get($restoreRecord->validation_report, 'database_verification.tables_count'))->toBeGreaterThan(0);
});


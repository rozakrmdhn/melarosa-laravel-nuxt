<?php

use App\Models\BackupHistory;
use App\Models\BackupRestoreHistory;
use App\Services\Backup\BackupService;
use App\Services\Backup\PostgresCliHelper;
use App\Services\Backup\RestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Mengambil konfigurasi PostgreSQL lokal untuk database uji terisolasi tanpa memodifikasi database aktif.
 *
 * @return array{host: string, port: string, username: string, password: string}
 */
function resolveLocalPostgresTestConfig(): array
{
    $cfg = [
        'host' => (string) env('PG_TEST_HOST', '127.0.0.1'),
        'port' => (string) env('PG_TEST_PORT', '5432'),
        'username' => (string) env('PG_TEST_USERNAME', 'postgres'),
        'password' => (string) env('PG_TEST_PASSWORD', 'postgres'),
    ];

    $envPath = base_path('.env');
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, 'DB_PASSWORD=')) {
                $cfg['password'] = trim(substr($line, 12), " \t\n\r\0\x0B\"'");
            } elseif (str_starts_with($line, 'DB_USERNAME=')) {
                $cfg['username'] = trim(substr($line, 12), " \t\n\r\0\x0B\"'");
            } elseif (str_starts_with($line, 'DB_HOST=')) {
                $cfg['host'] = trim(substr($line, 8), " \t\n\r\0\x0B\"'");
            } elseif (str_starts_with($line, 'DB_PORT=')) {
                $cfg['port'] = trim(substr($line, 8), " \t\n\r\0\x0B\"'");
            }
        }
    }

    return $cfg;
}

it('membackup dan merestore database PostgreSQL dengan ekstensi PostGIS dan data geometri secara utuh ke DB terisolasi', function () {
    $pgCli = app(PostgresCliHelper::class);
    $pgDump = $pgCli->resolveBinary('pg_dump');
    $pgRestore = $pgCli->resolveBinary('pg_restore');
    $psql = $pgCli->resolveBinary('psql');

    if (! $pgDump || ! $pgRestore || ! $psql) {
        $this->markTestSkipped('PostgreSQL client binaries (pg_dump/pg_restore/psql) tidak tersedia.');
    }

    $pgCfg = resolveLocalPostgresTestConfig();

    try {
        $pdo = new PDO(
            "pgsql:host={$pgCfg['host']};port={$pgCfg['port']};dbname=postgres",
            $pgCfg['username'],
            $pgCfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable) {
        $this->markTestSkipped('Server PostgreSQL lokal tidak dapat diakses untuk tes integrasi PostGIS.');
    }

    $suffix = strtolower(Str::random(6));
    $srcDb = "melarosa_bk_src_{$suffix}";
    $dstDb = "melarosa_bk_dst_{$suffix}";
    $tmpRoot = storage_path("app/private/temporary/pg_integ_{$suffix}");
    File::ensureDirectoryExists($tmpRoot.'/backups');
    File::ensureDirectoryExists($tmpRoot.'/temp');

    $originalConn = config('backup.connection');

    try {
        $pdo->exec("DROP DATABASE IF EXISTS \"{$srcDb}\" WITH (FORCE)");
        $pdo->exec("DROP DATABASE IF EXISTS \"{$dstDb}\" WITH (FORCE)");
        $pdo->exec("CREATE DATABASE \"{$srcDb}\"");

        $srcPdo = new PDO(
            "pgsql:host={$pgCfg['host']};port={$pgCfg['port']};dbname={$srcDb}",
            $pgCfg['username'],
            $pgCfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $srcPdo->exec('CREATE EXTENSION IF NOT EXISTS postgis');
        $srcPdo->exec('
            CREATE TABLE spatial_parcels (
                id BIGSERIAL PRIMARY KEY,
                kode VARCHAR(32) NOT NULL UNIQUE,
                geom geometry(Polygon, 4326) NOT NULL
            )
        ');
        $srcPdo->exec("
            INSERT INTO spatial_parcels (kode, geom) VALUES
            ('PRC-001', ST_GeomFromText('POLYGON((112.60 -7.95, 112.61 -7.95, 112.61 -7.96, 112.60 -7.96, 112.60 -7.95))', 4326)),
            ('PRC-002', ST_GeomFromText('POLYGON((112.62 -7.97, 112.63 -7.97, 112.63 -7.98, 112.62 -7.98, 112.62 -7.97))', 4326))
        ");
        $srcPdo = null;

        config([
            'filesystems.disks.backups.root' => $tmpRoot.'/backups',
            'backup.disk' => 'backups',
            'backup.temp_directory' => $tmpRoot.'/temp',
            'backup.encryption.enabled' => true,
            'backup.encryption.key' => 'base64:'.base64_encode(random_bytes(32)),
            'backup.connection' => 'pgsql_backup_src_test',
            'database.connections.pgsql_backup_src_test' => [
                'driver' => 'pgsql',
                'host' => $pgCfg['host'],
                'port' => $pgCfg['port'],
                'database' => $srcDb,
                'username' => $pgCfg['username'],
                'password' => $pgCfg['password'],
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ],
        ]);
        Storage::forgetDisk('backups');
        DB::purge('pgsql_backup_src_test');

        $backupService = app(BackupService::class);
        $restoreService = app(RestoreService::class);

        $pending = $backupService->createPendingBackup([
            'backup_type' => BackupHistory::TYPE_DATABASE,
            'backup_name' => 'PG PostGIS Real Test',
            'encrypt' => true,
        ]);
        $completedBackup = $backupService->executeBackup($pending);

        expect($completedBackup->status)->toBe(BackupHistory::STATUS_COMPLETED)
            ->and($completedBackup->postgis_version)->not->toBeNull()
            ->and($completedBackup->metadata['geometry_tables_count'])->toBe(1);

        $restorePending = $restoreService->createPendingRestore($completedBackup, [
            'restore_scope' => BackupHistory::TYPE_DATABASE,
            'target_mode' => BackupRestoreHistory::TARGET_MODE_ISOLATED,
            'target_database' => $dstDb,
        ]);
        $restoreCompleted = $restoreService->executeRestore($restorePending);

        expect($restoreCompleted->status)->toBe(BackupRestoreHistory::STATUS_COMPLETED)
            ->and(data_get($restoreCompleted->validation_report, 'database_verification.postgis_version'))->not->toBeNull()
            ->and(data_get($restoreCompleted->validation_report, 'database_verification.geometry_validation.0.total_rows'))->toBe(2)
            ->and(data_get($restoreCompleted->validation_report, 'database_verification.geometry_validation.0.invalid_geometries'))->toBe(0);

        $dstPdo = new PDO(
            "pgsql:host={$pgCfg['host']};port={$pgCfg['port']};dbname={$dstDb}",
            $pgCfg['username'],
            $pgCfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $count = (int) $dstPdo->query('SELECT COUNT(*) FROM spatial_parcels')->fetchColumn();
        $dstPdo->exec("INSERT INTO spatial_parcels (kode, geom) VALUES ('PRC-003', ST_GeomFromText('POLYGON((112.64 -7.95, 112.65 -7.95, 112.65 -7.96, 112.64 -7.96, 112.64 -7.95))', 4326))");
        $nextId = (int) $dstPdo->query("SELECT id FROM spatial_parcels WHERE kode = 'PRC-003'")->fetchColumn();
        $dstPdo = null;

        expect($count)->toBe(2)
            ->and($nextId)->toBeGreaterThanOrEqual(3);
    } finally {
        DB::purge('pgsql_backup_src_test');
        config(['backup.connection' => $originalConn]);
        try {
            $pdo->exec("DROP DATABASE IF EXISTS \"{$srcDb}\" WITH (FORCE)");
            $pdo->exec("DROP DATABASE IF EXISTS \"{$dstDb}\" WITH (FORCE)");
        } catch (Throwable) {
            // Abaikan error pembersihan jika database belum sempat terbuat
        }
        File::deleteDirectory($tmpRoot);
    }
});

it('memverifikasi DDL migrasi modul backup berjalan bersih up dan down pada PostgreSQL', function () {
    $pgCfg = resolveLocalPostgresTestConfig();

    try {
        $pdo = new PDO(
            "pgsql:host={$pgCfg['host']};port={$pgCfg['port']};dbname=postgres",
            $pgCfg['username'],
            $pgCfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable) {
        $this->markTestSkipped('Server PostgreSQL lokal tidak dapat diakses untuk verifikasi DDL migrasi.');
    }

    $suffix = strtolower(Str::random(6));
    $migDb = "melarosa_mig_test_{$suffix}";
    $originalDefault = DB::getDefaultConnection();

    try {
        $pdo->exec("DROP DATABASE IF EXISTS \"{$migDb}\" WITH (FORCE)");
        $pdo->exec("CREATE DATABASE \"{$migDb}\"");

        config([
            'database.connections.pgsql_mig_test' => [
                'driver' => 'pgsql',
                'host' => $pgCfg['host'],
                'port' => $pgCfg['port'],
                'database' => $migDb,
                'username' => $pgCfg['username'],
                'password' => $pgCfg['password'],
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ],
        ]);

        DB::setDefaultConnection('pgsql_mig_test');

        $migrationPaths = [
            database_path('migrations/0001_01_01_000000_create_users_table.php'),
            database_path('migrations/2026_11_14_010001_create_backup_histories_table.php'),
            database_path('migrations/2026_11_14_010002_create_backup_schedules_table.php'),
            database_path('migrations/2026_11_14_010003_create_backup_restore_histories_table.php'),
            database_path('migrations/2026_11_14_010004_create_notifications_table.php'),
        ];

        $instances = array_map(fn (string $path) => require $path, $migrationPaths);
        foreach ($instances as $migration) {
            $migration->up();
        }

        expect(Schema::hasTable('backup_histories'))->toBeTrue()
            ->and(Schema::hasTable('backup_schedules'))->toBeTrue()
            ->and(Schema::hasTable('backup_restore_histories'))->toBeTrue()
            ->and(Schema::hasTable('notifications'))->toBeTrue();

        foreach (array_reverse($instances) as $migration) {
            $migration->down();
        }

        expect(Schema::hasTable('backup_histories'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalDefault);
        DB::purge('pgsql_mig_test');
        try {
            $pdo->exec("DROP DATABASE IF EXISTS \"{$migDb}\" WITH (FORCE)");
        } catch (Throwable) {
            // Abaikan error pembersihan
        }
    }
});

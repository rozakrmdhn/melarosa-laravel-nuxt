<?php

namespace App\Services\Backup;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Helper aman untuk eksekusi PostgreSQL client binaries (pg_dump, pg_restore, psql)
 * tanpa menyisipkan kredensial atau parameter SQL secara mentah ke command string.
 */
class PostgresCliHelper
{
    public function resolveBinary(string $tool): ?string
    {
        $configured = config("backup.binaries.{$tool}");
        if (is_string($configured) && trim($configured) !== '') {
            $trimmed = trim($configured);

            return is_file($trimmed) ? $trimmed : null;
        }

        $finder = new ExecutableFinder;
        $found = $finder->find($tool);
        if ($found !== null) {
            return $found;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = glob('C:\\Program Files\\PostgreSQL\\*\\bin\\'.$tool.'.exe') ?: [];
            rsort($candidates, SORT_NATURAL);
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function getBinaryOrFail(string $tool): string
    {
        $path = $this->resolveBinary($tool);
        if ($path === null) {
            throw new BackupException("Binary PostgreSQL '{$tool}' tidak ditemukan. Konfigurasikan BACKUP_PG_*_BINARY pada .env.");
        }

        return $path;
    }

    public function assertValidIdentifier(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $identifier)) {
            throw new BackupException('Nama database atau identifier PostgreSQL tidak valid.');
        }

        return $identifier;
    }

    /**
     * @return array{driver: string, host: string, port: string, database: string, username: string, password: string, sslmode: ?string}
     */
    public function getConnectionConfig(?string $connectionName = null): array
    {
        $name = $connectionName ?: (string) config('backup.connection', config('database.default'));
        $cfg = config("database.connections.{$name}");

        if (! is_array($cfg)) {
            throw new BackupException("Konfigurasi koneksi database '{$name}' tidak ditemukan.");
        }

        return [
            'driver' => (string) ($cfg['driver'] ?? ''),
            'host' => (string) ($cfg['host'] ?? '127.0.0.1'),
            'port' => (string) ($cfg['port'] ?? '5432'),
            'database' => (string) ($cfg['database'] ?? ''),
            'username' => (string) ($cfg['username'] ?? ''),
            'password' => (string) ($cfg['password'] ?? ''),
            'sslmode' => isset($cfg['sslmode']) ? (string) $cfg['sslmode'] : null,
        ];
    }

    /**
     * @param  list<string>  $arguments
     */
    public function runProcess(array $arguments, array $connectionConfig, ?int $timeoutSeconds = null): Process
    {
        $env = [
            'PGPASSWORD' => $connectionConfig['password'] ?? '',
            'PGCLIENTENCODING' => 'UTF8',
        ];
        if (! empty($connectionConfig['sslmode'])) {
            $env['PGSSLMODE'] = $connectionConfig['sslmode'];
        }

        $process = new Process($arguments, null, $env, null, $timeoutSeconds ?? (int) config('backup.process_timeout_seconds', 1800));
        $process->run();

        if (! $process->isSuccessful()) {
            $errorDetail = $this->sanitizeOutput($process->getErrorOutput() ?: $process->getOutput());
            throw new BackupException(
                'Eksekusi proses PostgreSQL gagal: '.($errorDetail !== '' ? mb_substr($errorDetail, 0, 500) : 'exit code '.$process->getExitCode())
            );
        }

        return $process;
    }

    public function sanitizeOutput(string $output): string
    {
        return (string) preg_replace('/PGPASSWORD=[^\s"\']+/i', 'PGPASSWORD=[REDACTED]', trim($output));
    }

    /**
     * Mengambil metadata versi PostgreSQL, ekstensi PostGIS, dan ringkasan tabel spasial.
     *
     * @return array<string, mixed>
     */
    public function inspectDatabaseMetadata(?string $connectionName = null): array
    {
        $name = $connectionName ?: (string) config('backup.connection', config('database.default'));
        $cfg = $this->getConnectionConfig($name);
        $conn = DB::connection($name);

        if ($cfg['driver'] !== 'pgsql') {
            return [
                'driver' => $cfg['driver'],
                'database' => $cfg['database'],
                'database_version' => $conn->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: $cfg['driver'],
                'postgis_version' => null,
                'extensions' => [],
                'tables_count' => $this->countSqliteTables($conn, $cfg['driver']),
                'geometry_tables' => [],
            ];
        }

        $versionRow = $conn->selectOne('SHOW server_version');
        $dbVersion = $versionRow?->server_version ?? null;

        $extensions = collect($conn->select('SELECT extname, extversion FROM pg_extension ORDER BY extname'))
            ->map(fn ($row) => ['name' => $row->extname, 'version' => $row->extversion])
            ->values()
            ->all();

        $postgisExt = collect($extensions)->firstWhere('name', 'postgis');
        $postgisVersion = $postgisExt['version'] ?? null;

        if ($postgisVersion === null) {
            try {
                $pgis = $conn->selectOne('SELECT PostGIS_Lib_Version() AS v');
                $postgisVersion = $pgis?->v ?? null;
            } catch (\Throwable) {
                $postgisVersion = null;
            }
        }

        $tablesCountRow = $conn->selectOne(
            "SELECT COUNT(*)::int AS total FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog', 'information_schema') AND table_type = 'BASE TABLE'"
        );

        $geometryTables = [];
        try {
            $geometryTables = collect($conn->select(
                "SELECT f_table_schema || '.' || f_table_name AS table_name, f_geometry_column AS column_name, srid, type FROM geometry_columns ORDER BY f_table_schema, f_table_name"
            ))->map(fn ($row) => [
                'table' => $row->table_name,
                'column' => $row->column_name,
                'srid' => (int) $row->srid,
                'type' => $row->type,
            ])->values()->all();
        } catch (\Throwable) {
            $geometryTables = [];
        }

        return [
            'driver' => 'pgsql',
            'database' => $cfg['database'],
            'database_version' => $dbVersion,
            'postgis_version' => $postgisVersion,
            'extensions' => $extensions,
            'tables_count' => (int) ($tablesCountRow?->total ?? 0),
            'geometry_tables' => $geometryTables,
        ];
    }

    private function countSqliteTables(ConnectionInterface $conn, string $driver): int
    {
        if ($driver !== 'sqlite') {
            return 0;
        }

        $row = $conn->selectOne("SELECT COUNT(*) AS total FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

        return (int) ($row?->total ?? 0);
    }
}


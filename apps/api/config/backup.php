<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup & Restore Activation
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Timezone & Schedule Defaults
    |--------------------------------------------------------------------------
    */
    'timezone' => env('BACKUP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Jakarta')),

    /*
    |--------------------------------------------------------------------------
    | Storage Disks & Temporary Workspace
    |--------------------------------------------------------------------------
    */
    'disk' => env('BACKUP_DISK', 'backups'),
    'remote_disk' => env('BACKUP_REMOTE_DISK'),
    'remote_prefix' => env('BACKUP_REMOTE_PREFIX', 'melarosa-backups'),
    'temp_directory' => env('BACKUP_TEMP_DIR', storage_path('app/private/temporary/backups')),

    /*
    |--------------------------------------------------------------------------
    | Database Connection & PostgreSQL Client Binaries
    |--------------------------------------------------------------------------
    */
    'connection' => env('BACKUP_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),

    'binaries' => [
        'pg_dump' => env('BACKUP_PG_DUMP_BINARY'),
        'pg_restore' => env('BACKUP_PG_RESTORE_BINARY'),
        'psql' => env('BACKUP_PSQL_BINARY'),
    ],

    'process_timeout_seconds' => (int) env('BACKUP_PROCESS_TIMEOUT', 1800),

    /*
    |--------------------------------------------------------------------------
    | Encryption & Integrity Verification
    |--------------------------------------------------------------------------
    | File backup dan arsip backup dienkripsi menggunakan AES-256-GCM
    | chunked stream dengan kunci khusus dari environment variable.
    | Jangan pernah menyimpan BACKUP_ENCRYPTION_KEY di dalam repository atau file backup.
    */
    'encryption' => [
        'enabled' => (bool) env('BACKUP_ENCRYPTION_ENABLED', true),
        'cipher' => 'aes-256-gcm',
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'chunk_size' => 1048576, // 1 MiB per chunk
    ],

    'compression' => [
        'level' => (int) env('BACKUP_COMPRESSION_LEVEL', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Backup Paths & Exclusions
    |--------------------------------------------------------------------------
    */
    'files' => [
        'include_paths' => [
            'public' => storage_path('app/public'),
            'private' => storage_path('app/private'),
        ],
        'exclude_patterns' => [
            'backups',
            'temporary',
            'framework',
            'logs',
            'cache',
            'sessions',
            'views',
            'node_modules',
            'vendor',
            '*.log',
            '*.tmp',
            '.DS_Store',
            'Thumbs.db',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Defaults (Days)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'database' => (int) env('BACKUP_RETENTION_DATABASE_DAYS', 14),
        'file' => (int) env('BACKUP_RETENTION_FILE_DAYS', 7),
        'full' => (int) env('BACKUP_RETENTION_FULL_DAYS', 56), // 8 minggu
        'pre_restore_safety_days' => (int) env('BACKUP_RETENTION_SAFETY_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload & Signed Download Security
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_size_mb' => (int) env('BACKUP_UPLOAD_MAX_SIZE_MB', 2048),
        'allowed_extensions' => ['enc', 'zip', 'dump'],
    ],

    'download' => [
        'signed_url_ttl_minutes' => (int) env('BACKUP_DOWNLOAD_URL_TTL_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restore Safety Controls
    |--------------------------------------------------------------------------
    */
    'restore' => [
        'allow_overwrite_active_db' => (bool) env('BACKUP_RESTORE_ALLOW_OVERWRITE_ACTIVE_DB', false),
        'create_safety_backup' => (bool) env('BACKUP_RESTORE_CREATE_SAFETY_BACKUP', true),
        'confirmation_phrase' => 'KONFIRMASI RESTORE',
        'verify_postgis_geometry' => true,
        'max_geometry_sample_tables' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Monitoring & Notifications
    |--------------------------------------------------------------------------
    */
    'health' => [
        'disk_warning_percent' => (int) env('BACKUP_DISK_WARNING_PERCENT', 85),
        'min_free_disk_bytes' => (int) env('BACKUP_MIN_FREE_DISK_BYTES', 536870912), // 512 MB
        'schedule_grace_minutes' => (int) env('BACKUP_SCHEDULE_GRACE_MINUTES', 120),
    ],

    'notifications' => [
        'channels' => array_values(array_filter(array_map(
            static fn (string $v): string => trim($v),
            explode(',', (string) env('BACKUP_NOTIFICATION_CHANNELS', 'database'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('BACKUP_QUEUE_CONNECTION'),
        'name' => env('BACKUP_QUEUE_NAME', 'default'),
    ],

];


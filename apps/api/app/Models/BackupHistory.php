<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Riwayat eksekusi dan arsip backup database PostgreSQL/PostGIS serta file storage.
 */
class BackupHistory extends Model
{
    use HasUuids;

    public const TYPE_DATABASE = 'database';

    public const TYPE_FILE = 'file';

    public const TYPE_FULL = 'full';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_PRE_RESTORE = 'pre_restore';

    public const TRIGGER_UPLOADED = 'uploaded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_DATABASE,
        self::TYPE_FILE,
        self::TYPE_FULL,
    ];

    /** @var list<string> */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
    ];

    protected $fillable = [
        'backup_name',
        'backup_type',
        'trigger_type',
        'status',
        'disk',
        'remote_disk',
        'remote_path',
        'file_path',
        'file_name',
        'file_size',
        'checksum_sha256',
        'is_encrypted',
        'encryption_algorithm',
        'database_name',
        'database_version',
        'postgis_version',
        'includes_files',
        'started_at',
        'completed_at',
        'duration_seconds',
        'retention_days',
        'expires_at',
        'error_message',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_encrypted' => 'boolean',
            'includes_files' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_seconds' => 'integer',
            'retention_days' => 'integer',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restoreHistories(): HasMany
    {
        return $this->hasMany(BackupRestoreHistory::class, 'backup_history_id');
    }

    public function preRestoreLinks(): HasMany
    {
        return $this->hasMany(BackupRestoreHistory::class, 'pre_restore_backup_id');
    }

    public function isInUseByActiveRestore(): bool
    {
        return $this->restoreHistories()
            ->whereIn('status', BackupRestoreHistory::ACTIVE_STATUSES)
            ->exists()
            || $this->preRestoreLinks()
                ->whereIn('status', BackupRestoreHistory::ACTIVE_STATUSES)
                ->exists();
    }
}


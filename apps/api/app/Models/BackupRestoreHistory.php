<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat eksekusi pemulihan (restore) backup database atau file.
 */
class BackupRestoreHistory extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const TARGET_MODE_ISOLATED = 'isolated';

    public const TARGET_MODE_ACTIVE = 'active';

    /** @var list<string> */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
    ];

    protected $fillable = [
        'backup_history_id',
        'source_file_name',
        'restore_scope',
        'target_database',
        'target_mode',
        'status',
        'pre_restore_backup_id',
        'validation_report',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_message',
        'executed_by',
    ];

    protected function casts(): array
    {
        return [
            'validation_report' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function backupHistory(): BelongsTo
    {
        return $this->belongsTo(BackupHistory::class, 'backup_history_id');
    }

    public function preRestoreBackup(): BelongsTo
    {
        return $this->belongsTo(BackupHistory::class, 'pre_restore_backup_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}


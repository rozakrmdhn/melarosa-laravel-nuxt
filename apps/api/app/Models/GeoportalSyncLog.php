<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoportalSyncLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'triggered_by',
        'status',
        'records_found',
        'records_inserted',
        'records_updated',
        'records_skipped',
        'error_message',
        'duration_seconds',
        'user_id',
    ];

    protected $casts = [
        'records_found' => 'integer',
        'records_inserted' => 'integer',
        'records_updated' => 'integer',
        'records_skipped' => 'integer',
        'duration_seconds' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

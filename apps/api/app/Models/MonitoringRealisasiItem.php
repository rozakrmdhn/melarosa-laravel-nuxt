<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringRealisasiItem extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'monitoring-realisasi-items';

    protected $table = 'monitoring_realisasi_items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_monitoring',
        'id_segmen',
    ];

    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(MonitoringRealisasi::class, 'id_monitoring', 'id');
    }

    public function segmen(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSegmen::class, 'id_segmen', 'id');
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefBkkdBidang extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'ref-bkkd-bidang';

    protected $table = 'ref_bkkd_bidang';

    protected $fillable = [
        'nama',
        'id_opd_default',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function opdDefault(): BelongsTo
    {
        return $this->belongsTo(RefOpd::class, 'id_opd_default', 'id');
    }

    public function proposal(): HasMany
    {
        return $this->hasMany(BkkdProposal::class, 'id_bidang', 'id');
    }
}

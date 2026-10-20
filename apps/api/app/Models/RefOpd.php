<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefOpd extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'ref-opd';

    protected $table = 'ref_opd';

    protected $fillable = [
        'kode',
        'nama',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bidang(): HasMany
    {
        return $this->hasMany(RefBkkdBidang::class, 'id_opd_default', 'id');
    }

    public function verifikasi(): HasMany
    {
        return $this->hasMany(BkkdVerifikasi::class, 'id_opd', 'id');
    }
}

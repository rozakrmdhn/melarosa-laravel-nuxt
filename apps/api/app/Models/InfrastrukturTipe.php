<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfrastrukturTipe extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'infrastruktur-tipe';

    protected $table = 'infrastruktur_tipe';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'ikon',
        'warna',
        'geom_type',
        'table_name',
        'has_segmen',
        'is_active',
        'sort_order',
        'config',
    ];

    protected $casts = [
        'has_segmen' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'config' => 'array',
    ];

    /**
     * Relasi ke seluruh segmen infrastruktur dengan tipe ini.
     */
    public function segmen(): HasMany
    {
        return $this->hasMany(InfrastrukturSegmen::class, 'tipe_kode', 'kode');
    }

    /**
     * Scope hanya tipe infrastruktur yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }
}

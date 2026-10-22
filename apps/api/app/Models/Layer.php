<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layer extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'layers';

    protected $fillable = [
        'name',
        'geoportal_id',
        'category',
        'opd_name',
        'protocol',
        'url',
        'legend_url',
        'layer_name',
        'is_active',
        'default_visible',
        'opacity',
        'order',
        'attribution',
        'description',
        'csw_metadata',
        'source_type',
        'is_synced',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_visible' => 'boolean',
        'opacity' => 'float',
        'order' => 'integer',
        'is_synced' => 'boolean',
        'csw_metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Scope only active layers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope only geoportal layers.
     */
    public function scopeGeoportal(Builder $query): Builder
    {
        return $query->where('source_type', 'geoportal');
    }

    /**
     * Scope only internal layers.
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('source_type', '!=', 'geoportal');
    }

    /**
     * Scope ordered layers for map rendering and display.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order', 'asc')->orderBy('name', 'asc');
    }
}

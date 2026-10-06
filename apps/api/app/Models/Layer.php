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
        'protocol',
        'url',
        'layer_name',
        'is_active',
        'default_visible',
        'opacity',
        'order',
        'attribution',
        'description',
        'source_type',
        'is_synced',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_visible' => 'boolean',
        'opacity' => 'float',
        'order' => 'integer',
        'is_synced' => 'boolean',
    ];

    /**
     * Scope only active layers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered layers for map rendering and display.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order', 'asc')->orderBy('name', 'asc');
    }
}

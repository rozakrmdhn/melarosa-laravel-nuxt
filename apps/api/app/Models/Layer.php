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
        'style_config',
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
        'style_config' => 'array',
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
     * Scope only Geoportal Bojonegoro layers (WMS Service Geoportal).
     */
    public function scopeGeoportal(Builder $query): Builder
    {
        $like = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        return $query->where(function (Builder $sub) use ($like) {
            $sub->where('source_type', 'geoportal')
                ->orWhere('url', $like, '%geoportal.bojonegorokab.go.id%');
        });
    }

    /**
     * Scope only internal layers (Martin Tile Server MVT).
     */
    public function scopeInternal(Builder $query): Builder
    {
        $like = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        return $query->where(function (Builder $sub) use ($like) {
            $sub->where('source_type', 'internal')
                ->orWhere('source_type', 'martin')
                ->orWhere('protocol', $like, 'mvt')
                ->orWhere('url', $like, '%/martin/%');
        });
    }

    /**
     * Scope only external layers (WMS Service diluar Geoportal).
     */
    public function scopeExternal(Builder $query): Builder
    {
        $like = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        return $query->where(function (Builder $sub) use ($like) {
            $sub->where('url', 'not ' . $like, '%geoportal.bojonegorokab.go.id%')
                ->where('url', 'not ' . $like, '%/martin/%')
                ->where('protocol', 'not ' . $like, 'mvt');
        });
    }

    /**
     * Scope ordered layers for map rendering and display.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order', 'asc')->orderBy('name', 'asc');
    }
}

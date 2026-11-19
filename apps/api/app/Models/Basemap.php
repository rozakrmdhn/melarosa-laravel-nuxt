<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Basemap extends Model
{
    use HasUuids, Auditable;

    public const PUBLIC_CACHE_KEY = 'public_basemaps_list_v1';

    public string $auditModule = 'basemaps';

    protected $table = 'basemaps';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama',
        'label_singkat',
        'tagline',
        'provider',
        'tipe',
        'url_template',
        'fallback_url_template',
        'tile_urls',
        'attribution',
        'thumbnail_url',
        'preview_style',
        'min_zoom',
        'max_zoom',
        'tile_size',
        'cross_origin',
        'supports_openlayers',
        'supports_maplibre',
        'is_default',
        'is_active',
        'sort_order',
        'updated_by',
    ];

    protected $casts = [
        'tile_urls'           => 'array',
        'min_zoom'            => 'integer',
        'max_zoom'            => 'integer',
        'tile_size'           => 'integer',
        'supports_openlayers' => 'boolean',
        'supports_maplibre'   => 'boolean',
        'is_default'          => 'boolean',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(static function () {
            Cache::forget(self::PUBLIC_CACHE_KEY);
        });

        static::deleted(static function () {
            Cache::forget(self::PUBLIC_CACHE_KEY);
        });
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }
}

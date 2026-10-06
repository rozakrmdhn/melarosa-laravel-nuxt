<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BatasWilayahDesa extends Model
{
    use HasFactory, Auditable;

    public string $auditModule = 'batas-wilayah-desa';

    /**
     * Relationship to subdistrict (kecamatan).
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan', 'id');
    }

    public function infrastrukturSegmen(): HasMany
    {
        return $this->hasMany(InfrastrukturSegmen::class, 'id_desa', 'id');
    }

    public function plottingAnggaran(): HasMany
    {
        return $this->hasMany(PlottingAnggaran::class, 'id_desa', 'id');
    }

    public function monitoringRealisasi(): HasMany
    {
        return $this->hasMany(MonitoringRealisasi::class, 'id_desa', 'id');
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bataswilayah_desa';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_kecamatan',
        'nama_desa',
        'nama_pimpinan',
        'nama_jabatan',
        'nip',
        'pangkat_gol',
        'geom',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'id_kecamatan' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to select fields along with standard GeoJSON geometry,
     * area in hectares, perimeter in meters, and centroid from PostGIS.
     */
    public function scopeWithGeoJson($query)
    {
        return $query->select([
            'bataswilayah_desa.id',
            'bataswilayah_desa.id_kecamatan',
            'bataswilayah_desa.nama_desa',
            'bataswilayah_desa.nama_pimpinan',
            'bataswilayah_desa.nama_jabatan',
            'bataswilayah_desa.nip',
            'bataswilayah_desa.pangkat_gol',
            'bataswilayah_desa.created_at',
            'bataswilayah_desa.updated_at',
            DB::raw('ST_AsGeoJSON(bataswilayah_desa.geom) as geojson'),
            DB::raw('ROUND((ST_Area(bataswilayah_desa.geom::geography) / 10000)::numeric, 2) as luas_hektar'),
            DB::raw('ROUND((ST_Perimeter(bataswilayah_desa.geom::geography))::numeric, 2) as keliling_meter'),
            DB::raw('ST_AsGeoJSON(ST_Centroid(bataswilayah_desa.geom)) as centroid'),
        ]);
    }

    /**
     * Scope to filter features within a Bounding Box (for map viewport bounding box queries).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $minX West Longitude
     * @param float $minY South Latitude
     * @param float $maxX East Longitude
     * @param float $maxY North Latitude
     */
    public function scopeInBbox($query, float $minX, float $minY, float $maxX, float $maxY)
    {
        return $query->whereRaw(
            'bataswilayah_desa.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
            [$minX, $minY, $maxX, $maxY]
        );
    }

    /**
     * Scope query agar dibatasi sesuai wilayah user yang login.
     */
    public function scopeForUser($query, ?User $user = null)
    {
        if (!$user || $user->hasRole('admin')) {
            return $query;
        }

        // Jika user dibatasi per desa spesifik
        if ($user->id_desa) {
            return $query->where('bataswilayah_desa.id', $user->id_desa);
        }

        // Jika user dibatasi per kecamatan
        if ($user->id_kecamatan) {
            return $query->where('bataswilayah_desa.id_kecamatan', $user->id_kecamatan);
        }

        return $query;
    }

    /**
     * Convert the model instance to a standard GeoJSON Feature array.
     */
    public function toGeoJsonFeature(): array
    {
        return [
            'type' => 'Feature',
            'id' => $this->id,
            'geometry' => !empty($this->geojson) ? json_decode($this->geojson) : null,
            'properties' => [
                'id' => $this->id,
                'id_kecamatan' => $this->id_kecamatan,
                'nama_desa' => $this->nama_desa,
                'nama_pimpinan' => $this->nama_pimpinan,
                'nama_jabatan' => $this->nama_jabatan,
                'nip' => $this->nip,
                'pangkat_gol' => $this->pangkat_gol,
                'luas_hektar' => (float) ($this->luas_hektar ?? 0),
                'keliling_meter' => (float) ($this->keliling_meter ?? 0),
                'centroid' => !empty($this->centroid) ? json_decode($this->centroid) : null,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ],
        ];
    }
}

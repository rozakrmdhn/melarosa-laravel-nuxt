<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BatasWilayahKecamatan extends Model
{
    use HasFactory;

    /**
     * Relationship to villages (desa).
     */
    public function desa(): HasMany
    {
        return $this->hasMany(BatasWilayahDesa::class, 'id_kecamatan', 'id');
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bataswilayah_kecamatan';

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
        'nama_kecamatan',
        'nama_pimpinan',
        'nama_jabatan',
        'nip',
        'pangkat_gol',
        'geom',
        'WADMKC',
        'WADMKK',
        'WADMPR',
        'REMARK',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
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
            'bataswilayah_kecamatan.id',
            'bataswilayah_kecamatan.nama_kecamatan',
            'bataswilayah_kecamatan.nama_pimpinan',
            'bataswilayah_kecamatan.nama_jabatan',
            'bataswilayah_kecamatan.nip',
            'bataswilayah_kecamatan.pangkat_gol',
            'bataswilayah_kecamatan.created_at',
            'bataswilayah_kecamatan.updated_at',
            DB::raw('ST_AsGeoJSON(bataswilayah_kecamatan.geom) as geojson'),
            DB::raw('ROUND((ST_Area(bataswilayah_kecamatan.geom::geography) / 10000)::numeric, 2) as luas_hektar'),
            DB::raw('ROUND((ST_Perimeter(bataswilayah_kecamatan.geom::geography))::numeric, 2) as keliling_meter'),
            DB::raw('ST_AsGeoJSON(ST_Centroid(bataswilayah_kecamatan.geom)) as centroid'),
        ]);
    }

    /**
     * Scope to filter features within a Bounding Box.
     */
    public function scopeInBbox($query, float $minX, float $minY, float $maxX, float $maxY)
    {
        return $query->whereRaw(
            'bataswilayah_kecamatan.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
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

        if ($user->id_kecamatan) {
            return $query->where('bataswilayah_kecamatan.id', $user->id_kecamatan);
        }

        if ($user->id_desa) {
            return $query->whereHas('desa', function ($q) use ($user) {
                $q->where('bataswilayah_desa.id', $user->id_desa);
            });
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
                'nama_kecamatan' => $this->nama_kecamatan,
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;

class JalanPorosDesa extends Model
{
    use HasFactory;

    protected $table = 'jalan_porosdesa';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'kode_ruas',
        'nama_ruas',
        'desa',
        'kecamatan',
        'panjang',
        'lebar',
        'perkerasan',
        'kondisi',
        'status_awal',
        'status_eksisting',
        'sumber_data',
        'geom',
        'id_desa',
        'id_kecamatan',
    ];

    protected $casts = [
        'kode_ruas'    => 'integer',
        'panjang'      => 'float',
        'lebar'        => 'float',
        'id_desa'      => 'integer',
        'id_kecamatan' => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Relasi ke data batas wilayah kecamatan
     */
    public function kecamatanModel()
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan');
    }

    /**
     * Relasi ke data batas wilayah desa
     */
    public function desaModel()
    {
        return $this->belongsTo(BatasWilayahDesa::class, 'id_desa');
    }

    /**
     * Scope query agar otomatis terfilter berdasarkan wilayah user yang login.
     */
    public function scopeForUser($query, ?User $user = null)
    {
        if (!$user || $user->hasRole('admin')) {
            return $query;
        }

        // Jika user dibatasi tingkat desa
        if ($user->id_desa) {
            return $query->where('jalan_porosdesa.id_desa', $user->id_desa);
        }

        // Jika user dibatasi tingkat kecamatan
        if ($user->id_kecamatan) {
            return $query->where('jalan_porosdesa.id_kecamatan', $user->id_kecamatan);
        }

        return $query;
    }

    /**
     * Scope to select fields with GeoJSON geometry and computed spatial metrics
     * (length in meters, midpoint centroid) from PostGIS.
     */
    public function scopeWithGeoJson($query)
    {
        return $query->select([
            'jalan_porosdesa.id',
            'jalan_porosdesa.kode_ruas',
            'jalan_porosdesa.nama_ruas',
            'jalan_porosdesa.desa',
            'jalan_porosdesa.kecamatan',
            'jalan_porosdesa.panjang',
            'jalan_porosdesa.lebar',
            'jalan_porosdesa.perkerasan',
            'jalan_porosdesa.kondisi',
            'jalan_porosdesa.status_awal',
            'jalan_porosdesa.status_eksisting',
            'jalan_porosdesa.sumber_data',
            'jalan_porosdesa.id_desa',
            'jalan_porosdesa.id_kecamatan',
            'jalan_porosdesa.created_at',
            'jalan_porosdesa.updated_at',
            DB::raw('ST_AsGeoJSON(jalan_porosdesa.geom) as geojson'),
            DB::raw('ROUND((ST_Length(jalan_porosdesa.geom::geography))::numeric, 2) as panjang_meter'),
            DB::raw('ST_AsGeoJSON(ST_PointOnSurface(jalan_porosdesa.geom)) as centroid'),
        ]);
    }

    /**
     * Scope to filter road segments within a Bounding Box (viewport query).
     */
    public function scopeInBbox($query, float $minX, float $minY, float $maxX, float $maxY)
    {
        return $query->whereRaw(
            'jalan_porosdesa.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
            [$minX, $minY, $maxX, $maxY]
        );
    }

    /**
     * Convert this model instance to a standard GeoJSON Feature array.
     */
    public function toGeoJsonFeature(): array
    {
        return [
            'type'       => 'Feature',
            'id'         => $this->id,
            'geometry'   => !empty($this->geojson) ? json_decode($this->geojson) : null,
            'properties' => [
                'id'               => $this->id,
                'kode_ruas'        => $this->kode_ruas,
                'nama_ruas'        => $this->nama_ruas,
                'desa'             => $this->desa,
                'kecamatan'        => $this->kecamatan,
                'panjang'          => (float) ($this->panjang ?? 0),
                'panjang_meter'    => (float) ($this->panjang_meter ?? 0),
                'lebar'            => (float) ($this->lebar ?? 0),
                'perkerasan'       => $this->perkerasan,
                'kondisi'          => $this->kondisi,
                'status_awal'      => $this->status_awal,
                'status_eksisting' => $this->status_eksisting,
                'sumber_data'      => $this->sumber_data,
                'id_desa'          => $this->id_desa,
                'id_kecamatan'     => $this->id_kecamatan,
                'centroid'         => !empty($this->centroid) ? json_decode($this->centroid) : null,
                'created_at'       => $this->created_at?->toISOString(),
                'updated_at'       => $this->updated_at?->toISOString(),
            ],
        ];
    }
}

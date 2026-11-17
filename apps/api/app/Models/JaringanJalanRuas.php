<?php

namespace App\Models;

use App\Helpers\SpatialHelper;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class JaringanJalanRuas extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;

    public const HIERARKI_KABUPATEN = 'kabupaten';
    public const HIERARKI_POROS_DESA = 'poros_desa';
    public const HIERARKI_LINGKUNGAN = 'lingkungan';
    public const HIERARKI_USAHA_TANI = 'usaha_tani';

    public const STATUS_TERVERIFIKASI = 'terverifikasi';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ARSIP = 'arsip';

    public const SUMBER_PROMOSI_PROPOSAL = 'promosi_proposal';
    public const SUMBER_SK_PEMDA = 'sk_pemda';
    public const SUMBER_GEOPORTAL = 'geoportal';
    public const SUMBER_MANUAL = 'manual';

    public string $auditModule = 'jaringan-jalan-ruas';

    protected $table = 'jaringan_jalan_ruas';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_ruas',
        'nama_ruas',
        'hierarki_jalan',
        'id_kecamatan',
        'id_desa',
        'panjang_m',
        'lebar_m',
        'perkerasan',
        'kondisi',
        'sumber_data',
        'status_validasi',
        'sumber_proposal_id',
        'sumber_lokasi_id',
        'validated_by',
        'validated_at',
        'catatan_validasi',
        'geom',
    ];

    protected $casts = [
        'id_kecamatan' => 'integer',
        'id_desa' => 'integer',
        'panjang_m' => 'float',
        'lebar_m' => 'float',
        'validated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $purge = function () {
            DB::afterCommit(function () {
                if (class_exists(\App\Services\MartinTileService::class)) {
                    \App\Services\MartinTileService::purgeCache('jaringan_jalan_ruas');
                    \App\Services\MartinTileService::purgeCache('v_jaringan_jalan_bojonegoro');
                }
            });
        };

        static::saved($purge);
        static::deleted($purge);
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan');
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahDesa::class, 'id_desa');
    }

    public function sumberProposal(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'sumber_proposal_id', 'id');
    }

    public function sumberLokasi(): BelongsTo
    {
        return $this->belongsTo(BkkdProposalLokasi::class, 'sumber_lokasi_id', 'id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by', 'uuid');
    }

    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        if (!$user || $user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return $query;
        }

        if ($user->id_desa) {
            return $query->where('jaringan_jalan_ruas.id_desa', (int) $user->id_desa);
        }

        if ($user->id_kecamatan) {
            return $query->where('jaringan_jalan_ruas.id_kecamatan', (int) $user->id_kecamatan);
        }

        return $query;
    }

    public function scopeWithGeoJson(Builder $query): Builder
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $query->select([
                'jaringan_jalan_ruas.*',
                DB::raw('ST_AsGeoJSON(jaringan_jalan_ruas.geom) as geojson'),
                DB::raw(SpatialHelper::lengthColumn('jaringan_jalan_ruas.geom')),
                DB::raw('ST_AsGeoJSON(ST_PointOnSurface(jaringan_jalan_ruas.geom)) as centroid'),
            ]);
        }

        return $query->select([
            'jaringan_jalan_ruas.*',
            DB::raw('jaringan_jalan_ruas.geom as geojson'),
            DB::raw('jaringan_jalan_ruas.panjang_m as panjang_meter'),
            DB::raw('null as centroid'),
        ]);
    }

    public function toGeoJsonFeature(): array
    {
        $geometry = null;
        if (!empty($this->geojson)) {
            $geometry = is_string($this->geojson) ? json_decode($this->geojson, true) : $this->geojson;
        }

        $centroid = null;
        if (!empty($this->centroid)) {
            $centroid = is_string($this->centroid) ? json_decode($this->centroid, true) : $this->centroid;
        }

        return [
            'type' => 'Feature',
            'id' => $this->id,
            'geometry' => $geometry,
            'properties' => [
                'id' => $this->id,
                'kode_ruas' => $this->kode_ruas,
                'nama_ruas' => $this->nama_ruas,
                'hierarki_jalan' => $this->hierarki_jalan,
                'id_kecamatan' => $this->id_kecamatan,
                'id_desa' => $this->id_desa,
                'nama_kecamatan' => $this->kecamatan?->nama_kecamatan,
                'nama_desa' => $this->desa?->nama_desa,
                'panjang_m' => (float) ($this->panjang_m ?? $this->panjang_meter ?? 0),
                'lebar_m' => $this->lebar_m !== null ? (float) $this->lebar_m : null,
                'perkerasan' => $this->perkerasan,
                'kondisi' => $this->kondisi,
                'sumber_data' => $this->sumber_data,
                'status_validasi' => $this->status_validasi,
                'sumber_proposal_id' => $this->sumber_proposal_id,
                'sumber_lokasi_id' => $this->sumber_lokasi_id,
                'validated_at' => $this->validated_at?->toIso8601String(),
                'centroid' => $centroid,
            ],
        ];
    }
}

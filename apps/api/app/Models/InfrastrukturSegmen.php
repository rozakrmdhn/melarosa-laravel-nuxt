<?php

namespace App\Models;

use App\Enums\KondisiSegmen;
use App\Enums\StatusVerifikasi;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InfrastrukturSegmen extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'infrastruktur-segmen';

    protected $table = 'infrastruktur_segmen';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tipe_kode',
        'parent_id',
        'geom',
        'panjang',
        'lebar',
        'jenis_perkerasan',
        'status_jalan',
        'kondisi',
        'status_kondisi',
        'tahun_pembangunan',
        'sumber_dana',
        'keterangan',
        'foto_url',
        'atribut',
        'desa',
        'kecamatan',
        'id_desa',
        'id_kecamatan',
        'namobj',
        'plotting_id',
        'verifikator',
        'user_id',
        'status_parent',
        'sumber_data',
        'status_verifikasi',
        'catatan_verifikasi',
        'id_entry',
        'status_aset',
        'created_by',
        'created_by_role',
        'submitted_desa_at',
        'verified_kecamatan_by',
        'verified_kecamatan_at',
        'catatan_kecamatan',
        'verified_bappeda_by',
        'verified_bappeda_at',
        'catatan_bappeda',
    ];

    protected $casts = [
        'panjang'               => 'float',
        'lebar'                 => 'float',
        'tahun_pembangunan'     => 'integer',
        'atribut'               => 'array',
        'status_parent'         => 'boolean',
        'id_desa'               => 'integer',
        'id_kecamatan'          => 'integer',
        'submitted_desa_at'     => 'datetime',
        'verified_kecamatan_at' => 'datetime',
        'verified_bappeda_at'   => 'datetime',
        'kondisi'               => KondisiSegmen::class,
        'status_verifikasi'     => StatusVerifikasi::class,
    ];

    protected static function booted()
    {
        static::saved(function () {
            \Illuminate\Support\Facades\DB::afterCommit(function () {
                \App\Services\MartinTileService::purgeCache('infrastruktur_segmen');
            });
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\DB::afterCommit(function () {
                \App\Services\MartinTileService::purgeCache('infrastruktur_segmen');
            });
        });
    }

    // ─────────────────────────────────────────────
    // Relasi Eloquent
    // ─────────────────────────────────────────────

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturTipe::class, 'tipe_kode', 'kode');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSegmen::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(InfrastrukturSegmen::class, 'parent_id', 'id');
    }

    public function plotting(): BelongsTo
    {
        return $this->belongsTo(PlottingAnggaran::class, 'plotting_id', 'id');
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan', 'id');
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahDesa::class, 'id_desa', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function verifierKecamatan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_kecamatan_by', 'uuid');
    }

    public function verifierBappeda(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_bappeda_by', 'uuid');
    }

    // ─────────────────────────────────────────────
    // Query Scopes
    // ─────────────────────────────────────────────

    /**
     * Scope isolasi data wilayah berdasarkan akun user yang sedang aktif.
     */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        if (!$user || $user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return $query;
        }

        // Jika user dibatasi tingkat desa (operator desa)
        if ($user->id_desa) {
            return $query->where('infrastruktur_segmen.id_desa', $user->id_desa);
        }

        // Jika user dibatasi tingkat kecamatan (verifier kecamatan)
        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return $query->where('infrastruktur_segmen.id_kecamatan', $user->id_kecamatan);
        }

        return $query;
    }

    /**
     * Scope PostGIS GeoJSON, kalkulasi panjang meter spasial, dan titik tengah centroid.
     */
    public function scopeWithGeoJson(Builder $query): Builder
    {
        return $query->select([
            'infrastruktur_segmen.*',
            DB::raw('ST_AsGeoJSON(infrastruktur_segmen.geom) as geojson'),
            DB::raw('ROUND(ST_Length(infrastruktur_segmen.geom::geography)::numeric, 2) as panjang_meter_gis'),
            DB::raw('ST_AsGeoJSON(ST_Centroid(infrastruktur_segmen.geom)) as centroid'),
        ]);
    }

    /**
     * Scope filter geometri berada dalam Bounding Box peta (viewport map query).
     */
    public function scopeInBbox(Builder $query, float $minX, float $minY, float $maxX, float $maxY): Builder
    {
        return $query->whereRaw(
            'infrastruktur_segmen.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
            [$minX, $minY, $maxX, $maxY]
        );
    }

    /**
     * Scope antrean verifikasi tingkat kecamatan (menunggu disetujui kecamatan).
     */
    public function scopePendingKecamatan(Builder $query): Builder
    {
        return $query->where('status_verifikasi', StatusVerifikasi::SubmittedDesa->value);
    }

    /**
     * Scope antrean verifikasi tingkat Bappeda (telah lolos kecamatan).
     */
    public function scopePendingBappeda(Builder $query): Builder
    {
        return $query->where('status_verifikasi', StatusVerifikasi::VerifiedKecamatan->value);
    }

    /**
     * Scope segmen yang telah berstatus sah final oleh Bappeda.
     */
    public function scopeVerifiedFinal(Builder $query): Builder
    {
        return $query->where('status_verifikasi', StatusVerifikasi::VerifiedBappeda->value);
    }
}

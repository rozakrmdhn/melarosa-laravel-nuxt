<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlottingAnggaran extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'plotting-anggaran';

    protected $table = 'plotting_anggaran';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tahun_anggaran',
        'id_kecamatan',
        'id_desa',
        'jenis_bantuan',
        'nama_kegiatan',
        'lokasi_kegiatan',
        'sumber_dana',
        'nomor_sk',
        'tanggal_sk',
        'status_penetapan',
        'bkkd_proposal_id',
        'target_pagu_anggaran',
        'target_panjang_m',
        'user_id',
    ];

    protected $casts = [
        'tahun_anggaran'       => 'integer',
        'id_kecamatan'         => 'integer',
        'id_desa'              => 'integer',
        'tanggal_sk'           => 'date:Y-m-d',
        'target_pagu_anggaran' => 'decimal:2',
        'target_panjang_m'     => 'decimal:2',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'bkkd_proposal_id', 'id');
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan', 'id');
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahDesa::class, 'id_desa', 'id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function segmen(): HasMany
    {
        return $this->hasMany(InfrastrukturSegmen::class, 'plotting_id', 'id');
    }

    public function monitoringRealisasi(): HasMany
    {
        return $this->hasMany(MonitoringRealisasi::class, 'id_plotting', 'id');
    }

    /**
     * Scope filter otomatis wilayah user.
     */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        if (!$user || $user->isAdminOrBappeda()) {
            return $query;
        }

        if ($user->id_desa) {
            return $query->where('plotting_anggaran.id_desa', $user->id_desa);
        }

        if ($user->hasAnyRole(['verifierKecamatan', 'verifikator-kecamatan', 'kecamatan']) || $user->id_kecamatan) {
            return $query->where('plotting_anggaran.id_kecamatan', $user->id_kecamatan);
        }

        return $query;
    }
}

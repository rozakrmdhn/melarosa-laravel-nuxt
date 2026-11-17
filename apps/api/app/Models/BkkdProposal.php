<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BkkdProposal extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;

    public string $auditModule = 'bkkd-proposal';

    protected $table = 'bkkd_proposal';

    public const TAGGING_RKPD = 'RKPD';
    public const TAGGING_PRKPD = 'PRKPD';

    protected $fillable = [
        'nomor_registrasi',
        'tahun_anggaran',
        'tagging_usulan',
        'id_kecamatan',
        'id_desa',
        'id_bidang',
        'judul_kegiatan',
        'uraian_kegiatan',
        'lokasi_kegiatan',
        'nilai_usulan',
        'jumlah_lokasi',
        'total_panjang_gis',
        'status_analisa_spasial',
        'catatan_konfirmasi_desa',
        'dikonfirmasi_oleh',
        'dikonfirmasi_pada',
        'versi',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'id_kecamatan' => 'integer',
        'id_desa' => 'integer',
        'nilai_usulan' => 'decimal:2',
        'jumlah_lokasi' => 'integer',
        'total_panjang_gis' => 'decimal:2',
        'versi' => 'integer',
        'dikonfirmasi_pada' => 'datetime',
    ];

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan', 'id');
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahDesa::class, 'id_desa', 'id');
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(RefBkkdBidang::class, 'id_bidang', 'id');
    }

    public function lokasi(): HasMany
    {
        return $this->hasMany(BkkdProposalLokasi::class, 'id_proposal', 'id')->orderBy('urutan');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(BkkdProposalRiwayat::class, 'id_proposal', 'id')->orderByDesc('versi');
    }

    public function konfirmator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        if (!$user || $user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return $query;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return $query->where('bkkd_proposal.id_kecamatan', $user->id_kecamatan);
        }

        if ($user->id_desa) {
            return $query->where('bkkd_proposal.id_desa', $user->id_desa);
        }

        return $query;
    }
}

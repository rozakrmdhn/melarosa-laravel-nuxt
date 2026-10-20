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

    protected $fillable = [
        'nomor_registrasi',
        'tahun_anggaran',
        'tahap_anggaran',
        'id_kecamatan',
        'id_desa',
        'id_bidang',
        'id_proposal_induk',
        'nomor_surat_desa',
        'tanggal_surat_desa',
        'tanggal_diterima',
        'judul_kegiatan',
        'uraian_kegiatan',
        'lokasi_kegiatan',
        'panjang',
        'lebar',
        'tinggi',
        'volume',
        'satuan',
        'nilai_usulan',
        'status',
        'catatan',
        'alasan_pergantian_papbd',
        'nilai_disetujui_tapd',
        'catatan_tapd',
        'tanggal_penetapan_tapd',
        'ditetapkan_oleh',
        'geom',
        'versi',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'id_kecamatan' => 'integer',
        'id_desa' => 'integer',
        'panjang' => 'decimal:2',
        'lebar' => 'decimal:2',
        'tinggi' => 'decimal:2',
        'volume' => 'decimal:2',
        'nilai_usulan' => 'decimal:2',
        'nilai_disetujui_tapd' => 'decimal:2',
        'versi' => 'integer',
        'tanggal_surat_desa' => 'date',
        'tanggal_diterima' => 'date',
        'tanggal_penetapan_tapd' => 'date',
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

    public function proposalInduk(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'id_proposal_induk', 'id');
    }

    public function proposalPengganti(): HasMany
    {
        return $this->hasMany(BkkdProposal::class, 'id_proposal_induk', 'id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(BkkdProposalLampiran::class, 'id_proposal', 'id');
    }

    public function verifikasi(): HasMany
    {
        return $this->hasMany(BkkdVerifikasi::class, 'id_proposal', 'id')->orderByDesc('created_at');
    }

    public function verifikasiAktif(): HasOne
    {
        return $this->hasOne(BkkdVerifikasi::class, 'id_proposal', 'id')->where('is_aktif', true);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(BkkdProposalRiwayat::class, 'id_proposal', 'id')->orderByDesc('versi');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh', 'uuid');
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

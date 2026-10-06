<?php

namespace App\Models;

use App\Enums\StatusMonitoring;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MonitoringRealisasi extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'monitoring-realisasi';

    protected $table = 'monitoring_realisasi';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nomor_ba',
        'id_plotting',
        'id_kecamatan',
        'id_desa',
        'tahun_anggaran',
        'sumber_dana',
        'rencana_panjang',
        'realisasi_panjang',
        'status',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'tahun_anggaran'    => 'integer',
        'id_kecamatan'      => 'integer',
        'id_desa'           => 'integer',
        'rencana_panjang'   => 'decimal:2',
        'realisasi_panjang' => 'decimal:2',
        'status'            => StatusMonitoring::class,
    ];

    // ─────────────────────────────────────────────
    // Relasi Eloquent
    // ─────────────────────────────────────────────

    public function plotting(): BelongsTo
    {
        return $this->belongsTo(PlottingAnggaran::class, 'id_plotting', 'id');
    }

    public function segmen(): BelongsToMany
    {
        return $this->belongsToMany(InfrastrukturSegmen::class, 'monitoring_realisasi_items', 'id_monitoring', 'id_segmen');
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahKecamatan::class, 'id_kecamatan', 'id');
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(BatasWilayahDesa::class, 'id_desa', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MonitoringRealisasiItem::class, 'id_monitoring', 'id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MonitoringRealisasiRevision::class, 'id_monitoring', 'id')->orderBy('created_at', 'desc');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(MonitoringRealisasiRevision::class, 'id_monitoring', 'id')->latestOfMany('created_at');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    // ─────────────────────────────────────────────
    // Domain Methods Sinergi Revisi & Snapshot
    // ─────────────────────────────────────────────

    /**
     * Mengambil snapshot komprehensif dari data dokumen monitoring saat ini.
     */
    public function createSnapshot(): array
    {
        return [
            'nomor_ba'          => $this->nomor_ba,
            'id_plotting'       => $this->id_plotting,
            'id_kecamatan'      => $this->id_kecamatan,
            'id_desa'           => $this->id_desa,
            'tahun_anggaran'    => $this->tahun_anggaran,
            'sumber_dana'       => $this->sumber_dana,
            'rencana_panjang'   => (float) $this->rencana_panjang,
            'realisasi_panjang' => (float) $this->realisasi_panjang,
            'persentase'        => $this->rencana_panjang > 0 ? round(($this->realisasi_panjang / $this->rencana_panjang) * 100, 2) : 0,
            'status'            => $this->status?->value ?? $this->status,
            'keterangan'        => $this->keterangan,
            'segmen_ids'        => $this->items()->pluck('id_segmen')->toArray(),
        ];
    }

    /**
     * Merekam riwayat revisi baru, menyimpan data snapshot, dan mengalihkan status ke 'reverted'.
     */
    public function recordRevision(string $catatanRevisi, User|string $revisedBy): MonitoringRealisasiRevision
    {
        $userId = $revisedBy instanceof User ? $revisedBy->uuid : $revisedBy;
        $statusSebelum = $this->status?->value ?? $this->status;

        $revision = new MonitoringRealisasiRevision();
        $revision->id = (string) Str::uuid();
        $revision->id_monitoring = $this->id;
        $revision->catatan_revisi = $catatanRevisi;
        $revision->status_sebelum = $statusSebelum;
        $revision->data_snapshot = $this->createSnapshot();
        $revision->revised_by = $userId;
        $revision->reverted_at = now();
        $revision->created_at = now();
        $revision->save();

        $this->status = StatusMonitoring::Reverted;
        $this->save();

        return $revision;
    }

    /**
     * Mengembalikan (rollback) kondisi data monitoring ke snapshot revisi tertentu.
     */
    public function restoreFromRevision(MonitoringRealisasiRevision|string $revision): bool
    {
        $rev = $revision instanceof MonitoringRealisasiRevision
            ? $revision
            : $this->revisions()->where('id', $revision)->first();

        if (!$rev || empty($rev->data_snapshot)) {
            return false;
        }

        $snapshot = $rev->data_snapshot;

        return DB::transaction(function () use ($snapshot, $rev) {
            $this->fill([
                'nomor_ba'          => $snapshot['nomor_ba'] ?? $this->nomor_ba,
                'id_plotting'       => $snapshot['id_plotting'] ?? $this->id_plotting,
                'id_kecamatan'      => $snapshot['id_kecamatan'] ?? $this->id_kecamatan,
                'id_desa'           => $snapshot['id_desa'] ?? $this->id_desa,
                'tahun_anggaran'    => $snapshot['tahun_anggaran'] ?? $this->tahun_anggaran,
                'sumber_dana'       => $snapshot['sumber_dana'] ?? $this->sumber_dana,
                'rencana_panjang'   => $snapshot['rencana_panjang'] ?? $this->rencana_panjang,
                'realisasi_panjang' => $snapshot['realisasi_panjang'] ?? $this->realisasi_panjang,
                'status'            => $rev->status_sebelum ?? StatusMonitoring::Draft->value,
                'keterangan'        => $snapshot['keterangan'] ?? $this->keterangan,
            ]);
            $this->save();

            // Pulihkan daftar segmen terkait jika terdapat di snapshot
            if (isset($snapshot['segmen_ids']) && is_array($snapshot['segmen_ids'])) {
                $this->items()->delete();
                foreach (array_unique($snapshot['segmen_ids']) as $segmenId) {
                    MonitoringRealisasiItem::create([
                        'id'            => (string) Str::uuid(),
                        'id_monitoring' => $this->id,
                        'id_segmen'     => $segmenId,
                    ]);
                }
            }

            return true;
        });
    }

    /**
     * Memeriksa apakah monitoring sedang dalam status revisi/reverted.
     */
    public function isReverted(): bool
    {
        return $this->status === StatusMonitoring::Reverted;
    }

    /**
     * Memeriksa apakah monitoring pernah mengalami revisi.
     */
    public function hasRevisions(): bool
    {
        return $this->revisions()->exists();
    }

    /**
     * Scope filter wilayah pengguna aktif.
     */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        if (!$user || $user->hasRole('admin') || $user->hasRole('verifierBappeda')) {
            return $query;
        }

        if ($user->hasRole('verifierKecamatan') || $user->id_kecamatan) {
            return $query->where('monitoring_realisasi.id_kecamatan', $user->id_kecamatan);
        }

        if ($user->id_desa) {
            return $query->where('monitoring_realisasi.id_desa', $user->id_desa);
        }

        return $query;
    }
}

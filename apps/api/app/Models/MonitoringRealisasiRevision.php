<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringRealisasiRevision extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'monitoring-realisasi-revisions';

    protected $table = 'monitoring_realisasi_revisions';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false; // Hanya menggunakan created_at dan reverted_at khusus

    protected $fillable = [
        'id_monitoring',
        'catatan_revisi',
        'status_sebelum',
        'data_snapshot',
        'revised_by',
        'reverted_at',
        'created_at',
    ];

    protected $casts = [
        'data_snapshot' => 'array',
        'reverted_at'   => 'datetime',
        'created_at'    => 'datetime',
    ];

    // ─────────────────────────────────────────────
    // Relasi Eloquent
    // ─────────────────────────────────────────────

    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(MonitoringRealisasi::class, 'id_monitoring', 'id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by', 'uuid');
    }

    // ─────────────────────────────────────────────
    // Scopes & Helper Domain
    // ─────────────────────────────────────────────

    /**
     * Scope urutan revisi terbaru ke terlama.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Memulihkan data dokumen monitoring induk dari snapshot revisi ini.
     */
    public function restoreToMonitoring(): bool
    {
        return $this->monitoring ? $this->monitoring->restoreFromRevision($this) : false;
    }

    /**
     * Daftar ID segmen fisik pada saat snapshot revisi diambil.
     */
    public function getSegmenIdsAttribute(): array
    {
        return $this->data_snapshot['segmen_ids'] ?? [];
    }

    /**
     * Realisasi panjang pada snapshot.
     */
    public function getSnapshotRealisasiPanjangAttribute(): ?float
    {
        return isset($this->data_snapshot['realisasi_panjang']) ? (float) $this->data_snapshot['realisasi_panjang'] : null;
    }

    /**
     * Rencana target panjang pada snapshot.
     */
    public function getSnapshotRencanaPanjangAttribute(): ?float
    {
        return isset($this->data_snapshot['rencana_panjang']) ? (float) $this->data_snapshot['rencana_panjang'] : null;
    }
}

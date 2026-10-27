<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkkdVerifikasi extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'bkkd-verifikasi';

    protected $table = 'bkkd_verifikasi';

    protected $fillable = [
        'id_proposal',
        'id_dokumen',
        'id_opd',
        'nomor_surat_hasil',
        'tanggal_surat_hasil',
        'tanggal_diterima',
        'hasil',
        'nilai_rekomendasi',
        'volume_rekomendasi',
        'panjang_rekomendasi',
        'lebar_rekomendasi',
        'tinggi_rekomendasi',
        'catatan_opd',
        'tipe_sumber',
        'url_link',
        'path_dokumen',
        'nama_dokumen_asli',
        'is_aktif',
        'versi',
        'input_by',
        'updated_by',
    ];

    protected $casts = [
        'nilai_rekomendasi' => 'decimal:2',
        'volume_rekomendasi' => 'decimal:2',
        'panjang_rekomendasi' => 'decimal:2',
        'lebar_rekomendasi' => 'decimal:2',
        'tinggi_rekomendasi' => 'decimal:2',
        'is_aktif' => 'boolean',
        'versi' => 'integer',
        'tanggal_surat_hasil' => 'date',
        'tanggal_diterima' => 'date',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'id_proposal', 'id')->withTrashed();
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(BkkdVerifikasiDokumen::class, 'id_dokumen', 'id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(RefOpd::class, 'id_opd', 'id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by', 'uuid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(BkkdVerifikasiRiwayat::class, 'id_verifikasi', 'id')->orderByDesc('versi');
    }
}

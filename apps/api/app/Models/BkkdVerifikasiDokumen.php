<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkkdVerifikasiDokumen extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'bkkd-verifikasi-dokumen';

    protected $table = 'bkkd_verifikasi_dokumen';

    protected $fillable = [
        'id_opd',
        'nomor_surat_hasil',
        'tanggal_surat_hasil',
        'tanggal_diterima',
        'tahun_anggaran',
        'tipe_sumber',
        'url_link',
        'path_dokumen',
        'nama_dokumen_asli',
        'catatan_umum',
        'input_by',
        'updated_by',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'tanggal_surat_hasil' => 'date',
        'tanggal_diterima' => 'date',
    ];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(RefOpd::class, 'id_opd', 'id');
    }

    public function verifikasi(): HasMany
    {
        return $this->hasMany(BkkdVerifikasi::class, 'id_dokumen', 'id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by', 'uuid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }
}

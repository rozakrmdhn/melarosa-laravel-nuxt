<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalTahunAnggaranWilayah extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'jadwal-tahun-anggaran-wilayah';

    protected $table = 'jadwal_tahun_anggaran_wilayah';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'jadwal_id',
        'id_kecamatan',
        'id_desa',
        'mode_akses',
        'mulai_at',
        'selesai_at',
        'allow_plotting',
        'allow_digitasi',
        'allow_non_pagu',
        'alasan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id_kecamatan'   => 'integer',
        'id_desa'        => 'integer',
        'mulai_at'       => 'datetime',
        'selesai_at'     => 'datetime',
        'allow_plotting' => 'boolean',
        'allow_digitasi' => 'boolean',
        'allow_non_pagu' => 'boolean',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalTahunAnggaran::class, 'jadwal_id', 'id');
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
}

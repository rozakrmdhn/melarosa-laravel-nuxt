<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalTahunAnggaran extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'jadwal-tahun-anggaran';

    protected $table = 'jadwal_tahun_anggaran';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tahun_anggaran',
        'nama_periode',
        'status_jadwal',
        'mulai_at',
        'selesai_at',
        'allow_plotting',
        'allow_digitasi',
        'allow_non_pagu',
        'catatan',
        'updated_by',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'mulai_at'       => 'datetime',
        'selesai_at'     => 'datetime',
        'allow_plotting' => 'boolean',
        'allow_digitasi' => 'boolean',
        'allow_non_pagu' => 'boolean',
    ];

    public function wilayahOverrides(): HasMany
    {
        return $this->hasMany(JadwalTahunAnggaranWilayah::class, 'jadwal_id', 'id')
            ->orderByDesc('created_at');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }
}

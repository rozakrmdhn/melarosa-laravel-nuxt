<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkkdVerifikasiRiwayat extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'bkkd_verifikasi_riwayat';

    protected $fillable = [
        'id_verifikasi',
        'versi',
        'aksi',
        'field_berubah',
        'data_sebelum',
        'data_sesudah',
        'alasan_perubahan',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'versi' => 'integer',
        'field_berubah' => 'array',
        'data_sebelum' => 'array',
        'data_sesudah' => 'array',
        'created_at' => 'datetime',
    ];

    public function verifikasi(): BelongsTo
    {
        return $this->belongsTo(BkkdVerifikasi::class, 'id_verifikasi', 'id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'uuid');
    }
}

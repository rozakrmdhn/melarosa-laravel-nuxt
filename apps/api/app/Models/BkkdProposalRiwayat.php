<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkkdProposalRiwayat extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'bkkd_proposal_riwayat';

    protected $fillable = [
        'id_proposal',
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

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'id_proposal', 'id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'uuid');
    }
}

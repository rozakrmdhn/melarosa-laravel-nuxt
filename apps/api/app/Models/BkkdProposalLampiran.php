<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkkdProposalLampiran extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'bkkd_proposal_lampiran';

    protected $fillable = [
        'id_proposal',
        'jenis_dokumen',
        'tipe_sumber',
        'url_link',
        'nama_asli',
        'path',
        'mime_type',
        'ukuran_byte',
        'uploaded_by',
        'created_at',
    ];

    protected $casts = [
        'ukuran_byte' => 'integer',
        'created_at' => 'datetime',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'id_proposal', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'uuid');
    }
}

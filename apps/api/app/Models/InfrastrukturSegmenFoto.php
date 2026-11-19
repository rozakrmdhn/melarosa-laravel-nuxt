<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfrastrukturSegmenFoto extends Model
{
    use HasUuids, Auditable;

    public const MAX_PER_SEGMEN = 4;

    public const IDEAL_MIN_PER_SEGMEN = 3;

    public const TAHAP_LABELS = [
        'Titik Awal (0%)',
        'Bentang Tengah (50%)',
        'Titik Akhir (100%)',
        'Detail Kondisi',
    ];

    public string $auditModule = 'infrastruktur-segmen';

    protected $table = 'infrastruktur_segmen_fotos';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'segmen_id',
        'urutan',
        'tahap_label',
        'keterangan',
        'disk',
        'path',
        'mime_type',
        'ukuran_byte',
        'lebar_px',
        'tinggi_px',
        'sha256',
        'uploaded_by',
    ];

    protected $hidden = [
        'disk',
        'path',
        'sha256',
    ];

    protected $appends = [
        'file_endpoint',
    ];

    protected $casts = [
        'urutan'      => 'integer',
        'ukuran_byte' => 'integer',
        'lebar_px'    => 'integer',
        'tinggi_px'   => 'integer',
    ];

    public function getFileEndpointAttribute(): string
    {
        return "admin/infrastruktur-segmen/{$this->segmen_id}/fotos/{$this->id}/file";
    }

    public function segmen(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSegmen::class, 'segmen_id', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'uuid');
    }
}

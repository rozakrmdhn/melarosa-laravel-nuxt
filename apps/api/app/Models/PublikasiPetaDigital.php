<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublikasiPetaDigital extends Model
{
    use HasUuids, Auditable, SoftDeletes;

    public const MAX_PDF_KB = 25600; // 25 MB
    public const MAX_PDF_BYTES = 26214400;
    public const MAX_GEOREF_IMAGE_KB = 15360; // 15 MB
    public const MAX_GEOREF_IMAGE_BYTES = 15728640;

    public const GEOREF_METHODS = [
        'affine',
        'homography',
        'corners',
    ];

    public const TINGKAT_WILAYAH = [
        'kabupaten',
        'kecamatan',
        'desa',
    ];

    public const SUMBER_TIPE = [
        'file_pdf',
        'external_url',
    ];

    public const KATEGORI_LIST = [
        'Peta Jalan Poros Desa',
        'Peta Infrastruktur Desa',
        'Peta Batas Administrasi',
        'Peta Rencana Pembangunan',
        'Atlas & Peta Dasar Wilayah',
        'Peta Tematik Lainnya',
    ];

    public string $auditModule = 'publikasi-peta-digital';

    protected $table = 'publikasi_peta_digital';

    protected $fillable = [
        'judul',
        'slug',
        'nomor_dokumen',
        'kategori',
        'tingkat_wilayah',
        'id_kecamatan',
        'nama_kecamatan',
        'id_desa',
        'nama_desa',
        'tahun_terbit',
        'edisi',
        'skala_peta',
        'ringkasan',
        'sumber_tipe',
        'external_url',
        'external_domain',
        'file_disk',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'download_count',
        'is_georeferenced',
        'georef_method',
        'georef_coordinates',
        'georef_bbox',
        'georef_gcps',
        'georef_rms_meters',
        'georef_opacity',
        'georef_image_disk',
        'georef_image_path',
        'georef_image_mime',
        'georef_image_width',
        'georef_image_height',
        'georeferenced_at',
        'georeferenced_by',
        'is_published',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'file_disk',
        'file_path',
        'checksum_sha256',
        'georef_image_disk',
        'georef_image_path',
    ];

    protected $casts = [
        'id_kecamatan' => 'integer',
        'id_desa' => 'integer',
        'tahun_terbit' => 'integer',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'is_georeferenced' => 'boolean',
        'georef_coordinates' => 'array',
        'georef_bbox' => 'array',
        'georef_gcps' => 'array',
        'georef_rms_meters' => 'float',
        'georef_opacity' => 'float',
        'georef_image_width' => 'integer',
        'georef_image_height' => 'integer',
        'georeferenced_at' => 'datetime',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'wilayah_label',
        'file_size_label',
        'has_file',
        'has_external_url',
        'download_path',
        'preview_path',
        'has_georef_image',
        'georef_image_path_url',
    ];

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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    public function georeferencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'georeferenced_by', 'uuid');
    }

    public function getWilayahLabelAttribute(): string
    {
        if ($this->tingkat_wilayah === 'desa') {
            $desa = $this->nama_desa ? "Desa {$this->nama_desa}" : 'Desa';
            return $this->nama_kecamatan ? "{$desa}, Kec. {$this->nama_kecamatan}" : $desa;
        }
        if ($this->tingkat_wilayah === 'kecamatan') {
            return $this->nama_kecamatan ? "Kec. {$this->nama_kecamatan}" : 'Kecamatan';
        }
        return 'Kabupaten Bojonegoro';
    }

    public function getFileSizeLabelAttribute(): ?string
    {
        $bytes = (int) ($this->file_size ?? 0);
        if ($bytes <= 0) {
            return null;
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }
        return "{$bytes} B";
    }

    public function getHasFileAttribute(): bool
    {
        return !empty($this->file_path);
    }

    public function getHasExternalUrlAttribute(): bool
    {
        return !empty($this->external_url);
    }

    public function getDownloadPathAttribute(): string
    {
        return "/api/v1/publikasi-peta/{$this->id}/download";
    }

    public function getPreviewPathAttribute(): ?string
    {
        if ($this->sumber_tipe === 'file_pdf' && !empty($this->file_path)) {
            return "/api/v1/publikasi-peta/{$this->id}/download?inline=1";
        }
        return null;
    }

    public function getHasGeorefImageAttribute(): bool
    {
        return !empty($this->georef_image_path);
    }

    public function getGeorefImagePathUrlAttribute(): ?string
    {
        if ($this->is_georeferenced && !empty($this->georef_image_path)) {
            $ver = $this->georeferenced_at?->timestamp ?? 0;
            return "/api/v1/publikasi-peta/{$this->id}/georef-image?v={$ver}";
        }
        return null;
    }
}

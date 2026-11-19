<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hasil analisa spasial segmen rtrw_jaringan_transportasi_ln terhadap jalan_porosdesa dan jalan_kabupaten_luarkota.
 */
class RtrwJaringanAnalisaSpasial extends Model
{
    public const STATUS_TERDATA_POROS = 'terdata_poros_desa';
    public const STATUS_TERDATA_KABUPATEN = 'terdata_jalan_kabupaten';
    public const STATUS_TUMPANG_TINDIH = 'tumpang_tindih_keduanya';
    public const STATUS_IRISAN_SEBAGIAN = 'irisan_sebagian';
    public const STATUS_BELUM_TERDATA = 'belum_terdata';

    protected $table = 'rtrw_jaringan_analisa_spasial';

    protected $primaryKey = 'rtrw_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'rtrw_id',
        'orde01',
        'orde02',
        'orde03',
        'orde04',
        'nama_segmen',
        'id_kecamatan',
        'nama_kecamatan',
        'id_desa',
        'nama_desa',
        'panjang_m',
        'poros_overlap_m',
        'poros_overlap_pct',
        'matched_poros_id',
        'matched_poros_kode',
        'matched_poros_nama',
        'matched_poros_hierarki',
        'kab_overlap_m',
        'kab_overlap_pct',
        'matched_kab_id',
        'matched_kab_no_ruas',
        'matched_kab_nama',
        'matched_kab_klas',
        'combined_overlap_m',
        'combined_overlap_pct',
        'uncovered_m',
        'status_analisa',
        'already_adopted',
        'bbox_json',
        'analyzed_at',
    ];

    protected $casts = [
        'rtrw_id' => 'integer',
        'id_kecamatan' => 'integer',
        'id_desa' => 'integer',
        'panjang_m' => 'float',
        'poros_overlap_m' => 'float',
        'poros_overlap_pct' => 'float',
        'matched_poros_kode' => 'integer',
        'kab_overlap_m' => 'float',
        'kab_overlap_pct' => 'float',
        'matched_kab_id' => 'integer',
        'combined_overlap_m' => 'float',
        'combined_overlap_pct' => 'float',
        'uncovered_m' => 'float',
        'already_adopted' => 'boolean',
        'bbox_json' => 'array',
        'analyzed_at' => 'datetime',
    ];
}

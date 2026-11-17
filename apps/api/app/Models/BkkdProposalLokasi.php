<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BkkdProposalLokasi extends Model
{
    use HasFactory, HasUuids, Auditable;

    public string $auditModule = 'bkkd-proposal-lokasi';

    protected $table = 'bkkd_proposal_lokasi';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const PROMOSI_BELUM_DIEVALUASI = 'belum_dievaluasi';
    public const PROMOSI_KANDIDAT = 'kandidat';
    public const PROMOSI_TIDAK_PERLU = 'tidak_perlu';
    public const PROMOSI_DIAJUKAN = 'diajukan';
    public const PROMOSI_DISETUJUI = 'disetujui';
    public const PROMOSI_DITOLAK = 'ditolak';

    protected $fillable = [
        'id_proposal',
        'id_jalan_poros',
        'id_jaringan_ruas',
        'urutan',
        'nama_lokasi',
        'nama_ruas_usulan',
        'hierarki_jalan_usulan',
        'panjang_rencana_m',
        'panjang_gis_m',
        'lebar_m',
        'status_analisa_spasial',
        'status_promosi_master',
        'ringkasan_analisa',
        'validasi_promosi_spasial',
        'catatan_promosi_master',
        'diajukan_promosi_oleh',
        'diajukan_promosi_pada',
        'divalidasi_promosi_oleh',
        'divalidasi_promosi_pada',
        'created_by',
        'updated_by',
        'geom',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'panjang_rencana_m' => 'decimal:2',
        'panjang_gis_m' => 'decimal:2',
        'lebar_m' => 'decimal:2',
        'ringkasan_analisa' => 'array',
        'validasi_promosi_spasial' => 'array',
        'diajukan_promosi_pada' => 'datetime',
        'divalidasi_promosi_pada' => 'datetime',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BkkdProposal::class, 'id_proposal', 'id');
    }

    public function jalanPoros(): BelongsTo
    {
        return $this->belongsTo(JalanPorosDesa::class, 'id_jalan_poros', 'id');
    }

    public function jaringanRuas(): BelongsTo
    {
        return $this->belongsTo(JalanPorosDesa::class, 'id_jaringan_ruas', 'id');
    }

    public function pengajuPromosi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_promosi_oleh', 'uuid');
    }

    public function validatorPromosi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_promosi_oleh', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    public function scopeWithGeoJson(Builder $query): Builder
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            return $query->select([
                'bkkd_proposal_lokasi.*',
                DB::raw('ST_AsGeoJSON(bkkd_proposal_lokasi.geom) as geojson'),
                DB::raw('ST_AsGeoJSON(ST_Centroid(bkkd_proposal_lokasi.geom)) as centroid'),
            ]);
        }

        return $query->select([
            'bkkd_proposal_lokasi.*',
            DB::raw('bkkd_proposal_lokasi.geom as geojson'),
            DB::raw('null as centroid'),
        ]);
    }
}


<?php

namespace App\Services;

use App\Helpers\SpatialHelper;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\JalanPorosDesa;
use App\Models\RtrwJaringanAnalisaSpasial;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RtrwJaringanJalanService
{
    public const ORDE_KERETA_API = 'Sistem Jaringan Kereta Api';

    public const STATUS_LABELS = [
        RtrwJaringanAnalisaSpasial::STATUS_BELUM_TERDATA => 'Belum Terdata',
        RtrwJaringanAnalisaSpasial::STATUS_IRISAN_SEBAGIAN => 'Irisan Sebagian',
        RtrwJaringanAnalisaSpasial::STATUS_TERDATA_POROS => 'Terdata Jalan Poros Desa',
        RtrwJaringanAnalisaSpasial::STATUS_TERDATA_KABUPATEN => 'Terdata Jalan Kabupaten',
        RtrwJaringanAnalisaSpasial::STATUS_TUMPANG_TINDIH => 'Beririsan Poros & Kabupaten',
    ];

    /**
     * Selesaikan nama kolom aktual pada tabel rtrw_jaringan_transportasi_ln (mendukung Postgres lowercase dan SQLite test).
     *
     * @return array<string, string>
     */
    public function resolveRtrwColumns(string $alias = 'r'): array
    {
        $cols = Schema::hasTable('rtrw_jaringan_transportasi_ln')
            ? Schema::getColumnListing('rtrw_jaringan_transportasi_ln')
            : [];
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $prefix = $alias !== '' ? "{$alias}." : '';

        $pick = function (array $candidates, string $fallbackSql = 'NULL') use ($cols, $isPgsql, $prefix): string {
            foreach ($candidates as $cand) {
                if (in_array($cand, $cols, true)) {
                    $quoted = $isPgsql ? '"' . str_replace('"', '""', $cand) . '"' : $cand;
                    return $prefix . $quoted;
                }
            }
            return $fallbackSql;
        };

        return [
            'nama_jln' => $pick(['nama_ruas', 'Nama_Jln', 'Nama_Ruas'], 'NULL'),
            'namobj' => $pick(['namobj', 'NAMOBJ'], "'Jalan'"),
            'orde01' => $pick(['orde01', 'ORDE01'], "''"),
            'orde02' => $pick(['orde02', 'ORDE02'], "''"),
            'orde03' => $pick(['orde03', 'ORDE03'], "''"),
            'orde04' => $pick(['orde04', 'ORDE04'], "''"),
            'stsjrn' => $pick(['stsjrn', 'STSJRN'], 'NULL'),
            'sbdata' => $pick(['sbdata', 'SBDATA'], 'NULL'),
        ];
    }

    /**
     * Selesaikan nama kolom aktual pada tabel jalan_kabupaten_luarkota.
     *
     * @return array<string, string>
     */
    public function resolveKabupatenColumns(string $alias = 'jk'): array
    {
        $cols = Schema::hasTable('jalan_kabupaten_luarkota')
            ? Schema::getColumnListing('jalan_kabupaten_luarkota')
            : [];
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $prefix = $alias !== '' ? "{$alias}." : '';

        $pick = function (array $candidates, string $fallbackSql = 'NULL') use ($cols, $isPgsql, $prefix): string {
            foreach ($candidates as $cand) {
                if (in_array($cand, $cols, true)) {
                    $quoted = $isPgsql ? '"' . str_replace('"', '""', $cand) . '"' : $cand;
                    return $prefix . $quoted;
                }
            }
            return $fallbackSql;
        };

        return [
            'nama_ruas' => $pick(['nama_ruas', 'Nama_Ruas'], "'Jalan Kabupaten'"),
            'no_ruas' => $pick(['no_ruas', 'No_Ruas'], 'NULL'),
            'pangkal' => $pick(['pangkal', 'Pangkal'], 'NULL'),
            'ujung' => $pick(['ujung', 'Ujung'], 'NULL'),
            'klas_ruas' => $pick(['klas_ruas', 'Klas_Ruas'], 'NULL'),
            'status_ruas' => $pick(['status_ruas', 'Status_Ruas'], "'Jalan Kabupaten'"),
            'nama_kecamatan' => $pick(['nama_kecamatan', 'kecamatan_', 'Nama_Kecamatan'], 'NULL'),
            'nama_desa' => $pick(['nama_desa', 'Nama_Desa'], 'NULL'),
            'panjang' => $pick(['panjang', 'Panjang'], '0'),
        ];
    }

    /**
     * Terapkan batasan wilayah user (jika non-admin/non-Bappeda) di atas filter opsional.
     *
     * @return array{id_kecamatan: int|null, id_desa: int|null}
     */
    protected function resolveEffectiveWilayah(array $filters, ?User $user = null): array
    {
        $restrictedKecId = null;
        $restrictedDesaId = null;
        if ($user && !$user->isAdminOrBappeda()) {
            if ($user->id_desa) {
                $restrictedDesaId = (int) $user->id_desa;
                $restrictedKecId = $user->id_kecamatan ? (int) $user->id_kecamatan : null;
            } elseif ($user->id_kecamatan) {
                $restrictedKecId = (int) $user->id_kecamatan;
            }
        }

        return [
            'id_kecamatan' => $restrictedKecId ?: (!empty($filters['id_kecamatan']) ? (int) $filters['id_kecamatan'] : null),
            'id_desa' => $restrictedDesaId ?: (!empty($filters['id_desa']) ? (int) $filters['id_desa'] : null),
        ];
    }

    /**
     * Klasifikasi hasil analisa spasial berdasarkan persentase overlap terhadap jalan_porosdesa dan jalan_kabupaten_luarkota.
     */
    public function classifyStatusAnalisa(
        float $porosPct,
        float $kabPct,
        float $combinedPct,
        bool $alreadyAdopted = false
    ): string {
        if ($porosPct >= 25.0 && $kabPct >= 25.0) {
            return RtrwJaringanAnalisaSpasial::STATUS_TUMPANG_TINDIH;
        }
        if ($kabPct >= 60.0 || ($kabPct >= 25.0 && $kabPct > $porosPct && $combinedPct >= 60.0)) {
            return RtrwJaringanAnalisaSpasial::STATUS_TERDATA_KABUPATEN;
        }
        if ($alreadyAdopted || $porosPct >= 60.0 || ($porosPct >= 25.0 && $porosPct >= $kabPct && $combinedPct >= 60.0)) {
            return RtrwJaringanAnalisaSpasial::STATUS_TERDATA_POROS;
        }
        if ($combinedPct >= 15.0) {
            return RtrwJaringanAnalisaSpasial::STATUS_IRISAN_SEBAGIAN;
        }

        return RtrwJaringanAnalisaSpasial::STATUS_BELUM_TERDATA;
    }

    /**
     * Jalankan analisa spasial batch rtrw_jaringan_transportasi_ln vs jalan_porosdesa dan jalan_kabupaten_luarkota.
     *
     * @param array{id_kecamatan?: int|null, rtrw_ids?: array<int, int>|null, batch_size?: int} $options
     */
    public function runSpatialAnalysis(array $options = []): array
    {
        if (!Schema::hasTable('rtrw_jaringan_transportasi_ln') || !Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            return [
                'processed_count' => 0,
                'duration_seconds' => 0.0,
                'status_counts' => [],
            ];
        }

        $t0 = microtime(true);
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $rtrwIds = collect($options['rtrw_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
        $idKecamatan = !empty($options['id_kecamatan']) ? (int) $options['id_kecamatan'] : null;
        $batchSize = max(100, min(2000, (int) ($options['batch_size'] ?? 500)));

        $cols = $this->resolveRtrwColumns('r');
        $idQuery = DB::table('rtrw_jaringan_transportasi_ln as r')
            ->whereRaw("COALESCE({$cols['orde01']}, '') <> ?", [self::ORDE_KERETA_API])
            ->orderBy('r.id');

        if (!empty($rtrwIds)) {
            $idQuery->whereIn('r.id', $rtrwIds);
        } elseif ($idKecamatan && $isPgsql && Schema::hasTable('bataswilayah_kecamatan')) {
            $idQuery->whereExists(function ($q) use ($idKecamatan) {
                $q->select(DB::raw(1))
                    ->from('bataswilayah_kecamatan as k')
                    ->where('k.id', $idKecamatan)
                    ->whereRaw('r.geom IS NOT NULL AND k.geom IS NOT NULL AND r.geom && k.geom AND ST_Intersects(r.geom, k.geom)');
            });
        } elseif ($idKecamatan && Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_kecamatan')) {
            $idQuery->where('r.id_kecamatan', $idKecamatan);
        }

        $allIds = $idQuery->pluck('r.id')->map(fn ($v) => (int) $v)->all();

        $statusCounts = [
            RtrwJaringanAnalisaSpasial::STATUS_BELUM_TERDATA => 0,
            RtrwJaringanAnalisaSpasial::STATUS_IRISAN_SEBAGIAN => 0,
            RtrwJaringanAnalisaSpasial::STATUS_TERDATA_POROS => 0,
            RtrwJaringanAnalisaSpasial::STATUS_TERDATA_KABUPATEN => 0,
            RtrwJaringanAnalisaSpasial::STATUS_TUMPANG_TINDIH => 0,
        ];

        if (empty($allIds)) {
            return [
                'processed_count' => 0,
                'duration_seconds' => 0.0,
                'status_counts' => $statusCounts,
            ];
        }

        $now = now()->toDateTimeString();
        $processed = 0;

        foreach (array_chunk($allIds, $batchSize) as $chunkIds) {
            $records = $isPgsql
                ? $this->computeBatchAnalysisPgsql($chunkIds, $cols, $now)
                : $this->computeBatchAnalysisSqlite($chunkIds, $cols, $now);

            if (!empty($records)) {
                DB::table('rtrw_jaringan_analisa_spasial')->upsert(
                    $records,
                    ['rtrw_id'],
                    [
                        'orde01', 'orde02', 'orde03', 'orde04', 'nama_segmen',
                        'id_kecamatan', 'nama_kecamatan', 'id_desa', 'nama_desa',
                        'panjang_m', 'poros_overlap_m', 'poros_overlap_pct',
                        'matched_poros_id', 'matched_poros_kode', 'matched_poros_nama', 'matched_poros_hierarki',
                        'kab_overlap_m', 'kab_overlap_pct',
                        'matched_kab_id', 'matched_kab_no_ruas', 'matched_kab_nama', 'matched_kab_klas',
                        'combined_overlap_m', 'combined_overlap_pct', 'uncovered_m',
                        'status_analisa', 'already_adopted', 'bbox_json', 'analyzed_at', 'updated_at',
                    ]
                );

                foreach ($records as $rec) {
                    $st = $rec['status_analisa'];
                    $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
                    $processed++;
                }
            }
        }

        return [
            'processed_count' => $processed,
            'duration_seconds' => round(microtime(true) - $t0, 2),
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * Hitung irisan spasial presisi untuk satu batch rtrw_id di PostgreSQL / PostGIS (UTM 32749, buffer 15 m).
     *
     * @param array<int, int> $chunkIds
     * @param array<string, string> $cols
     * @return array<int, array<string, mixed>>
     */
    protected function computeBatchAnalysisPgsql(array $chunkIds, array $cols, string $now): array
    {
        $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
        $hasPoros = Schema::hasTable('jalan_porosdesa');
        $hasKab = Schema::hasTable('jalan_kabupaten_luarkota');
        $hasDesa = Schema::hasTable('bataswilayah_desa');
        $hasKec = Schema::hasTable('bataswilayah_kecamatan');
        $kabCols = $hasKab ? $this->resolveKabupatenColumns('jk') : null;

        $desaSelect = $hasDesa
            ? "desa.id_desa, desa.nama_desa, COALESCE(desa.id_kecamatan, kec.id_kecamatan) AS id_kecamatan, COALESCE(desa.nama_kecamatan, kec.nama_kecamatan) AS nama_kecamatan"
            : "NULL::bigint AS id_desa, NULL::text AS nama_desa, NULL::bigint AS id_kecamatan, NULL::text AS nama_kecamatan";

        $desaJoin = $hasDesa ? "
            LEFT JOIN LATERAL (
                SELECT d.id AS id_desa, d.nama_desa, d.id_kecamatan, k.nama_kecamatan
                FROM bataswilayah_desa d
                LEFT JOIN bataswilayah_kecamatan k ON k.id = d.id_kecamatan
                WHERE d.geom IS NOT NULL
                  AND b.geom && d.geom
                  AND ST_Intersects(b.geom, d.geom)
                ORDER BY ST_Length(ST_Intersection(b.geom_utm, ST_Transform(d.geom, 32749))) DESC
                LIMIT 1
            ) desa ON TRUE
        " : '';

        $kecJoin = ($hasDesa && $hasKec) ? "
            LEFT JOIN LATERAL (
                SELECT k.id AS id_kecamatan, k.nama_kecamatan
                FROM bataswilayah_kecamatan k
                WHERE k.geom IS NOT NULL
                  AND b.geom && k.geom
                  AND ST_Intersects(b.geom, k.geom)
                ORDER BY ST_Length(ST_Intersection(b.geom_utm, ST_Transform(k.geom, 32749))) DESC
                LIMIT 1
            ) kec ON desa.id_kecamatan IS NULL
        " : '';

        $porosSelect = $hasPoros
            ? "poros.matched_poros_id, poros.matched_poros_kode, poros.matched_poros_nama, poros.matched_poros_hierarki, COALESCE(poros.overlap_m, 0) AS poros_overlap_m, poros.buf_union AS poros_buf, EXISTS (SELECT 1 FROM jalan_porosdesa jp_ref WHERE jp_ref.rtrw_source_id = b.rtrw_id) AS already_adopted"
            : "NULL::text AS matched_poros_id, NULL::integer AS matched_poros_kode, NULL::text AS matched_poros_nama, NULL::text AS matched_poros_hierarki, 0::double precision AS poros_overlap_m, NULL::geometry AS poros_buf, FALSE AS already_adopted";

        $porosJoin = $hasPoros ? "
            LEFT JOIN LATERAL (
                SELECT
                    (ARRAY_AGG(jp.id ORDER BY len_i DESC))[1] AS matched_poros_id,
                    (ARRAY_AGG(jp.kode_ruas ORDER BY len_i DESC))[1] AS matched_poros_kode,
                    (ARRAY_AGG(jp.nama_ruas ORDER BY len_i DESC))[1] AS matched_poros_nama,
                    (ARRAY_AGG(jp.hierarki_jalan ORDER BY len_i DESC))[1] AS matched_poros_hierarki,
                    ST_Union(buf_geom) AS buf_union,
                    ST_Length(ST_Intersection(b.geom_utm, ST_Union(buf_geom))) AS overlap_m
                FROM (
                    SELECT
                        jp.id,
                        jp.kode_ruas,
                        jp.nama_ruas,
                        COALESCE(jp.hierarki_jalan, 'poros_desa') AS hierarki_jalan,
                        ST_Buffer(ST_Transform(jp.geom, 32749), 15) AS buf_geom,
                        ST_Length(ST_Intersection(b.geom_utm, ST_Buffer(ST_Transform(jp.geom, 32749), 15))) AS len_i
                    FROM jalan_porosdesa jp
                    WHERE jp.geom IS NOT NULL
                      AND jp.geom && ST_Expand(b.geom, 0.00025)
                      AND ST_DWithin(ST_Transform(jp.geom, 32749), b.geom_utm, 15)
                ) jp
            ) poros ON TRUE
        " : '';

        $kabSelect = $hasKab
            ? "kab.matched_kab_id, kab.matched_kab_no_ruas, kab.matched_kab_nama, kab.matched_kab_klas, COALESCE(kab.overlap_m, 0) AS kab_overlap_m, kab.buf_union AS kab_buf"
            : "NULL::bigint AS matched_kab_id, NULL::text AS matched_kab_no_ruas, NULL::text AS matched_kab_nama, NULL::text AS matched_kab_klas, 0::double precision AS kab_overlap_m, NULL::geometry AS kab_buf";

        $kabJoin = ($hasKab && $kabCols) ? "
            LEFT JOIN LATERAL (
                SELECT
                    (ARRAY_AGG(jk.id ORDER BY len_i DESC))[1] AS matched_kab_id,
                    (ARRAY_AGG(jk.no_ruas ORDER BY len_i DESC))[1] AS matched_kab_no_ruas,
                    (ARRAY_AGG(jk.nama_ruas ORDER BY len_i DESC))[1] AS matched_kab_nama,
                    (ARRAY_AGG(jk.klas_ruas ORDER BY len_i DESC))[1] AS matched_kab_klas,
                    ST_Union(buf_geom) AS buf_union,
                    ST_Length(ST_Intersection(b.geom_utm, ST_Union(buf_geom))) AS overlap_m
                FROM (
                    SELECT
                        jk.id,
                        {$kabCols['no_ruas']}::text AS no_ruas,
                        {$kabCols['nama_ruas']}::text AS nama_ruas,
                        {$kabCols['klas_ruas']}::text AS klas_ruas,
                        ST_Buffer(ST_Transform(jk.geom, 32749), 15) AS buf_geom,
                        ST_Length(ST_Intersection(b.geom_utm, ST_Buffer(ST_Transform(jk.geom, 32749), 15))) AS len_i
                    FROM jalan_kabupaten_luarkota jk
                    WHERE jk.geom IS NOT NULL
                      AND jk.geom && ST_Expand(b.geom, 0.00025)
                      AND ST_DWithin(ST_Transform(jk.geom, 32749), b.geom_utm, 15)
                ) jk
            ) kab ON TRUE
        " : '';

        $sql = "
            WITH batch AS (
                SELECT
                    r.id AS rtrw_id,
                    {$cols['orde01']}::text AS orde01,
                    {$cols['orde02']}::text AS orde02,
                    {$cols['orde03']}::text AS orde03,
                    {$cols['orde04']}::text AS orde04,
                    {$cols['nama_jln']}::text AS nama_jln,
                    {$cols['namobj']}::text AS namobj,
                    ST_Transform(r.geom, 4326) AS geom,
                    ST_Transform(r.geom, 32749) AS geom_utm,
                    ST_Length(ST_Transform(r.geom, 32749)) AS panjang_m
                FROM rtrw_jaringan_transportasi_ln r
                WHERE r.id IN ({$placeholders})
                  AND r.geom IS NOT NULL
            ),
            joined AS (
                SELECT
                    b.*,
                    {$desaSelect},
                    {$porosSelect},
                    {$kabSelect}
                FROM batch b
                {$desaJoin}
                {$kecJoin}
                {$porosJoin}
                {$kabJoin}
            )
            SELECT
                j.rtrw_id,
                j.orde01,
                j.orde02,
                j.orde03,
                j.orde04,
                j.nama_jln,
                j.namobj,
                j.id_kecamatan,
                j.nama_kecamatan,
                j.id_desa,
                j.nama_desa,
                ROUND(j.panjang_m::numeric, 2) AS panjang_m,
                ROUND(j.poros_overlap_m::numeric, 2) AS poros_overlap_m,
                j.matched_poros_id,
                j.matched_poros_kode,
                j.matched_poros_nama,
                j.matched_poros_hierarki,
                ROUND(j.kab_overlap_m::numeric, 2) AS kab_overlap_m,
                j.matched_kab_id,
                j.matched_kab_no_ruas,
                j.matched_kab_nama,
                j.matched_kab_klas,
                j.already_adopted,
                ROUND(
                    CASE
                        WHEN j.poros_buf IS NOT NULL AND j.kab_buf IS NOT NULL
                            THEN ST_Length(ST_Intersection(j.geom_utm, ST_Union(j.poros_buf, j.kab_buf)))
                        WHEN j.poros_buf IS NOT NULL THEN j.poros_overlap_m
                        WHEN j.kab_buf IS NOT NULL THEN j.kab_overlap_m
                        ELSE 0
                    END::numeric,
                    2
                ) AS combined_overlap_m,
                ST_XMin(j.geom) AS min_lng,
                ST_YMin(j.geom) AS min_lat,
                ST_XMax(j.geom) AS max_lng,
                ST_YMax(j.geom) AS max_lat
            FROM joined j
        ";

        $rows = DB::select($sql, $chunkIds);
        $records = [];

        foreach ($rows as $row) {
            $panjangM = max(0.0, round((float) $row->panjang_m, 2));
            $denom = max(1.0, $panjangM);
            $porosM = min($panjangM, max(0.0, round((float) $row->poros_overlap_m, 2)));
            $kabM = min($panjangM, max(0.0, round((float) $row->kab_overlap_m, 2)));
            $combinedM = min($panjangM, max($porosM, $kabM, round((float) $row->combined_overlap_m, 2)));

            $porosPct = round(min(100.0, ($porosM / $denom) * 100.0), 1);
            $kabPct = round(min(100.0, ($kabM / $denom) * 100.0), 1);
            $combinedPct = round(min(100.0, ($combinedM / $denom) * 100.0), 1);
            $uncoveredM = max(0.0, round($panjangM - $combinedM, 2));
            $alreadyAdopted = (bool) $row->already_adopted;

            $status = $this->classifyStatusAnalisa($porosPct, $kabPct, $combinedPct, $alreadyAdopted);
            $namaSegmen = trim((string) ($row->nama_jln ?? ''));
            if ($namaSegmen === '') {
                $namaSegmen = trim((string) ($row->namobj ?: 'Jalan'))
                    . ($row->nama_desa ? " {$row->nama_desa}" : '')
                    . " #RTRW-{$row->rtrw_id}";
            }

            $bbox = [
                round((float) $row->min_lng, 6),
                round((float) $row->min_lat, 6),
                round((float) $row->max_lng, 6),
                round((float) $row->max_lat, 6),
            ];

            $records[] = [
                'rtrw_id' => (int) $row->rtrw_id,
                'orde01' => $row->orde01 ? Str::limit((string) $row->orde01, 160, '') : null,
                'orde02' => $row->orde02 ? Str::limit((string) $row->orde02, 160, '') : null,
                'orde03' => $row->orde03 ? Str::limit((string) $row->orde03, 160, '') : null,
                'orde04' => $row->orde04 ? Str::limit((string) $row->orde04, 160, '') : null,
                'nama_segmen' => Str::limit($namaSegmen, 255, ''),
                'id_kecamatan' => $row->id_kecamatan ? (int) $row->id_kecamatan : null,
                'nama_kecamatan' => $row->nama_kecamatan ? Str::limit((string) $row->nama_kecamatan, 160, '') : null,
                'id_desa' => $row->id_desa ? (int) $row->id_desa : null,
                'nama_desa' => $row->nama_desa ? Str::limit((string) $row->nama_desa, 160, '') : null,
                'panjang_m' => $panjangM,
                'poros_overlap_m' => $porosM,
                'poros_overlap_pct' => $porosPct,
                'matched_poros_id' => $row->matched_poros_id ? (string) $row->matched_poros_id : null,
                'matched_poros_kode' => $row->matched_poros_kode !== null ? (int) $row->matched_poros_kode : null,
                'matched_poros_nama' => $row->matched_poros_nama ? Str::limit((string) $row->matched_poros_nama, 255, '') : null,
                'matched_poros_hierarki' => $row->matched_poros_hierarki ? (string) $row->matched_poros_hierarki : null,
                'kab_overlap_m' => $kabM,
                'kab_overlap_pct' => $kabPct,
                'matched_kab_id' => $row->matched_kab_id !== null ? (int) $row->matched_kab_id : null,
                'matched_kab_no_ruas' => $row->matched_kab_no_ruas !== null ? Str::limit((string) $row->matched_kab_no_ruas, 64, '') : null,
                'matched_kab_nama' => $row->matched_kab_nama ? Str::limit((string) $row->matched_kab_nama, 255, '') : null,
                'matched_kab_klas' => $row->matched_kab_klas ? Str::limit((string) $row->matched_kab_klas, 64, '') : null,
                'combined_overlap_m' => $combinedM,
                'combined_overlap_pct' => $combinedPct,
                'uncovered_m' => $uncoveredM,
                'status_analisa' => $status,
                'already_adopted' => $alreadyAdopted,
                'bbox_json' => json_encode($bbox),
                'analyzed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $records;
    }

    /**
     * Hitung irisan spasial di SQLite (lingkungan pengujian otomatis).
     *
     * @param array<int, int> $chunkIds
     * @param array<string, string> $cols
     * @return array<int, array<string, mixed>>
     */
    protected function computeBatchAnalysisSqlite(array $chunkIds, array $cols, string $now): array
    {
        $hasIdDesa = Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa');
        $hasIdKec = Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_kecamatan');
        $hasPanjang = Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'panjang_m');

        $rows = DB::table('rtrw_jaringan_transportasi_ln as r')
            ->whereIn('r.id', $chunkIds)
            ->select([
                'r.id',
                'r.geom',
                DB::raw("{$cols['nama_jln']} as nama_jln"),
                DB::raw("{$cols['namobj']} as namobj"),
                DB::raw("{$cols['orde01']} as orde01"),
                DB::raw("{$cols['orde02']} as orde02"),
                DB::raw("{$cols['orde03']} as orde03"),
                DB::raw("{$cols['orde04']} as orde04"),
                $hasIdDesa ? 'r.id_desa' : DB::raw('NULL as id_desa'),
                $hasIdKec ? 'r.id_kecamatan' : DB::raw('NULL as id_kecamatan'),
                $hasPanjang ? 'r.panjang_m' : DB::raw('NULL as panjang_m'),
            ])
            ->get();

        $desaNames = Schema::hasTable('bataswilayah_desa') ? BatasWilayahDesa::pluck('nama_desa', 'id') : collect();
        $desaKecMap = Schema::hasTable('bataswilayah_desa') ? BatasWilayahDesa::pluck('id_kecamatan', 'id') : collect();
        $kecNames = Schema::hasTable('bataswilayah_kecamatan') ? BatasWilayahKecamatan::pluck('nama_kecamatan', 'id') : collect();

        $porosLines = Schema::hasTable('jalan_porosdesa')
            ? DB::table('jalan_porosdesa')->get(['id', 'kode_ruas', 'nama_ruas', 'hierarki_jalan', 'rtrw_source_id', 'geom'])
                ->map(function ($p) {
                    $coords = $this->decodeLineCoordinates($p->geom);
                    return [
                        'id' => (string) $p->id,
                        'kode_ruas' => $p->kode_ruas !== null ? (int) $p->kode_ruas : null,
                        'nama_ruas' => (string) $p->nama_ruas,
                        'hierarki_jalan' => (string) ($p->hierarki_jalan ?? 'poros_desa'),
                        'rtrw_source_id' => $p->rtrw_source_id !== null ? (int) $p->rtrw_source_id : null,
                        'coords' => $coords,
                    ];
                })->all()
            : [];

        $kabLines = Schema::hasTable('jalan_kabupaten_luarkota')
            ? DB::table('jalan_kabupaten_luarkota')->get()->map(function ($k) {
                $arr = (array) $k;
                return [
                    'id' => (int) ($arr['id'] ?? 0),
                    'no_ruas' => $arr['no_ruas'] ?? $arr['No_Ruas'] ?? null,
                    'nama_ruas' => (string) ($arr['nama_ruas'] ?? $arr['Nama_Ruas'] ?? 'Jalan Kabupaten'),
                    'klas_ruas' => $arr['klas_ruas'] ?? $arr['Klas_Ruas'] ?? null,
                    'coords' => $this->decodeLineCoordinates($arr['geom'] ?? null),
                ];
            })->all()
            : [];

        $records = [];
        foreach ($rows as $row) {
            $coords = $this->decodeLineCoordinates($row->geom);
            $panjangM = $row->panjang_m !== null && (float) $row->panjang_m > 0
                ? round((float) $row->panjang_m, 2)
                : $this->polylineLengthMeters($coords);

            $bbox = $this->computeCoordsBbox($coords);
            $samples = $this->samplePolylinePoints($coords, 20);
            $sampleCount = max(1, count($samples));

            $porosHits = 0;
            $kabHits = 0;
            $bothOrEitherHits = 0;
            $porosHitCounts = [];
            $kabHitCounts = [];
            $alreadyAdopted = false;

            foreach ($porosLines as $p) {
                if ($p['rtrw_source_id'] === (int) $row->id) {
                    $alreadyAdopted = true;
                }
            }

            if (!empty($samples)) {
                foreach ($samples as $pt) {
                    $hitPoros = null;
                    foreach ($porosLines as $p) {
                        if (!empty($p['coords']) && $this->minDistancePointToPolylineMeters($pt, $p['coords']) <= 20.0) {
                            $hitPoros = $p;
                            break;
                        }
                    }
                    $hitKab = null;
                    foreach ($kabLines as $k) {
                        if (!empty($k['coords']) && $this->minDistancePointToPolylineMeters($pt, $k['coords']) <= 20.0) {
                            $hitKab = $k;
                            break;
                        }
                    }
                    if ($hitPoros) {
                        $porosHits++;
                        $porosHitCounts[$hitPoros['id']] = ($porosHitCounts[$hitPoros['id']] ?? 0) + 1;
                    }
                    if ($hitKab) {
                        $kabHits++;
                        $kabHitCounts[$hitKab['id']] = ($kabHitCounts[$hitKab['id']] ?? 0) + 1;
                    }
                    if ($hitPoros || $hitKab) {
                        $bothOrEitherHits++;
                    }
                }
            }

            $porosPct = round(($porosHits / $sampleCount) * 100.0, 1);
            $kabPct = round(($kabHits / $sampleCount) * 100.0, 1);
            $combinedPct = round(($bothOrEitherHits / $sampleCount) * 100.0, 1);
            $porosM = round($panjangM * ($porosPct / 100.0), 2);
            $kabM = round($panjangM * ($kabPct / 100.0), 2);
            $combinedM = round($panjangM * ($combinedPct / 100.0), 2);
            $uncoveredM = max(0.0, round($panjangM - $combinedM, 2));

            $bestPoros = null;
            if (!empty($porosHitCounts)) {
                arsort($porosHitCounts);
                $bestPid = (string) array_key_first($porosHitCounts);
                $bestPoros = collect($porosLines)->firstWhere('id', $bestPid);
            }

            $bestKab = null;
            if (!empty($kabHitCounts)) {
                arsort($kabHitCounts);
                $bestKid = (int) array_key_first($kabHitCounts);
                $bestKab = collect($kabLines)->firstWhere('id', $bestKid);
            }

            $idDesa = $row->id_desa ? (int) $row->id_desa : null;
            $idKec = $row->id_kecamatan ? (int) $row->id_kecamatan : ($idDesa ? ($desaKecMap[$idDesa] ?? null) : null);
            $namaDesa = $idDesa ? ($desaNames[$idDesa] ?? null) : null;
            $namaKec = $idKec ? ($kecNames[$idKec] ?? null) : null;

            $namaSegmen = trim((string) ($row->nama_jln ?? ''));
            if ($namaSegmen === '') {
                $namaSegmen = trim((string) ($row->namobj ?: 'Jalan'))
                    . ($namaDesa ? " {$namaDesa}" : '')
                    . " #RTRW-{$row->id}";
            }

            $records[] = [
                'rtrw_id' => (int) $row->id,
                'orde01' => $row->orde01 ? (string) $row->orde01 : null,
                'orde02' => $row->orde02 ? (string) $row->orde02 : null,
                'orde03' => $row->orde03 ? (string) $row->orde03 : null,
                'orde04' => $row->orde04 ? (string) $row->orde04 : null,
                'nama_segmen' => $namaSegmen,
                'id_kecamatan' => $idKec ? (int) $idKec : null,
                'nama_kecamatan' => $namaKec ? (string) $namaKec : null,
                'id_desa' => $idDesa,
                'nama_desa' => $namaDesa ? (string) $namaDesa : null,
                'panjang_m' => $panjangM,
                'poros_overlap_m' => $porosM,
                'poros_overlap_pct' => $porosPct,
                'matched_poros_id' => $bestPoros['id'] ?? null,
                'matched_poros_kode' => $bestPoros['kode_ruas'] ?? null,
                'matched_poros_nama' => $bestPoros['nama_ruas'] ?? null,
                'matched_poros_hierarki' => $bestPoros['hierarki_jalan'] ?? null,
                'kab_overlap_m' => $kabM,
                'kab_overlap_pct' => $kabPct,
                'matched_kab_id' => $bestKab['id'] ?? null,
                'matched_kab_no_ruas' => isset($bestKab['no_ruas']) ? (string) $bestKab['no_ruas'] : null,
                'matched_kab_nama' => $bestKab['nama_ruas'] ?? null,
                'matched_kab_klas' => $bestKab['klas_ruas'] ?? null,
                'combined_overlap_m' => $combinedM,
                'combined_overlap_pct' => $combinedPct,
                'uncovered_m' => $uncoveredM,
                'status_analisa' => $this->classifyStatusAnalisa($porosPct, $kabPct, $combinedPct, $alreadyAdopted),
                'already_adopted' => $alreadyAdopted,
                'bbox_json' => $bbox ? json_encode($bbox) : null,
                'analyzed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $records;
    }

    /**
     * Pastikan tabel hasil analisa spasial terisi apabila belum pernah dijalankan atau perlu refresh ringan.
     */
    protected function ensureSpatialAnalysisPopulated(): void
    {
        if (!Schema::hasTable('rtrw_jaringan_analisa_spasial') || !Schema::hasTable('rtrw_jaringan_transportasi_ln')) {
            return;
        }

        if (DB::table('rtrw_jaringan_analisa_spasial')->exists()) {
            return;
        }

        $this->runSpatialAnalysis(['batch_size' => 500]);
    }

    /**
     * Ringkasan analisa spasial rtrw_jaringan_transportasi_ln vs jalan_porosdesa & jalan_kabupaten_luarkota.
     */
    public function getSpatialAnalysisSummary(array $filters, ?User $user = null): array
    {
        $this->ensureSpatialAnalysisPopulated();

        $wilayah = $this->resolveEffectiveWilayah($filters, $user);
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $orde02 = trim((string) ($filters['orde02'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $onlyMissingDesa = filter_var($filters['only_missing_desa'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlyUnadopted = filter_var($filters['only_unadopted'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgsql ? 'ilike' : 'like';

        $baseQuery = DB::table('rtrw_jaringan_analisa_spasial as a');
        $this->applyAnalysisCommonFilters($baseQuery, $idKecamatan, $idDesa, $orde02, $search, $onlyMissingDesa, $onlyUnadopted, $likeOp);

        $adoptedCond = $isPgsql ? 'a.already_adopted IS TRUE' : 'a.already_adopted = 1';
        $row = (clone $baseQuery)->selectRaw("
            COUNT(*) as total_segmen,
            COALESCE(SUM(a.panjang_m), 0) as total_panjang_m,
            COALESCE(SUM(a.combined_overlap_m), 0) as total_tercover_m,
            COALESCE(SUM(a.uncovered_m), 0) as total_uncovered_m,
            COUNT(CASE WHEN {$adoptedCond} THEN 1 END) as already_adopted_count,
            COUNT(CASE WHEN a.status_analisa = 'belum_terdata' THEN 1 END) as cnt_belum_terdata,
            COALESCE(SUM(CASE WHEN a.status_analisa = 'belum_terdata' THEN a.panjang_m ELSE 0 END), 0) as len_belum_terdata,
            COUNT(CASE WHEN a.status_analisa = 'irisan_sebagian' THEN 1 END) as cnt_irisan_sebagian,
            COALESCE(SUM(CASE WHEN a.status_analisa = 'irisan_sebagian' THEN a.panjang_m ELSE 0 END), 0) as len_irisan_sebagian,
            COUNT(CASE WHEN a.status_analisa = 'terdata_poros_desa' THEN 1 END) as cnt_terdata_poros,
            COALESCE(SUM(CASE WHEN a.status_analisa = 'terdata_poros_desa' THEN a.panjang_m ELSE 0 END), 0) as len_terdata_poros,
            COUNT(CASE WHEN a.status_analisa = 'terdata_jalan_kabupaten' THEN 1 END) as cnt_terdata_kab,
            COALESCE(SUM(CASE WHEN a.status_analisa = 'terdata_jalan_kabupaten' THEN a.panjang_m ELSE 0 END), 0) as len_terdata_kab,
            COUNT(CASE WHEN a.status_analisa = 'tumpang_tindih_keduanya' THEN 1 END) as cnt_tumpang_tindih,
            COALESCE(SUM(CASE WHEN a.status_analisa = 'tumpang_tindih_keduanya' THEN a.panjang_m ELSE 0 END), 0) as len_tumpang_tindih,
            MAX(a.analyzed_at) as last_analyzed_at
        ")->first();

        $totalPanjangM = round((float) ($row->total_panjang_m ?? 0), 2);
        $totalTercoverM = round((float) ($row->total_tercover_m ?? 0), 2);
        $avgCoveragePct = $totalPanjangM > 0 ? round(min(100.0, ($totalTercoverM / $totalPanjangM) * 100.0), 1) : 0.0;

        return $this->buildSafeSummaryResponse($filters, $user, $wilayah, $row, $totalPanjangM, $totalTercoverM, $avgCoveragePct, $likeOp);
    }

    /**
     * Bangun payload ringkasan analisa spasial secara portable (PostgreSQL & SQLite).
     *
     * @param array{id_kecamatan: int|null, id_desa: int|null} $wilayah
     */
    protected function buildSafeSummaryResponse(
        array $filters,
        ?User $user,
        array $wilayah,
        ?object $row,
        float $totalPanjangM,
        float $totalTercoverM,
        float $avgCoveragePct,
        string $likeOp
    ): array {
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $orde02 = trim((string) ($filters['orde02'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $onlyMissingDesa = filter_var($filters['only_missing_desa'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlyUnadopted = filter_var($filters['only_unadopted'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ordeBase = DB::table('rtrw_jaringan_analisa_spasial as a');
        $this->applyAnalysisCommonFilters($ordeBase, $idKecamatan, $idDesa, '', $search, $onlyMissingDesa, $onlyUnadopted, $likeOp);
        $byOrde = $ordeBase->selectRaw("
            COALESCE(a.orde02, 'Lainnya') as orde02,
            COUNT(*) as total_segmen,
            COALESCE(SUM(a.panjang_m), 0) as total_panjang_m,
            COALESCE(SUM(a.combined_overlap_m), 0) as total_tercover_m,
            COUNT(CASE WHEN a.status_analisa = 'belum_terdata' THEN 1 END) as belum_terdata,
            COUNT(CASE WHEN a.status_analisa = 'irisan_sebagian' THEN 1 END) as irisan_sebagian,
            COUNT(CASE WHEN a.status_analisa = 'terdata_poros_desa' THEN 1 END) as terdata_poros_desa,
            COUNT(CASE WHEN a.status_analisa = 'terdata_jalan_kabupaten' THEN 1 END) as terdata_jalan_kabupaten,
            COUNT(CASE WHEN a.status_analisa = 'tumpang_tindih_keduanya' THEN 1 END) as tumpang_tindih_keduanya
        ")
            ->groupByRaw("COALESCE(a.orde02, 'Lainnya')")
            ->orderByDesc('total_segmen')
            ->get()
            ->map(function ($o) {
                $len = round((float) $o->total_panjang_m, 2);
                $cov = round((float) $o->total_tercover_m, 2);
                return [
                    'orde02' => (string) $o->orde02,
                    'total_segmen' => (int) $o->total_segmen,
                    'total_panjang_m' => $len,
                    'coverage_pct' => $len > 0 ? round(min(100.0, ($cov / $len) * 100.0), 1) : 0.0,
                    'belum_terdata' => (int) $o->belum_terdata,
                    'irisan_sebagian' => (int) $o->irisan_sebagian,
                    'terdata_poros_desa' => (int) $o->terdata_poros_desa,
                    'terdata_jalan_kabupaten' => (int) $o->terdata_jalan_kabupaten,
                    'tumpang_tindih_keduanya' => (int) $o->tumpang_tindih_keduanya,
                ];
            })
            ->values()
            ->all();

        $ordeOptions = array_map(fn ($o) => [
            'value' => $o['orde02'],
            'label' => $o['orde02'],
            'total' => $o['total_segmen'],
        ], $byOrde);

        // Breakdown wilayah (per Kecamatan jika tingkat Kabupaten, per Desa jika Kecamatan dipilih)
        $wilayahBase = DB::table('rtrw_jaringan_analisa_spasial as a');
        $this->applyAnalysisCommonFilters($wilayahBase, $idKecamatan, $idDesa, $orde02, $search, $onlyMissingDesa, $onlyUnadopted, $likeOp);
        if ($idKecamatan) {
            $wilayahRows = $wilayahBase->whereNotNull('a.id_desa')
                ->selectRaw("
                    a.id_desa as id,
                    MAX(a.nama_desa) as nama,
                    COUNT(*) as total_segmen,
                    COALESCE(SUM(a.panjang_m), 0) as total_panjang_m,
                    COALESCE(SUM(a.combined_overlap_m), 0) as total_tercover_m,
                    COALESCE(SUM(a.uncovered_m), 0) as uncovered_m,
                    COUNT(CASE WHEN a.status_analisa = 'belum_terdata' THEN 1 END) as belum_terdata,
                    COUNT(CASE WHEN a.status_analisa = 'terdata_poros_desa' THEN 1 END) as terdata_poros_desa,
                    COUNT(CASE WHEN a.status_analisa = 'terdata_jalan_kabupaten' THEN 1 END) as terdata_jalan_kabupaten,
                    COUNT(CASE WHEN a.status_analisa = 'tumpang_tindih_keduanya' THEN 1 END) as tumpang_tindih_keduanya
                ")
                ->groupBy('a.id_desa')
                ->orderByDesc('uncovered_m')
                ->limit(30)
                ->get();
            $wilayahLevel = 'desa';
        } else {
            $wilayahRows = $wilayahBase->whereNotNull('a.id_kecamatan')
                ->selectRaw("
                    a.id_kecamatan as id,
                    MAX(a.nama_kecamatan) as nama,
                    COUNT(*) as total_segmen,
                    COALESCE(SUM(a.panjang_m), 0) as total_panjang_m,
                    COALESCE(SUM(a.combined_overlap_m), 0) as total_tercover_m,
                    COALESCE(SUM(a.uncovered_m), 0) as uncovered_m,
                    COUNT(CASE WHEN a.status_analisa = 'belum_terdata' THEN 1 END) as belum_terdata,
                    COUNT(CASE WHEN a.status_analisa = 'terdata_poros_desa' THEN 1 END) as terdata_poros_desa,
                    COUNT(CASE WHEN a.status_analisa = 'terdata_jalan_kabupaten' THEN 1 END) as terdata_jalan_kabupaten,
                    COUNT(CASE WHEN a.status_analisa = 'tumpang_tindih_keduanya' THEN 1 END) as tumpang_tindih_keduanya
                ")
                ->groupBy('a.id_kecamatan')
                ->orderByDesc('uncovered_m')
                ->limit(28)
                ->get();
            $wilayahLevel = 'kecamatan';
        }

        $byWilayah = $wilayahRows->map(function ($w) {
            $len = round((float) $w->total_panjang_m, 2);
            $cov = round((float) $w->total_tercover_m, 2);
            return [
                'id' => (int) $w->id,
                'nama' => (string) ($w->nama ?: '-'),
                'total_segmen' => (int) $w->total_segmen,
                'total_panjang_m' => $len,
                'uncovered_m' => round((float) $w->uncovered_m, 2),
                'coverage_pct' => $len > 0 ? round(min(100.0, ($cov / $len) * 100.0), 1) : 0.0,
                'belum_terdata' => (int) $w->belum_terdata,
                'terdata_poros_desa' => (int) $w->terdata_poros_desa,
                'terdata_jalan_kabupaten' => (int) $w->terdata_jalan_kabupaten,
                'tumpang_tindih_keduanya' => (int) $w->tumpang_tindih_keduanya,
            ];
        })->values()->all();

        // Statistik dataset pembanding (jalan_porosdesa & jalan_kabupaten_luarkota)
        $porosQuery = JalanPorosDesa::query()->forUser($user);
        if ($idKecamatan) {
            $porosQuery->where('id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $porosQuery->where('id_desa', $idDesa);
        }
        $porosStats = $porosQuery->selectRaw('COUNT(*) as total_ruas, COALESCE(SUM(panjang), 0) as total_panjang_m')->first();

        $kabCount = 0;
        $kabPanjangM = 0.0;
        if (Schema::hasTable('jalan_kabupaten_luarkota')) {
            $kabCols = $this->resolveKabupatenColumns('jk');
            $kabQuery = DB::table('jalan_kabupaten_luarkota as jk');
            if ($idDesa && Schema::hasTable('bataswilayah_desa') && DB::connection()->getDriverName() === 'pgsql') {
                $kabQuery->whereExists(function ($q) use ($idDesa) {
                    $q->select(DB::raw(1))->from('bataswilayah_desa as bd')
                        ->where('bd.id', $idDesa)
                        ->whereRaw('jk.geom IS NOT NULL AND bd.geom IS NOT NULL AND jk.geom && bd.geom AND ST_Intersects(jk.geom, bd.geom)');
                });
            } elseif ($idKecamatan && Schema::hasTable('bataswilayah_kecamatan') && DB::connection()->getDriverName() === 'pgsql') {
                $kabQuery->whereExists(function ($q) use ($idKecamatan) {
                    $q->select(DB::raw(1))->from('bataswilayah_kecamatan as bk')
                        ->where('bk.id', $idKecamatan)
                        ->whereRaw('jk.geom IS NOT NULL AND bk.geom IS NOT NULL AND jk.geom && bk.geom AND ST_Intersects(jk.geom, bk.geom)');
                });
            }
            $kabStat = $kabQuery->selectRaw("COUNT(*) as total_ruas, COALESCE(SUM({$kabCols['panjang']}), 0) as total_panjang_m")->first();
            $kabCount = (int) ($kabStat->total_ruas ?? 0);
            $kabPanjangM = round((float) ($kabStat->total_panjang_m ?? 0), 2);
        }

        // Cakupan desa
        $cakupanSummary = $this->getDesaCoverageCounts($idKecamatan, $idDesa);

        return [
            'totals' => [
                'total_segmen' => (int) ($row->total_segmen ?? 0),
                'total_panjang_m' => $totalPanjangM,
                'total_tercover_m' => $totalTercoverM,
                'total_uncovered_m' => round((float) ($row->total_uncovered_m ?? 0), 2),
                'avg_coverage_pct' => $avgCoveragePct,
                'already_adopted_count' => (int) ($row->already_adopted_count ?? 0),
                'last_analyzed_at' => $row->last_analyzed_at ? (string) $row->last_analyzed_at : null,
                'statuses' => [
                    RtrwJaringanAnalisaSpasial::STATUS_BELUM_TERDATA => [
                        'count' => (int) ($row->cnt_belum_terdata ?? 0),
                        'panjang_m' => round((float) ($row->len_belum_terdata ?? 0), 2),
                    ],
                    RtrwJaringanAnalisaSpasial::STATUS_IRISAN_SEBAGIAN => [
                        'count' => (int) ($row->cnt_irisan_sebagian ?? 0),
                        'panjang_m' => round((float) ($row->len_irisan_sebagian ?? 0), 2),
                    ],
                    RtrwJaringanAnalisaSpasial::STATUS_TERDATA_POROS => [
                        'count' => (int) ($row->cnt_terdata_poros ?? 0),
                        'panjang_m' => round((float) ($row->len_terdata_poros ?? 0), 2),
                    ],
                    RtrwJaringanAnalisaSpasial::STATUS_TERDATA_KABUPATEN => [
                        'count' => (int) ($row->cnt_terdata_kab ?? 0),
                        'panjang_m' => round((float) ($row->len_terdata_kab ?? 0), 2),
                    ],
                    RtrwJaringanAnalisaSpasial::STATUS_TUMPANG_TINDIH => [
                        'count' => (int) ($row->cnt_tumpang_tindih ?? 0),
                        'panjang_m' => round((float) ($row->len_tumpang_tindih ?? 0), 2),
                    ],
                ],
            ],
            'dataset_overview' => [
                'rtrw_segmen' => (int) ($row->total_segmen ?? 0),
                'rtrw_panjang_m' => $totalPanjangM,
                'jalan_poros_ruas' => (int) ($porosStats->total_ruas ?? 0),
                'jalan_poros_panjang_m' => round((float) ($porosStats->total_panjang_m ?? 0), 2),
                'jalan_kabupaten_ruas' => $kabCount,
                'jalan_kabupaten_panjang_m' => $kabPanjangM,
            ],
            'desa_coverage' => $cakupanSummary,
            'by_orde' => $byOrde,
            'wilayah_level' => $wilayahLevel,
            'by_wilayah' => $byWilayah,
            'orde_options' => $ordeOptions,
        ];
    }

    /**
     * Terapkan filter standar pada query rtrw_jaringan_analisa_spasial.
     */
    protected function applyAnalysisCommonFilters(
        $query,
        ?int $idKecamatan,
        ?int $idDesa,
        string $orde02,
        string $search,
        bool $onlyMissingDesa,
        bool $onlyUnadopted,
        string $likeOp
    ): void {
        if ($idKecamatan) {
            $query->where('a.id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $query->where('a.id_desa', $idDesa);
        }
        if ($orde02 !== '') {
            $query->where('a.orde02', $orde02);
        }
        if ($onlyUnadopted) {
            $query->where('a.already_adopted', false);
        }
        if ($onlyMissingDesa && Schema::hasTable('bataswilayah_desa') && Schema::hasTable('jalan_porosdesa')) {
            $query->whereNotNull('a.id_desa')
                ->whereRaw('NOT EXISTS (SELECT 1 FROM jalan_porosdesa jp WHERE jp.id_desa = a.id_desa)');
        }
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term, $likeOp) {
                $q->where('a.nama_segmen', $likeOp, $term)
                    ->orWhere('a.nama_desa', $likeOp, $term)
                    ->orWhere('a.nama_kecamatan', $likeOp, $term)
                    ->orWhere('a.matched_poros_nama', $likeOp, $term)
                    ->orWhere('a.matched_kab_nama', $likeOp, $term)
                    ->orWhere('a.orde02', $likeOp, $term);
            });
        }
    }

    /**
     * Daftar paginasi hasil analisa spasial segmen RTRW terhadap jalan_porosdesa & jalan_kabupaten_luarkota.
     */
    public function listSpatialAnalysisSegments(array $filters, ?User $user = null): array
    {
        $this->ensureSpatialAnalysisPopulated();

        if (!Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            return [
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0],
            ];
        }

        $wilayah = $this->resolveEffectiveWilayah($filters, $user);
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $orde02 = trim((string) ($filters['orde02'] ?? ''));
        $statusAnalisa = trim((string) ($filters['status_analisa'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $onlyMissingDesa = filter_var($filters['only_missing_desa'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlyUnadopted = filter_var($filters['only_unadopted'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sortBy = (string) ($filters['sort_by'] ?? 'uncovered_desc');
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgsql ? 'ilike' : 'like';

        $query = DB::table('rtrw_jaringan_analisa_spasial as a');
        $this->applyAnalysisCommonFilters($query, $idKecamatan, $idDesa, $orde02, $search, $onlyMissingDesa, $onlyUnadopted, $likeOp);

        if ($statusAnalisa !== '' && $statusAnalisa !== 'semua') {
            $query->where('a.status_analisa', $statusAnalisa);
        }

        switch ($sortBy) {
            case 'panjang_desc':
                $query->orderByDesc('a.panjang_m')->orderBy('a.rtrw_id');
                break;
            case 'poros_pct_desc':
                $query->orderByDesc('a.poros_overlap_pct')->orderByDesc('a.panjang_m');
                break;
            case 'kab_pct_desc':
                $query->orderByDesc('a.kab_overlap_pct')->orderByDesc('a.panjang_m');
                break;
            case 'id_asc':
                $query->orderBy('a.rtrw_id');
                break;
            default:
                $query->orderByDesc('a.uncovered_m')->orderByDesc('a.panjang_m')->orderBy('a.rtrw_id');
                break;
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));
        $paginated = $query->paginate($perPage);

        $rows = collect($paginated->items())->map(function ($row) {
            $bbox = is_string($row->bbox_json) ? json_decode($row->bbox_json, true) : (is_array($row->bbox_json) ? $row->bbox_json : null);
            return [
                'id' => (int) $row->rtrw_id,
                'display_name' => (string) ($row->nama_segmen ?: "Segmen RTRW #{$row->rtrw_id}"),
                'orde01' => $row->orde01 ? (string) $row->orde01 : null,
                'orde02' => $row->orde02 ? (string) $row->orde02 : null,
                'orde03' => $row->orde03 ? (string) $row->orde03 : null,
                'orde04' => $row->orde04 ? (string) $row->orde04 : null,
                'id_kecamatan' => $row->id_kecamatan !== null ? (int) $row->id_kecamatan : null,
                'nama_kecamatan' => $row->nama_kecamatan ? (string) $row->nama_kecamatan : null,
                'id_desa' => $row->id_desa !== null ? (int) $row->id_desa : null,
                'nama_desa' => $row->nama_desa ? (string) $row->nama_desa : null,
                'panjang_m' => round((float) $row->panjang_m, 2),
                'poros_overlap_m' => round((float) $row->poros_overlap_m, 2),
                'poros_overlap_pct' => round((float) $row->poros_overlap_pct, 1),
                'matched_poros_id' => $row->matched_poros_id ? (string) $row->matched_poros_id : null,
                'matched_poros_kode' => $row->matched_poros_kode !== null ? (int) $row->matched_poros_kode : null,
                'matched_poros_nama' => $row->matched_poros_nama ? (string) $row->matched_poros_nama : null,
                'matched_poros_hierarki' => $row->matched_poros_hierarki ? (string) $row->matched_poros_hierarki : null,
                'kab_overlap_m' => round((float) $row->kab_overlap_m, 2),
                'kab_overlap_pct' => round((float) $row->kab_overlap_pct, 1),
                'matched_kab_id' => $row->matched_kab_id !== null ? (int) $row->matched_kab_id : null,
                'matched_kab_no_ruas' => $row->matched_kab_no_ruas ? (string) $row->matched_kab_no_ruas : null,
                'matched_kab_nama' => $row->matched_kab_nama ? (string) $row->matched_kab_nama : null,
                'matched_kab_klas' => $row->matched_kab_klas ? (string) $row->matched_kab_klas : null,
                'combined_overlap_m' => round((float) $row->combined_overlap_m, 2),
                'combined_overlap_pct' => round((float) $row->combined_overlap_pct, 1),
                'uncovered_m' => round((float) $row->uncovered_m, 2),
                'status_analisa' => (string) $row->status_analisa,
                'already_adopted' => (bool) $row->already_adopted,
                'recommended_hierarki' => $this->mapOrdeToHierarki((string) ($row->orde02 ?? '')),
                'bbox' => is_array($bbox) && count($bbox) === 4 ? $bbox : null,
                'analyzed_at' => $row->analyzed_at ? (string) $row->analyzed_at : null,
            ];
        })->values()->all();

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Indeks ringkas ID segmen RTRW per status untuk pewarnaan vector tile MapLibre GL JS (Martin).
     */
    public function getSpatialAnalysisMapIndex(array $filters, ?User $user = null): array
    {
        $this->ensureSpatialAnalysisPopulated();

        $wilayah = $this->resolveEffectiveWilayah($filters, $user);
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $orde02 = trim((string) ($filters['orde02'] ?? ''));
        $statusAnalisa = trim((string) ($filters['status_analisa'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $onlyMissingDesa = filter_var($filters['only_missing_desa'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlyUnadopted = filter_var($filters['only_unadopted'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgsql ? 'ilike' : 'like';

        $idsByStatus = [
            RtrwJaringanAnalisaSpasial::STATUS_BELUM_TERDATA => [],
            RtrwJaringanAnalisaSpasial::STATUS_IRISAN_SEBAGIAN => [],
            RtrwJaringanAnalisaSpasial::STATUS_TERDATA_POROS => [],
            RtrwJaringanAnalisaSpasial::STATUS_TERDATA_KABUPATEN => [],
            RtrwJaringanAnalisaSpasial::STATUS_TUMPANG_TINDIH => [],
        ];

        $hasActiveFilter = (bool) ($idKecamatan || $idDesa || $orde02 !== '' || ($statusAnalisa !== '' && $statusAnalisa !== 'semua') || $search !== '' || $onlyMissingDesa || $onlyUnadopted);
        $filteredIds = $hasActiveFilter ? [] : null;

        if (Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            // Selalu kirim pemetaan status penuh agar fitur Martin tetap berwarna di luar filter (dengan opacity berbeda)
            $allStatusRows = DB::table('rtrw_jaringan_analisa_spasial')->select(['rtrw_id', 'status_analisa'])->get();
            foreach ($allStatusRows as $sr) {
                $st = (string) $sr->status_analisa;
                if (isset($idsByStatus[$st])) {
                    $idsByStatus[$st][] = (int) $sr->rtrw_id;
                }
            }

            if ($hasActiveFilter) {
                $fQuery = DB::table('rtrw_jaringan_analisa_spasial as a');
                $this->applyAnalysisCommonFilters($fQuery, $idKecamatan, $idDesa, $orde02, $search, $onlyMissingDesa, $onlyUnadopted, $likeOp);
                if ($statusAnalisa !== '' && $statusAnalisa !== 'semua') {
                    $fQuery->where('a.status_analisa', $statusAnalisa);
                }
                $filteredIds = $fQuery->pluck('a.rtrw_id')->map(fn ($v) => (int) $v)->all();
            }
        }

        // Hitung bounding box wilayah aktif jika dipilih kecamatan/desa
        $bounds = null;
        if ($isPgsql) {
            if ($idDesa && Schema::hasTable('bataswilayah_desa')) {
                $bRow = DB::selectOne('SELECT ST_XMin(g) AS minx, ST_YMin(g) AS miny, ST_XMax(g) AS maxx, ST_YMax(g) AS maxy FROM (SELECT ST_Transform(geom, 4326) AS g FROM bataswilayah_desa WHERE id = ? AND geom IS NOT NULL) sub', [$idDesa]);
                if ($bRow && $bRow->minx !== null) {
                    $bounds = [(float) $bRow->minx, (float) $bRow->miny, (float) $bRow->maxx, (float) $bRow->maxy];
                }
            } elseif ($idKecamatan && Schema::hasTable('bataswilayah_kecamatan')) {
                $bRow = DB::selectOne('SELECT ST_XMin(g) AS minx, ST_YMin(g) AS miny, ST_XMax(g) AS maxx, ST_YMax(g) AS maxy FROM (SELECT ST_Transform(geom, 4326) AS g FROM bataswilayah_kecamatan WHERE id = ? AND geom IS NOT NULL) sub', [$idKecamatan]);
                if ($bRow && $bRow->minx !== null) {
                    $bounds = [(float) $bRow->minx, (float) $bRow->miny, (float) $bRow->maxx, (float) $bRow->maxy];
                }
            }
        }

        return [
            'ids_by_status' => $idsByStatus,
            'has_active_filter' => $hasActiveFilter,
            'filtered_ids' => $filteredIds,
            'bounds' => $bounds,
        ];
    }

    /**
     * Detail lengkap analisa spasial satu segmen RTRW beserta seluruh ruas jalan_porosdesa & jalan_kabupaten_luarkota yang beririsan.
     */
    public function getSpatialAnalysisSegmentDetail(int $rtrwId, ?User $user = null): array
    {
        if (!Schema::hasTable('rtrw_jaringan_transportasi_ln')) {
            throw new InvalidArgumentException('Tabel rtrw_jaringan_transportasi_ln tidak ditemukan.');
        }

        $this->ensureSpatialAnalysisPopulated();

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $cols = $this->resolveRtrwColumns('r');

        $row = DB::table('rtrw_jaringan_transportasi_ln as r')
            ->where('r.id', $rtrwId)
            ->select([
                'r.id',
                DB::raw("{$cols['nama_jln']} as nama_jln"),
                DB::raw("{$cols['namobj']} as namobj"),
                DB::raw("{$cols['orde01']} as orde01"),
                DB::raw("{$cols['orde02']} as orde02"),
                DB::raw("{$cols['orde03']} as orde03"),
                DB::raw("{$cols['orde04']} as orde04"),
                DB::raw("{$cols['stsjrn']} as stsjrn"),
                DB::raw("{$cols['sbdata']} as sbdata"),
                $isPgsql ? DB::raw('ST_AsGeoJSON(ST_Transform(r.geom, 4326)) as geojson_str') : 'r.geom as geojson_str',
            ])
            ->first();

        if (!$row) {
            throw new InvalidArgumentException("Segmen RTRW #{$rtrwId} tidak ditemukan.");
        }

        $analysis = DB::table('rtrw_jaringan_analisa_spasial')->where('rtrw_id', $rtrwId)->first();
        if (!$analysis) {
            $this->runSpatialAnalysis(['rtrw_ids' => [$rtrwId]]);
            $analysis = DB::table('rtrw_jaringan_analisa_spasial')->where('rtrw_id', $rtrwId)->first();
        }

        $geomObj = is_string($row->geojson_str) ? json_decode($row->geojson_str, true) : null;
        $panjangM = $analysis ? round((float) $analysis->panjang_m, 2) : 0.0;
        $bbox = $analysis && $analysis->bbox_json
            ? (is_string($analysis->bbox_json) ? json_decode($analysis->bbox_json, true) : $analysis->bbox_json)
            : $this->computeCoordsBbox($this->decodeLineCoordinates($row->geojson_str));

        $matchedPoros = [];
        $matchedKabupaten = [];

        if ($isPgsql) {
            if (Schema::hasTable('jalan_porosdesa')) {
                $porosRows = DB::select("
                    WITH target AS (
                        SELECT geom, ST_Transform(geom, 32749) AS geom_utm, GREATEST(ST_Length(ST_Transform(geom, 32749)), 1) AS total_m
                        FROM rtrw_jaringan_transportasi_ln
                        WHERE id = ? AND geom IS NOT NULL
                    )
                    SELECT
                        jp.id, jp.kode_ruas, jp.nama_ruas, COALESCE(jp.hierarki_jalan, 'poros_desa') AS hierarki_jalan,
                        jp.sumber_data, jp.tahun_survey, jp.perkerasan, jp.kondisi, jp.panjang, jp.lebar,
                        jp.nama_desa, jp.nama_kecamatan, jp.rtrw_source_id,
                        ROUND(ST_Length(ST_Intersection(t.geom_utm, ST_Buffer(ST_Transform(jp.geom, 32749), 15)))::numeric, 2) AS overlap_m,
                        ROUND((ST_Length(ST_Intersection(t.geom_utm, ST_Buffer(ST_Transform(jp.geom, 32749), 15))) / t.total_m * 100)::numeric, 1) AS overlap_pct,
                        ST_AsGeoJSON(ST_Transform(jp.geom, 4326)) AS geojson_str
                    FROM target t
                    JOIN jalan_porosdesa jp
                      ON jp.geom IS NOT NULL
                     AND jp.geom && ST_Expand(t.geom, 0.00025)
                     AND ST_DWithin(ST_Transform(jp.geom, 32749), t.geom_utm, 15)
                    ORDER BY overlap_m DESC
                    LIMIT 12
                ", [$rtrwId]);

                foreach ($porosRows as $pr) {
                    $matchedPoros[] = [
                        'id' => (string) $pr->id,
                        'kode_ruas' => $pr->kode_ruas !== null ? (int) $pr->kode_ruas : null,
                        'nama_ruas' => (string) $pr->nama_ruas,
                        'hierarki_jalan' => (string) $pr->hierarki_jalan,
                        'sumber_data' => $pr->sumber_data ? (string) $pr->sumber_data : null,
                        'tahun_survey' => $pr->tahun_survey ? (int) $pr->tahun_survey : null,
                        'perkerasan' => $pr->perkerasan ? (string) $pr->perkerasan : null,
                        'kondisi' => $pr->kondisi ? (string) $pr->kondisi : null,
                        'panjang_m' => round((float) ($pr->panjang ?? 0), 2),
                        'lebar_m' => $pr->lebar !== null ? (float) $pr->lebar : null,
                        'nama_desa' => $pr->nama_desa ? (string) $pr->nama_desa : null,
                        'nama_kecamatan' => $pr->nama_kecamatan ? (string) $pr->nama_kecamatan : null,
                        'is_rtrw_origin' => (int) ($pr->rtrw_source_id ?? 0) === $rtrwId,
                        'overlap_m' => round((float) $pr->overlap_m, 2),
                        'overlap_pct' => min(100.0, round((float) $pr->overlap_pct, 1)),
                        'geojson' => is_string($pr->geojson_str) ? json_decode($pr->geojson_str, true) : null,
                    ];
                }
            }

            if (Schema::hasTable('jalan_kabupaten_luarkota')) {
                $kabCols = $this->resolveKabupatenColumns('jk');
                $kabRows = DB::select("
                    WITH target AS (
                        SELECT geom, ST_Transform(geom, 32749) AS geom_utm, GREATEST(ST_Length(ST_Transform(geom, 32749)), 1) AS total_m
                        FROM rtrw_jaringan_transportasi_ln
                        WHERE id = ? AND geom IS NOT NULL
                    )
                    SELECT
                        jk.id,
                        {$kabCols['no_ruas']}::text AS no_ruas,
                        {$kabCols['nama_ruas']}::text AS nama_ruas,
                        {$kabCols['pangkal']}::text AS pangkal,
                        {$kabCols['ujung']}::text AS ujung,
                        {$kabCols['klas_ruas']}::text AS klas_ruas,
                        {$kabCols['status_ruas']}::text AS status_ruas,
                        {$kabCols['nama_kecamatan']}::text AS nama_kecamatan,
                        {$kabCols['nama_desa']}::text AS nama_desa,
                        COALESCE(NULLIF({$kabCols['panjang']}, 0), ROUND(ST_Length(ST_Transform(jk.geom, 32749))::numeric, 2)) AS panjang_m,
                        ROUND(ST_Length(ST_Intersection(t.geom_utm, ST_Buffer(ST_Transform(jk.geom, 32749), 15)))::numeric, 2) AS overlap_m,
                        ROUND((ST_Length(ST_Intersection(t.geom_utm, ST_Buffer(ST_Transform(jk.geom, 32749), 15))) / t.total_m * 100)::numeric, 1) AS overlap_pct,
                        ST_AsGeoJSON(ST_Transform(jk.geom, 4326)) AS geojson_str
                    FROM target t
                    JOIN jalan_kabupaten_luarkota jk
                      ON jk.geom IS NOT NULL
                     AND jk.geom && ST_Expand(t.geom, 0.00025)
                     AND ST_DWithin(ST_Transform(jk.geom, 32749), t.geom_utm, 15)
                    ORDER BY overlap_m DESC
                    LIMIT 12
                ", [$rtrwId]);

                foreach ($kabRows as $kr) {
                    $matchedKabupaten[] = [
                        'id' => (int) $kr->id,
                        'no_ruas' => $kr->no_ruas !== null ? (string) $kr->no_ruas : null,
                        'nama_ruas' => (string) $kr->nama_ruas,
                        'pangkal' => $kr->pangkal ? (string) $kr->pangkal : null,
                        'ujung' => $kr->ujung ? (string) $kr->ujung : null,
                        'klas_ruas' => $kr->klas_ruas ? (string) $kr->klas_ruas : null,
                        'status_ruas' => $kr->status_ruas ? (string) $kr->status_ruas : null,
                        'nama_kecamatan' => $kr->nama_kecamatan ? (string) $kr->nama_kecamatan : null,
                        'nama_desa' => $kr->nama_desa ? (string) $kr->nama_desa : null,
                        'panjang_m' => round((float) $kr->panjang_m, 2),
                        'overlap_m' => round((float) $kr->overlap_m, 2),
                        'overlap_pct' => min(100.0, round((float) $kr->overlap_pct, 1)),
                        'geojson' => is_string($kr->geojson_str) ? json_decode($kr->geojson_str, true) : null,
                    ];
                }
            }
        } else {
            // SQLite fallback untuk detail matching
            if ($analysis?->matched_poros_id && Schema::hasTable('jalan_porosdesa')) {
                $p = DB::table('jalan_porosdesa')->where('id', $analysis->matched_poros_id)->first();
                if ($p) {
                    $matchedPoros[] = [
                        'id' => (string) $p->id,
                        'kode_ruas' => $p->kode_ruas !== null ? (int) $p->kode_ruas : null,
                        'nama_ruas' => (string) $p->nama_ruas,
                        'hierarki_jalan' => (string) ($p->hierarki_jalan ?? 'poros_desa'),
                        'sumber_data' => $p->sumber_data ?? null,
                        'tahun_survey' => $p->tahun_survey ? (int) $p->tahun_survey : null,
                        'perkerasan' => $p->perkerasan ?? null,
                        'kondisi' => $p->kondisi ?? null,
                        'panjang_m' => round((float) ($p->panjang ?? 0), 2),
                        'lebar_m' => $p->lebar !== null ? (float) $p->lebar : null,
                        'nama_desa' => $p->nama_desa ?? $p->desa ?? null,
                        'nama_kecamatan' => $p->nama_kecamatan ?? $p->kecamatan ?? null,
                        'is_rtrw_origin' => (int) ($p->rtrw_source_id ?? 0) === $rtrwId,
                        'overlap_m' => round((float) ($analysis->poros_overlap_m ?? 0), 2),
                        'overlap_pct' => round((float) ($analysis->poros_overlap_pct ?? 0), 1),
                        'geojson' => is_string($p->geom) ? json_decode($p->geom, true) : null,
                    ];
                }
            }
            if ($analysis?->matched_kab_id && Schema::hasTable('jalan_kabupaten_luarkota')) {
                $k = DB::table('jalan_kabupaten_luarkota')->where('id', $analysis->matched_kab_id)->first();
                if ($k) {
                    $arr = (array) $k;
                    $matchedKabupaten[] = [
                        'id' => (int) $arr['id'],
                        'no_ruas' => isset($arr['no_ruas']) ? (string) $arr['no_ruas'] : null,
                        'nama_ruas' => (string) ($arr['nama_ruas'] ?? $arr['Nama_Ruas'] ?? 'Jalan Kabupaten'),
                        'pangkal' => $arr['pangkal'] ?? null,
                        'ujung' => $arr['ujung'] ?? null,
                        'klas_ruas' => $arr['klas_ruas'] ?? null,
                        'status_ruas' => $arr['status_ruas'] ?? 'Jalan Kabupaten',
                        'nama_kecamatan' => $arr['nama_kecamatan'] ?? null,
                        'nama_desa' => $arr['nama_desa'] ?? null,
                        'panjang_m' => round((float) ($arr['panjang'] ?? 0), 2),
                        'overlap_m' => round((float) ($analysis->kab_overlap_m ?? 0), 2),
                        'overlap_pct' => round((float) ($analysis->kab_overlap_pct ?? 0), 1),
                        'geojson' => is_string($arr['geom'] ?? null) ? json_decode($arr['geom'], true) : null,
                    ];
                }
            }
        }

        return [
            'segment' => [
                'id' => (int) $row->id,
                'display_name' => (string) ($analysis?->nama_segmen ?: ($row->nama_jln ?: "Segmen RTRW #{$row->id}")),
                'nama_ruas' => $row->nama_jln ? (string) $row->nama_jln : null,
                'namobj' => $row->namobj ? (string) $row->namobj : null,
                'orde01' => $row->orde01 ? (string) $row->orde01 : null,
                'orde02' => $row->orde02 ? (string) $row->orde02 : null,
                'orde03' => $row->orde03 ? (string) $row->orde03 : null,
                'orde04' => $row->orde04 ? (string) $row->orde04 : null,
                'stsjrn' => $row->stsjrn ? (string) $row->stsjrn : null,
                'sbdata' => $row->sbdata ? (string) $row->sbdata : null,
                'id_kecamatan' => $analysis?->id_kecamatan !== null ? (int) $analysis->id_kecamatan : null,
                'nama_kecamatan' => $analysis?->nama_kecamatan ? (string) $analysis->nama_kecamatan : null,
                'id_desa' => $analysis?->id_desa !== null ? (int) $analysis->id_desa : null,
                'nama_desa' => $analysis?->nama_desa ? (string) $analysis->nama_desa : null,
                'panjang_m' => $panjangM,
                'poros_overlap_m' => round((float) ($analysis?->poros_overlap_m ?? 0), 2),
                'poros_overlap_pct' => round((float) ($analysis?->poros_overlap_pct ?? 0), 1),
                'kab_overlap_m' => round((float) ($analysis?->kab_overlap_m ?? 0), 2),
                'kab_overlap_pct' => round((float) ($analysis?->kab_overlap_pct ?? 0), 1),
                'combined_overlap_m' => round((float) ($analysis?->combined_overlap_m ?? 0), 2),
                'combined_overlap_pct' => round((float) ($analysis?->combined_overlap_pct ?? 0), 1),
                'uncovered_m' => round((float) ($analysis?->uncovered_m ?? $panjangM), 2),
                'status_analisa' => (string) ($analysis?->status_analisa ?? RtrwJaringanAnalisaSpasial::STATUS_BELUM_TERDATA),
                'already_adopted' => (bool) ($analysis?->already_adopted ?? false),
                'recommended_hierarki' => $this->mapOrdeToHierarki((string) ($row->orde02 ?? '')),
                'bbox' => $bbox,
                'geojson' => $geomObj,
            ],
            'matched_poros' => $matchedPoros,
            'matched_kabupaten' => $matchedKabupaten,
        ];
    }

    /**
     * Daftar paginasi data jalan_kabupaten_luarkota beserta ringkasan dan jumlah keterkaitan segmen RTRW.
     */
    public function listJalanKabupaten(array $filters, ?User $user = null): array
    {
        if (!Schema::hasTable('jalan_kabupaten_luarkota')) {
            return [
                'summary' => ['total_ruas' => 0, 'total_panjang_m' => 0],
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0],
            ];
        }

        $wilayah = $this->resolveEffectiveWilayah($filters, $user);
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $search = trim((string) ($filters['search'] ?? ''));
        $klasFilter = trim((string) ($filters['klas_ruas'] ?? ''));
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgsql ? 'ilike' : 'like';
        $kabCols = $this->resolveKabupatenColumns('jk');

        $query = DB::table('jalan_kabupaten_luarkota as jk');

        if ($idDesa && Schema::hasTable('bataswilayah_desa') && $isPgsql) {
            $query->whereExists(function ($q) use ($idDesa) {
                $q->select(DB::raw(1))
                    ->from('bataswilayah_desa as bd')
                    ->where('bd.id', $idDesa)
                    ->whereRaw('jk.geom IS NOT NULL AND bd.geom IS NOT NULL AND jk.geom && bd.geom AND ST_Intersects(jk.geom, bd.geom)');
            });
        } elseif ($idKecamatan && Schema::hasTable('bataswilayah_kecamatan') && $isPgsql) {
            $query->whereExists(function ($q) use ($idKecamatan) {
                $q->select(DB::raw(1))
                    ->from('bataswilayah_kecamatan as bk')
                    ->where('bk.id', $idKecamatan)
                    ->whereRaw('jk.geom IS NOT NULL AND bk.geom IS NOT NULL AND jk.geom && bk.geom AND ST_Intersects(jk.geom, bk.geom)');
            });
        }

        if ($klasFilter !== '') {
            $query->whereRaw("{$kabCols['klas_ruas']} = ?", [$klasFilter]);
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term, $likeOp, $kabCols) {
                $q->whereRaw("{$kabCols['nama_ruas']} {$likeOp} ?", [$term])
                    ->orWhereRaw("CAST({$kabCols['no_ruas']} AS TEXT) {$likeOp} ?", [$term])
                    ->orWhereRaw("{$kabCols['pangkal']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$kabCols['ujung']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$kabCols['nama_kecamatan']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$kabCols['nama_desa']} {$likeOp} ?", [$term]);
            });
        }

        $statsRow = (clone $query)
            ->selectRaw("COUNT(*) as total_ruas, COALESCE(SUM({$kabCols['panjang']}), 0) as total_panjang_m")
            ->first();

        $selects = [
            'jk.id',
            DB::raw("{$kabCols['no_ruas']} as no_ruas"),
            DB::raw("{$kabCols['nama_ruas']} as nama_ruas"),
            DB::raw("{$kabCols['pangkal']} as pangkal"),
            DB::raw("{$kabCols['ujung']} as ujung"),
            DB::raw("{$kabCols['klas_ruas']} as klas_ruas"),
            DB::raw("{$kabCols['status_ruas']} as status_ruas"),
            DB::raw("{$kabCols['nama_kecamatan']} as nama_kecamatan"),
            DB::raw("{$kabCols['nama_desa']} as nama_desa"),
            DB::raw("{$kabCols['panjang']} as panjang_m"),
        ];

        if ($isPgsql) {
            $selects[] = DB::raw('ST_AsGeoJSON(ST_Transform(jk.geom, 4326)) as geojson_str');
            $selects[] = DB::raw('ST_XMin(ST_Transform(jk.geom, 4326)) as min_lng');
            $selects[] = DB::raw('ST_YMin(ST_Transform(jk.geom, 4326)) as min_lat');
            $selects[] = DB::raw('ST_XMax(ST_Transform(jk.geom, 4326)) as max_lng');
            $selects[] = DB::raw('ST_YMax(ST_Transform(jk.geom, 4326)) as max_lat');
        } else {
            $selects[] = 'jk.geom as geojson_str';
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));
        $paginated = $query->select($selects)->orderBy('jk.no_ruas')->orderBy('jk.id')->paginate($perPage);

        $kabIds = collect($paginated->items())->pluck('id')->map(fn ($v) => (int) $v)->all();
        $rtrwCounts = Schema::hasTable('rtrw_jaringan_analisa_spasial') && !empty($kabIds)
            ? DB::table('rtrw_jaringan_analisa_spasial')
                ->whereIn('matched_kab_id', $kabIds)
                ->selectRaw('matched_kab_id, COUNT(*) as total')
                ->groupBy('matched_kab_id')
                ->pluck('total', 'matched_kab_id')
                ->all()
            : [];

        $rows = collect($paginated->items())->map(function ($row) use ($isPgsql, $rtrwCounts) {
            $geomObj = is_string($row->geojson_str) ? json_decode($row->geojson_str, true) : null;
            $bbox = ($isPgsql && isset($row->min_lng) && $row->min_lng !== null)
                ? [
                    round((float) $row->min_lng, 6),
                    round((float) $row->min_lat, 6),
                    round((float) $row->max_lng, 6),
                    round((float) $row->max_lat, 6),
                ]
                : $this->computeCoordsBbox($this->decodeLineCoordinates($row->geojson_str));

            return [
                'id' => (int) $row->id,
                'no_ruas' => $row->no_ruas !== null ? (string) $row->no_ruas : null,
                'nama_ruas' => (string) ($row->nama_ruas ?: 'Jalan Kabupaten'),
                'pangkal' => $row->pangkal ? (string) $row->pangkal : null,
                'ujung' => $row->ujung ? (string) $row->ujung : null,
                'klas_ruas' => $row->klas_ruas ? (string) $row->klas_ruas : null,
                'status_ruas' => $row->status_ruas ? (string) $row->status_ruas : 'Jalan Kabupaten',
                'nama_kecamatan' => $row->nama_kecamatan ? (string) $row->nama_kecamatan : null,
                'nama_desa' => $row->nama_desa ? (string) $row->nama_desa : null,
                'panjang_m' => round((float) ($row->panjang_m ?? 0), 2),
                'rtrw_match_count' => (int) ($rtrwCounts[(int) $row->id] ?? 0),
                'bbox' => $bbox,
                'geometry' => $geomObj,
            ];
        })->values()->all();

        return [
            'summary' => [
                'total_ruas' => (int) ($statsRow->total_ruas ?? 0),
                'total_panjang_m' => round((float) ($statsRow->total_panjang_m ?? 0), 2),
            ],
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Detail satu ruas jalan_kabupaten_luarkota beserta segmen RTRW yang beririsan dengannya.
     */
    public function getJalanKabupatenDetail(int $kabId): array
    {
        if (!Schema::hasTable('jalan_kabupaten_luarkota')) {
            throw new InvalidArgumentException('Tabel jalan_kabupaten_luarkota tidak tersedia.');
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $kabCols = $this->resolveKabupatenColumns('jk');

        $selects = [
            'jk.id',
            DB::raw("{$kabCols['no_ruas']} as no_ruas"),
            DB::raw("{$kabCols['nama_ruas']} as nama_ruas"),
            DB::raw("{$kabCols['pangkal']} as pangkal"),
            DB::raw("{$kabCols['ujung']} as ujung"),
            DB::raw("{$kabCols['klas_ruas']} as klas_ruas"),
            DB::raw("{$kabCols['status_ruas']} as status_ruas"),
            DB::raw("{$kabCols['nama_kecamatan']} as nama_kecamatan"),
            DB::raw("{$kabCols['nama_desa']} as nama_desa"),
            DB::raw("{$kabCols['panjang']} as panjang_m"),
        ];

        if ($isPgsql) {
            $selects[] = DB::raw('ST_AsGeoJSON(ST_Transform(jk.geom, 4326)) as geojson_str');
            $selects[] = DB::raw('ST_XMin(ST_Transform(jk.geom, 4326)) as min_lng');
            $selects[] = DB::raw('ST_YMin(ST_Transform(jk.geom, 4326)) as min_lat');
            $selects[] = DB::raw('ST_XMax(ST_Transform(jk.geom, 4326)) as max_lng');
            $selects[] = DB::raw('ST_YMax(ST_Transform(jk.geom, 4326)) as max_lat');
        } else {
            $selects[] = 'jk.geom as geojson_str';
        }

        $row = DB::table('jalan_kabupaten_luarkota as jk')->where('jk.id', $kabId)->select($selects)->first();
        if (!$row) {
            throw new InvalidArgumentException("Ruas Jalan Kabupaten #{$kabId} tidak ditemukan.");
        }

        $geomObj = is_string($row->geojson_str) ? json_decode($row->geojson_str, true) : null;
        $bbox = ($isPgsql && isset($row->min_lng) && $row->min_lng !== null)
            ? [
                round((float) $row->min_lng, 6),
                round((float) $row->min_lat, 6),
                round((float) $row->max_lng, 6),
                round((float) $row->max_lat, 6),
            ]
            : $this->computeCoordsBbox($this->decodeLineCoordinates($row->geojson_str));

        $rtrwCount = Schema::hasTable('rtrw_jaringan_analisa_spasial')
            ? DB::table('rtrw_jaringan_analisa_spasial')->where('matched_kab_id', $kabId)->count()
            : 0;

        return [
            'id' => (int) $row->id,
            'no_ruas' => $row->no_ruas !== null ? (string) $row->no_ruas : null,
            'nama_ruas' => (string) ($row->nama_ruas ?: 'Jalan Kabupaten'),
            'pangkal' => $row->pangkal ? (string) $row->pangkal : null,
            'ujung' => $row->ujung ? (string) $row->ujung : null,
            'klas_ruas' => $row->klas_ruas ? (string) $row->klas_ruas : null,
            'status_ruas' => $row->status_ruas ? (string) $row->status_ruas : 'Jalan Kabupaten',
            'nama_kecamatan' => $row->nama_kecamatan ? (string) $row->nama_kecamatan : null,
            'nama_desa' => $row->nama_desa ? (string) $row->nama_desa : null,
            'panjang_m' => round((float) ($row->panjang_m ?? 0), 2),
            'rtrw_match_count' => $rtrwCount,
            'bbox' => $bbox,
            'geometry' => $geomObj,
        ];
    }

    /**
     * Hitung ringkasan desa terdata vs belum terdata di jalan_porosdesa.
     *
     * @return array{total_desa: int, desa_terdata: int, desa_belum_terdata: int}
     */
    protected function getDesaCoverageCounts(?int $idKecamatan = null, ?int $idDesa = null): array
    {
        if (!Schema::hasTable('bataswilayah_desa') || !Schema::hasTable('jalan_porosdesa')) {
            return ['total_desa' => 0, 'desa_terdata' => 0, 'desa_belum_terdata' => 0];
        }

        $q = DB::table('bataswilayah_desa as d');
        if ($idKecamatan) {
            $q->where('d.id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $q->where('d.id', $idDesa);
        }

        $total = (clone $q)->count();
        $terdata = (clone $q)->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('jalan_porosdesa as jp')
                ->whereColumn('jp.id_desa', 'd.id');
        })->count();

        return [
            'total_desa' => $total,
            'desa_terdata' => $terdata,
            'desa_belum_terdata' => max(0, $total - $terdata),
        ];
    }

    /**
     * Rekapitulasi cakupan desa yang sudah & belum terdata di jalan_porosdesa beserta statistik segmen RTRW & Jalan Kabupaten.
     */
    public function getCakupanDesa(array $filters, ?User $user = null): array
    {
        if (!Schema::hasTable('bataswilayah_desa') || !Schema::hasTable('jalan_porosdesa')) {
            return [
                'summary' => [
                    'total_desa' => 0,
                    'desa_terdata' => 0,
                    'desa_belum_terdata' => 0,
                ],
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                ],
            ];
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $hasRtrw = Schema::hasTable('rtrw_jaringan_transportasi_ln');
        $hasAnalisa = Schema::hasTable('rtrw_jaringan_analisa_spasial') && DB::table('rtrw_jaringan_analisa_spasial')->exists();
        $likeOp = $isPgsql ? 'ilike' : 'like';
        $cols = $hasRtrw ? $this->resolveRtrwColumns('r') : null;

        $wilayah = $this->resolveEffectiveWilayah($filters, $user);
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $statusCakupan = (string) ($filters['status_cakupan'] ?? 'belum_terdata');
        $search = trim((string) ($filters['search'] ?? ''));

        $baseScope = DB::table('bataswilayah_desa as d')
            ->leftJoin('bataswilayah_kecamatan as k', 'k.id', '=', 'd.id_kecamatan');

        if ($idKecamatan) {
            $baseScope->where('d.id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $baseScope->where('d.id', $idDesa);
        }
        if ($search !== '') {
            $term = '%' . $search . '%';
            $baseScope->where(function ($q) use ($term, $likeOp) {
                $q->where('d.nama_desa', $likeOp, $term)
                    ->orWhere('k.nama_kecamatan', $likeOp, $term);
            });
        }

        $totalDesa = (clone $baseScope)->count();
        $desaTerdata = (clone $baseScope)->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('jalan_porosdesa as jp')
                ->whereColumn('jp.id_desa', 'd.id');
        })->count();
        $desaBelumTerdata = max(0, $totalDesa - $desaTerdata);

        $listQuery = (clone $baseScope)->select([
            'd.id',
            'd.nama_desa',
            'd.id_kecamatan',
            'k.nama_kecamatan',
            DB::raw('(SELECT COUNT(*) FROM jalan_porosdesa jp WHERE jp.id_desa = d.id) as poros_count'),
            DB::raw('COALESCE((SELECT SUM(jp.panjang) FROM jalan_porosdesa jp WHERE jp.id_desa = d.id), 0) as poros_panjang_m'),
        ]);

        if ($hasAnalisa) {
            $listQuery->addSelect([
                DB::raw("(SELECT COUNT(*) FROM rtrw_jaringan_analisa_spasial a WHERE a.id_desa = d.id AND a.orde02 = 'Jalan Desa') as rtrw_jalan_desa_count"),
                DB::raw('(SELECT COUNT(*) FROM rtrw_jaringan_analisa_spasial a WHERE a.id_desa = d.id) as rtrw_total_count'),
                DB::raw("(SELECT COUNT(*) FROM rtrw_jaringan_analisa_spasial a WHERE a.id_desa = d.id AND a.status_analisa = 'belum_terdata') as rtrw_belum_terdata_count"),
            ]);
        } elseif ($hasRtrw && $cols && $isPgsql) {
            $listQuery->addSelect([
                DB::raw("(SELECT COUNT(*) FROM rtrw_jaringan_transportasi_ln r WHERE r.geom IS NOT NULL AND d.geom IS NOT NULL AND COALESCE({$cols['orde01']}, '') <> '" . self::ORDE_KERETA_API . "' AND {$cols['orde02']} = 'Jalan Desa' AND r.geom && d.geom AND ST_Intersects(r.geom, d.geom)) as rtrw_jalan_desa_count"),
                DB::raw("(SELECT COUNT(*) FROM rtrw_jaringan_transportasi_ln r WHERE r.geom IS NOT NULL AND d.geom IS NOT NULL AND COALESCE({$cols['orde01']}, '') <> '" . self::ORDE_KERETA_API . "' AND r.geom && d.geom AND ST_Intersects(r.geom, d.geom)) as rtrw_total_count"),
                DB::raw('0 as rtrw_belum_terdata_count'),
            ]);
        } elseif ($hasRtrw && $cols) {
            $hasIdDesaCol = Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa');
            if ($hasIdDesaCol) {
                $listQuery->addSelect([
                    DB::raw("(SELECT COUNT(*) FROM rtrw_jaringan_transportasi_ln r WHERE r.id_desa = d.id AND COALESCE({$cols['orde01']}, '') <> '" . self::ORDE_KERETA_API . "' AND {$cols['orde02']} = 'Jalan Desa') as rtrw_jalan_desa_count"),
                    DB::raw("(SELECT COUNT(*) FROM rtrw_jaringan_transportasi_ln r WHERE r.id_desa = d.id AND COALESCE({$cols['orde01']}, '') <> '" . self::ORDE_KERETA_API . "') as rtrw_total_count"),
                    DB::raw('0 as rtrw_belum_terdata_count'),
                ]);
            } else {
                $listQuery->addSelect([
                    DB::raw('0 as rtrw_jalan_desa_count'),
                    DB::raw('0 as rtrw_total_count'),
                    DB::raw('0 as rtrw_belum_terdata_count'),
                ]);
            }
        } else {
            $listQuery->addSelect([
                DB::raw('0 as rtrw_jalan_desa_count'),
                DB::raw('0 as rtrw_total_count'),
                DB::raw('0 as rtrw_belum_terdata_count'),
            ]);
        }

        if ($statusCakupan === 'belum_terdata') {
            $listQuery->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jalan_porosdesa as jp')
                    ->whereColumn('jp.id_desa', 'd.id');
            });
        } elseif ($statusCakupan === 'sudah_terdata') {
            $listQuery->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jalan_porosdesa as jp')
                    ->whereColumn('jp.id_desa', 'd.id');
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));
        $paginated = $listQuery
            ->orderByRaw('(SELECT COUNT(*) FROM jalan_porosdesa jp WHERE jp.id_desa = d.id) ASC')
            ->orderBy('k.nama_kecamatan')
            ->orderBy('d.nama_desa')
            ->paginate($perPage);

        $rows = collect($paginated->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'nama_desa' => (string) $row->nama_desa,
                'id_kecamatan' => $row->id_kecamatan !== null ? (int) $row->id_kecamatan : null,
                'nama_kecamatan' => $row->nama_kecamatan ? (string) $row->nama_kecamatan : null,
                'poros_count' => (int) $row->poros_count,
                'poros_panjang_m' => round((float) $row->poros_panjang_m, 2),
                'rtrw_jalan_desa_count' => (int) $row->rtrw_jalan_desa_count,
                'rtrw_total_count' => (int) $row->rtrw_total_count,
                'rtrw_belum_terdata_count' => (int) ($row->rtrw_belum_terdata_count ?? 0),
                'status_cakupan' => ((int) $row->poros_count) > 0 ? 'sudah_terdata' : 'belum_terdata',
            ];
        })->values();

        return [
            'summary' => [
                'total_desa' => $totalDesa,
                'desa_terdata' => $desaTerdata,
                'desa_belum_terdata' => $desaBelumTerdata,
            ],
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Daftar segmen jaringan jalan RTRW (Orde 1-4) beserta status adopsi & deteksi duplikat spasial.
     */
    public function listRtrwSegments(array $filters, ?User $user = null): array
    {
        if (!Schema::hasTable('rtrw_jaringan_transportasi_ln')) {
            return [
                'data' => [],
                'geojson' => ['type' => 'FeatureCollection', 'features' => []],
                'orde_options' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 30, 'total' => 0],
            ];
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgsql ? 'ilike' : 'like';
        $cols = $this->resolveRtrwColumns('r');

        $wilayah = $this->resolveEffectiveWilayah($filters, $user);
        $idKecamatan = $wilayah['id_kecamatan'];
        $idDesa = $wilayah['id_desa'];
        $orde02 = trim((string) ($filters['orde02'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $onlyUnadopted = filter_var($filters['only_unadopted'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlyMissingDesa = filter_var($filters['only_missing_desa'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ordeOptions = DB::table('rtrw_jaringan_transportasi_ln as r')
            ->whereRaw("COALESCE({$cols['orde01']}, '') <> ?", [self::ORDE_KERETA_API])
            ->selectRaw("{$cols['orde02']} as orde02, COUNT(*) as total")
            ->groupByRaw($cols['orde02'])
            ->orderByDesc('total')
            ->get()
            ->map(fn ($o) => [
                'value' => (string) $o->orde02,
                'label' => (string) $o->orde02,
                'total' => (int) $o->total,
            ])
            ->values()
            ->all();

        $query = DB::table('rtrw_jaringan_transportasi_ln as r')
            ->whereRaw("COALESCE({$cols['orde01']}, '') <> ?", [self::ORDE_KERETA_API]);

        if ($orde02 !== '') {
            $query->whereRaw("{$cols['orde02']} = ?", [$orde02]);
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term, $likeOp, $cols) {
                $q->whereRaw("{$cols['nama_jln']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$cols['namobj']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$cols['orde02']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$cols['orde03']} {$likeOp} ?", [$term])
                    ->orWhereRaw("{$cols['orde04']} {$likeOp} ?", [$term]);
            });
        }

        if ($onlyUnadopted && Schema::hasColumn('jalan_porosdesa', 'rtrw_source_id')) {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jalan_porosdesa as jp')
                    ->whereColumn('jp.rtrw_source_id', 'r.id');
            });
        }

        if ($idDesa && Schema::hasTable('bataswilayah_desa')) {
            if ($isPgsql) {
                $query->whereExists(function ($q) use ($idDesa) {
                    $q->select(DB::raw(1))
                        ->from('bataswilayah_desa as bd')
                        ->where('bd.id', $idDesa)
                        ->whereRaw('r.geom IS NOT NULL AND bd.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom)');
                });
            } elseif (Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa')) {
                $query->where('r.id_desa', $idDesa);
            }
        } elseif ($idKecamatan && Schema::hasTable('bataswilayah_kecamatan')) {
            if ($isPgsql) {
                $query->whereExists(function ($q) use ($idKecamatan) {
                    $q->select(DB::raw(1))
                        ->from('bataswilayah_kecamatan as bk')
                        ->where('bk.id', $idKecamatan)
                        ->whereRaw('r.geom IS NOT NULL AND bk.geom IS NOT NULL AND r.geom && bk.geom AND ST_Intersects(r.geom, bk.geom)');
                });
            } elseif (Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_kecamatan')) {
                $query->where('r.id_kecamatan', $idKecamatan);
            }
        } elseif ($onlyMissingDesa && Schema::hasTable('bataswilayah_desa') && Schema::hasTable('jalan_porosdesa')) {
            if ($isPgsql) {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('bataswilayah_desa as bd')
                        ->whereRaw('NOT EXISTS (SELECT 1 FROM jalan_porosdesa jp WHERE jp.id_desa = bd.id)')
                        ->whereRaw('r.geom IS NOT NULL AND bd.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom)');
                });
            } elseif (Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa')) {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('bataswilayah_desa as bd')
                        ->whereColumn('bd.id', 'r.id_desa')
                        ->whereRaw('NOT EXISTS (SELECT 1 FROM jalan_porosdesa jp WHERE jp.id_desa = bd.id)');
                });
            }
        }

        $query->select([
            'r.id',
            DB::raw("{$cols['nama_jln']} as nama_jln"),
            DB::raw("{$cols['namobj']} as namobj"),
            DB::raw("{$cols['orde01']} as orde01"),
            DB::raw("{$cols['orde02']} as orde02"),
            DB::raw("{$cols['orde03']} as orde03"),
            DB::raw("{$cols['orde04']} as orde04"),
            DB::raw("{$cols['stsjrn']} as stsjrn"),
            DB::raw("{$cols['sbdata']} as sbdata"),
        ]);

        if (!$isPgsql) {
            $query->addSelect([
                'r.geom as geojson_str',
                Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'panjang_m')
                    ? DB::raw('COALESCE(r.panjang_m, 150) as panjang_m')
                    : DB::raw('150 as panjang_m'),
                Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa') ? 'r.id_desa as resolved_id_desa' : DB::raw('NULL as resolved_id_desa'),
                Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_kecamatan') ? 'r.id_kecamatan as resolved_id_kecamatan' : DB::raw('NULL as resolved_id_kecamatan'),
                DB::raw('EXISTS (SELECT 1 FROM jalan_porosdesa jp WHERE jp.rtrw_source_id = r.id) as already_in_master'),
                DB::raw('0 as spatial_duplicate'),
            ]);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 30)));
        $paginated = $query
            ->orderByRaw('CASE WHEN ' . $cols['nama_jln'] . ' IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('r.id')
            ->paginate($perPage);

        $pageRows = collect($paginated->items());
        $spatialMeta = [];
        if ($isPgsql && $pageRows->isNotEmpty()) {
            $pageIds = $pageRows->pluck('id')->map(fn ($v) => (int) $v)->all();
            $placeholders = implode(',', array_fill(0, count($pageIds), '?'));
            $metaRows = DB::select("
                SELECT
                    r.id,
                    ST_AsGeoJSON(ST_Transform(r.geom, 4326)) as geojson_str,
                    ROUND(COALESCE(ST_Length(ST_Transform(r.geom, 32749)), 0)::numeric, 2) as panjang_m,
                    (SELECT bd.id FROM bataswilayah_desa bd WHERE bd.geom IS NOT NULL AND r.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom) ORDER BY ST_Length(ST_Intersection(ST_Transform(r.geom, 32749), ST_Transform(bd.geom, 32749))) DESC LIMIT 1) as resolved_id_desa,
                    (SELECT bd.nama_desa FROM bataswilayah_desa bd WHERE bd.geom IS NOT NULL AND r.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom) ORDER BY ST_Length(ST_Intersection(ST_Transform(r.geom, 32749), ST_Transform(bd.geom, 32749))) DESC LIMIT 1) as resolved_nama_desa,
                    (SELECT bd.id_kecamatan FROM bataswilayah_desa bd WHERE bd.geom IS NOT NULL AND r.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom) ORDER BY ST_Length(ST_Intersection(ST_Transform(r.geom, 32749), ST_Transform(bd.geom, 32749))) DESC LIMIT 1) as resolved_id_kecamatan,
                    EXISTS (SELECT 1 FROM jalan_porosdesa jp WHERE jp.rtrw_source_id = r.id) as already_in_master,
                    EXISTS (
                        SELECT 1 FROM jalan_porosdesa jp
                        WHERE jp.geom IS NOT NULL AND r.geom IS NOT NULL
                          AND jp.geom && ST_Expand(r.geom, 0.00025)
                          AND (
                            ST_Length(
                              ST_Intersection(
                                ST_Buffer(ST_Transform(jp.geom, 32749), 15),
                                ST_Transform(r.geom, 32749)
                              )
                            ) / GREATEST(ST_Length(ST_Transform(r.geom, 32749)), 1)
                          ) >= 0.60
                    ) as spatial_duplicate
                FROM rtrw_jaringan_transportasi_ln r
                WHERE r.id IN ({$placeholders})
            ", $pageIds);

            foreach ($metaRows as $mr) {
                $spatialMeta[(int) $mr->id] = $mr;
            }
        }

        $kecNames = BatasWilayahKecamatan::pluck('nama_kecamatan', 'id');
        $desaNames = BatasWilayahDesa::pluck('nama_desa', 'id');

        $items = [];
        $features = [];

        foreach ($pageRows as $row) {
            $metaRow = $spatialMeta[(int) $row->id] ?? null;
            $geojsonStr = $metaRow ? $metaRow->geojson_str : ($row->geojson_str ?? null);
            $panjangM = $metaRow ? $metaRow->panjang_m : ($row->panjang_m ?? 0);
            $rawDesaId = $metaRow ? $metaRow->resolved_id_desa : ($row->resolved_id_desa ?? null);
            $rawKecId = $metaRow ? $metaRow->resolved_id_kecamatan : ($row->resolved_id_kecamatan ?? null);
            $rawDesaName = $metaRow ? $metaRow->resolved_nama_desa : null;
            $alreadyInMaster = $metaRow ? (bool) $metaRow->already_in_master : (bool) ($row->already_in_master ?? false);
            $spatialDup = $metaRow ? (bool) $metaRow->spatial_duplicate : (bool) ($row->spatial_duplicate ?? false);

            $geomObj = is_string($geojsonStr) ? json_decode($geojsonStr, true) : null;
            $resolvedDesaId = $idDesa ?: ($rawDesaId ? (int) $rawDesaId : null);
            $resolvedKecId = $rawKecId ? (int) $rawKecId : ($idKecamatan ?: null);
            $resolvedDesaName = $rawDesaName ?: ($resolvedDesaId ? ($desaNames[$resolvedDesaId] ?? null) : null);
            $resolvedKecName = $resolvedKecId ? ($kecNames[$resolvedKecId] ?? null) : null;

            $displayName = trim((string) ($row->nama_jln ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row->namobj ?? 'Jalan Desa')) . ($resolvedDesaName ? " {$resolvedDesaName}" : '') . " #RTRW-{$row->id}";
            }

            $item = [
                'id' => (int) $row->id,
                'nama_jln' => $row->nama_jln ? (string) $row->nama_jln : null,
                'display_name' => $displayName,
                'namobj' => (string) $row->namobj,
                'orde01' => (string) $row->orde01,
                'orde02' => (string) $row->orde02,
                'orde03' => (string) $row->orde03,
                'orde04' => (string) $row->orde04,
                'stsjrn' => $row->stsjrn ? (string) $row->stsjrn : null,
                'sbdata' => $row->sbdata ? (string) $row->sbdata : null,
                'panjang_m' => round((float) $panjangM, 2),
                'id_desa' => $resolvedDesaId,
                'nama_desa' => $resolvedDesaName,
                'id_kecamatan' => $resolvedKecId,
                'nama_kecamatan' => $resolvedKecName,
                'already_in_master' => $alreadyInMaster,
                'spatial_duplicate' => $spatialDup,
                'recommended_hierarki' => $this->mapOrdeToHierarki((string) $row->orde02),
                'geojson' => $geomObj,
            ];
            $items[] = $item;

            if (is_array($geomObj)) {
                $features[] = [
                    'type' => 'Feature',
                    'id' => 'rtrw-' . $row->id,
                    'geometry' => $geomObj,
                    'properties' => [
                        'id' => (int) $row->id,
                        'display_name' => $displayName,
                        'namobj' => (string) $row->namobj,
                        'orde02' => (string) $row->orde02,
                        'orde04' => (string) $row->orde04,
                        'panjang_m' => round((float) $panjangM, 2),
                        'id_desa' => $resolvedDesaId,
                        'nama_desa' => $resolvedDesaName,
                        'already_in_master' => $alreadyInMaster,
                        'spatial_duplicate' => $spatialDup,
                    ],
                ];
            }
        }

        return [
            'data' => $items,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
            'orde_options' => $ordeOptions,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Adopsi satu atau banyak segmen RTRW (per ID segmen atau batch per desa) ke Master jalan_porosdesa.
     */
    public function adoptRtrwSegments(User $user, array $payload): array
    {
        if (!Schema::hasTable('rtrw_jaringan_transportasi_ln') || !Schema::hasTable('jalan_porosdesa')) {
            throw new InvalidArgumentException('Tabel RTRW atau jalan_porosdesa tidak tersedia.');
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $cols = $this->resolveRtrwColumns('r');

        $rtrwIds = collect($payload['rtrw_ids'] ?? [])->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->values()->all();
        $batchDesaId = !empty($payload['id_desa']) ? (int) $payload['id_desa'] : null;
        $orde02Filter = !empty($payload['orde02']) ? trim((string) $payload['orde02']) : 'Jalan Desa';
        $skipSpatialDuplicate = (bool) ($payload['skip_spatial_duplicate'] ?? true);
        $tahunSurvey = !empty($payload['tahun_survey']) ? (int) $payload['tahun_survey'] : (int) date('Y');
        $overrideNama = !empty($payload['nama_ruas']) ? trim((string) $payload['nama_ruas']) : null;
        $overrideHierarki = !empty($payload['hierarki_jalan']) ? trim((string) $payload['hierarki_jalan']) : null;

        if (empty($rtrwIds) && !$batchDesaId) {
            throw new InvalidArgumentException('Pilih minimal satu segmen RTRW atau tentukan Desa target untuk adopsi massal.');
        }

        $query = DB::table('rtrw_jaringan_transportasi_ln as r')
            ->whereRaw("COALESCE({$cols['orde01']}, '') <> ?", [self::ORDE_KERETA_API])
            ->select([
                'r.id',
                DB::raw("{$cols['nama_jln']} as nama_jln"),
                DB::raw("{$cols['namobj']} as namobj"),
                DB::raw("{$cols['orde02']} as orde02"),
                DB::raw("{$cols['orde04']} as orde04"),
                DB::raw("{$cols['sbdata']} as sbdata"),
            ]);

        if ($isPgsql) {
            $query->addSelect([
                DB::raw('ST_AsText(ST_Multi(ST_Force2D(ST_Transform(r.geom, 4326)))) as geom_wkt'),
                DB::raw('ROUND(COALESCE(ST_Length(ST_Transform(r.geom, 32749)), 0)::numeric, 2) as panjang_m'),
                DB::raw("(SELECT bd.id FROM bataswilayah_desa bd WHERE bd.geom IS NOT NULL AND r.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom) ORDER BY ST_Length(ST_Intersection(ST_Transform(r.geom, 32749), ST_Transform(bd.geom, 32749))) DESC LIMIT 1) as resolved_id_desa"),
                DB::raw("(SELECT bd.id_kecamatan FROM bataswilayah_desa bd WHERE bd.geom IS NOT NULL AND r.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom) ORDER BY ST_Length(ST_Intersection(ST_Transform(r.geom, 32749), ST_Transform(bd.geom, 32749))) DESC LIMIT 1) as resolved_id_kecamatan"),
                DB::raw("EXISTS (
                    SELECT 1 FROM jalan_porosdesa jp
                    WHERE jp.geom IS NOT NULL AND r.geom IS NOT NULL
                      AND jp.geom && ST_Expand(r.geom, 0.00025)
                      AND (
                        ST_Length(
                          ST_Intersection(
                            ST_Buffer(ST_Transform(jp.geom, 32749), 15),
                            ST_Transform(r.geom, 32749)
                          )
                        ) / GREATEST(ST_Length(ST_Transform(r.geom, 32749)), 1)
                      ) >= 0.60
                ) as spatial_duplicate"),
            ]);
        } else {
            $query->addSelect([
                'r.geom as geom_wkt',
                Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'panjang_m')
                    ? DB::raw('COALESCE(r.panjang_m, 150) as panjang_m')
                    : DB::raw('150 as panjang_m'),
                Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa') ? 'r.id_desa as resolved_id_desa' : DB::raw('NULL as resolved_id_desa'),
                Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_kecamatan') ? 'r.id_kecamatan as resolved_id_kecamatan' : DB::raw('NULL as resolved_id_kecamatan'),
                DB::raw('0 as spatial_duplicate'),
            ]);
        }

        if (!empty($rtrwIds)) {
            $query->whereIn('r.id', $rtrwIds);
        } elseif ($batchDesaId) {
            if ($orde02Filter !== '') {
                $query->whereRaw("{$cols['orde02']} = ?", [$orde02Filter]);
            }
            if ($isPgsql) {
                $query->whereExists(function ($q) use ($batchDesaId) {
                    $q->select(DB::raw(1))
                        ->from('bataswilayah_desa as bd')
                        ->where('bd.id', $batchDesaId)
                        ->whereRaw('r.geom IS NOT NULL AND bd.geom IS NOT NULL AND r.geom && bd.geom AND ST_Intersects(r.geom, bd.geom)');
                });
            } elseif (Schema::hasColumn('rtrw_jaringan_transportasi_ln', 'id_desa')) {
                $query->where('r.id_desa', $batchDesaId);
            }
        }

        $candidates = $query->orderBy('r.id')->limit(300)->get();
        if ($candidates->isEmpty()) {
            throw new InvalidArgumentException('Tidak ditemukan segmen RTRW yang memenuhi kriteria pilihan.');
        }

        $desaMap = BatasWilayahDesa::all(['id', 'nama_desa', 'id_kecamatan'])->keyBy('id');
        $kecMap = BatasWilayahKecamatan::pluck('nama_kecamatan', 'id');

        $hasNamaDesaCol = Schema::hasColumn('jalan_porosdesa', 'nama_desa');
        $hasNamaKecCol = Schema::hasColumn('jalan_porosdesa', 'nama_kecamatan');

        $result = DB::transaction(function () use (
            $candidates,
            $user,
            $batchDesaId,
            $overrideNama,
            $overrideHierarki,
            $skipSpatialDuplicate,
            $tahunSurvey,
            $desaMap,
            $kecMap,
            $hasNamaDesaCol,
            $hasNamaKecCol,
            $isPgsql
        ) {
            $maxKode = (int) DB::table('jalan_porosdesa')->max('kode_ruas');
            $adoptedIds = [];
            $adoptedRtrwIds = [];
            $skipped = [];

            foreach ($candidates as $row) {
                if (JalanPorosDesa::where('rtrw_source_id', (int) $row->id)->exists()) {
                    $skipped[] = [
                        'rtrw_id' => (int) $row->id,
                        'reason' => 'Sudah pernah diadopsi dari RTRW ke master jalan_porosdesa.',
                    ];
                    continue;
                }

                if ($skipSpatialDuplicate && (bool) $row->spatial_duplicate) {
                    $skipped[] = [
                        'rtrw_id' => (int) $row->id,
                        'reason' => 'Bertampalan >= 60% dengan ruas jalan_porosdesa yang sudah terdata.',
                    ];
                    continue;
                }

                $targetDesaId = $batchDesaId ?: ($row->resolved_id_desa ? (int) $row->resolved_id_desa : null);
                $desaModel = $targetDesaId ? $desaMap->get($targetDesaId) : null;
                $targetKecId = $row->resolved_id_kecamatan ? (int) $row->resolved_id_kecamatan : ($desaModel?->id_kecamatan ? (int) $desaModel->id_kecamatan : null);
                $namaDesa = $desaModel?->nama_desa;
                $namaKec = $targetKecId ? ($kecMap[$targetKecId] ?? null) : null;

                $namaRuas = $overrideNama && count($candidates) === 1
                    ? $overrideNama
                    : trim((string) ($row->nama_jln ?? ''));
                if ($namaRuas === '') {
                    $namaRuas = trim((string) ($row->namobj ?: 'Jalan Desa'))
                        . ($namaDesa ? " {$namaDesa}" : '')
                        . " #RTRW-{$row->id}";
                }

                $maxKode++;
                $newId = (string) Str::uuid();
                $hierarki = $overrideHierarki ?: $this->mapOrdeToHierarki((string) $row->orde02);

                $insertPayload = [
                    'id' => $newId,
                    'kode_ruas' => $maxKode,
                    'nama_ruas' => $namaRuas,
                    'desa' => $namaDesa,
                    'kecamatan' => $namaKec,
                    'panjang' => round((float) ($row->panjang_m ?? 0), 2),
                    'status_awal' => 'rtrw',
                    'status_eksisting' => 'terverifikasi',
                    'sumber_data' => JalanPorosDesa::SUMBER_RTRW_ORDE,
                    'tahun_survey' => $tahunSurvey,
                    'rtrw_source_id' => (int) $row->id,
                    'hierarki_jalan' => $hierarki,
                    'id_desa' => $targetDesaId,
                    'id_kecamatan' => $targetKecId,
                    'validated_by' => $user->uuid,
                    'validated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($hasNamaDesaCol) {
                    $insertPayload['nama_desa'] = $namaDesa;
                }
                if ($hasNamaKecCol) {
                    $insertPayload['nama_kecamatan'] = $namaKec;
                }

                if ($isPgsql && !empty($row->geom_wkt)) {
                    $insertPayload['geom'] = DB::raw('ST_GeomFromText(' . DB::getPdo()->quote((string) $row->geom_wkt) . ', 4326)');
                } else {
                    $insertPayload['geom'] = is_string($row->geom_wkt)
                        ? $row->geom_wkt
                        : SpatialHelper::geomFromGeoJsonRaw(json_encode(['type' => 'LineString', 'coordinates' => [[111.88, -7.15], [111.882, -7.15]]]));
                }

                DB::table('jalan_porosdesa')->insert($insertPayload);
                $adoptedIds[] = $newId;
                $adoptedRtrwIds[] = (int) $row->id;
            }

            if (count($adoptedIds) > 0) {
                DB::afterCommit(function () {
                    MartinTileService::purgeCache('jalan_porosdesa');
                });
            }

            return [
                'adopted_count' => count($adoptedIds),
                'skipped_count' => count($skipped),
                'skipped' => $skipped,
                'created_ids' => $adoptedIds,
                'adopted_rtrw_ids' => $adoptedRtrwIds,
            ];
        });

        // Segarkan cache analisa spasial bagi segmen RTRW yang baru diadopsi beserta tetangganya
        if (!empty($result['adopted_rtrw_ids']) && Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            $this->refreshSpatialAnalysisAroundRtrwIds($result['adopted_rtrw_ids']);
        }

        unset($result['adopted_rtrw_ids']);
        return $result;
    }

    /**
     * Segarkan hasil analisa spasial untuk daftar rtrw_id tertentu serta tetangga dekatnya (pasca adopsi / edit master).
     *
     * @param array<int, int> $rtrwIds
     */
    public function refreshSpatialAnalysisAroundRtrwIds(array $rtrwIds): void
    {
        if (empty($rtrwIds) || !Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('rtrw_jaringan_transportasi_ln')) {
            $placeholders = implode(',', array_fill(0, count($rtrwIds), '?'));
            $neighborIds = DB::select("
                SELECT DISTINCT r.id
                FROM rtrw_jaringan_transportasi_ln r
                JOIN rtrw_jaringan_transportasi_ln src ON src.id IN ({$placeholders})
                WHERE r.geom IS NOT NULL AND src.geom IS NOT NULL
                  AND r.geom && ST_Expand(src.geom, 0.0005)
            ", $rtrwIds);

            $targetIds = collect($neighborIds)->pluck('id')->map(fn ($v) => (int) $v)->merge($rtrwIds)->unique()->values()->all();
            $this->runSpatialAnalysis(['rtrw_ids' => $targetIds]);
            return;
        }

        $this->runSpatialAnalysis(['rtrw_ids' => $rtrwIds]);
    }

    /**
     * Segarkan cache analisa spasial di sekitar satu ruas jalan_porosdesa yang berubah/dihapus.
     */
    public function refreshSpatialAnalysisAroundPoros(JalanPorosDesa $ruas): void
    {
        if (!Schema::hasTable('rtrw_jaringan_analisa_spasial')) {
            return;
        }

        $targetIds = [];
        if ($ruas->rtrw_source_id) {
            $targetIds[] = (int) $ruas->rtrw_source_id;
        }

        $linked = DB::table('rtrw_jaringan_analisa_spasial')
            ->where('matched_poros_id', $ruas->id)
            ->pluck('rtrw_id')
            ->map(fn ($v) => (int) $v)
            ->all();
        $targetIds = array_merge($targetIds, $linked);

        if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('rtrw_jaringan_transportasi_ln')) {
            $nearby = DB::select("
                SELECT r.id
                FROM rtrw_jaringan_transportasi_ln r
                JOIN jalan_porosdesa jp ON jp.id = ?
                WHERE r.geom IS NOT NULL AND jp.geom IS NOT NULL
                  AND r.geom && ST_Expand(jp.geom, 0.00035)
                LIMIT 200
            ", [$ruas->id]);
            foreach ($nearby as $n) {
                $targetIds[] = (int) $n->id;
            }
        }

        $targetIds = array_values(array_unique(array_filter($targetIds)));
        if (!empty($targetIds)) {
            $this->runSpatialAnalysis(['rtrw_ids' => $targetIds]);
        }
    }

    public function mapOrdeToHierarki(string $orde02): string
    {
        $lower = strtolower(trim($orde02));
        if (str_contains($lower, 'kabupaten') || str_contains($lower, 'provinsi') || str_contains($lower, 'nasional')) {
            return JalanPorosDesa::HIERARKI_KABUPATEN_USULAN;
        }
        return JalanPorosDesa::HIERARKI_POROS_DESA;
    }

    /**
     * Ekstrak koordinat garis utama dari GeoJSON string/array untuk kalkulasi SQLite.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function decodeLineCoordinates(mixed $geom): array
    {
        $obj = is_string($geom) ? json_decode($geom, true) : (is_array($geom) ? $geom : null);
        if (!is_array($obj)) {
            return [];
        }
        if (($obj['type'] ?? '') === 'Feature' && isset($obj['geometry'])) {
            $obj = $obj['geometry'];
        }
        if (($obj['type'] ?? '') === 'LineString' && is_array($obj['coordinates'] ?? null)) {
            return array_values(array_filter(array_map(
                fn ($pt) => is_array($pt) && count($pt) >= 2 ? [(float) $pt[0], (float) $pt[1]] : null,
                $obj['coordinates']
            )));
        }
        if (($obj['type'] ?? '') === 'MultiLineString' && is_array($obj['coordinates'][0] ?? null)) {
            $flat = [];
            foreach ($obj['coordinates'] as $part) {
                if (is_array($part)) {
                    foreach ($part as $pt) {
                        if (is_array($pt) && count($pt) >= 2) {
                            $flat[] = [(float) $pt[0], (float) $pt[1]];
                        }
                    }
                }
            }
            return $flat;
        }

        return [];
    }

    /**
     * @param array<int, array{0: float, 1: float}> $coords
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    protected function computeCoordsBbox(array $coords): ?array
    {
        if (empty($coords)) {
            return null;
        }
        $minX = $maxX = $coords[0][0];
        $minY = $maxY = $coords[0][1];
        foreach ($coords as $pt) {
            if ($pt[0] < $minX) $minX = $pt[0];
            if ($pt[0] > $maxX) $maxX = $pt[0];
            if ($pt[1] < $minY) $minY = $pt[1];
            if ($pt[1] > $maxY) $maxY = $pt[1];
        }
        return [round($minX, 6), round($minY, 6), round($maxX, 6), round($maxY, 6)];
    }

    /**
     * @param array<int, array{0: float, 1: float}> $coords
     */
    protected function polylineLengthMeters(array $coords): float
    {
        $total = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $total += $this->haversineMeters($coords[$i - 1], $coords[$i]);
        }
        return round($total, 2);
    }

    /**
     * @param array<int, array{0: float, 1: float}> $coords
     * @return array<int, array{0: float, 1: float}>
     */
    protected function samplePolylinePoints(array $coords, int $targetCount = 20): array
    {
        if (count($coords) < 2) {
            return $coords;
        }
        $samples = [];
        $segCount = count($coords) - 1;
        $stepsPerSeg = max(1, (int) ceil($targetCount / $segCount));
        for ($i = 0; $i < $segCount; $i++) {
            $a = $coords[$i];
            $b = $coords[$i + 1];
            for ($s = 0; $s < $stepsPerSeg; $s++) {
                $t = $s / $stepsPerSeg;
                $samples[] = [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t];
            }
        }
        $samples[] = $coords[$segCount];
        return $samples;
    }

    /**
     * @param array{0: float, 1: float} $pt
     * @param array<int, array{0: float, 1: float}> $poly
     */
    protected function minDistancePointToPolylineMeters(array $pt, array $poly): float
    {
        if (count($poly) === 1) {
            return $this->haversineMeters($pt, $poly[0]);
        }
        $min = INF;
        for ($i = 1; $i < count($poly); $i++) {
            $d = $this->distancePointToSegmentMeters($pt, $poly[$i - 1], $poly[$i]);
            if ($d < $min) {
                $min = $d;
            }
        }
        return $min;
    }

    /**
     * @param array{0: float, 1: float} $p
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    protected function distancePointToSegmentMeters(array $p, array $a, array $b): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $lenSq = $dx * $dx + $dy * $dy;
        $t = $lenSq > 0 ? max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $lenSq)) : 0.0;
        $proj = [$a[0] + $t * $dx, $a[1] + $t * $dy];
        return $this->haversineMeters($p, $proj);
    }

    /**
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    protected function haversineMeters(array $a, array $b): float
    {
        $lat1 = deg2rad($a[1]);
        $lat2 = deg2rad($b[1]);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($b[0] - $a[0]);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        return 2 * 6371000.0 * asin(min(1.0, sqrt($h)));
    }
}

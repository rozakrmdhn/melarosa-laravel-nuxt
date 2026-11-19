<?php

namespace App\Services;

use App\Models\InfrastrukturSegmen;
use App\Models\JadwalTahunAnggaran;
use App\Models\JadwalTahunAnggaranWilayah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class JadwalAnggaranService
{
    /**
     * Evaluasi izin jadwal untuk aksi 'plotting' atau 'digitasi' pada tahun anggaran dan wilayah tertentu.
     * Catatan: Jadwal ini TIDAK diberlakukan untuk pengajuan Proposal BKKD,
     * dan Admin / Bappeda selalu memiliki bypass untuk edit/input koreksi kapan saja.
     */
    public function evaluateTerritoryForJadwal(
        JadwalTahunAnggaran $jadwal,
        ?int $idKecamatan = null,
        ?int $idDesa = null,
        ?Carbon $now = null,
    ): array {
        $now ??= now();

        $overrides = $jadwal->relationLoaded('wilayahOverrides')
            ? $jadwal->wilayahOverrides
            : $jadwal->wilayahOverrides()->get();

        // 1. Prioritas tertinggi: override tingkat Desa
        if ($idDesa) {
            $desaOverride = $overrides->first(
                fn (JadwalTahunAnggaranWilayah $ov) =>
                    $ov->id_desa !== null
                    && (int) $ov->id_desa === (int) $idDesa
                    && $this->isWithinWindow($now, $ov->mulai_at, $ov->selesai_at),
            );

            if ($desaOverride) {
                return $this->buildDecisionFromOverride($jadwal, $desaOverride, 'desa_override');
            }
        }

        // 2. Prioritas kedua: override tingkat Kecamatan (tanpa id_desa spesifik)
        if ($idKecamatan) {
            $kecOverride = $overrides->first(
                fn (JadwalTahunAnggaranWilayah $ov) =>
                    $ov->id_desa === null
                    && $ov->id_kecamatan !== null
                    && (int) $ov->id_kecamatan === (int) $idKecamatan
                    && $this->isWithinWindow($now, $ov->mulai_at, $ov->selesai_at),
            );

            if ($kecOverride) {
                return $this->buildDecisionFromOverride($jadwal, $kecOverride, 'kecamatan_override');
            }
        }

        // 3. Evaluasi jadwal global tahun anggaran
        $globalOpen = false;
        if ($jadwal->status_jadwal === 'dibuka') {
            $globalOpen = $this->isWithinWindow($now, $jadwal->mulai_at, $jadwal->selesai_at);
        } elseif ($jadwal->status_jadwal === 'terjadwal' && ($jadwal->mulai_at || $jadwal->selesai_at)) {
            $globalOpen = $this->isWithinWindow($now, $jadwal->mulai_at, $jadwal->selesai_at);
        }

        return [
            'is_open'        => $globalOpen,
            'source'         => 'global',
            'mode_akses'     => $globalOpen ? 'dibuka' : $jadwal->status_jadwal,
            'allow_plotting' => (bool) $jadwal->allow_plotting,
            'allow_digitasi' => (bool) $jadwal->allow_digitasi,
            'allow_non_pagu' => (bool) $jadwal->allow_non_pagu,
            'selesai_at'     => $jadwal->selesai_at?->toIso8601String(),
            'reason'         => $jadwal->catatan,
            'override_id'    => null,
        ];
    }

    /**
     * Cek apakah user boleh melakukan aksi ('plotting' | 'digitasi') pada tahun anggaran & wilayah target.
     */
    public function checkAccess(
        ?User $user,
        int $tahunAnggaran,
        ?int $idKecamatan,
        ?int $idDesa,
        string $action = 'digitasi',
        bool $isNonPagu = false,
    ): array {
        // Admin & Bappeda selalu mendapat bypass untuk koreksi kapan saja
        if ($user && $user->isAdminOrBappeda()) {
            return [
                'allowed' => true,
                'bypass'  => true,
                'source'  => 'bypass',
                'message' => null,
            ];
        }

        if (!Schema::hasTable('jadwal_tahun_anggaran')) {
            return [
                'allowed' => true,
                'bypass'  => false,
                'source'  => 'unconfigured',
                'message' => null,
            ];
        }

        // Jika Bappeda/Admin belum pernah membuat konfigurasi jadwal sama sekali, tetap izinkan agar tidak memutus alur lama
        if (!JadwalTahunAnggaran::query()->exists()) {
            return [
                'allowed' => true,
                'bypass'  => false,
                'source'  => 'unconfigured',
                'message' => null,
            ];
        }

        $jadwal = JadwalTahunAnggaran::with('wilayahOverrides')
            ->where('tahun_anggaran', $tahunAnggaran)
            ->first();

        if (!$jadwal) {
            return [
                'allowed' => false,
                'bypass'  => false,
                'source'  => 'not_scheduled',
                'message' => "Tahun Anggaran {$tahunAnggaran} belum dibuka dalam penjadwalan Bappeda.",
            ];
        }

        $decision = $this->evaluateTerritoryForJadwal($jadwal, $idKecamatan, $idDesa);

        if (!$decision['is_open']) {
            $reasonSuffix = !empty($decision['reason']) ? ' Catatan: ' . $decision['reason'] : '';
            return [
                'allowed' => false,
                'bypass'  => false,
                'source'  => $decision['source'],
                'message' => "Periode input Tahun Anggaran {$tahunAnggaran} sedang ditutup untuk wilayah Anda.{$reasonSuffix}",
            ];
        }

        if ($action === 'plotting' && !$decision['allow_plotting']) {
            return [
                'allowed' => false,
                'bypass'  => false,
                'source'  => $decision['source'],
                'message' => "Input atau perubahan Plotting Anggaran untuk Tahun Anggaran {$tahunAnggaran} sedang dinonaktifkan pada jadwal aktif.",
            ];
        }

        if ($action === 'digitasi') {
            if (!$decision['allow_digitasi']) {
                return [
                    'allowed' => false,
                    'bypass'  => false,
                    'source'  => $decision['source'],
                    'message' => "Digitasi dan perubahan segmen fisik untuk Tahun Anggaran {$tahunAnggaran} sedang dinonaktifkan pada jadwal aktif.",
                ];
            }
            if ($isNonPagu && !$decision['allow_non_pagu']) {
                return [
                    'allowed' => false,
                    'bypass'  => false,
                    'source'  => $decision['source'],
                    'message' => "Mode Inventarisasi Non-Pagu pada Tahun Anggaran {$tahunAnggaran} tidak diizinkan pada jadwal aktif.",
                ];
            }
        }

        return [
            'allowed'  => true,
            'bypass'   => false,
            'source'   => $decision['source'],
            'message'  => null,
            'decision' => $decision,
        ];
    }

    public function checkAccessForSegmen(?User $user, InfrastrukturSegmen $segmen): array
    {
        $isNonPagu = (bool) ($segmen->is_non_pagu || !$segmen->plotting_id);
        $tahun = (int) ($segmen->plotting?->tahun_anggaran ?: $segmen->tahun_pembangunan ?: date('Y'));

        return $this->checkAccess(
            $user,
            $tahun,
            $segmen->id_kecamatan ? (int) $segmen->id_kecamatan : null,
            $segmen->id_desa ? (int) $segmen->id_desa : null,
            'digitasi',
            $isNonPagu,
        );
    }

    /**
     * Daftar periode jadwal tahun anggaran lengkap dengan evaluasi efektif untuk wilayah user saat ini.
     */
    public function periodsForUser(?User $user, ?int $filterKecamatan = null, ?int $filterDesa = null): array
    {
        if (!Schema::hasTable('jadwal_tahun_anggaran')) {
            return [
                'configured' => false,
                'is_bypass'  => $user ? $user->isAdminOrBappeda() : false,
                'periods'    => [],
            ];
        }

        $jadwals = JadwalTahunAnggaran::with('wilayahOverrides')
            ->orderByDesc('tahun_anggaran')
            ->get();

        if ($jadwals->isEmpty()) {
            return [
                'configured' => false,
                'is_bypass'  => $user ? $user->isAdminOrBappeda() : false,
                'periods'    => [],
            ];
        }

        $isBypass = $user ? $user->isAdminOrBappeda() : false;
        $targetKec = $isBypass ? $filterKecamatan : ($user?->id_kecamatan ?: $filterKecamatan);
        $targetDesa = $isBypass ? $filterDesa : ($user?->id_desa ?: $filterDesa);

        $now = now();
        $periods = $jadwals->map(function (JadwalTahunAnggaran $jadwal) use ($targetKec, $targetDesa, $isBypass, $now) {
            $eval = $this->evaluateTerritoryForJadwal($jadwal, $targetKec, $targetDesa, $now);

            $activeDesaOverrides = $jadwal->wilayahOverrides->filter(
                fn (JadwalTahunAnggaranWilayah $ov) =>
                    $ov->id_desa !== null
                    && (!$targetKec || (int) $ov->id_kecamatan === (int) $targetKec)
                    && $this->isWithinWindow($now, $ov->mulai_at, $ov->selesai_at),
            );
            $openDesaOverrideIds = $activeDesaOverrides
                ->filter(fn (JadwalTahunAnggaranWilayah $ov) =>
                    $ov->mode_akses === 'buka_paksa'
                    && (bool) ($ov->allow_digitasi ?? $jadwal->allow_digitasi)
                )
                ->pluck('id_desa')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $openPlottingDesaIds = $activeDesaOverrides
                ->filter(fn (JadwalTahunAnggaranWilayah $ov) =>
                    $ov->mode_akses === 'buka_paksa'
                    && (bool) ($ov->allow_plotting ?? $jadwal->allow_plotting)
                )
                ->pluck('id_desa')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $closedDesaOverrideIds = $activeDesaOverrides
                ->filter(fn (JadwalTahunAnggaranWilayah $ov) => $ov->mode_akses === 'tutup_paksa')
                ->pluck('id_desa')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            return [
                'id'                       => $jadwal->id,
                'tahun_anggaran'           => $jadwal->tahun_anggaran,
                'nama_periode'             => $jadwal->nama_periode,
                'status_jadwal'            => $jadwal->status_jadwal,
                'is_open'                  => $eval['is_open'],
                'effective_open'           => $isBypass ? true : $eval['is_open'],
                'source'                   => $eval['source'],
                'mode_akses'               => $eval['mode_akses'],
                'allow_plotting'           => $isBypass ? true : $eval['allow_plotting'],
                'allow_digitasi'           => $isBypass ? true : $eval['allow_digitasi'],
                'allow_non_pagu'           => $isBypass ? true : $eval['allow_non_pagu'],
                'mulai_at'                 => $jadwal->mulai_at?->toIso8601String(),
                'selesai_at'               => $eval['selesai_at'],
                'reason'                   => $eval['reason'],
                'is_desa_override'         => $eval['source'] === 'desa_override',
                'open_desa_override_ids'   => $openDesaOverrideIds,
                'open_plotting_desa_ids'   => $openPlottingDesaIds,
                'closed_desa_override_ids' => $closedDesaOverrideIds,
            ];
        })->values()->all();

        return [
            'configured' => true,
            'is_bypass'  => $isBypass,
            'periods'    => $periods,
        ];
    }

    private function buildDecisionFromOverride(
        JadwalTahunAnggaran $jadwal,
        JadwalTahunAnggaranWilayah $override,
        string $source,
    ): array {
        $isOpen = $override->mode_akses === 'buka_paksa';

        return [
            'is_open'        => $isOpen,
            'source'         => $source,
            'mode_akses'     => $override->mode_akses,
            'allow_plotting' => $override->allow_plotting ?? (bool) $jadwal->allow_plotting,
            'allow_digitasi' => $override->allow_digitasi ?? (bool) $jadwal->allow_digitasi,
            'allow_non_pagu' => $override->allow_non_pagu ?? (bool) $jadwal->allow_non_pagu,
            'selesai_at'     => ($override->selesai_at ?? $jadwal->selesai_at)?->toIso8601String(),
            'reason'         => $override->alasan ?: $jadwal->catatan,
            'override_id'    => $override->id,
        ];
    }

    private function isWithinWindow(Carbon $now, ?Carbon $start, ?Carbon $end): bool
    {
        if ($start && $now->lt($start)) {
            return false;
        }
        if ($end && $now->gt($end)) {
            return false;
        }
        return true;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasWilayahDesa;
use App\Models\BkkdProposal;
use App\Models\RefBkkdBidang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BkkdAgregasiDesaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $tahun = (int) $request->input('tahun_anggaran', now()->year);
        $idKecamatan = $request->filled('id_kecamatan') ? (int) $request->input('id_kecamatan') : null;
        $idDesa = $request->filled('id_desa') ? (int) $request->input('id_desa') : null;
        $idBidang = $request->filled('id_bidang') && $request->input('id_bidang') !== 'ALL' ? $request->input('id_bidang') : null;
        $statusFilter = $request->input('status', 'ALL');
        $search = trim((string) $request->input('search', ''));
        $mode = $request->input('mode', 'bidang'); // 'bidang' (per bidang) or 'desa' (konsolidasi per desa)

        $sortBy = (string) $request->input('sort_by', 'kecamatan');
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allBidang = Cache::remember('ref_bkkd_bidang_all', 300, function () {
            return RefBkkdBidang::select(['id', 'nama'])->get();
        });
        $bidangMap = $allBidang->keyBy('id');

        $desaQuery = BatasWilayahDesa::join('bataswilayah_kecamatan', 'bataswilayah_desa.id_kecamatan', '=', 'bataswilayah_kecamatan.id')
            ->select([
                'bataswilayah_desa.id',
                'bataswilayah_desa.id_kecamatan',
                'bataswilayah_desa.nama_desa',
                'bataswilayah_kecamatan.nama_kecamatan',
            ])
            ->orderBy('bataswilayah_kecamatan.nama_kecamatan', 'asc')
            ->orderBy('bataswilayah_desa.nama_desa', 'asc');

        if ($idKecamatan) {
            $desaQuery->where('bataswilayah_desa.id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $desaQuery->where('bataswilayah_desa.id', $idDesa);
        }

        $allDesas = $desaQuery->get();

        $propQuery = BkkdProposal::with([
            'bidang:id,nama',
        ])
        ->select([
            'id',
            'id_desa',
            'id_kecamatan',
            'id_bidang',
            'nomor_registrasi',
            'judul_kegiatan',
            'nilai_usulan',
            'status_analisa_spasial',
            'tahun_anggaran',
        ])
        ->forUser($user)
        ->where('tahun_anggaran', $tahun);

        if ($idKecamatan) {
            $propQuery->where('id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $propQuery->where('id_desa', $idDesa);
        }
        if ($idBidang) {
            $propQuery->where('id_bidang', $idBidang);
        }

        $allProposals = $propQuery->get();
        $proposalsByDesa = $allProposals->groupBy('id_desa');

        $totalDesaWilayah = $allDesas->count();
        $desaWithProposalsCount = 0;
        foreach ($proposalsByDesa as $pDesa) {
            if ($pDesa->isNotEmpty()) {
                $desaWithProposalsCount++;
            }
        }

        $totalUsulanSemua = $allProposals->count();
        $totalNilaiSemua = (float) $allProposals->sum('nilai_usulan');

        $records = [];
        $selectedBidangObj = $idBidang ? $bidangMap->get($idBidang) : null;

        foreach ($allDesas as $desa) {
            $pDesa = $proposalsByDesa->get($desa->id, collect());

            if ($mode === 'desa') {
                $jumlahUsulan = $pDesa->count();
                $bidangList = $pDesa->map(fn ($p) => $p->bidang?->nama)->filter()->unique()->values()->all();

                $records[] = [
                    'id_desa' => $desa->id,
                    'nama_desa' => $desa->nama_desa,
                    'id_kecamatan' => $desa->id_kecamatan,
                    'nama_kecamatan' => $desa->nama_kecamatan ?? $desa->kecamatan?->nama_kecamatan ?? '',
                    'nama_bidang' => !empty($bidangList) ? implode(', ', $bidangList) : '-',
                    'jumlah_usulan' => $jumlahUsulan,
                    'total_nilai_usulan' => (float) $pDesa->sum('nilai_usulan'),
                    'proposals' => $pDesa->map(fn ($p) => [
                        'id' => $p->id,
                        'nomor_registrasi' => $p->nomor_registrasi,
                        'judul_kegiatan' => $p->judul_kegiatan,
                        'id_bidang' => $p->id_bidang,
                        'bidang' => $p->bidang?->nama,
                        'nilai_usulan' => $p->nilai_usulan !== null ? (float) $p->nilai_usulan : null,
                        'status_analisa_spasial' => $p->status_analisa_spasial ?? 'belum_dianalisa',
                        'status' => $p->status_analisa_spasial ?? 'belum_dianalisa',
                    ])->values()->all(),
                ];
            } else {
                if ($pDesa->isEmpty()) {
                    $records[] = [
                        'id_desa' => $desa->id,
                        'nama_desa' => $desa->nama_desa,
                        'id_kecamatan' => $desa->id_kecamatan,
                        'nama_kecamatan' => $desa->nama_kecamatan ?? $desa->kecamatan?->nama_kecamatan ?? '',
                        'id_bidang' => $selectedBidangObj?->id ?? null,
                        'nama_bidang' => $selectedBidangObj?->nama ?? '-',
                        'jumlah_usulan' => 0,
                        'total_nilai_usulan' => 0,
                        'proposals' => [],
                    ];
                } else {
                    $byBidang = $pDesa->groupBy('id_bidang');
                    foreach ($byBidang as $bId => $bidangProps) {
                        $firstP = $bidangProps->first();
                        $bidang = $firstP->bidang;
                        $jumlahUsulan = $bidangProps->count();

                        $records[] = [
                            'id_desa' => $desa->id,
                            'nama_desa' => $desa->nama_desa,
                            'id_kecamatan' => $desa->id_kecamatan,
                            'nama_kecamatan' => $desa->nama_kecamatan ?? $desa->kecamatan?->nama_kecamatan ?? '',
                            'id_bidang' => $bId,
                            'nama_bidang' => $bidang?->nama ?? '-',
                            'jumlah_usulan' => $jumlahUsulan,
                            'total_nilai_usulan' => (float) $bidangProps->sum('nilai_usulan'),
                            'proposals' => $bidangProps->map(fn ($p) => [
                                'id' => $p->id,
                                'nomor_registrasi' => $p->nomor_registrasi,
                                'judul_kegiatan' => $p->judul_kegiatan,
                                'id_bidang' => $p->id_bidang,
                                'bidang' => $p->bidang?->nama,
                                'nilai_usulan' => $p->nilai_usulan !== null ? (float) $p->nilai_usulan : null,
                                'status_analisa_spasial' => $p->status_analisa_spasial ?? 'belum_dianalisa',
                                'status' => $p->status_analisa_spasial ?? 'belum_dianalisa',
                            ])->values()->all(),
                        ];
                    }
                }
            }
        }

        // Apply Status Filter
        if ($statusFilter !== 'ALL') {
            $records = array_values(array_filter($records, function ($item) use ($statusFilter) {
                if ($statusFilter === 'sudah_ada') {
                    return $item['jumlah_usulan'] > 0;
                }
                if ($statusFilter === 'belum_ada') {
                    return $item['jumlah_usulan'] === 0;
                }
                return true;
            }));
        }

        // Apply Search Filter
        if ($search !== '') {
            $searchLower = strtolower($search);
            $records = array_values(array_filter($records, function ($item) use ($searchLower) {
                return str_contains(strtolower($item['nama_desa']), $searchLower)
                    || str_contains(strtolower($item['nama_kecamatan']), $searchLower)
                    || str_contains(strtolower($item['nama_bidang']), $searchLower);
            }));
        }

        // Apply Sorting (default: nama_kecamatan asc, then nama_desa asc)
        usort($records, function ($a, $b) use ($sortBy, $sortOrder) {
            if ($sortBy === 'desa') {
                $cmp = strcasecmp($a['nama_desa'], $b['nama_desa']);
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
                return strcasecmp($a['nama_kecamatan'], $b['nama_kecamatan']);
            }

            if ($sortBy === 'bidang') {
                $cmp = strcasecmp($a['nama_bidang'], $b['nama_bidang']);
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
            } elseif ($sortBy === 'jumlah_usulan') {
                $cmp = $a['jumlah_usulan'] <=> $b['jumlah_usulan'];
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
            } elseif ($sortBy === 'total_nilai_usulan') {
                $cmp = $a['total_nilai_usulan'] <=> $b['total_nilai_usulan'];
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
            }

            // Primary default: Kecamatan
            $cmpKec = strcasecmp($a['nama_kecamatan'], $b['nama_kecamatan']);
            if ($cmpKec !== 0) {
                return ($sortOrder === 'desc' && $sortBy === 'kecamatan') ? -$cmpKec : $cmpKec;
            }

            // Secondary default: Desa
            $cmpDesa = strcasecmp($a['nama_desa'], $b['nama_desa']);
            if ($cmpDesa !== 0) {
                return $cmpDesa;
            }

            // Tertiary: Bidang
            return strcasecmp($a['nama_bidang'], $b['nama_bidang']);
        });

        $totalRows = count($records);
        $perPage = max(1, min(500, (int) $request->input('per_page', 25)));
        $currentPage = max(1, (int) $request->input('page', 1));
        $offset = ($currentPage - 1) * $perPage;
        $paginatedRecords = array_slice($records, $offset, $perPage);

        return response()->json([
            'ok' => true,
            'tahun_anggaran' => $tahun,
            'summary' => [
                'total_desa' => $totalDesaWilayah,
                'desa_ada_usulan' => $desaWithProposalsCount,
                'desa_belum_ada_usulan' => $totalDesaWilayah - $desaWithProposalsCount,
                'total_usulan' => $totalUsulanSemua,
                'total_nilai_usulan' => $totalNilaiSemua,
            ],
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $totalRows,
                'last_page' => (int) ceil($totalRows / $perPage),
            ],
            'data' => $paginatedRecords,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', BkkdProposal::class);

        $user = $request->user();
        $tahun = (int) $request->input('tahun_anggaran', now()->year);
        $idKecamatan = $request->filled('id_kecamatan') ? (int) $request->input('id_kecamatan') : null;
        $idDesa = $request->filled('id_desa') ? (int) $request->input('id_desa') : null;
        $idBidang = $request->filled('id_bidang') && $request->input('id_bidang') !== 'ALL' ? $request->input('id_bidang') : null;
        $statusFilter = $request->input('status', 'ALL');
        $search = trim((string) $request->input('search', ''));
        $mode = $request->input('mode', 'bidang');

        $sortBy = (string) $request->input('sort_by', 'kecamatan');
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allBidang = Cache::remember('ref_bkkd_bidang_all', 300, function () {
            return RefBkkdBidang::select(['id', 'nama'])->get();
        });
        $bidangMap = $allBidang->keyBy('id');

        $desaQuery = BatasWilayahDesa::join('bataswilayah_kecamatan', 'bataswilayah_desa.id_kecamatan', '=', 'bataswilayah_kecamatan.id')
            ->select([
                'bataswilayah_desa.id',
                'bataswilayah_desa.id_kecamatan',
                'bataswilayah_desa.nama_desa',
                'bataswilayah_kecamatan.nama_kecamatan',
            ])
            ->orderBy('bataswilayah_kecamatan.nama_kecamatan', 'asc')
            ->orderBy('bataswilayah_desa.nama_desa', 'asc');

        if ($idKecamatan) {
            $desaQuery->where('bataswilayah_desa.id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $desaQuery->where('bataswilayah_desa.id', $idDesa);
        }

        $allDesas = $desaQuery->get();

        $propQuery = BkkdProposal::with([
            'bidang:id,nama',
        ])
        ->select([
            'id',
            'id_desa',
            'id_kecamatan',
            'id_bidang',
            'nomor_registrasi',
            'judul_kegiatan',
            'nilai_usulan',
            'status_analisa_spasial',
            'tahun_anggaran',
        ])
        ->forUser($user)
        ->where('tahun_anggaran', $tahun);

        if ($idKecamatan) {
            $propQuery->where('id_kecamatan', $idKecamatan);
        }
        if ($idDesa) {
            $propQuery->where('id_desa', $idDesa);
        }
        if ($idBidang) {
            $propQuery->where('id_bidang', $idBidang);
        }

        $allProposals = $propQuery->get();
        $proposalsByDesa = $allProposals->groupBy('id_desa');

        $records = [];
        $selectedBidangObj = $idBidang ? $bidangMap->get($idBidang) : null;

        foreach ($allDesas as $desa) {
            $pDesa = $proposalsByDesa->get($desa->id, collect());

            if ($mode === 'desa') {
                $jumlahUsulan = $pDesa->count();
                $bidangList = $pDesa->map(fn ($p) => $p->bidang?->nama)->filter()->unique()->values()->all();

                $records[] = [
                    'nama_kecamatan' => $desa->nama_kecamatan ?? $desa->kecamatan?->nama_kecamatan ?? '',
                    'nama_desa' => $desa->nama_desa,
                    'nama_bidang' => !empty($bidangList) ? implode(', ', $bidangList) : '-',
                    'jumlah_usulan' => $jumlahUsulan,
                    'total_nilai_usulan' => (float) $pDesa->sum('nilai_usulan'),
                ];
            } else {
                if ($pDesa->isEmpty()) {
                    $records[] = [
                        'nama_kecamatan' => $desa->nama_kecamatan ?? $desa->kecamatan?->nama_kecamatan ?? '',
                        'nama_desa' => $desa->nama_desa,
                        'nama_bidang' => $selectedBidangObj?->nama ?? '-',
                        'jumlah_usulan' => 0,
                        'total_nilai_usulan' => 0,
                    ];
                } else {
                    $byBidang = $pDesa->groupBy('id_bidang');
                    foreach ($byBidang as $bId => $bidangProps) {
                        $firstP = $bidangProps->first();
                        $bidang = $firstP->bidang;
                        $jumlahUsulan = $bidangProps->count();

                        $records[] = [
                            'nama_kecamatan' => $desa->nama_kecamatan ?? $desa->kecamatan?->nama_kecamatan ?? '',
                            'nama_desa' => $desa->nama_desa,
                            'nama_bidang' => $bidang?->nama ?? '-',
                            'jumlah_usulan' => $jumlahUsulan,
                            'total_nilai_usulan' => (float) $bidangProps->sum('nilai_usulan'),
                        ];
                    }
                }
            }
        }

        if ($statusFilter !== 'ALL') {
            $records = array_values(array_filter($records, function ($item) use ($statusFilter) {
                if ($statusFilter === 'sudah_ada') return $item['jumlah_usulan'] > 0;
                if ($statusFilter === 'belum_ada') return $item['jumlah_usulan'] === 0;
                return true;
            }));
        }

        if ($search !== '') {
            $searchLower = strtolower($search);
            $records = array_values(array_filter($records, function ($item) use ($searchLower) {
                return str_contains(strtolower($item['nama_desa']), $searchLower)
                    || str_contains(strtolower($item['nama_kecamatan']), $searchLower)
                    || str_contains(strtolower($item['nama_bidang']), $searchLower);
            }));
        }

        // Apply Sorting (default: nama_kecamatan asc, then nama_desa asc)
        usort($records, function ($a, $b) use ($sortBy, $sortOrder) {
            if ($sortBy === 'desa') {
                $cmp = strcasecmp($a['nama_desa'], $b['nama_desa']);
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
                return strcasecmp($a['nama_kecamatan'], $b['nama_kecamatan']);
            }

            if ($sortBy === 'bidang') {
                $cmp = strcasecmp($a['nama_bidang'], $b['nama_bidang']);
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
            } elseif ($sortBy === 'jumlah_usulan') {
                $cmp = $a['jumlah_usulan'] <=> $b['jumlah_usulan'];
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
            } elseif ($sortBy === 'total_nilai_usulan') {
                $cmp = $a['total_nilai_usulan'] <=> $b['total_nilai_usulan'];
                if ($cmp !== 0) {
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                }
            }

            // Primary default: Kecamatan
            $cmpKec = strcasecmp($a['nama_kecamatan'], $b['nama_kecamatan']);
            if ($cmpKec !== 0) {
                return ($sortOrder === 'desc' && $sortBy === 'kecamatan') ? -$cmpKec : $cmpKec;
            }

            // Secondary default: Desa
            $cmpDesa = strcasecmp($a['nama_desa'], $b['nama_desa']);
            if ($cmpDesa !== 0) {
                return $cmpDesa;
            }

            // Tertiary: Bidang
            return strcasecmp($a['nama_bidang'], $b['nama_bidang']);
        });

        $filename = sprintf('Agregasi_Usulan_Desa_BKKD_%s_%s.csv', $tahun, date('Ymd_His'));

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'No',
                'Kecamatan',
                'Desa',
                'Bidang',
                'Jumlah Usulan',
                'Total Nilai Usulan (Rp)',
            ]);

            $no = 1;
            foreach ($records as $r) {
                fputcsv($handle, [
                    $no++,
                    $r['nama_kecamatan'],
                    $r['nama_desa'],
                    $r['nama_bidang'],
                    $r['jumlah_usulan'],
                    number_format($r['total_nilai_usulan'], 0, ',', '.'),
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}

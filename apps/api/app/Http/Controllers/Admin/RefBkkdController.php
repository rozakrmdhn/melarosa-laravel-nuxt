<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BkkdRiwayatResource;
use App\Models\BkkdProposalRiwayat;
use App\Models\BkkdVerifikasiRiwayat;
use App\Models\RefBkkdBidang;
use App\Models\RefOpd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RefBkkdController extends Controller
{
    public function opd(): JsonResponse
    {
        return $this->opdList();
    }

    public function opdList(): JsonResponse
    {
        $opd = RefOpd::where('is_active', true)->orderBy('kode')->get();

        return response()->json([
            'ok' => true,
            'data' => $opd,
        ]);
    }

    public function bidang(): JsonResponse
    {
        return $this->bidangList();
    }

    public function bidangList(): JsonResponse
    {
        $bidang = RefBkkdBidang::with('opdDefault:id,kode,nama')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $bidang,
        ]);
    }

    public function riwayatGlobal(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasPermissionTo('bkkd-riwayat-view'))) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $jenis = $request->input('jenis', 'proposal'); // 'proposal' or 'verifikasi'
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        if ($jenis === 'verifikasi') {
            $query = BkkdVerifikasiRiwayat::with([
                'actor:uuid,name,email',
                'verifikasi.opd:id,kode,nama',
                'verifikasi.proposal.kecamatan:id,nama_kecamatan',
                'verifikasi.proposal.desa:id,nama_desa',
            ]);

            if ($request->filled('aksi')) {
                $query->where('aksi', $request->input('aksi'));
            }

            if ($request->filled('dari_tanggal')) {
                $query->whereDate('created_at', '>=', $request->input('dari_tanggal'));
            }

            if ($request->filled('sampai_tanggal')) {
                $query->whereDate('created_at', '<=', $request->input('sampai_tanggal'));
            }

            $query->orderByDesc('created_at');
            $results = $query->paginate($perPage);

            return response()->json([
                'ok' => true,
                'data' => BkkdRiwayatResource::collection($results->items()),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
            ]);
        }

        // Default: riwayat proposal
        $query = BkkdProposalRiwayat::with([
            'actor:uuid,name,email',
            'proposal.kecamatan:id,nama_kecamatan',
            'proposal.desa:id,nama_desa',
        ]);

        if ($request->filled('aksi')) {
            $query->where('aksi', $request->input('aksi'));
        }

        if ($request->filled('dari_tanggal')) {
            $query->whereDate('created_at', '>=', $request->input('dari_tanggal'));
        }

        if ($request->filled('sampai_tanggal')) {
            $query->whereDate('created_at', '<=', $request->input('sampai_tanggal'));
        }

        $query->orderByDesc('created_at');
        $results = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => BkkdRiwayatResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InfrastrukturTipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InfrastrukturTipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InfrastrukturTipe::query()->orderBy('sort_order', 'asc');

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'ok'   => true,
            'data' => $query->get(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $tipe = InfrastrukturTipe::where('id', $id)
            ->orWhere('kode', $id)
            ->first();

        if (!$tipe) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tipe infrastruktur tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'ok'   => true,
            'data' => $tipe,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode'        => ['required', 'string', 'max:50', 'unique:infrastruktur_tipe,kode'],
            'nama'        => ['required', 'string', 'max:255'],
            'deskripsi'   => ['nullable', 'string'],
            'ikon'        => ['nullable', 'string', 'max:100'],
            'warna'       => ['nullable', 'string', 'max:50'],
            'geom_type'   => ['nullable', 'string', 'in:LineString,MultiLineString,Point,Polygon'],
            'table_name'  => ['nullable', 'string', 'max:100'],
            'has_segmen'  => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
            'config'      => ['nullable', 'array'],
        ]);

        $tipe = new InfrastrukturTipe();
        $tipe->id = (string) Str::uuid();
        $tipe->fill($validated);
        $tipe->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Tipe infrastruktur berhasil ditambahkan.',
            'data'    => $tipe,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tipe = InfrastrukturTipe::where('id', $id)->first();
        if (!$tipe) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tipe infrastruktur tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'kode'        => ['sometimes', 'string', 'max:50', 'unique:infrastruktur_tipe,kode,' . $tipe->id],
            'nama'        => ['sometimes', 'string', 'max:255'],
            'deskripsi'   => ['nullable', 'string'],
            'ikon'        => ['nullable', 'string', 'max:100'],
            'warna'       => ['nullable', 'string', 'max:50'],
            'geom_type'   => ['nullable', 'string', 'in:LineString,MultiLineString,Point,Polygon'],
            'table_name'  => ['nullable', 'string', 'max:100'],
            'has_segmen'  => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
            'config'      => ['nullable', 'array'],
        ]);

        $tipe->fill($validated);
        $tipe->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Tipe infrastruktur berhasil diperbarui.',
            'data'    => $tipe,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $tipe = InfrastrukturTipe::where('id', $id)->first();
        if (!$tipe) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tipe infrastruktur tidak ditemukan.',
            ], 404);
        }

        if ($tipe->segmen()->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tipe infrastruktur ini tidak dapat dihapus karena sudah memiliki data segmen terkait.',
            ], 422);
        }

        $tipe->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Tipe infrastruktur berhasil dihapus.',
        ]);
    }
}

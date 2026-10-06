<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefSumberDana;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RefSumberDanaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RefSumberDana::query()->orderBy('sort_order', 'asc');

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
        $sd = RefSumberDana::where('id', $id)
            ->orWhere('kode', $id)
            ->first();

        if (!$sd) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sumber dana tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'ok'   => true,
            'data' => $sd,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode'       => ['required', 'string', 'max:50', 'unique:ref_sumber_dana,kode'],
            'nama'       => ['required', 'string', 'max:255'],
            'kategori'   => ['nullable', 'string', 'max:100'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $sd = new RefSumberDana();
        $sd->id = (string) Str::uuid();
        $sd->fill($validated);
        $sd->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Sumber dana berhasil ditambahkan.',
            'data'    => $sd,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $sd = RefSumberDana::where('id', $id)->first();
        if (!$sd) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sumber dana tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'kode'       => ['sometimes', 'string', 'max:50', 'unique:ref_sumber_dana,kode,' . $sd->id],
            'nama'       => ['sometimes', 'string', 'max:255'],
            'kategori'   => ['nullable', 'string', 'max:100'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $sd->fill($validated);
        $sd->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Sumber dana berhasil diperbarui.',
            'data'    => $sd,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $sd = RefSumberDana::where('id', $id)->first();
        if (!$sd) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sumber dana tidak ditemukan.',
            ], 404);
        }

        $sd->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Sumber dana berhasil dihapus.',
        ]);
    }
}

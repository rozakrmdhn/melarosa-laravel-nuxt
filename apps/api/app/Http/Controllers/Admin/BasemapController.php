<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Basemap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BasemapController extends Controller
{
    private const PREVIEW_STYLES = ['esri', 'rbi', 'osm', 'satellite', 'aerial', 'light', 'dark', 'topo'];

    /**
     * Daftar basemap aktif untuk konsumsi publik & seluruh peta OpenLayers/MapLibreGLJS.
     */
    public function publicIndex(): JsonResponse
    {
        $items = Cache::remember(Basemap::PUBLIC_CACHE_KEY, now()->addMinutes(15), function () {
            return Basemap::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderByDesc('is_default')
                ->orderBy('nama')
                ->get();
        });

        $defaultItem = $items->firstWhere('is_default', true) ?? $items->first();

        return response()->json([
            'ok'           => true,
            'default_kode' => $defaultItem?->kode ?? 'esri',
            'data'         => $items,
        ]);
    }

    /**
     * Daftar seluruh basemap untuk manajemen admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Basemap::with('updater:uuid,name')
            ->orderBy('sort_order')
            ->orderByDesc('is_default')
            ->orderBy('nama');

        if ($request->filled('search')) {
            $term = '%' . mb_strtolower(trim((string) $request->input('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(nama) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(kode) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(COALESCE(provider, \'\')) LIKE ?', [$term]);
            });
        }

        return response()->json([
            'ok'   => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['kode'] = strtolower(trim($validated['kode']));
        $validated['updated_by'] = $request->user()?->uuid;

        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = (int) Basemap::max('sort_order') + 1;
        }

        $basemap = DB::transaction(function () use ($validated) {
            if (!empty($validated['is_default'])) {
                Basemap::query()->update(['is_default' => false]);
                $validated['is_active'] = true;
            }
            return Basemap::create($validated);
        });

        return response()->json([
            'ok'      => true,
            'message' => "Basemap {$basemap->nama} berhasil ditambahkan.",
            'data'    => $basemap->load('updater:uuid,name'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $basemap = Basemap::find($id);
        if (!$basemap) {
            return response()->json(['ok' => false, 'message' => 'Basemap tidak ditemukan.'], 404);
        }

        $validated = $this->validatePayload($request, $basemap->id);
        if (isset($validated['kode'])) {
            $validated['kode'] = strtolower(trim($validated['kode']));
        }
        $validated['updated_by'] = $request->user()?->uuid;

        DB::transaction(function () use ($basemap, $validated) {
            if (!empty($validated['is_default'])) {
                Basemap::where('id', '!=', $basemap->id)->update(['is_default' => false]);
                $validated['is_active'] = true;
            }
            $basemap->fill($validated);
            $basemap->save();
        });

        return response()->json([
            'ok'      => true,
            'message' => "Basemap {$basemap->nama} diperbarui.",
            'data'    => $basemap->fresh('updater:uuid,name'),
        ]);
    }

    public function setDefault(Request $request, string $id): JsonResponse
    {
        $basemap = Basemap::find($id);
        if (!$basemap) {
            return response()->json(['ok' => false, 'message' => 'Basemap tidak ditemukan.'], 404);
        }

        DB::transaction(function () use ($basemap, $request) {
            Basemap::where('id', '!=', $basemap->id)->update(['is_default' => false]);
            $basemap->is_default = true;
            $basemap->is_active = true;
            $basemap->updated_by = $request->user()?->uuid;
            $basemap->save();
        });

        return response()->json([
            'ok'      => true,
            'message' => "{$basemap->nama} ditetapkan sebagai basemap utama.",
            'data'    => $basemap->fresh('updater:uuid,name'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $basemap = Basemap::find($id);
        if (!$basemap) {
            return response()->json(['ok' => false, 'message' => 'Basemap tidak ditemukan.'], 404);
        }

        if ($basemap->is_default) {
            return response()->json([
                'ok'      => false,
                'message' => 'Basemap default tidak dapat dihapus. Tetapkan basemap lain sebagai default terlebih dahulu.',
            ], 422);
        }

        $nama = $basemap->nama;
        $basemap->delete();

        return response()->json([
            'ok'      => true,
            'message' => "Basemap {$nama} dihapus.",
        ]);
    }

    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        $isUpdate = $ignoreId !== null;
        $reqRule = $isUpdate ? 'sometimes' : 'required';

        $urlSchemeRule = function ($attr, $value, $fail) {
            $v = trim((string) $value);
            if ($v === '') {
                return;
            }
            if (!str_starts_with($v, 'https://') && !str_starts_with($v, 'http://') && !str_starts_with($v, '/')) {
                $fail('URL harus diawali https://, http://, atau path relatif /.');
            }
        };

        $validated = $request->validate([
            'kode'                  => [
                $reqRule,
                'string',
                'max:60',
                'regex:/^[a-z0-9_-]+$/i',
                Rule::unique('basemaps', 'kode')->ignore($ignoreId),
            ],
            'nama'                  => [$reqRule, 'string', 'max:120'],
            'label_singkat'         => [$reqRule, 'string', 'max:40'],
            'tagline'               => ['nullable', 'string', 'max:200'],
            'provider'              => ['nullable', 'string', 'max:120'],
            'tipe'                  => ['sometimes', 'string', 'in:raster,vector'],
            'url_template'          => [$reqRule, 'string', 'max:1000', function ($attr, $value, $fail) {
                $v = trim((string) $value);
                if (!str_starts_with($v, 'https://') && !str_starts_with($v, 'http://') && !str_starts_with($v, '/')) {
                    $fail('URL template harus diawali https://, http://, atau path relatif /.');
                }
            }],
            'fallback_url_template' => ['nullable', 'string', 'max:1000', $urlSchemeRule],
            'tile_urls'             => ['nullable', 'array', 'max:8'],
            'tile_urls.*'           => ['string', 'max:1000', $urlSchemeRule],
            'attribution'           => ['nullable', 'string', 'max:255'],
            'thumbnail_url'         => ['nullable', 'string', 'max:255', $urlSchemeRule],
            'preview_style'         => ['sometimes', 'string', Rule::in(self::PREVIEW_STYLES)],
            'min_zoom'              => ['sometimes', 'integer', 'min:0', 'max:22'],
            'max_zoom'              => ['sometimes', 'integer', 'min:1', 'max:22'],
            'tile_size'             => ['sometimes', 'integer', 'in:256,512'],
            'cross_origin'          => ['nullable', 'string', 'max:30'],
            'supports_openlayers'   => ['sometimes', 'boolean'],
            'supports_maplibre'     => ['sometimes', 'boolean'],
            'is_default'            => ['sometimes', 'boolean'],
            'is_active'             => ['sometimes', 'boolean'],
            'sort_order'            => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        if (array_key_exists('tile_urls', $validated) && is_array($validated['tile_urls'])) {
            $cleaned = array_values(array_filter(array_map('trim', $validated['tile_urls']), fn ($u) => $u !== ''));
            $validated['tile_urls'] = count($cleaned) > 0 ? $cleaned : null;
        }

        return $validated;
    }
}

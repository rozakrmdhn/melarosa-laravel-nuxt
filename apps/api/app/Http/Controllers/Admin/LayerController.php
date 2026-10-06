<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LayerController extends Controller
{
    /**
     * Display a listing of the layers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $protocol = $request->query('protocol');
        $sourceType = $request->query('source_type');
        $isActive = $request->query('is_active');
        $defaultVisible = $request->query('default_visible');
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 15;
        }

        $query = Layer::query()
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('layer_name', 'ilike', "%{$search}%")
                        ->orWhere('attribution', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($protocol, function ($q, $protocol) {
                $q->where('protocol', $protocol);
            })
            ->when($sourceType, function ($q, $sourceType) {
                $q->where('source_type', $sourceType);
            })
            ->when($request->has('is_active') && $isActive !== null && $isActive !== '', function ($q) use ($isActive) {
                $q->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->has('default_visible') && $defaultVisible !== null && $defaultVisible !== '', function ($q) use ($defaultVisible) {
                $q->where('default_visible', filter_var($defaultVisible, FILTER_VALIDATE_BOOLEAN));
            });

        // Statistics for summary badges
        $summary = [
            'total' => Layer::count(),
            'active' => Layer::where('is_active', true)->count(),
            'default_visible' => Layer::where('default_visible', true)->count(),
        ];

        // Ordering: priority by order ASC, then name ASC
        $layers = $query->orderBy('order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'ok' => true,
            'summary' => $summary,
            'data' => $layers->items(),
            'meta' => [
                'current_page' => $layers->currentPage(),
                'last_page' => $layers->lastPage(),
                'per_page' => $layers->perPage(),
                'total' => $layers->total(),
            ],
        ]);
    }

    /**
     * Store a newly created layer in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'protocol' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:500'],
            'layer_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'default_visible' => ['sometimes', 'boolean'],
            'opacity' => ['sometimes', 'numeric', 'between:0,1'],
            'order' => ['nullable', 'integer', 'min:0'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_type' => ['required', 'string', 'max:50'],
            'is_synced' => ['sometimes', 'boolean'],
        ]);

        if (!isset($validated['order']) || $validated['order'] === null) {
            $maxOrder = Layer::max('order') ?? -1;
            $validated['order'] = $maxOrder + 1;
        }

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['default_visible'] = $validated['default_visible'] ?? false;
        $validated['opacity'] = $validated['opacity'] ?? 1.0;
        $validated['is_synced'] = $validated['is_synced'] ?? false;

        $layer = Layer::create($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Layer baru berhasil ditambahkan.',
            'data' => $layer,
        ], 201);
    }

    /**
     * Display the specified layer.
     */
    public function show(Layer $layer): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $layer,
        ]);
    }

    /**
     * Update the specified layer in storage.
     */
    public function update(Request $request, Layer $layer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'protocol' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'max:500'],
            'layer_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'default_visible' => ['sometimes', 'boolean'],
            'opacity' => ['sometimes', 'numeric', 'between:0,1'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_type' => ['sometimes', 'required', 'string', 'max:50'],
            'is_synced' => ['sometimes', 'boolean'],
        ]);

        $layer->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Data layer berhasil diperbarui.',
            'data' => $layer,
        ]);
    }

    /**
     * Remove the specified layer from storage.
     */
    public function destroy(Layer $layer): JsonResponse
    {
        $layer->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Layer berhasil dihapus.',
        ]);
    }

    /**
     * Fast toggle active status.
     */
    public function toggleActive(Layer $layer): JsonResponse
    {
        $layer->is_active = !$layer->is_active;
        $layer->save();

        return response()->json([
            'ok' => true,
            'message' => $layer->is_active ? 'Layer berhasil diaktifkan.' : 'Layer dinonaktifkan.',
            'data' => $layer,
        ]);
    }

    /**
     * Batch reorder layers.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'uuid', 'exists:layers,id'],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Layer::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Urutan layer berhasil disimpan.',
        ]);
    }

    /**
     * Public / Authenticated active layers feed for Map Viewer.
     */
    public function activeLayers(): JsonResponse
    {
        $layers = Layer::active()->ordered()->get();

        return response()->json([
            'ok' => true,
            'data' => $layers,
        ]);
    }
}

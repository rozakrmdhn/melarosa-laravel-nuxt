<?php

namespace App\Http\Controllers\Gis;

use App\Http\Controllers\Controller;
use App\Models\Layer;
use App\Services\Gis\GeoportalCswClientService;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GeoportalPublicLayerController extends Controller
{
    public function __construct(
        protected GeoportalCswClientService $cswService
    ) {}

    /**
     * Get active geoportal layers grouped by category for map viewer.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $category = $request->query('category');

        $query = Layer::active()->geoportal()->ordered()
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('category', 'ilike', "%{$search}%")
                        ->orWhere('opd_name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($category && $category !== 'ALL', function ($q) use ($category) {
                $q->where('category', $category);
            });

        $layers = $query->get();

        // Unique categories list with counts
        $categories = Layer::active()->geoportal()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $layers,
            'categories' => $categories,
            'total' => $layers->count(),
        ]);
    }

    /**
     * Get detailed metadata for a geoportal layer, including pyCSW catalog records.
     */
    public function metadata(Layer $layer, Request $request): JsonResponse
    {
        $refresh = $request->boolean('refresh');
        $cswData = $this->cswService->fetchPycswRecord($layer, $refresh);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $layer->id,
                'geoportal_id' => $layer->geoportal_id,
                'name' => $layer->name,
                'category' => $layer->category,
                'opd_name' => $layer->opd_name,
                'protocol' => $layer->protocol,
                'url' => $layer->url,
                'layer_name' => $layer->layer_name,
                'legend_url' => $layer->legend_url,
                'description' => $layer->description,
                'attribution' => $layer->attribution,
                'last_synced_at' => $layer->last_synced_at?->toISOString(),
                'csw_metadata' => $layer->csw_metadata,
                'pycsw' => $cswData,
            ],
        ]);
    }

    /**
     * Get layer attributes schema and sample feature rows from WFS.
     */
    public function attributes(Layer $layer, Request $request): JsonResponse
    {
        $refresh = $request->boolean('refresh');
        $attributesData = $this->cswService->fetchLayerAttributes($layer, $refresh);

        return response()->json([
            'ok' => $attributesData['ok'],
            'data' => $attributesData,
        ]);
    }

    /**
     * Download or view raw pyCSW XML metadata record (Dublin Core or ISO 19139).
     */
    public function cswXml(Layer $layer, Request $request): Response|JsonResponse
    {
        $schema = $request->query('schema', 'iso');
        $record = $this->cswService->fetchPycswRecord($layer);

        $xml = ($schema === 'dc') ? ($record['raw_dc_xml'] ?? '') : ($record['raw_iso_xml'] ?? '');

        if (empty($xml)) {
            return response()->json([
                'ok' => false,
                'message' => 'Catatan metadata XML pyCSW tidak ditemukan untuk layer ini.',
            ], 404);
        }

        $filename = ($layer->geoportal_id ?: 'geoportal-layer') . '-' . $schema . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => $request->boolean('download') ? "attachment; filename=\"{$filename}\"" : 'inline',
        ]);
    }

    /**
     * Reverse CORS Proxy for WMS GetFeatureInfo queries.
     */
    public function proxyFeatureInfo(Request $request): Response|JsonResponse
    {
        $targetUrl = $request->query('url');

        if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return response()->json(['ok' => false, 'message' => 'Invalid or missing target URL'], 400);
        }

        $host = parse_url($targetUrl, PHP_URL_HOST);
        $allowedHosts = [
            'geoportal.bojonegorokab.go.id',
            'localhost',
            '127.0.0.1',
        ];

        if (!in_array(strtolower($host), $allowedHosts, true)) {
            return response()->json(['ok' => false, 'message' => 'Host is not in the allowed list'], 403);
        }

        try {
            $client = new Client([
                'verify' => false,
                'timeout' => 10,
            ]);

            // Forward query parameters
            $queryParams = $request->except(['url']);

            $response = $client->get($targetUrl, [
                'query' => $queryParams,
                'headers' => [
                    'Accept' => $request->header('Accept', 'application/json, text/html, */*'),
                ],
            ]);

            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/json';
            $body = (string) $response->getBody();

            return response($body, $response->getStatusCode())
                ->header('Content-Type', $contentType);
        } catch (\Exception $e) {
            Log::warning('WMS GetFeatureInfo proxy failed: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil informasi fitur dari Geoportal: ' . $e->getMessage(),
            ], 502);
        }
    }
}

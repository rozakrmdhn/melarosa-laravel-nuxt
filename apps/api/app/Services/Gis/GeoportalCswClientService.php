<?php

namespace App\Services\Gis;

use App\Models\GeoportalSyncLog;
use App\Models\Layer;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class GeoportalCswClientService
{
    protected Client $httpClient;
    protected string $cswUrl;
    protected string $defaultWmsUrl;

    public function __construct(?Client $client = null)
    {
        $this->httpClient = $client ?? new Client([
            'verify' => false,
            'timeout' => 20,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'MelarosaWebGIS/1.0 (Geoportal-Harvester)',
                'Accept' => 'application/xml, text/xml',
            ],
        ]);

        $this->cswUrl = (string) config('services.geoportal.csw_url', 'https://geoportal.bojonegorokab.go.id/csw/');
        $this->defaultWmsUrl = (string) config('services.geoportal.wms_url', 'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms');
    }

    /**
     * Run full sync from Geoportal CSW to layers table.
     *
     * @param string $triggeredBy 'scheduler' | 'manual_admin' | 'cli'
     * @param int|null $userId
     * @return array
     */
    public function sync(string $triggeredBy = 'manual_admin', ?int $userId = null): array
    {
        $startTime = microtime(true);
        $syncLog = GeoportalSyncLog::create([
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'user_id' => $userId,
        ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $found = 0;

        try {
            $records = $this->harvestAllRecords();
            $found = count($records);

            $maxOrder = (int) Layer::max('order') ?? 0;

            foreach ($records as $record) {
                if (empty($record['geoportal_id']) || empty($record['name'])) {
                    $skipped++;
                    continue;
                }

                $layerName = $record['layer_name'] ?? $record['geoportal_id'];

                $existing = Layer::where('geoportal_id', $record['geoportal_id'])
                    ->orWhere('layer_name', $layerName)
                    ->orWhere('layer_name', $record['geoportal_id'])
                    ->first();

                $layerPayload = [
                    'name' => $record['name'],
                    'geoportal_id' => $record['geoportal_id'],
                    'category' => $record['category'] ?? 'Lainnya',
                    'opd_name' => $record['opd_name'] ?? 'Pemerintah Kabupaten Bojonegoro',
                    'protocol' => 'WMS',
                    'url' => $record['wms_url'] ?: $this->defaultWmsUrl,
                    'legend_url' => $record['legend_url'] ?? null,
                    'layer_name' => $layerName,
                    'attribution' => $record['opd_name'] ?? 'Geoportal Bojonegoro',
                    'description' => $record['abstract'] ?? null,
                    'csw_metadata' => $record['raw'] ?? null,
                    'source_type' => 'geoportal',
                    'is_synced' => true,
                    'last_synced_at' => now(),
                ];

                if ($existing) {
                    $existing->update($layerPayload);
                    $updated++;
                } else {
                    $maxOrder++;
                    $layerPayload['is_active'] = true;
                    $layerPayload['default_visible'] = false;
                    $layerPayload['opacity'] = 0.85;
                    $layerPayload['order'] = $maxOrder;

                    Layer::create($layerPayload);
                    $inserted++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            $syncLog->update([
                'status' => 'success',
                'records_found' => $found,
                'records_inserted' => $inserted,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'duration_seconds' => $duration,
            ]);

            Log::info("Geoportal CSW sync succeeded: {$inserted} inserted, {$updated} updated, {$skipped} skipped in {$duration}s.");

            return [
                'ok' => true,
                'message' => "Sinkronisasi Geoportal berhasil ({$found} katalog ditemukan: {$inserted} baru, {$updated} diperbarui).",
                'summary' => [
                    'found' => $found,
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'duration_seconds' => $duration,
                ],
                'log_id' => $syncLog->id,
            ];
        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $syncLog->update([
                'status' => 'failed',
                'records_found' => $found,
                'records_inserted' => $inserted,
                'records_updated' => $updated,
                'records_skipped' => $skipped,
                'error_message' => $e->getMessage(),
                'duration_seconds' => $duration,
            ]);

            Log::error("Geoportal CSW sync failed: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'ok' => false,
                'message' => 'Gagal menyinkronkan katalog Geoportal: ' . $e->getMessage(),
                'summary' => [
                    'found' => $found,
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'duration_seconds' => $duration,
                ],
                'log_id' => $syncLog->id,
            ];
        }
    }

    /**
     * Harvest all records traversing CSW pages.
     *
     * @return array
     */
    public function harvestAllRecords(): array
    {
        $allRecords = [];
        $startPosition = 1;
        $maxRecordsPerPage = 50;
        $maxSafetyPages = 20;
        $currentPage = 0;

        do {
            $currentPage++;
            $response = $this->httpClient->get($this->cswUrl, [
                'query' => [
                    'service' => 'CSW',
                    'version' => '2.0.2',
                    'request' => 'GetRecords',
                    'resultType' => 'results',
                    'elementSetName' => 'full',
                    'maxRecords' => $maxRecordsPerPage,
                    'startPosition' => $startPosition,
                    'typeNames' => 'csw:Record',
                ],
            ]);

            $xmlString = (string) $response->getBody();
            $parsed = $this->parseCswXml($xmlString);

            foreach ($parsed['records'] as $r) {
                $allRecords[] = $r;
            }

            if ($parsed['nextRecord'] > $startPosition && $parsed['nextRecord'] <= $parsed['matched']) {
                $startPosition = $parsed['nextRecord'];
            } else {
                break;
            }
        } while ($currentPage < $maxSafetyPages && count($allRecords) < $parsed['matched']);

        return $allRecords;
    }

    /**
     * Parse CSW XML response.
     *
     * @param string $xmlString
     * @return array{matched: int, returned: int, nextRecord: int, records: array}
     */
    public function parseCswXml(string $xmlString): array
    {
        $xml = new SimpleXMLElement($xmlString);
        $xml->registerXPathNamespace('csw', 'http://www.opengis.net/cat/csw/2.0.2');
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $xml->registerXPathNamespace('dct', 'http://purl.org/dc/terms/');
        $xml->registerXPathNamespace('ows', 'http://www.opengis.net/ows');

        $searchResults = $xml->xpath('//csw:SearchResults');
        $matched = 0;
        $returned = 0;
        $nextRecord = 0;

        if (!empty($searchResults)) {
            $attrs = $searchResults[0]->attributes();
            $matched = isset($attrs['numberOfRecordsMatched']) ? (int) $attrs['numberOfRecordsMatched'] : 0;
            $returned = isset($attrs['numberOfRecordsReturned']) ? (int) $attrs['numberOfRecordsReturned'] : 0;
            $nextRecord = isset($attrs['nextRecord']) ? (int) $attrs['nextRecord'] : 0;
        }

        $records = [];
        $recordNodes = $xml->xpath('//csw:Record');

        foreach ($recordNodes as $rec) {
            $rec->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $rec->registerXPathNamespace('dct', 'http://purl.org/dc/terms/');

            $idNode = $rec->xpath('dc:identifier');
            $titleNode = $rec->xpath('dc:title');
            $subjectNode = $rec->xpath('dc:subject');
            $abstractNode = $rec->xpath('dct:abstract');
            $publisherNode = $rec->xpath('dc:publisher');
            $creatorNode = $rec->xpath('dc:creator');
            $dateNode = $rec->xpath('dc:date');
            $modifiedNode = $rec->xpath('dct:modified');

            $id = !empty($idNode) ? trim((string) $idNode[0]) : '';
            $title = !empty($titleNode) ? trim((string) $titleNode[0]) : $id;
            $category = !empty($subjectNode) ? trim((string) $subjectNode[0]) : 'Lainnya';
            $abstract = !empty($abstractNode) ? trim((string) $abstractNode[0]) : '';
            $opd = !empty($publisherNode) ? trim((string) $publisherNode[0]) : (!empty($creatorNode) ? trim((string) $creatorNode[0]) : 'Pemerintah Kabupaten Bojonegoro');

            // Find WMS reference
            $wmsUrl = '';
            $refNodes = $rec->xpath('dct:references');
            foreach ($refNodes as $ref) {
                $scheme = (string) ($ref->attributes()['scheme'] ?? '');
                if (stripos($scheme, 'WMS') !== false || stripos((string) $ref, 'wms') !== false) {
                    $wmsUrl = trim((string) $ref);
                    break;
                }
            }

            if (empty($wmsUrl)) {
                $wmsUrl = $this->defaultWmsUrl;
            }

            // Layer name on Geoserver
            $layerName = $id;
            if (stripos($wmsUrl, 'palapa') !== false && !str_starts_with($layerName, 'palapa:')) {
                $layerName = 'palapa:' . $id;
            }

            // Generate official WMS GetLegendGraphic URL
            $legendUrl = rtrim($wmsUrl, '?&') . '?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetLegendGraphic&FORMAT=image/png&LAYER=' . urlencode($layerName);

            $records[] = [
                'geoportal_id' => $id,
                'name' => $title,
                'category' => $category,
                'opd_name' => $opd,
                'abstract' => $abstract,
                'wms_url' => $wmsUrl,
                'layer_name' => $layerName,
                'legend_url' => $legendUrl,
                'date' => !empty($dateNode) ? (string) $dateNode[0] : null,
                'modified' => !empty($modifiedNode) ? (string) $modifiedNode[0] : null,
                'raw' => [
                    'identifier' => $id,
                    'title' => $title,
                    'subject' => $category,
                    'abstract' => $abstract,
                    'publisher' => $opd,
                    'wms_url' => $wmsUrl,
                    'layer_name' => $layerName,
                ],
            ];
        }

        return [
            'matched' => $matched,
            'returned' => $returned,
            'nextRecord' => $nextRecord,
            'records' => $records,
        ];
    }

    /**
     * Fetch layer schema attributes and sample data from WFS.
     */
    public function fetchLayerAttributes(Layer $layer, bool $forceRefresh = false): array
    {
        $cacheKey = "geoportal_attrs_{$layer->id}";
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $wmsUrl = $layer->url ?: $this->defaultWmsUrl;
        $wfsUrl = preg_replace('/\/wms\b/i', '/wfs', $wmsUrl);
        $typeName = $layer->layer_name ?: $layer->geoportal_id;

        if (!str_contains($typeName, ':') && str_contains($wfsUrl, 'palapa')) {
            $typeName = 'palapa:' . $typeName;
        }

        try {
            // 1. DescribeFeatureType (Schema Definition)
            $response = $this->httpClient->get($wfsUrl, [
                'query' => [
                    'service' => 'WFS',
                    'version' => '1.1.0',
                    'request' => 'DescribeFeatureType',
                    'typeName' => $typeName,
                    'outputFormat' => 'application/json',
                ],
                'timeout' => 8,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $properties = [];
            $geomType = null;

            if (!empty($data['featureTypes'][0]['properties'])) {
                foreach ($data['featureTypes'][0]['properties'] as $prop) {
                    $type = $prop['type'] ?? $prop['localType'] ?? 'string';
                    $localType = $prop['localType'] ?? $type;
                    $isGeom = str_contains(strtolower($type), 'gml') ||
                              str_contains(strtolower($type), 'geom') ||
                              in_array(strtolower($localType), ['point', 'multipoint', 'linestring', 'multilinestring', 'polygon', 'multipolygon', 'geometry']);

                    if ($isGeom) {
                        $geomType = $localType;
                    }

                    $properties[] = [
                        'name' => $prop['name'],
                        'type' => $type,
                        'local_type' => $localType,
                        'nillable' => (bool) ($prop['nillable'] ?? true),
                        'is_geometry' => $isGeom,
                    ];
                }
            }

            // 2. Sample Data (WFS GetFeature)
            $sampleRows = [];
            $totalFeatures = 0;
            $detectedFcode = null;
            try {
                $featResponse = $this->httpClient->get($wfsUrl, [
                    'query' => [
                        'service' => 'WFS',
                        'version' => '1.1.0',
                        'request' => 'GetFeature',
                        'typeName' => $typeName,
                        'maxFeatures' => 8,
                        'outputFormat' => 'application/json',
                    ],
                    'timeout' => 8,
                ]);

                $featData = json_decode((string) $featResponse->getBody(), true);
                if (!empty($featData['features']) && is_array($featData['features'])) {
                    $totalFeatures = $featData['totalFeatures'] ?? count($featData['features']);
                    foreach ($featData['features'] as $f) {
                        $props = $f['properties'] ?? [];
                        $sampleRows[] = $props;

                        if (!$detectedFcode) {
                            foreach ($props as $k => $v) {
                                if (strcasecmp((string)$k, 'fcode') === 0 && !empty($v) && is_string($v) && $v !== '-') {
                                    $detectedFcode = trim($v);
                                    break;
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning("WFS GetFeature sample failed for layer {$layer->id}: " . $e->getMessage());
            }

            $result = [
                'ok' => true,
                'wfs_url' => $wfsUrl,
                'type_name' => $typeName,
                'geom_type' => $geomType ?? 'Atribut Non-Spasial / Terkait',
                'detected_fcode' => $detectedFcode,
                'attributes_count' => count($properties),
                'attributes' => $properties,
                'sample_data' => $sampleRows,
                'sample_count' => count($sampleRows),
                'total_features' => $totalFeatures,
                'fetched_at' => now()->toISOString(),
            ];

            Cache::put($cacheKey, $result, now()->addHours(6));

            return $result;
        } catch (Exception $e) {
            Log::warning("WFS DescribeFeatureType failed for layer {$layer->id}: " . $e->getMessage());

            return [
                'ok' => false,
                'message' => 'Layanan WFS layer ini tidak dapat diakses atau bukan layer vektor: ' . $e->getMessage(),
                'wfs_url' => $wfsUrl,
                'type_name' => $typeName,
                'geom_type' => 'Tidak tersedia',
                'attributes_count' => 0,
                'attributes' => [],
                'sample_data' => [],
                'sample_count' => 0,
                'total_features' => 0,
                'fetched_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Fetch complete pyCSW record (Dublin Core and ISO 19139).
     */
    public function fetchPycswRecord(Layer $layer, bool $forceRefresh = false): array
    {
        $cacheKey = "geoportal_csw_{$layer->id}";
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $id = $layer->geoportal_id;
        if (empty($id)) {
            return [
                'ok' => false,
                'message' => 'Layer ini tidak memiliki Geoportal CSW Identifier.',
                'dublin_core' => null,
                'iso_19139' => null,
                'raw_dc_xml' => '',
                'raw_iso_xml' => '',
            ];
        }

        $dcRecord = null;
        $isoRecord = null;
        $rawDcXml = '';
        $rawIsoXml = '';

        // 1. Fetch Dublin Core Record via CSW GetRecordById
        try {
            $dcRes = $this->httpClient->get($this->cswUrl, [
                'query' => [
                    'service' => 'CSW',
                    'version' => '2.0.2',
                    'request' => 'GetRecordById',
                    'id' => $id,
                    'elementSetName' => 'full',
                ],
                'timeout' => 8,
            ]);
            $rawDcXml = (string) $dcRes->getBody();
            $dcRecord = $this->parseDublinCoreXml($rawDcXml);
        } catch (Exception $e) {
            Log::warning("CSW GetRecordById (Dublin Core) failed for {$id}: " . $e->getMessage());
        }

        // 2. Fetch ISO 19139 (GMD) Record via CSW GetRecordById
        try {
            $isoRes = $this->httpClient->get($this->cswUrl, [
                'query' => [
                    'service' => 'CSW',
                    'version' => '2.0.2',
                    'request' => 'GetRecordById',
                    'id' => $id,
                    'elementSetName' => 'full',
                    'outputSchema' => 'http://www.isotc211.org/2005/gmd',
                ],
                'timeout' => 8,
            ]);
            $rawIsoXml = (string) $isoRes->getBody();
            $isoRecord = $this->parseIso19139Xml($rawIsoXml);
        } catch (Exception $e) {
            Log::warning("CSW GetRecordById (ISO 19139) failed for {$id}: " . $e->getMessage());
        }

        $result = [
            'ok' => !empty($dcRecord) || !empty($isoRecord),
            'csw_url' => $this->cswUrl,
            'identifier' => $id,
            'dublin_core' => $dcRecord,
            'iso_19139' => $isoRecord,
            'raw_dc_xml' => $rawDcXml,
            'raw_iso_xml' => $rawIsoXml,
            'fetched_at' => now()->toISOString(),
        ];

        if ($result['ok']) {
            Cache::put($cacheKey, $result, now()->addHours(6));
        }

        return $result;
    }

    /**
     * Parse CSW Dublin Core XML payload.
     */
    public function parseDublinCoreXml(string $xmlString): ?array
    {
        try {
            $xml = new SimpleXMLElement($xmlString);
            $xml->registerXPathNamespace('csw', 'http://www.opengis.net/cat/csw/2.0.2');
            $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $xml->registerXPathNamespace('dct', 'http://purl.org/dc/terms/');
            $xml->registerXPathNamespace('ows', 'http://www.opengis.net/ows');

            $records = $xml->xpath('//csw:Record');
            if (empty($records)) {
                return null;
            }
            $rec = $records[0];
            $rec->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $rec->registerXPathNamespace('dct', 'http://purl.org/dc/terms/');
            $rec->registerXPathNamespace('ows', 'http://www.opengis.net/ows');

            $idNode = $rec->xpath('dc:identifier');
            $titleNode = $rec->xpath('dc:title');
            $typeNode = $rec->xpath('dc:type');
            $subjectNodes = $rec->xpath('dc:subject');
            $abstractNode = $rec->xpath('dct:abstract');
            $publisherNode = $rec->xpath('dc:publisher');
            $creatorNode = $rec->xpath('dc:creator');
            $dateNode = $rec->xpath('dc:date');
            $modifiedNode = $rec->xpath('dct:modified');
            $spatialNode = $rec->xpath('dct:spatial');
            $rightsNode = $rec->xpath('dc:rights');

            // References
            $references = [];
            $refNodes = $rec->xpath('dct:references');
            foreach ($refNodes as $ref) {
                $references[] = [
                    'scheme' => (string) ($ref->attributes()['scheme'] ?? ''),
                    'url' => trim((string) $ref),
                ];
            }

            // Bounding box
            $bbox = null;
            $bboxNode = $rec->xpath('ows:BoundingBox');
            if (!empty($bboxNode)) {
                $b = $bboxNode[0];
                $b->registerXPathNamespace('ows', 'http://www.opengis.net/ows');
                $lower = $b->xpath('ows:LowerCorner');
                $upper = $b->xpath('ows:UpperCorner');
                if (!empty($lower) && !empty($upper)) {
                    $lowerStr = str_replace(',', '.', trim((string) $lower[0]));
                    $upperStr = str_replace(',', '.', trim((string) $upper[0]));
                    $lowerParts = preg_split('/\s+/', $lowerStr);
                    $upperParts = preg_split('/\s+/', $upperStr);
                    if (count($lowerParts) >= 2 && count($upperParts) >= 2) {
                        $w = (float) $lowerParts[0];
                        $s = (float) $lowerParts[1];
                        $e = (float) $upperParts[0];
                        $n = (float) $upperParts[1];
                        $bbox = [
                            'crs' => (string) ($b->attributes()['crs'] ?? 'EPSG:4326'),
                            'west' => $w,
                            'south' => $s,
                            'east' => $e,
                            'north' => $n,
                            'lower_corner' => "{$w} {$s}",
                            'upper_corner' => "{$e} {$n}",
                            'center' => [round(($w + $e) / 2, 7), round(($s + $n) / 2, 7)],
                        ];
                    }
                }
            }

            $subjects = [];
            foreach ($subjectNodes as $sub) {
                $subjects[] = trim((string) $sub);
            }

            return [
                'identifier' => !empty($idNode) ? trim((string) $idNode[0]) : null,
                'title' => !empty($titleNode) ? trim((string) $titleNode[0]) : null,
                'type' => !empty($typeNode) ? trim((string) $typeNode[0]) : 'dataset',
                'subjects' => $subjects,
                'abstract' => !empty($abstractNode) ? trim((string) $abstractNode[0]) : null,
                'publisher' => !empty($publisherNode) ? trim((string) $publisherNode[0]) : null,
                'creator' => !empty($creatorNode) ? trim((string) $creatorNode[0]) : null,
                'date' => !empty($dateNode) ? trim((string) $dateNode[0]) : null,
                'modified' => !empty($modifiedNode) ? trim((string) $modifiedNode[0]) : null,
                'crs' => !empty($spatialNode) ? trim((string) $spatialNode[0]) : 'EPSG:4326',
                'rights' => !empty($rightsNode) ? trim((string) $rightsNode[0]) : 'Pemerintah Kabupaten Bojonegoro (JIGN)',
                'bbox' => $bbox,
                'references' => $references,
            ];
        } catch (Exception $e) {
            Log::warning("Error parsing Dublin Core XML: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse CSW ISO 19139 (GMD) XML payload.
     */
    public function parseIso19139Xml(string $xmlString): ?array
    {
        try {
            $xml = new SimpleXMLElement($xmlString);
            $xml->registerXPathNamespace('gmd', 'http://www.isotc211.org/2005/gmd');
            $xml->registerXPathNamespace('gco', 'http://www.isotc211.org/2005/gco');

            $fileId = $xml->xpath('//gmd:fileIdentifier/gco:CharacterString');
            $dateStamp = $xml->xpath('//gmd:dateStamp/gco:Date | //gmd:dateStamp/gco:DateTime');
            $language = $xml->xpath('//gmd:language/gmd:LanguageCode | //gmd:language/gco:CharacterString');
            $topic = $xml->xpath('//gmd:topicCategory/gmd:MD_TopicCategoryCode');

            $orgName = $xml->xpath('//gmd:pointOfContact//gmd:organisationName/gco:CharacterString');
            $indName = $xml->xpath('//gmd:pointOfContact//gmd:individualName/gco:CharacterString');
            $role = $xml->xpath('//gmd:pointOfContact//gmd:role/gmd:CI_RoleCode');

            // Reference System (CRS)
            $crsCode = $xml->xpath('//gmd:referenceSystemInfo//gmd:code/gco:CharacterString');
            $crsSpace = $xml->xpath('//gmd:referenceSystemInfo//gmd:codeSpace/gco:CharacterString');
            $crs = (!empty($crsSpace) && !empty($crsCode))
                ? trim((string) $crsSpace[0]) . ':' . trim((string) $crsCode[0])
                : 'EPSG:4326';

            // Spatial Resolution / Scale Denominator
            $scaleNode = $xml->xpath('//gmd:spatialResolution//gmd:denominator/gco:Integer');
            $scaleDenominator = !empty($scaleNode) ? (int) trim((string) $scaleNode[0]) : null;

            // Supplemental Information & Lineage
            $suppNode = $xml->xpath('//gmd:supplementalInformation/gco:CharacterString');
            $supplementalInfo = !empty($suppNode) ? trim((string) $suppNode[0]) : null;

            $lineageNode = $xml->xpath('//gmd:lineage//gmd:statement/gco:CharacterString');
            $lineage = !empty($lineageNode) ? trim((string) $lineageNode[0]) : null;

            // BBox (Handling comma decimal separator e.g. 111,445102160 -> 111.445102160)
            $west = $xml->xpath('//gmd:westBoundLongitude/gco:Decimal');
            $east = $xml->xpath('//gmd:eastBoundLongitude/gco:Decimal');
            $south = $xml->xpath('//gmd:southBoundLatitude/gco:Decimal');
            $north = $xml->xpath('//gmd:northBoundLatitude/gco:Decimal');

            $parseCoord = function ($nodes) {
                if (empty($nodes)) return null;
                $val = trim((string) $nodes[0]);
                $val = str_replace(',', '.', $val);
                return is_numeric($val) ? (float) $val : null;
            };

            $westVal = $parseCoord($west);
            $eastVal = $parseCoord($east);
            $southVal = $parseCoord($south);
            $northVal = $parseCoord($north);

            $bbox = null;
            if ($westVal !== null && $eastVal !== null && $southVal !== null && $northVal !== null) {
                $centerLon = round(($westVal + $eastVal) / 2, 7);
                $centerLat = round(($southVal + $northVal) / 2, 7);
                $spanLon = round(abs($eastVal - $westVal), 7);
                $spanLat = round(abs($northVal - $southVal), 7);

                $bbox = [
                    'west' => $westVal,
                    'east' => $eastVal,
                    'south' => $southVal,
                    'north' => $northVal,
                    'crs' => $crs,
                    'center' => [$centerLon, $centerLat],
                    'span' => [
                        'lon' => $spanLon,
                        'lat' => $spanLat,
                    ],
                ];
            }

            return [
                'file_identifier' => !empty($fileId) ? trim((string) $fileId[0]) : null,
                'date_stamp' => !empty($dateStamp) ? trim((string) $dateStamp[0]) : null,
                'language' => !empty($language) ? trim((string) $language[0]) : 'ind',
                'topic_category' => !empty($topic) ? trim((string) $topic[0]) : null,
                'crs' => $crs,
                'scale_denominator' => $scaleDenominator,
                'supplemental_info' => $supplementalInfo,
                'lineage' => $lineage,
                'contact' => [
                    'organisation' => !empty($orgName) ? trim((string) $orgName[0]) : 'Pemerintah Kabupaten Bojonegoro',
                    'individual' => !empty($indName) ? trim((string) $indName[0]) : null,
                    'role' => !empty($role) ? trim((string) $role[0]) : 'pointOfContact',
                ],
                'bbox' => $bbox,
            ];
        } catch (Exception $e) {
            Log::warning("Error parsing ISO 19139 XML: " . $e->getMessage());
            return null;
        }
    }
}


import type { ActiveLayerItem } from "~/types/map-layers";

export interface LayerAttributeProperty {
  name: string;
  type: string;
  local_type: string;
  nillable: boolean;
  is_geometry: boolean;
}

export interface LayerAttributesData {
  ok: boolean;
  message?: string;
  wfs_url: string;
  type_name: string;
  geom_type: string;
  detected_fcode?: string | null;
  attributes_count: number;
  attributes: LayerAttributeProperty[];
  sample_data: Record<string, any>[];
  sample_count: number;
  total_features?: number;
  fetched_at?: string;
}

export interface PycswBbox {
  west?: number;
  east?: number;
  south?: number;
  north?: number;
  crs?: string;
  lower_corner?: string;
  upper_corner?: string;
  center?: [number, number];
  span?: {
    lon: number;
    lat: number;
  };
}

export interface PycswDublinCore {
  identifier: string | null;
  title: string | null;
  type: string | null;
  subjects: string[];
  abstract: string | null;
  publisher: string | null;
  creator: string | null;
  date: string | null;
  modified: string | null;
  crs: string | null;
  rights: string | null;
  bbox?: PycswBbox | null;
  references: Array<{ scheme: string; url: string }>;
}

export interface PycswIso19139 {
  file_identifier: string | null;
  date_stamp: string | null;
  language: string | null;
  topic_category: string | null;
  crs?: string | null;
  scale_denominator?: number | null;
  supplemental_info?: string | null;
  lineage?: string | null;
  contact?: {
    organisation?: string | null;
    individual?: string | null;
    role?: string | null;
  };
  bbox?: PycswBbox | null;
}

export interface PycswMetadataRecord {
  ok: boolean;
  csw_url: string;
  identifier: string;
  dublin_core?: PycswDublinCore | null;
  iso_19139?: PycswIso19139 | null;
  raw_dc_xml?: string;
  raw_iso_xml?: string;
  fetched_at?: string;
}

export interface GeoportalLayerItem {
  id: string;
  name: string;
  protocol: string;
  url: string;
  layer_name: string;
  source_type: string;
  is_active: boolean;
  default_visible: boolean;
  opacity: number;
  order: number;
  attribution: string | null;
  description: string | null;
  geoportal_id: string | null;
  category: string | null;
  opd_name: string | null;
  legend_url: string | null;
  csw_metadata?: Record<string, any> | null;
  pycsw?: PycswMetadataRecord | null;
  last_synced_at?: string | null;
  created_at?: string;
  updated_at?: string;
  title?: string;
  abstract?: string;
  agency?: string;
}

export interface GeoportalSyncTimelineItem {
  date: string;
  date_label: string;
  total_layers: number;
  added: number;
  removed: number;
  updated: number;
  net_change: number;
  status: string;
}

export interface GeoportalConnectionStatus {
  ok: boolean;
  connected: boolean;
  status: "online" | "fallback" | "offline";
  message: string;
  node_name: string;
  csw_url: string;
  remote_matched?: number | null;
  total_local_layers: number;
  active_local_layers: number;
  last_synced_at: string | null;
  response_time_ms?: number | null;
  error_detail?: string | null;
  checked_at: string;
  last_sync_log?: {
    id: string;
    status: string;
    records_found: number;
    records_inserted: number;
    records_updated: number;
    duration_seconds: number;
    created_at: string;
  } | null;
  sync_timeline?: GeoportalSyncTimelineItem[];
}

export function useGeoportalStatus() {
  const {
    data: statusResponse,
    status: requestStatus,
    refresh,
    error,
  } = useHttp<{ ok: boolean; data: GeoportalConnectionStatus }>("layers/geoportal/status", {
    key: "melarosa-geoportal-status",
    lazy: true,
  });

  const connectionStatus = computed<GeoportalConnectionStatus | null>(
    () => statusResponse.value?.data || null
  );

  const checking = computed(() => requestStatus.value === "pending");

  async function checkConnection(forceRefresh = true) {
    if (forceRefresh) {
      try {
        const res = await $http<{ ok: boolean; data: GeoportalConnectionStatus }>("layers/geoportal/status", {
          query: { refresh: 1 },
        });
        if (res?.data && statusResponse.value) {
          statusResponse.value.data = res.data;
        }
        return res?.data;
      } catch {
        await refresh();
      }
    } else {
      await refresh();
    }
  }

  return {
    connectionStatus,
    checking,
    error,
    refresh,
    checkConnection,
  };
}

export type GeoportalGroupedMap = Record<string, GeoportalLayerItem[]>;

export function useGeoportalLayers() {
  const searchQuery = ref("");
  const selectedCategory = ref<string | null>("ALL");
  const {
    connectionStatus,
    checking: checkingStatus,
    checkConnection,
  } = useGeoportalStatus();

  const {
    data: rawData,
    status,
    refresh,
    error,
  } = useHttp<{
    ok: boolean;
    data: GeoportalLayerItem[] | GeoportalGroupedMap;
    categories?: Array<{ category: string; count: number }>;
    total?: number;
  }>("layers/geoportal", {
    key: "melarosa-geoportal-layers-grouped",
    lazy: false,
    dedupe: "defer",
  });

  const loading = computed(() => status.value === "pending");

  const allLayers = computed<GeoportalLayerItem[]>(() => {
    const d = rawData.value?.data;
    if (Array.isArray(d)) return d;
    if (d && typeof d === "object") {
      return Object.values(d).flat();
    }
    return [];
  });

  const groupedLayers = computed<GeoportalGroupedMap>(() => {
    const d = rawData.value?.data;
    if (d && typeof d === "object" && !Array.isArray(d)) {
      return d as GeoportalGroupedMap;
    }
    const list = allLayers.value;
    const map: GeoportalGroupedMap = {};
    for (const item of list) {
      const cat = (item.category || "Lainnya").trim();
      if (!map[cat]) {
        map[cat] = [];
      }
      map[cat].push(item);
    }
    return map;
  });

  const categories = computed<Array<{ name: string; count: number }>>(() => {
    const list: Array<{ name: string; count: number }> = [];
    const map = groupedLayers.value;
    for (const [name, items] of Object.entries(map)) {
      list.push({
        name,
        count: Array.isArray(items) ? items.length : 0,
      });
    }
    return list.sort((a, b) => b.count - a.count);
  });

  const filteredGrouped = computed<GeoportalGroupedMap>(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const cat = selectedCategory.value;
    const result: GeoportalGroupedMap = {};

    for (const [groupName, items] of Object.entries(groupedLayers.value)) {
      if (cat && cat !== "ALL" && groupName !== cat) continue;

      const filteredItems = (items || []).filter((item) => {
        if (!q) return true;
        return (
          item.name?.toLowerCase().includes(q) ||
          item.layer_name?.toLowerCase().includes(q) ||
          item.description?.toLowerCase().includes(q) ||
          item.opd_name?.toLowerCase().includes(q) ||
          item.attribution?.toLowerCase().includes(q) ||
          groupName.toLowerCase().includes(q)
        );
      });

      if (filteredItems.length > 0) {
        result[groupName] = filteredItems;
      }
    }

    return result;
  });

  const totalFilteredCount = computed(() => {
    let count = 0;
    for (const items of Object.values(filteredGrouped.value)) {
      count += items.length;
    }
    return count;
  });

  function toActiveLayerItem(
    layer: GeoportalLayerItem,
    currentOpacity: number = 0.85
  ): ActiveLayerItem {
    return {
      id: layer.id,
      title: layer.name,
      name: layer.name,
      layer_name: layer.layer_name,
      protocol: "WMS",
      url: layer.url,
      attribution: layer.opd_name || layer.attribution || "Geoportal Bojonegoro",
      instansi: layer.opd_name || layer.attribution || "Geoportal Bojonegoro",
      description: layer.description,
      source_type: "geoportal",
      visible: true,
      opacity: typeof layer.opacity === "number" && layer.opacity > 0 ? layer.opacity : currentOpacity,
      expanded: true,
      activeTool: "opacity",
      wmsUrl: layer.url,
      wmsLayerName: layer.layer_name,
      geoportal_id: layer.geoportal_id,
      category: layer.category || undefined,
      opd_name: layer.opd_name,
      legend_url: layer.legend_url,
      csw_metadata: layer.csw_metadata,
      last_synced_at: layer.last_synced_at,
    };
  }

  async function fetchLayerMetadata(layerId: string, refresh = false) {
    return await $http<{ ok: boolean; data: any }>(`layers/geoportal/${layerId}/metadata`, {
      query: refresh ? { refresh: 1 } : {},
    });
  }

  async function fetchLayerAttributes(layerId: string, refresh = false) {
    return await $http<{ ok: boolean; data: LayerAttributesData }>(`layers/geoportal/${layerId}/attributes`, {
      query: refresh ? { refresh: 1 } : {},
    });
  }

  return {
    groupedLayers,
    allLayers,
    categories,
    filteredGrouped,
    totalFilteredCount,
    searchQuery,
    selectedCategory,
    loading,
    error,
    refresh,
    connectionStatus,
    checkingStatus,
    checkConnection,
    toActiveLayerItem,
    fetchLayerMetadata,
    fetchLayerAttributes,
  };
}

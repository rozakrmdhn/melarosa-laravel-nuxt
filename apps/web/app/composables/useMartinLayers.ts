export interface MartinConnectionStatus {
  ok: boolean;
  connected: boolean;
  status: "online" | "fallback" | "offline";
  message: string;
  service_name: string;
  martin_url: string;
  remote_sources?: number | null;
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
}

export interface MartinCatalogItem {
  source_id: string;
  suggested_name: string;
  category: string;
  description: string;
  raw_description: string;
  content_type: string;
  geom_type: "line" | "polygon" | "point";
  pg_geom_type?: string | null;
  is_legacy: boolean;
  default_active: boolean;
  url: string;
  fields: Record<string, string>;
  bounds?: number[] | null;
  tilejson_version?: string | null;
  synced: boolean;
  local_layer_id?: string | null;
  local_name?: string | null;
  local_is_active?: boolean | null;
  local_default_visible?: boolean | null;
  local_last_synced_at?: string | null;
}

export function useMartinStatus() {
  const {
    data: statusResponse,
    status: requestStatus,
    refresh,
    error,
  } = useHttp<{ ok: boolean; data: MartinConnectionStatus }>("layers/martin/status", {
    key: "melarosa-martin-status",
    lazy: true,
  });

  const connectionStatus = computed<MartinConnectionStatus | null>(
    () => statusResponse.value?.data || null
  );

  const checking = computed(() => requestStatus.value === "pending");

  async function checkConnection(forceRefresh = true) {
    if (forceRefresh) {
      try {
        const res = await $http<{ ok: boolean; data: MartinConnectionStatus }>("layers/martin/status", {
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


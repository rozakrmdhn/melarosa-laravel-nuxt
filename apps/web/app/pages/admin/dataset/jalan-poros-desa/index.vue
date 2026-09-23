<script setup lang="ts">
import type { SplitterItem } from "@nuxt/ui";
import {
  DEFAULT_SYMBOLOGY,
  type BasemapType,
  type ConsoleLog,
  type DatasetFilterOptions,
  type DesaOption,
  type KecamatanOption,
  type KondisiBreakdown,
  type LayerSymbology,
  type RuasProperties,
  type SelectedFeature,
  type SpatialSummary,
} from "~/types/dataset-editor";
import MapCanvas from "~/components/dataset/jalan-poros-desa/MapCanvas.vue";
import LeftPanel from "~/components/dataset/jalan-poros-desa/LeftPanel.vue";
import RightPanel from "~/components/dataset/jalan-poros-desa/RightPanel.vue";
import BottomPanel from "~/components/dataset/jalan-poros-desa/BottomPanel.vue";
import MobileBottomSheet from "~/components/dataset/jalan-poros-desa/MobileBottomSheet.vue";
import { usePermission } from "~/composables/usePermission";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  middleware: ["auth", "permission"],
  permission: [
    "jalan-poros-desa-view",
    "jalan-poros-desa-manage",
  ],
  fullBleed: true,
  key: (route) => route.path,
});

useSeoMeta({
  title: "Jalan Poros Desa - GIS Editor Spasial",
});

// ─── Runtime Config, Auth, Permissions & Toast ─────────────────────────────────

const config = useRuntimeConfig();
const apiBase = config.public.apiBase || "http://localhost:9000";
const toast = useToast();
const auth = useAuthStore();
const { can, isAdmin } = usePermission();

// Group Permissions
const canView = computed(() =>
  can("jalan-poros-desa-view") ||
  can("jalan-poros-desa-manage")
);
const canCreate = computed(() =>
  can("jalan-poros-desa-create") ||
  can("jalan-poros-desa-manage")
);
const canEdit = computed(() =>
  can("jalan-poros-desa-update") ||
  can("jalan-poros-desa-manage")
);
const canSplit = computed(() =>
  can("jalan-poros-desa-split") ||
  can("jalan-poros-desa-manage")
);
const canDelete = computed(() =>
  can("jalan-poros-desa-delete") ||
  can("jalan-poros-desa-manage")
);

// User Territory Context
const userKecamatanId = computed(() =>
  auth.user?.id_kecamatan != null ? Number(auth.user.id_kecamatan) : null
);
const userDesaId = computed(() =>
  auth.user?.id_desa != null ? Number(auth.user.id_desa) : null
);
const isKecamatanRestricted = computed(() => !isAdmin() && !!userKecamatanId.value);
const isDesaRestricted = computed(() => !isAdmin() && !!userDesaId.value);
const isWilayahRestricted = computed(
  () => !isAdmin() && (isKecamatanRestricted.value || isDesaRestricted.value)
);

// ─── Nuxt UI Splitter & Workspace Layout ──────────────────────────────────────

const SIDE_PANEL_WIDTH = 300;
const SIDE_PANEL_MIN = 280;
const SIDE_PANEL_MAX = 480;

const horizontalSplitterItems = ref<SplitterItem[]>([
  {
    id: "left-panel",
    slot: "left",
    sizeUnit: "px",
    defaultSize: SIDE_PANEL_WIDTH,
    minSize: SIDE_PANEL_MIN,
    maxSize: SIDE_PANEL_MAX,
    collapsible: true,
    collapsedSize: 42,
    class: "h-full",
  },
  {
    id: "map-canvas",
    slot: "map",
    minSize: 25,
    class: "flex flex-1 w-full h-full relative overflow-hidden",
  },
  {
    id: "right-panel",
    slot: "right",
    sizeUnit: "px",
    defaultSize: 42,
    minSize: SIDE_PANEL_MIN,
    maxSize: SIDE_PANEL_MAX,
    collapsible: true,
    collapsedSize: 42,
    class: "h-full",
  },
]);

const verticalSplitterItems = ref<SplitterItem[]>([
  {
    id: "main-workspace",
    slot: "workspace",
    minSize: 30,
    class: "flex flex-1 w-full h-full overflow-hidden",
  },
  {
    id: "bottom-panel",
    slot: "bottom",
    sizeUnit: "px",
    defaultSize: 42,
    minSize: 220,
    maxSize: 550,
    collapsible: true,
    collapsedSize: 40,
    class: "flex flex-col h-full",
  },
]);

const bottomActiveTab = ref<"table" | "console">("table");

// ─── Map & Layer State ────────────────────────────────────────────────────────

const mapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);
const mobileMapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);
const currentBasemap = ref<BasemapType>("osm");

function fitAllBounds() {
  mapCanvasRef.value?.fitBounds();
  mobileMapCanvasRef.value?.fitBounds();
}

function refreshAllVectorTiles() {
  mapCanvasRef.value?.refreshVectorTiles();
  mobileMapCanvasRef.value?.refreshVectorTiles();
}
const layerVisible = ref(true);
const layerOpacity = ref(1);
const mouseCoords = ref("");
const clickedCoordinate = ref<[number, number] | null>(null);

// ─── Custom Symbology State ───────────────────────────────────────────────────

const STORAGE_KEY_SYMBOLOGY = "gis_symbology_jalan_poros";

const layerSymbology = ref<LayerSymbology>({ ...DEFAULT_SYMBOLOGY });

// Load from localStorage on client mount
onMounted(() => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY_SYMBOLOGY);
    if (saved) {
      const parsed = JSON.parse(saved);
      layerSymbology.value = { ...DEFAULT_SYMBOLOGY, ...parsed };
    }
  } catch (err) {
    console.error("Gagal memuat preferensi simbologi:", err);
  }
});

// Persist changes to localStorage
watch(
  layerSymbology,
  (val) => {
    try {
      localStorage.setItem(STORAGE_KEY_SYMBOLOGY, JSON.stringify(val));
    } catch (err) {
      console.error("Gagal menyimpan preferensi simbologi:", err);
    }
  },
  { deep: true }
);

// ─── Console Logs ─────────────────────────────────────────────────────────────

const consoleLogs = ref<ConsoleLog[]>([]);

function addLog(level: "INFO" | "WARN" | "ERROR", message: string) {
  const time = new Date().toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
  consoleLogs.value.push({ time, level, message });
  if (consoleLogs.value.length > 300) {
    consoleLogs.value.shift();
  }
}

// ─── Router & Query Params Synchronization ──────────────────────────────────

const route = useRoute();
const router = useRouter();

// ─── Filter States (Initialized from URL query params) ──────────────────────

const tableKecamatan = ref<string | null>(
  (route.query.kecamatan as string) || null
);
const tableDesa = ref<string | null>(
  (route.query.desa as string) || null
);
const tableKondisi = ref<string | null>(
  (route.query.kondisi as string) || null
);
const tablePerkerasan = ref<string | null>(
  (route.query.perkerasan as string) || null
);
const tableSearch = ref<string>(
  (route.query.search as string) || ""
);
const tablePage = ref<number>(
  Math.max(1, Number(route.query.page) || 1)
);
const tablePerPage = ref<number>(
  Math.max(10, Math.min(100, Number(route.query.per_page) || 15))
);

// Track whether bottom panel is open (non-collapsed); used for on-demand fetch
const bottomPanelOpen = ref(false);

// ─── Mobile Drawer & Action Dock State ────────────────────────────────────────
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<"layer" | "table" | "inspector" | "filter">("layer");

const activeFilterCount = computed(() => {
  let count = 0;
  if (tableKecamatan.value) count++;
  if (tableDesa.value) count++;
  if (tableKondisi.value) count++;
  if (tablePerkerasan.value) count++;
  return count;
});

function syncQueryToUrl() {
  const query: Record<string, string> = {};
  if (tableKecamatan.value) query.kecamatan = tableKecamatan.value;
  if (tableDesa.value) query.desa = tableDesa.value;
  if (tableKondisi.value) query.kondisi = tableKondisi.value;
  if (tablePerkerasan.value) query.perkerasan = tablePerkerasan.value;
  if (tableSearch.value) query.search = tableSearch.value;
  if (tablePage.value > 1) query.page = String(tablePage.value);
  if (tablePerPage.value !== 15) query.per_page = String(tablePerPage.value);

  const currentQuery = { ...route.query };
  const keysA = Object.keys(query);
  const keysB = Object.keys(currentQuery);
  const isDifferent =
    keysA.length !== keysB.length ||
    keysA.some((k) => query[k] !== currentQuery[k]);

  if (isDifferent) {
    router.replace({ query });
  }
}

// Guard: suppress page-watcher's refreshTable() when page is reset programmatically
let programmaticPageReset = false;

// Watch filters: reset page, sync URL, refresh once
watch([tableKecamatan, tableDesa, tableKondisi, tablePerkerasan], () => {
  programmaticPageReset = true;
  tablePage.value = 1;
  programmaticPageReset = false;
  syncQueryToUrl();
  refreshTable();
});

// Page navigation: sync URL + refresh (skipped when triggered by programmatic page reset)
watch(tablePage, () => {
  syncQueryToUrl();
  if (!programmaticPageReset) refreshTable();
}, { flush: 'sync' });

let searchDebounceTimer: any = null;
watch(tableSearch, () => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    programmaticPageReset = true;
    tablePage.value = 1;
    programmaticPageReset = false;
    syncQueryToUrl();
    refreshTable();
  }, 300);
});

// Sync from URL back to refs when browser navigation (back/forward) occurs
watch(
  () => route.query,
  (newQ) => {
    tableKecamatan.value = (newQ.kecamatan as string) || null;
    tableDesa.value = (newQ.desa as string) || null;
    tableKondisi.value = (newQ.kondisi as string) || null;
    tablePerkerasan.value = (newQ.perkerasan as string) || null;
    tableSearch.value = (newQ.search as string) || "";
    tablePage.value = Math.max(1, Number(newQ.page) || 1);
    tablePerPage.value = Math.max(10, Math.min(100, Number(newQ.per_page) || 15));
  }
);

// Per-page change: reset page + sync URL + refresh once
watch(tablePerPage, () => {
  programmaticPageReset = true;
  tablePage.value = 1;
  programmaticPageReset = false;
  syncQueryToUrl();
  refreshTable();
});

// ─── Data Fetching ────────────────────────────────────────────────────────────

// Fetch Master Options (Kecamatan & Desa)
const { data: filterOptionsResponse } = useHttp<DatasetFilterOptions>(
  "admin/jalan-poros-desa",
  { query: { format: "options" }, lazy: true }
);

const kecamatanOptions = computed<KecamatanOption[]>(
  () => filterOptionsResponse.value?.kecamatan || []
);
const desaOptions = computed<DesaOption[]>(
  () => filterOptionsResponse.value?.desa || []
);

const userKecamatanName = computed(() => {
  if (!userKecamatanId.value) return null;
  const match = kecamatanOptions.value.find((k) => k.id === userKecamatanId.value);
  return match?.nama || (auth.user as any)?.kecamatan?.nama || null;
});

const userDesaName = computed(() => {
  if (!userDesaId.value) return null;
  const match = desaOptions.value.find((d) => d.id === userDesaId.value);
  return match?.nama || (auth.user as any)?.desa?.nama || null;
});

// ─── Feature Authorization Helpers ────────────────────────────────────────────

function canEditFeature(feature: any): boolean {
  if (!canEdit.value || !feature) return false;
  if (isAdmin()) return true;

  const props = feature.properties || feature;
  const fDesaId = props.id_desa != null ? Number(props.id_desa) : null;
  const fKecId = props.id_kecamatan != null ? Number(props.id_kecamatan) : null;

  if (isDesaRestricted.value) {
    if (fDesaId && Number(userDesaId.value)) {
      return fDesaId === Number(userDesaId.value);
    }
    if (props.desa && userDesaName.value) {
      return props.desa.trim().toLowerCase() === userDesaName.value.trim().toLowerCase();
    }
    return false;
  }

  if (isKecamatanRestricted.value) {
    if (fKecId && Number(userKecamatanId.value)) {
      return fKecId === Number(userKecamatanId.value);
    }
    if (props.kecamatan && userKecamatanName.value) {
      return props.kecamatan.trim().toLowerCase() === userKecamatanName.value.trim().toLowerCase();
    }
    return false;
  }

  return true;
}

function canDeleteFeature(feature: any): boolean {
  if (!canDelete.value || !feature) return false;
  if (isAdmin()) return true;

  // Operator desa dilarang menghapus sesuai aturan
  if (isDesaRestricted.value) return false;

  const props = feature.properties || feature;
  const fKecId = props.id_kecamatan != null ? Number(props.id_kecamatan) : null;

  if (isKecamatanRestricted.value) {
    if (fKecId && Number(userKecamatanId.value)) {
      return fKecId === Number(userKecamatanId.value);
    }
    if (props.kecamatan && userKecamatanName.value) {
      return props.kecamatan.trim().toLowerCase() === userKecamatanName.value.trim().toLowerCase();
    }
    return false;
  }

  return true;
}

function canSplitFeature(feature: any): boolean {
  if (!canSplit.value || !feature) return false;
  if (isAdmin()) return true;

  const props = feature.properties || feature;
  const fKecId = props.id_kecamatan != null ? Number(props.id_kecamatan) : null;
  const fDesaId = props.id_desa != null ? Number(props.id_desa) : null;

  if (isDesaRestricted.value) {
    if (fDesaId && Number(userDesaId.value)) {
      return fDesaId === Number(userDesaId.value);
    }
    if (props.desa && userDesaName.value) {
      return props.desa.trim().toLowerCase() === userDesaName.value.trim().toLowerCase();
    }
    return false;
  }

  if (isKecamatanRestricted.value) {
    if (fKecId && Number(userKecamatanId.value)) {
      return fKecId === Number(userKecamatanId.value);
    }
    if (props.kecamatan && userKecamatanName.value) {
      return props.kecamatan.trim().toLowerCase() === userKecamatanName.value.trim().toLowerCase();
    }
    return false;
  }

  return true;
}

// ─── Default initial filter kecamatan & desa ke wilayah kerja user ───────────

let hasInitializedTerritoryFilter = false;

watch(
  [userKecamatanId, kecamatanOptions, userDesaId, desaOptions],
  ([newKecId, kOptions, newDesaId, dOptions]) => {
    if (hasInitializedTerritoryFilter) return;

    let applied = false;
    if (newKecId && kOptions.length > 0 && !tableKecamatan.value) {
      const matchKec = kOptions.find((k) => k.id === newKecId);
      if (matchKec) {
        tableKecamatan.value = matchKec.nama;
        applied = true;
      }
    }

    if (newDesaId && dOptions.length > 0 && !tableDesa.value) {
      const matchDesa = dOptions.find((d) => d.id === newDesaId);
      if (matchDesa) {
        tableDesa.value = matchDesa.nama;
        applied = true;
      }
    }

    if (applied || (kOptions.length > 0 && dOptions.length > 0)) {
      hasInitializedTerritoryFilter = true;
    }
  },
  { immediate: true }
);

// Fetch Spatial Summary (stats & bbox filtered)
const { data: summaryData, status: summaryStatus, refresh: refreshSummary } = useHttp<SpatialSummary>(
  "admin/jalan-poros-desa",
  {
    query: computed(() => ({
      format: "summary",
      kecamatan: tableKecamatan.value || undefined,
      desa: tableDesa.value || undefined,
      kondisi: tableKondisi.value || undefined,
      perkerasan: tablePerkerasan.value || undefined,
      search: tableSearch.value || undefined,
    })),
  }
);

// Fetch Paginated Table Data (dimuat otomatis secara default saat inisialisasi)
const { data: tableResponse, status: tableStatus, refresh: refreshTable } = useHttp<{
  ok: boolean;
  data: any[];
  total: number;
  current_page: number;
  last_page: number;
}>("admin/jalan-poros-desa", {
  query: computed(() => ({
    format: "table",
    per_page: tablePerPage.value,
    page: tablePage.value,
    search: tableSearch.value || undefined,
    kondisi: tableKondisi.value || undefined,
    perkerasan: tablePerkerasan.value || undefined,
    kecamatan: tableKecamatan.value || undefined,
    desa: tableDesa.value || undefined,
  })),
  lazy: true,
});

const loading = computed(() => summaryStatus.value === "pending");
const totalRuas = computed(() => summaryData.value?.total_ruas ?? 0);
const totalPanjang = computed(() => {
  const m = summaryData.value?.total_panjang_meter ?? ((summaryData.value?.total_panjang_km ?? 0) * 1000);
  return Math.round(m).toLocaleString("id-ID");
});
const kondisiStats = computed(() => summaryData.value?.kondisi ?? []);
const tableRows = computed(() => tableResponse.value?.data ?? []);
const tableTotal = computed(() => tableResponse.value?.total ?? 0);
const tableLastPage = computed(() => tableResponse.value?.last_page ?? 1);

// ─── Table Active State ───────────────────────────────────────────────────────

const isTableActive = computed(() => {
  return bottomPanelOpen.value || (mobileDrawerOpen.value && mobileDrawerTab.value === "table");
});

// Triggered when bottom panel is toggled open in desktop
function handleBottomPanelExpandChange(isCollapsed: boolean) {
  bottomPanelOpen.value = !isCollapsed;
}

// (Broad watch removed — each trigger now calls refreshTable() exactly once via individual watchers)

// ─── Selected Feature ─────────────────────────────────────────────────────────

const selectedFeature = ref<SelectedFeature | null>(null);

// ─── Right Panel Auto Expand / Collapse on Feature Selection ────────────────

const horizontalSplitterRef = ref<any>(null);
const verticalSplitterRef = ref<any>(null);

const isDraggingHorizontal = ref(false);
const isDraggingVertical = ref(false);

function onHorizontalDragging(_index: number, dragging: boolean) {
  isDraggingHorizontal.value = dragging;
}

function onVerticalDragging(_index: number, dragging: boolean) {
  isDraggingVertical.value = dragging;
}

const rightPanelSlotData = shallowRef<{
  collapsed: boolean;
  collapse: () => void;
  expand: () => void;
  resize: (size: number) => void;
} | null>(null);

function syncRightPanelActions(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void
) {
  rightPanelSlotData.value = { collapsed, collapse, expand, resize };
  return "";
}

function expandRightPanel() {
  if (rightPanelSlotData.value) {
    if (rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.resize(SIDE_PANEL_WIDTH);
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[2]?.resize?.(SIDE_PANEL_WIDTH);
  }
}

// ─── Toggle All Panels (Focus / Map-Only Mode) ───────────────────────────────

const allPanelsHidden = ref(false);

function toggleAllPanels() {
  if (allPanelsHidden.value) {
    // Restore all panels to default sizes
    horizontalSplitterRef.value?.panelsRef?.[0]?.resize?.(SIDE_PANEL_WIDTH);
    expandRightPanel();
    verticalSplitterRef.value?.panelsRef?.[1]?.resize?.(280);
    allPanelsHidden.value = false;
  } else {
    // Collapse all panels — full-map focus mode
    horizontalSplitterRef.value?.panelsRef?.[0]?.collapse?.();
    collapseRightPanel();
    verticalSplitterRef.value?.panelsRef?.[1]?.collapse?.();
    allPanelsHidden.value = true;
  }
}

function collapseRightPanel() {
  if (rightPanelSlotData.value) {
    if (!rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.collapse();
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[2]?.collapse?.();
  }
}

watch(selectedFeature, (feat) => {
  if (feat) {
    nextTick(() => {
      expandRightPanel();
    });
  } else {
    nextTick(() => {
      collapseRightPanel();
    });
  }
});

function isValidGeometry(g: any): boolean {
  if (!g) return false;
  let parsed = g;
  if (typeof parsed === "string") {
    try {
      parsed = JSON.parse(parsed);
    } catch {
      return false;
    }
  }
  if (!parsed || !parsed.type || !Array.isArray(parsed.coordinates)) return false;
  if (parsed.type === "LineString") {
    return parsed.coordinates.length >= 2;
  }
  if (parsed.type === "MultiLineString") {
    return (
      parsed.coordinates.length > 0 &&
      parsed.coordinates.some((part: any) => Array.isArray(part) && part.length >= 2)
    );
  }
  return false;
}

function normalizeRuasProperties(source: any, fallbackId = ""): RuasProperties {
  const p = source?.properties || source || {};
  const id = String(p.id || source?.id || fallbackId || "");

  let centroid = p.centroid;
  if (typeof centroid === "string") {
    try {
      centroid = JSON.parse(centroid);
    } catch {
      centroid = null;
    }
  }

  // Parse panjang and panjang_meter consistently
  const pMeter = p.panjang_meter != null && p.panjang_meter !== "" ? Number(p.panjang_meter) : null;
  const pManual = p.panjang != null && p.panjang !== "" ? Number(p.panjang) : null;
  const finalPanjangMeter = pMeter ?? pManual ?? 0;
  const finalPanjang = pManual ?? pMeter ?? 0;

  return {
    id,
    kode_ruas: p.kode_ruas != null && p.kode_ruas !== "" ? Number(p.kode_ruas) : null,
    nama_ruas: p.nama_ruas || "-",
    desa: p.desa || null,
    kecamatan: p.kecamatan || null,
    panjang: finalPanjang,
    panjang_meter: finalPanjangMeter,
    lebar: p.lebar != null && p.lebar !== "" ? Number(p.lebar) : null,
    perkerasan: p.perkerasan || null,
    kondisi: p.kondisi || null,
    status_awal: p.status_awal || null,
    status_eksisting: p.status_eksisting || null,
    sumber_data: p.sumber_data || null,
    id_desa: p.id_desa != null && p.id_desa !== "" ? Number(p.id_desa) : null,
    id_kecamatan: p.id_kecamatan != null && p.id_kecamatan !== "" ? Number(p.id_kecamatan) : null,
    centroid,
    created_at: p.created_at,
    updated_at: p.updated_at,
  };
}

async function handleFeatureSelect(feat: SelectedFeature | null) {
  if (!feat?.id) {
    selectedFeature.value = null;
    return;
  }

  const id = String(feat.id);
  const rowMatch = tableRows.value.find((r: any) => String(r.id) === id);

  let rawGeom = feat.geometry || null;
  if (!rawGeom && rowMatch?.geojson) {
    try {
      rawGeom = typeof rowMatch.geojson === "string" ? JSON.parse(rowMatch.geojson) : rowMatch.geojson;
    } catch {
      // keep null
    }
  }

  // 1. Immediately normalize from map click feature and matching table row
  selectedFeature.value = {
    id,
    geometry: rawGeom,
    properties: normalizeRuasProperties({
      ...feat.properties,
      ...(rowMatch || {}),
    }, id),
  };

  // 2. Fetch full detail from API to guarantee 100% complete geometry and attributes
  try {
    const res = await $http<any>(`admin/jalan-poros-desa/${id}`);
    if (res?.ok && selectedFeature.value?.id === id) {
      const apiProps = res.feature?.properties || res.data || {};
      const fullGeom = res.feature?.geometry || (res.data?.geojson ? (typeof res.data.geojson === "string" ? JSON.parse(res.data.geojson) : res.data.geojson) : null);
      
      selectedFeature.value = {
        id,
        geometry: fullGeom || selectedFeature.value.geometry,
        properties: normalizeRuasProperties({
          ...selectedFeature.value.properties,
          ...apiProps,
        }, id),
      };
    }
  } catch {
    // Keep normalized local/MVT attributes if request fails
  }
}

async function handleZoomToFeature(feat: SelectedFeature | any) {
  if (!feat) return;
  const p = feat.properties || feat;
  const id = String(p.id || feat.id);

  // Parse geometry if available
  let rawGeom = feat.geometry || null;
  if (!rawGeom && p.geojson) {
    try {
      rawGeom = typeof p.geojson === "string" ? JSON.parse(p.geojson) : p.geojson;
    } catch {
      // keep null
    }
  }

  // 1. Set normalized selectedFeature state immediately
  selectedFeature.value = {
    id,
    geometry: rawGeom,
    properties: normalizeRuasProperties(p, id),
  };

  addLog("INFO", `Zoom ke: ${selectedFeature.value.properties.nama_ruas}`);

  let didZoom = false;

  // 2. Perform single fitbound to geometry if valid
  if (rawGeom && isValidGeometry(rawGeom)) {
    mapCanvasRef.value?.zoomToGeometry?.(rawGeom);
    mobileMapCanvasRef.value?.zoomToGeometry?.(rawGeom);
    didZoom = true;
  }

  // 3. Fetch from API to guarantee complete geometry & attributes
  try {
    const res = await $http<any>(`admin/jalan-poros-desa/${id}`);
    if (res?.ok && selectedFeature.value?.id === id) {
      const apiProps = res.feature?.properties || res.data || {};
      const fullGeom = res.feature?.geometry || (res.data?.geojson ? (typeof res.data.geojson === "string" ? JSON.parse(res.data.geojson) : res.data.geojson) : null);

      selectedFeature.value = {
        id,
        geometry: fullGeom || selectedFeature.value.geometry,
        properties: normalizeRuasProperties({
          ...selectedFeature.value.properties,
          ...apiProps,
        }, id),
      };

      if (!didZoom) {
        if (fullGeom && isValidGeometry(fullGeom)) {
          mapCanvasRef.value?.zoomToGeometry?.(fullGeom);
          mobileMapCanvasRef.value?.zoomToGeometry?.(fullGeom);
          didZoom = true;
        } else if (selectedFeature.value.properties.centroid) {
          mapCanvasRef.value?.zoomToCentroid(selectedFeature.value.properties.centroid);
          mobileMapCanvasRef.value?.zoomToCentroid(selectedFeature.value.properties.centroid);
          didZoom = true;
        }
      }
    }
  } catch {
    // Keep normalized attributes if request fails
  }

  if (!didZoom && selectedFeature.value.properties.centroid) {
    mapCanvasRef.value?.zoomToCentroid(selectedFeature.value.properties.centroid);
    mobileMapCanvasRef.value?.zoomToCentroid(selectedFeature.value.properties.centroid);
  }
}

function handleViewInTable(feat?: SelectedFeature) {
  bottomActiveTab.value = "table";
  if (feat?.properties?.nama_ruas) {
    tableSearch.value = feat.properties.nama_ruas;
  }
  // Open bottom panel and fetch data if not already open
  if (!bottomPanelOpen.value) {
    bottomPanelOpen.value = true;
    refreshTable();
    // Programmatically expand via the splitter ref
    nextTick(() => {
      verticalSplitterRef.value?.panelsRef?.[1]?.expand?.();
      verticalSplitterRef.value?.panelsRef?.[1]?.resize?.(260);
    });
  }
}

function resetFilters() {
  if (isKecamatanRestricted.value && userKecamatanName.value) {
    tableKecamatan.value = userKecamatanName.value;
  } else {
    tableKecamatan.value = null;
  }

  if (isDesaRestricted.value && userDesaName.value) {
    tableDesa.value = userDesaName.value;
  } else {
    tableDesa.value = null;
  }

  tableKondisi.value = null;
  tablePerkerasan.value = null;
  tableSearch.value = "";
  tablePage.value = 1;
  syncQueryToUrl();
}

// ─── Refresh All ──────────────────────────────────────────────────────

async function handleRefreshAll() {
  const tasks: Promise<any>[] = [refreshSummary()];
  if (bottomPanelOpen.value) tasks.push(refreshTable());
  await Promise.all(tasks);
  refreshAllVectorTiles();
  addLog("INFO", "Data spasial telah disinkronkan.");
  toast.add({
    title: "Data Diperbarui",
    description: "Dataset Jalan Poros Desa telah disinkronkan.",
    color: "success",
  });
}

// ─── Mobile Action Dock Handler ───────────────────────────────────────────────

function openMobileTab(tab: "layer" | "table" | "inspector" | "filter") {
  mobileDrawerTab.value = tab;
  mobileDrawerOpen.value = true;
  if (tab === "table") {
    refreshTable();
  }
}

// ─── Form State & Modals ──────────────────────────────────────────────────────

const isFormOpen = ref(false);
const isEditing = ref(false);
const submitting = ref(false);
const formState = reactive({
  id: "" as string,
  kode_ruas: undefined as number | undefined,
  nama_ruas: "",
  desa: "",
  kecamatan: "",
  panjang: undefined as number | undefined,
  lebar: undefined as number | undefined,
  perkerasan: "",
  kondisi: "",
  status_awal: "",
  status_eksisting: "",
  sumber_data: "",
  id_desa: undefined as number | undefined,
  id_kecamatan: undefined as number | undefined,
  geometry: "",
});

const kondisiOptions = ["Baik", "Sedang", "Rusak", "Rusak Berat"];
const perkerasanOptions = ["Aspal", "Beton", "Kerikil", "Tanah", "Lainnya"];

// ─── Select Menu Options & Cascading Helpers for Form ────────────────────────
const formKecamatanItems = computed(() => {
  return kecamatanOptions.value.map((k) => ({
    label: k.nama,
    value: k.nama,
    id: k.id,
  }));
});

const formSelectedKecamatanObj = computed(() => {
  if (formState.id_kecamatan) {
    const found = kecamatanOptions.value.find((k) => k.id === formState.id_kecamatan);
    if (found) return found;
  }
  if (formState.kecamatan) {
    const target = formState.kecamatan.trim().toLowerCase();
    return kecamatanOptions.value.find((k) => k.nama.toLowerCase() === target) || null;
  }
  return null;
});

const formDesaItems = computed(() => {
  let list = desaOptions.value;
  if (formSelectedKecamatanObj.value) {
    list = list.filter((d) => d.id_kecamatan === formSelectedKecamatanObj.value?.id);
  }
  return list.map((d) => ({
    label: d.nama,
    value: d.nama,
    id: d.id,
    id_kecamatan: d.id_kecamatan,
  }));
});

function handleFormKecamatanChange(val: any) {
  if (isKecamatanRestricted.value || isDesaRestricted.value) return;

  const selectedName = typeof val === "object" && val !== null ? (val.value || val.label || "") : (val ? String(val) : "");
  formState.kecamatan = selectedName;

  const match = kecamatanOptions.value.find(
    (k) => k.nama.toLowerCase() === selectedName.toLowerCase()
  );
  formState.id_kecamatan = match ? match.id : undefined;

  // Jika desa yang sebelumnya terpilih bukan bagian dari kecamatan ini, reset
  if (formState.desa && match) {
    const exists = desaOptions.value.some(
      (d) => d.id_kecamatan === match.id && d.nama.toLowerCase() === formState.desa.toLowerCase()
    );
    if (!exists) {
      formState.desa = "";
      formState.id_desa = undefined;
    }
  } else if (!selectedName) {
    formState.desa = "";
    formState.id_desa = undefined;
  }
}

function handleFormDesaChange(val: any) {
  if (isDesaRestricted.value) return;

  const selectedName = typeof val === "object" && val !== null ? (val.value || val.label || "") : (val ? String(val) : "");
  formState.desa = selectedName;

  const match = desaOptions.value.find((d) => {
    if (formSelectedKecamatanObj.value) {
      return d.id_kecamatan === formSelectedKecamatanObj.value.id && d.nama.toLowerCase() === selectedName.toLowerCase();
    }
    return d.nama.toLowerCase() === selectedName.toLowerCase();
  });

  if (match) {
    formState.id_desa = match.id;
    // Jika kecamatan belum diisi, otomatis isi dari parent desa
    if (!formState.kecamatan || !formState.id_kecamatan) {
      const parentKec = kecamatanOptions.value.find((k) => k.id === match.id_kecamatan);
      if (parentKec) {
        formState.kecamatan = parentKec.nama;
        formState.id_kecamatan = parentKec.id;
      }
    }
  } else {
    formState.id_desa = undefined;
  }
}

function openCreate() {
  if (!canCreate.value) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda tidak memiliki wewenang untuk menambah data jalan poros desa.",
      color: "error",
    });
    return;
  }

  isEditing.value = false;
  if (mobileDrawerOpen.value) {
    mobileDrawerOpen.value = false;
  }
  mapCanvasRef.value?.startDrawing();
  mobileMapCanvasRef.value?.startDrawing();

  addLog("INFO", "Mode gambar garis ruas jalan diaktifkan.");
}

function handleDrawSaved(payload: { geojson: any; lengthMeters: number }) {
  isSavingSuccess = false;
  if (isEditing.value) {
    formState.panjang = Math.round(payload.lengthMeters);
    formState.geometry = JSON.stringify(payload.geojson, null, 2);
    isFormOpen.value = true;
  } else {
    isEditing.value = false;
    let initKec = tableKecamatan.value || "";
    let initKecId: number | undefined;
    if (isKecamatanRestricted.value && userKecamatanId.value) {
      initKecId = userKecamatanId.value;
      const matchKec = kecamatanOptions.value.find((k) => k.id === initKecId);
      if (matchKec) initKec = matchKec.nama;
    } else if (initKec) {
      const matchKec = kecamatanOptions.value.find((k) => k.nama.toLowerCase() === initKec.toLowerCase());
      if (matchKec) {
        initKec = matchKec.nama;
        initKecId = matchKec.id;
      }
    }

    let initDesa = tableDesa.value || "";
    let initDesaId: number | undefined;
    if (isDesaRestricted.value && userDesaId.value) {
      initDesaId = userDesaId.value;
      const matchDesa = desaOptions.value.find((d) => d.id === initDesaId);
      if (matchDesa) {
        initDesa = matchDesa.nama;
        if (!initKecId && matchDesa.id_kecamatan) {
          initKecId = matchDesa.id_kecamatan;
          const matchKec = kecamatanOptions.value.find((k) => k.id === matchDesa.id_kecamatan);
          if (matchKec) initKec = matchKec.nama;
        }
      }
    } else if (initDesa) {
      const matchDesa = desaOptions.value.find(
        (d) => (initKecId ? d.id_kecamatan === initKecId : true) && d.nama.toLowerCase() === initDesa.toLowerCase()
      );
      if (matchDesa) {
        initDesa = matchDesa.nama;
        initDesaId = matchDesa.id;
      }
    }

    formState.id = "";
    formState.kode_ruas = summaryData.value?.next_kode_ruas || undefined;
    formState.nama_ruas = "";
    formState.desa = initDesa;
    formState.id_desa = initDesaId;
    formState.kecamatan = initKec;
    formState.id_kecamatan = initKecId;
    formState.panjang = Math.round(payload.lengthMeters);
    formState.lebar = undefined;
    formState.perkerasan = undefined as string | undefined;
    formState.kondisi = undefined as string | undefined;
    formState.status_awal = "";
    formState.status_eksisting = "";
    formState.sumber_data = "";
    formState.geometry = JSON.stringify(payload.geojson, null, 2);
    isFormOpen.value = true;
  }
}

let isSavingSuccess = false;

watch(isFormOpen, (isOpen, wasOpen) => {
  if (!isOpen && wasOpen && !isSavingSuccess) {
    addLog("INFO", "Form input dibatalkan. Kembali ke mode gambar/edit ruas jalan.");
  }
});

async function openEdit(feat: any) {
  if (!canEditFeature(feat)) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda hanya boleh mengedit ruas jalan di wilayah wewenang Anda.",
      color: "error",
    });
    return;
  }

  const p = feat.properties || feat;
  const id = String(p.id || feat.id || "");
  if (!id) return;

  // 1. Ambil geometri lengkap jika sudah ada di objek feature
  let geom = feat.geometry || p.geometry;
  if (!geom && p.geojson) {
    try {
      geom = typeof p.geojson === "string" ? JSON.parse(p.geojson) : p.geojson;
    } catch {
      geom = null;
    }
  }

  let completeData: any = { ...p };
  // Ambil data lengkap dari server jika geometri belum ada atau belum valid
  if (!isValidGeometry(geom)) {
    try {
      const res = await $http<any>(`admin/jalan-poros-desa/${id}`);
      if (res?.ok && res.data) {
        completeData = { ...completeData, ...res.data };
        geom = res.feature?.geometry || (res.data.geojson ? (typeof res.data.geojson === "string" ? JSON.parse(res.data.geojson) : res.data.geojson) : null);
      }
    } catch {
      // ignore
    }
  }

  // Sinkronisasi Kecamatan & Desa dengan master options
  let initialKec = completeData.kecamatan || "";
  let initialKecId = completeData.id_kecamatan ? Number(completeData.id_kecamatan) : undefined;
  if (initialKec || initialKecId) {
    const matchedKec = kecamatanOptions.value.find(
      (k) => (initialKecId && k.id === initialKecId) || (initialKec && k.nama.toLowerCase() === initialKec.toLowerCase())
    );
    if (matchedKec) {
      initialKec = matchedKec.nama;
      initialKecId = matchedKec.id;
    }
  }

  let initialDesa = completeData.desa || "";
  let initialDesaId = completeData.id_desa ? Number(completeData.id_desa) : undefined;
  if (initialDesa || initialDesaId) {
    const matchedDesa = desaOptions.value.find(
      (d) => (initialKecId ? d.id_kecamatan === initialKecId : true) &&
             ((initialDesaId && d.id === initialDesaId) || (initialDesa && d.nama.toLowerCase() === initialDesa.toLowerCase()))
    );
    if (matchedDesa) {
      initialDesa = matchedDesa.nama;
      initialDesaId = matchedDesa.id;
      if (!initialKecId && matchedDesa.id_kecamatan) {
        const parentKec = kecamatanOptions.value.find((k) => k.id === matchedDesa.id_kecamatan);
        if (parentKec) {
          initialKec = parentKec.nama;
          initialKecId = parentKec.id;
        }
      }
    }
  }

  // 2. Set form state awal untuk ruas yang diedit
  isEditing.value = true;
  formState.id = id;
  formState.kode_ruas = completeData.kode_ruas != null && completeData.kode_ruas !== "" ? Number(completeData.kode_ruas) : undefined;
  formState.nama_ruas = completeData.nama_ruas || "";
  formState.desa = initialDesa;
  formState.id_desa = initialDesaId;
  formState.kecamatan = initialKec;
  formState.id_kecamatan = initialKecId;
  formState.panjang = completeData.panjang != null && completeData.panjang !== "" ? Number(completeData.panjang) : undefined;
  formState.lebar = completeData.lebar != null && completeData.lebar !== "" ? Number(completeData.lebar) : undefined;
  formState.perkerasan = completeData.perkerasan || undefined;
  formState.kondisi = completeData.kondisi || undefined;
  formState.status_awal = completeData.status_awal || "";
  formState.status_eksisting = completeData.status_eksisting || "";
  formState.sumber_data = completeData.sumber_data || "";
  formState.geometry = JSON.stringify(geom || { type: "LineString", coordinates: [] }, null, 2);

  // 3. Tutup drawer pada mobile jika sedang terbuka
  if (mobileDrawerOpen.value) {
    mobileDrawerOpen.value = false;
  }

  // 4. Masuk ke mode edit geometri di kanvas peta aktif
  const isMobile = typeof window !== "undefined" && window.innerWidth < 1024;
  if (isValidGeometry(geom)) {
    if (isMobile) {
      mobileMapCanvasRef.value?.startEditingGeometry(geom, formState.id);
    } else {
      mapCanvasRef.value?.startEditingGeometry(geom, formState.id);
    }
    addLog("INFO", `Mode edit spasial aktif untuk ruas: ${formState.nama_ruas}`);
  } else {
    if (isMobile) {
      mobileMapCanvasRef.value?.startDrawing();
    } else {
      mapCanvasRef.value?.startDrawing();
    }
    addLog("INFO", `Mode gambar baru untuk ruas: ${formState.nama_ruas}`);
  }
}

async function handleSave() {
  if (isEditing.value && !canEdit.value) {
    toast.add({ title: "Akses Ditolak", description: "Anda tidak memiliki wewenang mengedit data.", color: "error" });
    return;
  }
  if (!isEditing.value && !canCreate.value) {
    toast.add({ title: "Akses Ditolak", description: "Anda tidak memiliki wewenang menambah data.", color: "error" });
    return;
  }

  if (!formState.nama_ruas.trim()) {
    toast.add({ title: "Validasi Gagal", description: "Nama ruas wajib diisi.", color: "error" });
    return;
  }
  let geom: any;
  try {
    geom = JSON.parse(formState.geometry);
  } catch {
    toast.add({
      title: "Format Geometri Salah",
      description: "Geometry harus berupa GeoJSON valid.",
      color: "error",
    });
    return;
  }

  // Kunci data wilayah sesuai wewenang user jika dibatasi
  if (isKecamatanRestricted.value && userKecamatanId.value) {
    formState.id_kecamatan = Number(userKecamatanId.value);
    if (userKecamatanName.value) formState.kecamatan = userKecamatanName.value;
  }
  if (isDesaRestricted.value && userDesaId.value) {
    formState.id_desa = Number(userDesaId.value);
    if (userDesaName.value) formState.desa = userDesaName.value;
  }

  submitting.value = true;
  try {
    const body = {
      kode_ruas: formState.kode_ruas ? Number(formState.kode_ruas) : null,
      nama_ruas: formState.nama_ruas,
      desa: formState.desa || null,
      kecamatan: formState.kecamatan || null,
      panjang: formState.panjang ? Number(formState.panjang) : null,
      lebar: formState.lebar ? Number(formState.lebar) : null,
      perkerasan: formState.perkerasan || null,
      kondisi: formState.kondisi || null,
      status_awal: formState.status_awal || null,
      status_eksisting: formState.status_eksisting || null,
      sumber_data: formState.sumber_data || null,
      id_desa: formState.id_desa ? Number(formState.id_desa) : null,
      id_kecamatan: formState.id_kecamatan ? Number(formState.id_kecamatan) : null,
      geometry: geom,
    };

    if (isEditing.value && formState.id) {
      await $http(`admin/jalan-poros-desa/${formState.id}`, { method: "PUT", body });
      toast.add({ title: "Berhasil", description: "Ruas jalan berhasil diperbarui.", color: "success" });
      addLog("INFO", `Ruas diperbarui: ${formState.nama_ruas}`);
    } else {
      await $http("admin/jalan-poros-desa", { method: "POST", body });
      toast.add({ title: "Berhasil", description: "Ruas jalan baru berhasil disimpan.", color: "success" });
      addLog("INFO", `Ruas baru ditambahkan: ${formState.nama_ruas}`);
    }

    isSavingSuccess = true;
    isFormOpen.value = false;
    mapCanvasRef.value?.stopDrawing(false);
    mapCanvasRef.value?.clearDraw();
    mobileMapCanvasRef.value?.stopDrawing(false);
    mobileMapCanvasRef.value?.clearDraw();
    await handleRefreshAll();
    if (geom && isValidGeometry(geom)) {
      mapCanvasRef.value?.zoomToGeometry?.(geom);
      mobileMapCanvasRef.value?.zoomToGeometry?.(geom);
    }
  } catch (err: any) {
    const msg = err?.data?.message || "Terjadi kesalahan saat menyimpan data.";
    toast.add({ title: "Gagal Menyimpan", description: msg, color: "error" });
    addLog("ERROR", `Gagal simpan: ${msg}`);
  } finally {
    submitting.value = false;
  }
}

// ─── Delete State ─────────────────────────────────────────────────────────────

const isDeleteOpen = ref(false);
const featureToDelete = ref<any | null>(null);
const deleting = ref(false);

function confirmDelete(feat: any) {
  if (!canDeleteFeature(feat)) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda tidak memiliki hak menghapus ruas jalan ini.",
      color: "error",
    });
    return;
  }

  featureToDelete.value = feat;
  isDeleteOpen.value = true;
}

async function handleDelete() {
  if (!featureToDelete.value) return;
  deleting.value = true;
  try {
    const id = featureToDelete.value.id || featureToDelete.value.properties?.id;
    await $http(`admin/jalan-poros-desa/${id}`, { method: "DELETE" });
    toast.add({ title: "Terhapus", description: "Ruas jalan berhasil dihapus.", color: "success" });
    addLog("INFO", `Ruas dihapus: ID ${id}`);
    if (selectedFeature.value?.id === String(id)) {
      selectedFeature.value = null;
    }
    isDeleteOpen.value = false;
    await handleRefreshAll();
  } catch (err: any) {
    const msg = err?.data?.message || "Gagal menghapus ruas jalan.";
    toast.add({ title: "Gagal Menghapus", description: msg, color: "error" });
    addLog("ERROR", `Gagal hapus: ${msg}`);
  } finally {
    deleting.value = false;
  }
}

// ─── Split Ruas State & Operations ───────────────────────────────────────────

const isSplitModalOpen = ref(false);
const isSplitting = ref(false);
const splitRoad = ref<any>(null);
const splitPoint = ref<[number, number] | null>(null);
const splitPart1 = reactive({
  nama_ruas: "",
  kode_ruas: undefined as number | undefined,
});
const splitPart2 = reactive({
  nama_ruas: "",
  kode_ruas: undefined as number | undefined,
});

async function handleStartSplit(feat: any) {
  if (!canSplitFeature(feat)) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda tidak diizinkan memotong ruas jalan ini.",
      color: "error",
    });
    return;
  }

  const p = feat.properties || feat;
  const id = String(p.id || feat.id || "");
  if (!id) return;

  let completeData: any = { ...p, id };
  let geom = feat.geometry || p.geometry;
  if (!isValidGeometry(geom)) {
    try {
      const res = await $http<any>(`admin/jalan-poros-desa/${id}`);
      if (res?.ok && res.data) {
        completeData = { ...completeData, ...res.data };
        geom = res.feature?.geometry || (res.data.geojson ? (typeof res.data.geojson === "string" ? JSON.parse(res.data.geojson) : res.data.geojson) : null);
      }
    } catch {
      // ignore
    }
  }
  completeData.geometry = geom;

  if (!isValidGeometry(geom)) {
    toast.add({ title: "Gagal", description: "Ruas jalan tidak memiliki geometri yang valid untuk di-split.", color: "error" });
    return;
  }

  if (mobileDrawerOpen.value) {
    mobileDrawerOpen.value = false;
  }

  mapCanvasRef.value?.startSplitMode(completeData);
  mobileMapCanvasRef.value?.startSplitMode(completeData);
  addLog("INFO", `Mode split aktif untuk ruas: "${completeData.nama_ruas}". Klik pada garis jalan di peta.`);
}

function handleSplitPointSelected(payload: { road: any; coordinate: [number, number] }) {
  splitRoad.value = payload.road;
  splitPoint.value = payload.coordinate;

  const roadName = payload.road.nama_ruas || payload.road.properties?.nama_ruas || "Ruas Jalan";
  const roadKode = payload.road.kode_ruas || payload.road.properties?.kode_ruas;

  splitPart1.nama_ruas = `${roadName} (Bagian 1)`;
  splitPart1.kode_ruas = roadKode;

  splitPart2.nama_ruas = `${roadName} (Bagian 2)`;
  splitPart2.kode_ruas = summaryData.value?.next_kode_ruas || ((Number(roadKode) || 0) + 1);

  isSplitModalOpen.value = true;
}

function handleCancelSplit() {
  isSplitModalOpen.value = false;
  mapCanvasRef.value?.cancelSplitMode();
  mobileMapCanvasRef.value?.cancelSplitMode();
  splitRoad.value = null;
  splitPoint.value = null;
}

async function handleConfirmSplit() {
  if (!splitRoad.value || !splitPoint.value) return;
  if (!splitPart1.nama_ruas.trim() || !splitPart2.nama_ruas.trim()) {
    toast.add({ title: "Validasi Gagal", description: "Nama ruas bagian 1 dan bagian 2 wajib diisi.", color: "error" });
    return;
  }

  isSplitting.value = true;
  try {
    const roadId = splitRoad.value.id || splitRoad.value.properties?.id;
    const res = await $http<any>(`admin/jalan-poros-desa/${roadId}/split`, {
      method: "POST",
      body: {
        point: splitPoint.value,
        part1: { nama_ruas: splitPart1.nama_ruas },
        part2: { nama_ruas: splitPart2.nama_ruas, kode_ruas: splitPart2.kode_ruas },
      },
    });

    if (res?.ok) {
      toast.add({ title: "Berhasil", description: "Ruas jalan berhasil dipecah menjadi 2 bagian.", color: "success" });
      addLog("INFO", `Ruas "${splitRoad.value.nama_ruas || splitRoad.value.properties?.nama_ruas}" dipecah menjadi "${splitPart1.nama_ruas}" dan "${splitPart2.nama_ruas}".`);
      isSplitModalOpen.value = false;
      mapCanvasRef.value?.cancelSplitMode();
      mobileMapCanvasRef.value?.cancelSplitMode();
      splitRoad.value = null;
      splitPoint.value = null;
      await handleRefreshAll();
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || "Gagal memecah ruas jalan.";
    toast.add({ title: "Gagal Split", description: msg, color: "error" });
    addLog("ERROR", `Gagal split: ${msg}`);
  } finally {
    isSplitting.value = false;
  }
}

</script>

<template>
  <div class="flex flex-col w-full h-[calc(100vh-3.5rem)] overflow-hidden bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-gray-100 font-[Inter,sans-serif]">

    <!-- ═══ TOPBAR (Clean Solid Admin Header) ════════════════════════════════ -->
    <header class="flex items-center justify-between h-11 px-3 sm:px-4 border-b border-gray-200 dark:border-gray-800 shrink-0 z-30 bg-white dark:bg-[#0b0f19]">
      <!-- Left: Title & Overview Badges -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <UIcon name="i-lucide-map" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <h1 class="text-xs font-semibold text-gray-800 dark:text-gray-200 capitalize tracking-wide truncate">
            Jalan Poros Desa
          </h1>
        </div>

        <USeparator orientation="vertical" class="h-4 hidden sm:block" />

        <div v-if="!loading" class="flex items-center gap-1 sm:gap-1.5">
          <UBadge
            :label="`${totalRuas} ruas`"
            color="neutral"
            variant="subtle"
            size="xs"
            class="font-mono"
          />
          <UBadge
            :label="`${totalPanjang} m`"
            color="primary"
            variant="subtle"
            size="xs"
            class="font-mono hidden sm:inline-flex"
          />
          <UBadge
            v-if="isDesaRestricted"
            :label="userDesaName ? `Desa ${userDesaName}` : 'Tingkat Desa'"
            color="warning"
            variant="subtle"
            size="xs"
            icon="i-lucide-map-pin"
            class="hidden md:inline-flex"
          />
          <UBadge
            v-else-if="isKecamatanRestricted"
            :label="userKecamatanName ? `Kec. ${userKecamatanName}` : 'Tingkat Kecamatan'"
            color="info"
            variant="subtle"
            size="xs"
            icon="i-lucide-map-pin"
            class="hidden md:inline-flex"
          />
        </div>
        <div v-else class="flex items-center gap-1 sm:gap-1.5">
          <USkeleton class="h-5 w-14 rounded" />
        </div>
      </div>

      <!-- Right: Action Buttons -->
      <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
        <UTooltip text="Sinkronkan Data & Peta">
          <UButton
            icon="i-lucide-refresh-cw"
            size="xs"
            color="neutral"
            variant="ghost"
            :loading="loading"
            class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            @click="handleRefreshAll"
          />
        </UTooltip>

        <UTooltip :text="allPanelsHidden ? 'Tampilkan semua panel' : 'Mode peta penuh'">
          <UButton
            icon="i-lucide-layout-dashboard"
            size="xs"
            color="neutral"
            :variant="allPanelsHidden ? 'subtle' : 'ghost'"
            :class="allPanelsHidden
              ? 'text-emerald-600 dark:text-emerald-400'
              : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="toggleAllPanels"
          />
        </UTooltip>

        <UButton
          v-if="canCreate"
          icon="i-lucide-plus"
          label="Tambah Data"
          size="xs"
          color="primary"
          variant="solid"
          class="hidden sm:inline-flex"
          @click="openCreate"
        />
        <UButton
          v-if="canCreate"
          icon="i-lucide-plus"
          size="xs"
          color="primary"
          variant="solid"
          class="sm:hidden"
          @click="openCreate"
        />
      </div>
    </header>

    <!-- ═══ MAIN WORKSPACE (USplitter with SplitterItem from @nuxt/ui) ═══════ -->
    <div class="flex flex-1 overflow-hidden relative w-full h-full">

      <!-- Desktop View: Nested Two-Tier USplitter (Vertical + Horizontal) -->
      <div class="hidden lg:flex flex-1 w-full h-full overflow-hidden">
        <USplitter
          ref="verticalSplitterRef"
          :items="verticalSplitterItems"
          orientation="vertical"
          class="w-full h-full"
          @dragging="onVerticalDragging"
          :ui="{
            root: 'h-full w-full',
            panel: isDraggingVertical ? '' : 'panel-smooth-transition',
            handle: 'h-1 bg-gray-200 dark:bg-gray-800 hover:bg-emerald-500 active:bg-emerald-600 transition-colors cursor-row-resize'
          }"
        >
          <!-- Workspace: Horizontal Splitter (Left 300px + Map + Right 380px in px units) -->
          <template #workspace>
            <USplitter
              ref="horizontalSplitterRef"
              :items="horizontalSplitterItems"
              orientation="horizontal"
              class="w-full h-full"
              @dragging="onHorizontalDragging"
              :ui="{
                root: 'h-full w-full',
                panel: isDraggingHorizontal ? '' : 'panel-smooth-transition',
                handle: 'w-1 bg-gray-200 dark:bg-gray-800 hover:bg-emerald-500 active:bg-emerald-600 transition-colors cursor-col-resize'
              }"
            >
              <!-- Panel Kiri: Layer & Simbologi -->
              <template #left="{ item, collapsed, collapse, expand, resize }">
                <LeftPanel
                  :collapsed="collapsed"
                  :collapsible="true"
                  v-model:layer-visible="layerVisible"
                  v-model:layer-opacity="layerOpacity"
                  v-model:symbology="layerSymbology"
                  v-model:kecamatan-filter="tableKecamatan"
                  v-model:desa-filter="tableDesa"
                  v-model:kondisi-filter="tableKondisi"
                  v-model:perkerasan-filter="tablePerkerasan"
                  :kecamatan-options="kecamatanOptions"
                  :desa-options="desaOptions"
                  :kondisi-options="kondisiOptions"
                  :perkerasan-options="perkerasanOptions"
                  :total-ruas="summaryData?.total_ruas || tableTotal"
                  :total-panjang-km="summaryData?.total_panjang_km"
                  :kondisi-stats="kondisiStats"
                  :loading="loading"
                  @update:collapsed="(c) => c ? collapse() : (resize ? resize(SIDE_PANEL_WIDTH) : expand())"
                  @zoom-to-layer="fitAllBounds"
                  @reset-filters="resetFilters"
                  @apply-filter="fitAllBounds"
                />
              </template>

              <!-- Map Canvas -->
              <template #map>
                <div class="w-full h-full relative overflow-hidden">
                  <MapCanvas
                    ref="mapCanvasRef"
                    :api-base="apiBase"
                    :selected-feature="selectedFeature"
                    :bbox="summaryData?.bbox"
                    :layer-visible="layerVisible"
                    :layer-opacity="layerOpacity"
                    :symbology="layerSymbology"
                    :clicked-coordinate="clickedCoordinate"
                    :kecamatan-filter="tableKecamatan"
                    :desa-filter="tableDesa"
                    :kondisi-filter="tableKondisi"
                    :perkerasan-filter="tablePerkerasan"
                    v-model:basemap="currentBasemap"
                    v-model:mouse-coords="mouseCoords"
                    @select="handleFeatureSelect"
                    @log="addLog"
                    @draw-saved="handleDrawSaved"
                    @split-point-selected="handleSplitPointSelected"
                    @map-click="(coord) => clickedCoordinate = coord"
                  />
                </div>
              </template>

              <!-- Panel Kanan: Properties / Feature Inspector -->
              <template #right="{ item, collapsed, collapse, expand, resize }">
                <span :class="syncRightPanelActions(collapsed, collapse, expand, resize)" class="hidden" />
                <RightPanel
                  :collapsed="collapsed"
                  :collapsible="true"
                  :selected-feature="selectedFeature"
                  :clicked-coordinate="clickedCoordinate"
                  :loading="loading"
                  :can-edit="canEditFeature(selectedFeature)"
                  :can-split="canSplitFeature(selectedFeature)"
                  :can-delete="canDeleteFeature(selectedFeature)"
                  @update:collapsed="(c) => c ? collapse() : (resize ? resize(SIDE_PANEL_WIDTH) : expand())"
                  @zoom-to-feature="handleZoomToFeature"
                  @edit-feature="openEdit"
                  @split-feature="handleStartSplit"
                  @delete-feature="confirmDelete"
                  @view-in-table="handleViewInTable"
                />
              </template>
            </USplitter>
          </template>

          <!-- Panel Bawah: Tabel Atribut & Konsol -->
          <template #bottom="{ item, collapsed, collapse, expand, resize }">
            <BottomPanel
              :collapsed="collapsed"
              :collapsible="true"
              v-model:active-tab="bottomActiveTab"
              v-model:table-search="tableSearch"
              v-model:table-page="tablePage"
              v-model:table-per-page="tablePerPage"
              v-model:kecamatan-filter="tableKecamatan"
              v-model:desa-filter="tableDesa"
              v-model:kondisi-filter="tableKondisi"
              v-model:perkerasan-filter="tablePerkerasan"
              :table-rows="tableRows"
              :table-total="tableTotal"
              :table-last-page="tableLastPage"
              :table-status="tableStatus"
              :console-logs="consoleLogs"
              :selected-feature-id="selectedFeature?.id"
              :can-edit-row="canEditFeature"
              :can-split-row="canSplitFeature"
              :can-delete-row="canDeleteFeature"
              @update:collapsed="(c) => { c ? collapse() : (resize ? resize(280) : expand()); handleBottomPanelExpandChange(c); }"
              @reset-filters="resetFilters"
              @row-click="handleZoomToFeature"
              @edit-row="openEdit"
              @split-row="handleStartSplit"
              @delete-row="confirmDelete"
              @clear-logs="consoleLogs = []"
            />
          </template>
        </USplitter>
      </div>

      <!-- Mobile View: Full-Bleed Map Canvas -->
      <div class="lg:hidden flex-1 relative overflow-hidden h-full w-full">
        <MapCanvas
          ref="mobileMapCanvasRef"
          :api-base="apiBase"
          :selected-feature="selectedFeature"
          :bbox="summaryData?.bbox"
          :layer-visible="layerVisible"
          :layer-opacity="layerOpacity"
          :symbology="layerSymbology"
          :clicked-coordinate="clickedCoordinate"
          :kecamatan-filter="tableKecamatan"
          :desa-filter="tableDesa"
          :kondisi-filter="tableKondisi"
          :perkerasan-filter="tablePerkerasan"
          v-model:basemap="currentBasemap"
          v-model:mouse-coords="mouseCoords"
          @select="handleFeatureSelect"
          @log="addLog"
          @draw-saved="handleDrawSaved"
          @split-point-selected="handleSplitPointSelected"
          @map-click="(coord) => clickedCoordinate = coord"
        />
      </div>

    </div>

    <!-- ═══ MOBILE FEATURE PEEK CARD (When a road is selected on mobile) ═════ -->
    <div
      v-if="selectedFeature"
      class="lg:hidden fixed bottom-16 left-3 right-3 z-30 pointer-events-auto transition-transform duration-200"
    >
      <div class="p-3 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-xl space-y-2">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate">
              {{ selectedFeature.properties.nama_ruas || 'Ruas Tanpa Nama' }}
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate">
              {{ selectedFeature.properties.desa || '-' }}, Kec. {{ selectedFeature.properties.kecamatan || '-' }}
            </p>
          </div>
          <UButton
            icon="i-lucide-x"
            size="xs"
            color="neutral"
            variant="ghost"
            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-1"
            @click="selectedFeature = null"
          />
        </div>

        <div class="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-gray-800/80 text-xs">
          <div class="flex items-center gap-1.5">
            <UBadge
              :label="selectedFeature.properties.kondisi || '-'"
              color="primary"
              variant="subtle"
              size="xs"
            />
            <span v-if="selectedFeature.properties.panjang_meter || selectedFeature.properties.panjang" class="text-[11px] font-mono text-gray-600 dark:text-gray-400">
              {{ Number(selectedFeature.properties.panjang_meter ?? selectedFeature.properties.panjang).toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m
            </span>
          </div>

          <div class="flex items-center gap-1">
            <UButton
              v-if="canSplitFeature(selectedFeature)"
              icon="i-lucide-scissors"
              size="xs"
              color="neutral"
              variant="subtle"
              title="Split Ruas"
              @click="handleStartSplit(selectedFeature)"
            />
            <UButton
              v-if="canEditFeature(selectedFeature)"
              icon="i-lucide-pencil"
              size="xs"
              color="neutral"
              variant="subtle"
              label="Edit"
              @click="openEdit(selectedFeature)"
            />
            <UButton
              icon="i-lucide-info"
              size="xs"
              color="primary"
              variant="solid"
              label="Detail"
              @click="openMobileTab('inspector')"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ MOBILE FLOATING BOTTOM DOCK (Touch-Friendly 44px min target) ══════ -->
    <nav
      class="lg:hidden fixed bottom-0 left-0 right-0 z-30 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-gray-200 dark:border-gray-800 px-1 py-1 pb-[max(0.5rem,env(safe-area-inset-bottom))] grid items-center select-none"
      :class="canCreate ? 'grid-cols-5' : 'grid-cols-4'"
      aria-label="Navigasi Editor Mobile"
    >
      <!-- 1. Layer -->
      <button
        type="button"
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 rounded-lg transition-colors cursor-pointer"
        :class="[
          mobileDrawerOpen && mobileDrawerTab === 'layer'
            ? 'text-emerald-600 dark:text-emerald-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('layer')"
      >
        <UIcon name="i-lucide-layers" class="size-5" />
        <span class="text-[10px] mt-0.5 leading-none">Layer</span>
      </button>

      <!-- 2. Tabel -->
      <button
        type="button"
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 rounded-lg transition-colors cursor-pointer"
        :class="[
          mobileDrawerOpen && mobileDrawerTab === 'table'
            ? 'text-emerald-600 dark:text-emerald-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('table')"
      >
        <div class="relative">
          <UIcon name="i-lucide-table" class="size-5" />
          <span
            v-if="tableTotal > 0"
            class="absolute -top-1 -right-2.5 px-1 text-[8px] font-mono rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-semibold"
          >
            {{ tableTotal > 99 ? '99+' : tableTotal }}
          </span>
        </div>
        <span class="text-[10px] mt-0.5 leading-none">Tabel</span>
      </button>

      <!-- 3. Tambah (Center Action Button) -->
      <button
        v-if="canCreate"
        type="button"
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors cursor-pointer group"
        @click="openCreate"
      >
        <div class="size-7 rounded-full bg-emerald-600 dark:bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-600/30 group-active:scale-95 transition-transform">
          <UIcon name="i-lucide-plus" class="size-4 stroke-[2.5]" />
        </div>
        <span class="text-[10px] mt-0.5 leading-none font-medium">Tambah</span>
      </button>

      <!-- 4. Properties -->
      <button
        type="button"
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 rounded-lg transition-colors cursor-pointer"
        :class="[
          mobileDrawerOpen && mobileDrawerTab === 'inspector'
            ? 'text-emerald-600 dark:text-emerald-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('inspector')"
      >
        <div class="relative">
          <UIcon name="i-lucide-info" class="size-5" />
          <span
            v-if="selectedFeature"
            class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#0b0f19]"
          />
        </div>
        <span class="text-[10px] mt-0.5 leading-none">Properties</span>
      </button>

      <!-- 5. Filter -->
      <button
        type="button"
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 rounded-lg transition-colors cursor-pointer"
        :class="[
          mobileDrawerOpen && mobileDrawerTab === 'filter'
            ? 'text-emerald-600 dark:text-emerald-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('filter')"
      >
        <div class="relative">
          <UIcon name="i-lucide-filter" class="size-5" />
          <span
            v-if="activeFilterCount > 0"
            class="absolute -top-1 -right-2 px-1 text-[8px] font-mono rounded-full bg-emerald-600 text-white font-bold"
          >
            {{ activeFilterCount }}
          </span>
        </div>
        <span class="text-[10px] mt-0.5 leading-none">Filter</span>
      </button>
    </nav>

    <!-- ═══ MOBILE BOTTOM SHEET (NATIVE-FEEL GIS SHEET) ══════════════════════════ -->
    <MobileBottomSheet
      v-model:open="mobileDrawerOpen"
      :title="
        mobileDrawerTab === 'layer'
          ? 'Layer & Simbologi'
          : mobileDrawerTab === 'table'
            ? 'Tabel Atribut'
            : mobileDrawerTab === 'inspector'
              ? 'Detail Ruas Jalan'
              : 'Filter Data Ruas'
      "
      :icon="
        mobileDrawerTab === 'layer'
          ? 'i-lucide-layers'
          : mobileDrawerTab === 'table'
            ? 'i-lucide-table'
            : mobileDrawerTab === 'inspector'
              ? 'i-lucide-info'
              : 'i-lucide-filter'
      "
    >
      <!-- Quick Tab Switcher inside sheet (4 tabs) -->
      <template #tabs>
        <div class="px-3 pt-1 pb-2 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30">
          <div class="grid grid-cols-4 gap-1 p-1 bg-gray-100 dark:bg-gray-800/70 rounded-xl">
            <!-- Tab 1: Layer -->
            <button
              type="button"
              class="flex items-center justify-center gap-1 py-1.5 px-1 rounded-lg text-xs font-medium transition-all cursor-pointer"
              :class="mobileDrawerTab === 'layer'
                ? 'bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'layer'"
            >
              <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
              <span>Layer</span>
            </button>

            <!-- Tab 2: Tabel -->
            <button
              type="button"
              class="flex items-center justify-center gap-1 py-1.5 px-1 rounded-lg text-xs font-medium transition-all cursor-pointer"
              :class="mobileDrawerTab === 'table'
                ? 'bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'table'; refreshTable()"
            >
              <UIcon name="i-lucide-table" class="size-3.5 shrink-0" />
              <span>Tabel</span>
              <span
                v-if="tableTotal > 0"
                class="px-1 py-0.2 rounded-full text-[9px] font-mono leading-tight"
                :class="mobileDrawerTab === 'table'
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300'
                  : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
              >
                {{ tableTotal > 999 ? (tableTotal / 1000).toFixed(1) + 'k' : tableTotal }}
              </span>
            </button>

            <!-- Tab 3: Detail Properties -->
            <button
              type="button"
              class="flex items-center justify-center gap-1 py-1.5 px-1 rounded-lg text-xs font-medium transition-all relative cursor-pointer"
              :class="mobileDrawerTab === 'inspector'
                ? 'bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'inspector'"
            >
              <UIcon name="i-lucide-info" class="size-3.5 shrink-0" />
              <span>Detail</span>
              <span
                v-if="selectedFeature"
                class="size-1.5 rounded-full bg-emerald-500 shrink-0"
              />
            </button>

            <!-- Tab 4: Filter -->
            <button
              type="button"
              class="flex items-center justify-center gap-1 py-1.5 px-1 rounded-lg text-xs font-medium transition-all relative cursor-pointer"
              :class="mobileDrawerTab === 'filter'
                ? 'bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'filter'"
            >
              <UIcon name="i-lucide-filter" class="size-3.5 shrink-0" />
              <span>Filter</span>
              <span
                v-if="activeFilterCount > 0"
                class="px-1 py-0.2 rounded-full text-[9px] font-mono leading-tight bg-emerald-600 text-white"
              >
                {{ activeFilterCount }}
              </span>
            </button>
          </div>
        </div>
      </template>

      <!-- Sheet Body -->
      <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
        <!-- Layer Tab -->
        <LeftPanel
          v-if="mobileDrawerTab === 'layer'"
          :is-mobile-drawer="true"
          default-tool="none"
          v-model:layer-visible="layerVisible"
          v-model:layer-opacity="layerOpacity"
          v-model:symbology="layerSymbology"
          v-model:kecamatan-filter="tableKecamatan"
          v-model:desa-filter="tableDesa"
          v-model:kondisi-filter="tableKondisi"
          v-model:perkerasan-filter="tablePerkerasan"
          :kecamatan-options="kecamatanOptions"
          :desa-options="desaOptions"
          :kondisi-options="kondisiOptions"
          :perkerasan-options="perkerasanOptions"
          :total-ruas="summaryData?.total_ruas || tableTotal"
          :total-panjang-km="summaryData?.total_panjang_km"
          :kondisi-stats="kondisiStats"
          :loading="loading"
          @zoom-to-layer="mapCanvasRef?.fitBounds(); mobileMapCanvasRef?.fitBounds()"
          @reset-filters="resetFilters"
          @apply-filter="fitAllBounds"
        />

        <!-- Table Tab -->
        <BottomPanel
          v-else-if="mobileDrawerTab === 'table'"
          :is-mobile-drawer="true"
          v-model:active-tab="bottomActiveTab"
          v-model:table-search="tableSearch"
          v-model:table-page="tablePage"
          v-model:table-per-page="tablePerPage"
          v-model:kecamatan-filter="tableKecamatan"
          v-model:desa-filter="tableDesa"
          v-model:kondisi-filter="tableKondisi"
          v-model:perkerasan-filter="tablePerkerasan"
          :table-rows="tableRows"
          :table-total="tableTotal"
          :table-last-page="tableLastPage"
          :table-status="tableStatus"
          :console-logs="consoleLogs"
          :selected-feature-id="selectedFeature?.id"
          :can-edit-row="canEditFeature"
          :can-split-row="canSplitFeature"
          :can-delete-row="canDeleteFeature"
          @reset-filters="resetFilters"
          @row-click="(row) => { handleZoomToFeature(row); mobileDrawerOpen = false; }"
          @edit-row="(row) => { openEdit(row); mobileDrawerOpen = false; }"
          @split-row="(row) => { handleStartSplit(row); mobileDrawerOpen = false; }"
          @delete-row="(row) => { confirmDelete(row); mobileDrawerOpen = false; }"
          @clear-logs="consoleLogs = []"
        />

        <!-- Properties Tab -->
        <RightPanel
          v-else-if="mobileDrawerTab === 'inspector'"
          :is-mobile-drawer="true"
          :selected-feature="selectedFeature"
          :clicked-coordinate="clickedCoordinate"
          :loading="loading"
          :can-edit="canEditFeature(selectedFeature)"
          :can-split="canSplitFeature(selectedFeature)"
          :can-delete="canDeleteFeature(selectedFeature)"
          @zoom-to-feature="(feat) => { handleZoomToFeature(feat); mobileDrawerOpen = false; }"
          @edit-feature="(feat) => { openEdit(feat); mobileDrawerOpen = false; }"
          @split-feature="(feat) => { handleStartSplit(feat); mobileDrawerOpen = false; }"
          @delete-feature="(feat) => { confirmDelete(feat); mobileDrawerOpen = false; }"
          @view-in-table="(feat) => { if (feat) handleViewInTable(feat); mobileDrawerTab = 'table'; refreshTable(); }"
        />

        <!-- Filter Tab (Dedicated Layer Filter) -->
        <LeftPanel
          v-else-if="mobileDrawerTab === 'filter'"
          :is-mobile-drawer="true"
          default-tool="filter"
          v-model:layer-visible="layerVisible"
          v-model:layer-opacity="layerOpacity"
          v-model:symbology="layerSymbology"
          v-model:kecamatan-filter="tableKecamatan"
          v-model:desa-filter="tableDesa"
          v-model:kondisi-filter="tableKondisi"
          v-model:perkerasan-filter="tablePerkerasan"
          :kecamatan-options="kecamatanOptions"
          :desa-options="desaOptions"
          :kondisi-options="kondisiOptions"
          :perkerasan-options="perkerasanOptions"
          :total-ruas="summaryData?.total_ruas || tableTotal"
          :total-panjang-km="summaryData?.total_panjang_km"
          :kondisi-stats="kondisiStats"
          :loading="loading"
          @zoom-to-layer="mapCanvasRef?.fitBounds(); mobileMapCanvasRef?.fitBounds()"
          @reset-filters="resetFilters"
          @apply-filter="fitAllBounds"
        />
      </div>
    </MobileBottomSheet>

    <!-- ═══ MODAL: Tambah / Edit Ruas Jalan (Clean Nuxt UI 4) ═════════════════ -->
    <UModal
      v-model:open="isFormOpen"
      :title="isEditing ? 'Edit Ruas Jalan' : 'Tambah Ruas Jalan'"
      :ui="{ content: 'max-w-2xl w-[calc(100vw-2rem)] max-h-[85vh] overflow-y-auto bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800' }"
    >
      <template #body>
        <div class="space-y-3.5 px-1">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Nama Ruas" required size="sm">
              <UInput
                v-model="formState.nama_ruas"
                placeholder="Contoh: Jl. Poros Desa Mulyorejo"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField
              label="Kode Ruas"
              :description="isEditing ? 'Primary key kode ruas (integer)' : 'Otomatis diisi urutan berikutnya, dapat disesuaikan'"
              size="sm"
            >
              <UInputNumber
                v-model="formState.kode_ruas"
                :placeholder="summaryData?.next_kode_ruas ? `Urutan berikutnya: ${summaryData.next_kode_ruas}` : 'Nomor kode ruas'"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Kecamatan" size="sm">
              <USelectMenu
                :model-value="formState.kecamatan"
                :items="formKecamatanItems"
                value-key="value"
                label-key="label"
                placeholder="Pilih atau cari kecamatan..."
                :disabled="isKecamatanRestricted || isDesaRestricted"
                :ui="{ content: 'z-[100]' }"
                size="sm"
                class="w-full"
                @update:model-value="handleFormKecamatanChange"
              />
            </UFormField>

            <UFormField
              :label="formSelectedKecamatanObj ? `Desa (${formSelectedKecamatanObj.nama})` : 'Desa / Kelurahan'"
              size="sm"
            >
              <USelectMenu
                :model-value="formState.desa"
                :items="formDesaItems"
                value-key="value"
                label-key="label"
                :placeholder="formSelectedKecamatanObj ? `Pilih desa di ${formSelectedKecamatanObj.nama}...` : 'Pilih atau cari desa...'"
                :disabled="isDesaRestricted"
                :ui="{ content: 'z-[100]' }"
                size="sm"
                class="w-full"
                @update:model-value="handleFormDesaChange"
              />
            </UFormField>

            <UFormField label="Panjang (m)" size="sm">
              <UInputNumber
                v-model="formState.panjang"
                :step="1"
                placeholder="Contoh: 2450"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Lebar (m)" size="sm">
              <UInputNumber
                v-model="formState.lebar"
                :step="0.1"
                placeholder="Contoh: 4.5"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Perkerasan" size="sm">
              <USelect
                v-model="formState.perkerasan"
                :items="perkerasanOptions"
                placeholder="Pilih perkerasan"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Kondisi" size="sm">
              <USelect
                v-model="formState.kondisi"
                :items="kondisiOptions"
                placeholder="Pilih kondisi"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Status Awal" size="sm">
              <UInput
                v-model="formState.status_awal"
                placeholder="Contoh: Jalan Desa"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Status Eksisting" size="sm">
              <UInput
                v-model="formState.status_eksisting"
                placeholder="Contoh: Jalan Poros Desa"
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <UFormField label="Sumber Data" size="sm">
            <UInput
              v-model="formState.sumber_data"
              placeholder="Contoh: Survei Lapangan Dinas PUPR"
              size="sm"
              class="w-full"
            />
          </UFormField>

          <UFormField
            label="Geometri (GeoJSON LineString)"
            description="Format koordinat WGS84: [[lon, lat], [lon, lat]]"
            required
            size="sm"
          >
            <UTextarea
              v-model="formState.geometry"
              :rows="4"
              size="sm"
              class="w-full font-mono text-xs"
              placeholder='{ "type": "LineString", "coordinates": [[111.85, -7.15], [111.87, -7.16]] }'
            />
          </UFormField>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isFormOpen = false"
          />
          <UButton
            :label="isEditing ? 'Simpan Perubahan' : 'Simpan Ruas'"
            color="primary"
            variant="solid"
            :loading="submitting"
            @click="handleSave"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: Konfirmasi Hapus ═══════════════════════════════════════════ -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Hapus Ruas Jalan"
      :ui="{ content: 'max-w-sm bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800' }"
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
          Apakah Anda yakin ingin menghapus ruas
          <span class="font-semibold text-gray-900 dark:text-white">
            {{ featureToDelete?.properties?.nama_ruas || featureToDelete?.nama_ruas || 'ini' }}
          </span>?
          Tindakan ini tidak dapat dibatalkan.
        </p>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isDeleteOpen = false"
          />
          <UButton
            label="Hapus Permanen"
            color="error"
            variant="solid"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: Split Ruas Jalan ════════════════════════════════════════════ -->
    <UModal
      v-model:open="isSplitModalOpen"
      title="Split Ruas Jalan"
      :ui="{ content: 'max-w-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800' }"
      @update:open="(val) => { if (!val) handleCancelSplit(); }"
    >
      <template #body>
        <div class="space-y-3.5 text-xs">
          <!-- Info Ruas Asal -->
          <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white">
              <UIcon name="i-lucide-scissors" class="size-4 text-amber-500" />
              <span>Ruas Induk: {{ splitRoad?.nama_ruas || splitRoad?.properties?.nama_ruas }}</span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 text-[11px] mt-0.5">
              Garis ruas akan dipotong pada koordinat [{{ splitPoint?.[0]?.toFixed(5) }}, {{ splitPoint?.[1]?.toFixed(5) }}] menjadi 2 ruas terpisah.
            </p>
          </div>

          <!-- Bagian 1 Card -->
          <div class="p-3 rounded-lg border border-emerald-500/30 bg-emerald-50/20 dark:bg-emerald-950/10 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-emerald-700 dark:text-emerald-300 text-xs">Bagian 1 (Ruas Eksisting)</span>
              <UBadge label="Kode Ruas Tetap" color="primary" variant="subtle" size="xs" />
            </div>
            <UFormField label="Nama Ruas Bagian 1" size="sm" required>
              <UInput v-model="splitPart1.nama_ruas" placeholder="Nama ruas bagian 1" size="sm" class="w-full" />
            </UFormField>
          </div>

          <!-- Bagian 2 Card -->
          <div class="p-3 rounded-lg border border-amber-500/30 bg-amber-50/20 dark:bg-amber-950/10 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-amber-700 dark:text-amber-300 text-xs">Bagian 2 (Ruas Baru)</span>
              <UBadge label="Kode Ruas Baru" color="warning" variant="subtle" size="xs" />
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-2">
                <UFormField label="Nama Ruas Bagian 2" size="sm" required>
                  <UInput v-model="splitPart2.nama_ruas" placeholder="Nama ruas bagian 2" size="sm" class="w-full" />
                </UFormField>
              </div>
              <div>
                <UFormField label="Kode Ruas" size="sm" required>
                  <UInput v-model.number="splitPart2.kode_ruas" type="number" size="sm" class="w-full font-mono" />
                </UFormField>
              </div>
            </div>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton label="Batal" color="neutral" variant="ghost" @click="handleCancelSplit" />
          <UButton
            label="Simpan Pemecahan"
            icon="i-lucide-scissors"
            color="primary"
            variant="solid"
            :loading="isSplitting"
            @click="handleConfirmSplit"
          />
        </div>
      </template>
    </UModal>

  </div>
</template>

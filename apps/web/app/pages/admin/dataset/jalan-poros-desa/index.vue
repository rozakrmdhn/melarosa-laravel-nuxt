<script setup lang="ts">
import type { SplitterItem } from "@nuxt/ui";
import {
  DEFAULT_SYMBOLOGY,
  DEFAULT_JALAN_POROS_SYMBOLOGY,
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

// ─── Responsive Breakpoint (Single MapLibre WebGL Instance) ───────────────────

const windowWidth = ref(import.meta.client ? window.innerWidth : 1280);
const isDesktop = computed(() => windowWidth.value >= 1024);

function handleWindowResize() {
  windowWidth.value = window.innerWidth;
}

onMounted(() => {
  if (import.meta.client) {
    windowWidth.value = window.innerWidth;
    window.addEventListener("resize", handleWindowResize, { passive: true });
  }
});

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener("resize", handleWindowResize);
  }
});

// ─── Nuxt UI Splitter & Workspace Layout ──────────────────────────────────────

const SIDE_PANEL_WIDTH = 300;
const RIGHT_PANEL_WIDTH = 360;
const BOTTOM_PANEL_HEIGHT = 280;
const SIDE_PANEL_MIN = 250;
const SIDE_PANEL_MAX = 480;

const horizontalSplitterItems = ref<SplitterItem[]>([
  {
    id: "left-panel",
    slot: "left",
    sizeUnit: "px",
    defaultSize: 42,
    minSize: SIDE_PANEL_MIN,
    maxSize: SIDE_PANEL_MAX,
    collapsible: true,
    collapsedSize: 42,
    class: "h-full",
  },
  {
    id: "map-canvas",
    slot: "map",
    minSize: 30,
    class: "flex flex-1 w-full h-full relative overflow-hidden",
  },
  {
    id: "right-panel",
    slot: "right",
    sizeUnit: "px",
    defaultSize: 42,
    minSize: 280,
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
    minSize: 32,
    class: "flex flex-1 w-full h-full overflow-hidden",
  },
  {
    id: "bottom-panel",
    slot: "bottom",
    sizeUnit: "px",
    defaultSize: 40,
    minSize: 180,
    maxSize: 520,
    collapsible: true,
    collapsedSize: 40,
    class: "flex flex-col h-full",
  },
]);

const bottomActiveTab = ref<"table" | "console">("table");

// ─── Map & Layer State (MapLibre GL JS Engine) ────────────────────────────────

const mapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);
const mobileMapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);
const { currentBasemap, setBasemapChoice } = useDefaultBasemap<BasemapType>("maplibre");

function scheduleMapResize() {
  nextTick(() => {
    mapCanvasRef.value?.resizeMap?.();
    mobileMapCanvasRef.value?.resizeMap?.();
  });
}

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

const layerSymbology = ref<LayerSymbology>({ ...DEFAULT_JALAN_POROS_SYMBOLOGY });

// Load from localStorage on client mount
onMounted(() => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY_SYMBOLOGY);
    if (saved) {
      const parsed = JSON.parse(saved);
      layerSymbology.value = {
        ...DEFAULT_JALAN_POROS_SYMBOLOGY,
        ...parsed,
        colorMode: parsed.colorMode && parsed.colorMode !== "single" ? parsed.colorMode : "kondisi",
      };
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

// ─── View Mode (Mode Peta vs Mode Tabular) ────────────────────────────────────

const viewMode = ref<"map" | "table">(
  (route.query.mode as "map" | "table") === "table" ? "table" : "map"
);

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
  if (viewMode.value === "table") query.mode = "table";
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

watch(viewMode, (newMode) => {
  syncQueryToUrl();
  if (newMode === "table") {
    refreshTable();
  } else if (newMode === "map") {
    nextTick(() => {
      scheduleMapResize();
      mapCanvasRef.value?.fitBounds();
      mobileMapCanvasRef.value?.fitBounds();
    });
  }
});

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
    if (newQ.mode === "table" || newQ.mode === "map") {
      viewMode.value = newQ.mode;
    }
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
  { key: "jalan-poros-options", query: { format: "options" }, lazy: true }
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
    key: "jalan-poros-summary",
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
  key: "jalan-poros-table",
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
  if (bottomPanelOpen.value && tableRows.value.length === 0) {
    refreshTable();
  }
}

// (Broad watch removed — each trigger now calls refreshTable() exactly once via individual watchers)

// ─── Selected Feature ─────────────────────────────────────────────────────────

const selectedFeature = ref<SelectedFeature | null>(null);

// ─── Right Panel Auto Expand / Collapse on Feature Selection ────────────────

const horizontalSplitterRef = ref<any>(null);
const verticalSplitterRef = ref<any>(null);

const isDraggingHorizontal = ref(false);
const isDraggingVertical = ref(false);
const leftPanelCollapsed = ref(true);
const rightPanelCollapsed = ref(true);
const bottomPanelCollapsed = ref(true);

function onHorizontalDragging(_index: number, dragging: boolean) {
  isDraggingHorizontal.value = dragging;
  if (!dragging) {
    scheduleMapResize();
  }
}

function onVerticalDragging(_index: number, dragging: boolean) {
  isDraggingVertical.value = dragging;
  if (!dragging) {
    scheduleMapResize();
  }
}

interface SplitterSlotCtrl {
  collapsed: boolean;
  collapse: () => void;
  expand: () => void;
  resize: (size: number) => void;
}

const leftPanelSlotData = shallowRef<SplitterSlotCtrl | null>(null);
const rightPanelSlotData = shallowRef<SplitterSlotCtrl | null>(null);
const bottomPanelSlotData = shallowRef<SplitterSlotCtrl | null>(null);

function syncLeftPanelActions(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void
) {
  leftPanelSlotData.value = { collapsed, collapse, expand, resize };
  if (leftPanelCollapsed.value !== collapsed) {
    queueMicrotask(() => {
      leftPanelCollapsed.value = collapsed;
    });
  }
  return "";
}

function syncRightPanelActions(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void
) {
  rightPanelSlotData.value = { collapsed, collapse, expand, resize };
  if (rightPanelCollapsed.value !== collapsed) {
    queueMicrotask(() => {
      rightPanelCollapsed.value = collapsed;
    });
  }
  return "";
}

function syncBottomPanelActions(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void
) {
  bottomPanelSlotData.value = { collapsed, collapse, expand, resize };
  if (bottomPanelCollapsed.value !== collapsed) {
    queueMicrotask(() => {
      bottomPanelCollapsed.value = collapsed;
    });
  }
  return "";
}

function expandRightPanel() {
  if (!isDesktop.value) return;
  if (rightPanelSlotData.value) {
    if (rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.resize(RIGHT_PANEL_WIDTH);
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[2]?.resize?.(RIGHT_PANEL_WIDTH);
  }
  scheduleMapResize();
}

// ─── Toggle All Panels (Focus / Map-Only Mode) ───────────────────────────────

const allPanelsHidden = computed(() =>
  Boolean(leftPanelCollapsed.value && rightPanelCollapsed.value && bottomPanelCollapsed.value)
);

function toggleAllPanels() {
  if (!isDesktop.value) return;
  if (allPanelsHidden.value) {
    leftPanelSlotData.value?.resize(SIDE_PANEL_WIDTH);
    bottomPanelSlotData.value?.resize(BOTTOM_PANEL_HEIGHT);
    handleBottomPanelExpandChange(false);
    if (selectedFeature.value) {
      rightPanelSlotData.value?.resize(RIGHT_PANEL_WIDTH);
    }
  } else {
    leftPanelSlotData.value?.collapse();
    collapseRightPanel();
    bottomPanelSlotData.value?.collapse();
    handleBottomPanelExpandChange(true);
  }
  scheduleMapResize();
}

function collapseRightPanel() {
  if (rightPanelSlotData.value) {
    if (!rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.collapse();
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[2]?.collapse?.();
  }
  scheduleMapResize();
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
  if (bottomPanelOpen.value || viewMode.value === "table") tasks.push(refreshTable());
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
const showRawGeoJson = ref(false);
const formNameError = ref("");
const formGeometryError = ref("");

const formState = reactive({
  id: "" as string,
  kode_ruas: undefined as number | undefined,
  nama_ruas: "",
  desa: "",
  kecamatan: "",
  panjang: undefined as number | undefined,
  lebar: undefined as number | undefined,
  perkerasan: undefined as string | undefined,
  kondisi: undefined as string | undefined,
  status_awal: "",
  status_eksisting: "",
  sumber_data: "",
  id_desa: undefined as number | undefined,
  id_kecamatan: undefined as number | undefined,
  geometry: "",
});

const kondisiOptions = ["Baik", "Sedang", "Rusak", "Rusak Berat"];
const perkerasanOptions = ["Aspal", "Beton", "Kerikil", "Tanah", "Lainnya"];
const statusAwalPresets = ["Jalan Desa", "Jalan Poros Desa", "Jalan Lingkungan"];
const statusEksistingPresets = ["Jalan Poros Desa", "Jalan Kabupaten", "Jalan Desa"];

const kondisiCardMeta: Array<{
  value: string;
  label: string;
  dotClass: string;
  activeClass: string;
}> = [
  {
    value: "Baik",
    label: "Baik",
    dotClass: "bg-emerald-500",
    activeClass:
      "border-emerald-500 bg-emerald-50/90 text-emerald-900 dark:border-emerald-500/80 dark:bg-emerald-950/60 dark:text-emerald-200 ring-1 ring-emerald-500/30",
  },
  {
    value: "Sedang",
    label: "Sedang",
    dotClass: "bg-amber-500",
    activeClass:
      "border-amber-500 bg-amber-50/90 text-amber-900 dark:border-amber-500/80 dark:bg-amber-950/60 dark:text-amber-200 ring-1 ring-amber-500/30",
  },
  {
    value: "Rusak",
    label: "Rusak",
    dotClass: "bg-orange-500",
    activeClass:
      "border-orange-500 bg-orange-50/90 text-orange-900 dark:border-orange-500/80 dark:bg-orange-950/60 dark:text-orange-200 ring-1 ring-orange-500/30",
  },
  {
    value: "Rusak Berat",
    label: "Rusak Berat",
    dotClass: "bg-red-500",
    activeClass:
      "border-red-500 bg-red-50/90 text-red-900 dark:border-red-500/80 dark:bg-red-950/60 dark:text-red-200 ring-1 ring-red-500/30",
  },
];

const parsedFormGeometrySummary = computed(() => {
  if (!formState.geometry) {
    return { valid: false, type: "-", vertexCount: 0, startCoord: null as [number, number] | null, endCoord: null as [number, number] | null };
  }
  try {
    const g = JSON.parse(formState.geometry);
    const geom = g?.type === "Feature" ? g.geometry : g;
    if (!geom || !geom.type || !Array.isArray(geom.coordinates)) {
      return { valid: false, type: "-", vertexCount: 0, startCoord: null, endCoord: null };
    }
    if (geom.type === "LineString") {
      const pts = geom.coordinates.filter((c: any) => Array.isArray(c) && c.length >= 2);
      return {
        valid: pts.length >= 2,
        type: "LineString",
        vertexCount: pts.length,
        startCoord: pts.length > 0 ? [Number(pts[0][0]), Number(pts[0][1])] : null,
        endCoord: pts.length > 1 ? [Number(pts[pts.length - 1][0]), Number(pts[pts.length - 1][1])] : null,
      };
    }
    if (geom.type === "MultiLineString") {
      const flat = geom.coordinates.flat().filter((c: any) => Array.isArray(c) && c.length >= 2);
      return {
        valid: flat.length >= 2,
        type: "MultiLineString",
        vertexCount: flat.length,
        startCoord: flat.length > 0 ? [Number(flat[0][0]), Number(flat[0][1])] : null,
        endCoord: flat.length > 1 ? [Number(flat[flat.length - 1][0]), Number(flat[flat.length - 1][1])] : null,
      };
    }
    return { valid: false, type: String(geom.type), vertexCount: 0, startCoord: null, endCoord: null };
  } catch {
    return { valid: false, type: "Format JSON Tidak Valid", vertexCount: 0, startCoord: null, endCoord: null };
  }
});

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
    if (!isEditing.value && (!formState.nama_ruas.trim() || /^Jl\.\s*Poros\s*Desa\s+/i.test(formState.nama_ruas.trim()))) {
      formState.nama_ruas = `Jl. Poros Desa ${match.nama}`;
      formNameError.value = "";
    }
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

// ─── Mode Tabular Helpers ─────────────────────────────────────────────────────

const tabularDesaOptions = computed(() => {
  if (!tableKecamatan.value) return desaOptions.value;
  const matchKec = kecamatanOptions.value.find(
    (k) => k.nama.toLowerCase() === tableKecamatan.value?.toLowerCase()
  );
  if (!matchKec) return desaOptions.value;
  return desaOptions.value.filter((d) => d.id_kecamatan === matchKec.id);
});

function handleTabularKecamatanChange(val: string | null) {
  if (isKecamatanRestricted.value) return;
  const selected = val && val !== "null" && val !== "" ? val : null;
  tableKecamatan.value = selected;

  if (tableDesa.value && selected) {
    const matchKec = kecamatanOptions.value.find(
      (k) => k.nama.toLowerCase() === selected.toLowerCase()
    );
    if (matchKec) {
      const stillValid = desaOptions.value.some(
        (d) => d.id_kecamatan === matchKec.id && d.nama.toLowerCase() === tableDesa.value?.toLowerCase()
      );
      if (!stillValid) {
        tableDesa.value = null;
      }
    }
  }
}

function handleTabularDesaChange(val: string | null) {
  if (isDesaRestricted.value) return;
  const selected = val && val !== "null" && val !== "" ? val : null;
  tableDesa.value = selected;

  if (selected && !tableKecamatan.value) {
    const matchDesa = desaOptions.value.find(
      (d) => d.nama.toLowerCase() === selected.toLowerCase()
    );
    if (matchDesa) {
      const parentKec = kecamatanOptions.value.find((k) => k.id === matchDesa.id_kecamatan);
      if (parentKec) {
        tableKecamatan.value = parentKec.nama;
      }
    }
  }
}

function getKondisiBadge(kondisi?: string | null) {
  if (!kondisi) return { label: "-", color: "neutral" as const };
  const k = kondisi.toLowerCase();
  if (k.includes("baik")) return { label: kondisi, color: "success" as const };
  if (k.includes("sedang")) return { label: kondisi, color: "warning" as const };
  if (k.includes("rusak berat")) return { label: kondisi, color: "error" as const };
  if (k.includes("rusak")) return { label: kondisi, color: "warning" as const };
  return { label: kondisi, color: "neutral" as const };
}

const tabularPageRangeText = computed(() => {
  if (!tableTotal.value) return "Tidak ada data";
  const start = (tablePage.value - 1) * tablePerPage.value + 1;
  const end = Math.min(tablePage.value * tablePerPage.value, tableTotal.value);
  return `${start.toLocaleString("id-ID")} - ${end.toLocaleString("id-ID")} dari ${tableTotal.value.toLocaleString("id-ID")} data`;
});

function handleViewRowOnMap(row: any) {
  viewMode.value = "map";
  nextTick(() => {
    handleZoomToFeature(row);
  });
}

function handleSplitFromTable(row: any) {
  viewMode.value = "map";
  nextTick(() => {
    handleStartSplit(row);
  });
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
  if (viewMode.value === "table") {
    viewMode.value = "map";
  }
  if (mobileDrawerOpen.value) {
    mobileDrawerOpen.value = false;
  }

  nextTick(() => {
    scheduleMapResize();
    mapCanvasRef.value?.startDrawing();
    mobileMapCanvasRef.value?.startDrawing();
  });

  addLog("INFO", "Mode gambar garis ruas jalan diaktifkan.");
}

function handleDrawSaved(payload: { geojson: any; lengthMeters: number }) {
  isSavingSuccess = false;
  showRawGeoJson.value = false;
  formNameError.value = "";
  formGeometryError.value = "";

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
    formState.nama_ruas = initDesa ? `Jl. Poros Desa ${initDesa}` : "";
    formState.desa = initDesa;
    formState.id_desa = initDesaId;
    formState.kecamatan = initKec;
    formState.id_kecamatan = initKecId;
    formState.panjang = Math.round(payload.lengthMeters);
    formState.lebar = 4;
    formState.perkerasan = "Aspal";
    formState.kondisi = "Baik";
    formState.status_awal = "Jalan Desa";
    formState.status_eksisting = "Jalan Poros Desa";
    formState.sumber_data = "Digitasi Peta GIS";
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

  // 3. Pindah ke tampilan peta & tutup drawer pada mobile jika sedang terbuka
  if (viewMode.value === "table") {
    viewMode.value = "map";
  }
  if (mobileDrawerOpen.value) {
    mobileDrawerOpen.value = false;
  }

  // 4. Masuk ke mode edit geometri di kanvas peta aktif setelah DOM peta siap
  nextTick(() => {
    scheduleMapResize();
    if (isValidGeometry(geom)) {
      mapCanvasRef.value?.startEditingGeometry(geom, formState.id);
      mobileMapCanvasRef.value?.startEditingGeometry(geom, formState.id);
      addLog("INFO", `Mode edit spasial aktif untuk ruas: ${formState.nama_ruas}`);
    } else {
      mapCanvasRef.value?.startDrawing();
      mobileMapCanvasRef.value?.startDrawing();
      addLog("INFO", `Mode gambar baru untuk ruas: ${formState.nama_ruas}`);
    }
  });
}

function resumeMapGeometryEditingFromModal() {
  isFormOpen.value = false;
  if (viewMode.value === "table") {
    viewMode.value = "map";
  }
  nextTick(() => {
    scheduleMapResize();
    let parsedGeom: any = null;
    try {
      parsedGeom = formState.geometry ? JSON.parse(formState.geometry) : null;
    } catch {
      parsedGeom = null;
    }
    if (isValidGeometry(parsedGeom)) {
      mapCanvasRef.value?.startEditingGeometry(parsedGeom, formState.id || undefined);
      mobileMapCanvasRef.value?.startEditingGeometry(parsedGeom, formState.id || undefined);
    } else {
      mapCanvasRef.value?.startDrawing();
      mobileMapCanvasRef.value?.startDrawing();
    }
  });
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

  formNameError.value = "";
  formGeometryError.value = "";

  if (!formState.nama_ruas.trim()) {
    formNameError.value = "Nama ruas jalan wajib diisi sebelum menyimpan.";
    toast.add({ title: "Validasi Gagal", description: "Nama ruas wajib diisi.", color: "error" });
    return;
  }
  let geom: any;
  try {
    geom = JSON.parse(formState.geometry);
    if (!isValidGeometry(geom)) {
      formGeometryError.value = "Geometri harus berupa LineString dengan minimal 2 titik koordinat.";
      showRawGeoJson.value = true;
      toast.add({
        title: "Geometri Belum Lengkap",
        description: "Garis ruas harus memiliki minimal 2 titik koordinat.",
        color: "error",
      });
      return;
    }
  } catch {
    formGeometryError.value = "Format GeoJSON tidak valid. Periksa tanda kurung koordinat.";
    showRawGeoJson.value = true;
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

  if (viewMode.value === "table") {
    viewMode.value = "map";
  }
  if (mobileDrawerOpen.value) {
    mobileDrawerOpen.value = false;
  }

  nextTick(() => {
    scheduleMapResize();
    mapCanvasRef.value?.startSplitMode(completeData);
    mobileMapCanvasRef.value?.startSplitMode(completeData);
  });
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
          <UIcon name="i-lucide-map" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
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

      <!-- Right: Action Buttons & Mode Switcher -->
      <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
        <!-- 2-Pilihan Mode: Peta vs Tabular -->
        <div class="flex items-center p-0.5 rounded-lg bg-gray-100 dark:bg-white/[0.06] border border-gray-200/60 dark:border-white/[0.08]">
          <UButton
            icon="i-lucide-map"
            label="Peta"
            size="xs"
            :color="viewMode === 'map' ? 'primary' : 'neutral'"
            :variant="viewMode === 'map' ? 'solid' : 'ghost'"
            class="text-xs font-medium cursor-pointer"
            @click="viewMode = 'map'"
          />
          <UButton
            icon="i-lucide-table"
            label="Tabular"
            size="xs"
            :color="viewMode === 'table' ? 'primary' : 'neutral'"
            :variant="viewMode === 'table' ? 'solid' : 'ghost'"
            class="text-xs font-medium cursor-pointer"
            @click="viewMode = 'table'"
          />
        </div>

        <USeparator orientation="vertical" class="h-4" />

        <UTooltip text="Sinkronkan Data & Peta">
          <UButton
            icon="i-lucide-refresh-cw"
            size="xs"
            color="neutral"
            variant="ghost"
            :loading="loading || (viewMode === 'table' && tableStatus === 'pending')"
            class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            @click="handleRefreshAll"
          />
        </UTooltip>

        <UTooltip v-if="viewMode === 'map'" :text="allPanelsHidden ? 'Tampilkan semua panel' : 'Mode peta penuh'">
          <UButton
            icon="i-lucide-layout-dashboard"
            size="xs"
            color="neutral"
            :variant="allPanelsHidden ? 'subtle' : 'ghost'"
            :class="allPanelsHidden
              ? 'text-blue-600 dark:text-blue-400'
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

    <!-- ═══ MAIN WORKSPACE (MODE PETA: USplitter with SplitterItem from @nuxt/ui) ═══════ -->
    <div
      v-show="viewMode === 'map'"
      class="flex flex-1 overflow-hidden relative w-full h-full"
    >

      <!-- Desktop View (>= 1024px): Nested Two-Tier USplitter (Jaringan Jalan Reference) -->
      <div v-if="isDesktop" class="flex flex-1 w-full h-full overflow-hidden">
        <USplitter
          ref="verticalSplitterRef"
          :items="verticalSplitterItems"
          orientation="vertical"
          class="w-full h-full"
          @dragging="onVerticalDragging"
          :ui="{
            root: 'h-full w-full',
            panel: isDraggingVertical ? '' : 'panel-smooth-transition',
            handle: 'h-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-row-resize'
          }"
        >
          <!-- Workspace: Horizontal Splitter (Left 300px + MapLibre + Right 360px) -->
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
                handle: 'w-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-col-resize'
              }"
            >
              <!-- Panel Kiri: Layer & Simbologi -->
              <template #left="{ collapsed, collapse, expand, resize }">
                <span :class="syncLeftPanelActions(collapsed, collapse, expand, resize)" class="hidden" />
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
                  @update:collapsed="(c) => { c ? collapse() : (resize ? resize(SIDE_PANEL_WIDTH) : expand()); scheduleMapResize(); }"
                  @zoom-to-layer="fitAllBounds"
                  @reset-filters="resetFilters"
                  @apply-filter="fitAllBounds"
                />
              </template>

              <!-- Center MapLibre GL JS Canvas -->
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
                    :kecamatan-options="kecamatanOptions"
                    :desa-options="desaOptions"
                    :basemap="currentBasemap"
                    @update:basemap="setBasemapChoice($event)"
                    v-model:mouse-coords="mouseCoords"
                    @select="handleFeatureSelect"
                    @log="addLog"
                    @draw-saved="handleDrawSaved"
                    @split-point-selected="handleSplitPointSelected"
                    @map-click="(coord) => clickedCoordinate = coord"
                  />

                  <!-- Floating Reopen Right Panel Button when collapsed with active selection -->
                  <button
                    v-if="selectedFeature && rightPanelCollapsed"
                    type="button"
                    class="absolute bottom-11 right-3 z-20 inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-white/95 px-3 py-1.5 text-xs font-semibold text-blue-900 shadow-lg backdrop-blur-md hover:bg-blue-50 dark:border-blue-700 dark:bg-[#0e1424]/95 dark:text-blue-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="expandRightPanel"
                  >
                    <UIcon name="i-lucide-panel-right-open" class="size-4 text-blue-600 dark:text-blue-400" />
                    <span>Buka Detail Ruas</span>
                  </button>
                </div>
              </template>

              <!-- Panel Kanan: Properties / Feature Inspector -->
              <template #right="{ collapsed, collapse, expand, resize }">
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
                  @update:collapsed="(c) => { c ? collapse() : (resize ? resize(RIGHT_PANEL_WIDTH) : expand()); scheduleMapResize(); }"
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
          <template #bottom="{ collapsed, collapse, expand, resize }">
            <span :class="syncBottomPanelActions(collapsed, collapse, expand, resize)" class="hidden" />
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
              @update:collapsed="(c) => { c ? collapse() : (resize ? resize(BOTTOM_PANEL_HEIGHT) : expand()); handleBottomPanelExpandChange(c); scheduleMapResize(); }"
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

      <!-- Mobile & Tablet View (< 1024px): Full-Bleed MapLibre Canvas + Floating Peek Card -->
      <div v-else class="flex-1 relative overflow-hidden h-full w-full">
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
          :kecamatan-options="kecamatanOptions"
          :desa-options="desaOptions"
          :basemap="currentBasemap"
          @update:basemap="setBasemapChoice($event)"
          v-model:mouse-coords="mouseCoords"
          @select="handleFeatureSelect"
          @log="addLog"
          @draw-saved="handleDrawSaved"
          @split-point-selected="handleSplitPointSelected"
          @map-click="(coord) => clickedCoordinate = coord"
        />

        <!-- Floating Feature Peek Card di Atas Dock saat Sheet Tertutup (Jaringan Jalan Reference) -->
        <div
          v-if="selectedFeature && !mobileDrawerOpen"
          class="absolute left-3 right-3 sm:left-auto sm:right-3 sm:w-96 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-xl backdrop-blur-md dark:border-gray-700 dark:bg-[#0e1424]/95"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <UBadge
                  :label="selectedFeature.properties?.kondisi || 'Ruas'"
                  :color="getKondisiBadge(selectedFeature.properties?.kondisi).color"
                  variant="subtle"
                  size="xs"
                />
                <span class="text-[11px] font-mono font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                  {{ Math.round(selectedFeature.properties?.panjang_meter || 0).toLocaleString("id-ID") }} m
                </span>
              </div>
              <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white truncate">
                {{ selectedFeature.properties?.nama_ruas || "Ruas Jalan" }}
              </p>
              <p class="text-xs text-gray-600 dark:text-gray-300 truncate">
                {{ selectedFeature.properties?.desa || "-" }}, Kec. {{ selectedFeature.properties?.kecamatan || "-" }}
              </p>
            </div>

            <button
              type="button"
              aria-label="Hapus pilihan ruas"
              class="size-11 flex items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="selectedFeature = null"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <div class="mt-2.5 flex items-center gap-2">
            <button
              type="button"
              class="min-h-[44px] flex-1 rounded-lg bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="openMobileTab('inspector')"
            >
              Buka Detail & Aksi
            </button>
            <button
              type="button"
              aria-label="Zoom ke ruas terpilih"
              class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-800 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="handleZoomToFeature(selectedFeature)"
            >
              <UIcon name="i-lucide-locate-fixed" class="size-4" />
            </button>
            <button
              v-if="canSplitFeature(selectedFeature)"
              type="button"
              aria-label="Split ruas terpilih"
              class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-300 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="handleStartSplit(selectedFeature)"
            >
              <UIcon name="i-lucide-scissors" class="size-4" />
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══ VIEW MODE 2: TABULAR DATA MODE ═══════════════════════════════════ -->
    <div
      v-show="viewMode === 'table'"
      class="flex flex-col flex-1 w-full h-full overflow-hidden bg-gray-50/70 dark:bg-[#070b14]"
    >
      <!-- Tabular Header & Filter Toolbar -->
      <div class="p-3 sm:p-4 border-b border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] shrink-0 space-y-3 shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <!-- Left: Title & Context info -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <UButton
              icon="i-lucide-arrow-left"
              size="xs"
              color="neutral"
              variant="subtle"
              label="Kembali ke Peta"
              class="cursor-pointer"
              @click="viewMode = 'map'"
            />
            <div class="h-4 w-px bg-gray-200 dark:bg-white/[0.1] hidden sm:block" />
            <h2 class="text-xs font-semibold text-gray-900 dark:text-white">
              Data Tabular Jalan Poros Desa
            </h2>
            <UBadge
              :label="`${tableTotal.toLocaleString('id-ID')} Total Ruas`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="font-mono text-[11px]"
            />
            <UBadge
              v-if="isKecamatanRestricted && userKecamatanName"
              :label="`Kecamatan: ${userKecamatanName}`"
              color="info"
              variant="subtle"
              size="xs"
              class="text-[10px]"
            />
            <UBadge
              v-if="isDesaRestricted && userDesaName"
              :label="`Desa: ${userDesaName}`"
              color="warning"
              variant="subtle"
              size="xs"
              class="text-[10px]"
            />
          </div>

          <!-- Right: Action shortcuts -->
          <div class="flex items-center gap-2">
            <UButton
              v-if="canCreate"
              icon="i-lucide-plus"
              label="Tambah Ruas Baru"
              size="xs"
              color="primary"
              variant="solid"
              class="cursor-pointer"
              @click="openCreate"
            />
          </div>
        </div>

        <!-- Filter Controls Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 items-center">
          <!-- 1. Text Search Input -->
          <UInput
            v-model="tableSearch"
            icon="i-lucide-search"
            placeholder="Cari kode, nama ruas, desa..."
            size="xs"
            class="w-full"
          />

          <!-- 2. Filter Kecamatan -->
          <select
            :value="tableKecamatan"
            :disabled="isKecamatanRestricted || isDesaRestricted"
            class="text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 py-1.5 focus:outline-hidden disabled:opacity-75 disabled:cursor-not-allowed"
            @change="handleTabularKecamatanChange(($event.target as HTMLSelectElement).value)"
          >
            <option :value="null">Semua Kecamatan</option>
            <option
              v-for="kec in kecamatanOptions"
              :key="kec.id"
              :value="kec.nama"
            >
              {{ kec.nama }}
            </option>
          </select>

          <!-- 3. Filter Desa -->
          <select
            :value="tableDesa"
            :disabled="isDesaRestricted"
            class="text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 py-1.5 focus:outline-hidden disabled:opacity-75 disabled:cursor-not-allowed"
            @change="handleTabularDesaChange(($event.target as HTMLSelectElement).value)"
          >
            <option :value="null">Semua Desa</option>
            <option
              v-for="desa in tabularDesaOptions"
              :key="desa.id"
              :value="desa.nama"
            >
              {{ desa.nama }}
            </option>
          </select>

          <!-- 4. Filter Kondisi -->
          <select
            v-model="tableKondisi"
            class="text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 py-1.5 focus:outline-hidden"
          >
            <option :value="null">Semua Kondisi</option>
            <option v-for="kond in kondisiOptions" :key="kond" :value="kond">
              {{ kond }}
            </option>
          </select>

          <!-- 5. Filter Perkerasan & Reset -->
          <div class="flex items-center gap-1.5">
            <select
              v-model="tablePerkerasan"
              class="flex-1 text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 py-1.5 focus:outline-hidden"
            >
              <option :value="null">Semua Perkerasan</option>
              <option v-for="perk in perkerasanOptions" :key="perk" :value="perk">
                {{ perk }}
              </option>
            </select>

            <UButton
              v-if="activeFilterCount > 0 || tableSearch"
              icon="i-lucide-filter-x"
              size="xs"
              color="neutral"
              variant="subtle"
              title="Reset Semua Filter"
              class="cursor-pointer shrink-0"
              @click="resetFilters"
            />
            <UButton
              icon="i-lucide-refresh-cw"
              size="xs"
              color="neutral"
              variant="outline"
              :loading="tableStatus === 'pending'"
              title="Muat Ulang Tabel"
              class="cursor-pointer shrink-0"
              @click="() => refreshTable()"
            />
          </div>
        </div>
      </div>

      <!-- Tabular Main Body: Card & Scrollable Table -->
      <div class="flex-1 p-3 sm:p-4 overflow-hidden flex flex-col">
        <div class="flex flex-col flex-1 w-full h-full rounded-xl border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden shadow-2xs">
          <!-- Scrollable Table -->
          <div class="flex-1 overflow-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0f1422] border-b border-gray-200 dark:border-white/[0.08] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider whitespace-nowrap">
                <tr>
                  <th class="py-2.5 px-3">Kode</th>
                  <th class="py-2.5 px-3 min-w-[220px]">Nama Ruas</th>
                  <th class="py-2.5 px-3">Desa</th>
                  <th class="py-2.5 px-3">Kecamatan</th>
                  <th class="py-2.5 px-3 text-right">Panjang (m)</th>
                  <th class="py-2.5 px-3 text-right">Lebar (m)</th>
                  <th class="py-2.5 px-3">Perkerasan</th>
                  <th class="py-2.5 px-3">Kondisi</th>
                  <th class="py-2.5 px-3">Status</th>
                  <th class="py-2.5 px-3">Sumber Data</th>
                  <th class="py-2.5 px-3 text-center min-w-[140px]">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/[0.05] font-sans">
                <!-- Skeleton rows when loading -->
                <tr v-if="tableStatus === 'pending' && tableRows.length === 0" v-for="i in 8" :key="i">
                  <td class="py-3 px-3"><USkeleton class="h-4 w-12 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-40 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-20 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-24 rounded" /></td>
                  <td class="py-3 px-3 text-right"><USkeleton class="h-4 w-16 ml-auto rounded" /></td>
                  <td class="py-3 px-3 text-right"><USkeleton class="h-4 w-12 ml-auto rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-16 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-16 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-16 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-20 rounded" /></td>
                  <td class="py-3 px-3"><USkeleton class="h-4 w-24 mx-auto rounded" /></td>
                </tr>

                <!-- Empty state -->
                <tr v-else-if="tableRows.length === 0">
                  <td colspan="11" class="py-12 px-4 text-center">
                    <div class="flex flex-col items-center justify-center gap-2 max-w-sm mx-auto">
                      <UIcon name="i-lucide-inbox" class="size-8 text-gray-400 dark:text-gray-500" />
                      <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                        Tidak ada data jalan poros desa yang cocok
                      </p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">
                        Coba sesuaikan kata kunci pencarian atau reset filter wilayah dan kondisi.
                      </p>
                      <UButton
                        v-if="activeFilterCount > 0 || tableSearch"
                        label="Reset Filter"
                        size="xs"
                        color="neutral"
                        variant="subtle"
                        class="mt-1 cursor-pointer"
                        @click="resetFilters"
                      />
                    </div>
                  </td>
                </tr>

                <!-- Table Rows -->
                <tr
                  v-else
                  v-for="row in tableRows"
                  :key="row.id"
                  class="transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
                  :class="[
                    String(row.id) === String(selectedFeature?.id)
                      ? 'bg-blue-50/70 dark:bg-blue-950/40'
                      : ''
                  ]"
                >
                  <!-- Kode -->
                  <td class="py-2.5 px-3 whitespace-nowrap text-gray-500 dark:text-gray-400 font-mono text-[11px]">
                    {{ row.kode_ruas ?? '-' }}
                  </td>

                  <!-- Nama Ruas -->
                  <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-white">
                    <div class="flex items-center gap-1.5">
                      <span class="truncate">{{ row.nama_ruas || 'Tanpa Nama' }}</span>
                    </div>
                  </td>

                  <!-- Desa -->
                  <td class="py-2.5 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                    {{ row.desa || '-' }}
                  </td>

                  <!-- Kecamatan -->
                  <td class="py-2.5 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                    {{ row.kecamatan || '-' }}
                  </td>

                  <!-- Panjang -->
                  <td class="py-2.5 px-3 text-right font-mono font-medium whitespace-nowrap text-gray-800 dark:text-gray-200">
                    {{ (row.panjang_meter != null && row.panjang_meter !== '') ? Number(row.panjang_meter).toLocaleString('id-ID', { maximumFractionDigits: 1 }) : (row.panjang != null && row.panjang !== '' ? Number(row.panjang).toLocaleString('id-ID', { maximumFractionDigits: 1 }) : '-') }}
                  </td>

                  <!-- Lebar -->
                  <td class="py-2.5 px-3 text-right font-mono whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.lebar != null && row.lebar !== '' ? `${Number(row.lebar).toLocaleString('id-ID', { maximumFractionDigits: 1 })}` : '-' }}
                  </td>

                  <!-- Perkerasan -->
                  <td class="py-2.5 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                    <UBadge
                      v-if="row.perkerasan"
                      :label="row.perkerasan"
                      color="neutral"
                      variant="subtle"
                      size="xs"
                      class="text-[10px]"
                    />
                    <span v-else class="text-gray-400">-</span>
                  </td>

                  <!-- Kondisi -->
                  <td class="py-2.5 px-3 whitespace-nowrap">
                    <UBadge
                      :label="getKondisiBadge(row.kondisi).label"
                      :color="getKondisiBadge(row.kondisi).color"
                      variant="subtle"
                      size="xs"
                      class="text-[10px]"
                    />
                  </td>

                  <!-- Status Eksisting -->
                  <td class="py-2.5 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400 text-[11px]">
                    {{ row.status_eksisting || '-' }}
                  </td>

                  <!-- Sumber Data -->
                  <td class="py-2.5 px-3 whitespace-nowrap text-gray-500 dark:text-gray-400 text-[11px]">
                    {{ row.sumber_data || '-' }}
                  </td>

                  <!-- Aksi -->
                  <td class="py-2.5 px-3 text-center whitespace-nowrap">
                    <div class="flex items-center justify-center gap-1">
                      <UButton
                        icon="i-lucide-map-pin"
                        size="xs"
                        color="primary"
                        variant="subtle"
                        label="Peta"
                        title="Tampilkan ruas ini di peta"
                        class="cursor-pointer text-xs"
                        @click="handleViewRowOnMap(row)"
                      />
                      <UButton
                        v-if="canEditFeature(row)"
                        icon="i-lucide-pencil"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        title="Edit Data Ruas"
                        class="cursor-pointer"
                        @click="openEdit(row)"
                      />
                      <UButton
                        v-if="canSplitFeature(row)"
                        icon="i-lucide-scissors"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        title="Split / Pecah Ruas"
                        class="cursor-pointer text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                        @click="handleSplitFromTable(row)"
                      />
                      <UButton
                        v-if="canDeleteFeature(row)"
                        icon="i-lucide-trash-2"
                        size="xs"
                        color="error"
                        variant="ghost"
                        title="Hapus Ruas"
                        class="cursor-pointer"
                        @click="confirmDelete(row)"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tabular Pagination Footer -->
          <div
            v-if="tableTotal > 0"
            class="flex flex-col sm:flex-row items-center justify-between px-4 py-2 border-t border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-[#070b14] text-xs gap-3 shrink-0"
          >
            <!-- Page info -->
            <span class="text-gray-600 dark:text-gray-400 font-mono text-xs tabular-nums">
              {{ tabularPageRangeText }}
            </span>

            <div class="flex items-center gap-3">
              <!-- Per Page Selector -->
              <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <span class="hidden sm:inline">Tampilkan:</span>
                <select
                  v-model.number="tablePerPage"
                  class="text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2 py-1 focus:outline-hidden"
                >
                  <option :value="10">10</option>
                  <option :value="15">15</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                  <option :value="100">100</option>
                </select>
                <span class="hidden sm:inline">per halaman</span>
              </div>

              <!-- UPagination -->
              <UPagination
                v-if="tableLastPage > 1"
                :page="tablePage"
                :total="tableTotal"
                :items-per-page="tablePerPage"
                size="xs"
                color="neutral"
                active-color="primary"
                @update:page="(p) => tablePage = p"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ MOBILE FEATURE PEEK CARD (When a road is selected on mobile) ═════ -->
    <div
      v-if="selectedFeature && viewMode === 'map'"
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
      v-if="viewMode === 'map'"
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
            ? 'text-blue-600 dark:text-blue-400 font-semibold'
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
            ? 'text-blue-600 dark:text-blue-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('table')"
      >
        <div class="relative">
          <UIcon name="i-lucide-table" class="size-5" />
          <span
            v-if="tableTotal > 0"
            class="absolute -top-1 -right-2.5 px-1 text-[8px] font-mono rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-semibold"
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
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors cursor-pointer group"
        @click="openCreate"
      >
        <div class="size-7 rounded-full bg-blue-600 dark:bg-blue-500 text-white flex items-center justify-center shadow-md shadow-blue-600/30 group-active:scale-95 transition-transform">
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
            ? 'text-blue-600 dark:text-blue-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('inspector')"
      >
        <div class="relative">
          <UIcon name="i-lucide-info" class="size-5" />
          <span
            v-if="selectedFeature"
            class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-500 ring-2 ring-white dark:ring-[#0b0f19]"
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
            ? 'text-blue-600 dark:text-blue-400 font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="openMobileTab('filter')"
      >
        <div class="relative">
          <UIcon name="i-lucide-filter" class="size-5" />
          <span
            v-if="activeFilterCount > 0"
            class="absolute -top-1 -right-2 px-1 text-[8px] font-mono rounded-full bg-blue-600 text-white font-bold"
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
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
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
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'table'; refreshTable()"
            >
              <UIcon name="i-lucide-table" class="size-3.5 shrink-0" />
              <span>Tabel</span>
              <span
                v-if="tableTotal > 0"
                class="px-1 py-0.2 rounded-full text-[9px] font-mono leading-tight"
                :class="mobileDrawerTab === 'table'
                  ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300'
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
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'inspector'"
            >
              <UIcon name="i-lucide-info" class="size-3.5 shrink-0" />
              <span>Detail</span>
              <span
                v-if="selectedFeature"
                class="size-1.5 rounded-full bg-blue-500 shrink-0"
              />
            </button>

            <!-- Tab 4: Filter -->
            <button
              type="button"
              class="flex items-center justify-center gap-1 py-1.5 px-1 rounded-lg text-xs font-medium transition-all relative cursor-pointer"
              :class="mobileDrawerTab === 'filter'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'filter'"
            >
              <UIcon name="i-lucide-filter" class="size-3.5 shrink-0" />
              <span>Filter</span>
              <span
                v-if="activeFilterCount > 0"
                class="px-1 py-0.2 rounded-full text-[9px] font-mono leading-tight bg-blue-600 text-white"
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

    <!-- ═══ MODAL: Tambah / Edit Ruas Jalan (GIS Post-Draw Dialog) ═════════════ -->
    <UModal
      v-model:open="isFormOpen"
      :title="isEditing ? 'Edit Atribut & Geometri Ruas Jalan' : 'Simpan Ruas Jalan Baru'"
      :description="
        isEditing
          ? 'Perbarui identitas, lokasi wilayah, spesifikasi teknis, atau kembali ke peta untuk menggeser titik garis.'
          : 'Garis ruas berhasil digambar di peta. Lengkapi atribut di bawah untuk menyimpan ke basis data PostGIS.'
      "
      :ui="{
        content:
          'max-w-3xl w-[calc(100vw-1.5rem)] max-h-[90vh] flex flex-col overflow-hidden bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-2xl rounded-2xl',
        header: 'px-4 sm:px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 shrink-0',
        body: 'p-4 sm:p-5 overflow-y-auto flex-1 min-h-0',
        footer: 'px-4 sm:px-5 py-3 border-t border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-[#0e1424]/80 shrink-0',
      }"
    >
      <template #body>
        <form class="space-y-4" @submit.prevent="handleSave">
          <!-- 1. Spatial Geometry Telemetry Banner -->
          <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border transition-colors"
            :class="
              parsedFormGeometrySummary.valid
                ? 'border-emerald-200/90 bg-emerald-50/50 dark:border-emerald-800/60 dark:bg-emerald-950/25'
                : 'border-amber-300 bg-amber-50/70 dark:border-amber-700/70 dark:bg-amber-950/30'
            "
          >
            <div class="flex items-start sm:items-center gap-3 min-w-0">
              <div
                class="size-9 rounded-lg flex items-center justify-center shrink-0"
                :class="
                  parsedFormGeometrySummary.valid
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'bg-amber-500 text-white'
                "
              >
                <UIcon
                  :name="parsedFormGeometrySummary.valid ? 'i-lucide-route' : 'i-lucide-alert-triangle'"
                  class="size-4.5"
                />
              </div>

              <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="text-xs font-semibold text-gray-900 dark:text-white">
                    {{
                      parsedFormGeometrySummary.valid
                        ? "Geometri Garis Siap Disimpan"
                        : "Geometri Perlu Diperiksa"
                    }}
                  </span>
                  <UBadge
                    :label="parsedFormGeometrySummary.type"
                    :color="parsedFormGeometrySummary.valid ? 'success' : 'warning'"
                    variant="subtle"
                    size="xs"
                    class="font-mono"
                  />
                  <UBadge
                    :label="`${parsedFormGeometrySummary.vertexCount} titik vertex`"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    class="font-mono"
                  />
                  <UBadge
                    v-if="formState.panjang"
                    :label="`${Number(formState.panjang).toLocaleString('id-ID')} m (${(Number(formState.panjang) / 1000).toFixed(2)} km)`"
                    color="primary"
                    variant="subtle"
                    size="xs"
                    class="font-mono"
                  />
                </div>

                <div
                  v-if="parsedFormGeometrySummary.startCoord && parsedFormGeometrySummary.endCoord"
                  class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] font-mono text-gray-600 dark:text-gray-300 tabular-nums"
                >
                  <span>
                    Awal: {{ parsedFormGeometrySummary.startCoord[1].toFixed(5) }}, {{ parsedFormGeometrySummary.startCoord[0].toFixed(5) }}
                  </span>
                  <span class="text-gray-400">-></span>
                  <span>
                    Akhir: {{ parsedFormGeometrySummary.endCoord[1].toFixed(5) }}, {{ parsedFormGeometrySummary.endCoord[0].toFixed(5) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-center">
              <UButton
                type="button"
                icon="i-lucide-pen-tool"
                label="Sesuaikan Garis di Peta"
                size="xs"
                color="primary"
                variant="soft"
                class="cursor-pointer font-medium"
                @click="resumeMapGeometryEditingFromModal"
              />
            </div>
          </div>

          <!-- 2. Section: Identitas & Lokasi Wilayah -->
          <div class="p-3.5 rounded-xl border border-gray-200/90 dark:border-gray-800 bg-gray-50/40 dark:bg-white/[0.02] space-y-3">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5">
                <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                  Identitas & Lokasi Administratif
                </h3>
              </div>
              <span class="text-[11px] text-gray-500 dark:text-gray-400">
                * Wajib diisi
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
              <!-- Nama Ruas -->
              <div class="sm:col-span-8">
                <UFormField
                  label="Nama Ruas Jalan"
                  required
                  size="sm"
                  :error="formNameError || undefined"
                >
                  <UInput
                    v-model="formState.nama_ruas"
                    placeholder="Contoh: Jl. Poros Desa Mulyorejo"
                    icon="i-lucide-signpost"
                    size="sm"
                    autofocus
                    class="w-full"
                    @update:model-value="formNameError = ''"
                  />
                </UFormField>
              </div>

              <!-- Kode Ruas -->
              <div class="sm:col-span-4">
                <UFormField
                  label="Kode / No. Ruas"
                  size="sm"
                >
                  <UInputNumber
                    v-model="formState.kode_ruas"
                    :placeholder="summaryData?.next_kode_ruas ? `Auto: ${summaryData.next_kode_ruas}` : 'Nomor kode'"
                    size="sm"
                    class="w-full font-mono"
                  />
                </UFormField>
              </div>

              <!-- Kecamatan -->
              <div class="sm:col-span-6">
                <UFormField label="Kecamatan" size="sm">
                  <USelectMenu
                    :model-value="formState.kecamatan"
                    :items="formKecamatanItems"
                    value-key="value"
                    label-key="label"
                    icon="i-lucide-building-2"
                    placeholder="Pilih atau cari kecamatan..."
                    :disabled="isKecamatanRestricted || isDesaRestricted"
                    size="sm"
                    class="w-full"
                    @update:model-value="handleFormKecamatanChange"
                  />
                </UFormField>
              </div>

              <!-- Desa -->
              <div class="sm:col-span-6">
                <UFormField
                  :label="formSelectedKecamatanObj ? `Desa (${formSelectedKecamatanObj.nama})` : 'Desa / Kelurahan'"
                  size="sm"
                >
                  <USelectMenu
                    :model-value="formState.desa"
                    :items="formDesaItems"
                    value-key="value"
                    label-key="label"
                    icon="i-lucide-home"
                    :placeholder="formSelectedKecamatanObj ? `Pilih desa di ${formSelectedKecamatanObj.nama}...` : 'Pilih atau cari desa...'"
                    :disabled="isDesaRestricted"
                    size="sm"
                    class="w-full"
                    @update:model-value="handleFormDesaChange"
                  />
                </UFormField>
              </div>
            </div>
          </div>

          <!-- 3. Section: Dimensi & Kondisi Fisik Jalan -->
          <div class="p-3.5 rounded-xl border border-gray-200/90 dark:border-gray-800 bg-gray-50/40 dark:bg-white/[0.02] space-y-3.5">
            <div class="flex items-center gap-1.5">
              <UIcon name="i-lucide-ruler" class="size-3.5 text-blue-600 dark:text-blue-400" />
              <h3 class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                Dimensi & Spesifikasi Teknis
              </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <UFormField
                label="Panjang Ruas (meter)"
                description="Terhitung otomatis dari panjang garis peta"
                size="sm"
              >
                <UInputNumber
                  v-model="formState.panjang"
                  :step="1"
                  :min="1"
                  placeholder="Contoh: 2450"
                  size="sm"
                  class="w-full font-mono"
                />
              </UFormField>

              <UFormField
                label="Lebar Rata-rata (meter)"
                description="Lebar badan perkerasan jalan"
                size="sm"
              >
                <UInputNumber
                  v-model="formState.lebar"
                  :step="0.5"
                  :min="0.5"
                  placeholder="Contoh: 4.0"
                  size="sm"
                  class="w-full font-mono"
                />
              </UFormField>
            </div>

            <!-- Interactive Pills: Jenis Perkerasan -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                Jenis Perkerasan
              </label>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="item in perkerasanOptions"
                  :key="item"
                  type="button"
                  :aria-pressed="formState.perkerasan === item"
                  class="min-h-[34px] px-3 py-1.5 rounded-lg border text-xs font-medium transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  :class="
                    formState.perkerasan === item
                      ? 'border-blue-600 bg-blue-600 text-white shadow-xs'
                      : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
                  "
                  @click="formState.perkerasan = item"
                >
                  {{ item }}
                </button>
              </div>
            </div>

            <!-- Interactive Cards: Kondisi Jalan -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                Kondisi Visual Jalan
              </label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button
                  v-for="item in kondisiCardMeta"
                  :key="item.value"
                  type="button"
                  :aria-pressed="formState.kondisi === item.value"
                  class="min-h-[38px] px-3 py-2 rounded-lg border text-xs font-medium flex items-center gap-2 transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  :class="
                    formState.kondisi === item.value
                      ? item.activeClass
                      : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
                  "
                  @click="formState.kondisi = item.value"
                >
                  <span class="size-2.5 rounded-full shrink-0" :class="item.dotClass" />
                  <span class="truncate">{{ item.label }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- 4. Section: Status Administratif & Sumber Data -->
          <div class="p-3.5 rounded-xl border border-gray-200/90 dark:border-gray-800 bg-gray-50/40 dark:bg-white/[0.02] space-y-3">
            <div class="flex items-center gap-1.5">
              <UIcon name="i-lucide-file-check-2" class="size-3.5 text-blue-600 dark:text-blue-400" />
              <h3 class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                Status & Sumber Data
              </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <UFormField label="Status Awal" size="sm">
                <UInput
                  v-model="formState.status_awal"
                  placeholder="Contoh: Jalan Desa"
                  size="sm"
                  class="w-full"
                />
                <div class="flex flex-wrap gap-1 mt-1">
                  <button
                    v-for="preset in statusAwalPresets"
                    :key="preset"
                    type="button"
                    class="px-1.5 py-0.5 rounded text-[10px] border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
                    @click="formState.status_awal = preset"
                  >
                    {{ preset }}
                  </button>
                </div>
              </UFormField>

              <UFormField label="Status Eksisting" size="sm">
                <UInput
                  v-model="formState.status_eksisting"
                  placeholder="Contoh: Jalan Poros Desa"
                  size="sm"
                  class="w-full"
                />
                <div class="flex flex-wrap gap-1 mt-1">
                  <button
                    v-for="preset in statusEksistingPresets"
                    :key="preset"
                    type="button"
                    class="px-1.5 py-0.5 rounded text-[10px] border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
                    @click="formState.status_eksisting = preset"
                  >
                    {{ preset }}
                  </button>
                </div>
              </UFormField>

              <UFormField label="Sumber Data" size="sm">
                <UInput
                  v-model="formState.sumber_data"
                  placeholder="Contoh: Survei Lapangan PUPR"
                  size="sm"
                  class="w-full"
                />
              </UFormField>
            </div>
          </div>

          <!-- 5. Collapsible Raw GeoJSON Inspector -->
          <div class="rounded-xl border border-gray-200/90 dark:border-gray-800 overflow-hidden">
            <button
              type="button"
              class="w-full flex items-center justify-between px-3.5 py-2.5 bg-gray-50/70 dark:bg-white/[0.02] hover:bg-gray-100/70 dark:hover:bg-white/[0.04] text-xs font-medium text-gray-700 dark:text-gray-300 transition-colors cursor-pointer"
              @click="showRawGeoJson = !showRawGeoJson"
            >
              <span class="flex items-center gap-2">
                <UIcon name="i-lucide-braces" class="size-3.5 text-gray-500" />
                <span>Koordinat GeoJSON Mentah (Opsional / Tingkat Lanjut)</span>
              </span>
              <UIcon
                :name="showRawGeoJson ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                class="size-4 text-gray-500"
              />
            </button>

            <div
              v-if="showRawGeoJson"
              class="p-3.5 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b0f19] space-y-2"
            >
              <UFormField
                label="GeoJSON LineString (WGS84)"
                description="Format: { &quot;type&quot;: &quot;LineString&quot;, &quot;coordinates&quot;: [[lon, lat], [lon, lat]] }"
                :error="formGeometryError || undefined"
                size="sm"
              >
                <UTextarea
                  v-model="formState.geometry"
                  :rows="4"
                  size="sm"
                  class="w-full font-mono text-xs"
                  placeholder='{ "type": "LineString", "coordinates": [[111.85, -7.15], [111.87, -7.16]] }'
                  @update:model-value="formGeometryError = ''"
                />
              </UFormField>
            </div>
          </div>
        </form>
      </template>

      <template #footer>
        <div class="flex flex-wrap items-center justify-between gap-2 w-full">
          <UButton
            type="button"
            icon="i-lucide-pen-tool"
            label="Kembali ke Peta"
            color="neutral"
            variant="outline"
            size="sm"
            class="cursor-pointer"
            @click="resumeMapGeometryEditingFromModal"
          />

          <div class="flex items-center gap-2 ml-auto">
            <UButton
              type="button"
              label="Tutup"
              color="neutral"
              variant="ghost"
              size="sm"
              class="cursor-pointer"
              @click="isFormOpen = false"
            />
            <UButton
              type="button"
              icon="i-lucide-check"
              :label="isEditing ? 'Simpan Perubahan' : 'Simpan Ruas Jalan'"
              color="primary"
              variant="solid"
              size="sm"
              class="cursor-pointer font-semibold"
              :loading="submitting"
              @click="handleSave"
            />
          </div>
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
          <div class="p-3 rounded-lg border border-blue-500/30 bg-blue-50/20 dark:bg-blue-950/10 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-blue-700 dark:text-blue-300 text-xs">Bagian 1 (Ruas Eksisting)</span>
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

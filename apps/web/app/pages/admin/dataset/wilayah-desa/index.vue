<script setup lang="ts">
import type { SplitterItem } from "@nuxt/ui";
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from "vue";
import MobileBottomSheet from "~/components/dataset/jalan-poros-desa/MobileBottomSheet.vue";
import WilayahDesaBottomPanel from "~/components/dataset/wilayah-desa/BottomPanel.vue";
import WilayahDesaRightPanel from "~/components/dataset/wilayah-desa/RightPanel.vue";
import WilayahDesaMaplibre, {
  type DesaMapFeaturePayload,
} from "~/components/dataset/WilayahDesaMaplibre.vue";
import type { ConsoleLog } from "~/types/dataset-editor";
import {
  DEFAULT_DESA_POLYGON_GEOJSON,
  parseDesaGeometryInput,
  type DesaFormState,
  type DesaRightPanelMode,
  type KecamatanSeriesItem,
  type WilayahDesaSpatialSummary,
} from "~/types/wilayah-desa";

definePageMeta({
  middleware: ["auth", "permission"],
  permission: [
    "batas-desa-view",
    "batas-desa-manage",
  ],
  fullBleed: true,
  key: (route) => route.path,
});

useSeoMeta({
  title: "Wilayah Desa - Map Series & Dataset Spasial",
});

const toast = useToast();
const auth = useAuthStore();
const { can, isAdmin } = usePermission();

const canCreate = computed(() => can("batas-desa.create") || can("batas-desa.manage"));
const canEdit = computed(() => can("batas-desa.edit") || can("batas-desa.manage"));
const canDelete = computed(() => can("batas-desa.delete") || can("batas-desa.manage"));

const userKecamatanId = computed(() => auth.user?.id_kecamatan ?? null);
const userDesaId = computed(() => auth.user?.id_desa ?? null);
const isKecamatanRestricted = computed(() => !isAdmin() && !!userKecamatanId.value);
const isDesaRestricted = computed(() => !isAdmin() && !!userDesaId.value);

function canEditFeature(feat: any): boolean {
  if (!canEdit.value || !feat) return false;
  if (isAdmin()) return true;

  const featId = Number(feat?.id ?? feat?.properties?.id ?? feat?.raw?.id);
  const featKecId = Number(
    feat?.id_kecamatan ??
      feat?.properties?.id_kecamatan ??
      feat?.raw?.id_kecamatan ??
      feat?.raw?.properties?.id_kecamatan
  );

  if (isDesaRestricted.value) {
    return !!userDesaId.value && featId === Number(userDesaId.value);
  }
  if (isKecamatanRestricted.value) {
    return !!userKecamatanId.value && featKecId === Number(userKecamatanId.value);
  }
  return true;
}

function canDeleteFeature(feat: any): boolean {
  if (!canDelete.value || !feat) return false;
  if (isAdmin()) return true;
  if (isDesaRestricted.value) return false;

  const featKecId = Number(
    feat?.id_kecamatan ??
      feat?.properties?.id_kecamatan ??
      feat?.raw?.id_kecamatan ??
      feat?.raw?.properties?.id_kecamatan
  );

  if (isKecamatanRestricted.value) {
    return !!userKecamatanId.value && featKecId === Number(userKecamatanId.value);
  }
  return true;
}

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
    addLog("INFO", "Workbench spasial wilayah desa siap.");
  }
});

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener("resize", handleWindowResize);
  }
  clearTimeout(searchDebounceTimer);
});

// ─── View Mode & Splitter Configuration ──────────────────────────────────────
const viewMode = ref<"map" | "table">("map");

const RIGHT_PANEL_WIDTH = 380;
const BOTTOM_PANEL_HEIGHT = 280;

const horizontalSplitterItems = ref<SplitterItem[]>([
  {
    id: "map-canvas",
    slot: "map",
    minSize: 35,
    class: "flex flex-1 w-full h-full relative overflow-hidden",
  },
  {
    id: "right-panel",
    slot: "right",
    sizeUnit: "px",
    defaultSize: 42,
    minSize: 300,
    maxSize: 520,
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
    maxSize: 500,
    collapsible: true,
    collapsedSize: 40,
    class: "flex flex-col h-full",
  },
]);

const horizontalSplitterRef = ref<any>(null);
const verticalSplitterRef = ref<any>(null);
const isDraggingHorizontal = ref(false);
const isDraggingVertical = ref(false);
const rightPanelCollapsed = ref(true);
const bottomPanelCollapsed = ref(true);
const bottomPanelOpen = ref(false);
const bottomActiveTab = ref<"table" | "console">("table");

interface SplitterSlotCtrl {
  collapsed: boolean;
  collapse: () => void;
  expand: () => void;
  resize: (size: number) => void;
}

const rightPanelSlotData = shallowRef<SplitterSlotCtrl | null>(null);
const bottomPanelSlotData = shallowRef<SplitterSlotCtrl | null>(null);

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

function scheduleMapResize() {
  nextTick(() => {
    mapViewerRef.value?.resizeMap?.();
    mobileMapViewerRef.value?.resizeMap?.();
  });
}

function expandRightPanel() {
  if (!isDesktop.value) return;
  if (rightPanelSlotData.value) {
    if (rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.resize(RIGHT_PANEL_WIDTH);
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[1]?.resize?.(RIGHT_PANEL_WIDTH);
  }
  scheduleMapResize();
}

function collapseRightPanel() {
  if (rightPanelSlotData.value) {
    if (!rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.collapse();
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[1]?.collapse?.();
  }
  scheduleMapResize();
}

function onHorizontalDragging(_index: number, dragging: boolean) {
  isDraggingHorizontal.value = dragging;
  if (!dragging) scheduleMapResize();
}

function onVerticalDragging(_index: number, dragging: boolean) {
  isDraggingVertical.value = dragging;
  if (!dragging) scheduleMapResize();
}

function handleBottomPanelExpandChange(isCollapsed: boolean) {
  bottomPanelOpen.value = !isCollapsed;
  if (bottomPanelOpen.value && tableFeatures.value.length === 0) {
    refreshTable();
  }
}

const allPanelsHidden = computed(() =>
  Boolean(rightPanelCollapsed.value && bottomPanelCollapsed.value)
);

function toggleAllPanels() {
  if (!isDesktop.value) return;
  if (allPanelsHidden.value) {
    bottomPanelSlotData.value?.resize(BOTTOM_PANEL_HEIGHT);
    handleBottomPanelExpandChange(false);
    if (selectedFeature.value || rightPanelMode.value !== "inspect") {
      rightPanelSlotData.value?.resize(RIGHT_PANEL_WIDTH);
    }
  } else {
    collapseRightPanel();
    bottomPanelSlotData.value?.collapse();
    handleBottomPanelExpandChange(true);
  }
  scheduleMapResize();
}

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

// ─── API Data Fetching ────────────────────────────────────────────────────────
const { data: summaryData, status: summaryStatus, refresh: refreshSummary } = useHttp<WilayahDesaSpatialSummary>(
  "admin/batas-wilayah-desa",
  {
    query: { format: "summary" },
    onFetchResponseError: (ctx) => {
      addLog("ERROR", `Gagal memuat ringkasan wilayah desa (HTTP ${ctx.response?.status || "?"}).`);
    },
  }
);

const { data: kecamatanResponse, status: kecamatanStatus, refresh: refreshKecamatan } = useHttp<{
  ok: boolean;
  data: KecamatanSeriesItem[];
}>("admin/batas-wilayah-kecamatan", {
  query: { format: "series" },
});

const kecamatanList = computed(() => kecamatanResponse.value?.data || []);

const selectedKecamatanId = ref<number | null>(null);

const selectedKecamatan = computed(() => {
  if (!selectedKecamatanId.value) return null;
  return kecamatanList.value.find((k) => k.id === selectedKecamatanId.value) || null;
});

const currentSeriesIndex = computed(() => {
  if (!selectedKecamatanId.value) return -1;
  return kecamatanList.value.findIndex((k) => k.id === selectedKecamatanId.value);
});

function getKecamatanName(kecId: number | null | undefined): string {
  if (!kecId) return "-";
  const found = kecamatanList.value.find((k) => k.id === Number(kecId));
  return found ? found.nama_kecamatan : String(kecId);
}

// Tabular state with pagination (synced with sub-district filter)
const tableSearch = ref("");
const tableKecamatanFilter = ref<number | null>(null);
const tablePage = ref(1);
const tablePerPage = ref(25);
let programmaticPageReset = false;
let suppressNextSearchWatch = false;
let pendingMapSyncFromTableFilter = false;

watch(
  [userKecamatanId, isKecamatanRestricted],
  ([newKecId, restricted]) => {
    if (restricted && newKecId) {
      selectedKecamatanId.value = newKecId;
      tableKecamatanFilter.value = newKecId;
    } else if (!restricted) {
      selectedKecamatanId.value = null;
      tableKecamatanFilter.value = null;
    }
  },
  { immediate: true }
);

const { data: tableDataResponse, status: tableStatus, refresh: refreshTable } = useHttp<{
  ok: boolean;
  data: any[];
  total: number;
  current_page: number;
  last_page: number;
}>("admin/batas-wilayah-desa", {
  key: "wilayah-desa-table",
  query: computed(() => ({
    format: "table",
    per_page: tablePerPage.value,
    page: tablePage.value,
    search: tableSearch.value || undefined,
    id_kecamatan: tableKecamatanFilter.value || undefined,
  })),
  lazy: true,
  watch: false,
});

watch([tableKecamatanFilter, tablePerPage], () => {
  programmaticPageReset = true;
  tablePage.value = 1;
  programmaticPageReset = false;
  refreshTable();
});

watch(tablePage, () => {
  if (!programmaticPageReset) {
    refreshTable();
  }
}, { flush: "sync" });

let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;
watch(tableSearch, () => {
  if (suppressNextSearchWatch) {
    suppressNextSearchWatch = false;
    return;
  }
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    programmaticPageReset = true;
    tablePage.value = 1;
    programmaticPageReset = false;
    refreshTable();
  }, 280);
});

watch(
  () => auth.user?.uuid,
  (newUuid, oldUuid) => {
    if (newUuid && oldUuid && newUuid !== oldUuid) {
      if (!isKecamatanRestricted.value) {
        selectedKecamatanId.value = null;
        tableKecamatanFilter.value = null;
      }
      refreshSummary();
      refreshKecamatan();
      refreshTable();
    }
  }
);

const loading = computed(
  () =>
    summaryStatus.value === "pending" ||
    kecamatanStatus.value === "pending"
);

const totalDesa = computed(() => summaryData.value?.total_desa ?? 0);
const totalLuasHektar = computed(() =>
  (summaryData.value?.total_luas_hektar || 0).toLocaleString("id-ID", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
);

const tableFeatures = computed(() => tableDataResponse.value?.data || []);
const tableTotal = computed(() => tableDataResponse.value?.total ?? 0);
const tableLastPage = computed(() => tableDataResponse.value?.last_page ?? 1);

const formattedTableFeatures = computed(() =>
  tableFeatures.value.map((item: any) => {
    const props = item.properties || item;
    return {
      id: item.id || props.id,
      nama_desa: props.nama_desa || item.nama_desa || "",
      id_kecamatan: props.id_kecamatan ?? item.id_kecamatan ?? null,
      luas_hektar: props.luas_hektar ?? item.luas_hektar ?? 0,
      nama_pimpinan: props.nama_pimpinan || item.nama_pimpinan || "-",
      nama_jabatan: props.nama_jabatan || item.nama_jabatan || "-",
      nip: props.nip || item.nip || "-",
      pangkat_gol: props.pangkat_gol || item.pangkat_gol || "-",
      raw: item,
    };
  })
);

const tabularPageRangeText = computed(() => {
  if (!tableTotal.value) return "Tidak ada data";
  const start = (tablePage.value - 1) * tablePerPage.value + 1;
  const end = Math.min(tablePage.value * tablePerPage.value, tableTotal.value);
  return `${start.toLocaleString("id-ID")} - ${end.toLocaleString("id-ID")} dari ${tableTotal.value.toLocaleString("id-ID")} desa`;
});

function resetTableFilters() {
  clearTimeout(searchDebounceTimer);
  if (tableSearch.value) {
    suppressNextSearchWatch = true;
    tableSearch.value = "";
  }
  const kecChanged = !isKecamatanRestricted.value && tableKecamatanFilter.value !== null;
  if (!isKecamatanRestricted.value) {
    selectedKecamatanId.value = null;
    tableKecamatanFilter.value = null;
  }
  if (!kecChanged) {
    programmaticPageReset = true;
    tablePage.value = 1;
    programmaticPageReset = false;
    refreshTable();
  }
  if (kecChanged) {
    addLog("INFO", "Filter tabel & peta direset ke seluruh Kabupaten Bojonegoro.");
    if (viewMode.value === "map") {
      fitMapToBounds();
    } else {
      pendingMapSyncFromTableFilter = true;
    }
  }
}

// ─── MapLibre & Selection State ───────────────────────────────────────────────
const mapViewerRef = ref<InstanceType<typeof WilayahDesaMaplibre> | null>(null);
const mobileMapViewerRef = ref<InstanceType<typeof WilayahDesaMaplibre> | null>(null);
const mapRefreshNonce = ref(0);
const { currentBasemap, setBasemapChoice } = useDefaultBasemap<string>("maplibre");

const clickedCoordinate = ref<[number, number] | null>(null);
const selectedFeature = ref<any | null>(null);
const loadingFeatureDetail = ref(false);
const featureDetailCache = new Map<number, any>();

// Right Panel Form State (Sticking to Map Canvas)
const rightPanelMode = ref<DesaRightPanelMode>("inspect");
const submitting = ref(false);
const formNameError = ref("");
const formGeometryError = ref("");
const formState = reactive<DesaFormState>({
  id: null,
  nama_desa: "",
  id_kecamatan: null,
  nama_pimpinan: "",
  nama_jabatan: "Kepala Desa",
  nip: "",
  pangkat_gol: "",
  geometry: "",
});

const formPreviewGeometry = computed(() => {
  if (rightPanelMode.value === "inspect") return null;
  return parseDesaGeometryInput(formState.geometry).geometry;
});

// Mobile Drawer State
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<"table" | "right">("right");

function openMobileTab(tab: "table" | "right") {
  mobileDrawerTab.value = tab;
  mobileDrawerOpen.value = true;
  if (tab === "table" && tableFeatures.value.length === 0) {
    refreshTable();
  }
}

function getActiveMapRef() {
  return isDesktop.value ? mapViewerRef.value : mobileMapViewerRef.value;
}

watch(
  [selectedFeature, rightPanelMode],
  ([feat, mode]) => {
    if (!isDesktop.value) return;
    if (feat || mode !== "inspect") {
      nextTick(() => expandRightPanel());
    } else {
      nextTick(() => collapseRightPanel());
    }
  }
);

function parseGeoJsonValue(val: any): any {
  if (!val) return null;
  if (typeof val === "string") {
    try {
      return JSON.parse(val);
    } catch {
      return null;
    }
  }
  return val;
}

const activeSelectedId = computed<number | null>(() => {
  const rawId = selectedFeature.value?.id ?? selectedFeature.value?.properties?.id;
  const n = Number(rawId);
  return Number.isFinite(n) && n > 0 ? n : null;
});

const activeSelectedGeometry = computed(() => {
  return (
    selectedFeature.value?.geometry ||
    parseGeoJsonValue(selectedFeature.value?.geojson) ||
    null
  );
});

async function enrichSelectedDesa(id: number, fitAfterLoad = false) {
  const cached = featureDetailCache.get(id);
  if (cached) {
    if (activeSelectedId.value === id) {
      selectedFeature.value = cached;
      if (fitAfterLoad && cached.geometry) {
        getActiveMapRef()?.fitToGeometry(cached.geometry);
      }
    }
    return cached;
  }

  loadingFeatureDetail.value = true;
  try {
    const res = await $http<{
      ok: boolean;
      data?: any;
      feature?: any;
    }>(`admin/batas-wilayah-desa/${id}`, {
      silent: true,
    } as any);
    const feat = res?.feature;
    if (feat) {
      featureDetailCache.set(id, feat);
      if (activeSelectedId.value === id) {
        selectedFeature.value = feat;
        if (fitAfterLoad && feat.geometry) {
          getActiveMapRef()?.fitToGeometry(feat.geometry);
        }
      }
      return feat;
    }
  } catch {
    // Keep MVT attributes if detail request fails
  } finally {
    loadingFeatureDetail.value = false;
  }
  return null;
}

function handleMapSelectDesa(payload: DesaMapFeaturePayload) {
  if (rightPanelMode.value !== "inspect") {
    rightPanelMode.value = "inspect";
  }

  const cached = featureDetailCache.get(payload.id);
  if (cached) {
    selectedFeature.value = cached;
    return;
  }

  selectedFeature.value = {
    type: "Feature",
    id: payload.id,
    properties: {
      id: payload.id,
      nama_desa: payload.nama_desa,
      id_kecamatan: payload.id_kecamatan,
      nama_pimpinan: payload.nama_pimpinan,
      nama_jabatan: payload.nama_jabatan,
      nip: payload.nip,
      pangkat_gol: payload.pangkat_gol,
      luas_hektar: payload.luas_hektar,
      keliling_meter: payload.keliling_meter,
    },
    geometry: null,
  };

  void enrichSelectedDesa(payload.id, false);
}

function clearSelection() {
  selectedFeature.value = null;
  if (rightPanelMode.value !== "inspect") {
    rightPanelMode.value = "inspect";
  }
}

function fitMapToBounds() {
  getActiveMapRef()?.fitToSummaryOrBojonegoro();
}

// ─── Series Navigation & Sub-District Filter Sync ─────────────────────────────
function selectKecamatan(id: number | null) {
  if (isKecamatanRestricted.value && userKecamatanId.value && id !== userKecamatanId.value) {
    return;
  }

  const prevId = selectedKecamatanId.value;
  selectedKecamatanId.value = id;
  tableKecamatanFilter.value = id;

  if (prevId !== id) {
    const label = id ? `Kecamatan ${getKecamatanName(id)}` : "Seluruh Kabupaten Bojonegoro";
    addLog("INFO", `Filter wilayah aktif: ${label}.`);
  }

  if (viewMode.value !== "map") {
    pendingMapSyncFromTableFilter = true;
    return;
  }

  if (!id) {
    fitMapToBounds();
    return;
  }

  const kec = kecamatanList.value.find((k) => k.id === id);
  if (kec?.bbox && prevId === id) {
    getActiveMapRef()?.safeFitBounds(kec.bbox, 64, 15, 550);
  }
}

function prevKecamatan() {
  if (kecamatanList.value.length === 0) return;
  const idx = currentSeriesIndex.value;
  if (idx <= 0) {
    selectKecamatan(kecamatanList.value[kecamatanList.value.length - 1]!.id);
  } else {
    selectKecamatan(kecamatanList.value[idx - 1]!.id);
  }
}

function nextKecamatan() {
  if (kecamatanList.value.length === 0) return;
  const idx = currentSeriesIndex.value;
  if (idx === -1 || idx >= kecamatanList.value.length - 1) {
    selectKecamatan(kecamatanList.value[0]!.id);
  } else {
    selectKecamatan(kecamatanList.value[idx + 1]!.id);
  }
}

function resetToAllBojonegoro() {
  selectKecamatan(null);
}

function focusUserDesa() {
  if (!userDesaId.value) return;
  const myDesa = formattedTableFeatures.value.find(
    (f: any) => Number(f.id) === Number(userDesaId.value)
  );
  if (myDesa) {
    zoomToFeature(myDesa.raw);
  } else {
    void enrichSelectedDesa(Number(userDesaId.value), true);
  }
}

function zoomToSelectedDesa() {
  const geom = activeSelectedGeometry.value;
  if (geom) {
    getActiveMapRef()?.fitToGeometry(geom);
    return;
  }
  const centroid = parseGeoJsonValue(selectedFeature.value?.properties?.centroid);
  if (centroid?.coordinates) {
    getActiveMapRef()?.flyToCoords(centroid.coordinates, 14.5);
    return;
  }
  if (activeSelectedId.value) {
    void enrichSelectedDesa(activeSelectedId.value, true);
  }
}

function zoomToFeature(feat: any) {
  viewMode.value = "map";
  rightPanelMode.value = "inspect";
  if (!isDesktop.value) {
    mobileDrawerOpen.value = false;
  }

  const featId = Number(feat.id || feat.properties?.id);
  const parsedGeom = parseGeoJsonValue(feat.geometry || feat.geojson || feat.raw?.geojson);
  const parsedCentroid = parseGeoJsonValue(feat.centroid || feat.properties?.centroid);

  selectedFeature.value = {
    type: "Feature",
    id: featId,
    properties: {
      id: featId,
      nama_desa: feat.nama_desa || feat.properties?.nama_desa,
      id_kecamatan: feat.id_kecamatan ?? feat.properties?.id_kecamatan ?? null,
      nama_pimpinan: feat.nama_pimpinan || feat.properties?.nama_pimpinan || "-",
      nama_jabatan: feat.nama_jabatan || feat.properties?.nama_jabatan || "-",
      nip: feat.nip || feat.properties?.nip || "-",
      pangkat_gol: feat.pangkat_gol || feat.properties?.pangkat_gol || "-",
      luas_hektar: feat.luas_hektar ?? feat.properties?.luas_hektar ?? 0,
      keliling_meter: feat.keliling_meter ?? feat.properties?.keliling_meter ?? 0,
      centroid: parsedCentroid,
    },
    geometry: parsedGeom,
  };

  nextTick(() => {
    scheduleMapResize();
    if (isDesktop.value) expandRightPanel();
    if (parsedGeom) {
      getActiveMapRef()?.fitToGeometry(parsedGeom);
    } else if (parsedCentroid?.coordinates) {
      getActiveMapRef()?.flyToCoords(parsedCentroid.coordinates, 14.5);
    } else if (featId) {
      void enrichSelectedDesa(featId, true);
    } else if (feat.id_kecamatan) {
      selectKecamatan(feat.id_kecamatan);
    }
  });
}

function handleViewInTable(feat?: any) {
  const name = feat?.properties?.nama_desa || feat?.nama_desa;
  const featKecId = feat?.properties?.id_kecamatan ?? feat?.id_kecamatan ?? null;
  const nextKecId = featKecId ? Number(featKecId) : null;
  const kecChanged = Boolean(nextKecId && !isKecamatanRestricted.value && nextKecId !== tableKecamatanFilter.value);
  if (kecChanged && nextKecId) {
    selectedKecamatanId.value = nextKecId;
    tableKecamatanFilter.value = nextKecId;
  }
  if (name) {
    const nextSearch = String(name);
    clearTimeout(searchDebounceTimer);
    if (tableSearch.value !== nextSearch) {
      suppressNextSearchWatch = true;
      tableSearch.value = nextSearch;
    }
    if (!kecChanged) {
      programmaticPageReset = true;
      tablePage.value = 1;
      programmaticPageReset = false;
      refreshTable();
    }
  }
  if (isDesktop.value) {
    bottomActiveTab.value = "table";
    bottomPanelSlotData.value?.resize(BOTTOM_PANEL_HEIGHT);
    handleBottomPanelExpandChange(false);
    scheduleMapResize();
  } else {
    openMobileTab("table");
  }
}

// ─── Refresh All ──────────────────────────────────────────────────────────────
async function handleRefreshAll() {
  featureDetailCache.clear();
  await Promise.all([refreshSummary(), refreshKecamatan(), refreshTable()]);
  mapRefreshNonce.value++;
  addLog("INFO", "Data spasial dan tile peta wilayah desa telah disinkronkan.");
  toast.add({
    title: "Data Diperbarui",
    description: "Tile peta spasial & data wilayah desa telah disinkronkan.",
    color: "success",
  });
}

// ─── Right-Panel Create & Edit Form Logic ─────────────────────────────────────
function buildStarterPolygonForViewport() {
  const kecBox = selectedKecamatan.value?.bbox;
  if (Array.isArray(kecBox) && kecBox.length === 4) {
    const [minX, minY, maxX, maxY] = kecBox.map(Number);
    if ([minX, minY, maxX, maxY].every(Number.isFinite)) {
      const cx = Number(((minX + maxX) / 2).toFixed(5));
      const cy = Number(((minY + maxY) / 2).toFixed(5));
      const dx = 0.008;
      const dy = 0.008;
      return {
        type: "Polygon",
        coordinates: [
          [
            [cx - dx, cy + dy],
            [cx + dx, cy + dy],
            [cx + dx, cy - dy],
            [cx - dx, cy - dy],
            [cx - dx, cy + dy],
          ],
        ],
      };
    }
  }
  return DEFAULT_DESA_POLYGON_GEOJSON;
}

function openCreatePanel() {
  if (!canCreate.value || isDesaRestricted.value) return;

  formNameError.value = "";
  formGeometryError.value = "";
  formState.id = null;
  formState.nama_desa = "";
  formState.id_kecamatan = userKecamatanId.value || selectedKecamatanId.value || null;
  formState.nama_pimpinan = "";
  formState.nama_jabatan = "Kepala Desa";
  formState.nip = "";
  formState.pangkat_gol = "";
  formState.geometry = JSON.stringify(buildStarterPolygonForViewport(), null, 2);

  rightPanelMode.value = "create";

  if (viewMode.value === "table") {
    viewMode.value = "map";
  }

  nextTick(() => {
    scheduleMapResize();
    if (isDesktop.value) {
      expandRightPanel();
    } else {
      openMobileTab("right");
    }
  });
}

async function openEditPanel(feat: any) {
  if (!canEditFeature(feat)) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda hanya memiliki wewenang untuk mengedit wilayah desa Anda sendiri.",
      color: "error",
    });
    return;
  }

  const targetId = Number(feat.id || feat.properties?.id);
  let resolvedFeat = feat;
  let resolvedGeom =
    parseGeoJsonValue(feat.geometry) ||
    parseGeoJsonValue(feat.raw?.geometry) ||
    parseGeoJsonValue(feat.geojson) ||
    parseGeoJsonValue(feat.raw?.geojson);

  if (!resolvedGeom && Number.isFinite(targetId) && targetId > 0) {
    const detailed = await enrichSelectedDesa(targetId, false);
    if (detailed) {
      resolvedFeat = detailed;
      resolvedGeom = parseGeoJsonValue(detailed.geometry);
    }
  }

  formNameError.value = "";
  formGeometryError.value = "";
  formState.id = targetId || null;
  formState.nama_desa = resolvedFeat.properties?.nama_desa || resolvedFeat.nama_desa || "";
  formState.id_kecamatan =
    resolvedFeat.properties?.id_kecamatan ?? resolvedFeat.id_kecamatan ?? null;
  formState.nama_pimpinan = resolvedFeat.properties?.nama_pimpinan || resolvedFeat.nama_pimpinan || "";
  formState.nama_jabatan =
    resolvedFeat.properties?.nama_jabatan || resolvedFeat.nama_jabatan || "Kepala Desa";
  formState.nip = resolvedFeat.properties?.nip || resolvedFeat.nip || "";
  formState.pangkat_gol = resolvedFeat.properties?.pangkat_gol || resolvedFeat.pangkat_gol || "";
  formState.geometry = JSON.stringify(resolvedGeom || {}, null, 2);

  // Keep selected feature synced with edited row
  if (Number.isFinite(targetId) && targetId > 0) {
    selectedFeature.value = {
      type: "Feature",
      id: targetId,
      geometry: resolvedGeom,
      properties: {
        id: targetId,
        nama_desa: formState.nama_desa,
        id_kecamatan: formState.id_kecamatan,
        nama_pimpinan: formState.nama_pimpinan || "-",
        nama_jabatan: formState.nama_jabatan || "-",
        nip: formState.nip || "-",
        pangkat_gol: formState.pangkat_gol || "-",
        luas_hektar: resolvedFeat.properties?.luas_hektar ?? resolvedFeat.luas_hektar ?? 0,
        keliling_meter: resolvedFeat.properties?.keliling_meter ?? resolvedFeat.keliling_meter ?? 0,
      },
    };
  }

  rightPanelMode.value = "edit";

  if (viewMode.value === "table") {
    viewMode.value = "map";
  }

  nextTick(() => {
    scheduleMapResize();
    if (isDesktop.value) {
      expandRightPanel();
    } else {
      openMobileTab("right");
    }
    if (resolvedGeom) {
      getActiveMapRef()?.fitToGeometry(resolvedGeom);
    }
  });
}

function handleCancelForm() {
  formNameError.value = "";
  formGeometryError.value = "";
  rightPanelMode.value = "inspect";
  if (isDesktop.value && !selectedFeature.value) {
    collapseRightPanel();
  }
  if (!isDesktop.value && !selectedFeature.value) {
    mobileDrawerOpen.value = false;
  }
}

async function handleSaveForm() {
  const isEditing = rightPanelMode.value === "edit";
  if (!isEditing && (!canCreate.value || isDesaRestricted.value)) return;
  if (isEditing && !canEditFeature({ id: formState.id, id_kecamatan: formState.id_kecamatan })) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda tidak diizinkan mengubah wilayah desa ini.",
      color: "error",
    });
    return;
  }

  formNameError.value = "";
  formGeometryError.value = "";

  if (!formState.nama_desa.trim()) {
    formNameError.value = "Nama desa wajib diisi.";
    toast.add({ title: "Validasi Gagal", description: "Nama desa wajib diisi.", color: "error" });
    return;
  }

  const parsedSummary = parseDesaGeometryInput(formState.geometry);
  if (!parsedSummary.valid || !parsedSummary.geometry) {
    formGeometryError.value = parsedSummary.error || "Geometry harus berupa GeoJSON Polygon atau MultiPolygon yang valid.";
    toast.add({
      title: "Format Geometri Salah",
      description: formGeometryError.value,
      color: "error",
    });
    return;
  }

  submitting.value = true;
  try {
    const kecId = isKecamatanRestricted.value
      ? userKecamatanId.value
      : (formState.id_kecamatan ? Number(formState.id_kecamatan) : null);

    const payload = {
      nama_desa: formState.nama_desa.trim(),
      id_kecamatan: kecId,
      nama_pimpinan: formState.nama_pimpinan.trim() || null,
      nama_jabatan: formState.nama_jabatan.trim() || null,
      nip: formState.nip.trim() || null,
      pangkat_gol: formState.pangkat_gol.trim() || null,
      geometry: parsedSummary.geometry,
    };

    let savedFeature: any = null;
    if (isEditing && formState.id) {
      const res = await $http<{ ok: boolean; feature?: any }>(`admin/batas-wilayah-desa/${formState.id}`, {
        method: "PUT",
        body: payload,
      });
      savedFeature = res?.feature ?? null;
      toast.add({ title: "Berhasil", description: "Data desa berhasil diperbarui.", color: "success" });
      addLog("INFO", `Wilayah desa diperbarui: ${payload.nama_desa}`);
    } else {
      const res = await $http<{ ok: boolean; feature?: any }>("admin/batas-wilayah-desa", {
        method: "POST",
        body: payload,
      });
      savedFeature = res?.feature ?? null;
      toast.add({ title: "Berhasil", description: "Batas desa baru berhasil disimpan.", color: "success" });
      addLog("INFO", `Wilayah desa baru disimpan: ${payload.nama_desa}`);
    }

    rightPanelMode.value = "inspect";
    if (savedFeature) {
      selectedFeature.value = savedFeature;
    }

    await handleRefreshAll();
    if (parsedSummary.geometry) {
      getActiveMapRef()?.fitToGeometry(parsedSummary.geometry);
    }
  } catch (err: any) {
    const msg = err?.data?.message || "Terjadi kesalahan saat menyimpan data batas wilayah desa.";
    toast.add({ title: "Gagal Menyimpan", description: msg, color: "error" });
    addLog("ERROR", `Gagal simpan wilayah desa: ${msg}`);
  } finally {
    submitting.value = false;
  }
}

// ─── Delete Modal ─────────────────────────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const featureToDelete = ref<any | null>(null);
const deleting = ref(false);

function confirmDelete(feat: any) {
  if (!canDeleteFeature(feat)) {
    toast.add({
      title: "Akses Ditolak",
      description: "Anda tidak memiliki izin untuk menghapus wilayah desa ini.",
      color: "error",
    });
    return;
  }
  featureToDelete.value = feat;
  isDeleteModalOpen.value = true;
}

async function handleDelete() {
  if (!canDelete.value || !featureToDelete.value) return;
  deleting.value = true;

  try {
    const id = Number(featureToDelete.value.id || featureToDelete.value.properties?.id);
    const name = featureToDelete.value.properties?.nama_desa || featureToDelete.value.nama_desa || `ID ${id}`;
    await $http(`admin/batas-wilayah-desa/${id}`, {
      method: "DELETE",
    });
    toast.add({ title: "Terhapus", description: "Data wilayah desa berhasil dihapus.", color: "success" });
    addLog("INFO", `Wilayah desa dihapus: ${name}`);

    if (activeSelectedId.value === id) {
      selectedFeature.value = null;
      rightPanelMode.value = "inspect";
    }
    isDeleteModalOpen.value = false;
    await handleRefreshAll();
  } catch (err: any) {
    const msg = err?.data?.message || "Gagal menghapus data wilayah desa.";
    toast.add({ title: "Gagal Menghapus", description: msg, color: "error" });
    addLog("ERROR", `Gagal hapus wilayah desa: ${msg}`);
  } finally {
    deleting.value = false;
  }
}

watch(viewMode, (mode) => {
  if (mode === "table") {
    refreshTable();
  } else if (mode === "map") {
    nextTick(() => {
      scheduleMapResize();
      if (pendingMapSyncFromTableFilter) {
        pendingMapSyncFromTableFilter = false;
        if (selectedKecamatan.value?.bbox) {
          getActiveMapRef()?.safeFitBounds(selectedKecamatan.value.bbox, 64, 15, 450);
        } else {
          fitMapToBounds();
        }
      }
    });
  }
});
</script>

<template>
  <div class="flex flex-col w-full h-[calc(100dvh-3.5rem)] overflow-hidden bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-gray-100">
    <!-- ═══ TOPBAR: Title, Navigation Series, View Mode (Peta / Tabel), Refresh, Add ═══ -->
    <header class="border-b border-gray-200 dark:border-gray-800 shrink-0 z-30 bg-white dark:bg-[#0b0f19]">
      <div class="flex items-center justify-between gap-2 min-h-11 px-3 sm:px-4">
        <!-- Left: Title + Summary Counts + Desktop Series Navigation -->
        <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
          <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <UIcon name="i-lucide-map" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
            <h1 class="text-xs font-semibold text-gray-900 dark:text-white tracking-wide truncate">
              Wilayah Desa
            </h1>
          </div>

          <USeparator orientation="vertical" class="h-4 hidden sm:block shrink-0" />

          <div v-if="!loading" class="flex items-center gap-1.5 shrink-0">
            <UBadge
              :label="`${totalDesa.toLocaleString('id-ID')} desa`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="tabular-nums"
            />
            <UBadge
              :label="`${totalLuasHektar} Ha`"
              color="primary"
              variant="subtle"
              size="xs"
              class="tabular-nums hidden xl:inline-flex"
            />
            <UBadge
              v-if="isKecamatanRestricted && auth.user?.kecamatan?.nama_kecamatan"
              :label="`Kec. ${auth.user.kecamatan.nama_kecamatan}`"
              color="info"
              variant="subtle"
              size="xs"
              icon="i-lucide-map-pin"
              class="hidden md:inline-flex"
            />
            <UBadge
              v-if="isDesaRestricted && auth.user?.desa?.nama_desa"
              :label="`Desa ${auth.user.desa.nama_desa}`"
              color="warning"
              variant="subtle"
              size="xs"
              icon="i-lucide-home"
              class="hidden md:inline-flex cursor-pointer"
              title="Klik untuk fokus ke wilayah desa Anda di peta"
              @click="focusUserDesa"
            />
          </div>
          <div v-else class="flex items-center gap-1.5 shrink-0">
            <USkeleton class="h-5 w-16 rounded" />
          </div>

          <!-- Desktop Inline Feature Navigation Series -->
          <template v-if="viewMode === 'map'">
            <USeparator orientation="vertical" class="h-4 hidden lg:block shrink-0" />

            <div class="hidden lg:flex items-center gap-1.5 min-w-0">
              <div class="flex items-center gap-0.5 bg-gray-100 dark:bg-white/[0.06] border border-gray-200/70 dark:border-white/[0.08] rounded-lg p-0.5 shrink-0">
                <button
                  type="button"
                  :disabled="kecamatanList.length === 0 || isKecamatanRestricted"
                  aria-label="Kecamatan sebelumnya"
                  title="Kecamatan Sebelumnya (Map Series)"
                  class="size-6 inline-flex items-center justify-center rounded-md text-gray-700 hover:bg-white hover:text-gray-900 disabled:opacity-45 disabled:cursor-not-allowed dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="prevKecamatan"
                >
                  <UIcon name="i-lucide-chevron-left" class="size-3.5" aria-hidden="true" />
                </button>

                <span class="text-[11px] font-medium text-gray-800 dark:text-gray-200 px-1.5 min-w-[74px] text-center select-none truncate tabular-nums">
                  <template v-if="selectedKecamatanId !== null && currentSeriesIndex >= 0">
                    Series {{ currentSeriesIndex + 1 }}/{{ kecamatanList.length }}
                  </template>
                  <template v-else>
                    Bojonegoro
                  </template>
                </span>

                <button
                  type="button"
                  :disabled="kecamatanList.length === 0 || isKecamatanRestricted"
                  aria-label="Kecamatan berikutnya"
                  title="Kecamatan Berikutnya (Map Series)"
                  class="size-6 inline-flex items-center justify-center rounded-md text-gray-700 hover:bg-white hover:text-gray-900 disabled:opacity-45 disabled:cursor-not-allowed dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="nextKecamatan"
                >
                  <UIcon name="i-lucide-chevron-right" class="size-3.5" aria-hidden="true" />
                </button>
              </div>

              <select
                :value="selectedKecamatanId ?? ''"
                :disabled="isKecamatanRestricted"
                aria-label="Navigasi wilayah kecamatan"
                class="w-44 xl:w-52 h-7 text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2 focus-visible:outline-2 focus-visible:outline-blue-600 transition-colors disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
                @change="(e: any) => selectKecamatan(e.target.value ? Number(e.target.value) : null)"
              >
                <option v-if="!isKecamatanRestricted" value="">Semua Kecamatan ({{ kecamatanList.length }})</option>
                <option
                  v-for="kec in kecamatanList"
                  :key="kec.id"
                  :value="kec.id"
                >
                  Kec. {{ kec.nama_kecamatan }} ({{ kec.jumlah_desa }} Desa)
                </option>
              </select>

              <button
                v-if="selectedKecamatanId !== null && !isKecamatanRestricted"
                type="button"
                aria-label="Reset navigasi ke seluruh Bojonegoro"
                title="Reset ke Seluruh Wilayah Bojonegoro"
                class="h-7 px-2 inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 text-[11px] font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-blue-600"
                @click="resetToAllBojonegoro"
              >
                <UIcon name="i-lucide-rotate-ccw" class="size-3" aria-hidden="true" />
                <span>Reset</span>
              </button>
            </div>
          </template>
        </div>

        <!-- Right: View Mode (Peta / Tabel) + Refresh + Panel Toggle + Tambah -->
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <!-- Mode Toggle: Peta / Tabel -->
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
              label="Tabel"
              size="xs"
              :color="viewMode === 'table' ? 'primary' : 'neutral'"
              :variant="viewMode === 'table' ? 'solid' : 'ghost'"
              class="text-xs font-medium cursor-pointer"
              @click="viewMode = 'table'"
            />
          </div>

          <USeparator orientation="vertical" class="h-4 hidden sm:block" />

          <UTooltip text="Sinkronkan Data & Peta">
            <UButton
              icon="i-lucide-refresh-cw"
              size="xs"
              color="neutral"
              variant="ghost"
              :loading="loading || (viewMode === 'table' && tableStatus === 'pending')"
              aria-label="Muat ulang data spasial"
              class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white cursor-pointer"
              @click="handleRefreshAll"
            />
          </UTooltip>

          <UTooltip
            v-if="viewMode === 'map' && isDesktop"
            :text="allPanelsHidden ? 'Tampilkan panel' : 'Mode peta penuh'"
          >
            <UButton
              icon="i-lucide-layout-dashboard"
              size="xs"
              color="neutral"
              :variant="allPanelsHidden ? 'subtle' : 'ghost'"
              aria-label="Ubah visibilitas panel"
              :class="
                allPanelsHidden
                  ? 'text-blue-600 dark:text-blue-400 cursor-pointer'
                  : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white cursor-pointer'
              "
              @click="toggleAllPanels"
            />
          </UTooltip>

          <UButton
            v-if="canCreate && !isDesaRestricted"
            icon="i-lucide-plus"
            label="Tambah Data"
            size="xs"
            color="primary"
            variant="solid"
            class="hidden sm:inline-flex cursor-pointer"
            @click="openCreatePanel"
          />
          <UButton
            v-if="canCreate && !isDesaRestricted"
            icon="i-lucide-plus"
            size="xs"
            color="primary"
            variant="solid"
            aria-label="Tambah wilayah desa baru"
            class="sm:hidden cursor-pointer"
            @click="openCreatePanel"
          />
        </div>
      </div>

      <!-- Mobile & Tablet (< 1024px) Feature Navigation Series Strip -->
      <div
        v-if="viewMode === 'map'"
        class="lg:hidden flex items-center gap-1.5 px-3 py-1.5 border-t border-gray-100 dark:border-gray-800/80 bg-gray-50/70 dark:bg-[#070b14]/50 overflow-x-auto"
      >
        <div class="flex items-center gap-0.5 bg-white dark:bg-[#131926] border border-gray-200 dark:border-gray-700 rounded-lg p-0.5 shrink-0">
          <button
            type="button"
            :disabled="kecamatanList.length === 0 || isKecamatanRestricted"
            aria-label="Kecamatan sebelumnya"
            class="min-h-[36px] min-w-[36px] inline-flex items-center justify-center rounded-md text-gray-700 hover:bg-gray-100 disabled:opacity-45 disabled:cursor-not-allowed dark:text-gray-200 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="prevKecamatan"
          >
            <UIcon name="i-lucide-chevron-left" class="size-4" aria-hidden="true" />
          </button>

          <span class="text-[11px] font-medium text-gray-800 dark:text-gray-200 px-1.5 min-w-[70px] text-center truncate tabular-nums">
            <template v-if="selectedKecamatanId !== null && currentSeriesIndex >= 0">
              Series {{ currentSeriesIndex + 1 }}/{{ kecamatanList.length }}
            </template>
            <template v-else>
              Bojonegoro
            </template>
          </span>

          <button
            type="button"
            :disabled="kecamatanList.length === 0 || isKecamatanRestricted"
            aria-label="Kecamatan berikutnya"
            class="min-h-[36px] min-w-[36px] inline-flex items-center justify-center rounded-md text-gray-700 hover:bg-gray-100 disabled:opacity-45 disabled:cursor-not-allowed dark:text-gray-200 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="nextKecamatan"
          >
            <UIcon name="i-lucide-chevron-right" class="size-4" aria-hidden="true" />
          </button>
        </div>

        <select
          :value="selectedKecamatanId ?? ''"
          :disabled="isKecamatanRestricted"
          aria-label="Navigasi wilayah kecamatan"
          class="flex-1 min-w-[140px] min-h-[38px] text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
          @change="(e: any) => selectKecamatan(e.target.value ? Number(e.target.value) : null)"
        >
          <option v-if="!isKecamatanRestricted" value="">Semua Kecamatan ({{ kecamatanList.length }})</option>
          <option
            v-for="kec in kecamatanList"
            :key="kec.id"
            :value="kec.id"
          >
            Kec. {{ kec.nama_kecamatan }} ({{ kec.jumlah_desa }} Desa)
          </option>
        </select>

        <button
          v-if="selectedKecamatanId !== null && !isKecamatanRestricted"
          type="button"
          aria-label="Reset navigasi wilayah"
          class="min-h-[38px] px-2.5 inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="resetToAllBojonegoro"
        >
          <UIcon name="i-lucide-rotate-ccw" class="size-3.5" aria-hidden="true" />
          <span>Reset</span>
        </button>
      </div>
    </header>

    <!-- ═══ VIEW 1: MAP WORKBENCH (Without Left Panel; Right Panel Sticks to Map Canvas) ═══ -->
    <div
      v-show="viewMode === 'map'"
      class="flex flex-1 overflow-hidden relative w-full h-full min-h-0"
    >
      <!-- Desktop (>= 1024px): Two-Tier USplitter -->
      <div v-if="isDesktop" class="flex flex-1 w-full h-full overflow-hidden">
        <USplitter
          ref="verticalSplitterRef"
          :items="verticalSplitterItems"
          orientation="vertical"
          class="w-full h-full"
          :ui="{
            root: 'h-full w-full',
            panel: isDraggingVertical ? '' : 'panel-smooth-transition',
            handle: 'h-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-row-resize',
          }"
          @dragging="onVerticalDragging"
        >
          <!-- Top Workspace: Map Canvas + Sticky Right Panel (No Left Panel) -->
          <template #workspace>
            <USplitter
              ref="horizontalSplitterRef"
              :items="horizontalSplitterItems"
              orientation="horizontal"
              class="w-full h-full"
              :ui="{
                root: 'h-full w-full',
                panel: isDraggingHorizontal ? '' : 'panel-smooth-transition',
                handle: 'w-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-col-resize',
              }"
              @dragging="onHorizontalDragging"
            >
              <!-- MapLibre Canvas -->
              <template #map>
                <div class="w-full h-full relative overflow-hidden">
                  <WilayahDesaMaplibre
                    ref="mapViewerRef"
                    :selected-kecamatan-id="selectedKecamatanId"
                    :selected-desa-id="activeSelectedId"
                    :selected-geometry="activeSelectedGeometry"
                    :form-preview-geometry="formPreviewGeometry"
                    :summary-bbox="summaryData?.bbox ?? null"
                    :active-kecamatan-bbox="selectedKecamatan?.bbox ?? null"
                    :active-kecamatan-name="selectedKecamatan?.nama_kecamatan ?? null"
                    :resolve-kecamatan-name="getKecamatanName"
                    :refresh-nonce="mapRefreshNonce"
                    :basemap="currentBasemap"
                    :clicked-coordinate="clickedCoordinate"
                    @update:basemap="setBasemapChoice($event)"
                    @select-desa="handleMapSelectDesa"
                    @clear-selection="clearSelection"
                    @fit-bojonegoro="resetToAllBojonegoro"
                    @focus-kecamatan="selectedKecamatanId && selectKecamatan(selectedKecamatanId)"
                    @map-click="(coord) => (clickedCoordinate = coord)"
                    @log="addLog"
                  />

                  <!-- Floating Reopen Right Panel Button when collapsed with active selection or form -->
                  <button
                    v-if="(selectedFeature || rightPanelMode !== 'inspect') && rightPanelCollapsed"
                    type="button"
                    class="absolute bottom-11 right-3 z-20 inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-white/95 px-3 py-1.5 text-xs font-semibold text-blue-900 shadow-lg backdrop-blur-md hover:bg-blue-50 dark:border-blue-700 dark:bg-[#0e1424]/95 dark:text-blue-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="expandRightPanel"
                  >
                    <UIcon name="i-lucide-panel-right-open" class="size-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                    <span>
                      {{ rightPanelMode === 'create' ? 'Buka Form Tambah' : rightPanelMode === 'edit' ? 'Buka Form Edit' : 'Buka Detail Desa' }}
                    </span>
                  </button>
                </div>
              </template>

              <!-- Sticky Right Panel: Inspector + Create/Edit Form -->
              <template #right="{ collapsed, collapse, expand, resize }">
                <span :class="syncRightPanelActions(collapsed, collapse, expand, resize)" class="hidden" />
                <WilayahDesaRightPanel
                  :collapsed="collapsed"
                  :collapsible="true"
                  :mode="rightPanelMode"
                  :selected-feature="selectedFeature"
                  :loading-feature-detail="loadingFeatureDetail"
                  :clicked-coordinate="clickedCoordinate"
                  :kecamatan-list="kecamatanList"
                  :selected-kecamatan-id="selectedKecamatanId"
                  :is-kecamatan-restricted="isKecamatanRestricted"
                  :is-desa-restricted="isDesaRestricted"
                  :can-create="canCreate"
                  :can-edit="canEditFeature(selectedFeature)"
                  :can-delete="canDeleteFeature(selectedFeature)"
                  :submitting="submitting"
                  :form-state="formState"
                  v-model:form-name-error="formNameError"
                  v-model:form-geometry-error="formGeometryError"
                  :resolve-kecamatan-name="getKecamatanName"
                  @update:collapsed="(c) => { c ? collapse() : (resize ? resize(RIGHT_PANEL_WIDTH) : expand()); scheduleMapResize(); }"
                  @open-create="openCreatePanel"
                  @open-edit="openEditPanel"
                  @cancel-form="handleCancelForm"
                  @submit-form="handleSaveForm"
                  @zoom-to-selected="zoomToSelectedDesa"
                  @focus-form-geometry="(g) => mapViewerRef?.fitToGeometry(g)"
                  @focus-kecamatan="(id) => selectKecamatan(id)"
                  @request-delete="confirmDelete"
                  @clear-selection="clearSelection"
                  @view-in-table="handleViewInTable"
                />
              </template>
            </USplitter>
          </template>

          <!-- Bottom Collapsible Drawer: Attribute Table & Console -->
          <template #bottom="{ collapsed, collapse, expand, resize }">
            <span :class="syncBottomPanelActions(collapsed, collapse, expand, resize)" class="hidden" />
            <WilayahDesaBottomPanel
              :collapsed="collapsed"
              :collapsible="true"
              v-model:active-tab="bottomActiveTab"
              v-model:table-search="tableSearch"
              v-model:table-page="tablePage"
              v-model:table-per-page="tablePerPage"
              :table-rows="formattedTableFeatures"
              :table-total="tableTotal"
              :table-last-page="tableLastPage"
              :table-status="tableStatus"
              :console-logs="consoleLogs"
              :selected-feature-id="activeSelectedId"
              :kecamatan-list="kecamatanList"
              :selected-kecamatan-id="tableKecamatanFilter"
              :is-kecamatan-restricted="isKecamatanRestricted"
              :has-active-filter="Boolean(tableKecamatanFilter && !isKecamatanRestricted)"
              :resolve-kecamatan-name="getKecamatanName"
              :can-edit-row="canEditFeature"
              :can-delete-row="canDeleteFeature"
              @update:collapsed="(c) => { c ? collapse() : (resize ? resize(BOTTOM_PANEL_HEIGHT) : expand()); handleBottomPanelExpandChange(c); scheduleMapResize(); }"
              @update:selected-kecamatan-id="selectKecamatan"
              @reset-filters="resetTableFilters"
              @retry-fetch="refreshTable"
              @row-click="(row) => zoomToFeature(row.raw)"
              @edit-row="(row) => openEditPanel(row.raw)"
              @delete-row="(row) => confirmDelete(row.raw)"
              @clear-logs="consoleLogs = []"
            />
          </template>
        </USplitter>
      </div>

      <!-- Mobile & Tablet (< 1024px): Full-Bleed Map + Floating Peek Card + Bottom Dock -->
      <div v-else class="flex-1 relative overflow-hidden h-full w-full">
        <WilayahDesaMaplibre
          ref="mobileMapViewerRef"
          :selected-kecamatan-id="selectedKecamatanId"
          :selected-desa-id="activeSelectedId"
          :selected-geometry="activeSelectedGeometry"
          :form-preview-geometry="formPreviewGeometry"
          :summary-bbox="summaryData?.bbox ?? null"
          :active-kecamatan-bbox="selectedKecamatan?.bbox ?? null"
          :active-kecamatan-name="selectedKecamatan?.nama_kecamatan ?? null"
          :resolve-kecamatan-name="getKecamatanName"
          :refresh-nonce="mapRefreshNonce"
          :basemap="currentBasemap"
          :clicked-coordinate="clickedCoordinate"
          @update:basemap="setBasemapChoice($event)"
          @select-desa="handleMapSelectDesa"
          @clear-selection="clearSelection"
          @fit-bojonegoro="resetToAllBojonegoro"
          @focus-kecamatan="selectedKecamatanId && selectKecamatan(selectedKecamatanId)"
          @map-click="(coord) => (clickedCoordinate = coord)"
          @log="addLog"
        />

        <!-- Floating Mobile Feature Peek Card -->
        <div
          v-if="selectedFeature && !mobileDrawerOpen && rightPanelMode === 'inspect'"
          class="fixed bottom-[calc(4.25rem+env(safe-area-inset-bottom))] left-3 right-3 sm:left-auto sm:right-3 sm:w-96 z-25 pointer-events-auto"
        >
          <div class="p-3 rounded-xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200 dark:border-gray-800 shadow-xl space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-gray-900 dark:text-white truncate">
                  {{ selectedFeature.properties?.nama_desa || 'Wilayah Desa' }}
                </p>
                <p class="text-[11px] text-gray-600 dark:text-gray-400 truncate">
                  Kec. {{ getKecamatanName(selectedFeature.properties?.id_kecamatan) }}
                </p>
              </div>
              <button
                type="button"
                aria-label="Tutup kartu ringkas desa"
                class="min-h-[36px] min-w-[36px] inline-flex items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                @click="clearSelection"
              >
                <UIcon name="i-lucide-x" class="size-4" aria-hidden="true" />
              </button>
            </div>

            <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-gray-100 dark:border-gray-800 text-xs">
              <span class="font-semibold text-blue-600 dark:text-blue-400 tabular-nums">
                {{ Number(selectedFeature.properties?.luas_hektar || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} Ha
              </span>

              <div class="flex items-center gap-1.5">
                <button
                  type="button"
                  aria-label="Zoom ke desa terpilih"
                  class="min-h-[40px] min-w-[40px] inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="zoomToSelectedDesa"
                >
                  <UIcon name="i-lucide-locate-fixed" class="size-4" aria-hidden="true" />
                </button>
                <button
                  v-if="canEditFeature(selectedFeature)"
                  type="button"
                  class="min-h-[40px] px-3 inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-800 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="openEditPanel(selectedFeature)"
                >
                  <UIcon name="i-lucide-pencil" class="size-3.5" aria-hidden="true" />
                  <span>Edit</span>
                </button>
                <button
                  type="button"
                  class="min-h-[40px] px-3 inline-flex items-center gap-1 rounded-lg bg-blue-600 text-xs font-semibold text-white hover:bg-blue-700 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="openMobileTab('right')"
                >
                  <UIcon name="i-lucide-info" class="size-3.5" aria-hidden="true" />
                  <span>Detail</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile Bottom Navigation Dock (>= 44px Touch Targets) -->
        <nav
          aria-label="Navigasi Panel Wilayah Desa Mobile"
          class="fixed bottom-0 left-0 right-0 z-30 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-gray-200 dark:border-gray-800 px-2 py-1 pb-[max(0.4rem,env(safe-area-inset-bottom))] grid items-center select-none"
          :class="canCreate && !isDesaRestricted ? 'grid-cols-3' : 'grid-cols-2'"
        >
          <button
            type="button"
            class="flex flex-col items-center justify-center min-h-[44px] py-1 px-2 rounded-lg transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              mobileDrawerOpen && mobileDrawerTab === 'table'
                ? 'text-blue-600 dark:text-blue-400 font-semibold'
                : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
            "
            @click="openMobileTab('table')"
          >
            <div class="relative">
              <UIcon name="i-lucide-table" class="size-5" aria-hidden="true" />
              <span
                v-if="totalDesa > 0"
                class="absolute -top-1 -right-3 px-1 text-[9px] tabular-nums rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-semibold"
              >
                {{ totalDesa }}
              </span>
            </div>
            <span class="text-[11px] mt-0.5 leading-none">Tabel</span>
          </button>

          <button
            v-if="canCreate && !isDesaRestricted"
            type="button"
            class="flex flex-col items-center justify-center min-h-[44px] py-1 px-2 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="openCreatePanel"
          >
            <span class="size-7 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-sm">
              <UIcon name="i-lucide-plus" class="size-4" aria-hidden="true" />
            </span>
            <span class="text-[11px] mt-0.5 leading-none font-medium">Tambah</span>
          </button>

          <button
            type="button"
            class="flex flex-col items-center justify-center min-h-[44px] py-1 px-2 rounded-lg transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              mobileDrawerOpen && mobileDrawerTab === 'right'
                ? 'text-blue-600 dark:text-blue-400 font-semibold'
                : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
            "
            @click="openMobileTab('right')"
          >
            <div class="relative">
              <UIcon :name="rightPanelMode === 'inspect' ? 'i-lucide-info' : 'i-lucide-file-pen-line'" class="size-5" aria-hidden="true" />
              <span
                v-if="selectedFeature || rightPanelMode !== 'inspect'"
                class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-600 ring-2 ring-white dark:ring-[#0b0f19]"
              />
            </div>
            <span class="text-[11px] mt-0.5 leading-none">
              {{ rightPanelMode === 'create' ? 'Form Baru' : rightPanelMode === 'edit' ? 'Edit' : 'Detail' }}
            </span>
          </button>
        </nav>

        <!-- Mobile Bottom Sheet -->
        <MobileBottomSheet
          v-model:open="mobileDrawerOpen"
          :title="
            mobileDrawerTab === 'table'
              ? 'Tabel Wilayah Desa'
              : rightPanelMode === 'create'
                ? 'Tambah Desa Baru'
                : rightPanelMode === 'edit'
                  ? 'Edit Wilayah Desa'
                  : 'Detail Wilayah Desa'
          "
          :icon="
            mobileDrawerTab === 'table'
              ? 'i-lucide-table'
              : rightPanelMode === 'create'
                ? 'i-lucide-plus-circle'
                : rightPanelMode === 'edit'
                  ? 'i-lucide-pencil'
                  : 'i-lucide-info'
          "
        >
          <template #tabs>
            <div class="px-3 pt-1 pb-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40">
              <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 dark:bg-gray-800/80 rounded-xl">
                <button
                  type="button"
                  class="min-h-[40px] flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  :class="
                    mobileDrawerTab === 'table'
                      ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                      : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                  "
                  @click="mobileDrawerTab = 'table'; if (tableFeatures.length === 0) refreshTable()"
                >
                  <UIcon name="i-lucide-table" class="size-3.5 shrink-0" aria-hidden="true" />
                  <span>Tabel</span>
                </button>

                <button
                  type="button"
                  class="min-h-[40px] flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  :class="
                    mobileDrawerTab === 'right'
                      ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                      : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                  "
                  @click="mobileDrawerTab = 'right'"
                >
                  <UIcon :name="rightPanelMode === 'inspect' ? 'i-lucide-info' : 'i-lucide-file-pen-line'" class="size-3.5 shrink-0" aria-hidden="true" />
                  <span>
                    {{ rightPanelMode === 'create' ? 'Form Tambah' : rightPanelMode === 'edit' ? 'Form Edit' : 'Detail Desa' }}
                  </span>
                </button>
              </div>
            </div>
          </template>

          <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
            <WilayahDesaBottomPanel
              v-if="mobileDrawerTab === 'table'"
              :is-mobile-drawer="true"
              :collapsible="false"
              active-tab="table"
              v-model:table-search="tableSearch"
              v-model:table-page="tablePage"
              v-model:table-per-page="tablePerPage"
              :table-rows="formattedTableFeatures"
              :table-total="tableTotal"
              :table-last-page="tableLastPage"
              :table-status="tableStatus"
              :selected-feature-id="activeSelectedId"
              :kecamatan-list="kecamatanList"
              :selected-kecamatan-id="tableKecamatanFilter"
              :is-kecamatan-restricted="isKecamatanRestricted"
              :has-active-filter="Boolean(tableKecamatanFilter && !isKecamatanRestricted)"
              :resolve-kecamatan-name="getKecamatanName"
              :can-edit-row="canEditFeature"
              :can-delete-row="canDeleteFeature"
              @update:selected-kecamatan-id="selectKecamatan"
              @reset-filters="resetTableFilters"
              @retry-fetch="refreshTable"
              @row-click="(row) => { zoomToFeature(row.raw); mobileDrawerOpen = false; }"
              @edit-row="(row) => openEditPanel(row.raw)"
              @delete-row="(row) => confirmDelete(row.raw)"
            />

            <WilayahDesaRightPanel
              v-else
              :is-mobile-drawer="true"
              :collapsible="false"
              :mode="rightPanelMode"
              :selected-feature="selectedFeature"
              :loading-feature-detail="loadingFeatureDetail"
              :clicked-coordinate="clickedCoordinate"
              :kecamatan-list="kecamatanList"
              :selected-kecamatan-id="selectedKecamatanId"
              :is-kecamatan-restricted="isKecamatanRestricted"
              :is-desa-restricted="isDesaRestricted"
              :can-create="canCreate"
              :can-edit="canEditFeature(selectedFeature)"
              :can-delete="canDeleteFeature(selectedFeature)"
              :submitting="submitting"
              :form-state="formState"
              v-model:form-name-error="formNameError"
              v-model:form-geometry-error="formGeometryError"
              :resolve-kecamatan-name="getKecamatanName"
              @open-create="openCreatePanel"
              @open-edit="openEditPanel"
              @cancel-form="handleCancelForm"
              @submit-form="handleSaveForm"
              @zoom-to-selected="zoomToSelectedDesa(); mobileDrawerOpen = false"
              @focus-form-geometry="(g) => { mobileMapViewerRef?.fitToGeometry(g); mobileDrawerOpen = false; }"
              @focus-kecamatan="(id) => { selectKecamatan(id); mobileDrawerOpen = false; }"
              @request-delete="confirmDelete"
              @clear-selection="clearSelection"
              @view-in-table="handleViewInTable"
            />
          </div>
        </MobileBottomSheet>
      </div>
    </div>

    <!-- ═══ VIEW 2: FULL TABULAR VIEW (Jalan Poros Reference) ═══ -->
    <div
      v-show="viewMode === 'table'"
      class="flex flex-col flex-1 w-full h-full overflow-hidden bg-gray-50/70 dark:bg-[#070b14]"
    >
      <!-- Tabular Header & Filter Toolbar -->
      <div class="p-3 sm:p-4 border-b border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] shrink-0 space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
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
              Daftar Wilayah Administrasi Desa
            </h2>
            <UBadge
              :label="`${tableTotal.toLocaleString('id-ID')} Desa`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="tabular-nums text-[11px]"
            />
            <UBadge
              v-if="!isKecamatanRestricted && selectedKecamatan"
              :label="`Filter: Kec. ${selectedKecamatan.nama_kecamatan}`"
              color="primary"
              variant="subtle"
              size="xs"
            />
            <UBadge
              v-if="isKecamatanRestricted && auth.user?.kecamatan?.nama_kecamatan"
              :label="`Kecamatan: ${auth.user.kecamatan.nama_kecamatan}`"
              color="info"
              variant="subtle"
              size="xs"
            />
            <UBadge
              v-if="isDesaRestricted && auth.user?.desa?.nama_desa"
              :label="`Desa: ${auth.user.desa.nama_desa}`"
              color="warning"
              variant="subtle"
              size="xs"
            />
          </div>

          <div class="flex items-center gap-2">
            <UButton
              v-if="canCreate && !isDesaRestricted"
              icon="i-lucide-plus"
              label="Tambah Desa Baru"
              size="xs"
              color="primary"
              variant="solid"
              class="cursor-pointer"
              @click="openCreatePanel"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 items-center">
          <UInput
            v-model="tableSearch"
            icon="i-lucide-search"
            placeholder="Cari desa, pimpinan, NIP..."
            aria-label="Cari data wilayah desa"
            size="xs"
            class="w-full"
          />

          <select
            :value="tableKecamatanFilter ?? ''"
            :disabled="isKecamatanRestricted"
            aria-label="Filter kecamatan pada tabel"
            class="h-8 text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
            @change="(e: any) => selectKecamatan(e.target.value ? Number(e.target.value) : null)"
          >
            <option v-if="!isKecamatanRestricted" value="">Semua Kecamatan ({{ kecamatanList.length }})</option>
            <option
              v-for="kec in kecamatanList"
              :key="kec.id"
              :value="kec.id"
            >
              Kec. {{ kec.nama_kecamatan }} ({{ kec.jumlah_desa }} Desa)
            </option>
          </select>

          <div class="flex items-center gap-1.5 sm:col-span-2 lg:col-span-2 justify-end">
            <UButton
              v-if="tableSearch || (tableKecamatanFilter && !isKecamatanRestricted)"
              icon="i-lucide-filter-x"
              label="Reset"
              size="xs"
              color="neutral"
              variant="subtle"
              class="cursor-pointer"
              @click="resetTableFilters"
            />
            <UButton
              icon="i-lucide-refresh-cw"
              size="xs"
              color="neutral"
              variant="outline"
              :loading="tableStatus === 'pending'"
              aria-label="Muat ulang tabel"
              title="Muat Ulang Tabel"
              class="cursor-pointer"
              @click="() => refreshTable()"
            />
          </div>
        </div>
      </div>

      <!-- Tabular Main Card -->
      <div class="flex-1 p-3 sm:p-4 overflow-hidden flex flex-col">
        <div class="flex flex-col flex-1 w-full h-full rounded-xl border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden shadow-2xs">
          <div class="flex-1 overflow-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0f1422] border-b border-gray-200 dark:border-white/[0.08] text-[11px] font-semibold uppercase text-gray-600 dark:text-gray-300 tracking-wider whitespace-nowrap">
                <tr>
                  <th class="py-2.5 px-3">Nama Desa</th>
                  <th class="py-2.5 px-3">Kecamatan</th>
                  <th class="py-2.5 px-3 text-right">Luas Wilayah (Ha)</th>
                  <th class="py-2.5 px-3">Pimpinan & Jabatan</th>
                  <th class="py-2.5 px-3">NIP / Pangkat</th>
                  <th class="py-2.5 px-3 text-center min-w-[130px]">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                <!-- Loading Skeleton -->
                <template v-if="tableStatus === 'pending' && formattedTableFeatures.length === 0">
                  <tr v-for="i in 8" :key="i">
                    <td class="py-3 px-3"><USkeleton class="h-4 w-32 rounded" /></td>
                    <td class="py-3 px-3"><USkeleton class="h-4 w-24 rounded" /></td>
                    <td class="py-3 px-3"><USkeleton class="h-4 w-16 ml-auto rounded" /></td>
                    <td class="py-3 px-3"><USkeleton class="h-4 w-36 rounded" /></td>
                    <td class="py-3 px-3"><USkeleton class="h-4 w-28 rounded" /></td>
                    <td class="py-3 px-3"><USkeleton class="h-4 w-24 mx-auto rounded" /></td>
                  </tr>
                </template>

                <!-- Error State -->
                <tr v-else-if="tableStatus === 'error'">
                  <td colspan="6" class="py-12 px-4 text-center">
                    <div class="flex flex-col items-center justify-center gap-2 max-w-sm mx-auto">
                      <UIcon name="i-lucide-alert-triangle" class="size-7 text-rose-600 dark:text-rose-400" aria-hidden="true" />
                      <p class="text-sm font-medium text-gray-900 dark:text-white">
                        Gagal memuat daftar wilayah desa
                      </p>
                      <p class="text-xs text-gray-600 dark:text-gray-400">
                        Terjadi gangguan saat mengambil data dari server.
                      </p>
                      <UButton
                        label="Coba Lagi"
                        icon="i-lucide-refresh-cw"
                        size="xs"
                        color="neutral"
                        variant="outline"
                        class="mt-1 cursor-pointer"
                        @click="() => refreshTable()"
                      />
                    </div>
                  </td>
                </tr>

                <!-- Empty State -->
                <tr v-else-if="formattedTableFeatures.length === 0">
                  <td colspan="6" class="py-12 px-4 text-center">
                    <div class="flex flex-col items-center justify-center gap-2 max-w-sm mx-auto">
                      <UIcon name="i-lucide-inbox" class="size-8 text-gray-500 dark:text-gray-400" aria-hidden="true" />
                      <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                        Tidak ada data batas wilayah desa yang cocok
                      </p>
                      <p class="text-xs text-gray-600 dark:text-gray-400">
                        Sesuaikan kata kunci pencarian atau reset filter kecamatan.
                      </p>
                      <UButton
                        v-if="tableSearch || (tableKecamatanFilter && !isKecamatanRestricted)"
                        label="Reset Filter"
                        size="xs"
                        color="neutral"
                        variant="subtle"
                        class="mt-1 cursor-pointer"
                        @click="resetTableFilters"
                      />
                    </div>
                  </td>
                </tr>

                <!-- Data Rows -->
                <tr
                  v-else
                  v-for="feat in formattedTableFeatures"
                  :key="feat.id"
                  class="transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
                  :class="activeSelectedId === Number(feat.id) ? 'bg-blue-50/70 dark:bg-blue-950/40' : ''"
                >
                  <td class="py-2.5 px-3 font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                    {{ feat.nama_desa }}
                  </td>
                  <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300 font-medium whitespace-nowrap">
                    {{ getKecamatanName(feat.id_kecamatan) }}
                  </td>
                  <td class="py-2.5 px-3 text-right text-blue-600 dark:text-blue-400 font-semibold whitespace-nowrap tabular-nums">
                    {{ Number(feat.luas_hektar || 0).toLocaleString("id-ID", { maximumFractionDigits: 2 }) }}
                  </td>
                  <td class="py-2.5 px-3 whitespace-nowrap">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                      {{ feat.nama_pimpinan }}
                    </div>
                    <div class="text-[11px] text-gray-600 dark:text-gray-400">
                      {{ feat.nama_jabatan }}
                    </div>
                  </td>
                  <td class="py-2.5 px-3 whitespace-nowrap">
                    <div class="tabular-nums text-gray-800 dark:text-gray-200">
                      {{ feat.nip }}
                    </div>
                    <div class="text-[11px] text-gray-600 dark:text-gray-400">
                      {{ feat.pangkat_gol }}
                    </div>
                  </td>
                  <td class="py-2.5 px-3 text-center whitespace-nowrap">
                    <div class="flex items-center justify-center gap-1">
                      <UButton
                        icon="i-lucide-map-pin"
                        size="xs"
                        color="primary"
                        variant="subtle"
                        label="Peta"
                        title="Lihat di Peta"
                        class="cursor-pointer text-xs"
                        @click="zoomToFeature(feat.raw)"
                      />
                      <UButton
                        v-if="canEditFeature(feat)"
                        icon="i-lucide-pencil"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        aria-label="Edit data desa"
                        title="Edit Data"
                        class="cursor-pointer"
                        @click="openEditPanel(feat.raw)"
                      />
                      <UButton
                        v-if="canDeleteFeature(feat)"
                        icon="i-lucide-trash-2"
                        size="xs"
                        color="error"
                        variant="ghost"
                        aria-label="Hapus data desa"
                        title="Hapus Data"
                        class="cursor-pointer"
                        @click="confirmDelete(feat.raw)"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination Footer -->
          <div
            v-if="tableTotal > 0"
            class="flex flex-col sm:flex-row items-center justify-between px-4 py-2 border-t border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-[#070b14] text-xs gap-3 shrink-0"
          >
            <span class="text-gray-600 dark:text-gray-400 text-xs tabular-nums">
              {{ tabularPageRangeText }}
            </span>

            <div class="flex items-center gap-3">
              <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                <label for="wd-table-perpage" class="hidden sm:inline">Tampilkan:</label>
                <select
                  id="wd-table-perpage"
                  v-model.number="tablePerPage"
                  class="text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2 py-1 focus-visible:outline-2 focus-visible:outline-blue-600 cursor-pointer"
                >
                  <option :value="10">10</option>
                  <option :value="15">15</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                  <option :value="100">100</option>
                </select>
                <span class="hidden sm:inline">per halaman</span>
              </div>

              <UPagination
                v-if="tableLastPage > 1"
                :page="tablePage"
                :total="tableTotal"
                :items-per-page="tablePerPage"
                size="xs"
                color="neutral"
                active-color="primary"
                @update:page="(p) => (tablePage = p)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ MODAL: KONFIRMASI HAPUS ═══ -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Hapus Batas Wilayah Desa"
      description="Apakah Anda yakin ingin menghapus data wilayah desa ini dari database PostGIS?"
      :ui="{ content: 'max-w-sm bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08]' }"
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
          Data wilayah
          <span class="font-semibold text-gray-900 dark:text-white">
            {{ featureToDelete?.properties?.nama_desa || featureToDelete?.nama_desa || 'desa ini' }}
          </span>
          akan dihapus permanen.
        </p>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="isDeleteModalOpen = false"
          />
          <UButton
            v-if="canDelete && !isDesaRestricted"
            label="Hapus Permanen"
            color="error"
            :loading="deleting"
            class="cursor-pointer"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

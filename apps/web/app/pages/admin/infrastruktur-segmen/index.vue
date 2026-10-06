<script setup lang="ts">
import type { SplitterItem } from '@nuxt/ui';
import MapCanvas from '~/components/dataset/infrastruktur-segmen/MapCanvas.vue';
import LeftPanel from '~/components/dataset/infrastruktur-segmen/LeftPanel.vue';
import RightPanel from '~/components/dataset/infrastruktur-segmen/RightPanel.vue';
import BottomPanel from '~/components/dataset/infrastruktur-segmen/BottomPanel.vue';
import MobileBottomSheet from '~/components/dataset/infrastruktur-segmen/MobileBottomSheet.vue';
import DialogFilter from '~/components/dataset/infrastruktur-segmen/DialogFilter.vue';
import DialogSimbology from '~/components/dataset/infrastruktur-segmen/DialogSimbology.vue';
import FilterContent from '~/components/dataset/infrastruktur-segmen/FilterContent.vue';
import SimbologyContent from '~/components/dataset/infrastruktur-segmen/SimbologyContent.vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { usePermission } from '~/composables/usePermission';
import { useAuthStore } from '~/stores/auth';
import { useWilayahStore } from '~/stores/wilayah';
import type {
  GeoJsonFeatureCollection,
  InfrastrukturSegmen,
  InfrastrukturTipe,
  PlottingAnggaran,
} from '~/types/infrastruktur';
import type {
  BasemapType,
  ConsoleLog,
  GisLayerItem,
  LayerId,
  LayerSymbology,
} from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY } from '~/types/dataset-editor';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: [
    'infrastruktur-segmen-view',
    'infrastruktur-segmen-create',
  ],
  fullBleed: true,
  key: (route) => route.path,
});

useSeoMeta({
  title: 'Editor Peta Spasial Segmen Fisik Infrastruktur',
});

const toast = useToast();
const auth = useAuthStore();
const wilayahStore = useWilayahStore();
const api = useInfrastrukturApi();
const route = useRoute();
const router = useRouter();
const config = useRuntimeConfig();
const apiBase = config.public.apiBase || 'http://localhost:9000';
const { can, isAdmin } = usePermission();

// ─── Permissions ──────────────────────────────────────────────────────────────
const canView = computed(() => can('infrastruktur-segmen-view'));
const canCreate = computed(() => can('infrastruktur-segmen-create'));
const canEdit = computed(() => can('infrastruktur-segmen-update'));
const canDelete = computed(() => can('infrastruktur-segmen-delete'));
const canSubmit = computed(() => can('infrastruktur-segmen-create') || can('infrastruktur-segmen-update'));

// Territory Context & Role-Permission Scoping
const userKecamatanId = computed(() =>
  auth.user?.id_kecamatan != null ? Number(auth.user.id_kecamatan) : null
);
const userDesaId = computed(() =>
  auth.user?.id_desa != null ? Number(auth.user.id_desa) : null
);
const isBappeda = computed(() => auth.hasRole('verifierBappeda'));
const isKecamatanRole = computed(() => auth.hasRole('verifierKecamatan'));
const isKecamatanRestricted = computed(() => !isAdmin() && !isBappeda.value && !!userKecamatanId.value);
const isDesaRestricted = computed(() => !isAdmin() && !isBappeda.value && !!userDesaId.value);

const userRoleLabel = computed(() => {
  if (isAdmin()) return 'Admin';
  if (isBappeda.value) return 'Bappeda';
  if (isKecamatanRole.value) return 'Kecamatan';
  if (isDesaRestricted.value) return 'Desa';
  return 'Pengguna';
});

const kecamatanSelectOptions = computed(() => [
  { label: 'Semua Kecamatan', value: null },
  ...wilayahStore.kecamatanList.map((k) => ({
    label: k.nama_kecamatan,
    value: k.id,
  })),
]);

const desaSelectOptions = computed(() => {
  const currentKecId = filterKecamatan.value ? Number(filterKecamatan.value) : null;
  if (!currentKecId) {
    return [{ label: 'Pilih Kecamatan dulu', value: null }];
  }
  const list = wilayahStore.desaByKecamatan[currentKecId] || (wilayahStore.desaByKecamatan as any)[String(currentKecId)] || [];
  return [
    { label: 'Semua Desa', value: null },
    ...list.map((d: any) => ({
      label: d.nama_desa,
      value: Number(d.id),
    })),
  ];
});

function handleKecamatanFilterChange(val: any) {
  if (isKecamatanRestricted.value) return;
  const kecId = val === null || val === undefined || val === 'ALL' || val === '' ? null : Number(val);
  filterKecamatan.value = isNaN(kecId!) ? null : kecId;
  if (!isDesaRestricted.value) {
    filterDesa.value = null;
  }
}

function handleDesaFilterChange(val: any) {
  if (isDesaRestricted.value) return;
  const desaId = val === null || val === undefined || val === 'ALL' || val === '' ? null : Number(val);
  filterDesa.value = isNaN(desaId!) ? null : desaId;
}

function resetWilayahFilter() {
  if (!isKecamatanRestricted.value) filterKecamatan.value = null;
  if (!isDesaRestricted.value) filterDesa.value = null;
}

// ─── Workspace Splitters Layout ───────────────────────────────────────────────
const SIDE_PANEL_WIDTH = 300;
const SIDE_PANEL_MIN = 280;
const SIDE_PANEL_MAX = 480;

const horizontalSplitterItems = ref<SplitterItem[]>([
  {
    id: 'left-panel',
    slot: 'left',
    sizeUnit: 'px',
    defaultSize: SIDE_PANEL_WIDTH,
    minSize: SIDE_PANEL_MIN,
    maxSize: SIDE_PANEL_MAX,
    collapsible: true,
    collapsedSize: 42,
    class: 'h-full',
  },
  {
    id: 'map-canvas',
    slot: 'map',
    minSize: 25,
    class: 'flex flex-1 w-full h-full relative overflow-hidden',
  },
  {
    id: 'right-panel',
    slot: 'right',
    sizeUnit: 'px',
    defaultSize: 42,
    minSize: SIDE_PANEL_MIN,
    maxSize: SIDE_PANEL_MAX,
    collapsible: true,
    collapsedSize: 42,
    class: 'h-full',
  },
]);

const verticalSplitterItems = ref<SplitterItem[]>([
  {
    id: 'main-workspace',
    slot: 'workspace',
    minSize: 30,
    class: 'flex flex-1 w-full h-full overflow-hidden',
  },
  {
    id: 'bottom-panel',
    slot: 'bottom',
    sizeUnit: 'px',
    defaultSize: 40,
    minSize: 220,
    maxSize: 550,
    collapsible: true,
    collapsedSize: 40,
    class: 'flex flex-col h-full',
  },
]);

const horizontalSplitterRef = ref<any>(null);
const verticalSplitterRef = ref<any>(null);

// Drag responsiveness flags
const isDraggingHorizontal = ref(false);
const isDraggingVertical = ref(false);

function onHorizontalDragging(_index: number, dragging: boolean) {
  isDraggingHorizontal.value = dragging;
}

function onVerticalDragging(_index: number, dragging: boolean) {
  isDraggingVertical.value = dragging;
}

// Right panel auto-expansion synchronization
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
  return '';
}

function expandRightPanel() {
  if (rightPanelSlotData.value) {
    rightPanelSlotData.value.resize(SIDE_PANEL_WIDTH);
    if (rightPanelSlotData.value.collapsed) {
      rightPanelSlotData.value.expand?.();
    }
  } else {
    horizontalSplitterRef.value?.panelsRef?.[2]?.resize?.(SIDE_PANEL_WIDTH);
    horizontalSplitterRef.value?.panelsRef?.[2]?.expand?.();
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

// Focus / Map-Only Mode
const allPanelsHidden = ref(false);

function toggleAllPanels() {
  if (allPanelsHidden.value) {
    horizontalSplitterRef.value?.panelsRef?.[0]?.resize?.(SIDE_PANEL_WIDTH);
    expandRightPanel();
    verticalSplitterRef.value?.panelsRef?.[1]?.resize?.(280);
    bottomPanelOpen.value = true;
    allPanelsHidden.value = false;
    addLog('INFO', 'Panel editor ditampilkan kembali.');
  } else {
    horizontalSplitterRef.value?.panelsRef?.[0]?.collapse?.();
    collapseRightPanel();
    verticalSplitterRef.value?.panelsRef?.[1]?.collapse?.();
    bottomPanelOpen.value = false;
    allPanelsHidden.value = true;
    addLog('INFO', 'Mode peta penuh diaktifkan.');
  }
}

// ─── Filter State & Query Sync ────────────────────────────────────────────────
const filterTipe = ref<string | null>((route.query.tipe as string) || null);
const filterKondisi = ref<string | null>((route.query.kondisi as string) || null);
const filterStatusVerifikasi = ref<string | null>((route.query.status as string) || null);

// Territory Authority Sanitizer: Mencegah manipulasi query parameter oleh user
function getAuthorizedKecamatan(requested: any): number | null {
  if (isKecamatanRestricted.value) {
    return userKecamatanId.value;
  }
  if (!requested || requested === 'ALL' || requested === '') return null;
  const n = Number(requested);
  return isNaN(n) ? null : n;
}

function getAuthorizedDesa(requested: any): number | null {
  if (isDesaRestricted.value) {
    return userDesaId.value;
  }
  if (!requested || requested === 'ALL' || requested === '') return null;
  const n = Number(requested);
  return isNaN(n) ? null : n;
}

const filterKecamatan = ref<number | null>(getAuthorizedKecamatan(route.query.id_kecamatan));
const filterDesa = ref<number | null>(getAuthorizedDesa(route.query.id_desa));
const tableSearch = ref<string>((route.query.search as string) || '');
const tablePage = ref<number>(Math.max(1, Number(route.query.page) || 1));
const tablePerPage = ref<number>(Math.max(10, Math.min(100, Number(route.query.per_page) || 15)));

// Enforce wilayah kerja user secara ketat dan otomatis sync URL jika ada penyimpangan
watch(
  [userKecamatanId, userDesaId, isKecamatanRestricted, isDesaRestricted],
  ([newKecId, newDesaId, kecRestricted, desaRestricted]) => {
    let changed = false;
    if (kecRestricted && newKecId && filterKecamatan.value !== newKecId) {
      filterKecamatan.value = newKecId;
      changed = true;
    }
    if (desaRestricted && newDesaId && filterDesa.value !== newDesaId) {
      filterDesa.value = newDesaId;
      changed = true;
    }
    if (changed) {
      syncQueryToUrl();
    }
  },
  { immediate: true }
);

const activeFilterCount = computed(() => {
  let count = 0;
  if (filterTipe.value) count++;
  if (filterKondisi.value) count++;
  if (filterStatusVerifikasi.value) count++;
  if (filterKecamatan.value) count++;
  if (filterDesa.value) count++;
  return count;
});

function syncQueryToUrl() {
  const query: Record<string, string> = {};
  if (filterTipe.value) query.tipe = filterTipe.value;
  if (filterKondisi.value) query.kondisi = filterKondisi.value;
  if (filterStatusVerifikasi.value) query.status = filterStatusVerifikasi.value;

  // Pastikan URL hanya memuat wilayah yang sah bagi user
  const authorizedKec = getAuthorizedKecamatan(filterKecamatan.value);
  const authorizedDesa = getAuthorizedDesa(filterDesa.value);

  if (authorizedKec) query.id_kecamatan = String(authorizedKec);
  if (authorizedDesa) query.id_desa = String(authorizedDesa);
  if (tableSearch.value) query.search = tableSearch.value;
  if (tablePage.value > 1) query.page = String(tablePage.value);
  if (tablePerPage.value !== 15) query.per_page = String(tablePerPage.value);
  if (selectedSegmenId.value) query.id = selectedSegmenId.value;

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

// ─── Symbology State & Local Storage ──────────────────────────────────────────
const STORAGE_KEY_SYMBOLOGY = 'gis_symbology_infrastruktur_segmen';
const layerSymbology = ref<LayerSymbology>({ ...DEFAULT_SYMBOLOGY });
const layerVisible = ref(true);
const layerOpacity = ref(1);
const mouseCoords = ref('');
const clickedCoordinate = ref<[number, number] | null>(null);
const currentBasemap = ref<BasemapType>('osm');
const datasetBbox = ref<[number, number, number, number] | null>(null);

onMounted(() => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY_SYMBOLOGY);
    if (saved) {
      const parsed = JSON.parse(saved);
      layerSymbology.value = { ...DEFAULT_SYMBOLOGY, ...parsed };
    }
  } catch (err) {
    console.error('Gagal memuat preferensi simbologi:', err);
  }
});

watch(
  layerSymbology,
  (val) => {
    try {
      localStorage.setItem(STORAGE_KEY_SYMBOLOGY, JSON.stringify(val));
    } catch (err) {
      console.error('Gagal menyimpan preferensi simbologi:', err);
    }
  },
  { deep: true }
);

// ─── Multi-Layer Registry & State ───────────────────────────────────────────
const activeLayerId = ref<string>('infrastruktur-segmen');

// State Layer 2: MVT Jalan Poros Desa
const STORAGE_KEY_SYMBOLOGY_JALAN = 'gis_symbology_jalan_poros_desa';
const jalanPorosDesaVisible = ref(true);
const jalanPorosDesaOpacity = ref(0.85);
const jalanPorosDesaSymbology = ref<LayerSymbology>({
  ...DEFAULT_SYMBOLOGY,
  lineColor: '#2563eb', // Royal Blue
  lineWidth: 2,
});

const filterJalanKecamatan = ref<number | null>(
  isKecamatanRestricted.value ? userKecamatanId.value : null
);
const filterJalanDesa = ref<number | null>(
  isDesaRestricted.value ? userDesaId.value : null
);
const filterJalanKondisi = ref<string | null>(null);
const filterJalanPerkerasan = ref<string | null>(null);

const activeSegmenFilterCount = computed(() => {
  let count = 0;
  if (filterTipe.value) count++;
  if (filterKondisi.value) count++;
  if (filterStatusVerifikasi.value) count++;
  if (filterKecamatan.value) count++;
  if (filterDesa.value) count++;
  return count;
});

const activeJalanFilterCount = computed(() => {
  let count = 0;
  if (filterJalanPerkerasan.value) count++;
  if (filterJalanKondisi.value) count++;
  if (filterJalanKecamatan.value) count++;
  if (filterJalanDesa.value) count++;
  return count;
});

const activeLayerTitle = computed(() => {
  return activeLayerId.value === 'jalan-poros-desa' ? 'Jalan Poros Desa' : 'Segmen Fisik Infrastruktur';
});

function handleApplySegmenFilter() {
  isFilterDialogOpen.value = false;
  fitAllBounds();
}

function handleApplyJalanFilter() {
  isFilterDialogOpen.value = false;
  mapCanvasRef.value?.refreshJalanPorosVectorTiles();
  mobileMapCanvasRef.value?.refreshJalanPorosVectorTiles();
}

function resetSegmenFilters() {
  resetFilters();
}

function resetJalanFilters() {
  filterJalanPerkerasan.value = null;
  filterJalanKondisi.value = null;
  if (!isKecamatanRestricted.value) filterJalanKecamatan.value = null;
  if (!isDesaRestricted.value) filterJalanDesa.value = null;
  mapCanvasRef.value?.refreshJalanPorosVectorTiles();
  mobileMapCanvasRef.value?.refreshJalanPorosVectorTiles();
}

onMounted(() => {
  try {
    const savedJalan = localStorage.getItem(STORAGE_KEY_SYMBOLOGY_JALAN);
    if (savedJalan) {
      jalanPorosDesaSymbology.value = { ...DEFAULT_SYMBOLOGY, lineColor: '#2563eb', ...JSON.parse(savedJalan) };
    }
  } catch (err) {
    console.error('Gagal memuat preferensi simbologi jalan poros desa:', err);
  }
});

watch(
  jalanPorosDesaSymbology,
  (val) => {
    try {
      localStorage.setItem(STORAGE_KEY_SYMBOLOGY_JALAN, JSON.stringify(val));
    } catch (err) {
      console.error('Gagal menyimpan preferensi simbologi jalan poros desa:', err);
    }
  },
  { deep: true }
);

// Unified Layer List for LeftPanel
const gisLayers = computed<GisLayerItem[]>(() => [
  {
    id: 'infrastruktur-segmen',
    title: 'Segmen Fisik Infrastruktur',
    subtitle: 'PostGIS Vector Tiles',
    sourceType: 'mvt',
    visible: layerVisible.value,
    opacity: layerOpacity.value,
    symbology: layerSymbology.value,
    isPrimary: true,
    canEditGeometry: true,
    canDelete: false,
    hasFilter: activeFilterCount.value > 0,
    activeFilterCount: activeFilterCount.value,
    metadata: {
      tipeData: 'Vector Tiles (MVT)',
      totalItem: totalSegmen.value,
      totalPanjangMeter: totalPanjangMeter.value,
      sumberData: 'Dinas PU & Bappeda',
      srs: 'EPSG:4326 (WGS84)',
    },
    kondisiStats: kondisiStats.value,
  },
  {
    id: 'jalan-poros-desa',
    title: 'Jalan Poros Desa',
    subtitle: 'PostGIS Vector Tiles Referensi',
    sourceType: 'mvt',
    visible: jalanPorosDesaVisible.value,
    opacity: jalanPorosDesaOpacity.value,
    symbology: jalanPorosDesaSymbology.value,
    isPrimary: false,
    canEditGeometry: false,
    canDelete: false,
    hasFilter: activeJalanFilterCount.value > 0,
    activeFilterCount: activeJalanFilterCount.value,
    metadata: {
      tipeData: 'Vector Tiles (MVT)',
      sumberData: 'Database Jalan Poros Desa',
      srs: 'EPSG:4326 (WGS84)',
    },
  },
]);

function handleUpdateLayerVisible(layerId: string, val: boolean) {
  if (layerId === 'infrastruktur-segmen') {
    layerVisible.value = val;
  } else if (layerId === 'jalan-poros-desa') {
    jalanPorosDesaVisible.value = val;
  }
}

function handleUpdateLayerOpacity(layerId: string, val: number) {
  if (layerId === 'infrastruktur-segmen') {
    layerOpacity.value = val;
  } else if (layerId === 'jalan-poros-desa') {
    jalanPorosDesaOpacity.value = val;
  }
}

function handleOpenFilterForLayer(layerId?: string) {
  activeLayerId.value = layerId || 'infrastruktur-segmen';
  isFilterDialogOpen.value = true;
}

function handleOpenSymbologyForLayer(layerId?: string) {
  activeLayerId.value = layerId || 'infrastruktur-segmen';
  isSymbologyDialogOpen.value = true;
}

function handleMobileOpenFilter(layerId?: string) {
  activeLayerId.value = layerId || 'infrastruktur-segmen';
  switchMobileTab('filter');
}

function handleMobileOpenSymbology(layerId?: string) {
  activeLayerId.value = layerId || 'infrastruktur-segmen';
  switchMobileTab('symbology');
}

function handleZoomToLayer(layerId?: string) {
  fitAllBounds();
}

// ─── Console Logs State ───────────────────────────────────────────────────────
const consoleLogs = ref<ConsoleLog[]>([]);

function addLog(level: 'INFO' | 'WARN' | 'ERROR', message: string) {
  const time = new Date().toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
  consoleLogs.value.push({ time, level, message });
  if (consoleLogs.value.length > 300) {
    consoleLogs.value.shift();
  }
}

// ─── Data State ───────────────────────────────────────────────────────────────
const mapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);
const mobileMapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);

function fitAllBounds(customBbox?: [number, number, number, number] | null) {
  const target = customBbox || datasetBbox.value;
  mapCanvasRef.value?.fitBounds(target);
  mobileMapCanvasRef.value?.fitBounds(target);
}

function refreshAllVectorTiles() {
  mapCanvasRef.value?.refreshVectorTiles();
  mobileMapCanvasRef.value?.refreshVectorTiles();
}

const tipeList = ref<InfrastrukturTipe[]>([]);
const plottingList = ref<PlottingAnggaran[]>([]);
const kecamatanList = computed(() => wilayahStore.kecamatanList);
const desaList = computed(() => (filterKecamatan.value ? wilayahStore.desaByKecamatan[filterKecamatan.value] || [] : []));
const tableRows = ref<InfrastrukturSegmen[]>([]);
const tableTotal = ref(0);
const tableLastPage = ref(1);
const selectedSegmenId = ref<string | null>(null);
const selectedSegmen = ref<InfrastrukturSegmen | null>(null);

const summaryTotalSegmen = ref<number | null>(null);
const summaryTotalPanjangMeter = ref<number | null>(null);
const kondisiStats = ref<Array<{ kondisi: string; jumlah: number; color?: string; panjang_meter?: number }>>([]);

const totalSegmen = computed(() => summaryTotalSegmen.value ?? tableTotal.value);

const loadingMap = ref(false);
const loadingTable = ref(false);
const isDrawingMode = ref(false);
const bottomActiveTab = ref<'table' | 'console'>('table');

// Track whether bottom panel is open (non-collapsed); used for on-demand fetch & sync
const bottomPanelOpen = ref(false);

// Total panjang terhitung
const totalPanjangMeter = computed(() => {
  if (summaryTotalPanjangMeter.value != null) {
    return summaryTotalPanjangMeter.value;
  }
  return tableRows.value.reduce((acc, row) => {
    return acc + Number(row.panjang_meter_gis ?? row.panjang ?? 0);
  }, 0);
});

// Watch filterKecamatan to load desa list for label lookup with in-memory caching
watch(
  filterKecamatan,
  async (kecId) => {
    if (kecId) {
      await wilayahStore.getDesaList(kecId);
    }
  },
  { immediate: true }
);

const currentKecamatanName = computed(() => {
  if (auth.user?.kecamatan?.nama_kecamatan) return auth.user.kecamatan.nama_kecamatan;
  if (!filterKecamatan.value) return null;
  return wilayahStore.getKecamatanName(filterKecamatan.value);
});

const currentDesaName = computed(() => {
  if (auth.user?.desa?.nama_desa) return auth.user.desa.nama_desa;
  if (!filterDesa.value) return null;
  return wilayahStore.getDesaName(filterDesa.value, filterKecamatan.value);
});


// ─── API Loaders ──────────────────────────────────────────────────────────────
async function loadMasterData() {
  try {
    const promises: Promise<any>[] = [
      api.fetchTipeList({ active_only: true }),
      api.fetchPlottingList({ per_page: 100 }),
      wilayahStore.getKecamatanList(),
    ];
    if (filterKecamatan.value) {
      promises.push(wilayahStore.getDesaList(Number(filterKecamatan.value)));
    }
    const [tipeRes, plottingRes] = await Promise.all(promises);
    if (tipeRes?.data) tipeList.value = tipeRes.data;
    if (plottingRes?.data) plottingList.value = plottingRes.data;
  } catch (err: any) {
    addLog('ERROR', `Gagal memuat master data: ${err?.message}`);
  }
}

async function loadSummary(shouldFitBounds = false) {
  try {
    const params: Record<string, any> = {};
    if (filterTipe.value) params.tipe_kode = filterTipe.value;
    if (filterKondisi.value) params.kondisi = filterKondisi.value;
    if (filterStatusVerifikasi.value) params.status_verifikasi = filterStatusVerifikasi.value;
    if (filterKecamatan.value) params.id_kecamatan = filterKecamatan.value;
    if (filterDesa.value) params.id_desa = filterDesa.value;

    const res = await api.fetchSegmenSummary(params);
    if (res?.ok) {
      if (res.bbox) {
        datasetBbox.value = res.bbox;
        if (shouldFitBounds) {
          fitAllBounds(res.bbox);
        }
      }
      summaryTotalSegmen.value = res.total_segmen;
      summaryTotalPanjangMeter.value = res.total_panjang_meter;
      kondisiStats.value = res.kondisi || [];
    }
  } catch (err: any) {
    console.error('Gagal memuat ringkasan spasial:', err);
  }
}

async function loadTable() {
  if (loadingTable.value) return;
  loadingTable.value = true;
  try {
    const params: Record<string, any> = {
      page: tablePage.value,
      per_page: tablePerPage.value,
    };
    if (filterTipe.value) params.tipe_kode = filterTipe.value;
    if (filterKondisi.value) params.kondisi = filterKondisi.value;
    if (filterStatusVerifikasi.value) params.status_verifikasi = filterStatusVerifikasi.value;
    if (filterKecamatan.value) params.id_kecamatan = filterKecamatan.value;
    if (filterDesa.value) params.id_desa = filterDesa.value;
    if (tableSearch.value) params.search = tableSearch.value;

    const res = await api.fetchSegmenList(params);
    if (res?.data) {
      tableRows.value = res.data;
      tableTotal.value = res.meta?.total ?? res.data.length;
      tableLastPage.value = res.meta?.last_page ?? 1;
    }
  } catch (err: any) {
    addLog('ERROR', `Gagal memuat tabel segmen: ${err?.message}`);
  } finally {
    loadingTable.value = false;
  }
}

// Triggered when bottom panel is toggled open in desktop
function handleBottomPanelExpandChange(isCollapsed: boolean) {
  bottomPanelOpen.value = !isCollapsed;
  if (bottomPanelOpen.value && tableRows.value.length === 0) {
    loadTable();
  }
}

async function refreshAll(shouldFit = true) {
  refreshAllVectorTiles();
  await Promise.all([loadSummary(shouldFit), loadTable()]);
  addLog('INFO', 'Data spasial dan tabel diperbarui.');
}

async function handleRefreshAll() {
  const tasks: Promise<any>[] = [loadMasterData(), loadSummary(true)];
  if (bottomPanelOpen.value) {
    tasks.push(loadTable());
  } else {
    loadTable();
  }
  await Promise.all(tasks);
  refreshAllVectorTiles();
  addLog('INFO', 'Data spasial telah disinkronkan.');
  toast.add({
    title: 'Data Diperbarui',
    description: 'Dataset Segmen Fisik Infrastruktur telah disinkronkan.',
    color: 'success',
  });
}

function resetFilters() {
  if (isKecamatanRestricted.value && userKecamatanId.value) {
    filterKecamatan.value = userKecamatanId.value;
  } else {
    filterKecamatan.value = null;
  }

  if (isDesaRestricted.value && userDesaId.value) {
    filterDesa.value = userDesaId.value;
  } else {
    filterDesa.value = null;
  }

  filterTipe.value = null;
  filterKondisi.value = null;
  filterStatusVerifikasi.value = null;
  tableSearch.value = '';
  tablePage.value = 1;
  syncQueryToUrl();
  refreshAll(true);
  addLog('INFO', 'Filter data direset.');
}

// Guard: suppress page-watcher's loadTable() when page is reset programmatically
let programmaticPageReset = false;

// Watch filters: reset page, sync URL, refresh once and fitbounds to filtered dataset
watch([filterTipe, filterKondisi, filterStatusVerifikasi, filterKecamatan, filterDesa], () => {
  programmaticPageReset = true;
  tablePage.value = 1;
  programmaticPageReset = false;
  syncQueryToUrl();
  refreshAll(true);
});

// Page navigation: sync URL + refresh (skipped when triggered by programmatic page reset)
watch(tablePage, () => {
  syncQueryToUrl();
  if (!programmaticPageReset) loadTable();
}, { flush: 'sync' });

let searchDebounceTimer: any = null;
watch(tableSearch, () => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    programmaticPageReset = true;
    tablePage.value = 1;
    programmaticPageReset = false;
    syncQueryToUrl();
    // Pencarian tabel tidak mempengaruhi MVT — hanya reload data tabular
    loadTable();
  }, 300);
});

// Sync from URL back to refs when browser navigation (back/forward or address bar edit) occurs
watch(
  () => route.query,
  (newQ) => {
    filterTipe.value = (newQ.tipe as string) || null;
    filterKondisi.value = (newQ.kondisi as string) || null;
    filterStatusVerifikasi.value = (newQ.status as string) || null;

    const authorizedKec = getAuthorizedKecamatan(newQ.id_kecamatan);
    const authorizedDesa = getAuthorizedDesa(newQ.id_desa);

    // Deteksi jika user mencoba memanipulasi parameter wilayah di address bar browser
    let hasViolation = false;
    if (newQ.id_kecamatan && authorizedKec !== Number(newQ.id_kecamatan)) {
      hasViolation = true;
    }
    if (newQ.id_desa && authorizedDesa !== Number(newQ.id_desa)) {
      hasViolation = true;
    }

    filterKecamatan.value = authorizedKec;
    filterDesa.value = authorizedDesa;

    if (hasViolation) {
      toast.add({
        title: 'Akses Wilayah Dibatasi',
        description: 'Parameter wilayah disesuaikan kembali dengan wilayah kerja Anda.',
        color: 'warning',
        icon: 'i-lucide-shield-alert',
      });
      syncQueryToUrl();
    }

    tableSearch.value = (newQ.search as string) || '';
    tablePage.value = Math.max(1, Number(newQ.page) || 1);
    tablePerPage.value = Math.max(10, Math.min(100, Number(newQ.per_page) || 15));

    const qId = (newQ.id as string) || (newQ.segmen_id as string) || null;
    if (!qId && selectedSegmenId.value) {
      selectedSegmenId.value = null;
      selectedSegmen.value = null;
      collapseRightPanel();
    } else if (qId && qId !== selectedSegmenId.value) {
      api.getSegmen(qId).then((res) => {
        if (res?.ok && res.data) {
          handleFeatureSelect(res.data, true);
        }
      }).catch(() => {});
    }
  }
);

// Per-page change: reset page + sync URL + refresh once
watch(tablePerPage, () => {
  programmaticPageReset = true;
  tablePage.value = 1;
  programmaticPageReset = false;
  syncQueryToUrl();
  loadTable();
});

// Watch selection to expand/collapse Right Panel
watch(selectedSegmen, (seg) => {
  if (seg) {
    nextTick(() => expandRightPanel());
  } else {
    nextTick(() => collapseRightPanel());
  }
});

// ─── Selection Logic ──────────────────────────────────────────────────────────
const isDetailLoading = ref(false);

function canEditSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canEdit.value) return false;
  if (isAdmin()) return true;
  if (isKecamatanRestricted.value && Number(seg.id_kecamatan) !== Number(userKecamatanId.value)) {
    return false;
  }
  if (isDesaRestricted.value && Number(seg.id_desa) !== Number(userDesaId.value)) {
    return false;
  }
  return true;
}

function canDeleteSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canDelete.value) return false;
  if (isAdmin()) return true;
  if (isKecamatanRestricted.value && Number(seg.id_kecamatan) !== Number(userKecamatanId.value)) {
    return false;
  }
  if (isDesaRestricted.value && Number(seg.id_desa) !== Number(userDesaId.value)) {
    return false;
  }
  return true;
}

async function handleFeatureSelect(feat: any | null, shouldZoom = false) {
  if (!feat) {
    selectedSegmenId.value = null;
    selectedSegmen.value = null;
    collapseRightPanel();
    syncQueryToUrl();
    return;
  }

  const id = String(feat.id || feat.properties?.id);
  selectedSegmenId.value = id;
  syncQueryToUrl();

  const p = feat.properties || {};

  // 1. Immediately populate from MVT properties + matching table row
  const rowMatch = tableRows.value.find((r) => String(r.id) === id);
  const matchedTipe = rowMatch?.tipe || tipeList.value.find((t) => t.kode === (p.tipe_kode || rowMatch?.tipe_kode));

  let rawGeom = feat.geometry || null;
  if (!rawGeom && rowMatch?.geojson) {
    try {
      rawGeom = typeof rowMatch.geojson === 'string' ? JSON.parse(rowMatch.geojson) : rowMatch.geojson;
    } catch {
      // keep null
    }
  }

  selectedSegmen.value = {
    id,
    namobj: p.namobj || rowMatch?.namobj || 'Segmen Tanpa Nama',
    tipe_kode: p.tipe_kode || rowMatch?.tipe_kode || '',
    tipe: matchedTipe,
    panjang: p.panjang ?? rowMatch?.panjang ?? p.panjang_meter_gis ?? null,
    panjang_meter_gis: p.panjang_meter_gis ?? rowMatch?.panjang_meter_gis ?? p.panjang ?? null,
    lebar: p.lebar ?? rowMatch?.lebar ?? null,
    kondisi: p.kondisi || rowMatch?.kondisi || 'Baik',
    status_kondisi: p.status_kondisi || rowMatch?.status_kondisi || 'Eksisting',
    tahun_pembangunan: p.tahun_pembangunan ?? rowMatch?.tahun_pembangunan ?? null,
    sumber_dana: p.sumber_dana || rowMatch?.sumber_dana || null,
    status_verifikasi: p.status_verifikasi || rowMatch?.status_verifikasi || 'draft',
    status_aset: p.status_aset || rowMatch?.status_aset || null,
    desa: p.desa || rowMatch?.desa || null,
    kecamatan: p.kecamatan || rowMatch?.kecamatan || null,
    id_desa: p.id_desa ?? rowMatch?.id_desa ?? null,
    id_kecamatan: p.id_kecamatan ?? rowMatch?.id_kecamatan ?? null,
    plotting_id: p.plotting_id || rowMatch?.plotting_id || null,
    plotting: rowMatch?.plotting,
    geometry: rawGeom,
    geojson: rowMatch?.geojson,
    centroid: p.centroid || rowMatch?.centroid || null,
  } as any;

  // 2. Auto-expand right panel immediately
  nextTick(() => expandRightPanel());
  if (shouldZoom) {
    mapCanvasRef.value?.zoomToFeature(selectedSegmen.value);
    mobileMapCanvasRef.value?.zoomToFeature(selectedSegmen.value);
  }

  addLog('INFO', `Segmen dipilih: ${p.namobj || id}`);

  // 3. Fetch full detail from API to guarantee complete attributes
  isDetailLoading.value = true;
  try {
    const res = await api.getSegmen(id);
    if (res?.ok && res.data && selectedSegmenId.value === id) {
      selectedSegmen.value = {
        ...selectedSegmen.value,
        ...res.data,
      };
    }
  } catch {
    // Keep local MVT attributes if request fails
  } finally {
    if (selectedSegmenId.value === id) {
      isDetailLoading.value = false;
    }
  }
}

function handleZoomToFeature(feat: any) {
  handleFeatureSelect(feat, true);
  mapCanvasRef.value?.zoomToFeature(feat);
  mobileMapCanvasRef.value?.zoomToFeature(feat);
  addLog('INFO', `Zoom ke segmen: ${feat.namobj || feat.properties?.namobj || feat.id}`);
}

// ─── Digitasi & Create Modal ──────────────────────────────────────────────────
const isCreateModalOpen = ref(false);
const isCreating = ref(false);

const createForm = reactive({
  namobj: '',
  tipe_kode: '',
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  plotting_id: null as string | null,
  panjang: 0,
  lebar: 3.5,
  kondisi: 'Baik' as any,
  status_kondisi: 'Eksisting' as any,
  tahun_pembangunan: new Date().getFullYear(),
  sumber_dana: 'APBD',
  status_aset: 'Aset Desa',
  sumber_data: 'Digitasi Canvas GIS',
  keterangan: '',
  geometry: null as any,
});

function handleStartDrawing() {
  isDrawingMode.value = true;
  mapCanvasRef.value?.startDrawing();
  mobileMapCanvasRef.value?.startDrawing();
  addLog('INFO', 'Memulai digitasi segmen garis baru.');
}

function onDrawCanceled() {
  isDrawingMode.value = false;
  addLog('INFO', 'Mode gambar dibatalkan.');
}

function handleCancelDrawing() {
  isDrawingMode.value = false;
  mapCanvasRef.value?.cancelDrawing();
  mobileMapCanvasRef.value?.cancelDrawing();
}

function handleRedraw() {
  mapCanvasRef.value?.cancelDrawing(false);
  mobileMapCanvasRef.value?.cancelDrawing(false);
  nextTick(() => {
    mapCanvasRef.value?.startDrawing();
    mobileMapCanvasRef.value?.startDrawing();
  });
  addLog('INFO', 'Menggambar ulang garis segmen.');
}

function handleUndo() {
  mapCanvasRef.value?.handleUndo();
  mobileMapCanvasRef.value?.handleUndo();
}

function handleRedo() {
  mapCanvasRef.value?.handleRedo();
  mobileMapCanvasRef.value?.handleRedo();
}

function handleToggleSnap() {
  mapCanvasRef.value?.toggleSnapping();
  mobileMapCanvasRef.value?.toggleSnapping();
}

function handleDrawSaved(payload: { geojson: any; lengthMeters: number }) {
  isDrawingMode.value = false;
  createForm.geometry = payload.geojson;
  createForm.panjang = Math.round(payload.lengthMeters);
  if (filterKecamatan.value) createForm.id_kecamatan = filterKecamatan.value;
  if (filterDesa.value) createForm.id_desa = filterDesa.value;
  if (filterTipe.value) createForm.tipe_kode = filterTipe.value;
  else if (tipeList.value.length > 0) createForm.tipe_kode = tipeList.value[0].kode;

  addLog('INFO', `Digitasi garis selesai. Panjang: ${createForm.panjang} m.`);
  isCreateModalOpen.value = true;
}

async function handleConfirmCreate() {
  if (!createForm.tipe_kode) {
    toast.add({ title: 'Validasi', description: 'Tipe infrastruktur wajib dipilih.', color: 'warning' });
    return;
  }
  if (!createForm.id_kecamatan || !createForm.id_desa) {
    toast.add({ title: 'Validasi', description: 'Kecamatan dan Desa wajib dipilih.', color: 'warning' });
    return;
  }
  if (!createForm.geometry) {
    toast.add({ title: 'Validasi', description: 'Geometri segmen tidak valid.', color: 'error' });
    return;
  }

  isCreating.value = true;
  try {
    const res = await api.createSegmen({
      tipe_kode: createForm.tipe_kode,
      namobj: createForm.namobj || 'Segmen Fisik Baru',
      id_kecamatan: createForm.id_kecamatan,
      id_desa: createForm.id_desa,
      plotting_id: createForm.plotting_id,
      panjang: createForm.panjang,
      lebar: createForm.lebar,
      kondisi: createForm.kondisi,
      status_kondisi: createForm.status_kondisi,
      tahun_pembangunan: createForm.tahun_pembangunan,
      sumber_dana: createForm.sumber_dana,
      status_aset: createForm.status_aset,
      sumber_data: createForm.sumber_data,
      keterangan: createForm.keterangan,
      geometry: createForm.geometry,
    });

    toast.add({
      title: 'Segmen Tersimpan',
      description: 'Segmen fisik baru berhasil disimpan sebagai draft.',
      color: 'success',
    });
    addLog('INFO', `Segmen fisik baru berhasil disimpan: ${createForm.namobj || 'Draft'}`);

    isCreateModalOpen.value = false;
    refreshAll();

    if (res?.data) {
      handleFeatureSelect(res.data);
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Terjadi kesalahan sistem.';
    toast.add({ title: 'Gagal Menyimpan', description: msg, color: 'error' });
    addLog('ERROR', `Gagal menyimpan segmen: ${msg}`);
  } finally {
    isCreating.value = false;
  }
}

// ─── Edit Action ──────────────────────────────────────────────────────────────
async function handleSaveSegmen(payload: Partial<InfrastrukturSegmen>) {
  if (!selectedSegmen.value) return;
  try {
    await api.updateSegmen(selectedSegmen.value.id, payload);
    toast.add({
      title: 'Perubahan Disimpan',
      description: 'Atribut segmen berhasil diperbarui.',
      color: 'success',
    });
    addLog('INFO', `Atribut segmen ${selectedSegmen.value.namobj || selectedSegmen.value.id} diperbarui.`);
    refreshAll();
    const res = await api.getSegmen(selectedSegmen.value.id);
    if (res?.data) selectedSegmen.value = res.data;
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Terjadi kesalahan.';
    toast.add({ title: 'Gagal Memperbarui', description: msg, color: 'error' });
    addLog('ERROR', `Gagal memperbarui segmen: ${msg}`);
  }
}

// ─── Delete Action ────────────────────────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const segmenToDelete = ref<InfrastrukturSegmen | null>(null);
const isDeleting = ref(false);

function confirmDelete(seg: InfrastrukturSegmen) {
  segmenToDelete.value = seg;
  isDeleteModalOpen.value = true;
}

async function handleDelete() {
  if (!segmenToDelete.value) return;
  isDeleting.value = true;
  try {
    await api.deleteSegmen(segmenToDelete.value.id);
    toast.add({
      title: 'Segmen Dihapus',
      description: 'Segmen berhasil dihapus dari sistem.',
      color: 'success',
    });
    addLog('WARN', `Segmen ${segmenToDelete.value.namobj || segmenToDelete.value.id} dihapus permanen.`);
    isDeleteModalOpen.value = false;
    if (selectedSegmenId.value === segmenToDelete.value.id) {
      selectedSegmenId.value = null;
      selectedSegmen.value = null;
      collapseRightPanel();
    }
    refreshAll();
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Tidak dapat menghapus segmen.';
    toast.add({ title: 'Gagal Menghapus', description: msg, color: 'error' });
    addLog('ERROR', `Gagal menghapus segmen: ${msg}`);
  } finally {
    isDeleting.value = false;
  }
}

// ─── Submit Verifikasi Action ─────────────────────────────────────────────────
async function handleSubmitVerifikasi(seg: InfrastrukturSegmen) {
  try {
    await api.submitSegmen(seg.id);
    toast.add({
      title: 'Verifikasi Diajukan',
      description: 'Segmen berhasil diajukan untuk verifikasi kecamatan.',
      color: 'success',
    });
    addLog('INFO', `Segmen ${seg.namobj || seg.id} diajukan untuk verifikasi kecamatan.`);
    refreshAll();
    if (selectedSegmen.value?.id === seg.id) {
      const res = await api.getSegmen(seg.id);
      if (res?.data) selectedSegmen.value = res.data;
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Tidak dapat mengajukan verifikasi.';
    toast.add({ title: 'Gagal Mengajukan', description: msg, color: 'error' });
    addLog('ERROR', `Gagal mengajukan verifikasi: ${msg}`);
  }
}

// ─── Desktop Filter & Simbology Dialog States ─────────────────────────────────
const isFilterDialogOpen = ref(false);
const isSymbologyDialogOpen = ref(false);

// ─── Mobile Drawer & Action Dock State ────────────────────────────────────────
export type MobileDrawerTabType = 'layer' | 'symbology' | 'filter' | 'table' | 'inspector';
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<MobileDrawerTabType>('layer');

function openMobileTab(tab: MobileDrawerTabType) {
  mobileDrawerTab.value = tab;
  mobileDrawerOpen.value = true;
  if (tab === 'table' && tableRows.value.length === 0) {
    loadTable();
  }
}

function switchMobileTab(tab: MobileDrawerTabType) {
  mobileDrawerTab.value = tab;
  if (tab === 'table' && tableRows.value.length === 0) {
    loadTable();
  }
}

function handleViewInTable(seg?: InfrastrukturSegmen | null) {
  bottomActiveTab.value = 'table';
  const targetName = seg?.namobj || selectedSegmen.value?.namobj;
  if (targetName) {
    tableSearch.value = targetName;
  }
  bottomPanelOpen.value = true;
  if (tableRows.value.length === 0) {
    loadTable();
  }
  nextTick(() => {
    verticalSplitterRef.value?.panelsRef?.[1]?.expand?.();
    verticalSplitterRef.value?.panelsRef?.[1]?.resize?.(280);
  });
}

// Initial mount & lifecycle
onMounted(async () => {
  // 1. Ambil Master Data Tipe & Plotting
  loadMasterData();

  // 2. Muat ringkasan spasial (bbox awal) & data tabel pertama kali
  await Promise.all([loadSummary(false), loadTable()]);

  // 3. Pastikan bottom panel tetap collapsed saat awal buka halaman (sesuai referensi jalan-poros-desa)
  nextTick(() => {
    if (!bottomPanelOpen.value) {
      verticalSplitterRef.value?.panelsRef?.[1]?.collapse?.();
    }
  });

  // 4. Hidrasi jika URL membawa ID segmen terpilih (?id=...)
  const initialId = (route.query.id as string) || (route.query.segmen_id as string) || null;
  if (initialId) {
    try {
      const res = await api.getSegmen(initialId);
      if (res?.ok && res.data) {
        handleFeatureSelect(res.data, true);
      }
    } catch {
      // Abaikan bila data tidak ditemukan
    }
  }

  addLog('INFO', 'Editor spasial siap digunakan.');
});
</script>

<template>
  <div class="flex flex-col w-full h-[calc(100vh-3.5rem)] overflow-hidden bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-gray-100 font-[Inter,sans-serif]">
    <!-- ═══ TOPBAR (Clean Solid Admin Header) ════════════════════════════════ -->
    <header class="flex items-center justify-between h-11 px-3 sm:px-4 border-b border-gray-200 dark:border-gray-800 shrink-0 z-30 bg-white dark:bg-[#0b0f19]">
      <!-- Left: Title & Overview Badges -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <UIcon name="i-lucide-network" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <h1 class="text-xs font-semibold text-gray-800 dark:text-gray-200 capitalize tracking-wide truncate">
            Segmen Fisik Infrastruktur
          </h1>
        </div>

        <USeparator orientation="vertical" class="h-4 hidden sm:block" />

        <div class="flex items-center gap-1 sm:gap-1.5">
          <UBadge
            :label="`${totalSegmen} segmen`"
            color="neutral"
            variant="subtle"
            size="xs"
            class="font-mono"
          />
          <UBadge
            :label="`${Math.round(totalPanjangMeter).toLocaleString('id-ID')} m`"
            color="primary"
            variant="subtle"
            size="xs"
            class="font-mono hidden sm:inline-flex"
          />
          <UBadge
            v-if="isDesaRestricted"
            label="Tingkat Desa"
            color="warning"
            variant="subtle"
            size="xs"
            icon="i-lucide-map-pin"
            class="hidden md:inline-flex"
          />
          <UBadge
            v-else-if="isKecamatanRestricted"
            label="Tingkat Kecamatan"
            color="info"
            variant="subtle"
            size="xs"
            icon="i-lucide-map-pin"
            class="hidden md:inline-flex"
          />
        </div>
      </div>

      <!-- Center: Filter Wilayah (Kecamatan & Desa) Terintegrasi Role Permission -->
      <div class="hidden md:flex items-center gap-1.5">
        <!-- Kecamatan Selector / Locked Badge -->
        <div
          v-if="isKecamatanRestricted"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-white dark:bg-[#0e1424] border border-gray-200/60 dark:border-gray-800 text-xs font-medium text-gray-700 dark:text-gray-300"
          :title="`Wilayah kerja Anda terkunci pada Kecamatan ${currentKecamatanName || ''}`"
        >
          <UIcon name="i-lucide-lock" class="size-3 text-amber-500 shrink-0" />
          <span class="truncate max-w-[130px]">Kec. {{ currentKecamatanName || 'Kerja' }}</span>
        </div>
        <USelectMenu
          v-else
          :model-value="filterKecamatan"
          :items="kecamatanSelectOptions"
          value-key="value"
          label-key="label"
          placeholder="Pilih Kecamatan"
          size="xs"
          class="w-36 lg:w-44 cursor-pointer"
          :leading-icon="'i-lucide-map-pin'"
          @update:model-value="handleKecamatanFilterChange"
        />

        <!-- Desa Selector / Locked Badge -->
        <div
          v-if="isDesaRestricted"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-white dark:bg-[#0e1424] border border-gray-200/60 dark:border-gray-800 text-xs font-medium text-gray-700 dark:text-gray-300"
          :title="`Wilayah kerja Anda terkunci pada Desa ${currentDesaName || ''}`"
        >
          <UIcon name="i-lucide-lock" class="size-3 text-amber-500 shrink-0" />
          <span class="truncate max-w-[130px]">Ds. {{ currentDesaName || 'Kerja' }}</span>
        </div>
        <USelectMenu
          v-else
          :model-value="filterDesa"
          :items="desaSelectOptions"
          value-key="value"
          label-key="label"
          :placeholder="filterKecamatan ? 'Pilih Desa' : 'Pilih Kecamatan dulu'"
          :disabled="!filterKecamatan"
          size="xs"
          class="w-36 lg:w-44 cursor-pointer"
          :leading-icon="'i-lucide-building-2'"
          @update:model-value="handleDesaFilterChange"
        />

        <!-- Reset Filter Wilayah (hanya jika admin/bappeda dan ada filter aktif) -->
        <UTooltip v-if="!isKecamatanRestricted && (filterKecamatan || filterDesa)" text="Reset Wilayah ke Semua">
          <UButton
            icon="i-lucide-rotate-ccw"
            size="xs"
            color="neutral"
            variant="ghost"
            class="size-6 p-0 flex items-center justify-center cursor-pointer text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
            @click="resetWilayahFilter"
          />
        </UTooltip>
      </div>

      <!-- Right: Action Buttons -->
      <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
        <UTooltip text="Sinkronkan Data & Peta">
          <UButton
            icon="i-lucide-refresh-cw"
            size="xs"
            color="neutral"
            variant="ghost"
            :loading="loadingMap || loadingTable"
            class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer"
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
              ? 'text-blue-600 dark:text-blue-400'
              : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            class="cursor-pointer"
            @click="toggleAllPanels"
          />
        </UTooltip>

        <UButton
          v-if="canCreate"
          icon="i-lucide-pen-tool"
          label="Mulai Digitasi"
          size="xs"
          color="warning"
          variant="solid"
          class="hidden sm:inline-flex cursor-pointer"
          :disabled="isDrawingMode"
          @click="handleStartDrawing"
        />
        <UButton
          v-if="canCreate"
          icon="i-lucide-pen-tool"
          size="xs"
          color="warning"
          variant="solid"
          class="sm:hidden cursor-pointer"
          :disabled="isDrawingMode"
          @click="handleStartDrawing"
        />
      </div>
    </header>

    <!-- ═══ MAIN WORKSPACE (USplitter with 60 FPS Drag Optimization) ══════════ -->
    <div class="flex flex-1 overflow-hidden relative w-full h-full">
      <!-- Desktop View: Nested Two-Tier USplitter -->
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
            handle: 'h-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-row-resize'
          }"
        >
          <!-- Workspace: Horizontal Splitter -->
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
                <LeftPanel
                  :collapsed="collapsed"
                  :collapsible="true"
                  :layers="gisLayers"
                  v-model:active-layer-id="activeLayerId"
                  v-model:layer-visible="layerVisible"
                  v-model:layer-opacity="layerOpacity"
                  v-model:symbology="layerSymbology"
                  :tipe-list="tipeList"
                  v-model:selected-tipe="filterTipe"
                  v-model:selected-kondisi="filterKondisi"
                  v-model:selected-status-verifikasi="filterStatusVerifikasi"
                  v-model:model-kecamatan="filterKecamatan"
                  v-model:model-desa="filterDesa"
                  :kecamatan-name="currentKecamatanName"
                  :desa-name="currentDesaName"
                  :is-kecamatan-locked="isKecamatanRestricted"
                  :is-desa-locked="isDesaRestricted"
                  :user-role-label="userRoleLabel"
                  :kondisi-stats="kondisiStats"
                  :total-segmen="totalSegmen"
                  :total-panjang-meter="totalPanjangMeter"
                  :is-drawing="isDrawingMode || (mapCanvasRef?.isDrawing ?? false)"
                  :can-undo="mapCanvasRef?.canUndo ?? false"
                  :can-redo="mapCanvasRef?.canRedo ?? false"
                  :can-save="mapCanvasRef?.canSave ?? false"
                  :is-snapping-enabled="mapCanvasRef?.isSnappingEnabled ?? true"
                  :snap-road-count="mapCanvasRef?.snapRoadCount ?? 0"
                  @update:collapsed="(c) => c ? collapse() : (resize ? resize(SIDE_PANEL_WIDTH) : expand())"
                  @update:layer-visible="handleUpdateLayerVisible"
                  @update:layer-opacity="handleUpdateLayerOpacity"
                  @start-drawing="handleStartDrawing"
                  @start-draw="handleStartDrawing"
                  @redraw="handleRedraw"
                  @undo="handleUndo"
                  @redo="handleRedo"
                  @toggle-snap="handleToggleSnap"
                  @cancel-draw="handleCancelDrawing"
                  @zoom-to-layer="handleZoomToLayer"
                  @reset-filters="resetFilters"
                  @apply-filter="fitAllBounds"
                  @open-filter="handleOpenFilterForLayer"
                  @open-symbology="handleOpenSymbologyForLayer"
                />
              </template>

              <!-- Map Canvas -->
              <template #map>
                <div class="w-full h-full relative overflow-hidden">
                  <MapCanvas
                    ref="mapCanvasRef"
                    :api-base="apiBase"
                    :selected-segmen-id="selectedSegmenId"
                    :selected-segmen="selectedSegmen"
                    :tipe-filter="filterTipe"
                    :kondisi-filter="filterKondisi"
                    :status-verifikasi-filter="filterStatusVerifikasi"
                    :kecamatan-filter="filterKecamatan"
                    :desa-filter="filterDesa"
                    :layer-visible="layerVisible"
                    :layer-opacity="layerOpacity"
                    :symbology="layerSymbology"
                    :jalan-poros-desa-visible="jalanPorosDesaVisible"
                    :jalan-poros-desa-opacity="jalanPorosDesaOpacity"
                    :jalan-poros-desa-symbology="jalanPorosDesaSymbology"
                    :jalan-poros-desa-kecamatan-filter="filterJalanKecamatan"
                    :jalan-poros-desa-desa-filter="filterJalanDesa"
                    :jalan-poros-desa-kondisi-filter="filterJalanKondisi"
                    :jalan-poros-desa-perkerasan-filter="filterJalanPerkerasan"
                    :bbox="datasetBbox"
                    :clicked-coordinate="clickedCoordinate"
                    v-model:basemap="currentBasemap"
                    v-model:mouse-coords="mouseCoords"
                    @select="handleFeatureSelect"
                    @log="(level, msg) => addLog(level, msg)"
                    @draw-saved="handleDrawSaved"
                    @draw-canceled="onDrawCanceled"
                    @map-click="(coord) => clickedCoordinate = coord"
                  />
                </div>
              </template>

              <!-- Panel Kanan: Properties / Feature Inspector -->
              <template #right="{ collapsed, collapse, expand, resize }">
                <span :class="syncRightPanelActions(collapsed, collapse, expand, resize)" class="hidden" />
                <RightPanel
                  :collapsed="collapsed"
                  :collapsible="true"
                  :selected-segmen="selectedSegmen"
                  :clicked-coordinate="clickedCoordinate"
                  :loading="isDetailLoading"
                  :tipe-list="tipeList"
                  :plotting-list="plottingList"
                  :can-edit="canEditSegmen(selectedSegmen)"
                  :can-delete="canDeleteSegmen(selectedSegmen)"
                  :can-submit="canSubmit"
                  @update:collapsed="(c) => c ? collapse() : (resize ? resize(SIDE_PANEL_WIDTH) : expand())"
                  @zoom-to-feature="handleZoomToFeature"
                  @delete-segmen="confirmDelete"
                  @submit-verifikasi="handleSubmitVerifikasi"
                  @save-segmen="handleSaveSegmen"
                  @view-in-table="handleViewInTable"
                />
              </template>
            </USplitter>
          </template>

          <!-- Panel Bawah: Tabel Atribut & Konsol -->
          <template #bottom="{ collapsed, collapse, expand, resize }">
            <BottomPanel
              :collapsed="collapsed"
              :collapsible="true"
              v-model:active-tab="bottomActiveTab"
              :table-rows="tableRows"
              :table-total="tableTotal"
              :table-page="tablePage"
              :table-last-page="tableLastPage"
              :table-per-page="tablePerPage"
              v-model:table-search="tableSearch"
              :table-loading="loadingTable"
              :selected-segmen-id="selectedSegmenId"
              :console-logs="consoleLogs"
              :can-edit="canEdit"
              :can-delete="canDelete"
              :can-submit="canSubmit"
              :tipe-list="tipeList"
              :selected-tipe="filterTipe"
              :selected-kondisi="filterKondisi"
              :selected-status-verifikasi="filterStatusVerifikasi"
              :model-kecamatan="filterKecamatan"
              :model-desa="filterDesa"
              :kecamatan-name="currentKecamatanName"
              :desa-name="currentDesaName"
              :can-reset-kecamatan="!isKecamatanRestricted"
              :can-reset-desa="!isDesaRestricted"
              @update:collapsed="(c) => { c ? collapse() : (resize ? resize(280) : expand()); handleBottomPanelExpandChange(c); }"
              @update:table-page="(p) => tablePage = p"
              @update:table-per-page="(pp) => tablePerPage = pp"
              @update:selected-tipe="(val) => filterTipe = val"
              @update:selected-kondisi="(val) => filterKondisi = val"
              @update:selected-status-verifikasi="(val) => filterStatusVerifikasi = val"
              @update:model-kecamatan="(val) => filterKecamatan = val"
              @update:model-desa="(val) => filterDesa = val"
              @reset-filters="resetFilters"
              @row-click="handleZoomToFeature"
              @edit-row="handleZoomToFeature"
              @delete-row="confirmDelete"
              @submit-row="handleSubmitVerifikasi"
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
          :selected-segmen-id="selectedSegmenId"
          :selected-segmen="selectedSegmen"
          :tipe-filter="filterTipe"
          :kondisi-filter="filterKondisi"
          :status-verifikasi-filter="filterStatusVerifikasi"
          :kecamatan-filter="filterKecamatan"
          :desa-filter="filterDesa"
          :layer-visible="layerVisible"
          :layer-opacity="layerOpacity"
          :symbology="layerSymbology"
          :jalan-poros-desa-visible="jalanPorosDesaVisible"
          :jalan-poros-desa-opacity="jalanPorosDesaOpacity"
          :jalan-poros-desa-symbology="jalanPorosDesaSymbology"
          :jalan-poros-desa-kecamatan-filter="filterJalanKecamatan"
          :jalan-poros-desa-desa-filter="filterJalanDesa"
          :jalan-poros-desa-kondisi-filter="filterJalanKondisi"
          :jalan-poros-desa-perkerasan-filter="filterJalanPerkerasan"
          :bbox="datasetBbox"
          :clicked-coordinate="clickedCoordinate"
          v-model:basemap="currentBasemap"
          v-model:mouse-coords="mouseCoords"
          @select="handleFeatureSelect"
          @log="(level, msg) => addLog(level, msg)"
          @draw-saved="handleDrawSaved"
          @draw-canceled="onDrawCanceled"
          @map-click="(coord) => clickedCoordinate = coord"
        />
      </div>
    </div>

    <!-- ═══ MOBILE FEATURE PEEK CARD ═════════════════════════════════════════ -->
    <div
      v-if="selectedSegmen"
      class="lg:hidden fixed bottom-16 left-3 right-3 z-30 pointer-events-auto transition-transform duration-200"
    >
      <div class="p-3 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-xl space-y-2">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate">
              {{ selectedSegmen.namobj || 'Segmen Tanpa Nama' }}
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate">
              {{ selectedSegmen.desa || '-' }}, Kec. {{ selectedSegmen.kecamatan || '-' }}
            </p>
          </div>
          <UButton
            icon="i-lucide-x"
            size="xs"
            color="neutral"
            variant="ghost"
            class="p-1 cursor-pointer"
            @click="selectedSegmen = null; selectedSegmenId = null"
          />
        </div>

        <div class="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-gray-800/80 text-xs">
          <div class="flex items-center gap-1.5">
            <UBadge
              :label="selectedSegmen.kondisi || '-'"
              color="primary"
              variant="subtle"
              size="xs"
            />
            <span class="text-[11px] font-mono text-gray-600 dark:text-gray-400">
              {{ Number(selectedSegmen.panjang_meter_gis ?? selectedSegmen.panjang ?? 0).toLocaleString('id-ID') }} m
            </span>
          </div>

          <div class="flex items-center gap-1">
            <UButton
              v-if="canEdit"
              icon="i-lucide-pencil"
              size="xs"
              color="neutral"
              variant="subtle"
              label="Edit"
              class="cursor-pointer"
              @click="openMobileTab('inspector')"
            />
            <UButton
              icon="i-lucide-info"
              size="xs"
              color="primary"
              variant="solid"
              label="Detail"
              class="cursor-pointer"
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

      <!-- 3. Digitasi (Center Action Button) -->
      <button
        v-if="canCreate"
        type="button"
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 transition-colors cursor-pointer group"
        @click="handleStartDrawing"
      >
        <div class="size-7 rounded-full bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/30 group-active:scale-95 transition-transform">
          <UIcon name="i-lucide-pen-tool" class="size-4 stroke-[2.5]" />
        </div>
        <span class="text-[10px] mt-0.5 leading-none font-medium">Gambar</span>
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
            v-if="selectedSegmen"
            class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-500 ring-2 ring-white dark:ring-[#0b0f19]"
          />
        </div>
        <span class="text-[10px] mt-0.5 leading-none">Detail</span>
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

    <!-- ═══ MOBILE BOTTOM SHEET ═══════════════════════════════════════════════ -->
    <MobileBottomSheet
      v-model:open="mobileDrawerOpen"
      :initial-snap="mobileDrawerTab === 'filter' || mobileDrawerTab === 'table' || mobileDrawerTab === 'symbology' ? 'full' : 'peek'"
      :title="
        mobileDrawerTab === 'layer'
          ? 'Daftar Layer & Data'
          : mobileDrawerTab === 'symbology'
            ? 'Pengaturan Simbologi Layer'
            : mobileDrawerTab === 'table'
              ? 'Tabel Atribut Segmen'
              : mobileDrawerTab === 'inspector'
                ? 'Detail Segmen Fisik'
                : 'Filter Data Segmen'
      "
      :icon="
        mobileDrawerTab === 'layer'
          ? 'i-lucide-layers'
          : mobileDrawerTab === 'symbology'
            ? 'i-lucide-palette'
            : mobileDrawerTab === 'table'
              ? 'i-lucide-table'
              : mobileDrawerTab === 'inspector'
                ? 'i-lucide-info'
                : 'i-lucide-filter'
      "
    >
      <!-- Quick Tab Switcher inside sheet (5 Tabs: Layer, Gaya, Filter, Tabel, Detail) -->
      <template #tabs>
        <div class="px-2 pt-1 pb-2 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30">
          <div class="grid grid-cols-5 gap-1 p-1 bg-gray-100 dark:bg-gray-800/70 rounded-xl">
            <!-- Tab 1: Layer -->
            <button
              type="button"
              class="flex flex-col sm:flex-row items-center justify-center gap-1 py-1.5 px-0.5 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'layer'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('layer')"
            >
              <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
              <span class="text-[11px] truncate">Layer</span>
            </button>

            <!-- Tab 2: Gaya (Simbologi) -->
            <button
              type="button"
              class="flex flex-col sm:flex-row items-center justify-center gap-1 py-1.5 px-0.5 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'symbology'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('symbology')"
            >
              <UIcon name="i-lucide-palette" class="size-3.5 shrink-0" />
              <span class="text-[11px] truncate">Gaya</span>
            </button>

            <!-- Tab 3: Filter -->
            <button
              type="button"
              class="flex flex-col sm:flex-row items-center justify-center gap-1 py-1.5 px-0.5 rounded-lg text-xs font-medium transition-all relative cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'filter'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('filter')"
            >
              <UIcon name="i-lucide-filter" class="size-3.5 shrink-0" />
              <span class="text-[11px] truncate">Filter</span>
              <span
                v-if="activeFilterCount > 0"
                class="px-1 py-0.2 rounded-full text-[9px] font-mono leading-tight bg-blue-600 text-white"
              >
                {{ activeFilterCount }}
              </span>
            </button>

            <!-- Tab 4: Tabel -->
            <button
              type="button"
              class="flex flex-col sm:flex-row items-center justify-center gap-1 py-1.5 px-0.5 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'table'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('table')"
            >
              <UIcon name="i-lucide-table" class="size-3.5 shrink-0" />
              <span class="text-[11px] truncate">Tabel</span>
              <span
                v-if="tableTotal > 0"
                class="px-1 py-0.2 rounded-full text-[9px] font-mono leading-tight bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300 hidden sm:inline-block"
              >
                {{ tableTotal > 999 ? (tableTotal / 1000).toFixed(1) + 'k' : tableTotal }}
              </span>
            </button>

            <!-- Tab 5: Detail Properties -->
            <button
              type="button"
              class="flex flex-col sm:flex-row items-center justify-center gap-1 py-1.5 px-0.5 rounded-lg text-xs font-medium transition-all relative cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'inspector'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('inspector')"
            >
              <UIcon name="i-lucide-info" class="size-3.5 shrink-0" />
              <span class="text-[11px] truncate">Detail</span>
              <span
                v-if="selectedSegmen"
                class="size-1.5 rounded-full bg-blue-500 shrink-0"
              />
            </button>
          </div>
        </div>
      </template>

      <!-- Sheet Body -->
      <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
        <!-- Tab 1: Layer Tab -->
        <LeftPanel
          v-if="mobileDrawerTab === 'layer'"
          :is-mobile-drawer="true"
          :layers="gisLayers"
          v-model:active-layer-id="activeLayerId"
          default-tool="none"
          v-model:layer-visible="layerVisible"
          v-model:layer-opacity="layerOpacity"
          v-model:symbology="layerSymbology"
          :tipe-list="tipeList"
          :selected-tipe="filterTipe"
          :selected-kondisi="filterKondisi"
          :selected-status-verifikasi="filterStatusVerifikasi"
          v-model:model-kecamatan="filterKecamatan"
          v-model:model-desa="filterDesa"
          :kecamatan-name="currentKecamatanName"
          :desa-name="currentDesaName"
          :is-kecamatan-locked="isKecamatanRestricted"
          :is-desa-locked="isDesaRestricted"
          :user-role-label="userRoleLabel"
          :kondisi-stats="kondisiStats"
          :total-segmen="totalSegmen"
          :total-panjang-meter="totalPanjangMeter"
          :is-drawing="isDrawingMode || (mobileMapCanvasRef?.isDrawing ?? false)"
          :can-undo="mobileMapCanvasRef?.canUndo ?? false"
          :can-redo="mobileMapCanvasRef?.canRedo ?? false"
          :can-save="mobileMapCanvasRef?.canSave ?? false"
          :is-snapping-enabled="mobileMapCanvasRef?.isSnappingEnabled ?? true"
          :snap-road-count="mobileMapCanvasRef?.snapRoadCount ?? 0"
          @update:layer-visible="handleUpdateLayerVisible"
          @update:layer-opacity="handleUpdateLayerOpacity"
          @open-filter="handleMobileOpenFilter"
          @open-symbology="handleMobileOpenSymbology"
          @start-drawing="() => { mobileDrawerOpen = false; handleStartDrawing(); }"
          @start-draw="() => { mobileDrawerOpen = false; handleStartDrawing(); }"
          @redraw="handleRedraw"
          @undo="handleUndo"
          @redo="handleRedo"
          @toggle-snap="handleToggleSnap"
          @cancel-draw="handleCancelDrawing"
          @reset-filters="resetFilters"
          @apply-filter="() => { mobileDrawerOpen = false; fitAllBounds(); }"
          @zoom-to-layer="(id) => { mobileDrawerOpen = false; handleZoomToLayer(id); }"
        />

        <!-- Tab 2: Gaya (Simbologi Tab) -->
        <div
          v-else-if="mobileDrawerTab === 'symbology'"
          class="flex-1 overflow-y-auto overflow-x-hidden p-3.5 pb-12 min-h-0 overscroll-contain"
          style="-webkit-overflow-scrolling: touch; touch-action: pan-y;"
        >
          <!-- Mobile Layer Switcher for Symbology -->
          <div class="mb-3 p-1 bg-gray-100 dark:bg-gray-800/80 rounded-xl flex items-center gap-1 border border-gray-200/60 dark:border-gray-800">
            <button
              type="button"
              :class="[
                'flex-1 py-1.5 px-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[38px]',
                activeLayerId === 'infrastruktur-segmen'
                  ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                  : 'text-gray-600 dark:text-gray-400'
              ]"
              @click="activeLayerId = 'infrastruktur-segmen'"
            >
              <UIcon name="i-lucide-route" class="size-3.5 shrink-0" />
              <span>Segmen Fisik</span>
            </button>
            <button
              type="button"
              :class="[
                'flex-1 py-1.5 px-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[38px]',
                activeLayerId === 'jalan-poros-desa'
                  ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                  : 'text-gray-600 dark:text-gray-400'
              ]"
              @click="activeLayerId = 'jalan-poros-desa'"
            >
              <UIcon name="i-lucide-milestone" class="size-3.5 shrink-0" />
              <span>Jalan Poros Desa</span>
            </button>
          </div>

          <SimbologyContent
            v-if="activeLayerId === 'infrastruktur-segmen'"
            :is-mobile="true"
            layer-id="infrastruktur-segmen"
            layer-title="Segmen Fisik Infrastruktur"
            v-model:symbology="layerSymbology"
            @reset="layerSymbology = { ...DEFAULT_SYMBOLOGY }"
            @close="mobileDrawerOpen = false"
          />

          <SimbologyContent
            v-else-if="activeLayerId === 'jalan-poros-desa'"
            :is-mobile="true"
            layer-id="jalan-poros-desa"
            layer-title="Jalan Poros Desa"
            v-model:symbology="jalanPorosDesaSymbology"
            @reset="jalanPorosDesaSymbology = { ...DEFAULT_SYMBOLOGY, lineColor: '#2563eb', lineWidth: 2 }"
            @close="mobileDrawerOpen = false"
          />
        </div>

        <!-- Tab 3: Filter Tab -->
        <div
          v-else-if="mobileDrawerTab === 'filter'"
          class="flex-1 overflow-y-auto overflow-x-hidden p-3.5 pb-12 min-h-0 overscroll-contain"
          style="-webkit-overflow-scrolling: touch; touch-action: pan-y;"
        >
          <!-- Mobile Layer Switcher for Filter -->
          <div class="mb-3 p-1 bg-gray-100 dark:bg-gray-800/80 rounded-xl flex items-center gap-1 border border-gray-200/60 dark:border-gray-800">
            <button
              type="button"
              :class="[
                'flex-1 py-1.5 px-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[38px]',
                activeLayerId === 'infrastruktur-segmen'
                  ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                  : 'text-gray-600 dark:text-gray-400'
              ]"
              @click="activeLayerId = 'infrastruktur-segmen'"
            >
              <UIcon name="i-lucide-route" class="size-3.5 shrink-0" />
              <span>Segmen Fisik</span>
              <UBadge
                v-if="activeSegmenFilterCount > 0"
                :label="String(activeSegmenFilterCount)"
                size="xs"
                color="primary"
                variant="subtle"
                class="text-[9px] px-1 py-0 h-4 font-mono ml-0.5"
              />
            </button>
            <button
              type="button"
              :class="[
                'flex-1 py-1.5 px-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[38px]',
                activeLayerId === 'jalan-poros-desa'
                  ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                  : 'text-gray-600 dark:text-gray-400'
              ]"
              @click="activeLayerId = 'jalan-poros-desa'"
            >
              <UIcon name="i-lucide-milestone" class="size-3.5 shrink-0" />
              <span>Jalan Poros Desa</span>
              <UBadge
                v-if="activeJalanFilterCount > 0"
                :label="String(activeJalanFilterCount)"
                size="xs"
                color="info"
                variant="subtle"
                class="text-[9px] px-1 py-0 h-4 font-mono ml-0.5"
              />
            </button>
          </div>

          <!-- Filter Content Segmen Fisik -->
          <FilterContent
            v-if="activeLayerId === 'infrastruktur-segmen'"
            :is-mobile="true"
            layer-id="infrastruktur-segmen"
            layer-title="Segmen Fisik Infrastruktur"
            :tipe-list="tipeList"
            v-model:selected-tipe="filterTipe"
            v-model:selected-kondisi="filterKondisi"
            v-model:selected-status-verifikasi="filterStatusVerifikasi"
            v-model:model-kecamatan="filterKecamatan"
            v-model:model-desa="filterDesa"
            @apply="() => { mobileDrawerOpen = false; handleApplySegmenFilter(); }"
            @reset="resetSegmenFilters"
          />

          <!-- Filter Content Jalan Poros Desa -->
          <FilterContent
            v-else-if="activeLayerId === 'jalan-poros-desa'"
            :is-mobile="true"
            layer-id="jalan-poros-desa"
            layer-title="Jalan Poros Desa"
            v-model:selected-perkerasan="filterJalanPerkerasan"
            v-model:selected-kondisi="filterJalanKondisi"
            v-model:model-kecamatan="filterJalanKecamatan"
            v-model:model-desa="filterJalanDesa"
            @apply="() => { mobileDrawerOpen = false; handleApplyJalanFilter(); }"
            @reset="resetJalanFilters"
          />
        </div>

        <!-- Tab 4: Table Tab -->
        <BottomPanel
          v-else-if="mobileDrawerTab === 'table'"
          :is-mobile-drawer="true"
          v-model:active-tab="bottomActiveTab"
          :table-rows="tableRows"
          :table-total="tableTotal"
          :table-page="tablePage"
          :table-last-page="tableLastPage"
          :table-per-page="tablePerPage"
          v-model:table-search="tableSearch"
          :table-loading="loadingTable"
          :selected-segmen-id="selectedSegmenId"
          :console-logs="consoleLogs"
          :can-edit="canEdit"
          :can-delete="canDelete"
          :can-submit="canSubmit"
          :tipe-list="tipeList"
          :selected-tipe="filterTipe"
          :selected-kondisi="filterKondisi"
          :selected-status-verifikasi="filterStatusVerifikasi"
          :model-kecamatan="filterKecamatan"
          :model-desa="filterDesa"
          :kecamatan-name="currentKecamatanName"
          :desa-name="currentDesaName"
          :can-reset-kecamatan="!isKecamatanRestricted"
          :can-reset-desa="!isDesaRestricted"
          @update:table-page="(p) => tablePage = p"
          @update:table-per-page="(pp) => tablePerPage = pp"
          @update:selected-tipe="(val) => filterTipe = val"
          @update:selected-kondisi="(val) => filterKondisi = val"
          @update:selected-status-verifikasi="(val) => filterStatusVerifikasi = val"
          @update:model-kecamatan="(val) => filterKecamatan = val"
          @update:model-desa="(val) => filterDesa = val"
          @reset-filters="resetFilters"
          @row-click="(row) => { handleZoomToFeature(row); mobileDrawerOpen = false; }"
          @edit-row="(row) => { switchMobileTab('inspector'); handleZoomToFeature(row); }"
          @delete-row="(row) => { confirmDelete(row); mobileDrawerOpen = false; }"
          @submit-row="(row) => handleSubmitVerifikasi(row)"
          @clear-logs="consoleLogs = []"
        />

        <!-- Tab 5: Properties Tab -->
        <RightPanel
          v-else-if="mobileDrawerTab === 'inspector'"
          :is-mobile-drawer="true"
          :selected-segmen="selectedSegmen"
          :clicked-coordinate="clickedCoordinate"
          :loading="isDetailLoading"
          :tipe-list="tipeList"
          :plotting-list="plottingList"
          :can-edit="canEditSegmen(selectedSegmen)"
          :can-delete="canDeleteSegmen(selectedSegmen)"
          :can-submit="canSubmit"
          @zoom-to-feature="(feat) => { handleZoomToFeature(feat); mobileDrawerOpen = false; }"
          @delete-segmen="(feat) => { confirmDelete(feat); mobileDrawerOpen = false; }"
          @submit-verifikasi="handleSubmitVerifikasi"
          @save-segmen="handleSaveSegmen"
          @view-in-table="(seg) => { if (seg) tableSearch = seg.namobj || ''; switchMobileTab('table'); }"
        />
      </div>
    </MobileBottomSheet>

    <!-- ═══ MODAL: Filter Data Layer Terpadu (Desktop Dialog) ══════════ -->
    <DialogFilter
      v-model:open="isFilterDialogOpen"
      v-model:active-layer-id="activeLayerId"
      :tipe-list="tipeList"
      v-model:selected-tipe="filterTipe"
      v-model:selected-kondisi="filterKondisi"
      v-model:selected-status-verifikasi="filterStatusVerifikasi"
      v-model:model-kecamatan="filterKecamatan"
      v-model:model-desa="filterDesa"
      :segmen-filter-count="activeFiltersCount"
      v-model:jalan-perkerasan="filterJalanPerkerasan"
      v-model:jalan-kondisi="filterJalanKondisi"
      v-model:jalan-kecamatan="filterJalanKecamatan"
      v-model:jalan-desa="filterJalanDesa"
      :jalan-filter-count="activeJalanFilterCount"
      @reset-segmen="resetSegmenFilters"
      @reset-jalan="resetJalanFilters"
      @apply-segmen="handleApplySegmenFilter"
      @apply-jalan="handleApplyJalanFilter"
    />

    <!-- ═══ MODAL: Pengaturan Simbologi Terpadu (Desktop Dialog) ════════════════ -->
    <DialogSimbology
      v-model:open="isSymbologyDialogOpen"
      v-model:active-layer-id="activeLayerId"
      v-model:segmen-symbology="layerSymbology"
      v-model:jalan-symbology="jalanPorosDesaSymbology"
      @reset-segmen="layerSymbology = { ...DEFAULT_SYMBOLOGY }"
      @reset-jalan="jalanPorosDesaSymbology = { ...DEFAULT_SYMBOLOGY, lineColor: '#2563eb', lineWidth: 2 }"
    />

    <!-- ═══ MODAL: Simpan Segmen Baru dari Digitasi ═══════════════════════════ -->
    <UModal
      v-model:open="isCreateModalOpen"
      title="Simpan Segmen Fisik Baru"
      :ui="{ content: 'max-w-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800' }"
    >
      <template #body>
        <div class="space-y-3.5 text-xs">
          <!-- Live Digitized Stats -->
          <div class="p-2.5 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 border border-amber-500/30 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-check-circle" class="size-4 text-amber-600 dark:text-amber-400" />
              <span class="font-medium text-gray-800 dark:text-gray-200">Garis LineString Selesai</span>
            </div>
            <UBadge
              :label="`Panjang: ${createForm.panjang.toLocaleString('id-ID')} m`"
              color="warning"
              variant="subtle"
              size="xs"
              class="font-mono"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Nama Objek / Ruas" required size="sm">
              <UInput
                v-model="createForm.namobj"
                placeholder="Contoh: Jl. Poros Dusun Barat"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Tipe Infrastruktur" required size="sm">
              <USelectMenu
                v-model="createForm.tipe_kode"
                :items="tipeList.map(t => ({ label: t.nama, value: t.kode }))"
                value-key="value"
                label-key="label"
                placeholder="Pilih tipe..."
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <!-- Wilayah Selector -->
          <WilayahSelector
            :model-kecamatan="createForm.id_kecamatan"
            :model-desa="createForm.id_desa"
            layout="row"
            :required="true"
            size="sm"
            @update:model-kecamatan="(val) => createForm.id_kecamatan = val"
            @update:model-desa="(val) => createForm.id_desa = val"
          />

          <!-- Kaitan Plotting Anggaran -->
          <UFormField label="Kaitan Plotting Anggaran" size="sm">
            <USelectMenu
              v-model="createForm.plotting_id"
              :items="[
                { label: 'Tanpa Kaitan Plotting (Mandiri)', value: null },
                ...plottingList.map(p => ({
                  label: `${p.nama_kegiatan} (${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(p.target_pagu_anggaran)})`,
                  value: p.id,
                }))
              ]"
              value-key="value"
              label-key="label"
              placeholder="Pilih alokasi plotting..."
              size="sm"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-3 gap-2">
            <UFormField label="Panjang (m)" size="sm">
              <UInputNumber
                v-model="createForm.panjang"
                :step="1"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Lebar (m)" size="sm">
              <UInputNumber
                v-model="createForm.lebar"
                :step="0.1"
                placeholder="Contoh: 3.5"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Kondisi Fisik" size="sm">
              <USelectMenu
                v-model="createForm.kondisi"
                :items="['Baik', 'Sedang', 'Rusak Ringan', 'Rusak Berat']"
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <UFormField label="Sumber Dana" size="sm">
              <UInput
                v-model="createForm.sumber_dana"
                placeholder="Contoh: BKKD / APBD"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Tahun Bangun" size="sm">
              <UInputNumber
                v-model="createForm.tahun_pembangunan"
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <UFormField label="Keterangan" size="sm">
            <UTextarea
              v-model="createForm.keterangan"
              :rows="2"
              placeholder="Catatan teknis lapangan..."
              size="sm"
              class="w-full"
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
            class="cursor-pointer"
            @click="isCreateModalOpen = false"
          />
          <UButton
            label="Simpan Draft Segmen"
            icon="i-lucide-save"
            color="primary"
            variant="solid"
            class="cursor-pointer"
            :loading="isCreating"
            @click="handleConfirmCreate"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: Konfirmasi Hapus Segmen ═════════════════════════════════════ -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Hapus Segmen Fisik"
      :ui="{ content: 'max-w-sm bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800' }"
    >
      <template #body>
        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Apakah Anda yakin ingin menghapus segmen
          <span class="font-semibold text-gray-900 dark:text-white">
            {{ segmenToDelete?.namobj || 'ini' }}
          </span>?
          Data koordinat spasial PostGIS dan atribut terkait akan dihapus.
        </p>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="isDeleteModalOpen = false"
          />
          <UButton
            label="Hapus Permanen"
            color="error"
            variant="solid"
            class="cursor-pointer"
            :loading="isDeleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

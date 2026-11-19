<script setup lang="ts">
import type { SplitterItem } from '@nuxt/ui';
import MapCanvas from '~/components/dataset/infrastruktur-segmen/MapCanvas.vue';
import LeftPanel from '~/components/dataset/infrastruktur-segmen/LeftPanel.vue';
import RightPanel from '~/components/dataset/infrastruktur-segmen/RightPanel.vue';
import DigitasiSetupPanel from '~/components/dataset/infrastruktur-segmen/DigitasiSetupPanel.vue';
import BottomPanel from '~/components/dataset/infrastruktur-segmen/BottomPanel.vue';
import MobileBottomSheet from '~/components/dataset/infrastruktur-segmen/MobileBottomSheet.vue';
import DialogFilter from '~/components/dataset/infrastruktur-segmen/DialogFilter.vue';
import DialogSimbology from '~/components/dataset/infrastruktur-segmen/DialogSimbology.vue';
import DialogSplitSegmen from '~/components/dataset/infrastruktur-segmen/DialogSplitSegmen.vue';
import NumericTextInput from '~/components/dataset/infrastruktur-segmen/NumericTextInput.vue';
import FilterContent from '~/components/dataset/infrastruktur-segmen/FilterContent.vue';
import SimbologyContent from '~/components/dataset/infrastruktur-segmen/SimbologyContent.vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import PlottingFormModal from '~/components/plotting-anggaran/PlottingFormModal.vue';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { usePermission } from '~/composables/usePermission';
import { useAuthStore } from '~/stores/auth';
import { useWilayahStore } from '~/stores/wilayah';
import type {
  ActiveJadwalPeriod,
  GeoJsonFeatureCollection,
  InfrastrukturSegmen,
  InfrastrukturTipe,
  JalanPorosRef,
  PlottingAnggaran,
  RefSumberDana,
} from '~/types/infrastruktur';
import {
  buildSumberDanaSelectItems,
  KONDISI_OPTIONS,
  PERKERASAN_OPTIONS,
  STATUS_ASET_OPTIONS,
  STATUS_JALAN_OPTIONS,
  SUMBER_DANA_OPTIONS,
} from '~/types/infrastruktur';
import type {
  BasemapType,
  ConsoleLog,
  GisLayerItem,
  LayerId,
  LayerSymbology,
} from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY, DEFAULT_JALAN_POROS_SYMBOLOGY } from '~/types/dataset-editor';
import { formatWilayah } from '~/utils/helpers';
import {
  computeCumulativeDistances,
  extractMergedRoadCoordinates,
  projectPointOntoRoad,
  sliceRoadByDistances,
  type LngLat,
  type SlicedRoadResult,
} from '~/utils/road-geometry';

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
const canVerifyKecamatanPerm = computed(() => isAdmin() || can('infrastruktur-segmen-verify-kecamatan'));
const canVerifyBappedaPerm = computed(() => isAdmin() || can('infrastruktur-segmen-verify-bappeda'));

// Territory Context & Role-Permission Scoping
const userKecamatanId = computed(() =>
  auth.user?.id_kecamatan != null ? Number(auth.user.id_kecamatan) : null
);
const userDesaId = computed(() =>
  auth.user?.id_desa != null ? Number(auth.user.id_desa) : null
);
const isBappeda = computed(() =>
  auth.hasRole('verifierBappeda') || auth.hasRole('verifikator-bappeda') || auth.hasRole('bappeda')
);
const isKecamatanRole = computed(() =>
  auth.hasRole('verifierKecamatan') || auth.hasRole('verifikator-kecamatan') || auth.hasRole('kecamatan')
);
const isDesaRole = computed(() =>
  auth.hasRole('operatorDesa') || auth.hasRole('operator-desa') || auth.hasRole('desa')
);
const isKecamatanRestricted = computed(() => !isAdmin() && !isBappeda.value && !!userKecamatanId.value);
const isDesaRestricted = computed(() => !isAdmin() && !isBappeda.value && !!userDesaId.value);

const userRoleLabel = computed(() => {
  if (isAdmin()) return 'Admin';
  if (isBappeda.value) return 'Bappeda';
  if (isKecamatanRole.value) return 'Kecamatan';
  if (isDesaRestricted.value || isDesaRole.value) return 'Desa';
  return 'Pengguna';
});

function handleKecamatanFilterChange(val: number | null) {
  if (isKecamatanRestricted.value) return;
  filterKecamatan.value = val;
  if (!isDesaRestricted.value) {
    filterDesa.value = null;
  }
}

function handleDesaFilterChange(val: number | null) {
  if (isDesaRestricted.value) return;
  filterDesa.value = val;
}

// ─── Workspace Splitters Layout ───────────────────────────────────────────────
const SIDE_PANEL_WIDTH = 300;
const RIGHT_PANEL_WIDTH = 340;
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
    defaultSize: 0,
    minSize: SIDE_PANEL_MIN,
    maxSize: SIDE_PANEL_MAX,
    collapsible: true,
    collapsedSize: 0,
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
const leftPaneContainerRef = ref<HTMLElement | null>(null);
const leftPanelActualWidth = ref<number>(SIDE_PANEL_WIDTH);
const isLeftPanelCollapsed = computed(() => leftPanelActualWidth.value < SIDE_PANEL_MIN - 20);
let leftPaneResizeObserver: ResizeObserver | null = null;

watch(
  leftPaneContainerRef,
  (el) => {
    if (leftPaneResizeObserver) {
      leftPaneResizeObserver.disconnect();
      leftPaneResizeObserver = null;
    }
    if (!el || typeof ResizeObserver === 'undefined') return;
    const measured = Math.round(el.getBoundingClientRect().width);
    if (measured > 0) leftPanelActualWidth.value = measured;
    leftPaneResizeObserver = new ResizeObserver((entries) => {
      const width = Math.round(entries[0]?.contentRect?.width ?? 0);
      if (width > 0) {
        leftPanelActualWidth.value = width;
      }
    });
    leftPaneResizeObserver.observe(el);
  },
  { flush: 'post' },
);

// Drag & right-panel collapse tracking
const isDraggingHorizontal = ref(false);
const isDraggingVertical = ref(false);
const isRightPanelCollapsed = ref(true);

function onHorizontalDragging(_index: number, dragging: boolean) {
  isDraggingHorizontal.value = dragging;
}

function onVerticalDragging(_index: number, dragging: boolean) {
  isDraggingVertical.value = dragging;
}

function onHorizontalCollapse(index: number) {
  if (index === 2) {
    isRightPanelCollapsed.value = true;
  }
}

function onHorizontalExpand(index: number) {
  if (index === 2) {
    isRightPanelCollapsed.value = false;
  }
}

function onHorizontalResize(index: number, size: number) {
  if (index === 2) {
    isRightPanelCollapsed.value = size <= 1;
  }
}

// Right panel auto-expansion synchronization (desktop USplitter only)
function withDesktopRightPanel(action: (panel: any) => void) {
  if (!isDesktop.value) return;
  const splitter = horizontalSplitterRef.value;
  const splitterEl = splitter?.$el as HTMLElement | undefined;
  const panel = splitter?.panelsRef?.[2];
  if (!splitterEl?.isConnected || !panel) return;
  try {
    action(panel);
  } catch {
    // SplitterGroup layout may still be initializing or unmounting across viewport changes
  }
}

function expandRightPanel() {
  if (!isDesktop.value) return;
  withDesktopRightPanel((panel) => {
    if (unref(panel.isCollapsed)) {
      panel.expand?.();
    }
    panel.resize?.(RIGHT_PANEL_WIDTH);
    isRightPanelCollapsed.value = false;
    allPanelsHidden.value = false;
  });
}

function collapseRightPanel() {
  if (!isDesktop.value) return;
  withDesktopRightPanel((panel) => {
    if (!unref(panel.isCollapsed)) {
      panel.collapse?.();
    } else {
      panel.resize?.(0);
    }
    isRightPanelCollapsed.value = true;
  });
}

// Focus / Map-Only Mode
const allPanelsHidden = ref(false);

function toggleAllPanels() {
  if (allPanelsHidden.value) {
    horizontalSplitterRef.value?.panelsRef?.[0]?.resize?.(SIDE_PANEL_WIDTH);
    if (selectedSegmen.value || isDigitasiSetupOpen.value) {
      expandRightPanel();
    } else {
      collapseRightPanel();
    }
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
const filterTahunPembangunan = ref<number | null>(
  route.query.tahun_pembangunan
    ? Number(route.query.tahun_pembangunan)
    : route.query.tahun
      ? Number(route.query.tahun)
      : null
);
const filterPerkerasan = ref<string | null>((route.query.perkerasan as string) || null);
const filterKondisi = ref<string | null>((route.query.kondisi as string) || null);
const filterStatusVerifikasi = ref<string | null>((route.query.status as string) || null);
const filterStatusJalan = ref<string | null>((route.query.status_jalan as string) || null);
const filterStatusKondisi = ref<string | null>((route.query.status_kondisi as string) || null);
const filterStatusAset = ref<string | null>((route.query.status_aset as string) || null);
const filterSumberDana = ref<string | null>((route.query.sumber_dana as string) || null);

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
  if (filterTahunPembangunan.value) count++;
  if (filterTipe.value) count++;
  if (filterPerkerasan.value) count++;
  if (filterKondisi.value) count++;
  if (filterStatusVerifikasi.value) count++;
  if (filterStatusJalan.value) count++;
  if (filterStatusKondisi.value) count++;
  if (filterStatusAset.value) count++;
  if (filterSumberDana.value) count++;
  if (!isKecamatanRestricted.value && filterKecamatan.value) count++;
  if (!isDesaRestricted.value && filterDesa.value) count++;
  return count;
});

function syncQueryToUrl() {
  const query: Record<string, string> = {};
  if (filterTipe.value) query.tipe = filterTipe.value;
  if (filterTahunPembangunan.value) query.tahun_pembangunan = String(filterTahunPembangunan.value);
  if (filterPerkerasan.value) query.perkerasan = filterPerkerasan.value;
  if (filterKondisi.value) query.kondisi = filterKondisi.value;
  if (filterStatusVerifikasi.value) query.status = filterStatusVerifikasi.value;
  if (filterStatusJalan.value) query.status_jalan = filterStatusJalan.value;
  if (filterStatusKondisi.value) query.status_kondisi = filterStatusKondisi.value;
  if (filterStatusAset.value) query.status_aset = filterStatusAset.value;
  if (filterSumberDana.value) query.sumber_dana = filterSumberDana.value;

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
  ...DEFAULT_JALAN_POROS_SYMBOLOGY,
});

const filterJalanKecamatan = ref<number | null>(
  isKecamatanRestricted.value ? userKecamatanId.value : filterKecamatan.value
);
const filterJalanDesa = ref<number | null>(
  isDesaRestricted.value ? userDesaId.value : filterDesa.value
);
const filterJalanKondisi = ref<string | null>(null);
const filterJalanPerkerasan = ref<string | null>(null);

// Unifikasi wilayah kerja antara layer Segmen Fisik dan Jalan Poros Desa
watch([filterKecamatan, filterDesa], ([newKec, newDesa]) => {
  let jalanChanged = false;
  if (filterJalanKecamatan.value !== newKec) {
    filterJalanKecamatan.value = newKec;
    jalanChanged = true;
  }
  if (filterJalanDesa.value !== newDesa) {
    filterJalanDesa.value = newDesa;
    jalanChanged = true;
  }
  if (jalanChanged) {
    mapCanvasRef.value?.refreshJalanPorosVectorTiles();
    mobileMapCanvasRef.value?.refreshJalanPorosVectorTiles();
  }
});

watch([filterJalanKecamatan, filterJalanDesa], ([newKec, newDesa]) => {
  const authorizedKec = getAuthorizedKecamatan(newKec);
  const authorizedDesa = getAuthorizedDesa(newDesa);
  if (filterKecamatan.value !== authorizedKec) {
    filterKecamatan.value = authorizedKec;
  }
  if (filterDesa.value !== authorizedDesa) {
    filterDesa.value = authorizedDesa;
  }
});

const activeSegmenFilterCount = computed(() => {
  let count = 0;
  if (filterTahunPembangunan.value) count++;
  if (filterTipe.value) count++;
  if (filterPerkerasan.value) count++;
  if (filterKondisi.value) count++;
  if (filterStatusVerifikasi.value) count++;
  if (filterStatusJalan.value) count++;
  if (filterStatusKondisi.value) count++;
  if (filterStatusAset.value) count++;
  if (filterSumberDana.value) count++;
  if (!isKecamatanRestricted.value && filterKecamatan.value) count++;
  if (!isDesaRestricted.value && filterDesa.value) count++;
  return count;
});

const activeJalanFilterCount = computed(() => {
  let count = 0;
  if (filterJalanPerkerasan.value) count++;
  if (filterJalanKondisi.value) count++;
  if (!isKecamatanRestricted.value && filterJalanKecamatan.value) count++;
  if (!isDesaRestricted.value && filterJalanDesa.value) count++;
  return count;
});

const activeLayerTitle = computed(() => {
  return activeLayerId.value === 'jalan-poros-desa' ? 'Jalan Poros Desa' : 'Segmen Fisik Infrastruktur';
});

watch(activeLayerId, (newId, oldId) => {
  if (newId !== oldId) {
    mapCanvasRef.value?.closePopup();
    mobileMapCanvasRef.value?.closePopup();
    if (newId === 'jalan-poros-desa' && selectedSegmenId.value) {
      selectedSegmenId.value = null;
      selectedSegmen.value = null;
      collapseRightPanel();
    }
  }
});

function handleApplySegmenFilter() {
  isFilterDialogOpen.value = false;
  mobileDrawerOpen.value = false;
  syncQueryToUrl();
  refreshAll(true);
  fitAllBounds();
}

function handleApplyJalanFilter() {
  isFilterDialogOpen.value = false;
  mobileDrawerOpen.value = false;
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

const totalActiveFilterBadge = computed(() => {
  return activeLayerId.value === 'jalan-poros-desa' ? activeJalanFilterCount.value : activeSegmenFilterCount.value;
});

const hasAnyActiveFilter = computed(() => {
  return activeSegmenFilterCount.value > 0 || activeJalanFilterCount.value > 0;
});

function handleToggleLegendFilter(payload: { layerId: string; field: 'kondisi' | 'perkerasan'; value: string }) {
  if (payload.layerId === 'jalan-poros-desa') {
    if (payload.field === 'kondisi') {
      const same = (filterJalanKondisi.value || '').toLowerCase() === payload.value.toLowerCase();
      filterJalanKondisi.value = same ? null : payload.value;
    } else {
      const same = (filterJalanPerkerasan.value || '').toLowerCase() === payload.value.toLowerCase();
      filterJalanPerkerasan.value = same ? null : payload.value;
    }
    mapCanvasRef.value?.refreshJalanPorosVectorTiles();
    mobileMapCanvasRef.value?.refreshJalanPorosVectorTiles();
    return;
  }

  if (payload.field === 'kondisi') {
    const same = (filterKondisi.value || '').toLowerCase() === payload.value.toLowerCase();
    filterKondisi.value = same ? null : payload.value;
  } else {
    const same = (filterPerkerasan.value || '').toLowerCase() === payload.value.toLowerCase();
    filterPerkerasan.value = same ? null : payload.value;
  }
}

function resetActiveLayerFilters() {
  resetFilters();
  if (filterJalanKondisi.value || filterJalanPerkerasan.value) {
    resetJalanFilters();
  }
}

onMounted(() => {
  try {
    const savedJalan = localStorage.getItem(STORAGE_KEY_SYMBOLOGY_JALAN);
    if (savedJalan) {
      const parsed = JSON.parse(savedJalan);
      jalanPorosDesaSymbology.value = {
        ...DEFAULT_JALAN_POROS_SYMBOLOGY,
        ...parsed,
        colorMode: parsed.colorMode && parsed.colorMode !== 'single' ? parsed.colorMode : 'kondisi',
      };
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
const rightPanelRef = ref<InstanceType<typeof RightPanel> | null>(null);
const mobileRightPanelRef = ref<InstanceType<typeof RightPanel> | null>(null);
const isDesktop = ref(true);

function updateViewportMode() {
  if (typeof window !== 'undefined') {
    isDesktop.value = window.innerWidth >= 1024;
  }
}

watch(isDesktop, (desktop) => {
  if (!desktop) return;
  nextTick(() => {
    if (selectedSegmen.value || isDigitasiSetupOpen.value) {
      expandRightPanel();
    } else {
      collapseRightPanel();
    }
  });
});

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
const sumberDanaList = ref<RefSumberDana[]>([]);
const kecamatanList = computed(() => wilayahStore.kecamatanList);
const desaList = computed(() => (filterKecamatan.value ? wilayahStore.desaByKecamatan[filterKecamatan.value] || [] : []));
const tableRows = ref<InfrastrukturSegmen[]>([]);
const tableTotal = ref(0);
const tableLastPage = ref(1);
const tableError = ref<string | null>(null);
const selectedSegmenId = ref<string | null>(null);
const selectedSegmen = ref<InfrastrukturSegmen | null>(null);

const summaryTotalSegmen = ref<number | null>(null);
const summaryTotalPanjangMeter = ref<number | null>(null);
const kondisiStats = ref<Array<{ kondisi: string; jumlah: number; color?: string; panjang_meter?: number }>>([]);

const totalSegmen = computed(() => summaryTotalSegmen.value ?? tableTotal.value);

const loadingMap = ref(false);
const loadingTable = ref(false);
const isSavingSegmen = ref(false);
const isDrawingMode = ref(false);
const isDigitasiSetupOpen = ref(false);
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

const clickedCoordText = computed(() => {
  if (!clickedCoordinate.value) return '';
  return `${clickedCoordinate.value[1].toFixed(5)}, ${clickedCoordinate.value[0].toFixed(5)}`;
});

// ─── API Loaders ──────────────────────────────────────────────────────────────
async function loadMasterData() {
  try {
    const canFetchSumberDana =
      can('ref-sumber-dana-view') ||
      can('ref-sumber-dana-manage') ||
      can('infrastruktur-segmen-view') ||
      can('infrastruktur-segmen-create') ||
      can('infrastruktur-segmen-update');

    const [tipeRes, plottingRes, sumberDanaRes, jadwalRes] = await Promise.all([
      can('infrastruktur-tipe-view') ||
      can('infrastruktur-tipe-manage') ||
      can('infrastruktur-segmen-view') ||
      can('infrastruktur-segmen-create')
        ? api.fetchTipeList({ active_only: true })
        : Promise.resolve(null),
      can('plotting-anggaran-view') ||
      can('plotting-anggaran-manage') ||
      can('infrastruktur-segmen-view') ||
      can('infrastruktur-segmen-create')
        ? api.fetchPlottingList({ per_page: 500 })
        : Promise.resolve(null),
      canFetchSumberDana
        ? api.fetchSumberDanaList({ active_only: true })
        : Promise.resolve(null),
      api.fetchActiveJadwal({
        id_kecamatan: isKecamatanRestricted.value ? userKecamatanId.value : (filterKecamatan.value ?? null),
        id_desa: isDesaRestricted.value ? userDesaId.value : (filterDesa.value ?? null),
      }).catch(() => null),
      wilayahStore.getKecamatanList(),
      ...(filterKecamatan.value ? [wilayahStore.getDesaList(Number(filterKecamatan.value))] : []),
    ]);
    if (tipeRes?.data) tipeList.value = tipeRes.data;
    if (plottingRes?.data) plottingList.value = plottingRes.data;
    if (jadwalRes) {
      jadwalConfigured.value = Boolean(jadwalRes.configured);
      jadwalBypass.value = Boolean(jadwalRes.is_bypass);
      jadwalPeriods.value = jadwalRes.data || [];
    }
    if (sumberDanaRes?.data) {
      sumberDanaList.value = sumberDanaRes.data;
      if (!isCreateModalOpen.value && (!createForm.sumber_dana || createForm.sumber_dana === 'APBD')) {
        const defaultSd =
          sumberDanaList.value.find((sd) => sd.kode === 'APBD_KAB') ??
          sumberDanaList.value[0];
        if (defaultSd?.kode) {
          createForm.sumber_dana = defaultSd.kode;
        }
      }
    }
  } catch (err: any) {
    addLog('ERROR', `Gagal memuat master data: ${err?.message}`);
  }
}

async function loadSummary(shouldFitBounds = false) {
  try {
    const params: Record<string, any> = {};
    if (filterTipe.value) params.tipe_kode = filterTipe.value;
    if (filterTahunPembangunan.value) params.tahun_pembangunan = filterTahunPembangunan.value;
    if (filterPerkerasan.value) params.jenis_perkerasan = filterPerkerasan.value;
    if (filterKondisi.value) params.kondisi = filterKondisi.value;
    if (filterStatusVerifikasi.value) params.status_verifikasi = filterStatusVerifikasi.value;
    if (filterStatusJalan.value) params.status_jalan = filterStatusJalan.value;
    if (filterStatusKondisi.value) params.status_kondisi = filterStatusKondisi.value;
    if (filterStatusAset.value) params.status_aset = filterStatusAset.value;
    if (filterSumberDana.value) params.sumber_dana = filterSumberDana.value;
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
  tableError.value = null;
  try {
    const params: Record<string, any> = {
      page: tablePage.value,
      per_page: tablePerPage.value,
    };
    if (filterTipe.value) params.tipe_kode = filterTipe.value;
    if (filterTahunPembangunan.value) params.tahun_pembangunan = filterTahunPembangunan.value;
    if (filterPerkerasan.value) params.jenis_perkerasan = filterPerkerasan.value;
    if (filterKondisi.value) params.kondisi = filterKondisi.value;
    if (filterStatusVerifikasi.value) params.status_verifikasi = filterStatusVerifikasi.value;
    if (filterStatusJalan.value) params.status_jalan = filterStatusJalan.value;
    if (filterStatusKondisi.value) params.status_kondisi = filterStatusKondisi.value;
    if (filterStatusAset.value) params.status_aset = filterStatusAset.value;
    if (filterSumberDana.value) params.sumber_dana = filterSumberDana.value;
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
    const msg = err?.data?.message || err?.message || 'Gagal menghubungi server.';
    tableError.value = msg;
    addLog('ERROR', `Gagal memuat tabel segmen: ${msg}`);
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
  const tasks: Promise<any>[] = [loadMasterData(), loadSummary(true), loadTable()];
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
  filterTahunPembangunan.value = null;
  filterPerkerasan.value = null;
  filterKondisi.value = null;
  filterStatusVerifikasi.value = null;
  filterStatusJalan.value = null;
  filterStatusKondisi.value = null;
  filterStatusAset.value = null;
  filterSumberDana.value = null;
  tableSearch.value = '';
  tablePage.value = 1;
  syncQueryToUrl();
  refreshAll(true);
  addLog('INFO', 'Filter data direset.');
}

// Guard: suppress page-watcher's loadTable() when page is reset programmatically
let programmaticPageReset = false;

// Watch filters: reset page, sync URL, refresh once and fitbounds to filtered dataset
watch([
  filterTipe,
  filterTahunPembangunan,
  filterPerkerasan,
  filterKondisi,
  filterStatusVerifikasi,
  filterStatusJalan,
  filterStatusKondisi,
  filterStatusAset,
  filterSumberDana,
  filterKecamatan,
  filterDesa,
], () => {
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

// Handler jika parameter ID pada URL tidak valid, terhapus, atau di luar akses
function handleInvalidSegmenId() {
  selectedSegmenId.value = null;
  selectedSegmen.value = null;
  collapseRightPanel();
  syncQueryToUrl();
  toast.add({
    title: 'Data Segmen Tidak Ditemukan',
    description: 'Segmen yang dituju mungkin telah dihapus atau Anda tidak memiliki akses ke wilayah ini.',
    color: 'warning',
    icon: 'i-lucide-alert-triangle',
  });
}

// Sync from URL back to refs when browser navigation (back/forward or address bar edit) occurs
watch(
  () => route.query,
  (newQ) => {
    filterTipe.value = (newQ.tipe as string) || null;
    filterTahunPembangunan.value = newQ.tahun_pembangunan
      ? Number(newQ.tahun_pembangunan)
      : newQ.tahun
        ? Number(newQ.tahun)
        : null;
    filterPerkerasan.value = (newQ.perkerasan as string) || null;
    filterKondisi.value = (newQ.kondisi as string) || null;
    filterStatusVerifikasi.value = (newQ.status as string) || null;
    filterStatusJalan.value = (newQ.status_jalan as string) || null;
    filterStatusKondisi.value = (newQ.status_kondisi as string) || null;
    filterStatusAset.value = (newQ.status_aset as string) || null;
    filterSumberDana.value = (newQ.sumber_dana as string) || null;

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
        } else {
          handleInvalidSegmenId();
        }
      }).catch(() => {
        handleInvalidSegmenId();
      });
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

// Watch selection & digitasi setup to expand/collapse Right Panel on desktop
watch(
  [isDigitasiSetupOpen, selectedSegmen],
  ([setupOpen, seg], [prevSetupOpen, prevSeg]) => {
    if (!isDesktop.value) return;
    if (setupOpen || seg) {
      const setupOpened = setupOpen && !prevSetupOpen;
      const segChanged = seg && (!prevSeg || String(seg.id) !== String(prevSeg.id));
      if (setupOpened || segChanged || (!prevSetupOpen && !prevSeg)) {
        nextTick(() => expandRightPanel());
      }
    } else {
      nextTick(() => collapseRightPanel());
    }
  },
);

// ─── Selection Logic ──────────────────────────────────────────────────────────
const isDetailLoading = ref(false);

function isSegmenOpenBySchedule(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !jadwalConfigured.value || jadwalBypass.value) return true;
  const linkedPlotYear = seg.plotting_id
    ? plottingList.value.find((p) => p.id === seg.plotting_id)?.tahun_anggaran
    : null;
  const tahun = Number(
    seg.plotting?.tahun_anggaran
    ?? linkedPlotYear
    ?? seg.tahun_pembangunan
    ?? new Date().getFullYear(),
  );
  const period = jadwalPeriods.value.find((j) => Number(j.tahun_anggaran) === tahun);
  if (!period) return false;
  const desaId = Number(seg.id_desa || 0);
  if (desaId > 0 && period.closed_desa_override_ids?.includes(desaId)) {
    return false;
  }
  if (desaId > 0 && period.open_desa_override_ids?.includes(desaId)) {
    return true;
  }
  if (!period.effective_open || !period.allow_digitasi) {
    return false;
  }
  const isNonPaguSeg = Boolean(seg.is_non_pagu || !seg.plotting_id);
  if (isNonPaguSeg && !period.allow_non_pagu) {
    return false;
  }
  return true;
}

function canEditSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canEdit.value) return false;
  if (isAdmin() || isBappeda.value) return true;
  if (!isSegmenOpenBySchedule(seg)) return false;
  const status = String(seg.status_verifikasi || '').toLowerCase();
  if (isDesaRestricted.value) {
    return (
      Number(seg.id_desa) === Number(userDesaId.value) &&
      (status === 'draft' || status === 'rejected_kecamatan')
    );
  }
  if (isKecamatanRestricted.value) {
    return Number(seg.id_kecamatan) === Number(userKecamatanId.value);
  }
  return true;
}

function canDeleteSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canDelete.value) return false;
  if (isAdmin() || isBappeda.value) return true;
  if (!isSegmenOpenBySchedule(seg)) return false;
  if (isDesaRestricted.value) return false;
  if (isKecamatanRestricted.value) {
    const status = String(seg.status_verifikasi || '').toLowerCase();
    return (
      Number(seg.id_kecamatan) === Number(userKecamatanId.value) &&
      (status === 'draft' || status === 'rejected_kecamatan')
    );
  }
  return true;
}

function canSubmitSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canSubmit.value) return false;
  const status = String(seg.status_verifikasi || '').toLowerCase();
  if (status !== 'draft' && status !== 'rejected_kecamatan' && status !== 'rejected_bappeda') return false;
  if (isAdmin()) return true;
  if (isDesaRestricted.value) {
    return Number(seg.id_desa) === Number(userDesaId.value);
  }
  if (isKecamatanRestricted.value) {
    const inKec = Number(seg.id_kecamatan) === Number(userKecamatanId.value);
    if (!inKec) return false;
    const isOwnCreation =
      (auth.user?.uuid && seg.created_by === auth.user.uuid) ||
      !String(seg.created_by_role || '').toLowerCase().includes('desa');
    return isOwnCreation;
  }
  return false;
}

function canVerifyKecamatanSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canVerifyKecamatanPerm.value) return false;
  const status = String(seg.status_verifikasi || '').toLowerCase();
  if (status !== 'submitted_desa' && status !== 'rejected_bappeda') return false;
  if (isAdmin()) return true;
  if (isBappeda.value || isDesaRestricted.value) return false;
  return Number(seg.id_kecamatan) === Number(userKecamatanId.value);
}

function canVerifyBappedaSegmen(seg: InfrastrukturSegmen | null): boolean {
  if (!seg || !canVerifyBappedaPerm.value) return false;
  const status = String(seg.status_verifikasi || '').toLowerCase();
  if (status !== 'verified_kecamatan') return false;
  return isAdmin() || isBappeda.value;
}

function canVerifyAnyRow(row: InfrastrukturSegmen): boolean {
  return canVerifyKecamatanSegmen(row) || canVerifyBappedaSegmen(row);
}

function handleEditRowFromTable(row: InfrastrukturSegmen) {
  handleFeatureSelect(row, true);
  nextTick(() => {
    expandRightPanel();
    rightPanelRef.value?.setActiveTab('edit');
  });
}

function handleMobileEditRowFromTable(row: InfrastrukturSegmen) {
  handleFeatureSelect(row, true);
  switchMobileTab('inspector');
  nextTick(() => {
    mobileRightPanelRef.value?.setActiveTab('edit');
  });
}

async function handleFeatureSelect(feat: any | null, shouldZoom = false) {
  if (!feat) {
    selectedSegmenId.value = null;
    selectedSegmen.value = null;
    if (!isDigitasiSetupOpen.value) {
      collapseRightPanel();
    }
    syncQueryToUrl();
    return;
  }

  if (isDigitasiSetupOpen.value) {
    isDigitasiSetupOpen.value = false;
    if (mobileDrawerTab.value === 'digitasi-setup') {
      mobileDrawerOpen.value = false;
    }
    clearCanvasReferenceHighlight(true);
  }
  isIdentifyMode.value = true;

  const id = String(feat.id || feat.properties?.id);
  const isReselectSameFeature = selectedSegmenId.value === id;
  if (!isReselectSameFeature) {
    segmenPanelFeedback.value = null;
  }
  selectedSegmenId.value = id;
  syncQueryToUrl();

  const p = feat.properties || feat || {};

  // 1. Immediately populate from MVT properties / table row + matching table row
  const rowMatch = tableRows.value.find((r) => String(r.id) === id);
  const matchedTipe = rowMatch?.tipe || feat?.tipe || tipeList.value.find((t) => t.kode === (p.tipe_kode || rowMatch?.tipe_kode));

  let rawGeom = feat.geometry || null;
  if (!rawGeom && (feat.geojson || rowMatch?.geojson)) {
    const rawJson = feat.geojson || rowMatch?.geojson;
    try {
      rawGeom = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;
    } catch {
      // keep null
    }
  }

  selectedSegmen.value = {
    ...(rowMatch || {}),
    ...(feat.properties ? {} : feat),
    id,
    namobj: p.namobj || rowMatch?.namobj || 'Segmen Tanpa Nama',
    tipe_kode: p.tipe_kode || rowMatch?.tipe_kode || '',
    tipe: matchedTipe,
    panjang: p.panjang ?? rowMatch?.panjang ?? null,
    panjang_meter_gis: p.panjang_meter_gis ?? rowMatch?.panjang_meter_gis ?? null,
    lebar: p.lebar ?? rowMatch?.lebar ?? null,
    jenis_perkerasan: p.jenis_perkerasan ?? rowMatch?.jenis_perkerasan ?? null,
    status_jalan: p.status_jalan ?? rowMatch?.status_jalan ?? null,
    kondisi: p.kondisi || rowMatch?.kondisi || 'Baik',
    status_kondisi: p.status_kondisi || rowMatch?.status_kondisi || 'Eksisting',
    tahun_pembangunan: p.tahun_pembangunan ?? rowMatch?.tahun_pembangunan ?? null,
    sumber_dana: p.sumber_dana || rowMatch?.sumber_dana || null,
    status_verifikasi: p.status_verifikasi || rowMatch?.status_verifikasi || 'draft',
    status_aset: p.status_aset || rowMatch?.status_aset || null,
    sumber_data: p.sumber_data ?? rowMatch?.sumber_data ?? null,
    keterangan: p.keterangan ?? rowMatch?.keterangan ?? null,
    desa: p.desa || rowMatch?.desa || null,
    kecamatan: p.kecamatan || rowMatch?.kecamatan || null,
    id_desa: p.id_desa ?? rowMatch?.id_desa ?? null,
    id_kecamatan: p.id_kecamatan ?? rowMatch?.id_kecamatan ?? null,
    plotting_id: p.plotting_id ?? rowMatch?.plotting_id ?? null,
    is_non_pagu: p.is_non_pagu ?? rowMatch?.is_non_pagu ?? false,
    parent_id: p.parent_id ?? rowMatch?.parent_id ?? null,
    parent: feat?.parent ?? rowMatch?.parent ?? null,
    plotting: feat?.plotting ?? rowMatch?.plotting,
    geometry: rawGeom,
    geojson: feat?.geojson ?? rowMatch?.geojson,
    centroid: p.centroid || rowMatch?.centroid || null,
  } as any;

  // 2. Re-expand right panel on desktop when clicking an already-selected feature (new selections handled by watcher)
  if (isDesktop.value && isReselectSameFeature) {
    nextTick(() => expandRightPanel());
  }
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
const createModalFeedback = ref<{ type: 'error'; message: string } | null>(null);
const segmenPanelFeedback = ref<{ type: 'success' | 'error' | 'warning'; message: string } | null>(null);

const maxBuildYear = new Date().getFullYear() + 5;
const createPanjangRef = ref<InstanceType<typeof NumericTextInput> | null>(null);
const createLebarRef = ref<InstanceType<typeof NumericTextInput> | null>(null);
const createTahunRef = ref<InstanceType<typeof NumericTextInput> | null>(null);

const createNumericErrors = reactive({
  panjang: null as string | null,
  lebar: null as string | null,
  tahun_pembangunan: null as string | null,
});

function resetCreateNumericErrors() {
  createNumericErrors.panjang = null;
  createNumericErrors.lebar = null;
  createNumericErrors.tahun_pembangunan = null;
  createPanjangRef.value?.clearValidation();
  createLebarRef.value?.clearValidation();
  createTahunRef.value?.clearValidation();
}

function validateCreateNumericFields(): boolean {
  const v1 = createPanjangRef.value?.validate() ?? true;
  const v2 = createLebarRef.value?.validate() ?? true;
  const v3 = createTahunRef.value?.validate() ?? true;
  return v1 && v2 && v3;
}

const createForm = reactive({
  namobj: '',
  tipe_kode: '',
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  plotting_id: null as string | null,
  is_non_pagu: false,
  parent_id: null as string | null,
  panjang: 0 as number | null,
  lebar: 3.5 as number | null,
  jenis_perkerasan: 'Aspal',
  status_jalan: 'Jalan Desa',
  kondisi: 'Baik' as any,
  status_kondisi: 'Eksisting' as any,
  tahun_pembangunan: new Date().getFullYear() as number | null,
  sumber_dana: 'APBD_KAB',
  status_aset: 'Aset Desa',
  sumber_data: 'Digitasi Canvas GIS',
  keterangan: '',
  geometry: null as any,
});

const createSumberDanaItems = computed(() =>
  buildSumberDanaSelectItems(sumberDanaList.value, createForm.sumber_dana, SUMBER_DANA_OPTIONS),
);

watch(
  () => createForm.plotting_id,
  (plottingId) => {
    if (!plottingId) return;
    const match = plottingList.value.find((p) => p.id === plottingId);
    if (match?.sumber_dana) {
      createForm.sumber_dana = match.sumber_dana;
    }
    if (match?.tahun_anggaran) {
      createForm.tahun_pembangunan = match.tahun_anggaran;
    }
  },
);

// ─── Pre-Digitization Setup & Map Interaction Modes ──────────────────────────
const isIdentifyMode = ref(true);
const identifyModeBeforeDraw = ref(true);
const digitasiBudgetMode = ref<'pagu' | 'non_pagu'>('pagu');
const selectedSetupPlottingId = ref<string | null>(null);
const setupPlottingLoading = ref(false);
const isQuickPlottingModalOpen = ref(false);
const digitasiMethod = ref<'manual' | 'auto'>('manual');
const digitasiRefMode = ref<'with_ref' | 'without_ref'>('with_ref');
const jadwalConfigured = ref(false);
const jadwalBypass = ref(false);
const jadwalPeriods = ref<ActiveJadwalPeriod[]>([]);
const setupJadwalLoading = ref(false);
const showClosedSchedulePlottings = ref(false);

const canCreatePlotting = computed(() =>
  isAdmin()
  || can('plotting-anggaran-manage')
  || can('infrastruktur-segmen-create'),
);

function isPlottingOpenBySchedule(p: PlottingAnggaran): boolean {
  if (!jadwalConfigured.value) return true;
  const period = jadwalPeriods.value.find(
    (j) => Number(j.tahun_anggaran) === Number(p.tahun_anggaran),
  );
  if (!period) return false;
  const desaId = Number(p.id_desa || 0);
  if (desaId > 0 && period.closed_desa_override_ids?.includes(desaId)) {
    return false;
  }
  if (desaId > 0 && period.open_desa_override_ids?.includes(desaId)) {
    return true;
  }
  if (jadwalBypass.value) {
    return Boolean(period.is_open);
  }
  return Boolean(period.effective_open && period.allow_digitasi);
}

const setupPlottingBaseByWilayah = computed(() => {
  const activeKecId = isKecamatanRestricted.value
    ? userKecamatanId.value
    : (filterKecamatan.value ?? null);
  const activeDesaId = isDesaRestricted.value
    ? userDesaId.value
    : (filterDesa.value ?? null);

  return plottingList.value.filter((p) => {
    const status = String(p.status_penetapan || 'ditetapkan').toLowerCase();
    if (status !== 'ditetapkan') return false;
    if (activeKecId && Number(p.id_kecamatan) !== Number(activeKecId)) return false;
    if (activeDesaId && Number(p.id_desa) !== Number(activeDesaId)) return false;
    return true;
  });
});

const hiddenClosedPlottingCount = computed(() => {
  if (!jadwalConfigured.value) return 0;
  return setupPlottingBaseByWilayah.value.filter((p) => !isPlottingOpenBySchedule(p)).length;
});

const setupPlottingOptions = computed(() => {
  if (!jadwalConfigured.value) {
    return setupPlottingBaseByWilayah.value;
  }
  if (jadwalBypass.value && showClosedSchedulePlottings.value) {
    return setupPlottingBaseByWilayah.value;
  }
  return setupPlottingBaseByWilayah.value.filter((p) => isPlottingOpenBySchedule(p));
});

watch(setupPlottingOptions, (opts) => {
  if (selectedSetupPlottingId.value && !opts.some((p) => p.id === selectedSetupPlottingId.value)) {
    selectedSetupPlottingId.value = null;
  }
});

const setupPlottingItems = computed(() =>
  setupPlottingOptions.value.map((p) => {
    const target = Number(p.target_panjang_m || 0);
    const used = Number(p.used_panjang_m || 0);
    const remainText = target > 0
      ? `Sisa ${Math.max(0, Math.round(target - used)).toLocaleString('id-ID')} m`
      : `Pagu ${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(p.target_pagu_anggaran || 0))}`;
    const skPart = p.nomor_sk ? `${p.nomor_sk} · ` : '';
    return {
      label: `[${p.tahun_anggaran}] ${skPart}${p.nama_kegiatan} (${remainText})`,
      value: p.id,
    };
  }),
);

const selectedSetupPlottingDetail = computed<PlottingAnggaran | null>(() => {
  if (digitasiBudgetMode.value !== 'pagu' || !selectedSetupPlottingId.value) return null;
  return setupPlottingOptions.value.find((p) => p.id === selectedSetupPlottingId.value)
    ?? plottingList.value.find((p) => p.id === selectedSetupPlottingId.value)
    ?? null;
});

async function loadActiveJadwalForSetup() {
  setupJadwalLoading.value = true;
  try {
    const plot = selectedSetupPlottingDetail.value;
    const road = selectedSetupRoadDetail.value?.data;
    const kecId = isKecamatanRestricted.value
      ? userKecamatanId.value
      : (plot?.id_kecamatan ?? road?.id_kecamatan ?? filterKecamatan.value ?? null);
    const desaId = isDesaRestricted.value
      ? userDesaId.value
      : (plot?.id_desa ?? road?.id_desa ?? filterDesa.value ?? null);
    const res = await api.fetchActiveJadwal({
      id_kecamatan: kecId,
      id_desa: desaId,
    });
    jadwalConfigured.value = Boolean(res?.configured);
    jadwalBypass.value = Boolean(res?.is_bypass);
    jadwalPeriods.value = res?.data || [];
  } catch {
    // Pertahankan status jadwal terakhir bila request gagal sesaat
  } finally {
    setupJadwalLoading.value = false;
  }
}

async function refreshSetupPlottingOptions() {
  setupPlottingLoading.value = true;
  try {
    const [res] = await Promise.all([
      api.fetchPlottingList({ per_page: 500 }),
      loadActiveJadwalForSetup(),
    ]);
    if (res?.data) {
      plottingList.value = res.data;
    }
  } catch {
    // Keep existing list on transient error
  } finally {
    setupPlottingLoading.value = false;
  }
}

const setupScheduleInfo = computed(() => {
  const openPeriods = jadwalPeriods.value.filter((p) => p.effective_open && p.allow_digitasi);
  const base = {
    configured: jadwalConfigured.value,
    isBypass: jadwalBypass.value,
    loading: setupJadwalLoading.value,
    openPeriods,
  };

  if (!jadwalConfigured.value || jadwalBypass.value) {
    return {
      ...base,
      selectedPeriod: null,
      isBlocked: false,
      blockReason: null,
    };
  }

  if (digitasiBudgetMode.value === 'non_pagu') {
    const nonPaguOpen = openPeriods.filter((p) => p.allow_non_pagu);
    const currentYear = new Date().getFullYear();
    const preferred =
      nonPaguOpen.find((p) => Number(p.tahun_anggaran) === currentYear) ??
      nonPaguOpen[0] ??
      null;
    const isBlocked = nonPaguOpen.length === 0;
    return {
      ...base,
      selectedPeriod: preferred,
      isBlocked,
      blockReason: isBlocked
        ? 'Input segmen Non-Pagu tidak diizinkan atau tidak ada jadwal tahun anggaran yang dibuka untuk wilayah ini.'
        : null,
    };
  }

  const plot = selectedSetupPlottingDetail.value;
  if (plot) {
    const match =
      jadwalPeriods.value.find((p) => Number(p.tahun_anggaran) === Number(plot.tahun_anggaran)) ??
      null;
    const allowed = Boolean(match && match.effective_open && match.allow_digitasi);
    return {
      ...base,
      selectedPeriod: match,
      isBlocked: !allowed,
      blockReason: allowed
        ? null
        : (match?.reason || `Digitasi Tahun Anggaran ${plot.tahun_anggaran} sedang ditutup untuk wilayah ini.`),
    };
  }

  return {
    ...base,
    selectedPeriod: openPeriods[0] ?? null,
    isBlocked: openPeriods.length === 0,
    blockReason:
      openPeriods.length === 0
        ? 'Saat ini tidak ada jadwal tahun anggaran yang dibuka untuk digitasi pada wilayah ini (kecuali desa mendapat dispensasi khusus).'
        : null,
  };
});

function handleQuickPlottingCreated(created: PlottingAnggaran) {
  if (created?.id) {
    if (!plottingList.value.some((p) => p.id === created.id)) {
      plottingList.value = [created, ...plottingList.value];
    }
    if (jadwalConfigured.value && jadwalBypass.value && !isPlottingOpenBySchedule(created)) {
      showClosedSchedulePlottings.value = true;
    }
    digitasiBudgetMode.value = 'pagu';
    selectedSetupPlottingId.value = created.id;
  }
  refreshSetupPlottingOptions();
}

const autoScope = ref<'partial' | 'full'>('partial');
const autoStartMeter = ref(0);
const autoEndMeter = ref(0);
const isPickingAutoPoints = ref(false);
const autoPickStep = ref<'start' | 'end'>('start');

const mapInteractionMode = computed<'pan' | 'identify' | 'pick-reference' | 'pick-auto-points'>(() => {
  if (isDigitasiSetupOpen.value) {
    if (digitasiMethod.value === 'auto' && isPickingAutoPoints.value && selectedSetupRoadDetail.value) {
      return 'pick-auto-points';
    }
    return digitasiRefMode.value === 'with_ref' ? 'pick-reference' : 'pan';
  }
  return isIdentifyMode.value ? 'identify' : 'pan';
});

function toggleIdentifyMode() {
  if (isDrawingMode.value) return;
  if (!isIdentifyMode.value) {
    if (isDigitasiSetupOpen.value) {
      handleCloseDigitasiSetup();
    }
    isIdentifyMode.value = true;
    addLog('INFO', 'Mode Identify Feature diaktifkan. Klik fitur di peta untuk lihat detail.');
  } else {
    isIdentifyMode.value = false;
    mapCanvasRef.value?.closePopup();
    mobileMapCanvasRef.value?.closePopup();
    addLog('INFO', 'Mode Identify Feature dinonaktifkan (mode jelajah peta).');
  }
}
const setupRoadList = ref<JalanPorosRef[]>([]);
const setupRoadLoading = ref(false);
const setupRoadError = ref<string | null>(null);
const selectedSetupRoadId = ref<string | null>(null);
const selectedSetupRoadDetail = ref<{ data: JalanPorosRef; feature: any } | null>(null);
const setupZoomLoading = ref(false);

watch(
  [
    () => selectedSetupPlottingDetail.value?.id,
    () => selectedSetupRoadDetail.value?.data?.id,
    digitasiBudgetMode,
  ],
  () => {
    if (isDigitasiSetupOpen.value) {
      loadActiveJadwalForSetup();
    }
  },
);

const activeDigitasiRef = ref<{ data: JalanPorosRef; feature: any } | null>(null);
const savedJalanLayerVisibleBeforeDraw = ref<boolean | null>(null);
let roadLookupSeq = 0;
let roadFeatureSeq = 0;

function formatRoadOptionLabel(road: JalanPorosRef): string {
  const kode = road.kode_ruas != null && String(road.kode_ruas).trim() !== ''
    ? `[#${road.kode_ruas}] `
    : '';
  const nama = road.nama_ruas || 'Ruas Tanpa Nama';
  const desa = road.desa ? ` · Ds. ${road.desa}` : '';
  return `${kode}${nama}${desa}`;
}

const setupRoadItems = computed(() =>
  setupRoadList.value.map((r) => ({
    label: formatRoadOptionLabel(r),
    value: r.id,
  })),
);

const activeReferenceLabel = computed(() => {
  if (activeDigitasiRef.value) {
    const r = activeDigitasiRef.value.data;
    const kode = r.kode_ruas != null && String(r.kode_ruas).trim() !== '' ? `[#${r.kode_ruas}] ` : '';
    return `Ref: ${kode}${r.nama_ruas || 'Jalan Poros'}`;
  }
  if (isDrawingMode.value) {
    return 'Tanpa Referensi (Mandiri)';
  }
  return null;
});

const autoRoadCoords = computed<LngLat[]>(() =>
  extractMergedRoadCoordinates(selectedSetupRoadDetail.value?.feature),
);
const autoRoadStats = computed(() => computeCumulativeDistances(autoRoadCoords.value));
const autoRoadTotalMeters = computed(
  () => Math.round(autoRoadStats.value.totalMeters * 100) / 100,
);

const autoSlicedResult = computed<SlicedRoadResult | null>(() => {
  if (digitasiMethod.value !== 'auto' || !selectedSetupRoadDetail.value) return null;
  if (autoRoadCoords.value.length < 2) return null;
  if (autoScope.value === 'full') {
    return sliceRoadByDistances(autoRoadCoords.value, 0, autoRoadStats.value.totalMeters);
  }
  return sliceRoadByDistances(autoRoadCoords.value, autoStartMeter.value, autoEndMeter.value);
});

const autoGeometryError = computed<string | null>(() => {
  if (digitasiMethod.value !== 'auto' || !selectedSetupRoadDetail.value) return null;
  if (autoRoadCoords.value.length < 2) {
    return 'Geometri ruas jalan poros tidak valid (kurang dari 2 titik koordinat).';
  }
  if (autoScope.value === 'partial' && !autoSlicedResult.value) {
    return 'Jarak antara Titik Awal (A) dan Titik Akhir (B) minimal 1 meter.';
  }
  return null;
});

const autoPickHint = computed<string | null>(() => {
  if (!isDigitasiSetupOpen.value || digitasiMethod.value !== 'auto' || !isPickingAutoPoints.value) {
    return null;
  }
  return autoPickStep.value === 'start'
    ? 'Klik ruas Jalan Poros untuk menentukan Titik Awal (A)'
    : `Titik A: STA ${autoStartMeter.value.toLocaleString('id-ID')} m · Klik ruas untuk Titik Akhir (B)`;
});

const canConfirmDigitasiSetup = computed(() => {
  if (setupScheduleInfo.value.isBlocked) {
    return false;
  }

  if (digitasiBudgetMode.value === 'pagu') {
    const plot = selectedSetupPlottingDetail.value;
    if (!plot) return false;
    if (digitasiMethod.value === 'auto' && autoSlicedResult.value) {
      const target = Number(plot.target_panjang_m || 0);
      const used = Number(plot.used_panjang_m || 0);
      if (target > 0 && used + autoSlicedResult.value.lengthMeters > target * 1.1 + 0.01) {
        return false;
      }
    }
  }

  if (digitasiMethod.value === 'auto') {
    return Boolean(
      selectedSetupRoadId.value &&
      selectedSetupRoadDetail.value &&
      !setupZoomLoading.value &&
      autoSlicedResult.value &&
      autoSlicedResult.value.coordinates.length >= 2,
    );
  }
  if (digitasiRefMode.value === 'with_ref') {
    return Boolean(selectedSetupRoadId.value && selectedSetupRoadDetail.value && !setupZoomLoading.value);
  }
  return true;
});

const isCreateLockedByPlotting = computed(() => Boolean(createForm.plotting_id && !createForm.is_non_pagu));

const isCreateRegionLockedByParent = computed(() => {
  if (isCreateLockedByPlotting.value) return true;
  if (!createForm.parent_id || !activeDigitasiRef.value) return false;
  const refData = activeDigitasiRef.value.data;
  return Boolean(refData.id_kecamatan && refData.id_desa);
});

async function loadSetupRoadOptions() {
  const seq = ++roadLookupSeq;
  setupRoadLoading.value = true;
  setupRoadError.value = null;
  const activeKecId = isKecamatanRestricted.value
    ? userKecamatanId.value
    : (filterKecamatan.value ?? null);
  const activeDesaId = isDesaRestricted.value
    ? userDesaId.value
    : (filterDesa.value ?? null);
  try {
    const res = await api.fetchJalanPorosLookup({
      id_kecamatan: activeKecId,
      id_desa: activeDesaId,
      limit: 2000,
    });
    if (seq !== roadLookupSeq) return;
    if (res?.ok && Array.isArray(res.data)) {
      setupRoadList.value = res.data;
    } else {
      setupRoadList.value = [];
    }
  } catch (err: any) {
    if (seq !== roadLookupSeq) return;
    setupRoadList.value = [];
    setupRoadError.value = err?.data?.message || err?.message || 'Gagal memuat daftar jalan poros desa.';
  } finally {
    if (seq === roadLookupSeq) {
      setupRoadLoading.value = false;
    }
  }
}

watch([filterKecamatan, filterDesa], () => {
  if (!isDigitasiSetupOpen.value) return;
  selectedSetupRoadId.value = null;
  selectedSetupRoadDetail.value = null;
  clearCanvasReferenceHighlight(true);
  loadSetupRoadOptions();
});

function clearAutoDigitasiPreview() {
  mapCanvasRef.value?.setAutoDigitasiPreview(null);
  mobileMapCanvasRef.value?.setAutoDigitasiPreview(null);
}

function clearCanvasReferenceHighlight(restoreLayerVisibility = true) {
  mapCanvasRef.value?.setReferenceJalanPoros(null, false);
  mobileMapCanvasRef.value?.setReferenceJalanPoros(null, false);
  clearAutoDigitasiPreview();
  if (restoreLayerVisibility && savedJalanLayerVisibleBeforeDraw.value !== null) {
    jalanPorosDesaVisible.value = savedJalanLayerVisibleBeforeDraw.value;
    savedJalanLayerVisibleBeforeDraw.value = null;
  }
}

function cleanupDigitasiReferenceState() {
  activeDigitasiRef.value = null;
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
  clearCanvasReferenceHighlight(true);
  isIdentifyMode.value = identifyModeBeforeDraw.value;
}

function ensureJalanPorosVisibleForSetup() {
  if (savedJalanLayerVisibleBeforeDraw.value === null) {
    savedJalanLayerVisibleBeforeDraw.value = jalanPorosDesaVisible.value;
  }
  jalanPorosDesaVisible.value = true;
}

async function focusMapToReferenceRoad(feature: any, shouldZoom = true) {
  if (!feature) return;
  ensureJalanPorosVisibleForSetup();
  await nextTick();
  mapCanvasRef.value?.setReferenceJalanPoros(feature, shouldZoom);
  mobileMapCanvasRef.value?.setReferenceJalanPoros(feature, shouldZoom);
}

async function handleSetupRoadChange(roadId: string | null, shouldZoom = true) {
  selectedSetupRoadId.value = roadId;
  selectedSetupRoadDetail.value = null;
  const seq = ++roadFeatureSeq;

  if (!roadId) {
    clearCanvasReferenceHighlight(false);
    return;
  }

  clearCanvasReferenceHighlight(false);
  setupZoomLoading.value = true;
  try {
    const res = await api.getJalanPoros(roadId);
    if (seq !== roadFeatureSeq) return;
    if (res?.ok && res.data && res.feature) {
      if (isDesaRestricted.value && res.data.id_desa && Number(res.data.id_desa) !== Number(userDesaId.value)) {
        selectedSetupRoadId.value = null;
        selectedSetupRoadDetail.value = null;
        toast.add({
          title: 'Ruas di Luar Wilayah Desa',
          description: 'Ruas jalan poros yang dipilih berada di luar wilayah desa Anda.',
          color: 'warning',
        });
        return;
      }
      if (
        isKecamatanRestricted.value &&
        !isDesaRestricted.value &&
        res.data.id_kecamatan &&
        Number(res.data.id_kecamatan) !== Number(userKecamatanId.value)
      ) {
        selectedSetupRoadId.value = null;
        selectedSetupRoadDetail.value = null;
        toast.add({
          title: 'Ruas di Luar Wilayah Kecamatan',
          description: 'Ruas jalan poros yang dipilih berada di luar wilayah kecamatan Anda.',
          color: 'warning',
        });
        return;
      }

      if (!setupRoadList.value.some((r) => String(r.id) === String(res.data.id))) {
        setupRoadList.value = [res.data, ...setupRoadList.value];
      }
      selectedSetupRoadDetail.value = { data: res.data, feature: res.feature };
      const roadCoords = extractMergedRoadCoordinates(res.feature);
      const roadTotal = Math.round(computeCumulativeDistances(roadCoords).totalMeters * 100) / 100;
      autoStartMeter.value = 0;
      autoEndMeter.value = roadTotal;
      isPickingAutoPoints.value = false;
      autoPickStep.value = 'start';
      await focusMapToReferenceRoad(res.feature, shouldZoom);
      addLog(
        'INFO',
        shouldZoom
          ? `Peta difokuskan ke ruas jalan poros: ${res.data.nama_ruas || res.data.id}.`
          : `Ruas referensi dipilih dari peta: ${res.data.nama_ruas || res.data.id}.`,
      );
    } else {
      toast.add({
        title: 'Geometri Ruas Tidak Tersedia',
        description: 'Data geometri jalan poros desa tidak ditemukan.',
        color: 'warning',
      });
    }
  } catch (err: any) {
    if (seq !== roadFeatureSeq) return;
    const msg = err?.data?.message || err?.message || 'Gagal mengambil geometri jalan poros.';
    toast.add({ title: 'Gagal Fokus ke Ruas', description: msg, color: 'error' });
  } finally {
    if (seq === roadFeatureSeq) {
      setupZoomLoading.value = false;
    }
  }
}

function handleMapPickReferenceRoad(payload: { id: string; namobj?: string; kode_ruas?: string | null }) {
  if (!isDigitasiSetupOpen.value || digitasiRefMode.value !== 'with_ref') return;
  handleSetupRoadChange(payload.id, false);
}

function handleSwitchDigitasiMethod(method: 'manual' | 'auto') {
  digitasiMethod.value = method;
  if (method === 'auto') {
    if (digitasiRefMode.value !== 'with_ref') {
      digitasiRefMode.value = 'with_ref';
    }
    ensureJalanPorosVisibleForSetup();
    if (selectedSetupRoadDetail.value?.feature) {
      focusMapToReferenceRoad(selectedSetupRoadDetail.value.feature, false);
    }
  } else {
    isPickingAutoPoints.value = false;
    autoPickStep.value = 'start';
    clearAutoDigitasiPreview();
  }
}

function handleSwitchSetupRefMode(mode: 'with_ref' | 'without_ref') {
  if (digitasiMethod.value === 'auto' && mode === 'without_ref') return;
  digitasiRefMode.value = mode;
  if (mode === 'without_ref') {
    isPickingAutoPoints.value = false;
    clearCanvasReferenceHighlight(true);
  } else {
    ensureJalanPorosVisibleForSetup();
    if (selectedSetupRoadDetail.value?.feature) {
      focusMapToReferenceRoad(selectedSetupRoadDetail.value.feature, false);
    }
  }
}

function handleUpdateAutoScope(scope: 'partial' | 'full') {
  autoScope.value = scope;
  if (scope === 'full') {
    isPickingAutoPoints.value = false;
    autoPickStep.value = 'start';
  }
}

function handleUpdateAutoStartMeter(val: number) {
  const clamped = Math.max(0, Math.min(autoRoadTotalMeters.value, val));
  autoStartMeter.value = Math.round(clamped * 100) / 100;
}

function handleUpdateAutoEndMeter(val: number) {
  const clamped = Math.max(0, Math.min(autoRoadTotalMeters.value, val));
  autoEndMeter.value = Math.round(clamped * 100) / 100;
}

function handleSwapAutoDirection() {
  const tmp = autoStartMeter.value;
  autoStartMeter.value = autoEndMeter.value;
  autoEndMeter.value = tmp;
}

function handleResetAutoRange() {
  autoStartMeter.value = 0;
  autoEndMeter.value = autoRoadTotalMeters.value;
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
}

function handleTogglePickAutoPoints() {
  if (!selectedSetupRoadDetail.value) return;
  if (isPickingAutoPoints.value) {
    isPickingAutoPoints.value = false;
    autoPickStep.value = 'start';
    if (!isDesktop.value && isDigitasiSetupOpen.value) {
      mobileDrawerTab.value = 'digitasi-setup';
      mobileDrawerSnap.value = 'peek';
      mobileDrawerOpen.value = true;
    }
    return;
  }
  autoScope.value = 'partial';
  isPickingAutoPoints.value = true;
  autoPickStep.value = 'start';
  if (!isDesktop.value) {
    mobileDrawerOpen.value = false;
  }
  addLog('INFO', 'Klik ruas Jalan Poros Desa pada peta untuk menentukan Titik Awal (A) dan Titik Akhir (B).');
}

function handleCancelAutoPick() {
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
  if (!isDesktop.value && isDigitasiSetupOpen.value) {
    mobileDrawerTab.value = 'digitasi-setup';
    mobileDrawerSnap.value = 'peek';
    mobileDrawerOpen.value = true;
  }
}

function handlePickAutoPoint(coords: [number, number]) {
  if (!isDigitasiSetupOpen.value || digitasiMethod.value !== 'auto' || !isPickingAutoPoints.value) {
    return;
  }
  const roadCoords = autoRoadCoords.value;
  if (roadCoords.length < 2) return;

  const proj = projectPointOntoRoad(coords, roadCoords, autoRoadStats.value);
  if (!proj) return;

  if (proj.distanceFromPointMeters > 300) {
    toast.add({
      title: 'Klik Lebih Dekat ke Ruas',
      description: 'Titik yang diklik terlalu jauh dari ruas Jalan Poros Desa referensi.',
      color: 'warning',
    });
    return;
  }

  const along = Math.round(proj.alongMeters * 100) / 100;
  if (autoPickStep.value === 'start') {
    autoStartMeter.value = along;
    autoPickStep.value = 'end';
    addLog('INFO', `Titik Awal (A) dipilih pada jarak ${along.toLocaleString('id-ID')} m.`);
    return;
  }

  if (Math.abs(along - autoStartMeter.value) < 1) {
    toast.add({
      title: 'Titik Terlalu Dekat',
      description: 'Titik Akhir (B) harus berjarak minimal 1 meter dari Titik Awal (A).',
      color: 'warning',
    });
    return;
  }

  autoEndMeter.value = along;
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
  addLog('INFO', `Titik Akhir (B) dipilih pada jarak ${along.toLocaleString('id-ID')} m.`);

  if (!isDesktop.value && isDigitasiSetupOpen.value) {
    mobileDrawerTab.value = 'digitasi-setup';
    mobileDrawerSnap.value = 'peek';
    mobileDrawerOpen.value = true;
  }
}

watch(
  [isDigitasiSetupOpen, digitasiMethod, autoSlicedResult, isPickingAutoPoints, autoPickStep, autoStartMeter],
  () => {
    if (!isDigitasiSetupOpen.value || digitasiMethod.value !== 'auto' || !selectedSetupRoadDetail.value) {
      if (!isCreateModalOpen.value) {
        clearAutoDigitasiPreview();
      }
      return;
    }

    if (isPickingAutoPoints.value && autoPickStep.value === 'end') {
      const startCoords = autoRoadCoords.value;
      const projPt = startCoords.length >= 2
        ? sliceRoadByDistances(startCoords, autoStartMeter.value, Math.min(autoRoadTotalMeters.value, autoStartMeter.value + 2))
        : null;
      const pinA = projPt?.startPoint ?? null;
      const preview = pinA
        ? { coordinates: [], startPoint: pinA, endPoint: null }
        : null;
      mapCanvasRef.value?.setAutoDigitasiPreview(preview);
      mobileMapCanvasRef.value?.setAutoDigitasiPreview(preview);
      return;
    }

    if (autoSlicedResult.value) {
      const preview = {
        coordinates: autoSlicedResult.value.coordinates,
        startPoint: autoSlicedResult.value.startPoint,
        endPoint: autoSlicedResult.value.endPoint,
      };
      mapCanvasRef.value?.setAutoDigitasiPreview(preview);
      mobileMapCanvasRef.value?.setAutoDigitasiPreview(preview);
    } else {
      clearAutoDigitasiPreview();
    }
  },
  { flush: 'post' },
);

function handleStartDrawing() {
  if (isDrawingMode.value) return;
  if (isDigitasiSetupOpen.value) {
    if (!isDesktop.value) {
      openMobileTab('digitasi-setup');
    } else {
      nextTick(() => expandRightPanel());
    }
    return;
  }
  identifyModeBeforeDraw.value = isIdentifyMode.value;
  isIdentifyMode.value = false;
  mapCanvasRef.value?.closePopup();
  mobileMapCanvasRef.value?.closePopup();
  digitasiBudgetMode.value = 'pagu';
  if (selectedSetupPlottingId.value && !setupPlottingOptions.value.some((p) => p.id === selectedSetupPlottingId.value)) {
    selectedSetupPlottingId.value = null;
  }
  digitasiMethod.value = 'manual';
  autoScope.value = 'partial';
  autoStartMeter.value = 0;
  autoEndMeter.value = 0;
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
  selectedSetupRoadId.value = null;
  selectedSetupRoadDetail.value = null;
  setupRoadError.value = null;
  if (digitasiRefMode.value === 'with_ref') {
    ensureJalanPorosVisibleForSetup();
  }
  isDigitasiSetupOpen.value = true;
  if (!isDesktop.value) {
    openMobileTab('digitasi-setup');
  } else {
    nextTick(() => expandRightPanel());
  }
  refreshSetupPlottingOptions();
  loadSetupRoadOptions();
}

function handleCloseDigitasiSetup(closeMobileDrawer = true) {
  isDigitasiSetupOpen.value = false;
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
  if (closeMobileDrawer && mobileDrawerTab.value === 'digitasi-setup') {
    mobileDrawerOpen.value = false;
  }
  if (!isDrawingMode.value && !isCreateModalOpen.value) {
    clearCanvasReferenceHighlight(true);
    isIdentifyMode.value = identifyModeBeforeDraw.value;
  }
  if (isDesktop.value && !selectedSegmen.value) {
    nextTick(() => collapseRightPanel());
  }
}

function openCreateModalFromGeometry(
  geojson: { type: 'LineString'; coordinates: LngLat[] },
  lengthMeters: number,
  sourceLabel: string,
) {
  createForm.geometry = geojson;
  createForm.panjang = Number.isFinite(lengthMeters)
    ? Math.round(lengthMeters * 100) / 100
    : 0;
  createForm.is_non_pagu = digitasiBudgetMode.value === 'non_pagu';
  createForm.plotting_id = digitasiBudgetMode.value === 'pagu' ? (selectedSetupPlottingDetail.value?.id ?? null) : null;
  createForm.keterangan = '';
  createForm.sumber_data = sourceLabel;

  const refRoad = activeDigitasiRef.value?.data;
  if (refRoad) {
    createForm.parent_id = refRoad.id;
    createForm.namobj = refRoad.nama_ruas || '';
    createForm.status_jalan = 'Jalan Desa';
    if (refRoad.lebar != null && Number(refRoad.lebar) > 0) {
      createForm.lebar = Number(refRoad.lebar);
    }
    createForm.id_kecamatan = isKecamatanRestricted.value
      ? userKecamatanId.value
      : (refRoad.id_kecamatan ?? filterKecamatan.value ?? null);
    createForm.id_desa = isDesaRestricted.value
      ? userDesaId.value
      : (refRoad.id_desa ?? filterDesa.value ?? null);
  } else {
    createForm.parent_id = null;
    createForm.namobj = '';
    createForm.id_kecamatan = isKecamatanRestricted.value
      ? userKecamatanId.value
      : (filterKecamatan.value ?? null);
    createForm.id_desa = isDesaRestricted.value
      ? userDesaId.value
      : (filterDesa.value ?? null);
  }

  const activePlot = selectedSetupPlottingDetail.value;
  if (!createForm.is_non_pagu && activePlot) {
    createForm.plotting_id = activePlot.id;
    createForm.sumber_dana = activePlot.sumber_dana;
    createForm.tahun_pembangunan = activePlot.tahun_anggaran;
    createForm.id_kecamatan = activePlot.id_kecamatan;
    createForm.id_desa = activePlot.id_desa;
    if (!createForm.namobj) {
      createForm.namobj = activePlot.nama_kegiatan || '';
    }
  } else if (sumberDanaList.value.length > 0) {
    const defaultSd =
      sumberDanaList.value.find((sd) => sd.kode === createForm.sumber_dana) ??
      sumberDanaList.value.find((sd) => sd.kode === 'APBD_KAB') ??
      sumberDanaList.value[0];
    if (defaultSd?.kode) {
      createForm.sumber_dana = defaultSd.kode;
    }
  }

  if (filterTipe.value) createForm.tipe_kode = filterTipe.value;
  else {
    const porosTipe = tipeList.value.find((t) => t.kode === 'jalan_poros');
    if (porosTipe) createForm.tipe_kode = porosTipe.kode;
    else if (tipeList.value.length > 0) createForm.tipe_kode = tipeList.value[0]!.kode;
  }

  resetCreateNumericErrors();
  isCreateModalOpen.value = true;
}

function handleConfirmDigitasiSetup() {
  if (!canConfirmDigitasiSetup.value) {
    if (digitasiRefMode.value === 'with_ref' && !selectedSetupRoadId.value) {
      toast.add({
        title: 'Pilih Jalan Poros',
        description: 'Pilih ruas jalan poros desa terlebih dahulu sebelum mulai digitasi.',
        color: 'warning',
      });
    }
    return;
  }

  if (digitasiMethod.value === 'auto') {
    if (!selectedSetupRoadDetail.value || !autoSlicedResult.value) return;
    activeDigitasiRef.value = selectedSetupRoadDetail.value;
    if (savedJalanLayerVisibleBeforeDraw.value === null) {
      savedJalanLayerVisibleBeforeDraw.value = jalanPorosDesaVisible.value;
    }
    jalanPorosDesaVisible.value = true;
    isPickingAutoPoints.value = false;
    isDigitasiSetupOpen.value = false;
    if (mobileDrawerTab.value === 'digitasi-setup') {
      mobileDrawerOpen.value = false;
    }
    if (isDesktop.value) {
      nextTick(() => collapseRightPanel());
    }

    const sliced = autoSlicedResult.value;
    addLog(
      'INFO',
      `Geometri otomatis terbentuk mengikuti "${formatRoadOptionLabel(selectedSetupRoadDetail.value.data)}" (${sliced.lengthMeters} m, ${sliced.coordinates.length} titik).`,
    );
    openCreateModalFromGeometry(
      { type: 'LineString', coordinates: sliced.coordinates },
      sliced.lengthMeters,
      'Digitasi Otomatis (Ref. Jalan Poros Desa)',
    );
    return;
  }

  if (digitasiRefMode.value === 'with_ref' && selectedSetupRoadDetail.value) {
    activeDigitasiRef.value = selectedSetupRoadDetail.value;
    if (savedJalanLayerVisibleBeforeDraw.value === null) {
      savedJalanLayerVisibleBeforeDraw.value = jalanPorosDesaVisible.value;
    }
    jalanPorosDesaVisible.value = true;
    const feat = selectedSetupRoadDetail.value.feature;
    nextTick(() => {
      mapCanvasRef.value?.setReferenceJalanPoros(feat, false);
      mobileMapCanvasRef.value?.setReferenceJalanPoros(feat, false);
    });
  } else {
    activeDigitasiRef.value = null;
    clearCanvasReferenceHighlight(true);
  }

  isDigitasiSetupOpen.value = false;
  if (mobileDrawerTab.value === 'digitasi-setup') {
    mobileDrawerOpen.value = false;
  }
  if (isDesktop.value) {
    nextTick(() => collapseRightPanel());
  }
  isDrawingMode.value = true;
  nextTick(() => {
    mapCanvasRef.value?.startDrawing();
    mobileMapCanvasRef.value?.startDrawing();
  });

  if (activeDigitasiRef.value) {
    addLog('INFO', `Memulai digitasi manual mengacu ruas: ${formatRoadOptionLabel(activeDigitasiRef.value.data)}.`);
  } else {
    addLog('INFO', 'Memulai digitasi manual tanpa referensi jalan poros (segmen mandiri).');
  }
}

function handleEditAutoGeometryOnMap() {
  if (!selectedSetupRoadDetail.value || !autoSlicedResult.value) return;
  activeDigitasiRef.value = selectedSetupRoadDetail.value;
  if (savedJalanLayerVisibleBeforeDraw.value === null) {
    savedJalanLayerVisibleBeforeDraw.value = jalanPorosDesaVisible.value;
  }
  jalanPorosDesaVisible.value = true;
  isPickingAutoPoints.value = false;
  isDigitasiSetupOpen.value = false;
  if (mobileDrawerTab.value === 'digitasi-setup') {
    mobileDrawerOpen.value = false;
  }
  if (isDesktop.value) {
    nextTick(() => collapseRightPanel());
  }

  const coords = autoSlicedResult.value.coordinates;
  const feat = selectedSetupRoadDetail.value.feature;
  clearAutoDigitasiPreview();
  isDrawingMode.value = true;
  nextTick(() => {
    mapCanvasRef.value?.setReferenceJalanPoros(feat, false);
    mobileMapCanvasRef.value?.setReferenceJalanPoros(feat, false);
    mapCanvasRef.value?.startDrawing(coords);
    mobileMapCanvasRef.value?.startDrawing(coords);
  });
}

function handleReopenDigitasiSetup() {
  mapCanvasRef.value?.cancelDrawing(false);
  mobileMapCanvasRef.value?.cancelDrawing(false);
  isDrawingMode.value = false;
  isIdentifyMode.value = false;
  isPickingAutoPoints.value = false;
  autoPickStep.value = 'start';
  if (activeDigitasiRef.value) {
    digitasiRefMode.value = 'with_ref';
    selectedSetupRoadId.value = activeDigitasiRef.value.data.id;
    selectedSetupRoadDetail.value = activeDigitasiRef.value;
    ensureJalanPorosVisibleForSetup();
  } else {
    digitasiMethod.value = 'manual';
    digitasiRefMode.value = 'without_ref';
  }
  isDigitasiSetupOpen.value = true;
  if (!isDesktop.value) {
    openMobileTab('digitasi-setup');
  } else {
    nextTick(() => expandRightPanel());
  }
  loadSetupRoadOptions();
}

function onDrawCanceled() {
  isDrawingMode.value = false;
  cleanupDigitasiReferenceState();
  addLog('INFO', 'Mode gambar dibatalkan.');
}

function handleCancelDrawing() {
  isDrawingMode.value = false;
  mapCanvasRef.value?.cancelDrawing();
  mobileMapCanvasRef.value?.cancelDrawing();
  cleanupDigitasiReferenceState();
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

function handleDrawClick() {
  mapCanvasRef.value?.handleDrawClick();
  mobileMapCanvasRef.value?.handleDrawClick();
}

function handleConfirmDraw() {
  mapCanvasRef.value?.confirmDraw();
  mobileMapCanvasRef.value?.confirmDraw();
}

function handleSaveEditedGeometry() {
  mapCanvasRef.value?.saveEditedGeometry();
  mobileMapCanvasRef.value?.saveEditedGeometry();
}

function handleCancelEditGeometry() {
  mapCanvasRef.value?.cancelEditGeometry();
  mobileMapCanvasRef.value?.cancelEditGeometry();
}

function handleCancelSplitMode() {
  isSplitDialogOpen.value = false;
  splitTargetSegmen.value = null;
  splitPointCoordinate.value = null;
  mapCanvasRef.value?.cancelSplitMode();
  mobileMapCanvasRef.value?.cancelSplitMode();
  addLog('INFO', 'Mode potong segmen dibatalkan.');
}

const desktopSpatialMode = computed<'edit-geometry' | 'split' | 'draw' | null>(() => {
  if (!isDesktop.value) return null;
  const canvas = mapCanvasRef.value;
  if (canvas?.isEditGeometryMode) return 'edit-geometry';
  if (canvas?.isSplitMode) return 'split';
  if (canvas?.isDrawing || isDrawingMode.value) return 'draw';
  return null;
});

const desktopEditSegmenName = computed(() => mapCanvasRef.value?.editingSegmenData?.namobj || 'Segmen');
const desktopEditedLengthMeters = computed(() => Number(mapCanvasRef.value?.editedLengthMeters ?? 0));
const desktopSplitSegmenName = computed(() => mapCanvasRef.value?.splitSegmenData?.namobj || 'Segmen');

const desktopIsDrawInteracting = computed(() => Boolean(mapCanvasRef.value?.isDrawInteracting));
const desktopCanUndo = computed(() => Boolean(mapCanvasRef.value?.canUndo));
const desktopCanRedo = computed(() => Boolean(mapCanvasRef.value?.canRedo));
const desktopCanSave = computed(() => Boolean(mapCanvasRef.value?.canSave));
const desktopHasCompletedLine = computed(() => Boolean(mapCanvasRef.value?.hasCompletedLine));
const desktopDrawnPointCount = computed(() => Number(mapCanvasRef.value?.drawnPointCount ?? 0));
const desktopDrawnLengthMeters = computed(() => Number(mapCanvasRef.value?.drawnLengthMeters ?? 0));
const desktopIsSnappingEnabled = computed(() => Boolean(mapCanvasRef.value?.isSnappingEnabled ?? true));
const desktopIsSnapLoading = computed(() => Boolean(mapCanvasRef.value?.isSnapLoading));
const desktopSnapRoadCount = computed(() => Number(mapCanvasRef.value?.snapRoadCount ?? 0));

function handleDrawSaved(payload: { geojson: any; lengthMeters: number }) {
  isDrawingMode.value = false;
  addLog('INFO', `Digitasi garis selesai. Panjang: ${Math.round(payload.lengthMeters * 100) / 100} m.`);
  openCreateModalFromGeometry(
    payload.geojson,
    payload.lengthMeters,
    digitasiMethod.value === 'auto' ? 'Digitasi Otomatis (Ref. Jalan Poros Desa)' : 'Digitasi Canvas GIS',
  );
}

watch(isCreateModalOpen, (open, prevOpen) => {
  if (open) {
    createModalFeedback.value = null;
  }
  if (!open && prevOpen) {
    cleanupDigitasiReferenceState();
  }
});

watch(isDigitasiSetupOpen, (open, prevOpen) => {
  if (!open && prevOpen && !isDrawingMode.value && !isCreateModalOpen.value) {
    clearCanvasReferenceHighlight(true);
  }
});

async function handleConfirmCreate() {
  createModalFeedback.value = null;
  if (!validateCreateNumericFields()) {
    createModalFeedback.value = { type: 'error', message: 'Periksa kembali isian angka yang belum valid.' };
    return;
  }
  if (!createForm.tipe_kode) {
    createModalFeedback.value = { type: 'error', message: 'Tipe infrastruktur wajib dipilih.' };
    return;
  }
  if (!createForm.id_kecamatan || !createForm.id_desa) {
    createModalFeedback.value = { type: 'error', message: 'Kecamatan dan Desa wajib dipilih.' };
    return;
  }
  if (!createForm.geometry) {
    createModalFeedback.value = { type: 'error', message: 'Geometri segmen tidak valid.' };
    return;
  }

  isCreating.value = true;
  try {
    const res = await api.createSegmen({
      tipe_kode: createForm.tipe_kode,
      namobj: createForm.namobj || 'Segmen Fisik Baru',
      id_kecamatan: createForm.id_kecamatan,
      id_desa: createForm.id_desa,
      plotting_id: createForm.is_non_pagu ? null : createForm.plotting_id,
      is_non_pagu: createForm.is_non_pagu,
      parent_id: createForm.parent_id,
      panjang: createForm.panjang ?? 0,
      lebar: createForm.lebar,
      jenis_perkerasan: createForm.jenis_perkerasan,
      status_jalan: createForm.status_jalan,
      kondisi: createForm.kondisi,
      status_kondisi: createForm.status_kondisi,
      tahun_pembangunan: createForm.tahun_pembangunan,
      sumber_dana: createForm.sumber_dana,
      status_aset: createForm.status_aset,
      sumber_data: createForm.sumber_data,
      keterangan: createForm.keterangan,
      geometry: createForm.geometry,
    });

    addLog('INFO', `Segmen fisik baru berhasil disimpan: ${createForm.namobj || 'Draft'}`);

    isCreateModalOpen.value = false;
    refreshAll();
    refreshSetupPlottingOptions();

    if (res?.data) {
      handleFeatureSelect(res.data);
    }
    const quotaWarning = (res as any)?.quota_warning ? String((res as any).quota_warning) : null;
    segmenPanelFeedback.value = quotaWarning
      ? { type: 'warning', message: `Segmen tersimpan (draft). ${quotaWarning}` }
      : { type: 'success', message: 'Segmen tersimpan sebagai draft.' };
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Gagal menyimpan segmen.';
    createModalFeedback.value = { type: 'error', message: msg };
    addLog('ERROR', `Gagal menyimpan segmen: ${msg}`);
  } finally {
    isCreating.value = false;
  }
}

// ─── Edit Action ──────────────────────────────────────────────────────────────
async function handleSaveSegmen(payload: Partial<InfrastrukturSegmen>) {
  if (!selectedSegmen.value || isSavingSegmen.value) return;
  segmenPanelFeedback.value = null;
  isSavingSegmen.value = true;
  try {
    await api.updateSegmen(selectedSegmen.value.id, payload);
    addLog('INFO', `Atribut segmen ${selectedSegmen.value.namobj || selectedSegmen.value.id} diperbarui.`);
    refreshAll();
    const res = await api.getSegmen(selectedSegmen.value.id);
    if (res?.data) selectedSegmen.value = res.data;
    rightPanelRef.value?.setActiveTab('inspector');
    mobileRightPanelRef.value?.setActiveTab('inspector');
    segmenPanelFeedback.value = { type: 'success', message: 'Atribut segmen diperbarui.' };
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Gagal memperbarui segmen.';
    segmenPanelFeedback.value = { type: 'error', message: msg };
    addLog('ERROR', `Gagal memperbarui segmen: ${msg}`);
  } finally {
    isSavingSegmen.value = false;
  }
}

// ─── Delete Action ────────────────────────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const segmenToDelete = ref<InfrastrukturSegmen | null>(null);
const isDeleting = ref(false);
const deleteModalError = ref<string | null>(null);

function confirmDelete(seg: InfrastrukturSegmen) {
  deleteModalError.value = null;
  segmenToDelete.value = seg;
  nextTick(() => {
    isDeleteModalOpen.value = true;
  });
}

async function handleDelete() {
  if (!segmenToDelete.value || isDeleting.value) return;
  deleteModalError.value = null;
  isDeleting.value = true;
  const target = segmenToDelete.value;
  try {
    await api.deleteSegmen(target.id);
    addLog('WARN', `Segmen ${target.namobj || target.id} dihapus permanen.`);
    isDeleteModalOpen.value = false;
    segmenToDelete.value = null;
    if (selectedSegmenId.value === target.id) {
      segmenPanelFeedback.value = null;
      selectedSegmenId.value = null;
      selectedSegmen.value = null;
      collapseRightPanel();
      syncQueryToUrl();
    }
    refreshAll();
    refreshSetupPlottingOptions();
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Tidak dapat menghapus segmen.';
    deleteModalError.value = msg;
    addLog('ERROR', `Gagal menghapus segmen: ${msg}`);
  } finally {
    isDeleting.value = false;
  }
}

// ─── Submit & Berjenjang Verifikasi Action ────────────────────────────────────
async function handleSubmitVerifikasi(seg: InfrastrukturSegmen) {
  try {
    await api.submitSegmen(seg.id);
    addLog('INFO', `Segmen ${seg.namobj || seg.id} diajukan ke tahap verifikasi kecamatan.`);
    refreshAll();
    if (selectedSegmen.value?.id === seg.id) {
      const res = await api.getSegmen(seg.id);
      if (res?.data) selectedSegmen.value = res.data;
      segmenPanelFeedback.value = {
        type: 'success',
        message: 'Segmen diajukan (Diajukan ke Kecamatan) dan siap diteruskan ke Bappeda.',
      };
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Tidak dapat mengajukan verifikasi.';
    if (selectedSegmen.value?.id === seg.id) {
      segmenPanelFeedback.value = { type: 'error', message: msg };
    }
    addLog('ERROR', `Gagal mengajukan verifikasi: ${msg}`);
  }
}

const isVerifyModalOpen = ref(false);
const verifyTargetSegmen = ref<InfrastrukturSegmen | null>(null);
const verifyLevel = ref<'kecamatan' | 'bappeda'>('kecamatan');
const verifyAction = ref<'approve' | 'reject'>('approve');
const verifyCatatan = ref('');
const verifyingSegmen = ref(false);
const verifyModalError = ref<string | null>(null);

function openVerifyModal(
  seg: InfrastrukturSegmen,
  level?: 'kecamatan' | 'bappeda',
  defaultAction: 'approve' | 'reject' = 'approve',
) {
  const resolvedLevel =
    level ||
    (canVerifyBappedaSegmen(seg) ? 'bappeda' : 'kecamatan');
  verifyTargetSegmen.value = seg;
  verifyLevel.value = resolvedLevel;
  verifyAction.value = defaultAction;
  verifyCatatan.value = '';
  verifyModalError.value = null;
  nextTick(() => {
    isVerifyModalOpen.value = true;
  });
}

async function handleConfirmVerify() {
  if (!verifyTargetSegmen.value || verifyingSegmen.value) return;
  verifyModalError.value = null;

  const trimmedNote = verifyCatatan.value.trim();
  if (verifyAction.value === 'reject' && !trimmedNote) {
    verifyModalError.value = 'Catatan alasan revisi wajib diisi saat menolak segmen.';
    return;
  }

  const target = verifyTargetSegmen.value;
  verifyingSegmen.value = true;
  try {
    const res =
      verifyLevel.value === 'bappeda'
        ? await api.verifyBappeda(target.id, verifyAction.value, trimmedNote || undefined)
        : await api.verifyKecamatan(target.id, verifyAction.value, trimmedNote || undefined);

    const statusMsg =
      res?.message ||
      (verifyAction.value === 'approve'
        ? verifyLevel.value === 'kecamatan'
          ? 'Segmen disetujui Kecamatan dan diteruskan ke Bappeda.'
          : 'Segmen disahkan final oleh Bappeda.'
        : 'Segmen dikembalikan untuk revisi.');

    addLog('INFO', `${target.namobj || target.id}: ${statusMsg}`);
    isVerifyModalOpen.value = false;
    verifyTargetSegmen.value = null;

    refreshAll();
    if (selectedSegmen.value?.id === target.id) {
      const detailRes = await api.getSegmen(target.id);
      if (detailRes?.data) selectedSegmen.value = detailRes.data;
      segmenPanelFeedback.value = {
        type: verifyAction.value === 'approve' ? 'success' : 'warning',
        message: statusMsg,
      };
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Gagal memproses verifikasi.';
    verifyModalError.value = msg;
    addLog('ERROR', `Gagal verifikasi: ${msg}`);
  } finally {
    verifyingSegmen.value = false;
  }
}

// ─── Edit Geometry & Split Handlers ──────────────────────────────────────────
const isSplitting = ref(false);
const isSplitDialogOpen = ref(false);
const splitTargetSegmen = ref<InfrastrukturSegmen | null>(null);
const splitPointCoordinate = ref<[number, number] | null>(null);

function handleStartEditGeometry(seg?: InfrastrukturSegmen | null) {
  const target = seg || selectedSegmen.value;
  if (!target) return;
  mapCanvasRef.value?.startEditGeometry(target);
  mobileMapCanvasRef.value?.startEditGeometry(target);
  addLog('INFO', `Memulai edit geometri untuk: "${target.namobj || target.id}".`);
}

async function handleEditGeometrySaved(payload: { id: string; geojson: any; lengthMeters: number }) {
  try {
    const res = await api.patchSegmenGeometry(payload.id, payload.geojson);
    if (res?.ok) {
      toast.add({
        title: 'Geometri Diperbarui',
        description: `Koordinat geometri segmen berhasil disimpan (${payload.lengthMeters} m).`,
        color: 'success',
      });
      addLog('INFO', `Geometri segmen ${payload.id} diperbarui (${payload.lengthMeters} m).`);
      mapCanvasRef.value?.refreshVectorTiles();
      mobileMapCanvasRef.value?.refreshVectorTiles();
      await refreshAll();
      if (selectedSegmenId.value === payload.id) {
        const fresh = await api.getSegmen(payload.id);
        if (fresh?.data) selectedSegmen.value = fresh.data;
      }
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Gagal memperbarui geometri segmen.';
    toast.add({ title: 'Gagal Edit Geometri', description: msg, color: 'error' });
    addLog('ERROR', `Gagal edit geometri: ${msg}`);
  }
}

function handleEditGeometryCanceled() {
  addLog('INFO', 'Mode edit geometri dibatalkan.');
}

function handleStartSplitSegmen(seg?: InfrastrukturSegmen | null) {
  const target = seg || selectedSegmen.value;
  if (!target) return;
  splitTargetSegmen.value = target;
  mapCanvasRef.value?.startSplitMode(target);
  mobileMapCanvasRef.value?.startSplitMode(target);
  addLog('INFO', `Mode split aktif untuk "${target.namobj || target.id}". Klik garis untuk titik potong.`);
}

function handleSplitPointSelected(payload: { segmen: InfrastrukturSegmen; coordinate: [number, number] }) {
  splitTargetSegmen.value = payload.segmen;
  splitPointCoordinate.value = payload.coordinate;
  isSplitDialogOpen.value = true;
}

function handleCancelSplit() {
  isSplitDialogOpen.value = false;
  splitPointCoordinate.value = null;
  mapCanvasRef.value?.cancelSplitMode();
  mobileMapCanvasRef.value?.cancelSplitMode();
}

async function handleConfirmSplit(names: { part1Name: string; part2Name: string }) {
  if (!splitTargetSegmen.value || !splitPointCoordinate.value) return;

  isSplitting.value = true;
  try {
    const targetId = splitTargetSegmen.value.id;
    const res = await api.splitSegmen(targetId, {
      point: splitPointCoordinate.value,
      part1: { namobj: names.part1Name },
      part2: { namobj: names.part2Name },
    });

    if (res?.ok) {
      toast.add({
        title: 'Berhasil Split Segmen',
        description: `Segmen berhasil dipecah menjadi "${names.part1Name}" dan "${names.part2Name}".`,
        color: 'success',
      });
      addLog('INFO', `Segmen "${splitTargetSegmen.value.namobj}" dipecah menjadi 2 bagian.`);
      isSplitDialogOpen.value = false;
      splitTargetSegmen.value = null;
      splitPointCoordinate.value = null;
      mapCanvasRef.value?.cancelSplitMode();
      mobileMapCanvasRef.value?.cancelSplitMode();
      mapCanvasRef.value?.refreshVectorTiles();
      mobileMapCanvasRef.value?.refreshVectorTiles();
      await refreshAll();
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Gagal memotong segmen.';
    toast.add({ title: 'Gagal Split', description: msg, color: 'error' });
    addLog('ERROR', `Gagal split segmen: ${msg}`);
  } finally {
    isSplitting.value = false;
  }
}

// ─── Desktop Filter & Simbology Dialog States ─────────────────────────────────
const isFilterDialogOpen = ref(false);
const isSymbologyDialogOpen = ref(false);

// ─── Mobile Drawer & Action Dock State ────────────────────────────────────────
export type MobileDrawerTabType = 'layer' | 'symbology' | 'filter' | 'table' | 'inspector' | 'digitasi-setup';
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<MobileDrawerTabType>('layer');
const mobileDrawerSnap = ref<'peek' | 'full'>('peek');

watch(mobileDrawerOpen, (open) => {
  if (!open && mobileDrawerTab.value === 'digitasi-setup' && isDigitasiSetupOpen.value && !isPickingAutoPoints.value) {
    handleCloseDigitasiSetup();
  }
});

function openMobileTab(tab: MobileDrawerTabType) {
  if (tab !== 'digitasi-setup' && isDigitasiSetupOpen.value) {
    handleCloseDigitasiSetup(false);
  }
  const targetSnap: 'peek' | 'full' = (tab === 'table' || tab === 'filter') ? 'full' : 'peek';
  if (!mobileDrawerOpen.value || tab === 'digitasi-setup') {
    mobileDrawerSnap.value = targetSnap;
    mobileDrawerOpen.value = true;
  }
  mobileDrawerTab.value = tab;
  if (tab === 'table' && tableRows.value.length === 0) {
    loadTable();
  }
}

function switchMobileTab(tab: MobileDrawerTabType) {
  if (tab !== 'digitasi-setup' && isDigitasiSetupOpen.value) {
    handleCloseDigitasiSetup(false);
  }
  mobileDrawerTab.value = tab;
  // Catatan: mobileDrawerSnap sengaja dipertahankan agar tinggi sheet tidak berubah saat berganti tab
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
  updateViewportMode();
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateViewportMode);
  }

  // 1. Ambil Master Data Tipe & Plotting
  loadMasterData();

  // 2. Muat ringkasan spasial (bbox awal) & data tabel pertama kali
  await Promise.all([loadSummary(false), loadTable()]);

  // 3. Pastikan bottom & right panel tetap collapsed saat awal buka halaman
  nextTick(() => {
    if (!bottomPanelOpen.value) {
      verticalSplitterRef.value?.panelsRef?.[1]?.collapse?.();
    }
    if (!selectedSegmen.value && !isDigitasiSetupOpen.value) {
      collapseRightPanel();
    }
  });

  // 4. Hidrasi jika URL membawa ID segmen terpilih (?id=...)
  const initialId = (route.query.id as string) || (route.query.segmen_id as string) || null;
  if (initialId) {
    try {
      const res = await api.getSegmen(initialId);
      if (res?.ok && res.data) {
        handleFeatureSelect(res.data, true);
      } else {
        handleInvalidSegmenId();
      }
    } catch {
      handleInvalidSegmenId();
    }
  }

  addLog('INFO', 'Editor spasial siap digunakan.');
});

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateViewportMode);
  }
  if (leftPaneResizeObserver) {
    leftPaneResizeObserver.disconnect();
    leftPaneResizeObserver = null;
  }
});
</script>

<template>
  <div class="flex flex-col w-full h-[calc(100dvh-3.5rem)] overflow-hidden bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-gray-100 font-['Plus_Jakarta_Sans',sans-serif]">
    <!-- ═══ TOPBAR (Clean Solid Admin Header / Contextual Desktop Mode Toolbar) ═══ -->
    <header
      class="flex items-center justify-between gap-2 sm:gap-3 h-11 border-b shrink-0 z-30 transition-colors duration-150"
      :class="[
        (isDesktop && !desktopSpatialMode && !isLeftPanelCollapsed) ? 'pl-0 pr-3 sm:pr-4' : 'px-3 sm:px-4',
        desktopSpatialMode === 'edit-geometry'
          ? 'bg-blue-50/90 dark:bg-blue-950/35 border-blue-200 dark:border-blue-800/80'
          : desktopSpatialMode === 'split' || desktopSpatialMode === 'draw'
            ? 'bg-amber-50/90 dark:bg-amber-950/35 border-amber-200 dark:border-amber-800/80'
            : 'bg-white dark:bg-[#0b0f19] border-gray-200 dark:border-gray-800',
      ]"
    >
      <!-- Contextual Toolbar Mode 1: Edit Geometri (Desktop) -->
      <template v-if="desktopSpatialMode === 'edit-geometry'">
        <div class="flex items-center gap-2.5 min-w-0">
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-700 text-white text-[11px] font-semibold shrink-0">
            <UIcon name="i-lucide-pen-tool" class="size-3.5" />
            <span>Edit Geometri</span>
          </span>
          <span class="text-xs font-medium text-gray-800 dark:text-gray-100 truncate max-w-[240px] xl:max-w-[340px]">
            {{ desktopEditSegmenName }}
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white dark:bg-[#0b0f19] border border-blue-200 dark:border-blue-800 text-[11px] font-mono font-semibold tabular-nums text-blue-700 dark:text-blue-300 shrink-0">
            <UIcon name="i-lucide-route" class="size-3 shrink-0" />
            <span>{{ desktopEditedLengthMeters.toLocaleString('id-ID') }} m</span>
          </span>
          <span class="hidden xl:inline text-[11px] text-gray-600 dark:text-gray-400 truncate">
            Geser titik vertex pada peta untuk mengubah bentuk ruas
          </span>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <UButton
            icon="i-lucide-x"
            label="Batal"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer bg-white dark:bg-[#0b0f19]"
            @click="handleCancelEditGeometry"
          />
          <UButton
            icon="i-lucide-check"
            label="Simpan Geometri"
            size="xs"
            color="primary"
            variant="solid"
            class="cursor-pointer"
            @click="handleSaveEditedGeometry"
          />
        </div>
      </template>

      <!-- Contextual Toolbar Mode 2: Split / Potong Segmen (Desktop) -->
      <template v-else-if="desktopSpatialMode === 'split'">
        <div class="flex items-center gap-2.5 min-w-0">
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-700 text-white text-[11px] font-semibold shrink-0">
            <UIcon name="i-lucide-scissors" class="size-3.5" />
            <span>Potong Segmen</span>
          </span>
          <span class="text-xs font-medium text-gray-800 dark:text-gray-100 truncate max-w-[240px] xl:max-w-[340px]">
            {{ desktopSplitSegmenName }}
          </span>
          <span class="hidden md:inline text-[11px] text-amber-800 dark:text-amber-300 truncate">
            Klik titik pada garis segmen di peta untuk memotong menjadi 2 bagian
          </span>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <UButton
            icon="i-lucide-x"
            label="Batal"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer bg-white dark:bg-[#0b0f19]"
            @click="handleCancelSplitMode"
          />
        </div>
      </template>

      <!-- Contextual Toolbar Mode 3: Digitasi / Draw Segmen Baru (Desktop) -->
      <template v-else-if="desktopSpatialMode === 'draw'">
        <div class="flex items-center gap-2 min-w-0">
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-700 text-white text-[11px] font-semibold shrink-0">
            <UIcon name="i-lucide-pencil" class="size-3.5" />
            <span>Digitasi</span>
          </span>

          <div
            v-if="activeReferenceLabel"
            class="inline-flex items-center gap-1.5 pl-2 pr-1 py-0.5 rounded-md border text-[11px] font-medium max-w-[260px] min-w-0"
            :class="activeDigitasiRef
              ? 'bg-sky-50 dark:bg-sky-950/50 border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300'
              : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300'"
          >
            <UIcon name="i-lucide-route" class="size-3 shrink-0" />
            <span class="truncate">{{ activeReferenceLabel }}</span>
            <button
              type="button"
              aria-label="Ubah referensi jalan poros"
              class="ml-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-white/80 dark:bg-black/30 hover:bg-white dark:hover:bg-black/50 text-current cursor-pointer shrink-0"
              @click="handleReopenDigitasiSetup"
            >
              Ubah
            </button>
          </div>

          <div
            v-if="desktopDrawnPointCount > 0"
            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-white dark:bg-[#0b0f19] border border-amber-200 dark:border-amber-800 text-[11px] font-mono tabular-nums shrink-0"
          >
            <span class="inline-flex items-center gap-1 font-semibold text-amber-700 dark:text-amber-300">
              <UIcon name="i-lucide-route" class="size-3 shrink-0" />
              <span>{{ desktopDrawnLengthMeters.toLocaleString('id-ID') }} m</span>
            </span>
            <span class="w-px h-3 bg-gray-200 dark:bg-gray-800" />
            <span class="text-gray-700 dark:text-gray-300">{{ desktopDrawnPointCount }} titik</span>
            <span
              v-if="desktopHasCompletedLine"
              class="font-sans font-semibold text-blue-600 dark:text-blue-400"
            >
              (Selesai)
            </span>
          </div>

          <span class="hidden xl:inline text-[11px] text-gray-600 dark:text-gray-400 truncate">
            {{ desktopHasCompletedLine ? 'Garis selesai, klik Simpan untuk isi atribut' : 'Klik peta untuk titik baru, klik ganda untuk mengakhiri garis' }}
          </span>
        </div>

        <div class="flex items-center gap-1 shrink-0">
          <UTooltip text="Mode Gambar Garis (Draw)">
            <UButton
              icon="i-lucide-pencil"
              size="xs"
              :color="desktopIsDrawInteracting ? 'warning' : 'neutral'"
              :variant="desktopIsDrawInteracting ? 'solid' : 'ghost'"
              aria-label="Mode gambar garis"
              class="cursor-pointer"
              @click="handleDrawClick"
            >
              <span class="hidden xl:inline">Gambar</span>
            </UButton>
          </UTooltip>

          <UTooltip text="Gambar Ulang (Redraw)">
            <UButton
              icon="i-lucide-rotate-ccw"
              size="xs"
              color="neutral"
              variant="ghost"
              aria-label="Gambar ulang garis"
              class="cursor-pointer"
              @click="handleRedraw"
            >
              <span class="hidden xl:inline">Ulang</span>
            </UButton>
          </UTooltip>

          <UTooltip text="Undo (Batalkan Titik)">
            <UButton
              icon="i-lucide-undo-2"
              size="xs"
              color="neutral"
              variant="ghost"
              aria-label="Batalkan titik terakhir"
              class="cursor-pointer"
              :disabled="!desktopCanUndo"
              @click="handleUndo"
            />
          </UTooltip>

          <UTooltip text="Redo (Kembalikan Titik)">
            <UButton
              icon="i-lucide-redo-2"
              size="xs"
              color="neutral"
              variant="ghost"
              aria-label="Kembalikan titik terakhir"
              class="cursor-pointer"
              :disabled="!desktopCanRedo"
              @click="handleRedo"
            />
          </UTooltip>

          <div class="w-px h-4 bg-gray-300 dark:bg-gray-700 mx-0.5" />

          <UTooltip :text="desktopIsSnappingEnabled ? 'Snapping Aktif: Klik untuk nonaktifkan' : 'Snapping Nonaktif: Klik untuk aktifkan'">
            <UButton
              icon="i-lucide-magnet"
              size="xs"
              :color="desktopIsSnappingEnabled ? 'primary' : 'neutral'"
              :variant="desktopIsSnappingEnabled ? 'subtle' : 'ghost'"
              :aria-pressed="desktopIsSnappingEnabled"
              aria-label="Toggle snapping jalan"
              class="cursor-pointer font-mono tabular-nums"
              @click="handleToggleSnap"
            >
              <span class="font-sans">{{ desktopIsSnappingEnabled ? 'Snap Aktif' : 'Snap Off' }}</span>
              <UIcon v-if="desktopIsSnappingEnabled && desktopIsSnapLoading" name="i-lucide-loader-2" class="size-3 animate-spin" />
              <span v-else-if="desktopIsSnappingEnabled && desktopSnapRoadCount > 0" class="text-[10px] opacity-80">({{ desktopSnapRoadCount }})</span>
            </UButton>
          </UTooltip>

          <div class="w-px h-4 bg-gray-300 dark:bg-gray-700 mx-0.5" />

          <UButton
            icon="i-lucide-x"
            label="Batal"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer bg-white dark:bg-[#0b0f19]"
            @click="handleCancelDrawing"
          />

          <UButton
            icon="i-lucide-check"
            label="Simpan"
            size="xs"
            color="primary"
            variant="solid"
            class="cursor-pointer"
            :disabled="!desktopCanSave"
            @click="handleConfirmDraw"
          />
        </div>
      </template>

      <!-- Normal Mode Header -->
      <template v-else>
        <!-- Left: Title Column aligned with Left Panel Boundary + Filter Wilayah -->
        <div class="flex items-center min-w-0 flex-1 h-full">
          <div
            class="flex items-center justify-between gap-2 min-w-0 shrink-0 h-full"
            :class="isDesktop && !isLeftPanelCollapsed ? 'px-3' : 'pr-2'"
            :style="isDesktop && !isLeftPanelCollapsed ? { width: `${leftPanelActualWidth}px` } : undefined"
          >
            <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
              <UIcon name="i-lucide-network" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
              <h1 class="text-xs font-semibold text-gray-800 dark:text-gray-200 capitalize tracking-wide truncate">
                Segmen Fisik Infrastruktur
              </h1>
            </div>

            <div v-if="isDesaRestricted || isKecamatanRestricted" class="hidden md:flex items-center gap-1.5 shrink-0">
              <UBadge
                v-if="isDesaRestricted"
                label="Tingkat Desa"
                color="warning"
                variant="subtle"
                size="xs"
                icon="i-lucide-map-pin"
              />
              <UBadge
                v-else-if="isKecamatanRestricted"
                label="Tingkat Kecamatan"
                color="info"
                variant="subtle"
                size="xs"
                icon="i-lucide-map-pin"
              />
            </div>
          </div>

          <div
            v-if="isDesktop && !isLeftPanelCollapsed"
            class="w-1 h-full flex justify-center shrink-0"
            aria-hidden="true"
          >
            <div class="w-px h-full bg-gray-200 dark:bg-gray-800" />
          </div>
          <USeparator v-else orientation="vertical" class="h-4 hidden md:block shrink-0" />

          <!-- Filter Wilayah (Kecamatan & Desa) Terintegrasi Role Permission -->
          <div
            class="hidden md:flex items-center min-w-0"
            :class="isDesktop && !isLeftPanelCollapsed ? 'pl-3' : 'pl-2.5'"
          >
            <WilayahSelector
              :model-kecamatan="filterKecamatan"
              :model-desa="filterDesa"
              layout="inline"
              :allow-all="true"
              :show-clear="false"
              :show-labels="false"
              size="xs"
              @update:model-kecamatan="handleKecamatanFilterChange"
              @update:model-desa="handleDesaFilterChange"
            />
          </div>
        </div>

        <!-- Right: Action Buttons -->
        <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
          <UButton
            icon="i-lucide-filter"
            :label="totalActiveFilterBadge > 0 ? `Filter (${totalActiveFilterBadge})` : 'Filter'"
            size="xs"
            :color="totalActiveFilterBadge > 0 ? 'primary' : 'neutral'"
            :variant="totalActiveFilterBadge > 0 ? 'subtle' : 'ghost'"
            class="hidden lg:inline-flex cursor-pointer"
            @click="isFilterDialogOpen = true"
          />

          <UTooltip v-if="hasAnyActiveFilter" text="Hapus semua filter aktif">
            <UButton
              icon="i-lucide-filter-x"
              label="Clear"
              size="xs"
              color="error"
              variant="subtle"
              aria-label="Hapus semua filter aktif"
              class="relative cursor-pointer after:content-[''] after:absolute after:-inset-y-1.5 after:-inset-x-0.5 sm:after:hidden"
              @click="resetActiveLayerFilters"
            />
          </UTooltip>

          <UTooltip text="Sinkronkan Data & Peta">
            <UButton
              icon="i-lucide-refresh-cw"
              size="xs"
              color="neutral"
              variant="ghost"
              aria-label="Sinkronkan data dan peta"
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
              :aria-label="allPanelsHidden ? 'Tampilkan semua panel' : 'Aktifkan mode peta penuh'"
              :class="allPanelsHidden
                ? 'text-blue-600 dark:text-blue-400'
                : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
              class="hidden lg:inline-flex cursor-pointer"
              @click="toggleAllPanels"
            />
          </UTooltip>

          <UButton
            v-if="canCreate"
            icon="i-lucide-pen-tool"
            label="Mulai Digitasi"
            size="xs"
            color="warning"
            :variant="isDigitasiSetupOpen ? 'subtle' : 'solid'"
            class="hidden sm:inline-flex cursor-pointer"
            :disabled="isDrawingMode"
            @click="handleStartDrawing"
          />
          <UButton
            v-if="canCreate"
            icon="i-lucide-pen-tool"
            size="xs"
            color="warning"
            :variant="isDigitasiSetupOpen ? 'subtle' : 'solid'"
            aria-label="Mulai digitasi segmen baru"
            class="sm:hidden cursor-pointer"
            :disabled="isDrawingMode"
            @click="handleStartDrawing"
          />
        </div>
      </template>
    </header>

    <!-- ═══ MAIN WORKSPACE (USplitter with 60 FPS Drag Optimization) ══════════ -->
    <div class="flex flex-1 overflow-hidden relative w-full h-full">
      <!-- Desktop View: Nested Two-Tier USplitter (hanya dimount saat viewport desktop) -->
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
          <!-- Workspace: Horizontal Splitter -->
          <template #workspace>
            <USplitter
              ref="horizontalSplitterRef"
              :items="horizontalSplitterItems"
              orientation="horizontal"
              class="w-full h-full"
              @dragging="onHorizontalDragging"
              @collapse="onHorizontalCollapse"
              @expand="onHorizontalExpand"
              @resize="onHorizontalResize"
              :ui="{
                root: 'h-full w-full',
                panel: isDraggingHorizontal ? '' : 'panel-smooth-transition',
                handle: 'w-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-col-resize'
              }"
            >
              <!-- Panel Kiri: Layer & Simbologi -->
              <template #left="{ collapsed, collapse, expand, resize }">
                <div ref="leftPaneContainerRef" class="h-full w-full flex flex-col min-h-0 overflow-hidden">
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
                  v-model:selected-tahun-pembangunan="filterTahunPembangunan"
                  v-model:selected-perkerasan="filterPerkerasan"
                  v-model:selected-kondisi="filterKondisi"
                  v-model:selected-status-verifikasi="filterStatusVerifikasi"
                  v-model:selected-status-jalan="filterStatusJalan"
                  v-model:selected-status-kondisi="filterStatusKondisi"
                  v-model:selected-status-aset="filterStatusAset"
                  v-model:selected-sumber-dana="filterSumberDana"
                  :jalan-poros-desa-kondisi-filter="filterJalanKondisi"
                  :jalan-poros-desa-perkerasan-filter="filterJalanPerkerasan"
                  v-model:model-kecamatan="filterKecamatan"
                  v-model:model-desa="filterDesa"
                  :kecamatan-name="currentKecamatanName"
                  :desa-name="currentDesaName"
                  :is-kecamatan-locked="isKecamatanRestricted"
                  :is-desa-locked="isDesaRestricted"
                  :show-inline-wilayah-selector="false"
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
                  @toggle-legend-filter="handleToggleLegendFilter"
                  />
                </div>
              </template>

              <!-- Map Canvas -->
              <template #map>
                <div class="w-full h-full relative overflow-hidden">
                  <MapCanvas
                    ref="mapCanvasRef"
                    :api-base="apiBase"
                    :active-layer-id="activeLayerId"
                    :selected-segmen-id="selectedSegmenId"
                    :selected-segmen="selectedSegmen"
                    :tipe-filter="filterTipe"
                    :tahun-pembangunan-filter="filterTahunPembangunan"
                    :perkerasan-filter="filterPerkerasan"
                    :kondisi-filter="filterKondisi"
                    :status-verifikasi-filter="filterStatusVerifikasi"
                    :status-jalan-filter="filterStatusJalan"
                    :status-kondisi-filter="filterStatusKondisi"
                    :status-aset-filter="filterStatusAset"
                    :sumber-dana-filter="filterSumberDana"
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
                    :clicked-coord-text="clickedCoordText"
                    :floating-mode-toolbar="false"
                    :interaction-mode="mapInteractionMode"
                    :selected-reference-road-id="selectedSetupRoadId"
                    :auto-pick-hint="autoPickHint"
                    v-model:basemap="currentBasemap"
                    v-model:mouse-coords="mouseCoords"
                    @select="handleFeatureSelect"
                    @pick-reference-road="handleMapPickReferenceRoad"
                    @pick-auto-point="handlePickAutoPoint"
                    @cancel-auto-pick="handleCancelAutoPick"
                    @toggle-identify="toggleIdentifyMode"
                    @log="(level, msg) => addLog(level, msg)"
                    @draw-saved="handleDrawSaved"
                    @draw-canceled="onDrawCanceled"
                    @map-click="(coord) => clickedCoordinate = coord"
                    @edit-geometry-saved="handleEditGeometrySaved"
                    @edit-geometry-canceled="handleEditGeometryCanceled"
                    @split-point-selected="handleSplitPointSelected"
                  />

                  <div
                    v-if="isRightPanelCollapsed && (isDigitasiSetupOpen || selectedSegmen)"
                    class="absolute right-3 top-1/2 -translate-y-1/2 z-20"
                  >
                    <UTooltip :text="isDigitasiSetupOpen ? 'Buka Panel Digitasi Setup' : 'Buka Detail Segmen'">
                      <UButton
                        :icon="isDigitasiSetupOpen ? 'i-lucide-pen-tool' : 'i-lucide-panel-right-open'"
                        :label="isDigitasiSetupOpen ? 'Digitasi Setup' : 'Detail'"
                        size="xs"
                        :color="isDigitasiSetupOpen ? 'warning' : 'neutral'"
                        variant="solid"
                        class="shadow-lg cursor-pointer"
                        @click="expandRightPanel"
                      />
                    </UTooltip>
                  </div>
                </div>
              </template>

              <!-- Panel Kanan: Pengaturan Digitasi atau Properties / Feature Inspector -->
              <template #right="{ collapsed, collapse, expand, resize }">
                <DigitasiSetupPanel
                  v-if="isDigitasiSetupOpen"
                  :collapsed="collapsed"
                  :collapsible="true"
                  :budget-mode="digitasiBudgetMode"
                  :plotting-items="setupPlottingItems"
                  :selected-plotting-id="selectedSetupPlottingId"
                  :selected-plotting-detail="selectedSetupPlottingDetail"
                  :plotting-loading="setupPlottingLoading"
                  :can-create-plotting="canCreatePlotting"
                  :sumber-dana-list="sumberDanaList"
                  :digitasi-method="digitasiMethod"
                  :ref-mode="digitasiRefMode"
                  :road-items="setupRoadItems"
                  :selected-road-id="selectedSetupRoadId"
                  :selected-road-detail="selectedSetupRoadDetail"
                  :road-loading="setupRoadLoading"
                  :zoom-loading="setupZoomLoading"
                  :road-error="setupRoadError"
                  :can-confirm="canConfirmDigitasiSetup"
                  :auto-scope="autoScope"
                  :auto-start-meter="autoStartMeter"
                  :auto-end-meter="autoEndMeter"
                  :auto-road-total-meters="autoRoadTotalMeters"
                  :auto-preview-length="autoSlicedResult?.lengthMeters ?? 0"
                  :auto-preview-vertex-count="autoSlicedResult?.coordinates.length ?? 0"
                  :auto-geometry-error="autoGeometryError"
                  :is-picking-auto-points="isPickingAutoPoints"
                  :auto-pick-step="autoPickStep"
                  :schedule-info="setupScheduleInfo"
                  :show-closed-plottings="showClosedSchedulePlottings"
                  :hidden-closed-plotting-count="hiddenClosedPlottingCount"
                  @update:collapsed="(c) => c ? collapse() : (resize ? resize(RIGHT_PANEL_WIDTH) : expand())"
                  @update:budget-mode="(m) => { digitasiBudgetMode = m; }"
                  @update:show-closed-plottings="(v) => { showClosedSchedulePlottings = v; }"
                  @plotting-change="(id) => { selectedSetupPlottingId = id; }"
                  @open-create-plotting="isQuickPlottingModalOpen = true"
                  @update:digitasi-method="handleSwitchDigitasiMethod"
                  @switch-ref-mode="handleSwitchSetupRefMode"
                  @road-change="handleSetupRoadChange"
                  @reload-roads="loadSetupRoadOptions"
                  @refocus-road="() => selectedSetupRoadDetail?.feature && focusMapToReferenceRoad(selectedSetupRoadDetail.feature, true)"
                  @update:auto-scope="handleUpdateAutoScope"
                  @update:auto-start-meter="handleUpdateAutoStartMeter"
                  @update:auto-end-meter="handleUpdateAutoEndMeter"
                  @toggle-pick-auto-points="handleTogglePickAutoPoints"
                  @swap-auto-direction="handleSwapAutoDirection"
                  @reset-auto-range="handleResetAutoRange"
                  @edit-auto-on-map="handleEditAutoGeometryOnMap"
                  @confirm="handleConfirmDigitasiSetup"
                  @cancel="() => handleCloseDigitasiSetup(true)"
                />
                <RightPanel
                  v-else-if="selectedSegmen"
                  ref="rightPanelRef"
                  :collapsed="collapsed"
                  :collapsible="true"
                  :selected-segmen="selectedSegmen"
                  :clicked-coordinate="clickedCoordinate"
                  :loading="isDetailLoading"
                  :saving="isSavingSegmen"
                  :tipe-list="tipeList"
                  :plotting-list="plottingList"
                  :sumber-dana-list="sumberDanaList"
                  :can-edit="canEditSegmen(selectedSegmen)"
                  :can-delete="canDeleteSegmen(selectedSegmen)"
                  :can-submit="canSubmitSegmen(selectedSegmen)"
                  :can-split="canEditSegmen(selectedSegmen) && canCreate"
                  :can-verify-kecamatan="canVerifyKecamatanSegmen(selectedSegmen)"
                  :can-verify-bappeda="canVerifyBappedaSegmen(selectedSegmen)"
                  :is-kecamatan-role="isKecamatanRestricted"
                  :save-feedback="segmenPanelFeedback"
                  @update:collapsed="(c) => c ? collapse() : (resize ? resize(RIGHT_PANEL_WIDTH) : expand())"
                  @close="handleFeatureSelect(null)"
                  @zoom-to-feature="handleZoomToFeature"
                  @delete-segmen="confirmDelete"
                  @submit-verifikasi="handleSubmitVerifikasi"
                  @open-verify="({ segmen, level, defaultAction }) => openVerifyModal(segmen, level, defaultAction)"
                  @save-segmen="handleSaveSegmen"
                  @fotos-updated="({ segmenId, fotos }) => { if (selectedSegmen && selectedSegmen.id === segmenId) { selectedSegmen = { ...selectedSegmen, fotos, foto_url: fotos[0]?.file_endpoint ?? null }; } }"
                  @clear-feedback="segmenPanelFeedback = null"
                  @view-in-table="handleViewInTable"
                  @edit-geometry="handleStartEditGeometry"
                  @split-segmen="handleStartSplitSegmen"
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
              :total-panjang-meter="totalPanjangMeter"
              :table-page="tablePage"
              :table-last-page="tableLastPage"
              :table-per-page="tablePerPage"
              v-model:table-search="tableSearch"
              :table-loading="loadingTable"
              :table-error="tableError"
              :selected-segmen-id="selectedSegmenId"
              :console-logs="consoleLogs"
              :can-edit="canEdit"
              :can-delete="canDelete"
              :can-submit="canSubmit"
              :can-edit-row="canEditSegmen"
              :can-delete-row="canDeleteSegmen"
              :can-submit-row="canSubmitSegmen"
              :can-verify-row="canVerifyAnyRow"
              :tipe-list="tipeList"
              :sumber-dana-list="sumberDanaList"
              :selected-tipe="filterTipe"
              :selected-tahun-pembangunan="filterTahunPembangunan"
              :selected-perkerasan="filterPerkerasan"
              :selected-kondisi="filterKondisi"
              :selected-status-verifikasi="filterStatusVerifikasi"
              :selected-status-jalan="filterStatusJalan"
              :selected-status-kondisi="filterStatusKondisi"
              :selected-status-aset="filterStatusAset"
              :selected-sumber-dana="filterSumberDana"
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
              @update:selected-tahun-pembangunan="(val) => filterTahunPembangunan = val"
              @update:selected-perkerasan="(val) => filterPerkerasan = val"
              @update:selected-kondisi="(val) => filterKondisi = val"
              @update:selected-status-verifikasi="(val) => filterStatusVerifikasi = val"
              @update:selected-status-jalan="(val) => filterStatusJalan = val"
              @update:selected-status-kondisi="(val) => filterStatusKondisi = val"
              @update:selected-status-aset="(val) => filterStatusAset = val"
              @update:selected-sumber-dana="(val) => filterSumberDana = val"
              @update:model-kecamatan="(val) => filterKecamatan = val"
              @update:model-desa="(val) => filterDesa = val"
              @reset-filters="resetFilters"
              @refresh="loadTable"
              @row-click="handleZoomToFeature"
              @edit-row="handleEditRowFromTable"
              @delete-row="confirmDelete"
              @submit-row="handleSubmitVerifikasi"
              @verify-row="(row) => openVerifyModal(row)"
              @clear-logs="consoleLogs = []"
            />
          </template>
        </USplitter>
      </div>

      <!-- Mobile View: Full-Bleed Map Canvas (hanya dimount saat viewport mobile/tablet) -->
      <div v-else class="flex-1 relative overflow-hidden h-full w-full">
        <MapCanvas
          ref="mobileMapCanvasRef"
          :api-base="apiBase"
          :active-layer-id="activeLayerId"
          :selected-segmen-id="selectedSegmenId"
          :selected-segmen="selectedSegmen"
          :tipe-filter="filterTipe"
          :tahun-pembangunan-filter="filterTahunPembangunan"
          :perkerasan-filter="filterPerkerasan"
          :kondisi-filter="filterKondisi"
          :status-verifikasi-filter="filterStatusVerifikasi"
          :status-jalan-filter="filterStatusJalan"
          :status-kondisi-filter="filterStatusKondisi"
          :status-aset-filter="filterStatusAset"
          :sumber-dana-filter="filterSumberDana"
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
          :clicked-coord-text="clickedCoordText"
          :floating-mode-toolbar="true"
          :reference-label="activeReferenceLabel"
          :interaction-mode="mapInteractionMode"
          :selected-reference-road-id="selectedSetupRoadId"
          :auto-pick-hint="autoPickHint"
          v-model:basemap="currentBasemap"
          v-model:mouse-coords="mouseCoords"
          @select="handleFeatureSelect"
          @pick-reference-road="handleMapPickReferenceRoad"
          @pick-auto-point="handlePickAutoPoint"
          @cancel-auto-pick="handleCancelAutoPick"
          @toggle-identify="toggleIdentifyMode"
          @log="(level, msg) => addLog(level, msg)"
          @draw-saved="handleDrawSaved"
          @draw-canceled="onDrawCanceled"
          @map-click="(coord) => clickedCoordinate = coord"
          @edit-geometry-saved="handleEditGeometrySaved"
          @edit-geometry-canceled="handleEditGeometryCanceled"
          @split-point-selected="handleSplitPointSelected"
        />
      </div>
    </div>

    <!-- ═══ MOBILE FEATURE PEEK CARD ═════════════════════════════════════════ -->
    <div
      v-if="selectedSegmen && !isDigitasiSetupOpen && !isDrawingMode"
      class="lg:hidden fixed bottom-[calc(3.875rem+env(safe-area-inset-bottom))] left-2.5 right-2.5 z-30 pointer-events-auto transition-transform duration-200"
    >
      <div class="p-2.5 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-xl space-y-1.5">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate">
              {{ selectedSegmen.namobj || 'Segmen Tanpa Nama' }}
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
              {{ formatWilayah(selectedSegmen.desa) }}, Kec. {{ formatWilayah(selectedSegmen.kecamatan) }}
            </p>
          </div>
          <UButton
            icon="i-lucide-x"
            size="xs"
            color="neutral"
            variant="ghost"
            aria-label="Tutup pratinjau segmen"
            class="relative p-0 cursor-pointer size-7 justify-center shrink-0 after:content-[''] after:absolute after:-inset-2"
            @click="handleFeatureSelect(null)"
          />
        </div>

        <div class="flex items-center justify-between pt-1.5 border-t border-gray-100 dark:border-gray-800/80 text-xs gap-2">
          <div class="flex items-center gap-1.5 min-w-0">
            <UBadge
              :label="selectedSegmen.kondisi || '-'"
              color="primary"
              variant="subtle"
              size="xs"
            />
            <span class="text-[11px] tabular-nums text-gray-600 dark:text-gray-400 truncate">
              {{ Number(selectedSegmen.panjang_meter_gis ?? selectedSegmen.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 4 }) }} m
            </span>
          </div>

          <div class="flex items-center gap-1.5 shrink-0">
            <UButton
              v-if="canEditSegmen(selectedSegmen)"
              icon="i-lucide-pencil"
              size="xs"
              color="neutral"
              variant="subtle"
              label="Edit"
              class="relative cursor-pointer min-h-[34px] px-2.5 after:content-[''] after:absolute after:-inset-y-1"
              @click="() => { openMobileTab('inspector'); nextTick(() => mobileRightPanelRef?.setActiveTab('edit')); }"
            />
            <UButton
              icon="i-lucide-info"
              size="xs"
              color="primary"
              variant="solid"
              label="Detail"
              class="relative cursor-pointer min-h-[34px] px-2.5 after:content-[''] after:absolute after:-inset-y-1"
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
            class="absolute -top-1 -right-2.5 px-1 text-[8px] tabular-nums rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-semibold"
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
        class="flex flex-col items-center justify-center min-h-[44px] py-1 px-1 transition-colors cursor-pointer group"
        :class="isDigitasiSetupOpen || isDrawingMode
          ? 'text-amber-700 dark:text-amber-300 font-semibold'
          : 'text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300'"
        @click="handleStartDrawing"
      >
        <div
          class="size-7 rounded-full text-white flex items-center justify-center shadow-md shadow-amber-500/30 group-active:scale-95 transition-transform"
          :class="isDigitasiSetupOpen || isDrawingMode ? 'bg-amber-600 ring-2 ring-amber-400/50' : 'bg-amber-500'"
        >
          <UIcon :name="digitasiMethod === 'auto' && isDigitasiSetupOpen ? 'i-lucide-waypoints' : 'i-lucide-pen-tool'" class="size-4 stroke-[2.5]" />
        </div>
        <span class="text-[10px] mt-0.5 leading-none font-medium">Digitasi</span>
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
            class="absolute -top-1 -right-2 px-1 text-[8px] tabular-nums rounded-full bg-blue-600 text-white font-bold"
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
      v-model:snap-level="mobileDrawerSnap"
      :title="
        mobileDrawerTab === 'layer'
          ? 'Daftar Layer & Data'
          : mobileDrawerTab === 'symbology'
            ? 'Pengaturan Simbologi Layer'
            : mobileDrawerTab === 'table'
              ? 'Tabel Atribut Segmen'
              : mobileDrawerTab === 'inspector'
                ? 'Detail Segmen Fisik'
                : mobileDrawerTab === 'digitasi-setup'
                  ? 'Mulai Digitasi Segmen'
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
                : mobileDrawerTab === 'digitasi-setup'
                  ? 'i-lucide-pen-tool'
                  : 'i-lucide-filter'
      "
    >
      <!-- Contextual Sub-Tab Header hanya untuk sub-mode berpasangan (Layer & Gaya) -->
      <template #tabs>
        <div
          v-if="mobileDrawerTab === 'layer' || mobileDrawerTab === 'symbology'"
          class="px-3 pt-1 pb-2 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30"
        >
          <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 dark:bg-gray-800/70 rounded-xl">
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'layer'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('layer')"
            >
              <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
              <span>Daftar Layer</span>
            </button>

            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[44px]"
              :class="mobileDrawerTab === 'symbology'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="switchMobileTab('symbology')"
            >
              <UIcon name="i-lucide-palette" class="size-3.5 shrink-0" />
              <span>Gaya & Warna</span>
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
          :selected-tahun-pembangunan="filterTahunPembangunan"
          :selected-perkerasan="filterPerkerasan"
          :selected-kondisi="filterKondisi"
          :selected-status-verifikasi="filterStatusVerifikasi"
          :selected-status-jalan="filterStatusJalan"
          :selected-status-kondisi="filterStatusKondisi"
          :selected-status-aset="filterStatusAset"
          :selected-sumber-dana="filterSumberDana"
          :jalan-poros-desa-kondisi-filter="filterJalanKondisi"
          :jalan-poros-desa-perkerasan-filter="filterJalanPerkerasan"
          v-model:model-kecamatan="filterKecamatan"
          v-model:model-desa="filterDesa"
          :kecamatan-name="currentKecamatanName"
          :desa-name="currentDesaName"
          :is-kecamatan-locked="isKecamatanRestricted"
          :is-desa-locked="isDesaRestricted"
          :show-inline-wilayah-selector="true"
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
          @toggle-legend-filter="handleToggleLegendFilter"
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
                'flex-1 py-1.5 px-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[44px]',
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
                'flex-1 py-1.5 px-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[44px]',
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
            @reset="jalanPorosDesaSymbology = { ...DEFAULT_JALAN_POROS_SYMBOLOGY }"
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
          <div
            role="tablist"
            aria-label="Pilih layer filter peta"
            class="mb-3 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl flex items-center gap-1 border border-slate-200/70 dark:border-slate-800"
          >
            <button
              type="button"
              role="tab"
              :aria-selected="activeLayerId === 'infrastruktur-segmen'"
              :class="[
                'flex-1 py-2 px-2.5 rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer min-h-[44px]',
                activeLayerId === 'infrastruktur-segmen'
                  ? 'bg-white dark:bg-[#0e1526] text-blue-600 dark:text-blue-400 font-semibold shadow-xs ring-1 ring-slate-200/80 dark:ring-slate-700/80'
                  : 'text-slate-600 dark:text-slate-400'
              ]"
              @click="activeLayerId = 'infrastruktur-segmen'"
            >
              <UIcon name="i-lucide-route" class="size-3.5 shrink-0" aria-hidden="true" />
              <span>Segmen Fisik</span>
              <UBadge
                v-if="activeSegmenFilterCount > 0"
                :label="String(activeSegmenFilterCount)"
                size="xs"
                color="primary"
                variant="subtle"
                class="text-[10px] px-1.5 py-0 h-4 tabular-nums"
              />
            </button>
            <button
              type="button"
              role="tab"
              :aria-selected="activeLayerId === 'jalan-poros-desa'"
              :class="[
                'flex-1 py-2 px-2.5 rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer min-h-[44px]',
                activeLayerId === 'jalan-poros-desa'
                  ? 'bg-white dark:bg-[#0e1526] text-blue-600 dark:text-blue-400 font-semibold shadow-xs ring-1 ring-slate-200/80 dark:ring-slate-700/80'
                  : 'text-slate-600 dark:text-slate-400'
              ]"
              @click="activeLayerId = 'jalan-poros-desa'"
            >
              <UIcon name="i-lucide-milestone" class="size-3.5 shrink-0" aria-hidden="true" />
              <span>Jalan Poros Desa</span>
              <UBadge
                v-if="activeJalanFilterCount > 0"
                :label="String(activeJalanFilterCount)"
                size="xs"
                color="info"
                variant="subtle"
                class="text-[9px] px-1 py-0 h-4 tabular-nums ml-0.5"
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
            :sumber-dana-list="sumberDanaList"
            :selected-tipe="filterTipe"
            :selected-tahun-pembangunan="filterTahunPembangunan"
            :selected-perkerasan="filterPerkerasan"
            :selected-kondisi="filterKondisi"
            :selected-status-verifikasi="filterStatusVerifikasi"
            :selected-status-jalan="filterStatusJalan"
            :selected-status-kondisi="filterStatusKondisi"
            :selected-status-aset="filterStatusAset"
            :selected-sumber-dana="filterSumberDana"
            :model-kecamatan="filterKecamatan"
            :model-desa="filterDesa"
            :is-kecamatan-locked="isKecamatanRestricted"
            :is-desa-locked="isDesaRestricted"
            @update:selected-tipe="(val) => filterTipe = val"
            @update:selected-tahun-pembangunan="(val) => filterTahunPembangunan = val"
            @update:selected-perkerasan="(val) => filterPerkerasan = val"
            @update:selected-kondisi="(val) => filterKondisi = val"
            @update:selected-status-verifikasi="(val) => filterStatusVerifikasi = val"
            @update:selected-status-jalan="(val) => filterStatusJalan = val"
            @update:selected-status-kondisi="(val) => filterStatusKondisi = val"
            @update:selected-status-aset="(val) => filterStatusAset = val"
            @update:selected-sumber-dana="(val) => filterSumberDana = val"
            @update:model-kecamatan="(val) => filterKecamatan = val"
            @update:model-desa="(val) => filterDesa = val"
            @apply="() => { mobileDrawerOpen = false; handleApplySegmenFilter(); }"
            @reset="resetSegmenFilters"
          />

          <!-- Filter Content Jalan Poros Desa -->
          <FilterContent
            v-else-if="activeLayerId === 'jalan-poros-desa'"
            :is-mobile="true"
            layer-id="jalan-poros-desa"
            layer-title="Jalan Poros Desa"
            :selected-perkerasan="filterJalanPerkerasan"
            :selected-kondisi="filterJalanKondisi"
            :model-kecamatan="filterJalanKecamatan"
            :model-desa="filterJalanDesa"
            :is-kecamatan-locked="isKecamatanRestricted"
            :is-desa-locked="isDesaRestricted"
            @update:selected-perkerasan="(val) => filterJalanPerkerasan = val"
            @update:selected-kondisi="(val) => filterJalanKondisi = val"
            @update:model-kecamatan="(val) => filterJalanKecamatan = val"
            @update:model-desa="(val) => filterJalanDesa = val"
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
          :total-panjang-meter="totalPanjangMeter"
          :table-page="tablePage"
          :table-last-page="tableLastPage"
          :table-per-page="tablePerPage"
          v-model:table-search="tableSearch"
          :table-loading="loadingTable"
          :table-error="tableError"
          :selected-segmen-id="selectedSegmenId"
          :console-logs="consoleLogs"
          :can-edit="canEdit"
          :can-delete="canDelete"
          :can-submit="canSubmit"
          :can-edit-row="canEditSegmen"
          :can-delete-row="canDeleteSegmen"
          :can-submit-row="canSubmitSegmen"
          :can-verify-row="canVerifyAnyRow"
          :tipe-list="tipeList"
          :sumber-dana-list="sumberDanaList"
          :selected-tipe="filterTipe"
          :selected-tahun-pembangunan="filterTahunPembangunan"
          :selected-perkerasan="filterPerkerasan"
          :selected-kondisi="filterKondisi"
          :selected-status-verifikasi="filterStatusVerifikasi"
          :selected-status-jalan="filterStatusJalan"
          :selected-status-kondisi="filterStatusKondisi"
          :selected-status-aset="filterStatusAset"
          :selected-sumber-dana="filterSumberDana"
          :model-kecamatan="filterKecamatan"
          :model-desa="filterDesa"
          :kecamatan-name="currentKecamatanName"
          :desa-name="currentDesaName"
          :can-reset-kecamatan="!isKecamatanRestricted"
          :can-reset-desa="!isDesaRestricted"
          @update:table-page="(p) => tablePage = p"
          @update:table-per-page="(pp) => tablePerPage = pp"
          @update:selected-tipe="(val) => filterTipe = val"
          @update:selected-tahun-pembangunan="(val) => filterTahunPembangunan = val"
          @update:selected-perkerasan="(val) => filterPerkerasan = val"
          @update:selected-kondisi="(val) => filterKondisi = val"
          @update:selected-status-verifikasi="(val) => filterStatusVerifikasi = val"
          @update:selected-status-jalan="(val) => filterStatusJalan = val"
          @update:selected-status-kondisi="(val) => filterStatusKondisi = val"
          @update:selected-status-aset="(val) => filterStatusAset = val"
          @update:selected-sumber-dana="(val) => filterSumberDana = val"
          @update:model-kecamatan="(val) => filterKecamatan = val"
          @update:model-desa="(val) => filterDesa = val"
          @reset-filters="resetFilters"
          @refresh="loadTable"
          @row-click="(row) => { handleZoomToFeature(row); mobileDrawerOpen = false; }"
          @edit-row="(row) => handleMobileEditRowFromTable(row)"
          @delete-row="(row) => { confirmDelete(row); mobileDrawerOpen = false; }"
          @submit-row="(row) => handleSubmitVerifikasi(row)"
          @verify-row="(row) => { mobileDrawerOpen = false; openVerifyModal(row); }"
          @clear-logs="consoleLogs = []"
        />

        <!-- Tab 5: Properties Tab -->
        <RightPanel
          v-else-if="mobileDrawerTab === 'inspector'"
          ref="mobileRightPanelRef"
          :is-mobile-drawer="true"
          :selected-segmen="selectedSegmen"
          :clicked-coordinate="clickedCoordinate"
          :loading="isDetailLoading"
          :saving="isSavingSegmen"
          :tipe-list="tipeList"
          :plotting-list="plottingList"
          :sumber-dana-list="sumberDanaList"
          :can-edit="canEditSegmen(selectedSegmen)"
          :can-delete="canDeleteSegmen(selectedSegmen)"
          :can-submit="canSubmitSegmen(selectedSegmen)"
          :can-split="canEditSegmen(selectedSegmen) && canCreate"
          :can-verify-kecamatan="canVerifyKecamatanSegmen(selectedSegmen)"
          :can-verify-bappeda="canVerifyBappedaSegmen(selectedSegmen)"
          :is-kecamatan-role="isKecamatanRestricted"
          :save-feedback="segmenPanelFeedback"
          @zoom-to-feature="(feat) => { handleZoomToFeature(feat); mobileDrawerOpen = false; }"
          @delete-segmen="(feat) => { confirmDelete(feat); mobileDrawerOpen = false; }"
          @submit-verifikasi="handleSubmitVerifikasi"
          @open-verify="({ segmen, level, defaultAction }) => { mobileDrawerOpen = false; openVerifyModal(segmen, level, defaultAction); }"
          @save-segmen="handleSaveSegmen"
          @fotos-updated="({ segmenId, fotos }) => { if (selectedSegmen && selectedSegmen.id === segmenId) { selectedSegmen = { ...selectedSegmen, fotos, foto_url: fotos[0]?.file_endpoint ?? null }; } }"
          @clear-feedback="segmenPanelFeedback = null"
          @view-in-table="(seg) => { if (seg) tableSearch = seg.namobj || ''; switchMobileTab('table'); }"
          @edit-geometry="(feat) => { mobileDrawerOpen = false; handleStartEditGeometry(feat); }"
          @split-segmen="(feat) => { mobileDrawerOpen = false; handleStartSplitSegmen(feat); }"
        />
        <!-- Tab 6: Setup Digitasi Mobile -->
        <DigitasiSetupPanel
          v-else-if="mobileDrawerTab === 'digitasi-setup'"
          :is-mobile-drawer="true"
          :budget-mode="digitasiBudgetMode"
          :plotting-items="setupPlottingItems"
          :selected-plotting-id="selectedSetupPlottingId"
          :selected-plotting-detail="selectedSetupPlottingDetail"
          :plotting-loading="setupPlottingLoading"
          :can-create-plotting="canCreatePlotting"
          :sumber-dana-list="sumberDanaList"
          :digitasi-method="digitasiMethod"
          :ref-mode="digitasiRefMode"
          :road-items="setupRoadItems"
          :selected-road-id="selectedSetupRoadId"
          :selected-road-detail="selectedSetupRoadDetail"
          :road-loading="setupRoadLoading"
          :zoom-loading="setupZoomLoading"
          :road-error="setupRoadError"
          :can-confirm="canConfirmDigitasiSetup"
          :auto-scope="autoScope"
          :auto-start-meter="autoStartMeter"
          :auto-end-meter="autoEndMeter"
          :auto-road-total-meters="autoRoadTotalMeters"
          :auto-preview-length="autoSlicedResult?.lengthMeters ?? 0"
          :auto-preview-vertex-count="autoSlicedResult?.coordinates.length ?? 0"
          :auto-geometry-error="autoGeometryError"
          :is-picking-auto-points="isPickingAutoPoints"
          :auto-pick-step="autoPickStep"
          :schedule-info="setupScheduleInfo"
          :show-closed-plottings="showClosedSchedulePlottings"
          :hidden-closed-plotting-count="hiddenClosedPlottingCount"
          @update:budget-mode="(m) => { digitasiBudgetMode = m; }"
          @update:show-closed-plottings="(v) => { showClosedSchedulePlottings = v; }"
          @plotting-change="(id) => { selectedSetupPlottingId = id; }"
          @open-create-plotting="isQuickPlottingModalOpen = true"
          @update:digitasi-method="handleSwitchDigitasiMethod"
          @switch-ref-mode="handleSwitchSetupRefMode"
          @road-change="handleSetupRoadChange"
          @reload-roads="loadSetupRoadOptions"
          @refocus-road="() => selectedSetupRoadDetail?.feature && focusMapToReferenceRoad(selectedSetupRoadDetail.feature, true)"
          @update:auto-scope="handleUpdateAutoScope"
          @update:auto-start-meter="handleUpdateAutoStartMeter"
          @update:auto-end-meter="handleUpdateAutoEndMeter"
          @toggle-pick-auto-points="handleTogglePickAutoPoints"
          @swap-auto-direction="handleSwapAutoDirection"
          @reset-auto-range="handleResetAutoRange"
          @edit-auto-on-map="handleEditAutoGeometryOnMap"
          @confirm="handleConfirmDigitasiSetup"
          @cancel="() => handleCloseDigitasiSetup(true)"
        />
      </div>
    </MobileBottomSheet>

    <!-- ═══ MODAL: Filter Data Layer Terpadu (Desktop Dialog) ══════════ -->
    <DialogFilter
      v-model:open="isFilterDialogOpen"
      v-model:active-layer-id="activeLayerId"
      :tipe-list="tipeList"
      :sumber-dana-list="sumberDanaList"
      v-model:selected-tipe="filterTipe"
      v-model:selected-tahun-pembangunan="filterTahunPembangunan"
      v-model:selected-perkerasan="filterPerkerasan"
      v-model:selected-kondisi="filterKondisi"
      v-model:selected-status-verifikasi="filterStatusVerifikasi"
      v-model:selected-status-jalan="filterStatusJalan"
      v-model:selected-status-kondisi="filterStatusKondisi"
      v-model:selected-status-aset="filterStatusAset"
      v-model:selected-sumber-dana="filterSumberDana"
      v-model:model-kecamatan="filterKecamatan"
      v-model:model-desa="filterDesa"
      :segmen-filter-count="activeSegmenFilterCount"
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
      @reset-jalan="jalanPorosDesaSymbology = { ...DEFAULT_JALAN_POROS_SYMBOLOGY }"
    />

    <!-- ═══ MODAL: Simpan Segmen Baru dari Digitasi ═══════════════════════════ -->
    <UModal
      v-model:open="isCreateModalOpen"
      :overlay="false"
      :modal="false"
      title="Simpan Segmen Fisik Baru"
      :ui="{ content: 'max-w-xl bg-white dark:bg-[#0b0f19] border border-slate-300/90 dark:border-slate-700/90 ring-1 ring-slate-900/10 dark:ring-white/10 shadow-2xl rounded-2xl' }"
    >
      <template #body>
        <div class="space-y-3.5 text-xs">
          <div
            v-if="createModalFeedback"
            role="alert"
            class="p-2.5 rounded-lg bg-red-50/90 dark:bg-red-950/35 border border-red-200 dark:border-red-800/80 text-xs font-medium text-red-900 dark:text-red-100"
          >
            {{ createModalFeedback.message }}
          </div>

          <!-- Live Digitized Stats -->
          <div class="p-2.5 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 border border-amber-500/30 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <UIcon
                :name="createForm.sumber_data?.includes('Otomatis') ? 'i-lucide-waypoints' : 'i-lucide-check-circle'"
                class="size-4 text-amber-600 dark:text-amber-400 shrink-0"
              />
              <span class="font-medium text-gray-800 dark:text-gray-200 truncate">
                {{ createForm.sumber_data?.includes('Otomatis') ? 'Geometri Otomatis Siap' : 'Garis LineString Selesai' }}
              </span>
            </div>
            <UBadge
              :label="`Panjang: ${(createForm.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 4 })} m`"
              color="warning"
              variant="subtle"
              size="xs"
              class="tabular-nums shrink-0"
            />
          </div>

          <!-- Status Referensi Jalan Poros Desa -->
          <div
            v-if="createForm.parent_id && activeDigitasiRef"
            class="p-2.5 rounded-lg bg-sky-50/70 dark:bg-sky-950/25 border border-sky-200 dark:border-sky-800/80 space-y-1"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="inline-flex items-center gap-1.5 font-semibold text-sky-800 dark:text-sky-200">
                <UIcon name="i-lucide-route" class="size-3.5 shrink-0" />
                <span>Mengacu Jalan Poros Desa</span>
              </span>
              <span class="text-[11px] font-mono tabular-nums text-sky-700 dark:text-sky-300">
                Ruas {{ Number(activeDigitasiRef.data.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m
              </span>
            </div>
            <p class="text-[11px] text-sky-700 dark:text-sky-300">
              {{ formatRoadOptionLabel(activeDigitasiRef.data) }}
              <span v-if="isCreateRegionLockedByParent"> (Wilayah disinkronkan otomatis dengan ruas referensi)</span>
            </p>
          </div>
          <div
            v-else
            class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400"
          >
            <UIcon name="i-lucide-git-commit-horizontal" class="size-3.5 shrink-0" />
            <span>Tanpa referensi jalan poros desa (Segmen Mandiri). Dapat ditautkan kapan saja melalui menu Edit.</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField required size="sm">
              <template #label>
                <div class="flex items-center justify-between gap-2 w-full">
                  <span>Nama Objek / Ruas</span>
                  <button
                    v-if="activeDigitasiRef?.data?.nama_ruas && createForm.namobj !== activeDigitasiRef.data.nama_ruas"
                    type="button"
                    class="text-[10px] font-semibold text-blue-700 dark:text-blue-300 hover:underline cursor-pointer"
                    @click="createForm.namobj = activeDigitasiRef.data.nama_ruas || ''"
                  >
                    Pakai Nama Poros
                  </button>
                </div>
              </template>
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
            :disabled="isCreateRegionLockedByParent"
            size="sm"
            @update:model-kecamatan="(val) => createForm.id_kecamatan = val"
            @update:model-desa="(val) => createForm.id_desa = val"
          />

          <!-- Kaitan Plotting Anggaran -->
          <UFormField
            label="Dasar Plotting Anggaran / Pagu"
            :hint="createForm.is_non_pagu
              ? 'Mode Inventarisasi Eksisting Non-Pagu (plotting_id = null).'
              : isCreateLockedByPlotting
                ? 'Dikunci otomatis dari Pagu Resmi yang dipilih sebelum digitasi.'
                : undefined"
            size="sm"
          >
            <USelectMenu
              v-model="createForm.plotting_id"
              :items="[
                { label: 'Tanpa Kaitan Plotting (Non-Pagu)', value: null },
                ...plottingList.map(p => ({
                  label: `${p.nomor_sk ? `${p.nomor_sk} · ` : ''}${p.nama_kegiatan} (${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(p.target_pagu_anggaran)})`,
                  value: p.id,
                }))
              ]"
              value-key="value"
              label-key="label"
              :disabled="createForm.is_non_pagu || isCreateLockedByPlotting"
              placeholder="Pilih alokasi plotting..."
              size="sm"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <UFormField
              label="Panjang (m)"
              :error="createNumericErrors.panjang || undefined"
              size="sm"
            >
              <NumericTextInput
                ref="createPanjangRef"
                v-model="createForm.panjang"
                field-label="Panjang"
                :allow-decimal="true"
                :min="0"
                placeholder="Contoh: 120.5"
                size="sm"
                @update:error="createNumericErrors.panjang = $event"
              />
            </UFormField>

            <UFormField
              label="Lebar (m)"
              :error="createNumericErrors.lebar || undefined"
              size="sm"
            >
              <NumericTextInput
                ref="createLebarRef"
                v-model="createForm.lebar"
                field-label="Lebar"
                :allow-decimal="true"
                :min="0"
                placeholder="Contoh: 3.5"
                size="sm"
                @update:error="createNumericErrors.lebar = $event"
              />
            </UFormField>

            <UFormField label="Kondisi Fisik" size="sm">
              <USelectMenu
                v-model="createForm.kondisi"
                :items="KONDISI_OPTIONS"
                value-key="value"
                label-key="label"
                :search-input="false"
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <UFormField label="Jenis Perkerasan" size="sm">
              <USelectMenu
                v-model="createForm.jenis_perkerasan"
                :items="PERKERASAN_OPTIONS"
                value-key="value"
                label-key="label"
                :search-input="false"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Status Jalan" size="sm">
              <USelectMenu
                v-model="createForm.status_jalan"
                :items="STATUS_JALAN_OPTIONS"
                value-key="value"
                label-key="label"
                :search-input="false"
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <UFormField
              label="Sumber Dana"
              :hint="isCreateLockedByPlotting ? 'Mengikuti Pagu Resmi' : undefined"
              size="sm"
            >
              <USelectMenu
                v-model="createForm.sumber_dana"
                :items="createSumberDanaItems"
                value-key="value"
                label-key="label"
                :disabled="isCreateLockedByPlotting"
                placeholder="Pilih sumber dana..."
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Status Aset" size="sm">
              <USelectMenu
                v-model="createForm.status_aset"
                :items="STATUS_ASET_OPTIONS"
                value-key="value"
                label-key="label"
                :search-input="false"
                size="sm"
                class="w-full"
              />
            </UFormField>

            <UFormField
              label="Tahun Anggaran"
              :error="createNumericErrors.tahun_pembangunan || undefined"
              :hint="isCreateLockedByPlotting ? 'Mengikuti Pagu Resmi' : undefined"
              size="sm"
            >
              <NumericTextInput
                ref="createTahunRef"
                v-model="createForm.tahun_pembangunan"
                field-label="Tahun anggaran"
                :allow-decimal="false"
                :min="1900"
                :max="maxBuildYear"
                :max-digits="4"
                :disabled="isCreateLockedByPlotting"
                placeholder="Contoh: 2026"
                size="sm"
                @update:error="createNumericErrors.tahun_pembangunan = $event"
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
      :dismissible="!isDeleting"
      title="Hapus Segmen Fisik"
      description="Konfirmasi penghapusan segmen infrastruktur."
      :ui="{ content: 'max-w-sm bg-white dark:bg-[#0b0f19] border border-slate-300/90 dark:border-slate-700/90 ring-1 ring-slate-900/10 dark:ring-white/10 shadow-2xl rounded-2xl' }"
    >
      <template #body>
        <div class="space-y-3 text-xs">
          <div
            v-if="deleteModalError"
            role="alert"
            class="p-2.5 rounded-lg bg-red-50/90 dark:bg-red-950/35 border border-red-200 dark:border-red-800/80 font-medium text-red-900 dark:text-red-100"
          >
            {{ deleteModalError }}
          </div>
          <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
            Apakah Anda yakin ingin menghapus segmen
            <span class="font-semibold text-gray-900 dark:text-white">
              {{ segmenToDelete?.namobj || 'ini' }}
            </span>?
            Data koordinat spasial PostGIS dan atribut terkait akan dihapus.
          </p>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            :disabled="isDeleting"
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

    <!-- ═══ MODAL: Verifikasi Segmen (Kecamatan -> Bappeda / Final Bappeda) ═════ -->
    <UModal
      v-model:open="isVerifyModalOpen"
      :dismissible="!verifyingSegmen"
      :title="verifyLevel === 'kecamatan' ? 'Verifikasi Tingkat Kecamatan' : 'Verifikasi Final Bappeda'"
      description="Setujui untuk meneruskan ke tingkat berikutnya atau kembalikan dengan catatan perbaikan."
      :ui="{ content: 'max-w-md bg-white dark:bg-[#0b0f19] border border-slate-300/90 dark:border-slate-700/90 ring-1 ring-slate-900/10 dark:ring-white/10 shadow-2xl rounded-2xl' }"
    >
      <template #body>
        <div class="space-y-3 text-xs">
          <div
            v-if="verifyModalError"
            role="alert"
            class="p-2.5 rounded-lg bg-red-50/90 dark:bg-red-950/35 border border-red-200 dark:border-red-800/80 font-medium text-red-900 dark:text-red-100"
          >
            {{ verifyModalError }}
          </div>

          <div v-if="verifyTargetSegmen" class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-1">
            <p class="font-semibold text-gray-900 dark:text-white">
              {{ verifyTargetSegmen.namobj || 'Segmen Infrastruktur' }}
            </p>
            <p class="text-[11px] text-gray-600 dark:text-gray-400">
              {{ formatWilayah(verifyTargetSegmen.desa) }}, Kec. {{ formatWilayah(verifyTargetSegmen.kecamatan) }}
              <span v-if="verifyTargetSegmen.panjang">
                · {{ Number(verifyTargetSegmen.panjang).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m
              </span>
            </p>
          </div>

          <div class="space-y-1.5">
            <span class="block font-medium text-gray-700 dark:text-gray-300">Keputusan Verifikasi</span>
            <div role="radiogroup" aria-label="Keputusan verifikasi segmen" class="grid grid-cols-2 gap-2">
              <button
                type="button"
                role="radio"
                :aria-checked="verifyAction === 'approve'"
                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border text-xs font-semibold cursor-pointer transition-colors"
                :class="
                  verifyAction === 'approve'
                    ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-200 border-emerald-400 dark:border-emerald-700'
                    : 'bg-white dark:bg-[#0b0f19] border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300'
                "
                @click="verifyAction = 'approve'"
              >
                <UIcon name="i-lucide-check-circle-2" class="size-3.5 shrink-0" aria-hidden="true" />
                <span>{{ verifyLevel === 'kecamatan' ? 'Teruskan ke Bappeda' : 'Sahkan Final' }}</span>
              </button>

              <button
                type="button"
                role="radio"
                :aria-checked="verifyAction === 'reject'"
                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border text-xs font-semibold cursor-pointer transition-colors"
                :class="
                  verifyAction === 'reject'
                    ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-900 dark:text-rose-200 border-rose-400 dark:border-rose-700'
                    : 'bg-white dark:bg-[#0b0f19] border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300'
                "
                @click="verifyAction = 'reject'"
              >
                <UIcon name="i-lucide-rotate-ccw" class="size-3.5 shrink-0" aria-hidden="true" />
                <span>Tolak / Revisi</span>
              </button>
            </div>
          </div>

          <UFormField
            label="Catatan Verifikasi"
            :required="verifyAction === 'reject'"
            size="sm"
          >
            <UTextarea
              v-model="verifyCatatan"
              :rows="3"
              :placeholder="verifyAction === 'reject' ? 'Tuliskan alasan perbaikan yang diperlukan...' : 'Catatan verifikasi (opsional)...'"
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
            :disabled="verifyingSegmen"
            @click="isVerifyModalOpen = false"
          />
          <UButton
            :label="verifyAction === 'approve' ? (verifyLevel === 'kecamatan' ? 'Teruskan ke Bappeda' : 'Sahkan Final') : 'Kembalikan untuk Revisi'"
            :color="verifyAction === 'approve' ? 'success' : 'error'"
            variant="solid"
            class="cursor-pointer"
            :loading="verifyingSegmen"
            @click="handleConfirmVerify"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: Konfirmasi Split Segmen ══════════════════════════════════════ -->
    <DialogSplitSegmen
      v-model:open="isSplitDialogOpen"
      :segmen="splitTargetSegmen"
      :split-point="splitPointCoordinate"
      :loading="isSplitting"
      @confirm="handleConfirmSplit"
      @cancel="handleCancelSplit"
    />

    <!-- ═══ MODAL: Buat Pagu / Plotting Anggaran Cepat ═══════════════════════════ -->
    <PlottingFormModal
      v-model:open="isQuickPlottingModalOpen"
      :default-kecamatan="isKecamatanRestricted ? userKecamatanId : filterKecamatan"
      :default-desa="isDesaRestricted ? userDesaId : filterDesa"
      @created="handleQuickPlottingCreated"
    />
  </div>
</template>

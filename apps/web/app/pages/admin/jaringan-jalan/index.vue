<script setup lang="ts">
import type { SplitterItem } from '@nuxt/ui';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import MobileBottomSheet from '~/components/dataset/infrastruktur-segmen/MobileBottomSheet.vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import JaringanJalanBottomPanel from '~/components/jaringan-jalan/JaringanJalanBottomPanel.vue';
import JaringanJalanFilterContent from '~/components/jaringan-jalan/JaringanJalanFilterContent.vue';
import JaringanJalanFilterModal from '~/components/jaringan-jalan/JaringanJalanFilterModal.vue';
import JaringanJalanInspectorCard from '~/components/jaringan-jalan/JaringanJalanInspectorCard.vue';
import JaringanJalanLeftPanel from '~/components/jaringan-jalan/JaringanJalanLeftPanel.vue';
import JaringanJalanMaplibre from '~/components/jaringan-jalan/JaringanJalanMaplibre.vue';
import JaringanJalanRightPanel from '~/components/jaringan-jalan/JaringanJalanRightPanel.vue';
import { useJaringanJalanApi } from '~/composables/useJaringanJalanApi';
import { usePermission } from '~/composables/usePermission';
import { useAuthStore } from '~/stores/auth';
import {
  computeGeometryBbox,
  formatAngka,
  formatPanjangKm,
} from '~/utils/jaringan-jalan-format';
import type {
  CakupanDesaItem,
  CakupanDesaSummary,
  HierarkiJalan,
  JalanKabupatenItem,
  JaringanConsoleEntry,
  JaringanJalanRuasFeature,
  JaringanLayerKey,
  JaringanMapLayersState,
  JaringanMapOpacityState,
  JaringanMapSelection,
  JaringanMobileDockTab,
  JaringanWorkspaceTab,
  RtrwAnalisaDetail,
  RtrwAnalisaItem,
  RtrwAnalisaMapIndex,
  RtrwAnalisaSummary,
  RtrwColorMode,
  StatusAnalisaRtrw,
} from '~/types/jaringan-jalan';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: ['jaringan-jalan-view', 'jaringan-jalan-manage'],
  fullBleed: true,
});

useHead({
  title: 'Analisa Spasial Jaringan Jalan | WebGIS Bojonegoro',
});

const toast = useToast();
const auth = useAuthStore();
const { can } = usePermission();
const jaringanApi = useJaringanJalanApi();

const canManage = computed(() =>
  auth.hasRole('admin') || auth.hasRole('verifierBappeda') || can('jaringan-jalan-manage'),
);

// ─── Responsive Breakpoint ────────────────────────────────────────────────────
const windowWidth = ref(import.meta.client ? window.innerWidth : 1280);
const isDesktop = computed(() => windowWidth.value >= 1024);

function handleWindowResize() {
  windowWidth.value = window.innerWidth;
}

// ─── Splitter & Workbench Panel Configuration ─────────────────────────────────
const LEFT_PANEL_WIDTH = 300;
const RIGHT_PANEL_WIDTH = 360;
const BOTTOM_PANEL_HEIGHT = 280;

const horizontalSplitterItems = ref<SplitterItem[]>([
  {
    id: 'left-panel',
    slot: 'left',
    sizeUnit: 'px',
    defaultSize: LEFT_PANEL_WIDTH,
    minSize: 250,
    maxSize: 420,
    collapsible: true,
    collapsedSize: 42,
    class: 'h-full',
  },
  {
    id: 'map-canvas',
    slot: 'map',
    minSize: 30,
    class: 'flex flex-1 w-full h-full relative overflow-hidden',
  },
  {
    id: 'right-panel',
    slot: 'right',
    sizeUnit: 'px',
    defaultSize: 42,
    minSize: 280,
    maxSize: 480,
    collapsible: true,
    collapsedSize: 42,
    class: 'h-full',
  },
]);

const verticalSplitterItems = ref<SplitterItem[]>([
  {
    id: 'main-workspace',
    slot: 'workspace',
    minSize: 32,
    class: 'flex flex-1 w-full h-full overflow-hidden',
  },
  {
    id: 'bottom-panel',
    slot: 'bottom',
    sizeUnit: 'px',
    defaultSize: 40,
    minSize: 180,
    maxSize: 520,
    collapsible: true,
    collapsedSize: 40,
    class: 'flex flex-col h-full',
  },
]);

interface SplitterSlotActions {
  collapsed: boolean;
  collapse: () => void;
  expand: () => void;
  resize: (s: number) => void;
}

const isDraggingHorizontal = ref(false);
const isDraggingVertical = ref(false);
const leftPanelCollapsed = ref(false);
const rightPanelCollapsed = ref(true);
const bottomPanelCollapsed = ref(true);
let leftSlotCtrl: SplitterSlotActions | null = null;
let rightSlotCtrl: SplitterSlotActions | null = null;
let bottomSlotCtrl: SplitterSlotActions | null = null;
const leftPanelContainerEl = ref<HTMLElement | null>(null);
const leftPanelActualWidth = ref(LEFT_PANEL_WIDTH);
let leftWidthObserver: ResizeObserver | null = null;

function syncLeftSlot(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void,
) {
  leftSlotCtrl = { collapsed, collapse, expand, resize };
  if (leftPanelCollapsed.value !== collapsed) {
    queueMicrotask(() => {
      leftPanelCollapsed.value = collapsed;
    });
  }
  return '';
}

function syncRightSlot(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void,
) {
  rightSlotCtrl = { collapsed, collapse, expand, resize };
  if (rightPanelCollapsed.value !== collapsed) {
    queueMicrotask(() => {
      rightPanelCollapsed.value = collapsed;
    });
  }
  return '';
}

function syncBottomSlot(
  collapsed: boolean,
  collapse: () => void,
  expand: () => void,
  resize: (size: number) => void,
) {
  bottomSlotCtrl = { collapsed, collapse, expand, resize };
  if (bottomPanelCollapsed.value !== collapsed) {
    queueMicrotask(() => {
      bottomPanelCollapsed.value = collapsed;
    });
  }
  return '';
}

const allPanelsCollapsed = computed(() => {
  return Boolean(
    leftPanelCollapsed.value && rightPanelCollapsed.value && bottomPanelCollapsed.value,
  );
});

function expandRightPanel() {
  if (!isDesktop.value || !rightSlotCtrl) return;
  if (rightSlotCtrl.collapsed) {
    rightSlotCtrl.resize(RIGHT_PANEL_WIDTH);
  }
}

function expandBottomPanel() {
  if (!isDesktop.value || !bottomSlotCtrl) return;
  if (bottomSlotCtrl.collapsed) {
    bottomSlotCtrl.resize(BOTTOM_PANEL_HEIGHT);
  }
}

function scheduleMapResize() {
  nextTick(() => {
    mapRef.value?.resizeMap();
  });
}

function handleSplitterDragging(axis: 'vertical' | 'horizontal', dragging: boolean) {
  if (axis === 'vertical') {
    isDraggingVertical.value = dragging;
  } else {
    isDraggingHorizontal.value = dragging;
  }
  if (!dragging) {
    scheduleMapResize();
  }
}

function toggleAllPanels() {
  if (!isDesktop.value) return;
  if (allPanelsCollapsed.value) {
    leftSlotCtrl?.resize(LEFT_PANEL_WIDTH);
    bottomSlotCtrl?.resize(BOTTOM_PANEL_HEIGHT);
    if (mapSelection.value) {
      rightSlotCtrl?.resize(RIGHT_PANEL_WIDTH);
    }
  } else {
    leftSlotCtrl?.collapse();
    rightSlotCtrl?.collapse();
    bottomSlotCtrl?.collapse();
  }
  scheduleMapResize();
}

// ─── Mobile Sheet & Dock State ────────────────────────────────────────────────
const mobileSheetOpen = ref(false);
const mobileSheetSnap = ref<'peek' | 'full'>('peek');
const mobileActiveDock = ref<JaringanMobileDockTab>('layers');

const mobileSheetTitle = computed(() => {
  switch (mobileActiveDock.value) {
    case 'layers':
      return 'Daftar Layer & Legenda Peta';
    case 'table':
      return 'Tabel Atribut & Cakupan';
    case 'rekap':
      return 'Rekap Analisa & Log';
    case 'detail':
      return 'Inspektor Ruas & Adopsi';
    case 'filter':
      return 'Filter Analisa Spasial';
  }
});

const mobileSheetIcon = computed(() => {
  switch (mobileActiveDock.value) {
    case 'layers':
      return 'i-lucide-layers';
    case 'table':
      return 'i-lucide-table-2';
    case 'rekap':
      return 'i-lucide-bar-chart-3';
    case 'detail':
      return 'i-lucide-info';
    case 'filter':
      return 'i-lucide-sliders-horizontal';
  }
});

const mobileRekapTab = ref<'breakdown' | 'console'>('breakdown');

function openMobileDock(tab: JaringanMobileDockTab, snap: 'peek' | 'full' = 'peek') {
  mobileActiveDock.value = tab;
  mobileSheetSnap.value = snap;
  mobileSheetOpen.value = true;
}

// ─── Workbench & API Data States ──────────────────────────────────────────────
const activeTab = ref<JaringanWorkspaceTab>('analisa');
const perPage = ref(25);
const filterModalOpen = ref(false);

const loadingSummary = ref(false);
const loadingAnalisa = ref(false);
const loadingMapIndex = ref(false);
const loadingDetail = ref(false);
const loadingCakupan = ref(false);
const loadingMaster = ref(false);
const loadingKabupaten = ref(false);
const runningAnalysis = ref(false);
const adoptingRtrw = ref(false);
const savingPoros = ref(false);
const deletingPoros = ref(false);

const summaryData = ref<RtrwAnalisaSummary | null>(null);
const mapIndex = ref<RtrwAnalisaMapIndex | null>(null);

const analisaList = ref<RtrwAnalisaItem[]>([]);
const analisaMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const selectedRtrwIds = ref<number[]>([]);

const cakupanList = ref<CakupanDesaItem[]>([]);
const cakupanMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const cakupanSummary = ref<CakupanDesaSummary>({
  total_desa: 0,
  desa_terdata: 0,
  desa_belum_terdata: 0,
});
const cakupanStatusFilter = ref<'belum_terdata' | 'sudah_terdata' | 'semua'>('belum_terdata');

const masterList = ref<JaringanJalanRuasFeature[]>([]);
const masterMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });

const kabupatenList = ref<JalanKabupatenItem[]>([]);
const kabupatenMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });

const inspectorMode = ref<'rtrw' | 'poros' | 'kabupaten' | null>(null);
const selectedRtrwDetail = ref<RtrwAnalisaDetail | null>(null);
const selectedPoros = ref<JaringanJalanRuasFeature | null>(null);
const selectedKabupaten = ref<JalanKabupatenItem | null>(null);
const mapSelection = ref<JaringanMapSelection | null>(null);

const mapLayers = ref<JaringanMapLayersState>({
  rtrw: true,
  poros: true,
  kabupaten: true,
  kecamatan: true,
  desa: false,
});
const layersOpacity = ref<JaringanMapOpacityState>({
  rtrw: 0.92,
  poros: 0.88,
  kabupaten: 0.95,
  kecamatan: 0.72,
  desa: 0.48,
});
const rtrwColorMode = ref<RtrwColorMode>('status');
const mapRef = ref<InstanceType<typeof JaringanJalanMaplibre> | null>(null);

// ─── Console Telemetry Logs ───────────────────────────────────────────────────
const consoleLogs = ref<JaringanConsoleEntry[]>([]);
let lastMartinStateOk: boolean | null = null;

function pushConsoleLog(level: JaringanConsoleEntry['level'], message: string) {
  const now = new Date().toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
  consoleLogs.value.unshift({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    timestamp: now,
    level,
    message,
  });
  if (consoleLogs.value.length > 80) {
    consoleLogs.value.pop();
  }
}

function handleMartinStatus(payload: { ok: boolean; errorCount: number; message?: string }) {
  if (lastMartinStateOk === payload.ok) return;
  lastMartinStateOk = payload.ok;
  if (payload.ok) {
    pushConsoleLog('success', 'Vector tile Martin aktif untuk seluruh layer jaringan jalan.');
  } else {
    pushConsoleLog('warn', payload.message || 'Sebagian tile vektor Martin tidak merespons.');
  }
}

// ─── Confirm Delete Modal ─────────────────────────────────────────────────────
const confirmDeleteTarget = ref<JaringanJalanRuasFeature | null>(null);
const deleteCancelBtnRef = ref<HTMLButtonElement | null>(null);
let lastFocusedBeforeModal: HTMLElement | null = null;

// ─── Filter State ─────────────────────────────────────────────────────────────
const filters = reactive({
  status_analisa: '' as '' | StatusAnalisaRtrw,
  orde02: '',
  only_unadopted: false,
  only_missing_desa: false,
  sort_by: 'uncovered_desc',
  hierarki_jalan: '' as '' | HierarkiJalan,
  klas_ruas: '',
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  search: '',
  analisaPage: 1,
  cakupanPage: 1,
  masterPage: 1,
  kabupatenPage: 1,
});

const isKecamatanLocked = computed(() => {
  if (auth.hasRole('admin') || auth.hasRole('verifierBappeda')) return false;
  return Boolean(auth.user?.id_kecamatan);
});

const isDesaLocked = computed(() => {
  if (auth.hasRole('admin') || auth.hasRole('verifierBappeda')) return false;
  return Boolean(auth.user?.id_desa);
});

const activeFilterCount = computed(() => {
  let c = 0;
  if (filters.status_analisa) c++;
  if (filters.orde02) c++;
  if (filters.only_unadopted) c++;
  if (filters.only_missing_desa) c++;
  if (filters.hierarki_jalan) c++;
  if (filters.klas_ruas.trim()) c++;
  if (!isKecamatanLocked.value && filters.id_kecamatan !== null) c++;
  if (!isDesaLocked.value && filters.id_desa !== null) c++;
  if (filters.search.trim()) c++;
  return c;
});

const hasActiveFilters = computed(() => activeFilterCount.value > 0);

function buildCommonQuery(includeStatus = false) {
  const query: Record<string, any> = {};
  if (filters.id_kecamatan) query.id_kecamatan = filters.id_kecamatan;
  if (filters.id_desa) query.id_desa = filters.id_desa;
  if (filters.orde02) query.orde02 = filters.orde02;
  if (filters.only_unadopted) query.only_unadopted = 1;
  if (filters.only_missing_desa) query.only_missing_desa = 1;
  if (filters.search.trim()) query.search = filters.search.trim();
  if (includeStatus && filters.status_analisa) query.status_analisa = filters.status_analisa;
  return query;
}

// ─── API Fetchers ─────────────────────────────────────────────────────────────
async function loadSummary() {
  loadingSummary.value = true;
  try {
    const res = await jaringanApi.fetchAnalisaSummary(buildCommonQuery(false));
    summaryData.value = res;
    if (res.desa_coverage) {
      cakupanSummary.value = res.desa_coverage;
    }
    if (res.dataset_overview) {
      if (dirtyTabs.master) masterMeta.value.total = res.dataset_overview.jalan_poros_ruas ?? masterMeta.value.total;
      if (dirtyTabs.kabupaten) kabupatenMeta.value.total = res.dataset_overview.jalan_kabupaten_ruas ?? kabupatenMeta.value.total;
    }
  } catch (err: any) {
    pushConsoleLog('error', err?.data?.message || 'Gagal memuat ringkasan analisa spasial.');
  } finally {
    loadingSummary.value = false;
  }
}

async function loadMapIndex() {
  loadingMapIndex.value = true;
  try {
    const res = await jaringanApi.fetchAnalisaMapIndex(buildCommonQuery(true));
    mapIndex.value = res;
  } catch {
    // Biarkan vector tile Martin tergambar meskipun index filter gagal
  } finally {
    loadingMapIndex.value = false;
  }
}

async function loadAnalisaList() {
  loadingAnalisa.value = true;
  try {
    const res = await jaringanApi.fetchAnalisaList({
      ...buildCommonQuery(true),
      sort_by: filters.sort_by,
      page: filters.analisaPage,
      per_page: perPage.value,
    });
    analisaList.value = res.data || [];
    selectedRtrwIds.value = [];
    if (res.meta) analisaMeta.value = res.meta;
  } catch (err: any) {
    pushConsoleLog('error', err?.data?.message || 'Gagal memuat daftar analisa RTRW.');
  } finally {
    loadingAnalisa.value = false;
  }
}

async function loadCakupanDesa() {
  loadingCakupan.value = true;
  try {
    const query: Record<string, any> = {
      status_cakupan: cakupanStatusFilter.value,
      page: filters.cakupanPage,
      per_page: perPage.value,
    };
    if (filters.id_kecamatan) query.id_kecamatan = filters.id_kecamatan;
    if (filters.id_desa) query.id_desa = filters.id_desa;
    if (filters.search.trim()) query.search = filters.search.trim();

    const res = await jaringanApi.fetchCakupanDesa(query);
    cakupanList.value = res.data || [];
    if (res.summary) cakupanSummary.value = res.summary;
    if (res.meta) cakupanMeta.value = res.meta;
  } catch (err: any) {
    pushConsoleLog('error', err?.data?.message || 'Gagal memuat daftar cakupan desa.');
  } finally {
    loadingCakupan.value = false;
  }
}

async function loadMasterList() {
  loadingMaster.value = true;
  try {
    const query: Record<string, any> = {
      page: filters.masterPage,
      per_page: perPage.value,
    };
    if (filters.hierarki_jalan) query.hierarki_jalan = filters.hierarki_jalan;
    if (filters.id_kecamatan) query.id_kecamatan = filters.id_kecamatan;
    if (filters.id_desa) query.id_desa = filters.id_desa;
    if (filters.search.trim()) query.search = filters.search.trim();

    const res = await jaringanApi.fetchRuasList(query);
    masterList.value = res.data || [];
    if (res.meta) masterMeta.value = res.meta;
  } catch (err: any) {
    pushConsoleLog('error', err?.data?.message || 'Gagal memuat daftar Master Jalan Poros Desa.');
  } finally {
    loadingMaster.value = false;
  }
}

async function loadKabupatenList() {
  loadingKabupaten.value = true;
  try {
    const query: Record<string, any> = {
      page: filters.kabupatenPage,
      per_page: perPage.value,
    };
    if (filters.id_kecamatan) query.id_kecamatan = filters.id_kecamatan;
    if (filters.id_desa) query.id_desa = filters.id_desa;
    if (filters.klas_ruas.trim()) query.klas_ruas = filters.klas_ruas.trim();
    if (filters.search.trim()) query.search = filters.search.trim();

    const res = await jaringanApi.fetchKabupatenList(query);
    kabupatenList.value = res.data || [];
    if (res.meta) kabupatenMeta.value = res.meta;
  } catch (err: any) {
    pushConsoleLog('error', err?.data?.message || 'Gagal memuat daftar Jalan Kabupaten.');
  } finally {
    loadingKabupaten.value = false;
  }
}

const dirtyTabs = reactive({
  analisa: true,
  cakupan: true,
  master: true,
  kabupaten: true,
});

const rtrwDetailCache = new Map<number, RtrwAnalisaDetail>();
const porosDetailCache = new Map<string, JaringanJalanRuasFeature>();
const kabDetailCache = new Map<number, JalanKabupatenItem>();
let inspectSeq = 0;

function clearDetailCaches() {
  rtrwDetailCache.clear();
  porosDetailCache.clear();
  kabDetailCache.clear();
}

async function ensureTabLoaded(tab: JaringanWorkspaceTab) {
  if (tab === 'analisa' && dirtyTabs.analisa) {
    dirtyTabs.analisa = false;
    await loadAnalisaList();
  } else if (tab === 'cakupan' && dirtyTabs.cakupan) {
    dirtyTabs.cakupan = false;
    await loadCakupanDesa();
  } else if (tab === 'master' && dirtyTabs.master) {
    dirtyTabs.master = false;
    await loadMasterList();
  } else if (tab === 'kabupaten' && dirtyTabs.kabupaten) {
    dirtyTabs.kabupaten = false;
    await loadKabupatenList();
  }
}

async function refreshAllData(invalidateCache = false) {
  if (invalidateCache) {
    clearDetailCaches();
  }
  dirtyTabs.analisa = true;
  dirtyTabs.cakupan = true;
  dirtyTabs.master = true;
  dirtyTabs.kabupaten = true;

  const activeDataTab: JaringanWorkspaceTab =
    activeTab.value === 'cakupan' || activeTab.value === 'master' || activeTab.value === 'kabupaten'
      ? activeTab.value
      : 'analisa';

  await Promise.all([
    loadSummary(),
    loadMapIndex(),
    ensureTabLoaded(activeDataTab),
  ]);
}

// ─── Map & Inspector Actions ──────────────────────────────────────────────────
async function inspectRtrwSegment(
  rtrwId: number,
  focusUi = true,
  hintBbox?: [number, number, number, number] | null,
) {
  const seq = ++inspectSeq;
  inspectorMode.value = 'rtrw';
  selectedPoros.value = null;
  selectedKabupaten.value = null;

  if (focusUi && isDesktop.value) {
    expandRightPanel();
  }
  if (hintBbox) {
    mapRef.value?.fitToBbox(hintBbox, 16.5);
  }

  const cached = rtrwDetailCache.get(rtrwId);
  if (cached) {
    selectedRtrwDetail.value = cached;
    loadingDetail.value = false;
    mapSelection.value = {
      kind: 'rtrw',
      id: cached.segment.id,
      title: cached.segment.display_name,
      subtitle: `${cached.segment.orde02 || 'RTRW'} · ${cached.segment.nama_desa ? `Desa ${cached.segment.nama_desa}` : cached.segment.nama_kecamatan || 'Bojonegoro'}`,
      badge: `${formatAngka(cached.segment.combined_overlap_pct, 0)}% Tercover`,
      bbox: cached.segment.bbox,
      geojson: cached.segment.geojson,
      matchedPorosGeojson: cached.matched_poros.map((m) => m.geojson).filter(Boolean),
      matchedKabupatenGeojson: cached.matched_kabupaten.map((m) => m.geojson).filter(Boolean),
    };
    return;
  }

  loadingDetail.value = true;
  try {
    const res = await jaringanApi.fetchAnalisaDetail(rtrwId);
    if (seq !== inspectSeq) return;
    rtrwDetailCache.set(rtrwId, res);
    selectedRtrwDetail.value = res;
    mapSelection.value = {
      kind: 'rtrw',
      id: res.segment.id,
      title: res.segment.display_name,
      subtitle: `${res.segment.orde02 || 'RTRW'} · ${res.segment.nama_desa ? `Desa ${res.segment.nama_desa}` : res.segment.nama_kecamatan || 'Bojonegoro'}`,
      badge: `${formatAngka(res.segment.combined_overlap_pct, 0)}% Tercover`,
      bbox: res.segment.bbox,
      geojson: res.segment.geojson,
      matchedPorosGeojson: res.matched_poros.map((m) => m.geojson).filter(Boolean),
      matchedKabupatenGeojson: res.matched_kabupaten.map((m) => m.geojson).filter(Boolean),
    };
    pushConsoleLog('info', `Inspeksi segmen RTRW #${res.segment.id}: ${res.segment.display_name}.`);
  } catch (err: any) {
    if (seq !== inspectSeq) return;
    toast.add({
      title: 'Gagal Memuat Detail Segmen',
      description: err?.data?.message || 'Tidak dapat memuat detail irisan spasial segmen RTRW.',
      color: 'error',
    });
  } finally {
    if (seq === inspectSeq) {
      loadingDetail.value = false;
    }
  }
}

async function inspectPorosFeature(featOrId: JaringanJalanRuasFeature | string, focusUi = true) {
  const seq = ++inspectSeq;
  inspectorMode.value = 'poros';
  selectedRtrwDetail.value = null;
  selectedKabupaten.value = null;

  if (focusUi && isDesktop.value) {
    expandRightPanel();
  }

  try {
    let feat: JaringanJalanRuasFeature;
    if (typeof featOrId === 'string') {
      const cached = porosDetailCache.get(featOrId);
      if (cached) {
        feat = cached;
      } else {
        const res = await jaringanApi.fetchRuasDetail(featOrId);
        if (seq !== inspectSeq) return;
        feat = res.data;
        porosDetailCache.set(featOrId, feat);
      }
    } else {
      feat = featOrId;
      porosDetailCache.set(feat.id, feat);
    }
    selectedPoros.value = feat;
    mapSelection.value = {
      kind: 'poros',
      id: feat.id,
      title: feat.properties.nama_ruas,
      subtitle: `${feat.properties.nama_desa ? `Desa ${feat.properties.nama_desa}` : '-'} · ${formatAngka(feat.properties.panjang_m, 0)} m`,
      badge: 'Master Poros',
      bbox: computeGeometryBbox(feat.geometry),
      geojson: feat.geometry,
    };
    pushConsoleLog('info', `Inspeksi Master Poros: ${feat.properties.nama_ruas}.`);
  } catch (err: any) {
    if (seq !== inspectSeq) return;
    toast.add({
      title: 'Gagal Memuat Detail Ruas Poros',
      description: err?.data?.message || 'Detail ruas jalan poros tidak tersedia.',
      color: 'error',
    });
  }
}

async function inspectKabupatenFeature(itemOrId: JalanKabupatenItem | number, focusUi = true) {
  const seq = ++inspectSeq;
  inspectorMode.value = 'kabupaten';
  selectedRtrwDetail.value = null;
  selectedPoros.value = null;

  if (focusUi && isDesktop.value) {
    expandRightPanel();
  }

  try {
    let item: JalanKabupatenItem;
    if (typeof itemOrId === 'number') {
      const cached = kabDetailCache.get(itemOrId);
      if (cached) {
        item = cached;
      } else {
        const res = await jaringanApi.fetchKabupatenDetail(itemOrId);
        if (seq !== inspectSeq) return;
        item = res.data;
        kabDetailCache.set(itemOrId, item);
      }
    } else {
      item = itemOrId;
      kabDetailCache.set(item.id, item);
    }
    selectedKabupaten.value = item;
    mapSelection.value = {
      kind: 'kabupaten',
      id: item.id,
      title: item.nama_ruas,
      subtitle: `No. Ruas ${item.no_ruas || '-'} · ${formatAngka(item.panjang_m, 0)} m`,
      badge: item.klas_ruas || 'Kabupaten',
      bbox: item.bbox || computeGeometryBbox(item.geometry),
      geojson: item.geometry,
    };
    pushConsoleLog('info', `Inspeksi Jalan Kabupaten: ${item.nama_ruas}.`);
  } catch (err: any) {
    if (seq !== inspectSeq) return;
    toast.add({
      title: 'Gagal Memuat Detail Jalan Kabupaten',
      description: err?.data?.message || 'Detail ruas kabupaten tidak tersedia.',
      color: 'error',
    });
  }
}

function clearSelection() {
  inspectorMode.value = null;
  selectedRtrwDetail.value = null;
  selectedPoros.value = null;
  selectedKabupaten.value = null;
  mapSelection.value = null;
  if (isDesktop.value && rightSlotCtrl && !rightSlotCtrl.collapsed) {
    rightSlotCtrl.collapse();
  }
}

function zoomToBbox(bbox: [number, number, number, number] | null | undefined) {
  if (!bbox) return;
  mapRef.value?.fitToBbox(bbox, 16.5);
  if (!isDesktop.value) {
    mobileSheetOpen.value = false;
  }
}

function handleZoomToLayer(key: JaringanLayerKey) {
  if (key === 'rtrw' && mapIndex.value?.bounds) {
    mapRef.value?.fitToBbox(mapIndex.value.bounds, 15.5);
  } else {
    mapRef.value?.fitToBojonegoro();
  }
  if (!isDesktop.value) {
    mobileSheetOpen.value = false;
  }
}

// ─── Spatial Actions & CRUD Handlers ──────────────────────────────────────────
async function handleRunFullAnalysis() {
  runningAnalysis.value = true;
  pushConsoleLog('info', 'Menjalankan perhitungan ulang irisan spasial di PostGIS...');
  try {
    const res = await jaringanApi.runAnalisaSpasial({
      id_kecamatan: filters.id_kecamatan,
    });
    const proc = res.data?.processed_count ?? 0;
    const dur = res.data?.duration_seconds ?? 0;
    toast.add({
      title: 'Analisa Spasial Selesai',
      description: res.message || `Diproses ${proc} segmen dalam ${dur} detik.`,
      color: 'success',
    });
    pushConsoleLog('success', `Analisa spasial selesai: ${proc} segmen diproses (${dur} dtk).`);
    await refreshAllData(true);
  } catch (err: any) {
    const msg = err?.data?.message || 'Gagal menghitung ulang analisa spasial.';
    toast.add({ title: 'Analisa Spasial Gagal', description: msg, color: 'error' });
    pushConsoleLog('error', msg);
  } finally {
    runningAnalysis.value = false;
  }
}

async function handleAdoptFromInspector(payload: {
  id: number;
  nama_ruas: string;
  hierarki_jalan: HierarkiJalan;
  tahun_survey: number;
  skip_spatial_duplicate: boolean;
}) {
  adoptingRtrw.value = true;
  try {
    const res = await jaringanApi.adopsiRtrw({
      rtrw_ids: [payload.id],
      nama_ruas: payload.nama_ruas.trim() || undefined,
      hierarki_jalan: payload.hierarki_jalan,
      tahun_survey: payload.tahun_survey,
      skip_spatial_duplicate: payload.skip_spatial_duplicate,
    });
    toast.add({
      title: 'Segmen RTRW Diadopsi',
      description: res.message || 'Segmen berhasil ditambahkan ke Master Jalan Poros Desa.',
      color: 'success',
    });
    pushConsoleLog('success', `Adopsi segmen RTRW #${payload.id} berhasil ke Master Poros.`);
    clearDetailCaches();
    await Promise.all([refreshAllData(true), inspectRtrwSegment(payload.id, false)]);
  } catch (err: any) {
    const msg = err?.data?.message || 'Tidak dapat mengadopsi segmen RTRW.';
    toast.add({ title: 'Gagal Adopsi Segmen', description: msg, color: 'error' });
    pushConsoleLog('error', msg);
  } finally {
    adoptingRtrw.value = false;
  }
}

async function handleBulkAdoptSelected() {
  if (selectedRtrwIds.value.length === 0) return;
  adoptingRtrw.value = true;
  try {
    const res = await jaringanApi.adopsiRtrw({
      rtrw_ids: selectedRtrwIds.value,
      tahun_survey: new Date().getFullYear(),
      skip_spatial_duplicate: true,
    });
    toast.add({
      title: 'Adopsi Terpilih Selesai',
      description: res.message || 'Segmen terpilih berhasil masuk ke Master Jalan Poros Desa.',
      color: 'success',
    });
    pushConsoleLog('success', `Adopsi massal selesai (${selectedRtrwIds.value.length} segmen diproses).`);
    selectedRtrwIds.value = [];
    await refreshAllData(true);
  } catch (err: any) {
    const msg = err?.data?.message || 'Gagal menjalankan adopsi massal segmen terpilih.';
    toast.add({ title: 'Gagal Adopsi Massal', description: msg, color: 'error' });
    pushConsoleLog('error', msg);
  } finally {
    adoptingRtrw.value = false;
  }
}

async function handleBatchAdoptVillage(desa: CakupanDesaItem) {
  adoptingRtrw.value = true;
  try {
    const res = await jaringanApi.adopsiRtrw({
      id_desa: desa.id,
      tahun_survey: new Date().getFullYear(),
      skip_spatial_duplicate: true,
    });
    toast.add({
      title: `Adopsi Desa ${desa.nama_desa} Selesai`,
      description: res.message || `Berhasil memproses rencana jalan untuk Desa ${desa.nama_desa}.`,
      color: 'success',
    });
    pushConsoleLog('success', `Adopsi segmen RTRW untuk Desa ${desa.nama_desa} selesai.`);
    await refreshAllData(true);
  } catch (err: any) {
    const msg = err?.data?.message || `Gagal adopsi RTRW Desa ${desa.nama_desa}.`;
    toast.add({ title: 'Gagal Adopsi Desa', description: msg, color: 'error' });
    pushConsoleLog('error', msg);
  } finally {
    adoptingRtrw.value = false;
  }
}

async function handleSavePoros(payload: {
  id: string;
  kode_ruas?: string | null;
  nama_ruas: string;
  hierarki_jalan: HierarkiJalan;
  lebar_m?: number | null;
  perkerasan?: string | null;
  kondisi?: string | null;
  catatan_validasi?: string | null;
}) {
  savingPoros.value = true;
  try {
    const res = await jaringanApi.updateRuas(payload.id, payload);
    porosDetailCache.set(payload.id, res.data);
    selectedPoros.value = res.data;
    toast.add({
      title: 'Master Poros Diperbarui',
      description: res.message || 'Atribut ruas berhasil disimpan.',
      color: 'success',
    });
    pushConsoleLog('success', `Pembaruan atribut ruas ${payload.nama_ruas} berhasil.`);
    await Promise.all([loadMasterList(), loadSummary(), loadCakupanDesa()]);
  } catch (err: any) {
    const msg = err?.data?.message || 'Gagal memperbarui atribut ruas jalan.';
    toast.add({ title: 'Gagal Menyimpan', description: msg, color: 'error' });
    pushConsoleLog('error', msg);
  } finally {
    savingPoros.value = false;
  }
}

function openDeleteDialog(feature: JaringanJalanRuasFeature) {
  lastFocusedBeforeModal = (document.activeElement as HTMLElement) || null;
  confirmDeleteTarget.value = feature;
  nextTick(() => {
    deleteCancelBtnRef.value?.focus();
  });
}

function closeDeleteDialog() {
  confirmDeleteTarget.value = null;
  nextTick(() => {
    lastFocusedBeforeModal?.focus();
    lastFocusedBeforeModal = null;
  });
}

async function confirmDeletePoros() {
  if (!confirmDeleteTarget.value) return;
  const target = confirmDeleteTarget.value;
  deletingPoros.value = true;
  try {
    const res = await jaringanApi.deleteRuas(target.id);
    toast.add({
      title: 'Ruas Dihapus',
      description: res.message || `Ruas ${target.properties.nama_ruas} dihapus dari Master Poros Desa.`,
      color: 'success',
    });
    pushConsoleLog('warn', `Ruas Master Poros "${target.properties.nama_ruas}" dihapus.`);
    closeDeleteDialog();
    clearSelection();
    await refreshAllData(true);
  } catch (err: any) {
    const msg = err?.data?.message || 'Gagal menghapus ruas.';
    toast.add({ title: 'Gagal Menghapus', description: msg, color: 'error' });
  } finally {
    deletingPoros.value = false;
  }
}

// ─── Filter Mutators & Synchronization ────────────────────────────────────────
function applyFilterPatch(patch: Partial<typeof filters>) {
  Object.assign(filters, patch);
  filters.analisaPage = 1;
  filters.cakupanPage = 1;
  filters.masterPage = 1;
  filters.kabupatenPage = 1;
  refreshAllData();
}

function resetFilters() {
  if (!isKecamatanLocked.value) filters.id_kecamatan = null;
  if (!isDesaLocked.value) filters.id_desa = null;
  filters.status_analisa = '';
  filters.orde02 = '';
  filters.only_unadopted = false;
  filters.only_missing_desa = false;
  filters.hierarki_jalan = '';
  filters.klas_ruas = '';
  filters.search = '';
  filters.analisaPage = 1;
  filters.cakupanPage = 1;
  filters.masterPage = 1;
  filters.kabupatenPage = 1;
  refreshAllData();
}

function setStatusFilter(status: '' | StatusAnalisaRtrw) {
  filters.status_analisa = status;
  filters.analisaPage = 1;
  Promise.all([loadAnalisaList(), loadMapIndex()]);
}

function toggleStatusFilter(status: '' | StatusAnalisaRtrw) {
  setStatusFilter(filters.status_analisa === status ? '' : status);
}

function toggleOrdeFilter(orde02: string) {
  filters.orde02 = filters.orde02 === orde02 ? '' : orde02;
  filters.analisaPage = 1;
  if (isDesktop.value && orde02) expandBottomPanel();
  refreshAllData();
}

function handlePageChange(payload: { tab: JaringanWorkspaceTab; page: number }) {
  if (payload.tab === 'analisa') {
    filters.analisaPage = payload.page;
    loadAnalisaList();
  } else if (payload.tab === 'cakupan') {
    filters.cakupanPage = payload.page;
    loadCakupanDesa();
  } else if (payload.tab === 'master') {
    filters.masterPage = payload.page;
    loadMasterList();
  } else if (payload.tab === 'kabupaten') {
    filters.kabupatenPage = payload.page;
    loadKabupatenList();
  }
}

let searchTimer: any = null;
function handleSearchUpdate(val: string) {
  filters.search = val;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    filters.analisaPage = 1;
    filters.cakupanPage = 1;
    filters.masterPage = 1;
    filters.kabupatenPage = 1;
    refreshAllData();
  }, 320);
}

watch(perPage, () => {
  filters.analisaPage = 1;
  filters.cakupanPage = 1;
  filters.masterPage = 1;
  filters.kabupatenPage = 1;
  refreshAllData();
});

watch(activeTab, (tab) => {
  ensureTabLoaded(tab);
});

watch(cakupanStatusFilter, () => {
  filters.cakupanPage = 1;
  dirtyTabs.cakupan = false;
  loadCakupanDesa();
});

function handleGlobalKeydown(e: KeyboardEvent) {
  if (e.key !== 'Escape') return;
  if (confirmDeleteTarget.value) {
    e.preventDefault();
    closeDeleteDialog();
  } else if (!filterModalOpen.value && !mobileSheetOpen.value && mapSelection.value) {
    e.preventDefault();
    clearSelection();
  }
}

onMounted(() => {
  if (import.meta.client) {
    window.addEventListener('resize', handleWindowResize);
    window.addEventListener('keydown', handleGlobalKeydown);
  }

  if (isKecamatanLocked.value && auth.user?.id_kecamatan) {
    filters.id_kecamatan = Number(auth.user.id_kecamatan);
  }
  if (isDesaLocked.value && auth.user?.id_desa) {
    filters.id_desa = Number(auth.user.id_desa);
  }

  nextTick(() => {
    if (leftPanelContainerEl.value && typeof ResizeObserver !== 'undefined') {
      leftWidthObserver = new ResizeObserver((entries) => {
        const w = entries[0]?.contentRect?.width;
        if (typeof w === 'number' && w > 0) {
          leftPanelActualWidth.value = Math.round(w);
        }
      });
      leftWidthObserver.observe(leftPanelContainerEl.value);
    }
  });

  pushConsoleLog('info', 'Workbench GIS Jaringan Jalan siap. Memuat ringkasan & indeks vektor...');
  refreshAllData();
});

onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  leftWidthObserver?.disconnect();
  leftWidthObserver = null;
  if (import.meta.client) {
    window.removeEventListener('resize', handleWindowResize);
    window.removeEventListener('keydown', handleGlobalKeydown);
  }
});
</script>

<template>
  <div class="flex flex-col w-full h-[calc(100dvh-3.5rem)] overflow-hidden bg-gray-100 text-gray-900 dark:bg-[#060913] dark:text-gray-100">
    <!-- ═══ TOP WORKBENCH HEADER BAR ════════════════════════════════════════════ -->
    <header class="flex items-center justify-between min-h-[48px] lg:min-h-0 lg:h-11 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-[#0b0f19] shrink-0 z-30">
      <!-- Left Title Group (Desktop aligned dynamically with LeftPanel width) -->
      <div class="flex items-center h-full min-w-0">
        <div
          class="flex items-center gap-2 px-3 shrink-0 transition-[width] duration-200 ease-out"
          :style="isDesktop && leftPanelActualWidth > 0 ? { width: `${leftPanelActualWidth}px` } : undefined"
        >
          <UIcon name="i-lucide-route" class="size-4 text-[#176B4D] dark:text-emerald-400 shrink-0" />
          <h1 class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white truncate">
            Analisa Spasial Jaringan Jalan
          </h1>
        </div>

        <div class="hidden lg:block h-5 w-px bg-gray-200 dark:bg-gray-800 shrink-0" />

        <!-- Desktop Quick Wilayah Selector -->
        <div class="hidden lg:flex items-center gap-2 px-3 min-w-0">
          <WilayahSelector
            v-if="!isDesaLocked"
            :model-kecamatan="filters.id_kecamatan"
            :model-desa="filters.id_desa"
            layout="inline"
            size="xs"
            :allow-all="true"
            :show-labels="false"
            :show-clear="false"
            @update:model-kecamatan="(v) => applyFilterPatch({ id_kecamatan: v, id_desa: null })"
            @update:model-desa="(v) => applyFilterPatch({ id_desa: v })"
          />
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200"
          >
            <UIcon name="i-lucide-lock" class="size-3.5" />
            Wilayah Desa Terkunci
          </span>

          <!-- Ringkasan Telemetri Singkat -->
          <div class="hidden xl:flex items-center gap-2 pl-2 border-l border-gray-200 dark:border-gray-800 text-xs tabular-nums text-gray-700 dark:text-gray-300">
            <span>
              <strong class="font-semibold text-gray-900 dark:text-white">{{ formatAngka(summaryData?.totals?.total_segmen ?? 0, 0) }}</strong> Segmen RTRW
            </span>
            <span class="text-gray-400">|</span>
            <span>
              Cakupan <strong class="font-semibold text-[#176B4D] dark:text-emerald-400">{{ formatAngka(summaryData?.totals?.avg_coverage_pct ?? 0, 1) }}%</strong>
            </span>
            <span class="text-gray-400">|</span>
            <span>
              <strong class="font-semibold text-gray-900 dark:text-white">{{ formatAngka(summaryData?.dataset_overview?.jalan_poros_ruas ?? 0, 0) }}</strong> Poros
            </span>
          </div>
        </div>
      </div>

      <!-- Right Action Controls -->
      <div class="flex items-center gap-1.5 px-2 sm:px-3 shrink-0">
        <!-- Tombol Filter (Desktop / Tablet Header) -->
        <button
          type="button"
          :aria-label="hasActiveFilters ? `Filter aktif (${activeFilterCount}), klik untuk ubah` : 'Buka filter lanjutan'"
          :class="[
            'hidden sm:inline-flex items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
            isDesktop ? 'h-7' : 'min-h-[44px]',
            hasActiveFilters
              ? 'border-blue-400 bg-blue-50 text-blue-900 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/60 dark:text-blue-200'
              : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200',
          ]"
          @click="isDesktop ? (filterModalOpen = true) : openMobileDock('filter', 'full')"
        >
          <UIcon name="i-lucide-sliders-horizontal" class="size-3.5" />
          <span>Filter</span>
          <span
            v-if="activeFilterCount > 0"
            class="rounded-full bg-blue-700 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white tabular-nums"
          >
            {{ activeFilterCount }}
          </span>
        </button>

        <!-- Hitung Ulang Spasial -->
        <button
          v-if="canManage"
          type="button"
          :disabled="runningAnalysis"
          aria-label="Hitung ulang irisan spasial"
          :class="[
            'inline-flex items-center gap-1.5 rounded-md bg-[#176B4D] px-2.5 sm:px-3 text-xs font-semibold text-white hover:bg-[#12543c] disabled:opacity-60 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
            isDesktop ? 'h-7' : 'min-h-[44px] min-w-[44px]',
          ]"
          @click="handleRunFullAnalysis"
        >
          <UIcon
            :name="runningAnalysis ? 'i-lucide-loader-2' : 'i-lucide-refresh-cw'"
            :class="['size-3.5 shrink-0', runningAnalysis ? 'animate-spin' : '']"
          />
          <span class="hidden sm:inline">{{ runningAnalysis ? 'Menghitung...' : 'Hitung Ulang' }}</span>
        </button>

        <!-- Desktop Toggle Focus Map -->
        <UTooltip v-if="isDesktop" :text="allPanelsCollapsed ? 'Tampilkan panel kerja' : 'Mode peta penuh'">
          <button
            type="button"
            :aria-label="allPanelsCollapsed ? 'Tampilkan semua panel kerja' : 'Ciutkan semua panel untuk mode peta penuh'"
            :aria-pressed="allPanelsCollapsed"
            class="size-7 flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="toggleAllPanels"
          >
            <UIcon :name="allPanelsCollapsed ? 'i-lucide-panel-left-open' : 'i-lucide-panel-left-close'" class="size-3.5" />
          </button>
        </UTooltip>
      </div>
    </header>

    <!-- ═══ MAIN GIS WORKBENCH AREA ═════════════════════════════════════════════ -->
    <div class="relative flex flex-1 min-h-0 w-full overflow-hidden">
      <!-- DESKTOP (>= 1024px): Two-Tier Resizable USplitter -->
      <div v-if="isDesktop" class="flex flex-1 w-full h-full overflow-hidden">
        <USplitter
          :items="verticalSplitterItems"
          orientation="vertical"
          class="w-full h-full"
          :ui="{
            root: 'h-full w-full',
            panel: isDraggingVertical ? '' : 'panel-smooth-transition',
            handle: 'h-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-row-resize',
          }"
          @dragging="(_index: number, dragging: boolean) => handleSplitterDragging('vertical', dragging)"
        >
          <template #workspace>
            <USplitter
              :items="horizontalSplitterItems"
              orientation="horizontal"
              class="w-full h-full"
              :ui="{
                root: 'h-full w-full',
                panel: isDraggingHorizontal ? '' : 'panel-smooth-transition',
                handle: 'w-1 bg-gray-200 dark:bg-gray-800 hover:bg-blue-500 active:bg-blue-600 transition-colors cursor-col-resize',
              }"
              @dragging="(_index: number, dragging: boolean) => handleSplitterDragging('horizontal', dragging)"
            >
              <!-- LEFT PANEL -->
              <template #left="{ collapsed, collapse, expand, resize }">
                <div ref="leftPanelContainerEl" class="w-full h-full flex flex-col overflow-hidden">
                  <span :class="syncLeftSlot(collapsed, collapse, expand, resize)" class="hidden" />
                  <JaringanJalanLeftPanel
                    :collapsed="collapsed"
                    :is-mobile-drawer="false"
                    :layers="mapLayers"
                    :layers-opacity="layersOpacity"
                    :color-mode-type="rtrwColorMode"
                    :summary-data="summaryData"
                    :status-filter="filters.status_analisa"
                    :orde-filter="filters.orde02"
                    :active-filter-count="activeFilterCount"
                    :id-kecamatan="filters.id_kecamatan"
                    :id-desa="filters.id_desa"
                    :is-kecamatan-locked="isKecamatanLocked"
                    :is-desa-locked="isDesaLocked"
                    @update:collapsed="(c) => { c ? collapse() : resize(LEFT_PANEL_WIDTH); scheduleMapResize(); }"
                    @update:layers="(v) => (mapLayers = v)"
                    @update:layers-opacity="(v) => (layersOpacity = v)"
                    @update:color-mode-type="(v) => (rtrwColorMode = v)"
                    @update:id-kecamatan="(v) => applyFilterPatch({ id_kecamatan: v, id_desa: null })"
                    @update:id-desa="(v) => applyFilterPatch({ id_desa: v })"
                    @toggle-status-filter="(st) => toggleStatusFilter(st)"
                    @toggle-orde-filter="(o) => toggleOrdeFilter(o)"
                    @zoom-to-layer="handleZoomToLayer"
                    @open-filter-modal="filterModalOpen = true"
                    @focus-workspace-tab="(t) => { activeTab = t; expandBottomPanel(); }"
                  />
                </div>
              </template>

              <!-- CENTER MAP CANVAS -->
              <template #map>
                <div class="relative w-full h-full overflow-hidden">
                  <JaringanJalanMaplibre
                    ref="mapRef"
                    :map-index="mapIndex"
                    :selection="mapSelection"
                    :layers="mapLayers"
                    :layers-opacity="layersOpacity"
                    :color-mode-type="rtrwColorMode"
                    :active-bounds="mapIndex?.bounds ?? null"
                    :loading-index="loadingMapIndex"
                    :full-bleed-canvas="true"
                    @update:layers="(v) => (mapLayers = v)"
                    @update:color-mode-type="(v) => (rtrwColorMode = v)"
                    @select-rtrw="(id) => inspectRtrwSegment(id, true)"
                    @select-poros="(id) => inspectPorosFeature(id, true)"
                    @select-kabupaten="(id) => inspectKabupatenFeature(id, true)"
                    @clear-selection="clearSelection"
                    @martin-status="handleMartinStatus"
                  />

                  <!-- Floating Reopen Right Panel Button when collapsed with active selection -->
                  <button
                    v-if="mapSelection && rightPanelCollapsed"
                    type="button"
                    class="absolute bottom-3 right-3 z-20 inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-white/95 px-3 py-1.5 text-xs font-semibold text-blue-900 shadow-lg backdrop-blur-md hover:bg-blue-50 dark:border-blue-700 dark:bg-[#0e1424]/95 dark:text-blue-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="expandRightPanel"
                  >
                    <UIcon name="i-lucide-panel-right-open" class="size-4 text-blue-600 dark:text-blue-400" />
                    <span>Buka Detail Ruas</span>
                  </button>
                </div>
              </template>

              <!-- RIGHT INSPECTOR PANEL -->
              <template #right="{ collapsed, collapse, expand, resize }">
                <span :class="syncRightSlot(collapsed, collapse, expand, resize)" class="hidden" />
                <JaringanJalanRightPanel
                  :collapsed="collapsed"
                  :active-mode="inspectorMode"
                  :rtrw-detail="selectedRtrwDetail"
                  :loading-rtrw-detail="loadingDetail"
                  :selected-poros="selectedPoros"
                  :selected-kabupaten="selectedKabupaten"
                  :can-manage="canManage"
                  :adopting-rtrw="adoptingRtrw"
                  :saving-poros="savingPoros"
                  :deleting-poros="deletingPoros"
                  @update:collapsed="(c) => { c ? collapse() : resize(RIGHT_PANEL_WIDTH); scheduleMapResize(); }"
                  @adopt-rtrw="handleAdoptFromInspector"
                  @save-poros="handleSavePoros"
                  @request-delete-poros="openDeleteDialog"
                  @clear-selection="clearSelection"
                  @zoom-to-bbox="zoomToBbox"
                />
              </template>
            </USplitter>
          </template>

          <!-- BOTTOM ATTRIBUTE & ANALYTICS DRAWER -->
          <template #bottom="{ collapsed, collapse, expand, resize }">
            <span :class="syncBottomSlot(collapsed, collapse, expand, resize)" class="hidden" />
            <JaringanJalanBottomPanel
              :collapsed="collapsed"
              :is-mobile-drawer="false"
              :active-tab="activeTab"
              :per-page="perPage"
              :search-query="filters.search"
              :can-manage="canManage"
              :has-active-filters="hasActiveFilters"
              :active-filter-count="activeFilterCount"
              :status-filter="filters.status_analisa"
              :orde-filter="filters.orde02"
              :selection="mapSelection"
              :summary-data="summaryData"
              :analisa-list="analisaList"
              :analisa-meta="analisaMeta"
              :loading-analisa="loadingAnalisa"
              :selected-rtrw-ids="selectedRtrwIds"
              :adopting-rtrw="adoptingRtrw"
              :cakupan-list="cakupanList"
              :cakupan-meta="cakupanMeta"
              :cakupan-summary="cakupanSummary"
              :cakupan-status-filter="cakupanStatusFilter"
              :loading-cakupan="loadingCakupan"
              :master-list="masterList"
              :master-meta="masterMeta"
              :loading-master="loadingMaster"
              :kabupaten-list="kabupatenList"
              :kabupaten-meta="kabupatenMeta"
              :loading-kabupaten="loadingKabupaten"
              :console-logs="consoleLogs"
              @update:collapsed="(c) => { c ? collapse() : resize(BOTTOM_PANEL_HEIGHT); scheduleMapResize(); }"
              @update:active-tab="(t) => (activeTab = t)"
              @update:per-page="(p) => (perPage = p)"
              @update:search-query="handleSearchUpdate"
              @update:selected-rtrw-ids="(ids) => (selectedRtrwIds = ids)"
              @update:cakupan-status-filter="(st) => (cakupanStatusFilter = st)"
              @change-page="handlePageChange"
              @inspect-rtrw="(item) => inspectRtrwSegment(item.id, true, item.bbox)"
              @zoom-rtrw="(item) => zoomToBbox(item.bbox)"
              @bulk-adopt="handleBulkAdoptSelected"
              @adopt-desa="handleBatchAdoptVillage"
              @filter-desa="(d) => { applyFilterPatch({ id_desa: d.id, status_analisa: '' }); activeTab = 'analisa'; }"
              @inspect-poros="(feat) => inspectPorosFeature(feat, true)"
              @inspect-kabupaten="(kab) => inspectKabupatenFeature(kab, true)"
              @filter-orde="(o) => toggleOrdeFilter(o)"
              @filter-wilayah="(id) => { if (summaryData?.wilayah_level === 'desa') applyFilterPatch({ id_desa: id }); else applyFilterPatch({ id_kecamatan: id, id_desa: null }); activeTab = 'analisa'; }"
              @toggle-status="setStatusFilter"
              @reset-filters="resetFilters"
              @open-filter-modal="filterModalOpen = true"
              @clear-console="consoleLogs = []"
            />
          </template>
        </USplitter>
      </div>

      <!-- MOBILE & TABLET (< 1024px): Full-Bleed Map + Floating Peek Card + Safe-Area Bottom Dock -->
      <div v-else class="relative flex-1 w-full h-full overflow-hidden">
        <JaringanJalanMaplibre
          ref="mapRef"
          :map-index="mapIndex"
          :selection="mapSelection"
          :layers="mapLayers"
          :layers-opacity="layersOpacity"
          :color-mode-type="rtrwColorMode"
          :active-bounds="mapIndex?.bounds ?? null"
          :loading-index="loadingMapIndex"
          :full-bleed-canvas="true"
          @update:layers="(v) => (mapLayers = v)"
          @update:color-mode-type="(v) => (rtrwColorMode = v)"
          @select-rtrw="(id) => { inspectRtrwSegment(id, false); openMobileDock('detail', 'peek'); }"
          @select-poros="(id) => { inspectPorosFeature(id, false); openMobileDock('detail', 'peek'); }"
          @select-kabupaten="(id) => { inspectKabupatenFeature(id, false); openMobileDock('detail', 'peek'); }"
          @clear-selection="clearSelection"
          @martin-status="handleMartinStatus"
        />

        <!-- Floating Feature Peek Card di Atas Dock saat Sheet Tertutup -->
        <div
          v-if="mapSelection && !mobileSheetOpen"
          class="absolute left-3 right-3 sm:left-auto sm:right-3 sm:w-96 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-xl backdrop-blur-md dark:border-gray-700 dark:bg-[#0e1424]/95"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <span
                v-if="mapSelection.badge"
                class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-800 border border-blue-200 dark:bg-blue-950/70 dark:text-blue-200 dark:border-blue-800"
              >
                {{ mapSelection.badge }}
              </span>
              <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white truncate">
                {{ mapSelection.title }}
              </p>
              <p v-if="mapSelection.subtitle" class="text-xs text-gray-600 dark:text-gray-300 truncate">
                {{ mapSelection.subtitle }}
              </p>
            </div>

            <button
              type="button"
              aria-label="Hapus pilihan segmen"
              class="size-11 flex items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="clearSelection"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <div class="mt-2.5 flex items-center gap-2">
            <button
              type="button"
              class="min-h-[44px] flex-1 rounded-lg bg-[#176B4D] px-3 text-xs font-semibold text-white hover:bg-[#12543c] cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="openMobileDock('detail', 'peek')"
            >
              Buka Detail & Aksi
            </button>
            <button
              v-if="mapSelection.bbox"
              type="button"
              aria-label="Zoom ke segmen terpilih"
              class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-800 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="zoomToBbox(mapSelection.bbox)"
            >
              <UIcon name="i-lucide-locate-fixed" class="size-4" />
            </button>
          </div>
        </div>

        <!-- Fixed Safe-Area Bottom Action Dock (5 Buttons >= 44px) -->
        <nav
          aria-label="Navigasi Panel Peta Seluler"
          class="fixed bottom-0 left-0 right-0 z-30 grid grid-cols-5 gap-1 border-t border-gray-200 bg-white/95 px-1.5 pt-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))] backdrop-blur-md shadow-lg dark:border-gray-800 dark:bg-[#0b0f19]/95"
        >
          <button
            type="button"
            :aria-pressed="mobileSheetOpen && mobileActiveDock === 'layers'"
            :class="[
              'min-h-[44px] flex flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              mobileSheetOpen && mobileActiveDock === 'layers'
                ? 'bg-emerald-50 text-[#176B4D] font-semibold dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
            ]"
            @click="openMobileDock('layers', 'peek')"
          >
            <UIcon name="i-lucide-layers" class="size-4" />
            <span>Layer</span>
          </button>

          <button
            type="button"
            :aria-pressed="mobileSheetOpen && mobileActiveDock === 'table'"
            :class="[
              'min-h-[44px] flex flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              mobileSheetOpen && mobileActiveDock === 'table'
                ? 'bg-emerald-50 text-[#176B4D] font-semibold dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
            ]"
            @click="openMobileDock('table', 'full')"
          >
            <UIcon name="i-lucide-table-2" class="size-4" />
            <span>Tabel</span>
          </button>

          <button
            type="button"
            :aria-pressed="mobileSheetOpen && mobileActiveDock === 'rekap'"
            :class="[
              'min-h-[44px] flex flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              mobileSheetOpen && mobileActiveDock === 'rekap'
                ? 'bg-emerald-50 text-[#176B4D] font-semibold dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
            ]"
            @click="openMobileDock('rekap', 'full')"
          >
            <UIcon name="i-lucide-bar-chart-3" class="size-4" />
            <span>Rekap</span>
          </button>

          <button
            type="button"
            :aria-pressed="mobileSheetOpen && mobileActiveDock === 'detail'"
            :class="[
              'relative min-h-[44px] flex flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              mobileSheetOpen && mobileActiveDock === 'detail'
                ? 'bg-blue-50 text-blue-800 font-semibold dark:bg-blue-950/60 dark:text-blue-300'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
            ]"
            @click="openMobileDock('detail', 'peek')"
          >
            <UIcon name="i-lucide-info" class="size-4" />
            <span>Detail</span>
            <span
              v-if="mapSelection"
              class="absolute top-1.5 right-3 size-2 rounded-full bg-blue-600"
            />
          </button>

          <button
            type="button"
            :aria-pressed="mobileSheetOpen && mobileActiveDock === 'filter'"
            :class="[
              'relative min-h-[44px] flex flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              mobileSheetOpen && mobileActiveDock === 'filter'
                ? 'bg-blue-50 text-blue-800 font-semibold dark:bg-blue-950/60 dark:text-blue-300'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
            ]"
            @click="openMobileDock('filter', 'full')"
          >
            <UIcon name="i-lucide-sliders-horizontal" class="size-4" />
            <span>Filter</span>
            <span
              v-if="activeFilterCount > 0"
              class="absolute top-1 right-2.5 rounded-full bg-blue-700 px-1 text-[10px] font-bold text-white tabular-nums"
            >
              {{ activeFilterCount }}
            </span>
          </button>
        </nav>

        <!-- Unified Mobile Bottom Sheet -->
        <MobileBottomSheet
          v-model:open="mobileSheetOpen"
          v-model:snap-level="mobileSheetSnap"
          :title="mobileSheetTitle"
          :icon="mobileSheetIcon"
        >
          <!-- Layer Drawer -->
          <JaringanJalanLeftPanel
            v-if="mobileActiveDock === 'layers'"
            :collapsed="false"
            :is-mobile-drawer="true"
            :layers="mapLayers"
            :layers-opacity="layersOpacity"
            :color-mode-type="rtrwColorMode"
            :summary-data="summaryData"
            :status-filter="filters.status_analisa"
            :orde-filter="filters.orde02"
            :active-filter-count="activeFilterCount"
            :id-kecamatan="filters.id_kecamatan"
            :id-desa="filters.id_desa"
            :is-kecamatan-locked="isKecamatanLocked"
            :is-desa-locked="isDesaLocked"
            @update:layers="(v) => (mapLayers = v)"
            @update:layers-opacity="(v) => (layersOpacity = v)"
            @update:color-mode-type="(v) => (rtrwColorMode = v)"
            @update:id-kecamatan="(v) => applyFilterPatch({ id_kecamatan: v, id_desa: null })"
            @update:id-desa="(v) => applyFilterPatch({ id_desa: v })"
            @toggle-status-filter="(st) => toggleStatusFilter(st)"
            @toggle-orde-filter="(o) => toggleOrdeFilter(o)"
            @zoom-to-layer="handleZoomToLayer"
            @open-filter-modal="openMobileDock('filter', 'full')"
            @focus-workspace-tab="(t) => { activeTab = t; openMobileDock('table', 'full'); }"
          />

          <!-- Data Table & Cakupan Drawer -->
          <JaringanJalanBottomPanel
            v-else-if="mobileActiveDock === 'table'"
            :collapsed="false"
            :is-mobile-drawer="true"
            :hide-console-tab="true"
            :only-tabs="['analisa', 'cakupan', 'master', 'kabupaten']"
            :active-tab="activeTab === 'breakdown' || activeTab === 'console' ? 'analisa' : activeTab"
            :per-page="perPage"
            :search-query="filters.search"
            :can-manage="canManage"
            :has-active-filters="hasActiveFilters"
            :active-filter-count="activeFilterCount"
            :status-filter="filters.status_analisa"
            :orde-filter="filters.orde02"
            :selection="mapSelection"
            :summary-data="summaryData"
            :analisa-list="analisaList"
            :analisa-meta="analisaMeta"
            :loading-analisa="loadingAnalisa"
            :selected-rtrw-ids="selectedRtrwIds"
            :adopting-rtrw="adoptingRtrw"
            :cakupan-list="cakupanList"
            :cakupan-meta="cakupanMeta"
            :cakupan-summary="cakupanSummary"
            :cakupan-status-filter="cakupanStatusFilter"
            :loading-cakupan="loadingCakupan"
            :master-list="masterList"
            :master-meta="masterMeta"
            :loading-master="loadingMaster"
            :kabupaten-list="kabupatenList"
            :kabupaten-meta="kabupatenMeta"
            :loading-kabupaten="loadingKabupaten"
            :console-logs="consoleLogs"
            @update:active-tab="(t) => (activeTab = t)"
            @update:search-query="handleSearchUpdate"
            @update:selected-rtrw-ids="(ids) => (selectedRtrwIds = ids)"
            @update:cakupan-status-filter="(st) => (cakupanStatusFilter = st)"
            @change-page="handlePageChange"
            @inspect-rtrw="(item) => { inspectRtrwSegment(item.id, false, item.bbox); openMobileDock('detail', 'peek'); }"
            @zoom-rtrw="(item) => zoomToBbox(item.bbox)"
            @bulk-adopt="handleBulkAdoptSelected"
            @adopt-desa="handleBatchAdoptVillage"
            @filter-desa="(d) => { applyFilterPatch({ id_desa: d.id, status_analisa: '' }); activeTab = 'analisa'; }"
            @inspect-poros="(feat) => { inspectPorosFeature(feat, false); openMobileDock('detail', 'peek'); }"
            @inspect-kabupaten="(kab) => { inspectKabupatenFeature(kab, false); openMobileDock('detail', 'peek'); }"
            @filter-orde="(o) => toggleOrdeFilter(o)"
            @filter-wilayah="(id) => { if (summaryData?.wilayah_level === 'desa') applyFilterPatch({ id_desa: id }); else applyFilterPatch({ id_kecamatan: id, id_desa: null }); }"
            @toggle-status="setStatusFilter"
            @reset-filters="resetFilters"
            @open-filter-modal="openMobileDock('filter', 'full')"
            @clear-console="consoleLogs = []"
          />

          <!-- Rekap Orde, Wilayah & Konsol Analis -->
          <JaringanJalanBottomPanel
            v-else-if="mobileActiveDock === 'rekap'"
            :collapsed="false"
            :is-mobile-drawer="true"
            :hide-console-tab="false"
            :only-tabs="['breakdown', 'console']"
            :active-tab="mobileRekapTab"
            :per-page="perPage"
            :search-query="filters.search"
            :can-manage="canManage"
            :has-active-filters="hasActiveFilters"
            :active-filter-count="activeFilterCount"
            :status-filter="filters.status_analisa"
            :orde-filter="filters.orde02"
            :selection="mapSelection"
            :summary-data="summaryData"
            :analisa-list="analisaList"
            :analisa-meta="analisaMeta"
            :loading-analisa="loadingAnalisa"
            :selected-rtrw-ids="selectedRtrwIds"
            :adopting-rtrw="adoptingRtrw"
            :cakupan-list="cakupanList"
            :cakupan-meta="cakupanMeta"
            :cakupan-summary="cakupanSummary"
            :cakupan-status-filter="cakupanStatusFilter"
            :loading-cakupan="loadingCakupan"
            :master-list="masterList"
            :master-meta="masterMeta"
            :loading-master="loadingMaster"
            :kabupaten-list="kabupatenList"
            :kabupaten-meta="kabupatenMeta"
            :loading-kabupaten="loadingKabupaten"
            :console-logs="consoleLogs"
            @update:active-tab="(t) => (mobileRekapTab = t === 'console' ? 'console' : 'breakdown')"
            @filter-orde="(o) => { toggleOrdeFilter(o); openMobileDock('table', 'full'); }"
            @filter-wilayah="(id) => { if (summaryData?.wilayah_level === 'desa') applyFilterPatch({ id_desa: id }); else applyFilterPatch({ id_kecamatan: id, id_desa: null }); activeTab = 'analisa'; openMobileDock('table', 'full'); }"
            @clear-console="consoleLogs = []"
          />

          <!-- Detail & Aksi Inspektor -->
          <div v-else-if="mobileActiveDock === 'detail'" class="p-3">
            <h2 class="sr-only">Inspektor Ruas Terpilih</h2>
            <JaringanJalanInspectorCard
              :active-mode="inspectorMode"
              :rtrw-detail="selectedRtrwDetail"
              :loading-rtrw-detail="loadingDetail"
              :selected-poros="selectedPoros"
              :selected-kabupaten="selectedKabupaten"
              :can-manage="canManage"
              :adopting-rtrw="adoptingRtrw"
              :saving-poros="savingPoros"
              :deleting-poros="deletingPoros"
              :is-mobile-drawer="true"
              @adopt-rtrw="handleAdoptFromInspector"
              @save-poros="handleSavePoros"
              @request-delete-poros="openDeleteDialog"
              @clear-selection="clearSelection"
              @zoom-to-bbox="zoomToBbox"
            />
          </div>

          <!-- Filter Drawer -->
          <div v-else-if="mobileActiveDock === 'filter'" class="p-3">
            <h2 class="sr-only">Filter Analisa Spasial dan Wilayah</h2>
            <JaringanJalanFilterContent
              :filters="filters"
              :orde-options="summaryData?.orde_options || []"
              :is-kecamatan-locked="isKecamatanLocked"
              :is-desa-locked="isDesaLocked"
              :is-mobile-drawer="true"
              @update-filter="applyFilterPatch"
              @reset-filters="resetFilters"
              @close="mobileSheetOpen = false"
            />
          </div>
        </MobileBottomSheet>
      </div>
    </div>

    <!-- Desktop Unified Filter Modal -->
    <JaringanJalanFilterModal
      v-model:open="filterModalOpen"
      :filters="filters"
      :orde-options="summaryData?.orde_options || []"
      :is-kecamatan-locked="isKecamatanLocked"
      :is-desa-locked="isDesaLocked"
      @update-filter="applyFilterPatch"
      @reset-filters="resetFilters"
    />

    <!-- Confirm Delete Master Poros Modal -->
    <Teleport to="body">
      <div
        v-if="confirmDeleteTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4 backdrop-blur-2xs"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-poros-title"
        @click.self="closeDeleteDialog"
      >
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-2xl space-y-4 dark:border-gray-800 dark:bg-[#0b0f19]">
          <div class="flex items-start gap-3">
            <div class="flex size-10 items-center justify-center rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 shrink-0">
              <UIcon name="i-lucide-alert-triangle" class="size-5" />
            </div>
            <div class="space-y-1">
              <h2 id="delete-poros-title" class="text-sm font-bold text-gray-900 dark:text-white">
                Hapus Ruas Master Jalan Poros Desa?
              </h2>
              <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                Ruas <strong>{{ confirmDeleteTarget.properties.nama_ruas }}</strong> akan dihapus dari Master Jalan Poros Desa dan memengaruhi skor cakupan desa terkait.
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button
              ref="deleteCancelBtnRef"
              type="button"
              :disabled="deletingPoros"
              class="min-h-[44px] lg:min-h-[36px] rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="closeDeleteDialog"
            >
              Batal
            </button>
            <button
              type="button"
              :disabled="deletingPoros"
              class="min-h-[44px] lg:min-h-[36px] inline-flex items-center gap-1.5 rounded-lg bg-rose-700 px-4 text-xs font-semibold text-white hover:bg-rose-800 disabled:opacity-60 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="confirmDeletePoros"
            >
              <UIcon v-if="deletingPoros" name="i-lucide-loader-2" class="size-4 animate-spin" />
              <span>{{ deletingPoros ? 'Menghapus...' : 'Hapus Permanen' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.panel-smooth-transition {
  transition:
    flex-basis 220ms cubic-bezier(0.22, 1, 0.36, 1),
    width 220ms cubic-bezier(0.22, 1, 0.36, 1),
    height 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
  .panel-smooth-transition {
    transition: none;
  }
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}

.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

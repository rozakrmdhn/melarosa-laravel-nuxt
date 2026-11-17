<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import { useAuthStore } from '~/stores/auth';
import { useWilayahStore } from '~/stores/wilayah';
import type {
  BkkdJalanPorosOption,
  BkkdProposal,
  BkkdProposalLokasi,
  BkkdRingkasanAnalisaSpasial,
  BkkdRoutePreviewItem,
  BkkdValidasiPromosiSpasial,
  HierarkiJalanBkkd,
  RefBkkdBidang,
  StatusAnalisaSpasialBkkd,
  StatusPromosiMasterBkkd,
  SumberRuteBkkd,
  TaggingUsulanBkkd,
} from '~/types/bkkd';

definePageMeta({
  middleware: ['auth'],
  fullBleed: true,
});

const route = useRoute();
const router = useRouter();
const toast = useToast();
const auth = useAuthStore();
const wilayahStore = useWilayahStore();
const { can } = usePermission();
const bkkdApi = useBkkdApi();

const proposalId = computed(() => String(route.params.id || 'baru'));
const isCreateMode = computed(() => proposalId.value === 'baru');

const canEdit = computed(() => {
  return (
    can('bkkd-proposal-update') ||
    can('bkkd-proposal-create') ||
    auth.hasRole('admin') ||
    auth.hasRole('verifierBappeda') ||
    auth.hasRole('operatorDesa') ||
    auth.hasRole('verifierKecamatan')
  );
});

const loadingPage = ref(false);
const savingProposal = ref(false);
const savingLocationId = ref<string | null>(null);
const dirtyLocationIds = ref<Record<string, boolean>>({});
const generatingRoute = ref(false);
const reanalyzing = ref(false);
const pageError = ref<string | null>(null);

const proposal = ref<BkkdProposal | null>(null);
const bidangList = ref<RefBkkdBidang[]>([]);
const jalanPorosOptions = ref<BkkdJalanPorosOption[]>([]);

const form = reactive({
  tahun_anggaran: new Date().getFullYear(),
  tagging_usulan: 'PRKPD' as TaggingUsulanBkkd,
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  id_bidang: '',
  judul_kegiatan: '',
  lokasi_kegiatan: '',
  nilai_usulan: null as number | null,
  uraian_kegiatan: '',
  catatan_konfirmasi_desa: '',
});

export interface WorkspaceLocationItem {
  localId: string;
  id?: string;
  urutan: number;
  nama_lokasi: string;
  id_jalan_poros: string | null;
  id_jaringan_ruas?: string | null;
  nama_ruas: string | null;
  nama_ruas_usulan?: string | null;
  hierarki_jalan_usulan?: HierarkiJalanBkkd | string | null;
  sumber_rute: SumberRuteBkkd;
  titik_awal: { lat: number; lng: number } | null;
  titik_akhir: { lat: number; lng: number } | null;
  panjang_gis_m: number;
  panjang_rencana_m: number | null;
  lebar_m: number | null;
  geojson: any;
  status_analisa_spasial: StatusAnalisaSpasialBkkd;
  status_promosi_master?: StatusPromosiMasterBkkd;
  ringkasan_analisa: BkkdRingkasanAnalisaSpasial | null;
  validasi_promosi_spasial?: BkkdValidasiPromosiSpasial | null;
  catatan_promosi_master?: string | null;
  jaringan_ruas?: {
    id: string;
    kode_ruas?: string | null;
    nama_ruas: string;
    hierarki_jalan: HierarkiJalanBkkd;
    status_validasi: string;
  } | null;
  snapshot_data_url: string | null;
}

const locations = ref<WorkspaceLocationItem[]>([]);
const selectedLocalId = ref<string | null>(null);

const activeLocation = computed<WorkspaceLocationItem | null>(() => {
  if (!locations.value.length) return null;
  if (selectedLocalId.value) {
    const found = locations.value.find((item) => item.localId === selectedLocalId.value);
    if (found) return found;
  }
  return locations.value[0] || null;
});

interface RouteFormDraft {
  nama_lokasi: string;
  id_jalan_poros: string;
  lebar_m: number | null;
  panjang_rencana_m: number | null;
  lat_awal: string | number;
  lng_awal: string | number;
  lat_akhir: string | number;
  lng_akhir: string | number;
}

const routeFormMode = ref<'create' | 'edit'>('create');
const routeSubView = ref<'list' | 'create' | 'edit'>('create');
const panelBodyRef = ref<HTMLElement | null>(null);

function scrollPanelBodyToTop() {
  nextTick(() => {
    panelBodyRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

function switchRouteSubView(view: 'list' | 'create' | 'edit') {
  routeSubView.value = view;
  routeFormMode.value = view === 'edit' ? 'edit' : 'create';
  renderBuilderMarkers();
  scrollPanelBodyToTop();
}

function normalizeOptionalLebar(value: unknown): number {
  if (value === null || value === undefined || value === '') return 0;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
}

const createBuilder = reactive<RouteFormDraft>({
  nama_lokasi: '',
  id_jalan_poros: '',
  lebar_m: 0,
  panjang_rencana_m: null,
  lat_awal: '',
  lng_awal: '',
  lat_akhir: '',
  lng_akhir: '',
});

const editBuilder = reactive<
  RouteFormDraft & {
    targetId: string | null;
    targetLocalId: string | null;
    targetUrutan: number;
  }
>({
  targetId: null,
  targetLocalId: null,
  targetUrutan: 1,
  nama_lokasi: '',
  id_jalan_poros: '',
  lebar_m: 0,
  panjang_rencana_m: null,
  lat_awal: '',
  lng_awal: '',
  lat_akhir: '',
  lng_akhir: '',
});

const activeEditLocation = computed<WorkspaceLocationItem | null>(() => {
  if (!editBuilder.targetLocalId) return null;
  return locations.value.find((item) => item.localId === editBuilder.targetLocalId) || null;
});

function getActiveRouteFormDraft(): RouteFormDraft {
  return routeFormMode.value === 'edit' ? editBuilder : createBuilder;
}

const clickTarget = ref<'awal' | 'akhir'>('awal');

interface WaypointDraft {
  lat: string | number;
  lng: string | number;
  label: string;
  is_route: boolean;
  use_ors: boolean;
}

function newWaypoint(lat: string | number = '', lng: string | number = ''): WaypointDraft {
  return { lat, lng, label: '', is_route: true, use_ors: false };
}

const createWaypoints = ref<WaypointDraft[]>([newWaypoint(), newWaypoint()]);

function addCreateWaypoint() {
  createWaypoints.value.push(newWaypoint());
}

function removeCreateWaypoint(idx: number) {
  if (createWaypoints.value.length <= 2) {
    createWaypoints.value[idx] = newWaypoint();
  } else {
    createWaypoints.value.splice(idx, 1);
  }
  renderBuilderMarkers();
}

function moveCreateWaypoint(idx: number, delta: -1 | 1) {
  const target = idx + delta;
  if (target < 0 || target >= createWaypoints.value.length) return;
  const list = createWaypoints.value;
  const tmp = list[idx]!;
  list[idx] = list[target]!;
  list[target] = tmp;
  renderBuilderMarkers();
}

function isValidWaypoint(wp: WaypointDraft): boolean {
  if (wp.lat === '' || wp.lng === '') return false;
  const lat = Number(wp.lat);
  const lng = Number(wp.lng);
  return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
}

const coordInputMode = ref<'decimal' | 'dms'>('decimal');

interface DmsParts {
  deg: number;
  min: number;
  sec: number;
  dir: 'N' | 'S' | 'E' | 'W';
}

function decimalToDms(val: string | number, isLat: boolean): DmsParts {
  const num = Number(val);
  if (val === '' || !Number.isFinite(num)) {
    return { deg: 0, min: 0, sec: 0, dir: isLat ? 'S' : 'E' };
  }
  const dir: 'N' | 'S' | 'E' | 'W' = isLat ? (num < 0 ? 'S' : 'N') : num < 0 ? 'W' : 'E';
  const abs = Math.abs(num);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = Math.round((minFloat - min) * 60 * 100) / 100;
  return { deg, min, sec, dir };
}

function dmsToDecimal(deg: number, min: number, sec: number, dir: 'N' | 'S' | 'E' | 'W'): number {
  const abs = Math.abs(Number(deg) || 0) + (Number(min) || 0) / 60 + (Number(sec) || 0) / 3600;
  const sign = dir === 'S' || dir === 'W' ? -1 : 1;
  return Number((sign * abs).toFixed(6));
}

function updateWaypointDms(
  wp: WaypointDraft,
  axis: 'lat' | 'lng',
  field: 'deg' | 'min' | 'sec' | 'dir',
  rawValue: string | number
) {
  const isLat = axis === 'lat';
  const current = decimalToDms(isLat ? wp.lat : wp.lng, isLat);
  const next: DmsParts = { ...current };
  if (field === 'dir') {
    next.dir = String(rawValue) as DmsParts['dir'];
  } else {
    next[field] = Math.max(0, Number(rawValue) || 0);
  }
  const dec = dmsToDecimal(next.deg, next.min, next.sec, next.dir);
  if (isLat) {
    wp.lat = dec;
  } else {
    wp.lng = dec;
  }
  renderBuilderMarkers();
}

function parseSingleDmsToken(token: string, defaultIsLat: boolean): number | null {
  const cleaned = token.trim().toUpperCase();
  const dmsRegex = /^(-?\d+(?:\.\d+)?)\s*[°º]\s*(\d+(?:\.\d+)?)?\s*['′’]?\s*(\d+(?:\.\d+)?)?\s*["″”]?[\s]*([NSEW])?$/;
  const match = cleaned.match(dmsRegex);
  if (!match) return null;
  const rawDeg = Number(match[1]);
  const min = Number(match[2] || 0);
  const sec = Number(match[3] || 0);
  const dirChar = match[4] as 'N' | 'S' | 'E' | 'W' | undefined;
  let dir: 'N' | 'S' | 'E' | 'W';
  if (dirChar) {
    dir = dirChar;
  } else if (rawDeg < 0) {
    dir = defaultIsLat ? 'S' : 'W';
  } else {
    dir = defaultIsLat ? 'N' : 'E';
  }
  return dmsToDecimal(Math.abs(rawDeg), min, sec, dir);
}

function handleWaypointPaste(event: ClipboardEvent, wp: WaypointDraft) {
  const text = event.clipboardData?.getData('text')?.trim();
  if (!text) return;

  // Format 1: Pair of decimal numbers separated by comma or space, e.g. "-7.1501, 111.8901"
  const decPair = text.match(/^(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)$/);
  if (decPair) {
    const a = Number(decPair[1]);
    const b = Number(decPair[2]);
    if (Number.isFinite(a) && Number.isFinite(b) && Math.abs(a) <= 90 && Math.abs(b) <= 180) {
      event.preventDefault();
      wp.lat = Number(a.toFixed(6));
      wp.lng = Number(b.toFixed(6));
      renderBuilderMarkers();
      return;
    }
  }

  // Format 2: Pair of DMS coordinates, e.g. 7°09'00.4"S 111°53'24.4"E
  const dmsParts = text.split(/\s*[,;]\s*|\s+(?=\d+\s*[°º])/).filter(Boolean);
  if (dmsParts.length === 2) {
    const latVal = parseSingleDmsToken(dmsParts[0]!, true);
    const lngVal = parseSingleDmsToken(dmsParts[1]!, false);
    if (latVal !== null && lngVal !== null && Math.abs(latVal) <= 90 && Math.abs(lngVal) <= 180) {
      event.preventDefault();
      wp.lat = latVal;
      wp.lng = lngVal;
      renderBuilderMarkers();
    }
  }
}
const activeLeftSection = ref<'identitas' | 'rute' | 'analisa'>('rute');
const rightPanelOpen = ref(true);
const mobileMapMenuOpen = ref(false);

// Mobile Bottom Sheet state (< 640px)
const isMobileViewport = ref(false);
const mobileSheetSnap = ref<'peek' | 'half' | 'full'>('half');
const sheetPanelRef = ref<HTMLElement | null>(null);
const isDraggingSheet = ref(false);
const draggingSheetHeightPx = ref<number | null>(null);
let sheetDragStartY = 0;
let sheetDragStartHeight = 0;
let sheetDragMoved = false;

function getMobileDetentHeightPx(snap: 'peek' | 'half' | 'full'): number {
  const vh = typeof window !== 'undefined' ? window.innerHeight : 760;
  if (snap === 'peek') return 156;
  if (snap === 'full') return Math.round(vh * 0.88);
  return Math.round(vh * 0.56);
}

const mobileSheetStyle = computed<Record<string, string>>(() => {
  if (!isMobileViewport.value || !rightPanelOpen.value) return {};
  const heightPx =
    isDraggingSheet.value && draggingSheetHeightPx.value !== null
      ? draggingSheetHeightPx.value
      : getMobileDetentHeightPx(mobileSheetSnap.value);
  return { height: `${heightPx}px` };
});

function setMobileSheetSnap(snap: 'peek' | 'half' | 'full') {
  mobileSheetSnap.value = snap;
  nextTick(() => {
    mapInstance?.updateSize();
  });
}

function cycleMobileSheetSnap() {
  if (mobileSheetSnap.value === 'peek') {
    setMobileSheetSnap('half');
  } else if (mobileSheetSnap.value === 'half') {
    setMobileSheetSnap('full');
  } else {
    setMobileSheetSnap('peek');
  }
}

function openWorkspaceTab(tab: 'identitas' | 'rute' | 'analisa') {
  activeLeftSection.value = tab;
  if (isMobileViewport.value && mobileSheetSnap.value === 'peek') {
    setMobileSheetSnap('half');
  }
}

function selectMapClickTarget(target: 'awal' | 'akhir') {
  clickTarget.value = target;
  if (isMobileViewport.value && mobileSheetSnap.value === 'full') {
    setMobileSheetSnap('peek');
  }
}

function onSheetHandlePointerDown(event: PointerEvent) {
  if (!isMobileViewport.value) return;
  const target = event.currentTarget as HTMLElement | null;
  target?.setPointerCapture?.(event.pointerId);
  sheetDragStartY = event.clientY;
  sheetDragStartHeight =
    sheetPanelRef.value?.getBoundingClientRect().height ??
    getMobileDetentHeightPx(mobileSheetSnap.value);
  sheetDragMoved = false;
  isDraggingSheet.value = true;
  draggingSheetHeightPx.value = Math.round(sheetDragStartHeight);
}

function onSheetHandlePointerMove(event: PointerEvent) {
  if (!isDraggingSheet.value || !isMobileViewport.value) return;
  const deltaY = event.clientY - sheetDragStartY;
  if (Math.abs(deltaY) > 6) {
    sheetDragMoved = true;
  }
  const vh = typeof window !== 'undefined' ? window.innerHeight : 760;
  const maxHeight = Math.round(vh * 0.9);
  const nextHeight = Math.max(96, Math.min(maxHeight, Math.round(sheetDragStartHeight - deltaY)));
  draggingSheetHeightPx.value = nextHeight;
}

function onSheetHandlePointerUp(event: PointerEvent) {
  if (!isDraggingSheet.value) return;
  const target = event.currentTarget as HTMLElement | null;
  if (target?.hasPointerCapture?.(event.pointerId)) {
    target.releasePointerCapture(event.pointerId);
  }
  const finalHeight = draggingSheetHeightPx.value ?? sheetDragStartHeight;
  isDraggingSheet.value = false;
  draggingSheetHeightPx.value = null;

  if (!sheetDragMoved) {
    cycleMobileSheetSnap();
    return;
  }

  if (finalHeight < 118) {
    toggleRightPanel(false);
    return;
  }

  const peekH = getMobileDetentHeightPx('peek');
  const halfH = getMobileDetentHeightPx('half');
  const fullH = getMobileDetentHeightPx('full');
  const distPeek = Math.abs(finalHeight - peekH);
  const distHalf = Math.abs(finalHeight - halfH);
  const distFull = Math.abs(finalHeight - fullH);

  if (distFull <= distHalf && distFull <= distPeek) {
    setMobileSheetSnap('full');
  } else if (distPeek <= distHalf) {
    setMobileSheetSnap('peek');
  } else {
    setMobileSheetSnap('half');
  }
}

function toggleRightPanel(open?: boolean) {
  rightPanelOpen.value = typeof open === 'boolean' ? open : !rightPanelOpen.value;
  if (isMobileViewport.value) {
    mobileSheetSnap.value = 'half';
  }
  nextTick(() => {
    mapInstance?.updateSize();
  });
}

// Map state
const mapContainer = ref<HTMLElement | null>(null);
type BasemapKey = 'esri' | 'rbi' | 'osm' | 'google_hybrid' | 'google_earth';
const basemapOptions: { id: BasemapKey; label: string }[] = [
  { id: 'esri', label: 'Esri' },
  { id: 'rbi', label: 'RBI' },
  { id: 'osm', label: 'OSM' },
  { id: 'google_hybrid', label: 'Google Hybrid' },
  { id: 'google_earth', label: 'Google Earth' },
];
const activeBasemap = ref<BasemapKey>('osm');
const showLayerUsulan = ref(true);
const showLayerJalanPoros = ref(true);
const showLayerJalanKabupaten = ref(true);
const showLayerSegmen = ref(true);
const hoverCoords = ref<{ lat: number; lng: number } | null>(null);

const isReshapeMode = ref(false);
const isManualDrawMode = ref(false);
const manualDrawVertexCount = ref(0);
const manualDrawLengthM = ref(0);
const isReshapeSnapEnabled = ref(true);
const hasUnanalyzedManualEdit = ref(false);
const reshapeHistory = ref<number[][][]>([]);
const reshapeBaselineCoords = ref<number[][] | null>(null);

const rawLayerData = reactive<{
  jalan_poros_desa: any;
  jaringan_jalan_ruas: any;
  jalan_kabupaten: any;
  infrastruktur_segmen: any;
  batas_desa: any;
  batas_desa_bbox: number[] | null;
}>({
  jalan_poros_desa: null,
  jaringan_jalan_ruas: null,
  jalan_kabupaten: null,
  infrastruktur_segmen: null,
  batas_desa: null,
  batas_desa_bbox: null,
});

const runtimeConfig = useRuntimeConfig();
const martinUrl = computed(() => String(runtimeConfig.public.martinUrl || '/martin').replace(/\/+$/, ''));

const isBappedaOrAdmin = computed(() => auth.hasRole('admin') || auth.hasRole('verifierBappeda'));

const promotionForm = reactive({
  nama_ruas: '',
  hierarki_jalan: 'lingkungan' as HierarkiJalanBkkd,
  keputusan: 'setujui_baru' as 'setujui_baru' | 'ekstensi_potong_unik' | 'tolak',
  catatan: '',
  submitting: false,
});

watch(
  activeLocation,
  (lok) => {
    if (!lok) return;
    promotionForm.nama_ruas = lok.nama_ruas_usulan || lok.nama_lokasi || '';
    promotionForm.hierarki_jalan = (lok.hierarki_jalan_usulan as HierarkiJalanBkkd) || 'lingkungan';
    promotionForm.keputusan =
      lok.validasi_promosi_spasial?.rekomendasi_aksi === 'ekstensi_ruas'
        ? 'ekstensi_potong_unik'
        : 'setujui_baru';
    promotionForm.catatan = lok.catatan_promosi_master || '';
  },
  { immediate: true }
);

let mapInstance: any = null;
let olModules: any = null;
let tileLayers: Record<string, any> = {};
let batasDesaSource: any = null;
let jalanPorosSource: any = null;
let jalanKabupatenSource: any = null;
let segmenSource: any = null;
let batasDesaMvtLayer: any = null;
let jalanPorosMvtLayer: any = null;
let jalanKabupatenMvtLayer: any = null;
let segmenMvtLayer: any = null;
let usulanSource: any = null;
let overlapHighlightSource: any = null;
let reshapeSource: any = null;
let markerSource: any = null;
let modifyInteraction: any = null;
let drawInteraction: any = null;
let snapPorosInteraction: any = null;
let snapSegmenInteraction: any = null;
let activeReshapeGeomListener: (() => void) | null = null;
let activeDrawGeomListener: (() => void) | null = null;

useHead({
  title: computed(() =>
    isCreateMode.value
      ? 'Workspace Analisis Proposal Baru | BKKD Bojonegoro'
      : `Analisis Proposal ${proposal.value?.nomor_registrasi || ''} | BKKD Bojonegoro`
  ),
});

// ─── Formatters & Helpers ──────────────────────────────────────────────────
function formatNumber(val?: number | null, maxDecimals = 2): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return '-';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maxDecimals }).format(Number(val));
}

function formatRupiah(val?: number | null): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(val));
}

function getSpatialStatusMeta(status?: StatusAnalisaSpasialBkkd | string | null) {
  switch (status) {
    case 'aman':
      return {
        label: 'Aman (Koridor Baru)',
        badgeClass:
          'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
        strokeColor: '#059669',
      };
    case 'lanjutan_ruas':
      return {
        label: 'Lanjutan Ruas Eksisting',
        badgeClass:
          'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800',
        strokeColor: '#0284c7',
      };
    case 'konfirmasi_belum_tuntas':
      return {
        label: 'Perlu Konfirmasi (Irisan Parsial)',
        badgeClass:
          'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
        strokeColor: '#d97706',
      };
    case 'duplikasi_tuntas':
      return {
        label: 'Indikasi Tumpang Tindih (>90%)',
        badgeClass:
          'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
        strokeColor: '#e11d48',
      };
    case 'kewenangan_kabupaten':
      return {
        label: 'Aset Kabupaten (Bukan Aset Desa)',
        badgeClass:
          'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
        strokeColor: '#dc2626',
      };
    default:
      return {
        label: 'Belum Dianalisa',
        badgeClass:
          'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        strokeColor: '#475569',
      };
  }
}

function getSumberRuteMeta(sumber?: SumberRuteBkkd | string | null) {
  switch (sumber) {
    case 'jalan_porosdesa':
      return {
        label: 'Koridor Jalan Poros Desa',
        badgeClass:
          'bg-indigo-50 text-indigo-800 border-indigo-300 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800',
      };
    case 'manual':
      return {
        label: 'Reshape Manual',
        badgeClass:
          'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
      };
    case 'openrouteservice':
      return {
        label: 'OpenRouteService API',
        badgeClass:
          'bg-teal-50 text-teal-800 border-teal-300 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-800',
      };
    default:
      return {
        label: 'Garis Koordinat Langsung',
        badgeClass:
          'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
      };
  }
}

function extractCoordsArray(geojson: any): number[][] {
  if (!geojson) return [];
  const parsed = typeof geojson === 'string' ? JSON.parse(geojson) : geojson;
  const geom = parsed?.geometry || parsed;
  if (!geom || !Array.isArray(geom.coordinates)) return [];
  if (geom.type === 'LineString') {
    return geom.coordinates.map((pt: any) => [Number(pt[0]), Number(pt[1])]);
  }
  if (geom.type === 'MultiLineString' && Array.isArray(geom.coordinates[0])) {
    return geom.coordinates.flatMap((line: any[]) =>
      line.map((pt: any) => [Number(pt[0]), Number(pt[1])])
    );
  }
  return [];
}

const activeVertexCount = computed(() => {
  return extractCoordsArray(activeLocation.value?.geojson).length;
});

const totalPanjangGis = computed(() => {
  return locations.value.reduce((acc, item) => acc + (Number(item.panjang_gis_m) || 0), 0);
});

const overallStatus = computed<StatusAnalisaSpasialBkkd>(() => {
  const statuses = locations.value.map((l) => l.status_analisa_spasial);
  if (statuses.includes('duplikasi_tuntas')) return 'duplikasi_tuntas';
  if (statuses.includes('kewenangan_kabupaten')) return 'kewenangan_kabupaten';
  if (statuses.includes('konfirmasi_belum_tuntas')) return 'konfirmasi_belum_tuntas';
  if (statuses.includes('lanjutan_ruas')) return 'lanjutan_ruas';
  if (statuses.includes('aman')) return 'aman';
  return 'belum_dianalisa';
});

function extractEndpointsFromGeoJson(geojson: any): {
  awal: { lat: number; lng: number } | null;
  akhir: { lat: number; lng: number } | null;
} {
  if (!geojson) return { awal: null, akhir: null };
  const parsed = typeof geojson === 'string' ? JSON.parse(geojson) : geojson;
  const coords = parsed?.coordinates || parsed?.geometry?.coordinates || [];
  if (!Array.isArray(coords) || coords.length < 2) return { awal: null, akhir: null };
  const first = coords[0];
  const last = coords[coords.length - 1];
  if (!Array.isArray(first) || !Array.isArray(last)) return { awal: null, akhir: null };
  return {
    awal: { lng: Number(first[0]), lat: Number(first[1]) },
    akhir: { lng: Number(last[0]), lat: Number(last[1]) },
  };
}

function mapApiLokasiToWorkspaceItem(lok: BkkdProposalLokasi, idx: number): WorkspaceLocationItem {
  const endpoints = extractEndpointsFromGeoJson(lok.geojson);
  const sumberRute =
    (lok.ringkasan_analisa?.sumber_rute as SumberRuteBkkd) ||
    (lok.id_jalan_poros || lok.ringkasan_analisa?.terhubung_jalan_poros
      ? 'jalan_porosdesa'
      : 'openrouteservice');

  return {
    localId: lok.id || `lok-${idx}-${Date.now()}`,
    id: lok.id,
    urutan: lok.urutan || idx + 1,
    nama_lokasi: lok.nama_lokasi,
    id_jalan_poros: lok.id_jalan_poros || null,
    id_jaringan_ruas: lok.id_jaringan_ruas || null,
    nama_ruas:
      lok.jaringan_ruas?.nama_ruas ||
      lok.jalan_poros?.nama_ruas ||
      lok.ringkasan_analisa?.referensi_jalan_poros?.[0]?.nama_ruas ||
      null,
    nama_ruas_usulan: lok.nama_ruas_usulan || null,
    hierarki_jalan_usulan: lok.hierarki_jalan_usulan || 'lingkungan',
    sumber_rute: sumberRute,
    titik_awal: endpoints.awal,
    titik_akhir: endpoints.akhir,
    panjang_gis_m: Number(lok.panjang_gis_m || 0),
    panjang_rencana_m: lok.panjang_rencana_m !== null && lok.panjang_rencana_m !== undefined ? Number(lok.panjang_rencana_m) : Number(lok.panjang_gis_m || 0),
    lebar_m: normalizeOptionalLebar(lok.lebar_m),
    geojson: typeof lok.geojson === 'string' ? JSON.parse(lok.geojson) : lok.geojson,
    status_analisa_spasial: lok.status_analisa_spasial || 'belum_dianalisa',
    status_promosi_master: lok.status_promosi_master || 'belum_dievaluasi',
    ringkasan_analisa: lok.ringkasan_analisa || null,
    validasi_promosi_spasial: lok.validasi_promosi_spasial || null,
    catatan_promosi_master: lok.catatan_promosi_master || null,
    jaringan_ruas: lok.jaringan_ruas || null,
    snapshot_data_url: null,
  };
}

// ─── Data Loading ──────────────────────────────────────────────────────────
let isHydratingProposal = false;

async function loadMasterReferences() {
  try {
    const [bidangRes] = await Promise.all([
      bkkdApi.fetchRefBidang(),
      wilayahStore.getKecamatanList(),
    ]);
    bidangList.value = bidangRes.data || [];
    if (!form.id_bidang && bidangList.value.length > 0) {
      form.id_bidang = bidangList.value[0]?.id || '';
    }
  } catch (err) {
    console.error('Failed loading master references:', err);
  }
}

function fitMapToKecamatan(idKecamatan: number | null) {
  if (!mapInstance || !olModules || !idKecamatan) return;
  const matched = wilayahStore.kecamatanList.find((k) => Number(k.id) === Number(idKecamatan));
  const bbox = matched?.bbox;
  if (
    Array.isArray(bbox) &&
    bbox.length === 4 &&
    bbox.every((v: any) => Number.isFinite(Number(v))) &&
    Math.abs(Number(bbox[2]) - Number(bbox[0])) > 0.0001
  ) {
    try {
      const extent = olModules.transformExtent(
        bbox.map((v: any) => Number(v)),
        'EPSG:4326',
        'EPSG:3857'
      );
      mapInstance.getView().fit(extent, {
        padding: getMapFitPadding(),
        maxZoom: 14,
        duration: 300,
      });
    } catch (e) {
      console.warn('Failed fitting map to kecamatan bbox:', e);
    }
  }
}

async function loadDesaSpatialContext(idDesa: number | null, fitToDesa = false) {
  if (!idDesa) {
    jalanPorosOptions.value = [];
    rawLayerData.jalan_poros_desa = null;
    rawLayerData.jaringan_jalan_ruas = null;
    rawLayerData.jalan_kabupaten = null;
    rawLayerData.infrastruktur_segmen = null;
    rawLayerData.batas_desa = null;
    rawLayerData.batas_desa_bbox = null;
    renderAllMapLayers();
    return;
  }
  try {
    const res = await bkkdApi.fetchRefJalanPoros(idDesa, 'hybrid');
    jalanPorosOptions.value = res.data || [];
    if (res.layers) {
      rawLayerData.jalan_poros_desa = res.layers.jalan_poros_desa || null;
      rawLayerData.jaringan_jalan_ruas = res.layers.jaringan_jalan_ruas || null;
      rawLayerData.jalan_kabupaten = res.layers.jalan_kabupaten || null;
      rawLayerData.infrastruktur_segmen = res.layers.infrastruktur_segmen || null;
      rawLayerData.batas_desa = res.layers.batas_desa || null;
      rawLayerData.batas_desa_bbox = Array.isArray(res.layers.batas_desa_bbox) ? res.layers.batas_desa_bbox : null;
    }
    renderAllMapLayers();
    if (fitToDesa && locations.value.length === 0) {
      fitMapToDesaOrLayers();
    }
  } catch (err) {
    console.error('Failed loading desa spatial layers:', err);
  }
}

async function handleFormWilayahChange(val: { id_kecamatan: number | null; id_desa: number | null }) {
  const prevDesa = form.id_desa;
  form.id_kecamatan = val.id_kecamatan;
  form.id_desa = val.id_desa;

  if (val.id_desa) {
    if (val.id_desa !== prevDesa) {
      await loadDesaSpatialContext(val.id_desa, true);
    }
    return;
  }

  await loadDesaSpatialContext(null, false);
  if (val.id_kecamatan && locations.value.length === 0) {
    fitMapToKecamatan(val.id_kecamatan);
  }
}

async function loadExistingProposal() {
  if (isCreateMode.value) {
    isHydratingProposal = true;
    try {
      const isUnrestricted = auth.hasRole('admin') || auth.hasRole('verifierBappeda');
      if (!isUnrestricted && auth.user?.id_kecamatan) {
        form.id_kecamatan = Number(auth.user.id_kecamatan);
        await wilayahStore.getDesaList(form.id_kecamatan);
      }
      if (!isUnrestricted && auth.user?.id_desa) {
        form.id_desa = Number(auth.user.id_desa);
        await loadDesaSpatialContext(form.id_desa, true);
      } else if (!isUnrestricted && form.id_kecamatan) {
        fitMapToKecamatan(form.id_kecamatan);
      }
      activeLeftSection.value = 'identitas';
    } finally {
      isHydratingProposal = false;
    }
    return;
  }

  loadingPage.value = true;
  pageError.value = null;
  isHydratingProposal = true;
  try {
    const [propRes, lokRes] = await Promise.all([
      bkkdApi.getProposal(proposalId.value),
      bkkdApi.fetchProposalLokasi(proposalId.value, 'hybrid'),
    ]);

    proposal.value = propRes.data;
    form.tahun_anggaran = propRes.data.tahun_anggaran;
    form.tagging_usulan = propRes.data.tagging_usulan === 'RKPD' ? 'RKPD' : 'PRKPD';
    form.id_kecamatan = Number(propRes.data.id_kecamatan);
    await wilayahStore.getDesaList(form.id_kecamatan);
    form.id_desa = Number(propRes.data.id_desa);
    form.id_bidang = propRes.data.id_bidang;
    form.judul_kegiatan = propRes.data.judul_kegiatan;
    form.lokasi_kegiatan = propRes.data.lokasi_kegiatan || '';
    form.nilai_usulan = propRes.data.nilai_usulan ?? null;
    form.uraian_kegiatan = propRes.data.uraian_kegiatan || '';
    form.catatan_konfirmasi_desa = propRes.data.catatan_konfirmasi_desa || '';

    jalanPorosOptions.value = lokRes.jalan_poros_options || [];
    if (lokRes.layers) {
      rawLayerData.jalan_poros_desa = lokRes.layers.jalan_poros_desa || null;
      rawLayerData.jaringan_jalan_ruas = lokRes.layers.jaringan_jalan_ruas || null;
      rawLayerData.jalan_kabupaten = lokRes.layers.jalan_kabupaten || null;
      rawLayerData.infrastruktur_segmen = lokRes.layers.infrastruktur_segmen || null;
      rawLayerData.batas_desa = lokRes.layers.batas_desa || null;
      rawLayerData.batas_desa_bbox = Array.isArray(lokRes.layers.batas_desa_bbox) ? lokRes.layers.batas_desa_bbox : null;
    }

    const mapped = (lokRes.data || []).map((lok, idx) => mapApiLokasiToWorkspaceItem(lok, idx));
    locations.value = mapped;
    dirtyLocationIds.value = {};
    hasUnanalyzedManualEdit.value = false;
    if (mapped.length > 0) {
      selectedLocalId.value = mapped[0]?.localId || null;
      populateEditBuilderFromLocation(mapped[0]!, false);
      routeSubView.value = 'list';
      activeLeftSection.value = 'analisa';
    } else {
      routeFormMode.value = 'create';
      routeSubView.value = 'create';
      activeLeftSection.value = 'rute';
    }

    await nextTick();
    renderAllMapLayers();
    fitMapToAllLocations();
    await generateAllSnapshots();
  } catch (err: any) {
    pageError.value = err?.data?.message || 'Gagal memuat data proposal dan analisa spasial.';
  } finally {
    isHydratingProposal = false;
    loadingPage.value = false;
  }
}

watch(
  () => form.id_desa,
  async (newDesa, oldDesa) => {
    if (isHydratingProposal) return;
    if (newDesa && newDesa !== oldDesa) {
      await loadDesaSpatialContext(newDesa, true);
    } else if (!newDesa && oldDesa) {
      await loadDesaSpatialContext(null, false);
    }
  }
);

// ─── OpenLayers Interactive Map & Snapshot Generator ───────────────────────
async function initMap() {
  if (!import.meta.client || !mapContainer.value || mapInstance) return;

  const [
    { default: Map },
    { default: View },
    { default: TileLayer },
    { default: VectorLayer },
    { default: VectorTileLayer },
    { default: XYZ },
    { default: VectorSource },
    { default: VectorTileSource },
    { default: GeoJSON },
    { default: MVT },
    { Style, Stroke, Fill, Circle: CircleStyle, Text },
    { fromLonLat, toLonLat, transformExtent },
    { default: Modify },
    { default: Draw },
    { default: Snap },
    { MultiPoint },
    { default: Feature },
    { getLength },
  ] = await Promise.all([
    import('ol/Map'),
    import('ol/View'),
    import('ol/layer/Tile'),
    import('ol/layer/Vector'),
    import('ol/layer/VectorTile'),
    import('ol/source/XYZ'),
    import('ol/source/Vector'),
    import('ol/source/VectorTile'),
    import('ol/format/GeoJSON'),
    import('ol/format/MVT'),
    import('ol/style'),
    import('ol/proj'),
    import('ol/interaction/Modify'),
    import('ol/interaction/Draw'),
    import('ol/interaction/Snap'),
    import('ol/geom'),
    import('ol/Feature'),
    import('ol/sphere'),
  ]);

  olModules = {
    GeoJSON,
    Style,
    Stroke,
    Fill,
    CircleStyle,
    Text,
    fromLonLat,
    toLonLat,
    transformExtent,
    Modify,
    Draw,
    Snap,
    MultiPoint,
    Feature,
    getLength,
  };

  tileLayers = {
    esri: new TileLayer({
      visible: activeBasemap.value === 'esri',
      source: new XYZ({
        url: 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
        crossOrigin: 'anonymous',
        maxZoom: 19,
      }),
    }),
    rbi: new TileLayer({
      visible: activeBasemap.value === 'rbi',
      source: new XYZ({
        url: '/rbi-tiles/{z}/{y}/{x}',
        crossOrigin: 'anonymous',
        maxZoom: 18,
        tileLoadFunction: (imageTile: any, src: string) => {
          const img = imageTile.getImage();
          img.crossOrigin = 'anonymous';
          let hasRetried = false;
          img.onerror = () => {
            if (!hasRetried && src.includes('/rbi-tiles/')) {
              hasRetried = true;
              img.src = src.replace(
                /^.*?\/rbi-tiles\//,
                'https://geoservices.big.go.id/rbi/rest/services/BASEMAP/Rupabumi_Indonesia/MapServer/tile/'
              );
            }
          };
          img.src = src;
        },
      }),
    }),
    osm: new TileLayer({
      visible: activeBasemap.value === 'osm',
      source: new XYZ({
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        crossOrigin: 'anonymous',
        maxZoom: 19,
      }),
    }),
    google_hybrid: new TileLayer({
      visible: activeBasemap.value === 'google_hybrid',
      source: new XYZ({
        url: 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
        crossOrigin: 'anonymous',
        maxZoom: 20,
      }),
    }),
    google_earth: new TileLayer({
      visible: activeBasemap.value === 'google_earth',
      source: new XYZ({
        url: 'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
        crossOrigin: 'anonymous',
        maxZoom: 20,
      }),
    }),
  };

  batasDesaSource = new VectorSource();
  jalanPorosSource = new VectorSource();
  jalanKabupatenSource = new VectorSource();
  segmenSource = new VectorSource();
  overlapHighlightSource = new VectorSource();
  usulanSource = new VectorSource();
  reshapeSource = new VectorSource();
  markerSource = new VectorSource();

  const makeMvtSource = (sourceId: string) =>
    new VectorTileSource({
      format: new MVT({ idProperty: 'id' }),
      url: `${martinUrl.value}/${sourceId}/{z}/{x}/{y}`,
      minZoom: 9,
      maxZoom: 19,
      cacheSize: 256,
    });

  const activeKecamatanName = computed(() => {
    if (!form.id_kecamatan) return '';
    const found = wilayahStore.kecamatanList.find((k: any) => Number(k.id) === Number(form.id_kecamatan));
    return String(found?.nama_kecamatan || proposal.value?.nama_kecamatan || '').trim().toLowerCase();
  });

  const extentsIntersect = (a: number[] | undefined, b: number[] | undefined): boolean => {
    if (!a || !b || a.length < 4 || b.length < 4) return false;
    return a[0]! <= b[2]! && a[2]! >= b[0]! && a[1]! <= b[3]! && a[3]! >= b[1]!;
  };

  const getActiveDesaExtent3857 = (): number[] | null => {
    if (!rawLayerData.batas_desa_bbox || rawLayerData.batas_desa_bbox.length !== 4) return null;
    const ext = transformExtent(rawLayerData.batas_desa_bbox as [number, number, number, number], 'EPSG:4326', 'EPSG:3857');
    if (!ext || !ext.every((v: number) => Number.isFinite(v))) return null;
    // Tambah toleransi 500 m di tepi batas desa agar jalan kabupaten perbatasan tetap terlihat
    return [ext[0]! - 500, ext[1]! - 500, ext[2]! + 500, ext[3]! + 500];
  };

  const matchesActiveWilayah = (feature: any, kind: 'desa' | 'poros' | 'segmen' | 'kabupaten'): boolean => {
    const activeDesaId = form.id_desa ? Number(form.id_desa) : null;
    const activeKecId = form.id_kecamatan ? Number(form.id_kecamatan) : null;

    if (kind === 'desa') {
      if (activeDesaId) {
        const fId = Number(feature.getId?.() ?? feature.get('id'));
        return Number.isFinite(fId) && fId === activeDesaId;
      }
      if (activeKecId) {
        return Number(feature.get('id_kecamatan')) === activeKecId;
      }
      return false;
    }

    if (kind === 'poros' || kind === 'segmen') {
      if (activeDesaId) {
        return Number(feature.get('id_desa')) === activeDesaId;
      }
      if (activeKecId) {
        return Number(feature.get('id_kecamatan')) === activeKecId;
      }
      return false;
    }

    // Jalan Kabupaten (jalan_kabupaten_luarkota) difilter berdasarkan nama_kecamatan hasil spatial join & BBOX desa aktif
    const rawKec = String(feature.get('nama_kecamatan') || '').trim();
    const targetKec = activeKecamatanName.value;
    if (targetKec && rawKec) {
      const kecTokens = rawKec
        .split(',')
        .map((s) => s.trim().toLowerCase())
        .filter(Boolean);
      if (!kecTokens.includes(targetKec)) {
        return false;
      }
    }

    const desaExt = getActiveDesaExtent3857();
    if (desaExt) {
      const fExt = feature.getGeometry?.()?.getExtent?.();
      return extentsIntersect(fExt, desaExt);
    }
    return Boolean(targetKec && rawKec);
  };

  batasDesaMvtLayer = new VectorTileLayer({
    source: makeMvtSource('bataswilayah_desa'),
    zIndex: 5,
    minZoom: 10,
    style: (feature: any) => {
      if (!matchesActiveWilayah(feature, 'desa')) return undefined;
      return new Style({
        stroke: new Stroke({
          color: 'rgba(100, 116, 139, 0.75)',
          width: 1.75,
          lineDash: [6, 6],
        }),
        fill: new Fill({
          color: 'rgba(148, 163, 184, 0.05)',
        }),
      });
    },
  });

  jalanPorosMvtLayer = new VectorTileLayer({
    source: makeMvtSource('jalan_porosdesa'),
    zIndex: 10,
    visible: showLayerJalanPoros.value,
    style: (feature: any) => {
      if (!matchesActiveWilayah(feature, 'poros')) return undefined;
      const nama = feature.get('nama_ruas') || '';
      const isPromoted = String(feature.get('sumber_data') || '') === 'promosi_proposal';
      return new Style({
        stroke: new Stroke({
          color: isPromoted ? '#0d9488' : '#6366f1',
          width: isPromoted ? 3 : 3.5,
          lineDash: isPromoted ? [6, 4] : [8, 5],
        }),
        text: nama
          ? new Text({
              text: String(nama),
              font: '600 10px Inter, sans-serif',
              placement: 'line',
              fill: new Fill({ color: isPromoted ? '#134e4a' : '#312e81' }),
              stroke: new Stroke({ color: '#ffffff', width: 3 }),
            })
          : undefined,
      });
    },
  });

  jalanKabupatenMvtLayer = new VectorTileLayer({
    source: makeMvtSource('jalan_kabupaten_luarkota'),
    zIndex: 12,
    visible: showLayerJalanKabupaten.value,
    style: (feature: any) => {
      if (!matchesActiveWilayah(feature, 'kabupaten')) return undefined;
      const nama = feature.get('nama_ruas') || '';
      return new Style({
        stroke: new Stroke({
          color: '#dc2626',
          width: 3.5,
        }),
        text: nama
          ? new Text({
              text: String(nama),
              font: '600 10px Inter, sans-serif',
              placement: 'line',
              fill: new Fill({ color: '#991b1b' }),
              stroke: new Stroke({ color: '#ffffff', width: 3 }),
            })
          : undefined,
      });
    },
  });

  segmenMvtLayer = new VectorTileLayer({
    source: makeMvtSource('infrastruktur_segmen'),
    zIndex: 15,
    visible: showLayerSegmen.value,
    style: (feature: any) => {
      if (!matchesActiveWilayah(feature, 'segmen')) return undefined;
      const kondisi = String(feature.get('kondisi') || '').toLowerCase();
      let color = '#10b981';
      if (kondisi.includes('berat')) color = '#ef4444';
      else if (kondisi.includes('sedang') || kondisi.includes('ringan')) color = '#f59e0b';
      return new Style({
        stroke: new Stroke({
          color,
          width: 4.5,
        }),
      });
    },
  });

  const batasDesaLayer = new VectorLayer({
    source: batasDesaSource,
    zIndex: 5,
    style: new Style({
      stroke: new Stroke({
        color: 'rgba(100, 116, 139, 0.7)',
        width: 2,
        lineDash: [6, 6],
      }),
      fill: new Fill({
        color: 'rgba(148, 163, 184, 0.05)',
      }),
    }),
  });

  const jalanPorosLayer = new VectorLayer({
    source: jalanPorosSource,
    zIndex: 10,
    style: (feature: any) => {
      const nama = feature.get('nama_ruas') || '';
      const isPromoted = Boolean(feature.get('is_jaringan_ruas'));
      return new Style({
        stroke: new Stroke({
          color: isPromoted ? '#0d9488' : '#6366f1',
          width: isPromoted ? 3.5 : 4,
          lineDash: isPromoted ? [6, 4] : [8, 5],
        }),
        text: nama
          ? new Text({
              text: String(nama),
              font: '600 10px Inter, sans-serif',
              placement: 'line',
              fill: new Fill({ color: isPromoted ? '#134e4a' : '#312e81' }),
              stroke: new Stroke({ color: '#ffffff', width: 3 }),
            })
          : undefined,
      });
    },
  });

  const jalanKabupatenLayer = new VectorLayer({
    source: jalanKabupatenSource,
    zIndex: 12,
    style: (feature: any) => {
      const nama = feature.get('nama_ruas') || '';
      return new Style({
        stroke: new Stroke({
          color: '#dc2626',
          width: 3.5,
        }),
        text: nama
          ? new Text({
              text: String(nama),
              font: '600 10px Inter, sans-serif',
              placement: 'line',
              fill: new Fill({ color: '#991b1b' }),
              stroke: new Stroke({ color: '#ffffff', width: 3 }),
            })
          : undefined,
      });
    },
  });

  const segmenLayer = new VectorLayer({
    source: segmenSource,
    zIndex: 15,
    style: (feature: any) => {
      const kondisi = String(feature.get('kondisi') || '').toLowerCase();
      let color = '#10b981';
      if (kondisi.includes('berat')) color = '#ef4444';
      else if (kondisi.includes('sedang') || kondisi.includes('ringan')) color = '#f59e0b';

      return new Style({
        stroke: new Stroke({
          color,
          width: 5,
        }),
      });
    },
  });

  const overlapLayer = new VectorLayer({
    source: overlapHighlightSource,
    zIndex: 22,
    style: new Style({
      stroke: new Stroke({
        color: 'rgba(244, 63, 94, 0.85)',
        width: 10,
      }),
    }),
  });

  const usulanLayer = new VectorLayer({
    source: usulanSource,
    zIndex: 25,
    style: (feature: any) => {
      const isSelected = feature.get('localId') === selectedLocalId.value;
      const status = feature.get('status_analisa_spasial');
      const statusMeta = getSpatialStatusMeta(status);
      const urutan = feature.get('urutan') || 1;
      const label = `#${urutan} ${feature.get('nama_lokasi') || ''}`;

      return [
        new Style({
          stroke: new Stroke({
            color: isSelected ? '#ffffff' : '#1e293b',
            width: isSelected ? 9 : 6.5,
          }),
        }),
        new Style({
          stroke: new Stroke({
            color: isSelected ? '#0284c7' : '#facc15',
            width: isSelected ? 5.5 : 4,
          }),
          text: new Text({
            text: label,
            font: '700 11px Inter, sans-serif',
            overflow: true,
            offsetY: -14,
            fill: new Fill({ color: '#0f172a' }),
            stroke: new Stroke({ color: '#ffffff', width: 3.5 }),
          }),
        }),
      ];
    },
  });

  const reshapeLayer = new VectorLayer({
    source: reshapeSource,
    zIndex: 32,
    style: (feature: any) => {
      const geom = feature.getGeometry();
      if (!geom) return [];
      const coords = geom.getCoordinates?.() || [];
      return [
        new Style({
          stroke: new Stroke({
            color: '#1e293b',
            width: 7,
          }),
        }),
        new Style({
          stroke: new Stroke({
            color: '#facc15',
            width: 4.5,
            lineDash: [8, 6],
          }),
        }),
        new Style({
          image: new CircleStyle({
            radius: 6,
            fill: new Fill({ color: '#facc15' }),
            stroke: new Stroke({ color: '#1e293b', width: 2 }),
          }),
          geometry: () => (Array.isArray(coords) && coords.length >= 2 ? new MultiPoint(coords) : geom),
        }),
      ];
    },
  });

  const markerLayer = new VectorLayer({
    source: markerSource,
    zIndex: 35,
    style: (feature: any) => {
      const role = feature.get('role');
      if (role === 'wp') {
        const isRoute = feature.get('is_route') !== false;
        return new Style({
          image: new CircleStyle({
            radius: 10,
            fill: new Fill({ color: isRoute ? '#0369a1' : '#64748b' }),
            stroke: new Stroke({ color: '#ffffff', width: 2.5 }),
          }),
          text: new Text({
            text: String(feature.get('label') || ''),
            font: '700 10px Inter, sans-serif',
            fill: new Fill({ color: '#ffffff' }),
          }),
        });
      }
      const isStart = role === 'awal';
      return new Style({
        image: new CircleStyle({
          radius: 9,
          fill: new Fill({ color: isStart ? '#059669' : '#e11d48' }),
          stroke: new Stroke({ color: '#ffffff', width: 2.5 }),
        }),
        text: new Text({
          text: isStart ? 'A' : 'B',
          font: '700 10px JetBrains Mono, monospace',
          fill: new Fill({ color: '#ffffff' }),
        }),
      });
    },
  });

  mapInstance = new Map({
    target: mapContainer.value,
    layers: [
      tileLayers.esri,
      tileLayers.rbi,
      tileLayers.osm,
      tileLayers.google_hybrid,
      tileLayers.google_earth,
      batasDesaMvtLayer,
      jalanPorosMvtLayer,
      jalanKabupatenMvtLayer,
      segmenMvtLayer,
      batasDesaLayer,
      jalanPorosLayer,
      jalanKabupatenLayer,
      segmenLayer,
      overlapLayer,
      usulanLayer,
      reshapeLayer,
      markerLayer,
    ],
    view: new View({
      center: fromLonLat([111.8815, -7.1502]),
      zoom: 12,
    }),
  });

  mapInstance.on('pointermove', (evt: any) => {
    if (evt.dragging) return;
    const [lng, lat] = toLonLat(evt.coordinate);
    hoverCoords.value = {
      lat: Number(lat.toFixed(6)),
      lng: Number(lng.toFixed(6)),
    };
  });

  mapInstance.on('singleclick', (evt: any) => {
    mobileMapMenuOpen.value = false;
    if (isReshapeMode.value || isManualDrawMode.value) {
      return;
    }
    // Check if user clicked on an existing usulan feature
    let clickedLocationId: string | null = null;
    mapInstance.forEachFeatureAtPixel(evt.pixel, (feat: any) => {
      if (feat.get('localId')) {
        clickedLocationId = feat.get('localId');
      }
    });

    if (clickedLocationId && !generatingRoute.value) {
      selectedLocalId.value = clickedLocationId;
      renderAllMapLayers();
    }

    // Plotting titik waypoint hanya aktif saat berada di sub-tab Tambah/Edit Lokasi
    if (activeLeftSection.value !== 'rute' || routeSubView.value === 'list') {
      return;
    }

    const [lng, lat] = toLonLat(evt.coordinate);
    const roundedLat = Number(lat.toFixed(6));
    const roundedLng = Number(lng.toFixed(6));

    if (!form.id_desa) {
      toast.add({
        title: 'Pilih Wilayah Desa Terlebih Dahulu',
        description: 'Silakan pilih Kecamatan dan Desa pada panel form di sisi kanan sebelum menentukan titik rute.',
        color: 'warning',
      });
      activeLeftSection.value = 'identitas';
      rightPanelOpen.value = true;
      return;
    }

    const targetDraft = getActiveRouteFormDraft();
    if (routeFormMode.value === 'create') {
      const emptyIdx = createWaypoints.value.findIndex((wp) => wp.lat === '' || wp.lng === '');
      if (emptyIdx !== -1) {
        createWaypoints.value[emptyIdx]!.lat = roundedLat;
        createWaypoints.value[emptyIdx]!.lng = roundedLng;
      } else {
        createWaypoints.value.push(newWaypoint(roundedLat, roundedLng));
      }
      renderBuilderMarkers();
      activeLeftSection.value = 'rute';
    } else if (clickTarget.value === 'awal') {
      targetDraft.lat_awal = roundedLat;
      targetDraft.lng_awal = roundedLng;
      clickTarget.value = 'akhir';
      renderBuilderMarkers();
      activeLeftSection.value = 'rute';
    } else {
      targetDraft.lat_akhir = roundedLat;
      targetDraft.lng_akhir = roundedLng;
      clickTarget.value = 'awal';
      renderBuilderMarkers();
      activeLeftSection.value = 'rute';
    }
  });

  renderAllMapLayers();
}

function setBasemap(mode: BasemapKey) {
  activeBasemap.value = mode;
  Object.entries(tileLayers).forEach(([key, layer]) => {
    layer.setVisible(key === mode);
  });
}

function renderAllMapLayers() {
  if (!mapInstance || !olModules) return;
  const format = new olModules.GeoJSON();

  batasDesaSource.clear();
  if (rawLayerData.batas_desa?.geometry) {
    try {
      const f = format.readFeature(rawLayerData.batas_desa, {
        dataProjection: 'EPSG:4326',
        featureProjection: 'EPSG:3857',
      });
      batasDesaSource.addFeature(f);
    } catch (e) {
      console.warn('Failed reading batas_desa geometry:', e);
    }
  }

  jalanPorosSource.clear();
  if (showLayerJalanPoros.value && rawLayerData.jalan_poros_desa?.features?.length) {
    try {
      const feats = format.readFeatures(rawLayerData.jalan_poros_desa, {
        dataProjection: 'EPSG:4326',
        featureProjection: 'EPSG:3857',
      });
      jalanPorosSource.addFeatures(feats);
    } catch (e) {
      console.warn('Failed reading jalan_poros_desa layer:', e);
    }
  }
  if (showLayerJalanPoros.value && rawLayerData.jaringan_jalan_ruas?.features?.length) {
    try {
      const jFeats = format.readFeatures(rawLayerData.jaringan_jalan_ruas, {
        dataProjection: 'EPSG:4326',
        featureProjection: 'EPSG:3857',
      });
      jFeats.forEach((f: any) => f.set('is_jaringan_ruas', true));
      jalanPorosSource.addFeatures(jFeats);
    } catch (e) {
      console.warn('Failed reading jaringan_jalan_ruas layer:', e);
    }
  }

  jalanKabupatenSource?.clear();
  if (showLayerJalanKabupaten.value && rawLayerData.jalan_kabupaten?.features?.length) {
    try {
      const kabFeats = format.readFeatures(rawLayerData.jalan_kabupaten, {
        dataProjection: 'EPSG:4326',
        featureProjection: 'EPSG:3857',
      });
      jalanKabupatenSource?.addFeatures(kabFeats);
    } catch (e) {
      console.warn('Failed reading jalan_kabupaten layer:', e);
    }
  }

  segmenSource.clear();
  if (showLayerSegmen.value && rawLayerData.infrastruktur_segmen?.features?.length) {
    try {
      const feats = format.readFeatures(rawLayerData.infrastruktur_segmen, {
        dataProjection: 'EPSG:4326',
        featureProjection: 'EPSG:3857',
      });
      segmenSource.addFeatures(feats);
    } catch (e) {
      console.warn('Failed reading infrastruktur_segmen layer:', e);
    }
  }

  usulanSource.clear();
  overlapHighlightSource.clear();

  if (showLayerUsulan.value) {
    locations.value.forEach((lok) => {
      if (!lok.geojson) return;
      try {
        const geomObj = typeof lok.geojson === 'string' ? JSON.parse(lok.geojson) : lok.geojson;
        const feat = format.readFeature(
          {
            type: 'Feature',
            geometry: geomObj.geometry || geomObj,
            properties: {
              localId: lok.localId,
              urutan: lok.urutan,
              nama_lokasi: lok.nama_lokasi,
              status_analisa_spasial: lok.status_analisa_spasial,
            },
          },
          {
            dataProjection: 'EPSG:4326',
            featureProjection: 'EPSG:3857',
          }
        );
        usulanSource.addFeature(feat);

        // Render intersection highlight for active location
        if (lok.localId === activeLocation.value?.localId && lok.ringkasan_analisa) {
          const segRefs = lok.ringkasan_analisa.referensi_infrastruktur_segmen || [];
          segRefs.forEach((seg) => {
            if (seg.irisan_geojson) {
              try {
                const overlapFeat = format.readFeature(
                  {
                    type: 'Feature',
                    geometry: seg.irisan_geojson,
                  },
                  {
                    dataProjection: 'EPSG:4326',
                    featureProjection: 'EPSG:3857',
                  }
                );
                overlapHighlightSource.addFeature(overlapFeat);
              } catch {
                // ignore non-line intersection points
              }
            }
          });
        }
      } catch (e) {
        console.warn('Failed rendering usulan feature:', e);
      }
    });
  }

  renderBuilderMarkers();
}

function renderBuilderMarkers() {
  if (!markerSource || !olModules) return;
  markerSource.clear();
  const format = new olModules.GeoJSON();
  const activeDraft = getActiveRouteFormDraft();

  if (routeFormMode.value === 'create') {
    createWaypoints.value.forEach((wp, idx) => {
      if (!isValidWaypoint(wp)) return;
      markerSource.addFeature(
        format.readFeature(
          {
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [Number(wp.lng), Number(wp.lat)] },
            properties: { role: 'wp', label: String(idx + 1), is_route: wp.is_route },
          },
          { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }
        )
      );
    });
    return;
  }
  const latA = Number(activeDraft.lat_awal);
  const lngA = Number(activeDraft.lng_awal);
  if (
    activeDraft.lat_awal !== '' &&
    activeDraft.lng_awal !== '' &&
    !Number.isNaN(latA) &&
    !Number.isNaN(lngA)
  ) {
    const fA = format.readFeature(
      {
        type: 'Feature',
        geometry: { type: 'Point', coordinates: [lngA, latA] },
        properties: { role: 'awal' },
      },
      { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }
    );
    markerSource.addFeature(fA);
  }

  const latB = Number(activeDraft.lat_akhir);
  const lngB = Number(activeDraft.lng_akhir);
  if (
    activeDraft.lat_akhir !== '' &&
    activeDraft.lng_akhir !== '' &&
    !Number.isNaN(latB) &&
    !Number.isNaN(lngB)
  ) {
    const fB = format.readFeature(
      {
        type: 'Feature',
        geometry: { type: 'Point', coordinates: [lngB, latB] },
        properties: { role: 'akhir' },
      },
      { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }
    );
    markerSource.addFeature(fB);
  }
}

watch(
  () => createWaypoints.value.map((wp) => [wp.lat, wp.lng, wp.is_route]),
  () => {
    renderBuilderMarkers();
  },
  { deep: true }
);

watch(
  () => [
    routeFormMode.value,
    createBuilder.lat_awal,
    createBuilder.lng_awal,
    createBuilder.lat_akhir,
    createBuilder.lng_akhir,
    editBuilder.lat_awal,
    editBuilder.lng_awal,
    editBuilder.lat_akhir,
    editBuilder.lng_akhir,
  ],
  () => {
    renderBuilderMarkers();
  }
);

watch(
  [showLayerUsulan, showLayerJalanPoros, showLayerJalanKabupaten, showLayerSegmen, selectedLocalId],
  () => {
    jalanPorosMvtLayer?.setVisible(showLayerJalanPoros.value);
    jalanKabupatenMvtLayer?.setVisible(showLayerJalanKabupaten.value);
    segmenMvtLayer?.setVisible(showLayerSegmen.value);
    renderAllMapLayers();
  }
);

watch(
  () => [form.id_kecamatan, form.id_desa, rawLayerData.batas_desa_bbox],
  () => {
    batasDesaMvtLayer?.changed();
    jalanPorosMvtLayer?.changed();
    jalanKabupatenMvtLayer?.changed();
    segmenMvtLayer?.changed();
  }
);

function getMapFitPadding(): [number, number, number, number] {
  if (typeof window === 'undefined') {
    return [70, 520, 70, 60];
  }
  if (window.innerWidth >= 1024) {
    return [76, rightPanelOpen.value ? 536 : 64, 72, 64];
  }
  if (window.innerWidth >= 640) {
    return [76, rightPanelOpen.value ? 456 : 48, 68, 48];
  }
  const bottomPad = rightPanelOpen.value
    ? getMobileDetentHeightPx(mobileSheetSnap.value) + 20
    : 72;
  return [96, 36, bottomPad, 36];
}

function fitMapToDesaOrLayers() {
  if (!mapInstance) return;
  if (rawLayerData.batas_desa_bbox && rawLayerData.batas_desa_bbox.length === 4 && olModules?.transformExtent) {
    const ext = olModules.transformExtent(rawLayerData.batas_desa_bbox, 'EPSG:4326', 'EPSG:3857');
    if (ext && ext.every((v: number) => Number.isFinite(v))) {
      mapInstance.getView().fit(ext, {
        padding: getMapFitPadding(),
        maxZoom: 16,
        duration: 300,
      });
      return;
    }
  }
  const sources = [batasDesaSource, jalanPorosSource, segmenSource];
  for (const src of sources) {
    if (src && src.getFeatures().length > 0) {
      const ext = src.getExtent();
      if (ext && ext.every((v: number) => Number.isFinite(v))) {
        mapInstance.getView().fit(ext, {
          padding: getMapFitPadding(),
          maxZoom: 16,
          duration: 300,
        });
        return;
      }
    }
  }
}

function fitMapToAllLocations() {
  if (!mapInstance) return;
  if (usulanSource && usulanSource.getFeatures().length > 0) {
    const ext = usulanSource.getExtent();
    if (ext && ext.every((v: number) => Number.isFinite(v))) {
      mapInstance.getView().fit(ext, {
        padding: getMapFitPadding(),
        maxZoom: 17,
        duration: 300,
      });
      return;
    }
  }
  fitMapToDesaOrLayers();
}

function focusLocationOnMap(lok: WorkspaceLocationItem) {
  if (isReshapeMode.value && selectedLocalId.value !== lok.localId) {
    stopReshapeMode(true);
  }
  selectedLocalId.value = lok.localId;
  hasUnanalyzedManualEdit.value = false;
  if (!mapInstance || !usulanSource) return;
  const feat = usulanSource
    .getFeatures()
    .find((f: any) => f.get('localId') === lok.localId);
  if (feat) {
    const ext = feat.getGeometry().getExtent();
    mapInstance.getView().fit(ext, {
      padding: getMapFitPadding(),
      maxZoom: 17,
      duration: 300,
    });
  }
}

const activeLocationIndex = computed(() => {
  if (!activeLocation.value) return -1;
  return locations.value.findIndex((item) => item.localId === activeLocation.value?.localId);
});

function stepActiveLocation(delta: -1 | 1) {
  if (locations.value.length === 0) return;
  const currentIdx = activeLocationIndex.value === -1 ? 0 : activeLocationIndex.value;
  const nextIdx = currentIdx + delta;
  if (nextIdx < 0 || nextIdx >= locations.value.length) return;
  const targetLok = locations.value[nextIdx];
  if (targetLok) {
    focusLocationOnMap(targetLok);
  }
}

// ─── Rendered Map Image Snapshot Generator (Gambar Peta Hasil Analisa) ─────
function generateLocationSnapshotCanvas(lok: WorkspaceLocationItem): string | null {
  if (!import.meta.client || !lok.geojson) return null;
  const width = 680;
  const height = 380;
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  if (!ctx) return null;

  const extractLineCoords = (geomInput: any): number[][][] => {
    if (!geomInput) return [];
    const g = typeof geomInput === 'string' ? JSON.parse(geomInput) : geomInput;
    const geom = g.geometry || g;
    if (!geom || !geom.coordinates) return [];
    if (geom.type === 'LineString') return [geom.coordinates];
    if (geom.type === 'MultiLineString') return geom.coordinates;
    return [];
  };

  const usulanLines = extractLineCoords(lok.geojson);
  if (!usulanLines.length || !usulanLines[0]?.length) return null;

  let minLng = Infinity;
  let maxLng = -Infinity;
  let minLat = Infinity;
  let maxLat = -Infinity;

  usulanLines.forEach((line) => {
    line.forEach((pt) => {
      const lng = Number(pt[0]);
      const lat = Number(pt[1]);
      if (lng < minLng) minLng = lng;
      if (lng > maxLng) maxLng = lng;
      if (lat < minLat) minLat = lat;
      if (lat > maxLat) maxLat = lat;
    });
  });

  const padLng = Math.max(0.0012, (maxLng - minLng) * 0.45);
  const padLat = Math.max(0.0012, (maxLat - minLat) * 0.45);
  minLng -= padLng;
  maxLng += padLng;
  minLat -= padLat;
  maxLat += padLat;

  const drawAreaTop = 44;
  const drawAreaBottom = height - 62;
  const drawAreaLeft = 24;
  const drawAreaRight = width - 24;
  const drawW = drawAreaRight - drawAreaLeft;
  const drawH = drawAreaBottom - drawAreaTop;

  const spanLng = Math.max(1e-6, maxLng - minLng);
  const spanLat = Math.max(1e-6, maxLat - minLat);
  const scale = Math.min(drawW / spanLng, drawH / spanLat);
  const offsetX = drawAreaLeft + (drawW - spanLng * scale) / 2;
  const offsetY = drawAreaTop + (drawH - spanLat * scale) / 2;

  const toXY = (lng: number, lat: number): [number, number] => {
    const x = offsetX + (lng - minLng) * scale;
    const y = offsetY + (maxLat - lat) * scale;
    return [x, y];
  };

  // 1. Cartographic background & coordinate grid
  ctx.fillStyle = '#0f172a';
  ctx.fillRect(0, 0, width, height);

  ctx.strokeStyle = 'rgba(148, 163, 184, 0.12)';
  ctx.lineWidth = 1;
  const gridStepX = width / 8;
  const gridStepY = height / 6;
  for (let gx = gridStepX; gx < width; gx += gridStepX) {
    ctx.beginPath();
    ctx.moveTo(gx, drawAreaTop);
    ctx.lineTo(gx, drawAreaBottom);
    ctx.stroke();
  }
  for (let gy = drawAreaTop; gy <= drawAreaBottom; gy += gridStepY) {
    ctx.beginPath();
    ctx.moveTo(0, gy);
    ctx.lineTo(width, gy);
    ctx.stroke();
  }

  // 2. Draw Jalan Poros Desa layer in viewport
  const porosFeatures = rawLayerData.jalan_poros_desa?.features || [];
  ctx.strokeStyle = '#818cf8';
  ctx.lineWidth = 3.5;
  ctx.setLineDash([8, 5]);
  porosFeatures.forEach((feat: any) => {
    const lines = extractLineCoords(feat.geometry);
    lines.forEach((line) => {
      if (line.length < 2) return;
      ctx.beginPath();
      line.forEach((pt, idx) => {
        const [x, y] = toXY(Number(pt[0]), Number(pt[1]));
        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();
    });
  });
  ctx.setLineDash([]);

  // 3. Draw Infrastruktur Segmen layer in viewport
  const segmenFeatures = rawLayerData.infrastruktur_segmen?.features || [];
  segmenFeatures.forEach((feat: any) => {
    const kondisi = String(feat.properties?.kondisi || '').toLowerCase();
    ctx.strokeStyle = kondisi.includes('berat')
      ? '#f87171'
      : kondisi.includes('sedang') || kondisi.includes('ringan')
        ? '#fbbf24'
        : '#34d399';
    ctx.lineWidth = 5;
    const lines = extractLineCoords(feat.geometry);
    lines.forEach((line) => {
      if (line.length < 2) return;
      ctx.beginPath();
      line.forEach((pt, idx) => {
        const [x, y] = toXY(Number(pt[0]), Number(pt[1]));
        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();
    });
  });

  // 4. Draw Overlap Highlight if any
  const overlapSegs = lok.ringkasan_analisa?.referensi_infrastruktur_segmen || [];
  overlapSegs.forEach((seg) => {
    if (!seg.irisan_geojson) return;
    const lines = extractLineCoords(seg.irisan_geojson);
    ctx.strokeStyle = 'rgba(244, 63, 94, 0.75)';
    ctx.lineWidth = 11;
    ctx.lineCap = 'round';
    lines.forEach((line) => {
      if (line.length < 2) return;
      ctx.beginPath();
      line.forEach((pt, idx) => {
        const [x, y] = toXY(Number(pt[0]), Number(pt[1]));
        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();
    });
  });

  // 5. Draw Proposal Usulan LineString (white halo + cyan core)
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  usulanLines.forEach((line) => {
    if (line.length < 2) return;
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 8.5;
    ctx.beginPath();
    line.forEach((pt, idx) => {
      const [x, y] = toXY(Number(pt[0]), Number(pt[1]));
      if (idx === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();

    ctx.strokeStyle = '#38bdf8';
    ctx.lineWidth = 5;
    ctx.beginPath();
    line.forEach((pt, idx) => {
      const [x, y] = toXY(Number(pt[0]), Number(pt[1]));
      if (idx === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();
  });

  // 6. Draw Start (A) and End (B) Pin Markers
  const firstLine = usulanLines[0]!;
  const ptStart = firstLine[0]!;
  const ptEnd = firstLine[firstLine.length - 1]!;
  const [ax, ay] = toXY(Number(ptStart[0]), Number(ptStart[1]));
  const [bx, by] = toXY(Number(ptEnd[0]), Number(ptEnd[1]));

  const drawMarkerPin = (x: number, y: number, label: string, fill: string) => {
    ctx.beginPath();
    ctx.arc(x, y, 10, 0, Math.PI * 2);
    ctx.fillStyle = fill;
    ctx.fill();
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = '#ffffff';
    ctx.stroke();

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 11px JetBrains Mono, monospace';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, x, y);
  };

  drawMarkerPin(ax, ay, 'A', '#059669');
  drawMarkerPin(bx, by, 'B', '#e11d48');

  // 7. Top Header Bar on Snapshot
  ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
  ctx.fillRect(0, 0, width, 40);
  ctx.fillStyle = '#f8fafc';
  ctx.font = '700 12px Inter, sans-serif';
  ctx.textAlign = 'left';
  ctx.textBaseline = 'middle';
  ctx.fillText(
    `PETA HASIL ANALISA SPASIAL - LOKASI #${lok.urutan}: ${lok.nama_lokasi.slice(0, 48)}`,
    14,
    20
  );

  const statusMeta = getSpatialStatusMeta(lok.status_analisa_spasial);
  ctx.fillStyle = statusMeta.strokeColor;
  ctx.font = '700 11px Inter, sans-serif';
  ctx.textAlign = 'right';
  ctx.fillText(statusMeta.label.toUpperCase(), width - 14, 20);

  // 8. Bottom Legend & Metrics Footer on Snapshot
  ctx.fillStyle = 'rgba(15, 23, 42, 0.94)';
  ctx.fillRect(0, height - 56, width, 56);

  ctx.textAlign = 'left';
  ctx.font = '600 11px JetBrains Mono, monospace';
  ctx.fillStyle = '#38bdf8';
  ctx.fillText(`Panjang Usulan: ${formatNumber(lok.panjang_gis_m)} m`, 14, height - 36);

  const irisanPoros = lok.ringkasan_analisa?.total_irisan_jalan_poros_m ?? 0;
  ctx.fillStyle = '#a5b4fc';
  ctx.fillText(`Koridor Jalan Poros: ${formatNumber(irisanPoros)} m`, 220, height - 36);

  const irisanSegmen = lok.ringkasan_analisa?.total_irisan_segmen_m ?? 0;
  ctx.fillStyle = '#34d399';
  ctx.fillText(`Irisan Segmen Eksisting: ${formatNumber(irisanSegmen)} m`, 445, height - 36);

  ctx.font = '500 10px JetBrains Mono, monospace';
  ctx.fillStyle = '#94a3b8';
  ctx.fillText(
    `Titik A: ${Number(ptStart[1]).toFixed(5)}, ${Number(ptStart[0]).toFixed(5)}  |  Titik B: ${Number(ptEnd[1]).toFixed(5)}, ${Number(ptEnd[0]).toFixed(5)}`,
    14,
    height - 16
  );

  return canvas.toDataURL('image/png');
}

async function generateAllSnapshots() {
  await nextTick();
  locations.value.forEach((lok) => {
    lok.snapshot_data_url = generateLocationSnapshotCanvas(lok);
  });
}

function downloadSnapshotPng(lok: WorkspaceLocationItem) {
  const dataUrl = lok.snapshot_data_url || generateLocationSnapshotCanvas(lok);
  if (!dataUrl) return;
  lok.snapshot_data_url = dataUrl;
  const link = document.createElement('a');
  link.href = dataUrl;
  const safeName = lok.nama_lokasi.replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 40);
  link.download = `analisa-spasial-lokasi-${lok.urutan}-${safeName}.png`;
  link.click();
}

// ─── Manual Geometry Reshape (Minimize OpenRouteService API Calls) ─────────
function detachReshapeInteractions() {
  if (activeReshapeGeomListener && reshapeSource) {
    const existingFeat = reshapeSource.getFeatures()?.[0];
    existingFeat?.getGeometry()?.un?.('change', activeReshapeGeomListener);
  }
  activeReshapeGeomListener = null;

  if (modifyInteraction && mapInstance) {
    mapInstance.removeInteraction(modifyInteraction);
  }
  modifyInteraction = null;

  if (drawInteraction && mapInstance) {
    mapInstance.removeInteraction(drawInteraction);
  }
  drawInteraction = null;
  activeDrawGeomListener = null;

  if (snapPorosInteraction && mapInstance) {
    mapInstance.removeInteraction(snapPorosInteraction);
  }
  snapPorosInteraction = null;

  if (snapSegmenInteraction && mapInstance) {
    mapInstance.removeInteraction(snapSegmenInteraction);
  }
  snapSegmenInteraction = null;
}

function syncReshapeSnapInteractions() {
  if (!mapInstance || !olModules) return;
  if (snapPorosInteraction) {
    mapInstance.removeInteraction(snapPorosInteraction);
    snapPorosInteraction = null;
  }
  if (snapSegmenInteraction) {
    mapInstance.removeInteraction(snapSegmenInteraction);
    snapSegmenInteraction = null;
  }
  if ((!isReshapeMode.value && !isManualDrawMode.value) || !isReshapeSnapEnabled.value) return;

  const { Snap } = olModules;
  if (jalanPorosSource) {
    snapPorosInteraction = new Snap({
      source: jalanPorosSource,
      pixelTolerance: 12,
    });
    mapInstance.addInteraction(snapPorosInteraction);
  }
  if (segmenSource) {
    snapSegmenInteraction = new Snap({
      source: segmenSource,
      pixelTolerance: 12,
    });
    mapInstance.addInteraction(snapSegmenInteraction);
  }
}

function toggleReshapeSnap() {
  isReshapeSnapEnabled.value = !isReshapeSnapEnabled.value;
  syncReshapeSnapInteractions();
}

function readReshapeCoords4326(olGeom: any): number[][] {
  if (!olModules || !olGeom) return [];
  const raw3857 = olGeom.getCoordinates?.() || [];
  return raw3857.map((pt: number[]) => {
    const [lng, lat] = olModules.toLonLat(pt);
    return [Number(lng.toFixed(6)), Number(lat.toFixed(6))];
  });
}

function applyCoordsToLocation(
  lok: WorkspaceLocationItem,
  coords4326: number[][],
  markUnanalyzed = true,
  syncReshapeFeature = false
) {
  if (!olModules || coords4326.length < 2) return;
  const format = new olModules.GeoJSON();
  const nextGeoJson = {
    type: 'LineString',
    coordinates: coords4326.map((pt) => [Number(pt[0]), Number(pt[1])]),
  };
  const olGeom = format.readGeometry(nextGeoJson, {
    dataProjection: 'EPSG:4326',
    featureProjection: 'EPSG:3857',
  });
  const lengthM = Math.round(olModules.getLength(olGeom) * 100) / 100;
  const firstPt = nextGeoJson.coordinates[0]!;
  const lastPt = nextGeoJson.coordinates[nextGeoJson.coordinates.length - 1]!;

  lok.geojson = nextGeoJson;
  lok.sumber_rute = 'manual';
  lok.panjang_gis_m = lengthM;
  lok.titik_awal = { lng: firstPt[0]!, lat: firstPt[1]! };
  lok.titik_akhir = { lng: lastPt[0]!, lat: lastPt[1]! };

  if (editBuilder.targetLocalId === lok.localId) {
    editBuilder.lat_awal = lok.titik_awal.lat;
    editBuilder.lng_awal = lok.titik_awal.lng;
    editBuilder.lat_akhir = lok.titik_akhir.lat;
    editBuilder.lng_akhir = lok.titik_akhir.lng;
  }

  hasUnanalyzedManualEdit.value = markUnanalyzed;
  dirtyLocationIds.value = { ...dirtyLocationIds.value, [lok.localId]: true };
  renderAllMapLayers();

  // Hanya ganti objek geometri OpenLayers saat Undo/Reset; jangan mengganti instance geom di tengah callback Modify
  if (syncReshapeFeature && isReshapeMode.value && reshapeSource) {
    const editFeat = reshapeSource.getFeatures()?.[0];
    if (editFeat) {
      if (activeReshapeGeomListener) {
        editFeat.getGeometry()?.un?.('change', activeReshapeGeomListener);
      }
      editFeat.setGeometry(olGeom);
      if (activeReshapeGeomListener) {
        olGeom.on('change', activeReshapeGeomListener);
      }
    }
  }
}

function stopManualDrawMode() {
  detachReshapeInteractions();
  if (reshapeSource) {
    reshapeSource.clear();
  }
  isManualDrawMode.value = false;
  manualDrawVertexCount.value = 0;
  manualDrawLengthM.value = 0;
}

function undoManualDrawPoint() {
  if (!isManualDrawMode.value || !drawInteraction) return;
  drawInteraction.removeLastPoint();
}

function finishManualDraw() {
  if (!isManualDrawMode.value || !drawInteraction) return;
  if (manualDrawVertexCount.value < 2) {
    toast.add({
      title: 'Minimal 2 Titik',
      description: 'Klik minimal 2 titik pada peta sebelum menyelesaikan gambar garis.',
      color: 'warning',
    });
    return;
  }
  drawInteraction.finishDrawing();
}

function startManualDrawMode() {
  if (!form.id_desa) {
    toast.add({
      title: 'Desa Belum Dipilih',
      description: 'Pilih Kecamatan dan Desa terlebih dahulu sebelum menggambar lokasi di peta.',
      color: 'warning',
    });
    activeLeftSection.value = 'identitas';
    rightPanelOpen.value = true;
    return;
  }

  if (!mapInstance || !olModules || !reshapeSource) return;

  if (isReshapeMode.value) {
    stopReshapeMode(false);
  }
  stopManualDrawMode();

  reshapeSource.clear();
  isManualDrawMode.value = true;
  manualDrawVertexCount.value = 0;
  manualDrawLengthM.value = 0;

  drawInteraction = new olModules.Draw({
    source: reshapeSource,
    type: 'LineString',
    style: [
      new olModules.Style({
        stroke: new olModules.Stroke({
          color: '#1e293b',
          width: 7,
        }),
      }),
      new olModules.Style({
        stroke: new olModules.Stroke({
          color: '#facc15',
          width: 4.5,
          lineDash: [8, 6],
        }),
        image: new olModules.CircleStyle({
          radius: 6,
          fill: new olModules.Fill({ color: '#facc15' }),
          stroke: new olModules.Stroke({ color: '#1e293b', width: 2 }),
        }),
      }),
    ],
  });

  drawInteraction.on('drawstart', (evt: any) => {
    const geom = evt.feature?.getGeometry?.();
    if (!geom) return;
    activeDrawGeomListener = () => {
      const coords = geom.getCoordinates?.() || [];
      manualDrawVertexCount.value = coords.length;
      manualDrawLengthM.value = olModules ? Math.round(olModules.getLength(geom) * 100) / 100 : 0;
    };
    geom.on('change', activeDrawGeomListener);
    activeDrawGeomListener();
  });

  drawInteraction.on('drawend', async (evt: any) => {
    const geom = evt.feature?.getGeometry?.();
    if (!geom || !olModules) {
      stopManualDrawMode();
      return;
    }

    const coords4326 = readReshapeCoords4326(geom);
    if (coords4326.length < 2) {
      stopManualDrawMode();
      return;
    }

    const lengthM = Math.round(olModules.getLength(geom) * 100) / 100;
    const first = coords4326[0]!;
    const last = coords4326[coords4326.length - 1]!;
    const baseName =
      createBuilder.nama_lokasi.trim() ||
      form.judul_kegiatan.trim() ||
      `Lokasi Ruas #${locations.value.length + 1}`;

    const newItem: WorkspaceLocationItem = {
      localId: `lok-${Date.now()}-manual`,
      urutan: locations.value.length + 1,
      nama_lokasi: baseName,
      id_jalan_poros: createBuilder.id_jalan_poros || null,
      nama_ruas: null,
      sumber_rute: 'manual',
      titik_awal: { lat: Number(first[1]!.toFixed(6)), lng: Number(first[0]!.toFixed(6)) },
      titik_akhir: { lat: Number(last[1]!.toFixed(6)), lng: Number(last[0]!.toFixed(6)) },
      panjang_gis_m: lengthM,
      panjang_rencana_m: createBuilder.panjang_rencana_m ?? lengthM,
      lebar_m: normalizeOptionalLebar(createBuilder.lebar_m),
      geojson: {
        type: 'LineString',
        coordinates: coords4326,
      },
      status_analisa_spasial: 'belum_dianalisa',
      ringkasan_analisa: null,
      snapshot_data_url: null,
    };

    stopManualDrawMode();
    locations.value.push(newItem);
    locations.value.forEach((item, idx) => {
      item.urutan = idx + 1;
    });
    selectedLocalId.value = newItem.localId;
    hasUnanalyzedManualEdit.value = true;
    dirtyLocationIds.value = { ...dirtyLocationIds.value, [newItem.localId]: true };

    resetCreateBuilder();
    routeSubView.value = 'list';
    renderAllMapLayers();
    await generateAllSnapshots();
    scrollPanelBodyToTop();

    toast.add({
      title: 'Garis Manual Berhasil Digambar',
      description: `Panjang ${formatNumber(lengthM)} m (${coords4326.length} titik). Klik "Analisa Ulang" untuk analisa spasial atau "Reshape" untuk koreksi titik.`,
      color: 'success',
    });
  });

  mapInstance.addInteraction(drawInteraction);
  syncReshapeSnapInteractions();

  if (isMobileViewport.value && rightPanelOpen.value) {
    mobileSheetSnap.value = 'peek';
  }

  toast.add({
    title: 'Mode Gambar Manual Aktif',
    description: 'Klik titik-titik jalur di peta. Klik ganda atau tekan "Selesai" untuk mengakhiri garis.',
    color: 'info',
  });
}

function startReshapeMode(targetLok?: WorkspaceLocationItem) {
  const lok = targetLok || activeLocation.value;
  if (!lok || !mapInstance || !olModules || !reshapeSource) {
    toast.add({
      title: 'Pilih atau Buat Lokasi Terlebih Dahulu',
      description: 'Tambahkan rute awal atau garis lurus manual sebelum melakukan reshape geometri.',
      color: 'warning',
    });
    return;
  }

  const coords = extractCoordsArray(lok.geojson);
  if (coords.length < 2) {
    toast.add({
      title: 'Geometri Tidak Valid',
      description: 'Garis lokasi minimal harus memiliki 2 titik koordinat.',
      color: 'warning',
    });
    return;
  }

  stopReshapeMode(false);
  selectedLocalId.value = lok.localId;
  populateEditBuilderFromLocation(lok, false);

  const normalizedCoords = coords.map((pt) => [Number(pt[0]), Number(pt[1])]);
  reshapeBaselineCoords.value = normalizedCoords.map((pt) => [...pt]);
  reshapeHistory.value = [];

  const format = new olModules.GeoJSON();
  const editGeom = format.readGeometry(
    { type: 'LineString', coordinates: normalizedCoords },
    { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }
  );
  const editFeat = new olModules.Feature({ geometry: editGeom });
  editFeat.set('localId', lok.localId);
  reshapeSource.clear();
  reshapeSource.addFeature(editFeat);

  isReshapeMode.value = true;

  modifyInteraction = new olModules.Modify({
    source: reshapeSource,
    pixelTolerance: 12,
  });

  modifyInteraction.on('modifystart', () => {
    const geom = editFeat.getGeometry();
    const current = readReshapeCoords4326(geom);
    if (current.length >= 2) {
      reshapeHistory.value = [...reshapeHistory.value.slice(-24), current];
    }
  });

  activeReshapeGeomListener = () => {
    const geom = editFeat.getGeometry();
    if (!geom || !olModules) return;
    lok.panjang_gis_m = Math.round(olModules.getLength(geom) * 100) / 100;
  };
  editGeom.on('change', activeReshapeGeomListener);

  modifyInteraction.on('modifyend', () => {
    const geom = editFeat.getGeometry();
    const nextCoords = readReshapeCoords4326(geom);
    if (nextCoords.length >= 2) {
      applyCoordsToLocation(lok, nextCoords, true, false);
    }
  });

  mapInstance.addInteraction(modifyInteraction);
  syncReshapeSnapInteractions();

  if (isMobileViewport.value && rightPanelOpen.value) {
    mobileSheetSnap.value = 'peek';
  }

  const ext = editGeom.getExtent();
  if (ext && ext.every((v: number) => Number.isFinite(v))) {
    mapInstance.getView().fit(ext, {
      padding: getMapFitPadding(),
      maxZoom: 18,
      duration: 260,
    });
  }

  toast.add({
    title: 'Mode Reshape Manual Aktif',
    description: 'Seret titik sudut atau tengah garis untuk koreksi lokasi tanpa memanggil OpenRouteService.',
    color: 'info',
  });
}

function flushActiveReshapeGeometry() {
  if (!isReshapeMode.value || !reshapeSource || !activeLocation.value) return;
  const editFeat = reshapeSource.getFeatures()?.[0];
  const geom = editFeat?.getGeometry?.();
  if (!geom) return;
  const nextCoords = readReshapeCoords4326(geom);
  if (nextCoords.length >= 2) {
    applyCoordsToLocation(activeLocation.value, nextCoords, hasUnanalyzedManualEdit.value, false);
  }
}

function stopReshapeMode(refreshSnapshot = true) {
  flushActiveReshapeGeometry();
  detachReshapeInteractions();
  if (reshapeSource) {
    reshapeSource.clear();
  }
  isReshapeMode.value = false;
  if (refreshSnapshot && activeLocation.value) {
    activeLocation.value.snapshot_data_url = generateLocationSnapshotCanvas(activeLocation.value);
  }
}

function undoReshapeStep() {
  const lok = activeLocation.value;
  if (!lok || reshapeHistory.value.length === 0) return;
  const previousCoords = reshapeHistory.value[reshapeHistory.value.length - 1]!;
  reshapeHistory.value = reshapeHistory.value.slice(0, -1);
  applyCoordsToLocation(lok, previousCoords, true, true);
}

function resetReshapeToBaseline() {
  const lok = activeLocation.value;
  if (!lok || !reshapeBaselineCoords.value || reshapeBaselineCoords.value.length < 2) return;
  const current = extractCoordsArray(lok.geojson);
  if (current.length >= 2) {
    reshapeHistory.value = [...reshapeHistory.value.slice(-24), current];
  }
  applyCoordsToLocation(lok, reshapeBaselineCoords.value, false, true);
}

function buildManualLineStringItem(
  draft: RouteFormDraft,
  existing?: WorkspaceLocationItem | null
): WorkspaceLocationItem {
  const latA = Number(draft.lat_awal);
  const lngA = Number(draft.lng_awal);
  const latB = Number(draft.lat_akhir);
  const lngB = Number(draft.lng_akhir);
  const baseName =
    draft.nama_lokasi.trim() ||
    form.judul_kegiatan.trim() ||
    `Lokasi Ruas #${existing ? existing.urutan : locations.value.length + 1}`;

  const geoJson = {
    type: 'LineString',
    coordinates: [
      [Number(lngA.toFixed(6)), Number(latA.toFixed(6))],
      [Number(lngB.toFixed(6)), Number(latB.toFixed(6))],
    ],
  };

  let lengthM = 0;
  if (olModules) {
    const format = new olModules.GeoJSON();
    const geom = format.readGeometry(geoJson, {
      dataProjection: 'EPSG:4326',
      featureProjection: 'EPSG:3857',
    });
    lengthM = Math.round(olModules.getLength(geom) * 100) / 100;
  }

  return {
    id: existing?.id,
    localId: existing?.localId || `lok-${Date.now()}-manual`,
    urutan: existing?.urutan ?? locations.value.length + 1,
    nama_lokasi: baseName,
    id_jalan_poros: draft.id_jalan_poros || null,
    nama_ruas: existing?.nama_ruas ?? null,
    sumber_rute: 'manual',
    titik_awal: { lat: latA, lng: lngA },
    titik_akhir: { lat: latB, lng: lngB },
    panjang_gis_m: lengthM,
    panjang_rencana_m: draft.panjang_rencana_m ?? lengthM,
    lebar_m: normalizeOptionalLebar(draft.lebar_m),
    geojson: geoJson,
    status_analisa_spasial: 'belum_dianalisa',
    ringkasan_analisa: null,
    snapshot_data_url: null,
  };
}

async function handleCreateManualStraightRoute() {
  if (!form.id_desa) {
    toast.add({
      title: 'Desa Belum Dipilih',
      description: 'Pilih Kecamatan dan Desa terlebih dahulu sebelum membuat garis manual.',
      color: 'warning',
    });
    activeLeftSection.value = 'identitas';
    return;
  }

  if (!isValidCoordinatePair(createBuilder)) {
    toast.add({
      title: 'Koordinat Awal & Akhir Belum Lengkap',
      description: 'Klik Titik Awal (A) dan Titik Akhir (B) pada peta atau isi koordinat pada Form Tambah Lokasi Baru.',
      color: 'warning',
    });
    return;
  }

  const newItem = buildManualLineStringItem(createBuilder, null);
  locations.value.push(newItem);
  locations.value.forEach((item, idx) => {
    item.urutan = idx + 1;
  });

  renderAllMapLayers();
  startReshapeMode(newItem);
  resetCreateBuilder();
  routeSubView.value = 'list';
  hasUnanalyzedManualEdit.value = true;
  dirtyLocationIds.value = { ...dirtyLocationIds.value, [newItem.localId]: true };
  scrollPanelBodyToTop();
}

async function handleResetEditToManualStraightRoute() {
  if (!form.id_desa) {
    toast.add({
      title: 'Desa Belum Dipilih',
      description: 'Pilih Kecamatan dan Desa terlebih dahulu.',
      color: 'warning',
    });
    activeLeftSection.value = 'identitas';
    return;
  }

  const targetIdx = editBuilder.targetLocalId
    ? locations.value.findIndex((item) => item.localId === editBuilder.targetLocalId)
    : -1;
  if (targetIdx === -1) {
    toast.add({
      title: 'Lokasi Edit Belum Dipilih',
      description: 'Pilih salah satu lokasi dari daftar di bawah dengan menekan tombol Edit.',
      color: 'warning',
    });
    return;
  }

  if (!isValidCoordinatePair(editBuilder)) {
    toast.add({
      title: 'Koordinat Edit Belum Lengkap',
      description: 'Pastikan Titik Awal (A) dan Titik Akhir (B) pada Form Edit Lokasi valid.',
      color: 'warning',
    });
    return;
  }

  const existing = locations.value[targetIdx]!;
  const updatedItem = buildManualLineStringItem(editBuilder, existing);
  locations.value.splice(targetIdx, 1, updatedItem);

  renderAllMapLayers();
  startReshapeMode(updatedItem);
  hasUnanalyzedManualEdit.value = true;
  dirtyLocationIds.value = { ...dirtyLocationIds.value, [updatedItem.localId]: true };
}

async function handleSaveSingleLocation(targetLok?: WorkspaceLocationItem) {
  const lok = targetLok || activeLocation.value;
  if (!lok) return;

  if (isCreateMode.value || !proposal.value) {
    toast.add({
      title: 'Simpan Proposal Terlebih Dahulu',
      description: 'Untuk proposal baru, gunakan tombol Simpan Proposal agar nomor registrasi dibuat terlebih dahulu.',
      color: 'warning',
    });
    return;
  }

  if (isReshapeMode.value && activeLocation.value?.localId === lok.localId) {
    stopReshapeMode(false);
  }

  savingLocationId.value = lok.localId;
  try {
    const payload = {
      nama_lokasi: lok.nama_lokasi.trim() || `Lokasi #${lok.urutan}`,
      urutan: lok.urutan,
      panjang_rencana_m: lok.panjang_rencana_m ?? lok.panjang_gis_m,
      lebar_m: normalizeOptionalLebar(lok.lebar_m),
      id_jalan_poros: lok.id_jalan_poros || null,
      sumber_rute: lok.sumber_rute,
      titik_sebaran: lok.ringkasan_analisa?.titik_sebaran || undefined,
      geojson: lok.geojson,
    };

    const targetIndex = locations.value.findIndex((item) => item.localId === lok.localId);

    if (lok.id) {
      const res = await bkkdApi.updateProposalLokasi(proposal.value.id, lok.id, payload);
      const updatedItem = mapApiLokasiToWorkspaceItem(res.data, Math.max(0, targetIndex));
      updatedItem.snapshot_data_url = generateLocationSnapshotCanvas(updatedItem);

      if (targetIndex !== -1) {
        locations.value.splice(targetIndex, 1, updatedItem);
      }
      selectedLocalId.value = updatedItem.localId;
      if (editBuilder.targetLocalId === updatedItem.localId) {
        populateEditBuilderFromLocation(updatedItem, routeFormMode.value === 'edit');
      }
      dirtyLocationIds.value = { ...dirtyLocationIds.value, [updatedItem.localId]: false };
    } else {
      const res = (await bkkdApi.createProposalLokasi(proposal.value.id, payload)) as any;
      const savedList = Array.isArray(res?.lokasi_list) && res.lokasi_list.length > 0
        ? res.lokasi_list
        : res?.data
          ? [res.data]
          : [];
      const mappedNew = savedList.map((apiLok: BkkdProposalLokasi, i: number) => {
        const item = mapApiLokasiToWorkspaceItem(apiLok, Math.max(0, targetIndex) + i);
        item.snapshot_data_url = generateLocationSnapshotCanvas(item);
        return item;
      });
      if (mappedNew.length > 0 && targetIndex !== -1) {
        locations.value.splice(targetIndex, 1, ...mappedNew);
        locations.value.forEach((item, idx) => {
          item.urutan = idx + 1;
        });
        selectedLocalId.value = mappedNew[0]!.localId;
        if (editBuilder.targetLocalId === lok.localId) {
          populateEditBuilderFromLocation(mappedNew[0]!, routeFormMode.value === 'edit');
        }
      }
      dirtyLocationIds.value = { ...dirtyLocationIds.value, [lok.localId]: false };
    }

    hasUnanalyzedManualEdit.value = false;
    renderAllMapLayers();

    try {
      const propRes = await bkkdApi.getProposal(proposal.value.id);
      proposal.value = propRes.data;
    } catch {
      // Abaikan kegagalan sinkronisasi header ringkasan jika lokasi sudah tersimpan
    }

    toast.add({
      title: `Lokasi #${lok.urutan} Berhasil Disimpan`,
      description: 'Perubahan geometri dan analisa spasial lokasi ini telah tersimpan tanpa menimpa lokasi lain.',
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Menyimpan Lokasi',
      description: err?.data?.message || 'Terjadi kesalahan saat menyimpan perubahan lokasi.',
      color: 'error',
    });
  } finally {
    savingLocationId.value = null;
  }
}

async function handleAnalyzeManualReshape() {
  flushActiveReshapeGeometry();
  const lok = activeLocation.value;
  if (!lok || !form.id_desa) {
    toast.add({
      title: 'Wilayah Desa Belum Dipilih',
      description: 'Pastikan Kecamatan dan Desa telah dipilih sebelum menjalankan analisa spasial.',
      color: 'warning',
    });
    return;
  }

  generatingRoute.value = true;
  try {
    const res = await bkkdApi.generateRoutePreview({
      id_desa: form.id_desa,
      id_proposal: proposal.value?.id || null,
      tahun_anggaran: form.tahun_anggaran,
      tagging_usulan: form.tagging_usulan,
      id_lokasi: lok.id ?? null,
      single_location_mode: true,
      nama_lokasi: lok.nama_lokasi,
      id_jalan_poros: lok.id_jalan_poros,
      lebar_m: lok.lebar_m,
      panjang_rencana_m: lok.panjang_rencana_m,
      sumber_rute: 'manual',
      preserve_manual_geometry: true,
      skip_ors: true,
      geojson: lok.geojson,
    });

    const analyzed = res.data || res.lokasi_list?.[0];
    if (analyzed) {
      lok.panjang_gis_m = Number(analyzed.panjang_gis_m || lok.panjang_gis_m);
      lok.status_analisa_spasial = analyzed.status_analisa_spasial || 'aman';
      lok.ringkasan_analisa = analyzed.ringkasan_analisa;
      lok.sumber_rute = 'manual';
      lok.snapshot_data_url = generateLocationSnapshotCanvas(lok);
      hasUnanalyzedManualEdit.value = false;
      renderAllMapLayers();

      toast.add({
        title: 'Analisa Geometri Manual Selesai',
        description: `Panjang ${formatNumber(lok.panjang_gis_m)} m dianalisa langsung tanpa OpenRouteService.`,
        color: 'success',
      });
    }
  } catch (err: any) {
    toast.add({
      title: 'Gagal Menganalisa Geometri Manual',
      description: err?.data?.message || 'Terjadi kesalahan saat menghitung irisan spasial.',
      color: 'error',
    });
  } finally {
    generatingRoute.value = false;
  }
}

// ─── Route Generation & Spatial Analysis Actions (Separated Create & Edit) ─
function isValidCoordinatePair(draft: RouteFormDraft): boolean {
  const latA = Number(draft.lat_awal);
  const lngA = Number(draft.lng_awal);
  const latB = Number(draft.lat_akhir);
  const lngB = Number(draft.lng_akhir);
  return (
    draft.lat_awal !== '' &&
    draft.lng_awal !== '' &&
    draft.lat_akhir !== '' &&
    draft.lng_akhir !== '' &&
    !Number.isNaN(latA) &&
    !Number.isNaN(lngA) &&
    !Number.isNaN(latB) &&
    !Number.isNaN(lngB) &&
    latA >= -90 &&
    latA <= 90 &&
    latB >= -90 &&
    latB <= 90 &&
    lngA >= -180 &&
    lngA <= 180 &&
    lngB >= -180 &&
    lngB <= 180
  );
}

let isHydratingEditBuilder = false;

function populateEditBuilderFromLocation(lok: WorkspaceLocationItem, switchToEditTab = true) {
  isHydratingEditBuilder = true;
  editBuilder.targetId = lok.id ?? null;
  editBuilder.targetLocalId = lok.localId;
  editBuilder.targetUrutan = lok.urutan;
  editBuilder.nama_lokasi = lok.nama_lokasi;
  editBuilder.id_jalan_poros = lok.id_jalan_poros || '';
  editBuilder.lebar_m = normalizeOptionalLebar(lok.lebar_m);
  editBuilder.panjang_rencana_m = lok.panjang_rencana_m ?? lok.panjang_gis_m;
  if (lok.titik_awal) {
    editBuilder.lat_awal = lok.titik_awal.lat;
    editBuilder.lng_awal = lok.titik_awal.lng;
  } else {
    editBuilder.lat_awal = '';
    editBuilder.lng_awal = '';
  }
  if (lok.titik_akhir) {
    editBuilder.lat_akhir = lok.titik_akhir.lat;
    editBuilder.lng_akhir = lok.titik_akhir.lng;
  } else {
    editBuilder.lat_akhir = '';
    editBuilder.lng_akhir = '';
  }
  if (switchToEditTab) {
    routeFormMode.value = 'edit';
  }
  renderBuilderMarkers();
  nextTick(() => {
    isHydratingEditBuilder = false;
  });
}

function enterEditLocationForm(lok: WorkspaceLocationItem) {
  if (isReshapeMode.value && selectedLocalId.value !== lok.localId) {
    stopReshapeMode(true);
  }
  selectedLocalId.value = lok.localId;
  hasUnanalyzedManualEdit.value = false;
  populateEditBuilderFromLocation(lok, true);
  routeSubView.value = 'edit';
  activeLeftSection.value = 'rute';
  rightPanelOpen.value = true;
  focusLocationOnMap(lok);
  scrollPanelBodyToTop();
}

const editLocationIndex = computed(() => {
  if (!editBuilder.targetLocalId) return -1;
  return locations.value.findIndex((item) => item.localId === editBuilder.targetLocalId);
});

function stepEditLocation(delta: -1 | 1) {
  if (locations.value.length === 0) return;
  const currentIdx = editLocationIndex.value === -1 ? 0 : editLocationIndex.value;
  const nextIdx = currentIdx + delta;
  if (nextIdx < 0 || nextIdx >= locations.value.length) return;
  const target = locations.value[nextIdx];
  if (target) {
    enterEditLocationForm(target);
  }
}

function cancelEditLocationForm() {
  isHydratingEditBuilder = true;
  stopReshapeMode(false);
  hasUnanalyzedManualEdit.value = false;
  editBuilder.targetId = null;
  editBuilder.targetLocalId = null;
  editBuilder.targetUrutan = 1;
  editBuilder.nama_lokasi = '';
  editBuilder.id_jalan_poros = '';
  editBuilder.lebar_m = 0;
  editBuilder.panjang_rencana_m = null;
  editBuilder.lat_awal = '';
  editBuilder.lng_awal = '';
  editBuilder.lat_akhir = '';
  editBuilder.lng_akhir = '';
  routeFormMode.value = 'create';
  routeSubView.value = locations.value.length > 0 ? 'list' : 'create';
  clickTarget.value = 'awal';
  renderBuilderMarkers();
  scrollPanelBodyToTop();
  nextTick(() => {
    isHydratingEditBuilder = false;
  });
}

function resetCreateBuilder() {
  stopReshapeMode(false);
  hasUnanalyzedManualEdit.value = false;
  createBuilder.nama_lokasi = '';
  createBuilder.id_jalan_poros = '';
  createBuilder.lebar_m = 0;
  createBuilder.panjang_rencana_m = null;
  createBuilder.lat_awal = '';
  createBuilder.lng_awal = '';
  createBuilder.lat_akhir = '';
  createBuilder.lng_akhir = '';
  createWaypoints.value = [newWaypoint(), newWaypoint()];
  routeFormMode.value = 'create';
  clickTarget.value = 'awal';
  renderBuilderMarkers();
  activeLeftSection.value = 'rute';
}

function handleSelectJalanPorosOption(mode: 'create' | 'edit') {
  const draft = mode === 'edit' ? editBuilder : createBuilder;
  if (!draft.id_jalan_poros) return;
  const found = jalanPorosOptions.value.find((jp) => jp.id === draft.id_jalan_poros);
  if (!found) return;
  if (!draft.nama_lokasi || draft.nama_lokasi.startsWith('Lokasi Ruas')) {
    draft.nama_lokasi = found.nama_ruas || `Ruas Kode ${found.kode_ruas}`;
  }
  if (found.panjang && found.panjang > 0 && !draft.panjang_rencana_m) {
    draft.panjang_rencana_m = found.panjang;
  }
  if (found.lebar && found.lebar > 0) {
    draft.lebar_m = found.lebar;
  }
  const endpoints = extractEndpointsFromGeoJson(found.geojson);
  if (mode === 'create' && endpoints.awal && endpoints.akhir && !createWaypoints.value.some(isValidWaypoint)) {
    createWaypoints.value = [
      newWaypoint(endpoints.awal.lat, endpoints.awal.lng),
      newWaypoint(endpoints.akhir.lat, endpoints.akhir.lng),
    ];
    renderBuilderMarkers();
    return;
  }
  if (endpoints.awal && endpoints.akhir && !isValidCoordinatePair(draft)) {
    draft.lat_awal = endpoints.awal.lat;
    draft.lng_awal = endpoints.awal.lng;
    draft.lat_akhir = endpoints.akhir.lat;
    draft.lng_akhir = endpoints.akhir.lng;
    renderBuilderMarkers();
  }
}

watch(
  () => [editBuilder.nama_lokasi, editBuilder.panjang_rencana_m, editBuilder.lebar_m, editBuilder.id_jalan_poros],
  ([nextNama, nextPanjang, nextLebar, nextPoros]) => {
    if (isHydratingEditBuilder || routeFormMode.value !== 'edit' || !editBuilder.targetLocalId) return;
    const target = locations.value.find((l) => l.localId === editBuilder.targetLocalId);
    if (!target) return;
    if (typeof nextNama === 'string' && nextNama.trim()) {
      target.nama_lokasi = nextNama;
    }
    target.panjang_rencana_m =
      typeof nextPanjang === 'number' && Number.isFinite(nextPanjang) ? nextPanjang : null;
    target.lebar_m = normalizeOptionalLebar(nextLebar);
    target.id_jalan_poros = typeof nextPoros === 'string' && nextPoros ? nextPoros : null;
    dirtyLocationIds.value = { ...dirtyLocationIds.value, [target.localId]: true };
  }
);

async function handleGenerateNewRoute() {
  if (!form.id_desa) {
    toast.add({
      title: 'Desa Belum Dipilih',
      description: 'Pilih Kecamatan dan Desa terlebih dahulu sebelum men-generate lokasi rute baru.',
      color: 'warning',
    });
    activeLeftSection.value = 'identitas';
    return;
  }

  const filledWaypoints = createWaypoints.value.filter(isValidWaypoint);
  const routeWaypointCount = filledWaypoints.filter((wp) => wp.is_route).length;
  if (filledWaypoints.length < 2 || routeWaypointCount < 2) {
    toast.add({
      title: 'Titik Rute Belum Cukup',
      description: 'Isi minimal 2 titik valid dan centang minimal 2 titik sebagai Acuan Rute (klik peta atau ketik koordinat).',
      color: 'warning',
    });
    return;
  }

  generatingRoute.value = true;
  try {
    const baseName =
      createBuilder.nama_lokasi.trim() ||
      form.judul_kegiatan.trim() ||
      `Lokasi Ruas #${locations.value.length + 1}`;

    const waypointPayload = filledWaypoints.map((wp, idx) => ({
      lat: Number(wp.lat),
      lng: Number(wp.lng),
      label: wp.label.trim() || `Titik ${idx + 1}`,
      is_route: wp.is_route,
      use_ors: wp.use_ors,
    }));

    const res = await bkkdApi.generateRoutePreview({
      id_desa: form.id_desa,
      id_proposal: proposal.value?.id || null,
      tahun_anggaran: form.tahun_anggaran,
      tagging_usulan: form.tagging_usulan,
      nama_lokasi: baseName,
      waypoints: waypointPayload,
      id_jalan_poros: createBuilder.id_jalan_poros || null,
      lebar_m: normalizeOptionalLebar(createBuilder.lebar_m),
      panjang_rencana_m: createBuilder.panjang_rencana_m,
    });

    const generatedItems: BkkdRoutePreviewItem[] = res.lokasi_list || [];
    if (!generatedItems.length) {
      toast.add({
        title: 'Gagal Membentuk Lokasi Baru',
        description: 'Tidak dapat mengekstrak rute dari koordinat yang dimasukkan.',
        color: 'error',
      });
      return;
    }

    const stamp = Date.now();
    const newWorkspaceItems: WorkspaceLocationItem[] = generatedItems.map((item, idx) => {
      const endpoints = extractEndpointsFromGeoJson(item.geojson);
      return {
        localId: `lok-${stamp}-${idx}`,
        urutan: locations.value.length + idx + 1,
        nama_lokasi: item.nama_lokasi || baseName,
        id_jalan_poros: item.id_jalan_poros || null,
        nama_ruas: item.nama_ruas || null,
        sumber_rute: item.sumber_rute || 'jalan_porosdesa',
        titik_awal: item.titik_awal || endpoints.awal,
        titik_akhir: item.titik_akhir || endpoints.akhir,
        panjang_gis_m: Number(item.panjang_gis_m || 0),
        panjang_rencana_m: Number(item.panjang_rencana_m ?? item.panjang_gis_m ?? 0),
        lebar_m: item.lebar_m !== null && item.lebar_m !== undefined ? normalizeOptionalLebar(item.lebar_m) : normalizeOptionalLebar(createBuilder.lebar_m),
        geojson: item.geojson,
        status_analisa_spasial: item.status_analisa_spasial || 'aman',
        ringkasan_analisa: item.ringkasan_analisa
          ? { ...item.ringkasan_analisa, titik_sebaran: item.titik_sebaran || item.ringkasan_analisa.titik_sebaran }
          : item.ringkasan_analisa,
        snapshot_data_url: null,
      };
    });

    locations.value.push(...newWorkspaceItems);
    locations.value.forEach((lok, idx) => {
      lok.urutan = idx + 1;
    });

    const primaryNew = newWorkspaceItems[0]!;
    selectedLocalId.value = primaryNew.localId;
    newWorkspaceItems.forEach((item) => {
      dirtyLocationIds.value = { ...dirtyLocationIds.value, [item.localId]: true };
    });

    resetCreateBuilder();
    routeSubView.value = 'list';
    renderAllMapLayers();
    focusLocationOnMap(primaryNew);
    await generateAllSnapshots();
    scrollPanelBodyToTop();

    const sumberLabel = getSumberRuteMeta(primaryNew.sumber_rute).label;
    toast.add({
      title:
        newWorkspaceItems.length > 1
          ? `${newWorkspaceItems.length} Lokasi Baru Ditambahkan`
          : 'Lokasi Baru Berhasil Dibuat',
      description: `Sumber rute: ${sumberLabel} (Total ${formatNumber(res.total_panjang_gis)} m).`,
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Generate Lokasi Baru',
      description: err?.data?.message || 'Terjadi kesalahan saat memproses rute dan analisa spasial.',
      color: 'error',
    });
  } finally {
    generatingRoute.value = false;
  }
}

async function handleRegenerateEditLocation() {
  if (!form.id_desa) {
    toast.add({
      title: 'Desa Belum Dipilih',
      description: 'Pilih Kecamatan dan Desa terlebih dahulu.',
      color: 'warning',
    });
    activeLeftSection.value = 'identitas';
    return;
  }

  const targetIdx = editBuilder.targetLocalId
    ? locations.value.findIndex((item) => item.localId === editBuilder.targetLocalId)
    : -1;
  if (targetIdx === -1) {
    toast.add({
      title: 'Target Edit Lokasi Tidak Ditemukan',
      description: 'Pilih tombol Edit pada salah satu lokasi di daftar terlebih dahulu.',
      color: 'warning',
    });
    return;
  }

  if (!isValidCoordinatePair(editBuilder)) {
    toast.add({
      title: 'Koordinat Edit Belum Lengkap',
      description: 'Pastikan Titik Awal (A) dan Titik Akhir (B) pada Form Edit Lokasi valid.',
      color: 'warning',
    });
    return;
  }

  const existing = locations.value[targetIdx]!;
  generatingRoute.value = true;
  try {
    const baseName =
      editBuilder.nama_lokasi.trim() ||
      existing.nama_lokasi ||
      `Lokasi #${existing.urutan}`;

    const res = await bkkdApi.generateRoutePreview({
      id_desa: form.id_desa,
      id_proposal: proposal.value?.id || null,
      tahun_anggaran: form.tahun_anggaran,
      tagging_usulan: form.tagging_usulan,
      id_lokasi: existing.id ?? editBuilder.targetId ?? null,
      single_location_mode: true,
      nama_lokasi: baseName,
      latitude: Number(editBuilder.lat_awal),
      longitude: Number(editBuilder.lng_awal),
      latitude_akhir: Number(editBuilder.lat_akhir),
      longitude_akhir: Number(editBuilder.lng_akhir),
      id_jalan_poros: editBuilder.id_jalan_poros || null,
      lebar_m: normalizeOptionalLebar(editBuilder.lebar_m),
      panjang_rencana_m: editBuilder.panjang_rencana_m,
    });

    const regenerated = res.data || res.lokasi_list?.[0];
    if (!regenerated) {
      toast.add({
        title: 'Gagal Generate Ulang Lokasi',
        description: 'Tidak dapat mengekstrak rute dari koordinat pembaruan.',
        color: 'error',
      });
      return;
    }

    const endpoints = extractEndpointsFromGeoJson(regenerated.geojson);
    const updatedItem: WorkspaceLocationItem = {
      id: regenerated.id || existing.id,
      localId: existing.localId,
      urutan: existing.urutan,
      nama_lokasi: regenerated.nama_lokasi || baseName,
      id_jalan_poros: regenerated.id_jalan_poros ?? existing.id_jalan_poros ?? null,
      nama_ruas: regenerated.nama_ruas ?? existing.nama_ruas ?? null,
      sumber_rute: regenerated.sumber_rute || 'jalan_porosdesa',
      titik_awal: regenerated.titik_awal || endpoints.awal,
      titik_akhir: regenerated.titik_akhir || endpoints.akhir,
      panjang_gis_m: Number(regenerated.panjang_gis_m || 0),
      panjang_rencana_m: Number(
        editBuilder.panjang_rencana_m ?? regenerated.panjang_rencana_m ?? regenerated.panjang_gis_m ?? 0
      ),
      lebar_m: normalizeOptionalLebar(editBuilder.lebar_m ?? regenerated.lebar_m ?? existing.lebar_m),
      geojson: regenerated.geojson,
      status_analisa_spasial: regenerated.status_analisa_spasial || 'aman',
      ringkasan_analisa: regenerated.ringkasan_analisa,
      snapshot_data_url: null,
    };

    locations.value.splice(targetIdx, 1, updatedItem);
    selectedLocalId.value = updatedItem.localId;
    dirtyLocationIds.value = { ...dirtyLocationIds.value, [updatedItem.localId]: true };
    populateEditBuilderFromLocation(updatedItem, true);

    renderAllMapLayers();
    focusLocationOnMap(updatedItem);
    updatedItem.snapshot_data_url = generateLocationSnapshotCanvas(updatedItem);

    toast.add({
      title: `Lokasi #${updatedItem.urutan} Berhasil Digenerate Ulang`,
      description: updatedItem.id
        ? `Rute diperbarui berdasarkan ID Lokasi tersimpan (${formatNumber(updatedItem.panjang_gis_m)} m). Klik Simpan Lokasi untuk menyimpan ke database.`
        : `Rute diperbarui (${formatNumber(updatedItem.panjang_gis_m)} m) tanpa membuat data ganda.`,
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Generate Ulang Lokasi',
      description: err?.data?.message || 'Terjadi kesalahan saat memproses pembaruan lokasi.',
      color: 'error',
    });
  } finally {
    generatingRoute.value = false;
  }
}

async function removeLocationItem(lok: WorkspaceLocationItem) {
  if (isReshapeMode.value && activeLocation.value?.localId === lok.localId) {
    stopReshapeMode(false);
  }
  locations.value = locations.value.filter((item) => item.localId !== lok.localId);
  locations.value.forEach((item, idx) => {
    item.urutan = idx + 1;
  });
  if (selectedLocalId.value === lok.localId) {
    selectedLocalId.value = locations.value[0]?.localId || null;
  }
  if (editBuilder.targetLocalId === lok.localId) {
    cancelEditLocationForm();
  }
  renderAllMapLayers();
}

async function handleSaveWorkspace() {
  if (!form.id_kecamatan || !form.id_desa || !form.id_bidang || !form.judul_kegiatan.trim()) {
    toast.add({
      title: 'Identitas Proposal Belum Lengkap',
      description: 'Pastikan Kecamatan, Desa, Bidang, dan Judul Kegiatan telah diisi.',
      color: 'warning',
    });
    activeLeftSection.value = 'identitas';
    return;
  }

  if (locations.value.length === 0) {
    toast.add({
      title: 'Lokasi LineString Belum Ditambahkan',
      description: 'Tambahkan minimal 1 lokasi rute LineString pada peta untuk dianalisa.',
      color: 'warning',
    });
    activeLeftSection.value = 'rute';
    return;
  }

  if (isReshapeMode.value) {
    stopReshapeMode(false);
  }

  savingProposal.value = true;
  try {
    const lokasiPayload = locations.value.map((lok, idx) => ({
      urutan: idx + 1,
      nama_lokasi: lok.nama_lokasi,
      id_jalan_poros: lok.id_jalan_poros || null,
      panjang_rencana_m: lok.panjang_rencana_m ?? lok.panjang_gis_m,
      lebar_m: normalizeOptionalLebar(lok.lebar_m),
      sumber_rute: lok.sumber_rute,
      titik_sebaran: lok.ringkasan_analisa?.titik_sebaran || undefined,
      geojson: lok.geojson,
    }));

    const payload: Record<string, any> = {
      tahun_anggaran: form.tahun_anggaran,
      tagging_usulan: form.tagging_usulan,
      id_kecamatan: form.id_kecamatan,
      id_desa: form.id_desa,
      id_bidang: form.id_bidang,
      judul_kegiatan: form.judul_kegiatan.trim(),
      lokasi_kegiatan:
        form.lokasi_kegiatan.trim() ||
        locations.value.map((l) => l.nama_lokasi).join('; '),
      nilai_usulan: form.nilai_usulan,
      uraian_kegiatan: form.uraian_kegiatan.trim() || null,
      lokasi: lokasiPayload,
    };

    if (isCreateMode.value) {
      const created = await bkkdApi.createProposal(payload);
      toast.add({
        title: 'Proposal & Analisa Spasial Disimpan',
        description: `Nomor Registrasi: ${created.data.nomor_registrasi}`,
        color: 'success',
      });
      await router.replace(`/admin/bkkd/proposal/${created.data.id}`);
    } else if (proposal.value) {
      payload.versi = proposal.value.versi;
      const updated = await bkkdApi.updateProposal(proposal.value.id, payload);
      proposal.value = updated.data;

      if (form.catatan_konfirmasi_desa.trim()) {
        await bkkdApi.simpanKonfirmasiDesa(proposal.value.id, {
          catatan_konfirmasi_desa: form.catatan_konfirmasi_desa.trim(),
        });
      }

      await loadExistingProposal();
      toast.add({
        title: 'Perubahan Proposal & Analisa Disimpan',
        description: 'Seluruh lokasi LineString dan hasil analisa spasial telah diperbarui.',
        color: 'success',
      });
    }
  } catch (err: any) {
    toast.add({
      title: 'Gagal Menyimpan Proposal',
      description: err?.data?.message || 'Periksa kembali isian proposal dan lokasi LineString.',
      color: 'error',
    });
  } finally {
    savingProposal.value = false;
  }
}

async function handleReanalyzeSavedProposal() {
  if (isCreateMode.value || !proposal.value) return;
  reanalyzing.value = true;
  try {
    await bkkdApi.reanalyzeProposalSpatial(proposal.value.id);
    await loadExistingProposal();
    toast.add({
      title: 'Analisa Spasial Diperbarui',
      description: 'Seluruh lokasi LineString telah dianalisa ulang terhadap Jalan Poros Desa & Infrastruktur Segmen.',
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Analisa Ulang',
      description: err?.data?.message || 'Terjadi kesalahan saat menganalisa ulang lokasi.',
      color: 'error',
    });
  } finally {
    reanalyzing.value = false;
  }
}

async function handleAjukanPromosiMaster() {
  if (!proposal.value?.id || !activeLocation.value?.id) return;
  promotionForm.submitting = true;
  try {
    const res = await bkkdApi.ajukanPromosiRuas(proposal.value.id, activeLocation.value.id, {
      nama_ruas_usulan: promotionForm.nama_ruas.trim() || activeLocation.value.nama_lokasi,
      hierarki_jalan_usulan: promotionForm.hierarki_jalan,
      catatan: promotionForm.catatan.trim() || undefined,
    });
    await loadExistingProposal();
    toast.add({
      title: 'Kandidat Ruas Diajukan',
      description: res.message || 'Lokasi diajukan ke antrean validasi Master Jaringan Jalan.',
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Pengajuan Gagal Validasi Spasial',
      description: err?.data?.message || 'Lokasi belum memenuhi syarat validasi spasial Master Jaringan Jalan.',
      color: 'error',
    });
  } finally {
    promotionForm.submitting = false;
  }
}

async function handleValidasiPromosiMaster() {
  if (!proposal.value?.id || !activeLocation.value?.id) return;
  promotionForm.submitting = true;
  try {
    const res = await bkkdApi.validasiPromosiRuas(proposal.value.id, activeLocation.value.id, {
      keputusan: promotionForm.keputusan,
      nama_ruas: promotionForm.nama_ruas.trim() || activeLocation.value.nama_lokasi,
      hierarki_jalan: promotionForm.hierarki_jalan,
      catatan: promotionForm.catatan.trim() || undefined,
    });
    await loadExistingProposal();
    toast.add({
      title: 'Validasi Master Jalan Berhasil',
      description: res.message || 'Status promosi jaringan jalan diperbarui.',
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Validasi Master Jalan',
      description: err?.data?.message || 'Terjadi kesalahan saat memvalidasi ruas.',
      color: 'error',
    });
  } finally {
    promotionForm.submitting = false;
  }
}

function syncViewportMode() {
  if (typeof window === 'undefined') return;
  isMobileViewport.value = window.innerWidth < 640;
}

function handleWindowResize() {
  syncViewportMode();
  mapInstance?.updateSize();
}

onMounted(async () => {
  syncViewportMode();
  window.addEventListener('resize', handleWindowResize);
  await loadMasterReferences();
  await initMap();
  nextTick(() => {
    mapInstance?.updateSize();
  });
  await loadExistingProposal();
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleWindowResize);
  stopReshapeMode(false);
  if (mapInstance) {
    mapInstance.setTarget(undefined);
    mapInstance = null;
  }
});
</script>

<template>
  <div class="relative w-full h-full overflow-hidden bg-slate-900">
    <!-- RIGHT OVERLAY PANEL (DESKTOP) / BOTTOM SHEET (MOBILE <640px) -->
    <aside
      ref="sheetPanelRef"
      aria-label="Panel Form Analisa Proposal"
      :aria-hidden="!rightPanelOpen"
      :style="mobileSheetStyle"
      class="fixed sm:absolute z-30 sm:z-20 inset-x-0 bottom-0 sm:inset-x-auto sm:top-0 sm:bottom-0 sm:right-0 sm:w-[440px] lg:w-[488px] xl:w-[512px] flex flex-col overflow-hidden bg-white dark:bg-slate-900 border-t sm:border-t-0 sm:border-l border-slate-200 dark:border-slate-800 rounded-t-2xl sm:rounded-none shadow-[0_-8px_32px_rgba(15,23,42,0.22)] sm:shadow-xl"
      :class="[
        !isDraggingSheet
          ? 'transition-[height,translate,transform,opacity] duration-300 ease-out'
          : 'sm:transition-[translate,transform,opacity] sm:duration-300 sm:ease-out',
        rightPanelOpen ? 'sm:translate-x-0 sm:opacity-100' : 'max-sm:hidden sm:translate-x-full sm:opacity-0 sm:pointer-events-none'
      ]"
    >
      <!-- Mobile Bottom Sheet Drag Handle Bar (<640px only) -->
      <div
        class="sm:hidden touch-none select-none shrink-0 flex items-center justify-between gap-2 px-3.5 min-h-[44px] border-b border-slate-100 dark:border-slate-800/80 bg-white dark:bg-slate-900 cursor-grab active:cursor-grabbing"
        role="separator"
        aria-orientation="horizontal"
        aria-label="Tarik atau ketuk untuk mengubah tinggi sheet panel"
        @pointerdown="onSheetHandlePointerDown"
        @pointermove="onSheetHandlePointerMove"
        @pointerup="onSheetHandlePointerUp"
        @pointercancel="onSheetHandlePointerUp"
      >
        <span class="text-xs font-medium text-slate-600 dark:text-slate-300 w-24 truncate">
          {{
            mobileSheetSnap === 'peek'
              ? 'Mode Peta'
              : mobileSheetSnap === 'full'
                ? 'Layar Penuh'
                : 'Panel Form'
          }}
        </span>

        <span class="w-10 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600 shrink-0" aria-hidden="true" />

        <div class="w-24 flex items-center justify-end gap-1 shrink-0" @pointerdown.stop>
          <button
            type="button"
            class="px-2.5 py-1 min-h-[36px] rounded-lg text-xs font-semibold text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-950/50 cursor-pointer inline-flex items-center gap-1"
            :title="mobileSheetSnap === 'peek' ? 'Tampilkan Form' : 'Kecilkan untuk Lihat Peta'"
            @click.stop="setMobileSheetSnap(mobileSheetSnap === 'peek' ? 'half' : 'peek')"
          >
            <UIcon
              :name="mobileSheetSnap === 'peek' ? 'i-lucide-chevron-up' : 'i-lucide-map'"
              class="w-3.5 h-3.5"
            />
            <span>{{ mobileSheetSnap === 'peek' ? 'Buka' : 'Peta' }}</span>
          </button>
        </div>
      </div>

      <!-- Workspace Top Header -->
      <header class="shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 bg-slate-50 dark:bg-slate-900">
        <div class="flex items-center gap-2.5 min-w-0">
          <NuxtLink
            to="/admin/bkkd/proposal"
            class="inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            title="Kembali ke Daftar Analisis Proposal"
            aria-label="Kembali ke Daftar Analisis Proposal"
          >
            <UIcon name="i-lucide-arrow-left" class="w-4 h-4" />
          </NuxtLink>
          <div class="min-w-0">
            <span class="block text-xs font-semibold text-slate-600 dark:text-slate-300 tabular-nums truncate">
              {{ isCreateMode ? 'PROPOSAL BARU' : proposal?.nomor_registrasi || 'WORKSPACE ANALISA' }}
            </span>
            <h1 class="text-sm font-bold text-slate-900 dark:text-slate-100 truncate">
              {{ form.judul_kegiatan || 'Workspace Analisis Spasial Proposal BKKD' }}
            </h1>
          </div>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            v-if="canEdit"
            type="button"
            :disabled="savingProposal"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white transition-colors cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            @click="handleSaveWorkspace"
          >
            <UIcon
              :name="savingProposal ? 'i-lucide-loader-2' : 'i-lucide-save'"
              class="w-4 h-4"
              :class="{ 'animate-spin': savingProposal }"
            />
            <span class="hidden sm:inline">{{ isCreateMode ? 'Simpan Proposal' : 'Simpan Perubahan' }}</span>
            <span class="sm:hidden">Simpan</span>
          </button>

          <button
            type="button"
            class="inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            title="Sembunyikan Panel Form"
            aria-label="Sembunyikan Panel Form"
            @click="toggleRightPanel(false)"
          >
            <UIcon name="i-lucide-panel-right-close" class="w-4 h-4 hidden sm:block" />
            <UIcon name="i-lucide-x" class="w-4 h-4 sm:hidden" />
          </button>
        </div>
      </header>

      <!-- Workspace Step Navigation Tabs -->
      <nav
        class="shrink-0 grid grid-cols-3 border-b border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 text-xs font-semibold"
        aria-label="Tahapan Analisa Proposal"
      >
        <button
          type="button"
          class="py-2 px-2 flex items-center justify-center gap-1.5 border-b-2 transition-colors cursor-pointer min-h-[44px] sm:min-h-[40px]"
          :class="
            activeLeftSection === 'identitas'
              ? 'border-sky-600 text-sky-700 dark:text-sky-400 bg-white dark:bg-slate-900'
              : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          "
          @click="openWorkspaceTab('identitas')"
        >
          <UIcon name="i-lucide-file-text" class="w-4 h-4 shrink-0" />
          <span>1. Identitas</span>
        </button>
        <button
          type="button"
          class="py-2 px-2 flex items-center justify-center gap-1.5 border-b-2 transition-colors cursor-pointer min-h-[44px] sm:min-h-[40px]"
          :class="
            activeLeftSection === 'rute'
              ? 'border-sky-600 text-sky-700 dark:text-sky-400 bg-white dark:bg-slate-900'
              : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          "
          @click="openWorkspaceTab('rute')"
        >
          <UIcon name="i-lucide-route" class="w-4 h-4 shrink-0" />
          <span>2. Rute ({{ locations.length }})</span>
        </button>
        <button
          type="button"
          class="py-2 px-2 flex items-center justify-center gap-1.5 border-b-2 transition-colors cursor-pointer min-h-[44px] sm:min-h-[40px]"
          :class="
            activeLeftSection === 'analisa'
              ? 'border-sky-600 text-sky-700 dark:text-sky-400 bg-white dark:bg-slate-900'
              : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          "
          @click="openWorkspaceTab('analisa')"
        >
          <UIcon name="i-lucide-layers" class="w-4 h-4 shrink-0" />
          <span>3. Hasil Analisa</span>
        </button>
      </nav>

      <!-- Scrollable Overflow Workspace Body -->
      <div
        ref="panelBodyRef"
        class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:pb-4 space-y-4"
      >
        <div
          v-if="pageError"
          class="p-3.5 rounded-lg border border-rose-300 bg-rose-50 text-rose-900 dark:bg-rose-950/50 dark:border-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-3"
        >
          <div>
            <p class="font-semibold">Gagal Memuat Workspace</p>
            <p class="mt-0.5">{{ pageError }}</p>
          </div>
          <button
            type="button"
            class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-rose-700 text-white text-xs font-semibold hover:bg-rose-800 cursor-pointer shrink-0"
            @click="loadExistingProposal"
          >
            Coba Lagi
          </button>
        </div>

        <!-- TAB 1: IDENTITAS PROPOSAL (RINGKAS & FOKUS ANALISA) -->
        <section v-show="activeLeftSection === 'identitas'" class="space-y-3.5">
          <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300">
            Pilih wilayah <strong>Kecamatan & Desa</strong> terlebih dahulu agar layer <code>jalan_porosdesa</code> dan <code>infrastruktur_segmen</code> otomatis dimuat ke peta kerja.
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Tahun Anggaran <span class="text-rose-600">*</span>
              </label>
              <input
                v-model.number="form.tahun_anggaran"
                type="number"
                min="2020"
                max="2100"
                class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm tabular-nums text-slate-900 dark:text-slate-100"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Tagging Usulan <span class="text-rose-600">*</span>
              </label>
              <div
                role="radiogroup"
                aria-label="Tagging Usulan PRKPD atau RKPD"
                class="grid grid-cols-2 gap-1 p-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/80"
              >
                <button
                  type="button"
                  role="radio"
                  :aria-checked="form.tagging_usulan === 'PRKPD'"
                  class="px-2.5 min-h-[36px] sm:min-h-[28px] rounded-md text-xs font-semibold transition-colors cursor-pointer"
                  :class="
                    form.tagging_usulan === 'PRKPD'
                      ? 'bg-sky-700 text-white shadow-sm'
                      : 'text-slate-700 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-slate-700/60'
                  "
                  @click="form.tagging_usulan = 'PRKPD'"
                >
                  PRKPD
                </button>
                <button
                  type="button"
                  role="radio"
                  :aria-checked="form.tagging_usulan === 'RKPD'"
                  class="px-2.5 min-h-[36px] sm:min-h-[28px] rounded-md text-xs font-semibold transition-colors cursor-pointer"
                  :class="
                    form.tagging_usulan === 'RKPD'
                      ? 'bg-emerald-700 text-white shadow-sm'
                      : 'text-slate-700 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-slate-700/60'
                  "
                  @click="form.tagging_usulan = 'RKPD'"
                >
                  RKPD
                </button>
              </div>
              <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                {{
                  form.tagging_usulan === 'PRKPD'
                    ? `Disandingkan dengan usulan RKPD TA ${form.tahun_anggaran} & lintas tahun.`
                    : `Disandingkan dengan usulan PRKPD Tahun N (${form.tahun_anggaran - 1}) & lintas tahun.`
                }}
              </p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Bidang Kegiatan <span class="text-rose-600">*</span>
            </label>
            <select
              v-model="form.id_bidang"
              class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100"
            >
              <option value="" disabled>Pilih Bidang</option>
              <option v-for="b in bidangList" :key="b.id" :value="b.id">
                {{ b.nama }}
              </option>
            </select>
          </div>

          <WilayahSelector
            :model-kecamatan="form.id_kecamatan"
            :model-desa="form.id_desa"
            :show-labels="true"
            :required="true"
            layout="row"
            size="md"
            @change="handleFormWilayahChange"
          />

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Judul Kegiatan Proposal <span class="text-rose-600">*</span>
            </label>
            <input
              v-model="form.judul_kegiatan"
              type="text"
              placeholder="Contoh: Pembangunan Jalan Rigid Beton Poros Dusun Krajan"
              class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Estimasi Nilai Usulan (Rp)
              </label>
              <input
                v-model.number="form.nilai_usulan"
                type="number"
                min="0"
                step="1000000"
                placeholder="Opsional"
                class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm tabular-nums text-slate-900 dark:text-slate-100"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Keterangan Lokasi Umum
              </label>
              <input
                v-model="form.lokasi_kegiatan"
                type="text"
                placeholder="Dusun / RT / RW (Opsional)"
                class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Catatan Teknis / Uraian Singkat
            </label>
            <textarea
              v-model="form.uraian_kegiatan"
              rows="2"
              placeholder="Catatan tambahan terkait ruang lingkup usulan..."
              class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100"
            />
          </div>

          <div class="pt-2 flex justify-end">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 hover:opacity-90 cursor-pointer"
              @click="activeLeftSection = 'rute'"
            >
              <span>Lanjut ke Plotting Rute LineString</span>
              <UIcon name="i-lucide-arrow-right" class="w-4 h-4" />
            </button>
          </div>
        </section>

        <!-- TAB 2: GENERATOR RUTE (DAFTAR / TAMBAH / EDIT TERPISAH TANPA SCROLL NAIK-TURUN) -->
        <section v-show="activeLeftSection === 'rute'" class="space-y-3.5">
          <!-- Sub-View Switcher: Daftar (N) | Tambah Baru | Edit #X -->
          <div
            class="p-1 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 grid grid-cols-3 gap-1"
            role="tablist"
            aria-label="Sub-Tampilan Rute Lokasi"
          >
            <button
              type="button"
              role="tab"
              :aria-selected="routeSubView === 'list'"
              class="min-h-[44px] sm:min-h-[36px] px-2 py-1.5 rounded-lg text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
              :class="
                routeSubView === 'list'
                  ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow-xs'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-slate-900'
              "
              @click="switchRouteSubView('list')"
            >
              <UIcon name="i-lucide-list" class="w-4 h-4 shrink-0" />
              <span class="truncate">Daftar ({{ locations.length }})</span>
            </button>

            <button
              type="button"
              role="tab"
              :aria-selected="routeSubView === 'create'"
              class="min-h-[44px] sm:min-h-[36px] px-2 py-1.5 rounded-lg text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
              :class="
                routeSubView === 'create'
                  ? 'bg-sky-700 text-white shadow-xs'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-slate-900'
              "
              @click="switchRouteSubView('create')"
            >
              <UIcon name="i-lucide-plus-circle" class="w-4 h-4 shrink-0" />
              <span class="truncate">Tambah</span>
            </button>

            <button
              type="button"
              role="tab"
              :aria-selected="routeSubView === 'edit'"
              class="min-h-[44px] sm:min-h-[36px] px-2 py-1.5 rounded-lg text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
              :class="
                routeSubView === 'edit'
                  ? 'bg-indigo-700 text-white shadow-xs'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-slate-900'
              "
              @click="switchRouteSubView('edit')"
            >
              <UIcon name="i-lucide-square-pen" class="w-4 h-4 shrink-0" />
              <span class="truncate">
                {{ activeEditLocation ? `Edit #${activeEditLocation.urutan}` : 'Edit' }}
              </span>
            </button>
          </div>

          <!-- Panel Kontrol Reshape Geometri Manual (Selalu di atas agar langsung terlihat tanpa scroll) -->
          <div
            v-if="isReshapeMode || (activeLocation && hasUnanalyzedManualEdit)"
            class="p-3 rounded-xl border border-amber-300 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/30 space-y-2.5"
          >
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-900 dark:text-amber-200">
                <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4 shrink-0" />
                <span>{{ isReshapeMode ? 'Mode Reshape Manual Aktif' : 'Geometri Diubah Manual' }}</span>
              </span>
              <span class="text-xs font-semibold text-amber-800 dark:text-amber-300 tabular-nums">
                {{ activeVertexCount }} vertex · {{ formatNumber(activeLocation?.panjang_gis_m ?? 0) }} m
              </span>
            </div>

            <p class="text-xs text-amber-900/90 dark:text-amber-200/90 leading-relaxed">
              Seret titik sudut atau tengah garis pada peta untuk koreksi lokasi tanpa memanggil OpenRouteService. Alt+Klik titik untuk menghapus vertex.
            </p>

            <div class="flex flex-wrap items-center gap-1.5">
              <button
                v-if="!isReshapeMode && activeLocation"
                type="button"
                class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-amber-700 hover:bg-amber-800 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer"
                @click="startReshapeMode(activeLocation)"
              >
                <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4" />
                <span>Lanjut Reshape</span>
              </button>

              <template v-else>
                <button
                  type="button"
                  :disabled="reshapeHistory.length === 0"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 disabled:opacity-40 text-slate-700 dark:text-slate-200 text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer"
                  @click="undoReshapeStep"
                >
                  <UIcon name="i-lucide-undo-2" class="w-4 h-4" />
                  <span>Undo</span>
                </button>

                <button
                  type="button"
                  :disabled="!reshapeBaselineCoords"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 disabled:opacity-40 text-slate-700 dark:text-slate-200 text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer"
                  @click="resetReshapeToBaseline"
                >
                  <UIcon name="i-lucide-rotate-ccw" class="w-4 h-4" />
                  <span>Reset</span>
                </button>

                <button
                  type="button"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer"
                  :class="
                    isReshapeSnapEnabled
                      ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-200'
                      : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                  "
                  @click="toggleReshapeSnap"
                >
                  <UIcon name="i-lucide-magnet" class="w-4 h-4" />
                  <span>Snap: {{ isReshapeSnapEnabled ? 'ON' : 'OFF' }}</span>
                </button>
              </template>

              <button
                type="button"
                :disabled="generatingRoute"
                class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer"
                @click="handleAnalyzeManualReshape"
              >
                <UIcon
                  :name="generatingRoute ? 'i-lucide-loader-2' : 'i-lucide-check-circle-2'"
                  class="w-4 h-4"
                  :class="{ 'animate-spin': generatingRoute }"
                />
                <span>Analisa Irisan (Tanpa ORS)</span>
              </button>

              <button
                v-if="!isCreateMode && canEdit && activeLocation"
                type="button"
                :disabled="savingLocationId === activeLocation.localId"
                class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer"
                @click="handleSaveSingleLocation(activeLocation)"
              >
                <UIcon
                  :name="savingLocationId === activeLocation.localId ? 'i-lucide-loader-2' : 'i-lucide-save'"
                  class="w-4 h-4"
                  :class="{ 'animate-spin': savingLocationId === activeLocation.localId }"
                />
                <span>{{ savingLocationId === activeLocation.localId ? 'Menyimpan...' : 'Simpan Lokasi Ini' }}</span>
              </button>

              <button
                v-if="isReshapeMode"
                type="button"
                class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 text-xs font-bold cursor-pointer"
                @click="stopReshapeMode(true)"
              >
                Selesai Reshape
              </button>
            </div>
          </div>

          <!-- FORM 1: TAMBAH LOKASI BARU -->
          <div
            v-show="routeSubView === 'create'"
            class="p-3.5 rounded-xl border border-sky-200 dark:border-sky-900 bg-sky-50/40 dark:bg-slate-800/50 space-y-3"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100">
                  Form Tambah Lokasi Baru
                </h2>
                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                  Klik Titik A dan B di peta lalu tekan Generate untuk menambahkan ruas baru.
                </p>
              </div>
              <button
                v-if="locations.length > 0"
                type="button"
                class="px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 cursor-pointer shrink-0 inline-flex items-center gap-1"
                @click="switchRouteSubView('list')"
              >
                <UIcon name="i-lucide-arrow-left" class="w-3.5 h-3.5" />
                <span>Daftar</span>
              </button>
            </div>

            <div class="space-y-2.5">
              <div>
                <label
                  for="create-builder-nama-lokasi"
                  class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1"
                >
                  Nama Segmen / Lokasi Baru
                </label>
                <input
                  id="create-builder-nama-lokasi"
                  v-model="createBuilder.nama_lokasi"
                  type="text"
                  placeholder="Misal: Segmen 1 Dusun Krajan RT 01 - RT 03"
                  class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100"
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                  <label
                    for="create-builder-panjang-rencana"
                    class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1"
                  >
                    Panjang Rencana (m)
                  </label>
                  <input
                    id="create-builder-panjang-rencana"
                    v-model.number="createBuilder.panjang_rencana_m"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="Otomatis dari GIS"
                    class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                  />
                </div>
                <div>
                  <label
                    for="create-builder-lebar-rencana"
                    class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1"
                  >
                    Lebar Rencana (m) <span class="font-normal text-slate-500 dark:text-slate-400">(Opsional)</span>
                  </label>
                  <input
                    id="create-builder-lebar-rencana"
                    v-model.number="createBuilder.lebar_m"
                    type="number"
                    min="0"
                    max="30"
                    step="0.5"
                    placeholder="0"
                    class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                  />
                </div>
              </div>
            </div>

            <div v-if="jalanPorosOptions.length > 0">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                Referensi Ruas Jalan Poros Desa (Opsional)
              </label>
              <select
                v-model="createBuilder.id_jalan_poros"
                class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100"
                @change="handleSelectJalanPorosOption('create')"
              >
                <option value="">Deteksi Otomatis (Jalan Poros Desa / OpenRouteService)</option>
                <option v-for="jp in jalanPorosOptions" :key="jp.id" :value="jp.id">
                  [Ruas {{ jp.kode_ruas || '-' }}] {{ jp.nama_ruas }} ({{ formatNumber(jp.panjang) }} m - {{ jp.kondisi || 'Eksisting' }})
                </option>
              </select>
            </div>

            <!-- Daftar Titik Dinamis (Waypoints) - Create -->
            <div class="space-y-2.5">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                  Titik Lokasi ({{ createWaypoints.length }})
                </span>

                <div class="flex items-center gap-1.5">
                  <div
                    role="group"
                    aria-label="Format koordinat"
                    class="inline-flex rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 p-0.5 text-[11px] font-semibold"
                  >
                    <button
                      type="button"
                      class="px-2 py-1 rounded-md transition-colors cursor-pointer"
                      :class="
                        coordInputMode === 'decimal'
                          ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xs'
                          : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                      "
                      @click="coordInputMode = 'decimal'"
                    >
                      Desimal
                    </button>
                    <button
                      type="button"
                      class="px-2 py-1 rounded-md transition-colors cursor-pointer"
                      :class="
                        coordInputMode === 'dms'
                          ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xs'
                          : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                      "
                      @click="coordInputMode = 'dms'"
                    >
                      Derajat
                    </button>
                  </div>

                  <button
                    type="button"
                    class="inline-flex items-center gap-1 px-2.5 min-h-[34px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold cursor-pointer"
                    @click="addCreateWaypoint"
                  >
                    <UIcon name="i-lucide-plus" class="w-3.5 h-3.5" aria-hidden="true" />
                    <span>Titik</span>
                  </button>
                </div>
              </div>

              <p class="text-[11px] text-slate-600 dark:text-slate-300">
                Klik peta untuk mengisi titik berikutnya, atau ketik / tempel koordinat (Desimal maupun DMS).
              </p>

              <div
                v-for="(wp, idx) in createWaypoints"
                :key="idx"
                class="p-2.5 rounded-xl border bg-white dark:bg-slate-900 space-y-2 transition-colors"
                :class="wp.is_route ? 'border-sky-300 dark:border-sky-800' : 'border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/60'"
              >
                <div class="flex items-center gap-1.5">
                  <span
                    class="w-5 h-5 rounded-full text-white text-[11px] font-bold inline-flex items-center justify-center shrink-0 tabular-nums"
                    :class="wp.is_route ? 'bg-sky-700' : 'bg-slate-500'"
                  >{{ idx + 1 }}</span>
                  <input
                    v-model="wp.label"
                    type="text"
                    :placeholder="`Titik ${idx + 1} (opsional)`"
                    class="flex-1 min-w-0 px-2.5 py-1 min-h-[34px] rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-slate-100"
                  />
                  <button
                    type="button"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-35 cursor-pointer shrink-0"
                    :disabled="idx === 0"
                    aria-label="Naikkan urutan titik"
                    @click="moveCreateWaypoint(idx, -1)"
                  >
                    <UIcon name="i-lucide-arrow-up" class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-35 cursor-pointer shrink-0"
                    :disabled="idx === createWaypoints.length - 1"
                    aria-label="Turunkan urutan titik"
                    @click="moveCreateWaypoint(idx, 1)"
                  >
                    <UIcon name="i-lucide-arrow-down" class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/50 cursor-pointer shrink-0"
                    aria-label="Hapus titik"
                    @click="removeCreateWaypoint(idx)"
                  >
                    <UIcon name="i-lucide-trash-2" class="w-3.5 h-3.5" />
                  </button>
                </div>

                <!-- Mode Input Desimal -->
                <div v-if="coordInputMode === 'decimal'" class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-[11px] font-medium text-slate-500 dark:text-slate-400 mb-0.5">Latitude</label>
                    <input
                      v-model="wp.lat"
                      type="text"
                      inputmode="decimal"
                      placeholder="-7.150100"
                      class="w-full px-2.5 py-1.5 min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-slate-900 dark:text-slate-100"
                      @paste="handleWaypointPaste($event, wp)"
                      @change="renderBuilderMarkers"
                    />
                  </div>
                  <div>
                    <label class="block text-[11px] font-medium text-slate-500 dark:text-slate-400 mb-0.5">Longitude</label>
                    <input
                      v-model="wp.lng"
                      type="text"
                      inputmode="decimal"
                      placeholder="111.890100"
                      class="w-full px-2.5 py-1.5 min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-slate-900 dark:text-slate-100"
                      @paste="handleWaypointPaste($event, wp)"
                      @change="renderBuilderMarkers"
                    />
                  </div>
                </div>

                <!-- Mode Input Derajat (DMS) -->
                <div v-else class="space-y-1.5">
                  <div class="grid grid-cols-[28px_1fr_1fr_1.2fr_46px] items-center gap-1">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Lat</span>
                    <input
                      :value="decimalToDms(wp.lat, true).deg"
                      type="number"
                      min="0"
                      max="90"
                      aria-label="Derajat Latitude"
                      placeholder="°"
                      class="w-full px-1.5 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-center"
                      @input="updateWaypointDms(wp, 'lat', 'deg', ($event.target as HTMLInputElement).value)"
                    />
                    <input
                      :value="decimalToDms(wp.lat, true).min"
                      type="number"
                      min="0"
                      max="59"
                      aria-label="Menit Latitude"
                      placeholder="'"
                      class="w-full px-1.5 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-center"
                      @input="updateWaypointDms(wp, 'lat', 'min', ($event.target as HTMLInputElement).value)"
                    />
                    <input
                      :value="decimalToDms(wp.lat, true).sec"
                      type="number"
                      min="0"
                      max="59.99"
                      step="0.01"
                      aria-label="Detik Latitude"
                      placeholder='"'
                      class="w-full px-1.5 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-center"
                      @input="updateWaypointDms(wp, 'lat', 'sec', ($event.target as HTMLInputElement).value)"
                    />
                    <select
                      :value="decimalToDms(wp.lat, true).dir"
                      aria-label="Arah Latitude"
                      class="w-full px-1 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-center"
                      @change="updateWaypointDms(wp, 'lat', 'dir', ($event.target as HTMLSelectElement).value)"
                    >
                      <option value="S">S</option>
                      <option value="N">N</option>
                    </select>
                  </div>

                  <div class="grid grid-cols-[28px_1fr_1fr_1.2fr_46px] items-center gap-1">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Lng</span>
                    <input
                      :value="decimalToDms(wp.lng, false).deg"
                      type="number"
                      min="0"
                      max="180"
                      aria-label="Derajat Longitude"
                      placeholder="°"
                      class="w-full px-1.5 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-center"
                      @input="updateWaypointDms(wp, 'lng', 'deg', ($event.target as HTMLInputElement).value)"
                    />
                    <input
                      :value="decimalToDms(wp.lng, false).min"
                      type="number"
                      min="0"
                      max="59"
                      aria-label="Menit Longitude"
                      placeholder="'"
                      class="w-full px-1.5 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-center"
                      @input="updateWaypointDms(wp, 'lng', 'min', ($event.target as HTMLInputElement).value)"
                    />
                    <input
                      :value="decimalToDms(wp.lng, false).sec"
                      type="number"
                      min="0"
                      max="59.99"
                      step="0.01"
                      aria-label="Detik Longitude"
                      placeholder='"'
                      class="w-full px-1.5 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs tabular-nums text-center"
                      @input="updateWaypointDms(wp, 'lng', 'sec', ($event.target as HTMLInputElement).value)"
                    />
                    <select
                      :value="decimalToDms(wp.lng, false).dir"
                      aria-label="Arah Longitude"
                      class="w-full px-1 py-1 min-h-[34px] rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-center"
                      @change="updateWaypointDms(wp, 'lng', 'dir', ($event.target as HTMLSelectElement).value)"
                    >
                      <option value="E">E</option>
                      <option value="W">W</option>
                    </select>
                  </div>

                  <p v-if="isValidWaypoint(wp)" class="text-[11px] text-slate-500 dark:text-slate-400 tabular-nums">
                    Desimal: {{ Number(wp.lat).toFixed(6) }}, {{ Number(wp.lng).toFixed(6) }}
                  </p>
                </div>

                <!-- Peran & Segmen Rute (Segmented Controls) -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-0.5">
                  <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 p-0.5 text-[11px] font-semibold">
                    <button
                      type="button"
                      class="px-2 py-0.5 rounded-md transition-colors cursor-pointer"
                      :class="wp.is_route ? 'bg-sky-700 text-white' : 'text-slate-600 dark:text-slate-300'"
                      @click="wp.is_route = true; renderBuilderMarkers()"
                    >
                      Titik Rute
                    </button>
                    <button
                      type="button"
                      class="px-2 py-0.5 rounded-md transition-colors cursor-pointer"
                      :class="!wp.is_route ? 'bg-slate-700 text-white' : 'text-slate-600 dark:text-slate-300'"
                      @click="wp.is_route = false; wp.use_ors = false; renderBuilderMarkers()"
                    >
                      Sebaran Saja
                    </button>
                  </div>

                  <div
                    v-if="wp.is_route && idx > 0"
                    class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 p-0.5 text-[11px] font-semibold"
                  >
                    <button
                      type="button"
                      class="px-2 py-0.5 rounded-md transition-colors cursor-pointer"
                      :class="!wp.use_ors ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xs' : 'text-slate-600 dark:text-slate-300'"
                      @click="wp.use_ors = false"
                    >
                      Poros / Lurus
                    </button>
                    <button
                      type="button"
                      class="px-2 py-0.5 rounded-md transition-colors cursor-pointer"
                      :class="wp.use_ors ? 'bg-indigo-700 text-white' : 'text-slate-600 dark:text-slate-300'"
                      @click="wp.use_ors = true"
                    >
                      Ikuti Jalan (ORS)
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <button
                type="button"
                :disabled="generatingRoute"
                class="w-full py-1.5 px-3 min-h-[44px] sm:min-h-[38px] rounded-lg bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white text-xs font-bold flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                @click="handleGenerateNewRoute"
              >
                <UIcon
                  :name="generatingRoute ? 'i-lucide-loader-2' : 'i-lucide-route'"
                  class="w-4 h-4 shrink-0"
                  :class="{ 'animate-spin': generatingRoute }"
                />
                <span>{{ generatingRoute ? 'Memproses Rute...' : 'Generate Lokasi Baru' }}</span>
              </button>

              <button
                type="button"
                :disabled="generatingRoute"
                class="w-full py-1.5 px-3 min-h-[44px] sm:min-h-[38px] rounded-lg border border-amber-400 dark:border-amber-700 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/50 dark:hover:bg-amber-950/80 disabled:opacity-50 text-amber-900 dark:text-amber-200 text-xs font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer"
                @click="startManualDrawMode()"
              >
                <UIcon name="i-lucide-pen-tool" class="w-4 h-4 shrink-0" />
                <span>{{ isManualDrawMode ? 'Sedang Menggambar...' : 'Gambar Manual di Peta' }}</span>
              </button>
            </div>
          </div>

          <!-- FORM 2: EDIT LOKASI TERPILIH (ANTI DOUBLE CREATE VIA ID LOKASI) -->
          <div
            v-show="routeSubView === 'edit'"
            class="p-3.5 rounded-xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50/40 dark:bg-slate-800/50 space-y-3"
          >
            <div v-if="!activeEditLocation" class="py-5 text-center space-y-2.5">
              <UIcon name="i-lucide-square-pen" class="w-7 h-7 text-indigo-600 dark:text-indigo-400 mx-auto" />
              <p class="text-xs font-bold text-slate-900 dark:text-slate-100">
                Belum Ada Lokasi Dipilih untuk Diedit
              </p>
              <p class="text-xs text-slate-600 dark:text-slate-300 max-w-xs mx-auto">
                Pilih lokasi dari Daftar atau tekan tombol di bawah untuk mulai mengedit berdasarkan ID lokasi.
              </p>
              <div class="flex items-center justify-center gap-2 flex-wrap">
                <button
                  v-if="locations.length > 0"
                  type="button"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer"
                  @click="switchRouteSubView('list')"
                >
                  <UIcon name="i-lucide-arrow-left" class="w-4 h-4" />
                  <span>Ke Daftar</span>
                </button>
                <button
                  v-if="locations.length > 0"
                  type="button"
                  class="inline-flex items-center gap-1.5 px-3.5 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-bold cursor-pointer"
                  @click="enterEditLocationForm(activeLocation || locations[0]!)"
                >
                  <UIcon name="i-lucide-square-pen" class="w-4 h-4" />
                  <span>Edit Lokasi #{{ (activeLocation || locations[0])?.urutan }}</span>
                </button>
              </div>
            </div>

            <template v-else>
              <!-- Inline Prev/Next Location Switcher inside Edit Form (No Scroll Back to List!) -->
              <div class="flex items-center justify-between gap-1.5 pb-2 border-b border-indigo-200/70 dark:border-indigo-900/70">
                <button
                  type="button"
                  class="px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 cursor-pointer inline-flex items-center gap-1 shrink-0"
                  title="Kembali ke Daftar Lokasi"
                  @click="switchRouteSubView('list')"
                >
                  <UIcon name="i-lucide-arrow-left" class="w-3.5 h-3.5" />
                  <span>Daftar</span>
                </button>

                <div
                  v-if="locations.length > 1"
                  class="flex items-center gap-1 flex-1 min-w-0 justify-center"
                >
                  <button
                    type="button"
                    :disabled="editLocationIndex <= 0"
                    class="w-11 h-11 sm:w-9 sm:h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 disabled:opacity-40 text-slate-700 dark:text-slate-200 hover:bg-slate-100 cursor-pointer shrink-0"
                    title="Edit Lokasi Sebelumnya"
                    aria-label="Edit Lokasi Sebelumnya"
                    @click="stepEditLocation(-1)"
                  >
                    <UIcon name="i-lucide-chevron-left" class="w-4 h-4" />
                  </button>
                  <span class="px-2 text-xs font-bold text-indigo-950 dark:text-indigo-200 tabular-nums truncate">
                    #{{ activeEditLocation.urutan }} ({{ editLocationIndex + 1 }}/{{ locations.length }})
                  </span>
                  <button
                    type="button"
                    :disabled="editLocationIndex >= locations.length - 1"
                    class="w-11 h-11 sm:w-9 sm:h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 disabled:opacity-40 text-slate-700 dark:text-slate-200 hover:bg-slate-100 cursor-pointer shrink-0"
                    title="Edit Lokasi Berikutnya"
                    aria-label="Edit Lokasi Berikutnya"
                    @click="stepEditLocation(1)"
                  >
                    <UIcon name="i-lucide-chevron-right" class="w-4 h-4" />
                  </button>
                </div>

                <button
                  type="button"
                  class="px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer shrink-0"
                  @click="cancelEditLocationForm"
                >
                  Batal
                </button>
              </div>

              <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-950 dark:text-indigo-200">
                      Form Edit Lokasi #{{ activeEditLocation.urutan }}
                    </h2>
                    <span
                      class="px-2 py-0.5 rounded text-xs font-semibold tabular-nums border"
                      :class="
                        activeEditLocation.id
                          ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-200 dark:border-emerald-800'
                          : 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-200 dark:border-amber-800'
                      "
                    >
                      {{
                        activeEditLocation.id
                          ? `ID: ${activeEditLocation.id.slice(0, 8)}`
                          : 'Draft Lokal'
                      }}
                    </span>
                  </div>
                  <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                    Generate ulang hanya memperbarui lokasi #{{ activeEditLocation.urutan }} tanpa membuat data ganda.
                  </p>
                </div>

                <button
                  type="button"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer shrink-0"
                  @click="cancelEditLocationForm"
                >
                  Batal Edit
                </button>
              </div>

              <div class="space-y-2.5">
                <div>
                  <label
                    for="edit-builder-nama-lokasi"
                    class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1"
                  >
                    Nama Segmen / Lokasi #{{ activeEditLocation.urutan }}
                  </label>
                  <input
                    id="edit-builder-nama-lokasi"
                    v-model="editBuilder.nama_lokasi"
                    type="text"
                    placeholder="Nama ruas lokasi"
                    class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100"
                  />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div>
                    <label
                      for="edit-builder-panjang-rencana"
                      class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1"
                    >
                      Panjang Rencana (m)
                    </label>
                    <input
                      id="edit-builder-panjang-rencana"
                      v-model.number="editBuilder.panjang_rencana_m"
                      type="number"
                      min="0"
                      step="1"
                      placeholder="Otomatis dari GIS"
                      class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                    />
                  </div>
                  <div>
                    <label
                      for="edit-builder-lebar-rencana"
                      class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1"
                    >
                      Lebar Rencana (m) <span class="font-normal text-slate-500 dark:text-slate-400">(Opsional)</span>
                    </label>
                    <input
                      id="edit-builder-lebar-rencana"
                      v-model.number="editBuilder.lebar_m"
                      type="number"
                      min="0"
                      max="30"
                      step="0.5"
                      placeholder="0"
                      class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                    />
                  </div>
                </div>
              </div>

              <div v-if="jalanPorosOptions.length > 0">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Referensi Ruas Jalan Poros Desa (Opsional)
                </label>
                <select
                  v-model="editBuilder.id_jalan_poros"
                  class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100"
                  @change="handleSelectJalanPorosOption('edit')"
                >
                  <option value="">Deteksi Otomatis (Jalan Poros Desa / OpenRouteService)</option>
                  <option v-for="jp in jalanPorosOptions" :key="jp.id" :value="jp.id">
                    [Ruas {{ jp.kode_ruas || '-' }}] {{ jp.nama_ruas }} ({{ formatNumber(jp.panjang) }} m - {{ jp.kondisi || 'Eksisting' }})
                  </option>
                </select>
              </div>

              <!-- Point A (Titik Awal) Input Box - Edit -->
              <div
                class="p-2.5 rounded-lg border transition-colors"
                :class="
                  clickTarget === 'awal'
                    ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/30'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900'
                "
              >
                <div class="flex items-center justify-between gap-2 mb-2">
                  <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                    <span class="w-5 h-5 rounded-full bg-emerald-700 text-white text-xs inline-flex items-center justify-center tabular-nums">A</span>
                    Titik Awal Lokasi #{{ activeEditLocation.urutan }}
                  </span>
                  <button
                    type="button"
                    class="px-3 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold cursor-pointer"
                    :class="
                      clickTarget === 'awal'
                        ? 'bg-emerald-700 text-white'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200'
                    "
                    @click="selectMapClickTarget('awal')"
                  >
                    {{ clickTarget === 'awal' ? 'Aktif Klik Peta (A)' : 'Ubah via Klik Peta' }}
                  </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Latitude Awal</label>
                    <input
                      v-model="editBuilder.lat_awal"
                      type="number"
                      step="0.000001"
                      class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Longitude Awal</label>
                    <input
                      v-model="editBuilder.lng_awal"
                      type="number"
                      step="0.000001"
                      class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                    />
                  </div>
                </div>
              </div>

              <!-- Point B (Titik Akhir) Input Box - Edit -->
              <div
                class="p-2.5 rounded-lg border transition-colors"
                :class="
                  clickTarget === 'akhir'
                    ? 'border-rose-500 bg-rose-50/50 dark:bg-rose-950/30'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900'
                "
              >
                <div class="flex items-center justify-between gap-2 mb-2">
                  <span class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-800 dark:text-rose-300">
                    <span class="w-5 h-5 rounded-full bg-rose-700 text-white text-xs inline-flex items-center justify-center tabular-nums">B</span>
                    Titik Akhir Lokasi #{{ activeEditLocation.urutan }}
                  </span>
                  <button
                    type="button"
                    class="px-3 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold cursor-pointer"
                    :class="
                      clickTarget === 'akhir'
                        ? 'bg-rose-700 text-white'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200'
                    "
                    @click="selectMapClickTarget('akhir')"
                  >
                    {{ clickTarget === 'akhir' ? 'Aktif Klik Peta (B)' : 'Ubah via Klik Peta' }}
                  </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Latitude Akhir</label>
                    <input
                      v-model="editBuilder.lat_akhir"
                      type="number"
                      step="0.000001"
                      class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Longitude Akhir</label>
                    <input
                      v-model="editBuilder.lng_akhir"
                      type="number"
                      step="0.000001"
                      class="w-full px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm tabular-nums text-slate-900 dark:text-slate-100"
                    />
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button
                  type="button"
                  :disabled="generatingRoute"
                  class="w-full py-1.5 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg bg-indigo-700 hover:bg-indigo-800 disabled:opacity-50 text-white text-xs font-bold flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                  @click="handleRegenerateEditLocation"
                >
                  <UIcon
                    :name="generatingRoute ? 'i-lucide-loader-2' : 'i-lucide-refresh-cw'"
                    class="w-4 h-4 shrink-0"
                    :class="{ 'animate-spin': generatingRoute }"
                  />
                  <span>
                    {{
                      generatingRoute
                        ? 'Memperbarui Rute...'
                        : `Generate Ulang Lokasi #${activeEditLocation.urutan}`
                    }}
                  </span>
                </button>

                <button
                  type="button"
                  :disabled="generatingRoute"
                  class="w-full py-1.5 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg border border-amber-400 dark:border-amber-700 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/50 dark:hover:bg-amber-950/80 disabled:opacity-50 text-amber-900 dark:text-amber-200 text-xs font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer"
                  title="Koreksi sebagian titik geometri yang sudah ada di peta tanpa memanggil ORS"
                  @click="startReshapeMode(activeEditLocation)"
                >
                  <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4 shrink-0" aria-hidden="true" />
                  <span>Reshape Geometri Ini</span>
                </button>
              </div>

              <div class="flex justify-end">
                <button
                  type="button"
                  :disabled="generatingRoute"
                  class="px-2.5 py-1 min-h-[36px] sm:min-h-[30px] rounded-lg text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                  title="Hapus lengkungan rute dan reset menjadi garis lurus 2 titik (Titik A ke Titik B)"
                  @click="handleResetEditToManualStraightRoute"
                >
                  Reset Jadi Garis Lurus (A-B)
                </button>
              </div>

              <button
                v-if="!isCreateMode && canEdit"
                type="button"
                :disabled="savingLocationId === activeEditLocation.localId"
                class="w-full py-1.5 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-xs font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer"
                @click="handleSaveSingleLocation(activeEditLocation)"
              >
                <UIcon
                  :name="savingLocationId === activeEditLocation.localId ? 'i-lucide-loader-2' : 'i-lucide-save'"
                  class="w-4 h-4 shrink-0"
                  :class="{ 'animate-spin': savingLocationId === activeEditLocation.localId }"
                />
                <span>
                  {{
                    savingLocationId === activeEditLocation.localId
                      ? `Menyimpan Lokasi #${activeEditLocation.urutan}...`
                      : `Simpan Lokasi #${activeEditLocation.urutan} ke Database`
                  }}
                </span>
              </button>
            </template>
          </div>

          <!-- Multi-Location List (Tampilan Daftar tanpa perlu scroll melewati form) -->
          <div v-show="routeSubView === 'list'" class="space-y-2.5">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                  Daftar Lokasi LineString ({{ locations.length }})
                </h3>
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 tabular-nums">
                  Total GIS: {{ formatNumber(totalPanjangGis) }} m
                </span>
              </div>
              <button
                type="button"
                class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer shrink-0"
                @click="switchRouteSubView('create')"
              >
                <UIcon name="i-lucide-plus-circle" class="w-4 h-4" />
                <span>Tambah Lokasi</span>
              </button>
            </div>

            <div
              v-if="locations.length === 0"
              class="p-6 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-2.5"
            >
              <UIcon name="i-lucide-map-pin-plus" class="w-7 h-7 text-slate-400 mx-auto" />
              <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                Belum Ada Lokasi LineString
              </p>
              <p class="text-xs text-slate-600 dark:text-slate-400">
                Buka tab Tambah lalu klik Titik A dan B di peta untuk membentuk ruas lokasi.
              </p>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold cursor-pointer"
                @click="switchRouteSubView('create')"
              >
                <UIcon name="i-lucide-plus-circle" class="w-4 h-4" />
                <span>Tambah Lokasi Pertama</span>
              </button>
            </div>

            <div
              v-for="lok in locations"
              :key="lok.localId"
              class="p-3 rounded-xl border transition-all cursor-pointer"
              :class="
                routeFormMode === 'edit' && editBuilder.targetLocalId === lok.localId
                  ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 shadow-xs'
                  : selectedLocalId === lok.localId
                    ? 'border-sky-500 bg-sky-50/40 dark:bg-sky-950/30 shadow-xs'
                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300'
              "
              @click="focusLocationOnMap(lok)"
            >
              <div class="space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="px-1.5 py-0.5 rounded bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 text-xs font-bold tabular-nums">
                        #{{ lok.urutan }}
                      </span>
                      <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
                        {{ lok.nama_lokasi }}
                      </span>
                      <span
                        v-if="routeFormMode === 'edit' && editBuilder.targetLocalId === lok.localId"
                        class="px-2 py-0.5 rounded bg-indigo-700 text-white text-xs font-semibold"
                      >
                        Sedang Diedit
                      </span>
                    </div>

                    <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border"
                        :class="getSumberRuteMeta(lok.sumber_rute).badgeClass"
                      >
                        {{ getSumberRuteMeta(lok.sumber_rute).label }}
                      </span>
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border"
                        :class="getSpatialStatusMeta(lok.status_analisa_spasial).badgeClass"
                      >
                        {{ getSpatialStatusMeta(lok.status_analisa_spasial).label }}
                      </span>
                    </div>
                  </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                  <button
                    type="button"
                    class="px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg border text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                    :class="
                      routeFormMode === 'edit' && editBuilder.targetLocalId === lok.localId
                        ? 'border-indigo-600 bg-indigo-700 text-white'
                        : 'border-indigo-300 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-800 dark:text-indigo-200 hover:bg-indigo-100'
                    "
                    title="Edit Lokasi Ini di Form Edit Lokasi"
                    aria-label="Edit Lokasi Ini"
                    @click.stop="enterEditLocationForm(lok)"
                  >
                    <UIcon name="i-lucide-square-pen" class="w-4 h-4" />
                    <span>Edit</span>
                  </button>

                  <button
                    type="button"
                    class="px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg border text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                    :class="
                      isReshapeMode && activeLocation?.localId === lok.localId
                        ? 'border-amber-500 bg-amber-600 text-white'
                        : 'border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 hover:bg-amber-100'
                    "
                    title="Reshape Geometri Manual di Peta"
                    aria-label="Reshape Geometri Manual"
                    @click.stop="
                      isReshapeMode && activeLocation?.localId === lok.localId
                        ? stopReshapeMode(true)
                        : startReshapeMode(lok)
                    "
                  >
                    <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4" />
                    <span>{{ isReshapeMode && activeLocation?.localId === lok.localId ? 'Selesai' : 'Reshape' }}</span>
                  </button>

                  <button
                    type="button"
                    class="w-11 h-11 sm:w-9 sm:h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-sky-700 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                    title="Lihat Detail Analisa & Peta"
                    aria-label="Lihat Detail Analisa"
                    @click.stop="
                      focusLocationOnMap(lok);
                      activeLeftSection = 'analisa';
                    "
                  >
                    <UIcon name="i-lucide-scan-search" class="w-4 h-4" />
                  </button>

                  <button
                    type="button"
                    class="w-11 h-11 sm:w-9 sm:h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer"
                    title="Hapus Lokasi Ini"
                    aria-label="Hapus Lokasi Ini"
                    @click.stop="removeLocationItem(lok)"
                  >
                    <UIcon name="i-lucide-trash-2" class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div class="mt-2.5 pt-2.5 border-t border-slate-200/70 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs tabular-nums">
                <div>
                  <span class="text-slate-600 dark:text-slate-300 block text-xs">Panjang Rencana</span>
                  <strong class="text-slate-900 dark:text-slate-100">
                    {{ lok.panjang_rencana_m !== null && lok.panjang_rencana_m !== undefined ? `${formatNumber(lok.panjang_rencana_m)} m` : '-' }}
                  </strong>
                </div>
                <div>
                  <span class="text-slate-600 dark:text-slate-300 block text-xs">Panjang GIS</span>
                  <strong class="text-slate-900 dark:text-slate-100">{{ formatNumber(lok.panjang_gis_m) }} m</strong>
                </div>
                <div>
                  <span class="text-slate-600 dark:text-slate-300 block text-xs">Koridor Poros</span>
                  <strong class="text-indigo-700 dark:text-indigo-300">
                    {{ formatNumber(lok.ringkasan_analisa?.total_irisan_jalan_poros_m ?? 0) }} m
                  </strong>
                </div>
                <div>
                  <span class="text-slate-600 dark:text-slate-300 block text-xs">Irisan Segmen</span>
                  <strong class="text-emerald-700 dark:text-emerald-300">
                    {{ formatNumber(lok.ringkasan_analisa?.total_irisan_segmen_m ?? 0) }} m
                  </strong>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- TAB 3: HASIL ANALISA SPASIAL (JALAN_POROSDESA & INFRASTRUKTUR_SEGMEN) + GAMBAR PETA -->
        <section v-show="activeLeftSection === 'analisa'" class="space-y-4">
          <div v-if="!activeLocation" class="p-6 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-2">
            <UIcon name="i-lucide-layers" class="w-8 h-8 text-slate-400 mx-auto" />
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
              Belum Ada Lokasi untuk Dianalisa
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Tambahkan titik awal dan akhir pada tab <strong>2. Rute</strong> untuk melihat hasil analisa spasial dan gambar peta.
            </p>
          </div>

          <template v-else>
            <!-- Location Stepper Selector: <- Nama Ruas -> -->
            <div
              v-if="locations.length > 0"
              class="flex items-center justify-between gap-2 p-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs"
            >
              <button
                type="button"
                :disabled="activeLocationIndex <= 0"
                class="w-11 h-11 sm:w-9 sm:h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer shrink-0"
                title="Lokasi Sebelumnya"
                aria-label="Lokasi Sebelumnya"
                @click="stepActiveLocation(-1)"
              >
                <UIcon name="i-lucide-arrow-left" class="w-4 h-4" />
              </button>

              <button
                type="button"
                class="flex-1 min-w-0 min-h-[44px] sm:min-h-[36px] px-2 py-1 rounded-lg text-center hover:bg-slate-50 dark:hover:bg-slate-800/70 transition-colors cursor-pointer"
                title="Fokus ke Ruas Ini di Peta"
                @click="focusLocationOnMap(activeLocation)"
              >
                <div class="flex items-center justify-center gap-1.5 min-w-0">
                  <span class="px-1.5 py-0.5 rounded bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 text-xs font-bold tabular-nums shrink-0">
                    #{{ activeLocation.urutan }}
                  </span>
                  <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
                    {{ activeLocation.nama_ruas || activeLocation.nama_lokasi }}
                  </span>
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-300 tabular-nums truncate">
                  {{ activeLocationIndex + 1 }} / {{ locations.length }} · {{ activeLocation.nama_lokasi }}
                </div>
              </button>

              <button
                type="button"
                :disabled="activeLocationIndex >= locations.length - 1"
                class="w-11 h-11 sm:w-9 sm:h-9 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer shrink-0"
                title="Lokasi Berikutnya"
                aria-label="Lokasi Berikutnya"
                @click="stepActiveLocation(1)"
              >
                <UIcon name="i-lucide-arrow-right" class="w-4 h-4" />
              </button>
            </div>

            <!-- Rendered Map Image Card (GAMBAR PETA HASIL ANALISA) -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-900 overflow-hidden shadow-xs">
              <div class="px-3.5 py-2.5 border-b border-slate-800 flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2 min-w-0">
                  <UIcon name="i-lucide-image" class="w-4 h-4 text-sky-400 shrink-0" />
                  <span class="text-xs font-bold text-slate-100 truncate">
                    Gambar Peta Hasil Analisa (Lokasi #{{ activeLocation.urutan }})
                  </span>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <button
                    type="button"
                    class="px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 cursor-pointer"
                    @click="activeLocation.snapshot_data_url = generateLocationSnapshotCanvas(activeLocation)"
                  >
                    Refresh Gambar
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 px-2.5 py-1 min-h-[44px] sm:min-h-[36px] rounded-lg text-xs font-semibold bg-sky-700 hover:bg-sky-600 text-white cursor-pointer"
                    @click="downloadSnapshotPng(activeLocation)"
                  >
                    <UIcon name="i-lucide-download" class="w-3.5 h-3.5" />
                    <span>Unduh PNG</span>
                  </button>
                </div>
              </div>

              <div class="relative aspect-[16/9] w-full bg-slate-950 flex items-center justify-center">
                <img
                  v-if="activeLocation.snapshot_data_url"
                  :src="activeLocation.snapshot_data_url"
                  :alt="`Peta Hasil Analisa ${activeLocation.nama_lokasi}`"
                  class="w-full h-full object-contain"
                />
                <div v-else class="text-center p-4">
                  <button
                    type="button"
                    class="px-3.5 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-sky-600 text-white text-xs font-semibold cursor-pointer"
                    @click="activeLocation.snapshot_data_url = generateLocationSnapshotCanvas(activeLocation)"
                  >
                    Render Gambar Peta Analisa
                  </button>
                </div>
              </div>
            </div>

            <!-- Spatial Recommendation Banner -->
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 space-y-2">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold border"
                  :class="getSpatialStatusMeta(activeLocation.status_analisa_spasial).badgeClass"
                >
                  {{ getSpatialStatusMeta(activeLocation.status_analisa_spasial).label }}
                </span>
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border"
                  :class="getSumberRuteMeta(activeLocation.sumber_rute).badgeClass"
                >
                  Generator: {{ getSumberRuteMeta(activeLocation.sumber_rute).label }}
                </span>
              </div>
              <p class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed">
                {{
                  activeLocation.ringkasan_analisa?.rekomendasi_bappeda ||
                  'Garis LineString telah diplot dan siap dianalisa terhadap Jalan Poros Desa dan Infrastruktur Segmen.'
                }}
              </p>

              <div class="pt-1 flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                  :class="
                    isReshapeMode
                      ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900'
                      : 'border-amber-400 dark:border-amber-700 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200'
                  "
                  @click="isReshapeMode ? stopReshapeMode(true) : startReshapeMode(activeLocation)"
                >
                  <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4" />
                  <span>{{ isReshapeMode ? 'Selesai Reshape Manual' : 'Reshape Geometri Manual' }}</span>
                </button>

                <button
                  v-if="isReshapeMode || hasUnanalyzedManualEdit"
                  type="button"
                  :disabled="generatingRoute"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer"
                  @click="handleAnalyzeManualReshape"
                >
                  <UIcon
                    :name="generatingRoute ? 'i-lucide-loader-2' : 'i-lucide-check-circle-2'"
                    class="w-4 h-4"
                    :class="{ 'animate-spin': generatingRoute }"
                  />
                  <span>Analisa Ulang (Tanpa Hit ORS)</span>
                </button>

                <button
                  v-if="!isCreateMode && canEdit"
                  type="button"
                  :disabled="savingLocationId === activeLocation.localId"
                  class="px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer"
                  @click="handleSaveSingleLocation(activeLocation)"
                >
                  <UIcon
                    :name="savingLocationId === activeLocation.localId ? 'i-lucide-loader-2' : 'i-lucide-save'"
                    class="w-4 h-4"
                    :class="{ 'animate-spin': savingLocationId === activeLocation.localId }"
                  />
                  <span>{{ savingLocationId === activeLocation.localId ? 'Menyimpan...' : 'Simpan Lokasi Ini' }}</span>
                </button>
              </div>
            </div>

            <!-- 4-Metric Spatial Comparison Grid -->
            <div class="grid grid-cols-2 gap-2.5 tabular-nums">
              <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <span class="block text-xs text-slate-600 dark:text-slate-300">
                  Panjang Garis LineString
                </span>
                <strong class="text-base font-bold text-slate-900 dark:text-slate-100">
                  {{ formatNumber(activeLocation.panjang_gis_m) }} m
                </strong>
              </div>

              <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <span class="block text-xs text-slate-600 dark:text-slate-300">
                  Koridor Jalan Poros Desa
                </span>
                <strong class="text-base font-bold text-indigo-700 dark:text-indigo-300">
                  {{ formatNumber(activeLocation.ringkasan_analisa?.total_irisan_jalan_poros_m ?? 0) }} m
                  <span class="text-xs font-normal">
                    ({{ formatNumber(activeLocation.ringkasan_analisa?.persen_koridor_jalan_poros ?? 0, 1) }}%)
                  </span>
                </strong>
              </div>

              <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <span class="block text-xs text-slate-600 dark:text-slate-300">
                  Irisan Infrastruktur Segmen
                </span>
                <strong class="text-base font-bold text-emerald-700 dark:text-emerald-300">
                  {{ formatNumber(activeLocation.ringkasan_analisa?.total_irisan_segmen_m ?? 0) }} m
                  <span class="text-xs font-normal">
                    ({{ formatNumber(activeLocation.ringkasan_analisa?.persen_irisan_segmen ?? 0, 1) }}%)
                  </span>
                </strong>
              </div>

              <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <span class="block text-xs text-slate-600 dark:text-slate-300">
                  Sisa Belum Terbangun
                </span>
                <strong class="text-base font-bold text-sky-700 dark:text-sky-300">
                  {{
                    formatNumber(
                      activeLocation.ringkasan_analisa?.sisa_belum_terbangun_m ??
                        activeLocation.panjang_gis_m
                    )
                  }}
                  m
                </strong>
              </div>
            </div>

            <!-- Detail Analisa Layer 1: JALAN_POROSDESA -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div class="px-3.5 py-2.5 bg-indigo-50/70 dark:bg-indigo-950/40 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-indigo-950 dark:text-indigo-200">
                  1. Analisa Koridor Jalan Poros Desa (<code>jalan_porosdesa</code>)
                </span>
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 tabular-nums shrink-0">
                  {{ activeLocation.ringkasan_analisa?.referensi_jalan_poros?.length || 0 }} Ruas
                </span>
              </div>

              <div
                v-if="!activeLocation.ringkasan_analisa?.referensi_jalan_poros?.length"
                class="p-3.5 text-xs text-slate-600 dark:text-slate-300"
              >
                Lokasi LineString berada di luar koridor <code>jalan_porosdesa</code> (Jalan Lingkungan / Non-Poros, dirutekan menggunakan OpenRouteService).
              </div>

              <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                <div
                  v-for="jp in activeLocation.ringkasan_analisa.referensi_jalan_poros"
                  :key="jp.id_jalan_poros"
                  class="p-3 text-xs space-y-1.5 bg-white dark:bg-slate-900"
                >
                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <strong class="text-slate-900 dark:text-slate-100">
                      [Kode {{ jp.kode_ruas || '-' }}] {{ jp.nama_ruas }}
                    </strong>
                    <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 text-xs font-semibold tabular-nums">
                      Irisan: {{ formatNumber(jp.panjang_irisan_m) }} m
                    </span>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-slate-600 dark:text-slate-300 tabular-nums">
                    <div>Panjang Ruas: {{ formatNumber(jp.panjang_ruas_m) }} m</div>
                    <div>Perkerasan: {{ jp.perkerasan || '-' }}</div>
                    <div>Kondisi: {{ jp.kondisi || '-' }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detail Analisa Layer 2: INFRASTRUKTUR_SEGMEN -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div class="px-3.5 py-2.5 bg-emerald-50/70 dark:bg-emerald-950/40 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-emerald-950 dark:text-emerald-200">
                  2. Analisa Realisasi Fisik (<code>infrastruktur_segmen</code>)
                </span>
                <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300 tabular-nums shrink-0">
                  {{ activeLocation.ringkasan_analisa?.referensi_infrastruktur_segmen?.length || 0 }} Segmen
                </span>
              </div>

              <div
                v-if="!activeLocation.ringkasan_analisa?.referensi_infrastruktur_segmen?.length"
                class="p-3.5 text-xs text-slate-600 dark:text-slate-300"
              >
                Tidak ditemukan tumpang tindih dengan <code>infrastruktur_segmen</code> eksisting pada koridor ini (100% segmen belum terbangun).
              </div>

              <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                <div
                  v-for="seg in activeLocation.ringkasan_analisa.referensi_infrastruktur_segmen"
                  :key="seg.id_segmen"
                  class="p-3 text-xs space-y-1.5 bg-white dark:bg-slate-900"
                >
                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <strong class="text-slate-900 dark:text-slate-100">
                      {{ seg.namobj }}
                    </strong>
                    <span class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/70 text-rose-800 dark:text-rose-300 text-xs font-semibold tabular-nums">
                      Irisan: {{ formatNumber(seg.panjang_irisan_m) }} m ({{ formatNumber(seg.persen_irisan_usulan, 1) }}%)
                    </span>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-slate-600 dark:text-slate-300 tabular-nums">
                    <div>Kondisi: {{ seg.kondisi || '-' }}</div>
                    <div>Tahun: {{ seg.tahun_pembangunan || '-' }}</div>
                    <div>Sumber: {{ seg.sumber_dana || '-' }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detail Analisa Layer 3: JALAN_KABUPATEN_LUARKOTA -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div class="px-3.5 py-2.5 bg-rose-50/70 dark:bg-rose-950/40 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-rose-950 dark:text-rose-200">
                  3. Analisa Kewenangan Jalan Kabupaten (<code>jalan_kabupaten_luarkota</code>)
                </span>
                <span class="text-xs font-bold text-rose-700 dark:text-rose-300 tabular-nums shrink-0">
                  {{ activeLocation.ringkasan_analisa?.referensi_jalan_kabupaten?.length || 0 }} Ruas
                </span>
              </div>

              <div
                v-if="!activeLocation.ringkasan_analisa?.referensi_jalan_kabupaten?.length"
                class="p-3.5 text-xs text-slate-600 dark:text-slate-300"
              >
                Tidak ditemukan irisan dengan ruas <code>jalan_kabupaten_luarkota</code> (bebas tumpang tindih kewenangan jalan kabupaten).
              </div>

              <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                <div
                  v-for="jk in activeLocation.ringkasan_analisa.referensi_jalan_kabupaten"
                  :key="jk.id_jalan_kabupaten"
                  class="p-3 text-xs space-y-1.5 bg-white dark:bg-slate-900"
                >
                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <strong class="text-slate-900 dark:text-slate-100">
                      [No. {{ jk.no_ruas || '-' }}] {{ jk.nama_ruas }}
                    </strong>
                    <span class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/70 text-rose-800 dark:text-rose-300 text-xs font-semibold tabular-nums">
                      Irisan: {{ formatNumber(jk.panjang_irisan_m) }} m ({{ formatNumber(jk.persen_masuk_koridor, 1) }}%)
                    </span>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-slate-600 dark:text-slate-300 tabular-nums">
                    <div>Panjang Ruas: {{ formatNumber(jk.panjang_ruas_m) }} m</div>
                    <div>Klas / Status: {{ jk.klas_ruas || '-' }} / {{ jk.status_ruas || 'Jalan Kabupaten' }}</div>
                    <div>Kecamatan: {{ jk.kecamatan || '-' }}</div>
                  </div>
                  <div v-if="jk.pangkal || jk.ujung" class="text-[11px] text-slate-600 dark:text-slate-300">
                    Pangkal: {{ jk.pangkal || '-' }} · Ujung: {{ jk.ujung || '-' }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Detail Analisa Layer 3: PROPOSAL LINTAS TAHUN & TAGGING RKPD / PRKPD -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div class="px-3.5 py-2.5 bg-amber-50/70 dark:bg-amber-950/40 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-amber-950 dark:text-amber-200">
                  3. Analisa Proposal Desa & Tagging RKPD / PRKPD
                </span>
                <span class="text-xs font-bold text-amber-800 dark:text-amber-300 tabular-nums shrink-0">
                  {{ activeLocation.ringkasan_analisa?.referensi_tahun_lalu?.length || 0 }} Proposal
                </span>
              </div>

              <div
                v-if="!activeLocation.ringkasan_analisa?.referensi_tahun_lalu?.length"
                class="p-3.5 text-xs text-slate-600 dark:text-slate-300"
              >
                Tidak ditemukan irisan lokasi dengan proposal PRKPD / RKPD pada tahun yang sama, tahun sebelumnya, maupun perencanaan tahun berikutnya.
              </div>

              <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                <div
                  v-for="refProp in activeLocation.ringkasan_analisa.referensi_tahun_lalu"
                  :key="`${refProp.id_proposal_lama}-${refProp.id_lokasi_lama}`"
                  class="p-3 text-xs space-y-1.5 bg-white dark:bg-slate-900"
                >
                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                      <span
                        class="px-2 py-0.5 rounded text-xs font-semibold tabular-nums border"
                        :class="
                          refProp.tagging_usulan === 'RKPD'
                            ? 'bg-emerald-50 text-emerald-900 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-200 dark:border-emerald-800'
                            : 'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/70 dark:text-sky-200 dark:border-sky-800'
                        "
                      >
                        {{ refProp.tagging_usulan || 'PRKPD' }} {{ refProp.tahun_anggaran }}
                      </span>
                      <span
                        class="px-2 py-0.5 rounded text-xs font-semibold tabular-nums border"
                        :class="
                          refProp.pasangan_tagging === 'prkpd_vs_rkpd'
                            ? 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/70 dark:text-amber-200 dark:border-amber-800'
                            : refProp.pasangan_tagging === 'rkpd_vs_prkpd'
                              ? 'bg-indigo-50 text-indigo-900 border-indigo-300 dark:bg-indigo-950/70 dark:text-indigo-200 dark:border-indigo-800'
                              : refProp.tahun_anggaran > form.tahun_anggaran
                                ? 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/70 dark:text-amber-200 dark:border-amber-800'
                                : refProp.tahun_anggaran === form.tahun_anggaran
                                  ? 'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/70 dark:text-sky-200 dark:border-sky-800'
                                  : 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700'
                        "
                      >
                        {{
                          refProp.pasangan_tagging === 'prkpd_vs_rkpd'
                            ? 'PRKPD vs RKPD'
                            : refProp.pasangan_tagging === 'rkpd_vs_prkpd'
                              ? 'RKPD vs PRKPD'
                              : refProp.tahun_anggaran > form.tahun_anggaran
                                ? `Perencanaan T+${refProp.tahun_anggaran - form.tahun_anggaran}`
                                : refProp.tahun_anggaran === form.tahun_anggaran
                                  ? 'Tahun Sama'
                                  : `Usulan ${refProp.tahun_anggaran}`
                        }}
                      </span>
                      <strong class="text-slate-900 dark:text-slate-100 truncate">
                        {{ refProp.nomor_registrasi }} · {{ refProp.judul_kegiatan }}
                      </strong>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/70 text-rose-800 dark:text-rose-300 text-xs font-semibold tabular-nums">
                      Irisan: {{ formatNumber(refProp.panjang_irisan_usulan_m) }} m
                    </span>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-slate-600 dark:text-slate-300 tabular-nums">
                    <div>Lokasi: {{ refProp.nama_lokasi_lama }}</div>
                    <div>Panjang Usulan: {{ formatNumber(refProp.panjang_usulan_lama_m) }} m</div>
                    <div>Realisasi: {{ formatNumber(refProp.persen_realisasi, 1) }}%</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. Inventaris & Validasi Promosi ke Master Jaringan Jalan Kabupaten -->
            <div v-if="activeLocation.id" class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              <div class="px-3.5 py-2.5 bg-teal-50/70 dark:bg-teal-950/40 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-teal-950 dark:text-teal-200">
                  4. Validasi Promosi ke Master Jaringan Jalan
                </span>
                <span
                  class="px-2 py-0.5 rounded text-[11px] font-semibold border shrink-0"
                  :class="
                    activeLocation.status_promosi_master === 'disetujui'
                      ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-200 dark:border-emerald-700'
                      : activeLocation.status_promosi_master === 'diajukan'
                        ? 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/60 dark:text-amber-200 dark:border-amber-700'
                        : activeLocation.status_promosi_master === 'kandidat'
                          ? 'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/60 dark:text-sky-200 dark:border-sky-700'
                          : 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'
                  "
                >
                  {{
                    activeLocation.status_promosi_master === 'disetujui'
                      ? 'Terdaftar di Master'
                      : activeLocation.status_promosi_master === 'diajukan'
                        ? 'Menunggu Validasi'
                        : activeLocation.status_promosi_master === 'kandidat'
                          ? 'Kandidat Ruas Baru'
                          : activeLocation.status_promosi_master === 'ditolak'
                            ? 'Ditolak'
                            : 'Tidak Perlu / Gagal Cek'
                  }}
                </span>
              </div>

              <div class="p-3.5 space-y-3 bg-white dark:bg-slate-900 text-xs">
                <div
                  v-if="activeLocation.validasi_promosi_spasial?.checks?.length"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-2"
                >
                  <div
                    v-for="chk in activeLocation.validasi_promosi_spasial.checks"
                    :key="chk.kode"
                    class="p-2 rounded-lg border text-[11px] space-y-0.5"
                    :class="
                      chk.lolos
                        ? 'border-emerald-200 bg-emerald-50/60 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                        : 'border-rose-200 bg-rose-50/60 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
                    "
                  >
                    <div class="font-bold flex items-center justify-between gap-1">
                      <span>{{ chk.label }}</span>
                      <span>{{ chk.lolos ? 'LOLOS' : 'GAGAL' }}</span>
                    </div>
                    <p class="opacity-90">{{ chk.detail }}</p>
                  </div>
                </div>

                <div
                  v-if="activeLocation.jaringan_ruas"
                  class="p-2.5 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-900 dark:text-emerald-200 flex items-center justify-between gap-2"
                >
                  <span>
                    Terhubung ke Master Ruas:
                    <strong>{{ activeLocation.jaringan_ruas.kode_ruas }} · {{ activeLocation.jaringan_ruas.nama_ruas }}</strong>
                  </span>
                  <NuxtLink
                    to="/admin/jaringan-jalan"
                    class="font-semibold text-emerald-800 dark:text-emerald-200 underline shrink-0"
                  >
                    Buka Modul Jaringan Jalan
                  </NuxtLink>
                </div>

                <div
                  v-else-if="canEdit && activeLocation.validasi_promosi_spasial?.lolos_validasi"
                  class="space-y-2.5 pt-1"
                >
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                      <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Nama Ruas Jalan Resmi
                      </label>
                      <input
                        v-model="promotionForm.nama_ruas"
                        type="text"
                        placeholder="Contoh: Jl. Dusun Krajan RT 02"
                        class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                      />
                    </div>
                    <div>
                      <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Hierarki Jalan
                      </label>
                      <select
                        v-model="promotionForm.hierarki_jalan"
                        class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                      >
                        <option value="lingkungan">Jalan Lingkungan</option>
                        <option value="poros_desa">Jalan Poros Desa</option>
                        <option value="usaha_tani">Jalan Usaha Tani</option>
                        <option value="kabupaten">Jalan Kabupaten</option>
                      </select>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <button
                      v-if="activeLocation.status_promosi_master !== 'diajukan'"
                      type="button"
                      :disabled="promotionForm.submitting"
                      class="inline-flex items-center gap-1.5 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg border border-sky-700 text-sky-800 dark:text-sky-200 hover:bg-sky-50 dark:hover:bg-sky-950/50 text-xs font-semibold cursor-pointer disabled:opacity-50"
                      @click="handleAjukanPromosiMaster"
                    >
                      <UIcon name="i-lucide-send" class="w-3.5 h-3.5" aria-hidden="true" />
                      <span>Ajukan ke Antrean Master Jalan</span>
                    </button>

                    <div v-if="isBappedaOrAdmin" class="flex flex-wrap items-center gap-2 ml-auto">
                      <select
                        v-model="promotionForm.keputusan"
                        aria-label="Keputusan Validasi Ruas"
                        class="min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                      >
                        <option value="setujui_baru">Setujui Sebagai Ruas Baru</option>
                        <option value="ekstensi_potong_unik">Potong Irisan & Tambah Unik</option>
                        <option value="tolak">Tolak Promosi</option>
                      </select>
                      <button
                        type="button"
                        :disabled="promotionForm.submitting"
                        class="inline-flex items-center gap-1.5 px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg text-white text-xs font-semibold cursor-pointer disabled:opacity-50"
                        :class="promotionForm.keputusan === 'tolak' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-emerald-700 hover:bg-emerald-800'"
                        @click="handleValidasiPromosiMaster"
                      >
                        <UIcon name="i-lucide-shield-check" class="w-4 h-4" aria-hidden="true" />
                        <span>{{ promotionForm.keputusan === 'tolak' ? 'Tolak Promosi' : 'Validasi Masuk Master' }}</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Catatan Konfirmasi Bappeda / Desa -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Catatan Hasil Analisa / Konfirmasi Lapangan
              </label>
              <textarea
                v-model="form.catatan_konfirmasi_desa"
                rows="2"
                placeholder="Catat hasil verifikasi spasial atau konfirmasi batas segmen dengan Pemerintah Desa..."
                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-slate-100"
              />
            </div>

            <div v-if="!isCreateMode" class="pt-1 flex justify-end">
              <button
                type="button"
                :disabled="reanalyzing"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                @click="handleReanalyzeSavedProposal"
              >
                <UIcon
                  :name="reanalyzing ? 'i-lucide-loader-2' : 'i-lucide-refresh-cw'"
                  class="w-4 h-4"
                  :class="{ 'animate-spin': reanalyzing }"
                />
                <span>Analisa Ulang Spasial dari Database</span>
              </button>
            </div>
          </template>
        </section>
      </div>
    </aside>

    <!-- FULL-BLEED INTERACTIVE OPENLAYERS MAP WORKSPACE -->
    <main class="absolute inset-0 w-full h-full bg-slate-900 overflow-hidden z-0">
      <div ref="mapContainer" class="w-full h-full" />

      <!-- Mobile Bottom Sheet Trigger (when Panel is Collapsed on < 640px) -->
      <button
        v-if="!rightPanelOpen"
        type="button"
        class="sm:hidden fixed bottom-4 inset-x-4 z-30 inline-flex items-center justify-center gap-2 px-4 py-2 min-h-[44px] rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg text-xs font-semibold text-slate-900 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        @click="toggleRightPanel(true)"
      >
        <UIcon name="i-lucide-panel-bottom-open" class="w-4 h-4 text-sky-700 dark:text-sky-400" />
        <span>Buka Panel Analisa ({{ locations.length }})</span>
      </button>

      <!-- Desktop/Tablet Collapsed Vertical Slide Rail (when Panel is Collapsed on >= 640px) -->
      <button
        type="button"
        :aria-hidden="rightPanelOpen"
        :tabindex="rightPanelOpen ? -1 : 0"
        class="hidden sm:flex absolute top-0 bottom-0 right-0 z-20 w-12 flex-col items-center justify-between py-3.5 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-l border-slate-200 dark:border-slate-800 shadow-lg text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800 transition-[translate,opacity,background-color] duration-300 ease-out cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-700"
        :class="
          rightPanelOpen
            ? 'translate-x-full opacity-0 pointer-events-none'
            : 'translate-x-0 opacity-100 delay-100'
        "
        title="Buka Panel Form & Analisa Proposal"
        aria-label="Buka Panel Form & Analisa Proposal"
        @click="toggleRightPanel(true)"
      >
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
          <UIcon name="i-lucide-panel-right-open" class="w-4 h-4" aria-hidden="true" />
        </span>

        <span class="flex items-center gap-2 [writing-mode:vertical-rl] rotate-180 select-none text-xs font-bold tracking-wide text-slate-700 dark:text-slate-200 whitespace-nowrap">
          <span>Panel Form &amp; Analisa</span>
          <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[11px] font-semibold tabular-nums text-sky-700 dark:text-sky-300">
            {{ locations.length }}
          </span>
        </span>

        <UIcon name="i-lucide-chevron-left" class="w-4 h-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />
      </button>

      <!-- Floating Top Map Controls (Slim Single-Row on Mobile, Full Toolbar on Tablet/Desktop) -->
      <div
        class="absolute top-3 left-3 flex flex-wrap items-start justify-between gap-2 pointer-events-none z-10 transition-[right] duration-300 ease-out"
        :class="
          rightPanelOpen
            ? 'right-16 sm:right-[452px] lg:right-[500px] xl:right-[524px]'
            : 'right-16 sm:right-16'
        "
      >
        <!-- Active Click Target Switcher OR Reshape Mode Toolbar -->
        <div class="pointer-events-auto max-w-full">
          <!-- Normal Plotting Mode: Compact Segmented Toggle (Mobile) + Full Pill (Desktop) -->
          <div
            v-if="!isReshapeMode && !isManualDrawMode"
            class="inline-flex items-center gap-1 p-1 rounded-xl bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-700 shadow-sm text-xs max-w-full overflow-x-auto"
          >
            <span
              v-if="routeFormMode === 'edit' && activeEditLocation"
              class="hidden sm:inline-flex items-center px-2 text-xs font-semibold text-indigo-700 dark:text-indigo-300 shrink-0"
            >
              Edit #{{ activeEditLocation.urutan }}
            </span>

            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg font-semibold inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer transition-colors"
              :class="
                clickTarget === 'awal'
                  ? 'bg-emerald-700 text-white shadow-xs'
                  : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
              "
              @click="selectMapClickTarget('awal')"
            >
              <span
                class="w-4 h-4 rounded-full text-[11px] font-bold inline-flex items-center justify-center shrink-0 tabular-nums"
                :class="clickTarget === 'awal' ? 'bg-white text-emerald-800' : 'bg-emerald-700 text-white'"
              >
                A
              </span>
              <span>Titik A</span>
            </button>

            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg font-semibold inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer transition-colors"
              :class="
                clickTarget === 'akhir'
                  ? 'bg-rose-700 text-white shadow-xs'
                  : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
              "
              @click="selectMapClickTarget('akhir')"
            >
              <span
                class="w-4 h-4 rounded-full text-[11px] font-bold inline-flex items-center justify-center shrink-0 tabular-nums"
                :class="clickTarget === 'akhir' ? 'bg-white text-rose-800' : 'bg-rose-700 text-white'"
              >
                B
              </span>
              <span>Titik B</span>
            </button>

            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border border-sky-300 dark:border-sky-700 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/60 text-sky-900 dark:text-sky-200 text-xs font-bold inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
              title="Gambar Garis Manual di Peta"
              @click="startManualDrawMode()"
            >
              <UIcon name="i-lucide-pen-tool" class="w-4 h-4 shrink-0" />
              <span>Gambar</span>
              <span class="hidden sm:inline">Manual</span>
            </button>

            <button
              v-if="activeLocation"
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200 text-xs font-bold inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
              title="Reshape Geometri Manual"
              @click="startReshapeMode()"
            >
              <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4 shrink-0" />
              <span>Reshape</span>
              <span class="hidden sm:inline">Manual</span>
            </button>
          </div>

          <!-- Manual Draw Mode Toolbar -->
          <div
            v-else-if="isManualDrawMode"
            class="inline-flex sm:flex-wrap items-center gap-1.5 p-1.5 rounded-xl bg-sky-50/95 dark:bg-slate-900/95 border border-sky-400 dark:border-sky-600 text-xs shadow-sm max-w-full overflow-x-auto sm:overflow-visible"
          >
            <span class="inline-flex items-center gap-1.5 px-1.5 font-bold text-sky-900 dark:text-sky-200 tabular-nums whitespace-nowrap shrink-0">
              <UIcon name="i-lucide-pen-tool" class="w-4 h-4 shrink-0" />
              <span class="hidden sm:inline">Gambar Manual ({{ manualDrawVertexCount }} titik · {{ formatNumber(manualDrawLengthM) }} m)</span>
              <span class="sm:hidden">{{ manualDrawVertexCount }} titik · {{ formatNumber(manualDrawLengthM) }} m</span>
            </span>
            <button
              type="button"
              :disabled="manualDrawVertexCount === 0"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 disabled:opacity-40 text-xs font-semibold text-slate-700 dark:text-slate-200 whitespace-nowrap shrink-0 cursor-pointer"
              @click="undoManualDrawPoint"
            >
              Undo Titik
            </button>
            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border text-xs font-semibold whitespace-nowrap shrink-0 cursor-pointer"
              :class="
                isReshapeSnapEnabled
                  ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
              "
              @click="toggleReshapeSnap"
            >
              Snap {{ isReshapeSnapEnabled ? 'ON' : 'OFF' }}
            </button>
            <button
              type="button"
              :disabled="manualDrawVertexCount < 2"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-40 text-white text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer"
              @click="finishManualDraw"
            >
              Selesai
            </button>
            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border border-rose-300 dark:border-rose-800 bg-white dark:bg-slate-800 text-rose-700 dark:text-rose-300 text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer"
              @click="stopManualDrawMode"
            >
              Batal
            </button>
          </div>

          <!-- Reshape Mode Toolbar (Single-row Horizontal Scroll on Mobile, Wrapped on Desktop) -->
          <div
            v-else
            class="inline-flex sm:flex-wrap items-center gap-1.5 p-1.5 rounded-xl bg-amber-50/95 dark:bg-slate-900/95 border border-amber-400 dark:border-amber-600 text-xs shadow-sm max-w-full overflow-x-auto sm:overflow-visible"
          >
            <span class="inline-flex items-center gap-1 px-1.5 font-bold text-amber-900 dark:text-amber-200 tabular-nums whitespace-nowrap shrink-0">
              <UIcon name="i-lucide-pencil-ruler" class="w-4 h-4 shrink-0" />
              <span class="hidden sm:inline">Reshape ({{ activeVertexCount }} titik · {{ formatNumber(activeLocation?.panjang_gis_m ?? 0) }} m)</span>
              <span class="sm:hidden">{{ formatNumber(activeLocation?.panjang_gis_m ?? 0) }} m</span>
            </span>
            <button
              type="button"
              :disabled="reshapeHistory.length === 0"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 disabled:opacity-40 text-xs font-semibold text-slate-700 dark:text-slate-200 whitespace-nowrap shrink-0 cursor-pointer"
              @click="undoReshapeStep"
            >
              Undo
            </button>
            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg border text-xs font-semibold whitespace-nowrap shrink-0 cursor-pointer"
              :class="
                isReshapeSnapEnabled
                  ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-200'
                  : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
              "
              @click="toggleReshapeSnap"
            >
              Snap {{ isReshapeSnapEnabled ? 'ON' : 'OFF' }}
            </button>
            <button
              type="button"
              :disabled="generatingRoute"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer"
              @click="handleAnalyzeManualReshape"
            >
              Analisa
            </button>
            <button
              v-if="!isCreateMode && canEdit && activeLocation"
              type="button"
              :disabled="savingLocationId === activeLocation.localId"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer"
              @click="handleSaveSingleLocation(activeLocation)"
            >
              {{ savingLocationId === activeLocation.localId ? 'Simpan...' : 'Simpan' }}
            </button>
            <button
              type="button"
              class="px-2.5 py-1 min-h-[44px] sm:min-h-[34px] rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer"
              @click="stopReshapeMode(true)"
            >
              Selesai
            </button>
          </div>
        </div>

        <!-- Tablet & Desktop (>= 640px): Inline Layer Visibility & Basemap Switcher -->
        <div class="hidden sm:flex pointer-events-auto items-center gap-1.5 flex-wrap max-w-full">
          <div class="hidden md:inline-flex items-center gap-1 rounded-xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-700 p-1 shadow-sm text-xs font-semibold">
            <label class="inline-flex items-center gap-1.5 px-2.5 min-h-[34px] rounded-lg cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
              <input v-model="showLayerUsulan" type="checkbox" class="rounded text-sky-700" />
              <span class="text-slate-800 dark:text-slate-200">Usulan ({{ locations.length }})</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2.5 min-h-[34px] rounded-lg cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
              <input v-model="showLayerJalanPoros" type="checkbox" class="rounded text-indigo-700" />
              <span class="text-indigo-700 dark:text-indigo-300">Jalan Poros</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2.5 min-h-[34px] rounded-lg cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
              <input v-model="showLayerJalanKabupaten" type="checkbox" class="rounded text-rose-700" />
              <span class="text-rose-700 dark:text-rose-300">Jalan Kabupaten</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2.5 min-h-[34px] rounded-lg cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
              <input v-model="showLayerSegmen" type="checkbox" class="rounded text-emerald-700" />
              <span class="text-emerald-700 dark:text-emerald-300">Infrastruktur Segmen</span>
            </label>
          </div>

          <div class="inline-flex items-center rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-1 text-xs font-semibold shadow-sm">
            <button
              v-for="bm in basemapOptions"
              :key="bm.id"
              type="button"
              class="px-2.5 py-1 min-h-[34px] rounded-lg cursor-pointer whitespace-nowrap"
              :class="activeBasemap === bm.id ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-700 dark:text-slate-300'"
              @click="setBasemap(bm.id)"
            >
              {{ bm.label }}
            </button>
          </div>

          <button
            type="button"
            class="w-9 h-9 inline-flex items-center justify-center rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
            title="Fokus ke Seluruh Garis Lokasi"
            aria-label="Fokus ke Seluruh Garis Lokasi"
            @click="fitMapToAllLocations"
          >
            <UIcon name="i-lucide-maximize-2" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Mobile (< 640px): Top-Right Vertical Map Utility Rail (Layers/Basemap Popover + Fit) -->
      <div class="sm:hidden absolute top-3 right-3 z-20 flex flex-col items-end gap-1.5 pointer-events-none">
        <div class="pointer-events-auto relative">
          <button
            type="button"
            class="w-11 h-11 inline-flex items-center justify-center rounded-xl border shadow-sm transition-colors cursor-pointer"
            :class="
              mobileMapMenuOpen
                ? 'bg-sky-700 border-sky-700 text-white'
                : 'bg-white/95 dark:bg-slate-900/95 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800'
            "
            title="Pengaturan Layer & Peta Dasar"
            aria-label="Pengaturan Layer dan Peta Dasar"
            :aria-expanded="mobileMapMenuOpen"
            @click="mobileMapMenuOpen = !mobileMapMenuOpen"
          >
            <UIcon name="i-lucide-layers" class="w-5 h-5" />
          </button>

          <!-- Compact Mobile Layers & Basemap Popover -->
          <div
            v-if="mobileMapMenuOpen"
            class="absolute right-0 top-12 w-60 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl space-y-3 z-30"
          >
            <div>
              <p class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-1.5">Peta Dasar</p>
              <div class="grid grid-cols-2 gap-1 p-1 rounded-lg bg-slate-100 dark:bg-slate-950 text-xs font-semibold">
                <button
                  v-for="bm in basemapOptions"
                  :key="bm.id"
                  type="button"
                  class="py-1.5 px-2 min-h-[36px] rounded-md cursor-pointer"
                  :class="activeBasemap === bm.id ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-700 dark:text-slate-300'"
                  @click="setBasemap(bm.id); mobileMapMenuOpen = false"
                >
                  {{ bm.label }}
                </button>
              </div>
            </div>

            <div class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-slate-800">
              <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Tampilkan Layer</p>
              <label class="flex items-center gap-2 min-h-[36px] px-2 rounded-lg text-xs font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                <input v-model="showLayerUsulan" type="checkbox" class="rounded text-sky-700" />
                <span>Garis Usulan ({{ locations.length }})</span>
              </label>
              <label class="flex items-center gap-2 min-h-[36px] px-2 rounded-lg text-xs font-medium text-indigo-800 dark:text-indigo-300 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                <input v-model="showLayerJalanPoros" type="checkbox" class="rounded text-indigo-700" />
                <span>Jalan Poros Desa</span>
              </label>
              <label class="flex items-center gap-2 min-h-[36px] px-2 rounded-lg text-xs font-medium text-rose-800 dark:text-rose-300 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                <input v-model="showLayerJalanKabupaten" type="checkbox" class="rounded text-rose-700" />
                <span>Jalan Kabupaten</span>
              </label>
              <label class="flex items-center gap-2 min-h-[36px] px-2 rounded-lg text-xs font-medium text-emerald-800 dark:text-emerald-300 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                <input v-model="showLayerSegmen" type="checkbox" class="rounded text-emerald-700" />
                <span>Infrastruktur Segmen</span>
              </label>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="pointer-events-auto w-11 h-11 inline-flex items-center justify-center rounded-xl bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-700 shadow-sm text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
          title="Fokus ke Seluruh Garis Lokasi"
          aria-label="Fokus ke Seluruh Garis Lokasi"
          @click="fitMapToAllLocations"
        >
          <UIcon name="i-lucide-maximize-2" class="w-5 h-5" />
        </button>
      </div>

      <!-- Floating Bottom-Left Map Legend & Live Coordinates (Desktop/Tablet) -->
      <div
        class="hidden sm:flex absolute bottom-3 left-3 flex-wrap items-center justify-between gap-2 pointer-events-none z-10"
        :class="rightPanelOpen ? 'right-3 sm:right-[460px] lg:right-[508px] xl:right-[532px]' : 'right-3'"
      >
        <div class="pointer-events-auto flex flex-wrap items-center gap-3 px-3 py-1.5 rounded-lg bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-700 dark:text-slate-200">
          <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-1 rounded bg-sky-500 inline-block" />
            LineString Usulan
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-0.5 border-b-2 border-dashed border-indigo-500 inline-block" />
            Jalan Poros Desa
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-1 rounded bg-emerald-500 inline-block" />
            Segmen Terbangun
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-1.5 rounded bg-rose-500/80 inline-block" />
            Irisan / Overlap
          </span>
        </div>

        <div
          v-if="hoverCoords"
          class="pointer-events-auto px-2.5 py-1 rounded-lg bg-slate-900 text-slate-100 border border-slate-700 text-xs tabular-nums"
        >
          Lat: {{ hoverCoords.lat.toFixed(6) }} | Lng: {{ hoverCoords.lng.toFixed(6) }}
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
:deep(.ol-zoom) {
  top: 112px;
  right: 12px;
  left: auto;
  bottom: auto;
  background-color: transparent;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

@media (min-width: 640px) {
  :deep(.ol-zoom) {
    top: auto;
    bottom: 52px;
    left: 12px;
    right: auto;
    gap: 4px;
  }
}

:deep(.ol-zoom button) {
  width: 44px;
  height: 44px;
  margin: 0;
  border-radius: 12px;
  border: 1px solid rgb(226 232 240);
  background-color: rgba(255, 255, 255, 0.96);
  color: rgb(15 23 42);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.14);
  cursor: pointer;
}

:deep(.ol-zoom button:hover) {
  background-color: rgb(241 245 249);
}

@media (min-width: 640px) {
  :deep(.ol-zoom button) {
    width: 36px;
    height: 36px;
    border-radius: 10px;
  }
}
</style>

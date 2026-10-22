<script setup lang="ts">
import 'ol/ol.css';
import { $http } from '~/utils/helpers';
import type { GeoJsonFeatureCollection, InfrastrukturSegmen } from '~/types/infrastruktur';
import type { BasemapType, LayerSymbology } from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY, DEFAULT_JALAN_POROS_SYMBOLOGY } from '~/types/dataset-editor';

interface Props {
  apiBase?: string;
  geojsonData?: GeoJsonFeatureCollection | null;
  selectedSegmenId?: string | null;
  selectedSegmen?: InfrastrukturSegmen | null;
  loading?: boolean;
  basemap?: BasemapType;
  tipeFilter?: string | null;
  tahunPembangunanFilter?: number | string | null;
  perkerasanFilter?: string | null;
  kondisiFilter?: string | null;
  statusVerifikasiFilter?: string | null;
  statusJalanFilter?: string | null;
  statusKondisiFilter?: string | null;
  statusAsetFilter?: string | null;
  sumberDanaFilter?: string | null;
  kecamatanFilter?: number | null;
  desaFilter?: number | null;
  layerVisible?: boolean;
  layerOpacity?: number;
  symbology?: LayerSymbology;
  bbox?: [number, number, number, number] | null;
  clickedCoordinate?: [number, number] | null;
  activeLayerId?: string;

  // Jalan Poros Desa Layer Props
  jalanPorosDesaVisible?: boolean;
  jalanPorosDesaOpacity?: number;
  jalanPorosDesaSymbology?: LayerSymbology;
  jalanPorosDesaKecamatanFilter?: number | string | null;
  jalanPorosDesaDesaFilter?: number | string | null;
  jalanPorosDesaKondisiFilter?: string | null;
  jalanPorosDesaPerkerasanFilter?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  apiBase: 'http://localhost:9000',
  activeLayerId: 'infrastruktur-segmen',
  geojsonData: null,
  selectedSegmenId: null,
  selectedSegmen: null,
  loading: false,
  basemap: 'osm',
  tipeFilter: null,
  tahunPembangunanFilter: null,
  perkerasanFilter: null,
  kondisiFilter: null,
  statusVerifikasiFilter: null,
  statusJalanFilter: null,
  statusKondisiFilter: null,
  statusAsetFilter: null,
  sumberDanaFilter: null,
  kecamatanFilter: null,
  desaFilter: null,
  layerVisible: true,
  layerOpacity: 1,
  symbology: () => ({ ...DEFAULT_SYMBOLOGY }),
  bbox: null,
  clickedCoordinate: null,

  jalanPorosDesaVisible: true,
  jalanPorosDesaOpacity: 0.85,
  jalanPorosDesaSymbology: () => ({
    ...DEFAULT_JALAN_POROS_SYMBOLOGY,
  }),
  jalanPorosDesaKecamatanFilter: null,
  jalanPorosDesaDesaFilter: null,
  jalanPorosDesaKondisiFilter: null,
  jalanPorosDesaPerkerasanFilter: null,
});

const emit = defineEmits<{
  (e: 'select', feature: any | null): void;
  (e: 'log', level: 'INFO' | 'WARN' | 'ERROR', message: string): void;
  (e: 'update:basemap', val: BasemapType): void;
  (e: 'update:mouseCoords', coords: string): void;
  (e: 'loaded'): void;
  (e: 'mvtLoading', loading: boolean): void;
  (e: 'drawSaved', payload: { geojson: { type: 'LineString'; coordinates: [number, number][] }; lengthMeters: number }): void;
  (e: 'drawCanceled'): void;
  (e: 'mapClick', coords: [number, number]): void;
  (e: 'editGeometrySaved', payload: { id: string; geojson: any; lengthMeters: number }): void;
  (e: 'editGeometryCanceled'): void;
  (e: 'splitPointSelected', payload: { segmen: InfrastrukturSegmen; coordinate: [number, number] }): void;
}>();

const colorMode = useColorMode();
const config = useRuntimeConfig();
const martinUrl = computed(() => (config.public.martinUrl as string) || '/martin');
const mapContainer = ref<HTMLDivElement | null>(null);
const scaleLineTarget = ref<HTMLDivElement | null>(null);
const tooltipEl = ref<HTMLDivElement | null>(null);
const pulseEl = ref<HTMLDivElement | null>(null);
const isPulseVisible = ref(false);

const currentBasemap = ref<BasemapType>(props.basemap);
const isMapLoaded = ref(false);
const isMvtLoading = ref(false);
let activeTileCount = 0;
let mvtSafetyTimeout: any = null;

const mouseCoordsText = ref('');
const tileVersion = ref(Date.now());

// ─── OpenLayers Instances ─────────────────────────────────────────────────────
let mapInstance: any = null;
let baseTileLayer: any = null;
let vectorTileSource: any = null;
let vectorTileLayer: any = null;
let drawSource: any = null;
let drawLayer: any = null;
let drawInteraction: any = null;
let modifyInteraction: any = null;
let activeGeomListener: any = null;
let tooltipOverlay: any = null;
let pulseOverlay: any = null;
let olModules: any = null;

let editGeometrySource: any = null;
let editGeometryLayer: any = null;
let editModifyInteraction: any = null;
let editSnapInteraction: any = null;
let editGeomListener: any = null;

let splitSource: any = null;
let splitLayer: any = null;
let splitSnapInteraction: any = null;
let splitClickListener: any = null;

const isEditGeometryMode = ref(false);
const editingSegmenData = ref<InfrastrukturSegmen | null>(null);
const editedFeature = ref<any>(null);
const editedLengthMeters = ref(0);

const isSplitMode = ref(false);
const splitSegmenData = ref<InfrastrukturSegmen | null>(null);
const splitSelectedCoord = ref<[number, number] | null>(null);

let resizeObserver: ResizeObserver | null = null;
let resizeRaf: number | null = null;
let pointerRafId: number | null = null;

// Hover & Tooltip / Popup Feature State
export interface PopupFeatureData {
  id: string;
  layerId: string;
  layerTitle: string;
  namobj: string;
  kode_ruas?: string | null;
  tipe_kode?: string | null;
  kondisi: string;
  perkerasan?: string | null;
  panjang: number;
  lebar?: number | null;
  desa: string;
  kecamatan: string;
  sumber_dana?: string | null;
  tahun?: number | string | null;
  status_kondisi?: string | null;
  status_verifikasi?: string | null;
  status_eksisting?: string | null;
  sumber_data?: string | null;
}

const hoveredFeature = ref<any>(null);
const tooltipData = ref<PopupFeatureData | null>(null);
const isPopupPinned = ref(false);

function closePopup() {
  isPopupPinned.value = false;
  tooltipData.value = null;
  hoveredFeature.value = null;
  if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
  vectorTileLayer?.changed();
  jalanPorosVectorTileLayer?.changed();
}

function showTooltipAt(coord: any) {
  if (!tooltipOverlay) return;
  try {
    tooltipOverlay.setOffset([24, -20]);
    tooltipOverlay.setPositioning('bottom-left');
    tooltipOverlay.setPosition(coord);
  } catch {
    tooltipOverlay.setPosition(coord);
  }
}

function getTargetVectorTileLayer() {
  if (props.activeLayerId === 'jalan-poros-desa') {
    return jalanPorosVectorTileLayer;
  }
  return vectorTileLayer;
}

function isTargetLayerVisible() {
  if (props.activeLayerId === 'jalan-poros-desa') {
    return !!props.jalanPorosDesaVisible;
  }
  return !!props.layerVisible;
}

function extractFeatureData(feature: any, layerId: string): PopupFeatureData {
  const p = feature.getProperties?.() ?? {};
  const featId = String(p.id || feature.getId?.() || feature.get?.('id') || '');
  const pMeter = p.panjang_meter_gis != null && p.panjang_meter_gis !== '' ? parseFloat(p.panjang_meter_gis) : null;
  const pManual = p.panjang != null && p.panjang !== '' ? parseFloat(p.panjang) : null;
  const panjang = pMeter ?? pManual ?? 0;
  const lebar = p.lebar != null && p.lebar !== '' ? parseFloat(p.lebar) : null;

  if (layerId === 'jalan-poros-desa') {
    return {
      id: featId,
      layerId: 'jalan-poros-desa',
      layerTitle: 'Jalan Poros Desa',
      namobj: p.nama_ruas || p.namobj || 'Jalan Poros Desa',
      kode_ruas: p.kode_ruas || null,
      tipe_kode: p.kode_ruas || 'Jalan Poros',
      kondisi: p.kondisi || 'Baik',
      perkerasan: p.perkerasan || null,
      panjang,
      lebar,
      desa: p.desa || '-',
      kecamatan: p.kecamatan || '-',
      status_kondisi: p.status_eksisting || p.status_kondisi || null,
      status_eksisting: p.status_eksisting || null,
      sumber_data: p.sumber_data || null,
    };
  }

  return {
    id: featId,
    layerId: 'infrastruktur-segmen',
    layerTitle: 'Segmen Fisik Infrastruktur',
    namobj: p.namobj || 'Segmen Fisik',
    kode_ruas: null,
    tipe_kode: p.tipe_kode || '-',
    kondisi: p.kondisi || 'Baik',
    perkerasan: p.jenis_perkerasan || p.perkerasan || null,
    panjang,
    lebar,
    desa: p.desa || '-',
    kecamatan: p.kecamatan || '-',
    status_kondisi: p.status_kondisi || 'Eksisting',
    status_verifikasi: p.status_verifikasi || 'draft',
    sumber_dana: p.sumber_dana || null,
    tahun: p.tahun_pembangunan || null,
  };
}

watch(
  () => props.activeLayerId,
  () => {
    closePopup();
  }
);

// ─── Digitizing (Draw) State ──────────────────────────────────────────────────
const isDrawing = ref(false);
const hasCompletedLine = ref(false);
const drawnPointCount = ref(0);
const drawnLengthMeters = ref(0);
const drawnFeature = ref<any>(null);
const redoStack = ref<any[]>([]);

// ─── Snapping State ───────────────────────────────────────────────────────────
let snapSource: any = null;
let drawSnapInteraction: any = null;
let networkSnapInteraction: any = null;
const isSnappingEnabled = ref(true);
const snapRoadCount = ref(0);
const isSnapLoading = ref(false);
let snapFetchAbort: AbortController | null = null;
let lastSnapBbox = '';
let snapMoveEndTimer: any = null;

const canUndo = computed(() => {
  if (hasCompletedLine.value) {
    const coords = drawnFeature.value?.getGeometry()?.getCoordinates();
    return Boolean(coords && coords.length > 0);
  }
  return drawnPointCount.value > 0;
});

const canRedo = computed(() => redoStack.value.length > 0);
const canSave = computed(() => drawnPointCount.value >= 2);
const isDrawInteracting = computed(() => isDrawing.value && !hasCompletedLine.value);

// ─── MVT Tile Loader Events ───────────────────────────────────────────────────
function onTileLoadStart() {
  activeTileCount++;
  isMvtLoading.value = true;
  emit('mvtLoading', true);
  clearTimeout(mvtSafetyTimeout);
  mvtSafetyTimeout = setTimeout(() => {
    if (activeTileCount > 0) {
      activeTileCount = 0;
      isMvtLoading.value = false;
      emit('mvtLoading', false);
    }
  }, 8000);
}

function onTileLoadFinish() {
  activeTileCount = Math.max(0, activeTileCount - 1);
  if (activeTileCount === 0) {
    clearTimeout(mvtSafetyTimeout);
    isMvtLoading.value = false;
    emit('mvtLoading', false);
  }
}

function getMvtUrl() {
  const base = martinUrl.value;
  const params = new URLSearchParams();
  params.set('_v', String(tileVersion.value));
  const qs = params.toString();
  return `${base}/infrastruktur_segmen/{z}/{x}/{y}?${qs}`;
}

function refreshVectorTiles(forceVersionBump = true) {
  if (forceVersionBump) {
    tileVersion.value = Date.now();
  }
  if (vectorTileSource) {
    const newUrl = getMvtUrl();
    vectorTileSource.setUrl(newUrl);
    if (forceVersionBump) {
      try {
        if ((vectorTileSource as any).sourceTiles_) {
          (vectorTileSource as any).sourceTiles_ = {};
        }
        if ((vectorTileSource as any).tileKeysBySourceTileUrl_) {
          (vectorTileSource as any).tileKeysBySourceTileUrl_ = {};
        }
        if ((vectorTileSource as any).tileCache) {
          (vectorTileSource as any).tileCache.clear();
        }
        const renderer = (vectorTileLayer as any)?.getRenderer?.();
        if (renderer) {
          renderer.tileCache_?.clear();
          renderer.sourceTileCache_?.clear();
        }
      } catch {
        // ignore
      }
      vectorTileSource.clear();
    }
    vectorTileSource.refresh();
  }
  if (vectorTileLayer) {
    vectorTileLayer.changed();
  }
  if (mapInstance) {
    mapInstance.render();
  }
}

// ─── Jalan Poros Desa MVT Layer Setup ─────────────────────────────────────────
let jalanPorosVectorTileSource: any = null;
let jalanPorosVectorTileLayer: any = null;
const jalanPorosTileVersion = ref(Date.now());
const jalanPorosStyleCache = new Map<string, any>();

function getJalanPorosMvtUrl() {
  const base = martinUrl.value;
  const params = new URLSearchParams();
  params.set('_v', String(jalanPorosTileVersion.value));
  const qs = params.toString();
  return `${base}/jalan_porosdesa/{z}/{x}/{y}?${qs}`;
}

function refreshJalanPorosVectorTiles(forceVersionBump = true) {
  if (forceVersionBump) {
    jalanPorosTileVersion.value = Date.now();
  }
  if (jalanPorosVectorTileSource) {
    const newUrl = getJalanPorosMvtUrl();
    jalanPorosVectorTileSource.setUrl(newUrl);
    if (forceVersionBump) {
      jalanPorosVectorTileSource.clear();
    }
    jalanPorosVectorTileSource.refresh();
  }
  if (jalanPorosVectorTileLayer) {
    jalanPorosVectorTileLayer.changed();
  }
}

function getJalanPorosStyle(feature: any) {
  if (!olModules) return null;
  const { Style, Stroke, Text, Fill } = olModules;

  // Client-side filtering for Jalan Poros Desa
  if (props.jalanPorosDesaKecamatanFilter) {
    const fKecId = String(feature.get('id_kecamatan') || '').trim();
    const fKec = String(feature.get('kecamatan') || '').toLowerCase().trim();
    const filterKec = String(props.jalanPorosDesaKecamatanFilter).trim().toLowerCase();
    if (fKecId !== filterKec && fKec !== filterKec && !fKec.includes(filterKec)) return null;
  }
  if (props.jalanPorosDesaDesaFilter) {
    const fDesaId = String(feature.get('id_desa') || '').trim();
    const fDesa = String(feature.get('desa') || '').toLowerCase().trim();
    const filterDesa = String(props.jalanPorosDesaDesaFilter).trim().toLowerCase();
    if (fDesaId !== filterDesa && fDesa !== filterDesa && !fDesa.includes(filterDesa)) return null;
  }
  if (props.jalanPorosDesaKondisiFilter) {
    const fKondisi = String(feature.get('kondisi') || '').toUpperCase().trim();
    const filterKondisi = String(props.jalanPorosDesaKondisiFilter).toUpperCase().trim();
    if (!fKondisi.includes(filterKondisi)) return null;
  }
  if (props.jalanPorosDesaPerkerasanFilter) {
    const fPerk = String(feature.get('perkerasan') || '').toLowerCase().trim();
    const filterPerk = String(props.jalanPorosDesaPerkerasanFilter).toLowerCase().trim();
    if (!fPerk.includes(filterPerk)) return null;
  }

  const sym = props.jalanPorosDesaSymbology || DEFAULT_JALAN_POROS_SYMBOLOGY;

  let strokeColor = sym.lineColor || '#2563eb';
  const kondisi = feature.get('kondisi');
  const perkerasan = feature.get('perkerasan');

  if (sym.colorMode === 'kondisi') {
    const k = String(kondisi || '').toLowerCase();
    if (k.includes('rusak berat')) strokeColor = '#ef4444';
    else if (k.includes('rusak ringan')) strokeColor = '#f97316';
    else if (k.includes('sedang')) strokeColor = '#f59e0b';
    else if (k.includes('rusak')) strokeColor = '#f97316';
    else if (k.includes('baik')) strokeColor = '#10b981';
    else strokeColor = sym.lineColor || '#2563eb';
  } else if (sym.colorMode === 'perkerasan') {
    const p = String(perkerasan || '').toLowerCase();
    if (p.includes('aspal')) strokeColor = '#3b82f6';
    else if (p.includes('beton')) strokeColor = '#10b981';
    else if (p.includes('kerikil')) strokeColor = '#f59e0b';
    else if (p.includes('tanah')) strokeColor = '#8b5cf6';
  }

  let lineDash: number[] | undefined = undefined;
  if (sym.lineDash === 'dashed') lineDash = [8, 6];
  if (sym.lineDash === 'dotted') lineDash = [2, 5];

  const strokeWidth = sym.lineWidth || 2;

  let textStyle: any = undefined;
  if (sym.labelEnabled && mapInstance) {
    const currentZoom = mapInstance.getView()?.getZoom() || 0;
    if (currentZoom >= (sym.labelMinZoom ?? 13)) {
      const field = sym.labelField || 'nama_ruas';
      const labelText = String(feature.get(field) || feature.get('nama_ruas') || '');
      if (labelText) {
        textStyle = new Text({
          text: labelText,
          font: `600 ${sym.labelFontSize || 11}px Inter, sans-serif`,
          fill: new Fill({ color: sym.labelColor || '#1e293b' }),
          stroke: new Stroke({
            color: sym.labelHaloColor || '#ffffff',
            width: sym.labelHaloWidth || 3,
          }),
          placement: 'line',
          overflow: false,
        });
      }
    }
  }

  const featId = String(feature.get('id') || feature.getId() || '');
  const hoverId = hoveredFeature.value ? String(hoveredFeature.value.get('id') || hoveredFeature.value.getId() || '') : '';
  const isHovered = Boolean(props.activeLayerId === 'jalan-poros-desa' && hoverId && hoverId === featId);
  const isPinned = Boolean(props.activeLayerId === 'jalan-poros-desa' && isPopupPinned.value && tooltipData.value?.id === featId);

  if (isPinned) {
    return new Style({
      stroke: new Stroke({
        color: '#0284c7',
        width: Math.max(strokeWidth + 2.5, 4.5),
        lineCap: 'round',
        lineJoin: 'round',
      }),
      text: textStyle,
      zIndex: 100,
    });
  }

  if (isHovered) {
    return new Style({
      stroke: new Stroke({
        color: '#38bdf8',
        width: Math.max(strokeWidth + 1.5, 3.5),
        lineCap: 'round',
        lineJoin: 'round',
      }),
      text: textStyle,
      zIndex: 90,
    });
  }

  return new Style({
    stroke: new Stroke({
      color: strokeColor,
      width: strokeWidth,
      lineDash,
      lineCap: 'round',
      lineJoin: 'round',
    }),
    text: textStyle,
    zIndex: 8,
  });
}

// ─── Basemap Source Factory ───────────────────────────────────────────────────
function getBasemapSource(type: BasemapType) {
  if (!olModules || type === 'none') return null;
  const { OSM, XYZ } = olModules;
  if (type === 'satellite') {
    return new XYZ({
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      maxZoom: 19,
    });
  }
  if (type === 'dark') {
    return new XYZ({
      url: 'https://basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png',
      attributions: '&copy; CARTO',
      maxZoom: 19,
    });
  }
  if (type === 'topo') {
    return new XYZ({
      url: 'https://tile.opentopomap.org/{z}/{x}/{y}.png',
      maxZoom: 17,
    });
  }
  return new OSM();
}

function setBasemap(type: BasemapType) {
  currentBasemap.value = type;
  emit('update:basemap', type);
  if (baseTileLayer && olModules) {
    if (type === 'none') {
      baseTileLayer.setVisible(false);
      baseTileLayer.setSource(null);
    } else {
      baseTileLayer.setVisible(true);
      baseTileLayer.setSource(getBasemapSource(type));
    }
  }
}

// ─── Feature Styling & Symbology Cache ──────────────────────────────────────────
const KONDISI_COLORS: Record<string, string> = {
  baik: '#10b981',         // Hijau Emerald
  sedang: '#f59e0b',       // Kuning Amber
  'rusak ringan': '#f97316', // Oranye
  'rusak berat': '#ef4444',  // Merah
};

const textStyleCache = new Map<string, any>();
const featureStyleCache = new Map<string, any>();

function clearStyleCache() {
  textStyleCache.clear();
  featureStyleCache.clear();
}

function getStrokeColor(feature: any, propsObj: any): string {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (sym.colorMode === 'kondisi') {
    const rawKondisi = String(propsObj.kondisi || feature.get('kondisi') || '').toLowerCase().trim();
    if (rawKondisi.includes('rusak berat')) return KONDISI_COLORS['rusak berat'];
    if (rawKondisi.includes('rusak')) return KONDISI_COLORS['rusak ringan'];
    if (rawKondisi.includes('sedang')) return KONDISI_COLORS['sedang'];
    if (rawKondisi.includes('baik')) return KONDISI_COLORS['baik'];
    return sym.lineColor || '#10b981';
  }
  return sym.lineColor || '#059669';
}

function getLineDash(): number[] | undefined {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (sym.lineDash === 'dashed') return [8, 6];
  if (sym.lineDash === 'dotted') return [2, 4];
  return undefined;
}

function getLabelText(feature: any, propsObj: any): string {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (!sym.labelEnabled) return '';

  const currentZoom = mapInstance ? (mapInstance.getView()?.getZoom() || 0) : 0;
  if (currentZoom < (sym.labelMinZoom ?? 13)) return '';

  const field = sym.labelField || 'namobj';
  if (field === 'panjang') {
    const p = propsObj.panjang_meter_gis ?? propsObj.panjang ?? feature.get('panjang');
    return p ? `${Number(p).toLocaleString('id-ID')} m` : '';
  }
  const val = propsObj[field] ?? feature.get(field);
  return val ? String(val) : '';
}

function getFeatureStyle(feature: any) {
  if (!olModules) return null;
  const { Style, Stroke, Fill, Text } = olModules;

  const propsObj = feature.getProperties?.() ?? {};

  // Client-side filtering
  if (props.tipeFilter && String(propsObj.tipe_kode || '').trim().toLowerCase() !== props.tipeFilter.trim().toLowerCase()) return null;
  if (props.tahunPembangunanFilter) {
    const fTahun = String(propsObj.tahun_pembangunan || propsObj.tahun || '').trim();
    if (fTahun !== String(props.tahunPembangunanFilter).trim()) return null;
  }
  if (props.perkerasanFilter && !String(propsObj.jenis_perkerasan || propsObj.perkerasan || '').toLowerCase().includes(props.perkerasanFilter.toLowerCase().trim())) return null;
  if (props.kondisiFilter && !String(propsObj.kondisi || '').toLowerCase().includes(props.kondisiFilter.toLowerCase().trim())) return null;
  if (props.statusVerifikasiFilter && String(propsObj.status_verifikasi || '').trim().toLowerCase() !== props.statusVerifikasiFilter.trim().toLowerCase()) return null;
  if (props.statusJalanFilter) {
    const fStatusJalan = String(propsObj.status_jalan || '').trim().toLowerCase();
    const filterVal = String(props.statusJalanFilter).trim().toLowerCase();
    if (fStatusJalan !== filterVal && !fStatusJalan.includes(filterVal)) return null;
  }
  if (props.statusKondisiFilter) {
    const fStatusKondisi = String(propsObj.status_kondisi || '').trim().toLowerCase();
    const filterVal = String(props.statusKondisiFilter).trim().toLowerCase();
    if (fStatusKondisi !== filterVal && !fStatusKondisi.includes(filterVal)) return null;
  }
  if (props.statusAsetFilter) {
    const fStatusAset = String(propsObj.status_aset || '').trim().toLowerCase();
    const filterVal = String(props.statusAsetFilter).trim().toLowerCase();
    if (fStatusAset !== filterVal && !fStatusAset.includes(filterVal)) return null;
  }
  if (props.sumberDanaFilter) {
    const fSumberDana = String(propsObj.sumber_dana || '').trim().toLowerCase();
    const filterVal = String(props.sumberDanaFilter).trim().toLowerCase();
    if (fSumberDana !== filterVal && !fSumberDana.includes(filterVal)) return null;
  }
  if (props.kecamatanFilter) {
    const fKecId = String(propsObj.id_kecamatan || '').trim();
    const fKec = String(propsObj.kecamatan || '').toLowerCase().trim();
    const filterKec = String(props.kecamatanFilter).trim().toLowerCase();
    if (fKecId !== filterKec && fKec !== filterKec && !fKec.includes(filterKec)) return null;
  }
  if (props.desaFilter) {
    const fDesaId = String(propsObj.id_desa || '').trim();
    const fDesa = String(propsObj.desa || '').toLowerCase().trim();
    const filterDesa = String(props.desaFilter).trim().toLowerCase();
    if (fDesaId !== filterDesa && fDesa !== filterDesa && !fDesa.includes(filterDesa)) return null;
  }

  const featId = String(propsObj.id || feature.getId() || '');
  const isSelected = Boolean(
    (props.selectedSegmenId && String(props.selectedSegmenId) === featId) ||
    (props.selectedSegmen?.id && String(props.selectedSegmen.id) === featId)
  );
  const isPinned = Boolean(props.activeLayerId !== 'jalan-poros-desa' && isPopupPinned.value && tooltipData.value?.id === featId);
  const hoverId = hoveredFeature.value ? String(hoveredFeature.value.get('id') || hoveredFeature.value.getId() || '') : '';
  const isHovered = Boolean(props.activeLayerId !== 'jalan-poros-desa' && hoverId && hoverId === featId);

  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  const isDark = colorMode.value === 'dark';
  const baseColor = getStrokeColor(feature, propsObj);
  const strokeWidth = sym.lineWidth || 2.5;
  const lineDash = getLineDash();

  // Label text if enabled & zoom threshold met
  const labelText = getLabelText(feature, propsObj);
  let textStyle: any = undefined;

  if (labelText) {
    const fontSize = sym.labelFontSize || 11;
    const textColor = sym.labelColor || (isDark ? '#f8fafc' : '#0f172a');
    const haloColor = sym.labelHaloColor || (isDark ? '#090d16' : '#ffffff');
    const haloWidth = sym.labelHaloWidth ?? 3;
    const textKey = `${labelText}_${fontSize}_${textColor}_${haloColor}_${haloWidth}`;

    textStyle = textStyleCache.get(textKey);
    if (!textStyle) {
      textStyle = new Text({
        text: labelText,
        font: `bold ${fontSize}px system-ui, -apple-system, sans-serif`,
        placement: 'line',
        maxAngle: Math.PI / 4,
        overflow: false,
        fill: new Fill({ color: textColor }),
        stroke: new Stroke({ color: haloColor, width: haloWidth }),
        offsetY: -8,
      });
      textStyleCache.set(textKey, textStyle);
    }
  }

  if (isSelected || isPinned) {
    return new Style({
      stroke: new Stroke({
        color: '#0284c7',
        width: Math.max(strokeWidth + 2.5, 4.5),
        lineDash: undefined,
        lineCap: 'round',
        lineJoin: 'round',
      }),
      text: textStyle,
      zIndex: 100,
    });
  }

  if (isHovered) {
    return new Style({
      stroke: new Stroke({
        color: isDark ? '#38bdf8' : '#0284c7',
        width: Math.max(strokeWidth + 1.5, 3.5),
        lineDash: undefined,
        lineCap: 'round',
        lineJoin: 'round',
      }),
      text: textStyle,
      zIndex: 50,
    });
  }

  const styleKey = `${baseColor}_${strokeWidth}_${sym.lineDash || 'solid'}_${labelText || ''}`;
  let cached = featureStyleCache.get(styleKey);
  if (!cached) {
    cached = new Style({
      stroke: new Stroke({
        color: baseColor,
        width: strokeWidth,
        lineDash,
        lineCap: 'round',
        lineJoin: 'round',
      }),
      text: textStyle,
      zIndex: 10,
    });
    featureStyleCache.set(styleKey, cached);
  }

  return cached;
}

// ─── Camera Controls ──────────────────────────────────────────────────────────
function fitBounds(customBbox?: [number, number, number, number] | null) {
  if (!mapInstance || !olModules) return;
  mapInstance.updateSize();
  const size = mapInstance.getSize();
  if (!size || size[0] <= 0 || size[1] <= 0) return;

  const targetBbox = customBbox || props.bbox;
  const { transformExtent } = olModules;
  if (targetBbox && targetBbox.length === 4 && targetBbox.every((n: number) => typeof n === 'number' && !isNaN(n) && isFinite(n))) {
    const [minX, minY, maxX, maxY] = targetBbox;
    if (minX === maxX && minY === maxY) {
      mapInstance.getView().animate({
        center: olModules.fromLonLat([minX, minY]),
        zoom: 16,
        duration: 700,
      });
      return;
    }
    const extent = transformExtent([minX, minY, maxX, maxY], 'EPSG:4326', 'EPSG:3857');
    mapInstance.getView().fit(extent, {
      duration: 700,
      padding: [60, 60, 60, 60],
      maxZoom: 16,
    });
  } else {
    mapInstance.getView().animate({
      center: olModules.fromLonLat([111.88, -7.2]),
      zoom: 11,
      duration: 600,
    });
  }
}

function zoomToGeometry(geometry: any) {
  if (!mapInstance || !olModules || !geometry) return false;
  try {
    const geojsonFormat = new olModules.GeoJSON();
    const geom = geojsonFormat.readGeometry(geometry, {
      dataProjection: 'EPSG:4326',
      featureProjection: 'EPSG:3857',
    });
    const extent = geom.getExtent();
    if (extent && extent.every((n: number) => !isNaN(n) && isFinite(n))) {
      mapInstance.getView().fit(extent, {
        padding: [80, 80, 80, 80],
        maxZoom: 17,
        duration: 600,
      });
      return true;
    }
  } catch {
    // fallback
  }
  return false;
}

function zoomToFeature(feature: any) {
  if (!mapInstance || !olModules || !feature) return;
  try {
    // 1. Parse dari format geometry GeoJSON
    const geom = feature.geometry || (feature.geojson ? (typeof feature.geojson === 'string' ? JSON.parse(feature.geojson) : feature.geojson) : null);
    if (geom) {
      const geojsonFormat = new olModules.GeoJSON();
      const olGeom = geojsonFormat.readGeometry(geom, {
        dataProjection: 'EPSG:4326',
        featureProjection: 'EPSG:3857',
      });

      const extent = olGeom.getExtent();
      if (extent && !extent.some((n: number) => isNaN(n) || !isFinite(n))) {
        mapInstance.getView().fit(extent, {
          padding: [80, 80, 80, 80],
          maxZoom: 17,
          duration: 600,
        });
        return;
      }
    }

    // 2. Fallback ke titik centroid jika geometri lengkap tidak ada
    const c = feature.centroid || feature.properties?.centroid;
    if (c) {
      const parsedCentroid = typeof c === 'string' ? JSON.parse(c) : c;
      const coords = parsedCentroid.coordinates || parsedCentroid;
      if (Array.isArray(coords) && coords.length >= 2) {
        mapInstance.getView().animate({
          center: olModules.fromLonLat([Number(coords[0]), Number(coords[1])]),
          zoom: 16,
          duration: 600,
        });
      }
    }
  } catch (err) {
    console.error('Gagal zoom ke fitur segmen:', err);
  }
}

function zoomIn() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || 11) + 1, duration: 200 });
}

function zoomOut() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || 11) - 1, duration: 200 });
}

function fitGeometryToCenter(geometryOrExtent: any, duration = 600, callback?: () => void) {
  if (!mapInstance || !olModules || !geometryOrExtent) return;
  mapInstance.updateSize();
  const size = mapInstance.getSize();
  if (!size || size[0] <= 0 || size[1] <= 0) return;

  const extent = Array.isArray(geometryOrExtent) && geometryOrExtent.length === 4
    ? geometryOrExtent
    : geometryOrExtent?.getExtent?.();

  if (!extent || !extent.every((n: number) => !isNaN(n) && isFinite(n))) return;

  mapInstance.getView().fit(extent, {
    size,
    padding: [90, 90, 90, 90],
    maxZoom: 17,
    duration,
    callback,
  });
}

function handleDrawClick() {
  if (!isDrawing.value) {
    startDrawing();
  } else if (hasCompletedLine.value) {
    emit('log', 'INFO', 'Garis sudah selesai digambar. Gunakan Redraw untuk mengulang dari awal, atau Simpan untuk membuka form.');
  } else {
    emit('log', 'INFO', 'Mode gambar sedang aktif. Silakan lanjutkan klik pada peta.');
  }
}

function handleRedraw() {
  startDrawing();
  emit('log', 'INFO', 'Mengulang gambar garis segmen dari awal.');
}

// ─── Drawing / Digitizing Operations ──────────────────────────────────────────
function startDrawing() {
  if (!mapInstance || !olModules) return;
  cancelDrawing(false);

  isDrawing.value = true;
  hasCompletedLine.value = false;
  drawnPointCount.value = 0;
  drawnLengthMeters.value = 0;
  drawnFeature.value = null;
  redoStack.value = [];

  const { Draw, sphere, Style, Stroke, Circle, Fill } = olModules;

  drawInteraction = new Draw({
    source: drawSource,
    type: 'LineString',
    style: [
      new Style({
        stroke: new Stroke({
          color: 'rgba(245, 158, 11, 0.4)',
          width: 8,
        }),
      }),
      new Style({
        stroke: new Stroke({
          color: '#f59e0b',
          width: 3.5,
          lineDash: [6, 6],
        }),
        image: new Circle({
          radius: 6,
          fill: new Fill({ color: '#f59e0b' }),
          stroke: new Stroke({ color: '#ffffff', width: 2 }),
        }),
      }),
    ],
  });

  drawInteraction.on('drawstart', (evt: any) => {
    drawnFeature.value = evt.feature;
    hasCompletedLine.value = false;
    redoStack.value = [];

    const geom = evt.feature.getGeometry();
    activeGeomListener = () => {
      const coords = geom.getCoordinates();
      if (coords && coords.length > 0) {
        drawnPointCount.value = Math.max(1, coords.length - 1);
        if (coords.length >= 2) {
          drawnLengthMeters.value = Math.round(sphere.getLength(geom));
        }
      }
    };
    geom.on('change', activeGeomListener);
  });

  drawInteraction.on('drawend', (evt: any) => {
    hasCompletedLine.value = true;
    drawnFeature.value = evt.feature;
    const geom = evt.feature.getGeometry();
    if (geom) {
      drawnLengthMeters.value = Math.round(sphere.getLength(geom));
      drawnPointCount.value = geom.getCoordinates()?.length || 0;
      if (activeGeomListener) {
        geom.un('change', activeGeomListener);
        activeGeomListener = null;
      }
      fitGeometryToCenter(geom, 600);
    }

    if (drawInteraction && mapInstance) {
      mapInstance.removeInteraction(drawInteraction);
      drawInteraction = null;
    }

    // Attach Modify interaction for vertex refinement before saving
    const { Modify, Style, Circle, Fill, Stroke } = olModules;
    modifyInteraction = new Modify({
      source: drawSource,
      style: new Style({
        image: new Circle({
          radius: 6,
          fill: new Fill({ color: '#f59e0b' }),
          stroke: new Stroke({ color: '#ffffff', width: 2.5 }),
        }),
      }),
    });

    modifyInteraction.on('modifystart', () => {
      redoStack.value = [];
    });

    activeGeomListener = () => {
      const coords = geom?.getCoordinates();
      if (Array.isArray(coords)) {
        drawnPointCount.value = coords.length;
        if (coords.length >= 2) {
          drawnLengthMeters.value = Math.round(sphere.getLength(geom));
        }
      }
    };
    geom?.on('change', activeGeomListener);
    mapInstance.addInteraction(modifyInteraction);
    updateSnapInteractions();
  });

  mapInstance.addInteraction(drawInteraction);

  snapSource = new olModules.VectorSource({ wrapX: false });
  updateSnapInteractions();
  mapInstance.on('moveend', onMapMoveEndDuringDraw);
  loadSnapFeatures();
  emit('log', 'INFO', 'Mode gambar aktif. Klik pada peta untuk menggambar garis segmen.');
}

function handleUndo() {
  if (!olModules) return;
  const { sphere } = olModules;

  if (drawInteraction) {
    const sketchCoords = (drawInteraction as any).sketchCoords_;
    if (sketchCoords && sketchCoords.length > 1) {
      const fixedIndex = sketchCoords.length - 2;
      const popped = sketchCoords[fixedIndex]?.slice();
      if (popped) {
        redoStack.value.push(popped);
      }
      (drawInteraction as any).removeLastPoint();
      const updatedCoords = (drawInteraction as any).sketchCoords_ || [];
      drawnPointCount.value = Math.max(0, updatedCoords.length - 1);
      if (drawnFeature.value) {
        const geom = drawnFeature.value.getGeometry();
        if (geom && drawnPointCount.value >= 2) {
          drawnLengthMeters.value = Math.round(sphere.getLength(geom));
        } else {
          drawnLengthMeters.value = 0;
        }
      }
    }
    return;
  }

  if (drawnFeature.value) {
    const geom = drawnFeature.value.getGeometry();
    if (!geom) return;
    const coords = geom.getCoordinates()?.slice() || [];
    if (coords.length > 0) {
      const popped = coords.pop();
      redoStack.value.push(popped);
      if (coords.length >= 2) {
        geom.setCoordinates(coords);
        drawnPointCount.value = coords.length;
        drawnLengthMeters.value = Math.round(sphere.getLength(geom));
        hasCompletedLine.value = true;
      } else {
        geom.setCoordinates(coords);
        drawnPointCount.value = coords.length;
        drawnLengthMeters.value = 0;
        hasCompletedLine.value = false;
      }
    }
  }
}

function handleRedo() {
  if (redoStack.value.length === 0 || !olModules) return;
  const { sphere } = olModules;
  const nextPoint = redoStack.value.pop();
  if (!nextPoint) return;

  if (drawInteraction) {
    (drawInteraction as any).appendCoordinates([nextPoint]);
    const updatedCoords = (drawInteraction as any).sketchCoords_ || [];
    drawnPointCount.value = Math.max(0, updatedCoords.length - 1);
    if (drawnFeature.value) {
      const geom = drawnFeature.value.getGeometry();
      if (geom && drawnPointCount.value >= 2) {
        drawnLengthMeters.value = Math.round(sphere.getLength(geom));
      }
    }
    return;
  }

  if (drawnFeature.value) {
    const geom = drawnFeature.value.getGeometry();
    if (geom) {
      const coords = geom.getCoordinates()?.slice() || [];
      coords.push(nextPoint);
      geom.setCoordinates(coords);
      drawnPointCount.value = coords.length;
      drawnLengthMeters.value = Math.round(sphere.getLength(geom));
      if (coords.length >= 2) {
        hasCompletedLine.value = true;
      }
    }
  }
}

function finishDrawing() {
  if (drawInteraction) {
    (drawInteraction as any).finishDrawing();
  }
}

function cancelDrawing(emitCancel = true) {
  const wasActive = isDrawing.value || hasCompletedLine.value || !!drawInteraction || !!modifyInteraction;
  if (!wasActive) return;

  clearTimeout(snapMoveEndTimer);
  if (mapInstance) {
    mapInstance.un('moveend', onMapMoveEndDuringDraw);
  }
  if (snapFetchAbort) {
    snapFetchAbort.abort();
    snapFetchAbort = null;
  }
  if (drawSnapInteraction && mapInstance) {
    mapInstance.removeInteraction(drawSnapInteraction);
    drawSnapInteraction = null;
  }
  if (networkSnapInteraction && mapInstance) {
    mapInstance.removeInteraction(networkSnapInteraction);
    networkSnapInteraction = null;
  }
  if (snapSource) {
    snapSource.clear();
    snapSource = null;
  }
  lastSnapBbox = '';
  snapRoadCount.value = 0;

  if (drawInteraction && mapInstance) {
    mapInstance.removeInteraction(drawInteraction);
    drawInteraction = null;
  }
  if (modifyInteraction && mapInstance) {
    mapInstance.removeInteraction(modifyInteraction);
    modifyInteraction = null;
  }
  if (activeGeomListener && drawnFeature.value) {
    drawnFeature.value.getGeometry()?.un('change', activeGeomListener);
    activeGeomListener = null;
  }
  if (drawSource) {
    drawSource.clear();
  }
  isDrawing.value = false;
  hasCompletedLine.value = false;
  drawnPointCount.value = 0;
  drawnLengthMeters.value = 0;
  drawnFeature.value = null;
  redoStack.value = [];

  if (emitCancel) {
    emit('drawCanceled');
  }
}

function updateSnapInteractions() {
  if (!mapInstance || !olModules) return;
  const { Snap } = olModules;

  if (drawSnapInteraction) {
    mapInstance.removeInteraction(drawSnapInteraction);
    drawSnapInteraction = null;
  }
  if (networkSnapInteraction) {
    mapInstance.removeInteraction(networkSnapInteraction);
    networkSnapInteraction = null;
  }

  if (!isSnappingEnabled.value || !isDrawing.value) return;

  if (drawSource) {
    drawSnapInteraction = new Snap({
      source: drawSource,
      edge: true,
      vertex: true,
      pixelTolerance: 12,
    });
    mapInstance.addInteraction(drawSnapInteraction);
  }
  if (snapSource) {
    networkSnapInteraction = new Snap({
      source: snapSource,
      edge: true,
      vertex: true,
      pixelTolerance: 14,
    });
    mapInstance.addInteraction(networkSnapInteraction);
  }
}

async function loadSnapFeatures() {
  if (!mapInstance || !olModules || !isDrawing.value) return;

  try {
    const size = mapInstance.getSize();
    if (!size || size[0] === 0 || size[1] === 0) return;

    const extent = mapInstance.getView().calculateExtent(size);
    if (!extent || extent.some((n: number) => isNaN(n) || !isFinite(n))) return;

    const [minX, minY, maxX, maxY] = olModules.transformExtent(extent, 'EPSG:3857', 'EPSG:4326');
    const dx = (maxX - minX) * 0.2;
    const dy = (maxY - minY) * 0.2;
    const bboxParam = `${(minX - dx).toFixed(5)},${(minY - dy).toFixed(5)},${(maxX + dx).toFixed(5)},${(maxY + dy).toFixed(5)}`;

    if (bboxParam === lastSnapBbox) return;
    lastSnapBbox = bboxParam;

    if (snapFetchAbort) {
      snapFetchAbort.abort();
    }
    snapFetchAbort = new AbortController();

    isSnapLoading.value = true;
    const res = await $http<any>('admin/infrastruktur-segmen', {
      query: {
        format: 'geojson',
        bbox: bboxParam,
      },
      signal: snapFetchAbort.signal,
    });

    if (res?.features && Array.isArray(res.features)) {
      if (!snapSource) return;
      snapSource.clear();

      let featuresToSnap = res.features;
      if (props.selectedSegmenId) {
        featuresToSnap = featuresToSnap.filter(
          (f: any) =>
            String(f.id) !== String(props.selectedSegmenId) &&
            String(f.properties?.id) !== String(props.selectedSegmenId)
        );
      }

      const format = new olModules.GeoJSON();
      const olFeatures = format.readFeatures(
        {
          type: 'FeatureCollection',
          features: featuresToSnap,
        },
        {
          dataProjection: 'EPSG:4326',
          featureProjection: 'EPSG:3857',
        }
      );

      snapSource.addFeatures(olFeatures);
      snapRoadCount.value = olFeatures.length;
      updateSnapInteractions();
      emit('log', 'INFO', `Snapping aktif ke ${olFeatures.length} segmen sekitar.`);
    }
  } catch (err: any) {
    if (
      err?.name === 'AbortError' ||
      err?.cause?.name === 'AbortError' ||
      (typeof err?.message === 'string' && err.message.toLowerCase().includes('abort'))
    ) {
      return;
    }
    console.error('Gagal memuat snap features:', err);
  } finally {
    isSnapLoading.value = false;
  }
}

function onMapMoveEndDuringDraw() {
  if (!isDrawing.value || !isSnappingEnabled.value) return;
  clearTimeout(snapMoveEndTimer);
  snapMoveEndTimer = setTimeout(() => {
    loadSnapFeatures();
  }, 400);
}

function toggleSnapping() {
  isSnappingEnabled.value = !isSnappingEnabled.value;
  updateSnapInteractions();
  if (isSnappingEnabled.value && (!snapSource || snapSource.getFeatures().length === 0)) {
    loadSnapFeatures();
  }
  emit('log', 'INFO', isSnappingEnabled.value ? 'Snapping diaktifkan.' : 'Snapping dinonaktifkan.');
}

function saveDrawnSegment() {
  if (!drawnFeature.value || !olModules) return;
  const geom = drawnFeature.value.getGeometry();
  if (!geom) return;

  const coords = geom.getCoordinates();
  if (!Array.isArray(coords) || coords.length < 2) return;

  const geojsonFormat = new olModules.GeoJSON();
  const geojsonObj = geojsonFormat.writeGeometryObject(geom, {
    featureProjection: 'EPSG:3857',
    dataProjection: 'EPSG:4326',
  });

  const length = Math.round(olModules.sphere.getLength(geom));

  fitGeometryToCenter(geom, 400);

  emit('drawSaved', {
    geojson: geojsonObj,
    lengthMeters: length,
  });

  emit('log', 'INFO', `Garis segmen berhasil dikonfirmasi (${length} m). Membuka form.`);

  cancelDrawing(false);
}

function confirmDraw() {
  saveDrawnSegment();
}

// ─── Edit Geometry Operations ────────────────────────────────────────────────
function startEditGeometry(segmen: InfrastrukturSegmen) {
  if (!mapInstance || !olModules) return;
  cancelDrawing(false);
  cancelSplitMode();
  cancelEditGeometry();

  const rawGeom = (segmen as any).geometry || segmen.geojson || (segmen as any).properties?.geojson || (segmen as any).properties?.geometry;
  if (!rawGeom) {
    emit('log', 'WARN', 'Geometri segmen tidak ditemukan untuk diedit.');
    return;
  }

  try {
    const parsedGeom = typeof rawGeom === 'string' ? JSON.parse(rawGeom) : rawGeom;
    const format = new olModules.GeoJSON();
    const olGeom = format.readGeometry(parsedGeom, {
      dataProjection: 'EPSG:4326',
      featureProjection: 'EPSG:3857',
    });

    const feat = new olModules.Feature({ geometry: olGeom });
    feat.setId(segmen.id);
    editedFeature.value = feat;
    editingSegmenData.value = segmen;
    isEditGeometryMode.value = true;
    editedLengthMeters.value = Math.round(olModules.sphere.getLength(olGeom));

    editGeometrySource.addFeature(feat);

    const extent = olGeom.getExtent();
    if (extent && !extent.some((n: number) => isNaN(n) || !isFinite(n))) {
      mapInstance.getView().fit(extent, {
        padding: [120, 120, 120, 120],
        maxZoom: 18,
        duration: 500,
      });
    }

    const { Modify, Style, Circle, Fill, Stroke, Snap } = olModules;
    editModifyInteraction = new Modify({
      source: editGeometrySource,
      style: new Style({
        image: new Circle({
          radius: 7,
          fill: new Fill({ color: '#2563eb' }),
          stroke: new Stroke({ color: '#ffffff', width: 2.5 }),
        }),
      }),
    });

    editGeomListener = () => {
      const g = feat.getGeometry();
      if (g) {
        editedLengthMeters.value = Math.round(olModules.sphere.getLength(g));
      }
    };
    olGeom.on('change', editGeomListener);

    mapInstance.addInteraction(editModifyInteraction);

    if (snapSource) {
      editSnapInteraction = new Snap({ source: snapSource, pixelTolerance: 14 });
      mapInstance.addInteraction(editSnapInteraction);
    }

    emit('log', 'INFO', `Mode edit geometri aktif untuk "${segmen.namobj || 'segmen'}". Geser titik koordinat.`);
  } catch (err: any) {
    emit('log', 'ERROR', `Gagal mengaktifkan mode edit: ${err?.message}`);
  }
}

function cancelEditGeometry() {
  if (editModifyInteraction && mapInstance) {
    mapInstance.removeInteraction(editModifyInteraction);
    editModifyInteraction = null;
  }
  if (editSnapInteraction && mapInstance) {
    mapInstance.removeInteraction(editSnapInteraction);
    editSnapInteraction = null;
  }
  if (editGeomListener && editedFeature.value) {
    editedFeature.value.getGeometry()?.un('change', editGeomListener);
    editGeomListener = null;
  }
  if (editGeometrySource) {
    editGeometrySource.clear();
  }
  isEditGeometryMode.value = false;
  editingSegmenData.value = null;
  editedFeature.value = null;
  editedLengthMeters.value = 0;
  emit('editGeometryCanceled');
}

function saveEditedGeometry() {
  if (!editedFeature.value || !editingSegmenData.value || !olModules) return;

  const geom = editedFeature.value.getGeometry();
  if (!geom) return;

  const format = new olModules.GeoJSON();
  const geojsonObj = format.writeGeometryObject(geom, {
    featureProjection: 'EPSG:3857',
    dataProjection: 'EPSG:4326',
  });
  const length = Math.round(olModules.sphere.getLength(geom));

  emit('editGeometrySaved', {
    id: editingSegmenData.value.id,
    geojson: geojsonObj,
    lengthMeters: length,
  });

  cancelEditGeometry();
}

// ─── Split Geometry Operations ───────────────────────────────────────────────
function startSplitMode(segmen: InfrastrukturSegmen) {
  if (!mapInstance || !olModules) return;
  cancelDrawing(false);
  cancelEditGeometry();
  cancelSplitMode();

  const rawGeom = (segmen as any).geometry || segmen.geojson || (segmen as any).properties?.geojson || (segmen as any).properties?.geometry;
  if (!rawGeom) {
    emit('log', 'WARN', 'Geometri segmen tidak valid untuk split.');
    return;
  }

  try {
    const parsedGeom = typeof rawGeom === 'string' ? JSON.parse(rawGeom) : rawGeom;
    const format = new olModules.GeoJSON();
    const olGeom = format.readGeometry(parsedGeom, {
      dataProjection: 'EPSG:4326',
      featureProjection: 'EPSG:3857',
    });

    isSplitMode.value = true;
    splitSegmenData.value = segmen;

    const roadFeat = new olModules.Feature({ geometry: olGeom });
    splitSource.addFeature(roadFeat);

    const extent = olGeom.getExtent();
    if (extent && !extent.some((n: number) => isNaN(n) || !isFinite(n))) {
      mapInstance.getView().fit(extent, {
        padding: [100, 100, 100, 100],
        maxZoom: 18,
        duration: 500,
      });
    }

    const { Snap } = olModules;
    splitSnapInteraction = new Snap({
      source: splitSource,
      edge: true,
      vertex: true,
      pixelTolerance: 15,
    });
    mapInstance.addInteraction(splitSnapInteraction);

    splitClickListener = (evt: any) => {
      if (!isSplitMode.value) return;
      const rawCoord = evt.coordinate;
      const snappedCoord = olGeom?.getClosestPoint ? olGeom.getClosestPoint(rawCoord) : rawCoord;
      const [lon, lat] = olModules.toLonLat(snappedCoord);
      splitSelectedCoord.value = [lon, lat];

      const markers = splitSource.getFeatures().filter((f: any) => f.get('isMarker'));
      markers.forEach((m: any) => splitSource.removeFeature(m));

      const marker = new olModules.Feature({
        geometry: new olModules.Point(snappedCoord),
      });
      marker.set('isMarker', true);
      splitSource.addFeature(marker);

      emit('splitPointSelected', {
        segmen,
        coordinate: [lon, lat],
      });
    };

    mapInstance.on('singleclick', splitClickListener);
    emit('log', 'INFO', `Klik pada garis "${segmen.namobj || 'segmen'}" di titik pemotongan.`);
  } catch (err: any) {
    emit('log', 'ERROR', `Gagal memuat segmen untuk split: ${err?.message}`);
  }
}

function cancelSplitMode() {
  if (splitClickListener && mapInstance) {
    mapInstance.un('singleclick', splitClickListener);
    splitClickListener = null;
  }
  if (splitSnapInteraction && mapInstance) {
    mapInstance.removeInteraction(splitSnapInteraction);
    splitSnapInteraction = null;
  }
  if (splitSource) {
    splitSource.clear();
  }
  isSplitMode.value = false;
  splitSegmenData.value = null;
  splitSelectedCoord.value = null;
}

// ─── Map Lifecycle ────────────────────────────────────────────────────────────
async function initMap() {
  if (!import.meta.client || !mapContainer.value) return;

  try {
    const [
      { default: Map },
      { default: View },
      { default: TileLayer },
      { default: VectorTileLayer },
      { default: VectorTileSource },
      { default: MVT },
      { default: VectorLayer },
      { default: VectorSource },
      { default: OSM },
      { default: XYZ },
      { default: GeoJSON },
      stylePkg,
      projPkg,
      { default: Overlay },
      { default: ScaleLine },
      { default: Draw },
      { default: Modify },
      { default: Snap },
      { default: Feature },
      { default: Point },
      spherePkg,
    ] = await Promise.all([
      import('ol/Map.js'),
      import('ol/View.js'),
      import('ol/layer/Tile.js'),
      import('ol/layer/VectorTile.js'),
      import('ol/source/VectorTile.js'),
      import('ol/format/MVT.js'),
      import('ol/layer/Vector.js'),
      import('ol/source/Vector.js'),
      import('ol/source/OSM.js'),
      import('ol/source/XYZ.js'),
      import('ol/format/GeoJSON.js'),
      import('ol/style.js'),
      import('ol/proj.js'),
      import('ol/Overlay.js'),
      import('ol/control/ScaleLine.js'),
      import('ol/interaction/Draw.js'),
      import('ol/interaction/Modify.js'),
      import('ol/interaction/Snap.js'),
      import('ol/Feature.js'),
      import('ol/geom/Point.js'),
      import('ol/sphere.js'),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      VectorTileLayer,
      VectorTileSource,
      MVT,
      VectorLayer,
      VectorSource,
      OSM,
      XYZ,
      GeoJSON,
      Style: stylePkg.Style,
      Fill: stylePkg.Fill,
      Stroke: stylePkg.Stroke,
      Text: stylePkg.Text,
      Circle: stylePkg.Circle,
      fromLonLat: projPkg.fromLonLat,
      toLonLat: projPkg.toLonLat,
      transformExtent: projPkg.transformExtent,
      Overlay,
      ScaleLine,
      Draw,
      Modify,
      Snap,
      Feature,
      Point,
      sphere: spherePkg,
    };

    // 1. Basemap Layer
    baseTileLayer = new TileLayer({
      source: currentBasemap.value === 'none' ? null : getBasemapSource(currentBasemap.value),
      visible: currentBasemap.value !== 'none',
      zIndex: 0,
    });

    // 2. Segmen PostGIS MVT Vector Layer
    const mvtUrl = getMvtUrl();
    vectorTileSource = new VectorTileSource({
      format: new MVT({ idProperty: 'id' }),
      url: mvtUrl,
      maxZoom: 20,
      minZoom: 8,
      cacheSize: 512,
    });

    vectorTileSource.on('tileloadstart', onTileLoadStart);
    vectorTileSource.on('tileloadend', onTileLoadFinish);
    vectorTileSource.on('tileloaderror', onTileLoadFinish);

    vectorTileLayer = new VectorTileLayer({
      source: vectorTileSource,
      style: getFeatureStyle,
      renderMode: 'hybrid',
      declutter: true,
      zIndex: 10,
      visible: props.layerVisible,
      opacity: props.layerOpacity,
    });

    // 2.5 Jalan Poros Desa PostGIS MVT Vector Layer
    const jalanMvtUrl = getJalanPorosMvtUrl();
    jalanPorosVectorTileSource = new VectorTileSource({
      format: new MVT({ idProperty: 'id' }),
      url: jalanMvtUrl,
      maxZoom: 20,
      minZoom: 8,
      cacheSize: 512,
    });

    jalanPorosVectorTileLayer = new VectorTileLayer({
      source: jalanPorosVectorTileSource,
      style: getJalanPorosStyle,
      renderMode: 'hybrid',
      declutter: true,
      zIndex: 8,
      visible: props.jalanPorosDesaVisible,
      opacity: props.jalanPorosDesaOpacity,
    });

    // 3. Draw Vector Layer (Digitasi)
    drawSource = new VectorSource({ wrapX: false });
    drawLayer = new VectorLayer({
      source: drawSource,
      zIndex: 50,
      style: [
        new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: 'rgba(245, 158, 11, 0.4)',
            width: 8,
          }),
        }),
        new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: '#f59e0b',
            width: 3.5,
            lineDash: [6, 6],
          }),
          image: new stylePkg.Circle({
            radius: 6,
            fill: new stylePkg.Fill({ color: '#f59e0b' }),
            stroke: new stylePkg.Stroke({ color: '#ffffff', width: 2 }),
          }),
        }),
      ],
    });

    // 3.1 Edit Geometry Vector Layer
    editGeometrySource = new VectorSource({ wrapX: false });
    editGeometryLayer = new VectorLayer({
      source: editGeometrySource,
      zIndex: 60,
      style: [
        new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: 'rgba(37, 99, 235, 0.35)',
            width: 8,
          }),
        }),
        new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: '#2563eb',
            width: 3.5,
            lineDash: [8, 6],
          }),
          image: new stylePkg.Circle({
            radius: 6,
            fill: new stylePkg.Fill({ color: '#2563eb' }),
            stroke: new stylePkg.Stroke({ color: '#ffffff', width: 2 }),
          }),
        }),
      ],
    });

    // 3.2 Split Vector Layer
    splitSource = new VectorSource({ wrapX: false });
    splitLayer = new VectorLayer({
      source: splitSource,
      zIndex: 65,
      style: (feature: any) => {
        if (feature.get('isMarker')) {
          return new stylePkg.Style({
            image: new stylePkg.Circle({
              radius: 8,
              fill: new stylePkg.Fill({ color: '#ef4444' }),
              stroke: new stylePkg.Stroke({ color: '#ffffff', width: 3 }),
            }),
          });
        }
        return [
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: 'rgba(245, 158, 11, 0.4)',
              width: 9,
            }),
          }),
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: '#d97706',
              width: 3.5,
            }),
          }),
        ];
      },
    });

    // 4. Overlays (Tooltip & Click Pulse)
    if (tooltipEl.value) {
      tooltipOverlay = new Overlay({
        element: tooltipEl.value,
        offset: [24, -20],
        positioning: 'bottom-left',
        stopEvent: false,
      });
    }

    if (pulseEl.value) {
      pulseOverlay = new Overlay({
        element: pulseEl.value,
        positioning: 'center-center',
        stopEvent: false,
      });
    }

    // 5. ScaleLine Control
    const scaleLineControl = new ScaleLine({
      target: scaleLineTarget.value || undefined,
      units: 'metric',
      minWidth: 80,
      bar: false,
    });

    // 6. Map Instance
    mapInstance = new Map({
      target: mapContainer.value,
      layers: [baseTileLayer, jalanPorosVectorTileLayer, vectorTileLayer, drawLayer, editGeometryLayer, splitLayer],
      view: new View({
        center: olModules.fromLonLat([111.88, -7.2]),
        zoom: 11,
        minZoom: 7,
        maxZoom: 20,
      }),
      overlays: [
        ...(tooltipOverlay ? [tooltipOverlay] : []),
        ...(pulseOverlay ? [pulseOverlay] : []),
      ],
      controls: [scaleLineControl],
    });

    // ─── Pointer Move Interaction (Coords & Hover Tooltip) ────────────────────
    mapInstance.on('pointermove', (e: any) => {
      if (e.dragging) {
        if (!isPopupPinned.value && tooltipOverlay) tooltipOverlay.setPosition(undefined);
        return;
      }

      if (isDrawing.value || isEditGeometryMode.value || isSplitMode.value) {
        if (mapContainer.value) mapContainer.value.style.cursor = isSplitMode.value ? 'crosshair' : (isDrawing.value ? 'crosshair' : 'default');
        hoveredFeature.value = null;
        if (!isPopupPinned.value) {
          tooltipData.value = null;
          if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        }
        return;
      }

      // Smooth coordinate update
      const lonLat = olModules.toLonLat(e.coordinate);
      const coordsText = `${lonLat[0].toFixed(5)}, ${lonLat[1].toFixed(5)}`;
      mouseCoordsText.value = coordsText;
      emit('update:mouseCoords', coordsText);

      // Throttled feature hit-test per animation frame
      const pixel = mapInstance.getEventPixel(e.originalEvent);
      if (pointerRafId !== null) return;
      pointerRafId = requestAnimationFrame(() => {
        pointerRafId = null;

        const targetLayer = getTargetVectorTileLayer();
        const targetVisible = isTargetLayerVisible();

        let hit: any = null;
        if (targetVisible && targetLayer) {
          mapInstance.forEachFeatureAtPixel(
            pixel,
            (f: any) => {
              hit = f;
              return true;
            },
            {
              layerFilter: (l: any) => l === targetLayer,
              hitTolerance: 8,
            }
          );
        }

        const prevHoverId = hoveredFeature.value ? String(hoveredFeature.value.get('id') || hoveredFeature.value.getId() || '') : '';
        const currentHitId = hit ? String(hit.get('id') || hit.getId() || '') : '';

        if (hit) {
          if (mapContainer.value) mapContainer.value.style.cursor = 'pointer';
          hoveredFeature.value = hit;
          if (!isPopupPinned.value) {
            tooltipData.value = extractFeatureData(hit, props.activeLayerId || 'infrastruktur-segmen');
            showTooltipAt(e.coordinate);
          }
        } else {
          if (mapContainer.value) mapContainer.value.style.cursor = '';
          hoveredFeature.value = null;
          if (!isPopupPinned.value) {
            tooltipData.value = null;
            if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
          }
        }

        if (currentHitId !== prevHoverId) {
          if (props.activeLayerId === 'jalan-poros-desa') {
            jalanPorosVectorTileLayer?.changed();
          } else {
            vectorTileLayer?.changed();
          }
        }
      });
    });

    // ─── Click Interaction (Feature Select & Popup) ───────────────────────────
    mapInstance.on('click', (e: any) => {
      if (isDrawing.value || isEditGeometryMode.value || isSplitMode.value) return;

      // Ignore clicks originating inside the popup card
      const origTarget = e.originalEvent?.target as HTMLElement | null;
      if (origTarget && tooltipEl.value && tooltipEl.value.contains(origTarget)) {
        return;
      }

      if (e.coordinate && pulseOverlay) {
        pulseOverlay.setPosition(e.coordinate);
        isPulseVisible.value = true;
      }

      if (olModules?.toLonLat && e.coordinate) {
        const lonLat = olModules.toLonLat(e.coordinate);
        if (lonLat && lonLat.length >= 2) {
          emit('mapClick', [lonLat[0], lonLat[1]]);
        }
      }

      const targetLayer = getTargetVectorTileLayer();
      const targetVisible = isTargetLayerVisible();

      if (!targetVisible || !targetLayer) {
        closePopup();
        emit('select', null);
        return;
      }

      const pixel = mapInstance.getEventPixel(e.originalEvent);
      let clicked: any = null;
      mapInstance.forEachFeatureAtPixel(
        pixel,
        (f: any) => {
          clicked = f;
          return true;
        },
        {
          layerFilter: (l: any) => l === targetLayer,
          hitTolerance: 8,
        }
      );

      if (clicked) {
        try {
          const featureData = extractFeatureData(clicked, props.activeLayerId || 'infrastruktur-segmen');
          tooltipData.value = featureData;
          isPopupPinned.value = true;
          showTooltipAt(e.coordinate);

          if (props.activeLayerId === 'jalan-poros-desa') {
            emit('log', 'INFO', `Jalan Poros Desa dipilih: ${featureData.namobj}`);
          } else {
            const propsObj = clicked.getProperties?.() ?? {};
            const featId = String(propsObj.id || clicked.getId() || clicked.get?.('id') || '');

            let centroid = null;
            if (propsObj.centroid) {
              try {
                centroid = typeof propsObj.centroid === 'string' ? JSON.parse(propsObj.centroid) : propsObj.centroid;
              } catch {
                centroid = null;
              }
            }

            const pMeter = propsObj.panjang_meter_gis != null && propsObj.panjang_meter_gis !== ''
              ? parseFloat(propsObj.panjang_meter_gis)
              : null;
            const pManual = propsObj.panjang != null && propsObj.panjang !== ''
              ? parseFloat(propsObj.panjang)
              : null;
            const finalPanjang = pMeter ?? pManual ?? 0;

            const normalizedFeature = {
              id: featId,
              type: 'Feature',
              geometry: null,
              properties: {
                ...propsObj,
                id: featId,
                namobj: propsObj.namobj || 'Segmen Fisik',
                tipe_kode: propsObj.tipe_kode || '-',
                kondisi: propsObj.kondisi || 'Baik',
                status_kondisi: propsObj.status_kondisi || 'Eksisting',
                panjang: pManual ?? pMeter ?? 0,
                panjang_meter_gis: finalPanjang,
                lebar: propsObj.lebar != null && propsObj.lebar !== '' ? parseFloat(propsObj.lebar) : 3.5,
                tahun_pembangunan: propsObj.tahun_pembangunan ? Number(propsObj.tahun_pembangunan) : null,
                sumber_dana: propsObj.sumber_dana || null,
                status_verifikasi: propsObj.status_verifikasi || 'draft',
                status_aset: propsObj.status_aset || null,
                desa: propsObj.desa || null,
                kecamatan: propsObj.kecamatan || null,
                id_desa: propsObj.id_desa != null && propsObj.id_desa !== '' ? Number(propsObj.id_desa) : null,
                id_kecamatan: propsObj.id_kecamatan != null && propsObj.id_kecamatan !== '' ? Number(propsObj.id_kecamatan) : null,
                plotting_id: propsObj.plotting_id || null,
                centroid,
              },
            };

            emit('select', normalizedFeature);
            emit('log', 'INFO', `Segmen dipilih: ${normalizedFeature.properties.namobj}`);
          }
        } catch (err) {
          console.error('Gagal memproses fitur klik:', err);
        }
      } else {
        closePopup();
        emit('select', null);
      }

      if (props.activeLayerId === 'jalan-poros-desa') {
        jalanPorosVectorTileLayer?.changed();
      } else {
        vectorTileLayer?.changed();
      }
    });

    isMapLoaded.value = true;
    emit('loaded');
    emit('log', 'INFO', 'Peta dimuat via PostGIS MVT.');

    if (props.bbox) {
      fitBounds();
    }
  } catch (err: any) {
    emit('log', 'ERROR', `Inisialisasi OpenLayers MVT gagal: ${err?.message}`);
    console.error('Inisialisasi OpenLayers MVT gagal:', err);
  }
}

// ─── Watchers ────────────────────────────────────────────────────────────────
let filterDebounceTimer: any = null;
watch(
  [
    () => props.tipeFilter,
    () => props.tahunPembangunanFilter,
    () => props.perkerasanFilter,
    () => props.kondisiFilter,
    () => props.statusVerifikasiFilter,
    () => props.statusJalanFilter,
    () => props.statusKondisiFilter,
    () => props.statusAsetFilter,
    () => props.sumberDanaFilter,
    () => props.kecamatanFilter,
    () => props.desaFilter,
  ],
  () => {
    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(() => {
      refreshVectorTiles(false);
    }, 150);
  }
);

watch(
  () => props.bbox,
  (newBbox) => {
    if (newBbox && isMapLoaded.value && !isDrawing.value) {
      fitBounds(newBbox);
    }
  },
  { deep: true }
);

watch(
  [() => props.selectedSegmenId, () => props.selectedSegmen],
  () => {
    if (vectorTileLayer) {
      vectorTileLayer.changed();
    }
  }
);

watch(
  () => props.basemap,
  (newBm) => {
    setBasemap(newBm);
  }
);

watch(
  () => props.layerVisible,
  (vis) => {
    if (vectorTileLayer) {
      vectorTileLayer.setVisible(vis);
    }
  }
);

watch(
  () => props.layerOpacity,
  (op) => {
    if (vectorTileLayer) {
      vectorTileLayer.setOpacity(op);
    }
  }
);

watch(
  () => props.symbology,
  () => {
    clearStyleCache();
    if (vectorTileLayer) {
      vectorTileLayer.changed();
    }
  },
  { deep: true }
);

// Watchers for Jalan Poros Desa MVT Layer
watch(
  () => props.jalanPorosDesaVisible,
  (vis) => {
    if (jalanPorosVectorTileLayer) {
      jalanPorosVectorTileLayer.setVisible(vis);
    }
  }
);

watch(
  () => props.jalanPorosDesaOpacity,
  (op) => {
    if (jalanPorosVectorTileLayer) {
      jalanPorosVectorTileLayer.setOpacity(op);
    }
  }
);

watch(
  () => props.jalanPorosDesaSymbology,
  () => {
    jalanPorosStyleCache.clear();
    if (jalanPorosVectorTileLayer) {
      jalanPorosVectorTileLayer.changed();
    }
  },
  { deep: true }
);

let jalanFilterDebounceTimer: any = null;
watch(
  [
    () => props.jalanPorosDesaKecamatanFilter,
    () => props.jalanPorosDesaDesaFilter,
    () => props.jalanPorosDesaKondisiFilter,
    () => props.jalanPorosDesaPerkerasanFilter,
  ],
  () => {
    clearTimeout(jalanFilterDebounceTimer);
    jalanFilterDebounceTimer = setTimeout(() => {
      refreshJalanPorosVectorTiles(false);
    }, 150);
  }
);

watch(colorMode, () => {
  clearStyleCache();
  if (vectorTileLayer) {
    vectorTileLayer.changed();
  }
});

watch(
  () => props.clickedCoordinate,
  (val) => {
    if (!val) {
      if (pulseOverlay) pulseOverlay.setPosition(undefined);
      isPulseVisible.value = false;
    } else if (pulseOverlay && olModules?.fromLonLat) {
      pulseOverlay.setPosition(olModules.fromLonLat(val));
      isPulseVisible.value = true;
    }
  }
);

onMounted(() => {
  initMap();

  if (mapContainer.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      if (resizeRaf) cancelAnimationFrame(resizeRaf);
      resizeRaf = requestAnimationFrame(() => {
        mapInstance?.updateSize();
      });
    });
    resizeObserver.observe(mapContainer.value);
  }
});

onUnmounted(() => {
  cancelDrawing(false);
  cancelEditGeometry();
  cancelSplitMode();
  clearTimeout(mvtSafetyTimeout);
  if (filterDebounceTimer) clearTimeout(filterDebounceTimer);
  if (jalanFilterDebounceTimer) clearTimeout(jalanFilterDebounceTimer);
  if (resizeRaf) {
    cancelAnimationFrame(resizeRaf);
    resizeRaf = null;
  }
  if (pointerRafId) {
    cancelAnimationFrame(pointerRafId);
    pointerRafId = null;
  }
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (mapInstance) {
    mapInstance.setTarget(undefined);
    mapInstance = null;
  }
});

defineExpose({
  fitBounds,
  zoomToFeature,
  zoomToGeometry,
  zoomIn,
  zoomOut,
  setBasemap,
  closePopup,
  refreshVectorTiles,
  refreshJalanPorosVectorTiles,
  startDrawing,
  cancelDrawing,
  handleUndo,
  handleRedo,
  handleRedraw,
  handleDrawClick,
  confirmDraw,
  isDrawing,
  isDrawInteracting,
  hasCompletedLine,
  drawnPointCount,
  drawnLengthMeters,
  redoStack,
  canUndo,
  canRedo,
  canSave,
  isSnappingEnabled,
  toggleSnapping,
  snapRoadCount,
  isSnapLoading,
  startEditGeometry,
  cancelEditGeometry,
  saveEditedGeometry,
  isEditGeometryMode,
  editedLengthMeters,
  startSplitMode,
  cancelSplitMode,
  isSplitMode,
});
</script>

<template>
  <div
    class="relative w-full h-full overflow-hidden select-none transition-colors duration-200"
    :class="currentBasemap === 'none' ? 'bg-white' : 'bg-gray-100 dark:bg-[#070b14]'"
  >
    <!-- OpenLayers Map Target Container -->
    <div
      ref="mapContainer"
      class="absolute inset-0 w-full h-full transition-colors duration-200"
      :class="currentBasemap === 'none' ? 'bg-white' : 'bg-gray-100 dark:bg-[#070b14]'"
    />

    <!-- ═══ FLOATING BAR: EDIT GEOMETRI MODE ═══════════════════════════════════ -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 -translate-y-2 scale-95"
    >
      <div
        v-if="isEditGeometryMode"
        class="absolute top-3 left-1/2 -translate-x-1/2 z-30 flex items-center gap-2.5 px-3.5 py-1.5 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md rounded-xl shadow-md border border-blue-500/40 text-xs text-gray-800 dark:text-gray-100"
      >
        <div class="flex items-center gap-1.5 font-medium text-blue-600 dark:text-blue-400">
          <UIcon name="i-lucide-pen-tool" class="size-4 animate-pulse shrink-0" />
          <span class="max-w-[200px] truncate">Edit: <strong>{{ editingSegmenData?.namobj || 'Segmen' }}</strong></span>
        </div>

        <UBadge :label="`${editedLengthMeters.toLocaleString('id-ID')} m`" color="primary" variant="subtle" size="xs" class="font-mono" />

        <div class="w-px h-4 bg-gray-200 dark:bg-gray-800" />

        <div class="flex items-center gap-1.5">
          <UButton size="xs" color="neutral" variant="ghost" label="Batal" @click="cancelEditGeometry" />
          <UButton size="xs" color="primary" icon="i-lucide-check" label="Simpan Geometri" @click="saveEditedGeometry" />
        </div>
      </div>
    </Transition>

    <!-- ═══ FLOATING BAR: SPLIT MODE ═══════════════════════════════════════════ -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 -translate-y-2 scale-95"
    >
      <div
        v-if="isSplitMode"
        class="absolute top-3 left-1/2 -translate-x-1/2 z-30 flex items-center gap-2.5 px-3.5 py-1.5 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md rounded-xl shadow-md border border-amber-500/40 text-xs text-gray-800 dark:text-gray-100"
      >
        <div class="flex items-center gap-1.5 font-medium text-amber-600 dark:text-amber-400">
          <UIcon name="i-lucide-scissors" class="size-4 animate-bounce shrink-0" />
          <span class="max-w-[240px] truncate">Klik garis untuk potong: <strong>{{ splitSegmenData?.namobj || 'Segmen' }}</strong></span>
        </div>

        <div class="w-px h-4 bg-gray-200 dark:bg-gray-800" />

        <UButton size="xs" color="neutral" variant="ghost" label="Batal Split" @click="cancelSplitMode" />
      </div>
    </Transition>

    <!-- Floating MVT Loading Indicator Badge (Top Left) -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-1.5 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 -translate-y-1.5 scale-95"
    >
      <div
        v-if="(isMvtLoading || loading) && isMapLoaded && !isDrawing"
        class="absolute top-3 left-3 z-20 flex items-center gap-2 px-2.5 py-1.5 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-xs border border-blue-500/30 dark:border-blue-500/30 rounded-lg shadow-sm pointer-events-none"
      >
        <UIcon
          name="i-lucide-loader-2"
          class="size-3.5 text-blue-600 dark:text-blue-400 animate-spin shrink-0"
        />
        <span class="text-xs font-medium text-gray-700 dark:text-gray-200">
          Memuat tile MVT...
        </span>
      </div>
    </Transition>

    <!-- ═══ FLOATING DRAW CONTROL (Vertical Toolbar on Left Side, UI/UX like MapControl) ═══ -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-x-2 scale-95"
      enter-to-class="opacity-100 translate-x-0 scale-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-x-0 scale-100"
      leave-to-class="opacity-0 -translate-x-2 scale-95"
    >
      <div
        v-if="isDrawing"
        class="absolute top-3 left-3 z-30 flex flex-col gap-1.5 items-start"
      >
        <!-- Vertical Toolbar Box (Identical aesthetic to MapControl) -->
        <div class="flex flex-col gap-0.5 p-1 bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
          <!-- 1. Draw -->
          <UTooltip text="Mode Gambar Garis (Draw)">
            <UButton
              icon="i-lucide-pencil"
              size="xs"
              :color="isDrawInteracting ? 'primary' : 'neutral'"
              :variant="isDrawInteracting ? 'solid' : 'ghost'"
              @click="handleDrawClick"
            />
          </UTooltip>

          <!-- 2. Redraw -->
          <UTooltip text="Gambar Ulang (Redraw)">
            <UButton
              icon="i-lucide-rotate-ccw"
              size="xs"
              color="neutral"
              variant="ghost"
              @click="handleRedraw"
            />
          </UTooltip>

          <!-- 3. Undo -->
          <UTooltip text="Undo (Batalkan Titik)">
            <UButton
              icon="i-lucide-undo-2"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="!canUndo"
              @click="handleUndo"
            />
          </UTooltip>

          <!-- 4. Redo -->
          <UTooltip text="Redo (Kembalikan Titik)">
            <UButton
              icon="i-lucide-redo-2"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="!canRedo"
              @click="handleRedo"
            />
          </UTooltip>

          <div class="w-full h-px bg-gray-200 dark:bg-gray-800 my-0.5" />

          <!-- Snapping Toggle Row with Status Indicator to the Right -->
          <div class="relative flex items-center">
            <UTooltip :text="isSnappingEnabled ? 'Snapping Aktif: Magnet Segmen dan Titik' : 'Snapping Nonaktif (Klik untuk Aktifkan)'">
              <UButton
                icon="i-lucide-magnet"
                size="xs"
                :color="isSnappingEnabled ? 'primary' : 'neutral'"
                :variant="isSnappingEnabled ? 'solid' : 'ghost'"
                @click="toggleSnapping"
              />
            </UTooltip>

            <!-- Indikator Snapping Aktif / Non Aktif di samping kanan toggle -->
            <div
              class="absolute left-full ml-1.5 flex items-center gap-1.5 px-2 py-0.5 rounded-md border text-[10px] font-medium select-none whitespace-nowrap shadow-xs transition-all cursor-pointer"
              :class="isSnappingEnabled
                ? 'bg-blue-50/95 dark:bg-blue-950/80 border-blue-200 dark:border-blue-800/80 text-blue-700 dark:text-blue-300'
                : 'bg-white/95 dark:bg-[#0b0f19]/95 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400'"
              :title="isSnappingEnabled ? 'Klik untuk menonaktifkan snapping' : 'Klik untuk mengaktifkan snapping'"
              @click="toggleSnapping"
            >
              <span
                class="size-1.5 rounded-full shrink-0"
                :class="isSnappingEnabled ? 'bg-blue-500 animate-pulse' : 'bg-gray-400 dark:bg-gray-500'"
              />
              <span>{{ isSnappingEnabled ? 'Aktif' : 'Non Aktif' }}</span>
              <span
                v-if="isSnappingEnabled && isSnapLoading"
                class="flex items-center gap-0.5 text-[9px] text-blue-600/70"
              >
                <UIcon name="i-lucide-loader-2" class="size-2 animate-spin" />
              </span>
              <span
                v-else-if="isSnappingEnabled && snapRoadCount > 0"
                class="text-[9px] font-mono text-blue-600/80 dark:text-blue-400/80"
              >
                ({{ snapRoadCount }})
              </span>
            </div>
          </div>

          <div class="w-full h-px bg-gray-200 dark:bg-gray-800 my-0.5" />

          <!-- 5. Simpan Row with Length & Vertex Calculation Indicator to the Right -->
          <div class="relative flex items-center">
            <UTooltip text="Simpan Garis (Buka Form)">
              <UButton
                icon="i-lucide-check"
                size="xs"
                color="primary"
                variant="solid"
                :disabled="!canSave"
                @click="confirmDraw"
              />
            </UTooltip>

            <!-- Indikator Kalkulasi Panjang & Vertex disamping kanan tombol simpan -->
            <Transition
              enter-active-class="transition-all duration-200 ease-out"
              enter-from-class="opacity-0 translate-x-1"
              enter-to-class="opacity-100 translate-x-0"
              leave-active-class="transition-all duration-150 ease-in"
              leave-from-class="opacity-100 translate-x-0"
              leave-to-class="opacity-0 translate-x-1"
            >
              <div
                v-if="drawnPointCount > 0"
                class="absolute left-full ml-1.5 flex items-center gap-1.5 px-2 py-0.5 rounded-md border text-[10px] select-none whitespace-nowrap shadow-xs backdrop-blur-xs bg-white/95 dark:bg-[#0b0f19]/95 border-gray-200 dark:border-gray-800"
              >
                <!-- Panjang (Meter) -->
                <div class="flex items-center gap-1 font-mono font-semibold text-blue-600 dark:text-blue-400">
                  <UIcon name="i-lucide-route" class="size-3 shrink-0" />
                  <span>{{ drawnLengthMeters.toLocaleString('id-ID') }} m</span>
                </div>

                <div class="w-px h-3 bg-gray-200 dark:bg-gray-800 shrink-0" />

                <!-- Titik / Vertex -->
                <div class="flex items-center gap-1 text-gray-500 dark:text-gray-400 font-mono">
                  <span>{{ drawnPointCount }} vertex</span>
                  <span
                    v-if="hasCompletedLine"
                    class="text-[9px] font-sans font-medium text-blue-500"
                  >
                    (Selesai)
                  </span>
                </div>
              </div>
            </Transition>
          </div>

          <!-- 6. Batal / Tutup -->
          <UTooltip text="Batal dan Tutup Draw">
            <UButton
              icon="i-lucide-x"
              size="xs"
              color="neutral"
              variant="ghost"
              class="text-gray-400 hover:text-red-500 dark:hover:text-red-400"
              @click="cancelDrawing(true)"
            />
          </UTooltip>
        </div>
      </div>
    </Transition>

    <!-- ═══ FLOATING NAVIGATION CONTROLS (Top Right, Clean Solid) ═══════════════ -->
    <div class="absolute top-3 right-3 z-20 flex flex-col gap-0.5 p-1 bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
      <UTooltip text="Perbesar (+)">
        <UButton
          icon="i-lucide-plus"
          size="xs"
          color="neutral"
          variant="ghost"
          @click="zoomIn"
        />
      </UTooltip>

      <UTooltip text="Perkecil (-)">
        <UButton
          icon="i-lucide-minus"
          size="xs"
          color="neutral"
          variant="ghost"
          @click="zoomOut"
        />
      </UTooltip>

      <div class="w-full h-px bg-gray-200 dark:bg-gray-800 my-0.5" />

      <UTooltip text="Fokus Semua Segmen">
        <UButton
          icon="i-lucide-maximize-2"
          size="xs"
          color="primary"
          variant="ghost"
          @click="fitBounds()"
        />
      </UTooltip>
    </div>

    <!-- ═══ FLOATING BASEMAP SELECTOR (Bottom Left, Clean Solid) ════════════════ -->
    <div class="absolute bottom-16 sm:bottom-3 left-3 z-20 flex items-center gap-1 p-1 bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
      <UButton
        v-for="bm in (['osm', 'dark', 'satellite', 'topo', 'none'] as const)"
        :key="bm"
        :label="bm === 'satellite' ? 'Satelit' : bm === 'dark' ? 'Gelap' : bm === 'topo' ? 'Topografi' : bm === 'none' ? 'None' : 'OSM'"
        size="xs"
        :color="currentBasemap === bm ? 'primary' : 'neutral'"
        :variant="currentBasemap === bm ? 'subtle' : 'ghost'"
        class="capitalize text-xs font-medium cursor-pointer"
        @click="setBasemap(bm)"
      />
    </div>

    <!-- ═══ FLOATING COORDINATES PILL (Desktop only, Bottom Center) ═════════════ -->
    <div class="hidden md:flex absolute bottom-3 left-1/2 -translate-x-1/2 z-20 pointer-events-none">
      <UBadge
        :label="mouseCoordsText || '0.00000, 0.00000'"
        color="neutral"
        variant="subtle"
        size="xs"
        class="font-mono shadow-sm px-2.5 py-0.5"
      />
    </div>

    <!-- ═══ FLOATING SCALE LINE CONTAINER (Bottom Right, Clean Glassmorphic) ═════ -->
    <div
      ref="scaleLineTarget"
      class="absolute bottom-16 sm:bottom-3 right-3 z-20 pointer-events-none select-none"
    />

    <!-- ═══ MAP FEATURE POPUP / TOOLTIP OVERLAY ═══════════════════════════════════ -->
    <div
      ref="tooltipEl"
      :class="[
        'transition-all duration-75 z-30 select-none relative',
        isPopupPinned ? 'pointer-events-auto' : 'pointer-events-none'
      ]"
    >
      <div
        v-if="tooltipData"
        class="relative p-3 rounded-xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800/90 shadow-2xl max-w-xs text-xs space-y-2 min-w-[220px]"
      >
        <!-- ═══ CALLOUT LEADER GEOMETRY & TARGET PINPOINT ═══ -->
        <!-- Anchored at Card Bottom-Left Corner, pointing down-left (-24px, +20px) to Mouse Pointer -->
        <div
          class="absolute -left-6 top-[calc(100%-4px)] w-8 h-7 pointer-events-none z-30 overflow-visible"
        >
          <!-- SVG Leader Line connecting Card Corner (24, 4) down-left to Mouse/Target Anchor (0, 24) -->
          <svg
            class="w-full h-full overflow-visible"
            viewBox="0 0 32 28"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <!-- White/Dark High-Contrast Casing Line -->
            <line
              x1="24"
              y1="4"
              x2="0"
              y2="24"
              :stroke="colorMode.value === 'dark' ? '#020617' : '#ffffff'"
              stroke-width="5"
              stroke-linecap="round"
            />
            <!-- Main Accent Leader Line -->
            <line
              x1="24"
              y1="4"
              x2="0"
              y2="24"
              :stroke="tooltipData.layerId === 'jalan-poros-desa' ? '#2563eb' : '#059669'"
              stroke-width="2.5"
              stroke-linecap="round"
            />
            <!-- Connector Dot on Card Border -->
            <circle
              cx="24"
              cy="4"
              r="2.5"
              :fill="tooltipData.layerId === 'jalan-poros-desa' ? '#2563eb' : '#059669'"
              stroke="#ffffff"
              stroke-width="1"
            />
          </svg>

          <!-- Pin Target Anchor Pinpoint directly on Mouse / Feature Coordinate (0, 24) -->
          <div
            class="absolute -left-1.5 top-[18px] size-3 flex items-center justify-center pointer-events-none"
          >
            <span
              class="absolute size-4 rounded-full animate-ping"
              :class="tooltipData.layerId === 'jalan-poros-desa' ? 'bg-blue-500/50' : 'bg-emerald-500/50'"
            />
            <span
              class="relative size-2.5 rounded-full ring-2 ring-white dark:ring-gray-950 shadow-md"
              :class="tooltipData.layerId === 'jalan-poros-desa' ? 'bg-blue-600' : 'bg-emerald-600'"
            />
          </div>
        </div>
        <!-- Header: Layer Indicator Tag & Close Button -->
        <div class="flex items-center justify-between gap-1.5 pb-1.5 border-b border-gray-100 dark:border-gray-800/80">
          <div class="flex items-center gap-1.5 min-w-0">
            <span
              class="size-2 rounded-full shrink-0"
              :class="tooltipData.layerId === 'jalan-poros-desa' ? 'bg-blue-500 ring-2 ring-blue-500/20' : 'bg-emerald-500 ring-2 ring-emerald-500/20'"
            />
            <span class="text-[11px] font-semibold text-gray-700 dark:text-gray-300 truncate">
              {{ tooltipData.layerTitle }}
            </span>
          </div>

          <div class="flex items-center gap-1 shrink-0">
            <UBadge
              v-if="tooltipData.kode_ruas"
              :label="tooltipData.kode_ruas"
              color="primary"
              variant="subtle"
              size="xs"
              class="text-[9px] px-1 py-0 h-4 font-mono font-medium"
            />
            <button
              v-if="isPopupPinned"
              type="button"
              class="size-5 rounded flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
              title="Tutup popup"
              @click.stop="closePopup"
            >
              <UIcon name="i-lucide-x" class="size-3.5" />
            </button>
          </div>
        </div>

        <!-- Nama Ruas / Nama Objek -->
        <div class="font-bold text-gray-900 dark:text-white line-clamp-2 text-xs leading-snug">
          {{ tooltipData.namobj }}
        </div>

        <!-- Badges & Ukuran -->
        <div class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-400 flex-wrap">
          <UBadge
            :label="tooltipData.kondisi"
            :color="
              tooltipData.kondisi.toLowerCase().includes('baik') ? 'success' :
              tooltipData.kondisi.toLowerCase().includes('sedang') ? 'warning' : 'error'
            "
            variant="subtle"
            size="xs"
            class="text-[10px] font-medium"
          />
          <UBadge
            v-if="tooltipData.perkerasan"
            :label="tooltipData.perkerasan"
            color="neutral"
            variant="outline"
            size="xs"
            class="text-[10px]"
          />
          <span v-if="tooltipData.panjang > 0" class="font-mono text-gray-800 dark:text-gray-200 font-semibold text-[11px]">
            {{ Math.round(tooltipData.panjang).toLocaleString('id-ID') }} m
          </span>
          <span v-if="tooltipData.lebar" class="text-gray-500 dark:text-gray-400 text-[10px] font-mono">
            (L: {{ tooltipData.lebar }} m)
          </span>
        </div>

        <!-- Info Wilayah Desa & Kecamatan -->
        <div class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 pt-0.5">
          <UIcon name="i-lucide-map-pin" class="size-3 shrink-0 text-blue-500" />
          <span class="truncate">{{ tooltipData.desa }}, Kec. {{ tooltipData.kecamatan }}</span>
        </div>

        <!-- Status Kondisi / Verifikasi Footer -->
        <div
          v-if="tooltipData.status_kondisi || tooltipData.status_eksisting || tooltipData.status_verifikasi"
          class="flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800/80 pt-1 font-mono"
        >
          <span v-if="tooltipData.status_kondisi || tooltipData.status_eksisting" class="capitalize">
            Status: {{ tooltipData.status_kondisi || tooltipData.status_eksisting }}
          </span>
          <span v-if="tooltipData.status_verifikasi" class="capitalize ml-auto">
            {{ tooltipData.status_verifikasi }}
          </span>
        </div>
      </div>
    </div>

    <!-- ═══ CLICK PULSE OVERLAY ═════════════════════════════════════════════════ -->
    <div
      ref="pulseEl"
      class="pointer-events-none z-20 flex items-center justify-center size-8"
      :class="isPulseVisible ? 'block' : 'hidden'"
    >
      <span class="absolute size-8 rounded-full bg-blue-500/40 animate-ping" />
      <span class="absolute size-5 rounded-full bg-blue-500/25" />
      <span class="relative size-3.5 rounded-full bg-blue-600 ring-2 ring-white dark:ring-gray-950 shadow-md flex items-center justify-center">
        <span class="size-1 rounded-full bg-white" />
      </span>
    </div>

  </div>
</template>

<style scoped>
:deep(.ol-scale-line) {
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(229, 231, 235, 1);
  border-radius: 0.5rem;
  padding: 3px 8px 3px 8px;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  display: inline-block;
  position: relative;
  bottom: auto;
  left: auto;
  right: auto;
}

:global(.dark) :deep(.ol-scale-line) {
  background: rgba(11, 15, 25, 0.92);
  border-color: rgba(31, 41, 55, 1);
}

:deep(.ol-scale-line-inner) {
  border: 2px solid #059669;
  border-top: none;
  color: #111827;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 10px;
  font-weight: 600;
  text-align: center;
  line-height: 1.2;
  margin: 0 auto;
  padding: 0 4px;
}

:global(.dark) :deep(.ol-scale-line-inner) {
  border-color: #10b981;
  color: #f3f4f6;
}

:deep(.ol-viewport),
:deep(.ol-viewport:focus),
:deep(.ol-viewport:focus-visible),
:deep(.ol-viewport canvas) {
  outline: none !important;
  border: none !important;
}
</style>

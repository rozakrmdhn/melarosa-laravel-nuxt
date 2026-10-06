<script setup lang="ts">
import 'ol/ol.css';
import { $http } from '~/utils/helpers';
import type { GeoJsonFeatureCollection, InfrastrukturSegmen } from '~/types/infrastruktur';
import type { BasemapType, LayerSymbology } from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY } from '~/types/dataset-editor';

interface Props {
  apiBase?: string;
  geojsonData?: GeoJsonFeatureCollection | null;
  selectedSegmenId?: string | null;
  selectedSegmen?: InfrastrukturSegmen | null;
  loading?: boolean;
  basemap?: BasemapType;
  tipeFilter?: string | null;
  kondisiFilter?: string | null;
  statusVerifikasiFilter?: string | null;
  kecamatanFilter?: number | null;
  desaFilter?: number | null;
  layerVisible?: boolean;
  layerOpacity?: number;
  symbology?: LayerSymbology;
  bbox?: [number, number, number, number] | null;
  clickedCoordinate?: [number, number] | null;

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
  geojsonData: null,
  selectedSegmenId: null,
  selectedSegmen: null,
  loading: false,
  basemap: 'osm',
  tipeFilter: null,
  kondisiFilter: null,
  statusVerifikasiFilter: null,
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
    ...DEFAULT_SYMBOLOGY,
    lineColor: '#2563eb',
    lineWidth: 2,
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

let resizeObserver: ResizeObserver | null = null;
let resizeRaf: number | null = null;
let pointerRafId: number | null = null;

// Hover & Tooltip State
const hoveredFeature = ref<any>(null);
const tooltipData = ref<{
  id: string;
  namobj: string;
  tipe_kode: string;
  kondisi: string;
  panjang: number;
  desa: string;
  kecamatan: string;
} | null>(null);

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

  const sym = props.jalanPorosDesaSymbology || DEFAULT_SYMBOLOGY;

  let strokeColor = sym.lineColor || '#2563eb';
  const kondisi = feature.get('kondisi');
  const perkerasan = feature.get('perkerasan');

  if (sym.colorMode === 'kondisi') {
    if (kondisi === 'Baik') strokeColor = '#10b981';
    else if (kondisi === 'Sedang') strokeColor = '#f59e0b';
    else if (kondisi === 'Rusak Ringan') strokeColor = '#f97316';
    else if (kondisi === 'Rusak Berat') strokeColor = '#ef4444';
  } else if (sym.colorMode === 'perkerasan') {
    if (perkerasan === 'Aspal') strokeColor = '#3b82f6';
    else if (perkerasan === 'Beton') strokeColor = '#10b981';
    else if (perkerasan === 'Kerikil') strokeColor = '#f59e0b';
    else if (perkerasan === 'Tanah') strokeColor = '#8b5cf6';
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
  if (!olModules) return null;
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
    baseTileLayer.setSource(getBasemapSource(type));
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
  if (props.kondisiFilter && !String(propsObj.kondisi || '').toLowerCase().includes(props.kondisiFilter.toLowerCase().trim())) return null;
  if (props.statusVerifikasiFilter && String(propsObj.status_verifikasi || '').trim().toLowerCase() !== props.statusVerifikasiFilter.trim().toLowerCase()) return null;
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
  const hoverId = hoveredFeature.value ? String(hoveredFeature.value.get('id') || hoveredFeature.value.getId() || '') : '';
  const isHovered = Boolean(hoverId && hoverId === featId);

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

  if (isSelected) {
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
      source: getBasemapSource(currentBasemap.value),
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

    // 4. Overlays (Tooltip & Click Pulse)
    if (tooltipEl.value) {
      tooltipOverlay = new Overlay({
        element: tooltipEl.value,
        offset: [0, -10],
        positioning: 'bottom-center',
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
      layers: [baseTileLayer, jalanPorosVectorTileLayer, vectorTileLayer, drawLayer],
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
        if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        return;
      }

      if (isDrawing.value) {
        if (mapContainer.value) mapContainer.value.style.cursor = 'crosshair';
        hoveredFeature.value = null;
        tooltipData.value = null;
        if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
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

        let hit: any = null;
        mapInstance.forEachFeatureAtPixel(
          pixel,
          (f: any) => {
            hit = f;
            return true;
          },
          {
            layerFilter: (l: any) => l === vectorTileLayer,
            hitTolerance: 8,
          }
        );

        const prevHoverId = hoveredFeature.value ? String(hoveredFeature.value.get('id') || hoveredFeature.value.getId() || '') : '';
        const currentHitId = hit ? String(hit.get('id') || hit.getId() || '') : '';

        if (hit) {
          if (mapContainer.value) mapContainer.value.style.cursor = 'pointer';
          hoveredFeature.value = hit;
          const p = hit.getProperties?.() ?? {};
          tooltipData.value = {
            id: currentHitId,
            namobj: p.namobj || 'Segmen Fisik',
            tipe_kode: p.tipe_kode || '-',
            kondisi: p.kondisi || '-',
            panjang: parseFloat(p.panjang_meter_gis || p.panjang || '0'),
            desa: p.desa || '-',
            kecamatan: p.kecamatan || '-',
          };
          if (tooltipOverlay) tooltipOverlay.setPosition(e.coordinate);
        } else {
          if (mapContainer.value) mapContainer.value.style.cursor = '';
          hoveredFeature.value = null;
          tooltipData.value = null;
          if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        }

        if (currentHitId !== prevHoverId) {
          vectorTileLayer?.changed();
        }
      });
    });

    // ─── Click Interaction (Feature Select) ────────────────────────────────────
    mapInstance.on('click', (e: any) => {
      if (isDrawing.value) return;

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

      if (!props.layerVisible) return;

      const pixel = mapInstance.getEventPixel(e.originalEvent);
      let clicked: any = null;
      mapInstance.forEachFeatureAtPixel(
        pixel,
        (f: any) => {
          clicked = f;
          return true;
        },
        {
          layerFilter: (l: any) => l === vectorTileLayer,
          hitTolerance: 8,
        }
      );

      if (clicked) {
        try {
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
        } catch (err) {
          console.error('Gagal memproses fitur segmen klik:', err);
        }
      } else {
        emit('select', null);
      }

      vectorTileLayer?.changed();
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
    () => props.kondisiFilter,
    () => props.statusVerifikasiFilter,
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
});
</script>

<template>
  <div class="relative w-full h-full overflow-hidden select-none bg-gray-100 dark:bg-[#070b14]">
    <!-- OpenLayers Map Target Container -->
    <div ref="mapContainer" class="absolute inset-0 w-full h-full bg-gray-100 dark:bg-[#070b14]" />

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
        v-for="bm in (['osm', 'dark', 'satellite', 'topo'] as const)"
        :key="bm"
        :label="bm === 'satellite' ? 'Satelit' : bm === 'dark' ? 'Gelap' : bm === 'topo' ? 'Topografi' : 'OSM'"
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

    <!-- ═══ MAP HOVER TOOLTIP OVERLAY ═══════════════════════════════════════════ -->
    <div
      ref="tooltipEl"
      class="pointer-events-none transition-transform duration-75"
    >
      <div
        v-if="tooltipData"
        class="p-2.5 rounded-lg bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200 dark:border-gray-800 shadow-xl max-w-xs text-xs space-y-1"
      >
        <div class="font-bold text-gray-900 dark:text-white line-clamp-1">
          {{ tooltipData.namobj }}
        </div>
        <div class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
          <UBadge
            :label="tooltipData.kondisi"
            :color="
              tooltipData.kondisi.toLowerCase().includes('baik') ? 'success' :
              tooltipData.kondisi.toLowerCase().includes('sedang') ? 'warning' : 'error'
            "
            variant="subtle"
            size="xs"
          />
          <span v-if="tooltipData.panjang > 0" class="font-mono">
            {{ Math.round(tooltipData.panjang).toLocaleString('id-ID') }} m
          </span>
        </div>
        <div class="text-[10px] text-gray-400 dark:text-gray-500">
          {{ tooltipData.desa }}, Kec. {{ tooltipData.kecamatan }}
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

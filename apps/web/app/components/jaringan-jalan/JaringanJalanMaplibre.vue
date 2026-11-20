<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BasemapSwitcher from '~/components/common/BasemapSwitcher.vue';
import { useDefaultBasemap } from '~/composables/useDefaultBasemap';
import { useBasemapStore } from '~/stores/basemap';
import { buildDynamicMaplibreBasemapSpec, resolveAbsoluteTileUrl } from '~/utils/basemap-sources';
import { computeGeometryBbox, getStatusAnalisaMeta } from '~/utils/jaringan-jalan-format';
import type {
  JaringanMapLayersState,
  JaringanMapOpacityState,
  JaringanMapSelection,
  RtrwAnalisaMapIndex,
  RtrwColorMode,
  StatusAnalisaRtrw,
} from '~/types/jaringan-jalan';

interface Props {
  mapIndex: RtrwAnalisaMapIndex | null;
  selection: JaringanMapSelection | null;
  layers: JaringanMapLayersState;
  layersOpacity?: JaringanMapOpacityState;
  colorModeType: RtrwColorMode;
  activeBounds?: [number, number, number, number] | null;
  loadingIndex?: boolean;
  fullBleedCanvas?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  layersOpacity: () => ({
    rtrw: 0.92,
    poros: 0.88,
    kabupaten: 0.95,
    kecamatan: 0.72,
    desa: 0.48,
  }),
  activeBounds: null,
  loadingIndex: false,
  fullBleedCanvas: true,
});

const emit = defineEmits<{
  (e: 'update:layers', val: JaringanMapLayersState): void;
  (e: 'update:colorModeType', val: RtrwColorMode): void;
  (e: 'select-rtrw', id: number): void;
  (e: 'select-poros', id: string): void;
  (e: 'select-kabupaten', id: number): void;
  (e: 'clear-selection'): void;
  (e: 'martin-status', payload: { ok: boolean; errorCount: number; message?: string }): void;
}>();

const BOJONEGORO_BOUNDS: [[number, number], [number, number]] = [
  [111.415, -7.475],
  [112.17, -6.975],
];

const config = useRuntimeConfig();
const colorMode = useColorMode();
const basemapStore = useBasemapStore();
const { currentBasemap, setBasemapChoice } = useDefaultBasemap<string>('maplibre', (code) => {
  applyBasemapSwitch(code);
});

const mapContainerRef = ref<HTMLElement | null>(null);
const isMapReady = ref(false);
const martinStatusState = ref<'ready' | 'loading' | 'warning'>('loading');
const martinWarningDetail = ref<string | null>(null);
const showMartinPopover = ref(false);
const currentZoom = ref(10.5);
const currentBearing = ref(0);
const cursorCoord = ref<{ lng: number; lat: number } | null>(null);
const hoverTooltip = ref<{
  x: number;
  y: number;
  layerLabel: string;
  title: string;
  subtitle: string;
  badge?: string;
} | null>(null);

let mapInstance: any = null;
let maplibregl: any = null;
let resizeObserver: ResizeObserver | null = null;
let canvasReadyFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let lastObservedW = 0;
let lastObservedH = 0;
let tileErrorCount = 0;
let lastImmediateSelectKey: string | null = null;

const appliedStatusCache = new Map<number, StatusAnalisaRtrw>();
const appliedDimCache = new Map<number, boolean>();

const martinBase = computed(() =>
  String(config.public.martinUrl || '/martin').replace(/\/+$/, ''),
);

const isDarkTheme = computed(() => colorMode.value === 'dark');

function martinTileUrl(sourceId: string): string {
  return resolveAbsoluteTileUrl(`${martinBase.value}/${sourceId}/{z}/{x}/{y}`);
}

function buildRtrwPaintColor(mode: RtrwColorMode): any {
  if (mode === 'orde') {
    return [
      'match',
      ['get', 'orde02'],
      'Jalan Desa',
      '#d97706',
      'Jaringan Jalan Kabupaten',
      '#0284c7',
      'Jaringan Jalan Provinsi',
      '#7c3aed',
      'Sistem Jaringan Jalan Nasional',
      '#dc2626',
      '#64748b',
    ];
  }

  return [
    'case',
    ['==', ['feature-state', 'status'], 'tumpang_tindih_keduanya'],
    '#e11d48',
    ['==', ['feature-state', 'status'], 'terdata_jalan_kabupaten'],
    '#0284c7',
    ['==', ['feature-state', 'status'], 'terdata_poros_desa'],
    '#059669',
    ['==', ['feature-state', 'status'], 'irisan_sebagian'],
    '#7c3aed',
    ['==', ['feature-state', 'status'], 'belum_terdata'],
    '#d97706',
    '#64748b',
  ];
}

function buildHighlightGeoJson(sel: JaringanMapSelection | null) {
  const features: any[] = [];
  if (!sel) {
    return { type: 'FeatureCollection', features };
  }

  const pushGeom = (geom: any, role: string) => {
    if (!geom) return;
    const parsed = typeof geom === 'string' ? JSON.parse(geom) : geom;
    const geometry = parsed?.type === 'Feature' ? parsed.geometry : parsed;
    if (!geometry || !geometry.type) return;
    features.push({
      type: 'Feature',
      geometry,
      properties: { role },
    });
  };

  try {
    if (sel.kind === 'rtrw') {
      for (const kg of sel.matchedKabupatenGeojson ?? []) {
        pushGeom(kg, 'matched-kabupaten');
      }
      for (const pg of sel.matchedPorosGeojson ?? []) {
        pushGeom(pg, 'matched-poros');
      }
    }
    pushGeom(sel.geojson, 'primary');
  } catch {
    // Abaikan geometri rusak agar peta tetap stabil
  }

  return { type: 'FeatureCollection', features };
}

function buildMapStyle(basemapCode: string) {
  const item = basemapStore.getByCode(basemapCode, 'maplibre');
  const { sources, layers } = buildDynamicMaplibreBasemapSpec(item ?? basemapCode, {
    baseOpacity: 0.92,
  });

  const dark = isDarkTheme.value;
  const kecLineColor = dark ? '#94a3b8' : '#475569';
  const desaLineColor = dark ? 'rgba(148, 163, 184, 0.85)' : 'rgba(71, 85, 105, 0.85)';

  sources['martin-kecamatan'] = {
    type: 'vector',
    tiles: [martinTileUrl('bataswilayah_kecamatan')],
    minzoom: 8,
    maxzoom: 18,
  };
  sources['martin-desa'] = {
    type: 'vector',
    tiles: [martinTileUrl('bataswilayah_desa')],
    minzoom: 10,
    maxzoom: 19,
  };
  sources['martin-rtrw'] = {
    type: 'vector',
    tiles: [martinTileUrl('rtrw_jaringan_transportasi_ln')],
    minzoom: 8,
    maxzoom: 19,
    promoteId: { rtrw_jaringan_transportasi_ln: 'id' },
  };
  sources['martin-poros'] = {
    type: 'vector',
    tiles: [martinTileUrl('jalan_porosdesa')],
    minzoom: 8,
    maxzoom: 19,
  };
  sources['martin-kabupaten'] = {
    type: 'vector',
    tiles: [martinTileUrl('jalan_kabupaten_luarkota')],
    minzoom: 8,
    maxzoom: 19,
  };
  sources['selection-highlight'] = {
    type: 'geojson',
    data: buildHighlightGeoJson(props.selection),
  };

  const op = props.layersOpacity;

  layers.push(
    {
      id: 'layer-kecamatan-line',
      type: 'line',
      source: 'martin-kecamatan',
      'source-layer': 'bataswilayah_kecamatan',
      layout: {
        visibility: props.layers.kecamatan ? 'visible' : 'none',
      },
      paint: {
        'line-color': kecLineColor,
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 0.8, 13, 1.5],
        'line-dasharray': [4, 2],
        'line-opacity': op.kecamatan ?? 0.72,
      },
    },
    {
      id: 'layer-desa-line',
      type: 'line',
      source: 'martin-desa',
      'source-layer': 'bataswilayah_desa',
      minzoom: 10.5,
      layout: {
        visibility: props.layers.desa ? 'visible' : 'none',
      },
      paint: {
        'line-color': desaLineColor,
        'line-width': 0.7,
        'line-dasharray': [2, 2],
        'line-opacity': op.desa ?? 0.48,
      },
    },
    {
      id: 'layer-rtrw-lines',
      type: 'line',
      source: 'martin-rtrw',
      'source-layer': 'rtrw_jaringan_transportasi_ln',
      filter: ['!=', ['get', 'orde01'], 'Sistem Jaringan Kereta Api'],
      layout: {
        visibility: props.layers.rtrw ? 'visible' : 'none',
        'line-cap': 'round',
        'line-join': 'round',
      },
      paint: {
        'line-color': buildRtrwPaintColor(props.colorModeType),
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 1.1, 13, 2.2, 16, 3.6],
        'line-opacity': [
          'case',
          ['boolean', ['feature-state', 'dim'], false],
          Math.min(0.18, (op.rtrw ?? 0.92) * 0.2),
          op.rtrw ?? 0.92,
        ],
      },
    },
    {
      id: 'layer-poros-lines',
      type: 'line',
      source: 'martin-poros',
      'source-layer': 'jalan_porosdesa',
      layout: {
        visibility: props.layers.poros ? 'visible' : 'none',
        'line-cap': 'round',
        'line-join': 'round',
      },
      paint: {
        'line-color': ['case', ['==', ['get', 'sumber_data'], 'rtrw_orde'], '#4f46e5', '#0d9488'],
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 1.2, 13, 2.4, 16, 3.8],
        'line-opacity': op.poros ?? 0.88,
      },
    },
    {
      id: 'layer-kabupaten-casing',
      type: 'line',
      source: 'martin-kabupaten',
      'source-layer': 'jalan_kabupaten_luarkota',
      layout: {
        visibility: props.layers.kabupaten ? 'visible' : 'none',
        'line-cap': 'round',
        'line-join': 'round',
      },
      paint: {
        'line-color': dark ? '#0f172a' : '#ffffff',
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 2.4, 13, 4, 16, 6],
        'line-opacity': Math.max(0.1, (op.kabupaten ?? 0.95) * 0.88),
      },
    },
    {
      id: 'layer-kabupaten-lines',
      type: 'line',
      source: 'martin-kabupaten',
      'source-layer': 'jalan_kabupaten_luarkota',
      layout: {
        visibility: props.layers.kabupaten ? 'visible' : 'none',
        'line-cap': 'round',
        'line-join': 'round',
      },
      paint: {
        'line-color': dark ? '#38bdf8' : '#1d4ed8',
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 1.4, 13, 2.6, 16, 4.2],
        'line-opacity': op.kabupaten ?? 0.95,
      },
    },
    {
      id: 'highlight-matched-kab',
      type: 'line',
      source: 'selection-highlight',
      filter: ['==', ['get', 'role'], 'matched-kabupaten'],
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': '#0284c7',
        'line-width': 5.5,
        'line-opacity': 0.65,
      },
    },
    {
      id: 'highlight-matched-poros',
      type: 'line',
      source: 'selection-highlight',
      filter: ['==', ['get', 'role'], 'matched-poros'],
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': '#10b981',
        'line-width': 5.5,
        'line-opacity': 0.65,
      },
    },
    {
      id: 'highlight-primary-halo-outer',
      type: 'line',
      source: 'selection-highlight',
      filter: ['==', ['get', 'role'], 'primary'],
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': '#38bdf8',
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 9, 14, 13, 17, 16],
        'line-blur': 6,
        'line-opacity': 0.48,
      },
    },
    {
      id: 'highlight-primary-halo-inner',
      type: 'line',
      source: 'selection-highlight',
      filter: ['==', ['get', 'role'], 'primary'],
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': '#0ea5e9',
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 5.2, 14, 7.2, 17, 9],
        'line-blur': 1.8,
        'line-opacity': 0.85,
      },
    },
    {
      id: 'highlight-primary-core',
      type: 'line',
      source: 'selection-highlight',
      filter: ['==', ['get', 'role'], 'primary'],
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': '#ffffff',
        'line-width': ['interpolate', ['linear'], ['zoom'], 9, 2, 14, 3, 17, 4],
        'line-opacity': 1,
      },
    },
  );

  return {
    version: 8 as const,
    sources,
    layers,
  };
}

function syncRtrwFeatureStates(forceAll = false) {
  if (!mapInstance || !props.mapIndex || !mapInstance.getSource('martin-rtrw')) return;

  if (forceAll) {
    appliedStatusCache.clear();
    appliedDimCache.clear();
  }

  const hasFilter = props.mapIndex.has_active_filter && Array.isArray(props.mapIndex.filtered_ids);
  const filteredSet = hasFilter ? new Set<number>(props.mapIndex.filtered_ids ?? []) : null;

  const entries = Object.entries(props.mapIndex.ids_by_status || {}) as Array<[StatusAnalisaRtrw, number[]]>;
  for (const [status, ids] of entries) {
    for (const id of ids) {
      const prevStatus = appliedStatusCache.get(id);
      const nextDim = filteredSet ? !filteredSet.has(id) : false;
      const prevDim = appliedDimCache.get(id);

      if (prevStatus !== status || prevDim !== nextDim) {
        mapInstance.setFeatureState(
          {
            source: 'martin-rtrw',
            sourceLayer: 'rtrw_jaringan_transportasi_ln',
            id,
          },
          {
            status,
            dim: nextDim,
          },
        );
        appliedStatusCache.set(id, status);
        appliedDimCache.set(id, nextDim);
      }
    }
  }
}

function syncLayerVisibility() {
  if (!mapInstance) return;
  const setVis = (layerId: string, visible: boolean) => {
    if (mapInstance.getLayer(layerId)) {
      mapInstance.setLayoutProperty(layerId, 'visibility', visible ? 'visible' : 'none');
    }
  };
  setVis('layer-rtrw-lines', props.layers.rtrw);
  setVis('layer-poros-lines', props.layers.poros);
  setVis('layer-kabupaten-casing', props.layers.kabupaten);
  setVis('layer-kabupaten-lines', props.layers.kabupaten);
  setVis('layer-kecamatan-line', props.layers.kecamatan);
  setVis('layer-desa-line', props.layers.desa);
}

function syncLayerOpacity() {
  if (!mapInstance) return;
  const op = props.layersOpacity;
  if (mapInstance.getLayer('layer-rtrw-lines')) {
    const val = op.rtrw ?? 0.92;
    mapInstance.setPaintProperty('layer-rtrw-lines', 'line-opacity', [
      'case',
      ['boolean', ['feature-state', 'dim'], false],
      Math.min(0.18, val * 0.2),
      val,
    ]);
  }
  if (mapInstance.getLayer('layer-poros-lines')) {
    mapInstance.setPaintProperty('layer-poros-lines', 'line-opacity', op.poros ?? 0.88);
  }
  if (mapInstance.getLayer('layer-kabupaten-casing')) {
    mapInstance.setPaintProperty(
      'layer-kabupaten-casing',
      'line-opacity',
      Math.max(0.1, (op.kabupaten ?? 0.95) * 0.88),
    );
  }
  if (mapInstance.getLayer('layer-kabupaten-lines')) {
    mapInstance.setPaintProperty('layer-kabupaten-lines', 'line-opacity', op.kabupaten ?? 0.95);
  }
  if (mapInstance.getLayer('layer-kecamatan-line')) {
    mapInstance.setPaintProperty('layer-kecamatan-line', 'line-opacity', op.kecamatan ?? 0.72);
  }
  if (mapInstance.getLayer('layer-desa-line')) {
    mapInstance.setPaintProperty('layer-desa-line', 'line-opacity', op.desa ?? 0.48);
  }
}

function syncColorModePaint() {
  if (!mapInstance || !mapInstance.getLayer('layer-rtrw-lines')) return;
  mapInstance.setPaintProperty('layer-rtrw-lines', 'line-color', buildRtrwPaintColor(props.colorModeType));
}

function syncSelectionOverlay(fitToSelection = false) {
  if (!mapInstance) return;
  const src = mapInstance.getSource('selection-highlight');
  if (src && typeof src.setData === 'function') {
    src.setData(buildHighlightGeoJson(props.selection));
  }

  if (!props.selection) {
    lastImmediateSelectKey = null;
    return;
  }

  const selKey = `${props.selection.kind}:${props.selection.id}`;
  const alreadyFittedImmediately = lastImmediateSelectKey === selKey;
  lastImmediateSelectKey = null;

  if (fitToSelection && !alreadyFittedImmediately && props.selection.bbox && props.selection.bbox.length === 4) {
    fitToBbox(props.selection.bbox);
  }
}

function applyBasemapSwitch(code: string) {
  if (!mapInstance) return;
  mapInstance.setStyle(buildMapStyle(code));
  mapInstance.once('styledata', () => {
    syncLayerVisibility();
    syncLayerOpacity();
    syncColorModePaint();
    syncRtrwFeatureStates(true);
    syncSelectionOverlay(false);
  });
}

function handleBasemapChange(code: string) {
  setBasemapChoice(code);
}

function isValidLngLatBounds(b: [number, number, number, number] | null | undefined): b is [number, number, number, number] {
  if (!b || b.length !== 4) return false;
  const [minX, minY, maxX, maxY] = b.map(Number);
  if (![minX, minY, maxX, maxY].every(Number.isFinite)) return false;
  if (minX > maxX || minY > maxY) return false;
  return minX >= -180 && maxX <= 180 && minY >= -85 && maxY <= 85;
}

function safeFitBounds(
  bbox: [number, number, number, number] | null | undefined,
  desiredPadding = 40,
  maxZoom = 16.5,
  duration = 450,
) {
  if (!mapInstance || !isValidLngLatBounds(bbox)) return;
  const el = mapContainerRef.value;
  const w = el?.clientWidth ?? 0;
  const h = el?.clientHeight ?? 0;
  if (w < 80 || h < 80) return;

  const [minX, minY, maxX, maxY] = bbox;
  if (Math.abs(maxX - minX) < 1e-6 && Math.abs(maxY - minY) < 1e-6) {
    mapInstance.easeTo({
      center: [minX, minY],
      zoom: Math.min(maxZoom, 15.5),
      duration,
    });
    return;
  }

  const maxAllowedPad = Math.max(4, Math.floor((Math.min(w, h) - 24) / 2));
  const safePadding = Math.max(4, Math.min(desiredPadding, maxAllowedPad));

  try {
    mapInstance.fitBounds(
      [
        [minX, minY],
        [maxX, maxY],
      ],
      { padding: safePadding, maxZoom, duration },
    );
  } catch {
    // Abaikan jika canvas terlalu sempit sementara saat pergantian panel
  }
}

function fitToBbox(bbox: [number, number, number, number] | null | undefined, maxZoom = 16.5) {
  safeFitBounds(bbox, 48, maxZoom, 240);
}

function fitToBojonegoro(duration = 280) {
  const flatBounds: [number, number, number, number] = [
    BOJONEGORO_BOUNDS[0][0],
    BOJONEGORO_BOUNDS[0][1],
    BOJONEGORO_BOUNDS[1][0],
    BOJONEGORO_BOUNDS[1][1],
  ];
  safeFitBounds(flatBounds, 28, 16, duration);
}

function fitToActiveBounds() {
  if (props.activeBounds && props.activeBounds.length === 4) {
    fitToBbox(props.activeBounds, 15.5);
  } else {
    fitToBojonegoro();
  }
}

function resetNorthBearing() {
  if (!mapInstance) return;
  mapInstance.easeTo({ bearing: 0, pitch: 0, duration: 280 });
}

function zoomBy(delta: number) {
  if (!mapInstance) return;
  const z = mapInstance.getZoom() ?? 10.5;
  mapInstance.easeTo({ zoom: Math.max(8, Math.min(18.5, z + delta)), duration: 220 });
}

function resizeAndRedrawNow() {
  const el = mapContainerRef.value;
  if (!el || !mapInstance) return;
  const w = Math.round(el.clientWidth);
  const h = Math.round(el.clientHeight);
  if (w < 40 || h < 40) return;
  if (w === lastObservedW && h === lastObservedH) return;
  lastObservedW = w;
  lastObservedH = h;
  mapInstance.resize();
  // MapLibre resize() reallocates/clears the WebGL canvas and defers _render()
  // to the next animation frame; calling redraw() synchronously paints inside
  // the same frame before browser composite so zero blank frames appear.
  if (isMapReady.value && typeof mapInstance.redraw === 'function') {
    mapInstance.redraw();
  }
}

function resizeMap() {
  resizeAndRedrawNow();
}

function getTooltipStyle() {
  if (!hoverTooltip.value) return {};
  const width = mapContainerRef.value?.clientWidth ?? 600;
  const maxLeft = Math.max(12, width - 272);
  return {
    left: `${Math.min(hoverTooltip.value.x + 14, maxLeft)}px`,
    top: `${Math.max(12, hoverTooltip.value.y - 12)}px`,
  };
}

function bindInteractiveEvents() {
  if (!mapInstance) return;

  const queryableLayers = ['layer-kabupaten-lines', 'layer-poros-lines', 'layer-rtrw-lines'];

  function queryAtPoint(point: { x: number; y: number }, layers: string[]) {
    const hitTolerance = 5;
    return mapInstance.queryRenderedFeatures(
      [
        [point.x - hitTolerance, point.y - hitTolerance],
        [point.x + hitTolerance, point.y + hitTolerance],
      ],
      { layers },
    );
  }

  function applyImmediateSelectionFeedback(kind: 'rtrw' | 'poros' | 'kabupaten', id: string | number, feat: any) {
    lastImmediateSelectKey = `${kind}:${id}`;
    const geom = feat?.geometry;
    if (geom) {
      const src = mapInstance.getSource('selection-highlight');
      if (src && typeof src.setData === 'function') {
        src.setData({
          type: 'FeatureCollection',
          features: [{ type: 'Feature', geometry: geom, properties: { role: 'primary' } }],
        });
      }
      const bbox = computeGeometryBbox(geom);
      if (bbox) {
        safeFitBounds(bbox, 48, 16.5, 220);
      }
    }
  }

  mapInstance.on('mousemove', (e: any) => {
    if (e?.lngLat) {
      cursorCoord.value = {
        lng: Number(e.lngLat.lng.toFixed(5)),
        lat: Number(e.lngLat.lat.toFixed(5)),
      };
    }

    const activeLayers = queryableLayers.filter((lid) => mapInstance.getLayer(lid));
    if (activeLayers.length === 0) return;

    const features = queryAtPoint(e.point, activeLayers);
    if (!features || features.length === 0) {
      mapInstance.getCanvas().style.cursor = '';
      hoverTooltip.value = null;
      return;
    }

    mapInstance.getCanvas().style.cursor = 'pointer';
    const top = features[0];
    const lid = top.layer?.id;
    const p = top.properties || {};

    if (lid === 'layer-kabupaten-lines') {
      hoverTooltip.value = {
        x: e.point.x,
        y: e.point.y,
        layerLabel: 'Jalan Kabupaten',
        title: String(p.nama_ruas || 'Ruas Jalan Kabupaten'),
        subtitle: `No. Ruas ${p.no_ruas || '-'} · ${p.nama_kecamatan || '-'}`,
        badge: p.klas_ruas ? String(p.klas_ruas) : 'Kabupaten',
      };
    } else if (lid === 'layer-poros-lines') {
      hoverTooltip.value = {
        x: e.point.x,
        y: e.point.y,
        layerLabel: 'Master Jalan Poros Desa',
        title: String(p.nama_ruas || 'Ruas Poros Desa'),
        subtitle: `Kode ${p.kode_ruas || '-'} · Desa ${p.nama_desa || p.desa || '-'}`,
        badge: String(p.kondisi || p.hierarki_jalan || 'Poros Desa'),
      };
    } else {
      const fState =
        top.id != null
          ? mapInstance.getFeatureState({
              source: 'martin-rtrw',
              sourceLayer: 'rtrw_jaringan_transportasi_ln',
              id: top.id,
            })
          : null;
      hoverTooltip.value = {
        x: e.point.x,
        y: e.point.y,
        layerLabel: 'Rencana Jalan RTRW',
        title: String(p.nama_ruas || p.namobj || `Segmen RTRW #${p.id ?? top.id ?? ''}`),
        subtitle: `${p.orde02 || '-'} · ${p.orde04 || '-'}`,
        badge: fState?.status ? getStatusAnalisaMeta(String(fState.status)).label : undefined,
      };
    }
  });

  mapInstance.on('mouseleave', () => {
    if (mapInstance) {
      mapInstance.getCanvas().style.cursor = '';
    }
    hoverTooltip.value = null;
  });

  mapInstance.on('click', (e: any) => {
    showMartinPopover.value = false;
    const activeLayers = ['layer-rtrw-lines', 'layer-poros-lines', 'layer-kabupaten-lines'].filter((lid) =>
      mapInstance.getLayer(lid),
    );
    if (activeLayers.length === 0) return;

    const features = queryAtPoint(e.point, activeLayers);
    if (!features || features.length === 0) return;

    const rtrwFeat = features.find((f: any) => f.layer?.id === 'layer-rtrw-lines');
    if (rtrwFeat) {
      const rid = Number(rtrwFeat.properties?.id ?? rtrwFeat.id);
      if (Number.isFinite(rid) && rid > 0) {
        applyImmediateSelectionFeedback('rtrw', rid, rtrwFeat);
        emit('select-rtrw', rid);
        return;
      }
    }

    const porosFeat = features.find((f: any) => f.layer?.id === 'layer-poros-lines');
    if (porosFeat && porosFeat.properties?.id) {
      const pid = String(porosFeat.properties.id);
      applyImmediateSelectionFeedback('poros', pid, porosFeat);
      emit('select-poros', pid);
      return;
    }

    const kabFeat = features.find((f: any) => f.layer?.id === 'layer-kabupaten-lines');
    if (kabFeat && kabFeat.properties?.id != null) {
      const kid = Number(kabFeat.properties.id);
      if (Number.isFinite(kid) && kid > 0) {
        applyImmediateSelectionFeedback('kabupaten', kid, kabFeat);
        emit('select-kabupaten', kid);
      }
    }
  });
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && showMartinPopover.value) {
    showMartinPopover.value = false;
  }
}

async function initMap() {
  if (!import.meta.client || !mapContainerRef.value || mapInstance) return;

  try {
    const [mod, workerMod] = await Promise.all([
      import('maplibre-gl'),
      import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
    ]);
    await import('maplibre-gl/dist/maplibre-gl.css');
    maplibregl = mod;

    if (workerMod?.default && typeof maplibregl.setWorkerUrl === 'function') {
      maplibregl.setWorkerUrl(workerMod.default);
    }

    await basemapStore.ensureLoaded();
    if (!mapContainerRef.value) return;

    mapInstance = new maplibregl.Map({
      container: mapContainerRef.value,
      style: buildMapStyle(currentBasemap.value),
      center: [111.82, -7.2],
      zoom: 10,
      minZoom: 8,
      maxZoom: 18.5,
      fadeDuration: 0,
      trackResize: false,
      canvasContextAttributes: {
        preserveDrawingBuffer: true,
        antialias: true,
      },
      attributionControl: false,
    });

    mapInstance.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
    mapInstance.addControl(new maplibregl.ScaleControl({ maxWidth: 110, unit: 'metric' }), 'bottom-right');

    const markCanvasReady = () => {
      if (isMapReady.value) return;
      if (canvasReadyFallbackTimer) {
        clearTimeout(canvasReadyFallbackTimer);
        canvasReadyFallbackTimer = null;
      }
      isMapReady.value = true;
      if (martinStatusState.value === 'loading') {
        martinStatusState.value = 'ready';
        emit('martin-status', { ok: true, errorCount: 0 });
      }
      const el = mapContainerRef.value;
      lastObservedW = Math.round(el?.clientWidth ?? 0);
      lastObservedH = Math.round(el?.clientHeight ?? 0);
      mapInstance?.resize();
      fitToBojonegoro(0);
      syncLayerVisibility();
      syncLayerOpacity();
      syncColorModePaint();
      syncRtrwFeatureStates(true);
      syncSelectionOverlay(false);
      if (typeof mapInstance?.redraw === 'function') {
        mapInstance.redraw();
      }
    };

    mapInstance.on('style.load', () => {
      markCanvasReady();
    });

    mapInstance.on('load', () => {
      markCanvasReady();
    });

    canvasReadyFallbackTimer = setTimeout(() => {
      markCanvasReady();
    }, 1800);

    mapInstance.on('zoom', () => {
      const z = mapInstance?.getZoom();
      if (typeof z === 'number') {
        currentZoom.value = Number(z.toFixed(1));
      }
    });

    mapInstance.on('rotate', () => {
      const b = mapInstance?.getBearing();
      if (typeof b === 'number') {
        currentBearing.value = Math.round(b);
      }
    });

    mapInstance.on('error', (e: any) => {
      const sid = String(e?.sourceId || '');
      if (sid.startsWith('martin-')) {
        tileErrorCount++;
        if (tileErrorCount >= 3) {
          martinStatusState.value = 'warning';
          martinWarningDetail.value =
            'Sebagian vector tile Martin belum merespons. Pastikan layanan tile server Martin (port 9090) aktif.';
          emit('martin-status', {
            ok: false,
            errorCount: tileErrorCount,
            message: martinWarningDetail.value,
          });
        }
      }
    });

    mapInstance.on('sourcedata', (e: any) => {
      if (e?.sourceId === 'martin-rtrw' && e?.isSourceLoaded) {
        if (martinStatusState.value !== 'ready') {
          martinStatusState.value = 'ready';
          martinWarningDetail.value = null;
          emit('martin-status', { ok: true, errorCount: 0 });
        }
        syncRtrwFeatureStates(false);
      }
    });

    bindInteractiveEvents();

    if (typeof ResizeObserver !== 'undefined' && mapContainerRef.value) {
      resizeObserver = new ResizeObserver(() => {
        resizeAndRedrawNow();
      });
      resizeObserver.observe(mapContainerRef.value);
    }
  } catch (err: any) {
    if (canvasReadyFallbackTimer) {
      clearTimeout(canvasReadyFallbackTimer);
      canvasReadyFallbackTimer = null;
    }
    isMapReady.value = true;
    martinStatusState.value = 'warning';
    martinWarningDetail.value = err?.message || 'Gagal memuat MapLibre GL JS.';
    emit('martin-status', {
      ok: false,
      errorCount: tileErrorCount + 1,
      message: martinWarningDetail.value,
    });
  }
}

watch(
  () => props.layers,
  () => syncLayerVisibility(),
  { deep: true },
);

watch(
  () => props.layersOpacity,
  () => syncLayerOpacity(),
  { deep: true },
);

watch(
  () => props.colorModeType,
  () => syncColorModePaint(),
);

watch(
  () => props.mapIndex,
  () => syncRtrwFeatureStates(false),
  { deep: true },
);

watch(
  () => props.selection,
  () => syncSelectionOverlay(true),
  { deep: true },
);

watch(
  () => props.activeBounds,
  (bounds) => {
    if (!mapInstance || !isMapReady.value || !bounds || bounds.length !== 4 || props.selection) return;
    fitToBbox(bounds, 15.5);
  },
);

watch(
  () => colorMode.value,
  () => {
    if (mapInstance && isMapReady.value) {
      applyBasemapSwitch(currentBasemap.value);
    }
  },
);

defineExpose({
  fitToBbox,
  fitToBojonegoro,
  fitToActiveBounds,
  resizeMap,
});

onMounted(() => {
  initMap();
  window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
  if (canvasReadyFallbackTimer) {
    clearTimeout(canvasReadyFallbackTimer);
    canvasReadyFallbackTimer = null;
  }
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});
</script>

<template>
  <div
    :class="[
      'relative overflow-hidden bg-slate-100 dark:bg-slate-950 select-none',
      fullBleedCanvas ? 'w-full h-full' : 'w-full h-[540px] lg:h-[620px] rounded-lg border border-gray-200 dark:border-gray-800',
    ]"
  >
    <!-- Canvas MapLibre -->
    <div ref="mapContainerRef" class="w-full h-full" />

    <!-- Top-Left: Subtle Martin & Filter Sync Telemetry Pill -->
    <div class="absolute top-3 left-3 z-10 flex flex-wrap items-center gap-2 pointer-events-none max-w-[calc(100%-6rem)]">
      <div class="relative pointer-events-auto">
        <button
          type="button"
          :aria-expanded="showMartinPopover"
          :aria-label="
            martinStatusState === 'warning'
              ? 'Peringatan koneksi vector tile Martin, klik untuk detail'
              : 'Status koneksi vector tile Martin'
          "
          :class="[
            'inline-flex items-center gap-2 rounded-lg border px-2.5 shadow-sm backdrop-blur-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
            'min-h-[44px] lg:min-h-[32px] lg:py-1',
            martinStatusState === 'warning'
              ? 'border-amber-300 bg-amber-50/95 text-amber-900 dark:border-amber-700 dark:bg-amber-950/95 dark:text-amber-100'
              : 'border-gray-200/90 bg-white/92 text-gray-800 dark:border-gray-700/90 dark:bg-[#0e1424]/92 dark:text-gray-200',
          ]"
          @click="showMartinPopover = !showMartinPopover"
        >
          <span
            :class="[
              'size-2 rounded-full shrink-0',
              martinStatusState === 'warning'
                ? 'bg-amber-600'
                : loadingIndex || martinStatusState === 'loading'
                  ? 'bg-blue-600 animate-spin'
                  : 'bg-emerald-600',
            ]"
          />
          <span class="text-xs font-medium whitespace-nowrap">
            {{
              martinStatusState === 'warning'
                ? 'Tile Server Terkendala'
                : loadingIndex
                  ? 'Sinkronisasi Spasial...'
                  : 'Vector Tile Aktif'
            }}
          </span>
          <UIcon
            v-if="martinStatusState === 'warning'"
            name="i-lucide-chevron-down"
            class="size-3.5 shrink-0"
          />
        </button>

        <div
          v-if="showMartinPopover"
          class="absolute left-0 top-full mt-1.5 w-72 rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-700 shadow-lg dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200"
        >
          <p class="font-semibold text-gray-900 dark:text-white">
            Telemetri Peta Vektor
          </p>
          <p class="mt-1 leading-relaxed text-gray-600 dark:text-gray-300">
            {{
              martinWarningDetail ||
              'Peta mengambil tile MVT langsung melalui proxy /martin dengan pewarnaan status spasial real-time.'
            }}
          </p>
          <div class="mt-2 flex items-center justify-between text-[11px] text-gray-600 dark:text-gray-400">
            <span class="tabular-nums">Zoom {{ currentZoom }}x</span>
            <button
              type="button"
              class="min-h-[44px] lg:min-h-[28px] px-2 font-medium text-blue-700 hover:underline dark:text-blue-400 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded"
              @click="showMartinPopover = false"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>

      <!-- Chip Pilihan Aktif Singkat di Desktop -->
      <div
        v-if="selection"
        class="pointer-events-auto hidden sm:inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-blue-50/95 px-2.5 py-1 text-xs font-medium text-blue-950 shadow-sm backdrop-blur-md dark:border-blue-700 dark:bg-blue-950/92 dark:text-blue-100 max-w-xs"
      >
        <UIcon name="i-lucide-crosshair" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
        <span class="truncate">{{ selection.title }}</span>
        <button
          type="button"
          aria-label="Hapus sorotan segmen terpilih"
          class="min-h-[44px] min-w-[44px] lg:min-h-[24px] lg:min-w-[24px] -my-1 -mr-1 flex items-center justify-center rounded text-blue-800 hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="emit('clear-selection')"
        >
          <UIcon name="i-lucide-x" class="size-3.5" />
        </button>
      </div>
    </div>

    <!-- Top-Right Navigation & Basemap Controls -->
    <div class="absolute top-3 right-3 z-10 flex flex-col items-end gap-2">
      <!-- Basemap Switcher -->
      <BasemapSwitcher
        :model-value="currentBasemap"
        engine="maplibre"
        variant="medium"
        placement="top-right"
        @update:model-value="handleBasemapChange"
      />

      <!-- Vertical GIS Navigation Controls -->
      <div
        class="flex flex-col rounded-lg border border-gray-200/90 bg-white/95 shadow-sm backdrop-blur-md overflow-hidden dark:border-gray-700/90 dark:bg-[#0e1424]/95 divide-y divide-gray-200 dark:divide-gray-800"
      >
        <button
          type="button"
          aria-label="Perbesar Peta"
          title="Perbesar (+)"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="zoomBy(1)"
        >
          <UIcon name="i-lucide-plus" class="size-4" />
        </button>
        <button
          type="button"
          aria-label="Perkecil Peta"
          title="Perkecil (-)"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="zoomBy(-1)"
        >
          <UIcon name="i-lucide-minus" class="size-4" />
        </button>
        <button
          type="button"
          aria-label="Fokus Wilayah Kabupaten Bojonegoro"
          title="Reset ke Seluruh Bojonegoro"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="fitToBojonegoro()"
        >
          <UIcon name="i-lucide-maximize-2" class="size-4" />
        </button>
        <button
          v-if="activeBounds"
          type="button"
          aria-label="Zoom ke Cakupan Filter Aktif"
          title="Zoom ke Hasil Filter Wilayah"
          class="size-11 lg:size-8 flex items-center justify-center text-blue-700 bg-blue-50/70 hover:bg-blue-100 dark:text-blue-400 dark:bg-blue-950/40 dark:hover:bg-blue-900/60 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="fitToActiveBounds"
        >
          <UIcon name="i-lucide-scan-search" class="size-4" />
        </button>
        <button
          v-if="currentBearing !== 0"
          type="button"
          aria-label="Reset Arah Utara"
          title="Reset Arah Utara"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="resetNorthBearing"
        >
          <UIcon name="i-lucide-compass" class="size-4" />
        </button>
      </div>
    </div>

    <!-- Interactive Map Hover Tooltip -->
    <div
      v-if="hoverTooltip"
      :style="getTooltipStyle()"
      class="pointer-events-none absolute z-20 w-64 rounded-lg border border-gray-200 bg-white/95 p-2.5 text-xs shadow-lg backdrop-blur-md dark:border-gray-700 dark:bg-[#0e1424]/95"
    >
      <div class="flex items-center justify-between gap-2 text-[11px] font-medium text-gray-600 dark:text-gray-400">
        <span>{{ hoverTooltip.layerLabel }}</span>
        <span
          v-if="hoverTooltip.badge"
          class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
        >
          {{ hoverTooltip.badge }}
        </span>
      </div>
      <p class="mt-1 font-semibold text-gray-900 dark:text-white leading-snug">
        {{ hoverTooltip.title }}
      </p>
      <p class="mt-0.5 text-[11px] text-gray-600 dark:text-gray-300">
        {{ hoverTooltip.subtitle }}
      </p>
    </div>

    <!-- Bottom-Left Live WGS84 Coordinate & Zoom Telemetry HUD -->
    <div
      class="pointer-events-none absolute left-3 bottom-[4.75rem] lg:bottom-2.5 z-10 hidden sm:flex items-center gap-2 rounded-md border border-gray-200/90 bg-white/90 px-2.5 py-1 text-[11px] font-medium text-gray-700 shadow-xs backdrop-blur-md tabular-nums dark:border-gray-700/90 dark:bg-[#0e1424]/90 dark:text-gray-200"
    >
      <span>Zoom {{ currentZoom }}x</span>
      <span v-if="cursorCoord" class="text-gray-500 dark:text-gray-400">|</span>
      <span v-if="cursorCoord">
        {{ cursorCoord.lat.toFixed(5) }}, {{ cursorCoord.lng.toFixed(5) }}
      </span>
    </div>
  </div>
</template>

<style scoped>
:deep(.maplibregl-ctrl-bottom-right) {
  margin-bottom: 4.75rem;
}

@media (min-width: 1024px) {
  :deep(.maplibregl-ctrl-bottom-right) {
    margin-bottom: 0.5rem;
  }
}
</style>

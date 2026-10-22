<script setup lang="ts">
import 'ol/ol.css';
import type { RekapRuasItem, RekapSegmenItem } from '~/types/rekap-jalan';

interface Props {
  modelValue?: boolean;
  open?: boolean;
  item: (RekapRuasItem | RekapSegmenItem | any) | null;
  title?: string;
  desaName?: string;
  kecamatanName?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: undefined,
  open: undefined,
  item: null,
  title: 'Preview Spasial Garis Jalan',
  desaName: undefined,
  kecamatanName: undefined,
});

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void;
  (e: 'update:open', val: boolean): void;
}>();

const api = useRekapJalanApi();
const toast = useToast();

// DOM Refs
const mapContainer = ref<HTMLDivElement | null>(null);
const popupEl = ref<HTMLDivElement | null>(null);

// OpenLayers references
let mapInstance: any = null;
let baseTileLayer: any = null;
let vectorLayer: any = null;
let vectorSourceInstance: any = null;
let markerLayer: any = null;
let markerSourceInstance: any = null;
let popupOverlay: any = null;
let OLModules: any = null;
let resizeObserver: ResizeObserver | null = null;
let resizeRaf: number | null = null;
let hasFittedInitialExtent = false;

// Modal Dialog & Layout State
const isFullscreen = ref(false);
const isRightPanelOpen = ref(true);
const activeRightTab = ref<'segmen' | 'detail'>('segmen');
const segmentSearchQuery = ref('');
const showLineLabels = ref(false);
const showStartEndMarkers = ref(true);

const isOpen = computed({
  get: () => {
    if (props.open !== undefined) return props.open;
    if (props.modelValue !== undefined) return props.modelValue;
    return false;
  },
  set: (val: boolean) => {
    emit('update:modelValue', val);
    emit('update:open', val);
  },
});

// Map State
const loadingData = ref(false);
const fetchError = ref<string | null>(null);
const fetchedSegments = ref<RekapSegmenItem[]>([]);
const activeSegmentId = ref<string | null>(null);
const selectedFeatureInfo = ref<any | null>(null);
const activeBasemap = ref<'osm' | 'esri' | 'carto'>('osm');
const mouseCoordinates = ref<{ lat: number; lon: number } | null>(null);

// ─── Formatters & Value Helpers ──────────────────────────────────────────────

function formatMeters(meters?: number): string {
  if (meters === undefined || meters === null || isNaN(meters)) return '-';
  if (meters >= 1000) {
    return `${(meters / 1000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} km (${Math.round(meters).toLocaleString('id-ID')} m)`;
  }
  return `${Math.round(meters).toLocaleString('id-ID')} m`;
}

function formatMeterShort(meters?: number): string {
  if (meters === undefined || meters === null || isNaN(meters)) return '-';
  if (meters >= 1000) {
    return `${(meters / 1000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} km`;
  }
  return `${Math.round(meters).toLocaleString('id-ID')} m`;
}

function formatArea(panjang?: number, lebar?: number): string {
  if (!panjang || !lebar) return '-';
  const area = Math.round(panjang * lebar);
  return `${area.toLocaleString('id-ID')} m²`;
}

function getKondisiColor(kondisi?: string): string {
  const k = (kondisi || '').toLowerCase();
  if (k.includes('rusak berat')) return '#ef4444'; // Red-Rose
  if (k.includes('rusak ringan') || k.includes('rusak')) return '#f97316'; // Orange
  if (k.includes('sedang')) return '#f59e0b'; // Amber
  if (k.includes('baik')) return '#10b981'; // Emerald
  return '#2563eb'; // Royal Blue default
}

function getKondisiBadgeClass(kondisi?: string): string {
  const k = (kondisi || '').toLowerCase();
  if (k.includes('rusak berat')) {
    return 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border border-rose-200 dark:border-rose-800/40';
  }
  if (k.includes('rusak ringan') || k.includes('rusak')) {
    return 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-400 border border-orange-200 dark:border-orange-800/40';
  }
  if (k.includes('sedang')) {
    return 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 dark:border-amber-800/40';
  }
  if (k.includes('baik')) {
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40';
  }
  return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700';
}

// ─── Enriched Item Resolvers ─────────────────────────────────────────────────

const resolvedInfo = computed(() => {
  const raw = (props.item || {}) as any;
  const namaRuas = raw.nama_ruas || raw.keterangan || props.title || 'Ruas Jalan Poros Desa';
  const kodeRuas = raw.kode_ruas && raw.kode_ruas !== '-' ? String(raw.kode_ruas) : null;
  const isPoros = raw.is_poros !== undefined ? raw.is_poros : (kodeRuas !== null);
  const kecamatan = raw.nama_kecamatan || raw.kecamatan || props.kecamatanName || '-';
  const desa = raw.nama_desa || raw.desa || props.desaName || '-';

  const panjangMaster = Number(raw.panjang_master || raw.panjang || 0);
  const panjangDibangun = Number(raw.panjang_dibangun || (raw.geom ? raw.panjang : 0));
  const panjangTersisa = raw.panjang_tersisa !== undefined
    ? Number(raw.panjang_tersisa)
    : Math.max(0, panjangMaster - panjangDibangun);
  const progress = raw.progress !== undefined
    ? Number(raw.progress)
    : (panjangMaster > 0 ? Math.min(100, Math.round((panjangDibangun / panjangMaster) * 100)) : 100);

  const statusAset = raw.status_aset || (raw.atribut?.status_aset) || null;
  const statusVerifikasi = raw.status_verifikasi || (raw.atribut?.status_verifikasi) || null;
  const statusJalan = raw.atribut?.status_jalan || (isPoros ? 'Jalan Poros Desa' : 'Jalan Desa Non-Ruas');
  const jenisPerkerasan = raw.atribut?.jenis_perkerasan || null;
  const tahunPembangunan = raw.tahun_pembangunan || raw.tahun || null;
  const sumberDana = raw.sumber_dana || null;
  const kondisi = raw.kondisi || null;
  const statusKondisi = raw.status_kondisi || null;
  const lebar = raw.lebar ? Number(raw.lebar) : null;
  const estimasiLuas = lebar && panjangMaster ? Math.round(panjangMaster * lebar) : null;

  return {
    raw,
    namaRuas,
    kodeRuas,
    isPoros,
    kecamatan,
    desa,
    panjangMaster,
    panjangDibangun,
    panjangTersisa,
    progress,
    statusAset,
    statusVerifikasi,
    statusJalan,
    jenisPerkerasan,
    tahunPembangunan,
    sumberDana,
    kondisi,
    statusKondisi,
    lebar,
    estimasiLuas,
  };
});

// Condition Statistics Breakdown
const conditionBreakdown = computed(() => {
  const totals: Record<string, { meters: number; count: number; color: string; label: string; bgClass: string }> = {
    baik: { meters: 0, count: 0, color: '#10b981', label: 'Baik', bgClass: 'bg-emerald-500' },
    sedang: { meters: 0, count: 0, color: '#f59e0b', label: 'Sedang', bgClass: 'bg-amber-500' },
    rusak_ringan: { meters: 0, count: 0, color: '#f97316', label: 'Rusak Ringan', bgClass: 'bg-orange-500' },
    rusak_berat: { meters: 0, count: 0, color: '#ef4444', label: 'Rusak Berat', bgClass: 'bg-rose-500' },
  };

  let totalMeters = 0;
  for (const seg of fetchedSegments.value) {
    const k = (seg.kondisi || '').toLowerCase();
    const len = Number(seg.panjang) || 0;
    totalMeters += len;

    if (k.includes('rusak berat')) {
      totals.rusak_berat.meters += len;
      totals.rusak_berat.count++;
    } else if (k.includes('rusak ringan') || k.includes('rusak')) {
      totals.rusak_ringan.meters += len;
      totals.rusak_ringan.count++;
    } else if (k.includes('sedang')) {
      totals.sedang.meters += len;
      totals.sedang.count++;
    } else if (k.includes('baik')) {
      totals.baik.meters += len;
      totals.baik.count++;
    }
  }

  return {
    totals,
    totalMeters: totalMeters || 1,
  };
});

// Filtered Segment List for Search
const filteredSegmentsList = computed(() => {
  const query = segmentSearchQuery.value.trim().toLowerCase();
  if (!query) return fetchedSegments.value;
  return fetchedSegments.value.filter((seg, idx) => {
    const matchIndex = String(idx + 1).includes(query);
    const matchKondisi = (seg.kondisi || '').toLowerCase().includes(query);
    const matchDana = (seg.sumber_dana || '').toLowerCase().includes(query);
    const matchTahun = String(seg.tahun || '').includes(query);
    const matchKet = (seg.keterangan || '').toLowerCase().includes(query);
    return matchIndex || matchKondisi || matchDana || matchTahun || matchKet;
  });
});

// Spatial Coordinates Extractors for Selected Feature
const spatialCoordinatesInfo = computed(() => {
  const info = selectedFeatureInfo.value;
  if (!info || !info.geom) return null;

  let coords: number[][] = [];
  const geom = info.geom;
  if (geom.type === 'LineString') {
    coords = geom.coordinates || [];
  } else if (geom.type === 'MultiLineString' && geom.coordinates) {
    coords = (geom.coordinates as number[][][]).flat();
  }

  if (coords.length === 0) return null;

  const startCoord = coords[0];
  const endCoord = coords[coords.length - 1];

  // In GeoJSON coords are [lon, lat]
  return {
    count: coords.length,
    startLat: Number(startCoord[1]).toFixed(6),
    startLon: Number(startCoord[0]).toFixed(6),
    endLat: Number(endCoord[1]).toFixed(6),
    endLon: Number(endCoord[0]).toFixed(6),
    rawGeoJson: JSON.stringify(geom, null, 2),
  };
});

// ─── OpenLayers Initialization ───────────────────────────────────────────────

async function loadOLModules() {
  if (OLModules) return OLModules;

  const [
    MapModule,
    ViewModule,
    TileLayerModule,
    VectorLayerModule,
    OSMModule,
    XYZModule,
    VectorSourceModule,
    GeoJSONModule,
    styleModule,
    projModule,
    OverlayModule,
    FeatureModule,
    PointModule,
  ] = await Promise.all([
    import('ol/Map'),
    import('ol/View'),
    import('ol/layer/Tile'),
    import('ol/layer/Vector'),
    import('ol/source/OSM'),
    import('ol/source/XYZ'),
    import('ol/source/Vector'),
    import('ol/format/GeoJSON'),
    import('ol/style'),
    import('ol/proj'),
    import('ol/Overlay'),
    import('ol/Feature'),
    import('ol/geom/Point'),
  ]);

  OLModules = {
    Map: MapModule.default,
    View: ViewModule.default,
    TileLayer: TileLayerModule.default,
    VectorLayer: VectorLayerModule.default,
    OSM: OSMModule.default,
    XYZ: XYZModule.default,
    VectorSource: VectorSourceModule.default,
    GeoJSON: GeoJSONModule.default,
    Style: styleModule.Style,
    Stroke: styleModule.Stroke,
    Circle: styleModule.Circle,
    Fill: styleModule.Fill,
    Text: styleModule.Text,
    fromLonLat: projModule.fromLonLat,
    toLonLat: projModule.toLonLat,
    Overlay: OverlayModule.default,
    Feature: FeatureModule.default,
    Point: PointModule.default,
  };

  return OLModules;
}

function getBasemapSource(type: 'osm' | 'esri' | 'carto') {
  if (!OLModules) return null;
  if (type === 'esri') {
    return new OLModules.XYZ({
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      maxZoom: 19,
      crossOrigin: 'anonymous',
    });
  }
  if (type === 'carto') {
    return new OLModules.XYZ({
      url: 'https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
      maxZoom: 19,
      crossOrigin: 'anonymous',
    });
  }
  return new OLModules.OSM({
    crossOrigin: 'anonymous',
  });
}

function switchBasemap(type: 'osm' | 'esri' | 'carto') {
  activeBasemap.value = type;
  if (baseTileLayer && OLModules) {
    baseTileLayer.setSource(getBasemapSource(type));
  }
}

function updateMarkerPoints(featureOrGeom: any) {
  if (!markerSourceInstance || !OLModules) return;
  markerSourceInstance.clear();
  if (!showStartEndMarkers.value || !featureOrGeom) return;

  const geom = featureOrGeom.getGeometry ? featureOrGeom.getGeometry() : featureOrGeom;
  if (!geom) return;

  let firstCoord: number[] | null = null;
  let lastCoord: number[] | null = null;

  const type = geom.getType ? geom.getType() : geom.type;
  if (type === 'LineString') {
    const coords = geom.getCoordinates ? geom.getCoordinates() : geom.coordinates;
    if (coords && coords.length >= 2) {
      firstCoord = coords[0];
      lastCoord = coords[coords.length - 1];
    }
  } else if (type === 'MultiLineString') {
    const lines = geom.getCoordinates ? geom.getCoordinates() : geom.coordinates;
    if (lines && lines.length > 0) {
      const firstLine = lines[0];
      const lastLine = lines[lines.length - 1];
      if (firstLine.length > 0 && lastLine.length > 0) {
        firstCoord = firstLine[0];
        lastCoord = lastLine[lastLine.length - 1];
      }
    }
  }

  if (firstCoord && lastCoord) {
    // Start Point (A)
    const startFeature = new OLModules.Feature({
      geometry: new OLModules.Point(firstCoord),
      label: 'A',
      type: 'start',
    });
    // End Point (B)
    const endFeature = new OLModules.Feature({
      geometry: new OLModules.Point(lastCoord),
      label: 'B',
      type: 'end',
    });
    markerSourceInstance.addFeatures([startFeature, endFeature]);
  }
}

async function initMap() {
  if (!import.meta.client || !mapContainer.value) return;

  const ol = await loadOLModules();

  if (mapInstance) {
    mapInstance.setTarget(null);
    mapInstance = null;
  }

  baseTileLayer = new ol.TileLayer({
    source: getBasemapSource(activeBasemap.value),
  });

  vectorSourceInstance = new ol.VectorSource();
  markerSourceInstance = new ol.VectorSource();

  // Vector Layer for Roads
  vectorLayer = new ol.VectorLayer({
    source: vectorSourceInstance,
    style: (feature: any) => {
      const kondisi = feature.get('kondisi');
      const panjang = feature.get('panjang');
      const isSelected = activeSegmentId.value && feature.get('id') === activeSegmentId.value;
      const strokeColor = getKondisiColor(kondisi);

      const styles = [
        // Outer halo / background outline
        new ol.Style({
          stroke: new ol.Stroke({
            color: isSelected ? '#ffffff' : (activeBasemap.value === 'esri' ? '#00000088' : '#00000033'),
            width: isSelected ? 10 : 6,
          }),
        }),
        // Inner colored line
        new ol.Style({
          stroke: new ol.Stroke({
            color: strokeColor,
            width: isSelected ? 5.5 : 3.5,
          }),
        }),
      ];

      // Optional text labels on line
      if (showLineLabels.value) {
        styles.push(
          new ol.Style({
            text: new ol.Text({
              text: `${formatMeterShort(panjang)} • ${(kondisi || '').toUpperCase()}`,
              font: 'bold 10px sans-serif',
              placement: 'line',
              repeat: 400,
              fill: new ol.Fill({ color: '#ffffff' }),
              stroke: new ol.Stroke({ color: '#0f172a', width: 3 }),
            }),
          })
        );
      }

      return styles;
    },
  });

  // Vector Layer for Start & End Markers (A / B)
  markerLayer = new ol.VectorLayer({
    source: markerSourceInstance,
    style: (feature: any) => {
      const isStart = feature.get('type') === 'start';
      const color = isStart ? '#10b981' : '#f43f5e';
      const label = feature.get('label') || (isStart ? 'A' : 'B');

      return new ol.Style({
        image: new ol.Circle({
          radius: 9,
          fill: new ol.Fill({ color }),
          stroke: new ol.Stroke({ color: '#ffffff', width: 2.5 }),
        }),
        text: new ol.Text({
          text: label,
          fill: new ol.Fill({ color: '#ffffff' }),
          font: 'bold 10px sans-serif',
          textAlign: 'center',
          textBaseline: 'middle',
        }),
      });
    },
  });

  popupOverlay = new ol.Overlay({
    element: popupEl.value,
    autoPan: {
      animation: {
        duration: 250,
      },
    },
  });

  mapInstance = new ol.Map({
    target: mapContainer.value,
    layers: [baseTileLayer, vectorLayer, markerLayer],
    view: new ol.View({
      center: ol.fromLonLat([111.88, -7.15]), // Default Bojonegoro
      zoom: 12,
    }),
    controls: [],
    overlays: [popupOverlay],
  });

  // Attach ResizeObserver to automatically update OpenLayers size when modal expands
  setupResizeObserver();

  // Mouse move: update coordinates & cursor pointer
  mapInstance.on('pointermove', (evt: any) => {
    if (evt.dragging) return;
    const pixel = mapInstance.getEventPixel(evt.originalEvent);
    const hit = mapInstance.hasFeatureAtPixel(pixel, {
      layerFilter: (l: any) => l === vectorLayer,
    });
    mapInstance.getTargetElement().style.cursor = hit ? 'pointer' : '';

    if (evt.coordinate && ol.toLonLat) {
      const [lon, lat] = ol.toLonLat(evt.coordinate);
      mouseCoordinates.value = {
        lat: Number(lat.toFixed(6)),
        lon: Number(lon.toFixed(6)),
      };
    }
  });

  // Map click handler to select segment
  mapInstance.on('click', (evt: any) => {
    const feature = mapInstance.forEachFeatureAtPixel(
      evt.pixel,
      (f: any) => f,
      { layerFilter: (l: any) => l === vectorLayer }
    );

    if (feature) {
      selectFeature(feature, evt.coordinate);
    } else {
      closePopup();
    }
  });

  // Load and render features
  await loadAndRenderGeometry();

  // Multi-pass updateSize to ensure tiles render immediately during and after modal animation
  requestAnimationFrame(() => triggerMapResize());
  setTimeout(() => triggerMapResize(), 80);
  setTimeout(() => triggerMapResize(), 200);
  setTimeout(() => triggerMapResize(), 450);
}

function setupResizeObserver() {
  if (!mapContainer.value || typeof ResizeObserver === 'undefined') return;

  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }

  resizeObserver = new ResizeObserver((entries) => {
    if (resizeRaf) cancelAnimationFrame(resizeRaf);
    resizeRaf = requestAnimationFrame(() => {
      if (!mapInstance) return;
      mapInstance.updateSize();

      for (const entry of entries) {
        if (entry.contentRect.width > 50 && entry.contentRect.height > 50) {
          if (!hasFittedInitialExtent && vectorSourceInstance && vectorSourceInstance.getFeatures().length > 0) {
            fitViewToFeatures();
          }
        }
      }
    });
  });

  resizeObserver.observe(mapContainer.value);
}

function triggerMapResize(fit = false) {
  if (!mapInstance) return;
  mapInstance.updateSize();
  if (fit || (!hasFittedInitialExtent && vectorSourceInstance && vectorSourceInstance.getFeatures().length > 0)) {
    fitViewToFeatures();
  }
}

function selectFeature(feature: any, clickCoord?: number[]) {
  const id = feature.get('id');
  activeSegmentId.value = id;

  selectedFeatureInfo.value = {
    id,
    tahun: feature.get('tahun'),
    panjang: feature.get('panjang'),
    lebar: feature.get('lebar'),
    kondisi: feature.get('kondisi'),
    sumber_dana: feature.get('sumber_dana'),
    status_kondisi: feature.get('status_kondisi') || 'Eksisting',
    keterangan: feature.get('keterangan'),
    geom: feature.get('rawGeom') || null,
  };

  // Position popup at midpoint of geometry
  const geom = feature.getGeometry();
  if (geom) {
    if (clickCoord) {
      popupOverlay.setPosition(clickCoord);
    } else {
      const extent = geom.getExtent();
      const centerCoord = [
        (extent[0] + extent[2]) / 2,
        (extent[1] + extent[3]) / 2,
      ];
      popupOverlay.setPosition(centerCoord);
    }
    updateMarkerPoints(geom);
  }

  vectorLayer.changed();
}

function closePopup() {
  selectedFeatureInfo.value = null;
  activeSegmentId.value = null;
  if (popupOverlay) popupOverlay.setPosition(undefined);
  if (markerSourceInstance) markerSourceInstance.clear();
  if (vectorLayer) vectorLayer.changed();
}

async function loadAndRenderGeometry() {
  if (!props.item || !vectorSourceInstance || !OLModules) return;

  vectorSourceInstance.clear();
  closePopup();
  fetchedSegments.value = [];
  fetchError.value = null;

  const rawItem = props.item as any;

  // Case 1: Geometri sudah tersedia langsung di objek (misal klik dari Level 4 segmen / Non-Ruas)
  if (rawItem.geom && rawItem.geom.coordinates) {
    const geojsonFormat = new OLModules.GeoJSON({ featureProjection: 'EPSG:3857' });
    const feature = geojsonFormat.readFeature({
      type: 'Feature',
      geometry: rawItem.geom,
      properties: {
        id: rawItem.id,
        tahun: rawItem.tahun || rawItem.tahun_pembangunan,
        panjang: rawItem.panjang || rawItem.panjang_master,
        lebar: rawItem.lebar,
        kondisi: rawItem.kondisi,
        sumber_dana: rawItem.sumber_dana,
        status_kondisi: rawItem.status_kondisi || 'Eksisting',
        keterangan: rawItem.keterangan || rawItem.nama_ruas,
        rawGeom: rawItem.geom,
      },
    });

    vectorSourceInstance.addFeature(feature);
    fetchedSegments.value = [rawItem as RekapSegmenItem];
    fitViewToFeatures();
    triggerMapResize(true);
    return;
  }

  // Case 2: Geometri belum tersedia di props (Ruas Poros Desa Level 3)
  // Perlu otomatis fetch daftar segmen dari API /rekap/ruas/{id}/segmen
  loadingData.value = true;
  try {
    const segments = await api.fetchDetailSegmen(rawItem.id);
    fetchedSegments.value = segments || [];

    const geojsonFormat = new OLModules.GeoJSON({ featureProjection: 'EPSG:3857' });
    let countAdded = 0;

    for (const seg of fetchedSegments.value) {
      if (seg.geom && seg.geom.coordinates && seg.geom.coordinates.length > 0) {
        try {
          const feature = geojsonFormat.readFeature({
            type: 'Feature',
            geometry: seg.geom,
            properties: {
              id: seg.id,
              tahun: seg.tahun,
              panjang: seg.panjang,
              lebar: seg.lebar,
              kondisi: seg.kondisi,
              sumber_dana: seg.sumber_dana,
              status_kondisi: seg.status_kondisi || 'Eksisting',
              keterangan: seg.keterangan,
              rawGeom: seg.geom,
            },
          });
          vectorSourceInstance.addFeature(feature);
          countAdded++;
        } catch (e) {
          console.warn('Gagal parse GeoJSON segmen:', seg.id, e);
        }
      }
    }

    if (countAdded > 0) {
      fitViewToFeatures();
      triggerMapResize(true);
    }
  } catch (err: any) {
    console.error('Gagal mengambil segmen spasial ruas:', err);
    fetchError.value = err?.message || 'Server API eksternal tidak mengembalikan koordinat geometri untuk ruas ini.';
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Geometri Ruas',
      description: fetchError.value,
      color: 'error',
    });
  } finally {
    loadingData.value = false;
  }
}

function fitViewToFeatures() {
  if (!mapInstance || !vectorSourceInstance) return;

  mapInstance.updateSize();
  const extent = vectorSourceInstance.getExtent();
  if (extent && isFinite(extent[0]) && extent[0] !== Infinity && extent[0] !== -Infinity) {
    mapInstance.getView().fit(extent, {
      padding: [80, 80, 80, 80],
      maxZoom: 17,
      duration: 350,
    });
    hasFittedInitialExtent = true;
  }
}

function zoomToSegment(seg: RekapSegmenItem) {
  if (!mapInstance || !vectorSourceInstance || !OLModules || !seg.geom) return;

  activeSegmentId.value = seg.id;
  selectedFeatureInfo.value = {
    id: seg.id,
    tahun: seg.tahun,
    panjang: seg.panjang,
    lebar: seg.lebar,
    kondisi: seg.kondisi,
    sumber_dana: seg.sumber_dana,
    status_kondisi: seg.status_kondisi || 'Eksisting',
    keterangan: seg.keterangan,
    geom: seg.geom,
  };

  const geojsonFormat = new OLModules.GeoJSON({ featureProjection: 'EPSG:3857' });
  const feature = geojsonFormat.readFeature({
    type: 'Feature',
    geometry: seg.geom,
  });

  const geom = feature.getGeometry();
  if (geom) {
    const extent = geom.getExtent();
    mapInstance.getView().fit(extent, {
      padding: [100, 100, 100, 100],
      maxZoom: 18,
      duration: 400,
    });

    // Place popup at coordinate center
    const centerCoord = [
      (extent[0] + extent[2]) / 2,
      (extent[1] + extent[3]) / 2,
    ];
    popupOverlay.setPosition(centerCoord);
    updateMarkerPoints(geom);
  }

  vectorLayer.changed();
}

function handleZoomIn() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || 12) + 1, duration: 200 });
}

function handleZoomOut() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || 12) - 1, duration: 200 });
}

function toggleLineLabels() {
  showLineLabels.value = !showLineLabels.value;
  if (vectorLayer) vectorLayer.changed();
}

function toggleStartEndMarkers() {
  showStartEndMarkers.value = !showStartEndMarkers.value;
  if (!showStartEndMarkers.value && markerSourceInstance) {
    markerSourceInstance.clear();
  } else if (selectedFeatureInfo.value) {
    const feat = vectorSourceInstance?.getFeatures().find((f: any) => f.get('id') === activeSegmentId.value);
    if (feat) updateMarkerPoints(feat.getGeometry());
  }
}

function toggleFullscreen() {
  isFullscreen.value = !isFullscreen.value;
  nextTick(() => {
    setTimeout(() => {
      triggerMapResize(true);
    }, 120);
  });
}

function toggleRightPanel() {
  isRightPanelOpen.value = !isRightPanelOpen.value;
  nextTick(() => {
    setTimeout(() => {
      triggerMapResize(false);
    }, 120);
  });
}

async function copyToClipboard(text: string, label = 'Tersalin ke clipboard') {
  try {
    await navigator.clipboard.writeText(text);
    toast.add({
      icon: 'i-lucide-check',
      title: 'Berhasil',
      description: label,
      color: 'success',
    });
  } catch {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menyalin',
      description: 'Browser tidak mengizinkan akses clipboard.',
      color: 'error',
    });
  }
}

function openInGoogleMaps(lat: number | string, lon: number | string) {
  window.open(`https://www.google.com/maps?q=${lat},${lon}`, '_blank', 'noopener,noreferrer');
}

// Watch modal state
watch(
  isOpen,
  (open) => {
    if (open) {
      hasFittedInitialExtent = false;
      nextTick(() => {
        setTimeout(() => {
          initMap();
        }, 100);
      });
    } else {
      closePopup();
      if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
      }
      if (resizeRaf) {
        cancelAnimationFrame(resizeRaf);
        resizeRaf = null;
      }
      if (mapInstance) {
        mapInstance.setTarget(null);
        mapInstance = null;
      }
    }
  }
);

// Watch item change if already open
watch(
  () => props.item,
  () => {
    if (isOpen.value && mapInstance) {
      loadAndRenderGeometry();
    }
  }
);

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (resizeRaf) {
    cancelAnimationFrame(resizeRaf);
    resizeRaf = null;
  }
  if (mapInstance) {
    mapInstance.setTarget(null);
    mapInstance = null;
  }
});
</script>

<template>
  <UModal
    v-model:open="isOpen"
    :title="resolvedInfo.namaRuas"
    :description="`Peta Spasial Jalan ${resolvedInfo.namaRuas}`"
    :close="false"
    :ui="{
      content: isFullscreen
        ? 'fixed inset-0 w-screen h-screen max-w-none max-h-none rounded-none bg-white dark:bg-[#0b0f19] border-0 flex flex-col overflow-hidden m-0 p-0 shadow-none z-50'
        : 'sm:max-w-6xl md:max-w-7xl w-[calc(100vw-2rem)] h-[88vh] max-h-[850px] bg-white dark:bg-[#0b0f19] border border-gray-200/80 dark:border-white/10 rounded-2xl shadow-2xl flex flex-col overflow-hidden p-0',
      body: 'p-0 sm:p-0 flex-1 flex flex-col min-h-0 overflow-hidden',
    }"
  >
    <template #content>
      <div
        :class="[
          'relative flex flex-col md:flex-row flex-1 w-full h-full min-h-0 overflow-hidden bg-white dark:bg-[#0b0f19]',
          isFullscreen ? 'h-screen w-screen' : 'h-full',
        ]"
      >
        <!-- ─── Left / Center: Map Canvas Viewport ──────────────────────────────── -->
        <div class="flex-1 relative w-full h-full min-h-[360px] bg-gray-100 dark:bg-gray-900 overflow-hidden">
          <!-- OL Target Container -->
          <div ref="mapContainer" class="absolute inset-0 w-full h-full outline-none" />

          <!-- Loading Overlay -->
          <div
            v-if="loadingData"
            class="absolute inset-0 flex flex-col items-center justify-center bg-white/75 dark:bg-black/70 backdrop-blur-xs z-30 pointer-events-none"
          >
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800 flex flex-col items-center gap-2">
              <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-blue-600 dark:text-blue-400" />
              <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                Mengambil geometri spasial segmen jalan...
              </p>
              <span class="text-[11px] text-gray-500">Menghubungkan ke API Saggaserv</span>
            </div>
          </div>

          <!-- Empty Geometry Alert -->
          <div
            v-else-if="!loadingData && fetchedSegments.length === 0 && !(item as any)?.geom"
            class="absolute inset-0 flex flex-col items-center justify-center bg-white/85 dark:bg-gray-900/85 backdrop-blur-xs p-6 text-center z-10"
          >
            <div class="size-14 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 flex items-center justify-center mb-3 border border-amber-200 dark:border-amber-800/40 shadow-xs">
              <UIcon name="i-lucide-map-pin-off" class="size-7" />
            </div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
              Belum Ada Data Geometri Spasial
            </h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mt-1 leading-relaxed">
              Ruas ini telah terdaftar dalam sistem rekapitulasi, namun belum memiliki data koordinat segmen fisik LineString dari hasil survei lapangan.
            </p>
            <div class="mt-4 flex items-center gap-2">
              <UButton
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-rotate-cw"
                label="Coba Muat Ulang"
                @click="loadAndRenderGeometry"
              />
              <UButton
                size="xs"
                color="primary"
                variant="soft"
                icon="i-lucide-info"
                label="Lihat Informasi Atribut"
                @click="activeRightTab = 'detail'; isRightPanelOpen = true"
              />
            </div>
          </div>

          <!-- Floating Route Info Badge (Top-Left) -->
          <div class="absolute top-3.5 left-3.5 z-20 flex items-center gap-2.5 max-w-[calc(100%-140px)] sm:max-w-sm md:max-w-md bg-white/95 dark:bg-gray-900/95 backdrop-blur-md px-3 py-2 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-md">
            <div
              :class="[
                'size-8 rounded-lg flex items-center justify-center shrink-0 text-xs font-semibold',
                resolvedInfo.isPoros
                  ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                  : 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
              ]"
            >
              <UIcon name="i-lucide-route" class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 truncate">
                <span class="text-xs font-bold text-gray-900 dark:text-white truncate">
                  {{ resolvedInfo.namaRuas }}
                </span>
                <span
                  :class="[
                    'text-[10px] font-semibold px-1.5 py-0.2 rounded shrink-0',
                    resolvedInfo.isPoros
                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                      : 'bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300',
                  ]"
                >
                  {{ resolvedInfo.isPoros ? 'Poros' : 'Non-Ruas' }}
                </span>
                <span
                  v-if="resolvedInfo.kodeRuas"
                  class="font-mono text-[10px] font-medium px-1.5 py-0.2 rounded bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/40 shrink-0"
                >
                  {{ resolvedInfo.kodeRuas }}
                </span>
              </div>
              <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                Kec. {{ resolvedInfo.kecamatan }} › Ds. {{ resolvedInfo.desa }} • {{ formatMeterShort(resolvedInfo.panjangMaster) }} ({{ resolvedInfo.progress }}%)
              </div>
            </div>
          </div>

          <!-- Map Floating Action Toolbar (Top-Left, below Route Badge) -->
          <div class="absolute top-[68px] left-3.5 z-20 flex flex-col gap-1.5 shadow-md rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur-xs p-1 border border-gray-200/80 dark:border-gray-800">
            <button
              type="button"
              class="size-8 rounded-lg flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
              title="Perbesar (Zoom In)"
              @click="handleZoomIn"
            >
              <UIcon name="i-lucide-plus" class="size-4" />
            </button>

            <button
              type="button"
              class="size-8 rounded-lg flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
              title="Perkecil (Zoom Out)"
              @click="handleZoomOut"
            >
              <UIcon name="i-lucide-minus" class="size-4" />
            </button>

            <div class="h-px bg-gray-200 dark:bg-gray-800 my-0.5" />

            <button
              type="button"
              class="size-8 rounded-lg flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
              title="Fokuskan ke seluruh ruas (Fit Extent)"
              @click="fitViewToFeatures"
            >
              <UIcon name="i-lucide-maximize" class="size-4" />
            </button>

            <button
              type="button"
              :class="[
                'size-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer',
                showLineLabels
                  ? 'bg-blue-500 text-white shadow-xs'
                  : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800',
              ]"
              title="Tampilkan/Sembunyikan Label Teks Segmen pada Garis"
              @click="toggleLineLabels"
            >
              <UIcon name="i-lucide-tag" class="size-4" />
            </button>

            <button
              type="button"
              :class="[
                'size-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer',
                showStartEndMarkers
                  ? 'bg-blue-500 text-white shadow-xs'
                  : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800',
              ]"
              title="Tampilkan/Sembunyikan Pin Titik Awal (A) & Akhir (B)"
              @click="toggleStartEndMarkers"
            >
              <UIcon name="i-lucide-map-pin" class="size-4" />
            </button>
          </div>

          <!-- Floating Map Header Controls (Top-Right of Map) -->
          <div class="absolute top-3.5 right-3.5 z-20 flex items-center gap-1.5">
            <!-- Basemap Switcher Toolbar -->
            <div class="hidden sm:flex items-center bg-white/95 dark:bg-gray-900/95 backdrop-blur-md p-0.5 rounded-xl text-xs border border-gray-200/80 dark:border-gray-800 shadow-md">
              <button
                type="button"
                :class="[
                  'px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer flex items-center gap-1',
                  activeBasemap === 'osm'
                    ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                ]"
                title="Peta Jalan Standar OpenStreetMap"
                @click="switchBasemap('osm')"
              >
                <UIcon name="i-lucide-map" class="size-3.5" />
                <span>Jalan</span>
              </button>
              <button
                type="button"
                :class="[
                  'px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer flex items-center gap-1',
                  activeBasemap === 'esri'
                    ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                ]"
                title="Foto Udara Satelit Esri Resolusi Tinggi"
                @click="switchBasemap('esri')"
              >
                <UIcon name="i-lucide-globe" class="size-3.5" />
                <span>Satelit</span>
              </button>
              <button
                type="button"
                :class="[
                  'px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer flex items-center gap-1',
                  activeBasemap === 'carto'
                    ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                ]"
                title="Peta Voyager / Minimalis Terang"
                @click="switchBasemap('carto')"
              >
                <UIcon name="i-lucide-compass" class="size-3.5" />
                <span>Voyager</span>
              </button>
            </div>

            <!-- Open Panel Button (when right panel is collapsed) -->
            <button
              v-if="!isRightPanelOpen"
              type="button"
              class="h-8 px-2.5 rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 shadow-md flex items-center gap-1.5 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors"
              title="Buka panel rincian"
              @click="toggleRightPanel"
            >
              <UIcon name="i-lucide-panel-right-open" class="size-4 text-blue-500" />
              <span>Panel</span>
            </button>

            <!-- Fullscreen Toggle -->
            <button
              type="button"
              class="size-8 rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 text-gray-700 dark:text-gray-200 shadow-md flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors"
              :title="isFullscreen ? 'Perkecil modal' : 'Layar penuh'"
              @click="toggleFullscreen"
            >
              <UIcon :name="isFullscreen ? 'i-lucide-minimize-2' : 'i-lucide-maximize-2'" class="size-4" />
            </button>

            <!-- Close Modal Button (always visible when panel is collapsed or on mobile) -->
            <button
              type="button"
              :class="[
                'size-8 rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 text-gray-700 dark:text-gray-200 shadow-md flex items-center justify-center hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:text-rose-600 dark:hover:text-rose-400 cursor-pointer transition-colors',
                isRightPanelOpen ? 'md:hidden' : 'flex',
              ]"
              title="Tutup Modal (Esc)"
              @click="isOpen = false"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>


            <!-- Bottom Left Info Bar: Cursor Coordinates & Features Count -->
            <div class="absolute bottom-3 left-3 z-10 flex items-center gap-2">
              <div class="px-2.5 py-1 rounded-lg bg-white/95 dark:bg-gray-900/95 backdrop-blur-xs border border-gray-200/80 dark:border-gray-800 text-[11px] font-mono text-gray-700 dark:text-gray-300 shadow-sm flex items-center gap-2">
                <span class="flex items-center gap-1">
                  <UIcon name="i-lucide-crosshair" class="size-3 text-blue-500" />
                  <span v-if="mouseCoordinates">
                    {{ mouseCoordinates.lat }}°, {{ mouseCoordinates.lon }}°
                  </span>
                  <span v-else class="text-gray-400">
                    Arahkan kursor ke peta
                  </span>
                </span>

                <span class="text-gray-300 dark:text-gray-700">|</span>

                <span class="text-gray-500">
                  {{ fetchedSegments.length }} Segmen
                </span>
              </div>
            </div>

            <!-- Bottom Right Condition Color Legend -->
            <div class="absolute bottom-3 right-3 z-10 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xs border border-gray-200/80 dark:border-gray-800 rounded-xl p-2.5 shadow-md text-[11px] hidden sm:block">
              <span class="font-semibold text-gray-700 dark:text-gray-300 block mb-1 text-[10px] uppercase tracking-wider">
                Kondisi Jalan
              </span>
              <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-gray-600 dark:text-gray-300">
                <div class="flex items-center gap-1.5">
                  <span class="size-2.5 rounded-full bg-emerald-500 shrink-0" />
                  <span>Baik</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2.5 rounded-full bg-amber-500 shrink-0" />
                  <span>Sedang</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2.5 rounded-full bg-orange-500 shrink-0" />
                  <span>Rusak Ringan</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2.5 rounded-full bg-rose-500 shrink-0" />
                  <span>Rusak Berat</span>
                </div>
              </div>
            </div>

            <!-- Popup Bubble Overlay on Map -->
            <div
              ref="popupEl"
              class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-2xl p-3.5 w-72 text-xs z-30 relative -translate-x-1/2 -translate-y-full mb-3"
              :class="{ hidden: !selectedFeatureInfo }"
            >
              <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white">
                  <span
                    class="size-2.5 rounded-full shrink-0"
                    :style="{ backgroundColor: getKondisiColor(selectedFeatureInfo?.kondisi) }"
                  />
                  <span>Detail Segmen Terpilih</span>
                </div>
                <button
                  type="button"
                  class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  title="Tutup popup"
                  @click="closePopup"
                >
                  <UIcon name="i-lucide-x" class="size-4" />
                </button>
              </div>

              <div v-if="selectedFeatureInfo" class="space-y-1.5 text-[11px]">
                <div class="flex justify-between items-center">
                  <span class="text-gray-500">Panjang:</span>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white">
                    {{ formatMeters(selectedFeatureInfo.panjang) }}
                  </span>
                </div>

                <div v-if="selectedFeatureInfo.lebar" class="flex justify-between items-center">
                  <span class="text-gray-500">Lebar & Luas:</span>
                  <span class="font-mono text-gray-900 dark:text-white">
                    {{ selectedFeatureInfo.lebar }} m ({{ formatArea(selectedFeatureInfo.panjang, selectedFeatureInfo.lebar) }})
                  </span>
                </div>

                <div class="flex justify-between items-center">
                  <span class="text-gray-500">Kondisi Fisik:</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-semibold capitalize', getKondisiBadgeClass(selectedFeatureInfo.kondisi)]">
                    {{ selectedFeatureInfo.kondisi }}
                  </span>
                </div>

                <div v-if="selectedFeatureInfo.sumber_dana" class="flex justify-between items-center">
                  <span class="text-gray-500">Sumber Dana:</span>
                  <span class="font-medium text-blue-600 dark:text-blue-400">
                    {{ selectedFeatureInfo.sumber_dana }}
                  </span>
                </div>

                <div v-if="selectedFeatureInfo.tahun" class="flex justify-between items-center">
                  <span class="text-gray-500">Tahun Anggaran:</span>
                  <span class="font-mono text-gray-900 dark:text-white">
                    T.A. {{ selectedFeatureInfo.tahun }}
                  </span>
                </div>

                <div v-if="selectedFeatureInfo.status_kondisi" class="flex justify-between items-center">
                  <span class="text-gray-500">Status Kondisi:</span>
                  <span class="text-gray-700 dark:text-gray-300">
                    {{ selectedFeatureInfo.status_kondisi }}
                  </span>
                </div>

                <p v-if="selectedFeatureInfo.keterangan" class="text-gray-500 italic text-[10px] mt-1 p-1.5 rounded bg-gray-50 dark:bg-gray-800/60 line-clamp-2">
                  "{{ selectedFeatureInfo.keterangan }}"
                </p>

                <!-- Action Button inside popup -->
                <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                  <button
                    type="button"
                    class="text-blue-600 dark:text-blue-400 font-semibold text-[11px] hover:underline flex items-center gap-1 cursor-pointer"
                    @click="activeRightTab = 'detail'; isRightPanelOpen = true"
                  >
                    <span>Detail Lengkap</span>
                    <UIcon name="i-lucide-arrow-right" class="size-3" />
                  </button>

                  <button
                    v-if="spatialCoordinatesInfo"
                    type="button"
                    class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 text-[11px] flex items-center gap-1 cursor-pointer"
                    title="Buka titik awal di Google Maps"
                    @click="openInGoogleMaps(spatialCoordinatesInfo.startLat, spatialCoordinatesInfo.startLon)"
                  >
                    <UIcon name="i-lucide-navigation" class="size-3" />
                    <span>Maps</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- ─── Right Side Panel: Segments List & Full Technical Attributes ─────── -->
          <div
            v-if="isRightPanelOpen"
            class="w-full md:w-88 lg:w-96 border-t md:border-t-0 md:border-s border-gray-200/80 dark:border-white/[0.08] bg-gray-50/70 dark:bg-[#070b14] flex flex-col max-h-[380px] md:max-h-none shrink-0 overflow-hidden"
          >
            <!-- Panel Header with Tab Navigation -->
            <div class="p-2 border-b border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center justify-between shrink-0 gap-2">
              <div class="flex items-center bg-gray-100 dark:bg-gray-800/80 p-0.5 rounded-lg text-xs flex-1">
                <button
                  type="button"
                  :class="[
                    'flex-1 py-1 px-2 rounded-md font-semibold transition-colors cursor-pointer flex items-center justify-center gap-1.5',
                    activeRightTab === 'segmen'
                      ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                      : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                  ]"
                  @click="activeRightTab = 'segmen'"
                >
                  <UIcon name="i-lucide-layers" class="size-3.5 text-blue-500" />
                  <span>Segmen ({{ fetchedSegments.length }})</span>
                </button>

                <button
                  type="button"
                  :class="[
                    'flex-1 py-1 px-2 rounded-md font-semibold transition-colors cursor-pointer flex items-center justify-center gap-1.5',
                    activeRightTab === 'detail'
                      ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                      : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                  ]"
                  @click="activeRightTab = 'detail'"
                >
                  <UIcon name="i-lucide-file-text" class="size-3.5 text-emerald-500" />
                  <span>Detail Ruas</span>
                </button>
              </div>

              <!-- Action Buttons (Collapse & Close) -->
              <div class="flex items-center gap-1 shrink-0">
                <button
                  type="button"
                  class="size-7 rounded-lg text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-center cursor-pointer transition-colors"
                  title="Sembunyikan panel ini"
                  @click="toggleRightPanel"
                >
                  <UIcon name="i-lucide-panel-right-close" class="size-4" />
                </button>
                <button
                  type="button"
                  class="size-7 rounded-lg text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center justify-center cursor-pointer transition-colors"
                  title="Tutup Modal (Esc)"
                  @click="isOpen = false"
                >
                  <UIcon name="i-lucide-x" class="size-4" />
                </button>
              </div>
            </div>

            <!-- ─── TAB 1: DAFTAR SEGMEN FISIK ─────────────────────────────────── -->
            <div v-if="activeRightTab === 'segmen'" class="flex-1 flex flex-col min-h-0 overflow-hidden">
              <!-- Condition Breakdown Bar -->
              <div v-if="fetchedSegments.length > 0" class="p-3 bg-white dark:bg-[#0b0f19] border-b border-gray-200/70 dark:border-white/[0.06] space-y-2 shrink-0">
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-semibold text-gray-700 dark:text-gray-300">
                    Proporsi Kondisi Fisik
                  </span>
                  <span class="font-mono text-gray-500">
                    {{ formatMeterShort(conditionBreakdown.totalMeters) }}
                  </span>
                </div>

                <!-- Segmented Multi-color Bar -->
                <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden flex">
                  <div
                    v-if="conditionBreakdown.totals.baik.meters > 0"
                    :style="{ width: `${(conditionBreakdown.totals.baik.meters / conditionBreakdown.totalMeters) * 100}%` }"
                    class="bg-emerald-500 h-full"
                    :title="`Baik: ${formatMeterShort(conditionBreakdown.totals.baik.meters)}`"
                  />
                  <div
                    v-if="conditionBreakdown.totals.sedang.meters > 0"
                    :style="{ width: `${(conditionBreakdown.totals.sedang.meters / conditionBreakdown.totalMeters) * 100}%` }"
                    class="bg-amber-500 h-full"
                    :title="`Sedang: ${formatMeterShort(conditionBreakdown.totals.sedang.meters)}`"
                  />
                  <div
                    v-if="conditionBreakdown.totals.rusak_ringan.meters > 0"
                    :style="{ width: `${(conditionBreakdown.totals.rusak_ringan.meters / conditionBreakdown.totalMeters) * 100}%` }"
                    class="bg-orange-500 h-full"
                    :title="`Rusak Ringan: ${formatMeterShort(conditionBreakdown.totals.rusak_ringan.meters)}`"
                  />
                  <div
                    v-if="conditionBreakdown.totals.rusak_berat.meters > 0"
                    :style="{ width: `${(conditionBreakdown.totals.rusak_berat.meters / conditionBreakdown.totalMeters) * 100}%` }"
                    class="bg-rose-500 h-full"
                    :title="`Rusak Berat: ${formatMeterShort(conditionBreakdown.totals.rusak_berat.meters)}`"
                  />
                </div>

                <!-- Mini Breakdown Stats -->
                <div class="grid grid-cols-2 gap-1 text-[10px]">
                  <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                      <span class="size-1.5 rounded-full bg-emerald-500" />
                      <span>Baik:</span>
                    </span>
                    <span class="font-mono font-medium text-gray-900 dark:text-white">
                      {{ formatMeterShort(conditionBreakdown.totals.baik.meters) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                      <span class="size-1.5 rounded-full bg-amber-500" />
                      <span>Sedang:</span>
                    </span>
                    <span class="font-mono font-medium text-gray-900 dark:text-white">
                      {{ formatMeterShort(conditionBreakdown.totals.sedang.meters) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                      <span class="size-1.5 rounded-full bg-orange-500" />
                      <span>R. Ringan:</span>
                    </span>
                    <span class="font-mono font-medium text-gray-900 dark:text-white">
                      {{ formatMeterShort(conditionBreakdown.totals.rusak_ringan.meters) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between text-gray-600 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                      <span class="size-1.5 rounded-full bg-rose-500" />
                      <span>R. Berat:</span>
                    </span>
                    <span class="font-mono font-medium text-gray-900 dark:text-white">
                      {{ formatMeterShort(conditionBreakdown.totals.rusak_berat.meters) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Search Segments (if multiple) -->
              <div v-if="fetchedSegments.length > 2" class="p-2 border-b border-gray-200/70 dark:border-white/[0.06] bg-gray-50/50 dark:bg-gray-900/40">
                <UInput
                  v-model="segmentSearchQuery"
                  icon="i-lucide-search"
                  placeholder="Cari segmen / tahun / sumber dana..."
                  size="xs"
                  class="w-full"
                />
              </div>

              <!-- Scrollable Segments Cards List -->
              <div class="flex-1 overflow-y-auto p-2.5 space-y-2">
                <div
                  v-if="loadingData"
                  class="py-12 text-center text-xs text-gray-400 flex flex-col items-center justify-center gap-2"
                >
                  <UIcon name="i-lucide-loader-2" class="size-5 animate-spin text-blue-500" />
                  <span>Memuat data segmen dari API...</span>
                </div>

                <div
                  v-else-if="fetchedSegments.length === 0"
                  class="py-12 text-center text-xs text-gray-400 italic px-4"
                >
                  Tidak ada segmen fisik bergeometri terdaftar.
                </div>

                <div
                  v-else-if="filteredSegmentsList.length === 0"
                  class="py-8 text-center text-xs text-gray-400 italic"
                >
                  Tidak ditemukan segmen dengan kata kunci pencarian.
                </div>

                <template v-else>
                  <div
                    v-for="(seg, idx) in filteredSegmentsList"
                    :key="seg.id"
                    :class="[
                      'p-3 rounded-xl border transition-all cursor-pointer text-xs space-y-2 relative group',
                      activeSegmentId === seg.id
                        ? 'bg-blue-50/90 dark:bg-blue-950/40 border-blue-400 dark:border-blue-700 shadow-sm ring-1 ring-blue-400/50'
                        : 'bg-white dark:bg-gray-900 border-gray-200/80 dark:border-white/[0.06] hover:border-blue-300 dark:hover:border-blue-800/80 hover:shadow-xs',
                    ]"
                    @click="zoomToSegment(seg)"
                  >
                    <!-- Card Top: Number, Tahun & Sumber Dana -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2 min-w-0">
                        <span
                          class="size-2.5 rounded-full shrink-0"
                          :style="{ backgroundColor: getKondisiColor(seg.kondisi) }"
                        />
                        <span class="font-semibold text-gray-900 dark:text-white truncate">
                          Segmen #{{ idx + 1 }}
                        </span>
                      </div>

                      <div class="flex items-center gap-1.5 shrink-0">
                        <span
                          v-if="seg.tahun"
                          class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300"
                        >
                          T.A. {{ seg.tahun }}
                        </span>
                        <span
                          v-if="seg.sumber_dana"
                          class="font-medium text-[10px] px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/30"
                        >
                          {{ seg.sumber_dana }}
                        </span>
                      </div>
                    </div>

                    <!-- Card Dimensions: Panjang, Lebar, Luas -->
                    <div class="grid grid-cols-2 gap-2 text-[11px] p-2 rounded-lg bg-gray-50/80 dark:bg-gray-800/50">
                      <div>
                        <span class="text-gray-400 block text-[10px]">Panjang Fisik</span>
                        <span class="font-mono font-semibold text-gray-900 dark:text-white">
                          {{ formatMeters(seg.panjang) }}
                        </span>
                      </div>
                      <div>
                        <span class="text-gray-400 block text-[10px]">Lebar & Luas</span>
                        <span class="font-mono text-gray-800 dark:text-gray-200">
                          {{ seg.lebar ? `${seg.lebar} m (${formatArea(seg.panjang, seg.lebar)})` : '-' }}
                        </span>
                      </div>
                    </div>

                    <!-- Card Bottom: Kondisi & Quick Action Buttons -->
                    <div class="flex items-center justify-between pt-1 text-[11px]">
                      <span :class="['px-2 py-0.5 rounded text-[10px] font-semibold capitalize', getKondisiBadgeClass(seg.kondisi)]">
                        {{ seg.kondisi }}
                      </span>

                      <!-- Quick Buttons -->
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          class="p-1 rounded text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                          title="Fokuskan tampilan peta ke segmen ini"
                          @click.stop="zoomToSegment(seg)"
                        >
                          <UIcon name="i-lucide-crosshair" class="size-3.5" />
                        </button>
                        <button
                          v-if="seg.geom"
                          type="button"
                          class="p-1 rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                          title="Salin GeoJSON segmen ini"
                          @click.stop="copyToClipboard(JSON.stringify(seg.geom), 'GeoJSON segmen tersalin!')"
                        >
                          <UIcon name="i-lucide-copy" class="size-3.5" />
                        </button>
                      </div>
                    </div>

                    <!-- Keterangan if available -->
                    <p v-if="seg.keterangan" class="text-gray-500 italic text-[10px] border-t border-gray-100 dark:border-gray-800/60 pt-1 line-clamp-2">
                      "{{ seg.keterangan }}"
                    </p>
                  </div>
                </template>
              </div>
            </div>

            <!-- ─── TAB 2: DETAIL INFORMASI & ATRIBUT LENGKAP ─────────────────── -->
            <div v-else-if="activeRightTab === 'detail'" class="flex-1 overflow-y-auto p-3 space-y-3.5 text-xs">
              <!-- Section 1: Identitas & Administrasi -->
              <div class="p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-white/[0.06] space-y-2">
                <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white text-xs border-b border-gray-100 dark:border-gray-800 pb-1.5">
                  <UIcon name="i-lucide-building-2" class="size-4 text-blue-500" />
                  <span>Identitas & Wilayah Administrasi</span>
                </div>

                <div class="space-y-1.5 text-[11px]">
                  <div class="flex justify-between">
                    <span class="text-gray-400">Nama Ruas:</span>
                    <span class="font-semibold text-gray-900 dark:text-white text-right max-w-[200px]">
                      {{ resolvedInfo.namaRuas }}
                    </span>
                  </div>

                  <div class="flex justify-between">
                    <span class="text-gray-400">Kode Ruas:</span>
                    <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                      {{ resolvedInfo.kodeRuas || 'Segmen Mandiri' }}
                    </span>
                  </div>

                  <div class="flex justify-between">
                    <span class="text-gray-400">Kategori:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                      {{ resolvedInfo.isPoros ? 'Jalan Poros Desa' : 'Jalan Desa Non-Ruas' }}
                    </span>
                  </div>

                  <div class="flex justify-between">
                    <span class="text-gray-400">Kecamatan:</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                      {{ resolvedInfo.kecamatan }}
                    </span>
                  </div>

                  <div class="flex justify-between">
                    <span class="text-gray-400">Desa:</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                      {{ resolvedInfo.desa }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Section 2: Dimensi & Capaian Pembangunan -->
              <div class="p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-white/[0.06] space-y-2.5">
                <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white text-xs border-b border-gray-100 dark:border-gray-800 pb-1.5">
                  <UIcon name="i-lucide-ruler" class="size-4 text-emerald-500" />
                  <span>Dimensi & Progress Pembangunan</span>
                </div>

                <!-- Progress Bar -->
                <div class="space-y-1">
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500 font-medium">Persentase Capaian:</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                      {{ resolvedInfo.progress }}%
                    </span>
                  </div>
                  <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                    <div
                      :style="{ width: `${resolvedInfo.progress}%` }"
                      :class="[
                        'h-full rounded-full transition-all',
                        resolvedInfo.progress >= 100 ? 'bg-emerald-500' : 'bg-blue-500',
                      ]"
                    />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
                  <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60">
                    <span class="text-gray-400 text-[10px] block">Panjang Master</span>
                    <span class="font-mono font-semibold text-gray-900 dark:text-white">
                      {{ formatMeters(resolvedInfo.panjangMaster) }}
                    </span>
                  </div>

                  <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60">
                    <span class="text-gray-400 text-[10px] block">Sudah Terbangun</span>
                    <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                      {{ formatMeters(resolvedInfo.panjangDibangun) }}
                    </span>
                  </div>

                  <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60">
                    <span class="text-gray-400 text-[10px] block">Sisa Belum Dibangun</span>
                    <span class="font-mono text-gray-700 dark:text-gray-300">
                      {{ formatMeters(resolvedInfo.panjangTersisa) }}
                    </span>
                  </div>

                  <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60">
                    <span class="text-gray-400 text-[10px] block">Jumlah Segmen</span>
                    <span class="font-mono font-semibold text-gray-900 dark:text-white">
                      {{ fetchedSegments.length }} Segmen Fisik
                    </span>
                  </div>
                </div>

                <div v-if="resolvedInfo.lebar" class="flex justify-between items-center text-[11px] pt-1 border-t border-gray-100 dark:border-gray-800">
                  <span class="text-gray-400">Estimasi Luas Perkerasan:</span>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white">
                    {{ formatArea(resolvedInfo.panjangMaster, resolvedInfo.lebar) }}
                  </span>
                </div>
              </div>

              <!-- Section 3: Status Jalan Poros -->
              <div class="p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-white/[0.06] space-y-2">
                <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white text-xs border-b border-gray-100 dark:border-gray-800 pb-1.5">
                  <UIcon name="i-lucide-hammer" class="size-4 text-amber-500" />
                  <span>Status Jalan Poros</span>
                </div>

                <div class="space-y-1.5 text-[11px]">
                  <div v-if="resolvedInfo.jenisPerkerasan" class="flex justify-between">
                    <span class="text-gray-400">Jenis Perkerasan:</span>
                    <span class="font-semibold text-gray-900 dark:text-white">
                      {{ resolvedInfo.jenisPerkerasan }}
                    </span>
                  </div>

                  <div v-if="resolvedInfo.statusJalan" class="flex justify-between">
                    <span class="text-gray-400">Status Jalan:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                      {{ resolvedInfo.statusJalan }}
                    </span>
                  </div>

                  <div v-if="resolvedInfo.statusAset" class="flex justify-between">
                    <span class="text-gray-400">Status Aset:</span>
                    <span class="font-medium text-purple-600 dark:text-purple-400">
                      {{ resolvedInfo.statusAset }}
                    </span>
                  </div>

                  <div v-if="resolvedInfo.statusVerifikasi" class="flex justify-between">
                    <span class="text-gray-400">Status Verifikasi:</span>
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400 capitalize">
                      {{ resolvedInfo.statusVerifikasi }}
                    </span>
                  </div>

                  <div v-if="resolvedInfo.sumberDana" class="flex justify-between">
                    <span class="text-gray-400">Sumber Dana:</span>
                    <span class="font-medium text-blue-600 dark:text-blue-400">
                      {{ resolvedInfo.sumberDana }}
                    </span>
                  </div>

                  <div v-if="resolvedInfo.tahunPembangunan" class="flex justify-between">
                    <span class="text-gray-400">Tahun Pembangunan:</span>
                    <span class="font-mono text-gray-900 dark:text-white">
                      T.A. {{ resolvedInfo.tahunPembangunan }}
                    </span>
                  </div>

                  <div v-if="resolvedInfo.kondisi" class="flex justify-between items-center">
                    <span class="text-gray-400">Kondisi Dominan:</span>
                    <span :class="['px-2 py-0.5 rounded text-[10px] font-semibold capitalize', getKondisiBadgeClass(resolvedInfo.kondisi)]">
                      {{ resolvedInfo.kondisi }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Section 4: Data Koordinat Spasial Segmen Terpilih -->
              <div v-if="spatialCoordinatesInfo" class="p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-white/[0.06] space-y-2">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                  <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white text-xs">
                    <UIcon name="i-lucide-map-pin" class="size-4 text-rose-500" />
                    <span>Koordinat Titik Segmen</span>
                  </div>
                  <span class="text-[10px] text-gray-400 font-mono">
                    {{ spatialCoordinatesInfo.count }} Titik Vertex
                  </span>
                </div>

                <div class="space-y-1.5 text-[11px]">
                  <div class="flex items-center justify-between">
                    <span class="text-gray-400 flex items-center gap-1">
                      <span class="size-2 rounded-full bg-emerald-500 shrink-0" />
                      <span>Titik Awal (A):</span>
                    </span>
                    <div class="flex items-center gap-1">
                      <span class="font-mono text-[10px] text-gray-800 dark:text-gray-200">
                        {{ spatialCoordinatesInfo.startLat }}, {{ spatialCoordinatesInfo.startLon }}
                      </span>
                      <button
                        type="button"
                        class="p-1 rounded text-blue-500 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                        title="Buka titik awal di Google Maps"
                        @click="openInGoogleMaps(spatialCoordinatesInfo.startLat, spatialCoordinatesInfo.startLon)"
                      >
                        <UIcon name="i-lucide-external-link" class="size-3" />
                      </button>
                    </div>
                  </div>

                  <div class="flex items-center justify-between">
                    <span class="text-gray-400 flex items-center gap-1">
                      <span class="size-2 rounded-full bg-rose-500 shrink-0" />
                      <span>Titik Akhir (B):</span>
                    </span>
                    <div class="flex items-center gap-1">
                      <span class="font-mono text-[10px] text-gray-800 dark:text-gray-200">
                        {{ spatialCoordinatesInfo.endLat }}, {{ spatialCoordinatesInfo.endLon }}
                      </span>
                      <button
                        type="button"
                        class="p-1 rounded text-blue-500 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                        title="Buka titik akhir di Google Maps"
                        @click="openInGoogleMaps(spatialCoordinatesInfo.endLat, spatialCoordinatesInfo.endLon)"
                      >
                        <UIcon name="i-lucide-external-link" class="size-3" />
                      </button>
                    </div>
                  </div>

                  <!-- Copy GeoJSON Action -->
                  <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <button
                      type="button"
                      class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-[11px] font-medium flex items-center gap-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer"
                      @click="copyToClipboard(spatialCoordinatesInfo.rawGeoJson, 'GeoJSON berhasil disalin!')"
                    >
                      <UIcon name="i-lucide-copy" class="size-3.5" />
                      <span>Salin Raw GeoJSON</span>
                    </button>

                    <button
                      type="button"
                      class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-[11px] font-medium flex items-center gap-1.5 hover:bg-blue-100 dark:hover:bg-blue-900/50 cursor-pointer"
                      @click="copyToClipboard(`${spatialCoordinatesInfo.startLat}, ${spatialCoordinatesInfo.startLon}`, 'Koordinat titik awal tersalin!')"
                    >
                      <UIcon name="i-lucide-crosshair" class="size-3.5" />
                      <span>Salin Titik Awal</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Section 5: Catatan Lapangan / Keterangan -->
              <div v-if="(item as any)?.keterangan" class="p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/70 dark:border-amber-800/30 text-[11px] space-y-1">
                <span class="font-semibold text-amber-900 dark:text-amber-400 flex items-center gap-1 text-[11px]">
                  <UIcon name="i-lucide-info" class="size-3.5" />
                  <span>Catatan Khusus Lapangan:</span>
                </span>
                <p class="text-amber-800 dark:text-amber-300/90 leading-relaxed italic">
                  "{{ (item as any)?.keterangan }}"
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </UModal>
  </template>

  <style scoped>
  :deep(.ol-viewport) {
    width: 100% !important;
    height: 100% !important;
    outline: none !important;
  }

  :deep(.ol-viewport canvas) {
    outline: none !important;
  }
  </style>

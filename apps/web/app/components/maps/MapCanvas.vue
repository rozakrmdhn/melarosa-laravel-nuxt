<script setup lang="ts">
import MapControls from "./MapControls.vue";
import MapScaleCoordinateBar from "./MapScaleCoordinateBar.vue";
import type { ActiveLayerItem, BasemapKey } from "~/types/map-layers";
import { normalizeBasemapAlias } from "~/types/basemap";
import { buildDynamicMaplibreBasemapSpec, resolveAbsoluteTileUrl } from "~/utils/basemap-sources";
import { useBasemapStore } from "~/stores/basemap";
import {
  extractMvtFeatureSelectionKey,
  resolveMvtLayerSelectionHint,
} from "~/utils/mvt-styler";
import {
  BOJONEGORO_MVT_BBOX_4326,
  type MvtExtentTuple,
} from "~/utils/mvt-loader";
import {
  buildMvtSubLayersForActiveLayer,
  computeGeoJsonClickPixelDistance,
  extractGeoJsonGeometryBbox,
  lonLat4326ToMercator,
  mercator3857To4326Bbox,
  stitchGeoJsonFragments,
  toMaplibreZoom,
  toStandardZoom,
} from "~/utils/maplibre-mvt-styler";
import {
  buildWmsGetMapPostXml,
  buildWmsSldXml,
  shouldUseWmsPostForSld,
} from "~/utils/wms-sld";
import { $http } from "~/utils/helpers";
import type { IdentifiedFeature } from "./IdentifyFeatures.vue";
import type { GeoparkSite } from "~/composables/useGeoparkSites";

export type { BasemapKey };

export interface MapViewportPayload {
  bbox: [number, number, number, number];
  zoom: number;
  center: [number, number];
}

const props = withDefaults(
  defineProps<{
    currentBasemap?: BasemapKey;
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    initialCenter?: [number, number] | null;
    initialBbox?: [number, number, number, number] | null;
    initialZoom?: number | null;
    defaultBbox?: [number, number, number, number] | null;
    leftPanelOpen?: boolean;
    activeLayers?: ActiveLayerItem[];
    mobileDrawerOpen?: boolean;
    mobileSheetSnap?: "min" | "peek" | "full";
    geoparkSites?: GeoparkSite[];
    selectedGeoparkSite?: GeoparkSite | null;
    activeTab?: string;
    deferLabelsOnMove?: boolean;
    show3dBuildings?: boolean;
    show3dTerrain?: boolean;
    terrainExaggeration?: number;
  }>(),
  {
    currentBasemap: "esri",
    clickedCoordinate: null,
    locationLabel: null,
    initialCenter: null,
    initialBbox: null,
    initialZoom: null,
    defaultBbox: () => [111.42351, -7.46964, 112.16569, -6.981707],
    leftPanelOpen: true,
    activeLayers: () => [],
    mobileDrawerOpen: false,
    mobileSheetSnap: "min",
    geoparkSites: () => [],
    selectedGeoparkSite: null,
    activeTab: "layers",
    deferLabelsOnMove: true,
    show3dBuildings: false,
    show3dTerrain: false,
    terrainExaggeration: 1.5,
  }
);

const emit = defineEmits<{
  (e: "map-click", coordinate: [number, number], meta?: { isPeekTap?: boolean }): void;
  (e: "canvas-tap-dismiss"): void;
  (e: "select-feature", feature: any): void;
  (e: "identify-features", features: IdentifiedFeature[]): void;
  (e: "identifying-features", loading: boolean): void;
  (e: "update:mouse-coords", coords: string): void;
  (e: "update:viewport", viewport: MapViewportPayload): void;
  (e: "loaded"): void;
  (e: "dismiss-popup"): void;
  (e: "select-geopark", site: GeoparkSite): void;
}>();

const BOJONEGORO_DEFAULT_BBOX: [number, number, number, number] = [111.42351, -7.46964, 112.16569, -6.981707];
const BOJONEGORO_CENTER: [number, number] = [111.7946, -7.2257];
const DEFAULT_ZOOM_256 = 11;

const BASEMAP_SOURCE_ID = "mr-basemap-source";
const BASEMAP_LAYER_ID = "mr-basemap-layer";
const TERRAIN_DEM_SOURCE_ID = "mr-src-terrain-dem";
const TERRAIN_HILLSHADE_LAYER_ID = "mr-terrain-hillshade";
const BUILDINGS_OSM_SOURCE_ID = "mr-src-osm-buildings";
const BUILDINGS_3D_LAYER_ID = "mr-3d-buildings";
const GRID_LINES_SOURCE_ID = "mr-graticule-lines-src";
const GRID_LABELS_SOURCE_ID = "mr-graticule-labels-src";
const GRID_LINES_LAYER_ID = "mr-graticule-lines-layer";
const GRID_LABELS_LAYER_ID = "mr-graticule-labels-layer";

const MVT_SEL_SOURCE_ID = "mr-mvt-sel-src";
const MVT_SEL_FILL_LAYER_ID = "mr-mvt-sel-poly-fill";
const SEL_GEOJSON_SOURCE_ID = "mr-sel-geojson-src";
const SEL_POLY_HALO_ID = "mr-sel-poly-halo";
const SEL_POLY_CORE_ID = "mr-sel-poly-core";
const SEL_LINE_GLOW_ID = "mr-sel-line-glow";
const SEL_LINE_CASING_ID = "mr-sel-line-casing";
const SEL_LINE_CORE_ID = "mr-sel-line-core";
const SEL_LINE_CENTER_ID = "mr-sel-line-center";
const SEL_PT_OUTER_CASING_ID = "mr-sel-pt-outer-casing";
const SEL_PT_OUTER_ID = "mr-sel-pt-outer";
const SEL_PT_MID_ID = "mr-sel-pt-mid";
const SEL_PT_CORE_ID = "mr-sel-pt-core";

const GEO_ACCURACY_SOURCE_ID = "mr-geo-accuracy-src";
const GEO_POINT_SOURCE_ID = "mr-geo-point-src";
const GEO_ACCURACY_FILL_ID = "mr-geo-accuracy-fill";
const GEO_ACCURACY_LINE_ID = "mr-geo-accuracy-line";
const GEO_HEADING_SYMBOL_ID = "mr-geo-heading-symbol";
const GEO_DOT_GLOW_ID = "mr-geo-dot-glow";
const GEO_DOT_RING_ID = "mr-geo-dot-ring";
const GEO_DOT_CORE_ID = "mr-geo-dot-core";
const GEO_HEADING_IMAGE_ID = "mr-geo-heading-cone-img";

const TOP_OVERLAY_LAYER_IDS = [
  GRID_LINES_LAYER_ID,
  GRID_LABELS_LAYER_ID,
  MVT_SEL_FILL_LAYER_ID,
  SEL_POLY_HALO_ID,
  SEL_POLY_CORE_ID,
  SEL_LINE_GLOW_ID,
  SEL_LINE_CASING_ID,
  SEL_LINE_CORE_ID,
  SEL_LINE_CENTER_ID,
  SEL_PT_OUTER_CASING_ID,
  SEL_PT_OUTER_ID,
  SEL_PT_MID_ID,
  SEL_PT_CORE_ID,
  GEO_ACCURACY_FILL_ID,
  GEO_ACCURACY_LINE_ID,
  GEO_HEADING_SYMBOL_ID,
  GEO_DOT_GLOW_ID,
  GEO_DOT_RING_ID,
  GEO_DOT_CORE_ID,
] as const;

interface PendingInitialExtentFit {
  kind: "bbox" | "coord";
  bbox?: [number, number, number, number];
  coord?: [number, number];
  maxZoom: number;
  snapOverride?: "min" | "peek" | "full";
}

interface MvtLayerRuntimeRecord {
  id: string;
  sourceId: string;
  sourceLayer: string;
  tileUrl: string;
  bounds4326?: [number, number, number, number] | null;
  subLayerIds: string[];
  identifiableLayerIds: string[];
  minZoomMl: number;
}

interface WmsLayerRuntimeRecord {
  id: string;
  sourceId: string;
  layerId: string;
  baseUrl: string;
  wmsLayerName: string;
  sldSig: string;
  usePostSld: boolean;
  minZoomMl: number;
}

const mapContainer = ref<HTMLElement | null>(null);
const scaleLineTarget = ref<HTMLElement | null>(null);

let mapInstance: any = null;
let maplibregl: any = null;
let scaleControlInstance: any = null;
let scaleControlEl: HTMLElement | null = null;
let pendingInitialExtent: PendingInitialExtentFit | null = null;
let resizeObserver: ResizeObserver | null = null;
let themeMutationObserver: MutationObserver | null = null;
let hoverCheckFrame: number | null = null;
let lastHoverCheckTime = 0;
let isDismissing = false;
let dismissTimer: ReturnType<typeof setTimeout> | null = null;
let selectionRequestSeq = 0;
let mvtPolySelectionKey: string | null = null;
const hydratedFullGeomKeys = new Set<string>();

const wmsLayersMap = new Map<string, WmsLayerRuntimeRecord>();
const mvtLayersMap = new Map<string, MvtLayerRuntimeRecord>();
const wmsPostConfigs = new Map<string, { url: string; sldXml: string }>();
let wmsPostProtocolRegistered = false;

const isDeferLabelsActive = ref(props.deferLabelsOnMove);
const livePointerCoords = ref<{ lng: number; lat: number } | null>(null);
const currentMapCenter = ref<{ lng: number; lat: number }>({
  lng: BOJONEGORO_CENTER[0],
  lat: BOJONEGORO_CENTER[1],
});
const currentZoomLevel = ref<number>(DEFAULT_ZOOM_256);
const isPointerOnCanvas = ref(false);
const activeDisplayCoords = computed(() => {
  if (isPointerOnCanvas.value && livePointerCoords.value) {
    return {
      coords: livePointerCoords.value,
      isLiveCursor: true,
    };
  }
  return {
    coords: livePointerCoords.value || currentMapCenter.value,
    isLiveCursor: false,
  };
});
const currentScaleRatio = ref<string>("1 : 25.000");

const mapLoaded = ref(false);
const isFullscreen = ref(false);
const isLocating = ref(false);
const hasUserLocation = ref(false);
const isUserLocationVisible = ref(false);
const userLocationCoords = ref<[number, number] | null>(null);
const activeNamobj = ref<string | null>(null);
const activeSelectedFeature = ref<IdentifiedFeature | null>(null);
const isIdentifyingFeatures = ref(false);
const userSpeed = ref<number | null>(null);
const userHeading = ref<number | null>(null);
const userAccuracy = ref<number | null>(null);
const showCartoGrid = ref(false);
const currentRotation = ref(0);

let lastReportedCoords: [number, number] | null = null;
let geolocationWatchId: number | null = null;
let orientationListener: ((e: any) => void) | null = null;
let isFirstLocationLock = true;
let geoAnimFrameId: number | null = null;
let geoAnimFrom: [number, number] | null = null;
let geoAnimTo: [number, number] | null = null;
let geoAnimStartTime: number | null = null;
let geoHeadingImageLoaded = false;
const GEO_ANIM_DURATION = 1200;

const basemapStoreForCanvas = useBasemapStore();

function prefersReducedMotion(): boolean {
  if (!import.meta.client || typeof window === "undefined") return false;
  return window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
}

function getMotionDuration(ms: number): number {
  if (prefersReducedMotion()) return 0;
  return typeof ms === "number" && Number.isFinite(ms) ? Math.max(0, ms) : 0;
}

function mountScaleControlIntoTarget() {
  if (!scaleLineTarget.value || !scaleControlEl) return;
  if (scaleControlEl.parentElement !== scaleLineTarget.value) {
    scaleLineTarget.value.innerHTML = "";
    scaleLineTarget.value.appendChild(scaleControlEl);
  }
}

function handleScaleTargetMounted(el: HTMLElement) {
  scaleLineTarget.value = el;
  mountScaleControlIntoTarget();
}

function formatScaleNumber(denom: number): string {
  if (denom <= 0) return "1.000";
  const STANDARD_SCALES = [
    500, 1000, 2000, 2500, 5000, 10000, 20000, 25000, 50000, 100000, 200000, 250000, 500000, 1000000, 2000000, 5000000,
  ];
  for (const std of STANDARD_SCALES) {
    const diff = Math.abs(denom - std) / std;
    if (diff < 0.22) {
      return std.toLocaleString("id-ID");
    }
  }
  if (denom < 1000) return (Math.round(denom / 50) * 50).toLocaleString("id-ID");
  if (denom < 10000) return (Math.round(denom / 500) * 500).toLocaleString("id-ID");
  if (denom < 100000) return (Math.round(denom / 1000) * 1000).toLocaleString("id-ID");
  if (denom < 500000) return (Math.round(denom / 5000) * 5000).toLocaleString("id-ID");
  return (Math.round(denom / 10000) * 10000).toLocaleString("id-ID");
}

function getZoomForScaleDenominator(scaleDenominator: number, centerCoord?: [number, number]): number {
  const lat = centerCoord && centerCoord.length >= 2 ? centerCoord[1] : BOJONEGORO_CENTER[1];
  const latRad = (lat * Math.PI) / 180;
  const cosLat = Math.max(0.1, Math.cos(latRad));
  const targetGroundResolution = scaleDenominator / 3779.527559;
  const targetMercRes512 = targetGroundResolution / cosLat;
  const z = Math.log2(40075016.68557849 / (512 * targetMercRes512));
  return Math.max(0, Math.min(20, z));
}

function updateMapCenterAndZoom() {
  if (!mapInstance) return;
  const zMl = mapInstance.getZoom();
  if (Number.isFinite(zMl)) {
    currentZoomLevel.value = Math.round(toStandardZoom(zMl));
  }
  const center = mapInstance.getCenter();
  if (center) {
    const lng = Number(center.lng);
    const lat = Number(center.lat);
    currentMapCenter.value = {
      lng: Number(lng.toFixed(5)),
      lat: Number(lat.toFixed(5)),
    };
    const latRad = (lat * Math.PI) / 180;
    const cosLat = Math.cos(latRad);
    const groundResolution = (40075016.68557849 * cosLat) / (512 * Math.pow(2, zMl));
    const scaleDenominator = Math.round(groundResolution * 3779.527559);
    currentScaleRatio.value = `1 : ${formatScaleNumber(scaleDenominator)}`;
  }
}

function handleSelectScale(targetDenom: number) {
  if (!mapInstance) return;
  const c = mapInstance.getCenter();
  const centerCoord: [number, number] = c ? [c.lng, c.lat] : BOJONEGORO_CENTER;
  const targetZoomMl = getZoomForScaleDenominator(targetDenom, centerCoord);
  mapInstance.easeTo({
    zoom: targetZoomMl,
    duration: getMotionDuration(350),
  });
}

function getDistanceMeters(coord1: [number, number], coord2: [number, number]): number {
  const [lon1, lat1] = coord1;
  const [lon2, lat2] = coord2;
  const R = 6371e3;
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

watch(
  () => props.locationLabel,
  (newLabel) => {
    if (newLabel) activeNamobj.value = newLabel;
  }
);

watch(
  () => props.clickedCoordinate,
  (coord) => {
    if (!coord) {
      activeNamobj.value = null;
      clearSelectionState();
    }
  },
  { deep: true }
);

function extractNamobj(properties: Record<string, any> | undefined): string | null {
  if (!properties) return null;
  for (const [k, v] of Object.entries(properties)) {
    if (k.toUpperCase() === "NAMOBJ" && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }
  const prioritizedKeys = ["NAMA_RUAS", "WADMKD", "NAMA_KECAMATAN", "WADMKC", "KECAMATAN", "WADMKK", "REMARK"];
  for (const pKey of prioritizedKeys) {
    for (const [k, v] of Object.entries(properties)) {
      if (k.toUpperCase() === pKey && v) {
        const s = String(v).trim();
        if (s && s !== "-") return s;
      }
    }
  }
  for (const [k, v] of Object.entries(properties)) {
    const upper = k.toUpperCase();
    if (!upper.includes("JABATAN") && (upper.includes("NAM") || upper.includes("TITLE")) && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }
  return null;
}

function getConditionColors(properties?: Record<string, any>, isDark = false) {
  let kondisiVal = "";
  if (properties) {
    for (const [k, v] of Object.entries(properties)) {
      const lower = k.toLowerCase();
      if (lower === "kondisi" || lower === "status" || lower.includes("kondisi")) {
        if (typeof v === "string" && v.trim()) {
          kondisiVal = v.trim().toLowerCase();
          break;
        }
      }
    }
  }
  if (kondisiVal.includes("rusak berat") || kondisiVal.includes("berat")) {
    return {
      core: isDark ? "#f87171" : "#ef4444",
      glow: isDark ? "rgba(248, 113, 113, 0.48)" : "rgba(239, 68, 68, 0.35)",
      fill: isDark ? "rgba(248, 113, 113, 0.18)" : "rgba(239, 68, 68, 0.12)",
    };
  }
  if (kondisiVal.includes("rusak ringan") || kondisiVal.includes("rusak")) {
    return {
      core: isDark ? "#fb923c" : "#f97316",
      glow: isDark ? "rgba(251, 146, 60, 0.48)" : "rgba(249, 115, 22, 0.35)",
      fill: isDark ? "rgba(251, 146, 60, 0.18)" : "rgba(249, 115, 22, 0.12)",
    };
  }
  if (kondisiVal.includes("sedang") || kondisiVal.includes("cukup")) {
    return {
      core: isDark ? "#fbbf24" : "#f59e0b",
      glow: isDark ? "rgba(251, 191, 36, 0.48)" : "rgba(245, 158, 11, 0.35)",
      fill: isDark ? "rgba(251, 191, 36, 0.18)" : "rgba(245, 158, 11, 0.12)",
    };
  }
  if (kondisiVal.includes("baik")) {
    return {
      core: isDark ? "#34d399" : "#10b981",
      glow: isDark ? "rgba(52, 211, 153, 0.48)" : "rgba(16, 185, 129, 0.35)",
      fill: isDark ? "rgba(52, 211, 153, 0.18)" : "rgba(16, 185, 129, 0.12)",
    };
  }
  return {
    core: isDark ? "#38bdf8" : "#0284c7",
    glow: isDark ? "rgba(56, 189, 248, 0.48)" : "rgba(2, 132, 199, 0.32)",
    fill: isDark ? "rgba(56, 189, 248, 0.16)" : "rgba(2, 132, 199, 0.12)",
  };
}

function emptyFeatureCollection(): GeoJSON.FeatureCollection {
  return { type: "FeatureCollection", features: [] };
}

function normalizeCoordLonLat(coord: [number, number]): [number, number] {
  if (!coord || coord.length < 2) return [111.89, -7.15];
  const [a, b] = coord;
  if (Math.abs(a) > 180 || Math.abs(b) > 180) {
    const converted = mercator3857To4326Bbox([a, b, a, b]);
    if (converted) return [converted[0], converted[1]];
  }
  if (Math.abs(a) <= 90 && Math.abs(b) > 90) {
    return [b, a];
  }
  return [a, b];
}

function normalizeBbox4326(rawBbox: [number, number, number, number]): [number, number, number, number] {
  if (!rawBbox || rawBbox.length !== 4) return [111.5, -7.4, 112.2, -7.0];
  const converted = mercator3857To4326Bbox(rawBbox);
  if (converted) {
    const [a, b, c, d] = converted;
    if (Math.abs(a) <= 90 && Math.abs(c) <= 90 && (Math.abs(b) > 90 || Math.abs(d) > 90)) {
      return [Math.min(b, d), Math.min(a, c), Math.max(b, d), Math.max(a, c)];
    }
    return converted;
  }
  return [111.5, -7.4, 112.2, -7.0];
}

function convertGeometryMercatorTo4326(geom: any): any {
  if (!geom || !geom.coordinates) return geom;
  const convertNode = (node: any): any => {
    if (!Array.isArray(node)) return node;
    if (node.length >= 2 && typeof node[0] === "number" && typeof node[1] === "number") {
      const converted = mercator3857To4326Bbox([node[0], node[1], node[0], node[1]]);
      return converted ? [converted[0], converted[1]] : [node[0], node[1]];
    }
    return node.map(convertNode);
  };
  return { ...geom, coordinates: convertNode(geom.coordinates) };
}

function extractFirstCoordinate(geom: any): [number, number] | null {
  if (!geom || !geom.coordinates) return null;
  let c = geom.coordinates;
  while (Array.isArray(c) && c.length > 0 && Array.isArray(c[0])) {
    c = c[0];
  }
  if (Array.isArray(c) && c.length >= 2 && typeof c[0] === "number" && typeof c[1] === "number") {
    return [c[0], c[1]];
  }
  return null;
}

function buildBasemapLayerConfig(key: BasemapKey): { source: any | null; layer: any | null } {
  const normalized = normalizeBasemapAlias(key);
  if (normalized === "none") return { source: null, layer: null };
  const item = basemapStoreForCanvas.getByCode(normalized, "maplibre");
  const spec = buildDynamicMaplibreBasemapSpec(item ?? normalized, { baseOpacity: 1 });
  const src = spec.sources?.basemap ?? null;
  const lyr = spec.layers?.[0] ?? null;
  if (!src || !lyr) return { source: null, layer: null };
  return {
    source: src,
    layer: { ...lyr, id: BASEMAP_LAYER_ID, source: BASEMAP_SOURCE_ID },
  };
}

function syncBasemap() {
  if (!mapInstance) return;
  const { source, layer } = buildBasemapLayerConfig(props.currentBasemap);

  if (mapInstance.getLayer(BASEMAP_LAYER_ID)) {
    mapInstance.removeLayer(BASEMAP_LAYER_ID);
  }
  if (mapInstance.getSource(BASEMAP_SOURCE_ID)) {
    mapInstance.removeSource(BASEMAP_SOURCE_ID);
  }

  if (source && layer) {
    mapInstance.addSource(BASEMAP_SOURCE_ID, source);
    const existingLayers = mapInstance.getStyle()?.layers || [];
    const firstNonBasemapId = existingLayers.find((l: any) => l.id !== BASEMAP_LAYER_ID)?.id;
    mapInstance.addLayer(layer, firstNonBasemapId);
  }
  enforceLayerStackOrder();
}

function build3dBuildingColorExpr(isDark: boolean) {
  return [
    "interpolate",
    ["linear"],
    ["coalesce", ["get", "render_height"], 8],
    0,
    isDark ? "#334155" : "#cbd5e1",
    12,
    isDark ? "#475569" : "#94a3b8",
    30,
    isDark ? "#64748b" : "#64748b",
    60,
    isDark ? "#94a3b8" : "#475569",
  ];
}

function sync3dEnvironment(options?: {
  adjustCamera?: boolean;
  justEnabledBuildings?: boolean;
  justEnabledTerrain?: boolean;
}) {
  if (!mapInstance) return;
  const isDark = typeof document !== "undefined" && document.documentElement.classList.contains("dark");
  const wantTerrain = Boolean(props.show3dTerrain);
  const wantBuildings = Boolean(props.show3dBuildings);
  const exaggerationRaw = Number(props.terrainExaggeration);
  const exaggeration = Number.isFinite(exaggerationRaw)
    ? Math.max(0.5, Math.min(3, exaggerationRaw))
    : 1.5;

  // 1. 3D Terrain (AWS Mapzen Terrarium DEM + subtle hillshade)
  if (wantTerrain) {
    if (!mapInstance.getSource(TERRAIN_DEM_SOURCE_ID)) {
      mapInstance.addSource(TERRAIN_DEM_SOURCE_ID, {
        type: "raster-dem",
        tiles: ["https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png"],
        tileSize: 256,
        maxzoom: 15,
        encoding: "terrarium",
      });
    }
    if (!mapInstance.getLayer(TERRAIN_HILLSHADE_LAYER_ID)) {
      mapInstance.addLayer(
        {
          id: TERRAIN_HILLSHADE_LAYER_ID,
          type: "hillshade",
          source: TERRAIN_DEM_SOURCE_ID,
          layout: { visibility: "visible" },
          paint: {
            "hillshade-exaggeration": 0.28,
            "hillshade-shadow-color": isDark ? "#020617" : "#475569",
            "hillshade-highlight-color": isDark ? "#64748b" : "#ffffff",
            "hillshade-accent-color": isDark ? "#334155" : "#94a3b8",
          },
        },
        getFirstOverlayLayerId()
      );
    } else {
      mapInstance.setLayoutProperty(TERRAIN_HILLSHADE_LAYER_ID, "visibility", "visible");
      mapInstance.setPaintProperty(TERRAIN_HILLSHADE_LAYER_ID, "hillshade-shadow-color", isDark ? "#020617" : "#475569");
      mapInstance.setPaintProperty(TERRAIN_HILLSHADE_LAYER_ID, "hillshade-highlight-color", isDark ? "#64748b" : "#ffffff");
      mapInstance.setPaintProperty(TERRAIN_HILLSHADE_LAYER_ID, "hillshade-accent-color", isDark ? "#334155" : "#94a3b8");
    }
    try {
      mapInstance.setTerrain({ source: TERRAIN_DEM_SOURCE_ID, exaggeration });
    } catch {
      // Abaikan bila WebGL terrain context belum siap
    }
  } else {
    try {
      if (mapInstance.getTerrain?.()) {
        mapInstance.setTerrain(null);
      }
    } catch {}
    if (mapInstance.getLayer(TERRAIN_HILLSHADE_LAYER_ID)) {
      mapInstance.setLayoutProperty(TERRAIN_HILLSHADE_LAYER_ID, "visibility", "none");
    }
  }

  // 2. 3D Buildings (OpenFreeMap / OpenMapTiles vector building fill-extrusion)
  if (wantBuildings) {
    if (!mapInstance.getSource(BUILDINGS_OSM_SOURCE_ID)) {
      mapInstance.addSource(BUILDINGS_OSM_SOURCE_ID, {
        type: "vector",
        url: "https://tiles.openfreemap.org/planet",
      });
    }
    const buildingColorExpr = build3dBuildingColorExpr(isDark);
    if (!mapInstance.getLayer(BUILDINGS_3D_LAYER_ID)) {
      mapInstance.addLayer(
        {
          id: BUILDINGS_3D_LAYER_ID,
          type: "fill-extrusion",
          source: BUILDINGS_OSM_SOURCE_ID,
          "source-layer": "building",
          minzoom: 13.5,
          filter: ["!=", ["get", "hide_3d"], true],
          layout: { visibility: "visible" },
          paint: {
            "fill-extrusion-color": buildingColorExpr,
            "fill-extrusion-height": [
              "interpolate",
              ["linear"],
              ["zoom"],
              13.5,
              0,
              14.2,
              ["coalesce", ["get", "render_height"], 8],
            ],
            "fill-extrusion-base": [
              "interpolate",
              ["linear"],
              ["zoom"],
              13.5,
              0,
              14.2,
              ["coalesce", ["get", "render_min_height"], 0],
            ],
            "fill-extrusion-vertical-gradient": true,
            "fill-extrusion-opacity": 0.88,
          },
        },
        getFirstOverlayLayerId()
      );
    } else {
      mapInstance.setLayoutProperty(BUILDINGS_3D_LAYER_ID, "visibility", "visible");
      mapInstance.setPaintProperty(BUILDINGS_3D_LAYER_ID, "fill-extrusion-color", buildingColorExpr);
    }
  } else if (mapInstance.getLayer(BUILDINGS_3D_LAYER_ID)) {
    mapInstance.setLayoutProperty(BUILDINGS_3D_LAYER_ID, "visibility", "none");
  }

  // 3. Atmospheric horizon sky for tilted 3D mode
  try {
    if (typeof mapInstance.setSky === "function") {
      if (wantTerrain || wantBuildings) {
        mapInstance.setSky({
          "sky-color": isDark ? "#0f172a" : "#88c6fc",
          "horizon-color": isDark ? "#1e293b" : "#e2eef8",
          "fog-color": isDark ? "#1e293b" : "#e2eef8",
          "fog-ground-blend": 0.45,
          "sky-horizon-blend": 0.7,
          "atmosphere-blend": 0.8,
        });
      } else {
        mapInstance.setSky(undefined);
      }
    }
  } catch {}

  enforceLayerStackOrder();

  if (options?.adjustCamera) {
    const curPitch = mapInstance.getPitch?.() || 0;
    const curZoom = mapInstance.getZoom?.() || 10;
    if (wantTerrain || wantBuildings) {
      const targetPitch = curPitch < 20 ? 48 : curPitch;
      const shouldZoomIn = Boolean(options.justEnabledBuildings) && curZoom < 13.5;
      if (curPitch < 20 || shouldZoomIn) {
        mapInstance.easeTo({
          pitch: targetPitch,
          ...(shouldZoomIn ? { zoom: 14.2 } : {}),
          duration: getMotionDuration(600),
        });
      }
    } else if (curPitch > 0) {
      mapInstance.easeTo({
        pitch: 0,
        duration: getMotionDuration(450),
      });
    }
  }
}

watch(
  [() => props.show3dBuildings, () => props.show3dTerrain, () => props.terrainExaggeration],
  ([nextBuildings, nextTerrain], [prevBuildings, prevTerrain]) => {
    const toggledBuildings = Boolean(nextBuildings) !== Boolean(prevBuildings);
    const toggledTerrain = Boolean(nextTerrain) !== Boolean(prevTerrain);
    sync3dEnvironment({
      adjustCamera: toggledBuildings || toggledTerrain,
      justEnabledBuildings: !prevBuildings && Boolean(nextBuildings),
      justEnabledTerrain: !prevTerrain && Boolean(nextTerrain),
    });
  }
);

watch(
  [() => props.currentBasemap, () => basemapStoreForCanvas.items],
  () => {
    syncBasemap();
  },
  { deep: false }
);

function getFirstOverlayLayerId(): string | undefined {
  if (!mapInstance) return undefined;
  for (const id of TOP_OVERLAY_LAYER_IDS) {
    if (mapInstance.getLayer(id)) return id;
  }
  return undefined;
}

function enforceLayerStackOrder() {
  if (!mapInstance) return;
  const orderedOverlayIds = TOP_OVERLAY_LAYER_IDS.filter((id) => Boolean(mapInstance.getLayer(id)));
  const firstOverlay = orderedOverlayIds[0];

  if (mapInstance.getLayer(TERRAIN_HILLSHADE_LAYER_ID)) {
    mapInstance.moveLayer(TERRAIN_HILLSHADE_LAYER_ID, firstOverlay);
  }

  // Order active GIS layers bottom-up beneath 3D buildings and top overlays
  const bottomUp = [...(props.activeLayers || [])].reverse();
  for (const layer of bottomUp) {
    const mvtRec = mvtLayersMap.get(layer.id);
    if (mvtRec) {
      for (const subId of mvtRec.subLayerIds) {
        if (mapInstance.getLayer(subId)) {
          mapInstance.moveLayer(subId, firstOverlay);
        }
      }
      continue;
    }
    const wmsRec = wmsLayersMap.get(layer.id);
    if (wmsRec && mapInstance.getLayer(wmsRec.layerId)) {
      mapInstance.moveLayer(wmsRec.layerId, firstOverlay);
    }
  }

  if (mapInstance.getLayer(BUILDINGS_3D_LAYER_ID)) {
    mapInstance.moveLayer(BUILDINGS_3D_LAYER_ID, firstOverlay);
  }

  // Re-assert overlay z-stack order from bottom overlay to top GPS dot
  for (let i = 0; i < orderedOverlayIds.length; i++) {
    mapInstance.moveLayer(orderedOverlayIds[i]);
  }

  // Keep basemap strictly at bottom of style layers
  if (mapInstance.getLayer(BASEMAP_LAYER_ID)) {
    const firstLayer = mapInstance.getStyle()?.layers?.find((l: any) => l.id !== BASEMAP_LAYER_ID)?.id;
    if (firstLayer) {
      mapInstance.moveLayer(BASEMAP_LAYER_ID, firstLayer);
    }
  }
}

function isLayerIdentifyDisabled(layer: any): boolean {
  if (!layer) return false;
  if (layer.disableIdentify === true) return true;
  const id = String(layer.id || "").toLowerCase();
  const layerName = String(layer.layer_name || layer.wmsLayerName || layer.name || "").toLowerCase();
  const martinSource = String(layer.martinSource || "").toLowerCase();
  const title = String(layer.title || "").toLowerCase();
  const url = String(layer.url || layer.mvtUrl || layer.wmsUrl || "").toLowerCase();

  return (
    layerName === "bataswilayah_kabupaten" ||
    martinSource === "bataswilayah_kabupaten" ||
    url.includes("bataswilayah_kabupaten") ||
    id.includes("bataswilayah_kabupaten") ||
    title.includes("batas wilayah kabupaten")
  );
}

function isMvtActiveLayer(layer: ActiveLayerItem): boolean {
  return Boolean(
    layer.protocol?.toUpperCase() === "MVT" ||
      layer.source_type === "martin" ||
      (layer.source_type === "internal" && !layer.wmsUrl && Boolean(layer.layer_name)) ||
      Boolean(layer.mvtUrl) ||
      Boolean(layer.martinSource) ||
      (layer.layer_name &&
        ["infrastruktur_segmen", "jalan_porosdesa", "jalan_kabupaten_luarkota", "bataswilayah_kabupaten"].includes(
          layer.layer_name
        )) ||
      (layer.url && layer.url.includes("/martin/"))
  );
}

function resolveLayerBounds4326(layer: Partial<ActiveLayerItem> & Record<string, any>): [number, number, number, number] | null {
  const metaBounds = layer?.csw_metadata?.bounds ? mercator3857To4326Bbox(layer.csw_metadata.bounds) : null;
  if (metaBounds) {
    return [metaBounds[0] - 0.015, metaBounds[1] - 0.015, metaBounds[2] + 0.015, metaBounds[3] + 0.015];
  }
  const srcName = String(layer?.martinSource || layer?.layer_name || "").toLowerCase();
  const rawUrl = String(layer?.mvtUrl || layer?.url || "");
  if (
    layer?.source_type === "martin" ||
    rawUrl.startsWith("/martin/") ||
    ["infrastruktur_segmen", "jalan_porosdesa", "jalan_kabupaten_luarkota", "bataswilayah_kabupaten", "bataswilayah_desa", "bataswilayah_kecamatan"].includes(srcName)
  ) {
    return BOJONEGORO_MVT_BBOX_4326 as MvtExtentTuple;
  }
  return null;
}

function removeMvtLayerRecord(id: string) {
  const rec = mvtLayersMap.get(id);
  if (!rec || !mapInstance) {
    mvtLayersMap.delete(id);
    return;
  }
  for (const subId of rec.subLayerIds) {
    if (mapInstance.getLayer(subId)) {
      mapInstance.removeLayer(subId);
    }
  }
  if (mapInstance.getSource(rec.sourceId)) {
    mapInstance.removeSource(rec.sourceId);
  }
  mvtLayersMap.delete(id);
}

function removeWmsLayerRecord(id: string) {
  const rec = wmsLayersMap.get(id);
  if (!rec || !mapInstance) {
    wmsLayersMap.delete(id);
    return;
  }
  if (mapInstance.getLayer(rec.layerId)) {
    mapInstance.removeLayer(rec.layerId);
  }
  if (mapInstance.getSource(rec.sourceId)) {
    mapInstance.removeSource(rec.sourceId);
  }
  for (const k of wmsPostConfigs.keys()) {
    if (k.startsWith(`${id}:`)) wmsPostConfigs.delete(k);
  }
  wmsLayersMap.delete(id);
}

function syncActiveLayers() {
  if (!mapInstance) return;
  const currentLayers = props.activeLayers || [];
  const currentLayerIds = new Set(currentLayers.map((l) => l.id));
  const config = useRuntimeConfig();
  const martinBase = String(config.public?.martinUrl || "/martin").replace(/\/+$/, "");
  const isDark = typeof document !== "undefined" && document.documentElement.classList.contains("dark");

  for (const id of [...wmsLayersMap.keys()]) {
    if (!currentLayerIds.has(id)) removeWmsLayerRecord(id);
  }
  for (const id of [...mvtLayersMap.keys()]) {
    if (!currentLayerIds.has(id)) removeMvtLayerRecord(id);
  }

  const beforeOverlayId = getFirstOverlayLayerId();

  [...currentLayers].reverse().forEach((layer) => {
    // A. Martin MVT Vector Tile Layer
    if (isMvtActiveLayer(layer)) {
      const sourceName =
        layer.martinSource ||
        layer.layer_name ||
        (layer.url ? layer.url.match(/\/martin\/([^/?#{]+)/)?.[1] : undefined) ||
        (layer.id.includes("segmen") ? "infrastruktur_segmen" : "jalan_porosdesa");
      const normalizedSourceName = String(sourceName || "").trim().toLowerCase();
      const rawTileUrl = layer.mvtUrl || `${martinBase}/${sourceName}/{z}/{x}/{y}`;
      const tileUrl = resolveAbsoluteTileUrl(rawTileUrl);
      const sourceId = `mr-src-mvt-${layer.id.replace(/[^a-zA-Z0-9_-]/g, "_")}`;
      const bounds4326 = resolveLayerBounds4326(layer);

      const prevRec = mvtLayersMap.get(layer.id);
      const sourceChanged = !prevRec || prevRec.tileUrl !== tileUrl || prevRec.sourceLayer !== normalizedSourceName;

      if (sourceChanged) {
        removeMvtLayerRecord(layer.id);
        mapInstance.addSource(sourceId, {
          type: "vector",
          tiles: [tileUrl],
          minzoom: normalizedSourceName === "bataswilayah_kabupaten" ? 4 : 6,
          maxzoom: 20,
          ...(bounds4326 ? { bounds: bounds4326 } : {}),
          promoteId: { [sourceName]: "id", [normalizedSourceName]: "id" },
        });
      }

      const bundle = buildMvtSubLayersForActiveLayer(layer, sourceId, sourceName, { isDark });
      const newIds = bundle.layers.map((l) => l.id);
      const prevIds = prevRec?.subLayerIds || [];

      const structureUnchanged =
        !sourceChanged &&
        prevIds.length === newIds.length &&
        newIds.every((id, idx) => prevIds[idx] === id && Boolean(mapInstance.getLayer(id)));

      if (structureUnchanged) {
        for (const spec of bundle.layers) {
          mapInstance.setLayoutProperty(spec.id, "visibility", spec.layout?.visibility || "visible");
          if (spec.filter) {
            mapInstance.setFilter(spec.id, spec.filter);
          } else {
            mapInstance.setFilter(spec.id, null);
          }
          if (typeof spec.minzoom === "number") {
            mapInstance.setLayerZoomRange(spec.id, spec.minzoom, 24);
          }
          if (spec.paint) {
            for (const [prop, val] of Object.entries(spec.paint)) {
              mapInstance.setPaintProperty(spec.id, prop, val);
            }
          }
        }
      } else {
        for (const oldId of prevIds) {
          if (mapInstance.getLayer(oldId)) mapInstance.removeLayer(oldId);
        }
        for (const spec of bundle.layers) {
          mapInstance.addLayer(spec, beforeOverlayId);
        }
      }

      mvtLayersMap.set(layer.id, {
        id: layer.id,
        sourceId,
        sourceLayer: normalizedSourceName,
        tileUrl,
        bounds4326,
        subLayerIds: newIds,
        identifiableLayerIds: bundle.identifiableLayerIds,
        minZoomMl: bundle.layers[0]?.minzoom ?? 0,
      });
      return;
    }

    // B. GeoServer / OGC WMS Layer
    const rawWmsUrl = layer.wmsUrl || layer.url;
    const effectiveWmsLayerName = layer.wmsLayerName || layer.layer_name;
    if (!rawWmsUrl || !effectiveWmsLayerName) return;

    let cleanWmsUrl = rawWmsUrl;
    try {
      const parsed = new URL(rawWmsUrl, typeof window !== "undefined" ? window.location.origin : "http://localhost");
      const stripKeys = ["service", "request", "version", "srs", "crs", "bbox", "width", "height", "layers", "tiled", "tilesorigin"];
      if (layer.style_config) {
        stripKeys.push("styles", "sld", "sld_body");
      }
      for (const k of stripKeys) {
        parsed.searchParams.delete(k);
        parsed.searchParams.delete(k.toUpperCase());
      }
      cleanWmsUrl = parsed.toString();
    } catch {
      cleanWmsUrl = rawWmsUrl.split("?")[0] || rawWmsUrl;
    }

    const sldXml = buildWmsSldXml(effectiveWmsLayerName, layer.style_config, cleanWmsUrl);
    const sldSig = sldXml || "";
    const rawMinZoom = Number(layer.style_config?.min_zoom);
    const minZoomMl = Number.isFinite(rawMinZoom) && rawMinZoom > 0 ? toMaplibreZoom(rawMinZoom) : 0;
    const usePostSld = Boolean(sldXml && shouldUseWmsPostForSld(cleanWmsUrl, sldXml));

    const prevWms = wmsLayersMap.get(layer.id);
    const wmsChanged =
      !prevWms ||
      prevWms.baseUrl !== cleanWmsUrl ||
      prevWms.wmsLayerName !== effectiveWmsLayerName ||
      prevWms.sldSig !== sldSig ||
      prevWms.usePostSld !== usePostSld;

    const visibility = layer.visible !== false ? "visible" : "none";
    const opacity = typeof layer.opacity === "number" ? Math.max(0, Math.min(1, layer.opacity)) : 1;

    if (!wmsChanged && prevWms && mapInstance.getLayer(prevWms.layerId)) {
      mapInstance.setLayoutProperty(prevWms.layerId, "visibility", visibility);
      mapInstance.setPaintProperty(prevWms.layerId, "raster-opacity", opacity);
      mapInstance.setLayerZoomRange(prevWms.layerId, minZoomMl, 24);
      return;
    }

    removeWmsLayerRecord(layer.id);

    const safeSlug = layer.id.replace(/[^a-zA-Z0-9_-]/g, "_");
    const sourceId = `mr-src-wms-${safeSlug}`;
    const layerId = `mr-wms-${safeSlug}`;

    let tileTemplate: string;
    if (usePostSld && sldXml) {
      const postKey = `${layer.id}:${Date.now()}:${sldXml.length}`;
      wmsPostConfigs.set(postKey, { url: cleanWmsUrl, sldXml });
      tileTemplate = `wms-sld-post://tile?key=${encodeURIComponent(postKey)}&bbox={bbox-epsg-3857}`;
    } else {
      const sep = cleanWmsUrl.includes("?") ? "&" : "?";
      tileTemplate =
        `${cleanWmsUrl}${sep}SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap` +
        `&LAYERS=${encodeURIComponent(effectiveWmsLayerName)}` +
        `&BBOX={bbox-epsg-3857}&WIDTH=256&HEIGHT=256&SRS=EPSG:3857&FORMAT=image/png&TRANSPARENT=true` +
        (sldXml ? `&SLD_BODY=${encodeURIComponent(sldXml)}` : "&STYLES=");
    }

    mapInstance.addSource(sourceId, {
      type: "raster",
      tiles: [tileTemplate],
      tileSize: 256,
    });

    mapInstance.addLayer(
      {
        id: layerId,
        type: "raster",
        source: sourceId,
        minzoom: minZoomMl,
        layout: { visibility },
        paint: { "raster-opacity": opacity },
      },
      beforeOverlayId
    );

    wmsLayersMap.set(layer.id, {
      id: layer.id,
      sourceId,
      layerId,
      baseUrl: cleanWmsUrl,
      wmsLayerName: effectiveWmsLayerName,
      sldSig,
      usePostSld,
      minZoomMl,
    });
  });

  enforceLayerStackOrder();

  if (activeSelectedFeature.value) {
    const activeLayerMatch = resolveActiveLayerForFeature(activeSelectedFeature.value);
    if (!activeLayerMatch || activeLayerMatch.visible === false) {
      clearSelectionState();
    } else {
      syncMvtSelectedFeatureState(activeSelectedFeature.value);
    }
  }
}

watch(
  () => props.activeLayers,
  () => {
    syncActiveLayers();
  },
  { deep: true }
);

function resolveActiveLayerForFeature(feat?: IdentifiedFeature | null): ActiveLayerItem | null {
  if (!feat) return null;
  const list = props.activeLayers || [];
  if (feat.layerId) {
    const byId = list.find((l) => l.id === feat.layerId);
    if (byId) return byId;
  }
  const targetName = String(feat.layerName || feat.properties?.layer || "").trim().toLowerCase();
  if (targetName) {
    const byName = list.find((l) => resolveMvtLayerSelectionHint(l) === targetName);
    if (byName) return byName;
  }
  if (feat.layerId) {
    const byIdAsName = String(feat.layerId).trim().toLowerCase();
    return list.find((l) => resolveMvtLayerSelectionHint(l) === byIdAsName) || null;
  }
  return null;
}

function resolveMvtRuntimeEntry(
  feat?: IdentifiedFeature | null
): { layerId: string; rec: MvtLayerRuntimeRecord; activeLayer: ActiveLayerItem | null } | null {
  if (!feat) return null;
  const activeLayer = resolveActiveLayerForFeature(feat);
  if (feat.layerId && mvtLayersMap.has(feat.layerId)) {
    return { layerId: feat.layerId, rec: mvtLayersMap.get(feat.layerId)!, activeLayer };
  }
  if (activeLayer?.id && mvtLayersMap.has(activeLayer.id)) {
    return { layerId: activeLayer.id, rec: mvtLayersMap.get(activeLayer.id)!, activeLayer };
  }
  const targetHint = resolveMvtLayerSelectionHint(
    activeLayer || { id: feat.layerId, layer_name: feat.layerName || undefined },
    feat.properties?.layer
  );
  if (!targetHint) return null;
  for (const [id, rec] of mvtLayersMap.entries()) {
    if (rec.sourceLayer === targetHint) {
      const matched = props.activeLayers?.find((l) => l.id === id) || activeLayer;
      return { layerId: id, rec, activeLayer: matched };
    }
  }
  return null;
}

function resolveFeatureSelection(f: any, layerHint?: string | null) {
  const propsObj = f?.properties || {};
  const rawId = f?.rawFeatureId ?? f?.id;
  const explicitLayer = f?.layerName || propsObj?.layer;
  const resolvedHint = String(layerHint || explicitLayer || "").trim().toLowerCase();

  if (f?.selectionKey) {
    const preKey = String(f.selectionKey).trim();
    const sep = preKey.indexOf("::");
    if (sep !== -1) {
      const preLayer = preKey.slice(0, sep).trim().toLowerCase();
      const preUid = preKey.slice(sep + 2).trim();
      if (!resolvedHint || preLayer === resolvedHint) {
        return { layerName: preLayer, uniqueId: preUid || null, key: preKey, id: preKey };
      }
    }
  }
  return extractMvtFeatureSelectionKey(propsObj, rawId, resolvedHint);
}

function buildFeatureGeometryCacheKey(feat: IdentifiedFeature, layerHint?: string): string {
  const sel = resolveFeatureSelection(feat, layerHint);
  if (sel.key) return sel.key;
  return `${feat.layerId}::${feat.rawFeatureId ?? feat.id ?? ""}`;
}

function querySourceFragmentsForKey(rec: MvtLayerRuntimeRecord, selKey: string, layerHint: string): any[] {
  if (!mapInstance || !selKey) return [];
  try {
    const all = mapInstance.querySourceFeatures(rec.sourceId, { sourceLayer: rec.sourceLayer }) || [];
    const seen = new Set<string>();
    const out: any[] = [];
    for (const f of all) {
      const propsObj = f.properties || {};
      const fSel = extractMvtFeatureSelectionKey(propsObj, f.id, layerHint);
      if (!fSel.key || fSel.key !== selKey) continue;
      if (f.geometry) {
        const bbox = extractGeoJsonGeometryBbox(f.geometry);
        const sig = bbox ? bbox.map((v) => v.toFixed(5)).join("_") : "";
        if (sig && seen.has(sig)) continue;
        if (sig) seen.add(sig);
        out.push(f.geometry);
      }
    }
    return out;
  } catch {
    return [];
  }
}

function buildSelectionOverlayGeoJson(
  geom: any,
  geomType: string,
  properties: Record<string, any> | undefined,
  isMvt: boolean,
  isWms: boolean
): GeoJSON.FeatureCollection {
  if (!geom || !geom.coordinates) return emptyFeatureCollection();
  const isDark = typeof document !== "undefined" && document.documentElement.classList.contains("dark");
  const theme = getConditionColors(properties, isDark);
  const typeLower = (geomType || geom.type || "").toLowerCase();

  let role = "point";
  if (typeLower.includes("polygon")) role = isMvt ? "polygon-mvt" : "polygon-full";
  else if (typeLower.includes("line")) role = isWms ? "line-wms" : "line";
  else if (isWms) role = "point-wms";

  return {
    type: "FeatureCollection",
    features: [
      {
        type: "Feature",
        geometry: geom,
        properties: {
          role,
          coreColor: theme.core,
          glowColor: theme.glow,
          casingColor: isDark ? "#070b14" : "#ffffff",
          reticleOuterCasing: isDark ? "rgba(7, 11, 20, 0.95)" : "rgba(255, 255, 255, 0.95)",
          reticleOuterFill: isDark ? "rgba(37, 99, 235, 0.12)" : "rgba(37, 99, 235, 0.08)",
        },
      },
    ],
  };
}

function syncMvtSelectedFeatureState(feat: IdentifiedFeature | null) {
  if (!mapInstance) return;
  const entry = resolveMvtRuntimeEntry(feat);
  const layerHint = feat
    ? resolveMvtLayerSelectionHint(
        entry?.activeLayer || { id: feat.layerId, layer_name: feat.layerName || undefined },
        feat.properties?.layer || entry?.rec.sourceLayer
      )
    : "";
  const sel = feat ? resolveFeatureSelection(feat, layerHint) : { layerName: "", uniqueId: null, key: null };

  const geomType = String(feat?.geometryType || "").toLowerCase();
  const activeLayerMatch = resolveActiveLayerForFeature(feat);
  const isTargetVisible = Boolean(activeLayerMatch ? activeLayerMatch.visible !== false : entry);
  const shouldRenderOverlay = Boolean(feat && entry && isTargetVisible && geomType.includes("polygon") && sel.uniqueId);

  if (shouldRenderOverlay && entry && sel.uniqueId) {
    mvtPolySelectionKey = sel.key;
    const numericId = Number(sel.uniqueId);
    const idCandidates = ["id", "ID", "gid", "GID", "fid", "FID", "ogc_fid", "objectid", "id_desa", "id_kecamatan"];
    const idFilter: any[] = [
      "any",
      ...(Number.isFinite(numericId) ? [["==", ["id"], numericId]] : []),
      ["==", ["to-string", ["id"]], sel.uniqueId],
      ...idCandidates.map((k) => ["==", ["to-string", ["coalesce", ["get", k], ""]], sel.uniqueId]),
    ];

    const overlayFilter = [
      "all",
      ["==", ["geometry-type"], "Polygon"],
      idFilter,
    ];

    if (mapInstance.getLayer(MVT_SEL_FILL_LAYER_ID)) {
      mapInstance.removeLayer(MVT_SEL_FILL_LAYER_ID);
    }
    const beforeSelLine = mapInstance.getLayer(SEL_POLY_HALO_ID) ? SEL_POLY_HALO_ID : undefined;
    mapInstance.addLayer(
      {
        id: MVT_SEL_FILL_LAYER_ID,
        type: "fill",
        source: entry.rec.sourceId,
        "source-layer": entry.rec.sourceLayer,
        filter: overlayFilter,
        paint: {
          "fill-color": "rgba(255, 255, 255, 0.14)",
        },
      },
      beforeSelLine
    );
  } else {
    mvtPolySelectionKey = null;
    if (mapInstance.getLayer(MVT_SEL_FILL_LAYER_ID)) {
      mapInstance.removeLayer(MVT_SEL_FILL_LAYER_ID);
    }
  }
}

function clearSelectionState() {
  selectionRequestSeq++;
  activeSelectedFeature.value = null;
  mvtPolySelectionKey = null;
  if (mapInstance) {
    const src = mapInstance.getSource(SEL_GEOJSON_SOURCE_ID);
    if (src && typeof src.setData === "function") {
      src.setData(emptyFeatureCollection());
    }
    if (mapInstance.getLayer(MVT_SEL_FILL_LAYER_ID)) {
      mapInstance.removeLayer(MVT_SEL_FILL_LAYER_ID);
    }
  }
}

async function fetchFullFeatureGeometry(feat: IdentifiedFeature, expectedSeq: number) {
  if (!feat || !feat.layerId || !mapInstance) return;
  const matchedLayer = resolveActiveLayerForFeature(feat);
  const isInternalMvtLayer = Boolean(
    mvtLayersMap.has(feat.layerId) ||
      (matchedLayer?.id && mvtLayersMap.has(matchedLayer.id)) ||
      matchedLayer?.protocol?.toUpperCase() === "MVT" ||
      matchedLayer?.source_type === "martin" ||
      matchedLayer?.source_type === "internal" ||
      matchedLayer?.url?.includes("/martin/")
  );
  if (!isInternalMvtLayer) return;

  const layerNameHint = resolveMvtLayerSelectionHint(
    matchedLayer || { id: feat.layerId, layer_name: feat.layerName || undefined },
    feat.properties?.layer
  );
  const sel = resolveFeatureSelection(feat, layerNameHint);
  const cacheKey = buildFeatureGeometryCacheKey(feat, layerNameHint);
  if ((feat as any)._hasFullBackendGeometry && feat.geojsonGeometry) return;

  const fallbackIdRaw = feat.rawFeatureId ?? (feat.id?.includes(".") ? feat.id.split(".").pop() : feat.id);
  const fallbackIdClean = fallbackIdRaw !== undefined && fallbackIdRaw !== null ? String(fallbackIdRaw).trim() : "";
  const isValidFallbackId =
    fallbackIdClean !== "" && !fallbackIdClean.startsWith("synthetic:") && !/^0\.\d+$/.test(fallbackIdClean);
  const featureId = sel.uniqueId || (isValidFallbackId ? fallbackIdClean : "");
  const namobj = featureId ? "" : extractNamobj(feat.properties);
  if (!featureId && !namobj) return;

  const layerRef = matchedLayer?.id || feat.layerId;

  try {
    const res: any = await $http(`layers/${encodeURIComponent(layerRef)}/feature-geometry`, {
      query: {
        layer_name: layerNameHint || undefined,
        feature_id: featureId || undefined,
        namobj: namobj || undefined,
      },
    });

    if (expectedSeq !== selectionRequestSeq || !mapInstance || !activeSelectedFeature.value) return;
    if (!res?.ok || !res?.geometry) return;

    const activeMatched = resolveActiveLayerForFeature(activeSelectedFeature.value);
    const activeHint = resolveMvtLayerSelectionHint(
      activeMatched || { id: activeSelectedFeature.value.layerId, layer_name: activeSelectedFeature.value.layerName },
      activeSelectedFeature.value.properties?.layer
    );
    const activeKey = buildFeatureGeometryCacheKey(activeSelectedFeature.value, activeHint);
    if (activeKey !== cacheKey) return;

    hydratedFullGeomKeys.add(cacheKey);
    const resolvedGeomType = String(res.geometry.type || feat.geometryType || "");
    const selSrc = mapInstance.getSource(SEL_GEOJSON_SOURCE_ID);
    if (selSrc && typeof selSrc.setData === "function") {
      selSrc.setData(buildSelectionOverlayGeoJson(res.geometry, resolvedGeomType, feat.properties, false, false));
    }
  } catch {
    // Keep immediate local highlight silently
  }
}

function renderSelectedFeature(feat: IdentifiedFeature | null, options?: { force?: boolean }) {
  if (!feat) {
    clearSelectionState();
    return;
  }

  const entry = resolveMvtRuntimeEntry(feat);
  const layerHint = resolveMvtLayerSelectionHint(
    entry?.activeLayer || { id: feat.layerId, layer_name: feat.layerName || undefined },
    feat.properties?.layer || entry?.rec.sourceLayer
  );
  const sel = resolveFeatureSelection(feat, layerHint);
  const geomType = String(feat.geometryType || "").toLowerCase();
  const isMvtPoly = Boolean(entry && geomType.includes("polygon") && sel.key);

  if (!options?.force && activeSelectedFeature.value) {
    const prev = activeSelectedFeature.value;
    const prevEntry = resolveMvtRuntimeEntry(prev);
    const prevHint = resolveMvtLayerSelectionHint(
      prevEntry?.activeLayer || { id: prev.layerId, layer_name: prev.layerName || undefined },
      prev.properties?.layer || prevEntry?.rec.sourceLayer
    );
    const prevKey = buildFeatureGeometryCacheKey(prev, prevHint);
    const nextKey = buildFeatureGeometryCacheKey(feat, layerHint);
    const isAlreadyRendered = isMvtPoly ? mvtPolySelectionKey === sel.key : prevKey === nextKey;
    if (prev.layerId === feat.layerId && prevKey === nextKey && isAlreadyRendered) {
      activeSelectedFeature.value = feat;
      return;
    }
  }

  const reqSeq = ++selectionRequestSeq;
  activeSelectedFeature.value = feat;
  syncMvtSelectedFeatureState(feat);
  if (!mapInstance) return;

  const selSrc = mapInstance.getSource(SEL_GEOJSON_SOURCE_ID);
  if (!selSrc || typeof selSrc.setData !== "function") return;

  let localGeom: any = null;
  if (entry?.rec && sel.key) {
    const fragments = querySourceFragmentsForKey(entry.rec, sel.key, layerHint);
    if (fragments.length > 0) {
      localGeom = stitchGeoJsonFragments(fragments);
    }
  }

  if (!localGeom && feat.geojsonGeometry) {
    const sample = extractFirstCoordinate(feat.geojsonGeometry);
    const isMercator = Boolean(sample && (Math.abs(sample[0]) > 180 || Math.abs(sample[1]) > 90));
    localGeom = isMercator ? convertGeometryMercatorTo4326(feat.geojsonGeometry) : feat.geojsonGeometry;
  }

  if (!localGeom && feat.coordinate) {
    const c = normalizeCoordLonLat(feat.coordinate);
    localGeom = { type: "Point", coordinates: c };
  }

  const isMvt = Boolean(
    entry ||
      (feat.layerId && mvtLayersMap.has(feat.layerId)) ||
      (feat.layerId &&
        props.activeLayers?.some(
          (l) => (l.id === feat.layerId || l.layer_name === feat.layerId) && (l.protocol === "MVT" || l.source_type === "martin")
        ))
  );
  const isWms =
    !isMvt &&
    Boolean(
      feat.layerId &&
        (wmsLayersMap.has(feat.layerId) ||
          props.activeLayers?.some((l) => (l.id === feat.layerId || l.layer_name === feat.layerId) && l.protocol === "WMS"))
    );

  if (isMvtPoly) {
    if ((feat as any)._hasFullBackendGeometry && feat.geojsonGeometry) {
      selSrc.setData(buildSelectionOverlayGeoJson(localGeom, feat.geometryType || "Polygon", feat.properties, false, false));
    } else {
      selSrc.setData(emptyFeatureCollection());
      fetchFullFeatureGeometry(feat, reqSeq);
    }
    return;
  }

  if (localGeom) {
    const resolvedGeomType = feat.geometryType || localGeom.type || "";
    selSrc.setData(buildSelectionOverlayGeoJson(localGeom, resolvedGeomType, feat.properties, isMvt, isWms));
    fetchFullFeatureGeometry(feat, reqSeq);
  }
}

function dismissPulse() {
  clearSelectionState();
  emit("dismiss-popup");
}

function findFeatureGeometryInMvt(criteria: {
  id?: string | number;
  kodeRuas?: string | number;
  namaRuas?: string;
  desaName?: string;
  kecamatanName?: string;
}): {
  geojsonGeometry: any;
  extent?: [number, number, number, number];
  coordinate?: [number, number];
  layerId: string;
} | null {
  if (!mapInstance) return null;

  const targetId = criteria.id !== undefined && criteria.id !== null ? String(criteria.id).trim().toLowerCase() : "";
  const targetKode =
    criteria.kodeRuas !== undefined && criteria.kodeRuas !== null ? String(criteria.kodeRuas).trim().toLowerCase() : "";
  const norm = (val?: string | number | null) =>
    String(val ?? "")
      .toLowerCase()
      .replace(/^(kecamatan|kec\.?|desa|kelurahan)\s+/i, "")
      .replace(/[^a-z0-9]+/g, " ")
      .trim();

  const targetName = norm(criteria.namaRuas);
  const targetDesa = norm(criteria.desaName);
  const targetKec = norm(criteria.kecamatanName);

  const layerEntries = Array.from(mvtLayersMap.entries()).sort(([, ra], [, rb]) => {
    const aPoros = ra.sourceLayer === "jalan_porosdesa" ? 0 : 1;
    const bPoros = rb.sourceLayer === "jalan_porosdesa" ? 0 : 1;
    return aPoros - bPoros;
  });

  for (const [layerId, rec] of layerEntries) {
    if (rec.sourceLayer.includes("bataswilayah")) continue;

    let candidates: any[] = [];
    try {
      candidates = mapInstance.querySourceFeatures(rec.sourceId, { sourceLayer: rec.sourceLayer }) || [];
    } catch {
      candidates = [];
    }

    let exactSeed: any = null;
    let nameSeed: any = null;

    for (const f of candidates) {
      const fProps = f.properties || {};
      const fSel = extractMvtFeatureSelectionKey(fProps, f.id, rec.sourceLayer);

      if (targetId && fSel.uniqueId && fSel.uniqueId.toLowerCase() === targetId) {
        exactSeed = f;
        break;
      }

      const kodeVal = String(fProps.kode_ruas ?? fProps.KODE_RUAS ?? fProps.no_ruas ?? fProps.NO_RUAS ?? "")
        .trim()
        .toLowerCase();
      if (targetKode && kodeVal && kodeVal === targetKode) {
        const fKec = norm(fProps.kecamatan || fProps.wadmkc || fProps.nama_kecamatan);
        const fDesa = norm(fProps.desa || fProps.wadmkd || fProps.nama_desa);
        if (
          (!targetKec || !fKec || fKec.includes(targetKec) || targetKec.includes(fKec)) &&
          (!targetDesa || !fDesa || fDesa.includes(targetDesa) || targetDesa.includes(fDesa))
        ) {
          exactSeed = f;
          break;
        }
      }

      if (!nameSeed && targetName) {
        const fName = norm(fProps.nama_ruas || fProps.namobj || fProps.nama_jalan || fProps.nama);
        if (fName && (fName === targetName || fName.includes(targetName) || targetName.includes(fName))) {
          const fKec = norm(fProps.kecamatan || fProps.wadmkc || fProps.nama_kecamatan);
          const fDesa = norm(fProps.desa || fProps.wadmkd || fProps.nama_desa);
          const kecOk = !targetKec || !fKec || fKec.includes(targetKec) || targetKec.includes(fKec);
          const desaOk = !targetDesa || !fDesa || fDesa.includes(targetDesa) || targetDesa.includes(fDesa);
          if (kecOk && desaOk) {
            nameSeed = f;
          }
        }
      }
    }

    const chosen = exactSeed || nameSeed;
    if (!chosen) continue;

    const chosenSel = extractMvtFeatureSelectionKey(chosen.properties || {}, chosen.id, rec.sourceLayer);
    const fragments = chosenSel.key
      ? querySourceFragmentsForKey(rec, chosenSel.key, rec.sourceLayer)
      : chosen.geometry
        ? [chosen.geometry]
        : [];
    const geojsonGeometry = stitchGeoJsonFragments(fragments.length > 0 ? fragments : [chosen.geometry]);
    if (!geojsonGeometry) continue;

    const bbox = extractGeoJsonGeometryBbox(geojsonGeometry);
    const extent = bbox
      ? ([
          Number(bbox[0].toFixed(6)),
          Number(bbox[1].toFixed(6)),
          Number(bbox[2].toFixed(6)),
          Number(bbox[3].toFixed(6)),
        ] as [number, number, number, number])
      : undefined;
    const coordinate = extent
      ? ([
          Number(((extent[0] + extent[2]) / 2).toFixed(6)),
          Number(((extent[1] + extent[3]) / 2).toFixed(6)),
        ] as [number, number])
      : undefined;

    return { geojsonGeometry, extent, coordinate, layerId };
  }

  return null;
}

function findDesaExtentInMvt(desaName: string, kecamatanName?: string | null): [number, number, number, number] | null {
  if (!mapInstance) return null;

  const norm = (val?: string | number | null) =>
    String(val ?? "")
      .toLowerCase()
      .replace(/^(kecamatan|kec\.?|desa|kelurahan)\s+/i, "")
      .replace(/[^a-z0-9]+/g, " ")
      .trim();

  const targetDesa = norm(desaName);
  const targetKec = norm(kecamatanName);
  if (!targetDesa) return null;

  const layerEntries = Array.from(mvtLayersMap.entries()).sort(([, ra], [, rb]) => {
    const aIsDesa = ra.sourceLayer.includes("bataswilayah_desa") ? 0 : 1;
    const bIsDesa = rb.sourceLayer.includes("bataswilayah_desa") ? 0 : 1;
    return aIsDesa - bIsDesa;
  });

  for (const [layerId, rec] of layerEntries) {
    if (rec.sourceLayer.includes("bataswilayah_kecamatan") || rec.sourceLayer.includes("bataswilayah_kabupaten")) {
      continue;
    }

    let candidates: any[] = [];
    try {
      candidates = mapInstance.querySourceFeatures(rec.sourceId, { sourceLayer: rec.sourceLayer }) || [];
    } catch {
      candidates = [];
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    let matchedCount = 0;

    const activeLayer = props.activeLayers?.find((l) => l.id === layerId);
    const filterKecId =
      activeLayer?.filterKecamatanId !== null &&
      activeLayer?.filterKecamatanId !== undefined &&
      Number.isFinite(Number(activeLayer.filterKecamatanId))
        ? Number(activeLayer.filterKecamatanId)
        : null;

    for (const f of candidates) {
      const p = f.properties || {};
      const fDesa = norm(p.desa ?? p.wadmkd ?? p.nama_desa ?? (rec.sourceLayer.includes("bataswilayah_desa") ? p.namobj : ""));
      if (!fDesa || fDesa !== targetDesa) continue;

      if (targetKec) {
        const fKec = norm(p.kecamatan ?? p.wadmkc ?? p.nama_kecamatan);
        if (fKec) {
          if (fKec !== targetKec) continue;
        } else if (filterKecId !== null && p.id_kecamatan !== null && p.id_kecamatan !== undefined) {
          if (Number(p.id_kecamatan) !== filterKecId) continue;
        }
      }

      const bbox = extractGeoJsonGeometryBbox(f.geometry);
      if (bbox) {
        minX = Math.min(minX, bbox[0]);
        minY = Math.min(minY, bbox[1]);
        maxX = Math.max(maxX, bbox[2]);
        maxY = Math.max(maxY, bbox[3]);
        matchedCount++;
      }
    }

    if (matchedCount > 0 && Number.isFinite(minX)) {
      return [
        Number(minX.toFixed(6)),
        Number(minY.toFixed(6)),
        Number(maxX.toFixed(6)),
        Number(maxY.toFixed(6)),
      ];
    }
  }

  return null;
}

function getStackRankForLayer(layerId?: string | null, fallback = 2): number {
  const list = props.activeLayers || [];
  if (layerId && list.length > 0) {
    const idx = list.findIndex((l) => l.id === layerId || l.layer_name === layerId);
    if (idx >= 0) return list.length - idx;
  }
  return fallback;
}

function getIdentifyGeometryPriority(geometryType?: string): number {
  const gt = String(geometryType || "").toLowerCase();
  if (gt.includes("point")) return 3;
  if (gt.includes("line")) return 2;
  if (gt.includes("polygon")) return 1;
  return 0;
}

function compareIdentifiedFeatures(a: any, b: any): number {
  const geomDiff = getIdentifyGeometryPriority(b.geometryType) - getIdentifyGeometryPriority(a.geometryType);
  if (geomDiff !== 0) return geomDiff;

  const orderA = a.layerIndex ?? 0;
  const orderB = b.layerIndex ?? 0;
  if (orderA !== orderB) return orderB - orderA;

  const isPolyA = (a.geometryType || "").toLowerCase().includes("polygon");
  const isPolyB = (b.geometryType || "").toLowerCase().includes("polygon");
  if (!isPolyA && !isPolyB) {
    const distA = typeof a._clickDistPx === "number" ? a._clickDistPx : 0;
    const distB = typeof b._clickDistPx === "number" ? b._clickDistPx : 0;
    if (Math.abs(distA - distB) > 1.5) return distA - distB;
  }
  return 0;
}

async function performFeatureIdentify(lngLat: [number, number], clickPt: { x: number; y: number }) {
  if (!mapInstance) return;
  const currentLayers = props.activeLayers || [];

  const visibleWmsLayers = currentLayers.filter(
    (l) =>
      wmsLayersMap.has(l.id) &&
      (l.wmsUrl || l.url) &&
      (l.wmsLayerName || l.layer_name) &&
      l.visible !== false &&
      !isLayerIdentifyDisabled(l)
  );

  activeNamobj.value = null;
  isIdentifyingFeatures.value = true;
  emit("identifying-features", true);

  // 1. Direct client-side MapLibre MVT vector identify
  const vectorIdentified: IdentifiedFeature[] = [];
  const seenVectorKeys = new Set<string>();
  const getActiveLayerOrder = (layerId: string) => getStackRankForLayer(layerId, 0);

  const subLayerLookup = new Map<string, { id: string; match: ActiveLayerItem; sourceLayer: string; rec: MvtLayerRuntimeRecord }>();
  const identifiableMlIds: string[] = [];

  for (const [id, rec] of mvtLayersMap.entries()) {
    const match = currentLayers.find((l) => l.id === id);
    if (!match || match.visible === false || isLayerIdentifyDisabled(match) || isLayerIdentifyDisabled({ id })) {
      continue;
    }
    if (rec.sourceLayer.includes("bataswilayah_kabupaten")) continue;
    for (const subId of rec.identifiableLayerIds) {
      if (mapInstance.getLayer(subId)) {
        identifiableMlIds.push(subId);
        subLayerLookup.set(subId, { id, match, sourceLayer: rec.sourceLayer, rec });
      }
    }
  }

  try {
    if (identifiableMlIds.length > 0) {
      const bboxPx: [[number, number], [number, number]] = [
        [clickPt.x - 6, clickPt.y - 6],
        [clickPt.x + 6, clickPt.y + 6],
      ];
      const hitFeatures = mapInstance.queryRenderedFeatures(bboxPx, { layers: identifiableMlIds }) || [];

      for (const feature of hitFeatures) {
        const entry = subLayerLookup.get(feature.layer?.id);
        if (!entry) continue;
        const { id, match, sourceLayer, rec } = entry;
        const rawProps = feature.properties || {};
        const resolvedLayerName = resolveMvtLayerSelectionHint(match, rawProps.layer || sourceLayer);
        if (resolvedLayerName.includes("bataswilayah_kabupaten")) continue;

        const featureSel = extractMvtFeatureSelectionKey(rawProps, feature.id, resolvedLayerName);
        const fallbackBbox = extractGeoJsonGeometryBbox(feature.geometry);
        const dedupeKey = featureSel.key
          ? `${id}::${featureSel.key}`
          : `${id}::nokey::${Math.round((fallbackBbox?.[0] ?? 0) * 10000)}_${Math.round((fallbackBbox?.[1] ?? 0) * 10000)}`;
        if (seenVectorKeys.has(dedupeKey)) continue;
        seenVectorKeys.add(dedupeKey);

        const cleanProps: Record<string, any> = {};
        for (const [k, v] of Object.entries(rawProps)) {
          if (k !== "geometry" && v !== undefined && v !== null && v !== "") {
            cleanProps[k] = v;
          }
        }

        let stitchedGeom = feature.geometry || null;
        if (featureSel.key) {
          const fragments = querySourceFragmentsForKey(rec, featureSel.key, resolvedLayerName);
          if (fragments.length > 1) {
            stitchedGeom = stitchGeoJsonFragments(fragments) || stitchedGeom;
          }
        }

        const featBbox = extractGeoJsonGeometryBbox(stitchedGeom);
        const featExtent = featBbox
          ? ([
              Number(featBbox[0].toFixed(6)),
              Number(featBbox[1].toFixed(6)),
              Number(featBbox[2].toFixed(6)),
              Number(featBbox[3].toFixed(6)),
            ] as [number, number, number, number])
          : undefined;

        const geomType = String(stitchedGeom?.type || feature.geometry?.type || "LineString");
        const clickDistPx = computeGeoJsonClickPixelDistance(stitchedGeom, clickPt, mapInstance);
        const resolvedVectorId =
          featureSel.uniqueId ?? `synthetic:${id}:${vectorIdentified.length}:${Math.round(clickDistPx * 100)}`;

        vectorIdentified.push({
          id: resolvedVectorId,
          rawFeatureId: featureSel.uniqueId,
          layerId: id,
          layerName: featureSel.layerName || resolvedLayerName,
          selectionKey: featureSel.key,
          layerTitle: match.title || match.name || id,
          layerAttribution: match.instansi || match.attribution || match.agency,
          layerInstansi: match.instansi || match.attribution || match.agency,
          geometryType: geomType,
          properties: cleanProps,
          coordinate: lngLat,
          extent: featExtent,
          geojsonGeometry: stitchedGeom,
          layerIndex: getActiveLayerOrder(id),
          _clickDistPx: clickDistPx,
        } as any);
      }
    }
  } catch (err) {
    console.warn("Gagal membaca fitur MVT MapLibre:", err);
  }

  vectorIdentified.sort(compareIdentifiedFeatures);

  if (visibleWmsLayers.length === 0) {
    if (vectorIdentified.length > 0) {
      activeNamobj.value = extractNamobj(vectorIdentified[0]!.properties);
      renderSelectedFeature(vectorIdentified[0]!);
    } else {
      activeNamobj.value = null;
      renderSelectedFeature(null);
    }
    isIdentifyingFeatures.value = false;
    emit("identifying-features", false);
    emit("identify-features", vectorIdentified);
    return;
  }

  if (vectorIdentified.length > 0) {
    activeNamobj.value = extractNamobj(vectorIdentified[0]!.properties);
    renderSelectedFeature(vectorIdentified[0]!);
    emit("identify-features", vectorIdentified);
  }

  // 2. Parallel GeoServer WMS GetFeatureInfo via 101x101 rotation-invariant Mercator window
  const [clickMercX, clickMercY] = lonLat4326ToMercator(lngLat);
  const mercRes512 = 40075016.68557849 / (512 * Math.pow(2, mapInstance.getZoom()));
  const halfSpan = Math.max(5, 50 * mercRes512);
  const wmsBboxParam = `${clickMercX - halfSpan},${clickMercY - halfSpan},${clickMercX + halfSpan},${clickMercY + halfSpan}`;

  const queries = visibleWmsLayers.map(async (layer) => {
    const rec = wmsLayersMap.get(layer.id);
    if (!rec) return [];

    const qLayer = rec.wmsLayerName;
    const sep = rec.baseUrl.includes("?") ? "&" : "?";
    const url =
      `${rec.baseUrl}${sep}SERVICE=WMS&VERSION=1.1.1&REQUEST=GetFeatureInfo` +
      `&FORMAT=image/png&TRANSPARENT=true` +
      `&QUERY_LAYERS=${encodeURIComponent(qLayer)}&LAYERS=${encodeURIComponent(qLayer)}` +
      `&INFO_FORMAT=application/json&FEATURE_COUNT=5&BUFFER=10` +
      `&X=50&Y=50&WIDTH=101&HEIGHT=101&SRS=EPSG:3857&BBOX=${wmsBboxParam}`;

    try {
      let data: any = null;
      try {
        data = await $http("wms/feature-info", { query: { url } });
      } catch {
        try {
          const directRes = await fetch(url, {
            signal: AbortSignal.timeout ? AbortSignal.timeout(3000) : undefined,
          });
          if (directRes.ok) {
            data = await directRes.json();
          }
        } catch {}
      }

      if (data && Array.isArray(data.features) && data.features.length > 0) {
        const layerOrder = getActiveLayerOrder(layer.id);
        const isResponse3857 = Boolean(data.crs?.properties?.name?.includes("3857"));

        return data.features.map((f: any) => {
          let featExtent: [number, number, number, number] | undefined = undefined;
          let normalizedGeom = f.geometry || undefined;
          let featureCoordinate: [number, number] = lngLat;

          if (normalizedGeom) {
            const sample = extractFirstCoordinate(normalizedGeom);
            const isCoordsMercator =
              isResponse3857 || Boolean(sample && (Math.abs(sample[0]) > 180 || Math.abs(sample[1]) > 90));
            if (isCoordsMercator) {
              normalizedGeom = convertGeometryMercatorTo4326(normalizedGeom);
            }
            const bbox = extractGeoJsonGeometryBbox(normalizedGeom);
            if (bbox) {
              featExtent = [
                Number(bbox[0].toFixed(6)),
                Number(bbox[1].toFixed(6)),
                Number(bbox[2].toFixed(6)),
                Number(bbox[3].toFixed(6)),
              ];
            }
            const first4326 = extractFirstCoordinate(normalizedGeom);
            const geomTypeLower = String(normalizedGeom.type || "").toLowerCase();
            if (geomTypeLower.includes("point") && first4326) {
              featureCoordinate = normalizeCoordLonLat(first4326);
            }
          }

          if (!featExtent && (f.bbox || data.bbox)) {
            const rawBbox = f.bbox || data.bbox;
            if (Array.isArray(rawBbox) && rawBbox.length === 4) {
              featExtent = normalizeBbox4326(rawBbox as [number, number, number, number]);
            }
          }

          return {
            id: f.id,
            layerId: layer.id,
            layerName: rec.wmsLayerName || undefined,
            layerTitle: layer.title || layer.name,
            layerAttribution: layer.instansi || layer.attribution || layer.agency,
            layerInstansi: layer.instansi || layer.attribution || layer.agency,
            geometryType: normalizedGeom?.type || f.geometry?.type,
            properties: f.properties || {},
            coordinate: featureCoordinate,
            extent: featExtent,
            geojsonGeometry: normalizedGeom,
            layerIndex: layerOrder,
          };
        });
      }
      return [];
    } catch (err) {
      console.warn(`Identify WMS gagal untuk layer [${layer.title}]:`, err);
      return [];
    }
  });

  const minDelayPromise = new Promise((resolve) => setTimeout(resolve, 250));
  const [settled] = await Promise.all([Promise.allSettled(queries), minDelayPromise]);

  const identifiedList: IdentifiedFeature[] = [...vectorIdentified];
  for (const item of settled) {
    if (item.status === "fulfilled" && Array.isArray(item.value)) {
      identifiedList.push(...item.value);
    }
  }

  identifiedList.sort(compareIdentifiedFeatures);

  if (identifiedList.length > 0) {
    activeNamobj.value = extractNamobj(identifiedList[0]!.properties);
    renderSelectedFeature(identifiedList[0]!);
  } else {
    activeNamobj.value = null;
    renderSelectedFeature(null);
  }

  isIdentifyingFeatures.value = false;
  emit("identifying-features", false);
  emit("identify-features", identifiedList);
}

function getViewport(): MapViewportPayload | null {
  if (!mapInstance) return null;
  const bounds = mapInstance.getBounds();
  const c = mapInstance.getCenter();
  const zStd = toStandardZoom(mapInstance.getZoom() ?? 10);
  if (!bounds || !c) return null;
  return {
    bbox: [
      Number(bounds.getWest().toFixed(5)),
      Number(bounds.getSouth().toFixed(5)),
      Number(bounds.getEast().toFixed(5)),
      Number(bounds.getNorth().toFixed(5)),
    ],
    zoom: Math.round(zStd * 100) / 100,
    center: [Number(c.lng.toFixed(5)), Number(c.lat.toFixed(5))],
  };
}

function emitViewportUpdate() {
  const vp = getViewport();
  if (vp) emit("update:viewport", vp);
}

function getDeviceMapPadding(snapOverride?: "min" | "peek" | "full"): [number, number, number, number] {
  if (typeof window === "undefined") return [60, 40, 60, 40];
  const isMobile = window.innerWidth < 768;
  if (isMobile) {
    const h = window.innerHeight;
    if (!props.mobileDrawerOpen && !snapOverride) {
      return [80, 24, 56, 24];
    }
    const snap = snapOverride || props.mobileSheetSnap || "min";
    const bottomPadding =
      snap === "full"
        ? Math.round(h * 0.82)
        : snap === "peek"
          ? Math.round(h * 0.5) + 28
          : Math.min(204, Math.round(h * 0.34));
    return [80, 24, bottomPadding, 24];
  }
  const isTablet = window.innerWidth >= 768 && window.innerWidth < 1024;
  const hasLeftPanel = props.leftPanelOpen !== false;
  const leftPadding = hasLeftPanel ? (isTablet ? 390 : 440) : 48;
  const rightPadding = isTablet ? 40 : 48;
  return [96, rightPadding, 56, leftPadding];
}

function resolveSafeFitPadding(
  w: number,
  h: number,
  padding: [number, number, number, number]
): { top: number; right: number; bottom: number; left: number } | null {
  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) return null;
  const availW = w - padding[1] - padding[3];
  const availH = h - padding[0] - padding[2];
  if (availW > 40 && availH > 40) {
    return { top: padding[0], right: padding[1], bottom: padding[2], left: padding[3] };
  }
  const padX = Math.min(16, Math.floor(w * 0.1));
  const padY = Math.min(16, Math.floor(h * 0.1));
  if (w - padX * 2 <= 10 || h - padY * 2 <= 10) return null;
  return { top: padY, right: padX, bottom: padY, left: padX };
}

function executeBoundsFit4326(
  bbox: [number, number, number, number],
  options: { maxZoom256?: number; duration?: number; snapOverride?: "min" | "peek" | "full" }
): boolean {
  if (!mapInstance || !mapContainer.value) return false;
  const w = mapContainer.value.clientWidth;
  const h = mapContainer.value.clientHeight;
  const safePad = resolveSafeFitPadding(w, h, getDeviceMapPadding(options.snapOverride));
  if (!safePad) return false;

  const centerCoord: [number, number] = [(bbox[0] + bbox[2]) / 2, (bbox[1] + bbox[3]) / 2];
  const zoom5000Ml = getZoomForScaleDenominator(5000, centerCoord);
  const reqMaxMl = typeof options.maxZoom256 === "number" ? toMaplibreZoom(options.maxZoom256) : zoom5000Ml;
  const maxZoomMl = Math.min(reqMaxMl, zoom5000Ml);

  mapInstance.fitBounds(
    [
      [bbox[0], bbox[1]],
      [bbox[2], bbox[3]],
    ],
    {
      padding: safePad,
      maxZoom: maxZoomMl,
      duration: getMotionDuration(options.duration ?? 0),
    }
  );
  return true;
}

function applyInitialExtentFit(config: PendingInitialExtentFit): boolean {
  if (!mapInstance) return false;
  if (config.kind === "coord" && config.coord) {
    const cleanCoord = normalizeCoordLonLat(config.coord);
    const delta = 0.00005;
    return executeBoundsFit4326(
      [cleanCoord[0] - delta, cleanCoord[1] - delta, cleanCoord[0] + delta, cleanCoord[1] + delta],
      { maxZoom256: config.maxZoom, snapOverride: config.snapOverride }
    );
  }
  if (config.bbox && config.bbox.length === 4) {
    return executeBoundsFit4326(normalizeBbox4326(config.bbox), {
      maxZoom256: config.maxZoom,
      snapOverride: config.snapOverride,
    });
  }
  return false;
}

function applyInitialExtentOnInit() {
  if (pendingInitialExtent) {
    if (applyInitialExtentFit(pendingInitialExtent)) pendingInitialExtent = null;
    return;
  }

  const isMobile = typeof window !== "undefined" && window.innerWidth < 768;
  const snapOverride = isMobile ? "min" : undefined;

  let config: PendingInitialExtentFit | null = null;
  if (props.initialBbox && props.initialBbox.length === 4) {
    config = {
      kind: "bbox",
      bbox: props.initialBbox,
      maxZoom: props.initialZoom ? Math.round(props.initialZoom) : 18,
      snapOverride,
    };
  } else if (!props.initialCenter && props.initialZoom !== null) {
    config = {
      kind: "coord",
      coord: BOJONEGORO_CENTER,
      maxZoom: props.initialZoom,
      snapOverride,
    };
  } else if (!props.initialCenter && props.initialZoom === null) {
    const fallbackBbox =
      props.defaultBbox && props.defaultBbox.length === 4 ? props.defaultBbox : BOJONEGORO_DEFAULT_BBOX;
    config = {
      kind: "bbox",
      bbox: fallbackBbox,
      maxZoom: 15,
      snapOverride,
    };
  }

  if (!config) return;
  if (!applyInitialExtentFit(config)) {
    pendingInitialExtent = config;
  } else {
    pendingInitialExtent = null;
  }
}

function fitCoordinateWithPadding(
  coord: [number, number],
  targetZoom?: number,
  duration = 450,
  snapOverride?: "min" | "peek" | "full"
) {
  pendingInitialExtent = null;
  if (!mapInstance) return;
  const cleanCoord = normalizeCoordLonLat(coord);
  const currentZoom256 = toStandardZoom(mapInstance.getZoom() ?? 10);
  const reqZoom256 = typeof targetZoom === "number" ? targetZoom : Math.max(currentZoom256, 15);
  const delta = 0.00005;
  executeBoundsFit4326(
    [cleanCoord[0] - delta, cleanCoord[1] - delta, cleanCoord[0] + delta, cleanCoord[1] + delta],
    { maxZoom256: reqZoom256, duration, snapOverride }
  );
}

function fitFeatureExtent(
  extent: [number, number, number, number],
  maxZoom?: number,
  duration = 500,
  snapOverride?: "min" | "peek" | "full"
) {
  pendingInitialExtent = null;
  if (!mapInstance || !extent || extent.length !== 4) return;
  const normalized = normalizeBbox4326(extent);
  const [minX, minY, maxX, maxY] = normalized;
  if (Math.abs(maxX - minX) < 0.0001 && Math.abs(maxY - minY) < 0.0001) {
    const centerCoord: [number, number] = [(minX + maxX) / 2, (minY + maxY) / 2];
    fitCoordinateWithPadding(centerCoord, maxZoom, duration, snapOverride);
    return;
  }
  executeBoundsFit4326(normalized, { maxZoom256: maxZoom, duration, snapOverride });
}

function fitBbox(
  bbox: [number, number, number, number],
  targetZoom?: number,
  duration = 0,
  snapOverride?: "min" | "peek" | "full"
) {
  if (!bbox || bbox.length !== 4) return;
  if (!mapInstance) {
    pendingInitialExtent = {
      kind: "bbox",
      bbox: [...bbox] as [number, number, number, number],
      maxZoom: targetZoom ?? 18,
      snapOverride,
    };
    return;
  }
  pendingInitialExtent = null;
  executeBoundsFit4326(normalizeBbox4326(bbox), { maxZoom256: targetZoom, duration, snapOverride });
}

function setCenterAndZoom(center: [number, number], zoom?: number, animate = false) {
  pendingInitialExtent = null;
  if (!mapInstance) return;
  const cleanCoord = normalizeCoordLonLat(center);
  const zMl = typeof zoom === "number" ? toMaplibreZoom(zoom) : mapInstance.getZoom();
  if (animate) {
    mapInstance.easeTo({ center: cleanCoord, zoom: zMl, duration: getMotionDuration(300) });
  } else {
    mapInstance.jumpTo({ center: cleanCoord, zoom: zMl });
  }
}

function zoomIn() {
  if (!mapInstance) return;
  mapInstance.zoomTo((mapInstance.getZoom() ?? 10) + 1, { duration: getMotionDuration(250) });
}

function zoomOut() {
  if (!mapInstance) return;
  mapInstance.zoomTo((mapInstance.getZoom() ?? 10) - 1, { duration: getMotionDuration(250) });
}

function resetView() {
  pendingInitialExtent = null;
  if (!mapInstance) return;
  if ((mapInstance.getBearing() || 0) !== 0 || (mapInstance.getPitch() || 0) !== 0) {
    mapInstance.setBearing(0);
    mapInstance.setPitch(0);
  }
  const targetBbox =
    props.defaultBbox && props.defaultBbox.length === 4 ? props.defaultBbox : BOJONEGORO_DEFAULT_BBOX;
  const isMobile = typeof window !== "undefined" && window.innerWidth < 768;
  fitBbox(targetBbox, 15, 500, isMobile ? "min" : undefined);
}

function resetNorth() {
  if (!mapInstance) return;
  const keepTilt = Boolean(props.show3dBuildings || props.show3dTerrain);
  mapInstance.easeTo({
    bearing: 0,
    ...(keepTilt ? {} : { pitch: 0 }),
    duration: getMotionDuration(350),
  });
}

function flyTo(coordinate: [number, number], zoom = 14, snapOverride?: "min" | "peek" | "full") {
  pendingInitialExtent = null;
  if (!mapInstance) return;
  fitCoordinateWithPadding(coordinate, zoom, 600, snapOverride);
}

// ─── Cartographic Graticule Grid (WebGL Lines + Border Coordinate Labels) ────
function formatLonGrid(lon: number): string {
  const norm = ((((lon + 180) % 360) + 360) % 360) - 180;
  const abs = Math.abs(norm);
  const deg = Math.floor(abs);
  const min = Math.floor((abs - deg) * 60);
  const sec = Math.round(((abs - deg) * 60 - min) * 60);
  const hemi = norm >= 0 ? "E" : "W";
  if (sec !== 0) return `${deg}°${String(min).padStart(2, "0")}'${String(sec).padStart(2, "0")}" ${hemi}`;
  if (min !== 0) return `${deg}°${String(min).padStart(2, "0")}' ${hemi}`;
  return `${deg}° ${hemi}`;
}

function formatLatGrid(lat: number): string {
  const norm = Math.max(-90, Math.min(90, lat));
  const abs = Math.abs(norm);
  const deg = Math.floor(abs);
  const min = Math.floor((abs - deg) * 60);
  const sec = Math.round(((abs - deg) * 60 - min) * 60);
  const hemi = norm >= 0 ? "N" : "S";
  if (sec !== 0) return `${deg}°${String(min).padStart(2, "0")}'${String(sec).padStart(2, "0")}" ${hemi}`;
  if (min !== 0) return `${deg}°${String(min).padStart(2, "0")}' ${hemi}`;
  return `${deg}° ${hemi}`;
}

function pickGridStep(spanDeg: number): number {
  const STEPS = [10, 5, 2, 1, 0.5, 0.25, 0.1, 0.05, 0.025, 0.01, 0.005, 0.0025, 0.001];
  for (const s of STEPS) {
    if (spanDeg / s >= 4) return s;
  }
  return 0.001;
}

function syncCartoGrid() {
  if (!mapInstance) return;
  const lineSrc = mapInstance.getSource(GRID_LINES_SOURCE_ID);
  const lblSrc = mapInstance.getSource(GRID_LABELS_SOURCE_ID);
  if (!lineSrc || !lblSrc) return;

  const vis = showCartoGrid.value ? "visible" : "none";
  if (mapInstance.getLayer(GRID_LINES_LAYER_ID)) mapInstance.setLayoutProperty(GRID_LINES_LAYER_ID, "visibility", vis);
  if (mapInstance.getLayer(GRID_LABELS_LAYER_ID)) mapInstance.setLayoutProperty(GRID_LABELS_LAYER_ID, "visibility", vis);

  if (!showCartoGrid.value) {
    lineSrc.setData(emptyFeatureCollection());
    lblSrc.setData(emptyFeatureCollection());
    return;
  }

  const bounds = mapInstance.getBounds();
  if (!bounds) return;

  const west = bounds.getWest();
  const east = bounds.getEast();
  const south = bounds.getSouth();
  const north = bounds.getNorth();
  const span = Math.max(east - west, north - south, 0.002);
  const step = pickGridStep(span);

  const lineFeatures: GeoJSON.Feature[] = [];
  const labelFeatures: GeoJSON.Feature[] = [];

  const startLon = Math.floor(west / step) * step;
  const endLon = Math.ceil(east / step) * step;
  const startLat = Math.floor(south / step) * step;
  const endLat = Math.ceil(north / step) * step;

  const latLabelEdgeLon = east - (east - west) * 0.04;
  const lonLabelEdgeLat = south + (north - south) * 0.08;

  for (let lon = startLon; lon <= endLon + step * 0.1; lon += step) {
    lineFeatures.push({
      type: "Feature",
      geometry: {
        type: "LineString",
        coordinates: [
          [lon, Math.max(-85, startLat - step)],
          [lon, Math.min(85, endLat + step)],
        ],
      },
      properties: {},
    });
    if (lon > west && lon < east) {
      labelFeatures.push({
        type: "Feature",
        geometry: { type: "Point", coordinates: [lon, lonLabelEdgeLat] },
        properties: { label: formatLonGrid(lon), axis: "lon" },
      });
    }
  }

  for (let lat = startLat; lat <= endLat + step * 0.1; lat += step) {
    if (lat < -85 || lat > 85) continue;
    lineFeatures.push({
      type: "Feature",
      geometry: {
        type: "LineString",
        coordinates: [
          [startLon - step, lat],
          [endLon + step, lat],
        ],
      },
      properties: {},
    });
    if (lat > south && lat < north) {
      labelFeatures.push({
        type: "Feature",
        geometry: { type: "Point", coordinates: [latLabelEdgeLon, lat] },
        properties: { label: formatLatGrid(lat), axis: "lat" },
      });
    }
  }

  lineSrc.setData({ type: "FeatureCollection", features: lineFeatures });
  lblSrc.setData({ type: "FeatureCollection", features: labelFeatures });
}

function updateCartoGridTheme() {
  if (!mapInstance) return;
  const isDark = document.documentElement.classList.contains("dark");
  if (mapInstance.getLayer(GRID_LINES_LAYER_ID)) {
    mapInstance.setPaintProperty(
      GRID_LINES_LAYER_ID,
      "line-color",
      isDark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.4)"
    );
  }
  if (mapInstance.getLayer(GRID_LABELS_LAYER_ID)) {
    mapInstance.setPaintProperty(GRID_LABELS_LAYER_ID, "text-color", isDark ? "#f1f5f9" : "#1e293b");
    mapInstance.setPaintProperty(
      GRID_LABELS_LAYER_ID,
      "text-halo-color",
      isDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)"
    );
  }
}

function toggleCartoGrid(forceState?: boolean) {
  showCartoGrid.value = typeof forceState === "boolean" ? forceState : !showCartoGrid.value;
  syncCartoGrid();
  return showCartoGrid.value;
}

function setDeferLabelsOnMove(forceState?: boolean) {
  const next = typeof forceState === "boolean" ? forceState : !isDeferLabelsActive.value;
  isDeferLabelsActive.value = next;
  return isDeferLabelsActive.value;
}

// ─── Geolocation Live Tracking + Compass Cone ─────────────────────────────────
function buildCirclePolygon4326(center: [number, number], radiusMeters: number, steps = 48): GeoJSON.Feature {
  const [lon, lat] = center;
  const rad = Math.max(1, radiusMeters);
  const latRad = (lat * Math.PI) / 180;
  const dLat = (rad / 6378137) * (180 / Math.PI);
  const dLon = dLat / Math.max(0.15, Math.cos(latRad));
  const ring: number[][] = [];
  for (let i = 0; i <= steps; i++) {
    const theta = (i / steps) * Math.PI * 2;
    ring.push([lon + Math.cos(theta) * dLon, lat + Math.sin(theta) * dLat]);
  }
  return {
    type: "Feature",
    geometry: { type: "Polygon", coordinates: [ring] },
    properties: {},
  };
}

function ensureHeadingBeamImage() {
  if (!mapInstance || geoHeadingImageLoaded || mapInstance.hasImage(GEO_HEADING_IMAGE_ID)) {
    geoHeadingImageLoaded = true;
    return;
  }
  const size = 90;
  const canvas = document.createElement("canvas");
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext("2d");
  if (!ctx) return;

  const grad = ctx.createRadialGradient(45, 45, 0, 45, 45, 42);
  grad.addColorStop(0, "rgba(26, 115, 232, 0.45)");
  grad.addColorStop(0.6, "rgba(59, 130, 246, 0.18)");
  grad.addColorStop(1, "rgba(96, 165, 250, 0)");

  ctx.beginPath();
  ctx.moveTo(45, 45);
  ctx.lineTo(20, 6);
  ctx.arc(45, 45, 45, -Math.PI * 0.68, -Math.PI * 0.32);
  ctx.closePath();
  ctx.fillStyle = grad;
  ctx.fill();

  const imgData = ctx.getImageData(0, 0, size, size);
  mapInstance.addImage(GEO_HEADING_IMAGE_ID, imgData, { pixelRatio: 1 });
  geoHeadingImageLoaded = true;
}

function setGeoVisibility(visible: boolean) {
  if (!mapInstance) return;
  const vis = visible ? "visible" : "none";
  for (const id of [GEO_ACCURACY_FILL_ID, GEO_ACCURACY_LINE_ID, GEO_DOT_GLOW_ID, GEO_DOT_RING_ID, GEO_DOT_CORE_ID]) {
    if (mapInstance.getLayer(id)) mapInstance.setLayoutProperty(id, "visibility", vis);
  }
  if (mapInstance.getLayer(GEO_HEADING_SYMBOL_ID)) {
    const showHeading = visible && userHeading.value !== null && userHeading.value !== undefined;
    mapInstance.setLayoutProperty(GEO_HEADING_SYMBOL_ID, "visibility", showHeading ? "visible" : "none");
  }
}

function updateGeoOverlayPosition(coord: [number, number], accuracyMeters: number) {
  if (!mapInstance) return;
  const ptSrc = mapInstance.getSource(GEO_POINT_SOURCE_ID);
  const accSrc = mapInstance.getSource(GEO_ACCURACY_SOURCE_ID);
  if (ptSrc && typeof ptSrc.setData === "function") {
    ptSrc.setData({
      type: "FeatureCollection",
      features: [
        {
          type: "Feature",
          geometry: { type: "Point", coordinates: coord },
          properties: { heading: userHeading.value ?? 0 },
        },
      ],
    });
  }
  if (accSrc && typeof accSrc.setData === "function") {
    accSrc.setData({
      type: "FeatureCollection",
      features: [buildCirclePolygon4326(coord, accuracyMeters)],
    });
  }
}

function updateHeadingDisplay(heading: number | null) {
  if (!mapInstance) return;
  if (mapInstance.getLayer(GEO_HEADING_SYMBOL_ID)) {
    const visible = isUserLocationVisible.value && heading !== null && heading !== undefined;
    mapInstance.setLayoutProperty(GEO_HEADING_SYMBOL_ID, "visibility", visible ? "visible" : "none");
    if (heading !== null && heading !== undefined) {
      mapInstance.setLayoutProperty(GEO_HEADING_SYMBOL_ID, "icon-rotate", heading);
    }
  }
}

function easeOutCubic(t: number): number {
  return 1 - Math.pow(1 - t, 3);
}

function animateGeoOverlayTo(targetCoord: [number, number], accuracyMeters: number) {
  geoAnimFrom = userLocationCoords.value ?? targetCoord;
  geoAnimTo = targetCoord;
  geoAnimStartTime = null;
  if (geoAnimFrameId !== null) cancelAnimationFrame(geoAnimFrameId);

  function step(timestamp: number) {
    if (!geoAnimStartTime) geoAnimStartTime = timestamp;
    const elapsed = timestamp - geoAnimStartTime;
    const t = Math.min(elapsed / GEO_ANIM_DURATION, 1);
    const ease = easeOutCubic(t);

    if (geoAnimFrom && geoAnimTo) {
      const lng = geoAnimFrom[0] + (geoAnimTo[0] - geoAnimFrom[0]) * ease;
      const lat = geoAnimFrom[1] + (geoAnimTo[1] - geoAnimFrom[1]) * ease;
      updateGeoOverlayPosition([lng, lat], accuracyMeters);
    }

    if (t < 1) {
      geoAnimFrameId = requestAnimationFrame(step);
    } else {
      geoAnimFrameId = null;
    }
  }
  geoAnimFrameId = requestAnimationFrame(step);
}

function startCompassTracking() {
  if (!window.DeviceOrientationEvent) return;
  const handleOrientation = (event: any) => {
    let heading: number | null = null;
    if (typeof event.webkitCompassHeading === "number") {
      heading = event.webkitCompassHeading;
    } else if (typeof event.alpha === "number") {
      heading = (360 - event.alpha) % 360;
    }
    if (heading !== null && !Number.isNaN(heading)) {
      userHeading.value = Math.round(heading);
      updateHeadingDisplay(userHeading.value);
    }
  };

  if (typeof (DeviceOrientationEvent as any).requestPermission === "function") {
    (DeviceOrientationEvent as any)
      .requestPermission()
      .then((permissionState: string) => {
        if (permissionState === "granted") {
          window.addEventListener("deviceorientation", handleOrientation, true);
          orientationListener = handleOrientation;
        }
      })
      .catch((err: any) => console.warn("Izin kompas orientasi:", err));
  } else {
    window.addEventListener("deviceorientationabsolute", handleOrientation, true);
    window.addEventListener("deviceorientation", handleOrientation, true);
    orientationListener = handleOrientation;
  }
}

function stopLiveTracking() {
  hasUserLocation.value = false;
  isUserLocationVisible.value = false;
  userSpeed.value = null;
  userHeading.value = null;
  userAccuracy.value = null;
  lastReportedCoords = null;
  isFirstLocationLock = true;

  if (geoAnimFrameId !== null) {
    cancelAnimationFrame(geoAnimFrameId);
    geoAnimFrameId = null;
  }
  geoAnimFrom = null;
  geoAnimTo = null;
  geoAnimStartTime = null;
  setGeoVisibility(false);

  if (geolocationWatchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(geolocationWatchId);
    geolocationWatchId = null;
  }
  if (orientationListener) {
    window.removeEventListener("deviceorientationabsolute", orientationListener, true);
    window.removeEventListener("deviceorientation", orientationListener, true);
    orientationListener = null;
  }
}

function startLiveTracking() {
  if (!navigator.geolocation) {
    console.warn("Geolocation tidak didukung oleh browser ini.");
    return;
  }
  isLocating.value = true;
  isFirstLocationLock = true;
  ensureHeadingBeamImage();

  geolocationWatchId = navigator.geolocation.watchPosition(
    (pos) => {
      isLocating.value = false;
      hasUserLocation.value = true;
      const newCoords: [number, number] = [pos.coords.longitude, pos.coords.latitude];
      const accuracy = pos.coords.accuracy || 10;
      userAccuracy.value = Math.round(accuracy);

      const rawSpeed = pos.coords.speed;
      if (typeof rawSpeed === "number" && !Number.isNaN(rawSpeed) && rawSpeed > 0.5) {
        userSpeed.value = Math.round(rawSpeed * 3.6);
      } else {
        userSpeed.value = 0;
      }

      if (typeof pos.coords.heading === "number" && !Number.isNaN(pos.coords.heading) && userHeading.value === null) {
        userHeading.value = Math.round(pos.coords.heading);
        updateHeadingDisplay(userHeading.value);
      }

      let shouldUpdatePosition = false;
      if (isFirstLocationLock || !lastReportedCoords) {
        shouldUpdatePosition = true;
      } else {
        const distanceMoved = getDistanceMeters(lastReportedCoords, newCoords);
        if (userSpeed.value && userSpeed.value >= 3) {
          shouldUpdatePosition = distanceMoved >= 2;
        } else if (accuracy > 30) {
          shouldUpdatePosition = distanceMoved > 25;
        } else {
          shouldUpdatePosition = distanceMoved >= 4;
        }
      }

      if (shouldUpdatePosition) {
        lastReportedCoords = newCoords;
        userLocationCoords.value = newCoords;
        if (!mapInstance) return;

        if (isFirstLocationLock) {
          updateGeoOverlayPosition(newCoords, accuracy);
        } else {
          animateGeoOverlayTo(newCoords, accuracy);
        }
        isUserLocationVisible.value = true;
        setGeoVisibility(true);
        updateHeadingDisplay(userHeading.value);

        if (isFirstLocationLock) {
          const isMobile = typeof window !== "undefined" && window.innerWidth < 768;
          const targetZoom256 = Math.max(toStandardZoom(mapInstance.getZoom() || 10), 16);
          if (isMobile) {
            fitCoordinateWithPadding(newCoords, targetZoom256, 700);
          } else {
            mapInstance.easeTo({
              center: newCoords,
              zoom: toMaplibreZoom(targetZoom256),
              duration: getMotionDuration(700),
            });
          }
          isFirstLocationLock = false;
        }
      }
    },
    (err) => {
      isLocating.value = false;
      console.warn("Gagal membaca GPS live tracking:", err.message);
    },
    { enableHighAccuracy: true, maximumAge: 1000, timeout: 10000 }
  );

  startCompassTracking();
}

function locateUser() {
  if (!import.meta.client) return;
  if (hasUserLocation.value) {
    stopLiveTracking();
    return;
  }
  startLiveTracking();
}

// ─── DOM Pin & Geopark Markers (maplibregl.Marker) ────────────────────────────
const FIRE_MARKER_SVG =
  '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 1.5c.9 3.6 5.6 5.6 5.6 11.2A5.6 5.6 0 0 1 12 22.5a5.6 5.6 0 0 1-5.6-9.8c.3 1.4 1 2.2 1.9 2.6C8 10.6 9.8 4.2 12 1.5z" fill="#ea580c" stroke="#ffffff" stroke-width="1.4" stroke-linejoin="round"/><path d="M12 11.5c.5 1.8 2.6 2.7 2.6 5a2.6 2.6 0 0 1-5.2 0c0-1.2.5-1.9 1.2-2.5.2.6.5 1 .9 1.2-.3-1.5-.2-2.8.5-3.7z" fill="#fcd34d"/></svg>';
const PIN_MARKER_SVG =
  '<svg viewBox="0 0 24 32" aria-hidden="true" focusable="false"><path d="M12 1.5C6.8 1.5 2.5 5.8 2.5 11c0 7.2 9.5 19.5 9.5 19.5S21.5 18.2 21.5 11c0-5.2-4.3-9.5-9.5-9.5z" fill="#dc2626" stroke="#ffffff" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="10.8" r="3.2" fill="#ffffff"/></svg>';

let pinMarker: any = null;
let pinEl: HTMLElement | null = null;

interface GeoparkMarkerEntry {
  site: GeoparkSite;
  marker: any;
  containerEl: HTMLElement;
}
let geoparkMarkerEntries: GeoparkMarkerEntry[] = [];
const activeGeoparkSite = ref<GeoparkSite | null>(props.selectedGeoparkSite || null);

function updateGeoparkMarkersActiveState() {
  const current = activeGeoparkSite.value;
  for (const entry of geoparkMarkerEntries) {
    const isMatch = Boolean(
      current &&
        (current.id === entry.site.id ||
          current.slug === entry.site.slug ||
          (current.code && current.code === entry.site.code))
    );
    const wrapperEl = entry.marker.getElement?.() || entry.containerEl;
    if (isMatch) {
      entry.containerEl.classList.add("is-active");
      if (wrapperEl?.style) wrapperEl.style.zIndex = "120";
    } else {
      entry.containerEl.classList.remove("is-active");
      if (wrapperEl?.style) wrapperEl.style.zIndex = "10";
    }
  }
}

function syncPinMarker() {
  if (!mapInstance || !maplibregl) return;
  if (props.activeTab === "geopark") {
    pinMarker?.remove();
    pinMarker = null;
    return;
  }
  const coord = props.clickedCoordinate;
  if (!coord || coord.length < 2) {
    pinMarker?.remove();
    pinMarker = null;
    return;
  }
  const cleanCoord = normalizeCoordLonLat(coord);
  if (!pinMarker) {
    pinEl = document.createElement("div");
    pinEl.className = "mr-pin-marker";
    pinEl.innerHTML = PIN_MARKER_SVG;
    pinMarker = new maplibregl.Marker({ element: pinEl, anchor: "bottom" });
  }
  pinMarker.setLngLat(cleanCoord).addTo(mapInstance);
  if (pinEl) {
    pinEl.classList.remove("is-dropping");
    void pinEl.offsetWidth;
    pinEl.classList.add("is-dropping");
  }
}

function clearGeoparkMarkers() {
  for (const entry of geoparkMarkerEntries) {
    entry.marker.remove();
  }
  geoparkMarkerEntries = [];
}

function syncGeoparkMarkers() {
  if (!mapInstance || !maplibregl) return;
  if (props.activeTab !== "geopark") {
    clearGeoparkMarkers();
    return;
  }

  const validSites = (props.geoparkSites || []).filter((s) => s.hasCoordinate);
  if (geoparkMarkerEntries.length > 0 && geoparkMarkerEntries.length === validSites.length) {
    updateGeoparkMarkersActiveState();
    return;
  }

  clearGeoparkMarkers();

  validSites.forEach((site) => {
    const container = document.createElement("div");
    container.className = "mr-geopark-marker-container";

    const label = document.createElement("div");
    label.className = "mr-geopark-holo-label";
    label.setAttribute("role", "tooltip");

    const codeSpan = document.createElement("span");
    codeSpan.className = "mr-holo-code";
    codeSpan.textContent = site.code || "SITE";

    const nameSpan = document.createElement("span");
    nameSpan.className = "mr-holo-name";
    nameSpan.textContent = site.name;

    const caret = document.createElement("div");
    caret.className = "mr-holo-caret";

    label.appendChild(codeSpan);
    label.appendChild(nameSpan);
    label.appendChild(caret);

    const handleGeoparkClick = (e: MouseEvent) => {
      e.stopPropagation();
      activeGeoparkSite.value = site;
      updateGeoparkMarkersActiveState();
      fitCoordinateWithPadding(
        [site.longitude, site.latitude],
        site.zoom || 15,
        500,
        props.mobileDrawerOpen ? "peek" : undefined
      );
      emit("select-geopark", site);
    };

    label.addEventListener("click", handleGeoparkClick);

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mr-fire-marker";
    btn.title = site.name;
    btn.setAttribute("aria-label", `Situs Geopark: ${site.name}`);
    btn.innerHTML = FIRE_MARKER_SVG;
    btn.addEventListener("click", handleGeoparkClick);

    container.appendChild(label);
    container.appendChild(btn);

    const marker = new maplibregl.Marker({ element: container, anchor: "bottom" })
      .setLngLat([site.longitude, site.latitude])
      .addTo(mapInstance);

    geoparkMarkerEntries.push({ site, marker, containerEl: container });
  });

  updateGeoparkMarkersActiveState();
}

watch(() => props.clickedCoordinate, syncPinMarker, { deep: true });
watch(() => props.geoparkSites, syncGeoparkMarkers);
watch(
  () => props.selectedGeoparkSite,
  (site) => {
    activeGeoparkSite.value = site || null;
    updateGeoparkMarkersActiveState();
  }
);
watch(
  () => props.activeTab,
  () => {
    syncPinMarker();
    syncGeoparkMarkers();
  }
);

// ─── Pointer Hover Cursor Check ───────────────────────────────────────────────
let lastHoverState = false;

function updateCursorOnHover(pt: { x: number; y: number }) {
  if (!mapInstance) return;
  const currentLayers = props.activeLayers || [];
  let hasFeature = false;

  const identifiableMlIds: string[] = [];
  for (const [id, rec] of mvtLayersMap.entries()) {
    const match = currentLayers.find((l) => l.id === id);
    if (!match || match.visible === false || isLayerIdentifyDisabled(match)) continue;
    if (isLayerIdentifyDisabled({ id }) || rec.sourceLayer.includes("bataswilayah_kabupaten")) continue;
    for (const subId of rec.identifiableLayerIds) {
      if (mapInstance.getLayer(subId)) identifiableMlIds.push(subId);
    }
  }

  if (identifiableMlIds.length > 0) {
    try {
      const box: [[number, number], [number, number]] = [
        [pt.x - 5, pt.y - 5],
        [pt.x + 5, pt.y + 5],
      ];
      const hits = mapInstance.queryRenderedFeatures(box, { layers: identifiableMlIds }) || [];
      if (hits.length > 0) hasFeature = true;
    } catch {
      hasFeature = false;
    }
  }

  if (hasFeature !== lastHoverState) {
    lastHoverState = hasFeature;
    const canvas = mapInstance.getCanvas?.();
    if (canvas) {
      canvas.style.cursor = hasFeature ? "pointer" : "";
    }
  }
}

function handleMapMouseEnter() {
  isPointerOnCanvas.value = true;
  if (mapContainer.value && document.activeElement !== mapContainer.value) {
    mapContainer.value.focus({ preventScroll: true });
  }
}

function handleMapMouseLeave() {
  isPointerOnCanvas.value = false;
  if (mapInstance) {
    const canvas = mapInstance.getCanvas?.();
    if (canvas) canvas.style.cursor = "";
    lastHoverState = false;
  }
}

// ─── Initialization & Overlay Layer Stack ─────────────────────────────────────
function buildInitialStyle(): any {
  const isDark = typeof document !== "undefined" && document.documentElement.classList.contains("dark");
  const { source: baseSource, layer: baseLayer } = buildBasemapLayerConfig(props.currentBasemap);

  const sources: Record<string, any> = {
    ...(baseSource ? { [BASEMAP_SOURCE_ID]: baseSource } : {}),
    [GRID_LINES_SOURCE_ID]: { type: "geojson", data: emptyFeatureCollection() },
    [GRID_LABELS_SOURCE_ID]: { type: "geojson", data: emptyFeatureCollection() },
    [SEL_GEOJSON_SOURCE_ID]: { type: "geojson", data: emptyFeatureCollection() },
    [GEO_ACCURACY_SOURCE_ID]: { type: "geojson", data: emptyFeatureCollection() },
    [GEO_POINT_SOURCE_ID]: { type: "geojson", data: emptyFeatureCollection() },
  };

  const layers: any[] = [
    ...(baseLayer ? [baseLayer] : []),
    {
      id: GRID_LINES_LAYER_ID,
      type: "line",
      source: GRID_LINES_SOURCE_ID,
      layout: { visibility: showCartoGrid.value ? "visible" : "none" },
      paint: {
        "line-color": isDark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.4)",
        "line-width": 1,
        "line-dasharray": [4, 4],
      },
    },
    {
      id: GRID_LABELS_LAYER_ID,
      type: "symbol",
      source: GRID_LABELS_SOURCE_ID,
      layout: {
        visibility: showCartoGrid.value ? "visible" : "none",
        "text-field": ["get", "label"],
        "text-font": ["Inter", "sans-serif"],
        "text-size": 10.5,
        "text-allow-overlap": true,
        "text-ignore-placement": true,
      },
      paint: {
        "text-color": isDark ? "#f1f5f9" : "#1e293b",
        "text-halo-color": isDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)",
        "text-halo-width": 2.5,
      },
    },
    {
      id: SEL_POLY_HALO_ID,
      type: "line",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["==", ["get", "role"], "polygon-full"],
      layout: { "line-cap": "round", "line-join": "round" },
      paint: { "line-color": "rgba(0, 0, 0, 0.9)", "line-width": 6 },
    },
    {
      id: SEL_POLY_CORE_ID,
      type: "line",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["==", ["get", "role"], "polygon-full"],
      layout: { "line-cap": "round", "line-join": "round" },
      paint: { "line-color": "#ffffff", "line-width": 2.75 },
    },
    {
      id: SEL_LINE_GLOW_ID,
      type: "line",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["==", ["get", "role"], "line"],
      layout: { "line-cap": "round", "line-join": "round" },
      paint: { "line-color": ["get", "glowColor"], "line-width": 14 },
    },
    {
      id: SEL_LINE_CASING_ID,
      type: "line",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["any", ["==", ["get", "role"], "line"], ["==", ["get", "role"], "line-wms"]],
      layout: { "line-cap": "round", "line-join": "round" },
      paint: {
        "line-color": ["case", ["==", ["get", "role"], "line-wms"], "rgba(0, 0, 0, 0.9)", ["get", "casingColor"]],
        "line-width": ["case", ["==", ["get", "role"], "line-wms"], 6.5, 8.5],
      },
    },
    {
      id: SEL_LINE_CORE_ID,
      type: "line",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["any", ["==", ["get", "role"], "line"], ["==", ["get", "role"], "line-wms"]],
      layout: { "line-cap": "round", "line-join": "round" },
      paint: {
        "line-color": ["case", ["==", ["get", "role"], "line-wms"], "#ffffff", ["get", "coreColor"]],
        "line-width": ["case", ["==", ["get", "role"], "line-wms"], 3, 5],
      },
    },
    {
      id: SEL_LINE_CENTER_ID,
      type: "line",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["==", ["get", "role"], "line"],
      layout: { "line-cap": "round", "line-join": "round" },
      paint: { "line-color": "rgba(255, 255, 255, 0.85)", "line-width": 1.75 },
    },
    {
      id: SEL_PT_OUTER_CASING_ID,
      type: "circle",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["any", ["==", ["get", "role"], "point"], ["==", ["get", "role"], "point-wms"]],
      paint: {
        "circle-radius": ["case", ["==", ["get", "role"], "point-wms"], 11, 30],
        "circle-color": ["case", ["==", ["get", "role"], "point-wms"], "rgba(255, 255, 255, 0.12)", ["get", "reticleOuterFill"]],
        "circle-stroke-color": ["case", ["==", ["get", "role"], "point-wms"], "rgba(0, 0, 0, 0.9)", ["get", "reticleOuterCasing"]],
        "circle-stroke-width": ["case", ["==", ["get", "role"], "point-wms"], 5.5, 5],
      },
    },
    {
      id: SEL_PT_OUTER_ID,
      type: "circle",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["any", ["==", ["get", "role"], "point"], ["==", ["get", "role"], "point-wms"]],
      paint: {
        "circle-radius": ["case", ["==", ["get", "role"], "point-wms"], 11, 30],
        "circle-color": "rgba(0, 0, 0, 0)",
        "circle-stroke-color": ["case", ["==", ["get", "role"], "point-wms"], "#ffffff", ["get", "coreColor"]],
        "circle-stroke-width": ["case", ["==", ["get", "role"], "point-wms"], 2.5, 2.25],
      },
    },
    {
      id: SEL_PT_MID_ID,
      type: "circle",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["==", ["get", "role"], "point"],
      paint: {
        "circle-radius": 12,
        "circle-color": ["get", "glowColor"],
        "circle-stroke-color": ["get", "reticleOuterCasing"],
        "circle-stroke-width": 3,
      },
    },
    {
      id: SEL_PT_CORE_ID,
      type: "circle",
      source: SEL_GEOJSON_SOURCE_ID,
      filter: ["any", ["==", ["get", "role"], "point"], ["==", ["get", "role"], "point-wms"]],
      paint: {
        "circle-radius": ["case", ["==", ["get", "role"], "point-wms"], 3.5, 4.5],
        "circle-color": ["case", ["==", ["get", "role"], "point-wms"], "#ffffff", ["get", "coreColor"]],
        "circle-stroke-color": ["case", ["==", ["get", "role"], "point-wms"], "rgba(0, 0, 0, 0.9)", "#ffffff"],
        "circle-stroke-width": 1.5,
      },
    },
    {
      id: GEO_ACCURACY_FILL_ID,
      type: "fill",
      source: GEO_ACCURACY_SOURCE_ID,
      layout: { visibility: "none" },
      paint: { "fill-color": "rgba(37, 99, 235, 0.12)" },
    },
    {
      id: GEO_ACCURACY_LINE_ID,
      type: "line",
      source: GEO_ACCURACY_SOURCE_ID,
      layout: { visibility: "none" },
      paint: { "line-color": "rgba(37, 99, 235, 0.38)", "line-width": 1 },
    },
    {
      id: GEO_HEADING_SYMBOL_ID,
      type: "symbol",
      source: GEO_POINT_SOURCE_ID,
      layout: {
        visibility: "none",
        "icon-image": GEO_HEADING_IMAGE_ID,
        "icon-rotation-alignment": "map",
        "icon-allow-overlap": true,
        "icon-ignore-placement": true,
      },
    },
    {
      id: GEO_DOT_GLOW_ID,
      type: "circle",
      source: GEO_POINT_SOURCE_ID,
      layout: { visibility: "none" },
      paint: { "circle-radius": 10, "circle-color": "rgba(37, 99, 235, 0.18)" },
    },
    {
      id: GEO_DOT_RING_ID,
      type: "circle",
      source: GEO_POINT_SOURCE_ID,
      layout: { visibility: "none" },
      paint: { "circle-radius": 8, "circle-color": "#ffffff" },
    },
    {
      id: GEO_DOT_CORE_ID,
      type: "circle",
      source: GEO_POINT_SOURCE_ID,
      layout: { visibility: "none" },
      paint: { "circle-radius": 6, "circle-color": "#1a73e8" },
    },
  ];

  return { version: 8 as const, sources, layers };
}

async function initMap() {
  if (!import.meta.client) return;
  await nextTick();
  if (!mapContainer.value) return;

  try {
    const [mlMod, workerMod] = await Promise.all([
      import("maplibre-gl"),
      import("maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url"),
    ]);
    await import("maplibre-gl/dist/maplibre-gl.css");

    maplibregl = (mlMod as any).default || mlMod;
    if (workerMod?.default && typeof maplibregl.setWorkerUrl === "function") {
      maplibregl.setWorkerUrl(workerMod.default);
    }

    if (!wmsPostProtocolRegistered && typeof maplibregl.addProtocol === "function") {
      wmsPostProtocolRegistered = true;
      maplibregl.addProtocol("wms-sld-post", async (params: { url: string }, abortController: AbortController) => {
        const u = new URL(params.url);
        const key = u.searchParams.get("key") || "";
        const cfg = wmsPostConfigs.get(key);
        const bboxParts = (u.searchParams.get("bbox") || "").split(",").map(Number);
        if (!cfg || bboxParts.length !== 4 || bboxParts.some((n) => !Number.isFinite(n))) {
          return { data: new ArrayBuffer(0) };
        }
        const postBody = buildWmsGetMapPostXml(
          cfg.sldXml,
          bboxParts as [number, number, number, number],
          256,
          256,
          "EPSG:3857"
        );
        const resp = await fetch(cfg.url, {
          method: "POST",
          headers: { "Content-Type": "application/xml" },
          body: postBody,
          signal: abortController.signal,
        });
        if (!resp.ok) throw new Error(`WMS POST HTTP ${resp.status}`);
        return { data: await resp.arrayBuffer() };
      });
    }

    let initialCenterCoord = BOJONEGORO_CENTER;
    if (props.initialCenter && props.initialCenter.length === 2) {
      initialCenterCoord = normalizeCoordLonLat(props.initialCenter);
    } else if (props.initialBbox && props.initialBbox.length === 4) {
      const b = normalizeBbox4326(props.initialBbox);
      initialCenterCoord = [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2];
    }
    const initialZoomMl = toMaplibreZoom(props.initialZoom ?? DEFAULT_ZOOM_256);

    mapInstance = new maplibregl.Map({
      container: mapContainer.value,
      style: buildInitialStyle(),
      center: initialCenterCoord,
      zoom: initialZoomMl,
      minZoom: 5,
      maxZoom: 19,
      maxPitch: 75,
      dragRotate: true,
      pitchWithRotate: true,
      attributionControl: false,
    });

    scaleControlInstance = new maplibregl.ScaleControl({ maxWidth: 88, unit: "metric" });
    scaleControlEl = scaleControlInstance.onAdd(mapInstance);
    if (scaleControlEl) {
      scaleControlEl.remove();
      mountScaleControlIntoTarget();
    }

    mapInstance.on("load", () => {
      if (!mapInstance) return;
      ensureHeadingBeamImage();
      applyInitialExtentOnInit();
      syncActiveLayers();
      sync3dEnvironment({
        adjustCamera: Boolean(props.show3dBuildings || props.show3dTerrain),
        justEnabledBuildings: Boolean(props.show3dBuildings),
        justEnabledTerrain: Boolean(props.show3dTerrain),
      });
      syncPinMarker();
      syncGeoparkMarkers();
      syncCartoGrid();
      updateMapCenterAndZoom();
      emitViewportUpdate();

      mapLoaded.value = true;
      emit("loaded");
    });

    mapInstance.on("movestart", () => {
      pendingInitialExtent = null;
    });

    mapInstance.on("move", () => {
      updateMapCenterAndZoom();
    });

    mapInstance.on("moveend", () => {
      emitViewportUpdate();
      updateMapCenterAndZoom();
      syncCartoGrid();
    });

    mapInstance.on("rotate", () => {
      if (!mapInstance) return;
      currentRotation.value = -(mapInstance.getBearing() || 0);
    });

    mapInstance.on("mousemove", (e: any) => {
      if (e?.lngLat) {
        const numLng = Number(e.lngLat.lng);
        const numLat = Number(e.lngLat.lat);
        livePointerCoords.value = { lng: numLng, lat: numLat };
        isPointerOnCanvas.value = true;
        emit("update:mouse-coords", `${numLat.toFixed(5)}, ${numLng.toFixed(5)}`);
      }
      if (e?.point && !mapInstance.isMoving?.()) {
        const now = typeof performance !== "undefined" ? performance.now() : Date.now();
        if (now - lastHoverCheckTime < 48 || hoverCheckFrame !== null) return;
        const pt = { x: e.point.x, y: e.point.y };
        hoverCheckFrame = requestAnimationFrame(() => {
          hoverCheckFrame = null;
          lastHoverCheckTime = typeof performance !== "undefined" ? performance.now() : Date.now();
          updateCursorOnHover(pt);
        });
      }
    });

    mapInstance.on("click", (e: any) => {
      if (isDismissing || !e?.lngLat) return;

      const isMobile = typeof window !== "undefined" && window.innerWidth < 768;
      if (isMobile && props.mobileDrawerOpen && props.mobileSheetSnap === "full") {
        emit("canvas-tap-dismiss");
        return;
      }

      const isPeekTap = isMobile && Boolean(props.mobileDrawerOpen) && props.mobileSheetSnap === "peek";
      const lng = Number(e.lngLat.lng);
      const lat = Number(e.lngLat.lat);

      if (!isPeekTap) {
        activeNamobj.value = null;
        clearSelectionState();
      }

      emit("map-click", [lng, lat], { isPeekTap });
      performFeatureIdentify([lng, lat], e.point || { x: 0, y: 0 });
    });

    setTimeout(() => {
      if (!mapLoaded.value) {
        mapLoaded.value = true;
        emit("loaded");
      }
    }, 1200);

    themeMutationObserver = new MutationObserver(() => {
      updateCartoGridTheme();
      syncActiveLayers();
      sync3dEnvironment();
      if (activeSelectedFeature.value) {
        renderSelectedFeature(activeSelectedFeature.value, { force: true });
      }
    });
    themeMutationObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"],
    });
  } catch (err) {
    console.error("Gagal menginisialisasi peta MapLibre GL JS:", err);
  }
}

function handleFullscreenChange() {
  isFullscreen.value = Boolean(document.fullscreenElement);
  if (mapInstance) {
    setTimeout(() => mapInstance.resize(), 150);
  }
}

onMounted(() => {
  initMap();
  document.addEventListener("fullscreenchange", handleFullscreenChange);

  resizeObserver = new ResizeObserver(() => {
    if (mapInstance) {
      mapInstance.resize();
      if (pendingInitialExtent && applyInitialExtentFit(pendingInitialExtent)) {
        pendingInitialExtent = null;
        updateMapCenterAndZoom();
        emitViewportUpdate();
      }
    }
  });
  if (mapContainer.value) {
    resizeObserver.observe(mapContainer.value);
  }
});

onBeforeUnmount(() => {
  pendingInitialExtent = null;
  if (dismissTimer) clearTimeout(dismissTimer);
  if (hoverCheckFrame !== null && typeof cancelAnimationFrame === "function") {
    cancelAnimationFrame(hoverCheckFrame);
    hoverCheckFrame = null;
  }
  stopLiveTracking();
  clearGeoparkMarkers();
  pinMarker?.remove();
  pinMarker = null;
  pinEl = null;

  document.removeEventListener("fullscreenchange", handleFullscreenChange);
  resizeObserver?.disconnect();
  resizeObserver = null;
  themeMutationObserver?.disconnect();
  themeMutationObserver = null;

  if (scaleControlInstance) {
    try {
      scaleControlInstance.onRemove();
    } catch {}
    scaleControlInstance = null;
    scaleControlEl = null;
  }

  wmsLayersMap.clear();
  mvtLayersMap.clear();
  wmsPostConfigs.clear();

  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});

defineExpose({
  zoomIn,
  zoomOut,
  resetView,
  resetNorth,
  flyTo,
  locateUser,
  fitBbox,
  fitCoordinateWithPadding,
  fitFeatureExtent,
  getDeviceMapPadding,
  renderSelectedFeature,
  findFeatureGeometryInMvt,
  findDesaExtentInMvt,
  setCenterAndZoom,
  getViewport,
  dismissPulse,
  toggleCartoGrid,
  setDeferLabelsOnMove,
  deferLabelsOnMove: () => isDeferLabelsActive.value,
  showCartoGrid: () => showCartoGrid.value,
  currentRotation: () => currentRotation.value,
  mapInstance: () => mapInstance,
});
</script>

<template>
  <div
    class="relative w-full h-full select-none overflow-hidden transition-colors duration-200"
    :class="currentBasemap === 'none' ? 'bg-white' : 'bg-gray-100 dark:bg-[#070b14]'"
  >
    <!-- MapLibre GL JS WebGL Target Container -->
    <div
      ref="mapContainer"
      class="w-full h-full cursor-default focus:outline-hidden transition-colors duration-200"
      :class="currentBasemap === 'none' ? 'bg-white' : ''"
      tabindex="0"
      aria-label="Peta Interaktif Kabupaten Bojonegoro"
      @mouseenter="handleMapMouseEnter"
      @mouseleave="handleMapMouseLeave"
    />

    <!-- Floating Navigation Controls (Bottom Left with LeftPanel & Mobile Clearance) -->
    <div
      class="absolute left-3 sm:left-4 z-20 flex flex-col items-start gap-1.5 pointer-events-none transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)]"
      :class="[
        leftPanelOpen ? 'md:left-[380px] lg:left-[432px]' : 'md:left-4',
        mobileDrawerOpen && mobileSheetSnap === 'full'
          ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
          : mobileDrawerOpen && mobileSheetSnap === 'peek'
            ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4 md:opacity-100'
            : mobileDrawerOpen && mobileSheetSnap === 'min'
              ? 'bottom-[calc(156px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
              : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100 pointer-events-auto'
      ]"
    >
      <MapControls
        align="left"
        :is-locating="isLocating"
        :has-location="hasUserLocation"
        :speed="userSpeed"
        :heading="userHeading"
        :accuracy="userAccuracy"
        :rotation="currentRotation"
        @locate="locateUser"
        @zoom-in="zoomIn"
        @zoom-out="zoomOut"
        @reset-north="resetNorth"
      />
    </div>

    <!-- Floating Scale Bar & Live Cursor Coordinates -->
    <MapScaleCoordinateBar
      :active-coords="activeDisplayCoords.coords"
      :is-live-cursor="activeDisplayCoords.isLiveCursor"
      :scale-ratio="currentScaleRatio"
      :left-panel-open="leftPanelOpen"
      :mobile-drawer-open="mobileDrawerOpen"
      :mobile-sheet-snap="mobileSheetSnap"
      :clicked-coordinate="clickedCoordinate"
      @scale-target-mounted="handleScaleTargetMounted"
      @select-scale="handleSelectScale"
    />
  </div>
</template>

<style scoped>
:deep(.maplibregl-canvas-container),
:deep(.maplibregl-canvas) {
  outline: none;
}
</style>

<style>
/* Marker elements mounted dynamically by maplibregl.Marker */
.mr-pin-marker {
  width: 20px;
  height: 28px;
  pointer-events: none;
  filter: drop-shadow(0 2px 3px rgba(15, 23, 42, 0.35));
}
.mr-pin-marker svg {
  display: block;
  width: 100%;
  height: 100%;
}
.mr-pin-marker.is-dropping svg {
  transform-origin: 50% 100%;
  animation: mr-pin-drop 240ms cubic-bezier(0.2, 0.8, 0.3, 1) both;
}
@keyframes mr-pin-drop {
  from { transform: translateY(-14px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.mr-geopark-marker-container {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  pointer-events: auto;
  user-select: none;
}

.mr-geopark-holo-label {
  display: none;
  position: absolute;
  bottom: calc(100% - 2px);
  left: 50%;
  transform: translateX(-50%) translateY(4px);
  opacity: 0;
  white-space: nowrap;
  padding: 4px 10px 4px 6px;
  border-radius: 9999px;
  font-size: 11px;
  line-height: 1.3;
  font-weight: 600;
  color: #0f172a;
  background: rgba(255, 255, 255, 0.94);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(249, 115, 22, 0.5);
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.7) inset,
    0 0 16px 2px rgba(249, 115, 22, 0.32),
    0 4px 14px rgba(15, 23, 42, 0.14);
  z-index: 60;
  pointer-events: auto;
  cursor: pointer;
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(.dark) .mr-geopark-holo-label,
.dark .mr-geopark-holo-label {
  color: #f8fafc;
  background: rgba(11, 19, 34, 0.94);
  border: 1px solid rgba(251, 146, 60, 0.6);
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.1) inset,
    0 0 18px 2px rgba(249, 115, 22, 0.42),
    0 4px 18px rgba(0, 0, 0, 0.5);
}

.mr-geopark-marker-container.is-active .mr-geopark-holo-label {
  display: flex;
  align-items: center;
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.mr-holo-caret {
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 5px solid rgba(249, 115, 22, 0.6);
}

.mr-holo-code {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1px 6px;
  margin-right: 6px;
  border-radius: 9999px;
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #c2410c;
  background: rgba(254, 215, 170, 0.7);
}

:global(.dark) .mr-holo-code,
.dark .mr-holo-code {
  color: #fed7aa;
  background: rgba(194, 65, 12, 0.45);
}

.mr-holo-name {
  display: inline-block;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mr-fire-marker {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  width: 44px;
  height: 44px;
  padding: 0 0 4px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  touch-action: manipulation;
}

.mr-fire-marker svg {
  width: 30px;
  height: 30px;
  transform-origin: 50% 100%;
  filter: drop-shadow(0 2px 2.5px rgba(15, 23, 42, 0.4));
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), filter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.mr-fire-marker:hover svg {
  transform: translateY(-2px) scale(1.08);
}

.mr-fire-marker:focus-visible {
  outline: 2px solid #ffffff;
  box-shadow: 0 0 0 4px #1d4ed8;
}

.mr-geopark-marker-container.is-active .mr-fire-marker svg {
  filter: drop-shadow(0 0 10px rgba(249, 115, 22, 0.75)) drop-shadow(0 2px 4px rgba(15, 23, 42, 0.5));
  animation: mr-fire-hop 0.95s cubic-bezier(0.3, 0.8, 0.4, 1) infinite;
}

@keyframes mr-fire-hop {
  0%, 100% { transform: translateY(0) scaleY(0.95); }
  45% { transform: translateY(-15px) scaleY(1.05); }
}

@media (prefers-reduced-motion: reduce) {
  .mr-geopark-marker-container.is-active .mr-fire-marker svg {
    animation: none;
    transform: translateY(-4px) scale(1.1);
  }
}
</style>

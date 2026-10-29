<script setup lang="ts">
import "ol/ol.css";
import MapControls from "./MapControls.vue";
import MapScaleCoordinateBar from "./MapScaleCoordinateBar.vue";
import type { ActiveLayerItem, BasemapKey } from "~/types/map-layers";
import { $http } from "~/utils/helpers";
import type { IdentifiedFeature } from "./IdentifyFeatures.vue";
import type { GeoparkSite } from "~/composables/useGeoparkSites";
import {
  isCanvasTainted,
  cropCanvasTo16_9,
  generateFallbackMapCanvas,
  type MapThumbnailOptions,
  type MapThumbnailMeta,
} from "~/composables/useMapThumbnail";

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
    activeLayers?: ActiveLayerItem[];
    mobileDrawerOpen?: boolean;
    mobileSheetSnap?: "min" | "peek" | "full";
    geoparkSites?: GeoparkSite[];
    selectedGeoparkSite?: GeoparkSite | null;
    activeTab?: string;
  }>(),
  {
    currentBasemap: "esri",
    clickedCoordinate: null,
    locationLabel: null,
    initialCenter: null,
    initialBbox: null,
    initialZoom: null,
    activeLayers: () => [],
    mobileDrawerOpen: false,
    mobileSheetSnap: "min",
    geoparkSites: () => [],
    selectedGeoparkSite: null,
    activeTab: "layers",
  }
);

const emit = defineEmits<{
  (e: "map-click", coordinate: [number, number]): void;
  (e: "canvas-tap-dismiss"): void;
  (e: "select-feature", feature: any): void;
  (e: "identify-features", features: IdentifiedFeature[]): void;
  (e: "identifying-features", loading: boolean): void;
  (e: "update:mouse-coords", coords: string): void;
  (e: "update:viewport", viewport: MapViewportPayload): void;
  (e: "loaded"): void;
  (e: "dismiss-popup"): void;
  (e: "share"): void;
  (e: "select-geopark", site: GeoparkSite): void;
}>();

const mapContainer = ref<HTMLElement | null>(null);
const scaleLineTarget = ref<HTMLElement | null>(null);
let scaleLineControlInstance: any = null;

function handleScaleTargetMounted(el: HTMLElement) {
  scaleLineTarget.value = el;
  if (scaleLineControlInstance && typeof scaleLineControlInstance.setTarget === "function") {
    scaleLineControlInstance.setTarget(el);
  }
}

// Bojonegoro coordinates: [longitude, latitude] in EPSG:4326
const BOJONEGORO_CENTER: [number, number] = [111.88, -7.15];
const DEFAULT_ZOOM = 11;

// ─── Live Cursor Coordinates & Map Scale Status Bar ──────────────────────────
const livePointerCoords = ref<{ lng: number; lat: number } | null>(null);
const currentMapCenter = ref<{ lng: number; lat: number }>({
  lng: BOJONEGORO_CENTER[0],
  lat: BOJONEGORO_CENTER[1],
});
const currentZoomLevel = ref<number>(DEFAULT_ZOOM);
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

function formatScaleNumber(denom: number): string {
  if (denom <= 0) return "1.000";
  const STANDARD_SCALES = [
    500, 1000, 2000, 2500, 5000, 10000, 20000, 25000, 50000, 100000, 200000, 250000, 500000, 1000000, 2000000, 5000000
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

function updateMapCenterAndZoom() {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const z = view.getZoom();
  if (z !== undefined) {
    currentZoomLevel.value = Math.round(z);
  }
  const center = view.getCenter();
  if (center) {
    const lonLat = olModules.toLonLat(center);
    if (lonLat && lonLat.length >= 2) {
      const lat = Number(lonLat[1]);
      const lng = Number(lonLat[0]);
      currentMapCenter.value = {
        lng: Number(lng.toFixed(5)),
        lat: Number(lat.toFixed(5)),
      };
      const res = view.getResolution();
      if (res !== undefined) {
        const latRad = (lat * Math.PI) / 180;
        const cosLat = Math.cos(latRad);
        const scaleDenominator = Math.round(res * cosLat * 3779.527559);
        currentScaleRatio.value = `1 : ${formatScaleNumber(scaleDenominator)}`;
      }
    }
  }
}

function handleSelectScale(targetDenom: number) {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const center = view.getCenter();
  if (!center) return;
  const lonLat = olModules.toLonLat(center);
  const lat = lonLat && lonLat.length >= 2 ? Number(lonLat[1]) : BOJONEGORO_CENTER[1];
  const latRad = (lat * Math.PI) / 180;
  const cosLat = Math.cos(latRad);
  const targetResolution = targetDenom / (cosLat * 3779.527559);

  view.animate({
    resolution: targetResolution,
    duration: 350,
  });
}

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
let lastReportedCoords: [number, number] | null = null;
let geolocationWatchId: number | null = null;
let orientationListener: ((e: any) => void) | null = null;
let isFirstLocationLock = true;

// Hitung jarak geografis antar dua titik koordinat (dalam meter menggunakan formula Haversine)
function getDistanceMeters(coord1: [number, number], coord2: [number, number]): number {
  const [lon1, lat1] = coord1;
  const [lon2, lat2] = coord2;
  const R = 6371e3; // Radius bumi dalam meter
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
    if (newLabel) {
      activeNamobj.value = newLabel;
    }
  }
);

watch(
  () => props.clickedCoordinate,
  (coord) => {
    if (!coord) {
      activeNamobj.value = null;
    }
  }
);

function extractNamobj(properties: Record<string, any> | undefined): string | null {
  if (!properties) return null;

  // 1. Cek langsung kunci NAMOBJ (case-insensitive)
  for (const [k, v] of Object.entries(properties)) {
    if (k.toUpperCase() === "NAMOBJ" && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }

  // 2. Cek properti nama spesifik spasial lainnya
  const prioritizedKeys = ["NAMA_RUAS", "WADMKD", "WADMKC", "WADMKK", "REMARK"];
  for (const pKey of prioritizedKeys) {
    for (const [k, v] of Object.entries(properties)) {
      if (k.toUpperCase() === pKey && v) {
        const s = String(v).trim();
        if (s && s !== "-") return s;
      }
    }
  }

  // 3. Fallback ke properti yang mengandung NAM atau TITLE
  for (const [k, v] of Object.entries(properties)) {
    const upper = k.toUpperCase();
    if ((upper.includes("NAM") || upper.includes("TITLE")) && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }

  return null;
}

let mapInstance: any = null;
let tileLayer: any = null;
let olModules: any = null;
const wmsLayersMap = new Map<string, any>();
const mvtLayersMap = new Map<string, any>();
let isDismissing = false;
let dismissTimer: ReturnType<typeof setTimeout> | null = null;

// Cartographic Graticule (Grid Kartografi & Koordinat Tepi Peta)
const showCartoGrid = ref(false);
const currentRotation = ref(0);
let graticuleLayer: any = null;
let themeMutationObserver: MutationObserver | null = null;

// Geolocation: OL VectorLayer for accuracy circle + dot (scales with zoom)
let geoVectorSource: any = null;
let geoVectorLayer: any = null;
let geoAccuracyFeature: any = null;
let geoPointFeature: any = null;
let geoHeadingFeature: any = null;

// Selection Highlight: VectorLayer dengan gaya trayek UI modern
let selectionVectorSource: any = null;
let selectionVectorLayer: any = null;

// Geolocation smooth animation state
let geoAnimFrameId: number | null = null;
let geoAnimFrom: [number, number] | null = null;
let geoAnimTo: [number, number] | null = null;
let geoAnimStartTime: number | null = null;
const GEO_ANIM_DURATION = 1200; // ms


function getBasemapSource(key: BasemapKey) {
  if (!olModules) return null;
  const { OSM, XYZ } = olModules;

  switch (key) {
    case "satellite":
      return new XYZ({
        url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        attributions: "&copy; Esri, Maxar, Earthstar Geographics",
        maxZoom: 19,
        crossOrigin: "anonymous",
      });
    case "aerial":
      return new XYZ({
        url: "https://petadasar.meritech.cloud/tile/{z}/{x}/{y}.jpg",
        attributions: "&copy; Peta Dasar ATR/BPN Aerial Orthophoto",
        maxZoom: 21,
        crossOrigin: "anonymous",
      });
    case "topo":
    case "osm":
      return new OSM();
    case "rbi":
      return new XYZ({
        url: "/rbi-tiles/{z}/{y}/{x}",
        attributions: "&copy; Badan Informasi Geospasial (BIG)",
        maxZoom: 18,
        crossOrigin: "anonymous",
        tileLoadFunction: (imageTile: any, src: string) => {
          const img = imageTile.getImage();
          img.crossOrigin = "anonymous";
          let hasRetried = false;
          img.onerror = () => {
            if (!hasRetried && src.includes("/rbi-tiles/")) {
              hasRetried = true;
              img.src = src.replace(/^.*?\/rbi-tiles\//, "https://geoservices.big.go.id/rbi/rest/services/BASEMAP/Rupabumi_Indonesia/MapServer/tile/");
            }
          };
          img.src = src;
        },
      });
    case "none":
      return null;
    case "esri":
    case "positron":
    case "dark":
    default:
      return new XYZ({
        url: "https://services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
        attributions: "&copy; Esri, HERE, Garmin, FAO, NOAA, USGS, EPA, NPS, NRCan",
        maxZoom: 19,
        crossOrigin: "anonymous",
      });
  }
}

watch(
  () => props.currentBasemap,
  (newBasemap) => {
    if (tileLayer && olModules) {
      if (newBasemap === "none") {
        tileLayer.setVisible(false);
        tileLayer.setSource(null);
      } else {
        const source = getBasemapSource(newBasemap);
        if (source) {
          tileLayer.setVisible(true);
          tileLayer.setSource(source);
        }
      }
    }
  }
);

watch(
  () => props.clickedCoordinate,
  (coord) => {
    if (!coord && selectionVectorSource) {
      selectionVectorSource.clear();
      activeSelectedFeature.value = null;
    }
  },
  { deep: true }
);

watch(
  () => props.activeLayers,
  () => {
    syncActiveWmsLayers();
  },
  { deep: true }
);

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

  // 1. Rusak Berat
  if (kondisiVal.includes("rusak berat") || kondisiVal.includes("berat")) {
    return {
      core: isDark ? "#f87171" : "#ef4444",
      glow: isDark ? "rgba(248, 113, 113, 0.48)" : "rgba(239, 68, 68, 0.35)",
      fill: isDark ? "rgba(248, 113, 113, 0.18)" : "rgba(239, 68, 68, 0.12)",
    };
  }

  // 2. Rusak Ringan / Rusak
  if (kondisiVal.includes("rusak ringan") || kondisiVal.includes("rusak")) {
    return {
      core: isDark ? "#fb923c" : "#f97316",
      glow: isDark ? "rgba(251, 146, 60, 0.48)" : "rgba(249, 115, 22, 0.35)",
      fill: isDark ? "rgba(251, 146, 60, 0.18)" : "rgba(249, 115, 22, 0.12)",
    };
  }

  // 3. Sedang
  if (kondisiVal.includes("sedang") || kondisiVal.includes("cukup")) {
    return {
      core: isDark ? "#fbbf24" : "#f59e0b",
      glow: isDark ? "rgba(251, 191, 36, 0.48)" : "rgba(245, 158, 11, 0.35)",
      fill: isDark ? "rgba(251, 191, 36, 0.18)" : "rgba(245, 158, 11, 0.12)",
    };
  }

  // 4. Baik
  if (kondisiVal.includes("baik")) {
    return {
      core: isDark ? "#34d399" : "#10b981",
      glow: isDark ? "rgba(52, 211, 153, 0.48)" : "rgba(16, 185, 129, 0.35)",
      fill: isDark ? "rgba(52, 211, 153, 0.18)" : "rgba(16, 185, 129, 0.12)",
    };
  }

  // 5. Default / Netral (Brand Cyan/Blue Bojonegoro)
  return {
    core: isDark ? "#38bdf8" : "#0284c7",
    glow: isDark ? "rgba(56, 189, 248, 0.48)" : "rgba(2, 132, 199, 0.32)",
    fill: isDark ? "rgba(56, 189, 248, 0.16)" : "rgba(2, 132, 199, 0.12)",
  };
}

function createModernTransitStyle(geometryType?: string, properties?: Record<string, any>) {
  if (!olModules) return null;
  const { OlStyle, Stroke, Fill, CircleStyle } = olModules;
  const isDark = typeof document !== "undefined" && document.documentElement.classList.contains("dark");
  const type = (geometryType || "").toLowerCase();
  const theme = getConditionColors(properties, isDark);

  if (type.includes("line")) {
    return [
      // 1. Underlay Shadow Glow lembut berpendar (Warna mengikuti kondisi jalan)
      new OlStyle({
        stroke: new Stroke({
          color: theme.glow,
          width: 14,
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 1010,
      }),
      // 2. Kontras Halo Casing (Pemisah tegas berlatar gelap/putih)
      new OlStyle({
        stroke: new Stroke({
          color: isDark ? "#070b14" : "#ffffff",
          width: 8.5,
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 1011,
      }),
      // 3. Core Route Solid (Warna kondisi solid tanpa pola putus-putus)
      new OlStyle({
        stroke: new Stroke({
          color: theme.core,
          width: 5,
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 1012,
      }),
      // 4. Center Illumination Core (Garis solid poros tengah untuk kedalaman 3D gloss)
      new OlStyle({
        stroke: new Stroke({
          color: "rgba(255, 255, 255, 0.85)",
          width: 1.75,
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 1013,
      }),
    ];
  }

  if (type.includes("polygon")) {
    return [
      // 1. Halo Casing & Ambient Fill Lembut
      new OlStyle({
        stroke: new Stroke({
          color: isDark ? "rgba(7, 11, 20, 0.95)" : "rgba(255, 255, 255, 0.95)",
          width: 6,
          lineCap: "round",
          lineJoin: "round",
        }),
        fill: new Fill({
          color: theme.fill,
        }),
        zIndex: 1010,
      }),
      // 2. Underlay Shadow Glow Perimeter
      new OlStyle({
        stroke: new Stroke({
          color: theme.glow,
          width: 8,
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 1011,
      }),
      // 3. Vibrant Solid Perimeter (Warna kondisi solid tanpa pola putus-putus)
      new OlStyle({
        stroke: new Stroke({
          color: theme.core,
          width: 3.5,
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 1012,
      }),
    ];
  }

  // Point / Titik (Indikator Transit Node & Garis Radius Luar untuk Titik WMS)
  return [
    // 1. Lingkaran Garis Radius Luar Casing (Outer Radius Halo Reticle)
    new OlStyle({
      image: new CircleStyle({
        radius: 30,
        stroke: new Stroke({
          color: isDark ? "rgba(7, 11, 20, 0.95)" : "rgba(255, 255, 255, 0.95)",
          width: 5,
        }),
        fill: new Fill({
          color: isDark ? "rgba(37, 99, 235, 0.12)" : "rgba(37, 99, 235, 0.08)",
        }),
      }),
      zIndex: 1010,
    }),
    // 2. Lingkaran Garis Radius Luar Solid (Outer Radius Line Berdasarkan Warna Tema)
    new OlStyle({
      image: new CircleStyle({
        radius: 30,
        stroke: new Stroke({
          color: theme.core,
          width: 2.25,
        }),
      }),
      zIndex: 1011,
    }),
    // 3. Ring Pembatas Intermediet (Mid Range Halo Ring)
    new OlStyle({
      image: new CircleStyle({
        radius: 12,
        stroke: new Stroke({
          color: isDark ? "rgba(7, 11, 20, 0.9)" : "rgba(255, 255, 255, 0.95)",
          width: 3,
        }),
        fill: new Fill({
          color: theme.glow,
        }),
      }),
      zIndex: 1012,
    }),
    // 4. Center Focal Point Dot (Titik Pusat Presisi Tanpa Menutupi Ikon WMS)
    new OlStyle({
      image: new CircleStyle({
        radius: 4.5,
        fill: new Fill({ color: theme.core }),
        stroke: new Stroke({ color: "#ffffff", width: 1.5 }),
      }),
      zIndex: 1013,
    }),
  ];
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

function getFeatureIdentifiers(f: any): string[] {
  if (!f) return [];
  const props = typeof f.getProperties === "function" ? f.getProperties() : f.properties || {};
  const ids: string[] = [];

  const rawId = typeof f.getId === "function" ? f.getId() : f.id;
  if (rawId !== undefined && rawId !== null && String(rawId).trim() !== "" && !String(rawId).startsWith("undefined")) {
    ids.push(String(rawId).trim());
  }

  const idProps = [
    "id",
    "id_segmen",
    "ID",
    "gid",
    "GID",
    "fid",
    "FID",
    "objectid",
    "OBJECTID",
    "kode_ruas",
    "KODE_RUAS",
    "no_ruas",
    "NO_RUAS",
  ];
  for (const p of idProps) {
    if (props[p] !== undefined && props[p] !== null && String(props[p]).trim() !== "") {
      ids.push(String(props[p]).trim());
    }
  }
  return ids;
}

function collectAllMvtFeatureFragments(olLayer: any, seedFeature: any): any[] {
  if (!olLayer || !seedFeature) return [];
  const seedKeys = new Set(getFeatureIdentifiers(seedFeature));
  if (seedKeys.size === 0) return [seedFeature];

  const fragments: any[] = [];
  const seenGeoms = new Set<string>();

  const addIfMatch = (f: any) => {
    if (!f) return;
    const fKeys = getFeatureIdentifiers(f);
    const isMatch = fKeys.some((k) => seedKeys.has(k));
    if (!isMatch) return;

    let geomSig = "";
    try {
      const geom = f.getGeometry?.();
      if (geom) {
        const ext = geom.getExtent?.();
        if (ext) {
          geomSig = `${Math.round(ext[0])}_${Math.round(ext[1])}_${Math.round(ext[2])}_${Math.round(ext[3])}`;
        }
      }
    } catch {}

    if (geomSig && seenGeoms.has(geomSig)) return;
    if (geomSig) seenGeoms.add(geomSig);

    fragments.push(f);
  };

  try {
    if (typeof olLayer.getFeaturesInExtent === "function" && mapInstance) {
      const extent = mapInstance.getView().calculateExtent();
      const renderedFeats = olLayer.getFeaturesInExtent(extent);
      if (Array.isArray(renderedFeats)) {
        for (const f of renderedFeats) addIfMatch(f);
      }
    }
  } catch {}

  const source = olLayer.getSource?.();
  if (source && source.sourceTiles_) {
    try {
      for (const key of Object.keys(source.sourceTiles_)) {
        const tile = source.sourceTiles_[key];
        const tileFeats = tile?.getFeatures?.();
        if (Array.isArray(tileFeats)) {
          for (const f of tileFeats) addIfMatch(f);
        }
      }
    } catch {}
  }

  return fragments.length > 0 ? fragments : [seedFeature];
}

function joinConnectingLines(lines: number[][][], tolerance = 10.0): number[][][] {
  if (lines.length <= 1) return lines;
  const remaining = lines.map((l) => [...l]);
  const joined: number[][][] = [];

  const distSq = (p1: number[], p2: number[]) => {
    const dx = p1[0] - p2[0];
    const dy = p1[1] - p2[1];
    return dx * dx + dy * dy;
  };
  const tolSq = tolerance * tolerance;

  while (remaining.length > 0) {
    let current = remaining.pop()!;
    let found = true;

    while (found) {
      found = false;
      for (let i = 0; i < remaining.length; i++) {
        const candidate = remaining[i];
        const startCur = current[0];
        const endCur = current[current.length - 1];
        const startCand = candidate[0];
        const endCand = candidate[candidate.length - 1];

        if (distSq(endCur, startCand) <= tolSq) {
          current = current.concat(candidate.slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        } else if (distSq(endCur, endCand) <= tolSq) {
          current = current.concat(candidate.slice().reverse().slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        } else if (distSq(startCur, endCand) <= tolSq) {
          current = candidate.concat(current.slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        } else if (distSq(startCur, startCand) <= tolSq) {
          current = candidate.slice().reverse().concat(current.slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        }
      }
    }
    joined.push(current);
  }

  return joined;
}

function stitchFeatureGeometries(fragments: any[], modules: any): any {
  if (!fragments || fragments.length === 0 || !modules) return null;
  if (fragments.length === 1) {
    if (modules.toGeometry) {
      try {
        const g = modules.toGeometry(fragments[0]);
        if (g) return g;
      } catch {}
    }
    return fragments[0].getGeometry?.() || null;
  }

  const sampleGeom = fragments[0].getGeometry?.();
  const sampleType = sampleGeom?.getType?.() || "";

  if (sampleType.includes("Line")) {
    const lineCoords: number[][][] = [];
    for (const f of fragments) {
      let g: any = null;
      if (modules.toGeometry) {
        try {
          g = modules.toGeometry(f);
        } catch {}
      }
      if (!g) g = f.getGeometry?.();
      if (!g) continue;

      const t = g.getType?.() || "";
      if (t === "LineString") {
        lineCoords.push(g.getCoordinates());
      } else if (t === "MultiLineString") {
        lineCoords.push(...g.getCoordinates());
      }
    }

    if (lineCoords.length > 0) {
      const joined = joinConnectingLines(lineCoords, 10.0);
      if (joined.length === 1 && modules.LineString) {
        return new modules.LineString(joined[0]);
      } else if (modules.MultiLineString) {
        return new modules.MultiLineString(joined);
      }
    }
  } else if (sampleType.includes("Polygon")) {
    const polyCoords: number[][][][] = [];
    for (const f of fragments) {
      let g: any = null;
      if (modules.toGeometry) {
        try {
          g = modules.toGeometry(f);
        } catch {}
      }
      if (!g) g = f.getGeometry?.();
      if (!g) continue;

      const t = g.getType?.() || "";
      if (t === "Polygon") {
        polyCoords.push(g.getCoordinates());
      } else if (t === "MultiPolygon") {
        polyCoords.push(...g.getCoordinates());
      }
    }

    if (polyCoords.length > 0) {
      if (polyCoords.length === 1 && modules.Polygon) {
        return new modules.Polygon(polyCoords[0]);
      } else if (modules.MultiPolygon) {
        return new modules.MultiPolygon(polyCoords);
      }
    }
  }

  if (modules.toGeometry) {
    try {
      const g = modules.toGeometry(fragments[0]);
      if (g) return g;
    } catch {}
  }
  return fragments[0].getGeometry?.() || null;
}

function renderSelectedFeature(feat: IdentifiedFeature | null) {
  activeSelectedFeature.value = feat;
  if (!selectionVectorSource || !olModules) return;
  selectionVectorSource.clear();
  if (!feat) return;

  try {
    let olGeom: any = null;

    // Kumpulkan seluruh pecahan dari layer MVT jika feature terpotong oleh batas tile
    if (feat.layerId && mvtLayersMap.has(feat.layerId)) {
      const olLayer = mvtLayersMap.get(feat.layerId);
      const fragments = collectAllMvtFeatureFragments(olLayer, feat);
      if (fragments.length > 1) {
        olGeom = stitchFeatureGeometries(fragments, olModules);
      }
    }

    if (!olGeom && feat.olGeometry) {
      olGeom = typeof feat.olGeometry.clone === "function" ? feat.olGeometry.clone() : feat.olGeometry;
    } else if (!olGeom && feat.geojsonGeometry && olModules.GeoJSON) {
      const sample = extractFirstCoordinate(feat.geojsonGeometry);
      const isMercator = sample && (Math.abs(sample[0]) > 180 || Math.abs(sample[1]) > 90);
      const geoJsonFormat = new olModules.GeoJSON();
      olGeom = geoJsonFormat.readGeometry(feat.geojsonGeometry, {
        dataProjection: isMercator ? "EPSG:3857" : "EPSG:4326",
        featureProjection: "EPSG:3857",
      });
    }

    if (!olGeom && feat.coordinate && olModules.PointGeom) {
      const mercator = olModules.fromLonLat(feat.coordinate);
      olGeom = new olModules.PointGeom(mercator);
    }

    if (olGeom && olModules.Feature) {
      const highlightFeature = new olModules.Feature({
        geometry: olGeom,
      });
      const resolvedGeomType = feat.geometryType || (typeof olGeom.getType === "function" ? olGeom.getType() : "");
      const styles = createModernTransitStyle(resolvedGeomType, feat.properties);
      if (styles) {
        highlightFeature.setStyle(styles);
      }
      selectionVectorSource.addFeature(highlightFeature);
    }
  } catch (err) {
    console.warn("Gagal merender selected feature highlight:", err);
  }
}

function refreshSelectedMvtFeature() {
  if (!activeSelectedFeature.value || !activeSelectedFeature.value.layerId || !olModules) return;
  const olLayer = mvtLayersMap.get(activeSelectedFeature.value.layerId);
  if (!olLayer) return;

  const fragments = collectAllMvtFeatureFragments(olLayer, activeSelectedFeature.value);
  if (fragments.length > 1) {
    const stitched = stitchFeatureGeometries(fragments, olModules);
    if (stitched && selectionVectorSource) {
      selectionVectorSource.clear();
      const highlightFeature = new olModules.Feature({
        geometry: stitched,
      });
      const resolvedGeomType =
        activeSelectedFeature.value.geometryType || (typeof stitched.getType === "function" ? stitched.getType() : "");
      const styles = createModernTransitStyle(resolvedGeomType, activeSelectedFeature.value.properties);
      if (styles) {
        highlightFeature.setStyle(styles);
      }
      selectionVectorSource.addFeature(highlightFeature);
    }
  }
}

function dismissPulse() {
  activeSelectedFeature.value = null;
  if (selectionVectorSource) {
    selectionVectorSource.clear();
  }
  emit("dismiss-popup");
}

// Ease-out cubic untuk animasi marker geolocation yang terasa natural
function easeOutCubic(t: number): number {
  return 1 - Math.pow(1 - t, 3);
}

// Animasikan overlay geolocation dari posisi saat ini ke posisi target secara smooth
function animateGeoOverlayTo(targetMercator: [number, number]) {
  if (!geoPointFeature) return;

  // Ambil koordinat render saat ini sebagai titik awal animasi
  const currentCoords = geoPointFeature.getGeometry()?.getCoordinates() as [number, number] | undefined;
  geoAnimFrom = currentCoords ?? targetMercator;
  geoAnimTo = targetMercator;
  geoAnimStartTime = null;

  // Batalkan animasi sebelumnya jika GPS update datang lagi sebelum selesai
  if (geoAnimFrameId !== null) cancelAnimationFrame(geoAnimFrameId);

  function step(timestamp: number) {
    if (!geoAnimStartTime) geoAnimStartTime = timestamp;
    const elapsed = timestamp - geoAnimStartTime;
    const t = Math.min(elapsed / GEO_ANIM_DURATION, 1);
    const ease = easeOutCubic(t);

    if (geoAnimFrom && geoAnimTo && geoPointFeature) {
      const x = geoAnimFrom[0] + (geoAnimTo[0] - geoAnimFrom[0]) * ease;
      const y = geoAnimFrom[1] + (geoAnimTo[1] - geoAnimFrom[1]) * ease;
      geoPointFeature.getGeometry()?.setCoordinates([x, y]);
      geoAccuracyFeature?.getGeometry()?.setCenter([x, y]);
      geoHeadingFeature?.getGeometry()?.setCoordinates([x, y]);
    }

    if (t < 1) {
      geoAnimFrameId = requestAnimationFrame(step);
    } else {
      geoAnimFrameId = null;
    }
  }

  geoAnimFrameId = requestAnimationFrame(step);
}

function zoomIn() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || DEFAULT_ZOOM) + 1, duration: 250 });
}

function zoomOut() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || DEFAULT_ZOOM) - 1, duration: 250 });
}

function resetView() {
  if (!mapInstance || !olModules) return;
  mapInstance.getView().animate({
    center: olModules.fromLonLat(BOJONEGORO_CENTER),
    zoom: DEFAULT_ZOOM,
    rotation: 0,
    duration: 500,
  });
}

function resetNorth() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  if (!view) return;
  view.animate({
    rotation: 0,
    duration: 350,
  });
}

function flyTo(coordinate: [number, number], zoom = 14, snapOverride?: "min" | "peek" | "full") {
  if (!mapInstance || !olModules) return;
  fitCoordinateWithPadding(coordinate, zoom, 600, snapOverride);
}

function locateUser() {
  if (!import.meta.client) return;

  // Jika geolocation saat ini sedang aktif, matikan (toggle off)
  if (hasUserLocation.value) {
    stopLiveTracking();
    return;
  }

  // Jika belum aktif, mulai live tracking
  startLiveTracking();
}

function updateHeadingDisplay(heading: number | null) {
  if (geoHeadingFeature && olModules?._buildHeadingStyle) {
    geoHeadingFeature.setStyle(olModules._buildHeadingStyle(heading));
  }
}

function startLiveTracking() {
  if (!navigator.geolocation) {
    console.warn("Geolocation tidak didukung oleh browser ini.");
    return;
  }

  isLocating.value = true;
  isFirstLocationLock = true;

  // 1. Live tracking posisi dan kecepatan GPS real-time dengan filter peredam jitter
  geolocationWatchId = navigator.geolocation.watchPosition(
    (pos) => {
      isLocating.value = false;
      hasUserLocation.value = true;
      const newCoords: [number, number] = [pos.coords.longitude, pos.coords.latitude];
      const accuracy = pos.coords.accuracy || 10;
      userAccuracy.value = Math.round(accuracy);

      // Kecepatan GPS (m/s diubah ke km/jam)
      const rawSpeed = pos.coords.speed;
      if (typeof rawSpeed === "number" && !isNaN(rawSpeed) && rawSpeed > 0.5) {
        userSpeed.value = Math.round(rawSpeed * 3.6);
      } else {
        userSpeed.value = 0;
      }

      // Fallback heading GPS jika bergerak dan kompas fisik belum aktif
      if (typeof pos.coords.heading === "number" && !isNaN(pos.coords.heading) && userHeading.value === null) {
        userHeading.value = Math.round(pos.coords.heading);
        updateHeadingDisplay(userHeading.value);
      }

      // ═══ FILTER PEREDAM JITTER (Mencegah Titik Melompat/Bergeser di PC) ═══
      let shouldUpdatePosition = false;

      if (isFirstLocationLock || !lastReportedCoords) {
        // Pembacaan pertama kali selalu diterima untuk mengunci posisi awal
        shouldUpdatePosition = true;
      } else {
        const distanceMoved = getDistanceMeters(lastReportedCoords, newCoords);

        // Jika kecepatan nyata terdeteksi (sedang jalan/kendaraan)
        if (userSpeed.value && userSpeed.value >= 3) {
          shouldUpdatePosition = distanceMoved >= 2;
        }
        // Jika akurasi kasar (tipikal PC / Wi-Fi / IP dengan radius > 30 meter)
        else if (accuracy > 30) {
          // Hanya update jika perpindahan fisik benar-benar jauh di luar fluktuasi sinyal Wi-Fi (> 25 meter)
          shouldUpdatePosition = distanceMoved > 25;
        }
        // Jika akurasi tinggi (GPS HP dengan radius <= 30 meter)
        else {
          // Abaikan noise satelit mikro di bawah 4 meter
          shouldUpdatePosition = distanceMoved >= 4;
        }
      }

      if (shouldUpdatePosition) {
        lastReportedCoords = newCoords;
        userLocationCoords.value = newCoords;

        if (!mapInstance || !olModules) return;
        const mercator = olModules.fromLonLat(newCoords) as [number, number];

        if (geoPointFeature) {
          // Update accuracy circle radius (koreksi proyeksi Mercator berdasarkan latitude)
          const mercatorRadius = accuracy / Math.cos((newCoords[1] * Math.PI) / 180);
          geoAccuracyFeature?.getGeometry()?.setRadius(mercatorRadius);

          // Update heading feature style jika heading tersedia
          updateHeadingDisplay(userHeading.value);

          if (isFirstLocationLock) {
            // Posisi pertama: langsung tanpa animasi
            geoPointFeature.getGeometry()?.setCoordinates(mercator);
            geoAccuracyFeature?.getGeometry()?.setCenter(mercator);
            geoHeadingFeature?.getGeometry()?.setCoordinates(mercator);
          } else {
            // Perpindahan selanjutnya: smooth lerp
            animateGeoOverlayTo(mercator);
          }
          isUserLocationVisible.value = true;
          if (geoVectorLayer && !geoVectorLayer.getVisible()) {
            geoVectorLayer.setVisible(true);
          }
        }

        // Kunci ke tengah peta hanya pada pembacaan pertama kali
        if (isFirstLocationLock) {
          const isMobile = typeof window !== "undefined" && window.innerWidth < 768;
          if (isMobile) {
            fitCoordinateWithPadding(newCoords, Math.max(mapInstance.getView().getZoom() || 11, 16), 700);
          } else {
            mapInstance.getView().animate({
              center: mercator,
              zoom: Math.max(mapInstance.getView().getZoom() || 11, 16),
              duration: 700,
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
    {
      enableHighAccuracy: true,
      maximumAge: 1000,
      timeout: 10000,
    }
  );

  // 2. Baca arah kompas perangkat mobile (DeviceOrientation)
  startCompassTracking();
}

function startCompassTracking() {
  if (!window.DeviceOrientationEvent) return;

  const handleOrientation = (event: any) => {
    let heading: number | null = null;

    // iOS Safari menggunakan webkitCompassHeading
    if (typeof event.webkitCompassHeading === "number") {
      heading = event.webkitCompassHeading;
    }
    // Android Chrome / standar (alpha terhadap True North)
    else if (typeof event.alpha === "number") {
      heading = (360 - event.alpha) % 360;
    }

    if (heading !== null && !isNaN(heading)) {
      userHeading.value = Math.round(heading);
      updateHeadingDisplay(userHeading.value);
    }
  };

  // Izin sensor khusus iOS 13+
  if (typeof (DeviceOrientationEvent as any).requestPermission === "function") {
    (DeviceOrientationEvent as any).requestPermission()
      .then((permissionState: string) => {
        if (permissionState === "granted") {
          window.addEventListener("deviceorientation", handleOrientation, true);
          orientationListener = handleOrientation;
        }
      })
      .catch((err: any) => console.warn("Izin kompas orientasi:", err));
  } else {
    // Android / browser desktop
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

  // Batalkan animasi lerp yang sedang berjalan
  if (geoAnimFrameId !== null) {
    cancelAnimationFrame(geoAnimFrameId);
    geoAnimFrameId = null;
  }
  geoAnimFrom = null;
  geoAnimTo = null;
  geoAnimStartTime = null;

  // Sembunyikan vector layer geolocation (pertahankan feature di memory agar toggle berikutnya responsif)
  if (geoVectorLayer) geoVectorLayer.setVisible(false);

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

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    mapContainer.value?.requestFullscreen().catch(() => {});
    isFullscreen.value = true;
  } else {
    document.exitFullscreen().catch(() => {});
    isFullscreen.value = false;
  }
}

async function initMap() {
  if (!import.meta.client) return;
  await nextTick();
  if (!mapContainer.value) return;

  try {
    const [
      { default: Map },
      { default: View },
      { default: TileLayer },
      { default: TileWMS },
      { default: VectorTileLayer },
      { default: VectorTileSource },
      { default: MVT },
      { default: OSM },
      { default: XYZ },
      projPkg,
      { default: Overlay },
      { default: ScaleLine },
      { defaults: defaultInteractions, MouseWheelZoom },
      { always },
      { default: VectorLayer },
      { default: VectorSource },
      { default: Feature },
      { default: PointGeom },
      { default: CircleGeom },
      { default: OlStyle },
      { default: CircleStyle },
      { default: Fill },
      { default: Stroke },
      { default: OlText },
      { default: RegularShape },
      { default: OlIcon },
      { default: Graticule },
      { default: GeoJSONFormat },
      { toFeature, toGeometry },
      { default: LineString },
      { default: MultiLineString },
      { default: Polygon },
      { default: MultiPolygon },
    ] = await Promise.all([
      import("ol/Map.js"),
      import("ol/View.js"),
      import("ol/layer/Tile.js"),
      import("ol/source/TileWMS.js"),
      import("ol/layer/VectorTile.js"),
      import("ol/source/VectorTile.js"),
      import("ol/format/MVT.js"),
      import("ol/source/OSM.js"),
      import("ol/source/XYZ.js"),
      import("ol/proj.js"),
      import("ol/Overlay.js"),
      import("ol/control/ScaleLine.js"),
      import("ol/interaction.js"),
      import("ol/events/condition.js"),
      import("ol/layer/Vector.js"),
      import("ol/source/Vector.js"),
      import("ol/Feature.js"),
      import("ol/geom/Point.js"),
      import("ol/geom/Circle.js"),
      import("ol/style/Style.js"),
      import("ol/style/Circle.js"),
      import("ol/style/Fill.js"),
      import("ol/style/Stroke.js"),
      import("ol/style/Text.js"),
      import("ol/style/RegularShape.js"),
      import("ol/style/Icon.js"),
      import("ol/layer/Graticule.js"),
      import("ol/format/GeoJSON.js"),
      import("ol/render/Feature.js"),
      import("ol/geom/LineString.js"),
      import("ol/geom/MultiLineString.js"),
      import("ol/geom/Polygon.js"),
      import("ol/geom/MultiPolygon.js"),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      TileWMS,
      VectorTileLayer,
      VectorTileSource,
      MVT,
      OlText,
      OlStyle,
      Fill,
      Stroke,
      Graticule,
      OSM,
      XYZ,
      fromLonLat: projPkg.fromLonLat,
      toLonLat: projPkg.toLonLat,
      transformExtent: projPkg.transformExtent,
      Overlay,
      ScaleLine,
      Feature,
      PointGeom,
      GeoJSON: GeoJSONFormat,
      toFeature,
      toGeometry,
      LineString,
      MultiLineString,
      Polygon,
      MultiPolygon,
    };

    tileLayer = new TileLayer({
      source: props.currentBasemap === "none" ? null : getBasemapSource(props.currentBasemap),
      visible: props.currentBasemap !== "none",
      zIndex: 0,
    });

    // Geolocation VectorLayer: accuracy circle + standard blue dot + heading beam (Google Maps style)
    geoVectorSource = new VectorSource();

    const accuracyStyle = new OlStyle({
      fill: new Fill({ color: "rgba(37, 99, 235, 0.12)" }),
      stroke: new Stroke({ color: "rgba(37, 99, 235, 0.38)", width: 1 }),
    });

    const dotStyle = [
      // Outer subtle glow halo
      new OlStyle({
        image: new CircleStyle({
          radius: 10,
          fill: new Fill({ color: "rgba(37, 99, 235, 0.18)" }),
        }),
      }),
      // Outer crisp white ring
      new OlStyle({
        image: new CircleStyle({
          radius: 8,
          fill: new Fill({ color: "#ffffff" }),
        }),
      }),
      // Inner solid Google blue core
      new OlStyle({
        image: new CircleStyle({
          radius: 6,
          fill: new Fill({ color: "#1a73e8" }),
        }),
      }),
    ];

    const buildHeadingStyle = (heading: number | null) => {
      if (heading === null || heading === undefined) return [];
      const rad = (heading * Math.PI) / 180;
      return [
        new OlStyle({
          image: new OlIcon({
            src: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 90 90"><defs><radialGradient id="g" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="%231a73e8" stop-opacity="0.45"/><stop offset="60%" stop-color="%233b82f6" stop-opacity="0.18"/><stop offset="100%" stop-color="%2360a5fa" stop-opacity="0"/></radialGradient></defs><path d="M 45 45 L 20 6 A 45 45 0 0 1 70 6 Z" fill="url(%23g)"/></svg>',
            anchor: [0.5, 0.5],
            rotation: rad,
          }),
        }),
      ];
    };

    // Accuracy circle feature
    const dummyCenter = olModules.fromLonLat(BOJONEGORO_CENTER);
    geoAccuracyFeature = new Feature(new CircleGeom(dummyCenter, 30));
    geoAccuracyFeature.setStyle(accuracyStyle);

    // Dot feature
    geoPointFeature = new Feature(new PointGeom(dummyCenter));
    geoPointFeature.setStyle(dotStyle);

    // Heading beam cone feature
    geoHeadingFeature = new Feature(new PointGeom(dummyCenter));
    geoHeadingFeature.setStyle(buildHeadingStyle(null));

    // Store the builder so startLiveTracking and compass can update heading style
    olModules._buildHeadingStyle = buildHeadingStyle;

    geoVectorSource.addFeatures([geoAccuracyFeature, geoPointFeature, geoHeadingFeature]);

    geoVectorLayer = new VectorLayer({
      source: geoVectorSource,
      zIndex: 25,
      visible: false,
    });

    // Selection Highlight VectorLayer: rendering objek terpilih dengan style trayek UI modern
    selectionVectorSource = new VectorSource();
    selectionVectorLayer = new VectorLayer({
      source: selectionVectorSource,
      zIndex: 1000,
      visible: true,
    });

    const scaleLineControl = new ScaleLine({
      target: scaleLineTarget.value || undefined,
      units: "metric",
      minWidth: 70,
      bar: false,
    });
    scaleLineControlInstance = scaleLineControl;

    // Inisialisasi posisi dan zoom dari props atau default Bojonegoro
    let initialCenter = olModules.fromLonLat(BOJONEGORO_CENTER);
    let initialZoomLevel = props.initialZoom ?? DEFAULT_ZOOM;

    if (props.initialCenter && props.initialCenter.length === 2) {
      initialCenter = olModules.fromLonLat(props.initialCenter);
    } else if (props.initialBbox && props.initialBbox.length === 4) {
      const [minLng, minLat, maxLng, maxLat] = props.initialBbox;
      initialCenter = olModules.fromLonLat([(minLng + maxLng) / 2, (minLat + maxLat) / 2]);
    }

    const overlaysList: any[] = [];

    // Pastikan scroll mouse langsung dapat melakukan zoom tanpa perlu klik fokus terlebih dahulu
    const defaultInteractionsList = defaultInteractions({
      onFocusOnly: false,
      mouseWheelZoom: false,
    });

    const mouseWheelZoomInteraction = new MouseWheelZoom({
      condition: always,
      onFocusOnly: false,
      useAnchor: true,
      duration: 150,
      timeout: 80,
    });

    // Formatters untuk koordinat pada tepi grid kartografi (Notasi ringkas E dan S)
    function formatLonGrid(lon: number): string {
      const norm = ((((lon + 180) % 360) + 360) % 360) - 180;
      const abs = Math.abs(norm);
      const deg = Math.floor(abs);
      const min = Math.floor((abs - deg) * 60);
      const sec = Math.round(((abs - deg) * 60 - min) * 60);
      const hemi = norm >= 0 ? "E" : "W";
      if (sec !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}'${String(sec).padStart(2, "0")}" ${hemi}`;
      }
      if (min !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}' ${hemi}`;
      }
      return `${deg}° ${hemi}`;
    }

    function formatLatGrid(lat: number): string {
      const norm = Math.max(-90, Math.min(90, lat));
      const abs = Math.abs(norm);
      const deg = Math.floor(abs);
      const min = Math.floor((abs - deg) * 60);
      const sec = Math.round(((abs - deg) * 60 - min) * 60);
      const hemi = norm >= 0 ? "N" : "S";
      if (sec !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}'${String(sec).padStart(2, "0")}" ${hemi}`;
      }
      if (min !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}' ${hemi}`;
      }
      return `${deg}° ${hemi}`;
    }

    const isCurrentDark = document.documentElement.classList.contains("dark");
    graticuleLayer = new Graticule({
      strokeStyle: new Stroke({
        color: isCurrentDark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.4)",
        width: 1,
        lineDash: [4, 4],
      }),
      showLabels: true,
      wrapX: false,
      zIndex: 40,
      lonLabelPosition: 0,
      latLabelPosition: 1,
      lonLabelFormatter: formatLonGrid,
      latLabelFormatter: formatLatGrid,
      lonLabelStyle: new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textBaseline: "bottom",
        textAlign: "center",
        fill: new Fill({ color: isCurrentDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isCurrentDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetY: -8,
      }),
      latLabelStyle: new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textAlign: "end",
        textBaseline: "middle",
        fill: new Fill({ color: isCurrentDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isCurrentDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetX: -8,
      }),
      visible: showCartoGrid.value,
    });

    function updateGraticuleTheme() {
      if (!graticuleLayer) return;
      const isDark = document.documentElement.classList.contains("dark");
      graticuleLayer.strokeStyle_ = new Stroke({
        color: isDark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.4)",
        width: 1,
        lineDash: [4, 4],
      });
      graticuleLayer.lonLabelStyle_ = new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textBaseline: "bottom",
        textAlign: "center",
        fill: new Fill({ color: isDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetY: -8,
      });
      graticuleLayer.latLabelStyle_ = new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textAlign: "end",
        textBaseline: "middle",
        fill: new Fill({ color: isDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetX: -8,
      });
      graticuleLayer.changed();
    }

    themeMutationObserver = new MutationObserver(() => {
      updateGraticuleTheme();
      if (activeSelectedFeature.value) {
        renderSelectedFeature(activeSelectedFeature.value);
      }
    });
    themeMutationObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"],
    });

    mapInstance = new Map({
      target: mapContainer.value,
      layers: [tileLayer, geoVectorLayer, selectionVectorLayer, graticuleLayer],
      view: new View({
        center: initialCenter,
        zoom: initialZoomLevel,
        minZoom: 6,
        maxZoom: 19,
      }),
      overlays: overlaysList,
      controls: [scaleLineControl],
      interactions: defaultInteractionsList.extend([mouseWheelZoomInteraction]),
    });

    // Sesuaikan extent jika initialBbox tersedia
    if (props.initialBbox && props.initialBbox.length === 4) {
      const extent3857 = olModules.transformExtent(props.initialBbox, "EPSG:4326", "EPSG:3857");
      mapInstance.getView().fit(extent3857, {
        size: mapInstance.getSize(),
        padding: [10, 10, 10, 10],
        maxZoom: props.initialZoom ? Math.round(props.initialZoom) : 18,
      });
    }

    syncPinMarker();
    syncGeoparkMarkers();

    // Inisialisasi posisi center dan zoom status bar awal
    updateMapCenterAndZoom();

    // Listener pergerakan dan zoom peta untuk sinkronisasi URL parameter
    mapInstance.on("moveend", () => {
      emitViewportUpdate();
      updateMapCenterAndZoom();
    });

    // Sinkronkan skala rasio peta secara langsung saat user melakukan zoom / pinch
    mapInstance.getView().on("change:resolution", () => {
      updateMapCenterAndZoom();
    });

    // Sinkronkan rotasi peta untuk indikator arah mata angin & kompas reset
    mapInstance.getView().on("change:rotation", () => {
      const rad = mapInstance.getView()?.getRotation() || 0;
      currentRotation.value = (rad * 180) / Math.PI;
    });

    // Pointer move listener for live mouse coordinates & feature hover detection
    mapInstance.on("pointermove", (evt: any) => {
      if (evt.dragging) return;
      const coords = olModules.toLonLat(evt.coordinate);
      if (coords && coords.length >= 2) {
        const numLng = Number(coords[0]);
        const numLat = Number(coords[1]);
        livePointerCoords.value = { lng: numLng, lat: numLat };
        isPointerOnCanvas.value = true;

        const lng = numLng.toFixed(5);
        const lat = numLat.toFixed(5);
        emit("update:mouse-coords", `${lat}, ${lng}`);
      }

      if (evt.pixel) {
        updateCursorOnHover(evt.pixel);
      }
    });

    // Map click handler - Query data fitur WMS HANYA saat user melakukan klik
    mapInstance.on("singleclick", (evt: any) => {
      // Abaikan klik yang berasal dari tombol tutup popup
      if (isDismissing) return;

      const isMobile = typeof window !== "undefined" && window.innerWidth < 768;
      if (isMobile && props.mobileDrawerOpen && props.mobileSheetSnap && props.mobileSheetSnap !== "min") {
        emit("canvas-tap-dismiss");
        return;
      }

      const coords = olModules.toLonLat(evt.coordinate);
      if (coords && coords.length >= 2) {
        const lng = Number(coords[0]);
        const lat = Number(coords[1]);

        activeNamobj.value = null;
        if (selectionVectorSource) {
          selectionVectorSource.clear();
        }
        activeSelectedFeature.value = null;

        emit("map-click", [lng, lat]);
        performWmsIdentify(evt.coordinate);
      }
    });

    mapLoaded.value = true;
    syncActiveWmsLayers();
    if (mapInstance) {
      mapInstance.once("rendercomplete", () => {
        emit("loaded");
      });
      setTimeout(() => {
        emit("loaded");
      }, 750);
    } else {
      emit("loaded");
    }
    emitViewportUpdate();
  } catch (err) {
    console.error("Gagal menginisialisasi peta OpenLayers:", err);
  }
}

function syncActiveWmsLayers() {
  if (!mapInstance || !olModules) return;
  const { TileLayer, TileWMS, VectorTileLayer, VectorTileSource, MVT, OlStyle, Stroke, Fill, OlText } = olModules;
  const currentLayers = props.activeLayers || [];
  const currentLayerIds = new Set(currentLayers.map((l) => l.id));
  const config = useRuntimeConfig();
  const martinBase = (config.public?.martinUrl as string) || "/martin";

  // 1. Hapus layer yang tidak lagi aktif
  for (const [id, olLayer] of wmsLayersMap.entries()) {
    if (!currentLayerIds.has(id)) {
      mapInstance.removeLayer(olLayer);
      wmsLayersMap.delete(id);
    }
  }
  for (const [id, olLayer] of mvtLayersMap.entries()) {
    if (!currentLayerIds.has(id)) {
      mapInstance.removeLayer(olLayer);
      mvtLayersMap.delete(id);
    }
  }

  // 2. Tambahkan atau perbarui status layer
  currentLayers.forEach((layer, index) => {
    const isMvt =
      layer.protocol?.toUpperCase() === "MVT" ||
      layer.source_type === "martin" ||
      Boolean(layer.mvtUrl) ||
      Boolean(layer.martinSource) ||
      (layer.layer_name && ["infrastruktur_segmen", "jalan_porosdesa", "bataswilayah_kabupaten"].includes(layer.layer_name)) ||
      (layer.url && layer.url.includes("/martin/"));

    // A. Handle Martin MVT Layer
    if (isMvt) {
      const sourceName =
        layer.martinSource ||
        layer.layer_name ||
        (layer.url ? layer.url.match(/\/martin\/([^\/]+)/)?.[1] : undefined) ||
        (layer.id.includes("segmen") ? "infrastruktur_segmen" : "jalan_porosdesa");

      const tileUrl = layer.mvtUrl || `${martinBase}/${sourceName}/{z}/{x}/{y}`;
      const isKabupaten =
        sourceName === "bataswilayah_kabupaten" ||
        layer.layer_name === "bataswilayah_kabupaten" ||
        layer.id === "01a15e28-a042-71a0-b111-ed7d9256e5bf" ||
        Boolean(layer.url && layer.url.includes("bataswilayah_kabupaten")) ||
        Boolean(layer.mvtUrl && layer.mvtUrl.includes("bataswilayah_kabupaten"));

      const isSegmen = !isKabupaten && (sourceName === "infrastruktur_segmen" || layer.id.includes("segmen"));

      // Urutan z-index dinamis: Batas wilayah kabupaten selalu di lapisan dasar (z-index 4), jalan di atasnya (z-index 10+)
      const layerZIndex = isKabupaten ? 4 : 10 + (currentLayers.length - index) * 2;
      const effectiveOpacity = typeof layer.opacity === "number" ? layer.opacity : 1;

      // ─── 1. Standar Kartografi Batas Administrasi Kabupaten (BIG / Peta RBI Dati II) ───
      const createKabupatenStyleFn = (_targetOlLayer: any) => {
        return (_feature?: any) => {
          const currentZoom = mapInstance?.getView()?.getZoom() || 0;
          const isDark = document.documentElement.classList.contains("dark");

          return [
            // Casing / underlay halo batas kabupaten (efek kartografi batas wilayah teritorial)
            new OlStyle({
              stroke: new Stroke({
                color: isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(71, 85, 105, 0.22)",
                width: currentZoom >= 13 ? 7.5 : currentZoom >= 11 ? 5.5 : 4,
                lineCap: "round",
                lineJoin: "round",
              }),
              fill: new Fill({
                color: isDark ? "rgba(148, 163, 184, 0.03)" : "rgba(71, 85, 105, 0.015)",
              }),
            }),
            // Garis utama batas kartografi kabupaten (Standar BIG: Dash-Dot-Dot-Dash warna abu-abu gelap)
            new OlStyle({
              stroke: new Stroke({
                color: isDark ? "#94a3b8" : "#475569",
                width: currentZoom >= 13 ? 2.5 : currentZoom >= 11 ? 2 : 1.75,
                lineDash: [12, 4, 2, 4, 2, 4],
                lineCap: "round",
                lineJoin: "round",
              }),
              zIndex: 4,
            }),
          ];
        };
      };

      // ─── 2. Generator Gaya MVT Ruas & Segmen Jalan Berdasarkan Kondisi ───
      const createMvtStyleFn = (targetOlLayer: any, isLayerSegmen: boolean) => {
        return (feature: any) => {
          // 1. Filter Wilayah (Kecamatan)
          const targetKec = (targetOlLayer.get("filterKecamatan") || "").trim().toLowerCase();
          if (targetKec && targetKec !== "all") {
            const rawFeatKec = String(
              feature.get("kecamatan") ||
              feature.get("WADMKC") ||
              feature.get("wadmkc") ||
              feature.get("nama_kecamatan") ||
              ""
            ).trim().toLowerCase();

            const cleanTarget = targetKec.replace(/^(kecamatan|kec\.)\s+/i, "").trim();
            const cleanFeat = rawFeatKec.replace(/^(kecamatan|kec\.)\s+/i, "").trim();

            if (!cleanFeat || (cleanFeat !== cleanTarget && !cleanFeat.includes(cleanTarget) && !cleanTarget.includes(cleanFeat))) {
              return null;
            }
          }

          // 2. Filter Status Kondisi
          const targetStatus = (targetOlLayer.get("filterStatus") || "").trim().toLowerCase();
          if (targetStatus && targetStatus !== "all") {
            const featKondisi = String(feature.get("kondisi") || feature.get("status") || "").trim().toLowerCase();
            if (!featKondisi.includes(targetStatus)) {
              return null;
            }
          }

          const kondisi = String(feature.get("kondisi") || "").toLowerCase();
          let strokeColor = isLayerSegmen ? "#10b981" : "#2563eb";

          if (kondisi.includes("rusak berat")) strokeColor = "#ef4444";
          else if (kondisi.includes("rusak ringan")) strokeColor = "#f97316";
          else if (kondisi.includes("sedang")) strokeColor = "#f59e0b";
          else if (kondisi.includes("rusak")) strokeColor = "#f97316";
          else if (kondisi.includes("baik")) strokeColor = "#10b981";

          let textStyle: any = undefined;
          const currentZoom = mapInstance?.getView()?.getZoom() || 0;
          if (currentZoom >= 13.5) {
            const labelText = String(feature.get(isLayerSegmen ? "namobj" : "nama_ruas") || feature.get("nama_ruas") || feature.get("namobj") || "");
            if (labelText && labelText !== "-") {
              textStyle = new OlText({
                text: labelText,
                font: "600 11px Inter, sans-serif",
                fill: new Fill({ color: isLayerSegmen ? "#064e3b" : "#1e3a8a" }),
                stroke: new Stroke({ color: "#ffffff", width: 3 }),
                placement: "line",
                overflow: false,
              });
            }
          }

          return new OlStyle({
            stroke: new Stroke({
              color: strokeColor,
              width: currentZoom >= 14 ? (isLayerSegmen ? 4 : 2.5) : (isLayerSegmen ? 3 : 1.75),
              lineCap: "round",
              lineJoin: "round",
            }),
            text: textStyle,
            zIndex: isLayerSegmen ? 100 : 10,
          });
        };
      };

      if (mvtLayersMap.has(layer.id)) {
        const olLayer = mvtLayersMap.get(layer.id);
        olLayer.setVisible(layer.visible ?? true);
        olLayer.setOpacity(effectiveOpacity);
        olLayer.setZIndex(layerZIndex);

        if (isKabupaten) {
          olLayer.setStyle(createKabupatenStyleFn(olLayer));
        } else {
          // Pantau jika filterKecamatan atau filterStatus berubah agar render ulang style
          const prevKec = olLayer.get("filterKecamatan");
          const prevStatus = olLayer.get("filterStatus");
          if (prevKec !== layer.filterKecamatan || prevStatus !== layer.filterStatus) {
            olLayer.set("filterKecamatan", layer.filterKecamatan);
            olLayer.set("filterStatus", layer.filterStatus);
            olLayer.setStyle(createMvtStyleFn(olLayer, isSegmen));
            olLayer.changed();
          }
        }
      } else {
        try {
          const source = new VectorTileSource({
            format: new MVT({ idProperty: "id" }),
            url: tileUrl,
            maxZoom: 20,
            minZoom: isKabupaten ? 5 : 7,
            cacheSize: 512,
          });

          source.on("tileloadend", () => {
            if (activeSelectedFeature.value && activeSelectedFeature.value.layerId === layer.id) {
              refreshSelectedMvtFeature();
            }
          });

          const olLayer = new VectorTileLayer({
            source,
            renderMode: "hybrid",
            declutter: true,
            opacity: effectiveOpacity,
            visible: layer.visible ?? true,
            zIndex: layerZIndex,
          });

          if (isKabupaten) {
            olLayer.setStyle(createKabupatenStyleFn(olLayer));
          } else {
            olLayer.set("filterKecamatan", layer.filterKecamatan);
            olLayer.set("filterStatus", layer.filterStatus);
            olLayer.setStyle(createMvtStyleFn(olLayer, isSegmen));
          }

          mapInstance.addLayer(olLayer);
          mvtLayersMap.set(layer.id, olLayer);
        } catch (err) {
          console.error(`Gagal memuat layer Martin MVT [${layer.title}]:`, err);
        }
      }
      return;
    }

    // B. Handle WMS Layer
    const effectiveWmsUrl = layer.wmsUrl || layer.url;
    const effectiveWmsLayerName = layer.wmsLayerName || layer.layer_name;

    if (effectiveWmsUrl && effectiveWmsLayerName) {
      const layerZIndex = 10 + (currentLayers.length - index) * 2;
      if (wmsLayersMap.has(layer.id)) {
        const olLayer = wmsLayersMap.get(layer.id);
        olLayer.setVisible(layer.visible);
        olLayer.setOpacity(layer.opacity ?? 1);
        olLayer.setZIndex(layerZIndex);
      } else {
        try {
          const source = new TileWMS({
            url: effectiveWmsUrl,
            crossOrigin: "anonymous",
            params: {
              LAYERS: effectiveWmsLayerName,
              TILED: true,
              VERSION: "1.1.1",
              FORMAT: "image/png",
              TRANSPARENT: true,
            },
            serverType: "geoserver",
            transition: 0,
          });

          const olLayer = new TileLayer({
            source,
            opacity: layer.opacity ?? 1,
            visible: layer.visible ?? true,
            zIndex: layerZIndex,
          });

          mapInstance.addLayer(olLayer);
          wmsLayersMap.set(layer.id, olLayer);
        } catch (err) {
          console.error(`Gagal memuat layer WMS [${layer.title || layer.name}]:`, err);
        }
      }
    }
  });
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

async function performWmsIdentify(olCoord: any) {
  if (!mapInstance || !olModules) return;
  const currentLayers = props.activeLayers || [];
  const visibleWmsLayers = currentLayers.filter(
    (l) => (l.wmsUrl || l.url) && (l.wmsLayerName || l.layer_name) && l.visible !== false && !isLayerIdentifyDisabled(l)
  );

  activeNamobj.value = null;
  isIdentifyingFeatures.value = true;
  emit("identifying-features", true);

  // 1. Cek fitur vektor langsung dari Martin MVT layer pada titik piksel klik
  const vectorIdentified: IdentifiedFeature[] = [];
  const getActiveLayerOrder = (layerId: string) => {
    const idx = currentLayers.findIndex((l) => l.id === layerId || l.layer_name === layerId);
    return idx >= 0 ? idx : 999;
  };

  try {
    const pixel = mapInstance.getPixelFromCoordinate(olCoord);
    if (pixel) {
      mapInstance.forEachFeatureAtPixel(
        pixel,
        (feature: any, layer: any) => {
          for (const [id, olLayer] of mvtLayersMap.entries()) {
            if (olLayer === layer) {
              const match = currentLayers.find((l) => l.id === id);
              if (match && (match.visible === false || isLayerIdentifyDisabled(match))) return;
              if (isLayerIdentifyDisabled({ id })) return;

              const styleFn = olLayer.getStyleFunction();
              if (styleFn) {
                const computedStyle = styleFn(feature, mapInstance.getView().getResolution());
                if (!computedStyle) return;
              }
              if (match && match.visible !== false) {
                const rawProps = feature.getProperties() || {};
                const featureId = String(feature.getId?.() || rawProps.id || "");
                if (featureId.toLowerCase().includes("bataswilayah_kabupaten")) return;

                const cleanProps: Record<string, any> = {};
                for (const [k, v] of Object.entries(rawProps)) {
                  if (k !== "geometry" && v !== undefined && v !== null && v !== "") {
                    cleanProps[k] = v;
                  }
                }

                let featExtent: [number, number, number, number] | undefined = undefined;
                let geojsonGeom: any = null;
                let olGeomInstance: any = null;

                // Kumpulkan seluruh pecahan dari layer MVT jika feature terpotong oleh batas tile
                const matchingFragments = collectAllMvtFeatureFragments(olLayer, feature);
                if (matchingFragments.length > 1) {
                  olGeomInstance = stitchFeatureGeometries(matchingFragments, olModules);
                } else if (olModules.toGeometry) {
                  try {
                    olGeomInstance = olModules.toGeometry(feature);
                  } catch (convErr) {
                    console.warn("Gagal konversi toGeometry:", convErr);
                  }
                }

                if (!olGeomInstance) {
                  const geom = feature.getGeometry?.();
                  olGeomInstance = geom;
                }

                if (olGeomInstance) {
                  const ext3857 = typeof olGeomInstance.getExtent === "function" ? olGeomInstance.getExtent() : null;
                  if (ext3857 && ext3857.length === 4 && Number.isFinite(ext3857[0])) {
                    const ext4426 = olModules.transformExtent(ext3857, "EPSG:3857", "EPSG:4326");
                    featExtent = [
                      Number(ext4426[0].toFixed(6)),
                      Number(ext4426[1].toFixed(6)),
                      Number(ext4426[2].toFixed(6)),
                      Number(ext4426[3].toFixed(6)),
                    ];
                  }

                  if (olModules.GeoJSON) {
                    try {
                      const geoJsonFormat = new olModules.GeoJSON();
                      geojsonGeom = geoJsonFormat.writeGeometryObject(olGeomInstance, {
                        dataProjection: "EPSG:4326",
                        featureProjection: "EPSG:3857",
                      });
                    } catch {}
                  }
                }

                const layerOrder = getActiveLayerOrder(id);
                const geomType = olGeomInstance?.getType?.() || feature.getType?.() || feature.getGeometry?.()?.getType?.() || "LineString";

                vectorIdentified.push({
                  id: String(feature.getId?.() || cleanProps.id || Math.random()),
                  layerId: id,
                  layerTitle: match.title || match.name || id,
                  layerAttribution: match.instansi || match.attribution || match.agency,
                  layerInstansi: match.instansi || match.attribution || match.agency,
                  geometryType: geomType,
                  properties: cleanProps,
                  coordinate: olModules.toLonLat(olCoord),
                  extent: featExtent,
                  geojsonGeometry: geojsonGeom,
                  olGeometry: olGeomInstance,
                  layerIndex: layerOrder,
                });
              }
              break;
            }
          }
        },
        {
          hitTolerance: 6,
          layerFilter: (l: any) => l !== tileLayer && !wmsLayersMap.has(l),
        }
      );
    }
  } catch (err) {
    console.warn("Gagal membaca fitur MVT at pixel:", err);
  }

  // Jika tidak ada layer WMS yang aktif dan fitur vektor sudah ditemukan atau kosong
  if (visibleWmsLayers.length === 0) {
    // Prioritaskan layer aktif teratas dan garis di atas poligon
    vectorIdentified.sort((a, b) => {
      const orderA = a.layerIndex ?? 999;
      const orderB = b.layerIndex ?? 999;
      if (orderA !== orderB) return orderA - orderB;

      const isPolyA = (a.geometryType || "").toLowerCase().includes("polygon");
      const isPolyB = (b.geometryType || "").toLowerCase().includes("polygon");
      if (isPolyA && !isPolyB) return 1;
      if (!isPolyA && isPolyB) return -1;
      return 0;
    });

    if (vectorIdentified.length > 0) {
      activeNamobj.value = extractNamobj(vectorIdentified[0].properties);
      renderSelectedFeature(vectorIdentified[0]);
    } else {
      activeNamobj.value = null;
      renderSelectedFeature(null);
    }
    await new Promise((resolve) => setTimeout(resolve, 150));
    isIdentifyingFeatures.value = false;
    emit("identifying-features", false);
    emit("identify-features", vectorIdentified);
    return;
  }

  const view = mapInstance.getView();
  const resolution = view.getResolution();
  const projection = view.getProjection();

  // Jalankan query seluruh layer WMS aktif secara paralel (Promise.allSettled)
  const queries = visibleWmsLayers.map(async (layer) => {
    const olLayer = wmsLayersMap.get(layer.id);
    if (!olLayer) return [];
    const source = olLayer.getSource();
    if (!source || typeof source.getFeatureInfoUrl !== "function") return [];

    const queryLayerName = layer.wmsLayerName || layer.layer_name;
    const url = source.getFeatureInfoUrl(olCoord, resolution, projection, {
      INFO_FORMAT: "application/json",
      QUERY_LAYERS: queryLayerName,
      FEATURE_COUNT: 5,
      BUFFER: 10,
    });

    if (!url) return [];

    try {
      let data: any = null;

      // 1. Prioritaskan proxy backend wms/feature-info (menghindari CORS dan SSL handshake error)
      try {
        data = await $http("wms/feature-info", {
          query: { url },
        });
      } catch (proxyErr) {
        // 2. Fallback otomatis ke direct browser fetch jika proxy backend mengalami kendala
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
          let olGeomInstance: any = null;
          let featureCoordinate: [number, number] = olModules.toLonLat(olCoord);

          if (f.geometry && olModules?.GeoJSON) {
            try {
              const sample = extractFirstCoordinate(f.geometry);
              const isCoordsMercator = isResponse3857 || Boolean(sample && (Math.abs(sample[0]) > 180 || Math.abs(sample[1]) > 90));

              const geoJsonFormat = new olModules.GeoJSON();
              olGeomInstance = geoJsonFormat.readGeometry(f.geometry, {
                dataProjection: isCoordsMercator ? "EPSG:3857" : "EPSG:4326",
                featureProjection: "EPSG:3857",
              });

              if (olGeomInstance) {
                const ext3857 = olGeomInstance.getExtent();
                if (ext3857 && ext3857.length === 4 && Number.isFinite(ext3857[0]) && Number.isFinite(ext3857[1])) {
                  const ext4326 = olModules.transformExtent(ext3857, "EPSG:3857", "EPSG:4326");
                  featExtent = [
                    Number(ext4326[0].toFixed(6)),
                    Number(ext4326[1].toFixed(6)),
                    Number(ext4326[2].toFixed(6)),
                    Number(ext4326[3].toFixed(6)),
                  ];
                }

                if (sample) {
                  if (isCoordsMercator) {
                    featureCoordinate = olModules.toLonLat([sample[0], sample[1]]);
                  } else {
                    featureCoordinate = normalizeCoordLonLat([sample[0], sample[1]]);
                  }
                }
              }
            } catch (errGeom) {
              console.warn("Gagal membaca geometri WMS GeoJSON:", errGeom);
            }
          }

          if (!featExtent && (f.bbox || data.bbox)) {
            const rawBbox = f.bbox || data.bbox;
            if (Array.isArray(rawBbox) && rawBbox.length === 4) {
              featExtent = normalizeBbox4326(rawBbox as [number, number, number, number], olModules);
            }
          }

          return {
            id: f.id,
            layerId: layer.id,
            layerTitle: layer.title || layer.name,
            layerAttribution: layer.instansi || layer.attribution || layer.agency,
            layerInstansi: layer.instansi || layer.attribution || layer.agency,
            geometryType: f.geometry?.type || olGeomInstance?.getType?.(),
            properties: f.properties || {},
            coordinate: featureCoordinate,
            extent: featExtent,
            geojsonGeometry: f.geometry,
            olGeometry: olGeomInstance,
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

  // Berikan sedikit jeda animasi (250ms) agar efek visual radar & ripple pada peta serta status loading berjalan mulus tanpa flickering
  const minDelayPromise = new Promise((resolve) => setTimeout(resolve, 250));

  const [settled] = await Promise.all([
    Promise.allSettled(queries),
    minDelayPromise,
  ]);

  const identifiedList: IdentifiedFeature[] = [...vectorIdentified];

  for (const item of settled) {
    if (item.status === "fulfilled" && Array.isArray(item.value)) {
      identifiedList.push(...item.value);
    }
  }

  // Prioritaskan secara mutlak:
  // 1. Layer teratas di daftar layer aktif (layerIndex terendah / index 0 adalah top of stack)
  // 2. Tipe garis ruas/segmen (LineString) & titik (Point) di atas poligon wilayah
  identifiedList.sort((a, b) => {
    const orderA = a.layerIndex ?? 999;
    const orderB = b.layerIndex ?? 999;
    if (orderA !== orderB) return orderA - orderB;

    const isPolyA = (a.geometryType || "").toLowerCase().includes("polygon");
    const isPolyB = (b.geometryType || "").toLowerCase().includes("polygon");
    if (isPolyA && !isPolyB) return 1;
    if (!isPolyA && isPolyB) return -1;

    return 0;
  });

  // Tampilkan namobj dan render highlight style trayek modern dari fitur yang teridentifikasi ke kanvas peta
  if (identifiedList.length > 0) {
    activeNamobj.value = extractNamobj(identifiedList[0].properties);
    renderSelectedFeature(identifiedList[0]);
  } else {
    activeNamobj.value = null;
    renderSelectedFeature(null);
  }

  isIdentifyingFeatures.value = false;
  emit("identifying-features", false);
  emit("identify-features", identifiedList);
}

function emitViewportUpdate() {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const size = mapInstance.getSize();
  if (!size) return;

  const extent3857 = view.calculateExtent(size);
  const bbox4326 = olModules.transformExtent(extent3857, "EPSG:3857", "EPSG:4326");
  const rawZoom = view.getZoom() ?? DEFAULT_ZOOM;
  const zoom = Math.round(rawZoom * 100) / 100;
  const center4326 = olModules.toLonLat(view.getCenter());

  emit("update:viewport", {
    bbox: [
      Number(bbox4326[0].toFixed(5)),
      Number(bbox4326[1].toFixed(5)),
      Number(bbox4326[2].toFixed(5)),
      Number(bbox4326[3].toFixed(5)),
    ],
    zoom,
    center: [
      Number(center4326[0].toFixed(5)),
      Number(center4326[1].toFixed(5)),
    ],
  });
}

function getDeviceMapPadding(snapOverride?: "min" | "peek" | "full"): [number, number, number, number] {
  if (typeof window === "undefined") return [60, 40, 60, 40];
  const isMobile = window.innerWidth < 768;
  if (isMobile) {
    const snap = snapOverride || props.mobileSheetSnap || "peek";
    const h = window.innerHeight;
    const bottomPadding = snap === "full"
      ? Math.round(h * 0.82)
      : snap === "peek"
        ? Math.round(h * 0.50) + 28
        : 170;
    return [80, 24, bottomPadding, 24];
  }
  // Desktop & Tablet: kompensasi padding kiri untuk Floating Left Panel (350px tablet / 400px desktop) dan top padding untuk Floating Navbar (80px)
  const isTablet = window.innerWidth >= 768 && window.innerWidth < 1024;
  const leftPadding = isTablet ? 390 : 440;
  return [96, 48, 48, leftPadding];
}

function normalizeCoordLonLat(coord: [number, number]): [number, number] {
  if (!coord || coord.length < 2) return [111.89, -7.15];
  let [lon, lat] = coord;
  if (Math.abs(lon) <= 90 && Math.abs(lat) > 90) {
    return [lat, lon];
  }
  return [lon, lat];
}

function normalizeBbox4326(
  rawBbox: [number, number, number, number],
  modules?: any
): [number, number, number, number] {
  if (!rawBbox || rawBbox.length !== 4) return [111.5, -7.4, 112.2, -7.0];
  let [a, b, c, d] = rawBbox;

  if (Math.abs(a) > 180 || Math.abs(c) > 180) {
    if (modules?.transformExtent) {
      try {
        const trans = modules.transformExtent(rawBbox, "EPSG:3857", "EPSG:4326");
        if (trans && Number.isFinite(trans[0])) {
          return [trans[0], trans[1], trans[2], trans[3]];
        }
      } catch {}
    }
  }

  if (Math.abs(a) <= 90 && Math.abs(c) <= 90 && (Math.abs(b) > 90 || Math.abs(d) > 90)) {
    return [
      Math.min(b, d),
      Math.min(a, c),
      Math.max(b, d),
      Math.max(a, c),
    ];
  }

  return [
    Math.min(a, c),
    Math.min(b, d),
    Math.max(a, c),
    Math.max(b, d),
  ];
}

function getResolutionForScale(scaleDenominator: number, centerCoord?: [number, number]): number {
  const lat = centerCoord && centerCoord.length >= 2 ? centerCoord[1] : BOJONEGORO_CENTER[1];
  const latRad = (lat * Math.PI) / 180;
  const cosLat = Math.cos(latRad);
  return scaleDenominator / (cosLat * 3779.527559);
}

function fitCoordinateWithPadding(
  coord: [number, number],
  targetZoom?: number,
  duration = 450,
  snapOverride?: "min" | "peek" | "full"
) {
  if (!mapInstance || !olModules) return;
  const cleanCoord = normalizeCoordLonLat(coord);
  const padding = getDeviceMapPadding(snapOverride);
  const view = mapInstance.getView();

  // Batasi fitbound maksimal pada skala 1:5.000
  const minRes5000 = getResolutionForScale(5000, cleanCoord);
  const zoom5000 = view.getZoomForResolution(minRes5000) ?? 16.8;
  const currentZoom = view.getZoom() || DEFAULT_ZOOM;
  const target = typeof targetZoom === "number" ? targetZoom : Math.max(currentZoom, 15);
  const resolvedZoom = Math.min(target, zoom5000);

  const delta = 0.00005;
  const microBbox: [number, number, number, number] = [
    cleanCoord[0] - delta,
    cleanCoord[1] - delta,
    cleanCoord[0] + delta,
    cleanCoord[1] + delta,
  ];
  const extent3857 = olModules.transformExtent(microBbox, "EPSG:4326", "EPSG:3857");

  view.fit(extent3857, {
    size: mapInstance.getSize(),
    padding,
    duration,
    minResolution: minRes5000,
    maxZoom: resolvedZoom,
  });
}

function fitFeatureExtent(
  extent: [number, number, number, number],
  maxZoom?: number,
  duration = 500,
  snapOverride?: "min" | "peek" | "full"
) {
  if (!mapInstance || !olModules || !extent || extent.length !== 4) return;
  const normalized = normalizeBbox4326(extent, olModules);
  const [minX, minY, maxX, maxY] = normalized;
  const centerCoord: [number, number] = [(minX + maxX) / 2, (minY + maxY) / 2];

  // Batasi fitbound maksimal pada skala 1:5.000
  const minRes5000 = getResolutionForScale(5000, centerCoord);
  const view = mapInstance.getView();
  const zoom5000 = view.getZoomForResolution(minRes5000) ?? 16.8;
  const resolvedMaxZoom = typeof maxZoom === "number" ? Math.min(maxZoom, zoom5000) : zoom5000;

  if (Math.abs(maxX - minX) < 0.0001 && Math.abs(maxY - minY) < 0.0001) {
    fitCoordinateWithPadding(centerCoord, resolvedMaxZoom, duration, snapOverride);
    return;
  }

  const padding = getDeviceMapPadding(snapOverride);
  const extent3857 = olModules.transformExtent(normalized, "EPSG:4326", "EPSG:3857");

  view.fit(extent3857, {
    size: mapInstance.getSize(),
    padding,
    duration,
    minResolution: minRes5000,
    maxZoom: resolvedMaxZoom,
  });
}

function fitBbox(
  bbox: [number, number, number, number],
  targetZoom?: number,
  duration = 0,
  snapOverride?: "min" | "peek" | "full"
) {
  if (!mapInstance || !olModules || !bbox || bbox.length !== 4) return;
  const normalized = normalizeBbox4326(bbox, olModules);
  const extent3857 = olModules.transformExtent(normalized, "EPSG:4326", "EPSG:3857");
  const view = mapInstance.getView();
  const centerCoord: [number, number] = [(normalized[0] + normalized[2]) / 2, (normalized[1] + normalized[3]) / 2];
  const minRes5000 = getResolutionForScale(5000, centerCoord);
  const zoom5000 = view.getZoomForResolution(minRes5000) ?? 16.8;
  const resolvedMaxZoom = typeof targetZoom === "number" ? Math.min(targetZoom, zoom5000) : zoom5000;
  const padding = getDeviceMapPadding(snapOverride);

  view.fit(extent3857, {
    size: mapInstance.getSize(),
    padding,
    duration,
    minResolution: minRes5000,
    maxZoom: resolvedMaxZoom,
  });
}

function getViewport(): MapViewportPayload | null {
  if (!mapInstance || !olModules) return null;
  const view = mapInstance.getView();
  const size = mapInstance.getSize();
  if (!size) return null;

  const extent3857 = view.calculateExtent(size);
  const bbox4326 = olModules.transformExtent(extent3857, "EPSG:3857", "EPSG:4326");
  const rawZoom = view.getZoom() ?? DEFAULT_ZOOM;
  const zoom = Math.round(rawZoom * 100) / 100;
  const center4326 = olModules.toLonLat(view.getCenter());

  return {
    bbox: [
      Number(bbox4326[0].toFixed(5)),
      Number(bbox4326[1].toFixed(5)),
      Number(bbox4326[2].toFixed(5)),
      Number(bbox4326[3].toFixed(5)),
    ],
    zoom,
    center: [
      Number(center4326[0].toFixed(5)),
      Number(center4326[1].toFixed(5)),
    ],
  };
}

const FIRE_MARKER_SVG =
  '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 1.5c.9 3.6 5.6 5.6 5.6 11.2A5.6 5.6 0 0 1 12 22.5a5.6 5.6 0 0 1-5.6-9.8c.3 1.4 1 2.2 1.9 2.6C8 10.6 9.8 4.2 12 1.5z" fill="#ea580c" stroke="#ffffff" stroke-width="1.4" stroke-linejoin="round"/><path d="M12 11.5c.5 1.8 2.6 2.7 2.6 5a2.6 2.6 0 0 1-5.2 0c0-1.2.5-1.9 1.2-2.5.2.6.5 1 .9 1.2-.3-1.5-.2-2.8.5-3.7z" fill="#fcd34d"/></svg>';
const PIN_MARKER_SVG =
  '<svg viewBox="0 0 24 32" aria-hidden="true" focusable="false"><path d="M12 1.5C6.8 1.5 2.5 5.8 2.5 11c0 7.2 9.5 19.5 9.5 19.5S21.5 18.2 21.5 11c0-5.2-4.3-9.5-9.5-9.5z" fill="#dc2626" stroke="#ffffff" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="10.8" r="3.2" fill="#ffffff"/></svg>';

let pinOverlay: any = null;
let geoparkOverlays: any[] = [];

interface GeoparkMarkerEntry {
  site: GeoparkSite;
  overlay: any;
  containerEl: HTMLElement;
  labelEl: HTMLElement;
  buttonEl: HTMLButtonElement;
}
let geoparkMarkerEntries: GeoparkMarkerEntry[] = [];
const activeGeoparkSite = ref<GeoparkSite | null>(props.selectedGeoparkSite || null);

// Perbarui visual aktif: animasi loncat-loncat dan label holo HANYA pada situs yang sedang dipilih
function updateGeoparkMarkersActiveState() {
  const current = activeGeoparkSite.value;
  for (const entry of geoparkMarkerEntries) {
    const isMatch = Boolean(
      current &&
        (current.id === entry.site.id ||
          current.slug === entry.site.slug ||
          (current.code && current.code === entry.site.code))
    );

    if (isMatch) {
      entry.containerEl.classList.add("is-active");
      if (typeof entry.overlay.setZIndex === "function") {
        entry.overlay.setZIndex(120);
      }
    } else {
      entry.containerEl.classList.remove("is-active");
      if (typeof entry.overlay.setZIndex === "function") {
        entry.overlay.setZIndex(10);
      }
    }
  }
}

// Pin standar ukuran diperkecil untuk seluruh fitur non-Geopark (WMS identify, pencarian, koordinat).
// Khusus Geopark, pin standar ditiadakan karena Geopark menggunakan marker animasi loncat-loncat.
function syncPinMarker() {
  if (!mapInstance || !olModules) return;
  if (props.activeTab === "geopark") {
    pinOverlay?.setPosition(undefined);
    return;
  }
  const coord = props.clickedCoordinate;
  if (!coord || coord.length < 2) {
    pinOverlay?.setPosition(undefined);
    return;
  }
  if (!pinOverlay) {
    const el = document.createElement("div");
    el.className = "mr-pin-marker";
    el.innerHTML = PIN_MARKER_SVG;
    pinOverlay = new olModules.Overlay({ element: el, positioning: "bottom-center", stopEvent: false });
    mapInstance.addOverlay(pinOverlay);
  }
  pinOverlay.setPosition(olModules.fromLonLat([coord[0], coord[1]]));
  const pinEl = pinOverlay.getElement();
  pinEl.classList.remove("is-dropping");
  void pinEl.offsetWidth;
  pinEl.classList.add("is-dropping");
}

function syncGeoparkMarkers() {
  if (!mapInstance || !olModules) return;

  if (props.activeTab !== "geopark") {
    if (geoparkMarkerEntries.length > 0) {
      for (const entry of geoparkMarkerEntries) {
        mapInstance.removeOverlay(entry.overlay);
      }
      geoparkMarkerEntries = [];
      geoparkOverlays = [];
    }
    return;
  }

  const validSites = (props.geoparkSites || []).filter((s) => s.hasCoordinate);

  // Jika marker sudah terpasang dan jumlahnya sama, cukup update status aktif tanpa render ulang DOM
  if (geoparkMarkerEntries.length > 0 && geoparkMarkerEntries.length === validSites.length) {
    updateGeoparkMarkersActiveState();
    return;
  }

  for (const entry of geoparkMarkerEntries) {
    mapInstance.removeOverlay(entry.overlay);
  }
  geoparkMarkerEntries = [];
  geoparkOverlays = [];

  validSites.forEach((site) => {

    const container = document.createElement("div");
    container.className = "mr-geopark-marker-container";

    // 1. Label Nama Geopark Simple Minimalis dengan Efek Holo
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

    // Handler klik terpadu untuk marker dan label holo Geopark
    const handleGeoparkClick = (e: MouseEvent) => {
      e.stopPropagation();
      activeGeoparkSite.value = site;
      updateGeoparkMarkersActiveState();

      // Fitbound kamera ke titik geosite dengan device-aware padding
      fitCoordinateWithPadding(
        [site.longitude, site.latitude],
        site.zoom || 15,
        500,
        props.mobileDrawerOpen ? "peek" : undefined
      );

      emit("select-geopark", site);
    };

    label.addEventListener("click", handleGeoparkClick);

    // 2. Tombol Marker Api Geopark (target sentuh 44x44px)
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mr-fire-marker";
    btn.title = site.name;
    btn.setAttribute("aria-label", `Situs Geopark: ${site.name}`);
    btn.innerHTML = FIRE_MARKER_SVG;

    btn.addEventListener("click", handleGeoparkClick);

    container.appendChild(label);
    container.appendChild(btn);

    const overlay = new olModules.Overlay({
      element: container,
      positioning: "bottom-center",
      position: olModules.fromLonLat([site.longitude, site.latitude]),
      stopEvent: true,
    });

    mapInstance.addOverlay(overlay);
    geoparkOverlays.push(overlay);
    geoparkMarkerEntries.push({
      site,
      overlay,
      containerEl: container,
      labelEl: label,
      buttonEl: btn,
    });
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

onMounted(() => {
  initMap();

  document.addEventListener("fullscreenchange", () => {
    isFullscreen.value = Boolean(document.fullscreenElement);
    if (mapInstance) {
      setTimeout(() => mapInstance.updateSize(), 150);
    }
  });

  const resizeObserver = new ResizeObserver(() => {
    if (mapInstance) {
      mapInstance.updateSize();
    }
  });
  if (mapContainer.value) {
    resizeObserver.observe(mapContainer.value);
  }

  onUnmounted(() => {
    stopLiveTracking();
    resizeObserver.disconnect();
    if (themeMutationObserver) {
      themeMutationObserver.disconnect();
      themeMutationObserver = null;
    }
    if (mapInstance) {
      if (pinOverlay) {
        mapInstance.removeOverlay(pinOverlay);
        pinOverlay = null;
      }
      for (const overlay of geoparkOverlays) {
        mapInstance.removeOverlay(overlay);
      }
      geoparkOverlays = [];
      geoparkMarkerEntries = [];
      mapInstance.setTarget(undefined);
      mapInstance = null;
    }
  });
});

function toggleCartoGrid(forceState?: boolean) {
  showCartoGrid.value = typeof forceState === "boolean" ? forceState : !showCartoGrid.value;
  if (graticuleLayer) {
    graticuleLayer.setVisible(showCartoGrid.value);
  }
  return showCartoGrid.value;
}

function setCenterAndZoom(center: [number, number], zoom?: number, animate = false) {
  if (!mapInstance || !olModules) return;
  const cleanCoord = normalizeCoordLonLat(center);
  const view = mapInstance.getView();
  const mercator = olModules.fromLonLat(cleanCoord);
  if (animate) {
    view.animate({
      center: mercator,
      zoom: zoom ?? view.getZoom(),
      duration: 300,
    });
  } else {
    view.setCenter(mercator);
    if (typeof zoom === "number") {
      view.setZoom(zoom);
    }
  }
}

let lastHoverState = false;

function updateCursorOnHover(pixel: [number, number]) {
  if (!mapInstance) return;
  const viewport = mapInstance.getViewport();
  if (!viewport) return;

  const currentLayers = props.activeLayers || [];
  let hasFeature = false;

  // 1. Cek layer vektor jika ada, kecualikan basemap, utility layers, dan bataswilayah_kabupaten
  try {
    mapInstance.forEachFeatureAtPixel(
      pixel,
      (feature: any, layer: any) => {
        for (const [id, olLayer] of mvtLayersMap.entries()) {
          if (olLayer === layer) {
            const match = currentLayers.find((l) => l.id === id);
            if (match && (match.visible === false || isLayerIdentifyDisabled(match))) return;
            if (isLayerIdentifyDisabled({ id })) return;

            const rawProps = feature.getProperties?.() || {};
            const featureId = String(feature.getId?.() || rawProps.id || "").toLowerCase();
            if (featureId.includes("bataswilayah_kabupaten")) return;

            hasFeature = true;
            return true;
          }
        }
      },
      {
        hitTolerance: 5,
        layerFilter: (layer: any) => {
          if (layer === tileLayer || layer === geoVectorLayer || layer === graticuleLayer) return false;
          for (const [id, olLayer] of mvtLayersMap.entries()) {
            if (olLayer === layer) {
              const match = currentLayers.find((l) => l.id === id);
              if (match && (match.visible === false || isLayerIdentifyDisabled(match))) return false;
              if (isLayerIdentifyDisabled({ id })) return false;
              return true;
            }
          }
          return false;
        },
      }
    );
  } catch {
    hasFeature = false;
  }

  // 2. Cek layer WMS jika belum terdeteksi dari vektor
  if (!hasFeature && wmsLayersMap.size > 0) {
    const offsets = [
      [0, 0],
      [-2, 0],
      [2, 0],
      [0, -2],
      [0, 2],
      [-2, -2],
      [2, 2],
      [-2, 2],
      [2, -2],
    ];

    for (const [id, olLayer] of wmsLayersMap.entries()) {
      if (!olLayer || !olLayer.getVisible()) continue;
      const match = currentLayers.find((l) => l.id === id);
      if (match && (match.visible === false || isLayerIdentifyDisabled(match))) continue;
      if (isLayerIdentifyDisabled({ id })) continue;

      try {
        for (const [dx, dy] of offsets) {
          const testPixel = [pixel[0] + dx, pixel[1] + dy] as [number, number];
          const data = olLayer.getData(testPixel);
          if (data && data[3] > 15) {
            hasFeature = true;
            break;
          }
        }
      } catch {
        // Fallback jika browser membatasi getImageData pada canvas
      }
      if (hasFeature) break;
    }
  }

  if (hasFeature !== lastHoverState) {
    lastHoverState = hasFeature;
    if (hasFeature) {
      viewport.classList.add("cursor-pointer-active");
    } else {
      viewport.classList.remove("cursor-pointer-active");
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
    const viewport = mapInstance.getViewport();
    if (viewport) {
      viewport.classList.remove("cursor-pointer-active");
    }
    lastHoverState = false;
  }
}

onBeforeUnmount(() => {
  if (dismissTimer) clearTimeout(dismissTimer);
  stopLiveTracking();
  if (geoVectorSource) geoVectorSource.clear();
  geoAccuracyFeature = null;
  geoPointFeature = null;
  geoHeadingFeature = null;
});

function buildCurrentMapThumbnailMeta(): MapThumbnailMeta {
  const vp = getViewport();
  return {
    center: vp?.center || props.clickedCoordinate || [111.89, -7.15],
    zoom: vp?.zoom || props.initialZoom || 13.5,
    extent: vp?.bbox || null,
    basemap: props.currentBasemap,
    activeLayers: props.activeLayers,
    filterWilayah: props.activeLayers?.find((l) => l.filterKecamatan)?.filterKecamatan || null,
    title: props.locationLabel || "Peta Spasial Kabupaten Bojonegoro",
  };
}

async function getMapSnapshot(options?: { crop16_9?: boolean; width?: number; height?: number }): Promise<string | null> {
  if (options?.crop16_9) {
    return getMapThumbnail(options);
  }
  return new Promise((resolve) => {
    if (!mapInstance || !mapContainer.value) return resolve(null);
    try {
      mapInstance.renderSync();
      const size = mapInstance.getSize();
      if (!size) return resolve(null);
      const [width, height] = size;
      const exportCanvas = document.createElement("canvas");
      exportCanvas.width = width;
      exportCanvas.height = height;
      const ctx = exportCanvas.getContext("2d");
      if (!ctx) return resolve(null);

      const canvases = mapContainer.value.querySelectorAll(".ol-layer canvas, canvas.ol-layer");
      let taintedCount = 0;
      if (canvases.length > 0) {
        canvases.forEach((c) => {
          const canvasEl = c as HTMLCanvasElement;
          if (canvasEl.width > 0 && canvasEl.height > 0) {
            if (isCanvasTainted(canvasEl)) {
              taintedCount++;
              return;
            }
            const parent = canvasEl.parentNode as HTMLElement | null;
            const opacity = parent?.style.opacity || canvasEl.style.opacity || "1";
            ctx.globalAlpha = parseFloat(opacity) || 1;
            const transform = canvasEl.style.transform;
            if (transform) {
              const matrixMatch = transform.match(/^matrix\(([^\(]*)\)$/);
              if (matrixMatch) {
                const m = matrixMatch[1].split(",").map(Number);
                ctx.setTransform(m[0], m[1], m[2], m[3], m[4], m[5]);
              }
            }
            ctx.drawImage(canvasEl, 0, 0);
          }
        });
        ctx.setTransform(1, 0, 0, 1, 0, 0);
      }

      if (taintedCount > 0 && taintedCount === canvases.length) {
        const meta = buildCurrentMapThumbnailMeta();
        return resolve(generateFallbackMapCanvas(meta, 640, 360, true));
      }

      resolve(exportCanvas.toDataURL("image/png"));
    } catch (err) {
      console.warn("Snapshot notice (cross-origin fallback):", err);
      const meta = buildCurrentMapThumbnailMeta();
      resolve(generateFallbackMapCanvas(meta, 640, 360, true));
    }
  });
}

async function getMapThumbnail(options: MapThumbnailOptions = {}): Promise<string | null> {
  return new Promise((resolve) => {
    if (!mapInstance || !mapContainer.value) return resolve(null);
    try {
      mapInstance.renderSync();
      const size = mapInstance.getSize();
      if (!size) return resolve(null);
      const [width, height] = size;
      const exportCanvas = document.createElement("canvas");
      exportCanvas.width = width;
      exportCanvas.height = height;
      const ctx = exportCanvas.getContext("2d");
      if (!ctx) return resolve(null);

      if (props.currentBasemap === "none") {
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, width, height);
      }

      const canvases = mapContainer.value.querySelectorAll(".ol-layer canvas, canvas.ol-layer");
      let taintedCount = 0;
      if (canvases.length > 0) {
        canvases.forEach((c) => {
          const canvasEl = c as HTMLCanvasElement;
          if (canvasEl.width > 0 && canvasEl.height > 0) {
            if (isCanvasTainted(canvasEl)) {
              taintedCount++;
              return;
            }
            const parent = canvasEl.parentNode as HTMLElement | null;
            const opacity = parent?.style.opacity || canvasEl.style.opacity || "1";
            ctx.globalAlpha = parseFloat(opacity) || 1;
            const transform = canvasEl.style.transform;
            if (transform) {
              const matrixMatch = transform.match(/^matrix\(([^\(]*)\)$/);
              if (matrixMatch) {
                const m = matrixMatch[1].split(",").map(Number);
                ctx.setTransform(m[0], m[1], m[2], m[3], m[4], m[5]);
              }
            }
            ctx.drawImage(canvasEl, 0, 0);
          }
        });
        ctx.setTransform(1, 0, 0, 1, 0, 0);
      }

      const meta = buildCurrentMapThumbnailMeta();
      const targetW = options.width || 640;
      const targetH = options.height || Math.round((targetW * 9) / 16);

      if (taintedCount > 0 && taintedCount === canvases.length) {
        const fallback = generateFallbackMapCanvas(
          meta,
          targetW,
          targetH,
          options.includeWatermark !== false
        );
        return resolve(fallback);
      }

      const resultDataUrl = cropCanvasTo16_9(
        exportCanvas,
        targetW,
        targetH,
        options.quality ?? 0.92,
        options.format ?? "image/png",
        options.includeWatermark !== false,
        meta
      );

      resolve(resultDataUrl || null);
    } catch (err) {
      console.warn("Ekspor thumbnail peta dialihkan ke fallback kartografis:", err);
      const meta = buildCurrentMapThumbnailMeta();
      const targetW = options.width || 640;
      const targetH = options.height || Math.round((targetW * 9) / 16);
      const fallback = generateFallbackMapCanvas(
        meta,
        targetW,
        targetH,
        options.includeWatermark !== false
      );
      resolve(fallback);
    }
  });
}

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
  setCenterAndZoom,
  getViewport,
  dismissPulse,
  getMapSnapshot,
  getMapThumbnail,
  getThumbnailMeta: buildCurrentMapThumbnailMeta,
  toggleCartoGrid,
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
    <!-- OpenLayers Map Target Container (Default panah, pointer saat menyorot feature data) -->
    <div
      ref="mapContainer"
      class="w-full h-full cursor-default focus:outline-hidden transition-colors duration-200"
      :class="currentBasemap === 'none' ? 'bg-white' : ''"
      tabindex="0"
      aria-label="Peta Interaktif Kabupaten Bojonegoro"
      @mouseenter="handleMapMouseEnter"
      @mouseleave="handleMapMouseLeave"
    />

    <!-- Floating Navigation Controls (Bottom Right with Dynamic Mobile Clearance) -->
    <div
      class="absolute right-3 md:right-4 z-20 flex flex-col items-end gap-1.5 pointer-events-none transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)]"
      :class="[
        mobileDrawerOpen && mobileSheetSnap === 'full'
          ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
          : mobileDrawerOpen && mobileSheetSnap === 'peek'
            ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4 md:opacity-100'
            : mobileDrawerOpen && mobileSheetSnap === 'min'
              ? 'bottom-[calc(150px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
              : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100 pointer-events-auto'
      ]"
    >
      <!-- Navigation Controls Component -->
      <MapControls
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

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- FLOATING SCALE BAR & LIVE CURSOR COORDINATES (STANDALONE COMPONENT)     -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <MapScaleCoordinateBar
      :active-coords="activeDisplayCoords.coords"
      :scale-ratio="currentScaleRatio"
      :mobile-drawer-open="mobileDrawerOpen"
      :mobile-sheet-snap="mobileSheetSnap"
      :clicked-coordinate="clickedCoordinate"
      @scale-target-mounted="handleScaleTargetMounted"
      @select-scale="handleSelectScale"
    />
  </div>
</template>

<style scoped>
/* ─── Kursor Peta: Default Panah, Pointer Saat Menyorot Fitur Data ─────────── */
:deep(.ol-viewport),
:deep(.ol-viewport canvas) {
  cursor: default !important;
}

:deep(.ol-viewport.cursor-pointer-active),
:deep(.ol-viewport.cursor-pointer-active canvas) {
  cursor: pointer !important;
}

/* ─── ScaleLine Control in Bottom-Center Status Pill ────────────────────────── */
:deep(.ol-scale-line) {
  position: relative !important;
  bottom: auto !important;
  left: auto !important;
  right: auto !important;
  top: auto !important;
  background: transparent !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  box-shadow: none !important;
}

:deep(.ol-scale-line-inner) {
  color: #334155;
  border: 1.5px solid #475569;
  border-top: none;
  font-size: 10px;
  font-weight: 700;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  text-align: center;
  margin: 0 auto;
  padding: 0 4px 1px;
  background: transparent !important;
  line-height: 1.2;
  transition: color 0.2s ease, border-color 0.2s ease;
}

/* ─── Dark Mode ScaleLine ───────────────────────────────────────────────────── */
:global(.dark) :deep(.ol-scale-line),
.dark :deep(.ol-scale-line) {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

:global(.dark) :deep(.ol-scale-line-inner),
.dark :deep(.ol-scale-line-inner) {
  color: #e2e8f0;
  border: 1.5px solid #94a3b8;
  border-top: none;
}
</style>

<style>
/* Marker dibuat lewat DOM oleh OL Overlay, jadi di luar scope komponen. */
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

/* ─── Geopark Marker Container ────────────────────────────────────────────── */
.mr-geopark-marker-container {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  pointer-events: auto;
  user-select: none;
}

/* ─── Holo Label: Simple Minimalist with Holographic Halo Glow ────────────── */
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
  /* Holographic halo border: prismatic amber edge dengan soft ambient glow */
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

/* Caret Arrow */
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

/* Code Badge di dalam Holo Pill */
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

/* ─── Fire Marker Button ──────────────────────────────────────────────────── */
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

/* Animasi Loncat-loncat: HANYA aktif saat feature geopark diklik / terpilih */
.mr-geopark-marker-container.is-active .mr-fire-marker svg {
  filter: drop-shadow(0 0 10px rgba(249, 115, 22, 0.75)) drop-shadow(0 2px 4px rgba(15, 23, 42, 0.5));
  animation: mr-fire-hop 0.95s cubic-bezier(0.3, 0.8, 0.4, 1) infinite;
}

@keyframes mr-fire-hop {
  0%, 100% {
    transform: translateY(0) scaleY(0.95);
  }
  45% {
    transform: translateY(-15px) scaleY(1.05);
  }
}

@media (prefers-reduced-motion: reduce) {
  .mr-geopark-marker-container.is-active .mr-fire-marker svg {
    animation: none;
    transform: translateY(-4px) scale(1.1);
  }
}
</style>

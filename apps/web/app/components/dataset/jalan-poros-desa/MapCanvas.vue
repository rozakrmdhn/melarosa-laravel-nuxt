<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from "vue";
import type {
  BasemapType,
  DesaOption,
  KecamatanOption,
  LayerSymbology,
  RuasProperties,
  SelectedFeature,
} from "~/types/dataset-editor";
import { DEFAULT_JALAN_POROS_SYMBOLOGY, DEFAULT_SYMBOLOGY } from "~/types/dataset-editor";
import { $http } from "~/utils/helpers";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";
import { useBasemapStore } from "~/stores/basemap";
import {
  buildDynamicMaplibreBasemapSpec,
  resolveAbsoluteTileUrl,
} from "~/utils/basemap-sources";
import { computeGeometryBbox } from "~/utils/jaringan-jalan-format";
import {
  extractMergedRoadCoordinates,
  haversineDistanceMeters,
  projectPointOntoRoad,
  type LngLat,
} from "~/utils/road-geometry";

const props = withDefaults(
  defineProps<{
    apiBase: string;
    selectedFeature?: SelectedFeature | null;
    bbox?: [number, number, number, number] | null;
    layerVisible?: boolean;
    layerOpacity?: number;
    basemap?: BasemapType;
    kecamatanFilter?: string | null;
    desaFilter?: string | null;
    kondisiFilter?: string | null;
    perkerasanFilter?: string | null;
    kecamatanOptions?: KecamatanOption[];
    desaOptions?: DesaOption[];
    symbology?: LayerSymbology;
    clickedCoordinate?: [number, number] | null;
  }>(),
  {
    selectedFeature: null,
    bbox: null,
    layerVisible: true,
    layerOpacity: 1,
    basemap: "osm",
    kecamatanFilter: null,
    desaFilter: null,
    kondisiFilter: null,
    perkerasanFilter: null,
    kecamatanOptions: () => [],
    desaOptions: () => [],
    symbology: () => ({ ...DEFAULT_JALAN_POROS_SYMBOLOGY }),
    clickedCoordinate: null,
  }
);

const emit = defineEmits<{
  (e: "select", feature: SelectedFeature | null): void;
  (e: "log", level: "INFO" | "WARN" | "ERROR", message: string): void;
  (e: "update:mouseCoords", coords: string): void;
  (e: "update:basemap", basemap: BasemapType): void;
  (e: "loaded"): void;
  (e: "mvtLoading", loading: boolean): void;
  (e: "drawSaved", payload: { geojson: any; lengthMeters: number }): void;
  (e: "drawCanceled"): void;
  (e: "splitPointSelected", payload: { road: any; coordinate: [number, number] }): void;
  (e: "mapClick", coordinate: [number, number]): void;
}>();

const BOJONEGORO_BOUNDS: [number, number, number, number] = [111.415, -7.475, 112.17, -6.975];

const colorMode = useColorMode();
const config = useRuntimeConfig();
const basemapStore = useBasemapStore();
const martinBase = computed(() =>
  String(config.public.martinUrl || "/martin").replace(/\/+$/, "")
);
const isDarkTheme = computed(() => colorMode.value === "dark");

const mapContainer = ref<HTMLElement | null>(null);
const mapLoaded = ref(false);
const isMvtLoading = ref(false);
const martinStatusState = ref<"ready" | "loading" | "warning">("loading");
const martinWarningDetail = ref<string | null>(null);
const showMartinPopover = ref(false);

const currentZoom = ref(10.5);
const currentPitch = ref(0);
const currentBearing = ref(0);
const is3DTiltActive = computed(() => currentPitch.value >= 15);
const cursorCoord = ref<{ lng: number; lat: number } | null>(null);
const mouseCoords = ref("");
const tileRefreshNonce = ref(0);

const hoverTooltip = ref<{
  x: number;
  y: number;
  nama_ruas: string;
  kondisi: string;
  panjang_meter: number;
  desa: string;
  kecamatan: string;
} | null>(null);

// ─── Draw / Vertex Edit / Snap State (shallowRef avoids Vue Proxy DataCloneError in MapLibre Worker) ───
const isDrawing = ref(false);
const hasCompletedLine = ref(false);
const isEditingExisting = ref(false);
const drawnCoords = shallowRef<LngLat[]>([]);
const previewCursorCoord = shallowRef<LngLat | null>(null);
const undoStack = shallowRef<LngLat[][]>([]);
const redoStack = shallowRef<LngLat[][]>([]);

const drawnPointCount = computed(() => drawnCoords.value.length);
const drawnLengthMeters = computed(() => {
  const pts = drawnCoords.value;
  if (pts.length < 2) return 0;
  let total = 0;
  for (let i = 1; i < pts.length; i++) {
    total += haversineDistanceMeters(pts[i - 1]!, pts[i]!);
  }
  return Math.round(total);
});

const canUndo = computed(() => undoStack.value.length > 0);
const canRedo = computed(() => redoStack.value.length > 0);
const canSave = computed(() => drawnCoords.value.length >= 2);
const isDrawInteracting = computed(() => isDrawing.value && !hasCompletedLine.value);

const isSnappingEnabled = ref(true);
const snapRoadCount = ref(0);
const isSnapLoading = ref(false);
const editingRuasId = ref<string | number | null>(null);
const snapCandidateRoads = shallowRef<LngLat[][]>([]);
const activeSnapPoint = shallowRef<{ coord: LngLat; kind: "vertex" | "edge" } | null>(null);

let draggingVertexIndex: number | null = null;
let dragSnapshotBefore: LngLat[] | null = null;
let suppressNextClickAfterDrag = false;
let snapFetchAbort: AbortController | null = null;
let lastSnapBbox = "";
let snapMoveEndTimer: ReturnType<typeof setTimeout> | null = null;
let pendingEditorAction: (() => void) | null = null;
let lastAppliedLineDash: string = props.symbology?.lineDash || "solid";

// ─── Split Mode State ─────────────────────────────────────────────────────────
const isSplitMode = ref(false);
const splitRoadData = shallowRef<any>(null);
const splitRoadCoords = shallowRef<LngLat[]>([]);
const splitPreviewPoint = shallowRef<{ coord: LngLat; alongMeters: number } | null>(null);
const splitSelectedCoord = shallowRef<LngLat | null>(null);

// ─── MapLibre & Geoman Runtime Refs ───────────────────────────────────────────
let mapInstance: any = null;
let maplibregl: any = null;
let gmInstance: any = null;
let GeomanClass: any = null;
let isRestoringGeomanState = false;
let pulseMarker: any = null;
let resizeObserver: ResizeObserver | null = null;
let canvasReadyFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let hasFittedToSummaryBbox = false;
let lastFittedBboxKey = "";
let hasUserInteractedWithCamera = false;
let lastObservedW = 0;
let lastObservedH = 0;
let tileErrorCount = 0;

function prefersReducedMotion(): boolean {
  if (!import.meta.client || typeof window === "undefined") return false;
  return window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
}

function getMotionDuration(ms: number, fallbackMs = 0): number {
  if (prefersReducedMotion()) return 0;
  if (typeof ms === "number" && Number.isFinite(ms)) return Math.max(0, ms);
  return Math.max(0, fallbackMs);
}

function toPlainCoord(pt: LngLat): [number, number] {
  return [Number(pt[0]), Number(pt[1])];
}

function cloneCoords(pts: LngLat[]): LngLat[] {
  return pts.map((p) => [Number(p[0]), Number(p[1])] as LngLat);
}

function pushUndoSnapshot() {
  const nextUndo = [...undoStack.value, cloneCoords(drawnCoords.value)];
  if (nextUndo.length > 80) {
    nextUndo.shift();
  }
  undoStack.value = nextUndo;
  redoStack.value = [];
}

function martinTileUrl(sourceId: string): string {
  const base = resolveAbsoluteTileUrl(`${martinBase.value}/${sourceId}/{z}/{x}/{y}`);
  return tileRefreshNonce.value > 0 ? `${base}?v=${tileRefreshNonce.value}` : base;
}

// ─── Symbology & Filter Expressions ──────────────────────────────────────────
function buildRoadColorExpression(): any {
  const sym = props.symbology || DEFAULT_JALAN_POROS_SYMBOLOGY;

  if (sym.colorMode === "kondisi") {
    const kondisiExpr: any = ["downcase", ["coalesce", ["to-string", ["get", "kondisi"]], ""]];
    return [
      "case",
      ["in", "rusak berat", kondisiExpr],
      "#ef4444",
      ["in", "rusak ringan", kondisiExpr],
      "#f97316",
      ["in", "rusak", kondisiExpr],
      "#f97316",
      ["in", "sedang", kondisiExpr],
      "#f59e0b",
      ["in", "baik", kondisiExpr],
      "#10b981",
      sym.lineColor || "#2563eb",
    ];
  }

  if (sym.colorMode === "perkerasan") {
    return [
      "match",
      ["coalesce", ["to-string", ["get", "perkerasan"]], ""],
      "Aspal",
      "#0284c7",
      "Beton",
      "#6366f1",
      "Kerikil",
      "#d97706",
      "Tanah",
      "#854d0e",
      "#64748b",
    ];
  }

  return sym.lineColor || "#2563eb";
}

function buildRoadDashArray(): number[] | undefined {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (sym.lineDash === "dashed") return [3, 2];
  if (sym.lineDash === "dotted") return [0.5, 2];
  return undefined;
}

function buildRoadWidthExpression(): any {
  const sym = props.symbology || DEFAULT_JALAN_POROS_SYMBOLOGY;
  const base = Math.max(1.5, Number(sym.lineWidth || 3));
  return [
    "interpolate",
    ["linear"],
    ["zoom"],
    9,
    Math.max(1.2, base * 0.65),
    13,
    base,
    17,
    base * 1.45,
  ];
}

function buildMvtFilterExpression(): any {
  const clauses: any[] = ["all"];

  if (props.kecamatanFilter) {
    const target = props.kecamatanFilter.trim().toLowerCase();
    const matchedKec = props.kecamatanOptions.find(
      (k) => k.nama.toLowerCase() === target || String(k.id) === target
    );
    const kecName = (matchedKec?.nama || props.kecamatanFilter).trim().toLowerCase();
    const kecOr: any[] = [
      "any",
      ["==", ["downcase", ["coalesce", ["to-string", ["get", "kecamatan"]], ""]], kecName],
      ["==", ["downcase", ["coalesce", ["to-string", ["get", "nama_kecamatan"]], ""]], kecName],
    ];
    if (matchedKec?.id != null) {
      kecOr.push(["==", ["to-number", ["get", "id_kecamatan"], -999], Number(matchedKec.id)]);
    }
    clauses.push(kecOr);
  }

  if (props.desaFilter) {
    const target = props.desaFilter.trim().toLowerCase();
    const matchedDesa = props.desaOptions.find(
      (d) => d.nama.toLowerCase() === target || String(d.id) === target
    );
    const desaName = (matchedDesa?.nama || props.desaFilter).trim().toLowerCase();
    const desaOr: any[] = [
      "any",
      ["==", ["downcase", ["coalesce", ["to-string", ["get", "desa"]], ""]], desaName],
      ["==", ["downcase", ["coalesce", ["to-string", ["get", "nama_desa"]], ""]], desaName],
    ];
    if (matchedDesa?.id != null) {
      desaOr.push(["==", ["to-number", ["get", "id_desa"], -999], Number(matchedDesa.id)]);
    }
    clauses.push(desaOr);
  }

  if (props.kondisiFilter) {
    const target = props.kondisiFilter.trim().toLowerCase();
    clauses.push(["in", target, ["downcase", ["coalesce", ["to-string", ["get", "kondisi"]], ""]]]);
  }

  if (props.perkerasanFilter) {
    const target = props.perkerasanFilter.trim().toLowerCase();
    clauses.push(["in", target, ["downcase", ["coalesce", ["to-string", ["get", "perkerasan"]], ""]]]);
  }

  return clauses.length > 1 ? clauses : null;
}

function buildSelectedMvtFilterExpression(): any {
  const selId = props.selectedFeature?.id ? String(props.selectedFeature.id).trim() : "";
  if (!selId) {
    return ["==", ["to-string", ["get", "id"]], "__NO_MATCH__"];
  }
  return ["==", ["to-string", ["get", "id"]], selId];
}

function buildSelectedFeatureGeoJson() {
  const geom = props.selectedFeature?.geometry;
  if (!geom) {
    return { type: "FeatureCollection", features: [] };
  }
  try {
    const parsed =
      typeof geom === "string" ? JSON.parse(geom) : JSON.parse(JSON.stringify(geom));
    const geometry = parsed?.type === "Feature" ? parsed.geometry : parsed;
    if (!geometry || !geometry.type) {
      return { type: "FeatureCollection", features: [] };
    }
    return {
      type: "FeatureCollection",
      features: [{ type: "Feature", geometry, properties: {} }],
    };
  } catch {
    return { type: "FeatureCollection", features: [] };
  }
}

function buildDrawLineGeoJson() {
  const features: any[] = [];
  if (!gmInstance?.loaded && drawnCoords.value.length >= 2) {
    features.push({
      type: "Feature",
      geometry: {
        type: "LineString",
        coordinates: cloneCoords(drawnCoords.value),
      },
      properties: { role: "line" },
    });
  }
  return { type: "FeatureCollection", features };
}

function buildDrawPreviewGeoJson() {
  const features: any[] = [];
  if (!gmInstance?.loaded && isDrawInteracting.value && drawnCoords.value.length >= 1 && previewCursorCoord.value) {
    const last = drawnCoords.value[drawnCoords.value.length - 1]!;
    features.push({
      type: "Feature",
      geometry: {
        type: "LineString",
        coordinates: [toPlainCoord(last), toPlainCoord(previewCursorCoord.value)],
      },
      properties: {},
    });
  }
  return { type: "FeatureCollection", features };
}

function buildDrawVerticesGeoJson() {
  if (gmInstance?.loaded) {
    return { type: "FeatureCollection", features: [] };
  }
  const total = drawnCoords.value.length;
  const features: any[] = drawnCoords.value.map((coord, idx) => ({
    type: "Feature",
    geometry: {
      type: "Point",
      coordinates: toPlainCoord(coord),
    },
    properties: {
      index: idx,
      isEndpoint: idx === 0 || idx === total - 1,
    },
  }));
  return { type: "FeatureCollection", features };
}

function buildDrawMidpointsGeoJson() {
  const features: any[] = [];
  if (!gmInstance?.loaded && isDrawing.value && drawnCoords.value.length >= 2) {
    for (let i = 0; i < drawnCoords.value.length - 1; i++) {
      const a = drawnCoords.value[i]!;
      const b = drawnCoords.value[i + 1]!;
      features.push({
        type: "Feature",
        geometry: {
          type: "Point",
          coordinates: [Number((a[0] + b[0]) / 2), Number((a[1] + b[1]) / 2)],
        },
        properties: {
          insertAfterIndex: i,
        },
      });
    }
  }
  return { type: "FeatureCollection", features };
}

function buildSnapIndicatorGeoJson() {
  if (!activeSnapPoint.value) {
    return { type: "FeatureCollection", features: [] };
  }
  return {
    type: "FeatureCollection",
    features: [
      {
        type: "Feature",
        geometry: {
          type: "Point",
          coordinates: toPlainCoord(activeSnapPoint.value.coord),
        },
        properties: {
          kind: String(activeSnapPoint.value.kind),
        },
      },
    ],
  };
}

function buildSplitGeoJson() {
  const features: any[] = [];
  if (isSplitMode.value && splitRoadCoords.value.length >= 2) {
    features.push({
      type: "Feature",
      geometry: {
        type: "LineString",
        coordinates: cloneCoords(splitRoadCoords.value),
      },
      properties: { role: "road" },
    });
  }
  if (isSplitMode.value && splitPreviewPoint.value) {
    features.push({
      type: "Feature",
      geometry: {
        type: "Point",
        coordinates: toPlainCoord(splitPreviewPoint.value.coord),
      },
      properties: { role: "preview" },
    });
  }
  if (isSplitMode.value && splitSelectedCoord.value) {
    features.push({
      type: "Feature",
      geometry: {
        type: "Point",
        coordinates: toPlainCoord(splitSelectedCoord.value),
      },
      properties: { role: "pin" },
    });
  }
  return { type: "FeatureCollection", features };
}

function buildMapStyle(basemapCode: BasemapType) {
  const isNone = basemapCode === "none";
  const item = isNone ? null : basemapStore.getByCode(basemapCode, "maplibre");
  const { sources, layers } = isNone
    ? { sources: {} as Record<string, any>, layers: [] as any[] }
    : buildDynamicMaplibreBasemapSpec(item ?? basemapCode, { baseOpacity: 0.92 });

  const dark = isDarkTheme.value;
  const kecLineColor = dark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.38)";
  const desaLineColor = dark ? "rgba(148, 163, 184, 0.28)" : "rgba(100, 116, 139, 0.25)";

  if (isNone) {
    layers.unshift({
      id: "canvas-background",
      type: "background",
      paint: {
        "background-color": "#ffffff",
      },
    });
  }

  sources["martin-kecamatan"] = {
    type: "vector",
    tiles: [martinTileUrl("bataswilayah_kecamatan")],
    minzoom: 8,
    maxzoom: 18,
  };
  sources["martin-desa"] = {
    type: "vector",
    tiles: [martinTileUrl("bataswilayah_desa")],
    minzoom: 10,
    maxzoom: 18,
  };
  sources["martin-poros"] = {
    type: "vector",
    tiles: [martinTileUrl("jalan_porosdesa")],
    minzoom: 8,
    maxzoom: 19,
  };
  sources["selected-road-geojson"] = {
    type: "geojson",
    data: buildSelectedFeatureGeoJson(),
  };
  sources["draw-line-source"] = {
    type: "geojson",
    data: buildDrawLineGeoJson(),
  };
  sources["draw-preview-source"] = {
    type: "geojson",
    data: buildDrawPreviewGeoJson(),
  };
  sources["draw-midpoints-source"] = {
    type: "geojson",
    data: buildDrawMidpointsGeoJson(),
  };
  sources["draw-vertices-source"] = {
    type: "geojson",
    data: buildDrawVerticesGeoJson(),
  };
  sources["snap-indicator-source"] = {
    type: "geojson",
    data: buildSnapIndicatorGeoJson(),
  };
  sources["split-source"] = {
    type: "geojson",
    data: buildSplitGeoJson(),
  };

  const dash = buildRoadDashArray();
  const vis = props.layerVisible ? "visible" : "none";
  const mvtFilter = buildMvtFilterExpression();

  layers.push(
    {
      id: "layer-kecamatan-context",
      type: "line",
      source: "martin-kecamatan",
      "source-layer": "bataswilayah_kecamatan",
      paint: {
        "line-color": kecLineColor,
        "line-width": ["interpolate", ["linear"], ["zoom"], 9, 0.8, 13, 1.4],
        "line-dasharray": [4, 2],
      },
    },
    {
      id: "layer-desa-context",
      type: "line",
      source: "martin-desa",
      "source-layer": "bataswilayah_desa",
      minzoom: 11,
      paint: {
        "line-color": desaLineColor,
        "line-width": 0.75,
        "line-dasharray": [2, 2],
      },
    },
    {
      id: "layer-poros-casing",
      type: "line",
      source: "martin-poros",
      "source-layer": "jalan_porosdesa",
      ...(mvtFilter ? { filter: mvtFilter } : {}),
      layout: {
        visibility: vis,
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": dark ? "rgba(7, 11, 20, 0.8)" : "rgba(255, 255, 255, 0.85)",
        "line-width": [
          "interpolate",
          ["linear"],
          ["zoom"],
          9,
          Math.max(2.6, (props.symbology?.lineWidth || 3) * 0.65 + 1.5),
          13,
          (props.symbology?.lineWidth || 3) + 2.2,
          17,
          (props.symbology?.lineWidth || 3) * 1.45 + 2.6,
        ],
        "line-opacity": Math.max(0.15, props.layerOpacity * 0.85),
      },
    },
    {
      id: "layer-poros-lines",
      type: "line",
      source: "martin-poros",
      "source-layer": "jalan_porosdesa",
      ...(mvtFilter ? { filter: mvtFilter } : {}),
      layout: {
        visibility: vis,
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": buildRoadColorExpression(),
        "line-width": buildRoadWidthExpression(),
        "line-opacity": props.layerOpacity,
        ...(dash ? { "line-dasharray": dash } : {}),
      },
    },
    // Instant MVT Selection Highlight (0ms latency before full GeoJSON arrives)
    {
      id: "layer-poros-selected-mvt-casing",
      type: "line",
      source: "martin-poros",
      "source-layer": "jalan_porosdesa",
      filter: buildSelectedMvtFilterExpression(),
      layout: {
        visibility: vis,
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#ffffff",
        "line-width": 8.5,
        "line-opacity": 0.9,
      },
    },
    {
      id: "layer-poros-selected-mvt-line",
      type: "line",
      source: "martin-poros",
      "source-layer": "jalan_porosdesa",
      filter: buildSelectedMvtFilterExpression(),
      layout: {
        visibility: vis,
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#0284c7",
        "line-width": 4.8,
      },
    },
    // Selected Road Highlight (Full GeoJSON)
    {
      id: "layer-selected-casing",
      type: "line",
      source: "selected-road-geojson",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#ffffff",
        "line-width": 9,
        "line-opacity": 0.9,
      },
    },
    {
      id: "layer-selected-line",
      type: "line",
      source: "selected-road-geojson",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#0284c7",
        "line-width": 5,
      },
    },
    // Split Mode Layers
    {
      id: "layer-split-road-casing",
      type: "line",
      source: "split-source",
      filter: ["==", ["get", "role"], "road"],
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "#ffffff",
        "line-width": 9,
        "line-opacity": 0.9,
      },
    },
    {
      id: "layer-split-road-line",
      type: "line",
      source: "split-source",
      filter: ["==", ["get", "role"], "road"],
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "#f59e0b",
        "line-width": 5,
      },
    },
    {
      id: "layer-split-preview-point",
      type: "circle",
      source: "split-source",
      filter: ["==", ["get", "role"], "preview"],
      paint: {
        "circle-radius": 7,
        "circle-color": "rgba(245, 158, 11, 0.35)",
        "circle-stroke-color": "#d97706",
        "circle-stroke-width": 2.5,
      },
    },
    {
      id: "layer-split-pin-point",
      type: "circle",
      source: "split-source",
      filter: ["==", ["get", "role"], "pin"],
      paint: {
        "circle-radius": 8,
        "circle-color": "#dc2626",
        "circle-stroke-color": "#ffffff",
        "circle-stroke-width": 3,
      },
    },
    // Draw & Edit Geometry Layers
    {
      id: "layer-draw-casing",
      type: "line",
      source: "draw-line-source",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "rgba(16, 185, 129, 0.35)",
        "line-width": 9,
      },
    },
    {
      id: "layer-draw-line",
      type: "line",
      source: "draw-line-source",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "#059669",
        "line-width": 3.5,
      },
    },
    {
      id: "layer-draw-preview",
      type: "line",
      source: "draw-preview-source",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "#10b981",
        "line-width": 2.5,
        "line-dasharray": [2.5, 2],
      },
    },
    {
      id: "layer-draw-midpoints",
      type: "circle",
      source: "draw-midpoints-source",
      paint: {
        "circle-radius": 5,
        "circle-color": "rgba(16, 185, 129, 0.72)",
        "circle-stroke-color": "#ffffff",
        "circle-stroke-width": 1.5,
      },
    },
    {
      id: "layer-draw-vertices",
      type: "circle",
      source: "draw-vertices-source",
      paint: {
        "circle-radius": ["case", ["==", ["get", "isEndpoint"], true], 7, 6],
        "circle-color": ["case", ["==", ["get", "isEndpoint"], true], "#059669", "#10b981"],
        "circle-stroke-color": "#ffffff",
        "circle-stroke-width": 2.5,
      },
    },
    // Live Magnetic Snap Ring
    {
      id: "layer-snap-indicator",
      type: "circle",
      source: "snap-indicator-source",
      paint: {
        "circle-radius": 9,
        "circle-color": "rgba(37, 99, 235, 0.2)",
        "circle-stroke-color": "#2563eb",
        "circle-stroke-width": 2.5,
      },
    }
  );

  return {
    version: 8 as const,
    sources,
    layers,
  };
}

// ─── Layer Sync Helpers ───────────────────────────────────────────────────────
function syncPorosStylingAndFilters() {
  if (!mapInstance) return;
  const currentDashMode = props.symbology?.lineDash || "solid";
  if (currentDashMode !== lastAppliedLineDash) {
    lastAppliedLineDash = currentDashMode;
    applyMapStyle(props.basemap);
    return;
  }

  const vis = props.layerVisible ? "visible" : "none";
  const mvtFilter = buildMvtFilterExpression();
  const dash = buildRoadDashArray();

  for (const lid of ["layer-poros-casing", "layer-poros-lines"]) {
    if (!mapInstance.getLayer(lid)) continue;
    mapInstance.setLayoutProperty(lid, "visibility", vis);
    mapInstance.setFilter(lid, mvtFilter);
  }

  if (mapInstance.getLayer("layer-poros-lines")) {
    mapInstance.setPaintProperty("layer-poros-lines", "line-color", buildRoadColorExpression());
    mapInstance.setPaintProperty("layer-poros-lines", "line-width", buildRoadWidthExpression());
    mapInstance.setPaintProperty("layer-poros-lines", "line-opacity", props.layerOpacity);
    if (dash) {
      mapInstance.setPaintProperty("layer-poros-lines", "line-dasharray", dash);
    }
  }
  if (mapInstance.getLayer("layer-poros-casing")) {
    mapInstance.setPaintProperty(
      "layer-poros-casing",
      "line-opacity",
      Math.max(0.15, props.layerOpacity * 0.85)
    );
  }
}

function syncSelectedRoadSource() {
  if (!mapInstance) return;
  const mvtSelFilter = buildSelectedMvtFilterExpression();
  if (mapInstance.getLayer("layer-poros-selected-mvt-casing")) {
    mapInstance.setFilter("layer-poros-selected-mvt-casing", mvtSelFilter);
  }
  if (mapInstance.getLayer("layer-poros-selected-mvt-line")) {
    mapInstance.setFilter("layer-poros-selected-mvt-line", mvtSelFilter);
  }
  const src = mapInstance.getSource("selected-road-geojson");
  if (src && typeof src.setData === "function") {
    src.setData(buildSelectedFeatureGeoJson());
  }
}

function syncDrawSources() {
  if (!mapInstance) return;
  try {
    mapInstance.getSource("draw-line-source")?.setData(buildDrawLineGeoJson());
    mapInstance.getSource("draw-preview-source")?.setData(buildDrawPreviewGeoJson());
    mapInstance.getSource("draw-midpoints-source")?.setData(buildDrawMidpointsGeoJson());
    mapInstance.getSource("draw-vertices-source")?.setData(buildDrawVerticesGeoJson());
    mapInstance.getSource("snap-indicator-source")?.setData(buildSnapIndicatorGeoJson());
  } catch (err) {
    console.warn("[MapLibre] syncDrawSources warning:", err);
  }
}

function syncSplitSource() {
  if (!mapInstance) return;
  try {
    mapInstance.getSource("split-source")?.setData(buildSplitGeoJson());
  } catch (err) {
    console.warn("[MapLibre] syncSplitSource warning:", err);
  }
}

function syncPulseMarker() {
  if (!mapInstance || !maplibregl) return;
  if (!props.clickedCoordinate) {
    if (pulseMarker) {
      pulseMarker.remove();
      pulseMarker = null;
    }
    return;
  }

  if (!pulseMarker) {
    const el = document.createElement("div");
    el.className = "pointer-events-none flex items-center justify-center size-8";
    el.innerHTML =
      '<span class="absolute size-7 rounded-full bg-blue-500/35 animate-ping"></span>' +
      '<span class="relative size-3.5 rounded-full bg-blue-600 ring-2 ring-white shadow-md flex items-center justify-center">' +
      '<span class="size-1 rounded-full bg-white"></span></span>';
    pulseMarker = new maplibregl.Marker({ element: el, anchor: "center" });
  }
  const lng = Number(props.clickedCoordinate[0]);
  const lat = Number(props.clickedCoordinate[1]);
  if (!Number.isFinite(lng) || !Number.isFinite(lat)) return;
  pulseMarker.setLngLat([lng, lat]).addTo(mapInstance);
}

// ─── Basemap & Tile Refresh ──────────────────────────────────────────────────
function syncAllOverlaysAfterStyleChange() {
  if (!mapInstance) return;
  syncPorosStylingAndFilters();
  syncSelectedRoadSource();
  syncDrawSources();
  syncSplitSource();
}

function applyMapStyle(code: BasemapType) {
  if (!mapInstance) return;
  lastAppliedLineDash = props.symbology?.lineDash || "solid";
  const wasDrawing = isDrawing.value;
  const wasCompleted = hasCompletedLine.value;
  const savedCoords = cloneCoords(drawnCoords.value);

  if (gmInstance) {
    try {
      void gmInstance.destroy({ removeSources: false });
    } catch {
      // ignore destroy during style swap
    }
    gmInstance = null;
  }

  mapInstance.setStyle(buildMapStyle(code));
  mapInstance.once("style.load", async () => {
    syncAllOverlaysAfterStyleChange();
    await initGeoman();
    if (wasDrawing && gmInstance?.loaded) {
      if (wasCompleted && savedCoords.length >= 2) {
        await importCoordsIntoGeomanEdit(savedCoords);
      } else {
        await syncGeomanSnapping();
        await gmInstance.enableDraw("line");
      }
    }
  });
  mapInstance.once("styledata", () => {
    if (mapInstance?.getSource("draw-line-source")) {
      syncAllOverlaysAfterStyleChange();
    }
  });
}

function setBasemap(type: BasemapType) {
  emit("update:basemap", type);
  applyMapStyle(type);
}

function refreshVectorTiles() {
  tileRefreshNonce.value++;
  if (mapInstance && mapLoaded.value) {
    applyMapStyle(props.basemap);
  }
}

// ─── Navigation & Fit Bounds ─────────────────────────────────────────────────
function isValidBounds(
  b: [number, number, number, number] | null | undefined
): b is [number, number, number, number] {
  if (!Array.isArray(b) || b.length !== 4) return false;
  if (b.some((v) => v === null || v === undefined || v === "")) return false;
  const [minX, minY, maxX, maxY] = b.map(Number);
  if (![minX, minY, maxX, maxY].every(Number.isFinite)) return false;
  if (minX > maxX || minY > maxY) return false;
  return minX >= -180 && maxX <= 180 && minY >= -85 && maxY <= 85;
}

function safeFitBounds(
  bbox: [number, number, number, number] | null | undefined,
  padding = 56,
  maxZoom = 16.5,
  duration = 520
) {
  if (!mapInstance || !isValidBounds(bbox)) return;
  const el = mapContainer.value;
  const w = el?.clientWidth ?? 0;
  const h = el?.clientHeight ?? 0;
  if (w < 80 || h < 80) return;

  const [minX, minY, maxX, maxY] = bbox.map(Number) as [number, number, number, number];
  const motionMs = getMotionDuration(duration, 520);

  if (Math.abs(maxX - minX) < 1e-6 && Math.abs(maxY - minY) < 1e-6) {
    mapInstance.easeTo({
      center: [minX, minY],
      zoom: Math.min(maxZoom, 15.5),
      duration: motionMs,
    });
    return;
  }

  const maxAllowedPad = Math.max(8, Math.floor((Math.min(w, h) - 32) / 2));
  const safePad = Math.max(8, Math.min(padding, maxAllowedPad));

  try {
    mapInstance.fitBounds(
      [
        [minX, minY],
        [maxX, maxY],
      ],
      { padding: safePad, maxZoom, duration: motionMs }
    );
  } catch {
    // Abaikan sementara saat panel terlalu sempit
  }
}

function formatBboxKey(b: [number, number, number, number]): string {
  return b.map((n) => Number(n).toFixed(5)).join(",");
}

function fitBounds(duration = 480) {
  if (!mapInstance) return;
  const safeDuration =
    typeof duration === "number" && Number.isFinite(duration) ? Math.max(0, duration) : 480;
  mapInstance.resize();
  if (isValidBounds(props.bbox)) {
    hasFittedToSummaryBbox = true;
    lastFittedBboxKey = formatBboxKey(props.bbox);
    safeFitBounds(props.bbox, 52, 16, safeDuration);
  } else {
    safeFitBounds(BOJONEGORO_BOUNDS, 40, 15, safeDuration);
  }
}

function zoomIn() {
  if (!mapInstance) return;
  const z = mapInstance.getZoom() ?? 10.5;
  mapInstance.easeTo({ zoom: Math.min(19, z + 1), duration: getMotionDuration(220) });
}

function zoomOut() {
  if (!mapInstance) return;
  const z = mapInstance.getZoom() ?? 10.5;
  mapInstance.easeTo({ zoom: Math.max(8, z - 1), duration: getMotionDuration(220) });
}

function toggle3DTilt() {
  if (!mapInstance) return;
  mapInstance.easeTo({
    pitch: is3DTiltActive.value ? 0 : 45,
    duration: getMotionDuration(350),
  });
}

function resetNorthAndTilt() {
  if (!mapInstance) return;
  mapInstance.easeTo({
    bearing: 0,
    pitch: 0,
    duration: getMotionDuration(300),
  });
}

function zoomToCentroid(centroid: { type?: string; coordinates?: [number, number] } | [number, number] | null) {
  if (!mapInstance || !centroid) return;
  const coords = Array.isArray(centroid) ? centroid : centroid.coordinates;
  if (!Array.isArray(coords) || coords.length < 2) return;
  const lng = Number(coords[0]);
  const lat = Number(coords[1]);
  if (!Number.isFinite(lng) || !Number.isFinite(lat) || lng < -180 || lng > 180 || lat < -90 || lat > 90) {
    return;
  }
  mapInstance.flyTo({
    center: [lng, lat],
    zoom: 16,
    duration: getMotionDuration(600),
    essential: true,
  });
}

function zoomToGeometry(geom: any) {
  if (!mapInstance || !geom) return;
  const parsed = typeof geom === "string" ? JSON.parse(geom) : geom;
  const bbox = computeGeometryBbox(parsed?.geometry ?? parsed);
  if (bbox) {
    safeFitBounds(bbox, 72, 17, 520);
  }
}

function resizeMap() {
  const el = mapContainer.value;
  if (!el || !mapInstance) return;
  const w = Math.round(el.clientWidth);
  const h = Math.round(el.clientHeight);
  if (w < 40 || h < 40) return;
  if (w === lastObservedW && h === lastObservedH) return;
  lastObservedW = w;
  lastObservedH = h;
  mapInstance.resize();
  if (mapLoaded.value && typeof mapInstance.redraw === "function") {
    mapInstance.redraw();
  }
}

// ─── Magnetic Snapping Engine (Native MapLibre Pixel-Space Snapping) ─────────
function resolveSnappedCoord(rawLngLat: LngLat, excludeVertexIdx: number | null = null): {
  coord: LngLat;
  snapped: { coord: LngLat; kind: "vertex" | "edge" } | null;
} {
  if (!isSnappingEnabled.value || !mapInstance) {
    return { coord: rawLngLat, snapped: null };
  }

  const cursorPx = mapInstance.project(rawLngLat);
  const VERTEX_TOLERANCE_PX = 14;
  const EDGE_TOLERANCE_PX = 12;

  let bestVertexDist = VERTEX_TOLERANCE_PX;
  let bestVertexCoord: LngLat | null = null;

  // 1. Check self-vertices
  for (let i = 0; i < drawnCoords.value.length; i++) {
    if (excludeVertexIdx !== null && i === excludeVertexIdx) continue;
    const pt = drawnCoords.value[i]!;
    const px = mapInstance.project(pt);
    const d = Math.hypot(px.x - cursorPx.x, px.y - cursorPx.y);
    if (d < bestVertexDist) {
      bestVertexDist = d;
      bestVertexCoord = pt;
    }
  }

  // 2. Check nearby road vertices
  for (const road of snapCandidateRoads.value) {
    for (const pt of road) {
      const px = mapInstance.project(pt);
      const d = Math.hypot(px.x - cursorPx.x, px.y - cursorPx.y);
      if (d < bestVertexDist) {
        bestVertexDist = d;
        bestVertexCoord = pt;
      }
    }
  }

  if (bestVertexCoord) {
    return {
      coord: [bestVertexCoord[0], bestVertexCoord[1]],
      snapped: { coord: [bestVertexCoord[0], bestVertexCoord[1]], kind: "vertex" },
    };
  }

  // 3. Check nearby road edges
  let bestEdgeDist = EDGE_TOLERANCE_PX;
  let bestEdgeCoord: LngLat | null = null;

  for (const road of snapCandidateRoads.value) {
    if (road.length < 2) continue;
    const proj = projectPointOntoRoad(rawLngLat, road);
    if (!proj || !Number.isFinite(proj.point[0]) || !Number.isFinite(proj.point[1])) continue;
    const px = mapInstance.project(proj.point);
    const d = Math.hypot(px.x - cursorPx.x, px.y - cursorPx.y);
    if (d < bestEdgeDist) {
      bestEdgeDist = d;
      bestEdgeCoord = proj.point;
    }
  }

  if (bestEdgeCoord) {
    return {
      coord: bestEdgeCoord,
      snapped: { coord: bestEdgeCoord, kind: "edge" },
    };
  }

  return { coord: rawLngLat, snapped: null };
}

async function loadSnapFeatures() {
  if (!mapInstance || !isDrawing.value || !isSnappingEnabled.value) return;

  try {
    const b = mapInstance.getBounds();
    if (!b) return;
    const minX = b.getWest();
    const minY = b.getSouth();
    const maxX = b.getEast();
    const maxY = b.getNorth();
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
    const res = await $http<any>("admin/jalan-poros-desa", {
      query: { format: "geojson", bbox: bboxParam },
      signal: snapFetchAbort.signal,
      silent: true,
    } as any);

    if (res?.features && Array.isArray(res.features)) {
      let featuresToSnap = res.features;
      if (editingRuasId.value != null) {
        featuresToSnap = featuresToSnap.filter(
          (f: any) =>
            String(f.id) !== String(editingRuasId.value) &&
            String(f.properties?.id) !== String(editingRuasId.value)
        );
      }

      const chains: LngLat[][] = [];
      for (const feat of featuresToSnap) {
        const coords = extractMergedRoadCoordinates(feat);
        if (coords.length >= 2) {
          chains.push(coords);
        }
      }

      snapCandidateRoads.value = chains;
      snapRoadCount.value = chains.length;
      emit("log", "INFO", `Snapping magnet aktif ke ${chains.length} ruas jalan sekitar.`);
    }
  } catch (err: any) {
    if (
      err?.name === "AbortError" ||
      err?.cause?.name === "AbortError" ||
      (typeof err?.message === "string" && err.message.toLowerCase().includes("abort"))
    ) {
      return;
    }
  } finally {
    isSnapLoading.value = false;
  }
}

function toggleSnapping() {
  isSnappingEnabled.value = !isSnappingEnabled.value;
  if (!isSnappingEnabled.value) {
    activeSnapPoint.value = null;
    syncDrawSources();
  } else if (snapCandidateRoads.value.length === 0) {
    void loadSnapFeatures();
  }
  void syncGeomanSnapping();
  emit(
    "log",
    "INFO",
    isSnappingEnabled.value
      ? "Snapping persimpangan dan ruas jalan diaktifkan."
      : "Snapping dinonaktifkan."
  );
}

// ─── Geoman MapLibre GL JS Integration ───────────────────────────────────────
function extractGeomanFirstLineCoords(): LngLat[] {
  if (!gmInstance?.loaded) return [];
  try {
    const fc = gmInstance.features.exportGeoJson({ allowedShapes: ["line"] });
    const first = fc?.features?.[0];
    if (!first?.geometry) return [];
    return extractMergedRoadCoordinates(first);
  } catch {
    return [];
  }
}

async function syncGeomanSnapping() {
  if (!gmInstance?.loaded) return;
  try {
    if (isSnappingEnabled.value && isDrawing.value) {
      if (!gmInstance.isModeEnabled("helper", "snapping")) {
        await gmInstance.enableMode("helper", "snapping");
      }
      const snapHelper = gmInstance.actionInstances?.helper__snapping;
      if (snapHelper && !snapHelper.__melarosaSnapHooked) {
        snapHelper.__melarosaSnapHooked = true;
        snapHelper.getCustomLngLatsSnapping = (screenPoint: [number, number]) => {
          if (!isSnappingEnabled.value || !mapInstance) {
            activeSnapPoint.value = null;
            return null;
          }
          const raw = mapInstance.unproject(screenPoint);
          const rawTuple: LngLat = [Number(raw.lng), Number(raw.lat)];
          const { snapped } = resolveSnappedCoord(rawTuple);
          activeSnapPoint.value = snapped;
          syncDrawSources();
          return snapped ? [Number(snapped.coord[0]), Number(snapped.coord[1])] : null;
        };
      }
    } else {
      if (gmInstance.isModeEnabled("helper", "snapping")) {
        await gmInstance.disableMode("helper", "snapping");
      }
      activeSnapPoint.value = null;
      syncDrawSources();
    }
  } catch (err) {
    console.warn("[Geoman] syncGeomanSnapping warning:", err);
  }
}

async function importCoordsIntoGeomanEdit(coords: LngLat[]) {
  if (!gmInstance?.loaded || coords.length < 2) return;
  isRestoringGeomanState = true;
  try {
    await gmInstance.disableAllModes();
    await gmInstance.features.deleteAll();
    await gmInstance.features.importGeoJsonFeature({
      type: "Feature",
      properties: { shape: "line" },
      geometry: {
        type: "LineString",
        coordinates: cloneCoords(coords),
      },
    });
    await syncGeomanSnapping();
    await gmInstance.enableMode("edit", "change");
  } catch (err) {
    console.warn("[Geoman] importCoordsIntoGeomanEdit warning:", err);
  } finally {
    isRestoringGeomanState = false;
  }
}

async function handleGeomanEvent(event: any) {
  if (!gmInstance?.loaded || isRestoringGeomanState) return;
  const eventName = String(event?.name || "");

  // 1. Live Draw Vertex Added / Removed
  if (eventName === "gm:drawupdate" || eventName === "_gm:draw") {
    const draft = gmInstance.draw?.getDraft?.();
    if (draft && Array.isArray(draft.coordinates)) {
      const next = cloneCoords(draft.coordinates as LngLat[]);
      if (next.length !== drawnCoords.value.length) {
        if (next.length > drawnCoords.value.length && drawnCoords.value.length > 0) {
          pushUndoSnapshot();
        }
        drawnCoords.value = next;
        syncDrawSources();
      }
    }
    return;
  }

  // 2. Draw Completed (gm:create) -> Switch to Geoman Vertex Edit Mode (change)
  if (eventName === "gm:create") {
    const coords = extractGeomanFirstLineCoords();
    if (coords.length >= 2) {
      pushUndoSnapshot();
      drawnCoords.value = cloneCoords(coords);
      hasCompletedLine.value = true;
      previewCursorCoord.value = null;
      activeSnapPoint.value = null;
      syncDrawSources();
      try {
        await gmInstance.disableDraw();
        await syncGeomanSnapping();
        await gmInstance.enableMode("edit", "change");
      } catch {
        // ignore transition error
      }
      emit(
        "log",
        "INFO",
        `Garis Geoman selesai (${drawnPointCount.value} titik, ${drawnLengthMeters.value} m). Geser vertex, tarik titik tengah untuk sisip, atau klik Simpan.`
      );
    }
    return;
  }

  // 3. Vertex Edit Start -> Save snapshot for Undo
  if (eventName === "gm:editstart" || (eventName === "_gm:edit" && event?.action === "feature_edit_start")) {
    dragSnapshotBefore = cloneCoords(drawnCoords.value);
    return;
  }

  // 4. Live Vertex Drag / Edge Midpoint Insert / Right-Click Vertex Delete
  if (
    eventName === "gm:edit" ||
    eventName === "gm:editend" ||
    (eventName === "_gm:edit" &&
      (event?.action === "marker_move" ||
        event?.action === "feature_updated" ||
        event?.action === "feature_edit_end" ||
        event?.action === "marker_right_click"))
  ) {
    const coords = extractGeomanFirstLineCoords();
    if (coords.length >= 2) {
      if (
        (eventName === "gm:editend" || event?.action === "feature_edit_end") &&
        dragSnapshotBefore
      ) {
        const nextUndo = [...undoStack.value, dragSnapshotBefore];
        if (nextUndo.length > 80) nextUndo.shift();
        undoStack.value = nextUndo;
        redoStack.value = [];
        dragSnapshotBefore = null;
      }
      drawnCoords.value = cloneCoords(coords);
      syncDrawSources();
    }
  }
}

async function initGeoman() {
  if (!mapInstance || !GeomanClass || gmInstance) return;
  try {
    gmInstance = new GeomanClass(mapInstance, {
      settings: {
        useControlsUi: false,
        controlsUiEnabledByDefault: false,
        snapDistance: 16,
        awaitDataUpdatesOnEvents: true,
      },
    });
    gmInstance.setGlobalEventsListener((evt: any) => {
      void handleGeomanEvent(evt);
    });
    // Wait up to 3.5s for Geoman internal sources/marker image so it never blocks forever
    await Promise.race([
      gmInstance.waitForGeomanLoaded(),
      new Promise<void>((resolve) => setTimeout(resolve, 3500)),
    ]);
  } catch (err) {
    console.warn("[Geoman] initGeoman warning:", err);
    gmInstance = null;
  }
}

async function ensureGeomanLoaded(): Promise<boolean> {
  if (!gmInstance && mapInstance && GeomanClass) {
    await initGeoman();
  }
  if (gmInstance && !gmInstance.loaded) {
    try {
      await Promise.race([
        gmInstance.waitForGeomanLoaded(),
        new Promise<void>((resolve) => setTimeout(resolve, 2000)),
      ]);
    } catch {
      // ignore
    }
  }
  return Boolean(gmInstance?.loaded);
}

// ─── Draw / Redraw / Vertex Edit / Undo / Redo Operations ────────────────────
function startDrawing() {
  if (!mapInstance || !mapLoaded.value) {
    pendingEditorAction = () => startDrawing();
    return;
  }
  pendingEditorAction = null;
  cancelSplitMode();
  stopDrawing(false);
  clearDraw();

  isDrawing.value = true;
  isEditingExisting.value = false;
  hasCompletedLine.value = false;

  void (async () => {
    const ready = await ensureGeomanLoaded();
    if (!isDrawing.value) return;
    if (ready && gmInstance?.loaded) {
      isRestoringGeomanState = true;
      try {
        await gmInstance.disableAllModes();
        await gmInstance.features.deleteAll();
      } finally {
        isRestoringGeomanState = false;
      }
      await syncGeomanSnapping();
      await gmInstance.enableDraw("line");
    } else if (mapInstance) {
      mapInstance.doubleClickZoom?.disable();
      mapInstance.getCanvas().style.cursor = "crosshair";
    }
  })();

  void loadSnapFeatures();
  emit(
    "log",
    "INFO",
    "Mode gambar Geoman MapLibre aktif. Klik bebas di peta untuk menambah titik, klik titik terakhir atau tekan Enter untuk selesai."
  );
}

function finishActiveDraw() {
  if (!isDrawInteracting.value) return;
  if (gmInstance?.loaded && gmInstance.drawEnabled("line")) {
    const draft = gmInstance.draw?.getDraft?.();
    const draftCount = draft?.coordinates?.length ?? drawnCoords.value.length;
    if (draftCount < 2) {
      emit("log", "WARN", "Minimal 2 titik diperlukan untuk membentuk garis ruas jalan.");
      return;
    }
    void (async () => {
      const res = await gmInstance.draw.finish();
      if (!res?.ok && drawnCoords.value.length >= 2) {
        hasCompletedLine.value = true;
        await importCoordsIntoGeomanEdit(drawnCoords.value);
      }
      syncDrawSources();
    })();
    return;
  }

  if (drawnCoords.value.length < 2) {
    emit("log", "WARN", "Minimal 2 titik diperlukan untuk membentuk garis ruas jalan.");
    return;
  }
  hasCompletedLine.value = true;
  previewCursorCoord.value = null;
  activeSnapPoint.value = null;
  mapInstance?.doubleClickZoom?.enable();
  if (mapInstance) {
    mapInstance.getCanvas().style.cursor = "";
  }
  syncDrawSources();
  zoomToGeometry({ type: "LineString", coordinates: cloneCoords(drawnCoords.value) });
  emit(
    "log",
    "INFO",
    `Garis selesai (${drawnPointCount.value} titik, ${drawnLengthMeters.value} m). Geser vertex, klik titik tengah untuk sisip, atau klik Simpan.`
  );
}

function startEditingGeometry(geomData: any, id?: string | number) {
  if (!mapInstance || !mapLoaded.value) {
    pendingEditorAction = () => startEditingGeometry(geomData, id);
    return;
  }
  pendingEditorAction = null;
  cancelSplitMode();
  stopDrawing(false);
  clearDraw();

  const coords = extractMergedRoadCoordinates(geomData);
  if (coords.length < 2) {
    emit("log", "WARN", "Geometri tidak memiliki koordinat garis yang valid untuk diedit.");
    startDrawing();
    return;
  }

  isDrawing.value = true;
  isEditingExisting.value = true;
  hasCompletedLine.value = true;
  editingRuasId.value = id ?? null;
  drawnCoords.value = cloneCoords(coords);
  undoStack.value = [];
  redoStack.value = [];

  void (async () => {
    const ready = await ensureGeomanLoaded();
    if (ready && isDrawing.value) {
      await importCoordsIntoGeomanEdit(coords);
    }
  })();
  syncDrawSources();
  zoomToGeometry({ type: "LineString", coordinates: cloneCoords(coords) });
  setTimeout(() => {
    void loadSnapFeatures();
  }, 450);

  emit(
    "log",
    "INFO",
    `Mode edit vertex Geoman aktif (${coords.length} titik, ${drawnLengthMeters.value} m). Geser titik vertex, tarik titik tengah untuk menyisipkan, atau klik kanan vertex untuk menghapus.`
  );
}

function handleDrawClick() {
  if (!isDrawing.value) {
    startDrawing();
  } else if (hasCompletedLine.value) {
    if (gmInstance?.loaded) {
      void (async () => {
        if (!gmInstance.isModeEnabled("edit", "change")) {
          await importCoordsIntoGeomanEdit(drawnCoords.value);
        }
      })();
      emit(
        "log",
        "INFO",
        "Mode edit vertex Geoman aktif. Tarik titik tengah atau ujung garis untuk menambah/menggeser titik, atau klik Redraw untuk gambar baru."
      );
      return;
    }
    hasCompletedLine.value = false;
    mapInstance?.doubleClickZoom?.disable();
    if (mapInstance) {
      mapInstance.getCanvas().style.cursor = "crosshair";
    }
    syncDrawSources();
    emit("log", "INFO", "Lanjutkan klik peta untuk menambah titik di ujung garis.");
  } else {
    finishActiveDraw();
  }
}

function handleRedraw() {
  startDrawing();
  emit("log", "INFO", "Mengulang gambar garis ruas jalan dari awal.");
}

function handleUndo() {
  if (gmInstance?.loaded && isDrawInteracting.value && gmInstance.drawEnabled("line")) {
    void (async () => {
      const currentSnapshot = cloneCoords(drawnCoords.value);
      const removed = await gmInstance.draw.removeLastVertex();
      if (removed) {
        const draft = gmInstance.draw.getDraft();
        const nextCoords = draft?.coordinates ? cloneCoords(draft.coordinates as LngLat[]) : [];
        if (undoStack.value.length > 0) {
          const nextUndo = [...undoStack.value];
          nextUndo.pop();
          undoStack.value = nextUndo;
        }
        redoStack.value = [...redoStack.value, currentSnapshot];
        drawnCoords.value = nextCoords;
        syncDrawSources();
      }
    })();
    return;
  }

  if (undoStack.value.length === 0) return;
  const currentSnapshot = cloneCoords(drawnCoords.value);
  const nextUndo = [...undoStack.value];
  const prev = nextUndo.pop()!;
  undoStack.value = nextUndo;
  redoStack.value = [...redoStack.value, currentSnapshot];
  drawnCoords.value = prev;
  if (gmInstance?.loaded && prev.length >= 2) {
    hasCompletedLine.value = true;
    void importCoordsIntoGeomanEdit(prev);
  } else if (drawnCoords.value.length < 2) {
    hasCompletedLine.value = false;
    mapInstance?.doubleClickZoom?.disable();
  }
  syncDrawSources();
}

function handleRedo() {
  if (redoStack.value.length === 0) return;
  const currentSnapshot = cloneCoords(drawnCoords.value);
  const nextRedo = [...redoStack.value];
  const next = nextRedo.pop()!;
  redoStack.value = nextRedo;
  undoStack.value = [...undoStack.value, currentSnapshot];
  drawnCoords.value = next;
  if (gmInstance?.loaded && next.length >= 2) {
    hasCompletedLine.value = true;
    void importCoordsIntoGeomanEdit(next);
  }
  syncDrawSources();
}

function clearDraw() {
  drawnCoords.value = [];
  previewCursorCoord.value = null;
  activeSnapPoint.value = null;
  undoStack.value = [];
  redoStack.value = [];
  hasCompletedLine.value = false;
  isEditingExisting.value = false;
  draggingVertexIndex = null;
  dragSnapshotBefore = null;
  suppressNextClickAfterDrag = false;
  if (gmInstance?.loaded) {
    void (async () => {
      isRestoringGeomanState = true;
      try {
        await gmInstance.features.deleteAll();
      } catch {
        // ignore
      } finally {
        isRestoringGeomanState = false;
      }
    })();
  }
  syncDrawSources();
}

function stopDrawing(emitCancel = true) {
  pendingEditorAction = null;
  if (snapMoveEndTimer) {
    clearTimeout(snapMoveEndTimer);
    snapMoveEndTimer = null;
  }
  if (snapFetchAbort) {
    snapFetchAbort.abort();
    snapFetchAbort = null;
  }
  if (gmInstance?.loaded) {
    void (async () => {
      isRestoringGeomanState = true;
      try {
        await gmInstance.disableAllModes();
        await gmInstance.features.deleteAll();
      } catch {
        // ignore
      } finally {
        isRestoringGeomanState = false;
      }
    })();
  }
  if (mapInstance) {
    mapInstance.doubleClickZoom?.enable();
    mapInstance.dragPan?.enable();
    mapInstance.getCanvas().style.cursor = "";
  }
  lastSnapBbox = "";
  editingRuasId.value = null;
  snapCandidateRoads.value = [];
  snapRoadCount.value = 0;
  isDrawing.value = false;
  isEditingExisting.value = false;
  hasCompletedLine.value = false;
  previewCursorCoord.value = null;
  activeSnapPoint.value = null;
  draggingVertexIndex = null;
  dragSnapshotBefore = null;
  suppressNextClickAfterDrag = false;

  if (emitCancel) {
    clearDraw();
    emit("drawCanceled");
    emit("log", "INFO", "Mode gambar/edit dibatalkan.");
  } else {
    syncDrawSources();
  }
}

function confirmDraw() {
  if (gmInstance?.loaded && gmInstance.drawEnabled("line")) {
    const draft = gmInstance.draw?.getDraft?.();
    if (draft?.coordinates && draft.coordinates.length >= 2) {
      drawnCoords.value = cloneCoords(draft.coordinates as LngLat[]);
    }
  }
  if (drawnCoords.value.length < 2) {
    const gmCoords = extractGeomanFirstLineCoords();
    if (gmCoords.length >= 2) {
      drawnCoords.value = cloneCoords(gmCoords);
    }
  }
  if (drawnCoords.value.length < 2) return;
  const geojson = {
    type: "LineString",
    coordinates: cloneCoords(drawnCoords.value),
  };
  const length = drawnLengthMeters.value;

  hasCompletedLine.value = true;
  previewCursorCoord.value = null;
  activeSnapPoint.value = null;
  if (gmInstance?.loaded && gmInstance.drawEnabled("line")) {
    void (async () => {
      await gmInstance.draw.finish();
      await importCoordsIntoGeomanEdit(drawnCoords.value);
    })();
  }
  mapInstance?.doubleClickZoom?.enable();
  syncDrawSources();
  zoomToGeometry(geojson);

  emit("drawSaved", {
    geojson,
    lengthMeters: length,
  });
  emit("log", "INFO", `Garis ruas berhasil dikonfirmasi (${length} m). Membuka form.`);
}

// ─── Split Road Mode ─────────────────────────────────────────────────────────
function startSplitMode(roadData: any) {
  if (!mapInstance || !mapLoaded.value) {
    pendingEditorAction = () => startSplitMode(roadData);
    return;
  }
  pendingEditorAction = null;
  stopDrawing(false);
  cancelSplitMode();

  const rawGeom =
    roadData?.geometry ||
    roadData?.properties?.geometry ||
    roadData?.geojson ||
    roadData?.properties?.geojson;

  const coords = extractMergedRoadCoordinates(rawGeom);
  if (coords.length < 2) {
    emit("log", "WARN", "Geometri ruas jalan tidak ditemukan untuk split.");
    return;
  }

  isSplitMode.value = true;
  splitRoadData.value = roadData;
  splitRoadCoords.value = cloneCoords(coords);
  splitPreviewPoint.value = null;
  splitSelectedCoord.value = null;

  syncSplitSource();
  zoomToGeometry({ type: "LineString", coordinates: cloneCoords(coords) });
  mapInstance.getCanvas().style.cursor = "crosshair";

  emit(
    "log",
    "INFO",
    `Mode split aktif untuk ruas "${roadData.nama_ruas || roadData.properties?.nama_ruas || "jalan"}". Klik pada badan jalan di titik pemotongan.`
  );
}

function cancelSplitMode() {
  isSplitMode.value = false;
  splitRoadData.value = null;
  splitRoadCoords.value = [];
  splitPreviewPoint.value = null;
  splitSelectedCoord.value = null;
  if (mapInstance) {
    mapInstance.getCanvas().style.cursor = "";
  }
  syncSplitSource();
}

// ─── Interactive Tooltip & Map Events ────────────────────────────────────────
function getKondisiBadgeColor(kondisi: string) {
  const k = (kondisi || "").toLowerCase();
  if (k.includes("baik")) return "success";
  if (k.includes("sedang")) return "warning";
  if (k.includes("rusak berat")) return "error";
  if (k.includes("rusak")) return "warning";
  return "neutral";
}

function getTooltipStyle() {
  if (!hoverTooltip.value) return {};
  const width = mapContainer.value?.clientWidth ?? 800;
  const height = mapContainer.value?.clientHeight ?? 600;
  const left = Math.min(Math.max(12, hoverTooltip.value.x + 14), Math.max(12, width - 252));
  const top = Math.min(Math.max(12, hoverTooltip.value.y - 108), Math.max(12, height - 120));
  return {
    left: `${left}px`,
    top: `${top}px`,
  };
}

function queryDrawHandleAtPoint(point: { x: number; y: number }, layers: string[]) {
  if (!mapInstance) return [];
  const activeLayers = layers.filter((lid) => mapInstance.getLayer(lid));
  if (activeLayers.length === 0) return [];
  const pad = 8;
  return mapInstance.queryRenderedFeatures(
    [
      [point.x - pad, point.y - pad],
      [point.x + pad, point.y + pad],
    ],
    { layers: activeLayers }
  );
}

function bindMapEvents() {
  if (!mapInstance) return;

  // Vertex Drag Start (Fallback only when Geoman is not loaded)
  const beginVertexDrag = (e: any) => {
    if (gmInstance?.loaded) return;
    if (!isDrawing.value || drawnCoords.value.length === 0) return;
    if (e.originalEvent && typeof e.originalEvent.button === "number" && e.originalEvent.button !== 0) {
      return;
    }

    const hitVertices = queryDrawHandleAtPoint(e.point, ["layer-draw-vertices"]);
    if (hitVertices && hitVertices.length > 0) {
      const idx = Number(hitVertices[0].properties?.index);
      if (Number.isFinite(idx) && idx >= 0 && idx < drawnCoords.value.length) {
        e.preventDefault();
        draggingVertexIndex = idx;
        dragSnapshotBefore = cloneCoords(drawnCoords.value);
        suppressNextClickAfterDrag = false;
        mapInstance.dragPan.disable();
        mapInstance.getCanvas().style.cursor = "grabbing";
        return;
      }
    }

    // Midpoint click/drag inserts a new vertex immediately
    const hitMidpoints = queryDrawHandleAtPoint(e.point, ["layer-draw-midpoints"]);
    if (hitMidpoints && hitMidpoints.length > 0) {
      const afterIdx = Number(hitMidpoints[0].properties?.insertAfterIndex);
      if (Number.isFinite(afterIdx) && afterIdx >= 0 && afterIdx < drawnCoords.value.length - 1) {
        e.preventDefault();
        dragSnapshotBefore = cloneCoords(drawnCoords.value);
        suppressNextClickAfterDrag = true;
        const newCoord: LngLat = [Number(e.lngLat.lng), Number(e.lngLat.lat)];
        const next = cloneCoords(drawnCoords.value);
        next.splice(afterIdx + 1, 0, newCoord);
        drawnCoords.value = next;
        draggingVertexIndex = afterIdx + 1;
        mapInstance.dragPan.disable();
        mapInstance.getCanvas().style.cursor = "grabbing";
        syncDrawSources();
      }
    }
  };

  mapInstance.on("mousedown", beginVertexDrag);
  mapInstance.on("touchstart", beginVertexDrag);

  // Vertex Delete on Right-Click (Fallback only when Geoman is not loaded)
  mapInstance.on("contextmenu", (e: any) => {
    if (gmInstance?.loaded) return;
    if (!isDrawing.value || drawnCoords.value.length <= 2) return;
    const hitVertices = queryDrawHandleAtPoint(e.point, ["layer-draw-vertices"]);
    if (hitVertices && hitVertices.length > 0) {
      const idx = Number(hitVertices[0].properties?.index);
      if (Number.isFinite(idx) && idx >= 0 && idx < drawnCoords.value.length) {
        e.preventDefault();
        pushUndoSnapshot();
        const next = cloneCoords(drawnCoords.value);
        next.splice(idx, 1);
        drawnCoords.value = next;
        syncDrawSources();
        emit("log", "INFO", `Vertex #${idx + 1} dihapus (${next.length} titik tersisa).`);
      }
    }
  });

  // Pointer Move: Coordinates, Snap Indicator, Split Preview, Hover Tooltip
  mapInstance.on("mousemove", (e: any) => {
    const lngLat: LngLat = [Number(e.lngLat.lng), Number(e.lngLat.lat)];
    cursorCoord.value = {
      lng: Number(lngLat[0].toFixed(5)),
      lat: Number(lngLat[1].toFixed(5)),
    };
    const formatted = `${lngLat[1].toFixed(5)}, ${lngLat[0].toFixed(5)}`;
    mouseCoords.value = formatted;
    emit("update:mouseCoords", formatted);

    // 1. Active Vertex Dragging (Fallback when Geoman not loaded)
    if (!gmInstance?.loaded && draggingVertexIndex !== null) {
      suppressNextClickAfterDrag = true;
      const { coord, snapped } = resolveSnappedCoord(lngLat, draggingVertexIndex);
      activeSnapPoint.value = snapped;
      const next = cloneCoords(drawnCoords.value);
      next[draggingVertexIndex] = toPlainCoord(coord);
      drawnCoords.value = next;
      syncDrawSources();
      return;
    }

    // 2. Active Drawing & Snap Indicator
    if (isDrawing.value) {
      hoverTooltip.value = null;
      if (gmInstance?.loaded) {
        // When Geoman is active, update magnetic snap ring visual indicator
        if (isSnappingEnabled.value) {
          const { snapped } = resolveSnappedCoord(lngLat);
          if (
            (snapped && !activeSnapPoint.value) ||
            (!snapped && activeSnapPoint.value) ||
            (snapped &&
              activeSnapPoint.value &&
              (snapped.coord[0] !== activeSnapPoint.value.coord[0] ||
                snapped.coord[1] !== activeSnapPoint.value.coord[1]))
          ) {
            activeSnapPoint.value = snapped;
            syncDrawSources();
          }
        }
        return;
      }

      const hitHandle = queryDrawHandleAtPoint(e.point, [
        "layer-draw-vertices",
        "layer-draw-midpoints",
      ]);

      if (isDrawInteracting.value) {
        const { coord, snapped } = resolveSnappedCoord(lngLat);
        previewCursorCoord.value = toPlainCoord(coord);
        activeSnapPoint.value = snapped;
        mapInstance.getCanvas().style.cursor = hitHandle.length > 0 ? "grab" : "crosshair";
        syncDrawSources();
      } else {
        mapInstance.getCanvas().style.cursor = hitHandle.length > 0 ? "grab" : "";
      }
      return;
    }

    // 3. Split Mode Live Projection
    if (isSplitMode.value && splitRoadCoords.value.length >= 2) {
      hoverTooltip.value = null;
      const proj = projectPointOntoRoad(lngLat, splitRoadCoords.value);
      if (proj && Number.isFinite(proj.point[0]) && Number.isFinite(proj.point[1])) {
        splitPreviewPoint.value = {
          coord: toPlainCoord(proj.point),
          alongMeters: Math.round(proj.alongMeters),
        };
        syncSplitSource();
      }
      mapInstance.getCanvas().style.cursor = "crosshair";
      return;
    }

    // 4. Normal Inspection Hover
    if (!mapInstance.getLayer("layer-poros-lines")) return;
    const hitTolerance = 6;
    const features = mapInstance.queryRenderedFeatures(
      [
        [e.point.x - hitTolerance, e.point.y - hitTolerance],
        [e.point.x + hitTolerance, e.point.y + hitTolerance],
      ],
      { layers: ["layer-poros-lines"] }
    );

    if (!features || features.length === 0) {
      mapInstance.getCanvas().style.cursor = "";
      hoverTooltip.value = null;
      return;
    }

    mapInstance.getCanvas().style.cursor = "pointer";
    const p = features[0].properties || {};
    hoverTooltip.value = {
      x: e.point.x,
      y: e.point.y,
      nama_ruas: String(p.nama_ruas || "Ruas Jalan"),
      kondisi: String(p.kondisi || "-"),
      panjang_meter: Number(p.panjang_meter ?? p.panjang ?? 0),
      desa: String(p.desa || p.nama_desa || "-"),
      kecamatan: String(p.kecamatan || p.nama_kecamatan || "-"),
    };
  });

  // End Vertex Drag (Fallback when Geoman not loaded)
  const endVertexDrag = () => {
    if (gmInstance?.loaded) return;
    if (draggingVertexIndex === null) return;
    if (dragSnapshotBefore && suppressNextClickAfterDrag) {
      const nextUndo = [...undoStack.value, dragSnapshotBefore];
      if (nextUndo.length > 80) nextUndo.shift();
      undoStack.value = nextUndo;
      redoStack.value = [];
    }
    dragSnapshotBefore = null;
    draggingVertexIndex = null;
    activeSnapPoint.value = null;
    mapInstance?.dragPan?.enable();
    if (mapInstance) {
      mapInstance.getCanvas().style.cursor = isDrawInteracting.value ? "crosshair" : "";
    }
    syncDrawSources();
  };

  mapInstance.on("mouseup", endVertexDrag);
  mapInstance.on("touchend", endVertexDrag);

  mapInstance.on("dragstart", (e: any) => {
    hoverTooltip.value = null;
    if (e?.originalEvent) {
      hasUserInteractedWithCamera = true;
    }
  });

  mapInstance.on("wheel", () => {
    hasUserInteractedWithCamera = true;
  });

  mapInstance.on("mouseleave", () => {
    if (draggingVertexIndex === null && !isDrawing.value && !isSplitMode.value && mapInstance) {
      mapInstance.getCanvas().style.cursor = "";
    }
    hoverTooltip.value = null;
  });

  mapInstance.on("moveend", () => {
    if (isDrawing.value && isSnappingEnabled.value) {
      if (snapMoveEndTimer) clearTimeout(snapMoveEndTimer);
      snapMoveEndTimer = setTimeout(() => {
        void loadSnapFeatures();
      }, 380);
    }
  });

  // Click Handler: Draw Vertex / Split Pin / Road Feature Selection
  mapInstance.on("click", (e: any) => {
    showMartinPopover.value = false;
    if (suppressNextClickAfterDrag) {
      suppressNextClickAfterDrag = false;
      return;
    }

    // When Geoman is active in draw/edit mode, let Geoman handle pointer clicks cleanly
    if (isDrawing.value && gmInstance?.loaded) {
      return;
    }

    const rawLngLat: LngLat = [Number(e.lngLat.lng), Number(e.lngLat.lat)];

    // 1. Drawing click (Fallback when Geoman not loaded) -> add vertex
    if (isDrawInteracting.value) {
      const { coord } = resolveSnappedCoord(rawLngLat);
      const plainCoord = toPlainCoord(coord);
      const last = drawnCoords.value[drawnCoords.value.length - 1];
      if (last && haversineDistanceMeters(last, plainCoord) < 0.25) {
        return;
      }
      pushUndoSnapshot();
      drawnCoords.value = [...cloneCoords(drawnCoords.value), plainCoord];
      syncDrawSources();
      return;
    }

    // 2. Ignore map click when editing completed line vertices
    if (isDrawing.value && hasCompletedLine.value) {
      return;
    }

    // 3. Split mode click -> project point onto road and emit
    if (isSplitMode.value && splitRoadCoords.value.length >= 2) {
      const proj = projectPointOntoRoad(rawLngLat, splitRoadCoords.value);
      const cutCoord: LngLat = toPlainCoord(
        proj && Number.isFinite(proj.point[0]) && Number.isFinite(proj.point[1])
          ? proj.point
          : rawLngLat
      );
      splitSelectedCoord.value = cutCoord;
      syncSplitSource();
      emit("splitPointSelected", {
        road: splitRoadData.value,
        coordinate: cutCoord,
      });
      return;
    }

    // 4. Normal Inspection Click
    emit("mapClick", rawLngLat);

    if (!mapInstance.getLayer("layer-poros-lines")) return;
    const hitTolerance = 7;
    const features = mapInstance.queryRenderedFeatures(
      [
        [e.point.x - hitTolerance, e.point.y - hitTolerance],
        [e.point.x + hitTolerance, e.point.y + hitTolerance],
      ],
      { layers: ["layer-poros-lines"] }
    );

    if (!features || features.length === 0) {
      emit("select", null);
      return;
    }

    const top = features[0];
    const p = top.properties || {};
    const id = String(p.id ?? top.id ?? "");
    if (!id) return;

    const propsNormalized: RuasProperties = {
      id,
      kode_ruas: p.kode_ruas != null && p.kode_ruas !== "" ? Number(p.kode_ruas) : null,
      nama_ruas: String(p.nama_ruas || "-"),
      desa: p.desa || p.nama_desa || null,
      kecamatan: p.kecamatan || p.nama_kecamatan || null,
      panjang: p.panjang != null ? Number(p.panjang) : Number(p.panjang_meter ?? 0),
      panjang_meter: p.panjang_meter != null ? Number(p.panjang_meter) : Number(p.panjang ?? 0),
      lebar: p.lebar != null ? Number(p.lebar) : null,
      perkerasan: p.perkerasan || null,
      kondisi: p.kondisi || null,
      status_awal: p.status_awal || null,
      status_eksisting: p.status_eksisting || null,
      sumber_data: p.sumber_data || null,
      id_desa: p.id_desa != null ? Number(p.id_desa) : null,
      id_kecamatan: p.id_kecamatan != null ? Number(p.id_kecamatan) : null,
    };

    // Pass geometry: null so parent always fetches complete unclipped PostGIS geometry
    emit("select", {
      id,
      properties: propsNormalized,
      geometry: null,
    });
  });

  // Double-click finishes active Draw LineString
  mapInstance.on("dblclick", (e: any) => {
    if (isDrawInteracting.value && drawnCoords.value.length >= 2) {
      e.preventDefault();
      finishActiveDraw();
    }
  });
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === "Escape") {
    if (showMartinPopover.value) {
      showMartinPopover.value = false;
      return;
    }
    if (isSplitMode.value) {
      cancelSplitMode();
      return;
    }
    if (isDrawing.value) {
      stopDrawing(true);
      return;
    }
  }

  if (isDrawing.value) {
    if (e.key === "Enter" && isDrawInteracting.value && drawnCoords.value.length >= 2) {
      e.preventDefault();
      finishActiveDraw();
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "z") {
      e.preventDefault();
      if (e.shiftKey) {
        handleRedo();
      } else {
        handleUndo();
      }
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "y") {
      e.preventDefault();
      handleRedo();
    }
  }
}

async function initMap() {
  if (!import.meta.client || !mapContainer.value || mapInstance) return;

  try {
    const [mod, workerMod, geomanMod] = await Promise.all([
      import("maplibre-gl"),
      import("maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url"),
      import("@geoman-io/maplibre-geoman-free"),
    ]);
    await Promise.all([
      import("maplibre-gl/dist/maplibre-gl.css"),
      import("@geoman-io/maplibre-geoman-free/dist/maplibre-geoman.css"),
    ]);
    maplibregl = mod;
    GeomanClass = geomanMod?.Geoman ?? null;

    if (workerMod?.default && typeof maplibregl.setWorkerUrl === "function") {
      maplibregl.setWorkerUrl(workerMod.default);
    }

    await basemapStore.ensureLoaded();
    if (!mapContainer.value) return;

    lastAppliedLineDash = props.symbology?.lineDash || "solid";
    mapInstance = new maplibregl.Map({
      container: mapContainer.value,
      style: buildMapStyle(props.basemap),
      center: [111.88, -7.2],
      zoom: 10.5,
      minZoom: 8,
      maxZoom: 19,
      maxPitch: 60,
      fadeDuration: 0,
      trackResize: false,
      canvasContextAttributes: {
        preserveDrawingBuffer: true,
        antialias: true,
      },
      attributionControl: false,
    });

    mapInstance.addControl(
      new maplibregl.AttributionControl({ compact: true }),
      "bottom-right"
    );
    mapInstance.addControl(
      new maplibregl.ScaleControl({ maxWidth: 110, unit: "metric" }),
      "bottom-right"
    );

    const markCanvasReady = () => {
      if (mapLoaded.value) return;
      if (canvasReadyFallbackTimer) {
        clearTimeout(canvasReadyFallbackTimer);
        canvasReadyFallbackTimer = null;
      }
      mapLoaded.value = true;
      if (martinStatusState.value === "loading") {
        martinStatusState.value = "ready";
      }
      isMvtLoading.value = false;
      emit("mvtLoading", false);
      emit("loaded");

      const el = mapContainer.value;
      lastObservedW = Math.round(el?.clientWidth ?? 0);
      lastObservedH = Math.round(el?.clientHeight ?? 0);
      mapInstance?.resize();
      // Instant initial fitBounds (0ms) so the user immediately sees the target extent
      fitBounds(0);
      // Re-verify fitBounds after splitter/layout settles on initial open
      setTimeout(() => {
        if (
          mapInstance &&
          !hasUserInteractedWithCamera &&
          !isDrawing.value &&
          !isSplitMode.value &&
          !props.selectedFeature
        ) {
          resizeMap();
          fitBounds(0);
        }
      }, 140);
      syncPorosStylingAndFilters();
      syncSelectedRoadSource();
      syncDrawSources();
      syncSplitSource();
      syncPulseMarker();
      void initGeoman().then(() => {
        if (pendingEditorAction) {
          const act = pendingEditorAction;
          pendingEditorAction = null;
          act();
        }
      });
      if (typeof mapInstance?.redraw === "function") {
        mapInstance.redraw();
      }
    };

    mapInstance.on("style.load", () => {
      markCanvasReady();
    });

    mapInstance.on("load", () => {
      markCanvasReady();
    });

    // Fallback safety timer so loading overlay never hangs if a remote tile times out
    canvasReadyFallbackTimer = setTimeout(() => {
      markCanvasReady();
    }, 1800);

    mapInstance.on("zoom", () => {
      const z = mapInstance?.getZoom();
      if (typeof z === "number") {
        currentZoom.value = Number(z.toFixed(1));
      }
    });

    mapInstance.on("pitch", () => {
      const p = mapInstance?.getPitch();
      if (typeof p === "number") {
        currentPitch.value = Math.round(p);
      }
    });

    mapInstance.on("rotate", () => {
      const b = mapInstance?.getBearing();
      if (typeof b === "number") {
        currentBearing.value = Math.round(b);
      }
    });

    mapInstance.on("sourcedataloading", (e: any) => {
      if (e?.sourceId === "martin-poros") {
        isMvtLoading.value = true;
        emit("mvtLoading", true);
      }
    });

    mapInstance.on("sourcedata", (e: any) => {
      if (e?.sourceId === "martin-poros" && e?.isSourceLoaded) {
        isMvtLoading.value = false;
        emit("mvtLoading", false);
        if (martinStatusState.value !== "ready") {
          martinStatusState.value = "ready";
          martinWarningDetail.value = null;
        }
      }
    });

    mapInstance.on("error", (e: any) => {
      const sid = String(e?.sourceId || "");
      if (sid.startsWith("martin-")) {
        tileErrorCount++;
        if (tileErrorCount >= 3) {
          martinStatusState.value = "warning";
          martinWarningDetail.value =
            "Sebagian vector tile Martin belum merespons. Pastikan layanan tile server Martin (port 9090) aktif.";
        }
      }
    });

    bindMapEvents();

    if (typeof ResizeObserver !== "undefined" && mapContainer.value) {
      resizeObserver = new ResizeObserver(() => {
        resizeMap();
      });
      resizeObserver.observe(mapContainer.value);
    }
  } catch (err: any) {
    if (canvasReadyFallbackTimer) {
      clearTimeout(canvasReadyFallbackTimer);
      canvasReadyFallbackTimer = null;
    }
    mapLoaded.value = true;
    isMvtLoading.value = false;
    emit("mvtLoading", false);
    martinStatusState.value = "warning";
    martinWarningDetail.value = err?.message || "Gagal memuat MapLibre GL JS.";
    emit("log", "ERROR", `Gagal memuat MapLibre GL JS: ${err?.message || "Unknown error"}`);
  }
}

// ─── Reactive Watchers ───────────────────────────────────────────────────────
watch(
  () => props.bbox,
  (newBbox) => {
    if (!mapInstance || !mapLoaded.value) return;
    if (!isValidBounds(newBbox)) return;
    if (isDrawing.value || isSplitMode.value || props.selectedFeature) return;

    const nextKey = formatBboxKey(newBbox);
    if (!hasFittedToSummaryBbox || nextKey !== lastFittedBboxKey) {
      const isInitialArrival = !hasFittedToSummaryBbox;
      hasFittedToSummaryBbox = true;
      lastFittedBboxKey = nextKey;
      mapInstance.resize();
      safeFitBounds(newBbox, 52, 16, isInitialArrival ? 360 : 480);
    }
  }
);

watch(
  () => [
    props.layerVisible,
    props.layerOpacity,
    props.kecamatanFilter,
    props.desaFilter,
    props.kondisiFilter,
    props.perkerasanFilter,
    props.symbology,
  ],
  () => {
    syncPorosStylingAndFilters();
  },
  { deep: true }
);

watch(
  () => props.selectedFeature,
  () => {
    syncSelectedRoadSource();
  },
  { deep: true }
);

watch(
  () => props.clickedCoordinate,
  () => {
    syncPulseMarker();
  }
);

watch(
  () => props.basemap,
  (code) => {
    if (mapInstance && mapLoaded.value) {
      applyMapStyle(code);
    }
  }
);

watch(
  () => basemapStore.items,
  () => {
    if (mapInstance && mapLoaded.value && props.basemap !== "none") {
      applyMapStyle(props.basemap);
    }
  }
);

watch(
  () => colorMode.value,
  () => {
    if (mapInstance && mapLoaded.value) {
      applyMapStyle(props.basemap);
    }
  }
);

onMounted(() => {
  initMap();
  window.addEventListener("keydown", handleKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", handleKeydown);
  if (canvasReadyFallbackTimer) {
    clearTimeout(canvasReadyFallbackTimer);
    canvasReadyFallbackTimer = null;
  }
  cancelSplitMode();
  stopDrawing(false);
  if (gmInstance) {
    try {
      void gmInstance.destroy({ removeSources: false });
    } catch {
      // ignore
    }
    gmInstance = null;
  }
  if (pulseMarker) {
    pulseMarker.remove();
    pulseMarker = null;
  }
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});

defineExpose({
  fitBounds,
  zoomIn,
  zoomOut,
  zoomToCentroid,
  zoomToGeometry,
  refreshVectorTiles,
  resizeMap,
  setBasemap,
  isMvtLoading,
  startDrawing,
  startEditingGeometry,
  stopDrawing,
  clearDraw,
  isDrawing,
  isSnappingEnabled,
  toggleSnapping,
  snapRoadCount,
  startSplitMode,
  cancelSplitMode,
  isSplitMode,
});
</script>

<template>
  <div
    class="relative w-full h-full overflow-hidden select-none transition-colors duration-200"
    :class="basemap === 'none' ? 'bg-white' : 'bg-slate-100 dark:bg-[#070b14]'"
  >
    <!-- MapLibre WebGL Canvas -->
    <div
      ref="mapContainer"
      class="absolute inset-0 w-full h-full"
    />

    <!-- Top-Left: Martin Vector Tile Telemetry Pill (when not drawing or splitting) -->
    <div
      v-if="!isDrawing && !isSplitMode"
      class="absolute top-3 left-3 z-20 flex flex-wrap items-center gap-2 pointer-events-none max-w-[calc(100%-6.5rem)]"
    >
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
                : isMvtLoading || martinStatusState === 'loading'
                  ? 'bg-blue-600 animate-pulse'
                  : 'bg-emerald-600',
            ]"
          />
          <span class="text-xs font-medium whitespace-nowrap">
            {{
              martinStatusState === "warning"
                ? "Tile Server Terkendala"
                : isMvtLoading
                  ? "Memuat Vector Tile..."
                  : "Vector Tile Aktif"
            }}
          </span>
        </button>

        <div
          v-if="showMartinPopover"
          class="absolute left-0 top-full mt-1.5 w-72 rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-700 shadow-lg dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200"
        >
          <p class="font-semibold text-gray-900 dark:text-white">
            Telemetri Peta Vektor MapLibre
          </p>
          <p class="mt-1 leading-relaxed text-gray-600 dark:text-gray-300">
            {{
              martinWarningDetail ||
              "Peta merender vector tile MVT jalan_porosdesa secara langsung melalui WebGL MapLibre GL JS."
            }}
          </p>
          <div class="mt-2 flex items-center justify-between text-[11px] text-gray-600 dark:text-gray-400">
            <span class="tabular-nums">Zoom {{ currentZoom }}x · Pitch {{ currentPitch }}°</span>
            <button
              type="button"
              class="min-h-[32px] px-2 font-medium text-blue-700 hover:underline dark:text-blue-400 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded"
              @click="showMartinPopover = false"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Floating Split Mode Banner (Top-Left) -->
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
        class="absolute top-3 left-3 z-30 flex items-center gap-2.5 px-3 py-2 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-amber-500/60 rounded-xl shadow-lg text-xs select-none max-w-sm sm:max-w-md"
      >
        <div class="size-7 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-scissors" class="size-4" />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="font-semibold text-gray-900 dark:text-white leading-tight truncate">
            Mode Split Ruas Jalan
          </span>
          <span class="text-[11px] text-gray-600 dark:text-gray-300 leading-tight truncate">
            Klik badan ruas
            <span class="font-semibold text-amber-700 dark:text-amber-400">
              "{{ splitRoadData?.nama_ruas || splitRoadData?.properties?.nama_ruas || "jalan" }}"
            </span>
            <template v-if="splitPreviewPoint">
              · STA {{ splitPreviewPoint.alongMeters.toLocaleString("id-ID") }} m
            </template>
          </span>
        </div>
        <UButton
          icon="i-lucide-x"
          size="xs"
          color="neutral"
          variant="ghost"
          class="ml-auto text-gray-500 hover:text-red-600 dark:hover:text-red-400 shrink-0"
          aria-label="Batal mode split"
          title="Batal Split (Esc)"
          @click="cancelSplitMode"
        />
      </div>
    </Transition>

    <!-- Floating Vertical Draw / Vertex Edit Control Toolbar (Top-Left) -->
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
        <div class="flex flex-col gap-0.5 p-1 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-xl shadow-md">
          <!-- 1. Draw / Finish -->
          <UTooltip :text="isDrawInteracting ? 'Selesai Gambar (Enter)' : 'Lanjut Tambah Titik (Draw)'">
            <UButton
              :icon="isDrawInteracting ? 'i-lucide-pencil' : 'i-lucide-pen-tool'"
              size="xs"
              :color="isDrawInteracting ? 'primary' : 'neutral'"
              :variant="isDrawInteracting ? 'solid' : 'ghost'"
              aria-label="Mode gambar garis"
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
              aria-label="Gambar ulang dari awal"
              @click="handleRedraw"
            />
          </UTooltip>

          <!-- 3. Undo -->
          <UTooltip text="Undo (Ctrl+Z)">
            <UButton
              icon="i-lucide-undo-2"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="!canUndo"
              aria-label="Undo perubahan terakhir"
              @click="handleUndo"
            />
          </UTooltip>

          <!-- 4. Redo -->
          <UTooltip text="Redo (Ctrl+Y)">
            <UButton
              icon="i-lucide-redo-2"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="!canRedo"
              aria-label="Redo perubahan"
              @click="handleRedo"
            />
          </UTooltip>

          <div class="w-full h-px bg-gray-200 dark:bg-gray-800 my-0.5" />

          <!-- 5. Magnetic Snapping Toggle + Live Count Pill -->
          <div class="relative flex items-center">
            <UTooltip
              :text="
                isSnappingEnabled
                  ? 'Snapping Aktif: Magnet Ruas & Titik'
                  : 'Snapping Nonaktif (Klik untuk Aktifkan)'
              "
            >
              <UButton
                icon="i-lucide-magnet"
                size="xs"
                :color="isSnappingEnabled ? 'primary' : 'neutral'"
                :variant="isSnappingEnabled ? 'solid' : 'ghost'"
                aria-label="Toggle snapping magnet ruas jalan"
                @click="toggleSnapping"
              />
            </UTooltip>

            <button
              type="button"
              class="absolute left-full ml-1.5 flex items-center gap-1.5 px-2 py-0.5 rounded-md border text-[10px] font-medium select-none whitespace-nowrap shadow-xs transition-all cursor-pointer"
              :class="
                isSnappingEnabled
                  ? 'bg-blue-50/95 dark:bg-blue-950/85 border-blue-200 dark:border-blue-800/80 text-blue-700 dark:text-blue-300'
                  : 'bg-white/95 dark:bg-[#0b0f19]/95 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400'
              "
              @click="toggleSnapping"
            >
              <span
                class="size-1.5 rounded-full shrink-0"
                :class="isSnappingEnabled ? 'bg-blue-600' : 'bg-gray-400 dark:bg-gray-500'"
              />
              <span>{{ isSnappingEnabled ? "Snap Aktif" : "Snap Mati" }}</span>
              <UIcon
                v-if="isSnappingEnabled && isSnapLoading"
                name="i-lucide-loader-2"
                class="size-2.5 animate-spin text-blue-600"
              />
              <span
                v-else-if="isSnappingEnabled && snapRoadCount > 0"
                class="text-[10px] font-mono text-blue-700 dark:text-blue-300 tabular-nums"
              >
                ({{ snapRoadCount }})
              </span>
            </button>
          </div>

          <div class="w-full h-px bg-gray-200 dark:bg-gray-800 my-0.5" />

          <!-- 6. Save Geometry + Live Length & Vertex Pill -->
          <div class="relative flex items-center">
            <UTooltip text="Simpan Garis & Buka Form">
              <UButton
                icon="i-lucide-check"
                size="xs"
                color="primary"
                variant="solid"
                :disabled="!canSave"
                aria-label="Simpan geometri garis"
                @click="confirmDraw"
              />
            </UTooltip>

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
                class="absolute left-full ml-1.5 flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[11px] select-none whitespace-nowrap shadow-sm backdrop-blur-md bg-white/95 dark:bg-[#0b0f19]/95 border-gray-200 dark:border-gray-800"
              >
                <div class="flex items-center gap-1 font-mono font-semibold text-blue-600 dark:text-blue-400 tabular-nums">
                  <UIcon name="i-lucide-route" class="size-3 shrink-0" />
                  <span>{{ drawnLengthMeters.toLocaleString("id-ID") }} m</span>
                </div>
                <div class="w-px h-3 bg-gray-200 dark:bg-gray-800 shrink-0" />
                <div class="flex items-center gap-1 text-gray-600 dark:text-gray-300 font-mono tabular-nums">
                  <span>{{ drawnPointCount }} titik</span>
                  <span
                    v-if="isEditingExisting"
                    class="text-[10px] font-sans font-semibold text-amber-600 dark:text-amber-400"
                  >
                    (Edit)
                  </span>
                  <span
                    v-else-if="hasCompletedLine"
                    class="text-[10px] font-sans font-semibold text-emerald-600 dark:text-emerald-400"
                  >
                    (Siap)
                  </span>
                </div>
              </div>
            </Transition>
          </div>

          <!-- 7. Cancel Draw -->
          <UTooltip text="Batal & Tutup Draw (Esc)">
            <UButton
              icon="i-lucide-x"
              size="xs"
              color="neutral"
              variant="ghost"
              class="text-gray-500 hover:text-red-600 dark:hover:text-red-400"
              aria-label="Batal mode gambar"
              @click="stopDrawing(true)"
            />
          </UTooltip>
        </div>

        <!-- Compact Keyboard / Edit Hint Pill -->
        <div
          class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-gray-200/90 dark:border-gray-800 bg-white/92 dark:bg-[#0b0f19]/92 backdrop-blur-md shadow-xs text-[10px] text-gray-600 dark:text-gray-300"
        >
          <UIcon name="i-lucide-info" class="size-3 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <span v-if="isDrawInteracting">
            Klik peta tambah titik · Klik ganda / Enter selesai
          </span>
          <span v-else>
            Geser vertex · Klik titik tengah sisip · Klik kanan hapus
          </span>
        </div>
      </div>
    </Transition>

    <!-- Top-Right: Basemap Switcher + Vertical GIS Navigation Controls (Jaringan Jalan Reference) -->
    <div class="absolute top-3 right-3 z-20 flex flex-col items-end gap-2">
      <BasemapSwitcher
        :model-value="basemap"
        engine="maplibre"
        variant="medium"
        :allow-none="true"
        placement="top-right"
        @update:model-value="setBasemap($event as BasemapType)"
      />

      <div
        class="flex flex-col rounded-lg border border-gray-200/90 bg-white/95 shadow-sm backdrop-blur-md overflow-hidden dark:border-gray-700/90 dark:bg-[#0e1424]/95 divide-y divide-gray-200 dark:divide-gray-800"
      >
        <button
          type="button"
          aria-label="Perbesar Peta"
          title="Perbesar (+)"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="zoomIn"
        >
          <UIcon name="i-lucide-plus" class="size-4" />
        </button>
        <button
          type="button"
          aria-label="Perkecil Peta"
          title="Perkecil (-)"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="zoomOut"
        >
          <UIcon name="i-lucide-minus" class="size-4" />
        </button>
        <button
          type="button"
          :aria-pressed="is3DTiltActive"
          aria-label="Ubah tampilan 2D dan 3D Tilt"
          :title="is3DTiltActive ? 'Kembali ke 2D' : 'Tampilan 3D Tilt'"
          :class="[
            'size-11 lg:size-8 flex items-center justify-center text-[11px] font-bold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
            is3DTiltActive
              ? 'bg-blue-600 text-white hover:bg-blue-700'
              : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800',
          ]"
          @click="toggle3DTilt"
        >
          3D
        </button>
        <button
          type="button"
          aria-label="Fokus semua ruas jalan"
          title="Fokus Semua Ruas"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="fitBounds()"
        >
          <UIcon name="i-lucide-maximize-2" class="size-4" />
        </button>
        <button
          v-if="currentBearing !== 0 || currentPitch !== 0"
          type="button"
          aria-label="Reset arah utara dan kemiringan"
          title="Reset Utara & 2D"
          class="size-11 lg:size-8 flex items-center justify-center text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="resetNorthAndTilt"
        >
          <UIcon name="i-lucide-compass" class="size-4" />
        </button>
      </div>
    </div>

    <!-- Interactive Map Hover Tooltip (Clamped inside Canvas) -->
    <div
      v-if="hoverTooltip"
      :style="getTooltipStyle()"
      class="pointer-events-none absolute z-20 w-60 rounded-xl border border-gray-200 bg-white/95 p-2.5 text-xs shadow-xl backdrop-blur-md dark:border-gray-700 dark:bg-[#0e1424]/95 space-y-1.5"
    >
      <div class="flex items-center gap-1.5">
        <span class="size-2 rounded-full bg-blue-500 shrink-0" />
        <p class="font-semibold text-gray-900 dark:text-white truncate">
          {{ hoverTooltip.nama_ruas }}
        </p>
      </div>
      <div class="flex items-center justify-between gap-2">
        <UBadge
          :label="hoverTooltip.kondisi || '-'"
          :color="getKondisiBadgeColor(hoverTooltip.kondisi)"
          variant="subtle"
          size="xs"
        />
        <span class="font-mono text-gray-800 dark:text-gray-200 text-[11px] font-semibold tabular-nums">
          {{ Number(hoverTooltip.panjang_meter).toLocaleString("id-ID", { maximumFractionDigits: 1 }) }} m
        </span>
      </div>
      <div class="flex items-center gap-1 text-[11px] text-gray-600 dark:text-gray-300 truncate pt-1 border-t border-gray-100 dark:border-gray-800">
        <UIcon name="i-lucide-map-pin" class="size-3 shrink-0 text-blue-500" />
        <span class="truncate">{{ hoverTooltip.desa }}, Kec. {{ hoverTooltip.kecamatan }}</span>
      </div>
    </div>

    <!-- Bottom-Left Live WGS84 Coordinate & Zoom Telemetry HUD -->
    <div
      class="pointer-events-none absolute left-3 bottom-[4.75rem] lg:bottom-2.5 z-10 hidden sm:flex items-center gap-2 rounded-md border border-gray-200/90 bg-white/92 px-2.5 py-1 text-[11px] font-medium text-gray-700 shadow-xs backdrop-blur-md tabular-nums dark:border-gray-700/90 dark:bg-[#0e1424]/92 dark:text-gray-200"
    >
      <span>Zoom {{ currentZoom }}x</span>
      <template v-if="currentPitch > 0">
        <span class="text-gray-400">·</span>
        <span>Tilt {{ currentPitch }}°</span>
      </template>
      <template v-if="cursorCoord">
        <span class="text-gray-400">|</span>
        <span>{{ cursorCoord.lat.toFixed(5) }}, {{ cursorCoord.lng.toFixed(5) }}</span>
      </template>
    </div>

    <!-- Loading Overlay -->
    <div
      v-if="!mapLoaded"
      class="absolute inset-0 flex items-center justify-center bg-gray-100/90 dark:bg-[#070b14]/90 z-30"
    >
      <div class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-lg text-xs font-medium text-gray-700 dark:text-gray-200">
        <UIcon name="i-lucide-loader-2" class="size-4 text-blue-600 dark:text-blue-400 animate-spin" />
        <span>Memuat kanvas MapLibre GL JS...</span>
      </div>
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

@media (prefers-reduced-motion: reduce) {
  .transition-all,
  .transition-colors {
    transition-duration: 0.01ms !important;
  }
}
</style>

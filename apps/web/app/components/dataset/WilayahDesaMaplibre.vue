<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";
import { useDefaultBasemap } from "~/composables/useDefaultBasemap";
import { useBasemapStore } from "~/stores/basemap";
import {
  buildDynamicMaplibreBasemapSpec,
  resolveAbsoluteTileUrl,
} from "~/utils/basemap-sources";
import { computeGeometryBbox } from "~/utils/jaringan-jalan-format";

export interface DesaMapFeaturePayload {
  id: number;
  nama_desa: string;
  id_kecamatan: number | null;
  nama_pimpinan: string;
  nama_jabatan: string;
  nip: string;
  pangkat_gol: string;
  luas_hektar: number;
  keliling_meter: number;
  geometry?: any;
  bbox?: [number, number, number, number] | null;
}

interface Props {
  selectedKecamatanId: number | null;
  selectedDesaId: number | null;
  selectedGeometry?: any;
  formPreviewGeometry?: any;
  summaryBbox?: [number, number, number, number] | null;
  activeKecamatanBbox?: [number, number, number, number] | null;
  activeKecamatanName?: string | null;
  resolveKecamatanName: (kecId: number | null | undefined) => string;
  refreshNonce?: number;
  basemap?: string;
  clickedCoordinate?: [number, number] | null;
}

const props = withDefaults(defineProps<Props>(), {
  selectedGeometry: null,
  formPreviewGeometry: null,
  summaryBbox: null,
  activeKecamatanBbox: null,
  activeKecamatanName: null,
  refreshNonce: 0,
  basemap: undefined,
  clickedCoordinate: null,
});

const emit = defineEmits<{
  (e: "select-desa", payload: DesaMapFeaturePayload): void;
  (e: "clear-selection"): void;
  (e: "fit-bojonegoro"): void;
  (e: "focus-kecamatan"): void;
  (e: "update:basemap", code: string): void;
  (e: "map-click", coordinate: [number, number]): void;
  (e: "log", level: "INFO" | "WARN" | "ERROR", message: string): void;
}>();

const BOJONEGORO_BOUNDS: [[number, number], [number, number]] = [
  [111.415, -7.475],
  [112.17, -6.975],
];

const config = useRuntimeConfig();
const colorMode = useColorMode();
const basemapStore = useBasemapStore();
const { currentBasemap, setBasemapChoice } = useDefaultBasemap<string>(
  "maplibre",
  (code) => {
    if (!props.basemap) {
      applyBasemapSwitch(code);
    }
  }
);

const activeBasemap = computed(() => props.basemap ?? currentBasemap.value);

const mapContainerRef = ref<HTMLElement | null>(null);
const isMapReady = ref(false);
const isMvtLoading = ref(false);
const martinStatusState = ref<"ready" | "loading" | "warning">("loading");
const martinWarningDetail = ref<string | null>(null);
const showMartinPopover = ref(false);
const showLegendPopover = ref(false);

const currentZoom = ref(10.2);
const currentPitch = ref(0);
const currentBearing = ref(0);
const cursorCoord = ref<{ lng: number; lat: number } | null>(null);
const hoveredFeatureId = ref<number | string | null>(null);
const hoverTooltip = ref<{
  x: number;
  y: number;
  nama_desa: string;
  kecamatan: string;
  luas_hektar: number | null;
  pimpinan: string;
} | null>(null);

let mapInstance: any = null;
let maplibregl: any = null;
let pulseMarker: any = null;
let resizeObserver: ResizeObserver | null = null;
let canvasReadyFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let lastObservedW = 0;
let lastObservedH = 0;
let tileErrorCount = 0;
let appliedSelectedId: number | null = null;
let appliedHoveredId: number | string | null = null;

const isDarkTheme = computed(() => colorMode.value === "dark");
const is3DTiltActive = computed(() => currentPitch.value >= 15);
const martinBase = computed(() =>
  String(config.public.martinUrl || "/martin").replace(/\/+$/, "")
);

function prefersReducedMotion(): boolean {
  if (!import.meta.client || typeof window === "undefined") return false;
  return window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
}

function getMotionDuration(ms: number, fallbackMs = 0): number {
  if (prefersReducedMotion()) return 0;
  if (typeof ms === "number" && Number.isFinite(ms)) return Math.max(0, ms);
  return Math.max(0, fallbackMs);
}

function buildMartinTileUrl(sourceId: string): string {
  const base = resolveAbsoluteTileUrl(`${martinBase.value}/${sourceId}/{z}/{x}/{y}`);
  return props.refreshNonce > 0 ? `${base}?v=${props.refreshNonce}` : base;
}

function buildGeoJsonFeatureCollection(geom: any) {
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

function buildDesaFillColorExpr(): any {
  const kecId = props.selectedKecamatanId;
  const dark = isDarkTheme.value;

  const isSelected: any = ["boolean", ["feature-state", "selected"], false];
  const isHovered: any = ["boolean", ["feature-state", "hover"], false];

  if (kecId !== null) {
    const isSameKec: any = ["==", ["to-number", ["get", "id_kecamatan"], -999], kecId];
    return [
      "case",
      isSelected,
      dark ? "rgba(56, 189, 248, 0.42)" : "rgba(14, 165, 233, 0.42)",
      isHovered,
      dark ? "rgba(52, 211, 153, 0.46)" : "rgba(16, 185, 129, 0.46)",
      isSameKec,
      dark ? "rgba(16, 185, 129, 0.30)" : "rgba(16, 185, 129, 0.28)",
      dark ? "rgba(15, 23, 42, 0.22)" : "rgba(148, 163, 184, 0.12)",
    ];
  }

  return [
    "case",
    isSelected,
    dark ? "rgba(56, 189, 248, 0.42)" : "rgba(14, 165, 233, 0.42)",
    isHovered,
    dark ? "rgba(52, 211, 153, 0.46)" : "rgba(16, 185, 129, 0.46)",
    dark ? "rgba(16, 185, 129, 0.20)" : "rgba(16, 185, 129, 0.18)",
  ];
}

function buildDesaOutlineColorExpr(): any {
  const kecId = props.selectedKecamatanId;
  const dark = isDarkTheme.value;

  const isSelected: any = ["boolean", ["feature-state", "selected"], false];
  const isHovered: any = ["boolean", ["feature-state", "hover"], false];

  if (kecId !== null) {
    const isSameKec: any = ["==", ["to-number", ["get", "id_kecamatan"], -999], kecId];
    return [
      "case",
      isSelected,
      "#0284c7",
      isHovered,
      "#10b981",
      isSameKec,
      dark ? "rgba(52, 211, 153, 0.92)" : "rgba(5, 150, 105, 0.92)",
      dark ? "rgba(148, 163, 184, 0.18)" : "rgba(100, 116, 139, 0.28)",
    ];
  }

  return [
    "case",
    isSelected,
    "#0284c7",
    isHovered,
    "#10b981",
    dark ? "rgba(52, 211, 153, 0.85)" : "rgba(5, 150, 105, 0.85)",
  ];
}

function buildDesaOutlineWidthExpr(): any {
  const kecId = props.selectedKecamatanId;

  const isSelected: any = ["boolean", ["feature-state", "selected"], false];
  const isHovered: any = ["boolean", ["feature-state", "hover"], false];

  if (kecId !== null) {
    const isSameKec: any = ["==", ["to-number", ["get", "id_kecamatan"], -999], kecId];
    return [
      "case",
      isSelected,
      3.2,
      isHovered,
      2.6,
      isSameKec,
      1.8,
      0.7,
    ];
  }

  return [
    "case",
    isSelected,
    3.2,
    isHovered,
    2.5,
    1.25,
  ];
}

function buildKecamatanContextColorExpr(): any {
  const kecId = props.selectedKecamatanId;
  const dark = isDarkTheme.value;
  if (kecId !== null) {
    return [
      "case",
      ["==", ["to-number", ["get", "id"], -999], kecId],
      dark ? "#38bdf8" : "#0284c7",
      dark ? "rgba(148, 163, 184, 0.22)" : "rgba(71, 85, 105, 0.28)",
    ];
  }
  return dark ? "rgba(148, 163, 184, 0.45)" : "rgba(51, 65, 85, 0.42)";
}

function buildKecamatanContextWidthExpr(): any {
  const kecId = props.selectedKecamatanId;
  if (kecId !== null) {
    return [
      "case",
      ["==", ["to-number", ["get", "id"], -999], kecId],
      2.4,
      0.8,
    ];
  }
  return ["interpolate", ["linear"], ["zoom"], 8, 1, 13, 1.8];
}

function buildMapStyle(basemapCode: string) {
  const item = basemapStore.getByCode(basemapCode, "maplibre");
  const { sources, layers } =
    basemapCode === "none"
      ? { sources: {}, layers: [] }
      : buildDynamicMaplibreBasemapSpec(item ?? basemapCode, {
          baseOpacity: 1,
        });

  sources["martin-desa"] = {
    type: "vector",
    tiles: [buildMartinTileUrl("bataswilayah_desa")],
    minzoom: 8,
    maxzoom: 18,
    promoteId: { bataswilayah_desa: "id" },
  };

  sources["martin-kecamatan"] = {
    type: "vector",
    tiles: [buildMartinTileUrl("bataswilayah_kecamatan")],
    minzoom: 8,
    maxzoom: 18,
  };

  sources["selected-desa-geojson"] = {
    type: "geojson",
    data: buildGeoJsonFeatureCollection(props.selectedGeometry),
  };

  sources["form-preview-geojson"] = {
    type: "geojson",
    data: buildGeoJsonFeatureCollection(props.formPreviewGeometry),
  };

  layers.push(
    {
      id: "layer-desa-fill",
      type: "fill",
      source: "martin-desa",
      "source-layer": "bataswilayah_desa",
      paint: {
        "fill-color": buildDesaFillColorExpr(),
        "fill-color-transition": { duration: 0, delay: 0 },
        "fill-opacity": 1,
      },
    },
    {
      id: "layer-desa-outline",
      type: "line",
      source: "martin-desa",
      "source-layer": "bataswilayah_desa",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": buildDesaOutlineColorExpr(),
        "line-color-transition": { duration: 0, delay: 0 },
        "line-width": buildDesaOutlineWidthExpr(),
        "line-width-transition": { duration: 0, delay: 0 },
      },
    },
    {
      id: "layer-kecamatan-context",
      type: "line",
      source: "martin-kecamatan",
      "source-layer": "bataswilayah_kecamatan",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": buildKecamatanContextColorExpr(),
        "line-width": buildKecamatanContextWidthExpr(),
        "line-dasharray": [4, 2],
      },
    },
    {
      id: "layer-selected-geojson-fill",
      type: "fill",
      source: "selected-desa-geojson",
      paint: {
        "fill-color": "#0ea5e9",
        "fill-opacity": 0.24,
      },
    },
    {
      id: "layer-selected-geojson-casing",
      type: "line",
      source: "selected-desa-geojson",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#ffffff",
        "line-width": 5,
        "line-opacity": 0.85,
      },
    },
    {
      id: "layer-selected-geojson-line",
      type: "line",
      source: "selected-desa-geojson",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#0284c7",
        "line-width": 2.8,
      },
    },
    {
      id: "layer-form-preview-fill",
      type: "fill",
      source: "form-preview-geojson",
      paint: {
        "fill-color": "#f59e0b",
        "fill-opacity": 0.22,
      },
    },
    {
      id: "layer-form-preview-casing",
      type: "line",
      source: "form-preview-geojson",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#ffffff",
        "line-width": 5,
        "line-opacity": 0.88,
      },
    },
    {
      id: "layer-form-preview-line",
      type: "line",
      source: "form-preview-geojson",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#d97706",
        "line-width": 3,
        "line-dasharray": [3, 1.5],
      },
    }
  );

  return {
    version: 8 as const,
    transition: { duration: 0, delay: 0 },
    sources,
    layers,
  };
}

function setDesaFeatureState(id: number | string, state: Record<string, boolean>) {
  if (!mapInstance || !mapInstance.getSource("martin-desa")) return;
  try {
    mapInstance.setFeatureState(
      {
        source: "martin-desa",
        sourceLayer: "bataswilayah_desa",
        id,
      },
      state
    );
  } catch {
    // Ignore while style is mid-swap
  }
}

function syncSelectedFeatureState(force = false) {
  if (!mapInstance || !mapInstance.getSource("martin-desa")) return;
  const nextId = props.selectedDesaId ?? null;

  if (force) {
    if (appliedSelectedId !== null && appliedSelectedId !== nextId) {
      setDesaFeatureState(appliedSelectedId, { selected: false });
    }
    if (nextId !== null) {
      setDesaFeatureState(nextId, { selected: true });
    }
    appliedSelectedId = nextId;
    return;
  }

  if (appliedSelectedId === nextId) return;
  if (appliedSelectedId !== null) {
    setDesaFeatureState(appliedSelectedId, { selected: false });
  }
  if (nextId !== null) {
    setDesaFeatureState(nextId, { selected: true });
  }
  appliedSelectedId = nextId;
}

function setHoveredFeature(nextId: number | string | null) {
  hoveredFeatureId.value = nextId;
  if (!mapInstance || !mapInstance.getSource("martin-desa")) return;
  if (appliedHoveredId === nextId) return;
  if (appliedHoveredId !== null) {
    setDesaFeatureState(appliedHoveredId, { hover: false });
  }
  if (nextId !== null) {
    setDesaFeatureState(nextId, { hover: true });
  }
  appliedHoveredId = nextId;
}

function syncFeatureStates(force = false) {
  syncSelectedFeatureState(force);
  if (force && hoveredFeatureId.value !== null) {
    setDesaFeatureState(hoveredFeatureId.value, { hover: true });
    appliedHoveredId = hoveredFeatureId.value;
  }
}

function syncDynamicPaints() {
  if (!mapInstance) return;
  if (mapInstance.getLayer("layer-desa-fill")) {
    mapInstance.setPaintProperty("layer-desa-fill", "fill-color", buildDesaFillColorExpr());
  }
  if (mapInstance.getLayer("layer-desa-outline")) {
    mapInstance.setPaintProperty("layer-desa-outline", "line-color", buildDesaOutlineColorExpr());
    mapInstance.setPaintProperty("layer-desa-outline", "line-width", buildDesaOutlineWidthExpr());
  }
  if (mapInstance.getLayer("layer-kecamatan-context")) {
    mapInstance.setPaintProperty(
      "layer-kecamatan-context",
      "line-color",
      buildKecamatanContextColorExpr()
    );
    mapInstance.setPaintProperty(
      "layer-kecamatan-context",
      "line-width",
      buildKecamatanContextWidthExpr()
    );
  }
}

function syncSelectedGeoJsonOverlay() {
  if (!mapInstance) return;
  const src = mapInstance.getSource("selected-desa-geojson");
  if (src && typeof src.setData === "function") {
    src.setData(buildGeoJsonFeatureCollection(props.selectedGeometry));
  }
}

function syncFormPreviewGeoJsonOverlay() {
  if (!mapInstance) return;
  const src = mapInstance.getSource("form-preview-geojson");
  if (src && typeof src.setData === "function") {
    src.setData(buildGeoJsonFeatureCollection(props.formPreviewGeometry));
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

  const lng = Number(props.clickedCoordinate[0]);
  const lat = Number(props.clickedCoordinate[1]);
  if (!Number.isFinite(lng) || !Number.isFinite(lat)) return;

  if (!pulseMarker) {
    const el = document.createElement("div");
    el.className = "pointer-events-none flex items-center justify-center size-7";
    el.innerHTML =
      '<span class="size-3.5 rounded-full bg-blue-600 ring-2 ring-white shadow-md flex items-center justify-center">' +
      '<span class="size-1 rounded-full bg-white"></span></span>';
    pulseMarker = new maplibregl.Marker({ element: el, anchor: "center" });
  }
  pulseMarker.setLngLat([lng, lat]).addTo(mapInstance);
}

function applyBasemapSwitch(code: string) {
  if (!mapInstance) return;
  appliedSelectedId = null;
  appliedHoveredId = null;
  mapInstance.setStyle(buildMapStyle(code));
  mapInstance.once("styledata", () => {
    syncDynamicPaints();
    syncFeatureStates(true);
    syncSelectedGeoJsonOverlay();
    syncFormPreviewGeoJsonOverlay();
    syncPulseMarker();
  });
}

function handleBasemapChange(code: string) {
  setBasemapChoice(code);
  emit("update:basemap", code);
  if (props.basemap !== undefined) {
    applyBasemapSwitch(code);
  }
}

function isValidLngLatBounds(
  b: [number, number, number, number] | null | undefined
): b is [number, number, number, number] {
  if (!Array.isArray(b) || b.length !== 4) return false;
  const nums = b.map((v: unknown) =>
    typeof v === "number"
      ? v
      : typeof v === "string" && v.trim() !== ""
        ? Number(v)
        : NaN
  );
  const [minX, minY, maxX, maxY] = nums as [number, number, number, number];
  if (![minX, minY, maxX, maxY].every(Number.isFinite)) return false;
  if (minX > maxX || minY > maxY) return false;
  return minX >= -180 && maxX <= 180 && minY >= -85 && maxY <= 85;
}

function safeFitBounds(
  bbox: [number, number, number, number] | null | undefined,
  desiredPadding = 56,
  maxZoom = 14.5,
  duration = 550
) {
  if (!mapInstance || !isValidLngLatBounds(bbox)) return;
  const el = mapContainerRef.value;
  const w = el?.clientWidth ?? 0;
  const h = el?.clientHeight ?? 0;
  if (w < 80 || h < 80) return;

  const [minX, minY, maxX, maxY] = bbox.map(Number) as [number, number, number, number];
  const motionMs = getMotionDuration(duration, 550);

  if (Math.abs(maxX - minX) < 1e-6 && Math.abs(maxY - minY) < 1e-6) {
    mapInstance.easeTo({
      center: [minX, minY],
      zoom: Math.min(maxZoom, 14),
      duration: motionMs,
    });
    return;
  }

  const maxAllowedPad = Math.max(8, Math.floor((Math.min(w, h) - 32) / 2));
  const safePad = Math.max(8, Math.min(desiredPadding, maxAllowedPad));

  try {
    mapInstance.fitBounds(
      [
        [minX, minY],
        [maxX, maxY],
      ],
      {
        padding: safePad,
        maxZoom,
        duration: motionMs,
      }
    );
  } catch {
    // Ignore when panel is temporarily narrow during splitter drag
  }
}

function fitToSummaryOrBojonegoro(duration = 550) {
  const safeDuration =
    typeof duration === "number" && Number.isFinite(duration) ? Math.max(0, duration) : 550;
  if (isValidLngLatBounds(props.summaryBbox)) {
    safeFitBounds(props.summaryBbox, 52, 13.5, safeDuration);
    return;
  }
  const flatBounds: [number, number, number, number] = [
    BOJONEGORO_BOUNDS[0][0],
    BOJONEGORO_BOUNDS[0][1],
    BOJONEGORO_BOUNDS[1][0],
    BOJONEGORO_BOUNDS[1][1],
  ];
  safeFitBounds(flatBounds, 44, 13.5, safeDuration);
}

function flyToCoords(coords: [number, number], zoom = 14) {
  if (!mapInstance || !Array.isArray(coords) || coords.length < 2) return;
  const lng = Number(coords[0]);
  const lat = Number(coords[1]);
  if (!Number.isFinite(lng) || !Number.isFinite(lat) || lng < -180 || lng > 180 || lat < -90 || lat > 90) {
    return;
  }
  mapInstance.flyTo({
    center: [lng, lat],
    zoom,
    duration: getMotionDuration(650),
    essential: true,
  });
}

function fitToGeometry(geom: any) {
  const bbox = computeGeometryBbox(geom);
  if (bbox) {
    safeFitBounds(bbox, 64, 15, 550);
  }
}

function zoomBy(delta: number) {
  if (!mapInstance) return;
  const z = mapInstance.getZoom() ?? 10.2;
  mapInstance.easeTo({
    zoom: Math.max(8, Math.min(18, z + delta)),
    duration: getMotionDuration(220),
  });
}

function toggle3DTilt() {
  if (!mapInstance) return;
  const nextPitch = is3DTiltActive.value ? 0 : 42;
  mapInstance.easeTo({
    pitch: nextPitch,
    duration: getMotionDuration(360),
  });
}

function resetNorthAndTilt() {
  if (!mapInstance) return;
  mapInstance.easeTo({
    bearing: 0,
    pitch: 0,
    duration: getMotionDuration(320),
  });
}

function resizeMap() {
  const el = mapContainerRef.value;
  if (!el || !mapInstance) return;
  const w = Math.round(el.clientWidth);
  const h = Math.round(el.clientHeight);
  if (w < 40 || h < 40) return;
  if (w === lastObservedW && h === lastObservedH) return;
  lastObservedW = w;
  lastObservedH = h;
  mapInstance.resize();
  if (isMapReady.value && typeof mapInstance.redraw === "function") {
    mapInstance.redraw();
  }
}

function getTooltipStyle() {
  if (!hoverTooltip.value) return {};
  const width = mapContainerRef.value?.clientWidth ?? 800;
  const height = mapContainerRef.value?.clientHeight ?? 600;
  const cardWidth = 224;
  const cardHeight = 112;
  const left = Math.min(Math.max(12, hoverTooltip.value.x + 14), Math.max(12, width - cardWidth - 12));
  const top = Math.min(Math.max(56, hoverTooltip.value.y - cardHeight - 10), Math.max(56, height - cardHeight - 16));
  return {
    left: `${left}px`,
    top: `${top}px`,
  };
}

function extractDesaPayload(feat: any): DesaMapFeaturePayload | null {
  if (!feat) return null;
  const p = feat.properties || {};
  const id = Number(p.id ?? feat.id);
  if (!Number.isFinite(id) || id <= 0) return null;

  const kecIdRaw = p.id_kecamatan;
  const kecId = kecIdRaw !== undefined && kecIdRaw !== null && kecIdRaw !== "" ? Number(kecIdRaw) : null;
  const luasRaw = p.luas_hektar !== undefined && p.luas_hektar !== null ? Number(p.luas_hektar) : 0;
  const kelilingRaw = p.keliling_meter !== undefined && p.keliling_meter !== null ? Number(p.keliling_meter) : 0;
  const bbox = feat.geometry ? computeGeometryBbox(feat.geometry) : null;

  return {
    id,
    nama_desa: String(p.nama_desa || `Desa #${id}`),
    id_kecamatan: kecId !== null && Number.isFinite(kecId) ? kecId : null,
    nama_pimpinan: String(p.nama_pimpinan || "-"),
    nama_jabatan: String(p.nama_jabatan || "-"),
    nip: String(p.nip || "-"),
    pangkat_gol: String(p.pangkat_gol || "-"),
    luas_hektar: Number.isFinite(luasRaw) ? luasRaw : 0,
    keliling_meter: Number.isFinite(kelilingRaw) ? kelilingRaw : 0,
    bbox,
  };
}

function bindInteractiveEvents() {
  if (!mapInstance) return;

  mapInstance.on("mousemove", (e: any) => {
    if (e?.lngLat) {
      cursorCoord.value = {
        lng: Number(e.lngLat.lng.toFixed(5)),
        lat: Number(e.lngLat.lat.toFixed(5)),
      };
    }

    if (!mapInstance.getLayer("layer-desa-fill")) return;

    const features = mapInstance.queryRenderedFeatures(e.point, {
      layers: ["layer-desa-fill"],
    });

    if (!features || features.length === 0) {
      mapInstance.getCanvas().style.cursor = "";
      setHoveredFeature(null);
      hoverTooltip.value = null;
      return;
    }

    const top = features[0];
    const p = top.properties || {};
    const rawId = top.id ?? p.id;
    const fId = Number(rawId);
    const nextHoverId =
      rawId !== undefined && rawId !== null && rawId !== "" && Number.isFinite(fId)
        ? typeof top.id === "string" || typeof top.id === "number"
          ? top.id
          : fId
        : null;
    const kecId = p.id_kecamatan !== undefined && p.id_kecamatan !== null ? Number(p.id_kecamatan) : null;
    const luasParsed = p.luas_hektar !== undefined && p.luas_hektar !== null ? Number(p.luas_hektar) : null;

    mapInstance.getCanvas().style.cursor = "pointer";
    setHoveredFeature(nextHoverId);

    hoverTooltip.value = {
      x: e.point.x,
      y: e.point.y,
      nama_desa: String(p.nama_desa || "Desa"),
      kecamatan: props.resolveKecamatanName(kecId),
      luas_hektar: luasParsed !== null && Number.isFinite(luasParsed) && luasParsed > 0 ? luasParsed : null,
      pimpinan: String(p.nama_pimpinan || "-"),
    };
  });

  mapInstance.on("dragstart", () => {
    setHoveredFeature(null);
    hoverTooltip.value = null;
  });

  mapInstance.on("mouseleave", () => {
    if (mapInstance) {
      mapInstance.getCanvas().style.cursor = "";
    }
    setHoveredFeature(null);
    hoverTooltip.value = null;
  });

  mapInstance.on("click", (e: any) => {
    showMartinPopover.value = false;
    showLegendPopover.value = false;
    if (e?.lngLat) {
      emit("map-click", [Number(e.lngLat.lng), Number(e.lngLat.lat)]);
    }
    if (!mapInstance.getLayer("layer-desa-fill")) return;

    const features = mapInstance.queryRenderedFeatures(e.point, {
      layers: ["layer-desa-fill"],
    });

    if (!features || features.length === 0) {
      emit("clear-selection");
      return;
    }

    const payload = extractDesaPayload(features[0]);
    if (payload) {
      emit("select-desa", payload);
    }
  });
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === "Escape") {
    showMartinPopover.value = false;
    showLegendPopover.value = false;
  }
}

async function initMap() {
  if (!import.meta.client || !mapContainerRef.value || mapInstance) return;

  try {
    const [mod, workerMod] = await Promise.all([
      import("maplibre-gl"),
      import("maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url"),
    ]);
    await import("maplibre-gl/dist/maplibre-gl.css");
    maplibregl = mod;

    if (workerMod?.default && typeof maplibregl.setWorkerUrl === "function") {
      maplibregl.setWorkerUrl(workerMod.default);
    }

    await basemapStore.ensureLoaded();
    if (!mapContainerRef.value) return;

    mapInstance = new maplibregl.Map({
      container: mapContainerRef.value,
      style: buildMapStyle(activeBasemap.value),
      center: [111.88, -7.2],
      zoom: 10.2,
      minZoom: 8,
      maxZoom: 18,
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
      if (isMapReady.value) return;
      if (canvasReadyFallbackTimer) {
        clearTimeout(canvasReadyFallbackTimer);
        canvasReadyFallbackTimer = null;
      }
      isMapReady.value = true;
      isMvtLoading.value = false;
      if (martinStatusState.value === "loading") {
        martinStatusState.value = "ready";
      }
      const el = mapContainerRef.value;
      lastObservedW = Math.round(el?.clientWidth ?? 0);
      lastObservedH = Math.round(el?.clientHeight ?? 0);
      mapInstance?.resize();

      if (props.activeKecamatanBbox && props.selectedKecamatanId !== null) {
        safeFitBounds(props.activeKecamatanBbox, 64, 15, 0);
      } else {
        fitToSummaryOrBojonegoro(0);
      }

      syncDynamicPaints();
      syncFeatureStates(true);
      syncSelectedGeoJsonOverlay();
      syncFormPreviewGeoJsonOverlay();
      syncPulseMarker();
      if (typeof mapInstance?.redraw === "function") {
        mapInstance.redraw();
      }
      emit("log", "INFO", "Kanvas MapLibre GL JS + Martin MVT wilayah desa siap.");
    };

    mapInstance.on("style.load", () => {
      markCanvasReady();
    });

    mapInstance.on("load", () => {
      markCanvasReady();
    });

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

    mapInstance.on("error", (e: any) => {
      const sid = String(e?.sourceId || "");
      if (sid.startsWith("martin-")) {
        tileErrorCount++;
        if (tileErrorCount >= 3) {
          martinStatusState.value = "warning";
          martinWarningDetail.value =
            "Sebagian vector tile Martin belum merespons. Pastikan layanan tile server Martin (port 9090) aktif.";
          emit("log", "WARN", martinWarningDetail.value);
        }
      }
    });

    mapInstance.on("sourcedataloading", (e: any) => {
      if (e?.sourceId === "martin-desa") {
        isMvtLoading.value = true;
      }
    });

    mapInstance.on("sourcedata", (e: any) => {
      if (e?.sourceId === "martin-desa" && e?.isSourceLoaded) {
        isMvtLoading.value = false;
        if (martinStatusState.value !== "ready") {
          martinStatusState.value = "ready";
          martinWarningDetail.value = null;
        }
      }
    });

    bindInteractiveEvents();

    if (typeof ResizeObserver !== "undefined" && mapContainerRef.value) {
      resizeObserver = new ResizeObserver(() => {
        resizeMap();
      });
      resizeObserver.observe(mapContainerRef.value);
    }
  } catch (err: any) {
    if (canvasReadyFallbackTimer) {
      clearTimeout(canvasReadyFallbackTimer);
      canvasReadyFallbackTimer = null;
    }
    isMapReady.value = true;
    isMvtLoading.value = false;
    martinStatusState.value = "warning";
    martinWarningDetail.value = err?.message || "Gagal memuat MapLibre GL JS.";
    emit("log", "ERROR", martinWarningDetail.value || "Gagal memuat MapLibre GL JS.");
  }
}

watch(
  () => props.selectedKecamatanId,
  () => {
    syncDynamicPaints();
  }
);

watch(
  () => props.selectedDesaId,
  () => {
    syncSelectedFeatureState();
  },
  { flush: "sync" }
);

watch(
  () => props.selectedGeometry,
  () => {
    syncSelectedGeoJsonOverlay();
  },
  { deep: true }
);

watch(
  () => props.formPreviewGeometry,
  () => {
    syncFormPreviewGeoJsonOverlay();
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
  () => props.summaryBbox,
  (newBbox) => {
    if (isMapReady.value && newBbox && props.selectedKecamatanId === null) {
      fitToSummaryOrBojonegoro();
    }
  }
);

watch(
  () => props.activeKecamatanBbox,
  (newBbox) => {
    if (isMapReady.value && newBbox && props.selectedKecamatanId !== null) {
      safeFitBounds(newBbox, 64, 15, 550);
    }
  }
);

watch(
  () => props.refreshNonce,
  () => {
    if (mapInstance && isMapReady.value) {
      applyBasemapSwitch(activeBasemap.value);
    }
  }
);

watch(
  () => props.basemap,
  (nextCode) => {
    if (nextCode && mapInstance && isMapReady.value) {
      applyBasemapSwitch(nextCode);
    }
  }
);

watch(
  () => colorMode.value,
  () => {
    if (mapInstance && isMapReady.value) {
      syncDynamicPaints();
    }
  }
);

defineExpose({
  fitToSummaryOrBojonegoro,
  safeFitBounds,
  flyToCoords,
  fitToGeometry,
  resizeMap,
  handleBasemapChange,
});

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
</script>

<template>
  <div
    class="relative w-full h-full overflow-hidden select-none transition-colors duration-200"
    :class="activeBasemap === 'none' ? 'bg-white' : 'bg-slate-100 dark:bg-[#070b14]'"
  >
    <!-- MapLibre WebGL Canvas -->
    <div ref="mapContainerRef" class="absolute inset-0 w-full h-full" />

    <!-- Loading Overlay -->
    <div
      v-if="!isMapReady"
      class="absolute inset-0 z-30 flex items-center justify-center bg-gray-100/90 dark:bg-[#070b14]/90 pointer-events-none"
    >
      <div class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-lg text-xs font-medium text-gray-700 dark:text-gray-200">
        <UIcon name="i-lucide-loader-2" class="size-4 animate-spin text-blue-600 dark:text-blue-400" />
        <span>Memuat kanvas MapLibre GL JS...</span>
      </div>
    </div>

    <!-- Top-Left: Martin Vector Tile Telemetry Pill & Compact Legend Toggle -->
    <div class="absolute top-3 left-3 z-20 flex flex-wrap items-start gap-2 pointer-events-none max-w-[calc(100%-7.5rem)]">
      <!-- Telemetry Pill -->
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
              : 'border-gray-200/90 bg-white/95 text-gray-800 dark:border-gray-700/90 dark:bg-[#0e1424]/95 dark:text-gray-200',
          ]"
          @click="showMartinPopover = !showMartinPopover; showLegendPopover = false"
        >
          <UIcon
            v-if="isMvtLoading || martinStatusState === 'loading'"
            name="i-lucide-loader-2"
            class="size-3.5 animate-spin text-blue-600 dark:text-blue-400 shrink-0"
            aria-hidden="true"
          />
          <span
            v-else
            :class="[
              'size-2 rounded-full shrink-0',
              martinStatusState === 'warning' ? 'bg-amber-600' : 'bg-emerald-600',
            ]"
            aria-hidden="true"
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
              "Peta merender vector tile MVT bataswilayah_desa secara langsung melalui WebGL MapLibre GL JS."
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

      <!-- Legend Toggle Pill -->
      <div class="relative pointer-events-auto">
        <button
          type="button"
          :aria-expanded="showLegendPopover"
          aria-label="Tampilkan atau sembunyikan legenda peta"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/90 bg-white/95 px-2.5 text-xs font-medium text-gray-800 shadow-sm backdrop-blur-md hover:bg-gray-50 dark:border-gray-700/90 dark:bg-[#0e1424]/95 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer min-h-[44px] lg:min-h-[32px] lg:py-1 focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="showLegendPopover = !showLegendPopover; showMartinPopover = false"
        >
          <UIcon name="i-lucide-layers" class="size-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" aria-hidden="true" />
          <span class="hidden sm:inline">Legenda</span>
          <UIcon
            :name="showLegendPopover ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
            class="size-3.5 text-gray-600 dark:text-gray-400 shrink-0"
            aria-hidden="true"
          />
        </button>

        <div
          v-if="showLegendPopover"
          class="absolute left-0 top-full mt-1.5 w-60 rounded-lg border border-gray-200 bg-white p-3 text-xs shadow-lg dark:border-gray-700 dark:bg-[#0e1424] space-y-2"
        >
          <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-gray-100 dark:border-gray-800">
            <span class="font-semibold text-gray-900 dark:text-white">Legenda Peta</span>
            <span class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">MVT</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="size-3.5 rounded-xs border border-emerald-600 bg-emerald-500/25 shrink-0" aria-hidden="true" />
            <span class="text-gray-800 dark:text-gray-200">Batas Wilayah Desa</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="size-3.5 rounded-xs border-2 border-sky-600 bg-sky-500/35 shrink-0" aria-hidden="true" />
            <span class="text-gray-800 dark:text-gray-200">Desa Terpilih</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="size-3.5 rounded-xs border-2 border-dashed border-amber-600 bg-amber-500/25 shrink-0" aria-hidden="true" />
            <span class="text-gray-800 dark:text-gray-200">Pratinjau Form</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3.5 h-0 border-t-2 border-dashed border-slate-500 dark:border-slate-400 shrink-0" aria-hidden="true" />
            <span class="text-gray-700 dark:text-gray-300">Batas Kecamatan</span>
          </div>
          <div v-if="selectedKecamatanId !== null" class="flex items-center gap-2">
            <span class="size-3.5 rounded-xs border border-gray-400/60 bg-gray-400/20 shrink-0" aria-hidden="true" />
            <span class="text-gray-600 dark:text-gray-400">Di Luar Kecamatan Aktif</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Top-Right: Basemap Switcher + Vertical GIS Navigation Controls (Jalan Poros Reference) -->
    <div class="absolute top-3 right-3 z-20 flex flex-col items-end gap-2">
      <BasemapSwitcher
        :model-value="activeBasemap"
        engine="maplibre"
        variant="medium"
        :allow-none="true"
        placement="top-right"
        @update:model-value="handleBasemapChange"
      />

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
          <UIcon name="i-lucide-plus" class="size-4" aria-hidden="true" />
        </button>
        <button
          type="button"
          aria-label="Perkecil Peta"
          title="Perkecil (-)"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="zoomBy(-1)"
        >
          <UIcon name="i-lucide-minus" class="size-4" aria-hidden="true" />
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
          aria-label="Fokus seluruh wilayah Bojonegoro"
          title="Fokus Seluruh Wilayah"
          class="size-11 lg:size-8 flex items-center justify-center text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="
            fitToSummaryOrBojonegoro();
            emit('fit-bojonegoro');
          "
        >
          <UIcon name="i-lucide-maximize-2" class="size-4" aria-hidden="true" />
        </button>
        <button
          v-if="activeKecamatanName"
          type="button"
          :aria-label="'Fokus ke Kecamatan ' + activeKecamatanName"
          :title="'Fokus ke Kec. ' + activeKecamatanName"
          class="size-11 lg:size-8 flex items-center justify-center text-blue-700 bg-blue-50/80 hover:bg-blue-100 dark:text-blue-300 dark:bg-blue-950/40 dark:hover:bg-blue-900/60 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="
            if (activeKecamatanBbox) safeFitBounds(activeKecamatanBbox, 64, 15, 550);
            emit('focus-kecamatan');
          "
        >
          <UIcon name="i-lucide-focus" class="size-4" aria-hidden="true" />
        </button>
        <button
          v-if="currentBearing !== 0 || currentPitch !== 0"
          type="button"
          aria-label="Reset arah utara dan kemiringan"
          title="Reset Utara & 2D"
          class="size-11 lg:size-8 flex items-center justify-center text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="resetNorthAndTilt"
        >
          <UIcon name="i-lucide-compass" class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>

    <!-- Interactive Map Hover Tooltip (Clamped inside Canvas) -->
    <div
      v-if="hoverTooltip"
      :style="getTooltipStyle()"
      class="pointer-events-none absolute z-20 w-56 rounded-xl border border-gray-200 bg-white/95 p-2.5 text-xs shadow-xl backdrop-blur-md dark:border-gray-700 dark:bg-[#0e1424]/95 space-y-1"
    >
      <div class="flex items-center justify-between gap-2">
        <p class="font-bold text-gray-900 dark:text-white truncate">
          {{ hoverTooltip.nama_desa }}
        </p>
        <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
          Desa
        </span>
      </div>
      <div class="flex items-center justify-between text-[11px] text-gray-600 dark:text-gray-300 gap-2">
        <span>Kecamatan</span>
        <span class="font-medium text-gray-900 dark:text-gray-100 truncate">
          {{ hoverTooltip.kecamatan }}
        </span>
      </div>
      <div
        v-if="hoverTooltip.luas_hektar !== null"
        class="flex items-center justify-between text-[11px] text-gray-600 dark:text-gray-300 gap-2"
      >
        <span>Luas</span>
        <span class="font-semibold text-blue-600 dark:text-blue-400 tabular-nums">
          {{ hoverTooltip.luas_hektar.toLocaleString("id-ID") }} Ha
        </span>
      </div>
      <div
        v-if="hoverTooltip.pimpinan && hoverTooltip.pimpinan !== '-'"
        class="text-[11px] text-gray-600 dark:text-gray-400 pt-1 border-t border-gray-100 dark:border-gray-800 truncate"
      >
        Pimpinan: {{ hoverTooltip.pimpinan }}
      </div>
    </div>

    <!-- Bottom-Left Live WGS84 Coordinate & Zoom Telemetry HUD -->
    <div
      class="pointer-events-none absolute left-3 bottom-[4.75rem] lg:bottom-2.5 z-10 hidden sm:flex items-center gap-2 rounded-md border border-gray-200/90 bg-white/95 px-2.5 py-1 text-[11px] font-medium text-gray-700 shadow-xs backdrop-blur-md tabular-nums dark:border-gray-700/90 dark:bg-[#0e1424]/95 dark:text-gray-200"
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
  .transition-colors {
    transition-duration: 0.01ms !important;
  }
}
</style>

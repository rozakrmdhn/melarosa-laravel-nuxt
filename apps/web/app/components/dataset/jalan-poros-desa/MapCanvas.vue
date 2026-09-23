<script setup lang="ts">
import "ol/ol.css";
import type { BasemapType, LayerSymbology, RuasProperties, SelectedFeature } from "~/types/dataset-editor";
import { DEFAULT_SYMBOLOGY } from "~/types/dataset-editor";
import { $http } from "~/utils/helpers";

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
    symbology: () => ({ ...DEFAULT_SYMBOLOGY }),
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

const colorMode = useColorMode();
const mapContainer = ref<HTMLElement | null>(null);
const scaleLineTarget = ref<HTMLElement | null>(null);
const tooltipEl = ref<HTMLElement | null>(null);
const pulseEl = ref<HTMLElement | null>(null);
const isPulseVisible = ref(false);
const mapLoaded = ref(false);
const isMvtLoading = ref(false);
let activeTileCount = 0;
let mvtSafetyTimeout: any = null;

function onTileLoadStart() {
  activeTileCount++;
  isMvtLoading.value = true;
  emit("mvtLoading", true);
  clearTimeout(mvtSafetyTimeout);
  mvtSafetyTimeout = setTimeout(() => {
    if (activeTileCount > 0) {
      activeTileCount = 0;
      isMvtLoading.value = false;
      emit("mvtLoading", false);
    }
  }, 8000);
}

function onTileLoadFinish() {
  activeTileCount = Math.max(0, activeTileCount - 1);
  if (activeTileCount === 0) {
    clearTimeout(mvtSafetyTimeout);
    isMvtLoading.value = false;
    emit("mvtLoading", false);
  }
}
const mouseCoords = ref("");
const hoveredFeature = ref<any>(null);
const tooltipData = ref<{
  nama_ruas: string;
  kondisi: string;
  panjang_meter: number;
  desa: string;
  kecamatan: string;
} | null>(null);

// ─── Draw Feature State ───────────────────────────────────────────────────────
const isDrawing = ref(false);
const hasCompletedLine = ref(false);
const drawnPointCount = ref(0);
const drawnLengthMeters = ref(0);
const drawnFeature = ref<any>(null);
const redoStack = ref<any[]>([]);

const canUndo = computed(() => {
  if (hasCompletedLine.value) {
    const coords = drawnFeature.value?.getGeometry()?.getCoordinates();
    return Boolean(coords && coords.length > 0);
  }
  return drawnPointCount.value > 0;
});

const canRedo = computed(() => {
  return redoStack.value.length > 0;
});

const canSave = computed(() => {
  return drawnPointCount.value >= 2;
});

const isDrawInteracting = computed(() => {
  return isDrawing.value && !hasCompletedLine.value;
});

let drawSource: any = null;
let drawLayer: any = null;
let drawInteraction: any = null;
let modifyInteraction: any = null;
let activeGeomListener: any = null;
const isEditingExisting = ref(false);

let snapSource: any = null;
let drawSnapInteraction: any = null;
let networkSnapInteraction: any = null;
const isSnappingEnabled = ref(true);
const snapRoadCount = ref(0);
const isSnapLoading = ref(false);
const editingRuasId = ref<string | number | null>(null);
let snapFetchAbort: AbortController | null = null;
let lastSnapBbox = "";
let snapMoveEndTimer: any = null;

const isSplitMode = ref(false);
const splitRoadData = ref<any>(null);
let splitSource: any = null;
let splitLayer: any = null;
let splitSnapInteraction: any = null;
let splitClickListener: any = null;
const splitSelectedCoord = ref<[number, number] | null>(null);

let mapInstance: any = null;
let tileLayer: any = null;
let vectorTileLayer: any = null;
let vectorTileSource: any = null;
let tooltipOverlay: any = null;
let pulseOverlay: any = null;
let olModules: any = null;

function getBasemapSource(type: BasemapType) {
  if (!olModules) return null;
  const { OSM, XYZ } = olModules;
  if (type === "satellite") {
    return new XYZ({
      url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
      maxZoom: 19,
    });
  }
  if (type === "dark") {
    return new XYZ({
      url: "https://basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png",
      attributions: '&copy; <a href="https://carto.com/">CARTO</a>',
      maxZoom: 19,
    });
  }
  return new OSM();
}

function setBasemap(type: BasemapType) {
  emit("update:basemap", type);
  if (tileLayer && olModules) {
    tileLayer.setSource(getBasemapSource(type));
  }
}

const KONDISI_COLORS: Record<string, string> = {
  BAIK: "#10b981",
  SEDANG: "#f59e0b",
  RUSAK: "#f97316",
  "RUSAK BERAT": "#ef4444",
};

const PERKERASAN_COLORS: Record<string, string> = {
  Aspal: "#0284c7",
  Beton: "#6366f1",
  Kerikil: "#d97706",
  Tanah: "#854d0e",
  Lainnya: "#64748b",
};

function getStrokeColor(feature: any): string {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (sym.colorMode === "kondisi") {
    const k = String(feature.get("kondisi") || "").toUpperCase().trim();
    if (k.includes("RUSAK BERAT")) return KONDISI_COLORS["RUSAK BERAT"];
    if (k.includes("RUSAK")) return KONDISI_COLORS["RUSAK"];
    if (k.includes("SEDANG")) return KONDISI_COLORS["SEDANG"];
    if (k.includes("BAIK")) return KONDISI_COLORS["BAIK"];
    return sym.lineColor || "#10b981";
  }
  if (sym.colorMode === "perkerasan") {
    const p = String(feature.get("perkerasan") || "").trim();
    return PERKERASAN_COLORS[p] || PERKERASAN_COLORS["Lainnya"] || "#64748b";
  }
  return sym.lineColor || "#059669";
}

function getLineDash(): number[] | undefined {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (sym.lineDash === "dashed") return [8, 6];
  if (sym.lineDash === "dotted") return [2, 4];
  return undefined;
}

function getLabelText(feature: any): string {
  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  if (!sym.labelEnabled) return "";

  const currentZoom = mapInstance ? (mapInstance.getView()?.getZoom() || 0) : 0;
  if (currentZoom < (sym.labelMinZoom ?? 13)) return "";

  const field = sym.labelField || "nama_ruas";
  if (field === "panjang") {
    const pMeter = feature.get("panjang_meter");
    const pKm = feature.get("panjang");
    if (pMeter != null && pMeter !== "") {
      return `${Number(pMeter).toLocaleString("id-ID", { maximumFractionDigits: 1 })} m`;
    }
    if (pKm != null && pKm !== "") {
      return `${Number(pKm).toLocaleString("id-ID", { maximumFractionDigits: 1 })} m`;
    }
    return "";
  }
  const val = feature.get(field);
  return val ? String(val) : "";
}

// ─── Style & Text Cache for High-Performance MVT Rendering ───────────────────
const textStyleCache = new Map<string, any>();
const featureStyleCache = new Map<string, any>();

function clearStyleCache() {
  textStyleCache.clear();
  featureStyleCache.clear();
}

function getFeatureStyle(feature: any) {
  if (!olModules) return null;
  const { Style, Stroke, Fill, Text } = olModules;

  const id = String(feature.get("id") || feature.getId() || "");
  const isSelected = props.selectedFeature?.id === id;
  const hoverId = hoveredFeature.value
    ? String(hoveredFeature.value.get("id") || hoveredFeature.value.getId() || "")
    : "";
  const isHovered = hoverId === id;

  const sym = props.symbology || DEFAULT_SYMBOLOGY;
  const isDark = colorMode.value === "dark";

  // Base stroke properties
  const baseColor = getStrokeColor(feature);
  const strokeWidth = sym.lineWidth || 2.5;
  const lineDash = getLineDash();

  // Label text if enabled & zoom threshold met
  const labelText = getLabelText(feature);
  let textStyle: any = undefined;

  if (labelText) {
    const fontSize = sym.labelFontSize || 11;
    const textColor = sym.labelColor || (isDark ? "#f8fafc" : "#0f172a");
    const haloColor = sym.labelHaloColor || (isDark ? "#090d16" : "#ffffff");
    const haloWidth = sym.labelHaloWidth ?? 3;
    const textKey = `${labelText}_${fontSize}_${textColor}_${haloColor}_${haloWidth}`;

    textStyle = textStyleCache.get(textKey);
    if (!textStyle) {
      textStyle = new Text({
        text: labelText,
        font: `bold ${fontSize}px system-ui, -apple-system, sans-serif`,
        placement: "line",
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
        color: "#0284c7",
        width: Math.max(strokeWidth + 2.5, 4.5),
        lineDash: undefined,
      }),
      text: textStyle,
      zIndex: 100,
    });
  }

  if (isHovered) {
    return new Style({
      stroke: new Stroke({
        color: isDark ? "#ffffff" : "#0f172a",
        width: Math.max(strokeWidth + 1.5, 3.5),
        lineDash: undefined,
      }),
      text: textStyle,
      zIndex: 50,
    });
  }

  const styleKey = `${baseColor}_${strokeWidth}_${sym.lineDash || "solid"}_${labelText || ""}`;
  let cached = featureStyleCache.get(styleKey);
  if (!cached) {
    cached = new Style({
      stroke: new Stroke({
        color: baseColor,
        width: strokeWidth,
        lineDash,
      }),
      text: textStyle,
      zIndex: 10,
    });
    featureStyleCache.set(styleKey, cached);
  }

  return cached;
}

function fitBounds() {
  if (!mapInstance || !olModules) return;
  const size = mapInstance.getSize();
  if (!size || size[0] <= 0 || size[1] <= 0) return;

  const { transformExtent } = olModules;
  if (props.bbox) {
    const [minX, minY, maxX, maxY] = props.bbox;
    const extent = transformExtent([minX, minY, maxX, maxY], "EPSG:4326", "EPSG:3857");
    mapInstance.getView().fit(extent, {
      duration: 700,
      padding: [50, 50, 50, 50],
      maxZoom: 15,
    });
  } else {
    mapInstance.getView().animate({
      center: olModules.fromLonLat([111.88, -7.2]),
      zoom: 11,
      duration: 600,
    });
  }
}

function zoomIn() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || 10) + 1, duration: 200 });
}

function zoomOut() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || 10) - 1, duration: 200 });
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

function zoomToGeometry(geometry: any) {
  if (!mapInstance || !olModules || !geometry) return false;
  try {
    const geojsonFormat = new olModules.GeoJSON();
    const geom = geojsonFormat.readGeometry(geometry, {
      dataProjection: "EPSG:4326",
      featureProjection: "EPSG:3857",
    });
    const extent = geom.getExtent();
    if (extent && extent.every((n: number) => !isNaN(n) && isFinite(n))) {
      fitGeometryToCenter(extent, 600);
      return true;
    }
  } catch {
    // fallback
  }
  return false;
}

function zoomToCentroid(coords: [number, number] | string | any) {
  if (!mapInstance || !olModules) return;
  const size = mapInstance.getSize();
  if (!size || size[0] <= 0 || size[1] <= 0) return;

  let lonLat: [number, number] | null = null;

  if (typeof coords === "string") {
    try {
      const parsed = JSON.parse(coords);
      if (Array.isArray(parsed) && parsed.length >= 2) {
        lonLat = [Number(parsed[0]), Number(parsed[1])] as [number, number];
      } else if (parsed?.coordinates && Array.isArray(parsed.coordinates)) {
        lonLat = [Number(parsed.coordinates[0]), Number(parsed.coordinates[1])] as [number, number];
      }
    } catch {
      const parts = coords.split(",").map((s) => parseFloat(s.trim()));
      if (parts.length >= 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
        lonLat = [parts[0], parts[1]];
      }
    }
  } else if (Array.isArray(coords) && coords.length >= 2) {
    lonLat = [Number(coords[0]), Number(coords[1])];
  } else if (coords && typeof coords === "object") {
    if (Array.isArray(coords.coordinates) && coords.coordinates.length >= 2) {
      lonLat = [Number(coords.coordinates[0]), Number(coords.coordinates[1])];
    } else if (coords.lon != null && coords.lat != null) {
      lonLat = [Number(coords.lon), Number(coords.lat)];
    } else if (coords.x != null && coords.y != null) {
      lonLat = [Number(coords.x), Number(coords.y)];
    }
  }

  if (lonLat && !isNaN(lonLat[0]) && !isNaN(lonLat[1])) {
    mapInstance.getView().animate({
      center: olModules.fromLonLat(lonLat),
      zoom: 16,
      duration: 700,
    });
  }
}

const tileVersion = ref(Date.now());

function getMvtUrl() {
  const params = new URLSearchParams();
  if (props.kecamatanFilter) params.set("kecamatan", props.kecamatanFilter);
  if (props.desaFilter) params.set("desa", props.desaFilter);
  if (props.kondisiFilter) params.set("kondisi", props.kondisiFilter);
  if (props.perkerasanFilter) params.set("perkerasan", props.perkerasanFilter);
  params.set("_v", String(tileVersion.value));
  const qs = params.toString();
  return `${props.apiBase}/api/v1/dataset/mvt/jalan-poros-desa/{z}/{x}/{y}.pbf?${qs}`;
}

function refreshVectorTiles() {
  tileVersion.value = Date.now();
  if (vectorTileSource) {
    const newUrl = getMvtUrl();
    vectorTileSource.setUrl(newUrl);
    vectorTileSource.clear();
    vectorTileSource.refresh();
  }
  if (vectorTileLayer) {
    vectorTileLayer.changed();
  }
}

watch(
  [
    () => props.kecamatanFilter,
    () => props.desaFilter,
    () => props.kondisiFilter,
    () => props.perkerasanFilter,
  ],
  () => {
    refreshVectorTiles();
  }
);

function getKondisiBadgeColor(kondisi: string | null | undefined): "success" | "warning" | "error" | "neutral" {
  if (!kondisi) return "neutral";
  const k = kondisi.toLowerCase();
  if (k.includes("baik")) return "success";
  if (k.includes("sedang")) return "warning";
  if (k.includes("rusak berat")) return "error";
  if (k.includes("rusak")) return "warning";
  return "neutral";
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
      { default: VectorTileLayer },
      { default: VectorTileSource },
      { default: OSM },
      { default: XYZ },
      { default: MVT },
      { default: GeoJSON },
      stylePkg,
      projPkg,
      { default: Overlay },
      { default: ScaleLine },
      { default: VectorLayer },
      { default: VectorSource },
      { default: Draw },
      { default: Modify },
      { default: Snap },
      { default: Feature },
      { default: Point },
      spherePkg,
    ] = await Promise.all([
      import("ol/Map.js"),
      import("ol/View.js"),
      import("ol/layer/Tile.js"),
      import("ol/layer/VectorTile.js"),
      import("ol/source/VectorTile.js"),
      import("ol/source/OSM.js"),
      import("ol/source/XYZ.js"),
      import("ol/format/MVT.js"),
      import("ol/format/GeoJSON.js"),
      import("ol/style.js"),
      import("ol/proj.js"),
      import("ol/Overlay.js"),
      import("ol/control/ScaleLine.js"),
      import("ol/layer/Vector.js"),
      import("ol/source/Vector.js"),
      import("ol/interaction/Draw.js"),
      import("ol/interaction/Modify.js"),
      import("ol/interaction/Snap.js"),
      import("ol/Feature.js"),
      import("ol/geom/Point.js"),
      import("ol/sphere.js"),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      VectorTileLayer,
      VectorTileSource,
      OSM,
      XYZ,
      MVT,
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
      VectorLayer,
      VectorSource,
      Draw,
      Modify,
      Snap,
      Feature,
      Point,
      sphere: spherePkg,
    };

    tileLayer = new TileLayer({
      source: getBasemapSource(props.basemap),
      zIndex: 0,
    });

    const mvtUrl = getMvtUrl();
    vectorTileSource = new VectorTileSource({
      format: new MVT({ idProperty: "id" }),
      url: mvtUrl,
      maxZoom: 18,
    });

    vectorTileSource.on("tileloadstart", onTileLoadStart);
    vectorTileSource.on("tileloadend", onTileLoadFinish);
    vectorTileSource.on("tileloaderror", onTileLoadFinish);

    vectorTileLayer = new VectorTileLayer({
      source: vectorTileSource,
      style: getFeatureStyle,
      renderMode: "hybrid",
      declutter: true,
      zIndex: 10,
      visible: props.layerVisible,
      opacity: props.layerOpacity,
    });

    // ─── Drawing Layer Setup (LineString Feature Drawing) ─────────────────
    drawSource = new VectorSource({ wrapX: false });
    snapSource = new VectorSource({ wrapX: false });
    drawLayer = new VectorLayer({
      source: drawSource,
      zIndex: 50,
      style: [
        new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: "rgba(16, 185, 129, 0.35)",
            width: 8,
          }),
        }),
        new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: "#10b981",
            width: 3.5,
          }),
          image: new stylePkg.Circle({
            radius: 5,
            fill: new stylePkg.Fill({ color: "#10b981" }),
            stroke: new stylePkg.Stroke({ color: "#ffffff", width: 2 }),
          }),
        }),
      ],
    });

    if (tooltipEl.value) {
      tooltipOverlay = new Overlay({
        element: tooltipEl.value,
        offset: [0, 1],
        positioning: "bottom-center",
        stopEvent: false,
      });
    }

    if (pulseEl.value) {
      pulseOverlay = new Overlay({
        element: pulseEl.value,
        positioning: "center-center",
        stopEvent: false,
      });
    }

    const scaleLineControl = new ScaleLine({
      target: scaleLineTarget.value || undefined,
      units: "metric",
      minWidth: 80,
      bar: false,
    });

    mapInstance = new Map({
      target: mapContainer.value,
      layers: [tileLayer, vectorTileLayer, drawLayer],
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

    splitSource = new VectorSource({ wrapX: false });
    splitLayer = new VectorLayer({
      source: splitSource,
      zIndex: 55,
      style: (feat: any) => {
        if (feat.get("isMarker")) {
          return new stylePkg.Style({
            image: new stylePkg.Circle({
              radius: 7,
              fill: new stylePkg.Fill({ color: "#f59e0b" }),
              stroke: new stylePkg.Stroke({ color: "#ffffff", width: 2.5 }),
            }),
          });
        }
        return [
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: "rgba(245, 158, 11, 0.4)",
              width: 9,
            }),
          }),
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: "#f59e0b",
              width: 3.5,
              lineDash: [8, 6],
            }),
          }),
        ];
      },
    });
    mapInstance.addLayer(splitLayer);

    // Pointer move: coords update every event; hit-test throttled to one per animation frame
    let pointerRafId: number | null = null;
    mapInstance.on("pointermove", (e: any) => {
      if (e.dragging) {
        if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        return;
      }

      if (isDrawing.value) {
        if (mapContainer.value) mapContainer.value.style.cursor = "crosshair";
        hoveredFeature.value = null;
        tooltipData.value = null;
        if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        return;
      }

      // Cheap: update coords on every event for smooth coordinate display
      const lonLat = olModules.toLonLat(e.coordinate);
      const coordsText = `${lonLat[0].toFixed(5)}, ${lonLat[1].toFixed(5)}`;
      mouseCoords.value = coordsText;
      emit("update:mouseCoords", coordsText);

      // Expensive: gate hit-test to one per animation frame
      const coord = e.coordinate;
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
            hitTolerance: 5,
          }
        );

        const prevHoverId = hoveredFeature.value
          ? String(hoveredFeature.value.get("id") || hoveredFeature.value.getId() || "")
          : "";
        const currentHitId = hit
          ? String(hit.get("id") || hit.getId() || "")
          : "";

        if (hit && props.layerVisible) {
          if (mapContainer.value) mapContainer.value.style.cursor = "pointer";
          hoveredFeature.value = hit;
          const p = hit.getProperties?.() ?? {};
          tooltipData.value = {
            nama_ruas: p.nama_ruas || hit.get("nama_ruas") || "Ruas Jalan",
            kondisi: p.kondisi || hit.get("kondisi") || "-",
            panjang_meter: parseFloat(p.panjang_meter || hit.get("panjang_meter") || "0"),
            desa: p.desa || hit.get("desa") || "-",
            kecamatan: p.kecamatan || hit.get("kecamatan") || "-",
          };
          if (tooltipOverlay) tooltipOverlay.setPosition(coord);
        } else {
          if (mapContainer.value) mapContainer.value.style.cursor = "";
          hoveredFeature.value = null;
          tooltipData.value = null;
          if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        }

        if (currentHitId !== prevHoverId) {
          vectorTileLayer.changed();
        }
      });
    });

    // Click select
    mapInstance.on("click", (e: any) => {
      if (isDrawing.value) return;

      if (e.coordinate && pulseOverlay) {
        pulseOverlay.setPosition(e.coordinate);
        isPulseVisible.value = true;
      }

      if (olModules?.toLonLat && e.coordinate) {
        const lonLat = olModules.toLonLat(e.coordinate);
        if (lonLat && lonLat.length >= 2) {
          emit("mapClick", [lonLat[0], lonLat[1]]);
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
          hitTolerance: 6,
        }
      );

      if (clicked) {
        const propsObj = clicked.getProperties?.() ?? {};
        const featId = String(propsObj.id || clicked.getId() || clicked.get?.("id") || "");

        let centroid = null;
        if (propsObj.centroid) {
          try {
            centroid = typeof propsObj.centroid === "string" ? JSON.parse(propsObj.centroid) : propsObj.centroid;
          } catch {
            centroid = null;
          }
        }

        const pMeter = propsObj.panjang_meter != null && propsObj.panjang_meter !== "" ? parseFloat(propsObj.panjang_meter) : null;
        const pManual = propsObj.panjang != null && propsObj.panjang !== "" ? parseFloat(propsObj.panjang) : null;
        const finalPanjang = pMeter ?? pManual ?? 0;

        const selected: SelectedFeature = {
          id: featId,
          properties: {
            id: featId,
            kode_ruas: propsObj.kode_ruas != null && propsObj.kode_ruas !== "" ? Number(propsObj.kode_ruas) : null,
            nama_ruas: propsObj.nama_ruas || "-",
            desa: propsObj.desa || null,
            kecamatan: propsObj.kecamatan || null,
            panjang: pManual ?? pMeter ?? 0,
            panjang_meter: pMeter ?? pManual ?? 0,
            lebar: propsObj.lebar != null && propsObj.lebar !== "" ? parseFloat(propsObj.lebar) : null,
            perkerasan: propsObj.perkerasan || null,
            kondisi: propsObj.kondisi || null,
            status_awal: propsObj.status_awal || null,
            status_eksisting: propsObj.status_eksisting || null,
            sumber_data: propsObj.sumber_data || null,
            id_desa: propsObj.id_desa != null && propsObj.id_desa !== "" ? Number(propsObj.id_desa) : null,
            id_kecamatan: propsObj.id_kecamatan != null && propsObj.id_kecamatan !== "" ? Number(propsObj.id_kecamatan) : null,
            centroid,
          },
        };
        emit("select", selected);
        emit("log", "INFO", `Ruas dipilih: ${selected.properties.nama_ruas}`);
      } else {
        emit("select", null);
      }
      vectorTileLayer.changed();
    });

    mapLoaded.value = true;
    emit("loaded");
    emit("log", "INFO", "Peta dimuat via PostGIS MVT.");

    if (props.bbox) {
      fitBounds();
    }
  } catch (err: any) {
    emit("log", "ERROR", `Gagal inisialisasi peta: ${err?.message}`);
  }
}

watch(
  () => props.basemap,
  (newBm) => {
    if (tileLayer && olModules) {
      tileLayer.setSource(getBasemapSource(newBm));
    }
  }
);

watch(
  () => props.layerVisible,
  (visible) => {
    if (vectorTileLayer) {
      vectorTileLayer.setVisible(visible);
    }
  }
);

watch(
  () => props.layerOpacity,
  (opacity) => {
    if (vectorTileLayer) {
      vectorTileLayer.setOpacity(opacity);
    }
  }
);

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

watch(
  () => props.selectedFeature,
  () => {
    if (vectorTileLayer) {
      vectorTileLayer.changed();
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

watch(colorMode, () => {
  clearStyleCache();
  if (vectorTileLayer) {
    vectorTileLayer.changed();
  }
});

let resizeObserver: ResizeObserver | null = null;
let resizeRaf: number | null = null;
let pointerRafId: number | null = null;

onMounted(() => {
  initMap();

  if (mapContainer.value && typeof ResizeObserver !== "undefined") {
    resizeObserver = new ResizeObserver(() => {
      if (resizeRaf) cancelAnimationFrame(resizeRaf);
      resizeRaf = requestAnimationFrame(() => {
        mapInstance?.updateSize();
      });
    });
    resizeObserver.observe(mapContainer.value);
  }
});

// ─── Snapping Operations (Self-Snapping & Existing Network Snapping) ────────

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

  // 1. Self-snapping to the line being drawn/edited
  if (drawSource) {
    drawSnapInteraction = new Snap({
      source: drawSource,
      edge: true,
      vertex: true,
      pixelTolerance: 12,
    });
    mapInstance.addInteraction(drawSnapInteraction);
  }

  // 2. Network snapping to other existing road features in area
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

    const [minX, minY, maxX, maxY] = olModules.transformExtent(extent, "EPSG:3857", "EPSG:4326");
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
      query: {
        format: "geojson",
        bbox: bboxParam,
      },
      signal: snapFetchAbort.signal,
    });

    if (res?.features && Array.isArray(res.features)) {
      if (!snapSource) return;
      snapSource.clear();

      let featuresToSnap = res.features;
      if (editingRuasId.value) {
        featuresToSnap = featuresToSnap.filter(
          (f: any) =>
            String(f.id) !== String(editingRuasId.value) &&
            String(f.properties?.id) !== String(editingRuasId.value)
        );
      }

      const format = new olModules.GeoJSON();
      const olFeatures = format.readFeatures(
        {
          type: "FeatureCollection",
          features: featuresToSnap,
        },
        {
          dataProjection: "EPSG:4326",
          featureProjection: "EPSG:3857",
        }
      );

      snapSource.addFeatures(olFeatures);
      snapRoadCount.value = olFeatures.length;
      updateSnapInteractions();
      emit("log", "INFO", `Snapping aktif ke ${olFeatures.length} ruas jalan sekitar.`);
    }
  } catch (err: any) {
    if (
      err?.name === "AbortError" ||
      err?.cause?.name === "AbortError" ||
      (typeof err?.message === "string" && err.message.toLowerCase().includes("abort"))
    ) {
      return;
    }
    console.error("Gagal memuat snap features:", err);
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
  emit("log", "INFO", isSnappingEnabled.value ? "Snapping persimpangan dan ruas jalan diaktifkan." : "Snapping dinonaktifkan.");
}

// ─── Split Road Geometry Feature ─────────────────────────────────────────────

function startSplitMode(roadData: any) {
  if (!mapInstance || !olModules) return;
  stopDrawing(false);
  cancelSplitMode();

  isSplitMode.value = true;
  splitRoadData.value = roadData;

  const rawGeom = roadData.geometry || roadData.properties?.geometry || roadData.geojson || roadData.properties?.geojson;
  if (!rawGeom) {
    emit("log", "WARN", "Geometri ruas jalan tidak ditemukan untuk split.");
    return;
  }

  try {
    const parsedGeom = typeof rawGeom === "string" ? JSON.parse(rawGeom) : rawGeom;
    const format = new olModules.GeoJSON();
    const olGeom = format.readGeometry(parsedGeom, {
      dataProjection: "EPSG:4326",
      featureProjection: "EPSG:3857",
    });

    const roadFeat = new olModules.Feature({ geometry: olGeom });
    splitSource.addFeature(roadFeat);

    const extent = olGeom.getExtent();
    mapInstance.getView().fit(extent, {
      padding: [100, 100, 100, 100],
      maxZoom: 17,
      duration: 600,
    });

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
      // Snap click point precisely to the closest position on the road geometry
      const snappedCoord = olGeom?.getClosestPoint ? olGeom.getClosestPoint(rawCoord) : rawCoord;
      const [lon, lat] = olModules.toLonLat(snappedCoord);
      splitSelectedCoord.value = [lon, lat];

      const existingMarkers = splitSource.getFeatures().filter((f: any) => f.get("isMarker"));
      existingMarkers.forEach((m: any) => splitSource.removeFeature(m));

      const marker = new olModules.Feature({
        geometry: new olModules.Point(snappedCoord),
      });
      marker.set("isMarker", true);
      splitSource.addFeature(marker);

      emit("splitPointSelected", {
        road: roadData,
        coordinate: [lon, lat],
      });
    };
    mapInstance.on("singleclick", splitClickListener);
    emit("log", "INFO", `Mode split aktif untuk ruas: "${roadData.nama_ruas || roadData.properties?.nama_ruas}". Klik pada badan jalan di lokasi pemotongan.`);
  } catch (err: any) {
    emit("log", "ERROR", `Gagal memuat ruas untuk split: ${err?.message}`);
  }
}

function cancelSplitMode() {
  if (splitClickListener && mapInstance) {
    mapInstance.un("singleclick", splitClickListener);
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
  splitRoadData.value = null;
  splitSelectedCoord.value = null;
}

// ─── Drawing Feature Operations (Draw, Redraw, Undo, Redo, Simpan) ───────────

function startDrawing() {
  if (!mapInstance || !olModules) return;
  stopDrawing(false);
  clearDraw();
  redoStack.value = [];

  isDrawing.value = true;
  hasCompletedLine.value = false;
  drawnPointCount.value = 0;
  drawnLengthMeters.value = 0;
  drawnFeature.value = null;

  const { Draw, sphere, Style, Stroke, Circle, Fill } = olModules;

  drawInteraction = new Draw({
    source: drawSource,
    type: "LineString",
    style: [
      new Style({
        stroke: new Stroke({
          color: "rgba(16, 185, 129, 0.4)",
          width: 7,
        }),
      }),
      new Style({
        stroke: new Stroke({
          color: "#059669",
          width: 3,
          lineDash: [6, 6],
        }),
        image: new Circle({
          radius: 6,
          fill: new Fill({ color: "#10b981" }),
          stroke: new Stroke({ color: "#ffffff", width: 2 }),
        }),
      }),
    ],
  });

  drawInteraction.on("drawstart", (evt: any) => {
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
    geom.on("change", activeGeomListener);
  });

  drawInteraction.on("drawend", (evt: any) => {
    hasCompletedLine.value = true;
    drawnFeature.value = evt.feature;
    const geom = evt.feature.getGeometry();
    if (geom) {
      drawnLengthMeters.value = Math.round(sphere.getLength(geom));
      drawnPointCount.value = geom.getCoordinates()?.length || 0;
      if (activeGeomListener) {
        geom.un("change", activeGeomListener);
        activeGeomListener = null;
      }
      // Posisikan geometri tepat di tengah map canvas setelah selesai menggambar
      fitGeometryToCenter(geom, 600);
    }
    if (drawInteraction && mapInstance) {
      mapInstance.removeInteraction(drawInteraction);
      drawInteraction = null;
    }

    // Pasang modify interaction agar titik dapat disesuaikan kembali sebelum disimpan
    const { Modify, Style, Circle, Fill, Stroke } = olModules;
    modifyInteraction = new Modify({
      source: drawSource,
      style: new Style({
        image: new Circle({
          radius: 6,
          fill: new Fill({ color: "#10b981" }),
          stroke: new Stroke({ color: "#ffffff", width: 2.5 }),
        }),
      }),
    });
    modifyInteraction.on("modifystart", () => {
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
    geom?.on("change", activeGeomListener);
    mapInstance.addInteraction(modifyInteraction);
    updateSnapInteractions();
  });

  mapInstance.addInteraction(drawInteraction);
  updateSnapInteractions();
  mapInstance.on("moveend", onMapMoveEndDuringDraw);
  loadSnapFeatures();
  emit("log", "INFO", "Mode gambar aktif. Klik pada peta untuk menggambar garis ruas jalan.");
}

function handleDrawClick() {
  if (!isDrawing.value) {
    startDrawing();
  } else if (hasCompletedLine.value) {
    emit("log", "INFO", "Garis sudah selesai digambar. Gunakan Redraw untuk mengulang dari awal, atau Simpan untuk membuka form.");
  } else {
    emit("log", "INFO", "Mode gambar sedang aktif. Silakan lanjutkan klik pada peta.");
  }
}

function handleRedraw() {
  startDrawing();
  emit("log", "INFO", "Mengulang gambar garis ruas jalan dari awal.");
}

function handleUndo() {
  if (!olModules) return;
  const { sphere } = olModules;

  // Case A: Drawing is actively in-progress with Draw interaction
  if (drawInteraction) {
    const sketchCoords = drawInteraction.sketchCoords_;
    if (sketchCoords && sketchCoords.length > 1) {
      const fixedIndex = sketchCoords.length - 2;
      const popped = sketchCoords[fixedIndex]?.slice();
      if (popped) {
        redoStack.value.push(popped);
      }
      drawInteraction.removeLastPoint();
      const updatedCoords = drawInteraction.sketchCoords_ || [];
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

  // Case B: Completed line
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
      } else if (coords.length === 1) {
        geom.setCoordinates(coords);
        drawnPointCount.value = 1;
        drawnLengthMeters.value = 0;
        hasCompletedLine.value = false;
      } else {
        clearDraw();
      }
    }
  }
}

function handleRedo() {
  if (redoStack.value.length === 0 || !olModules) return;
  const { sphere } = olModules;
  const nextPoint = redoStack.value.pop();
  if (!nextPoint) return;

  // Case A: Drawing is active with Draw interaction
  if (drawInteraction) {
    drawInteraction.appendCoordinates([nextPoint]);
    const updatedCoords = drawInteraction.sketchCoords_ || [];
    drawnPointCount.value = Math.max(0, updatedCoords.length - 1);
    if (drawnFeature.value) {
      const geom = drawnFeature.value.getGeometry();
      if (geom && drawnPointCount.value >= 2) {
        drawnLengthMeters.value = Math.round(sphere.getLength(geom));
      }
    }
    return;
  }

  // Case B: Completed feature exists
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
      return;
    }
  }

  // Case C: Restart drawing and append coordinate
  startDrawing();
  if (drawInteraction) {
    drawInteraction.appendCoordinates([nextPoint]);
  }
}

function startEditingGeometry(geomData: any, id?: string | number) {
  if (!mapInstance || !olModules) return;
  stopDrawing(false);
  clearDraw();
  redoStack.value = [];

  isDrawing.value = true;
  isEditingExisting.value = true;
  hasCompletedLine.value = true;
  editingRuasId.value = id || null;

  const { GeoJSON, Feature, Modify, Style, Stroke, Circle, Fill, sphere } = olModules;
  const format = new GeoJSON();

  try {
    const rawGeom = typeof geomData === "string" ? JSON.parse(geomData) : geomData;
    // Flatten 1-part MultiLineString into standard LineString for seamless vertex editing and undo/redo
    let editGeom = rawGeom;
    if (rawGeom?.type === "MultiLineString" && Array.isArray(rawGeom.coordinates) && rawGeom.coordinates.length === 1) {
      editGeom = {
        type: "LineString",
        coordinates: rawGeom.coordinates[0],
      };
    }

    const olGeom = format.readGeometry(editGeom, {
      dataProjection: "EPSG:4326",
      featureProjection: "EPSG:3857",
    });

    const feat = new Feature({ geometry: olGeom });
    drawSource.addFeature(feat);
    drawnFeature.value = feat;

    const countPts = () => {
      const g = feat.getGeometry();
      if (!g) return 0;
      const t = g.getType();
      const c = g.getCoordinates();
      if (!Array.isArray(c)) return 0;
      if (t === "MultiLineString") {
        return c.reduce((sum: number, line: any[]) => sum + (Array.isArray(line) ? line.length : 0), 0);
      }
      return c.length;
    };

    drawnPointCount.value = countPts();
    drawnLengthMeters.value = Math.round(sphere.getLength(olGeom));

    // Smoothly zoom/fit to the road segment at center of map canvas
    fitGeometryToCenter(olGeom, 600, () => {
      loadSnapFeatures();
    });

    // Attach Modify interaction
    modifyInteraction = new Modify({
      source: drawSource,
      style: new Style({
        image: new Circle({
          radius: 6,
          fill: new Fill({ color: "#10b981" }),
          stroke: new Stroke({ color: "#ffffff", width: 2.5 }),
        }),
      }),
    });

    modifyInteraction.on("modifystart", () => {
      redoStack.value = [];
    });

    activeGeomListener = () => {
      const totalPoints = countPts();
      drawnPointCount.value = totalPoints;
      if (totalPoints >= 2) {
        drawnLengthMeters.value = Math.round(sphere.getLength(olGeom));
      }
    };
    olGeom.on("change", activeGeomListener);

    mapInstance.addInteraction(modifyInteraction);
    updateSnapInteractions();
    mapInstance.on("moveend", onMapMoveEndDuringDraw);
    emit("log", "INFO", `Mode edit geometri aktif (${drawnPointCount.value} titik, ${drawnLengthMeters.value} m). Geser titik koordinat untuk mengubah bentuk garis.`);
  } catch (err: any) {
    emit("log", "ERROR", `Gagal memuat geometri untuk diedit: ${err?.message}`);
  }
}

function stopDrawing(emitCancel = true) {
  clearTimeout(snapMoveEndTimer);
  if (mapInstance) {
    mapInstance.un("moveend", onMapMoveEndDuringDraw);
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
  }
  lastSnapBbox = "";
  editingRuasId.value = null;
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
    drawnFeature.value.getGeometry()?.un("change", activeGeomListener);
    activeGeomListener = null;
  }
  isDrawing.value = false;
  isEditingExisting.value = false;
  hasCompletedLine.value = false;
  drawnPointCount.value = 0;
  drawnLengthMeters.value = 0;
  redoStack.value = [];
  if (emitCancel) {
    clearDraw();
    emit("drawCanceled");
    emit("log", "INFO", "Mode gambar/edit dibatalkan.");
  }
}

function clearDraw() {
  if (drawSource) {
    drawSource.clear();
  }
  drawnFeature.value = null;
  hasCompletedLine.value = false;
  isEditingExisting.value = false;
  drawnPointCount.value = 0;
  drawnLengthMeters.value = 0;
  redoStack.value = [];
}

function confirmDraw() {
  if (!drawnFeature.value || !olModules) return;
  const geom = drawnFeature.value.getGeometry();
  if (!geom) return;

  const t = geom.getType();
  const coords = geom.getCoordinates();
  if (!Array.isArray(coords)) return;
  const totalPoints = t === "MultiLineString"
    ? coords.reduce((sum: number, line: any[]) => sum + (Array.isArray(line) ? line.length : 0), 0)
    : coords.length;

  if (totalPoints < 2) return;

  // Pastikan geometri berada tepat di tengah map canvas saat dikonfirmasi
  fitGeometryToCenter(geom, 400);

  const geojsonFormat = new olModules.GeoJSON();
  const geojsonObj = geojsonFormat.writeGeometryObject(geom, {
    featureProjection: "EPSG:3857",
    dataProjection: "EPSG:4326",
  });

  const length = Math.round(olModules.sphere.getLength(geom));

  emit("drawSaved", {
    geojson: geojsonObj,
    lengthMeters: length,
  });

  emit("log", "INFO", `Garis ruas berhasil dikonfirmasi (${length} m). Membuka form.`);
}

onUnmounted(() => {
  cancelSplitMode();
  clearTimeout(snapMoveEndTimer);
  if (snapFetchAbort) {
    snapFetchAbort.abort();
    snapFetchAbort = null;
  }
  if (drawInteraction && mapInstance) {
    mapInstance.removeInteraction(drawInteraction);
    drawInteraction = null;
  }
  if (modifyInteraction && mapInstance) {
    mapInstance.removeInteraction(modifyInteraction);
    modifyInteraction = null;
  }
  if (drawLayer && mapInstance) {
    mapInstance.removeLayer(drawLayer);
    drawLayer = null;
    drawSource = null;
  }
  if (mvtSafetyTimeout) {
    clearTimeout(mvtSafetyTimeout);
    mvtSafetyTimeout = null;
  }
  if (pointerRafId !== null) {
    cancelAnimationFrame(pointerRafId);
    pointerRafId = null;
  }
  if (resizeRaf) {
    cancelAnimationFrame(resizeRaf);
    resizeRaf = null;
  }
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (mapInstance) {
    mapInstance.setTarget(null as any);
    mapInstance = null;
    vectorTileSource = null;
    vectorTileLayer = null;
    tileLayer = null;
    tooltipOverlay = null;
  }
});

defineExpose({
  fitBounds,
  zoomIn,
  zoomOut,
  zoomToCentroid,
  zoomToGeometry,
  refreshVectorTiles,
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
  <div class="relative w-full h-full overflow-hidden select-none bg-gray-100 dark:bg-[#070b14]">
    <!-- Map Canvas Element -->
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
        v-if="isMvtLoading && mapLoaded && !isDrawing && !isSplitMode"
        class="absolute top-3 left-3 z-20 flex items-center gap-2 px-2.5 py-1.5 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-xs border border-emerald-500/30 dark:border-emerald-500/30 rounded-lg shadow-sm pointer-events-none"
      >
        <UIcon
          name="i-lucide-loader-2"
          class="size-3.5 text-emerald-600 dark:text-emerald-400 animate-spin shrink-0"
        />
        <span class="text-xs font-medium text-gray-700 dark:text-gray-200">
          Memuat tile MVT...
        </span>
      </div>
    </Transition>

    <!-- Floating Split Mode Banner -->
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
        class="absolute top-3 left-3 z-30 flex items-center gap-2 px-3 py-2 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-xs border border-amber-500/50 rounded-lg shadow-md text-xs select-none max-w-sm sm:max-w-md"
      >
        <div class="size-6 rounded-md bg-amber-500/10 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-scissors" class="size-4 animate-pulse" />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="font-semibold text-gray-900 dark:text-white leading-tight truncate">
            Mode Split Ruas Jalan
          </span>
          <span class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight truncate">
            Klik pada badan ruas <span class="font-medium text-amber-600 dark:text-amber-400">"{{ splitRoadData?.nama_ruas || splitRoadData?.properties?.nama_ruas || 'jalan' }}"</span> untuk memotong.
          </span>
        </div>
        <UButton
          icon="i-lucide-x"
          size="xs"
          color="neutral"
          variant="ghost"
          class="ml-auto text-gray-400 hover:text-red-500 dark:hover:text-red-400 shrink-0"
          title="Batal Split"
          @click="cancelSplitMode"
        />
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
            <UTooltip :text="isSnappingEnabled ? 'Snapping Aktif: Magnet Ruas & Titik' : 'Snapping Nonaktif (Klik untuk Aktifkan)'">
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
                ? 'bg-emerald-50/95 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300'
                : 'bg-white/95 dark:bg-[#0b0f19]/95 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400'"
              :title="isSnappingEnabled ? 'Klik untuk menonaktifkan snapping' : 'Klik untuk mengaktifkan snapping'"
              @click="toggleSnapping"
            >
              <span
                class="size-1.5 rounded-full shrink-0"
                :class="isSnappingEnabled ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400 dark:bg-gray-500'"
              />
              <span>{{ isSnappingEnabled ? 'Aktif' : 'Non Aktif' }}</span>
              <span
                v-if="isSnappingEnabled && isSnapLoading"
                class="flex items-center gap-0.5 text-[9px] text-emerald-600/70"
              >
                <UIcon name="i-lucide-loader-2" class="size-2 animate-spin" />
              </span>
              <span
                v-else-if="isSnappingEnabled && snapRoadCount > 0"
                class="text-[9px] font-mono text-emerald-600/80 dark:text-emerald-400/80"
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
                <div class="flex items-center gap-1 font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                  <UIcon name="i-lucide-route" class="size-3 shrink-0" />
                  <span>{{ drawnLengthMeters.toLocaleString('id-ID') }} m</span>
                </div>

                <div class="w-px h-3 bg-gray-200 dark:bg-gray-800 shrink-0" />

                <!-- Titik / Vertex -->
                <div class="flex items-center gap-1 text-gray-500 dark:text-gray-400 font-mono">
                  <span>{{ drawnPointCount }} vertex</span>
                  <span
                    v-if="isEditingExisting"
                    class="text-[9px] font-sans font-medium text-amber-500"
                  >
                    (Edit)
                  </span>
                  <span
                    v-else-if="hasCompletedLine"
                    class="text-[9px] font-sans font-medium text-emerald-500"
                  >
                    (Selesai)
                  </span>
                </div>
              </div>
            </Transition>
          </div>

          <!-- 6. Batal / Tutup -->
          <UTooltip text="Batal & Tutup Draw">
            <UButton
              icon="i-lucide-x"
              size="xs"
              color="neutral"
              variant="ghost"
              class="text-gray-400 hover:text-red-500 dark:hover:text-red-400"
              @click="stopDrawing(true)"
            />
          </UTooltip>
        </div>
      </div>
    </Transition>

    <!-- Hover Tooltip & Callout Line Overlay -->
    <div
      ref="tooltipEl"
      class="pointer-events-none transition-opacity duration-150 z-30 flex flex-col items-center"
      :class="tooltipData ? 'opacity-100' : 'opacity-0'"
    >
      <div
        v-if="tooltipData"
        class="px-3 py-2 rounded-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-xl min-w-[190px] max-w-[260px] space-y-1.5"
      >
        <div class="flex items-center gap-1.5">
          <div class="size-1.5 rounded-full bg-emerald-500 shrink-0" />
          <p class="font-semibold text-gray-900 dark:text-gray-100 truncate text-xs">
            {{ tooltipData.nama_ruas }}
          </p>
        </div>

        <div class="flex items-center justify-between gap-2 pt-0.5">
          <UBadge
            :label="tooltipData.kondisi || '-'"
            :color="getKondisiBadgeColor(tooltipData.kondisi)"
            variant="subtle"
            size="xs"
          />
          <span class="font-mono text-gray-700 dark:text-gray-300 text-[11px] font-semibold">
            {{ Number(tooltipData.panjang_meter).toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m
          </span>
        </div>

        <div class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 truncate pt-0.5 border-t border-gray-100 dark:border-gray-800/80">
          <UIcon name="i-lucide-map-pin" class="size-3 shrink-0 text-emerald-500" />
          <span class="truncate">{{ tooltipData.desa }}, Kec. {{ tooltipData.kecamatan }}</span>
        </div>
      </div>

      <!-- Garis Callout (Leader Stem & Target Anchor Pin) -->
      <div v-if="tooltipData" class="flex flex-col items-center -mt-[1px]">
        <!-- Segitiga penunjuk kecil -->
        <div class="w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-gray-200 dark:border-t-gray-800" />
        <!-- Batang Garis Callout (Lebih Tinggi untuk visibilitas optimal) -->
        <div class="w-[2px] h-9 bg-emerald-500 dark:bg-emerald-400 shadow-xs -mt-[1px]" />
        <!-- Pin Target Anchor Dot di Titik Vektor -->
        <div class="relative flex items-center justify-center -mt-[2px]">
          <span class="size-3.5 rounded-full bg-emerald-500/40 animate-ping absolute" />
          <span class="size-2 rounded-full bg-emerald-500 dark:bg-emerald-400 ring-2 ring-white dark:ring-[#070b14] shadow-sm" />
        </div>
      </div>
    </div>

    <!-- Clicked Coordinate Pulse Marker Overlay -->
    <div
      ref="pulseEl"
      class="pointer-events-none z-20 flex items-center justify-center size-8"
      :class="isPulseVisible ? 'block' : 'hidden'"
    >
      <span class="absolute size-8 rounded-full bg-emerald-500/40 animate-ping" />
      <span class="absolute size-5 rounded-full bg-emerald-500/25" />
      <span class="relative size-3.5 rounded-full bg-emerald-600 ring-2 ring-white dark:ring-gray-950 shadow-md flex items-center justify-center">
        <span class="size-1 rounded-full bg-white" />
      </span>
    </div>

    <!-- Floating Navigation Controls (Top Right, Clean Solid) -->
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

      <UTooltip text="Fokus Semua Ruas">
        <UButton
          icon="i-lucide-maximize-2"
          size="xs"
          color="primary"
          variant="ghost"
          @click="fitBounds"
        />
      </UTooltip>
    </div>

    <!-- Floating Basemap Selector (Bottom Left, Clean Solid) -->
    <div class="absolute bottom-16 sm:bottom-3 left-3 z-20 flex items-center gap-1 p-1 bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
      <UButton
        v-for="bm in (['osm', 'dark', 'satellite'] as const)"
        :key="bm"
        :label="bm === 'satellite' ? 'Satelit' : bm === 'dark' ? 'Gelap' : 'OSM'"
        size="xs"
        :color="basemap === bm ? 'primary' : 'neutral'"
        :variant="basemap === bm ? 'subtle' : 'ghost'"
        class="capitalize text-xs font-medium"
        @click="setBasemap(bm)"
      />
    </div>

    <!-- Floating Coordinates Pill (Desktop only, Bottom Center) -->
    <div class="hidden md:flex absolute bottom-3 left-1/2 -translate-x-1/2 z-20 pointer-events-none">
      <UBadge
        :label="mouseCoords || '0.00000, 0.00000'"
        color="neutral"
        variant="subtle"
        size="xs"
        class="font-mono shadow-sm px-2.5 py-0.5"
      />
    </div>

    <!-- Floating Scale Line Container (Bottom Right, Clean Glassmorphic) -->
    <div
      ref="scaleLineTarget"
      class="absolute bottom-16 sm:bottom-3 right-3 z-20 pointer-events-none select-none"
    />

    <!-- Loading Overlay -->
    <div
      v-if="!mapLoaded"
      class="absolute inset-0 flex items-center justify-center bg-gray-100/90 dark:bg-[#070b14]/90 z-30"
    >
      <div class="flex flex-col items-center gap-2">
        <UIcon name="i-lucide-loader-2" class="size-6 text-emerald-600 dark:text-emerald-400 animate-spin" />
        <span class="text-xs text-gray-500 font-medium">Memuat peta spasial...</span>
      </div>
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
</style>

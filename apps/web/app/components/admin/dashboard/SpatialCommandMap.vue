<script setup lang="ts">
import "ol/ol.css";
import {
  BOJONEGORO_DEFAULT_BBOX,
  formatDecimalId,
  formatIntId,
  getKondisiMeta,
  type KecamatanSeriesItem,
} from "~/types/admin-dashboard";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";
import { useBasemapStore } from "~/stores/basemap";
import { createOpenLayersBasemapSource } from "~/utils/basemap-sources";

interface InspectedFeature {
  source: "infrastruktur" | "jalan_poros" | "kecamatan" | "desa";
  title: string;
  subtitle?: string;
  kecamatan?: string;
  desa?: string;
  kondisi?: string;
  perkerasan?: string;
  panjangMeter?: number | null;
  statusVerifikasi?: string;
  kecamatanId?: number | null;
}

interface Props {
  kecamatanList: KecamatanSeriesItem[];
  loadingKecamatan: boolean;
  kecamatanError: string | null;
  selectedKecamatanId: number | null;
  selectedDesaId: number | null;
  selectedKondisi: string | null;
  activeBbox: number[] | null;
  isTerritoryLocked: boolean;
  lockedScopeLabel: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: "select-kecamatan", id: number | null): void;
  (e: "select-kondisi", kondisi: string | null): void;
  (e: "retry-kecamatan"): void;
}>();

const config = useRuntimeConfig();
const colorMode = useColorMode();

const martinUrl = computed(() => config.public.martinUrl || "/martin");

const basemapStore = useBasemapStore();
const mapContainerRef = ref<HTMLElement | null>(null);
const searchQuery = ref("");
const basemapMode = ref<string>("esri");

const layerVisibility = reactive({
  infrastruktur: true,
  jalanPoros: true,
  batasKecamatan: true,
  batasDesa: false,
});

const inspected = ref<InspectedFeature | null>(null);
const mvtTileWarning = ref(false);
const isMapReady = ref(false);
const mapInitError = ref<string | null>(null);
const currentZoom = ref(10);

let olMap: any = null;
let olModules: any = null;
let baseTileLayer: any = null;
let kecamatanLayer: any = null;
let desaLayer: any = null;
let jalanPorosLayer: any = null;
let infrastrukturLayer: any = null;
let highlightSource: any = null;
let resizeObserver: ResizeObserver | null = null;
let tileErrorCount = 0;

const filteredKecamatan = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return props.kecamatanList;
  return props.kecamatanList.filter(
    (item) =>
      item.nama_kecamatan.toLowerCase().includes(q) ||
      (item.nama_pimpinan && item.nama_pimpinan.toLowerCase().includes(q)),
  );
});

const selectedKecamatanName = computed(() => {
  if (!props.selectedKecamatanId) return null;
  return (
    props.kecamatanList.find((k) => Number(k.id) === Number(props.selectedKecamatanId))
      ?.nama_kecamatan ?? null
  );
});

function prefersReducedMotion(): boolean {
  if (typeof window === "undefined") return true;
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function buildBasemapSource() {
  if (!olModules) return null;
  if (basemapMode.value === "kanvas" || basemapMode.value === "none") return null;
  const item = basemapStore.getByCode(basemapMode.value, "openlayers");
  return createOpenLayersBasemapSource(item ?? basemapMode.value, olModules);
}

function updateBasemapSource() {
  if (!baseTileLayer || !olModules) return;
  const source = buildBasemapSource();
  baseTileLayer.setSource(source);
  baseTileLayer.setVisible(Boolean(source));
}

function resolveFeatureKecamatanMatch(feature: any): boolean {
  if (!props.selectedKecamatanId) return true;
  const rawId = feature.get("id_kecamatan") ?? feature.get("id") ?? feature.getId?.();
  const fid = Number(rawId);
  if (!Number.isNaN(fid) && fid === Number(props.selectedKecamatanId)) {
    return true;
  }
  const fName = String(
    feature.get("nama_kecamatan") ?? feature.get("kecamatan") ?? "",
  )
    .trim()
    .toLowerCase();
  const targetName = (selectedKecamatanName.value || "").trim().toLowerCase();
  return Boolean(targetName && fName && fName === targetName);
}

function resolveKondisiColor(kondisi?: string | null): string {
  return getKondisiMeta(kondisi || "").hex;
}

function makeInfrastrukturStyle(feature: any) {
  if (!olModules) return undefined;
  const matchedKec = resolveFeatureKecamatanMatch(feature);
  const fKondisi = String(feature.get("kondisi") ?? "");
  const matchedKondisi =
    !props.selectedKondisi ||
    fKondisi.toLowerCase() === props.selectedKondisi.toLowerCase();
  const isHighlighted = matchedKec && matchedKondisi;

  const strokeColor = isHighlighted ? resolveKondisiColor(fKondisi) : "#94a3b8";
  const width = isHighlighted ? (currentZoom.value >= 13 ? 4 : 2.75) : 1.25;
  const opacityAlpha = isHighlighted ? 1 : 0.22;

  return new olModules.Style({
    stroke: new olModules.Stroke({
      color: hexToRgba(strokeColor, opacityAlpha),
      width,
      lineCap: "round",
      lineJoin: "round",
    }),
  });
}

function makeJalanPorosStyle(feature: any) {
  if (!olModules) return undefined;
  const matchedKec = resolveFeatureKecamatanMatch(feature);
  const isDark = colorMode.value === "dark";
  const baseColor = isDark ? "#38bdf8" : "#0284c7";
  return new olModules.Style({
    stroke: new olModules.Stroke({
      color: hexToRgba(baseColor, matchedKec ? 0.72 : 0.16),
      width: matchedKec ? (currentZoom.value >= 13 ? 2.5 : 1.5) : 1,
      lineDash: [5, 3],
    }),
  });
}

function makeKecamatanStyle(feature: any) {
  if (!olModules) return undefined;
  const isSelected = Boolean(props.selectedKecamatanId) && resolveFeatureKecamatanMatch(feature);
  const isDark = colorMode.value === "dark";
  const strokeColor = isSelected
    ? isDark
      ? "#60a5fa"
      : "#1d4ed8"
    : isDark
      ? "rgba(148, 163, 184, 0.45)"
      : "rgba(71, 85, 105, 0.45)";
  const fillColor = isSelected
    ? isDark
      ? "rgba(59, 130, 246, 0.14)"
      : "rgba(37, 99, 235, 0.08)"
    : "rgba(148, 163, 184, 0.02)";

  const name = feature.get("nama_kecamatan");
  return new olModules.Style({
    stroke: new olModules.Stroke({
      color: strokeColor,
      width: isSelected ? 2.4 : 1.15,
    }),
    fill: new olModules.Fill({ color: fillColor }),
    text:
      currentZoom.value >= 10 && name
        ? new olModules.Text({
            text: String(name),
            font: isSelected ? "600 11px Plus Jakarta Sans, sans-serif" : "500 10px Plus Jakarta Sans, sans-serif",
            fill: new olModules.Fill({
              color: isDark ? "#e2e8f0" : "#1e293b",
            }),
            stroke: new olModules.Stroke({
              color: isDark ? "rgba(15, 23, 42, 0.85)" : "rgba(255, 255, 255, 0.9)",
              width: 3,
            }),
            overflow: false,
          })
        : undefined,
  });
}

function makeDesaStyle() {
  if (!olModules) return undefined;
  const isDark = colorMode.value === "dark";
  return new olModules.Style({
    stroke: new olModules.Stroke({
      color: isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(100, 116, 139, 0.28)",
      width: 0.8,
      lineDash: [3, 3],
    }),
    fill: new olModules.Fill({ color: "rgba(0,0,0,0)" }),
  });
}

function hexToRgba(hex: string, alpha: number): string {
  const clean = hex.replace("#", "");
  if (clean.length !== 6) return hex;
  const r = parseInt(clean.slice(0, 2), 16);
  const g = parseInt(clean.slice(2, 4), 16);
  const b = parseInt(clean.slice(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function updateHighlightBoxAndFit(bbox?: number[] | null) {
  if (!olMap || !olModules || !highlightSource) return;
  const raw =
    bbox && bbox.length === 4 && bbox.every((n) => Number.isFinite(Number(n)))
      ? (bbox.map(Number) as [number, number, number, number])
      : BOJONEGORO_DEFAULT_BBOX;

  highlightSource.clear();

  // Only draw bbox indicator frame when focused on a specific sub-territory
  if (props.selectedKecamatanId || props.selectedDesaId) {
    const ext3857 = olModules.transformExtent(raw, "EPSG:4326", "EPSG:3857");
    const boxGeom = olModules.fromExtent(ext3857);
    const feat = new olModules.Feature({ geometry: boxGeom });
    highlightSource.addFeature(feat);
  }

  const extent3857 = olModules.transformExtent(raw, "EPSG:4326", "EPSG:3857");
  olMap.getView().fit(extent3857, {
    padding: [36, 36, 36, 36],
    duration: prefersReducedMotion() ? 0 : 420,
    maxZoom: 16,
  });
}

function refreshVectorStyles() {
  kecamatanLayer?.changed();
  jalanPorosLayer?.changed();
  infrastrukturLayer?.changed();
}

function zoomBy(delta: number) {
  if (!olMap) return;
  const view = olMap.getView();
  const z = view.getZoom() ?? 10;
  view.animate({
    zoom: z + delta,
    duration: prefersReducedMotion() ? 0 : 200,
  });
}

function fitActiveBounds() {
  updateHighlightBoxAndFit(props.activeBbox);
}

function inspectMapPixel(pixel: number[]) {
  if (!olMap) return;
  let found: InspectedFeature | null = null;

  olMap.forEachFeatureAtPixel(
    pixel,
    (feature: any, layer: any) => {
      const lid = layer?.get("layerId");
      if (!lid || found) return;

      if (lid === "infrastruktur") {
        found = {
          source: "infrastruktur",
          title: feature.get("namobj") || "Segmen Infrastruktur",
          subtitle: feature.get("jenis_perkerasan") || "Segmen Jalan",
          kecamatan: feature.get("kecamatan"),
          desa: feature.get("desa"),
          kondisi: feature.get("kondisi"),
          perkerasan: feature.get("jenis_perkerasan"),
          panjangMeter: Number(feature.get("panjang") ?? 0) || null,
          statusVerifikasi: feature.get("status_verifikasi"),
          kecamatanId: Number(feature.get("id_kecamatan")) || null,
        };
      } else if (lid === "jalanPoros") {
        found = {
          source: "jalan_poros",
          title: feature.get("nama_ruas") || "Ruas Jalan Poros Desa",
          subtitle: feature.get("kode_ruas") ? `Ruas #${feature.get("kode_ruas")}` : "Master Poros Desa",
          kecamatan: feature.get("kecamatan"),
          desa: feature.get("desa"),
          kondisi: feature.get("kondisi"),
          perkerasan: feature.get("perkerasan"),
          panjangMeter: Number(feature.get("panjang") ?? 0) || null,
          kecamatanId: Number(feature.get("id_kecamatan")) || null,
        };
      } else if (lid === "kecamatan") {
        const kid = Number(feature.get("id") ?? feature.getId?.()) || null;
        found = {
          source: "kecamatan",
          title: `Kecamatan ${feature.get("nama_kecamatan") || "-"}`,
          subtitle: "Batas Wilayah Kecamatan",
          kecamatan: feature.get("nama_kecamatan"),
          kecamatanId: kid,
        };
      } else if (lid === "desa") {
        found = {
          source: "desa",
          title: `Desa ${feature.get("nama_desa") || "-"}`,
          subtitle: "Batas Administrasi Desa",
          kecamatan: feature.get("nama_kecamatan"),
          desa: feature.get("nama_desa"),
          kecamatanId: Number(feature.get("id_kecamatan")) || null,
        };
      }
    },
    { hitTolerance: 6 },
  );

  inspected.value = found;
}

async function initMap() {
  if (!import.meta.client) return;
  await nextTick();
  if (!mapContainerRef.value) return;

  mapInitError.value = null;

  try {
    const [
      { default: OlMap },
      { default: View },
      { default: TileLayer },
      { default: VectorLayer },
      { default: VectorTileLayer },
      { default: XYZ },
      { default: VectorSource },
      { default: VectorTileSource },
      { default: MVT },
      { default: Feature },
      { fromExtent },
      { transformExtent },
      { default: Style },
      { default: Fill },
      { default: Stroke },
      { default: Text },
      { defaults: defaultControls },
    ] = await Promise.all([
      import("ol/Map.js"),
      import("ol/View.js"),
      import("ol/layer/Tile.js"),
      import("ol/layer/Vector.js"),
      import("ol/layer/VectorTile.js"),
      import("ol/source/XYZ.js"),
      import("ol/source/Vector.js"),
      import("ol/source/VectorTile.js"),
      import("ol/format/MVT.js"),
      import("ol/Feature.js"),
      import("ol/geom/Polygon.js"),
      import("ol/proj.js"),
      import("ol/style/Style.js"),
      import("ol/style/Fill.js"),
      import("ol/style/Stroke.js"),
      import("ol/style/Text.js"),
      import("ol/control/defaults.js"),
    ]);

    if (!mapContainerRef.value) return;

    olModules = {
      XYZ,
      Feature,
      fromExtent,
      transformExtent,
      Style,
      Fill,
      Stroke,
      Text,
    };

    const initialBasemapSource = buildBasemapSource();
    baseTileLayer = new TileLayer({
      source: initialBasemapSource ?? undefined,
      visible: Boolean(initialBasemapSource),
      zIndex: 0,
    });

    const makeMvtSource = (sourceId: string) => {
      const src = new VectorTileSource({
        format: new MVT({ idProperty: "id" }),
        url: `${martinUrl.value}/${sourceId}/{z}/{x}/{y}`,
        minZoom: 7,
        maxZoom: 19,
        cacheSize: 256,
      });
      src.on("tileloaderror", () => {
        tileErrorCount++;
        if (tileErrorCount >= 4) {
          mvtTileWarning.value = true;
        }
      });
      src.on("tileloadend", () => {
        mvtTileWarning.value = false;
      });
      return src;
    };

    kecamatanLayer = new VectorTileLayer({
      source: makeMvtSource("bataswilayah_kecamatan"),
      style: makeKecamatanStyle,
      declutter: true,
      visible: layerVisibility.batasKecamatan,
      zIndex: 4,
    });
    kecamatanLayer.set("layerId", "kecamatan");

    desaLayer = new VectorTileLayer({
      source: makeMvtSource("bataswilayah_desa"),
      style: makeDesaStyle,
      minZoom: 11,
      visible: layerVisibility.batasDesa,
      zIndex: 5,
    });
    desaLayer.set("layerId", "desa");

    jalanPorosLayer = new VectorTileLayer({
      source: makeMvtSource("jalan_porosdesa"),
      style: makeJalanPorosStyle,
      visible: layerVisibility.jalanPoros,
      zIndex: 7,
    });
    jalanPorosLayer.set("layerId", "jalanPoros");

    infrastrukturLayer = new VectorTileLayer({
      source: makeMvtSource("infrastruktur_segmen"),
      style: makeInfrastrukturStyle,
      visible: layerVisibility.infrastruktur,
      zIndex: 9,
    });
    infrastrukturLayer.set("layerId", "infrastruktur");

    highlightSource = new VectorSource({ wrapX: false });
    const highlightLayer = new VectorLayer({
      source: highlightSource,
      zIndex: 12,
      style: new Style({
        stroke: new Stroke({
          color: "#2563eb",
          width: 2,
          lineDash: [6, 4],
        }),
        fill: new Fill({ color: "rgba(37, 99, 235, 0.04)" }),
      }),
    });

    const initialExtent = transformExtent(
      (props.activeBbox && props.activeBbox.length === 4
        ? props.activeBbox
        : BOJONEGORO_DEFAULT_BBOX) as [number, number, number, number],
      "EPSG:4326",
      "EPSG:3857",
    );

    if (olMap) {
      olMap.setTarget(undefined);
      olMap = null;
    }

    olMap = new OlMap({
      target: mapContainerRef.value,
      controls: defaultControls({ zoom: false, attribution: false, rotate: false }),
      layers: [
        baseTileLayer,
        kecamatanLayer,
        desaLayer,
        jalanPorosLayer,
        infrastrukturLayer,
        highlightLayer,
      ],
      view: new View({
        center: [
          (initialExtent[0] + initialExtent[2]) / 2,
          (initialExtent[1] + initialExtent[3]) / 2,
        ],
        zoom: 10,
        minZoom: 8,
        maxZoom: 19,
      }),
    });

    olMap.getView().fit(initialExtent, { padding: [32, 32, 32, 32], duration: 0 });
    isMapReady.value = true;

    requestAnimationFrame(() => {
      olMap?.updateSize();
    });

    olMap.getView().on("change:resolution", () => {
      const z = Math.round(olMap.getView().getZoom() ?? 10);
      if (z !== currentZoom.value) {
        currentZoom.value = z;
        refreshVectorStyles();
      }
    });

    olMap.on("singleclick", (evt: any) => {
      inspectMapPixel(evt.pixel);
    });

    olMap.on("pointermove", (evt: any) => {
      if (evt.dragging) return;
      const hit = olMap.hasFeatureAtPixel(evt.pixel, { hitTolerance: 6 });
      const targetEl = olMap.getTargetElement();
      if (targetEl) {
        targetEl.style.cursor = hit ? "pointer" : "grab";
      }
    });

    resizeObserver?.disconnect();
    resizeObserver = new ResizeObserver(() => {
      olMap?.updateSize();
    });
    resizeObserver.observe(mapContainerRef.value);
  } catch (err: any) {
    console.error("Gagal menginisialisasi peta dashboard admin:", err);
    mapInitError.value = err?.message || "Gagal menginisialisasi kanvas OpenLayers.";
    isMapReady.value = false;
  }
}

onMounted(() => {
  initMap();
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  if (olMap) {
    olMap.setTarget(undefined);
    olMap = null;
  }
});

watch(
  () => [props.selectedKecamatanId, props.selectedDesaId, props.selectedKondisi],
  () => {
    refreshVectorStyles();
  },
);

watch(
  () => props.activeBbox,
  (nextBbox) => {
    updateHighlightBoxAndFit(nextBbox);
  },
  { deep: true },
);

watch([basemapMode, () => basemapStore.items], () => {
  updateBasemapSource();
});

watch(
  () => colorMode.value,
  () => {
    refreshVectorStyles();
  },
);

watch(
  () => layerVisibility.infrastruktur,
  (v) => infrastrukturLayer?.setVisible(v),
);
watch(
  () => layerVisibility.jalanPoros,
  (v) => jalanPorosLayer?.setVisible(v),
);
watch(
  () => layerVisibility.batasKecamatan,
  (v) => kecamatanLayer?.setVisible(v),
);
watch(
  () => layerVisibility.batasDesa,
  (v) => desaLayer?.setVisible(v),
);
</script>

<template>
  <section
    aria-labelledby="spatial-command-title"
    class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden"
  >
    <!-- Header Bar -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-map" class="size-4.5 text-blue-600 dark:text-blue-400 shrink-0" />
          <h2
            id="spatial-command-title"
            class="text-base font-bold text-slate-900 dark:text-slate-50 tracking-tight truncate"
          >
            Peta Eksplorasi Spasial Bojonegoro
          </h2>
          <span
            v-if="selectedKecamatanName"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/70"
          >
            Kec. {{ selectedKecamatanName }}
          </span>
          <span
            v-if="selectedKondisi"
            :class="[
              'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium border',
              getKondisiMeta(selectedKondisi).badgeClass,
            ]"
          >
            {{ selectedKondisi }}
          </span>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
          Klik wilayah kecamatan atau segmen jalan di peta untuk inspeksi atribut, atau pilih dari daftar wilayah.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-1.5">
        <NuxtLink
          to="/maps"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-10 sm:min-h-8 rounded-lg text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 dark:text-blue-300 dark:bg-blue-950/50 dark:hover:bg-blue-900/60 dark:border-blue-800/60 transition-colors focus-visible:outline-2 focus-visible:outline-blue-600"
        >
          <UIcon name="i-lucide-maximize-2" class="size-3.5 shrink-0" />
          <span>WebGIS Layar Penuh</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Split Workspace: Interactive Map (8 cols) + Kecamatan Explorer Rail (4 cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12">
      <!-- Map Viewport -->
      <div class="lg:col-span-8 relative h-[360px] sm:h-[420px] xl:h-[460px] bg-slate-100 dark:bg-[#070b14] border-b lg:border-b-0 lg:border-e border-slate-200 dark:border-slate-800 overflow-hidden">
        <!-- Cartographic Background Grid for "Kanvas" Mode -->
        <div
          v-if="basemapMode === 'kanvas' || basemapMode === 'none'"
          class="absolute inset-0 pointer-events-none opacity-45 dark:opacity-25 bg-[radial-gradient(#94a3b8_1px,transparent_1px)] [background-size:20px_20px]"
          aria-hidden="true"
        />

        <div
          ref="mapContainerRef"
          class="absolute inset-0 w-full h-full"
          role="application"
          aria-label="Peta interaktif jaringan jalan dan batas administrasi Kabupaten Bojonegoro"
        />

        <!-- Status Muat / Error Inisialisasi Peta -->
        <div
          v-if="!isMapReady && !mapInitError"
          class="absolute inset-0 z-[5] flex flex-col items-center justify-center gap-2 bg-slate-100/90 dark:bg-[#070b14]/90 text-xs font-medium text-slate-600 dark:text-slate-300 pointer-events-none"
        >
          <UIcon name="i-lucide-loader-circle" class="size-5 animate-spin text-blue-600 dark:text-blue-400" />
          <span>Memuat kanvas peta spasial...</span>
        </div>

        <div
          v-if="mapInitError"
          class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2.5 p-4 text-center bg-slate-100/95 dark:bg-[#070b14]/95"
        >
          <UIcon name="i-lucide-map-pin-off" class="size-7 text-red-600 dark:text-red-400" />
          <p class="text-xs font-medium text-slate-800 dark:text-slate-200 max-w-sm">
            {{ mapInitError }}
          </p>
          <button
            type="button"
            class="px-3.5 py-2 min-h-9 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white cursor-pointer"
            @click="initMap"
          >
            Muat Ulang Peta
          </button>
        </div>

        <!-- Top-Left Layer Switcher Pills -->
        <div
          class="absolute top-3 left-3 right-14 z-10 flex flex-wrap items-center gap-1.5 pointer-events-none"
        >
          <button
            type="button"
            class="pointer-events-auto inline-flex items-center gap-1.5 px-2.5 py-1.5 min-h-9 rounded-lg text-xs font-medium border shadow-xs backdrop-blur-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              layerVisibility.infrastruktur
                ? 'bg-white/95 text-slate-900 border-blue-300 dark:bg-slate-900/95 dark:text-slate-50 dark:border-blue-700'
                : 'bg-white/85 text-slate-600 border-slate-200 dark:bg-slate-900/80 dark:text-slate-400 dark:border-slate-700'
            "
            :aria-pressed="layerVisibility.infrastruktur"
            @click="layerVisibility.infrastruktur = !layerVisibility.infrastruktur"
          >
            <span class="size-2 rounded-full bg-emerald-600 dark:bg-emerald-400 shrink-0" />
            <span>Segmen Fisik</span>
          </button>

          <button
            type="button"
            class="pointer-events-auto inline-flex items-center gap-1.5 px-2.5 py-1.5 min-h-9 rounded-lg text-xs font-medium border shadow-xs backdrop-blur-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              layerVisibility.jalanPoros
                ? 'bg-white/95 text-slate-900 border-sky-300 dark:bg-slate-900/95 dark:text-slate-50 dark:border-sky-700'
                : 'bg-white/85 text-slate-600 border-slate-200 dark:bg-slate-900/80 dark:text-slate-400 dark:border-slate-700'
            "
            :aria-pressed="layerVisibility.jalanPoros"
            @click="layerVisibility.jalanPoros = !layerVisibility.jalanPoros"
          >
            <span class="size-2 rounded-full bg-sky-600 dark:bg-sky-400 shrink-0" />
            <span>Poros Desa</span>
          </button>

          <button
            type="button"
            class="pointer-events-auto inline-flex items-center gap-1.5 px-2.5 py-1.5 min-h-9 rounded-lg text-xs font-medium border shadow-xs backdrop-blur-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              layerVisibility.batasKecamatan
                ? 'bg-white/95 text-slate-900 border-slate-400 dark:bg-slate-900/95 dark:text-slate-50 dark:border-slate-600'
                : 'bg-white/85 text-slate-600 border-slate-200 dark:bg-slate-900/80 dark:text-slate-400 dark:border-slate-700'
            "
            :aria-pressed="layerVisibility.batasKecamatan"
            @click="layerVisibility.batasKecamatan = !layerVisibility.batasKecamatan"
          >
            <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
            <span>Batas Kec.</span>
          </button>

          <button
            type="button"
            class="pointer-events-auto inline-flex items-center gap-1.5 px-2.5 py-1.5 min-h-9 rounded-lg text-xs font-medium border shadow-xs backdrop-blur-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              layerVisibility.batasDesa
                ? 'bg-white/95 text-slate-900 border-slate-400 dark:bg-slate-900/95 dark:text-slate-50 dark:border-slate-600'
                : 'bg-white/85 text-slate-600 border-slate-200 dark:bg-slate-900/80 dark:text-slate-400 dark:border-slate-700'
            "
            :aria-pressed="layerVisibility.batasDesa"
            @click="layerVisibility.batasDesa = !layerVisibility.batasDesa"
          >
            <span>Batas Desa</span>
          </button>
        </div>

        <!-- Top-Right Basemap Switcher (Medium Basemap) -->
        <div class="absolute top-3 right-3 z-10 flex flex-col gap-1.5">
          <BasemapSwitcher
            v-model="basemapMode"
            variant="medium"
            engine="openlayers"
            :allow-none="true"
            placement="top-right"
          />
        </div>

        <!-- MVT Tile Offline Hint (Real network condition only, never fake error) -->
        <div
          v-if="mvtTileWarning"
          class="absolute bottom-3 left-3 z-10 max-w-xs px-3 py-2 rounded-lg bg-amber-50/95 dark:bg-amber-950/90 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 shadow-sm"
        >
          Server tile vektor Martin belum merespons. Layer MVT akan tampil otomatis setelah tile server aktif.
        </div>

        <!-- Clicked Feature Inspector Card (Non-blocking corner card) -->
        <div
          v-else-if="inspected"
          class="absolute bottom-3 left-3 z-10 w-[calc(100%-5rem)] max-w-xs rounded-xl bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-700 shadow-md p-3 backdrop-blur-md"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">
                {{ inspected.subtitle || "Informasi Fitur Spasial" }}
              </p>
              <h3 class="text-sm font-bold text-slate-900 dark:text-slate-50 truncate mt-0.5">
                {{ inspected.title }}
              </h3>
            </div>
            <button
              type="button"
              aria-label="Tutup panel inspeksi peta"
              class="min-w-8 min-h-8 flex items-center justify-center rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="inspected = null"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
            <span v-if="inspected.kecamatan">Kec. {{ inspected.kecamatan }}</span>
            <span v-if="inspected.desa" class="text-slate-400">/</span>
            <span v-if="inspected.desa">Desa {{ inspected.desa }}</span>
            <span
              v-if="inspected.panjangMeter"
              class="ms-auto font-semibold text-slate-900 dark:text-slate-100 tabular-nums"
            >
              {{ formatDecimalId(inspected.panjangMeter / 1000, 2) }} km
            </span>
          </div>

          <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            <span
              v-if="inspected.kondisi"
              :class="[
                'inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border',
                getKondisiMeta(inspected.kondisi).badgeClass,
              ]"
            >
              {{ inspected.kondisi }}
            </span>

            <button
              v-if="
                !isTerritoryLocked &&
                inspected.kecamatanId &&
                Number(inspected.kecamatanId) !== Number(selectedKecamatanId)
              "
              type="button"
              class="ms-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="emit('select-kecamatan', inspected.kecamatanId!)"
            >
              <UIcon name="i-lucide-filter" class="size-3" />
              <span>Filter Wilayah</span>
            </button>
          </div>
        </div>

        <!-- Condition Compact Legend Pill (Bottom Left when inspector closed & no warning) -->
        <div
          v-else-if="layerVisibility.infrastruktur"
          class="hidden sm:flex absolute bottom-3 left-3 z-10 items-center gap-2.5 px-2.5 py-1.5 rounded-lg bg-white/92 dark:bg-slate-900/92 border border-slate-200 dark:border-slate-700 shadow-xs text-[11px] font-medium text-slate-700 dark:text-slate-300 backdrop-blur-md"
        >
          <span
            v-for="item in ['Baik', 'Sedang', 'Rusak Ringan', 'Rusak Berat']"
            :key="item"
            class="inline-flex items-center gap-1.5"
          >
            <span
              class="size-2 rounded-full shrink-0"
              :style="{ backgroundColor: getKondisiMeta(item).hex }"
            />
            <span>{{ item }}</span>
          </span>
        </div>

        <!-- Bottom-Right Zoom & Reset Extent Controls -->
        <div class="absolute bottom-3 right-3 z-10 flex flex-col gap-1.5">
          <div class="flex flex-col rounded-lg bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden divide-y divide-slate-200 dark:divide-slate-700">
            <button
              type="button"
              aria-label="Perbesar peta"
              class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="zoomBy(1)"
            >
              <UIcon name="i-lucide-plus" class="size-4" />
            </button>
            <button
              type="button"
              aria-label="Perkecil peta"
              class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="zoomBy(-1)"
            >
              <UIcon name="i-lucide-minus" class="size-4" />
            </button>
            <button
              type="button"
              aria-label="Paskan batas wilayah aktif"
              title="Paskan peta ke wilayah aktif"
              class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="fitActiveBounds"
            >
              <UIcon name="i-lucide-locate-fixed" class="size-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Kecamatan Explorer Rail (4 cols) -->
      <div class="lg:col-span-4 flex flex-col h-[340px] sm:h-[380px] lg:h-[420px] xl:h-[460px] bg-white dark:bg-[#0f172a]">
        <div class="p-3.5 border-b border-slate-200 dark:border-slate-800 space-y-2.5 shrink-0">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
              Cakupan Wilayah Administrasi
            </h3>
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 tabular-nums">
              {{ formatIntId(filteredKecamatan.length) }} Kec.
            </span>
          </div>

          <div v-if="isTerritoryLocked" class="px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <UIcon name="i-lucide-lock" class="size-3.5 shrink-0 text-blue-700 dark:text-blue-300" />
            <span class="truncate font-medium">Wilayah kerja terkunci: {{ lockedScopeLabel }}</span>
          </div>

          <div v-else class="relative">
            <label for="kecamatan-explorer-search" class="sr-only">
              Cari kecamatan di Kabupaten Bojonegoro
            </label>
            <UIcon
              name="i-lucide-search"
              class="size-4 text-slate-500 dark:text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
            />
            <input
              id="kecamatan-explorer-search"
              v-model="searchQuery"
              type="search"
              placeholder="Cari kecamatan atau camat..."
              class="w-full min-h-10 sm:min-h-9 pl-9 pr-8 py-1.5 rounded-lg text-base sm:text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-500 dark:placeholder:text-slate-400 focus:outline-2 focus:outline-blue-600 dark:focus:outline-blue-400"
            >
            <button
              v-if="searchQuery"
              type="button"
              aria-label="Hapus pencarian kecamatan"
              class="absolute right-1.5 top-1/2 -translate-y-1/2 min-w-8 min-h-8 flex items-center justify-center rounded text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white cursor-pointer"
              @click="searchQuery = ''"
            >
              <UIcon name="i-lucide-x" class="size-3.5" />
            </button>
          </div>
        </div>

        <!-- Scrollable Territory List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
          <!-- Loading State -->
          <template v-if="loadingKecamatan">
            <div
              v-for="i in 6"
              :key="i"
              class="h-14 rounded-lg bg-slate-100 dark:bg-slate-800/70 animate-pulse"
            />
          </template>

          <!-- Error State -->
          <div
            v-else-if="kecamatanError"
            class="h-full flex flex-col items-center justify-center text-center p-4 gap-2.5"
          >
            <UIcon name="i-lucide-alert-circle" class="size-6 text-red-600 dark:text-red-400" />
            <p class="text-xs font-medium text-slate-700 dark:text-slate-300">
              {{ kecamatanError }}
            </p>
            <button
              type="button"
              class="px-3 py-1.5 min-h-9 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 cursor-pointer"
              @click="emit('retry-kecamatan')"
            >
              Muat Ulang Data Wilayah
            </button>
          </div>

          <!-- Empty State -->
          <div
            v-else-if="filteredKecamatan.length === 0"
            class="h-full flex flex-col items-center justify-center text-center p-4 gap-2"
          >
            <UIcon name="i-lucide-map-pin-off" class="size-6 text-slate-400 dark:text-slate-500" />
            <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
              Tidak ada kecamatan yang cocok dengan "{{ searchQuery }}".
            </p>
            <button
              v-if="searchQuery"
              type="button"
              class="px-3 py-1.5 min-h-9 rounded-lg text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 dark:text-blue-300 dark:bg-blue-950/60 cursor-pointer"
              @click="searchQuery = ''"
            >
              Tampilkan Semua
            </button>
          </div>

          <!-- Real Kecamatan List -->
          <template v-else>
            <button
              v-if="!isTerritoryLocked && selectedKecamatanId"
              type="button"
              class="w-full min-h-10 px-3 py-2 rounded-lg text-left text-xs font-semibold text-blue-700 dark:text-blue-300 bg-blue-50/80 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/60 flex items-center justify-between gap-2 cursor-pointer transition-colors"
              @click="emit('select-kecamatan', null)"
            >
              <span>Kembalikan ke Seluruh Bojonegoro</span>
              <UIcon name="i-lucide-rotate-ccw" class="size-3.5 shrink-0" />
            </button>

            <button
              v-for="kec in filteredKecamatan"
              :key="kec.id"
              type="button"
              :disabled="isTerritoryLocked && Number(selectedKecamatanId) !== Number(kec.id)"
              class="w-full min-h-12 px-3 py-2 rounded-lg text-left border transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 flex items-center justify-between gap-2.5"
              :class="
                Number(selectedKecamatanId) === Number(kec.id)
                  ? 'bg-blue-50/90 dark:bg-blue-950/50 border-blue-300 dark:border-blue-700'
                  : 'bg-white dark:bg-[#0f172a] border-transparent hover:bg-slate-50 dark:hover:bg-slate-800/60 hover:border-slate-200 dark:hover:border-slate-700'
              "
              @click="
                isTerritoryLocked
                  ? updateHighlightBoxAndFit(kec.bbox)
                  : emit(
                      'select-kecamatan',
                      Number(selectedKecamatanId) === Number(kec.id) ? null : kec.id,
                    )
              "
            >
              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <span
                    class="size-2 rounded-full shrink-0"
                    :class="
                      Number(selectedKecamatanId) === Number(kec.id)
                        ? 'bg-blue-600 dark:bg-blue-400'
                        : 'bg-slate-300 dark:bg-slate-600'
                    "
                  />
                  <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                    {{ kec.nama_kecamatan }}
                  </span>
                </div>
                <p
                  v-if="kec.nama_pimpinan"
                  class="text-[11px] text-slate-600 dark:text-slate-400 truncate mt-0.5 ps-3.5"
                >
                  {{ kec.nama_pimpinan }}
                </p>
              </div>

              <div class="text-right shrink-0 tabular-nums">
                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                  {{ formatIntId(kec.jumlah_desa) }} Desa
                </span>
                <span class="block text-[11px] text-slate-600 dark:text-slate-400">
                  {{ formatDecimalId(kec.luas_hektar, 0) }} Ha
                </span>
              </div>
            </button>
          </template>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import type { GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import type { LayerStyleConfig } from "~/types/map-layers";
import {
  buildWmsGetMapPostXml,
  buildWmsSldXml,
  hasWmsCustomStyle,
  shouldUseWmsPostForSld,
} from "~/utils/wms-sld";
import { resolveEffectiveWmsLegendUrl } from "~/utils/mvt-legend";
import {
  buildMaplibreBasemapSpec,
  type MaplibreBasemapKey,
} from "~/utils/maplibre-basemaps";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";
import { useBasemapStore } from "~/stores/basemap";

const props = withDefaults(
  defineProps<{
    open?: boolean;
    layer?: GeoportalLayerItem | null;
  }>(),
  {
    open: false,
    layer: null,
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
  (e: "inspect-metadata", layer: GeoportalLayerItem): void;
}>();

const pendingWebgisDataset = usePendingWebgisDataset();

function handleOpenInWebgis() {
  if (props.layer) {
    pendingWebgisDataset.value = props.layer;
  }
  isOpen.value = false;
}

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit("update:open", val),
});

const isMobileScreen = ref(false);
const updateViewportMode = () => {
  if (typeof window !== "undefined") {
    isMobileScreen.value = window.innerWidth < 768;
  }
};

const basemapStore = useBasemapStore();

onMounted(() => {
  updateViewportMode();
  window.addEventListener("resize", updateViewportMode, { passive: true });
  void basemapStore.ensureLoaded().then(() => {
    if (!basemapStore.isValidCode(activeBasemap.value, "maplibre")) {
      activeBasemap.value = basemapStore.defaultKode || "esri";
    }
  });
});

onBeforeUnmount(() => {
  if (typeof window !== "undefined") {
    window.removeEventListener("resize", updateViewportMode);
  }
  destroyMap();
});

const activeBasemap = ref<MaplibreBasemapKey>("esri");
const showDatasetOverlay = ref(true);
const overlayOpacity = ref(0.85);
const legendError = ref(false);

const mapContainerRef = ref<HTMLElement | null>(null);
const isMapLoading = ref(false);

let mapInstance: any = null;
let maplibreglModule: any = null;
let resizeTimer: ReturnType<typeof setTimeout> | null = null;
let resizeObserver: ResizeObserver | null = null;

let activePostConfig: { url: string; sldXml: string } | null = null;
let protocolRegistered = false;

const layerBbox = computed<[[number, number], [number, number]]>(() => {
  const raw = props.layer?.pycsw?.iso_19139?.bbox || props.layer?.pycsw?.dublin_core?.bbox;
  const west = Number(raw?.west ?? 111.416);
  const south = Number(raw?.south ?? -7.468);
  const east = Number(raw?.east ?? 112.152);
  const north = Number(raw?.north ?? -6.985);
  return [
    [west, south],
    [east, north],
  ];
});

const effectiveLegendUrl = computed(() => resolveEffectiveWmsLegendUrl(props.layer as any));

const wmsTileSpec = computed<{ tiles: string[]; minzoom: number } | null>(() => {
  if (!props.layer?.layer_name) return null;
  const rawUrl = props.layer.url || "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms";
  const baseUrl = rawUrl.split("?")[0] || rawUrl;
  const styleCfg = (props.layer.style_config as LayerStyleConfig | null) || null;
  const sldXml = hasWmsCustomStyle(styleCfg)
    ? buildWmsSldXml(props.layer.layer_name, styleCfg, baseUrl)
    : null;
  const rawMinZoom = Number(styleCfg?.min_zoom);
  const minzoom = Number.isFinite(rawMinZoom) && rawMinZoom > 0 ? Math.min(18, rawMinZoom) : 0;

  if (sldXml && shouldUseWmsPostForSld(baseUrl, sldXml)) {
    activePostConfig = { url: baseUrl, sldXml };
    return {
      tiles: [`dataset-sld-post://tile?bbox={bbox-epsg-3857}&id=${encodeURIComponent(props.layer.id)}`],
      minzoom,
    };
  }

  activePostConfig = null;
  let url =
    `${baseUrl}?service=WMS&version=1.1.1&request=GetMap` +
    `&layers=${encodeURIComponent(props.layer.layer_name)}` +
    `&bbox={bbox-epsg-3857}&width=256&height=256&srs=EPSG:3857&format=image/png&transparent=true`;

  if (sldXml) {
    url += `&SLD_BODY=${encodeURIComponent(sldXml)}`;
  } else {
    url += "&styles=";
  }

  return { tiles: [url], minzoom };
});

function buildMapStyle(basemapKey: MaplibreBasemapKey) {
  const item = basemapStore.getByCode(basemapKey, "maplibre");
  const { sources, layers } = buildMaplibreBasemapSpec(item ?? basemapKey);

  if (wmsTileSpec.value) {
    sources["dataset-wms"] = {
      type: "raster",
      tiles: wmsTileSpec.value.tiles,
      tileSize: 256,
    };
    layers.push({
      id: "dataset-wms-layer",
      type: "raster",
      source: "dataset-wms",
      minzoom: wmsTileSpec.value.minzoom,
      paint: {
        "raster-opacity": showDatasetOverlay.value ? overlayOpacity.value : 0,
      },
    });
  }

  return {
    version: 8 as const,
    sources,
    layers,
  };
}

function destroyMap() {
  if (resizeTimer) {
    clearTimeout(resizeTimer);
    resizeTimer = null;
  }
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
}

async function initMap() {
  if (!import.meta.client || !mapContainerRef.value || !props.layer) return;

  destroyMap();
  isMapLoading.value = true;
  legendError.value = false;

  try {
    if (!maplibreglModule) {
      const [mod, workerMod] = await Promise.all([
        import("maplibre-gl"),
        import("maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url"),
      ]);
      await import("maplibre-gl/dist/maplibre-gl.css");
      maplibreglModule = mod;
      if (workerMod?.default && typeof maplibreglModule.setWorkerUrl === "function") {
        maplibreglModule.setWorkerUrl(workerMod.default);
      }
      if (!protocolRegistered && typeof maplibreglModule.addProtocol === "function") {
        protocolRegistered = true;
        maplibreglModule.addProtocol("dataset-sld-post", async (params: { url: string }, abortController: AbortController) => {
          if (!activePostConfig) return { data: new ArrayBuffer(0) };
          const u = new URL(params.url);
          const rawBbox = (u.searchParams.get("bbox") || "").split(",").map(Number);
          if (rawBbox.length !== 4 || rawBbox.some((n) => !Number.isFinite(n))) {
            return { data: new ArrayBuffer(0) };
          }
          const postBody = buildWmsGetMapPostXml(
            activePostConfig.sldXml,
            rawBbox as [number, number, number, number],
            256,
            256,
            "EPSG:3857"
          );
          const resp = await fetch(activePostConfig.url, {
            method: "POST",
            headers: { "Content-Type": "text/xml; charset=utf-8" },
            body: postBody,
            signal: abortController.signal,
          });
          if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
          return { data: await resp.arrayBuffer() };
        });
      }
    }

    if (!mapContainerRef.value) return;

    mapInstance = new maplibreglModule.Map({
      container: mapContainerRef.value,
      style: buildMapStyle(activeBasemap.value),
      bounds: layerBbox.value,
      fitBoundsOptions: { padding: 28 },
      pitch: 0,
      bearing: 0,
      attributionControl: false,
    });

    mapInstance.addControl(
      new maplibreglModule.AttributionControl({ compact: true }),
      "bottom-left"
    );

    mapInstance.on("load", () => {
      isMapLoading.value = false;
      mapInstance?.resize();
    });

    if (typeof ResizeObserver !== "undefined" && mapContainerRef.value) {
      resizeObserver = new ResizeObserver(() => {
        mapInstance?.resize();
      });
      resizeObserver.observe(mapContainerRef.value);
    }

    resizeTimer = setTimeout(() => {
      mapInstance?.resize();
      isMapLoading.value = false;
    }, 350);
  } catch (err) {
    console.warn("Gagal memuat minimap dataset MapLibre:", err);
    isMapLoading.value = false;
  }
}

function switchBasemap(key: MaplibreBasemapKey) {
  activeBasemap.value = key;
  if (mapInstance) {
    mapInstance.setStyle(buildMapStyle(key));
  }
}

function updateOverlayPaint() {
  if (mapInstance && mapInstance.getLayer("dataset-wms-layer")) {
    mapInstance.setPaintProperty(
      "dataset-wms-layer",
      "raster-opacity",
      showDatasetOverlay.value ? overlayOpacity.value : 0
    );
  }
}

function toggleOverlay() {
  showDatasetOverlay.value = !showDatasetOverlay.value;
  updateOverlayPaint();
}

function handleZoomIn() {
  mapInstance?.zoomIn({ duration: 220 });
}

function handleZoomOut() {
  mapInstance?.zoomOut({ duration: 220 });
}

function handleFitExtent() {
  mapInstance?.fitBounds(layerBbox.value, { padding: 28, duration: 450 });
}

watch([showDatasetOverlay, overlayOpacity], () => {
  updateOverlayPaint();
});

watch(
  [isOpen, () => props.layer?.id],
  async ([opened]) => {
    if (opened && props.layer) {
      showDatasetOverlay.value = true;
      await nextTick();
      setTimeout(() => {
        if (isOpen.value) {
          initMap();
        }
      }, 60);
    } else if (!opened) {
      destroyMap();
    }
  }
);
</script>

<template>
  <!-- Desktop Dialog -->
  <UModal
    v-if="!isMobileScreen"
    v-model:open="isOpen"
    :title="layer?.name ? `Minimap ${layer.name}` : 'Minimap Dataset'"
    description="Pratinjau spasial layer WMS dataset JIGN Bojonegoro menggunakan MapLibre GL JS."
    :ui="{
      overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
      content: 'z-[110] w-[calc(100vw-3rem)] max-w-5xl max-h-[88vh] bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10 p-0 sm:p-0'
    }"
  >
    <template #content>
      <div v-if="layer" class="flex flex-col flex-1 min-h-0 overflow-hidden font-sans bg-[#F7F8F5] dark:bg-[#070b14]">
        <!-- Header (Selaras dengan Metadata Inspector) -->
        <div class="px-6 py-4 border-b border-slate-200/80 dark:border-white/[0.08] flex items-start justify-between gap-3 bg-white dark:bg-[#0c1522] shrink-0">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300">
                {{ layer.category || "Umum" }}
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-white/[0.06] text-slate-700 dark:text-slate-300">
                OGC {{ (layer.protocol || "WMS").toUpperCase() }}
              </span>
              <span class="font-mono text-[11px] text-slate-600 dark:text-slate-400 truncate max-w-md">
                {{ layer.layer_name }}
              </span>
            </div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug truncate">
              {{ layer.name }}
            </h2>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
              Wali Data:
              <strong class="font-semibold text-slate-800 dark:text-slate-200">
                {{ layer.opd_name || layer.attribution || "Pemerintah Kabupaten Bojonegoro" }}
              </strong>
            </p>
          </div>

          <button
            type="button"
            aria-label="Tutup minimap dataset"
            class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white transition-colors cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
            @click="isOpen = false"
          >
            <UIcon name="i-lucide-x" class="w-5 h-5" aria-hidden="true" />
          </button>
        </div>

        <!-- MapLibre Canvas Body (penuh tanpa padding) -->
        <div class="relative w-full h-[58vh] min-h-[430px] max-h-[540px] shrink-0 overflow-hidden bg-[#E9EFEA] dark:bg-[#0c1522]">
          <div class="relative w-full h-full">
            <div ref="mapContainerRef" class="w-full h-full touch-none" />

            <div
              v-if="isMapLoading"
              class="absolute inset-0 z-10 flex items-center justify-center bg-[#F7F8F5]/85 dark:bg-[#070b14]/85 backdrop-blur-xs pointer-events-none"
            >
              <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/10 shadow-sm text-xs font-semibold text-slate-700 dark:text-slate-200">
                <UIcon name="i-lucide-loader-2" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400 animate-spin" aria-hidden="true" />
                <span>Memuat layer WMS MapLibre...</span>
              </div>
            </div>

            <!-- Top-Left: Medium Basemap (Dialog) & Layer Toggle -->
            <div class="absolute top-3 left-3 z-10 flex flex-wrap items-center gap-2">
              <BasemapSwitcher
                :model-value="activeBasemap"
                variant="medium"
                engine="maplibre"
                placement="top-left"
                @update:model-value="switchBasemap($event)"
              />

              <button
                type="button"
                class="min-h-[42px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border shadow-sm text-[11px] font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E]"
                :class="
                  showDatasetOverlay
                    ? 'bg-[#2B5B9E] border-[#2B5B9E] text-white'
                    : 'bg-white dark:bg-[#0c1522] border-slate-200/80 dark:border-white/10 text-slate-700 dark:text-slate-200'
                "
                @click="toggleOverlay"
              >
                <UIcon :name="showDatasetOverlay ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="w-3.5 h-3.5" aria-hidden="true" />
                <span>{{ showDatasetOverlay ? "Layer Aktif" : "Layer Nonaktif" }}</span>
              </button>
            </div>

            <!-- Top-Right: Zoom & Fit Extent Controls -->
            <div class="absolute top-3 right-3 z-10 flex flex-col w-11 bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/10 rounded-xl shadow-sm overflow-hidden">
              <button
                type="button"
                aria-label="Perbesar peta"
                class="h-11 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-[#2B5B9E]"
                @click="handleZoomIn"
              >
                <UIcon name="i-lucide-plus" class="w-4 h-4" aria-hidden="true" />
              </button>
              <div class="h-px bg-slate-200/80 dark:bg-white/10" />
              <button
                type="button"
                aria-label="Perkecil peta"
                class="h-11 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-[#2B5B9E]"
                @click="handleZoomOut"
              >
                <UIcon name="i-lucide-minus" class="w-4 h-4" aria-hidden="true" />
              </button>
              <div class="h-px bg-slate-200/80 dark:bg-white/10" />
              <button
                type="button"
                aria-label="Pusatkan ke cakupan dataset"
                class="h-11 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-[#2B5B9E]"
                @click="handleFitExtent"
              >
                <UIcon name="i-lucide-maximize-2" class="w-4 h-4" aria-hidden="true" />
              </button>
            </div>

            <!-- Bottom-Right: Compact WMS Legend Preview -->
            <div
              v-if="effectiveLegendUrl && !legendError"
              class="absolute bottom-3 right-3 z-10 p-2 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/10 shadow-sm max-w-[160px]"
            >
              <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">
                Legenda
              </p>
              <img
                :src="effectiveLegendUrl"
                :alt="`Legenda ${layer.name}`"
                class="max-h-20 object-contain rounded bg-white p-1"
                @error="legendError = true"
              />
            </div>
          </div>
        </div>

        <!-- Footer (Selaras dengan Metadata Inspector) -->
        <div class="px-6 py-3.5 bg-white dark:bg-[#0c1522] border-t border-slate-200/80 dark:border-white/[0.08] flex flex-wrap items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
              @click="isOpen = false; emit('inspect-metadata', layer)"
            >
              <UIcon name="i-lucide-file-search-2" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
              <span>Inspeksi Metadata</span>
            </button>

            <label class="hidden sm:flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
              <span>Opasitas:</span>
              <input
                v-model.number="overlayOpacity"
                type="range"
                min="0.1"
                max="1"
                step="0.05"
                class="w-24 accent-[#2B5B9E] cursor-pointer"
                aria-label="Opasitas layer dataset"
              />
            </label>
          </div>

          <div class="flex items-center gap-2 ml-auto">
            <button
              type="button"
              class="min-h-[44px] px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.05] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
              @click="isOpen = false"
            >
              Tutup
            </button>

            <NuxtLink
              :to="buildWebgisDatasetRoute(layer)"
              class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] active:bg-[#1C3D6E] text-white text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
              @click="handleOpenInWebgis"
            >
              <UIcon name="i-lucide-map" class="w-4 h-4" aria-hidden="true" />
              <span>Buka di Peta WebGIS</span>
            </NuxtLink>
          </div>
        </div>
      </div>
    </template>
  </UModal>

  <!-- Mobile Bottom Sheet (handle-only + data-vaul-no-drag pada kanvas agar geser peta tidak menggeser sheet) -->
  <UDrawer
    v-else
    v-model:open="isOpen"
    direction="bottom"
    :handle-only="true"
    :title="layer?.name ? `Minimap ${layer.name}` : 'Minimap Dataset'"
    description="Pratinjau spasial layer WMS dataset JIGN Bojonegoro menggunakan MapLibre GL JS."
    :ui="{
      overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
      content: 'z-[110] max-h-[92dvh] bg-white dark:bg-[#0c1522] text-slate-900 dark:text-slate-100 rounded-t-3xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10 p-0',
      handle: 'w-10 h-1 rounded-full bg-slate-300 dark:bg-slate-600 mx-auto mt-2.5 mb-1 shrink-0'
    }"
  >
    <template #content>
      <div v-if="layer" class="flex flex-col flex-1 min-h-0 overflow-hidden font-sans bg-[#F7F8F5] dark:bg-[#070b14]">
        <!-- Header (Selaras dengan Metadata Bottom Sheet) -->
        <div class="px-4 pt-2 pb-3 border-b border-slate-200/80 dark:border-white/[0.08] flex items-start justify-between gap-3 bg-white dark:bg-[#0c1522] shrink-0">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300">
                {{ layer.category || "Umum" }}
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-white/[0.06] text-slate-700 dark:text-slate-300">
                OGC {{ (layer.protocol || "WMS").toUpperCase() }}
              </span>
            </div>
            <h2 class="text-[17px] font-bold text-slate-900 dark:text-white tracking-tight leading-snug line-clamp-2">
              {{ layer.name }}
            </h2>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
              Wali Data:
              <strong class="font-semibold text-slate-800 dark:text-slate-200">
                {{ layer.opd_name || layer.attribution || "Pemerintah Kabupaten Bojonegoro" }}
              </strong>
            </p>
          </div>

          <button
            type="button"
            aria-label="Tutup minimap dataset"
            class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white transition-colors cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
            @click="isOpen = false"
          >
            <UIcon name="i-lucide-x" class="w-5 h-5" aria-hidden="true" />
          </button>
        </div>

        <!-- MapLibre Canvas Container (penuh tanpa padding, terisolasi dari gesture tarik bottom sheet) -->
        <div
          data-vaul-no-drag
          class="w-full shrink-0"
        >
          <div
            data-vaul-no-drag
            class="relative w-full h-[52dvh] min-h-[300px] max-h-[460px] overflow-hidden bg-[#E9EFEA] dark:bg-[#0c1522] touch-none overscroll-contain"
          >
            <div
              ref="mapContainerRef"
              data-vaul-no-drag
              class="w-full h-full touch-none overscroll-contain"
              @pointerdown.stop
              @touchstart.stop
              @touchmove.stop
            />

            <div
              v-if="isMapLoading"
              class="absolute inset-0 z-10 flex items-center justify-center bg-[#F7F8F5]/85 dark:bg-[#070b14]/85 backdrop-blur-xs pointer-events-none"
            >
              <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/10 shadow-sm text-xs font-semibold text-slate-700 dark:text-slate-200">
                <UIcon name="i-lucide-loader-2" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400 animate-spin" aria-hidden="true" />
                <span>Memuat layer...</span>
              </div>
            </div>

            <!-- Mini Basemap Switcher (Bottom Sheet) -->
            <div
              data-vaul-no-drag
              class="absolute top-2.5 left-2.5 right-16 z-10 max-w-full"
            >
              <BasemapSwitcher
                :model-value="activeBasemap"
                variant="mini"
                engine="maplibre"
                @update:model-value="switchBasemap($event)"
              />
            </div>

            <!-- Zoom & Fit Extent Controls (44x44 tap targets) -->
            <div
              data-vaul-no-drag
              class="absolute top-2.5 right-2.5 z-10 flex flex-col w-11 bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/10 rounded-xl shadow-sm overflow-hidden"
            >
              <button
                type="button"
                aria-label="Perbesar peta"
                class="h-11 flex items-center justify-center text-slate-700 dark:text-slate-200 active:bg-slate-100 dark:active:bg-white/10 cursor-pointer"
                @click="handleZoomIn"
              >
                <UIcon name="i-lucide-plus" class="w-4 h-4" aria-hidden="true" />
              </button>
              <div class="h-px bg-slate-200/80 dark:bg-white/10" />
              <button
                type="button"
                aria-label="Perkecil peta"
                class="h-11 flex items-center justify-center text-slate-700 dark:text-slate-200 active:bg-slate-100 dark:active:bg-white/10 cursor-pointer"
                @click="handleZoomOut"
              >
                <UIcon name="i-lucide-minus" class="w-4 h-4" aria-hidden="true" />
              </button>
              <div class="h-px bg-slate-200/80 dark:bg-white/10" />
              <button
                type="button"
                aria-label="Pusatkan ke cakupan dataset"
                class="h-11 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 active:bg-slate-100 dark:active:bg-white/10 cursor-pointer"
                @click="handleFitExtent"
              >
                <UIcon name="i-lucide-maximize-2" class="w-4 h-4" aria-hidden="true" />
              </button>
            </div>

            <!-- Bottom-Right: Toggle Layer -->
            <button
              type="button"
              data-vaul-no-drag
              class="absolute bottom-2.5 right-2.5 z-10 min-h-[40px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border shadow-sm text-[11px] font-semibold transition-colors cursor-pointer"
              :class="
                showDatasetOverlay
                  ? 'bg-[#2B5B9E] border-[#2B5B9E] text-white'
                  : 'bg-white dark:bg-[#0c1522] border-slate-200/80 dark:border-white/10 text-slate-700 dark:text-slate-200'
              "
              @click="toggleOverlay"
            >
              <UIcon :name="showDatasetOverlay ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="w-3.5 h-3.5" aria-hidden="true" />
              <span>{{ showDatasetOverlay ? "Layer Aktif" : "Layer Nonaktif" }}</span>
            </button>
          </div>
        </div>

        <!-- Footer (Selaras dengan Metadata Bottom Sheet) -->
        <div class="px-4 pt-3 pb-[max(0.875rem,env(safe-area-inset-bottom))] bg-white dark:bg-[#0c1522] border-t border-slate-200/80 dark:border-white/[0.08] flex items-center justify-between gap-2 shrink-0">
          <button
            type="button"
            class="min-h-[44px] inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200/80 active:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
            @click="isOpen = false; emit('inspect-metadata', layer)"
          >
            <UIcon name="i-lucide-file-search-2" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
            <span>Metadata</span>
          </button>

          <NuxtLink
            :to="buildWebgisDatasetRoute(layer)"
            class="flex-1 min-w-0 min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] active:bg-[#1C3D6E] text-white text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
            @click="handleOpenInWebgis"
          >
            <UIcon name="i-lucide-map" class="w-4 h-4 shrink-0" aria-hidden="true" />
            <span class="truncate">Buka di Peta WebGIS</span>
          </NuxtLink>
        </div>
      </div>
    </template>
  </UDrawer>
</template>

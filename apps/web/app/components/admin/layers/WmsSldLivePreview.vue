<script setup lang="ts">
import type { LayerStyleConfig } from "~/types/map-layers";
import {
  buildWmsGetMapPostXml,
  buildWmsSldXml,
  hasWmsCustomStyle,
  shouldUseWmsPostForSld,
} from "~/utils/wms-sld";
import {
  buildMaplibreBasemapSpec,
  type MaplibreBasemapKey,
} from "~/utils/maplibre-basemaps";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";
import { useBasemapStore } from "~/stores/basemap";

const props = withDefaults(
  defineProps<{
    wmsUrl?: string | null;
    layerName?: string | null;
    styleConfig?: LayerStyleConfig | null;
    opacity?: number;
  }>(),
  {
    wmsUrl: null,
    layerName: null,
    styleConfig: null,
    opacity: 1,
  }
);

const BOJONEGORO_CENTER: [number, number] = [111.88, -7.16];
const DEFAULT_ZOOM = 12;

const basemapStore = useBasemapStore();
const activeBasemap = ref<MaplibreBasemapKey>("esri");

const mapContainerRef = ref<HTMLElement | null>(null);
const isLoadingTiles = ref(false);
const tileErrorMsg = ref<string | null>(null);
const currentZoom = ref(DEFAULT_ZOOM);
const sldRevision = ref(0);

let mapInstance: any = null;
let maplibreglModule: any = null;
let resizeObserver: ResizeObserver | null = null;
let refreshTimer: ReturnType<typeof setTimeout> | null = null;

// State aktif untuk custom protocol POST MapLibre saat SLD > 6.5 KB
let activePostConfig: { url: string; sldXml: string } | null = null;
let protocolRegistered = false;

const isCustomSld = computed(() => hasWmsCustomStyle(props.styleConfig));

function buildCleanWmsUrl(rawUrl: string): string {
  try {
    const parsed = new URL(rawUrl, typeof window !== "undefined" ? window.location.origin : "http://localhost");
    for (const k of ["service", "request", "version", "srs", "crs", "bbox", "width", "height", "layers", "tiled", "tilesorigin", "styles", "sld", "sld_body"]) {
      parsed.searchParams.delete(k);
      parsed.searchParams.delete(k.toUpperCase());
    }
    return parsed.toString();
  } catch {
    return rawUrl.split("?")[0] || rawUrl;
  }
}

function resolveWmsSourceSpec(): { tiles: string[]; minzoom: number } | null {
  if (!props.wmsUrl || !props.layerName) return null;
  const cleanUrl = buildCleanWmsUrl(props.wmsUrl);
  const sldXml = buildWmsSldXml(props.layerName, props.styleConfig, cleanUrl);
  const usePost = Boolean(sldXml && shouldUseWmsPostForSld(cleanUrl, sldXml));

  const rawMinZoom = Number(props.styleConfig?.min_zoom);
  const minzoom = Number.isFinite(rawMinZoom) && rawMinZoom > 0 ? Math.min(18, rawMinZoom) : 0;

  if (usePost && sldXml) {
    activePostConfig = { url: cleanUrl, sldXml };
    return {
      tiles: [`wms-sld-post://tile?bbox={bbox-epsg-3857}&rev=${sldRevision.value}`],
      minzoom,
    };
  }

  activePostConfig = null;
  const sep = cleanUrl.includes("?") ? "&" : "?";
  let tileUrl =
    `${cleanUrl}${sep}SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap` +
    `&FORMAT=image/png&TRANSPARENT=true&SRS=EPSG:3857` +
    `&LAYERS=${encodeURIComponent(props.layerName)}` +
    `&WIDTH=256&HEIGHT=256&BBOX={bbox-epsg-3857}`;

  if (sldXml) {
    tileUrl += `&SLD_BODY=${encodeURIComponent(sldXml)}`;
  }

  return {
    tiles: [tileUrl],
    minzoom,
  };
}

function buildInitialStyle() {
  const wmsSpec = resolveWmsSourceSpec();
  const item = basemapStore.getByCode(activeBasemap.value, "maplibre");
  const { sources, layers } = buildMaplibreBasemapSpec(item ?? activeBasemap.value, { baseOpacity: 0.9 });

  if (wmsSpec) {
    sources["wms-preview"] = {
      type: "raster",
      tiles: wmsSpec.tiles,
      tileSize: 256,
    };
    layers.push({
      id: "wms-preview-layer",
      type: "raster",
      source: "wms-preview",
      minzoom: wmsSpec.minzoom,
      maxzoom: 22,
      paint: {
        "raster-opacity": props.opacity ?? 1,
      },
    });
  }

  return {
    version: 8 as const,
    sources,
    layers,
  };
}

function refreshWmsLayer() {
  if (!mapInstance || !mapInstance.isStyleLoaded()) return;
  sldRevision.value += 1;
  tileErrorMsg.value = null;

  if (mapInstance.getLayer("wms-preview-layer")) {
    mapInstance.removeLayer("wms-preview-layer");
  }
  if (mapInstance.getSource("wms-preview")) {
    mapInstance.removeSource("wms-preview");
  }

  const wmsSpec = resolveWmsSourceSpec();
  if (!wmsSpec) return;

  isLoadingTiles.value = true;
  mapInstance.addSource("wms-preview", {
    type: "raster",
    tiles: wmsSpec.tiles,
    tileSize: 256,
  });
  mapInstance.addLayer({
    id: "wms-preview-layer",
    type: "raster",
    source: "wms-preview",
    minzoom: wmsSpec.minzoom,
    maxzoom: 22,
    paint: {
      "raster-opacity": props.opacity ?? 1,
    },
  });
}

function zoomBy(delta: number) {
  if (!mapInstance) return;
  const z = mapInstance.getZoom() ?? DEFAULT_ZOOM;
  mapInstance.easeTo({
    zoom: Math.max(8, Math.min(18, z + delta)),
    duration: 200,
  });
}

function switchBasemap(key: MaplibreBasemapKey) {
  activeBasemap.value = key;
  if (!mapInstance) return;
  sldRevision.value += 1;
  mapInstance.setStyle(buildInitialStyle());
}

function resetView() {
  if (!mapInstance) return;
  mapInstance.easeTo({
    center: BOJONEGORO_CENTER,
    zoom: DEFAULT_ZOOM,
    duration: 250,
  });
}

onMounted(async () => {
  if (!import.meta.client || !mapContainerRef.value) return;

  try {
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
      maplibreglModule.addProtocol("wms-sld-post", async (params: { url: string }, abortController: AbortController) => {
        if (!activePostConfig) {
          throw new Error("Konfigurasi POST SLD tidak aktif.");
        }
        const parsed = new URL(params.url.replace("wms-sld-post://", "https://local/"));
        const bboxParts = (parsed.searchParams.get("bbox") || "").split(",").map(Number);
        if (bboxParts.length !== 4 || bboxParts.some((n) => !Number.isFinite(n))) {
          throw new Error("BBOX tile tidak valid.");
        }
        const xmlBody = buildWmsGetMapPostXml(
          activePostConfig.sldXml,
          bboxParts as [number, number, number, number],
          256,
          256,
          "EPSG:3857"
        );
        const res = await fetch(activePostConfig.url, {
          method: "POST",
          headers: { "Content-Type": "application/xml" },
          body: xmlBody,
          signal: abortController.signal,
        });
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        const data = await res.arrayBuffer();
        return { data };
      });
      protocolRegistered = true;
    }

    if (!mapContainerRef.value) return;

    isLoadingTiles.value = true;
    mapInstance = new maplibreglModule.Map({
      container: mapContainerRef.value,
      style: buildInitialStyle(),
      center: BOJONEGORO_CENTER,
      zoom: DEFAULT_ZOOM,
      minZoom: 8,
      maxZoom: 18,
      pitch: 0,
      bearing: 0,
      attributionControl: false,
    });

    mapInstance.addControl(
      new maplibreglModule.AttributionControl({ compact: true }),
      "bottom-left"
    );

    mapInstance.on("load", () => {
      mapInstance?.resize();
      isLoadingTiles.value = false;
    });

    mapInstance.on("idle", () => {
      isLoadingTiles.value = false;
    });

    mapInstance.on("zoom", () => {
      const z = mapInstance?.getZoom();
      if (typeof z === "number") {
        currentZoom.value = Number(z.toFixed(1));
      }
    });

    mapInstance.on("error", (e: any) => {
      if (e?.sourceId === "wms-preview") {
        tileErrorMsg.value = "Sebagian tile WMS gagal dimuat dari GeoServer.";
      }
      isLoadingTiles.value = false;
    });

    if (typeof ResizeObserver !== "undefined") {
      resizeObserver = new ResizeObserver(() => {
        mapInstance?.resize();
      });
      resizeObserver.observe(mapContainerRef.value);
    }
  } catch (err) {
    console.warn("Gagal memuat pratinjau peta MapLibre GL JS:", err);
    tileErrorMsg.value = "Gagal menginisialisasi kanvas MapLibre GL JS.";
    isLoadingTiles.value = false;
  }
});

watch(
  () => [props.wmsUrl, props.layerName, props.styleConfig],
  () => {
    if (refreshTimer) clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => {
      refreshWmsLayer();
    }, 250);
  },
  { deep: true }
);

watch(
  () => props.opacity,
  (nextOpacity) => {
    if (mapInstance && mapInstance.getLayer("wms-preview-layer")) {
      mapInstance.setPaintProperty("wms-preview-layer", "raster-opacity", nextOpacity ?? 1);
    }
  }
);

onBeforeUnmount(() => {
  if (refreshTimer) clearTimeout(refreshTimer);
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});
</script>

<template>
  <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-white/[0.08] flex items-center justify-between gap-2">
      <div>
        <h3 class="text-xs font-semibold text-gray-900 dark:text-white">
          Pratinjau Langsung WMS (MapLibre GL JS)
        </h3>
        <p class="text-[11px] text-gray-600 dark:text-gray-300 tabular-nums">
          {{ isCustomSld ? 'Mode: Custom SLD' : 'Mode: SLD Internal GeoServer' }} · Zoom {{ currentZoom }}
        </p>
      </div>
      <div class="flex items-center gap-1 flex-wrap justify-end">
        <BasemapSwitcher
          :model-value="activeBasemap"
          variant="medium"
          engine="maplibre"
          placement="top-right"
          class="mr-1"
          @update:model-value="switchBasemap($event)"
        />
        <button
          type="button"
          aria-label="Perbesar peta pratinjau"
          class="size-9 inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
          @click="zoomBy(1)"
        >
          <UIcon name="i-lucide-plus" class="size-4" aria-hidden="true" />
        </button>
        <button
          type="button"
          aria-label="Perkecil peta pratinjau"
          class="size-9 inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
          @click="zoomBy(-1)"
        >
          <UIcon name="i-lucide-minus" class="size-4" aria-hidden="true" />
        </button>
        <button
          type="button"
          aria-label="Reset tampilan ke Bojonegoro"
          class="size-9 inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
          @click="resetView"
        >
          <UIcon name="i-lucide-locate-fixed" class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div class="relative h-64 sm:h-72 w-full bg-slate-100 dark:bg-[#070b14]">
      <div ref="mapContainerRef" class="w-full h-full" />

      <div
        v-if="isLoadingTiles"
        class="absolute top-2.5 left-2.5 z-10 px-2.5 py-1 rounded-md bg-white/95 dark:bg-[#0c1424]/95 border border-gray-200 dark:border-white/10 text-[11px] font-medium text-gray-700 dark:text-gray-200 flex items-center gap-1.5"
      >
        <UIcon name="i-lucide-loader-2" class="size-3.5 animate-spin text-blue-600" aria-hidden="true" />
        <span>Memuat tile WMS...</span>
      </div>

      <div
        v-if="!wmsUrl || !layerName"
        class="absolute inset-0 z-10 flex items-center justify-center p-4 text-center bg-gray-50/90 dark:bg-[#070b14]/90"
      >
        <p class="text-xs text-gray-600 dark:text-gray-300">
          URL WMS dan nama layer teknis wajib diisi pada tab Edit Layer untuk memuat pratinjau.
        </p>
      </div>
    </div>

    <div
      v-if="tileErrorMsg"
      class="px-3.5 py-2 border-t border-amber-200 dark:border-amber-800/40 bg-amber-50 dark:bg-amber-950/30 text-[11px] text-amber-800 dark:text-amber-200 flex items-center justify-between gap-2"
    >
      <span>{{ tileErrorMsg }}</span>
      <button
        type="button"
        class="font-semibold underline cursor-pointer shrink-0"
        @click="refreshWmsLayer"
      >
        Muat ulang
      </button>
    </div>
  </div>
</template>

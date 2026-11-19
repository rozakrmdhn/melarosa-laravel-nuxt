<template>
  <button
    type="button"
    class="group/thumb relative w-full h-36 sm:h-40 rounded-xl overflow-hidden border border-slate-200/80 dark:border-white/[0.08] bg-slate-100 dark:bg-[#08101c] block text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E]"
    :aria-label="`Pratinjau peta ${layer.name}`"
    @click="emit('preview', layer)"
  >
    <!-- Lapisan 1: Basemap Static JPG (Esri World Topo sesuai BBOX layer) -->
    <img
      v-if="!basemapError"
      :src="basemapJpgUrl"
      alt=""
      aria-hidden="true"
      loading="lazy"
      decoding="async"
      class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover/thumb:scale-[1.03]"
      @load="basemapLoaded = true"
      @error="basemapError = true"
    />

    <!-- Lapisan 2: Overlay WMS GetMap PNG (mengikuti Custom SLD bila aktif) -->
    <img
      v-if="effectiveOverlayUrl && !overlayError"
      :src="effectiveOverlayUrl"
      :alt="`Pratinjau peta layer ${layer.name}`"
      loading="lazy"
      decoding="async"
      class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover/thumb:scale-[1.03]"
      :style="{ opacity: clampOpacity(layer.opacity) }"
      @load="overlayLoaded = true"
      @error="handleOverlayError"
    />

    <!-- Fallback jika WMS/basemap gagal dimuat atau protokol non-WMS (mis. MVT) -->
    <div
      v-if="(!effectiveOverlayUrl || overlayError) && basemapError"
      class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-gradient-to-br from-slate-100 to-slate-200/70 dark:from-[#0c1626] dark:to-[#070b14] text-slate-500 dark:text-slate-400 px-4 text-center"
    >
      <UIcon name="i-lucide-map" class="w-6 h-6 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
      <span class="text-[11px] font-medium line-clamp-1">
        {{ layer.layer_name || layer.name }}
      </span>
    </div>

    <!-- Shimmer sementara menunggu overlay WMS -->
    <div
      v-if="effectiveOverlayUrl && !overlayLoaded && !overlayError"
      class="absolute inset-0 bg-slate-200/35 dark:bg-slate-900/35 animate-pulse pointer-events-none"
    />

    <!-- Gradient halus bagian bawah untuk keterbacaan badge -->
    <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-slate-950/55 via-slate-950/15 to-transparent pointer-events-none" />

    <!-- Badge kiri bawah: jenis style / protokol -->
    <div class="absolute bottom-2 left-2.5 flex items-center gap-1.5 pointer-events-none">
      <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wider bg-slate-950/75 text-white backdrop-blur-xs">
        {{ (layer.protocol || "WMS").toUpperCase() }}
      </span>
      <span
        v-if="hasCustomSld"
        class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-600/90 text-white backdrop-blur-xs"
      >
        Custom Style
      </span>
    </div>

    <!-- Pill aksi kanan bawah saat hover / fokus -->
    <div class="absolute bottom-2 right-2.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/95 dark:bg-[#0c1522]/95 text-slate-900 dark:text-white text-[11px] font-semibold shadow-xs border border-slate-200/80 dark:border-white/10 opacity-90 group-hover/thumb:opacity-100 transition-opacity">
      <UIcon name="i-lucide-maximize-2" class="w-3 h-3 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
      <span>Pratinjau</span>
    </div>
  </button>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import type { GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import type { LayerStyleConfig } from "~/types/map-layers";
import {
  buildWmsGetMapPostXml,
  buildWmsSldXml,
  hasWmsCustomStyle,
  shouldUseWmsPostForSld,
} from "~/utils/wms-sld";

const THUMB_WIDTH = 480;
const THUMB_HEIGHT = 220;

const props = defineProps<{
  layer: GeoportalLayerItem;
}>();

const emit = defineEmits<{
  (e: "preview", layer: GeoportalLayerItem): void;
}>();

const basemapLoaded = ref(false);
const basemapError = ref(false);
const overlayLoaded = ref(false);
const overlayError = ref(false);
const fallbackToDefaultStyle = ref(false);

function clampOpacity(val?: number | null): number {
  const num = Number(val);
  if (!Number.isFinite(num)) return 0.9;
  return Math.max(0.25, Math.min(1, num));
}

function lonToMercatorX(lon: number): number {
  return (lon * 20037508.34) / 180;
}

function latToMercatorY(lat: number): number {
  const clamped = Math.max(-85, Math.min(85, lat));
  const y = Math.log(Math.tan(((90 + clamped) * Math.PI) / 360)) / (Math.PI / 180);
  return (y * 20037508.34) / 180;
}

// Hitung BBOX Web Mercator (EPSG:3857) yang proporsinya pas 480x220 agar basemap JPG & WMS PNG presisi sejajar
const alignedMercatorBbox = computed<[number, number, number, number]>(() => {
  const raw =
    props.layer.pycsw?.iso_19139?.bbox ||
    props.layer.pycsw?.dublin_core?.bbox ||
    (props.layer.csw_metadata as any)?.bbox;

  const west = Number(raw?.west ?? 111.416);
  const south = Number(raw?.south ?? -7.468);
  const east = Number(raw?.east ?? 112.152);
  const north = Number(raw?.north ?? -6.985);

  const safeWest = Number.isFinite(west) ? west : 111.416;
  const safeSouth = Number.isFinite(south) ? south : -7.468;
  const safeEast = Number.isFinite(east) && east > safeWest ? east : 112.152;
  const safeNorth = Number.isFinite(north) && north > safeSouth ? north : -6.985;

  let minX = lonToMercatorX(safeWest);
  let maxX = lonToMercatorX(safeEast);
  let minY = latToMercatorY(safeSouth);
  let maxY = latToMercatorY(safeNorth);

  const rawDx = Math.max(1000, maxX - minX);
  const rawDy = Math.max(1000, maxY - minY);
  const cx = (minX + maxX) / 2;
  const cy = (minY + maxY) / 2;

  const pad = 1.12;
  let dx = rawDx * pad;
  let dy = rawDy * pad;
  const targetRatio = THUMB_WIDTH / THUMB_HEIGHT;

  if (dx / dy > targetRatio) {
    dy = dx / targetRatio;
  } else {
    dx = dy * targetRatio;
  }

  minX = cx - dx / 2;
  maxX = cx + dx / 2;
  minY = cy - dy / 2;
  maxY = cy + dy / 2;

  return [
    Number(minX.toFixed(2)),
    Number(minY.toFixed(2)),
    Number(maxX.toFixed(2)),
    Number(maxY.toFixed(2)),
  ];
});

const basemapJpgUrl = computed(() => {
  const bboxStr = alignedMercatorBbox.value.join(",");
  return `https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/export?bbox=${bboxStr}&bboxSR=3857&imageSR=3857&size=${THUMB_WIDTH},${THUMB_HEIGHT}&format=jpg&f=image`;
});

const isWmsProtocol = computed(() =>
  String(props.layer.protocol || "wms").toLowerCase().includes("wms")
);

const styleCfg = computed(() => (props.layer.style_config as LayerStyleConfig | null) || null);
const hasCustomSld = computed(() => isWmsProtocol.value && hasWmsCustomStyle(styleCfg.value));
const postBlobUrl = ref<string | null>(null);
let postAbortController: AbortController | null = null;

function revokePostBlob() {
  if (postAbortController) {
    postAbortController.abort();
    postAbortController = null;
  }
  if (postBlobUrl.value) {
    URL.revokeObjectURL(postBlobUrl.value);
    postBlobUrl.value = null;
  }
}

onBeforeUnmount(() => {
  revokePostBlob();
});

async function fetchPostThumbnail(cleanUrl: string, sldXml: string) {
  revokePostBlob();
  if (!import.meta.client) return;
  postAbortController = new AbortController();
  try {
    const bodyXml = buildWmsGetMapPostXml(
      sldXml,
      alignedMercatorBbox.value,
      THUMB_WIDTH,
      THUMB_HEIGHT,
      "EPSG:3857"
    );
    const resp = await fetch(cleanUrl, {
      method: "POST",
      headers: { "Content-Type": "text/xml; charset=utf-8" },
      body: bodyXml,
      signal: postAbortController.signal,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const ct = resp.headers.get("content-type") || "";
    if (!ct.includes("image")) throw new Error("Non-image WMS response");
    const blob = await resp.blob();
    postBlobUrl.value = URL.createObjectURL(blob);
  } catch (err: any) {
    if (err?.name !== "AbortError") {
      fallbackToDefaultStyle.value = true;
    }
  }
}

function buildWmsThumbnailUrl(withSld: boolean): string | null {
  if (!isWmsProtocol.value || !props.layer.layer_name) return null;
  const rawBase = props.layer.url || "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms";
  const cleanUrl = rawBase.split("?")[0] || rawBase;
  const bboxStr = alignedMercatorBbox.value.join(",");

  let url =
    `${cleanUrl}?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap` +
    `&FORMAT=image/png&TRANSPARENT=true&SRS=EPSG:3857` +
    `&LAYERS=${encodeURIComponent(props.layer.layer_name)}` +
    `&WIDTH=${THUMB_WIDTH}&HEIGHT=${THUMB_HEIGHT}&BBOX=${bboxStr}`;

  if (withSld && hasCustomSld.value) {
    const sldXml = buildWmsSldXml(props.layer.layer_name, styleCfg.value, cleanUrl);
    if (sldXml && !shouldUseWmsPostForSld(cleanUrl, sldXml)) {
      url += `&SLD_BODY=${encodeURIComponent(sldXml)}`;
    } else if (sldXml && postBlobUrl.value) {
      return postBlobUrl.value;
    } else {
      url += "&STYLES=";
    }
  } else {
    url += "&STYLES=";
  }

  return url;
}

const effectiveOverlayUrl = computed(() => {
  return buildWmsThumbnailUrl(!fallbackToDefaultStyle.value);
});

watch(
  [() => props.layer.id, () => props.layer.style_config],
  () => {
    basemapLoaded.value = false;
    basemapError.value = false;
    overlayLoaded.value = false;
    overlayError.value = false;
    fallbackToDefaultStyle.value = false;
    revokePostBlob();

    if (isWmsProtocol.value && props.layer.layer_name && hasCustomSld.value) {
      const rawBase = props.layer.url || "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms";
      const cleanUrl = rawBase.split("?")[0] || rawBase;
      const sldXml = buildWmsSldXml(props.layer.layer_name, styleCfg.value, cleanUrl);
      if (sldXml && shouldUseWmsPostForSld(cleanUrl, sldXml)) {
        fetchPostThumbnail(cleanUrl, sldXml);
      }
    }
  },
  { immediate: true, deep: true }
);

function handleOverlayError() {
  if (!fallbackToDefaultStyle.value && hasCustomSld.value) {
    fallbackToDefaultStyle.value = true;
    return;
  }
  overlayError.value = true;
}
</script>

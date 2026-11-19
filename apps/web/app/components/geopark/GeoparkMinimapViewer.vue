<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import type { GeoparkSite } from "~/composables/useGeoparkSites";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";
import { useBasemapStore } from "~/stores/basemap";
import { buildMaplibreBasemapSpec, type MaplibreBasemapKey } from "~/utils/maplibre-basemaps";

const props = withDefaults(
  defineProps<{
    open?: boolean;
    site?: GeoparkSite | null;
  }>(),
  {
    open: false,
    site: null,
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
}>();

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

type BasemapOption = MaplibreBasemapKey;
const activeBasemap = ref<BasemapOption>("esri");

const mapContainerRef = ref<HTMLElement | null>(null);
const isMapLoading = ref(false);
const isCoordCopied = ref(false);

let mapInstance: any = null;
let markerInstance: any = null;
let maplibreglModule: any = null;
let resizeTimer: ReturnType<typeof setTimeout> | null = null;

const targetCoordinate = computed<[number, number]>(() => {
  if (props.site?.hasCoordinate && props.site.coordinate?.length === 2) {
    return [props.site.coordinate[0], props.site.coordinate[1]];
  }
  return [111.8817, -7.1502];
});

const targetZoom = computed(() => {
  if (props.site?.hasCoordinate) {
    return props.site.zoom || 14.5;
  }
  return 10.5;
});

const formattedCoordinate = computed(() => {
  const [lng, lat] = targetCoordinate.value;
  return `${lat.toFixed(5)}°, ${lng.toFixed(5)}°`;
});

function buildRasterStyle(basemapKey: BasemapOption) {
  const item = basemapStore.getByCode(basemapKey, "maplibre");
  const { sources, layers } = buildMaplibreBasemapSpec(item ?? basemapKey);
  return {
    version: 8 as const,
    sources,
    layers,
  };
}

function createMarkerElement(site: GeoparkSite | null) {
  const wrapper = document.createElement("div");
  wrapper.className = "relative flex items-center justify-center";

  const pulse = document.createElement("span");
  pulse.className = "absolute size-9 rounded-full bg-[#2B5B9E]/30 animate-ping";
  wrapper.appendChild(pulse);

  const pin = document.createElement("div");
  pin.className =
    "relative size-8 rounded-full bg-[#2B5B9E] text-white border-2 border-white shadow-lg flex items-center justify-center";
  pin.innerHTML =
    '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>';
  wrapper.appendChild(pin);

  if (site?.name) {
    wrapper.title = site.name;
  }

  return wrapper;
}

function destroyMap() {
  if (resizeTimer) {
    clearTimeout(resizeTimer);
    resizeTimer = null;
  }
  if (markerInstance) {
    markerInstance.remove();
    markerInstance = null;
  }
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
}

async function initMap() {
  if (!import.meta.client || !mapContainerRef.value || !props.site) return;

  destroyMap();
  isMapLoading.value = true;

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
    }

    if (!mapContainerRef.value) return;

    mapInstance = new maplibreglModule.Map({
      container: mapContainerRef.value,
      style: buildRasterStyle(activeBasemap.value),
      center: targetCoordinate.value,
      zoom: targetZoom.value,
      pitch: 0,
      bearing: 0,
      attributionControl: false,
    });

    mapInstance.addControl(
      new maplibreglModule.AttributionControl({ compact: true }),
      "bottom-left"
    );

    if (props.site.hasCoordinate) {
      markerInstance = new maplibreglModule.Marker({
        element: createMarkerElement(props.site),
        anchor: "center",
      })
        .setLngLat(targetCoordinate.value)
        .addTo(mapInstance);
    }

    mapInstance.on("load", () => {
      isMapLoading.value = false;
      mapInstance?.resize();
    });

    // Ensure proper dimensions after modal/drawer open transition settles
    resizeTimer = setTimeout(() => {
      mapInstance?.resize();
      isMapLoading.value = false;
    }, 320);
  } catch (err) {
    console.warn("Gagal memuat minimap MapLibre:", err);
    isMapLoading.value = false;
  }
}

function switchBasemap(key: BasemapOption) {
  activeBasemap.value = key;
  if (mapInstance) {
    mapInstance.setStyle(buildRasterStyle(key));
  }
}

function handleZoomIn() {
  mapInstance?.zoomIn({ duration: 220 });
}

function handleZoomOut() {
  mapInstance?.zoomOut({ duration: 220 });
}

function handleRecenter() {
  mapInstance?.flyTo({
    center: targetCoordinate.value,
    zoom: targetZoom.value,
    duration: 500,
  });
}

function copyCoords() {
  if (!import.meta.client) return;
  const [lng, lat] = targetCoordinate.value;
  navigator.clipboard.writeText(`${lat.toFixed(6)}, ${lng.toFixed(6)}`);
  isCoordCopied.value = true;
  setTimeout(() => {
    isCoordCopied.value = false;
  }, 2000);
}

watch(
  [isOpen, () => props.site?.id],
  async ([opened]) => {
    if (opened && props.site) {
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

const webgisLink = computed(() => {
  if (!props.site) return "/maps";
  if (props.site.hasCoordinate) {
    return `/maps?geosite=${encodeURIComponent(props.site.slug)}`;
  }
  return `/maps?search=${encodeURIComponent(props.site.name)}`;
});
</script>

<template>
  <!-- Desktop Dialog -->
  <UModal
    v-if="!isMobileScreen"
    v-model:open="isOpen"
    :title="site?.name ? `Minimap ${site.name}` : 'Minimap Situs Geopark'"
    description="Pratinjau lokasi spasial situs Geopark Bojonegoro menggunakan MapLibre GL JS."
    :ui="{
      overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
      content: 'z-[110] w-[calc(100vw-3rem)] max-w-3xl bg-white dark:bg-[#0c1522] text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10 p-0 sm:p-0'
    }"
  >
    <template #content>
      <div v-if="site" class="flex flex-col overflow-hidden font-sans">
        <!-- Header -->
        <div class="px-5 py-4 border-b border-slate-200/80 dark:border-white/[0.08] flex items-start justify-between gap-3 bg-white dark:bg-[#0c1522]">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300">
                {{ site.code }}
              </span>
              <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                {{ site.category }}
              </span>
            </div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight truncate">
              {{ site.name }}
            </h2>
          </div>

          <button
            type="button"
            aria-label="Tutup minimap"
            class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-white/[0.06] text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0"
            @click="isOpen = false"
          >
            <UIcon name="i-lucide-x" class="w-5 h-5" />
          </button>
        </div>

        <!-- MapLibre Canvas -->
        <div class="relative w-full h-[400px] bg-[#E9EFEA] dark:bg-[#070b14]">
          <div ref="mapContainerRef" class="w-full h-full" />

          <!-- Loading Overlay -->
          <div
            v-if="isMapLoading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-[#F7F8F5]/80 dark:bg-[#070b14]/80 backdrop-blur-xs pointer-events-none"
          >
            <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full bg-white dark:bg-[#0c1522] shadow-sm text-xs font-semibold text-slate-700 dark:text-slate-200">
              <UIcon name="i-lucide-loader-2" class="w-4 h-4 text-[#2B5B9E] animate-spin" />
              <span>Memuat peta MapLibre...</span>
            </div>
          </div>

          <!-- Medium Basemap Switcher (Dialog) -->
          <div class="absolute top-3 left-3 z-10">
            <BasemapSwitcher
              :model-value="activeBasemap"
              variant="medium"
              engine="maplibre"
              placement="top-left"
              @update:model-value="switchBasemap($event)"
            />
          </div>

          <!-- Zoom & Recenter Controls -->
          <div class="absolute top-3 right-3 z-10 flex flex-col w-10 bg-white/95 dark:bg-[#0c1522]/95 border border-slate-200/80 dark:border-white/10 rounded-xl shadow-sm overflow-hidden">
            <button
              type="button"
              aria-label="Perbesar peta"
              class="h-10 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer"
              @click="handleZoomIn"
            >
              <UIcon name="i-lucide-plus" class="w-4 h-4" />
            </button>
            <div class="h-px bg-slate-200/80 dark:bg-white/10" />
            <button
              type="button"
              aria-label="Perkecil peta"
              class="h-10 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer"
              @click="handleZoomOut"
            >
              <UIcon name="i-lucide-minus" class="w-4 h-4" />
            </button>
            <div class="h-px bg-slate-200/80 dark:bg-white/10" />
            <button
              type="button"
              aria-label="Pusatkan ke lokasi situs"
              class="h-10 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer"
              @click="handleRecenter"
            >
              <UIcon name="i-lucide-locate-fixed" class="w-4 h-4" />
            </button>
          </div>

          <!-- Coordinate Pill -->
          <div class="absolute bottom-3 right-3 z-10 px-2.5 py-1 rounded-lg bg-white/95 dark:bg-[#0c1522]/95 border border-slate-200/80 dark:border-white/10 text-[11px] font-mono font-medium text-slate-700 dark:text-slate-200 shadow-xs tabular-nums">
            {{ formattedCoordinate }}
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3.5 bg-white dark:bg-[#0c1522] border-t border-slate-200/80 dark:border-white/[0.08] flex flex-wrap items-center justify-between gap-3">
          <button
            v-if="site.hasCoordinate"
            type="button"
            class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-white/[0.06] hover:bg-slate-200/80 dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
            @click="copyCoords"
          >
            <UIcon :name="isCoordCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="w-4 h-4" />
            <span>{{ isCoordCopied ? "Koordinat Tersalin" : "Salin Koordinat" }}</span>
          </button>

          <NuxtLink
            :to="webgisLink"
            class="ml-auto min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-white text-xs font-semibold transition-colors"
            @click="isOpen = false"
          >
            <UIcon name="i-lucide-map" class="w-4 h-4" />
            <span>Buka di Peta WebGIS</span>
          </NuxtLink>
        </div>
      </div>
    </template>
  </UModal>

  <!-- Mobile Bottom Sheet -->
  <UDrawer
    v-else
    v-model:open="isOpen"
    direction="bottom"
    :title="site?.name ? `Minimap ${site.name}` : 'Minimap Situs Geopark'"
    description="Pratinjau lokasi spasial situs Geopark Bojonegoro menggunakan MapLibre GL JS."
    :ui="{
      overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
      content: 'z-[110] max-h-[90dvh] bg-white dark:bg-[#0c1522] text-slate-900 dark:text-slate-100 rounded-t-3xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10',
      handle: 'bg-slate-300 dark:bg-slate-700'
    }"
  >
    <template #content>
      <div v-if="site" class="flex flex-col overflow-hidden font-sans">
        <!-- Header -->
        <div class="px-4 pt-2 pb-3 border-b border-slate-200/80 dark:border-white/[0.08] flex items-start justify-between gap-3 bg-white dark:bg-[#0c1522]">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5 mb-1">
              <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300">
                {{ site.code }}
              </span>
              <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate">
                {{ site.category }}
              </span>
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white tracking-tight truncate">
              {{ site.name }}
            </h2>
          </div>

          <button
            type="button"
            aria-label="Tutup minimap"
            class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-white/[0.06] text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0"
            @click="isOpen = false"
          >
            <UIcon name="i-lucide-x" class="w-5 h-5" />
          </button>
        </div>

        <!-- MapLibre Canvas -->
        <div class="relative w-full h-[52dvh] min-h-[280px] max-h-[440px] bg-[#E9EFEA] dark:bg-[#070b14]">
          <div ref="mapContainerRef" class="w-full h-full" />

          <div
            v-if="isMapLoading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-[#F7F8F5]/80 dark:bg-[#070b14]/80 backdrop-blur-xs pointer-events-none"
          >
            <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full bg-white dark:bg-[#0c1522] shadow-sm text-xs font-semibold text-slate-700 dark:text-slate-200">
              <UIcon name="i-lucide-loader-2" class="w-4 h-4 text-[#2B5B9E] animate-spin" />
              <span>Memuat peta...</span>
            </div>
          </div>

          <!-- Mini Basemap Switcher (Bottom Sheet) -->
          <div
            data-vaul-no-drag
            class="absolute top-3 left-3 right-16 z-10 max-w-full"
          >
            <BasemapSwitcher
              :model-value="activeBasemap"
              variant="mini"
              engine="maplibre"
              @update:model-value="switchBasemap($event)"
            />
          </div>

          <!-- Zoom & Recenter Controls -->
          <div class="absolute top-3 right-3 z-10 flex flex-col w-11 bg-white/95 dark:bg-[#0c1522]/95 border border-slate-200/80 dark:border-white/10 rounded-xl shadow-sm overflow-hidden">
            <button
              type="button"
              aria-label="Perbesar peta"
              class="h-11 flex items-center justify-center text-slate-700 dark:text-slate-200 active:bg-slate-100 dark:active:bg-white/10 cursor-pointer"
              @click="handleZoomIn"
            >
              <UIcon name="i-lucide-plus" class="w-4 h-4" />
            </button>
            <div class="h-px bg-slate-200/80 dark:bg-white/10" />
            <button
              type="button"
              aria-label="Perkecil peta"
              class="h-11 flex items-center justify-center text-slate-700 dark:text-slate-200 active:bg-slate-100 dark:active:bg-white/10 cursor-pointer"
              @click="handleZoomOut"
            >
              <UIcon name="i-lucide-minus" class="w-4 h-4" />
            </button>
            <div class="h-px bg-slate-200/80 dark:bg-white/10" />
            <button
              type="button"
              aria-label="Pusatkan ke lokasi situs"
              class="h-11 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 active:bg-slate-100 dark:active:bg-white/10 cursor-pointer"
              @click="handleRecenter"
            >
              <UIcon name="i-lucide-locate-fixed" class="w-4 h-4" />
            </button>
          </div>

          <!-- Coordinate Pill -->
          <div class="absolute bottom-3 right-3 z-10 px-2.5 py-1 rounded-lg bg-white/95 dark:bg-[#0c1522]/95 border border-slate-200/80 dark:border-white/10 text-[11px] font-mono font-medium text-slate-700 dark:text-slate-200 shadow-xs tabular-nums">
            {{ formattedCoordinate }}
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 pt-3 pb-[max(0.875rem,env(safe-area-inset-bottom))] bg-white dark:bg-[#0c1522] border-t border-slate-200/80 dark:border-white/[0.08] flex items-center gap-2.5">
          <button
            v-if="site.hasCoordinate"
            type="button"
            class="min-h-[44px] inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-white/[0.06] text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer shrink-0"
            @click="copyCoords"
          >
            <UIcon :name="isCoordCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="w-4 h-4" />
            <span>{{ isCoordCopied ? "Tersalin" : "Salin" }}</span>
          </button>

          <NuxtLink
            :to="webgisLink"
            class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-white text-xs font-semibold transition-colors"
            @click="isOpen = false"
          >
            <UIcon name="i-lucide-map" class="w-4 h-4" />
            <span>Buka di Peta WebGIS</span>
          </NuxtLink>
        </div>
      </div>
    </template>
  </UDrawer>
</template>

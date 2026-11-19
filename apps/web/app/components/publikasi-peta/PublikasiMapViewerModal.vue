<script setup lang="ts">
import * as maplibregl from 'maplibre-gl';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import 'maplibre-gl/dist/maplibre-gl.css';
import BasemapSwitcher from '~/components/common/BasemapSwitcher.vue';
import { resolvePublikasiGeorefImageUrl } from '~/composables/usePublikasiPetaApi';
import { useBasemapStore } from '~/stores/basemap';
import type { PublikasiGeorefCorners, PublikasiLngLat, PublikasiPetaDigitalItem } from '~/types/publikasi-peta';
import { computeBboxFromCorners, formatDms, isPointInQuad, lngLatToUtm } from '~/utils/georef-math';
import { buildMaplibreBasemapSpec } from '~/utils/maplibre-basemaps';

const props = defineProps<{
  open: boolean;
  item: PublikasiPetaDigitalItem | null;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const basemapStore = useBasemapStore();

const isOpenModel = computed({
  get: () => props.open,
  set: (v: boolean) => emit('update:open', v),
});

const activeBasemap = ref<string>('esri');
const opacity = ref<number>(0.85);
const mapContainerRef = ref<HTMLElement | null>(null);
const showCornerLabels = ref<boolean>(true);

const hoverCoord = ref<{ lng: number; lat: number; inside: boolean } | null>(null);
const selectedCoord = ref<{ lng: number; lat: number; inside: boolean; copied: boolean } | null>(null);

let mapInstance: maplibregl.Map | null = null;
let resizeObserver: ResizeObserver | null = null;
let pinMarker: maplibregl.Marker | null = null;
let cornerMarkers: maplibregl.Marker[] = [];

const CORNER_NAMES = ['Kiri Atas (TL)', 'Kanan Atas (TR)', 'Kanan Bawah (BR)', 'Kiri Bawah (BL)'] as const;

const georefCorners = computed<PublikasiGeorefCorners | null>(() => {
  const coords = props.item?.georef_coordinates;
  if (!Array.isArray(coords) || coords.length !== 4) return null;
  return coords as PublikasiGeorefCorners;
});

const overlayImageUrl = computed(() => {
  if (!props.item?.is_georeferenced || !props.item?.has_georef_image) return null;
  return resolvePublikasiGeorefImageUrl(props.item.id, props.item.georeferenced_at);
});

function renderOverlayLayers() {
  if (!mapInstance || !mapInstance.isStyleLoaded() || !georefCorners.value || !overlayImageUrl.value) return;

  const srcId = 'viewer-georef-image';
  const layerId = 'viewer-georef-image-layer';
  const lineSrcId = 'viewer-georef-outline';
  const lineLayerId = 'viewer-georef-outline-layer';

  if (mapInstance.getLayer(layerId)) mapInstance.removeLayer(layerId);
  if (mapInstance.getSource(srcId)) mapInstance.removeSource(srcId);
  if (mapInstance.getLayer(lineLayerId)) mapInstance.removeLayer(lineLayerId);
  if (mapInstance.getSource(lineSrcId)) mapInstance.removeSource(lineSrcId);

  mapInstance.addSource(srcId, {
    type: 'image',
    url: overlayImageUrl.value,
    coordinates: georefCorners.value,
  });

  mapInstance.addLayer({
    id: layerId,
    type: 'raster',
    source: srcId,
    paint: {
      'raster-opacity': opacity.value,
      'raster-fade-duration': 0,
    },
  });

  mapInstance.addSource(lineSrcId, {
    type: 'geojson',
    data: {
      type: 'Feature',
      geometry: {
        type: 'Polygon',
        coordinates: [[...georefCorners.value, georefCorners.value[0]]],
      },
      properties: {},
    } as any,
  });

  mapInstance.addLayer({
    id: lineLayerId,
    type: 'line',
    source: lineSrcId,
    paint: {
      'line-color': '#2563eb',
      'line-width': 2,
      'line-dasharray': [3, 2],
    },
  });

  refreshCornerMarkers();
}

function refreshCornerMarkers() {
  cornerMarkers.forEach((m) => m.remove());
  cornerMarkers = [];
  if (!mapInstance || !georefCorners.value || !showCornerLabels.value) return;

  const shortNames = ['TL', 'TR', 'BR', 'BL'];
  georefCorners.value.forEach((coord, idx) => {
    const el = document.createElement('div');
    el.className =
      'px-1.5 py-0.5 rounded bg-slate-900/85 text-white text-[10px] font-bold border border-white/80 shadow pointer-events-none select-none';
    el.textContent = shortNames[idx] ?? `C${idx + 1}`;
    const m = new maplibregl.Marker({ element: el }).setLngLat(coord).addTo(mapInstance!);
    cornerMarkers.push(m);
  });
}

function fitToOverlay() {
  if (!mapInstance || !georefCorners.value) return;
  const [minLng, minLat, maxLng, maxLat] = computeBboxFromCorners(georefCorners.value);
  mapInstance.fitBounds(
    [
      [minLng, minLat],
      [maxLng, maxLat],
    ],
    { padding: 48, duration: 450 },
  );
}

function applyBasemapStyle(code: string) {
  if (!mapInstance) return;
  const baseItem = basemapStore.getByCode(code, 'maplibre');
  const { sources, layers } = buildMaplibreBasemapSpec(baseItem ?? code, { baseOpacity: 1 });
  mapInstance.setStyle({ version: 8, sources, layers } as any);
  mapInstance.once('styledata', () => {
    renderOverlayLayers();
  });
}

function initMap() {
  if (!mapContainerRef.value) return;
  destroyMap();

  if (import.meta.client && typeof maplibregl.setWorkerUrl === 'function') {
    maplibregl.setWorkerUrl(maplibreWorkerUrl);
  }

  basemapStore.ensureLoaded();
  opacity.value = props.item?.georef_opacity ?? 0.85;

  const baseItem = basemapStore.getByCode(activeBasemap.value, 'maplibre');
  const { sources, layers } = buildMaplibreBasemapSpec(baseItem ?? activeBasemap.value, { baseOpacity: 1 });

  mapInstance = new maplibregl.Map({
    container: mapContainerRef.value,
    style: { version: 8, sources, layers } as any,
    center: [111.88, -7.16],
    zoom: 12,
    attributionControl: false,
  });

  mapInstance.addControl(new maplibregl.NavigationControl({ showCompass: true }), 'top-left');

  mapInstance.on('load', () => {
    renderOverlayLayers();
    fitToOverlay();
  });

  mapInstance.on('mousemove', (e) => {
    const pt: PublikasiLngLat = [Number(e.lngLat.lng.toFixed(6)), Number(e.lngLat.lat.toFixed(6))];
    const inside = georefCorners.value ? isPointInQuad(pt, georefCorners.value) : false;
    hoverCoord.value = { lng: pt[0], lat: pt[1], inside };
  });

  mapInstance.on('mouseout', () => {
    hoverCoord.value = null;
  });

  mapInstance.on('click', (e) => {
    const lng = Number(e.lngLat.lng.toFixed(7));
    const lat = Number(e.lngLat.lat.toFixed(7));
    const inside = georefCorners.value ? isPointInQuad([lng, lat], georefCorners.value) : false;
    selectedCoord.value = { lng, lat, inside, copied: false };

    if (pinMarker) pinMarker.remove();
    pinMarker = new maplibregl.Marker({ color: '#dc2626' })
      .setLngLat([lng, lat])
      .setPopup(
        new maplibregl.Popup({ offset: 18 }).setHTML(
          `<div style="font-size:11px;line-height:1.45"><strong>Koordinat Lokasi</strong><br/>Lat: ${lat}<br/>Lng: ${lng}<br/><span style="color:#475569">${formatDms(lat, true)}, ${formatDms(lng, false)}</span></div>`,
        ),
      )
      .addTo(mapInstance!)
      .togglePopup();
  });

  resizeObserver = new ResizeObserver(() => {
    mapInstance?.resize();
  });
  resizeObserver.observe(mapContainerRef.value);
}

function destroyMap() {
  cornerMarkers.forEach((m) => m.remove());
  cornerMarkers = [];
  if (pinMarker) {
    pinMarker.remove();
    pinMarker = null;
  }
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
}

function copySelectedCoord() {
  if (!selectedCoord.value || !import.meta.client) return;
  const text = `${selectedCoord.value.lat}, ${selectedCoord.value.lng}`;
  navigator?.clipboard?.writeText(text).catch(() => {});
  selectedCoord.value.copied = true;
}

watch(
  () => [props.open, props.item?.id],
  async ([opened]) => {
    if (opened && props.item?.is_georeferenced) {
      selectedCoord.value = null;
      hoverCoord.value = null;
      await nextTick();
      initMap();
    } else {
      destroyMap();
    }
  },
);

watch(activeBasemap, (code) => {
  applyBasemapStyle(code);
});

watch(opacity, (val) => {
  if (mapInstance?.getLayer('viewer-georef-image-layer')) {
    mapInstance.setPaintProperty('viewer-georef-image-layer', 'raster-opacity', val);
  }
});

watch(showCornerLabels, () => {
  refreshCornerMarkers();
});

onBeforeUnmount(() => {
  destroyMap();
});
</script>

<template>
  <UModal
    v-model:open="isOpenModel"
    :title="item ? `Baca Koordinat Peta Digital: ${item.judul}` : 'Pembaca Koordinat Peta Digital'"
    :description="item?.wilayah_label || 'Overlay MapLibre GL JS (EPSG:4326 / WGS84)'"
    :ui="{ content: 'sm:max-w-6xl' }"
  >
    <template #body>
      <div v-if="item" class="space-y-3">
        <!-- Control Bar -->
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-slate-800 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <UBadge color="success" variant="subtle" size="md" class="font-semibold">
              MapLibre GL JS ({{ item.georef_method || 'affine' }})
            </UBadge>
            <span v-if="item.georef_rms_meters !== null && item.georef_rms_meters !== undefined" class="text-slate-600 dark:text-slate-300 tabular-nums">
              Akurasi RMS: {{ item.georef_rms_meters }} m
            </span>
            <span class="text-slate-500 dark:text-slate-400">
              Klik titik mana saja pada peta untuk membaca koordinatnya.
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 cursor-pointer">
              <input v-model="showCornerLabels" type="checkbox" class="rounded border-slate-300" />
              <span>Label Sudut</span>
            </label>
            <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
              <span>Opasitas: {{ Math.round(opacity * 100) }}%</span>
              <input v-model.number="opacity" type="range" min="0.1" max="1" step="0.05" class="w-24 accent-[#2B5B9E]" />
            </label>
            <UButton
              icon="i-lucide-focus"
              label="Fokus Peta"
              size="xs"
              color="neutral"
              variant="outline"
              class="cursor-pointer"
              @click="fitToOverlay"
            />
          </div>
        </div>

        <!-- MapLibre GL JS Canvas -->
        <div class="relative h-[58vh] min-h-[380px] rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-900">
          <div ref="mapContainerRef" class="w-full h-full" />

          <BasemapSwitcher
            v-model="activeBasemap"
            engine="maplibre"
            variant="compact"
            placement="top-right"
          />

          <!-- Coordinate Readout HUD -->
          <div class="absolute bottom-2.5 left-2.5 right-2.5 flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-lg bg-slate-900/85 text-white text-xs backdrop-blur-xs">
            <div v-if="hoverCoord" class="flex flex-wrap items-center gap-2 font-mono tabular-nums">
              <span>Lat: {{ hoverCoord.lat.toFixed(6) }}</span>
              <span>Lng: {{ hoverCoord.lng.toFixed(6) }}</span>
              <span class="text-sky-300 hidden md:inline">
                UTM {{ lngLatToUtm(hoverCoord.lng, hoverCoord.lat).zone }}{{ lngLatToUtm(hoverCoord.lng, hoverCoord.lat).hemisphere }}:
                {{ Math.round(lngLatToUtm(hoverCoord.lng, hoverCoord.lat).easting) }}E,
                {{ Math.round(lngLatToUtm(hoverCoord.lng, hoverCoord.lat).northing) }}N
              </span>
              <span class="text-slate-300 hidden xl:inline">
                ({{ formatDms(hoverCoord.lat, true) }}, {{ formatDms(hoverCoord.lng, false) }})
              </span>
              <UBadge v-if="hoverCoord.inside" color="success" variant="solid" size="md" class="font-semibold">
                Area Peta Digital
              </UBadge>
            </div>
            <span v-else class="text-slate-300">
              Gerakkan kursor pada peta untuk membaca koordinat real-time
            </span>

            <div v-if="selectedCoord" class="flex items-center gap-2 font-mono">
              <span class="text-amber-300">Pin: {{ selectedCoord.lat }}, {{ selectedCoord.lng }}</span>
              <button
                type="button"
                class="px-2.5 py-1 rounded bg-white/15 hover:bg-white/25 text-white font-sans font-semibold cursor-pointer"
                @click="copySelectedCoord"
              >
                {{ selectedCoord.copied ? 'Tersalin' : 'Salin Lat, Lng' }}
              </button>
            </div>
          </div>
        </div>

        <!-- 4 Corner Coordinates Table -->
        <div v-if="georefCorners" class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
          <div
            v-for="(coord, idx) in georefCorners"
            :key="idx"
            class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-white/[0.03] border border-slate-200/80 dark:border-slate-800 font-mono tabular-nums"
          >
            <p class="font-sans font-bold text-[#2B5B9E] dark:text-sky-300">{{ CORNER_NAMES[idx] }}</p>
            <p class="text-slate-700 dark:text-slate-300 mt-0.5">{{ coord[1].toFixed(6) }}, {{ coord[0].toFixed(6) }}</p>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

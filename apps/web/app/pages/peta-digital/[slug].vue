<script setup lang="ts">
import * as maplibregl from 'maplibre-gl';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import 'maplibre-gl/dist/maplibre-gl.css';
import BasemapSwitcher from '~/components/common/BasemapSwitcher.vue';
import {
  resolvePublikasiDownloadUrl,
  resolvePublikasiGeorefImageUrl,
  usePublikasiPetaApi,
} from '~/composables/usePublikasiPetaApi';
import { useBasemapStore } from '~/stores/basemap';
import type { PublikasiGeorefCorners, PublikasiLngLat, PublikasiPetaDigitalItem } from '~/types/publikasi-peta';
import { computeBboxFromCorners, formatDms, isPointInQuad, lngLatToUtm } from '~/utils/georef-math';
import { buildMaplibreBasemapSpec } from '~/utils/maplibre-basemaps';
import { rasterizePdfPageToPng } from '~/utils/pdf-rasterizer';

const route = useRoute();
const api = usePublikasiPetaApi();
const basemapStore = useBasemapStore();

const idOrSlug = computed(() => String(route.params.slug || ''));

const loading = ref(true);
const loadError = ref<string | null>(null);
const item = ref<PublikasiPetaDigitalItem | null>(null);
const activeView = ref<'map' | 'pdf' | 'external'>('pdf');
const pdfRenderMode = ref<'iframe' | 'canvas'>('iframe');
const showInfoPanel = ref(false);
const copiedShare = ref(false);

// MapLibre GL JS state
const activeBasemap = ref<string>('esri');
const opacity = ref<number>(0.88);
const showCornerLabels = ref<boolean>(true);
const mapContainerRef = ref<HTMLElement | null>(null);
const hoverCoord = ref<{ lng: number; lat: number; inside: boolean } | null>(null);
const selectedCoord = ref<{ lng: number; lat: number; inside: boolean; copied: boolean } | null>(null);

let mapInstance: maplibregl.Map | null = null;
let resizeObserver: ResizeObserver | null = null;
let pinMarker: maplibregl.Marker | null = null;
let cornerMarkers: maplibregl.Marker[] = [];

// PDF.js Canvas fallback state
const pdfCanvasLoading = ref(false);
const pdfCanvasError = ref<string | null>(null);
const pdfCanvasImgUrl = ref<string | null>(null);
const pdfPageNum = ref(1);
const pdfPageCount = ref(1);

const CORNER_NAMES = ['Kiri Atas (TL)', 'Kanan Atas (TR)', 'Kanan Bawah (BR)', 'Kiri Bawah (BL)'] as const;

usePublicSeo({
  title: () => (item.value ? `${item.value.judul} · Peta Digital` : 'Detail Peta Digital · MELAROSA'),
  description: () => item.value?.ringkasan || 'Tampilan penuh peta digital resmi Kabupaten Bojonegoro.',
  path: () => `/peta-digital/${idOrSlug.value}`,
});

const georefCorners = computed<PublikasiGeorefCorners | null>(() => {
  const coords = item.value?.georef_coordinates;
  if (!Array.isArray(coords) || coords.length !== 4) return null;
  return coords as PublikasiGeorefCorners;
});

const overlayImageUrl = computed(() => {
  if (!item.value?.is_georeferenced || !item.value?.has_georef_image) return null;
  return resolvePublikasiGeorefImageUrl(item.value.id, item.value.georeferenced_at);
});

const pdfInlineUrl = computed(() => {
  if (!item.value || item.value.sumber_tipe !== 'file_pdf' || !item.value.has_file) return '';
  return resolvePublikasiDownloadUrl(item.value.id, { inline: true });
});

function revokePdfCanvasUrl() {
  if (pdfCanvasImgUrl.value) {
    URL.revokeObjectURL(pdfCanvasImgUrl.value);
    pdfCanvasImgUrl.value = null;
  }
}

async function loadPdfCanvasPage(page = 1) {
  if (!pdfInlineUrl.value) return;
  pdfCanvasLoading.value = true;
  pdfCanvasError.value = null;
  try {
    const res = await rasterizePdfPageToPng(pdfInlineUrl.value, page, 3200);
    revokePdfCanvasUrl();
    pdfCanvasImgUrl.value = res.objectUrl;
    pdfPageNum.value = res.pageNumber;
    pdfPageCount.value = res.pageCount;
  } catch (err: any) {
    pdfCanvasError.value = err?.message || 'Gagal merender halaman PDF.';
  } finally {
    pdfCanvasLoading.value = false;
  }
}

function renderOverlayLayers() {
  if (!mapInstance || !mapInstance.isStyleLoaded() || !georefCorners.value || !overlayImageUrl.value) return;

  const srcId = 'detail-georef-image';
  const layerId = 'detail-georef-image-layer';
  const lineSrcId = 'detail-georef-outline';
  const lineLayerId = 'detail-georef-outline-layer';

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
    { padding: 52, duration: 450 },
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
  if (!mapContainerRef.value || !item.value?.is_georeferenced) return;
  destroyMap();

  if (import.meta.client && typeof maplibregl.setWorkerUrl === 'function') {
    maplibregl.setWorkerUrl(maplibreWorkerUrl);
  }

  basemapStore.ensureLoaded();
  opacity.value = item.value.georef_opacity ?? 0.88;

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

async function loadDetail() {
  if (!idOrSlug.value) return;
  loading.value = true;
  loadError.value = null;
  destroyMap();
  revokePdfCanvasUrl();

  try {
    const res = await api.fetchPublicDetail(idOrSlug.value);
    if (!res?.data) {
      loadError.value = 'Publikasi peta digital tidak ditemukan.';
      return;
    }
    item.value = res.data;

    if (res.data.is_georeferenced && res.data.has_georef_image) {
      activeView.value = 'map';
    } else if (res.data.sumber_tipe === 'file_pdf' && res.data.has_file) {
      activeView.value = 'pdf';
    } else {
      activeView.value = 'external';
    }
  } catch (err: any) {
    loadError.value = err?.data?.message || 'Terbitan peta digital tidak ditemukan atau tidak tersedia.';
    return;
  } finally {
    loading.value = false;
  }

  if (activeView.value === 'map') {
    await nextTick();
    initMap();
  }
}

function triggerDownload() {
  if (!item.value || !import.meta.client) return;
  const url = resolvePublikasiDownloadUrl(item.value.id);
  window.open(url, '_blank', 'noopener,noreferrer');
  item.value.download_count = (item.value.download_count || 0) + 1;
}

function copyPageLink() {
  if (!import.meta.client) return;
  navigator?.clipboard?.writeText(window.location.href).catch(() => {});
  copiedShare.value = true;
  setTimeout(() => {
    copiedShare.value = false;
  }, 2000);
}

function copySelectedCoord() {
  if (!selectedCoord.value || !import.meta.client) return;
  const text = `${selectedCoord.value.lat}, ${selectedCoord.value.lng}`;
  navigator?.clipboard?.writeText(text).catch(() => {});
  selectedCoord.value.copied = true;
}

watch(activeView, async (next) => {
  if (next === 'map') {
    await nextTick();
    initMap();
  } else {
    destroyMap();
    if (next === 'pdf' && pdfRenderMode.value === 'canvas' && !pdfCanvasImgUrl.value) {
      loadPdfCanvasPage(1);
    }
  }
});

watch(pdfRenderMode, (mode) => {
  if (mode === 'canvas' && !pdfCanvasImgUrl.value) {
    loadPdfCanvasPage(1);
  }
});

watch(activeBasemap, (code) => {
  applyBasemapStyle(code);
});

watch(opacity, (val) => {
  if (mapInstance?.getLayer('detail-georef-image-layer')) {
    mapInstance.setPaintProperty('detail-georef-image-layer', 'raster-opacity', val);
  }
});

watch(showCornerLabels, () => {
  refreshCornerMarkers();
});

watch(idOrSlug, () => {
  loadDetail();
});

onMounted(() => {
  loadDetail();
});

onBeforeUnmount(() => {
  destroyMap();
  revokePdfCanvasUrl();
});
</script>

<template>
  <div class="min-h-[calc(100vh-88px)] bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col">
    <!-- Top Compact Bar -->
    <header class="shrink-0 bg-white dark:bg-[#0c1522] border-b border-slate-200/85 dark:border-white/[0.08] px-4 sm:px-6 py-3">
      <div class="max-w-[1600px] mx-auto flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div class="min-w-0 space-y-1">
          <nav aria-label="Jejak navigasi" class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
            <NuxtLink to="/" class="hover:text-[#2B5B9E] dark:hover:text-sky-400 font-medium">
              Beranda
            </NuxtLink>
            <UIcon name="i-lucide-chevron-right" class="size-3" aria-hidden="true" />
            <NuxtLink to="/peta-digital" class="hover:text-[#2B5B9E] dark:hover:text-sky-400 font-medium">
              Peta Digital
            </NuxtLink>
            <UIcon name="i-lucide-chevron-right" class="size-3" aria-hidden="true" />
            <span class="font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[220px]">
              {{ item?.judul || 'Detail Peta' }}
            </span>
          </nav>

          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tracking-tight truncate max-w-3xl">
              {{ item?.judul || 'Memuat Peta Digital...' }}
            </h1>
            <UBadge
              v-if="item?.is_georeferenced && item?.has_georef_image"
              color="success"
              variant="subtle"
              size="md"
              class="font-semibold"
            >
              Ter-georeference (MapLibre)
            </UBadge>
          </div>

          <div v-if="item" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
            <span class="inline-flex items-center gap-1 font-semibold text-[#2B5B9E] dark:text-sky-300">
              <UIcon name="i-lucide-map-pin" class="size-3.5" aria-hidden="true" />
              <span>{{ item.wilayah_label }}</span>
            </span>
            <span>{{ item.kategori }}</span>
            <span>Tahun {{ item.tahun_terbit }}</span>
            <span v-if="item.skala_peta">Skala {{ item.skala_peta }}</span>
            <span v-if="item.nomor_dokumen" class="font-mono text-[11px]">No. {{ item.nomor_dokumen }}</span>
          </div>
        </div>

        <div v-if="item" class="flex flex-wrap items-center gap-2 shrink-0">
          <!-- Mode Switcher when both MapLibre & PDF exist -->
          <div
            v-if="item.is_georeferenced && item.has_georef_image && item.sumber_tipe === 'file_pdf' && item.has_file"
            class="inline-flex rounded-xl border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/[0.04] p-0.5"
          >
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-colors flex items-center gap-1.5"
              :class="activeView === 'map'
                ? 'bg-[#2B5B9E] text-white shadow-2xs'
                : 'text-slate-700 dark:text-slate-300'"
              @click="activeView = 'map'"
            >
              <UIcon name="i-lucide-map" class="size-3.5" aria-hidden="true" />
              <span>Peta Koordinat</span>
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-colors flex items-center gap-1.5"
              :class="activeView === 'pdf'
                ? 'bg-[#2B5B9E] text-white shadow-2xs'
                : 'text-slate-700 dark:text-slate-300'"
              @click="activeView = 'pdf'"
            >
              <UIcon name="i-lucide-file-text" class="size-3.5" aria-hidden="true" />
              <span>Dokumen PDF</span>
            </button>
          </div>

          <UButton
            :icon="item.sumber_tipe === 'file_pdf' ? 'i-lucide-download' : 'i-lucide-external-link'"
            :label="item.sumber_tipe === 'file_pdf' ? 'Unduh PDF' : 'Buka Tautan'"
            color="primary"
            size="sm"
            class="cursor-pointer"
            @click="triggerDownload"
          />

          <UButton
            :icon="copiedShare ? 'i-lucide-check' : 'i-lucide-share-2'"
            :label="copiedShare ? 'Tersalin' : 'Salin Tautan'"
            color="neutral"
            variant="outline"
            size="sm"
            class="cursor-pointer"
            @click="copyPageLink"
          />

          <UButton
            icon="i-lucide-panel-right"
            :label="showInfoPanel ? 'Tutup Info' : 'Info Peta'"
            :color="showInfoPanel ? 'primary' : 'neutral'"
            variant="outline"
            size="sm"
            class="cursor-pointer"
            @click="showInfoPanel = !showInfoPanel"
          />
        </div>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="flex-1 p-4 sm:p-6 max-w-[1600px] w-full mx-auto">
      <USkeleton class="h-[75vh] w-full rounded-2xl" />
    </div>

    <!-- Error -->
    <div v-else-if="loadError || !item" class="flex-1 flex items-center justify-center p-6">
      <div class="max-w-md w-full rounded-2xl bg-white dark:bg-[#0c1522] border border-red-200 dark:border-red-800 p-8 text-center space-y-3">
        <UIcon name="i-lucide-file-warning" class="size-10 mx-auto text-red-600" aria-hidden="true" />
        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ loadError || 'Publikasi peta tidak ditemukan.' }}</p>
        <NuxtLink to="/peta-digital">
          <UButton icon="i-lucide-arrow-left" label="Kembali ke Katalog Peta Digital" color="primary" size="sm" />
        </NuxtLink>
      </div>
    </div>

    <!-- Main Full-Screen Map + Optional Info Panel -->
    <main v-else class="flex-1 flex flex-col lg:flex-row min-h-0 max-w-[1600px] w-full mx-auto p-3 sm:p-4 gap-3">
      <section class="flex-1 min-w-0 flex flex-col rounded-2xl overflow-hidden border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1522] shadow-2xs min-h-[75vh]">
        <!-- VIEW 1: MapLibre GL JS Full View -->
        <template v-if="activeView === 'map'">
          <div class="shrink-0 px-3 py-2 border-b border-slate-200/80 dark:border-white/[0.08] bg-slate-50/90 dark:bg-white/[0.02] flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex flex-wrap items-center gap-2 text-slate-600 dark:text-slate-300">
              <span class="font-semibold text-slate-800 dark:text-white">Tampilan Peta Interaktif MapLibre GL JS</span>
              <span v-if="item.georef_rms_meters !== null && item.georef_rms_meters !== undefined" class="tabular-nums">
                · Akurasi RMS: {{ item.georef_rms_meters }} m
              </span>
              <span class="hidden sm:inline text-slate-500">· Klik peta untuk menyalin titik koordinat</span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 cursor-pointer">
                <input v-model="showCornerLabels" type="checkbox" class="rounded border-slate-300" />
                <span>Sudut</span>
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

          <div class="relative flex-1 min-h-[540px] w-full">
            <div ref="mapContainerRef" class="w-full h-full" />

            <BasemapSwitcher
              v-model="activeBasemap"
              engine="maplibre"
              variant="compact"
              placement="top-right"
            />

            <!-- Coordinate Readout HUD -->
            <div class="absolute bottom-3 left-3 right-3 flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-xl bg-slate-900/85 text-white text-xs backdrop-blur-xs">
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
                Gerakkan kursor atau klik pada peta untuk membaca koordinat WGS84 / UTM 49S
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
        </template>

        <!-- VIEW 2: Full-Height PDF Preview (Same-Origin Proxy + PDF.js Fallback) -->
        <template v-else-if="activeView === 'pdf'">
          <div class="shrink-0 px-3 py-2 border-b border-slate-200/80 dark:border-white/[0.08] bg-slate-50/90 dark:bg-white/[0.02] flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-slate-800 dark:text-white">Pratinjau Dokumen Peta Digital (PDF)</span>
              <span v-if="item.file_size_label" class="text-slate-500">({{ item.file_size_label }})</span>
            </div>

            <div class="flex items-center gap-2">
              <div v-if="pdfRenderMode === 'canvas' && pdfPageCount > 1" class="inline-flex items-center gap-1">
                <UButton
                  icon="i-lucide-chevron-left"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  :disabled="pdfPageNum <= 1 || pdfCanvasLoading"
                  @click="loadPdfCanvasPage(pdfPageNum - 1)"
                />
                <span class="font-medium">Hal {{ pdfPageNum }}/{{ pdfPageCount }}</span>
                <UButton
                  icon="i-lucide-chevron-right"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  :disabled="pdfPageNum >= pdfPageCount || pdfCanvasLoading"
                  @click="loadPdfCanvasPage(pdfPageNum + 1)"
                />
              </div>

              <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-0.5">
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-[11px] font-semibold cursor-pointer transition-colors"
                  :class="pdfRenderMode === 'iframe' ? 'bg-[#2B5B9E] text-white' : 'text-slate-600 dark:text-slate-300'"
                  @click="pdfRenderMode = 'iframe'"
                >
                  PDF Viewer
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-[11px] font-semibold cursor-pointer transition-colors"
                  :class="pdfRenderMode === 'canvas' ? 'bg-[#2B5B9E] text-white' : 'text-slate-600 dark:text-slate-300'"
                  @click="pdfRenderMode = 'canvas'"
                >
                  Mode Gambar
                </button>
              </div>
            </div>
          </div>

          <div class="flex-1 min-h-[600px] bg-slate-100 dark:bg-slate-900 relative flex items-center justify-center overflow-auto">
            <iframe
              v-if="pdfRenderMode === 'iframe' && pdfInlineUrl"
              :src="pdfInlineUrl"
              title="Pratinjau Penuh Peta Digital PDF"
              class="w-full h-full min-h-[600px] border-0"
            />

            <template v-else>
              <div v-if="pdfCanvasLoading" class="flex flex-col items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                <UIcon name="i-lucide-loader-circle" class="size-6 animate-spin text-[#2B5B9E]" aria-hidden="true" />
                <span>Merender halaman peta PDF...</span>
              </div>
              <div v-else-if="pdfCanvasError" class="p-6 text-center space-y-2 text-xs text-red-700 dark:text-red-300">
                <p>{{ pdfCanvasError }}</p>
                <UButton label="Unduh PDF" icon="i-lucide-download" size="xs" color="primary" @click="triggerDownload" />
              </div>
              <img
                v-else-if="pdfCanvasImgUrl"
                :src="pdfCanvasImgUrl"
                :alt="item.judul"
                class="max-h-full max-w-full object-contain shadow-lg p-3"
              />
            </template>
          </div>
        </template>

        <!-- VIEW 3: External URL View -->
        <template v-else>
          <div class="flex-1 flex items-center justify-center p-8 text-center">
            <div class="max-w-lg space-y-4">
              <div class="w-14 h-14 rounded-2xl mx-auto bg-blue-50 dark:bg-blue-950/40 text-[#2B5B9E] dark:text-sky-300 flex items-center justify-center">
                <UIcon name="i-lucide-globe" class="size-7" aria-hidden="true" />
              </div>
              <div class="space-y-1.5">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                  Peta Digital Tautan Eksternal
                </h2>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                  Sumber peta digital ini dihosting pada domain resmi <strong>{{ item.external_domain || 'eksternal' }}</strong>. Klik tombol di bawah untuk membuka peta penuh di tab baru.
                </p>
              </div>
              <UButton
                icon="i-lucide-external-link"
                label="Buka Sumber Peta Digital"
                color="primary"
                size="md"
                class="cursor-pointer"
                @click="triggerDownload"
              />
            </div>
          </div>
        </template>
      </section>

      <!-- Collapsible Metadata & Info Sidebar -->
      <aside
        v-if="showInfoPanel"
        class="lg:w-80 shrink-0 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200 dark:border-white/[0.08] p-4 space-y-4 overflow-y-auto"
      >
        <div class="space-y-1.5">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Informasi Publikasi</h2>
          <p v-if="item.ringkasan" class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ item.ringkasan }}
          </p>
        </div>

        <dl class="space-y-2 text-xs border-t border-slate-200/80 dark:border-white/[0.08] pt-3">
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Wilayah</dt>
            <dd class="font-semibold text-right">{{ item.wilayah_label }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Kategori</dt>
            <dd class="font-semibold text-right">{{ item.kategori }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Tahun / Edisi</dt>
            <dd class="font-semibold text-right">{{ item.tahun_terbit }}{{ item.edisi ? ` (${item.edisi})` : '' }}</dd>
          </div>
          <div v-if="item.skala_peta" class="flex justify-between gap-2">
            <dt class="text-slate-500">Skala Peta</dt>
            <dd class="font-semibold text-right">{{ item.skala_peta }}</dd>
          </div>
          <div v-if="item.nomor_dokumen" class="flex justify-between gap-2">
            <dt class="text-slate-500">Nomor Dokumen</dt>
            <dd class="font-mono text-right">{{ item.nomor_dokumen }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-slate-500">Total Unduhan</dt>
            <dd class="font-semibold tabular-nums text-right">{{ item.download_count }}x</dd>
          </div>
        </dl>

        <div v-if="georefCorners" class="space-y-2 border-t border-slate-200/80 dark:border-white/[0.08] pt-3">
          <h3 class="text-xs font-bold text-slate-900 dark:text-white">Koordinat 4 Sudut (WGS84)</h3>
          <div class="space-y-1.5">
            <div
              v-for="(coord, idx) in georefCorners"
              :key="idx"
              class="px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-white/[0.04] border border-slate-200/70 dark:border-white/[0.08] text-[11px] font-mono tabular-nums"
            >
              <p class="font-sans font-semibold text-[#2B5B9E] dark:text-sky-300">{{ CORNER_NAMES[idx] }}</p>
              <p>{{ coord[1].toFixed(6) }}, {{ coord[0].toFixed(6) }}</p>
            </div>
          </div>
        </div>
      </aside>
    </main>
  </div>
</template>

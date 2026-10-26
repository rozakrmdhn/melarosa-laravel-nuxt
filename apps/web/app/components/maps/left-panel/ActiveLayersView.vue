<script setup lang="ts">
import type { ActiveLayerItem } from "~/types/map-layers";
import { useGeoparkSites, type GeoparkCategoryFilter } from "~/composables/useGeoparkSites";

const { geologicalSites, biologicalSites, culturalSites } = useGeoparkSites();

const repGeoSite = computed(() => geologicalSites.value.find((s) => Boolean(s.image)) || geologicalSites.value[0]);
const repBioSite = computed(() => biologicalSites.value.find((s) => Boolean(s.image)) || biologicalSites.value[0]);
const repCultureSite = computed(() => culturalSites.value.find((s) => Boolean(s.image)) || culturalSites.value[0]);

const props = defineProps<{
  activeLayers: ActiveLayerItem[];
  selectedKecamatanFilter?: string | null;
  clickedCoordinate?: [number, number] | null;
  inspectorOpen?: boolean;
  universalSearchQuery?: string;
  filteredActiveLayers: ActiveLayerItem[];
}>();

const emit = defineEmits<{
  (e: "select-layer", layerId: string): void;
  (e: "toggle-layer-visibility", layerId: string): void;
  (e: "reorder-layers", layers: ActiveLayerItem[]): void;
  (e: "update-filter", payload: { layerId: string; kecamatan: string | null; status: string | null }): void;
  (e: "reset-wilayah"): void;
  (e: "go-to-geoportal"): void;
  (e: "go-to-geopark", category?: GeoparkCategoryFilter): void;
  (e: "go-to-identify"): void;
  (e: "clear-search"): void;
  (e: "share"): void;
}>();

const draggedLayerIndex = ref<number | null>(null);
const recentlyMovedLayerId = ref<string | null>(null);
const isTouchDevice = ref(false);
const isTouchDragging = ref(false);
let touchCurrentIndex = -1;

onMounted(() => {
  if (typeof window !== "undefined") {
    isTouchDevice.value =
      "ontouchstart" in window ||
      navigator.maxTouchPoints > 0 ||
      (window.matchMedia && window.matchMedia("(pointer: coarse)").matches);
  }
});

function moveLayer(fromFilteredIndex: number, toFilteredIndex: number) {
  if (fromFilteredIndex === toFilteredIndex) return;
  const listFiltered = props.filteredActiveLayers;
  if (fromFilteredIndex < 0 || fromFilteredIndex >= listFiltered.length) return;
  if (toFilteredIndex < 0 || toFilteredIndex >= listFiltered.length) return;

  const sourceLayer = listFiltered[fromFilteredIndex];
  const targetLayer = listFiltered[toFilteredIndex];
  if (!sourceLayer || !targetLayer) return;

  const fullList = [...props.activeLayers];
  const sourceFullIndex = fullList.findIndex((l) => l.id === sourceLayer.id);
  const targetFullIndex = fullList.findIndex((l) => l.id === targetLayer.id);
  if (sourceFullIndex === -1 || targetFullIndex === -1) return;

  const [removed] = fullList.splice(sourceFullIndex, 1);
  if (!removed) return;
  fullList.splice(targetFullIndex, 0, removed);

  recentlyMovedLayerId.value = sourceLayer.id;
  setTimeout(() => {
    if (recentlyMovedLayerId.value === sourceLayer.id) {
      recentlyMovedLayerId.value = null;
    }
  }, 900);

  emit("reorder-layers", fullList);
}

function onDragStart(index: number, event: DragEvent) {
  if (isTouchDevice.value || props.universalSearchQuery) {
    event.preventDefault();
    return;
  }
  draggedLayerIndex.value = index;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(index));
  }
}

function onDragOver(event: DragEvent) {
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = "move";
  }
}

function onDrop(dropIndex: number) {
  if (draggedLayerIndex.value === null || draggedLayerIndex.value === dropIndex) return;
  moveLayer(draggedLayerIndex.value, dropIndex);
  draggedLayerIndex.value = null;
}

function onGripTouchStart(index: number, event: TouchEvent) {
  if (props.universalSearchQuery) return;
  // Mencegah browser mobile memicu drag teks native ke Chrome Omnibox / Google Search
  if (event.cancelable) {
    event.preventDefault();
  }
  event.stopPropagation();

  draggedLayerIndex.value = index;
  touchCurrentIndex = index;
  isTouchDragging.value = true;
}

function onGripTouchMove(event: TouchEvent) {
  if (!isTouchDragging.value || draggedLayerIndex.value === null) return;
  if (event.cancelable) {
    event.preventDefault();
  }
  event.stopPropagation();

  const touch = event.touches[0];
  if (!touch) return;

  const targetEl = document.elementFromPoint(touch.clientX, touch.clientY);
  if (!targetEl) return;

  const cardEl = targetEl.closest("[data-layer-card-index]") as HTMLElement | null;
  if (cardEl && cardEl.dataset.layerCardIndex !== undefined) {
    const targetIdx = Number(cardEl.dataset.layerCardIndex);
    if (!Number.isNaN(targetIdx) && targetIdx !== touchCurrentIndex && targetIdx >= 0 && targetIdx < props.filteredActiveLayers.length) {
      moveLayer(touchCurrentIndex, targetIdx);
      touchCurrentIndex = targetIdx;
      draggedLayerIndex.value = targetIdx;
    }
  }
}

function onGripTouchEnd(event?: TouchEvent) {
  if (event && event.cancelable) {
    event.preventDefault();
  }
  draggedLayerIndex.value = null;
  touchCurrentIndex = -1;
  isTouchDragging.value = false;
}
</script>

<template>
  <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-3 space-y-3">
    <!-- Banner Filter Wilayah Aktif Peta -->
    <div
      v-if="props.selectedKecamatanFilter"
      class="flex items-center justify-between p-2.5 rounded-xl bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200/70 dark:border-blue-900/50 shadow-2xs"
    >
      <div class="flex items-center gap-2 min-w-0">
        <div class="size-7 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-map-pin" class="size-4" />
        </div>
        <div class="min-w-0">
          <span class="text-xs uppercase font-bold tracking-wider text-blue-600 dark:text-blue-400 block">
            Filter Wilayah Aktif
          </span>
          <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
            Kecamatan {{ props.selectedKecamatanFilter }}
          </span>
        </div>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800 transition-colors cursor-pointer shadow-2xs shrink-0"
        title="Bersihkan filter wilayah dan reset ke seluruh Bojonegoro"
        @click="emit('reset-wilayah')"
      >
        <UIcon name="i-lucide-x" class="size-3.5" />
        <span>Hapus Filter</span>
      </button>
    </div>

    <!-- Subheader urutan layer -->
    <div v-if="props.activeLayers.length > 0" class="flex items-center justify-between px-1">
      <div class="flex items-center gap-1.5">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Layer</span>
        <span class="px-1.5 py-0.2 rounded-full text-xs font-bold tabular-nums bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
          {{ props.activeLayers.length }}
        </span>
      </div>
    </div>

    <!-- State belum ada layer aktif -->
    <div
      v-if="props.activeLayers.length === 0"
      class="p-5 rounded-2xl border border-dashed border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] text-center space-y-3.5"
    >
      <div class="size-11 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center shadow-xs">
        <UIcon name="i-lucide-layers-2" class="size-5.5" />
      </div>
      <div class="space-y-1">
        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Belum Ada Layer Aktif</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed px-1">
          Tambahkan layer tematik dari katalog data spasial untuk dianalisis di atas peta Bojonegoro.
        </p>
      </div>
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
        @click="emit('go-to-geoportal')"
      >
        Jelajahi Katalog Geoportal
      </button>
    </div>

    <!-- State hasil filter kueri tidak ditemukan -->
    <div
      v-else-if="props.universalSearchQuery && props.filteredActiveLayers.length === 0"
      class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] text-center space-y-2"
    >
      <p class="text-xs text-slate-600 dark:text-slate-400">
        Tidak ada layer aktif yang cocok dengan "{{ props.universalSearchQuery }}"
      </p>
      <button
        type="button"
        class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline cursor-pointer min-h-[44px] px-2"
        @click="emit('clear-search')"
      >
        Bersihkan pencarian
      </button>
    </div>

    <!-- Daftar kartu layer aktif -->
    <TransitionGroup
      v-else
      name="layer-card"
      tag="div"
      class="space-y-2 relative"
    >
      <div
        v-for="(layer, index) in props.filteredActiveLayers"
        :key="layer.id"
        :data-layer-card-index="index"
        :draggable="!isTouchDevice && !props.universalSearchQuery"
        class="rounded-xl border transition-all duration-200 overflow-hidden select-none"
        :class="[
          layer.visible
            ? 'border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0c1424] shadow-xs'
            : 'border-slate-200/50 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] opacity-75',
          draggedLayerIndex === index ? 'opacity-40 border-dashed border-blue-500 ring-2 ring-blue-500/30' : '',
          recentlyMovedLayerId === layer.id ? 'ring-2 ring-blue-500/70 border-blue-500 bg-blue-50/40 dark:bg-blue-950/30 shadow-md shadow-blue-500/10' : ''
        ]"
        style="-webkit-user-select: none; user-select: none; -webkit-touch-callout: none;"
        @dragstart="onDragStart(index, $event)"
        @dragover="onDragOver($event)"
        @drop="onDrop(index)"
        @dragend="draggedLayerIndex = null"
      >
        <div
          class="flex items-center justify-between px-2.5 py-2 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-white/[0.03] transition-colors"
          @click="emit('select-layer', layer.id)"
        >
          <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 pr-1 flex-1">
            <!-- Grip Drag Handle (Desktop HTML5 & Mobile Touch-Drag Aman Tanpa Search Redirect) -->
            <div
              class="flex items-center shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 p-1 -ml-1 select-none touch-none cursor-grab active:cursor-grabbing"
              title="Seret atau gunakan tombol panah untuk mengubah urutan layer"
              @touchstart.passive="false"
              @touchstart="onGripTouchStart(index, $event)"
              @touchmove.passive="false"
              @touchmove="onGripTouchMove($event)"
              @touchend="onGripTouchEnd($event)"
              @touchcancel="onGripTouchEnd($event)"
              @click.stop
            >
              <UIcon name="i-lucide-grip-vertical" class="size-3.5 text-slate-300 dark:text-slate-600" />
            </div>

            <!-- Tombol 1-Tap Reorder (Naikkan / Turunkan Urutan Layer) -->
            <div class="flex flex-col items-center justify-center shrink-0 -my-1 -ml-0.5" @click.stop>
              <button
                type="button"
                class="relative after:absolute after:-inset-1 size-4 rounded flex items-center justify-center transition-colors text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-20 disabled:pointer-events-none cursor-pointer"
                :disabled="index === 0 || !!props.universalSearchQuery"
                aria-label="Naikkan urutan layer"
                title="Naikkan urutan layer"
                @click.stop="moveLayer(index, index - 1)"
              >
                <UIcon name="i-lucide-chevron-up" class="size-3" />
              </button>
              <button
                type="button"
                class="relative after:absolute after:-inset-1 size-4 rounded flex items-center justify-center transition-colors text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-20 disabled:pointer-events-none cursor-pointer"
                :disabled="index === props.filteredActiveLayers.length - 1 || !!props.universalSearchQuery"
                aria-label="Turunkan urutan layer"
                title="Turunkan urutan layer"
                @click.stop="moveLayer(index, index + 1)"
              >
                <UIcon name="i-lucide-chevron-down" class="size-3" />
              </button>
            </div>

            <div class="min-w-0 flex-1">
              <span
                class="block text-xs font-semibold truncate tracking-tight"
                :class="layer.visible ? 'text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400'"
              >
                {{ layer.title || layer.name }}
              </span>
              <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">
                {{ layer.instansi || layer.attribution || layer.agency || layer.protocol || 'WMS' }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-0.5 shrink-0" @click.stop>
            <UTooltip :text="layer.visible ? 'Sembunyikan' : 'Tampilkan'">
              <button
                type="button"
                class="relative after:absolute after:-inset-1.5 after:content-[''] size-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
                :class="layer.visible
                  ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-white/5'"
                aria-label="Toggle visibilitas layer"
                @click="emit('toggle-layer-visibility', layer.id)"
              >
                <UIcon :name="layer.visible ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="size-4" />
              </button>
            </UTooltip>
            <UIcon name="i-lucide-chevron-right" class="size-3.5 text-slate-500 dark:text-slate-400" />
          </div>
        </div>

        <!-- Active Filter Wilayah on Layer with Clear Action -->
        <div
          v-if="layer.filterKecamatan && layer.filterKecamatan !== 'ALL'"
          class="flex items-center justify-between px-2.5 py-1.5 bg-blue-50/60 dark:bg-blue-950/30 border-t border-blue-100/80 dark:border-blue-900/40 text-xs"
          @click.stop
        >
          <div class="flex items-center gap-1.5 text-blue-700 dark:text-blue-300 min-w-0">
            <UIcon name="i-lucide-filter" class="size-3 text-blue-600 dark:text-blue-400 shrink-0" />
            <span class="truncate font-medium">Filter Wilayah: <strong>Kec. {{ layer.filterKecamatan }}</strong></span>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 hover:bg-white dark:hover:bg-white/10 transition-colors cursor-pointer shrink-0"
            title="Hapus filter wilayah pada layer ini"
            @click.stop="emit('update-filter', { layerId: layer.id, kecamatan: null, status: layer.filterStatus || null })"
          >
            <UIcon name="i-lucide-x" class="size-3" />
            <span>Hapus</span>
          </button>
        </div>
      </div>
    </TransitionGroup>

    <!-- Pintasan tematik & eksplorasi spasial -->
    <div class="pt-2 border-t border-slate-100 dark:border-white/5 space-y-2">
      <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 px-1">
        Dataset
      </p>

      <!-- Card 1: Katalog Geoportal -->
      <div
        class="p-2.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-gradient-to-br from-blue-50/60 to-indigo-50/30 dark:from-blue-950/20 dark:to-indigo-950/10 hover:border-blue-400/60 dark:hover:border-blue-500/40 transition-all cursor-pointer group shadow-xs"
        @click="emit('go-to-geoportal')"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="size-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
              <UIcon name="i-lucide-globe-2" class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                  Katalog Dataset
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Akses katalog layer spasial Geoportal Bojonegoro
              </p>
            </div>
          </div>
          <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-500 group-hover:text-blue-600 dark:hover:text-blue-400 transition-colors shrink-0" />
        </div>
      </div>

      <!-- Pintasan Geosite Geopark Bojonegoro -->
      <div class="space-y-2 pt-1">
        <div class="flex items-center justify-between px-1">
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              Geosite Geopark
            </span>
          </div>
          <button
            type="button"
            class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline cursor-pointer flex items-center gap-0.5"
            @click="emit('go-to-geopark', 'ALL')"
          >
            <span>Semua Situs</span>
            <UIcon name="i-lucide-arrow-up-right" class="size-3" />
          </button>
        </div>

        <!-- 3 Kartu Kategori Pilar (Per Baris / Stacked Rows dengan Background Foto Perwakilan Geosite) -->
        <div class="space-y-2.5">
          <!-- 1. Pilar Geologi -->
          <button
            type="button"
            class="relative w-full overflow-hidden rounded-2xl border border-slate-200/90 dark:border-white/10 bg-slate-900 group cursor-pointer text-left transition-all duration-300 hover:shadow-md hover:border-blue-400/80 dark:hover:border-blue-500/80 active:scale-[0.99] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 min-h-[76px]"
            @click="emit('go-to-geopark', 'geo-diversity')"
          >
            <!-- Background Image dari Perwakilan Geosite Geologi -->
            <img
              v-if="repGeoSite?.image"
              :src="repGeoSite.image"
              :alt="repGeoSite.name || 'Keragaman Geologi'"
              loading="lazy"
              class="absolute inset-0 w-full h-full object-cover opacity-60 dark:opacity-45 group-hover:scale-105 group-hover:opacity-75 transition-all duration-500"
            />
            <div
              v-else
              class="absolute inset-0 bg-gradient-to-r from-blue-950 via-slate-900 to-slate-900"
            />
            <!-- Dark Gradient Scrim Overlay untuk Keterbacaan Teks Maksimal -->
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/80 to-slate-950/45" />

            <!-- Content Area -->
            <div class="relative z-10 flex items-center justify-between p-3 sm:p-3.5 gap-3">
              <div class="min-w-0 flex-1 space-y-0.5">
                <div class="flex items-center gap-2">
                  <span class="text-xs text-slate-300/80 font-medium tabular-nums">
                    {{ geologicalSites.length ? `${geologicalSites.length} Situs` : 'Keragaman Alam' }}
                  </span>
                </div>
                <h4 class="text-xs sm:text-sm font-bold text-white tracking-tight group-hover:text-blue-300 transition-colors truncate">
                  Keragaman Geologi
                </h4>
                <p class="text-[11px] text-slate-300/90 truncate font-normal">
                  {{ repGeoSite ? `Situs unggulan: ${repGeoSite.name || repGeoSite.title}` : 'Fenomena alam, fosil & formasi batuan purba' }}
                </p>
              </div>

              <div class="size-8 rounded-full bg-white/10 dark:bg-white/15 border border-white/20 flex items-center justify-center text-white shrink-0 group-hover:bg-blue-600 group-hover:border-blue-500 group-hover:translate-x-0.5 transition-all shadow-xs">
                <UIcon name="i-lucide-chevron-right" class="size-4" />
              </div>
            </div>
          </button>

          <!-- 2. Pilar Hayati -->
          <button
            type="button"
            class="relative w-full overflow-hidden rounded-2xl border border-slate-200/90 dark:border-white/10 bg-slate-900 group cursor-pointer text-left transition-all duration-300 hover:shadow-md hover:border-emerald-400/80 dark:hover:border-emerald-500/80 active:scale-[0.99] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 min-h-[76px]"
            @click="emit('go-to-geopark', 'bio-diversity')"
          >
            <!-- Background Image dari Perwakilan Geosite Hayati -->
            <img
              v-if="repBioSite?.image"
              :src="repBioSite.image"
              :alt="repBioSite.name || 'Keragaman Hayati'"
              loading="lazy"
              class="absolute inset-0 w-full h-full object-cover opacity-60 dark:opacity-45 group-hover:scale-105 group-hover:opacity-75 transition-all duration-500"
            />
            <div
              v-else
              class="absolute inset-0 bg-gradient-to-r from-emerald-950 via-slate-900 to-slate-900"
            />
            <!-- Dark Gradient Scrim Overlay -->
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/80 to-slate-950/45" />

            <!-- Content Area -->
            <div class="relative z-10 flex items-center justify-between p-3 sm:p-3.5 gap-3">
              <div class="min-w-0 flex-1 space-y-0.5">
                <div class="flex items-center gap-2">
                  <span class="text-xs text-slate-300/80 font-medium tabular-nums">
                    {{ biologicalSites.length ? `${biologicalSites.length} Situs` : 'Keragaman Hayati' }}
                  </span>
                </div>
                <h4 class="text-xs sm:text-sm font-bold text-white tracking-tight group-hover:text-emerald-300 transition-colors truncate">
                  Keragaman Hayati
                </h4>
                <p class="text-[11px] text-slate-300/90 truncate font-normal">
                  {{ repBioSite ? `Situs unggulan: ${repBioSite.name || repBioSite.title}` : 'Flora, fauna & ekosistem endemik Bojonegoro' }}
                </p>
              </div>

              <div class="size-8 rounded-full bg-white/10 dark:bg-white/15 border border-white/20 flex items-center justify-center text-white shrink-0 group-hover:bg-emerald-600 group-hover:border-emerald-500 group-hover:translate-x-0.5 transition-all shadow-xs">
                <UIcon name="i-lucide-chevron-right" class="size-4" />
              </div>
            </div>
          </button>

          <!-- 3. Pilar Budaya -->
          <button
            type="button"
            class="relative w-full overflow-hidden rounded-2xl border border-slate-200/90 dark:border-white/10 bg-slate-900 group cursor-pointer text-left transition-all duration-300 hover:shadow-md hover:border-amber-400/80 dark:hover:border-amber-500/80 active:scale-[0.99] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-amber-500 min-h-[76px]"
            @click="emit('go-to-geopark', 'culture-diversity')"
          >
            <!-- Background Image dari Perwakilan Geosite Budaya -->
            <img
              v-if="repCultureSite?.image"
              :src="repCultureSite.image"
              :alt="repCultureSite.name || 'Keragaman Budaya'"
              loading="lazy"
              class="absolute inset-0 w-full h-full object-cover opacity-60 dark:opacity-45 group-hover:scale-105 group-hover:opacity-75 transition-all duration-500"
            />
            <div
              v-else
              class="absolute inset-0 bg-gradient-to-r from-amber-950 via-slate-900 to-slate-900"
            />
            <!-- Dark Gradient Scrim Overlay -->
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/80 to-slate-950/45" />

            <!-- Content Area -->
            <div class="relative z-10 flex items-center justify-between p-3 sm:p-3.5 gap-3">
              <div class="min-w-0 flex-1 space-y-0.5">
                <div class="flex items-center gap-2">
                  <span class="text-xs text-slate-300/80 font-medium tabular-nums">
                    {{ culturalSites.length ? `${culturalSites.length} Situs` : 'Keragaman Budaya' }}
                  </span>
                </div>
                <h4 class="text-xs sm:text-sm font-bold text-white tracking-tight group-hover:text-amber-300 transition-colors truncate">
                  Keragaman Budaya
                </h4>
                <p class="text-[11px] text-slate-300/90 truncate font-normal">
                  {{ repCultureSite ? `Situs unggulan: ${repCultureSite.name || repCultureSite.title}` : 'Tradisi, cagar budaya & warisan leluhur' }}
                </p>
              </div>

              <div class="size-8 rounded-full bg-white/10 dark:bg-white/15 border border-white/20 flex items-center justify-center text-white shrink-0 group-hover:bg-amber-600 group-hover:border-amber-500 group-hover:translate-x-0.5 transition-all shadow-xs">
                <UIcon name="i-lucide-chevron-right" class="size-4" />
              </div>
            </div>
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.layer-card-move {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.layer-card-enter-active,
.layer-card-leave-active {
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.layer-card-enter-from,
.layer-card-leave-to {
  opacity: 0;
  transform: translateY(8px) scale(0.98);
}
</style>

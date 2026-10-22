<script setup lang="ts">
import { useGeoportalLayers, type GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import type { ActiveLayerItem } from "~/types/map-layers";
import GeoportalMetadataModal from "./GeoportalMetadataModal.vue";

const props = withDefaults(
  defineProps<{
    activeLayers?: ActiveLayerItem[];
  }>(),
  {
    activeLayers: () => [],
  }
);

const emit = defineEmits<{
  (e: "toggle-layer", payload: { layer: GeoportalLayerItem; active: boolean; opacity?: number }): void;
  (e: "update-opacity", payload: { layerId: string; opacity: number }): void;
}>();

const {
  groupedLayers,
  categories,
  filteredGrouped,
  totalFilteredCount,
  searchQuery,
  selectedCategory,
  loading,
  refresh,
} = useGeoportalLayers();

// Track which category accordions are expanded (default: first 3 categories open)
const expandedCategories = ref<Record<string, boolean>>({});

// Initialize all categories as expanded by default
watch(
  categories,
  (cats) => {
    cats.forEach((c) => {
      if (expandedCategories.value[c.name] === undefined) {
        expandedCategories.value[c.name] = true;
      }
    });
  },
  { immediate: true }
);

function toggleCategory(catName: string) {
  const isCurrentlyOpen = expandedCategories.value[catName] !== false;
  expandedCategories.value[catName] = !isCurrentlyOpen;
}

// Helpers for active status and opacity
function isLayerActive(layerId: string): boolean {
  return props.activeLayers.some((l) => l.id === layerId);
}

function getLayerOpacity(layerId: string, defaultOpacity: number = 0.85): number {
  const match = props.activeLayers.find((l) => l.id === layerId);
  return match ? match.opacity : defaultOpacity;
}

function handleToggle(layer: GeoportalLayerItem) {
  const currentActive = isLayerActive(layer.id);
  emit("toggle-layer", {
    layer,
    active: !currentActive,
    opacity: layer.opacity || 0.85,
  });
}

function handleOpacityChange(layerId: string, val: number) {
  emit("update-opacity", {
    layerId,
    opacity: val,
  });
}

// Metadata Modal state
const isMetadataModalOpen = ref(false);
const metadataLayer = ref<GeoportalLayerItem | null>(null);

function viewMetadata(layer: GeoportalLayerItem) {
  metadataLayer.value = layer;
  isMetadataModalOpen.value = true;
}
</script>

<template>
  <div class="space-y-3">
    <!-- Header: Title & Total Count -->
    <div class="flex items-center justify-between px-1">
      <div class="flex items-center gap-1.5">
        <UIcon name="i-lucide-globe-2" class="size-4 text-blue-600 dark:text-blue-400" />
        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
          Simpul Jaringan
        </span>
      </div>
      <div class="flex items-center gap-1">
        <UBadge
          :label="`${totalFilteredCount} Layer`"
          size="xs"
          color="info"
          variant="subtle"
          class="font-mono text-[10px]"
        />
        <button
          type="button"
          class="p-1 rounded-md text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
          title="Muat Ulang Katalog"
          @click="() => refresh()"
        >
          <UIcon name="i-heroicons-arrow-path" class="size-3.5" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Simpul Jaringan Geoportal Identity Card -->
    <div class="p-3 rounded-xl bg-gradient-to-br from-blue-50/90 via-slate-50 to-indigo-50/70 dark:from-blue-950/40 dark:via-[#0c1424] dark:to-indigo-950/30 border border-blue-200/80 dark:border-blue-900/40 shadow-xs space-y-2">
      <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
          <div class="size-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs ring-2 ring-blue-500/20">
            <UIcon name="i-lucide-globe" class="size-4.5" />
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
                JIGN Daerah Kab. Bojonegoro
              </span>
              <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[9px] font-semibold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                <span class="size-1.5 rounded-full bg-emerald-500" />
                Online
              </span>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium truncate">
              Pemerintah Kabupaten Bojonegoro
            </p>
          </div>
        </div>

        <a
          href="https://geoportal.bojonegorokab.go.id"
          target="_blank"
          rel="noopener noreferrer"
          class="shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-white/80 dark:hover:bg-white/10 transition-colors"
          title="Kunjungi Portal Resmi geoportal.bojonegorokab.go.id"
          aria-label="Kunjungi Portal Resmi geoportal.bojonegorokab.go.id"
        >
          <UIcon name="i-lucide-external-link" class="size-3.5" />
        </a>
      </div>

      <div class="flex items-center gap-1.5 pt-1.5 border-t border-blue-100/80 dark:border-white/5 text-[10px] text-slate-500 dark:text-slate-400">
        <span class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-white/5 font-mono font-medium border border-blue-100 dark:border-white/5">CSW 2.0.2</span>
        <span class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-white/5 font-mono font-medium border border-blue-100 dark:border-white/5">WMS 1.1.1</span>
        <span class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-white/5 font-mono font-medium border border-blue-100 dark:border-white/5">JIGN Terhubung</span>
      </div>
    </div>

    <!-- Quick Search Input -->
    <div class="relative">
      <UIcon
        name="i-heroicons-magnifying-glass"
        class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400"
      />
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Cari layer Geoportal (batas, jalan, irigasi)..."
        class="w-full h-8 pl-8 pr-7 rounded-lg bg-slate-50 dark:bg-white/[0.03] border border-slate-200/80 dark:border-white/10 text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
      />
      <button
        v-if="searchQuery"
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
        @click="searchQuery = ''"
      >
        <UIcon name="i-lucide-x" class="size-3" />
      </button>
    </div>

    <!-- Category Pills Filter (Horizontal Scroll) -->
    <div v-if="categories.length > 1" class="flex items-center gap-1 overflow-x-auto pb-1 text-xs no-scrollbar">
      <button
        type="button"
        class="shrink-0 px-2 py-0.5 rounded-md text-[11px] font-medium transition-colors cursor-pointer"
        :class="selectedCategory === 'ALL'
          ? 'bg-blue-600 text-white font-semibold'
          : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10'"
        @click="selectedCategory = 'ALL'"
      >
        Semua
      </button>
      <button
        v-for="cat in categories"
        :key="cat.name"
        type="button"
        class="shrink-0 px-2 py-0.5 rounded-md text-[11px] font-medium transition-colors cursor-pointer flex items-center gap-1"
        :class="selectedCategory === cat.name
          ? 'bg-blue-600 text-white font-semibold'
          : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10'"
        @click="selectedCategory = cat.name"
      >
        <span>{{ cat.name }}</span>
        <span class="text-[9px] opacity-75 font-mono">({{ cat.count }})</span>
      </button>
    </div>

    <!-- Loading Skeleton State -->
    <div v-if="loading && categories.length === 0" class="space-y-2 py-2">
      <div v-for="i in 3" :key="i" class="h-10 rounded-xl bg-slate-100 dark:bg-white/5 animate-pulse" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="Object.keys(filteredGrouped).length === 0"
      class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-white/10 text-center space-y-1.5"
    >
      <UIcon name="i-lucide-search-x" class="size-5 text-slate-400 mx-auto" />
      <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Layer tidak ditemukan</p>
      <p class="text-[11px] text-slate-400">Coba ubah kata kunci pencarian Anda.</p>
    </div>

    <!-- Accordion Category Groups -->
    <div v-else class="space-y-2">
      <div
        v-for="(layers, catName) in filteredGrouped"
        :key="catName"
        class="rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50/40 dark:bg-white/[0.01] overflow-hidden"
      >
        <!-- Category Accordion Header -->
        <button
          type="button"
          class="w-full flex items-center justify-between px-3 py-2 text-left bg-slate-100/60 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.05] transition-colors cursor-pointer"
          @click="toggleCategory(catName)"
        >
          <div class="flex items-center gap-2 min-w-0">
            <UIcon
              :name="expandedCategories[catName] !== false ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
              class="size-3.5 text-slate-400 shrink-0 transition-transform"
            />
            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">
              {{ catName }}
            </span>
          </div>
          <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-slate-200/80 dark:bg-white/10 text-slate-600 dark:text-slate-400 shrink-0">
            {{ layers.length }}
          </span>
        </button>

        <!-- Category Layers List -->
        <div v-if="expandedCategories[catName] !== false" class="p-2 space-y-2 divide-y divide-slate-100 dark:divide-white/5">
          <div
            v-for="layer in layers"
            :key="layer.id"
            class="pt-2 first:pt-0 space-y-2"
          >
            <!-- Layer Row Header -->
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-start gap-2 min-w-0 flex-1">
                <!-- Toggle Checkbox / Switch -->
                <button
                  type="button"
                  class="mt-0.5 size-4 rounded flex items-center justify-center border transition-all cursor-pointer shrink-0"
                  :class="isLayerActive(layer.id)
                    ? 'bg-blue-600 border-blue-600 text-white shadow-xs'
                    : 'border-slate-300 dark:border-white/20 bg-white dark:bg-slate-900 hover:border-blue-400'"
                  :aria-checked="isLayerActive(layer.id)"
                  role="checkbox"
                  @click="handleToggle(layer)"
                >
                  <UIcon v-if="isLayerActive(layer.id)" name="i-lucide-check" class="size-3 stroke-[3]" />
                </button>

                <!-- Layer Title & OPD -->
                <div class="min-w-0 flex-1">
                  <p
                    class="text-xs font-semibold leading-tight cursor-pointer hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                    :class="isLayerActive(layer.id) ? 'text-blue-600 dark:text-blue-400 font-bold' : 'text-slate-800 dark:text-slate-200'"
                    @click="handleToggle(layer)"
                  >
                    {{ layer.name }}
                  </p>
                  <p v-if="layer.opd_name || layer.attribution" class="text-[10px] text-slate-400 truncate mt-0.5">
                    {{ layer.opd_name || layer.attribution }}
                  </p>
                </div>
              </div>

              <!-- Metadata Info Button -->
              <button
                type="button"
                class="p-1 rounded-md text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/5 cursor-pointer shrink-0"
                title="Lihat Metadata Resmi"
                @click="viewMetadata(layer)"
              >
                <UIcon name="i-lucide-info" class="size-3.5" />
              </button>
            </div>

            <!-- Active Layer Controls: Opacity & Legend Preview -->
            <div
              v-if="isLayerActive(layer.id)"
              class="pl-6 space-y-2 pt-1 border-t border-slate-100 dark:border-white/5"
            >
              <!-- Opacity Slider -->
              <div class="space-y-1">
                <div class="flex items-center justify-between text-[10px] font-mono text-slate-500">
                  <span class="flex items-center gap-1">
                    <UIcon name="i-lucide-sliders" class="size-3" />
                    <span>Transparansi</span>
                  </span>
                  <span class="font-bold text-slate-700 dark:text-slate-300">
                    {{ Math.round(getLayerOpacity(layer.id) * 100) }}%
                  </span>
                </div>
                <input
                  type="range"
                  min="0.1"
                  max="1"
                  step="0.05"
                  :value="getLayerOpacity(layer.id)"
                  class="w-full h-1 bg-slate-200 dark:bg-white/10 rounded-lg appearance-none cursor-pointer accent-blue-600"
                  @input="(e: any) => handleOpacityChange(layer.id, parseFloat(e.target.value))"
                />
              </div>

              <!-- Official Legend Graphic -->
              <div v-if="layer.legend_url" class="space-y-1">
                <span class="text-[10px] font-semibold text-slate-400 flex items-center gap-1">
                  <UIcon name="i-lucide-list" class="size-3" />
                  <span>Simbol Legenda:</span>
                </span>
                <div class="p-1.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-white/5 inline-block max-w-full overflow-hidden">
                  <img
                    :src="layer.legend_url"
                    :alt="`Legenda ${layer.name}`"
                    class="max-h-24 object-contain max-w-full"
                    loading="lazy"
                    onerror="this.parentElement.style.display='none'"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Metadata Modal -->
    <GeoportalMetadataModal
      v-model:open="isMetadataModalOpen"
      :layer="metadataLayer"
    />
  </div>
</template>

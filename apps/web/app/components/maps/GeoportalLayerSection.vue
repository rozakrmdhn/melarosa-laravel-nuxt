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

const toast = useToast();
const { can } = usePermission();
const canSyncGeoportal = computed(() => can("layers-manage") || can("layers-create") || can("layers-update"));
const syncing = ref(false);

const {
  groupedLayers,
  categories,
  filteredGrouped,
  totalFilteredCount,
  searchQuery,
  selectedCategory,
  loading,
  refresh,
  connectionStatus,
  checkingStatus,
  checkConnection,
} = useGeoportalLayers();

async function handleFullRefresh() {
  await Promise.all([
    refresh(),
    checkConnection(true),
  ]);
}

async function handleQuickSync() {
  if (syncing.value) return;
  syncing.value = true;
  try {
    const res = await $http<{
      ok: boolean;
      message: string;
      data?: {
        records_found: number;
        records_inserted: number;
        records_updated: number;
        duration_seconds: number;
      };
    }>("admin/layers/geoportal/sync", {
      method: "POST",
    });

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: "Sinkronisasi Berhasil",
        description: res.message || "Katalog Geoportal berhasil diperbarui dari server pusat.",
        color: "success",
      });
      await handleFullRefresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message ||
      err?.message ||
      "Gagal menyinkronkan data katalog Geoportal.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Sinkronisasi Gagal",
      description: msg,
      color: "error",
    });
  } finally {
    syncing.value = false;
  }
}

function formatLastSync(iso?: string | null): string {
  if (!iso) return "Belum pernah disinkronkan";
  const date = new Date(iso);
  if (isNaN(date.getTime())) return "-";
  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

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
  return (props.activeLayers || []).some((l) => l.id === layerId);
}

function getLayerOpacity(layerId: string, defaultOpacity: number = 0.85): number {
  const match = (props.activeLayers || []).find((l) => l.id === layerId);
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
          title="Muat Ulang Katalog & Status Koneksi"
          @click="handleFullRefresh"
        >
          <UIcon name="i-heroicons-arrow-path" class="size-3.5" :class="{ 'animate-spin': loading || checkingStatus }" />
        </button>
      </div>
    </div>

    <!-- Simpul Jaringan Geoportal Identity & Connection Card -->
    <div
      class="p-3 rounded-xl border shadow-xs space-y-2 transition-colors"
      :class="[
        connectionStatus?.status === 'online'
          ? 'bg-gradient-to-br from-emerald-50/70 via-slate-50 to-blue-50/50 dark:from-emerald-950/30 dark:via-[#0c1424] dark:to-blue-950/20 border-emerald-200/80 dark:border-emerald-900/40'
          : connectionStatus?.status === 'fallback'
          ? 'bg-gradient-to-br from-amber-50/80 via-slate-50 to-orange-50/50 dark:from-amber-950/30 dark:via-[#0c1424] dark:to-orange-950/20 border-amber-200/80 dark:border-amber-900/40'
          : 'bg-gradient-to-br from-red-50/70 via-slate-50 to-slate-100/50 dark:from-red-950/30 dark:via-[#0c1424] dark:to-slate-900/40 border-red-200/80 dark:border-red-900/40'
      ]"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
          <div
            class="size-8 rounded-lg text-white flex items-center justify-center shrink-0 shadow-xs"
            :class="[
              connectionStatus?.status === 'online'
                ? 'bg-emerald-600 ring-2 ring-emerald-500/20'
                : connectionStatus?.status === 'fallback'
                ? 'bg-amber-500 ring-2 ring-amber-500/20'
                : 'bg-red-600 ring-2 ring-red-500/20'
            ]"
          >
            <UIcon name="i-lucide-globe" class="size-4.5" />
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
                JIGN Daerah Kab. Bojonegoro
              </span>

              <!-- Status Pill Lamp -->
              <span
                class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[9px] font-semibold"
                :class="[
                  connectionStatus?.status === 'online'
                    ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300'
                    : connectionStatus?.status === 'fallback'
                    ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300'
                    : 'bg-red-100 dark:bg-red-950/80 text-red-700 dark:text-red-300'
                ]"
              >
                <span class="relative flex size-1.5">
                  <span
                    v-if="connectionStatus?.status === 'online'"
                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
                  />
                  <span
                    class="relative inline-flex rounded-full size-1.5"
                    :class="[
                      connectionStatus?.status === 'online'
                        ? 'bg-emerald-500'
                        : connectionStatus?.status === 'fallback'
                        ? 'bg-amber-500'
                        : 'bg-red-500'
                    ]"
                  />
                </span>
                <span>
                  {{
                    checkingStatus
                      ? 'Cek...'
                      : connectionStatus?.status === 'online'
                      ? 'Terhubung (Online)'
                      : connectionStatus?.status === 'fallback'
                      ? 'Data Cadangan'
                      : 'Terputus'
                  }}
                </span>
              </span>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium truncate">
              Pemerintah Kabupaten Bojonegoro
            </p>
          </div>
        </div>

        <div class="flex items-center gap-1 shrink-0">
          <button
            type="button"
            class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-white/80 dark:hover:bg-white/10 transition-colors cursor-pointer"
            title="Cek status koneksi"
            :disabled="checkingStatus"
            @click="() => checkConnection(true)"
          >
            <UIcon name="i-heroicons-arrow-path" class="size-3.5" :class="{ 'animate-spin': checkingStatus }" />
          </button>
          <a
            href="https://geoportal.bojonegorokab.go.id"
            target="_blank"
            rel="noopener noreferrer"
            class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-white/80 dark:hover:bg-white/10 transition-colors"
            title="Kunjungi Portal Resmi geoportal.bojonegorokab.go.id"
            aria-label="Kunjungi Portal Resmi geoportal.bojonegorokab.go.id"
          >
            <UIcon name="i-lucide-external-link" class="size-3.5" />
          </a>
        </div>
      </div>

      <!-- Detail chips & actions -->
      <div class="flex items-center gap-1.5 pt-1.5 border-t border-slate-200/60 dark:border-white/5 text-[10px] text-slate-500 dark:text-slate-400 flex-wrap">
        <span class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-white/5 font-mono font-medium border border-slate-200/60 dark:border-white/5">CSW 2.0.2</span>
        <span class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-white/5 font-mono font-medium border border-slate-200/60 dark:border-white/5">WMS 1.1.1</span>
        <span
          v-if="connectionStatus?.response_time_ms && connectionStatus.status === 'online'"
          class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-white/5 tabular-nums font-semibold border border-slate-200/60 dark:border-white/5 text-emerald-600 dark:text-emerald-400"
        >
          {{ connectionStatus.response_time_ms }}ms
        </span>
        <span class="truncate ml-auto text-[9px] text-slate-400">
          Sinkron: {{ formatLastSync(connectionStatus?.last_synced_at) }}
        </span>

        <!-- Admin Quick Sync Button -->
        <button
          v-if="canSyncGeoportal"
          type="button"
          class="w-full mt-1 px-2 py-1 rounded-lg text-[10px] font-semibold bg-blue-600 hover:bg-blue-700 active:scale-98 text-white flex items-center justify-center gap-1.5 cursor-pointer transition-all shadow-xs"
          :disabled="syncing"
          title="Sinkronisasi ulang daftar data dari Geoportal secara manual (Khusus Admin)"
          @click="handleQuickSync"
        >
          <UIcon name="i-lucide-refresh-cw" class="size-3" :class="{ 'animate-spin': syncing }" />
          <span>{{ syncing ? 'Menyinkronkan Katalog...' : 'Sinkronisasi Ulang Katalog' }}</span>
        </button>
      </div>
    </div>

    <!-- Alert / Pesan Pemberitahuan Ringan jika Geoportal Gangguan / Fallback -->
    <div
      v-if="connectionStatus?.status === 'fallback'"
      class="p-2.5 rounded-xl border border-amber-300/80 dark:border-amber-800/60 bg-amber-50/90 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 space-y-1 text-xs"
    >
      <div class="flex items-start gap-2">
        <UIcon name="i-lucide-alert-triangle" class="size-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
        <div class="min-w-0">
          <p class="font-bold text-[11px] leading-tight">
            Pemberitahuan: Menggunakan Data Cadangan
          </p>
          <p class="text-[10px] text-amber-800/90 dark:text-amber-300/90 mt-0.5 leading-relaxed">
            Layanan live Geoportal Bojonegoro sedang mengalami gangguan atau lambat. Melarosa menyajikan data katalog cadangan lokal ({{ connectionStatus?.total_local_layers ?? totalFilteredCount }} layer) agar peta tetap berjalan normal.
          </p>
        </div>
      </div>
    </div>

    <!-- Alert jika status Offline / Terputus sama sekali -->
    <div
      v-else-if="connectionStatus?.status === 'offline'"
      class="p-2.5 rounded-xl border border-red-300/80 dark:border-red-800/60 bg-red-50/90 dark:bg-red-950/40 text-red-900 dark:text-red-200 space-y-1 text-xs"
    >
      <div class="flex items-start gap-2">
        <UIcon name="i-lucide-wifi-off" class="size-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
        <div class="min-w-0">
          <p class="font-bold text-[11px] leading-tight">
            Koneksi Simpul Jaringan Terputus
          </p>
          <p class="text-[10px] text-red-800/90 dark:text-red-300/90 mt-0.5 leading-relaxed">
            Tidak dapat menghubungi server Geoportal Bojonegoro. Silakan periksa koneksi internet Anda atau coba periksa kembali status koneksi.
          </p>
        </div>
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
        <span class="text-[9px] opacity-75 font-semibold tabular-nums">({{ cat.count }})</span>
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
          <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold tabular-nums bg-slate-200/80 dark:bg-white/10 text-slate-600 dark:text-slate-400 shrink-0">
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

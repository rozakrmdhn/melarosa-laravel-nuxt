<script setup lang="ts">
import { computed } from "vue";
import type { KecamatanEntry, DatasetResultItem } from "~/components/maps/LeftPanel.vue";
import type { GeoparkSite } from "~/composables/useGeoparkSites";
import { formatDMS, getKecamatanFromCoordinate, type ParsedCoordinate } from "~/utils/gis-helpers";

const props = defineProps<{
  filteredKecamatanResults: KecamatanEntry[];
  filteredDatasetResults: DatasetResultItem[];
  filteredGeoparkResults: GeoparkSite[];
  totalSearchResultsCount: number;
  universalSearchQuery: string;
  parsedCoordinateResult?: ParsedCoordinate | null;
}>();

const emit = defineEmits<{
  (e: "select-kecamatan", k: KecamatanEntry): void;
  (e: "select-dataset", d: DatasetResultItem): void;
  (e: "select-geopark", site: GeoparkSite): void;
  (e: "select-coordinate", coord: ParsedCoordinate): void;
  (e: "close-search"): void;
}>();

const coordinateDMS = computed(() => {
  if (!props.parsedCoordinateResult) return "";
  return formatDMS(props.parsedCoordinateResult.lat, props.parsedCoordinateResult.lng);
});

const nearbyKecamatan = computed(() => {
  if (!props.parsedCoordinateResult) return null;
  return getKecamatanFromCoordinate(props.parsedCoordinateResult.lat, props.parsedCoordinateResult.lng);
});
</script>

<template>
  <div class="flex-1 min-h-0 overflow-y-auto px-4 sm:px-5 py-3 space-y-4">
    <!-- 0. Kategori Titik Koordinat (WGS 84) - Prioritas Utama saat terdeteksi -->
    <div v-if="props.parsedCoordinateResult" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
          <UIcon name="i-lucide-crosshair" class="size-4" />
          <span>Hasil Pencarian Koordinat</span>
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
          WGS 84
        </span>
      </div>

      <div class="space-y-1.5">
        <button
          type="button"
          class="w-full flex items-center justify-between p-3 rounded-2xl text-left transition-all cursor-pointer bg-blue-50/80 dark:bg-blue-950/40 hover:bg-blue-100/90 dark:hover:bg-blue-900/50 border border-blue-200/80 dark:border-blue-800/60 hover:border-blue-400 dark:hover:border-blue-500 group min-h-[52px] shadow-2xs"
          @click="emit('select-coordinate', props.parsedCoordinateResult)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 rounded-full flex items-center justify-center shrink-0 bg-blue-600 text-white shadow-xs group-hover:scale-105 transition-transform">
              <UIcon name="i-lucide-navigation" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 truncate tabular-nums">
                {{ props.parsedCoordinateResult.formatted }}
              </p>
              <p class="text-xs text-slate-600 dark:text-slate-300 truncate mt-0.5 tabular-nums">
                {{ coordinateDMS }}
                <span v-if="nearbyKecamatan" class="text-blue-600 dark:text-blue-400 font-semibold">
                  · Kec. {{ nearbyKecamatan.name }}
                </span>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0 pl-1.5">
            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-600 text-white shadow-2xs">
              Fly To Peta
            </span>
            <UIcon name="i-lucide-chevron-right" class="size-4 text-blue-500 group-hover:translate-x-0.5 transition-transform" />
          </div>
        </button>
      </div>
    </div>
    <!-- 1. Kategori Wilayah & Kecamatan -->
    <div v-if="props.filteredKecamatanResults.length > 0" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
          Wilayah & Kecamatan
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
          {{ props.filteredKecamatanResults.length }} wilayah
        </span>
      </div>

      <div class="space-y-1.5">
        <button
          v-for="k in props.filteredKecamatanResults"
          :key="k.name"
          type="button"
          class="w-full flex items-center justify-between p-2.5 rounded-2xl text-left transition-all cursor-pointer bg-slate-50/70 dark:bg-white/[0.02] hover:bg-slate-100 dark:hover:bg-white/[0.05] border border-slate-200/60 dark:border-white/5 hover:border-blue-400 dark:hover:border-blue-600 group min-h-[48px]"
          @click="emit('select-kecamatan', k)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 rounded-full flex items-center justify-center shrink-0 bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-900/50 group-hover:scale-105 transition-transform">
              <UIcon name="i-lucide-map-pin" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 truncate">
                Kecamatan {{ k.name }}
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Zona {{ k.zona }} · {{ k.desc }}
              </p>
            </div>
          </div>
          <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-300 dark:text-slate-600 group-hover:text-blue-500 transition-colors shrink-0 pl-1" />
        </button>
      </div>
    </div>

    <!-- 2. Kategori Dataset & Layer Spasial -->
    <div v-if="props.filteredDatasetResults.length > 0" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
          Dataset & Layer Spasial
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
          {{ props.filteredDatasetResults.length }} dataset
        </span>
      </div>

      <div class="space-y-1.5">
        <button
          v-for="d in props.filteredDatasetResults"
          :key="d.id"
          type="button"
          class="w-full flex items-center justify-between p-2.5 rounded-2xl text-left transition-all cursor-pointer bg-slate-50/70 dark:bg-white/[0.02] hover:bg-slate-100 dark:hover:bg-white/[0.05] border border-slate-200/60 dark:border-white/5 hover:border-emerald-400 dark:hover:border-emerald-600 group min-h-[48px]"
          @click="emit('select-dataset', d)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 rounded-full flex items-center justify-center shrink-0 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-900/50 group-hover:scale-105 transition-transform">
              <UIcon name="i-lucide-layers-2" class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5 min-w-0">
                <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 truncate">
                  {{ d.title }}
                </p>
                <span
                  v-if="d.slug"
                  class="px-1.5 py-0.2 rounded-md text-xs font-medium bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 border border-slate-200/70 dark:border-white/10 shrink-0 truncate max-w-[130px]"
                  :title="d.slug"
                >
                  {{ d.slug }}
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                {{ d.subtitle }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0 pl-1">
            <span
              v-if="d.isActive"
              class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300"
            >
              Aktif
            </span>
            <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 transition-colors" />
          </div>
        </button>
      </div>
    </div>

    <!-- 3. Kategori Geosite & Geopark Bojonegoro (Menggantikan dummy landmark) -->
    <div v-if="props.filteredGeoparkResults.length > 0" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
          Geosite & Geopark Bojonegoro
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
          {{ props.filteredGeoparkResults.length }} situs
        </span>
      </div>

      <div class="space-y-1.5">
        <button
          v-for="site in props.filteredGeoparkResults"
          :key="site.id"
          type="button"
          class="w-full flex items-center justify-between p-2.5 rounded-2xl text-left transition-all cursor-pointer bg-slate-50/70 dark:bg-white/[0.02] hover:bg-slate-100 dark:hover:bg-white/[0.05] border border-slate-200/60 dark:border-white/5 hover:border-emerald-400 dark:hover:border-emerald-600 group min-h-[48px]"
          @click="emit('select-geopark', site)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="size-9 rounded-full flex items-center justify-center shrink-0 border group-hover:scale-105 transition-transform"
              :class="site.categorySlug === 'geo-diversity'
                ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border-blue-200/60 dark:border-blue-900/50'
                : site.categorySlug === 'bio-diversity'
                  ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-900/50'
                  : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border-amber-200/60 dark:border-amber-900/50'"
            >
              <span class="text-xs font-bold tabular-nums">{{ site.code }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 truncate">
                {{ site.title }}
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                {{ site.category }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0 pl-1">
            <span
              v-if="!site.hasCoordinate"
              class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300"
            >
              Takbenda
            </span>
            <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 transition-colors" />
          </div>
        </button>
      </div>
    </div>

    <!-- State kosong saat tidak ada hasil -->
    <div
      v-if="props.totalSearchResultsCount === 0 && !props.parsedCoordinateResult"
      class="py-10 px-3 text-center space-y-2.5"
    >
      <div class="size-12 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-search-x" class="size-6" />
      </div>
      <div class="space-y-1">
        <p class="text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300">
          Tidak ada hasil yang cocok
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400 px-4">
          Tidak ditemukan wilayah, koordinat, dataset, atau geosite untuk kata kunci "{{ props.universalSearchQuery }}".
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/80 transition-colors cursor-pointer min-h-[44px]"
        @click="emit('close-search')"
      >
        <span>Tutup dan kembali ke peta</span>
      </button>
    </div>
  </div>
</template>

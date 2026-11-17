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
    <!-- 0. Kategori Titik Lokasi - Prioritas saat format koordinat terdeteksi -->
    <div v-if="props.parsedCoordinateResult" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
          <UIcon name="i-ion-locate" class="size-4" />
          <span>Titik Lokasi Peta</span>
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
              <UIcon name="i-ion-navigate" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 truncate tabular-nums">
                {{ props.parsedCoordinateResult.formatted }}
              </p>
              <p class="text-xs text-slate-600 dark:text-slate-300 truncate mt-0.5 tabular-nums">
                <span v-if="nearbyKecamatan" class="text-blue-600 dark:text-blue-400 font-semibold">
                  Kecamatan {{ nearbyKecamatan.name }}
                </span>
                <span v-else>Kabupaten Bojonegoro</span>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0 pl-1.5">
            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-600 text-white shadow-2xs">
              Buka di Peta
            </span>
            <UIcon name="i-ion-chevron-forward" class="size-4 text-blue-500 group-hover:translate-x-0.5 transition-transform" />
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

      <div class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden">
        <button
          v-for="k in props.filteredKecamatanResults"
          :key="k.name"
          type="button"
          class="w-full flex items-center justify-between px-3.5 py-3 text-left transition-colors cursor-pointer hover:bg-slate-200/45 dark:hover:bg-white/[0.04] group min-h-[54px]"
          @click="emit('select-kecamatan', k)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 rounded-xl flex items-center justify-center shrink-0 bg-white dark:bg-[#0f172a] shadow-2xs text-blue-600 dark:text-blue-400">
              <UIcon name="i-lucide-map-pin" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <p class="text-[13px] font-bold text-slate-900 dark:text-slate-100 truncate">
                Kecamatan {{ k.name }}
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Zona {{ k.zona }} · {{ k.desc }}
              </p>
            </div>
          </div>
          <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-400 group-hover:text-blue-500 transition-colors shrink-0 pl-1" />
        </button>
      </div>
    </div>

    <!-- 2. Kategori Peta Pendukung -->
    <div v-if="props.filteredDatasetResults.length > 0" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
          Peta & Data Wilayah
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
          {{ props.filteredDatasetResults.length }} peta
        </span>
      </div>

      <div class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden">
        <button
          v-for="d in props.filteredDatasetResults"
          :key="d.id"
          type="button"
          class="w-full flex items-center justify-between px-3.5 py-3 text-left transition-colors cursor-pointer hover:bg-slate-200/45 dark:hover:bg-white/[0.04] group min-h-[54px]"
          @click="emit('select-dataset', d)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 rounded-xl flex items-center justify-center shrink-0 bg-white dark:bg-[#0f172a] shadow-2xs text-emerald-600 dark:text-emerald-400">
              <UIcon name="i-ion-map-outline" class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-[13px] font-bold text-slate-900 dark:text-slate-100 truncate">
                {{ d.title }}
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                {{ d.category || d.subtitle }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0 pl-1">
            <span
              v-if="d.isActive"
              class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300"
            >
              Aktif
            </span>
            <UIcon name="i-ion-chevron-forward" class="size-4 text-slate-400 group-hover:text-emerald-500 transition-colors" />
          </div>
        </button>
      </div>
    </div>

    <!-- 3. Kategori Wisata Geopark Bojonegoro -->
    <div v-if="props.filteredGeoparkResults.length > 0" class="space-y-2">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
          Wisata Geopark Bojonegoro
        </span>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums">
          {{ props.filteredGeoparkResults.length }} lokasi
        </span>
      </div>

      <div class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden">
        <button
          v-for="site in props.filteredGeoparkResults"
          :key="site.id"
          type="button"
          class="w-full flex items-center justify-between px-3.5 py-3 text-left transition-colors cursor-pointer hover:bg-slate-200/45 dark:hover:bg-white/[0.04] group min-h-[54px]"
          @click="emit('select-geopark', site)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="size-9 rounded-xl flex items-center justify-center shrink-0 bg-white dark:bg-[#0f172a] shadow-2xs"
              :class="site.categorySlug === 'geo-diversity'
                ? 'text-blue-600 dark:text-blue-400'
                : site.categorySlug === 'bio-diversity'
                  ? 'text-emerald-600 dark:text-emerald-400'
                  : 'text-amber-600 dark:text-amber-400'"
            >
              <span class="text-xs font-bold tabular-nums">{{ site.code }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-[13px] font-bold text-slate-900 dark:text-slate-100 truncate">
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
              class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300"
            >
              Takbenda
            </span>
            <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-400 group-hover:text-emerald-500 transition-colors" />
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
          Tidak ditemukan kecamatan, jalan, atau lokasi wisata untuk kata kunci "{{ props.universalSearchQuery }}".
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

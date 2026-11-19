<script setup lang="ts">
import { computed, ref } from "vue";
import type { ActiveLayerItem } from "~/types/map-layers";
import {
  buildMartinLegendEntries,
  getLegendGeomLabel,
  type MartinLegendLayerEntry,
} from "~/utils/mvt-legend";
import MartinSymbolSwatch from "./MartinSymbolSwatch.vue";

const props = withDefaults(
  defineProps<{
    activeLayers?: ActiveLayerItem[];
    compact?: boolean;
    showManageButton?: boolean;
  }>(),
  {
    activeLayers: () => [],
    compact: false,
    showManageButton: true,
  }
);

const emit = defineEmits<{
  (e: "open-other-layers"): void;
  (e: "select-layer", layerId: string): void;
}>();

const collapsedLayerIds = ref<Record<string, boolean>>({});

const legendEntries = computed<MartinLegendLayerEntry[]>(() =>
  buildMartinLegendEntries(props.activeLayers, { includeHidden: false })
);

function toggleLayerCollapsed(layerId: string) {
  collapsedLayerIds.value[layerId] = !collapsedLayerIds.value[layerId];
}

function isLayerCollapsed(entry: MartinLegendLayerEntry): boolean {
  return Boolean(collapsedLayerIds.value[entry.layerId]);
}
</script>

<template>
  <div
    class="rounded-2xl border border-slate-200/85 dark:border-white/10 bg-white dark:bg-[#0f172a] shadow-2xs overflow-hidden"
  >
    <!-- Header -->
    <div
      class="px-3.5 py-2.5 border-b border-slate-100 dark:border-white/[0.06] flex items-center justify-between gap-2"
    >
      <div class="flex items-center gap-2 min-w-0">
        <UIcon
          name="i-lucide-swatch-book"
          class="size-4 text-[#2B5B9E] dark:text-sky-400 shrink-0"
          aria-hidden="true"
        />
        <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
          Legenda
        </span>
        <span
          class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-white/[0.08] text-[10px] font-semibold text-slate-600 dark:text-slate-300 tabular-nums shrink-0"
        >
          {{ legendEntries.length }} Aktif
        </span>
      </div>

      <button
        v-if="showManageButton"
        type="button"
        class="text-[11px] font-semibold text-[#2B5B9E] dark:text-sky-400 hover:underline cursor-pointer shrink-0 py-1 px-1.5 -mr-1 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E]"
        @click="emit('open-other-layers')"
      >
        Atur Layer
      </button>
    </div>

    <!-- Empty State (Bila semua layer Martin dinonaktifkan) -->
    <div
      v-if="legendEntries.length === 0"
      class="px-3.5 py-4 text-center space-y-2"
    >
      <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
        Belum ada layer bersimbologi aktif di peta.
      </p>
      <button
        v-if="showManageButton"
        type="button"
        class="min-h-[38px] inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
        @click="emit('open-other-layers')"
      >
        <UIcon name="i-lucide-layers" class="size-3.5" aria-hidden="true" />
        <span>Aktifkan Layer</span>
      </button>
    </div>

    <!-- Dynamic Layer Entries (urutan tumpukan dari layer teratas ke terbawah) -->
    <div
      v-else
      class="divide-y divide-slate-100 dark:divide-white/[0.06]"
    >
      <div
        v-for="entry in legendEntries"
        :key="entry.layerId"
        class="px-3.5 py-2.5 space-y-2"
      >
        <div class="flex items-center justify-between gap-2">
          <button
            type="button"
            class="min-w-0 flex-1 flex items-center gap-1.5 text-left cursor-pointer group focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] rounded-md"
            :aria-expanded="!isLayerCollapsed(entry)"
            @click="toggleLayerCollapsed(entry.layerId)"
          >
            <UIcon
              name="i-lucide-chevron-down"
              class="size-3.5 text-slate-500 dark:text-slate-400 shrink-0 transition-transform duration-150"
              :class="{ '-rotate-90': isLayerCollapsed(entry) }"
              aria-hidden="true"
            />
            <span class="text-xs font-semibold text-slate-900 dark:text-slate-100 truncate group-hover:text-[#2B5B9E] dark:group-hover:text-sky-300 transition-colors">
              {{ entry.title }}
            </span>
          </button>

          <div class="flex items-center gap-1 shrink-0">
            <span
              v-if="entry.opacity < 1"
              class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/[0.06] text-[10px] font-semibold text-slate-600 dark:text-slate-300 tabular-nums"
              title="Transparansi layer aktif"
            >
              {{ Math.round(entry.opacity * 100) }}%
            </span>
            <span
              class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/[0.06] text-[10px] font-medium text-slate-600 dark:text-slate-300"
            >
              {{ getLegendGeomLabel(entry.geomType) }}
            </span>
          </div>
        </div>

        <div
          v-show="!isLayerCollapsed(entry)"
          class="pl-5"
          :class="
            entry.items.length >= 2 && !compact
              ? 'grid grid-cols-2 gap-x-3 gap-y-2'
              : 'space-y-1.5'
          "
        >
          <div
            v-for="item in entry.items"
            :key="item.key"
            class="flex items-center gap-2 min-w-0"
          >
            <MartinSymbolSwatch :swatch="item.swatch" />
            <span class="text-[11px] font-medium text-slate-700 dark:text-slate-200 capitalize leading-snug truncate">
              {{ item.label }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

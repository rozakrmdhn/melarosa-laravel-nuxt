<script setup lang="ts">
import {
  formatIntId,
  formatRelativeTimeId,
  type MartinStatusData,
} from "~/types/admin-dashboard";

interface GeoportalTimelineDay {
  date: string;
  label: string;
  has_log?: boolean;
  status: "success" | "failed" | "none" | string;
  sync_count?: number;
  items_synced?: number;
  duration_seconds?: number | null;
}

interface GeoportalConnectionStatus {
  status?: "online" | "fallback" | "offline" | string;
  node_name?: string;
  response_time_ms?: number | null;
  active_local_layers?: number;
  total_local_layers?: number;
  last_synced_at?: string | null;
  sync_timeline?: GeoportalTimelineDay[];
}

interface Props {
  geoportalStatus: GeoportalConnectionStatus | null;
  geoportalChecking: boolean;
  geoportalError: string | null;
  martinStatus: MartinStatusData | null;
  martinChecking: boolean;
  martinError: string | null;
  canManageLayers: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: "refresh"): void;
}>();

const selectedDayIndex = ref<number | null>(null);

const timeline = computed(() => props.geoportalStatus?.sync_timeline ?? []);

watch(
  timeline,
  (list) => {
    if (list.length > 0 && selectedDayIndex.value === null) {
      selectedDayIndex.value = list.length - 1;
    }
  },
  { immediate: true },
);

const selectedDay = computed(() => {
  if (selectedDayIndex.value === null) return null;
  return timeline.value[selectedDayIndex.value] ?? null;
});

function getStatusPillMeta(status?: string) {
  if (status === "online") {
    return {
      label: "Online",
      dotClass: "bg-emerald-600 dark:bg-emerald-400",
      badgeClass:
        "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/70",
    };
  }
  if (status === "fallback") {
    return {
      label: "Cadangan Lokal",
      dotClass: "bg-amber-600 dark:bg-amber-400",
      badgeClass:
        "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/70",
    };
  }
  return {
    label: "Terputus",
    dotClass: "bg-red-600 dark:bg-red-400",
    badgeClass:
      "bg-red-50 text-red-700 border-red-200 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800/70",
  };
}

const jignMeta = computed(() => getStatusPillMeta(props.geoportalStatus?.status));
const martinMeta = computed(() => getStatusPillMeta(props.martinStatus?.status));
</script>

<template>
  <section
    aria-labelledby="simpul-jaringan-title"
    class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col justify-between gap-4"
  >
    <!-- Header -->
    <div class="flex items-start justify-between gap-2.5">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-server" class="size-4.5 text-blue-600 dark:text-blue-400 shrink-0" />
          <h2
            id="simpul-jaringan-title"
            class="text-base font-bold text-slate-900 dark:text-slate-50 tracking-tight truncate"
          >
            Layanan Spasial & Simpul JIGN
          </h2>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
          Konektivitas tile vektor Martin dan katalog simpul geospasial.
        </p>
      </div>

      <button
        type="button"
        :disabled="geoportalChecking || martinChecking"
        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 min-h-9 sm:min-h-8 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer disabled:opacity-50 shrink-0 focus-visible:outline-2 focus-visible:outline-blue-600"
        @click="emit('refresh')"
      >
        <UIcon
          name="i-lucide-refresh-cw"
          :class="['size-3.5 shrink-0', (geoportalChecking || martinChecking) && 'animate-spin']"
        />
        <span>Periksa</span>
      </button>
    </div>

    <!-- Services Telemetry Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-3">
      <!-- Martin MVT Service -->
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800 space-y-2">
        <div class="flex items-center justify-between gap-2">
          <div class="min-w-0">
            <p class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
              Martin Vector Tile (MVT)
            </p>
            <p class="text-[11px] text-slate-600 dark:text-slate-400 truncate">
              PostGIS Real-time Streaming
            </p>
          </div>
          <span
            v-if="martinChecking"
            class="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
          >
            Memeriksa...
          </span>
          <span
            v-else
            :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold border shrink-0', martinMeta.badgeClass]"
          >
            <span :class="['size-2 rounded-full shrink-0', martinMeta.dotClass]" />
            {{ martinMeta.label }}
          </span>
        </div>

        <p v-if="martinError" class="text-xs text-red-600 dark:text-red-400">
          {{ martinError }}
        </p>
        <div
          v-else-if="martinStatus"
          class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300 tabular-nums"
        >
          <span>
            Layer aktif:
            <strong class="text-slate-900 dark:text-slate-100">
              {{ formatIntId(martinStatus.active_local_layers ?? 0) }} / {{ formatIntId(martinStatus.total_local_layers ?? 0) }}
            </strong>
          </span>
          <span v-if="martinStatus.remote_sources !== null && martinStatus.remote_sources !== undefined">
            Sumber katalog:
            <strong class="text-slate-900 dark:text-slate-100">
              {{ formatIntId(martinStatus.remote_sources) }}
            </strong>
          </span>
          <span v-if="martinStatus.response_time_ms">
            Latensi:
            <strong class="text-slate-900 dark:text-slate-100">
              {{ formatIntId(martinStatus.response_time_ms) }} ms
            </strong>
          </span>
        </div>
      </div>

      <!-- JIGN Geoportal Node -->
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800 space-y-2">
        <div class="flex items-center justify-between gap-2">
          <div class="min-w-0">
            <p class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
              {{ geoportalStatus?.node_name || "Simpul Jaringan JIGN" }}
            </p>
            <p class="text-[11px] text-slate-600 dark:text-slate-400 truncate">
              Sinkronisasi terakhir: {{ formatRelativeTimeId(geoportalStatus?.last_synced_at) }}
            </p>
          </div>
          <span
            v-if="geoportalChecking"
            class="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
          >
            Memeriksa...
          </span>
          <span
            v-else
            :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold border shrink-0', jignMeta.badgeClass]"
          >
            <span :class="['size-2 rounded-full shrink-0', jignMeta.dotClass]" />
            {{ jignMeta.label }}
          </span>
        </div>

        <p v-if="geoportalError" class="text-xs text-red-600 dark:text-red-400">
          {{ geoportalError }}
        </p>
        <div
          v-else-if="geoportalStatus"
          class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300 tabular-nums"
        >
          <span>
            Layer lokal:
            <strong class="text-slate-900 dark:text-slate-100">
              {{ formatIntId(geoportalStatus.active_local_layers ?? 0) }} / {{ formatIntId(geoportalStatus.total_local_layers ?? 0) }}
            </strong>
          </span>
          <span v-if="geoportalStatus.response_time_ms">
            Latensi:
            <strong class="text-slate-900 dark:text-slate-100">
              {{ formatIntId(geoportalStatus.response_time_ms) }} ms
            </strong>
          </span>
        </div>
      </div>
    </div>

    <!-- Accessible Interactive Sync Timeline -->
    <div v-if="timeline.length > 0" class="space-y-2 pt-1">
      <div class="flex items-center justify-between text-xs">
        <span class="font-semibold text-slate-700 dark:text-slate-300">
          Riwayat Sinkronisasi Harian
        </span>
        <span class="text-slate-600 dark:text-slate-400 tabular-nums">
          {{ timeline.length }} hari terakhir
        </span>
      </div>

      <div class="grid gap-1.5" :style="{ gridTemplateColumns: `repeat(${timeline.length}, minmax(0, 1fr))` }">
        <button
          v-for="(day, idx) in timeline"
          :key="day.date || idx"
          type="button"
          :aria-pressed="selectedDayIndex === idx"
          :aria-label="`Sinkronisasi ${day.label}: ${day.status === 'success' ? 'berhasil' : day.status === 'failed' ? 'gagal' : 'tanpa log'}`"
          class="h-10 rounded-md flex flex-col justify-end p-0.5 transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          :class="
            selectedDayIndex === idx
              ? 'ring-2 ring-blue-600 dark:ring-blue-400 bg-slate-100 dark:bg-slate-800'
              : 'hover:bg-slate-100 dark:hover:bg-slate-800/70'
          "
          @click="selectedDayIndex = idx"
        >
          <span
            class="w-full rounded-sm transition-all"
            :class="[
              day.status === 'success'
                ? 'h-6 bg-emerald-600 dark:bg-emerald-500'
                : day.status === 'failed'
                  ? 'h-4 bg-red-600 dark:bg-red-500'
                  : 'h-2 bg-slate-300 dark:bg-slate-700',
            ]"
          />
        </button>
      </div>

      <div
        v-if="selectedDay"
        class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800 text-xs flex flex-wrap items-center justify-between gap-2 tabular-nums"
      >
        <span class="font-semibold text-slate-800 dark:text-slate-200">
          {{ selectedDay.label }}
        </span>
        <span class="text-slate-600 dark:text-slate-300">
          <template v-if="selectedDay.status === 'success'">
            Berhasil
            <template v-if="selectedDay.sync_count"> ({{ formatIntId(selectedDay.sync_count) }}x proses)</template>
            <template v-if="selectedDay.items_synced"> , {{ formatIntId(selectedDay.items_synced) }} layer</template>
          </template>
          <template v-else-if="selectedDay.status === 'failed'">
            Sinkronisasi gagal pada tanggal ini
          </template>
          <template v-else>
            Tidak ada aktivitas sinkronisasi terjadwal
          </template>
        </span>
      </div>
    </div>

    <!-- Footer Link -->
    <div v-if="canManageLayers" class="pt-1 border-t border-slate-100 dark:border-slate-800/80 flex justify-end">
      <NuxtLink
        to="/admin/layers"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline min-h-8 px-1"
      >
        <span>Kelola Katalog Layer & Martin Tile</span>
        <UIcon name="i-lucide-arrow-right" class="size-3.5" />
      </NuxtLink>
    </div>
  </section>
</template>

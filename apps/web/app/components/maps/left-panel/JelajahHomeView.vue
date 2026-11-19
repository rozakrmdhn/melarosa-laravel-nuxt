<script setup lang="ts">
import { computed, ref, onMounted, watch } from "vue";
import type { ActiveLayerItem, MainTab } from "~/types/map-layers";
import { KECAMATAN_ENTRIES, type KecamatanEntry } from "~/constants/kecamatan";
import { useGeoparkSites, type GeoparkSite } from "~/composables/useGeoparkSites";
import { useMapHistory, type RecentMapItem } from "~/composables/useMapHistory";
import {
  useMapRekapOverview,
  formatDistanceMeters,
  formatPercentId,
  formatCountId,
  normalizeWilayahKey,
} from "~/composables/useMapRekapOverview";
import DynamicMartinLegend from "./DynamicMartinLegend.vue";

const props = withDefaults(
  defineProps<{
    activeLayers?: ActiveLayerItem[];
    selectedKecamatanFilter?: string | null;
    selectedDesaFilter?: string | null;
    geoparkOverlayVisible?: boolean;
  }>(),
  {
    activeLayers: () => [],
    selectedKecamatanFilter: null,
    selectedDesaFilter: null,
    geoparkOverlayVisible: false,
  }
);

const emit = defineEmits<{
  (e: "toggle-roads-visibility"): void;
  (e: "toggle-geopark-overlay"): void;
  (e: "locate-user"): void;
  (e: "zoom-to-home"): void;
  (e: "go-to-tab", tab: MainTab, opts?: { sortPriority?: boolean; category?: "jalan" | "jembatan" | "kantor" }): void;
  (e: "open-other-layers"): void;
  (e: "select-kecamatan", payload: {
    name: string;
    coordinate: [number, number];
    zoom?: number;
    bbox?: [number, number, number, number];
    isKecamatan?: boolean;
  }): void;
  (e: "filter-wilayah", kecamatan: string | null, customBbox?: [number, number, number, number] | null, desa?: string | null): void;
  (e: "select-geopark", site: GeoparkSite): void;
  (e: "open-recent", item: RecentMapItem): void;
}>();

const { sites: geoparkSites } = useGeoparkSites();
const { recentItems, ensureLoaded: ensureHistoryLoaded } = useMapHistory();
const {
  kecamatanStats,
  loadingKecamatan,
  desaCache,
  loadingDesaId,
  countySummary,
  ensureKecamatanStats,
  fetchDesaForKecamatan,
  findKecamatanStatByName,
} = useMapRekapOverview();

const showDistrictPicker = ref(false);
const districtPickerQuery = ref("");

onMounted(() => {
  ensureHistoryLoaded();
  ensureKecamatanStats();
});

const isRoadsVisible = computed(() => {
  const roadLayers = (props.activeLayers || []).filter((l) => {
    const hay = `${l.id || ""} ${l.layer_name || ""} ${l.martinSource || ""} ${l.title || ""} ${l.name || ""}`.toLowerCase();
    return hay.includes("jalan") || hay.includes("infrastruktur_segmen");
  });
  if (roadLayers.length === 0) return false;
  return roadLayers.some((l) => l.visible !== false);
});

const roadStatusLabel = computed(() => {
  if (!isRoadsVisible.value) return "Disembunyikan";
  if (countySummary.value && countySummary.value.totalRuas > 0) {
    return `${formatCountId(countySummary.value.totalRuas)} ruas`;
  }
  return "Ditampilkan";
});

const activeDistrictStat = computed(() => {
  return findKecamatanStatByName(props.selectedKecamatanFilter);
});

watch(
  () => props.selectedKecamatanFilter,
  (next, prev) => {
    if (!next && prev) {
      showDistrictPicker.value = false;
      districtPickerQuery.value = "";
    }
  }
);

watch(
  activeDistrictStat,
  (stat) => {
    if (stat) {
      fetchDesaForKecamatan(stat.id_kecamatan);
    }
  },
  { immediate: true }
);

const spotlightTitle = computed(() => {
  if (activeDistrictStat.value) {
    return `Kec. ${activeDistrictStat.value.nama_kecamatan}`;
  }
  if (props.selectedKecamatanFilter) {
    return `Kec. ${props.selectedKecamatanFilter}`;
  }
  return "Pantau Jalan Desa";
});

const spotlightSubtitle = computed(() => {
  if (activeDistrictStat.value) {
    return `${formatCountId(activeDistrictStat.value.jumlah_desa)} desa · ${formatPercentId(activeDistrictStat.value.progress)} terbangun`;
  }
  if (countySummary.value) {
    return `${formatDistanceMeters(countySummary.value.panjangDibangunMeter)} terbangun dari ${formatDistanceMeters(countySummary.value.totalPanjangMeter)}`;
  }
  return "Ringkasan pembangunan 28 kecamatan";
});

interface SpotlightRow {
  id: string;
  badge: string;
  badgeClass: string;
  name: string;
  meta: string;
  metric: string;
  metricClass: string;
  kecamatanName?: string;
}

const spotlightRows = computed<SpotlightRow[]>(() => {
  if (activeDistrictStat.value) {
    const desas = desaCache.value[activeDistrictStat.value.id_kecamatan] || [];
    const sorted = [...desas].sort((a, b) => Number(a.progress || 0) - Number(b.progress || 0));
    return sorted.slice(0, 3).map((desa, idx) => {
      const pct = Number(desa.progress || 0);
      return {
        id: String(desa.id_desa),
        badge: String(idx + 1),
        badgeClass:
          pct >= 75
            ? "bg-emerald-600 text-white"
            : pct >= 45
              ? "bg-blue-600 text-white"
              : "bg-amber-600 text-white",
        name: `Desa ${desa.nama_desa}`,
        meta: `${formatCountId(desa.jumlah_ruas)} ruas`,
        metric: formatPercentId(pct),
        metricClass:
          pct >= 75
            ? "text-emerald-700 dark:text-emerald-400"
            : pct >= 45
              ? "text-blue-700 dark:text-blue-400"
              : "text-amber-700 dark:text-amber-400",
        kecamatanName: activeDistrictStat.value?.nama_kecamatan,
      };
    });
  }

  const list = [...kecamatanStats.value];
  if (list.length === 0) return [];

  // Show top 3 highest built districts so the card highlights immediate real milestones
  const topDistricts = list
    .sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0))
    .slice(0, 3);

  return topDistricts.map((kec, idx) => {
    const pct = Number(kec.progress || 0);
    return {
      id: String(kec.id_kecamatan),
      badge: kec.nama_kecamatan.slice(0, 1).toUpperCase() || String(idx + 1),
      badgeClass:
        idx === 0
          ? "bg-emerald-600 text-white"
          : idx === 1
            ? "bg-blue-600 text-white"
            : "bg-indigo-600 text-white",
      name: `Kec. ${kec.nama_kecamatan}`,
      meta: formatDistanceMeters(kec.panjang_dibangun),
      metric: formatPercentId(pct),
      metricClass: "text-emerald-700 dark:text-emerald-400",
      kecamatanName: kec.nama_kecamatan,
    };
  });
});

const filteredPickerKecamatan = computed(() => {
  const q = districtPickerQuery.value.trim().toLowerCase();
  if (!q) return KECAMATAN_ENTRIES;
  return KECAMATAN_ENTRIES.filter((k) => k.name.toLowerCase().includes(q));
});

function handlePickKecamatan(kec: KecamatanEntry | null) {
  showDistrictPicker.value = false;
  districtPickerQuery.value = "";
  if (!kec) {
    emit("zoom-to-home");
    return;
  }
  emit("filter-wilayah", kec.name);
  emit("select-kecamatan", {
    name: `Kecamatan ${kec.name}`,
    coordinate: kec.coordinate,
    zoom: kec.zoom,
    bbox: kec.bbox,
    isKecamatan: true,
  });
}

function handleSpotlightRowClick(row: SpotlightRow) {
  if (row.kecamatanName) {
    const entry = KECAMATAN_ENTRIES.find(
      (k) => normalizeWilayahKey(k.name) === normalizeWilayahKey(row.kecamatanName)
    );
    if (entry && !activeDistrictStat.value) {
      handlePickKecamatan(entry);
      return;
    }
  }
  emit("go-to-tab", "infrastruktur", { category: "jalan" });
}

function handleJumpStarterKecamatan(name: string) {
  const entry = KECAMATAN_ENTRIES.find((k) => normalizeWilayahKey(k.name) === normalizeWilayahKey(name));
  if (entry) {
    handlePickKecamatan(entry);
  }
}

const featuredWisataSite = computed(() => {
  return (
    geoparkSites.value.find((s) => s.name.toLowerCase().includes("kayangan")) ||
    geoparkSites.value[0] ||
    null
  );
});
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 overflow-y-auto px-4 sm:px-5 pt-1 pb-5 space-y-4 select-none">
    <!-- 1. Tiga Kartu Toggle Infrastruktur Utama -->
    <div class="grid grid-cols-3 gap-2 shrink-0">
      <!-- Jalan Desa (Aktif / Toggle Peta) -->
      <button
        type="button"
        :aria-pressed="isRoadsVisible"
        class="p-2.5 rounded-2xl border text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-1.5 min-h-[96px] active:scale-[0.98]"
        :class="isRoadsVisible
          ? 'bg-blue-50/90 dark:bg-blue-950/35 border-blue-300/80 dark:border-blue-700/60 shadow-2xs'
          : 'bg-slate-100/90 dark:bg-white/[0.04] border-slate-200/70 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/[0.07]'"
        @click="emit('toggle-roads-visibility')"
      >
        <div
          class="size-11 rounded-2xl flex items-center justify-center transition-transform"
          :class="isRoadsVisible
            ? 'bg-blue-600 text-white shadow-xs'
            : 'bg-white dark:bg-white/10 text-slate-600 dark:text-slate-300 border border-slate-200/70 dark:border-white/10'"
        >
          <UIcon name="i-ion-trail-sign" class="size-5" />
        </div>
        <div class="space-y-0.5 min-w-0 w-full">
          <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
            Jalan Desa
          </p>
          <p class="text-[11px] font-medium text-slate-600 dark:text-slate-300 flex items-center justify-center gap-1 truncate tabular-nums">
            <span
              class="size-1.5 rounded-full shrink-0"
              :class="isRoadsVisible ? 'bg-emerald-500' : 'bg-slate-400'"
            />
            <span class="truncate">{{ roadStatusLabel }}</span>
          </p>
        </div>
      </button>

      <!-- Jembatan Desa (Sedang Dikembangkan) -->
      <button
        type="button"
        title="Fitur sebaran jembatan desa sedang dalam tahap pengembangan"
        class="p-2.5 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-100/80 dark:bg-white/[0.04] hover:bg-amber-50/70 dark:hover:bg-amber-950/25 hover:border-amber-300/80 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-1.5 min-h-[96px] active:scale-[0.98]"
        @click="emit('go-to-tab', 'infrastruktur', { category: 'jembatan' })"
      >
        <div class="size-11 rounded-2xl bg-amber-100 dark:bg-amber-950/70 text-amber-700 dark:text-amber-300 flex items-center justify-center">
          <UIcon name="i-ion-construct" class="size-5" />
        </div>
        <div class="space-y-0.5 min-w-0 w-full">
          <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
            Jembatan
          </p>
          <p class="text-[10px] font-semibold text-amber-800 dark:text-amber-300 flex items-center justify-center gap-1 truncate">
            <span class="size-1.5 rounded-full bg-amber-500 shrink-0" />
            <span class="truncate">Dikembangkan</span>
          </p>
        </div>
      </button>

      <!-- Kantor Desa (Sedang Dikembangkan) -->
      <button
        type="button"
        title="Fitur sebaran kantor desa sedang dalam tahap pengembangan"
        class="p-2.5 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-100/80 dark:bg-white/[0.04] hover:bg-emerald-50/70 dark:hover:bg-emerald-950/25 hover:border-emerald-300/80 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-1.5 min-h-[96px] active:scale-[0.98]"
        @click="emit('go-to-tab', 'infrastruktur', { category: 'kantor' })"
      >
        <div class="size-11 rounded-2xl bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300 flex items-center justify-center">
          <UIcon name="i-ion-business" class="size-5" />
        </div>
        <div class="space-y-0.5 min-w-0 w-full">
          <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
            Kantor Desa
          </p>
          <p class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-300 flex items-center justify-center gap-1 truncate">
            <span class="size-1.5 rounded-full bg-emerald-500 shrink-0" />
            <span class="truncate">Dikembangkan</span>
          </p>
        </div>
      </button>
    </div>

    <!-- 2. Lima Aksi Cepat -->
    <div class="grid grid-cols-5 gap-1 shrink-0">
      <button
        type="button"
        class="flex flex-col items-center gap-1 py-1 px-0.5 rounded-xl hover:bg-slate-100/80 dark:hover:bg-white/[0.04] transition-colors cursor-pointer min-h-[44px]"
        @click="emit('locate-user')"
      >
        <span class="size-11 rounded-2xl bg-slate-100 dark:bg-white/[0.06] border border-slate-200/70 dark:border-white/10 flex items-center justify-center text-slate-800 dark:text-slate-100">
          <UIcon name="i-ion-navigate" class="size-5" />
        </span>
        <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300 leading-tight text-center">
          Sekitar Saya
        </span>
      </button>

      <button
        type="button"
        class="flex flex-col items-center gap-1 py-1 px-0.5 rounded-xl hover:bg-slate-100/80 dark:hover:bg-white/[0.04] transition-colors cursor-pointer min-h-[44px]"
        @click="emit('zoom-to-home')"
      >
        <span class="size-11 rounded-2xl bg-sky-100/90 dark:bg-sky-950/60 border border-sky-200/80 dark:border-sky-800/50 flex items-center justify-center text-sky-700 dark:text-sky-300">
          <UIcon name="i-ion-map" class="size-5" />
        </span>
        <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300 leading-tight text-center">
          Bojonegoro
        </span>
      </button>

      <button
        type="button"
        class="flex flex-col items-center gap-1 py-1 px-0.5 rounded-xl hover:bg-slate-100/80 dark:hover:bg-white/[0.04] transition-colors cursor-pointer min-h-[44px]"
        @click="emit('go-to-tab', 'infrastruktur', { sortPriority: true, category: 'jalan' })"
      >
        <span class="size-11 rounded-2xl bg-amber-100/90 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-800/50 flex items-center justify-center text-amber-700 dark:text-amber-300">
          <UIcon name="i-ion-alert-circle" class="size-5" />
        </span>
        <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300 leading-tight text-center">
          Prioritas
        </span>
      </button>

      <button
        type="button"
        :aria-pressed="props.geoparkOverlayVisible"
        class="flex flex-col items-center gap-1 py-1 px-0.5 rounded-xl hover:bg-slate-100/80 dark:hover:bg-white/[0.04] transition-colors cursor-pointer min-h-[44px]"
        @click="emit('toggle-geopark-overlay')"
      >
        <span
          class="size-11 rounded-2xl border flex items-center justify-center transition-colors"
          :class="props.geoparkOverlayVisible
            ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
            : 'bg-emerald-100/90 dark:bg-emerald-950/60 border-emerald-200/80 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-300'"
        >
          <UIcon name="i-ion-earth" class="size-5" />
        </span>
        <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300 leading-tight text-center">
          Wisata
        </span>
      </button>

      <button
        type="button"
        class="flex flex-col items-center gap-1 py-1 px-0.5 rounded-xl hover:bg-slate-100/80 dark:hover:bg-white/[0.04] transition-colors cursor-pointer min-h-[44px]"
        @click="emit('open-other-layers')"
      >
        <span class="size-11 rounded-2xl bg-slate-100 dark:bg-white/[0.06] border border-slate-200/70 dark:border-white/10 flex items-center justify-center text-slate-800 dark:text-slate-100">
          <UIcon name="i-ion-grid" class="size-5" />
        </span>
        <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300 leading-tight text-center">
          Lainnya
        </span>
      </button>
    </div>

    <!-- 3. Kartu Sorotan Wilayah ("Untukmu") -->
    <section class="space-y-2 shrink-0">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
        Untukmu
      </h2>

      <div class="rounded-2xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0f172a] p-3.5 shadow-2xs space-y-2.5">
        <button
          type="button"
          class="w-full flex items-center justify-between gap-2.5 text-left cursor-pointer min-h-[44px] rounded-xl hover:bg-slate-50 dark:hover:bg-white/[0.03] p-1 -m-1 transition-colors"
          @click="emit('go-to-tab', 'infrastruktur', { category: 'jalan' })"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="size-10 rounded-xl bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
              <UIcon name="i-ion-stats-chart" class="size-5" />
            </div>
            <div class="min-w-0">
              <span class="text-sm font-bold text-slate-900 dark:text-white truncate block">
                {{ spotlightTitle }}
              </span>
              <p class="text-xs text-slate-600 dark:text-slate-300 truncate mt-0.5 tabular-nums">
                {{ spotlightSubtitle }}
              </p>
            </div>
          </div>
          <UIcon name="i-ion-chevron-forward" class="size-4 text-slate-400 shrink-0" />
        </button>

        <!-- Daftar 3 Baris Capaian Nyata -->
        <div
          v-if="loadingKecamatan && spotlightRows.length === 0"
          class="space-y-2 pt-1"
        >
          <div v-for="i in 3" :key="i" class="h-10 rounded-xl bg-slate-100 dark:bg-white/5 animate-pulse" />
        </div>

        <div
          v-else-if="activeDistrictStat && loadingDesaId === activeDistrictStat.id_kecamatan && spotlightRows.length === 0"
          class="space-y-2 pt-1"
        >
          <div v-for="i in 3" :key="i" class="h-10 rounded-xl bg-slate-100 dark:bg-white/5 animate-pulse" />
        </div>

        <div v-else-if="spotlightRows.length > 0" class="divide-y divide-slate-100 dark:divide-white/5">
          <button
            v-for="row in spotlightRows"
            :key="row.id"
            type="button"
            class="w-full py-2 flex items-center justify-between gap-2 text-left hover:bg-slate-50 dark:hover:bg-white/[0.02] rounded-lg px-1 -mx-1 transition-colors cursor-pointer min-h-[44px]"
            @click="handleSpotlightRowClick(row)"
          >
            <div class="flex items-center gap-2 min-w-0">
              <span
                class="size-6 rounded-full text-[11px] font-bold flex items-center justify-center shrink-0"
                :class="row.badgeClass"
              >
                {{ row.badge }}
              </span>
              <span class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">
                {{ row.name }}
              </span>
            </div>

            <div class="flex items-center gap-2.5 shrink-0 tabular-nums">
              <span class="text-xs text-slate-500 dark:text-slate-400">
                {{ row.meta }}
              </span>
              <span class="text-xs font-bold min-w-[42px] text-right" :class="row.metricClass">
                {{ row.metric }}
              </span>
            </div>
          </button>
        </div>

        <!-- Inline Kecamatan Picker -->
        <div
          v-if="showDistrictPicker"
          class="pt-2 border-t border-slate-100 dark:border-white/10 space-y-2"
        >
          <input
            v-model="districtPickerQuery"
            type="text"
            placeholder="Cari nama kecamatan..."
            class="w-full h-10 px-3 rounded-xl bg-slate-100 dark:bg-white/[0.06] border border-slate-200/70 dark:border-white/10 text-base sm:text-xs text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/30"
          />
          <div class="max-h-40 overflow-y-auto space-y-1">
            <button
              type="button"
              class="w-full px-2.5 py-2 rounded-lg text-left text-xs font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer min-h-[44px] flex items-center"
              @click="handlePickKecamatan(null)"
            >
              Semua Kabupaten Bojonegoro
            </button>
            <button
              v-for="kec in filteredPickerKecamatan"
              :key="kec.name"
              type="button"
              class="w-full px-2.5 py-2 rounded-lg text-left text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 cursor-pointer min-h-[44px] flex items-center justify-between"
              @click="handlePickKecamatan(kec)"
            >
              <span>Kecamatan {{ kec.name }}</span>
              <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ kec.zona }}</span>
            </button>
          </div>
        </div>

        <!-- Footer Kartu -->
        <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 dark:border-white/5 text-xs">
          <span class="text-slate-500 dark:text-slate-400">
            Data infrastruktur daerah
          </span>
          <button
            type="button"
            class="font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer min-h-[44px] px-2 -mr-2 flex items-center"
            @click="showDistrictPicker = !showDistrictPicker"
          >
            {{ showDistrictPicker ? 'Tutup daftar' : 'Pilih kecamatan' }}
          </button>
        </div>
      </div>
    </section>

    <!-- 4. Terakhir Kamu Buka / Jelajahi Cepat -->
    <section class="space-y-2 shrink-0">
      <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        {{ recentItems.length > 0 ? 'TERAKHIR KAMU BUKA' : 'WILAYAH POPULER' }}
      </h3>

      <div v-if="recentItems.length > 0" class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-0.5">
        <button
          v-for="item in recentItems"
          :key="item.id"
          type="button"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-slate-100/90 dark:bg-white/[0.06] hover:bg-slate-200/70 dark:hover:bg-white/10 border border-slate-200/70 dark:border-white/10 text-xs font-semibold text-slate-800 dark:text-slate-100 shrink-0 cursor-pointer min-h-[44px] transition-colors"
          @click="emit('open-recent', item)"
        >
          <UIcon
            :name="item.kind === 'wisata' ? 'i-ion-earth' : 'i-ion-location'"
            class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0"
          />
          <span class="truncate max-w-[160px]">{{ item.label }}</span>
        </button>
      </div>

      <div v-else class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-0.5">
        <button
          v-for="starter in ['Bojonegoro', 'Kalitidu', 'Padangan', 'Dander']"
          :key="starter"
          type="button"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-slate-100/90 dark:bg-white/[0.06] hover:bg-slate-200/70 dark:hover:bg-white/10 border border-slate-200/70 dark:border-white/10 text-xs font-semibold text-slate-800 dark:text-slate-100 shrink-0 cursor-pointer min-h-[44px] transition-colors"
          @click="handleJumpStarterKecamatan(starter)"
        >
          <UIcon name="i-ion-location" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
          <span>Kec. {{ starter }}</span>
        </button>
      </div>
    </section>

    <!-- 5. Dari Melarosa: Ringkasan Pembangunan & Legenda Peta -->
    <section class="space-y-2.5 shrink-0">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
          Dari Melarosa
        </h3>
        <button
          type="button"
          class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
          @click="emit('open-other-layers')"
        >
          Kelola Layer
        </button>
      </div>

      <!-- Kartu 1: Progres Jalan Desa Kabupaten -->
      <div class="rounded-2xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0f172a] p-3.5 space-y-2.5 shadow-2xs">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <span class="size-8 rounded-xl bg-blue-100 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-bar-chart-3" class="size-4" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                Capaian Jalan Desa Bojonegoro
              </p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate tabular-nums">
                {{
                  countySummary
                    ? `${formatDistanceMeters(countySummary.panjangDibangunMeter)} terbangun dari ${formatDistanceMeters(countySummary.totalPanjangMeter)}`
                    : "Memuat ringkasan kabupaten..."
                }}
              </p>
            </div>
          </div>
          <span
            v-if="countySummary"
            class="px-2 py-0.5 rounded-lg bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300 text-xs font-bold tabular-nums shrink-0"
          >
            {{ formatPercentId(countySummary.progress) }}
          </span>
        </div>

        <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
          <div
            class="h-full rounded-full bg-gradient-to-r from-blue-600 to-emerald-500 transition-all duration-500"
            :style="{ width: `${Math.min(100, Math.max(4, countySummary?.progress ?? 0))}%` }"
          />
        </div>
      </div>

      <!-- Kartu 2: Legenda Dinamis Layer Martin Tile Server -->
      <DynamicMartinLegend
        :active-layers="props.activeLayers"
        @open-other-layers="emit('open-other-layers')"
      />
    </section>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

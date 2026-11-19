<script setup lang="ts">
import { computed, ref, watch, onMounted } from "vue";
import { KECAMATAN_ENTRIES } from "~/constants/kecamatan";
import type { RekapKecamatanItem, RekapDesaItem, RekapRuasItem, RekapSegmenItem } from "~/types/rekap-jalan";
import { useRekapJalanApi } from "~/composables/useRekapJalanApi";
import {
  useMapRekapOverview,
  formatDistanceMeters,
  formatPercentId,
  formatCountId,
  normalizeWilayahKey,
} from "~/composables/useMapRekapOverview";

type InfraCategory = "jalan" | "jembatan" | "kantor";
type SortMode = "nama" | "terbangun" | "prioritas";

const props = withDefaults(
  defineProps<{
    selectedKecamatanFilter?: string | null;
    selectedDesaFilter?: string | null;
    initialSortPriority?: boolean;
    initialCategory?: InfraCategory;
  }>(),
  {
    selectedKecamatanFilter: null,
    selectedDesaFilter: null,
    initialSortPriority: false,
    initialCategory: "jalan",
  }
);

const emit = defineEmits<{
  (e: "select-kecamatan", payload: {
    name: string;
    coordinate: [number, number];
    zoom?: number;
    bbox?: [number, number, number, number];
    isKecamatan?: boolean;
  }): void;
  (e: "filter-wilayah", kecamatan: string | null, customBbox?: [number, number, number, number] | null, desa?: string | null): void;
  (e: "select-road", payload: {
    ruas: RekapRuasItem;
    desaName: string;
    kecamatanName: string;
  }): void;
}>();

const {
  kecamatanStats,
  loadingKecamatan,
  errorKecamatan,
  desaCache,
  loadingDesaId,
  errorDesa,
  ruasCache,
  loadingRuasDesaId,
  errorRuas,
  countySummary,
  ensureKecamatanStats,
  fetchDesaForKecamatan,
  fetchRuasForDesa,
} = useMapRekapOverview();

const activeCategory = ref<InfraCategory>(props.initialCategory || "jalan");
const sortMode = ref<SortMode>(props.initialSortPriority ? "prioritas" : "nama");
const filterQuery = ref("");

const categoryScrollRef = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

function updateCategoryScrollButtons() {
  const el = categoryScrollRef.value;
  if (!el) {
    canScrollLeft.value = false;
    canScrollRight.value = false;
    return;
  }
  canScrollLeft.value = el.scrollLeft > 4;
  canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
}

function scrollCategoriesBy(delta: number) {
  const el = categoryScrollRef.value;
  if (!el) return;
  el.scrollBy({ left: delta, behavior: "smooth" });
}

const infraCategories: Array<{
  id: InfraCategory;
  label: string;
  icon: string;
  activeClass: string;
  dotClass?: string;
  isDeveloping?: boolean;
}> = [
  {
    id: "jalan",
    label: "Jalan Desa",
    icon: "i-ion-trail-sign",
    activeClass: "bg-blue-600 text-white border-blue-600 shadow-xs",
  },
  {
    id: "jembatan",
    label: "Jembatan Desa",
    icon: "i-ion-construct",
    activeClass: "bg-amber-600 text-white border-amber-600 shadow-xs",
    dotClass: "bg-amber-500",
    isDeveloping: true,
  },
  {
    id: "kantor",
    label: "Kantor Desa",
    icon: "i-ion-business",
    activeClass: "bg-emerald-600 text-white border-emerald-600 shadow-xs",
    dotClass: "bg-emerald-500",
    isDeveloping: true,
  },
];

const selectedDistrict = useState<RekapKecamatanItem | null>("melarosa-infra-selected-district", () => null);
const selectedDesa = useState<RekapDesaItem | null>("melarosa-infra-selected-desa", () => null);

watch(
  () => props.initialSortPriority,
  (val) => {
    if (val) {
      activeCategory.value = "jalan";
      sortMode.value = "prioritas";
      selectedDistrict.value = null;
      selectedDesa.value = null;
    }
  }
);

watch(
  () => props.initialCategory,
  (val) => {
    if (val) activeCategory.value = val;
  }
);

onMounted(async () => {
  await ensureKecamatanStats();
  syncSelectedDistrictFromFilter();
  syncSelectedDesaFromFilter();
  updateCategoryScrollButtons();
});

watch(
  () => [props.selectedKecamatanFilter, props.selectedDesaFilter],
  () => {
    syncSelectedDistrictFromFilter();
    syncSelectedDesaFromFilter();
  }
);

watch(
  () => [props.selectedKecamatanFilter, kecamatanStats.value.length],
  () => {
    if (props.selectedKecamatanFilter && !selectedDistrict.value) {
      syncSelectedDistrictFromFilter();
    }
  }
);

function syncSelectedDistrictFromFilter() {
  if (!props.selectedKecamatanFilter) {
    selectedDistrict.value = null;
    selectedDesa.value = null;
    selectedRuasYear.value = null;
    filterQuery.value = "";
    return;
  }
  const key = normalizeWilayahKey(props.selectedKecamatanFilter);
  const found = kecamatanStats.value.find((k) => normalizeWilayahKey(k.nama_kecamatan) === key);
  if (found && selectedDistrict.value?.id_kecamatan !== found.id_kecamatan) {
    selectedDistrict.value = found;
    selectedDesa.value = null;
    selectedRuasYear.value = null;
    filterQuery.value = "";
    fetchDesaForKecamatan(found.id_kecamatan);
  }
}

function syncSelectedDesaFromFilter() {
  if (!props.selectedDesaFilter) {
    if (selectedDesa.value) {
      selectedDesa.value = null;
      selectedRuasYear.value = null;
      filterQuery.value = "";
    }
    return;
  }
  if (!selectedDistrict.value) return;
  const targetKey = normalizeWilayahKey(props.selectedDesaFilter);
  const list = desaCache.value[selectedDistrict.value.id_kecamatan] || [];
  const foundDesa = list.find((d) => normalizeWilayahKey(d.nama_desa) === targetKey);
  if (foundDesa && selectedDesa.value?.id_desa !== foundDesa.id_desa) {
    selectedDesa.value = foundDesa;
    selectedRuasYear.value = null;
    filterQuery.value = "";
    fetchRuasForDesa(foundDesa.id_desa);
  }
}

watch(
  () => (selectedDistrict.value ? desaCache.value[selectedDistrict.value.id_kecamatan] : null),
  () => {
    syncSelectedDesaFromFilter();
  }
);

const filteredDistricts = computed(() => {
  const q = filterQuery.value.trim().toLowerCase();
  let list = [...kecamatanStats.value];
  if (q) {
    list = list.filter((item) => item.nama_kecamatan.toLowerCase().includes(q));
  }
  if (sortMode.value === "terbangun") {
    return list.sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0));
  }
  if (sortMode.value === "prioritas") {
    return list.sort((a, b) => Number(a.progress || 0) - Number(b.progress || 0));
  }
  return list.sort((a, b) => a.nama_kecamatan.localeCompare(b.nama_kecamatan, "id"));
});

const currentDesaList = computed<RekapDesaItem[]>(() => {
  if (!selectedDistrict.value) return [];
  return desaCache.value[selectedDistrict.value.id_kecamatan] || [];
});

const filteredDesas = computed(() => {
  const q = filterQuery.value.trim().toLowerCase();
  let list = [...currentDesaList.value];
  if (q) {
    list = list.filter((d) => d.nama_desa.toLowerCase().includes(q));
  }
  if (sortMode.value === "terbangun") {
    return list.sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0));
  }
  if (sortMode.value === "prioritas") {
    return list.sort((a, b) => Number(a.progress || 0) - Number(b.progress || 0));
  }
  return list.sort((a, b) => a.nama_desa.localeCompare(b.nama_desa, "id"));
});

const rekapApi = useRekapJalanApi();
const desaSegmenCache = ref<Record<string, Array<RekapSegmenItem & { ruasId: string }>>>({});
const loadingDesaSegmenId = ref<string | null>(null);

async function ensureSegmenForDesa(idDesa: string | number, ruasList: RekapRuasItem[]) {
  const key = String(idDesa || "");
  if (!key || Array.isArray(desaSegmenCache.value[key]) || ruasList.length === 0) return;

  loadingDesaSegmenId.value = key;
  try {
    const results = await Promise.allSettled(
      ruasList.map(async (r) => {
        const segs = await rekapApi.fetchDetailSegmen(r.id);
        return (Array.isArray(segs) ? segs : []).map((s) => ({
          ...s,
          ruasId: String(r.id),
        }));
      })
    );
    const merged: Array<RekapSegmenItem & { ruasId: string }> = [];
    for (const res of results) {
      if (res.status === "fulfilled") {
        merged.push(...res.value);
      }
    }
    desaSegmenCache.value = {
      ...desaSegmenCache.value,
      [key]: merged,
    };
  } finally {
    if (loadingDesaSegmenId.value === key) {
      loadingDesaSegmenId.value = null;
    }
  }
}

const currentRuasList = computed<RekapRuasItem[]>(() => {
  if (!selectedDesa.value) return [];
  return ruasCache.value[String(selectedDesa.value.id_desa)] || [];
});

const currentDesaSegmens = computed(() => {
  if (!selectedDesa.value) return [];
  return desaSegmenCache.value[String(selectedDesa.value.id_desa)] || [];
});

watch(
  [selectedDesa, currentRuasList],
  ([desa, ruasList]) => {
    if (desa && ruasList.length > 0) {
      ensureSegmenForDesa(desa.id_desa, ruasList);
    }
  },
  { immediate: true }
);

const selectedRuasYear = ref<number | "none" | null>(null);

interface DesaYearSummaryItem {
  key: number | "none";
  label: string;
  count: number;
  totalPanjang: number;
  percentOfMax: number;
  ruasIds: Set<string>;
}

const desaYearSummary = computed<DesaYearSummaryItem[]>(() => {
  const segmens = currentDesaSegmens.value;
  if (!segmens.length) return [];

  const buckets = new Map<number | "none", Omit<DesaYearSummaryItem, "percentOfMax">>();
  for (const seg of segmens) {
    const parsedYear = Number(seg.tahun);
    const key: number | "none" = Number.isFinite(parsedYear) && parsedYear > 1900 ? parsedYear : "none";
    const existing = buckets.get(key) || {
      key,
      label: key === "none" ? "Tanpa Tahun" : String(key),
      count: 0,
      totalPanjang: 0,
      ruasIds: new Set<string>(),
    };
    existing.count += 1;
    existing.totalPanjang += Number(seg.panjang || 0);
    existing.ruasIds.add(seg.ruasId);
    buckets.set(key, existing);
  }

  const sorted = Array.from(buckets.values()).sort((a, b) => {
    if (a.key === "none") return 1;
    if (b.key === "none") return -1;
    return Number(b.key) - Number(a.key);
  });

  const maxLen = Math.max(1, ...sorted.map((s) => s.totalPanjang));
  return sorted.map((item) => ({
    ...item,
    percentOfMax: Math.round((item.totalPanjang / maxLen) * 100),
  }));
});

const filteredRuasList = computed(() => {
  const q = filterQuery.value.trim().toLowerCase();
  let list = currentRuasList.value;
  if (selectedRuasYear.value !== null) {
    const yearGroup = desaYearSummary.value.find((y) => y.key === selectedRuasYear.value);
    if (yearGroup) {
      list = list.filter((r) => yearGroup.ruasIds.has(String(r.id)));
    }
  }
  if (!q) return list;
  return list.filter((r) => {
    const nameMatch = r.nama_ruas.toLowerCase().includes(q);
    const surfaceMatch = String(r.atribut?.jenis_perkerasan || "").toLowerCase().includes(q);
    return nameMatch || surfaceMatch;
  });
});

function handleOpenDistrict(kec: RekapKecamatanItem) {
  selectedDistrict.value = kec;
  selectedDesa.value = null;
  filterQuery.value = "";
  fetchDesaForKecamatan(kec.id_kecamatan);

  const entry = KECAMATAN_ENTRIES.find(
    (k) => normalizeWilayahKey(k.name) === normalizeWilayahKey(kec.nama_kecamatan)
  );
  if (entry) {
    emit("filter-wilayah", entry.name);
    emit("select-kecamatan", {
      name: `Kecamatan ${entry.name}`,
      coordinate: entry.coordinate,
      zoom: entry.zoom,
      bbox: entry.bbox,
      isKecamatan: true,
    });
  }
}

function handleOpenDesa(desa: RekapDesaItem) {
  selectedDesa.value = desa;
  selectedRuasYear.value = null;
  filterQuery.value = "";
  fetchRuasForDesa(desa.id_desa);
  emit(
    "filter-wilayah",
    selectedDistrict.value?.nama_kecamatan || props.selectedKecamatanFilter || null,
    undefined,
    desa.nama_desa
  );
}

function handleBackToDistricts() {
  selectedDesa.value = null;
  selectedDistrict.value = null;
  selectedRuasYear.value = null;
  filterQuery.value = "";
  emit("filter-wilayah", null, undefined, null);
}

function handleBackToDesas() {
  selectedDesa.value = null;
  selectedRuasYear.value = null;
  filterQuery.value = "";
  emit(
    "filter-wilayah",
    selectedDistrict.value?.nama_kecamatan || props.selectedKecamatanFilter || null,
    undefined,
    null
  );
}

function handleRoadClick(ruas: RekapRuasItem) {
  if (!selectedDistrict.value || !selectedDesa.value) return;
  emit("select-road", {
    ruas,
    desaName: selectedDesa.value.nama_desa,
    kecamatanName: selectedDistrict.value.nama_kecamatan,
  });
}

function getProgressBarClass(progress: number): string {
  if (progress >= 80) return "bg-emerald-500";
  if (progress >= 45) return "bg-blue-500";
  return "bg-amber-500";
}

function getConditionPillClass(kondisi?: string): string {
  const k = String(kondisi || "").toLowerCase();
  if (k.includes("baik")) {
    return "bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300";
  }
  if (k.includes("sedang")) {
    return "bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300";
  }
  if (k.includes("rusak") || k.includes("belum")) {
    return "bg-red-100 dark:bg-red-950/70 text-red-700 dark:text-red-300";
  }
  return "bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300";
}

const officeRoadmapSummary = computed(() => {
  if (countySummary.value && countySummary.value.totalDesa > 0) {
    return `Fitur peta sebaran kantor dan balai desa untuk ${formatCountId(countySummary.value.totalDesa)} desa di ${formatCountId(countySummary.value.totalKecamatan)} kecamatan saat ini sedang dalam tahap pengembangan dan verifikasi koordinat.`;
  }
  return "Fitur peta sebaran kantor dan balai desa di seluruh Kabupaten Bojonegoro saat ini sedang dalam tahap pengembangan dan verifikasi koordinat.";
});
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 overflow-y-auto px-4 sm:px-5 pt-1 pb-5 space-y-3.5 select-none">
    <!-- Filter Kategori Infrastruktur (hanya tampil di halaman utama Tab Infrastruktur, horizontal scroll + panah) -->
    <div
      v-if="!selectedDistrict || activeCategory !== 'jalan'"
      class="relative shrink-0"
    >
      <button
        v-if="canScrollLeft"
        type="button"
        aria-label="Geser kategori ke kiri"
        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 size-8 rounded-full bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-white/15 shadow-sm flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-all active:scale-95"
        @click="scrollCategoriesBy(-160)"
      >
        <UIcon name="i-lucide-chevron-left" class="size-4" />
      </button>

      <div
        ref="categoryScrollRef"
        class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5 scroll-smooth"
        :class="[canScrollLeft ? 'pl-9' : '', canScrollRight ? 'pr-9' : '']"
        @scroll="updateCategoryScrollButtons"
      >
        <button
          v-for="cat in infraCategories"
          :key="cat.id"
          type="button"
          :title="cat.isDeveloping ? `${cat.label} (Sedang dikembangkan)` : cat.label"
          class="px-3 py-2 rounded-full text-xs font-semibold transition-all cursor-pointer min-h-[38px] inline-flex items-center gap-1.5 border shrink-0 active:scale-[0.98]"
          :class="
            activeCategory === cat.id
              ? cat.activeClass
              : 'bg-slate-100/90 dark:bg-white/[0.05] text-slate-700 dark:text-slate-200 border-slate-200/70 dark:border-white/10 hover:bg-slate-200/70 dark:hover:bg-white/10'
          "
          @click="activeCategory = cat.id"
        >
          <UIcon :name="cat.icon" class="size-3.5 shrink-0" />
          <span class="whitespace-nowrap">{{ cat.label }}</span>
          <span
            v-if="cat.isDeveloping && activeCategory !== cat.id"
            class="size-1.5 rounded-full shrink-0"
            :class="cat.dotClass || 'bg-amber-500'"
            aria-label="Sedang dikembangkan"
          />
        </button>
      </div>

      <button
        v-if="canScrollRight"
        type="button"
        aria-label="Geser kategori ke kanan"
        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 size-8 rounded-full bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-white/15 shadow-sm flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-all active:scale-95"
        @click="scrollCategoriesBy(160)"
      >
        <UIcon name="i-lucide-chevron-right" class="size-4" />
      </button>
    </div>

    <!-- KATEGORI JALAN DESA -->
    <template v-if="activeCategory === 'jalan'">
      <!-- Kartu Ringkasan Kabupaten (Level 1) -->
      <div
        v-if="!selectedDistrict && countySummary"
        class="p-3.5 rounded-2xl bg-slate-100/80 dark:bg-white/[0.04] border border-slate-200/70 dark:border-white/10 space-y-2.5 shrink-0"
      >
        <div class="flex items-center justify-between gap-2">
          <div>
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">
              Total Jalan Desa Bojonegoro
            </p>
            <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums mt-0.5">
              {{ formatDistanceMeters(countySummary.panjangDibangunMeter) }} / {{ formatDistanceMeters(countySummary.totalPanjangMeter) }}
            </p>
          </div>
          <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 tabular-nums shrink-0">
            {{ formatPercentId(countySummary.progress) }} terbangun
          </span>
        </div>
        <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
          <div
            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
            :style="{ width: `${Math.min(100, Math.max(2, countySummary.progress))}%` }"
          />
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-300 tabular-nums">
          {{ formatCountId(countySummary.totalKecamatan) }} kecamatan · {{ formatCountId(countySummary.totalDesa) }} desa · {{ formatCountId(countySummary.totalRuas) }} ruas jalan
        </p>
      </div>

      <!-- Pencarian Cepat (dengan Tombol Kembali di Samping Kiri saat Wilayah Dipilih) & Urutan -->
      <div class="space-y-2 shrink-0">
        <div class="flex items-center gap-2">
          <button
            v-if="selectedDistrict"
            type="button"
            class="size-11 min-w-[44px] min-h-[44px] rounded-full border border-slate-200/80 dark:border-white/10 bg-slate-100/90 dark:bg-white/[0.06] hover:bg-slate-200/80 dark:hover:bg-white/10 flex items-center justify-center text-slate-700 dark:text-slate-200 transition-all cursor-pointer shrink-0 active:scale-95"
            :title="selectedDesa ? `Kembali ke daftar desa di Kec. ${selectedDistrict.nama_kecamatan}` : 'Kembali ke semua kecamatan'"
            :aria-label="selectedDesa ? `Kembali ke daftar desa di Kecamatan ${selectedDistrict.nama_kecamatan}` : 'Kembali ke semua kecamatan'"
            @click="selectedDesa ? handleBackToDesas() : handleBackToDistricts()"
          >
            <UIcon name="i-ion-arrow-back" class="size-4.5" />
          </button>

          <div class="relative flex-1 min-w-0">
            <UIcon
              name="i-ion-search-outline"
              class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-500 dark:text-slate-400 pointer-events-none"
            />
            <input
              v-model="filterQuery"
              type="text"
              :placeholder="
                selectedDesa
                  ? `Cari ruas di Desa ${selectedDesa.nama_desa}...`
                  : selectedDistrict
                    ? `Cari desa di Kec. ${selectedDistrict.nama_kecamatan}...`
                    : 'Saring nama kecamatan...'
              "
              class="w-full h-11 pl-10 pr-10 rounded-full bg-slate-100/90 dark:bg-white/[0.06] border border-slate-200/80 dark:border-white/10 text-base sm:text-xs text-slate-900 dark:text-slate-100 placeholder-slate-500 dark:placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30"
            />
            <button
              v-if="filterQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 size-7 after:absolute after:-inset-2 after:content-[''] rounded-full flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 cursor-pointer"
              aria-label="Hapus saringan"
              @click="filterQuery = ''"
            >
              <UIcon name="i-ion-close" class="size-4" />
            </button>
          </div>
        </div>

        <!-- Filter Urutan (Segmented Control Ringkas) -->
        <div
          v-if="!selectedDesa"
          role="group"
          aria-label="Urutkan daftar wilayah"
          class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-slate-100/90 dark:bg-white/[0.06] border border-slate-200/70 dark:border-white/10"
        >
          <button
            type="button"
            class="py-1.5 px-2 min-h-[34px] rounded-lg text-[11px] sm:text-xs font-semibold transition-all cursor-pointer inline-flex items-center justify-center gap-1 truncate"
            :class="
              sortMode === 'nama'
                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
            "
            @click="sortMode = 'nama'"
          >
            <UIcon name="i-lucide-arrow-down-a-z" class="size-3.5 shrink-0" />
            <span class="truncate">Nama A-Z</span>
          </button>

          <button
            type="button"
            class="py-1.5 px-2 min-h-[34px] rounded-lg text-[11px] sm:text-xs font-semibold transition-all cursor-pointer inline-flex items-center justify-center gap-1 truncate"
            :class="
              sortMode === 'terbangun'
                ? 'bg-white dark:bg-slate-900 text-emerald-700 dark:text-emerald-300 shadow-xs'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
            "
            @click="sortMode = 'terbangun'"
          >
            <span
              class="size-1.5 rounded-full shrink-0"
              :class="sortMode === 'terbangun' ? 'bg-emerald-500' : 'bg-emerald-500/60'"
            />
            <span class="truncate">Terbangun</span>
          </button>

          <button
            type="button"
            class="py-1.5 px-2 min-h-[34px] rounded-lg text-[11px] sm:text-xs font-semibold transition-all cursor-pointer inline-flex items-center justify-center gap-1 truncate"
            :class="
              sortMode === 'prioritas'
                ? 'bg-white dark:bg-slate-900 text-amber-700 dark:text-amber-300 shadow-xs'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
            "
            @click="sortMode = 'prioritas'"
          >
            <span
              class="size-1.5 rounded-full shrink-0"
              :class="sortMode === 'prioritas' ? 'bg-amber-500' : 'bg-amber-500/60'"
            />
            <span class="truncate">Prioritas</span>
          </button>
        </div>
      </div>

      <!-- LEVEL 3: DAFTAR RUAS JALAN PER DESA -->
      <div v-if="selectedDesa" class="space-y-2.5">
        <!-- Grafik & Ringkasan Panjang Segmen per Tahun di Desa Terpilih -->
        <div
          v-if="loadingDesaSegmenId === String(selectedDesa.id_desa)"
          class="p-3 rounded-2xl bg-slate-100/80 dark:bg-white/[0.04] border border-slate-200/70 dark:border-white/10 space-y-2"
        >
          <div class="h-3 w-40 rounded bg-slate-200 dark:bg-white/10 animate-pulse" />
          <div class="h-5 w-full rounded bg-slate-200 dark:bg-white/10 animate-pulse" />
          <div class="h-5 w-4/5 rounded bg-slate-200 dark:bg-white/10 animate-pulse" />
        </div>

        <div
          v-else-if="desaYearSummary.length > 0"
          class="p-3 rounded-2xl bg-slate-100/80 dark:bg-white/[0.04] border border-slate-200/70 dark:border-white/10 space-y-2.5"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
              Segmen per Tahun · {{ selectedDesa.nama_desa }}
            </span>
            <button
              v-if="selectedRuasYear !== null"
              type="button"
              class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
              @click="selectedRuasYear = null"
            >
              Semua Tahun
            </button>
          </div>

          <div class="space-y-1.5">
            <button
              v-for="yg in desaYearSummary"
              :key="String(yg.key)"
              type="button"
              class="w-full p-2 rounded-xl border text-left transition-all cursor-pointer space-y-1 active:scale-[0.99]"
              :class="
                selectedRuasYear === yg.key
                  ? 'bg-blue-50/90 dark:bg-blue-950/40 border-blue-400 dark:border-blue-600'
                  : 'bg-white dark:bg-[#0f172a] border-slate-200/80 dark:border-white/10 hover:border-blue-300'
              "
              @click="selectedRuasYear = selectedRuasYear === yg.key ? null : yg.key"
            >
              <div class="flex items-center justify-between gap-2 text-xs">
                <span class="font-bold text-slate-900 dark:text-white tabular-nums">
                  {{ yg.label }}
                </span>
                <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 tabular-nums">
                  {{ formatDistanceMeters(yg.totalPanjang) }}
                  <span class="text-slate-500 dark:text-slate-400 font-normal">({{ yg.count }} segmen)</span>
                </span>
              </div>
              <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300"
                  :class="selectedRuasYear === yg.key ? 'bg-blue-600' : 'bg-gradient-to-r from-blue-600 to-emerald-500'"
                  :style="{ width: `${Math.max(4, yg.percentOfMax)}%` }"
                />
              </div>
            </button>
          </div>
        </div>

        <div v-if="loadingRuasDesaId === String(selectedDesa.id_desa)" class="space-y-2 py-2">
          <div v-for="i in 4" :key="i" class="h-16 rounded-2xl bg-slate-100 dark:bg-white/5 animate-pulse" />
        </div>

        <div
          v-else-if="errorRuas && currentRuasList.length === 0"
          class="p-4 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center space-y-2"
        >
          <p class="text-xs text-slate-600 dark:text-slate-300">{{ errorRuas }}</p>
          <button
            type="button"
            class="px-4 py-2 rounded-full text-xs font-bold bg-blue-600 text-white cursor-pointer min-h-[44px]"
            @click="fetchRuasForDesa(selectedDesa.id_desa, true)"
          >
            Muat Ulang Ruas
          </button>
        </div>

        <div
          v-else-if="filteredRuasList.length === 0"
          class="p-5 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center text-xs text-slate-600 dark:text-slate-300"
        >
          Tidak ada ruas jalan yang cocok di desa ini.
        </div>

        <div
          v-else
          class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden"
        >
          <button
            v-for="ruas in filteredRuasList"
            :key="ruas.id"
            type="button"
            class="w-full px-3.5 py-3 hover:bg-slate-200/45 dark:hover:bg-white/[0.04] text-left transition-colors cursor-pointer space-y-2 min-h-[56px]"
            @click="handleRoadClick(ruas)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[13px] font-bold text-slate-900 dark:text-white leading-snug">
                  {{ ruas.nama_ruas }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 tabular-nums">
                  Panjang {{ formatDistanceMeters(ruas.panjang_master || ruas.panjang) }}
                  <span v-if="ruas.atribut?.jenis_perkerasan">
                    · {{ ruas.atribut.jenis_perkerasan }}
                  </span>
                </p>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0"
                :class="getConditionPillClass(ruas.kondisi || ruas.status_kondisi)"
              >
                {{ ruas.kondisi || ruas.status_kondisi || formatPercentId(ruas.progress) }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 pt-1">
              <span class="tabular-nums">
                Terbangun {{ formatDistanceMeters(ruas.panjang_dibangun) }} ({{ formatPercentId(ruas.progress) }})
              </span>
              <span class="text-blue-600 dark:text-blue-400 font-semibold flex items-center gap-0.5 shrink-0">
                Lihat di peta
                <UIcon name="i-ion-chevron-forward" class="size-3.5" />
              </span>
            </div>
          </button>
        </div>
      </div>

      <!-- LEVEL 2: DAFTAR DESA DI KECAMATAN TERPILIH -->
      <div v-else-if="selectedDistrict" class="space-y-2">
        <div v-if="loadingDesaId === selectedDistrict.id_kecamatan && currentDesaList.length === 0" class="space-y-2 py-2">
          <div v-for="i in 5" :key="i" class="h-16 rounded-2xl bg-slate-100 dark:bg-white/5 animate-pulse" />
        </div>

        <div
          v-else-if="errorDesa && currentDesaList.length === 0"
          class="p-4 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center space-y-2"
        >
          <p class="text-xs text-slate-600 dark:text-slate-300">{{ errorDesa }}</p>
          <button
            type="button"
            class="px-4 py-2 rounded-full text-xs font-bold bg-blue-600 text-white cursor-pointer min-h-[44px]"
            @click="fetchDesaForKecamatan(selectedDistrict.id_kecamatan, true)"
          >
            Coba Lagi
          </button>
        </div>

        <div
          v-else-if="filteredDesas.length === 0"
          class="p-5 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center text-xs text-slate-600 dark:text-slate-300"
        >
          Tidak ada desa yang cocok.
        </div>

        <div
          v-else
          class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden"
        >
          <button
            v-for="desa in filteredDesas"
            :key="desa.id_desa"
            type="button"
            class="w-full px-3.5 py-3 hover:bg-slate-200/45 dark:hover:bg-white/[0.04] text-left transition-colors cursor-pointer space-y-2 min-h-[56px]"
            @click="handleOpenDesa(desa)"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
                  Desa {{ desa.nama_desa }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 tabular-nums">
                  {{ formatCountId(desa.jumlah_ruas) }} ruas · {{ formatDistanceMeters(desa.total_panjang) }}
                </p>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                  {{ formatPercentId(desa.progress) }}
                </span>
                <UIcon name="i-ion-chevron-forward" class="size-4 text-slate-400" />
              </div>
            </div>
            <div class="h-1.5 w-full rounded-full bg-white/90 dark:bg-white/10 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="getProgressBarClass(desa.progress)"
                :style="{ width: `${Math.min(100, Math.max(2, desa.progress))}%` }"
              />
            </div>
          </button>
        </div>
      </div>

      <!-- LEVEL 1: DAFTAR KECAMATAN -->
      <div v-else class="space-y-2">
        <div v-if="loadingKecamatan && kecamatanStats.length === 0" class="space-y-2 py-2">
          <div v-for="i in 6" :key="i" class="h-16 rounded-2xl bg-slate-100 dark:bg-white/5 animate-pulse" />
        </div>

        <div
          v-else-if="errorKecamatan && kecamatanStats.length === 0"
          class="p-4 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center space-y-2"
        >
          <p class="text-xs text-slate-600 dark:text-slate-300">{{ errorKecamatan }}</p>
          <button
            type="button"
            class="px-4 py-2 rounded-full text-xs font-bold bg-blue-600 text-white cursor-pointer min-h-[44px]"
            @click="ensureKecamatanStats(true)"
          >
            Muat Ulang Data
          </button>
        </div>

        <div
          v-else-if="filteredDistricts.length === 0"
          class="p-5 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center text-xs text-slate-600 dark:text-slate-300"
        >
          Kecamatan tidak ditemukan.
        </div>

        <div
          v-else
          class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden"
        >
          <button
            v-for="kec in filteredDistricts"
            :key="kec.id_kecamatan"
            type="button"
            class="w-full px-3.5 py-3 hover:bg-slate-200/45 dark:hover:bg-white/[0.04] text-left transition-colors cursor-pointer space-y-2 min-h-[56px]"
            @click="handleOpenDistrict(kec)"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
                  Kecamatan {{ kec.nama_kecamatan }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 tabular-nums">
                  {{ formatCountId(kec.jumlah_desa) }} desa · {{ formatDistanceMeters(kec.panjang_dibangun) }} / {{ formatDistanceMeters(kec.total_panjang) }}
                </p>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                  {{ formatPercentId(kec.progress) }}
                </span>
                <UIcon name="i-ion-chevron-forward" class="size-4 text-slate-400" />
              </div>
            </div>
            <div class="h-1.5 w-full rounded-full bg-white/90 dark:bg-white/10 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="getProgressBarClass(kec.progress)"
                :style="{ width: `${Math.min(100, Math.max(2, kec.progress))}%` }"
              />
            </div>
          </button>
        </div>
      </div>
    </template>

    <!-- KATEGORI JEMBATAN / KANTOR DESA (TAHAP RENCANA) -->
    <div
      v-else
      class="p-5 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/80 dark:bg-white/[0.03] text-center space-y-3.5 mt-1"
    >
      <div
        class="size-14 rounded-2xl mx-auto flex items-center justify-center shadow-xs"
        :class="activeCategory === 'jembatan'
          ? 'bg-amber-100 dark:bg-amber-950/70 text-amber-700 dark:text-amber-300'
          : 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300'"
      >
        <UIcon :name="activeCategory === 'jembatan' ? 'i-ion-construct' : 'i-ion-business'" class="size-7" />
      </div>

      <div class="space-y-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300">
          <span class="size-1.5 rounded-full bg-amber-500" />
          <span>Sedang Dikembangkan</span>
        </span>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
          {{ activeCategory === 'jembatan' ? 'Peta Sebaran Jembatan Desa' : 'Peta Sebaran Kantor Desa' }}
        </h3>
        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed max-w-[300px] mx-auto">
          {{
            activeCategory === 'jembatan'
              ? 'Fitur ini sedang dalam tahap pengembangan. Pendataan koordinat titik jembatan, panjang bentang, dan kondisi struktur penghubung antar desa di Kabupaten Bojonegoro sedang disiapkan agar segera tampil interaktif di peta.'
              : officeRoadmapSummary
          }}
        </p>
      </div>

      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-full text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition-colors cursor-pointer min-h-[44px]"
        @click="activeCategory = 'jalan'"
      >
        <UIcon name="i-ion-trail-sign" class="size-4" />
        <span>Lihat Data Jalan Desa</span>
      </button>
    </div>
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

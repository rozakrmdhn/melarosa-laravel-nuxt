<script setup lang="ts">
interface SearchItem {
  id: string;
  name: string;
  type: "kecamatan" | "koordinat" | "landmark";
  desc: string;
  coordinate: [number, number]; // [lng, lat]
  zoom: number;
}

const props = withDefaults(
  defineProps<{
    leftPanelOpen?: boolean;
  }>(),
  {
    leftPanelOpen: false,
  }
);

const emit = defineEmits<{
  (e: "toggle-left-panel"): void;
  (e: "select-location", payload: { name: string; coordinate: [number, number]; zoom?: number }): void;
}>();

const route = useRoute();
const initialQuery = typeof route.query.search === "string" ? route.query.search : (typeof route.query.name === "string" ? route.query.name : "");
const searchQuery = ref(initialQuery.replace("Kecamatan ", ""));
const isFocused = ref(false);
const searchInputRef = ref<HTMLInputElement | null>(null);

watch(
  () => route.query.search,
  (newVal) => {
    if (typeof newVal === "string") {
      searchQuery.value = newVal.replace("Kecamatan ", "");
    } else if (!newVal && !route.query.name) {
      searchQuery.value = "";
    }
  }
);

// Bojonegoro 28 subdistricts dataset with centroids [lng, lat]
const KECAMATAN_LIST: SearchItem[] = [
  { id: "k-1", name: "Kecamatan Bojonegoro (Kota)", type: "kecamatan", desc: "Pusat Pemerintahan & Perkotaan", coordinate: [111.8817, -7.1502], zoom: 14 },
  { id: "k-2", name: "Kecamatan Kapas", type: "kecamatan", desc: "Wilayah Timur Bojonegoro", coordinate: [111.9167, -7.1833], zoom: 13 },
  { id: "k-3", name: "Kecamatan Balen", type: "kecamatan", desc: "Wilayah Jalur Utama Timur", coordinate: [111.9667, -7.1667], zoom: 13 },
  { id: "k-4", name: "Kecamatan Sumberrejo", type: "kecamatan", desc: "Kawasan Sentra Padi & Perdagangan", coordinate: [112.0167, -7.1500], zoom: 13 },
  { id: "k-5", name: "Kecamatan Kanor", type: "kecamatan", desc: "Kawasan Bantaran Bengawan Solo", coordinate: [112.0333, -7.1000], zoom: 13 },
  { id: "k-6", name: "Kecamatan Baureno", type: "kecamatan", desc: "Gerbang Timur Perbatasan Lamongan", coordinate: [112.1000, -7.1333], zoom: 13 },
  { id: "k-7", name: "Kecamatan Kepohbaru", type: "kecamatan", desc: "Wilayah Tenggara Bojonegoro", coordinate: [112.0667, -7.2167], zoom: 13 },
  { id: "k-8", name: "Kecamatan Kedungadem", type: "kecamatan", desc: "Wilayah Selatan Perbatasan Nganjuk", coordinate: [112.0167, -7.2833], zoom: 13 },
  { id: "k-9", name: "Kecamatan Sugihwaras", type: "kecamatan", desc: "Wilayah Pertanian & Perkebunan", coordinate: [111.9500, -7.3000], zoom: 13 },
  { id: "k-10", name: "Kecamatan Temayang", type: "kecamatan", desc: "Wilayah Waduk Pacal & Hutan", coordinate: [111.8833, -7.3167], zoom: 13 },
  { id: "k-11", name: "Kecamatan Dander", type: "kecamatan", desc: "Kawasan Wisata Kayangan Api & Hutan", coordinate: [111.8500, -7.2333], zoom: 13 },
  { id: "k-12", name: "Kecamatan Bubulan", type: "kecamatan", desc: "Wilayah Dataran Tinggi Selatan", coordinate: [111.8000, -7.3333], zoom: 13 },
  { id: "k-13", name: "Kecamatan Gondang", type: "kecamatan", desc: "Kawasan Hutan Jati Perbatasan", coordinate: [111.8167, -7.4167], zoom: 13 },
  { id: "k-14", name: "Kecamatan Sekar", type: "kecamatan", desc: "Wilayah Pegunungan Kendeng Selatan", coordinate: [111.7500, -7.4500], zoom: 13 },
  { id: "k-15", name: "Kecamatan Ngasem", type: "kecamatan", desc: "Wilayah Hutan & Wisata Geopark", coordinate: [111.7667, -7.2333], zoom: 13 },
  { id: "k-16", name: "Kecamatan Ngambon", type: "kecamatan", desc: "Kawasan Hijau Perbukitan", coordinate: [111.7167, -7.2833], zoom: 13 },
  { id: "k-17", name: "Kecamatan Tambakrejo", type: "kecamatan", desc: "Wilayah Hutan Barat Daya", coordinate: [111.6500, -7.2333], zoom: 13 },
  { id: "k-18", name: "Kecamatan Purwosari", type: "kecamatan", desc: "Kawasan Minyak & Pertanian", coordinate: [111.6667, -7.1667], zoom: 13 },
  { id: "k-19", name: "Kecamatan Padangan", type: "kecamatan", desc: "Kawasan Perkotaan Perbatasan Cepu", coordinate: [111.6167, -7.1500], zoom: 13 },
  { id: "k-20", name: "Kecamatan Kasiman", type: "kecamatan", desc: "Wilayah Barat Bengawan Solo", coordinate: [111.6000, -7.1000], zoom: 13 },
  { id: "k-21", name: "Kecamatan Kedewan", type: "kecamatan", desc: "Wisata Geopark Sumur Minyak Tua", coordinate: [111.5833, -7.0333], zoom: 13 },
  { id: "k-22", name: "Kecamatan Malo", type: "kecamatan", desc: "Kawasan Kerajinan Gerabah & Hutan", coordinate: [111.7000, -7.1000], zoom: 13 },
  { id: "k-23", name: "Kecamatan Kalitidu", type: "kecamatan", desc: "Kawasan Industri Migas Banyu Urip", coordinate: [111.7667, -7.1333], zoom: 13 },
  { id: "k-24", name: "Kecamatan Trucuk", type: "kecamatan", desc: "Wilayah Utara Jembatan Sosrodilogo", coordinate: [111.8667, -7.1333], zoom: 13 },
  { id: "k-25", name: "Kecamatan Gayam", type: "kecamatan", desc: "Pusat Blok Cepu ExxonMobil", coordinate: [111.7167, -7.1833], zoom: 13 },
  { id: "k-26", name: "Kecamatan Margomulyo", type: "kecamatan", desc: "Kawasan Samin & Perbatasan Ngawi", coordinate: [111.5333, -7.2833], zoom: 13 },
  { id: "k-27", name: "Kecamatan Ngraho", type: "kecamatan", desc: "Wilayah Selatan Jalur Ngawi-Bojonegoro", coordinate: [111.5667, -7.2333], zoom: 13 },
  { id: "k-28", name: "Kecamatan Sukosewu", type: "kecamatan", desc: "Wilayah Pertanian Tengah Bojonegoro", coordinate: [111.9333, -7.2333], zoom: 13 },
];

import { parseCoordinateString } from "~/utils/gis-helpers";

// Check if user input matches coordinates format (Decimal DD or DMS)
const coordinateMatch = computed<SearchItem | null>(() => {
  const parsed = parseCoordinateString(searchQuery.value);
  if (!parsed) return null;

  return {
    id: "coord-custom",
    name: `Koordinat: ${parsed.formatted}`,
    type: "koordinat",
    desc: "Lompat langsung ke titik koordinat spasial",
    coordinate: [parsed.lng, parsed.lat],
    zoom: 16,
  };
});

// Filter suggestions based on query
const suggestions = computed<SearchItem[]>(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const list: SearchItem[] = [];

  if (coordinateMatch.value) {
    list.push(coordinateMatch.value);
  }

  if (!q) {
    // Show top popular districts by default if empty focus
    return KECAMATAN_LIST.slice(0, 5);
  }

  const matches = KECAMATAN_LIST.filter(
    (item) =>
      item.name.toLowerCase().includes(q) ||
      item.desc.toLowerCase().includes(q)
  );

  return [...list, ...matches.slice(0, 6)];
});

function handleSelect(item: SearchItem) {
  emit("select-location", {
    name: item.name,
    coordinate: item.coordinate,
    zoom: item.zoom,
  });
  searchQuery.value = item.name.replace("Kecamatan ", "");
  isFocused.value = false;
  searchInputRef.value?.blur();
}

function clearSearch() {
  searchQuery.value = "";
  searchInputRef.value?.focus();
}

// Close suggestion when clicking outside
const panelContainerRef = ref<HTMLElement | null>(null);
function handleWindowClick(e: MouseEvent) {
  if (panelContainerRef.value && !panelContainerRef.value.contains(e.target as Node)) {
    isFocused.value = false;
  }
}

onMounted(() => {
  window.addEventListener("click", handleWindowClick);
});

onUnmounted(() => {
  window.removeEventListener("click", handleWindowClick);
});
</script>

<template>
  <div
    ref="panelContainerRef"
    class="relative w-full transition-all duration-200"
  >
    <!-- Floating Search Bar Card -->
    <div
      class="flex items-center gap-1.5 p-1.5 bg-white/95 dark:bg-[#09111e]/85 backdrop-blur-xl border rounded-2xl shadow-lg shadow-slate-900/5 dark:shadow-none transition-all"
      :class="isFocused
        ? 'border-blue-500/80 ring-2 ring-blue-500/20'
        : 'border-slate-200/90 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/20'"
    >
      <!-- Left Panel Toggle Button inside Search Bar -->
      <UTooltip :text="leftPanelOpen ? 'Tutup Panel Menu' : 'Buka Panel Menu'">
        <UButton
          :icon="leftPanelOpen ? 'i-lucide-panel-left-close' : 'i-lucide-menu'"
          size="sm"
          color="neutral"
          variant="ghost"
          :class="[
            'shrink-0 rounded-xl transition-colors cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center',
            leftPanelOpen
              ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-600 dark:text-blue-300'
              : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10'
          ]"
          aria-label="Toggle Panel Menu Kiri"
          @click="emit('toggle-left-panel')"
        />
      </UTooltip>

      <!-- Search Input Field -->
      <div class="flex-1 flex items-center gap-1.5 min-w-0">
        <UIcon name="i-lucide-search" class="size-4 text-slate-400 shrink-0 ml-1" />
        <input
          ref="searchInputRef"
          v-model="searchQuery"
          type="text"
          role="combobox"
          :aria-expanded="isFocused && suggestions.length > 0"
          aria-autocomplete="list"
          aria-controls="search-suggestions"
          aria-haspopup="listbox"
          placeholder="Cari kecamatan, lokasi, koordinat..."
          class="w-full bg-transparent text-xs sm:text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-hidden py-2 min-h-[44px]"
          @focus="isFocused = true"
          @keydown.enter="suggestions[0] && handleSelect(suggestions[0])"
        />
      </div>

      <!-- Clear Button -->
      <UButton
        v-if="searchQuery"
        icon="i-lucide-x"
        size="xs"
        color="neutral"
        variant="ghost"
        class="shrink-0 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 min-h-[44px] min-w-[44px] flex items-center justify-center rounded-xl"
        aria-label="Hapus pencarian"
        @click="clearSearch"
      />

      <!-- Quick Reset Center Button -->
      <UTooltip text="Pusatkan ke Bojonegoro">
        <UButton
          icon="i-lucide-crosshair"
          size="xs"
          color="neutral"
          variant="subtle"
          class="shrink-0 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
          aria-label="Pusatkan ke Bojonegoro"
          @click="emit('select-location', { name: 'Kabupaten Bojonegoro', coordinate: [111.88, -7.15], zoom: 11 })"
        />
      </UTooltip>
    </div>

    <!-- Live Suggestions Dropdown -->
    <Transition name="dropdown-fade">
      <div
        v-if="isFocused && (suggestions.length > 0 || searchQuery.trim())"
        id="search-suggestions"
        role="listbox"
        class="absolute top-full left-0 right-0 mt-2 bg-white/98 dark:bg-[#09111e]/98 backdrop-blur-xl border border-slate-200/90 dark:border-white/10 rounded-2xl shadow-2xl overflow-hidden z-40 max-h-[50vh] overflow-y-auto divide-y divide-slate-100 dark:divide-white/10"
      >
        <div class="px-3.5 py-2.5 bg-slate-50/80 dark:bg-slate-900/60 flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
            {{ searchQuery ? 'Hasil Pencarian' : 'Rekomendasi Wilayah' }}
          </span>
          <span class="text-xs text-slate-500 dark:text-slate-400">Enter untuk memilih</span>
        </div>

        <!-- Empty state when search finds nothing -->
        <div
          v-if="suggestions.length === 0 && searchQuery.trim()"
          class="px-4 py-6 text-center"
        >
          <UIcon name="i-lucide-search-x" class="size-8 text-slate-300 dark:text-slate-600 mx-auto" />
          <p class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300">Tidak ada hasil untuk "{{ searchQuery }}"</p>
          <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Coba nama kecamatan atau format koordinat: -7.15, 111.88</p>
        </div>

        <button
          v-for="item in suggestions"
          :key="item.id"
          type="button"
          role="option"
          :aria-selected="false"
          class="w-full px-3.5 py-3 text-left flex items-center gap-3 hover:bg-blue-50/70 dark:hover:bg-blue-950/40 transition-colors cursor-pointer group min-h-[44px]"
          @mousedown.prevent="handleSelect(item)"
        >
          <div
            class="size-8 rounded-xl flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform"
            :class="item.type === 'koordinat'
              ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400'
              : 'bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400'"
          >
            <UIcon
              :name="item.type === 'koordinat' ? 'i-lucide-crosshair' : 'i-lucide-map-pin'"
              class="size-4"
            />
          </div>

          <div class="min-w-0 flex-1">
            <p class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-slate-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
              {{ item.name }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
              {{ item.desc }}
            </p>
          </div>

          <UIcon
            name="i-lucide-chevron-right"
            class="size-4 text-slate-300 dark:text-slate-600 group-hover:text-blue-500 group-hover:translate-x-0.5 transition-all shrink-0"
          />
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

<script setup lang="ts">
import type { BasemapKey } from "~/components/maps/MapCanvas.vue";
import type { ActiveLayerItem, ActiveLayerTool } from "~/types/map-layers";
import GeoportalLayerSection from "./GeoportalLayerSection.vue";
import { useGeoportalLayers, type GeoportalLayerItem } from "~/composables/useGeoportalLayers";

const props = withDefaults(
  defineProps<{
    isMobileDrawer?: boolean;
    activeLayers?: ActiveLayerItem[];
    currentBasemap?: BasemapKey;
    activeTab?: "layers" | "geoportal" | "kecamatan";
    selectedKecamatanFilter?: string | null;
  }>(),
  {
    isMobileDrawer: false,
    activeLayers: () => [],
    currentBasemap: "esri",
    activeTab: "layers",
    selectedKecamatanFilter: null,
  }
);

const emit = defineEmits<{
  (e: "close"): void;
  (e: "zoom-to-home"): void;
  (e: "select-kecamatan", payload: { name: string; coordinate: [number, number]; zoom?: number }): void;
  (e: "filter-wilayah", kecamatan: string | null): void;
  (e: "reorder-layers", layers: ActiveLayerItem[]): void;
  (e: "zoom-to-layer", layer: ActiveLayerItem): void;
  (e: "toggle-layer-visibility", layerId: string): void;
  (e: "update-layer-opacity", payload: { layerId: string; opacity: number }): void;
  (e: "update-layer-filter", payload: { layerId: string; kecamatan: string | null; status: string | null }): void;
  (e: "remove-layer", layerId: string): void;
  (e: "add-layer", layer: ActiveLayerItem): void;
  (e: "update:currentBasemap", val: BasemapKey): void;
}>();

const localActiveTab = ref<"layers" | "geoportal" | "kecamatan">(props.activeTab);

watch(
  () => props.activeTab,
  (val) => {
    if (val) localActiveTab.value = val;
  }
);

const currentActiveTab = computed(() =>
  props.isMobileDrawer ? (props.activeTab || "layers") : localActiveTab.value
);

const { toActiveLayerItem } = useGeoportalLayers();

function handleGeoportalToggle(payload: { layer: GeoportalLayerItem; active: boolean; opacity?: number }) {
  if (payload.active) {
    const item = toActiveLayerItem(payload.layer, payload.opacity ?? 0.85);
    emit("add-layer", item);
  } else {
    emit("remove-layer", payload.layer.id);
  }
}

function handleGeoportalOpacity(payload: { layerId: string; opacity: number }) {
  emit("update-layer-opacity", payload);
}

const kecamatanQuery = ref("");

interface BasemapOption {
  key: BasemapKey;
  name: string;
  tagline: string;
}

const BASEMAP_OPTIONS: BasemapOption[] = [
  { key: "esri", name: "Esri", tagline: "Esri World Topo Map" },
  { key: "rbi", name: "RBI", tagline: "Rupabumi Indonesia (BIG)" },
  { key: "osm", name: "OSM", tagline: "Peta Jalan OSM" },
  { key: "satellite", name: "Satelit", tagline: "Citra Resolusi Tinggi" },
  { key: "aerial", name: "Aerial", tagline: "Foto Udara Resolusi Tinggi" },
  { key: "none", name: "None", tagline: "Putih Polos Tanpa Basemap" },
];

function selectBasemap(key: BasemapKey) {
  emit("update:currentBasemap", key);
}

interface KecamatanEntry {
  name: string;
  zona: string;
  desc: string;
  coordinate: [number, number];
  zoom: number;
}

const KECAMATAN_ENTRIES: KecamatanEntry[] = [
  { name: "Balen", zona: "Timur", desc: "Jalur Utama Timur Bojonegoro", coordinate: [111.9667, -7.1667], zoom: 13 },
  { name: "Baureno", zona: "Timur", desc: "Gerbang Timur Perbatasan Lamongan", coordinate: [112.1000, -7.1333], zoom: 13 },
  { name: "Bojonegoro", zona: "Kota", desc: "Pusat Pemerintahan & Perkotaan", coordinate: [111.8817, -7.1502], zoom: 14 },
  { name: "Bubulan", zona: "Selatan", desc: "Dataran Tinggi Selatan", coordinate: [111.8000, -7.3333], zoom: 13 },
  { name: "Dander", zona: "Tengah", desc: "Kawasan Wisata Kayangan Api", coordinate: [111.8500, -7.2333], zoom: 13 },
  { name: "Gayam", zona: "Barat", desc: "Pusat Migas Blok Cepu", coordinate: [111.7167, -7.1833], zoom: 13 },
  { name: "Gondang", zona: "Selatan", desc: "Kawasan Hutan Jati Perbatasan", coordinate: [111.8167, -7.4167], zoom: 13 },
  { name: "Kalitidu", zona: "Barat", desc: "Kawasan Industri & Pertanian", coordinate: [111.7667, -7.1333], zoom: 13 },
  { name: "Kanor", zona: "Timur", desc: "Bantaran Bengawan Solo", coordinate: [112.0333, -7.1000], zoom: 13 },
  { name: "Kapas", zona: "Timur", desc: "Kawasan Penyangga Perkotaan", coordinate: [111.9167, -7.1833], zoom: 13 },
  { name: "Kasiman", zona: "Barat", desc: "Barat Bengawan Solo", coordinate: [111.6000, -7.1000], zoom: 13 },
  { name: "Kedewan", zona: "Barat", desc: "Geopark Sumur Minyak Tua", coordinate: [111.5833, -7.0333], zoom: 13 },
  { name: "Kedungadem", zona: "Tenggara", desc: "Perbatasan Wilayah Nganjuk", coordinate: [112.0167, -7.2833], zoom: 13 },
  { name: "Kepohbaru", zona: "Timur", desc: "Sentra Pertanian Timur", coordinate: [112.0667, -7.2167], zoom: 13 },
  { name: "Malo", zona: "Barat", desc: "Sentra Kerajinan Gerabah", coordinate: [111.7000, -7.1000], zoom: 13 },
  { name: "Margomulyo", zona: "Barat", desc: "Kawasan Adat Samin & Ngawi", coordinate: [111.5333, -7.2833], zoom: 13 },
  { name: "Ngambon", zona: "Barat", desc: "Kawasan Hijau Perbukitan", coordinate: [111.7167, -7.2833], zoom: 13 },
  { name: "Ngasem", zona: "Barat", desc: "Hutan & Geopark Khayangan", coordinate: [111.7667, -7.2333], zoom: 13 },
  { name: "Ngraho", zona: "Barat", desc: "Jalur Penghubung Ngawi", coordinate: [111.5667, -7.2333], zoom: 13 },
  { name: "Padangan", zona: "Barat", desc: "Kawasan Perbatasan Cepu", coordinate: [111.6167, -7.1500], zoom: 13 },
  { name: "Purwosari", zona: "Barat", desc: "Kawasan Energi & Padi", coordinate: [111.6667, -7.1667], zoom: 13 },
  { name: "Sekar", zona: "Selatan", desc: "Pegunungan Kendeng Selatan", coordinate: [111.7500, -7.4500], zoom: 13 },
  { name: "Sugihwaras", zona: "Selatan", desc: "Pertanian & Perkebunan", coordinate: [111.9500, -7.3000], zoom: 13 },
  { name: "Sukosewu", zona: "Timur", desc: "Pertanian Tengah Bojonegoro", coordinate: [111.9333, -7.2333], zoom: 13 },
  { name: "Sumberrejo", zona: "Timur", desc: "Sentra Niaga Padi Bojonegoro", coordinate: [112.0167, -7.1500], zoom: 13 },
  { name: "Tambakrejo", zona: "Barat", desc: "Kawasan Hutan Barat Daya", coordinate: [111.6500, -7.2333], zoom: 13 },
  { name: "Temayang", zona: "Selatan", desc: "Kawasan Wisata Waduk Pacal", coordinate: [111.8833, -7.3167], zoom: 13 },
  { name: "Trucuk", zona: "Kota", desc: "Utara Jembatan Sosrodilogo", coordinate: [111.8667, -7.1333], zoom: 13 },
];

const filteredKecamatan = computed(() => {
  const q = kecamatanQuery.value.trim().toLowerCase();
  if (!q) return KECAMATAN_ENTRIES;
  return KECAMATAN_ENTRIES.filter(
    (k) => k.name.toLowerCase().includes(q) || k.zona.toLowerCase().includes(q) || k.desc.toLowerCase().includes(q)
  );
});

function handleKecamatanClick(k: KecamatanEntry) {
  const isCurrentlySelected = props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase();
  if (isCurrentlySelected) {
    emit("filter-wilayah", null);
  } else {
    emit("filter-wilayah", k.name);
    emit("select-kecamatan", {
      name: `Kecamatan ${k.name}`,
      coordinate: k.coordinate,
      zoom: k.zoom,
    });
  }
}

function handleResetWilayahFilter() {
  emit("filter-wilayah", null);
  emit("zoom-to-home");
}

function toggleLayerTool(layer: ActiveLayerItem, tool: ActiveLayerTool) {
  if (layer.activeTool === tool) {
    layer.activeTool = "none";
  } else {
    layer.activeTool = tool;
    if (!layer.expanded) {
      layer.expanded = true;
    }
  }
}

function hasLayerFilter(layer: ActiveLayerItem): boolean {
  return (
    !!(layer.filterKecamatan && layer.filterKecamatan !== "ALL") ||
    !!(layer.filterStatus && layer.filterStatus !== "ALL")
  );
}

function resetLayerFilter(layer: ActiveLayerItem) {
  layer.filterKecamatan = null;
  layer.filterStatus = null;
  emit("update-layer-filter", {
    layerId: layer.id,
    kecamatan: null,
    status: null,
  });
}

function handleFilterKecamatanChange(layer: ActiveLayerItem, val: string) {
  layer.filterKecamatan = val === "ALL" ? null : val;
  emit("update-layer-filter", {
    layerId: layer.id,
    kecamatan: layer.filterKecamatan,
    status: layer.filterStatus || null,
  });
}

function handleFilterStatusChange(layer: ActiveLayerItem, val: string) {
  layer.filterStatus = val === "ALL" ? null : val;
  emit("update-layer-filter", {
    layerId: layer.id,
    kecamatan: layer.filterKecamatan || null,
    status: layer.filterStatus,
  });
}

// ═══ Layer Ordering (Reorder Up / Down & Drag-and-Drop) ═══
const recentlyMovedLayerId = ref<string | null>(null);
let movedHighlightTimer: ReturnType<typeof setTimeout> | null = null;

function moveLayer(fromIndex: number, direction: -1 | 1) {
  const toIndex = fromIndex + direction;
  if (toIndex < 0 || toIndex >= props.activeLayers.length) return;
  const list = [...props.activeLayers];
  const [item] = list.splice(fromIndex, 1);
  list.splice(toIndex, 0, item);

  recentlyMovedLayerId.value = item.id;
  if (movedHighlightTimer) clearTimeout(movedHighlightTimer);
  movedHighlightTimer = setTimeout(() => {
    recentlyMovedLayerId.value = null;
  }, 400);

  emit("reorder-layers", list);
}

const draggedLayerIndex = ref<number | null>(null);

function onDragStart(index: number, e: DragEvent) {
  draggedLayerIndex.value = index;
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("text/plain", String(index));
  }
}

function onDragOver(e: DragEvent) {
  e.preventDefault();
  if (e.dataTransfer) {
    e.dataTransfer.dropEffect = "move";
  }
}

function onDrop(targetIndex: number) {
  if (draggedLayerIndex.value === null || draggedLayerIndex.value === targetIndex) {
    draggedLayerIndex.value = null;
    return;
  }
  const list = [...props.activeLayers];
  const [item] = list.splice(draggedLayerIndex.value, 1);
  list.splice(targetIndex, 0, item);

  recentlyMovedLayerId.value = item.id;
  if (movedHighlightTimer) clearTimeout(movedHighlightTimer);
  movedHighlightTimer = setTimeout(() => {
    recentlyMovedLayerId.value = null;
  }, 400);

  draggedLayerIndex.value = null;
  emit("reorder-layers", list);
}

onBeforeUnmount(() => {
  if (movedHighlightTimer) clearTimeout(movedHighlightTimer);
});
</script>

<template>
  <div
    :class="[
      'flex flex-col bg-white/95 dark:bg-[#09111e]/95 backdrop-blur-xl select-none transition-all duration-300 ease-out',
      isMobileDrawer
        ? 'w-full h-full'
        : 'w-84 sm:w-92 max-h-[calc(100vh-210px)] rounded-2xl border border-slate-200/90 dark:border-white/10 shadow-2xl overflow-hidden'
    ]"
  >
    <!-- ═══ 1. HEADER (Title, Quick Catalog & Close Button - Desktop Only) ═══ -->
    <div
      v-if="!isMobileDrawer"
      class="flex items-center justify-between h-13 px-3.5 border-b border-slate-100 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] shrink-0"
    >
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="size-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 shadow-xs">
          <UIcon name="i-lucide-layers" class="size-4.5 stroke-[2.25]" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-1.5">
            <h2 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 truncate">
              Panel Layer
            </h2>
            <span
              v-if="props.activeLayers.length > 0"
              class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 leading-none"
            >
              {{ props.activeLayers.length }}
            </span>
          </div>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate font-medium">
            Katalog Data & Analisis Peta
          </p>
        </div>
      </div>

      <div class="flex items-center gap-1.5 shrink-0">
        <!-- Close Panel Button -->
        <button
          type="button"
          class="size-8.5 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer"
          aria-label="Tutup panel layer"
          @click="emit('close')"
        >
          <UIcon name="i-lucide-x" class="size-4" />
        </button>
      </div>
    </div>

    <!-- ═══ 2. SEGMENTED NAVIGATION TABS (Desktop Only) ═══ -->
    <div
      v-if="!isMobileDrawer"
      class="p-2 border-b border-slate-100 dark:border-white/10 bg-slate-50/40 dark:bg-white/[0.01] shrink-0"
    >
      <div
        role="tablist"
        aria-label="Navigasi Panel Layer"
        class="grid grid-cols-3 gap-1 p-0.5 bg-slate-200/60 dark:bg-slate-800/60 rounded-xl text-xs font-medium"
      >
        <!-- Tab 1: Layer Aktif -->
        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'layers'"
          class="py-1.5 px-1.5 min-h-[36px] rounded-lg flex items-center justify-center gap-1 transition-all cursor-pointer text-[11px]"
          :class="currentActiveTab === 'layers'
            ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          @click="localActiveTab = 'layers'"
        >
          <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
          <span class="truncate">Layer</span>
          <span
            v-if="props.activeLayers.length > 0"
            class="px-1 py-0.2 rounded text-[9px] font-mono leading-none"
            :class="currentActiveTab === 'layers'
              ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300'
              : 'bg-slate-300/60 dark:bg-slate-700 text-slate-700 dark:text-slate-300'"
          >
            {{ props.activeLayers.length }}
          </span>
        </button>

        <!-- Tab 2: Geoportal Bojonegoro -->
        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'geoportal'"
          class="py-1.5 px-1.5 min-h-[36px] rounded-lg flex items-center justify-center gap-1 transition-all cursor-pointer text-[11px]"
          :class="currentActiveTab === 'geoportal'
            ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          @click="localActiveTab = 'geoportal'"
        >
          <UIcon name="i-lucide-globe-2" class="size-3.5 shrink-0" />
          <span class="truncate">Geoportal</span>
        </button>

        <!-- Tab 3: Cari Wilayah -->
        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'kecamatan'"
          class="py-1.5 px-1.5 min-h-[36px] rounded-lg flex items-center justify-center gap-1 transition-all cursor-pointer text-[11px]"
          :class="currentActiveTab === 'kecamatan'
            ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          @click="localActiveTab = 'kecamatan'"
        >
          <UIcon name="i-lucide-map-pin" class="size-3.5 shrink-0" />
          <span class="truncate">Wilayah</span>
        </button>
      </div>
    </div>

    <!-- ═══ 3. BODY CONTENT AREA ═══ -->
    <div class="flex-1 overflow-y-auto p-3 min-h-0">
      <Transition name="tab-fade" mode="out-in">
        <!-- ─── TAB 1: LAYER ITEMS BERDASARKAN KATALOG ───────────────────────── -->
        <div v-if="currentActiveTab === 'layers'" key="tab-layers" class="space-y-3">
          <!-- ── SEKSI PEMILIH BASEMAP CEPAT (OPSI B) ── -->
        <div class="space-y-1.5 p-2 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/80 dark:border-white/10">
          <div class="flex items-center justify-between px-1">
            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300">
              Peta Dasar (Basemap)
            </span>
            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold font-mono">
              {{ BASEMAP_OPTIONS.find(b => b.key === props.currentBasemap)?.name }}
            </span>
          </div>

          <!-- Kartu Pilihan Basemap Horizontal -->
          <div class="grid grid-cols-6 gap-1 pt-0.5" role="radiogroup" aria-label="Pilihan Peta Dasar">
            <button
              v-for="b in BASEMAP_OPTIONS"
              :key="b.key"
              type="button"
              role="radio"
              :aria-checked="props.currentBasemap === b.key"
              class="flex flex-col items-center p-1 rounded-lg transition-all cursor-pointer group text-center focus-visible:outline-hidden"
              :class="props.currentBasemap === b.key
                ? 'bg-blue-50 dark:bg-blue-950/60 ring-1.5 ring-blue-500'
                : 'hover:bg-slate-200/60 dark:hover:bg-white/5'"
              @click="selectBasemap(b.key)"
            >
              <!-- Thumbnail Icon Representatif -->
              <div
                class="size-8 rounded-md flex items-center justify-center transition-transform group-active:scale-95 shadow-xs"
                :class="props.currentBasemap === b.key
                  ? 'bg-blue-600 text-white'
                  : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white'"
              >
                <UIcon v-if="b.key === 'esri' || b.key === 'positron'" name="i-lucide-compass" class="size-4" />
                <UIcon v-else-if="b.key === 'rbi'" name="i-lucide-flag" class="size-4" />
                <UIcon v-else-if="b.key === 'osm' || b.key === 'topo'" name="i-lucide-map" class="size-4" />
                <UIcon v-else-if="b.key === 'satellite'" name="i-lucide-globe" class="size-4" />
                <UIcon v-else-if="b.key === 'aerial'" name="i-lucide-camera" class="size-4" />
                <UIcon v-else name="i-lucide-square" class="size-4" />
              </div>

              <span
                class="text-[10px] mt-1 leading-tight truncate w-full tracking-tight"
                :class="props.currentBasemap === b.key
                  ? 'font-bold text-blue-600 dark:text-blue-400'
                  : 'font-medium text-slate-600 dark:text-slate-400'"
              >
                {{ b.name }}
              </span>
            </button>
          </div>
        </div>

        <!-- Subheader: Count & Ordering Guide -->
        <div v-if="props.activeLayers.length > 0" class="flex items-center justify-between px-1">
          <div class="flex items-center gap-1.5">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider truncate">
              Urutan Layer
            </span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
              {{ props.activeLayers.length }}
            </span>
          </div>
          <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium flex items-center gap-1">
            <UIcon name="i-lucide-arrow-up-down" class="size-3" />
            <span>Atas = Paling Depan</span>
          </span>
        </div>

        <!-- Empty State jika belum ada layer aktif -->
        <div
          v-if="props.activeLayers.length === 0"
          class="p-5 rounded-2xl border border-dashed border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] text-center space-y-3.5"
        >
          <div class="size-11 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center shadow-xs">
            <UIcon name="i-lucide-layers-2" class="size-5.5" />
          </div>

          <div class="space-y-1">
            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
              Belum Ada Layer Aktif
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed px-1">
              Tambahkan layer tematik dari katalog data spasial untuk dianalisis di atas peta Bojonegoro.
            </p>
          </div>

          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
            @click="localActiveTab = 'geoportal'"
          >
            <UIcon name="i-lucide-globe-2" class="size-4" />
            <span>Jelajahi Katalog Geoportal</span>
          </button>
        </div>

        <!-- Daftar Kartu Layer Aktif dengan Reordering & Transisi Halus -->
        <TransitionGroup
          v-else
          name="layer-card"
          tag="div"
          class="space-y-2 relative"
        >
          <div
            v-for="(layer, index) in props.activeLayers"
            :key="layer.id"
            draggable="true"
            class="rounded-xl border transition-all duration-200 overflow-hidden"
            :class="[
              layer.visible
                ? 'border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0c1424] shadow-xs'
                : 'border-slate-200/50 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] opacity-75',
              draggedLayerIndex === index ? 'opacity-40 border-dashed border-blue-500 ring-2 ring-blue-500/30' : '',
              recentlyMovedLayerId === layer.id ? 'ring-2 ring-blue-500/70 border-blue-500 bg-blue-50/40 dark:bg-blue-950/30 shadow-md shadow-blue-500/10' : ''
            ]"
            @dragstart="onDragStart(index, $event)"
            @dragover="onDragOver($event)"
            @drop="onDrop(index)"
            @dragend="draggedLayerIndex = null"
          >
            <!-- 1. Card Header: Title + Eye Toggle + Chevron Toggle -->
            <div
              class="flex items-center justify-between px-2.5 py-2 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-white/[0.03] transition-colors"
              @click="layer.expanded = !layer.expanded"
            >
              <div class="flex items-center gap-2 min-w-0 pr-1 flex-1">
                <!-- Drag Grip & Order Number Badge -->
                <div
                  class="flex items-center gap-1 shrink-0 text-slate-400 cursor-grab active:cursor-grabbing"
                  title="Tarik untuk mengubah urutan layer"
                  @click.stop
                >
                  <UIcon name="i-lucide-grip-vertical" class="size-3 text-slate-300 dark:text-slate-600 hover:text-slate-500" />
                  <span class="text-[9px] font-mono font-bold px-1 py-0.2 rounded bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400">
                    #{{ index + 1 }}
                  </span>
                </div>

                <!-- Status indicator dot -->
                <span
                  class="size-2 rounded-full shrink-0 transition-colors"
                  :class="layer.visible ? 'bg-blue-500 ring-2 ring-blue-500/20' : 'bg-slate-300 dark:bg-slate-600'"
                />

                <!-- Layer Title & Metadata Subtitle -->
                <div class="min-w-0 flex-1">
                  <span
                    class="block text-xs font-semibold truncate tracking-tight"
                    :class="layer.visible ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500'"
                    :title="layer.title || layer.name"
                  >
                    {{ layer.title || layer.name }}
                  </span>
                  <span class="block text-[10px] text-slate-400 dark:text-slate-500 truncate">
                    <template v-if="layer.instansi || layer.attribution || layer.agency">
                      {{ layer.instansi || layer.attribution || layer.agency }}
                    </template>
                    <template v-else-if="layer.layer_name || layer.wmsLayerName">
                      {{ layer.layer_name || layer.wmsLayerName }}
                    </template>
                    <template v-else>
                      {{ layer.protocol || "WMS" }}
                    </template>
                  </span>
                </div>
              </div>

              <!-- Quick Header Actions -->
              <div class="flex items-center gap-0.5 shrink-0" @click.stop>
                <!-- Quick Reorder Buttons: Naik / Turun -->
                <div class="flex items-center gap-0.5 border-r border-slate-200/80 dark:border-white/10 pr-1 mr-0.5">
                  <UTooltip text="Pindah ke atas (urutan tumpukan lebih depan)">
                    <button
                      type="button"
                      :disabled="index === 0"
                      class="size-6.5 rounded-md flex items-center justify-center transition-all"
                      :class="index === 0
                        ? 'text-slate-200 dark:text-slate-800 cursor-not-allowed opacity-30'
                        : 'text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/5 active:scale-90 cursor-pointer'"
                      aria-label="Pindah layer ke atas"
                      @click="moveLayer(index, -1)"
                    >
                      <UIcon name="i-lucide-arrow-up" class="size-3.5 stroke-[2.5]" />
                    </button>
                  </UTooltip>

                  <UTooltip text="Pindah ke bawah (urutan tumpukan lebih belakang)">
                    <button
                      type="button"
                      :disabled="index === props.activeLayers.length - 1"
                      class="size-6.5 rounded-md flex items-center justify-center transition-all"
                      :class="index === props.activeLayers.length - 1
                        ? 'text-slate-200 dark:text-slate-800 cursor-not-allowed opacity-30'
                        : 'text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/5 active:scale-90 cursor-pointer'"
                      aria-label="Pindah layer ke bawah"
                      @click="moveLayer(index, 1)"
                    >
                      <UIcon name="i-lucide-arrow-down" class="size-3.5 stroke-[2.5]" />
                    </button>
                  </UTooltip>
                </div>

                <!-- Quick Toggle Eye Button -->
                <UTooltip :text="layer.visible ? 'Sembunyikan layer di peta' : 'Tampilkan layer di peta'">
                  <button
                    type="button"
                    class="size-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
                    :class="[
                      layer.visible
                        ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40'
                        : 'text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5'
                    ]"
                    aria-label="Toggle visibilitas layer"
                    @click="emit('toggle-layer-visibility', layer.id)"
                  >
                    <UIcon :name="layer.visible ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="size-4" />
                  </button>
                </UTooltip>

                <!-- Chevron Accordion Toggle -->
                <button
                  type="button"
                  class="size-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/5 transition-transform duration-200 cursor-pointer"
                  :class="layer.expanded ? 'rotate-0' : 'rotate-180'"
                  :title="layer.expanded ? 'Sembunyikan alat detail' : 'Buka alat detail'"
                  @click="layer.expanded = !layer.expanded"
                >
                  <UIcon name="i-lucide-chevron-up" class="size-3.5" />
                </button>
              </div>
            </div>

            <!-- 2. Card Body: Action Toolbar + Inline Tool Panels dengan transisi tinggi halus (expand/collapse) -->
            <div
              class="grid transition-[grid-template-rows,opacity] duration-300 ease-out"
              :class="layer.expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'"
            >
              <div class="overflow-hidden">
                <div class="px-2.5 pb-2.5 space-y-2 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01]">
                  <!-- Toolbar Alat Layer -->
                  <div class="flex items-center justify-between pt-1.5 text-slate-500 dark:text-slate-400">
                    <!-- 2.1 Visibilitas -->
                    <UTooltip :text="layer.visible ? 'Sembunyikan di peta' : 'Tampilkan di peta'">
                      <button
                        type="button"
                        class="size-8.5 flex items-center justify-center rounded-lg transition-colors cursor-pointer"
                        :class="layer.visible ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5'"
                        aria-label="Toggle visibilitas"
                        @click="emit('toggle-layer-visibility', layer.id)"
                      >
                        <UIcon :name="layer.visible ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="size-3.5" />
                      </button>
                    </UTooltip>

                    <!-- 2.2 Transparansi / Opasitas -->
                    <UTooltip text="Atur transparansi layer">
                      <button
                        type="button"
                        class="size-8.5 flex items-center justify-center rounded-lg transition-colors cursor-pointer"
                        :class="layer.activeTool === 'opacity'
                          ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 font-semibold'
                          : 'hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-800 dark:hover:text-slate-200'"
                        aria-label="Atur transparansi"
                        @click="toggleLayerTool(layer, 'opacity')"
                      >
                        <UIcon name="i-lucide-sun-medium" class="size-3.5" />
                      </button>
                    </UTooltip>

                    <!-- 2.3 Zoom ke Cakupan Layer -->
                    <UTooltip text="Pusatkan peta ke cakupan layer ini">
                      <button
                        type="button"
                        class="size-8.5 flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-800 dark:hover:text-slate-200 transition-colors cursor-pointer"
                        aria-label="Zoom ke layer"
                        @click="emit('zoom-to-layer', layer)"
                      >
                        <UIcon name="i-lucide-maximize-2" class="size-3.5" />
                      </button>
                    </UTooltip>

                    <!-- 2.4 Filter Data Wilayah -->
                    <UTooltip text="Filter data wilayah layer ini">
                      <button
                        type="button"
                        class="size-8.5 flex items-center justify-center rounded-lg transition-colors relative cursor-pointer"
                        :class="[
                          layer.activeTool === 'filter'
                            ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 font-semibold'
                            : hasLayerFilter(layer)
                              ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40'
                              : 'hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-800 dark:hover:text-slate-200'
                        ]"
                        aria-label="Filter data layer"
                        @click="toggleLayerTool(layer, 'filter')"
                      >
                        <UIcon name="i-lucide-filter" class="size-3.5" />
                        <span
                          v-if="hasLayerFilter(layer)"
                          class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-blue-500 ring-2 ring-white dark:ring-[#0c1424]"
                        />
                      </button>
                    </UTooltip>

                    <!-- 2.5 Informasi Metadata -->
                    <UTooltip text="Informasi metadata layer">
                      <button
                        type="button"
                        class="size-8.5 flex items-center justify-center rounded-lg transition-colors cursor-pointer"
                        :class="layer.activeTool === 'info'
                          ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 font-semibold'
                          : 'hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-800 dark:hover:text-slate-200'"
                        aria-label="Metadata layer"
                        @click="toggleLayerTool(layer, 'info')"
                      >
                        <UIcon name="i-lucide-info" class="size-3.5" />
                      </button>
                    </UTooltip>

                    <!-- 2.6 Hapus Layer Item -->
                    <UTooltip text="Keluarkan layer dari peta">
                      <button
                        type="button"
                        class="size-8.5 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors cursor-pointer"
                        aria-label="Hapus layer"
                        @click="emit('remove-layer', layer.id)"
                      >
                        <UIcon name="i-lucide-trash-2" class="size-3.5" />
                      </button>
                    </UTooltip>
                  </div>

                  <!-- ── Sub-panel Alat dengan Transisi Halus ── -->
                  <Transition name="subtool" mode="out-in">
                    <!-- ── Sub-panel 1: Transparansi Slider ── -->
                    <div
                      v-if="layer.activeTool === 'opacity'"
                      key="tool-opacity"
                      class="pt-2 border-t border-slate-200/60 dark:border-white/5 space-y-2"
                    >
                      <div class="flex justify-between items-center text-[11px]">
                        <span class="text-slate-600 dark:text-slate-300 font-medium">Opasitas:</span>
                        <span class="font-mono text-blue-600 dark:text-blue-400 font-bold">
                          {{ Math.round(layer.opacity * 100) }}%
                        </span>
                      </div>
                      <USlider
                        :model-value="layer.opacity"
                        :min="0.1"
                        :max="1"
                        :step="0.05"
                        size="xs"
                        color="primary"
                        @update:model-value="(val: any) => emit('update-layer-opacity', { layerId: layer.id, opacity: Number(val) })"
                      />
                      <div class="grid grid-cols-4 gap-1 pt-1">
                        <button
                          v-for="preset in [0.25, 0.5, 0.75, 1]"
                          :key="preset"
                          type="button"
                          class="py-1 px-1 rounded-md text-[10px] font-mono transition-colors text-center cursor-pointer min-h-[26px]"
                          :class="Math.abs(layer.opacity - preset) < 0.05
                            ? 'bg-blue-600 text-white font-bold'
                            : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
                          @click="emit('update-layer-opacity', { layerId: layer.id, opacity: preset })"
                        >
                          {{ Math.round(preset * 100) }}%
                        </button>
                      </div>
                    </div>

                    <!-- ── Sub-panel 2: Filter Data Wilayah ── -->
                    <div
                      v-else-if="layer.activeTool === 'filter'"
                      key="tool-filter"
                      class="pt-2 border-t border-slate-200/60 dark:border-white/5 space-y-2"
                    >
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                          <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">
                            Filter Wilayah
                          </span>
                          <span
                            v-if="hasLayerFilter(layer)"
                            class="px-1.5 py-0.2 rounded-md text-[9px] font-bold bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400"
                          >
                            Aktif
                          </span>
                        </div>

                        <button
                          v-if="hasLayerFilter(layer)"
                          type="button"
                          class="text-[10px] text-blue-600 dark:text-blue-400 hover:underline font-medium cursor-pointer"
                          @click="resetLayerFilter(layer)"
                        >
                          Reset Filter
                        </button>
                      </div>

                      <div class="space-y-1">
                        <label class="text-[10px] font-medium text-slate-500 dark:text-slate-400 block">Kecamatan</label>
                        <select
                          :value="layer.filterKecamatan || 'ALL'"
                          class="w-full bg-white dark:bg-[#09111e] border border-slate-200 dark:border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-hidden focus:border-blue-500 min-h-[34px]"
                          @change="handleFilterKecamatanChange(layer, ($event.target as HTMLSelectElement).value)"
                        >
                          <option value="ALL">Semua Kecamatan (28 Wilayah)</option>
                          <option v-for="k in KECAMATAN_ENTRIES" :key="k.name" :value="k.name">
                            Kecamatan {{ k.name }} (Zona {{ k.zona }})
                          </option>
                        </select>
                      </div>
                    </div>

                    <!-- ── Sub-panel 3: Informasi Metadata ── -->
                    <div
                      v-else-if="layer.activeTool === 'info'"
                      key="tool-info"
                      class="pt-2 border-t border-slate-200/60 dark:border-white/5 space-y-1.5 text-xs"
                    >
                      <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200 block">
                        Detail Metadata
                      </span>
                      <div class="rounded-lg bg-white dark:bg-[#09111e] p-2 space-y-1 border border-slate-100 dark:border-white/10 text-[11px]">
                        <div v-if="layer.instansi || layer.attribution || layer.agency" class="flex justify-between gap-2">
                          <span class="text-slate-400 shrink-0">Instansi:</span>
                          <span class="text-slate-800 dark:text-slate-200 text-right truncate font-medium">
                            {{ layer.instansi || layer.attribution || layer.agency }}
                          </span>
                        </div>
                        <div v-if="layer.layer_name || layer.wmsLayerName" class="flex justify-between gap-2">
                          <span class="text-slate-400 shrink-0">Layer:</span>
                          <span class="font-mono text-slate-800 dark:text-slate-200 truncate text-right">
                            {{ layer.layer_name || layer.wmsLayerName }}
                          </span>
                        </div>
                        <div v-if="layer.protocol" class="flex justify-between">
                          <span class="text-slate-400">Protokol:</span>
                          <span class="font-mono uppercase text-slate-700 dark:text-slate-300">{{ layer.protocol }}</span>
                        </div>
                        <div v-if="layer.legend_url" class="pt-1.5 border-t border-slate-100 dark:border-white/5 space-y-1">
                          <span class="text-[10px] text-slate-400 block font-semibold">Simbol Legenda:</span>
                          <img
                            :src="layer.legend_url"
                            alt="Legenda Resmi"
                            class="max-h-24 object-contain rounded bg-white p-1 border border-slate-200/80"
                            loading="lazy"
                          />
                        </div>
                        <div v-else-if="layer.layer_name === 'bataswilayah_kabupaten' || layer.id === '01a15e28-a042-71a0-b111-ed7d9256e5bf'" class="pt-1.5 border-t border-slate-100 dark:border-white/5 space-y-1">
                          <span class="text-[10px] text-slate-400 block font-semibold">Simbol Kartografi:</span>
                          <div class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-white/10">
                            <svg class="w-12 h-3 shrink-0" viewBox="0 0 48 12">
                              <line x1="0" y1="6" x2="48" y2="6" stroke="currentColor" class="text-slate-400/30 dark:text-slate-500/30" stroke-width="6" stroke-linecap="round" />
                              <line x1="0" y1="6" x2="48" y2="6" stroke="currentColor" class="text-slate-600 dark:text-slate-300" stroke-width="2.5" stroke-dasharray="10,3,2,3,2,3" stroke-linecap="round" />
                            </svg>
                            <span class="text-[10px] font-medium text-slate-700 dark:text-slate-300">
                              Batas Administrasi Kabupaten (BIG)
                            </span>
                          </div>
                        </div>
                        <p v-if="layer.description" class="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-white/5 leading-relaxed">
                          {{ layer.description }}
                        </p>
                      </div>
                    </div>
                  </Transition>
                </div>
              </div>
            </div>
          </div>
        </TransitionGroup>
      </div>

      <!-- ─── TAB 2: KATALOG GEOPORTAL BOJONEGORO ──────────────────────────── -->
      <div v-else-if="currentActiveTab === 'geoportal'" key="tab-geoportal" class="space-y-3">
        <GeoportalLayerSection
          :active-layers="props.activeLayers"
          @toggle-layer="handleGeoportalToggle"
          @update-opacity="handleGeoportalOpacity"
        />
      </div>

      <!-- ─── TAB 3: FILTER WILAYAH MVT (28 Kecamatan) ─────────────────── -->
      <div v-else-if="currentActiveTab === 'kecamatan'" key="tab-kecamatan" class="space-y-2.5">
        <!-- Active Filter Alert Banner -->
        <div
          v-if="props.selectedKecamatanFilter"
          class="p-2.5 rounded-xl bg-blue-50/90 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-900/60 flex items-center justify-between gap-2 shadow-xs"
        >
          <div class="flex items-center gap-2 min-w-0">
            <div class="size-7 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-filter" class="size-3.5" />
            </div>
            <div class="min-w-0">
              <div class="text-xs font-bold text-blue-900 dark:text-blue-100 truncate">
                Kecamatan {{ props.selectedKecamatanFilter }}
              </div>
              <p class="text-[10px] text-blue-600 dark:text-blue-400 font-medium truncate">
                Layer MVT terfilter ke wilayah ini
              </p>
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 px-2.5 py-1 text-[10px] font-bold rounded-lg bg-white dark:bg-[#0c1424] text-blue-700 dark:text-blue-300 border border-blue-200/80 dark:border-blue-800/60 hover:bg-blue-50 dark:hover:bg-blue-950 transition-colors shadow-xs cursor-pointer"
            @click="handleResetWilayahFilter"
          >
            Reset
          </button>
        </div>

        <div v-else class="px-1 text-[11px] text-slate-500 dark:text-slate-400">
          Pilih kecamatan untuk memfilter ruas jalan & infrastruktur MVT:
        </div>

        <!-- Search Input -->
        <div class="relative">
          <UIcon name="i-lucide-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400 pointer-events-none" />
          <input
            v-model="kecamatanQuery"
            type="text"
            placeholder="Cari dari 28 kecamatan..."
            class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-200/80 dark:border-white/10 rounded-xl pl-8 pr-7 py-1.5 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 min-h-[36px]"
          />
          <button
            v-if="kecamatanQuery"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 size-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
            @click="kecamatanQuery = ''"
          >
            <UIcon name="i-lucide-x" class="size-3.5" />
          </button>
        </div>

        <!-- Kecamatan List -->
        <div class="space-y-1 max-h-[320px] overflow-y-auto pr-0.5">
          <button
            v-for="k in filteredKecamatan"
            :key="k.name"
            type="button"
            class="w-full flex items-center justify-between p-2 rounded-xl text-left transition-all cursor-pointer group border"
            :class="props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase()
              ? 'bg-blue-50 dark:bg-blue-950/60 border-blue-500 ring-1 ring-blue-500/50 shadow-xs'
              : 'hover:bg-slate-100/80 dark:hover:bg-white/[0.04] border-transparent hover:border-slate-200/60 dark:hover:border-white/5'"
            @click="handleKecamatanClick(k)"
          >
            <div class="flex items-center gap-2 min-w-0">
              <div
                class="size-6 rounded-lg flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform"
                :class="props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase()
                  ? 'bg-blue-600 text-white'
                  : 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400'"
              >
                <UIcon
                  :name="props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase() ? 'i-lucide-check' : 'i-lucide-map-pin'"
                  class="size-3"
                />
              </div>
              <div class="min-w-0">
                <div
                  class="text-xs font-semibold truncate"
                  :class="props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase()
                    ? 'text-blue-900 dark:text-blue-200 font-bold'
                    : 'text-slate-800 dark:text-slate-200'"
                >
                  Kecamatan {{ k.name }}
                </div>
                <div class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                  {{ k.desc }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
              <span
                v-if="props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase()"
                class="text-[9px] px-1.5 py-0.2 rounded-full font-bold bg-blue-600 text-white leading-none"
              >
                Aktif
              </span>
              <span class="text-[10px] px-1.5 py-0.5 rounded-md font-mono bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400">
                {{ k.zona }}
              </span>
            </div>
          </button>

          <!-- No Match State -->
          <div v-if="filteredKecamatan.length === 0" class="p-4 text-center text-xs text-slate-400">
            Tidak ditemukan kecamatan dengan kata kunci "{{ kecamatanQuery }}"
          </div>
        </div>

        <!-- Button Reset to Entire Bojonegoro -->
        <div class="pt-1 border-t border-slate-100 dark:border-white/5">
          <button
            type="button"
            class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/[0.05] transition-colors cursor-pointer min-h-[38px]"
            @click="handleResetWilayahFilter"
          >
            <UIcon name="i-lucide-crosshair" class="size-3.5 text-blue-600 dark:text-blue-400" />
            <span>Pusatkan Peta ke Bojonegoro</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</div>
</template>

<style scoped>
/* Smooth FLIP Transition when ordering/reordering layers */
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

/* Smooth tool sub-panel transitions (opacity slider, filter, metadata) */
.subtool-enter-active,
.subtool-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.subtool-enter-from {
  opacity: 0;
  transform: translateY(-4px);
}
.subtool-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Smooth tab content fade & slide transitions */
.tab-fade-enter-active,
.tab-fade-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.tab-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.tab-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* Scrollbar styling */
::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.4);
  border-radius: 9999px;
}
::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.6);
}
</style>

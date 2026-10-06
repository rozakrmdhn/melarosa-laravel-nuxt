<script setup lang="ts">
import type {
  DesaOption,
  KecamatanOption,
  KondisiBreakdown,
  LayerSymbology,
  SymbologyColorMode,
  SymbologyLineDash,
} from "~/types/dataset-editor";
import { DEFAULT_SYMBOLOGY } from "~/types/dataset-editor";

export type ActiveToolType = "none" | "opacity" | "filter" | "symbology" | "info";

const props = withDefaults(
  defineProps<{
    collapsed?: boolean;
    width?: number;
    layerVisible?: boolean;
    layerOpacity?: number;
    kondisiStats?: KondisiBreakdown[];
    loading?: boolean;
    isMobileDrawer?: boolean;
    collapsible?: boolean;
    symbology?: LayerSymbology;

    // Filter Props
    kecamatanFilter?: string | null;
    desaFilter?: string | null;
    kondisiFilter?: string | null;
    perkerasanFilter?: string | null;
    kecamatanOptions?: KecamatanOption[];
    desaOptions?: DesaOption[];
    kondisiOptions?: string[];
    perkerasanOptions?: string[];

    // Metadata Props
    totalRuas?: number;
    totalPanjangKm?: number;
    layerTitle?: string;
    defaultTool?: ActiveToolType;
  }>(),
  {
    collapsed: false,
    width: 300,
    layerVisible: true,
    layerOpacity: 1,
    kondisiStats: () => [],
    loading: false,
    isMobileDrawer: false,
    collapsible: true,
    symbology: () => ({ ...DEFAULT_SYMBOLOGY }),
    kecamatanFilter: null,
    desaFilter: null,
    kondisiFilter: null,
    perkerasanFilter: null,
    kecamatanOptions: () => [],
    desaOptions: () => [],
    kondisiOptions: () => ["BAIK", "SEDANG", "RUSAK", "RUSAK BERAT"],
    perkerasanOptions: () => ["Aspal", "Beton", "Kerikil", "Tanah", "Lainnya"],
    totalRuas: 0,
    totalPanjangKm: 0,
    layerTitle: "Jalan Poros Desa",
    defaultTool: "none",
  }
);

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "update:layerVisible", val: boolean): void;
  (e: "update:layerOpacity", val: number): void;
  (e: "update:symbology", val: LayerSymbology): void;
  (e: "update:kecamatanFilter", val: string | null): void;
  (e: "update:desaFilter", val: string | null): void;
  (e: "update:kondisiFilter", val: string | null): void;
  (e: "update:perkerasanFilter", val: string | null): void;
  (e: "zoomToLayer"): void;
  (e: "resetFilters"): void;
  (e: "applyFilter"): void;
}>();

// ─── Layer Card State ─────────────────────────────────────────────────────────
const isLayerCardExpanded = ref(true);
const activeTool = ref<ActiveToolType>(props.defaultTool);

watch(
  () => props.defaultTool,
  (val) => {
    if (val && val !== "none") {
      activeTool.value = val;
      isLayerCardExpanded.value = true;
    }
  }
);

function toggleTool(tool: ActiveToolType) {
  if (activeTool.value === tool) {
    activeTool.value = "none";
  } else {
    activeTool.value = tool;
    if (!isLayerCardExpanded.value) {
      isLayerCardExpanded.value = true;
    }
  }
}

// ─── Filter Computation & Cascading ──────────────────────────────────────────
const selectedKecamatanObj = computed(() => {
  if (!props.kecamatanFilter) return null;
  const target = props.kecamatanFilter.toLowerCase();
  return (
    props.kecamatanOptions.find(
      (k) =>
        k.nama.toLowerCase() === target || String(k.id) === props.kecamatanFilter
    ) || null
  );
});

const kecamatanSelectItems = computed(() => [
  { label: "Semua Kecamatan", value: "ALL" },
  ...props.kecamatanOptions.map((k) => ({
    label: k.nama,
    value: k.nama,
  })),
]);

const filteredDesaList = computed(() => {
  if (!selectedKecamatanObj.value) {
    return props.desaOptions;
  }
  return props.desaOptions.filter(
    (d) => d.id_kecamatan === selectedKecamatanObj.value?.id
  );
});

const desaSelectItems = computed(() => [
  {
    label: selectedKecamatanObj.value
      ? `Semua Desa (${selectedKecamatanObj.value.nama})`
      : "Semua Desa",
    value: "ALL",
  },
  ...filteredDesaList.value.map((d) => ({
    label: d.nama,
    value: d.nama,
  })),
]);

function handleKecamatanChange(val: any) {
  const newVal = !val || val === "ALL" ? null : String(val);
  emit("update:kecamatanFilter", newVal);

  if (props.desaFilter && newVal) {
    const targetKec = props.kecamatanOptions.find(
      (k) => k.nama.toLowerCase() === newVal.toLowerCase()
    );
    if (targetKec) {
      const exists = props.desaOptions.some(
        (d) =>
          d.id_kecamatan === targetKec.id &&
          d.nama.toLowerCase() === props.desaFilter?.toLowerCase()
      );
      if (!exists) {
        emit("update:desaFilter", null);
      }
    }
  }
}

function handleDesaChange(val: any) {
  const newVal = !val || val === "ALL" ? null : String(val);
  emit("update:desaFilter", newVal);
}

const kondisiSelectItems = computed(() => [
  { label: "Semua Kondisi", value: "ALL" },
  ...props.kondisiOptions.map((k) => ({ label: k, value: k })),
]);

const perkerasanSelectItems = computed(() => [
  { label: "Semua Perkerasan", value: "ALL" },
  ...props.perkerasanOptions.map((p) => ({ label: p, value: p })),
]);

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.kecamatanFilter) count++;
  if (props.desaFilter) count++;
  if (props.kondisiFilter) count++;
  if (props.perkerasanFilter) count++;
  return count;
});

const hasActiveFilter = computed(() => activeFiltersCount.value > 0);

// ─── Symbology Controls ───────────────────────────────────────────────────────
const symbologyTab = ref<"line" | "label">("line");
const activeSymbology = computed(() => props.symbology || DEFAULT_SYMBOLOGY);

function updateSymbology<K extends keyof LayerSymbology>(key: K, val: LayerSymbology[K]) {
  emit("update:symbology", {
    ...activeSymbology.value,
    [key]: val,
  });
}

function resetSymbology() {
  emit("update:symbology", { ...DEFAULT_SYMBOLOGY });
}

const COLOR_PRESETS = [
  { label: "blue", hex: "#059669" },
  { label: "Sky", hex: "#0284c7" },
  { label: "Indigo", hex: "#6366f1" },
  { label: "Purple", hex: "#9333ea" },
  { label: "Rose", hex: "#e11d48" },
  { label: "Amber", hex: "#d97706" },
  { label: "Orange", hex: "#ea580c" },
  { label: "Slate", hex: "#475569" },
];

const labelFieldItems = [
  { label: "Nama Ruas", value: "nama_ruas" },
  { label: "Kode Ruas", value: "kode_ruas" },
  { label: "Kondisi Jalan", value: "kondisi" },
  { label: "Panjang Ruas", value: "panjang" },
];

const lineDashItems = [
  { label: "Solid", value: "solid" as SymbologyLineDash },
  { label: "Putus-putus", value: "dashed" as SymbologyLineDash },
  { label: "Titik", value: "dotted" as SymbologyLineDash },
];

const colorModeItems = [
  { label: "Satu Warna", value: "single" as SymbologyColorMode },
  { label: "Kondisi", value: "kondisi" as SymbologyColorMode },
  { label: "Perkerasan", value: "perkerasan" as SymbologyColorMode },
];
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col overflow-hidden w-full h-full',
      'bg-white dark:bg-[#0b0f19]'
    ]"
  >
    <!-- Header Panel Kiri: Title & (Desktop) Collapse Button -->
    <div
      class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 justify-between select-none"
    >
      <Transition name="panel-subtle-fade">
        <div v-if="!collapsed" class="flex items-center gap-2 overflow-hidden">
          <UIcon name="i-lucide-layers" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            Daftar Layer & Data
          </span>
        </div>
      </Transition>

      <UTooltip v-if="!isMobileDrawer && collapsible" :text="collapsed ? 'Buka panel layer' : 'Tutup panel layer'">
        <UButton
          icon="i-lucide-chevron-left"
          size="xs"
          color="neutral"
          variant="ghost"
          :class="[
            'shrink-0 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
            collapsed ? 'rotate-180' : 'rotate-0'
          ]"
          @click="emit('update:collapsed', !collapsed)"
        />
      </UTooltip>
    </div>

    <Transition name="panel-fade" mode="out-in">
      <!-- Desktop Collapsed Rail -->
      <div
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
        class="flex flex-col items-center pt-3 gap-3 flex-1 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors"
        title="Klik untuk membuka panel layer"
        @click="emit('update:collapsed', false)"
      >
        <UTooltip text="Layer & Data">
          <div class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon name="i-lucide-layers" class="size-4 text-gray-400 dark:text-gray-500" />
          </div>
        </UTooltip>
        <UTooltip text="Jalan Poros Desa">
          <div class="relative p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon
              name="i-lucide-route"
              :class="['size-4', layerVisible ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600']"
            />
            <span
              v-if="hasActiveFilter"
              class="absolute top-1 right-1 size-1.5 rounded-full bg-blue-500 ring-1 ring-white dark:ring-[#0b0f19]"
            />
          </div>
        </UTooltip>
      </div>

      <!-- Expanded Body Content -->
      <div
        v-else
        key="body"
        class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden p-3 md:p-3.5 space-y-3"
      >
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <!-- DEDICATED GIS LAYER CARD (Sesuai Referensi Visual)                  -->
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <div
          class="rounded-lg border border-gray-200 dark:border-gray-800/90 bg-gray-50/70 dark:bg-[#0e1424] shadow-xs overflow-hidden transition-all duration-200"
        >
          <!-- 1. Header Layer Card: Drag Grip + Title + Chevron Toggle -->
          <div
            class="flex items-center justify-between px-2.5 py-2 cursor-pointer hover:bg-gray-100/60 dark:hover:bg-gray-800/40 transition-colors"
            @click="isLayerCardExpanded = !isLayerCardExpanded"
          >
            <div class="flex items-center gap-2 min-w-0 pr-1">
              <!-- Grip Icon (Drag Handle) -->
              <UIcon
                name="i-lucide-grip-vertical"
                class="size-4 text-gray-400 dark:text-gray-500 shrink-0 cursor-grab active:cursor-grabbing"
                title="Urutan Layer"
              />
              <!-- Layer Title -->
              <span
                class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate tracking-tight"
                :title="layerTitle"
              >
                {{ layerTitle }}
              </span>
            </div>

            <!-- Chevron Expand/Collapse -->
            <button
              type="button"
              class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-transform duration-200 cursor-pointer"
              :class="isLayerCardExpanded ? 'rotate-0' : 'rotate-180'"
              :title="isLayerCardExpanded ? 'Sembunyikan detail layer' : 'Buka detail layer'"
              @click.stop="isLayerCardExpanded = !isLayerCardExpanded"
            >
              <UIcon name="i-lucide-chevron-up" class="size-4" />
            </button>
          </div>

          <!-- Card Body (Toolbar + Inline Sections + Legend) -->
          <div v-show="isLayerCardExpanded" class="px-2.5 pb-2.5 space-y-2.5">
            <!-- 2. Action Toolbar (6 + 1 Ikon Aksi Sesuai Referensi) -->
            <div
              class="flex items-center justify-between pt-1 border-t border-gray-200/60 dark:border-gray-800/60 text-gray-500 dark:text-gray-400"
            >
              <!-- 2.1 Ikon Visibilitas (Eye) -->
              <UTooltip :text="layerVisible ? 'Sembunyikan layer di peta' : 'Tampilkan layer di peta'">
                <button
                  type="button"
                  class="p-1.5 rounded-md transition-colors cursor-pointer"
                  :class="[
                    layerVisible
                      ? 'text-cyan-500 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-950/40'
                      : 'text-gray-400 dark:text-gray-600 hover:text-gray-700 dark:hover:text-gray-300'
                  ]"
                  @click="emit('update:layerVisible', !layerVisible)"
                >
                  <UIcon :name="layerVisible ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="size-4" />
                </button>
              </UTooltip>

              <!-- 2.2 Ikon Opasitas (Sun / Transparency) -->
              <UTooltip text="Atur transparansi layer">
                <button
                  type="button"
                  class="p-1.5 rounded-md transition-colors cursor-pointer"
                  :class="[
                    activeTool === 'opacity'
                      ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400'
                      : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                  ]"
                  @click="toggleTool('opacity')"
                >
                  <UIcon name="i-lucide-sun-medium" class="size-4" />
                </button>
              </UTooltip>

              <!-- 2.3 Ikon Zoom to Extent (Fit Bounds) -->
              <UTooltip text="Zoom ke seluruh cakupan ruas layer ini">
                <button
                  type="button"
                  class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200 transition-colors cursor-pointer"
                  @click="emit('zoomToLayer')"
                >
                  <UIcon name="i-lucide-maximize-2" class="size-4" />
                </button>
              </UTooltip>

              <!-- 2.4 Ikon Dedicated Filter Layer (Funnel) -->
              <UTooltip text="Filter data ruas pada layer ini">
                <button
                  type="button"
                  class="p-1.5 rounded-md transition-colors relative cursor-pointer"
                  :class="[
                    activeTool === 'filter'
                      ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 font-semibold'
                      : hasActiveFilter
                        ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                  ]"
                  @click="toggleTool('filter')"
                >
                  <UIcon name="i-lucide-filter" class="size-4" />
                  <!-- Indikator dot / badge filter aktif -->
                  <span
                    v-if="hasActiveFilter"
                    class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-500 ring-2 ring-white dark:ring-[#0e1424]"
                    :title="`${activeFiltersCount} filter aktif`"
                  />
                </button>
              </UTooltip>

              <!-- 2.5 Ikon Simbologi & Warna (Palette) -->
              <UTooltip text="Ubah gaya garis & label peta">
                <button
                  type="button"
                  class="p-1.5 rounded-md transition-colors cursor-pointer"
                  :class="[
                    activeTool === 'symbology'
                      ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400'
                      : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                  ]"
                  @click="toggleTool('symbology')"
                >
                  <UIcon name="i-lucide-palette" class="size-4" />
                </button>
              </UTooltip>

              <!-- 2.6 Ikon Info / Metadata Layer -->
              <UTooltip text="Informasi & ringkasan layer">
                <button
                  type="button"
                  class="p-1.5 rounded-md transition-colors cursor-pointer"
                  :class="[
                    activeTool === 'info'
                      ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400'
                      : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                  ]"
                  @click="toggleTool('info')"
                >
                  <UIcon name="i-lucide-info" class="size-4" />
                </button>
              </UTooltip>

              <!-- 2.7 Ikon Hapus Layer (Disabled untuk layer utama) -->
              <UTooltip text="Layer utama sistem (tidak dapat dihapus)">
                <button
                  type="button"
                  disabled
                  class="p-1.5 rounded-md opacity-40 cursor-not-allowed text-gray-400"
                >
                  <UIcon name="i-lucide-trash-2" class="size-4" />
                </button>
              </UTooltip>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- INLINE EXPAND PANELS (Slide Down berdasarkan tool aktif)      -->
            <!-- ══════════════════════════════════════════════════════════════ -->

            <!-- SEKSI 1: INLINE FILTER LAYER -->
            <div
              v-if="activeTool === 'filter'"
              class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-2.5 animate-in fade-in duration-150"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <UIcon name="i-lucide-filter" class="size-3.5 text-blue-600 dark:text-blue-400" />
                  <span class="text-[11px] font-semibold text-gray-800 dark:text-gray-200">
                    Filter Layer Ini
                  </span>
                  <UBadge
                    v-if="hasActiveFilter"
                    :label="`${activeFiltersCount} aktif`"
                    size="xs"
                    color="primary"
                    variant="subtle"
                    class="text-[9px] px-1 py-0 h-4 leading-none"
                  />
                </div>

                <UButton
                  v-if="hasActiveFilter"
                  label="Reset Filter"
                  size="xs"
                  color="primary"
                  variant="link"
                  class="p-0 text-[10px] h-auto cursor-pointer"
                  @click="emit('resetFilters')"
                />
              </div>

              <!-- Field 1: Kecamatan -->
              <div class="space-y-1">
                <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">Kecamatan</span>
                <USelectMenu
                  :model-value="kecamatanFilter || 'ALL'"
                  :items="kecamatanSelectItems"
                  value-key="value"
                  label-key="label"
                  placeholder="Pilih kecamatan..."
                  size="xs"
                  class="w-full"
                  @update:model-value="handleKecamatanChange"
                />
              </div>

              <!-- Field 2: Desa / Kelurahan -->
              <div class="space-y-1">
                <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">
                  {{ selectedKecamatanObj ? `Desa (${selectedKecamatanObj.nama})` : 'Desa / Kelurahan' }}
                </span>
                <USelectMenu
                  :model-value="desaFilter || 'ALL'"
                  :items="desaSelectItems"
                  value-key="value"
                  label-key="label"
                  :placeholder="selectedKecamatanObj ? `Pilih desa di ${selectedKecamatanObj.nama}...` : 'Pilih desa...'"
                  size="xs"
                  class="w-full"
                  @update:model-value="handleDesaChange"
                />
              </div>

              <!-- Field 3: Kondisi Jalan -->
              <div class="space-y-1">
                <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">Kondisi Jalan</span>
                <USelectMenu
                  :model-value="kondisiFilter || 'ALL'"
                  :items="kondisiSelectItems"
                  value-key="value"
                  label-key="label"
                  placeholder="Semua Kondisi"
                  size="xs"
                  class="w-full"
                  @update:model-value="emit('update:kondisiFilter', !$event || $event === 'ALL' ? null : String($event))"
                />
              </div>

              <!-- Field 4: Jenis Perkerasan -->
              <div class="space-y-1">
                <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">Jenis Perkerasan</span>
                <USelectMenu
                  :model-value="perkerasanFilter || 'ALL'"
                  :items="perkerasanSelectItems"
                  value-key="value"
                  label-key="label"
                  placeholder="Semua Perkerasan"
                  size="xs"
                  class="w-full"
                  @update:model-value="emit('update:perkerasanFilter', !$event || $event === 'ALL' ? null : String($event))"
                />
              </div>
            </div>

            <!-- SEKSI 2: INLINE OPASITAS SLIDER -->
            <div
              v-else-if="activeTool === 'opacity'"
              class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-1.5 animate-in fade-in duration-150"
            >
              <div class="flex justify-between items-center text-[11px]">
                <span class="text-gray-600 dark:text-gray-300 font-medium">Transparansi Layer:</span>
                <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold">
                  {{ Math.round(layerOpacity * 100) }}%
                </span>
              </div>
              <USlider
                :model-value="layerOpacity"
                :min="0.1"
                :max="1"
                :step="0.05"
                size="xs"
                color="primary"
                @update:model-value="emit('update:layerOpacity', Number($event))"
              />
            </div>

            <!-- SEKSI 3: INLINE SIMBOLOGI (STYLING) -->
            <div
              v-else-if="activeTool === 'symbology'"
              class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-2.5 animate-in fade-in duration-150"
            >
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-gray-800 dark:text-gray-200">
                  Gaya Tampilan Layer
                </span>
                <button
                  type="button"
                  class="text-[10px] text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer"
                  @click="resetSymbology"
                >
                  Reset
                </button>
              </div>

              <!-- Segmented Tab: Garis vs Label -->
              <div class="flex items-center gap-1 bg-gray-200/60 dark:bg-gray-800/80 p-0.5 rounded-md text-xs font-medium">
                <button
                  type="button"
                  :class="[
                    'flex-1 flex items-center justify-center gap-1 py-1 px-1.5 rounded transition-all cursor-pointer text-[11px]',
                    symbologyTab === 'line'
                      ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs font-semibold'
                      : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                  ]"
                  @click="symbologyTab = 'line'"
                >
                  <UIcon name="i-lucide-palette" class="size-3" />
                  <span>Garis</span>
                </button>

                <button
                  type="button"
                  :class="[
                    'flex-1 flex items-center justify-center gap-1 py-1 px-1.5 rounded transition-all cursor-pointer text-[11px]',
                    symbologyTab === 'label'
                      ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs font-semibold'
                      : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                  ]"
                  @click="symbologyTab = 'label'"
                >
                  <UIcon name="i-lucide-type" class="size-3" />
                  <span>Label</span>
                  <span
                    v-if="activeSymbology.labelEnabled"
                    class="size-1.5 rounded-full bg-blue-500 shrink-0"
                  />
                </button>
              </div>

              <!-- Subtab Garis -->
              <div v-if="symbologyTab === 'line'" class="space-y-2 pt-0.5">
                <!-- Mode Pewarnaan -->
                <div class="space-y-1">
                  <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide block">
                    Mode Pewarnaan
                  </span>
                  <div class="grid grid-cols-3 gap-1">
                    <button
                      v-for="mode in colorModeItems"
                      :key="mode.value"
                      type="button"
                      :class="[
                        'px-1 py-1 rounded text-[10px] font-medium transition-colors text-center cursor-pointer truncate',
                        activeSymbology.colorMode === mode.value
                          ? 'bg-blue-600 text-white shadow-xs font-semibold'
                          : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                      ]"
                      @click="updateSymbology('colorMode', mode.value)"
                    >
                      {{ mode.label }}
                    </button>
                  </div>
                </div>

                <!-- Palette Single Color -->
                <div v-if="activeSymbology.colorMode === 'single'" class="space-y-1">
                  <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">Pilihan Warna</span>
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <button
                      v-for="color in COLOR_PRESETS"
                      :key="color.hex"
                      type="button"
                      :style="{ backgroundColor: color.hex }"
                      :class="[
                        'size-4 rounded-full transition-transform cursor-pointer shrink-0',
                        activeSymbology.lineColor.toLowerCase() === color.hex.toLowerCase()
                          ? 'ring-2 ring-offset-1 ring-blue-500 scale-110'
                          : 'hover:scale-105 opacity-85 hover:opacity-100'
                      ]"
                      :title="color.label"
                      @click="updateSymbology('lineColor', color.hex)"
                    />
                    <label
                      class="size-4 rounded-full border border-gray-300 dark:border-gray-600 cursor-pointer flex items-center justify-center overflow-hidden shrink-0 hover:scale-105 transition-transform relative"
                      title="Pilih warna custom"
                    >
                      <input
                        type="color"
                        :value="activeSymbology.lineColor"
                        class="opacity-0 absolute inset-0 size-full cursor-pointer"
                        @input="updateSymbology('lineColor', ($event.target as HTMLInputElement).value)"
                      />
                      <UIcon name="i-lucide-pipette" class="size-2.5 text-gray-500 pointer-events-none" />
                    </label>
                  </div>
                </div>

                <!-- Ketebalan Garis -->
                <div class="space-y-1 pt-0.5">
                  <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400">
                    <span>Ketebalan Garis:</span>
                    <span class="font-mono text-gray-700 dark:text-gray-300 font-semibold">{{ activeSymbology.lineWidth }} px</span>
                  </div>
                  <USlider
                    :model-value="activeSymbology.lineWidth"
                    :min="1"
                    :max="6"
                    :step="0.5"
                    size="xs"
                    color="primary"
                    @update:model-value="updateSymbology('lineWidth', Number($event))"
                  />
                </div>

                <!-- Gaya Garis -->
                <div class="space-y-1 pt-0.5">
                  <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">Gaya Garis</span>
                  <div class="grid grid-cols-3 gap-1">
                    <button
                      v-for="item in lineDashItems"
                      :key="item.value"
                      type="button"
                      :class="[
                        'px-1 py-0.5 rounded text-[10px] font-medium transition-colors text-center cursor-pointer truncate',
                        activeSymbology.lineDash === item.value
                          ? 'bg-blue-600 text-white shadow-xs font-semibold'
                          : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                      ]"
                      @click="updateSymbology('lineDash', item.value)"
                    >
                      {{ item.label }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Subtab Label -->
              <div v-else-if="symbologyTab === 'label'" class="space-y-2 pt-0.5">
                <div class="flex items-center justify-between p-1.5 rounded-md bg-gray-100/70 dark:bg-gray-800/50">
                  <span class="text-xs text-gray-700 dark:text-gray-300 font-medium">Tampilkan Label</span>
                  <input
                    type="checkbox"
                    :checked="activeSymbology.labelEnabled"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    @change="updateSymbology('labelEnabled', ($event.target as HTMLInputElement).checked)"
                  />
                </div>

                <div v-if="activeSymbology.labelEnabled" class="space-y-2">
                  <div class="space-y-1">
                    <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 block">Kolom Label</span>
                    <USelectMenu
                      :model-value="activeSymbology.labelField"
                      :items="labelFieldItems"
                      value-key="value"
                      label-key="label"
                      size="xs"
                      class="w-full"
                      @update:model-value="updateSymbology('labelField', $event as any)"
                    />
                  </div>

                  <div class="space-y-1">
                    <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400">
                      <span>Ukuran Huruf:</span>
                      <span class="font-mono text-gray-700 dark:text-gray-300">{{ activeSymbology.labelFontSize }} px</span>
                    </div>
                    <USlider
                      :model-value="activeSymbology.labelFontSize"
                      :min="9"
                      :max="16"
                      :step="1"
                      size="xs"
                      color="primary"
                      @update:model-value="updateSymbology('labelFontSize', Number($event))"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- SEKSI 4: INLINE METADATA INFO -->
            <div
              v-else-if="activeTool === 'info'"
              class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-2 animate-in fade-in duration-150 text-xs"
            >
              <span class="text-[11px] font-semibold text-gray-800 dark:text-gray-200 block">
                Metadata Dataset
              </span>
              <div class="rounded-md bg-white dark:bg-gray-900/60 p-2 space-y-1.5 border border-gray-100 dark:border-gray-800">
                <div class="flex justify-between text-[11px]">
                  <span class="text-gray-500 dark:text-gray-400">Tipe Data:</span>
                  <span class="font-mono font-medium text-gray-800 dark:text-gray-200">Vector Tiles (MVT)</span>
                </div>
                <div class="flex justify-between text-[11px]">
                  <span class="text-gray-500 dark:text-gray-400">Total Ruas:</span>
                  <span class="font-mono font-semibold text-blue-600 dark:text-blue-400">{{ totalRuas }} ruas</span>
                </div>
                <div class="flex justify-between text-[11px]">
                  <span class="text-gray-500 dark:text-gray-400">Total Panjang:</span>
                  <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ (totalPanjangKm || 0).toFixed(2) }} km</span>
                </div>
                <div class="flex justify-between text-[11px]">
                  <span class="text-gray-500 dark:text-gray-400">Sistem Koordinat:</span>
                  <span class="font-mono text-gray-800 dark:text-gray-200">EPSG:4326 (WGS84)</span>
                </div>
                <div class="flex justify-between text-[11px]">
                  <span class="text-gray-500 dark:text-gray-400">Sumber:</span>
                  <span class="text-gray-800 dark:text-gray-200 text-right truncate max-w-[140px]">BAPPEDA</span>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- 3. LEGENDA SIMBOLOGI (Sesuai Tampilan di Referensi Visual)     -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-1.5">
              <!-- Legenda Mode Satu Warna (Sesuai persis dengan gambar referensi) -->
              <template v-if="activeSymbology.colorMode === 'single'">
                <div class="flex items-center gap-2 text-xs py-0.5">
                  <span
                    class="w-4 h-1 rounded-full shrink-0"
                    :style="{ backgroundColor: activeSymbology.lineColor }"
                  />
                  <span class="text-gray-700 dark:text-gray-200 font-medium">Jalan Poros Desa</span>
                </div>
              </template>

              <!-- Legenda Mode Kondisi -->
              <template v-else-if="activeSymbology.colorMode === 'kondisi'">
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#10b981] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Baik</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#f59e0b] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Sedang</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#f97316] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Rusak</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#ef4444] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Rusak Berat</span>
                  </div>
                </div>
              </template>

              <!-- Legenda Mode Perkerasan -->
              <template v-else-if="activeSymbology.colorMode === 'perkerasan'">
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#0284c7] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Aspal</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#6366f1] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Beton</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#d97706] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Kerikil</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3 h-1 rounded-full bg-[#854d0e] shrink-0" />
                    <span class="text-gray-700 dark:text-gray-300">Tanah</span>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </component>
</template>

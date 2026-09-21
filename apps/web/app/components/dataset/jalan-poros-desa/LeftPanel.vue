<script setup lang="ts">
import type {
  KondisiBreakdown,
  LayerSymbology,
  SymbologyColorMode,
  SymbologyLabelField,
  SymbologyLineDash,
} from "~/types/dataset-editor";
import { DEFAULT_SYMBOLOGY } from "~/types/dataset-editor";

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
  }>(),
  {
    collapsed: false,
    width: 280,
    layerVisible: true,
    layerOpacity: 1,
    kondisiStats: () => [],
    loading: false,
    isMobileDrawer: false,
    collapsible: true,
    symbology: () => ({ ...DEFAULT_SYMBOLOGY }),
  }
);

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "update:layerVisible", val: boolean): void;
  (e: "update:layerOpacity", val: number): void;
  (e: "update:symbology", val: LayerSymbology): void;
  (e: "zoomToLayer"): void;
}>();

const showSettings = ref(false);
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

// Preset color options for Single Color mode
const COLOR_PRESETS = [
  { label: "Emerald", hex: "#059669" },
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
      'flex flex-col select-none overflow-hidden w-full h-full',
      'bg-white dark:bg-[#0b0f19]'
    ]"
  >
    <!-- Header: Title & (Desktop) Collapse Button -->
    <div
      class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 justify-between"
    >
      <Transition name="panel-subtle-fade">
        <div v-if="!collapsed" class="flex items-center gap-2 overflow-hidden">
          <UIcon name="i-lucide-layers" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            Layer & Simbologi
          </span>
        </div>
      </Transition>

      <UTooltip v-if="!isMobileDrawer && collapsible" :text="collapsed ? 'Buka panel kiri' : 'Tutup panel kiri'">
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
        <UTooltip text="Layer & Simbologi">
          <div class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon name="i-lucide-layers" class="size-4 text-gray-400 dark:text-gray-500" />
          </div>
        </UTooltip>
        <UTooltip text="Jalan Poros Desa">
          <div class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon
              name="i-lucide-route"
              :class="['size-4', layerVisible ? 'text-emerald-500' : 'text-gray-300 dark:text-gray-600']"
            />
          </div>
        </UTooltip>
      </div>

      <!-- Expanded Body Content -->
      <div
        v-else
        key="body"
        class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden p-3 md:p-3.5 space-y-4 divide-y divide-gray-100 dark:divide-gray-800/80"
      >
        <!-- Section 1: Layer Utama Jalan Poros -->
        <div class="space-y-2">
          <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">
            Daftar Layer
          </span>

          <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-2.5 bg-gray-50/50 dark:bg-gray-900/30 space-y-2">
            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-200">
                <input
                  type="checkbox"
                  :checked="layerVisible"
                  class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                  @change="emit('update:layerVisible', ($event.target as HTMLInputElement).checked)"
                />
                <span class="truncate">Jalan Poros Desa</span>
              </label>

              <div class="flex items-center gap-0.5">
                <UTooltip text="Fokuskan ke seluruh layer">
                  <UButton
                    icon="i-lucide-maximize-2"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    class="cursor-pointer"
                    @click="emit('zoomToLayer')"
                  />
                </UTooltip>

                <UTooltip text="Pengaturan opacity">
                  <UButton
                    icon="i-lucide-sliders-horizontal"
                    size="xs"
                    color="neutral"
                    :variant="showSettings ? 'subtle' : 'ghost'"
                    class="cursor-pointer"
                    @click="showSettings = !showSettings"
                  />
                </UTooltip>
              </div>
            </div>

            <!-- Opacity slider submenu -->
            <div v-if="showSettings && layerVisible" class="pt-2 border-t border-gray-200 dark:border-gray-800 space-y-1">
              <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400">
                <span>Transparansi:</span>
                <span class="font-mono text-gray-700 dark:text-gray-300 font-semibold">
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
          </div>
        </div>

        <!-- Section 2: Simbologi Layer (Kustom Warna & Labeling) -->
        <div class="pt-3 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">
              Simbologi Peta
            </span>
            <button
              type="button"
              class="text-[10px] text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors cursor-pointer"
              title="Reset ke pengaturan default"
              @click="resetSymbology"
            >
              Reset
            </button>
          </div>

          <!-- Segmented Tab Switcher (Garis vs Label) -->
          <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800/80 p-0.5 rounded-lg text-xs font-medium">
            <button
              type="button"
              :class="[
                'flex-1 flex items-center justify-center gap-1.5 py-1 px-2 rounded-md transition-all cursor-pointer',
                symbologyTab === 'line'
                  ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs font-semibold'
                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
              ]"
              @click="symbologyTab = 'line'"
            >
              <UIcon name="i-lucide-palette" class="size-3.5" />
              <span>Garis</span>
            </button>

            <button
              type="button"
              :class="[
                'flex-1 flex items-center justify-center gap-1.5 py-1 px-2 rounded-md transition-all cursor-pointer',
                symbologyTab === 'label'
                  ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs font-semibold'
                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
              ]"
              @click="symbologyTab = 'label'"
            >
              <UIcon name="i-lucide-type" class="size-3.5" />
              <span>Label Teks</span>
              <span
                v-if="activeSymbology.labelEnabled"
                class="size-1.5 rounded-full bg-emerald-500 shrink-0"
                title="Label aktif"
              />
            </button>
          </div>

          <!-- TAB 1: LINE STYLING -->
          <div v-if="symbologyTab === 'line'" class="space-y-3 pt-0.5">
            <!-- 1.1 Mode Pewarnaan -->
            <div class="space-y-1.5">
              <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide block">
                Pewarnaan
              </span>
              <div class="grid grid-cols-3 gap-1">
                <button
                  v-for="mode in colorModeItems"
                  :key="mode.value"
                  type="button"
                  :class="[
                    'px-1.5 py-1 rounded text-[11px] font-medium transition-colors text-center cursor-pointer truncate',
                    activeSymbology.colorMode === mode.value
                      ? 'bg-emerald-600 text-white shadow-xs font-semibold'
                      : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                  ]"
                  @click="updateSymbology('colorMode', mode.value)"
                >
                  {{ mode.label }}
                </button>
              </div>
            </div>

            <!-- 1.2 Palet Warna (Hanya jika colorMode === 'single') -->
            <div v-if="activeSymbology.colorMode === 'single'" class="space-y-1.5">
              <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400">
                <span class="font-medium uppercase tracking-wide">Pilihan Warna</span>
                <span class="font-mono text-gray-700 dark:text-gray-300 uppercase">{{ activeSymbology.lineColor }}</span>
              </div>

              <!-- Preset Swatches + Native Color Input -->
              <div class="flex items-center gap-1.5 flex-wrap">
                <button
                  v-for="color in COLOR_PRESETS"
                  :key="color.hex"
                  type="button"
                  :style="{ backgroundColor: color.hex }"
                  :class="[
                    'size-5 rounded-full transition-transform cursor-pointer shrink-0',
                    activeSymbology.lineColor.toLowerCase() === color.hex.toLowerCase()
                      ? 'ring-2 ring-offset-2 ring-emerald-500 scale-110'
                      : 'hover:scale-105 opacity-85 hover:opacity-100'
                  ]"
                  :title="color.label"
                  @click="updateSymbology('lineColor', color.hex)"
                />
                <!-- Custom Native Color Picker -->
                <label
                  class="size-5 rounded-full border border-gray-300 dark:border-gray-600 cursor-pointer flex items-center justify-center overflow-hidden shrink-0 hover:scale-105 transition-transform relative"
                  title="Pilih warna custom"
                >
                  <input
                    type="color"
                    :value="activeSymbology.lineColor"
                    class="opacity-0 absolute inset-0 size-full cursor-pointer"
                    @input="updateSymbology('lineColor', ($event.target as HTMLInputElement).value)"
                  />
                  <UIcon name="i-lucide-pipette" class="size-3 text-gray-500 pointer-events-none" />
                </label>
              </div>
            </div>

            <!-- 1.3 Legenda Kategori Dinamis (Jika mode 'kondisi' atau 'perkerasan') -->
            <div v-else-if="activeSymbology.colorMode === 'kondisi'" class="space-y-1 p-2 rounded-lg bg-gray-50/80 dark:bg-gray-900/40 border border-gray-200/70 dark:border-gray-800 text-[11px]">
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#10b981]" />
                  <span class="text-gray-700 dark:text-gray-300">Baik</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#10B981</span>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#f59e0b]" />
                  <span class="text-gray-700 dark:text-gray-300">Sedang</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#F59E0B</span>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#f97316]" />
                  <span class="text-gray-700 dark:text-gray-300">Rusak</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#F97316</span>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#ef4444]" />
                  <span class="text-gray-700 dark:text-gray-300">Rusak Berat</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#EF4444</span>
              </div>
            </div>

            <div v-else-if="activeSymbology.colorMode === 'perkerasan'" class="space-y-1 p-2 rounded-lg bg-gray-50/80 dark:bg-gray-900/40 border border-gray-200/70 dark:border-gray-800 text-[11px]">
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#0284c7]" />
                  <span class="text-gray-700 dark:text-gray-300">Aspal</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#0284C7</span>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#6366f1]" />
                  <span class="text-gray-700 dark:text-gray-300">Beton</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#6366F1</span>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#d97706]" />
                  <span class="text-gray-700 dark:text-gray-300">Kerikil</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#D97706</span>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <div class="flex items-center gap-2">
                  <span class="w-3.5 h-1 rounded-full bg-[#854d0e]" />
                  <span class="text-gray-700 dark:text-gray-300">Tanah</span>
                </div>
                <span class="font-mono text-[10px] text-gray-400">#854D0E</span>
              </div>
            </div>

            <!-- 1.4 Ketebalan Garis -->
            <div class="space-y-1.5 pt-1">
              <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400">
                <span class="font-medium uppercase tracking-wide">Ketebalan Garis</span>
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

            <!-- 1.5 Gaya Garis (Dash Style) -->
            <div class="space-y-1.5 pt-1">
              <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide block">
                Gaya Garis
              </span>
              <div class="grid grid-cols-3 gap-1">
                <button
                  v-for="item in lineDashItems"
                  :key="item.value"
                  type="button"
                  :class="[
                    'px-1.5 py-1 rounded text-[11px] font-medium transition-colors text-center cursor-pointer truncate',
                    activeSymbology.lineDash === item.value
                      ? 'bg-emerald-600 text-white shadow-xs font-semibold'
                      : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                  ]"
                  @click="updateSymbology('lineDash', item.value)"
                >
                  {{ item.label }}
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 2: TEXT LABELING -->
          <div v-else-if="symbologyTab === 'label'" class="space-y-3 pt-0.5">
            <!-- 2.1 Toggle Label Switch -->
            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50/80 dark:bg-gray-900/40 border border-gray-200/70 dark:border-gray-800">
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-tag" class="size-4 text-emerald-600 dark:text-emerald-400" />
                <div>
                  <span class="text-xs font-medium text-gray-800 dark:text-gray-200 block">Tampilkan Label</span>
                  <span class="text-[10px] text-gray-400 block">Teks ruas di atas peta</span>
                </div>
              </div>
              <input
                type="checkbox"
                :checked="activeSymbology.labelEnabled"
                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                @change="updateSymbology('labelEnabled', ($event.target as HTMLInputElement).checked)"
              />
            </div>

            <!-- Label Settings Submenu (when enabled) -->
            <div v-if="activeSymbology.labelEnabled" class="space-y-3">
              <!-- 2.2 Field Label Dropdown -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide block">
                  Informasi Teks
                </span>
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

              <!-- 2.3 Ukuran Font Slider -->
              <div class="space-y-1.5 pt-1">
                <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400">
                  <span class="font-medium uppercase tracking-wide">Ukuran Huruf</span>
                  <span class="font-mono text-gray-700 dark:text-gray-300 font-semibold">{{ activeSymbology.labelFontSize }} px</span>
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

              <!-- 2.4 Batas Zoom Minimal -->
              <div class="space-y-1.5 pt-1">
                <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400">
                  <span class="font-medium uppercase tracking-wide">Zoom Minimal Muncul</span>
                  <span class="font-mono text-gray-700 dark:text-gray-300 font-semibold">Level {{ activeSymbology.labelMinZoom }}</span>
                </div>
                <USlider
                  :model-value="activeSymbology.labelMinZoom"
                  :min="11"
                  :max="16"
                  :step="1"
                  size="xs"
                  color="primary"
                  @update:model-value="updateSymbology('labelMinZoom', Number($event))"
                />
                <p class="text-[10px] text-gray-400 leading-tight">
                  Label dirender saat peta di-zoom masuk ke level {{ activeSymbology.labelMinZoom }}+ agar tidak menumpuk.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </component>
</template>

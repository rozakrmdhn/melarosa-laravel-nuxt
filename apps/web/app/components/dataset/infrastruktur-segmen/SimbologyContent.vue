<script setup lang="ts">
import type {
  LayerSymbology,
  SymbologyColorMode,
  SymbologyLineDash,
  SymbologyLabelField,
} from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY } from '~/types/dataset-editor';

interface Props {
  layerId?: string;
  layerTitle?: string;
  symbology?: LayerSymbology;
  isMobile?: boolean;
  hideFooterActions?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  layerId: 'infrastruktur-segmen',
  layerTitle: 'Segmen Fisik Infrastruktur',
  symbology: () => ({ ...DEFAULT_SYMBOLOGY }),
  isMobile: false,
  hideFooterActions: false,
});

const emit = defineEmits<{
  (e: 'update:symbology', val: LayerSymbology): void;
  (e: 'reset'): void;
  (e: 'close'): void;
}>();

const symbologyTab = ref<'line' | 'label'>('line');
const activeSymbology = computed(() => props.symbology || DEFAULT_SYMBOLOGY);

function updateSymbology<K extends keyof LayerSymbology>(key: K, val: LayerSymbology[K]) {
  emit('update:symbology', {
    ...activeSymbology.value,
    [key]: val,
  });
}

function resetSymbology() {
  emit('reset');
  emit('update:symbology', { ...DEFAULT_SYMBOLOGY });
}

const COLOR_PRESETS = [
  { label: 'Emerald', hex: '#10b981' },
  { label: 'Royal Blue', hex: '#2563eb' },
  { label: 'Sky', hex: '#0284c7' },
  { label: 'Indigo', hex: '#6366f1' },
  { label: 'Purple', hex: '#9333ea' },
  { label: 'Rose', hex: '#e11d48' },
  { label: 'Amber', hex: '#d97706' },
  { label: 'Orange', hex: '#ea580c' },
  { label: 'Slate', hex: '#475569' },
];

const labelFieldItems = computed(() => {
  if (props.layerId === 'jalan-poros-desa') {
    return [
      { label: 'Nama Ruas Jalan', value: 'nama_ruas' as SymbologyLabelField },
      { label: 'Kode Ruas', value: 'kode_ruas' as SymbologyLabelField },
      { label: 'Kondisi Fisik', value: 'kondisi' as SymbologyLabelField },
      { label: 'Panjang Ruas', value: 'panjang' as SymbologyLabelField },
    ];
  }
  return [
    { label: 'Nama Objek / Ruas', value: 'namobj' as any },
    { label: 'Tipe Infrastruktur', value: 'tipe_kode' as any },
    { label: 'Kondisi Fisik', value: 'kondisi' as SymbologyLabelField },
    { label: 'Panjang Ruas', value: 'panjang' as SymbologyLabelField },
  ];
});

const lineDashItems = [
  { label: 'Solid', value: 'solid' as SymbologyLineDash },
  { label: 'Putus-putus', value: 'dashed' as SymbologyLineDash },
  { label: 'Titik-titik', value: 'dotted' as SymbologyLineDash },
];

const colorModeItems = computed(() => {
  if (props.layerId === 'jalan-poros-desa') {
    return [
      { label: 'Warna Tunggal', value: 'single' as SymbologyColorMode },
      { label: 'Berdasarkan Kondisi', value: 'kondisi' as SymbologyColorMode },
      { label: 'Berdasarkan Perkerasan', value: 'perkerasan' as SymbologyColorMode },
    ];
  }
  return [
    { label: 'Warna Tunggal', value: 'single' as SymbologyColorMode },
    { label: 'Berdasarkan Kondisi', value: 'kondisi' as SymbologyColorMode },
  ];
});

const previewDashArray = computed(() => {
  if (activeSymbology.value.lineDash === 'dashed') return '8,6';
  if (activeSymbology.value.lineDash === 'dotted') return '2,5';
  return 'none';
});
</script>

<template>
  <div class="space-y-4">
    <!-- Live Preview Box -->
    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800 space-y-2">
      <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span class="font-medium flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
          <UIcon name="i-lucide-eye" class="size-3.5 text-blue-500" />
          Pratinjau Garis
        </span>
        <UBadge
          :label="`${activeSymbology.lineWidth} px · ${activeSymbology.lineDash}`"
          size="xs"
          color="neutral"
          variant="subtle"
          class="font-mono text-[10px]"
        />
      </div>

      <div class="h-16 w-full rounded-lg bg-white dark:bg-[#070b14] border border-gray-200/70 dark:border-gray-800 flex flex-col items-center justify-center relative overflow-hidden px-4 select-none">
        <svg class="w-full h-7" viewBox="0 0 300 20" preserveAspectRatio="none">
          <template v-if="activeSymbology.colorMode === 'single'">
            <line
              x1="8"
              y1="10"
              x2="292"
              y2="10"
              :stroke="activeSymbology.lineColor"
              :stroke-width="activeSymbology.lineWidth"
              :stroke-dasharray="previewDashArray"
              stroke-linecap="round"
            />
          </template>
          <template v-else-if="activeSymbology.colorMode === 'kondisi'">
            <line x1="8" y1="10" x2="75" y2="10" stroke="#10b981" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
            <line x1="75" y1="10" x2="148" y2="10" stroke="#f59e0b" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
            <line x1="148" y1="10" x2="220" y2="10" stroke="#f97316" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
            <line x1="220" y1="10" x2="292" y2="10" stroke="#ef4444" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
          </template>
          <template v-else-if="activeSymbology.colorMode === 'perkerasan'">
            <line x1="8" y1="10" x2="75" y2="10" stroke="#3b82f6" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
            <line x1="75" y1="10" x2="148" y2="10" stroke="#10b981" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
            <line x1="148" y1="10" x2="220" y2="10" stroke="#f59e0b" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
            <line x1="220" y1="10" x2="292" y2="10" stroke="#8b5cf6" :stroke-width="activeSymbology.lineWidth" :stroke-dasharray="previewDashArray" stroke-linecap="round" />
          </template>
        </svg>

        <div
          v-if="activeSymbology.labelEnabled"
          class="font-semibold transition-all mt-0.5 truncate max-w-full px-2"
          :style="{
            fontSize: `${activeSymbology.labelFontSize}px`,
            color: activeSymbology.labelColor || '#0f172a',
            textShadow: '0 0 2px #ffffff, 0 0 2px #ffffff'
          }"
        >
          {{ layerTitle }} (Contoh Label)
        </div>
      </div>
    </div>

    <!-- Segmented Tab: Garis vs Label -->
    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-850/80 p-1 rounded-xl text-xs font-medium border border-gray-200/60 dark:border-gray-800">
      <button
        type="button"
        :class="[
          'flex-1 flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg transition-all cursor-pointer min-h-[34px]',
          symbologyTab === 'line'
            ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="symbologyTab = 'line'"
      >
        <UIcon name="i-lucide-palette" class="size-3.5" />
        <span>Gaya Garis</span>
      </button>

      <button
        type="button"
        :class="[
          'flex-1 flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg transition-all cursor-pointer min-h-[34px]',
          symbologyTab === 'label'
            ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
        ]"
        @click="symbologyTab = 'label'"
      >
        <UIcon name="i-lucide-type" class="size-3.5" />
        <span>Label Teks</span>
        <span
          v-if="activeSymbology.labelEnabled"
          class="size-1.5 rounded-full bg-blue-500 shrink-0"
        />
      </button>
    </div>

    <!-- Tab 1: Gaya Garis -->
    <div v-if="symbologyTab === 'line'" class="space-y-3.5">
      <!-- Mode Pewarnaan -->
      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
          Mode Pewarnaan Garis
        </label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <button
            v-for="mode in colorModeItems"
            :key="mode.value"
            type="button"
            :class="[
              'px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all text-center cursor-pointer min-h-[36px] truncate border',
              activeSymbology.colorMode === mode.value
                ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700 font-semibold'
                : 'bg-white dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'
            ]"
            @click="updateSymbology('colorMode', mode.value)"
          >
            {{ mode.label }}
          </button>
        </div>
      </div>

      <!-- Color Swatches (Single Mode Only) -->
      <div v-if="activeSymbology.colorMode === 'single'" class="space-y-2">
        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
          Pilihan Warna
        </label>
        <div class="flex items-center gap-2 flex-wrap">
          <button
            v-for="color in COLOR_PRESETS"
            :key="color.hex"
            type="button"
            :style="{ backgroundColor: color.hex }"
            :class="[
              'size-7 rounded-full transition-transform cursor-pointer shrink-0',
              activeSymbology.lineColor.toLowerCase() === color.hex.toLowerCase()
                ? 'ring-2 ring-offset-2 ring-blue-500 scale-110'
                : 'hover:scale-105 opacity-90 hover:opacity-100'
            ]"
            :title="color.label"
            @click="updateSymbology('lineColor', color.hex)"
          />
          <!-- Pipette for custom color -->
          <label
            class="size-7 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 cursor-pointer flex items-center justify-center overflow-hidden shrink-0 hover:scale-105 transition-transform relative"
            title="Pilih warna kustom"
          >
            <input
              type="color"
              :value="activeSymbology.lineColor"
              class="opacity-0 absolute inset-0 size-full cursor-pointer"
              @input="updateSymbology('lineColor', ($event.target as HTMLInputElement).value)"
            />
            <UIcon name="i-lucide-pipette" class="size-3.5 text-gray-600 dark:text-gray-300 pointer-events-none" />
          </label>
        </div>
      </div>

      <!-- Legenda Mode Kondisi -->
      <div v-else-if="activeSymbology.colorMode === 'kondisi'" class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 space-y-1.5">
        <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 block">Legenda Kondisi:</span>
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#10b981] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Baik</span></div>
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#f59e0b] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Sedang</span></div>
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#f97316] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Rusak Ringan</span></div>
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#ef4444] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Rusak Berat</span></div>
        </div>
      </div>

      <!-- Legenda Mode Perkerasan -->
      <div v-else-if="activeSymbology.colorMode === 'perkerasan'" class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 space-y-1.5">
        <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 block">Legenda Perkerasan:</span>
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#3b82f6] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Aspal</span></div>
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#10b981] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Beton</span></div>
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#f59e0b] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Kerikil</span></div>
          <div class="flex items-center gap-2"><span class="w-3.5 h-1.5 rounded-full bg-[#8b5cf6] shrink-0" /><span class="text-gray-700 dark:text-gray-300">Tanah</span></div>
        </div>
      </div>

      <!-- Ketebalan Garis -->
      <div class="space-y-1.5">
        <div class="flex justify-between items-center text-xs text-gray-700 dark:text-gray-300">
          <span class="font-semibold">Ketebalan Garis</span>
          <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold">{{ activeSymbology.lineWidth }} px</span>
        </div>
        <USlider
          :model-value="activeSymbology.lineWidth"
          :min="1"
          :max="6"
          :step="0.5"
          size="sm"
          color="primary"
          @update:model-value="updateSymbology('lineWidth', Number($event))"
        />
      </div>

      <!-- Pola Garis -->
      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
          Pola Garis
        </label>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="item in lineDashItems"
            :key="item.value"
            type="button"
            :class="[
              'px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all text-center cursor-pointer min-h-[36px] border',
              activeSymbology.lineDash === item.value
                ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700 font-semibold'
                : 'bg-white dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'
            ]"
            @click="updateSymbology('lineDash', item.value)"
          >
            {{ item.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Tab 2: Label Teks -->
    <div v-else-if="symbologyTab === 'label'" class="space-y-3.5">
      <!-- Toggle Label On/Off -->
      <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-800">
        <div>
          <span class="text-xs font-semibold text-gray-900 dark:text-white block">Tampilkan Label Peta</span>
          <span class="text-[11px] text-gray-500 dark:text-gray-400">Tampilkan nama atau atribut langsung di atas garis</span>
        </div>
        <USwitch
          :model-value="activeSymbology.labelEnabled"
          color="primary"
          @update:model-value="updateSymbology('labelEnabled', Boolean($event))"
        />
      </div>

      <template v-if="activeSymbology.labelEnabled">
        <!-- Kolom Label -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
            Kolom Teks Label
          </label>
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

        <!-- Ukuran Font -->
        <div class="space-y-1.5">
          <div class="flex justify-between items-center text-xs text-gray-700 dark:text-gray-300">
            <span class="font-semibold">Ukuran Teks</span>
            <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold">{{ activeSymbology.labelFontSize }} px</span>
          </div>
          <USlider
            :model-value="activeSymbology.labelFontSize"
            :min="9"
            :max="18"
            :step="1"
            size="sm"
            color="primary"
            @update:model-value="updateSymbology('labelFontSize', Number($event))"
          />
        </div>

        <!-- Warna Teks Label -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
            Warna Teks
          </label>
          <div class="flex items-center gap-2">
            <input
              type="color"
              :value="activeSymbology.labelColor || '#0f172a'"
              class="size-8 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5 bg-transparent"
              @input="updateSymbology('labelColor', ($event.target as HTMLInputElement).value)"
            />
            <span class="text-xs font-mono text-gray-600 dark:text-gray-400">
              {{ activeSymbology.labelColor || '#0f172a' }}
            </span>
          </div>
        </div>

        <!-- Min Zoom Level -->
        <div class="space-y-1.5">
          <div class="flex justify-between items-center text-xs text-gray-700 dark:text-gray-300">
            <span class="font-semibold">Tampil Mulai Zoom Level</span>
            <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold">Zoom {{ activeSymbology.labelMinZoom ?? 13 }}</span>
          </div>
          <USlider
            :model-value="activeSymbology.labelMinZoom ?? 13"
            :min="10"
            :max="18"
            :step="1"
            size="sm"
            color="primary"
            @update:model-value="updateSymbology('labelMinZoom', Number($event))"
          />
        </div>
      </template>
    </div>

    <!-- Action Buttons Footer -->
    <div
      v-if="!hideFooterActions"
      class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between"
    >
      <UButton
        icon="i-lucide-rotate-ccw"
        label="Atur Ulang Bawaan"
        color="neutral"
        variant="ghost"
        size="xs"
        class="cursor-pointer"
        @click="resetSymbology"
      />
      <UButton
        icon="i-lucide-check"
        label="Selesai"
        color="primary"
        variant="solid"
        size="xs"
        class="cursor-pointer font-medium"
        :class="isMobile ? 'min-h-[44px] px-4' : ''"
        @click="emit('close')"
      />
    </div>
  </div>
</template>

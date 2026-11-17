<script setup lang="ts">
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import { getKondisiLegendItems, type InfrastrukturTipe } from '~/types/infrastruktur';
import type {
  GisLayerItem,
  LayerId,
  LayerSymbology,
} from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY } from '~/types/dataset-editor';

export type ActiveToolType = 'none' | 'opacity' | 'info';

interface Props {
  collapsed?: boolean;
  width?: number;
  isMobileDrawer?: boolean;
  collapsible?: boolean;

  // Multi-layer Support
  layers?: GisLayerItem[];
  activeLayerId?: string;

  // Single-layer fallback props (for backward compatibility)
  layerVisible?: boolean;
  layerOpacity?: number;
  symbology?: LayerSymbology;

  // Filter Props
  tipeList?: InfrastrukturTipe[];
  selectedTipe?: string | null;
  selectedTahunPembangunan?: number | string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  selectedPerkerasan?: string | null;
  selectedStatusJalan?: string | null;
  selectedStatusKondisi?: string | null;
  selectedStatusAset?: string | null;
  selectedSumberDana?: string | null;
  jalanPorosDesaKondisiFilter?: string | null;
  jalanPorosDesaPerkerasanFilter?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  kecamatanName?: string | null;
  desaName?: string | null;
  isKecamatanLocked?: boolean;
  isDesaLocked?: boolean;
  showInlineWilayahSelector?: boolean;
  userRoleLabel?: string;

  // Metadata & Stats Props
  totalSegmen?: number;
  totalPanjangMeter?: number;
  kondisiStats?: Array<{ kondisi: string; jumlah: number; color?: string; panjang_meter?: number }>;
  loading?: boolean;
  isDrawing?: boolean;
  canUndo?: boolean;
  canRedo?: boolean;
  canSave?: boolean;
  isSnappingEnabled?: boolean;
  snapRoadCount?: number;
  layerTitle?: string;
  defaultTool?: ActiveToolType;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  width: 300,
  isMobileDrawer: false,
  collapsible: true,
  layers: undefined,
  activeLayerId: 'infrastruktur-segmen',
  layerVisible: true,
  layerOpacity: 1,
  symbology: () => ({ ...DEFAULT_SYMBOLOGY }),
  tipeList: () => [],
  selectedTipe: null,
  selectedTahunPembangunan: null,
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  selectedPerkerasan: null,
  selectedStatusJalan: null,
  selectedStatusKondisi: null,
  selectedStatusAset: null,
  selectedSumberDana: null,
  jalanPorosDesaKondisiFilter: null,
  jalanPorosDesaPerkerasanFilter: null,
  modelKecamatan: null,
  modelDesa: null,
  kecamatanName: null,
  desaName: null,
  isKecamatanLocked: false,
  isDesaLocked: false,
  showInlineWilayahSelector: false,
  userRoleLabel: '',
  totalSegmen: 0,
  totalPanjangMeter: 0,
  kondisiStats: () => [],
  loading: false,
  isDrawing: false,
  canUndo: false,
  canRedo: false,
  canSave: false,
  isSnappingEnabled: true,
  snapRoadCount: 0,
  layerTitle: 'Segmen Fisik Infrastruktur',
  defaultTool: 'none',
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  // Multi-layer dynamic events
  (e: 'update:layer-visible', layerId: string, val: boolean): void;
  (e: 'update:layer-opacity', layerId: string, val: number): void;
  (e: 'open-filter', layerId?: string): void;
  (e: 'openFilter', layerId?: string): void;
  (e: 'open-symbology', layerId?: string): void;
  (e: 'openSymbology', layerId?: string): void;
  (e: 'zoom-to-layer', layerId?: string): void;
  (e: 'zoomToLayer', layerId?: string): void;
  (e: 'select-layer', layerId: string): void;
  (e: 'update:activeLayerId', val: string): void;
  (e: 'update:active-layer-id', val: string): void;

  // Single-layer legacy events
  (e: 'update:layerVisible', val: boolean): void;
  (e: 'update:layerOpacity', val: number): void;
  (e: 'update:symbology', val: LayerSymbology): void;
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selected-tipe', val: string | null): void;
  (e: 'update:selectedTahunPembangunan', val: number | string | null): void;
  (e: 'update:selected-tahun-pembangunan', val: number | string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selected-kondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:selected-status-verifikasi', val: string | null): void;
  (e: 'update:selectedPerkerasan', val: string | null): void;
  (e: 'update:selected-perkerasan', val: string | null): void;
  (e: 'update:selectedStatusJalan', val: string | null): void;
  (e: 'update:selected-status-jalan', val: string | null): void;
  (e: 'update:selectedStatusKondisi', val: string | null): void;
  (e: 'update:selected-status-kondisi', val: string | null): void;
  (e: 'update:selectedStatusAset', val: string | null): void;
  (e: 'update:selected-status-aset', val: string | null): void;
  (e: 'update:selectedSumberDana', val: string | null): void;
  (e: 'update:selected-sumber-dana', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:model-kecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'update:model-desa', val: number | null): void;
  (e: 'startDrawing'): void;
  (e: 'start-draw'): void;
  (e: 'redraw'): void;
  (e: 'undo'): void;
  (e: 'redo'): void;
  (e: 'toggle-snap'): void;
  (e: 'cancel-draw'): void;
  (e: 'resetFilters'): void;
  (e: 'applyFilter'): void;
  (e: 'toggle-legend-filter', payload: { layerId: string; field: 'kondisi' | 'perkerasan'; value: string }): void;
}>();

// ─── Filter Counter for Primary Layer ─────────────────────────────────────────
const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.selectedTahunPembangunan) count++;
  if (props.selectedTipe) count++;
  if (props.selectedPerkerasan) count++;
  if (props.selectedKondisi) count++;
  if (props.selectedStatusVerifikasi) count++;
  if (props.selectedStatusJalan) count++;
  if (props.selectedStatusKondisi) count++;
  if (props.selectedStatusAset) count++;
  if (props.selectedSumberDana) count++;
  if (!props.isKecamatanLocked && props.modelKecamatan) count++;
  if (!props.isDesaLocked && props.modelDesa) count++;
  return count;
});

const hasActiveFilter = computed(() => activeFiltersCount.value > 0);

const hasResettableTerritory = computed(
  () =>
    (!props.isKecamatanLocked && props.modelKecamatan !== null) ||
    (!props.isDesaLocked && props.modelDesa !== null),
);

const activeTerritorySummary = computed(() => {
  if (props.desaName && props.kecamatanName) {
    return `Desa ${props.desaName}, Kec. ${props.kecamatanName}`;
  }
  if (props.kecamatanName) {
    return `Kecamatan ${props.kecamatanName}`;
  }
  if (props.modelKecamatan) {
    return 'Kecamatan terpilih';
  }
  return 'Semua Wilayah (Kabupaten)';
});

function handleResetTerritory() {
  if (!props.isKecamatanLocked) {
    emit('update:modelKecamatan', null);
    emit('update:model-kecamatan', null);
  }
  if (!props.isDesaLocked) {
    emit('update:modelDesa', null);
    emit('update:model-desa', null);
  }
}

const perkerasanLegendItems = [
  { label: 'Aspal', color: '#3b82f6' },
  { label: 'Beton', color: '#10b981' },
  { label: 'Kerikil', color: '#f59e0b' },
  { label: 'Tanah', color: '#8b5cf6' },
];

function getKondisiStat(kondisiLabel: string) {
  if (!props.kondisiStats?.length) return null;
  return (
    props.kondisiStats.find(
      (item) => item.kondisi?.toLowerCase() === kondisiLabel.toLowerCase(),
    ) || null
  );
}

function formatShortLength(meter?: number | null) {
  if (!meter || meter <= 0) return '0 m';
  if (meter >= 1000) return `${(meter / 1000).toFixed(1)} km`;
  return `${Math.round(meter)} m`;
}

// ─── Normalized Layer List ───────────────────────────────────────────────────
const effectiveLayers = computed<GisLayerItem[]>(() => {
  if (props.layers && props.layers.length > 0) {
    return props.layers;
  }
  return [
    {
      id: 'infrastruktur-segmen',
      title: props.layerTitle || 'Segmen Fisik Infrastruktur',
      subtitle: 'PostGIS Vector Tiles',
      sourceType: 'mvt',
      visible: props.layerVisible ?? true,
      opacity: props.layerOpacity ?? 1,
      symbology: props.symbology || DEFAULT_SYMBOLOGY,
      isPrimary: true,
      canEditGeometry: true,
      canDelete: false,
      hasFilter: hasActiveFilter.value,
      activeFilterCount: activeFiltersCount.value,
      metadata: {
        tipeData: 'Vector Tiles (MVT)',
        totalItem: props.totalSegmen,
        totalPanjangMeter: props.totalPanjangMeter,
        sumberData: 'Dinas PU & Bappeda',
        srs: 'EPSG:4326 (WGS84)',
      },
      kondisiStats: props.kondisiStats,
    },
  ];
});

// ─── Layer Cards Expand / Collapse State ─────────────────────────────────────
const expandedCards = ref<Record<string, boolean>>({
  'infrastruktur-segmen': true,
  'jalan-poros-desa': true,
});

function isCardExpanded(id: string): boolean {
  return expandedCards.value[id] !== false;
}

function toggleCardExpanded(id: string) {
  expandedCards.value[id] = !isCardExpanded(id);
}

// ─── Active Tool State Per Layer (Opacity / Info) ─────────────────────────────
const activeLayerTools = ref<Record<string, ActiveToolType>>({
  'infrastruktur-segmen': props.defaultTool === 'opacity' || props.defaultTool === 'info' ? props.defaultTool : 'none',
});

function getActiveTool(id: string): ActiveToolType {
  return activeLayerTools.value[id] || 'none';
}

function toggleLayerTool(id: string, tool: ActiveToolType) {
  if (activeLayerTools.value[id] === tool) {
    activeLayerTools.value[id] = 'none';
  } else {
    activeLayerTools.value[id] = tool;
    expandedCards.value[id] = true;
  }
}

// ─── Layer Interaction Handlers ──────────────────────────────────────────────
function handleToggleVisibility(layer: GisLayerItem) {
  const newVal = !layer.visible;
  emit('update:layer-visible', layer.id, newVal);
  if (layer.id === 'infrastruktur-segmen') {
    emit('update:layerVisible', newVal);
  }
}

function handleUpdateOpacity(layer: GisLayerItem, val: number) {
  emit('update:layer-opacity', layer.id, val);
  if (layer.id === 'infrastruktur-segmen') {
    emit('update:layerOpacity', val);
  }
}

function handleFilterClick(layerId: string) {
  emit('openFilter', layerId);
  emit('open-filter', layerId);
}

function handleSymbologyClick(layerId: string) {
  emit('openSymbology', layerId);
  emit('open-symbology', layerId);
}

function handleZoomToLayer(layerId: string) {
  emit('zoomToLayer', layerId);
  emit('zoom-to-layer', layerId);
}

function getLayerColor(layer: GisLayerItem): string {
  if (layer.symbology.colorMode === 'single') {
    return layer.symbology.lineColor || (layer.isPrimary ? '#059669' : '#2563eb');
  }
  if (layer.symbology.colorMode === 'kondisi') {
    return '#10b981';
  }
  if (layer.symbology.colorMode === 'perkerasan') {
    return '#3b82f6';
  }
  return layer.isPrimary ? '#059669' : '#2563eb';
}

function handleSelectLayer(layerId: string) {
  emit('select-layer', layerId);
  emit('update:activeLayerId', layerId);
  emit('update:active-layer-id', layerId);
}

function isLegendFilterActive(layerId: string, field: 'kondisi' | 'perkerasan', label: string): boolean {
  const normalized = label.toLowerCase();
  if (layerId === 'jalan-poros-desa') {
    const activeVal = field === 'kondisi' ? props.jalanPorosDesaKondisiFilter : props.jalanPorosDesaPerkerasanFilter;
    return (activeVal || '').toLowerCase() === normalized;
  }
  const activeVal = field === 'kondisi' ? props.selectedKondisi : props.selectedPerkerasan;
  return (activeVal || '').toLowerCase() === normalized;
}

function handleLegendItemClick(layerId: string, field: 'kondisi' | 'perkerasan', label: string) {
  emit('toggle-legend-filter', { layerId, field, value: label });
  if (layerId !== 'jalan-poros-desa') {
    const nextVal = isLegendFilterActive(layerId, field, label) ? null : label;
    if (field === 'kondisi') {
      emit('update:selectedKondisi', nextVal);
      emit('update:selected-kondisi', nextVal);
    } else {
      emit('update:selectedPerkerasan', nextVal);
      emit('update:selected-perkerasan', nextVal);
    }
  }
}
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]',
      isMobileDrawer ? '' : 'select-none'
    ]"
  >
    <!-- Header Panel Kiri: Judul & Tombol Collapse (Desktop only) -->
    <div
      v-if="!isMobileDrawer"
      class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 justify-between select-none"
    >
      <Transition name="panel-subtle-fade">
        <div v-if="!collapsed" class="flex items-center gap-2 overflow-hidden">
          <UIcon name="i-lucide-layers" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            Daftar Layer
          </span>
        </div>
      </Transition>

      <UTooltip v-if="collapsible" :text="collapsed ? 'Buka panel layer' : 'Tutup panel layer'">
        <UButton
          icon="i-lucide-chevron-left"
          size="xs"
          color="neutral"
          variant="ghost"
          :aria-label="collapsed ? 'Buka panel layer' : 'Tutup panel layer'"
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
      <button
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
        type="button"
        :aria-expanded="false"
        aria-label="Buka panel daftar layer"
        class="flex flex-col items-center pt-3 gap-3 flex-1 w-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
        title="Klik untuk membuka panel layer"
        @click="emit('update:collapsed', false)"
      >
        <UTooltip text="Layer & Data">
          <div class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon name="i-lucide-layers" class="size-4 text-gray-500 dark:text-gray-400" />
          </div>
        </UTooltip>

        <!-- Rail icon per layer -->
        <div
          v-for="layer in effectiveLayers"
          :key="`rail-${layer.id}`"
        >
          <UTooltip :text="layer.title">
            <div class="relative p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
              <UIcon
                :name="layer.id === 'jalan-poros-desa' ? 'i-lucide-milestone' : 'i-lucide-route'"
                :class="['size-4', layer.visible ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400 opacity-60']"
              />
              <span
                v-if="layer.hasFilter"
                class="absolute top-1 right-1 size-1.5 rounded-full bg-blue-500 ring-1 ring-white dark:ring-[#0b0f19]"
              />
            </div>
          </UTooltip>
        </div>
      </button>

      <!-- Expanded Body Content -->
      <div
        v-else
        key="body"
        class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden p-3 md:p-3.5 pb-8 space-y-3 min-h-0 overscroll-contain"
        style="-webkit-overflow-scrolling: touch; touch-action: pan-y;"
      >

        <!-- ─── Card Cakupan Wilayah Kerja & Filter Wilayah ─── -->
        <div class="p-2.5 rounded-lg border border-gray-200/90 dark:border-gray-800/90 bg-white dark:bg-[#0e1424] shadow-2xs space-y-2">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 min-w-0">
              <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
              <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                Wilayah Kerja
              </span>
            </div>
            <UBadge
              :label="userRoleLabel || (isDesaLocked ? 'Tingkat Desa' : isKecamatanLocked ? 'Tingkat Kecamatan' : 'Semua Wilayah')"
              :color="isDesaLocked ? 'warning' : isKecamatanLocked ? 'info' : 'primary'"
              variant="subtle"
              size="xs"
              class="text-[10px] px-1.5 py-0 h-4 font-medium shrink-0"
            />
          </div>

          <!-- Notice jika role memiliki batasan wilayah kerja -->
          <div
            v-if="isKecamatanLocked || isDesaLocked"
            class="flex items-center gap-2 p-2 rounded-md bg-amber-50/70 dark:bg-amber-950/25 border border-amber-500/25 text-xs text-amber-800 dark:text-amber-300"
          >
            <UIcon name="i-lucide-lock" class="size-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
            <div class="min-w-0 flex-1 text-[11px] leading-tight">
              <span v-if="isDesaLocked" class="font-medium block truncate">
                Desa {{ desaName || '...' }}, Kec. {{ kecamatanName || '...' }}
              </span>
              <span v-else class="font-medium block truncate">
                Kecamatan {{ kecamatanName || '...' }}
              </span>
              <span class="text-[10px] text-amber-700/80 dark:text-amber-400/80 block">
                {{ isDesaLocked ? 'Akses spasial terkunci pada wilayah desa kerja.' : 'Akses kecamatan terkunci, filter desa dapat dipilih.' }}
              </span>
            </div>
          </div>

          <!-- Desktop: Ringkasan cakupan aktif agar tidak duplikasi dengan Topbar -->
          <div
            v-if="!isDesaLocked && !showInlineWilayahSelector && !isMobileDrawer"
            class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md bg-gray-50 dark:bg-gray-900/50 border border-gray-200/70 dark:border-gray-800/80 text-[11px]"
          >
            <span class="text-gray-700 dark:text-gray-200 font-medium truncate">
              {{ activeTerritorySummary }}
            </span>
            <div class="flex items-center gap-1 shrink-0">
              <UButton
                v-if="hasResettableTerritory"
                label="Reset"
                size="xs"
                color="neutral"
                variant="ghost"
                aria-label="Reset filter wilayah"
                class="h-5 px-1.5 text-[10px] cursor-pointer"
                @click="handleResetTerritory"
              />
              <UButton
                label="Ubah"
                size="xs"
                color="primary"
                variant="subtle"
                aria-label="Ubah filter wilayah"
                class="h-5 px-1.5 text-[10px] cursor-pointer"
                @click="handleFilterClick(activeLayerId)"
              />
            </div>
          </div>

          <!-- Mobile Drawer / Inline Mode: Selector Wilayah Penuh -->
          <div v-else-if="!isDesaLocked" class="space-y-1.5 pt-0.5">
            <WilayahSelector
              :model-kecamatan="modelKecamatan"
              :model-desa="modelDesa"
              layout="col"
              :allow-all="true"
              :show-labels="true"
              :size="isMobileDrawer ? 'sm' : 'xs'"
              @update:model-kecamatan="(val) => { emit('update:modelKecamatan', val); emit('update:model-kecamatan', val); }"
              @update:model-desa="(val) => { emit('update:modelDesa', val); emit('update:model-desa', val); }"
            />
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════════ -->
        <!-- DAFTAR DEDICATED GIS LAYER CARDS (MULTI-LAYER)                        -->
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <div class="space-y-2.5">
          <div
            v-for="layer in effectiveLayers"
            :key="layer.id"
            :class="[
              'rounded-lg border shadow-xs overflow-hidden transition-colors duration-150',
              activeLayerId === layer.id
                ? 'border-l-[3px] border-l-blue-600 dark:border-l-blue-500 border-gray-200 dark:border-gray-700/80 bg-white dark:bg-[#0e1424]'
                : 'border-l-[3px] border-l-transparent border-gray-200 dark:border-gray-800/80 bg-gray-50/70 dark:bg-[#0e1424]/70 hover:border-gray-300 dark:hover:border-gray-700'
            ]"
          >
            <!-- 1. Header Layer Card: Select Trigger + Chevron Toggle -->
            <div
              :class="[
                'flex items-center justify-between gap-1 px-2 py-1.5 transition-colors select-none',
                isMobileDrawer ? 'min-h-[44px]' : ''
              ]"
            >
              <button
                type="button"
                :aria-pressed="activeLayerId === layer.id"
                class="flex items-center gap-2 min-w-0 flex-1 text-left py-0.5 px-1 rounded hover:bg-gray-100/70 dark:hover:bg-gray-800/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer"
                @click="handleSelectLayer(layer.id)"
              >
                <UIcon
                  :name="layer.isPrimary ? 'i-lucide-route' : 'i-lucide-milestone'"
                  class="size-3.5 shrink-0"
                  :class="activeLayerId === layer.id ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'"
                />

                <!-- Swatch Garis Simbologi Layer -->
                <span
                  class="w-3.5 h-1 rounded-full shrink-0 shadow-2xs"
                  :style="{ backgroundColor: getLayerColor(layer) }"
                  :title="`Warna Simbologi: ${getLayerColor(layer)}`"
                />

                <div class="flex items-center gap-1.5 truncate">
                  <span
                    class="text-xs truncate tracking-tight"
                    :class="activeLayerId === layer.id ? 'text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-gray-300 font-medium'"
                    :title="layer.title"
                  >
                    {{ layer.title }}
                  </span>

                  <!-- Single Clean Status Badge -->
                  <UBadge
                    v-if="activeLayerId === layer.id"
                    label="Aktif"
                    color="primary"
                    variant="subtle"
                    size="xs"
                    class="text-[9px] px-1.5 py-0 h-4 shrink-0 font-medium"
                  />
                  <UBadge
                    v-else-if="!layer.isPrimary"
                    label="Referensi"
                    color="neutral"
                    variant="outline"
                    size="xs"
                    class="text-[9px] px-1 py-0 h-4 shrink-0"
                  />
                </div>
              </button>

              <!-- Chevron Expand/Collapse -->
              <button
                type="button"
                :aria-expanded="isCardExpanded(layer.id)"
                :aria-label="isCardExpanded(layer.id) ? `Sembunyikan detail ${layer.title}` : `Buka detail ${layer.title}`"
                :class="[
                  'p-1.5 rounded text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-transform duration-200 cursor-pointer shrink-0',
                  isMobileDrawer ? 'min-h-[44px] min-w-[44px] flex items-center justify-center' : '',
                  isCardExpanded(layer.id) ? 'rotate-0' : 'rotate-180'
                ]"
                :title="isCardExpanded(layer.id) ? 'Sembunyikan detail layer' : 'Buka detail layer'"
                @click="toggleCardExpanded(layer.id)"
              >
                <UIcon name="i-lucide-chevron-up" class="size-4" />
              </button>
            </div>

            <!-- Card Body (Toolbar + Inline Drawers + Legend) -->
            <div v-show="isCardExpanded(layer.id)" class="px-2.5 pb-2.5 space-y-2.5">
              <!-- 2. Action Toolbar (6 Functional Icons) -->
              <div
                class="flex items-center justify-between pt-1 border-t border-gray-200/60 dark:border-gray-800/60 text-gray-600 dark:text-gray-300"
              >
                <!-- 2.1 Ikon Visibilitas (Eye) -->
                <UTooltip :text="layer.visible ? 'Sembunyikan layer di peta' : 'Tampilkan layer di peta'">
                  <button
                    type="button"
                    :aria-label="layer.visible ? `Sembunyikan ${layer.title}` : `Tampilkan ${layer.title}`"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'transition-colors cursor-pointer',
                      layer.visible
                        ? 'text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-950/40'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'
                    ]"
                    @click="handleToggleVisibility(layer)"
                  >
                    <UIcon :name="layer.visible ? 'i-lucide-eye' : 'i-lucide-eye-off'" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                  </button>
                </UTooltip>

                <!-- 2.2 Ikon Opasitas (Sun / Transparency) -->
                <UTooltip text="Atur transparansi layer">
                  <button
                    type="button"
                    :aria-label="`Atur transparansi ${layer.title}`"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'transition-colors cursor-pointer',
                      getActiveTool(layer.id) === 'opacity'
                        ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                    ]"
                    @click="toggleLayerTool(layer.id, 'opacity')"
                  >
                    <UIcon name="i-lucide-sun-medium" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                  </button>
                </UTooltip>

                <!-- 2.3 Ikon Zoom to Extent (Fit Bounds) -->
                <UTooltip text="Zoom ke seluruh cakupan data layer ini">
                  <button
                    type="button"
                    :aria-label="`Zoom ke cakupan ${layer.title}`"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200 transition-colors cursor-pointer'
                    ]"
                    @click="handleZoomToLayer(layer.id)"
                  >
                    <UIcon name="i-lucide-maximize-2" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                  </button>
                </UTooltip>

                <!-- 2.4 Ikon Dedicated Filter Layer (Funnel + Active Count) -->
                <UTooltip :text="`Buka filter untuk ${layer.title}`">
                  <button
                    type="button"
                    :aria-label="`Filter ${layer.title}`"
                    :class="[
                      isMobileDrawer ? 'min-h-[44px] min-w-[44px] flex items-center justify-center gap-1 px-2.5 rounded-lg' : 'px-1.5 py-1 rounded-md flex items-center gap-1',
                      'transition-colors relative cursor-pointer text-[11px] font-medium',
                      layer.hasFilter
                        ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/50 hover:bg-blue-100 dark:hover:bg-blue-900/50 border border-blue-200/80 dark:border-blue-800/60'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                    ]"
                    @click="handleFilterClick(layer.id)"
                  >
                    <UIcon name="i-lucide-filter" :class="isMobileDrawer ? 'size-4' : 'size-3.5'" />
                    <span v-if="layer.hasFilter && (layer.activeFilterCount ?? 0) > 0" class="tabular-nums font-semibold">
                      {{ layer.activeFilterCount }}
                    </span>
                  </button>
                </UTooltip>

                <!-- 2.5 Ikon Simbologi & Warna (Palette) -->
                <UTooltip :text="`Buka pengaturan gaya dan warna untuk ${layer.title}`">
                  <button
                    type="button"
                    :aria-label="`Atur simbologi ${layer.title}`"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'transition-colors cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                    ]"
                    @click="handleSymbologyClick(layer.id)"
                  >
                    <UIcon name="i-lucide-palette" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                  </button>
                </UTooltip>

                <!-- 2.6 Ikon Info / Metadata Layer -->
                <UTooltip text="Informasi ringkas dataset">
                  <button
                    type="button"
                    :aria-label="`Informasi dataset ${layer.title}`"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'transition-colors cursor-pointer',
                      getActiveTool(layer.id) === 'info'
                        ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                    ]"
                    @click="toggleLayerTool(layer.id, 'info')"
                  >
                    <UIcon name="i-lucide-info" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                  </button>
                </UTooltip>
              </div>

              <!-- ══════════════════════════════════════════════════════════════ -->
              <!-- INLINE EXPAND DRAWERS (Opacity Slider & Metadata Info)         -->
              <!-- ══════════════════════════════════════════════════════════════ -->

              <!-- SEKSI 1: INLINE OPASITAS SLIDER -->
              <div
                v-if="getActiveTool(layer.id) === 'opacity'"
                class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-1.5 animate-in fade-in duration-150"
              >
                <div class="flex justify-between items-center text-[11px]">
                  <span class="text-gray-600 dark:text-gray-300 font-medium">Transparansi {{ layer.title }}:</span>
                  <span class="tabular-nums text-blue-600 dark:text-blue-400 font-semibold">
                    {{ Math.round((layer.opacity ?? 1) * 100) }}%
                  </span>
                </div>
                <USlider
                  :model-value="layer.opacity ?? 1"
                  :min="0.1"
                  :max="1"
                  :step="0.05"
                  size="xs"
                  color="primary"
                  @update:model-value="handleUpdateOpacity(layer, Number($event))"
                />
              </div>

              <!-- SEKSI 2: INLINE METADATA INFO -->
              <div
                v-else-if="getActiveTool(layer.id) === 'info'"
                class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-2 animate-in fade-in duration-150 text-xs"
              >
                <span class="text-[11px] font-semibold text-gray-800 dark:text-gray-200 block">
                  Metadata Dataset: {{ layer.title }}
                </span>
                <div class="rounded-md bg-white dark:bg-gray-900/60 p-2 space-y-1.5 border border-gray-100 dark:border-gray-800">
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Status Layer:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                      {{ layer.isPrimary ? 'Utama (Dapat Diedit)' : 'Referensi (Baca-Saja)' }}
                    </span>
                  </div>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Tipe Data:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ layer.metadata?.tipeData || 'Vector Tiles (MVT)' }}</span>
                  </div>
                  <div v-if="layer.metadata?.totalItem !== undefined" class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Total Objek:</span>
                    <span class="tabular-nums font-semibold text-blue-600 dark:text-blue-400">{{ layer.metadata.totalItem }} segmen</span>
                  </div>
                  <div v-if="layer.metadata?.totalPanjangMeter !== undefined" class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Total Panjang:</span>
                    <span class="tabular-nums font-medium text-gray-800 dark:text-gray-200">{{ Math.round(layer.metadata.totalPanjangMeter).toLocaleString('id-ID') }} m</span>
                  </div>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Sistem Koordinat:</span>
                    <span class="text-gray-800 dark:text-gray-200">{{ layer.metadata?.srs || 'EPSG:4326 (WGS84)' }}</span>
                  </div>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Sumber Data:</span>
                    <span class="text-gray-800 dark:text-gray-200 text-right truncate max-w-[140px]">{{ layer.metadata?.sumberData || 'Database Spasial' }}</span>
                  </div>
                </div>
              </div>

              <!-- ══════════════════════════════════════════════════════════════ -->
              <!-- 3. LEGENDA SIMBOLOGI & BREAKDOWN GAYA                         -->
              <!-- ══════════════════════════════════════════════════════════════ -->
              <div class="pt-2 border-t border-gray-200/70 dark:border-gray-800/80 space-y-1.5">
                <!-- Legenda Mode Satu Warna -->
                <template v-if="layer.symbology.colorMode === 'single'">
                  <div class="flex items-center gap-2 text-xs py-0.5">
                    <span
                      class="w-4 h-1 rounded-full shrink-0"
                      :style="{ backgroundColor: layer.symbology.lineColor }"
                    />
                    <span class="text-gray-700 dark:text-gray-200 font-medium truncate">{{ layer.title }}</span>
                  </div>
                </template>

                <!-- Legenda Mode Kondisi (interaktif quick filter & metrik ringkas) -->
                <template v-else-if="layer.symbology.colorMode === 'kondisi'">
                  <div class="space-y-1.5">
                    <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                      <button
                        v-for="item in getKondisiLegendItems(layer.id)"
                        :key="item.label"
                        type="button"
                        :aria-pressed="isLegendFilterActive(layer.id, 'kondisi', item.label)"
                        :title="`Klik untuk filter kondisi ${item.label}`"
                        :class="[
                          'flex items-center justify-between gap-1.5 min-w-0 px-1.5 rounded border text-left transition-colors cursor-pointer',
                          isMobileDrawer ? 'min-h-[44px] py-1.5' : 'py-1',
                          isLegendFilterActive(layer.id, 'kondisi', item.label)
                            ? 'border-blue-500/60 bg-blue-50/80 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 font-semibold'
                            : 'border-transparent hover:border-gray-200 dark:hover:border-gray-700/80 hover:bg-gray-100/70 dark:hover:bg-gray-800/40 text-gray-700 dark:text-gray-300'
                        ]"
                        @click="handleLegendItemClick(layer.id, 'kondisi', item.label)"
                      >
                        <div class="flex items-center gap-1.5 min-w-0">
                          <span
                            class="w-3 h-1 rounded-full shrink-0"
                            :style="{ backgroundColor: item.color }"
                          />
                          <span class="truncate">{{ item.label }}</span>
                        </div>
                        <span
                          v-if="layer.isPrimary && getKondisiStat(item.label)"
                          class="text-[10px] text-gray-500 dark:text-gray-400 tabular-nums shrink-0"
                          :title="`${getKondisiStat(item.label)?.jumlah ?? 0} segmen (${formatShortLength(getKondisiStat(item.label)?.panjang_meter)})`"
                        >
                          {{ getKondisiStat(item.label)?.jumlah ?? 0 }}
                        </span>
                      </button>
                    </div>
                  </div>
                </template>

                <!-- Legenda Mode Perkerasan (interaktif quick filter) -->
                <template v-else-if="layer.symbology.colorMode === 'perkerasan'">
                  <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                    <button
                      v-for="item in perkerasanLegendItems"
                      :key="item.label"
                      type="button"
                      :aria-pressed="isLegendFilterActive(layer.id, 'perkerasan', item.label)"
                      :title="`Klik untuk filter perkerasan ${item.label}`"
                      :class="[
                        'flex items-center gap-1.5 px-1.5 rounded border text-left transition-colors cursor-pointer',
                        isMobileDrawer ? 'min-h-[44px] py-1.5' : 'py-1',
                        isLegendFilterActive(layer.id, 'perkerasan', item.label)
                          ? 'border-blue-500/60 bg-blue-50/80 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 font-semibold'
                          : 'border-transparent hover:border-gray-200 dark:hover:border-gray-700/80 hover:bg-gray-100/70 dark:hover:bg-gray-800/40 text-gray-700 dark:text-gray-300'
                      ]"
                      @click="handleLegendItemClick(layer.id, 'perkerasan', item.label)"
                    >
                      <span
                        class="w-3 h-1 rounded-full shrink-0"
                        :style="{ backgroundColor: item.color }"
                      />
                      <span class="truncate">{{ item.label }}</span>
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>

      </div>
    </Transition>
  </component>
</template>

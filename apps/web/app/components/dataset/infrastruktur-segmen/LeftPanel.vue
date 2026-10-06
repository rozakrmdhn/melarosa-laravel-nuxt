<script setup lang="ts">
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import type { InfrastrukturTipe } from '~/types/infrastruktur';
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
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  kecamatanName?: string | null;
  desaName?: string | null;
  isKecamatanLocked?: boolean;
  isDesaLocked?: boolean;
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
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  modelKecamatan: null,
  modelDesa: null,
  kecamatanName: null,
  desaName: null,
  isKecamatanLocked: false,
  isDesaLocked: false,
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
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selected-kondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:selected-status-verifikasi', val: string | null): void;
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
}>();

// ─── Filter Counter for Primary Layer ─────────────────────────────────────────
const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.selectedTipe) count++;
  if (props.selectedKondisi) count++;
  if (props.selectedStatusVerifikasi) count++;
  if (props.modelKecamatan) count++;
  if (props.modelDesa) count++;
  return count;
});

const hasActiveFilter = computed(() => activeFiltersCount.value > 0);

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
            Daftar Layer & Data
          </span>
          <UBadge
            :label="`${effectiveLayers.length} Layer`"
            color="neutral"
            variant="subtle"
            size="xs"
            class="text-[10px] px-1.5 py-0 h-4 font-mono ml-auto"
          />
        </div>
      </Transition>

      <UTooltip v-if="collapsible" :text="collapsed ? 'Buka panel layer' : 'Tutup panel layer'">
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

        <!-- Rail icon per layer -->
        <div
          v-for="layer in effectiveLayers"
          :key="`rail-${layer.id}`"
        >
          <UTooltip :text="layer.title">
            <div class="relative p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
              <UIcon
                :name="layer.id === 'jalan-poros-desa' ? 'i-lucide-milestone' : 'i-lucide-route'"
                :class="['size-4', layer.visible ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600']"
              />
              <span
                v-if="layer.hasFilter"
                class="absolute top-1 right-1 size-1.5 rounded-full bg-blue-500 ring-1 ring-white dark:ring-[#0b0f19]"
              />
            </div>
          </UTooltip>
        </div>
      </div>

      <!-- Expanded Body Content -->
      <div
        v-else
        key="body"
        class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden p-3 md:p-3.5 pb-8 space-y-3 min-h-0 overscroll-contain"
        style="-webkit-overflow-scrolling: touch; touch-action: pan-y;"
      >
        <!-- ─── Geometry Editor Controls (Hanya saat menggambar atau di desktop) ─── -->
        <div
          v-if="!isMobileDrawer || isDrawing"
          class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0e1424] shadow-xs"
        >
          <!-- Normal mode: Mulai gambar garis baru -->
          <div v-if="!isDrawing" class="flex items-center justify-between gap-2">
            <UButton
              icon="i-lucide-pencil-line"
              label="Gambar Garis Segmen"
              size="xs"
              color="primary"
              variant="subtle"
              class="flex-1 justify-center cursor-pointer font-medium"
              @click="emit('start-draw')"
            />
            <UTooltip :text="isSnappingEnabled ? 'Snapping aktif (klik untuk matikan)' : 'Snapping nonaktif (klik untuk aktifkan)'">
              <UButton
                icon="i-lucide-magnet"
                size="xs"
                :color="isSnappingEnabled ? 'success' : 'neutral'"
                variant="ghost"
                class="cursor-pointer"
                @click="emit('toggle-snap')"
              />
            </UTooltip>
          </div>

          <!-- Drawing mode: Toolbar aktif saat menggambar -->
          <div v-else class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75" />
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500" />
                </span>
                Mode Digitasi Aktif
              </span>
              <span class="text-[11px] text-gray-500 font-mono">Klik 2x untuk selesai</span>
            </div>

            <div class="flex items-center gap-1 pt-1 border-t border-gray-100 dark:border-gray-800">
              <UTooltip text="Undo vertex terakhir">
                <UButton
                  icon="i-lucide-undo"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  :disabled="!canUndo"
                  class="cursor-pointer"
                  @click="emit('undo')"
                />
              </UTooltip>
              <UTooltip text="Redo vertex">
                <UButton
                  icon="i-lucide-redo"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  :disabled="!canRedo"
                  class="cursor-pointer"
                  @click="emit('redo')"
                />
              </UTooltip>
              <UTooltip text="Gambar ulang dari awal">
                <UButton
                  icon="i-lucide-rotate-ccw"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  class="cursor-pointer"
                  @click="emit('redraw')"
                />
              </UTooltip>
              <UTooltip :text="isSnappingEnabled ? 'Snapping ke jaringan jalan aktif' : 'Snapping nonaktif'">
                <UButton
                  icon="i-lucide-magnet"
                  size="xs"
                  :color="isSnappingEnabled ? 'success' : 'neutral'"
                  variant="ghost"
                  class="cursor-pointer"
                  @click="emit('toggle-snap')"
                />
              </UTooltip>
              <UButton
                icon="i-lucide-x"
                label="Batal"
                size="xs"
                color="error"
                variant="ghost"
                class="cursor-pointer ml-auto"
                @click="emit('cancel-draw')"
              />
            </div>
          </div>
        </div>

        <!-- ─── Card Cakupan Wilayah Kerja & Filter Wilayah ─── -->
        <div class="p-2.5 rounded-lg border border-gray-200/90 dark:border-gray-800/90 bg-white dark:bg-[#0e1424] shadow-2xs space-y-2">
          <div class="flex items-center justify-between">
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
              class="text-[9px] px-1.5 py-0 h-4 font-mono font-medium"
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

          <!-- Selector Wilayah Compact (tampil jika tidak dikunci penuh ke desa) -->
          <div v-if="!isDesaLocked" class="space-y-1.5 pt-0.5">
            <WilayahSelector
              :model-kecamatan="modelKecamatan"
              :model-desa="modelDesa"
              layout="col"
              :allow-all="true"
              :show-labels="false"
              size="xs"
              @update:model-kecamatan="emit('update:modelKecamatan', $event)"
              @update:model-desa="emit('update:modelDesa', $event)"
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
              'rounded-lg border shadow-xs overflow-hidden transition-all duration-200',
              activeLayerId === layer.id
                ? 'border-l-[3.5px] border-l-blue-600 dark:border-l-blue-500 border-gray-200 dark:border-gray-700/80 bg-white dark:bg-[#0e1424] ring-1 ring-blue-500/20 dark:ring-blue-500/30'
                : 'border-l-[3.5px] border-l-transparent border-gray-200 dark:border-gray-800/80 bg-gray-50/60 dark:bg-[#0e1424]/60 hover:border-gray-300 dark:hover:border-gray-700'
            ]"
            @click="handleSelectLayer(layer.id)"
          >
            <!-- 1. Header Layer Card: Left Stripe + Grip + Swatch + Title + Badges + Chevron Toggle -->
            <div
              :class="[
                'flex items-center justify-between px-2.5 py-2 cursor-pointer transition-colors select-none',
                activeLayerId === layer.id
                  ? 'bg-blue-50/30 dark:bg-blue-950/20'
                  : 'hover:bg-gray-100/60 dark:hover:bg-gray-800/40',
                isMobileDrawer ? 'min-h-[44px]' : ''
              ]"
              @click="handleSelectLayer(layer.id)"
            >
              <div class="flex items-center gap-2 min-w-0 pr-1">
                <UIcon
                  name="i-lucide-grip-vertical"
                  class="size-4 text-gray-400 dark:text-gray-500 shrink-0 cursor-grab active:cursor-grabbing"
                  title="Urutan Layer"
                />

                <!-- Swatch Garis Simbologi Layer -->
                <span
                  class="w-3.5 h-1 rounded-full shrink-0 shadow-2xs"
                  :style="{ backgroundColor: getLayerColor(layer) }"
                  :title="`Warna Simbologi: ${getLayerColor(layer)}`"
                />

                <div class="flex items-center gap-1.5 truncate">
                  <span
                    class="text-xs font-semibold truncate tracking-tight"
                    :class="activeLayerId === layer.id ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-700 dark:text-gray-300'"
                    :title="layer.title"
                  >
                    {{ layer.title }}
                  </span>

                  <!-- Status Badge -->
                  <UBadge
                    v-if="activeLayerId === layer.id"
                    label="Aktif"
                    color="primary"
                    variant="subtle"
                    size="xs"
                    class="text-[9px] px-1.5 py-0 h-4 font-mono shrink-0 font-medium"
                  />
                  <UBadge
                    v-else-if="layer.isPrimary"
                    label="Utama"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    class="text-[9px] px-1 py-0 h-4 font-mono shrink-0"
                  />
                  <UBadge
                    v-else
                    label="Referensi"
                    color="neutral"
                    variant="outline"
                    size="xs"
                    class="text-[9px] px-1 py-0 h-4 font-mono shrink-0"
                  />
                </div>
              </div>

              <!-- Chevron Expand/Collapse -->
              <button
                type="button"
                :class="[
                  'p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-transform duration-200 cursor-pointer',
                  isMobileDrawer ? 'min-h-[44px] min-w-[44px] flex items-center justify-center' : '',
                  isCardExpanded(layer.id) ? 'rotate-0' : 'rotate-180'
                ]"
                :title="isCardExpanded(layer.id) ? 'Sembunyikan detail layer' : 'Buka detail layer'"
                @click.stop="toggleCardExpanded(layer.id)"
              >
                <UIcon name="i-lucide-chevron-up" class="size-4" />
              </button>
            </div>

            <!-- Card Body (Toolbar + Inline Drawers + Legend) -->
            <div v-show="isCardExpanded(layer.id)" class="px-2.5 pb-2.5 space-y-2.5">
              <!-- 2. Action Toolbar (6 Icons) -->
              <div
                class="flex items-center justify-between pt-1 border-t border-gray-200/60 dark:border-gray-800/60 text-gray-500 dark:text-gray-400"
              >
                <!-- 2.1 Ikon Visibilitas (Eye) -->
                <UTooltip :text="layer.visible ? 'Sembunyikan layer di peta' : 'Tampilkan layer di peta'">
                  <button
                    type="button"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'transition-colors cursor-pointer',
                      layer.visible
                        ? 'text-cyan-500 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-950/40'
                        : 'text-gray-400 dark:text-gray-600 hover:text-gray-700 dark:hover:text-gray-300'
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
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200 transition-colors cursor-pointer'
                    ]"
                    @click="handleZoomToLayer(layer.id)"
                  >
                    <UIcon name="i-lucide-maximize-2" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                  </button>
                </UTooltip>

                <!-- 2.4 Ikon Dedicated Filter Layer (Funnel) -->
                <UTooltip :text="`Buka dialog filter untuk ${layer.title}`">
                  <button
                    type="button"
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'transition-colors relative cursor-pointer',
                      layer.hasFilter
                        ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/50'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-gray-200'
                    ]"
                    @click="handleFilterClick(layer.id)"
                  >
                    <UIcon name="i-lucide-filter" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
                    <span
                      v-if="layer.hasFilter"
                      class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-500 ring-2 ring-white dark:ring-[#0e1424]"
                      :title="`${layer.activeFilterCount ?? 0} filter aktif`"
                    />
                  </button>
                </UTooltip>

                <!-- 2.5 Ikon Simbologi & Warna (Palette) -->
                <UTooltip :text="`Buka pengaturan gaya & warna untuk ${layer.title}`">
                  <button
                    type="button"
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

                <!-- 2.7 Ikon Status / Hapus Layer -->
                <UTooltip :text="layer.isPrimary ? 'Layer utama sistem (tidak dapat dihapus)' : 'Layer referensi MVT'">
                  <button
                    type="button"
                    disabled
                    :class="[
                      isMobileDrawer ? 'size-10 min-h-[44px] min-w-[44px] flex items-center justify-center p-2 rounded-lg' : 'p-1.5 rounded-md',
                      'opacity-40 cursor-not-allowed text-gray-400'
                    ]"
                  >
                    <UIcon :name="layer.isPrimary ? 'i-lucide-lock' : 'i-lucide-database'" :class="isMobileDrawer ? 'size-5' : 'size-4'" />
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
                  <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold">
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
                    <span class="text-gray-500 dark:text-gray-400">Tipe Data:</span>
                    <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ layer.metadata?.tipeData || 'Vector Tiles (MVT)' }}</span>
                  </div>
                  <div v-if="layer.metadata?.totalItem !== undefined" class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Total Objek:</span>
                    <span class="font-mono font-semibold text-blue-600 dark:text-blue-400">{{ layer.metadata.totalItem }} segmen</span>
                  </div>
                  <div v-if="layer.metadata?.totalPanjangMeter !== undefined" class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Total Panjang:</span>
                    <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ Math.round(layer.metadata.totalPanjangMeter).toLocaleString('id-ID') }} m</span>
                  </div>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500 dark:text-gray-400">Sistem Koordinat:</span>
                    <span class="font-mono text-gray-800 dark:text-gray-200">{{ layer.metadata?.srs || 'EPSG:4326 (WGS84)' }}</span>
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

                <!-- Legenda Mode Kondisi -->
                <template v-else-if="layer.symbology.colorMode === 'kondisi'">
                  <div class="grid grid-cols-2 gap-1.5 text-[11px]">
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
                      <span class="text-gray-700 dark:text-gray-300">Rusak Ringan</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <span class="w-3 h-1 rounded-full bg-[#ef4444] shrink-0" />
                      <span class="text-gray-700 dark:text-gray-300">Rusak Berat</span>
                    </div>
                  </div>
                </template>

                <!-- Legenda Mode Perkerasan -->
                <template v-else-if="layer.symbology.colorMode === 'perkerasan'">
                  <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                    <div class="flex items-center gap-1.5">
                      <span class="w-3 h-1 rounded-full bg-[#3b82f6] shrink-0" />
                      <span class="text-gray-700 dark:text-gray-300">Aspal</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <span class="w-3 h-1 rounded-full bg-[#10b981] shrink-0" />
                      <span class="text-gray-700 dark:text-gray-300">Beton</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <span class="w-3 h-1 rounded-full bg-[#f59e0b] shrink-0" />
                      <span class="text-gray-700 dark:text-gray-300">Kerikil</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <span class="w-3 h-1 rounded-full bg-[#8b5cf6] shrink-0" />
                      <span class="text-gray-700 dark:text-gray-300">Tanah</span>
                    </div>
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

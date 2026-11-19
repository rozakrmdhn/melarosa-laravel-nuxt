<script setup lang="ts">
import { computed, ref } from 'vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import {
  ORDE_LEGEND_ITEMS,
  STATUS_ANALISA_LIST,
  formatAngka,
  formatPanjangKm,
} from '~/utils/jaringan-jalan-format';
import type {
  JaringanLayerKey,
  JaringanMapLayersState,
  JaringanMapOpacityState,
  JaringanWorkspaceTab,
  RtrwAnalisaSummary,
  RtrwColorMode,
  StatusAnalisaRtrw,
} from '~/types/jaringan-jalan';

interface Props {
  collapsed: boolean;
  isMobileDrawer?: boolean;
  layers: JaringanMapLayersState;
  layersOpacity: JaringanMapOpacityState;
  colorModeType: RtrwColorMode;
  summaryData: RtrwAnalisaSummary | null;
  statusFilter: '' | StatusAnalisaRtrw;
  ordeFilter: string;
  activeFilterCount: number;
  idKecamatan: number | null;
  idDesa: number | null;
  isKecamatanLocked?: boolean;
  isDesaLocked?: boolean;
  lockedKecamatanLabel?: string | null;
  lockedDesaLabel?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  isMobileDrawer: false,
  isKecamatanLocked: false,
  isDesaLocked: false,
  lockedKecamatanLabel: null,
  lockedDesaLabel: null,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'update:layers', val: JaringanMapLayersState): void;
  (e: 'update:layersOpacity', val: JaringanMapOpacityState): void;
  (e: 'update:colorModeType', val: RtrwColorMode): void;
  (e: 'update:idKecamatan', val: number | null): void;
  (e: 'update:idDesa', val: number | null): void;
  (e: 'toggle-status-filter', val: StatusAnalisaRtrw): void;
  (e: 'toggle-orde-filter', val: string): void;
  (e: 'zoom-to-layer', key: JaringanLayerKey): void;
  (e: 'open-filter-modal'): void;
  (e: 'focus-workspace-tab', tab: JaringanWorkspaceTab): void;
}>();

const activeLayerId = ref<JaringanLayerKey>('rtrw');
const expandedCards = ref<Record<JaringanLayerKey, boolean>>({
  rtrw: true,
  poros: true,
  kabupaten: false,
  kecamatan: false,
  desa: false,
});
const openToolDrawer = ref<Partial<Record<JaringanLayerKey, 'opacity' | 'info' | null>>>({});

interface LayerCardMeta {
  id: JaringanLayerKey;
  title: string;
  subtitle: string;
  isPrimary: boolean;
  icon: string;
  swatchColor: string;
  badgeText: string;
  totalLabel: string;
  workspaceTab?: JaringanWorkspaceTab;
  technicalSource: string;
  zoomNote: string;
}

const layerCards = computed<LayerCardMeta[]>(() => {
  const ov = props.summaryData?.dataset_overview;
  return [
    {
      id: 'rtrw',
      title: 'Rencana Jalan RTRW',
      subtitle: 'Baseline analisa spasial terhadap jalan eksisting',
      isPrimary: true,
      icon: 'i-lucide-route',
      swatchColor: '#d97706',
      badgeText: 'Analisa Utama',
      totalLabel: ov
        ? `${formatAngka(ov.rtrw_segmen, 0)} segmen · ${formatPanjangKm(ov.rtrw_panjang_m)}`
        : 'Memuat data segmen...',
      workspaceTab: 'analisa',
      technicalSource: 'Tile vektor MVT: rtrw_jaringan_transportasi_ln',
      zoomNote: 'Zoom optimal 8 - 19',
    },
    {
      id: 'poros',
      title: 'Master Jalan Poros Desa',
      subtitle: 'Jaringan ruas eksisting & hasil adopsi RTRW',
      isPrimary: false,
      icon: 'i-lucide-milestone',
      swatchColor: '#0d9488',
      badgeText: 'Master Desa',
      totalLabel: ov
        ? `${formatAngka(ov.jalan_poros_ruas, 0)} ruas · ${formatPanjangKm(ov.jalan_poros_panjang_m)}`
        : 'Memuat ruas...',
      workspaceTab: 'master',
      technicalSource: 'Tile vektor MVT: jalan_porosdesa',
      zoomNote: 'Zoom optimal 8 - 19',
    },
    {
      id: 'kabupaten',
      title: 'Jalan Kabupaten Luarkota',
      subtitle: 'Ruas kolektor/lokal kewenangan kabupaten',
      isPrimary: false,
      icon: 'i-lucide-git-commit-horizontal',
      swatchColor: '#1d4ed8',
      badgeText: 'Referensi',
      totalLabel: ov
        ? `${formatAngka(ov.jalan_kabupaten_ruas, 0)} ruas · ${formatPanjangKm(ov.jalan_kabupaten_panjang_m)}`
        : 'Memuat ruas...',
      workspaceTab: 'kabupaten',
      technicalSource: 'Tile vektor MVT: jalan_kabupaten_luarkota',
      zoomNote: 'Zoom optimal 8 - 19',
    },
    {
      id: 'kecamatan',
      title: 'Batas Administrasi Kecamatan',
      subtitle: 'Garis delineasi wilayah kecamatan',
      isPrimary: false,
      icon: 'i-lucide-map',
      swatchColor: '#475569',
      badgeText: 'Batas Admin',
      totalLabel: '28 wilayah kecamatan',
      technicalSource: 'Tile vektor MVT: bataswilayah_kecamatan',
      zoomNote: 'Zoom 8 - 18',
    },
    {
      id: 'desa',
      title: 'Batas Administrasi Desa',
      subtitle: 'Garis delineasi wilayah desa/kelurahan',
      isPrimary: false,
      icon: 'i-lucide-grid-2x2',
      swatchColor: '#64748b',
      badgeText: 'Detail Desa',
      totalLabel: 'Aktif otomatis pada zoom >= 10.5',
      technicalSource: 'Tile vektor MVT: bataswilayah_desa',
      zoomNote: 'Terlihat mulai zoom 10.5+',
    },
  ];
});

const statusProportionSegments = computed(() => {
  const totals = props.summaryData?.totals;
  const totalLen = Math.max(1, totals?.total_panjang_m ?? 0);
  const st = totals?.statuses;
  if (!st || !totals?.total_panjang_m) return [];

  return STATUS_ANALISA_LIST.map((meta) => {
    const stat = st[meta.key];
    return {
      ...meta,
      count: stat?.count ?? 0,
      panjangM: stat?.panjang_m ?? 0,
      pct: Math.round(((stat?.panjang_m ?? 0) / totalLen) * 1000) / 10,
    };
  });
});

function selectLayerCard(layer: LayerCardMeta) {
  activeLayerId.value = layer.id;
  expandedCards.value[layer.id] = true;
  if (layer.workspaceTab) {
    emit('focus-workspace-tab', layer.workspaceTab);
  }
}

function toggleLayerVisibility(key: JaringanLayerKey) {
  emit('update:layers', {
    ...props.layers,
    [key]: !props.layers[key],
  });
}

function updateOpacity(key: JaringanLayerKey, rawVal: string | number) {
  const pct = Math.max(10, Math.min(100, Number(rawVal) || 100));
  emit('update:layersOpacity', {
    ...props.layersOpacity,
    [key]: Number((pct / 100).toFixed(2)),
  });
}

function toggleToolDrawer(key: JaringanLayerKey, tool: 'opacity' | 'info') {
  openToolDrawer.value = {
    ...openToolDrawer.value,
    [key]: openToolDrawer.value[key] === tool ? null : tool,
  };
}
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    class="flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]"
  >
    <!-- Header Desktop Rail / Expanded -->
    <div
      v-if="!isMobileDrawer"
      class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-2.5 gap-2 justify-between"
    >
      <div v-if="!collapsed" class="flex items-center gap-2 overflow-hidden">
        <UIcon name="i-lucide-layers" class="size-4 text-[#176B4D] dark:text-emerald-400 shrink-0" />
        <h2 class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">
          Daftar Layer & Analisa
        </h2>
      </div>

      <UTooltip :text="collapsed ? 'Buka panel layer' : 'Tutup panel layer'">
        <button
          type="button"
          :aria-expanded="!collapsed"
          :aria-label="collapsed ? 'Buka panel layer' : 'Tutup panel layer'"
          class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="emit('update:collapsed', !collapsed)"
        >
          <UIcon
            name="i-lucide-chevron-left"
            :class="['size-4 transition-transform duration-200', collapsed ? 'rotate-180' : '']"
          />
        </button>
      </UTooltip>
    </div>

    <!-- Desktop Collapsed Vertical Rail -->
    <button
      v-if="!isMobileDrawer && collapsed"
      type="button"
      :aria-expanded="false"
      aria-label="Buka panel daftar layer dan legenda peta"
      class="flex flex-col items-center pt-3 gap-3 flex-1 w-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-600"
      title="Klik untuk membuka panel layer"
      @click="emit('update:collapsed', false)"
    >
      <span class="p-1.5 rounded-md bg-emerald-50 text-[#176B4D] dark:bg-emerald-950/60 dark:text-emerald-400">
        <UIcon name="i-lucide-layers" class="size-4" />
      </span>
      <span
        class="text-xs font-medium text-gray-600 dark:text-gray-300 tracking-wide whitespace-nowrap"
        style="writing-mode: vertical-rl;"
      >
        Daftar Layer
      </span>
    </button>

    <!-- Scrollable Body Content -->
    <div v-else class="flex-1 min-h-0 overflow-y-auto p-3 space-y-3">
      <h2 v-if="isMobileDrawer" class="sr-only">Daftar Layer dan Legenda Peta</h2>
      <!-- 1. Wilayah Kerja Card -->
      <section
        class="rounded-lg border border-gray-200 bg-gray-50/80 p-2.5 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]"
        aria-labelledby="heading-wilayah-kerja"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 min-w-0">
            <UIcon name="i-lucide-map-pin" class="size-3.5 text-[#176B4D] dark:text-emerald-400 shrink-0" />
            <h3 id="heading-wilayah-kerja" class="text-xs font-semibold text-gray-900 dark:text-white truncate">
              Wilayah Kerja Analisa
            </h3>
          </div>
          <span
            v-if="summaryData?.totals?.last_analyzed_at"
            class="text-[11px] text-gray-600 dark:text-gray-400 shrink-0"
          >
            {{ isKecamatanLocked || isDesaLocked ? 'Terkunci' : 'Bojonegoro' }}
          </span>
        </div>

        <div
          v-if="isDesaLocked"
          class="rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200"
        >
          <p class="font-medium">{{ lockedKecamatanLabel || 'Kecamatan Aktif' }}</p>
          <p class="text-[11px] text-gray-600 dark:text-gray-400">{{ lockedDesaLabel || 'Desa Aktif' }}</p>
        </div>

        <div v-else-if="isMobileDrawer" class="pt-0.5">
          <WilayahSelector
            :model-kecamatan="idKecamatan"
            :model-desa="idDesa"
            layout="col"
            :allow-all="true"
            :show-labels="true"
            size="sm"
            @update:model-kecamatan="(v) => emit('update:idKecamatan', v)"
            @update:model-desa="(v) => emit('update:idDesa', v)"
          />
        </div>

        <div
          v-else
          class="flex items-center justify-between gap-2 rounded-md border border-gray-200/80 bg-white px-2.5 py-1.5 text-xs text-gray-700 dark:border-gray-800 dark:bg-[#0b0f19] dark:text-gray-300"
        >
          <span class="truncate">
            {{
              idDesa
                ? `Desa terpilih (${summaryData?.wilayah_level === 'desa' ? 'Filter Aktif' : 'Wilayah'})`
                : idKecamatan
                  ? `Kecamatan aktif (${summaryData?.by_wilayah?.length ?? 0} sub-unit)`
                  : 'Seluruh Kabupaten Bojonegoro'
            }}
          </span>
          <button
            v-if="activeFilterCount > 0"
            type="button"
            class="text-[11px] font-semibold text-blue-700 hover:underline dark:text-blue-400 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded"
            @click="emit('open-filter-modal')"
          >
            Ubah Filter ({{ activeFilterCount }})
          </button>
        </div>
      </section>

      <!-- 2. Proporsi Irisan Spasial -->
      <section
        class="rounded-lg border border-gray-200 bg-white p-2.5 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]"
        aria-labelledby="heading-proporsi-spasial"
      >
        <div class="flex items-center justify-between gap-2">
          <h3 id="heading-proporsi-spasial" class="text-xs font-semibold text-gray-900 dark:text-white">
            Cakupan Jaringan RTRW
          </h3>
          <span class="text-xs font-semibold tabular-nums text-[#176B4D] dark:text-emerald-400">
            {{ formatAngka(summaryData?.totals?.avg_coverage_pct ?? 0, 1) }}%
          </span>
        </div>

        <div v-if="statusProportionSegments.length > 0" class="space-y-2">
          <div
            class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
            role="group"
            aria-label="Grafik proporsi irisan panjang jaringan jalan RTRW"
          >
            <button
              v-for="seg in statusProportionSegments"
              :key="seg.key"
              type="button"
              :style="{ width: `${Math.max(seg.pct, 1.5)}%` }"
              :title="`${seg.label}: ${seg.pct}% (${seg.count} segmen)`"
              :aria-label="`Filter ${seg.label} (${seg.pct}%)`"
              :class="[
                'h-full transition-opacity cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                seg.barClass,
                statusFilter && statusFilter !== seg.key ? 'opacity-35' : 'opacity-100',
              ]"
              @click="emit('toggle-status-filter', seg.key)"
            />
          </div>

          <div class="flex items-center justify-between text-[11px] text-gray-600 dark:text-gray-400 tabular-nums">
            <span>Tercover: {{ formatPanjangKm(summaryData?.totals?.total_tercover_m) }}</span>
            <span>Gap: {{ formatPanjangKm(summaryData?.totals?.total_uncovered_m) }}</span>
          </div>
        </div>

        <div
          v-else
          class="rounded-md border border-amber-200 bg-amber-50/70 p-2 text-xs text-amber-900 dark:border-amber-800/80 dark:bg-amber-950/50 dark:text-amber-200"
        >
          <p class="font-medium">Belum ada segmen rencana jalan RTRW di database saat ini.</p>
          <p class="mt-0.5 text-[11px] leading-relaxed">
            Master Jalan Poros Desa ({{ formatAngka(summaryData?.dataset_overview?.jalan_poros_ruas ?? 0, 0) }} ruas) dan Jalan Kabupaten ({{ formatAngka(summaryData?.dataset_overview?.jalan_kabupaten_ruas ?? 0, 0) }} ruas) tetap dapat dilihat dan diinspeksi pada peta.
          </p>
        </div>
      </section>

      <!-- 3. Stack Layer Vektor Martin -->
      <section class="space-y-2" aria-labelledby="heading-layer-stack">
        <div class="flex items-center justify-between px-0.5">
          <h3 id="heading-layer-stack" class="text-xs font-semibold text-gray-800 dark:text-gray-200">
            Layer Peta Vektor (Martin)
          </h3>
          <span class="text-[11px] text-gray-600 dark:text-gray-400 tabular-nums">
            {{ Object.values(layers).filter(Boolean).length }}/5 aktif
          </span>
        </div>

        <div
          v-for="layer in layerCards"
          :key="layer.id"
          :class="[
            'rounded-lg border overflow-hidden transition-colors',
            activeLayerId === layer.id
              ? 'border-l-[3px] border-l-blue-600 border-gray-200 bg-white shadow-2xs dark:border-l-blue-500 dark:border-gray-700 dark:bg-[#0e1424]'
              : 'border-l-[3px] border-l-transparent border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-[#0e1424]/70',
          ]"
        >
          <!-- Layer Header -->
          <div class="flex items-center justify-between gap-1 px-2 py-1">
            <button
              type="button"
              :aria-pressed="activeLayerId === layer.id"
              :class="[
                'flex items-center gap-2 min-w-0 flex-1 text-left rounded px-1.5 py-1 hover:bg-gray-100/80 dark:hover:bg-gray-800/50 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : '',
              ]"
              @click="selectLayerCard(layer)"
            >
              <span
                class="w-3.5 h-1 rounded-full shrink-0"
                :style="{ backgroundColor: layer.swatchColor }"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                    {{ layer.title }}
                  </span>
                </div>
                <p class="text-[11px] text-gray-600 dark:text-gray-400 tabular-nums truncate">
                  {{ layer.totalLabel }}
                </p>
              </div>
            </button>

            <button
              type="button"
              :aria-expanded="expandedCards[layer.id]"
              :aria-label="expandedCards[layer.id] ? `Sembunyikan opsi ${layer.title}` : `Tampilkan opsi ${layer.title}`"
              :class="[
                'flex items-center justify-center rounded text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 shrink-0',
                isMobileDrawer ? 'size-11' : 'size-7',
              ]"
              @click="expandedCards[layer.id] = !expandedCards[layer.id]"
            >
              <UIcon
                name="i-lucide-chevron-up"
                :class="['size-4 transition-transform duration-150', expandedCards[layer.id] ? '' : 'rotate-180']"
              />
            </button>
          </div>

          <!-- Expanded Controls & Interactive Legend -->
          <div v-show="expandedCards[layer.id]" class="px-2.5 pb-2.5 space-y-2 border-t border-gray-200/70 dark:border-gray-800/70">
            <!-- Action Strip -->
            <div class="flex items-center justify-between gap-1 pt-1.5">
              <button
                type="button"
                :aria-pressed="layers[layer.id]"
                :aria-label="layers[layer.id] ? `Sembunyikan ${layer.title}` : `Tampilkan ${layer.title}`"
                :title="layers[layer.id] ? 'Sembunyikan layer' : 'Tampilkan layer'"
                :class="[
                  'flex items-center justify-center rounded-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'size-11' : 'size-7',
                  layers[layer.id]
                    ? 'bg-emerald-50 text-[#176B4D] dark:bg-emerald-950/60 dark:text-emerald-400'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800',
                ]"
                @click="toggleLayerVisibility(layer.id)"
              >
                <UIcon :name="layers[layer.id] ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="size-4" />
              </button>

              <button
                type="button"
                :aria-pressed="openToolDrawer[layer.id] === 'opacity'"
                :aria-label="`Atur opasitas ${layer.title}`"
                title="Atur opasitas garis"
                :class="[
                  'flex items-center justify-center rounded-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'size-11' : 'size-7',
                  openToolDrawer[layer.id] === 'opacity'
                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                ]"
                @click="toggleToolDrawer(layer.id, 'opacity')"
              >
                <UIcon name="i-lucide-sun-medium" class="size-4" />
              </button>

              <button
                type="button"
                :aria-label="`Zoom peta ke cakupan ${layer.title}`"
                title="Zoom ke seluruh cakupan"
                :class="[
                  'flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'size-11' : 'size-7',
                ]"
                @click="emit('zoom-to-layer', layer.id)"
              >
                <UIcon name="i-lucide-maximize-2" class="size-4" />
              </button>

              <button
                v-if="layer.id === 'rtrw'"
                type="button"
                :aria-label="`Filter layer ${layer.title}`"
                title="Buka filter analisa spasial"
                :class="[
                  'flex items-center justify-center gap-1 rounded-md text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'min-h-[44px] min-w-[44px] px-2' : 'h-7 px-1.5',
                  activeFilterCount > 0
                    ? 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                ]"
                @click="emit('open-filter-modal')"
              >
                <UIcon name="i-lucide-filter" class="size-3.5" />
                <span v-if="activeFilterCount > 0" class="tabular-nums">{{ activeFilterCount }}</span>
              </button>

              <button
                v-if="layer.id === 'rtrw'"
                type="button"
                :aria-label="`Ganti mode pewarnaan RTRW (Saat ini ${colorModeType === 'status' ? 'Status Irisan' : 'Orde RTRW'})`"
                :title="`Mode Warna: ${colorModeType === 'status' ? 'Status Irisan' : 'Orde RTRW'}`"
                :class="[
                  'flex items-center justify-center rounded-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'size-11' : 'size-7',
                  colorModeType === 'orde'
                    ? 'bg-violet-50 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                ]"
                @click="emit('update:colorModeType', colorModeType === 'status' ? 'orde' : 'status')"
              >
                <UIcon name="i-lucide-palette" class="size-4" />
              </button>

              <button
                type="button"
                :aria-pressed="openToolDrawer[layer.id] === 'info'"
                :aria-label="`Metadata sumber ${layer.title}`"
                title="Info sumber layer"
                :class="[
                  'flex items-center justify-center rounded-md transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'size-11' : 'size-7',
                  openToolDrawer[layer.id] === 'info'
                    ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-white'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                ]"
                @click="toggleToolDrawer(layer.id, 'info')"
              >
                <UIcon name="i-lucide-info" class="size-4" />
              </button>
            </div>

            <!-- Inline Opacity Slider -->
            <div
              v-if="openToolDrawer[layer.id] === 'opacity'"
              class="rounded-md border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-[#0b0f19]"
            >
              <div class="flex items-center justify-between text-[11px] font-medium text-gray-700 dark:text-gray-300">
                <label :for="`opacity-range-${layer.id}`">Opasitas Layer</label>
                <span class="tabular-nums">{{ Math.round((layersOpacity[layer.id] ?? 0.9) * 100) }}%</span>
              </div>
              <input
                :id="`opacity-range-${layer.id}`"
                type="range"
                min="10"
                max="100"
                step="5"
                :value="Math.round((layersOpacity[layer.id] ?? 0.9) * 100)"
                class="mt-1.5 w-full accent-[#176B4D] cursor-pointer min-h-[28px]"
                @input="(e) => updateOpacity(layer.id, (e.target as HTMLInputElement).value)"
              />
            </div>

            <!-- Inline Metadata Drawer -->
            <div
              v-if="openToolDrawer[layer.id] === 'info'"
              class="rounded-md border border-gray-200 bg-gray-50 p-2 text-[11px] text-gray-700 space-y-0.5 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-300"
            >
              <p class="font-medium text-gray-900 dark:text-white">{{ layer.subtitle }}</p>
              <p>{{ layer.technicalSource }}</p>
              <p class="text-gray-600 dark:text-gray-400">{{ layer.zoomNote }}</p>
            </div>

            <!-- Interactive Legend for Primary RTRW Layer -->
            <div v-if="layer.id === 'rtrw'" class="pt-1 space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-medium text-gray-600 dark:text-gray-400">
                  {{ colorModeType === 'status' ? 'Legenda Status (klik untuk filter):' : 'Legenda Orde RTRW:' }}
                </span>
                <button
                  type="button"
                  :class="[
                    'text-[11px] font-semibold text-blue-700 hover:underline dark:text-blue-400 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded',
                    isMobileDrawer ? 'min-h-[44px] px-2 flex items-center' : '',
                  ]"
                  @click="emit('update:colorModeType', colorModeType === 'status' ? 'orde' : 'status')"
                >
                  {{ colorModeType === 'status' ? 'Ubah ke Orde' : 'Ubah ke Status' }}
                </button>
              </div>

              <!-- Status Legend Clickable Items -->
              <div v-if="colorModeType === 'status'" class="grid grid-cols-1 gap-1">
                <button
                  v-for="st in STATUS_ANALISA_LIST"
                  :key="st.key"
                  type="button"
                  :aria-pressed="statusFilter === st.key"
                  :class="[
                    'flex items-center justify-between gap-2 rounded-md border px-2 text-left text-xs transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                    isMobileDrawer ? 'min-h-[44px]' : 'py-1',
                    statusFilter === st.key
                      ? 'border-blue-500 bg-blue-50 text-blue-950 font-semibold dark:border-blue-400 dark:bg-blue-950/60 dark:text-blue-100'
                      : 'border-gray-200/80 bg-gray-50/70 text-gray-800 hover:bg-gray-100 dark:border-gray-800 dark:bg-[#0b0f19]/70 dark:text-gray-200 dark:hover:bg-gray-800/60',
                  ]"
                  @click="emit('toggle-status-filter', st.key)"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <span class="w-3 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: st.swatchHex }" />
                    <span class="truncate">{{ st.label }}</span>
                  </span>
                  <span class="tabular-nums text-[11px] text-gray-600 dark:text-gray-400 shrink-0">
                    {{ formatAngka(summaryData?.totals?.statuses?.[st.key]?.count ?? 0, 0) }}
                  </span>
                </button>
              </div>

              <!-- Orde Legend Clickable Items -->
              <div v-else class="grid grid-cols-1 gap-1">
                <button
                  v-for="item in ORDE_LEGEND_ITEMS"
                  :key="item.key"
                  type="button"
                  :aria-pressed="ordeFilter === item.key"
                  :class="[
                    'flex items-center justify-between gap-2 rounded-md border px-2 text-left text-xs transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                    isMobileDrawer ? 'min-h-[44px]' : 'py-1',
                    ordeFilter === item.key
                      ? 'border-violet-500 bg-violet-50 text-violet-950 font-semibold dark:border-violet-400 dark:bg-violet-950/60 dark:text-violet-100'
                      : 'border-gray-200/80 bg-gray-50/70 text-gray-800 hover:bg-gray-100 dark:border-gray-800 dark:bg-[#0b0f19]/70 dark:text-gray-200 dark:hover:bg-gray-800/60',
                  ]"
                  @click="emit('toggle-orde-filter', item.key)"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <span class="w-3 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
                    <span class="truncate">{{ item.label }}</span>
                  </span>
                </button>
              </div>
            </div>

            <!-- Sub-legend for Poros Layer -->
            <div v-else-if="layer.id === 'poros'" class="flex flex-wrap gap-3 pt-0.5 text-[11px] text-gray-700 dark:text-gray-300">
              <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-1 rounded-full bg-teal-600" />
                Master Poros Eksisting
              </span>
              <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-1 rounded-full bg-indigo-600" />
                Hasil Adopsi RTRW
              </span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </component>
</template>

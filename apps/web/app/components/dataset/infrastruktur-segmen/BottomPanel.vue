<script setup lang="ts">
import type { InfrastrukturSegmen, InfrastrukturTipe } from '~/types/infrastruktur';
import type { ConsoleLog } from '~/types/dataset-editor';

interface Props {
  collapsed?: boolean;
  height?: number;
  activeTab?: 'table' | 'console';
  tableRows?: InfrastrukturSegmen[];
  tableTotal?: number;
  tablePage?: number;
  tableLastPage?: number;
  tablePerPage?: number;
  tableSearch?: string;
  tableLoading?: boolean;
  selectedSegmenId?: string | null;
  consoleLogs?: ConsoleLog[];
  isMobileDrawer?: boolean;
  collapsible?: boolean;
  canEdit?: boolean;
  canDelete?: boolean;
  canSubmit?: boolean;

  // Active filters for chips
  tipeList?: InfrastrukturTipe[];
  selectedTipe?: string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  kecamatanName?: string | null;
  desaName?: string | null;
  canResetKecamatan?: boolean;
  canResetDesa?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: true,
  height: 240,
  activeTab: 'table',
  tableRows: () => [],
  tableTotal: 0,
  tablePage: 1,
  tableLastPage: 1,
  tablePerPage: 15,
  tableSearch: '',
  tableLoading: false,
  selectedSegmenId: null,
  consoleLogs: () => [],
  isMobileDrawer: false,
  collapsible: true,
  canEdit: true,
  canDelete: true,
  canSubmit: true,
  tipeList: () => [],
  selectedTipe: null,
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  modelKecamatan: null,
  modelDesa: null,
  kecamatanName: null,
  desaName: null,
  canResetKecamatan: true,
  canResetDesa: true,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'update:activeTab', tab: 'table' | 'console'): void;
  (e: 'update:tableSearch', search: string): void;
  (e: 'update:tablePage', page: number): void;
  (e: 'update:tablePerPage', perPage: number): void;
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'resetFilters'): void;
  (e: 'rowClick', row: InfrastrukturSegmen): void;
  (e: 'editRow', row: InfrastrukturSegmen): void;
  (e: 'deleteRow', row: InfrastrukturSegmen): void;
  (e: 'submitRow', row: InfrastrukturSegmen): void;
  (e: 'clearLogs'): void;
}>();

const hasActiveFilters = computed(() => {
  return Boolean(
    props.selectedTipe ||
    props.selectedKondisi ||
    props.selectedStatusVerifikasi ||
    props.modelKecamatan ||
    props.modelDesa
  );
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (props.selectedTipe) count++;
  if (props.selectedKondisi) count++;
  if (props.selectedStatusVerifikasi) count++;
  if (props.modelKecamatan) count++;
  if (props.modelDesa) count++;
  return count;
});

const kecamatanLabel = computed(() => {
  if (props.kecamatanName) return props.kecamatanName;
  if (!props.modelKecamatan) return null;
  const matchRow = props.tableRows?.find((r) => r.id_kecamatan === props.modelKecamatan);
  if (matchRow?.kecamatan) return matchRow.kecamatan;
  return `ID: ${props.modelKecamatan}`;
});

const desaLabel = computed(() => {
  if (props.desaName) return props.desaName;
  if (!props.modelDesa) return null;
  const matchRow = props.tableRows?.find((r) => r.id_desa === props.modelDesa);
  if (matchRow?.desa) return matchRow.desa;
  return `ID: ${props.modelDesa}`;
});

const tipeLabel = computed(() => {
  if (!props.selectedTipe) return null;
  const match = props.tipeList?.find((t) => t.kode === props.selectedTipe);
  return match?.nama || props.selectedTipe;
});

const statusVerifikasiMap: Record<string, { label: string; colorClass: string }> = {
  draft: {
    label: 'Draft',
    colorClass: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700',
  },
  submitted_desa: {
    label: 'Diajukan ke Kecamatan',
    colorClass: 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
  },
  verified_kecamatan: {
    label: 'Terverifikasi Kecamatan',
    colorClass: 'bg-sky-50 dark:bg-sky-950/40 text-sky-800 dark:text-sky-300 border-sky-300 dark:border-sky-800',
  },
  rejected_kecamatan: {
    label: 'Ditolak Kecamatan',
    colorClass: 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
  },
  verified_bappeda: {
    label: 'Disahkan Bappeda',
    colorClass: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
  },
  rejected_bappeda: {
    label: 'Ditolak Bappeda',
    colorClass: 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
  },
};

const statusVerifikasiInfo = computed(() => {
  if (!props.selectedStatusVerifikasi) return null;
  return (
    statusVerifikasiMap[props.selectedStatusVerifikasi] || {
      label: props.selectedStatusVerifikasi,
      colorClass: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700',
    }
  );
});

const kondisiColorMap: Record<string, string> = {
  Baik: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
  Sedang: 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
  'Rusak Ringan': 'bg-orange-50 dark:bg-orange-950/40 text-orange-800 dark:text-orange-300 border-orange-300 dark:border-orange-800',
  'Rusak Berat': 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
};

const kondisiInfo = computed(() => {
  if (!props.selectedKondisi) return null;
  return {
    label: props.selectedKondisi,
    colorClass:
      kondisiColorMap[props.selectedKondisi] ||
      'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700',
  };
});

const pageRangeText = computed(() => {
  if (!props.tableTotal) return 'Tidak ada data';
  const start = (props.tablePage - 1) * props.tablePerPage + 1;
  const end = Math.min(props.tablePage * props.tablePerPage, props.tableTotal);
  return `${start} - ${end} dari ${props.tableTotal.toLocaleString('id-ID')} data`;
});

const perPageOptions = [
  { label: '10', value: 10 },
  { label: '15', value: 15 },
  { label: '25', value: 25 },
  { label: '50', value: 50 },
  { label: '100', value: 100 },
];

function getKondisiBadge(kondisi?: string | null) {
  switch (kondisi) {
    case 'Baik':
      return { label: 'Baik', color: 'success' as const };
    case 'Sedang':
      return { label: 'Sedang', color: 'warning' as const };
    case 'Rusak Ringan':
      return { label: 'Rusak Ringan', color: 'warning' as const };
    case 'Rusak Berat':
      return { label: 'Rusak Berat', color: 'error' as const };
    default:
      return { label: kondisi || '-', color: 'neutral' as const };
  }
}

function getStatusBadge(status?: string | null) {
  switch (status) {
    case 'verified_bappeda':
      return { label: 'Disahkan Bappeda', color: 'success' as const };
    case 'verified_kecamatan':
      return { label: 'Terverifikasi Kec.', color: 'info' as const };
    case 'submitted_desa':
      return { label: 'Diajukan', color: 'warning' as const };
    case 'rejected_kecamatan':
    case 'rejected_bappeda':
      return { label: 'Revisi', color: 'error' as const };
    default:
      return { label: 'Draft', color: 'neutral' as const };
  }
}

function handleHeaderClick(e: MouseEvent) {
  if (!props.collapsible || props.isMobileDrawer) return;

  const target = e.target as HTMLElement | null;
  if (!target) return;

  if (target.closest('button, input, select, textarea, a, [role="button"], .stop-header-toggle')) {
    return;
  }

  emit('update:collapsed', !props.collapsed);
}

function handleTabClick(tab: 'table' | 'console') {
  if (props.collapsed) {
    emit('update:activeTab', tab);
    emit('update:collapsed', false);
  } else if (props.activeTab === tab) {
    emit('update:collapsed', true);
  } else {
    emit('update:activeTab', tab);
  }
}
</script>

<template>
  <div
    :class="[
      'flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]',
      isMobileDrawer ? '' : 'select-none',
      !isMobileDrawer && 'border-t border-gray-200 dark:border-gray-800'
    ]"
  >
    <!-- Header Strip (Sticky Controls Area) -->
    <div
      class="shrink-0 z-20 bg-white dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800 select-none transition-colors duration-150"
      :class="[
        isMobileDrawer ? 'p-3 space-y-2' : 'flex items-center h-10 px-3 gap-2',
        !isMobileDrawer && collapsible && 'cursor-pointer hover:bg-gray-50/75 dark:hover:bg-gray-800/40'
      ]"
      :title="!isMobileDrawer && collapsible ? (collapsed ? 'Klik untuk membuka panel bawah' : 'Klik header untuk menutup panel bawah') : undefined"
      @click="handleHeaderClick"
    >
      <!-- Desktop View: Single Compact Row with Dual Tab -->
      <template v-if="!isMobileDrawer">
        <!-- Dual Tabs: Tabel Atribut vs Konsol Sistem -->
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800/80 p-0.5 rounded-lg stop-header-toggle" @click.stop>
          <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer"
            :class="activeTab === 'table'
              ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
            @click="handleTabClick('table')"
          >
            <UIcon name="i-lucide-table" class="size-3.5" />
            <span>Tabel Atribut</span>
            <span
              v-if="tableTotal > 0"
              class="px-1 text-[9px] font-mono rounded-full bg-blue-50 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400 font-bold"
            >
              {{ tableTotal > 999 ? (tableTotal / 1000).toFixed(1) + 'k' : tableTotal }}
            </span>
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer"
            :class="activeTab === 'console'
              ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
            @click="handleTabClick('console')"
          >
            <UIcon name="i-lucide-terminal" class="size-3.5" />
            <span>Konsol Sistem</span>
            <span
              v-if="consoleLogs.length > 0"
              class="px-1 text-[9px] font-mono rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium"
            >
              {{ consoleLogs.length }}
            </span>
          </button>
        </div>

        <!-- Right Controls: Rows per page + Search + Collapse Button -->
        <div class="ml-auto flex items-center gap-2 sm:gap-3 shrink-0 stop-header-toggle" @click.stop>
          <template v-if="activeTab === 'table'">
            <!-- Rows Per Page -->
            <div v-show="!collapsed" class="flex items-center gap-1.5 shrink-0 transition-opacity duration-150">
              <span class="text-gray-400 dark:text-gray-500 text-[11px] hidden sm:inline">Baris:</span>
              <div class="flex items-center gap-0.5">
                <button
                  v-for="opt in perPageOptions"
                  :key="opt.value"
                  type="button"
                  :class="[
                    'px-1.5 py-0.5 rounded text-[11px] font-mono transition-colors cursor-pointer',
                    tablePerPage === opt.value
                      ? 'bg-blue-600 text-white font-semibold shadow-xs'
                      : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200'
                  ]"
                  :title="`Tampilkan ${opt.label} baris per halaman`"
                  @click="emit('update:tablePerPage', opt.value)"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <!-- Search Input -->
            <div v-show="!collapsed" class="w-36 sm:w-48 lg:w-60 transition-opacity duration-150">
              <UInput
                :model-value="tableSearch"
                icon="i-lucide-search"
                placeholder="Pencarian segmen..."
                size="xs"
                class="w-full"
                @update:model-value="emit('update:tableSearch', String($event))"
              >
                <template v-if="tableSearch" #trailing>
                  <UButton
                    icon="i-lucide-x"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    class="cursor-pointer -mr-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                    @click="emit('update:tableSearch', '')"
                  />
                </template>
              </UInput>
            </div>
          </template>

          <template v-else>
            <!-- Clear Logs Button -->
            <UButton
              v-show="!collapsed && consoleLogs.length > 0"
              icon="i-lucide-trash-2"
              label="Bersihkan Log"
              size="xs"
              color="neutral"
              variant="ghost"
              class="text-xs cursor-pointer"
              @click="emit('clearLogs')"
            />
          </template>

          <!-- Collapse Button -->
          <UTooltip v-if="collapsible" :text="collapsed ? 'Buka panel bawah' : 'Tutup panel bawah'">
            <UButton
              icon="i-lucide-chevron-down"
              size="xs"
              color="neutral"
              variant="ghost"
              :class="[
                'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
                collapsed ? 'rotate-180' : 'rotate-0'
              ]"
              @click.stop="emit('update:collapsed', !collapsed)"
            />
          </UTooltip>
        </div>
      </template>

      <!-- Mobile Layout: 2-Row Sticky Header -->
      <template v-else>
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 min-w-0">
            <UIcon name="i-lucide-table" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
            <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 shrink-0">Tabel Atribut</span>
            <UBadge
              :label="`${tableTotal.toLocaleString('id-ID')} data`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="font-mono text-[10px]"
            />
          </div>

          <div class="flex items-center gap-1 shrink-0">
            <span class="text-gray-400 dark:text-gray-500 text-[10px]">Baris:</span>
            <div class="flex items-center gap-0.5">
              <button
                v-for="opt in perPageOptions"
                :key="opt.value"
                type="button"
                :class="[
                  'px-1.5 py-0.5 rounded text-[10px] font-mono transition-colors cursor-pointer',
                  tablePerPage === opt.value
                    ? 'bg-blue-600 text-white font-semibold'
                    : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                ]"
                @click="emit('update:tablePerPage', opt.value)"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="w-full">
          <UInput
            :model-value="tableSearch"
            icon="i-lucide-search"
            placeholder="Cari segmen atau lokasi..."
            size="sm"
            class="w-full"
            @update:model-value="emit('update:tableSearch', String($event))"
          >
            <template v-if="tableSearch" #trailing>
              <UButton
                icon="i-lucide-x"
                size="xs"
                color="neutral"
                variant="ghost"
                @click="emit('update:tableSearch', '')"
              />
            </template>
          </UInput>
        </div>
      </template>
    </div>

    <!-- Panel Content Area -->
    <Transition name="panel-body-fade">
      <div
        v-show="isMobileDrawer || !collapsed"
        class="flex-1 flex flex-col min-h-0 bg-white dark:bg-[#0b0f19]"
      >
        <!-- TAB 1: TABEL ATRIBUT -->
        <template v-if="activeTab === 'table'">
          <!-- Active Filter Chips Bar -->
          <div
            v-if="hasActiveFilters"
            class="flex items-center gap-2 px-3 py-1.5 bg-gray-50/90 dark:bg-gray-900/60 border-b border-gray-200/80 dark:border-gray-800 text-[11px] overflow-x-auto shrink-0 scrollbar-none"
          >
            <div class="text-gray-500 dark:text-gray-400 font-medium shrink-0 flex items-center gap-1.5 text-[11px]">
              <UIcon name="i-lucide-filter" class="size-3.5 text-blue-600 dark:text-blue-400" />
              <span class="font-medium text-gray-700 dark:text-gray-300">Filter:</span>
              <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-200/80 dark:bg-gray-800 text-gray-600 dark:text-gray-300 leading-none">
                {{ activeFilterCount }}
              </span>
            </div>

            <!-- Chip Kecamatan -->
            <span
              v-if="modelKecamatan"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 border border-blue-200 dark:border-blue-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Kecamatan:</span>
              <span class="font-semibold tracking-tight">{{ kecamatanLabel }}</span>
              <button
                v-if="canResetKecamatan"
                type="button"
                class="p-0.5 rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-800/60 transition-colors cursor-pointer flex items-center justify-center text-blue-700 dark:text-blue-300 hover:text-blue-950 dark:hover:text-white"
                title="Hapus filter kecamatan"
                @click="emit('update:modelKecamatan', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Desa -->
            <span
              v-if="modelDesa"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 border border-blue-200 dark:border-blue-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Desa:</span>
              <span class="font-semibold tracking-tight">{{ desaLabel }}</span>
              <button
                v-if="canResetDesa"
                type="button"
                class="p-0.5 rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-800/60 transition-colors cursor-pointer flex items-center justify-center text-blue-700 dark:text-blue-300 hover:text-blue-950 dark:hover:text-white"
                title="Hapus filter desa"
                @click="emit('update:modelDesa', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Tipe -->
            <span
              v-if="selectedTipe"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Tipe:</span>
              <span class="font-semibold tracking-tight">{{ tipeLabel }}</span>
              <button
                type="button"
                class="p-0.5 rounded-full hover:bg-indigo-200/60 dark:hover:bg-indigo-800/60 transition-colors cursor-pointer flex items-center justify-center text-indigo-700 dark:text-indigo-300 hover:text-indigo-950 dark:hover:text-white"
                title="Hapus filter tipe"
                @click="emit('update:selectedTipe', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Kondisi -->
            <span
              v-if="kondisiInfo"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border shrink-0 text-[11px] shadow-2xs',
                kondisiInfo.colorClass
              ]"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Kondisi:</span>
              <span class="font-semibold tracking-tight">{{ kondisiInfo.label }}</span>
              <button
                type="button"
                class="p-0.5 rounded-full hover:bg-black/10 dark:hover:bg-white/10 transition-colors cursor-pointer flex items-center justify-center"
                title="Hapus filter kondisi"
                @click="emit('update:selectedKondisi', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Status Verifikasi -->
            <span
              v-if="statusVerifikasiInfo"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border shrink-0 text-[11px] shadow-2xs',
                statusVerifikasiInfo.colorClass
              ]"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Status:</span>
              <span class="font-semibold tracking-tight">{{ statusVerifikasiInfo.label }}</span>
              <button
                type="button"
                class="p-0.5 rounded-full hover:bg-black/10 dark:hover:bg-white/10 transition-colors cursor-pointer flex items-center justify-center"
                title="Hapus filter status verifikasi"
                @click="emit('update:selectedStatusVerifikasi', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Tombol Reset Semua -->
            <button
              type="button"
              class="ml-auto text-[11px] text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 font-medium cursor-pointer shrink-0 inline-flex items-center gap-1 transition-colors"
              title="Reset semua filter"
              @click="emit('resetFilters')"
            >
              <UIcon name="i-lucide-rotate-ccw" class="size-3" />
              <span>Reset Semua</span>
            </button>
          </div>

          <!-- Loading State -->
          <div v-if="tableLoading" class="p-3 space-y-2 flex-1">
            <USkeleton v-for="i in 5" :key="i" class="h-6 w-full rounded" />
          </div>

          <!-- Empty State -->
          <div
            v-else-if="tableRows.length === 0"
            class="flex flex-col items-center justify-center flex-1 gap-2 text-gray-400 p-4"
          >
            <UIcon name="i-lucide-inbox" class="size-6 text-gray-400" />
            <p class="text-xs text-gray-600 dark:text-gray-400">Tidak ada data segmen yang sesuai dengan filter.</p>
          </div>

          <!-- Table Content -->
          <div v-else class="flex-1 overflow-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800 text-[11px] font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
                <tr>
                  <th class="py-2 px-3">Tipe</th>
                  <th class="py-2 px-3 min-w-[200px]">Nama Objek / Ruas</th>
                  <th class="py-2 px-3">Desa</th>
                  <th class="py-2 px-3">Kecamatan</th>
                  <th class="py-2 px-3 text-right">Panjang (m)</th>
                  <th class="py-2 px-3 text-right">Lebar (m)</th>
                  <th class="py-2 px-3">Kondisi</th>
                  <th class="py-2 px-3">Status Verifikasi</th>
                  <th class="py-2 px-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60 font-sans">
                <tr
                  v-for="row in tableRows"
                  :key="row.id"
                  class="cursor-pointer transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
                  :class="[
                    row.id === selectedSegmenId
                      ? 'bg-blue-50/70 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 font-medium'
                      : 'text-gray-700 dark:text-gray-300'
                  ]"
                  @click="emit('rowClick', row)"
                >
                  <td class="py-2 px-3 whitespace-nowrap">
                    <UBadge
                      :label="row.tipe?.nama || row.tipe_kode"
                      color="neutral"
                      variant="subtle"
                      size="xs"
                    />
                  </td>
                  <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white truncate max-w-[220px]">
                    <div class="flex items-center gap-1.5 truncate">
                      <UIcon
                        v-if="selectedSegmenId === row.id"
                        name="i-lucide-map-pin"
                        class="size-3 text-blue-500 shrink-0"
                      />
                      <span class="truncate">{{ row.namobj || 'Tanpa Nama' }}</span>
                    </div>
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.desa || '-' }}
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.kecamatan || '-' }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono font-medium whitespace-nowrap">
                    {{ Number(row.panjang_meter_gis ?? row.panjang ?? 0).toLocaleString('id-ID') }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono whitespace-nowrap">
                    {{ row.lebar ? `${row.lebar} m` : '-' }}
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap">
                    <UBadge
                      :label="getKondisiBadge(row.kondisi).label"
                      :color="getKondisiBadge(row.kondisi).color"
                      variant="subtle"
                      size="xs"
                    />
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap">
                    <UBadge
                      :label="getStatusBadge(row.status_verifikasi).label"
                      :color="getStatusBadge(row.status_verifikasi).color"
                      variant="subtle"
                      size="xs"
                    />
                  </td>
                  <td class="py-2 px-3 text-center whitespace-nowrap" @click.stop>
                    <div class="flex items-center justify-center gap-0.5">
                      <UButton
                        icon="i-lucide-locate-fixed"
                        size="xs"
                        color="primary"
                        variant="ghost"
                        title="Zoom ke segmen di peta"
                        class="cursor-pointer text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                        @click="emit('rowClick', row)"
                      />
                      <UButton
                        v-if="canEdit"
                        icon="i-lucide-pencil"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        title="Edit Segmen"
                        class="cursor-pointer"
                        @click="emit('editRow', row)"
                      />
                      <UButton
                        v-if="canSubmit && (row.status_verifikasi === 'draft' || row.status_verifikasi === 'rejected_kecamatan')"
                        icon="i-lucide-send"
                        size="xs"
                        color="primary"
                        variant="ghost"
                        title="Ajukan Verifikasi"
                        class="cursor-pointer"
                        @click="emit('submitRow', row)"
                      />
                      <UButton
                        v-if="canDelete"
                        icon="i-lucide-trash-2"
                        size="xs"
                        color="error"
                        variant="ghost"
                        title="Hapus Segmen"
                        class="cursor-pointer"
                        @click="emit('deleteRow', row)"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination Footer -->
          <div
            v-if="tableTotal > 0"
            class="flex items-center justify-between px-3 py-1.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-[#0d1117] text-[11px] shrink-0 gap-3"
          >
            <span class="text-gray-500 dark:text-gray-400 font-mono shrink-0 tabular-nums">
              {{ pageRangeText }}
            </span>

            <UPagination
              v-if="tableLastPage > 1"
              :page="tablePage"
              :total="tableTotal"
              :items-per-page="tablePerPage"
              size="xs"
              color="neutral"
              active-color="primary"
              @update:page="emit('update:tablePage', $event)"
            />
          </div>
        </template>

        <!-- TAB 2: KONSOL SISTEM -->
        <template v-else-if="activeTab === 'console'">
          <div class="flex-1 flex flex-col min-h-0 bg-[#070b14] text-gray-300 font-mono text-[11px] p-3 overflow-y-auto">
            <div v-if="consoleLogs.length === 0" class="flex flex-col items-center justify-center flex-1 text-gray-500 gap-1.5 py-6">
              <UIcon name="i-lucide-terminal" class="size-6 text-gray-600" />
              <span>Belum ada log aktivitas GIS.</span>
            </div>
            <div v-else class="space-y-1">
              <div
                v-for="(log, idx) in consoleLogs"
                :key="idx"
                class="flex items-start gap-2 py-0.5 leading-relaxed hover:bg-white/5 rounded px-1"
              >
                <span class="text-gray-500 shrink-0 text-[10px]">{{ log.time }}</span>
                <span
                  class="px-1 py-0.2 rounded text-[9px] font-bold shrink-0 leading-tight"
                  :class="{
                    'bg-blue-900/60 text-blue-300 border border-blue-700/50': log.level === 'INFO',
                    'bg-amber-900/60 text-amber-300 border border-amber-700/50': log.level === 'WARN',
                    'bg-red-900/60 text-red-300 border border-red-700/50': log.level === 'ERROR',
                  }"
                >
                  {{ log.level }}
                </span>
                <span class="text-gray-200 break-all">{{ log.message }}</span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import type { InfrastrukturSegmen, InfrastrukturTipe } from '~/types/infrastruktur';
import type { ConsoleLog } from '~/types/dataset-editor';
import { formatWilayah } from '~/utils/helpers';

interface Props {
  collapsed?: boolean;
  height?: number;
  activeTab?: 'table' | 'console';
  tableRows?: InfrastrukturSegmen[];
  tableTotal?: number;
  totalPanjangMeter?: number | null;
  tablePage?: number;
  tableLastPage?: number;
  tablePerPage?: number;
  tableSearch?: string;
  tableLoading?: boolean;
  tableError?: string | null;
  selectedSegmenId?: string | null;
  consoleLogs?: ConsoleLog[];
  isMobileDrawer?: boolean;
  collapsible?: boolean;
  canEdit?: boolean;
  canDelete?: boolean;
  canSubmit?: boolean;
  canEditRow?: (row: InfrastrukturSegmen) => boolean;
  canDeleteRow?: (row: InfrastrukturSegmen) => boolean;
  canSubmitRow?: (row: InfrastrukturSegmen) => boolean;

  // Active filters for chips
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
  totalPanjangMeter: null,
  tablePage: 1,
  tableLastPage: 1,
  tablePerPage: 15,
  tableSearch: '',
  tableLoading: false,
  tableError: null,
  selectedSegmenId: null,
  consoleLogs: () => [],
  isMobileDrawer: false,
  collapsible: true,
  canEdit: true,
  canDelete: true,
  canSubmit: true,
  canEditRow: undefined,
  canDeleteRow: undefined,
  canSubmitRow: undefined,
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
  (e: 'update:selectedTahunPembangunan', val: number | string | null): void;
  (e: 'update:selected-tahun-pembangunan', val: number | string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
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
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'resetFilters'): void;
  (e: 'refresh'): void;
  (e: 'rowClick', row: InfrastrukturSegmen): void;
  (e: 'editRow', row: InfrastrukturSegmen): void;
  (e: 'deleteRow', row: InfrastrukturSegmen): void;
  (e: 'submitRow', row: InfrastrukturSegmen): void;
  (e: 'clearLogs'): void;
}>();

const hasActiveFilters = computed(() => {
  return Boolean(
    props.selectedTahunPembangunan ||
    props.selectedTipe ||
    props.selectedPerkerasan ||
    props.selectedKondisi ||
    props.selectedStatusVerifikasi ||
    props.selectedStatusJalan ||
    props.selectedStatusKondisi ||
    props.selectedStatusAset ||
    props.selectedSumberDana ||
    props.modelKecamatan ||
    props.modelDesa
  );
});

const hasResettableFilters = computed(() => {
  return Boolean(
    props.selectedTahunPembangunan ||
    props.selectedTipe ||
    props.selectedPerkerasan ||
    props.selectedKondisi ||
    props.selectedStatusVerifikasi ||
    props.selectedStatusJalan ||
    props.selectedStatusKondisi ||
    props.selectedStatusAset ||
    props.selectedSumberDana ||
    (props.canResetKecamatan && props.modelKecamatan) ||
    (props.canResetDesa && props.modelDesa)
  );
});

const activeFilterCount = computed(() => {
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
  if (props.canResetKecamatan && props.modelKecamatan) count++;
  if (props.canResetDesa && props.modelDesa) count++;
  return count;
});

function isRowEditable(row: InfrastrukturSegmen) {
  return props.canEditRow ? props.canEditRow(row) : Boolean(props.canEdit);
}

function isRowDeletable(row: InfrastrukturSegmen) {
  return props.canDeleteRow ? props.canDeleteRow(row) : Boolean(props.canDelete);
}

function isRowSubmittable(row: InfrastrukturSegmen) {
  if (props.canSubmitRow) return props.canSubmitRow(row);
  return Boolean(
    props.canSubmit &&
      (row.status_verifikasi === 'draft' || row.status_verifikasi === 'rejected_kecamatan'),
  );
}

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

const formattedTotalLength = computed(() => {
  const raw =
    props.totalPanjangMeter !== null && props.totalPanjangMeter !== undefined
      ? Number(props.totalPanjangMeter)
      : props.tableRows.reduce((acc, row) => acc + Number(row.panjang_meter_gis ?? row.panjang ?? 0), 0);
  if (!Number.isFinite(raw)) return '0 m';
  return `${raw.toLocaleString('id-ID', { maximumFractionDigits: 2 })} m`;
});

const perPageOptions = [
  { label: '10', value: 10 },
  { label: '15', value: 15 },
  { label: '25', value: 25 },
  { label: '50', value: 50 },
  { label: '100', value: 100 },
];

const mobilePageOptions = computed(() => {
  const totalPages = Math.max(1, Number(props.tableLastPage) || 1);
  if (totalPages > 300) return [];
  return Array.from({ length: totalPages }, (_, idx) => idx + 1);
});

const mobileListRef = ref<HTMLElement | null>(null);

watch(
  () => props.tablePage,
  () => {
    if (!props.isMobileDrawer || !mobileListRef.value) return;
    const reduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    mobileListRef.value.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
  },
);

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
      return { label: 'Ditolak Kec.', color: 'error' as const };
    case 'rejected_bappeda':
      return { label: 'Ditolak Bappeda', color: 'error' as const };
    default:
      return { label: 'Draft', color: 'neutral' as const };
  }
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
      :class="isMobileDrawer ? 'px-3 pt-2.5 pb-2 space-y-2' : 'flex items-center h-10 px-3 gap-2'"
    >
      <!-- Desktop View: Single Compact Row with Dual Tab -->
      <template v-if="!isMobileDrawer">
        <!-- Dual Tabs: Tabel Atribut vs Konsol Sistem -->
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800/80 p-0.5 rounded-lg shrink-0">
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
              class="px-1.5 text-[10px] tabular-nums rounded-full bg-blue-50 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400 font-bold"
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
              class="px-1.5 text-[10px] tabular-nums rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium"
            >
              {{ consoleLogs.length }}
            </span>
          </button>
        </div>

        <!-- Accessible Flexible Header Collapse Target -->
        <button
          v-if="collapsible"
          type="button"
          :aria-expanded="!collapsed"
          :aria-label="collapsed ? 'Buka panel bawah' : 'Tutup panel bawah'"
          :title="collapsed ? 'Klik untuk membuka panel bawah' : 'Klik untuk menutup panel bawah'"
          class="flex-1 h-full min-w-6 cursor-pointer rounded hover:bg-gray-50/75 dark:hover:bg-gray-800/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 transition-colors"
          @click="emit('update:collapsed', !collapsed)"
        />
        <div v-else class="flex-1" />

        <!-- Right Controls: Rows per page + Search + Collapse Button -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
          <template v-if="activeTab === 'table'">
            <!-- Rows Per Page -->
            <div v-show="!collapsed" class="flex items-center gap-1.5 shrink-0 transition-opacity duration-150">
              <span class="text-gray-500 dark:text-gray-400 text-[11px] hidden sm:inline">Baris:</span>
              <div class="flex items-center gap-0.5">
                <button
                  v-for="opt in perPageOptions"
                  :key="opt.value"
                  type="button"
                  :class="[
                    'px-1.5 py-0.5 rounded text-[11px] tabular-nums transition-colors cursor-pointer',
                    tablePerPage === opt.value
                      ? 'bg-blue-600 text-white font-semibold shadow-xs'
                      : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200'
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
                aria-label="Pencarian segmen dalam tabel"
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
                    aria-label="Hapus pencarian"
                    class="cursor-pointer -mr-1 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
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
              :aria-expanded="!collapsed"
              :aria-label="collapsed ? 'Buka panel bawah' : 'Tutup panel bawah'"
              :class="[
                'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
                collapsed ? 'rotate-180' : 'rotate-0'
              ]"
              @click="emit('update:collapsed', !collapsed)"
            />
          </UTooltip>
        </div>
      </template>

      <!-- Mobile Layout: Compact Search Bar + Result Counter & Per-Page Picker -->
      <template v-else>
        <!-- Full-Width Touch Search Bar -->
        <div class="relative flex items-center">
          <UIcon
            name="i-lucide-search"
            class="size-4 text-gray-500 dark:text-gray-400 pointer-events-none absolute left-3"
            aria-hidden="true"
          />
          <input
            :value="tableSearch"
            type="search"
            inputmode="search"
            enterkeyhint="search"
            aria-label="Cari nama ruas, desa, atau kecamatan"
            placeholder="Cari nama ruas, desa, kecamatan..."
            class="w-full h-11 pl-9 pr-10 rounded-xl bg-gray-100/90 dark:bg-gray-800/80 border border-gray-200/80 dark:border-gray-700/80 text-base sm:text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:outline-none focus:bg-white dark:focus:bg-[#0b0f19] focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-colors [&::-webkit-search-cancel-button]:hidden"
            @input="emit('update:tableSearch', ($event.target as HTMLInputElement).value)"
          />
          <button
            v-if="tableSearch"
            type="button"
            aria-label="Hapus pencarian"
            title="Hapus pencarian"
            class="absolute right-0.5 size-10 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-xl text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer"
            @click="emit('update:tableSearch', '')"
          >
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </div>

        <!-- Result Meta & Baris/Hal Control -->
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 min-w-0 text-xs text-gray-600 dark:text-gray-400 tabular-nums">
            <span class="font-semibold text-gray-900 dark:text-gray-100">
              {{ tableTotal.toLocaleString('id-ID') }}
            </span>
            <span class="truncate">segmen tercatat</span>
          </div>

          <div class="flex items-center gap-1.5 shrink-0">
            <button
              type="button"
              :disabled="tableLoading"
              aria-label="Muat ulang daftar segmen"
              title="Muat ulang data"
              class="relative size-9 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 active:bg-gray-100 dark:active:bg-gray-700 flex items-center justify-center cursor-pointer disabled:opacity-50 after:content-[''] after:absolute after:-inset-1"
              @click="emit('refresh')"
            >
              <UIcon
                name="i-lucide-refresh-cw"
                :class="['size-3.5', tableLoading ? 'animate-spin text-blue-600 dark:text-blue-400' : '']"
              />
            </button>

            <label class="relative inline-flex items-center cursor-pointer">
              <span class="sr-only">Jumlah baris per halaman</span>
              <select
                :value="tablePerPage"
                aria-label="Jumlah baris per halaman"
                class="appearance-none h-9 pl-2.5 pr-7 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-xs font-medium text-gray-800 dark:text-gray-100 tabular-nums cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                @change="emit('update:tablePerPage', Number(($event.target as HTMLSelectElement).value))"
              >
                <option
                  v-for="opt in perPageOptions"
                  :key="opt.value"
                  :value="opt.value"
                  class="bg-white dark:bg-[#0e1424] text-gray-900 dark:text-gray-100"
                >
                  {{ opt.label }} / hal
                </option>
              </select>
              <UIcon
                name="i-lucide-chevron-down"
                class="size-3.5 text-gray-500 dark:text-gray-400 pointer-events-none absolute right-2"
                aria-hidden="true"
              />
            </label>
          </div>
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
              <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-200/80 dark:bg-gray-800 text-gray-600 dark:text-gray-300 leading-none tabular-nums">
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
                aria-label="Hapus filter kecamatan"
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
                aria-label="Hapus filter desa"
                class="p-0.5 rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-800/60 transition-colors cursor-pointer flex items-center justify-center text-blue-700 dark:text-blue-300 hover:text-blue-950 dark:hover:text-white"
                title="Hapus filter desa"
                @click="emit('update:modelDesa', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Tahun Pembangunan -->
            <span
              v-if="selectedTahunPembangunan"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Tahun:</span>
              <span class="font-semibold tracking-tight tabular-nums">{{ selectedTahunPembangunan }}</span>
              <button
                type="button"
                aria-label="Hapus filter tahun pembangunan"
                class="p-0.5 rounded-full hover:bg-emerald-200/60 dark:hover:bg-emerald-800/60 transition-colors cursor-pointer flex items-center justify-center text-emerald-700 dark:text-emerald-300 hover:text-emerald-950 dark:hover:text-white"
                title="Hapus filter tahun pembangunan"
                @click="emit('update:selectedTahunPembangunan', null); emit('update:selected-tahun-pembangunan', null)"
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
                aria-label="Hapus filter tipe"
                class="p-0.5 rounded-full hover:bg-indigo-200/60 dark:hover:bg-indigo-800/60 transition-colors cursor-pointer flex items-center justify-center text-indigo-700 dark:text-indigo-300 hover:text-indigo-950 dark:hover:text-white"
                title="Hapus filter tipe"
                @click="emit('update:selectedTipe', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Jenis Perkerasan -->
            <span
              v-if="selectedPerkerasan"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800/80 text-slate-900 dark:text-slate-200 border border-slate-200 dark:border-slate-700 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Perkerasan:</span>
              <span class="font-semibold tracking-tight">{{ selectedPerkerasan }}</span>
              <button
                type="button"
                aria-label="Hapus filter perkerasan"
                class="p-0.5 rounded-full hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition-colors cursor-pointer flex items-center justify-center text-slate-700 dark:text-slate-300 hover:text-slate-950 dark:hover:text-white"
                title="Hapus filter perkerasan"
                @click="emit('update:selectedPerkerasan', null); emit('update:selected-perkerasan', null)"
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
                aria-label="Hapus filter kondisi"
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
                aria-label="Hapus filter status verifikasi"
                class="p-0.5 rounded-full hover:bg-black/10 dark:hover:bg-white/10 transition-colors cursor-pointer flex items-center justify-center"
                title="Hapus filter status verifikasi"
                @click="emit('update:selectedStatusVerifikasi', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Status Jalan -->
            <span
              v-if="selectedStatusJalan"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-cyan-50 dark:bg-cyan-950/40 text-cyan-900 dark:text-cyan-200 border border-cyan-200 dark:border-cyan-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Jalan:</span>
              <span class="font-semibold tracking-tight">{{ selectedStatusJalan }}</span>
              <button
                type="button"
                aria-label="Hapus filter status jalan"
                class="p-0.5 rounded-full hover:bg-cyan-200/60 dark:hover:bg-cyan-800/60 transition-colors cursor-pointer flex items-center justify-center text-cyan-700 dark:text-cyan-300 hover:text-cyan-950 dark:hover:text-white"
                title="Hapus filter status jalan"
                @click="emit('update:selectedStatusJalan', null); emit('update:selected-status-jalan', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Status Kondisi -->
            <span
              v-if="selectedStatusKondisi"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-teal-50 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200 border border-teal-200 dark:border-teal-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Status:</span>
              <span class="font-semibold tracking-tight">{{ selectedStatusKondisi }}</span>
              <button
                type="button"
                aria-label="Hapus filter status kondisi"
                class="p-0.5 rounded-full hover:bg-teal-200/60 dark:hover:bg-teal-800/60 transition-colors cursor-pointer flex items-center justify-center text-teal-700 dark:text-teal-300 hover:text-teal-950 dark:hover:text-white"
                title="Hapus filter status kondisi"
                @click="emit('update:selectedStatusKondisi', null); emit('update:selected-status-kondisi', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Status Aset -->
            <span
              v-if="selectedStatusAset"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200 border border-purple-200 dark:border-purple-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Aset:</span>
              <span class="font-semibold tracking-tight">{{ selectedStatusAset }}</span>
              <button
                type="button"
                aria-label="Hapus filter status aset"
                class="p-0.5 rounded-full hover:bg-purple-200/60 dark:hover:bg-purple-800/60 transition-colors cursor-pointer flex items-center justify-center text-purple-700 dark:text-purple-300 hover:text-purple-950 dark:hover:text-white"
                title="Hapus filter status aset"
                @click="emit('update:selectedStatusAset', null); emit('update:selected-status-aset', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Chip Sumber Dana -->
            <span
              v-if="selectedSumberDana"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800/80 shrink-0 text-[11px] shadow-2xs"
            >
              <span class="text-[10px] uppercase tracking-wider font-medium opacity-70">Dana:</span>
              <span class="font-semibold tracking-tight">{{ selectedSumberDana }}</span>
              <button
                type="button"
                aria-label="Hapus filter sumber dana"
                class="p-0.5 rounded-full hover:bg-amber-200/60 dark:hover:bg-amber-800/60 transition-colors cursor-pointer flex items-center justify-center text-amber-700 dark:text-amber-300 hover:text-amber-950 dark:hover:text-white"
                title="Hapus filter sumber dana"
                @click="emit('update:selectedSumberDana', null); emit('update:selected-sumber-dana', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <!-- Tombol Reset Semua -->
            <button
              v-if="hasResettableFilters"
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
          <div
            v-if="tableLoading"
            role="status"
            aria-live="polite"
            aria-label="Memuat daftar segmen"
            :class="isMobileDrawer ? 'p-2.5 space-y-2.5 flex-1 overflow-hidden bg-gray-50/80 dark:bg-[#070b14]' : 'p-3 space-y-2 flex-1'"
          >
            <template v-if="isMobileDrawer">
              <div
                v-for="i in 4"
                :key="i"
                class="rounded-xl border border-gray-200/80 dark:border-gray-800 bg-white dark:bg-[#0e1424] p-3 space-y-2.5"
              >
                <div class="flex items-start justify-between gap-2">
                  <USkeleton class="h-4 w-3/4 rounded" />
                  <USkeleton class="h-4 w-14 rounded" />
                </div>
                <USkeleton class="h-3 w-1/2 rounded" />
                <div class="flex gap-1.5">
                  <USkeleton class="h-5 w-14 rounded-full" />
                  <USkeleton class="h-5 w-24 rounded-full" />
                </div>
              </div>
            </template>
            <template v-else>
              <USkeleton v-for="i in 5" :key="i" class="h-6 w-full rounded" />
            </template>
          </div>

          <!-- Error State -->
          <div
            v-else-if="tableError"
            class="flex flex-col items-center justify-center flex-1 gap-2.5 p-4 text-center"
          >
            <div class="size-9 rounded-full bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center">
              <UIcon name="i-lucide-alert-circle" class="size-5" />
            </div>
            <div class="space-y-0.5 max-w-md">
              <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                Gagal memuat daftar segmen
              </p>
              <p class="text-[11px] text-gray-500 dark:text-gray-400">
                {{ tableError }}
              </p>
            </div>
            <UButton
              icon="i-lucide-refresh-cw"
              label="Coba Lagi"
              :size="isMobileDrawer ? 'sm' : 'xs'"
              color="primary"
              variant="subtle"
              :class="isMobileDrawer ? 'min-h-[44px] px-4 cursor-pointer' : 'cursor-pointer'"
              @click="emit('refresh')"
            />
          </div>

          <!-- Empty State -->
          <div
            v-else-if="tableRows.length === 0"
            class="flex flex-col items-center justify-center flex-1 gap-2.5 text-gray-500 dark:text-gray-400 p-4 text-center"
          >
            <UIcon
              :name="hasResettableFilters || tableSearch ? 'i-lucide-search-x' : 'i-lucide-inbox'"
              class="size-6 text-gray-500 dark:text-gray-400"
            />
            <p class="text-xs text-gray-600 dark:text-gray-400 max-w-md">
              {{
                tableSearch
                  ? `Tidak ada segmen yang cocok dengan kata kunci "${tableSearch}".`
                  : hasResettableFilters
                    ? 'Tidak ada data segmen yang sesuai dengan kombinasi filter saat ini.'
                    : 'Belum ada data segmen fisik yang tersedia di wilayah ini.'
              }}
            </p>
            <div v-if="hasResettableFilters || tableSearch" class="flex flex-wrap items-center justify-center gap-2">
              <UButton
                v-if="tableSearch"
                icon="i-lucide-x"
                label="Hapus Pencarian"
                :size="isMobileDrawer ? 'sm' : 'xs'"
                color="neutral"
                variant="subtle"
                :class="isMobileDrawer ? 'min-h-[44px] px-4 cursor-pointer' : 'cursor-pointer'"
                @click="emit('update:tableSearch', '')"
              />
              <UButton
                v-if="hasResettableFilters"
                icon="i-lucide-rotate-ccw"
                label="Atur Ulang Filter"
                :size="isMobileDrawer ? 'sm' : 'xs'"
                color="primary"
                variant="subtle"
                :class="isMobileDrawer ? 'min-h-[44px] px-4 cursor-pointer' : 'cursor-pointer'"
                @click="emit('resetFilters')"
              />
            </div>
          </div>

          <!-- Mobile Card List View (< lg bottom drawer) -->
          <div
            v-else-if="isMobileDrawer"
            ref="mobileListRef"
            class="flex-1 overflow-y-auto overscroll-contain p-2.5 space-y-2.5 bg-gray-50/80 dark:bg-[#070b14]"
            style="-webkit-overflow-scrolling: touch; touch-action: pan-y;"
          >
            <article
              v-for="row in tableRows"
              :key="`card-${row.id}`"
              :class="[
                'rounded-xl border overflow-hidden transition-colors',
                row.id === selectedSegmenId
                  ? 'border-blue-500 ring-1 ring-blue-500/25 bg-blue-50/35 dark:bg-blue-950/30'
                  : 'border-gray-200/90 dark:border-gray-800 bg-white dark:bg-[#0e1424]'
              ]"
            >
              <!-- Card Body: Klik untuk fokus ke peta -->
              <button
                type="button"
                class="w-full text-left p-3 space-y-2 cursor-pointer active:bg-gray-50/80 dark:active:bg-gray-800/40 transition-colors"
                @click="emit('rowClick', row)"
              >
                <!-- Baris 1: Nama Ruas (lebar penuh) + Panjang di kanan atas -->
                <div class="flex items-start justify-between gap-2.5">
                  <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-semibold leading-snug text-gray-900 dark:text-white line-clamp-2 flex items-start gap-1.5">
                      <UIcon
                        v-if="selectedSegmenId === row.id"
                        name="i-lucide-map-pin"
                        class="size-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5"
                      />
                      <span>{{ row.namobj || 'Tanpa Nama' }}</span>
                    </p>
                  </div>

                  <span class="shrink-0 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[11px] font-mono font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ Number(row.panjang_meter_gis ?? row.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m
                  </span>
                </div>

                <!-- Baris 2: Wilayah Desa & Kecamatan -->
                <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 min-w-0">
                  <UIcon name="i-lucide-map-pin" class="size-3.5 text-gray-500 dark:text-gray-400 shrink-0" aria-hidden="true" />
                  <span class="truncate">
                    {{ formatWilayah(row.desa) }}, Kec. {{ formatWilayah(row.kecamatan) }}
                  </span>
                </div>

                <!-- Baris 3: Status Kondisi & Verifikasi + Detail Teknis -->
                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                  <UBadge
                    :label="getKondisiBadge(row.kondisi).label"
                    :color="getKondisiBadge(row.kondisi).color"
                    variant="subtle"
                    size="xs"
                  />
                  <UBadge
                    :label="getStatusBadge(row.status_verifikasi).label"
                    :color="getStatusBadge(row.status_verifikasi).color"
                    variant="subtle"
                    size="xs"
                  />
                  <span
                    v-if="row.lebar != null || row.jenis_perkerasan || row.tahun_pembangunan"
                    class="text-[11px] text-gray-600 dark:text-gray-400 tabular-nums ml-auto flex items-center gap-1.5"
                  >
                    <template v-if="row.lebar != null">
                      <span>L {{ Number(row.lebar).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m</span>
                    </template>
                    <template v-if="row.jenis_perkerasan">
                      <span v-if="row.lebar != null" aria-hidden="true">&middot;</span>
                      <span>{{ row.jenis_perkerasan }}</span>
                    </template>
                    <template v-if="row.tahun_pembangunan">
                      <span v-if="row.lebar != null || row.jenis_perkerasan" aria-hidden="true">&middot;</span>
                      <span>{{ row.tahun_pembangunan }}</span>
                    </template>
                  </span>
                </div>
              </button>

              <!-- Baris Aksi Bawah (Ringkas dengan target sentuh 44px) -->
              <div class="flex items-center justify-between gap-2 px-3 py-1.5 border-t border-gray-100 dark:border-gray-800/80 bg-gray-50/60 dark:bg-[#0b0f19]/60">
                <button
                  type="button"
                  class="relative inline-flex items-center gap-1.5 h-8 px-2 -ml-2 rounded-lg text-xs font-semibold text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/50 active:bg-blue-100 dark:active:bg-blue-900/50 transition-colors cursor-pointer after:content-[''] after:absolute after:-inset-1.5"
                  @click="emit('rowClick', row)"
                >
                  <UIcon name="i-lucide-locate-fixed" class="size-3.5 shrink-0" />
                  <span>Lihat di Peta</span>
                </button>

                <div class="flex items-center gap-2 shrink-0">
                  <button
                    v-if="isRowEditable(row)"
                    type="button"
                    :aria-label="`Edit ${row.namobj || 'segmen'}`"
                    class="relative inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 active:bg-gray-100 transition-colors cursor-pointer after:content-[''] after:absolute after:-inset-y-1.5 after:-inset-x-1"
                    @click="emit('editRow', row)"
                  >
                    <UIcon name="i-lucide-pencil" class="size-3.5 shrink-0" />
                    <span>Edit</span>
                  </button>

                  <button
                    v-if="isRowSubmittable(row)"
                    type="button"
                    :aria-label="`Ajukan verifikasi ${row.namobj || 'segmen'}`"
                    title="Ajukan Verifikasi"
                    class="relative inline-flex items-center justify-center size-8 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/70 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors cursor-pointer after:content-[''] after:absolute after:-inset-1.5"
                    @click="emit('submitRow', row)"
                  >
                    <UIcon name="i-lucide-send" class="size-3.5 shrink-0" />
                  </button>

                  <button
                    v-if="isRowDeletable(row)"
                    type="button"
                    :aria-label="`Hapus ${row.namobj || 'segmen'}`"
                    title="Hapus Segmen"
                    class="relative inline-flex items-center justify-center size-8 rounded-lg border border-rose-200 dark:border-rose-800/80 bg-rose-50/70 dark:bg-rose-950/35 text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition-colors cursor-pointer after:content-[''] after:absolute after:-inset-1.5"
                    @click="emit('deleteRow', row)"
                  >
                    <UIcon name="i-lucide-trash-2" class="size-3.5 shrink-0" />
                  </button>
                </div>
              </div>
            </article>
          </div>

          <!-- Desktop Table Content (Compacted 7 Scannable Columns + Sticky Aksi) -->
          <div v-else class="flex-1 overflow-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800 text-[11px] font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
                <tr>
                  <th class="py-2 px-3 min-w-[200px]">Nama Ruas / Segmen</th>
                  <th class="py-2 px-3 min-w-[150px]">Wilayah</th>
                  <th class="py-2 px-3 text-right">Dimensi</th>
                  <th class="py-2 px-3 min-w-[130px]">Fisik & Status</th>
                  <th class="py-2 px-3">Kondisi</th>
                  <th class="py-2 px-3">Verifikasi</th>
                  <th class="sticky right-0 z-20 bg-gray-50 dark:bg-[#0b0f19] py-2 px-3 text-center border-l border-gray-200 dark:border-gray-800">
                    Aksi
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60 font-sans">
                <tr
                  v-for="row in tableRows"
                  :key="row.id"
                  class="group cursor-pointer transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
                  :class="[
                    row.id === selectedSegmenId
                      ? 'bg-blue-50/70 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 font-medium'
                      : 'text-gray-700 dark:text-gray-300'
                  ]"
                  @click="emit('rowClick', row)"
                >
                  <td class="py-2 px-3 max-w-[260px]">
                    <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white truncate">
                      <UIcon
                        v-if="selectedSegmenId === row.id"
                        name="i-lucide-map-pin"
                        class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0"
                      />
                      <span class="truncate">{{ row.namobj || 'Tanpa Nama' }}</span>
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                      {{ row.tahun_pembangunan ? `Tahun ${row.tahun_pembangunan}` : 'Tahun -' }}
                    </div>
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap">
                    <div class="text-gray-800 dark:text-gray-200 font-medium">
                      {{ formatWilayah(row.desa) }}
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                      Kec. {{ formatWilayah(row.kecamatan) }}
                    </div>
                  </td>
                  <td class="py-2 px-3 text-right tabular-nums whitespace-nowrap">
                    <div class="font-semibold text-gray-900 dark:text-white">
                      {{ Number(row.panjang_meter_gis ?? row.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 4 }) }} m
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                      {{ row.lebar != null ? `L: ${Number(row.lebar).toLocaleString('id-ID', { maximumFractionDigits: 4 })} m` : 'L: -' }}
                    </div>
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap">
                    <div class="text-gray-800 dark:text-gray-200">
                      {{ row.jenis_perkerasan || '-' }}
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                      {{ row.status_jalan || '-' }}
                    </div>
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
                  <td
                    class="sticky right-0 z-10 py-2 px-3 text-center whitespace-nowrap border-l border-gray-100 dark:border-gray-800/80 transition-colors"
                    :class="row.id === selectedSegmenId ? 'bg-blue-50 dark:bg-[#0e192e]' : 'bg-white dark:bg-[#0b0f19] group-hover:bg-blue-50/70 dark:group-hover:bg-[#0d1526]'"
                    @click.stop
                  >
                    <div class="flex items-center justify-center gap-0.5">
                      <UButton
                        icon="i-lucide-locate-fixed"
                        size="xs"
                        color="primary"
                        variant="ghost"
                        :aria-label="`Zoom ke ${row.namobj || 'segmen'}`"
                        title="Zoom ke segmen di peta"
                        class="cursor-pointer text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                        @click="emit('rowClick', row)"
                      />
                      <UButton
                        v-if="isRowEditable(row)"
                        icon="i-lucide-pencil"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        :aria-label="`Edit ${row.namobj || 'segmen'}`"
                        title="Edit Segmen"
                        class="cursor-pointer"
                        @click="emit('editRow', row)"
                      />
                      <UButton
                        v-if="isRowSubmittable(row)"
                        icon="i-lucide-send"
                        size="xs"
                        color="primary"
                        variant="ghost"
                        :aria-label="`Ajukan verifikasi ${row.namobj || 'segmen'}`"
                        title="Ajukan Verifikasi"
                        class="cursor-pointer"
                        @click="emit('submitRow', row)"
                      />
                      <UButton
                        v-if="isRowDeletable(row)"
                        icon="i-lucide-trash-2"
                        size="xs"
                        color="error"
                        variant="ghost"
                        :aria-label="`Hapus ${row.namobj || 'segmen'}`"
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

          <!-- Pagination & Length Summary Footer -->
          <div
            v-if="tableTotal > 0 && isMobileDrawer"
            class="border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b0f19] px-3 py-2 shrink-0 space-y-2"
          >
            <div class="flex items-center justify-between gap-2 text-[11px] text-gray-600 dark:text-gray-400 tabular-nums">
              <span class="truncate">{{ pageRangeText }}</span>
              <span class="shrink-0">
                Total:
                <strong class="font-semibold text-gray-900 dark:text-gray-100">
                  {{ formattedTotalLength }}
                </strong>
              </span>
            </div>

            <div
              v-if="tableLastPage > 1"
              class="grid grid-cols-[auto_1fr_auto] items-center gap-2"
            >
              <button
                type="button"
                :disabled="tablePage <= 1 || tableLoading"
                aria-label="Halaman sebelumnya"
                class="inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-xs font-medium text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 active:bg-gray-100 disabled:opacity-40 disabled:pointer-events-none cursor-pointer transition-colors"
                @click="emit('update:tablePage', Math.max(1, tablePage - 1))"
              >
                <UIcon name="i-lucide-chevron-left" class="size-4 shrink-0" />
                <span class="hidden min-[360px]:inline">Sebelumnya</span>
              </button>

              <div class="flex justify-center min-w-0">
                <label
                  v-if="mobilePageOptions.length > 0"
                  class="relative inline-flex items-center cursor-pointer"
                >
                  <span class="sr-only">Pilih nomor halaman</span>
                  <select
                    :value="tablePage"
                    aria-label="Pilih nomor halaman"
                    class="appearance-none min-h-[44px] pl-3 pr-7 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#0e1424] text-xs font-semibold text-gray-800 dark:text-gray-100 tabular-nums cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    @change="emit('update:tablePage', Number(($event.target as HTMLSelectElement).value))"
                  >
                    <option
                      v-for="p in mobilePageOptions"
                      :key="p"
                      :value="p"
                      class="bg-white dark:bg-[#0e1424] text-gray-900 dark:text-gray-100"
                    >
                      Hal. {{ p }} / {{ tableLastPage }}
                    </option>
                  </select>
                  <UIcon
                    name="i-lucide-chevrons-up-down"
                    class="size-3.5 text-gray-500 dark:text-gray-400 pointer-events-none absolute right-2"
                    aria-hidden="true"
                  />
                </label>

                <span
                  v-else
                  class="inline-flex items-center justify-center min-h-[44px] px-3 rounded-xl bg-gray-50 dark:bg-[#0e1424] border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-800 dark:text-gray-100 tabular-nums"
                >
                  Hal. {{ tablePage }} / {{ tableLastPage.toLocaleString('id-ID') }}
                </span>
              </div>

              <button
                type="button"
                :disabled="tablePage >= tableLastPage || tableLoading"
                aria-label="Halaman berikutnya"
                class="inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#0e1424] text-xs font-medium text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 active:bg-gray-100 disabled:opacity-40 disabled:pointer-events-none cursor-pointer transition-colors"
                @click="emit('update:tablePage', Math.min(tableLastPage, tablePage + 1))"
              >
                <span class="hidden min-[360px]:inline">Berikutnya</span>
                <UIcon name="i-lucide-chevron-right" class="size-4 shrink-0" />
              </button>
            </div>
          </div>

          <div
            v-else-if="tableTotal > 0"
            class="flex flex-wrap items-center justify-between px-3 py-1.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-[#0d1117] text-[11px] shrink-0 gap-x-3 gap-y-1.5"
          >
            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 min-w-0 text-gray-600 dark:text-gray-400 tabular-nums">
              <span>{{ pageRangeText }}</span>
              <span class="text-gray-300 dark:text-gray-700 select-none" aria-hidden="true">|</span>
              <span class="inline-flex items-center gap-1">
                <span>Panjang total:</span>
                <strong class="font-semibold text-gray-900 dark:text-gray-100">
                  {{ formattedTotalLength }}
                </strong>
              </span>
            </div>

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

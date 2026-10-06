<script setup lang="ts">
import type { ConsoleLog } from '~/types/dataset-editor';

interface Props {
  collapsed?: boolean;
  height?: number;
  activeTab?: 'table' | 'console';
  tableRows?: any[];
  tableTotal?: number;
  tablePage?: number;
  tableLastPage?: number;
  tablePerPage?: number;
  tableSearch?: string;
  tableStatus?: string;
  tableLoading?: boolean;
  consoleLogs?: ConsoleLog[];
  selectedFeatureId?: string | null;
  isMobileDrawer?: boolean;
  collapsible?: boolean;
  kecamatanFilter?: string | null;
  desaFilter?: string | null;
  kondisiFilter?: string | null;
  perkerasanFilter?: string | null;
  canEditRow?: (row: any) => boolean;
  canSplitRow?: (row: any) => boolean;
  canDeleteRow?: (row: any) => boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  height: 240,
  activeTab: 'table',
  tableRows: () => [],
  tableTotal: 0,
  tablePage: 1,
  tableLastPage: 1,
  tablePerPage: 15,
  tableSearch: '',
  tableStatus: 'idle',
  tableLoading: false,
  consoleLogs: () => [],
  selectedFeatureId: null,
  isMobileDrawer: false,
  collapsible: true,
  kecamatanFilter: null,
  desaFilter: null,
  kondisiFilter: null,
  perkerasanFilter: null,
  canEditRow: () => true,
  canSplitRow: () => true,
  canDeleteRow: () => true,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'update:activeTab', tab: 'table' | 'console'): void;
  (e: 'update:tableSearch', search: string): void;
  (e: 'update:tablePage', page: number): void;
  (e: 'update:tablePerPage', perPage: number): void;
  (e: 'update:kecamatanFilter', val: string | null): void;
  (e: 'update:desaFilter', val: string | null): void;
  (e: 'update:kondisiFilter', val: string | null): void;
  (e: 'update:perkerasanFilter', val: string | null): void;
  (e: 'resetFilters'): void;
  (e: 'rowClick', row: any): void;
  (e: 'editRow', row: any): void;
  (e: 'splitRow', row: any): void;
  (e: 'deleteRow', row: any): void;
  (e: 'clearLogs'): void;
}>();

const isLoading = computed(() => props.tableLoading || props.tableStatus === 'pending');

const hasActiveFilters = computed(() => {
  return Boolean(
    props.kecamatanFilter ||
    props.desaFilter ||
    props.kondisiFilter ||
    props.perkerasanFilter
  );
});

// Page range text for pagination footer, e.g. "1 - 15 dari 1.250 data"
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
  if (!kondisi) return { label: '-', color: 'neutral' as const };
  const k = kondisi.toLowerCase();
  if (k.includes('baik')) return { label: kondisi, color: 'success' as const };
  if (k.includes('sedang')) return { label: kondisi, color: 'warning' as const };
  if (k.includes('rusak berat')) return { label: kondisi, color: 'error' as const };
  if (k.includes('rusak')) return { label: kondisi, color: 'warning' as const };
  return { label: kondisi, color: 'neutral' as const };
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
      'flex flex-col overflow-hidden w-full h-full bg-white dark:bg-[#0b0f19] select-none',
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
                placeholder="Pencarian ruas..."
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
            placeholder="Cari nama ruas, desa, kecamatan..."
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
            class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50/90 dark:bg-gray-900/60 border-b border-gray-200/80 dark:border-gray-800 text-[11px] overflow-x-auto shrink-0 scrollbar-none"
          >
            <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0 flex items-center gap-1 text-[11px]">
              <UIcon name="i-lucide-filter" class="size-3 text-blue-600 dark:text-blue-400" />
              Filter:
            </span>

            <span
              v-if="kecamatanFilter"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 font-medium shrink-0 text-[11px]"
            >
              Kec. {{ kecamatanFilter }}
              <button
                type="button"
                class="hover:text-blue-900 dark:hover:text-blue-100 cursor-pointer flex items-center"
                title="Hapus filter kecamatan"
                @click="emit('update:kecamatanFilter', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <span
              v-if="desaFilter"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 font-medium shrink-0 text-[11px]"
            >
              Desa {{ desaFilter }}
              <button
                type="button"
                class="hover:text-blue-900 dark:hover:text-blue-100 cursor-pointer flex items-center"
                title="Hapus filter desa"
                @click="emit('update:desaFilter', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <span
              v-if="kondisiFilter"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 font-medium shrink-0 text-[11px]"
            >
              Kondisi: {{ kondisiFilter }}
              <button
                type="button"
                class="hover:text-gray-900 dark:hover:text-white cursor-pointer flex items-center"
                title="Hapus filter kondisi"
                @click="emit('update:kondisiFilter', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <span
              v-if="perkerasanFilter"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 font-medium shrink-0 text-[11px]"
            >
              Perkerasan: {{ perkerasanFilter }}
              <button
                type="button"
                class="hover:text-gray-900 dark:hover:text-white cursor-pointer flex items-center"
                title="Hapus filter perkerasan"
                @click="emit('update:perkerasanFilter', null)"
              >
                <UIcon name="i-lucide-x" class="size-3" />
              </button>
            </span>

            <button
              type="button"
              class="ml-auto text-[11px] text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 font-medium cursor-pointer shrink-0 underline decoration-dotted"
              @click="emit('resetFilters')"
            >
              Reset Semua
            </button>
          </div>

          <!-- Loading State -->
          <div v-if="isLoading" class="p-3 space-y-2 flex-1">
            <USkeleton v-for="i in 5" :key="i" class="h-6 w-full rounded" />
          </div>

          <!-- Empty State -->
          <div
            v-else-if="tableRows.length === 0"
            class="flex flex-col items-center justify-center flex-1 gap-2 text-gray-400 p-4"
          >
            <UIcon name="i-lucide-inbox" class="size-6 text-gray-400" />
            <p class="text-xs text-gray-600 dark:text-gray-400">Tidak ada data ruas jalan yang sesuai.</p>
          </div>

          <!-- Native Clean Table Content -->
          <div v-else class="flex-1 overflow-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800 text-[11px] font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
                <tr>
                  <th class="py-2 px-3">Kode</th>
                  <th class="py-2 px-3 min-w-[200px]">Nama Ruas</th>
                  <th class="py-2 px-3">Desa</th>
                  <th class="py-2 px-3">Kecamatan</th>
                  <th class="py-2 px-3 text-right">Panjang (m)</th>
                  <th class="py-2 px-3">Perkerasan</th>
                  <th class="py-2 px-3">Kondisi</th>
                  <th class="py-2 px-3">Status</th>
                  <th class="py-2 px-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60 font-sans">
                <tr
                  v-for="row in tableRows"
                  :key="row.id"
                  class="cursor-pointer transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
                  :class="[
                    String(row.id) === String(selectedFeatureId)
                      ? 'bg-blue-50/70 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 font-medium'
                      : 'text-gray-700 dark:text-gray-300'
                  ]"
                  @click="emit('rowClick', row)"
                >
                  <td class="py-2 px-3 whitespace-nowrap text-gray-500 font-mono">
                    {{ row.kode_ruas ?? '-' }}
                  </td>
                  <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white truncate max-w-[260px]">
                    <div class="flex items-center gap-1.5 truncate">
                      <UIcon
                        v-if="String(selectedFeatureId) === String(row.id)"
                        name="i-lucide-map-pin"
                        class="size-3 text-blue-500 shrink-0"
                      />
                      <span class="truncate">{{ row.nama_ruas || 'Tanpa Nama' }}</span>
                    </div>
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.desa || '-' }}
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.kecamatan || '-' }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono font-medium whitespace-nowrap">
                    {{ (row.panjang_meter != null && row.panjang_meter !== '') ? Number(row.panjang_meter).toLocaleString('id-ID', { maximumFractionDigits: 1 }) : (row.panjang != null && row.panjang !== '' ? Number(row.panjang).toLocaleString('id-ID', { maximumFractionDigits: 1 }) : '-') }}
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.perkerasan || '-' }}
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap">
                    <UBadge
                      :label="getKondisiBadge(row.kondisi).label"
                      :color="getKondisiBadge(row.kondisi).color"
                      variant="subtle"
                      size="xs"
                    />
                  </td>
                  <td class="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                    {{ row.status_eksisting || '-' }}
                  </td>
                  <td class="py-2 px-3 text-center whitespace-nowrap" @click.stop>
                    <div class="flex items-center justify-center gap-0.5">
                      <UButton
                        icon="i-lucide-locate-fixed"
                        size="xs"
                        color="primary"
                        variant="ghost"
                        title="Zoom ke ruas di peta"
                        class="cursor-pointer text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                        @click="emit('rowClick', row)"
                      />
                      <UButton
                        v-if="canEditRow ? canEditRow(row) : true"
                        icon="i-lucide-pencil"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        title="Edit Ruas"
                        class="cursor-pointer"
                        @click="emit('editRow', row)"
                      />
                      <UButton
                        v-if="canSplitRow ? canSplitRow(row) : true"
                        icon="i-lucide-scissors"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        title="Split / Pecah Ruas"
                        class="cursor-pointer text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                        @click="emit('splitRow', row)"
                      />
                      <UButton
                        v-if="canDeleteRow ? canDeleteRow(row) : true"
                        icon="i-lucide-trash-2"
                        size="xs"
                        color="error"
                        variant="ghost"
                        title="Hapus Ruas"
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

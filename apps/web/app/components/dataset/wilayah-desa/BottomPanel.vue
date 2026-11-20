<script setup lang="ts">
import { computed } from "vue";
import type { ConsoleLog } from "~/types/dataset-editor";
import type { KecamatanSeriesItem } from "~/types/wilayah-desa";

interface Props {
  collapsed?: boolean;
  activeTab?: "table" | "console";
  tableRows?: any[];
  tableTotal?: number;
  tablePage?: number;
  tableLastPage?: number;
  tablePerPage?: number;
  tableSearch?: string;
  tableStatus?: string;
  tableLoading?: boolean;
  consoleLogs?: ConsoleLog[];
  selectedFeatureId?: number | null;
  isMobileDrawer?: boolean;
  collapsible?: boolean;
  hasActiveFilter?: boolean;
  kecamatanList?: KecamatanSeriesItem[];
  selectedKecamatanId?: number | null;
  isKecamatanRestricted?: boolean;
  resolveKecamatanName: (kecId: number | null | undefined) => string;
  canEditRow?: (row: any) => boolean;
  canDeleteRow?: (row: any) => boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  activeTab: "table",
  tableRows: () => [],
  tableTotal: 0,
  tablePage: 1,
  tableLastPage: 1,
  tablePerPage: 15,
  tableSearch: "",
  tableStatus: "idle",
  tableLoading: false,
  consoleLogs: () => [],
  selectedFeatureId: null,
  isMobileDrawer: false,
  collapsible: true,
  hasActiveFilter: false,
  kecamatanList: () => [],
  selectedKecamatanId: null,
  isKecamatanRestricted: false,
  canEditRow: () => true,
  canDeleteRow: () => true,
});

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "update:activeTab", tab: "table" | "console"): void;
  (e: "update:tableSearch", search: string): void;
  (e: "update:tablePage", page: number): void;
  (e: "update:tablePerPage", perPage: number): void;
  (e: "update:selectedKecamatanId", kecId: number | null): void;
  (e: "resetFilters"): void;
  (e: "retryFetch"): void;
  (e: "rowClick", row: any): void;
  (e: "editRow", row: any): void;
  (e: "deleteRow", row: any): void;
  (e: "clearLogs"): void;
}>();

const isLoading = computed(() => props.tableLoading || props.tableStatus === "pending");
const isError = computed(() => props.tableStatus === "error");

const activeKecamatanLabel = computed(() => {
  if (!props.selectedKecamatanId) return null;
  return props.resolveKecamatanName(props.selectedKecamatanId);
});

const hasClearableFilter = computed(() =>
  Boolean(props.tableSearch || (props.selectedKecamatanId !== null && !props.isKecamatanRestricted))
);

function handleKecamatanSelectChange(event: Event) {
  const target = event.target as HTMLSelectElement | null;
  const val = target?.value;
  emit("update:selectedKecamatanId", val ? Number(val) : null);
}

const pageRangeText = computed(() => {
  if (!props.tableTotal) return "Tidak ada data";
  const start = (props.tablePage - 1) * props.tablePerPage + 1;
  const end = Math.min(props.tablePage * props.tablePerPage, props.tableTotal);
  const base = `${start.toLocaleString("id-ID")} - ${end.toLocaleString("id-ID")} dari ${props.tableTotal.toLocaleString("id-ID")} desa`;
  return activeKecamatanLabel.value ? `${base} (Kec. ${activeKecamatanLabel.value})` : base;
});

const perPageOptions = [10, 15, 25, 50, 100];

function handleHeaderClick(e: MouseEvent) {
  if (!props.collapsible || props.isMobileDrawer) return;
  const target = e.target as HTMLElement | null;
  if (!target) return;
  if (target.closest('button, input, select, textarea, a, [role="button"], .stop-header-toggle')) {
    return;
  }
  emit("update:collapsed", !props.collapsed);
}

function handleTabClick(tab: "table" | "console") {
  if (props.collapsed) {
    emit("update:activeTab", tab);
    emit("update:collapsed", false);
  } else if (props.activeTab === tab) {
    emit("update:collapsed", true);
  } else {
    emit("update:activeTab", tab);
  }
}
</script>

<template>
  <div
    :class="[
      'flex flex-col overflow-hidden w-full h-full bg-white dark:bg-[#0b0f19] select-none',
      !isMobileDrawer && 'border-t border-gray-200 dark:border-gray-800',
    ]"
  >
    <!-- Sticky Header Strip -->
    <div
      class="shrink-0 z-20 bg-white dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800 select-none transition-colors duration-150"
      :class="[
        isMobileDrawer ? 'p-3 space-y-2' : 'flex items-center h-10 px-3 gap-2',
        !isMobileDrawer && collapsible && 'cursor-pointer hover:bg-gray-50/75 dark:hover:bg-gray-800/40',
      ]"
      :title="!isMobileDrawer && collapsible ? (collapsed ? 'Klik untuk membuka panel bawah' : 'Klik header untuk menutup panel bawah') : undefined"
      @click="handleHeaderClick"
    >
      <!-- Desktop Header -->
      <template v-if="!isMobileDrawer">
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800/80 p-0.5 rounded-lg stop-header-toggle" @click.stop>
          <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeTab === 'table'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
            "
            @click="handleTabClick('table')"
          >
            <UIcon name="i-lucide-table" class="size-3.5" aria-hidden="true" />
            <span>Tabel Atribut</span>
            <span
              v-if="tableTotal > 0"
              class="px-1.5 rounded-full text-[10px] tabular-nums bg-blue-50 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 font-semibold"
            >
              {{ tableTotal.toLocaleString("id-ID") }}
            </span>
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeTab === 'console'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
                : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
            "
            @click="handleTabClick('console')"
          >
            <UIcon name="i-lucide-terminal" class="size-3.5" aria-hidden="true" />
            <span>Konsol Sistem</span>
            <span
              v-if="consoleLogs.length > 0"
              class="px-1.5 rounded-full text-[10px] tabular-nums bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-medium"
            >
              {{ consoleLogs.length }}
            </span>
          </button>
        </div>

        <div class="ml-auto flex items-center gap-2 shrink-0 stop-header-toggle" @click.stop>
          <template v-if="activeTab === 'table'">
            <div v-show="!collapsed" class="hidden xl:flex items-center gap-1.5 shrink-0">
              <span class="text-gray-600 dark:text-gray-400 text-[11px]">Baris:</span>
              <div class="flex items-center gap-0.5">
                <button
                  v-for="opt in perPageOptions"
                  :key="opt"
                  type="button"
                  :class="[
                    'px-1.5 py-0.5 rounded text-[11px] tabular-nums transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                    tablePerPage === opt
                      ? 'bg-blue-600 text-white font-semibold shadow-xs'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                  ]"
                  :title="`Tampilkan ${opt} baris per halaman`"
                  @click="emit('update:tablePerPage', opt)"
                >
                  {{ opt }}
                </button>
              </div>
            </div>

            <div v-show="!collapsed" class="w-36 sm:w-44 lg:w-48 shrink-0">
              <select
                :value="selectedKecamatanId ?? ''"
                :disabled="isKecamatanRestricted"
                aria-label="Filter kecamatan pada tabel atribut"
                class="w-full h-7 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
                @change="handleKecamatanSelectChange"
              >
                <option v-if="!isKecamatanRestricted" value="">Semua Kecamatan</option>
                <option
                  v-for="kec in kecamatanList"
                  :key="kec.id"
                  :value="kec.id"
                >
                  Kec. {{ kec.nama_kecamatan }} ({{ kec.jumlah_desa }})
                </option>
              </select>
            </div>

            <div v-show="!collapsed" class="w-36 sm:w-44 lg:w-52 shrink-0">
              <UInput
                :model-value="tableSearch"
                icon="i-lucide-search"
                placeholder="Cari desa, pimpinan, NIP..."
                aria-label="Cari data wilayah desa"
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
                    aria-label="Bersihkan pencarian"
                    class="cursor-pointer -mr-1 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    @click="emit('update:tableSearch', '')"
                  />
                </template>
              </UInput>
            </div>

            <button
              v-show="!collapsed && hasClearableFilter"
              type="button"
              title="Reset filter kecamatan dan pencarian"
              class="h-7 px-2 inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 text-[11px] font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="emit('resetFilters')"
            >
              <UIcon name="i-lucide-filter-x" class="size-3" aria-hidden="true" />
              <span class="hidden sm:inline">Reset</span>
            </button>
          </template>

          <template v-else>
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

          <UTooltip v-if="collapsible" :text="collapsed ? 'Buka panel bawah' : 'Tutup panel bawah'">
            <button
              type="button"
              :aria-expanded="!collapsed"
              :aria-label="collapsed ? 'Buka panel bawah' : 'Tutup panel bawah'"
              class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click.stop="emit('update:collapsed', !collapsed)"
            >
              <UIcon
                name="i-lucide-chevron-down"
                :class="['size-4 transition-transform duration-200', collapsed ? 'rotate-180' : 'rotate-0']"
                aria-hidden="true"
              />
            </button>
          </UTooltip>
        </div>
      </template>

      <!-- Mobile Drawer Header -->
      <template v-else>
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 min-w-0">
            <UIcon name="i-lucide-table" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
            <span class="text-xs font-semibold text-gray-900 dark:text-white shrink-0">Tabel Atribut</span>
            <span class="rounded px-1.5 py-0.5 text-[11px] font-medium tabular-nums bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
              {{ tableTotal.toLocaleString("id-ID") }} desa
            </span>
          </div>

          <div class="flex items-center gap-1 shrink-0">
            <button
              v-for="opt in [10, 15, 25, 50]"
              :key="opt"
              type="button"
              :class="[
                'min-h-[36px] min-w-[32px] px-1.5 rounded text-[11px] tabular-nums transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                tablePerPage === opt
                  ? 'bg-blue-600 text-white font-semibold'
                  : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
              ]"
              @click="emit('update:tablePerPage', opt)"
            >
              {{ opt }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <select
            :value="selectedKecamatanId ?? ''"
            :disabled="isKecamatanRestricted"
            aria-label="Filter kecamatan pada tabel atribut"
            class="w-full min-h-[40px] text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
            @change="handleKecamatanSelectChange"
          >
            <option v-if="!isKecamatanRestricted" value="">Semua Kecamatan ({{ kecamatanList.length }})</option>
            <option
              v-for="kec in kecamatanList"
              :key="kec.id"
              :value="kec.id"
            >
              Kec. {{ kec.nama_kecamatan }} ({{ kec.jumlah_desa }} Desa)
            </option>
          </select>

          <div class="flex items-center gap-1.5 w-full">
            <UInput
              :model-value="tableSearch"
              icon="i-lucide-search"
              placeholder="Cari desa, pimpinan, NIP..."
              aria-label="Cari data wilayah desa"
              size="md"
              class="w-full"
              @update:model-value="emit('update:tableSearch', String($event))"
            >
              <template v-if="tableSearch" #trailing>
                <UButton
                  icon="i-lucide-x"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  aria-label="Bersihkan pencarian"
                  @click="emit('update:tableSearch', '')"
                />
              </template>
            </UInput>

            <button
              v-if="hasClearableFilter"
              type="button"
              aria-label="Reset filter wilayah"
              title="Reset filter"
              class="min-h-[40px] px-2.5 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="emit('resetFilters')"
            >
              <UIcon name="i-lucide-filter-x" class="size-4" aria-hidden="true" />
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Panel Content -->
    <div
      v-show="isMobileDrawer || !collapsed"
      class="flex-1 flex flex-col min-h-0 bg-white dark:bg-[#0b0f19]"
    >
      <!-- TAB 1: TABEL ATRIBUT -->
      <template v-if="activeTab === 'table'">
        <!-- Loading State -->
        <div v-if="isLoading" class="p-3 space-y-2 flex-1">
          <USkeleton v-for="i in 5" :key="i" class="h-7 w-full rounded" />
        </div>

        <!-- Error State -->
        <div
          v-else-if="isError"
          class="flex flex-col items-center justify-center flex-1 gap-2 p-6 text-center"
        >
          <UIcon name="i-lucide-alert-triangle" class="size-6 text-rose-600 dark:text-rose-400" aria-hidden="true" />
          <p class="text-xs font-semibold text-gray-900 dark:text-white">
            Gagal memuat daftar wilayah desa
          </p>
          <p class="text-xs text-gray-600 dark:text-gray-400">
            Periksa koneksi API lalu coba muat ulang tabel.
          </p>
          <button
            type="button"
            class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="emit('retryFetch')"
          >
            <UIcon name="i-lucide-refresh-cw" class="size-3.5" aria-hidden="true" />
            <span>Coba Lagi</span>
          </button>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="tableRows.length === 0"
          class="flex flex-col items-center justify-center flex-1 gap-2 p-6 text-center"
        >
          <UIcon name="i-lucide-inbox" class="size-6 text-gray-500 dark:text-gray-400" aria-hidden="true" />
          <p class="text-xs font-semibold text-gray-900 dark:text-white">
            Tidak ada data wilayah desa yang sesuai
          </p>
          <p class="text-xs text-gray-600 dark:text-gray-400">
            {{ hasActiveFilter || tableSearch ? 'Sesuaikan kata kunci atau reset filter wilayah aktif.' : 'Belum ada data desa yang tercatat.' }}
          </p>
          <button
            v-if="hasActiveFilter || tableSearch"
            type="button"
            class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="emit('resetFilters')"
          >
            <UIcon name="i-lucide-rotate-ccw" class="size-3.5" aria-hidden="true" />
            <span>Reset Filter</span>
          </button>
        </div>

        <!-- Data Table -->
        <div v-else class="flex-1 overflow-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0f1422] border-b border-gray-200 dark:border-gray-800 text-[11px] font-semibold text-gray-600 dark:text-gray-300 whitespace-nowrap">
              <tr>
                <th class="py-2 px-3">Nama Desa</th>
                <th class="py-2 px-3">Kecamatan</th>
                <th class="py-2 px-3 text-right">Luas (Ha)</th>
                <th class="py-2 px-3">Pimpinan & Jabatan</th>
                <th class="py-2 px-3">NIP / Pangkat</th>
                <th class="py-2 px-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70">
              <tr
                v-for="row in tableRows"
                :key="row.id"
                class="cursor-pointer transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/25"
                :class="[
                  selectedFeatureId === Number(row.id)
                    ? 'bg-blue-50/75 dark:bg-blue-950/45 text-blue-900 dark:text-blue-200 font-medium'
                    : 'text-gray-800 dark:text-gray-200',
                ]"
                @click="emit('rowClick', row)"
              >
                <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <UIcon
                      v-if="selectedFeatureId === Number(row.id)"
                      name="i-lucide-map-pin"
                      class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0"
                      aria-hidden="true"
                    />
                    <span>{{ row.nama_desa || '-' }}</span>
                  </div>
                </td>
                <td class="py-2 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                  {{ resolveKecamatanName(row.id_kecamatan) }}
                </td>
                <td class="py-2 px-3 text-right font-semibold text-blue-600 dark:text-blue-400 whitespace-nowrap tabular-nums">
                  {{ Number(row.luas_hektar || 0).toLocaleString("id-ID", { maximumFractionDigits: 2 }) }}
                </td>
                <td class="py-2 px-3 whitespace-nowrap">
                  <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ row.nama_pimpinan || '-' }}
                  </div>
                  <div class="text-[11px] text-gray-600 dark:text-gray-400">
                    {{ row.nama_jabatan || '-' }}
                  </div>
                </td>
                <td class="py-2 px-3 whitespace-nowrap">
                  <div class="tabular-nums text-gray-800 dark:text-gray-200">
                    {{ row.nip || '-' }}
                  </div>
                  <div class="text-[11px] text-gray-600 dark:text-gray-400">
                    {{ row.pangkat_gol || '-' }}
                  </div>
                </td>
                <td class="py-2 px-3 text-center whitespace-nowrap" @click.stop>
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      aria-label="Fokus ke desa di peta"
                      title="Lihat di Peta"
                      class="min-h-[36px] min-w-[36px] lg:min-h-0 lg:min-w-0 lg:size-7 inline-flex items-center justify-center rounded-md text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                      @click="emit('rowClick', row)"
                    >
                      <UIcon name="i-lucide-locate-fixed" class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                      v-if="canEditRow ? canEditRow(row) : true"
                      type="button"
                      aria-label="Edit data desa"
                      title="Edit Desa"
                      class="min-h-[36px] min-w-[36px] lg:min-h-0 lg:min-w-0 lg:size-7 inline-flex items-center justify-center rounded-md text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                      @click="emit('editRow', row)"
                    >
                      <UIcon name="i-lucide-pencil" class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                      v-if="canDeleteRow ? canDeleteRow(row) : true"
                      type="button"
                      aria-label="Hapus data desa"
                      title="Hapus Desa"
                      class="min-h-[36px] min-w-[36px] lg:min-h-0 lg:min-w-0 lg:size-7 inline-flex items-center justify-center rounded-md text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                      @click="emit('deleteRow', row)"
                    >
                      <UIcon name="i-lucide-trash-2" class="size-3.5" aria-hidden="true" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div
          v-if="tableTotal > 0"
          class="flex items-center justify-between px-3 py-1.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-[#0d1117] text-[11px] shrink-0 gap-3"
        >
          <span class="text-gray-600 dark:text-gray-400 shrink-0 tabular-nums">
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
        <div class="flex-1 flex flex-col min-h-0 bg-[#070b14] text-gray-200 font-mono text-[11px] p-3 overflow-y-auto">
          <div v-if="consoleLogs.length === 0" class="flex flex-col items-center justify-center flex-1 text-gray-400 gap-1.5 py-6">
            <UIcon name="i-lucide-terminal" class="size-6 text-gray-400" aria-hidden="true" />
            <span>Belum ada log aktivitas GIS.</span>
          </div>
          <div v-else class="space-y-1">
            <div
              v-for="(log, idx) in consoleLogs"
              :key="idx"
              class="flex items-start gap-2 py-0.5 leading-relaxed hover:bg-white/5 rounded px-1"
            >
              <span class="text-gray-400 shrink-0 text-[10px] tabular-nums">{{ log.time }}</span>
              <span
                class="px-1 py-0.2 rounded text-[10px] font-bold shrink-0 leading-tight"
                :class="{
                  'bg-blue-900/60 text-blue-200 border border-blue-700/50': log.level === 'INFO',
                  'bg-amber-900/60 text-amber-200 border border-amber-700/50': log.level === 'WARN',
                  'bg-rose-900/60 text-rose-200 border border-rose-700/50': log.level === 'ERROR',
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
  </div>
</template>

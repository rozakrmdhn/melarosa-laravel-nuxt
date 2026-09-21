<script setup lang="ts">
import type { ConsoleLog } from "~/types/dataset-editor";

const props = withDefaults(
  defineProps<{
    collapsed?: boolean;
    height?: number;
    activeTab?: "table" | "console";
    tableRows?: any[];
    tableTotal?: number;
    tablePage?: number;
    tableLastPage?: number;
    tablePerPage?: number;
    tableSearch?: string;
    tableStatus?: string;
    consoleLogs?: ConsoleLog[];
    selectedFeatureId?: string | null;
    isMobileDrawer?: boolean;
    collapsible?: boolean;
    kecamatanFilter?: string | null;
    desaFilter?: string | null;
    kondisiFilter?: string | null;
    perkerasanFilter?: string | null;
  }>(),
  {
    collapsed: false,
    height: 240,
    activeTab: "table",
    tableRows: () => [],
    tableTotal: 0,
    tablePage: 1,
    tableLastPage: 1,
    tablePerPage: 15,
    tableSearch: "",
    tableStatus: "idle",
    consoleLogs: () => [],
    selectedFeatureId: null,
    isMobileDrawer: false,
    collapsible: true,
    kecamatanFilter: null,
    desaFilter: null,
    kondisiFilter: null,
    perkerasanFilter: null,
  }
);

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "update:activeTab", tab: "table" | "console"): void;
  (e: "update:tableSearch", search: string): void;
  (e: "update:tablePage", page: number): void;
  (e: "update:tablePerPage", perPage: number): void;
  (e: "update:kecamatanFilter", val: string | null): void;
  (e: "update:desaFilter", val: string | null): void;
  (e: "update:kondisiFilter", val: string | null): void;
  (e: "update:perkerasanFilter", val: string | null): void;
  (e: "resetFilters"): void;
  (e: "rowClick", row: any): void;
  (e: "editRow", row: any): void;
  (e: "splitRow", row: any): void;
  (e: "deleteRow", row: any): void;
  (e: "clearLogs"): void;
}>();

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
  if (!props.tableTotal) return "Tidak ada data";
  const start = (props.tablePage - 1) * props.tablePerPage + 1;
  const end = Math.min(props.tablePage * props.tablePerPage, props.tableTotal);
  const total = props.tableTotal.toLocaleString("id-ID");
  return `${start} - ${end} dari ${total} data`;
});

const perPageOptions = [
  { label: "10", value: 10 },
  { label: "15", value: 15 },
  { label: "25", value: 25 },
  { label: "50", value: 50 },
  { label: "100", value: 100 },
];

const tableColumns = [
  { accessorKey: "kode_ruas", header: "Kode" },
  { accessorKey: "nama_ruas", header: "Nama Ruas" },
  { accessorKey: "desa", header: "Desa" },
  { accessorKey: "kecamatan", header: "Kecamatan" },
  { accessorKey: "panjang", header: "Panjang (m)" },
  { accessorKey: "perkerasan", header: "Perkerasan" },
  { accessorKey: "kondisi", header: "Kondisi" },
  { accessorKey: "status_eksisting", header: "Status" },
  { accessorKey: "actions", header: "Aksi" },
];

function getKondisiBadgeColor(kondisi: string | null | undefined): "success" | "warning" | "error" | "neutral" {
  if (!kondisi) return "neutral";
  const k = kondisi.toLowerCase();
  if (k.includes("baik")) return "success";
  if (k.includes("sedang")) return "warning";
  if (k.includes("rusak berat")) return "error";
  if (k.includes("rusak")) return "warning";
  return "neutral";
}
</script>

<template>
  <div
    :class="[
      'flex flex-col select-none overflow-hidden w-full h-full',
      isMobileDrawer
        ? 'bg-white dark:bg-[#0b0f19]'
        : 'bg-white dark:bg-[#0b0f19]'
    ]"
  >
    <!-- Header Strip (Sticky Controls Area) -->
    <div
      class="shrink-0 z-20 bg-white dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800"
      :class="isMobileDrawer ? 'p-3 space-y-2' : 'flex items-center h-10 px-3 gap-2'"
    >
      <!-- Desktop Layout: Single Compact Row -->
      <template v-if="!isMobileDrawer">
        <!-- Left: Judul Tabel Atribut & Badge Total Data -->
        <div class="flex items-center gap-2 min-w-0">
          <UIcon name="i-lucide-table" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 shrink-0">Tabel Atribut</span>
          <UBadge
            :label="`${tableTotal.toLocaleString('id-ID')} data`"
            color="neutral"
            variant="subtle"
            size="xs"
            class="font-mono text-[10px]"
          />
        </div>

        <!-- Right: Filter Daftar Baris + Pencarian + Collapse Button -->
        <div class="ml-auto flex items-center gap-2 sm:gap-3 shrink-0">
          <!-- Filter Daftar Baris (Rows Per Page) -->
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
                    ? 'bg-emerald-600 text-white font-semibold shadow-xs'
                    : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200'
                ]"
                :title="`Tampilkan ${opt.label} baris per halaman`"
                @click="emit('update:tablePerPage', opt.value)"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>

          <!-- Fitur Pencarian Ruas -->
          <div v-show="!collapsed" class="w-36 sm:w-48 lg:w-60 transition-opacity duration-150">
            <UInput
              :model-value="tableSearch"
              icon="i-lucide-search"
              placeholder="Pencarian data..."
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

          <!-- Tombol Collapse Panel -->
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
              @click="emit('update:collapsed', !collapsed)"
            />
          </UTooltip>
        </div>
      </template>

      <!-- Mobile Layout: 2-Row Sticky Header -->
      <template v-else>
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 min-w-0">
            <UIcon name="i-lucide-table" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 shrink-0">Tabel Atribut</span>
            <UBadge
              :label="`${tableTotal.toLocaleString('id-ID')} data`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="font-mono text-[10px]"
            />
          </div>

          <!-- Filter Daftar Baris Mobile -->
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
                    ? 'bg-emerald-600 text-white font-semibold'
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

    <!-- Content: Attribute Table (v-show keeps virtual DOM in memory for zero lag open/close) -->
    <Transition name="panel-body-fade">
      <div
        v-show="isMobileDrawer || !collapsed"
        class="flex-1 flex flex-col min-h-0 bg-white dark:bg-[#0b0f19]"
      >
      <!-- Active Filter Chips Bar -->
      <div
        v-if="hasActiveFilters"
        class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50/90 dark:bg-gray-900/60 border-b border-gray-200/80 dark:border-gray-800 text-[11px] overflow-x-auto shrink-0 scrollbar-none"
      >
        <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0 flex items-center gap-1 text-[11px]">
          <UIcon name="i-lucide-filter" class="size-3 text-emerald-600 dark:text-emerald-400" />
          Filter:
        </span>

        <!-- Kecamatan Chip -->
        <span
          v-if="kecamatanFilter"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-medium shrink-0 text-[11px]"
        >
          Kec. {{ kecamatanFilter }}
          <button
            type="button"
            class="hover:text-emerald-900 dark:hover:text-emerald-100 cursor-pointer flex items-center"
            title="Hapus filter kecamatan"
            @click="emit('update:kecamatanFilter', null)"
          >
            <UIcon name="i-lucide-x" class="size-3" />
          </button>
        </span>

        <!-- Desa Chip -->
        <span
          v-if="desaFilter"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-medium shrink-0 text-[11px]"
        >
          Desa {{ desaFilter }}
          <button
            type="button"
            class="hover:text-emerald-900 dark:hover:text-emerald-100 cursor-pointer flex items-center"
            title="Hapus filter desa"
            @click="emit('update:desaFilter', null)"
          >
            <UIcon name="i-lucide-x" class="size-3" />
          </button>
        </span>

        <!-- Kondisi Chip -->
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

        <!-- Perkerasan Chip -->
        <span
          v-if="perkerasanFilter"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 font-medium shrink-0 text-[11px]"
        >
          {{ perkerasanFilter }}
          <button
            type="button"
            class="hover:text-gray-900 dark:hover:text-white cursor-pointer flex items-center"
            title="Hapus filter perkerasan"
            @click="emit('update:perkerasanFilter', null)"
          >
            <UIcon name="i-lucide-x" class="size-3" />
          </button>
        </span>

        <!-- Reset All Button -->
        <button
          type="button"
          class="ml-auto text-[11px] text-gray-500 hover:text-emerald-600 dark:text-gray-400 dark:hover:text-emerald-400 font-medium cursor-pointer shrink-0 underline decoration-dotted"
          @click="emit('resetFilters')"
        >
          Reset Semua
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="tableStatus === 'pending'" class="p-3 space-y-2 flex-1">
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

      <!-- UTable -->
      <div v-else class="flex-1 overflow-auto">
        <UTable
          :data="tableRows"
          :columns="tableColumns"
          @select="(_e, row) => emit('rowClick', row.original)"
          :ui="{
            root: 'w-full text-xs font-mono',
            thead: 'sticky top-0 bg-gray-50 dark:bg-[#0b0f19] border-b border-gray-200 dark:border-gray-800 z-10',
            th: 'px-3 py-2 text-left text-[11px] font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap',
            tbody: 'divide-y divide-gray-100 dark:divide-gray-800/60',
            tr: 'hover:bg-emerald-50/40 dark:hover:bg-emerald-950/20 transition-colors cursor-pointer',
            td: 'px-3 py-1.5 align-middle',
          }"
        >
          <template #kode_ruas-cell="{ row }">
            <span class="text-gray-500">{{ row.original.kode_ruas ?? '-' }}</span>
          </template>

          <template #nama_ruas-cell="{ row }">
            <div
              class="max-w-[400px] truncate font-sans font-medium flex items-center gap-1.5"
              :class="selectedFeatureId === String(row.original.id) ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-900 dark:text-gray-100'"
              @click="emit('rowClick', row.original)"
            >
              <UIcon
                v-if="selectedFeatureId === String(row.original.id)"
                name="i-lucide-map-pin"
                class="size-3 text-emerald-500 shrink-0"
              />
              <span class="truncate">{{ row.original.nama_ruas || '-' }}</span>
            </div>
          </template>

          <template #desa-cell="{ row }">
            <span class="text-gray-600 dark:text-gray-400 max-w-[120px] truncate block" @click="emit('rowClick', row.original)">
              {{ row.original.desa || '-' }}
            </span>
          </template>

          <template #kecamatan-cell="{ row }">
            <span class="text-gray-600 dark:text-gray-400 max-w-[120px] truncate block" @click="emit('rowClick', row.original)">
              {{ row.original.kecamatan || '-' }}
            </span>
          </template>

          <template #panjang-cell="{ row }">
            <span class="font-mono text-gray-800 dark:text-gray-200 font-medium" @click="emit('rowClick', row.original)">
              {{ (row.original.panjang_meter != null && row.original.panjang_meter !== '') ? `${Number(row.original.panjang_meter).toLocaleString('id-ID', { maximumFractionDigits: 1 })} m` : (row.original.panjang != null && row.original.panjang !== '' ? `${Number(row.original.panjang).toLocaleString('id-ID', { maximumFractionDigits: 1 })} m` : '-') }}
            </span>
          </template>

          <template #perkerasan-cell="{ row }">
            <span class="text-gray-600 dark:text-gray-400" @click="emit('rowClick', row.original)">
              {{ row.original.perkerasan || '-' }}
            </span>
          </template>

          <template #kondisi-cell="{ row }">
            <div @click="emit('rowClick', row.original)">
              <UBadge
                :label="row.original.kondisi || '-'"
                :color="getKondisiBadgeColor(row.original.kondisi)"
                variant="subtle"
                size="xs"
              />
            </div>
          </template>

          <template #status_eksisting-cell="{ row }">
            <span class="text-gray-600 dark:text-gray-400 max-w-[120px] truncate block" @click="emit('rowClick', row.original)">
              {{ row.original.status_eksisting || '-' }}
            </span>
          </template>

          <template #actions-cell="{ row }">
            <div class="flex items-center justify-center gap-0.5" @click.stop>
              <UButton
                icon="i-lucide-locate-fixed"
                size="xs"
                color="primary"
                variant="ghost"
                title="Zoom ke ruas di peta"
                class="cursor-pointer text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                @click="emit('rowClick', row.original)"
              />
              <UButton
                icon="i-lucide-pencil"
                size="xs"
                color="neutral"
                variant="ghost"
                title="Edit Ruas"
                class="cursor-pointer"
                @click="emit('editRow', row.original)"
              />
              <UButton
                icon="i-lucide-scissors"
                size="xs"
                color="neutral"
                variant="ghost"
                title="Split / Pecah Ruas"
                class="cursor-pointer text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                @click="emit('splitRow', row.original)"
              />
              <UButton
                icon="i-lucide-trash-2"
                size="xs"
                color="error"
                variant="ghost"
                title="Hapus Ruas"
                class="cursor-pointer"
                @click="emit('deleteRow', row.original)"
              />
            </div>
          </template>
        </UTable>
      </div>

      <!-- Pagination Footer (always visible when table has data) -->
      <div
        v-if="tableTotal > 0"
        class="flex items-center justify-between px-3 py-1.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-[#0d1117] text-[11px] shrink-0 gap-3"
      >
        <!-- Left: record range info -->
        <span class="text-gray-500 dark:text-gray-400 font-mono shrink-0 tabular-nums">
          {{ pageRangeText }}
        </span>

        <!-- Center/Right: pagination buttons -->
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
    </div>
    </Transition>
  </div>
</template>

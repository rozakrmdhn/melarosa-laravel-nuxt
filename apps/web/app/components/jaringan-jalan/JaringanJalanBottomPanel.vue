<script setup lang="ts">
import { computed } from 'vue';
import {
  formatAngka,
  formatPanjangKm,
  getHierarkiJalanLabel,
  getStatusAnalisaMeta,
} from '~/utils/jaringan-jalan-format';
import type {
  CakupanDesaItem,
  CakupanDesaSummary,
  JalanKabupatenItem,
  JaringanConsoleEntry,
  JaringanMapSelection,
  JaringanWorkspaceTab,
  RtrwAnalisaItem,
  RtrwAnalisaSummary,
  StatusAnalisaRtrw,
} from '~/types/jaringan-jalan';

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface Props {
  collapsed: boolean;
  isMobileDrawer?: boolean;
  hideConsoleTab?: boolean;
  onlyTabs?: JaringanWorkspaceTab[];
  activeTab: JaringanWorkspaceTab;
  perPage: number;
  searchQuery: string;
  canManage: boolean;
  hasActiveFilters: boolean;
  activeFilterCount: number;
  statusFilter: '' | StatusAnalisaRtrw;
  ordeFilter: string;
  selection: JaringanMapSelection | null;
  summaryData: RtrwAnalisaSummary | null;
  analisaList: RtrwAnalisaItem[];
  analisaMeta: PaginationMeta;
  loadingAnalisa: boolean;
  selectedRtrwIds: number[];
  adoptingRtrw: boolean;
  cakupanList: CakupanDesaItem[];
  cakupanMeta: PaginationMeta;
  cakupanSummary: CakupanDesaSummary;
  cakupanStatusFilter: 'belum_terdata' | 'sudah_terdata' | 'semua';
  loadingCakupan: boolean;
  masterList: any[];
  masterMeta: PaginationMeta;
  loadingMaster: boolean;
  kabupatenList: JalanKabupatenItem[];
  kabupatenMeta: PaginationMeta;
  loadingKabupaten: boolean;
  consoleLogs: JaringanConsoleEntry[];
}

const props = withDefaults(defineProps<Props>(), {
  isMobileDrawer: false,
  hideConsoleTab: false,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'update:activeTab', val: JaringanWorkspaceTab): void;
  (e: 'update:perPage', val: number): void;
  (e: 'update:searchQuery', val: string): void;
  (e: 'update:selectedRtrwIds', val: number[]): void;
  (e: 'update:cakupanStatusFilter', val: 'belum_terdata' | 'sudah_terdata' | 'semua'): void;
  (e: 'change-page', payload: { tab: JaringanWorkspaceTab; page: number }): void;
  (e: 'inspect-rtrw', item: RtrwAnalisaItem): void;
  (e: 'zoom-rtrw', item: RtrwAnalisaItem): void;
  (e: 'bulk-adopt'): void;
  (e: 'adopt-desa', desa: CakupanDesaItem): void;
  (e: 'filter-desa', desa: CakupanDesaItem): void;
  (e: 'inspect-poros', feature: any): void;
  (e: 'inspect-kabupaten', item: JalanKabupatenItem): void;
  (e: 'filter-orde', orde02: string): void;
  (e: 'filter-wilayah', id: number): void;
  (e: 'toggle-status', status: '' | StatusAnalisaRtrw): void;
  (e: 'reset-filters'): void;
  (e: 'open-filter-modal'): void;
  (e: 'clear-console'): void;
}>();

const tabs = computed(() => {
  const base: Array<{ key: JaringanWorkspaceTab; label: string; count?: number }> = [
    { key: 'analisa', label: 'Analisa RTRW', count: props.analisaMeta.total },
    { key: 'cakupan', label: 'Cakupan Desa', count: props.cakupanSummary.desa_belum_terdata },
    { key: 'master', label: 'Master Poros', count: props.masterMeta.total },
    { key: 'kabupaten', label: 'Jalan Kabupaten', count: props.kabupatenMeta.total },
    { key: 'breakdown', label: 'Rekap Orde & Wilayah' },
  ];
  if (!props.hideConsoleTab) {
    base.push({ key: 'console', label: 'Konsol Analis', count: props.consoleLogs.length });
  }
  if (props.onlyTabs && props.onlyTabs.length > 0) {
    return base.filter((item) => props.onlyTabs!.includes(item.key));
  }
  return base;
});

const currentMeta = computed<PaginationMeta>(() => {
  switch (props.activeTab) {
    case 'cakupan':
      return props.cakupanMeta;
    case 'master':
      return props.masterMeta;
    case 'kabupaten':
      return props.kabupatenMeta;
    default:
      return props.analisaMeta;
  }
});

const showSearchAndPagination = computed(() =>
  ['analisa', 'cakupan', 'master', 'kabupaten'].includes(props.activeTab),
);

const allAdoptableSelected = computed(() => {
  const adoptable = props.analisaList.filter((item) => !item.already_adopted);
  return adoptable.length > 0 && adoptable.every((item) => props.selectedRtrwIds.includes(item.id));
});

function selectTab(key: JaringanWorkspaceTab) {
  emit('update:activeTab', key);
  if (props.collapsed && !props.isMobileDrawer) {
    emit('update:collapsed', false);
  }
}

function toggleSelectAllAdoptable() {
  const adoptableIds = props.analisaList.filter((item) => !item.already_adopted).map((item) => item.id);
  if (adoptableIds.length === 0) return;
  if (allAdoptableSelected.value) {
    emit(
      'update:selectedRtrwIds',
      props.selectedRtrwIds.filter((id) => !adoptableIds.includes(id)),
    );
  } else {
    const merged = new Set([...props.selectedRtrwIds, ...adoptableIds]);
    emit('update:selectedRtrwIds', Array.from(merged));
  }
}

function toggleSingleRtrw(id: number) {
  if (props.selectedRtrwIds.includes(id)) {
    emit(
      'update:selectedRtrwIds',
      props.selectedRtrwIds.filter((item) => item !== id),
    );
  } else {
    emit('update:selectedRtrwIds', [...props.selectedRtrwIds, id]);
  }
}

function isRowSelected(kind: 'rtrw' | 'poros' | 'kabupaten', id: string | number): boolean {
  return props.selection?.kind === kind && String(props.selection.id) === String(id);
}
</script>

<template>
  <section
    class="flex flex-col h-full w-full min-h-0 overflow-hidden bg-white dark:bg-[#0b0f19]"
    aria-label="Tabel atribut dan rekap analisa jaringan jalan"
  >
    <!-- Top Sticky Bar (Desktop h-10 collapsible bar / Mobile scrollable tab strip) -->
    <div
      :class="[
        'flex items-center border-b border-gray-200 dark:border-gray-800 shrink-0 px-2 gap-1.5',
        isMobileDrawer ? 'overflow-x-auto py-1.5' : 'h-10',
      ]"
    >
      <div class="flex items-center gap-1 overflow-x-auto no-scrollbar shrink-0" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.key"
          :class="[
            'inline-flex items-center gap-1.5 rounded-md px-2.5 text-xs font-medium whitespace-nowrap transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 shrink-0',
            isMobileDrawer ? 'min-h-[44px]' : 'h-7',
            activeTab === tab.key
              ? 'bg-[#176B4D] text-white shadow-2xs dark:bg-emerald-700'
              : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800/70',
          ]"
          @click="selectTab(tab.key)"
        >
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.count !== undefined"
            :class="[
              'rounded px-1.5 py-0.5 text-[11px] font-semibold tabular-nums',
              activeTab === tab.key
                ? 'bg-white/20 text-white'
                : 'bg-gray-200/80 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            ]"
          >
            {{ formatAngka(tab.count, 0) }}
          </span>
        </button>
      </div>

      <!-- Spacer Klik untuk Buka/Tutup di Desktop -->
      <button
        v-if="!isMobileDrawer"
        type="button"
        :aria-label="collapsed ? 'Buka laci tabel atribut' : 'Ciutkan laci tabel atribut'"
        class="flex-1 h-full min-w-[24px] cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded"
        @click="emit('update:collapsed', !collapsed)"
      />

      <!-- Desktop Bar Right Controls -->
      <div v-if="!isMobileDrawer" class="flex items-center gap-1.5 shrink-0">
        <div v-if="!collapsed && showSearchAndPagination" class="relative w-44">
          <UIcon
            name="i-lucide-search"
            class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 size-3.5 text-gray-600 dark:text-gray-400"
          />
          <input
            :value="searchQuery"
            type="search"
            aria-label="Cari ruas di tabel"
            placeholder="Cari ruas atau desa..."
            class="h-7 w-full rounded-md border border-gray-300 bg-gray-50 pl-7 pr-2 text-xs text-gray-900 focus-visible:outline-2 focus-visible:outline-blue-600 dark:border-gray-700 dark:bg-[#0e1424] dark:text-white"
            @input="(e) => emit('update:searchQuery', (e.target as HTMLInputElement).value)"
          />
        </div>

        <button
          v-if="hasActiveFilters"
          type="button"
          class="h-7 rounded-md border border-gray-300 bg-white px-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="emit('reset-filters')"
        >
          Reset ({{ activeFilterCount }})
        </button>

        <UTooltip :text="collapsed ? 'Bentangkan tabel atribut' : 'Ciutkan tabel atribut'">
          <button
            type="button"
            :aria-expanded="!collapsed"
            :aria-label="collapsed ? 'Bentangkan tabel atribut' : 'Ciutkan tabel atribut'"
            class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="emit('update:collapsed', !collapsed)"
          >
            <UIcon
              name="i-lucide-chevron-up"
              :class="['size-4 transition-transform duration-200', collapsed ? '' : 'rotate-180']"
            />
          </button>
        </UTooltip>
      </div>
    </div>

    <!-- Drawer Body -->
    <Transition name="drawer-fade">
      <div v-if="isMobileDrawer || !collapsed" class="flex-1 min-h-0 flex flex-col overflow-hidden">
      <!-- Filter & Batch Strip -->
      <div
        v-if="activeTab === 'analisa'"
        class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200/80 bg-gray-50/70 px-3 py-1.5 dark:border-gray-800 dark:bg-[#0e1424]"
      >
        <div class="flex flex-wrap items-center gap-1.5">
          <button
            type="button"
            :class="[
              'rounded-md border px-2 text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-7',
              !statusFilter
                ? 'border-gray-900 bg-gray-900 text-white dark:border-gray-200 dark:bg-gray-100 dark:text-gray-900'
                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-300',
            ]"
            @click="emit('toggle-status', '')"
          >
            Semua
          </button>
          <button
            type="button"
            :class="[
              'rounded-md border px-2 text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-7',
              statusFilter === 'belum_terdata'
                ? 'border-amber-700 bg-amber-700 text-white'
                : 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
            ]"
            @click="emit('toggle-status', statusFilter === 'belum_terdata' ? '' : 'belum_terdata')"
          >
            Belum Terdata ({{ formatAngka(summaryData?.totals?.statuses?.belum_terdata?.count ?? 0, 0) }})
          </button>
          <button
            type="button"
            :class="[
              'rounded-md border px-2 text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-7',
              statusFilter === 'irisan_sebagian'
                ? 'border-violet-700 bg-violet-700 text-white'
                : 'border-violet-300 bg-violet-50 text-violet-900 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-200',
            ]"
            @click="emit('toggle-status', statusFilter === 'irisan_sebagian' ? '' : 'irisan_sebagian')"
          >
            Irisan Sebagian ({{ formatAngka(summaryData?.totals?.statuses?.irisan_sebagian?.count ?? 0, 0) }})
          </button>
          <span
            v-if="ordeFilter"
            class="inline-flex items-center gap-1 rounded-md border border-violet-300 bg-violet-50 px-2 py-1 text-xs font-medium text-violet-900 dark:border-violet-800 dark:bg-violet-950/70 dark:text-violet-200"
          >
            Orde: {{ ordeFilter }}
            <button
              type="button"
              aria-label="Hapus filter orde"
              class="cursor-pointer hover:text-rose-700 focus-visible:outline-2 focus-visible:outline-blue-600 rounded"
              @click="emit('filter-orde', '')"
            >
              <UIcon name="i-lucide-x" class="size-3.5" />
            </button>
          </span>
        </div>

        <div class="flex items-center gap-2">
          <div v-if="isMobileDrawer" class="w-44">
            <input
              :value="searchQuery"
              type="search"
              aria-label="Cari segmen RTRW"
              placeholder="Cari ruas..."
              class="min-h-[44px] w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
              @input="(e) => emit('update:searchQuery', (e.target as HTMLInputElement).value)"
            />
          </div>

          <button
            v-if="canManage && selectedRtrwIds.length > 0"
            type="button"
            :disabled="adoptingRtrw"
            :class="[
              'inline-flex items-center gap-1.5 rounded-md bg-[#176B4D] px-3 text-xs font-semibold text-white hover:bg-[#12543c] disabled:opacity-60 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-7',
            ]"
            @click="emit('bulk-adopt')"
          >
            <UIcon v-if="adoptingRtrw" name="i-lucide-loader-2" class="size-3.5 animate-spin" />
            <span>Adopsi {{ selectedRtrwIds.length }} Terpilih</span>
          </button>
        </div>
      </div>

      <!-- Search Bar on Mobile for Other Tabs -->
      <div
        v-else-if="isMobileDrawer && showSearchAndPagination"
        class="border-b border-gray-200/80 bg-gray-50/70 px-3 py-2 dark:border-gray-800 dark:bg-[#0e1424]"
      >
        <input
          :value="searchQuery"
          type="search"
          aria-label="Cari di daftar"
          placeholder="Cari nama ruas, nomor ruas, atau desa..."
          class="min-h-[44px] w-full rounded-md border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
          @input="(e) => emit('update:searchQuery', (e.target as HTMLInputElement).value)"
        />
      </div>

      <!-- TAB 1: ANALISA SPASIAL RTRW -->
      <div v-if="activeTab === 'analisa'" class="flex-1 min-h-0 overflow-auto">
        <h2 class="sr-only">Daftar Segmen Analisa Spasial RTRW</h2>
        <div v-if="loadingAnalisa" class="p-4 text-xs text-gray-600 dark:text-gray-400">
          Memuat daftar analisa spasial segmen...
        </div>

        <div
          v-else-if="analisaList.length === 0"
          class="p-6 text-center space-y-1.5 text-xs text-gray-600 dark:text-gray-400"
        >
          <p class="font-semibold text-gray-900 dark:text-white">
            Tidak ada segmen analisa spasial yang sesuai filter.
          </p>
          <p>
            Jika sumber Rencana Jalan RTRW belum terisi atau filter terlalu ketat, reset filter atau periksa tab Master Poros dan Jalan Kabupaten.
          </p>
        </div>

        <!-- Mobile Card List -->
        <div v-else-if="isMobileDrawer" class="p-3 space-y-2.5">
          <div
            v-for="item in analisaList"
            :key="item.id"
            :class="[
              'rounded-lg border p-3 space-y-2 transition-colors',
              isRowSelected('rtrw', item.id)
                ? 'border-blue-500 bg-blue-50/60 dark:border-blue-400 dark:bg-blue-950/30'
                : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-[#0e1424]',
            ]"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-start gap-2.5 min-w-0">
                <label
                  v-if="canManage && !item.already_adopted"
                  class="min-h-[44px] min-w-[44px] -ml-2 -mt-2 flex items-center justify-center cursor-pointer shrink-0"
                  :aria-label="`Pilih segmen ${item.display_name} untuk adopsi massal`"
                >
                  <input
                    type="checkbox"
                    :checked="selectedRtrwIds.includes(item.id)"
                    class="size-4 accent-[#176B4D] rounded"
                    @change="toggleSingleRtrw(item.id)"
                  />
                </label>
                <div class="min-w-0">
                  <span
                    :class="[
                      'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                      getStatusAnalisaMeta(item.status_analisa).badgeClass,
                    ]"
                  >
                    {{ getStatusAnalisaMeta(item.status_analisa).shortLabel }}
                  </span>
                  <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                    {{ item.display_name }}
                  </p>
                  <p class="text-xs text-gray-600 dark:text-gray-400">
                    {{ item.orde02 || 'RTRW' }} · {{ item.nama_desa ? `Desa ${item.nama_desa}` : 'Luar Desa' }}, Kec. {{ item.nama_kecamatan || '-' }}
                  </p>
                </div>
              </div>
              <span class="text-xs font-bold tabular-nums text-gray-900 dark:text-white shrink-0">
                {{ formatAngka(item.panjang_m, 0) }} m
              </span>
            </div>

            <div class="flex items-center gap-2 pt-1">
              <button
                type="button"
                class="min-h-[44px] flex-1 rounded-md bg-blue-700 px-3 text-xs font-semibold text-white hover:bg-blue-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                @click="emit('inspect-rtrw', item)"
              >
                Periksa Detail & Adopsi
              </button>
              <button
                v-if="item.bbox"
                type="button"
                aria-label="Zoom peta ke segmen ini"
                class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                @click="emit('zoom-rtrw', item)"
              >
                <UIcon name="i-lucide-locate-fixed" class="size-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop Attribute Table -->
        <table v-else class="w-full text-left text-xs border-collapse">
          <thead class="sticky top-0 z-10 bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-[#0e1424] dark:text-gray-300 border-b border-gray-200 dark:border-gray-800">
            <tr>
              <th v-if="canManage" class="w-9 px-2.5 py-2 text-center">
                <input
                  type="checkbox"
                  aria-label="Pilih semua segmen pada halaman ini yang belum diadopsi"
                  :checked="allAdoptableSelected"
                  class="size-3.5 accent-[#176B4D] cursor-pointer"
                  @change="toggleSelectAllAdoptable"
                />
              </th>
              <th class="px-3 py-2">Nama Segmen & Orde RTRW</th>
              <th class="px-3 py-2">Wilayah (Desa / Kec.)</th>
              <th class="px-3 py-2 text-right">Panjang</th>
              <th class="px-3 py-2">Irisan Spasial</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/80">
            <tr
              v-for="item in analisaList"
              :key="item.id"
              :class="[
                'transition-colors cursor-pointer',
                isRowSelected('rtrw', item.id)
                  ? 'bg-blue-50/90 dark:bg-blue-950/35'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-900/50',
              ]"
              @click="emit('inspect-rtrw', item)"
            >
              <td v-if="canManage" class="px-2.5 py-2 text-center" @click.stop>
                <input
                  v-if="!item.already_adopted"
                  type="checkbox"
                  :aria-label="`Pilih segmen ${item.display_name}`"
                  :checked="selectedRtrwIds.includes(item.id)"
                  class="size-3.5 accent-[#176B4D] cursor-pointer"
                  @change="toggleSingleRtrw(item.id)"
                />
                <UIcon v-else name="i-lucide-check-circle-2" class="size-3.5 text-emerald-600 mx-auto" title="Sudah diadopsi" />
              </td>
              <td class="px-3 py-2">
                <p class="font-semibold text-gray-900 dark:text-white">{{ item.display_name }}</p>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">
                  {{ item.orde02 || '-' }} <template v-if="item.orde04">· {{ item.orde04 }}</template>
                </p>
              </td>
              <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                <p>{{ item.nama_desa ? `Desa ${item.nama_desa}` : 'Luar Desa' }}</p>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">{{ item.nama_kecamatan || '-' }}</p>
              </td>
              <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900 dark:text-white whitespace-nowrap">
                {{ formatAngka(item.panjang_m, 0) }} m
              </td>
              <td class="px-3 py-2 min-w-[140px]">
                <div class="flex items-center gap-2 tabular-nums">
                  <div class="h-1.5 flex-1 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                    <div
                      class="h-full bg-[#176B4D]"
                      :style="{ width: `${Math.min(100, item.combined_overlap_pct)}%` }"
                    />
                  </div>
                  <span class="text-[11px] font-semibold text-gray-800 dark:text-gray-200 w-10 text-right">
                    {{ formatAngka(item.combined_overlap_pct, 0) }}%
                  </span>
                </div>
                <p class="mt-0.5 text-[11px] text-gray-600 dark:text-gray-400 truncate max-w-[200px]">
                  {{ item.matched_poros_nama || item.matched_kab_nama || 'Belum ada padanan' }}
                </p>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span
                  :class="[
                    'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                    getStatusAnalisaMeta(item.status_analisa).badgeClass,
                  ]"
                >
                  {{ getStatusAnalisaMeta(item.status_analisa).shortLabel }}
                </span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap" @click.stop>
                <div class="inline-flex items-center gap-1">
                  <button
                    v-if="item.bbox"
                    type="button"
                    aria-label="Zoom ke lokasi segmen"
                    title="Zoom ke peta"
                    class="size-7 inline-flex items-center justify-center rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="emit('zoom-rtrw', item)"
                  >
                    <UIcon name="i-lucide-locate-fixed" class="size-3.5" />
                  </button>
                  <button
                    type="button"
                    class="h-7 rounded bg-blue-50 px-2 text-xs font-semibold text-blue-800 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="emit('inspect-rtrw', item)"
                  >
                    Periksa
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- TAB 2: CAKUPAN DESA -->
      <div v-else-if="activeTab === 'cakupan'" class="flex-1 min-h-0 flex flex-col overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200/80 bg-gray-50/70 px-3 py-1.5 dark:border-gray-800 dark:bg-[#0e1424]">
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="opt in [
                { id: 'belum_terdata' as const, label: `Belum Terdata (${cakupanSummary.desa_belum_terdata})` },
                { id: 'sudah_terdata' as const, label: `Sudah Terdata (${cakupanSummary.desa_terdata})` },
                { id: 'semua' as const, label: `Semua Desa (${cakupanSummary.total_desa})` },
              ]"
              :key="opt.id"
              type="button"
              :class="[
                'rounded-md border px-2.5 text-xs font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : 'h-7',
                cakupanStatusFilter === opt.id
                  ? 'border-[#176B4D] bg-[#176B4D] text-white'
                  : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200',
              ]"
              @click="emit('update:cakupanStatusFilter', opt.id)"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <div class="flex-1 min-h-0 overflow-auto">
          <div v-if="loadingCakupan" class="p-4 text-xs text-gray-600 dark:text-gray-400">
            Memuat cakupan data jalan per desa...
          </div>

          <div v-else-if="cakupanList.length === 0" class="p-6 text-center text-xs text-gray-600 dark:text-gray-400">
            Tidak ada desa dalam kategori filter ini.
          </div>

          <div v-else-if="isMobileDrawer" class="p-3 space-y-2.5">
            <div
              v-for="d in cakupanList"
              :key="d.id"
              class="rounded-lg border border-gray-200 bg-white p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">Desa {{ d.nama_desa }}</p>
                  <p class="text-xs text-gray-600 dark:text-gray-400">Kec. {{ d.nama_kecamatan || '-' }}</p>
                </div>
                <span
                  :class="[
                    'rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                    d.status_cakupan === 'sudah_terdata'
                      ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200'
                      : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
                  ]"
                >
                  {{ d.status_cakupan === 'sudah_terdata' ? 'Terdata' : 'Belum Terdata' }}
                </span>
              </div>

              <div class="grid grid-cols-2 gap-2 text-xs tabular-nums text-gray-700 dark:text-gray-300">
                <span>Poros Desa: {{ formatAngka(d.poros_count, 0) }} ruas</span>
                <span>Segmen RTRW: {{ formatAngka(d.rtrw_total_count, 0) }}</span>
              </div>

              <div class="flex items-center gap-2 pt-1">
                <button
                  type="button"
                  class="min-h-[44px] flex-1 rounded-md border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-800 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="emit('filter-desa', d)"
                >
                  Fokus Desa
                </button>
                <button
                  v-if="canManage && d.rtrw_total_count > 0"
                  type="button"
                  :disabled="adoptingRtrw"
                  class="min-h-[44px] flex-1 rounded-md bg-[#176B4D] px-3 text-xs font-semibold text-white hover:bg-[#12543c] disabled:opacity-60 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="emit('adopt-desa', d)"
                >
                  Adopsi RTRW
                </button>
              </div>
            </div>
          </div>

          <table v-else class="w-full text-left text-xs border-collapse">
            <thead class="sticky top-0 z-10 bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-[#0e1424] dark:text-gray-300 border-b border-gray-200 dark:border-gray-800">
              <tr>
                <th class="px-3 py-2">Desa</th>
                <th class="px-3 py-2">Kecamatan</th>
                <th class="px-3 py-2 text-right">Ruas Poros Desa</th>
                <th class="px-3 py-2 text-right">Total Panjang Poros</th>
                <th class="px-3 py-2 text-right">Segmen RTRW</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/80">
              <tr
                v-for="d in cakupanList"
                :key="d.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-900/50"
              >
                <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ d.nama_desa }}</td>
                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ d.nama_kecamatan || '-' }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatAngka(d.poros_count, 0) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatPanjangKm(d.poros_panjang_m) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatAngka(d.rtrw_total_count, 0) }}</td>
                <td class="px-3 py-2">
                  <span
                    :class="[
                      'rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                      d.status_cakupan === 'sudah_terdata'
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200'
                        : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
                    ]"
                  >
                    {{ d.status_cakupan === 'sudah_terdata' ? 'Terdata' : 'Belum Terdata' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right space-x-1.5 whitespace-nowrap">
                  <button
                    type="button"
                    class="h-7 rounded border border-gray-300 bg-white px-2 text-xs font-medium text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0e1424] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="emit('filter-desa', d)"
                  >
                    Fokus
                  </button>
                  <button
                    v-if="canManage && d.rtrw_total_count > 0"
                    type="button"
                    :disabled="adoptingRtrw"
                    class="h-7 rounded bg-[#176B4D] px-2 text-xs font-semibold text-white hover:bg-[#12543c] disabled:opacity-60 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="emit('adopt-desa', d)"
                  >
                    Adopsi Desa
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: MASTER JALAN POROS -->
      <div v-else-if="activeTab === 'master'" class="flex-1 min-h-0 overflow-auto">
        <div v-if="loadingMaster" class="p-4 text-xs text-gray-600 dark:text-gray-400">
          Memuat daftar Master Jalan Poros Desa...
        </div>
        <div v-else-if="masterList.length === 0" class="p-6 text-center text-xs text-gray-600 dark:text-gray-400">
          Tidak ada ruas Master Jalan Poros Desa pada cakupan filter saat ini.
        </div>
        <div v-else-if="isMobileDrawer" class="p-3 space-y-2.5">
          <div
            v-for="feat in masterList"
            :key="feat.id"
            class="rounded-lg border border-gray-200 bg-white p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ feat.properties.nama_ruas }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                  {{ getHierarkiJalanLabel(feat.properties.hierarki_jalan) }} · Desa {{ feat.properties.nama_desa || '-' }}
                </p>
              </div>
              <span class="text-xs font-semibold tabular-nums">{{ formatAngka(feat.properties.panjang_m, 0) }} m</span>
            </div>
            <button
              type="button"
              class="min-h-[44px] w-full rounded-md bg-blue-700 px-3 text-xs font-semibold text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="emit('inspect-poros', feat)"
            >
              Periksa & Sunting Ruas
            </button>
          </div>
        </div>
        <table v-else class="w-full text-left text-xs border-collapse">
          <thead class="sticky top-0 z-10 bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-[#0e1424] dark:text-gray-300 border-b border-gray-200 dark:border-gray-800">
            <tr>
              <th class="px-3 py-2">Kode & Nama Ruas</th>
              <th class="px-3 py-2">Hierarki</th>
              <th class="px-3 py-2">Desa / Kecamatan</th>
              <th class="px-3 py-2 text-right">Panjang</th>
              <th class="px-3 py-2">Sumber Data</th>
              <th class="px-3 py-2 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/80">
            <tr
              v-for="feat in masterList"
              :key="feat.id"
              :class="[
                'cursor-pointer transition-colors',
                isRowSelected('poros', feat.id)
                  ? 'bg-blue-50/90 dark:bg-blue-950/35'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-900/50',
              ]"
              @click="emit('inspect-poros', feat)"
            >
              <td class="px-3 py-2">
                <p class="font-semibold text-gray-900 dark:text-white">{{ feat.properties.nama_ruas }}</p>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">Kode {{ feat.properties.kode_ruas || '-' }}</p>
              </td>
              <td class="px-3 py-2">{{ getHierarkiJalanLabel(feat.properties.hierarki_jalan) }}</td>
              <td class="px-3 py-2">
                <p>{{ feat.properties.nama_desa ? `Desa ${feat.properties.nama_desa}` : '-' }}</p>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">{{ feat.properties.nama_kecamatan || '-' }}</p>
              </td>
              <td class="px-3 py-2 text-right tabular-nums font-medium">{{ formatAngka(feat.properties.panjang_m, 0) }} m</td>
              <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ feat.properties.sumber_data }}</td>
              <td class="px-3 py-2 text-right" @click.stop>
                <button
                  type="button"
                  class="h-7 rounded bg-blue-50 px-2.5 text-xs font-semibold text-blue-800 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="emit('inspect-poros', feat)"
                >
                  Periksa
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- TAB 4: JALAN KABUPATEN -->
      <div v-else-if="activeTab === 'kabupaten'" class="flex-1 min-h-0 overflow-auto">
        <div v-if="loadingKabupaten" class="p-4 text-xs text-gray-600 dark:text-gray-400">
          Memuat daftar Jalan Kabupaten Luarkota...
        </div>
        <div v-else-if="kabupatenList.length === 0" class="p-6 text-center text-xs text-gray-600 dark:text-gray-400">
          Tidak ada ruas Jalan Kabupaten pada cakupan filter ini.
        </div>
        <div v-else-if="isMobileDrawer" class="p-3 space-y-2.5">
          <div
            v-for="kab in kabupatenList"
            :key="kab.id"
            class="rounded-lg border border-gray-200 bg-white p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ kab.nama_ruas }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                  No. {{ kab.no_ruas || '-' }} · {{ kab.klas_ruas || 'Kabupaten' }}
                </p>
              </div>
              <span class="text-xs font-semibold tabular-nums">{{ formatAngka(kab.panjang_m, 0) }} m</span>
            </div>
            <button
              type="button"
              class="min-h-[44px] w-full rounded-md bg-sky-700 px-3 text-xs font-semibold text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="emit('inspect-kabupaten', kab)"
            >
              Zoom & Lihat Detail Ruas
            </button>
          </div>
        </div>
        <table v-else class="w-full text-left text-xs border-collapse">
          <thead class="sticky top-0 z-10 bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-[#0e1424] dark:text-gray-300 border-b border-gray-200 dark:border-gray-800">
            <tr>
              <th class="px-3 py-2">No. & Nama Ruas</th>
              <th class="px-3 py-2">Klasifikasi</th>
              <th class="px-3 py-2">Kecamatan</th>
              <th class="px-3 py-2 text-right">Panjang</th>
              <th class="px-3 py-2 text-right">Irisan RTRW</th>
              <th class="px-3 py-2 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/80">
            <tr
              v-for="kab in kabupatenList"
              :key="kab.id"
              :class="[
                'cursor-pointer transition-colors',
                isRowSelected('kabupaten', kab.id)
                  ? 'bg-blue-50/90 dark:bg-blue-950/35'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-900/50',
              ]"
              @click="emit('inspect-kabupaten', kab)"
            >
              <td class="px-3 py-2">
                <p class="font-semibold text-gray-900 dark:text-white">{{ kab.nama_ruas }}</p>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">No. Ruas {{ kab.no_ruas || '-' }}</p>
              </td>
              <td class="px-3 py-2">{{ kab.klas_ruas || '-' }}</td>
              <td class="px-3 py-2">{{ kab.nama_kecamatan || '-' }}</td>
              <td class="px-3 py-2 text-right tabular-nums font-medium">{{ formatAngka(kab.panjang_m, 0) }} m</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatAngka(kab.rtrw_match_count, 0) }}</td>
              <td class="px-3 py-2 text-right" @click.stop>
                <button
                  type="button"
                  class="h-7 rounded bg-sky-50 px-2.5 text-xs font-semibold text-sky-900 hover:bg-sky-100 dark:bg-sky-950/60 dark:text-sky-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="emit('inspect-kabupaten', kab)"
                >
                  Periksa
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- TAB 5: REKAP ORDE & WILAYAH -->
      <div v-else-if="activeTab === 'breakdown'" class="flex-1 min-h-0 overflow-y-auto p-3">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="rounded-lg border border-gray-200 bg-white overflow-hidden dark:border-gray-800 dark:bg-[#0e1424]">
            <div class="border-b border-gray-200 px-3 py-2 dark:border-gray-800">
              <h2 class="text-xs font-semibold text-gray-900 dark:text-white">
                Matriks Cakupan per Orde RTRW
              </h2>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 text-[11px] font-semibold text-gray-700 dark:bg-[#0b0f19] dark:text-gray-300">
                  <tr>
                    <th class="px-3 py-2">Orde Jalan RTRW</th>
                    <th class="px-3 py-2 text-right">Segmen</th>
                    <th class="px-3 py-2 text-right">Cakupan</th>
                    <th class="px-3 py-2 text-right">Gap</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/80 tabular-nums">
                  <tr
                    v-for="row in summaryData?.by_orde || []"
                    :key="row.orde02"
                    class="hover:bg-gray-50 dark:hover:bg-gray-900/50 cursor-pointer"
                    @click="emit('filter-orde', row.orde02)"
                  >
                    <td class="px-3 py-2 font-medium text-blue-700 dark:text-blue-400">{{ row.orde02 }}</td>
                    <td class="px-3 py-2 text-right">{{ formatAngka(row.total_segmen, 0) }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ formatAngka(row.coverage_pct, 1) }}%</td>
                    <td class="px-3 py-2 text-right text-amber-800 dark:text-amber-300">{{ formatAngka(row.belum_terdata, 0) }}</td>
                  </tr>
                  <tr v-if="!summaryData?.by_orde?.length">
                    <td colspan="4" class="px-3 py-4 text-center text-gray-600 dark:text-gray-400">
                      Belum ada data rekap per orde.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white overflow-hidden dark:border-gray-800 dark:bg-[#0e1424]">
            <div class="border-b border-gray-200 px-3 py-2 dark:border-gray-800">
              <h2 class="text-xs font-semibold text-gray-900 dark:text-white">
                Peringkat Wilayah ({{ summaryData?.wilayah_level === 'desa' ? 'Per Desa' : 'Per Kecamatan' }})
              </h2>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 text-[11px] font-semibold text-gray-700 dark:bg-[#0b0f19] dark:text-gray-300">
                  <tr>
                    <th class="px-3 py-2">Wilayah</th>
                    <th class="px-3 py-2 text-right">Cakupan</th>
                    <th class="px-3 py-2 text-right">Sisa Gap</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/80 tabular-nums">
                  <tr
                    v-for="w in summaryData?.by_wilayah || []"
                    :key="w.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-900/50 cursor-pointer"
                    @click="emit('filter-wilayah', w.id)"
                  >
                    <td class="px-3 py-2 font-medium text-blue-700 dark:text-blue-400">{{ w.nama }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ formatAngka(w.coverage_pct, 1) }}%</td>
                    <td class="px-3 py-2 text-right text-amber-800 dark:text-amber-300">{{ formatPanjangKm(w.uncovered_m) }}</td>
                  </tr>
                  <tr v-if="!summaryData?.by_wilayah?.length">
                    <td colspan="3" class="px-3 py-4 text-center text-gray-600 dark:text-gray-400">
                      Belum ada data rekap wilayah.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 6: KONSOL ANALIS -->
      <div v-else-if="activeTab === 'console'" class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-gray-900 dark:text-white">
            Log Aktivitas & Telemetri Spasial
          </h2>
          <button
            v-if="consoleLogs.length > 0"
            type="button"
            class="text-xs font-medium text-blue-700 hover:underline dark:text-blue-400 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded"
            @click="emit('clear-console')"
          >
            Bersihkan Log
          </button>
        </div>

        <div v-if="consoleLogs.length === 0" class="rounded-md border border-dashed border-gray-300 p-4 text-center text-xs text-gray-600 dark:text-gray-400 dark:border-gray-700">
          Belum ada catatan peristiwa sesi ini.
        </div>

        <ul v-else class="space-y-1.5 text-xs">
          <li
            v-for="entry in consoleLogs"
            :key="entry.id"
            class="flex items-start gap-2.5 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 dark:border-gray-800 dark:bg-[#0e1424]"
          >
            <span class="tabular-nums text-[11px] font-semibold text-gray-600 dark:text-gray-400 shrink-0">
              {{ entry.timestamp }}
            </span>
            <span
              :class="[
                'font-medium',
                entry.level === 'error'
                  ? 'text-rose-800 dark:text-rose-300'
                  : entry.level === 'warn'
                    ? 'text-amber-800 dark:text-amber-300'
                    : entry.level === 'success'
                      ? 'text-emerald-800 dark:text-emerald-300'
                      : 'text-gray-800 dark:text-gray-200',
              ]"
            >
              {{ entry.message }}
            </span>
          </li>
        </ul>
      </div>

      <!-- Footer Pagination Strip -->
      <div
        v-if="showSearchAndPagination && currentMeta.total > 0"
        class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50/90 px-3 py-1.5 text-xs dark:border-gray-800 dark:bg-[#0e1424] shrink-0"
      >
        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300 tabular-nums">
          <span>
            Halaman {{ currentMeta.current_page }} dari {{ currentMeta.last_page }}
            ({{ formatAngka(currentMeta.total, 0) }} baris)
          </span>
          <div v-if="!isMobileDrawer" class="hidden sm:inline-flex items-center gap-1">
            <button
              v-for="sz in [15, 25, 50]"
              :key="sz"
              type="button"
              :class="[
                'h-6 rounded px-1.5 text-[11px] font-medium cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                perPage === sz
                  ? 'bg-gray-900 text-white dark:bg-gray-200 dark:text-gray-900'
                  : 'bg-white text-gray-700 border border-gray-300 dark:bg-[#0b0f19] dark:text-gray-300 dark:border-gray-700',
              ]"
              @click="emit('update:perPage', sz)"
            >
              {{ sz }}
            </button>
          </div>
        </div>

        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentMeta.current_page <= 1"
            aria-label="Halaman sebelumnya"
            :class="[
              'inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 font-medium text-gray-800 hover:bg-gray-100 disabled:opacity-45 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px] min-w-[44px]' : 'h-7',
            ]"
            @click="emit('change-page', { tab: activeTab, page: currentMeta.current_page - 1 })"
          >
            <UIcon name="i-lucide-chevron-left" class="size-4" />
          </button>
          <button
            type="button"
            :disabled="currentMeta.current_page >= currentMeta.last_page"
            aria-label="Halaman berikutnya"
            :class="[
              'inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 font-medium text-gray-800 hover:bg-gray-100 disabled:opacity-45 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px] min-w-[44px]' : 'h-7',
            ]"
            @click="emit('change-page', { tab: activeTab, page: currentMeta.current_page + 1 })"
          >
            <UIcon name="i-lucide-chevron-right" class="size-4" />
          </button>
        </div>
      </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.drawer-fade-enter-active,
.drawer-fade-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (prefers-reduced-motion: reduce) {
  .drawer-fade-enter-active,
  .drawer-fade-leave-active {
    transition: none;
  }
}
</style>

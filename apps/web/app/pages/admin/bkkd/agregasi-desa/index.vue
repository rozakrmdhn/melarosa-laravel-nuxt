<script setup lang="ts">
import type { BkkdAgregasiDesaItem, BkkdAgregasiDesaSummary, BkkdAgregasiProposalItem } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';
import { getTahunAnggaranOptions } from '~/utils/helpers';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'bkkd-proposal-view',
});

useSeoMeta({
  title: 'Agregasi Usulan BKKD Berdasarkan Wilayah Desa',
});

const api = useBkkdApi();

// Filter States
const selectedTahun = ref<number>(new Date().getFullYear() + 1);
const filterKecamatan = ref<number | null>(null);
const filterDesa = ref<number | null>(null);
const filterBidang = ref<string>('ALL');
const filterStatus = ref<string>('ALL');
const modePengelompokan = ref<'bidang' | 'desa'>('desa');
const searchQuery = ref('');
const page = ref(1);
const perPage = ref(25);

// Sorting States (default order by kecamatan dan desa)
type SortField = 'kecamatan' | 'desa' | 'bidang' | 'opd' | 'jumlah_usulan' | 'usulan_diverifikasi' | 'usulan_dipilih';
const sortBy = ref<SortField>('kecamatan');
const sortOrder = ref<'asc' | 'desc'>('asc');

function toggleSort(field: SortField) {
  if (sortBy.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = field;
    sortOrder.value = 'asc';
  }
  resetPageAndLoad();
}

// Data States
const items = ref<BkkdAgregasiDesaItem[]>([]);
const totalItems = ref(0);
const loading = ref(false);
const bidangOptions = ref<{ label: string; value: string }[]>([
  { label: 'Semua Bidang', value: 'ALL' },
]);

const summary = reactive<BkkdAgregasiDesaSummary>({
  total_desa: 0,
  desa_ada_usulan: 0,
  desa_belum_ada_usulan: 0,
  desa_diverifikasi: 0,
  desa_dipilih_tapd: 0,
  total_usulan: 0,
  total_usulan_diverifikasi: 0,
  total_usulan_dipilih: 0,
});

// Modal Detail Usulan Desa
const modalDetailOpen = ref(false);
const selectedRow = ref<BkkdAgregasiDesaItem | null>(null);

// Expandable Sub-Row States
const expandedRows = ref<Record<string, boolean>>({});

function getRowKey(item: BkkdAgregasiDesaItem, idx: number) {
  return `${item.id_desa}-${item.id_bidang || 'all'}-${idx}`;
}

function toggleRow(item: BkkdAgregasiDesaItem, idx: number) {
  if (item.jumlah_usulan === 0) return;
  const key = getRowKey(item, idx);
  expandedRows.value[key] = !expandedRows.value[key];
}

function isRowExpanded(item: BkkdAgregasiDesaItem, idx: number): boolean {
  return Boolean(expandedRows.value[getRowKey(item, idx)]);
}

function expandAllCurrentPage() {
  items.value.forEach((item, idx) => {
    if (item.jumlah_usulan > 0) {
      expandedRows.value[getRowKey(item, idx)] = true;
    }
  });
}

function collapseAllCurrentPage() {
  expandedRows.value = {};
}

const hasAnyExpanded = computed(() => {
  return Object.values(expandedRows.value).some(Boolean);
});

interface BidangSummaryTag {
  nama: string;
  count: number;
  hasDipilih: boolean;
  hasDiverifikasi: boolean;
}

function getBidangSummaryTags(item: BkkdAgregasiDesaItem): BidangSummaryTag[] {
  if (!item.proposals || item.proposals.length === 0) {
    if (item.nama_bidang && item.nama_bidang !== '-') {
      return [{
        nama: item.nama_bidang,
        count: item.jumlah_usulan || 1,
        hasDipilih: item.usulan_dipilih > 0,
        hasDiverifikasi: item.usulan_diverifikasi > 0,
      }];
    }
    return [];
  }

  const map = new Map<string, BidangSummaryTag>();
  for (const p of item.proposals) {
    const bName = p.bidang || 'Bidang Lainnya';
    const existing = map.get(bName);
    if (existing) {
      existing.count++;
      if (p.is_dipilih) existing.hasDipilih = true;
      if (p.is_diverifikasi) existing.hasDiverifikasi = true;
    } else {
      map.set(bName, {
        nama: bName,
        count: 1,
        hasDipilih: Boolean(p.is_dipilih),
        hasDiverifikasi: Boolean(p.is_diverifikasi),
      });
    }
  }
  return Array.from(map.values());
}

const tahunOptions = getTahunAnggaranOptions(false);

const statusOptions = [
  { label: 'Semua Status Wilayah', value: 'ALL' },
  { label: 'Sudah Ada Usulan', value: 'sudah_ada' },
  { label: 'Belum Ada Usulan', value: 'belum_ada' },
  { label: 'Sudah Diverifikasi OPD', value: 'diverifikasi' },
  { label: 'Belum Diverifikasi OPD', value: 'belum_diverifikasi' },
  { label: 'Sudah Dipilih TAPD', value: 'dipilih_tapd' },
  { label: 'Belum Dipilih TAPD', value: 'belum_dipilih' },
];

const perPageOptions = [
  { label: '15 per halaman', value: 15 },
  { label: '25 per halaman', value: 25 },
  { label: '50 per halaman', value: 50 },
  { label: '100 per halaman', value: 100 },
];

async function loadBidangOptions() {
  try {
    const res = await api.fetchRefBidang();
    if (res.data) {
      bidangOptions.value = [
        { label: 'Semua Bidang', value: 'ALL' },
        ...res.data.map((b) => ({ label: b.nama, value: b.id })),
      ];
    }
  } catch (err) {
    console.error('Gagal memuat referensi bidang:', err);
  }
}

async function loadData() {
  loading.value = true;
  try {
    const params: Record<string, any> = {
      tahun_anggaran: selectedTahun.value,
      status: filterStatus.value,
      mode: modePengelompokan.value,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
      page: page.value,
      per_page: perPage.value,
    };

    if (filterKecamatan.value) {
      params.id_kecamatan = filterKecamatan.value;
    }
    if (filterDesa.value) {
      params.id_desa = filterDesa.value;
    }
    if (filterBidang.value && filterBidang.value !== 'ALL') {
      params.id_bidang = filterBidang.value;
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    const res = await api.fetchAgregasiDesa(params);
    items.value = res.data || [];
    totalItems.value = res.meta?.total || 0;

    if (res.summary) {
      Object.assign(summary, res.summary);
    }
  } catch (err) {
    console.error('Gagal memuat data agregasi desa:', err);
  } finally {
    loading.value = false;
  }
}

function resetPageAndLoad() {
  expandedRows.value = {};
  if (page.value === 1) {
    loadData();
  } else {
    page.value = 1;
  }
}

watch([selectedTahun, filterKecamatan, filterDesa, filterBidang, filterStatus, modePengelompokan, perPage], () => {
  resetPageAndLoad();
});

watch(page, () => {
  loadData();
});

let searchDebounce: any = null;
function onSearchInput() {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    resetPageAndLoad();
  }, 400);
}

function handleExportExcel() {
  const params: Record<string, any> = {
    tahun_anggaran: selectedTahun.value,
    status: filterStatus.value,
    mode: modePengelompokan.value,
    sort_by: sortBy.value,
    sort_order: sortOrder.value,
  };
  if (filterKecamatan.value) {
    params.id_kecamatan = filterKecamatan.value;
  }
  if (filterDesa.value) {
    params.id_desa = filterDesa.value;
  }
  if (filterBidang.value && filterBidang.value !== 'ALL') {
    params.id_bidang = filterBidang.value;
  }
  if (searchQuery.value.trim()) {
    params.search = searchQuery.value.trim();
  }

  const url = api.getAgregasiDesaExportUrl(params);
  window.open(url, '_blank');
}

function openDetailModal(item: BkkdAgregasiDesaItem) {
  selectedRow.value = item;
  modalDetailOpen.value = true;
}

function formatCurrency(val: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

onMounted(() => {
  loadBidangOptions();
  loadData();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
          <UIcon name="i-lucide-table-properties" class="w-6 h-6 text-primary-500" />
          <span>Agregasi Usulan Wilayah Desa BKKD</span>
        </h1>
        <p class="text-xs text-gray-500 mt-1">
          Matriks pemantauan sebaran usulan desa, status verifikasi OPD teknis, dan penetapan pilihan TAPD per wilayah se-Kabupaten Bojonegoro.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          color="neutral"
          variant="outline"
          icon="i-lucide-file-spreadsheet"
          label="Ekspor Excel"
          @click="handleExportExcel"
        />
      </div>
    </div>

    <!-- Statistik Metrik Kartu -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
      <div
        class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs cursor-pointer hover:border-primary-400 transition-colors"
        @click="filterStatus = 'ALL'"
      >
        <span class="text-[11px] font-medium text-gray-500">Total Wilayah Desa</span>
        <div class="text-2xl font-bold font-mono text-gray-900 dark:text-gray-100 mt-1">
          {{ summary.total_desa }} <span class="text-xs font-normal text-gray-400">desa</span>
        </div>
        <div class="text-[10px] text-gray-400 mt-0.5">Kab. Bojonegoro</div>
      </div>

      <div
        class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs cursor-pointer hover:border-blue-400 transition-colors"
        :class="{ 'ring-2 ring-blue-500/30': filterStatus === 'sudah_ada' }"
        @click="filterStatus = 'sudah_ada'"
      >
        <span class="text-[11px] font-medium text-blue-600 dark:text-blue-400">Sudah Ada Usulan</span>
        <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 mt-1">
          {{ summary.desa_ada_usulan }} <span class="text-xs font-normal text-gray-400">desa</span>
        </div>
        <div class="text-[10px] text-blue-500/80 mt-0.5">Total {{ summary.total_usulan }} proposal</div>
      </div>

      <div
        class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs cursor-pointer hover:border-amber-400 transition-colors"
        :class="{ 'ring-2 ring-amber-500/30': filterStatus === 'belum_ada' }"
        @click="filterStatus = 'belum_ada'"
      >
        <span class="text-[11px] font-medium text-amber-600 dark:text-amber-400">Belum Ada Usulan</span>
        <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 mt-1">
          {{ summary.desa_belum_ada_usulan }} <span class="text-xs font-normal text-gray-400">desa</span>
        </div>
        <div class="text-[10px] text-amber-600/70 mt-0.5">Belum mengajukan usulan</div>
      </div>

      <div
        class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs cursor-pointer hover:border-emerald-400 transition-colors"
        :class="{ 'ring-2 ring-emerald-500/30': filterStatus === 'diverifikasi' }"
        @click="filterStatus = 'diverifikasi'"
      >
        <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Diverifikasi OPD</span>
        <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1">
          {{ summary.desa_diverifikasi }} <span class="text-xs font-normal text-gray-400">desa</span>
        </div>
        <div class="text-[10px] text-emerald-600/80 mt-0.5">{{ summary.total_usulan_diverifikasi }} usulan ditelaah</div>
      </div>

      <div
        class="p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-xs cursor-pointer hover:border-emerald-500 transition-colors"
        :class="{ 'ring-2 ring-emerald-500/40': filterStatus === 'dipilih_tapd' }"
        @click="filterStatus = 'dipilih_tapd'"
      >
        <span class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300">Dipilih TAPD</span>
        <div class="text-2xl font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-1">
          {{ summary.desa_dipilih_tapd }} <span class="text-xs font-normal text-emerald-600">desa</span>
        </div>
        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5">{{ summary.total_usulan_dipilih }} usulan disetujui</div>
      </div>
    </div>

    <!-- Filter & Toolbar Bar -->
    <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tahun Anggaran</label>
          <USelectMenu
            v-model="selectedTahun"
            :items="tahunOptions"
            value-key="value"
            label-key="label"
            class="w-full text-xs"
            size="sm"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Bidang Usulan</label>
          <USelectMenu
            v-model="filterBidang"
            :items="bidangOptions"
            value-key="value"
            label-key="label"
            class="w-full text-xs"
            size="sm"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status Usulan</label>
          <USelectMenu
            v-model="filterStatus"
            :items="statusOptions"
            value-key="value"
            label-key="label"
            class="w-full text-xs"
            size="sm"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Pencarian</label>
          <UInput
            v-model="searchQuery"
            placeholder="Cari kecamatan, desa, OPD..."
            icon="i-lucide-search"
            class="w-full text-xs"
            size="sm"
            @input="onSearchInput"
          />
        </div>
      </div>

      <div class="pt-2 border-t border-gray-100 dark:border-white/[0.04] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="w-full sm:w-auto">
          <WilayahSelector
            v-model:modelKecamatan="filterKecamatan"
            v-model:modelDesa="filterDesa"
            allow-all
            layout="row"
            size="sm"
          />
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
          <!-- Tombol Expand / Collapse All -->
          <div class="hidden sm:inline-flex items-center gap-1 p-0.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-gray-800 text-xs">
            <button
              type="button"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-white dark:hover:bg-gray-700 transition-colors"
              title="Buka seluruh rincian usulan pada halaman ini"
              @click="expandAllCurrentPage"
            >
              <UIcon name="i-lucide-chevrons-down" class="w-3.5 h-3.5" />
              <span>Buka Semua</span>
            </button>
            <button
              v-if="hasAnyExpanded"
              type="button"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-white dark:hover:bg-gray-700 transition-colors"
              title="Tutup seluruh rincian usulan pada halaman ini"
              @click="collapseAllCurrentPage"
            >
              <UIcon name="i-lucide-chevrons-up" class="w-3.5 h-3.5" />
              <span>Tutup Semua</span>
            </button>
          </div>

          <div class="flex items-center gap-1.5 text-xs text-gray-500">
            <span>Tampilan:</span>
            <div class="inline-flex rounded-lg border border-gray-200 dark:border-white/[0.08] p-0.5 bg-gray-50 dark:bg-gray-800">
              <button
                type="button"
                class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
                :class="modePengelompokan === 'desa' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-800'"
                @click="modePengelompokan = 'desa'"
              >
                Ringkasan Desa
              </button>
              <button
                type="button"
                class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
                :class="modePengelompokan === 'bidang' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-800'"
                @click="modePengelompokan = 'bidang'"
              >
                Per Bidang & OPD
              </button>
            </div>
          </div>

          <USelectMenu
            v-model="perPage"
            :items="perPageOptions"
            value-key="value"
            label-key="label"
            class="w-36 text-xs"
            size="sm"
          />
        </div>
      </div>
    </div>

    <!-- Tabel Agregasi Data Desa -->
    <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/70 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400">
              <th class="p-3 w-9 text-center"></th>
              <th class="p-3 font-semibold w-12 text-center">No</th>
              <th
                class="p-3 font-semibold cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan Kecamatan"
                @click="toggleSort('kecamatan')"
              >
                <div class="flex items-center gap-1.5">
                  <span>Kecamatan</span>
                  <UIcon
                    v-if="sortBy === 'kecamatan'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-a-z' : 'i-lucide-arrow-down-z-a'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th
                class="p-3 font-semibold cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan Desa"
                @click="toggleSort('desa')"
              >
                <div class="flex items-center gap-1.5">
                  <span>Desa</span>
                  <UIcon
                    v-if="sortBy === 'desa'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-a-z' : 'i-lucide-arrow-down-z-a'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th class="p-3 font-semibold text-center min-w-[140px] whitespace-nowrap">
                Status Wilayah
              </th>
              <th
                class="p-3 font-semibold min-w-[170px] cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan Bidang"
                @click="toggleSort('bidang')"
              >
                <div class="flex items-center gap-1.5">
                  <span>{{ modePengelompokan === 'desa' ? 'Bidang Usulan' : 'Bidang' }}</span>
                  <UIcon
                    v-if="sortBy === 'bidang'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-a-z' : 'i-lucide-arrow-down-z-a'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th
                class="p-3 font-semibold cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan OPD Teknis"
                @click="toggleSort('opd')"
              >
                <div class="flex items-center gap-1.5">
                  <span>OPD Teknis</span>
                  <UIcon
                    v-if="sortBy === 'opd'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-a-z' : 'i-lucide-arrow-down-z-a'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th
                class="p-3 font-semibold text-center w-28 cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan Jumlah Usulan"
                @click="toggleSort('jumlah_usulan')"
              >
                <div class="flex items-center justify-center gap-1.5">
                  <span>Jumlah Usulan</span>
                  <UIcon
                    v-if="sortBy === 'jumlah_usulan'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-1-9' : 'i-lucide-arrow-down-9-1'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th
                class="p-3 font-semibold text-center w-32 cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan Usulan Diverifikasi"
                @click="toggleSort('usulan_diverifikasi')"
              >
                <div class="flex items-center justify-center gap-1.5">
                  <span>Usulan Diverifikasi</span>
                  <UIcon
                    v-if="sortBy === 'usulan_diverifikasi'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-1-9' : 'i-lucide-arrow-down-9-1'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th
                class="p-3 font-semibold text-center w-28 cursor-pointer select-none hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                title="Urutkan berdasarkan Usulan Dipilih"
                @click="toggleSort('usulan_dipilih')"
              >
                <div class="flex items-center justify-center gap-1.5">
                  <span>Usulan Dipilih</span>
                  <UIcon
                    v-if="sortBy === 'usulan_dipilih'"
                    :name="sortOrder === 'asc' ? 'i-lucide-arrow-up-1-9' : 'i-lucide-arrow-down-9-1'"
                    class="w-3.5 h-3.5 text-primary-500"
                  />
                  <UIcon
                    v-else
                    name="i-lucide-arrow-up-down"
                    class="w-3 h-3 text-gray-400/50"
                  />
                </div>
              </th>
              <th class="p-3 font-semibold text-center w-20">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr v-if="loading">
              <td colspan="11" class="p-8 text-center text-gray-500">
                <UIcon name="i-lucide-loader-2" class="w-6 h-6 animate-spin mx-auto text-primary-500 mb-2" />
                Memuat data agregasi wilayah desa...
              </td>
            </tr>

            <tr v-else-if="items.length === 0">
              <td colspan="11" class="p-8 text-center text-gray-500">
                <UIcon name="i-lucide-inbox" class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                Tidak ditemukan data wilayah desa sesuai filter pencarian.
              </td>
            </tr>

            <template
              v-for="(item, idx) in items"
              :key="getRowKey(item, idx)"
            >
              <!-- Baris Utama Desa -->
              <tr
                class="transition-colors border-b border-gray-100 dark:border-white/[0.04]"
                :class="[
                  item.usulan_dipilih > 0
                    ? 'bg-emerald-50/70 dark:bg-emerald-950/40 hover:bg-emerald-100/70 dark:hover:bg-emerald-900/50 border-l-4 border-l-emerald-500'
                    : isRowExpanded(item, idx)
                      ? 'bg-slate-50 dark:bg-slate-800/40 border-l-4 border-l-primary-500'
                      : item.jumlah_usulan === 0
                        ? 'hover:bg-gray-50/60 dark:hover:bg-gray-800/20 opacity-75'
                        : 'hover:bg-gray-50/60 dark:hover:bg-gray-800/20',
                  item.jumlah_usulan > 0 ? 'cursor-pointer' : ''
                ]"
                @click="toggleRow(item, idx)"
              >
                <!-- 0. Chevron Toggle Expand -->
                <td class="p-2.5 text-center w-9">
                  <button
                    v-if="item.jumlah_usulan > 0"
                    type="button"
                    class="p-1 rounded-md text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-200/60 dark:hover:bg-gray-700/60 transition-colors"
                    :title="isRowExpanded(item, idx) ? 'Tutup rincian usulan' : 'Lihat rincian usulan desa'"
                    @click.stop="toggleRow(item, idx)"
                  >
                    <UIcon
                      name="i-lucide-chevron-right"
                      class="w-4 h-4 transition-transform duration-200"
                      :class="{ 'rotate-90 text-primary-600 dark:text-primary-400': isRowExpanded(item, idx) }"
                    />
                  </button>
                  <span v-else class="text-gray-300 dark:text-gray-700 text-xs">•</span>
                </td>

                <!-- 1. No -->
                <td class="p-3 text-center font-mono text-gray-500">
                  {{ (page - 1) * perPage + idx + 1 }}
                </td>

                <!-- 2. Kecamatan -->
                <td class="p-3 whitespace-nowrap font-medium text-gray-800 dark:text-gray-200">
                  {{ item.nama_kecamatan }}
                </td>

                <!-- 3. Desa -->
                <td class="p-3 whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                      {{ item.nama_desa }}
                    </span>
                    <span v-if="modePengelompokan === 'desa' && item.jumlah_usulan > 1" class="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-white/10">
                      {{ item.jumlah_usulan }} usulan
                    </span>
                  </div>
                </td>

                <!-- 4. Status Wilayah (Smart Consolidation Badge) -->
                <td class="p-3 whitespace-nowrap text-center">
                  <UBadge
                    v-if="item.usulan_dipilih > 0 && item.usulan_dipilih === item.jumlah_usulan"
                    color="success"
                    variant="subtle"
                    size="sm"
                    class="font-semibold text-xs border border-emerald-300/60 dark:border-emerald-700/60 shadow-2xs"
                  >
                    <UIcon name="i-lucide-award" class="w-3.5 h-3.5 shrink-0" />
                    <span>Semua Terpilih ({{ item.usulan_dipilih }})</span>
                  </UBadge>
                  <UBadge
                    v-else-if="item.usulan_dipilih > 0"
                    color="success"
                    variant="subtle"
                    size="sm"
                    class="font-semibold text-xs border border-emerald-300/60 dark:border-emerald-700/60 shadow-2xs"
                  >
                    <UIcon name="i-lucide-check-circle" class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ item.usulan_dipilih }} dari {{ item.jumlah_usulan }} Terpilih</span>
                  </UBadge>
                  <UBadge
                    v-else-if="item.jumlah_usulan === 0"
                    color="neutral"
                    variant="subtle"
                    size="sm"
                    class="text-xs font-normal text-gray-400 border border-gray-200/60 dark:border-white/10"
                  >
                    Belum Mengajukan
                  </UBadge>
                  <UBadge
                    v-else-if="item.usulan_diverifikasi > 0"
                    color="primary"
                    variant="subtle"
                    size="sm"
                    class="font-semibold text-xs border border-primary-200/60 dark:border-primary-800/60 shadow-2xs"
                  >
                    <UIcon name="i-lucide-clipboard-check" class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ item.usulan_diverifikasi }} Diverifikasi OPD</span>
                  </UBadge>
                  <UBadge
                    v-else
                    color="warning"
                    variant="subtle"
                    size="sm"
                    class="font-medium text-xs border border-amber-200/60 dark:border-amber-800/60"
                  >
                    <UIcon name="i-lucide-clock" class="w-3.5 h-3.5 shrink-0" />
                    <span>Menunggu Telaah</span>
                  </UBadge>
                </td>

                <!-- 5. Bidang Usulan (Tag Pills Multi-Bidang) -->
                <td class="p-3">
                  <!-- Mode Ringkasan Desa: Tampilkan Pills per Bidang dengan Micro-Status -->
                  <div v-if="modePengelompokan === 'desa'" class="flex flex-wrap items-center gap-1.5 max-w-sm">
                    <template v-if="getBidangSummaryTags(item).length > 0">
                      <span
                        v-for="b in getBidangSummaryTags(item)"
                        :key="b.nama"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border"
                        :class="[
                          b.hasDipilih
                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60 font-semibold shadow-2xs'
                            : 'bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 border-primary-200 dark:border-primary-800/50'
                        ]"
                      >
                        <span
                          v-if="b.hasDipilih"
                          class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"
                          title="Usulan pada bidang ini terpilih TAPD"
                        />
                        <span class="truncate max-w-[130px]">{{ b.nama }}</span>
                        <span class="text-[10px] font-mono opacity-80">({{ b.count }})</span>
                      </span>
                    </template>
                    <span v-else class="text-gray-400 font-sans font-normal text-xs">-</span>
                  </div>

                  <!-- Mode Per Bidang & OPD: Single Badge -->
                  <div v-else>
                    <UBadge
                      v-if="item.nama_bidang && item.nama_bidang !== '-'"
                      color="primary"
                      variant="subtle"
                      size="xs"
                      class="font-medium text-[11px]"
                    >
                      {{ item.nama_bidang }}
                    </UBadge>
                    <span v-else class="text-gray-400 font-sans font-normal text-xs">-</span>
                  </div>
                </td>

                <!-- 6. OPD Teknis -->
                <td class="p-3 max-w-xs">
                  <div v-if="item.nama_opd && item.nama_opd !== '-'">
                    <div class="font-medium text-gray-800 dark:text-gray-200 line-clamp-1" :title="item.nama_opd">
                      {{ item.nama_opd }}
                    </div>
                    <div v-if="item.kode_opd && item.kode_opd !== '-'" class="text-[10px] text-gray-400 font-mono">
                      {{ item.kode_opd }}
                    </div>
                  </div>
                  <span v-else class="text-gray-400 font-sans font-normal">-</span>
                </td>

                <!-- 7. Jumlah Usulan -->
                <td class="p-3 text-center whitespace-nowrap">
                  <span
                    v-if="item.jumlah_usulan > 0"
                    class="inline-flex items-center justify-center min-w-7 px-2 py-0.5 rounded-full font-mono font-bold text-xs bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-900/40"
                  >
                    {{ item.jumlah_usulan }}
                  </span>
                  <span v-else class="text-gray-400 font-mono font-normal">0</span>
                </td>

                <!-- 8. Usulan Diverifikasi -->
                <td class="p-3 text-center whitespace-nowrap">
                  <span
                    v-if="item.usulan_diverifikasi > 0"
                    class="inline-flex items-center justify-center min-w-7 px-2 py-0.5 rounded-full font-mono font-bold text-xs bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/40"
                  >
                    {{ item.usulan_diverifikasi }}
                  </span>
                  <span v-else-if="item.jumlah_usulan > 0" class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/40">
                    0 (Menunggu)
                  </span>
                  <span v-else class="text-gray-400 font-mono font-normal">0</span>
                </td>

                <!-- 9. Usulan Dipilih -->
                <td class="p-3 text-center whitespace-nowrap">
                  <span
                    v-if="item.usulan_dipilih > 0"
                    class="inline-flex items-center justify-center min-w-7 px-2 py-0.5 rounded-full font-mono font-bold text-xs bg-emerald-600 text-white shadow-xs"
                  >
                    {{ item.usulan_dipilih }}
                  </span>
                  <span v-else-if="item.jumlah_usulan > 0" class="text-gray-400 font-mono font-normal">
                    0
                  </span>
                  <span v-else class="text-gray-400 font-mono font-normal">
                    0
                  </span>
                </td>

                <!-- 10. Action Detail -->
                <td class="p-3 text-center whitespace-nowrap">
                  <div class="flex items-center justify-center gap-1">
                    <UButton
                      v-if="item.jumlah_usulan > 0"
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      :icon="isRowExpanded(item, idx) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                      :title="isRowExpanded(item, idx) ? 'Tutup Rincian' : 'Buka Rincian Usulan'"
                      @click.stop="toggleRow(item, idx)"
                    />
                    <UButton
                      v-if="item.jumlah_usulan > 0"
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      icon="i-lucide-maximize-2"
                      title="Lihat Rincian Lengkap (Modal)"
                      @click.stop="openDetailModal(item)"
                    />
                    <span v-else class="text-gray-300 dark:text-gray-600">-</span>
                  </div>
                </td>
              </tr>

              <!-- Sub-Row Expandable Drawer untuk Rincian Usulan per Bidang -->
              <tr
                v-if="isRowExpanded(item, idx)"
                :key="`expand-${getRowKey(item, idx)}`"
                class="bg-slate-50/70 dark:bg-slate-900/60 border-b border-gray-200 dark:border-white/[0.08]"
              >
                <td colspan="11" class="p-0 border-b border-gray-200 dark:border-white/[0.08]">
                  <div class="p-4 sm:p-5 bg-gradient-to-b from-slate-50/90 to-slate-100/60 dark:from-slate-900/60 dark:to-slate-950/80 border-y border-primary-200/50 dark:border-primary-900/40 shadow-inner space-y-3.5">
                    <!-- Sub-row Header Banner -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1">
                      <div class="flex items-start sm:items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
                          <UIcon name="i-lucide-list-tree" class="w-4 h-4" />
                        </div>
                        <div>
                          <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 flex flex-wrap items-center gap-2">
                            <span>Rincian Usulan Desa {{ item.nama_desa }}</span>
                            <UBadge color="primary" size="xs" variant="subtle">
                              {{ item.proposals.length }} Usulan Tercatat
                            </UBadge>
                          </h4>
                          <p class="text-[11px] text-gray-500 mt-0.5">
                            Kecamatan {{ item.nama_kecamatan }} • Tahun Anggaran {{ selectedTahun }}
                          </p>
                        </div>
                      </div>

                      <!-- Total Nilai Usulan, Rekomendasi OPD, & Alokasi TAPD -->
                      <div class="flex flex-wrap items-center gap-2.5 text-xs font-mono">
                        <div class="p-2 rounded-lg bg-white/90 dark:bg-gray-800/80 border border-gray-200/80 dark:border-white/[0.06] text-right shadow-2xs">
                          <span class="text-[10px] text-gray-500 block font-sans">Total Usulan Desa:</span>
                          <span class="font-bold text-gray-900 dark:text-gray-100">
                            {{ formatCurrency(item.total_nilai_usulan || item.proposals.reduce((acc, p) => acc + (p.nilai_usulan || 0), 0)) }}
                          </span>
                        </div>

                        <div v-if="(item.total_rekomendasi_opd ?? 0) > 0" class="p-2 rounded-lg bg-sky-50/90 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-900/60 text-right shadow-2xs">
                          <span class="text-[10px] text-sky-700 dark:text-sky-400 block font-sans font-medium">Total Rekomendasi OPD:</span>
                          <span class="font-bold text-sky-700 dark:text-sky-300">
                            {{ formatCurrency(item.total_rekomendasi_opd || item.proposals.reduce((acc, p) => acc + (p.nilai_rekomendasi || 0), 0)) }}
                          </span>
                        </div>

                        <div v-if="item.usulan_dipilih > 0" class="p-2 rounded-lg bg-emerald-50/90 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-900/60 text-right shadow-2xs">
                          <span class="text-[10px] text-emerald-700 dark:text-emerald-400 block font-sans font-medium">Alokasi Disetujui TAPD:</span>
                          <span class="font-bold text-emerald-700 dark:text-emerald-300">
                            {{ formatCurrency(item.total_disetujui_tapd || item.proposals.filter(p => p.is_dipilih).reduce((acc, p) => acc + (p.nilai_disetujui_tapd || 0), 0)) }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <!-- Sub-table Usulan -->
                    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] overflow-hidden bg-white dark:bg-[#0b0f19] shadow-xs">
                      <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                          <thead>
                            <tr class="bg-gray-50/90 dark:bg-gray-800/60 text-[11px] font-semibold text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.06]">
                              <th class="py-2.5 px-3 w-10 text-center">No</th>
                              <th class="py-2.5 px-3 w-48">No. Surat Desa</th>
                              <th class="py-2.5 px-3 w-40">Bidang BKKD</th>
                              <th class="py-2.5 px-3 w-48">OPD Teknis Penelaah</th>
                              <th class="py-2.5 px-3">Judul Usulan Kegiatan</th>
                              <th class="py-2.5 px-3 text-right w-36 whitespace-nowrap">Nilai Usulan</th>
                              <th class="py-2.5 px-3 text-right w-36 whitespace-nowrap">Nilai Rekomendasi</th>
                              <th class="py-2.5 px-3 text-right w-36 whitespace-nowrap">Alokasi Disetujui</th>
                              <th class="py-2.5 px-3 text-center w-36">Status Terakhir</th>
                              <th class="py-2.5 px-3 text-center w-20">Aksi</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            <tr
                              v-for="(prop, pIdx) in item.proposals"
                              :key="prop.id"
                              class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition-colors"
                              :class="{ 'bg-emerald-50/30 dark:bg-emerald-950/15': prop.is_dipilih }"
                            >
                              <td class="py-2.5 px-3 text-center text-gray-400 font-mono text-[11px]">
                                {{ pIdx + 1 }}
                              </td>

                              <td class="py-2.5 px-3 font-mono font-semibold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                <NuxtLink
                                  :to="`/admin/bkkd/proposal/${prop.id}`"
                                  target="_blank"
                                  class="hover:underline flex items-center gap-1.5"
                                  :title="prop.nomor_registrasi ? `No. Registrasi: ${prop.nomor_registrasi}` : undefined"
                                >
                                  <span>{{ prop.nomor_surat_desa || prop.nomor_registrasi || '-' }}</span>
                                  <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                </NuxtLink>
                                <span v-if="prop.nomor_surat_desa && prop.nomor_registrasi" class="text-[10px] text-gray-400 font-mono block font-normal">
                                  {{ prop.nomor_registrasi }}
                                </span>
                              </td>

                              <td class="py-2.5 px-3 whitespace-nowrap">
                                <UBadge color="primary" size="xs" variant="subtle" class="font-medium text-[11px]">
                                  {{ prop.bidang || '-' }}
                                </UBadge>
                              </td>

                              <td class="py-2.5 px-3 text-[11px] text-gray-600 dark:text-gray-300">
                                <div class="line-clamp-1" :title="prop.opd || '-'">
                                  {{ prop.opd || '-' }}
                                </div>
                              </td>

                              <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-100">
                                <div class="line-clamp-2" :title="prop.judul_kegiatan">
                                  {{ prop.judul_kegiatan }}
                                </div>
                              </td>

                              <!-- 1. Kolom Nilai Usulan -->
                              <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                <span>{{ formatCurrency(prop.nilai_usulan || 0) }}</span>
                              </td>

                              <!-- 2. Kolom Nilai Rekomendasi OPD -->
                              <td class="py-2.5 px-3 text-right font-mono whitespace-nowrap">
                                <span
                                  v-if="prop.nilai_rekomendasi !== null && prop.nilai_rekomendasi !== undefined"
                                  class="font-semibold text-sky-700 dark:text-sky-300"
                                >
                                  {{ formatCurrency(prop.nilai_rekomendasi) }}
                                </span>
                                <span
                                  v-else-if="prop.is_diverifikasi"
                                  class="text-[11px] text-gray-400 font-sans italic"
                                  title="OPD merekomendasikan sesuai nilai usulan desa"
                                >
                                  Sesuai Usulan
                                </span>
                                <span v-else class="text-gray-400 font-sans text-xs">
                                  -
                                </span>
                              </td>

                              <!-- 3. Kolom Alokasi Disetujui TAPD -->
                              <td class="py-2.5 px-3 text-right font-mono whitespace-nowrap">
                                <span
                                  v-if="prop.is_dipilih && prop.nilai_disetujui_tapd"
                                  class="font-bold text-emerald-700 dark:text-emerald-400"
                                >
                                  {{ formatCurrency(prop.nilai_disetujui_tapd) }}
                                </span>
                                <span
                                  v-else-if="prop.is_dipilih"
                                  class="font-bold text-emerald-700 dark:text-emerald-400"
                                >
                                  {{ formatCurrency(prop.nilai_rekomendasi || prop.nilai_usulan || 0) }}
                                </span>
                                <span v-else class="text-gray-300 dark:text-gray-600 text-xs">
                                  -
                                </span>
                              </td>

                              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                <BkkdStatusBadge :status="prop.status" size="sm" />
                              </td>

                              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                <UButton
                                  color="primary"
                                  variant="subtle"
                                  size="xs"
                                  icon="i-lucide-external-link"
                                  label="Buka"
                                  :to="`/admin/bkkd/proposal/${prop.id}`"
                                  target="_blank"
                                />
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="totalItems > 0"
        class="p-3.5 border-t border-gray-100 dark:border-white/[0.04] bg-gray-50/50 dark:bg-gray-800/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-gray-500"
      >
        <div>
          Menampilkan baris <span class="font-medium text-gray-800 dark:text-gray-200">{{ (page - 1) * perPage + 1 }}</span> sampai <span class="font-medium text-gray-800 dark:text-gray-200">{{ Math.min(page * perPage, totalItems) }}</span> dari total <span class="font-medium text-gray-800 dark:text-gray-200">{{ totalItems }}</span> data agregasi desa
        </div>

        <div class="flex items-center gap-2">
          <UPagination
            v-model:page="page"
            :total="totalItems"
            :items-per-page="perPage"
            size="sm"
          />
        </div>
      </div>
    </div>

    <!-- Modal Daftar Usulan Desa -->
    <UModal
      v-model:open="modalDetailOpen"
      :title="selectedRow ? `Daftar Usulan Desa ${selectedRow.nama_desa}` : 'Rincian Usulan'"
      :description="selectedRow ? `Kecamatan ${selectedRow.nama_kecamatan} • ${selectedRow.nama_bidang !== '-' ? selectedRow.nama_bidang : 'Semua Bidang'}` : undefined"
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div v-if="selectedRow && selectedRow.proposals.length > 0" class="space-y-3 py-1">
          <div
            v-for="prop in selectedRow.proposals"
            :key="prop.id"
            class="p-3.5 rounded-xl border shadow-xs space-y-2 transition-colors"
            :class="[
              prop.is_dipilih
                ? 'border-emerald-300 dark:border-emerald-800/80 bg-emerald-50/70 dark:bg-emerald-950/30 ring-1 ring-emerald-500/30'
                : 'border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/50',
            ]"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <NuxtLink
                :to="`/admin/bkkd/proposal/${prop.id}`"
                class="font-mono font-bold text-xs text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1"
                target="_blank"
                :title="prop.nomor_registrasi ? `No. Registrasi: ${prop.nomor_registrasi}` : undefined"
              >
                <span>{{ prop.nomor_surat_desa || prop.nomor_registrasi }}</span>
                <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5 text-gray-400" />
              </NuxtLink>

              <div class="flex items-center gap-1.5">
                <UBadge v-if="prop.is_dipilih" color="success" size="xs" variant="solid">
                  Dipilih TAPD
                </UBadge>
                <UBadge v-else-if="prop.is_diverifikasi" color="primary" size="xs" variant="subtle">
                  Sudah Ditelaah OPD
                </UBadge>
                <UBadge v-else color="neutral" size="xs" variant="outline">
                  Menunggu Verifikasi
                </UBadge>
              </div>
            </div>

            <h4 class="text-xs font-semibold text-gray-900 dark:text-gray-100">
              {{ prop.judul_kegiatan }}
            </h4>

            <div class="flex flex-wrap items-center justify-between text-xs pt-2 border-t border-gray-100 dark:border-white/[0.04] gap-2">
              <span class="text-gray-500 font-sans">
                Bidang: <strong class="text-gray-700 dark:text-gray-300">{{ prop.bidang || '-' }}</strong>
              </span>

              <div class="flex flex-wrap items-center gap-3 text-right">
                <div>
                  <span class="text-[10px] text-gray-400 block font-sans">Usulan:</span>
                  <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">
                    {{ formatCurrency(prop.nilai_usulan) }}
                  </span>
                </div>

                <div v-if="prop.nilai_rekomendasi !== null && prop.nilai_rekomendasi !== undefined">
                  <span class="text-[10px] text-sky-600 dark:text-sky-400 block font-sans font-medium">Rekom OPD:</span>
                  <span class="font-mono font-semibold text-sky-700 dark:text-sky-300">
                    {{ formatCurrency(prop.nilai_rekomendasi) }}
                  </span>
                </div>

                <div v-if="prop.is_dipilih && prop.nilai_disetujui_tapd">
                  <span class="text-[10px] text-emerald-600 dark:text-emerald-400 block font-sans font-medium">Pagu TAPD:</span>
                  <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(prop.nilai_disetujui_tapd) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="p-6 text-center text-gray-500 text-xs">
          Belum ada proposal usulan yang tercatat.
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { PlottingAnggaran, RefSumberDana } from '~/types/infrastruktur';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { usePermission } from '~/composables/usePermission';
import PlottingMetricsCard from '~/components/plotting-anggaran/PlottingMetricsCard.vue';
import PlottingFormModal from '~/components/plotting-anggaran/PlottingFormModal.vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'plotting-anggaran-view',
});

useSeoMeta({
  title: 'Perencanaan Plotting Pagu Anggaran',
});

const toast = useToast();
const { can } = usePermission();
const api = useInfrastrukturApi();

const canManage = computed(() => can('plotting-anggaran-manage'));

// ─── Query & Filter State ───────────────────────────────────────────────────
const currentYear = new Date().getFullYear();
const selectedTahun = ref<number | 'ALL'>('ALL');
const filterKecamatan = ref<number | null>(null);
const filterDesa = ref<number | null>(null);
const filterSumberDana = ref<string>('ALL');
const searchQuery = ref('');
const page = ref(1);
const perPage = ref(15);

const items = ref<PlottingAnggaran[]>([]);
const totalItems = ref(0);
const loading = ref(false);

const summary = reactive({
  total_kegiatan: 0,
  total_pagu_anggaran: 0,
  total_target_panjang: 0,
});

// Referensi Sumber Dana untuk Filter
const sumberDanaList = ref<RefSumberDana[]>([]);

const sumberDanaFilterItems = computed(() => [
  { label: 'Semua Sumber Dana', value: 'ALL' },
  ...sumberDanaList.value.map((sd) => ({
    label: `${sd.nama} (${sd.kode})`,
    value: sd.kode,
  })),
]);

const tahunOptions = [
  { label: 'Semua Tahun', value: 'ALL' },
  { label: '2026', value: 2026 },
  { label: '2025', value: 2025 },
  { label: '2024', value: 2024 },
  { label: '2023', value: 2023 },
];

async function loadData() {
  loading.value = true;
  try {
    const params: Record<string, any> = {
      page: page.value,
      per_page: perPage.value,
    };

    if (selectedTahun.value !== 'ALL') {
      params.tahun_anggaran = selectedTahun.value;
    }
    if (filterKecamatan.value) {
      params.id_kecamatan = filterKecamatan.value;
    }
    if (filterDesa.value) {
      params.id_desa = filterDesa.value;
    }
    if (filterSumberDana.value !== 'ALL') {
      params.sumber_dana = filterSumberDana.value;
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    const res = await api.fetchPlottingList(params);
    items.value = res.data || [];
    totalItems.value = res.meta?.total || 0;

    if (res.summary) {
      summary.total_kegiatan = res.summary.total_kegiatan || 0;
      summary.total_pagu_anggaran = res.summary.total_pagu_anggaran || 0;
      summary.total_target_panjang = res.summary.total_target_panjang || 0;
    }
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Data',
      description: err?.data?.message || 'Terjadi kesalahan saat memuat data plotting anggaran.',
      color: 'error',
    });
  } finally {
    loading.value = false;
  }
}

async function loadSumberDanaOptions() {
  try {
    const res = await api.fetchSumberDanaList();
    sumberDanaList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat list sumber dana:', err);
  }
}

function handleResetFilters() {
  selectedTahun.value = 'ALL';
  filterKecamatan.value = null;
  filterDesa.value = null;
  filterSumberDana.value = 'ALL';
  searchQuery.value = '';
  page.value = 1;
  loadData();
}

// Watch filters to reset page to 1
watch([selectedTahun, filterKecamatan, filterDesa, filterSumberDana], () => {
  page.value = 1;
  loadData();
});

// Debounce search
let searchTimer: any = null;
watch(searchQuery, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    loadData();
  }, 400);
});

// Watch page change
watch(page, () => {
  loadData();
});

// ─── Form Modal State ───────────────────────────────────────────────────────
const isFormOpen = ref(false);
const isEditing = ref(false);
const selectedItem = ref<PlottingAnggaran | null>(null);

function openCreateModal() {
  isEditing.value = false;
  selectedItem.value = null;
  isFormOpen.value = true;
}

function openEditModal(item: PlottingAnggaran) {
  isEditing.value = true;
  selectedItem.value = item;
  isFormOpen.value = true;
}

// ─── Delete Modal State ─────────────────────────────────────────────────────
const isDeleteOpen = ref(false);
const itemToDelete = ref<PlottingAnggaran | null>(null);
const deleting = ref(false);

function confirmDelete(item: PlottingAnggaran) {
  itemToDelete.value = item;
  isDeleteOpen.value = true;
}

async function handleDelete() {
  if (!itemToDelete.value) return;
  deleting.value = true;
  try {
    await api.deletePlotting(itemToDelete.value.id);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Berhasil Dihapus',
      description: `Plotting kegiatan '${itemToDelete.value.nama_kegiatan}' telah dihapus.`,
      color: 'success',
    });
    isDeleteOpen.value = false;
    await loadData();
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menghapus',
      description: err?.data?.message || 'Data plotting tidak dapat dihapus karena sudah memiliki segmen atau realisasi terkait.',
      color: 'error',
    });
  } finally {
    deleting.value = false;
  }
}

// Formatters
function formatRupiah(val: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

function formatMeter(val: number): string {
  const m = Number(val || 0);
  if (m >= 1000) {
    return `${(m / 1000).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} km`;
  }
  return `${m.toLocaleString('id-ID')} m`;
}

onMounted(() => {
  loadData();
  loadSumberDanaOptions();
});
</script>

<template>
  <div class="space-y-4">
    <!-- Header Page & Primary Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <UIcon name="i-lucide-wallet" class="size-5 text-primary-500" />
          <span>Perencanaan Plotting Pagu Anggaran</span>
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Alokasi pagu dana pembangunan infrastruktur desa dan penetapan target output fisik kegiatan.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          v-if="canManage"
          label="Tambah Plotting"
          icon="i-lucide-plus"
          size="sm"
          color="primary"
          variant="solid"
          @click="openCreateModal"
        />
      </div>
    </div>

    <!-- 1. KPI Metrik Ringkasan -->
    <PlottingMetricsCard
      :total-kegiatan="summary.total_kegiatan"
      :total-pagu="summary.total_pagu_anggaran"
      :total-panjang="summary.total_target_panjang"
      :loading="loading"
    />

    <!-- 2. Filter Multi-Kriteria Toolbar Card -->
    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-3.5 space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
        <!-- Filter Tahun Anggaran -->
        <div>
          <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Tahun Anggaran</label>
          <USelectMenu
            v-model="selectedTahun"
            :items="tahunOptions"
            value-key="value"
            label-key="label"
            size="sm"
            class="w-full"
          />
        </div>

        <!-- Filter Sumber Dana -->
        <div>
          <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Sumber Dana</label>
          <USelectMenu
            v-model="filterSumberDana"
            :items="sumberDanaFilterItems"
            value-key="value"
            label-key="label"
            size="sm"
            class="w-full"
          />
        </div>

        <!-- Filter Nama / Lokasi Search -->
        <div class="sm:col-span-2">
          <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Pencarian Kegiatan</label>
          <UInput
            v-model="searchQuery"
            icon="i-lucide-search"
            placeholder="Ketik nama paket, jenis bantuan, atau lokasi..."
            size="sm"
            class="w-full"
          />
        </div>
      </div>

      <!-- Selector Wilayah Terpadu (Kecamatan -> Desa) -->
      <div class="pt-2 border-t border-gray-100 dark:border-white/[0.04] flex flex-col lg:flex-row lg:items-end justify-between gap-3">
        <div class="flex-1">
          <WilayahSelector
            v-model:modelKecamatan="filterKecamatan"
            v-model:modelDesa="filterDesa"
            allow-all
            all-option-label="Semua"
            size="sm"
            layout="row"
          />
        </div>

        <div class="flex items-center gap-2 self-end pb-0.5">
          <UButton
            label="Reset Filter"
            icon="i-lucide-rotate-ccw"
            color="neutral"
            variant="ghost"
            size="sm"
            @click="handleResetFilters"
          />
          <UButton
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="outline"
            size="sm"
            :loading="loading"
            @click="loadData"
          />
        </div>
      </div>
    </div>

    <!-- 3. Tabel Data Plotting Anggaran -->
    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <!-- Loading Skeleton State -->
      <div v-if="loading" class="p-10 text-center space-y-3">
        <UIcon name="i-lucide-loader-2" class="size-6 animate-spin mx-auto text-primary-500" />
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data perencanaan plotting...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="items.length === 0"
        class="p-12 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
          <UIcon name="i-lucide-wallet" class="size-6 opacity-60" />
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Tidak ada data plotting anggaran</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          Belum ada kegiatan pembangunan yang terdaftar untuk filter atau tahun anggaran yang dipilih.
        </p>
        <div v-if="canManage && !searchQuery.trim()" class="pt-2">
          <UButton
            label="Tambah Plotting Baru"
            icon="i-lucide-plus"
            size="sm"
            color="primary"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- Table Content -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-3 text-center w-20">Tahun</th>
              <th scope="col" class="py-3 px-4 w-48">Wilayah</th>
              <th scope="col" class="py-3 px-4">Nama Kegiatan & Lokasi</th>
              <th scope="col" class="py-3 px-3 text-center w-36">Sumber & Bantuan</th>
              <th scope="col" class="py-3 px-4 text-right w-40">Target Pagu</th>
              <th scope="col" class="py-3 px-4 text-right w-32">Target Fisik</th>
              <th v-if="canManage" scope="col" class="py-3 px-4 text-right w-24">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="row in items"
              :key="row.id"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <!-- Tahun -->
              <td class="py-3 px-3 text-center">
                <UBadge
                  :label="String(row.tahun_anggaran)"
                  color="neutral"
                  variant="subtle"
                  size="xs"
                  class="font-mono font-semibold"
                />
              </td>

              <!-- Wilayah: Kecamatan & Desa -->
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900 dark:text-white">
                  {{ row.desa?.nama_desa || `Desa ID: ${row.id_desa}` }}
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-0.5">
                  <UIcon name="i-lucide-map-pin" class="size-3 text-gray-400 shrink-0" />
                  <span>Kec. {{ row.kecamatan?.nama_kecamatan || row.id_kecamatan }}</span>
                </div>
              </td>

              <!-- Kegiatan & Lokasi -->
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900 dark:text-white line-clamp-2">
                  {{ row.nama_kegiatan }}
                </div>
                <div v-if="row.lokasi_kegiatan" class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">
                  {{ row.lokasi_kegiatan }}
                </div>
              </td>

              <!-- Sumber & Bantuan -->
              <td class="py-3 px-3 text-center space-y-1">
                <UBadge
                  :label="row.sumber_dana"
                  color="primary"
                  variant="subtle"
                  size="xs"
                  class="uppercase tracking-wider font-semibold"
                />
                <div v-if="row.jenis_bantuan" class="text-[10px] text-gray-400 dark:text-gray-500">
                  {{ row.jenis_bantuan }}
                </div>
              </td>

              <!-- Target Pagu (Rp) -->
              <td class="py-3 px-4 text-right">
                <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                  {{ formatRupiah(row.target_pagu_anggaran) }}
                </span>
              </td>

              <!-- Target Fisik (Panjang) -->
              <td class="py-3 px-4 text-right">
                <span class="font-mono font-medium text-gray-900 dark:text-white">
                  {{ formatMeter(row.target_panjang_m) }}
                </span>
              </td>

              <!-- Aksi -->
              <td v-if="canManage" class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <UButton
                    icon="i-lucide-pencil"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    @click="openEditModal(row)"
                  />
                  <UButton
                    icon="i-lucide-trash-2"
                    size="xs"
                    color="error"
                    variant="ghost"
                    @click="confirmDelete(row)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="!loading && totalItems > 0"
        class="p-4 border-t border-gray-200 dark:border-white/[0.08] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400"
      >
        <div>
          Menampilkan baris {{ (page - 1) * perPage + 1 }} sampai
          {{ Math.min(page * perPage, totalItems) }} dari total
          <strong class="text-gray-800 dark:text-gray-200">{{ totalItems }}</strong> kegiatan
        </div>
        <UPagination
          v-model:page="page"
          :total="totalItems"
          :items-per-page="perPage"
          size="sm"
        />
      </div>
    </div>

    <!-- Modal Form Tambah / Edit -->
    <PlottingFormModal
      v-model:open="isFormOpen"
      :is-editing="isEditing"
      :item="selectedItem"
      @success="loadData"
    />

    <!-- Modal Konfirmasi Hapus -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Konfirmasi Hapus Plotting"
      description="Apakah Anda yakin ingin menghapus perencanaan kegiatan ini? Tindakan ini tidak dapat dibatalkan jika belum memiliki segmen fisik terkait."
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Anda akan menghapus plotting kegiatan:
          <strong class="text-gray-900 dark:text-white font-semibold">{{ itemToDelete?.nama_kegiatan }}</strong>
          (Tahun {{ itemToDelete?.tahun_anggaran }}).
        </p>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isDeleteOpen = false"
          />
          <UButton
            label="Hapus Plotting"
            color="error"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

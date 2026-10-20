<script setup lang="ts">
import type { BkkdProposal, BkkdProposalSummary } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import { usePermission } from '~/composables/usePermission';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';
import BkkdProposalFormModal from '~/components/bkkd/BkkdProposalFormModal.vue';
import BkkdVerifikasiFormModal from '~/components/bkkd/BkkdVerifikasiFormModal.vue';
import BkkdAjukanOpdModal from '~/components/bkkd/BkkdAjukanOpdModal.vue';
import BkkdBatalkanModal from '~/components/bkkd/BkkdBatalkanModal.vue';
import BkkdHapusProposalModal from '~/components/bkkd/BkkdHapusProposalModal.vue';
import BkkdImportProposalModal from '~/components/bkkd/BkkdImportProposalModal.vue';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'bkkd-proposal-view',
});

useSeoMeta({
  title: 'Daftar Proposal Usulan BKKD',
});

const toast = useToast();
const { can } = usePermission();
const api = useBkkdApi();

const canCreate = computed(() => can('bkkd-proposal-create'));
const canUpdate = computed(() => can('bkkd-proposal-update'));
const canDelete = computed(() => can('bkkd-proposal-delete'));
const canVerifikasi = computed(() => can('bkkd-verifikasi-manage'));

// Filters & Query
const selectedTahun = ref<number | 'ALL'>('ALL');
const filterBidang = ref<string>('ALL');
const filterKecamatan = ref<number | null>(null);
const filterDesa = ref<number | null>(null);
const filterStatus = ref<string>('ALL');
const searchQuery = ref('');
const page = ref(1);
const perPage = ref(15);

const bidangOptions = ref<{ label: string; value: string }[]>([
  { label: 'Semua Bidang', value: 'ALL' },
]);

const items = ref<BkkdProposal[]>([]);
const totalItems = ref(0);
const loading = ref(false);

const summary = reactive<BkkdProposalSummary>({
  total_proposal: 0,
  total_nilai_usulan: 0,
  draft: 0,
  diajukan_opd: 0,
  layak: 0,
  layak_bersyarat: 0,
  tidak_layak: 0,
  ditetapkan_tapd: 0,
});

const tahunOptions = getTahunAnggaranOptions(true);

const statusOptions = [
  { label: 'Semua Status', value: 'ALL' },
  { label: 'Draft', value: 'draft' },
  { label: 'Diajukan ke OPD', value: 'diajukan_opd' },
  { label: 'Layak', value: 'layak' },
  { label: 'Layak Bersyarat', value: 'layak_bersyarat' },
  { label: 'Tidak Layak', value: 'tidak_layak' },
  { label: 'Dikembalikan', value: 'dikembalikan' },
  { label: 'Ditetapkan TAPD', value: 'ditetapkan_tapd' },
  { label: 'Tidak Terpilih', value: 'tidak_terpilih' },
  { label: 'Digantikan di P-APBD', value: 'digantikan_papbd' },
  { label: 'Dibatalkan', value: 'dibatalkan' },
];

// Modal States
const modalFormOpen = ref(false);
const isEditing = ref(false);
const activeItem = ref<BkkdProposal | null>(null);

const modalVerifikasiOpen = ref(false);
const activeProposalForVerif = ref<BkkdProposal | null>(null);

const modalAjukanOpen = ref(false);
const activeProposalForAjukan = ref<BkkdProposal | null>(null);

const modalBatalkanOpen = ref(false);
const activeProposalForBatalkan = ref<BkkdProposal | null>(null);

const modalHapusOpen = ref(false);
const activeProposalForHapus = ref<BkkdProposal | null>(null);

const modalImportOpen = ref(false);

function formatCurrency(val: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

function formatVolume(val?: number | string | null, unit?: string | null) {
  if (val === null || val === undefined || val === '') return '-';
  const num = Number(val);
  if (isNaN(num)) return '-';
  const formatted = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 2,
  }).format(num);
  return unit ? `${formatted} ${unit}` : formatted;
}

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
    if (filterBidang.value !== 'ALL') {
      params.id_bidang = filterBidang.value;
    }
    if (filterKecamatan.value) {
      params.id_kecamatan = filterKecamatan.value;
    }
    if (filterDesa.value) {
      params.id_desa = filterDesa.value;
    }
    if (filterStatus.value !== 'ALL') {
      params.status = filterStatus.value;
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    const res = await api.fetchProposals(params);
    items.value = res.data || [];
    totalItems.value = res.meta?.total || 0;

    if (res.summary) {
      Object.assign(summary, res.summary);
    }
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Data',
      description: err?.data?.message || 'Terjadi kesalahan saat memuat proposal BKKD.',
      color: 'error',
    });
  } finally {
    loading.value = false;
  }
}

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

watch([selectedTahun, filterBidang, filterKecamatan, filterDesa, filterStatus], () => {
  page.value = 1;
  loadData();
});

let searchDebounce: NodeJS.Timeout;
function onSearchInput() {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    page.value = 1;
    loadData();
  }, 400);
}

function openCreateModal() {
  activeItem.value = null;
  isEditing.value = false;
  modalFormOpen.value = true;
}

function openEditModal(item: BkkdProposal) {
  activeItem.value = item;
  isEditing.value = true;
  modalFormOpen.value = true;
}

function openVerifikasiModal(item: BkkdProposal) {
  activeProposalForVerif.value = item;
  modalVerifikasiOpen.value = true;
}

function handleAjukanOpd(item: BkkdProposal) {
  activeProposalForAjukan.value = item;
  modalAjukanOpen.value = true;
}

function handleBatalkan(item: BkkdProposal) {
  activeProposalForBatalkan.value = item;
  modalBatalkanOpen.value = true;
}

function handleDelete(item: BkkdProposal) {
  activeProposalForHapus.value = item;
  modalHapusOpen.value = true;
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
          <UIcon name="i-lucide-file-text" class="w-6 h-6 text-primary-500" />
          <span>Proposal Usulan BKKD</span>
        </h1>
        <p class="text-xs text-gray-500 mt-1">
          Pencatatan dan pemantauan proposal usulan Bantuan Keuangan Khusus Desa per desa di Kabupaten Bojonegoro.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          v-if="canCreate"
          color="neutral"
          variant="outline"
          icon="i-lucide-file-spreadsheet"
          label="Import Excel"
          @click="modalImportOpen = true"
        />
        <UButton
          v-if="canCreate"
          color="primary"
          icon="i-lucide-plus"
          label="Tambah Usulan Proposal"
          @click="openCreateModal"
        />
      </div>
    </div>

    <!-- Statistik Metrik Kartu -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-[11px] font-medium text-gray-500">Total Usulan</span>
        <div class="text-lg font-bold font-mono text-gray-900 dark:text-gray-100 mt-0.5">
          {{ summary.total_proposal }}
        </div>
        <span class="text-[11px] text-primary-600 dark:text-primary-400 font-mono">
          {{ formatCurrency(summary.total_nilai_usulan) }}
        </span>
      </div>

      <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-[11px] font-medium text-gray-500">Draft</span>
        <div class="text-lg font-bold font-mono text-gray-700 dark:text-gray-300 mt-0.5">
          {{ summary.draft }}
        </div>
        <span class="text-[11px] text-gray-400">Dalam penyusunan</span>
      </div>

      <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-[11px] font-medium text-blue-600 dark:text-blue-400">Diajukan ke OPD</span>
        <div class="text-lg font-bold font-mono text-blue-600 dark:text-blue-400 mt-0.5">
          {{ summary.diajukan_opd }}
        </div>
        <span class="text-[11px] text-gray-400">Menunggu telaah</span>
      </div>

      <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Layak (OPD)</span>
        <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
          {{ summary.layak }}
        </div>
        <span class="text-[11px] text-gray-400">Memenuhi syarat</span>
      </div>

      <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-[11px] font-medium text-amber-600 dark:text-amber-400">Layak Bersyarat</span>
        <div class="text-lg font-bold font-mono text-amber-600 dark:text-amber-400 mt-0.5">
          {{ summary.layak_bersyarat }}
        </div>
        <span class="text-[11px] text-gray-400">Perlu penyesuaian</span>
      </div>

      <div class="p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-xs">
        <span class="text-[11px] font-medium text-emerald-700 dark:text-emerald-400">Ditetapkan TAPD</span>
        <div class="text-lg font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-0.5">
          {{ summary.ditetapkan_tapd }}
        </div>
        <span class="text-[11px] text-emerald-600 dark:text-emerald-400">Masuk RAPBD</span>
      </div>
    </div>

    <!-- Filter Bar -->
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
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Bidang BKKD</label>
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
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Pencarian Usulan</label>
          <UInput
            v-model="searchQuery"
            placeholder="Cari nomor registrasi, judul kegiatan..."
            icon="i-lucide-search"
            class="w-full text-xs"
            size="sm"
            @input="onSearchInput"
          />
        </div>
      </div>

      <!-- Wilayah Selector -->
      <div class="pt-2 border-t border-gray-100 dark:border-white/[0.04]">
        <WilayahSelector
          v-model:modelKecamatan="filterKecamatan"
          v-model:modelDesa="filterDesa"
          allow-all
          layout="row"
          size="sm"
        />
      </div>
    </div>

    <!-- Tabel Daftar Proposal -->
    <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400">
              <th class="p-3 font-semibold">No. Registrasi</th>
              <th class="p-3 font-semibold">Wilayah Desa</th>
              <th class="p-3 font-semibold">Bidang BKKD</th>
              <th class="p-3 font-semibold">Judul Kegiatan & Lokasi</th>
              <th class="p-3 font-semibold text-right">Nilai Usulan</th>
              <th class="p-3 font-semibold text-center min-w-[130px]">Status</th>
              <th class="p-3 font-semibold text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr v-if="loading">
              <td colspan="7" class="p-8 text-center text-gray-500">
                <UIcon name="i-lucide-loader-2" class="w-6 h-6 animate-spin mx-auto text-primary-500 mb-2" />
                Memuat data proposal usulan...
              </td>
            </tr>

            <tr v-else-if="items.length === 0">
              <td colspan="7" class="p-8 text-center text-gray-500">
                <UIcon name="i-lucide-inbox" class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                Tidak ditemukan usulan proposal BKKD sesuai filter pencarian.
              </td>
            </tr>

            <tr
              v-for="item in items"
              :key="item.id"
              class="hover:bg-gray-50/60 dark:hover:bg-gray-800/20 transition-colors"
            >
              <td class="p-3 font-mono font-semibold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                <NuxtLink :to="`/admin/bkkd/proposal/${item.id}`" class="hover:underline flex items-center gap-1">
                  <span>{{ item.nomor_registrasi }}</span>
                  <UIcon name="i-lucide-external-link" class="w-3 h-3 text-gray-400" />
                </NuxtLink>
                <div class="text-[10px] text-gray-400 font-normal">T.A. {{ item.tahun_anggaran }} • v{{ item.versi }}</div>
              </td>

              <td class="p-3 whitespace-nowrap">
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ item.nama_desa }}</div>
                <div class="text-[11px] text-gray-500">Kec. {{ item.nama_kecamatan }}</div>
              </td>

              <td class="p-3 whitespace-nowrap">
                <UBadge color="primary" variant="subtle" size="xs" class="font-medium inline-flex items-center gap-1">
                  <UIcon name="i-lucide-layers" class="w-3 h-3 shrink-0" />
                  <span>{{ item.nama_bidang || item.bidang?.nama || '-' }}</span>
                </UBadge>
              </td>

              <td class="p-3 max-w-xs">
                <NuxtLink :to="`/admin/bkkd/proposal/${item.id}`" class="font-medium text-gray-900 dark:text-gray-100 hover:text-primary-600 line-clamp-1">
                  {{ item.judul_kegiatan }}
                </NuxtLink>
                <div class="text-[11px] text-gray-500 truncate mt-0.5">
                  <UIcon name="i-lucide-map-pin" class="w-3 h-3 inline mr-0.5 text-gray-400" />
                  {{ item.lokasi_kegiatan }}
                </div>
              </td>

              <td class="p-3 text-right whitespace-nowrap font-mono font-medium text-gray-900 dark:text-gray-100">
                <span v-if="item.nilai_usulan !== null && item.nilai_usulan !== undefined">{{ formatCurrency(item.nilai_usulan) }}</span>
                <span v-else class="text-gray-400 font-sans font-normal">-</span>
              </td>

              <td class="p-3 text-center whitespace-nowrap">
                <BkkdStatusBadge :status="item.status" size="sm" />
              </td>

              <td class="p-3 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1">
                  <NuxtLink :to="`/admin/bkkd/proposal/${item.id}`">
                    <UButton
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      icon="i-lucide-eye"
                      title="Lihat Detail"
                    />
                  </NuxtLink>

                  <NuxtLink :to="`/admin/bkkd/proposal/${item.id}?tab=linimasa`">
                    <UButton
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      icon="i-lucide-history"
                      title="Lihat Linimasa Usulan"
                    />
                  </NuxtLink>

                  <UButton
                    v-if="canUpdate"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-edit"
                    title="Edit Usulan"
                    @click="openEditModal(item)"
                  />

                  <!-- Aksi Ajukan ke OPD jika draft -->
                  <UButton
                    v-if="canUpdate && item.status === 'draft'"
                    color="info"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-send"
                    title="Ajukan ke OPD Teknis"
                    @click="handleAjukanOpd(item)"
                  />

                  <!-- Aksi Catat Verifikasi jika diajukan_opd -->
                  <UButton
                    v-if="canVerifikasi && ['diajukan_opd', 'dikembalikan'].includes(item.status)"
                    color="primary"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-clipboard-check"
                    title="Catat Hasil Telaah OPD"
                    @click="openVerifikasiModal(item)"
                  />

                  <!-- Aksi Batalkan Usulan -->
                  <UButton
                    v-if="canUpdate && !['ditetapkan_tapd', 'dibatalkan'].includes(item.status)"
                    color="warning"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-ban"
                    title="Batalkan Usulan"
                    @click="handleBatalkan(item)"
                  />

                  <!-- Aksi Hapus Proposal (soft delete untuk draft atau dibatalkan) -->
                  <UButton
                    v-if="canDelete && ['draft', 'dibatalkan'].includes(item.status)"
                    color="error"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-trash-2"
                    title="Hapus Proposal"
                    @click="handleDelete(item)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-3 border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-between text-xs text-gray-500">
        <div>
          Menampilkan {{ items.length }} dari {{ totalItems }} data usulan
        </div>
        <div class="flex items-center gap-2">
          <UButton
            color="neutral"
            variant="outline"
            size="xs"
            label="Sebelumnya"
            :disabled="page <= 1 || loading"
            @click="page--; loadData()"
          />
          <span class="font-mono">{{ page }}</span>
          <UButton
            color="neutral"
            variant="outline"
            size="xs"
            label="Selanjutnya"
            :disabled="page * perPage >= totalItems || loading"
            @click="page++; loadData()"
          />
        </div>
      </div>
    </div>

    <!-- Modals -->
    <BkkdProposalFormModal
      v-model:open="modalFormOpen"
      :is-editing="isEditing"
      :item="activeItem"
      @success="loadData"
    />

    <BkkdVerifikasiFormModal
      v-model:open="modalVerifikasiOpen"
      :proposal="activeProposalForVerif"
      @success="loadData"
    />

    <BkkdAjukanOpdModal
      v-model:open="modalAjukanOpen"
      :proposal="activeProposalForAjukan"
      @success="loadData"
    />

    <BkkdBatalkanModal
      v-model:open="modalBatalkanOpen"
      :proposal="activeProposalForBatalkan"
      @success="loadData"
    />

    <BkkdHapusProposalModal
      v-model:open="modalHapusOpen"
      :proposal="activeProposalForHapus"
      @success="loadData"
    />

    <BkkdImportProposalModal
      v-model:open="modalImportOpen"
      @success="loadData"
    />
  </div>
</template>

<script setup lang="ts">
import type { BkkdVerifikasi, RefOpd, BkkdRiwayat } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import { usePermission } from '~/composables/usePermission';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';
import BkkdVerifikasiFormModal from '~/components/bkkd/BkkdVerifikasiFormModal.vue';
import BkkdVerifikasiKolektifModal from '~/components/bkkd/BkkdVerifikasiKolektifModal.vue';
import BkkdRiwayatTimeline from '~/components/bkkd/BkkdRiwayatTimeline.vue';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'bkkd-verifikasi-view',
});

useSeoMeta({
  title: 'Hasil Verifikasi OPD Teknis BKKD',
});

const toast = useToast();
const { can } = usePermission();
const api = useBkkdApi();

const canManageVerifikasi = computed(() => can('bkkd-verifikasi-manage'));

// Filters
const selectedTahun = ref<number | 'ALL'>('ALL');
const filterOpd = ref<string>('ALL');
const filterHasil = ref<string>('ALL');
const showSuperseded = ref(false);
const showDeletedProposal = ref(false);
const searchQuery = ref('');
const page = ref(1);
const perPage = ref(15);

const items = ref<BkkdVerifikasi[]>([]);
const totalItems = ref(0);
const loading = ref(false);
const opdList = ref<RefOpd[]>([]);

// Modals
const modalKolektifOpen = ref(false);
const modalEditOpen = ref(false);
const activeVerifikasi = ref<BkkdVerifikasi | null>(null);

const modalRiwayatOpen = ref(false);
const riwayatVerifikasiList = ref<BkkdRiwayat[]>([]);
const loadingRiwayat = ref(false);

const tahunOptions = getTahunAnggaranOptions(true);

const hasilOptions = [
  { label: 'Semua Hasil', value: 'ALL' },
  { label: 'Layak', value: 'layak' },
  { label: 'Layak Bersyarat', value: 'layak_bersyarat' },
  { label: 'Tidak Layak', value: 'tidak_layak' },
  { label: 'Dikembalikan', value: 'dikembalikan' },
];

const opdOptions = computed(() => [
  { label: 'Semua OPD', value: 'ALL' },
  ...opdList.value.map((o) => ({
    label: `${o.nama} (${o.kode})`,
    value: o.id,
  })),
]);

function formatCurrency(val: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

function formatDate(iso: string) {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID', {
    dateStyle: 'medium',
  });
}

async function loadOpdList() {
  try {
    const res = await api.fetchRefOpd();
    opdList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat OPD:', err);
  }
}

async function loadData() {
  loading.value = true;
  try {
    const params: Record<string, any> = {
      page: page.value,
      per_page: perPage.value,
      show_superseded: showSuperseded.value,
      show_deleted_proposal: showDeletedProposal.value,
    };

    if (selectedTahun.value !== 'ALL') {
      params.tahun_anggaran = selectedTahun.value;
    }
    if (filterOpd.value !== 'ALL') {
      params.id_opd = filterOpd.value;
    }
    if (filterHasil.value !== 'ALL') {
      params.hasil = filterHasil.value;
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    const res = await api.fetchVerifikasiList(params);
    items.value = res.data || [];
    totalItems.value = res.meta?.total || 0;
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Data',
      description: err?.data?.message || 'Terjadi kesalahan sistem.',
      color: 'error',
    });
  } finally {
    loading.value = false;
  }
}

watch([selectedTahun, filterOpd, filterHasil, showSuperseded, showDeletedProposal], () => {
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

function openEdit(verif: BkkdVerifikasi) {
  activeVerifikasi.value = verif;
  modalEditOpen.value = true;
}

async function openRiwayat(verif: BkkdVerifikasi) {
  activeVerifikasi.value = verif;
  modalRiwayatOpen.value = true;
  loadingRiwayat.value = true;
  try {
    const res = await api.getVerifikasiRiwayat(verif.id);
    riwayatVerifikasiList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat riwayat:', err);
  } finally {
    loadingRiwayat.value = false;
  }
}

onMounted(() => {
  loadOpdList();
  loadData();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
          <UIcon name="i-lucide-clipboard-check" class="w-6 h-6 text-primary-500" />
          <span>Hasil Verifikasi OPD Teknis</span>
        </h1>
        <p class="text-xs text-gray-500 mt-1">
          Daftar berita acara dan rekomendasi hasil telaah teknis proposal BKKD oleh dinas terkait (DPU Bina Marga, Perkim, DPMD).
        </p>
      </div>

      <div v-if="canManageVerifikasi" class="flex items-center gap-2">
        <UButton
          color="primary"
          variant="solid"
          icon="i-lucide-layers"
          label="Verifikasi Kolektif (1 Dokumen Banyak Desa)"
          size="sm"
          @click="modalKolektifOpen = true"
        />
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
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">OPD Teknis</label>
          <USelectMenu
            v-model="filterOpd"
            :items="opdOptions"
            value-key="value"
            label-key="label"
            class="w-full text-xs"
            size="sm"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hasil Verifikasi</label>
          <USelectMenu
            v-model="filterHasil"
            :items="hasilOptions"
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
            placeholder="Cari nomor surat telaah, judul usulan..."
            icon="i-lucide-search"
            class="w-full text-xs"
            size="sm"
            @input="onSearchInput"
          />
        </div>
      </div>

      <div class="pt-2 border-t border-gray-100 dark:border-white/[0.04] flex flex-wrap items-center justify-between gap-4 text-xs">
        <label class="flex items-center gap-2 cursor-pointer text-gray-600 dark:text-gray-400">
          <input
            v-model="showSuperseded"
            type="checkbox"
            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
          />
          <span>Tampilkan juga telaah usang yang pernah dikoreksi (Superseded)</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer text-gray-600 dark:text-gray-400">
          <input
            v-model="showDeletedProposal"
            type="checkbox"
            class="rounded border-gray-300 text-rose-600 focus:ring-rose-500"
          />
          <span :class="{ 'font-semibold text-rose-600 dark:text-rose-400': showDeletedProposal }">
            Tampilkan juga telaah dari usulan yang dihapus (Soft Delete)
          </span>
        </label>
      </div>
    </div>

    <!-- Tabel Verifikasi -->
    <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400">
              <th class="p-3 font-semibold">Surat Hasil Telaah</th>
              <th class="p-3 font-semibold">Usulan & Wilayah Desa</th>
              <th class="p-3 font-semibold">OPD Teknis</th>
              <th class="p-3 font-semibold">Tanggal</th>
              <th class="p-3 font-semibold text-center">Hasil Telaah</th>
              <th class="p-3 font-semibold text-right">Rekomendasi Anggaran</th>
              <th class="p-3 font-semibold text-center">Dokumen Berkas</th>
              <th class="p-3 font-semibold text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr v-if="loading">
              <td colspan="8" class="p-8 text-center text-gray-500">
                <UIcon name="i-lucide-loader-2" class="w-6 h-6 animate-spin mx-auto text-primary-500 mb-2" />
                Memuat data verifikasi OPD...
              </td>
            </tr>

            <tr v-else-if="items.length === 0">
              <td colspan="8" class="p-8 text-center text-gray-500">
                <UIcon name="i-lucide-inbox" class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                Tidak ditemukan hasil verifikasi OPD sesuai filter.
              </td>
            </tr>

            <tr
              v-for="item in items"
              :key="item.id"
              class="hover:bg-gray-50/60 dark:hover:bg-gray-800/20 transition-colors"
              :class="{
                'opacity-60 bg-gray-50/40 dark:bg-gray-900/20': !item.is_aktif,
                'bg-rose-50/20 dark:bg-rose-950/10 border-l-4 border-l-rose-500': item.proposal?.is_deleted || item.is_proposal_deleted,
              }"
            >
              <td class="p-3 font-mono font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <span>{{ item.nomor_surat_hasil }}</span>
                  <UBadge
                    v-if="item.is_kolektif || item.id_dokumen"
                    color="neutral"
                    size="xs"
                    variant="subtle"
                    class="text-[10px]"
                    title="Diverifikasi melalui Berita Acara Kolektif"
                  >
                    Kolektif
                  </UBadge>
                </div>
                <div class="flex items-center gap-1.5 text-[10px] text-gray-400 mt-0.5">
                  <span>v{{ item.versi }}</span>
                  <span v-if="!item.is_aktif" class="text-amber-600 dark:text-amber-400 font-semibold">(Tergantikan)</span>
                </div>
              </td>

              <td class="p-3 max-w-xs">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <NuxtLink
                    v-if="item.proposal"
                    :to="`/admin/bkkd/proposal/${item.proposal.id}`"
                    class="font-medium text-primary-600 dark:text-primary-400 hover:underline line-clamp-1"
                    :class="{ 'line-through text-gray-400': item.proposal.is_deleted || item.is_proposal_deleted }"
                  >
                    {{ item.proposal.nomor_registrasi }}
                  </NuxtLink>
                  <UBadge v-if="item.proposal?.is_deleted || item.is_proposal_deleted" color="error" size="xs" variant="subtle" class="font-semibold text-[10px]">
                    Usulan Dihapus
                  </UBadge>
                  <UBadge v-if="item.proposal?.nama_bidang || item.proposal?.bidang?.nama" color="primary" size="xs" variant="subtle" class="font-medium text-[10px]">
                    {{ item.proposal?.nama_bidang || item.proposal?.bidang?.nama }}
                  </UBadge>
                </div>
                <div class="text-[11px] text-gray-700 dark:text-gray-300 truncate" :class="{ 'line-through text-gray-400': item.proposal?.is_deleted || item.is_proposal_deleted }">
                  {{ item.proposal?.judul_kegiatan }}
                </div>
                <div class="text-[10px] text-gray-500 mt-0.5">
                  <span v-if="item.proposal?.nama_desa || item.proposal?.nama_kecamatan">
                    Desa {{ item.proposal?.nama_desa || '-' }}, Kec. {{ item.proposal?.nama_kecamatan || '-' }}
                  </span>
                  <span v-else class="text-gray-400">Wilayah belum terdata</span>
                  <span v-if="item.proposal?.deleted_at" class="text-rose-500 font-medium ml-1">
                    • Dihapus: {{ formatDate(item.proposal.deleted_at) }}
                  </span>
                </div>
              </td>

              <td class="p-3 whitespace-nowrap">
                <div class="font-medium text-gray-800 dark:text-gray-200">
                  {{ item.opd?.nama || item.nama_opd || 'OPD Teknis' }}
                </div>
                <div v-if="item.opd?.kode || item.kode_opd" class="text-[10px] text-gray-400 font-mono">
                  {{ item.opd?.kode || item.kode_opd }}
                </div>
              </td>

              <td class="p-3 whitespace-nowrap text-gray-600 dark:text-gray-400 font-mono">
                <div>{{ formatDate(item.tanggal_verifikasi || item.tanggal_surat_hasil || item.tanggal_diterima) }}</div>
                <div v-if="item.tanggal_diterima && item.tanggal_diterima !== (item.tanggal_verifikasi || item.tanggal_surat_hasil)" class="text-[10px] text-gray-400">
                  Diterima: {{ formatDate(item.tanggal_diterima) }}
                </div>
              </td>

              <td class="p-3 text-center whitespace-nowrap">
                <BkkdStatusBadge :status="item.hasil" size="sm" />
              </td>

              <td class="p-3 text-right whitespace-nowrap font-mono font-medium text-emerald-600 dark:text-emerald-400">
                <span v-if="(item.rekomendasi_anggaran ?? item.nilai_rekomendasi) !== null && (item.rekomendasi_anggaran ?? item.nilai_rekomendasi) !== undefined">
                  {{ formatCurrency(item.rekomendasi_anggaran ?? item.nilai_rekomendasi) }}
                </span>
                <span v-else class="text-gray-400 font-normal">-</span>
                <div v-if="item.volume_rekomendasi" class="text-[10px] text-gray-500 font-sans font-normal mt-0.5">
                  Vol: {{ item.volume_rekomendasi }} {{ item.proposal?.satuan || '' }}
                </div>
                <div v-if="item.panjang_rekomendasi || item.lebar_rekomendasi || item.tinggi_rekomendasi" class="text-[10px] text-gray-400 font-mono">
                  {{ item.panjang_rekomendasi ? `${item.panjang_rekomendasi}m` : '-' }} × {{ item.lebar_rekomendasi ? `${item.lebar_rekomendasi}m` : '-' }} × {{ item.tinggi_rekomendasi ? `${item.tinggi_rekomendasi}m` : '-' }}
                </div>
              </td>

              <td class="p-3 text-center whitespace-nowrap">
                <a
                  v-if="item.tipe_sumber === 'link' || item.url_link"
                  :href="item.url_link || api.getVerifikasiDownloadUrl(item.id)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-medium text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 dark:hover:bg-sky-900/40 border border-sky-200 dark:border-sky-800 transition min-h-[28px]"
                  :title="item.nama_dokumen_asli || 'Buka tautan cloud dokumen'"
                >
                  <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5" />
                  <span>Cloud Link</span>
                </a>
                <a
                  v-else-if="item.has_dokumen"
                  :href="api.getVerifikasiDownloadUrl(item.id)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/40 hover:bg-primary-100 dark:hover:bg-primary-900/40 border border-primary-200 dark:border-primary-800 transition min-h-[28px]"
                  :title="item.nama_dokumen_asli || 'Unduh berkas fisik'"
                >
                  <UIcon name="i-lucide-download" class="w-3.5 h-3.5" />
                  <span>Berkas</span>
                </a>
                <span v-else class="text-gray-400 text-[11px]">-</span>
              </td>

              <td class="p-3 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1">
                  <UButton
                    v-if="canManageVerifikasi && item.is_aktif && !(item.proposal?.is_deleted || item.is_proposal_deleted)"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-edit-3"
                    title="Koreksi Verifikasi"
                    @click="openEdit(item)"
                  />
                  <span
                    v-else-if="item.proposal?.is_deleted || item.is_proposal_deleted"
                    class="text-[11px] text-rose-500 font-medium px-1.5 py-0.5 rounded bg-rose-50 dark:bg-rose-950/40"
                    title="Usulan telah dihapus sehingga verifikasi tidak dapat diubah"
                  >
                    Terkunci
                  </span>

                  <UButton
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-history"
                    title="Riwayat Audit Telaah"
                    @click="openRiwayat(item)"
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
          Menampilkan {{ items.length }} dari {{ totalItems }} verifikasi
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

    <!-- Modal Koreksi Verifikasi -->
    <BkkdVerifikasiFormModal
      v-model:open="modalEditOpen"
      :verifikasi="activeVerifikasi"
      :proposal="activeVerifikasi?.proposal"
      :is-editing="true"
      @success="loadData"
    />

    <!-- Modal Verifikasi Kolektif (1 Dokumen Banyak Desa) -->
    <BkkdVerifikasiKolektifModal
      v-model:open="modalKolektifOpen"
      @success="loadData"
    />

    <!-- Modal Riwayat Audit Verifikasi -->
    <UModal
      v-model:open="modalRiwayatOpen"
      title="Riwayat Audit Verifikasi OPD"
      :description="activeVerifikasi ? `Nomor Telaah: ${activeVerifikasi.nomor_surat_hasil}` : ''"
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <BkkdRiwayatTimeline
          :riwayat-list="riwayatVerifikasiList"
          :loading="loadingRiwayat"
        />
      </template>
    </UModal>
  </div>
</template>

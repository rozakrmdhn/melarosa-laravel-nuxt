<script setup lang="ts">
import type { BkkdProposal, BkkdRekapitulasiDesaItem, BkkdRekapitulasiSummary } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import { usePermission } from '~/composables/usePermission';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';
import BkkdPenetapanTapdModal from '~/components/bkkd/BkkdPenetapanTapdModal.vue';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'bkkd-proposal-view',
});

useSeoMeta({
  title: 'Rekapitulasi Usulan Desa & Penetapan TAPD BKKD',
});

const toast = useToast();
const { can } = usePermission();
const api = useBkkdApi();

const canUpdate = computed(() => can('bkkd-proposal-update'));

const selectedTahun = ref<number>(new Date().getFullYear() + 1);
const filterKecamatan = ref<number | null>(null);
const filterDesa = ref<number | null>(null);

const desaList = ref<BkkdRekapitulasiDesaItem[]>([]);
const loading = ref(false);

const summary = reactive<BkkdRekapitulasiSummary>({
  total_desa_pengusul: 0,
  total_usulan_masuk: 0,
  desa_terakomodir_tapd: 0,
  total_pagu_rancangan_apbd: 0,
});

// Modals
const modalTapdOpen = ref(false);
const activeProposalForTapd = ref<BkkdProposal | null>(null);

const tahunOptions = getTahunAnggaranOptions(false);

function formatCurrency(val: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

async function loadData() {
  loading.value = true;
  try {
    const params: Record<string, any> = {
      tahun_anggaran: selectedTahun.value,
    };
    if (filterKecamatan.value) {
      params.id_kecamatan = filterKecamatan.value;
    }
    if (filterDesa.value) {
      params.id_desa = filterDesa.value;
    }

    const res = await api.fetchRekapitulasiDesa(params);
    desaList.value = res.data || [];
    if (res.summary) {
      Object.assign(summary, res.summary);
    }
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Rekapitulasi',
      description: err?.data?.message || 'Terjadi kesalahan sistem.',
      color: 'error',
    });
  } finally {
    loading.value = false;
  }
}

watch([selectedTahun, filterKecamatan, filterDesa], () => {
  loadData();
});

function openTapdModal(proposal: BkkdProposal) {
  activeProposalForTapd.value = proposal;
  modalTapdOpen.value = true;
}

async function handleBatalkanTapd(proposal: BkkdProposal) {
  const alasan = prompt(`Batalkan penetapan TAPD untuk usulan "${proposal.nomor_registrasi}"? Berikan alasan:`);
  if (alasan === null) return;

  try {
    await api.batalkanTapd(proposal.id, alasan.trim() || undefined);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Penetapan Dibatalkan',
      description: `Usulan '${proposal.nomor_registrasi}' dikembalikan statusnya ke layak.`,
      color: 'success',
    });
    loadData();
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Membatalkan',
      description: err?.data?.message || 'Terjadi kesalahan.',
      color: 'error',
    });
  }
}

function handleExportExcel() {
  const params: Record<string, any> = {
    tahun_anggaran: selectedTahun.value,
  };
  if (filterKecamatan.value) {
    params.id_kecamatan = filterKecamatan.value;
  }
  if (filterDesa.value) {
    params.id_desa = filterDesa.value;
  }
  const url = api.getRekapitulasiExportUrl(params);
  window.open(url, '_blank');
}

onMounted(() => {
  loadData();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
          <UIcon name="i-lucide-calculator" class="w-6 h-6 text-primary-500" />
          <span>Rekapitulasi Usulan & Penetapan TAPD</span>
        </h1>
        <p class="text-xs text-gray-500 mt-1">
          Penetapan usulan prioritas per desa oleh Tim Anggaran Pemerintah Daerah (TAPD) untuk alokasi Rancangan APBD (Maksimal 1 Usulan per Desa).
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          color="neutral"
          variant="outline"
          icon="i-lucide-file-spreadsheet"
          label="Ekspor Excel Rekapitulasi"
          @click="handleExportExcel"
        />
      </div>
    </div>

    <!-- Statistik Metrik Kartu -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-xs font-medium text-gray-500">Desa Pengusul</span>
        <div class="text-2xl font-bold font-mono text-gray-900 dark:text-gray-100 mt-1">
          {{ summary.total_desa_pengusul }} <span class="text-xs font-normal text-gray-400">desa</span>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs">
        <span class="text-xs font-medium text-gray-500">Total Usulan Masuk</span>
        <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 mt-1">
          {{ summary.total_usulan_masuk }} <span class="text-xs font-normal text-gray-400">proposal</span>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-xs">
        <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Desa Terakomodir TAPD</span>
        <div class="text-2xl font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-1">
          {{ summary.desa_terakomodir_tapd }} <span class="text-xs font-normal text-emerald-600">desa</span>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-xs">
        <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Total Pagu Rancangan APBD</span>
        <div class="text-lg font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-1 truncate">
          {{ formatCurrency(summary.total_pagu_rancangan_apbd) }}
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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

        <div class="sm:col-span-2">
          <WilayahSelector
            v-model:modelKecamatan="filterKecamatan"
            v-model:modelDesa="filterDesa"
            allow-all
            layout="row"
            size="sm"
          />
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center text-gray-500 text-xs">
      <UIcon name="i-lucide-loader-2" class="w-8 h-8 animate-spin mx-auto text-primary-500 mb-2" />
      Memuat matriks rekapitulasi desa...
    </div>

    <!-- Empty State -->
    <div
      v-else-if="desaList.length === 0"
      class="p-12 text-center border border-dashed border-gray-200 dark:border-white/[0.08] rounded-xl text-gray-500 text-xs"
    >
      <UIcon name="i-lucide-folder-open" class="w-10 h-10 mx-auto text-gray-400 mb-2" />
      <p class="font-medium text-gray-700 dark:text-gray-300">Belum Ada Usulan Desa</p>
      <p class="text-gray-400 mt-1">Tidak ditemukan usulan proposal BKKD pada tahun anggaran dan wilayah terpilih.</p>
    </div>

    <!-- Matrix Daftar Desa dan Proposalnya -->
    <div v-else class="space-y-4">
      <div
        v-for="desa in desaList"
        :key="desa.id_desa"
        class="rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs overflow-hidden"
      >
        <!-- Baris Header Desa -->
        <div class="p-4 bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-white/[0.06] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-map-pin" class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                Desa {{ desa.nama_desa }}
              </h3>
              <p class="text-xs text-gray-500">
                Kecamatan {{ desa.nama_kecamatan }} • Total {{ desa.jumlah_usulan }} usulan terdaftar
              </p>
            </div>
          </div>

          <!-- Status TAPD Desa -->
          <div class="flex items-center gap-3">
            <div v-if="desa.ada_usulan_ditetapkan" class="text-right">
              <UBadge color="success" size="sm" variant="subtle" class="inline-flex items-center gap-1 font-semibold">
                <UIcon name="i-lucide-check" class="w-3.5 h-3.5" />
                <span>Sudah Ditetapkan TAPD</span>
              </UBadge>
              <div class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                Pagu APBD: {{ formatCurrency(desa.nilai_disetujui_tapd || 0) }}
              </div>
            </div>
            <div v-else>
              <UBadge color="neutral" size="sm" variant="outline" class="text-gray-500">
                Belum Ada Usulan Terpilih
              </UBadge>
            </div>
          </div>
        </div>

        <!-- Tabel Usulan dari Desa Ini -->
        <div class="divide-y divide-gray-100 dark:divide-white/[0.04]">
          <div
            v-for="prop in desa.proposals"
            :key="prop.id"
            class="p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3 hover:bg-gray-50/40 dark:hover:bg-gray-800/20 transition-colors"
            :class="{ 'bg-emerald-50/30 dark:bg-emerald-950/10 border-l-4 border-l-emerald-500': prop.status === 'ditetapkan_tapd' }"
          >
            <div class="space-y-1 max-w-2xl">
              <div class="flex flex-wrap items-center gap-2">
                <NuxtLink :to="`/admin/bkkd/proposal/${prop.id}`" class="text-xs font-mono font-bold text-primary-600 dark:text-primary-400 hover:underline">
                  {{ prop.nomor_registrasi }}
                </NuxtLink>
                <BkkdStatusBadge :status="prop.status" size="sm" />
                <UBadge color="primary" size="xs" variant="subtle" class="font-medium inline-flex items-center gap-1">
                  <UIcon name="i-lucide-layers" class="w-3 h-3 shrink-0" />
                  <span>{{ prop.nama_bidang || prop.bidang?.nama || '-' }}</span>
                </UBadge>
              </div>

              <h4 class="text-xs font-semibold text-gray-900 dark:text-gray-100">
                {{ prop.judul_kegiatan }}
              </h4>

              <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500">
                <span>Nilai Usulan: <strong class="font-mono text-gray-700 dark:text-gray-300">{{ formatCurrency(prop.nilai_usulan) }}</strong></span>
                <span v-if="prop.verifikasi_aktif">
                  • Rekomendasi OPD ({{ prop.verifikasi_aktif.opd?.nama_singkat || prop.verifikasi_aktif.opd?.nama }}):
                  <strong class="font-mono text-emerald-600 dark:text-emerald-400">
                    {{ prop.verifikasi_aktif.rekomendasi_anggaran ? formatCurrency(prop.verifikasi_aktif.rekomendasi_anggaran) : 'Sesuai Usulan' }}
                  </strong>
                </span>
                <span>• Lokasi: {{ prop.lokasi_kegiatan }}</span>
              </div>

              <!-- Info jika berstatus ditetapkan_tapd -->
              <div v-if="prop.status === 'ditetapkan_tapd'" class="pt-1 text-xs text-emerald-700 dark:text-emerald-400 font-medium flex items-center gap-2">
                <UIcon name="i-lucide-award" class="w-4 h-4 shrink-0" />
                <span>Disetujui TAPD Masuk APBD: <strong class="font-mono">{{ formatCurrency(prop.nilai_disetujui_tapd || 0) }}</strong></span>
                <span v-if="prop.catatan_tapd" class="text-gray-500 italic font-normal">({{ prop.catatan_tapd }})</span>
              </div>
            </div>

            <!-- Tombol Aksi TAPD -->
            <div class="flex items-center gap-2 shrink-0">
              <NuxtLink :to="`/admin/bkkd/proposal/${prop.id}`">
                <UButton
                  color="neutral"
                  variant="ghost"
                  size="xs"
                  icon="i-lucide-eye"
                  label="Detail"
                />
              </NuxtLink>

              <!-- Tombol Batalkan Penetapan jika terpilih -->
              <UButton
                v-if="canUpdate && prop.status === 'ditetapkan_tapd'"
                color="error"
                variant="outline"
                size="xs"
                icon="i-lucide-x"
                label="Batalkan Pilihan"
                @click="handleBatalkanTapd(prop)"
              />

              <!-- Tombol Pilih Usulan Ini Masuk APBD -->
              <UButton
                v-else-if="canUpdate && ['layak', 'layak_bersyarat', 'tidak_terpilih'].includes(prop.status)"
                color="primary"
                size="xs"
                icon="i-lucide-award"
                label="Pilih Usulan Ini"
                @click="openTapdModal(prop)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Penetapan TAPD -->
    <BkkdPenetapanTapdModal
      v-model:open="modalTapdOpen"
      :proposal="activeProposalForTapd"
      @success="loadData"
    />
  </div>
</template>

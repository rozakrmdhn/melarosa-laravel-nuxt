<script setup lang="ts">
import type { BkkdRiwayat } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import BkkdRiwayatTimeline from '~/components/bkkd/BkkdRiwayatTimeline.vue';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'bkkd-riwayat-view',
});

useSeoMeta({
  title: 'Riwayat Perubahan & Audit Trail BKKD',
});

const toast = useToast();
const api = useBkkdApi();

// Filters
const selectedTahun = ref<number | 'ALL'>('ALL');
const filterKecamatan = ref<number | null>(null);
const filterDesa = ref<number | null>(null);
const filterAksi = ref<string>('ALL');
const page = ref(1);
const perPage = ref(20);

const items = ref<BkkdRiwayat[]>([]);
const totalItems = ref(0);
const loading = ref(false);

const tahunOptions = getTahunAnggaranOptions(true);

const aksiProposalOptions = [
  { label: 'Semua Aksi', value: 'ALL' },
  { label: 'Usulan Dibuat (created)', value: 'created' },
  { label: 'Data Diperbarui (updated)', value: 'updated' },
  { label: 'Dihapus Sementara (deleted)', value: 'deleted' },
  { label: 'Dipulihkan (restored)', value: 'restored' },
];

const currentAksiOptions = computed(() => aksiProposalOptions);

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
    if (filterAksi.value !== 'ALL') {
      params.aksi = filterAksi.value;
    }

    const res = await api.fetchGlobalRiwayat(params);
    items.value = res.data || [];
    totalItems.value = res.meta?.total || 0;
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Riwayat',
      description: err?.data?.message || 'Terjadi kesalahan sistem.',
      color: 'error',
    });
  } finally {
    loading.value = false;
  }
}

watch([selectedTahun, filterKecamatan, filterDesa, filterAksi], () => {
  page.value = 1;
  loadData();
});

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
          <UIcon name="i-lucide-history" class="w-6 h-6 text-primary-500" />
          <span>Riwayat Perubahan & Audit Trail BKKD</span>
        </h1>
        <p class="text-xs text-gray-500 mt-1">
          Log jejak audit dan snapshot sebelum vs sesudah perubahan data usulan proposal serta analisa spasial.
        </p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Aksi</label>
          <USelectMenu
            v-model="filterAksi"
            :items="currentAksiOptions"
            value-key="value"
            label-key="label"
            class="w-full text-xs"
            size="sm"
          />
        </div>

        <div>
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

    <!-- Container Timeline -->
    <div class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-6">
      <BkkdRiwayatTimeline
        :riwayat-list="items"
        :loading="loading"
      />

      <!-- Pagination Controls -->
      <div v-if="items.length > 0" class="pt-4 border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-between text-xs text-gray-500">
        <div>
          Menampilkan {{ items.length }} dari {{ totalItems }} log riwayat
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
  </div>
</template>

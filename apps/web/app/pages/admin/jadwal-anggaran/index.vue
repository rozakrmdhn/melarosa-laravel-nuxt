<script setup lang="ts">
import type { JadwalTahunAnggaran, JadwalTahunAnggaranWilayah } from '~/types/infrastruktur';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { usePermission } from '~/composables/usePermission';
import { useAuthStore } from '~/stores/auth';
import WilayahSelector from '~/components/common/WilayahSelector.vue';

definePageMeta({
  middleware: ['auth'],
});

useSeoMeta({
  title: 'Penjadwalan Tahun Anggaran',
});

const auth = useAuthStore();
const { can, isAdmin } = usePermission();
const api = useInfrastrukturApi();

interface InlineFeedback {
  type: 'success' | 'error';
  message: string;
}

const pageFeedback = ref<InlineFeedback | null>(null);
const jadwalFormFeedback = ref<InlineFeedback | null>(null);
const overrideFormFeedback = ref<InlineFeedback | null>(null);

const canManage = computed(
  () =>
    isAdmin()
    || auth.hasRole('admin')
    || auth.hasRole('verifierBappeda')
    || auth.hasRole('verifikator-bappeda')
    || auth.hasRole('bappeda')
    || can('jadwal-anggaran-manage'),
);

const loading = ref(false);
const jadwals = ref<JadwalTahunAnggaran[]>([]);
const searchQuery = ref('');
const statusFilter = ref<'all' | 'dibuka' | 'terjadwal' | 'ditutup'>('all');
const viewMode = ref<'grid' | 'table'>('grid');

const statusFilterTabs = [
  { key: 'all', label: 'Semua Jadwal', icon: 'i-lucide-calendar-clock' },
  { key: 'dibuka', label: 'Dibuka', icon: 'i-lucide-unlock' },
  { key: 'terjadwal', label: 'Terjadwal', icon: 'i-lucide-clock' },
  { key: 'ditutup', label: 'Ditutup', icon: 'i-lucide-lock' },
] as const;

const statusFilterSelectOptions = [
  { label: 'Semua Status', value: 'all' },
  { label: 'Dibuka', value: 'dibuka' },
  { label: 'Terjadwal', value: 'terjadwal' },
  { label: 'Ditutup', value: 'ditutup' },
];

const filteredJadwals = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return jadwals.value.filter((item) => {
    if (statusFilter.value !== 'all' && item.status_jadwal !== statusFilter.value) {
      return false;
    }
    if (q) {
      const hay = `${item.tahun_anggaran} ${item.nama_periode} ${item.catatan || ''}`.toLowerCase();
      if (!hay.includes(q)) return false;
    }
    return true;
  });
});

const hasActiveFilters = computed(
  () => Boolean(searchQuery.value.trim()) || statusFilter.value !== 'all',
);

function resetFilters() {
  searchQuery.value = '';
  statusFilter.value = 'all';
}

// Modal Buat / Edit Jadwal Global
const isJadwalModalOpen = ref(false);
const isEditingJadwal = ref(false);
const editingJadwalId = ref<string | null>(null);
const savingJadwal = ref(false);

const jadwalForm = reactive({
  tahun_anggaran: new Date().getFullYear(),
  nama_periode: '',
  status_jadwal: 'dibuka' as 'terjadwal' | 'dibuka' | 'ditutup',
  mulai_at: '',
  selesai_at: '',
  allow_plotting: true,
  allow_digitasi: true,
  allow_non_pagu: true,
  catatan: '',
});

// Modal Pengaturan Override Desa / Kecamatan Tertentu
const isOverrideModalOpen = ref(false);
const activeJadwalForOverride = ref<JadwalTahunAnggaran | null>(null);
const savingOverride = ref(false);

const overrideForm = reactive({
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  mode_akses: 'buka_paksa' as 'buka_paksa' | 'tutup_paksa',
  mulai_at: '',
  selesai_at: '',
  allow_plotting_mode: 'global' as 'global' | 'allow' | 'deny',
  allow_digitasi_mode: 'global' as 'global' | 'allow' | 'deny',
  allow_non_pagu_mode: 'global' as 'global' | 'allow' | 'deny',
  alasan: '',
});

const statusOptions = [
  { label: 'Dibuka (Aktif Sekarang)', value: 'dibuka' },
  { label: 'Terjadwal (Sesuai Rentang Tanggal)', value: 'terjadwal' },
  { label: 'Ditutup', value: 'ditutup' },
];

const modeAksesOptions = [
  { label: 'Buka Dispensasi Desa / Wilayah Ini (Buka Paksa)', value: 'buka_paksa' },
  { label: 'Tutup / Bekukan Sementara Wilayah Ini (Tutup Paksa)', value: 'tutup_paksa' },
];

const permOverrideOptions = [
  { label: 'Ikut Jadwal Global', value: 'global' },
  { label: 'Izinkan', value: 'allow' },
  { label: 'Larang', value: 'deny' },
];

function toDatetimeLocalInput(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatTanggal(iso?: string | null): string {
  if (!iso) return '-';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '-';
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getStatusBadge(status: string) {
  switch (status) {
    case 'dibuka':
      return { label: 'Dibuka', color: 'success' as const };
    case 'terjadwal':
      return { label: 'Terjadwal', color: 'info' as const };
    default:
      return { label: 'Ditutup', color: 'neutral' as const };
  }
}

async function loadJadwal() {
  loading.value = true;
  try {
    const res = await api.fetchJadwalList();
    jadwals.value = res.data || [];
    if (activeJadwalForOverride.value) {
      const refreshed = jadwals.value.find((j) => j.id === activeJadwalForOverride.value?.id);
      if (refreshed) {
        activeJadwalForOverride.value = refreshed;
      }
    }
  } catch (err: any) {
    pageFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal memuat daftar jadwal.',
    };
  } finally {
    loading.value = false;
  }
}

function openCreateJadwal() {
  jadwalFormFeedback.value = null;
  isEditingJadwal.value = false;
  editingJadwalId.value = null;
  const nextYear = new Date().getFullYear();
  Object.assign(jadwalForm, {
    tahun_anggaran: nextYear,
    nama_periode: `Periode Input Infrastruktur TA ${nextYear}`,
    status_jadwal: 'dibuka',
    mulai_at: '',
    selesai_at: '',
    allow_plotting: true,
    allow_digitasi: true,
    allow_non_pagu: true,
    catatan: '',
  });
  isJadwalModalOpen.value = true;
}

function openEditJadwal(item: JadwalTahunAnggaran) {
  jadwalFormFeedback.value = null;
  isEditingJadwal.value = true;
  editingJadwalId.value = item.id;
  Object.assign(jadwalForm, {
    tahun_anggaran: item.tahun_anggaran,
    nama_periode: item.nama_periode,
    status_jadwal: item.status_jadwal,
    mulai_at: toDatetimeLocalInput(item.mulai_at),
    selesai_at: toDatetimeLocalInput(item.selesai_at),
    allow_plotting: Boolean(item.allow_plotting),
    allow_digitasi: Boolean(item.allow_digitasi),
    allow_non_pagu: Boolean(item.allow_non_pagu),
    catatan: item.catatan || '',
  });
  isJadwalModalOpen.value = true;
}

async function handleSaveJadwal() {
  jadwalFormFeedback.value = null;
  if (!jadwalForm.nama_periode.trim()) {
    jadwalFormFeedback.value = { type: 'error', message: 'Nama periode wajib diisi.' };
    return;
  }

  savingJadwal.value = true;
  try {
    const payload = {
      tahun_anggaran: Number(jadwalForm.tahun_anggaran),
      nama_periode: jadwalForm.nama_periode.trim(),
      status_jadwal: jadwalForm.status_jadwal,
      mulai_at: jadwalForm.mulai_at ? new Date(jadwalForm.mulai_at).toISOString() : null,
      selesai_at: jadwalForm.selesai_at ? new Date(jadwalForm.selesai_at).toISOString() : null,
      allow_plotting: jadwalForm.allow_plotting,
      allow_digitasi: jadwalForm.allow_digitasi,
      allow_non_pagu: jadwalForm.allow_non_pagu,
      catatan: jadwalForm.catatan.trim() || null,
    };

    if (isEditingJadwal.value && editingJadwalId.value) {
      await api.updateJadwal(editingJadwalId.value, payload);
      pageFeedback.value = { type: 'success', message: `Jadwal TA ${payload.tahun_anggaran} diperbarui.` };
    } else {
      await api.createJadwal(payload);
      pageFeedback.value = { type: 'success', message: `Jadwal TA ${payload.tahun_anggaran} ditambahkan.` };
    }

    isJadwalModalOpen.value = false;
    await loadJadwal();
  } catch (err: any) {
    jadwalFormFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menyimpan jadwal.',
    };
  } finally {
    savingJadwal.value = false;
  }
}

async function quickToggleStatus(item: JadwalTahunAnggaran, nextStatus: 'dibuka' | 'ditutup') {
  try {
    await api.updateJadwal(item.id, { status_jadwal: nextStatus });
    pageFeedback.value = {
      type: 'success',
      message: `TA ${item.tahun_anggaran} diubah menjadi ${nextStatus}.`,
    };
    await loadJadwal();
  } catch (err: any) {
    pageFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal mengubah status jadwal.',
    };
  }
}

async function handleDeleteJadwal(item: JadwalTahunAnggaran) {
  try {
    await api.deleteJadwal(item.id);
    pageFeedback.value = {
      type: 'success',
      message: `Jadwal TA ${item.tahun_anggaran} dihapus.`,
    };
    await loadJadwal();
  } catch (err: any) {
    pageFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menghapus jadwal.',
    };
  }
}

function openOverrideModal(item: JadwalTahunAnggaran) {
  overrideFormFeedback.value = null;
  activeJadwalForOverride.value = item;
  Object.assign(overrideForm, {
    id_kecamatan: null,
    id_desa: null,
    mode_akses: item.status_jadwal === 'ditutup' ? 'buka_paksa' : 'tutup_paksa',
    mulai_at: '',
    selesai_at: '',
    allow_plotting_mode: 'global',
    allow_digitasi_mode: 'global',
    allow_non_pagu_mode: 'global',
    alasan: '',
  });
  isOverrideModalOpen.value = true;
}

function resolveTriState(val: 'global' | 'allow' | 'deny'): boolean | null {
  if (val === 'allow') return true;
  if (val === 'deny') return false;
  return null;
}

async function handleSaveOverride() {
  if (!activeJadwalForOverride.value) return;
  overrideFormFeedback.value = null;
  if (!overrideForm.id_kecamatan && !overrideForm.id_desa) {
    overrideFormFeedback.value = { type: 'error', message: 'Pilih Kecamatan atau Desa target terlebih dahulu.' };
    return;
  }

  savingOverride.value = true;
  try {
    await api.createJadwalOverride(activeJadwalForOverride.value.id, {
      id_kecamatan: overrideForm.id_kecamatan,
      id_desa: overrideForm.id_desa,
      mode_akses: overrideForm.mode_akses,
      mulai_at: overrideForm.mulai_at ? new Date(overrideForm.mulai_at).toISOString() : null,
      selesai_at: overrideForm.selesai_at ? new Date(overrideForm.selesai_at).toISOString() : null,
      allow_plotting: resolveTriState(overrideForm.allow_plotting_mode),
      allow_digitasi: resolveTriState(overrideForm.allow_digitasi_mode),
      allow_non_pagu: resolveTriState(overrideForm.allow_non_pagu_mode),
      alasan: overrideForm.alasan.trim() || null,
    });
    overrideFormFeedback.value = {
      type: 'success',
      message: 'Aturan wilayah tersimpan.',
    };
    overrideForm.alasan = '';
    overrideForm.id_desa = null;
    await loadJadwal();
  } catch (err: any) {
    overrideFormFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menyimpan aturan wilayah.',
    };
  } finally {
    savingOverride.value = false;
  }
}

async function handleDeleteOverride(override: JadwalTahunAnggaranWilayah) {
  if (!activeJadwalForOverride.value) return;
  overrideFormFeedback.value = null;
  try {
    await api.deleteJadwalOverride(activeJadwalForOverride.value.id, override.id);
    overrideFormFeedback.value = {
      type: 'success',
      message: 'Aturan wilayah dihapus.',
    };
    await loadJadwal();
  } catch (err: any) {
    overrideFormFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menghapus aturan wilayah.',
    };
  }
}

onMounted(() => {
  loadJadwal();
});
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <UIcon name="i-lucide-calendar-clock" class="size-5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
            Penjadwalan Tahun Anggaran
          </h1>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Atur tahun anggaran yang dibuka untuk input Plotting Anggaran &amp; Digitasi Segmen oleh Desa/Kecamatan, termasuk dispensasi atau penguncian untuk desa tertentu. Pengajuan Proposal BKKD dan koreksi oleh Admin/Bappeda tidak dibatasi jadwal ini.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <UButton
          icon="i-heroicons-arrow-path"
          label="Refresh"
          aria-label="Muat ulang daftar jadwal tahun anggaran"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          @click="loadJadwal"
        />
        <UButton
          v-if="canManage"
          icon="i-lucide-calendar-plus"
          label="Tambah Jadwal Tahun"
          color="primary"
          variant="solid"
          size="sm"
          class="min-h-[44px] sm:min-h-[38px] px-3.5 whitespace-nowrap cursor-pointer font-semibold"
          @click="openCreateJadwal"
        />
      </div>
    </header>

    <!-- Filter & Toolbar Card -->
    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100 dark:border-white/[0.06]">
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 text-xs">
          <button
            v-for="tab in statusFilterTabs"
            :key="tab.key"
            type="button"
            class="px-3 py-1.5 min-h-[38px] rounded-lg text-xs font-medium transition-colors cursor-pointer flex items-center gap-2 shrink-0 border"
            :class="statusFilter === tab.key
              ? 'bg-blue-600 text-white border-blue-600 font-semibold'
              : 'bg-gray-50 dark:bg-white/[0.03] border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]'"
            @click="statusFilter = tab.key"
          >
            <UIcon :name="tab.icon" class="size-3.5 shrink-0" aria-hidden="true" />
            <span>{{ tab.label }}</span>
          </button>
        </div>

        <div class="flex items-center gap-3 self-start sm:self-center shrink-0">
          <span class="text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">
            Menampilkan {{ filteredJadwals.length }} dari {{ jadwals.length }} jadwal
          </span>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer shrink-0"
            @click="resetFilters"
          >
            Reset Filter
          </button>
          <div class="inline-flex items-center rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.03] p-0.5">
            <button
              type="button"
              aria-label="Tampilan grid kartu"
              class="px-2 py-1 rounded-md text-xs font-medium flex items-center gap-1 transition-colors cursor-pointer"
              :class="viewMode === 'grid' ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="viewMode = 'grid'"
            >
              <UIcon name="i-lucide-layout-grid" class="size-3.5" aria-hidden="true" />
              <span class="hidden sm:inline">Grid</span>
            </button>
            <button
              type="button"
              aria-label="Tampilan tabel full width"
              class="px-2 py-1 rounded-md text-xs font-medium flex items-center gap-1 transition-colors cursor-pointer"
              :class="viewMode === 'table' ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="viewMode = 'table'"
            >
              <UIcon name="i-lucide-table" class="size-3.5" aria-hidden="true" />
              <span class="hidden sm:inline">Tabel</span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="sm:col-span-3">
          <UInput
            v-model="searchQuery"
            icon="i-lucide-search"
            placeholder="Cari tahun anggaran, nama periode, atau catatan..."
            aria-label="Cari jadwal tahun anggaran"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="statusFilter"
            :items="statusFilterSelectOptions"
            value-key="value"
            label-key="label"
            aria-label="Filter status jadwal"
            class="w-full"
            size="sm"
          />
        </div>
      </div>
    </div>

    <div
      v-if="pageFeedback"
      role="status"
      :class="[
        'px-4 py-2.5 rounded-xl border text-xs flex items-center justify-between gap-2',
        pageFeedback.type === 'success'
          ? 'bg-emerald-50/90 dark:bg-emerald-950/35 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100'
          : 'bg-red-50/90 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100',
      ]"
    >
      <span class="font-medium">{{ pageFeedback.message }}</span>
      <button
        type="button"
        aria-label="Tutup pesan status"
        class="p-0.5 rounded opacity-75 hover:opacity-100 cursor-pointer"
        @click="pageFeedback = null"
      >
        <UIcon name="i-lucide-x" class="size-3.5" aria-hidden="true" />
      </button>
    </div>

    <!-- Loading / Empty / List -->
    <div v-if="loading && jadwals.length === 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <USkeleton v-for="i in 3" :key="i" class="h-44 rounded-xl" />
    </div>

    <div
      v-else-if="filteredJadwals.length === 0"
      class="p-12 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] text-center space-y-3"
    >
      <div class="size-12 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-calendar-clock" class="size-6" aria-hidden="true" />
      </div>
      <p class="text-sm font-semibold text-gray-900 dark:text-white">
        {{ jadwals.length === 0 ? 'Belum Ada Penjadwalan Tahun Anggaran' : 'Tidak ada jadwal yang sesuai filter' }}
      </p>
      <p class="text-xs text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
        {{ jadwals.length === 0
          ? 'Saat belum ada jadwal yang dibuat, semua tahun anggaran dianggap terbuka bagi Desa dan Kecamatan. Tambahkan jadwal untuk mengendalikan tahun aktif maupun dispensasi per desa.'
          : 'Ubah kata kunci pencarian atau reset filter untuk melihat seluruh jadwal tahun anggaran.' }}
      </p>
      <div class="pt-1 flex items-center justify-center gap-2">
        <UButton
          v-if="hasActiveFilters"
          label="Reset Filter"
          color="neutral"
          variant="outline"
          size="sm"
          class="cursor-pointer"
          @click="resetFilters"
        />
        <UButton
          v-if="canManage && jadwals.length === 0"
          icon="i-lucide-plus"
          label="Buat Jadwal Tahun Pertama"
          color="primary"
          size="sm"
          class="cursor-pointer"
          @click="openCreateJadwal"
        />
      </div>
    </div>

    <!-- Grid View -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="item in filteredJadwals"
        :key="item.id"
        class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3 flex flex-col justify-between"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">
                  TA {{ item.tahun_anggaran }}
                </span>
                <UBadge
                  :label="getStatusBadge(item.status_jadwal).label"
                  :color="getStatusBadge(item.status_jadwal).color"
                  variant="subtle"
                  size="md"
                  class="font-semibold"
                />
              </div>
              <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                {{ item.nama_periode }}
              </p>
            </div>

            <div v-if="canManage" class="flex items-center gap-1 shrink-0">
              <UButton
                v-if="item.status_jadwal !== 'dibuka'"
                label="Buka"
                icon="i-lucide-unlock"
                size="xs"
                color="success"
                variant="subtle"
                class="cursor-pointer"
                @click="quickToggleStatus(item, 'dibuka')"
              />
              <UButton
                v-else
                label="Tutup"
                icon="i-lucide-lock"
                size="xs"
                color="warning"
                variant="subtle"
                class="cursor-pointer"
                @click="quickToggleStatus(item, 'ditutup')"
              />
              <UButton
                icon="i-lucide-pencil"
                size="xs"
                color="neutral"
                variant="ghost"
                aria-label="Edit jadwal"
                class="cursor-pointer"
                @click="openEditJadwal(item)"
              />
              <UButton
                icon="i-lucide-trash-2"
                size="xs"
                color="error"
                variant="ghost"
                aria-label="Hapus jadwal"
                class="cursor-pointer"
                @click="handleDeleteJadwal(item)"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200/70 dark:border-white/[0.06] text-[11px]">
            <div>
              <span class="text-gray-500 dark:text-gray-400 block">Mulai Aktif</span>
              <span class="font-medium text-gray-800 dark:text-gray-200 tabular-nums">
                {{ formatTanggal(item.mulai_at) }}
              </span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400 block">Batas Berakhir</span>
              <span class="font-medium text-gray-800 dark:text-gray-200 tabular-nums">
                {{ formatTanggal(item.selesai_at) }}
              </span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-1.5">
            <UBadge
              :label="item.allow_plotting ? 'Plotting: Diizinkan' : 'Plotting: Ditutup'"
              :color="item.allow_plotting ? 'primary' : 'neutral'"
              variant="outline"
              size="md"
              class="font-medium"
            />
            <UBadge
              :label="item.allow_digitasi ? 'Digitasi Segmen: Diizinkan' : 'Digitasi: Ditutup'"
              :color="item.allow_digitasi ? 'primary' : 'neutral'"
              variant="outline"
              size="md"
              class="font-medium"
            />
            <UBadge
              :label="item.allow_non_pagu ? 'Non-Pagu: Diizinkan' : 'Non-Pagu: Dilarang'"
              :color="item.allow_non_pagu ? 'neutral' : 'warning'"
              variant="outline"
              size="md"
              class="font-medium"
            />
          </div>

          <p v-if="item.catatan" class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
            {{ item.catatan }}
          </p>
        </div>

        <div class="pt-2.5 border-t border-gray-200 dark:border-white/[0.08] flex items-center justify-between gap-2">
          <span class="text-xs text-gray-600 dark:text-gray-400 tabular-nums">
            Aturan khusus wilayah:
            <strong class="text-gray-900 dark:text-gray-100">{{ item.wilayah_overrides?.length || 0 }}</strong> desa/kecamatan
          </span>
          <UButton
            v-if="canManage"
            icon="i-lucide-map-pin-check"
            label="Atur Desa Tertentu"
            size="xs"
            color="info"
            variant="subtle"
            class="cursor-pointer"
            @click="openOverrideModal(item)"
          />
        </div>
      </div>
    </div>

    <!-- Table View (Full Width) -->
    <div v-else class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-4">Tahun &amp; Periode</th>
              <th scope="col" class="py-3 px-3">Rentang Jadwal</th>
              <th scope="col" class="py-3 px-3">Izin Fitur Input</th>
              <th scope="col" class="py-3 px-3 text-center">Override Wilayah</th>
              <th scope="col" class="py-3 px-3 text-center">Status</th>
              <th v-if="canManage" scope="col" class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="item in filteredJadwals"
              :key="`tbl-${item.id}`"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <td class="py-3 px-4">
                <p class="font-bold text-sm text-gray-900 dark:text-white tabular-nums">TA {{ item.tahun_anggaran }}</p>
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ item.nama_periode }}</p>
                <p v-if="item.catatan" class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">{{ item.catatan }}</p>
              </td>
              <td class="py-3 px-3 tabular-nums">
                <p><span class="text-gray-500 dark:text-gray-400">Mulai:</span> {{ formatTanggal(item.mulai_at) }}</p>
                <p class="mt-0.5"><span class="text-gray-500 dark:text-gray-400">Selesai:</span> {{ formatTanggal(item.selesai_at) }}</p>
              </td>
              <td class="py-3 px-3">
                <div class="flex flex-wrap items-center gap-1.5">
                  <UBadge
                    :label="item.allow_plotting ? 'Plotting' : 'No Plotting'"
                    :color="item.allow_plotting ? 'primary' : 'neutral'"
                    variant="outline"
                    size="md"
                  />
                  <UBadge
                    :label="item.allow_digitasi ? 'Digitasi' : 'No Digitasi'"
                    :color="item.allow_digitasi ? 'primary' : 'neutral'"
                    variant="outline"
                    size="md"
                  />
                  <UBadge
                    :label="item.allow_non_pagu ? 'Non-Pagu' : 'Pagu Saja'"
                    :color="item.allow_non_pagu ? 'neutral' : 'warning'"
                    variant="outline"
                    size="md"
                  />
                </div>
              </td>
              <td class="py-3 px-3 text-center">
                <UButton
                  v-if="canManage"
                  icon="i-lucide-map-pin-check"
                  :label="`${item.wilayah_overrides?.length || 0} aturan`"
                  size="xs"
                  color="info"
                  variant="subtle"
                  class="cursor-pointer"
                  @click="openOverrideModal(item)"
                />
                <span v-else class="tabular-nums">{{ item.wilayah_overrides?.length || 0 }}</span>
              </td>
              <td class="py-3 px-3 text-center">
                <UBadge
                  :label="getStatusBadge(item.status_jadwal).label"
                  :color="getStatusBadge(item.status_jadwal).color"
                  variant="subtle"
                  size="md"
                  class="font-semibold"
                />
              </td>
              <td v-if="canManage" class="py-3 px-4 text-right">
                <div class="inline-flex items-center gap-1">
                  <UButton
                    v-if="item.status_jadwal !== 'dibuka'"
                    label="Buka"
                    icon="i-lucide-unlock"
                    size="xs"
                    color="success"
                    variant="subtle"
                    class="cursor-pointer"
                    @click="quickToggleStatus(item, 'dibuka')"
                  />
                  <UButton
                    v-else
                    label="Tutup"
                    icon="i-lucide-lock"
                    size="xs"
                    color="warning"
                    variant="subtle"
                    class="cursor-pointer"
                    @click="quickToggleStatus(item, 'ditutup')"
                  />
                  <UButton
                    icon="i-lucide-pencil"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    aria-label="Edit jadwal"
                    class="cursor-pointer"
                    @click="openEditJadwal(item)"
                  />
                  <UButton
                    icon="i-lucide-trash-2"
                    size="xs"
                    color="error"
                    variant="ghost"
                    aria-label="Hapus jadwal"
                    class="cursor-pointer"
                    @click="handleDeleteJadwal(item)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal: Tambah / Edit Jadwal Global -->
    <UModal
      v-model:open="isJadwalModalOpen"
      :title="isEditingJadwal ? `Edit Jadwal TA ${jadwalForm.tahun_anggaran}` : 'Tambah Jadwal Tahun Anggaran'"
      :ui="{ content: 'sm:max-w-lg dark:bg-[#0b0f19] dark:border-gray-800' }"
    >
      <template #body>
        <form class="space-y-3.5 text-xs" @submit.prevent="handleSaveJadwal">
          <div
            v-if="jadwalFormFeedback"
            role="alert"
            :class="[
              'px-3 py-2 rounded-lg border text-xs font-medium',
              jadwalFormFeedback.type === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-950/35 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100'
                : 'bg-red-50 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100',
            ]"
          >
            {{ jadwalFormFeedback.message }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Tahun Anggaran" required size="sm">
              <UInput
                v-model.number="jadwalForm.tahun_anggaran"
                type="number"
                min="2020"
                max="2040"
                :disabled="isEditingJadwal"
                class="w-full font-mono"
              />
            </UFormField>

            <UFormField label="Status Jadwal" required size="sm">
              <USelectMenu
                v-model="jadwalForm.status_jadwal"
                :items="statusOptions"
                value-key="value"
                label-key="label"
                :search-input="false"
                class="w-full"
              />
            </UFormField>
          </div>

          <UFormField label="Nama Periode" required size="sm">
            <UInput
              v-model="jadwalForm.nama_periode"
              placeholder="Contoh: Input Infrastruktur TA 2026"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Mulai Berlaku (Opsional)" size="sm">
              <UInput v-model="jadwalForm.mulai_at" type="datetime-local" class="w-full" />
            </UFormField>

            <UFormField label="Batas Berakhir (Opsional)" size="sm">
              <UInput v-model="jadwalForm.selesai_at" type="datetime-local" class="w-full" />
            </UFormField>
          </div>

          <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-2">
            <p class="font-semibold text-gray-800 dark:text-gray-200">Izin Modul pada Periode Ini</p>
            <div class="flex flex-col gap-2">
              <UCheckbox v-model="jadwalForm.allow_plotting" label="Izinkan input / ubah Plotting Anggaran oleh Desa/Kecamatan" />
              <UCheckbox v-model="jadwalForm.allow_digitasi" label="Izinkan digitasi & pengeditan Segmen Fisik Infrastruktur" />
              <UCheckbox v-model="jadwalForm.allow_non_pagu" label="Izinkan mode Inventarisasi Eksisting Non-Pagu" />
            </div>
          </div>

          <UFormField label="Catatan / Pengumuman ke Desa" size="sm">
            <UTextarea
              v-model="jadwalForm.catatan"
              :rows="2"
              placeholder="Catatan alasan penutupan atau informasi batas waktu..."
              class="w-full"
            />
          </UFormField>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton label="Batal" color="neutral" variant="ghost" @click="isJadwalModalOpen = false" />
          <UButton
            :label="isEditingJadwal ? 'Simpan Perubahan' : 'Buat Jadwal'"
            color="primary"
            :loading="savingJadwal"
            @click="handleSaveJadwal"
          />
        </div>
      </template>
    </UModal>

    <!-- Modal: Pengaturan Khusus Desa / Kecamatan (Override) -->
    <UModal
      v-model:open="isOverrideModalOpen"
      :title="`Pengaturan Desa Khusus: TA ${activeJadwalForOverride?.tahun_anggaran ?? ''}`"
      description="Berikan dispensasi buka khusus untuk salah satu desa/kecamatan atau bekukan sementara wilayah tertentu."
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-gray-800' }"
    >
      <template #body>
        <div class="space-y-4 text-xs">
          <!-- Form Tambah Override Desa -->
          <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-3">
            <p class="font-semibold text-gray-900 dark:text-white">
              Tambah / Perbarui Aturan Wilayah
            </p>

            <div
              v-if="overrideFormFeedback"
              role="status"
              :class="[
                'px-3 py-2 rounded-lg border text-xs font-medium',
                overrideFormFeedback.type === 'success'
                  ? 'bg-emerald-50 dark:bg-emerald-950/35 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100'
                  : 'bg-red-50 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100',
              ]"
            >
              {{ overrideFormFeedback.message }}
            </div>

            <WilayahSelector
              v-model:model-kecamatan="overrideForm.id_kecamatan"
              v-model:model-desa="overrideForm.id_desa"
              layout="row"
              :allow-all="false"
              size="sm"
            />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
              <UFormField label="Mode Akses" required size="sm" class="sm:col-span-1">
                <USelectMenu
                  v-model="overrideForm.mode_akses"
                  :items="modeAksesOptions"
                  value-key="value"
                  label-key="label"
                  :search-input="false"
                  class="w-full"
                />
              </UFormField>

              <UFormField label="Mulai (Opsional)" size="sm">
                <UInput v-model="overrideForm.mulai_at" type="datetime-local" class="w-full" />
              </UFormField>

              <UFormField label="Sampai Dengan (Opsional)" size="sm">
                <UInput v-model="overrideForm.selesai_at" type="datetime-local" class="w-full" />
              </UFormField>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
              <UFormField label="Plotting Anggaran" size="sm">
                <USelectMenu
                  v-model="overrideForm.allow_plotting_mode"
                  :items="permOverrideOptions"
                  value-key="value"
                  label-key="label"
                  :search-input="false"
                  class="w-full"
                />
              </UFormField>

              <UFormField label="Digitasi Segmen" size="sm">
                <USelectMenu
                  v-model="overrideForm.allow_digitasi_mode"
                  :items="permOverrideOptions"
                  value-key="value"
                  label-key="label"
                  :search-input="false"
                  class="w-full"
                />
              </UFormField>

              <UFormField label="Mode Non-Pagu" size="sm">
                <USelectMenu
                  v-model="overrideForm.allow_non_pagu_mode"
                  :items="permOverrideOptions"
                  value-key="value"
                  label-key="label"
                  :search-input="false"
                  class="w-full"
                />
              </UFormField>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 items-end">
              <UFormField label="Alasan / Nomor Surat Dispensasi" size="sm" class="flex-1 w-full">
                <UInput
                  v-model="overrideForm.alasan"
                  placeholder="Misal: Perpanjangan input BA & digitasi Desa Pasinan s.d. akhir bulan"
                  class="w-full"
                />
              </UFormField>
              <UButton
                icon="i-lucide-save"
                label="Simpan Aturan Wilayah"
                color="primary"
                size="sm"
                :loading="savingOverride"
                class="cursor-pointer shrink-0"
                @click="handleSaveOverride"
              />
            </div>
          </div>

          <!-- Daftar Override Aktif -->
          <div class="space-y-2">
            <p class="font-semibold text-gray-800 dark:text-gray-200">
              Daftar Wilayah dengan Aturan Khusus ({{ activeJadwalForOverride?.wilayah_overrides?.length || 0 }})
            </p>

            <div
              v-if="!activeJadwalForOverride?.wilayah_overrides?.length"
              class="p-4 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 text-center text-gray-500 dark:text-gray-400"
            >
              Belum ada aturan khusus wilayah untuk TA {{ activeJadwalForOverride?.tahun_anggaran }}. Seluruh desa mengikuti aturan global.
            </div>

            <div v-else class="divide-y divide-gray-200 dark:divide-gray-800 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
              <div
                v-for="ov in activeJadwalForOverride.wilayah_overrides"
                :key="ov.id"
                class="p-3 bg-white dark:bg-[#0b0f19] flex items-start justify-between gap-3"
              >
                <div class="space-y-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-semibold text-gray-900 dark:text-white">
                      {{
                        ov.desa
                          ? `Desa ${ov.desa.nama_desa} (Kec. ${ov.kecamatan?.nama_kecamatan || ov.id_kecamatan})`
                          : `Seluruh Kec. ${ov.kecamatan?.nama_kecamatan || ov.id_kecamatan}`
                      }}
                    </span>
                    <UBadge
                      :label="ov.mode_akses === 'buka_paksa' ? 'Dispensasi Buka' : 'Ditutup Sementara'"
                      :color="ov.mode_akses === 'buka_paksa' ? 'success' : 'error'"
                      variant="subtle"
                      size="xs"
                    />
                  </div>
                  <p v-if="ov.selesai_at || ov.mulai_at" class="text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">
                    Berlaku: {{ formatTanggal(ov.mulai_at) }} s.d. {{ formatTanggal(ov.selesai_at) }}
                  </p>
                  <p v-if="ov.alasan" class="text-[11px] text-gray-600 dark:text-gray-300">
                    {{ ov.alasan }}
                  </p>
                </div>

                <UButton
                  icon="i-lucide-trash-2"
                  size="xs"
                  color="error"
                  variant="ghost"
                  aria-label="Hapus aturan wilayah"
                  class="cursor-pointer shrink-0"
                  @click="handleDeleteOverride(ov)"
                />
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end w-full">
          <UButton label="Tutup" color="neutral" variant="outline" @click="isOverrideModalOpen = false" />
        </div>
      </template>
    </UModal>
  </div>
</template>

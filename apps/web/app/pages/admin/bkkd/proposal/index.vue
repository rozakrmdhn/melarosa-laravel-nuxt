<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import BkkdProposalPreviewMap from '~/components/bkkd/BkkdProposalPreviewMap.vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import { useAuthStore } from '~/stores/auth';
import type {
  BkkdProposal,
  BkkdProposalLokasi,
  BkkdProposalSummary,
  BkkdReferensiOverlapTahunLalu,
  RefBkkdBidang,
  StatusAnalisaSpasialBkkd,
} from '~/types/bkkd';

definePageMeta({
  middleware: ['auth'],
});

useHead({
  title: 'Verifikasi Spasial Proposal BKKD | WebGIS Bojonegoro',
});

const toast = useToast();
const auth = useAuthStore();
const { can } = usePermission();
const bkkdApi = useBkkdApi();

const canCreate = computed(() => {
  return (
    can('bkkd-proposal-create') ||
    auth.hasRole('admin') ||
    auth.hasRole('verifierBappeda') ||
    auth.hasRole('operatorDesa') ||
    auth.hasRole('verifierKecamatan')
  );
});

const canDelete = computed(() => {
  return (
    can('bkkd-proposal-delete') ||
    auth.hasRole('admin') ||
    auth.hasRole('verifierBappeda') ||
    auth.hasRole('operatorDesa')
  );
});

const loading = ref(false);
const hasLoadedOnce = ref(false);
const errorState = ref<string | null>(null);
const proposals = ref<BkkdProposal[]>([]);
const summary = ref<BkkdProposalSummary>({
  total_proposal: 0,
  total_nilai_usulan: 0,
  total_lokasi: 0,
  total_panjang_gis: 0,
  jumlah_aman: 0,
  jumlah_lanjutan_ruas: 0,
  jumlah_konfirmasi_belum_tuntas: 0,
  jumlah_duplikasi_tuntas: 0,
  jumlah_kewenangan_kabupaten: 0,
  jumlah_belum_dianalisa: 0,
});

const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
  const list: number[] = [];
  for (let y = currentYear - 2; y <= currentYear + 3; y++) {
    list.push(y);
  }
  return list;
});

const filters = reactive({
  tahun_anggaran: 0,
  tagging_usulan: '' as '' | 'RKPD' | 'PRKPD',
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  id_bidang: '',
  status_analisa_spasial: '' as '' | StatusAnalisaSpasialBkkd,
  search: '',
  page: 1,
  per_page: 15,
});

const bidangList = ref<RefBkkdBidang[]>([]);
const reanalyzingId = ref<string | null>(null);
const deletingId = ref<string | null>(null);

// Split Map Preview state
const mapPanelOpen = ref(false);
const mobileMapModalOpen = ref(false);
const previewProposal = ref<BkkdProposal | null>(null);
const mobileMapCloseBtnRef = ref<HTMLButtonElement | null>(null);

// Delete Confirmation Modal state
const confirmDeleteTarget = ref<BkkdProposal | null>(null);
const deleteCancelBtnRef = ref<HTMLButtonElement | null>(null);

// Import CSV Modal state
const importModalOpen = ref(false);
const importFile = ref<File | null>(null);
const importingCsv = ref(false);
const importResult = ref<{
  imported_count: number;
  failed_count: number;
  message?: string;
  errors: Array<{ baris?: number; judul?: string; pesan?: string }>;
} | null>(null);
const importCloseBtnRef = ref<HTMLButtonElement | null>(null);

let lastFocusedElement: HTMLElement | null = null;

const isKecamatanLocked = computed(() => {
  if (auth.hasRole('admin') || auth.hasRole('verifierBappeda')) return false;
  return Boolean(auth.user?.id_kecamatan);
});

const isDesaLocked = computed(() => {
  if (auth.hasRole('admin') || auth.hasRole('verifierBappeda')) return false;
  return Boolean(auth.user?.id_desa);
});

const hasActiveFilters = computed(() => {
  const userFilteredKec = !isKecamatanLocked.value && filters.id_kecamatan !== null;
  const userFilteredDesa = !isDesaLocked.value && filters.id_desa !== null;
  return Boolean(
    filters.tahun_anggaran > 0 ||
      filters.tagging_usulan ||
      userFilteredKec ||
      userFilteredDesa ||
      filters.id_bidang ||
      filters.status_analisa_spasial ||
      filters.search.trim()
  );
});

const statusFilterTabs = computed(() => [
  {
    key: '' as const,
    label: 'Semua',
    count: summary.value.total_proposal || 0,
    activeClass: 'border-sky-700 bg-sky-50 text-sky-900 dark:border-sky-500 dark:bg-sky-950/60 dark:text-sky-200',
  },
  {
    key: 'aman' as const,
    label: 'Aman',
    count: summary.value.jumlah_aman || 0,
    activeClass: 'border-emerald-700 bg-emerald-50 text-emerald-900 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200',
  },
  {
    key: 'lanjutan_ruas' as const,
    label: 'Lanjutan',
    count: summary.value.jumlah_lanjutan_ruas || 0,
    activeClass: 'border-sky-700 bg-sky-50 text-sky-900 dark:border-sky-500 dark:bg-sky-950/60 dark:text-sky-200',
  },
  {
    key: 'konfirmasi_belum_tuntas' as const,
    label: 'Konfirmasi',
    count: summary.value.jumlah_konfirmasi_belum_tuntas || 0,
    activeClass: 'border-amber-700 bg-amber-50 text-amber-900 dark:border-amber-500 dark:bg-amber-950/60 dark:text-amber-200',
  },
  {
    key: 'duplikasi_tuntas' as const,
    label: 'Tumpang Tindih',
    count: summary.value.jumlah_duplikasi_tuntas || 0,
    activeClass: 'border-rose-700 bg-rose-50 text-rose-900 dark:border-rose-500 dark:bg-rose-950/60 dark:text-rose-200',
  },
  {
    key: 'kewenangan_kabupaten' as const,
    label: 'Aset Kabupaten',
    count: summary.value.jumlah_kewenangan_kabupaten || 0,
    activeClass: 'border-rose-700 bg-rose-50 text-rose-900 dark:border-rose-500 dark:bg-rose-950/60 dark:text-rose-200',
  },
  {
    key: 'belum_dianalisa' as const,
    label: 'Tertunda',
    count: summary.value.jumlah_belum_dianalisa || 0,
    activeClass: 'border-slate-700 bg-slate-100 text-slate-900 dark:border-slate-400 dark:bg-slate-800 dark:text-slate-100',
  },
]);

function formatNumber(val?: number | null, maxDecimals = 2): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return '0';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maxDecimals }).format(Number(val));
}

function formatRupiah(val?: number | null, compact = false): string {
  if (val === null || val === undefined || Number.isNaN(Number(val)) || Number(val) === 0) {
    return compact ? 'Rp 0' : 'Belum diisi';
  }
  const num = Number(val);
  if (compact && num >= 1_000_000_000) {
    return `Rp ${formatNumber(num / 1_000_000_000, 2)} M`;
  }
  if (compact && num >= 1_000_000) {
    return `Rp ${formatNumber(num / 1_000_000, 1)} Jt`;
  }
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(num);
}

function getSpatialStatusMeta(status?: StatusAnalisaSpasialBkkd | string | null) {
  switch (status) {
    case 'aman':
      return {
        label: 'Aman (Belum Terbangun)',
        shortLabel: 'Aman',
        badgeClass:
          'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-200 dark:border-emerald-700',
      };
    case 'lanjutan_ruas':
      return {
        label: 'Lanjutan Ruas',
        shortLabel: 'Lanjutan',
        badgeClass:
          'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/70 dark:text-sky-200 dark:border-sky-700',
      };
    case 'konfirmasi_belum_tuntas':
      return {
        label: 'Perlu Konfirmasi',
        shortLabel: 'Konfirmasi',
        badgeClass:
          'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/70 dark:text-amber-200 dark:border-amber-700',
      };
    case 'duplikasi_tuntas':
      return {
        label: 'Indikasi Tumpang Tindih',
        shortLabel: 'Tumpang Tindih',
        badgeClass:
          'bg-rose-50 text-rose-900 border-rose-300 dark:bg-rose-950/70 dark:text-rose-200 dark:border-rose-700',
      };
    case 'kewenangan_kabupaten':
      return {
        label: 'Aset Kabupaten (Bukan Desa)',
        shortLabel: 'Aset Kabupaten',
        badgeClass:
          'bg-rose-50 text-rose-900 border-rose-300 dark:bg-rose-950/70 dark:text-rose-200 dark:border-rose-700',
      };
    default:
      return {
        label: 'Belum Dianalisa',
        shortLabel: 'Tertunda',
        badgeClass:
          'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600',
      };
  }
}

function extractProposalInsight(item: BkkdProposal) {
  const loks: BkkdProposalLokasi[] = item.lokasi || [];
  const ruasNames = new Set<string>();
  const kabupatenNames = new Set<string>();
  const sources = new Set<string>();
  const crossRefs = new Map<string, BkkdReferensiOverlapTahunLalu>();
  let totalIrisanPoros = 0;
  let totalIrisanKabupaten = 0;
  let totalIrisanSegmen = 0;
  let segmenCount = 0;

  loks.forEach((lok) => {
    const ringkasan = lok.ringkasan_analisa;
    if (!ringkasan) return;
    if (ringkasan.sumber_rute) sources.add(ringkasan.sumber_rute);
    totalIrisanPoros += Number(ringkasan.total_irisan_jalan_poros_m || 0);
    totalIrisanKabupaten += Number(ringkasan.total_irisan_jalan_kabupaten_m || 0);
    totalIrisanSegmen += Number(ringkasan.total_irisan_segmen_m || 0);

    (ringkasan.referensi_jalan_poros || []).forEach((jp) => {
      if (jp.nama_ruas) ruasNames.add(jp.nama_ruas);
      totalIrisanPoros = Math.max(totalIrisanPoros, Number(jp.panjang_irisan_m || 0));
    });

    (ringkasan.referensi_jalan_kabupaten || []).forEach((jk) => {
      if (jk.nama_ruas) kabupatenNames.add(jk.nama_ruas);
      totalIrisanKabupaten = Math.max(totalIrisanKabupaten, Number(jk.panjang_irisan_m || 0));
    });

    (ringkasan.referensi_infrastruktur_segmen || []).forEach((seg) => {
      segmenCount += 1;
      totalIrisanSegmen = Math.max(totalIrisanSegmen, Number(seg.panjang_irisan_m || 0));
    });

    (ringkasan.referensi_tahun_lalu || []).forEach((refProp) => {
      const key = `${refProp.id_proposal_lama}-${refProp.id_lokasi_lama}`;
      if (!crossRefs.has(key)) {
        crossRefs.set(key, refProp);
      }
    });
  });

  const panjangGis = Number(item.total_panjang_gis || 0);
  const sisaBelumTerbangun = Math.max(0, panjangGis - totalIrisanSegmen);
  const builtRatioPct = panjangGis > 0 ? Math.min(100, Math.round((totalIrisanSegmen / panjangGis) * 100)) : 0;
  const nonPorosLabel = sources.has('manual')
    ? 'Digitasi Manual'
    : sources.has('garis_lurus')
      ? 'Koordinat Langsung'
      : 'Rute Jalan Otomatis';

  const refsArray = Array.from(crossRefs.values());
  const topCrossRef = refsArray[0] || null;
  let crossRefLabel = '';
  if (topCrossRef) {
    const targetTag = topCrossRef.tagging_usulan || 'PRKPD';
    if (topCrossRef.pasangan_tagging === 'prkpd_vs_rkpd') {
      crossRefLabel = `Irisan RKPD ${topCrossRef.tahun_anggaran} (${formatNumber(topCrossRef.panjang_irisan_usulan_m, 0)} m)`;
    } else if (topCrossRef.pasangan_tagging === 'rkpd_vs_prkpd') {
      crossRefLabel = `Irisan PRKPD ${topCrossRef.tahun_anggaran} (${formatNumber(topCrossRef.panjang_irisan_usulan_m, 0)} m)`;
    } else {
      crossRefLabel = `Irisan ${targetTag} ${topCrossRef.tahun_anggaran} (${formatNumber(topCrossRef.panjang_irisan_usulan_m, 0)} m)`;
    }
  }

  return {
    ruasList: Array.from(ruasNames),
    kabupatenList: Array.from(kabupatenNames),
    totalIrisanPoros,
    totalIrisanKabupaten,
    totalIrisanSegmen,
    segmenCount,
    sisaBelumTerbangun,
    builtRatioPct,
    nonPorosLabel,
    crossRefCount: refsArray.length,
    crossRefLabel,
    isCrossPair: Boolean(
      topCrossRef?.pasangan_tagging === 'prkpd_vs_rkpd' || topCrossRef?.pasangan_tagging === 'rkpd_vs_prkpd'
    ),
  };
}

async function loadMasterFilters() {
  try {
    const bidangRes = await bkkdApi.fetchRefBidang();
    bidangList.value = bidangRes.data || [];
  } catch (err) {
    console.error('Failed loading filter references:', err);
  }
}

function handleWilayahFilterChange(val: { id_kecamatan: number | null; id_desa: number | null }) {
  filters.id_kecamatan = val.id_kecamatan;
  filters.id_desa = val.id_desa;
  filters.page = 1;
  loadProposals();
}

async function loadProposals() {
  loading.value = true;
  errorState.value = null;
  try {
    const queryParams: Record<string, any> = {
      page: filters.page,
      per_page: filters.per_page,
    };
    if (filters.tahun_anggaran > 0) queryParams.tahun_anggaran = filters.tahun_anggaran;
    if (filters.tagging_usulan) queryParams.tagging_usulan = filters.tagging_usulan;
    if (filters.id_kecamatan) queryParams.id_kecamatan = filters.id_kecamatan;
    if (filters.id_desa) queryParams.id_desa = filters.id_desa;
    if (filters.id_bidang) queryParams.id_bidang = filters.id_bidang;
    if (filters.status_analisa_spasial) {
      queryParams.status_analisa_spasial = filters.status_analisa_spasial;
    }
    if (filters.search.trim()) queryParams.search = filters.search.trim();

    const res = await bkkdApi.fetchProposals(queryParams);
    proposals.value = res.data || [];
    hasLoadedOnce.value = true;

    if (res.meta) {
      meta.value = res.meta;
    }
    if (res.summary) {
      summary.value = {
        ...summary.value,
        ...res.summary,
      };
    }

    if (previewProposal.value) {
      const updatedPreview = proposals.value.find((p) => p.id === previewProposal.value?.id);
      if (updatedPreview) {
        previewProposal.value = updatedPreview;
      }
    } else if (mapPanelOpen.value && proposals.value.length > 0) {
      previewProposal.value = proposals.value[0] || null;
    }
  } catch (err: any) {
    errorState.value = err?.data?.message || 'Gagal memuat daftar verifikasi proposal BKKD.';
    hasLoadedOnce.value = true;
  } finally {
    loading.value = false;
  }
}

watch(
  () => [filters.tahun_anggaran, filters.tagging_usulan, filters.id_bidang, filters.status_analisa_spasial],
  () => {
    filters.page = 1;
    loadProposals();
  }
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
function handleSearchInput() {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    filters.page = 1;
    loadProposals();
  }, 300);
}

function resetFilters() {
  filters.tahun_anggaran = 0;
  filters.tagging_usulan = '';
  filters.id_kecamatan = isKecamatanLocked.value && auth.user?.id_kecamatan
    ? Number(auth.user.id_kecamatan)
    : null;
  filters.id_desa = isDesaLocked.value && auth.user?.id_desa
    ? Number(auth.user.id_desa)
    : null;
  filters.id_bidang = '';
  filters.status_analisa_spasial = '';
  filters.search = '';
  filters.page = 1;
  loadProposals();
}

function toggleStatusFilter(key: '' | StatusAnalisaSpasialBkkd) {
  if (key === '') {
    filters.status_analisa_spasial = '';
    return;
  }
  filters.status_analisa_spasial = filters.status_analisa_spasial === key ? '' : key;
}

function openMapPreviewForProposal(item: BkkdProposal) {
  previewProposal.value = item;
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    lastFocusedElement = document.activeElement as HTMLElement | null;
    mobileMapModalOpen.value = true;
    nextTick(() => {
      mobileMapCloseBtnRef.value?.focus();
    });
  } else {
    mapPanelOpen.value = true;
  }
}

function toggleDesktopMapPanel() {
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    if (!previewProposal.value && proposals.value.length > 0) {
      previewProposal.value = proposals.value[0] || null;
    }
    lastFocusedElement = document.activeElement as HTMLElement | null;
    mobileMapModalOpen.value = true;
    nextTick(() => {
      mobileMapCloseBtnRef.value?.focus();
    });
    return;
  }

  mapPanelOpen.value = !mapPanelOpen.value;
  if (mapPanelOpen.value && !previewProposal.value && proposals.value.length > 0) {
    previewProposal.value = proposals.value[0] || null;
  }
}

function closeMobileMapModal() {
  mobileMapModalOpen.value = false;
  nextTick(() => {
    lastFocusedElement?.focus?.();
  });
}

async function handleReanalyzeRow(item: BkkdProposal) {
  reanalyzingId.value = item.id;
  try {
    await bkkdApi.reanalyzeProposalSpatial(item.id);
    await loadProposals();
    toast.add({
      title: 'Analisa Spasial Diperbarui',
      description: `Proposal ${item.nomor_registrasi} berhasil dianalisa ulang.`,
      color: 'success',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Analisa Ulang',
      description: err?.data?.message || 'Terjadi kesalahan saat menganalisa ulang proposal.',
      color: 'error',
    });
  } finally {
    reanalyzingId.value = null;
  }
}

async function handleRefreshList() {
  await loadProposals();
}

function promptDeleteRow(item: BkkdProposal) {
  lastFocusedElement = document.activeElement as HTMLElement | null;
  confirmDeleteTarget.value = item;
  nextTick(() => {
    deleteCancelBtnRef.value?.focus();
  });
}

function closeDeleteDialog() {
  if (deletingId.value) return;
  confirmDeleteTarget.value = null;
  nextTick(() => {
    lastFocusedElement?.focus?.();
  });
}

async function confirmDeleteRow() {
  const item = confirmDeleteTarget.value;
  if (!item) return;
  deletingId.value = item.id;
  try {
    await bkkdApi.deleteProposal(item.id);
    if (previewProposal.value?.id === item.id) {
      previewProposal.value = null;
    }
    confirmDeleteTarget.value = null;
    await loadProposals();
    toast.add({
      title: 'Proposal Dihapus',
      description: `Data proposal ${item.nomor_registrasi} telah dihapus.`,
      color: 'success',
    });
    nextTick(() => {
      lastFocusedElement?.focus?.();
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Menghapus Proposal',
      description: err?.data?.message || 'Proposal tidak dapat dihapus.',
      color: 'error',
    });
  } finally {
    deletingId.value = null;
  }
}

function openImportModal() {
  lastFocusedElement = document.activeElement as HTMLElement | null;
  importFile.value = null;
  importResult.value = null;
  importModalOpen.value = true;
  nextTick(() => {
    importCloseBtnRef.value?.focus();
  });
}

function closeImportModal() {
  if (importingCsv.value) return;
  importModalOpen.value = false;
  nextTick(() => {
    lastFocusedElement?.focus?.();
  });
}

function onImportFileSelected(event: Event) {
  const target = event.target as HTMLInputElement;
  importFile.value = target.files?.[0] || null;
  importResult.value = null;
}

async function submitImportCsv() {
  if (!importFile.value) return;
  importingCsv.value = true;
  try {
    const res = await bkkdApi.importProposalExcel({ file: importFile.value });
    importResult.value = {
      imported_count: res.imported_count || 0,
      failed_count: res.failed_count || 0,
      message: res.message,
      errors: res.errors || [],
    };
    if ((res.imported_count || 0) > 0) {
      await loadProposals();
    }
    toast.add({
      title: 'Proses Import Selesai',
      description: res.message || `${res.imported_count || 0} proposal berhasil ditambahkan.`,
      color: (res.imported_count || 0) > 0 ? 'success' : 'warning',
    });
  } catch (err: any) {
    toast.add({
      title: 'Gagal Mengimpor File',
      description: err?.data?.message || 'Periksa format kolom CSV dan coba lagi.',
      color: 'error',
    });
  } finally {
    importingCsv.value = false;
  }
}

function handleEscapeKey(evt: KeyboardEvent) {
  if (evt.key !== 'Escape') return;
  if (confirmDeleteTarget.value) {
    closeDeleteDialog();
  } else if (importModalOpen.value) {
    closeImportModal();
  } else if (mobileMapModalOpen.value) {
    closeMobileMapModal();
  }
}

onMounted(async () => {
  window.addEventListener('keydown', handleEscapeKey);
  if (isKecamatanLocked.value && auth.user?.id_kecamatan) {
    filters.id_kecamatan = Number(auth.user.id_kecamatan);
  }
  if (isDesaLocked.value && auth.user?.id_desa) {
    filters.id_desa = Number(auth.user.id_desa);
  }
  await Promise.all([loadMasterFilters(), loadProposals()]);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleEscapeKey);
  if (searchTimer) clearTimeout(searchTimer);
});
</script>

<template>
  <div class="w-full space-y-4">
    <!-- COMPACT COMMAND HEADER -->
    <header class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
          Verifikasi Proposal BKKD
        </h1>
        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300 tabular-nums flex flex-wrap items-center gap-x-2 gap-y-1">
          <span><strong>{{ formatNumber(summary.total_proposal, 0) }}</strong> usulan</span>
          <span aria-hidden="true">·</span>
          <span><strong>{{ formatNumber(summary.total_lokasi || 0, 0) }}</strong> ruas ({{ formatNumber(summary.total_panjang_gis || 0, 0) }} m)</span>
          <template v-if="(summary.total_nilai_usulan || 0) > 0">
            <span aria-hidden="true">·</span>
            <strong class="text-emerald-700 dark:text-emerald-300">{{ formatRupiah(summary.total_nilai_usulan, true) }}</strong>
          </template>
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <button
          type="button"
          :aria-pressed="mapPanelOpen || mobileMapModalOpen"
          class="inline-flex items-center justify-center gap-2 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg border text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
          :class="
            mapPanelOpen || mobileMapModalOpen
              ? 'border-emerald-700 bg-emerald-50 text-emerald-900 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200'
              : 'border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
          "
          @click="toggleDesktopMapPanel"
        >
          <UIcon name="i-lucide-map" class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>{{ mapPanelOpen ? 'Tutup Peta' : 'Pratinjau Peta' }}</span>
        </button>

        <button
          v-if="canCreate"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
          @click="openImportModal"
        >
          <UIcon name="i-lucide-upload" class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>Import CSV</span>
        </button>

        <button
          type="button"
          :disabled="loading"
          class="inline-flex items-center justify-center gap-2 px-3 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
          title="Muat ulang daftar proposal dan status analisa terbaru"
          @click="handleRefreshList"
        >
          <UIcon
            name="i-lucide-refresh-cw"
            class="w-4 h-4 shrink-0"
            :class="{ 'animate-spin': loading }"
            aria-hidden="true"
          />
          <span>Refresh</span>
        </button>

        <NuxtLink
          v-if="canCreate"
          to="/admin/bkkd/proposal/baru"
          class="inline-flex items-center justify-center gap-2 px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold transition-colors w-full sm:w-auto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        >
          <UIcon name="i-lucide-map-pin-plus" class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>Buat Proposal</span>
        </NuxtLink>
      </div>
    </header>

    <!-- UNIFIED STATUS & FILTER CARD -->
    <section
      aria-label="Filter dan Status Verifikasi Proposal"
      class="rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-2.5"
    >
      <!-- Row 1: Single-line Status Tabs & PRKPD/RKPD Switcher -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 pb-2.5 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 tabular-nums">
          <button
            v-for="tab in statusFilterTabs"
            :key="tab.label"
            type="button"
            :aria-pressed="filters.status_analisa_spasial === tab.key"
            class="shrink-0 inline-flex items-center gap-1.5 px-2.5 min-h-[44px] sm:min-h-[32px] rounded-lg border text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
            :class="
              filters.status_analisa_spasial === tab.key
                ? tab.activeClass
                : 'border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            "
            @click="toggleStatusFilter(tab.key)"
          >
            <span>{{ tab.label }}</span>
            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[11px] font-bold text-slate-800 dark:text-slate-100">
              {{ formatNumber(tab.count, 0) }}
            </span>
          </button>
        </div>

        <div class="flex items-center justify-between md:justify-end gap-2 shrink-0">
          <div
            role="group"
            aria-label="Filter Dokumen PRKPD atau RKPD"
            class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 p-0.5"
          >
            <button
              v-for="tag in [
                { value: '', label: 'Semua' },
                { value: 'PRKPD', label: 'PRKPD' },
                { value: 'RKPD', label: 'RKPD' },
              ]"
              :key="tag.label"
              type="button"
              :aria-pressed="filters.tagging_usulan === tag.value"
              class="px-2.5 min-h-[44px] sm:min-h-[32px] rounded-md text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
              :class="
                filters.tagging_usulan === tag.value
                  ? tag.value === 'RKPD'
                    ? 'bg-emerald-700 text-white'
                    : 'bg-sky-700 text-white'
                  : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700'
              "
              @click="filters.tagging_usulan = tag.value as '' | 'PRKPD' | 'RKPD'"
            >
              {{ tag.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Row 2: Search, TA, Bidang, Wilayah, Reset -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2 items-center">
        <!-- Search -->
        <div class="sm:col-span-2 lg:col-span-3 relative">
          <UIcon
            name="i-lucide-search"
            class="w-4 h-4 text-slate-600 dark:text-slate-300 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
            aria-hidden="true"
          />
          <input
            v-model="filters.search"
            type="text"
            aria-label="Cari judul kegiatan, nomor registrasi, atau lokasi"
            placeholder="Cari judul, nomor registrasi, lokasi..."
            class="w-full min-h-[44px] sm:min-h-[36px] pl-9 pr-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-600 dark:placeholder:text-slate-300 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
            @input="handleSearchInput"
          />
        </div>

        <!-- Tahun Anggaran -->
        <div class="lg:col-span-2">
          <select
            v-model.number="filters.tahun_anggaran"
            aria-label="Filter Tahun Anggaran"
            class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm tabular-nums text-slate-900 dark:text-slate-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
          >
            <option :value="0">Semua TA</option>
            <option v-for="y in yearOptions" :key="y" :value="y">TA {{ y }}</option>
          </select>
        </div>

        <!-- Bidang -->
        <div class="lg:col-span-2">
          <select
            v-model="filters.id_bidang"
            aria-label="Filter Bidang Kegiatan"
            class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-slate-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
          >
            <option value="">Semua Bidang</option>
            <option v-for="b in bidangList" :key="b.id" :value="b.id">
              {{ b.nama }}
            </option>
          </select>
        </div>

        <!-- Kecamatan & Desa -->
        <div class="sm:col-span-2" :class="hasActiveFilters ? 'lg:col-span-4' : 'lg:col-span-5'">
          <WilayahSelector
            :model-kecamatan="filters.id_kecamatan"
            :model-desa="filters.id_desa"
            :show-labels="false"
            :allow-all="true"
            layout="row"
            size="md"
            @change="handleWilayahFilterChange"
          />
        </div>

        <!-- Conditional Reset -->
        <div v-if="hasActiveFilters" class="sm:col-span-2 lg:col-span-1">
          <button
            type="button"
            class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 rounded-lg border border-slate-400 dark:border-slate-600 text-xs font-semibold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>
    </section>

    <!-- ERROR STATE -->
    <div
      v-if="errorState"
      role="alert"
      class="p-4 rounded-xl border border-rose-300 bg-rose-50 dark:bg-rose-950/40 dark:border-rose-800 text-rose-900 dark:text-rose-200 flex flex-wrap items-center justify-between gap-4 text-xs"
    >
      <div>
        <p class="font-bold">Gagal Memuat Daftar Proposal</p>
        <p class="mt-0.5">{{ errorState }}</p>
      </div>
      <button
        type="button"
        class="px-4 min-h-[44px] sm:min-h-[36px] rounded-lg bg-rose-700 text-white font-semibold hover:bg-rose-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700"
        @click="loadProposals"
      >
        Muat Ulang Data
      </button>
    </div>

    <!-- MAIN WORKSPACE: DATA LIST + OPTIONAL SPLIT MAP PREVIEW -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
      <section
        :class="mapPanelOpen ? 'lg:col-span-7' : 'lg:col-span-12'"
        class="rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden"
        aria-label="Daftar Usulan Proposal BKKD"
      >
        <!-- Non-destructive background refresh bar when reloading existing data -->
        <div
          v-if="loading && hasLoadedOnce && proposals.length > 0"
          role="status"
          class="px-4 py-2 bg-sky-50 dark:bg-sky-950/60 border-b border-sky-200 dark:border-sky-800 flex items-center gap-2 text-xs font-semibold text-sky-900 dark:text-sky-200"
        >
          <UIcon name="i-lucide-loader-2" class="w-4 h-4 animate-spin shrink-0" aria-hidden="true" />
          <span>Memperbarui daftar proposal dan ringkasan spasial...</span>
        </div>

        <!-- Initial Skeleton State (Zero height collapse) -->
        <div
          v-if="loading && proposals.length === 0"
          role="status"
          aria-label="Memuat daftar proposal"
          class="divide-y divide-slate-200 dark:divide-slate-800"
        >
          <div
            v-for="idx in 4"
            :key="idx"
            class="p-4 space-y-2.5 animate-pulse motion-reduce:animate-none"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="h-4 w-44 rounded bg-slate-200 dark:bg-slate-800" />
              <div class="h-5 w-28 rounded bg-slate-200 dark:bg-slate-800" />
            </div>
            <div class="h-4 w-3/4 rounded bg-slate-200 dark:bg-slate-800" />
            <div class="h-3 w-1/2 rounded bg-slate-200 dark:bg-slate-800" />
          </div>
          <span class="sr-only">Memuat daftar proposal dan ringkasan analisa spasial...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="proposals.length === 0" class="py-12 px-4 text-center space-y-2.5">
          <UIcon name="i-lucide-map" class="w-9 h-9 text-slate-600 dark:text-slate-300 mx-auto" aria-hidden="true" />
          <p class="text-sm font-bold text-slate-900 dark:text-slate-100">
            {{ hasActiveFilters ? 'Tidak Ada Proposal yang Cocok dengan Filter' : 'Belum Ada Data Proposal BKKD' }}
          </p>
          <p class="text-xs text-slate-600 dark:text-slate-300 max-w-md mx-auto">
            {{
              hasActiveFilters
                ? 'Ubah filter tahun anggaran, PRKPD/RKPD, wilayah, atau kata kunci untuk melihat usulan lainnya.'
                : 'Mulai buat usulan baru di peta atau impor daftar usulan awal dari file CSV untuk menjalankan penyandingan otomatis.'
            }}
          </p>
          <div class="pt-2 flex flex-wrap items-center justify-center gap-2">
            <button
              v-if="hasActiveFilters"
              type="button"
              class="inline-flex items-center gap-1.5 px-4 min-h-[44px] sm:min-h-[38px] rounded-lg border border-slate-400 dark:border-slate-600 text-xs font-semibold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
              @click="resetFilters"
            >
              Reset Filter
            </button>
            <NuxtLink
              v-if="canCreate"
              to="/admin/bkkd/proposal/baru"
              class="inline-flex items-center gap-1.5 px-4 min-h-[44px] sm:min-h-[38px] rounded-lg bg-sky-700 text-white text-xs font-semibold hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            >
              <UIcon name="i-lucide-map-pin-plus" class="w-4 h-4" aria-hidden="true" />
              <span>Buat Proposal di Peta</span>
            </NuxtLink>
          </div>
        </div>

        <template v-else>
          <!-- Mobile (< 640px: 1 col) & Tablet (640px-1023px: 2 col grid) Card Layout -->
          <div
            class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-3 p-3.5 transition-opacity"
            :class="{ 'opacity-60 pointer-events-none': loading }"
          >
            <article
              v-for="item in proposals"
              :key="`card-${item.id}`"
              class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-3.5 flex flex-col justify-between gap-3"
              :class="
                previewProposal?.id === item.id
                  ? 'ring-2 ring-sky-700 dark:ring-sky-500'
                  : ''
              "
            >
              <div class="space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                    <span
                      class="px-1.5 py-0.5 rounded text-[11px] font-semibold border tabular-nums"
                      :class="
                        item.tagging_usulan === 'RKPD'
                          ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-200 dark:border-emerald-700'
                          : 'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/70 dark:text-sky-200 dark:border-sky-700'
                      "
                    >
                      {{ item.tagging_usulan || 'PRKPD' }} {{ item.tahun_anggaran }}
                    </span>
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 tabular-nums">
                      {{ item.nomor_registrasi }}
                    </span>
                  </div>
                  <span
                    class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border"
                    :class="getSpatialStatusMeta(item.status_analisa_spasial).badgeClass"
                  >
                    {{ getSpatialStatusMeta(item.status_analisa_spasial).shortLabel }}
                  </span>
                </div>

                <div>
                  <NuxtLink
                    :to="`/admin/bkkd/proposal/${item.id}`"
                    class="block text-sm font-bold text-slate-900 dark:text-slate-100 hover:text-sky-700 dark:hover:text-sky-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 rounded"
                  >
                    {{ item.judul_kegiatan }}
                  </NuxtLink>
                  <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                    Desa {{ item.nama_desa || '-' }}, Kec. {{ item.nama_kecamatan || '-' }} · {{ item.nama_bidang || 'Infrastruktur' }}
                  </p>
                </div>

                <div class="grid grid-cols-2 gap-2 p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs tabular-nums">
                  <div>
                    <span class="block text-[11px] text-slate-600 dark:text-slate-300">Nilai Usulan</span>
                    <strong class="text-slate-900 dark:text-slate-100">
                      {{ formatRupiah(item.nilai_usulan, true) }}
                    </strong>
                  </div>
                  <div>
                    <span class="block text-[11px] text-slate-600 dark:text-slate-300">Rute & Panjang</span>
                    <strong class="text-slate-900 dark:text-slate-100">
                      {{ item.jumlah_lokasi || item.lokasi?.length || 0 }} lokasi · {{ formatNumber(item.total_panjang_gis || 0, 0) }} m
                    </strong>
                  </div>
                </div>

                <!-- Built ratio progress & cross-year alert -->
                <div class="space-y-1.5 text-xs">
                  <div v-if="(item.total_panjang_gis || 0) > 0" class="space-y-1">
                    <div class="flex items-center justify-between text-[11px] text-slate-600 dark:text-slate-300 tabular-nums">
                      <span>Terbangun: {{ formatNumber(extractProposalInsight(item).totalIrisanSegmen, 0) }} m</span>
                      <span>Sisa: {{ formatNumber(extractProposalInsight(item).sisaBelumTerbangun, 0) }} m</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden" aria-hidden="true">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="
                          extractProposalInsight(item).builtRatioPct >= 90
                            ? 'bg-rose-600'
                            : extractProposalInsight(item).builtRatioPct > 0
                              ? 'bg-amber-600'
                              : 'bg-emerald-600'
                        "
                        :style="{ width: `${extractProposalInsight(item).builtRatioPct}%` }"
                      />
                    </div>
                  </div>

                  <div
                    v-if="extractProposalInsight(item).crossRefCount > 0"
                    class="px-2.5 py-1.5 rounded-md border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/60 text-[11px] font-semibold text-amber-900 dark:text-amber-200 tabular-nums"
                  >
                    {{ extractProposalInsight(item).crossRefLabel }}
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  :aria-pressed="previewProposal?.id === item.id && (mapPanelOpen || mobileMapModalOpen)"
                  class="inline-flex items-center justify-center gap-1.5 px-3 min-h-[44px] rounded-lg border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                  @click="openMapPreviewForProposal(item)"
                >
                  <UIcon name="i-lucide-eye" class="w-4 h-4" aria-hidden="true" />
                  <span>Peta Mini</span>
                </button>

                <NuxtLink
                  :to="`/admin/bkkd/proposal/${item.id}`"
                  class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 min-h-[44px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                >
                  <UIcon name="i-lucide-map" class="w-4 h-4" aria-hidden="true" />
                  <span>Buka Workspace</span>
                </NuxtLink>

                <button
                  type="button"
                  :disabled="reanalyzingId === item.id"
                  class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                  title="Analisa Ulang Spasial"
                  aria-label="Analisa Ulang Spasial"
                  @click="handleReanalyzeRow(item)"
                >
                  <UIcon
                    :name="reanalyzingId === item.id ? 'i-lucide-loader-2' : 'i-lucide-refresh-cw'"
                    class="w-4 h-4"
                    :class="{ 'animate-spin': reanalyzingId === item.id }"
                    aria-hidden="true"
                  />
                </button>

                <button
                  v-if="canDelete"
                  type="button"
                  :disabled="deletingId === item.id"
                  class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-rose-700 hover:border-rose-400 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                  title="Hapus Proposal"
                  aria-label="Hapus Proposal"
                  @click="promptDeleteRow(item)"
                >
                  <UIcon name="i-lucide-trash-2" class="w-4 h-4" aria-hidden="true" />
                </button>
              </div>
            </article>
          </div>

          <!-- Desktop Verification Table (>= 1024px) without row-level click trap -->
          <div
            class="hidden lg:block overflow-x-auto transition-opacity"
            :class="{ 'opacity-60 pointer-events-none': loading }"
          >
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950/60 text-xs font-semibold text-slate-700 dark:text-slate-200">
                  <th class="py-3 px-4">Usulan & Wilayah</th>
                  <th class="py-3 px-4">Anggaran & Panjang</th>
                  <th class="py-3 px-4">Koridor & Penyandingan</th>
                  <th class="py-3 px-4">Status Spasial</th>
                  <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                <tr
                  v-for="item in proposals"
                  :key="item.id"
                  class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                  :class="
                    previewProposal?.id === item.id && mapPanelOpen
                      ? 'bg-sky-50/70 dark:bg-sky-950/30'
                      : ''
                  "
                >
                  <!-- Col 1: Proposal, Tagging, Wilayah -->
                  <td class="py-3.5 px-4 align-top max-w-[300px]">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span
                        class="px-1.5 py-0.5 rounded text-[11px] font-semibold border tabular-nums"
                        :class="
                          item.tagging_usulan === 'RKPD'
                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-200 dark:border-emerald-700'
                            : 'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/70 dark:text-sky-200 dark:border-sky-700'
                        "
                      >
                        {{ item.tagging_usulan || 'PRKPD' }} {{ item.tahun_anggaran }}
                      </span>
                      <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 tabular-nums">
                        {{ item.nomor_registrasi }}
                      </span>
                      <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[11px] font-medium text-slate-700 dark:text-slate-200">
                        {{ item.nama_bidang || 'Infrastruktur' }}
                      </span>
                    </div>

                    <NuxtLink
                      :to="`/admin/bkkd/proposal/${item.id}`"
                      class="mt-1.5 inline-block text-sm font-bold text-slate-900 dark:text-slate-100 hover:text-sky-700 dark:hover:text-sky-300 line-clamp-2 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                    >
                      {{ item.judul_kegiatan }}
                    </NuxtLink>

                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                      Desa {{ item.nama_desa || '-' }}, Kec. {{ item.nama_kecamatan || '-' }}
                    </p>
                  </td>

                  <!-- Col 2: Budget & Corridor Metrics -->
                  <td class="py-3.5 px-4 align-top tabular-nums whitespace-nowrap">
                    <div class="font-bold text-slate-900 dark:text-slate-100">
                      {{ formatRupiah(item.nilai_usulan, true) }}
                    </div>
                    <div class="mt-0.5 text-xs text-slate-700 dark:text-slate-200">
                      {{ item.jumlah_lokasi || item.lokasi?.length || 0 }} lokasi ·
                      <strong>{{ formatNumber(item.total_panjang_gis || 0, 0) }} m</strong>
                    </div>

                    <div v-if="(item.total_panjang_gis || 0) > 0" class="mt-2 w-36 space-y-1">
                      <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden" aria-hidden="true">
                        <div
                          class="h-full rounded-full"
                          :class="
                            extractProposalInsight(item).builtRatioPct >= 90
                              ? 'bg-rose-600'
                              : extractProposalInsight(item).builtRatioPct > 0
                                ? 'bg-amber-600'
                                : 'bg-emerald-600'
                          "
                          :style="{ width: `${extractProposalInsight(item).builtRatioPct}%` }"
                        />
                      </div>
                      <div class="text-[11px] text-slate-600 dark:text-slate-300">
                        Sisa {{ formatNumber(extractProposalInsight(item).sisaBelumTerbangun, 0) }} m
                      </div>
                    </div>
                  </td>

                  <!-- Col 3: Poros, Segmen, Cross-Year PRKPD/RKPD Signals -->
                  <td class="py-3.5 px-4 align-top space-y-1.5 max-w-[270px]">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span
                        v-if="extractProposalInsight(item).ruasList.length > 0"
                        class="inline-flex items-center px-2 py-0.5 rounded bg-sky-50 dark:bg-sky-950/70 text-sky-900 dark:text-sky-200 text-[11px] font-semibold"
                      >
                        Poros Desa ({{ formatNumber(extractProposalInsight(item).totalIrisanPoros, 0) }} m)
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-[11px] font-semibold"
                      >
                        {{ extractProposalInsight(item).nonPorosLabel }}
                      </span>
                      <span
                        v-if="extractProposalInsight(item).kabupatenList.length > 0"
                        class="inline-flex items-center px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/70 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-800 text-[11px] font-semibold"
                      >
                        Jalan Kabupaten ({{ formatNumber(extractProposalInsight(item).totalIrisanKabupaten, 0) }} m)
                      </span>
                    </div>

                    <p
                      v-if="extractProposalInsight(item).ruasList.length > 0"
                      class="text-[11px] text-slate-600 dark:text-slate-300 line-clamp-1"
                    >
                      {{ extractProposalInsight(item).ruasList.join(', ') }}
                    </p>
                    <p
                      v-if="extractProposalInsight(item).kabupatenList.length > 0"
                      class="text-[11px] font-medium text-rose-700 dark:text-rose-300 line-clamp-1"
                    >
                      Kab: {{ extractProposalInsight(item).kabupatenList.join(', ') }}
                    </p>

                    <div class="text-[11px] tabular-nums">
                      <template v-if="extractProposalInsight(item).segmenCount > 0">
                        <span class="font-semibold text-amber-800 dark:text-amber-300">
                          {{ extractProposalInsight(item).segmenCount }} segmen terbangun ({{ formatNumber(extractProposalInsight(item).totalIrisanSegmen, 0) }} m)
                        </span>
                      </template>
                      <template v-else>
                        <span class="font-semibold text-emerald-700 dark:text-emerald-300">
                          0 m irisan segmen eksisting
                        </span>
                      </template>
                    </div>

                    <div
                      v-if="extractProposalInsight(item).crossRefCount > 0"
                      class="inline-flex items-center px-2 py-0.5 rounded border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/60 text-[11px] font-semibold text-amber-900 dark:text-amber-200 tabular-nums"
                    >
                      {{ extractProposalInsight(item).crossRefLabel }}
                    </div>
                  </td>

                  <!-- Col 4: Spatial Status -->
                  <td class="py-3.5 px-4 align-top">
                    <span
                      class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold border whitespace-nowrap"
                      :class="getSpatialStatusMeta(item.status_analisa_spasial).badgeClass"
                    >
                      {{ getSpatialStatusMeta(item.status_analisa_spasial).label }}
                    </span>
                  </td>

                  <!-- Col 5: Keyboard-Accessible Actions -->
                  <td class="py-3.5 px-4 align-top text-right">
                    <div class="inline-flex items-center justify-end gap-1.5">
                      <button
                        type="button"
                        :aria-pressed="previewProposal?.id === item.id && mapPanelOpen"
                        class="inline-flex items-center justify-center min-w-[36px] min-h-[36px] px-2.5 rounded-lg border text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                        :class="
                          previewProposal?.id === item.id && mapPanelOpen
                            ? 'border-emerald-700 bg-emerald-50 text-emerald-900 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200'
                            : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
                        "
                        title="Lihat Pratinjau Koridor di Peta Samping"
                        aria-label="Lihat Pratinjau Koridor di Peta Samping"
                        @click="openMapPreviewForProposal(item)"
                      >
                        <UIcon name="i-lucide-eye" class="w-4 h-4" aria-hidden="true" />
                      </button>

                      <NuxtLink
                        :to="`/admin/bkkd/proposal/${item.id}`"
                        class="inline-flex items-center gap-1.5 px-3 min-h-[36px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                      >
                        <UIcon name="i-lucide-map" class="w-3.5 h-3.5" aria-hidden="true" />
                        <span>Buka Peta</span>
                      </NuxtLink>

                      <button
                        type="button"
                        :disabled="reanalyzingId === item.id"
                        class="inline-flex items-center justify-center min-w-[36px] min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                        title="Analisa Ulang Spasial"
                        aria-label="Analisa Ulang Spasial"
                        @click="handleReanalyzeRow(item)"
                      >
                        <UIcon
                          :name="reanalyzingId === item.id ? 'i-lucide-loader-2' : 'i-lucide-refresh-cw'"
                          class="w-3.5 h-3.5"
                          :class="{ 'animate-spin': reanalyzingId === item.id }"
                          aria-hidden="true"
                        />
                      </button>

                      <button
                        v-if="canDelete"
                        type="button"
                        :disabled="deletingId === item.id"
                        class="inline-flex items-center justify-center min-w-[36px] min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-rose-700 hover:border-rose-400 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                        title="Hapus Proposal"
                        aria-label="Hapus Proposal"
                        @click="promptDeleteRow(item)"
                      >
                        <UIcon name="i-lucide-trash-2" class="w-3.5 h-3.5" aria-hidden="true" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- PAGINATION FOOTER -->
        <footer
          v-if="meta.last_page > 1"
          class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-700 dark:text-slate-200"
        >
          <span class="tabular-nums">
            Halaman {{ meta.current_page }} dari {{ meta.last_page }} (Total {{ meta.total }} proposal)
          </span>
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="meta.current_page <= 1"
              class="px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-400 dark:border-slate-600 disabled:opacity-40 font-semibold cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
              @click="
                filters.page = meta.current_page - 1;
                loadProposals();
              "
            >
              Sebelumnya
            </button>
            <button
              type="button"
              :disabled="meta.current_page >= meta.last_page"
              class="px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-slate-400 dark:border-slate-600 disabled:opacity-40 font-semibold cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
              @click="
                filters.page = meta.current_page + 1;
                loadProposals();
              "
            >
              Berikutnya
            </button>
          </div>
        </footer>
      </section>

      <!-- DESKTOP STICKY MAP PREVIEW SPLIT PANE (>= 1024px) -->
      <div v-if="mapPanelOpen" class="hidden lg:block lg:col-span-5 lg:sticky lg:top-4">
        <BkkdProposalPreviewMap
          :proposal="previewProposal"
          @close="mapPanelOpen = false"
        />
      </div>
    </div>

    <!-- MOBILE / TABLET MAP PREVIEW DIALOG (< 1024px) -->
    <div
      v-if="mobileMapModalOpen"
      role="dialog"
      aria-modal="true"
      aria-label="Pratinjau Peta Koridor Proposal"
      class="fixed inset-0 z-50 lg:hidden flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/65"
      @click.self="closeMobileMapModal"
    >
      <div class="w-full sm:max-w-xl max-h-[92dvh] overflow-y-auto rounded-t-2xl sm:rounded-xl bg-white dark:bg-slate-900 shadow-xl">
        <div class="sr-only">
          <button ref="mobileMapCloseBtnRef" type="button" @click="closeMobileMapModal">
            Tutup Pratinjau Peta
          </button>
        </div>
        <BkkdProposalPreviewMap
          :proposal="previewProposal"
          @close="closeMobileMapModal"
        />
      </div>
    </div>

    <!-- IMPORT CSV MODAL -->
    <div
      v-if="importModalOpen"
      role="dialog"
      aria-modal="true"
      aria-labelledby="import-csv-dialog-title"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65"
      @click.self="closeImportModal"
    >
      <div class="w-full max-w-lg rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-xl space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 id="import-csv-dialog-title" class="text-base font-bold text-slate-900 dark:text-slate-100">
              Import Usulan Proposal (CSV)
            </h2>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
              Unggah berkas CSV berisi daftar usulan desa untuk dibuatkan nomor registrasi otomatis sebelum digitasi rute di peta.
            </p>
          </div>
          <button
            ref="importCloseBtnRef"
            type="button"
            class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] sm:min-w-[36px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            aria-label="Tutup dialog import CSV"
            @click="closeImportModal"
          >
            <UIcon name="i-lucide-x" class="w-4 h-4" aria-hidden="true" />
          </button>
        </div>

        <div class="p-3 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 flex flex-wrap items-center justify-between gap-2">
          <span>Gunakan format kolom resmi agar kecamatan, desa, dan bidang dikenali otomatis.</span>
          <a
            :href="bkkdApi.getProposalImportTemplateUrl()"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 font-semibold text-sky-700 dark:text-sky-300 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 rounded"
          >
            <UIcon name="i-lucide-download" class="w-3.5 h-3.5" aria-hidden="true" />
            <span>Unduh Template CSV</span>
          </a>
        </div>

        <div>
          <label for="bkkd-csv-upload" class="block text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
            Pilih Berkas CSV (.csv)
          </label>
          <input
            id="bkkd-csv-upload"
            type="file"
            accept=".csv,text/csv"
            class="w-full min-h-[44px] px-3 py-2 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-sky-700 file:text-white file:text-xs file:font-semibold file:cursor-pointer cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            @change="onImportFileSelected"
          />
        </div>

        <div
          v-if="importResult"
          class="p-3 rounded-lg border text-xs space-y-1.5"
          :class="
            importResult.failed_count > 0
              ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200'
              : 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200'
          "
        >
          <p class="font-bold">
            Berhasil: {{ importResult.imported_count }} · Dilewati/Gagal: {{ importResult.failed_count }}
          </p>
          <ul v-if="importResult.errors.length > 0" class="list-disc pl-4 space-y-0.5 max-h-32 overflow-y-auto">
            <li v-for="(err, idx) in importResult.errors.slice(0, 8)" :key="idx">
              Baris {{ err.baris }} ({{ err.judul || '-' }}): {{ err.pesan }}
            </li>
          </ul>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
          <button
            type="button"
            :disabled="importingCsv"
            class="px-3.5 min-h-[44px] sm:min-h-[38px] rounded-lg border border-slate-400 dark:border-slate-600 text-xs font-semibold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            @click="closeImportModal"
          >
            Tutup
          </button>
          <button
            type="button"
            :disabled="!importFile || importingCsv"
            class="inline-flex items-center gap-1.5 px-4 min-h-[44px] sm:min-h-[38px] rounded-lg bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white text-xs font-semibold cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            @click="submitImportCsv"
          >
            <UIcon
              v-if="importingCsv"
              name="i-lucide-loader-2"
              class="w-4 h-4 animate-spin"
              aria-hidden="true"
            />
            <span>Mulai Import CSV</span>
          </button>
        </div>
      </div>
    </div>

    <!-- INLINE DELETE CONFIRMATION DIALOG -->
    <div
      v-if="confirmDeleteTarget"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-proposal-dialog-title"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65"
      @click.self="closeDeleteDialog"
    >
      <div class="w-full max-w-md rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-xl space-y-4">
        <div class="space-y-1.5">
          <h2 id="delete-proposal-dialog-title" class="text-base font-bold text-slate-900 dark:text-slate-100">
            Hapus Data Proposal?
          </h2>
          <p class="text-xs text-slate-600 dark:text-slate-300">
            Proposal
            <strong class="text-slate-900 dark:text-slate-100">{{ confirmDeleteTarget.judul_kegiatan }}</strong>
            (<span class="font-semibold tabular-nums">{{ confirmDeleteTarget.nomor_registrasi }}</span>) beserta seluruh rute lokasi dan hasil analisa spasialnya akan dihapus.
          </p>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
          <button
            ref="deleteCancelBtnRef"
            type="button"
            :disabled="Boolean(deletingId)"
            class="px-3.5 min-h-[44px] sm:min-h-[38px] rounded-lg border border-slate-400 dark:border-slate-600 text-xs font-semibold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            @click="closeDeleteDialog"
          >
            Batal
          </button>
          <button
            type="button"
            :disabled="Boolean(deletingId)"
            class="inline-flex items-center gap-1.5 px-4 min-h-[44px] sm:min-h-[38px] rounded-lg bg-rose-700 hover:bg-rose-800 disabled:opacity-50 text-white text-xs font-semibold cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700"
            @click="confirmDeleteRow"
          >
            <UIcon
              v-if="deletingId"
              name="i-lucide-loader-2"
              class="w-4 h-4 animate-spin"
              aria-hidden="true"
            />
            <span>Hapus Proposal</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

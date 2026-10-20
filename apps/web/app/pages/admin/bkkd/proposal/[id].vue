<script setup lang="ts">
import type { BkkdProposal, BkkdRiwayat } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import { usePermission } from '~/composables/usePermission';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';
import BkkdProposalFormModal from '~/components/bkkd/BkkdProposalFormModal.vue';
import BkkdVerifikasiFormModal from '~/components/bkkd/BkkdVerifikasiFormModal.vue';
import BkkdPenetapanTapdModal from '~/components/bkkd/BkkdPenetapanTapdModal.vue';
import BkkdAjukanOpdModal from '~/components/bkkd/BkkdAjukanOpdModal.vue';
import BkkdBatalkanModal from '~/components/bkkd/BkkdBatalkanModal.vue';
import BkkdHapusProposalModal from '~/components/bkkd/BkkdHapusProposalModal.vue';
import BkkdLampiranList from '~/components/bkkd/BkkdLampiranList.vue';
import BkkdRiwayatTimeline from '~/components/bkkd/BkkdRiwayatTimeline.vue';
import BkkdSandinganPapbdCard from '~/components/bkkd/BkkdSandinganPapbdCard.vue';
import BkkdGantiPapbdModal from '~/components/bkkd/BkkdGantiPapbdModal.vue';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'bkkd-proposal-view',
});

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { can } = usePermission();
const api = useBkkdApi();

const proposalId = computed(() => String(route.params.id));

const canUpdate = computed(() => can('bkkd-proposal-update') && !proposal.value?.deleted_at && !proposal.value?.is_deleted);
const canDelete = computed(() => can('bkkd-proposal-delete') && !proposal.value?.deleted_at && !proposal.value?.is_deleted);
const canVerifikasi = computed(() => can('bkkd-verifikasi-manage') && !proposal.value?.deleted_at && !proposal.value?.is_deleted);

const proposal = ref<BkkdProposal | null>(null);
const riwayatList = ref<BkkdRiwayat[]>([]);
const loading = ref(true);
const loadingRiwayat = ref(false);

export type TabKey = 'rincian' | 'verifikasi' | 'berkas' | 'linimasa';

function parseInitialTab(val?: string | null): TabKey {
  if (!val) return 'rincian';
  const v = val.toLowerCase();
  if (v === 'rincian' || v === 'detail') return 'rincian';
  if (v === 'verifikasi') return 'verifikasi';
  if (v === 'berkas' || v === 'lampiran') return 'berkas';
  if (v === 'linimasa' || v === 'riwayat') return 'linimasa';
  return 'rincian';
}

const activeTab = ref<TabKey>(parseInitialTab(route.query.tab as string));

// Horizontal scroll tab navigation
const tabListRef = ref<HTMLElement | null>(null);
const tabElementRefs = ref<Record<string, HTMLElement | null>>({});
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

function setTabRef(id: string, el: any) {
  if (el) {
    tabElementRefs.value[id] = el as HTMLElement;
  }
}

function checkScroll() {
  const el = tabListRef.value;
  if (!el) return;
  canScrollLeft.value = el.scrollLeft > 6;
  canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 6;
}

function scrollTabList(direction: 'left' | 'right') {
  const el = tabListRef.value;
  if (!el) return;
  const scrollAmount = 200;
  el.scrollBy({
    left: direction === 'left' ? -scrollAmount : scrollAmount,
    behavior: 'smooth',
  });
}

function selectTab(tabId: TabKey) {
  activeTab.value = tabId;
  router.replace({
    query: {
      ...route.query,
      tab: tabId,
    },
  });
  nextTick(() => {
    tabElementRefs.value[tabId]?.scrollIntoView({
      behavior: 'smooth',
      inline: 'center',
      block: 'nearest',
    });
    checkScroll();
  });
}

function handleTabKeydown(e: KeyboardEvent, currentId: TabKey) {
  const tabs: TabKey[] = ['rincian', 'verifikasi', 'berkas', 'linimasa'];
  const currentIndex = tabs.indexOf(currentId);
  if (e.key === 'ArrowRight') {
    e.preventDefault();
    const nextIndex = (currentIndex + 1) % tabs.length;
    selectTab(tabs[nextIndex]);
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault();
    const prevIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    selectTab(tabs[prevIndex]);
  } else if (e.key === 'Home') {
    e.preventDefault();
    selectTab(tabs[0]);
  } else if (e.key === 'End') {
    e.preventDefault();
    selectTab(tabs[tabs.length - 1]);
  }
}

const tabsList = computed(() => [
  {
    id: 'rincian' as TabKey,
    label: 'Rincian Usulan',
    icon: 'i-lucide-file-text',
    badge: null,
  },
  {
    id: 'verifikasi' as TabKey,
    label: 'Hasil Verifikasi',
    icon: 'i-lucide-clipboard-check',
    badge: proposal.value?.semua_verifikasi?.length || (proposal.value?.verifikasi_aktif ? 1 : 0),
  },
  {
    id: 'berkas' as TabKey,
    label: 'Berkas',
    icon: 'i-lucide-paperclip',
    badge: proposal.value?.lampiran?.length || 0,
  },
  {
    id: 'linimasa' as TabKey,
    label: 'Linimasa',
    icon: 'i-lucide-history',
    badge: riwayatList.value?.length || null,
  },
]);

// Modals
const modalEditOpen = ref(false);
const modalVerifOpen = ref(false);
const modalTapdOpen = ref(false);
const modalAjukanOpen = ref(false);
const modalBatalkanOpen = ref(false);
const modalHapusOpen = ref(false);
const modalGantiPapbdOpen = ref(false);

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

function formatVolume(val?: number | string | null, unit?: string | null) {
  if (val === null || val === undefined || val === '') return '-';
  const num = Number(val);
  if (isNaN(num)) return '-';
  const formatted = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 2,
  }).format(num);
  return unit ? `${formatted} ${unit}` : formatted;
}

async function loadProposal() {
  loading.value = true;
  try {
    const res = await api.getProposal(proposalId.value);
    proposal.value = res.data;
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Proposal Tidak Ditemukan',
      description: err?.data?.message || 'Data proposal tidak dapat dimuat.',
      color: 'error',
    });
    router.push('/admin/bkkd/proposal');
  } finally {
    loading.value = false;
  }
}

async function loadRiwayat() {
  loadingRiwayat.value = true;
  try {
    const res = await api.getProposalRiwayat(proposalId.value);
    riwayatList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat riwayat:', err);
  } finally {
    loadingRiwayat.value = false;
  }
}

watch(activeTab, (tab) => {
  if (tab === 'linimasa' && riwayatList.value.length === 0) {
    loadRiwayat();
  }
});

function handleAjukanOpd() {
  modalAjukanOpen.value = true;
}

function handleBatalkan() {
  modalBatalkanOpen.value = true;
}

function handleDelete() {
  modalHapusOpen.value = true;
}

function handleDeletedSuccess() {
  router.push('/admin/bkkd/proposal');
}

function handleGantiSuccess(newProposal?: BkkdProposal) {
  if (newProposal?.id) {
    router.push(`/admin/bkkd/proposal/${newProposal.id}`);
  } else {
    loadProposal();
    loadRiwayat();
  }
}

onMounted(() => {
  loadProposal();
  if (activeTab.value === 'linimasa') {
    loadRiwayat();
  }
  nextTick(() => {
    checkScroll();
    window.addEventListener('resize', checkScroll);
  });
});

onUnmounted(() => {
  window.removeEventListener('resize', checkScroll);
});
</script>

<template>
  <div class="space-y-6">
    <!-- Loading Skeleton -->
    <div v-if="loading" class="p-12 text-center text-gray-500 text-xs">
      <UIcon name="i-lucide-loader-2" class="w-8 h-8 animate-spin mx-auto text-primary-500 mb-2" />
      Memuat rincian proposal...
    </div>

    <div v-else-if="proposal" class="space-y-6">
      <!-- Banner Jika Usulan Dihapus -->
      <div v-if="proposal.deleted_at || proposal.is_deleted" class="p-4 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/50 text-rose-600 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-trash-2" class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-rose-800 dark:text-rose-200">Usulan Proposal Telah Dihapus (Soft Delete)</h3>
            <p class="text-rose-600 dark:text-rose-400 mt-0.5">
              Data proposal ini berada dalam arsip tempat sampah dan hanya dapat dilihat (read-only). Tindakan pengajuan dan verifikasi dinonaktifkan.
            </p>
          </div>
        </div>
        <UBadge color="error" size="sm" variant="subtle" class="font-semibold shrink-0">
          Dihapus: {{ formatDate(proposal.deleted_at || '') }}
        </UBadge>
      </div>

      <!-- Header Banner Proposal -->
      <div class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs font-mono font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40 px-2 py-0.5 rounded">
                {{ proposal.nomor_registrasi }}
              </span>
              <BkkdStatusBadge :status="proposal.status" size="sm" />
              <UBadge color="neutral" size="xs" variant="outline">
                T.A. {{ proposal.tahun_anggaran }}
              </UBadge>
              <UBadge color="neutral" size="xs" variant="subtle" class="font-mono">
                versi #{{ proposal.versi }}
              </UBadge>
            </div>

            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
              {{ proposal.judul_kegiatan }}
            </h1>

            <p class="text-xs text-gray-500 flex items-center gap-2">
              <span>
                <UIcon name="i-lucide-map-pin" class="w-3.5 h-3.5 inline text-gray-400 mr-1" />
                Desa {{ proposal.nama_desa }}, Kec. {{ proposal.nama_kecamatan }}
              </span>
              <span>•</span>
              <span class="inline-flex items-center gap-1 font-medium text-primary-600 dark:text-primary-400">
                <UIcon name="i-lucide-layers" class="w-3.5 h-3.5 shrink-0" />
                <span>Bidang: {{ proposal.nama_bidang || proposal.bidang?.nama || '-' }}</span>
              </span>
            </p>
          </div>

          <!-- Quick Actions -->
          <div class="flex flex-wrap items-center gap-2">
            <UButton
              v-if="canUpdate"
              color="neutral"
              variant="outline"
              size="sm"
              icon="i-lucide-edit"
              label="Edit Usulan"
              @click="modalEditOpen = true"
            />

            <UButton
              v-if="canUpdate && proposal.status === 'draft'"
              color="info"
              size="sm"
              icon="i-lucide-send"
              label="Ajukan ke OPD"
              @click="handleAjukanOpd"
            />

            <UButton
              v-if="canVerifikasi && ['diajukan_opd', 'dikembalikan'].includes(proposal.status)"
              color="primary"
              size="sm"
              icon="i-lucide-clipboard-check"
              label="Catat Hasil Telaah OPD"
              @click="modalVerifOpen = true"
            />

            <UButton
              v-if="canUpdate && ['layak', 'layak_bersyarat'].includes(proposal.status)"
              color="success"
              size="sm"
              icon="i-lucide-award"
              label="Tetapkan TAPD"
              @click="modalTapdOpen = true"
            />

            <!-- Tombol Pengajuan Pergantian P-APBD: jika usulan induk berstatus layak atau ditetapkan TAPD, dan belum digantikan -->
            <UButton
              v-if="canUpdate && ['ditetapkan_tapd', 'layak', 'layak_bersyarat'].includes(proposal.status) && (proposal.tahap_anggaran || 'induk') === 'induk' && (!proposal.proposal_pengganti || proposal.proposal_pengganti.length === 0)"
              color="warning"
              size="sm"
              icon="i-lucide-arrow-right-left"
              label="Ajukan Pergantian P-APBD"
              @click="modalGantiPapbdOpen = true"
            />

            <UButton
              v-if="canUpdate && !['ditetapkan_tapd', 'dibatalkan'].includes(proposal.status)"
              color="error"
              variant="ghost"
              size="sm"
              icon="i-lucide-ban"
              label="Batalkan"
              @click="handleBatalkan"
            />

            <UButton
              v-if="canDelete && ['draft', 'dibatalkan'].includes(proposal.status)"
              color="error"
              variant="ghost"
              size="sm"
              icon="i-lucide-trash-2"
              label="Hapus Usulan"
              @click="handleDelete"
            />
          </div>
        </div>

        <!-- Highlight Ringkasan Anggaran -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-gray-100 dark:border-white/[0.04]">
          <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
            <span class="text-[11px] text-gray-500">Nilai Usulan Desa</span>
            <div class="text-base font-bold font-mono text-gray-900 dark:text-gray-100">
              {{ proposal.nilai_usulan !== null && proposal.nilai_usulan !== undefined ? formatCurrency(proposal.nilai_usulan) : '-' }}
            </div>
            <div v-if="(proposal.volume ?? proposal.volume_target) !== null && (proposal.volume ?? proposal.volume_target) !== undefined" class="text-[11px] text-gray-500 mt-1 flex flex-wrap items-center gap-1">
              <span>Target:</span>
              <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">
                {{ formatVolume(proposal.volume ?? proposal.volume_target, proposal.satuan || proposal.satuan_target) }}
              </span>
              <span v-if="proposal.panjang || proposal.lebar || proposal.tinggi" class="text-[10px] text-gray-400 font-mono">
                ({{ proposal.panjang ? `${proposal.panjang}m` : '-' }} × {{ proposal.lebar ? `${proposal.lebar}m` : '-' }} × {{ proposal.tinggi ? `${proposal.tinggi}m` : '-' }})
              </span>
            </div>
          </div>

          <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
            <span class="text-[11px] text-gray-500">Rekomendasi OPD Teknis</span>
            <div class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400">
              {{ proposal.verifikasi_aktif?.rekomendasi_anggaran ? formatCurrency(proposal.verifikasi_aktif.rekomendasi_anggaran) : '-' }}
            </div>
            <span v-if="proposal.verifikasi_aktif" class="text-[10px] text-gray-400">
              {{ proposal.verifikasi_aktif.opd?.nama || 'OPD Teknis' }}
            </span>
          </div>

          <div class="p-3 rounded-lg bg-emerald-50/40 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40">
            <span class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">Alokasi Pagu Disetujui TAPD</span>
            <div class="text-base font-bold font-mono text-emerald-700 dark:text-emerald-300">
              {{ proposal.nilai_disetujui_tapd ? formatCurrency(proposal.nilai_disetujui_tapd) : 'Belum Ditetapkan' }}
            </div>
            <span v-if="proposal.tanggal_penetapan_tapd" class="text-[10px] text-emerald-600 dark:text-emerald-400">
              Ditetapkan {{ formatDate(proposal.tanggal_penetapan_tapd) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Tab Navigation Bar (Responsive & Horizontal Scroll) -->
      <div class="relative bg-white dark:bg-gray-900/60 rounded-xl border border-gray-100 dark:border-white/[0.06] p-1.5 shadow-xs">
        <!-- Scroll navigation indicator buttons -->
        <button
          v-if="canScrollLeft"
          type="button"
          aria-label="Gulir tab ke kiri"
          class="absolute left-1 top-1/2 -translate-y-1/2 z-10 w-7 h-7 rounded-lg bg-white/95 dark:bg-gray-800/95 border border-gray-200 dark:border-white/10 shadow-sm flex items-center justify-center text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 focus:outline-none transition-opacity"
          @click="scrollTabList('left')"
        >
          <UIcon name="i-lucide-chevron-left" class="w-4 h-4" />
        </button>

        <button
          v-if="canScrollRight"
          type="button"
          aria-label="Gulir tab ke kanan"
          class="absolute right-1 top-1/2 -translate-y-1/2 z-10 w-7 h-7 rounded-lg bg-white/95 dark:bg-gray-800/95 border border-gray-200 dark:border-white/10 shadow-sm flex items-center justify-center text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 focus:outline-none transition-opacity"
          @click="scrollTabList('right')"
        >
          <UIcon name="i-lucide-chevron-right" class="w-4 h-4" />
        </button>

        <!-- Horizontal Scrollable Tab List -->
        <div
          ref="tabListRef"
          role="tablist"
          aria-label="Menu Tab Detail Usulan BKKD"
          class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth px-1"
          @scroll="checkScroll"
        >
          <button
            v-for="tab in tabsList"
            :id="`tab-${tab.id}`"
            :key="tab.id"
            :ref="(el) => setTabRef(tab.id, el)"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :aria-controls="`tabpanel-${tab.id}`"
            tabindex="0"
            type="button"
            class="group relative inline-flex items-center gap-2 px-4 py-2.5 min-h-[44px] rounded-lg text-xs font-semibold whitespace-nowrap transition-all duration-150 shrink-0 select-none cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
            :class="[
              activeTab === tab.id
                ? 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200/70 dark:border-primary-800/60 shadow-xs'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.04] border border-transparent'
            ]"
            @click="selectTab(tab.id)"
            @keydown="handleTabKeydown($event, tab.id)"
          >
            <UIcon
              :name="tab.icon"
              class="w-4 h-4 shrink-0 transition-transform duration-150 group-hover:scale-110"
              :class="activeTab === tab.id ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-600 dark:group-hover:text-gray-300'"
            />
            <span class="tracking-tight">{{ tab.label }}</span>

            <!-- Badge Counter -->
            <span
              v-if="tab.badge !== undefined && tab.badge !== null"
              class="inline-flex items-center justify-center min-w-[20px] px-1.5 py-0.5 text-[11px] font-mono font-medium rounded-full transition-colors"
              :class="[
                activeTab === tab.id
                  ? 'bg-primary-600 text-white dark:bg-primary-500 dark:text-gray-950'
                  : 'bg-gray-100 text-gray-600 dark:bg-white/[0.08] dark:text-gray-400 group-hover:bg-gray-200 dark:group-hover:bg-white/[0.12]'
              ]"
            >
              {{ tab.badge }}
            </span>
          </button>
        </div>
      </div>

      <!-- Tab Panel 1: Rincian Usulan -->
      <div
        v-if="activeTab === 'rincian'"
        id="tabpanel-rincian"
        role="tabpanel"
        aria-labelledby="tab-rincian"
        class="space-y-6"
      >
        <!-- Sandingan RAPBD vs P-APBD jika terdapat korelasi pergantian usulan -->
        <BkkdSandinganPapbdCard v-if="proposal" :current-proposal="proposal" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <div class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <UIcon name="i-lucide-file-text" class="w-4 h-4 text-primary-500" />
                <span>Uraian & Spesifikasi Teknis Usulan</span>
              </h2>
              <span class="text-xs font-mono text-gray-400">ID: {{ proposal.id.slice(0, 8) }}</span>
            </div>

            <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
              {{ proposal.uraian_kegiatan || 'Tidak ada uraian kegiatan tambahan yang dicantumkan oleh pihak desa.' }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 pt-3 border-t border-gray-100 dark:border-white/[0.04] text-xs">
              <div class="p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
                <span class="text-[11px] text-gray-500 block">Target Volume:</span>
                <p class="font-medium text-gray-900 dark:text-gray-100 mt-0.5 font-mono">
                  <span v-if="(proposal.volume ?? proposal.volume_target) !== null && (proposal.volume ?? proposal.volume_target) !== undefined">
                    {{ formatVolume(proposal.volume ?? proposal.volume_target) }}
                  </span>
                  <span v-else class="text-gray-400 font-sans font-normal">-</span>
                </p>
              </div>

              <div class="p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
                <span class="text-[11px] text-gray-500 block">Satuan Target:</span>
                <p class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                  {{ proposal.satuan || proposal.satuan_target || '-' }}
                </p>
              </div>

              <div class="p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
                <span class="text-[11px] text-gray-500 block">Dimensi Fisik (P×L×T):</span>
                <p class="font-medium text-gray-900 dark:text-gray-100 mt-0.5 font-mono">
                  <span v-if="proposal.panjang || proposal.lebar || proposal.tinggi">
                    {{ proposal.panjang ? `${proposal.panjang}m` : '-' }} × {{ proposal.lebar ? `${proposal.lebar}m` : '-' }} × {{ proposal.tinggi ? `${proposal.tinggi}m` : '-' }}
                  </span>
                  <span v-else class="text-gray-400 font-sans font-normal">-</span>
                </p>
              </div>

              <div class="p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
                <span class="text-[11px] text-gray-500 block">Lokasi Kegiatan:</span>
                <p class="font-medium text-gray-900 dark:text-gray-100 mt-0.5 truncate" :title="proposal.lokasi_kegiatan">
                  {{ proposal.lokasi_kegiatan }}
                </p>
              </div>

              <div class="p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
                <span class="text-[11px] text-gray-500 block">Titik Spasial WGS 84:</span>
                <div v-if="proposal.latitude && proposal.longitude" class="mt-0.5 space-y-1">
                  <p class="font-mono text-[11px] text-gray-800 dark:text-gray-200 truncate">
                    {{ proposal.latitude }}, {{ proposal.longitude }}
                  </p>
                  <NuxtLink
                    :to="`/maps/@${proposal.latitude},${proposal.longitude},15z`"
                    class="inline-flex items-center gap-1 text-[11px] font-medium text-primary-600 dark:text-primary-400 hover:underline"
                  >
                    <UIcon name="i-lucide-map" class="w-3 h-3" />
                    <span>Lihat di Peta WebGIS</span>
                  </NuxtLink>
                </div>
                <p v-else class="text-gray-400 mt-0.5 italic">
                  Belum diplot koordinat
                </p>
              </div>
            </div>
          </div>

          <!-- Informasi Penetapan TAPD jika ada -->
          <div v-if="proposal.status === 'ditetapkan_tapd'" class="p-5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/20 dark:bg-emerald-950/10 space-y-3">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-bold text-emerald-800 dark:text-emerald-400 flex items-center gap-1.5">
                <UIcon name="i-lucide-award" class="w-4 h-4" />
                <span>Rancangan Alokasi Anggaran APBD (Penetapan TAPD)</span>
              </h2>
              <span class="text-xs text-emerald-600 font-mono">{{ formatDate(proposal.tanggal_penetapan_tapd || '') }}</span>
            </div>

            <div class="p-3.5 rounded-lg bg-white dark:bg-gray-800/80 border border-emerald-100 dark:border-emerald-900/40">
              <span class="text-xs text-gray-500">Pagu Disetujui TAPD:</span>
              <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                {{ formatCurrency(proposal.nilai_disetujui_tapd || 0) }}
              </div>
              <p v-if="proposal.catatan_tapd" class="text-xs text-gray-600 dark:text-gray-400 mt-2 italic bg-gray-50/80 dark:bg-gray-900/40 p-2 rounded">
                Catatan TAPD: "{{ proposal.catatan_tapd }}"
              </p>
            </div>
          </div>
        </div>

        <!-- Kolom Samping: Surat Permohonan Desa & Metadata -->
        <div class="space-y-6">
          <div class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-3 text-xs">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <UIcon name="i-lucide-mail" class="w-4 h-4 text-primary-500" />
              <span>Surat Permohonan Desa</span>
            </h2>
            <div class="space-y-2.5 pt-1">
              <div>
                <span class="text-gray-500 block text-[11px]">Nomor Surat:</span>
                <p class="font-mono font-medium text-gray-800 dark:text-gray-200 mt-0.5">
                  {{ proposal.nomor_surat_desa || '-' }}
                </p>
              </div>
              <div>
                <span class="text-gray-500 block text-[11px]">Tanggal Surat:</span>
                <p class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">
                  {{ proposal.tanggal_surat_desa ? formatDate(proposal.tanggal_surat_desa) : '-' }}
                </p>
              </div>
              <div v-if="proposal.tanggal_diterima">
                <span class="text-gray-500 block text-[11px]">Tanggal Diterima Bappeda:</span>
                <p class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">
                  {{ formatDate(proposal.tanggal_diterima) }}
                </p>
              </div>
            </div>
          </div>

          <div class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-3 text-xs text-gray-500">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <UIcon name="i-lucide-info" class="w-4 h-4 text-primary-500" />
              <span>Informasi Sistem</span>
            </h2>
            <div class="space-y-2 pt-1 divide-y divide-gray-100 dark:divide-white/[0.04]">
              <div class="flex justify-between pt-1">
                <span>Waktu Dibuat:</span>
                <span class="font-mono text-gray-700 dark:text-gray-300">{{ formatDate(proposal.created_at) }}</span>
              </div>
              <div class="flex justify-between pt-2">
                <span>Pembaruan Terakhir:</span>
                <span class="font-mono text-gray-700 dark:text-gray-300">{{ formatDate(proposal.updated_at) }}</span>
              </div>
              <div class="flex justify-between pt-2">
                <span>Nomor Versi:</span>
                <span class="font-mono font-semibold text-primary-600 dark:text-primary-400">v{{ proposal.versi }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>

      <!-- Tab Panel 2: Hasil Verifikasi OPD -->
      <div
        v-else-if="activeTab === 'verifikasi'"
        id="tabpanel-verifikasi"
        role="tabpanel"
        aria-labelledby="tab-verifikasi"
        class="space-y-6"
      >
        <!-- Verifikasi Aktif Terakhir -->
        <div v-if="proposal.verifikasi_aktif" class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-white/[0.04]">
            <div>
              <span class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 uppercase tracking-wider block">Hasil Telaah OPD Teknis Aktif</span>
              <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mt-0.5 flex items-center gap-2">
                <UIcon name="i-lucide-building-2" class="w-4 h-4 text-gray-400" />
                <span>{{ proposal.verifikasi_aktif.opd?.nama || proposal.nama_bidang || 'OPD Teknis Terkait' }}</span>
              </h2>
            </div>
            <div class="flex items-center gap-2">
              <UBadge color="neutral" size="xs" variant="outline" class="font-mono">
                versi #{{ proposal.verifikasi_aktif.versi || 1 }}
              </UBadge>
              <BkkdStatusBadge :status="proposal.verifikasi_aktif.hasil" size="sm" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 text-xs">
            <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
              <span class="text-[11px] text-gray-500 block">Nomor Berita Acara:</span>
              <p class="font-mono font-semibold text-gray-900 dark:text-gray-100 mt-1">
                {{ proposal.verifikasi_aktif.nomor_surat_hasil || '-' }}
              </p>
            </div>

            <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
              <span class="text-[11px] text-gray-500 block">Tanggal Verifikasi:</span>
              <p class="font-medium text-gray-800 dark:text-gray-200 mt-1">
                {{ formatDate(proposal.verifikasi_aktif.tanggal_verifikasi || proposal.verifikasi_aktif.created_at) }}
              </p>
            </div>

            <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
              <span class="text-[11px] text-gray-500 block">Rekomendasi Anggaran:</span>
              <p class="font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                {{ proposal.verifikasi_aktif.rekomendasi_anggaran ? formatCurrency(proposal.verifikasi_aktif.rekomendasi_anggaran) : '-' }}
              </p>
              <span v-if="proposal.verifikasi_aktif.rekomendasi_anggaran && proposal.nilai_usulan" class="text-[10px] text-gray-400 mt-0.5 block">
                ({{ Math.round((proposal.verifikasi_aktif.rekomendasi_anggaran / proposal.nilai_usulan) * 100) }}% dari usulan desa)
              </span>
            </div>

            <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
              <span class="text-[11px] text-gray-500 block">Volume Rekomendasi:</span>
              <p class="font-mono font-bold text-gray-800 dark:text-gray-200 mt-1">
                {{ formatVolume(proposal.verifikasi_aktif.volume_rekomendasi, proposal.satuan || proposal.satuan_target) }}
              </p>
            </div>

            <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-white/[0.04]">
              <span class="text-[11px] text-gray-500 block">Dimensi Rekomendasi:</span>
              <p class="font-mono font-bold text-gray-800 dark:text-gray-200 mt-1">
                <span v-if="proposal.verifikasi_aktif.panjang_rekomendasi || proposal.verifikasi_aktif.lebar_rekomendasi || proposal.verifikasi_aktif.tinggi_rekomendasi">
                  {{ proposal.verifikasi_aktif.panjang_rekomendasi ? `${proposal.verifikasi_aktif.panjang_rekomendasi}m` : '-' }} × {{ proposal.verifikasi_aktif.lebar_rekomendasi ? `${proposal.verifikasi_aktif.lebar_rekomendasi}m` : '-' }} × {{ proposal.verifikasi_aktif.tinggi_rekomendasi ? `${proposal.verifikasi_aktif.tinggi_rekomendasi}m` : '-' }}
                </span>
                <span v-else class="text-gray-400 font-sans font-normal">-</span>
              </p>
            </div>
          </div>

          <div class="p-3.5 rounded-lg bg-gray-50/60 dark:bg-gray-800/40 text-xs border border-gray-100 dark:border-white/[0.04] space-y-1">
            <span class="font-semibold text-gray-700 dark:text-gray-300 block">Catatan Teknis & Pertimbangan OPD:</span>
            <p class="text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">
              {{ proposal.verifikasi_aktif.catatan_opd || 'Tidak ada catatan khusus yang dilampirkan oleh verifikator OPD.' }}
            </p>
          </div>

          <div v-if="proposal.verifikasi_aktif.has_dokumen" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
            <span class="text-xs text-gray-500">Dokumen Berita Acara / Telaah Teknis Resmi:</span>
            <a
              :href="api.getVerifikasiDownloadUrl(proposal.verifikasi_aktif.id)"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 min-h-[44px] rounded-lg text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
            >
              <UIcon name="i-lucide-download" class="w-4 h-4" />
              <span>Unduh Berkas Berita Acara Telaah OPD</span>
            </a>
          </div>
        </div>

        <!-- Riwayat Telaah Terdahulu jika ada multiple telaah -->
        <div v-if="proposal.semua_verifikasi && proposal.semua_verifikasi.length > 1" class="space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">
            Riwayat Verifikasi Terdahulu ({{ proposal.semua_verifikasi.length - 1 }} versi sebelumnya)
          </h3>
          <div class="space-y-3">
            <div
              v-for="v in proposal.semua_verifikasi.filter(item => item.id !== proposal?.verifikasi_aktif?.id)"
              :key="v.id"
              class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/40 dark:bg-gray-900/20 text-xs space-y-2"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">Versi #{{ v.versi }}</span>
                  <BkkdStatusBadge :status="v.hasil" size="sm" />
                </div>
                <span class="text-gray-400">{{ formatDate(v.tanggal_verifikasi || v.created_at) }}</span>
              </div>
              <p class="text-gray-600 dark:text-gray-400">{{ v.catatan_opd || 'Tidak ada catatan.' }}</p>
            </div>
          </div>
        </div>

        <!-- Empty State jika belum ada verifikasi -->
        <div v-if="!proposal.verifikasi_aktif" class="p-8 text-center rounded-xl border border-dashed border-gray-200 dark:border-white/[0.08] text-gray-500 text-xs space-y-3">
          <UIcon name="i-lucide-clipboard-x" class="w-8 h-8 mx-auto text-gray-400" />
          <div class="max-w-md mx-auto space-y-1">
            <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Belum Ada Hasil Verifikasi Resmi</p>
            <p class="text-gray-500">
              <span v-if="proposal.status === 'draft'">
                Proposal saat ini masih dalam status draf desa dan belum diajukan ke dinas atau OPD teknis terkait.
              </span>
              <span v-else-if="proposal.status === 'diajukan_opd'">
                Proposal telah diajukan ke OPD teknis dan saat ini sedang dalam antrean telaah lapangan dan teknis.
              </span>
              <span v-else>
                Belum ada berkas atau catatan verifikasi yang dicatat untuk usulan ini.
              </span>
            </p>
          </div>

          <div v-if="canVerifikasi && ['diajukan_opd', 'dikembalikan'].includes(proposal.status)" class="pt-2">
            <UButton
              color="primary"
              size="sm"
              icon="i-lucide-clipboard-check"
              label="Catat Hasil Telaah OPD Sekarang"
              @click="modalVerifOpen = true"
            />
          </div>
        </div>
      </div>

      <!-- Tab Panel 3: Berkas Lampiran -->
      <div
        v-else-if="activeTab === 'berkas'"
        id="tabpanel-berkas"
        role="tabpanel"
        aria-labelledby="tab-berkas"
        class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-4"
      >
        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-white/[0.04]">
          <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <UIcon name="i-lucide-paperclip" class="w-4 h-4 text-primary-500" />
              <span>Berkas & Dokumen Kelengkapan Usulan</span>
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">
              Lampiran administratif mencakup proposal resmi desa, RAB, gambar rencana teknis, dan foto kondisi 0%, baik berupa file unggahan maupun tautan cloud (Google Drive / OneDrive).
            </p>
          </div>
          <UBadge color="neutral" size="xs" variant="subtle">
            {{ proposal.lampiran?.length || 0 }} Dokumen Lampiran
          </UBadge>
        </div>

        <BkkdLampiranList
          :proposal-id="proposal.id"
          :lampiran-list="proposal.lampiran"
          :can-manage="canUpdate"
          @refresh="loadProposal"
        />
      </div>

      <!-- Tab Panel 4: Linimasa Perjalanan Usulan -->
      <div
        v-else-if="activeTab === 'linimasa'"
        id="tabpanel-linimasa"
        role="tabpanel"
        aria-labelledby="tab-linimasa"
        class="space-y-6"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-gray-100 dark:border-white/[0.04]">
          <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <UIcon name="i-lucide-route" class="w-4 h-4 text-primary-500" />
              <span>Linimasa Perjalanan Usulan</span>
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">
              Pantau perkembangan dan tahapan usulan BKKD secara lengkap, transparan, dan mudah dipahami.
            </p>
          </div>

          <UButton
            color="neutral"
            variant="outline"
            size="xs"
            icon="i-lucide-refresh-cw"
            label="Segarkan Linimasa"
            :loading="loadingRiwayat"
            @click="loadRiwayat"
          />
        </div>

        <!-- Sandingan RAPBD vs P-APBD jika terdapat korelasi pergantian usulan -->
        <BkkdSandinganPapbdCard v-if="proposal" :current-proposal="proposal" />

        <BkkdRiwayatTimeline
          :riwayat-list="riwayatList"
          :loading="loadingRiwayat"
          :proposal="proposal"
        />
      </div>
    </div>

    <!-- State: Proposal Tidak Ditemukan -->
    <div
      v-else
      class="p-12 text-center text-sm text-gray-500 dark:text-gray-400 space-y-3 bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] rounded-xl shadow-xs"
    >
      <div class="size-12 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center mx-auto">
        <UIcon name="i-lucide-file-x" class="size-6" />
      </div>
      <p class="font-semibold text-gray-800 dark:text-gray-200 text-base">
        Proposal Usulan Tidak Ditemukan
      </p>
      <p class="text-xs text-gray-400 max-w-md mx-auto">
        Data proposal usulan dengan ID tersebut tidak dapat dimuat atau telah dihapus.
      </p>
      <div class="pt-2">
        <UButton
          label="Kembali ke Daftar Proposal"
          icon="i-lucide-arrow-left"
          color="neutral"
          variant="outline"
          size="sm"
          to="/admin/bkkd/proposal"
        />
      </div>
    </div>

    <!-- Modals -->
    <BkkdProposalFormModal
      v-model:open="modalEditOpen"
      :is-editing="true"
      :item="proposal"
      @success="loadProposal(); loadRiwayat()"
    />

    <BkkdVerifikasiFormModal
      v-model:open="modalVerifOpen"
      :proposal="proposal"
      @success="loadProposal(); loadRiwayat()"
    />

    <BkkdPenetapanTapdModal
      v-model:open="modalTapdOpen"
      :proposal="proposal"
      @success="loadProposal(); loadRiwayat()"
    />

    <BkkdAjukanOpdModal
      v-model:open="modalAjukanOpen"
      :proposal="proposal"
      @success="loadProposal(); loadRiwayat()"
    />

    <BkkdBatalkanModal
      v-model:open="modalBatalkanOpen"
      :proposal="proposal"
      @success="loadProposal(); loadRiwayat()"
    />

    <BkkdHapusProposalModal
      v-model:open="modalHapusOpen"
      :proposal="proposal"
      @success="handleDeletedSuccess"
    />

    <BkkdGantiPapbdModal
      v-model:open="modalGantiPapbdOpen"
      :proposal="proposal"
      @success="handleGantiSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import {
  KONDISI_OPTIONS,
  PERKERASAN_OPTIONS,
  STATUS_ASET_OPTIONS,
  STATUS_JALAN_OPTIONS,
  SUMBER_DANA_OPTIONS,
  withCurrentOption,
  type InfrastrukturSegmen,
  type InfrastrukturTipe,
  type JalanPorosRef,
  type PlottingAnggaran,
} from '~/types/infrastruktur';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { formatWilayah } from '~/utils/helpers';
import NumericTextInput from './NumericTextInput.vue';

interface Props {
  collapsed?: boolean;
  width?: number;
  selectedSegmen?: InfrastrukturSegmen | null;
  clickedCoordinate?: [number, number] | null;
  loading?: boolean;
  saving?: boolean;
  isMobileDrawer?: boolean;
  collapsible?: boolean;
  tipeList?: InfrastrukturTipe[];
  plottingList?: PlottingAnggaran[];
  canEdit?: boolean;
  canDelete?: boolean;
  canSubmit?: boolean;
  canSplit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  width: 300,
  selectedSegmen: null,
  clickedCoordinate: null,
  loading: false,
  saving: false,
  isMobileDrawer: false,
  collapsible: true,
  tipeList: () => [],
  plottingList: () => [],
  canEdit: true,
  canDelete: true,
  canSubmit: true,
  canSplit: undefined,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'close'): void;
  (e: 'zoomToFeature', segmen: InfrastrukturSegmen): void;
  (e: 'editSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'deleteSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'submitVerifikasi', segmen: InfrastrukturSegmen): void;
  (e: 'viewInTable', segmen: InfrastrukturSegmen): void;
  (e: 'saveSegmen', payload: Partial<InfrastrukturSegmen>): void;
  (e: 'editGeometry', segmen: InfrastrukturSegmen): void;
  (e: 'splitSegmen', segmen: InfrastrukturSegmen): void;
}>();

const activeTab = ref<'inspector' | 'edit'>('inspector');
const showUnsavedGuard = ref(false);
const editBaselineJson = ref('');
const maxBuildYear = new Date().getFullYear() + 5;

const infrastrukturApi = useInfrastrukturApi();
const jalanPorosOptions = ref<JalanPorosRef[]>([]);
const isLoadingJalanPoros = ref(false);
let lastJalanPorosRegionKey = '';

async function loadEditJalanPorosOptions(seg?: InfrastrukturSegmen | null) {
  if (!seg) return;
  const regionKey = `${seg.id_kecamatan || 0}:${seg.id_desa || 0}`;
  if (regionKey === lastJalanPorosRegionKey && jalanPorosOptions.value.length > 0) {
    return;
  }
  isLoadingJalanPoros.value = true;
  try {
    const res = await infrastrukturApi.fetchJalanPorosLookup({
      id_kecamatan: seg.id_kecamatan || null,
      id_desa: seg.id_desa || null,
      limit: 500,
    });
    if (res?.ok && Array.isArray(res.data)) {
      jalanPorosOptions.value = res.data;
      lastJalanPorosRegionKey = regionKey;
    }
  } catch {
    jalanPorosOptions.value = [];
  } finally {
    isLoadingJalanPoros.value = false;
  }
}

const editPanjangRef = ref<InstanceType<typeof NumericTextInput> | null>(null);
const editLebarRef = ref<InstanceType<typeof NumericTextInput> | null>(null);
const editTahunRef = ref<InstanceType<typeof NumericTextInput> | null>(null);

const editNumericErrors = reactive({
  panjang: null as string | null,
  lebar: null as string | null,
  tahun_pembangunan: null as string | null,
});

function resetEditNumericErrors() {
  editNumericErrors.panjang = null;
  editNumericErrors.lebar = null;
  editNumericErrors.tahun_pembangunan = null;
  editPanjangRef.value?.clearValidation();
  editLebarRef.value?.clearValidation();
  editTahunRef.value?.clearValidation();
}

function validateEditNumericFields(): boolean {
  const v1 = editPanjangRef.value?.validate() ?? true;
  const v2 = editLebarRef.value?.validate() ?? true;
  const v3 = editTahunRef.value?.validate() ?? true;
  return v1 && v2 && v3;
}

const effectiveCanSplit = computed(() => props.canSplit ?? props.canEdit);

// Local edit form state synced with selectedSegmen
const editForm = reactive<Partial<InfrastrukturSegmen>>({
  namobj: '',
  tipe_kode: '',
  panjang: null,
  lebar: null,
  jenis_perkerasan: '',
  status_jalan: '',
  kondisi: 'Baik',
  status_kondisi: 'Eksisting',
  tahun_pembangunan: new Date().getFullYear(),
  sumber_dana: '',
  status_aset: '',
  sumber_data: '',
  keterangan: '',
  plotting_id: null,
  parent_id: null,
});

function syncEditFormFromSegmen(seg?: InfrastrukturSegmen | null) {
  if (!seg) return;
  editForm.namobj = seg.namobj || '';
  editForm.tipe_kode = seg.tipe_kode || '';
  editForm.panjang = seg.panjang ?? null;
  editForm.lebar = seg.lebar ?? null;
  editForm.jenis_perkerasan = seg.jenis_perkerasan || '';
  editForm.status_jalan = seg.status_jalan || '';
  editForm.kondisi = seg.kondisi || 'Baik';
  editForm.status_kondisi = seg.status_kondisi || 'Eksisting';
  editForm.tahun_pembangunan = seg.tahun_pembangunan || new Date().getFullYear();
  editForm.sumber_dana = seg.sumber_dana || '';
  editForm.status_aset = seg.status_aset || '';
  editForm.sumber_data = seg.sumber_data || '';
  editForm.keterangan = seg.keterangan || '';
  editForm.plotting_id = seg.plotting_id || null;
  editForm.parent_id = seg.parent_id || null;
  editBaselineJson.value = serializeEditForm();
  showUnsavedGuard.value = false;
  resetEditNumericErrors();
}

function serializeEditForm() {
  return JSON.stringify({
    namobj: editForm.namobj || '',
    tipe_kode: editForm.tipe_kode || '',
    panjang: editForm.panjang ?? null,
    lebar: editForm.lebar ?? null,
    jenis_perkerasan: editForm.jenis_perkerasan || '',
    status_jalan: editForm.status_jalan || '',
    kondisi: editForm.kondisi || 'Baik',
    status_kondisi: editForm.status_kondisi || 'Eksisting',
    tahun_pembangunan: editForm.tahun_pembangunan || '',
    sumber_dana: editForm.sumber_dana || '',
    status_aset: editForm.status_aset || '',
    sumber_data: editForm.sumber_data || '',
    keterangan: editForm.keterangan || '',
    plotting_id: editForm.plotting_id ?? null,
    parent_id: editForm.parent_id ?? null,
  });
}

const isEditDirty = computed(() => {
  if (activeTab.value !== 'edit' || !props.selectedSegmen) return false;
  return serializeEditForm() !== editBaselineJson.value;
});

function setActiveTab(tab: 'inspector' | 'edit') {
  if (tab === 'edit' && !props.canEdit) {
    activeTab.value = 'inspector';
    return;
  }
  if (tab === 'inspector' && activeTab.value === 'edit' && isEditDirty.value) {
    showUnsavedGuard.value = true;
    return;
  }
  showUnsavedGuard.value = false;
  if (tab === 'edit') {
    syncEditFormFromSegmen(props.selectedSegmen);
    loadEditJalanPorosOptions(props.selectedSegmen);
  }
  activeTab.value = tab;
}

function confirmDiscardToInspector() {
  syncEditFormFromSegmen(props.selectedSegmen);
  showUnsavedGuard.value = false;
  activeTab.value = 'inspector';
}

defineExpose({
  setActiveTab,
});

const copied = ref(false);
const copiedId = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;
let copyIdTimer: ReturnType<typeof setTimeout> | null = null;

function fallbackCopy(text: string) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.opacity = '0';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
  } catch (err) {
    console.error('Gagal menyalin:', err);
  }
  document.body.removeChild(textArea);
}

function writeClipboard(text: string, onSuccess: () => void) {
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard
      .writeText(text)
      .then(onSuccess)
      .catch(() => {
        fallbackCopy(text);
        onSuccess();
      });
  } else {
    fallbackCopy(text);
    onSuccess();
  }
}

function copyCoords() {
  if (!props.clickedCoordinate) return;
  const [lng, lat] = props.clickedCoordinate;
  const text = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  writeClipboard(text, () => {
    copied.value = true;
    if (copyTimer) clearTimeout(copyTimer);
    copyTimer = setTimeout(() => {
      copied.value = false;
    }, 1800);
  });
}

function copySegmenId() {
  if (!props.selectedSegmen?.id) return;
  writeClipboard(String(props.selectedSegmen.id), () => {
    copiedId.value = true;
    if (copyIdTimer) clearTimeout(copyIdTimer);
    copyIdTimer = setTimeout(() => {
      copiedId.value = false;
    }, 1800);
  });
}

watch(
  () => props.selectedSegmen,
  (seg, prevSeg) => {
    const newId = seg?.id ?? null;
    const oldId = prevSeg?.id ?? null;
    if (newId !== oldId) {
      activeTab.value = 'inspector';
      syncEditFormFromSegmen(seg);
    } else if (activeTab.value === 'inspector') {
      syncEditFormFromSegmen(seg);
    }
  },
  { immediate: true },
);

const panjangGisDisplay = computed(() => {
  const raw = props.selectedSegmen?.panjang_meter_gis;
  if (raw === null || raw === undefined || String(raw).trim() === '') return '-';
  const num = Number(raw);
  if (!Number.isFinite(num)) return '-';
  return String(Number(num.toFixed(2)));
});

const tipeItems = computed(() =>
  props.tipeList.map((t) => ({
    label: t.nama,
    value: t.kode,
  })),
);

const plottingItems = computed(() => [
  { label: 'Tanpa Kaitan Plotting (Mandiri)', value: null },
  ...props.plottingList.map((p) => ({
    label: `${p.nama_kegiatan} (${formatRupiah(p.target_pagu_anggaran)})`,
    value: p.id,
  })),
]);

function formatJalanPorosLabel(refRoad?: JalanPorosRef | null): string {
  if (!refRoad) return 'Tanpa Referensi (Mandiri)';
  const kode = refRoad.kode_ruas != null && refRoad.kode_ruas !== '' ? `[#${refRoad.kode_ruas}] ` : '';
  return `${kode}${refRoad.nama_ruas || 'Ruas Jalan Poros'}`;
}

const jalanPorosItems = computed(() => {
  const list = [...jalanPorosOptions.value];
  const currentParent = props.selectedSegmen?.parent;
  if (currentParent && !list.some((item) => item.id === currentParent.id)) {
    list.unshift(currentParent);
  }
  return [
    { label: 'Tanpa Referensi (Segmen Mandiri)', value: null },
    ...list.map((r) => ({
      label: `${r.kode_ruas != null ? `[#${r.kode_ruas}] ` : ''}${r.nama_ruas || 'Ruas Tanpa Nama'}${r.desa ? ` · ${r.desa}` : ''}`,
      value: r.id,
    })),
  ];
});

const kondisiItems = KONDISI_OPTIONS;
const perkerasanItems = computed(() =>
  withCurrentOption(PERKERASAN_OPTIONS, editForm.jenis_perkerasan),
);
const statusJalanItems = computed(() =>
  withCurrentOption(STATUS_JALAN_OPTIONS, editForm.status_jalan),
);
const sumberDanaItems = computed(() =>
  withCurrentOption(SUMBER_DANA_OPTIONS, editForm.sumber_dana),
);
const statusAsetItems = computed(() =>
  withCurrentOption(STATUS_ASET_OPTIONS, editForm.status_aset),
);

function formatRupiah(val?: number | null) {
  if (val === null || val === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

const tipeLabel = computed(() => {
  if (!props.selectedSegmen) return '';
  if (props.selectedSegmen.tipe?.nama) return props.selectedSegmen.tipe.nama;
  const match = props.tipeList.find((t) => t.kode === props.selectedSegmen?.tipe_kode);
  return match?.nama || props.selectedSegmen.tipe_kode || 'Segmen';
});

function getStatusVerifikasiBadge(status?: string | null) {
  const s = String(status || '').toLowerCase();
  switch (s) {
    case 'verified_bappeda':
      return { label: 'Disahkan Bappeda', color: 'success' as const };
    case 'verified_kecamatan':
      return { label: 'Disetujui Kecamatan', color: 'info' as const };
    case 'submitted_desa':
      return { label: 'Diajukan ke Kecamatan', color: 'warning' as const };
    case 'rejected_kecamatan':
    case 'rejected_bappeda':
      return { label: 'Perlu Revisi', color: 'error' as const };
    default:
      return { label: 'Draft', color: 'neutral' as const };
  }
}

function getKondisiBadge(kondisi?: string | null) {
  switch (kondisi) {
    case 'Baik':
      return { label: 'Baik', color: 'success' as const };
    case 'Sedang':
      return { label: 'Sedang', color: 'warning' as const };
    case 'Rusak Ringan':
      return { label: 'Rusak Ringan', color: 'warning' as const };
    case 'Rusak Berat':
      return { label: 'Rusak Berat', color: 'error' as const };
    default:
      return { label: kondisi || 'Belum Diisi', color: 'neutral' as const };
  }
}

function handleSaveEdit() {
  if (!validateEditNumericFields()) {
    return;
  }
  showUnsavedGuard.value = false;
  editBaselineJson.value = serializeEditForm();
  emit('saveSegmen', { ...editForm });
}

function handleCancelEdit() {
  syncEditFormFromSegmen(props.selectedSegmen);
  showUnsavedGuard.value = false;
  activeTab.value = 'inspector';
}

const canSubmitCurrentSegmen = computed(() => {
  if (!props.canSubmit || !props.selectedSegmen) return false;
  const status = String(props.selectedSegmen.status_verifikasi || '').toLowerCase();
  return status === 'draft' || status === 'rejected_kecamatan';
});

const secondaryActionItems = computed(() => {
  if (!props.selectedSegmen) return [];
  const seg = props.selectedSegmen;
  const primaryGroup: any[] = [
    {
      label: 'Lihat di Tabel',
      icon: 'i-lucide-table',
      onSelect: () => emit('viewInTable', seg),
    },
  ];
  if (canSubmitCurrentSegmen.value) {
    primaryGroup.unshift({
      label: 'Ajukan Verifikasi',
      icon: 'i-lucide-send',
      onSelect: () => emit('submitVerifikasi', seg),
    });
  }
  const groups: any[][] = [primaryGroup];
  if (props.canDelete) {
    groups.push([
      {
        label: 'Hapus Segmen',
        icon: 'i-lucide-trash-2',
        color: 'error' as const,
        onSelect: () => emit('deleteSegmen', seg),
      },
    ]);
  }
  return groups;
});
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]',
      isMobileDrawer ? '' : 'select-none'
    ]"
  >
    <!-- Header: Title & Collapse Button -->
    <div class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 gap-2 justify-between select-none">
      <Transition name="panel-subtle-fade">
        <div v-if="isMobileDrawer || !collapsed" class="flex items-center gap-2 overflow-hidden">
          <UIcon name="i-lucide-info" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            {{ activeTab === 'inspector' ? 'Detail Data' : 'Edit Formulir' }}
          </span>
          <UIcon
            v-if="loading"
            name="i-lucide-loader-2"
            class="size-3 text-blue-500 animate-spin shrink-0"
            title="Memperbarui data detail..."
          />
          <UBadge
            v-if="selectedSegmen"
            :label="tipeLabel"
            size="xs"
            color="primary"
            variant="subtle"
            class="text-[9px] px-1 py-0 h-4 leading-none shrink-0"
          />
        </div>
      </Transition>

      <!-- Desktop Action Controls: Dismiss Selection + Collapse Toggle -->
      <div v-if="!isMobileDrawer" class="flex items-center gap-0.5 shrink-0">
        <UTooltip v-if="selectedSegmen && !collapsed" text="Tutup detail & batal pilih segmen">
          <UButton
            icon="i-lucide-x"
            size="xs"
            color="neutral"
            variant="ghost"
            aria-label="Tutup panel detail dan batalkan pilihan segmen"
            class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer"
            @click="emit('close')"
          />
        </UTooltip>

        <UTooltip v-if="collapsible" :text="collapsed ? 'Buka panel detail' : 'Tutup panel detail'">
          <UButton
            icon="i-lucide-chevron-right"
            size="xs"
            color="neutral"
            variant="ghost"
            :aria-label="collapsed ? 'Buka panel detail' : 'Tutup panel detail'"
            :class="[
              'shrink-0 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
              collapsed ? 'rotate-180' : 'rotate-0'
            ]"
            @click="emit('update:collapsed', !collapsed)"
          />
        </UTooltip>
      </div>
    </div>

    <Transition name="panel-fade" mode="out-in">
      <!-- Desktop Collapsed Rail -->
      <button
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
        type="button"
        :aria-expanded="false"
        aria-label="Buka panel detail segmen"
        class="flex flex-col items-center pt-3 gap-3 flex-1 w-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
        title="Klik untuk membuka detail segmen"
        @click="emit('update:collapsed', false)"
      >
        <UTooltip text="Detail Segmen" :content="{ side: 'left' }">
          <div class="relative p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon
              name="i-lucide-info"
              :class="[
                'size-4 transition-colors',
                selectedSegmen ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'
              ]"
            />
            <span
              v-if="selectedSegmen"
              class="absolute top-1 right-1 size-1.5 rounded-full bg-blue-500 ring-1 ring-white dark:ring-[#0b0f19]"
            />
          </div>
        </UTooltip>
      </button>

      <!-- Content: Body Area -->
      <div
        v-else
        key="body"
        class="flex-1 flex flex-col min-h-0 overflow-hidden"
      >
        <!-- Empty State: Belum ada segmen terpilih -->
        <div
          v-if="!selectedSegmen"
          class="flex flex-col items-center justify-center h-full gap-2.5 px-4 py-8 text-center text-gray-500 dark:text-gray-400"
        >
          <div class="size-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 dark:text-gray-400">
            <UIcon name="i-lucide-mouse-pointer-click" class="size-5" />
          </div>
          <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Belum Ada Segmen Terpilih</p>
          <p class="text-[11px] text-gray-500 dark:text-gray-400 max-w-[220px] leading-relaxed">
            Pilih garis segmen fisik pada peta canvas atau baris di tabel atribut untuk menginspeksi detail properties.
          </p>
        </div>

        <!-- Selected Feature Detail Properties -->
        <div v-else class="flex-1 flex flex-col min-h-0">
          <!-- Top Action Toolbar (Single Consolidated Row + Overflow Menu) -->
          <div class="p-3 pb-2 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30 shrink-0">
            <div class="flex items-center gap-1.5">
              <UButton
                icon="i-lucide-map-pin"
                label="Zoom"
                size="xs"
                color="primary"
                variant="subtle"
                :class="['justify-center cursor-pointer', isMobileDrawer ? 'min-h-[44px] flex-1' : 'flex-1']"
                @click="emit('zoomToFeature', selectedSegmen)"
              />
              <UButton
                v-if="canEdit"
                icon="i-lucide-pen-tool"
                label="Geometri"
                size="xs"
                color="neutral"
                variant="subtle"
                title="Ubah titik koordinat garis di peta"
                :class="['justify-center cursor-pointer', isMobileDrawer ? 'min-h-[44px]' : '']"
                @click="emit('editGeometry', selectedSegmen)"
              />
              <UButton
                v-if="effectiveCanSplit"
                icon="i-lucide-scissors"
                label="Split"
                size="xs"
                color="neutral"
                variant="subtle"
                title="Potong segmen menjadi 2 bagian"
                :class="['justify-center cursor-pointer', isMobileDrawer ? 'min-h-[44px]' : '']"
                @click="emit('splitSegmen', selectedSegmen)"
              />
              <UDropdownMenu
                :items="secondaryActionItems"
                :content="{ side: 'bottom', align: 'end' }"
              >
                <UButton
                  icon="i-lucide-ellipsis"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  aria-label="Aksi lainnya untuk segmen"
                  :class="['shrink-0 cursor-pointer justify-center', isMobileDrawer ? 'min-h-[44px] min-w-[44px]' : '']"
                />
              </UDropdownMenu>
            </div>

            <!-- Segmented Subtabs -->
            <div class="grid gap-1 p-0.5 bg-gray-200/60 dark:bg-gray-800 rounded-lg mt-2" :class="canEdit ? 'grid-cols-2' : 'grid-cols-1'">
              <button
                type="button"
                class="px-2 rounded-md text-xs font-medium transition-all text-center cursor-pointer"
                :class="[
                  isMobileDrawer ? 'min-h-[44px] py-2' : 'py-1',
                  activeTab === 'inspector'
                    ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                ]"
                @click="setActiveTab('inspector')"
              >
                Informasi Teknis
              </button>
              <button
                v-if="canEdit"
                type="button"
                class="px-2 rounded-md text-xs font-medium transition-all text-center cursor-pointer inline-flex items-center justify-center gap-1.5"
                :class="[
                  isMobileDrawer ? 'min-h-[44px] py-2' : 'py-1',
                  activeTab === 'edit'
                    ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                ]"
                @click="setActiveTab('edit')"
              >
                <span>Edit Formulir</span>
                <span
                  v-if="isEditDirty"
                  class="size-1.5 rounded-full bg-amber-500 shrink-0"
                  title="Ada perubahan belum disimpan"
                />
              </button>
            </div>
          </div>

          <!-- Scrollable Body -->
          <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
            <!-- TAB 1: INFORMASI TEKNIS -->
            <template v-if="activeTab === 'inspector'">
              <!-- Info banner if segmen cannot be edited by user (mode baca) -->
              <div
                v-if="!canEdit && !canDelete && !canSubmit"
                class="text-[11px] text-gray-500 dark:text-gray-400 italic p-2 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-800/80 flex items-center gap-1.5"
              >
                <UIcon name="i-lucide-info" class="size-3.5 text-amber-500 shrink-0" />
                <span>Mode baca: Segmen fisik ini di luar wewenang wilayah Anda.</span>
              </div>

              <!-- Status & Title Card -->
              <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white leading-tight">
                      {{ selectedSegmen.namobj || 'Segmen Tanpa Nama' }}
                    </h3>
                    <div class="flex items-center gap-1 mt-0.5">
                      <p class="text-[11px] text-gray-600 dark:text-gray-400 tabular-nums">
                        ID: {{ String(selectedSegmen.id || '').slice(0, 8) }}...
                      </p>
                      <UTooltip :text="copiedId ? 'ID tersalin' : 'Salin ID lengkap segmen'">
                        <button
                          type="button"
                          :aria-label="copiedId ? 'ID segmen tersalin' : 'Salin ID segmen'"
                          class="p-0.5 rounded text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors"
                          @click="copySegmenId"
                        >
                          <UIcon :name="copiedId ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3" />
                        </button>
                      </UTooltip>
                    </div>
                  </div>
                  <UBadge
                    :label="getStatusVerifikasiBadge(selectedSegmen.status_verifikasi).label"
                    :color="getStatusVerifikasiBadge(selectedSegmen.status_verifikasi).color"
                    variant="subtle"
                    size="xs"
                  />
                </div>

                <!-- Badges -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                  <UBadge
                    :label="getKondisiBadge(selectedSegmen.kondisi).label"
                    :color="getKondisiBadge(selectedSegmen.kondisi).color"
                    variant="subtle"
                    size="xs"
                  />
                  <UBadge
                    v-if="selectedSegmen.status_aset"
                    :label="selectedSegmen.status_aset"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                  />
                  <UBadge
                    v-if="selectedSegmen.tahun_pembangunan"
                    :label="`Tahun ${selectedSegmen.tahun_pembangunan}`"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    class="tabular-nums"
                  />
                </div>
              </div>

              <!-- Properties Key-Value Table -->
              <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 overflow-hidden divide-y divide-gray-100 dark:divide-gray-800/60 border border-gray-100 dark:border-gray-800/80">
                <div
                  v-for="[key, val] in [
                    ['Nama Objek', selectedSegmen.namobj],
                    ['Tipe', tipeLabel],
                    ['Referensi Poros', formatJalanPorosLabel(selectedSegmen.parent)],
                    ['Desa', formatWilayah(selectedSegmen.desa)],
                    ['Kecamatan', formatWilayah(selectedSegmen.kecamatan)],
                    ['Panjang Isian', selectedSegmen.panjang !== null && selectedSegmen.panjang !== undefined ? `${Number(selectedSegmen.panjang).toLocaleString('id-ID', { maximumFractionDigits: 4 })} m` : '-'],
                    ['Panjang (GIS)', selectedSegmen.panjang_meter_gis !== null && selectedSegmen.panjang_meter_gis !== undefined ? `${Number(selectedSegmen.panjang_meter_gis).toLocaleString('id-ID', { maximumFractionDigits: 4 })} m` : '-'],
                    ['Lebar Fisik', selectedSegmen.lebar !== null && selectedSegmen.lebar !== undefined ? `${Number(selectedSegmen.lebar).toLocaleString('id-ID', { maximumFractionDigits: 4 })} m` : '-'],
                    ['Jenis Perkerasan', selectedSegmen.jenis_perkerasan],
                    ['Status Jalan', selectedSegmen.status_jalan],
                    ['Kondisi', selectedSegmen.kondisi],
                    ['Status Kondisi', selectedSegmen.status_kondisi],
                    ['Sumber Dana', selectedSegmen.sumber_dana],
                    ['Status Aset', selectedSegmen.status_aset],
                    ['Tahun Bangun', selectedSegmen.tahun_pembangunan],
                    ['Sumber Data', selectedSegmen.sumber_data],
                  ]"
                  :key="key"
                  class="flex items-start justify-between gap-3 px-3 py-1.5 text-xs"
                >
                  <span class="text-gray-500 dark:text-gray-400 w-[40%] shrink-0">{{ key }}</span>
                  <span class="text-gray-800 dark:text-gray-200 font-medium text-right break-words tabular-nums min-w-0 flex-1">
                    {{ typeof val === 'object' && val !== null ? formatWilayah(val) : (val !== null && val !== undefined && val !== '' ? val : '-') }}
                  </span>
                </div>
              </div>

              <!-- Plotting Anggaran Card -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Plotting Anggaran Terkait
                </span>
                <div v-if="selectedSegmen.plotting" class="p-2.5 rounded-lg bg-blue-50/30 dark:bg-blue-950/20 border border-blue-500/20 space-y-1">
                  <p class="font-semibold text-blue-700 dark:text-blue-300">
                    {{ selectedSegmen.plotting.nama_kegiatan }}
                  </p>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-600 dark:text-gray-400">Target Pagu:</span>
                    <span class="tabular-nums font-medium text-gray-900 dark:text-white">
                      {{ formatRupiah(selectedSegmen.plotting.target_pagu_anggaran) }}
                    </span>
                  </div>
                </div>
                <div v-else class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-center">
                  Belum terikat ke alokasi plotting anggaran.
                </div>
              </div>

              <!-- Foto Dokumentasi Lapangan -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Foto Dokumentasi Lapangan
                </span>
                <div v-if="selectedSegmen.foto_url" class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800">
                  <img
                    :src="selectedSegmen.foto_url"
                    alt="Foto Survei Segmen"
                    class="w-full h-36 object-cover bg-gray-100"
                  />
                </div>
                <div v-else class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-200 dark:border-gray-800 text-center text-gray-500 dark:text-gray-400">
                  <UIcon name="i-lucide-camera" class="size-5 mx-auto mb-1 text-gray-500 dark:text-gray-400" />
                  <p class="text-[11px]">Belum ada foto dokumentasi terlampir.</p>
                </div>
              </div>

              <!-- Catatan Verifikasi -->
              <div
                v-if="selectedSegmen.catatan_kecamatan || selectedSegmen.catatan_bappeda"
                class="p-2.5 rounded-lg bg-amber-50/40 dark:bg-amber-950/20 border border-amber-500/30 space-y-1"
              >
                <div class="flex items-center gap-1 font-semibold text-amber-700 dark:text-amber-400 text-[11px]">
                  <UIcon name="i-lucide-alert-circle" class="size-3.5" />
                  <span>Catatan Verifikator</span>
                </div>
                <p v-if="selectedSegmen.catatan_kecamatan" class="text-[11px] text-gray-700 dark:text-gray-300">
                  <span class="font-medium">Kecamatan:</span> {{ selectedSegmen.catatan_kecamatan }}
                </p>
                <p v-if="selectedSegmen.catatan_bappeda" class="text-[11px] text-gray-700 dark:text-gray-300">
                  <span class="font-medium">Bappeda:</span> {{ selectedSegmen.catatan_bappeda }}
                </p>
              </div>
            </template>

            <!-- TAB 2: EDIT FORMULIR -->
            <template v-else-if="activeTab === 'edit'">
              <div class="space-y-3">
                <UFormField label="Nama Objek / Ruas" required size="sm">
                  <UInput
                    v-model="editForm.namobj"
                    placeholder="Contoh: Jl. Poros Desa Mulyoagung"
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <UFormField label="Tipe Infrastruktur" required size="sm">
                  <USelectMenu
                    v-model="editForm.tipe_kode"
                    :items="tipeItems"
                    value-key="value"
                    label-key="label"
                    placeholder="Pilih tipe..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <UFormField
                  label="Referensi Jalan Poros Desa"
                  hint="Opsional. Mengikat segmen pada koridor ruas jalan poros desa."
                  size="sm"
                >
                  <USelectMenu
                    v-model="editForm.parent_id"
                    :items="jalanPorosItems"
                    :loading="isLoadingJalanPoros"
                    value-key="value"
                    label-key="label"
                    placeholder="Cari atau pilih ruas jalan poros..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField
                    label="Panjang Isian (m)"
                    :error="editNumericErrors.panjang || undefined"
                    size="sm"
                  >
                    <NumericTextInput
                      ref="editPanjangRef"
                      v-model="editForm.panjang"
                      field-label="Panjang isian"
                      :allow-decimal="true"
                      :min="0"
                      placeholder="Contoh: 120.5"
                      size="sm"
                      @update:error="editNumericErrors.panjang = $event"
                    />
                  </UFormField>

                  <UFormField
                    label="Panjang GIS (m)"
                    size="sm"
                  >
                    <UInput
                      :model-value="panjangGisDisplay"
                      disabled
                      readonly
                      size="sm"
                      aria-label="Panjang GIS dalam meter (otomatis dari geometri, nonaktif)"
                      class="w-full"
                      :ui="{ base: 'tabular-nums bg-gray-100/80 dark:bg-gray-900/60 text-gray-600 dark:text-gray-400' }"
                    />
                  </UFormField>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField
                    label="Lebar (m)"
                    :error="editNumericErrors.lebar || undefined"
                    size="sm"
                  >
                    <NumericTextInput
                      ref="editLebarRef"
                      v-model="editForm.lebar"
                      field-label="Lebar"
                      :allow-decimal="true"
                      :min="0"
                      placeholder="Contoh: 3.5"
                      size="sm"
                      @update:error="editNumericErrors.lebar = $event"
                    />
                  </UFormField>

                  <UFormField
                    label="Tahun Bangun"
                    :error="editNumericErrors.tahun_pembangunan || undefined"
                    size="sm"
                  >
                    <NumericTextInput
                      ref="editTahunRef"
                      v-model="editForm.tahun_pembangunan"
                      field-label="Tahun bangun"
                      :allow-decimal="false"
                      :min="1900"
                      :max="maxBuildYear"
                      :max-digits="4"
                      placeholder="Contoh: 2024"
                      size="sm"
                      @update:error="editNumericErrors.tahun_pembangunan = $event"
                    />
                  </UFormField>
                </div>

                <UFormField label="Kondisi Fisik" size="sm">
                  <USelectMenu
                    v-model="editForm.kondisi"
                    :items="kondisiItems"
                    value-key="value"
                    label-key="label"
                    :search-input="false"
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField label="Jenis Perkerasan" size="sm">
                    <USelectMenu
                      v-model="editForm.jenis_perkerasan"
                      :items="perkerasanItems"
                      value-key="value"
                      label-key="label"
                      :search-input="false"
                      placeholder="Pilih perkerasan..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>

                  <UFormField label="Status Jalan" size="sm">
                    <USelectMenu
                      v-model="editForm.status_jalan"
                      :items="statusJalanItems"
                      value-key="value"
                      label-key="label"
                      :search-input="false"
                      placeholder="Pilih status..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>

                <UFormField label="Plotting Anggaran Terkait" size="sm">
                  <USelectMenu
                    v-model="editForm.plotting_id"
                    :items="plottingItems"
                    value-key="value"
                    label-key="label"
                    placeholder="Pilih plotting..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField label="Sumber Dana" size="sm">
                    <USelectMenu
                      v-model="editForm.sumber_dana"
                      :items="sumberDanaItems"
                      value-key="value"
                      label-key="label"
                      placeholder="Pilih sumber..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>

                  <UFormField label="Status Aset" size="sm">
                    <USelectMenu
                      v-model="editForm.status_aset"
                      :items="statusAsetItems"
                      value-key="value"
                      label-key="label"
                      :search-input="false"
                      placeholder="Pilih aset..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>

                <UFormField label="Keterangan Tambahan" size="sm">
                  <UTextarea
                    v-model="editForm.keterangan"
                    :rows="2"
                    placeholder="Catatan kondisi atau spesifikasi..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>
              </div>
            </template>
          </div>

          <!-- Sticky Action Footer untuk Edit Formulir -->
          <div
            v-if="activeTab === 'edit'"
            class="shrink-0 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b0f19] px-3 py-2.5 space-y-2"
          >
            <div
              v-if="showUnsavedGuard"
              class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/35 border border-amber-300 dark:border-amber-700/60 text-[11px] text-amber-800 dark:text-amber-200"
            >
              <span class="font-medium">Buang perubahan yang belum disimpan?</span>
              <div class="flex items-center gap-1 shrink-0">
                <UButton
                  label="Tetap Edit"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  @click="showUnsavedGuard = false"
                />
                <UButton
                  label="Buang"
                  size="xs"
                  color="warning"
                  variant="subtle"
                  @click="confirmDiscardToInspector"
                />
              </div>
            </div>

            <div class="flex items-center gap-2">
              <UButton
                label="Batal"
                color="neutral"
                variant="subtle"
                :class="['flex-1 justify-center cursor-pointer', isMobileDrawer ? 'min-h-[44px]' : '']"
                :disabled="saving"
                @click="handleCancelEdit"
              />
              <UButton
                label="Simpan Perubahan"
                icon="i-lucide-save"
                color="primary"
                variant="solid"
                :class="['flex-1 justify-center cursor-pointer', isMobileDrawer ? 'min-h-[44px]' : '']"
                :loading="saving || loading"
                @click="handleSaveEdit"
              />
            </div>
          </div>
        </div>

        <!-- Sticky Footer: Koordinat Klik Map Canvas -->
        <div class="border-t border-gray-200 dark:border-gray-800 shrink-0 px-3 py-2 bg-gray-50/50 dark:bg-gray-900/30">
          <div
            v-if="clickedCoordinate"
            class="flex items-center justify-between gap-2 text-xs"
          >
            <div class="flex items-center gap-1.5 tabular-nums text-[11px] text-gray-700 dark:text-gray-300 min-w-0">
              <span class="truncate">
                <span class="text-gray-500 dark:text-gray-400 text-[10px] mr-1">Lat</span>{{ clickedCoordinate[1].toFixed(6) }},
                <span class="text-gray-500 dark:text-gray-400 text-[10px] mr-1 ml-1.5">Long</span>{{ clickedCoordinate[0].toFixed(6) }}
              </span>
            </div>

            <UTooltip :text="copied ? 'Tersalin!' : 'Salin koordinat'">
              <UButton
                :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'"
                size="xs"
                :color="copied ? 'primary' : 'neutral'"
                variant="ghost"
                :aria-label="copied ? 'Koordinat tersalin' : 'Salin koordinat klik peta'"
                class="cursor-pointer shrink-0 h-6 px-1.5 text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                @click="copyCoords"
              />
            </UTooltip>
          </div>

          <div
            v-else
            class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 py-0.5"
          >
            <UIcon name="i-lucide-mouse-pointer-click" class="size-3.5 shrink-0 opacity-70" />
            <span class="italic text-[11px]">Klik peta untuk mengambil koordinat</span>
          </div>
        </div>
      </div>
    </Transition>
  </component>
</template>

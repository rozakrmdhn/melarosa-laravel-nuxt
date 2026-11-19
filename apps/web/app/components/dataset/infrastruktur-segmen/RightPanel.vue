<script setup lang="ts">
import {
  buildSumberDanaSelectItems,
  FOTO_TAHAP_OPTIONS,
  IDEAL_MIN_SEGMEN_FOTOS,
  KONDISI_OPTIONS,
  MAX_SEGMEN_FOTOS,
  PERKERASAN_OPTIONS,
  resolveSumberDanaLabel,
  STATUS_ASET_OPTIONS,
  STATUS_JALAN_OPTIONS,
  SUMBER_DANA_OPTIONS,
  withCurrentOption,
  type InfrastrukturSegmen,
  type InfrastrukturSegmenFoto,
  type InfrastrukturTipe,
  type JalanPorosRef,
  type PlottingAnggaran,
  type RefSumberDana,
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
  sumberDanaList?: RefSumberDana[];
  canEdit?: boolean;
  canDelete?: boolean;
  canSubmit?: boolean;
  canSplit?: boolean;
  canVerifyKecamatan?: boolean;
  canVerifyBappeda?: boolean;
  isKecamatanRole?: boolean;
  saveFeedback?: { type: 'success' | 'error' | 'warning'; message: string } | null;
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
  sumberDanaList: () => [],
  canEdit: true,
  canDelete: true,
  canSubmit: true,
  canSplit: undefined,
  canVerifyKecamatan: false,
  canVerifyBappeda: false,
  isKecamatanRole: false,
  saveFeedback: null,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'close'): void;
  (e: 'zoomToFeature', segmen: InfrastrukturSegmen): void;
  (e: 'editSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'deleteSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'submitVerifikasi', segmen: InfrastrukturSegmen): void;
  (e: 'openVerify', payload: { segmen: InfrastrukturSegmen; level: 'kecamatan' | 'bappeda'; defaultAction: 'approve' | 'reject' }): void;
  (e: 'viewInTable', segmen: InfrastrukturSegmen): void;
  (e: 'saveSegmen', payload: Partial<InfrastrukturSegmen>): void;
  (e: 'editGeometry', segmen: InfrastrukturSegmen): void;
  (e: 'splitSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'fotosUpdated', payload: { segmenId: string; fotos: InfrastrukturSegmenFoto[] }): void;
  (e: 'clearFeedback'): void;
}>();

const activeTab = ref<'inspector' | 'edit'>('inspector');
const showUnsavedGuard = ref(false);
const editBaselineJson = ref('');
const maxBuildYear = new Date().getFullYear() + 5;

const infrastrukturApi = useInfrastrukturApi();
const jalanPorosOptions = ref<JalanPorosRef[]>([]);
const isLoadingJalanPoros = ref(false);
let lastJalanPorosRegionKey = '';

// ─── Attachment Foto Dokumentasi Lapangan (Privat + Sanitasi Ketat) ─────────
const fotoInputRef = ref<HTMLInputElement | null>(null);
const fotoBlobUrls = reactive<Record<string, string>>({});
const uploadingFoto = ref(false);
const deletingFotoId = ref<string | null>(null);
const confirmDeleteFotoId = ref<string | null>(null);
const previewFoto = ref<InfrastrukturSegmenFoto | null>(null);
const fotoFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null);
const selectedTahapFoto = ref<string>(FOTO_TAHAP_OPTIONS[0]!.value);
const keteranganFoto = ref('');

const attachedFotos = computed<InfrastrukturSegmenFoto[]>(
  () => props.selectedSegmen?.fotos || [],
);

function suggestNextTahapFoto(fotos: InfrastrukturSegmenFoto[]) {
  const used = new Set(fotos.map((f) => f.tahap_label));
  const nextOption = FOTO_TAHAP_OPTIONS.find((opt) => !used.has(opt.value));
  selectedTahapFoto.value = (nextOption || FOTO_TAHAP_OPTIONS[0]!).value;
}

function revokeUnusedBlobUrls(activeIds: Set<string>) {
  Object.keys(fotoBlobUrls).forEach((fotoId) => {
    if (!activeIds.has(fotoId)) {
      const url = fotoBlobUrls[fotoId];
      if (url) URL.revokeObjectURL(url);
      delete fotoBlobUrls[fotoId];
    }
  });
}

async function syncFotoBlobPreviews(fotos: InfrastrukturSegmenFoto[]) {
  const activeIds = new Set(fotos.map((f) => f.id));
  revokeUnusedBlobUrls(activeIds);

  await Promise.all(
    fotos.map(async (foto) => {
      if (fotoBlobUrls[foto.id] || !foto.file_endpoint) return;
      try {
        fotoBlobUrls[foto.id] = await infrastrukturApi.fetchSegmenFotoBlobUrl(foto.file_endpoint);
      } catch {
        // Biarkan kosong jika preview gagal dimuat
      }
    }),
  );
}

watch(
  () => props.selectedSegmen?.id,
  () => {
    fotoFeedback.value = null;
    confirmDeleteFotoId.value = null;
    previewFoto.value = null;
    keteranganFoto.value = '';
  },
);

watch(
  attachedFotos,
  (fotos) => {
    suggestNextTahapFoto(fotos);
    syncFotoBlobPreviews(fotos);
  },
  { immediate: true, deep: true },
);

onBeforeUnmount(() => {
  revokeUnusedBlobUrls(new Set());
});

function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function triggerSelectFoto() {
  fotoFeedback.value = null;
  fotoInputRef.value?.click();
}

async function handleFotoFileSelected(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file || !props.selectedSegmen?.id) return;

  fotoFeedback.value = null;

  if (attachedFotos.value.length >= MAX_SEGMEN_FOTOS) {
    fotoFeedback.value = { type: 'error', message: `Maksimal ${MAX_SEGMEN_FOTOS} foto per segmen.` };
    return;
  }

  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    fotoFeedback.value = { type: 'error', message: 'Format foto harus JPG, PNG, atau WebP.' };
    return;
  }

  if (/\.(php\d*|phtml|phar|svg|html?|js|exe|sh)$/i.test(file.name)) {
    fotoFeedback.value = { type: 'error', message: 'Nama file tidak diizinkan.' };
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    fotoFeedback.value = { type: 'error', message: 'Ukuran file maksimal 5 MB.' };
    return;
  }

  uploadingFoto.value = true;
  try {
    const res = await infrastrukturApi.uploadSegmenFoto(props.selectedSegmen.id, file, {
      tahap_label: selectedTahapFoto.value,
      keterangan: keteranganFoto.value,
    });
    keteranganFoto.value = '';
    const updatedFotos = res?.fotos || [];
    emit('fotosUpdated', { segmenId: props.selectedSegmen.id, fotos: updatedFotos });
    fotoFeedback.value = { type: 'success', message: 'Foto terunggah dan disanitasi.' };
  } catch (err: any) {
    const firstValidationErr = err?.data?.errors?.foto?.[0];
    fotoFeedback.value = {
      type: 'error',
      message: firstValidationErr || err?.data?.message || 'Gagal mengunggah foto.',
    };
  } finally {
    uploadingFoto.value = false;
  }
}

async function handleConfirmDeleteFoto(fotoId: string) {
  if (!props.selectedSegmen?.id || deletingFotoId.value) return;
  fotoFeedback.value = null;
  deletingFotoId.value = fotoId;
  try {
    const res = await infrastrukturApi.deleteSegmenFoto(props.selectedSegmen.id, fotoId);
    confirmDeleteFotoId.value = null;
    if (previewFoto.value?.id === fotoId) {
      previewFoto.value = null;
    }
    emit('fotosUpdated', { segmenId: props.selectedSegmen.id, fotos: res?.fotos || [] });
    fotoFeedback.value = { type: 'success', message: 'Foto dihapus.' };
  } catch (err: any) {
    fotoFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menghapus foto.',
    };
  } finally {
    deletingFotoId.value = null;
  }
}

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
    } else if (activeTab.value === 'inspector' || !isEditDirty.value) {
      syncEditFormFromSegmen(seg);
      if (activeTab.value === 'edit') {
        loadEditJalanPorosOptions(seg);
      }
    }
  },
  { immediate: true },
);

const panjangGisNumeric = computed(() => {
  const raw = props.selectedSegmen?.panjang_meter_gis;
  if (raw === null || raw === undefined || String(raw).trim() === '') return null;
  const num = Number(raw);
  return Number.isFinite(num) && num > 0 ? Math.round(num * 100) / 100 : null;
});

const panjangGisDisplay = computed(() => {
  if (panjangGisNumeric.value === null) return '-';
  return String(panjangGisNumeric.value);
});

function syncPanjangFromGis() {
  if (panjangGisNumeric.value === null) return;
  editForm.panjang = panjangGisNumeric.value;
  editNumericErrors.panjang = null;
  editPanjangRef.value?.clearValidation();
}

const tipeItems = computed(() =>
  props.tipeList.map((t) => ({
    label: t.nama,
    value: t.kode,
  })),
);

const plottingItems = computed(() => {
  const list = [...props.plottingList];
  const currentPlot = props.selectedSegmen?.plotting;
  if (currentPlot?.id && !list.some((p) => p.id === currentPlot.id)) {
    list.unshift(currentPlot);
  }
  return [
    { label: 'Tanpa Kaitan Plotting (Mandiri)', value: null },
    ...list.map((p) => ({
      label: `${p.nama_kegiatan} (${formatRupiah(p.target_pagu_anggaran)})`,
      value: p.id,
    })),
  ];
});

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
const editKondisiButtons = [
  {
    value: 'Baik',
    label: 'Baik',
    dotClass: 'bg-emerald-600 dark:bg-emerald-400',
    activeClass: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-200 border-emerald-400 dark:border-emerald-700 ring-1 ring-emerald-500/30',
  },
  {
    value: 'Sedang',
    label: 'Sedang',
    dotClass: 'bg-amber-500 dark:bg-amber-400',
    activeClass: 'bg-amber-50 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200 border-amber-400 dark:border-amber-700 ring-1 ring-amber-500/30',
  },
  {
    value: 'Rusak Ringan',
    label: 'Rusak Ringan',
    dotClass: 'bg-orange-600 dark:bg-orange-400',
    activeClass: 'bg-orange-50 dark:bg-orange-950/60 text-orange-900 dark:text-orange-200 border-orange-400 dark:border-orange-700 ring-1 ring-orange-500/30',
  },
  {
    value: 'Rusak Berat',
    label: 'Rusak Berat',
    dotClass: 'bg-rose-600 dark:bg-rose-400',
    activeClass: 'bg-rose-50 dark:bg-rose-950/60 text-rose-900 dark:text-rose-200 border-rose-400 dark:border-rose-700 ring-1 ring-rose-500/30',
  },
];
const perkerasanItems = computed(() =>
  withCurrentOption(PERKERASAN_OPTIONS, editForm.jenis_perkerasan),
);
const statusJalanItems = computed(() =>
  withCurrentOption(STATUS_JALAN_OPTIONS, editForm.status_jalan),
);
const sumberDanaItems = computed(() =>
  buildSumberDanaSelectItems(props.sumberDanaList, editForm.sumber_dana, SUMBER_DANA_OPTIONS),
);

watch(
  () => editForm.plotting_id,
  (newPlottingId, oldPlottingId) => {
    if (!newPlottingId || newPlottingId === oldPlottingId || activeTab.value !== 'edit') return;
    const match = props.plottingList.find((p) => p.id === newPlottingId);
    if (match?.sumber_dana) {
      editForm.sumber_dana = match.sumber_dana;
    }
    if (match?.tahun_anggaran) {
      editForm.tahun_pembangunan = match.tahun_anggaran;
    }
  },
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
  emit('saveSegmen', {
    ...editForm,
    is_non_pagu: !editForm.plotting_id,
  });
}

function handleCancelEdit() {
  syncEditFormFromSegmen(props.selectedSegmen);
  showUnsavedGuard.value = false;
  activeTab.value = 'inspector';
}

const canSubmitCurrentSegmen = computed(() => {
  if (!props.canSubmit || !props.selectedSegmen) return false;
  const status = String(props.selectedSegmen.status_verifikasi || '').toLowerCase();
  return status === 'draft' || status === 'rejected_kecamatan' || status === 'rejected_bappeda';
});

const isWaitingDesaSubmission = computed(() => {
  if (!props.isKecamatanRole || !props.selectedSegmen) return false;
  if (props.canVerifyKecamatan || props.canVerifyBappeda || canSubmitCurrentSegmen.value) return false;
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
        <span
          class="text-[11px] font-medium text-gray-500 dark:text-gray-400 tracking-wide whitespace-nowrap"
          style="writing-mode: vertical-rl;"
        >
          Detail Data
        </span>
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
            <div
              v-if="saveFeedback"
              role="status"
              :class="[
                'p-2 rounded-lg border text-[11px] flex items-start justify-between gap-2',
                saveFeedback.type === 'success'
                  ? 'bg-emerald-50/90 dark:bg-emerald-950/35 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100'
                  : saveFeedback.type === 'warning'
                    ? 'bg-amber-50/90 dark:bg-amber-950/35 border-amber-200 dark:border-amber-800/80 text-amber-900 dark:text-amber-100'
                    : 'bg-red-50/90 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100',
              ]"
            >
              <span class="font-medium leading-snug">{{ saveFeedback.message }}</span>
              <button
                type="button"
                aria-label="Tutup pesan"
                class="p-0.5 rounded opacity-75 hover:opacity-100 cursor-pointer shrink-0"
                @click="emit('clearFeedback')"
              >
                <UIcon name="i-lucide-x" class="size-3" aria-hidden="true" />
              </button>
            </div>

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

                <!-- 2x2 Quick Specs Strip -->
                <div class="grid grid-cols-2 gap-1.5 pt-2 border-t border-gray-200/70 dark:border-gray-800/80">
                  <div class="px-2 py-1.5 rounded-lg bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-gray-800">
                    <span class="block text-[10px] text-gray-500 dark:text-gray-400">Panjang GIS</span>
                    <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                      {{ panjangGisNumeric !== null ? `${panjangGisNumeric.toLocaleString('id-ID', { maximumFractionDigits: 2 })} m` : '-' }}
                    </span>
                  </div>
                  <div class="px-2 py-1.5 rounded-lg bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-gray-800">
                    <span class="block text-[10px] text-gray-500 dark:text-gray-400">Lebar & Perkerasan</span>
                    <span class="font-semibold text-gray-900 dark:text-white truncate block">
                      {{ selectedSegmen.lebar ? `${Number(selectedSegmen.lebar).toLocaleString('id-ID')} m` : '-' }} · {{ selectedSegmen.jenis_perkerasan || '-' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Aksi Alur Verifikasi 3 Tingkat -->
              <div
                v-if="canSubmitCurrentSegmen || canVerifyKecamatan || canVerifyBappeda || isWaitingDesaSubmission"
                class="p-2.5 rounded-xl border space-y-2"
                :class="
                  canVerifyKecamatan || canVerifyBappeda
                    ? 'bg-emerald-50/75 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/80'
                    : canSubmitCurrentSegmen
                      ? 'bg-sky-50/75 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800/80'
                      : 'bg-amber-50/70 dark:bg-amber-950/25 border-amber-200 dark:border-amber-800/70'
                "
              >
                <template v-if="canVerifyKecamatan">
                  <p class="text-[11px] font-semibold text-emerald-950 dark:text-emerald-100">
                    Verifikasi Tingkat Kecamatan
                  </p>
                  <div class="flex items-center gap-1.5">
                    <UButton
                      icon="i-lucide-send-horizontal"
                      label="Teruskan ke Bappeda"
                      size="xs"
                      color="success"
                      variant="solid"
                      class="flex-1 justify-center cursor-pointer"
                      @click="emit('openVerify', { segmen: selectedSegmen, level: 'kecamatan', defaultAction: 'approve' })"
                    />
                    <UButton
                      icon="i-lucide-rotate-ccw"
                      label="Tolak"
                      size="xs"
                      color="error"
                      variant="outline"
                      class="cursor-pointer shrink-0"
                      @click="emit('openVerify', { segmen: selectedSegmen, level: 'kecamatan', defaultAction: 'reject' })"
                    />
                  </div>
                </template>

                <template v-else-if="canVerifyBappeda">
                  <p class="text-[11px] font-semibold text-emerald-950 dark:text-emerald-100">
                    Verifikasi Final Bappeda
                  </p>
                  <div class="flex items-center gap-1.5">
                    <UButton
                      icon="i-lucide-badge-check"
                      label="Sahkan Final"
                      size="xs"
                      color="success"
                      variant="solid"
                      class="flex-1 justify-center cursor-pointer"
                      @click="emit('openVerify', { segmen: selectedSegmen, level: 'bappeda', defaultAction: 'approve' })"
                    />
                    <UButton
                      icon="i-lucide-rotate-ccw"
                      label="Tolak"
                      size="xs"
                      color="error"
                      variant="outline"
                      class="cursor-pointer shrink-0"
                      @click="emit('openVerify', { segmen: selectedSegmen, level: 'bappeda', defaultAction: 'reject' })"
                    />
                  </div>
                </template>

                <template v-else-if="canSubmitCurrentSegmen">
                  <p class="text-[11px] font-semibold text-sky-950 dark:text-sky-100">
                    Siap Diajukan Verifikasi
                  </p>
                  <UButton
                    icon="i-lucide-send"
                    label="Ajukan ke Verifikasi Kecamatan"
                    size="xs"
                    color="primary"
                    variant="solid"
                    class="w-full justify-center cursor-pointer"
                    @click="emit('submitVerifikasi', selectedSegmen)"
                  />
                </template>

                <p v-else-if="isWaitingDesaSubmission" class="text-[11px] text-amber-900 dark:text-amber-100 leading-snug">
                  Segmen masih berstatus <strong>Draft</strong> di Desa. Menunggu akun Desa mengajukan verifikasi sebelum dapat diteruskan ke Bappeda.
                </p>
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
                    ['Sumber Dana', resolveSumberDanaLabel(sumberDanaList, selectedSegmen.sumber_dana)],
                    ['Status Aset', selectedSegmen.status_aset],
                    ['Tahun Anggaran', selectedSegmen.tahun_pembangunan],
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
                  <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-blue-700 dark:text-blue-300">
                      {{ selectedSegmen.plotting.nama_kegiatan }}
                    </p>
                    <UBadge
                      v-if="selectedSegmen.plotting.nomor_sk"
                      :label="selectedSegmen.plotting.nomor_sk"
                      size="xs"
                      color="info"
                      variant="subtle"
                      class="text-[9px] px-1.5 py-0 h-4 shrink-0"
                    />
                  </div>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-600 dark:text-gray-400">Target Pagu:</span>
                    <span class="tabular-nums font-medium text-gray-900 dark:text-white">
                      {{ formatRupiah(selectedSegmen.plotting.target_pagu_anggaran) }}
                    </span>
                  </div>
                </div>
                <div v-else-if="selectedSegmen.is_non_pagu" class="p-2.5 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-300/70 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-center text-[11px]">
                  Inventarisasi Eksisting Non-Pagu (Tanpa SK / Anggaran Baru)
                </div>
                <div v-else class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-center">
                  Belum terikat ke alokasi plotting anggaran.
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
                <!-- Grup 1: Identitas & Referensi -->
                <div class="p-2.5 rounded-xl bg-gray-50/70 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-800 space-y-2.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                      Identitas & Referensi
                    </span>
                  </div>

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
                    hint="Opsional, mengikat segmen ke koridor poros."
                    size="sm"
                  >
                    <USelectMenu
                      v-model="editForm.parent_id"
                      :items="jalanPorosItems"
                      :loading="isLoadingJalanPoros"
                      value-key="value"
                      label-key="label"
                      placeholder="Pilih ruas jalan poros..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>

                <!-- Grup 2: Dimensi & Kondisi Fisik -->
                <div class="p-2.5 rounded-xl bg-gray-50/70 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-800 space-y-2.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                      Dimensi & Kondisi Fisik
                    </span>
                    <button
                      v-if="panjangGisNumeric !== null && Number(editForm.panjang ?? 0) !== panjangGisNumeric"
                      type="button"
                      class="text-[10px] font-semibold text-blue-700 dark:text-blue-300 hover:underline cursor-pointer"
                      title="Samakan panjang isian dengan hasil pengukuran GIS"
                      @click="syncPanjangFromGis"
                    >
                      Samakan GIS ({{ panjangGisDisplay }} m)
                    </button>
                    <span
                      v-else-if="panjangGisNumeric !== null"
                      class="text-[10px] font-mono text-gray-600 dark:text-gray-400 tabular-nums"
                    >
                      GIS: {{ panjangGisDisplay }} m
                    </span>
                  </div>

                  <div class="grid grid-cols-2 gap-2.5">
                    <UFormField
                      label="Panjang (m)"
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
                  </div>

                  <div class="space-y-1.5">
                    <span class="block text-xs font-medium text-gray-700 dark:text-gray-200">
                      Kondisi Fisik
                    </span>
                    <div
                      role="radiogroup"
                      aria-label="Kondisi fisik segmen"
                      class="grid grid-cols-2 gap-1.5"
                    >
                      <button
                        v-for="k in editKondisiButtons"
                        :key="k.value"
                        type="button"
                        role="radio"
                        :aria-checked="editForm.kondisi === k.value"
                        class="flex items-center gap-1.5 px-2.5 py-2 rounded-lg border text-xs font-medium transition-colors cursor-pointer text-left focus-visible:outline-2 focus-visible:outline-blue-600"
                        :class="
                          editForm.kondisi === k.value
                            ? `${k.activeClass} font-semibold`
                            : 'bg-white dark:bg-[#0b0f19] border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900'
                        "
                        @click="editForm.kondisi = k.value"
                      >
                        <span class="size-2 rounded-full shrink-0" :class="k.dotClass" aria-hidden="true" />
                        <span class="truncate">{{ k.label }}</span>
                      </button>
                    </div>
                  </div>

                  <div class="grid grid-cols-2 gap-2.5">
                    <UFormField label="Jenis Perkerasan" size="sm">
                      <USelectMenu
                        v-model="editForm.jenis_perkerasan"
                        :items="perkerasanItems"
                        value-key="value"
                        label-key="label"
                        :search-input="false"
                        placeholder="Pilih..."
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
                        placeholder="Pilih..."
                        size="sm"
                        class="w-full"
                      />
                    </UFormField>
                  </div>
                </div>

                <!-- Grup 3: Pendanaan, Tahun Anggaran & Aset -->
                <div class="p-2.5 rounded-xl bg-gray-50/70 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-800 space-y-2.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                      Pendanaan & Tahun Anggaran
                    </span>
                    <span
                      v-if="editForm.plotting_id"
                      class="px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-700/80 text-[10px] font-semibold text-emerald-900 dark:text-emerald-200"
                    >
                      Mengikuti Pagu
                    </span>
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

                  <div class="grid grid-cols-2 gap-2.5">
                    <UFormField
                      label="Tahun Anggaran"
                      :error="editNumericErrors.tahun_pembangunan || undefined"
                      size="sm"
                    >
                      <NumericTextInput
                        ref="editTahunRef"
                        v-model="editForm.tahun_pembangunan"
                        field-label="Tahun anggaran"
                        :allow-decimal="false"
                        :min="1900"
                        :max="maxBuildYear"
                        :max-digits="4"
                        :disabled="Boolean(editForm.plotting_id)"
                        placeholder="Contoh: 2026"
                        size="sm"
                        @update:error="editNumericErrors.tahun_pembangunan = $event"
                      />
                    </UFormField>

                    <UFormField label="Sumber Dana" size="sm">
                      <USelectMenu
                        v-model="editForm.sumber_dana"
                        :items="sumberDanaItems"
                        value-key="value"
                        label-key="label"
                        :disabled="Boolean(editForm.plotting_id)"
                        placeholder="Pilih sumber..."
                        size="sm"
                        class="w-full"
                      />
                    </UFormField>
                  </div>

                  <UFormField label="Status Aset" size="sm">
                    <USelectMenu
                      v-model="editForm.status_aset"
                      :items="statusAsetItems"
                      value-key="value"
                      label-key="label"
                      :search-input="false"
                      placeholder="Pilih status aset..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>

                  <UFormField label="Keterangan Tambahan" size="sm">
                    <UTextarea
                      v-model="editForm.keterangan"
                      :rows="2"
                      placeholder="Catatan kondisi atau spesifikasi lapangan..."
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>
              </div>
            </template>

            <!-- Foto Dokumentasi Lapangan (Tersedia di Detail Data & Form Edit) -->
            <div
              class="space-y-2"
              :class="activeTab === 'edit' ? 'pt-3 border-t border-gray-200 dark:border-gray-800' : ''"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                  Foto Dokumentasi Lapangan
                </span>
                <span
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[11px] font-semibold tabular-nums"
                  :class="
                    attachedFotos.length >= IDEAL_MIN_SEGMEN_FOTOS
                      ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/80'
                      : attachedFotos.length > 0
                        ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/80'
                        : 'bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'
                  "
                >
                  {{ attachedFotos.length }}/{{ MAX_SEGMEN_FOTOS }}
                  {{ attachedFotos.length >= IDEAL_MIN_SEGMEN_FOTOS ? 'Ideal' : '' }}
                </span>
              </div>

              <div
                v-if="fotoFeedback"
                role="status"
                :class="[
                  'px-2.5 py-1.5 rounded-lg border text-[11px] font-medium flex items-center justify-between gap-2',
                  fotoFeedback.type === 'success'
                    ? 'bg-emerald-50/90 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100'
                    : 'bg-red-50/90 dark:bg-red-950/40 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100',
                ]"
              >
                <span>{{ fotoFeedback.message }}</span>
                <button
                  type="button"
                  aria-label="Tutup pesan foto"
                  class="p-0.5 rounded opacity-75 hover:opacity-100 cursor-pointer"
                  @click="fotoFeedback = null"
                >
                  <UIcon name="i-lucide-x" class="size-3" aria-hidden="true" />
                </button>
              </div>

              <!-- Grid Foto Privat Terlampir -->
              <div v-if="attachedFotos.length > 0" class="grid grid-cols-2 gap-2">
                <div
                  v-for="foto in attachedFotos"
                  :key="foto.id"
                  class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0e1424] overflow-hidden flex flex-col"
                >
                  <button
                    type="button"
                    :aria-label="`Perbesar foto ${foto.tahap_label}`"
                    class="relative w-full aspect-[4/3] bg-gray-100 dark:bg-gray-900 overflow-hidden cursor-pointer group focus-visible:outline-2 focus-visible:outline-blue-600"
                    @click="previewFoto = foto"
                  >
                    <img
                      v-if="fotoBlobUrls[foto.id]"
                      :src="fotoBlobUrls[foto.id]"
                      :alt="`${foto.tahap_label}${foto.keterangan ? `: ${foto.keterangan}` : ''}`"
                      class="w-full h-full object-cover"
                    >
                    <div
                      v-else
                      class="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-500"
                    >
                      <UIcon name="i-lucide-image" class="size-5 animate-pulse" aria-hidden="true" />
                    </div>
                    <span
                      class="absolute inset-x-0 bottom-0 px-1.5 py-0.5 bg-slate-950/75 text-white text-[10px] font-medium truncate text-left"
                    >
                      {{ foto.tahap_label }}
                    </span>
                  </button>

                  <div class="p-1.5 space-y-1 text-[10px] text-gray-600 dark:text-gray-400">
                    <p v-if="foto.keterangan" class="text-gray-800 dark:text-gray-200 line-clamp-1 font-medium">
                      {{ foto.keterangan }}
                    </p>
                    <div class="flex items-center justify-between gap-1">
                      <span class="tabular-nums truncate">
                        {{ formatBytes(foto.ukuran_byte) }}
                      </span>

                      <template v-if="canEdit">
                        <div v-if="confirmDeleteFotoId === foto.id" class="flex items-center gap-1 shrink-0">
                          <button
                            type="button"
                            class="text-rose-700 dark:text-rose-300 font-semibold hover:underline cursor-pointer"
                            :disabled="deletingFotoId === foto.id"
                            @click="handleConfirmDeleteFoto(foto.id)"
                          >
                            Ya
                          </button>
                          <button
                            type="button"
                            class="text-gray-600 dark:text-gray-400 hover:underline cursor-pointer"
                            :disabled="deletingFotoId === foto.id"
                            @click="confirmDeleteFotoId = null"
                          >
                            Batal
                          </button>
                        </div>
                        <button
                          v-else
                          type="button"
                          :aria-label="`Hapus foto ${foto.tahap_label}`"
                          class="text-rose-700 dark:text-rose-300 hover:underline cursor-pointer shrink-0 font-medium"
                          @click="confirmDeleteFotoId = foto.id"
                        >
                          Hapus
                        </button>
                      </template>
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-else
                class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-300 dark:border-gray-700 text-center text-gray-600 dark:text-gray-400 space-y-1"
              >
                <UIcon name="i-lucide-camera" class="size-5 mx-auto text-gray-500 dark:text-gray-400" aria-hidden="true" />
                <p class="text-[11px] font-medium">Belum ada foto dokumentasi lapangan.</p>
                <p class="text-[10px]">Idealnya 3 sampai 4 foto (Titik Awal 0%, Tengah 50%, Akhir 100%).</p>
              </div>

              <!-- Upload Form Terintegrasi (Saat User Boleh Edit & Kuota < 4) -->
              <div
                v-if="canEdit && attachedFotos.length < MAX_SEGMEN_FOTOS"
                class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-2"
              >
                <input
                  ref="fotoInputRef"
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                  class="hidden"
                  @change="handleFotoFileSelected"
                >
                <div class="grid grid-cols-1 gap-1.5">
                  <USelectMenu
                    v-model="selectedTahapFoto"
                    :items="FOTO_TAHAP_OPTIONS"
                    value-key="value"
                    label-key="label"
                    :search-input="false"
                    size="xs"
                    aria-label="Tahap posisi foto dokumentasi"
                    class="w-full"
                  />
                  <UInput
                    v-model="keteranganFoto"
                    placeholder="Keterangan lokasi foto (opsional)"
                    size="xs"
                    maxlength="200"
                    class="w-full"
                  />
                </div>
                <div class="flex items-center justify-between gap-2">
                  <span class="text-[10px] text-gray-600 dark:text-gray-400 leading-tight">
                    JPG/PNG/WebP maks 5 MB (privasi EXIF dihapus otomatis).
                  </span>
                  <UButton
                    icon="i-lucide-upload"
                    label="Unggah Foto"
                    size="xs"
                    color="primary"
                    variant="subtle"
                    :loading="uploadingFoto"
                    class="cursor-pointer shrink-0"
                    @click="triggerSelectFoto"
                  />
                </div>
              </div>
            </div>
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

            <div
              v-if="isEditDirty && !showUnsavedGuard"
              class="text-[11px] font-medium text-amber-800 dark:text-amber-300 flex items-center gap-1.5"
            >
              <span class="size-1.5 rounded-full bg-amber-500 shrink-0" aria-hidden="true" />
              <span>Ada perubahan atribut yang belum disimpan</span>
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

    <!-- Lightbox Pratinjau Foto Privat -->
    <UModal
      :open="Boolean(previewFoto)"
      :title="previewFoto?.tahap_label || 'Pratinjau Foto Dokumentasi'"
      :description="previewFoto?.keterangan || 'Dokumentasi lapangan segmen infrastruktur'"
      :ui="{ content: 'sm:max-w-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 rounded-2xl' }"
      @update:open="(v) => { if (!v) previewFoto = null; }"
    >
      <template #body>
        <div v-if="previewFoto" class="space-y-2.5 text-xs">
          <div class="rounded-xl overflow-hidden bg-slate-950 flex items-center justify-center max-h-[65vh]">
            <img
              v-if="fotoBlobUrls[previewFoto.id]"
              :src="fotoBlobUrls[previewFoto.id]"
              :alt="previewFoto.tahap_label"
              class="max-h-[65vh] w-auto object-contain"
            >
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-600 dark:text-gray-400 tabular-nums">
            <span>
              {{ previewFoto.lebar_px || '?' }} x {{ previewFoto.tinggi_px || '?' }} px · {{ formatBytes(previewFoto.ukuran_byte) }}
            </span>
            <span v-if="previewFoto.uploader?.name">
              Diunggah oleh: {{ previewFoto.uploader.name }}
            </span>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end w-full">
          <UButton label="Tutup" color="neutral" variant="outline" @click="previewFoto = null" />
        </div>
      </template>
    </UModal>
  </component>
</template>

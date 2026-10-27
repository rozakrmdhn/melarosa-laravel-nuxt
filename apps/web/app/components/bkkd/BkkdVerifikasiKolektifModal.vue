<script setup lang="ts">
import type { BkkdProposal, RefBkkdBidang, RefOpd } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import { getTahunAnggaranOptions } from '~/utils/helpers';

interface Props {
  open: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'success'): void;
}>();

const toast = useToast();
const api = useBkkdApi();

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit('update:open', val),
});

const activeStep = ref<'dokumen' | 'pilih_desa' | 'matriks'>('dokumen');
const submitting = ref(false);
const uploadingFile = ref(false);
const loadingProposals = ref(false);
const loadingReferences = ref(false);

const opdList = ref<RefOpd[]>([]);
const bidangList = ref<RefBkkdBidang[]>([]);
const tahunOptions = getTahunAnggaranOptions(false);

// Header Berita Acara State
const headerState = reactive({
  tahun_anggaran: new Date().getFullYear(),
  id_opd: '',
  nomor_surat_hasil: '',
  tanggal_surat_hasil: new Date().toISOString().split('T')[0],
  tanggal_diterima: new Date().toISOString().split('T')[0],
  catatan_umum: '',
  tipe_sumber: 'file' as 'file' | 'link',
  url_link: '',
  nama_dokumen_asli: '',
});

const uploadedDocPath = ref<string | null>(null);
const uploadedDocName = ref<string | null>(null);

// Filter & Proposal Selection State
const filterKecamatan = ref<number | 'ALL'>('ALL');
const filterBidang = ref<string | 'ALL'>('ALL');
const searchProposal = ref('');

const availableProposals = ref<BkkdProposal[]>([]);
const selectedProposalIds = ref<Set<string>>(new Set());

// Items Matriks Form State: Record<proposalId, { hasil, nilai_rekomendasi, panjang_rekomendasi, lebar_rekomendasi, tinggi_rekomendasi, volume_rekomendasi, catatan_opd }>
interface ItemFormState {
  hasil: 'layak' | 'layak_bersyarat' | 'tidak_layak' | 'dikembalikan';
  nilai_rekomendasi: number | null;
  panjang_rekomendasi: number | null;
  lebar_rekomendasi: number | null;
  tinggi_rekomendasi: number | null;
  volume_rekomendasi: number | null;
  catatan_opd: string;
}
const itemsState = reactive<Record<string, ItemFormState>>({});

const hasilOptions = [
  { label: 'Layak', value: 'layak' },
  { label: 'Layak Bersyarat', value: 'layak_bersyarat' },
  { label: 'Tidak Layak', value: 'tidak_layak' },
  { label: 'Dikembalikan', value: 'dikembalikan' },
];

function formatCurrency(val: number | null | undefined) {
  if (val === null || val === undefined) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

async function loadReferences() {
  loadingReferences.value = true;
  try {
    const [opdRes, bidangRes] = await Promise.all([
      api.fetchRefOpd(),
      api.fetchRefBidang(),
    ]);
    opdList.value = opdRes.data || [];
    bidangList.value = bidangRes.data || [];
    if (!headerState.id_opd && opdList.value.length > 0) {
      headerState.id_opd = opdList.value[0].id;
    }
  } catch (err) {
    console.error('Gagal memuat referensi OPD/Bidang:', err);
  } finally {
    loadingReferences.value = false;
  }
}

async function loadCandidateProposals() {
  loadingProposals.value = true;
  try {
    const params: Record<string, any> = {
      status: 'diajukan_opd',
      tahun_anggaran: headerState.tahun_anggaran,
      per_page: 100,
    };
    if (filterKecamatan.value !== 'ALL') {
      params.id_kecamatan = filterKecamatan.value;
    }
    if (filterBidang.value !== 'ALL') {
      params.id_bidang = filterBidang.value;
    }
    const res = await api.fetchProposals(params);
    availableProposals.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat daftar proposal diajukan_opd:', err);
  } finally {
    loadingProposals.value = false;
  }
}

const filteredProposals = computed(() => {
  let list = availableProposals.value;
  if (searchProposal.value.trim()) {
    const q = searchProposal.value.trim().toLowerCase();
    list = list.filter(
      (p) =>
        p.nomor_registrasi?.toLowerCase().includes(q) ||
        p.judul_kegiatan?.toLowerCase().includes(q) ||
        p.nama_desa?.toLowerCase().includes(q) ||
        p.nama_kecamatan?.toLowerCase().includes(q)
    );
  }
  return list;
});

const kecamatanOptions = computed(() => {
  const map = new Map<number, string>();
  availableProposals.value.forEach((p) => {
    if (p.id_kecamatan && p.nama_kecamatan) {
      map.set(p.id_kecamatan, p.nama_kecamatan);
    }
  });
  const list = Array.from(map.entries()).map(([id, nama]) => ({
    label: nama,
    value: id,
  }));
  return [{ label: 'Semua Kecamatan', value: 'ALL' }, ...list];
});

const bidangOptions = computed(() => [
  { label: 'Semua Bidang', value: 'ALL' },
  ...bidangList.value.map((b) => ({ label: b.nama, value: b.id })),
]);

function toggleSelectProposal(p: BkkdProposal) {
  if (selectedProposalIds.value.has(p.id)) {
    selectedProposalIds.value.delete(p.id);
  } else {
    selectedProposalIds.value.add(p.id);
    if (!itemsState[p.id]) {
      itemsState[p.id] = {
        hasil: 'layak',
        nilai_rekomendasi: p.nilai_usulan ?? null,
        panjang_rekomendasi: p.panjang ?? null,
        lebar_rekomendasi: p.lebar ?? null,
        tinggi_rekomendasi: p.tinggi ?? null,
        volume_rekomendasi: p.volume ?? null,
        catatan_opd: '',
      };
    }
  }
}

function selectAllFiltered() {
  filteredProposals.value.forEach((p) => {
    selectedProposalIds.value.add(p.id);
    if (!itemsState[p.id]) {
      itemsState[p.id] = {
        hasil: 'layak',
        nilai_rekomendasi: p.nilai_usulan ?? null,
        panjang_rekomendasi: p.panjang ?? null,
        lebar_rekomendasi: p.lebar ?? null,
        tinggi_rekomendasi: p.tinggi ?? null,
        volume_rekomendasi: p.volume ?? null,
        catatan_opd: '',
      };
    }
  });
}

function clearAllSelected() {
  selectedProposalIds.value.clear();
}

const selectedProposalsList = computed(() =>
  availableProposals.value.filter((p) => selectedProposalIds.value.has(p.id))
);

const totalNilaiUsulanSelected = computed(() =>
  selectedProposalsList.value.reduce((acc, p) => acc + (p.nilai_usulan || 0), 0)
);

const totalNilaiRekomendasiSelected = computed(() =>
  selectedProposalsList.value.reduce((acc, p) => {
    const item = itemsState[p.id];
    if (item && item.hasil !== 'tidak_layak' && item.hasil !== 'dikembalikan') {
      return acc + (item.nilai_rekomendasi || 0);
    }
    return acc;
  }, 0)
);

// Quick preset actions for table
function setAllStatus(status: 'layak' | 'layak_bersyarat' | 'tidak_layak' | 'dikembalikan') {
  selectedProposalsList.value.forEach((p) => {
    if (itemsState[p.id]) {
      itemsState[p.id].hasil = status;
      if (status === 'tidak_layak' || status === 'dikembalikan') {
        itemsState[p.id].nilai_rekomendasi = 0;
      } else if (!itemsState[p.id].nilai_rekomendasi) {
        itemsState[p.id].nilai_rekomendasi = p.nilai_usulan ?? 0;
      }
    }
  });
}

function copyAllProposedValues() {
  selectedProposalsList.value.forEach((p) => {
    if (itemsState[p.id] && itemsState[p.id].hasil !== 'tidak_layak' && itemsState[p.id].hasil !== 'dikembalikan') {
      itemsState[p.id].nilai_rekomendasi = p.nilai_usulan ?? 0;
    }
  });
}

async function handleFileUpload(e: Event) {
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0) return;

  const file = target.files[0];
  uploadingFile.value = true;
  try {
    const res = await api.uploadTemporaryDocument(file, 'bkkd-verifikasi');
    if (res.data?.path) {
      uploadedDocPath.value = res.data.path;
      uploadedDocName.value = file.name;
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Dokumen Berita Acara Terunggah',
        description: `File '${file.name}' siap diproses untuk seluruh usulan terpilih.`,
        color: 'success',
      });
    }
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Mengunggah Dokumen',
      description: err?.data?.message || 'Pastikan file berekstensi PDF/Excel dengan ukuran maks 15MB.',
      color: 'error',
    });
  } finally {
    uploadingFile.value = false;
  }
}

async function handleSaveKolektif() {
  if (!headerState.id_opd) {
    toast.add({ icon: 'i-lucide-alert-circle', title: 'Validasi', description: 'OPD teknis pemeriksa wajib dipilih.', color: 'error' });
    activeStep.value = 'dokumen';
    return;
  }
  if (!headerState.nomor_surat_hasil.trim()) {
    toast.add({ icon: 'i-lucide-alert-circle', title: 'Validasi', description: 'Nomor surat Berita Acara wajib diisi.', color: 'error' });
    activeStep.value = 'dokumen';
    return;
  }
  if (headerState.tipe_sumber === 'file' && !uploadedDocPath.value) {
    toast.add({ icon: 'i-lucide-alert-circle', title: 'Validasi', description: 'Unggah berkas fisik Berita Acara (PDF/Excel) terlebih dahulu.', color: 'error' });
    activeStep.value = 'dokumen';
    return;
  }
  if (headerState.tipe_sumber === 'link') {
    if (!headerState.url_link.trim()) {
      toast.add({ icon: 'i-lucide-alert-circle', title: 'Validasi', description: 'Tautan URL Cloud dokumen wajib diisi.', color: 'error' });
      activeStep.value = 'dokumen';
      return;
    }
    try {
      new URL(headerState.url_link.trim());
    } catch {
      toast.add({ icon: 'i-lucide-alert-circle', title: 'Validasi', description: 'Format tautan URL Cloud tidak valid. Pastikan menyertakan http:// atau https://', color: 'error' });
      activeStep.value = 'dokumen';
      return;
    }
  }
  if (selectedProposalIds.value.size === 0) {
    toast.add({ icon: 'i-lucide-alert-circle', title: 'Validasi', description: 'Pilih minimal 1 usulan desa yang masuk dalam Berita Acara ini.', color: 'error' });
    activeStep.value = 'pilih_desa';
    return;
  }

  submitting.value = true;
  try {
    const itemsPayload = selectedProposalsList.value.map((p) => {
      const it = itemsState[p.id];
      const isUnqualified = it.hasil === 'tidak_layak' || it.hasil === 'dikembalikan';
      return {
        id_proposal: p.id,
        hasil: it.hasil,
        nilai_rekomendasi: isUnqualified ? null : Number(it.nilai_rekomendasi || 0),
        panjang_rekomendasi: isUnqualified ? null : (it.panjang_rekomendasi ? Number(it.panjang_rekomendasi) : null),
        lebar_rekomendasi: isUnqualified ? null : (it.lebar_rekomendasi ? Number(it.lebar_rekomendasi) : null),
        tinggi_rekomendasi: isUnqualified ? null : (it.tinggi_rekomendasi ? Number(it.tinggi_rekomendasi) : null),
        volume_rekomendasi: isUnqualified ? null : (it.volume_rekomendasi ? Number(it.volume_rekomendasi) : null),
        catatan_opd: it.catatan_opd || null,
      };
    });

    const isLink = headerState.tipe_sumber === 'link';
    const payload = {
      id_opd: headerState.id_opd,
      nomor_surat_hasil: headerState.nomor_surat_hasil.trim(),
      tanggal_surat_hasil: headerState.tanggal_surat_hasil,
      tanggal_diterima: headerState.tanggal_diterima || headerState.tanggal_surat_hasil,
      tahun_anggaran: headerState.tahun_anggaran,
      tipe_sumber: headerState.tipe_sumber,
      url_link: isLink ? headerState.url_link.trim() : null,
      path_dokumen: isLink ? null : uploadedDocPath.value,
      nama_dokumen_asli: isLink
        ? (headerState.nama_dokumen_asli.trim() || 'Tautan Berita Acara (Cloud)')
        : (uploadedDocName.value || 'berita_acara_verifikasi.pdf'),
      catatan_umum: headerState.catatan_umum || null,
      items: itemsPayload,
    };

    const res = await api.submitVerifikasiKolektif(payload);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Verifikasi Kolektif Berhasil',
      description: res.message || `Berita acara berhasil dicatat untuk ${itemsPayload.length} desa.`,
      color: 'success',
    });

    emit('success');
    isOpen.value = false;
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menyimpan Verifikasi Kolektif',
      description: err?.data?.message || 'Terjadi kesalahan pada server saat memproses transaksi.',
      color: 'error',
    });
  } finally {
    submitting.value = false;
  }
}

watch(
  () => props.open,
  async (newVal) => {
    if (newVal) {
      activeStep.value = 'dokumen';
      uploadedDocPath.value = null;
      uploadedDocName.value = null;
      headerState.tipe_sumber = 'file';
      headerState.url_link = '';
      headerState.nama_dokumen_asli = '';
      selectedProposalIds.value.clear();
      Object.keys(itemsState).forEach((k) => delete itemsState[k]);
      headerState.nomor_surat_hasil = '';
      headerState.catatan_umum = '';

      await loadReferences();
      await loadCandidateProposals();
    }
  }
);

watch([filterKecamatan, filterBidang, () => headerState.tahun_anggaran], () => {
  if (props.open) {
    loadCandidateProposals();
  }
});
</script>

<template>
  <UModal
    v-model:open="isOpen"
    title="Verifikasi Kolektif OPD (1 Dokumen Banyak Desa)"
    description="Catat Berita Acara telaah teknis OPD untuk banyak usulan desa sekaligus tanpa perlu mengulang upload dokumen."
    :ui="{ content: 'sm:max-w-5xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <div class="space-y-5 py-1">
        <!-- Stepper Navigation -->
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/[0.06] pb-3 text-xs">
          <div class="flex items-center gap-1 sm:gap-2">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 cursor-pointer min-h-[36px]"
              :class="activeStep === 'dokumen' ? 'bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
              @click="activeStep = 'dokumen'"
            >
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] bg-primary-600 text-white font-bold">1</span>
              <span>Dokumen Berita Acara</span>
            </button>

            <UIcon name="i-lucide-chevron-right" class="w-3.5 h-3.5 text-gray-400 shrink-0" />

            <button
              type="button"
              class="px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 cursor-pointer min-h-[36px]"
              :class="activeStep === 'pilih_desa' ? 'bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
              @click="activeStep = 'pilih_desa'"
            >
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold" :class="selectedProposalIds.size > 0 ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">2</span>
              <span>Pilih Desa ({{ selectedProposalIds.size }})</span>
            </button>

            <UIcon name="i-lucide-chevron-right" class="w-3.5 h-3.5 text-gray-400 shrink-0" />

            <button
              type="button"
              class="px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 cursor-pointer min-h-[36px]"
              :class="activeStep === 'matriks' ? 'bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
              @click="activeStep = 'matriks'"
            >
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold" :class="selectedProposalIds.size > 0 ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">3</span>
              <span>Isi Hasil & Rekomendasi</span>
            </button>
          </div>

          <div v-if="selectedProposalIds.size > 0" class="hidden sm:flex items-center gap-2 text-[11px] font-mono text-gray-600 dark:text-gray-400">
            <span class="font-bold text-primary-600 dark:text-primary-400">{{ selectedProposalIds.size }} Desa</span>
            <span>&bull;</span>
            <span>Rekomendasi: <strong class="text-emerald-600 dark:text-emerald-400">{{ formatCurrency(totalNilaiRekomendasiSelected) }}</strong></span>
          </div>
        </div>

        <!-- STEP 1: Dokumen Berita Acara -->
        <div v-show="activeStep === 'dokumen'" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Tahun Anggaran <span class="text-red-500">*</span>
              </label>
              <USelectMenu
                v-model="headerState.tahun_anggaran"
                :items="tahunOptions"
                value-key="value"
                label-key="label"
                class="w-full text-xs"
                size="sm"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                OPD Teknis Pemeriksa <span class="text-red-500">*</span>
              </label>
              <USelectMenu
                v-model="headerState.id_opd"
                :items="opdList.map((o) => ({ label: `${o.nama} (${o.kode})`, value: o.id }))"
                value-key="value"
                label-key="label"
                placeholder="Pilih OPD Teknis..."
                class="w-full text-xs"
                size="sm"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Nomor Surat / Berita Acara Telaah <span class="text-red-500">*</span>
              </label>
              <UInput
                v-model="headerState.nomor_surat_hasil"
                placeholder="Contoh: 600/145/412.204/2026"
                class="w-full text-xs"
                size="sm"
              />
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Tanggal Surat <span class="text-red-500">*</span>
                </label>
                <UInput
                  v-model="headerState.tanggal_surat_hasil"
                  type="date"
                  class="w-full text-xs"
                  size="sm"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Tanggal Diterima
                </label>
                <UInput
                  v-model="headerState.tanggal_diterima"
                  type="date"
                  class="w-full text-xs"
                  size="sm"
                />
              </div>
            </div>
          </div>

          <!-- Pemilihan Metode Sumber Dokumen (Upload Berkas Fisik vs Tautan Cloud) -->
          <div class="space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                Metode Lampiran Berita Acara <span class="text-red-500">*</span>
              </label>
              <div class="inline-flex p-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-white/[0.08] self-start sm:self-auto">
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5 min-h-[36px]"
                  :class="headerState.tipe_sumber === 'file' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-2xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
                  @click="headerState.tipe_sumber = 'file'"
                >
                  <UIcon name="i-lucide-upload" class="w-3.5 h-3.5" />
                  <span>Unggah Berkas Fisik</span>
                </button>
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5 min-h-[36px]"
                  :class="headerState.tipe_sumber === 'link' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-2xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
                  @click="headerState.tipe_sumber = 'link'"
                >
                  <UIcon name="i-lucide-cloud" class="w-3.5 h-3.5" />
                  <span>Tautan Cloud Link</span>
                </button>
              </div>
            </div>

            <!-- OPSI 1: Upload Berkas PDF/Excel -->
            <div v-if="headerState.tipe_sumber === 'file'" class="p-4 rounded-xl border border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-gray-900/30">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
                    <UIcon name="i-lucide-file-text" class="w-5 h-5" />
                  </div>
                  <div>
                    <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100">
                      Berkas Fisik Berita Acara (Scan PDF / Excel Rekapitulasi)
                    </h4>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                      Unggah 1 kali untuk seluruh usulan desa. Format PDF atau XLSX maks 15MB.
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <label class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-white/[0.1] text-xs font-medium text-gray-700 dark:text-gray-200 shadow-2xs hover:bg-gray-50 dark:hover:bg-gray-750 cursor-pointer transition min-h-[36px]">
                    <UIcon v-if="uploadingFile" name="i-lucide-loader-2" class="w-4 h-4 animate-spin text-primary-500" />
                    <UIcon v-else name="i-lucide-upload" class="w-4 h-4 text-gray-500" />
                    <span>{{ uploadedDocName ? 'Ganti File' : 'Pilih Berkas' }}</span>
                    <input type="file" accept=".pdf,.xlsx,.xls" class="hidden" @change="handleFileUpload" />
                  </label>
                </div>
              </div>

              <div v-if="uploadedDocName" class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.04] flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400">
                <div class="flex items-center gap-1.5 font-medium">
                  <UIcon name="i-lucide-check-circle-2" class="w-4 h-4" />
                  <span>{{ uploadedDocName }}</span>
                </div>
                <span class="text-[10px] text-gray-400">Siap disimpan</span>
              </div>
            </div>

            <!-- OPSI 2: Tautan Cloud (Google Drive / OneDrive / Storage Dinas) -->
            <div v-else class="p-4 rounded-xl border border-sky-200/70 dark:border-sky-900/40 bg-sky-50/30 dark:bg-sky-950/20 space-y-3">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                  <UIcon name="i-lucide-cloud" class="w-5 h-5" />
                </div>
                <div>
                  <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100">
                    Tautan Penyimpanan Cloud (Google Drive, OneDrive, Storage Dinas)
                  </h4>
                  <p class="text-[11px] text-gray-500 mt-0.5">
                    Cocok untuk folder dokumen telaah besar, foto survei lapangan puluhan desa, atau link dokumen dinas.
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                <div class="sm:col-span-2">
                  <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    URL Tautan Cloud <span class="text-red-500">*</span>
                  </label>
                  <UInput
                    v-model="headerState.url_link"
                    type="url"
                    placeholder="https://drive.google.com/drive/folders/... atau tautan dinas"
                    icon="i-lucide-link"
                    class="w-full text-xs"
                    size="sm"
                  />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Label Dokumen (Opsional)
                  </label>
                  <UInput
                    v-model="headerState.nama_dokumen_asli"
                    placeholder="Contoh: Folder Telaah DPU BM 2026"
                    class="w-full text-xs"
                    size="sm"
                  />
                </div>
              </div>

              <div v-if="headerState.url_link" class="flex flex-wrap items-center justify-between gap-2 text-[11px] pt-1">
                <a
                  :href="headerState.url_link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 text-sky-600 dark:text-sky-400 hover:underline font-medium"
                >
                  <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5" />
                  <span>Uji buka tautan cloud</span>
                </a>
                <span class="text-gray-400">Pastikan hak akses dapat dibuka oleh verifikator dan TAPD</span>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
              Catatan Umum Berita Acara (Opsional)
            </label>
            <textarea
              v-model="headerState.catatan_umum"
              rows="2"
              placeholder="Contoh: Hasil telaah lapangan triwulan I untuk kegiatan peningkatan jalan desa..."
              class="w-full text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-gray-900 p-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500"
            ></textarea>
          </div>

          <div class="flex justify-end pt-2">
            <UButton
              color="primary"
              variant="solid"
              icon="i-lucide-arrow-right"
              label="Lanjut: Pilih Usulan Desa"
              size="sm"
              @click="activeStep = 'pilih_desa'"
            />
          </div>
        </div>

        <!-- STEP 2: Pilih Usulan Desa -->
        <div v-show="activeStep === 'pilih_desa'" class="space-y-4">
          <!-- Filter Bar Pemilihan -->
          <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/50 dark:bg-gray-900/40 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
            <div>
              <label class="block font-medium text-gray-600 dark:text-gray-400 mb-1">Kecamatan</label>
              <USelectMenu
                v-model="filterKecamatan"
                :items="kecamatanOptions"
                value-key="value"
                label-key="label"
                class="w-full text-xs"
                size="xs"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-600 dark:text-gray-400 mb-1">Bidang BKKD</label>
              <USelectMenu
                v-model="filterBidang"
                :items="bidangOptions"
                value-key="value"
                label-key="label"
                class="w-full text-xs"
                size="xs"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-600 dark:text-gray-400 mb-1">Cari Desa / Usulan</label>
              <UInput
                v-model="searchProposal"
                placeholder="Ketik nama desa atau kegiatan..."
                icon="i-lucide-search"
                class="w-full text-xs"
                size="xs"
              />
            </div>
          </div>

          <!-- Selection Controls Bar -->
          <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
              <UButton
                color="neutral"
                variant="outline"
                size="xs"
                icon="i-lucide-check-square"
                label="Pilih Semua yang Tampil"
                @click="selectAllFiltered"
              />
              <UButton
                v-if="selectedProposalIds.size > 0"
                color="neutral"
                variant="ghost"
                size="xs"
                icon="i-lucide-x-square"
                label="Batalkan Pilihan"
                @click="clearAllSelected"
              />
            </div>
            <span class="font-semibold text-gray-700 dark:text-gray-300">
              {{ selectedProposalIds.size }} usulan dipilih dari {{ availableProposals.length }} usulan berstatus diajukan
            </span>
          </div>

          <!-- Tabel Seleksi Proposal -->
          <div class="max-h-[340px] overflow-y-auto rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40">
            <table class="w-full text-left border-collapse text-xs">
              <thead class="sticky top-0 bg-gray-50/90 dark:bg-gray-800/90 backdrop-blur-xs border-b border-gray-100 dark:border-white/[0.06] text-gray-600 dark:text-gray-400">
                <tr>
                  <th class="p-2.5 w-10 text-center">Pilih</th>
                  <th class="p-2.5 font-semibold">Wilayah Desa</th>
                  <th class="p-2.5 font-semibold">Kegiatan & Bidang</th>
                  <th class="p-2.5 font-semibold text-right">Nilai Usulan Desa</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                <tr v-if="loadingProposals">
                  <td colspan="4" class="p-6 text-center text-gray-500">
                    <UIcon name="i-lucide-loader-2" class="w-5 h-5 animate-spin mx-auto text-primary-500 mb-1" />
                    Memuat daftar usulan desa...
                  </td>
                </tr>
                <tr v-else-if="filteredProposals.length === 0">
                  <td colspan="4" class="p-6 text-center text-gray-500">
                    Tidak ditemukan usulan desa berstatus <code>diajukan_opd</code> untuk filter ini.
                  </td>
                </tr>
                <tr
                  v-for="p in filteredProposals"
                  :key="p.id"
                  class="hover:bg-primary-50/20 dark:hover:bg-primary-950/10 cursor-pointer transition"
                  :class="selectedProposalIds.has(p.id) ? 'bg-primary-50/30 dark:bg-primary-950/20' : ''"
                  @click="toggleSelectProposal(p)"
                >
                  <td class="p-2.5 text-center" @click.stop>
                    <input
                      type="checkbox"
                      :checked="selectedProposalIds.has(p.id)"
                      class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer"
                      @change="toggleSelectProposal(p)"
                    />
                  </td>
                  <td class="p-2.5">
                    <div class="font-bold text-gray-900 dark:text-gray-100">Desa {{ p.nama_desa || '-' }}</div>
                    <div class="text-[11px] text-gray-500">Kec. {{ p.nama_kecamatan || '-' }}</div>
                  </td>
                  <td class="p-2.5">
                    <div class="font-medium text-gray-800 dark:text-gray-200 line-clamp-1">{{ p.judul_kegiatan }}</div>
                    <div class="text-[11px] text-primary-600 dark:text-primary-400 font-mono">{{ p.nama_bidang || '-' }}</div>
                  </td>
                  <td class="p-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(p.nilai_usulan) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-between items-center pt-2">
            <UButton
              color="neutral"
              variant="outline"
              label="Kembali ke Dokumen"
              size="sm"
              @click="activeStep = 'dokumen'"
            />
            <UButton
              color="primary"
              variant="solid"
              icon="i-lucide-arrow-right"
              :label="`Lanjut: Isi Hasil (${selectedProposalIds.size} Desa)`"
              :disabled="selectedProposalIds.size === 0"
              size="sm"
              @click="activeStep = 'matriks'"
            />
          </div>
        </div>

        <!-- STEP 3: Matriks Isian Hasil & Rekomendasi -->
        <div v-show="activeStep === 'matriks'" class="space-y-4">
          <!-- Preset Buttons Bar -->
          <div class="p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-gray-900/40 flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="font-semibold text-gray-500 mr-1">Aksi Cepat Massal:</span>
              <UButton
                color="neutral"
                variant="outline"
                size="xs"
                label="Set Semua: Layak"
                @click="setAllStatus('layak')"
              />
              <UButton
                color="neutral"
                variant="outline"
                size="xs"
                label="Set Semua: Layak Bersyarat"
                @click="setAllStatus('layak_bersyarat')"
              />
              <UButton
                color="neutral"
                variant="outline"
                size="xs"
                label="Salin Usulan ke Rekomendasi"
                @click="copyAllProposedValues"
              />
            </div>

            <div class="text-xs font-mono">
              <span class="text-gray-500">Total Rekomendasi: </span>
              <strong class="text-emerald-600 dark:text-emerald-400 text-sm">
                {{ formatCurrency(totalNilaiRekomendasiSelected) }}
              </strong>
            </div>
          </div>

          <!-- Matriks Input Table -->
          <div class="max-h-[380px] overflow-y-auto rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40">
            <table class="w-full text-left border-collapse text-xs">
              <thead class="sticky top-0 bg-gray-50/90 dark:bg-gray-800/90 backdrop-blur-xs border-b border-gray-100 dark:border-white/[0.06] text-gray-600 dark:text-gray-400">
                <tr>
                  <th class="p-2.5 font-semibold w-8 text-center">No</th>
                  <th class="p-2.5 font-semibold w-48">Desa & Usulan</th>
                  <th class="p-2.5 font-semibold w-36">Hasil Telaah</th>
                  <th class="p-2.5 font-semibold w-44">Rekomendasi Anggaran (Rp)</th>
                  <th class="p-2.5 font-semibold">Catatan Lapangan & Teknis</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                <tr v-if="selectedProposalsList.length === 0">
                  <td colspan="5" class="p-6 text-center text-gray-500">
                    Belum ada usulan desa yang dipilih. Silakan kembali ke langkah sebelumnya.
                  </td>
                </tr>
                <tr
                  v-for="(p, idx) in selectedProposalsList"
                  :key="p.id"
                  class="align-top hover:bg-gray-50/30 dark:hover:bg-gray-800/30"
                >
                  <td class="p-2.5 text-center text-gray-400 font-mono">{{ idx + 1 }}</td>
                  <td class="p-2.5">
                    <div class="font-bold text-gray-900 dark:text-gray-100">Desa {{ p.nama_desa || '-' }}</div>
                    <div class="text-[11px] text-gray-500 line-clamp-1" :title="p.judul_kegiatan">{{ p.judul_kegiatan }}</div>
                    <div class="text-[11px] text-gray-400 font-mono mt-0.5">Usulan: {{ formatCurrency(p.nilai_usulan) }}</div>
                  </td>
                  <td class="p-2.5">
                    <USelectMenu
                      v-if="itemsState[p.id]"
                      v-model="itemsState[p.id].hasil"
                      :items="hasilOptions"
                      value-key="value"
                      label-key="label"
                      class="w-full text-xs"
                      size="xs"
                      @update:model-value="(val) => {
                        if (val === 'tidak_layak' || val === 'dikembalikan') {
                          itemsState[p.id].nilai_rekomendasi = 0;
                        } else if (!itemsState[p.id].nilai_rekomendasi) {
                          itemsState[p.id].nilai_rekomendasi = p.nilai_usulan ?? 0;
                        }
                      }"
                    />
                  </td>
                  <td class="p-2.5">
                    <div v-if="itemsState[p.id]">
                      <UInput
                        v-model.number="itemsState[p.id].nilai_rekomendasi"
                        type="number"
                        min="0"
                        placeholder="Rp 0"
                        :disabled="itemsState[p.id].hasil === 'tidak_layak' || itemsState[p.id].hasil === 'dikembalikan'"
                        class="w-full font-mono text-xs"
                        size="xs"
                      />
                      <span class="text-[10px] text-gray-400 font-mono block mt-0.5">
                        {{ formatCurrency(itemsState[p.id].nilai_rekomendasi) }}
                      </span>
                    </div>
                  </td>
                  <td class="p-2.5">
                    <UInput
                      v-if="itemsState[p.id]"
                      v-model="itemsState[p.id].catatan_opd"
                      placeholder="Catatan teknis / spesifikasi..."
                      class="w-full text-xs"
                      size="xs"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Bottom Action Controls -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-gray-100 dark:border-white/[0.06]">
            <UButton
              color="neutral"
              variant="outline"
              label="Kembali ke Pilih Desa"
              size="sm"
              @click="activeStep = 'pilih_desa'"
            />

            <div class="flex items-center gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                label="Batal"
                size="sm"
                @click="isOpen = false"
              />
              <UButton
                color="primary"
                variant="solid"
                icon="i-lucide-check-circle"
                :loading="submitting"
                :label="`Simpan Hasil Telaah (${selectedProposalIds.size} Desa)`"
                size="sm"
                @click="handleSaveKolektif"
              />
            </div>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

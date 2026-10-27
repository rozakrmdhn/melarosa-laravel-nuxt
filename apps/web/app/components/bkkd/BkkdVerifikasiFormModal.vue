<script setup lang="ts">
import type { BkkdProposal, BkkdVerifikasi, BkkdVerifikasiDokumen, RefBkkdBidang, RefOpd } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';

interface Props {
  open: boolean;
  proposal?: BkkdProposal | null;
  verifikasi?: BkkdVerifikasi | null;
  isEditing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  proposal: null,
  verifikasi: null,
  isEditing: false,
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

const submitting = ref(false);
const uploadingFile = ref(false);
const opdList = ref<RefOpd[]>([]);
const bidangList = ref<RefBkkdBidang[]>([]);
const loadingOpd = ref(false);

const useExistingDokumen = ref(false);
const selectedDokumenId = ref<string>('');
const existingDokumenList = ref<BkkdVerifikasiDokumen[]>([]);
const loadingDokumenList = ref(false);

const selectedFile = ref<File | null>(null);
const uploadedDocPath = ref<string | null>(null);
const uploadedDocName = ref<string | null>(null);

const tipeSumberDokumen = ref<'file' | 'link'>('file');
const urlLinkDokumen = ref('');
const labelLinkDokumen = ref('');

const formState = reactive({
  id_opd: '',
  nomor_surat_hasil: '',
  tanggal_verifikasi: new Date().toISOString().split('T')[0],
  hasil: 'layak' as 'layak' | 'layak_bersyarat' | 'tidak_layak' | 'dikembalikan',
  rekomendasi_anggaran: 0,
  volume_rekomendasi: null as number | null,
  panjang_rekomendasi: null as number | null,
  lebar_rekomendasi: null as number | null,
  tinggi_rekomendasi: null as number | null,
  catatan_opd: '',
  alasan_perubahan: '',
});

const errors = reactive<Record<string, string>>({});

const hasilOptions = [
  { label: 'Layak (Memenuhi seluruh kriteria teknis)', value: 'layak' },
  { label: 'Layak Bersyarat (Memerlukan penyempurnaan dokumen/lapangan)', value: 'layak_bersyarat' },
  { label: 'Tidak Layak (Tidak sesuai prioritas/kriteria teknis)', value: 'tidak_layak' },
  { label: 'Dikembalikan (Berkas tidak lengkap untuk diverifikasi)', value: 'dikembalikan' },
];

const activeProposal = computed(() => props.proposal || props.verifikasi?.proposal || null);

const targetBidang = computed(() => {
  const idBidang = activeProposal.value?.id_bidang || (activeProposal.value as any)?.bidang?.id;
  if (!idBidang) return null;
  return bidangList.value.find((b) => b.id === idBidang) || null;
});

const targetBidangName = computed(() => {
  return (
    activeProposal.value?.nama_bidang ||
    activeProposal.value?.bidang?.nama ||
    targetBidang.value?.nama ||
    ''
  );
});

const targetOpdId = computed(() => {
  return (
    activeProposal.value?.id_opd_default ||
    activeProposal.value?.bidang?.id_opd_default ||
    targetBidang.value?.id_opd_default ||
    targetBidang.value?.opd_default?.id ||
    null
  );
});

const opdSelectItems = computed(() =>
  opdList.value
    .map((o) => {
      const isTarget = !!targetOpdId.value && o.id === targetOpdId.value;
      return {
        label: isTarget ? `${o.nama} (${o.kode}) [Sesuai Bidang Usulan]` : `${o.nama} (${o.kode})`,
        value: o.id,
        isTarget,
      };
    })
    .sort((a, b) => {
      if (a.isTarget && !b.isTarget) return -1;
      if (!a.isTarget && b.isTarget) return 1;
      return 0;
    })
);

async function loadReferences() {
  loadingOpd.value = true;
  try {
    const [opdRes, bidangRes] = await Promise.all([
      api.fetchRefOpd(),
      api.fetchRefBidang(),
    ]);
    opdList.value = opdRes.data || [];
    bidangList.value = bidangRes.data || [];
  } catch (err) {
    console.error('Gagal memuat referensi OPD/Bidang:', err);
  } finally {
    loadingOpd.value = false;
  }
}

async function loadExistingDokumen() {
  loadingDokumenList.value = true;
  try {
    const res = await api.fetchDokumenVerifikasiList({
      tahun_anggaran: activeProposal.value?.tahun_anggaran,
    });
    existingDokumenList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat dokumen berita acara:', err);
  } finally {
    loadingDokumenList.value = false;
  }
}

function onSelectDokumen(docId: string) {
  selectedDokumenId.value = docId;
  const doc = existingDokumenList.value.find((d) => d.id === docId);
  if (doc) {
    formState.id_opd = doc.id_opd;
    formState.nomor_surat_hasil = doc.nomor_surat_hasil;
    formState.tanggal_verifikasi = doc.tanggal_surat_hasil || doc.tanggal_diterima || formState.tanggal_verifikasi;
    uploadedDocName.value = doc.nama_dokumen_asli || null;
    uploadedDocPath.value = null;
  }
}

function onToggleUseExisting() {
  if (useExistingDokumen.value) {
    loadExistingDokumen();
  }
}

watch(
  () => props.open,
  async (newVal) => {
    if (newVal) {
      Object.keys(errors).forEach((k) => delete errors[k]);
      selectedFile.value = null;
      uploadedDocPath.value = null;
      uploadedDocName.value = null;
      useExistingDokumen.value = !!(props.verifikasi?.id_dokumen);
      selectedDokumenId.value = props.verifikasi?.id_dokumen || '';
      existingDokumenList.value = [];

      await loadReferences();

      if (props.isEditing && props.verifikasi) {
        formState.id_opd = props.verifikasi.id_opd;
        formState.nomor_surat_hasil = props.verifikasi.nomor_surat_hasil;
        formState.tanggal_verifikasi = props.verifikasi.tanggal_surat_hasil || props.verifikasi.tanggal_verifikasi || new Date().toISOString().split('T')[0];
        formState.hasil = props.verifikasi.hasil;
        formState.rekomendasi_anggaran = props.verifikasi.nilai_rekomendasi ?? props.verifikasi.rekomendasi_anggaran ?? 0;
        formState.volume_rekomendasi = props.verifikasi.volume_rekomendasi ?? activeProposal.value?.volume ?? null;
        formState.panjang_rekomendasi = props.verifikasi.panjang_rekomendasi ?? activeProposal.value?.panjang ?? null;
        formState.lebar_rekomendasi = props.verifikasi.lebar_rekomendasi ?? activeProposal.value?.lebar ?? null;
        formState.tinggi_rekomendasi = props.verifikasi.tinggi_rekomendasi ?? activeProposal.value?.tinggi ?? null;
        formState.catatan_opd = props.verifikasi.catatan_opd || '';
        formState.alasan_perubahan = '';
        tipeSumberDokumen.value = (props.verifikasi.tipe_sumber === 'link' || (!props.verifikasi.has_dokumen && !!props.verifikasi.url_link)) ? 'link' : 'file';
        urlLinkDokumen.value = props.verifikasi.url_link || '';
        labelLinkDokumen.value = (props.verifikasi as any).nama_dokumen_asli || props.verifikasi.dokumen_hasil_nama || '';
        uploadedDocName.value = (props.verifikasi as any).nama_dokumen_asli || props.verifikasi.dokumen_hasil_nama || null;
      } else {
        // Otomatis tentukan OPD pemeriksa berdasarkan id_bidang proposal usulan
        formState.id_opd = targetOpdId.value || (opdList.value[0]?.id ?? '');
        formState.nomor_surat_hasil = '';
        formState.tanggal_verifikasi = new Date().toISOString().split('T')[0];
        formState.hasil = 'layak';
        formState.rekomendasi_anggaran = activeProposal.value?.nilai_usulan || 0;
        formState.volume_rekomendasi = activeProposal.value?.volume ?? null;
        formState.panjang_rekomendasi = activeProposal.value?.panjang ?? null;
        formState.lebar_rekomendasi = activeProposal.value?.lebar ?? null;
        formState.tinggi_rekomendasi = activeProposal.value?.tinggi ?? null;
        formState.catatan_opd = '';
        formState.alasan_perubahan = '';
        tipeSumberDokumen.value = 'file';
        urlLinkDokumen.value = '';
        labelLinkDokumen.value = '';
      }
    }
  }
);

function formatCurrency(val: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

async function handleFileChange(e: Event) {
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0) return;

  const file = target.files[0];
  selectedFile.value = file;
  uploadedDocName.value = file.name;

  uploadingFile.value = true;
  try {
    const res = await api.uploadTemporaryDocument(file, 'bkkd-verifikasi');
    if (res.data?.path) {
      uploadedDocPath.value = res.data.path;
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Dokumen Berhasil Diunggah',
        description: `File '${file.name}' siap disimpan bersama hasil telaah.`,
        color: 'success',
      });
    }
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Mengunggah Berkas',
      description: err?.data?.message || 'Pastikan file berekstensi PDF/Excel dengan ukuran maks 10MB.',
      color: 'error',
    });
    selectedFile.value = null;
    uploadedDocName.value = null;
    uploadedDocPath.value = null;
  } finally {
    uploadingFile.value = false;
  }
}

async function handleSubmit() {
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!formState.id_opd) {
    errors.id_opd = 'OPD teknis pemeriksa wajib dipilih.';
  }
  if (!formState.nomor_surat_hasil.trim()) {
    errors.nomor_surat_hasil = 'Nomor surat hasil telaah/verifikasi wajib diisi.';
  }
  if (!formState.tanggal_verifikasi) {
    errors.tanggal_verifikasi = 'Tanggal verifikasi wajib diisi.';
  }
  if (['layak', 'layak_bersyarat'].includes(formState.hasil) && formState.rekomendasi_anggaran <= 0) {
    errors.rekomendasi_anggaran = 'Nilai rekomendasi anggaran wajib diisi untuk hasil telaah layak.';
  }
  if (props.isEditing && !formState.alasan_perubahan.trim()) {
    errors.alasan_perubahan = 'Alasan koreksi verifikasi wajib diisi untuk rekaman audit.';
  }

  if (Object.keys(errors).length > 0) {
    toast.add({
      icon: 'i-lucide-alert-triangle',
      title: 'Validasi Belum Lengkap',
      description: 'Mohon periksa kolom yang bertanda merah.',
      color: 'error',
    });
    return;
  }

  submitting.value = true;
  try {
    const payload: Record<string, any> = {
      id_opd: formState.id_opd,
      nomor_surat_hasil: formState.nomor_surat_hasil.trim(),
      tanggal_surat_hasil: formState.tanggal_verifikasi,
      tanggal_verifikasi: formState.tanggal_verifikasi,
      tanggal_diterima: formState.tanggal_verifikasi,
      hasil: formState.hasil,
      nilai_rekomendasi: ['layak', 'layak_bersyarat'].includes(formState.hasil)
        ? Number(formState.rekomendasi_anggaran)
        : null,
      rekomendasi_anggaran: ['layak', 'layak_bersyarat'].includes(formState.hasil)
        ? Number(formState.rekomendasi_anggaran)
        : null,
      volume_rekomendasi: ['layak', 'layak_bersyarat'].includes(formState.hasil) && formState.volume_rekomendasi !== null && formState.volume_rekomendasi !== undefined && (formState.volume_rekomendasi as any) !== ''
        ? Number(formState.volume_rekomendasi)
        : null,
      panjang_rekomendasi: ['layak', 'layak_bersyarat'].includes(formState.hasil) && formState.panjang_rekomendasi !== null && formState.panjang_rekomendasi !== undefined && (formState.panjang_rekomendasi as any) !== ''
        ? Number(formState.panjang_rekomendasi)
        : null,
      lebar_rekomendasi: ['layak', 'layak_bersyarat'].includes(formState.hasil) && formState.lebar_rekomendasi !== null && formState.lebar_rekomendasi !== undefined && (formState.lebar_rekomendasi as any) !== ''
        ? Number(formState.lebar_rekomendasi)
        : null,
      tinggi_rekomendasi: ['layak', 'layak_bersyarat'].includes(formState.hasil) && formState.tinggi_rekomendasi !== null && formState.tinggi_rekomendasi !== undefined && (formState.tinggi_rekomendasi as any) !== ''
        ? Number(formState.tinggi_rekomendasi)
        : null,
      catatan_opd: formState.catatan_opd?.trim() || null,
    };

    if (useExistingDokumen.value && selectedDokumenId.value) {
      payload.id_dokumen = selectedDokumenId.value;
    } else {
      payload.tipe_sumber = tipeSumberDokumen.value;
      if (tipeSumberDokumen.value === 'link') {
        if (urlLinkDokumen.value.trim()) {
          try {
            new URL(urlLinkDokumen.value.trim());
          } catch {
            toast.add({
              icon: 'i-lucide-alert-triangle',
              title: 'Validasi',
              description: 'Format URL tautan cloud dokumen tidak valid. Pastikan menyertakan http:// atau https://',
              color: 'error',
            });
            submitting.value = false;
            return;
          }
        }
        payload.url_link = urlLinkDokumen.value.trim() || null;
        payload.nama_dokumen_asli = labelLinkDokumen.value.trim() || 'Tautan Dokumen Verifikasi';
      } else if (uploadedDocPath.value) {
        payload.path_dokumen = uploadedDocPath.value;
        payload.nama_dokumen_asli = uploadedDocName.value;
      }
    }

    if (props.isEditing && props.verifikasi) {
      payload.versi = props.verifikasi.versi;
      payload.alasan_perubahan = formState.alasan_perubahan.trim();
      await api.updateVerifikasi(props.verifikasi.id, payload);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Verifikasi Berhasil Dikoreksi',
        description: 'Catatan koreksi verifikasi tersimpan dalam riwayat audit.',
        color: 'success',
      });
    } else if (activeProposal.value?.id) {
      await api.submitVerifikasi(activeProposal.value.id, payload);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Hasil Telaah OPD Tersimpan',
        description: `Hasil telaah usulan '${activeProposal.value.nomor_registrasi}' berhasil disimpan.`,
        color: 'success',
      });
    }

    emit('success');
    isOpen.value = false;
  } catch (err: any) {
    if (err?.statusCode === 409 || err?.status === 409) {
      toast.add({
        icon: 'i-lucide-alert-octagon',
        title: 'Konflik Data (409 Conflict)',
        description: err?.data?.message || 'Data verifikasi telah diubah pengguna lain.',
        color: 'error',
      });
      return;
    }

    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menyimpan Verifikasi',
      description: err?.data?.message || 'Terjadi kesalahan sistem.',
      color: 'error',
    });
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    :title="isEditing ? 'Koreksi Hasil Verifikasi OPD' : 'Pencatatan Hasil Verifikasi OPD Teknis'"
    :description="activeProposal ? `Usulan: ${activeProposal.nomor_registrasi} - ${activeProposal.judul_kegiatan}` : 'Formulir verifikasi teknis proposal BKKD.'"
    :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <form class="space-y-4 py-1" @submit.prevent="handleSubmit">
        <!-- Informasi Usulan Desa -->
        <div v-if="activeProposal" class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/70 dark:bg-gray-900/40 text-xs space-y-2">
          <div class="flex justify-between items-start gap-2">
            <span class="text-gray-500">Bidang Usulan:</span>
            <span class="font-semibold text-primary-600 dark:text-primary-400 text-right">
              {{ targetBidangName || '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Desa & Kecamatan:</span>
            <span class="font-medium text-gray-900 dark:text-gray-100">
              Desa {{ activeProposal.nama_desa || '-' }}, Kec. {{ activeProposal.nama_kecamatan || '-' }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-500">Nilai Usulan Desa:</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono text-sm">
              {{ activeProposal.nilai_usulan ? formatCurrency(activeProposal.nilai_usulan) : '-' }}
            </span>
          </div>
          <div v-if="(activeProposal.volume ?? activeProposal.volume_target) !== null && (activeProposal.volume ?? activeProposal.volume_target) !== undefined" class="flex justify-between items-center">
            <span class="text-gray-500">Target Volume Usulan:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200 font-mono">
              {{ activeProposal.volume ?? activeProposal.volume_target }} {{ activeProposal.satuan || activeProposal.satuan_target || '' }}
            </span>
          </div>
          <div v-if="activeProposal.panjang || activeProposal.lebar || activeProposal.tinggi" class="flex justify-between items-center">
            <span class="text-gray-500">Dimensi Usulan (P×L×T):</span>
            <span class="font-medium text-gray-800 dark:text-gray-200 font-mono">
              {{ activeProposal.panjang ? `${activeProposal.panjang}m` : '-' }} × {{ activeProposal.lebar ? `${activeProposal.lebar}m` : '-' }} × {{ activeProposal.tinggi ? `${activeProposal.tinggi}m` : '-' }}
            </span>
          </div>
          <div v-if="activeProposal.lokasi_kegiatan" class="flex justify-between items-start gap-2">
            <span class="text-gray-500 shrink-0">Lokasi:</span>
            <span class="text-gray-700 dark:text-gray-300 text-right">{{ activeProposal.lokasi_kegiatan }}</span>
          </div>
        </div>

        <!-- Opsi Hubungkan Dokumen Berita Acara Kolektif -->
        <div class="p-3 rounded-xl border border-primary-200/50 dark:border-primary-900/40 bg-primary-50/20 dark:bg-primary-950/20 text-xs flex items-center justify-between gap-3">
          <div>
            <span class="font-semibold text-gray-900 dark:text-gray-100 block">Hubungkan Berita Acara Kolektif</span>
            <span class="text-[11px] text-gray-500">Pilih jika usulan desa ini termasuk dalam dokumen Berita Acara yang sudah pernah diunggah.</span>
          </div>
          <label class="flex items-center gap-2 cursor-pointer font-medium text-primary-600 dark:text-primary-400">
            <input
              v-model="useExistingDokumen"
              type="checkbox"
              class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer"
              @change="onToggleUseExisting"
            />
            <span>Gunakan Dokumen Ada</span>
          </label>
        </div>

        <div v-if="useExistingDokumen" class="space-y-1.5 p-3 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/60 dark:bg-gray-900/40">
          <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Pilih Berita Acara OPD Terdaftar <span class="text-red-500">*</span>
          </label>
          <USelectMenu
            v-model="selectedDokumenId"
            :items="existingDokumenList.map((d) => ({
              label: `${d.nomor_surat_hasil} - ${d.opd?.nama || 'OPD'} (${d.total_desa || 0} Desa)`,
              value: d.id,
            }))"
            value-key="value"
            label-key="label"
            :loading="loadingDokumenList"
            placeholder="Pilih nomor surat berita acara..."
            class="w-full text-xs"
            size="sm"
            @update:model-value="onSelectDokumen"
          />
          <p v-if="uploadedDocName" class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-medium mt-1">
            <UIcon name="i-lucide-check-circle" class="w-3.5 h-3.5" />
            <span>Dokumen terpilih: {{ uploadedDocName }} (tidak perlu upload ulang file)</span>
          </p>
        </div>

        <!-- OPD & Nomor Surat -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <UFormField label="OPD Teknis Pemeriksa" required :error="errors.id_opd" size="sm">
            <USelectMenu
              v-model="formState.id_opd"
              :items="opdSelectItems"
              value-key="value"
              label-key="label"
              class="w-full"
              :loading="loadingOpd"
            />
            <template #help>
              <div v-if="targetBidangName" class="mt-1 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                <UIcon name="i-lucide-check-circle" class="w-3.5 h-3.5 shrink-0" />
                <span>Disesuaikan otomatis berdasarkan bidang: {{ targetBidangName }}</span>
              </div>
            </template>
          </UFormField>

          <UFormField label="Tanggal Verifikasi" required :error="errors.tanggal_verifikasi" size="sm">
            <UInput
              v-model="formState.tanggal_verifikasi"
              type="date"
              class="w-full text-xs"
            />
          </UFormField>
        </div>

        <UFormField label="Nomor Surat Berita Acara / Rekomendasi OPD" required :error="errors.nomor_surat_hasil" size="sm">
          <UInput
            v-model="formState.nomor_surat_hasil"
            placeholder="Contoh: 600/123/412.201/2026"
            class="w-full font-mono text-xs"
          />
        </UFormField>

        <!-- Hasil Telaah & Rekomendasi Anggaran -->
        <div class="space-y-3 p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.04] bg-gray-50/30 dark:bg-gray-900/20">
          <UFormField label="Kesimpulan Hasil Verifikasi" required size="sm">
            <USelectMenu
              v-model="formState.hasil"
              :items="hasilOptions"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </UFormField>

          <div v-if="['layak', 'layak_bersyarat'].includes(formState.hasil)" class="space-y-3 pt-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <UFormField label="Rekomendasi Anggaran OPD Teknis (Rp)" required :error="errors.rekomendasi_anggaran" size="sm">
                <UInput
                  v-model.number="formState.rekomendasi_anggaran"
                  type="number"
                  min="0"
                  step="100000"
                  class="w-full font-mono"
                />
              </UFormField>

              <UFormField
                label="Volume Rekomendasi OPD"
                :help="(activeProposal?.satuan || activeProposal?.satuan_target) ? `Satuan usulan: ${activeProposal.satuan || activeProposal.satuan_target}` : undefined"
                :error="errors.volume_rekomendasi"
                size="sm"
              >
                <div class="flex items-center gap-2">
                  <UInput
                    v-model.number="formState.volume_rekomendasi"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Volume..."
                    class="w-full font-mono"
                  />
                  <span v-if="activeProposal?.satuan || activeProposal?.satuan_target" class="text-xs font-semibold text-gray-600 dark:text-gray-300 whitespace-nowrap px-2.5 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-md border border-gray-200 dark:border-white/[0.08]">
                    {{ activeProposal.satuan || activeProposal.satuan_target }}
                  </span>
                </div>
              </UFormField>
            </div>

            <!-- Dimensi Fisik Rekomendasi OPD -->
            <div class="grid grid-cols-3 gap-2">
              <UFormField label="Panjang Rekom. (m)" size="sm">
                <UInput
                  v-model.number="formState.panjang_rekomendasi"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  class="w-full font-mono text-xs"
                />
              </UFormField>
              <UFormField label="Lebar Rekom. (m)" size="sm">
                <UInput
                  v-model.number="formState.lebar_rekomendasi"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  class="w-full font-mono text-xs"
                />
              </UFormField>
              <UFormField label="Tinggi Rekom. (m)" size="sm">
                <UInput
                  v-model.number="formState.tinggi_rekomendasi"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  class="w-full font-mono text-xs"
                />
              </UFormField>
            </div>

            <div v-if="formState.rekomendasi_anggaran > 0" class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
              Terbilang: {{ formatCurrency(formState.rekomendasi_anggaran) }}
            </div>
          </div>
        </div>

        <!-- Catatan Teknis OPD -->
        <UFormField label="Catatan Teknis / Syarat Realisasi OPD" size="sm">
          <UTextarea
            v-model="formState.catatan_opd"
            :rows="3"
            placeholder="Tuliskan catatan teknis hasil peninjauan berkas atau lapangan..."
            class="w-full"
          />
        </UFormField>

        <!-- Lampiran Dokumen Berita Acara / Telaah -->
        <div v-if="!useExistingDokumen" class="space-y-3 p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-gray-900/30">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
              Dokumen Telaah Teknis OPD
            </label>
            <div class="inline-flex p-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-white/[0.08] self-start sm:self-auto">
              <button
                type="button"
                class="px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5 min-h-[32px]"
                :class="tipeSumberDokumen === 'file' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-2xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
                @click="tipeSumberDokumen = 'file'"
              >
                <UIcon name="i-lucide-upload" class="w-3.5 h-3.5" />
                <span>Upload Berkas</span>
              </button>
              <button
                type="button"
                class="px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5 min-h-[32px]"
                :class="tipeSumberDokumen === 'link' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-2xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-200'"
                @click="tipeSumberDokumen = 'link'"
              >
                <UIcon name="i-lucide-cloud" class="w-3.5 h-3.5" />
                <span>Tautan Cloud</span>
              </button>
            </div>
          </div>

          <!-- Opsi File Upload -->
          <div v-if="tipeSumberDokumen === 'file'" class="space-y-2">
            <p class="text-[11px] text-gray-500">
              Format berkas PDF, Excel, atau JPG/PNG, ukuran maksimum 10MB.
            </p>
            <div class="flex flex-wrap items-center gap-3">
              <input
                type="file"
                accept=".pdf,.xlsx,.xls,.png,.jpg"
                class="text-xs file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/40 dark:file:text-primary-300 cursor-pointer"
                @change="handleFileChange"
              />
              <span v-if="uploadingFile" class="text-xs text-gray-500 animate-pulse">Mengunggah...</span>
              <span v-else-if="uploadedDocName" class="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-medium">
                <UIcon name="i-lucide-file-check" class="w-4 h-4" />
                <span>{{ uploadedDocName }}</span>
              </span>
            </div>
          </div>

          <!-- Opsi Cloud Link -->
          <div v-else class="space-y-2.5">
            <p class="text-[11px] text-gray-500">
              Gunakan tautan penyimpanan cloud (Google Drive, OneDrive, atau server dinas).
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
              <div class="sm:col-span-2">
                <UInput
                  v-model="urlLinkDokumen"
                  type="url"
                  placeholder="https://drive.google.com/... atau tautan dokumen dinas"
                  icon="i-lucide-link"
                  class="w-full text-xs"
                  size="sm"
                />
              </div>
              <div>
                <UInput
                  v-model="labelLinkDokumen"
                  placeholder="Keterangan dokumen (opsional)"
                  class="w-full text-xs"
                  size="sm"
                />
              </div>
            </div>
            <div v-if="urlLinkDokumen" class="flex items-center gap-2 text-[11px]">
              <a
                :href="urlLinkDokumen"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-sky-600 dark:text-sky-400 hover:underline font-medium"
              >
                <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5" />
                <span>Uji buka tautan</span>
              </a>
            </div>
          </div>
        </div>

        <!-- Alasan Koreksi jika Mode Edit -->
        <div v-if="isEditing" class="p-3 rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/20 space-y-2">
          <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-semibold text-xs">
            <UIcon name="i-lucide-alert-triangle" class="w-4 h-4" />
            <span>Koreksi Hasil Verifikasi (Audit Trail)</span>
          </div>
          <UFormField label="Alasan Koreksi Verifikasi" required :error="errors.alasan_perubahan" size="sm">
            <UTextarea
              v-model="formState.alasan_perubahan"
              :rows="2"
              placeholder="Contoh: Ralat rekomendasi nilai anggaran berdasarkan klarifikasi surat susulan OPD..."
              class="w-full"
            />
          </UFormField>
        </div>
      </form>
    </template>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full">
        <UButton
          color="neutral"
          variant="ghost"
          label="Batal"
          :disabled="submitting || uploadingFile"
          @click="isOpen = false"
        />
        <UButton
          color="primary"
          :label="isEditing ? 'Simpan Koreksi' : 'Simpan Verifikasi'"
          :loading="submitting || uploadingFile"
          icon="i-lucide-clipboard-check"
          @click="handleSubmit"
        />
      </div>
    </template>
  </UModal>
</template>

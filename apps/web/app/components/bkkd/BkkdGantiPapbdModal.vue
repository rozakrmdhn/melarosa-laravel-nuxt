<script setup lang="ts">
import type { BkkdProposal, RefBkkdBidang } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';

interface Props {
  open: boolean;
  proposal?: BkkdProposal | null;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  proposal: null,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'success', newProposal?: BkkdProposal): void;
}>();

const toast = useToast();
const api = useBkkdApi();

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit('update:open', val),
});

const submitting = ref(false);
const loadingBidang = ref(false);
const bidangList = ref<RefBkkdBidang[]>([]);

const formState = reactive({
  alasan_pergantian_papbd: '',
  id_bidang: '',
  judul_kegiatan: '',
  uraian_kegiatan: '',
  lokasi_kegiatan: '',
  panjang: null as number | null,
  lebar: null as number | null,
  tinggi: null as number | null,
  volume: null as number | null,
  satuan: 'meter',
  nilai_usulan: null as number | null,
  nomor_surat_desa: '',
  tanggal_surat_desa: new Date().toISOString().split('T')[0],
});

const errors = reactive<Record<string, string>>({});

async function loadBidang() {
  if (bidangList.value.length > 0) return;
  loadingBidang.value = true;
  try {
    const res = await api.fetchRefBidang();
    bidangList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat referensi bidang:', err);
  } finally {
    loadingBidang.value = false;
  }
}

watch(
  () => props.open,
  (newVal) => {
    if (newVal) {
      loadBidang();
      Object.keys(errors).forEach((k) => delete errors[k]);
      if (props.proposal) {
        formState.id_bidang = props.proposal.id_bidang || '';
        formState.alasan_pergantian_papbd = '';
        formState.judul_kegiatan = '';
        formState.uraian_kegiatan = '';
        formState.lokasi_kegiatan = props.proposal.lokasi_kegiatan || '';
        formState.panjang = null;
        formState.lebar = null;
        formState.tinggi = null;
        formState.volume = null;
        formState.satuan = props.proposal.satuan || 'meter';
        formState.nilai_usulan = props.proposal.nilai_disetujui_tapd || props.proposal.nilai_usulan || null;
        formState.nomor_surat_desa = '';
        formState.tanggal_surat_desa = new Date().toISOString().split('T')[0];
      }
    }
  }
);

function formatCurrency(val?: number | null) {
  if (val === null || val === undefined) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

async function handleConfirm() {
  if (!props.proposal) return;

  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!formState.alasan_pergantian_papbd.trim()) {
    errors.alasan_pergantian_papbd = 'Alasan resmi pergantian usulan di P-APBD wajib diisi.';
  }
  if (!formState.judul_kegiatan.trim()) {
    errors.judul_kegiatan = 'Judul kegiatan usulan baru wajib diisi.';
  }
  if (!formState.id_bidang) {
    errors.id_bidang = 'Bidang usulan baru wajib dipilih.';
  }
  if (!formState.nomor_surat_desa.trim()) {
    errors.nomor_surat_desa = 'Nomor surat permohonan desa wajib diisi.';
  }
  if (!formState.tanggal_surat_desa) {
    errors.tanggal_surat_desa = 'Tanggal surat permohonan desa wajib diisi.';
  }

  if (Object.keys(errors).length > 0) return;

  submitting.value = true;
  try {
    const res = await api.gantiProposalPapbd(props.proposal.id, {
      alasan_pergantian_papbd: formState.alasan_pergantian_papbd.trim(),
      id_bidang: formState.id_bidang,
      judul_kegiatan: formState.judul_kegiatan.trim(),
      uraian_kegiatan: formState.uraian_kegiatan.trim() || null,
      lokasi_kegiatan: formState.lokasi_kegiatan.trim() || null,
      panjang: formState.panjang,
      lebar: formState.lebar,
      tinggi: formState.tinggi,
      volume: formState.volume,
      satuan: formState.satuan,
      nilai_usulan: formState.nilai_usulan,
      nomor_surat_desa: formState.nomor_surat_desa.trim(),
      tanggal_surat_desa: formState.tanggal_surat_desa,
    });

    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Pergantian Usulan Berhasil',
      description: res.message || 'Usulan P-APBD baru telah dibuat dan tersandingkan dengan usulan RAPBD Induk.',
      color: 'success',
    });

    isOpen.value = false;
    emit('success', res.data);
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memproses Pergantian',
      description: err?.data?.message || 'Terjadi kesalahan sistem saat memproses pergantian usulan.',
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
    title="Pengajuan Pergantian Usulan P-APBD"
    description="Formulir resmi penggantian usulan BKKD yang telah ditetapkan pada RAPBD dengan kegiatan baru di Perubahan APBD."
    :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <div v-if="proposal" class="space-y-4 py-1 text-xs">
        <!-- Kartu Usulan Awal RAPBD Induk -->
        <div class="p-3.5 rounded-xl border border-amber-200/70 dark:border-amber-900/40 bg-amber-50/50 dark:bg-amber-950/20 space-y-2">
          <div class="flex items-center justify-between pb-1.5 border-b border-amber-200/40 dark:border-amber-900/30">
            <span class="text-[11px] font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
              <UIcon name="i-lucide-file-text" class="w-3.5 h-3.5" />
              Usulan Semula yang Ditetapkan pada RAPBD:
            </span>
            <span class="font-mono font-semibold text-amber-700 dark:text-amber-400">
              {{ proposal.nomor_registrasi }}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
            <div>
              <span class="text-gray-500 block">Kegiatan Awal:</span>
              <span class="font-medium text-gray-900 dark:text-gray-100">{{ proposal.judul_kegiatan }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Pagu Ditetapkan TAPD:</span>
              <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">
                {{ formatCurrency(proposal.nilai_disetujui_tapd || proposal.nilai_usulan) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Alasan Pergantian (Wajib) -->
        <div class="space-y-1.5">
          <label class="block font-semibold text-gray-800 dark:text-gray-200">
            Alasan Resmi Pergantian Usulan di P-APBD <span class="text-rose-500">*</span>
          </label>
          <textarea
            v-model="formState.alasan_pergantian_papbd"
            rows="3"
            placeholder="Contoh: Lokasi jalan poros dusun krajan terdampak sengketa batas tanah, dialihkan untuk pembangunan Jembatan Usaha Tani Dusun Sambong sesuai hasil Musdes Perubahan."
            class="w-full text-xs rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 focus:outline-none focus:ring-1 focus:ring-primary-500"
          />
          <p v-if="errors.alasan_pergantian_papbd" class="text-[11px] text-rose-500 font-medium">
            {{ errors.alasan_pergantian_papbd }}
          </p>
        </div>

        <!-- Spesifikasi Usulan Baru P-APBD -->
        <div class="space-y-3 pt-2 border-t border-gray-100 dark:border-white/[0.06]">
          <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
            <UIcon name="i-lucide-sparkles" class="w-4 h-4 text-primary-500" />
            Spesifikasi Usulan Kegiatan Baru (P-APBD):
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-medium text-gray-700 dark:text-gray-300">Bidang BKKD Baru <span class="text-rose-500">*</span></label>
              <select
                v-model="formState.id_bidang"
                class="w-full text-xs py-2 px-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500"
              >
                <option value="" disabled>Pilih Bidang</option>
                <option v-for="b in bidangList" :key="b.id" :value="b.id">
                  {{ b.nama }}
                </option>
              </select>
              <p v-if="errors.id_bidang" class="text-[11px] text-rose-500">{{ errors.id_bidang }}</p>
            </div>

            <div class="space-y-1">
              <label class="block font-medium text-gray-700 dark:text-gray-300">Estimasi Nilai Usulan (Rp)</label>
              <input
                v-model.number="formState.nilai_usulan"
                type="number"
                placeholder="Contoh: 350000000"
                class="w-full text-xs py-2 px-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono focus:ring-1 focus:ring-primary-500"
              >
            </div>
          </div>

          <div class="space-y-1">
            <label class="block font-medium text-gray-700 dark:text-gray-300">Judul Kegiatan Usulan Baru <span class="text-rose-500">*</span></label>
            <input
              v-model="formState.judul_kegiatan"
              type="text"
              placeholder="Contoh: Pembangunan Jembatan Usaha Tani Dusun Sambong"
              class="w-full text-xs py-2 px-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500"
            >
            <p v-if="errors.judul_kegiatan" class="text-[11px] text-rose-500">{{ errors.judul_kegiatan }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-medium text-gray-700 dark:text-gray-300">Lokasi Pelaksanaan Baru</label>
              <input
                v-model="formState.lokasi_kegiatan"
                type="text"
                placeholder="Contoh: Dusun Sambong RT 03 RW 01"
                class="w-full text-xs py-2 px-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500"
              >
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div class="space-y-1">
                <label class="block text-[10px] text-gray-500">Panjang (m)</label>
                <input
                  v-model.number="formState.panjang"
                  type="number"
                  step="0.1"
                  class="w-full text-xs py-2 px-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono"
                >
              </div>
              <div class="space-y-1">
                <label class="block text-[10px] text-gray-500">Lebar (m)</label>
                <input
                  v-model.number="formState.lebar"
                  type="number"
                  step="0.1"
                  class="w-full text-xs py-2 px-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono"
                >
              </div>
              <div class="space-y-1">
                <label class="block text-[10px] text-gray-500">Tinggi (m)</label>
                <input
                  v-model.number="formState.tinggi"
                  type="number"
                  step="0.1"
                  class="w-full text-xs py-2 px-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono"
                >
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div class="space-y-1">
              <label class="block font-medium text-gray-700 dark:text-gray-300">No. Surat Permohonan Desa <span class="text-rose-500">*</span></label>
              <input
                v-model="formState.nomor_surat_desa"
                type="text"
                placeholder="Contoh: 140/08/Desa/2026"
                class="w-full text-xs py-2 px-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500"
              >
              <p v-if="errors.nomor_surat_desa" class="text-[11px] text-rose-500">{{ errors.nomor_surat_desa }}</p>
            </div>

            <div class="space-y-1">
              <label class="block font-medium text-gray-700 dark:text-gray-300">Tanggal Surat Desa <span class="text-rose-500">*</span></label>
              <input
                v-model="formState.tanggal_surat_desa"
                type="date"
                class="w-full text-xs py-2 px-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500"
              >
              <p v-if="errors.tanggal_surat_desa" class="text-[11px] text-rose-500">{{ errors.tanggal_surat_desa }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full">
        <UButton
          color="neutral"
          variant="ghost"
          label="Batal"
          :disabled="submitting"
          @click="isOpen = false"
        />
        <UButton
          color="warning"
          icon="i-lucide-arrow-right-left"
          label="Simpan & Buat Usulan P-APBD"
          :loading="submitting"
          @click="handleConfirm"
        />
      </div>
    </template>
  </UModal>
</template>

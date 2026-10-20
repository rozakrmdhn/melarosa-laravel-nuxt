<script setup lang="ts">
import type { BkkdProposal, RefBkkdBidang } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import WilayahSelector from '~/components/common/WilayahSelector.vue';

interface Props {
  open: boolean;
  isEditing?: boolean;
  item?: BkkdProposal | null;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  isEditing: false,
  item: null,
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
const loadingBidang = ref(false);
const bidangList = ref<RefBkkdBidang[]>([]);
const tahunOptions = getTahunAnggaranOptions(false);

const formState = reactive({
  tahun_anggaran: new Date().getFullYear() + 1,
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  id_bidang: '',
  judul_kegiatan: '',
  uraian_kegiatan: '',
  lokasi_kegiatan: '',
  latitude: null as number | null,
  longitude: null as number | null,
  panjang: null as number | null,
  lebar: null as number | null,
  tinggi: null as number | null,
  volume_target: null as number | null,
  satuan_target: 'meter',
  nilai_usulan: null as number | null,
  nomor_surat_desa: '',
  tanggal_surat_desa: '',
  tanggal_diterima: new Date().toISOString().split('T')[0],
  alasan_perubahan: '',
});

const errors = reactive<Record<string, string>>({});

const bidangSelectItems = computed(() =>
  bidangList.value.map((b) => ({
    label: b.nama,
    value: b.id,
  }))
);

const isPastDraft = computed(() => {
  return props.isEditing && props.item && props.item.status !== 'draft';
});

async function loadBidang() {
  loadingBidang.value = true;
  try {
    const res = await api.fetchRefBidang();
    bidangList.value = res.data || [];
    if (!formState.id_bidang && bidangList.value.length > 0) {
      formState.id_bidang = bidangList.value[0].id;
    }
  } catch (err) {
    console.error('Gagal memuat referensi bidang BKKD:', err);
  } finally {
    loadingBidang.value = false;
  }
}

watch(
  () => props.open,
  async (newVal) => {
    if (newVal) {
      Object.keys(errors).forEach((k) => delete errors[k]);
      await loadBidang();

      if (props.isEditing && props.item) {
        formState.tahun_anggaran = props.item.tahun_anggaran;
        formState.id_kecamatan = props.item.id_kecamatan;
        formState.id_desa = props.item.id_desa;
        formState.id_bidang = props.item.id_bidang;
        formState.judul_kegiatan = props.item.judul_kegiatan;
        formState.uraian_kegiatan = props.item.uraian_kegiatan || '';
        formState.lokasi_kegiatan = props.item.lokasi_kegiatan;
        formState.latitude = props.item.latitude || null;
        formState.longitude = props.item.longitude || null;
        formState.panjang = props.item.panjang ?? null;
        formState.lebar = props.item.lebar ?? null;
        formState.tinggi = props.item.tinggi ?? null;
        formState.volume_target = props.item.volume ?? props.item.volume_target ?? null;
        formState.satuan_target = props.item.satuan ?? props.item.satuan_target ?? 'meter';
        formState.nilai_usulan = props.item.nilai_usulan ?? null;
        formState.nomor_surat_desa = props.item.nomor_surat_desa || '';
        formState.tanggal_surat_desa = props.item.tanggal_surat_desa || '';
        formState.tanggal_diterima = props.item.tanggal_diterima || new Date().toISOString().split('T')[0];
        formState.alasan_perubahan = '';
      } else {
        formState.tahun_anggaran = new Date().getFullYear() + 1;
        formState.id_kecamatan = null;
        formState.id_desa = null;
        formState.judul_kegiatan = '';
        formState.uraian_kegiatan = '';
        formState.lokasi_kegiatan = '';
        formState.latitude = null;
        formState.longitude = null;
        formState.panjang = null;
        formState.lebar = null;
        formState.tinggi = null;
        formState.volume_target = null;
        formState.satuan_target = 'meter';
        formState.nilai_usulan = null;
        formState.nomor_surat_desa = '';
        formState.tanggal_surat_desa = '';
        formState.tanggal_diterima = new Date().toISOString().split('T')[0];
        formState.alasan_perubahan = '';
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

async function handleSubmit() {
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!formState.judul_kegiatan.trim()) {
    errors.judul_kegiatan = 'Judul kegiatan wajib diisi.';
  }
  if (!formState.id_kecamatan) {
    errors.id_kecamatan = 'Kecamatan wajib dipilih.';
  }
  if (!formState.id_desa) {
    errors.id_desa = 'Desa wajib dipilih.';
  }
  if (!formState.id_bidang) {
    errors.id_bidang = 'Bidang bantuan wajib dipilih.';
  }
  if (!formState.lokasi_kegiatan.trim()) {
    errors.lokasi_kegiatan = 'Lokasi kegiatan wajib diisi.';
  }
  if (formState.nilai_usulan !== null && formState.nilai_usulan !== undefined && (formState.nilai_usulan as any) !== '') {
    if (Number(formState.nilai_usulan) < 0) {
      errors.nilai_usulan = 'Nilai usulan tidak boleh bernilai negatif.';
    }
  }
  if (!formState.nomor_surat_desa?.trim()) {
    errors.nomor_surat_desa = 'Nomor surat permohonan desa wajib diisi.';
  }
  if (!formState.tanggal_surat_desa) {
    errors.tanggal_surat_desa = 'Tanggal surat desa wajib diisi.';
  }
  if (!formState.tanggal_diterima) {
    errors.tanggal_diterima = 'Tanggal berkas diterima wajib diisi.';
  }
  if (isPastDraft.value && !formState.alasan_perubahan.trim()) {
    errors.alasan_perubahan = 'Alasan perubahan wajib diisi karena usulan sudah melewati tahap draft.';
  }

  if (Object.keys(errors).length > 0) {
    toast.add({
      icon: 'i-lucide-alert-triangle',
      title: 'Validasi Belum Lengkap',
      description: 'Mohon periksa kembali kolom yang bertanda merah.',
      color: 'error',
    });
    return;
  }

  submitting.value = true;
  try {
    const payload: Record<string, any> = {
      tahun_anggaran: Number(formState.tahun_anggaran),
      id_kecamatan: Number(formState.id_kecamatan),
      id_desa: Number(formState.id_desa),
      id_bidang: formState.id_bidang,
      judul_kegiatan: formState.judul_kegiatan.trim(),
      uraian_kegiatan: formState.uraian_kegiatan?.trim() || null,
      lokasi_kegiatan: formState.lokasi_kegiatan.trim(),
      latitude: formState.latitude ? Number(formState.latitude) : null,
      longitude: formState.longitude ? Number(formState.longitude) : null,
      panjang: formState.panjang !== null && formState.panjang !== undefined && (formState.panjang as any) !== '' ? Number(formState.panjang) : null,
      lebar: formState.lebar !== null && formState.lebar !== undefined && (formState.lebar as any) !== '' ? Number(formState.lebar) : null,
      tinggi: formState.tinggi !== null && formState.tinggi !== undefined && (formState.tinggi as any) !== '' ? Number(formState.tinggi) : null,
      volume: formState.volume_target ? Number(formState.volume_target) : null,
      volume_target: formState.volume_target ? Number(formState.volume_target) : null,
      satuan: formState.satuan_target?.trim() || null,
      satuan_target: formState.satuan_target?.trim() || null,
      nilai_usulan: formState.nilai_usulan !== null && formState.nilai_usulan !== undefined && (formState.nilai_usulan as any) !== '' ? Number(formState.nilai_usulan) : null,
      nomor_surat_desa: formState.nomor_surat_desa?.trim() || null,
      tanggal_surat_desa: formState.tanggal_surat_desa || null,
      tanggal_diterima: formState.tanggal_diterima || new Date().toISOString().split('T')[0],
    };

    if (props.isEditing && props.item?.id) {
      payload.versi = props.item.versi;
      if (formState.alasan_perubahan.trim()) {
        payload.alasan_perubahan = formState.alasan_perubahan.trim();
      }
      await api.updateProposal(props.item.id, payload);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Diperbarui',
        description: `Proposal usulan BKKD '${payload.judul_kegiatan}' berhasil disimpan.`,
        color: 'success',
      });
    } else {
      await api.createProposal(payload);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Ditambahkan',
        description: `Proposal usulan BKKD '${payload.judul_kegiatan}' berhasil didaftarkan sebagai draft.`,
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
        description: err?.data?.message || 'Data telah diubah oleh pengguna lain. Silakan muat ulang halaman.',
        color: 'error',
      });
      return;
    }

    if (err?.data?.errors) {
      Object.keys(err.data.errors).forEach((key) => {
        errors[key] = err.data.errors[key][0];
      });
    }

    toast.add({
      icon: 'i-lucide-alert-circle',
      title: props.isEditing ? 'Gagal Memperbarui Proposal' : 'Gagal Menyimpan Proposal',
      description: err?.data?.message || 'Terjadi kesalahan pada server backend.',
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
    :title="isEditing ? `Edit Proposal: ${item?.nomor_registrasi || item?.judul_kegiatan}` : 'Tambah Usulan Proposal BKKD'"
    :description="isEditing ? 'Perbarui informasi proposal usulan Bantuan Keuangan Khusus Desa.' : 'Daftarkan proposal usulan Bantuan Keuangan Khusus Desa baru.'"
    :ui="{ content: 'sm:max-w-3xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <form class="space-y-4 py-1" @submit.prevent="handleSubmit">
        <!-- Baris 1: Tahun Anggaran & Bidang BKKD -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <UFormField label="Tahun Anggaran" required :error="errors.tahun_anggaran" size="sm">
            <USelectMenu
              v-model.number="formState.tahun_anggaran"
              :items="tahunOptions"
              value-key="value"
              label-key="label"
              class="w-full font-mono"
            />
          </UFormField>

          <UFormField label="Bidang BKKD" required :error="errors.id_bidang" size="sm">
            <USelectMenu
              v-model="formState.id_bidang"
              :items="bidangSelectItems"
              value-key="value"
              label-key="label"
              class="w-full"
              :loading="loadingBidang"
            />
          </UFormField>
        </div>

        <!-- Baris 2: Wilayah Pengusul -->
        <div class="p-3 rounded-lg border border-gray-100 dark:border-white/[0.04] bg-gray-50/50 dark:bg-gray-900/30">
          <WilayahSelector
            v-model:modelKecamatan="formState.id_kecamatan"
            v-model:modelDesa="formState.id_desa"
            required
            layout="row"
          />
          <p v-if="errors.id_kecamatan || errors.id_desa" class="text-xs text-red-500 mt-1">
            {{ errors.id_kecamatan || errors.id_desa }}
          </p>
        </div>

        <!-- Baris 3: Judul Kegiatan & Nilai Usulan -->
        <div class="space-y-3">
          <UFormField label="Judul Kegiatan Usulan" required :error="errors.judul_kegiatan" size="sm">
            <UInput
              v-model="formState.judul_kegiatan"
              placeholder="Contoh: Pembangunan Tembok Penahan Tanah (TPT) Dusun Krajan"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Nilai Usulan Anggaran (Rp)" :error="errors.nilai_usulan" size="sm">
              <UInput
                v-model.number="formState.nilai_usulan"
                type="number"
                min="0"
                step="100000"
                placeholder="Contoh: 150000000 (opsional)"
                class="w-full font-mono"
              />
              <template v-if="formState.nilai_usulan" #help>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                  Terbilang: {{ formatCurrency(formState.nilai_usulan) }}
                </span>
              </template>
            </UFormField>

            <div class="grid grid-cols-2 gap-2">
              <UFormField label="Target Volume" size="sm">
                <UInput
                  v-model.number="formState.volume_target"
                  type="number"
                  step="0.01"
                  placeholder="300"
                  class="w-full font-mono"
                />
              </UFormField>
              <UFormField label="Satuan" size="sm">
                <UInput
                  v-model="formState.satuan_target"
                  placeholder="meter / unit"
                  class="w-full"
                />
              </UFormField>
            </div>

            <!-- Dimensi Fisik Teknis Usulan -->
            <div class="grid grid-cols-3 gap-2 mt-2">
              <UFormField label="Panjang (m)" size="sm">
                <UInput
                  v-model.number="formState.panjang"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full font-mono text-xs"
                />
              </UFormField>
              <UFormField label="Lebar (m)" size="sm">
                <UInput
                  v-model.number="formState.lebar"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full font-mono text-xs"
                />
              </UFormField>
              <UFormField label="Tinggi (m)" size="sm">
                <UInput
                  v-model.number="formState.tinggi"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full font-mono text-xs"
                />
              </UFormField>
            </div>
          </div>
        </div>

        <!-- Baris 4: Lokasi & Koordinat -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="sm:col-span-2">
            <UFormField label="Lokasi Spesifik Kegiatan" required :error="errors.lokasi_kegiatan" size="sm">
              <UTextarea
                v-model="formState.lokasi_kegiatan"
                :rows="2"
                placeholder="Contoh: RT. 004 RW. 002 Dusun Candi, Kab. Bojonegoro"
                class="w-full"
              />
            </UFormField>
          </div>
          <div>
            <UFormField label="Koordinat Lat, Long (Opsional)" size="sm">
              <div class="flex gap-1">
                <UInput
                  v-model.number="formState.latitude"
                  type="number"
                  step="0.000001"
                  placeholder="Lat"
                  class="w-1/2 font-mono text-xs"
                />
                <UInput
                  v-model.number="formState.longitude"
                  type="number"
                  step="0.000001"
                  placeholder="Long"
                  class="w-1/2 font-mono text-xs"
                />
              </div>
            </UFormField>
          </div>
        </div>

        <!-- Baris 5: Uraian Kegiatan -->
        <UFormField label="Uraian Singkat Pekerjaan" size="sm">
          <UTextarea
            v-model="formState.uraian_kegiatan"
            :rows="2"
            placeholder="Jelaskan kondisi eksisting dan kebutuhan pembangunan..."
            class="w-full"
          />
        </UFormField>

        <!-- Baris 6: Surat Pengantar Desa & Tanggal Diterima -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-lg border border-gray-100 dark:border-white/[0.04] bg-gray-50/30 dark:bg-gray-900/20">
          <UFormField label="Nomor Surat Permohonan Desa" required :error="errors.nomor_surat_desa" size="sm">
            <UInput
              v-model="formState.nomor_surat_desa"
              placeholder="Contoh: 140/012/412.51.01/2026"
              class="w-full font-mono text-xs"
            />
          </UFormField>
          <UFormField label="Tanggal Surat Desa" required :error="errors.tanggal_surat_desa" size="sm">
            <UInput
              v-model="formState.tanggal_surat_desa"
              type="date"
              class="w-full text-xs"
            />
          </UFormField>
          <UFormField label="Tanggal Diterima Bappeda" required :error="errors.tanggal_diterima" size="sm">
            <UInput
              v-model="formState.tanggal_diterima"
              type="date"
              class="w-full text-xs"
            />
          </UFormField>
        </div>

        <!-- Baris 7: Alasan Perubahan (Jika status > draft) -->
        <div v-if="isPastDraft" class="p-3 rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/20 space-y-2">
          <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-semibold text-xs">
            <UIcon name="i-lucide-alert-triangle" class="w-4 h-4" />
            <span>Pemberitahuan Audit Trail: Usulan Berstatus "{{ item?.status }}"</span>
          </div>
          <p class="text-xs text-gray-600 dark:text-gray-400">
            Perubahan pada usulan yang telah diajukan atau diverifikasi wajib disertai alasan yang jelas untuk rekaman log akuntabilitas.
          </p>
          <UFormField label="Alasan Perubahan Data" required :error="errors.alasan_perubahan" size="sm">
            <UTextarea
              v-model="formState.alasan_perubahan"
              :rows="2"
              placeholder="Contoh: Penyesuaian volume target berdasarkan hasil survei bersama tim teknis desa"
              class="w-full"
            />
          </UFormField>
        </div>
      </form>
    </template>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <div class="text-xs text-gray-500">
          <span v-if="isEditing">Versi saat ini: #{{ item?.versi || 1 }}</span>
        </div>
        <div class="flex items-center gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            label="Batal"
            :disabled="submitting"
            @click="isOpen = false"
          />
          <UButton
            color="primary"
            :label="isEditing ? 'Simpan Perubahan' : 'Daftarkan Proposal'"
            :loading="submitting"
            icon="i-lucide-save"
            @click="handleSubmit"
          />
        </div>
      </div>
    </template>
  </UModal>
</template>

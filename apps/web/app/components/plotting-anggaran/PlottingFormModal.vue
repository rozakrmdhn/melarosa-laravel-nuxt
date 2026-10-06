<script setup lang="ts">
import type { PlottingAnggaran, RefSumberDana } from '~/types/infrastruktur';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import WilayahSelector from '~/components/common/WilayahSelector.vue';

interface Props {
  open: boolean;
  isEditing?: boolean;
  item?: PlottingAnggaran | null;
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
const api = useInfrastrukturApi();

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit('update:open', val),
});

const submitting = ref(false);
const loadingSumberDana = ref(false);
const sumberDanaList = ref<RefSumberDana[]>([]);

const jenisBantuanOptions = [
  { label: 'Bantuan Keuangan Khusus (BKK Desa)', value: 'BKK Desa' },
  { label: 'Dana Alokasi Khusus (DAK Fisik)', value: 'DAK Fisik' },
  { label: 'APBD Reguler Kabupaten', value: 'APBD Reguler' },
  { label: 'Bantuan Provinsi (Banprov)', value: 'Banprov' },
  { label: 'Dana Bagi Hasil (DBH)', value: 'DBH' },
  { label: 'Lainnya', value: 'Lainnya' },
];

const formState = reactive({
  tahun_anggaran: new Date().getFullYear(),
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  jenis_bantuan: 'BKK Desa',
  nama_kegiatan: '',
  lokasi_kegiatan: '',
  sumber_dana: '',
  target_pagu_anggaran: 0,
  target_panjang_m: 0,
});

const errors = reactive<Record<string, string>>({});

const sumberDanaSelectItems = computed(() =>
  sumberDanaList.value.map((sd) => ({
    label: `${sd.nama} (${sd.kode})`,
    value: sd.kode,
  }))
);

async function loadSumberDana() {
  loadingSumberDana.value = true;
  try {
    const res = await api.fetchSumberDanaList({ active_only: true });
    sumberDanaList.value = res.data || [];

    if (!formState.sumber_dana && sumberDanaList.value.length > 0) {
      formState.sumber_dana = sumberDanaList.value[0].kode;
    }
  } catch (err) {
    console.error('Gagal memuat referensi sumber dana:', err);
  } finally {
    loadingSumberDana.value = false;
  }
}

watch(
  () => props.open,
  (val) => {
    if (val) {
      Object.keys(errors).forEach((k) => delete errors[k]);
      if (props.isEditing && props.item) {
        Object.assign(formState, {
          tahun_anggaran: props.item.tahun_anggaran,
          id_kecamatan: props.item.id_kecamatan,
          id_desa: props.item.id_desa,
          jenis_bantuan: props.item.jenis_bantuan || 'BKK Desa',
          nama_kegiatan: props.item.nama_kegiatan || '',
          lokasi_kegiatan: props.item.lokasi_kegiatan || '',
          sumber_dana: props.item.sumber_dana || '',
          target_pagu_anggaran: Number(props.item.target_pagu_anggaran) || 0,
          target_panjang_m: Number(props.item.target_panjang_m) || 0,
        });
      } else {
        Object.assign(formState, {
          tahun_anggaran: new Date().getFullYear(),
          id_kecamatan: null,
          id_desa: null,
          jenis_bantuan: 'BKK Desa',
          nama_kegiatan: '',
          lokasi_kegiatan: '',
          sumber_dana: sumberDanaList.value[0]?.kode || '',
          target_pagu_anggaran: 0,
          target_panjang_m: 0,
        });
      }
      loadSumberDana();
    }
  },
  { immediate: true }
);

function formatRupiahPreview(val: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

async function handleSubmit() {
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!formState.nama_kegiatan.trim()) {
    errors.nama_kegiatan = 'Nama kegiatan wajib diisi.';
  }
  if (!formState.id_kecamatan) {
    errors.id_kecamatan = 'Kecamatan wajib dipilih.';
  }
  if (!formState.id_desa) {
    errors.id_desa = 'Desa wajib dipilih.';
  }
  if (!formState.sumber_dana) {
    errors.sumber_dana = 'Sumber dana wajib dipilih.';
  }
  if (formState.target_pagu_anggaran <= 0) {
    errors.target_pagu_anggaran = 'Pagu anggaran harus lebih dari 0.';
  }
  if (formState.target_panjang_m <= 0) {
    errors.target_panjang_m = 'Target panjang fisik harus lebih dari 0 meter.';
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
    const payload = {
      tahun_anggaran: Number(formState.tahun_anggaran),
      id_kecamatan: Number(formState.id_kecamatan),
      id_desa: Number(formState.id_desa),
      jenis_bantuan: formState.jenis_bantuan,
      nama_kegiatan: formState.nama_kegiatan.trim(),
      lokasi_kegiatan: formState.lokasi_kegiatan?.trim() || null,
      sumber_dana: formState.sumber_dana,
      target_pagu_anggaran: Number(formState.target_pagu_anggaran),
      target_panjang_m: Number(formState.target_panjang_m),
    };

    if (props.isEditing && props.item?.id) {
      await api.updatePlotting(props.item.id, payload);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Diperbarui',
        description: `Plotting kegiatan '${payload.nama_kegiatan}' berhasil disimpan.`,
        color: 'success',
      });
    } else {
      await api.createPlotting(payload);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Ditambahkan',
        description: `Plotting kegiatan '${payload.nama_kegiatan}' berhasil dibuat.`,
        color: 'success',
      });
    }

    isOpen.value = false;
    emit('success');
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menyimpan',
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
    :title="isEditing ? `Edit Plotting: ${item?.nama_kegiatan}` : 'Tambah Perencanaan Plotting Anggaran'"
    :description="isEditing ? 'Perbarui informasi pagu alokasi dan target panjang fisik kegiatan.' : 'Daftarkan alokasi pagu kegiatan baru untuk tahun anggaran berjalan.'"
    :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <form class="space-y-4 py-1" @submit.prevent="handleSubmit">
        <!-- Baris 1: Tahun Anggaran & Jenis Bantuan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <UFormField label="Tahun Anggaran" required :error="errors.tahun_anggaran" size="sm">
            <UInput
              v-model.number="formState.tahun_anggaran"
              type="number"
              min="2020"
              max="2035"
              class="w-full font-mono"
              required
            />
          </UFormField>

          <UFormField label="Jenis Bantuan" required size="sm">
            <USelectMenu
              v-model="formState.jenis_bantuan"
              :items="jenisBantuanOptions"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Baris 2: Wilayah Bertingkat (Kecamatan -> Desa) -->
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

        <!-- Baris 3: Nama Kegiatan & Lokasi -->
        <UFormField label="Nama Paket / Kegiatan Pembangunan" required :error="errors.nama_kegiatan" size="sm">
          <UInput
            v-model="formState.nama_kegiatan"
            placeholder="Contoh: Peningkatan Jalan Poros Desa Ruas Dusun A - Dusun B"
            class="w-full"
            required
          />
        </UFormField>

        <UFormField label="Lokasi Pekerjaan Spesifik" size="sm" help="Keterangan tambahan lokasi / RT / Dusun setempat">
          <UInput
            v-model="formState.lokasi_kegiatan"
            placeholder="Contoh: Dusun Krajan RT 04 RW 02"
            class="w-full"
          />
        </UFormField>

        <!-- Baris 4: Sumber Dana, Target Pagu & Target Panjang -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <UFormField label="Sumber Pendanaan" required :error="errors.sumber_dana" size="sm">
            <USelectMenu
              v-model="formState.sumber_dana"
              :items="sumberDanaSelectItems"
              value-key="value"
              label-key="label"
              :loading="loadingSumberDana"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Target Pagu Anggaran (Rp)" required :error="errors.target_pagu_anggaran" size="sm">
            <UInput
              v-model.number="formState.target_pagu_anggaran"
              type="number"
              min="0"
              step="1000000"
              class="w-full font-mono"
              required
            />
            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 font-mono mt-0.5 block truncate">
              {{ formatRupiahPreview(formState.target_pagu_anggaran) }}
            </span>
          </UFormField>

          <UFormField label="Target Panjang Fisik (m)" required :error="errors.target_panjang_m" size="sm">
            <UInput
              v-model.number="formState.target_panjang_m"
              type="number"
              min="0"
              step="10"
              class="w-full font-mono"
              required
            />
            <span class="text-[11px] text-gray-500 font-mono mt-0.5 block">
              {{ (formState.target_panjang_m || 0).toLocaleString('id-ID') }} meter
              <span v-if="formState.target_panjang_m >= 1000" class="text-blue-500">
                ({{ (formState.target_panjang_m / 1000).toFixed(2) }} km)
              </span>
            </span>
          </UFormField>
        </div>
      </form>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <UButton
          label="Batal"
          color="neutral"
          variant="ghost"
          @click="isOpen = false"
        />
        <UButton
          :label="isEditing ? 'Simpan Perubahan' : 'Buat Plotting Kegiatan'"
          color="primary"
          :loading="submitting"
          @click="handleSubmit"
        />
      </div>
    </template>
  </UModal>
</template>

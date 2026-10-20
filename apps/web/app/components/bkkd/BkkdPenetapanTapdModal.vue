<script setup lang="ts">
import type { BkkdProposal } from '~/types/bkkd';
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
  (e: 'success'): void;
}>();

const toast = useToast();
const api = useBkkdApi();

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit('update:open', val),
});

const submitting = ref(false);

const formState = reactive({
  nilai_disetujui_tapd: 0,
  catatan_tapd: '',
  tanggal_penetapan_tapd: new Date().toISOString().split('T')[0],
});

const errors = reactive<Record<string, string>>({});

watch(
  () => props.open,
  (newVal) => {
    if (newVal && props.proposal) {
      Object.keys(errors).forEach((k) => delete errors[k]);

      // Nilai default: nilai disetujui sebelumnya, atau rekomendasi OPD, atau nilai usulan
      const defaultValue =
        props.proposal.nilai_disetujui_tapd ||
        props.proposal.verifikasi_aktif?.rekomendasi_anggaran ||
        props.proposal.nilai_usulan ||
        0;

      formState.nilai_disetujui_tapd = Number(defaultValue);
      formState.catatan_tapd = props.proposal.catatan_tapd || '';
      formState.tanggal_penetapan_tapd =
        props.proposal.tanggal_penetapan_tapd || new Date().toISOString().split('T')[0];
    }
  }
);

function formatCurrency(val?: number | null) {
  if (val === null || val === undefined) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

async function handleTetapkan() {
  if (!props.proposal) return;
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (formState.nilai_disetujui_tapd <= 0) {
    errors.nilai_disetujui_tapd = 'Nilai pagu disetujui TAPD harus lebih dari 0.';
    return;
  }

  submitting.value = true;
  try {
    await api.tetapkanTapd(props.proposal.id, {
      nilai_disetujui_tapd: Number(formState.nilai_disetujui_tapd),
      catatan_tapd: formState.catatan_tapd.trim() || undefined,
      tanggal_penetapan_tapd: formState.tanggal_penetapan_tapd,
    });

    toast.add({
      icon: 'i-lucide-award',
      title: 'Usulan Ditetapkan TAPD',
      description: `Usulan '${props.proposal.nomor_registrasi}' resmi masuk ke Rancangan APBD.`,
      color: 'success',
    });

    emit('success');
    isOpen.value = false;
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menetapkan Usulan',
      description: err?.data?.message || 'Terjadi kesalahan sistem saat proses penetapan.',
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
    title="Penetapan Usulan BKKD oleh TAPD"
    description="Pilih dan sesuaikan alokasi pagu usulan prioritas desa untuk dimasukkan ke dalam Rancangan APBD."
    :ui="{ content: 'sm:max-w-xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <div v-if="proposal" class="space-y-4 py-1">
        <!-- Rangkuman Identitas Usulan -->
        <div class="p-3.5 rounded-lg border border-gray-100 dark:border-white/[0.06] bg-gray-50/70 dark:bg-gray-900/40 space-y-2 text-xs">
          <div class="flex items-center justify-between pb-2 border-b border-gray-200/60 dark:border-white/[0.04]">
            <span class="font-bold text-gray-900 dark:text-gray-100">{{ proposal.nomor_registrasi }}</span>
            <span class="text-gray-500">Desa {{ proposal.nama_desa }}, Kec. {{ proposal.nama_kecamatan }}</span>
          </div>

          <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">
            {{ proposal.judul_kegiatan }}
          </p>

          <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
            <div class="p-2 rounded bg-white dark:bg-gray-800/60 border border-gray-100 dark:border-white/[0.04]">
              <div class="text-gray-500">Nilai Usulan Desa:</div>
              <div class="font-semibold text-gray-800 dark:text-gray-200 font-mono text-xs">
                {{ formatCurrency(proposal.nilai_usulan) }}
              </div>
            </div>

            <div class="p-2 rounded bg-white dark:bg-gray-800/60 border border-gray-100 dark:border-white/[0.04]">
              <div class="text-gray-500">Rekomendasi OPD Teknis:</div>
              <div class="font-semibold text-emerald-600 dark:text-emerald-400 font-mono text-xs">
                {{ proposal.verifikasi_aktif?.rekomendasi_anggaran ? formatCurrency(proposal.verifikasi_aktif.rekomendasi_anggaran) : 'Sesuai Usulan' }}
              </div>
            </div>
          </div>
        </div>

        <!-- Penyesuaian Anggaran Pagu oleh TAPD -->
        <div class="space-y-3">
          <UFormField
            label="Pagu Anggaran Disetujui TAPD (Rancangan APBD)"
            required
            :error="errors.nilai_disetujui_tapd"
            size="sm"
          >
            <UInput
              v-model.number="formState.nilai_disetujui_tapd"
              type="number"
              min="0"
              step="100000"
              class="w-full font-mono text-base font-semibold"
            />
            <template #help>
              <div class="flex items-center justify-between mt-1 text-xs">
                <span class="text-primary-600 dark:text-primary-400 font-medium">
                  {{ formatCurrency(formState.nilai_disetujui_tapd) }}
                </span>
                <span class="text-gray-500">
                  (TAPD dapat menyesuaikan pagu alokasi)
                </span>
              </div>
            </template>
          </UFormField>

          <UFormField label="Tanggal Penetapan TAPD" required size="sm">
            <UInput
              v-model="formState.tanggal_penetapan_tapd"
              type="date"
              class="w-full text-xs"
            />
          </UFormField>

          <UFormField label="Catatan / Pertimbangan TAPD" size="sm">
            <UTextarea
              v-model="formState.catatan_tapd"
              :rows="3"
              placeholder="Contoh: Disetujui sebagai usulan prioritas konektivitas desa dengan penyesuaian pagu anggaran..."
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Warning Satu Desa Satu Usulan -->
        <div class="p-3 rounded-lg border border-amber-200/80 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/20 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
          <UIcon name="i-lucide-info" class="w-4 h-4 shrink-0 mt-0.5" />
          <span>
            <strong>Aturan Kebijakan Satu Desa Satu Usulan:</strong> Menetapkan usulan ini akan otomatis mengubah usulan lain dari Desa {{ proposal.nama_desa }} pada tahun anggaran {{ proposal.tahun_anggaran }} menjadi status <em>Tidak Terpilih TAPD</em>.
          </span>
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
          color="primary"
          label="Tetapkan Usulan Masuk APBD"
          :loading="submitting"
          icon="i-lucide-award"
          @click="handleTetapkan"
        />
      </div>
    </template>
  </UModal>
</template>

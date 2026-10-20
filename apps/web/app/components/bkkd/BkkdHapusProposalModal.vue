<script setup lang="ts">
import type { BkkdProposal } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';

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

function formatCurrency(val?: number | null) {
  if (val === null || val === undefined) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

async function handleConfirm() {
  if (!props.proposal) return;

  submitting.value = true;
  try {
    await api.deleteProposal(props.proposal.id);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Proposal Dihapus',
      description: `Proposal '${props.proposal.nomor_registrasi}' berhasil dipindahkan ke tempat sampah.`,
      color: 'success',
    });
    emit('success');
    isOpen.value = false;
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menghapus Proposal',
      description: err?.data?.message || 'Terjadi kesalahan saat menghapus proposal.',
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
    title="Hapus Usulan Proposal BKKD"
    description="Konfirmasi penghapusan usulan proposal BKKD."
    :ui="{ content: 'sm:max-w-md dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <div v-if="proposal" class="space-y-3.5 py-1 text-xs">
        <p class="text-gray-600 dark:text-gray-300">
          Apakah Anda yakin ingin menghapus usulan proposal berikut?
        </p>

        <!-- Kartu Ringkasan Proposal -->
        <div class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/70 dark:bg-gray-900/40 space-y-2">
          <div class="flex justify-between items-center gap-2">
            <span class="font-mono font-bold text-gray-900 dark:text-gray-100">
              {{ proposal.nomor_registrasi }}
            </span>
            <BkkdStatusBadge :status="proposal.status" size="xs" />
          </div>

          <div class="flex justify-between items-start gap-2">
            <span class="text-gray-500 shrink-0">Kegiatan:</span>
            <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">
              {{ proposal.judul_kegiatan }}
            </span>
          </div>

          <div class="flex justify-between items-start gap-2">
            <span class="text-gray-500">Wilayah:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200 text-right">
              Desa {{ proposal.nama_desa || '-' }}, Kec. {{ proposal.nama_kecamatan || '-' }}
            </span>
          </div>

          <div class="flex justify-between items-center pt-1 border-t border-gray-200/60 dark:border-white/[0.06]">
            <span class="text-gray-500">Nilai Usulan:</span>
            <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400 text-sm">
              {{ formatCurrency(proposal.nilai_usulan) }}
            </span>
          </div>
        </div>

        <!-- Info Box (Soft Delete) -->
        <div class="p-3 rounded-lg bg-red-50/60 dark:bg-red-950/25 border border-red-200/70 dark:border-red-900/40 flex items-start gap-2 text-red-700 dark:text-red-300">
          <UIcon name="i-lucide-info" class="w-4 h-4 shrink-0 mt-0.5 text-red-600 dark:text-red-400" />
          <span class="leading-relaxed">
            Proposal akan dipindahkan ke arsip tempat sampah (soft delete). Data tidak hilang permanen dan dapat dipulihkan kembali oleh administrator bila diperlukan.
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
          color="error"
          icon="i-lucide-trash-2"
          label="Ya, Hapus Proposal"
          :loading="submitting"
          @click="handleConfirm"
        />
      </div>
    </template>
  </UModal>
</template>

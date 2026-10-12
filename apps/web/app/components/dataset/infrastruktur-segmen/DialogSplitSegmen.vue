<script setup lang="ts">
import type { InfrastrukturSegmen } from '~/types/infrastruktur';

interface Props {
  open: boolean;
  segmen: InfrastrukturSegmen | null;
  splitPoint: [number, number] | null;
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'confirm', payload: { part1Name: string; part2Name: string }): void;
  (e: 'cancel'): void;
}>();

const part1Name = ref('');
const part2Name = ref('');

watch(
  () => props.segmen,
  (s) => {
    if (s) {
      part1Name.value = `${s.namobj || 'Segmen'} (Bagian 1)`;
      part2Name.value = `${s.namobj || 'Segmen'} (Bagian 2)`;
    }
  },
  { immediate: true }
);

function handleConfirm() {
  if (!part1Name.value.trim() || !part2Name.value.trim()) return;
  emit('confirm', {
    part1Name: part1Name.value.trim(),
    part2Name: part2Name.value.trim(),
  });
}
</script>

<template>
  <UModal
    :open="open"
    title="Konfirmasi Pemotongan Segmen"
    :ui="{ content: 'sm:max-w-md' }"
    @update:open="(val) => emit('update:open', val)"
  >
    <template #body>
      <div class="space-y-4 text-xs text-gray-700 dark:text-gray-300">
        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 space-y-2">
          <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm">
            {{ segmen?.namobj || 'Segmen Tanpa Nama' }}
          </p>
          <div class="flex items-center gap-4 text-gray-500 dark:text-gray-400">
            <span>Panjang: <strong class="text-gray-800 dark:text-gray-200">{{ segmen?.panjang ?? segmen?.panjang_meter_gis ?? 0 }} m</strong></span>
            <span>Tipe: <strong class="text-gray-800 dark:text-gray-200">{{ segmen?.tipe_kode || '-' }}</strong></span>
          </div>
          <p v-if="splitPoint" class="font-mono text-[11px] text-blue-600 dark:text-blue-400">
            Titik Potong: {{ splitPoint[0].toFixed(5) }}, {{ splitPoint[1].toFixed(5) }}
          </p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Nama Bagian 1</label>
            <UInput v-model="part1Name" placeholder="Nama Ruas Bagian 1" size="sm" class="w-full" />
          </div>

          <div>
            <label class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Nama Bagian 2 (Baru)</label>
            <UInput v-model="part2Name" placeholder="Nama Ruas Bagian 2" size="sm" class="w-full" />
          </div>
        </div>

        <p class="text-[11px] text-gray-400 dark:text-gray-500 leading-relaxed">
          Pemotongan membagi geometri menjadi 2 segmen terpisah. Atribut administratif dan teknis dari segmen asal akan diwariskan ke bagian kedua.
        </p>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton color="neutral" variant="ghost" size="sm" label="Batal" @click="emit('cancel')" />
        <UButton
          color="primary"
          size="sm"
          icon="i-lucide-scissors"
          label="Konfirmasi Split"
          :loading="loading"
          @click="handleConfirm"
        />
      </div>
    </template>
  </UModal>
</template>

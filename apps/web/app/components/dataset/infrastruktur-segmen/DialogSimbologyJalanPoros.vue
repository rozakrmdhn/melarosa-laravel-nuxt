<script setup lang="ts">
import SimbologyContent from '~/components/dataset/infrastruktur-segmen/SimbologyContent.vue';
import type { LayerSymbology } from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY } from '~/types/dataset-editor';

interface Props {
  open?: boolean;
  symbology?: LayerSymbology;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  symbology: () => ({
    ...DEFAULT_SYMBOLOGY,
    lineColor: '#2563eb',
    lineWidth: 2,
  }),
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update:symbology', val: LayerSymbology): void;
  (e: 'reset'): void;
}>();

function handleClose() {
  emit('update:open', false);
}
</script>

<template>
  <UModal
    :open="open"
    title="Pengaturan Gaya: Jalan Poros Desa"
    description="Sesuaikan warna garis, mode tematik (tunggal, kondisi, perkerasan), ketebalan, dan label ruas jalan poros desa."
    :ui="{
      content: 'max-w-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-2xl rounded-2xl'
    }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <SimbologyContent
        layer-id="jalan-poros-desa"
        layer-title="Jalan Poros Desa"
        :symbology="symbology"
        :is-mobile="false"
        @update:symbology="emit('update:symbology', $event)"
        @reset="emit('reset')"
        @close="handleClose"
      />
    </template>
  </UModal>
</template>

<script setup lang="ts">
import FilterContent from '~/components/dataset/infrastruktur-segmen/FilterContent.vue';

interface Props {
  open?: boolean;
  selectedPerkerasan?: string | null;
  selectedKondisi?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
}

withDefaults(defineProps<Props>(), {
  open: false,
  selectedPerkerasan: null,
  selectedKondisi: null,
  modelKecamatan: null,
  modelDesa: null,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update:selectedPerkerasan', val: string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'apply'): void;
  (e: 'reset'): void;
}>();

function handleApply() {
  emit('apply');
  emit('update:open', false);
}
</script>

<template>
  <UModal
    :open="open"
    title="Filter: Jalan Poros Desa"
    description="Saring data jalan poros desa berdasarkan wilayah, jenis perkerasan, dan kondisi."
    :ui="{
      content: 'max-w-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-2xl rounded-2xl'
    }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <FilterContent
        layer-id="jalan-poros-desa"
        layer-title="Jalan Poros Desa"
        :selected-perkerasan="selectedPerkerasan"
        :selected-kondisi="selectedKondisi"
        :model-kecamatan="modelKecamatan"
        :model-desa="modelDesa"
        :is-mobile="false"
        @update:selected-perkerasan="emit('update:selectedPerkerasan', $event)"
        @update:selected-kondisi="emit('update:selectedKondisi', $event)"
        @update:model-kecamatan="emit('update:modelKecamatan', $event)"
        @update:model-desa="emit('update:modelDesa', $event)"
        @apply="handleApply"
        @reset="emit('reset')"
      />
    </template>
  </UModal>
</template>

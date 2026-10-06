<script setup lang="ts">
import FilterContent from '~/components/dataset/infrastruktur-segmen/FilterContent.vue';
import type { InfrastrukturTipe } from '~/types/infrastruktur';

interface Props {
  open?: boolean;
  tipeList?: InfrastrukturTipe[];
  selectedTipe?: string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  selectedPerkerasan?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
}

withDefaults(defineProps<Props>(), {
  open: false,
  tipeList: () => [],
  selectedTipe: null,
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  selectedPerkerasan: null,
  modelKecamatan: null,
  modelDesa: null,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:selectedPerkerasan', val: string | null): void;
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
    title="Filter: Segmen Fisik Infrastruktur"
    description="Saring data segmen fisik berdasarkan wilayah dan atribut teknis."
    :ui="{
      content: 'max-w-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-2xl rounded-2xl'
    }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <FilterContent
        layer-id="infrastruktur-segmen"
        layer-title="Segmen Fisik Infrastruktur"
        :tipe-list="tipeList"
        :selected-tipe="selectedTipe"
        :selected-kondisi="selectedKondisi"
        :selected-status-verifikasi="selectedStatusVerifikasi"
        :selected-perkerasan="selectedPerkerasan"
        :model-kecamatan="modelKecamatan"
        :model-desa="modelDesa"
        :is-mobile="false"
        @update:selected-tipe="emit('update:selectedTipe', $event)"
        @update:selected-kondisi="emit('update:selectedKondisi', $event)"
        @update:selected-status-verifikasi="emit('update:selectedStatusVerifikasi', $event)"
        @update:selected-perkerasan="emit('update:selectedPerkerasan', $event)"
        @update:model-kecamatan="emit('update:modelKecamatan', $event)"
        @update:model-desa="emit('update:modelDesa', $event)"
        @apply="handleApply"
        @reset="emit('reset')"
      />
    </template>
  </UModal>
</template>

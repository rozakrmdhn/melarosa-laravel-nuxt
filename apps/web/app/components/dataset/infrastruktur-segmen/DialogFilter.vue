<script setup lang="ts">
import FilterContent from '~/components/dataset/infrastruktur-segmen/FilterContent.vue';
import type { InfrastrukturTipe } from '~/types/infrastruktur';

interface Props {
  open?: boolean;
  activeLayerId?: string;
  tipeList?: InfrastrukturTipe[];
  // Filter Layer Segmen Fisik
  selectedTipe?: string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  segmenFilterCount?: number;

  // Filter Layer Jalan Poros Desa
  jalanPerkerasan?: string | null;
  jalanKondisi?: string | null;
  jalanKecamatan?: number | null;
  jalanDesa?: number | null;
  jalanFilterCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  activeLayerId: 'infrastruktur-segmen',
  tipeList: () => [],
  selectedTipe: null,
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  modelKecamatan: null,
  modelDesa: null,
  segmenFilterCount: 0,
  jalanPerkerasan: null,
  jalanKondisi: null,
  jalanKecamatan: null,
  jalanDesa: null,
  jalanFilterCount: 0,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update:activeLayerId', val: string): void;
  // Segmen Filter Emits
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'reset-segmen'): void;
  (e: 'apply-segmen'): void;

  // Jalan Poros Filter Emits
  (e: 'update:jalanPerkerasan', val: string | null): void;
  (e: 'update:jalanKondisi', val: string | null): void;
  (e: 'update:jalanKecamatan', val: number | null): void;
  (e: 'update:jalanDesa', val: number | null): void;
  (e: 'reset-jalan'): void;
  (e: 'apply-jalan'): void;

  (e: 'apply'): void;
}>();

const currentHasActiveFilter = computed(() => {
  if (props.activeLayerId === 'jalan-poros-desa') {
    return (props.jalanFilterCount ?? 0) > 0;
  }
  return (props.segmenFilterCount ?? 0) > 0;
});

function handleResetCurrent() {
  if (props.activeLayerId === 'jalan-poros-desa') {
    emit('reset-jalan');
  } else {
    emit('reset-segmen');
  }
}

function handleApply() {
  if (props.activeLayerId === 'jalan-poros-desa') {
    emit('apply-jalan');
  } else {
    emit('apply-segmen');
  }
  emit('apply');
  emit('update:open', false);
}
</script>

<template>
  <UModal
    :open="open"
    title="Filter Data Peta"
    description="Pilih layer dan tentukan kriteria wilayah atau atribut data."
    :ui="{
      content: 'max-w-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-gray-800 shadow-2xl rounded-2xl'
    }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <!-- Layer Segmented Switcher -->
      <div class="mb-4 p-1 bg-gray-100 dark:bg-gray-850/80 rounded-xl flex items-center gap-1 border border-gray-200/60 dark:border-gray-800">
        <button
          type="button"
          :class="[
            'flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[36px]',
            activeLayerId === 'infrastruktur-segmen'
              ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
          ]"
          @click="emit('update:activeLayerId', 'infrastruktur-segmen')"
        >
          <UIcon name="i-lucide-route" class="size-3.5 shrink-0" />
          <span class="truncate">Segmen Fisik</span>
          <UBadge
            v-if="(segmenFilterCount ?? 0) > 0"
            :label="String(segmenFilterCount)"
            size="xs"
            color="primary"
            variant="subtle"
            class="text-[9px] px-1 py-0 h-4 font-mono ml-0.5"
          />
        </button>

        <button
          type="button"
          :class="[
            'flex-1 py-1.5 px-3 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer min-h-[36px]',
            activeLayerId === 'jalan-poros-desa'
              ? 'bg-white dark:bg-[#0e1424] text-blue-600 dark:text-blue-400 font-semibold shadow-xs'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
          ]"
          @click="emit('update:activeLayerId', 'jalan-poros-desa')"
        >
          <UIcon name="i-lucide-milestone" class="size-3.5 shrink-0" />
          <span class="truncate">Jalan Poros Desa</span>
          <UBadge
            v-if="(jalanFilterCount ?? 0) > 0"
            :label="String(jalanFilterCount)"
            size="xs"
            color="info"
            variant="subtle"
            class="text-[9px] px-1 py-0 h-4 font-mono ml-0.5"
          />
        </button>
      </div>

      <!-- Layer 1 Content: Segmen Fisik -->
      <FilterContent
        v-if="activeLayerId === 'infrastruktur-segmen'"
        layer-id="infrastruktur-segmen"
        layer-title="Segmen Fisik Infrastruktur"
        :tipe-list="tipeList"
        :selected-tipe="selectedTipe"
        :selected-kondisi="selectedKondisi"
        :selected-status-verifikasi="selectedStatusVerifikasi"
        :model-kecamatan="modelKecamatan"
        :model-desa="modelDesa"
        :is-mobile="false"
        :hide-header="true"
        :hide-footer-actions="true"
        @update:selected-tipe="emit('update:selectedTipe', $event)"
        @update:selected-kondisi="emit('update:selectedKondisi', $event)"
        @update:selected-status-verifikasi="emit('update:selectedStatusVerifikasi', $event)"
        @update:model-kecamatan="emit('update:modelKecamatan', $event)"
        @update:model-desa="emit('update:modelDesa', $event)"
      />

      <!-- Layer 2 Content: Jalan Poros Desa -->
      <FilterContent
        v-else-if="activeLayerId === 'jalan-poros-desa'"
        layer-id="jalan-poros-desa"
        layer-title="Jalan Poros Desa"
        :selected-perkerasan="jalanPerkerasan"
        :selected-kondisi="jalanKondisi"
        :model-kecamatan="jalanKecamatan"
        :model-desa="jalanDesa"
        :is-mobile="false"
        :hide-header="true"
        :hide-footer-actions="true"
        @update:selected-perkerasan="emit('update:jalanPerkerasan', $event)"
        @update:selected-kondisi="emit('update:jalanKondisi', $event)"
        @update:model-kecamatan="emit('update:jalanKecamatan', $event)"
        @update:model-desa="emit('update:jalanDesa', $event)"
      />

      <!-- Dialog Unified Footer -->
      <div class="mt-5 pt-3.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <div>
          <UButton
            v-if="currentHasActiveFilter"
            icon="i-lucide-rotate-ccw"
            label="Atur Ulang"
            color="neutral"
            variant="ghost"
            size="xs"
            class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            @click="handleResetCurrent"
          />
        </div>

        <div class="flex items-center gap-2">
          <UButton
            label="Tutup"
            color="neutral"
            variant="subtle"
            size="xs"
            class="cursor-pointer"
            @click="emit('update:open', false)"
          />
          <UButton
            icon="i-lucide-check"
            label="Terapkan Filter"
            color="primary"
            variant="solid"
            size="xs"
            class="cursor-pointer font-medium"
            @click="handleApply"
          />
        </div>
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import SimbologyContent from '~/components/dataset/infrastruktur-segmen/SimbologyContent.vue';
import type { LayerSymbology } from '~/types/dataset-editor';
import { DEFAULT_SYMBOLOGY } from '~/types/dataset-editor';

interface Props {
  open?: boolean;
  activeLayerId?: string;
  segmenSymbology?: LayerSymbology;
  jalanSymbology?: LayerSymbology;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  activeLayerId: 'infrastruktur-segmen',
  segmenSymbology: () => ({ ...DEFAULT_SYMBOLOGY }),
  jalanSymbology: () => ({
    ...DEFAULT_SYMBOLOGY,
    lineColor: '#2563eb',
    lineWidth: 2,
  }),
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update:activeLayerId', val: string): void;
  (e: 'update:segmenSymbology', val: LayerSymbology): void;
  (e: 'update:jalanSymbology', val: LayerSymbology): void;
  (e: 'reset-segmen'): void;
  (e: 'reset-jalan'): void;
}>();

function handleClose() {
  emit('update:open', false);
}

function handleResetCurrent() {
  if (props.activeLayerId === 'jalan-poros-desa') {
    emit('reset-jalan');
  } else {
    emit('reset-segmen');
  }
}
</script>

<template>
  <UModal
    :open="open"
    title="Pengaturan Gaya Layer"
    description="Sesuaikan warna garis, ketebalan, dan label teks pada peta."
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
        </button>
      </div>

      <!-- Layer 1 Simbology: Segmen Fisik -->
      <SimbologyContent
        v-if="activeLayerId === 'infrastruktur-segmen'"
        layer-id="infrastruktur-segmen"
        layer-title="Segmen Fisik Infrastruktur"
        :symbology="segmenSymbology"
        :is-mobile="false"
        :hide-footer-actions="true"
        @update:symbology="emit('update:segmenSymbology', $event)"
        @reset="emit('reset-segmen')"
        @close="handleClose"
      />

      <!-- Layer 2 Simbology: Jalan Poros Desa -->
      <SimbologyContent
        v-else-if="activeLayerId === 'jalan-poros-desa'"
        layer-id="jalan-poros-desa"
        layer-title="Jalan Poros Desa"
        :symbology="jalanSymbology"
        :is-mobile="false"
        :hide-footer-actions="true"
        @update:symbology="emit('update:jalanSymbology', $event)"
        @reset="emit('reset-jalan')"
        @close="handleClose"
      />

      <!-- Dialog Unified Footer -->
      <div class="mt-5 pt-3.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <div>
          <UButton
            icon="i-lucide-rotate-ccw"
            label="Atur Ulang Bawaan"
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
            @click="handleClose"
          />
          <UButton
            icon="i-lucide-check"
            label="Selesai"
            color="primary"
            variant="solid"
            size="xs"
            class="cursor-pointer font-medium"
            @click="handleClose"
          />
        </div>
      </div>
    </template>
  </UModal>
</template>

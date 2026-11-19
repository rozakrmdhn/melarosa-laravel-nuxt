<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import JaringanJalanFilterContent from '~/components/jaringan-jalan/JaringanJalanFilterContent.vue';

interface Props {
  open: boolean;
  filters: any;
  ordeOptions: Array<{ value: string; label: string; total: number }>;
  isKecamatanLocked?: boolean;
  isDesaLocked?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isKecamatanLocked: false,
  isDesaLocked: false,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update-filter', payload: Record<string, any>): void;
  (e: 'reset-filters'): void;
}>();

function closeModal() {
  emit('update:open', false);
}

function handleKeydown(e: KeyboardEvent) {
  if (props.open && e.key === 'Escape') {
    e.preventDefault();
    closeModal();
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!import.meta.client) return;
    if (isOpen) {
      window.addEventListener('keydown', handleKeydown);
    } else {
      window.removeEventListener('keydown', handleKeydown);
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener('keydown', handleKeydown);
  }
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4 backdrop-blur-2xs"
      role="dialog"
      aria-modal="true"
      aria-labelledby="filter-modal-title"
      @click.self="closeModal"
    >
      <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-2xl overflow-hidden dark:border-gray-800 dark:bg-[#0b0f19]">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-sliders-horizontal" class="size-4 text-[#176B4D] dark:text-emerald-400" />
            <h2 id="filter-modal-title" class="text-sm font-semibold text-gray-900 dark:text-white">
              Filter Analisa & Jaringan Jalan
            </h2>
          </div>

          <button
            type="button"
            aria-label="Tutup dialog filter"
            class="size-8 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="closeModal"
          >
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </div>

        <div class="p-4 max-h-[80dvh] overflow-y-auto">
          <JaringanJalanFilterContent
            :filters="filters"
            :orde-options="ordeOptions"
            :is-kecamatan-locked="isKecamatanLocked"
            :is-desa-locked="isDesaLocked"
            :is-mobile-drawer="false"
            @update-filter="(p) => emit('update-filter', p)"
            @reset-filters="emit('reset-filters')"
            @close="closeModal"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>

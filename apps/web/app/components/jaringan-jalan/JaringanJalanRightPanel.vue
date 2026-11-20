<script setup lang="ts">
import { computed } from 'vue';
import JaringanJalanInspectorCard from '~/components/jaringan-jalan/JaringanJalanInspectorCard.vue';
import type {
  HierarkiJalan,
  JalanKabupatenItem,
  JaringanJalanRuasFeature,
  RtrwAnalisaDetail,
} from '~/types/jaringan-jalan';

interface Props {
  collapsed: boolean;
  activeMode: 'rtrw' | 'poros' | 'kabupaten' | null;
  rtrwDetail: RtrwAnalisaDetail | null;
  loadingRtrwDetail: boolean;
  selectedPoros: JaringanJalanRuasFeature | null;
  selectedKabupaten: JalanKabupatenItem | null;
  canManage: boolean;
  adoptingRtrw: boolean;
  savingPoros: boolean;
  deletingPoros: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (
    e: 'adopt-rtrw',
    payload: {
      id: number;
      nama_ruas: string;
      hierarki_jalan: HierarkiJalan;
      tahun_survey: number;
      skip_spatial_duplicate: boolean;
    },
  ): void;
  (
    e: 'save-poros',
    payload: {
      id: string;
      kode_ruas?: string | null;
      nama_ruas: string;
      hierarki_jalan: HierarkiJalan;
      lebar_m?: number | null;
      perkerasan?: string | null;
      kondisi?: string | null;
      catatan_validasi?: string | null;
    },
  ): void;
  (e: 'request-delete-poros', feature: JaringanJalanRuasFeature): void;
  (e: 'clear-selection'): void;
  (e: 'zoom-to-bbox', bbox: [number, number, number, number] | null): void;
}>();

const activeLabel = computed(() => {
  if (props.activeMode === 'rtrw') return 'RTRW';
  if (props.activeMode === 'poros') return 'Poros';
  if (props.activeMode === 'kabupaten') return 'Kabupaten';
  return null;
});
</script>

<template>
  <aside class="flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]">
    <!-- Header -->
    <div class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-2.5 gap-2 justify-between">
      <div v-if="!collapsed" class="flex items-center gap-2 overflow-hidden">
        <UIcon name="i-lucide-info" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
        <h2 class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate">
          Inspektor Ruas
        </h2>
        <span
          v-if="activeLabel"
          class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-semibold text-blue-800 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-200 dark:border-blue-800 shrink-0"
        >
          {{ activeLabel }}
        </span>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <UTooltip v-if="activeMode && !collapsed" text="Bersihkan pilihan & tutup detail">
          <button
            type="button"
            aria-label="Hapus pilihan ruas saat ini"
            class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="emit('clear-selection')"
          >
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </UTooltip>

        <UTooltip :text="collapsed ? 'Buka panel inspektor' : 'Ciutkan panel inspektor'">
          <button
            type="button"
            :aria-expanded="!collapsed"
            :aria-label="collapsed ? 'Buka panel inspektor' : 'Ciutkan panel inspektor'"
            class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="emit('update:collapsed', !collapsed)"
          >
            <UIcon
              name="i-lucide-chevron-right"
              :class="['size-4 transition-transform duration-200', collapsed ? 'rotate-180' : '']"
            />
          </button>
        </UTooltip>
      </div>
    </div>

    <!-- Collapsed Vertical Rail / Expanded Body -->
    <Transition name="panel-fade" mode="out-in">
      <button
        v-if="collapsed"
        key="rail"
        type="button"
        :aria-expanded="false"
        aria-label="Buka panel inspektor ruas"
        class="flex flex-col items-center pt-3 gap-3 flex-1 w-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-600"
        title="Klik untuk membuka inspektor ruas"
        @click="emit('update:collapsed', false)"
      >
        <span
          :class="[
            'p-1.5 rounded-md',
            activeMode
              ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400'
              : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
          ]"
        >
          <UIcon name="i-lucide-info" class="size-4" />
        </span>
        <span
          class="text-xs font-medium text-gray-600 dark:text-gray-300 tracking-wide whitespace-nowrap"
          style="writing-mode: vertical-rl;"
        >
          Inspektor Ruas
        </span>
      </button>

      <!-- Expanded Body -->
      <div v-else key="body" class="flex-1 min-h-0 overflow-y-auto p-3">
        <JaringanJalanInspectorCard
          :active-mode="activeMode"
          :rtrw-detail="rtrwDetail"
          :loading-rtrw-detail="loadingRtrwDetail"
          :selected-poros="selectedPoros"
          :selected-kabupaten="selectedKabupaten"
          :can-manage="canManage"
          :adopting-rtrw="adoptingRtrw"
          :saving-poros="savingPoros"
          :deleting-poros="deletingPoros"
          :is-mobile-drawer="false"
          @adopt-rtrw="(p) => emit('adopt-rtrw', p)"
          @save-poros="(p) => emit('save-poros', p)"
          @request-delete-poros="(f) => emit('request-delete-poros', f)"
          @clear-selection="emit('clear-selection')"
          @zoom-to-bbox="(b) => emit('zoom-to-bbox', b)"
        />
      </div>
    </Transition>
  </aside>
</template>

<style scoped>
.panel-fade-enter-active,
.panel-fade-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}

.panel-fade-enter-from,
.panel-fade-leave-to {
  opacity: 0;
  transform: translateX(4px);
}

@media (prefers-reduced-motion: reduce) {
  .panel-fade-enter-active,
  .panel-fade-leave-active {
    transition: none;
  }
}
</style>

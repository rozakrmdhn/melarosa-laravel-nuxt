<script setup lang="ts">
import BasemapThumbnail from '~/components/common/BasemapThumbnail.vue';
import { useBasemapStore } from '~/stores/basemap';
import type { BasemapEngine, BasemapItem, BasemapVariant } from '~/types/basemap';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    variant?: BasemapVariant;
    engine?: BasemapEngine;
    allowNone?: boolean;
    placement?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';
    isMobile?: boolean;
    isMobileSheetOpen?: boolean;
  }>(),
  {
    variant: 'medium',
    engine: 'all',
    allowNone: false,
    placement: 'top-right',
    isMobile: false,
    isMobileSheetOpen: false,
  },
);

const emit = defineEmits<{
  (e: 'update:modelValue', code: string): void;
  (e: 'change', item: BasemapItem | null): void;
  (e: 'open-mobile-sheet'): void;
}>();

const basemapStore = useBasemapStore();
const rootRef = ref<HTMLElement | null>(null);
const isPopoverOpen = ref(false);
const isHovered = ref(false);
let closeTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
  basemapStore.ensureLoaded();
  window.addEventListener('click', handleClickOutside);
  window.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
  if (closeTimer) clearTimeout(closeTimer);
  window.removeEventListener('click', handleClickOutside);
  window.removeEventListener('keydown', handleKeyDown);
});

const basemapList = computed<BasemapItem[]>(() => basemapStore.listForEngine(props.engine));

const isNoneActive = computed(() => {
  const v = String(props.modelValue || '').trim().toLowerCase();
  return v === 'none' || v === 'kanvas';
});

const activeItem = computed<BasemapItem | null>(() => {
  if (isNoneActive.value) return null;
  return basemapStore.getByCode(props.modelValue, props.engine);
});

const isInteractiveOpen = computed(() => {
  if (props.isMobile) return Boolean(props.isMobileSheetOpen);
  return isHovered.value || isPopoverOpen.value;
});

function selectCode(code: string) {
  emit('update:modelValue', code);
  emit('change', code === 'none' ? null : basemapStore.getByCode(code, props.engine));
  isPopoverOpen.value = false;
  isHovered.value = false;
}

function handleInteractiveEnter() {
  if (props.isMobile || props.variant !== 'interactive') return;
  if (closeTimer) {
    clearTimeout(closeTimer);
    closeTimer = null;
  }
  isHovered.value = true;
}

function handleInteractiveLeave() {
  if (props.isMobile || props.variant !== 'interactive') return;
  if (closeTimer) clearTimeout(closeTimer);
  closeTimer = setTimeout(() => {
    isHovered.value = false;
  }, 420);
}

function handleInteractiveToggle() {
  if (props.isMobile) {
    emit('open-mobile-sheet');
    return;
  }
  isPopoverOpen.value = !isPopoverOpen.value;
}

function handleKeyDown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    isPopoverOpen.value = false;
    isHovered.value = false;
  }
}

function handleClickOutside(e: MouseEvent) {
  if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
    isPopoverOpen.value = false;
    isHovered.value = false;
  }
}

const mediumPopoverPositionClass = computed(() => {
  switch (props.placement) {
    case 'top-left':
      return 'top-full left-0 mt-2';
    case 'bottom-left':
      return 'bottom-full left-0 mb-2';
    case 'bottom-right':
      return 'bottom-full right-0 mb-2';
    default:
      return 'top-full right-0 mt-2';
  }
});
</script>

<template>
  <div
    ref="rootRef"
    class="relative inline-flex select-none"
    @mouseenter="handleInteractiveEnter"
    @mouseleave="handleInteractiveLeave"
  >
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. MINI BASEMAP (Untuk MiniMap & Bottom Sheet)
         ═══════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="variant === 'mini'"
      role="radiogroup"
      aria-label="Pilihan basemap ringkas"
      class="inline-flex items-center gap-1 p-1 rounded-xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800 shadow-md max-w-full overflow-x-auto"
    >
      <button
        v-for="bm in basemapList"
        :key="bm.kode"
        type="button"
        role="radio"
        :aria-checked="!isNoneActive && activeItem?.kode === bm.kode"
        :title="bm.nama"
        class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-lg text-[11px] font-semibold transition-colors cursor-pointer shrink-0 min-h-[30px] focus-visible:outline-2 focus-visible:outline-blue-600"
        :class="
          !isNoneActive && activeItem?.kode === bm.kode
            ? 'bg-blue-600 text-white shadow-xs'
            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/80'
        "
        @click="selectCode(bm.kode)"
      >
        <span class="size-5 rounded-md overflow-hidden border border-black/10 dark:border-white/15 shrink-0 block">
          <BasemapThumbnail :preview-style="bm.preview_style" :thumbnail-url="bm.thumbnail_url" :alt="bm.nama" />
        </span>
        <span class="whitespace-nowrap">{{ bm.label_singkat }}</span>
      </button>

      <button
        v-if="allowNone"
        type="button"
        role="radio"
        :aria-checked="isNoneActive"
        title="Kanvas Polos"
        class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-lg text-[11px] font-semibold transition-colors cursor-pointer shrink-0 min-h-[30px] focus-visible:outline-2 focus-visible:outline-blue-600"
        :class="
          isNoneActive
            ? 'bg-blue-600 text-white shadow-xs'
            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/80'
        "
        @click="selectCode('none')"
      >
        <span class="size-5 rounded-md overflow-hidden border border-black/10 dark:border-white/15 shrink-0 block">
          <BasemapThumbnail preview-style="none" alt="Kanvas Polos" />
        </span>
        <span class="whitespace-nowrap">Polos</span>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. MEDIUM BASEMAP (Untuk Dialog & /admin/infrastruktur-segmen)
         ═══════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="variant === 'medium'">
      <button
        type="button"
        :aria-expanded="isPopoverOpen"
        aria-label="Pilih Peta Dasar (Basemap)"
        class="inline-flex items-center gap-2 pl-1.5 pr-2.5 py-1.5 rounded-xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border shadow-md hover:shadow-lg transition-all cursor-pointer min-h-[36px] focus-visible:outline-2 focus-visible:outline-blue-600"
        :class="
          isPopoverOpen
            ? 'border-blue-500 ring-2 ring-blue-500/25'
            : 'border-gray-200/90 dark:border-gray-800 hover:border-blue-400/70'
        "
        @click="isPopoverOpen = !isPopoverOpen"
      >
        <span class="size-6 rounded-md overflow-hidden border border-black/10 dark:border-white/15 shrink-0 block">
          <BasemapThumbnail
            :preview-style="isNoneActive ? 'none' : (activeItem?.preview_style || 'esri')"
            :thumbnail-url="isNoneActive ? null : activeItem?.thumbnail_url"
            :alt="isNoneActive ? 'Kanvas Polos' : (activeItem?.nama || 'Basemap')"
          />
        </span>
        <span class="text-xs font-semibold text-gray-800 dark:text-gray-100 whitespace-nowrap max-w-[120px] truncate">
          {{ isNoneActive ? 'Kanvas Polos' : (activeItem?.label_singkat || 'Basemap') }}
        </span>
        <UIcon
          name="i-lucide-chevron-down"
          class="size-3.5 text-gray-500 dark:text-gray-400 transition-transform duration-150 shrink-0"
          :class="isPopoverOpen ? 'rotate-180' : ''"
          aria-hidden="true"
        />
      </button>

      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-120 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="isPopoverOpen"
          role="radiogroup"
          aria-label="Daftar pilihan basemap"
          :class="[
            'absolute z-50 w-[280px] p-2.5 rounded-2xl bg-white/98 dark:bg-[#0b0f19]/98 backdrop-blur-md border border-gray-200 dark:border-gray-800 shadow-2xl space-y-2',
            mediumPopoverPositionClass,
          ]"
        >
          <div class="flex items-center justify-between px-0.5">
            <span class="text-[11px] font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-1.5">
              <UIcon name="i-lucide-globe" class="size-3.5 text-blue-600 dark:text-blue-400" aria-hidden="true" />
              <span>Peta Dasar (Basemap)</span>
            </span>
            <span class="text-[10px] text-gray-500 dark:text-gray-400 tabular-nums">
              {{ basemapList.length }} opsi
            </span>
          </div>

          <div class="grid grid-cols-2 gap-1.5 max-h-[260px] overflow-y-auto pr-0.5">
            <button
              v-for="bm in basemapList"
              :key="bm.kode"
              type="button"
              role="radio"
              :aria-checked="!isNoneActive && activeItem?.kode === bm.kode"
              class="group relative flex flex-col rounded-xl overflow-hidden border text-left transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              :class="
                !isNoneActive && activeItem?.kode === bm.kode
                  ? 'border-blue-600 ring-1 ring-blue-500/40 bg-blue-50/50 dark:bg-blue-950/30'
                  : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0e1424] hover:border-blue-400/70'
              "
              @click="selectCode(bm.kode)"
            >
              <div class="relative w-full aspect-[16/9] bg-gray-100 dark:bg-gray-900 overflow-hidden">
                <BasemapThumbnail :preview-style="bm.preview_style" :thumbnail-url="bm.thumbnail_url" :alt="bm.nama" />
                <span
                  v-if="!isNoneActive && activeItem?.kode === bm.kode"
                  class="absolute top-1 right-1 size-4 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-xs"
                >
                  <UIcon name="i-lucide-check" class="size-2.5" aria-hidden="true" />
                </span>
              </div>
              <div class="px-2 py-1.5 min-w-0">
                <p class="text-[11px] font-semibold text-gray-900 dark:text-white truncate leading-tight">
                  {{ bm.label_singkat }}
                </p>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                  {{ bm.provider || bm.nama }}
                </p>
              </div>
            </button>

            <button
              v-if="allowNone"
              type="button"
              role="radio"
              :aria-checked="isNoneActive"
              class="group relative flex flex-col rounded-xl overflow-hidden border text-left transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              :class="
                isNoneActive
                  ? 'border-blue-600 ring-1 ring-blue-500/40 bg-blue-50/50 dark:bg-blue-950/30'
                  : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0e1424] hover:border-blue-400/70'
              "
              @click="selectCode('none')"
            >
              <div class="relative w-full aspect-[16/9] bg-white overflow-hidden">
                <BasemapThumbnail preview-style="none" alt="Kanvas Polos" />
                <span
                  v-if="isNoneActive"
                  class="absolute top-1 right-1 size-4 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-xs"
                >
                  <UIcon name="i-lucide-check" class="size-2.5" aria-hidden="true" />
                </span>
              </div>
              <div class="px-2 py-1.5 min-w-0">
                <p class="text-[11px] font-semibold text-gray-900 dark:text-white truncate leading-tight">
                  Kanvas Polos
                </p>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                  Tanpa peta dasar
                </p>
              </div>
            </button>
          </div>
        </div>
      </Transition>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. INTERAKTIF BASEMAP (Untuk halaman /maps)
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else class="relative inline-flex items-end">
      <UTooltip text="Pilihan Peta Dasar (Basemap)">
        <button
          type="button"
          data-basemap-toggle="true"
          :aria-expanded="isInteractiveOpen"
          aria-label="Pilihan Peta Dasar (Basemap)"
          class="relative size-11 rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-all duration-200 cursor-pointer border bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md shrink-0 z-20 active:scale-95 focus-visible:outline-2 focus-visible:outline-blue-600"
          :class="
            isInteractiveOpen
              ? 'border-blue-500 ring-2 ring-blue-500/30'
              : 'border-gray-200/90 dark:border-gray-800 hover:border-blue-400/80'
          "
          @click="handleInteractiveToggle"
        >
          <BasemapThumbnail
            :preview-style="isNoneActive ? 'none' : (activeItem?.preview_style || 'esri')"
            :thumbnail-url="isNoneActive ? null : activeItem?.thumbnail_url"
            :alt="activeItem?.nama || 'Basemap'"
          />
          <span
            class="absolute inset-x-0 bottom-0 bg-slate-950/75 text-white text-[9px] font-semibold text-center py-0.5 truncate px-0.5 leading-none"
          >
            {{ isNoneActive ? 'Polos' : (activeItem?.label_singkat || 'Peta') }}
          </span>
        </button>
      </UTooltip>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-x-2 scale-95"
        enter-to-class="opacity-100 translate-x-0 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-x-0 scale-100"
        leave-to-class="opacity-0 translate-x-2 scale-95"
      >
        <div
          v-if="!isMobile && isInteractiveOpen"
          role="radiogroup"
          aria-label="Pilih Peta Dasar Interaktif"
          class="absolute right-full bottom-0 mr-2 z-30 p-2 rounded-2xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800 shadow-2xl flex items-stretch gap-2 max-w-[min(74vw,540px)] overflow-x-auto"
        >
          <button
            v-for="bm in basemapList"
            :key="bm.kode"
            type="button"
            role="radio"
            :aria-checked="!isNoneActive && activeItem?.kode === bm.kode"
            :title="bm.tagline || bm.nama"
            class="w-[82px] shrink-0 flex flex-col rounded-xl overflow-hidden border transition-all cursor-pointer text-left focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              !isNoneActive && activeItem?.kode === bm.kode
                ? 'border-blue-600 ring-2 ring-blue-500/30 bg-blue-50/40 dark:bg-blue-950/30'
                : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0e1424] hover:border-blue-400/80'
            "
            @click="selectCode(bm.kode)"
          >
            <div class="relative w-full h-12 bg-gray-100 dark:bg-gray-900 overflow-hidden">
              <BasemapThumbnail :preview-style="bm.preview_style" :thumbnail-url="bm.thumbnail_url" :alt="bm.nama" />
              <span
                v-if="!isNoneActive && activeItem?.kode === bm.kode"
                class="absolute top-1 right-1 size-4 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-xs"
              >
                <UIcon name="i-lucide-check" class="size-2.5" aria-hidden="true" />
              </span>
            </div>
            <div class="px-1.5 py-1 min-w-0">
              <p class="text-[11px] font-semibold text-gray-900 dark:text-white truncate leading-tight">
                {{ bm.label_singkat }}
              </p>
              <p class="text-[9px] text-gray-500 dark:text-gray-400 truncate uppercase tracking-wide">
                {{ bm.tipe }}
              </p>
            </div>
          </button>

          <button
            v-if="allowNone"
            type="button"
            role="radio"
            :aria-checked="isNoneActive"
            title="Kanvas polos tanpa basemap"
            class="w-[74px] shrink-0 flex flex-col rounded-xl overflow-hidden border transition-all cursor-pointer text-left focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              isNoneActive
                ? 'border-blue-600 ring-2 ring-blue-500/30 bg-blue-50/40 dark:bg-blue-950/30'
                : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0e1424] hover:border-blue-400/80'
            "
            @click="selectCode('none')"
          >
            <div class="relative w-full h-12 bg-white overflow-hidden">
              <BasemapThumbnail preview-style="none" alt="Kanvas Polos" />
              <span
                v-if="isNoneActive"
                class="absolute top-1 right-1 size-4 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-xs"
              >
                <UIcon name="i-lucide-check" class="size-2.5" aria-hidden="true" />
              </span>
            </div>
            <div class="px-1.5 py-1 min-w-0">
              <p class="text-[11px] font-semibold text-gray-900 dark:text-white truncate leading-tight">
                Polos
              </p>
              <p class="text-[9px] text-gray-500 dark:text-gray-400 truncate uppercase tracking-wide">
                kanvas
              </p>
            </div>
          </button>
        </div>
      </Transition>
    </div>
  </div>
</template>

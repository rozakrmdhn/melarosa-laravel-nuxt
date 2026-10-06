<script setup lang="ts">
// Native-feel bottom sheet with touch drag handle and snap points for mobile GIS interface.
// Snap levels: PEEK (~44% viewport height), FULL (92% viewport height).

const props = withDefaults(
  defineProps<{
    open?: boolean;
    title?: string;
    icon?: string;
    initialSnap?: 'peek' | 'full';
  }>(),
  {
    open: false,
    title: 'Tabel Atribut',
    icon: 'i-lucide-table',
    initialSnap: 'peek',
  }
);

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'expand'): void;
  (e: 'peek'): void;
}>();

// Snap Constants
const PEEK_VH = 0.44;
const FULL_VH = 0.92;
const DISMISS_PX = 72;
const SNAP_UP_PX = -56;
const SNAP_DOWN_PX = 72;

type SnapLevel = 'peek' | 'full';

const snapLevel = ref<SnapLevel>(props.initialSnap || 'peek');
const isDragging = ref(false);
const dragStartY = ref(0);
const dragDelta = ref(0);

const viewportH = ref(typeof window !== 'undefined' ? window.innerHeight : 700);
const peekH = computed(() => Math.round(viewportH.value * PEEK_VH));
const fullH = computed(() => Math.round(viewportH.value * FULL_VH));

const currentBaseH = computed(() =>
  snapLevel.value === 'full' ? fullH.value : peekH.value
);

const liveTranslateY = computed(() => {
  if (!isDragging.value) return 0;
  const raw = dragDelta.value;
  if (snapLevel.value === 'full' && raw < 0) return raw * 0.2;
  if (snapLevel.value === 'peek' && raw < 0) return raw * 0.35;
  return raw;
});

const currentTranslateY = computed(() => Math.max(0, liveTranslateY.value));

const sheetStyle = computed(() => ({
  height: `${currentBaseH.value}px`,
  transform: `translateY(${liveTranslateY.value}px)`,
  '--sheet-current-y': `${currentTranslateY.value}px`,
  transition: isDragging.value
    ? 'none'
    : 'transform 0.34s cubic-bezier(0.32, 0.72, 0, 1), height 0.34s cubic-bezier(0.32, 0.72, 0, 1)',
}));

function startDrag(e: TouchEvent | MouseEvent) {
  isDragging.value = true;
  dragStartY.value = 'touches' in e ? e.touches[0].clientY : e.clientY;
  dragDelta.value = 0;
}

function onDrag(e: TouchEvent | MouseEvent) {
  if (!isDragging.value) return;
  const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
  dragDelta.value = clientY - dragStartY.value;
}

function endDrag() {
  if (!isDragging.value) return;
  const delta = dragDelta.value;
  isDragging.value = false;
  dragDelta.value = 0;
  dragStartY.value = 0;

  if (snapLevel.value === 'peek') {
    if (delta > DISMISS_PX) {
      emit('update:open', false);
    } else if (delta < SNAP_UP_PX) {
      snapLevel.value = 'full';
      emit('expand');
    }
  } else if (snapLevel.value === 'full') {
    if (delta > SNAP_DOWN_PX) {
      snapLevel.value = 'peek';
      emit('peek');
    }
  }
}

function handleResize() {
  if (typeof window !== 'undefined') {
    viewportH.value = window.innerHeight;
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', handleResize, { passive: true });
    window.addEventListener('touchmove', onDrag, { passive: true });
    window.addEventListener('touchend', endDrag);
    window.addEventListener('mousemove', onDrag);
    window.addEventListener('mouseup', endDrag);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('touchmove', onDrag);
    window.removeEventListener('touchend', endDrag);
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', endDrag);
  }
});

watch(
  () => props.open,
  (val) => {
    if (val) {
      snapLevel.value = props.initialSnap || 'peek';
    }
  }
);

watch(
  () => props.initialSnap,
  (val) => {
    if (val) {
      snapLevel.value = val;
    }
  }
);

function toggleSnap() {
  if (snapLevel.value === 'peek') {
    snapLevel.value = 'full';
    emit('expand');
  } else {
    snapLevel.value = 'peek';
    emit('peek');
  }
}

function dismiss() {
  emit('update:open', false);
}
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop overlay -->
    <Transition name="sheet-backdrop">
      <div
        v-if="open"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-[2px] lg:hidden"
        @click="dismiss"
      />
    </Transition>

    <!-- Bottom Sheet Container (touch-none removed so children can scroll naturally) -->
    <Transition name="sheet-slide">
      <div
        v-if="open"
        class="fixed bottom-0 left-0 right-0 z-40 lg:hidden flex flex-col bg-white dark:bg-[#0b0f19] rounded-t-2xl shadow-2xl border-t border-gray-200 dark:border-gray-800 will-change-transform"
        :style="sheetStyle"
      >
        <!-- Top Drag Header: touch-none strictly isolated here for dragging gesture -->
        <div
          class="shrink-0 flex flex-col items-center pt-2.5 pb-2 px-4 cursor-grab active:cursor-grabbing border-b border-gray-100 dark:border-gray-800/80 touch-none select-none"
          @mousedown="startDrag"
          @touchstart.passive="startDrag"
        >
          <!-- Drag pill handle -->
          <div class="w-10 h-1.5 rounded-full bg-gray-300 dark:bg-gray-700 hover:bg-gray-400 transition-colors mb-2" />

          <!-- Title Bar -->
          <div class="w-full flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <UIcon :name="icon" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
              <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                {{ title }}
              </span>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <!-- Touch-friendly snap toggle: 44px min tap target compliant with antislop-layoutmobile R-03 -->
              <UButton
                :icon="snapLevel === 'peek' ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                size="sm"
                color="neutral"
                variant="ghost"
                class="size-8 min-h-[44px] min-w-[44px] p-0 flex items-center justify-center cursor-pointer"
                :title="snapLevel === 'peek' ? 'Perbesar panel' : 'Perkecil panel'"
                @click="toggleSnap"
              />
              <!-- Touch-friendly close button: 44px min tap target compliant with antislop-layoutmobile R-03 -->
              <UButton
                icon="i-lucide-x"
                size="sm"
                color="neutral"
                variant="ghost"
                class="size-8 min-h-[44px] min-w-[44px] p-0 flex items-center justify-center cursor-pointer"
                title="Tutup panel"
                @click="dismiss"
              />
            </div>
          </div>
        </div>

        <!-- Optional Tabs Slot -->
        <slot name="tabs" />

        <!-- Sheet Body: clipped container allowing inner panels to handle their own scrolling -->
        <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet-backdrop-enter-active,
.sheet-backdrop-leave-active {
  transition: opacity 0.25s ease;
}
.sheet-backdrop-enter-from,
.sheet-backdrop-leave-to {
  opacity: 0;
}

.sheet-slide-enter-active,
.sheet-slide-leave-active {
  transition: transform 0.32s cubic-bezier(0.32, 0.72, 0, 1);
}
.sheet-slide-enter-from,
.sheet-slide-leave-to {
  transform: translateY(100%) !important;
}
</style>

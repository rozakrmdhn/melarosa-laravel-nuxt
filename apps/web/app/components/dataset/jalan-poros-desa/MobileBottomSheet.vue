<script setup lang="ts">
// Native-feel bottom sheet with drag handle + snap points for mobile GIS interface.
// Snap levels: PEEK (~42% viewport height), FULL (92% viewport height).
// Swipe down past threshold at PEEK dismisses. Swipe up from PEEK expands to FULL.

const props = withDefaults(defineProps<{
  open?: boolean;
  title?: string;
  icon?: string;
}>(), {
  open: false,
  title: "Tabel Atribut",
  icon: "i-lucide-table",
});

const emit = defineEmits<{
  (e: "update:open", val: boolean): void;
  (e: "expand"): void;
  (e: "peek"): void;
}>();

// ─── Snap Constants ───────────────────────────────────────────────────────────
const PEEK_VH = 0.44;       // 44% of vh
const FULL_VH = 0.92;       // 92% of vh
const DISMISS_PX = 72;      // px dragged down from PEEK to dismiss
const SNAP_UP_PX = -56;     // px dragged up from PEEK to snap to FULL
const SNAP_DOWN_PX = 72;    // px dragged down from FULL to snap to PEEK

// ─── Reactive State ───────────────────────────────────────────────────────────
type SnapLevel = "peek" | "full";

const snapLevel = ref<SnapLevel>("peek");
const isDragging = ref(false);
const dragStartY = ref(0);
const dragDelta = ref(0);   // positive = dragged down, negative = up

// Computed heights
const viewportH = ref(typeof window !== "undefined" ? window.innerHeight : 700);
const peekH = computed(() => Math.round(viewportH.value * PEEK_VH));
const fullH = computed(() => Math.round(viewportH.value * FULL_VH));

const currentBaseH = computed(() =>
  snapLevel.value === "full" ? fullH.value : peekH.value
);

// Live translateY during drag (positive = sliding down = smaller visible height)
const liveTranslateY = computed(() => {
  if (!isDragging.value) return 0;
  // Resist pulling further up when full, slight resistance pulling down when peek
  const raw = dragDelta.value;
  if (snapLevel.value === "full" && raw < 0) return raw * 0.2;   // rubber band up
  if (snapLevel.value === "peek" && raw < 0) return raw * 0.35;  // rubber band up
  return raw;
});

const sheetStyle = computed(() => ({
  height: `${currentBaseH.value}px`,
  transform: props.open
    ? `translateY(${liveTranslateY.value}px)`
    : "translateY(100%)",
  transition: isDragging.value
    ? "none"
    : "transform 0.34s cubic-bezier(0.32, 0.72, 0, 1), height 0.34s cubic-bezier(0.32, 0.72, 0, 1)",
}));

// ─── Drag Handlers ────────────────────────────────────────────────────────────

function startDrag(e: TouchEvent | MouseEvent) {
  isDragging.value = true;
  dragStartY.value = "touches" in e ? e.touches[0].clientY : e.clientY;
  dragDelta.value = 0;
}

function onDrag(e: TouchEvent | MouseEvent) {
  if (!isDragging.value) return;
  const clientY = "touches" in e ? e.touches[0].clientY : e.clientY;
  dragDelta.value = clientY - dragStartY.value;
}

function endDrag() {
  if (!isDragging.value) return;
  const delta = dragDelta.value;
  isDragging.value = false;
  dragDelta.value = 0;
  dragStartY.value = 0;

  if (snapLevel.value === "peek") {
    if (delta > DISMISS_PX) {
      // Swipe down enough: dismiss
      emit("update:open", false);
    } else if (delta < SNAP_UP_PX) {
      // Swipe up enough: expand to full
      snapLevel.value = "full";
      emit("expand");
    }
    // otherwise: spring back to peek
  } else if (snapLevel.value === "full") {
    if (delta > SNAP_DOWN_PX) {
      // Swipe down from full: back to peek
      snapLevel.value = "peek";
      emit("peek");
    }
    // otherwise: spring back to full
  }
}

// ─── Actions ──────────────────────────────────────────────────────────────────

function close() {
  emit("update:open", false);
  snapLevel.value = "peek";
}

function toggleSnap() {
  snapLevel.value = snapLevel.value === "full" ? "peek" : "full";
}

// Reset snap level on open
watch(() => props.open, (val) => {
  if (val) snapLevel.value = "peek";
});

// Keyboard Escape
function onKeydown(e: KeyboardEvent) {
  if (e.key === "Escape" && props.open) {
    e.preventDefault();
    if (snapLevel.value === "full") {
      snapLevel.value = "peek";
    } else {
      close();
    }
  }
}

// Track viewport height changes (orientation change)
function onResize() {
  viewportH.value = window.innerHeight;
}

onMounted(() => {
  document.addEventListener("keydown", onKeydown);
  window.addEventListener("resize", onResize, { passive: true });
});
onUnmounted(() => {
  document.removeEventListener("keydown", onKeydown);
  window.removeEventListener("resize", onResize);
});
</script>

<template>
  <Teleport to="body">
    <!-- Scrim: only when fully expanded -->
    <Transition name="scrim">
      <div
        v-if="open && snapLevel === 'full'"
        class="fixed inset-0 z-[38] bg-black/25"
        aria-hidden="true"
        @click="snapLevel = 'peek'"
      />
    </Transition>

    <!-- Sheet wrapper: controls height + vertical position -->
    <div
      v-if="open"
      class="fixed bottom-0 left-0 right-0 z-[39] will-change-transform"
      :style="sheetStyle"
      @touchmove.passive="onDrag"
      @touchend="endDrag"
      @mousemove="onDrag"
      @mouseup="endDrag"
      @mouseleave="endDrag"
    >
      <!-- Inner container: rounded top corners, clipped overflow for content -->
      <div
        role="region"
        :aria-label="title"
        class="flex flex-col h-full bg-white dark:bg-[#0d1117] rounded-t-[20px] overflow-hidden border-t border-l border-r border-gray-200/60 dark:border-gray-800/80 shadow-[0_-8px_40px_rgba(0,0,0,0.18)]"
      >

        <!-- ── Drag Handle Zone ── -->
        <div
          class="shrink-0 flex flex-col items-center gap-0.5 pt-2.5 pb-1 touch-none select-none cursor-grab active:cursor-grabbing"
          @touchstart.passive="startDrag"
          @mousedown.prevent="startDrag"
        >
          <!-- Pill handle -->
          <div
            class="w-10 h-[5px] rounded-full transition-colors duration-150"
            :class="isDragging
              ? 'bg-emerald-500 dark:bg-emerald-400'
              : 'bg-gray-300 dark:bg-gray-600'"
          />
        </div>

        <!-- ── Sheet Header ── -->
        <header class="shrink-0 flex items-center gap-2 px-4 pt-1 pb-2.5 border-b border-gray-100 dark:border-gray-800">
          <slot name="header-icon">
            <UIcon :name="icon" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          </slot>

          <span class="flex-1 text-sm font-semibold text-gray-800 dark:text-gray-100 truncate leading-none">
            {{ title }}
          </span>

          <!-- Expand/collapse toggle: tap to snap between peek and full -->
          <button
            type="button"
            class="min-w-[40px] min-h-[40px] flex items-center justify-center rounded-full text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800/70 active:bg-gray-200 dark:active:bg-gray-700 transition-colors"
            :aria-label="snapLevel === 'full' ? 'Perkecil' : 'Perbesar'"
            @click="toggleSnap"
          >
            <UIcon
              :name="snapLevel === 'full' ? 'i-lucide-chevron-down' : 'i-lucide-chevron-up'"
              class="size-5"
            />
          </button>

          <!-- Close button -->
          <button
            type="button"
            class="min-w-[40px] min-h-[40px] flex items-center justify-center rounded-full text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800/70 active:bg-gray-200 dark:active:bg-gray-700 transition-colors"
            aria-label="Tutup"
            @click="close"
          >
            <UIcon name="i-lucide-x" class="size-[18px]" />
          </button>
        </header>

        <!-- ── Tab Bar slot ── -->
        <slot name="tabs" />

        <!-- ── Content Body ── -->
        <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
          <slot />
        </div>

        <!-- ── Footer (pagination) slot ── -->
        <slot name="footer" />

        <!-- Safe area spacer for phones with home indicator -->
        <div class="shrink-0" style="height: env(safe-area-inset-bottom, 0px)" />
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.scrim-enter-active,
.scrim-leave-active {
  transition: opacity 0.22s ease;
}
.scrim-enter-from,
.scrim-leave-to {
  opacity: 0;
}
</style>

<script setup lang="ts">
// ─── Native-feel Mobile Bottom Sheet for /maps Interface ─────────────────────
// Features:
// - Fluid touch & mouse drag gestures with inertia & velocity flick detection
// - Fixed-base container with translateY translation (eliminates empty bottom gap on drag up)
// - Damped rubber-banding at upper (full) and lower (min) extremes
// - 3 Ergonomic Snap Levels:
//     * MIN (~72px + safe area): compact floating pill for non-intrusive map exploration
//     * PEEK (48% vh): quick basemap & active layer controls while keeping map interactive
//     * FULL (92% vh): deep exploration (28 kecamatan list, geoportal catalogue, WMS table)
// - Accessible keyboard controls (Escape, ArrowUp, ArrowDown) and min 44x44px touch targets

export type SnapLevel = "min" | "peek" | "full";

const props = withDefaults(
  defineProps<{
    open?: boolean;
    snap?: SnapLevel;
    title?: string;
    icon?: string;
    subtitle?: string;
  }>(),
  {
    open: false,
    snap: "peek",
    title: "Lapisan Peta & Data",
    icon: "i-lucide-layers",
    subtitle: "",
  }
);

const emit = defineEmits<{
  (e: "update:open", val: boolean): void;
  (e: "update:snap", val: SnapLevel): void;
  (e: "expand"): void;
  (e: "peek"): void;
  (e: "minimize"): void;
}>();

// ─── Snap Constants & Bleed ──────────────────────────────────────────────────
const MIN_HEIGHT_PX = 200; // resting height showing handle, header, tabs, and search feature
const PEEK_RATIO = 0.50; // 50% of viewport (separuh layar)
const FULL_RATIO = 0.92; // 92% of viewport
const BOTTOM_BLEED_PX = 80; // extra extension below viewport to eliminate drag-up gap

// ─── Viewport Reactive State ─────────────────────────────────────────────────
const viewportH = ref(typeof window !== "undefined" ? window.innerHeight : 720);
const fullH = computed(() => Math.round(viewportH.value * FULL_RATIO));
const peekH = computed(() => Math.round(viewportH.value * PEEK_RATIO));
const minH = computed(() => MIN_HEIGHT_PX);

// Offsets from top of sheet:
// At full: translateY = 0
// At peek: translateY = fullH - peekH
// At min: translateY = fullH - minH
const snapOffsets = computed<Record<SnapLevel, number>>(() => ({
  full: 0,
  peek: Math.max(0, fullH.value - peekH.value),
  min: Math.max(0, fullH.value - minH.value),
}));

// ─── Reactive Snap & Drag State ──────────────────────────────────────────────
const snapLevel = ref<SnapLevel>(props.snap || "peek");
const isDragging = ref(false);
const dragStartY = ref(0);
const dragStartOffset = ref(0);
const currentDragOffset = ref(0);

// Velocity tracking for quick flick swipes
let dragLastY = 0;
let dragLastTime = 0;
let dragVelocity = 0; // px / ms (positive = moving down, negative = moving up)

// Content scroll container ref for pull-down detection
const scrollContainerRef = ref<HTMLElement | null>(null);

// ─── Target TranslateY Calculation ────────────────────────────────────────────
const sheetTranslateY = computed(() => {
  if (!props.open) {
    return fullH.value + BOTTOM_BLEED_PX + 20; // completely hidden below screen
  }

  if (isDragging.value) {
    const raw = currentDragOffset.value;
    const minOffset = snapOffsets.value.min;

    // Upward rubber-band dampening beyond full (raw < 0)
    if (raw < 0) {
      return raw * 0.22;
    }
    // Downward rubber-band dampening beyond min
    if (raw > minOffset) {
      const over = raw - minOffset;
      return minOffset + over * 0.35;
    }
    return raw;
  }

  return snapOffsets.value[snapLevel.value] ?? snapOffsets.value.peek;
});

const sheetStyle = computed(() => {
  return {
    height: `${fullH.value + BOTTOM_BLEED_PX}px`,
    bottom: `-${BOTTOM_BLEED_PX}px`,
    transform: `translate3d(0, ${sheetTranslateY.value}px, 0)`,
    transition: isDragging.value
      ? "none"
      : "transform 0.58s cubic-bezier(0.16, 1, 0.3, 1)",
  };
});

// ─── Drag Handling (Touch & Mouse) ───────────────────────────────────────────
function beginDrag(clientY: number) {
  isDragging.value = true;
  dragStartY.value = clientY;
  dragLastY = clientY;
  dragLastTime = performance.now();
  dragVelocity = 0;
  dragStartOffset.value = snapOffsets.value[snapLevel.value] ?? snapOffsets.value.peek;
  currentDragOffset.value = dragStartOffset.value;

  if (typeof window !== "undefined") {
    window.addEventListener("touchmove", onWindowTouchMove, { passive: false });
    window.addEventListener("touchend", onWindowTouchEnd);
    window.addEventListener("touchcancel", onWindowTouchEnd);
    window.addEventListener("mousemove", onWindowMouseMove);
    window.addEventListener("mouseup", onWindowMouseUp);
  }
}

function updateDrag(clientY: number) {
  if (!isDragging.value) return;

  const now = performance.now();
  const dt = now - dragLastTime;
  if (dt > 8) {
    dragVelocity = (clientY - dragLastY) / dt;
    dragLastY = clientY;
    dragLastTime = now;
  }

  const delta = clientY - dragStartY.value;
  currentDragOffset.value = dragStartOffset.value + delta;
}

function finishDrag() {
  if (!isDragging.value) return;
  isDragging.value = false;

  if (typeof window !== "undefined") {
    window.removeEventListener("touchmove", onWindowTouchMove);
    window.removeEventListener("touchend", onWindowTouchEnd);
    window.removeEventListener("touchcancel", onWindowTouchEnd);
    window.removeEventListener("mousemove", onWindowMouseMove);
    window.removeEventListener("mouseup", onWindowMouseUp);
  }

  const offset = currentDragOffset.value;
  const v = dragVelocity;
  const offsets = snapOffsets.value;

  // 1. High Velocity Flick Swipes
  if (v < -0.45) {
    // Quick flick UP
    if (snapLevel.value === "min") {
      snapTo(v < -0.9 ? "full" : "peek");
    } else if (snapLevel.value === "peek") {
      snapTo("full");
    } else {
      snapTo("full");
    }
    return;
  }

  if (v > 0.45) {
    // Quick flick DOWN
    if (snapLevel.value === "full") {
      snapTo("peek");
    } else if (snapLevel.value === "peek") {
      snapTo("min");
    } else if (snapLevel.value === "min") {
      snapTo("min");
    }
    return;
  }

  // 2. Slow drag: Nearest Snap Threshold Calculation
  // If dragged past min by > 55px, snap back to min
  if (offset > offsets.min + 55) {
    snapTo("min");
    return;
  }

  // Find snap level with closest distance
  const distFull = Math.abs(offset - offsets.full);
  const distPeek = Math.abs(offset - offsets.peek);
  const distMin = Math.abs(offset - offsets.min);

  if (distFull <= distPeek && distFull <= distMin) {
    snapTo("full");
  } else if (distPeek <= distMin) {
    snapTo("peek");
  } else {
    snapTo("min");
  }
}

// Window Event Listeners for smooth tracking
function onWindowTouchMove(e: TouchEvent) {
  if (!isDragging.value || !e.touches.length) return;
  // Prevent browser scroll or elastic bounce while dragging sheet
  if (e.cancelable) e.preventDefault();
  updateDrag(e.touches[0].clientY);
}

function onWindowTouchEnd() {
  finishDrag();
}

function onWindowMouseMove(e: MouseEvent) {
  if (!isDragging.value) return;
  updateDrag(e.clientY);
}

function onWindowMouseUp() {
  finishDrag();
}

// Direct touch start on header / handle bar
function handleHeaderTouchStart(e: TouchEvent) {
  if (e.touches.length === 1) {
    beginDrag(e.touches[0].clientY);
  }
}

function handleHeaderMouseDown(e: MouseEvent) {
  if (e.button === 0) {
    beginDrag(e.clientY);
  }
}

// ─── Touch & Drag Handling Across All Content & Tab Bar ─────────────────────
let contentTouchStartX = 0;
let contentTouchStartY = 0;
let isPullingFromTop = false;

function handleContentTouchStart(e: TouchEvent) {
  if (e.touches.length !== 1) return;
  contentTouchStartX = e.touches[0].clientX;
  contentTouchStartY = e.touches[0].clientY;
  isPullingFromTop = false;
}

function handleContentTouchMove(e: TouchEvent) {
  if (e.touches.length !== 1 || isDragging.value) return;

  const currentX = e.touches[0].clientX;
  const currentY = e.touches[0].clientY;
  const diffX = currentX - contentTouchStartX;
  const diffY = currentY - contentTouchStartY;
  const absDiffX = Math.abs(diffX);
  const absDiffY = Math.abs(diffY);

  // Require minimum 6px movement and vertical gesture priority
  if (absDiffY < 6 || absDiffY <= absDiffX) return;

  const scrollEl = scrollContainerRef.value;

  // 1. When NOT at full snap ('peek' or 'min'):
  // ANY vertical swipe (UP or DOWN) across ALL content directly drags the bottom sheet!
  if (snapLevel.value !== "full") {
    if (e.cancelable) e.preventDefault();
    beginDrag(currentY);
    return;
  }

  // 2. When at 'full' snap:
  // If scrolled to top and pulling DOWN, drag the sheet downwards to peek
  const isAtTop = !scrollEl || scrollEl.scrollTop <= 0;
  if (isAtTop && diffY > 8) {
    if (e.cancelable) e.preventDefault();
    isPullingFromTop = true;
    beginDrag(currentY);
  }
}

function handleContentMouseDown(e: MouseEvent) {
  if (e.button !== 0 || snapLevel.value === "full") return;
  // Don't intercept clicks on buttons, links, inputs, or interactive controls
  const target = e.target as HTMLElement | null;
  if (target && target.closest("button, a, input, select, textarea, [role='button'], [role='tab'], [role='radio']")) {
    return;
  }
  beginDrag(e.clientY);
}

// ─── Snap Actions ─────────────────────────────────────────────────────────────
function snapTo(level: SnapLevel) {
  snapLevel.value = level;
  emit("update:snap", level);
  if (level === "full") emit("expand");
  else if (level === "peek") emit("peek");
  else if (level === "min") emit("minimize");
}

function toggleSnapFromHandle() {
  if (snapLevel.value === "min") {
    snapTo("peek");
  } else if (snapLevel.value === "peek") {
    snapTo("full");
  } else {
    snapTo("peek");
  }
}

function close() {
  snapTo("min");
}


// Sync external prop changes
watch(
  () => props.snap,
  (newSnap) => {
    if (newSnap && newSnap !== snapLevel.value) {
      snapLevel.value = newSnap;
    }
  }
);

watch(
  () => props.open,
  (val) => {
    if (val && !props.snap) {
      snapLevel.value = "peek";
    }
  }
);

// Keyboard Accessibility (R-32)
function onKeydown(e: KeyboardEvent) {
  if (!props.open) return;

  if (e.key === "Escape") {
    e.preventDefault();
    if (snapLevel.value === "full") snapTo("peek");
    else snapTo("min");
  } else if (e.key === "ArrowUp") {
    // ArrowUp expands
    if (snapLevel.value === "min") {
      e.preventDefault();
      snapTo("peek");
    } else if (snapLevel.value === "peek") {
      e.preventDefault();
      snapTo("full");
    }
  } else if (e.key === "ArrowDown") {
    // ArrowDown minimizes
    if (snapLevel.value === "full") {
      e.preventDefault();
      snapTo("peek");
    } else if (snapLevel.value === "peek") {
      e.preventDefault();
      snapTo("min");
    }
  }
}

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
  if (typeof window !== "undefined") {
    window.removeEventListener("touchmove", onWindowTouchMove);
    window.removeEventListener("touchend", onWindowTouchEnd);
    window.removeEventListener("touchcancel", onWindowTouchEnd);
    window.removeEventListener("mousemove", onWindowMouseMove);
    window.removeEventListener("mouseup", onWindowMouseUp);
  }
});
</script>

<template>
  <Teleport to="body">

    <!-- ═══ 2. BOTTOM SHEET WRAPPER (Solid Full Base, Shifted with translateY) ══════ -->
    <Transition name="sheet-slide">
      <div
        v-if="open"
        role="dialog"
        :aria-label="title"
        :aria-modal="snapLevel === 'full'"
        class="fixed inset-x-0 z-[39] will-change-transform md:hidden select-none pointer-events-auto"
        :style="sheetStyle"
      >
      <!-- Main Sheet Container with Top Rounded Corners, Border & Depth Shadow -->
      <div
        class="flex flex-col h-full bg-white dark:bg-[#0b0f19] rounded-t-[24px] border-t border-l border-r border-slate-200/90 dark:border-white/10 shadow-[0_-10px_36px_rgba(15,23,42,0.18)] dark:shadow-[0_-12px_44px_rgba(0,0,0,0.5)] overflow-hidden"
      >
        <!-- ── Top Drag Zone (Handle + Header Area) ── -->
        <div
          class="shrink-0 touch-none cursor-grab active:cursor-grabbing border-b border-slate-100 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02]"
          @touchstart.passive="handleHeaderTouchStart"
          @mousedown="handleHeaderMouseDown"
        >
          <!-- ── Grab Handle Pill ── -->
          <div
            class="flex items-center justify-center pt-2.5 pb-1.5"
            @click.stop="toggleSnapFromHandle"
          >
            <div
              class="h-[4.5px] rounded-full transition-all duration-200"
              :class="[
                isDragging
                  ? 'w-12 bg-blue-600 dark:bg-blue-400 shadow-sm'
                  : 'w-10 bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500'
              ]"
            />
          </div>

          <!-- ── Sheet Header ── -->
          <header class="flex items-center gap-2.5 px-3.5 pb-2.5 pt-0.5">
            <!-- Header Icon / Slot -->
            <slot name="header-icon">
              <button
                type="button"
                class="size-8.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 shadow-xs cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
                :aria-label="snapLevel === 'min' ? 'Buka panel ke setengah layar' : 'Ikon panel'"
                @click.stop="snapLevel === 'min' ? snapTo('peek') : undefined"
              >
                <UIcon :name="icon" class="size-4.5 stroke-[2.25]" />
              </button>
            </slot>

            <!-- Title & Subtitle (Clickable to Expand when Minimized) -->
            <div
              class="flex-1 min-w-0 select-none py-0.5"
              :class="snapLevel === 'min' ? 'cursor-pointer' : ''"
              @click="snapLevel === 'min' ? snapTo('peek') : undefined"
            >
              <div class="flex items-center gap-1.5">
                <h2 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 truncate leading-none">
                  {{ title }}
                </h2>
                <!-- Minimal Badge when Minimized -->
                <span
                  v-if="snapLevel === 'min'"
                  class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300 shrink-0"
                >
                  Ketuk detail
                </span>
              </div>
              <p
                v-if="subtitle"
                class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 truncate font-medium mt-1"
              >
                {{ subtitle }}
              </p>
            </div>

            <!-- Extra Action Slot (e.g. Bagikan Peta) -->
            <div
              v-show="snapLevel !== 'min'"
              class="shrink-0 flex items-center"
              @click.stop
            >
              <slot name="header-actions" />
            </div>

            <!-- Snap Level Controls (Min 44x44px touch targets per antislop-layoutmobile R-03) -->
            <div class="shrink-0 flex items-center gap-0.5" @click.stop>
              <!-- When Minimized: Expand to Peek Button -->
              <button
                v-if="snapLevel === 'min'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
                aria-label="Buka panel ke setengah layar"
                title="Buka panel"
                @click="snapTo('peek')"
              >
                <UIcon name="i-lucide-chevron-up" class="size-5" />
              </button>

              <!-- When Peek: Maximize to Full Button -->
              <button
                v-if="snapLevel === 'peek'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
                aria-label="Perbesar layar penuh"
                title="Layar penuh"
                @click="snapTo('full')"
              >
                <UIcon name="i-lucide-maximize-2" class="size-4" />
              </button>

              <!-- When Full: Restore to Peek Button -->
              <button
                v-if="snapLevel === 'full'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
                aria-label="Perkecil panel ke setengah layar"
                title="Perkecil panel"
                @click="snapTo('peek')"
              >
                <UIcon name="i-lucide-minimize-2" class="size-4" />
              </button>

              <!-- Tutup (Minimize) Button: Menutup panel kembali ke posisi bar minimal 'min' -->
              <button
                v-if="snapLevel !== 'min'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
                aria-label="Kecilkan panel ke bar bawah"
                title="Kecilkan panel"
                @click="snapTo('min')"
              >
                <UIcon name="i-lucide-chevron-down" class="size-5" />
              </button>
            </div>
          </header>
        </div>

        <!-- ── Tab Bar Slot (Draggable when swiping, clickable when tapping) ── -->
        <div
          class="shrink-0"
          @touchstart.passive="handleContentTouchStart"
          @touchmove="handleContentTouchMove"
          @mousedown="handleContentMouseDown"
        >
          <slot name="tabs" />
        </div>

        <!-- ── Scrollable Content Body (Full Drag-Up on all content at min & peek) ── -->
        <div
          ref="scrollContainerRef"
          class="flex-1 min-h-0 flex flex-col"
          :class="snapLevel === 'full' ? 'overflow-y-auto overscroll-contain' : 'overflow-hidden'"
          @touchstart.passive="handleContentTouchStart"
          @touchmove="handleContentTouchMove"
          @mousedown="handleContentMouseDown"
        >
          <slot />
        </div>

        <!-- Bottom Safe Area Inset Spacer -->
        <div
          class="shrink-0 bg-white dark:bg-[#0b0f19]"
          style="height: calc(env(safe-area-inset-bottom, 0px) + 8px)"
        />
      </div>
    </div>
  </Transition>
  </Teleport>
</template>

<style scoped>

/* Bottom Sheet Slide Up & Down Transition */
.sheet-slide-enter-active {
  transition: transform 0.62s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.sheet-slide-enter-from {
  transform: translate3d(0, calc(100% + 100px), 0) !important;
}

.sheet-slide-leave-active {
  transition: transform 0.52s cubic-bezier(0.25, 1, 0.5, 1) !important;
}
.sheet-slide-leave-to {
  transform: translate3d(0, calc(100% + 100px), 0) !important;
}
</style>


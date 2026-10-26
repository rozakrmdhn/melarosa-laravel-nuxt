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
  (e: "backdrop-click"): void;
}>();

// ─── Snap Constants & Bleed ──────────────────────────────────────────────────
const MIN_HEIGHT_PX = 150; // resting height showing handle, tabs, and search feature
const PEEK_RATIO = 0.50; // 50% of viewport (separuh layar)
const FULL_RATIO = 0.92; // 92% of viewport
const BOTTOM_BLEED_PX = 120; // extra extension below viewport to eliminate drag-up gap and provide solid base

function getEffectiveViewportHeight() {
  if (typeof window === "undefined") return 720;
  return window.innerHeight;
}

// ─── Viewport & Keyboard Reactive State ──────────────────────────────────────
const viewportH = ref(getEffectiveViewportHeight());
const keyboardHeight = ref(0);
const fullH = computed(() => Math.round(viewportH.value * FULL_RATIO));
const peekH = computed(() => Math.round(viewportH.value * PEEK_RATIO));
const minH = computed(() => Math.min(MIN_HEIGHT_PX, Math.round(viewportH.value * 0.35)));

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
const sheetRef = ref<HTMLElement | null>(null);

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
let activeScrollableEl: HTMLElement | null = null;
let touchStartScrollTop = 0;

function findScrollableParent(target: HTMLElement | null, boundary: HTMLElement | null): HTMLElement | null {
  let curr = target;
  while (curr && curr !== boundary && curr !== document.body) {
    const style = window.getComputedStyle(curr);
    const overflowY = style.overflowY;
    if (
      (overflowY === "auto" || overflowY === "scroll") &&
      curr.scrollHeight > curr.clientHeight + 1
    ) {
      return curr;
    }
    curr = curr.parentElement;
  }
  if (boundary) {
    const style = window.getComputedStyle(boundary);
    const overflowY = style.overflowY;
    if (
      (overflowY === "auto" || overflowY === "scroll") &&
      boundary.scrollHeight > boundary.clientHeight + 1
    ) {
      return boundary;
    }
  }
  return null;
}

function handleContentTouchStart(e: TouchEvent) {
  if (e.touches.length !== 1) return;
  contentTouchStartX = e.touches[0].clientX;
  contentTouchStartY = e.touches[0].clientY;
  const target = e.target as HTMLElement | null;
  activeScrollableEl = findScrollableParent(target, scrollContainerRef.value);
  touchStartScrollTop = activeScrollableEl ? activeScrollableEl.scrollTop : 0;
}

function handleContentTouchMove(e: TouchEvent) {
  if (e.touches.length !== 1 || isDragging.value) return;

  const currentX = e.touches[0].clientX;
  const currentY = e.touches[0].clientY;
  const diffX = currentX - contentTouchStartX;
  const diffY = currentY - contentTouchStartY;
  const absDiffX = Math.abs(diffX);
  const absDiffY = Math.abs(diffY);

  // Require minimum vertical movement threshold and vertical direction priority
  if (absDiffY < 8 || absDiffY <= absDiffX) return;

  // Case 1: When sheet is minimized to resting bottom bar
  // Any upward swipe expands sheet to peek or full
  if (snapLevel.value === "min") {
    if (diffY < -8) {
      if (e.cancelable) e.preventDefault();
      beginDrag(currentY);
    }
    return;
  }

  // Case 2: When user is interacting inside a scrollable container
  if (activeScrollableEl) {
    const currentScrollTop = activeScrollableEl.scrollTop;

    // A. Swiping finger UP (diffY < 0): User wants to scroll content DOWN
    if (diffY < 0) {
      const isAtBottom =
        activeScrollableEl.scrollTop + activeScrollableEl.clientHeight >=
        activeScrollableEl.scrollHeight - 2;

      // If content has room to scroll down, prioritize natural content scrolling
      if (!isAtBottom) {
        return;
      }

      // If at peek and already reached the very bottom, drag up to expand to full
      if (snapLevel.value === "peek" && diffY < -24) {
        if (e.cancelable) e.preventDefault();
        beginDrag(currentY);
      }
      return;
    }

    // B. Swiping finger DOWN (diffY > 0): User wants to scroll content UP towards top
    if (diffY > 0) {
      // If content is scrolled down (> 2px), prioritize natural content scrolling back to top
      if (currentScrollTop > 2) {
        return;
      }

      // Only if the touch started while content was already at the top (<= 1)
      // AND user makes a deliberate downward pull (> 20px)
      if (touchStartScrollTop <= 1 && diffY > 20) {
        if (e.cancelable) e.preventDefault();
        beginDrag(currentY);
      }
      return;
    }
  }

  // Case 3: When touching non-scrollable area (e.g. tabs or container padding)
  if (!activeScrollableEl) {
    if (snapLevel.value === "peek") {
      if (diffY < -12 || diffY > 12) {
        if (e.cancelable) e.preventDefault();
        beginDrag(currentY);
      }
    } else if (snapLevel.value === "full") {
      if (diffY > 16) {
        if (e.cancelable) e.preventDefault();
        beginDrag(currentY);
      }
    }
  }
}

function handleContentMouseDown(e: MouseEvent) {
  if (e.button !== 0 || snapLevel.value === "full") return;
  const target = e.target as HTMLElement | null;
  if (target && target.closest("button, a, input, select, textarea, [role='button'], [role='tab'], [role='radio']")) {
    return;
  }
  if (findScrollableParent(target, scrollContainerRef.value)) {
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
  else if (level === "min") {
    // Tutup keyboard virtual agar sheet tidak tertinggal di atas keyboard
    const active = document.activeElement as HTMLElement | null;
    if (active && sheetRef.value?.contains(active)) active.blur();
    emit("minimize");
  }
}

// Fokus ke kolom teks (pencarian) baik dari mode default (min) maupun separuh layar (peek) membuka sheet secara penuh (full)
function handleSheetFocusIn(e: FocusEvent) {
  if (snapLevel.value === "full") return;
  const el = e.target as HTMLElement | null;
  if (!el) return;
  const isTextField =
    el.tagName === "TEXTAREA" ||
    (el.tagName === "INPUT" && !/^(checkbox|radio|range|button|submit|color|file)$/i.test((el as HTMLInputElement).type));
  if (isTextField) snapTo("full");
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

function handleBackdropClick() {
  snapTo("min");
  emit("backdrop-click");
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

// iOS Safari dan Android Chrome: pertahankan viewportH selalu berdasarkan window.innerHeight yang stabil agar ukuran full konsisten
function onResize() {
  if (typeof window === "undefined") return;
  viewportH.value = window.innerHeight;

  const vv = window.visualViewport;
  if (vv && vv.scale <= 1.01) {
    const inset = Math.round(window.innerHeight - vv.height - vv.offsetTop);
    keyboardHeight.value = inset > 100 ? inset : 0;
  } else {
    keyboardHeight.value = 0;
  }
}

function lockWindowScroll() {
  if (typeof window !== "undefined" && (window.scrollY !== 0 || window.scrollX !== 0)) {
    window.scrollTo(0, 0);
  }
}

onMounted(() => {
  document.addEventListener("keydown", onKeydown);
  window.addEventListener("resize", onResize, { passive: true });
  window.addEventListener("orientationchange", onResize, { passive: true });
  window.addEventListener("scroll", lockWindowScroll, { passive: true });
  if (typeof window !== "undefined" && window.visualViewport) {
    window.visualViewport.addEventListener("resize", onResize, { passive: true });
    window.visualViewport.addEventListener("scroll", onResize, { passive: true });
  }
});

onUnmounted(() => {
  document.removeEventListener("keydown", onKeydown);
  window.removeEventListener("resize", onResize);
  window.removeEventListener("orientationchange", onResize);
  window.removeEventListener("scroll", lockWindowScroll);
  if (typeof window !== "undefined" && window.visualViewport) {
    window.visualViewport.removeEventListener("resize", onResize);
    window.visualViewport.removeEventListener("scroll", onResize);
  }
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
        ref="sheetRef"
        role="dialog"
        :aria-label="title"
        :aria-modal="snapLevel === 'full'"
        class="fixed inset-x-0 z-[39] will-change-transform md:hidden select-none pointer-events-auto"
        :style="sheetStyle"
        @focusin="handleSheetFocusIn"
      >
      <!-- Main Sheet Container with Top Rounded Corners, Border & Depth Shadow (Solid Background) -->
      <div
        class="flex flex-col h-full bg-white dark:bg-[#0b0f19] rounded-t-[24px] border-t border-l border-r border-slate-200/80 dark:border-white/10 shadow-[0_-10px_36px_rgba(15,23,42,0.18)] dark:shadow-[0_-12px_44px_rgba(0,0,0,0.5)] overflow-hidden"
      >
        <!-- ── Top Drag Zone (Grab Handle Pill) ── -->
        <div
          class="shrink-0 touch-none cursor-grab active:cursor-grabbing flex items-center justify-center pt-3 pb-2 select-none"
          @touchstart.passive="handleHeaderTouchStart"
          @mousedown="handleHeaderMouseDown"
          @click.stop="toggleSnapFromHandle"
        >
          <div
            class="h-[4.5px] rounded-full transition-all duration-200 cursor-pointer"
            :class="[
              isDragging
                ? 'w-12 bg-blue-600 dark:bg-blue-400 shadow-sm'
                : 'w-10 bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500'
            ]"
          />
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

        <!-- ── Content Body Container (Responsive Internal Clearance for Virtual Keyboard) ── -->
        <div
          ref="scrollContainerRef"
          class="flex-1 min-h-0 flex flex-col overflow-hidden transition-[padding-bottom] duration-200"
          :style="{
            paddingBottom: keyboardHeight > 0 ? `${keyboardHeight}px` : '0px'
          }"
          @touchstart.passive="handleContentTouchStart"
          @touchmove="handleContentTouchMove"
          @mousedown="handleContentMouseDown"
        >
          <slot />
        </div>

        <!-- Bottom Safe Area Inset Spacer (Solid background to anchor sheet base firmly to the bottom) -->
        <div
          class="shrink-0 transition-[height] duration-200 bg-white dark:bg-[#0b0f19]"
          :style="{
            height: snapLevel === 'full'
              ? `${BOTTOM_BLEED_PX}px`
              : `calc(${BOTTOM_BLEED_PX}px + env(safe-area-inset-bottom, 0px))`
          }"
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

.sheet-fade-enter-active,
.sheet-fade-leave-active {
  transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.sheet-fade-enter-from,
.sheet-fade-leave-to {
  opacity: 0;
}
</style>


<script setup lang="ts">
// Native-feel mobile bottom sheet with drag gestures and snap points for the /maps interface.
// Snap levels:
// - MIN (~72px): compact floating pill for non-intrusive map exploration (single-click).
// - PEEK (48% vh): shows quick basemap & active layer controls while keeping the upper map visible.
// - FULL (92% vh): expands for deep exploration (28 kecamatan list, full metadata, etc.).
// Swipe down past threshold at PEEK minimizes to MIN. Swipe up from PEEK expands to FULL.

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

// ─── Snap Constants ───────────────────────────────────────────────────────────
const MIN_PX = 72; // ~72px mini-bar height
const PEEK_VH = 0.48; // 48% of vh
const FULL_VH = 0.92; // 92% of vh

// ─── Reactive State ───────────────────────────────────────────────────────────
const snapLevel = ref<SnapLevel>(props.snap || "peek");
const isDragging = ref(false);
const dragStartY = ref(0);
const dragDelta = ref(0); // positive = dragged down, negative = dragged up

// Computed heights based on dynamic window innerHeight
const viewportH = ref(typeof window !== "undefined" ? window.innerHeight : 700);
const peekH = computed(() => Math.round(viewportH.value * PEEK_VH));
const fullH = computed(() => Math.round(viewportH.value * FULL_VH));

const currentBaseH = computed(() => {
  if (snapLevel.value === "min") return MIN_PX;
  if (snapLevel.value === "full") return fullH.value;
  return peekH.value;
});

// Live translateY during drag with rubber-banding resistance
const liveTranslateY = computed(() => {
  if (!isDragging.value) return 0;
  const raw = dragDelta.value;
  if (snapLevel.value === "full" && raw < 0) return raw * 0.2; // rubber band upwards
  if (snapLevel.value === "min" && raw > 0) return raw * 0.35; // rubber band downwards
  return raw;
});

const currentTranslateY = computed(() => Math.max(0, liveTranslateY.value));

const sheetStyle = computed(() => {
  const h =
    snapLevel.value === "min"
      ? "calc(72px + env(safe-area-inset-bottom, 0px))"
      : `${currentBaseH.value}px`;

  return {
    height: h,
    transform: `translateY(${liveTranslateY.value}px)`,
    "--sheet-current-y": `${currentTranslateY.value}px`,
    transition: isDragging.value
      ? "none"
      : "transform 0.34s cubic-bezier(0.32, 0.72, 0, 1), height 0.34s cubic-bezier(0.32, 0.72, 0, 1)",
  };
});

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

  if (snapLevel.value === "min") {
    if (delta < -36) {
      snapTo("peek");
    } else if (delta > 56) {
      close();
    }
  } else if (snapLevel.value === "peek") {
    if (delta > 56) {
      snapTo("min");
    } else if (delta < -48) {
      snapTo("full");
    }
  } else if (snapLevel.value === "full") {
    if (delta > 56) {
      snapTo("peek");
    }
  }
}

// ─── Snap Actions ─────────────────────────────────────────────────────────────

function snapTo(level: SnapLevel) {
  snapLevel.value = level;
  emit("update:snap", level);
  if (level === "full") emit("expand");
  else if (level === "peek") emit("peek");
  else if (level === "min") emit("minimize");
}

function close() {
  emit("update:open", false);
}

function handleScrimClick() {
  if (snapLevel.value === "full") {
    snapTo("peek");
  } else {
    close();
  }
}

// Sync external snap changes
watch(
  () => props.snap,
  (newSnap) => {
    if (newSnap && newSnap !== snapLevel.value) {
      snapLevel.value = newSnap;
    }
  }
);

// Reset snap level when opened if not specified
watch(
  () => props.open,
  (val) => {
    if (val && !props.snap) {
      snapLevel.value = "peek";
    }
  }
);

// Keyboard accessibility: Escape drops levels or closes
function onKeydown(e: KeyboardEvent) {
  if (e.key === "Escape" && props.open) {
    e.preventDefault();
    if (snapLevel.value === "full") {
      snapTo("peek");
    } else if (snapLevel.value === "peek") {
      snapTo("min");
    } else {
      close();
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
});
</script>

<template>
  <Teleport to="body">
    <!-- ═══ 1. SCRIM / BACKDROP (Only active at FULL to keep map clickable at MIN & PEEK) ═════════════════════════ -->
    <Transition name="scrim">
      <div
        v-if="open && snapLevel === 'full'"
        class="fixed inset-0 z-[38] bg-black/50 backdrop-blur-[2px] transition-colors duration-300 md:hidden"
        aria-hidden="true"
        @click="handleScrimClick"
      />
    </Transition>

    <!-- ═══ 2. BOTTOM SHEET WRAPPER ══════════════════════════════════════════════ -->
    <Transition name="bottom-sheet">
      <div
        v-if="open"
        class="fixed bottom-0 left-0 right-0 z-[39] will-change-transform md:hidden"
        :style="sheetStyle"
        @touchmove.passive="onDrag"
        @touchend="endDrag"
        @mousemove="onDrag"
        @mouseup="endDrag"
        @mouseleave="endDrag"
      >
        <!-- Inner Container with Rounded Top Corners & Shadow -->
        <div
          role="region"
          :aria-label="title"
          class="flex flex-col h-full bg-white dark:bg-[#0b0f19] rounded-t-[24px] overflow-hidden border-t border-l border-r border-slate-200/80 dark:border-white/10 shadow-[0_-8px_32px_rgba(0,0,0,0.2)] select-none"
        >
          <!-- ── Drag Handle Zone ── -->
          <div
            class="shrink-0 flex flex-col items-center gap-0.5 pt-2 pb-1 touch-none cursor-grab active:cursor-grabbing"
            @touchstart.passive="startDrag"
            @mousedown.prevent="startDrag"
            @click="snapLevel === 'min' ? snapTo('peek') : undefined"
          >
            <!-- Pill Grab Handle -->
            <div
              class="w-10 h-[5px] rounded-full transition-colors duration-150"
              :class="
                isDragging
                  ? 'bg-blue-500 dark:bg-blue-400'
                  : 'bg-slate-300 dark:bg-slate-600'
              "
            />
          </div>

          <!-- ── Sheet Header ── -->
          <header
            class="shrink-0 flex items-center gap-2.5 px-3.5 pt-0.5 pb-2 border-b border-slate-100 dark:border-white/10"
          >
            <!-- Header Icon / Slot -->
            <slot name="header-icon">
              <button
                type="button"
                class="size-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 shadow-xs cursor-pointer focus:outline-hidden"
                :aria-label="snapLevel === 'min' ? 'Buka detail panel' : 'Ikon panel'"
                @click="snapLevel === 'min' ? snapTo('peek') : undefined"
              >
                <UIcon :name="icon" class="size-4.5 stroke-[2.25]" />
              </button>
            </slot>

            <!-- Title & Subtitle clickable when minimized to expand -->
            <div
              class="flex-1 min-w-0 py-0.5 select-none"
              :class="snapLevel === 'min' ? 'cursor-pointer' : ''"
              @click="snapLevel === 'min' ? snapTo('peek') : undefined"
            >
              <div class="flex items-center gap-1.5">
                <h2
                  class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 truncate leading-none"
                >
                  {{ title }}
                </h2>
                <!-- Minimal Badge when minimized -->
                <span
                  v-if="snapLevel === 'min'"
                  class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300 shrink-0"
                >
                  Ketuk detail
                </span>
              </div>
              <p
                v-if="subtitle"
                class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 truncate font-medium mt-1 font-mono"
              >
                {{ subtitle }}
              </p>
            </div>

            <!-- Extra Action Slot (e.g. + Katalog button) - only visible when expanded -->
            <div v-show="snapLevel !== 'min'" class="shrink-0 flex items-center">
              <slot name="header-actions" />
            </div>

            <!-- Snap Level Controls (Min 44x44px tap targets per antislop-layoutmobile) -->
            <div class="shrink-0 flex items-center gap-0.5">
              <!-- When Minimized: Expand to Peek Button -->
              <button
                v-if="snapLevel === 'min'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer"
                aria-label="Buka panel ke setengah layar"
                @click.stop="snapTo('peek')"
              >
                <UIcon name="i-lucide-chevron-up" class="size-5" />
              </button>

              <!-- When Peek: Minimize to Min Button -->
              <button
                v-if="snapLevel === 'peek'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer"
                aria-label="Kecilkan panel bawah"
                @click.stop="snapTo('min')"
              >
                <UIcon name="i-lucide-chevron-down" class="size-4.5" />
              </button>

              <!-- When Peek: Maximize to Full Button -->
              <button
                v-if="snapLevel === 'peek'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer"
                aria-label="Perbesar penuh layar"
                @click.stop="snapTo('full')"
              >
                <UIcon name="i-lucide-maximize-2" class="size-4" />
              </button>

              <!-- When Full: Restore to Peek Button -->
              <button
                v-if="snapLevel === 'full'"
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer"
                aria-label="Perkecil panel ke setengah layar"
                @click.stop="snapTo('peek')"
              >
                <UIcon name="i-lucide-minimize-2" class="size-4" />
              </button>

              <!-- Close Button (Always accessible with min 44x44px target) -->
              <button
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 active:scale-95 transition-all cursor-pointer"
                aria-label="Tutup panel bawah"
                @click.stop="close"
              >
                <UIcon name="i-lucide-x" class="size-4.5" />
              </button>
            </div>
          </header>

          <!-- ── Tab Bar Slot (if provided) ── -->
          <div :aria-hidden="snapLevel === 'min'" :tabindex="snapLevel === 'min' ? -1 : undefined">
            <slot name="tabs" />
          </div>

          <!-- ── Content Body (Scrollable) ── -->
          <div
            class="flex-1 overflow-y-auto overscroll-contain min-h-0 flex flex-col"
            :aria-hidden="snapLevel === 'min'"
            :tabindex="snapLevel === 'min' ? -1 : undefined"
          >
            <slot />
          </div>

          <!-- Safe area spacer for mobile devices -->
          <div
            class="shrink-0"
            style="height: env(safe-area-inset-bottom, 0px)"
          />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Scrim Backdrop Transition */
.scrim-enter-active,
.scrim-leave-active {
  transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.scrim-enter-from,
.scrim-leave-to {
  opacity: 0;
}

/* Bottom Sheet Slide Up & Down Transition */
.bottom-sheet-enter-active {
  transition: transform 0.36s cubic-bezier(0.32, 0.72, 0, 1) !important;
}
.bottom-sheet-enter-from {
  transform: translateY(100%) !important;
}
.bottom-sheet-enter-to {
  transform: translateY(0) !important;
}

.bottom-sheet-leave-active {
  transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1) !important;
}
.bottom-sheet-leave-from {
  transform: translateY(var(--sheet-current-y, 0px)) !important;
}
.bottom-sheet-leave-to {
  transform: translateY(100%) !important;
}
</style>

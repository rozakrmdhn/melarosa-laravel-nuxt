<script setup lang="ts">
// ─── Dedicated Mobile Bottom Sheet for Basemap Selection ─────────────────────
// Features:
// - Height: opens at ~50% vh by default, expandable to full (~88% vh)
// - Fluid touch & mouse drag gestures with velocity flick detection
// - Swipe down to dismiss with damping
// - Instant visual radio selection with haptic/visual check feedback
// - WCAG AA compliant contrast, min 44x44px touch targets, full safe-area support
// - Accessible keyboard controls (Escape to close, radiogroup navigation)

import type { BasemapKey } from "~/types/map-layers";
import type { BasemapItem } from "~/types/basemap";
import BasemapThumbnail from "~/components/common/BasemapThumbnail.vue";
import { useBasemapStore } from "~/stores/basemap";

export interface BasemapDef {
  key: BasemapKey;
  name: string;
  tagline: string;
  provider: string;
  type: "vector" | "raster";
  item?: BasemapItem;
}

const props = withDefaults(
  defineProps<{
    open?: boolean;
    currentBasemap?: BasemapKey;
  }>(),
  {
    open: false,
    currentBasemap: "esri",
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
  (e: "update:currentBasemap", val: BasemapKey): void;
  (e: "select", key: BasemapKey): void;
  (e: "after-close"): void;
}>();

const basemapStore = useBasemapStore();

const basemapList = computed<BasemapDef[]>(() => {
  const dynamicItems = basemapStore.listForEngine("maplibre").map((item) => ({
    key: item.kode as BasemapKey,
    name: item.nama,
    tagline: item.tagline || item.provider || "Peta dasar dinamis",
    provider: item.provider || "Custom Tile",
    type: (item.tipe === "vector" ? "vector" : "raster") as "vector" | "raster",
    item,
  }));

  dynamicItems.push({
    key: "none",
    name: "Kanvas Kosong",
    tagline: "Tanpa peta dasar untuk fokus analisis layer",
    provider: "Internal",
    type: "raster",
    item: {
      id: "none",
      kode: "none",
      nama: "Kanvas Kosong",
      label_singkat: "Kanvas",
      tagline: "Tanpa peta dasar",
      provider: "Internal",
      tipe: "raster",
      url_template: "",
      fallback_url_template: null,
      tile_urls: null,
      attribution: null,
      thumbnail_url: null,
      preview_style: "none",
      min_zoom: 0,
      max_zoom: 22,
      tile_size: 256,
      cross_origin: "anonymous",
      supports_openlayers: true,
      supports_maplibre: true,
      is_default: false,
      is_active: true,
      sort_order: 999,
    },
  });

  return dynamicItems;
});

// ─── Snap Constants & Dimensions ─────────────────────────────────────────────
const HALF_RATIO = 0.54; // Separuh layar (~54% viewport)
const FULL_RATIO = 0.88; // Layar penuh (~88% viewport)
const BOTTOM_BLEED_PX = 80;

function getEffectiveViewportHeight() {
  if (typeof window === "undefined") return 720;
  if (window.visualViewport) {
    return Math.round(window.visualViewport.height);
  }
  return window.innerHeight;
}

const viewportH = ref(getEffectiveViewportHeight());
const fullH = computed(() => Math.round(viewportH.value * FULL_RATIO));
const halfH = computed(() => Math.round(viewportH.value * HALF_RATIO));

type SheetSnap = "half" | "full";
const snapOffsets = computed<Record<SheetSnap, number>>(() => ({
  full: 0,
  half: Math.max(0, fullH.value - halfH.value),
}));

const snapLevel = ref<SheetSnap>("half");
const isDragging = ref(false);
const dragStartY = ref(0);
const dragStartOffset = ref(0);
const currentDragOffset = ref(0);

let dragLastY = 0;
let dragLastTime = 0;
let dragVelocity = 0;

const scrollContainerRef = ref<HTMLElement | null>(null);

// ─── TranslateY Calculation ──────────────────────────────────────────────────
const sheetTranslateY = computed(() => {
  if (!props.open) {
    return fullH.value + BOTTOM_BLEED_PX + 30; // completely hidden below viewport
  }

  if (isDragging.value) {
    const raw = currentDragOffset.value;

    // Upward rubber-band dampening beyond full (raw < 0)
    if (raw < 0) {
      return raw * 0.22;
    }
    // Downward drag past half follows finger 1:1 for natural pull-to-dismiss
    return raw;
  }

  return snapOffsets.value[snapLevel.value] ?? snapOffsets.value.half;
});

const sheetStyle = computed(() => ({
  height: `${fullH.value + BOTTOM_BLEED_PX}px`,
  bottom: `-${BOTTOM_BLEED_PX}px`,
  transform: `translate3d(0, ${sheetTranslateY.value}px, 0)`,
  transition: isDragging.value
    ? "none"
    : "transform 0.58s cubic-bezier(0.16, 1, 0.3, 1)",
}));

// ─── Drag Handling (Touch & Mouse) ───────────────────────────────────────────
function beginDrag(clientY: number) {
  isDragging.value = true;
  dragStartY.value = clientY;
  dragLastY = clientY;
  dragLastTime = performance.now();
  dragVelocity = 0;
  dragStartOffset.value = snapOffsets.value[snapLevel.value] ?? snapOffsets.value.half;
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

let wasDragging = false;

function finishDrag() {
  if (!isDragging.value) return;
  isDragging.value = false;
  wasDragging = true;
  setTimeout(() => {
    wasDragging = false;
  }, 140);

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
  if (v < -0.42) {
    snapTo("full");
    return;
  }

  if (v > 0.42) {
    if (snapLevel.value === "full") {
      snapTo("half");
    } else {
      close();
    }
    return;
  }

  // 2. Slow Drag Threshold: If dragged downwards past half by > 55px, dismiss
  if (offset > offsets.half + 55) {
    close();
    return;
  }

  const distFull = Math.abs(offset - offsets.full);
  const distHalf = Math.abs(offset - offsets.half);

  if (distFull < distHalf) {
    snapTo("full");
  } else {
    snapTo("half");
  }
}

function onWindowTouchMove(e: TouchEvent) {
  if (!isDragging.value || !e.touches.length) return;
  const touch = e.touches[0];
  if (!touch) return;
  if (e.cancelable) e.preventDefault();
  updateDrag(touch.clientY);
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

// ─── Touch & Drag Handling Across All Content & Basemap Cards ────────────────
let contentTouchStartX = 0;
let contentTouchStartY = 0;

function handleContentTouchStart(e: TouchEvent) {
  if (e.touches.length !== 1) return;
  const touch = e.touches[0];
  if (!touch) return;
  contentTouchStartX = touch.clientX;
  contentTouchStartY = touch.clientY;
}

function handleContentTouchMove(e: TouchEvent) {
  if (e.touches.length !== 1 || isDragging.value) return;
  const touch = e.touches[0];
  if (!touch) return;

  const currentX = touch.clientX;
  const currentY = touch.clientY;
  const diffX = currentX - contentTouchStartX;
  const diffY = currentY - contentTouchStartY;
  const absDiffX = Math.abs(diffX);
  const absDiffY = Math.abs(diffY);

  // Require minimum 6px movement and vertical gesture priority
  if (absDiffY < 6 || absDiffY <= absDiffX) return;

  const scrollEl = scrollContainerRef.value;
  const isAtTop = !scrollEl || scrollEl.scrollTop <= 0;

  // 1. Swiping DOWN when at top of content:
  // Directly drags the bottom sheet downwards to dismiss!
  if (diffY > 0 && isAtTop) {
    if (e.cancelable) e.preventDefault();
    beginDrag(currentY);
    return;
  }

  // 2. Swiping UP when NOT at full snap:
  // Directly drags the bottom sheet upwards to full!
  if (diffY < 0 && snapLevel.value !== "full") {
    if (e.cancelable) e.preventDefault();
    beginDrag(currentY);
    return;
  }
}

function handleContentMouseDown(e: MouseEvent) {
  if (e.button !== 0 || snapLevel.value === "full") return;
  const target = e.target as HTMLElement | null;
  if (target && target.closest("button, a, input, select, textarea, [role='button'], [role='radio'], [role='tab']")) {
    return;
  }
  beginDrag(e.clientY);
}

function toggleSnapFromHandle() {
  if (snapLevel.value === "half") {
    snapTo("full");
  } else {
    snapTo("half");
  }
}

function snapTo(level: SheetSnap) {
  snapLevel.value = level;
}

function close() {
  emit("update:open", false);
}

// ─── Basemap Selection Handler ───────────────────────────────────────────────
const lastSelectedKey = ref<BasemapKey | null>(null);

function handleSelectBasemap(key: BasemapKey) {
  if (isDragging.value || wasDragging) return;
  lastSelectedKey.value = key;
  emit("update:currentBasemap", key);
  emit("select", key);

  // Subtle auto-dismiss after 260ms to let user perceive selection confirmation
  setTimeout(() => {
    close();
  }, 260);
}

// ─── Lifecycle & Escape/Arrow Handler ─────────────────────────────────────────
function handleKeyDown(e: KeyboardEvent) {
  if (!props.open) return;
  if (e.key === "Escape") {
    e.preventDefault();
    if (snapLevel.value === "full") {
      snapTo("half");
    } else {
      close();
    }
  } else if (e.key === "ArrowUp") {
    if (snapLevel.value === "half") {
      e.preventDefault();
      snapTo("full");
    }
  } else if (e.key === "ArrowDown") {
    if (snapLevel.value === "full") {
      e.preventDefault();
      snapTo("half");
    } else {
      e.preventDefault();
      close();
    }
  }
}

function updateViewportH() {
  viewportH.value = getEffectiveViewportHeight();
}

watch(
  () => props.open,
  (val) => {
    if (val) {
      snapLevel.value = "half";
      updateViewportH();
    }
  }
);

const sheetContainerRef = ref<HTMLElement | null>(null);

function handleDocumentClick(e: PointerEvent) {
  if (!props.open) return;
  const target = e.target as HTMLElement | null;
  if (!target) return;
  if (sheetContainerRef.value && sheetContainerRef.value.contains(target)) {
    return;
  }
  if (target.closest && target.closest("[data-basemap-toggle]")) {
    return;
  }
  close();
}

onMounted(() => {
  void basemapStore.ensureLoaded();
  if (typeof window !== "undefined") {
    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("resize", updateViewportH, { passive: true });
    window.addEventListener("orientationchange", updateViewportH, { passive: true });
    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", updateViewportH, { passive: true });
      window.visualViewport.addEventListener("scroll", updateViewportH, { passive: true });
    }
    window.addEventListener("pointerdown", handleDocumentClick);
  }
});

onUnmounted(() => {
  if (typeof window !== "undefined") {
    window.removeEventListener("keydown", handleKeyDown);
    window.removeEventListener("resize", updateViewportH);
    window.removeEventListener("orientationchange", updateViewportH);
    if (window.visualViewport) {
      window.visualViewport.removeEventListener("resize", updateViewportH);
      window.visualViewport.removeEventListener("scroll", updateViewportH);
    }
    window.removeEventListener("pointerdown", handleDocumentClick);
  }
});
</script>

<template>
  <Teleport to="body">
    <!-- ═══ SCRIM / BACKDROP (Active at FULL snap, subtle and clickable to restore to half) ═══ -->
    <Transition name="scrim">
      <div
        v-if="open && snapLevel === 'full'"
        class="fixed inset-0 z-[49] bg-black/40 dark:bg-black/60 backdrop-blur-[2px] transition-opacity duration-300 md:hidden pointer-events-auto"
        aria-hidden="true"
        @click="snapTo('half')"
      />
    </Transition>

    <!-- ═══ BOTTOM SHEET CONTAINER (Sheet-Slide Transition) ═══ -->
    <Transition name="sheet-slide" @after-leave="emit('after-close')">
      <div
        v-if="open"
        class="fixed inset-x-0 z-50 pointer-events-none flex flex-col justify-end select-none will-change-transform md:hidden"
        :style="sheetStyle"
      >
        <div
          ref="sheetContainerRef"
          class="pointer-events-auto relative w-full h-full flex flex-col rounded-t-[28px] bg-white/92 dark:bg-[#0b1322]/92 backdrop-blur-2xl border-t border-slate-200/80 dark:border-white/10 shadow-[0_-12px_40px_rgba(0,0,0,0.22)] overflow-hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Pilihan Peta Dasar (Basemap)"
        >
          <!-- ═══ TOP DRAG ZONE (Handle + Header Area) ═══ -->
          <div
            class="shrink-0 select-none touch-none cursor-grab active:cursor-grabbing border-b border-slate-100 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02]"
            @touchstart.passive="handleHeaderTouchStart"
            @mousedown="handleHeaderMouseDown"
          >
            <!-- ── Grab Handle Pill ── -->
            <div
              class="flex items-center justify-center pt-2.5 pb-1.5 cursor-pointer"
              @click.stop="toggleSnapFromHandle"
            >
              <div
                class="h-[4.5px] rounded-full transition-all duration-200"
                :class="[
                  isDragging
                    ? 'w-12 bg-blue-600 dark:bg-blue-400 shadow-sm'
                    : 'w-10 bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500'
                ]"
                aria-hidden="true"
              />
            </div>

            <!-- ── Header Section ── -->
            <div class="px-4 pb-3 pt-0.5 flex items-center justify-between">
              <div
                class="flex items-center gap-2.5 min-w-0 flex-1 cursor-pointer"
                @click="toggleSnapFromHandle"
              >
                <div class="size-8.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 shadow-xs">
                  <UIcon name="i-lucide-map" class="size-4.5 stroke-[2.25]" />
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 truncate tracking-tight">
                      Peta Dasar (Basemap)
                    </h2>
                    <span class="px-1.5 py-0.2 rounded-full text-xs font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300">
                      {{ basemapList.length }} Pilihan
                    </span>
                  </div>
                  <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                    Pilih gaya peta dasar untuk eksplorasi wilayah
                  </p>
                </div>
              </div>

              <!-- Close Button (Min 44x44px touch target) -->
              <button
                type="button"
                class="size-11 rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-95 shrink-0"
                aria-label="Tutup Pilihan Peta Dasar"
                @click.stop="close"
              >
                <UIcon name="i-lucide-x" class="size-5" />
              </button>
            </div>
          </div>

          <!-- ═══ SCROLLABLE CONTENT BODY: BASEMAP CARDS GRID ═══ -->
          <div
            ref="scrollContainerRef"
            class="flex-1 overflow-y-auto overscroll-contain p-3.5 sm:p-4 min-h-0 select-none"
            role="radiogroup"
            aria-label="Daftar Peta Dasar"
            @touchstart.passive="handleContentTouchStart"
            @touchmove="handleContentTouchMove"
            @mousedown="handleContentMouseDown"
          >
          <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
            <button
              v-for="item in basemapList"
              :key="item.key"
              type="button"
              role="radio"
              :aria-checked="currentBasemap === item.key"
              class="group relative flex flex-col p-2 rounded-2xl border text-left transition-all duration-200 cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-98 select-none"
              :class="currentBasemap === item.key
                ? 'bg-blue-50/70 dark:bg-blue-950/40 border-blue-500 ring-2 ring-blue-500/25 shadow-md shadow-blue-500/5'
                : 'bg-white dark:bg-white/[0.03] border-slate-200/90 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/20 shadow-xs'"
              @click="handleSelectBasemap(item.key)"
            >
              <!-- Card Thumbnail Visual Preview -->
              <div class="relative w-full aspect-16/10 rounded-xl overflow-hidden border border-slate-200/60 dark:border-white/10 mb-2 shadow-xs">
                <BasemapThumbnail
                  :preview-style="item.item?.preview_style || item.key"
                  :thumbnail-url="item.item?.thumbnail_url"
                  :alt="item.name"
                  class="w-full h-full"
                />

                <!-- Top Badges Overlay -->
                <div class="absolute top-1.5 inset-x-1.5 flex items-center justify-between pointer-events-none">
                  <span
                    class="px-1.5 py-0.2 rounded-md text-xs font-bold tracking-tight uppercase shadow-xs backdrop-blur-xs"
                    :class="item.type === 'vector'
                      ? 'bg-emerald-600/90 text-white'
                      : 'bg-slate-900/80 text-white'"
                  >
                    {{ item.type }}
                  </span>

                  <!-- Active Checkmark Indicator -->
                  <div
                    v-if="currentBasemap === item.key"
                    class="size-5 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-md ring-2 ring-white dark:ring-[#0b1322]"
                  >
                    <UIcon name="i-lucide-check" class="size-3.5 stroke-[3]" />
                  </div>
                </div>
              </div>

              <!-- Basemap Metadata & Info -->
              <div class="flex flex-col min-w-0 pr-0.5">
                <span
                  class="text-xs font-bold truncate leading-tight transition-colors"
                  :class="currentBasemap === item.key
                    ? 'text-blue-600 dark:text-blue-400'
                    : 'text-slate-900 dark:text-slate-100'"
                >
                  {{ item.name }}
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5 leading-tight">
                  {{ item.tagline }}
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium truncate mt-1">
                  {{ item.provider }}
                </span>
              </div>
            </button>
          </div>

          <!-- Safe area spacer counteracting bottom bleed so cards are 100% visible above URL bar -->
          <div
            class="shrink-0"
            :style="{ height: `calc(env(safe-area-inset-bottom, 0px) + ${BOTTOM_BLEED_PX}px + 20px)` }"
          />
        </div>
      </div>
    </div>
  </Transition>
</Teleport>
</template>

<style scoped>
/* Bottom Sheet Slide Up & Down Transition */
.sheet-slide-enter-active {
  transition: transform 0.58s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.sheet-slide-enter-from {
  transform: translate3d(0, calc(100% + 100px), 0) !important;
}

.sheet-slide-leave-active {
  transition: transform 0.48s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.sheet-slide-leave-to {
  transform: translate3d(0, calc(100% + 100px), 0) !important;
}

/* Scrim Fade Transition */
.scrim-enter-active,
.scrim-leave-active {
  transition: opacity 0.3s ease;
}
.scrim-enter-from,
.scrim-leave-to {
  opacity: 0;
}
</style>

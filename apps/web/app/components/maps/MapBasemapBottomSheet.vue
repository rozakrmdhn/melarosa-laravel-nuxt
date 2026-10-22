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

export interface BasemapDef {
  key: BasemapKey;
  name: string;
  tagline: string;
  provider: string;
  type: "vector" | "raster";
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

const basemapList: BasemapDef[] = [
  {
    key: "esri",
    name: "Esri World Topo",
    tagline: "Topografi, elevasi & kontur muka bumi",
    provider: "Esri / ArcGIS",
    type: "raster",
  },
  {
    key: "rbi",
    name: "Rupabumi Indonesia",
    tagline: "Peta toponimi resmi standar nasional BIG",
    provider: "Badan Informasi Geospasial",
    type: "raster",
  },
  {
    key: "osm",
    name: "OpenStreetMap",
    tagline: "Peta jalan komunitas global detail",
    provider: "OSM Community",
    type: "vector",
  },
  {
    key: "satellite",
    name: "Citra Satelit",
    tagline: "Citra satelit resolusi tinggi permukaan bumi",
    provider: "World Imagery",
    type: "raster",
  },
  {
    key: "aerial",
    name: "Foto Udara",
    tagline: "Ortofoto udara resolusi tinggi Bojonegoro",
    provider: "Pemkab Bojonegoro",
    type: "raster",
  },
];

// ─── Snap Constants & Dimensions ─────────────────────────────────────────────
const HALF_RATIO = 0.54; // Separuh layar (~54% viewport)
const FULL_RATIO = 0.88; // Layar penuh (~88% viewport)
const BOTTOM_BLEED_PX = 80;

const viewportH = ref(typeof window !== "undefined" ? window.innerHeight : 720);
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
  if (typeof window !== "undefined") {
    viewportH.value = window.innerHeight;
  }
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
  if (typeof window !== "undefined") {
    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("resize", updateViewportH, { passive: true });
    window.addEventListener("pointerdown", handleDocumentClick);
  }
});

onUnmounted(() => {
  if (typeof window !== "undefined") {
    window.removeEventListener("keydown", handleKeyDown);
    window.removeEventListener("resize", updateViewportH);
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
          class="pointer-events-auto relative w-full h-full flex flex-col rounded-t-[28px] bg-white dark:bg-[#0b1322] border-t border-slate-200/90 dark:border-white/10 shadow-[0_-12px_40px_rgba(0,0,0,0.22)] overflow-hidden"
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
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300">
                      {{ basemapList.length }} Pilihan
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    Pilih gaya peta dasar untuk eksplorasi wilayah
                  </p>
                </div>
              </div>

              <!-- Close Button (Min 44x44px touch target) -->
              <button
                type="button"
                class="size-11 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-95 shrink-0"
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
                <!-- Esri World Topo Preview -->
                <svg
                  v-if="item.key === 'esri'"
                  class="w-full h-full object-cover"
                  viewBox="0 0 64 64"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <rect width="64" height="64" fill="#E8ECE4" />
                  <path d="M0 20C15 15 35 25 50 18C58 14 62 16 64 15V64H0V20Z" fill="#DCE5D8" />
                  <path d="M0 38C18 32 32 44 48 36C56 32 60 35 64 33V64H0V38Z" fill="#CBDBC5" />
                  <path d="M0 48C12 45 24 55 40 50C52 46 58 52 64 48V64H0V48Z" fill="#B7D2AE" />
                  <line x1="0" y1="28" x2="64" y2="28" stroke="#FFFFFF" stroke-width="2.5" />
                  <line x1="0" y1="28" x2="64" y2="28" stroke="#E28935" stroke-width="1.2" />
                  <line x1="36" y1="0" x2="36" y2="64" stroke="#FFFFFF" stroke-width="2" />
                  <line x1="36" y1="0" x2="36" y2="64" stroke="#E28935" stroke-width="0.8" />
                </svg>

                <!-- RBI Preview -->
                <svg
                  v-else-if="item.key === 'rbi'"
                  class="w-full h-full object-cover"
                  viewBox="0 0 64 64"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <rect width="64" height="64" fill="#F4F6F0" />
                  <path d="M0 0H36L28 28H0V0Z" fill="#DDECD2" />
                  <path d="M32 36H64V64H24L32 36Z" fill="#CEE3C3" />
                  <path d="M0 44C18 42 28 50 44 42C52 38 58 40 64 38V48C56 50 48 58 36 54C24 50 14 56 0 54V44Z" fill="#85C1E9" />
                  <line x1="0" y1="22" x2="64" y2="22" stroke="#FFFFFF" stroke-width="3" />
                  <line x1="0" y1="22" x2="64" y2="22" stroke="#E74C3C" stroke-width="1.8" />
                  <line x1="26" y1="0" x2="26" y2="64" stroke="#FFFFFF" stroke-width="3" />
                  <line x1="26" y1="0" x2="26" y2="64" stroke="#D35400" stroke-width="1.5" />
                  <circle cx="26" cy="22" r="3.5" fill="#C0392B" stroke="#FFFFFF" stroke-width="1" />
                </svg>

                <!-- OSM Preview -->
                <svg
                  v-else-if="item.key === 'osm'"
                  class="w-full h-full object-cover"
                  viewBox="0 0 64 64"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <rect width="64" height="64" fill="#E8ECE9" />
                  <path d="M0 0H28L22 32H0V0Z" fill="#C8E6C9" />
                  <path d="M40 40H64V64H32L40 40Z" fill="#DCEDC8" />
                  <path d="M64 12C52 14 44 26 36 34C28 42 16 46 0 48V56C20 54 32 50 42 40C50 32 56 22 64 20V12Z" fill="#90CAF9" />
                  <line x1="0" y1="28" x2="64" y2="28" stroke="#FFFFFF" stroke-width="4" />
                  <line x1="0" y1="28" x2="64" y2="28" stroke="#FFE082" stroke-width="2.5" />
                  <line x1="30" y1="0" x2="30" y2="64" stroke="#FFFFFF" stroke-width="4" />
                  <line x1="30" y1="0" x2="30" y2="64" stroke="#FFCC80" stroke-width="2" />
                </svg>

                <!-- Satellite Preview -->
                <svg
                  v-else-if="item.key === 'satellite'"
                  class="w-full h-full object-cover"
                  viewBox="0 0 64 64"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <rect width="64" height="64" fill="#1C3120" />
                  <rect x="2" y="2" width="24" height="20" fill="#2E4A28" opacity="0.9" />
                  <rect x="28" y="2" width="22" height="26" fill="#3D5A34" opacity="0.85" />
                  <rect x="52" y="2" width="10" height="18" fill="#1E3722" opacity="0.9" />
                  <rect x="2" y="24" width="20" height="26" fill="#4B6338" opacity="0.8" />
                  <rect x="24" y="30" width="26" height="20" fill="#2D4629" opacity="0.9" />
                  <rect x="52" y="22" width="10" height="26" fill="#385430" opacity="0.8" />
                  <path d="M0 52C16 50 28 56 42 48C50 44 56 46 64 44V64H0V52Z" fill="#1A3344" />
                  <path d="M0 26H64M26 0V64" stroke="#7A8B7B" stroke-width="1" stroke-dasharray="3 1" opacity="0.6" />
                </svg>

                <!-- Aerial Preview -->
                <svg
                  v-else-if="item.key === 'aerial'"
                  class="w-full h-full object-cover"
                  viewBox="0 0 64 64"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <rect width="64" height="64" fill="#1C3822" />
                  <polygon points="0,0 34,0 26,34 0,28" fill="#365C2E" />
                  <polygon points="34,0 64,0 64,30 26,34" fill="#4A753D" />
                  <polygon points="0,28 26,34 22,64 0,64" fill="#2E5026" />
                  <polygon points="26,34 64,30 64,64 22,64" fill="#3D6433" />
                  <rect x="8" y="10" width="10" height="8" rx="1" fill="#C2593F" />
                  <rect x="42" y="14" width="12" height="10" rx="1" fill="#D97757" />
                  <rect x="36" y="44" width="14" height="10" rx="1" fill="#B34B32" />
                  <path d="M0 40Q24 36 28 32T64 26" stroke="#CBD5E1" stroke-width="2.5" fill="none" opacity="0.8" />
                  <circle cx="32" cy="32" r="10" stroke="#38BDF8" stroke-width="1.2" stroke-dasharray="2 2" fill="none" opacity="0.85" />
                  <circle cx="32" cy="32" r="2.5" fill="#38BDF8" opacity="0.9" />
                </svg>

                <!-- Top Badges Overlay -->
                <div class="absolute top-1.5 inset-x-1.5 flex items-center justify-between pointer-events-none">
                  <span
                    class="px-1.5 py-0.2 rounded-md text-[9px] font-bold tracking-tight uppercase shadow-xs backdrop-blur-xs"
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
                <span class="text-[10px] text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5 leading-tight">
                  {{ item.tagline }}
                </span>
                <span class="text-[9px] text-slate-400 dark:text-slate-500 font-medium truncate mt-1">
                  {{ item.provider }}
                </span>
              </div>
            </button>
          </div>

          <!-- Safe area spacer for modern bezel-less phones -->
          <div
            class="shrink-0"
            style="height: calc(env(safe-area-inset-bottom, 0px) + 12px)"
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

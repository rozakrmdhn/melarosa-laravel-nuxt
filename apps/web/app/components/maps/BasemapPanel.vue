<script setup lang="ts">
import type { BasemapKey } from "~/components/maps/MapCanvas.vue";

const props = withDefaults(
  defineProps<{
    currentBasemap?: BasemapKey;
    isMobile?: boolean;
    isMobileSheetOpen?: boolean;
  }>(),
  {
    currentBasemap: "esri",
    isMobile: false,
    isMobileSheetOpen: false,
  }
);

const emit = defineEmits<{
  (e: "update:currentBasemap", val: BasemapKey): void;
  (e: "open-mobile-sheet"): void;
}>();

interface BasemapDef {
  key: BasemapKey;
  name: string;
  tagline: string;
  type: "vector" | "raster";
}

const basemapList: BasemapDef[] = [
  {
    key: "esri",
    name: "Esri",
    tagline: "Esri World Topo Map",
    type: "raster",
  },
  {
    key: "rbi",
    name: "RBI",
    tagline: "Rupabumi Indonesia (BIG)",
    type: "raster",
  },
  {
    key: "osm",
    name: "OSM",
    tagline: "Peta jalan OpenStreetMap",
    type: "vector",
  },
  {
    key: "satellite",
    name: "Satelit",
    tagline: "Citra satelit resolusi tinggi",
    type: "raster",
  },
  {
    key: "aerial",
    name: "Aerial",
    tagline: "Foto udara resolusi tinggi",
    type: "raster",
  },
];

const rootRef = ref<HTMLElement | null>(null);
const isHovered = ref(false);
const isClickedOpen = ref(false);
let closeTimer: ReturnType<typeof setTimeout> | null = null;
const HOVER_CLOSE_DELAY_MS = 500;

const isOpen = computed(() => {
  if (props.isMobile) return props.isMobileSheetOpen ?? false;
  return isHovered.value || isClickedOpen.value;
});

const activeBasemap = computed(() => {
  return basemapList.find((b) => b.key === props.currentBasemap) || basemapList[0];
});

function handleMouseEnter() {
  if (props.isMobile) return;
  if (closeTimer) {
    clearTimeout(closeTimer);
    closeTimer = null;
  }
  isHovered.value = true;
}

function handleMouseLeave() {
  if (props.isMobile) return;
  if (closeTimer) clearTimeout(closeTimer);
  closeTimer = setTimeout(() => {
    isHovered.value = false;
  }, HOVER_CLOSE_DELAY_MS);
}

function toggleClicked() {
  if (props.isMobile) {
    emit("open-mobile-sheet");
    return;
  }
  isClickedOpen.value = !isClickedOpen.value;
}

function selectBasemap(key: BasemapKey) {
  emit("update:currentBasemap", key);
  isClickedOpen.value = false;
}

function handleKeyDown(e: KeyboardEvent) {
  if (e.key === "Escape") {
    if (closeTimer) {
      clearTimeout(closeTimer);
      closeTimer = null;
    }
    isClickedOpen.value = false;
    isHovered.value = false;
  }
}

function handleClickOutside(e: MouseEvent) {
  if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
    if (closeTimer) {
      clearTimeout(closeTimer);
      closeTimer = null;
    }
    isClickedOpen.value = false;
    isHovered.value = false;
  }
}

onMounted(() => {
  window.addEventListener("click", handleClickOutside);
  window.addEventListener("keydown", handleKeyDown);
});

onUnmounted(() => {
  if (closeTimer) clearTimeout(closeTimer);
  window.removeEventListener("click", handleClickOutside);
  window.removeEventListener("keydown", handleKeyDown);
});
</script>

<template>
  <div
    ref="rootRef"
    class="relative inline-flex items-end select-none group"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <!-- ═══ 1. SQUARE TOGGLE BUTTON (Google Maps Style) ═══ -->
    <UTooltip text="Pilihan Peta Dasar (Basemap)">
      <button
        type="button"
        data-basemap-toggle="true"
        class="relative size-12 sm:size-14 rounded-xl sm:rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-200 cursor-pointer border bg-white dark:bg-slate-900 shrink-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 ring-offset-white dark:ring-offset-slate-950 z-20 group-hover:scale-102 active:scale-95"
        :class="isOpen
          ? 'border-blue-500 ring-2 ring-blue-500/30'
          : 'border-slate-200/90 dark:border-slate-700/80 hover:border-blue-400/80'"
        :aria-expanded="isOpen"
        aria-label="Pilihan Peta Dasar (Basemap)"
        @click="toggleClicked"
      >
      <!-- Preview Visual of the currently active basemap -->
      <div class="absolute inset-0 w-full h-full pointer-events-none">
        <!-- Esri World Topo Preview (Default) -->
        <svg
          v-if="activeBasemap.key === 'esri' || activeBasemap.key === 'positron'"
          class="w-full h-full"
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
          v-else-if="activeBasemap.key === 'rbi'"
          class="w-full h-full"
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
          v-else-if="activeBasemap.key === 'osm' || activeBasemap.key === 'topo'"
          class="w-full h-full"
          viewBox="0 0 64 64"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <rect width="64" height="64" fill="#E8ECE9" />
          <path d="M0 0H28L22 32H0V0Z" fill="#C8E6C9" />
          <path d="M40 40H64V64H32L40 40Z" fill="#DCEDC8" />
          <path
            d="M64 12C52 14 44 26 36 34C28 42 16 46 0 48V56C20 54 32 50 42 40C50 32 56 22 64 20V12Z"
            fill="#90CAF9"
          />
          <line x1="0" y1="28" x2="64" y2="28" stroke="#FFFFFF" stroke-width="4" />
          <line x1="0" y1="28" x2="64" y2="28" stroke="#FFE082" stroke-width="2.5" />
          <line x1="30" y1="0" x2="30" y2="64" stroke="#FFFFFF" stroke-width="4" />
          <line x1="30" y1="0" x2="30" y2="64" stroke="#FFCC80" stroke-width="2" />
        </svg>

        <!-- Satellite Preview -->
        <svg
          v-else-if="activeBasemap.key === 'satellite'"
          class="w-full h-full"
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
          <path
            d="M0 52C16 50 28 56 42 48C50 44 56 46 64 44V64H0V52Z"
            fill="#1A3344"
          />
          <path
            d="M0 26H64M26 0V64"
            stroke="#7A8B7B"
            stroke-width="1"
            stroke-dasharray="3 1"
            opacity="0.6"
          />
        </svg>

        <!-- Aerial Preview -->
        <svg
          v-else-if="activeBasemap.key === 'aerial'"
          class="w-full h-full"
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
      </div>

      <!-- Bottom Capsule Label (Google Maps style) -->
      <div
        class="absolute bottom-1 inset-x-1 py-0.5 rounded-md bg-slate-950/75 dark:bg-black/85 backdrop-blur-xs text-[10px] font-bold text-white text-center tracking-tight truncate px-1 shadow-xs pointer-events-none"
      >
        {{ activeBasemap.name }}
      </div>

      <!-- Subtle Expand Indicator Icon -->
      <div
        class="absolute top-1 right-1 size-4 rounded-full bg-slate-950/60 text-white flex items-center justify-center transition-transform duration-200 pointer-events-none"
        :class="isOpen ? 'rotate-90 bg-blue-600' : ''"
      >
        <UIcon name="i-lucide-chevron-right" class="size-2.5 stroke-[3]" />
      </div>
    </button>
    </UTooltip>

    <!-- ═══ 2. HORIZONTAL FLYOUT DRAWER (Expands to the Right on Hover - Only Desktop) ═══ -->
    <Transition name="drawer-right">
      <div
        v-if="!isMobile && isOpen"
        class="absolute left-full bottom-0 pl-2.5 z-30 flex items-center pointer-events-auto"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
      >
        <!-- Card Container with Backdrop Blur & Clean Border -->
        <div
          class="bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-slate-200/90 dark:border-slate-800 rounded-2xl p-2 sm:p-2.5 shadow-2xl flex items-center gap-2 sm:gap-2.5 max-w-[calc(100vw-88px)] overflow-x-auto no-scrollbar"
          role="radiogroup"
          aria-label="Pilihan Jenis Peta Dasar"
        >
          <!-- Individual Basemap Option Cards -->
          <button
            v-for="item in basemapList"
            :key="item.key"
            type="button"
            role="radio"
            :aria-checked="currentBasemap === item.key"
            class="flex flex-col items-center group/card cursor-pointer focus-visible:outline-hidden rounded-xl p-1 transition-all shrink-0 min-w-[56px] sm:min-w-[64px]"
            @click="selectBasemap(item.key)"
          >
            <!-- Thumbnail Visual -->
            <div
              class="relative size-12 sm:size-14 rounded-xl overflow-hidden border-2 transition-all duration-200 shadow-xs group-hover/card:scale-105"
              :class="currentBasemap === item.key
                ? 'border-blue-500 ring-2 ring-blue-500/30 scale-102'
                : 'border-slate-200 dark:border-slate-700/80 group-hover/card:border-slate-400 dark:group-hover/card:border-slate-500'"
            >
              <!-- Esri World Topo Thumbnail -->
              <svg
                v-if="item.key === 'esri' || item.key === 'positron'"
                class="w-full h-full"
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

              <!-- RBI Thumbnail -->
              <svg
                v-else-if="item.key === 'rbi'"
                class="w-full h-full"
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

              <!-- OSM Thumbnail -->
              <svg
                v-else-if="item.key === 'osm' || item.key === 'topo'"
                class="w-full h-full"
                viewBox="0 0 64 64"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <rect width="64" height="64" fill="#E8ECE9" />
                <path d="M0 0H28L22 32H0V0Z" fill="#C8E6C9" />
                <path d="M40 40H64V64H32L40 40Z" fill="#DCEDC8" />
                <path
                  d="M64 12C52 14 44 26 36 34C28 42 16 46 0 48V56C20 54 32 50 42 40C50 32 56 22 64 20V12Z"
                  fill="#90CAF9"
                />
                <line x1="0" y1="28" x2="64" y2="28" stroke="#FFFFFF" stroke-width="4" />
                <line x1="0" y1="28" x2="64" y2="28" stroke="#FFE082" stroke-width="2.5" />
                <line x1="30" y1="0" x2="30" y2="64" stroke="#FFFFFF" stroke-width="4" />
                <line x1="30" y1="0" x2="30" y2="64" stroke="#FFCC80" stroke-width="2" />
              </svg>

              <!-- Satellite Thumbnail -->
              <svg
                v-else-if="item.key === 'satellite'"
                class="w-full h-full"
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
                <path
                  d="M0 52C16 50 28 56 42 48C50 44 56 46 64 44V64H0V52Z"
                  fill="#1A3344"
                />
                <path
                  d="M0 26H64M26 0V64"
                  stroke="#7A8B7B"
                  stroke-width="1"
                  stroke-dasharray="3 1"
                  opacity="0.6"
                />
              </svg>

              <!-- Aerial Thumbnail -->
              <svg
                v-else-if="item.key === 'aerial'"
                class="w-full h-full"
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

              <!-- Active Check Badge -->
              <div
                v-if="currentBasemap === item.key"
                class="absolute top-1 right-1 size-4 rounded-full bg-blue-500 text-white flex items-center justify-center shadow-xs"
              >
                <UIcon name="i-lucide-check" class="size-2.5 stroke-[3]" />
              </div>
            </div>

            <!-- Card Label -->
            <span
              class="text-[11px] mt-1.5 leading-tight text-center tracking-tight transition-colors"
              :class="currentBasemap === item.key
                ? 'font-bold text-blue-600 dark:text-blue-400'
                : 'font-medium text-slate-700 dark:text-slate-300 group-hover/card:text-slate-950 dark:group-hover/card:text-white'"
            >
              {{ item.name }}
            </span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Smooth slide out to the right transition */
.drawer-right-enter-active,
.drawer-right-leave-active {
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1),
    transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  transform-origin: left bottom;
}

.drawer-right-enter-from,
.drawer-right-leave-to {
  opacity: 0;
  transform: translateX(-12px) scale(0.96);
}

/* Hide scrollbar for cross-browser support */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

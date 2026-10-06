<script setup lang="ts">
import type { BasemapKey } from "~/components/maps/MapCanvas.vue";

const props = withDefaults(
  defineProps<{
    currentBasemap?: BasemapKey;
  }>(),
  {
    currentBasemap: "osm",
  }
);

const emit = defineEmits<{
  (e: "update:currentBasemap", val: BasemapKey): void;
}>();

interface BasemapDef {
  key: BasemapKey;
  name: string;
  tagline: string;
  type: "vector" | "raster";
}

const basemapList: BasemapDef[] = [
  {
    key: "osm",
    name: "Standar",
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
    key: "positron",
    name: "Terang",
    tagline: "Peta bersih minimalis",
    type: "vector",
  },
  {
    key: "dark",
    name: "Gelap",
    tagline: "Kontras malam hari",
    type: "vector",
  },
  {
    key: "topo",
    name: "Medan",
    tagline: "Kontur dan elevasi",
    type: "raster",
  },
];

const rootRef = ref<HTMLElement | null>(null);
const isHovered = ref(false);
const isClickedOpen = ref(false);
let closeTimer: ReturnType<typeof setTimeout> | null = null;
const HOVER_CLOSE_DELAY_MS = 500;

const isOpen = computed(() => isHovered.value || isClickedOpen.value);

const activeBasemap = computed(() => {
  return basemapList.find((b) => b.key === props.currentBasemap) || basemapList[0];
});

function handleMouseEnter() {
  if (closeTimer) {
    clearTimeout(closeTimer);
    closeTimer = null;
  }
  isHovered.value = true;
}

function handleMouseLeave() {
  if (closeTimer) clearTimeout(closeTimer);
  closeTimer = setTimeout(() => {
    isHovered.value = false;
  }, HOVER_CLOSE_DELAY_MS);
}

function toggleClicked() {
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
    <button
      type="button"
      class="relative size-14 sm:size-16 rounded-xl sm:rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-200 cursor-pointer border bg-white dark:bg-slate-900 shrink-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 ring-offset-white dark:ring-offset-slate-950 z-20 group-hover:scale-102"
      :class="isOpen
        ? 'border-blue-500 ring-2 ring-blue-500/30'
        : 'border-gray-200/90 dark:border-slate-700/80 hover:border-blue-400/80'"
      :aria-expanded="isOpen"
      aria-label="Pilihan Peta Dasar (Basemap)"
      @click="toggleClicked"
    >
      <!-- Preview Visual of the currently active basemap -->
      <div class="absolute inset-0 w-full h-full pointer-events-none">
        <!-- OSM Preview -->
        <svg
          v-if="activeBasemap.key === 'osm'"
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

        <!-- Positron Preview -->
        <svg
          v-else-if="activeBasemap.key === 'positron'"
          class="w-full h-full"
          viewBox="0 0 64 64"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <rect width="64" height="64" fill="#F4F4F6" />
          <rect x="4" y="4" width="22" height="24" rx="2" fill="#EAEBEF" />
          <rect x="32" y="4" width="28" height="18" rx="2" fill="#EAEBEF" />
          <rect x="4" y="34" width="36" height="26" rx="2" fill="#EAEBEF" />
          <rect x="44" y="26" width="16" height="34" rx="2" fill="#EAEBEF" />
          <line x1="0" y1="30" x2="64" y2="30" stroke="#FFFFFF" stroke-width="3" />
          <line x1="28" y1="0" x2="28" y2="64" stroke="#FFFFFF" stroke-width="3" />
          <line x1="0" y1="30" x2="64" y2="30" stroke="#D1D5DB" stroke-width="1" />
          <line x1="28" y1="0" x2="28" y2="64" stroke="#D1D5DB" stroke-width="1" />
        </svg>

        <!-- Dark Preview -->
        <svg
          v-else-if="activeBasemap.key === 'dark'"
          class="w-full h-full"
          viewBox="0 0 64 64"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <rect width="64" height="64" fill="#0F172A" />
          <rect x="4" y="4" width="24" height="22" rx="2" fill="#1E293B" />
          <rect x="34" y="4" width="26" height="26" rx="2" fill="#1E293B" />
          <rect x="4" y="32" width="24" height="28" rx="2" fill="#1E293B" />
          <rect x="34" y="36" width="26" height="24" rx="2" fill="#1E293B" />
          <line x1="0" y1="30" x2="64" y2="30" stroke="#334155" stroke-width="2" />
          <line x1="30" y1="0" x2="30" y2="64" stroke="#334155" stroke-width="2" />
          <line x1="0" y1="30" x2="64" y2="30" stroke="#0284C7" stroke-width="0.8" opacity="0.8" />
          <line x1="30" y1="0" x2="30" y2="64" stroke="#0284C7" stroke-width="0.8" opacity="0.8" />
        </svg>

        <!-- Topo Preview -->
        <svg
          v-else-if="activeBasemap.key === 'topo'"
          class="w-full h-full"
          viewBox="0 0 64 64"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <rect width="64" height="64" fill="#E2E8D5" />
          <path d="M0 10C24 4 40 22 64 12V64H0V10Z" fill="#D3DDBE" />
          <path d="M0 24C20 18 36 34 64 24V64H0V24Z" fill="#C4CEAC" />
          <path d="M0 38C18 32 30 46 64 36V64H0V38Z" fill="#B2BD97" />
          <path d="M0 50C22 46 38 56 64 48V64H0V50Z" fill="#A1AC84" />
          <path d="M0 10C24 4 40 22 64 12" stroke="#8A966E" stroke-width="0.8" fill="none" />
          <path d="M0 24C20 18 36 34 64 24" stroke="#8A966E" stroke-width="0.8" fill="none" />
          <path d="M0 38C18 32 30 46 64 36" stroke="#8A966E" stroke-width="0.8" fill="none" />
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

    <!-- ═══ 2. HORIZONTAL FLYOUT DRAWER (Expands to the Right on Hover) ═══ -->
    <Transition name="drawer-right">
      <div
        v-show="isOpen"
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
              <!-- OSM Thumbnail -->
              <svg
                v-if="item.key === 'osm'"
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

              <!-- Positron Thumbnail -->
              <svg
                v-else-if="item.key === 'positron'"
                class="w-full h-full"
                viewBox="0 0 64 64"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <rect width="64" height="64" fill="#F4F4F6" />
                <rect x="4" y="4" width="22" height="24" rx="2" fill="#EAEBEF" />
                <rect x="32" y="4" width="28" height="18" rx="2" fill="#EAEBEF" />
                <rect x="4" y="34" width="36" height="26" rx="2" fill="#EAEBEF" />
                <rect x="44" y="26" width="16" height="34" rx="2" fill="#EAEBEF" />
                <line x1="0" y1="30" x2="64" y2="30" stroke="#FFFFFF" stroke-width="3" />
                <line x1="28" y1="0" x2="28" y2="64" stroke="#FFFFFF" stroke-width="3" />
                <line x1="0" y1="30" x2="64" y2="30" stroke="#D1D5DB" stroke-width="1" />
                <line x1="28" y1="0" x2="28" y2="64" stroke="#D1D5DB" stroke-width="1" />
              </svg>

              <!-- Dark Thumbnail -->
              <svg
                v-else-if="item.key === 'dark'"
                class="w-full h-full"
                viewBox="0 0 64 64"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <rect width="64" height="64" fill="#0F172A" />
                <rect x="4" y="4" width="24" height="22" rx="2" fill="#1E293B" />
                <rect x="34" y="4" width="26" height="26" rx="2" fill="#1E293B" />
                <rect x="4" y="32" width="24" height="28" rx="2" fill="#1E293B" />
                <rect x="34" y="36" width="26" height="24" rx="2" fill="#1E293B" />
                <line x1="0" y1="30" x2="64" y2="30" stroke="#334155" stroke-width="2" />
                <line x1="30" y1="0" x2="30" y2="64" stroke="#334155" stroke-width="2" />
                <line x1="0" y1="30" x2="64" y2="30" stroke="#0284C7" stroke-width="0.8" opacity="0.8" />
                <line x1="30" y1="0" x2="30" y2="64" stroke="#0284C7" stroke-width="0.8" opacity="0.8" />
              </svg>

              <!-- Topo Thumbnail -->
              <svg
                v-else-if="item.key === 'topo'"
                class="w-full h-full"
                viewBox="0 0 64 64"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <rect width="64" height="64" fill="#E2E8D5" />
                <path d="M0 10C24 4 40 22 64 12V64H0V10Z" fill="#D3DDBE" />
                <path d="M0 24C20 18 36 34 64 24V64H0V24Z" fill="#C4CEAC" />
                <path d="M0 38C18 32 30 46 64 36V64H0V38Z" fill="#B2BD97" />
                <path d="M0 50C22 46 38 56 64 48V64H0V50Z" fill="#A1AC84" />
                <path d="M0 10C24 4 40 22 64 12" stroke="#8A966E" stroke-width="0.8" fill="none" />
                <path d="M0 24C20 18 36 34 64 24" stroke="#8A966E" stroke-width="0.8" fill="none" />
                <path d="M0 38C18 32 30 46 64 36" stroke="#8A966E" stroke-width="0.8" fill="none" />
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

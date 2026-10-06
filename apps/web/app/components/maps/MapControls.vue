<script setup lang="ts">
interface Props {
  isLocating?: boolean;
  hasLocation?: boolean;
  showLocate?: boolean;
  speed?: number | null; // km/jam
  heading?: number | null; // derajat 0-360
  accuracy?: number | null; // meter
}

const props = withDefaults(defineProps<Props>(), {
  isLocating: false,
  hasLocation: false,
  showLocate: true,
  speed: null,
  heading: null,
  accuracy: null,
});

const emit = defineEmits<{
  (e: "locate"): void;
  (e: "zoom-in"): void;
  (e: "zoom-out"): void;
  (e: "share"): void;
}>();

function getCardinal(deg: number): string {
  const directions = ["U", "TL", "T", "TG", "S", "BD", "B", "BL"];
  const idx = Math.round(deg / 45) % 8;
  return directions[idx];
}

const locationTooltip = computed(() => {
  if (!props.hasLocation) return "Aktifkan Live Tracking GPS & Kompas";
  const parts: string[] = [];
  if (props.speed !== null && props.speed !== undefined) {
    parts.push(`${props.speed} km/j`);
  }
  if (props.heading !== null && props.heading !== undefined) {
    parts.push(`${props.heading}° ${getCardinal(props.heading)}`);
  }
  if (props.accuracy !== null && props.accuracy !== undefined) {
    if (props.accuracy > 100) {
      parts.push(`±${props.accuracy}m Jaringan/Wi-Fi`);
    } else {
      parts.push(`±${props.accuracy}m`);
    }
  }
  const info = parts.length > 0 ? ` (${parts.join(" · ")})` : "";
  return `Matikan Live Tracking${info}`;
});
</script>

<template>
  <div
    class="flex flex-col items-end gap-1.5 pointer-events-none select-none"
    role="toolbar"
    aria-label="Kontrol Navigasi Peta"
  >
    <!-- ═══ 1. GEOLOCATION BUTTON (With Merged Live Speed & Compass Heading) ═══ -->
    <div
      v-if="showLocate"
      class="pointer-events-auto flex items-center bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden"
      :class="hasLocation
        ? 'border-blue-300/90 dark:border-blue-800/80 ring-1 ring-blue-500/25 shadow-blue-500/10'
        : 'border-gray-200/90 dark:border-gray-800'"
    >
      <UTooltip :text="locationTooltip">
        <div class="flex items-center">
          <!-- Live Speed Section (Smoothly expands/collapses horizontally inside the same pill) -->
          <Transition name="speed-expand">
            <button
              v-if="hasLocation && speed !== null"
              type="button"
              class="flex items-center pl-3 pr-2 h-[44px] whitespace-nowrap overflow-hidden select-none cursor-pointer hover:bg-blue-50/50 dark:hover:bg-blue-950/30 transition-colors focus:outline-hidden"
              aria-label="Status Kecepatan GPS"
              @click="emit('locate')"
            >
              <!-- Speed number on top, unit label below (no icon) -->
              <div class="flex flex-col items-center justify-center min-w-[28px] text-center leading-none">
                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                  {{ speed }}
                </span>
                <span class="text-[8px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-tight mt-0.5">
                  km/j
                </span>
              </div>

              <!-- Hairline divider between speed section and locate button -->
              <div class="w-px h-5 bg-blue-200/80 dark:bg-blue-900/60 ml-2.5 mr-0.5" aria-hidden="true" />
            </button>
          </Transition>

          <!-- Toggle Locate Button -->
          <button
            type="button"
            class="min-w-[44px] min-h-[44px] flex items-center justify-center transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-1.5 focus-visible:ring-blue-500 z-10 active:scale-95"
            :class="hasLocation
              ? 'text-blue-600 dark:text-blue-400 bg-blue-50/60 dark:bg-blue-950/40 hover:bg-blue-100/70 dark:hover:bg-blue-900/50'
              : 'text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80'"
            :aria-pressed="hasLocation"
            aria-label="Toggle Live Tracking GPS"
            @click="emit('locate')"
          >
            <!-- Loading Spinner while fetching GPS -->
            <UIcon
              v-if="isLocating"
              name="i-lucide-loader-2"
              class="size-4 animate-spin text-blue-600 dark:text-blue-400"
            />
            <!-- Directional Compass Arrow when heading is available -->
            <UIcon
              v-else-if="hasLocation && heading !== null"
              name="i-lucide-navigation"
              class="size-4 text-blue-600 dark:text-blue-400 transition-transform duration-150 ease-out"
              :style="{ transform: `rotate(${heading}deg)` }"
            />
            <!-- Locked Location State without heading -->
            <UIcon
              v-else-if="hasLocation"
              name="i-lucide-locate-fixed"
              class="size-4 stroke-[2.25]"
            />
            <!-- Default Locate State (Inactive) -->
            <UIcon
              v-else
              name="i-lucide-locate"
              class="size-4 stroke-[2.25]"
            />
          </button>
        </div>
      </UTooltip>
    </div>

    <!-- ═══ 2. SHARE BUTTON PILL ═══ -->
    <div
      class="pointer-events-auto flex items-center bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800 rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-200 overflow-hidden"
    >
      <UTooltip text="Bagikan Peta & Postingan Media Sosial">
        <button
          type="button"
          class="min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 active:scale-90 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-1.5 focus-visible:ring-blue-500 z-10"
          aria-label="Bagikan Peta"
          @click="emit('share')"
        >
          <UIcon name="i-lucide-share-2" class="size-4 stroke-[2.25]" />
        </button>
      </UTooltip>
    </div>

    <!-- ═══ 3. ZOOM IN / OUT PILL (Separated Freestanding Container) ═══ -->
    <div
      class="pointer-events-auto flex flex-col bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800 rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-200 overflow-hidden"
    >
      <!-- Zoom In Button -->
      <UTooltip text="Perbesar">
        <button
          type="button"
          class="min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 active:scale-90 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-1.5 focus-visible:ring-blue-500 z-10"
          aria-label="Perbesar peta"
          @click="emit('zoom-in')"
        >
          <UIcon name="i-lucide-plus" class="size-4 stroke-[2.25]" />
        </button>
      </UTooltip>

      <!-- Hairline Divider -->
      <div class="w-full h-px bg-gray-200/80 dark:bg-slate-800" aria-hidden="true" />

      <!-- Zoom Out Button -->
      <UTooltip text="Perkecil">
        <button
          type="button"
          class="min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 active:scale-90 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-1.5 focus-visible:ring-blue-500 z-10"
          aria-label="Perkecil peta"
          @click="emit('zoom-out')"
        >
          <UIcon name="i-lucide-minus" class="size-4 stroke-[2.25]" />
        </button>
      </UTooltip>
    </div>
  </div>
</template>

<style scoped>
/* Smooth expand/collapse transition for speed indicator merging into geolocation pill */
.speed-expand-enter-active,
.speed-expand-leave-active {
  transition: max-width 0.32s cubic-bezier(0.16, 1, 0.3, 1),
              opacity 0.22s ease,
              padding 0.32s cubic-bezier(0.16, 1, 0.3, 1),
              transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
  max-width: 140px;
}

.speed-expand-enter-from,
.speed-expand-leave-to {
  max-width: 0 !important;
  opacity: 0;
  padding-left: 0 !important;
  padding-right: 0 !important;
  transform: scale(0.92) translateX(8px);
}
</style>

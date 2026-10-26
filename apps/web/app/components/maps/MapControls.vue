<script setup lang="ts">
interface Props {
  isLocating?: boolean;
  hasLocation?: boolean;
  showLocate?: boolean;
  speed?: number | null; // km/jam
  heading?: number | null; // derajat 0-360
  accuracy?: number | null; // meter
  rotation?: number; // derajat rotasi map canvas
}

const props = withDefaults(defineProps<Props>(), {
  isLocating: false,
  hasLocation: false,
  showLocate: true,
  speed: null,
  heading: null,
  accuracy: null,
  rotation: 0,
});

const emit = defineEmits<{
  (e: "locate"): void;
  (e: "zoom-in"): void;
  (e: "zoom-out"): void;
  (e: "reset-north"): void;
}>();

function getCardinal(deg: number): string {
  const directions = ["U", "TL", "T", "TG", "S", "BD", "B", "BL"];
  const idx = Math.round(deg / 45) % 8;
  return directions[idx];
}

const normalizedDegrees = computed(() => {
  const deg = ((props.rotation % 360) + 360) % 360;
  return deg > 180 ? deg - 360 : deg;
});

const isRotated = computed(() => Math.abs(normalizedDegrees.value) > 0.5);

const compassTooltip = computed(() => {
  if (isRotated.value) {
    const angle = Math.abs(Math.round(normalizedDegrees.value));
    return `Reset Orientasi ke Utara (${angle}°)`;
  }
  return "Arah Mata Angin (Tegak Lurus ke Utara)";
});

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
    <!-- 1. COMPASS & NORTH RESET BUTTON -->
    <div
      class="pointer-events-auto flex items-center bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-200 overflow-hidden"
      :class="isRotated
        ? 'border-blue-400/90 dark:border-blue-600/90 ring-1 ring-blue-500/25 shadow-blue-500/10'
        : 'border-gray-200/90 dark:border-gray-800'"
    >
      <UTooltip :text="compassTooltip">
        <button
          type="button"
          class="min-w-[44px] min-h-[44px] size-11 flex flex-col items-center justify-center relative transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 z-10 active:scale-90"
          :class="isRotated
            ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50/60 dark:hover:bg-blue-950/40'
            : 'text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80'"
          aria-label="Arah Mata Angin dan Reset Orientasi Utara"
          @click="emit('reset-north')"
        >
          <!-- Needle dial rotating synchronously with map view -->
          <div
            class="relative size-6 flex items-center justify-center transition-transform duration-100 ease-out will-change-transform"
            :style="{ transform: `rotate(${props.rotation}deg)` }"
          >
            <!-- High-precision vector compass needle -->
            <svg viewBox="0 0 24 24" class="size-5.5 filter drop-shadow-xs" fill="none">
              <!-- North tip (Bright red) -->
              <polygon points="12,2 15.8,12 12,9.6" fill="#ef4444" />
              <polygon points="12,2 8.2,12 12,9.6" fill="#dc2626" />
              <!-- South tip (Slate neutral) -->
              <polygon points="12,22 15.8,12 12,14.4" fill="#94a3b8" />
              <polygon points="12,22 8.2,12 12,14.4" fill="#64748b" />
              <!-- Center pivot dot -->
              <circle cx="12" cy="12" r="1.6" fill="#ffffff" stroke="#334155" stroke-width="0.8" />
            </svg>
          </div>

          <!-- Angle badge when rotated -->
          <span
            v-if="isRotated"
            class="absolute bottom-0.5 text-[10px] font-bold leading-none text-blue-600 dark:text-blue-400 tabular-nums"
          >
            {{ Math.abs(Math.round(normalizedDegrees)) }}°
          </span>
        </button>
      </UTooltip>
    </div>

    <!-- 2. GEOLOCATION BUTTON (With Merged Live Speed & Compass Heading) -->
    <div
      v-if="showLocate"
      class="pointer-events-auto flex items-center bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden"
      :class="hasLocation
        ? 'border-blue-300/90 dark:border-blue-800/80 ring-1 ring-blue-500/25 shadow-blue-500/10'
        : 'border-gray-200/90 dark:border-gray-800'"
    >
      <UTooltip :text="locationTooltip">
        <div class="flex items-center">
          <Transition name="speed-expand">
            <button
              v-if="hasLocation && speed !== null"
              type="button"
              class="flex items-center pl-3 pr-2 h-[44px] whitespace-nowrap overflow-hidden select-none cursor-pointer hover:bg-blue-50/50 dark:hover:bg-blue-950/30 transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
              aria-label="Status Kecepatan GPS"
              @click="emit('locate')"
            >
              <div class="flex flex-col items-center justify-center min-w-[28px] text-center leading-none">
                <span class="text-xs font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                  {{ speed }}
                </span>
                <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-tight mt-0.5">
                  km/j
                </span>
              </div>
              <div class="w-px h-5 bg-blue-200/80 dark:bg-blue-900/60 ml-2.5 mr-0.5" aria-hidden="true" />
            </button>
          </Transition>

          <button
            type="button"
            class="min-w-[44px] min-h-[44px] flex items-center justify-center transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 z-10 active:scale-95"
            :class="hasLocation
              ? 'text-blue-600 dark:text-blue-400 bg-blue-50/60 dark:bg-blue-950/40 hover:bg-blue-100/70 dark:hover:bg-blue-900/50'
              : 'text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80'"
            :aria-pressed="hasLocation"
            aria-label="Toggle Live Tracking GPS"
            @click="emit('locate')"
          >
            <UIcon
              v-if="isLocating"
              name="i-lucide-loader-2"
              class="size-4 animate-spin text-blue-600 dark:text-blue-400"
            />
            <UIcon
              v-else-if="hasLocation && heading !== null"
              name="i-lucide-navigation"
              class="size-4 text-blue-600 dark:text-blue-400 transition-transform duration-150 ease-out"
              :style="{ transform: `rotate(${heading}deg)` }"
            />
            <UIcon
              v-else-if="hasLocation"
              name="i-lucide-locate-fixed"
              class="size-4 stroke-[2.25]"
            />
            <UIcon
              v-else
              name="i-lucide-locate"
              class="size-4 stroke-[2.25]"
            />
          </button>
        </div>
      </UTooltip>
    </div>

    <!-- 3. ZOOM IN / OUT PILL -->
    <div
      class="pointer-events-auto flex flex-col bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800 rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-200 overflow-hidden"
    >
      <UTooltip text="Perbesar">
        <button
          type="button"
          class="min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 active:scale-90 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 z-10"
          aria-label="Perbesar peta"
          @click="emit('zoom-in')"
        >
          <UIcon name="i-lucide-plus" class="size-4 stroke-[2.25]" />
        </button>
      </UTooltip>

      <div class="w-full h-px bg-gray-200/80 dark:bg-slate-800" aria-hidden="true" />

      <UTooltip text="Perkecil">
        <button
          type="button"
          class="min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 active:scale-90 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 z-10"
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

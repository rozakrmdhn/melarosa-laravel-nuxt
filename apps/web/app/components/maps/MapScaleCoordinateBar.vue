<script setup lang="ts">
export interface ScaleOption {
  label: string;
  value: number; // scale denominator
}

const SCALE_OPTIONS: ScaleOption[] = [
  { label: "1 : 1.000", value: 1000 },
  { label: "1 : 2.500", value: 2500 },
  { label: "1 : 5.000", value: 5000 },
  { label: "1 : 10.000", value: 10000 },
  { label: "1 : 25.000", value: 25000 },
  { label: "1 : 50.000", value: 50000 },
  { label: "1 : 100.000", value: 100000 },
  { label: "1 : 250.000", value: 250000 },
  { label: "1 : 500.000", value: 500000 },
  { label: "1 : 1.000.000", value: 1000000 },
];

const props = withDefaults(
  defineProps<{
    activeCoords?: { lng: number; lat: number } | null;
    scaleRatio?: string | null;
    mobileDrawerOpen?: boolean;
    mobileSheetSnap?: "min" | "peek" | "full" | string;
    clickedCoordinate?: [number, number] | null;
  }>(),
  {
    activeCoords: null,
    scaleRatio: "1 : 25.000",
    mobileDrawerOpen: false,
    mobileSheetSnap: "min",
    clickedCoordinate: null,
  }
);

const emit = defineEmits<{
  (e: "scale-target-mounted", el: HTMLElement): void;
  (e: "select-scale", scaleDenominator: number): void;
}>();

const scaleTargetEl = ref<HTMLElement | null>(null);
const scaleDropdownRef = ref<HTMLElement | null>(null);
const isScaleDropdownOpen = ref(false);
const coordDisplayFormat = ref<"dd" | "dms">("dd");

function toggleScaleDropdown() {
  isScaleDropdownOpen.value = !isScaleDropdownOpen.value;
}

function selectScale(option: ScaleOption) {
  isScaleDropdownOpen.value = false;
  emit("select-scale", option.value);
}

function handleClickOutside(event: MouseEvent) {
  if (scaleDropdownRef.value && !scaleDropdownRef.value.contains(event.target as Node)) {
    isScaleDropdownOpen.value = false;
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === "Escape" && isScaleDropdownOpen.value) {
    isScaleDropdownOpen.value = false;
  }
}

onMounted(() => {
  if (scaleTargetEl.value) {
    emit("scale-target-mounted", scaleTargetEl.value);
  }
  document.addEventListener("click", handleClickOutside);
  document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  document.removeEventListener("keydown", handleKeydown);
});

function toDms(dec: number, isLng: boolean): string {
  const abs = Math.abs(dec);
  const degrees = Math.floor(abs);
  const minutesDec = (abs - degrees) * 60;
  const minutes = Math.floor(minutesDec);
  const seconds = ((minutesDec - minutes) * 60).toFixed(1);
  const dir = isLng ? (dec >= 0 ? "E" : "W") : (dec >= 0 ? "N" : "S");
  return `${degrees}°${minutes.toString().padStart(2, "0")}'${seconds.padStart(4, "0")}" ${dir}`;
}

const formattedLatDisplay = computed(() => {
  const lat = props.activeCoords?.lat;
  if (lat === undefined || lat === null) return "-";
  const abs = Math.abs(lat).toFixed(5);
  const dir = lat >= 0 ? "N" : "S";
  return `${abs}° ${dir}`;
});

const formattedLngDisplay = computed(() => {
  const lng = props.activeCoords?.lng;
  if (lng === undefined || lng === null) return "-";
  const abs = Math.abs(lng).toFixed(5);
  const dir = lng >= 0 ? "E" : "W";
  return `${abs}° ${dir}`;
});

const formattedDmsDisplay = computed(() => {
  const c = props.activeCoords;
  if (!c) return "-";
  return `${toDms(c.lat, false)} ${toDms(c.lng, true)}`;
});

defineExpose({
  scaleTargetEl,
  coordDisplayFormat,
});
</script>

<template>
  <div
    class="absolute z-20 pointer-events-auto select-none left-1/2 -translate-x-1/2 max-w-[calc(100vw-120px)] sm:max-w-none transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)]"
    :class="[
      mobileDrawerOpen && mobileSheetSnap === 'full'
        ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
        : mobileDrawerOpen && mobileSheetSnap === 'peek'
          ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4 md:opacity-100'
          : mobileDrawerOpen && mobileSheetSnap === 'min'
            ? 'bottom-[calc(200px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
            : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100 pointer-events-auto'
    ]"
  >
    <div
      class="group relative flex items-center gap-1 sm:gap-2 px-2 py-0.5 sm:px-2.5 sm:py-0.5 rounded-full bg-white/92 dark:bg-[#0b1322]/92 backdrop-blur-md border border-slate-200/80 dark:border-white/10 shadow-md shadow-slate-900/5 dark:shadow-black/40 text-xs transition-colors hover:border-slate-300 dark:hover:border-white/20 h-6 sm:h-6.5 leading-none"
    >
      <!-- 1. Scale Bar Graphic Section (Selalu tampil di mobile & desktop) -->
      <div class="flex items-center shrink-0" title="Skala Grafis Peta">
        <div
          ref="scaleTargetEl"
          class="flex items-center justify-center min-w-[44px] sm:min-w-[55px] h-3.5 scale-line-container"
        />
      </div>

      <!-- Vertical Divider -->
      <div class="h-2.5 w-px bg-slate-200 dark:bg-white/15 shrink-0" />

      <!-- 2. Scale Ratio Dropdown Select (Gaya QGIS / ArcGIS - Minimalis) -->
      <div ref="scaleDropdownRef" class="relative flex items-center shrink-0">
        <button
          type="button"
          class="flex items-center gap-0.5 text-[10px] sm:text-[10.5px] font-semibold text-slate-700 dark:text-slate-200 tabular-nums shrink-0 px-1 py-0.5 rounded-md hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer focus-visible:ring-1.5 focus-visible:ring-blue-500 focus:outline-hidden leading-none"
          :class="{ 'bg-slate-100 dark:bg-white/10 text-blue-600 dark:text-blue-400': isScaleDropdownOpen }"
          title="Pilih Skala Kartografis Standar"
          :aria-expanded="isScaleDropdownOpen"
          aria-haspopup="listbox"
          @click.stop="toggleScaleDropdown"
        >
          <span>{{ scaleRatio || '1 : 25.000' }}</span>
          <UIcon
            name="i-lucide-chevron-up"
            class="size-2.5 text-slate-400 dark:text-slate-500 transition-transform duration-200"
            :class="{ 'rotate-180 text-blue-600 dark:text-blue-400': isScaleDropdownOpen }"
          />
        </button>

        <!-- Dropdown Popover (Opens Upward towards map canvas) -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          enter-from-class="opacity-0 translate-y-1.5 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition-all duration-100 ease-in"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 translate-y-1.5 scale-95"
        >
          <div
            v-if="isScaleDropdownOpen"
            class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 z-50 min-w-[120px] py-1 bg-white/98 dark:bg-[#0b1322]/98 backdrop-blur-md rounded-xl border border-slate-200/90 dark:border-white/10 shadow-xl shadow-slate-900/15 dark:shadow-black/60 max-h-[210px] overflow-y-auto"
            role="listbox"
            aria-label="Daftar Skala Kartografis Standar"
          >
            <button
              v-for="scale in SCALE_OPTIONS"
              :key="scale.value"
              type="button"
              role="option"
              :aria-selected="scaleRatio === scale.label"
              class="w-full flex items-center justify-between gap-1.5 px-2 py-0.5 text-left text-[10px] transition-colors cursor-pointer rounded-md leading-tight"
              :class="scaleRatio === scale.label
                ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-bold'
                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10'"
              @click.stop="selectScale(scale)"
            >
              <span class="tabular-nums">{{ scale.label }}</span>
              <UIcon
                v-if="scaleRatio === scale.label"
                name="i-lucide-check"
                class="size-2.5 text-blue-600 dark:text-blue-400 shrink-0"
              />
            </button>
          </div>
        </Transition>
      </div>

      <!-- 3. Desktop Coordinates Section (Disembunyikan di mobile, tampil di desktop md:) -->
      <div class="hidden md:flex items-center gap-1.5 shrink-0 min-w-0">
        <!-- Divider -->
        <div class="h-2.5 w-px bg-slate-200 dark:bg-white/15 shrink-0" />

        <div class="flex items-center gap-1 shrink-0 min-w-0">
          <!-- Coordinate Values (Tampilan Bersih Read-Only Tanpa Tombol Salin) -->
          <div
            class="flex items-center text-[10px] sm:text-[10.5px] text-slate-700 dark:text-slate-200 font-medium px-0.5 select-text leading-none"
            title="Koordinat Kursor Peta"
          >
            <span v-if="coordDisplayFormat === 'dd'" class="tabular-nums flex items-center">
              <span>{{ formattedLatDisplay }}</span>
              <span class="mx-0.5 text-slate-300 dark:text-slate-600 font-normal">•</span>
              <span>{{ formattedLngDisplay }}</span>
            </span>

            <span v-else class="tabular-nums text-[10px] truncate max-w-[140px]">
              {{ formattedDmsDisplay }}
            </span>
          </div>

          <!-- Format Toggle Button (DD / DMS - Minimalis) -->
          <button
            type="button"
            class="min-h-[19px] min-w-[22px] px-1 py-0 rounded-md text-[9px] font-bold transition-all cursor-pointer active:scale-95 flex items-center justify-center shrink-0 focus-visible:ring-1.5 focus-visible:ring-blue-500 focus:outline-hidden leading-none"
            :class="coordDisplayFormat === 'dd'
              ? 'bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-white/15'
              : 'bg-blue-600 text-white shadow-xs'"
            :title="`Beralih ke format ${coordDisplayFormat === 'dd' ? 'DMS (Derajat Menit Detik)' : 'DD (Derajat Desimal)'}`"
            @click="coordDisplayFormat = coordDisplayFormat === 'dd' ? 'dms' : 'dd'"
          >
            {{ coordDisplayFormat.toUpperCase() }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ScaleLine Control styling inside target container */
:deep(.ol-scale-line) {
  position: relative !important;
  bottom: auto !important;
  left: auto !important;
  right: auto !important;
  top: auto !important;
  background: transparent !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  box-shadow: none !important;
}

:deep(.ol-scale-line-inner) {
  color: #334155;
  border: 1.5px solid #475569;
  border-top: none;
  font-size: 9px;
  font-weight: 700;
  font-family: inherit;
  font-variant-numeric: tabular-nums;
  text-align: center;
  margin: 0 auto;
  padding: 0 3px 0.5px;
  background: transparent !important;
  line-height: 1.1;
  transition: color 0.2s ease, border-color 0.2s ease;
}

:global(.dark) :deep(.ol-scale-line),
.dark :deep(.ol-scale-line) {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

:global(.dark) :deep(.ol-scale-line-inner),
.dark :deep(.ol-scale-line-inner) {
  color: #e2e8f0;
  border: 1.5px solid #94a3b8;
  border-top: none;
}
</style>

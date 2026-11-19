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
    isLiveCursor?: boolean;
    scaleRatio?: string | null;
    leftPanelOpen?: boolean;
    mobileDrawerOpen?: boolean;
    mobileSheetSnap?: "min" | "peek" | "full" | string;
    clickedCoordinate?: [number, number] | null;
  }>(),
  {
    activeCoords: null,
    isLiveCursor: false,
    scaleRatio: "1 : 25.000",
    leftPanelOpen: false,
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
    class="absolute z-20 pointer-events-none select-none flex justify-center transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] left-[68px] right-[68px] md:right-[76px]"
    :class="[
      leftPanelOpen ? 'md:left-[444px] lg:left-[496px]' : 'md:left-[76px]',
      mobileDrawerOpen && mobileSheetSnap === 'full'
        ? 'opacity-0 translate-y-4 md:opacity-100 md:translate-y-0 md:bottom-4'
        : mobileDrawerOpen && mobileSheetSnap === 'peek'
          ? 'bottom-[calc(50dvh+16px)] opacity-100 md:bottom-4'
          : mobileDrawerOpen && mobileSheetSnap === 'min'
            ? 'bottom-[calc(156px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 md:bottom-4'
            : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100'
    ]"
  >
    <div
      class="group relative pointer-events-auto flex items-center gap-2 sm:gap-2.5 px-2.5 sm:px-3 rounded-xl bg-white/95 dark:bg-[#0c1522]/95 border border-slate-200/90 dark:border-white/10 shadow-md shadow-slate-900/10 dark:shadow-black/45 text-xs transition-colors hover:border-slate-300 dark:hover:border-white/20 h-8 sm:h-9 max-w-full"
    >
      <!-- 1. Scale Bar Graphic Section (Selalu tampil di mobile & desktop) -->
      <div class="flex items-center shrink-0" title="Skala Grafis Peta">
        <div
          ref="scaleTargetEl"
          class="flex items-end justify-center min-w-[48px] sm:min-w-[60px] h-4 scale-line-container"
        />
      </div>

      <!-- Vertical Divider -->
      <div class="h-4 w-px bg-slate-200 dark:bg-white/15 shrink-0" aria-hidden="true" />

      <!-- 2. Scale Ratio Dropdown Select -->
      <div ref="scaleDropdownRef" class="relative flex items-center shrink-0">
        <button
          type="button"
          class="relative flex items-center gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200 tabular-nums shrink-0 px-1.5 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer after:absolute after:-inset-y-2 after:-inset-x-1 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400 leading-none"
          :class="{ 'bg-[#2B5B9E]/10 dark:bg-sky-500/15 text-[#2B5B9E] dark:text-sky-300': isScaleDropdownOpen }"
          title="Pilih Skala Kartografis Standar"
          :aria-expanded="isScaleDropdownOpen"
          aria-haspopup="listbox"
          @click.stop="toggleScaleDropdown"
        >
          <span>{{ scaleRatio || '1 : 25.000' }}</span>
          <UIcon
            name="i-lucide-chevron-up"
            class="size-3 text-slate-500 dark:text-slate-400 transition-transform duration-200"
            :class="{ 'rotate-180 text-[#2B5B9E] dark:text-sky-300': isScaleDropdownOpen }"
            aria-hidden="true"
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
            class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-50 min-w-[136px] p-1 bg-white dark:bg-[#0c1522] rounded-xl border border-slate-200/90 dark:border-white/10 shadow-xl shadow-slate-900/15 dark:shadow-black/60 max-h-[240px] overflow-y-auto"
            role="listbox"
            aria-label="Daftar Skala Kartografis Standar"
          >
            <button
              v-for="scale in SCALE_OPTIONS"
              :key="scale.value"
              type="button"
              role="option"
              :aria-selected="scaleRatio === scale.label"
              class="w-full min-h-[40px] flex items-center justify-between gap-2 px-2.5 text-left text-xs transition-colors cursor-pointer rounded-lg leading-tight focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-[#2B5B9E]"
              :class="scaleRatio === scale.label
                ? 'bg-[#2B5B9E]/10 dark:bg-sky-500/15 text-[#2B5B9E] dark:text-sky-300 font-bold'
                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10'"
              @click.stop="selectScale(scale)"
            >
              <span class="tabular-nums">{{ scale.label }}</span>
              <UIcon
                v-if="scaleRatio === scale.label"
                name="i-lucide-check"
                class="size-3.5 text-[#2B5B9E] dark:text-sky-300 shrink-0"
                aria-hidden="true"
              />
            </button>
          </div>
        </Transition>
      </div>

      <!-- 3. Desktop Coordinates Section (Responsif terhadap panel kiri agar tidak berhimpitan) -->
      <div
        class="items-center gap-2 shrink-0 min-w-0"
        :class="leftPanelOpen ? 'hidden lg:flex' : 'hidden md:flex'"
      >
        <!-- Divider -->
        <div class="h-4 w-px bg-slate-200 dark:bg-white/15 shrink-0" aria-hidden="true" />

        <div class="flex items-center gap-1.5 shrink-0 min-w-0">
          <!-- Coordinate Values (Read-Only, Non-Jitter, Non-Truncated) -->
          <div
            class="flex items-center gap-1.5 font-mono text-[11px] font-medium tabular-nums tracking-tight text-slate-700 dark:text-slate-200 select-text leading-none whitespace-nowrap"
            :title="isLiveCursor ? 'Koordinat Kursor Peta (WGS 84)' : 'Koordinat Titik Tengah Peta (WGS 84)'"
          >
            <UIcon
              name="i-lucide-crosshair"
              class="size-3.5 shrink-0 transition-colors"
              :class="isLiveCursor ? 'text-[#2B5B9E] dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'"
              aria-hidden="true"
            />
            <span
              v-if="coordDisplayFormat === 'dd'"
              class="inline-flex items-center min-w-[172px] justify-center"
            >
              <span>{{ formattedLatDisplay }}</span>
              <span class="mx-1 text-slate-400 dark:text-slate-500 font-normal">·</span>
              <span>{{ formattedLngDisplay }}</span>
            </span>

            <span v-else class="inline-flex items-center min-w-[192px] justify-center">
              {{ formattedDmsDisplay }}
            </span>
          </div>

          <!-- Format Toggle Button (DD / DMS) -->
          <button
            type="button"
            class="relative h-6 min-w-[30px] px-1.5 rounded-md text-[10px] font-bold tracking-wide transition-all cursor-pointer active:scale-95 flex items-center justify-center shrink-0 after:absolute after:-inset-y-2.5 after:-inset-x-1 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400 leading-none"
            :class="coordDisplayFormat === 'dd'
              ? 'bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-200 hover:bg-slate-200/80 dark:hover:bg-white/15'
              : 'bg-[#2B5B9E] text-white shadow-xs'"
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
  color: #1e293b;
  border: 1.5px solid #475569;
  border-top: none;
  font-size: 10.5px;
  font-weight: 700;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  text-align: center;
  margin: 0 auto;
  padding: 0 5px 1.5px;
  background: transparent !important;
  line-height: 1.15;
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

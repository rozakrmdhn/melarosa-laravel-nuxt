<script setup lang="ts">
import type { IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";
import BottomLocationBar from "~/components/maps/BottomLocationBar.vue";

const props = withDefaults(
  defineProps<{
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    identifiedFeatures?: IdentifiedFeature[];
    isIdentifying?: boolean;
  }>(),
  {
    clickedCoordinate: null,
    locationLabel: null,
    identifiedFeatures: () => [],
    isIdentifying: false,
  }
);

const emit = defineEmits<{
  (e: "close"): void;
  (e: "clear-pin"): void;
  (e: "select-feature", feature: IdentifiedFeature): void;
  (e: "share"): void;
}>();

const primaryFeature = computed(() => {
  if (!props.identifiedFeatures || props.identifiedFeatures.length === 0) return null;
  return props.identifiedFeatures[0];
});

const primaryFeatureName = computed(() => {
  if (!primaryFeature.value) return null;
  const p = primaryFeature.value.properties || {};

  const nameEntry = Object.entries(p).find(
    ([k, v]) => typeof v === "string" && v.trim() && (k.toLowerCase().includes("nam") || k.toLowerCase().includes("title"))
  );
  if (nameEntry) return String(nameEntry[1]);

  if (primaryFeature.value.id) return primaryFeature.value.id;

  const firstValid = Object.values(p).find((v) => v !== null && v !== undefined && v !== "");
  return firstValid ? String(firstValid) : null;
});

const identifyTitle = computed(() => {
  if (primaryFeatureName.value) return primaryFeatureName.value;
  if (props.locationLabel) return props.locationLabel;
  if (props.isIdentifying) return "Memeriksa Fitur...";
  return "Identifikasi Fitur";
});

const identifySubtitle = computed(() => {
  if (primaryFeature.value?.layerTitle) {
    const instansi = primaryFeature.value.layerInstansi || primaryFeature.value.layerAttribution;
    return instansi ? `${primaryFeature.value.layerTitle} · ${instansi}` : primaryFeature.value.layerTitle;
  }
  if (props.clickedCoordinate) {
    return `${props.clickedCoordinate[1].toFixed(5)}, ${props.clickedCoordinate[0].toFixed(5)} · WGS 84`;
  }
  return "Ketuk titik pada area peta Bojonegoro";
});
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 select-none">
    <!-- ═══ HEADER HERO SECTION IDENTIK DENGAN SEARCHITEMABOUTVIEW & LAYERDETAILVIEW ═══ -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-white/5 flex items-start justify-between gap-3 shrink-0 bg-white dark:bg-[#09111e]">
      <div class="flex items-start gap-3 min-w-0 flex-1">
        <!-- Circular Badge Avatar -->
        <div class="size-11 rounded-full bg-blue-600 dark:bg-blue-500 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider">
          <UIcon v-if="props.isIdentifying" name="i-lucide-loader-2" class="size-5 animate-spin" />
          <UIcon v-else-if="primaryFeature" name="i-lucide-map-pin" class="size-5" />
          <UIcon v-else name="i-lucide-crosshair" class="size-5" />
        </div>

        <!-- Title & Subtitle -->
        <div class="min-w-0 flex-1 pt-0.5">
          <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight truncate">
            {{ identifyTitle }}
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal truncate">
            {{ identifySubtitle }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-1.5 shrink-0 mt-0.5">
        <!-- Tombol Bersihkan Pin -->
        <button
          v-if="props.clickedCoordinate"
          type="button"
          class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-red-600 dark:hover:text-red-400 px-2.5 py-1.5 rounded-lg border border-slate-200/80 dark:border-white/10 hover:border-red-200 dark:hover:border-red-900/50 bg-white dark:bg-white/5 transition-all cursor-pointer min-h-[44px] active:scale-95"
          aria-label="Bersihkan pin penanda di peta"
          title="Bersihkan pin penanda di peta"
          @click="emit('clear-pin')"
        >
          <UIcon name="i-lucide-map-pin-off" class="size-4 text-red-500 shrink-0" />
          <span class="hidden sm:inline">Bersihkan Pin</span>
        </button>

        <!-- Circle X Close Button -->
        <button
          type="button"
          class="size-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-95 shrink-0"
          aria-label="Tutup identifikasi fitur dan kembali ke daftar layer"
          title="Tutup identifikasi fitur dan kembali ke daftar layer"
          @click="emit('close')"
        >
          <UIcon name="i-lucide-x" class="size-4" />
        </button>
      </div>
    </div>

    <!-- ═══ BODY CONTENT: TERINTEGRASI DENGAN BOTTOMLOCATIONBAR DRAWER MODE ═══ -->
    <div class="flex-1 overflow-y-auto min-h-0">
      <BottomLocationBar
        :is-mobile-drawer="true"
        :clicked-coordinate="props.clickedCoordinate"
        :location-label="props.locationLabel"
        :identified-features="props.identifiedFeatures"
        :is-identifying="props.isIdentifying"
        @close="emit('close')"
        @select-feature="(f) => emit('select-feature', f)"
        @share="emit('share')"
      />
    </div>
  </div>
</template>

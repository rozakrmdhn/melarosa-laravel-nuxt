<script setup lang="ts">
import IdentifyFeatures, { type IdentifiedFeature } from "./IdentifyFeatures.vue";

const props = withDefaults(
  defineProps<{
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    isMobileDrawer?: boolean;
    initialExpanded?: boolean;
    identifiedFeatures?: IdentifiedFeature[];
    isIdentifying?: boolean;
  }>(),
  {
    clickedCoordinate: null,
    locationLabel: null,
    isMobileDrawer: true, // Default to true: embedded natively inside LeftPanel & Mobile Bottom Sheet
    initialExpanded: true,
    identifiedFeatures: () => [],
    isIdentifying: false,
  }
);

const emit = defineEmits<{
  (e: "close"): void;
  (e: "update:expanded", val: boolean): void;
  (e: "select-feature", feature: IdentifiedFeature): void;
}>();

const toast = useToast();
const copied = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;
const isExpanded = ref(props.initialExpanded);
const activeFeatureIndex = ref(0);

watch(isExpanded, (val) => {
  emit("update:expanded", val);
});

watch(
  () => props.identifiedFeatures,
  (newFeatures) => {
    activeFeatureIndex.value = 0;
    if (newFeatures && newFeatures.length > 0) {
      isExpanded.value = true;
    }
  },
  { deep: true }
);

onBeforeUnmount(() => {
  if (copyTimer) clearTimeout(copyTimer);
});

const primaryFeature = computed(() => {
  if (!props.identifiedFeatures || props.identifiedFeatures.length === 0) return null;
  return props.identifiedFeatures[activeFeatureIndex.value] || props.identifiedFeatures[0];
});

const effectiveCoordinate = computed<[number, number] | null>(() => {
  const coord = primaryFeature.value?.coordinate ?? props.clickedCoordinate ?? null;
  if (!coord || coord.length < 2) return null;
  const [lng, lat] = coord;
  if (!Number.isFinite(lng) || !Number.isFinite(lat)) return null;
  return [lng, lat];
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

const formattedLat = computed(() => {
  if (!effectiveCoordinate.value) return "-";
  return effectiveCoordinate.value[1].toFixed(6);
});

const formattedLng = computed(() => {
  if (!effectiveCoordinate.value) return "-";
  return effectiveCoordinate.value[0].toFixed(6);
});

const formattedPoint = computed(() => {
  if (!effectiveCoordinate.value) return "";
  return `${formattedLat.value}, ${formattedLng.value}`;
});

const googleMapsUrl = computed(() => {
  if (!effectiveCoordinate.value) return "#";
  return `https://www.google.com/maps?q=${formattedLat.value},${formattedLng.value}`;
});

function fallbackCopy(text: string) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.opacity = "0";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand("copy");
  } catch (err) {
    console.warn("Gagal menyalin teks:", err);
  }
  document.body.removeChild(textArea);
}

function copyCoordinate() {
  if (!formattedPoint.value) return;
  const text = formattedPoint.value;

  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }

  copied.value = true;
  toast.add({
    title: "Titik Koordinat Disalin",
    description: `${text} berhasil disalin ke clipboard`,
    color: "success",
    icon: "i-ion-checkmark-circle",
  });

  if (copyTimer) clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    copied.value = false;
  }, 2000);
}
</script>

<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 1. EMBEDDED MODE (Default): Konten tunggal tanpa tab                  -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-if="isMobileDrawer"
    class="w-full flex flex-col px-4 sm:px-5 pt-3.5 pb-6 space-y-4"
  >
    <IdentifyFeatures
      v-model:selected-index="activeFeatureIndex"
      :features="identifiedFeatures"
      :loading="isIdentifying"
      :has-point="Boolean(effectiveCoordinate)"
      @select-feature="(f) => emit('select-feature', f)"
    />

    <section
      v-if="effectiveCoordinate"
      aria-label="Koordinat titik dan navigasi Google Maps"
      class="rounded-2xl border border-slate-200/85 dark:border-white/10 bg-slate-50/85 dark:bg-white/[0.03] p-3.5 space-y-3"
    >
      <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
          <UIcon name="i-ion-location-outline" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span>Titik Koordinat</span>
        </span>
        <span class="text-xs font-bold tabular-nums text-slate-900 dark:text-slate-100 select-text">
          {{ formattedPoint }}
        </span>
      </div>

      <div class="grid grid-cols-2 gap-2.5">
        <button
          type="button"
          class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border transition-all cursor-pointer flex items-center justify-center gap-1.5 min-w-0 active:scale-[0.98] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
          :class="copied
            ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300'
            : 'border-slate-300 dark:border-white/15 bg-white dark:bg-[#0f172a] text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-white/10'"
          @click="copyCoordinate"
        >
          <UIcon :name="copied ? 'i-ion-checkmark-circle' : 'i-ion-copy-outline'" class="size-4 shrink-0" />
          <span class="truncate">{{ copied ? 'Tersalin' : 'Salin Titik' }}</span>
        </button>

        <a
          :href="googleMapsUrl"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Buka titik koordinat di Google Maps"
          class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition-all cursor-pointer flex items-center justify-center gap-1.5 min-w-0 active:scale-[0.98] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
        >
          <UIcon name="i-ion-open-outline" class="size-4 shrink-0" />
          <span class="truncate">Google Maps</span>
        </a>
      </div>
    </section>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 2. DESKTOP STANDALONE FLOATING MODE (Fallback tanpa tab)              -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-else
    class="flex flex-col bg-white/95 dark:bg-[#09111e]/95 backdrop-blur-xl border border-slate-200/90 dark:border-white/10 shadow-2xl rounded-2xl overflow-hidden transition-all duration-300 ease-out w-full max-w-[calc(100vw-24px)] sm:w-[400px]"
  >
    <!-- Top Summary Pill Bar -->
    <div
      class="flex items-center justify-between h-12 px-3 bg-slate-50/60 dark:bg-white/[0.02] cursor-pointer group select-none"
      @click="isExpanded = !isExpanded"
    >
      <div class="flex items-center gap-2.5 min-w-0 flex-1">
        <div
          class="size-8 rounded-xl flex items-center justify-center shrink-0 shadow-xs transition-colors"
          :class="primaryFeature
            ? 'bg-blue-600 text-white'
            : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400'"
        >
          <UIcon :name="isIdentifying ? 'i-ion-refresh-outline' : 'i-ion-location'" class="size-4" :class="isIdentifying ? 'animate-spin' : ''" />
        </div>

        <div class="min-w-0 flex-1 pr-1">
          <div v-if="primaryFeatureName" class="truncate">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
              {{ primaryFeatureName }}
            </span>
            <span class="text-xs text-slate-600 dark:text-slate-400 tabular-nums truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
          <div v-else-if="locationLabel" class="truncate">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
              {{ locationLabel }}
            </span>
            <span class="text-xs text-slate-600 dark:text-slate-400 tabular-nums truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
          <div v-else class="truncate">
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 block">
              {{ isIdentifying ? 'Memeriksa fitur...' : 'Koordinat titik' }}
            </span>
            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 tabular-nums truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-1 shrink-0" @click.stop>
        <button
          type="button"
          class="size-10 rounded-xl flex items-center justify-center transition-all cursor-pointer text-slate-600 dark:text-slate-300 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-white/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
          :class="isExpanded ? 'bg-blue-50 text-blue-600' : ''"
          :aria-expanded="isExpanded"
          aria-label="Toggle detail informasi"
          @click="isExpanded = !isExpanded"
        >
          <UIcon
            name="i-ion-chevron-up"
            class="size-4 transition-transform duration-300"
            :class="isExpanded ? 'rotate-180' : ''"
          />
        </button>

        <button
          type="button"
          class="size-10 rounded-xl flex items-center justify-center transition-colors cursor-pointer text-slate-600 hover:text-slate-800 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
          aria-label="Tutup panel informasi"
          @click="emit('close')"
        >
          <UIcon name="i-ion-close" class="size-4" />
        </button>
      </div>
    </div>

    <Transition name="panel-expand">
      <div
        v-show="isExpanded"
        class="border-t border-slate-100 dark:border-white/10 p-3.5 space-y-4 max-h-[420px] overflow-y-auto"
      >
        <IdentifyFeatures
          v-model:selected-index="activeFeatureIndex"
          :features="identifiedFeatures"
          :loading="isIdentifying"
          :has-point="Boolean(effectiveCoordinate)"
          @select-feature="emit('select-feature', $event)"
        />

        <section
          v-if="effectiveCoordinate"
          aria-label="Koordinat titik dan navigasi Google Maps"
          class="rounded-2xl border border-slate-200/85 dark:border-white/10 bg-slate-50/85 dark:bg-white/[0.03] p-3 space-y-2.5"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Titik Koordinat</span>
            <span class="text-xs font-bold tabular-nums text-slate-900 dark:text-slate-100 select-text">{{ formattedPoint }}</span>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-white/15 bg-white dark:bg-[#0f172a] text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-white/10 transition-all cursor-pointer flex items-center justify-center gap-1.5"
              @click="copyCoordinate"
            >
              <UIcon :name="copied ? 'i-ion-checkmark-circle' : 'i-ion-copy-outline'" class="size-4 shrink-0" />
              <span class="truncate">{{ copied ? 'Tersalin' : 'Salin Titik' }}</span>
            </button>

            <a
              :href="googleMapsUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition-all cursor-pointer flex items-center justify-center gap-1.5"
            >
              <UIcon name="i-ion-open-outline" class="size-4 shrink-0" />
              <span class="truncate">Google Maps</span>
            </a>
          </div>
        </section>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Transisi expand/collapse panel detail */
.panel-expand-enter-active,
.panel-expand-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}
.panel-expand-enter-from,
.panel-expand-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
}
</style>

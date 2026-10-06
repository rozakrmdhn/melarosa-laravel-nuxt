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
    isMobileDrawer: false,
    initialExpanded: false,
    identifiedFeatures: () => [],
    isIdentifying: false,
  }
);

const emit = defineEmits<{
  (e: "close"): void;
  (e: "update:expanded", val: boolean): void;
  (e: "select-feature", feature: IdentifiedFeature): void;
  (e: "share"): void;
}>();

const toast = useToast();
const copied = ref(false);
let copyTimer: any = null;
const isExpanded = ref(props.initialExpanded);
const activeTab = ref<"features" | "coordinates">("features");

watch(isExpanded, (val) => {
  emit("update:expanded", val);
});

// Jika ada fitur baru teridentifikasi, otomatis beralih ke tab fitur dan buka panel
watch(
  () => props.identifiedFeatures,
  (newFeatures) => {
    if (newFeatures && newFeatures.length > 0) {
      activeTab.value = "features";
      isExpanded.value = true;
    }
  },
  { deep: true }
);

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

const formattedLat = computed(() => {
  if (!props.clickedCoordinate) return "-";
  return props.clickedCoordinate[1].toFixed(6);
});

const formattedLng = computed(() => {
  if (!props.clickedCoordinate) return "-";
  return props.clickedCoordinate[0].toFixed(6);
});

// Proyeksi koordinat EPSG:3857 (Web Mercator) dalam satuan meter
const webMercatorCoords = computed(() => {
  if (!props.clickedCoordinate) return null;
  const [lng, lat] = props.clickedCoordinate;
  const x = (lng * 20037508.34) / 180;
  const latRad = (lat * Math.PI) / 180;
  const y = (Math.log(Math.tan(Math.PI / 4 + latRad / 2)) * 20037508.34) / Math.PI;
  return {
    x: Math.round(x).toLocaleString("id-ID"),
    y: Math.round(y).toLocaleString("id-ID"),
  };
});

const googleMapsUrl = computed(() => {
  if (!props.clickedCoordinate) return "#";
  const [lng, lat] = props.clickedCoordinate;
  return `https://www.google.com/maps?q=${lat},${lng}`;
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
    console.error("Gagal menyalin teks:", err);
  }
  document.body.removeChild(textArea);
}

function copyCoordinate() {
  if (!props.clickedCoordinate) return;
  const [lng, lat] = props.clickedCoordinate;
  const text = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }

  copied.value = true;
  toast.add({
    title: "Koordinat Disalin",
    description: `${text} berhasil disalin ke clipboard`,
    color: "success",
    icon: "i-lucide-check",
  });

  clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    copied.value = false;
  }, 2000);
}
</script>

<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 1. MOBILE DRAWER MODE (Rendered inside MapMobileBottomSheet)            -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-if="isMobileDrawer"
    class="h-full flex flex-col p-4 overflow-y-auto space-y-3.5 select-none"
  >
    <!-- Tab Switcher Nav (Fitur WMS vs Koordinat) -->
    <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 shrink-0">
      <button
        type="button"
        class="flex-1 flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer min-h-[36px]"
        :class="activeTab === 'features'
          ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activeTab = 'features'"
      >
        <UIcon name="i-lucide-info" class="size-3.5" />
        <span>Atribut Fitur WMS</span>
        <span
          v-if="identifiedFeatures && identifiedFeatures.length > 0"
          class="size-4.5 rounded-full text-[10px] flex items-center justify-center bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold"
        >
          {{ identifiedFeatures.length }}
        </span>
      </button>

      <button
        type="button"
        class="flex-1 flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer min-h-[36px]"
        :class="activeTab === 'coordinates'
          ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activeTab = 'coordinates'"
      >
        <UIcon name="i-lucide-crosshair" class="size-3.5" />
        <span>Koordinat Titik</span>
      </button>
    </div>

    <!-- 1A. Tab Konten: IdentifyFeatures WMS -->
    <div v-show="activeTab === 'features'" class="space-y-3">
      <IdentifyFeatures
        :features="identifiedFeatures"
        :loading="isIdentifying"
      />
    </div>

    <!-- 1B. Tab Konten: Detail Koordinat Titik -->
    <div v-show="activeTab === 'coordinates'" class="space-y-3">
      <!-- Empty State Koordinat jika belum ada titik -->
      <div
        v-if="!clickedCoordinate"
        class="py-8 px-4 text-center space-y-3"
      >
        <div class="size-12 rounded-2xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto">
          <UIcon name="i-lucide-mouse-pointer-click" class="size-6 stroke-[1.5]" />
        </div>
        <div class="space-y-1">
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
            Belum ada titik dipilih
          </p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed max-w-[240px] mx-auto">
            Ketuk pada area peta untuk memeriksa koordinat geografis wilayah Bojonegoro.
          </p>
        </div>
      </div>

      <!-- Kartu Koordinat Aktif -->
      <div v-else class="space-y-3">
        <!-- Title Card if locationLabel exists -->
        <div
          v-if="locationLabel"
          class="p-3 rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] flex items-center gap-2.5"
        >
          <div class="size-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-info" class="size-4" />
          </div>
          <div class="min-w-0">
            <p class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">Lokasi Terpilih</p>
            <p class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">{{ locationLabel }}</p>
          </div>
        </div>

        <!-- Geographic Coordinates Card -->
        <div class="p-3.5 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
              <UIcon name="i-lucide-crosshair" class="size-3.5 text-blue-600 dark:text-blue-400" />
              Koordinat Geografis
            </span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
              EPSG:4326
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10">
              <p class="text-[10px] text-slate-400 font-medium">Latitude (Lintang)</p>
              <p class="font-mono font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ formattedLat }}</p>
            </div>
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10">
              <p class="text-[10px] text-slate-400 font-medium">Longitude (Bujur)</p>
              <p class="font-mono font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ formattedLng }}</p>
            </div>
          </div>

          <!-- Projected Web Mercator info -->
          <div v-if="webMercatorCoords" class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 space-y-1">
            <div class="flex justify-between items-center text-[10px] text-slate-400">
              <span class="font-medium">Proyeksi Web Mercator</span>
              <span class="font-mono font-bold text-slate-600 dark:text-slate-400">EPSG:3857</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[11px] font-mono font-semibold text-slate-700 dark:text-slate-300">
              <div>X: {{ webMercatorCoords.x }} m</div>
              <div>Y: {{ webMercatorCoords.y }} m</div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-2 pt-1">
            <button
              type="button"
              class="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 active:scale-95 shadow-xs transition-all cursor-pointer min-h-[44px]"
              @click="copyCoordinate"
            >
              <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
              <span>{{ copied ? 'Tersalin' : 'Salin Koordinat' }}</span>
            </button>
            <a
              :href="googleMapsUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] active:scale-95 transition-all cursor-pointer min-h-[44px]"
            >
              <UIcon name="i-lucide-external-link" class="size-4 text-slate-400" />
              <span>Google Maps</span>
            </a>
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] hover:text-blue-600 dark:hover:text-blue-400 active:scale-95 transition-all cursor-pointer min-h-[44px]"
              aria-label="Bagikan Peta Lokasi"
              @click="emit('share')"
            >
              <UIcon name="i-lucide-share-2" class="size-4 text-slate-400" />
              <span>Bagikan</span>
            </button>
          </div>
        </div>

        <!-- Spatial Reference Card -->
        <div class="p-3.5 rounded-2xl border border-slate-100 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] space-y-2 text-xs">
          <p class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
            Sistem Referensi Geospasial
          </p>
          <div class="space-y-1.5 text-[11px] text-slate-500 dark:text-slate-400">
            <div class="flex justify-between">
              <span>Datum Geodetik:</span>
              <span class="font-mono font-medium text-slate-800 dark:text-slate-200">WGS 84 (EPSG:4326)</span>
            </div>
            <div class="flex justify-between">
              <span>Sistem Proyeksi:</span>
              <span class="font-mono font-medium text-slate-800 dark:text-slate-200">Web Mercator (EPSG:3857)</span>
            </div>
            <div class="flex justify-between">
              <span>Cakupan Wilayah:</span>
              <span class="font-medium text-blue-600 dark:text-blue-400">Kabupaten Bojonegoro</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 2. DESKTOP MODE: FLOATING BOTTOM-CENTER BAR WITH EXPAND/COLLAPSE       -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-else
    class="flex flex-col bg-white/95 dark:bg-[#09111e]/95 backdrop-blur-xl border border-slate-200/90 dark:border-white/10 shadow-2xl rounded-2xl overflow-hidden select-none transition-all duration-300 ease-out w-[380px] sm:w-[440px]"
  >
    <!-- Top Summary Pill Bar (Always visible) -->
    <div
      class="flex items-center justify-between h-12 px-3 bg-slate-50/60 dark:bg-white/[0.02] cursor-pointer group"
      @click="isExpanded = !isExpanded"
    >
      <div class="flex items-center gap-2.5 min-w-0 flex-1">
        <!-- Pin or Layer icon with pulse accent -->
        <div
          class="size-8 rounded-xl flex items-center justify-center shrink-0 shadow-xs relative transition-colors"
          :class="primaryFeature
            ? 'bg-blue-600 text-white'
            : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400'"
        >
          <UIcon name="i-lucide-info" class="size-4 stroke-[2.25]" />
          <span
            v-if="isIdentifying"
            class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-amber-500 animate-ping"
          />
          <span
            v-else
            class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-500 animate-pulse"
          />
        </div>

        <!-- Location, Feature, or Coordinate Title -->
        <div class="min-w-0 flex-1 pr-1">
          <!-- 1. Jika ada fitur WMS yang teridentifikasi -->
          <div v-if="primaryFeatureName" class="truncate">
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
                {{ primaryFeatureName }}
              </span>
              <span
                v-if="primaryFeature?.layerTitle"
                class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 truncate max-w-[130px]"
              >
                {{ primaryFeature.layerTitle }}
              </span>
            </div>
            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>

          <!-- 2. Jika ada nama pencarian lokasi -->
          <div v-else-if="locationLabel" class="truncate">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
              {{ locationLabel }}
            </span>
            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>

          <!-- 3. Default: Koordinat Titik -->
          <div v-else class="truncate">
            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">
              {{ isIdentifying ? 'Memeriksa Fitur...' : 'Koordinat Titik' }}
            </span>
            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 font-mono truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-1 shrink-0" @click.stop>
        <!-- Quick Copy Button -->
        <UTooltip :text="copied ? 'Tersalin ke clipboard!' : 'Salin koordinat'">
          <button
            type="button"
            class="size-8.5 rounded-xl flex items-center justify-center transition-colors cursor-pointer text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10"
            :class="copied ? 'text-green-600 dark:text-green-400 font-bold' : ''"
            aria-label="Salin koordinat"
            @click="copyCoordinate"
          >
            <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
          </button>
        </UTooltip>

        <!-- Up/Down Chevron Expand Button -->
        <UTooltip :text="isExpanded ? 'Tutup detail informasi' : 'Buka detail lengkap informasi'">
          <button
            type="button"
            class="size-8.5 rounded-xl flex items-center justify-center transition-all cursor-pointer text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10"
            :class="isExpanded ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400' : ''"
            :aria-expanded="isExpanded"
            aria-label="Toggle detail informasi"
            @click="isExpanded = !isExpanded"
          >
            <UIcon
              name="i-lucide-chevron-up"
              class="size-4.5 transition-transform duration-300"
              :class="isExpanded ? 'rotate-180' : ''"
            />
          </button>
        </UTooltip>

        <!-- Close Button -->
        <UTooltip text="Tutup informasi">
          <button
            type="button"
            class="size-8.5 rounded-xl flex items-center justify-center transition-colors cursor-pointer text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10"
            aria-label="Tutup panel informasi"
            @click="emit('close')"
          >
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </UTooltip>
      </div>
    </div>

    <!-- Expandable Detailed Section (Slide Up/Down) -->
    <Transition name="panel-expand">
      <div
        v-show="isExpanded"
        class="border-t border-slate-100 dark:border-white/10 p-3.5 space-y-3 max-h-[420px] overflow-y-auto"
      >
        <!-- Tab Selector: Fitur WMS vs Koordinat -->
        <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10">
          <button
            type="button"
            class="flex-1 flex items-center justify-center gap-1.5 py-1 px-2.5 rounded-lg text-xs font-semibold transition-all cursor-pointer min-h-[32px]"
            :class="activeTab === 'features'
              ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 shadow-xs'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
            @click="activeTab = 'features'"
          >
            <UIcon name="i-lucide-info" class="size-3.5" />
            <span>Atribut WMS</span>
            <span
              v-if="identifiedFeatures && identifiedFeatures.length > 0"
              class="size-4 rounded-full text-[9px] flex items-center justify-center bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold"
            >
              {{ identifiedFeatures.length }}
            </span>
          </button>

          <button
            type="button"
            class="flex-1 flex items-center justify-center gap-1.5 py-1 px-2.5 rounded-lg text-xs font-semibold transition-all cursor-pointer min-h-[32px]"
            :class="activeTab === 'coordinates'
              ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 shadow-xs'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
            @click="activeTab = 'coordinates'"
          >
            <UIcon name="i-lucide-crosshair" class="size-3.5" />
            <span>Koordinat Titik</span>
          </button>
        </div>

        <!-- 2A. TAB FITUR WMS: IdentifyFeatures Component -->
        <div v-show="activeTab === 'features'">
          <IdentifyFeatures
            :features="identifiedFeatures"
            :loading="isIdentifying"
            @select-feature="emit('select-feature', $event)"
          />
        </div>

        <!-- 2B. TAB KOORDINAT TITIK -->
        <div v-show="activeTab === 'coordinates'" class="space-y-3">
          <!-- Geographic Coordinates Card -->
          <div class="p-3 rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] space-y-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                <UIcon name="i-lucide-crosshair" class="size-3.5 text-blue-500" />
                Sistem Koordinat Geografis
              </span>
              <span class="px-1.5 py-0.2 rounded-md text-[9px] font-mono font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
                EPSG:4326
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs">
              <div class="p-2 rounded-lg bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10">
                <p class="text-[10px] text-slate-400 font-medium">Latitude (Lintang)</p>
                <p class="font-mono font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                  {{ formattedLat }}
                </p>
              </div>
              <div class="p-2 rounded-lg bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10">
                <p class="text-[10px] text-slate-400 font-medium">Longitude (Bujur)</p>
                <p class="font-mono font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                  {{ formattedLng }}
                </p>
              </div>
            </div>

            <!-- Web Mercator Projected Info -->
            <div
              v-if="webMercatorCoords"
              class="p-2 rounded-lg bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 space-y-1"
            >
              <div class="flex justify-between items-center text-[10px] text-slate-400">
                <span class="font-medium">Proyeksi Web Mercator</span>
                <span class="font-mono font-bold text-slate-600 dark:text-slate-400">EPSG:3857</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-[11px] font-mono font-semibold text-slate-700 dark:text-slate-300">
                <div>X: {{ webMercatorCoords.x }} m</div>
                <div>Y: {{ webMercatorCoords.y }} m</div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 pt-0.5">
              <button
                type="button"
                class="flex-1 flex items-center justify-center gap-1.5 py-1.5 px-2.5 min-h-[36px] rounded-xl text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 active:scale-95 shadow-xs transition-all cursor-pointer"
                @click="copyCoordinate"
              >
                <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
                <span>{{ copied ? 'Tersalin' : 'Salin Koordinat' }}</span>
              </button>
              <a
                :href="googleMapsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center justify-center gap-1.5 py-1.5 px-3 min-h-[36px] rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] active:scale-95 transition-all cursor-pointer"
              >
                <UIcon name="i-lucide-external-link" class="size-3.5 text-slate-400" />
                <span>Google Maps</span>
              </a>
            </div>
          </div>

          <!-- Reference Spatial Card -->
          <div class="p-3 rounded-xl border border-slate-100 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] space-y-1.5 text-xs">
            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
              Referensi Geospasial
            </p>
            <div class="space-y-1 text-[11px] text-slate-500 dark:text-slate-400">
              <div class="flex justify-between">
                <span>Datum:</span>
                <span class="font-mono font-medium text-slate-800 dark:text-slate-200">WGS 84 (EPSG:4326)</span>
              </div>
              <div class="flex justify-between">
                <span>Proyeksi:</span>
                <span class="font-mono font-medium text-slate-800 dark:text-slate-200">Web Mercator (EPSG:3857)</span>
              </div>
              <div class="flex justify-between">
                <span>Wilayah:</span>
                <span class="font-medium text-blue-600 dark:text-blue-400">Kabupaten Bojonegoro</span>
              </div>
            </div>
          </div>
        </div>
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

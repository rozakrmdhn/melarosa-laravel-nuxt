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

function toDms(dec: number, isLng: boolean): string {
  const abs = Math.abs(dec);
  const degrees = Math.floor(abs);
  const minutesDec = (abs - degrees) * 60;
  const minutes = Math.floor(minutesDec);
  const seconds = ((minutesDec - minutes) * 60).toFixed(1);
  const dir = isLng ? (dec >= 0 ? "E" : "W") : (dec >= 0 ? "N" : "S");
  return `${degrees}°${minutes.toString().padStart(2, "0")}'${seconds.padStart(4, "0")}" ${dir}`;
}

const coordFormat = ref<"dd" | "dms">("dd");

const formattedDmsLat = computed(() => {
  if (!props.clickedCoordinate) return "-";
  return toDms(props.clickedCoordinate[1], false);
});

const formattedDmsLng = computed(() => {
  if (!props.clickedCoordinate) return "-";
  return toDms(props.clickedCoordinate[0], true);
});

const formattedDms = computed(() => {
  if (!props.clickedCoordinate) return "-";
  return `${formattedDmsLat.value}, ${formattedDmsLng.value}`;
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
  const text = coordFormat.value === "dms" ? formattedDms.value : `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }

  copied.value = true;
  toast.add({
    title: "Koordinat Disalin",
    description: `${text} (${coordFormat.value.toUpperCase()}) berhasil disalin ke clipboard`,
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
  <!-- 1. EMBEDDED MODE (Default): Terintegrasi responsif di dalam LeftPanel   -->
  <!--    dan Lembar Bawah Mobile (MapMobileBottomSheet)                      -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-if="isMobileDrawer"
    class="w-full flex flex-col px-4 sm:px-5 py-3 space-y-3.5 select-none"
  >
    <!-- Tab Switcher Nav: Pure Pill 100% Radius Selaras Standar LeftPanel (DESIGN_MAPS.md) -->
    <div
      role="tablist"
      aria-label="Pilihan Informasi Spasial"
      class="grid grid-cols-2 gap-1 p-1 bg-slate-200/60 dark:bg-slate-800/60 rounded-full select-none text-xs sm:text-sm font-bold tracking-tight shrink-0"
    >
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'features'"
        class="py-2 px-3 rounded-full flex items-center justify-center gap-1.5 transition-all cursor-pointer min-h-[44px] select-none text-center"
        :class="activeTab === 'features'
          ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        @click="activeTab = 'features'"
      >
        <UIcon name="i-lucide-info" class="size-3.5 shrink-0" />
        <span>Atribut Fitur</span>
      </button>

      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'coordinates'"
        class="py-2 px-3 rounded-full flex items-center justify-center gap-1.5 transition-all cursor-pointer min-h-[44px] select-none text-center"
        :class="activeTab === 'coordinates'
          ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        @click="activeTab = 'coordinates'"
      >
        <UIcon name="i-lucide-crosshair" class="size-3.5 shrink-0" />
        <span>Info Lokasi</span>
      </button>
    </div>

    <!-- 1A. Tab Konten: IdentifyFeatures WMS -->
    <div v-show="activeTab === 'features'" class="space-y-3">
      <IdentifyFeatures
        :features="identifiedFeatures"
        :loading="isIdentifying"
        @select-feature="(f) => emit('select-feature', f)"
      />
    </div>

    <!-- 1B. Tab Konten: Detail Koordinat Titik -->
    <div v-show="activeTab === 'coordinates'" class="space-y-3">
      <!-- Empty State Koordinat jika belum ada titik -->
      <div
        v-if="!clickedCoordinate"
        class="py-8 px-4 text-center space-y-3"
      >
        <div class="size-12 rounded-2xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-500 dark:text-slate-400 mx-auto">
          <UIcon name="i-lucide-mouse-pointer-click" class="size-6 stroke-[1.5]" />
        </div>
        <div class="space-y-1">
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
            Belum ada titik dipilih
          </p>
          <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-[240px] mx-auto">
            Ketuk pada area peta untuk memeriksa koordinat geografis wilayah Bojonegoro.
          </p>
        </div>
      </div>

      <!-- Kartu Koordinat Aktif -->
      <div v-else class="space-y-3">
        <!-- Title Card jika locationLabel ada -->
        <div
          v-if="locationLabel"
          class="p-3 rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] flex items-center gap-2.5"
        >
          <div class="size-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-info" class="size-4" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Lokasi Terpilih</p>
            <p class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">{{ locationLabel }}</p>
          </div>
        </div>

        <!-- Geographic Coordinates Card: Responsive Grid Sesuai Lebar LeftPanel -->
        <div class="p-3.5 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] space-y-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5 truncate">
              <UIcon name="i-lucide-crosshair" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
              <span>Koordinat Geografis</span>
            </span>

            <div class="flex items-center gap-1.5 shrink-0">
              <!-- Format Toggle Button (DD / DMS) -->
              <button
                type="button"
                class="px-2 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer min-h-[32px] flex items-center justify-center active:scale-95"
                :class="coordFormat === 'dd'
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'bg-slate-200/80 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-white/15'"
                :title="`Beralih ke format ${coordFormat === 'dd' ? 'DMS' : 'DD'}`"
                @click="coordFormat = coordFormat === 'dd' ? 'dms' : 'dd'"
              >
                {{ coordFormat.toUpperCase() }}
              </button>
              <span class="px-2 py-1 rounded-full text-[10px] font-bold font-mono bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
                EPSG:4326
              </span>
            </div>
          </div>

          <!-- Koordinat DD (Decimal Degrees): Responsif 2 Kolom -->
          <div v-if="coordFormat === 'dd'" class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 min-w-0">
              <p class="text-[11px] text-slate-500 font-medium truncate">Latitude (Lintang)</p>
              <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100 mt-0.5 text-xs sm:text-sm">
                {{ formattedLat }}
              </p>
            </div>
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 min-w-0">
              <p class="text-[11px] text-slate-500 font-medium truncate">Longitude (Bujur)</p>
              <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100 mt-0.5 text-xs sm:text-sm">
                {{ formattedLng }}
              </p>
            </div>
          </div>

          <!-- Koordinat DMS (Degrees Minutes Seconds): Bebas Truncate agar Detik Presisi Terlihat Utuh -->
          <div v-else class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 min-w-0">
              <p class="text-[11px] text-slate-500 font-medium truncate">Lintang (DMS)</p>
              <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100 mt-0.5 text-[11px] sm:text-xs leading-tight break-all sm:break-normal">
                {{ formattedDmsLat }}
              </p>
            </div>
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 min-w-0">
              <p class="text-[11px] text-slate-500 font-medium truncate">Bujur (DMS)</p>
              <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100 mt-0.5 text-[11px] sm:text-xs leading-tight break-all sm:break-normal">
                {{ formattedDmsLng }}
              </p>
            </div>
          </div>

          <!-- Web Mercator Projected Info (EPSG:3857) -->
          <div v-if="webMercatorCoords" class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 space-y-1.5">
            <div class="flex justify-between items-center text-xs text-slate-500">
              <span class="font-medium text-[11px]">Proyeksi Web Mercator</span>
              <span class="font-bold tabular-nums text-slate-600 dark:text-slate-400 text-[10px] font-mono">EPSG:3857</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs tabular-nums font-semibold text-slate-700 dark:text-slate-300">
              <div class="truncate">Timur (X): {{ webMercatorCoords.x }} m</div>
              <div class="truncate">Utara (Y): {{ webMercatorCoords.y }} m</div>
            </div>
          </div>

          <!-- Action Buttons (Responsive 3-Column Grid, Memenuhi Standar WCAG Target Sentuh Minimal 44px) -->
          <div class="grid grid-cols-3 gap-1.5 sm:gap-2 pt-1">
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-2 px-1.5 sm:px-2 rounded-xl text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-xs transition-all cursor-pointer min-h-[44px] min-w-0 focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
              @click="copyCoordinate"
            >
              <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4 shrink-0" />
              <span class="truncate">{{ copied ? 'Tersalin' : 'Salin' }}</span>
            </button>

            <a
              :href="googleMapsUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center justify-center gap-1.5 py-2 px-1.5 sm:px-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] active:scale-[0.98] transition-all cursor-pointer min-h-[44px] min-w-0 focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
            >
              <UIcon name="i-lucide-external-link" class="size-4 text-slate-500 shrink-0" />
              <span class="truncate">Maps</span>
            </a>

            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-2 px-1.5 sm:px-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] hover:text-blue-600 dark:hover:text-blue-400 active:scale-[0.98] transition-all cursor-pointer min-h-[44px] min-w-0 focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
              aria-label="Bagikan Peta Lokasi"
              @click="emit('share')"
            >
              <UIcon name="i-lucide-share-2" class="size-4 text-slate-500 shrink-0" />
              <span class="truncate">Bagikan</span>
            </button>
          </div>
        </div>

        <!-- Spatial Reference Card: Rapi & Anti-Overflow -->
        <div class="p-3.5 rounded-2xl border border-slate-100 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] space-y-2 text-xs">
          <p class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
            Sistem Referensi Geospasial
          </p>
          <div class="space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center justify-between gap-2">
              <span class="shrink-0">Datum Geodetik:</span>
              <span class="font-medium tabular-nums text-slate-800 dark:text-slate-200 text-right truncate">WGS 84 (EPSG:4326)</span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span class="shrink-0">Sistem Proyeksi:</span>
              <span class="font-medium tabular-nums text-slate-800 dark:text-slate-200 text-right truncate">Web Mercator (EPSG:3857)</span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span class="shrink-0">Cakupan Wilayah:</span>
              <span class="font-medium text-blue-600 dark:text-blue-400 text-right truncate">Kabupaten Bojonegoro</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 2. DESKTOP STANDALONE FLOATING MODE (Fallback jika dipasang mandiri)   -->
  <!--    Posisi diselaraskan menghormati clearance LeftPanel (DESIGN_MAPS.md)-->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-else
    class="flex flex-col bg-white/95 dark:bg-[#09111e]/95 backdrop-blur-xl border border-slate-200/90 dark:border-white/10 shadow-2xl rounded-2xl overflow-hidden select-none transition-all duration-300 ease-out w-full max-w-[calc(100vw-24px)] sm:w-[400px]"
  >
    <!-- Top Summary Pill Bar -->
    <div
      class="flex items-center justify-between h-12 px-3 bg-slate-50/60 dark:bg-white/[0.02] cursor-pointer group"
      @click="isExpanded = !isExpanded"
    >
      <div class="flex items-center gap-2.5 min-w-0 flex-1">
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

        <div class="min-w-0 flex-1 pr-1">
          <div v-if="primaryFeatureName" class="truncate">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
              {{ primaryFeatureName }}
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
          <div v-else-if="locationLabel" class="truncate">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
              {{ locationLabel }}
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
          <div v-else class="truncate">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
              {{ isIdentifying ? 'Memeriksa Fitur...' : 'Koordinat Titik' }}
            </span>
            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 tabular-nums truncate block">
              {{ formattedLat }}, {{ formattedLng }}
            </span>
          </div>
        </div>
      </div>

      <!-- Action Icons -->
      <div class="flex items-center gap-1 shrink-0" @click.stop>
        <button
          type="button"
          class="size-8 rounded-xl flex items-center justify-center transition-colors cursor-pointer text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-white/10"
          :class="copied ? 'text-green-600 font-bold' : ''"
          aria-label="Salin koordinat"
          @click="copyCoordinate"
        >
          <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
        </button>

        <button
          type="button"
          class="size-8 rounded-xl flex items-center justify-center transition-all cursor-pointer text-slate-600 dark:text-slate-300 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-white/10"
          :class="isExpanded ? 'bg-blue-50 text-blue-600' : ''"
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

        <button
          type="button"
          class="size-8 rounded-xl flex items-center justify-center transition-colors cursor-pointer text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-white/10"
          aria-label="Tutup panel informasi"
          @click="emit('close')"
        >
          <UIcon name="i-lucide-x" class="size-4" />
        </button>
      </div>
    </div>

    <!-- Expandable Detailed Section -->
    <Transition name="panel-expand">
      <div
        v-show="isExpanded"
        class="border-t border-slate-100 dark:border-white/10 p-3.5 space-y-3 max-h-[420px] overflow-y-auto"
      >
        <!-- Reusable Tab Switcher -->
        <div
          role="tablist"
          class="grid grid-cols-2 gap-1 p-1 bg-slate-200/60 dark:bg-slate-800/60 rounded-full select-none text-xs font-bold tracking-tight shrink-0"
        >
          <button
            type="button"
            class="py-2 px-3 rounded-full flex items-center justify-center gap-1.5 transition-all cursor-pointer min-h-[44px] select-none text-center"
            :class="activeTab === 'features'
              ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            @click="activeTab = 'features'"
          >
            <UIcon name="i-lucide-info" class="size-3.5 shrink-0" />
            <span>Atribut Fitur</span>
          </button>

          <button
            type="button"
            class="py-2 px-3 rounded-full flex items-center justify-center gap-1.5 transition-all cursor-pointer min-h-[44px] select-none text-center"
            :class="activeTab === 'coordinates'
              ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            @click="activeTab = 'coordinates'"
          >
            <UIcon name="i-lucide-crosshair" class="size-3.5 shrink-0" />
            <span>Info Lokasi</span>
          </button>
        </div>

        <div v-show="activeTab === 'features'">
          <IdentifyFeatures
            :features="identifiedFeatures"
            :loading="isIdentifying"
            @select-feature="emit('select-feature', $event)"
          />
        </div>

        <div v-show="activeTab === 'coordinates'" class="space-y-3">
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 min-w-0">
              <p class="text-[11px] text-slate-500 font-medium truncate">Latitude (Lintang)</p>
              <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100 mt-0.5 text-xs sm:text-sm">
                {{ formattedLat }}
              </p>
            </div>
            <div class="p-2.5 rounded-xl bg-white dark:bg-[#070b14] border border-slate-200/80 dark:border-white/10 min-w-0">
              <p class="text-[11px] text-slate-500 font-medium truncate">Longitude (Bujur)</p>
              <p class="font-bold tabular-nums text-slate-900 dark:text-slate-100 mt-0.5 text-xs sm:text-sm">
                {{ formattedLng }}
              </p>
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

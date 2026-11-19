<script setup lang="ts">
import IdentifyFeatures, { type IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";
import { getKecamatanFromCoordinate } from "~/utils/gis-helpers";

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
  (e: "select-feature", feature: IdentifiedFeature): void;
}>();

const toast = useToast();
const selectedFeatureIndex = ref(0);
const pointCopied = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

watch(
  () => props.identifiedFeatures,
  () => {
    selectedFeatureIndex.value = 0;
  }
);

onBeforeUnmount(() => {
  if (copyTimer) clearTimeout(copyTimer);
});

const activeFeature = computed<IdentifiedFeature | null>(() => {
  if (!props.identifiedFeatures || props.identifiedFeatures.length === 0) return null;
  return props.identifiedFeatures[selectedFeatureIndex.value] || props.identifiedFeatures[0] || null;
});

const effectiveCoordinate = computed<[number, number] | null>(() => {
  const coord = activeFeature.value?.coordinate ?? props.clickedCoordinate ?? null;
  if (!coord || coord.length < 2) return null;
  const [lng, lat] = coord;
  if (!Number.isFinite(lng) || !Number.isFinite(lat)) return null;
  return [lng, lat];
});

const formattedPoint = computed(() => {
  if (!effectiveCoordinate.value) return "";
  const [lng, lat] = effectiveCoordinate.value;
  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
});

const googleMapsUrl = computed(() => {
  if (!effectiveCoordinate.value) return "#";
  const [lng, lat] = effectiveCoordinate.value;
  return `https://www.google.com/maps?q=${lat.toFixed(6)},${lng.toFixed(6)}`;
});

const activeFeatureName = computed(() => {
  if (!activeFeature.value) return null;
  const p = activeFeature.value.properties || {};
  const layerHint = `${activeFeature.value.layerName || ""} ${activeFeature.value.layerId || ""} ${activeFeature.value.layerTitle || ""}`.toLowerCase();

  const preferredKeys = layerHint.includes("bataswilayah_kecamatan") || (layerHint.includes("kecamatan") && !layerHint.includes("palapa:"))
    ? ["nama_kecamatan", "wadmkc", "kecamatan", "namobj", "nama", "title", "name"]
    : ["namobj", "nama_ruas", "nama_desa", "wadmkd", "nama_kecamatan", "wadmkc", "nama", "title", "name"];

  for (const pk of preferredKeys) {
    const match = Object.entries(p).find(([k, v]) => k.toLowerCase() === pk && typeof v === "string" && v.trim());
    if (match) return String(match[1]);
  }

  const nameEntry = Object.entries(p).find(
    ([k, v]) => {
      const lower = k.toLowerCase();
      return (
        typeof v === "string" &&
        v.trim() &&
        !lower.includes("jabatan") &&
        (lower.includes("nam") || lower.includes("title"))
      );
    }
  );
  if (nameEntry) return String(nameEntry[1]);

  if (activeFeature.value.id) return activeFeature.value.id;

  const firstValid = Object.values(p).find((v) => v !== null && v !== undefined && v !== "");
  return firstValid ? String(firstValid) : null;
});

function toFriendlyLayerLabel(rawTitle?: string | null): string {
  const t = String(rawTitle || "").toLowerCase();
  if (t.includes("segmen")) return "Kondisi Jalan Dibangun";
  if (t.includes("poros") || t.includes("jalan")) return "Jaringan Jalan Desa";
  if (t.includes("jembatan")) return "Jembatan Desa";
  if (t.includes("kantor")) return "Kantor Desa";
  if (t.includes("desa")) return "Wilayah Desa";
  if (t.includes("kecamatan")) return "Wilayah Kecamatan";
  if (t.includes("kabupaten")) return "Wilayah Bojonegoro";
  return rawTitle || "Informasi Peta";
}

const activeFeatureIcon = computed(() => {
  if (props.isIdentifying) return "i-ion-refresh-outline";
  if (!activeFeature.value) return "i-ion-location";
  const raw = `${activeFeature.value.layerId || ""} ${activeFeature.value.layerTitle || ""}`.toLowerCase();
  if (raw.includes("jembatan")) return "i-ion-construct";
  if (raw.includes("kantor")) return "i-ion-business";
  if (raw.includes("desa") || raw.includes("kecamatan") || raw.includes("kabupaten") || raw.includes("batas")) {
    return "i-ion-map";
  }
  if (raw.includes("poros") || raw.includes("jalan") || raw.includes("segmen")) return "i-ion-trail-sign";
  return "i-ion-layers";
});

const nearbyKecamatanName = computed(() => {
  if (!effectiveCoordinate.value) return null;
  const match = getKecamatanFromCoordinate(effectiveCoordinate.value[1], effectiveCoordinate.value[0]);
  return match ? `Kecamatan ${match.name}` : null;
});

const identifyTitle = computed(() => {
  if (activeFeatureName.value) return activeFeatureName.value;
  if (props.locationLabel) return props.locationLabel;
  if (props.isIdentifying) return "Memeriksa Lokasi...";
  if (nearbyKecamatanName.value) return nearbyKecamatanName.value;
  return "Lokasi di Peta";
});

const identifySubtitle = computed(() => {
  if (activeFeature.value?.layerTitle) {
    const label = toFriendlyLayerLabel(activeFeature.value.layerTitle);
    const meta = nearbyKecamatanName.value || activeFeature.value.layerInstansi || activeFeature.value.layerAttribution;
    return meta ? `${label} · ${meta}` : label;
  }
  if (effectiveCoordinate.value) {
    return nearbyKecamatanName.value
      ? `${nearbyKecamatanName.value} · Kabupaten Bojonegoro`
      : "Kabupaten Bojonegoro";
  }
  return "Ketuk titik atau objek pada peta untuk melihat info lokasi";
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
    console.warn("Gagal menyalin koordinat:", err);
  }
  document.body.removeChild(textArea);
}

function copyPoint() {
  if (!formattedPoint.value) return;
  const text = formattedPoint.value;

  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }

  pointCopied.value = true;
  toast.add({
    title: "Titik Koordinat Disalin",
    description: `${text} berhasil disalin ke clipboard`,
    color: "success",
    icon: "i-ion-checkmark-circle",
  });

  if (copyTimer) clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    pointCopied.value = false;
  }, 2000);
}
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0">
    <!-- Header Lokasi (Tanpa Hapus Pin; tombol X menutup panel sekaligus menghapus pin) -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-white/5 flex items-start justify-between gap-3 shrink-0 select-none">
      <div class="flex items-start gap-3 min-w-0 flex-1">
        <div class="size-10 sm:size-11 rounded-full bg-blue-600 dark:bg-blue-500 text-white flex items-center justify-center shrink-0 shadow-xs">
          <UIcon
            :name="activeFeatureIcon"
            class="size-5"
            :class="props.isIdentifying ? 'animate-spin' : ''"
          />
        </div>

        <div class="min-w-0 flex-1 pt-0.5">
          <h2 class="text-[15px] sm:text-base font-bold text-slate-900 dark:text-slate-100 leading-snug line-clamp-2 break-words">
            {{ identifyTitle }}
          </h2>
          <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 leading-snug line-clamp-1">
            {{ identifySubtitle }}
          </p>
        </div>
      </div>

      <button
        type="button"
        class="size-11 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 flex items-center justify-center transition-colors cursor-pointer active:scale-95 shrink-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
        aria-label="Tutup info lokasi dan hapus pin"
        title="Tutup & hapus pin"
        @click="emit('close')"
      >
        <UIcon name="i-ion-close" class="size-5" />
      </button>
    </div>

    <!-- Konten Tunggal Tanpa Tab: Atribut Feature + Aksi Titik & Google Maps di Paling Bawah -->
    <div class="flex-1 overflow-y-auto min-h-0 px-4 sm:px-5 pt-3.5 pb-6 space-y-4">
      <IdentifyFeatures
        v-model:selected-index="selectedFeatureIndex"
        :features="props.identifiedFeatures"
        :loading="props.isIdentifying"
        :has-point="Boolean(effectiveCoordinate)"
        @select-feature="(f) => emit('select-feature', f)"
      />

      <!-- Paling Bawah dari Atribut Feature Keseluruhan: Salin Titik & Google Maps -->
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
            :class="pointCopied
              ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300'
              : 'border-slate-300 dark:border-white/15 bg-white dark:bg-[#0f172a] text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-white/10'"
            @click="copyPoint"
          >
            <UIcon :name="pointCopied ? 'i-ion-checkmark-circle' : 'i-ion-copy-outline'" class="size-4 shrink-0" />
            <span class="truncate">{{ pointCopied ? 'Tersalin' : 'Salin Titik' }}</span>
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
  </div>
</template>

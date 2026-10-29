<script setup lang="ts">
import { ref, computed } from "vue";
import type { KecamatanEntry, DatasetResultItem } from "~/components/maps/LeftPanel.vue";
import type { GeoparkSite } from "~/composables/useGeoparkSites";
import type { ActiveLayerItem } from "~/types/map-layers";
import type { GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import GeoportalMetadataModal from "~/components/maps/GeoportalMetadataModal.vue";
import DatasetMetadataSection from "./DatasetMetadataSection.vue";

const props = defineProps<{
  selectedItem?: { type: "kecamatan" | "dataset" | "geopark" | "landmark" | "coordinate"; data: any } | null;
  selectedKecamatanFilter?: string | null;
  activeLayers?: ActiveLayerItem[];
}>();

const emit = defineEmits<{
  (e: "close"): void;
  (e: "zoom-kecamatan", k: KecamatanEntry): void;
  (e: "zoom-dataset", item: DatasetResultItem): void;
  (e: "toggle-dataset", item: DatasetResultItem): void;
  (e: "zoom-geopark", site: GeoparkSite): void;
  (e: "zoom-landmark", lm: any): void;
  (e: "zoom-coordinate", coord: any): void;
  (e: "open-geopark-tab", site: GeoparkSite): void;
}>();

const itemType = computed(() => props.selectedItem?.type || "kecamatan");
const itemData = computed(() => props.selectedItem?.data || {});

function isDatasetActive(item?: DatasetResultItem): boolean {
  if (!item || !item.id) return false;
  return (props.activeLayers || []).some((l) => l.id === item.id);
}

function getKecamatanCode(name?: string): string {
  if (!name) return "KEC";
  const map: Record<string, string> = {
    Balen: "BLN",
    Baureno: "BRN",
    Bojonegoro: "BJN",
    Bubulan: "BBL",
    Dander: "DND",
    Gayam: "GYM",
    Gondang: "GDG",
    Kalitidu: "KTD",
    Kanor: "KNR",
    Kapas: "KPS",
    Kasiman: "KSM",
    Kedewan: "KDW",
    Kedungadem: "KDA",
    Kepohbaru: "KPB",
    Malo: "MLO",
    Margomulyo: "MGM",
    Ngambon: "NGB",
    Ngasem: "NGS",
    Ngraho: "NRH",
    Padangan: "PDG",
    Purwosari: "PWS",
    Sekar: "SKR",
    Sugihwaras: "SGW",
    Sukosewu: "SKS",
    Sumberrejo: "SBR",
    Tambakrejo: "TBR",
    Temayang: "TMY",
    Trucuk: "TRC",
  };
  return map[name] || name.slice(0, 3).toUpperCase();
}

function getDatasetCode(item?: DatasetResultItem): string {
  if (!item) return "DATA";
  const proto = String(item.protocol || "").toUpperCase();
  if (proto.includes("MVT") || proto.includes("VECTOR")) return "MVT";
  if (proto.includes("WFS")) return "WFS";
  if (proto.includes("WMS")) return "WMS";
  if (proto.includes("GEOJSON")) return "GEO";
  return "DATA";
}

function getGeoparkCode(item?: any): string {
  if (!item) return "GP";
  if (item.code) return String(item.code);
  const name = String(item.name || item.title || "");
  const words = name.split(" ").filter(Boolean);
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }
  return name.slice(0, 3).toUpperCase() || "GP";
}

const isCopied = ref(false);
function copyCoordinates(coords?: [number, number] | number[] | null) {
  if (!coords || coords.length < 2) return;
  if (import.meta.client && navigator?.clipboard) {
    navigator.clipboard.writeText(`${coords[1]}, ${coords[0]}`);
    isCopied.value = true;
    setTimeout(() => {
      isCopied.value = false;
    }, 1800);
  }
}

const isDmsCopied = ref(false);
function copyDMS(dmsStr?: string) {
  if (!dmsStr) return;
  if (import.meta.client && navigator?.clipboard) {
    navigator.clipboard.writeText(dmsStr);
    isDmsCopied.value = true;
    setTimeout(() => {
      isDmsCopied.value = false;
    }, 1800);
  }
}

const kecamatanCoords = computed<[number, number] | null>(() => {
  const c = itemData.value?.coordinate;
  if (Array.isArray(c) && c.length >= 2 && !isNaN(Number(c[0])) && !isNaN(Number(c[1]))) {
    return [Number(c[0]), Number(c[1])];
  }
  return null;
});

const geoparkCoords = computed<[number, number] | null>(() => {
  const d = itemData.value;
  if (!d) return null;
  const lat = Number(d.latitude ?? d.coordinate?.[1] ?? 0);
  const lng = Number(d.longitude ?? d.coordinate?.[0] ?? 0);
  if (lat !== 0 || lng !== 0) {
    return [lng, lat];
  }
  return null;
});

function handleZoomGeopark(item: any) {
  emit("zoom-geopark", item);
  emit("zoom-landmark", item);
}

const isMetadataModalOpen = ref(false);

const targetGeoportalLayer = computed<GeoportalLayerItem | null>(() => {
  if (itemType.value !== "dataset") return null;
  const d = itemData.value;
  const r = d?.raw || {};
  const layerId = String(d?.id || r?.id || "");
  if (!layerId) return null;

  return {
    id: layerId,
    name: String(r?.name || d?.title || r?.title || "Dataset Spasial"),
    protocol: String(d?.protocol || r?.protocol || "WMS"),
    url: String(r?.url || r?.wmsUrl || "https://geoportal.bojonegorokab.go.id/geoserver/wms"),
    layer_name: String(r?.layer_name || r?.wmsLayerName || d?.title || ""),
    source_type: String(r?.source_type || (d?.isGeoportal ? "geoportal" : "geoportal")),
    is_active: isDatasetActive(d),
    default_visible: Boolean(r?.default_visible ?? true),
    opacity: Number(r?.opacity ?? 0.85),
    order: Number(r?.order ?? 0),
    attribution: r?.attribution || r?.instansi || d?.subtitle?.split(" · ")[0] || "Dinas Pemkab Bojonegoro",
    description: r?.description || d?.raw?.description || null,
    geoportal_id: r?.geoportal_id || null,
    category: r?.category || d?.category || "Tematik Umum",
    opd_name: r?.opd_name || r?.instansi || d?.subtitle?.split(" · ")[0] || "Pemerintah Kabupaten Bojonegoro",
    legend_url: r?.legend_url || null,
    csw_metadata: r?.csw_metadata || null,
    last_synced_at: r?.last_synced_at || null,
  };
});
</script>

<template>
  <div v-if="props.selectedItem && props.selectedItem.data" class="flex-1 flex flex-col min-h-0">
    <!-- Header Hero Section -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-white/5 flex items-start justify-between gap-3 shrink-0 bg-transparent">
      <div class="flex items-start gap-3 min-w-0 flex-1">
        <!-- Circular Badge Avatar -->
        <template v-if="itemType === 'kecamatan'">
          <div class="size-11 rounded-full bg-blue-600 dark:bg-blue-500 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider">
            {{ getKecamatanCode(itemData.name) }}
          </div>
        </template>
        <template v-else-if="itemType === 'dataset'">
          <div class="size-11 rounded-full bg-purple-700 dark:bg-purple-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider">
            {{ getDatasetCode(itemData) }}
          </div>
        </template>
        <template v-else-if="itemType === 'coordinate'">
          <div class="size-11 rounded-full bg-blue-600 dark:bg-blue-500 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider">
            <UIcon name="i-lucide-crosshair" class="size-5" />
          </div>
        </template>
        <template v-else>
          <div
            class="size-11 rounded-full text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider"
            :class="itemData.categorySlug === 'geo-diversity'
              ? 'bg-blue-600 dark:bg-blue-500'
              : itemData.categorySlug === 'bio-diversity'
                ? 'bg-emerald-600 dark:bg-emerald-500'
                : 'bg-amber-600 dark:bg-amber-500'"
          >
            {{ getGeoparkCode(itemData) }}
          </div>
        </template>

        <!-- Title & Subtitle -->
        <div class="min-w-0 flex-1 pt-0.5">
          <template v-if="itemType === 'kecamatan'">
            <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight">
              Kecamatan {{ itemData.name }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal">
              Zona {{ itemData.zona }} · {{ itemData.desc }}
            </p>
          </template>
          <template v-else-if="itemType === 'dataset'">
            <div class="flex items-center gap-1.5 flex-wrap">
              <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight">
                {{ itemData.title }}
              </h1>
              <span
                v-if="itemData.slug"
                class="px-1.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-white/10 shrink-0"
              >
                {{ itemData.slug }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal">
              {{ itemData.subtitle }}
            </p>
          </template>
          <template v-else-if="itemType === 'coordinate'">
            <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight">
              Titik Koordinat
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal tabular-nums">
              {{ itemData.formatted }} · WGS 84
            </p>
          </template>
          <template v-else>
            <div class="flex items-center gap-1.5 flex-wrap">
              <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight">
                {{ itemData.title || itemData.name }}
              </h1>
              <span
                v-if="itemData.slug"
                class="px-1.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-white/10 shrink-0"
              >
                {{ itemData.slug }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal truncate">
              {{ itemData.category }} · {{ itemData.tag || itemData.kecamatan }}
            </p>
          </template>
        </div>
      </div>

      <!-- Circle X Close Button -->
      <button
        type="button"
        class="relative after:absolute after:-inset-1 after:content-[''] size-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-95 shrink-0 mt-0.5"
        aria-label="Tutup detail dan kembali ke daftar pencarian"
        title="Tutup detail dan kembali ke daftar pencarian"
        @click="emit('close')"
      >
        <UIcon name="i-lucide-x" class="size-4" />
      </button>
    </div>

    <!-- Body Content Area -->
    <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-3.5 space-y-5">
      <!-- 1. Detail Wilayah & Kecamatan -->
      <template v-if="itemType === 'kecamatan'">
        <!-- Status & Zona -->
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300">
              Zona {{ itemData.zona }}
            </span>
            <span
              v-if="props.selectedKecamatanFilter?.trim().toLowerCase() === itemData.name?.toLowerCase()"
              class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 flex items-center gap-1"
            >
              <UIcon name="i-lucide-check-circle" class="size-3.5" />
              Filter Aktif di Peta
            </span>
          </div>
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Bojonegoro</span>
        </div>

        <!-- Karakteristik Wilayah -->
        <div class="space-y-1.5">
          <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            Karakteristik & Potensi
          </h2>
          <p class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed font-medium">
            {{ itemData.desc }}. Wilayah ini merupakan bagian dari konfigurasi pembangunan terpadu Pemerintah Kabupaten Bojonegoro.
          </p>
        </div>

        <!-- Titik Koordinat -->
        <div v-if="kecamatanCoords" class="space-y-1.5">
          <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Titik Pusat Geografis
            </h2>
            <button
              type="button"
              class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer flex items-center gap-1 min-h-[44px] px-2"
              @click="copyCoordinates(kecamatanCoords)"
            >
              <UIcon :name="isCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
              <span>{{ isCopied ? 'Tersalin' : 'Salin' }}</span>
            </button>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] flex items-center justify-between text-xs text-slate-700 dark:text-slate-300">
            <span class="tabular-nums font-semibold">{{ kecamatanCoords[1].toFixed(5) }}°, {{ kecamatanCoords[0].toFixed(5) }}°</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold tabular-nums">WGS 84</span>
          </div>
        </div>

        <!-- Tombol Aksi Navigasi -->
        <div class="space-y-2 pt-2">
          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
            @click="emit('zoom-kecamatan', itemData)"
          >
            <UIcon name="i-lucide-crosshair" class="size-4" />
            <span>Pusatkan Peta ke Wilayah Ini</span>
          </button>
          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/[0.1] text-slate-700 dark:text-slate-200 font-medium text-xs transition-all cursor-pointer"
            @click="emit('close')"
          >
            <span>Kembali ke Daftar Pencarian</span>
          </button>
        </div>
      </template>

      <!-- 4. Detail Titik Koordinat (Desimal & Derajat) -->
      <template v-else-if="itemType === 'coordinate'">
        <!-- Status & Label Koordinat -->
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300">
              Koordinat WGS 84
            </span>
            <span
              v-if="itemData.kecamatan"
              class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 flex items-center gap-1"
            >
              <UIcon name="i-lucide-map-pin" class="size-3.5" />
              Kecamatan {{ itemData.kecamatan.name }}
            </span>
          </div>
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Bojonegoro</span>
        </div>

        <!-- Format Desimal (DD) -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Koordinat Desimal (DD)
            </h2>
            <button
              type="button"
              class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer flex items-center gap-1 min-h-[44px] px-2"
              @click="copyCoordinates([itemData.lng, itemData.lat])"
            >
              <UIcon :name="isCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
              <span>{{ isCopied ? 'Tersalin' : 'Salin Desimal' }}</span>
            </button>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] flex items-center justify-between text-xs text-slate-700 dark:text-slate-300">
            <span class="tabular-nums font-semibold">{{ itemData.lat?.toFixed(5) }}°, {{ itemData.lng?.toFixed(5) }}°</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold tabular-nums">Latitude, Longitude</span>
          </div>
        </div>

        <!-- Format Derajat Menit Detik (DMS) -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Koordinat Derajat (DMS)
            </h2>
            <button
              type="button"
              class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer flex items-center gap-1 min-h-[44px] px-2"
              @click="copyDMS(itemData.dms)"
            >
              <UIcon :name="isDmsCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
              <span>{{ isDmsCopied ? 'Tersalin' : 'Salin Derajat' }}</span>
            </button>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] flex items-center justify-between text-xs text-slate-700 dark:text-slate-300">
            <span class="tabular-nums font-semibold">{{ itemData.dms }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold tabular-nums">DMS WGS 84</span>
          </div>
        </div>

        <!-- Tombol Aksi Navigasi Kamera -->
        <div class="space-y-2 pt-2">
          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
            @click="emit('zoom-coordinate', itemData)"
          >
            <UIcon name="i-lucide-navigation" class="size-4" />
            <span>Terbangkan Kamera ke Titik Ini (Fly To)</span>
          </button>

          <a
            :href="`https://www.google.com/maps?q=${itemData.lat},${itemData.lng}`"
            target="_blank"
            rel="noopener noreferrer"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.05] dark:hover:bg-white/[0.08] text-slate-700 dark:text-slate-200 font-semibold text-xs transition-all cursor-pointer"
          >
            <UIcon name="i-lucide-external-link" class="size-4" />
            <span>Buka di Google Maps</span>
          </a>

          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/[0.1] text-slate-700 dark:text-slate-200 font-medium text-xs transition-all cursor-pointer"
            @click="emit('close')"
          >
            <span>Kembali ke Daftar Pencarian</span>
          </button>
        </div>
      </template>

      <!-- 2. Detail Dataset & Layer Spasial -->
      <template v-else-if="itemType === 'dataset'">
        <!-- Status & Badge Protokol -->
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-2">
            <span
              class="size-2.5 rounded-full"
              :class="isDatasetActive(itemData) ? 'bg-emerald-500 ring-2 ring-emerald-200 dark:ring-emerald-950' : 'bg-slate-300 dark:bg-slate-600'"
            />
            <span class="font-bold text-xs" :class="isDatasetActive(itemData) ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
              {{ isDatasetActive(itemData) ? 'Aktif pada Kanvas Peta' : 'Belum Ditambahkan' }}
            </span>
          </div>

          <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
            {{ itemData.protocol }}
          </span>
        </div>

        <!-- Tombol Aksi Navigasi Spasial -->
        <div class="space-y-2 pt-1">
          <!-- Tombol Tambahkan Layer jika belum aktif -->
          <button
            v-if="!isDatasetActive(itemData)"
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition-all cursor-pointer"
            @click="emit('toggle-dataset', itemData)"
          >
            <UIcon name="i-lucide-plus" class="size-4" />
            <span>Tambahkan Layer ke Peta</span>
          </button>

          <!-- Tombol Pusatkan Cakupan jika sudah aktif -->
          <button
            v-else
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
            @click="emit('zoom-dataset', itemData)"
          >
            <UIcon name="i-lucide-maximize-2" class="size-4" />
            <span>Pusatkan Peta ke Cakupan Layer</span>
          </button>

          <!-- Tombol Hapus Layer jika sudah aktif -->
          <button
            v-if="isDatasetActive(itemData)"
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/40 text-red-600 dark:text-red-400 font-semibold text-xs transition-all cursor-pointer"
            @click="emit('toggle-dataset', itemData)"
          >
            <UIcon name="i-lucide-trash-2" class="size-4" />
            <span>Hapus Layer dari Peta</span>
          </button>
        </div>

        <!-- Metadata Lengkap Geoportal / pyCSW / KUGI BIG -->
        <DatasetMetadataSection
          :layer="targetGeoportalLayer"
          :is-active="isDatasetActive(itemData)"
          @toggle="emit('toggle-dataset', itemData)"
          @zoom="emit('zoom-dataset', itemData)"
          @open-modal="isMetadataModalOpen = true"
        />

        <!-- Tombol Kembali -->
        <div class="pt-2">
          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/[0.1] text-slate-700 dark:text-slate-200 font-medium text-xs transition-all cursor-pointer"
            @click="emit('close')"
          >
            <span>Kembali ke Daftar Pencarian</span>
          </button>
        </div>
      </template>

      <!-- 3. Detail Geosite & Geopark Bojonegoro -->
      <template v-else>
        <!-- Foto Banner Geosite Resmi -->
        <div
          v-if="itemData.image || itemData.gambarUrl"
          class="rounded-2xl overflow-hidden border border-slate-200/80 dark:border-white/10 shadow-xs max-h-[190px] bg-slate-100 dark:bg-white/5 relative"
        >
          <img
            :src="itemData.image || itemData.gambarUrl"
            :alt="itemData.title || itemData.name || 'Geosite Bojonegoro'"
            class="w-full h-full object-cover max-h-[190px]"
          />
          <div v-if="itemData.code" class="absolute bottom-2 left-2 px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-xs text-white text-xs font-semibold tabular-nums">
            {{ itemData.code }}
          </div>
        </div>

        <!-- Kategori & Wilayah / Tag -->
        <div class="flex items-center justify-between text-xs flex-wrap gap-2">
          <span
            class="font-bold px-2.5 py-1 rounded-full text-xs"
            :class="itemData.categorySlug === 'geo-diversity'
              ? 'bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300'
              : itemData.categorySlug === 'bio-diversity'
                ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300'
                : 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300'"
          >
            {{ itemData.category || 'Geosite Resmi' }}
          </span>
          <span class="text-slate-600 dark:text-slate-300 font-medium">
            {{ itemData.kecamatan || 'Bojonegoro' }}
          </span>
        </div>

        <!-- Deskripsi Signifikansi Geosite -->
        <div class="space-y-1.5">
          <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            Deskripsi & Signifikansi
          </h2>
          <p class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed font-medium">
            {{ itemData.desc || ((itemData.name || itemData.title) + ' merupakan situs resmi Geopark Nasional Bojonegoro.') }}
          </p>
        </div>

        <!-- Titik Koordinat Geografis -->
        <div v-if="geoparkCoords" class="space-y-1.5">
          <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Titik Koordinat Geografis
            </h2>
            <button
              type="button"
              class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer flex items-center gap-1 min-h-[44px] px-2"
              @click="copyCoordinates(geoparkCoords)"
            >
              <UIcon :name="isCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
              <span>{{ isCopied ? 'Tersalin' : 'Salin' }}</span>
            </button>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] flex items-center justify-between text-xs text-slate-700 dark:text-slate-300">
            <span class="tabular-nums font-semibold">
              {{ geoparkCoords[1].toFixed(6) }}°, {{ geoparkCoords[0].toFixed(6) }}°
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold tabular-nums">
              Zoom {{ itemData.zoom || 14 }}
            </span>
          </div>
        </div>

        <!-- Banner jika merupakan warisan budaya tak benda -->
        <div
          v-else
          class="p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2"
        >
          <UIcon name="i-lucide-sparkles" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
          <span>Warisan Budaya Takbenda Bojonegoro (Intangible Cultural Heritage)</span>
        </div>

        <!-- Tombol Aksi Navigasi -->
        <div class="space-y-2 pt-2">
          <button
            v-if="geoparkCoords"
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
            @click="handleZoomGeopark(itemData)"
          >
            <UIcon name="i-lucide-crosshair" class="size-4" />
            <span>Pusatkan Peta ke Geosite Ini</span>
          </button>

          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition-all cursor-pointer"
            @click="emit('open-geopark-tab', itemData)"
          >
            <UIcon name="i-lucide-book-open" class="size-4" />
            <span>Buka Ulasan di Tab Geosite</span>
          </button>

          <NuxtLink
            v-if="itemData.slug"
            :to="`/geopark/${itemData.slug}`"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
          >
            <UIcon name="i-lucide-external-link" class="size-4" />
            <span>Baca Ulasan Geopark Selengkapnya</span>
          </NuxtLink>

          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/[0.1] text-slate-700 dark:text-slate-200 font-medium text-xs transition-all cursor-pointer"
            @click="emit('close')"
          >
            <span>Kembali ke Daftar Pencarian</span>
          </button>
        </div>
      </template>
    </div>

    <!-- Modal Dialog Metadata Lengkap Layar Penuh -->
    <GeoportalMetadataModal
      v-model:open="isMetadataModalOpen"
      :layer="targetGeoportalLayer"
    />
  </div>
</template>

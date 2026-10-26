<script setup lang="ts">
import type { ActiveLayerItem } from "~/types/map-layers";
import type { KecamatanEntry } from "~/components/maps/LeftPanel.vue";

const props = defineProps<{
  selectedLayer: ActiveLayerItem | null;
  selectedKecamatanFilter?: string | null;
  kecamatanEntries: KecamatanEntry[];
}>();

const emit = defineEmits<{
  (e: "close"): void;
  (e: "back"): void;
  (e: "toggle-visibility", layerId: string): void;
  (e: "update-opacity", payload: { layerId: string; opacity: number }): void;
  (e: "update-filter", payload: { layerId: string; kecamatan: string | null; status: string | null }): void;
  (e: "zoom-to-layer", layer: ActiveLayerItem): void;
  (e: "remove-layer", layerId: string): void;
}>();

function hasLayerFilter(layer: ActiveLayerItem): boolean {
  return (
    !!(layer.filterKecamatan && layer.filterKecamatan !== "ALL") ||
    !!(layer.filterStatus && layer.filterStatus !== "ALL")
  );
}

function resetLayerFilter(layer: ActiveLayerItem) {
  layer.filterKecamatan = null;
  layer.filterStatus = null;
  emit("update-filter", {
    layerId: layer.id,
    kecamatan: null,
    status: null,
  });
}

function handleFilterKecamatanChange(layer: ActiveLayerItem, value: string) {
  const val = value === "ALL" ? null : value;
  layer.filterKecamatan = val;
  emit("update-filter", {
    layerId: layer.id,
    kecamatan: val,
    status: layer.filterStatus || null,
  });
}

function getLayerCode(layer: ActiveLayerItem | null): string {
  if (!layer) return "DATA";
  const proto = (layer.protocol || "").toUpperCase();
  if (proto.includes("MVT") || proto.includes("VECTOR")) return "MVT";
  if (proto.includes("WFS")) return "WFS";
  if (proto.includes("WMS")) return "WMS";
  if (proto.includes("GEOJSON")) return "GEO";
  return "DATA";
}
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0">
    <!-- Header Hero Section: Avatar Bulat, Judul Bold, Subtitle, Tombol Circle X -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-white/5 flex items-start justify-between gap-3 shrink-0 bg-transparent">
      <div class="flex items-start gap-3 min-w-0 flex-1">
        <!-- Circular Badge Avatar -->
        <div class="size-11 rounded-full bg-purple-700 dark:bg-purple-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider">
          {{ getLayerCode(props.selectedLayer) }}
        </div>

        <!-- Title & Subtitle -->
        <div class="min-w-0 flex-1 pt-0.5">
          <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight">
            {{ props.selectedLayer?.title || props.selectedLayer?.name }}
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal">
            {{ props.selectedLayer?.instansi || props.selectedLayer?.attribution || props.selectedLayer?.agency || 'Geoportal JIGN Bojonegoro' }}
          </p>
        </div>
      </div>

      <!-- Circle X Close Button -->
      <button
        type="button"
        class="relative after:absolute after:-inset-1.5 after:content-[''] size-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-95 shrink-0 mt-0.5"
        aria-label="Tutup detail dan kembali ke daftar layer"
        title="Tutup detail dan kembali ke daftar layer"
        @click="emit('close')"
      >
        <UIcon name="i-lucide-x" class="size-4" />
      </button>
    </div>

    <!-- Scrollable Cards dengan Card Border untuk Tiap Section -->
    <div v-if="props.selectedLayer" class="flex-1 overflow-y-auto px-4 sm:px-5 py-3 space-y-3">
      <!-- Card 1: Visibilitas & Transparansi Layer -->
      <div class="p-3.5 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/90 dark:border-white/10 shadow-xs space-y-3">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
          Kontrol Tampilan Layer
        </span>

        <!-- Switch Visibilitas -->
        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-white/5">
          <div>
            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 block">Visibilitas Lapisan</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">
              {{ props.selectedLayer.visible ? 'Layer aktif ditampilkan di atas peta' : 'Layer disembunyikan dari peta' }}
            </span>
          </div>

          <button
            type="button"
            class="relative inline-flex h-5 w-9 rounded-full transition-colors cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 shrink-0"
            :class="props.selectedLayer.visible ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600'"
            :aria-checked="props.selectedLayer.visible"
            role="switch"
            aria-label="Toggle visibilitas layer"
            @click="emit('toggle-visibility', props.selectedLayer!.id)"
          >
            <span
              class="inline-block size-4 rounded-full bg-white shadow-xs transition-transform duration-200 mt-0.5"
              :class="props.selectedLayer.visible ? 'translate-x-4' : 'translate-x-0.5'"
            />
          </button>
        </div>

        <!-- Slider Opasitas / Transparansi -->
        <div class="space-y-2">
          <div class="flex justify-between items-center">
            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Transparansi Real-Time</span>
            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 tabular-nums">
              {{ Math.round(props.selectedLayer.opacity * 100) }}%
            </span>
          </div>
          <USlider
            :model-value="props.selectedLayer.opacity"
            :min="0.1"
            :max="1"
            :step="0.05"
            size="xs"
            color="primary"
            @update:model-value="(val: any) => emit('update-opacity', { layerId: props.selectedLayer!.id, opacity: Number(val) })"
          />
          <div class="grid grid-cols-4 gap-1 pt-0.5">
            <button
              v-for="preset in [0.25, 0.5, 0.75, 1]"
              :key="preset"
              type="button"
              class="py-1 px-1 rounded-md text-xs font-medium tabular-nums transition-colors text-center cursor-pointer min-h-[44px]"
              :class="Math.abs(props.selectedLayer.opacity - preset) < 0.05
                ? 'bg-blue-600 text-white font-bold'
                : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
              @click="emit('update-opacity', { layerId: props.selectedLayer!.id, opacity: preset })"
            >
              {{ Math.round(preset * 100) }}%
            </button>
          </div>
        </div>
      </div>

      <!-- Card 2: Metadata & Spesifikasi Spasial -->
      <div class="p-3.5 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/90 dark:border-white/10 shadow-xs space-y-2.5">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
          Metadata & Spesifikasi Spasial
        </span>

        <div class="space-y-2 text-xs">
          <div v-if="props.selectedLayer.instansi || props.selectedLayer.attribution || props.selectedLayer.agency" class="flex justify-between gap-2 pb-1.5 border-b border-slate-100 dark:border-white/5">
            <span class="text-slate-500 dark:text-slate-400 shrink-0">Instansi / OPD:</span>
            <span class="font-medium text-slate-900 dark:text-slate-100 text-right">
              {{ props.selectedLayer.instansi || props.selectedLayer.attribution || props.selectedLayer.agency }}
            </span>
          </div>

          <div v-if="props.selectedLayer.layer_name || props.selectedLayer.wmsLayerName" class="flex justify-between gap-2 pb-1.5 border-b border-slate-100 dark:border-white/5">
            <span class="text-slate-500 dark:text-slate-400 shrink-0">Layer Identitas:</span>
            <span class="font-mono text-xs text-slate-800 dark:text-slate-200 text-right break-all">
              {{ props.selectedLayer.layer_name || props.selectedLayer.wmsLayerName }}
            </span>
          </div>

          <div v-if="props.selectedLayer.protocol" class="flex justify-between gap-2 pb-1.5 border-b border-slate-100 dark:border-white/5">
            <span class="text-slate-500 dark:text-slate-400">Protokol Akses:</span>
            <span class="font-mono uppercase font-bold text-blue-600 dark:text-blue-400 text-xs">
              {{ props.selectedLayer.protocol }}
            </span>
          </div>

          <div v-if="props.selectedLayer.legend_url" class="pt-1 space-y-1.5">
            <span class="text-xs text-slate-500 font-semibold block uppercase tracking-wider">Simbol Legenda Resmi:</span>
            <div class="p-2 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-100 dark:border-white/5 flex items-center justify-center">
              <img
                :src="props.selectedLayer.legend_url"
                alt="Legenda Resmi Simpul Geoportal"
                class="max-h-24 object-contain rounded bg-white p-1 border border-slate-200/80"
                loading="lazy"
              />
            </div>
          </div>

          <p v-if="props.selectedLayer.description" class="text-xs text-slate-600 dark:text-slate-400 pt-1.5 border-t border-slate-100 dark:border-white/5 leading-relaxed font-medium">
            {{ props.selectedLayer.description }}
          </p>
        </div>
      </div>

      <!-- Card 3: Filter Data Per Kecamatan -->
      <div class="p-3.5 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/90 dark:border-white/10 shadow-xs space-y-2.5">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            Filter Atribut Wilayah
          </span>
          <button
            v-if="hasLayerFilter(props.selectedLayer)"
            type="button"
            class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-bold cursor-pointer"
            @click="resetLayerFilter(props.selectedLayer)"
          >
            Reset Filter
          </button>
        </div>

        <!-- Indikator & Tombol Hapus Filter Wilayah Aktif -->
        <div
          v-if="props.selectedLayer.filterKecamatan && props.selectedLayer.filterKecamatan !== 'ALL'"
          class="flex items-center justify-between p-2 rounded-xl bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200/60 dark:border-blue-900/40"
        >
          <div class="flex items-center gap-2 min-w-0">
            <UIcon name="i-lucide-filter" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <div class="min-w-0">
              <span class="text-xs text-slate-500 dark:text-slate-400 block">Filter Aktif:</span>
              <span class="text-xs font-bold text-blue-900 dark:text-blue-200 truncate block">
                Kecamatan {{ props.selectedLayer.filterKecamatan }}
              </span>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800 transition-colors cursor-pointer shadow-2xs shrink-0"
            title="Bersihkan filter wilayah pada layer ini"
            @click="handleFilterKecamatanChange(props.selectedLayer, 'ALL')"
          >
            <UIcon name="i-lucide-x" class="size-3.5" />
            <span>Hapus Filter</span>
          </button>
        </div>

        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400 block">Pilih Kecamatan</label>
          <select
            :value="props.selectedLayer.filterKecamatan || 'ALL'"
            class="w-full bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/10 rounded-xl px-2.5 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-hidden focus:border-blue-500 min-h-[44px]"
            @change="handleFilterKecamatanChange(props.selectedLayer, ($event.target as HTMLSelectElement).value)"
          >
            <option value="ALL">Semua Kecamatan (28 Wilayah)</option>
            <option v-for="k in props.kecamatanEntries" :key="k.name" :value="k.name">
              Kecamatan {{ k.name }} (Zona {{ k.zona }})
            </option>
          </select>
        </div>
      </div>

      <!-- Card 4: Aksi Navigasi Spasial -->
      <div class="p-3.5 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/90 dark:border-white/10 shadow-xs space-y-2">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
          Aksi Navigasi Layer
        </span>

        <button
          type="button"
          class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
          @click="emit('zoom-to-layer', props.selectedLayer)"
        >
          <UIcon name="i-lucide-maximize-2" class="size-4" />
          <span>Zoom ke Cakupan Layer</span>
        </button>

        <button
          type="button"
          class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl border border-red-200/70 dark:border-red-900/40 bg-red-50/70 dark:bg-red-950/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-950/40 text-xs font-semibold transition-all cursor-pointer"
          @click="emit('remove-layer', props.selectedLayer.id)"
        >
          <UIcon name="i-lucide-trash-2" class="size-4" />
          <span>Hapus dari Peta</span>
        </button>
      </div>
    </div>

    <!-- Fallback -->
    <div v-else class="p-6 text-center text-xs text-slate-500">
      Layer tidak ditemukan.
    </div>
  </div>
</template>

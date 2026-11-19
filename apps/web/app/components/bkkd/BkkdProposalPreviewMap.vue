<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { BkkdProposal } from '~/types/bkkd';
import BasemapSwitcher from '~/components/common/BasemapSwitcher.vue';
import { useBasemapStore } from '~/stores/basemap';
import { createOpenLayersBasemapSource } from '~/utils/basemap-sources';

interface Props {
  proposal: BkkdProposal | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const bkkdApi = useBkkdApi();
const basemapStore = useBasemapStore();

const mapContainerRef = ref<HTMLElement | null>(null);
const loading = ref(false);
const errorState = ref<string | null>(null);
const basemap = ref<string>('osm');
const featureCount = ref(0);
const crossYearCount = ref(0);
const segmenCount = ref(0);
const lastSummary = ref<{
  jumlah_lokasi?: number;
  total_panjang_gis?: number;
  desa?: string | null;
  kecamatan?: string | null;
} | null>(null);

let mapInstance: any = null;
let baseTileLayer: any = null;
let olModules: any = null;
let batasSource: any = null;
let porosSource: any = null;
let kabupatenSource: any = null;
let segmenSource: any = null;
let crossYearSource: any = null;
let selectedSource: any = null;
let loadSeq = 0;

function formatNumber(val?: number | null, maxDecimals = 1): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return '0';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maxDecimals }).format(Number(val));
}

function getSelectedStrokeColor(status?: string | null): string {
  switch (status) {
    case 'aman':
      return '#059669';
    case 'lanjutan_ruas':
      return '#0284c7';
    case 'konfirmasi_belum_tuntas':
      return '#d97706';
    case 'duplikasi_tuntas':
      return '#e11d48';
    case 'kewenangan_kabupaten':
      return '#dc2626';
    default:
      return '#0369a1';
  }
}

async function initPreviewMap() {
  if (!import.meta.client || !mapContainerRef.value || mapInstance) return;

  const [
    { default: Map },
    { default: View },
    { default: TileLayer },
    { default: VectorLayer },
    { default: XYZ },
    { default: VectorSource },
    { default: GeoJSON },
    { Style, Stroke, Fill, Text },
    { fromLonLat },
  ] = await Promise.all([
    import('ol/Map'),
    import('ol/View'),
    import('ol/layer/Tile'),
    import('ol/layer/Vector'),
    import('ol/source/XYZ'),
    import('ol/source/Vector'),
    import('ol/format/GeoJSON'),
    import('ol/style'),
    import('ol/proj'),
  ]);

  olModules = { GeoJSON, Style, Stroke, Fill, Text, fromLonLat, XYZ };

  const initialBasemapItem = basemapStore.getByCode(basemap.value, 'openlayers');
  baseTileLayer = new TileLayer({
    visible: true,
    source: createOpenLayersBasemapSource(initialBasemapItem ?? basemap.value, { XYZ }) ?? undefined,
  });

  batasSource = new VectorSource();
  porosSource = new VectorSource();
  kabupatenSource = new VectorSource();
  segmenSource = new VectorSource();
  crossYearSource = new VectorSource();
  selectedSource = new VectorSource();

  const batasLayer = new VectorLayer({
    source: batasSource,
    zIndex: 5,
    style: new Style({
      stroke: new Stroke({ color: 'rgba(71, 85, 105, 0.75)', width: 1.5, lineDash: [6, 5] }),
      fill: new Fill({ color: 'rgba(148, 163, 184, 0.06)' }),
    }),
  });

  const porosLayer = new VectorLayer({
    source: porosSource,
    zIndex: 10,
    style: new Style({
      stroke: new Stroke({ color: '#6366f1', width: 2.5, lineDash: [7, 5] }),
    }),
  });

  const kabupatenLayer = new VectorLayer({
    source: kabupatenSource,
    zIndex: 12,
    style: (feature: any) => {
      const nama = feature.get('nama_ruas') || '';
      return new Style({
        stroke: new Stroke({ color: '#dc2626', width: 3 }),
        text: nama
          ? new Text({
              text: String(nama),
              font: '600 10px Inter, sans-serif',
              placement: 'line',
              overflow: true,
              fill: new Fill({ color: '#991b1b' }),
              stroke: new Stroke({ color: '#ffffff', width: 3 }),
            })
          : undefined,
      });
    },
  });

  const segmenLayer = new VectorLayer({
    source: segmenSource,
    zIndex: 15,
    style: (feature: any) => {
      const kondisi = String(feature.get('kondisi') || '').toLowerCase();
      let color = '#10b981';
      if (kondisi.includes('berat')) color = '#ef4444';
      else if (kondisi.includes('sedang') || kondisi.includes('ringan')) color = '#f59e0b';
      return new Style({ stroke: new Stroke({ color, width: 4 }) });
    },
  });

  const crossYearLayer = new VectorLayer({
    source: crossYearSource,
    zIndex: 20,
    style: (feature: any) => {
      const tag = feature.get('tagging_usulan') || 'PRKPD';
      const tahun = feature.get('tahun_anggaran') || '';
      const isRkpd = tag === 'RKPD';
      return new Style({
        stroke: new Stroke({
          color: isRkpd ? '#7c3aed' : '#ea580c',
          width: 4,
          lineDash: [8, 4],
        }),
        text: new Text({
          text: `${tag} ${tahun}`.trim(),
          font: '700 10px Inter, sans-serif',
          placement: 'line',
          overflow: true,
          fill: new Fill({ color: isRkpd ? '#4c1d95' : '#9a3412' }),
          stroke: new Stroke({ color: '#ffffff', width: 3 }),
        }),
      });
    },
  });

  const selectedLayer = new VectorLayer({
    source: selectedSource,
    zIndex: 30,
    style: (feature: any) => {
      const strokeColor = getSelectedStrokeColor(feature.get('status_analisa_spasial'));
      const urutan = feature.get('urutan') || 1;
      const nama = feature.get('nama_lokasi') || '';
      return [
        new Style({ stroke: new Stroke({ color: '#ffffff', width: 8 }) }),
        new Style({
          stroke: new Stroke({ color: strokeColor, width: 4.5 }),
          text: new Text({
            text: `#${urutan} ${nama}`.trim(),
            font: '700 11px Inter, sans-serif',
            overflow: true,
            offsetY: -12,
            fill: new Fill({ color: '#0f172a' }),
            stroke: new Stroke({ color: '#ffffff', width: 3.5 }),
          }),
        }),
      ];
    },
  });

  mapInstance = new Map({
    target: mapContainerRef.value,
    layers: [
      baseTileLayer,
      batasLayer,
      porosLayer,
      kabupatenLayer,
      segmenLayer,
      crossYearLayer,
      selectedLayer,
    ],
    view: new View({
      center: fromLonLat([111.88, -7.15]),
      zoom: 12,
      minZoom: 9,
      maxZoom: 19,
    }),
  });
}

function switchBasemap(mode: string) {
  basemap.value = mode;
  if (!baseTileLayer || !olModules) return;
  const item = basemapStore.getByCode(mode, 'openlayers');
  const source = createOpenLayersBasemapSource(item ?? mode, olModules);
  baseTileLayer.setSource(source);
  baseTileLayer.setVisible(Boolean(source));
}

watch(
  () => basemapStore.items,
  () => {
    if (baseTileLayer && olModules) {
      switchBasemap(basemap.value);
    }
  },
);

function fitToProposalExtent() {
  if (!mapInstance || !selectedSource || !batasSource) return;
  const extent = selectedSource.getFeatures().length > 0
    ? selectedSource.getExtent()
    : crossYearSource?.getFeatures().length > 0
      ? crossYearSource.getExtent()
      : batasSource.getFeatures().length > 0
        ? batasSource.getExtent()
        : null;

  if (!extent || !Number.isFinite(extent[0])) return;
  mapInstance.getView().fit(extent, {
    padding: [42, 42, 42, 42],
    maxZoom: 17,
    duration: 280,
  });
}

function clearMapSources() {
  batasSource?.clear();
  porosSource?.clear();
  kabupatenSource?.clear();
  segmenSource?.clear();
  crossYearSource?.clear();
  selectedSource?.clear();
  featureCount.value = 0;
  crossYearCount.value = 0;
  segmenCount.value = 0;
}

async function loadProposalSpatialPreview() {
  if (!import.meta.client) return;
  const prop = props.proposal;
  clearMapSources();
  errorState.value = null;
  lastSummary.value = null;

  if (!prop) return;

  const seq = ++loadSeq;
  loading.value = true;
  try {
    await initPreviewMap();
    const res = await bkkdApi.fetchProposalLokasi(prop.id);
    if (seq !== loadSeq) return;

    const format = new olModules.GeoJSON({
      featureProjection: 'EPSG:3857',
      dataProjection: 'EPSG:4326',
    });

    const readSafe = (fc: any) => {
      if (!fc) return [];
      if (fc.type === 'Feature') return format.readFeatures(fc);
      if (fc.type === 'FeatureCollection' && Array.isArray(fc.features) && fc.features.length > 0) {
        return format.readFeatures(fc);
      }
      return [];
    };

    const batasFeats = readSafe(res.layers?.batas_desa);
    const porosFeats = readSafe(res.layers?.jalan_poros_desa);
    const jaringanFeats = readSafe(res.layers?.jaringan_jalan_ruas);
    const kabupatenFeats = readSafe(res.layers?.jalan_kabupaten);
    const segmenFeats = readSafe(res.layers?.infrastruktur_segmen);
    const crossFeats = readSafe(res.layers?.usulan_tahun_lalu);
    const selectedFeats = readSafe(res.geojson);

    if (batasFeats.length) batasSource.addFeatures(batasFeats);
    if (porosFeats.length) porosSource.addFeatures(porosFeats);
    if (jaringanFeats.length) porosSource.addFeatures(jaringanFeats);
    if (kabupatenFeats.length) kabupatenSource.addFeatures(kabupatenFeats);
    if (segmenFeats.length) segmenSource.addFeatures(segmenFeats);
    if (crossFeats.length) crossYearSource.addFeatures(crossFeats);
    if (selectedFeats.length) selectedSource.addFeatures(selectedFeats);

    featureCount.value = selectedFeats.length;
    crossYearCount.value = crossFeats.length;
    segmenCount.value = segmenFeats.length;
    lastSummary.value = res.summary || null;

    await nextTick();
    mapInstance?.updateSize();
    fitToProposalExtent();
  } catch (err: any) {
    if (seq !== loadSeq) return;
    errorState.value = err?.data?.message || 'Gagal memuat geometri lokasi proposal untuk pratinjau peta.';
  } finally {
    if (seq === loadSeq) {
      loading.value = false;
    }
  }
}

const proposalTag = computed(() => props.proposal?.tagging_usulan || 'PRKPD');

watch(
  () => props.proposal?.id,
  () => {
    loadProposalSpatialPreview();
  },
  { immediate: true }
);

onMounted(async () => {
  await initPreviewMap();
  if (props.proposal) {
    await loadProposalSpatialPreview();
  }
});

onBeforeUnmount(() => {
  if (mapInstance) {
    mapInstance.setTarget(undefined);
    mapInstance = null;
  }
});
</script>

<template>
  <aside
    aria-label="Panel Pratinjau Peta Proposal"
    class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden flex flex-col"
  >
    <!-- Header -->
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-start justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/60">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-1.5">
          <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
            Pratinjau Peta Koridor
          </span>
          <span
            v-if="proposal"
            class="px-1.5 py-0.5 rounded text-[11px] font-semibold border"
            :class="
              proposalTag === 'RKPD'
                ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-800'
                : 'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/70 dark:text-sky-300 dark:border-sky-800'
            "
          >
            {{ proposalTag }} {{ proposal.tahun_anggaran }}
          </span>
        </div>
        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300 truncate">
          {{ proposal ? proposal.judul_kegiatan : 'Pilih proposal pada daftar untuk memeriksa koridor dan irisan desa.' }}
        </p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] sm:min-w-[36px] sm:min-h-[36px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        title="Tutup Pratinjau Peta"
        aria-label="Tutup Pratinjau Peta"
        @click="emit('close')"
      >
        <UIcon name="i-lucide-x" class="w-4 h-4" aria-hidden="true" />
      </button>
    </div>

    <!-- Basemap & Focus Controls (Medium Basemap) -->
    <div class="px-3.5 py-2 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 bg-white dark:bg-slate-900">
      <BasemapSwitcher
        :model-value="basemap"
        variant="medium"
        engine="openlayers"
        placement="top-left"
        @update:model-value="switchBasemap($event)"
      />

      <button
        type="button"
        :disabled="!proposal || loading"
        class="inline-flex items-center gap-1.5 px-2.5 min-h-[36px] sm:min-h-[30px] rounded-lg border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        @click="fitToProposalExtent"
      >
        <UIcon name="i-lucide-locate-fixed" class="w-3.5 h-3.5" aria-hidden="true" />
        <span>Pusatkan</span>
      </button>
    </div>

    <!-- Map Viewport -->
    <div class="relative w-full h-[300px] sm:h-[340px] lg:h-[380px] bg-slate-100 dark:bg-slate-800">
      <div ref="mapContainerRef" class="w-full h-full" />

      <div
        v-if="loading"
        role="status"
        class="absolute inset-0 bg-white/80 dark:bg-slate-950/80 flex flex-col items-center justify-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200 p-4 text-center"
      >
        <UIcon name="i-lucide-loader-2" class="w-5 h-5 animate-spin text-sky-700 dark:text-sky-400" aria-hidden="true" />
        <span>Memuat koridor lokasi dan layer pembanding desa...</span>
      </div>

      <div
        v-else-if="errorState"
        role="alert"
        class="absolute inset-0 bg-white/95 dark:bg-slate-950/95 flex flex-col items-center justify-center gap-2.5 p-5 text-center"
      >
        <p class="text-xs font-bold text-rose-800 dark:text-rose-300">{{ errorState }}</p>
        <button
          type="button"
          class="px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-xs font-semibold cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700"
          @click="loadProposalSpatialPreview"
        >
          Muat Ulang Peta
        </button>
      </div>

      <div
        v-else-if="!proposal"
        class="absolute inset-0 bg-slate-50/95 dark:bg-slate-900/95 flex flex-col items-center justify-center gap-2 p-6 text-center"
      >
        <UIcon name="i-lucide-map" class="w-8 h-8 text-slate-600 dark:text-slate-300" aria-hidden="true" />
        <p class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Proposal Dipilih</p>
        <p class="text-xs text-slate-600 dark:text-slate-300 max-w-xs">
          Tekan tombol Pratinjau Peta pada salah satu usulan di daftar untuk menampilkan koridornya beserta segmen eksisting desa.
        </p>
      </div>

      <div
        v-else-if="featureCount === 0"
        class="absolute inset-x-3 bottom-3 rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50/95 dark:bg-amber-950/95 p-3 text-xs text-amber-900 dark:text-amber-200 flex items-center justify-between gap-2"
      >
        <span>Usulan ini belum memiliki garis rute lokasi pada peta.</span>
        <NuxtLink
          :to="`/admin/bkkd/proposal/${proposal.id}`"
          class="shrink-0 px-2.5 py-1.5 rounded-md bg-amber-700 hover:bg-amber-800 text-white text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700"
        >
          Gambar Rute
        </NuxtLink>
      </div>
    </div>

    <!-- Legend & Summary Strip -->
    <div class="p-3.5 border-t border-slate-200 dark:border-slate-800 space-y-2.5 text-xs">
      <div v-if="proposal" class="grid grid-cols-3 gap-2 tabular-nums">
        <div class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800">
          <span class="block text-[11px] text-slate-600 dark:text-slate-300">Lokasi Usulan</span>
          <strong class="text-slate-900 dark:text-slate-100">{{ featureCount }} ruas</strong>
        </div>
        <div class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800">
          <span class="block text-[11px] text-slate-600 dark:text-slate-300">Panjang GIS</span>
          <strong class="text-slate-900 dark:text-slate-100">{{ formatNumber(lastSummary?.total_panjang_gis ?? proposal.total_panjang_gis) }} m</strong>
        </div>
        <div class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800">
          <span class="block text-[11px] text-slate-600 dark:text-slate-300">Pembanding Desa</span>
          <strong class="text-slate-900 dark:text-slate-100">{{ crossYearCount }} ruas · {{ segmenCount }} segmen</strong>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-700 dark:text-slate-300">
        <span class="inline-flex items-center gap-1.5">
          <span class="w-3 h-1 rounded-full bg-sky-700 inline-block" aria-hidden="true" />
          Usulan Dipilih
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-3 h-1 rounded-full bg-violet-600 inline-block" aria-hidden="true" />
          RKPD Lain
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-3 h-1 rounded-full bg-orange-600 inline-block" aria-hidden="true" />
          PRKPD Lain
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-3 h-1 rounded-full bg-emerald-500 inline-block" aria-hidden="true" />
          Segmen Eksisting
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-3 h-1 rounded-full bg-red-600 inline-block" aria-hidden="true" />
          Jalan Kabupaten
        </span>
      </div>

      <div v-if="proposal" class="pt-0.5">
        <NuxtLink
          :to="`/admin/bkkd/proposal/${proposal.id}`"
          class="w-full inline-flex items-center justify-center gap-2 px-4 min-h-[44px] sm:min-h-[38px] rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        >
          <UIcon name="i-lucide-maximize-2" class="w-4 h-4" aria-hidden="true" />
          <span>Buka Workspace Analisis Penuh</span>
        </NuxtLink>
      </div>
    </div>
  </aside>
</template>

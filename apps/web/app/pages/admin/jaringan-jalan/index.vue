<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import type { JaringanJalanKandidatItem } from '~/composables/useJaringanJalanApi';
import { useAuthStore } from '~/stores/auth';
import type {
  HierarkiJalanBkkd,
  JaringanJalanRuasFeature,
  StatusPromosiMasterBkkd,
} from '~/types/bkkd';

definePageMeta({
  middleware: ['auth'],
});

useHead({
  title: 'Jaringan Jalan Kabupaten Bojonegoro | WebGIS Bojonegoro',
});

const toast = useToast();
const auth = useAuthStore();
const bkkdApi = useBkkdApi();
const jaringanApi = useJaringanJalanApi();

const isBappedaOrAdmin = computed(() => {
  return auth.hasRole('admin') || auth.hasRole('verifierBappeda');
});

const activeTab = ref<'kandidat' | 'master'>('kandidat');
const loadingKandidat = ref(false);
const loadingMaster = ref(false);
const errorState = ref<string | null>(null);

const kandidatList = ref<JaringanJalanKandidatItem[]>([]);
const kandidatMeta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const masterFeatures = ref<JaringanJalanRuasFeature[]>([]);
const masterMeta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const masterSummary = ref({
  total_ruas: 0,
  total_panjang_m: 0,
  jumlah_poros_desa: 0,
  jumlah_lingkungan: 0,
  jumlah_usaha_tani: 0,
  jumlah_kabupaten: 0,
});

const filters = reactive({
  status_promosi: '' as '' | StatusPromosiMasterBkkd | 'semua',
  hierarki_jalan: '' as '' | HierarkiJalanBkkd,
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  search: '',
  kandidatPage: 1,
  masterPage: 1,
});

const selectedCandidate = ref<JaringanJalanKandidatItem | null>(null);
const selectedMaster = ref<JaringanJalanRuasFeature | null>(null);

const validatingId = ref<string | null>(null);
const savingMasterId = ref<string | null>(null);
const deletingMasterId = ref<string | null>(null);

const decisionForm = reactive({
  keputusan: 'setujui_baru' as 'setujui_baru' | 'ekstensi_potong_unik' | 'tolak',
  nama_ruas: '',
  kode_ruas: '',
  hierarki_jalan: 'lingkungan' as HierarkiJalanBkkd,
  perkerasan: '',
  kondisi: '',
  catatan: '',
});

const masterEditForm = reactive({
  kode_ruas: '',
  nama_ruas: '',
  hierarki_jalan: 'lingkungan' as HierarkiJalanBkkd,
  lebar_m: null as number | null,
  perkerasan: '',
  kondisi: '',
  catatan_validasi: '',
});

const mapContainerRef = ref<HTMLElement | null>(null);
const basemap = ref<'osm' | 'satellite' | 'carto'>('osm');
let mapInstance: any = null;
let tileLayers: Record<'osm' | 'satellite' | 'carto', any> | null = null;
let olModules: any = null;
let porosLayerSource: any = null;
let masterLayerSource: any = null;
let highlightSource: any = null;

function formatNumber(val?: number | null, maxDecimals = 1): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return '0';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maxDecimals }).format(Number(val));
}

function getHierarkiLabel(h?: string | null): string {
  switch (h) {
    case 'kabupaten':
      return 'Jalan Kabupaten';
    case 'poros_desa':
      return 'Jalan Poros Desa';
    case 'usaha_tani':
      return 'Jalan Usaha Tani';
    default:
      return 'Jalan Lingkungan';
  }
}

function getPromosiStatusBadge(status?: StatusPromosiMasterBkkd | string) {
  switch (status) {
    case 'disetujui':
      return {
        label: 'Tervalidasi di Master',
        cls: 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-200 dark:border-emerald-700',
      };
    case 'diajukan':
      return {
        label: 'Menunggu Validasi',
        cls: 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/70 dark:text-amber-200 dark:border-amber-700',
      };
    case 'kandidat':
      return {
        label: 'Kandidat Siap Diajukan',
        cls: 'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/70 dark:text-sky-200 dark:border-sky-700',
      };
    case 'ditolak':
      return {
        label: 'Ditolak',
        cls: 'bg-rose-50 text-rose-900 border-rose-300 dark:bg-rose-950/70 dark:text-rose-200 dark:border-rose-700',
      };
    default:
      return {
        label: 'Tidak Perlu / Belum Memenuhi',
        cls: 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600',
      };
  }
}

async function initMap() {
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

  olModules = { GeoJSON, Style, Stroke, Fill, Text, fromLonLat };

  tileLayers = {
    osm: new TileLayer({
      visible: basemap.value === 'osm',
      source: new XYZ({ url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', crossOrigin: 'anonymous', maxZoom: 19 }),
    }),
    satellite: new TileLayer({
      visible: basemap.value === 'satellite',
      source: new XYZ({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        crossOrigin: 'anonymous',
        maxZoom: 19,
      }),
    }),
    carto: new TileLayer({
      visible: basemap.value === 'carto',
      source: new XYZ({ url: 'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', crossOrigin: 'anonymous', maxZoom: 19 }),
    }),
  };

  porosLayerSource = new VectorSource();
  masterLayerSource = new VectorSource();
  highlightSource = new VectorSource();

  const porosLayer = new VectorLayer({
    source: porosLayerSource,
    zIndex: 10,
    style: new Style({
      stroke: new Stroke({ color: '#6366f1', width: 3, lineDash: [7, 5] }),
    }),
  });

  const masterLayer = new VectorLayer({
    source: masterLayerSource,
    zIndex: 18,
    style: (feat: any) =>
      new Style({
        stroke: new Stroke({ color: '#0d9488', width: 4 }),
        text: feat.get('nama_ruas')
          ? new Text({
              text: String(feat.get('nama_ruas')),
              font: '600 10px Inter, sans-serif',
              placement: 'line',
              overflow: true,
              fill: new Fill({ color: '#134e4a' }),
              stroke: new Stroke({ color: '#ffffff', width: 3 }),
            })
          : undefined,
      }),
  });

  const highlightLayer = new VectorLayer({
    source: highlightSource,
    zIndex: 30,
    style: [
      new Style({ stroke: new Stroke({ color: '#ffffff', width: 9 }) }),
      new Style({ stroke: new Stroke({ color: '#d97706', width: 5 }) }),
    ],
  });

  mapInstance = new Map({
    target: mapContainerRef.value,
    layers: [tileLayers.osm, tileLayers.satellite, tileLayers.carto, porosLayer, masterLayer, highlightLayer],
    view: new View({
      center: fromLonLat([111.88, -7.15]),
      zoom: 12,
      minZoom: 9,
      maxZoom: 19,
    }),
  });
}

function switchBasemap(mode: 'osm' | 'satellite' | 'carto') {
  basemap.value = mode;
  if (!tileLayers) return;
  tileLayers.osm.setVisible(mode === 'osm');
  tileLayers.satellite.setVisible(mode === 'satellite');
  tileLayers.carto.setVisible(mode === 'carto');
}

async function focusFeatureOnMap(geojson: any, idDesa?: number | null) {
  await initMap();
  if (!mapInstance || !olModules || !highlightSource) return;

  highlightSource.clear();
  porosLayerSource?.clear();

  const format = new olModules.GeoJSON({
    dataProjection: 'EPSG:4326',
    featureProjection: 'EPSG:3857',
  });

  if (idDesa) {
    try {
      const ctx = await bkkdApi.fetchRefJalanPoros(idDesa);
      if (ctx.layers?.jalan_poros_desa?.features?.length) {
        porosLayerSource.addFeatures(format.readFeatures(ctx.layers.jalan_poros_desa));
      }
      if (ctx.layers?.jaringan_jalan_ruas?.features?.length) {
        masterLayerSource.clear();
        masterLayerSource.addFeatures(format.readFeatures(ctx.layers.jaringan_jalan_ruas));
      }
    } catch {
      // Ignore context overlay failures
    }
  }

  if (geojson) {
    try {
      const geomObj = typeof geojson === 'string' ? JSON.parse(geojson) : geojson;
      const feat = format.readFeature({
        type: 'Feature',
        geometry: geomObj.geometry || geomObj,
      });
      highlightSource.addFeature(feat);
      const ext = highlightSource.getExtent();
      if (ext && Number.isFinite(ext[0])) {
        await nextTick();
        mapInstance.updateSize();
        mapInstance.getView().fit(ext, { padding: [44, 44, 44, 44], maxZoom: 17, duration: 280 });
      }
    } catch {
      // Ignore invalid geometry
    }
  }
}

async function selectCandidate(item: JaringanJalanKandidatItem) {
  selectedCandidate.value = item;
  selectedMaster.value = null;

  decisionForm.nama_ruas = item.nama_ruas_usulan || item.nama_lokasi;
  decisionForm.kode_ruas = item.jaringan_ruas?.kode_ruas != null ? String(item.jaringan_ruas.kode_ruas) : '';
  decisionForm.hierarki_jalan = (item.hierarki_jalan_usulan as HierarkiJalanBkkd) || 'lingkungan';
  decisionForm.keputusan =
    item.validasi_promosi_spasial?.rekomendasi_aksi === 'ekstensi_ruas'
      ? 'ekstensi_potong_unik'
      : 'setujui_baru';
  decisionForm.catatan = item.catatan_promosi_master || '';

  await focusFeatureOnMap(item.geojson, item.proposal?.id_desa);
}

async function selectMasterRuas(feat: JaringanJalanRuasFeature) {
  selectedMaster.value = feat;
  selectedCandidate.value = null;

  masterEditForm.kode_ruas = feat.properties.kode_ruas != null ? String(feat.properties.kode_ruas) : '';
  masterEditForm.nama_ruas = feat.properties.nama_ruas;
  masterEditForm.hierarki_jalan = feat.properties.hierarki_jalan;
  masterEditForm.lebar_m = feat.properties.lebar_m ?? null;
  masterEditForm.perkerasan = feat.properties.perkerasan || '';
  masterEditForm.kondisi = feat.properties.kondisi || '';
  masterEditForm.catatan_validasi = '';

  await focusFeatureOnMap(feat.geometry, feat.properties.id_desa);
}

async function loadKandidatList() {
  loadingKandidat.value = true;
  errorState.value = null;
  try {
    const query: Record<string, any> = {
      page: filters.kandidatPage,
      per_page: 20,
    };
    if (filters.status_promosi) query.status_promosi = filters.status_promosi;
    if (filters.id_kecamatan) query.id_kecamatan = filters.id_kecamatan;
    if (filters.id_desa) query.id_desa = filters.id_desa;
    if (filters.search.trim()) query.search = filters.search.trim();

    const res = await jaringanApi.fetchKandidatList(query);
    kandidatList.value = res.data || [];
    if (res.meta) kandidatMeta.value = res.meta;

    if (kandidatList.value.length > 0 && activeTab.value === 'kandidat') {
      const current = kandidatList.value.find((k) => k.id === selectedCandidate.value?.id) || kandidatList.value[0];
      if (current) await selectCandidate(current);
    }
  } catch (err: any) {
    errorState.value = err?.data?.message || 'Gagal memuat antrean kandidat ruas jalan.';
  } finally {
    loadingKandidat.value = false;
  }
}

async function loadMasterList() {
  loadingMaster.value = true;
  errorState.value = null;
  try {
    const query: Record<string, any> = {
      page: filters.masterPage,
      per_page: 20,
    };
    if (filters.hierarki_jalan) query.hierarki_jalan = filters.hierarki_jalan;
    if (filters.id_kecamatan) query.id_kecamatan = filters.id_kecamatan;
    if (filters.id_desa) query.id_desa = filters.id_desa;
    if (filters.search.trim()) query.search = filters.search.trim();

    const res = await jaringanApi.fetchRuasList(query);
    masterFeatures.value = res.data || [];
    if (res.meta) masterMeta.value = res.meta;
    if (res.summary) masterSummary.value = res.summary;

    await initMap();
    if (masterLayerSource && olModules && res.geojson?.features?.length) {
      const format = new olModules.GeoJSON({ dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' });
      masterLayerSource.clear();
      masterLayerSource.addFeatures(format.readFeatures(res.geojson));
      if (activeTab.value === 'master') {
        const ext = masterLayerSource.getExtent();
        if (ext && Number.isFinite(ext[0])) {
          mapInstance?.getView().fit(ext, { padding: [40, 40, 40, 40], maxZoom: 15, duration: 280 });
        }
      }
    }
  } catch (err: any) {
    errorState.value = err?.data?.message || 'Gagal memuat master jaringan jalan.';
  } finally {
    loadingMaster.value = false;
  }
}

function handleWilayahChange(val: { id_kecamatan: number | null; id_desa: number | null }) {
  filters.id_kecamatan = val.id_kecamatan;
  filters.id_desa = val.id_desa;
  filters.kandidatPage = 1;
  filters.masterPage = 1;
  loadKandidatList();
  loadMasterList();
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    filters.kandidatPage = 1;
    filters.masterPage = 1;
    loadKandidatList();
    loadMasterList();
  }, 300);
}

watch(
  () => [filters.status_promosi, filters.hierarki_jalan],
  () => {
    filters.kandidatPage = 1;
    filters.masterPage = 1;
    loadKandidatList();
    loadMasterList();
  }
);

watch(activeTab, async (tab) => {
  if (tab === 'master' && masterFeatures.value.length > 0 && !selectedMaster.value) {
    await selectMasterRuas(masterFeatures.value[0]!);
  } else if (tab === 'kandidat' && kandidatList.value.length > 0 && !selectedCandidate.value) {
    await selectCandidate(kandidatList.value[0]!);
  }
});

async function submitBappedaDecision() {
  const item = selectedCandidate.value;
  if (!item || !item.proposal) return;
  validatingId.value = item.id;
  try {
    const rawDecisionKode = String(decisionForm.kode_ruas ?? '').trim();
    const res = await jaringanApi.validasiPromosi(item.proposal.id, item.id, {
      keputusan: decisionForm.keputusan,
      nama_ruas: decisionForm.nama_ruas.trim() || undefined,
      kode_ruas: rawDecisionKode !== '' ? rawDecisionKode : undefined,
      hierarki_jalan: decisionForm.hierarki_jalan,
      perkerasan: decisionForm.perkerasan.trim() || undefined,
      kondisi: decisionForm.kondisi.trim() || undefined,
      catatan: decisionForm.catatan.trim() || undefined,
    });
    toast.add({
      title: 'Validasi Ruas Disimpan',
      description: res.message || 'Status promosi ruas berhasil diperbarui.',
      color: 'success',
    });
    await Promise.all([loadKandidatList(), loadMasterList()]);
  } catch (err: any) {
    toast.add({
      title: 'Validasi Gagal',
      description: err?.data?.message || 'Lokasi tidak memenuhi syarat validasi spasial.',
      color: 'error',
    });
  } finally {
    validatingId.value = null;
  }
}

async function saveMasterRuasChanges() {
  const feat = selectedMaster.value;
  if (!feat) return;
  savingMasterId.value = feat.properties.id;
  try {
    const rawEditKode = String(masterEditForm.kode_ruas ?? '').trim();
    const res = await jaringanApi.updateRuas(feat.properties.id, {
      kode_ruas: rawEditKode !== '' ? rawEditKode : null,
      nama_ruas: masterEditForm.nama_ruas.trim(),
      hierarki_jalan: masterEditForm.hierarki_jalan,
      lebar_m: masterEditForm.lebar_m,
      perkerasan: masterEditForm.perkerasan.trim() || null,
      kondisi: masterEditForm.kondisi.trim() || null,
      catatan_validasi: masterEditForm.catatan_validasi.trim() || null,
    });
    toast.add({
      title: 'Ruas Diperbarui',
      description: res.message || 'Data master jalan poros & desa berhasil diperbarui.',
      color: 'success',
    });
    await loadMasterList();
    if (res.data) {
      await selectMasterRuas(res.data);
    }
  } catch (err: any) {
    toast.add({
      title: 'Gagal Memperbarui Ruas',
      description: err?.data?.message || 'Terjadi kesalahan saat menyimpan perubahan ruas.',
      color: 'error',
    });
  } finally {
    savingMasterId.value = null;
  }
}

async function archiveMasterRuas(feat: JaringanJalanRuasFeature) {
  deletingMasterId.value = feat.properties.id;
  try {
    const res = await jaringanApi.deleteRuas(feat.properties.id);
    toast.add({
      title: 'Ruas Dihapus',
      description: res.message || 'Ruas telah dihapus dari master jalan poros & desa.',
      color: 'success',
    });
    selectedMaster.value = null;
    await Promise.all([loadMasterList(), loadKandidatList()]);
  } catch (err: any) {
    toast.add({
      title: 'Gagal Mengarsipkan Ruas',
      description: err?.data?.message || 'Tidak dapat mengarsipkan ruas.',
      color: 'error',
    });
  } finally {
    deletingMasterId.value = null;
  }
}

onMounted(async () => {
  await initMap();
  await Promise.all([loadMasterList(), loadKandidatList()]);
});

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer);
  if (mapInstance) {
    mapInstance.setTarget(undefined);
    mapInstance = null;
  }
});
</script>

<template>
  <div class="w-full space-y-4">
    <header class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
          Jaringan Jalan Kabupaten Bojonegoro
        </h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-600 dark:text-slate-300 max-w-[72ch]">
          Inventaris master ruas jalan terpadu (jalan poros & desa) yang diperkaya dan divalidasi secara spasial dari usulan proposal.
        </p>
      </div>

      <!-- Summary Counters & Cross-Module Link -->
      <div class="flex flex-wrap items-center gap-2.5 shrink-0">
        <div class="grid grid-cols-3 gap-3 rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2 tabular-nums">
          <div>
            <span class="block text-[11px] text-slate-600 dark:text-slate-300">Ruas Master</span>
            <strong class="text-base font-bold text-slate-900 dark:text-slate-100">
              {{ formatNumber(masterSummary.total_ruas, 0) }}
            </strong>
          </div>
          <div>
            <span class="block text-[11px] text-slate-600 dark:text-slate-300">Total Panjang</span>
            <strong class="text-base font-bold text-emerald-700 dark:text-emerald-300">
              {{ formatNumber(masterSummary.total_panjang_m, 0) }} m
            </strong>
          </div>
          <div>
            <span class="block text-[11px] text-slate-600 dark:text-slate-300">Kandidat</span>
            <strong class="text-base font-bold text-sky-700 dark:text-sky-300">
              {{ formatNumber(kandidatMeta.total, 0) }}
            </strong>
          </div>
        </div>

        <NuxtLink
          to="/admin/dataset/jalan-poros-desa"
          class="inline-flex items-center gap-2 px-3.5 min-h-[44px] sm:min-h-[38px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        >
          <UIcon name="i-lucide-database" class="w-4 h-4" aria-hidden="true" />
          <span>Dataset Jalan Poros</span>
        </NuxtLink>

        <NuxtLink
          to="/admin/bkkd/proposal"
          class="inline-flex items-center gap-2 px-3.5 min-h-[44px] sm:min-h-[38px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        >
          <UIcon name="i-lucide-file-text" class="w-4 h-4" aria-hidden="true" />
          <span>Proposal BKKD</span>
        </NuxtLink>
      </div>
    </header>

    <!-- Tab Switcher & Filters -->
    <section class="p-3.5 rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div role="tablist" aria-label="Mode Pengelolaan Jaringan Jalan" class="inline-flex rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 p-0.5">
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'kandidat'"
            class="px-3.5 min-h-[44px] sm:min-h-[34px] rounded-md text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
            :class="
              activeTab === 'kandidat'
                ? 'bg-sky-700 text-white'
                : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700'
            "
            @click="activeTab = 'kandidat'"
          >
            Antrean Validasi Proposal ({{ kandidatMeta.total }})
          </button>
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'master'"
            class="px-3.5 min-h-[44px] sm:min-h-[34px] rounded-md text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
            :class="
              activeTab === 'master'
                ? 'bg-emerald-700 text-white'
                : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700'
            "
            @click="activeTab = 'master'"
          >
            Master Jalan Poros &amp; Desa ({{ masterSummary.total_ruas }})
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300 tabular-nums">
          <span>Lingkungan: <strong>{{ masterSummary.jumlah_lingkungan }}</strong></span>
          <span>Poros Desa: <strong>{{ masterSummary.jumlah_poros_desa }}</strong></span>
          <span>Usaha Tani: <strong>{{ masterSummary.jumlah_usaha_tani }}</strong></span>
          <span>Kabupaten: <strong>{{ masterSummary.jumlah_kabupaten }}</strong></span>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5 items-center">
        <div class="lg:col-span-4 relative">
          <UIcon name="i-lucide-search" class="w-4 h-4 text-slate-600 dark:text-slate-300 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true" />
          <input
            v-model="filters.search"
            type="text"
            aria-label="Cari nama ruas, kode ruas, atau proposal"
            placeholder="Cari nama ruas, kode, nomor proposal..."
            class="w-full min-h-[44px] sm:min-h-[36px] pl-9 pr-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-600 dark:placeholder:text-slate-300 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
            @input="onSearchInput"
          />
        </div>

        <div class="lg:col-span-3">
          <select
            v-if="activeTab === 'kandidat'"
            v-model="filters.status_promosi"
            aria-label="Filter Status Promosi"
            class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-slate-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
          >
            <option value="">Antrean & Kandidat</option>
            <option value="diajukan">Menunggu Validasi</option>
            <option value="kandidat">Kandidat Otomatis</option>
            <option value="disetujui">Sudah Terverifikasi</option>
            <option value="ditolak">Ditolak</option>
            <option value="semua">Semua Riwayat</option>
          </select>

          <select
            v-else
            v-model="filters.hierarki_jalan"
            aria-label="Filter Hierarki Jalan"
            class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-slate-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-700"
          >
            <option value="">Semua Hierarki Jalan</option>
            <option value="lingkungan">Jalan Lingkungan</option>
            <option value="poros_desa">Jalan Poros Desa</option>
            <option value="usaha_tani">Jalan Usaha Tani</option>
            <option value="kabupaten">Jalan Kabupaten</option>
          </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-5">
          <WilayahSelector
            :model-kecamatan="filters.id_kecamatan"
            :model-desa="filters.id_desa"
            :show-labels="false"
            :allow-all="true"
            layout="row"
            size="md"
            @change="handleWilayahChange"
          />
        </div>
      </div>
    </section>

    <div
      v-if="errorState"
      role="alert"
      class="p-4 rounded-xl border border-rose-300 bg-rose-50 dark:bg-rose-950/40 dark:border-rose-800 text-rose-900 dark:text-rose-200 text-xs flex items-center justify-between gap-3"
    >
      <span>{{ errorState }}</span>
      <button
        type="button"
        class="px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg bg-rose-700 text-white font-semibold cursor-pointer"
        @click="activeTab === 'kandidat' ? loadKandidatList() : loadMasterList()"
      >
        Muat Ulang
      </button>
    </div>

    <!-- Split Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
      <!-- Left List -->
      <section class="lg:col-span-6 rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <!-- Tab 1: Candidate Queue -->
        <template v-if="activeTab === 'kandidat'">
          <div v-if="loadingKandidat && kandidatList.length === 0" class="p-6 text-center text-xs text-slate-600 dark:text-slate-300">
            Memuat kandidat ruas jalan dari proposal...
          </div>
          <div v-else-if="kandidatList.length === 0" class="p-10 text-center space-y-2 text-xs text-slate-600 dark:text-slate-300">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Belum Ada Kandidat Ruas pada Filter Ini</p>
            <p>Lokasi proposal di luar Jalan Poros Desa yang lolos cek geometri dan batas desa otomatis muncul di daftar ini.</p>
          </div>
          <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
            <article
              v-for="item in kandidatList"
              :key="item.id"
              class="p-3.5 space-y-2 transition-colors"
              :class="selectedCandidate?.id === item.id ? 'bg-sky-50/80 dark:bg-sky-950/35' : ''"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span
                      class="px-2 py-0.5 rounded text-[11px] font-semibold border"
                      :class="getPromosiStatusBadge(item.status_promosi_master).cls"
                    >
                      {{ getPromosiStatusBadge(item.status_promosi_master).label }}
                    </span>
                    <span
                      v-if="item.proposal"
                      class="text-xs font-semibold text-slate-700 dark:text-slate-200 tabular-nums"
                    >
                      {{ item.proposal.tagging_usulan }} {{ item.proposal.tahun_anggaran }} · {{ item.proposal.nomor_registrasi }}
                    </span>
                  </div>
                  <h2 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">
                    {{ item.nama_ruas_usulan || item.nama_lokasi }}
                  </h2>
                  <p class="text-xs text-slate-600 dark:text-slate-300">
                    {{ item.proposal?.judul_kegiatan || '-' }} · Desa {{ item.proposal?.nama_desa || '-' }} ({{ formatNumber(item.panjang_gis_m, 0) }} m)
                  </p>
                </div>

                <button
                  type="button"
                  :aria-pressed="selectedCandidate?.id === item.id"
                  class="shrink-0 px-3 min-h-[44px] sm:min-h-[34px] rounded-lg border text-xs font-semibold cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                  :class="
                    selectedCandidate?.id === item.id
                      ? 'border-sky-700 bg-sky-700 text-white'
                      : 'border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800'
                  "
                  @click="selectCandidate(item)"
                >
                  Periksa
                </button>
              </div>
            </article>
          </div>
        </template>

        <!-- Tab 2: Master Road Network -->
        <template v-else>
          <div v-if="loadingMaster && masterFeatures.length === 0" class="p-6 text-center text-xs text-slate-600 dark:text-slate-300">
            Memuat Master Jalan Poros &amp; Desa Bojonegoro...
          </div>
          <div v-else-if="masterFeatures.length === 0" class="p-10 text-center space-y-2 text-xs text-slate-600 dark:text-slate-300">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Belum Ada Ruas Master</p>
            <p>Validasi kandidat dari tab Antrean Validasi Proposal untuk menambahkan ruas baru langsung ke Master Jalan Poros &amp; Desa.</p>
          </div>
          <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
            <article
              v-for="feat in masterFeatures"
              :key="feat.properties.id"
              class="p-3.5 flex items-start justify-between gap-2 transition-colors"
              :class="selectedMaster?.properties.id === feat.properties.id ? 'bg-emerald-50/80 dark:bg-emerald-950/35' : ''"
            >
              <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-200 dark:border-emerald-700">
                    {{ feat.properties.kode_ruas || 'NON-KODE' }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                    {{ getHierarkiLabel(feat.properties.hierarki_jalan) }}
                  </span>
                </div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                  {{ feat.properties.nama_ruas }}
                </h2>
                <p class="text-xs text-slate-600 dark:text-slate-300 tabular-nums">
                  Desa {{ feat.properties.nama_desa || '-' }}, Kec. {{ feat.properties.nama_kecamatan || '-' }} · {{ formatNumber(feat.properties.panjang_m, 0) }} m
                </p>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <NuxtLink
                  v-if="feat.properties.sumber_proposal_id"
                  :to="`/admin/bkkd/proposal/${feat.properties.sumber_proposal_id}`"
                  class="px-2.5 min-h-[44px] sm:min-h-[34px] inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                  Proposal
                </NuxtLink>
                <button
                  type="button"
                  :aria-pressed="selectedMaster?.properties.id === feat.properties.id"
                  class="px-3 min-h-[44px] sm:min-h-[34px] rounded-lg border text-xs font-semibold cursor-pointer"
                  :class="
                    selectedMaster?.properties.id === feat.properties.id
                      ? 'border-emerald-700 bg-emerald-700 text-white'
                      : 'border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800'
                  "
                  @click="selectMasterRuas(feat)"
                >
                  Kelola
                </button>
              </div>
            </article>
          </div>
        </template>
      </section>

      <!-- Right Map & Validation / Master Editor -->
      <aside class="lg:col-span-6 lg:sticky lg:top-4 space-y-3">
        <div class="rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
          <div class="px-3.5 py-2.5 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 bg-slate-50 dark:bg-slate-950/60">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
              Peta Konteks Jaringan Jalan
            </span>
            <div class="inline-flex rounded-lg border border-slate-300 dark:border-slate-700 p-0.5 bg-white dark:bg-slate-800">
              <button
                v-for="mode in [
                  { id: 'osm', label: 'OSM' },
                  { id: 'satellite', label: 'Satelit' },
                  { id: 'carto', label: 'Terang' },
                ]"
                :key="mode.id"
                type="button"
                class="px-2.5 min-h-[32px] rounded-md text-xs font-semibold cursor-pointer"
                :class="basemap === mode.id ? 'bg-slate-900 text-white dark:bg-sky-700' : 'text-slate-700 dark:text-slate-200'"
                @click="switchBasemap(mode.id as 'osm' | 'satellite' | 'carto')"
              >
                {{ mode.label }}
              </button>
            </div>
          </div>

          <div ref="mapContainerRef" class="w-full h-[280px] sm:h-[320px] bg-slate-100 dark:bg-slate-800" />

          <div class="px-3.5 py-2 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3 text-[11px] text-slate-700 dark:text-slate-200">
            <span class="inline-flex items-center gap-1.5">
              <span class="w-3 h-1 rounded-full bg-amber-600 inline-block" aria-hidden="true" />
              Kandidat / Ruas Dipilih
            </span>
            <span class="inline-flex items-center gap-1.5">
              <span class="w-3 h-1 rounded-full bg-teal-600 inline-block" aria-hidden="true" />
              Promosi Proposal Baru
            </span>
            <span class="inline-flex items-center gap-1.5">
              <span class="w-3 h-1 rounded-full bg-indigo-500 inline-block" aria-hidden="true" />
              Jalan Poros Eksisting
            </span>
          </div>
        </div>

        <!-- Candidate Spatial Validation Panel -->
        <div
          v-if="selectedCandidate"
          class="rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3.5"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                Gerbang Validasi Spasial: {{ selectedCandidate.nama_ruas_usulan || selectedCandidate.nama_lokasi }}
              </h3>
              <p class="text-xs text-slate-600 dark:text-slate-300">
                Rekomendasi sistem: <strong>{{ selectedCandidate.validasi_promosi_spasial?.rekomendasi_aksi || 'evaluasi' }}</strong>
              </p>
            </div>
            <NuxtLink
              v-if="selectedCandidate.proposal"
              :to="`/admin/bkkd/proposal/${selectedCandidate.proposal.id}`"
              class="text-xs font-semibold text-sky-700 dark:text-sky-300 hover:underline shrink-0"
            >
              Buka Proposal
            </NuxtLink>
          </div>

          <div
            v-if="selectedCandidate.validasi_promosi_spasial?.checks?.length"
            class="grid grid-cols-1 sm:grid-cols-2 gap-2"
          >
            <div
              v-for="chk in selectedCandidate.validasi_promosi_spasial.checks"
              :key="chk.kode"
              class="p-2.5 rounded-lg border text-xs space-y-0.5"
              :class="
                chk.lolos
                  ? 'border-emerald-300 bg-emerald-50/70 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                  : 'border-rose-300 bg-rose-50/70 text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
              "
            >
              <div class="font-bold flex items-center justify-between gap-1">
                <span>{{ chk.label }}</span>
                <span>{{ chk.lolos ? 'LOLOS' : 'GAGAL' }}</span>
              </div>
              <p class="text-[11px] opacity-90">{{ chk.detail }}</p>
            </div>
          </div>

          <div
            v-if="isBappedaOrAdmin && selectedCandidate.status_promosi_master !== 'disetujui'"
            class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2.5"
          >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Nama Ruas Resmi Master
                </label>
                <input
                  v-model="decisionForm.nama_ruas"
                  type="text"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Hierarki Jalan
                </label>
                <select
                  v-model="decisionForm.hierarki_jalan"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                >
                  <option value="lingkungan">Jalan Lingkungan</option>
                  <option value="poros_desa">Jalan Poros Desa</option>
                  <option value="usaha_tani">Jalan Usaha Tani</option>
                  <option value="kabupaten">Jalan Kabupaten</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Keputusan Verifikator
                </label>
                <select
                  v-model="decisionForm.keputusan"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                >
                  <option value="setujui_baru">Setujui Sebagai Ruas Baru</option>
                  <option value="ekstensi_potong_unik">Potong Irisan & Tambah Unik</option>
                  <option value="tolak">Tolak Promosi</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Kode Ruas (Angka, Opsional)
                </label>
                <input
                  v-model="decisionForm.kode_ruas"
                  type="number"
                  min="1"
                  placeholder="Otomatis jika kosong"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Catatan Validasi
                </label>
                <input
                  v-model="decisionForm.catatan"
                  type="text"
                  placeholder="Opsional"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>
            </div>

            <div class="flex justify-end">
              <button
                type="button"
                :disabled="validatingId === selectedCandidate.id || (!selectedCandidate.validasi_promosi_spasial?.lolos_validasi && decisionForm.keputusan !== 'tolak')"
                class="inline-flex items-center gap-2 px-4 min-h-[44px] sm:min-h-[38px] rounded-lg text-xs font-semibold text-white cursor-pointer disabled:opacity-50"
                :class="decisionForm.keputusan === 'tolak' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-emerald-700 hover:bg-emerald-800'"
                @click="submitBappedaDecision"
              >
                <UIcon
                  :name="validatingId === selectedCandidate.id ? 'i-lucide-loader-2' : 'i-lucide-shield-check'"
                  class="w-4 h-4"
                  :class="{ 'animate-spin': validatingId === selectedCandidate.id }"
                  aria-hidden="true"
                />
                <span>
                  {{
                    decisionForm.keputusan === 'tolak'
                      ? 'Tolak Masuk Master'
                      : 'Validasi & Tambahkan ke Master Jalan Poros & Desa'
                  }}
                </span>
              </button>
            </div>
          </div>

          <div
            v-else-if="selectedCandidate.jaringan_ruas"
            class="p-3 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/50 text-xs text-emerald-900 dark:text-emerald-200"
          >
            Sudah terintegrasi di Master Jalan Poros &amp; Desa sebagai
            <strong>{{ selectedCandidate.jaringan_ruas.kode_ruas }} · {{ selectedCandidate.jaringan_ruas.nama_ruas }}</strong>
            ({{ getHierarkiLabel(selectedCandidate.jaringan_ruas.hierarki_jalan) }}).
          </div>
        </div>

        <!-- Master Ruas Curation Form -->
        <div
          v-else-if="selectedMaster"
          class="rounded-xl border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                Detail Ruas Master: {{ selectedMaster.properties.nama_ruas }}
              </h3>
              <p class="text-xs text-slate-600 dark:text-slate-300 tabular-nums">
                Kode: {{ selectedMaster.properties.kode_ruas || '-' }} · Panjang {{ formatNumber(selectedMaster.properties.panjang_m, 1) }} m
              </p>
            </div>
          </div>

          <div v-if="isBappedaOrAdmin" class="space-y-2.5 pt-2 border-t border-slate-200 dark:border-slate-800">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Nama Ruas
                </label>
                <input
                  v-model="masterEditForm.nama_ruas"
                  type="text"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Hierarki Jalan
                </label>
                <select
                  v-model="masterEditForm.hierarki_jalan"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-2.5 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                >
                  <option value="lingkungan">Jalan Lingkungan</option>
                  <option value="poros_desa">Jalan Poros Desa</option>
                  <option value="usaha_tani">Jalan Usaha Tani</option>
                  <option value="kabupaten">Jalan Kabupaten</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Kode Ruas (Angka)
                </label>
                <input
                  v-model="masterEditForm.kode_ruas"
                  type="number"
                  min="1"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Perkerasan
                </label>
                <input
                  v-model="masterEditForm.perkerasan"
                  type="text"
                  placeholder="Beton / Aspal / Paving"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                  Kondisi
                </label>
                <input
                  v-model="masterEditForm.kondisi"
                  type="text"
                  placeholder="Baik / Rusak Ringan / Berat"
                  class="w-full min-h-[44px] sm:min-h-[36px] px-3 py-1.5 rounded-lg border border-slate-400 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-slate-100"
                />
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
              <button
                type="button"
                :disabled="deletingMasterId === selectedMaster.properties.id"
                class="px-3.5 min-h-[44px] sm:min-h-[36px] rounded-lg border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-xs font-semibold cursor-pointer disabled:opacity-50"
                @click="archiveMasterRuas(selectedMaster)"
              >
                Arsipkan Ruas
              </button>
              <button
                type="button"
                :disabled="savingMasterId === selectedMaster.properties.id || !masterEditForm.nama_ruas.trim()"
                class="px-4 min-h-[44px] sm:min-h-[36px] rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold cursor-pointer disabled:opacity-50"
                @click="saveMasterRuasChanges"
              >
                Simpan Perubahan Ruas
              </button>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

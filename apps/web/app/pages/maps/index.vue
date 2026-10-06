<script setup lang="ts">
import MapCanvas, { type BasemapKey } from "~/components/maps/MapCanvas.vue";
import LeftPanel from "~/components/maps/LeftPanel.vue";
import BottomLocationBar from "~/components/maps/BottomLocationBar.vue";
import KatalogDialog from "~/components/maps/KatalogDialog.vue";
import MapMobileBottomSheet, { type SnapLevel } from "~/components/maps/MapMobileBottomSheet.vue";
import MapShareModal from "~/components/maps/MapShareModal.vue";
import type { ActiveLayerItem } from "~/types/map-layers";

definePageMeta({
  layout: false,
  fullBleed: true,
});

useSeoMeta({
  title: "Peta Interaktif Publik | Melarosa GIS",
  description: "Peta spasial interaktif publik Kabupaten Bojonegoro dengan pencarian lokasi, eksplorasi basemap, dan katalog layer tematik.",
});

useHead({
  htmlAttrs: {
    class: "overflow-hidden h-full",
  },
  bodyAttrs: {
    class: "overflow-hidden h-full",
  },
});

const route = useRoute();
const router = useRouter();
const colorMode = useColorMode();
const mapCanvasRef = ref<InstanceType<typeof MapCanvas> | null>(null);

interface GoogleMapsView {
  center: [number, number]; // [lng, lat]
  zoom: number;
}

// Parser format Google Maps: /maps/@-7.1552492,111.9071763,14z atau 4981m
function parseGoogleMapsPath(rawPath: string): GoogleMapsView | null {
  if (!rawPath) return null;
  const match = rawPath.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)(?:,(\d+(?:\.\d+)?)(m|z)?)?/);
  if (!match) return null;

  const lat = Number(match[1]);
  const lng = Number(match[2]);
  if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
    return null;
  }

  let zoom = 11;
  if (match[3]) {
    const val = Number(match[3]);
    const unit = match[4] || "z";

    if (unit === "m") {
      // Konversi ketinggian meter (altitude) ke zoom level
      const screenHeight = typeof window !== "undefined" ? window.innerHeight || 800 : 800;
      const latRad = (lat * Math.PI) / 180;
      const calculatedZoom = Math.log2((156543.03392 * Math.cos(latRad) * screenHeight) / Math.max(val, 10));
      zoom = Math.min(Math.max(Math.round(calculatedZoom * 10) / 10, 4), 19);
    } else {
      zoom = Math.min(Math.max(Math.round(val * 100) / 100, 4), 19);
    }
  }

  return {
    center: [lng, lat],
    zoom,
  };
}

function parseBboxQuery(raw: any): [number, number, number, number] | null {
  if (!raw || typeof raw !== "string") return null;
  const parts = raw.split(",").map((v) => Number(v.trim()));
  if (parts.length === 4 && parts.every((n) => !isNaN(n))) {
    const [minLng, minLat, maxLng, maxLat] = parts;
    if (minLng < maxLng && minLat < maxLat) {
      return [minLng, minLat, maxLng, maxLat];
    }
  }
  return null;
}

function parseZoomQuery(raw: any): number | null {
  if (!raw) return null;
  const z = Number(raw);
  if (!isNaN(z) && z >= 4 && z <= 22) {
    return z;
  }
  return null;
}

// Inisialisasi awal koordinat dari path URL gaya Google Maps atau query bbox
const pathView = parseGoogleMapsPath(route.fullPath || route.path);
const initialCenter = pathView ? pathView.center : null;
const initialZoom = pathView ? pathView.zoom : parseZoomQuery(route.query.zoom || route.query.z);
const initialBbox = pathView ? null : parseBboxQuery(route.query.bbox);

// Label lokasi pencarian dari parameter URL ?search=... atau ?name=...
function extractSearchQuery(queryObj: any): string | null {
  if (!queryObj) return null;
  if (typeof queryObj.search === "string" && queryObj.search.trim()) return queryObj.search.trim();
  if (typeof queryObj.name === "string" && queryObj.name.trim()) return queryObj.name.trim();
  if (typeof queryObj.q === "string" && queryObj.q.trim()) return queryObj.q.trim();
  return null;
}

const searchedLocationLabel = ref<string | null>(extractSearchQuery(route.query));

// Inisialisasi basemap dari parameter URL atau localStorage yang persisten
const validBasemaps: BasemapKey[] = ["osm", "satellite", "positron", "dark", "topo"];

function getInitialBasemap(): BasemapKey {
  const queryParam = typeof route.query.basemap === "string" ? route.query.basemap : null;
  if (queryParam && validBasemaps.includes(queryParam as BasemapKey)) {
    return queryParam as BasemapKey;
  }
  if (import.meta.client) {
    try {
      const saved = localStorage.getItem("melarosa_active_basemap");
      if (saved && validBasemaps.includes(saved as BasemapKey)) {
        return saved as BasemapKey;
      }
    } catch {}
  }
  return "osm";
}

const currentBasemap = useState<BasemapKey>("melarosa_active_basemap", () => getInitialBasemap());

// ─── API Integration (Layers Stored in State, Hidden from LeftPanel) ───────────
const { data: layersData } = useHttp<any>("layers", {
  key: "melarosa-map-layers-catalog",
  lazy: true,
  dedupe: "defer",
  getCachedData: (key, nuxtApp) => {
    return nuxtApp.payload.data[key] || nuxtApp.static.data[key];
  },
});

// ─── Map & Panels Floating States ─────────────────────────────────────────────
// Hanya aktifkan pin / popup marker jika halaman dibuka dengan parameter pencarian eksplisit (?search, ?q, ?pin)
const hasExplicitSearchOrPin = Boolean(extractSearchQuery(route.query) || route.query.pin);
const clickedCoordinate = ref<[number, number] | null>(
  hasExplicitSearchOrPin && pathView ? pathView.center : null
);

// Floating panels toggles
const leftPanelOpen = ref(false);
const locationBarOpen = ref(hasExplicitSearchOrPin);
const isKatalogDialogOpen = ref(false);
const isShareModalOpen = ref(false);

async function getMapSnapshot(): Promise<string | null> {
  if (!mapCanvasRef.value) return null;
  return mapCanvasRef.value.getMapSnapshot();
}

// WMS Feature identification state
import type { IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";
const identifiedFeatures = ref<IdentifiedFeature[]>([]);
const isIdentifyingFeatures = ref(false);

// ─── Active GIS Layers State (Dikelola dari Katalog Data & LeftPanel) ─────────
const activeLayers = ref<ActiveLayerItem[]>([
  {
    id: "batas-desa",
    title: "Batas Wilayah Administrasi Desa & Kelurahan",
    name: "Batas Wilayah Administrasi Desa & Kelurahan",
    layer_name: "palapa:ADMINISTRASIDESA_LN_10K_2019_BOJONEGORO",
    protocol: "WMS",
    url: "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms",
    attribution: "Dinas Pemberdayaan Masyarakat dan Desa (DPMD)",
    description: "Batas administrasi resmi desa dan kelurahan Kabupaten Bojonegoro.",
    source_type: "geoserver",
    wmsUrl: "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms",
    wmsLayerName: "palapa:ADMINISTRASIDESA_LN_10K_2019_BOJONEGORO",
    visible: true,
    opacity: 1,
    expanded: true,
    activeTool: "none",
  },
  {
    id: "jalan-poros-desa",
    title: "Jaringan Jalan Poros Desa Bojonegoro",
    name: "Jaringan Jalan Poros Desa Bojonegoro",
    layer_name: "palapa:JALAN_LN_5K_2021_POROSDESA_BOJONEGORO",
    protocol: "WMS",
    url: "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms",
    attribution: "Dinas PU Bina Marga dan Penataan Ruang",
    description: "Trase ruas jalan poros penghubung antar-desa Kabupaten Bojonegoro.",
    source_type: "geoserver",
    wmsUrl: "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms",
    wmsLayerName: "palapa:JALAN_LN_5K_2021_POROSDESA_BOJONEGORO",
    visible: true,
    opacity: 0.9,
    expanded: false,
    activeTool: "none",
  },
]);

const activeLayerIds = computed(() => activeLayers.value.map((l) => l.id));

// Basemap options definition
interface BasemapOption {
  key: BasemapKey;
  name: string;
  tagline: string;
}

const BASEMAP_OPTIONS: BasemapOption[] = [
  { key: "osm", name: "Standar", tagline: "Peta Jalan OSM" },
  { key: "satellite", name: "Satelit", tagline: "Citra Resolusi Tinggi" },
  { key: "positron", name: "Terang", tagline: "Peta Bersih Minimalis" },
  { key: "dark", name: "Gelap", tagline: "Kontras Malam Hari" },
  { key: "topo", name: "Medan", tagline: "Kontur & Topografi" },
];

// Mobile drawers
type MobileDrawerTab = "layer" | "kecamatan" | "inspector";
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<MobileDrawerTab>("layer");
const mobileSheetSnap = ref<SnapLevel>("min");

const isLeftPanelActive = computed(() => {
  return leftPanelOpen.value || (mobileDrawerOpen.value && mobileDrawerTab.value !== "inspector");
});

let urlUpdateTimer: ReturnType<typeof setTimeout> | null = null;

// Perbarui URL dengan format Google Maps /maps/@lat,lng,zoomz secara hening
function handleViewportUpdate(viewport: { bbox: [number, number, number, number]; zoom: number; center: [number, number] }) {
  if (urlUpdateTimer) clearTimeout(urlUpdateTimer);
  urlUpdateTimer = setTimeout(() => {
    if (!import.meta.client) return;

    const lat = viewport.center[1].toFixed(7);
    const lng = viewport.center[0].toFixed(7);
    const z = Number.isInteger(viewport.zoom) ? String(viewport.zoom) : viewport.zoom.toFixed(2);
    const targetPath = `/maps/@${lat},${lng},${z}z`;

    const url = new URL(window.location.href);
    const targetBasemap = currentBasemap.value !== "osm" ? currentBasemap.value : null;

    if (url.pathname === targetPath && url.searchParams.get("basemap") === targetBasemap) {
      return;
    }

    url.pathname = targetPath;
    url.searchParams.delete("bbox");
    url.searchParams.delete("zoom");
    url.searchParams.delete("z");

    if (targetBasemap) {
      url.searchParams.set("basemap", targetBasemap);
    } else {
      url.searchParams.delete("basemap");
    }

    window.history.replaceState(window.history.state, "", url.toString());
  }, 180);
}

// Sinkronkan perubahan basemap ke URL parameter & localStorage
watch(
  currentBasemap,
  (newBasemap) => {
    if (!import.meta.client) return;
    try {
      localStorage.setItem("melarosa_active_basemap", newBasemap);
    } catch {}

    const url = new URL(window.location.href);
    if (newBasemap !== "osm") {
      url.searchParams.set("basemap", newBasemap);
    } else {
      url.searchParams.delete("basemap");
    }
    window.history.replaceState(window.history.state, "", url.toString());
  },
  { immediate: true }
);

// Tangani navigasi riwayat browser (tombol Back / Forward)
function handlePopState() {
  if (!import.meta.client) return;
  const currentPathView = parseGoogleMapsPath(window.location.pathname);
  const params = new URLSearchParams(window.location.search);
  const basemapParam = params.get("basemap");

  if (basemapParam && validBasemaps.includes(basemapParam as BasemapKey)) {
    currentBasemap.value = basemapParam as BasemapKey;
  }

  if (currentPathView) {
    const searchLabel = params.get("search") || params.get("name") || params.get("q") || null;
    searchedLocationLabel.value = searchLabel;
    if (searchLabel || params.get("pin")) {
      clickedCoordinate.value = currentPathView.center;
      locationBarOpen.value = true;
    } else {
      clickedCoordinate.value = null;
      locationBarOpen.value = false;
    }
    if (mapCanvasRef.value) {
      mapCanvasRef.value.setCenterAndZoom(currentPathView.center, currentPathView.zoom, true);
    }
  } else {
    const parsedBbox = parseBboxQuery(params.get("bbox"));
    const parsedZoom = parseZoomQuery(params.get("zoom"));
    if (parsedBbox && mapCanvasRef.value) {
      mapCanvasRef.value.fitBbox(parsedBbox, parsedZoom ?? undefined, 250);
    }
  }
}

// Pantau perubahan URL secara dinamis (termasuk navigasi pencarian dari PublicNavbar)
watch(
  () => route.fullPath,
  (newFullPath) => {
    if (!import.meta.client) return;
    const pv = parseGoogleMapsPath(newFullPath);
    const searchLabel = extractSearchQuery(route.query);
    if (pv && (searchLabel || route.query.pin)) {
      clickedCoordinate.value = pv.center;
      searchedLocationLabel.value = searchLabel;
      mapCanvasRef.value?.flyTo(pv.center, pv.zoom || 14);
      locationBarOpen.value = true;
      mobileDrawerOpen.value = false;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const basemapParam = urlParams.get("basemap") || (route.query.basemap as string);
    if (basemapParam && validBasemaps.includes(basemapParam as BasemapKey)) {
      currentBasemap.value = basemapParam as BasemapKey;
    }
  }
);

// Sinkronisasi penutupan bottomsheet di mobile saat tab inspector aktif agar popup marker di peta ikut tertutup
watch(mobileDrawerOpen, (isOpen) => {
  if (!isOpen && mobileDrawerTab.value === "inspector" && (clickedCoordinate.value || locationBarOpen.value)) {
    handleCloseInspector();
  }
});

onMounted(() => {
  window.addEventListener("popstate", handlePopState);
  try {
    const saved = localStorage.getItem("melarosa_active_basemap");
    if (saved && validBasemaps.includes(saved as BasemapKey) && !route.query.basemap) {
      currentBasemap.value = saved as BasemapKey;
    }
  } catch {}
});

onUnmounted(() => {
  if (urlUpdateTimer) clearTimeout(urlUpdateTimer);
  window.removeEventListener("popstate", handlePopState);
});

function handleMapClick(coordinate: [number, number]) {
  clickedCoordinate.value = coordinate;
  searchedLocationLabel.value = null;
  // Reset fitur sebelumnya agar skeleton loading & transisi animasi data fitur baru terlihat jelas
  identifiedFeatures.value = [];
  locationBarOpen.value = true;
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    mobileDrawerTab.value = "inspector";
    mobileSheetSnap.value = "min";
    mobileDrawerOpen.value = true;
  }
}

function handleSelectLocation(payload: { name: string; coordinate: [number, number]; zoom?: number }) {
  clickedCoordinate.value = payload.coordinate;
  searchedLocationLabel.value = payload.name;
  mapCanvasRef.value?.flyTo(payload.coordinate, payload.zoom ?? 14);
  locationBarOpen.value = true;
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    mobileDrawerTab.value = "inspector";
    mobileSheetSnap.value = "min";
    mobileDrawerOpen.value = true;
  } else {
    mobileDrawerOpen.value = false;
  }

  // Sinkronkan query parameter ?search= ke URL browser dan pastikan basemap aktif tetap terjaga
  if (import.meta.client) {
    const url = new URL(window.location.href);
    url.searchParams.set("search", payload.name);
    if (currentBasemap.value && currentBasemap.value !== "osm") {
      url.searchParams.set("basemap", currentBasemap.value);
    }
    window.history.replaceState(window.history.state, "", url.toString());
  }
}

function extractNamobj(props: Record<string, any> | undefined): string | null {
  if (!props) return null;
  // 1. Cek langsung kunci NAMOBJ (case-insensitive)
  for (const [k, v] of Object.entries(props)) {
    if (k.toUpperCase() === "NAMOBJ" && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }
  // 2. Cek properti nama spesifik spasial lainnya
  const prioritizedKeys = ["NAMA_RUAS", "WADMKD", "WADMKC", "WADMKK", "REMARK"];
  for (const pKey of prioritizedKeys) {
    for (const [k, v] of Object.entries(props)) {
      if (k.toUpperCase() === pKey && v) {
        const s = String(v).trim();
        if (s && s !== "-") return s;
      }
    }
  }
  // 3. Fallback ke properti yang mengandung NAM atau TITLE
  for (const [k, v] of Object.entries(props)) {
    const upper = k.toUpperCase();
    if ((upper.includes("NAM") || upper.includes("TITLE")) && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }
  return null;
}

function handleIdentifyFeatures(features: IdentifiedFeature[]) {
  identifiedFeatures.value = features;
  if (features && features.length > 0) {
    if (features[0].coordinate) {
      clickedCoordinate.value = features[0].coordinate;
    }
    const namobj = extractNamobj(features[0].properties);
    if (namobj) {
      searchedLocationLabel.value = namobj;
    }
    locationBarOpen.value = true;
  }
}

function handleSelectFeature(feature: IdentifiedFeature) {
  const namobj = extractNamobj(feature?.properties);
  if (namobj) {
    searchedLocationLabel.value = namobj;
  }
}

let isClosingInspector = false;

function handleCloseInspector() {
  if (isClosingInspector) return;
  isClosingInspector = true;
  try {
    locationBarOpen.value = false;
    mapCanvasRef.value?.dismissPulse?.();
    if (mobileDrawerOpen.value && mobileDrawerTab.value === "inspector") {
      mobileDrawerOpen.value = false;
    }
    // Hapus query search dari URL saat inspector ditutup, tetap pertahankan basemap
    if (import.meta.client) {
      const url = new URL(window.location.href);
      url.searchParams.delete("search");
      url.searchParams.delete("name");
      if (currentBasemap.value && currentBasemap.value !== "osm") {
        url.searchParams.set("basemap", currentBasemap.value);
      }
      window.history.replaceState(window.history.state, "", url.toString());

      // Di mobile di mana BottomLocationBar (desktop) tidak ter-render / hidden,
      // bersihkan koordinat setelah jeda 320ms (agar animasi slide-down bottomsheet 280ms selesai sempurna tanpa flicker)
      if (window.innerWidth < 768) {
        setTimeout(() => {
          if (!locationBarOpen.value && !mobileDrawerOpen.value) {
            clickedCoordinate.value = null;
            searchedLocationLabel.value = null;
            identifiedFeatures.value = [];
          }
        }, 320);
      }
    }
  } finally {
    isClosingInspector = false;
  }
}

function handleInspectorAfterLeave() {
  if (!locationBarOpen.value) {
    clickedCoordinate.value = null;
    searchedLocationLabel.value = null;
    identifiedFeatures.value = [];
  }
}

function openMobileTab(tab: MobileDrawerTab) {
  mobileDrawerTab.value = tab;
  mobileSheetSnap.value = tab === "inspector" ? "min" : "peek";
  mobileDrawerOpen.value = true;
}

function toggleLeftPanel() {
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    if (mobileDrawerOpen.value && (mobileDrawerTab.value === "layer" || mobileDrawerTab.value === "kecamatan")) {
      mobileDrawerOpen.value = false;
    } else {
      openMobileTab("layer");
    }
  } else {
    leftPanelOpen.value = !leftPanelOpen.value;
  }
}

function handleZoomToLayer(layer: ActiveLayerItem) {
  if (layer.coordinates) {
    clickedCoordinate.value = layer.coordinates;
    searchedLocationLabel.value = layer.title;
    mapCanvasRef.value?.flyTo(layer.coordinates, layer.zoom || 12);
    locationBarOpen.value = true;
  }
}

function handleToggleLayerVisibility(layerId: string) {
  const target = activeLayers.value.find((l) => l.id === layerId);
  if (target) {
    target.visible = !target.visible;
  }
}

function handleUpdateLayerOpacity(payload: { layerId: string; opacity: number }) {
  const target = activeLayers.value.find((l) => l.id === payload.layerId);
  if (target) {
    target.opacity = payload.opacity;
  }
}

function handleUpdateLayerFilter(payload: { layerId: string; kecamatan: string | null; status: string | null }) {
  const target = activeLayers.value.find((l) => l.id === payload.layerId);
  if (target) {
    target.filterKecamatan = payload.kecamatan;
    target.filterStatus = payload.status;
  }
}

function handleRemoveLayer(layerId: string) {
  activeLayers.value = activeLayers.value.filter((l) => l.id !== layerId);
}

function handleSelectDataset(dataset: any) {
  const existing = activeLayers.value.find((l) => l.id === dataset.id);
  if (existing) {
    existing.visible = true;
    existing.expanded = true;
  } else {
    activeLayers.value.push({
      id: dataset.id,
      title: dataset.name || dataset.title,
      name: dataset.name || dataset.title,
      layer_name: dataset.layer_name || dataset.wmsLayerName,
      protocol: dataset.protocol,
      url: dataset.url || dataset.wmsUrl,
      attribution: dataset.attribution || dataset.agency,
      description: dataset.description,
      source_type: dataset.source_type,
      created_at: dataset.created_at,
      updated_at: dataset.updated_at,
      wmsUrl: dataset.wmsUrl || dataset.url,
      wmsLayerName: dataset.wmsLayerName || dataset.layer_name,
      visible: true,
      opacity: typeof dataset.opacity === "number" ? dataset.opacity : 1,
      expanded: true,
      activeTool: "none",
    });
  }

  leftPanelOpen.value = true;
  isKatalogDialogOpen.value = false;
}
</script>

<template>
  <div class="relative w-full h-full overflow-hidden bg-gray-100 dark:bg-[#070b14] select-none font-sans antialiased text-gray-900 dark:text-gray-100">
    <!-- ═══ 1. BACKGROUND FULL-BLEED INTERACTIVE MAP ═════════════════════════════ -->
    <ClientOnly>
      <MapCanvas
        ref="mapCanvasRef"
        :current-basemap="currentBasemap"
        :clicked-coordinate="clickedCoordinate"
        :location-label="searchedLocationLabel"
        :initial-center="initialCenter"
        :initial-bbox="initialBbox"
        :initial-zoom="initialZoom"
        :active-layers="activeLayers"
        :mobile-drawer-open="mobileDrawerOpen"
        :mobile-sheet-snap="mobileSheetSnap"
        @map-click="handleMapClick"
        @identify-features="handleIdentifyFeatures"
        @identifying-features="isIdentifyingFeatures = $event"
        @update:viewport="handleViewportUpdate"
        @dismiss-popup="handleCloseInspector"
        @share="isShareModalOpen = true"
      />
      <template #fallback>
        <div class="w-full h-full flex flex-col items-center justify-center bg-gray-100 dark:bg-[#070b14] text-gray-400 gap-3">
          <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-blue-500" />
          <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Memuat Peta Spasial...</p>
          <p class="text-xs text-gray-400 dark:text-gray-500">Membutuhkan koneksi internet dan WebGL</p>
        </div>
      </template>
    </ClientOnly>

    <!-- ═══ 2. FLOATING LEFT PANEL (Desktop - Proportional above bottom-left trigger) ═══ -->
    <Transition name="panel-pop">
      <div
        v-if="leftPanelOpen"
        class="hidden md:block absolute bottom-[72px] sm:bottom-[80px] left-3 sm:left-4 lg:left-5 z-20 pointer-events-auto"
      >
        <LeftPanel
          v-model:current-basemap="currentBasemap"
          :active-layers="activeLayers"
          @close="leftPanelOpen = false"
          @zoom-to-home="mapCanvasRef?.resetView()"
          @select-kecamatan="handleSelectLocation"
          @open-katalog-dialog="isKatalogDialogOpen = true"
          @zoom-to-layer="handleZoomToLayer"
          @toggle-layer-visibility="handleToggleLayerVisibility"
          @update-layer-opacity="handleUpdateLayerOpacity"
          @update-layer-filter="handleUpdateLayerFilter"
          @remove-layer="handleRemoveLayer"
        />
      </div>
    </Transition>

    <!-- ═══ 3. FLOATING CONTROL (BOTTOM LEFT): TOMBOL TUNGGAL LAPISAN PETA ═══ -->
    <div
      class="absolute left-3 sm:left-4 lg:left-5 z-20 pointer-events-auto select-none transition-all duration-300 ease-out"
      :class="[
        mobileDrawerOpen && (mobileSheetSnap === 'peek' || mobileSheetSnap === 'full')
          ? 'opacity-0 pointer-events-none translate-y-4'
          : mobileDrawerOpen && mobileSheetSnap === 'min'
            ? 'bottom-[calc(86px+env(safe-area-inset-bottom,0px))] md:bottom-4 sm:md:bottom-5 opacity-100'
            : 'bottom-4 sm:bottom-5 opacity-100'
      ]"
    >
      <UTooltip text="Lapisan Peta (Peta Dasar & Layer Tematik)">
        <button
          type="button"
          class="group relative flex items-center gap-2.5 h-12 px-3 rounded-2xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-slate-200/90 dark:border-white/10 shadow-lg hover:shadow-xl transition-all duration-200 cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-95"
          :class="isLeftPanelActive
            ? 'ring-2 ring-blue-500/80 border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 shadow-blue-500/10'
            : 'hover:border-slate-300 dark:hover:border-white/20'"
          :aria-expanded="isLeftPanelActive"
          aria-label="Buka Lapisan Peta dan Peta Dasar"
          @click="toggleLeftPanel"
        >
          <!-- Icon Badge (Layer Icon) -->
          <div
            class="size-7.5 rounded-xl flex items-center justify-center transition-colors shadow-xs"
            :class="isLeftPanelActive
              ? 'bg-blue-600 text-white'
              : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40'"
          >
            <UIcon name="i-lucide-layers" class="size-4 stroke-[2.25]" />
          </div>

          <!-- Label Text & Active Status Indicator -->
          <div class="flex flex-col text-left pr-0.5">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 tracking-tight leading-tight">
              Lapisan Peta
            </span>
            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium leading-tight capitalize">
              {{ currentBasemap }} • {{ activeLayers.length }} Layer
            </span>
          </div>

          <!-- Chevron Indicator -->
          <UIcon
            name="i-lucide-chevron-up"
            class="size-3.5 text-slate-400 dark:text-slate-500 transition-transform duration-200"
            :class="isLeftPanelActive ? 'rotate-180 text-blue-600 dark:text-blue-400' : ''"
          />
        </button>
      </UTooltip>
    </div>

    <!-- ═══ 4. FLOATING BOTTOM-CENTER LOCATION BAR (Desktop) ═══════════════════ -->
    <div
      class="hidden md:block absolute bottom-4 sm:bottom-5 left-1/2 -translate-x-1/2 z-20 pointer-events-none"
    >
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-12 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-12 scale-95"
        @after-leave="handleInspectorAfterLeave"
      >
        <div
          v-if="locationBarOpen && (clickedCoordinate || identifiedFeatures.length > 0)"
          class="pointer-events-auto"
        >
          <BottomLocationBar
            :clicked-coordinate="clickedCoordinate"
            :location-label="searchedLocationLabel"
            :identified-features="identifiedFeatures"
            :is-identifying="isIdentifyingFeatures"
            @close="handleCloseInspector"
            @select-feature="handleSelectFeature"
            @share="isShareModalOpen = true"
          />
        </div>
      </Transition>
    </div>

    <!-- ═══ 5. MOBILE BOTTOM SHEET DRAWER ═════════════════════════════════════ -->
    <MapMobileBottomSheet
      v-model:open="mobileDrawerOpen"
      v-model:snap="mobileSheetSnap"
      :title="
        mobileDrawerTab === 'layer'
          ? 'Panel Lapisan Peta'
          : mobileDrawerTab === 'kecamatan'
            ? 'Wilayah Bojonegoro'
            : (searchedLocationLabel || 'Informasi Lokasi & Titik')
      "
      :subtitle="
        mobileDrawerTab === 'layer'
          ? `${activeLayers.length} Layer Aktif · Bojonegoro`
          : mobileDrawerTab === 'kecamatan'
            ? '28 Wilayah Kecamatan Terdata'
            : (searchedLocationLabel && clickedCoordinate
                ? `${clickedCoordinate[1].toFixed(5)}, ${clickedCoordinate[0].toFixed(5)}`
                : (clickedCoordinate
                    ? `${clickedCoordinate[1].toFixed(5)}, ${clickedCoordinate[0].toFixed(5)}`
                    : 'Ketuk area peta untuk info detail'))
      "
      :icon="
        mobileDrawerTab === 'layer'
          ? 'i-lucide-layers'
          : mobileDrawerTab === 'kecamatan'
            ? 'i-lucide-compass'
            : 'i-lucide-map-pin'
      "
    >
      <!-- Action Header Button: Bagikan Peta -->
      <template #header-actions>
        <button
          type="button"
          class="size-8 rounded-lg border border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0b0f19] text-slate-700 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-50 dark:hover:bg-white/[0.04] flex items-center justify-center cursor-pointer transition-colors active:scale-95 mr-1"
          aria-label="Bagikan Peta & Postingan Media Sosial"
          title="Bagikan Peta"
          @click="isShareModalOpen = true"
        >
          <UIcon name="i-lucide-share-2" class="size-4" />
        </button>
      </template>

      <!-- Quick Tab Switcher inside Sheet (3 tabs: Lapisan Peta, Wilayah, Lokasi) -->
      <template #tabs>
        <div class="px-3 pt-1 pb-2 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30">
          <div class="grid grid-cols-3 gap-1.5 p-1 bg-gray-100 dark:bg-gray-800/70 rounded-xl">
            <!-- Tab 1: Layer -->
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[38px]"
              :class="mobileDrawerTab === 'layer'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'layer'"
            >
              <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
              <span>Layer</span>
              <span
                v-if="activeLayers.length > 0"
                class="px-1.5 py-0.2 rounded-full text-[9px] font-mono leading-tight"
                :class="mobileDrawerTab === 'layer'
                  ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300'
                  : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
              >
                {{ activeLayers.length }}
              </span>
            </button>

            <!-- Tab 2: Wilayah -->
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-all cursor-pointer min-h-[38px]"
              :class="mobileDrawerTab === 'kecamatan'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'kecamatan'"
            >
              <UIcon name="i-lucide-compass" class="size-3.5 shrink-0" />
              <span>Wilayah</span>
              <span
                class="px-1.5 py-0.2 rounded-full text-[9px] font-mono leading-tight"
                :class="mobileDrawerTab === 'kecamatan'
                  ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300'
                  : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
              >
                28
              </span>
            </button>

            <!-- Tab 3: Detail / Lokasi -->
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-medium transition-all relative cursor-pointer min-h-[38px]"
              :class="mobileDrawerTab === 'inspector'
                ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'inspector'"
            >
              <UIcon name="i-lucide-info" class="size-3.5 shrink-0" />
              <span>Lokasi</span>
              <span
                v-if="clickedCoordinate"
                class="size-1.5 rounded-full bg-blue-500 shrink-0"
              />
            </button>
          </div>
        </div>
      </template>

      <!-- Content Body inside Sheet (Persistent with v-show to avoid unmounting & refetching) -->
      <div class="h-full flex flex-col min-h-0">
        <!-- 1 & 2. Layer & Wilayah (Kecamatan) Tabs (Unified single persistent LeftPanel instance) -->
        <div
          v-show="mobileDrawerTab === 'layer' || mobileDrawerTab === 'kecamatan'"
          class="h-full flex flex-col min-h-0"
        >
          <LeftPanel
            v-model:current-basemap="currentBasemap"
            :is-mobile-drawer="true"
            :active-layers="activeLayers"
            :active-tab="mobileDrawerTab === 'kecamatan' ? 'kecamatan' : 'layers'"
            @close="mobileDrawerOpen = false"
            @zoom-to-home="mapCanvasRef?.resetView(); mobileDrawerOpen = false"
            @select-kecamatan="handleSelectLocation"
            @open-katalog-dialog="isKatalogDialogOpen = true; mobileDrawerOpen = false"
            @zoom-to-layer="handleZoomToLayer"
            @toggle-layer-visibility="handleToggleLayerVisibility"
            @update-layer-opacity="handleUpdateLayerOpacity"
            @update-layer-filter="handleUpdateLayerFilter"
            @remove-layer="handleRemoveLayer"
          />
        </div>

        <!-- 3. Lokasi / Detail Tab (Persistent BottomLocationBar) -->
        <div
          v-show="mobileDrawerTab === 'inspector'"
          class="h-full flex flex-col min-h-0"
        >
          <BottomLocationBar
            :is-mobile-drawer="true"
            :clicked-coordinate="clickedCoordinate"
            :location-label="searchedLocationLabel"
            :identified-features="identifiedFeatures"
            :is-identifying="isIdentifyingFeatures"
            @close="handleCloseInspector"
            @select-feature="handleSelectFeature"
            @share="isShareModalOpen = true"
          />
        </div>
      </div>
    </MapMobileBottomSheet>

    <!-- ═══ 9. DIALOG KATALOG DATA SPASIAL ═════════════════════════════════════ -->
    <KatalogDialog
      v-model:open="isKatalogDialogOpen"
      :layers-data="layersData"
      :active-layer-ids="activeLayerIds"
      @select-dataset="handleSelectDataset"
    />

    <!-- ═══ 10. DIALOG BAGIKAN PETA & POSTINGAN SOSIAL MEDIA ═══════════════════ -->
    <MapShareModal
      v-model:open="isShareModalOpen"
      :current-basemap="currentBasemap"
      :clicked-coordinate="clickedCoordinate"
      :location-label="searchedLocationLabel"
      :active-layers="activeLayers"
      :map-center="clickedCoordinate || initialCenter"
      :map-zoom="initialZoom"
      :get-map-snapshot="getMapSnapshot"
    />
  </div>
</template>

<style scoped>
/* Smooth popup transition for LeftPanel originating from toggle button */
.panel-pop-enter-active,
.panel-pop-leave-active {
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  transform-origin: left bottom;
}
.panel-pop-enter-from,
.panel-pop-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.96);
}

/* Smooth slide transitions for floating panels */
.slide-left-enter-active,
.slide-left-leave-active {
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-left-enter-from,
.slide-left-leave-to {
  opacity: 0;
  transform: translateX(-16px) scale(0.98);
}

.slide-right-enter-active,
.slide-right-leave-active {
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-right-enter-from,
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(16px) scale(0.98);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

</style>

<script setup lang="ts">
import MapCanvas, { type BasemapKey } from "~/components/maps/MapCanvas.vue";
import LeftPanel from "~/components/maps/LeftPanel.vue";
import { KECAMATAN_ENTRIES, type KecamatanEntry } from "~/constants/kecamatan";
import BasemapPanel from "~/components/maps/BasemapPanel.vue";
import MapBasemapBottomSheet from "~/components/maps/MapBasemapBottomSheet.vue";
import BottomLocationBar from "~/components/maps/BottomLocationBar.vue";
import MapMobileBottomSheet, { type SnapLevel } from "~/components/maps/MapMobileBottomSheet.vue";
import MapShareModal from "~/components/maps/MapShareModal.vue";
import MapShareBottomSheet from "~/components/maps/MapShareBottomSheet.vue";
import type { ActiveLayerItem, MainTab } from "~/types/map-layers";
import { useGeoparkSites, type GeoparkSite } from "~/composables/useGeoparkSites";
import type { MapThumbnailOptions } from "~/composables/useMapThumbnail";
import { parseCoordinateString } from "~/utils/gis-helpers";

definePageMeta({
  layout: false,
  fullBleed: true,
});

useHead({
  htmlAttrs: {
    class: "overflow-hidden h-full",
  },
  bodyAttrs: {
    class: "overflow-hidden h-full",
  },
  meta: [
    {
      name: "viewport",
      content: "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover",
    },
  ],
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

// Spatial BBOX Martin Tile Server (gabungan data jalan_porosdesa & infrastruktur_segmen se-Bojonegoro)
const MARTIN_SPATIAL_BBOX: [number, number, number, number] = [
  111.445099, -7.451684, 112.163338, -6.981707
];

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
const validBasemaps: BasemapKey[] = ["esri", "rbi", "osm", "satellite", "aerial", "none", "dark", "topo", "positron"];

function getInitialBasemap(): BasemapKey {
  const queryParam = typeof route.query.basemap === "string" ? route.query.basemap : null;
  if (queryParam) {
    if (queryParam === "positron") return "esri";
    if (queryParam === "dark") return "esri";
    if (queryParam === "topo") return "osm";
    if (validBasemaps.includes(queryParam as BasemapKey)) {
      return queryParam as BasemapKey;
    }
  }
  if (import.meta.client) {
    try {
      const saved = localStorage.getItem("melarosa_active_basemap");
      if (saved === "positron") return "esri";
      if (saved === "dark") return "esri";
      if (saved === "topo") return "osm";
      if (saved && validBasemaps.includes(saved as BasemapKey)) {
        return saved as BasemapKey;
      }
    } catch {}
  }
  return "esri";
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
// Di desktop, panel kiri terbuka secara default
const leftPanelOpen = ref(false);
const locationBarOpen = ref(hasExplicitSearchOrPin);
const selectedKecamatanFilter = ref<string | null>(null);
const isShareModalOpen = ref(false);
const isBasemapBottomSheetOpen = ref(false);

// ─── OpenGraph & Social Share Dynamic Metadata (16:9 Thumbnail Banner) ─────────
const ogImageUrl = computed(() => {
  const host = typeof window !== "undefined" ? window.location.origin : "https://melarosa.bojonegorokab.go.id";
  const center = clickedCoordinate.value || initialCenter || [111.8817, -7.1502];
  const z = initialZoom || 13.5;
  const wilayah = selectedKecamatanFilter.value || "";
  const basemap = currentBasemap.value || "esri";
  const search = searchedLocationLabel.value || "";

  const params = new URLSearchParams();
  params.set("lat", center[1].toFixed(5));
  params.set("lng", center[0].toFixed(5));
  params.set("z", String(z));
  if (wilayah) params.set("wilayah", wilayah);
  if (basemap) params.set("basemap", basemap);
  if (search) params.set("label", search);

  return `${host}/api/og/map?${params.toString()}`;
});

const ogPageTitle = computed(() => {
  if (searchedLocationLabel.value) return `Peta ${searchedLocationLabel.value} · WebGIS Bojonegoro`;
  if (selectedKecamatanFilter.value) return `Peta Kecamatan ${selectedKecamatanFilter.value} · WebGIS Bojonegoro`;
  return "Peta Spasial Interaktif Bojonegoro · Melarosa GIS";
});

const ogPageDescription = computed(() => {
  if (selectedKecamatanFilter.value) {
    return `Informasi geospasial resmi Kecamatan ${selectedKecamatanFilter.value}, Kabupaten Bojonegoro: batas wilayah, tata ruang, infrastruktur dan katalog layer tematik.`;
  }
  return "Peta spasial interaktif Kabupaten Bojonegoro dengan pencarian lokasi, batas wilayah, eksplorasi basemap, dan katalog layer tematik.";
});

useSeoMeta({
  title: () => ogPageTitle.value,
  description: () => ogPageDescription.value,
  ogTitle: () => ogPageTitle.value,
  ogDescription: () => ogPageDescription.value,
  ogImage: () => ogImageUrl.value,
  ogImageWidth: 1200,
  ogImageHeight: 675,
  ogType: "website",
  twitterCard: "summary_large_image",
  twitterTitle: () => ogPageTitle.value,
  twitterDescription: () => ogPageDescription.value,
  twitterImage: () => ogImageUrl.value,
});

// Shared state: lebar sidebar dikirim ke app.vue agar navbar bisa geser
const mapsSidebarWidth = useState<number>("maps_sidebar_width", () => 0);
const SIDEBAR_WIDTH = 400;

// Fitur On-Loading Progresif Kanvas Peta (Fast LeftPanel + Map Canvas Loader)
const isMapCanvasLoaded = ref(false);
const loadingProgress = ref(18);
const loadingStageText = ref("Menyiapkan kanvas peta spasial Bojonegoro...");
let progressTimer: ReturnType<typeof setInterval> | null = null;

function startLoadingSimulation() {
  loadingProgress.value = 22;
  loadingStageText.value = "Menghubungkan layanan geospasial Bojonegoro...";

  let tick = 0;
  progressTimer = setInterval(() => {
    tick++;
    if (tick === 1) {
      loadingProgress.value = 48;
      loadingStageText.value = "Mengunduh modul kartografi OpenLayers...";
    } else if (tick === 2) {
      loadingProgress.value = 76;
      loadingStageText.value = "Menyiapkan layer tematik & batas wilayah...";
    } else if (tick === 3) {
      loadingProgress.value = 92;
      loadingStageText.value = "Merender data spasial ke kanvas...";
    }
  }, 260);
}

// Shared state dengan PublicNavbar.vue: menandai bahwa peta telah selesai dimuat
const isMapsReady = useState<boolean>("melarosa_maps_ready", () => false);

function handleMapLoaded() {
  if (progressTimer) {
    clearInterval(progressTimer);
    progressTimer = null;
  }
  loadingProgress.value = 100;
  loadingStageText.value = "Peta spasial siap digunakan";
  setTimeout(() => {
    isMapCanvasLoaded.value = true;

    // Jeda nyaman setelah peta selesai memuat (650ms), lalu picu transisi simultan melebar & panel kiri
    setTimeout(() => {
      isMapsReady.value = true;
      isLeftPanelMounted.value = true;
    }, 650);
  }, 260);
}

// Mobile drawers & screen detection
type MobileDrawerTab = "layer" | "dataset" | "geopark" | "geoportal";
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<MobileDrawerTab>("layer");
const mobileSheetSnap = ref<SnapLevel>("min");
const isMobileScreen = ref(false);
const previousTabBeforeIdentify = ref<MainTab | null>(null);

function updateIsMobile() {
  if (typeof window !== "undefined") {
    const isMobile = window.innerWidth < 768;
    isMobileScreen.value = isMobile;
    if (!isMobile) {
      mobileDrawerOpen.value = false;
      // Buka panel kiri secara default di desktop
      if (!leftPanelOpen.value) {
        leftPanelOpen.value = true;
      }
    } else {
      // Di mobile, sidebar tidak pernah full-height
      mapsSidebarWidth.value = 0;
    }
    syncSidebarWidth();
  }
}

function syncSidebarWidth() {
  if (isMobileScreen.value || !leftPanelOpen.value) {
    mapsSidebarWidth.value = 0;
  } else {
    const w = typeof window !== "undefined" && window.innerWidth < 1024 ? 350 : SIDEBAR_WIDTH;
    mapsSidebarWidth.value = w;
  }
}

watch(leftPanelOpen, () => syncSidebarWidth());

const isLeftPanelActive = computed(() => {
  if (isMobileScreen.value) {
    return mobileDrawerOpen.value && mobileDrawerTab.value === "layer" && mobileSheetSnap.value !== "min";
  }
  return leftPanelOpen.value;
});

function handleToggleBasemapSheet() {
  if (isBasemapBottomSheetOpen.value) {
    isBasemapBottomSheetOpen.value = false;
    return;
  }
  if (isMobileScreen.value) {
    if (isShareModalOpen.value) {
      isShareModalOpen.value = false;
    }
    if (mobileDrawerOpen.value && mobileSheetSnap.value !== "min") {
      mobileSheetSnap.value = "min";
    }
  }
  isBasemapBottomSheetOpen.value = true;
}

function handleToggleLeftPanelOrMobileDrawer() {
  if (isMobileScreen.value) {
    if (isBasemapBottomSheetOpen.value) {
      isBasemapBottomSheetOpen.value = false;
    }
    if (!mobileDrawerOpen.value) {
      openMobileTab("layer");
    } else if (mobileSheetSnap.value === "min") {
      mobileDrawerTab.value = "layer";
      mobileSheetSnap.value = "peek";
    } else {
      mobileSheetSnap.value = "min";
    }
    return;
  }
  toggleLeftPanel();
}

async function getMapSnapshot(options?: { crop16_9?: boolean; width?: number; height?: number }): Promise<string | null> {
  if (!mapCanvasRef.value) return null;
  return mapCanvasRef.value.getMapSnapshot(options);
}

async function getMapThumbnail(options?: MapThumbnailOptions): Promise<string | null> {
  if (!mapCanvasRef.value) return null;
  return mapCanvasRef.value.getMapThumbnail(options);
}

const isCartoGridActive = ref(false);

function handleToggleCartoGrid() {
  if (mapCanvasRef.value?.toggleCartoGrid) {
    isCartoGridActive.value = Boolean(mapCanvasRef.value.toggleCartoGrid());
  } else {
    isCartoGridActive.value = !isCartoGridActive.value;
  }
}

function handleFitKabupaten() {
  handleFilterWilayah(null);
  if (isMobileScreen.value && mobileSheetSnap.value !== "min") {
    mobileSheetSnap.value = "min";
  }
  mapCanvasRef.value?.fitBbox(
    MARTIN_SPATIAL_BBOX,
    15,
    550,
    isMobileScreen.value ? "min" : undefined
  );
}

function handleOpenShare() {
  if (isMobileScreen.value) {
    // Tutup bottom sheet peta terlebih dahulu dengan transisi lembut
    mobileDrawerOpen.value = false;
    isBasemapBottomSheetOpen.value = false;
  }
  isShareModalOpen.value = true;
}

function handleShareSheetClosed() {
  if (isMobileScreen.value) {
    // Beri jeda halus (60ms) setelah sheet bagikan tertutup sempurna, lalu buka kembali panel peta ke separuh layar
    mobileSheetSnap.value = "peek";
    setTimeout(() => {
      mobileDrawerOpen.value = true;
    }, 60);
  }
}

watch(isShareModalOpen, (isOpen) => {
  if (isOpen && isMobileScreen.value) {
    mobileDrawerOpen.value = false;
    isBasemapBottomSheetOpen.value = false;
  }
});

watch([mobileDrawerOpen, mobileSheetSnap], ([open, snap]) => {
  if (open && snap !== "min" && isBasemapBottomSheetOpen.value) {
    isBasemapBottomSheetOpen.value = false;
  }
});

// WMS Feature identification state
import type { IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";
const identifiedFeatures = ref<IdentifiedFeature[]>([]);
const isIdentifyingFeatures = ref(false);

// ─── Active GIS Layers Helper & Mapping ──────────────────────────────────────────
function mapLayerItemToActive(item: any): ActiveLayerItem {
  const isMvt =
    (item.protocol || "").toUpperCase() === "MVT" ||
    item.source_type === "martin" ||
    (item.url && item.url.includes("/martin/")) ||
    (item.layer_name && ["infrastruktur_segmen", "jalan_porosdesa", "bataswilayah_kabupaten"].includes(item.layer_name));

  const isSegmen = item.layer_name === "infrastruktur_segmen" || item.id === "01a1589c-8c15-70b6-a4fe-35517f888b36";
  const isKabupaten =
    item.layer_name === "bataswilayah_kabupaten" ||
    item.id === "01a15e28-a042-71a0-b111-ed7d9256e5bf" ||
    (item.url && item.url.includes("bataswilayah_kabupaten"));

  const detectedMartinSource = item.martinSource || item.layer_name || (item.url ? item.url.match(/\/martin\/([^\/]+)/)?.[1] : undefined);

  return {
    id: String(item.id),
    title: item.name || item.title || item.layer_name,
    name: item.name || item.title || item.layer_name,
    layer_name: item.layer_name || item.wmsLayerName || (isKabupaten ? "bataswilayah_kabupaten" : null),
    protocol: isMvt ? "MVT" : (item.protocol || "WMS").toUpperCase().replace("OGC:", ""),
    url: item.url || item.wmsUrl || "",
    instansi: item.instansi || item.attribution || item.agency || (isSegmen || item.layer_name === "jalan_porosdesa" || isKabupaten ? "Bappeda" : "BIG"),
    attribution: item.attribution || item.instansi || item.agency || (isSegmen || item.layer_name === "jalan_porosdesa" || isKabupaten ? "Bappeda" : "BIG"),
    agency: item.agency || item.instansi || item.attribution || (isSegmen || item.layer_name === "jalan_porosdesa" || isKabupaten ? "Bappeda" : "BIG"),
    description: item.description || (isKabupaten ? "Batas administrasi resmi wilayah Kabupaten Bojonegoro sesuai standar pemetaan RBI/BIG." : null),
    source_type: isMvt ? "martin" : (item.source_type || "external"),
    visible: true,
    opacity: typeof item.opacity === "number" ? item.opacity : 1,
    order: typeof item.order === "number" ? item.order : (isSegmen ? 0 : isKabupaten ? 13 : 1),
    wmsUrl: isMvt ? undefined : (item.wmsUrl || item.url),
    wmsLayerName: isMvt ? undefined : (item.wmsLayerName || item.layer_name),
    mvtUrl: isMvt ? (item.mvtUrl || item.url) : undefined,
    martinSource: isMvt ? (detectedMartinSource || (isKabupaten ? "bataswilayah_kabupaten" : isSegmen ? "infrastruktur_segmen" : "jalan_porosdesa")) : undefined,
    expanded: false,
    activeTool: "none",
    disableIdentify: isKabupaten || Boolean(item.disableIdentify),
    geoportal_id: item.geoportal_id,
    category: item.category,
    opd_name: item.opd_name,
    legend_url: item.legend_url,
    csw_metadata: item.csw_metadata,
    last_synced_at: item.last_synced_at,
  };
}

// ─── Preset Fallback Default Layers ───────────────────────────────────────────
const FALLBACK_DEFAULT_LAYERS: ActiveLayerItem[] = [
  {
    id: "01a1589c-8c15-70b6-a4fe-35517f888b36",
    title: "Infrastruktur Segmen",
    name: "Infrastruktur Segmen",
    layer_name: "infrastruktur_segmen",
    protocol: "MVT",
    url: "/martin/infrastruktur_segmen/{z}/{x}/{y}",
    mvtUrl: "/martin/infrastruktur_segmen/{z}/{x}/{y}",
    martinSource: "infrastruktur_segmen",
    source_type: "martin",
    instansi: "Bappeda",
    attribution: "Bappeda",
    agency: "Bappeda",
    description: "Data spasial vektor MVT segmen jalan dan jembatan infrastruktur Kabupaten Bojonegoro.",
    visible: true,
    opacity: 1,
    expanded: false,
    activeTool: "none",
  },
  {
    id: "01a1589c-8bf1-7294-9cf7-41ffda7e71f6",
    title: "Jalan Poros Desa",
    name: "Jalan Poros Desa",
    layer_name: "jalan_porosdesa",
    protocol: "MVT",
    url: "/martin/jalan_porosdesa/{z}/{x}/{y}",
    mvtUrl: "/martin/jalan_porosdesa/{z}/{x}/{y}",
    martinSource: "jalan_porosdesa",
    source_type: "martin",
    instansi: "Bappeda",
    attribution: "Bappeda",
    agency: "Bappeda",
    description: "Data spasial vektor MVT trase ruas jalan poros penghubung antar-desa Kabupaten Bojonegoro.",
    visible: true,
    opacity: 1,
    expanded: false,
    activeTool: "none",
  },
  {
    id: "01a15e28-a042-71a0-b111-ed7d9256e5bf",
    title: "Batas Wilayah Kabupaten",
    name: "Batas Wilayah Kabupaten",
    layer_name: "bataswilayah_kabupaten",
    protocol: "MVT",
    url: "/martin/bataswilayah_kabupaten/{z}/{x}/{y}",
    mvtUrl: "/martin/bataswilayah_kabupaten/{z}/{x}/{y}",
    martinSource: "bataswilayah_kabupaten",
    source_type: "martin",
    instansi: "Bappeda",
    attribution: "Bappeda",
    agency: "Bappeda",
    description: "Batas administrasi resmi wilayah Kabupaten Bojonegoro sesuai standar pemetaan RBI/BIG.",
    visible: true,
    opacity: 1,
    expanded: false,
    activeTool: "none",
    disableIdentify: true,
    order: 13,
  },
];

const activeLayers = ref<ActiveLayerItem[]>([...FALLBACK_DEFAULT_LAYERS]);
const hasInitializedLayersFromApi = ref(false);

const activeLayersSummary = computed(() => {
  const layers = activeLayers.value || [];
  const visible = layers.filter((l) => Boolean(l && l.visible)).length;
  return `${visible}/${layers.length} Layer Aktif`;
});

function applyLayersFromApi(feed: any) {
  const list = feed?.data || (Array.isArray(feed) ? feed : null);
  if (!Array.isArray(list) || list.length === 0) return;

  const defaultVisibleItems = list.filter((item: any) => Boolean(item.default_visible) && Boolean(item.is_active));
  if (defaultVisibleItems.length > 0) {
    defaultVisibleItems.sort((a, b) => {
      const isSegmenA = a.layer_name === "infrastruktur_segmen";
      const isSegmenB = b.layer_name === "infrastruktur_segmen";
      if (isSegmenA) return -1;
      if (isSegmenB) return 1;
      return (a.order ?? 0) - (b.order ?? 0);
    });

    activeLayers.value = defaultVisibleItems.map(mapLayerItemToActive);
    hasInitializedLayersFromApi.value = true;
  }
}

watch(
  () => layersData.value,
  (val) => {
    if (val && !hasInitializedLayersFromApi.value) {
      applyLayersFromApi(val);
    }
  },
  { immediate: true }
);

const activeLayerIds = computed(() => {
  const ids: string[] = [];
  for (const l of activeLayers.value) {
    if (l.id) ids.push(l.id);
    if (l.layer_name) ids.push(l.layer_name);
  }
  return ids;
});

// Basemap options definition
interface BasemapOption {
  key: BasemapKey;
  name: string;
  tagline: string;
}

const BASEMAP_OPTIONS: BasemapOption[] = [
  { key: "esri", name: "Esri", tagline: "Esri World Topo Map" },
  { key: "rbi", name: "RBI", tagline: "Rupabumi Indonesia (BIG)" },
  { key: "osm", name: "OSM", tagline: "Peta Jalan OSM" },
  { key: "satellite", name: "Satelit", tagline: "Citra Resolusi Tinggi" },
  { key: "aerial", name: "Aerial", tagline: "Foto Udara Resolusi Tinggi" },
];

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
    const targetBasemap = currentBasemap.value !== "esri" ? currentBasemap.value : null;

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
    if (newBasemap !== "esri") {
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
    // Filter layer MVT jika terdapat parameter kecamatan atau nama pencarian kecamatan
    const targetKecParam = typeof route.query.kecamatan === "string" && route.query.kecamatan.trim()
      ? route.query.kecamatan.trim()
      : (typeof route.query.wilayah === "string" && route.query.wilayah.trim()
          ? route.query.wilayah.trim()
          : (searchLabel
              ? (/^kecamatan\s+/i.test(searchLabel)
                  ? searchLabel.replace(/^kecamatan\s+/i, "").replace(/\s*\(kota\)$/i, "").trim()
                  : (KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === searchLabel.toLowerCase())?.name || null))
              : null));

    const parsedCoords = searchLabel ? parseCoordinateString(searchLabel) : null;

    if (targetKecParam) {
      // Hanya eksekusi jika filter kecamatan belum aktif atau berbeda dari yang sedang aktif
      if (targetKecParam.toLowerCase() !== (selectedKecamatanFilter.value || "").toLowerCase()) {
        handleFilterWilayah(targetKecParam);
        const matched = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === targetKecParam.toLowerCase());
        if (matched && matched.bbox) {
          mapCanvasRef.value?.fitBbox(matched.bbox, 14, 500, isMobileScreen.value ? "peek" : undefined);
          clickedCoordinate.value = null;
          mapCanvasRef.value?.dismissPulse?.();
          searchedLocationLabel.value = `Kecamatan ${matched.name}`;
        }
      }
    } else if (selectedKecamatanFilter.value && !route.query.wilayah && !route.query.kecamatan) {
      // Jika parameter wilayah/kecamatan sudah dibersihkan dari query URL, sinkronkan reset
      handleFilterWilayah(null);
    } else if (pv && (searchLabel || route.query.pin)) {
      clickedCoordinate.value = pv.center;
      searchedLocationLabel.value = searchLabel;
      mapCanvasRef.value?.flyTo(pv.center, pv.zoom || 14);
      locationBarOpen.value = true;
      mobileDrawerOpen.value = false;
    } else if (parsedCoords) {
      clickedCoordinate.value = [parsedCoords.lng, parsedCoords.lat];
      searchedLocationLabel.value = `Koordinat: ${parsedCoords.formatted}`;
      mapCanvasRef.value?.flyTo([parsedCoords.lng, parsedCoords.lat], 16);
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

// Sinkronisasi penutupan bottomsheet di mobile: saat drawer ditutup dan ada koordinat aktif, bersihkan
watch(mobileDrawerOpen, (isOpen) => {
  if (!isOpen && (clickedCoordinate.value || locationBarOpen.value)) {
    handleCloseInspector();
  }
});

onMounted(async () => {
  if (import.meta.client) {
    startLoadingSimulation();
    setTimeout(() => {
      if (!isMapCanvasLoaded.value) {
        handleMapLoaded();
      }
    }, 4500);
  }
  window.addEventListener("popstate", handlePopState);
  updateIsMobile();
  window.addEventListener("resize", updateIsMobile, { passive: true });
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    mobileDrawerOpen.value = true;
    mobileSheetSnap.value = "min";
  } else {
    // Buka panel kiri secara default di desktop
    leftPanelOpen.value = true;
    syncSidebarWidth();
  }
  try {
    const saved = localStorage.getItem("melarosa_active_basemap");
    if (saved && validBasemaps.includes(saved as BasemapKey) && !route.query.basemap) {
      currentBasemap.value = saved as BasemapKey;
    }
  } catch {}

  // Filter layer MVT pada inisialisasi jika query kecamatan/wilayah tersedia
  const initialKec = typeof route.query.kecamatan === "string" && route.query.kecamatan.trim()
    ? route.query.kecamatan.trim()
    : (typeof route.query.wilayah === "string" && route.query.wilayah.trim()
        ? route.query.wilayah.trim()
        : (searchedLocationLabel.value
            ? (/^kecamatan\s+/i.test(searchedLocationLabel.value)
                ? searchedLocationLabel.value.replace(/^kecamatan\s+/i, "").replace(/\s*\(kota\)$/i, "").trim()
                : (KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === searchedLocationLabel.value!.toLowerCase())?.name || null))
            : null));
  const initialCoord = searchedLocationLabel.value ? parseCoordinateString(searchedLocationLabel.value) : null;
  if (initialKec) {
    handleFilterWilayah(initialKec);
    const matched = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === initialKec.toLowerCase());
    if (matched && matched.bbox && !route.query.bbox) {
      setTimeout(() => {
        mapCanvasRef.value?.fitBbox(matched.bbox, 14, 500, isMobileScreen.value ? "peek" : undefined);
        clickedCoordinate.value = null;
        mapCanvasRef.value?.dismissPulse?.();
      }, 350);
    }
  } else if (initialCoord && !pathView) {
    setTimeout(() => {
      clickedCoordinate.value = [initialCoord.lng, initialCoord.lat];
      mapCanvasRef.value?.flyTo([initialCoord.lng, initialCoord.lat], 16);
      locationBarOpen.value = true;
    }, 350);
  }

  // Fitbound awal hanya jika data spasial Martin mengembalikan bounds; tanpa objek, kamera tidak digeser
  if (!pathView && !route.query.bbox && !hasExplicitSearchOrPin) {
    try {
      const [segmenRes, jalanRes] = await Promise.allSettled([
        $fetch<any>("/martin/infrastruktur_segmen"),
        $fetch<any>("/martin/jalan_porosdesa"),
      ]);
      const b1 = segmenRes.status === "fulfilled" ? segmenRes.value?.bounds : null;
      const b2 = jalanRes.status === "fulfilled" ? jalanRes.value?.bounds : null;
      let targetBbox: [number, number, number, number] | null = null;
      if (Array.isArray(b1) && Array.isArray(b2) && b1.length === 4 && b2.length === 4) {
        targetBbox = [
          Math.min(b1[0], b2[0]),
          Math.min(b1[1], b2[1]),
          Math.max(b1[2], b2[2]),
          Math.max(b1[3], b2[3]),
        ];
      } else if (Array.isArray(b1) && b1.length === 4) {
        targetBbox = b1 as [number, number, number, number];
      } else if (Array.isArray(b2) && b2.length === 4) {
        targetBbox = b2 as [number, number, number, number];
      }
      if (targetBbox) {
        const bbox = targetBbox;
        nextTick(() => {
          mapCanvasRef.value?.fitBbox(bbox, 15, 600);
        });
      }
    } catch {}
  }
});

onUnmounted(() => {
  if (progressTimer) clearInterval(progressTimer);
  if (urlUpdateTimer) clearTimeout(urlUpdateTimer);
  window.removeEventListener("popstate", handlePopState);
  window.removeEventListener("resize", updateIsMobile);
});

function handleMapClick(coordinate: [number, number]) {
  clickedCoordinate.value = coordinate;
  searchedLocationLabel.value = null;
  identifiedFeatures.value = [];
  locationBarOpen.value = true;

  // Bersihkan highlight trayek lama seketika saat titik baru diklik
  mapCanvasRef.value?.renderSelectedFeature?.(null);

  // Simpan tab sebelumnya jika pengguna sedang membuka tab selain 'layers' (Peta)
  if (currentMainTab.value !== "layers") {
    previousTabBeforeIdentify.value = currentMainTab.value;
  }
  handleTabChange("layers");

  if (typeof window !== "undefined" && window.innerWidth < 768) {
    // Mobile: buka bottom sheet di tab layer ke posisi peek (50% vh) agar hasil identifikasi fitur langsung tampak
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  } else {
    // Desktop: pastikan panel kiri terbuka
    leftPanelOpen.value = true;
  }
  // Fitbound dihandle tunggal secara definitif di handleIdentifyFeatures setelah data siap
}

function handleCanvasTapDismiss() {
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    mobileSheetSnap.value = "min";
    if (locationBarOpen.value) {
      handleCloseInspector();
    }
  }
}

function handleSelectLocation(payload: { name: string; coordinate: [number, number]; zoom?: number }) {
  clickedCoordinate.value = payload.coordinate;
  searchedLocationLabel.value = payload.name;
  locationBarOpen.value = true;
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
    mapCanvasRef.value?.fitCoordinateWithPadding(payload.coordinate, payload.zoom ?? 15, 500, "peek");
  } else {
    leftPanelOpen.value = true;
    mobileDrawerOpen.value = false;
    mapCanvasRef.value?.fitCoordinateWithPadding(payload.coordinate, payload.zoom ?? 15, 500);
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

function handleSelectKecamatan(payload: {
  name: string;
  coordinate: [number, number];
  zoom?: number;
  bbox?: [number, number, number, number];
  isKecamatan?: boolean;
}) {
  const cleanKecName = payload.name.replace(/^Kecamatan\s+/i, "").replace(/\s*\(kota\)$/i, "").trim().toLowerCase();
  const matchedKec = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === cleanKecName);
  const targetBbox = payload.bbox || matchedKec?.bbox;
  const isKec = payload.isKecamatan || !!matchedKec || !!payload.bbox;

  if (isKec && targetBbox) {
    mapCanvasRef.value?.fitBbox(
      targetBbox,
      payload.zoom ?? 14,
      500,
      isMobileScreen.value ? "peek" : undefined
    );
    // Peniadaan marker pin: pencarian wilayah kecamatan adalah inspeksi area, bukan titik penanda
    clickedCoordinate.value = null;
    mapCanvasRef.value?.dismissPulse?.();
  } else {
    mapCanvasRef.value?.fitCoordinateWithPadding(
      payload.coordinate,
      payload.zoom ?? 13.5,
      500,
      isMobileScreen.value ? "peek" : undefined
    );
    clickedCoordinate.value = payload.coordinate;
  }

  searchedLocationLabel.value = payload.name;
  if (isMobileScreen.value) {
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  }
}

function handleSelectCoordinate(payload: {
  coordinate: [number, number];
  formatted: string;
  zoom?: number;
}) {
  clickedCoordinate.value = payload.coordinate;
  searchedLocationLabel.value = `Titik Koordinat: ${payload.formatted}`;
  locationBarOpen.value = true;

  const targetZoom = payload.zoom ?? 16;

  if (isMobileScreen.value) {
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
    mapCanvasRef.value?.flyTo(payload.coordinate, targetZoom, "peek");
  } else {
    leftPanelOpen.value = true;
    mobileDrawerOpen.value = false;
    mapCanvasRef.value?.flyTo(payload.coordinate, targetZoom);
  }

  // Sinkronkan query parameter ?search= ke URL browser dan pastikan basemap aktif tetap terjaga
  if (import.meta.client) {
    const url = new URL(window.location.href);
    url.searchParams.set("search", payload.formatted);
    url.searchParams.set("pin", "1");
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

function isIgnoredIdentifyFeature(f: IdentifiedFeature): boolean {
  if (!f) return true;
  const layerId = String(f.layerId || "").toLowerCase();
  const layerTitle = String(f.layerTitle || "").toLowerCase();
  return (
    layerId.includes("bataswilayah_kabupaten") ||
    layerTitle.includes("bataswilayah_kabupaten") ||
    layerTitle.includes("batas wilayah kabupaten")
  );
}

function handleIdentifyFeatures(features: IdentifiedFeature[]) {
  const filtered = (features || []).filter((f) => !isIgnoredIdentifyFeature(f));
  identifiedFeatures.value = filtered;
  if (filtered.length > 0) {
    const topFeature = filtered[0];
    if (topFeature.coordinate) {
      clickedCoordinate.value = topFeature.coordinate;
    }
    const namobj = extractNamobj(topFeature.properties);
    if (namobj) {
      searchedLocationLabel.value = namobj;
    }
    locationBarOpen.value = true;
    if (isMobileScreen.value) {
      mobileDrawerTab.value = "layer";
      mobileSheetSnap.value = "peek";
      mobileDrawerOpen.value = true;
    }

    // Pasang highlight style trayek UI modern untuk fitur teratas
    mapCanvasRef.value?.renderSelectedFeature?.(topFeature);

    // SATU KALI FITBOUND TUNGGAL:
    // Prioritaskan extent geometri fitur teratas layer aktif agar tidak terjadi pergeseran kamera ganda
    if (topFeature.extent) {
      mapCanvasRef.value?.fitFeatureExtent(
        topFeature.extent,
        undefined,
        550,
        isMobileScreen.value ? "peek" : undefined
      );
    } else if (clickedCoordinate.value) {
      mapCanvasRef.value?.fitCoordinateWithPadding(
        clickedCoordinate.value,
        undefined,
        500,
        isMobileScreen.value ? "peek" : undefined
      );
    }
  } else {
    // Klik area kosong: tidak ada objek fitur, kamera tidak digeser
    mapCanvasRef.value?.renderSelectedFeature?.(null);
  }
}

function handleSelectFeature(feature: IdentifiedFeature) {
  const namobj = extractNamobj(feature?.properties);
  if (namobj) {
    searchedLocationLabel.value = namobj;
  }

  // Update highlight style trayek UI modern sesuai fitur yang dipilih dari daftar
  mapCanvasRef.value?.renderSelectedFeature?.(feature);

  if (isMobileScreen.value) {
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  }

  if (feature?.extent) {
    mapCanvasRef.value?.fitFeatureExtent(
      feature.extent,
      undefined,
      500,
      isMobileScreen.value ? "peek" : undefined
    );
  } else if (feature?.coordinate) {
    mapCanvasRef.value?.fitCoordinateWithPadding(
      feature.coordinate,
      undefined,
      450,
      isMobileScreen.value ? "peek" : undefined
    );
  }
}

let isClosingInspector = false;

function handleClearPin() {
  clickedCoordinate.value = null;
  searchedLocationLabel.value = null;
  identifiedFeatures.value = [];
  locationBarOpen.value = false;
  mapCanvasRef.value?.renderSelectedFeature?.(null);
  if (import.meta.client) {
    const url = new URL(window.location.href);
    url.searchParams.delete("search");
    url.searchParams.delete("name");
    url.searchParams.delete("pin");
    window.history.replaceState(window.history.state, "", url.toString());
  }
}

function handleCloseInspector() {
  if (isClosingInspector) return;
  isClosingInspector = true;
  try {
    locationBarOpen.value = false;
    clickedCoordinate.value = null;
    searchedLocationLabel.value = null;
    identifiedFeatures.value = [];
    mapCanvasRef.value?.dismissPulse?.();
    mapCanvasRef.value?.renderSelectedFeature?.(null);

    // Otomatis kembalikan ke tab sebelumnya (Dataset atau Geopark) saat inspector ditutup
    if (previousTabBeforeIdentify.value) {
      const prev = previousTabBeforeIdentify.value;
      previousTabBeforeIdentify.value = null;
      handleTabChange(prev);
      if (isMobileScreen.value) {
        mobileDrawerTab.value = prev === "geopark" ? "geopark" : prev === "dataset" ? "dataset" : "layer";
      }
    }

    if (import.meta.client) {
      const url = new URL(window.location.href);
      url.searchParams.delete("search");
      url.searchParams.delete("name");
      url.searchParams.delete("pin");
      if (currentBasemap.value && currentBasemap.value !== "esri") {
        url.searchParams.set("basemap", currentBasemap.value);
      } else {
        url.searchParams.delete("basemap");
      }
      window.history.replaceState(window.history.state, "", url.toString());
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

const mobileLeftPanelRef = ref<any>(null);
const desktopLeftPanelRef = ref<any>(null);

const { sites: geoparkSites } = useGeoparkSites();

function getGeoparkSiteSlug(site: GeoparkSite): string {
  if (!site) return "";
  const name = site.name || site.title || "";
  const clean = name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
  return clean || site.code.toLowerCase() || site.id;
}

function findGeoparkSiteBySlugOrId(querySite: string, list: GeoparkSite[]): GeoparkSite | null {
  if (!querySite || !list.length) return null;
  const q = String(querySite).trim().toLowerCase();
  return list.find((s) => {
    if (s.slug && s.slug.toLowerCase() === q) return true;
    if (s.id && s.id.toLowerCase() === q) return true;
    if (s.code && s.code.toLowerCase() === q) return true;
    const slug = s.name ? s.name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") : "";
    if (slug === q) return true;
    if (s.name && s.name.toLowerCase() === q) return true;
    if (s.title && s.title.toLowerCase() === q) return true;
    return false;
  }) || null;
}

function updateMapQuery(newParams: Record<string, string | null | undefined>, replace = false) {
  const current = { ...route.query };
  let changed = false;

  for (const [k, v] of Object.entries(newParams)) {
    if (v === null || v === undefined || v === "") {
      if (k in current) {
        delete current[k];
        changed = true;
      }
    } else if (current[k] !== String(v)) {
      current[k] = String(v);
      changed = true;
    }
  }

  if (changed) {
    if (replace) {
      router.replace({ query: current });
    } else {
      router.push({ query: current });
    }
  }
}

// Active Tab derived directly from route.query.tab atau route.query.geosite (Single source of truth)
const currentMainTab = computed<MainTab>(() => {
  const raw = String(route.query.tab || "").toLowerCase();
  const siteQuery = route.query.geosite || route.query.site;
  if (raw === "geosite" || raw === "geopark" || Boolean(siteQuery)) return "geopark";
  if (raw === "dataset" || raw === "geoportal") return "dataset";
  return "layers";
});

const selectedGeoparkSite = computed<GeoparkSite | null>(() => {
  const siteQuery = route.query.geosite || route.query.site;
  if (!siteQuery) return null;
  return findGeoparkSiteBySlugOrId(String(siteQuery), geoparkSites.value);
});

function handleTabChange(tab: MainTab) {
  if (tab !== "layers") {
    previousTabBeforeIdentify.value = null;
  }
  const queryTab = tab === "layers" ? undefined : tab === "geopark" ? "geosite" : tab;
  updateMapQuery({
    tab: queryTab,
    geosite: undefined,
    site: undefined,
    layer: undefined,
  });
}

function handleMobileTabClick(tab: MobileDrawerTab) {
  if (locationBarOpen.value) {
    locationBarOpen.value = false;
    clickedCoordinate.value = null;
    searchedLocationLabel.value = null;
    identifiedFeatures.value = [];
    mapCanvasRef.value?.dismissPulse?.();
  }
  previousTabBeforeIdentify.value = null;
  mobileDrawerTab.value = tab;
  if (mobileSheetSnap.value === "min") {
    mobileSheetSnap.value = "peek";
  }
  const mainTab: MainTab =
    tab === "geopark"
      ? "geopark"
      : tab === "dataset" || tab === "geoportal"
        ? "dataset"
        : "layers";
  handleTabChange(mainTab);
  mobileLeftPanelRef.value?.resetSubViews(mainTab);
  desktopLeftPanelRef.value?.resetSubViews(mainTab);
}

function openMobileTab(tab: MobileDrawerTab) {
  mobileDrawerTab.value = tab;
  mobileSheetSnap.value = "peek";
  mobileDrawerOpen.value = true;
  const mainTab: MainTab =
    tab === "geopark"
      ? "geopark"
      : tab === "dataset" || tab === "geoportal"
        ? "dataset"
        : "layers";
  handleTabChange(mainTab);
  mobileLeftPanelRef.value?.resetSubViews(mainTab);
  desktopLeftPanelRef.value?.resetSubViews(mainTab);
}

function handleOpenLayerDetail(layerId: string) {
  updateMapQuery({
    tab: undefined,
    layer: layerId,
  });
  if (isMobileScreen.value) {
    mobileDrawerTab.value = "layer";
    mobileDrawerOpen.value = true;
    if (mobileSheetSnap.value === "min") {
      mobileSheetSnap.value = "peek";
    }
  }
}

function handleCloseLayerDetail() {
  updateMapQuery({ layer: undefined });
}

function handleOpenGeoparkDetail(site: GeoparkSite) {
  const slug = site.slug || getGeoparkSiteSlug(site);
  updateMapQuery({
    tab: "geosite",
    geosite: slug,
    site: undefined,
  });
  if (site.hasCoordinate && site.coordinate) {
    mapCanvasRef.value?.flyTo(site.coordinate, site.zoom || 15);
  }
  if (!leftPanelOpen.value && !isMobileScreen.value) {
    leftPanelOpen.value = true;
  }
  if (isMobileScreen.value) {
    mobileDrawerTab.value = "geopark";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  }
}

function handleCloseGeoparkDetail() {
  updateMapQuery({
    geosite: undefined,
    site: undefined,
  });
}

function handleMobileSearchFocus() {
  if (isMobileScreen.value) {
    mobileDrawerOpen.value = true;
    mobileSheetSnap.value = "full";
  } else if (mobileDrawerOpen.value) {
    mobileSheetSnap.value = "full";
  }
  if (typeof window !== "undefined") {
    window.scrollTo(0, 0);
  }
}

function handleMobileSearchClose() {
  if (typeof window !== "undefined") {
    window.scrollTo(0, 0);
  }
}

function applyRouteQueryToViews() {
  const q = route.query;
  const rawTab = String(q.tab || "").toLowerCase();
  const siteQuery = q.geosite || q.site;
  const mainTab: MainTab =
    rawTab === "geosite" || rawTab === "geopark" || Boolean(siteQuery)
      ? "geopark"
      : rawTab === "dataset" || rawTab === "geoportal"
        ? "dataset"
        : "layers";

  mobileDrawerTab.value =
    mainTab === "geopark"
      ? "geopark"
      : mainTab === "dataset"
        ? "dataset"
        : "layer";

  // Check sub-views
  if (siteQuery) {
    const matched = findGeoparkSiteBySlugOrId(String(siteQuery), geoparkSites.value);
    if (matched) {
      desktopLeftPanelRef.value?.openGeoparkDetail(matched);
      mobileLeftPanelRef.value?.openGeoparkDetail(matched);
      if (!leftPanelOpen.value && !isMobileScreen.value) {
        leftPanelOpen.value = true;
      }
      if (matched.hasCoordinate && matched.coordinate) {
        mapCanvasRef.value?.flyTo(matched.coordinate, matched.zoom || 15);
      }
      if (isMobileScreen.value) {
        mobileDrawerTab.value = "geopark";
        mobileSheetSnap.value = "peek";
        mobileDrawerOpen.value = true;
      }
    }
  } else {
    desktopLeftPanelRef.value?.closeGeoparkDetail();
    mobileLeftPanelRef.value?.closeGeoparkDetail();
  }

  if (mainTab === "layers" && q.layer) {
    desktopLeftPanelRef.value?.openLayerDetail(String(q.layer));
    mobileLeftPanelRef.value?.openLayerDetail(String(q.layer));
    if (isMobileScreen.value) {
      mobileDrawerTab.value = "layer";
      mobileDrawerOpen.value = true;
      if (mobileSheetSnap.value === "min") {
        mobileSheetSnap.value = "peek";
      }
    }
  } else if (!q.layer) {
    desktopLeftPanelRef.value?.closeLayerDetail();
    mobileLeftPanelRef.value?.closeLayerDetail();
  }

  const rawKec = q.wilayah || q.kecamatan;
  if (rawKec) {
    const matchedKec = KECAMATAN_ENTRIES.find(
      (k) => k.name.toLowerCase() === String(rawKec).toLowerCase()
    );
    if (matchedKec && selectedKecamatanFilter.value !== matchedKec.name) {
      selectedKecamatanFilter.value = matchedKec.name;
      if (isMobileScreen.value) {
        mobileSheetSnap.value = "peek";
        mobileDrawerOpen.value = true;
      }
    }
  } else if (!rawKec && selectedKecamatanFilter.value) {
    selectedKecamatanFilter.value = null;
  }
}

watch(
  () => route.query,
  () => {
    applyRouteQueryToViews();
  },
  { deep: true }
);

watch(
  () => geoparkSites.value,
  (list) => {
    const siteQuery = route.query.geosite || route.query.site;
    if (list.length && siteQuery) {
      const matched = findGeoparkSiteBySlugOrId(String(siteQuery), list);
      if (matched) {
        desktopLeftPanelRef.value?.openGeoparkDetail(matched);
        mobileLeftPanelRef.value?.openGeoparkDetail(matched);
        if (!leftPanelOpen.value && !isMobileScreen.value) {
          leftPanelOpen.value = true;
        }
        if (matched.hasCoordinate && matched.coordinate) {
          mapCanvasRef.value?.flyTo(matched.coordinate, matched.zoom || 15);
        }
        if (isMobileScreen.value) {
          mobileDrawerTab.value = "geopark";
          mobileSheetSnap.value = "peek";
          mobileDrawerOpen.value = true;
        }
      }
    }
  }
);

onMounted(() => {
  setTimeout(() => {
    applyRouteQueryToViews();
  }, 120);
});

function toggleLeftPanel() {
  leftPanelOpen.value = !leftPanelOpen.value;
}

function handleZoomToLayer(layer: ActiveLayerItem) {
  if (layer.coordinates) {
    clickedCoordinate.value = layer.coordinates;
    searchedLocationLabel.value = layer.title;
    if (isMobileScreen.value) {
      mapCanvasRef.value?.fitCoordinateWithPadding(layer.coordinates, layer.zoom || 12, 500, "peek");
      mobileSheetSnap.value = "peek";
      mobileDrawerOpen.value = true;
    } else {
      mapCanvasRef.value?.flyTo(layer.coordinates, layer.zoom || 12);
    }
    locationBarOpen.value = true;
    if (!isMobileScreen.value) {
      leftPanelOpen.value = true;
    }
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
  activeLayers.value = activeLayers.value.map((layer) => {
    if (layer.id === payload.layerId) {
      return {
        ...layer,
        filterKecamatan: payload.kecamatan,
        filterStatus: payload.status,
      };
    }
    return layer;
  });

  // Jika layer di-clear dan tidak ada lagi layer dengan filter kecamatan aktif
  const hasRemainingFilter = activeLayers.value.some(
    (l) => Boolean(l.filterKecamatan && l.filterKecamatan !== "ALL")
  );
  if (!hasRemainingFilter && selectedKecamatanFilter.value) {
    handleFilterWilayah(null);
  }
}

function handleFilterWilayah(kecamatanName: string | null) {
  selectedKecamatanFilter.value = kecamatanName;
  updateMapQuery({
    wilayah: kecamatanName || undefined,
    kecamatan: kecamatanName || undefined,
    search: kecamatanName ? (route.query.search as string) : (route.query.search && /^kecamatan/i.test(String(route.query.search)) ? undefined : (route.query.search as string)),
    name: kecamatanName ? (route.query.name as string) : (route.query.name && /^kecamatan/i.test(String(route.query.name)) ? undefined : (route.query.name as string)),
    q: kecamatanName ? (route.query.q as string) : (route.query.q && /^kecamatan/i.test(String(route.query.q)) ? undefined : (route.query.q as string)),
  });
  if (isMobileScreen.value && kecamatanName) {
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  }
  if (kecamatanName) {
    clickedCoordinate.value = null;
    mapCanvasRef.value?.dismissPulse?.();
  } else {
    searchedLocationLabel.value = null;
    if (import.meta.client) {
      const url = new URL(window.location.href);
      url.searchParams.delete("wilayah");
      url.searchParams.delete("kecamatan");
      const s = url.searchParams.get("search");
      if (s && (/^kecamatan\s+/i.test(s) || KECAMATAN_ENTRIES.some((k) => k.name.toLowerCase() === s.toLowerCase()))) {
        url.searchParams.delete("search");
      }
      const n = url.searchParams.get("name");
      if (n && (/^kecamatan\s+/i.test(n) || KECAMATAN_ENTRIES.some((k) => k.name.toLowerCase() === n.toLowerCase()))) {
        url.searchParams.delete("name");
      }
      const q = url.searchParams.get("q");
      if (q && (/^kecamatan\s+/i.test(q) || KECAMATAN_ENTRIES.some((k) => k.name.toLowerCase() === q.toLowerCase()))) {
        url.searchParams.delete("q");
      }
      window.history.replaceState(window.history.state, "", url.toString());
    }
  }
  activeLayers.value = activeLayers.value.map((layer) => {
    const isMvt =
      (layer.protocol || "").toUpperCase() === "MVT" ||
      layer.source_type === "martin" ||
      (layer.layer_name && ["infrastruktur_segmen", "jalan_porosdesa"].includes(layer.layer_name));
    if (isMvt) {
      return {
        ...layer,
        filterKecamatan: kecamatanName,
      };
    }
    return layer;
  });
}

function handleReorderLayers(newLayers: ActiveLayerItem[]) {
  activeLayers.value = [...newLayers];
}

function handleRemoveLayer(layerId: string) {
  activeLayers.value = (activeLayers.value || []).filter((l) => l.id !== layerId);
}

function handleSelectDataset(dataset: any) {
  const existing = activeLayers.value.find(
    (l) => l.id === dataset.id || (l.layer_name && dataset.layer_name && l.layer_name === dataset.layer_name)
  );
  if (existing) {
    existing.visible = true;
    existing.expanded = true;
  } else {
    // Sisipkan layer baru di posisi teratas tumpukan
    activeLayers.value.unshift(mapLayerItemToActive(dataset));
  }

  leftPanelOpen.value = true;
}

// Transisi masuk Floating Left Panel (muncul anggun setelah navbar meregang ke kiri & kanan)
const isLeftPanelMounted = ref(false);

onMounted(() => {
  if (import.meta.client) {
    isMapsReady.value = false;
    isLeftPanelMounted.value = false;
    updateIsMobile();
    window.addEventListener("resize", updateIsMobile, { passive: true });
    startLoadingSimulation();

    // Fallback keselamatan jika event map loaded tertahan lebih dari 4 detik
    setTimeout(() => {
      if (!isMapsReady.value) {
        isMapsReady.value = true;
        isLeftPanelMounted.value = true;
      }
    }, 4500);
  }
});

onUnmounted(() => {
  if (import.meta.client) {
    window.removeEventListener("resize", updateIsMobile);
  }
  isMapsReady.value = false;
  isLeftPanelMounted.value = false;
  if (progressTimer) {
    clearInterval(progressTimer);
    progressTimer = null;
  }
  if (urlUpdateTimer) {
    clearTimeout(urlUpdateTimer);
    urlUpdateTimer = null;
  }
});
</script>

<template>
  <div
    class="wrapper-maps flex w-full h-full relative overflow-hidden bg-gray-100 dark:bg-[#070b14] select-none font-sans antialiased text-gray-900 dark:text-gray-100"
  >
    <!-- Panel Kiri: Desktop Floating Card (melayang di bawah Floating Navbar) -->
    <aside
      class="hidden md:flex flex-col w-[350px] lg:w-[400px] shrink-0 absolute top-[76px] sm:top-[80px] left-3 sm:left-4 bottom-3 sm:bottom-4 z-20 bg-white dark:bg-[#09111e] rounded-2xl sm:rounded-3xl shadow-2xl shadow-slate-900/10 dark:shadow-black/50 border border-slate-200/80 dark:border-white/10 overflow-hidden transition-all duration-[1500ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] will-change-transform"
      :class="isLeftPanelMounted
        ? 'opacity-100 translate-x-0 pointer-events-auto'
        : 'opacity-0 -translate-x-12 pointer-events-none'"
      aria-label="Panel Navigasi Spasial Kiri"
    >
      <LeftPanel
        ref="desktopLeftPanelRef"
        :active-tab="currentMainTab"
        :active-layers="activeLayers"
        :selected-kecamatan-filter="selectedKecamatanFilter"
        :clicked-coordinate="clickedCoordinate"
        :location-label="searchedLocationLabel"
        :identified-features="identifiedFeatures"
        :is-identifying="isIdentifyingFeatures"
        :inspector-open="locationBarOpen"
        @tab-change="handleTabChange"
        @open-layer-detail="handleOpenLayerDetail"
        @close-layer-detail="handleCloseLayerDetail"
        @open-geopark-detail="handleOpenGeoparkDetail"
        @close-geopark-detail="handleCloseGeoparkDetail"
        @zoom-to-home="handleFilterWilayah(null); mapCanvasRef?.resetView()"
        @select-kecamatan="handleSelectKecamatan"
        @filter-wilayah="handleFilterWilayah"
        @reorder-layers="handleReorderLayers"
        @zoom-to-layer="handleZoomToLayer"
        @toggle-layer-visibility="handleToggleLayerVisibility"
        @update-layer-opacity="handleUpdateLayerOpacity"
        @update-layer-filter="handleUpdateLayerFilter"
        @remove-layer="handleRemoveLayer"
        @add-layer="handleSelectDataset"
        @close-inspector="handleCloseInspector"
        @clear-pin="handleClearPin"
        @select-feature="handleSelectFeature"
        @select-coordinate="handleSelectCoordinate"
        @share="handleOpenShare"
      />
    </aside>

    <!-- Kanvas Peta: Memenuhi seluruh layar latar belakang (Edge-to-Edge) -->
    <main
      class="w-full h-full relative overflow-hidden bg-gray-100 dark:bg-[#070b14]"
      aria-label="Kanvas Peta Interaktif"
    >
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
          :active-tab="currentMainTab"
          :selected-geopark-site="selectedGeoparkSite"
          :geopark-sites="currentMainTab === 'geopark' ? geoparkSites : []"
          @select-geopark="handleOpenGeoparkDetail"
          @map-click="handleMapClick"
          @canvas-tap-dismiss="handleCanvasTapDismiss"
          @identify-features="handleIdentifyFeatures"
          @identifying-features="isIdentifyingFeatures = $event"
          @update:viewport="handleViewportUpdate"
          @dismiss-popup="handleCloseInspector"
          @share="handleOpenShare"
          @loaded="handleMapLoaded"
        />
        <template #fallback>
          <div class="absolute inset-0 z-30 flex flex-col items-center justify-center select-none bg-slate-50 dark:bg-[#070c16] text-slate-800 dark:text-slate-100 p-6 overflow-hidden">
            <div class="absolute inset-0 opacity-[0.035] dark:opacity-[0.06] pointer-events-none [background-image:radial-gradient(#2563eb_1px,transparent_1px)] [background-size:24px_24px]" />

            <div class="relative z-10 flex flex-col items-center text-center max-w-sm w-full space-y-4">
              <div class="relative size-14 rounded-2xl bg-blue-50 dark:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/60 flex items-center justify-center text-blue-600 dark:text-blue-400 shadow-xl shadow-blue-500/10">
                <UIcon name="i-lucide-compass" class="size-7 animate-spin" style="animation-duration: 4s;" />
                <span class="absolute -top-1 -right-1 size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#070c16] animate-pulse" />
              </div>

              <div class="space-y-1">
                <h3 class="text-sm sm:text-base font-bold tracking-tight text-slate-900 dark:text-white">
                  Memuat Peta Spasial
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                  Menyiapkan kanvas peta Bojonegoro...
                </p>
              </div>

              <div class="w-full max-w-[240px] space-y-2">
                <div class="h-1.5 w-full bg-slate-200/80 dark:bg-white/10 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-blue-600 dark:bg-blue-500 rounded-full animate-pulse"
                    style="width: 35%;"
                  />
                </div>
              </div>

              <p class="text-[10px] font-mono tracking-wider text-slate-400 dark:text-slate-500 uppercase">
                WebGIS Kabupaten Bojonegoro
              </p>
            </div>
          </div>
        </template>
      </ClientOnly>

      <!-- Map Canvas Loading Progress Overlay (Smooth Transition, Active on first load) -->
      <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        leave-active-class="transition-opacity duration-400 ease-in"
        leave-to-class="opacity-0 pointer-events-none"
      >
        <div
          v-if="!isMapCanvasLoaded"
          class="absolute inset-0 z-30 flex flex-col items-center justify-center select-none bg-slate-50/95 dark:bg-[#070c16]/95 backdrop-blur-xs text-slate-800 dark:text-slate-100 p-6 overflow-hidden"
          role="status"
          aria-live="polite"
        >
          <div class="absolute inset-0 opacity-[0.035] dark:opacity-[0.06] pointer-events-none [background-image:radial-gradient(#2563eb_1px,transparent_1px)] [background-size:24px_24px]" />

          <div class="relative z-10 flex flex-col items-center text-center max-w-sm w-full space-y-4">
            <div class="relative size-14 rounded-2xl bg-blue-50 dark:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/60 flex items-center justify-center text-blue-600 dark:text-blue-400 shadow-xl shadow-blue-500/10">
              <UIcon name="i-lucide-compass" class="size-7 animate-spin" style="animation-duration: 4s;" />
              <span class="absolute -top-1 -right-1 size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#070c16] animate-pulse" />
            </div>

            <div class="space-y-1">
              <h3 class="text-sm sm:text-base font-bold tracking-tight text-slate-900 dark:text-white">
                Memuat Peta Spasial
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                {{ loadingStageText }}
              </p>
            </div>

            <div class="w-full max-w-[240px] space-y-1.5">
              <div class="h-1.5 w-full bg-slate-200/80 dark:bg-white/10 rounded-full overflow-hidden">
                <div
                  class="h-full bg-blue-600 dark:bg-blue-500 rounded-full transition-all duration-300 ease-out"
                  :style="{ width: `${loadingProgress}%` }"
                />
              </div>
              <div class="flex items-center justify-between text-[11px] font-mono text-slate-400 dark:text-slate-500 px-0.5">
                <span>Inisialisasi</span>
                <span class="font-bold text-blue-600 dark:text-blue-400 tabular-nums">
                  {{ loadingProgress }}%
                </span>
              </div>
            </div>

            <p class="text-[10px] font-mono tracking-wider text-slate-400 dark:text-slate-500 uppercase">
              WebGIS Kabupaten Bojonegoro
            </p>
          </div>
        </div>
      </Transition>

      <!-- Floating Display & Layer Group (Kiri Bawah di dalam Kanvas Peta) -->
      <div
        class="absolute z-20 pointer-events-auto select-none flex flex-col items-start gap-1.5 transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] left-3 sm:left-4 md:left-[380px] lg:left-[432px]"
        :class="[
          mobileDrawerOpen && mobileSheetSnap === 'full'
            ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
            : mobileDrawerOpen && mobileSheetSnap === 'peek'
              ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4'
              : mobileDrawerOpen && mobileSheetSnap === 'min'
                ? 'bottom-[calc(150px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
                : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100 pointer-events-auto'
        ]"
      >
        <!-- Stack Tombol Utilitas (Fitbound Batas Kabupaten & Grid Kartografi) diletakkan di atas Basemap -->
        <div
          class="flex flex-col w-11 bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-gray-200/90 dark:border-gray-800 rounded-lg sm:rounded-xl shadow-md hover:shadow-lg transition-all duration-200 overflow-hidden"
        >
          <!-- Tombol Fitbound ke Default Batas Wilayah Kabupaten Bojonegoro -->
          <UTooltip text="Pusatkan ke Batas Wilayah Kabupaten Bojonegoro">
            <button
              type="button"
              class="w-full h-11 min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 active:scale-90"
              aria-label="Pusatkan ke Batas Wilayah Kabupaten Bojonegoro"
              @click="handleFitKabupaten"
            >
              <UIcon name="i-lucide-maximize" class="size-4 stroke-[2.25]" />
            </button>
          </UTooltip>

          <div class="w-full h-px bg-gray-200/80 dark:bg-slate-800" aria-hidden="true" />

          <!-- Tombol Toggle Grid Kartografi & Koordinat Tepi (di atas toggle basemap) -->
          <UTooltip :text="isCartoGridActive ? 'Sembunyikan Grid Kartografi & Koordinat' : 'Tampilkan Grid Kartografi & Koordinat Tepi'">
            <button
              type="button"
              class="w-full h-11 min-h-[44px] min-w-[44px] flex items-center justify-center transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 active:scale-90"
              :class="isCartoGridActive
                ? 'text-blue-600 dark:text-blue-400 bg-blue-50/70 dark:bg-blue-950/50 hover:bg-blue-100/70 dark:hover:bg-blue-900/50'
                : 'text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80'"
              :aria-pressed="isCartoGridActive"
              aria-label="Toggle Grid Kartografi & Koordinat Tepi"
              @click="handleToggleCartoGrid"
            >
              <UIcon name="i-lucide-grid-3x3" class="size-4 stroke-[2.25]" />
            </button>
          </UTooltip>
        </div>

        <!-- Basemap Panel (di bawah Grid Kartografi) -->
        <BasemapPanel
          v-model:current-basemap="currentBasemap"
          :is-mobile="isMobileScreen"
          :is-mobile-sheet-open="isBasemapBottomSheetOpen"
          @open-mobile-sheet="handleToggleBasemapSheet"
        />
      </div>
    </main>

    <!-- ═══ 4. [DIHAPUS] Floating BottomLocationBar desktop dipindahkan ke dalam LeftPanel sub-view identify ═══ -->

    <!-- ═══ 5. MOBILE BOTTOM SHEET DRAWER ═════════════════════════════════════ -->
    <MapMobileBottomSheet
      v-model:open="mobileDrawerOpen"
      v-model:snap="mobileSheetSnap"
      @backdrop-click="handleCanvasTapDismiss"
      :title="
        locationBarOpen && clickedCoordinate
          ? 'Identifikasi Fitur'
          : mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
            ? 'Katalog Dataset'
            : mobileDrawerTab === 'geopark'
              ? 'Geopark Bojonegoro'
              : 'Panel Lapisan Peta'
      "
      :subtitle="
        locationBarOpen && clickedCoordinate
          ? (searchedLocationLabel || `${clickedCoordinate[1].toFixed(5)}, ${clickedCoordinate[0].toFixed(5)}`)
          : mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
            ? 'Katalog Layer Resmi JIGN Bojonegoro'
            : mobileDrawerTab === 'geopark'
              ? 'Warisan Geologi, Hayati & Cagar Alam'
              : `${activeLayers.length} Layer Aktif · Bojonegoro`
      "
      :icon="
        locationBarOpen && clickedCoordinate
          ? 'i-lucide-crosshair'
          : mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
            ? 'i-lucide-database'
            : mobileDrawerTab === 'geopark'
              ? 'i-lucide-mountain-snow'
              : 'i-lucide-layers'
      "
    >
      <!-- Tab Switcher: 3 tab teks murni radius 100% tanpa garis atas/bawah & tombol quick share -->
      <template #tabs>
        <div class="px-2.5 sm:px-3 pt-1 pb-1.5 bg-transparent flex items-center gap-1.5">
          <div class="flex-1 grid grid-cols-3 gap-1 p-1 bg-slate-200/60 dark:bg-white/5 rounded-full border border-slate-200/70 dark:border-white/10" role="tablist" aria-label="Navigasi Panel Mobile">
            <!-- Tab 1: Peta -->
            <button
              type="button"
              role="tab"
              :aria-selected="currentMainTab === 'layers'"
              class="py-2 px-2 rounded-full text-sm sm:text-base font-bold tracking-tight transition-all cursor-pointer min-h-[44px] select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center"
              :class="currentMainTab === 'layers'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="handleMobileTabClick('layer')"
            >
              Peta
            </button>

            <!-- Tab 2: Dataset -->
            <button
              type="button"
              role="tab"
              :aria-selected="currentMainTab === 'dataset'"
              class="py-2 px-2 rounded-full text-sm sm:text-base font-bold tracking-tight transition-all cursor-pointer min-h-[44px] select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center"
              :class="currentMainTab === 'dataset'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="handleMobileTabClick('dataset')"
            >
              Dataset
            </button>

            <!-- Tab 3: Geosite -->
            <button
              type="button"
              role="tab"
              :aria-selected="currentMainTab === 'geopark'"
              class="py-2 px-2 rounded-full text-sm sm:text-base font-bold tracking-tight transition-all cursor-pointer min-h-[44px] select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center"
              :class="currentMainTab === 'geopark'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="handleMobileTabClick('geopark')"
            >
              Geosite
            </button>
          </div>

          <!-- Tombol Cepat Bagikan Peta Mobile -->
          <button
            type="button"
            class="size-11 rounded-full flex items-center justify-center shrink-0 bg-slate-200/60 dark:bg-white/5 hover:bg-blue-50 dark:hover:bg-blue-950/60 text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 border border-slate-200/70 dark:border-white/10 transition-all cursor-pointer min-h-[44px] min-w-[44px] active:scale-95 shadow-2xs"
            title="Bagikan Tautan Peta dengan Pratinjau Thumbnail OG"
            aria-label="Bagikan peta"
            @click="handleOpenShare"
          >
            <UIcon name="i-lucide-share-2" class="size-4" />
          </button>
        </div>
      </template>

      <!-- Content Body: satu LeftPanel instance persisten dengan v-show -->
      <div class="h-full flex-1 flex flex-col min-h-0 overflow-hidden">
        <LeftPanel
          ref="mobileLeftPanelRef"
          :is-mobile-drawer="true"
          :active-layers="activeLayers"
          :active-tab="currentMainTab"
          :selected-kecamatan-filter="selectedKecamatanFilter"
          :clicked-coordinate="clickedCoordinate"
          :location-label="searchedLocationLabel"
          :identified-features="identifiedFeatures"
          :is-identifying="isIdentifyingFeatures"
          :inspector-open="locationBarOpen"
          @tab-change="handleTabChange"
          @open-layer-detail="handleOpenLayerDetail"
          @close-layer-detail="handleCloseLayerDetail"
          @open-geopark-detail="handleOpenGeoparkDetail"
          @close-geopark-detail="handleCloseGeoparkDetail"
          @close="mobileSheetSnap = 'min'"
          @zoom-to-home="handleFilterWilayah(null); mapCanvasRef?.resetView(); mobileSheetSnap = 'min'"
          @select-kecamatan="handleSelectKecamatan"
          @filter-wilayah="handleFilterWilayah"
          @reorder-layers="handleReorderLayers"
          @zoom-to-layer="handleZoomToLayer"
          @toggle-layer-visibility="handleToggleLayerVisibility"
          @update-layer-opacity="handleUpdateLayerOpacity"
          @update-layer-filter="handleUpdateLayerFilter"
          @remove-layer="handleRemoveLayer"
          @add-layer="handleSelectDataset"
          @close-inspector="handleCloseInspector"
          @clear-pin="handleClearPin"
          @select-feature="handleSelectFeature"
          @select-coordinate="handleSelectCoordinate"
          @share="handleOpenShare"
          @search-focus="handleMobileSearchFocus"
          @search-close="handleMobileSearchClose"
        />
      </div>
    </MapMobileBottomSheet>

    <!-- ═══ 6. FITUR BASEMAP: MOBILE BOTTOM SHEET MANDIRI ═══ -->
    <MapBasemapBottomSheet
      v-model:open="isBasemapBottomSheetOpen"
      v-model:current-basemap="currentBasemap"
    />


    <!-- ═══ 10. FITUR BAGIKAN PETA: DESKTOP MODAL & MOBILE BOTTOM SHEET MANDIRI ═══ -->
    <!-- Desktop Modal Dialog -->
    <MapShareModal
      v-if="!isMobileScreen"
      v-model:open="isShareModalOpen"
      :current-basemap="currentBasemap"
      :clicked-coordinate="clickedCoordinate"
      :location-label="searchedLocationLabel"
      :active-layers="activeLayers"
      :map-center="clickedCoordinate || initialCenter"
      :map-zoom="initialZoom"
      :get-map-snapshot="getMapSnapshot"
      :get-map-thumbnail="getMapThumbnail"
    />

    <!-- Mobile Bottom Sheet Mandiri (Tinggi Separuh Layar ~52vh, Expandable ke Full) -->
    <MapShareBottomSheet
      v-if="isMobileScreen"
      v-model:open="isShareModalOpen"
      :current-basemap="currentBasemap"
      :clicked-coordinate="clickedCoordinate"
      :location-label="searchedLocationLabel"
      :active-layers="activeLayers"
      :map-center="clickedCoordinate || initialCenter"
      :map-zoom="initialZoom"
      :get-map-snapshot="getMapSnapshot"
      :get-map-thumbnail="getMapThumbnail"
      @after-close="handleShareSheetClosed"
    />
  </div>
</template>

<style scoped>
/* Full-height sidebar: slide dari kiri saat buka/tutup */
.sidebar-slide-enter-active,
.sidebar-slide-leave-active {
  transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.sidebar-slide-enter-from,
.sidebar-slide-leave-to {
  opacity: 0;
  transform: translateX(-100%);
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

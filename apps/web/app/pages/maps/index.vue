<script setup lang="ts">
import MapCanvas, { type BasemapKey } from "~/components/maps/MapCanvas.vue";
import LeftPanel from "~/components/maps/LeftPanel.vue";
import BasemapPanel from "~/components/maps/BasemapPanel.vue";
import MapBasemapBottomSheet from "~/components/maps/MapBasemapBottomSheet.vue";
import BottomLocationBar from "~/components/maps/BottomLocationBar.vue";
import MapMobileBottomSheet, { type SnapLevel } from "~/components/maps/MapMobileBottomSheet.vue";
import MapShareModal from "~/components/maps/MapShareModal.vue";
import MapShareBottomSheet from "~/components/maps/MapShareBottomSheet.vue";
import type { ActiveLayerItem } from "~/types/map-layers";

definePageMeta({
  layout: false,
  fullBleed: true,
});

useSeoMeta({
  title: "Peta Interaktif",
  description: "Peta spasial interaktif Kabupaten Bojonegoro dengan pencarian lokasi, eksplorasi basemap, dan katalog layer tematik.",
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

// Spatial BBOX Martin Tile Server (gabungan data jalan_porosdesa & infrastruktur_segmen se-Bojonegoro)
const MARTIN_SPATIAL_BBOX: [number, number, number, number] = [
  111.445099, -7.451684, 112.163338, -6.981707
];

// Inisialisasi awal koordinat dari path URL gaya Google Maps atau query bbox
const pathView = parseGoogleMapsPath(route.fullPath || route.path);
const initialCenter = pathView ? pathView.center : null;
const initialZoom = pathView ? pathView.zoom : parseZoomQuery(route.query.zoom || route.query.z);
const initialBbox = pathView ? null : (parseBboxQuery(route.query.bbox) || MARTIN_SPATIAL_BBOX);

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

function handleMapLoaded() {
  if (progressTimer) {
    clearInterval(progressTimer);
    progressTimer = null;
  }
  loadingProgress.value = 100;
  loadingStageText.value = "Peta spasial siap digunakan";
  setTimeout(() => {
    isMapCanvasLoaded.value = true;
  }, 220);
}

// Mobile drawers & screen detection
type MobileDrawerTab = "layer" | "dataset" | "geopark" | "geoportal";
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<MobileDrawerTab>("layer");
const mobileSheetSnap = ref<SnapLevel>("min");
const isMobileScreen = ref(false);

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

async function getMapSnapshot(): Promise<string | null> {
  if (!mapCanvasRef.value) return null;
  return mapCanvasRef.value.getMapSnapshot();
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
    if (pv && (searchLabel || route.query.pin)) {
      clickedCoordinate.value = pv.center;
      searchedLocationLabel.value = searchLabel;
      mapCanvasRef.value?.flyTo(pv.center, pv.zoom || 14);
      locationBarOpen.value = true;
      mobileDrawerOpen.value = false;
    }

    // Filter layer MVT jika terdapat parameter kecamatan atau nama pencarian kecamatan
    const targetKecParam = typeof route.query.kecamatan === "string" && route.query.kecamatan.trim()
      ? route.query.kecamatan.trim()
      : (searchLabel && /^kecamatan\s+/i.test(searchLabel) ? searchLabel.replace(/^kecamatan\s+/i, "").replace(/\s*\(kota\)$/i, "").trim() : null);

    if (targetKecParam) {
      handleFilterWilayah(targetKecParam);
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

  // Filter layer MVT pada inisialisasi jika query kecamatan tersedia
  const initialKec = typeof route.query.kecamatan === "string" && route.query.kecamatan.trim()
    ? route.query.kecamatan.trim()
    : (searchedLocationLabel.value && /^kecamatan\s+/i.test(searchedLocationLabel.value)
        ? searchedLocationLabel.value.replace(/^kecamatan\s+/i, "").replace(/\s*\(kota\)$/i, "").trim()
        : null);
  if (initialKec) {
    handleFilterWilayah(initialKec);
  }

  // Pastikan fitbound by data spasial Martin saat pertama kali dibuka (tanpa url search/pin/coord)
  if (!pathView && !route.query.bbox && !hasExplicitSearchOrPin) {
    try {
      const [segmenRes, jalanRes] = await Promise.allSettled([
        $fetch<any>("/martin/infrastruktur_segmen"),
        $fetch<any>("/martin/jalan_porosdesa"),
      ]);
      const b1 = segmenRes.status === "fulfilled" ? segmenRes.value?.bounds : null;
      const b2 = jalanRes.status === "fulfilled" ? jalanRes.value?.bounds : null;
      let targetBbox = MARTIN_SPATIAL_BBOX;
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
      nextTick(() => {
        mapCanvasRef.value?.fitBbox(targetBbox, 15, 600);
      });
    } catch {
      nextTick(() => {
        mapCanvasRef.value?.fitBbox(MARTIN_SPATIAL_BBOX, 15, 600);
      });
    }
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
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    // Mobile: buka bottom sheet di tab layer ke posisi peek (50% vh) agar hasil identifikasi fitur langsung tampak
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  } else {
    // Desktop: pastikan panel kiri terbuka
    leftPanelOpen.value = true;
  }
}

function handleSelectLocation(payload: { name: string; coordinate: [number, number]; zoom?: number }) {
  clickedCoordinate.value = payload.coordinate;
  searchedLocationLabel.value = payload.name;
  mapCanvasRef.value?.flyTo(payload.coordinate, payload.zoom ?? 14);
  locationBarOpen.value = true;
  if (typeof window !== "undefined" && window.innerWidth < 768) {
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  } else {
    leftPanelOpen.value = true;
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

function handleSelectKecamatan(payload: { name: string; coordinate: [number, number]; zoom?: number }) {
  mapCanvasRef.value?.flyTo(payload.coordinate, payload.zoom ?? 13.5);
  // Simpan koordinat dan label wilayah tanpa membuka paksa inspector agar SearchItemAboutView tetap aktif
  clickedCoordinate.value = payload.coordinate;
  searchedLocationLabel.value = payload.name;
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

function handleClearPin() {
  clickedCoordinate.value = null;
  searchedLocationLabel.value = null;
  identifiedFeatures.value = [];
  locationBarOpen.value = false;
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

function openMobileTab(tab: MobileDrawerTab) {
  mobileDrawerTab.value = tab;
  mobileSheetSnap.value = "peek";
  mobileDrawerOpen.value = true;
}

function handleMobileSearchFocus() {
  if (mobileDrawerOpen.value && mobileSheetSnap.value === "min") {
    mobileSheetSnap.value = "peek";
  }
}

function handleMobileSearchClose() {
  if (mobileDrawerOpen.value && !locationBarOpen.value && mobileSheetSnap.value === "peek") {
    mobileSheetSnap.value = "min";
  }
}

function toggleLeftPanel() {
  leftPanelOpen.value = !leftPanelOpen.value;
}

function handleZoomToLayer(layer: ActiveLayerItem) {
  if (layer.coordinates) {
    clickedCoordinate.value = layer.coordinates;
    searchedLocationLabel.value = layer.title;
    mapCanvasRef.value?.flyTo(layer.coordinates, layer.zoom || 12);
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
}

function handleFilterWilayah(kecamatanName: string | null) {
  selectedKecamatanFilter.value = kecamatanName;
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
</script>

<template>
  <div
    class="wrapper-maps flex w-full h-full relative overflow-hidden bg-gray-100 dark:bg-[#070b14] select-none font-sans antialiased text-gray-900 dark:text-gray-100"
  >
    <!-- Panel Kiri: Desktop Split (lebar standar 400px, responsif 350px pada tablet) -->
    <aside
      class="hidden md:flex flex-col w-[350px] lg:w-[400px] shrink-0 h-full relative z-20 bg-white dark:bg-[#09111e] border-r border-slate-200 dark:border-white/8 shadow-lg overflow-hidden transition-[width] duration-200 ease-out"
      aria-label="Panel Navigasi Spasial Kiri"
    >
      <LeftPanel
        :active-layers="activeLayers"
        :selected-kecamatan-filter="selectedKecamatanFilter"
        :clicked-coordinate="clickedCoordinate"
        :location-label="searchedLocationLabel"
        :identified-features="identifiedFeatures"
        :is-identifying="isIdentifyingFeatures"
        :inspector-open="locationBarOpen"
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
        @share="handleOpenShare"
      />
    </aside>

    <!-- Panel Kanan: Map Canvas Container (mengisi sisa layar penuh) -->
    <main
      class="flex-1 h-full min-w-0 relative overflow-hidden bg-gray-100 dark:bg-[#070b14]"
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
          @map-click="handleMapClick"
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

      <!-- Floating Basemap Panel (Kiri Bawah di dalam Kanvas Peta) -->
      <div
        class="absolute z-20 pointer-events-auto select-none flex flex-col items-start gap-2 transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] left-3 sm:left-4"
        :class="[
          mobileDrawerOpen && mobileSheetSnap === 'full'
            ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
            : mobileDrawerOpen && mobileSheetSnap === 'peek'
              ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4'
              : mobileDrawerOpen && mobileSheetSnap === 'min'
                ? 'bottom-[calc(200px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
                : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100 pointer-events-auto'
        ]"
      >
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
      :title="
        mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
          ? 'Katalog Dataset'
          : mobileDrawerTab === 'geopark'
            ? 'Geopark Bojonegoro'
            : locationBarOpen && clickedCoordinate
              ? 'Identifikasi Fitur'
              : 'Panel Lapisan Peta'
      "
      :subtitle="
        mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
          ? 'Katalog Layer Resmi JIGN Bojonegoro'
          : mobileDrawerTab === 'geopark'
            ? 'Warisan Geologi, Hayati & Cagar Alam'
            : locationBarOpen && clickedCoordinate
              ? (searchedLocationLabel || `${clickedCoordinate[1].toFixed(5)}, ${clickedCoordinate[0].toFixed(5)}`)
              : `${activeLayers.length} Layer Aktif · Bojonegoro`
      "
      :icon="
        mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
          ? 'i-lucide-database'
          : mobileDrawerTab === 'geopark'
            ? 'i-lucide-mountain-snow'
            : locationBarOpen
              ? 'i-lucide-crosshair'
              : 'i-lucide-layers'
      "
    >
      <!-- Tab Switcher: 3 tab teks murni radius 100% tanpa garis atas/bawah -->
      <template #tabs>
        <div class="px-2.5 sm:px-3 pt-2 pb-1.5 bg-slate-50/70 dark:bg-[#070b14]/50">
          <div class="grid grid-cols-3 gap-1 p-1 bg-slate-200/60 dark:bg-white/5 rounded-full border border-slate-200/70 dark:border-white/10" role="tablist" aria-label="Navigasi Panel Mobile">
            <!-- Tab 1: Peta -->
            <button
              type="button"
              role="tab"
              :aria-selected="mobileDrawerTab === 'layer'"
              class="py-2 px-2 rounded-full text-xs sm:text-sm font-semibold transition-all cursor-pointer min-h-[44px] select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center"
              :class="mobileDrawerTab === 'layer'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'layer'; if (mobileSheetSnap === 'min') mobileSheetSnap = 'peek'"
            >
              Peta
            </button>

            <!-- Tab 2: Dataset -->
            <button
              type="button"
              role="tab"
              :aria-selected="mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'"
              class="py-2 px-2 rounded-full text-xs sm:text-sm font-semibold transition-all cursor-pointer min-h-[44px] select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center"
              :class="mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'dataset'; if (mobileSheetSnap === 'min') mobileSheetSnap = 'peek'"
            >
              Dataset
            </button>

            <!-- Tab 3: Geopark -->
            <button
              type="button"
              role="tab"
              :aria-selected="mobileDrawerTab === 'geopark'"
              class="py-2 px-2 rounded-full text-xs sm:text-sm font-semibold transition-all cursor-pointer min-h-[44px] select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center"
              :class="mobileDrawerTab === 'geopark'
                ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="mobileDrawerTab = 'geopark'; if (mobileSheetSnap === 'min') mobileSheetSnap = 'peek'"
            >
              Geopark
            </button>
          </div>
        </div>
      </template>

      <!-- Content Body: satu LeftPanel instance persisten dengan v-show -->
      <div class="min-h-full flex flex-col min-h-0">
        <LeftPanel
          :is-mobile-drawer="true"
          :active-layers="activeLayers"
          :active-tab="mobileDrawerTab === 'geopark' ? 'geopark' : (mobileDrawerTab === 'dataset' || mobileDrawerTab === 'geoportal') ? 'dataset' : 'layers'"
          :selected-kecamatan-filter="selectedKecamatanFilter"
          :clicked-coordinate="clickedCoordinate"
          :location-label="searchedLocationLabel"
          :identified-features="identifiedFeatures"
          :is-identifying="isIdentifyingFeatures"
          :inspector-open="locationBarOpen"
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

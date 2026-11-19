<script setup lang="ts">
import MapCanvas, { type BasemapKey } from "~/components/maps/MapCanvas.vue";
import LeftPanel from "~/components/maps/LeftPanel.vue";
import { KECAMATAN_ENTRIES, type KecamatanEntry } from "~/constants/kecamatan";
import BasemapPanel from "~/components/maps/BasemapPanel.vue";
import MapBasemapBottomSheet from "~/components/maps/MapBasemapBottomSheet.vue";
import MapMobileBottomSheet, { type SnapLevel } from "~/components/maps/MapMobileBottomSheet.vue";
import type { ActiveLayerItem, MainTab } from "~/types/map-layers";
import { normalizeBasemapAlias } from "~/types/basemap";
import { useBasemapStore } from "~/stores/basemap";
import { useGeoparkSites, type GeoparkSite } from "~/composables/useGeoparkSites";
import type { RekapRuasItem } from "~/types/rekap-jalan";
import { parseCoordinateString } from "~/utils/gis-helpers";
import { normalizeWilayahKey } from "~/composables/useMapRekapOverview";
import { useRekapJalanApi } from "~/composables/useRekapJalanApi";
import { $http } from "~/utils/helpers";

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
const mobileLeftPanelRef = ref<any>(null);
const desktopLeftPanelRef = ref<any>(null);

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

// Spatial BBOX Kabupaten Bojonegoro (union 28 kecamatan & batas data spasial Martin)
const MARTIN_SPATIAL_BBOX: [number, number, number, number] = [
  111.42351, -7.46964, 112.16569, -6.981707
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

// Inisialisasi basemap dinamis dari database, parameter URL, atau localStorage
const basemapStore = useBasemapStore();

function getInitialBasemap(): BasemapKey {
  const queryParam = typeof route.query.basemap === "string" ? route.query.basemap.trim() : null;
  if (queryParam) {
    return normalizeBasemapAlias(queryParam) as BasemapKey;
  }
  if (import.meta.client) {
    try {
      const saved = localStorage.getItem("melarosa_active_basemap");
      if (saved && saved.trim()) {
        return normalizeBasemapAlias(saved.trim()) as BasemapKey;
      }
    } catch {}
  }
  return (basemapStore.defaultKode || "esri") as BasemapKey;
}

const currentBasemap = useState<BasemapKey>("melarosa_active_basemap", () => getInitialBasemap());

// ─── API Integration (Layers Stored in State, Hidden from LeftPanel) ───────────
const { data: layersData } = useHttp<any>("layers", {
  key: "melarosa-map-layers-catalog",
  lazy: true,
  dedupe: "defer",
  getCachedData: (key, nuxtApp) => {
    if (!nuxtApp.isHydrating) return undefined;
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
const leftPanelOpen = ref(import.meta.client ? window.innerWidth >= 768 : false);
const locationBarOpen = ref(hasExplicitSearchOrPin);
const selectedKecamatanFilter = ref<string | null>(null);
const selectedDesaFilter = ref<string | null>(null);
const selectedStatusFilter = ref<string | null>(null);
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
// Transisi masuk Floating Left Panel (muncul anggun setelah navbar meregang ke kiri & kanan)
const isLeftPanelMounted = ref(false);

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
type MobileDrawerTab = "layer" | "infrastruktur" | "geopark" | "dataset";
const mobileDrawerOpen = ref(false);
const mobileDrawerTab = ref<MobileDrawerTab>("layer");
const mobileSheetSnap = ref<SnapLevel>("min");
const isMobileScreen = ref(false);
const previousTabBeforeIdentify = ref<MainTab | null>(null);
const showGeoparkOverlay = ref(false);

function isRoadLayerItem(layer: ActiveLayerItem): boolean {
  const hay = `${layer.id || ""} ${layer.layer_name || ""} ${layer.martinSource || ""} ${layer.title || ""} ${layer.name || ""}`.toLowerCase();
  return hay.includes("jalan") || hay.includes("infrastruktur_segmen");
}

function handleToggleRoadsVisibility() {
  const roadLayers = activeLayers.value.filter(isRoadLayerItem);
  if (roadLayers.length === 0) return;
  const anyVisible = roadLayers.some((l) => l.visible !== false);
  const nextVisible = !anyVisible;
  activeLayers.value = activeLayers.value.map((l) =>
    isRoadLayerItem(l) ? { ...l, visible: nextVisible } : l
  );
}

function handleToggleGeoparkOverlay() {
  showGeoparkOverlay.value = !showGeoparkOverlay.value;
}

function handleLocateUser() {
  mapCanvasRef.value?.locateUser?.();
  if (isMobileScreen.value && mobileSheetSnap.value !== "min") {
    mobileSheetSnap.value = "min";
  }
}

function collectGeoJsonPoints(coords: any, out: [number, number][]) {
  if (!Array.isArray(coords)) return;
  if (
    coords.length >= 2 &&
    typeof coords[0] === "number" &&
    typeof coords[1] === "number" &&
    Number.isFinite(coords[0]) &&
    Number.isFinite(coords[1])
  ) {
    out.push([coords[0], coords[1]]);
    return;
  }
  for (const item of coords) {
    collectGeoJsonPoints(item, out);
  }
}

function extractGeoJsonBounds(geom?: { type?: string; coordinates?: any }): {
  coordinate?: [number, number];
  extent?: [number, number, number, number];
} {
  if (!geom || !geom.coordinates) return {};
  const points: [number, number][] = [];
  collectGeoJsonPoints(geom.coordinates, points);
  const first = points[0];
  if (!first) return {};

  let minX = first[0];
  let minY = first[1];
  let maxX = first[0];
  let maxY = first[1];
  for (const [x, y] of points) {
    if (x < minX) minX = x;
    if (y < minY) minY = y;
    if (x > maxX) maxX = x;
    if (y > maxY) maxY = y;
  }
  if (minX === maxX) {
    minX -= 0.001;
    maxX += 0.001;
  }
  if (minY === maxY) {
    minY -= 0.001;
    maxY += 0.001;
  }
  const midpoint = points[Math.floor(points.length / 2)] || first;
  return {
    coordinate: midpoint,
    extent: [minX, minY, maxX, maxY],
  };
}

function formatRoadMeters(meters?: number): string {
  const m = Number(meters || 0);
  if (m >= 1000) {
    return `${(m / 1000).toLocaleString("id-ID", { maximumFractionDigits: 2 })} km`;
  }
  return `${Math.round(m).toLocaleString("id-ID")} m`;
}

let roadSelectRequestSeq = 0;
let lastRoadZoomedId: string | null = null;
let lastRoadZoomedAt = 0;

function mergeGeoJsonLines(geoms: Array<{ type?: string; coordinates?: any } | null | undefined>) {
  const lines: number[][][] = [];
  for (const g of geoms) {
    if (!g || !g.coordinates) continue;
    if (g.type === "LineString" && Array.isArray(g.coordinates) && g.coordinates.length >= 2) {
      lines.push(g.coordinates);
    } else if (g.type === "MultiLineString" && Array.isArray(g.coordinates)) {
      for (const part of g.coordinates) {
        if (Array.isArray(part) && part.length >= 2) {
          lines.push(part);
        }
      }
    }
  }
  if (lines.length === 0) return null;
  if (lines.length === 1) {
    return { type: "LineString", coordinates: lines[0] };
  }
  return { type: "MultiLineString", coordinates: lines };
}

function isPorosDesaLayerItem(layer: ActiveLayerItem): boolean {
  const src = String(layer.martinSource || layer.layer_name || "").toLowerCase();
  const title = String(layer.title || layer.name || "").toLowerCase();
  return (
    src === "jalan_porosdesa" ||
    layer.id === "01a1589c-8bf1-7294-9cf7-41ffda7e71f6" ||
    title.includes("poros")
  );
}

function ensurePorosRoadLayerVisible() {
  const porosHidden = activeLayers.value.some((l) => isPorosDesaLayerItem(l) && l.visible === false);
  if (porosHidden) {
    activeLayers.value = activeLayers.value.map((l) =>
      isPorosDesaLayerItem(l) ? { ...l, visible: true } : l
    );
    return;
  }
  const hasAnyRoadVisible = activeLayers.value.some((l) => isRoadLayerItem(l) && l.visible !== false);
  if (!hasAnyRoadVisible && activeLayers.value.some(isRoadLayerItem)) {
    activeLayers.value = activeLayers.value.map((l) =>
      isRoadLayerItem(l) ? { ...l, visible: true } : l
    );
  }
}

async function fetchRoadCenterlineGeometry(
  ruas: RekapRuasItem,
  desaName: string,
  kecamatanName: string
): Promise<{ type: string; coordinates: any } | null> {
  const queryParams: Record<string, string> = {
    layer_name: "jalan_porosdesa",
  };
  if (ruas.id !== undefined && ruas.id !== null && String(ruas.id).trim() !== "") {
    queryParams.feature_id = String(ruas.id).trim();
  }
  if (ruas.kode_ruas !== undefined && ruas.kode_ruas !== null && String(ruas.kode_ruas).trim() !== "") {
    queryParams.kode_ruas = String(ruas.kode_ruas).trim();
  }
  if (ruas.nama_ruas) {
    queryParams.namobj = ruas.nama_ruas;
  }
  if (desaName) {
    queryParams.desa = desaName;
  }
  if (kecamatanName) {
    queryParams.kecamatan = kecamatanName;
  }

  try {
    const res: any = await $http("layers/jalan_porosdesa/feature-geometry", {
      query: queryParams,
    });
    if (res?.ok && res?.geometry?.coordinates) {
      return res.geometry;
    }
  } catch {
    // Fallback to remote rekap API host or segments/MVT below
  }

  try {
    const config = useRuntimeConfig();
    const rekapBase = String(config.public?.rekapApiUrl || "").replace(/\/$/, "");
    if (rekapBase) {
      const qs = new URLSearchParams(queryParams).toString();
      const res: any = await $fetch(`${rekapBase}/layers/jalan_porosdesa/feature-geometry?${qs}`);
      if (res?.ok && res?.geometry?.coordinates) {
        return res.geometry;
      }
    }
  } catch {
    // Remote host may not expose layers endpoint
  }

  return null;
}

async function fetchSegmentsMergedGeometry(ruasId: string | number): Promise<{ type: string; coordinates: any } | null> {
  try {
    const rekapApi = useRekapJalanApi();
    const segments = await rekapApi.fetchDetailSegmen(ruasId);
    if (!Array.isArray(segments) || segments.length === 0) return null;
    return mergeGeoJsonLines(segments.map((s) => s.geom));
  } catch {
    return null;
  }
}

async function handleSelectRoadFromExplorer(payload: {
  ruas: RekapRuasItem;
  desaName: string;
  kecamatanName: string;
}) {
  const { ruas, desaName, kecamatanName } = payload;
  const requestId = ++roadSelectRequestSeq;

  const targetKecKey = normalizeWilayahKey(kecamatanName);
  const matchedKec = KECAMATAN_ENTRIES.find(
    (k) => normalizeWilayahKey(k.name) === targetKecKey
  );

  ensurePorosRoadLayerVisible();
  const porosLayer = activeLayers.value.find(isPorosDesaLayerItem);

  const featureProps: Record<string, any> = {
    nama_ruas: ruas.nama_ruas,
    desa: desaName,
    kecamatan: kecamatanName,
    panjang_ruas: formatRoadMeters(ruas.panjang_master || ruas.panjang),
    sudah_dibangun: formatRoadMeters(ruas.panjang_dibangun),
    capaian: `${Math.round(ruas.progress || 0)}%`,
  };
  if (ruas.kode_ruas !== undefined && ruas.kode_ruas !== null && String(ruas.kode_ruas) !== "") {
    featureProps.kode_ruas = ruas.kode_ruas;
  }
  if (ruas.kondisi || ruas.status_kondisi) {
    featureProps.kondisi = ruas.kondisi || ruas.status_kondisi;
  }
  if (ruas.atribut?.jenis_perkerasan) {
    featureProps.jenis_perkerasan = ruas.atribut.jenis_perkerasan;
  }
  if (ruas.tahun_pembangunan) {
    featureProps.tahun_pembangunan = ruas.tahun_pembangunan;
  }
  if (ruas.sumber_dana) {
    featureProps.sumber_dana = ruas.sumber_dana;
  }

  if (currentMainTab.value !== "layers") {
    previousTabBeforeIdentify.value = currentMainTab.value;
  }

  mapCanvasRef.value?.renderSelectedFeature?.(null);
  isIdentifyingFeatures.value = true;
  clickedCoordinate.value = matchedKec?.coordinate ?? null;
  searchedLocationLabel.value = ruas.nama_ruas;
  identifiedFeatures.value = [];
  locationBarOpen.value = true;

  handleTabChange("layers");

  if (isMobileScreen.value) {
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  } else {
    leftPanelOpen.value = true;
  }

  let resolvedGeoJson: any = ruas.geom?.coordinates ? ruas.geom : null;
  let mvtMatch = !resolvedGeoJson
    ? mapCanvasRef.value?.findFeatureGeometryInMvt?.({
        id: ruas.id,
        kodeRuas: ruas.kode_ruas,
        namaRuas: ruas.nama_ruas,
        desaName,
        kecamatanName,
      })
    : null;

  if (!resolvedGeoJson && mvtMatch?.geojsonGeometry) {
    resolvedGeoJson = mvtMatch.geojsonGeometry;
  }

  if (!resolvedGeoJson) {
    const [centerlineRes, segmentsRes] = await Promise.allSettled([
      fetchRoadCenterlineGeometry(ruas, desaName, kecamatanName),
      fetchSegmentsMergedGeometry(ruas.id),
    ]);

    if (requestId !== roadSelectRequestSeq || !locationBarOpen.value) return;

    const centerlineGeom = centerlineRes.status === "fulfilled" ? centerlineRes.value : null;
    const segmentsGeom = segmentsRes.status === "fulfilled" ? segmentsRes.value : null;
    resolvedGeoJson = centerlineGeom || segmentsGeom || null;

    if (!resolvedGeoJson) {
      mvtMatch = mapCanvasRef.value?.findFeatureGeometryInMvt?.({
        id: ruas.id,
        kodeRuas: ruas.kode_ruas,
        namaRuas: ruas.nama_ruas,
        desaName,
        kecamatanName,
      });
      if (mvtMatch?.geojsonGeometry) {
        resolvedGeoJson = mvtMatch.geojsonGeometry;
      }
    }
  }

  if (requestId !== roadSelectRequestSeq || !locationBarOpen.value) return;

  const bounds = extractGeoJsonBounds(resolvedGeoJson);
  const resolvedExtent = bounds.extent || mvtMatch?.extent;
  const targetCoordinate = bounds.coordinate || mvtMatch?.coordinate || matchedKec?.coordinate;

  if (!resolvedGeoJson) {
    featureProps.lokasi_peta = matchedKec
      ? `Menampilkan wilayah Kecamatan ${matchedKec.name}`
      : "Koordinat rinci ruas belum tersedia";
  }

  const featId = `ruas-${ruas.id}`;
  const resolvedPorosLayerName = porosLayer?.layer_name || porosLayer?.martinSource || "jalan_porosdesa";
  const rawRoadId =
    ruas.id !== undefined && ruas.id !== null && String(ruas.id).trim() !== "" ? String(ruas.id).trim() : null;
  const feat: IdentifiedFeature = {
    id: featId,
    rawFeatureId: rawRoadId,
    layerId: mvtMatch?.layerId || porosLayer?.id || "jalan_porosdesa",
    layerName: resolvedPorosLayerName,
    selectionKey: rawRoadId ? `${resolvedPorosLayerName.toLowerCase()}::${rawRoadId}` : null,
    layerTitle: porosLayer?.title || "Jalan Desa",
    layerInstansi: porosLayer?.instansi || "Pemerintah Kabupaten Bojonegoro",
    geometryType: resolvedGeoJson?.type || "LineString",
    properties: featureProps,
    coordinate: targetCoordinate,
    extent: resolvedExtent || matchedKec?.bbox,
    geojsonGeometry: resolvedGeoJson || undefined,
  };
  if (resolvedGeoJson) {
    (feat as any)._hasFullBackendGeometry = true;
  }

  lastRoadZoomedId = featId;
  lastRoadZoomedAt = Date.now();
  isIdentifyingFeatures.value = false;
  clickedCoordinate.value = targetCoordinate ?? null;
  identifiedFeatures.value = [feat];
  mapCanvasRef.value?.renderSelectedFeature?.(feat);

  if (resolvedExtent) {
    mapCanvasRef.value?.fitFeatureExtent?.(
      resolvedExtent,
      17,
      550,
      isMobileScreen.value ? "peek" : undefined
    );
  } else if (matchedKec?.bbox) {
    mapCanvasRef.value?.fitBbox?.(
      matchedKec.bbox,
      14,
      500,
      isMobileScreen.value ? "peek" : undefined
    );
    // Vector tiles for this kecamatan may finish loading shortly after fitBbox
    setTimeout(() => {
      if (requestId !== roadSelectRequestSeq || !locationBarOpen.value) return;
      const delayedMvt = mapCanvasRef.value?.findFeatureGeometryInMvt?.({
        id: ruas.id,
        kodeRuas: ruas.kode_ruas,
        namaRuas: ruas.nama_ruas,
        desaName,
        kecamatanName,
      });
      if (!delayedMvt?.geojsonGeometry) return;

      const delayedBounds = extractGeoJsonBounds(delayedMvt.geojsonGeometry);
      const nextProps = { ...featureProps };
      delete nextProps.lokasi_peta;
      const upgradedFeat: IdentifiedFeature = {
        ...feat,
        layerId: delayedMvt.layerId || feat.layerId,
        geometryType: delayedMvt.geojsonGeometry.type || feat.geometryType,
        properties: nextProps,
        coordinate: delayedBounds.coordinate || delayedMvt.coordinate || targetCoordinate,
        extent: delayedBounds.extent || delayedMvt.extent || matchedKec.bbox,
        geojsonGeometry: delayedMvt.geojsonGeometry,
      };
      (upgradedFeat as any)._hasFullBackendGeometry = true;
      lastRoadZoomedId = upgradedFeat.id || null;
      lastRoadZoomedAt = Date.now();
      clickedCoordinate.value = upgradedFeat.coordinate ?? null;
      identifiedFeatures.value = [upgradedFeat];
      mapCanvasRef.value?.renderSelectedFeature?.(upgradedFeat);
      const zoomExtent = delayedBounds.extent || delayedMvt.extent;
      if (zoomExtent) {
        mapCanvasRef.value?.fitFeatureExtent?.(
          zoomExtent,
          17,
          550,
          isMobileScreen.value ? "peek" : undefined
        );
      }
    }, 850);
  } else if (targetCoordinate) {
    mapCanvasRef.value?.fitCoordinateWithPadding?.(
      targetCoordinate,
      15,
      500,
      isMobileScreen.value ? "peek" : undefined
    );
  }
}

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

const pendingOpenLayersMenu = ref(false);

function handleOpenLayersMenu() {
  const mobile = typeof window !== "undefined" ? window.innerWidth < 768 : isMobileScreen.value;
  if (mobile) {
    mobileDrawerTab.value = "layer";
    mobileDrawerOpen.value = true;
    if (mobileSheetSnap.value === "min") {
      mobileSheetSnap.value = "peek";
    }
    if (!mobileLeftPanelRef.value?.openOtherLayers) {
      pendingOpenLayersMenu.value = true;
    }
    nextTick(() => {
      if (mobileLeftPanelRef.value?.openOtherLayers) {
        mobileLeftPanelRef.value.openOtherLayers();
        pendingOpenLayersMenu.value = false;
      }
    });
  } else {
    leftPanelOpen.value = true;
    if (!desktopLeftPanelRef.value?.openOtherLayers) {
      pendingOpenLayersMenu.value = true;
    }
    nextTick(() => {
      if (desktopLeftPanelRef.value?.openOtherLayers) {
        desktopLeftPanelRef.value.openOtherLayers();
        pendingOpenLayersMenu.value = false;
      }
    });
  }
}

const activeMobileTabIndex = computed(() => {
  if (currentMainTab.value === "infrastruktur" || currentMainTab.value === "dataset") return 1;
  if (currentMainTab.value === "geopark") return 2;
  return 0;
});

function handleToggleLayersMenu() {
  const mobile = typeof window !== "undefined" ? window.innerWidth < 768 : isMobileScreen.value;
  if (mobile) {
    const isLayerViewOpen =
      Boolean(mobileLeftPanelRef.value?.isOtherLayersOpen?.()) &&
      mobileDrawerOpen.value &&
      mobileSheetSnap.value !== "min";

    if (isLayerViewOpen) {
      pendingOpenLayersMenu.value = false;
      mobileLeftPanelRef.value?.closeOtherLayers?.();
      mobileDrawerTab.value = "layer";
      mobileSheetSnap.value = "min";
      return;
    }

    handleOpenLayersMenu();
    return;
  }

  const isDesktopLayerViewOpen =
    Boolean(desktopLeftPanelRef.value?.isOtherLayersOpen?.()) && leftPanelOpen.value;

  if (isDesktopLayerViewOpen) {
    pendingOpenLayersMenu.value = false;
    desktopLeftPanelRef.value?.closeOtherLayers?.();
    return;
  }

  handleOpenLayersMenu();
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
function parseLayerOrder(item: any): number {
  if (item?.order !== null && item?.order !== undefined && item?.order !== "") {
    const numeric = Number(item.order);
    if (!Number.isNaN(numeric)) return numeric;
  }
  if (item?.layer_name === "infrastruktur_segmen") return 5;
  if (item?.layer_name === "bataswilayah_kabupaten") return 1;
  return 2;
}

function withNormalizedStackOrder(layers: ActiveLayerItem[]): ActiveLayerItem[] {
  const total = layers.length;
  return layers.map((layer, idx) => ({
    ...layer,
    order: total - idx,
  }));
}

function mapLayerItemToActive(item: any): ActiveLayerItem {
  const isMvt =
    (item.protocol || "").toUpperCase() === "MVT" ||
    item.source_type === "martin" ||
    (item.url && item.url.includes("/martin/")) ||
    (item.layer_name && ["infrastruktur_segmen", "jalan_porosdesa", "jalan_kabupaten_luarkota", "bataswilayah_kabupaten"].includes(item.layer_name));

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
    order: parseLayerOrder(item),
    wmsUrl: isMvt ? undefined : (item.wmsUrl || item.url),
    wmsLayerName: isMvt ? undefined : (item.wmsLayerName || item.layer_name),
    mvtUrl: isMvt ? (item.mvtUrl || item.url) : undefined,
    martinSource: isMvt ? (detectedMartinSource || (isKabupaten ? "bataswilayah_kabupaten" : isSegmen ? "infrastruktur_segmen" : "jalan_porosdesa")) : undefined,
    style_config: item.style_config || null,
    expanded: Boolean(item.expanded),
    activeTool: item.activeTool || "none",
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
    order: 5,
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
    order: 3,
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
    order: 1,
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
    // Standar GIS panel (QGIS/ArcGIS): reverse dari order ASC sehingga order terbesar (lapisan teratas peta)
    // berada di index 0 (paling atas daftar) dan order terkecil (lapisan dasar) berada di paling bawah daftar.
    defaultVisibleItems.sort((a, b) => {
      const orderA = parseLayerOrder(a);
      const orderB = parseLayerOrder(b);
      if (orderB !== orderA) return orderB - orderA;
      return String(a.name || a.title || "").localeCompare(String(b.name || b.title || ""), "id");
    });

    activeLayers.value = withNormalizedStackOrder(defaultVisibleItems.map(mapLayerItemToActive));
    hasInitializedLayersFromApi.value = true;
  } else {
    activeLayers.value = activeLayers.value.map((fb) => {
      const matched = list.find(
        (item: any) =>
          item.layer_name === fb.layer_name ||
          (fb.martinSource && item.layer_name === fb.martinSource),
      );
      if (!matched) return fb;
      const resolved = mapLayerItemToActive(matched);
      return {
        ...resolved,
        visible: fb.visible,
        opacity: fb.opacity,
        order: fb.order ?? resolved.order,
      };
    });
    hasInitializedLayersFromApi.value = true;
  }

  if (selectedKecamatanFilter.value || selectedDesaFilter.value || selectedStatusFilter.value) {
    applyMvtFiltersToActiveLayers();
  }
}

const { allLayers: geoportalCatalogLayers, toActiveLayerItem } = useGeoportalLayers();
const pendingWebgisDataset = usePendingWebgisDataset();
const appliedDatasetQueryKey = ref<string | null>(null);
const fittedDatasetBboxKey = ref<string | null>(null);

function matchesDatasetQuery(item: any, datasetParam: string, layerNameParam: string): boolean {
  if (!item) return false;
  const itemId = String(item.id || "").trim();
  const itemGeoId = String(item.geoportal_id || "").trim();
  const itemLayerName = String(item.layer_name || item.wmsLayerName || "")
    .trim()
    .toLowerCase();
  const dsLower = datasetParam.toLowerCase();
  const lnLower = layerNameParam.toLowerCase();

  if (datasetParam && ((itemId && itemId === datasetParam) || (itemGeoId && itemGeoId === datasetParam))) {
    return true;
  }
  if (dsLower && itemLayerName && itemLayerName === dsLower) {
    return true;
  }
  if (lnLower && itemLayerName && itemLayerName === lnLower) {
    return true;
  }
  return false;
}

function fitDatasetBboxIfAvailable(targetKey: string, source: any) {
  if (!source || fittedDatasetBboxKey.value === targetKey || pathView) return;
  const bbox =
    source.pycsw?.iso_19139?.bbox ||
    source.pycsw?.dublin_core?.bbox ||
    source.csw_metadata?.iso_19139?.bbox ||
    source.csw_metadata?.dublin_core?.bbox ||
    source.bbox;
  if (bbox && bbox.west != null && bbox.south != null && bbox.east != null && bbox.north != null) {
    fittedDatasetBboxKey.value = targetKey;
    nextTick(() => {
      mapCanvasRef.value?.fitBbox(
        [Number(bbox.west), Number(bbox.south), Number(bbox.east), Number(bbox.north)],
        13,
        500,
        isMobileScreen.value ? "peek" : undefined
      );
    });
  }
}

function applyDatasetQueryToMap() {
  const datasetParam = typeof route.query.dataset === "string" ? route.query.dataset.trim() : "";
  const layerNameParam = typeof route.query.layer_name === "string" ? route.query.layer_name.trim() : "";
  const targetKey = datasetParam || layerNameParam;
  if (!targetKey) {
    pendingWebgisDataset.value = null;
    appliedDatasetQueryKey.value = null;
    fittedDatasetBboxKey.value = null;
    return;
  }

  const rawFeedList: any[] = layersData.value?.data || (Array.isArray(layersData.value) ? layersData.value : []);
  const matchedFromApi = rawFeedList.find((item: any) =>
    matchesDatasetQuery(item, datasetParam, layerNameParam)
  );
  const matchedGeoportal = geoportalCatalogLayers.value.find((item) =>
    matchesDatasetQuery(item, datasetParam, layerNameParam)
  );
  const matchedPending = matchesDatasetQuery(pendingWebgisDataset.value, datasetParam, layerNameParam)
    ? pendingWebgisDataset.value
    : null;
  const geoCandidate = matchedGeoportal || matchedPending;

  if (appliedDatasetQueryKey.value === targetKey) {
    fitDatasetBboxIfAvailable(targetKey, geoCandidate || matchedFromApi);
    return;
  }

  const existing = activeLayers.value.find((l) =>
    matchesDatasetQuery(l, datasetParam, layerNameParam)
  );

  if (existing) {
    const rest = activeLayers.value.filter((l) => l.id !== existing.id);
    activeLayers.value = withNormalizedStackOrder([
      { ...existing, visible: true, expanded: true },
      ...rest,
    ]);
    appliedDatasetQueryKey.value = targetKey;
    handleOpenLayersMenu();
    fitDatasetBboxIfAvailable(targetKey, geoCandidate || matchedFromApi || existing);
    if (hasInitializedLayersFromApi.value && matchedPending) {
      pendingWebgisDataset.value = null;
    }
    return;
  }

  let candidate: ActiveLayerItem | null = null;
  if (
    matchedFromApi &&
    ((matchedFromApi.protocol || "").toUpperCase() === "MVT" || matchedFromApi.source_type === "martin")
  ) {
    candidate = mapLayerItemToActive(matchedFromApi);
  } else if (geoCandidate) {
    candidate = toActiveLayerItem(geoCandidate, 0.85);
  } else if (matchedFromApi) {
    candidate = mapLayerItemToActive({
      ...matchedFromApi,
      expanded: true,
      activeTool: "opacity",
      opacity:
        typeof matchedFromApi.opacity === "number" && matchedFromApi.opacity > 0
          ? matchedFromApi.opacity
          : 0.85,
    });
  }

  if (candidate) {
    handleSelectDataset(candidate);
    appliedDatasetQueryKey.value = targetKey;
    handleOpenLayersMenu();
    fitDatasetBboxIfAvailable(targetKey, geoCandidate || matchedFromApi);
    if (hasInitializedLayersFromApi.value && matchedPending) {
      pendingWebgisDataset.value = null;
    }
  }
}

watch(
  () => layersData.value,
  (val) => {
    if (val && !hasInitializedLayersFromApi.value) {
      applyLayersFromApi(val);
      // Pastikan dataset dari query (?dataset= / ?layer_name=) tetap disisipkan di atas default layers API
      appliedDatasetQueryKey.value = null;
    }
    applyDatasetQueryToMap();
  },
  { immediate: true }
);

watch(
  [() => route.query.dataset, () => route.query.layer_name],
  () => {
    appliedDatasetQueryKey.value = null;
    fittedDatasetBboxKey.value = null;
    applyDatasetQueryToMap();
  }
);

watch(
  [() => geoportalCatalogLayers.value.length, () => pendingWebgisDataset.value],
  () => {
    applyDatasetQueryToMap();
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

  if (basemapParam) {
    const normalized = normalizeBasemapAlias(basemapParam);
    if (normalized === "none" || basemapStore.isValidCode(normalized, "openlayers")) {
      currentBasemap.value = normalized as BasemapKey;
    }
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

    const targetDesaParam = typeof route.query.desa === "string" && route.query.desa.trim()
      ? route.query.desa.trim()
      : null;
    const targetStatusParam = typeof route.query.status === "string" && route.query.status.trim()
      ? route.query.status.trim()
      : null;

    if (targetStatusParam !== selectedStatusFilter.value) {
      selectedStatusFilter.value = targetStatusParam;
      applyMvtFiltersToActiveLayers();
    }

    const parsedCoords = searchLabel ? parseCoordinateString(searchLabel) : null;

    if (targetKecParam || targetDesaParam) {
      // Hanya eksekusi jika filter kecamatan atau desa belum aktif atau berbeda dari yang sedang aktif
      const kecChanged = (targetKecParam || "").toLowerCase() !== (selectedKecamatanFilter.value || "").toLowerCase();
      const desaChanged = (targetDesaParam || "").toLowerCase() !== (selectedDesaFilter.value || "").toLowerCase();
      if (kecChanged || desaChanged) {
        handleFilterWilayah(targetKecParam, undefined, targetDesaParam);
        if (targetKecParam) {
          const matched = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === targetKecParam.toLowerCase());
          if (matched && matched.bbox) {
            mapCanvasRef.value?.fitBbox(matched.bbox, 14, 500, isMobileScreen.value ? "peek" : undefined);
            clickedCoordinate.value = null;
            mapCanvasRef.value?.dismissPulse?.();
            searchedLocationLabel.value = targetDesaParam ? `${targetDesaParam}, Kec. ${matched.name}` : `Kecamatan ${matched.name}`;
          }
        }
      }
    } else if ((selectedKecamatanFilter.value || selectedDesaFilter.value) && !route.query.wilayah && !route.query.kecamatan && !route.query.desa) {
      // Jika parameter wilayah/kecamatan/desa sudah dibersihkan dari query URL, sinkronkan reset
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
    if (basemapParam) {
      const normalized = normalizeBasemapAlias(basemapParam);
      if (normalized === "none" || basemapStore.isValidCode(normalized, "openlayers")) {
        currentBasemap.value = normalized as BasemapKey;
      }
    }
  }
);

// Sinkronisasi penutupan bottomsheet di mobile: saat drawer ditutup dan ada koordinat aktif, bersihkan
watch(mobileDrawerOpen, (isOpen) => {
  if (!isOpen && (clickedCoordinate.value || locationBarOpen.value)) {
    handleCloseInspector();
  }
});

onMounted(() => {
  if (import.meta.client) {
    isMapsReady.value = false;
    isLeftPanelMounted.value = false;
    startLoadingSimulation();
    setTimeout(() => {
      if (!isMapCanvasLoaded.value) {
        handleMapLoaded();
      }
      if (!isMapsReady.value) {
        isMapsReady.value = true;
        isLeftPanelMounted.value = true;
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
  void basemapStore.ensureLoaded().then(() => {
    const queryParam = typeof route.query.basemap === "string" ? route.query.basemap.trim() : null;
    if (queryParam) {
      const normalized = normalizeBasemapAlias(queryParam);
      if (normalized === "none" || basemapStore.isValidCode(normalized, "openlayers")) {
        currentBasemap.value = normalized as BasemapKey;
        return;
      }
    }
    try {
      const saved = localStorage.getItem("melarosa_active_basemap");
      if (saved && saved.trim()) {
        const normalized = normalizeBasemapAlias(saved.trim());
        if (normalized === "none" || basemapStore.isValidCode(normalized, "openlayers")) {
          currentBasemap.value = normalized as BasemapKey;
          return;
        }
      }
    } catch {}
    if (currentBasemap.value !== "none" && !basemapStore.isValidCode(currentBasemap.value, "openlayers")) {
      currentBasemap.value = (basemapStore.defaultKode || "esri") as BasemapKey;
    }
  });

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
  const initialDesa = typeof route.query.desa === "string" && route.query.desa.trim()
    ? route.query.desa.trim()
    : null;
  const initialStatus = typeof route.query.status === "string" && route.query.status.trim()
    ? route.query.status.trim()
    : null;
  if (initialStatus) {
    selectedStatusFilter.value = initialStatus;
    applyMvtFiltersToActiveLayers();
  }

  const initialCoord = searchedLocationLabel.value ? parseCoordinateString(searchedLocationLabel.value) : null;
  if (initialKec || initialDesa) {
    handleFilterWilayah(initialKec, undefined, initialDesa);
    if (initialKec) {
      const matched = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === initialKec.toLowerCase());
      if (matched && matched.bbox && !route.query.bbox) {
        setTimeout(() => {
          mapCanvasRef.value?.fitBbox(matched.bbox, 14, 500, isMobileScreen.value ? "peek" : undefined);
          clickedCoordinate.value = null;
          mapCanvasRef.value?.dismissPulse?.();
        }, 350);
      }
    }
  } else if (initialCoord && !pathView) {
    setTimeout(() => {
      clickedCoordinate.value = [initialCoord.lng, initialCoord.lat];
      mapCanvasRef.value?.flyTo([initialCoord.lng, initialCoord.lat], 16);
      locationBarOpen.value = true;
    }, 350);
  }
});

onUnmounted(() => {
  if (progressTimer) {
    clearInterval(progressTimer);
    progressTimer = null;
  }
  if (urlUpdateTimer) {
    clearTimeout(urlUpdateTimer);
    urlUpdateTimer = null;
  }
  isMapsReady.value = false;
  isLeftPanelMounted.value = false;
  if (import.meta.client) {
    window.removeEventListener("popstate", handlePopState);
    window.removeEventListener("resize", updateIsMobile);
  }
});

interface PeekTapContext {
  wasInspectorOpen: boolean;
  previousSignature: string | null;
  coordinate: [number, number];
}

const activeIdentifiedSignature = ref<string | null>(null);
const pendingPeekTapContext = ref<PeekTapContext | null>(null);

function isPolygonFeature(feature: IdentifiedFeature | null | undefined): boolean {
  return String(feature?.geometryType || "").toLowerCase().includes("polygon");
}

function getIdentifiedFeatureSignature(feature: IdentifiedFeature | null | undefined): string | null {
  if (!feature) return null;
  const layerPart = String(feature.layerId || feature.layerName || "").toLowerCase();
  const keyPart = String(
    feature.selectionKey ||
    feature.rawFeatureId ||
    feature.id ||
    extractNamobj(feature.properties) ||
    ""
  ).toLowerCase();
  if (!layerPart && !keyPart) return null;
  return `${layerPart}::${keyPart}`;
}

function handleMapClick(coordinate: [number, number], meta?: { isPeekTap?: boolean }) {
  const isPeekTap = Boolean(meta?.isPeekTap) && isMobileScreen.value;

  if (isPeekTap) {
    // Saat mobile bottom sheet sedang di posisi peek:
    // Pertahankan isi & sheet sementara query identify berjalan (in-place swap tanpa kedip),
    // lalu putuskan swap vs soft-dismiss saat hasil identify final tiba.
    pendingPeekTapContext.value = {
      wasInspectorOpen: locationBarOpen.value,
      previousSignature: activeIdentifiedSignature.value,
      coordinate,
    };
    return;
  }

  pendingPeekTapContext.value = null;
  activeIdentifiedSignature.value = null;
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
  pendingPeekTapContext.value = null;
  activeIdentifiedSignature.value = null;
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

  // Terapkan filter wilayah kecamatan & spatial extent ke seluruh layer MVT
  handleFilterWilayah(matchedKec ? matchedKec.name : payload.name, targetBbox);

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
  const layerName = String(f.layerName || "").toLowerCase();
  const layerTitle = String(f.layerTitle || "").toLowerCase();
  return (
    layerId.includes("bataswilayah_kabupaten") ||
    layerName.includes("bataswilayah_kabupaten") ||
    layerTitle.includes("bataswilayah_kabupaten") ||
    layerTitle.includes("batas wilayah kabupaten")
  );
}

function handleIdentifyFeatures(features: IdentifiedFeature[]) {
  const filtered = (features || []).filter((f) => !isIgnoredIdentifyFeature(f));
  const peekCtx = pendingPeekTapContext.value;
  const isFinalResult = !isIdentifyingFeatures.value;

  if (peekCtx) {
    if (filtered.length === 0) {
      if (isFinalResult) {
        // Tap area kosong saat peek -> soft dismiss ke min
        pendingPeekTapContext.value = null;
        handleCanvasTapDismiss();
      }
      return;
    }

    const candidateTop = filtered[0];
    const candidateSig = getIdentifiedFeatureSignature(candidateTop);
    const isSameActivePolygon =
      peekCtx.wasInspectorOpen &&
      isPolygonFeature(candidateTop) &&
      Boolean(candidateSig) &&
      candidateSig === peekCtx.previousSignature;

    if (isSameActivePolygon) {
      if (isFinalResult) {
        // Tap ulang poligon aktif yang sama (misal batas desa/kecamatan) -> toggle off / soft dismiss
        pendingPeekTapContext.value = null;
        handleCanvasTapDismiss();
      }
      return;
    }

    if (isFinalResult) {
      pendingPeekTapContext.value = null;
    }

    // In-Place Swap: ganti langsung ke feature baru sambil mempertahankan sheet tetap di peek
    if (currentMainTab.value !== "layers") {
      previousTabBeforeIdentify.value = currentMainTab.value;
      handleTabChange("layers");
    }
    clickedCoordinate.value = candidateTop.coordinate || peekCtx.coordinate;
  }

  identifiedFeatures.value = filtered;
  if (filtered.length > 0) {
    const topFeature = filtered[0];
    activeIdentifiedSignature.value = getIdentifiedFeatureSignature(topFeature);
    if (topFeature.coordinate) {
      clickedCoordinate.value = topFeature.coordinate;
    }
    const namobj = extractNamobj(topFeature.properties);
    searchedLocationLabel.value = namobj || null;
    locationBarOpen.value = true;
    if (isMobileScreen.value) {
      mobileDrawerTab.value = "layer";
      mobileSheetSnap.value = "peek";
      mobileDrawerOpen.value = true;
    }

    // Pasang highlight style trayek UI modern untuk fitur teratas secara instan tanpa fitBounds
    mapCanvasRef.value?.renderSelectedFeature?.(topFeature);
  } else {
    activeIdentifiedSignature.value = null;
    // Klik area kosong: tidak ada objek fitur, kamera tidak digeser
    mapCanvasRef.value?.renderSelectedFeature?.(null);
  }
}

function handleSelectFeature(feature: IdentifiedFeature) {
  if (lastRoadZoomedId && feature?.id === lastRoadZoomedId && Date.now() - lastRoadZoomedAt < 400) {
    return;
  }
  activeIdentifiedSignature.value = getIdentifiedFeatureSignature(feature);
  const namobj = extractNamobj(feature?.properties);
  if (namobj) {
    searchedLocationLabel.value = namobj;
  }

  // Update highlight style trayek UI modern sesuai fitur yang dipilih dari daftar secara instan tanpa fitBounds
  mapCanvasRef.value?.renderSelectedFeature?.(feature);

  if (isMobileScreen.value) {
    mobileDrawerTab.value = "layer";
    mobileSheetSnap.value = "peek";
    mobileDrawerOpen.value = true;
  }
}

let isClosingInspector = false;

function handleClearPin() {
  pendingPeekTapContext.value = null;
  activeIdentifiedSignature.value = null;
  roadSelectRequestSeq++;
  isIdentifyingFeatures.value = false;
  previousTabBeforeIdentify.value = null;
  clickedCoordinate.value = null;
  searchedLocationLabel.value = null;
  identifiedFeatures.value = [];
  locationBarOpen.value = false;
  mapCanvasRef.value?.dismissPulse?.();
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
  roadSelectRequestSeq++;
  isIdentifyingFeatures.value = false;
  try {
    locationBarOpen.value = false;
    clickedCoordinate.value = null;
    searchedLocationLabel.value = null;
    identifiedFeatures.value = [];
    mapCanvasRef.value?.dismissPulse?.();
    mapCanvasRef.value?.renderSelectedFeature?.(null);

    // Otomatis kembalikan ke tab sebelumnya saat panel info lokasi ditutup
    if (previousTabBeforeIdentify.value) {
      const prev = previousTabBeforeIdentify.value;
      previousTabBeforeIdentify.value = null;
      handleTabChange(prev);
      if (isMobileScreen.value) {
        mobileDrawerTab.value =
          prev === "geopark"
            ? "geopark"
            : prev === "infrastruktur"
              ? "infrastruktur"
              : prev === "dataset"
                ? "dataset"
                : "layer";
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

watch(
  [() => desktopLeftPanelRef.value, () => mobileLeftPanelRef.value],
  () => {
    if (!pendingOpenLayersMenu.value) return;
    const mobile = typeof window !== "undefined" ? window.innerWidth < 768 : isMobileScreen.value;
    if (mobile && mobileLeftPanelRef.value?.openOtherLayers) {
      mobileLeftPanelRef.value.openOtherLayers();
      pendingOpenLayersMenu.value = false;
    } else if (!mobile && desktopLeftPanelRef.value?.openOtherLayers) {
      desktopLeftPanelRef.value.openOtherLayers();
      pendingOpenLayersMenu.value = false;
    }
  },
  { flush: "post" }
);

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
  if (raw === "geosite" || raw === "geopark" || raw === "wisata" || Boolean(siteQuery)) return "geopark";
  if (raw === "infrastruktur" || raw === "wilayah" || raw === "desa") return "infrastruktur";
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
  if (isMobileScreen.value) {
    mobileDrawerTab.value =
      tab === "geopark"
        ? "geopark"
        : tab === "infrastruktur"
          ? "infrastruktur"
          : tab === "dataset"
            ? "dataset"
            : "layer";
  }
  const queryTab = tab === "layers" ? undefined : tab === "geopark" ? "wisata" : tab;
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
      : tab === "infrastruktur"
        ? "infrastruktur"
        : tab === "dataset"
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
      : tab === "infrastruktur"
        ? "infrastruktur"
        : tab === "dataset"
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
    rawTab === "geosite" || rawTab === "geopark" || rawTab === "wisata" || Boolean(siteQuery)
      ? "geopark"
      : rawTab === "infrastruktur" || rawTab === "wilayah" || rawTab === "desa"
        ? "infrastruktur"
        : rawTab === "dataset" || rawTab === "geoportal"
          ? "dataset"
          : "layers";

  mobileDrawerTab.value =
    mainTab === "geopark"
      ? "geopark"
      : mainTab === "infrastruktur"
        ? "infrastruktur"
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
  const rawDesa = q.desa;
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
    if (!rawDesa && selectedDesaFilter.value) {
      selectedDesaFilter.value = null;
    } else if (rawDesa && typeof rawDesa === "string" && selectedDesaFilter.value !== rawDesa) {
      selectedDesaFilter.value = rawDesa;
    }
  } else if (!rawKec && (selectedKecamatanFilter.value || selectedDesaFilter.value)) {
    selectedKecamatanFilter.value = null;
    selectedDesaFilter.value = null;
    applyMvtFiltersToActiveLayers(null);
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

function convertBbox4326To3857(bbox: [number, number, number, number]): [number, number, number, number] {
  const minX = (bbox[0] * 20037508.34) / 180;
  let minY = Math.log(Math.tan(((90 + bbox[1]) * Math.PI) / 360)) / (Math.PI / 180);
  minY = (minY * 20037508.34) / 180;

  const maxX = (bbox[2] * 20037508.34) / 180;
  let maxY = Math.log(Math.tan(((90 + bbox[3]) * Math.PI) / 360)) / (Math.PI / 180);
  maxY = (maxY * 20037508.34) / 180;

  return [minX, minY, maxX, maxY];
}

function isMvtLayerItem(layer: ActiveLayerItem): boolean {
  return Boolean(
    (layer.protocol || "").toUpperCase() === "MVT" ||
    layer.source_type === "martin" ||
    (layer.source_type === "internal" && !layer.wmsUrl) ||
    layer.mvtUrl ||
    layer.martinSource ||
    (layer.url && layer.url.includes("/martin/")) ||
    (layer.layer_name &&
      [
        "infrastruktur_segmen",
        "jalan_porosdesa",
        "jalan_kabupaten_luarkota",
        "bataswilayah_desa",
        "bataswilayah_kecamatan",
      ].includes(layer.layer_name))
  );
}

function applyMvtFiltersToActiveLayers(customExtent3857?: [number, number, number, number] | null) {
  let filterExtent3857 = customExtent3857 ?? null;
  if (!filterExtent3857 && selectedKecamatanFilter.value) {
    const cleanKec = selectedKecamatanFilter.value.replace(/^Kecamatan\s+/i, "").trim().toLowerCase();
    const matchedKec = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === cleanKec);
    if (matchedKec?.bbox && matchedKec.bbox.length === 4) {
      filterExtent3857 = convertBbox4326To3857(matchedKec.bbox);
    }
  }

  activeLayers.value = activeLayers.value.map((layer) => {
    if (isMvtLayerItem(layer)) {
      return {
        ...layer,
        filterKecamatan: selectedKecamatanFilter.value,
        filterDesa: selectedDesaFilter.value,
        filterStatus: selectedStatusFilter.value,
        filterExtent: filterExtent3857,
      };
    }
    return layer;
  });
}

function fitMapToDesaFilter(desaName: string, kecamatanName?: string | null, attempt = 0) {
  if (!import.meta.client) return;
  const desaExtent = mapCanvasRef.value?.findDesaExtentInMvt?.(desaName, kecamatanName);
  if (desaExtent) {
    mapCanvasRef.value?.fitBbox?.(
      desaExtent,
      15,
      500,
      isMobileScreen.value ? "peek" : undefined
    );
    return;
  }
  if (attempt < 3) {
    setTimeout(() => {
      if (selectedDesaFilter.value && selectedDesaFilter.value.toLowerCase() === desaName.toLowerCase()) {
        fitMapToDesaFilter(desaName, kecamatanName, attempt + 1);
      }
    }, 450);
  }
}

function handleFilterWilayah(
  kecamatanName: string | null,
  customBbox?: [number, number, number, number] | null,
  desaName?: string | null
) {
  selectedKecamatanFilter.value = kecamatanName;
  selectedDesaFilter.value = desaName || null;

  let filterExtent3857: [number, number, number, number] | null = null;
  if (kecamatanName) {
    const cleanKec = kecamatanName.replace(/^Kecamatan\s+/i, "").trim().toLowerCase();
    const matchedKec = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === cleanKec);
    const targetBbox = customBbox || matchedKec?.bbox;
    if (targetBbox && targetBbox.length === 4) {
      filterExtent3857 = convertBbox4326To3857(targetBbox);
    }
  } else if (customBbox && customBbox.length === 4) {
    filterExtent3857 = convertBbox4326To3857(customBbox);
  }

  updateMapQuery({
    wilayah: kecamatanName || undefined,
    kecamatan: kecamatanName || undefined,
    desa: desaName || undefined,
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
      if (!desaName) {
        url.searchParams.delete("desa");
      }
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
  applyMvtFiltersToActiveLayers(filterExtent3857);

  if (desaName && !customBbox) {
    fitMapToDesaFilter(desaName, kecamatanName);
  }
}

function handleReorderLayers(newLayers: ActiveLayerItem[]) {
  // Posisi di panel: index 0 adalah paling atas (order tertinggi / depan peta), index terakhir paling bawah (dasar)
  activeLayers.value = withNormalizedStackOrder(newLayers);
}

function handleRemoveLayer(layerId: string) {
  activeLayers.value = withNormalizedStackOrder(
    (activeLayers.value || []).filter((l) => l.id !== layerId)
  );
}

function handleSelectDataset(dataset: any) {
  const targetLayerName = String(dataset.layer_name || dataset.wmsLayerName || "").trim().toLowerCase();
  const existing = activeLayers.value.find(
    (l) =>
      l.id === dataset.id ||
      (targetLayerName && String(l.layer_name || "").trim().toLowerCase() === targetLayerName)
  );
  if (existing) {
    const rest = activeLayers.value.filter((l) => l.id !== existing.id);
    activeLayers.value = withNormalizedStackOrder([
      { ...existing, visible: true, expanded: true },
      ...rest,
    ]);
  } else {
    // Sisipkan layer baru di posisi teratas tumpukan GIS (paling depan di peta)
    const newActiveItem = mapLayerItemToActive(dataset);
    activeLayers.value = withNormalizedStackOrder([newActiveItem, ...activeLayers.value]);
  }

  if (selectedKecamatanFilter.value || selectedDesaFilter.value || selectedStatusFilter.value) {
    applyMvtFiltersToActiveLayers();
  }

  leftPanelOpen.value = true;
}


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
        :selected-desa-filter="selectedDesaFilter"
        :clicked-coordinate="clickedCoordinate"
        :location-label="searchedLocationLabel"
        :identified-features="identifiedFeatures"
        :is-identifying="isIdentifyingFeatures"
        :inspector-open="locationBarOpen"
        :geopark-overlay-visible="showGeoparkOverlay"
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
        @toggle-roads-visibility="handleToggleRoadsVisibility"
        @toggle-geopark-overlay="handleToggleGeoparkOverlay"
        @locate-user="handleLocateUser"
        @select-road="handleSelectRoadFromExplorer"
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
          :default-bbox="MARTIN_SPATIAL_BBOX"
          :left-panel-open="leftPanelOpen"
          :active-layers="activeLayers"
          :mobile-drawer-open="mobileDrawerOpen"
          :mobile-sheet-snap="mobileSheetSnap"
          :active-tab="currentMainTab"
          :selected-geopark-site="selectedGeoparkSite"
          :geopark-sites="(currentMainTab === 'geopark' || showGeoparkOverlay) ? geoparkSites : []"
          @select-geopark="handleOpenGeoparkDetail"
          @map-click="handleMapClick"
          @canvas-tap-dismiss="handleCanvasTapDismiss"
          @identify-features="handleIdentifyFeatures"
          @identifying-features="isIdentifyingFeatures = $event"
          @update:viewport="handleViewportUpdate"
          @dismiss-popup="handleCloseInspector"
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

              <p class="text-[11px] font-medium tracking-wide text-slate-400 dark:text-slate-500 uppercase">
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
              <div class="flex items-center justify-between text-[11px] font-medium text-slate-400 dark:text-slate-500 px-0.5">
                <span>Inisialisasi</span>
                <span class="font-bold text-blue-600 dark:text-blue-400 tabular-nums">
                  {{ loadingProgress }}%
                </span>
              </div>
            </div>

            <p class="text-[11px] font-medium tracking-wide text-slate-400 dark:text-slate-500 uppercase">
              WebGIS Kabupaten Bojonegoro
            </p>
          </div>
        </div>
      </Transition>

      <!-- Floating Display, Layer & Basemap Group (Kanan Bawah di dalam Kanvas Peta) -->
      <div
        class="absolute z-20 pointer-events-auto select-none flex flex-col items-end gap-1.5 transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] right-3 sm:right-4 md:right-4"
        :class="[
          mobileDrawerOpen && mobileSheetSnap === 'full'
            ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
            : mobileDrawerOpen && mobileSheetSnap === 'peek'
              ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4'
              : mobileDrawerOpen && mobileSheetSnap === 'min'
                ? 'bottom-[calc(156px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
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

          <!-- Tombol Layer (toggle buka/tutup halaman layer & kembali ke Jelajah) -->
          <UTooltip text="Kelola &amp; Urutkan Layer Peta">
            <button
              type="button"
              class="w-full h-11 min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 transition-all cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 active:scale-90"
              aria-label="Kelola dan Urutkan Layer Peta"
              @click="handleToggleLayersMenu"
            >
              <UIcon name="i-lucide-layers" class="size-4 stroke-[2.25]" />
            </button>
          </UTooltip>
        </div>

        <!-- Basemap Panel -->
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
          ? 'Info Lokasi & Jalan'
          : mobileDrawerTab === 'infrastruktur'
            ? 'Infrastruktur Bojonegoro'
            : mobileDrawerTab === 'geopark'
              ? 'Wisata Bojonegoro'
              : mobileDrawerTab === 'dataset'
                ? 'Lapisan Peta Tambahan'
                : 'Peta Bojonegoro'
      "
      :icon="
        locationBarOpen && clickedCoordinate
          ? 'i-ion-location'
          : mobileDrawerTab === 'infrastruktur'
            ? 'i-ion-construct'
            : mobileDrawerTab === 'geopark'
              ? 'i-ion-earth'
              : mobileDrawerTab === 'dataset'
                ? 'i-ion-layers'
                : 'i-ion-map'
      "
    >
      <!-- Tab Switcher: 3 tab teks murni radius 100% tanpa garis atas/bawah -->
      <template #tabs>
        <div class="px-2.5 sm:px-3 pt-1 pb-1.5 bg-transparent">
          <div class="relative w-full grid grid-cols-3 p-1 bg-slate-200/60 dark:bg-white/5 rounded-full border border-slate-200/70 dark:border-white/10 overflow-hidden" role="tablist" aria-label="Navigasi Panel Mobile">
            <span
              aria-hidden="true"
              class="pointer-events-none absolute top-1 bottom-1 left-1 w-[calc((100%-8px)/3)] rounded-full bg-white dark:bg-[#0b0f19] shadow-xs transition-transform duration-300 [transition-timing-function:cubic-bezier(0.22,1,0.36,1)] will-change-transform motion-reduce:transition-none"
              :style="{ transform: `translate3d(${activeMobileTabIndex * 100}%, 0, 0)` }"
            />

            <!-- Tab 1: Jelajah -->
            <button
              type="button"
              role="tab"
              :aria-selected="currentMainTab === 'layers'"
              class="relative z-10 py-1.5 px-2 rounded-full text-[13px] tracking-tight transition-colors duration-200 cursor-pointer min-h-[36px] inline-flex items-center justify-center select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center truncate"
              :class="currentMainTab === 'layers'
                ? 'text-blue-600 dark:text-blue-400 font-bold'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold'"
              @click="handleMobileTabClick('layer')"
            >
              Jelajah
            </button>

            <!-- Tab 2: Infrastruktur -->
            <button
              type="button"
              role="tab"
              :aria-selected="currentMainTab === 'infrastruktur' || currentMainTab === 'dataset'"
              class="relative z-10 py-1.5 px-2 rounded-full text-[13px] tracking-tight transition-colors duration-200 cursor-pointer min-h-[36px] inline-flex items-center justify-center select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center truncate"
              :class="currentMainTab === 'infrastruktur' || currentMainTab === 'dataset'
                ? 'text-blue-600 dark:text-blue-400 font-bold'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold'"
              @click="handleMobileTabClick('infrastruktur')"
            >
              Infrastruktur
            </button>

            <!-- Tab 3: Wisata -->
            <button
              type="button"
              role="tab"
              :aria-selected="currentMainTab === 'geopark'"
              class="relative z-10 py-1.5 px-2 rounded-full text-[13px] tracking-tight transition-colors duration-200 cursor-pointer min-h-[36px] inline-flex items-center justify-center select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden active:scale-95 text-center truncate"
              :class="currentMainTab === 'geopark'
                ? 'text-blue-600 dark:text-blue-400 font-bold'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold'"
              @click="handleMobileTabClick('geopark')"
            >
              Wisata
            </button>
          </div>
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
        :selected-desa-filter="selectedDesaFilter"
          :clicked-coordinate="clickedCoordinate"
          :location-label="searchedLocationLabel"
          :identified-features="identifiedFeatures"
          :is-identifying="isIdentifyingFeatures"
          :inspector-open="locationBarOpen"
          :geopark-overlay-visible="showGeoparkOverlay"
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
          @toggle-roads-visibility="handleToggleRoadsVisibility"
          @toggle-geopark-overlay="handleToggleGeoparkOverlay"
          @locate-user="handleLocateUser"
          @select-road="handleSelectRoadFromExplorer"
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

<script setup lang="ts">
import "ol/ol.css";
import MapControls from "./MapControls.vue";
import MapScaleCoordinateBar from "./MapScaleCoordinateBar.vue";
import type { ActiveLayerItem, BasemapKey } from "~/types/map-layers";
import { $http } from "~/utils/helpers";
import type { IdentifiedFeature } from "./IdentifyFeatures.vue";

export type { BasemapKey };

export interface MapViewportPayload {
  bbox: [number, number, number, number];
  zoom: number;
  center: [number, number];
}

const props = withDefaults(
  defineProps<{
    currentBasemap?: BasemapKey;
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    initialCenter?: [number, number] | null;
    initialBbox?: [number, number, number, number] | null;
    initialZoom?: number | null;
    activeLayers?: ActiveLayerItem[];
    mobileDrawerOpen?: boolean;
    mobileSheetSnap?: "min" | "peek" | "full";
  }>(),
  {
    currentBasemap: "esri",
    clickedCoordinate: null,
    locationLabel: null,
    initialCenter: null,
    initialBbox: null,
    initialZoom: null,
    activeLayers: () => [],
    mobileDrawerOpen: false,
    mobileSheetSnap: "min",
  }
);

const emit = defineEmits<{
  (e: "map-click", coordinate: [number, number]): void;
  (e: "select-feature", feature: any): void;
  (e: "identify-features", features: IdentifiedFeature[]): void;
  (e: "identifying-features", loading: boolean): void;
  (e: "update:mouse-coords", coords: string): void;
  (e: "update:viewport", viewport: MapViewportPayload): void;
  (e: "loaded"): void;
  (e: "dismiss-popup"): void;
  (e: "share"): void;
}>();

const mapContainer = ref<HTMLElement | null>(null);
const pulseEl = ref<HTMLElement | null>(null);
const scaleLineTarget = ref<HTMLElement | null>(null);
let scaleLineControlInstance: any = null;

function handleScaleTargetMounted(el: HTMLElement) {
  scaleLineTarget.value = el;
  if (scaleLineControlInstance && typeof scaleLineControlInstance.setTarget === "function") {
    scaleLineControlInstance.setTarget(el);
  }
}

// Bojonegoro coordinates: [longitude, latitude] in EPSG:4326
const BOJONEGORO_CENTER: [number, number] = [111.88, -7.15];
const DEFAULT_ZOOM = 11;

// ─── Live Cursor Coordinates & Map Scale Status Bar ──────────────────────────
const livePointerCoords = ref<{ lng: number; lat: number } | null>(null);
const currentMapCenter = ref<{ lng: number; lat: number }>({
  lng: BOJONEGORO_CENTER[0],
  lat: BOJONEGORO_CENTER[1],
});
const currentZoomLevel = ref<number>(DEFAULT_ZOOM);
const isPointerOnCanvas = ref(false);
const activeDisplayCoords = computed(() => {
  if (isPointerOnCanvas.value && livePointerCoords.value) {
    return {
      coords: livePointerCoords.value,
      isLiveCursor: true,
    };
  }
  return {
    coords: livePointerCoords.value || currentMapCenter.value,
    isLiveCursor: false,
  };
});

const currentScaleRatio = ref<string>("1 : 25.000");

function formatScaleNumber(denom: number): string {
  if (denom <= 0) return "1.000";
  const STANDARD_SCALES = [
    500, 1000, 2000, 2500, 5000, 10000, 20000, 25000, 50000, 100000, 200000, 250000, 500000, 1000000, 2000000, 5000000
  ];
  for (const std of STANDARD_SCALES) {
    const diff = Math.abs(denom - std) / std;
    if (diff < 0.22) {
      return std.toLocaleString("id-ID");
    }
  }
  if (denom < 1000) return (Math.round(denom / 50) * 50).toLocaleString("id-ID");
  if (denom < 10000) return (Math.round(denom / 500) * 500).toLocaleString("id-ID");
  if (denom < 100000) return (Math.round(denom / 1000) * 1000).toLocaleString("id-ID");
  if (denom < 500000) return (Math.round(denom / 5000) * 5000).toLocaleString("id-ID");
  return (Math.round(denom / 10000) * 10000).toLocaleString("id-ID");
}

function updateMapCenterAndZoom() {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const z = view.getZoom();
  if (z !== undefined) {
    currentZoomLevel.value = Math.round(z);
  }
  const center = view.getCenter();
  if (center) {
    const lonLat = olModules.toLonLat(center);
    if (lonLat && lonLat.length >= 2) {
      const lat = Number(lonLat[1]);
      const lng = Number(lonLat[0]);
      currentMapCenter.value = {
        lng: Number(lng.toFixed(5)),
        lat: Number(lat.toFixed(5)),
      };
      const res = view.getResolution();
      if (res !== undefined) {
        const latRad = (lat * Math.PI) / 180;
        const cosLat = Math.cos(latRad);
        const scaleDenominator = Math.round(res * cosLat * 3779.527559);
        currentScaleRatio.value = `1 : ${formatScaleNumber(scaleDenominator)}`;
      }
    }
  }
}

function handleSelectScale(targetDenom: number) {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const center = view.getCenter();
  if (!center) return;
  const lonLat = olModules.toLonLat(center);
  const lat = lonLat && lonLat.length >= 2 ? Number(lonLat[1]) : BOJONEGORO_CENTER[1];
  const latRad = (lat * Math.PI) / 180;
  const cosLat = Math.cos(latRad);
  const targetResolution = targetDenom / (cosLat * 3779.527559);

  view.animate({
    resolution: targetResolution,
    duration: 350,
  });
}

const mapLoaded = ref(false);
const isPulseVisible = ref(false);
const isBadgeVisible = ref(Boolean(props.clickedCoordinate));
const isFullscreen = ref(false);
const isLocating = ref(false);
const hasUserLocation = ref(false);
const isUserLocationVisible = ref(false);
const userLocationCoords = ref<[number, number] | null>(null);
const clickAnimKey = ref(0);
const activeNamobj = ref<string | null>(null);
const isIdentifyingFeatures = ref(false);
const userSpeed = ref<number | null>(null);
const userHeading = ref<number | null>(null);
const userAccuracy = ref<number | null>(null);
let lastReportedCoords: [number, number] | null = null;
let geolocationWatchId: number | null = null;
let orientationListener: ((e: any) => void) | null = null;
let isFirstLocationLock = true;

// Hitung jarak geografis antar dua titik koordinat (dalam meter menggunakan formula Haversine)
function getDistanceMeters(coord1: [number, number], coord2: [number, number]): number {
  const [lon1, lat1] = coord1;
  const [lon2, lat2] = coord2;
  const R = 6371e3; // Radius bumi dalam meter
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

watch(
  () => props.locationLabel,
  (newLabel) => {
    if (newLabel) {
      activeNamobj.value = newLabel;
    }
  }
);

watch(
  () => props.clickedCoordinate,
  (coord) => {
    if (!coord) {
      activeNamobj.value = null;
    }
  }
);

function extractNamobj(properties: Record<string, any> | undefined): string | null {
  if (!properties) return null;

  // 1. Cek langsung kunci NAMOBJ (case-insensitive)
  for (const [k, v] of Object.entries(properties)) {
    if (k.toUpperCase() === "NAMOBJ" && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }

  // 2. Cek properti nama spesifik spasial lainnya
  const prioritizedKeys = ["NAMA_RUAS", "WADMKD", "WADMKC", "WADMKK", "REMARK"];
  for (const pKey of prioritizedKeys) {
    for (const [k, v] of Object.entries(properties)) {
      if (k.toUpperCase() === pKey && v) {
        const s = String(v).trim();
        if (s && s !== "-") return s;
      }
    }
  }

  // 3. Fallback ke properti yang mengandung NAM atau TITLE
  for (const [k, v] of Object.entries(properties)) {
    const upper = k.toUpperCase();
    if ((upper.includes("NAM") || upper.includes("TITLE")) && v) {
      const s = String(v).trim();
      if (s && s !== "-") return s;
    }
  }

  return null;
}

const effectivePopupTitle = computed(() => {
  if (activeNamobj.value) return activeNamobj.value;
  if (props.locationLabel) return props.locationLabel;
  if (isIdentifyingFeatures.value) return "Memeriksa objek spasial...";
  return "Titik Koordinat";
});

let mapInstance: any = null;
let tileLayer: any = null;
let pulseOverlay: any = null;
let olModules: any = null;
const wmsLayersMap = new Map<string, any>();
const mvtLayersMap = new Map<string, any>();
let isDismissing = false;
let dismissTimer: ReturnType<typeof setTimeout> | null = null;

// Cartographic Graticule (Grid Kartografi & Koordinat Tepi Peta)
const showCartoGrid = ref(false);
let graticuleLayer: any = null;
let themeMutationObserver: MutationObserver | null = null;

// Geolocation: OL VectorLayer for accuracy circle + dot (scales with zoom)
let geoVectorSource: any = null;
let geoVectorLayer: any = null;
let geoAccuracyFeature: any = null;
let geoPointFeature: any = null;
let geoHeadingFeature: any = null;

// Geolocation smooth animation state
let geoAnimFrameId: number | null = null;
let geoAnimFrom: [number, number] | null = null;
let geoAnimTo: [number, number] | null = null;
let geoAnimStartTime: number | null = null;
const GEO_ANIM_DURATION = 1200; // ms


function getBasemapSource(key: BasemapKey) {
  if (!olModules) return null;
  const { OSM, XYZ } = olModules;

  switch (key) {
    case "satellite":
      return new XYZ({
        url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        attributions: "&copy; Esri, Maxar, Earthstar Geographics",
        maxZoom: 19,
        crossOrigin: "anonymous",
      });
    case "aerial":
      return new XYZ({
        url: "https://petadasar.meritech.cloud/tile/{z}/{x}/{y}.jpg",
        attributions: "&copy; Peta Dasar ATR/BPN Aerial Orthophoto",
        maxZoom: 21,
        crossOrigin: "anonymous",
      });
    case "topo":
    case "osm":
      return new OSM();
    case "rbi":
      return new XYZ({
        url: "/rbi-tiles/{z}/{y}/{x}",
        attributions: "&copy; Badan Informasi Geospasial (BIG)",
        maxZoom: 18,
        crossOrigin: "anonymous",
        tileLoadFunction: (imageTile: any, src: string) => {
          const img = imageTile.getImage();
          img.crossOrigin = "anonymous";
          let hasRetried = false;
          img.onerror = () => {
            if (!hasRetried && src.includes("/rbi-tiles/")) {
              hasRetried = true;
              img.src = src.replace(/^.*?\/rbi-tiles\//, "https://geoservices.big.go.id/rbi/rest/services/BASEMAP/Rupabumi_Indonesia/MapServer/tile/");
            }
          };
          img.src = src;
        },
      });
    case "none":
      return null;
    case "esri":
    case "positron":
    case "dark":
    default:
      return new XYZ({
        url: "https://services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
        attributions: "&copy; Esri, HERE, Garmin, FAO, NOAA, USGS, EPA, NPS, NRCan",
        maxZoom: 19,
        crossOrigin: "anonymous",
      });
  }
}

watch(
  () => props.currentBasemap,
  (newBasemap) => {
    if (tileLayer && olModules) {
      if (newBasemap === "none") {
        tileLayer.setVisible(false);
        tileLayer.setSource(null);
      } else {
        const source = getBasemapSource(newBasemap);
        if (source) {
          tileLayer.setVisible(true);
          tileLayer.setSource(source);
        }
      }
    }
  }
);

watch(
  () => props.clickedCoordinate,
  (coord) => {
    if (!coord || !pulseOverlay || !olModules) {
      isBadgeVisible.value = false;
      return;
    }
    clickAnimKey.value++;
    isBadgeVisible.value = true;
    isPulseVisible.value = true;
    const mercator = olModules.fromLonLat(coord);
    pulseOverlay.setPosition(mercator);
  },
  { deep: true }
);

watch(
  () => props.activeLayers,
  () => {
    syncActiveWmsLayers();
  },
  { deep: true }
);

function dismissPulse() {
  if (!isBadgeVisible.value) return;
  isBadgeVisible.value = false;
  emit("dismiss-popup");

  // Block the OL singleclick that will fire from this same pointer event.
  // OL delays singleclick by ~250ms to separate it from double-click.
  isDismissing = true;
  if (dismissTimer) clearTimeout(dismissTimer);
  dismissTimer = setTimeout(() => { isDismissing = false; }, 500);
}

function onBadgeAfterLeave() {
  if (!isBadgeVisible.value) {
    isPulseVisible.value = false;
    if (pulseOverlay) pulseOverlay.setPosition(undefined);
  }
}

// Ease-out cubic untuk animasi marker geolocation yang terasa natural
function easeOutCubic(t: number): number {
  return 1 - Math.pow(1 - t, 3);
}

// Animasikan overlay geolocation dari posisi saat ini ke posisi target secara smooth
function animateGeoOverlayTo(targetMercator: [number, number]) {
  if (!geoPointFeature) return;

  // Ambil koordinat render saat ini sebagai titik awal animasi
  const currentCoords = geoPointFeature.getGeometry()?.getCoordinates() as [number, number] | undefined;
  geoAnimFrom = currentCoords ?? targetMercator;
  geoAnimTo = targetMercator;
  geoAnimStartTime = null;

  // Batalkan animasi sebelumnya jika GPS update datang lagi sebelum selesai
  if (geoAnimFrameId !== null) cancelAnimationFrame(geoAnimFrameId);

  function step(timestamp: number) {
    if (!geoAnimStartTime) geoAnimStartTime = timestamp;
    const elapsed = timestamp - geoAnimStartTime;
    const t = Math.min(elapsed / GEO_ANIM_DURATION, 1);
    const ease = easeOutCubic(t);

    if (geoAnimFrom && geoAnimTo && geoPointFeature) {
      const x = geoAnimFrom[0] + (geoAnimTo[0] - geoAnimFrom[0]) * ease;
      const y = geoAnimFrom[1] + (geoAnimTo[1] - geoAnimFrom[1]) * ease;
      geoPointFeature.getGeometry()?.setCoordinates([x, y]);
      geoAccuracyFeature?.getGeometry()?.setCenter([x, y]);
      geoHeadingFeature?.getGeometry()?.setCoordinates([x, y]);
    }

    if (t < 1) {
      geoAnimFrameId = requestAnimationFrame(step);
    } else {
      geoAnimFrameId = null;
    }
  }

  geoAnimFrameId = requestAnimationFrame(step);
}

function zoomIn() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || DEFAULT_ZOOM) + 1, duration: 250 });
}

function zoomOut() {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  view.animate({ zoom: (view.getZoom() || DEFAULT_ZOOM) - 1, duration: 250 });
}

function resetView() {
  if (!mapInstance || !olModules) return;
  mapInstance.getView().animate({
    center: olModules.fromLonLat(BOJONEGORO_CENTER),
    zoom: DEFAULT_ZOOM,
    duration: 500,
  });
}

function flyTo(coordinate: [number, number], zoom = 14) {
  if (!mapInstance || !olModules) return;
  mapInstance.getView().animate({
    center: olModules.fromLonLat(coordinate),
    zoom,
    duration: 600,
  });
}

function locateUser() {
  if (!import.meta.client) return;

  // Jika geolocation saat ini sedang aktif, matikan (toggle off)
  if (hasUserLocation.value) {
    stopLiveTracking();
    return;
  }

  // Jika belum aktif, mulai live tracking
  startLiveTracking();
}

function updateHeadingDisplay(heading: number | null) {
  if (geoHeadingFeature && olModules?._buildHeadingStyle) {
    geoHeadingFeature.setStyle(olModules._buildHeadingStyle(heading));
  }
}

function startLiveTracking() {
  if (!navigator.geolocation) {
    console.warn("Geolocation tidak didukung oleh browser ini.");
    return;
  }

  isLocating.value = true;
  isFirstLocationLock = true;

  // 1. Live tracking posisi dan kecepatan GPS real-time dengan filter peredam jitter
  geolocationWatchId = navigator.geolocation.watchPosition(
    (pos) => {
      isLocating.value = false;
      hasUserLocation.value = true;
      const newCoords: [number, number] = [pos.coords.longitude, pos.coords.latitude];
      const accuracy = pos.coords.accuracy || 10;
      userAccuracy.value = Math.round(accuracy);

      // Kecepatan GPS (m/s diubah ke km/jam)
      const rawSpeed = pos.coords.speed;
      if (typeof rawSpeed === "number" && !isNaN(rawSpeed) && rawSpeed > 0.5) {
        userSpeed.value = Math.round(rawSpeed * 3.6);
      } else {
        userSpeed.value = 0;
      }

      // Fallback heading GPS jika bergerak dan kompas fisik belum aktif
      if (typeof pos.coords.heading === "number" && !isNaN(pos.coords.heading) && userHeading.value === null) {
        userHeading.value = Math.round(pos.coords.heading);
        updateHeadingDisplay(userHeading.value);
      }

      // ═══ FILTER PEREDAM JITTER (Mencegah Titik Melompat/Bergeser di PC) ═══
      let shouldUpdatePosition = false;

      if (isFirstLocationLock || !lastReportedCoords) {
        // Pembacaan pertama kali selalu diterima untuk mengunci posisi awal
        shouldUpdatePosition = true;
      } else {
        const distanceMoved = getDistanceMeters(lastReportedCoords, newCoords);

        // Jika kecepatan nyata terdeteksi (sedang jalan/kendaraan)
        if (userSpeed.value && userSpeed.value >= 3) {
          shouldUpdatePosition = distanceMoved >= 2;
        }
        // Jika akurasi kasar (tipikal PC / Wi-Fi / IP dengan radius > 30 meter)
        else if (accuracy > 30) {
          // Hanya update jika perpindahan fisik benar-benar jauh di luar fluktuasi sinyal Wi-Fi (> 25 meter)
          shouldUpdatePosition = distanceMoved > 25;
        }
        // Jika akurasi tinggi (GPS HP dengan radius <= 30 meter)
        else {
          // Abaikan noise satelit mikro di bawah 4 meter
          shouldUpdatePosition = distanceMoved >= 4;
        }
      }

      if (shouldUpdatePosition) {
        lastReportedCoords = newCoords;
        userLocationCoords.value = newCoords;

        if (!mapInstance || !olModules) return;
        const mercator = olModules.fromLonLat(newCoords) as [number, number];

        if (geoPointFeature) {
          // Update accuracy circle radius (koreksi proyeksi Mercator berdasarkan latitude)
          const mercatorRadius = accuracy / Math.cos((newCoords[1] * Math.PI) / 180);
          geoAccuracyFeature?.getGeometry()?.setRadius(mercatorRadius);

          // Update heading feature style jika heading tersedia
          updateHeadingDisplay(userHeading.value);

          if (isFirstLocationLock) {
            // Posisi pertama: langsung tanpa animasi
            geoPointFeature.getGeometry()?.setCoordinates(mercator);
            geoAccuracyFeature?.getGeometry()?.setCenter(mercator);
            geoHeadingFeature?.getGeometry()?.setCoordinates(mercator);
          } else {
            // Perpindahan selanjutnya: smooth lerp
            animateGeoOverlayTo(mercator);
          }
          isUserLocationVisible.value = true;
          if (geoVectorLayer && !geoVectorLayer.getVisible()) {
            geoVectorLayer.setVisible(true);
          }
        }

        // Kunci ke tengah peta hanya pada pembacaan pertama kali
        if (isFirstLocationLock) {
          mapInstance.getView().animate({
            center: mercator,
            zoom: Math.max(mapInstance.getView().getZoom() || 11, 16),
            duration: 700,
          });
          isFirstLocationLock = false;
        }
      }
    },
    (err) => {
      isLocating.value = false;
      console.warn("Gagal membaca GPS live tracking:", err.message);
    },
    {
      enableHighAccuracy: true,
      maximumAge: 1000,
      timeout: 10000,
    }
  );

  // 2. Baca arah kompas perangkat mobile (DeviceOrientation)
  startCompassTracking();
}

function startCompassTracking() {
  if (!window.DeviceOrientationEvent) return;

  const handleOrientation = (event: any) => {
    let heading: number | null = null;

    // iOS Safari menggunakan webkitCompassHeading
    if (typeof event.webkitCompassHeading === "number") {
      heading = event.webkitCompassHeading;
    }
    // Android Chrome / standar (alpha terhadap True North)
    else if (typeof event.alpha === "number") {
      heading = (360 - event.alpha) % 360;
    }

    if (heading !== null && !isNaN(heading)) {
      userHeading.value = Math.round(heading);
      updateHeadingDisplay(userHeading.value);
    }
  };

  // Izin sensor khusus iOS 13+
  if (typeof (DeviceOrientationEvent as any).requestPermission === "function") {
    (DeviceOrientationEvent as any).requestPermission()
      .then((permissionState: string) => {
        if (permissionState === "granted") {
          window.addEventListener("deviceorientation", handleOrientation, true);
          orientationListener = handleOrientation;
        }
      })
      .catch((err: any) => console.warn("Izin kompas orientasi:", err));
  } else {
    // Android / browser desktop
    window.addEventListener("deviceorientationabsolute", handleOrientation, true);
    window.addEventListener("deviceorientation", handleOrientation, true);
    orientationListener = handleOrientation;
  }
}

function stopLiveTracking() {
  hasUserLocation.value = false;
  isUserLocationVisible.value = false;
  userSpeed.value = null;
  userHeading.value = null;
  userAccuracy.value = null;
  lastReportedCoords = null;
  isFirstLocationLock = true;

  // Batalkan animasi lerp yang sedang berjalan
  if (geoAnimFrameId !== null) {
    cancelAnimationFrame(geoAnimFrameId);
    geoAnimFrameId = null;
  }
  geoAnimFrom = null;
  geoAnimTo = null;
  geoAnimStartTime = null;

  // Sembunyikan vector layer geolocation (pertahankan feature di memory agar toggle berikutnya responsif)
  if (geoVectorLayer) geoVectorLayer.setVisible(false);

  if (geolocationWatchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(geolocationWatchId);
    geolocationWatchId = null;
  }

  if (orientationListener) {
    window.removeEventListener("deviceorientationabsolute", orientationListener, true);
    window.removeEventListener("deviceorientation", orientationListener, true);
    orientationListener = null;
  }
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    mapContainer.value?.requestFullscreen().catch(() => {});
    isFullscreen.value = true;
  } else {
    document.exitFullscreen().catch(() => {});
    isFullscreen.value = false;
  }
}

async function initMap() {
  if (!import.meta.client) return;
  await nextTick();
  if (!mapContainer.value) return;

  try {
    const [
      { default: Map },
      { default: View },
      { default: TileLayer },
      { default: TileWMS },
      { default: VectorTileLayer },
      { default: VectorTileSource },
      { default: MVT },
      { default: OSM },
      { default: XYZ },
      projPkg,
      { default: Overlay },
      { default: ScaleLine },
      { defaults: defaultInteractions, MouseWheelZoom },
      { always },
      { default: VectorLayer },
      { default: VectorSource },
      { default: Feature },
      { default: PointGeom },
      { default: CircleGeom },
      { default: OlStyle },
      { default: CircleStyle },
      { default: Fill },
      { default: Stroke },
      { default: OlText },
      { default: RegularShape },
      { default: OlIcon },
      { default: Graticule },
    ] = await Promise.all([
      import("ol/Map.js"),
      import("ol/View.js"),
      import("ol/layer/Tile.js"),
      import("ol/source/TileWMS.js"),
      import("ol/layer/VectorTile.js"),
      import("ol/source/VectorTile.js"),
      import("ol/format/MVT.js"),
      import("ol/source/OSM.js"),
      import("ol/source/XYZ.js"),
      import("ol/proj.js"),
      import("ol/Overlay.js"),
      import("ol/control/ScaleLine.js"),
      import("ol/interaction.js"),
      import("ol/events/condition.js"),
      import("ol/layer/Vector.js"),
      import("ol/source/Vector.js"),
      import("ol/Feature.js"),
      import("ol/geom/Point.js"),
      import("ol/geom/Circle.js"),
      import("ol/style/Style.js"),
      import("ol/style/Circle.js"),
      import("ol/style/Fill.js"),
      import("ol/style/Stroke.js"),
      import("ol/style/Text.js"),
      import("ol/style/RegularShape.js"),
      import("ol/style/Icon.js"),
      import("ol/layer/Graticule.js"),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      TileWMS,
      VectorTileLayer,
      VectorTileSource,
      MVT,
      OlText,
      OlStyle,
      Fill,
      Stroke,
      Graticule,
      OSM,
      XYZ,
      fromLonLat: projPkg.fromLonLat,
      toLonLat: projPkg.toLonLat,
      transformExtent: projPkg.transformExtent,
      Overlay,
      ScaleLine,
    };

    tileLayer = new TileLayer({
      source: props.currentBasemap === "none" ? null : getBasemapSource(props.currentBasemap),
      visible: props.currentBasemap !== "none",
      zIndex: 0,
    });

    if (pulseEl.value) {
      pulseOverlay = new Overlay({
        element: pulseEl.value,
        positioning: "center-center",
        stopEvent: false,
      });
      if (props.clickedCoordinate) {
        const mercator = olModules.fromLonLat(props.clickedCoordinate);
        pulseOverlay.setPosition(mercator);
        isPulseVisible.value = true;
      }
    }

    // Geolocation VectorLayer: accuracy circle + standard blue dot + heading beam (Google Maps style)
    geoVectorSource = new VectorSource();

    const accuracyStyle = new OlStyle({
      fill: new Fill({ color: "rgba(37, 99, 235, 0.12)" }),
      stroke: new Stroke({ color: "rgba(37, 99, 235, 0.38)", width: 1 }),
    });

    const dotStyle = [
      // Outer subtle glow halo
      new OlStyle({
        image: new CircleStyle({
          radius: 10,
          fill: new Fill({ color: "rgba(37, 99, 235, 0.18)" }),
        }),
      }),
      // Outer crisp white ring
      new OlStyle({
        image: new CircleStyle({
          radius: 8,
          fill: new Fill({ color: "#ffffff" }),
        }),
      }),
      // Inner solid Google blue core
      new OlStyle({
        image: new CircleStyle({
          radius: 6,
          fill: new Fill({ color: "#1a73e8" }),
        }),
      }),
    ];

    const buildHeadingStyle = (heading: number | null) => {
      if (heading === null || heading === undefined) return [];
      const rad = (heading * Math.PI) / 180;
      return [
        new OlStyle({
          image: new OlIcon({
            src: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 90 90"><defs><radialGradient id="g" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="%231a73e8" stop-opacity="0.45"/><stop offset="60%" stop-color="%233b82f6" stop-opacity="0.18"/><stop offset="100%" stop-color="%2360a5fa" stop-opacity="0"/></radialGradient></defs><path d="M 45 45 L 20 6 A 45 45 0 0 1 70 6 Z" fill="url(%23g)"/></svg>',
            anchor: [0.5, 0.5],
            rotation: rad,
          }),
        }),
      ];
    };

    // Accuracy circle feature
    const dummyCenter = olModules.fromLonLat(BOJONEGORO_CENTER);
    geoAccuracyFeature = new Feature(new CircleGeom(dummyCenter, 30));
    geoAccuracyFeature.setStyle(accuracyStyle);

    // Dot feature
    geoPointFeature = new Feature(new PointGeom(dummyCenter));
    geoPointFeature.setStyle(dotStyle);

    // Heading beam cone feature
    geoHeadingFeature = new Feature(new PointGeom(dummyCenter));
    geoHeadingFeature.setStyle(buildHeadingStyle(null));

    // Store the builder so startLiveTracking and compass can update heading style
    olModules._buildHeadingStyle = buildHeadingStyle;

    geoVectorSource.addFeatures([geoAccuracyFeature, geoPointFeature, geoHeadingFeature]);

    geoVectorLayer = new VectorLayer({
      source: geoVectorSource,
      zIndex: 25,
      visible: false,
    });

    const scaleLineControl = new ScaleLine({
      target: scaleLineTarget.value || undefined,
      units: "metric",
      minWidth: 70,
      bar: false,
    });
    scaleLineControlInstance = scaleLineControl;

    // Inisialisasi posisi dan zoom dari props atau default Bojonegoro
    let initialCenter = olModules.fromLonLat(BOJONEGORO_CENTER);
    let initialZoomLevel = props.initialZoom ?? DEFAULT_ZOOM;

    if (props.initialCenter && props.initialCenter.length === 2) {
      initialCenter = olModules.fromLonLat(props.initialCenter);
    } else if (props.initialBbox && props.initialBbox.length === 4) {
      const [minLng, minLat, maxLng, maxLat] = props.initialBbox;
      initialCenter = olModules.fromLonLat([(minLng + maxLng) / 2, (minLat + maxLat) / 2]);
    }

    const overlaysList: any[] = [];
    if (pulseOverlay) overlaysList.push(pulseOverlay);

    // Pastikan scroll mouse langsung dapat melakukan zoom tanpa perlu klik fokus terlebih dahulu
    const defaultInteractionsList = defaultInteractions({
      onFocusOnly: false,
      mouseWheelZoom: false,
    });

    const mouseWheelZoomInteraction = new MouseWheelZoom({
      condition: always,
      onFocusOnly: false,
      useAnchor: true,
      duration: 150,
      timeout: 80,
    });

    // Formatters untuk koordinat pada tepi grid kartografi (Notasi ringkas E dan S)
    function formatLonGrid(lon: number): string {
      const norm = ((((lon + 180) % 360) + 360) % 360) - 180;
      const abs = Math.abs(norm);
      const deg = Math.floor(abs);
      const min = Math.floor((abs - deg) * 60);
      const sec = Math.round(((abs - deg) * 60 - min) * 60);
      const hemi = norm >= 0 ? "E" : "W";
      if (sec !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}'${String(sec).padStart(2, "0")}" ${hemi}`;
      }
      if (min !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}' ${hemi}`;
      }
      return `${deg}° ${hemi}`;
    }

    function formatLatGrid(lat: number): string {
      const norm = Math.max(-90, Math.min(90, lat));
      const abs = Math.abs(norm);
      const deg = Math.floor(abs);
      const min = Math.floor((abs - deg) * 60);
      const sec = Math.round(((abs - deg) * 60 - min) * 60);
      const hemi = norm >= 0 ? "N" : "S";
      if (sec !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}'${String(sec).padStart(2, "0")}" ${hemi}`;
      }
      if (min !== 0) {
        return `${deg}°${String(min).padStart(2, "0")}' ${hemi}`;
      }
      return `${deg}° ${hemi}`;
    }

    const isCurrentDark = document.documentElement.classList.contains("dark");
    graticuleLayer = new Graticule({
      strokeStyle: new Stroke({
        color: isCurrentDark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.4)",
        width: 1,
        lineDash: [4, 4],
      }),
      showLabels: true,
      wrapX: false,
      zIndex: 40,
      lonLabelPosition: 0,
      latLabelPosition: 1,
      lonLabelFormatter: formatLonGrid,
      latLabelFormatter: formatLatGrid,
      lonLabelStyle: new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textBaseline: "bottom",
        textAlign: "center",
        fill: new Fill({ color: isCurrentDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isCurrentDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetY: -8,
      }),
      latLabelStyle: new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textAlign: "end",
        textBaseline: "middle",
        fill: new Fill({ color: isCurrentDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isCurrentDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetX: -8,
      }),
      visible: showCartoGrid.value,
    });

    function updateGraticuleTheme() {
      if (!graticuleLayer) return;
      const isDark = document.documentElement.classList.contains("dark");
      graticuleLayer.strokeStyle_ = new Stroke({
        color: isDark ? "rgba(148, 163, 184, 0.45)" : "rgba(71, 85, 105, 0.4)",
        width: 1,
        lineDash: [4, 4],
      });
      graticuleLayer.lonLabelStyle_ = new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textBaseline: "bottom",
        textAlign: "center",
        fill: new Fill({ color: isDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetY: -8,
      });
      graticuleLayer.latLabelStyle_ = new OlText({
        font: "600 10.5px Inter, -apple-system, sans-serif",
        textAlign: "end",
        textBaseline: "middle",
        fill: new Fill({ color: isDark ? "#f1f5f9" : "#1e293b" }),
        stroke: new Stroke({ color: isDark ? "rgba(15, 23, 42, 0.9)" : "rgba(255, 255, 255, 0.95)", width: 3 }),
        offsetX: -8,
      });
      graticuleLayer.changed();
    }

    themeMutationObserver = new MutationObserver(() => {
      updateGraticuleTheme();
    });
    themeMutationObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"],
    });

    mapInstance = new Map({
      target: mapContainer.value,
      layers: [tileLayer, geoVectorLayer, graticuleLayer],
      view: new View({
        center: initialCenter,
        zoom: initialZoomLevel,
        minZoom: 6,
        maxZoom: 19,
      }),
      overlays: overlaysList,
      controls: [scaleLineControl],
      interactions: defaultInteractionsList.extend([mouseWheelZoomInteraction]),
    });

    // Sesuaikan extent jika initialBbox tersedia
    if (props.initialBbox && props.initialBbox.length === 4) {
      const extent3857 = olModules.transformExtent(props.initialBbox, "EPSG:4326", "EPSG:3857");
      mapInstance.getView().fit(extent3857, {
        size: mapInstance.getSize(),
        padding: [10, 10, 10, 10],
        maxZoom: props.initialZoom ? Math.round(props.initialZoom) : 18,
      });
    }

    // Inisialisasi posisi center dan zoom status bar awal
    updateMapCenterAndZoom();

    // Listener pergerakan dan zoom peta untuk sinkronisasi URL parameter
    mapInstance.on("moveend", () => {
      emitViewportUpdate();
      updateMapCenterAndZoom();
    });

    // Sinkronkan skala rasio peta secara langsung saat user melakukan zoom / pinch
    mapInstance.getView().on("change:resolution", () => {
      updateMapCenterAndZoom();
    });

    // Pointer move listener for live mouse coordinates & feature hover detection
    mapInstance.on("pointermove", (evt: any) => {
      if (evt.dragging) return;
      const coords = olModules.toLonLat(evt.coordinate);
      if (coords && coords.length >= 2) {
        const numLng = Number(coords[0]);
        const numLat = Number(coords[1]);
        livePointerCoords.value = { lng: numLng, lat: numLat };
        isPointerOnCanvas.value = true;

        const lng = numLng.toFixed(5);
        const lat = numLat.toFixed(5);
        emit("update:mouse-coords", `${lat}, ${lng}`);
      }

      if (evt.pixel) {
        updateCursorOnHover(evt.pixel);
      }
    });

    // Map click handler - Query data fitur WMS HANYA saat user melakukan klik
    mapInstance.on("singleclick", (evt: any) => {
      // Abaikan klik yang berasal dari tombol tutup popup
      if (isDismissing) return;

      const coords = olModules.toLonLat(evt.coordinate);
      if (coords && coords.length >= 2) {
        const lng = Number(coords[0]);
        const lat = Number(coords[1]);

        clickAnimKey.value++;
        activeNamobj.value = null;
        if (pulseOverlay) {
          pulseOverlay.setPosition(evt.coordinate);
          isPulseVisible.value = true;
          isBadgeVisible.value = true;
        }

        emit("map-click", [lng, lat]);
        performWmsIdentify(evt.coordinate);
      }
    });

    // If initial coordinate is provided, show pulse marker
    if (props.clickedCoordinate) {
      const mercator = olModules.fromLonLat(props.clickedCoordinate);
      if (pulseOverlay) {
        pulseOverlay.setPosition(mercator);
        isPulseVisible.value = true;
        isBadgeVisible.value = true;
      }
    }

    mapLoaded.value = true;
    syncActiveWmsLayers();
    if (mapInstance) {
      mapInstance.once("rendercomplete", () => {
        emit("loaded");
      });
      setTimeout(() => {
        emit("loaded");
      }, 750);
    } else {
      emit("loaded");
    }
    emitViewportUpdate();
  } catch (err) {
    console.error("Gagal menginisialisasi peta OpenLayers:", err);
  }
}

function syncActiveWmsLayers() {
  if (!mapInstance || !olModules) return;
  const { TileLayer, TileWMS, VectorTileLayer, VectorTileSource, MVT, OlStyle, Stroke, Fill, OlText } = olModules;
  const currentLayers = props.activeLayers || [];
  const currentLayerIds = new Set(currentLayers.map((l) => l.id));
  const config = useRuntimeConfig();
  const martinBase = (config.public?.martinUrl as string) || "/martin";

  // 1. Hapus layer yang tidak lagi aktif
  for (const [id, olLayer] of wmsLayersMap.entries()) {
    if (!currentLayerIds.has(id)) {
      mapInstance.removeLayer(olLayer);
      wmsLayersMap.delete(id);
    }
  }
  for (const [id, olLayer] of mvtLayersMap.entries()) {
    if (!currentLayerIds.has(id)) {
      mapInstance.removeLayer(olLayer);
      mvtLayersMap.delete(id);
    }
  }

  // 2. Tambahkan atau perbarui status layer
  currentLayers.forEach((layer, index) => {
    const isMvt =
      layer.protocol?.toUpperCase() === "MVT" ||
      layer.source_type === "martin" ||
      Boolean(layer.mvtUrl) ||
      Boolean(layer.martinSource) ||
      (layer.layer_name && ["infrastruktur_segmen", "jalan_porosdesa", "bataswilayah_kabupaten"].includes(layer.layer_name)) ||
      (layer.url && layer.url.includes("/martin/"));

    // A. Handle Martin MVT Layer
    if (isMvt) {
      const sourceName =
        layer.martinSource ||
        layer.layer_name ||
        (layer.url ? layer.url.match(/\/martin\/([^\/]+)/)?.[1] : undefined) ||
        (layer.id.includes("segmen") ? "infrastruktur_segmen" : "jalan_porosdesa");

      const tileUrl = layer.mvtUrl || `${martinBase}/${sourceName}/{z}/{x}/{y}`;
      const isKabupaten =
        sourceName === "bataswilayah_kabupaten" ||
        layer.layer_name === "bataswilayah_kabupaten" ||
        layer.id === "01a15e28-a042-71a0-b111-ed7d9256e5bf" ||
        Boolean(layer.url && layer.url.includes("bataswilayah_kabupaten")) ||
        Boolean(layer.mvtUrl && layer.mvtUrl.includes("bataswilayah_kabupaten"));

      const isSegmen = !isKabupaten && (sourceName === "infrastruktur_segmen" || layer.id.includes("segmen"));

      // Urutan z-index dinamis: Batas wilayah kabupaten selalu di lapisan dasar (z-index 4), jalan di atasnya (z-index 10+)
      const layerZIndex = isKabupaten ? 4 : 10 + (currentLayers.length - index) * 2;
      const effectiveOpacity = typeof layer.opacity === "number" ? layer.opacity : 1;

      // ─── 1. Standar Kartografi Batas Administrasi Kabupaten (BIG / Peta RBI Dati II) ───
      const createKabupatenStyleFn = (_targetOlLayer: any) => {
        return (_feature?: any) => {
          const currentZoom = mapInstance?.getView()?.getZoom() || 0;
          const isDark = document.documentElement.classList.contains("dark");

          return [
            // Casing / underlay halo batas kabupaten (efek kartografi batas wilayah teritorial)
            new OlStyle({
              stroke: new Stroke({
                color: isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(71, 85, 105, 0.22)",
                width: currentZoom >= 13 ? 7.5 : currentZoom >= 11 ? 5.5 : 4,
                lineCap: "round",
                lineJoin: "round",
              }),
              fill: new Fill({
                color: isDark ? "rgba(148, 163, 184, 0.03)" : "rgba(71, 85, 105, 0.015)",
              }),
            }),
            // Garis utama batas kartografi kabupaten (Standar BIG: Dash-Dot-Dot-Dash warna abu-abu gelap)
            new OlStyle({
              stroke: new Stroke({
                color: isDark ? "#94a3b8" : "#475569",
                width: currentZoom >= 13 ? 2.5 : currentZoom >= 11 ? 2 : 1.75,
                lineDash: [12, 4, 2, 4, 2, 4],
                lineCap: "round",
                lineJoin: "round",
              }),
              zIndex: 4,
            }),
          ];
        };
      };

      // ─── 2. Generator Gaya MVT Ruas & Segmen Jalan Berdasarkan Kondisi ───
      const createMvtStyleFn = (targetOlLayer: any, isLayerSegmen: boolean) => {
        return (feature: any) => {
          // 1. Filter Wilayah (Kecamatan)
          const targetKec = (targetOlLayer.get("filterKecamatan") || "").trim().toLowerCase();
          if (targetKec && targetKec !== "all") {
            const rawFeatKec = String(
              feature.get("kecamatan") ||
              feature.get("WADMKC") ||
              feature.get("wadmkc") ||
              feature.get("nama_kecamatan") ||
              ""
            ).trim().toLowerCase();

            const cleanTarget = targetKec.replace(/^(kecamatan|kec\.)\s+/i, "").trim();
            const cleanFeat = rawFeatKec.replace(/^(kecamatan|kec\.)\s+/i, "").trim();

            if (!cleanFeat || (cleanFeat !== cleanTarget && !cleanFeat.includes(cleanTarget) && !cleanTarget.includes(cleanFeat))) {
              return null;
            }
          }

          // 2. Filter Status Kondisi
          const targetStatus = (targetOlLayer.get("filterStatus") || "").trim().toLowerCase();
          if (targetStatus && targetStatus !== "all") {
            const featKondisi = String(feature.get("kondisi") || feature.get("status") || "").trim().toLowerCase();
            if (!featKondisi.includes(targetStatus)) {
              return null;
            }
          }

          const kondisi = String(feature.get("kondisi") || "").toLowerCase();
          let strokeColor = isLayerSegmen ? "#10b981" : "#2563eb";

          if (kondisi.includes("rusak berat")) strokeColor = "#ef4444";
          else if (kondisi.includes("rusak ringan")) strokeColor = "#f97316";
          else if (kondisi.includes("sedang")) strokeColor = "#f59e0b";
          else if (kondisi.includes("rusak")) strokeColor = "#f97316";
          else if (kondisi.includes("baik")) strokeColor = "#10b981";

          let textStyle: any = undefined;
          const currentZoom = mapInstance?.getView()?.getZoom() || 0;
          if (currentZoom >= 13.5) {
            const labelText = String(feature.get(isLayerSegmen ? "namobj" : "nama_ruas") || feature.get("nama_ruas") || feature.get("namobj") || "");
            if (labelText && labelText !== "-") {
              textStyle = new OlText({
                text: labelText,
                font: "600 11px Inter, sans-serif",
                fill: new Fill({ color: isLayerSegmen ? "#064e3b" : "#1e3a8a" }),
                stroke: new Stroke({ color: "#ffffff", width: 3 }),
                placement: "line",
                overflow: false,
              });
            }
          }

          return new OlStyle({
            stroke: new Stroke({
              color: strokeColor,
              width: currentZoom >= 14 ? (isLayerSegmen ? 4 : 2.5) : (isLayerSegmen ? 3 : 1.75),
              lineCap: "round",
              lineJoin: "round",
            }),
            text: textStyle,
            zIndex: isLayerSegmen ? 100 : 10,
          });
        };
      };

      if (mvtLayersMap.has(layer.id)) {
        const olLayer = mvtLayersMap.get(layer.id);
        olLayer.setVisible(layer.visible ?? true);
        olLayer.setOpacity(effectiveOpacity);
        olLayer.setZIndex(layerZIndex);

        if (isKabupaten) {
          olLayer.setStyle(createKabupatenStyleFn(olLayer));
        } else {
          // Pantau jika filterKecamatan atau filterStatus berubah agar render ulang style
          const prevKec = olLayer.get("filterKecamatan");
          const prevStatus = olLayer.get("filterStatus");
          if (prevKec !== layer.filterKecamatan || prevStatus !== layer.filterStatus) {
            olLayer.set("filterKecamatan", layer.filterKecamatan);
            olLayer.set("filterStatus", layer.filterStatus);
            olLayer.setStyle(createMvtStyleFn(olLayer, isSegmen));
            olLayer.changed();
          }
        }
      } else {
        try {
          const source = new VectorTileSource({
            format: new MVT({ idProperty: "id" }),
            url: tileUrl,
            maxZoom: 20,
            minZoom: isKabupaten ? 5 : 7,
            cacheSize: 512,
          });

          const olLayer = new VectorTileLayer({
            source,
            renderMode: "hybrid",
            declutter: true,
            opacity: effectiveOpacity,
            visible: layer.visible ?? true,
            zIndex: layerZIndex,
          });

          if (isKabupaten) {
            olLayer.setStyle(createKabupatenStyleFn(olLayer));
          } else {
            olLayer.set("filterKecamatan", layer.filterKecamatan);
            olLayer.set("filterStatus", layer.filterStatus);
            olLayer.setStyle(createMvtStyleFn(olLayer, isSegmen));
          }

          mapInstance.addLayer(olLayer);
          mvtLayersMap.set(layer.id, olLayer);
        } catch (err) {
          console.error(`Gagal memuat layer Martin MVT [${layer.title}]:`, err);
        }
      }
      return;
    }

    // B. Handle WMS Layer
    const effectiveWmsUrl = layer.wmsUrl || layer.url;
    const effectiveWmsLayerName = layer.wmsLayerName || layer.layer_name;

    if (effectiveWmsUrl && effectiveWmsLayerName) {
      const layerZIndex = 10 + (currentLayers.length - index) * 2;
      if (wmsLayersMap.has(layer.id)) {
        const olLayer = wmsLayersMap.get(layer.id);
        olLayer.setVisible(layer.visible);
        olLayer.setOpacity(layer.opacity ?? 1);
        olLayer.setZIndex(layerZIndex);
      } else {
        try {
          const source = new TileWMS({
            url: effectiveWmsUrl,
            crossOrigin: "anonymous",
            params: {
              LAYERS: effectiveWmsLayerName,
              TILED: true,
              VERSION: "1.1.1",
              FORMAT: "image/png",
              TRANSPARENT: true,
            },
            serverType: "geoserver",
            transition: 0,
          });

          const olLayer = new TileLayer({
            source,
            opacity: layer.opacity ?? 1,
            visible: layer.visible ?? true,
            zIndex: layerZIndex,
          });

          mapInstance.addLayer(olLayer);
          wmsLayersMap.set(layer.id, olLayer);
        } catch (err) {
          console.error(`Gagal memuat layer WMS [${layer.title || layer.name}]:`, err);
        }
      }
    }
  });
}

async function performWmsIdentify(olCoord: any) {
  if (!mapInstance || !olModules) return;
  const currentLayers = props.activeLayers || [];
  const visibleWmsLayers = currentLayers.filter(
    (l) => (l.wmsUrl || l.url) && (l.wmsLayerName || l.layer_name) && l.visible !== false
  );

  activeNamobj.value = null;
  isIdentifyingFeatures.value = true;
  emit("identifying-features", true);

  // 1. Cek fitur vektor langsung dari Martin MVT layer pada titik piksel klik
  const vectorIdentified: IdentifiedFeature[] = [];
  try {
    const pixel = mapInstance.getPixelFromCoordinate(olCoord);
    if (pixel) {
      mapInstance.forEachFeatureAtPixel(
        pixel,
        (feature: any, layer: any) => {
          for (const [id, olLayer] of mvtLayersMap.entries()) {
            if (olLayer === layer) {
              const styleFn = olLayer.getStyleFunction();
              if (styleFn) {
                const computedStyle = styleFn(feature, mapInstance.getView().getResolution());
                if (!computedStyle) return;
              }
              const match = currentLayers.find((l) => l.id === id);
              if (match && match.visible !== false) {
                const rawProps = feature.getProperties() || {};
                const cleanProps: Record<string, any> = {};
                for (const [k, v] of Object.entries(rawProps)) {
                  if (k !== "geometry" && v !== undefined && v !== null && v !== "") {
                    cleanProps[k] = v;
                  }
                }
                vectorIdentified.push({
                  id: String(feature.getId?.() || cleanProps.id || Math.random()),
                  layerId: id,
                  layerTitle: match.title || match.name || id,
                  layerAttribution: match.instansi || match.attribution || match.agency,
                  layerInstansi: match.instansi || match.attribution || match.agency,
                  geometryType: feature.getGeometry?.()?.getType?.() || "LineString",
                  properties: cleanProps,
                  coordinate: olModules.toLonLat(olCoord),
                });
              }
              break;
            }
          }
        },
        {
          hitTolerance: 6,
          layerFilter: (l: any) => l !== tileLayer && !wmsLayersMap.has(l),
        }
      );
    }
  } catch (err) {
    console.warn("Gagal membaca fitur MVT at pixel:", err);
  }

  // Prioritaskan fitur garis ruas/segmen atau titik di atas poligon wilayah agar jalan teridentifikasi lebih dahulu
  vectorIdentified.sort((a, b) => {
    const isPolyA = (a.geometryType || "").toLowerCase().includes("polygon");
    const isPolyB = (b.geometryType || "").toLowerCase().includes("polygon");
    if (isPolyA && !isPolyB) return 1;
    if (!isPolyA && isPolyB) return -1;
    return 0;
  });

  // Jika tidak ada layer WMS yang aktif dan fitur vektor sudah ditemukan atau kosong
  if (visibleWmsLayers.length === 0) {
    if (vectorIdentified.length > 0) {
      activeNamobj.value = extractNamobj(vectorIdentified[0].properties);
    }
    await new Promise((resolve) => setTimeout(resolve, 150));
    isIdentifyingFeatures.value = false;
    emit("identifying-features", false);
    emit("identify-features", vectorIdentified);
    return;
  }

  const view = mapInstance.getView();
  const resolution = view.getResolution();
  const projection = view.getProjection();

  // Jalankan query seluruh layer WMS aktif secara paralel (Promise.allSettled)
  const queries = visibleWmsLayers.map(async (layer) => {
    const olLayer = wmsLayersMap.get(layer.id);
    if (!olLayer) return [];
    const source = olLayer.getSource();
    if (!source || typeof source.getFeatureInfoUrl !== "function") return [];

    const queryLayerName = layer.wmsLayerName || layer.layer_name;
    const url = source.getFeatureInfoUrl(olCoord, resolution, projection, {
      INFO_FORMAT: "application/json",
      QUERY_LAYERS: queryLayerName,
      FEATURE_COUNT: 5,
      BUFFER: 10,
    });

    if (!url) return [];

    try {
      let data: any = null;

      // 1. Coba direct fetch langsung dari browser (~80ms, GeoServer mendukung Access-Control-Allow-Origin: *)
      try {
        const directRes = await fetch(url, {
          signal: AbortSignal.timeout ? AbortSignal.timeout(2000) : undefined,
        });
        if (directRes.ok) {
          data = await directRes.json();
        }
      } catch {
        // 2. Fallback otomatis ke proxy backend jika direct fetch mengalami kendala
        data = await $http("wms/feature-info", {
          query: { url },
        });
      }

      if (data && Array.isArray(data.features) && data.features.length > 0) {
        return data.features.map((f: any) => ({
          id: f.id,
          layerId: layer.id,
          layerTitle: layer.title || layer.name,
          layerAttribution: layer.instansi || layer.attribution || layer.agency,
          layerInstansi: layer.instansi || layer.attribution || layer.agency,
          geometryType: f.geometry?.type,
          properties: f.properties || {},
          coordinate: olModules.toLonLat(olCoord),
        }));
      }
      return [];
    } catch (err) {
      console.warn(`Identify WMS gagal untuk layer [${layer.title}]:`, err);
      return [];
    }
  });

  // Berikan sedikit jeda animasi (250ms) agar efek visual radar & ripple pada peta serta status loading berjalan mulus tanpa flickering
  const minDelayPromise = new Promise((resolve) => setTimeout(resolve, 250));

  const [settled] = await Promise.all([
    Promise.allSettled(queries),
    minDelayPromise,
  ]);

  const identifiedList: IdentifiedFeature[] = [...vectorIdentified];

  for (const item of settled) {
    if (item.status === "fulfilled" && Array.isArray(item.value)) {
      identifiedList.push(...item.value);
    }
  }

  // Tampilkan namobj dari fitur yang teridentifikasi ke popup koordinat
  if (identifiedList.length > 0) {
    activeNamobj.value = extractNamobj(identifiedList[0].properties);
  } else {
    activeNamobj.value = null;
  }

  isIdentifyingFeatures.value = false;
  emit("identifying-features", false);
  emit("identify-features", identifiedList);
}

function emitViewportUpdate() {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const size = mapInstance.getSize();
  if (!size) return;

  const extent3857 = view.calculateExtent(size);
  const bbox4326 = olModules.transformExtent(extent3857, "EPSG:3857", "EPSG:4326");
  const rawZoom = view.getZoom() ?? DEFAULT_ZOOM;
  const zoom = Math.round(rawZoom * 100) / 100;
  const center4326 = olModules.toLonLat(view.getCenter());

  emit("update:viewport", {
    bbox: [
      Number(bbox4326[0].toFixed(5)),
      Number(bbox4326[1].toFixed(5)),
      Number(bbox4326[2].toFixed(5)),
      Number(bbox4326[3].toFixed(5)),
    ],
    zoom,
    center: [
      Number(center4326[0].toFixed(5)),
      Number(center4326[1].toFixed(5)),
    ],
  });
}

function fitBbox(bbox: [number, number, number, number], targetZoom?: number, duration = 0) {
  if (!mapInstance || !olModules) return;
  const extent3857 = olModules.transformExtent(bbox, "EPSG:4326", "EPSG:3857");
  const view = mapInstance.getView();
  view.fit(extent3857, {
    size: mapInstance.getSize(),
    padding: [10, 10, 10, 10],
    duration,
    maxZoom: targetZoom ?? 19,
  });
}

function getViewport(): MapViewportPayload | null {
  if (!mapInstance || !olModules) return null;
  const view = mapInstance.getView();
  const size = mapInstance.getSize();
  if (!size) return null;

  const extent3857 = view.calculateExtent(size);
  const bbox4326 = olModules.transformExtent(extent3857, "EPSG:3857", "EPSG:4326");
  const rawZoom = view.getZoom() ?? DEFAULT_ZOOM;
  const zoom = Math.round(rawZoom * 100) / 100;
  const center4326 = olModules.toLonLat(view.getCenter());

  return {
    bbox: [
      Number(bbox4326[0].toFixed(5)),
      Number(bbox4326[1].toFixed(5)),
      Number(bbox4326[2].toFixed(5)),
      Number(bbox4326[3].toFixed(5)),
    ],
    zoom,
    center: [
      Number(center4326[0].toFixed(5)),
      Number(center4326[1].toFixed(5)),
    ],
  };
}

onMounted(() => {
  initMap();

  document.addEventListener("fullscreenchange", () => {
    isFullscreen.value = Boolean(document.fullscreenElement);
    if (mapInstance) {
      setTimeout(() => mapInstance.updateSize(), 150);
    }
  });

  const resizeObserver = new ResizeObserver(() => {
    if (mapInstance) {
      mapInstance.updateSize();
    }
  });
  if (mapContainer.value) {
    resizeObserver.observe(mapContainer.value);
  }

  onUnmounted(() => {
    stopLiveTracking();
    resizeObserver.disconnect();
    if (themeMutationObserver) {
      themeMutationObserver.disconnect();
      themeMutationObserver = null;
    }
    if (mapInstance) {
      mapInstance.setTarget(undefined);
      mapInstance = null;
    }
  });
});

function toggleCartoGrid() {
  showCartoGrid.value = !showCartoGrid.value;
  if (graticuleLayer) {
    graticuleLayer.setVisible(showCartoGrid.value);
  }
}

function setCenterAndZoom(center: [number, number], zoom?: number, animate = false) {
  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  const mercator = olModules.fromLonLat(center);
  if (animate) {
    view.animate({
      center: mercator,
      zoom: zoom ?? view.getZoom(),
      duration: 300,
    });
  } else {
    view.setCenter(mercator);
    if (typeof zoom === "number") {
      view.setZoom(zoom);
    }
  }
}

let lastHoverState = false;

function updateCursorOnHover(pixel: [number, number]) {
  if (!mapInstance) return;
  const viewport = mapInstance.getViewport();
  if (!viewport) return;

  let hasFeature = false;

  // 1. Cek layer vektor jika ada
  try {
    hasFeature = Boolean(
      mapInstance.hasFeatureAtPixel(pixel, {
        layerFilter: (layer: any) => layer !== tileLayer,
      })
    );
  } catch {
    hasFeature = false;
  }

  // 2. Cek layer WMS jika belum terdeteksi dari vektor
  if (!hasFeature && wmsLayersMap.size > 0) {
    const offsets = [
      [0, 0],
      [-2, 0],
      [2, 0],
      [0, -2],
      [0, 2],
      [-2, -2],
      [2, 2],
      [-2, 2],
      [2, -2],
    ];

    for (const [_, olLayer] of wmsLayersMap.entries()) {
      if (!olLayer || !olLayer.getVisible()) continue;
      try {
        for (const [dx, dy] of offsets) {
          const testPixel = [pixel[0] + dx, pixel[1] + dy] as [number, number];
          const data = olLayer.getData(testPixel);
          if (data && data[3] > 15) {
            hasFeature = true;
            break;
          }
        }
      } catch {
        // Fallback jika browser membatasi getImageData pada canvas
      }
      if (hasFeature) break;
    }
  }

  if (hasFeature !== lastHoverState) {
    lastHoverState = hasFeature;
    if (hasFeature) {
      viewport.classList.add("cursor-pointer-active");
    } else {
      viewport.classList.remove("cursor-pointer-active");
    }
  }
}

function handleMapMouseEnter() {
  isPointerOnCanvas.value = true;
  if (mapContainer.value && document.activeElement !== mapContainer.value) {
    mapContainer.value.focus({ preventScroll: true });
  }
}

function handleMapMouseLeave() {
  isPointerOnCanvas.value = false;
  if (mapInstance) {
    const viewport = mapInstance.getViewport();
    if (viewport) {
      viewport.classList.remove("cursor-pointer-active");
    }
    lastHoverState = false;
  }
}

onBeforeUnmount(() => {
  if (dismissTimer) clearTimeout(dismissTimer);
  stopLiveTracking();
  if (geoVectorSource) geoVectorSource.clear();
  geoAccuracyFeature = null;
  geoPointFeature = null;
  geoHeadingFeature = null;
});

async function getMapSnapshot(): Promise<string | null> {
  return new Promise((resolve) => {
    if (!mapInstance || !mapContainer.value) return resolve(null);
    try {
      const size = mapInstance.getSize();
      if (!size) return resolve(null);
      const [width, height] = size;
      const exportCanvas = document.createElement("canvas");
      exportCanvas.width = width;
      exportCanvas.height = height;
      const ctx = exportCanvas.getContext("2d");
      if (!ctx) return resolve(null);

      const canvases = mapContainer.value.querySelectorAll(".ol-layer canvas, canvas.ol-layer");
      if (canvases.length > 0) {
        canvases.forEach((c) => {
          const canvasEl = c as HTMLCanvasElement;
          if (canvasEl.width > 0 && canvasEl.height > 0) {
            const parent = canvasEl.parentNode as HTMLElement | null;
            const opacity = parent?.style.opacity || canvasEl.style.opacity || "1";
            ctx.globalAlpha = parseFloat(opacity) || 1;
            const transform = canvasEl.style.transform;
            if (transform) {
              const matrixMatch = transform.match(/^matrix\(([^\(]*)\)$/);
              if (matrixMatch) {
                const m = matrixMatch[1].split(",").map(Number);
                ctx.setTransform(m[0], m[1], m[2], m[3], m[4], m[5]);
              }
            }
            ctx.drawImage(canvasEl, 0, 0);
          }
        });
        ctx.setTransform(1, 0, 0, 1, 0, 0);
      }
      resolve(exportCanvas.toDataURL("image/png"));
    } catch (err) {
      console.warn("Snapshot notice (cross-origin fallback):", err);
      resolve(null);
    }
  });
}

defineExpose({
  zoomIn,
  zoomOut,
  resetView,
  flyTo,
  locateUser,
  fitBbox,
  setCenterAndZoom,
  getViewport,
  dismissPulse,
  getMapSnapshot,
  mapInstance: () => mapInstance,
});
</script>

<template>
  <div
    class="relative w-full h-full select-none overflow-hidden transition-colors duration-200"
    :class="currentBasemap === 'none' ? 'bg-white' : 'bg-gray-100 dark:bg-[#070b14]'"
  >
    <!-- OpenLayers Map Target Container (Default panah, pointer saat menyorot feature data) -->
    <div
      ref="mapContainer"
      class="w-full h-full cursor-default focus:outline-hidden transition-colors duration-200"
      :class="currentBasemap === 'none' ? 'bg-white' : ''"
      tabindex="0"
      aria-label="Peta Interaktif Kabupaten Bojonegoro"
      @mouseenter="handleMapMouseEnter"
      @mouseleave="handleMapMouseLeave"
    />

    <!-- ═══ PULSE MARKER OVERLAY (SEARCHED / CLICKED LOCATION) ═══════════════ -->
    <div
      ref="pulseEl"
      class="pointer-events-none z-30 select-none relative size-0"
      :class="isPulseVisible ? 'block' : 'hidden'"
    >
      <!-- Floating Label Badge above the Pin -->
      <div class="absolute bottom-[44px] left-0 -translate-x-1/2 flex flex-col items-center pointer-events-none">
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-3.5 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 translate-y-3.5 scale-95"
          @after-leave="onBadgeAfterLeave"
        >
          <div
            v-if="isBadgeVisible && (effectivePopupTitle || clickedCoordinate)"
            class="flex flex-col items-center pointer-events-auto"
          >
            <!-- Badge Pill Card -->
            <div
              class="group relative flex items-center justify-between gap-2.5 px-3.5 py-1.5 rounded-xl border backdrop-blur-xl transition-all shadow-xl min-w-[200px] max-w-[280px]"
              :class="[
                'bg-white/95 text-slate-800 border-slate-200/90 shadow-slate-900/10',
                'dark:bg-[#09111e]/95 dark:text-white dark:border-white/20 dark:shadow-2xl dark:shadow-black/70'
              ]"
            >
              <!-- Left content: Indicator + Texts -->
              <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <!-- Glowing Dot Indicator -->
                <span class="relative flex size-2 shrink-0">
                  <span
                    class="absolute inline-flex h-full w-full rounded-full opacity-75"
                    :class="isIdentifyingFeatures ? 'bg-amber-400 animate-ping' : 'bg-blue-400 animate-ping'"
                  />
                  <span
                    class="relative inline-flex size-2 rounded-full"
                    :class="isIdentifyingFeatures ? 'bg-amber-500' : 'bg-blue-600 dark:bg-blue-400'"
                  />
                </span>

                <!-- Label & Subtitle -->
                <div class="flex flex-col min-w-0 flex-1">
                  <span class="text-xs font-semibold tracking-tight truncate leading-tight">
                    {{ effectivePopupTitle }}
                  </span>
                  <span
                    v-if="clickedCoordinate"
                    class="text-[10px] font-mono leading-tight text-slate-500 dark:text-slate-400 mt-0.5"
                  >
                    {{ clickedCoordinate[1].toFixed(5) }}, {{ clickedCoordinate[0].toFixed(5) }}
                  </span>
                </div>
              </div>

              <!-- Close Button (Min 44x44px touch target on mobile per antislop-layoutmobile) -->
              <button
                type="button"
                class="relative -mr-1 ml-1.5 shrink-0 flex items-center justify-center size-7 sm:size-6 rounded-lg transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-95 touch-manipulation before:absolute before:inset-[-8px] before:content-['']"
                :class="[
                  'text-slate-400 hover:text-slate-700 hover:bg-slate-100 active:bg-slate-200',
                  'dark:text-slate-500 dark:hover:text-slate-200 dark:hover:bg-white/10 dark:active:bg-white/20'
                ]"
                aria-label="Tutup popup lokasi"
                @pointerdown.stop
                @mousedown.stop
                @touchstart.stop.prevent
                @touchend.stop="dismissPulse"
                @click.stop="dismissPulse"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="size-3.5 stroke-current" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
              </button>
            </div>

            <!-- Bottom Caret Arrow pointing to Pin Core -->
            <div
              class="size-2 -mt-1 rotate-45 border-r border-b transition-colors"
              :class="[
                'bg-white border-slate-200/90',
                'dark:bg-[#09111e] dark:border-white/20'
              ]"
            />
          </div>
        </Transition>
      </div>

      <!-- Singleclick pin: teardrop SVG shape with crosshair, blue = query/action -->
      <div :key="clickAnimKey" class="absolute inset-0 pointer-events-none">
        <!-- Ground pinpoint dot directly on the exact clicked coordinate (0, 0) -->
        <span class="absolute size-1.5 rounded-full bg-blue-600 dark:bg-blue-400 -translate-x-1/2 -translate-y-1/2" />

        <!-- Ripple shockwave ring originating from exact click point -->
        <span class="absolute left-0 top-0 -translate-x-1/2 -translate-y-1/2 size-12 flex items-center justify-center pointer-events-none">
          <span
            class="size-full rounded-full ring-wave pointer-events-none border border-blue-500/50 bg-blue-500/10 dark:border-blue-400/60 dark:bg-blue-400/15"
          />
        </span>

        <!-- Outer radar ping centered on (0, 0) -->
        <span
          class="absolute size-8 rounded-full animate-ping -translate-x-1/2 -translate-y-1/2"
          style="animation-duration: 1.4s;"
          :class="[
            'bg-blue-500/20 border border-blue-500/30',
            'dark:bg-blue-400/25 dark:border-blue-400/40'
          ]"
        />

        <!-- Teardrop pin SVG: anchored at bottom tip (x=14, y=36) directly onto (0, 0) -->
        <div class="absolute left-0 bottom-0 -translate-x-1/2">
          <div class="scale-animation" style="transform-origin: 50% 100%;">
            <svg width="28" height="36" viewBox="0 0 28 36" fill="none" xmlns="http://www.w3.org/2000/svg" class="drop-shadow-md block overflow-visible">
              <!-- Pin body: tip is at (14, 36) -->
              <path
                d="M14 0C6.268 0 0 6.268 0 14c0 9.188 12.375 20.813 13.406 21.781a.813.813 0 0 0 1.188 0C15.625 34.813 28 23.188 28 14 28 6.268 21.732 0 14 0z"
                class="fill-blue-600 dark:fill-blue-500"
              />
              <!-- Crosshair lines inside pin -->
              <line x1="14" y1="7" x2="14" y2="21" stroke="white" stroke-width="1.5" stroke-linecap="round" opacity="0.9"/>
              <line x1="7" y1="14" x2="21" y2="14" stroke="white" stroke-width="1.5" stroke-linecap="round" opacity="0.9"/>
              <!-- Center dot -->
              <circle cx="14" cy="14" r="2.5" fill="white" opacity="0.95"/>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Floating Navigation Controls (Bottom Right with Dynamic Mobile Clearance) -->
    <div
      class="absolute right-3 md:right-4 z-20 flex flex-col items-end gap-1.5 pointer-events-none transition-all duration-[580ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)]"
      :class="[
        mobileDrawerOpen && mobileSheetSnap === 'full'
          ? 'opacity-0 pointer-events-none translate-y-4 md:opacity-100 md:pointer-events-auto md:translate-y-0 md:bottom-4'
          : mobileDrawerOpen && mobileSheetSnap === 'peek'
            ? 'bottom-[calc(50dvh+16px)] opacity-100 pointer-events-auto md:bottom-4 md:opacity-100'
            : mobileDrawerOpen && mobileSheetSnap === 'min'
              ? 'bottom-[calc(200px+env(safe-area-inset-bottom,0px)+16px)] opacity-100 pointer-events-auto md:bottom-4'
              : 'bottom-[calc(14px+env(safe-area-inset-bottom,0px))] sm:bottom-4 md:bottom-4 opacity-100 pointer-events-auto'
      ]"
    >
      <!-- Navigation Controls Component -->
      <MapControls
        :is-locating="isLocating"
        :has-location="hasUserLocation"
        :speed="userSpeed"
        :heading="userHeading"
        :accuracy="userAccuracy"
        :show-grid="showCartoGrid"
        @locate="locateUser"
        @zoom-in="zoomIn"
        @zoom-out="zoomOut"
        @share="emit('share')"
        @toggle-grid="toggleCartoGrid"
      />
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- FLOATING SCALE BAR & LIVE CURSOR COORDINATES (STANDALONE COMPONENT)     -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <MapScaleCoordinateBar
      :active-coords="activeDisplayCoords.coords"
      :scale-ratio="currentScaleRatio"
      :mobile-drawer-open="mobileDrawerOpen"
      :mobile-sheet-snap="mobileSheetSnap"
      :clicked-coordinate="clickedCoordinate"
      @scale-target-mounted="handleScaleTargetMounted"
      @select-scale="handleSelectScale"
    />
  </div>
</template>

<style scoped>
/* ─── Kursor Peta: Default Panah, Pointer Saat Menyorot Fitur Data ─────────── */
:deep(.ol-viewport),
:deep(.ol-viewport canvas) {
  cursor: default !important;
}

:deep(.ol-viewport.cursor-pointer-active),
:deep(.ol-viewport.cursor-pointer-active canvas) {
  cursor: pointer !important;
}

/* ─── ScaleLine Control in Bottom-Center Status Pill ────────────────────────── */
:deep(.ol-scale-line) {
  position: relative !important;
  bottom: auto !important;
  left: auto !important;
  right: auto !important;
  top: auto !important;
  background: transparent !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  box-shadow: none !important;
}

:deep(.ol-scale-line-inner) {
  color: #334155;
  border: 1.5px solid #475569;
  border-top: none;
  font-size: 10px;
  font-weight: 700;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  text-align: center;
  margin: 0 auto;
  padding: 0 4px 1px;
  background: transparent !important;
  line-height: 1.2;
  transition: color 0.2s ease, border-color 0.2s ease;
}

/* ─── Dark Mode ScaleLine ───────────────────────────────────────────────────── */
:global(.dark) :deep(.ol-scale-line),
.dark :deep(.ol-scale-line) {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

:global(.dark) :deep(.ol-scale-line-inner),
.dark :deep(.ol-scale-line-inner) {
  color: #e2e8f0;
  border: 1.5px solid #94a3b8;
  border-top: none;
}

/* ─── Click Shockwave Ring & Pin Pop Animation ───────────────────────────── */
@keyframes ring-expand {
  0% {
    transform: scale(0.2);
    opacity: 0.95;
  }
  100% {
    transform: scale(1.6);
    opacity: 0;
  }
}

.ring-wave {
  animation: ring-expand 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes pin-pop {
  0% {
    transform: scale(0.35);
    opacity: 0.6;
  }
  60% {
    transform: scale(1.15);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.scale-animation {
  animation: pin-pop 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}
</style>

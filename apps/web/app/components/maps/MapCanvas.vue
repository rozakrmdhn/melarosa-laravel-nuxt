<script setup lang="ts">
import "ol/ol.css";
import MapControls from "./MapControls.vue";
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
    currentBasemap: "osm",
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
let isDismissing = false;
let dismissTimer: ReturnType<typeof setTimeout> | null = null;

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

// Bojonegoro coordinates: [longitude, latitude] in EPSG:4326
const BOJONEGORO_CENTER: [number, number] = [111.88, -7.15];
const DEFAULT_ZOOM = 11;

function getBasemapSource(key: BasemapKey) {
  if (!olModules) return null;
  const { OSM, XYZ } = olModules;

  switch (key) {
    case "satellite":
      return new XYZ({
        url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        attributions: "&copy; Esri, Maxar, Earthstar Geographics",
        maxZoom: 19,
      });
    case "positron":
      return new XYZ({
        url: "https://cartodb-basemaps-a.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png",
        attributions: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/attributions">CARTO</a>',
        maxZoom: 19,
      });
    case "dark":
      return new XYZ({
        url: "https://cartodb-basemaps-a.global.ssl.fastly.net/dark_all/{z}/{x}/{y}.png",
        attributions: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/attributions">CARTO</a>',
        maxZoom: 19,
      });
    case "topo":
      return new XYZ({
        url: "https://tile.opentopomap.org/{z}/{x}/{y}.png",
        attributions: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
        maxZoom: 17,
      });
    case "osm":
    default:
      return new OSM();
  }
}

watch(
  () => props.currentBasemap,
  (newBasemap) => {
    if (tileLayer && olModules) {
      const source = getBasemapSource(newBasemap);
      if (source) {
        tileLayer.setSource(source);
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
      { default: RegularShape },
      { default: OlIcon },
    ] = await Promise.all([
      import("ol/Map.js"),
      import("ol/View.js"),
      import("ol/layer/Tile.js"),
      import("ol/source/TileWMS.js"),
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
      import("ol/style/RegularShape.js"),
      import("ol/style/Icon.js"),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      TileWMS,
      OSM,
      XYZ,
      fromLonLat: projPkg.fromLonLat,
      toLonLat: projPkg.toLonLat,
      transformExtent: projPkg.transformExtent,
      Overlay,
      ScaleLine,
    };

    tileLayer = new TileLayer({
      source: getBasemapSource(props.currentBasemap),
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

    mapInstance = new Map({
      target: mapContainer.value,
      layers: [tileLayer, geoVectorLayer],
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

    // Listener pergerakan dan zoom peta untuk sinkronisasi URL parameter
    mapInstance.on("moveend", () => {
      emitViewportUpdate();
    });

    // Pointer move listener for live mouse coordinates & feature hover detection
    mapInstance.on("pointermove", (evt: any) => {
      if (evt.dragging) return;
      const coords = olModules.toLonLat(evt.coordinate);
      if (coords && coords.length >= 2) {
        const lng = coords[0].toFixed(5);
        const lat = coords[1].toFixed(5);
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
    emit("loaded");
    emitViewportUpdate();
  } catch (err) {
    console.error("Gagal menginisialisasi peta OpenLayers:", err);
  }
}

function syncActiveWmsLayers() {
  if (!mapInstance || !olModules) return;
  const { TileLayer, TileWMS } = olModules;
  const currentLayers = props.activeLayers || [];
  const currentLayerIds = new Set(currentLayers.map((l) => l.id));

  // 1. Hapus layer WMS yang tidak lagi aktif
  for (const [id, olLayer] of wmsLayersMap.entries()) {
    if (!currentLayerIds.has(id)) {
      mapInstance.removeLayer(olLayer);
      wmsLayersMap.delete(id);
    }
  }

  // 2. Tambahkan atau perbarui status layer WMS
  currentLayers.forEach((layer, index) => {
    if (layer.wmsUrl && layer.wmsLayerName) {
      if (wmsLayersMap.has(layer.id)) {
        const olLayer = wmsLayersMap.get(layer.id);
        olLayer.setVisible(layer.visible);
        olLayer.setOpacity(layer.opacity ?? 1);
        olLayer.setZIndex(5 + index);
      } else {
        try {
          const source = new TileWMS({
            url: layer.wmsUrl,
            crossOrigin: "anonymous",
            params: {
              LAYERS: layer.wmsLayerName,
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
            zIndex: 5 + index,
          });

          mapInstance.addLayer(olLayer);
          wmsLayersMap.set(layer.id, olLayer);
        } catch (err) {
          console.error(`Gagal memuat layer WMS [${layer.title}]:`, err);
        }
      }
    }
  });
}

async function performWmsIdentify(olCoord: any) {
  if (!mapInstance || !olModules) return;
  const currentLayers = props.activeLayers || [];
  const visibleWmsLayers = currentLayers.filter(
    (l) => l.wmsUrl && l.wmsLayerName && l.visible !== false
  );

  activeNamobj.value = null;
  isIdentifyingFeatures.value = true;

  if (visibleWmsLayers.length === 0) {
    emit("identifying-features", true);
    await new Promise((resolve) => setTimeout(resolve, 200));
    isIdentifyingFeatures.value = false;
    emit("identifying-features", false);
    emit("identify-features", []);
    return;
  }

  const view = mapInstance.getView();
  const resolution = view.getResolution();
  const projection = view.getProjection();

  emit("identifying-features", true);

  // Jalankan query seluruh layer WMS aktif secara paralel (Promise.allSettled)
  const queries = visibleWmsLayers.map(async (layer) => {
    const olLayer = wmsLayersMap.get(layer.id);
    if (!olLayer) return [];
    const source = olLayer.getSource();
    if (!source || typeof source.getFeatureInfoUrl !== "function") return [];

    const url = source.getFeatureInfoUrl(olCoord, resolution, projection, {
      INFO_FORMAT: "application/json",
      QUERY_LAYERS: layer.wmsLayerName,
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

  // Berikan sedikit jeda animasi (320ms) agar efek visual radar & ripple pada peta serta status loading berjalan mulus tanpa flickering
  const minDelayPromise = new Promise((resolve) => setTimeout(resolve, 320));

  const [settled] = await Promise.all([
    Promise.allSettled(queries),
    minDelayPromise,
  ]);

  const identifiedList: IdentifiedFeature[] = [];

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
    if (mapInstance) {
      mapInstance.setTarget(undefined);
      mapInstance = null;
    }
  });
});

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
  if (mapContainer.value && document.activeElement !== mapContainer.value) {
    mapContainer.value.focus({ preventScroll: true });
  }
}

function handleMapMouseLeave() {
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
  <div class="relative w-full h-full select-none overflow-hidden bg-gray-100 dark:bg-[#070b14]">
    <!-- OpenLayers Map Target Container (Default panah, pointer saat menyorot feature data) -->
    <div
      ref="mapContainer"
      class="w-full h-full cursor-default focus:outline-hidden"
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

    <!-- Floating Navigation Controls & ScaleLine (Bottom Right with Dynamic Mobile Clearance) -->
    <div
      class="absolute right-3 z-20 flex flex-col items-end gap-1.5 pointer-events-none transition-all duration-300 ease-out"
      :class="[
        mobileDrawerOpen && (mobileSheetSnap === 'peek' || mobileSheetSnap === 'full')
          ? 'opacity-0 pointer-events-none translate-y-4'
          : mobileDrawerOpen && mobileSheetSnap === 'min'
            ? 'bottom-[calc(86px+env(safe-area-inset-bottom,0px))] md:bottom-3 opacity-100'
            : 'bottom-3 opacity-100'
      ]"
    >
      <!-- Navigation Controls Component -->
      <MapControls
        :is-locating="isLocating"
        :has-location="hasUserLocation"
        :speed="userSpeed"
        :heading="userHeading"
        :accuracy="userAccuracy"
        @locate="locateUser"
        @zoom-in="zoomIn"
        @zoom-out="zoomOut"
        @share="emit('share')"
      />

      <!-- ScaleLine Mount Anchor (Underneath Map Controls in Bottom Right) -->
      <div
        ref="scaleLineTarget"
        class="pointer-events-auto select-none"
      />
    </div>
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

/* ─── Transparent ScaleLine (Google Maps Style) ─────────────────────────────── */
:deep(.ol-scale-line) {
  position: relative;
  bottom: auto;
  left: auto;
  right: auto;
  background: transparent !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
  box-shadow: none !important;
}

:deep(.ol-scale-line-inner) {
  color: #1e293b;
  border: 1.5px solid #1e293b;
  border-top: none;
  font-size: 10px;
  font-weight: 600;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  text-align: center;
  margin: 0 auto;
  padding: 1px 2px;
  background: transparent !important;
  text-shadow: 0 1px 2px rgba(255, 255, 255, 0.95), 0 0 2px rgba(255, 255, 255, 0.95);
  filter: drop-shadow(0 1px 1px rgba(255, 255, 255, 0.9));
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
  color: #f1f5f9;
  border: 1.5px solid #f1f5f9;
  border-top: none;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95), 0 0 3px rgba(0, 0, 0, 0.9);
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.9));
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

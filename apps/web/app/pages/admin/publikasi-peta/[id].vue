<script setup lang="ts">
import * as maplibregl from 'maplibre-gl';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import 'maplibre-gl/dist/maplibre-gl.css';
import BasemapSwitcher from '~/components/common/BasemapSwitcher.vue';
import {
  resolvePublikasiDownloadUrl,
  resolvePublikasiGeorefImageUrl,
  usePublikasiPetaApi,
} from '~/composables/usePublikasiPetaApi';
import { usePermission } from '~/composables/usePermission';
import { useBasemapStore } from '~/stores/basemap';
import type {
  PublikasiGeorefCorners,
  PublikasiGeorefGcp,
  PublikasiGeorefMethod,
  PublikasiLngLat,
  PublikasiPetaDigitalItem,
} from '~/types/publikasi-peta';
import {
  computeBboxFromCorners,
  cornersFromBbox,
  extractGeoJsonBbox,
  formatDms,
  isPointInQuad,
  lngLatToUtm,
  parseCoordNumber,
  solveGeorefTransform,
  utmToLngLat,
} from '~/utils/georef-math';
import { buildMaplibreBasemapSpec } from '~/utils/maplibre-basemaps';
import { loadImageDimensionsFromBlob, rasterizePdfPageToPng } from '~/utils/pdf-rasterizer';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: [
    'publikasi-peta-view',
    'publikasi-peta-update',
    'publikasi-peta-manage',
  ],
  fullBleed: true,
});

const route = useRoute();
const api = usePublikasiPetaApi();
const basemapStore = useBasemapStore();
const { can, isAdmin } = usePermission();

const canManage = computed(
  () => isAdmin() || can('publikasi-peta-update') || can('publikasi-peta-manage'),
);

const itemId = computed(() => String(route.params.id || ''));

const loadingItem = ref(true);
const loadError = ref<string | null>(null);
const item = ref<PublikasiPetaDigitalItem | null>(null);

useSeoMeta({
  title: () => (item.value ? `Georeferencing: ${item.value.judul}` : 'Georeferencing Peta Digital'),
});

const BOJONEGORO_CENTER: [number, number] = [111.88, -7.16];
const CORNER_LABELS = ['TL', 'TR', 'BR', 'BL'] as const;

type EditorMode = 'gcp' | 'corners' | 'inspect';

const activeMode = ref<EditorMode>('gcp');
const activeBasemap = ref<string>('esri');
const transformPreference = ref<'homography' | 'affine'>('affine');
const opacity = ref<number>(0.85);
const showBottomPanel = ref<boolean>(true);

const rasterLoading = ref(false);
const rasterError = ref<string | null>(null);
const feedback = ref<{ type: 'success' | 'error' | 'info'; message: string } | null>(null);
const saving = ref(false);
const clearing = ref(false);

const imageObjectUrl = ref<string | null>(null);
const imagePreviewSrc = ref<string | null>(null);
const pendingImageBlob = ref<Blob | null>(null);
const imageWidth = ref<number>(0);
const imageHeight = ref<number>(0);
const pdfPageCount = ref<number>(1);
const pdfPageNumber = ref<number>(1);
const localFileInputRef = ref<HTMLInputElement | null>(null);
const sourceViewportRef = ref<HTMLElement | null>(null);
const sourceImgRef = ref<HTMLImageElement | null>(null);
const loupeCanvasRef = ref<HTMLCanvasElement | null>(null);

// Left source canvas zoom & pan state (top-left origin in natural image pixels)
const srcZoom = ref<number>(1);
const fitScale = ref<number>(1);
const srcPan = reactive({ x: 0, y: 0 });
const isPanningSrc = ref(false);
const panStart = reactive({ clientX: 0, clientY: 0, startX: 0, startY: 0 });
const movedDuringDrag = ref(false);
const hoverPixel = ref<{ x: number; y: number } | null>(null);
const showLoupe = ref<boolean>(true);

// Georeferencing State
const gcps = ref<PublikasiGeorefGcp[]>([]);
const pendingPixelPoint = ref<{ px: number; py: number } | null>(null);
const corners = ref<PublikasiGeorefCorners | null>(null);
const rmsMeters = ref<number | null>(null);
const gcpPointErrors = ref<Record<string, number>>({});
const activeMethod = ref<PublikasiGeorefMethod>('affine');

// Manual GCP Coordinate Entry (UTM Zone 49S / WGS84 Decimal)
const INDONESIA_UTM_ZONES = [46, 47, 48, 49, 50, 51, 52, 53, 54] as const;
const gcpCoordFormat = ref<'utm' | 'decimal'>('utm');
const editingGcpIndex = ref<number | null>(null);
const gcpFirstInputRef = ref<HTMLInputElement | null>(null);
const gcpInputError = ref<string | null>(null);
const gcpForm = reactive({
  utmZone: 49,
  utmHemisphere: 'S' as 'S' | 'N',
  utmEasting: '',
  utmNorthing: '',
  decLat: '',
  decLng: '',
});

const isGcpCoordPanelOpen = computed(() =>
  canManage.value && (pendingPixelPoint.value !== null || editingGcpIndex.value !== null),
);

const gcpResolvedPreview = computed<{ lng: number; lat: number; utmLabel: string } | null>(() => {
  if (gcpCoordFormat.value === 'utm') {
    const easting = parseCoordNumber(gcpForm.utmEasting);
    const northing = parseCoordNumber(gcpForm.utmNorthing);
    if (easting === null || northing === null) return null;
    const lngLat = utmToLngLat(easting, northing, gcpForm.utmZone, gcpForm.utmHemisphere);
    if (!lngLat) return null;
    return {
      lng: lngLat[0],
      lat: lngLat[1],
      utmLabel: `${gcpForm.utmZone}${gcpForm.utmHemisphere}: ${easting} E, ${northing} N`,
    };
  }

  const lat = parseCoordNumber(gcpForm.decLat);
  const lng = parseCoordNumber(gcpForm.decLng);
  if (lat === null || lng === null || lat < -85.05 || lat > 85.05 || lng < -180 || lng > 180) {
    return null;
  }
  const utm = lngLatToUtm(lng, lat);
  return {
    lng: Number(lng.toFixed(7)),
    lat: Number(lat.toFixed(7)),
    utmLabel: `${utm.zone}${utm.hemisphere}: ${utm.easting} E, ${utm.northing} N`,
  };
});

function formatGcpUtmSummary(gcp: PublikasiGeorefGcp): string {
  const utm = lngLatToUtm(gcp.lng, gcp.lat);
  return `${utm.zone}${utm.hemisphere} ${Math.round(utm.easting)}E, ${Math.round(utm.northing)}N`;
}

// MapLibre GL JS State
const mapContainerRef = ref<HTMLElement | null>(null);
let mapInstance: maplibregl.Map | null = null;
let resizeObserver: ResizeObserver | null = null;
let gcpMarkers: maplibregl.Marker[] = [];
let cornerMarkers: maplibregl.Marker[] = [];
let inspectMarker: maplibregl.Marker | null = null;
let boundaryFeature: any | null = null;
let lastLoadedOverlayUrl: string | null = null;

const hoverMapCoord = ref<{ lng: number; lat: number; inside: boolean } | null>(null);
const inspectedCoord = ref<{ lng: number; lat: number; copied: boolean } | null>(null);

const hasOverlayPlaced = computed(() => Boolean(corners.value && imagePreviewSrc.value));

function revokeObjectUrl() {
  if (imageObjectUrl.value) {
    URL.revokeObjectURL(imageObjectUrl.value);
    imageObjectUrl.value = null;
  }
}

function fitLeftView() {
  const vp = sourceViewportRef.value;
  if (!vp || imageWidth.value <= 0 || imageHeight.value <= 0) {
    srcZoom.value = 1;
    fitScale.value = 1;
    srcPan.x = 0;
    srcPan.y = 0;
    return;
  }
  const vpW = Math.max(100, vp.clientWidth);
  const vpH = Math.max(100, vp.clientHeight);
  const pad = 16;
  const nextFit = Math.min((vpW - pad) / imageWidth.value, (vpH - pad) / imageHeight.value, 4);
  fitScale.value = nextFit;
  srcZoom.value = nextFit;
  srcPan.x = (vpW - imageWidth.value * nextFit) / 2;
  srcPan.y = (vpH - imageHeight.value * nextFit) / 2;
}

function resetLeftView() {
  fitLeftView();
}

function handleSourceImgLoad() {
  const img = sourceImgRef.value;
  if (img && img.naturalWidth > 0 && img.naturalHeight > 0) {
    imageWidth.value = img.naturalWidth;
    imageHeight.value = img.naturalHeight;
  }
  nextTick(() => {
    fitLeftView();
  });
}

function zoomLeftAtPoint(factor: number, clientX?: number, clientY?: number) {
  const vp = sourceViewportRef.value;
  if (!vp || imageWidth.value <= 0 || imageHeight.value <= 0) return;

  const vpRect = vp.getBoundingClientRect();
  const anchorX = clientX !== undefined ? clientX - vpRect.left : vpRect.width / 2;
  const anchorY = clientY !== undefined ? clientY - vpRect.top : vpRect.height / 2;

  const minScale = Math.max(0.05, fitScale.value * 0.45);
  const maxScale = 16;
  const nextScale = Math.min(maxScale, Math.max(minScale, srcZoom.value * factor));
  if (nextScale === srcZoom.value) return;

  const imgX = (anchorX - srcPan.x) / srcZoom.value;
  const imgY = (anchorY - srcPan.y) / srcZoom.value;

  srcZoom.value = nextScale;
  srcPan.x = anchorX - imgX * nextScale;
  srcPan.y = anchorY - imgY * nextScale;
}

function zoomLeftBy(delta: number) {
  zoomLeftAtPoint(delta > 0 ? 1.3 : 1 / 1.3);
}

function handleLeftWheel(event: WheelEvent) {
  event.preventDefault();
  const factor = event.deltaY < 0 ? 1.22 : 1 / 1.22;
  zoomLeftAtPoint(factor, event.clientX, event.clientY);
  updateHoverPixelAndLoupe(event.clientX, event.clientY);
}

function clientToImagePixel(clientX: number, clientY: number): { px: number; py: number } | null {
  const vp = sourceViewportRef.value;
  if (!vp || imageWidth.value <= 0 || imageHeight.value <= 0 || srcZoom.value <= 0) return null;
  const vpRect = vp.getBoundingClientRect();
  const px = (clientX - vpRect.left - srcPan.x) / srcZoom.value;
  const py = (clientY - vpRect.top - srcPan.y) / srcZoom.value;
  if (px < 0 || py < 0 || px > imageWidth.value || py > imageHeight.value) return null;
  return {
    px: Number(px.toFixed(2)),
    py: Number(py.toFixed(2)),
  };
}

function drawLoupe(px: number, py: number) {
  const canvas = loupeCanvasRef.value;
  const img = sourceImgRef.value;
  if (!canvas || !img || !img.complete || img.naturalWidth <= 0) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const size = canvas.width;
  const sampleRadius = 15; // 30x30 source pixels zoomed into 120x120 (4x crisp pixel magnification)
  ctx.clearRect(0, 0, size, size);
  ctx.fillStyle = '#0f172a';
  ctx.fillRect(0, 0, size, size);

  ctx.imageSmoothingEnabled = false;
  const sSize = sampleRadius * 2;
  const rawSx = px - sampleRadius;
  const rawSy = py - sampleRadius;
  const clipSx = Math.max(0, rawSx);
  const clipSy = Math.max(0, rawSy);
  const clipEx = Math.min(img.naturalWidth, rawSx + sSize);
  const clipEy = Math.min(img.naturalHeight, rawSy + sSize);
  const clipSw = clipEx - clipSx;
  const clipSh = clipEy - clipSy;

  if (clipSw > 0 && clipSh > 0) {
    const scale = size / sSize;
    const dx = (clipSx - rawSx) * scale;
    const dy = (clipSy - rawSy) * scale;
    const dw = clipSw * scale;
    const dh = clipSh * scale;
    ctx.drawImage(img, clipSx, clipSy, clipSw, clipSh, dx, dy, dw, dh);
  }

  // Thin pixel-center crosshair
  const mid = size / 2;
  ctx.strokeStyle = 'rgba(220, 38, 38, 0.9)';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(mid, 0);
  ctx.lineTo(mid, mid - 6);
  ctx.moveTo(mid, mid + 6);
  ctx.lineTo(mid, size);
  ctx.moveTo(0, mid);
  ctx.lineTo(mid - 6, mid);
  ctx.moveTo(mid + 6, mid);
  ctx.lineTo(size, mid);
  ctx.stroke();

  ctx.fillStyle = '#dc2626';
  ctx.beginPath();
  ctx.arc(mid, mid, 2.2, 0, Math.PI * 2);
  ctx.fill();
}

function updateHoverPixelAndLoupe(clientX: number, clientY: number) {
  const pt = clientToImagePixel(clientX, clientY);
  if (!pt) {
    hoverPixel.value = null;
    return;
  }
  hoverPixel.value = { x: Math.round(pt.px), y: Math.round(pt.py) };
  if (showLoupe.value) {
    nextTick(() => drawLoupe(pt.px, pt.py));
  }
}

function handleLeftMouseDown(event: MouseEvent) {
  if (event.button !== 0 || !imagePreviewSrc.value) return;
  isPanningSrc.value = true;
  movedDuringDrag.value = false;
  panStart.clientX = event.clientX;
  panStart.clientY = event.clientY;
  panStart.startX = srcPan.x;
  panStart.startY = srcPan.y;
}

function handleLeftMouseMove(event: MouseEvent) {
  if (isPanningSrc.value) {
    const dx = event.clientX - panStart.clientX;
    const dy = event.clientY - panStart.clientY;
    if (movedDuringDrag.value || Math.abs(dx) > 6 || Math.abs(dy) > 6) {
      movedDuringDrag.value = true;
      srcPan.x = panStart.startX + dx;
      srcPan.y = panStart.startY + dy;
    }
  }
  updateHoverPixelAndLoupe(event.clientX, event.clientY);
}

function handleLeftMouseUp() {
  isPanningSrc.value = false;
}

function handleViewportClick(event: MouseEvent) {
  if (movedDuringDrag.value) {
    movedDuringDrag.value = false;
    return;
  }
  if (!imagePreviewSrc.value || imageWidth.value <= 0 || imageHeight.value <= 0) return;

  const pt = clientToImagePixel(event.clientX, event.clientY);
  if (!pt) return;

  activeMode.value = 'gcp';
  showBottomPanel.value = true;
  editingGcpIndex.value = null;
  gcpInputError.value = null;
  gcpForm.utmEasting = '';
  gcpForm.utmNorthing = '';
  gcpForm.decLat = '';
  gcpForm.decLng = '';

  pendingPixelPoint.value = {
    px: pt.px,
    py: pt.py,
  };
  feedback.value = null;
  nextTick(() => {
    gcpFirstInputRef.value?.focus();
  });
}

function openEditGcpCoord(index: number) {
  const target = gcps.value[index];
  if (!target) return;
  pendingPixelPoint.value = null;
  editingGcpIndex.value = index;
  gcpInputError.value = null;

  const utm = lngLatToUtm(target.lng, target.lat, gcpForm.utmZone);
  gcpForm.utmZone = utm.zone;
  gcpForm.utmHemisphere = utm.hemisphere;
  gcpForm.utmEasting = String(utm.easting);
  gcpForm.utmNorthing = String(utm.northing);
  gcpForm.decLat = String(target.lat);
  gcpForm.decLng = String(target.lng);

  nextTick(() => {
    gcpFirstInputRef.value?.focus();
  });
}

function cancelGcpCoordPanel() {
  pendingPixelPoint.value = null;
  editingGcpIndex.value = null;
  gcpInputError.value = null;
}

function commitGcpCoordForm() {
  gcpInputError.value = null;
  if (!canManage.value) return;

  let lng: number | null = null;
  let lat: number | null = null;

  if (gcpCoordFormat.value === 'utm') {
    const easting = parseCoordNumber(gcpForm.utmEasting);
    const northing = parseCoordNumber(gcpForm.utmNorthing);
    if (easting === null || northing === null) {
      gcpInputError.value = 'Easting (X) dan Northing (Y) wajib diisi dengan angka valid.';
      return;
    }
    const converted = utmToLngLat(easting, northing, gcpForm.utmZone, gcpForm.utmHemisphere);
    if (!converted) {
      gcpInputError.value = 'Koordinat UTM di luar rentang valid (Easting 100.000-900.000 m, Northing 0-10.000.000 m).';
      return;
    }
    lng = converted[0];
    lat = converted[1];
  } else {
    const parsedLat = parseCoordNumber(gcpForm.decLat);
    const parsedLng = parseCoordNumber(gcpForm.decLng);
    if (
      parsedLat === null ||
      parsedLng === null ||
      parsedLat < -85.05 ||
      parsedLat > 85.05 ||
      parsedLng < -180 ||
      parsedLng > 180
    ) {
      gcpInputError.value = 'Latitude (-85.05..85.05) dan Longitude (-180..180) wajib diisi angka desimal valid.';
      return;
    }
    lat = Number(parsedLat.toFixed(7));
    lng = Number(parsedLng.toFixed(7));
  }

  if (editingGcpIndex.value !== null && gcps.value[editingGcpIndex.value]) {
    gcps.value[editingGcpIndex.value]!.lng = lng;
    gcps.value[editingGcpIndex.value]!.lat = lat;
    editingGcpIndex.value = null;
  } else if (pendingPixelPoint.value) {
    const nextIdx = gcps.value.length + 1;
    gcps.value.push({
      id: `${Date.now()}-${nextIdx}`,
      label: `GCP-${nextIdx}`,
      px: pendingPixelPoint.value.px,
      py: pendingPixelPoint.value.py,
      lng,
      lat,
    });
    pendingPixelPoint.value = null;
  } else {
    return;
  }

  feedback.value = null;
  recomputeFromGcps();
  if (mapInstance && gcps.value.length <= 2) {
    mapInstance.panTo([lng, lat], { duration: 400 });
  } else if (mapInstance && corners.value) {
    zoomToGeorefArea();
  }
}

function clearMapMarkers() {
  gcpMarkers.forEach((m) => m.remove());
  gcpMarkers = [];
  cornerMarkers.forEach((m) => m.remove());
  cornerMarkers = [];
}

function refreshOverlayOnMap() {
  if (!mapInstance || !mapInstance.isStyleLoaded()) return;

  const overlaySourceId = 'pub-georef-image';
  const overlayLayerId = 'pub-georef-image-layer';
  const outlineSourceId = 'pub-georef-outline';
  const outlineLayerId = 'pub-georef-outline-layer';

  if (corners.value && imagePreviewSrc.value) {
    const existing = mapInstance.getSource(overlaySourceId) as maplibregl.ImageSource | undefined;
    if (existing && typeof existing.setCoordinates === 'function') {
      if (lastLoadedOverlayUrl === imagePreviewSrc.value) {
        existing.setCoordinates(corners.value);
      } else {
        existing.updateImage({
          url: imagePreviewSrc.value,
          coordinates: corners.value,
        });
        lastLoadedOverlayUrl = imagePreviewSrc.value;
      }
    } else {
      if (mapInstance.getLayer(overlayLayerId)) mapInstance.removeLayer(overlayLayerId);
      if (mapInstance.getSource(overlaySourceId)) mapInstance.removeSource(overlaySourceId);

      mapInstance.addSource(overlaySourceId, {
        type: 'image',
        url: imagePreviewSrc.value,
        coordinates: corners.value,
      });
      lastLoadedOverlayUrl = imagePreviewSrc.value;

      const beforeId = mapInstance.getLayer('wilayah-boundary-line') ? 'wilayah-boundary-line' : undefined;
      mapInstance.addLayer(
        {
          id: overlayLayerId,
          type: 'raster',
          source: overlaySourceId,
          paint: {
            'raster-opacity': opacity.value,
            'raster-fade-duration': 0,
          },
        },
        beforeId,
      );
    }

    if (mapInstance.getLayer(overlayLayerId)) {
      mapInstance.setPaintProperty(overlayLayerId, 'raster-opacity', opacity.value);
    }

    const ring = [...corners.value, corners.value[0]];
    const outlineData = {
      type: 'Feature',
      geometry: {
        type: 'Polygon',
        coordinates: [ring],
      },
      properties: {},
    };

    const outlineSource = mapInstance.getSource(outlineSourceId) as maplibregl.GeoJSONSource | undefined;
    if (outlineSource) {
      outlineSource.setData(outlineData as any);
    } else {
      mapInstance.addSource(outlineSourceId, {
        type: 'geojson',
        data: outlineData as any,
      });
      mapInstance.addLayer({
        id: outlineLayerId,
        type: 'line',
        source: outlineSourceId,
        paint: {
          'line-color': '#2563eb',
          'line-width': 2,
          'line-dasharray': [3, 2],
        },
      });
    }
  } else {
    lastLoadedOverlayUrl = null;
    if (mapInstance.getLayer(overlayLayerId)) mapInstance.removeLayer(overlayLayerId);
    if (mapInstance.getSource(overlaySourceId)) mapInstance.removeSource(overlaySourceId);
    if (mapInstance.getLayer(outlineLayerId)) mapInstance.removeLayer(outlineLayerId);
    if (mapInstance.getSource(outlineSourceId)) mapInstance.removeSource(outlineSourceId);
  }

  refreshMarkersOnMap();
}

function refreshMarkersOnMap() {
  clearMapMarkers();
  if (!mapInstance) return;

  gcps.value.forEach((gcp, index) => {
    const el = document.createElement('div');
    el.className = 'relative w-3 h-3 rounded-full bg-red-600 border-[1.5px] border-white shadow cursor-move select-none';
    const tag = document.createElement('span');
    tag.className =
      'absolute -top-3.5 left-2 px-1 rounded bg-slate-900/85 text-white text-[9px] font-bold leading-tight pointer-events-none';
    tag.textContent = String(index + 1);
    el.appendChild(tag);

    const marker = new maplibregl.Marker({ element: el, draggable: canManage.value })
      .setLngLat([gcp.lng, gcp.lat])
      .addTo(mapInstance!);

    marker.on('dragend', () => {
      const lngLat = marker.getLngLat();
      gcp.lng = Number(lngLat.lng.toFixed(7));
      gcp.lat = Number(lngLat.lat.toFixed(7));
      recomputeFromGcps();
    });

    gcpMarkers.push(marker);
  });

  if (corners.value && activeMode.value === 'corners' && canManage.value) {
    corners.value.forEach((coord, idx) => {
      const el = document.createElement('div');
      el.className =
        'px-1.5 py-0.5 rounded-md bg-blue-600 text-white text-[10px] font-bold border-2 border-white shadow-md cursor-grab active:cursor-grabbing select-none';
      el.textContent = CORNER_LABELS[idx] ?? `C${idx + 1}`;

      const marker = new maplibregl.Marker({ element: el, draggable: true })
        .setLngLat(coord)
        .addTo(mapInstance!);

      marker.on('drag', () => {
        if (!corners.value) return;
        const lngLat = marker.getLngLat();
        corners.value[idx] = [Number(lngLat.lng.toFixed(7)), Number(lngLat.lat.toFixed(7))];
        activeMethod.value = 'corners';
        rmsMeters.value = null;

        const overlaySource = mapInstance?.getSource('pub-georef-image') as maplibregl.ImageSource | undefined;
        overlaySource?.setCoordinates(corners.value);

        const outlineSource = mapInstance?.getSource('pub-georef-outline') as maplibregl.GeoJSONSource | undefined;
        if (outlineSource) {
          outlineSource.setData({
            type: 'Feature',
            geometry: { type: 'Polygon', coordinates: [[...corners.value, corners.value[0]]] },
            properties: {},
          } as any);
        }
      });

      cornerMarkers.push(marker);
    });
  }
}

function recomputeFromGcps() {
  if (gcps.value.length < 3 || imageWidth.value <= 0 || imageHeight.value <= 0) {
    rmsMeters.value = null;
    gcpPointErrors.value = {};
    refreshOverlayOnMap();
    return;
  }

  const res = solveGeorefTransform(gcps.value, imageWidth.value, imageHeight.value, transformPreference.value);
  if (res) {
    corners.value = res.coordinates;
    rmsMeters.value = res.rmsMeters;
    activeMethod.value = res.method;
    const errMap: Record<string, number> = {};
    for (const p of res.pointErrorsMeters) {
      errMap[p.id] = p.errorMeters;
    }
    gcpPointErrors.value = errMap;

    if (res.rmsMeters > 50) {
      feedback.value = {
        type: 'error',
        message: `Peringatan: Error RMS tinggi (${res.rmsMeters} m). Periksa titik GCP yang ditandai error tinggi, pastikan titik diklik di perpotongan grid dan Zona UTM Bojonegoro adalah 49S.`,
      };
    }
  }
  refreshOverlayOnMap();
}

function handleQuickPlaceCorners() {
  if (!mapInstance) return;
  let targetBbox: [number, number, number, number] | null = boundaryFeature
    ? extractGeoJsonBbox(boundaryFeature)
    : null;

  if (!targetBbox) {
    const b = mapInstance.getBounds();
    const lngPad = (b.getEast() - b.getWest()) * 0.2;
    const latPad = (b.getNorth() - b.getSouth()) * 0.2;
    targetBbox = [
      b.getWest() + lngPad,
      b.getSouth() + latPad,
      b.getEast() - lngPad,
      b.getNorth() - latPad,
    ];
  }

  corners.value = cornersFromBbox(targetBbox, imageWidth.value || 1000, imageHeight.value || 1000);
  activeMethod.value = 'corners';
  rmsMeters.value = null;
  activeMode.value = 'corners';
  feedback.value = {
    type: 'info',
    message: '4 sudut peta ditempatkan ke area tampilan. Geser pin TL, TR, BR, BL pada MapLibre untuk menyesuaikan posisi.',
  };
  refreshOverlayOnMap();
}

function removeGcp(index: number) {
  gcps.value.splice(index, 1);
  gcps.value.forEach((g, idx) => {
    g.label = `GCP-${idx + 1}`;
  });
  if (gcps.value.length >= 3) {
    recomputeFromGcps();
  } else {
    rmsMeters.value = null;
    refreshOverlayOnMap();
  }
}

function resetGcps() {
  gcps.value = [];
  pendingPixelPoint.value = null;
  corners.value = null;
  rmsMeters.value = null;
  refreshOverlayOnMap();
}

function zoomToGeorefArea() {
  if (!mapInstance) return;
  if (corners.value) {
    const [minLng, minLat, maxLng, maxLat] = computeBboxFromCorners(corners.value);
    mapInstance.fitBounds(
      [
        [minLng, minLat],
        [maxLng, maxLat],
      ],
      { padding: 56, duration: 500 },
    );
    return;
  }
  const bbox = extractGeoJsonBbox(boundaryFeature);
  if (bbox) {
    mapInstance.fitBounds(
      [
        [bbox[0], bbox[1]],
        [bbox[2], bbox[3]],
      ],
      { padding: 56, duration: 500 },
    );
  }
}

function renderBoundaryLayerOnMap() {
  if (!mapInstance || !mapInstance.isStyleLoaded() || !boundaryFeature) return;

  const srcId = 'wilayah-boundary-source';
  const fillId = 'wilayah-boundary-fill';
  const lineId = 'wilayah-boundary-line';

  if (mapInstance.getSource(srcId)) {
    (mapInstance.getSource(srcId) as maplibregl.GeoJSONSource).setData(boundaryFeature);
  } else {
    mapInstance.addSource(srcId, { type: 'geojson', data: boundaryFeature });
    mapInstance.addLayer({
      id: fillId,
      type: 'fill',
      source: srcId,
      paint: { 'fill-color': '#2563eb', 'fill-opacity': 0.08 },
    });
    mapInstance.addLayer({
      id: lineId,
      type: 'line',
      source: srcId,
      paint: { 'line-color': '#1d4ed8', 'line-width': 2.2 },
    });
  }
}

function applyBasemapStyle(code: string) {
  if (!mapInstance) return;
  const baseItem = basemapStore.getByCode(code, 'maplibre');
  const { sources, layers } = buildMaplibreBasemapSpec(baseItem ?? code, { baseOpacity: 1 });
  lastLoadedOverlayUrl = null;
  mapInstance.setStyle({
    version: 8,
    sources,
    layers,
  } as any);
  mapInstance.once('styledata', () => {
    renderBoundaryLayerOnMap();
    refreshOverlayOnMap();
  });
}

function initMapLibre() {
  if (!mapContainerRef.value) return;
  destroyMapLibre();

  if (import.meta.client && typeof maplibregl.setWorkerUrl === 'function') {
    maplibregl.setWorkerUrl(maplibreWorkerUrl);
  }

  basemapStore.ensureLoaded();
  const baseItem = basemapStore.getByCode(activeBasemap.value, 'maplibre');
  const { sources, layers } = buildMaplibreBasemapSpec(baseItem ?? activeBasemap.value, { baseOpacity: 1 });

  mapInstance = new maplibregl.Map({
    container: mapContainerRef.value,
    style: { version: 8, sources, layers } as any,
    center: BOJONEGORO_CENTER,
    zoom: 12,
    attributionControl: false,
  });

  mapInstance.addControl(new maplibregl.NavigationControl({ showCompass: true }), 'top-left');

  mapInstance.on('load', () => {
    renderBoundaryLayerOnMap();
    refreshOverlayOnMap();
    zoomToGeorefArea();
  });

  mapInstance.on('mousemove', (e) => {
    const pt: PublikasiLngLat = [Number(e.lngLat.lng.toFixed(6)), Number(e.lngLat.lat.toFixed(6))];
    const inside = corners.value ? isPointInQuad(pt, corners.value) : false;
    hoverMapCoord.value = { lng: pt[0], lat: pt[1], inside };
  });

  mapInstance.on('mouseout', () => {
    hoverMapCoord.value = null;
  });

  mapInstance.on('click', (e) => {
    const lng = Number(e.lngLat.lng.toFixed(7));
    const lat = Number(e.lngLat.lat.toFixed(7));

    if (activeMode.value === 'gcp' && canManage.value) {
      if (!pendingPixelPoint.value) {
        feedback.value = {
          type: 'info',
          message: 'Klik terlebih dahulu satu titik pada Peta Digital (panel kiri) sebelum memilih titik pasangannya di peta.',
        };
        return;
      }

      const nextIdx = gcps.value.length + 1;
      gcps.value.push({
        id: `${Date.now()}-${nextIdx}`,
        label: `GCP-${nextIdx}`,
        px: pendingPixelPoint.value.px,
        py: pendingPixelPoint.value.py,
        lng,
        lat,
      });
      pendingPixelPoint.value = null;
      editingGcpIndex.value = null;
      gcpInputError.value = null;
      feedback.value = null;
      recomputeFromGcps();
      return;
    }

    if (activeMode.value === 'inspect') {
      inspectedCoord.value = { lng, lat, copied: false };
      if (inspectMarker) inspectMarker.remove();
      inspectMarker = new maplibregl.Marker({ color: '#dc2626' })
        .setLngLat([lng, lat])
        .setPopup(
          new maplibregl.Popup({ offset: 18 }).setHTML(
            `<div style="font-size:11px;line-height:1.45"><strong>Koordinat Terbaca</strong><br/>Lat: ${lat}<br/>Lng: ${lng}<br/><span style="color:#475569">${formatDms(lat, true)}, ${formatDms(lng, false)}</span></div>`,
          ),
        )
        .addTo(mapInstance!)
        .togglePopup();
    }
  });

  resizeObserver = new ResizeObserver(() => {
    mapInstance?.resize();
  });
  resizeObserver.observe(mapContainerRef.value);
}

function destroyMapLibre() {
  lastLoadedOverlayUrl = null;
  clearMapMarkers();
  if (inspectMarker) {
    inspectMarker.remove();
    inspectMarker = null;
  }
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
}

async function loadRasterFromItemPdf(pageNum = 1) {
  if (!item.value || item.value.sumber_tipe !== 'file_pdf' || !item.value.has_file) return;
  rasterLoading.value = true;
  rasterError.value = null;
  try {
    const pdfUrl = resolvePublikasiDownloadUrl(item.value.id, { inline: true });
    const result = await rasterizePdfPageToPng(pdfUrl, pageNum, 3600);
    revokeObjectUrl();
    imageObjectUrl.value = result.objectUrl;
    imagePreviewSrc.value = result.objectUrl;
    pendingImageBlob.value = result.blob;
    imageWidth.value = result.width;
    imageHeight.value = result.height;
    pdfPageCount.value = result.pageCount;
    pdfPageNumber.value = result.pageNumber;
    await nextTick();
    fitLeftView();
    refreshOverlayOnMap();
  } catch (err: any) {
    rasterError.value =
      err?.message || 'Gagal merender halaman PDF ke raster. Anda dapat mengunggah gambar PNG/JPG peta secara manual.';
  } finally {
    rasterLoading.value = false;
  }
}

async function handlePickLocalFile(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  rasterLoading.value = true;
  rasterError.value = null;
  feedback.value = null;

  try {
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
      const result = await rasterizePdfPageToPng(file, 1, 3600);
      revokeObjectUrl();
      imageObjectUrl.value = result.objectUrl;
      imagePreviewSrc.value = result.objectUrl;
      pendingImageBlob.value = result.blob;
      imageWidth.value = result.width;
      imageHeight.value = result.height;
      pdfPageCount.value = result.pageCount;
      pdfPageNumber.value = 1;
    } else if (['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
      const dims = await loadImageDimensionsFromBlob(file);
      revokeObjectUrl();
      const objUrl = URL.createObjectURL(file);
      imageObjectUrl.value = objUrl;
      imagePreviewSrc.value = objUrl;
      pendingImageBlob.value = file;
      imageWidth.value = dims.width;
      imageHeight.value = dims.height;
      pdfPageCount.value = 1;
      pdfPageNumber.value = 1;
    } else {
      rasterError.value = 'Format file harus PNG, JPG, WebP, atau PDF.';
    }
    await nextTick();
    fitLeftView();
    refreshOverlayOnMap();
  } catch (err: any) {
    rasterError.value = err?.message || 'Gagal membaca file peta lokal.';
  } finally {
    rasterLoading.value = false;
    input.value = '';
  }
}

function hydrateFromItem(loaded: PublikasiPetaDigitalItem) {
  item.value = loaded;
  if (loaded.is_georeferenced && loaded.georef_coordinates?.length === 4) {
    corners.value = loaded.georef_coordinates.map((c) => [c[0], c[1]]) as PublikasiGeorefCorners;
    gcps.value = (loaded.georef_gcps || []).map((g) => ({ ...g }));
    rmsMeters.value = loaded.georef_rms_meters ?? null;
    opacity.value = loaded.georef_opacity ?? 0.85;
    activeMethod.value = loaded.georef_method || 'affine';
    imageWidth.value = loaded.georef_image_width || 1000;
    imageHeight.value = loaded.georef_image_height || 1000;
    imagePreviewSrc.value = loaded.has_georef_image
      ? resolvePublikasiGeorefImageUrl(loaded.id, loaded.georeferenced_at)
      : null;
  } else {
    corners.value = null;
    gcps.value = [];
    rmsMeters.value = null;
    opacity.value = 0.85;
    activeMethod.value = 'affine';
    imagePreviewSrc.value = null;
  }
}

async function initWorkspace() {
  if (!itemId.value) return;
  loadingItem.value = true;
  loadError.value = null;
  feedback.value = null;
  rasterError.value = null;
  pendingImageBlob.value = null;
  pendingPixelPoint.value = null;
  inspectedCoord.value = null;
  revokeObjectUrl();

  try {
    const res = await api.fetchAdminDetail(itemId.value);
    if (!res?.data) {
      loadError.value = 'Publikasi peta digital tidak ditemukan.';
      return;
    }
    hydrateFromItem(res.data);

    try {
      const boundaryRes = await api.fetchWilayahBoundary(res.data.id);
      boundaryFeature = boundaryRes?.feature || null;
    } catch {
      boundaryFeature = null;
    }
  } catch (err: any) {
    loadError.value = err?.data?.message || 'Gagal memuat detail publikasi peta digital.';
    return;
  } finally {
    loadingItem.value = false;
  }

  await nextTick();
  initMapLibre();

  if (item.value && !imagePreviewSrc.value && item.value.sumber_tipe === 'file_pdf' && item.value.has_file) {
    await loadRasterFromItemPdf(1);
  }
}

async function handleSaveGeoreference() {
  if (!item.value || saving.value || !canManage.value) return;
  if (!corners.value) {
    feedback.value = {
      type: 'error',
      message: 'Silakan tandai minimal 3 titik ikat (GCP) atau gunakan Pasangkan ke Wilayah terlebih dahulu.',
    };
    return;
  }
  if (!pendingImageBlob.value && !item.value.has_georef_image) {
    feedback.value = {
      type: 'error',
      message: 'Gambar raster peta belum tersedia untuk disimpan ke layer MapLibre.',
    };
    return;
  }

  saving.value = true;
  feedback.value = null;

  try {
    const fd = new FormData();
    fd.append('georef_method', activeMethod.value);
    fd.append('georef_coordinates', JSON.stringify(corners.value));
    fd.append('georef_gcps', JSON.stringify(gcps.value));
    if (rmsMeters.value !== null) {
      fd.append('georef_rms_meters', String(rmsMeters.value));
    }
    fd.append('georef_opacity', String(opacity.value));
    if (pendingImageBlob.value) {
      const ext = pendingImageBlob.value.type === 'image/jpeg' ? 'jpg' : pendingImageBlob.value.type === 'image/webp' ? 'webp' : 'png';
      fd.append('georef_image', pendingImageBlob.value, `georef-map.${ext}`);
    }

    const res = await api.saveGeoreference(item.value.id, fd);
    if (res?.data) {
      pendingImageBlob.value = null;
      hydrateFromItem(res.data);
      lastLoadedOverlayUrl = null;
      refreshOverlayOnMap();
      feedback.value = {
        type: 'success',
        message: 'Koordinat georeferencing MapLibre GL JS berhasil disimpan.',
      };
    }
  } catch (err: any) {
    const errorsObj = err?.data?.errors;
    const firstErr = errorsObj ? Object.values(errorsObj).flat()[0] : null;
    feedback.value = {
      type: 'error',
      message: String(firstErr || err?.data?.message || 'Gagal menyimpan koordinat georeferencing.'),
    };
  } finally {
    saving.value = false;
  }
}

async function handleClearGeoreference() {
  if (!item.value || clearing.value || !canManage.value) return;
  clearing.value = true;
  feedback.value = null;
  try {
    const res = await api.clearGeoreference(item.value.id);
    if (res?.data) {
      pendingImageBlob.value = null;
      hydrateFromItem(res.data);
      resetGcps();
      feedback.value = {
        type: 'info',
        message: 'Data georeferencing berhasil dihapus.',
      };
    }
  } catch (err: any) {
    feedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menghapus data georeferencing.',
    };
  } finally {
    clearing.value = false;
  }
}

function copyInspectedCoord() {
  if (!inspectedCoord.value || !import.meta.client) return;
  const text = `${inspectedCoord.value.lat}, ${inspectedCoord.value.lng}`;
  navigator?.clipboard?.writeText(text).catch(() => {});
  inspectedCoord.value.copied = true;
}

watch(itemId, () => {
  initWorkspace();
});

watch(activeBasemap, (code) => {
  applyBasemapStyle(code);
});

watch(opacity, (val) => {
  if (mapInstance?.getLayer('pub-georef-image-layer')) {
    mapInstance.setPaintProperty('pub-georef-image-layer', 'raster-opacity', val);
  }
});

watch(activeMode, () => {
  refreshMarkersOnMap();
});

watch(transformPreference, () => {
  if (gcps.value.length >= 3) {
    recomputeFromGcps();
  }
});

onMounted(() => {
  initWorkspace();
  window.addEventListener('mouseup', handleLeftMouseUp);
});

onBeforeUnmount(() => {
  window.removeEventListener('mouseup', handleLeftMouseUp);
  destroyMapLibre();
  revokeObjectUrl();
});
</script>

<template>
  <div class="h-full w-full flex flex-col overflow-hidden bg-slate-50 dark:bg-[#070b14]">
    <!-- Top Bar -->
    <header class="shrink-0 px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-[#0b0f19] flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <NuxtLink to="/admin/publikasi-peta">
          <UButton
            icon="i-lucide-arrow-left"
            label="Daftar Publikasi"
            color="neutral"
            variant="outline"
            size="xs"
            class="cursor-pointer shrink-0"
          />
        </NuxtLink>

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate max-w-md lg:max-w-xl">
              {{ item?.judul || 'Georeferencing Peta Digital' }}
            </h1>
            <UBadge
              v-if="item"
              :color="item.is_georeferenced ? 'success' : 'neutral'"
              variant="subtle"
              size="md"
              class="font-semibold"
            >
              {{ item.is_georeferenced ? `Ter-georef (${item.georef_method || 'affine'})` : 'Belum Georef' }}
            </UBadge>
          </div>
          <p v-if="item" class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
            {{ item.wilayah_label }} · {{ item.kategori }} · Tahun {{ item.tahun_terbit }}
            <span v-if="rmsMeters !== null" class="text-emerald-700 dark:text-emerald-300 font-semibold ml-1.5 tabular-nums">
              · RMS Error: {{ rmsMeters }} m
            </span>
          </p>
        </div>
      </div>

      <div v-if="item" class="flex flex-wrap items-center gap-2 shrink-0">
        <NuxtLink v-if="item.is_published" :to="`/peta-digital/${item.slug || item.id}`" target="_blank">
          <UButton
            icon="i-lucide-external-link"
            label="Lihat Publik"
            color="neutral"
            variant="ghost"
            size="xs"
            class="cursor-pointer"
          />
        </NuxtLink>

        <UButton
          v-if="item.is_georeferenced && canManage"
          icon="i-lucide-trash-2"
          label="Hapus Georef"
          color="error"
          variant="ghost"
          size="xs"
          :loading="clearing"
          class="cursor-pointer"
          @click="handleClearGeoreference"
        />

        <UButton
          v-if="canManage"
          icon="i-lucide-check"
          :label="saving ? 'Menyimpan...' : 'Simpan Koordinat Georeferencing'"
          color="primary"
          size="sm"
          :loading="saving"
          :disabled="!corners"
          class="cursor-pointer"
          @click="handleSaveGeoreference"
        />
      </div>
    </header>

    <!-- Loading / Error Shell -->
    <div v-if="loadingItem" class="flex-1 p-6 grid grid-cols-1 lg:grid-cols-12 gap-4">
      <USkeleton class="lg:col-span-5 h-full rounded-xl" />
      <USkeleton class="lg:col-span-7 h-full rounded-xl" />
    </div>

    <div v-else-if="loadError || !item" class="flex-1 flex items-center justify-center p-6">
      <div class="max-w-md w-full rounded-2xl border border-red-200 dark:border-red-800 bg-white dark:bg-[#0b0f19] p-6 text-center space-y-3">
        <UIcon name="i-lucide-alert-circle" class="size-8 mx-auto text-red-600" aria-hidden="true" />
        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ loadError || 'Data publikasi tidak ditemukan.' }}</p>
        <NuxtLink to="/admin/publikasi-peta">
          <UButton label="Kembali ke Daftar" color="neutral" variant="outline" size="sm" />
        </NuxtLink>
      </div>
    </div>

    <!-- Main Full-Bleed Workspace -->
    <div v-else class="relative flex-1 min-h-0 flex flex-col">
      <!-- Floating Non-Shifting Inline Feedback Pill -->
      <div
        v-if="feedback"
        role="status"
        class="absolute top-13 left-1/2 -translate-x-1/2 z-30 max-w-2xl px-3.5 py-2 rounded-xl border text-xs flex items-center justify-between gap-2 shadow-lg"
        :class="feedback.type === 'error'
          ? 'bg-red-50/95 dark:bg-red-950/95 border-red-300 dark:border-red-700 text-red-900 dark:text-red-100'
          : feedback.type === 'success'
            ? 'bg-emerald-50/95 dark:bg-emerald-950/95 border-emerald-300 dark:border-emerald-700 text-emerald-900 dark:text-emerald-100'
            : 'bg-blue-50/95 dark:bg-blue-950/95 border-blue-300 dark:border-blue-700 text-blue-900 dark:text-blue-100'"
      >
        <span class="font-medium">{{ feedback.message }}</span>
        <button type="button" class="cursor-pointer p-0.5 shrink-0" aria-label="Tutup pesan" @click="feedback = null">
          <UIcon name="i-lucide-x" class="size-3.5" aria-hidden="true" />
        </button>
      </div>

      <!-- Mode & Tool Bar -->
      <div class="shrink-0 px-4 py-2 border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-white/[0.02] flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-1.5">
          <button
            type="button"
            class="min-h-[34px] px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
            :class="activeMode === 'gcp'
              ? 'bg-[#2B5B9E] text-white shadow-xs'
              : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
            @click="activeMode = 'gcp'"
          >
            <UIcon name="i-lucide-crosshair" class="size-3.5" aria-hidden="true" />
            <span>Titik Ikat (GCP)</span>
          </button>
          <button
            type="button"
            class="min-h-[34px] px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
            :class="activeMode === 'corners'
              ? 'bg-[#2B5B9E] text-white shadow-xs'
              : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
            @click="activeMode = 'corners'"
          >
            <UIcon name="i-lucide-move" class="size-3.5" aria-hidden="true" />
            <span>Geser 4 Sudut</span>
          </button>
          <button
            type="button"
            class="min-h-[34px] px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
            :class="activeMode === 'inspect'
              ? 'bg-[#2B5B9E] text-white shadow-xs'
              : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
            @click="activeMode = 'inspect'"
          >
            <UIcon name="i-lucide-locate-fixed" class="size-3.5" aria-hidden="true" />
            <span>Baca Koordinat</span>
          </button>

          <span class="hidden sm:inline-block h-5 w-px bg-slate-200 dark:bg-slate-700 mx-1" />

          <UButton
            icon="i-lucide-scan"
            label="Pasangkan ke Wilayah"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer"
            :disabled="!imagePreviewSrc"
            @click="handleQuickPlaceCorners"
          />
          <UButton
            icon="i-lucide-focus"
            label="Zoom Overlay"
            size="xs"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="zoomToGeorefArea"
          />
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs">
          <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
            <span>Transformasi:</span>
            <select
              v-model="transformPreference"
              class="rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1 text-xs font-semibold"
            >
              <option value="affine">Affine / Orde 1 (Disarankan)</option>
              <option value="homography">Projective (4+ GCP)</option>
            </select>
          </label>

          <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
            <span>Opasitas: {{ Math.round(opacity * 100) }}%</span>
            <input v-model.number="opacity" type="range" min="0.15" max="1" step="0.05" class="w-24 accent-[#2B5B9E]" />
          </label>

          <UButton
            :icon="showBottomPanel ? 'i-lucide-panel-bottom-close' : 'i-lucide-panel-bottom-open'"
            :label="`Titik & Sudut (${gcps.length})`"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer"
            @click="showBottomPanel = !showBottomPanel"
          />
        </div>
      </div>

      <!-- Full-Height Split Canvas (Zero Layout Shift on Click) -->
      <div class="flex-1 min-h-0 p-3 grid grid-cols-1 lg:grid-cols-12 gap-3 overflow-hidden">
        <!-- LEFT: Source Digital Map Raster -->
        <div class="lg:col-span-5 min-h-[320px] flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 overflow-hidden">
          <div class="shrink-0 px-3 py-2 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-[#0b0f19] flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-1.5 font-semibold text-slate-800 dark:text-slate-200">
              <UIcon name="i-lucide-image" class="size-3.5 text-[#2B5B9E]" aria-hidden="true" />
              <span>Kanvas Peta Digital</span>
              <span v-if="imageWidth > 0" class="text-[11px] font-normal text-slate-500">({{ imageWidth }}x{{ imageHeight }}px)</span>
            </div>

            <div class="flex items-center gap-1">
              <div v-if="item.sumber_tipe === 'file_pdf' && pdfPageCount > 1" class="inline-flex items-center gap-1 mr-1">
                <UButton
                  icon="i-lucide-chevron-left"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  :disabled="pdfPageNumber <= 1 || rasterLoading"
                  @click="loadRasterFromItemPdf(pdfPageNumber - 1)"
                />
                <span class="text-[11px] font-medium">Hal {{ pdfPageNumber }}/{{ pdfPageCount }}</span>
                <UButton
                  icon="i-lucide-chevron-right"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  :disabled="pdfPageNumber >= pdfPageCount || rasterLoading"
                  @click="loadRasterFromItemPdf(pdfPageNumber + 1)"
                />
              </div>

              <input
                ref="localFileInputRef"
                type="file"
                accept="image/png,image/jpeg,image/webp,application/pdf,.pdf"
                class="hidden"
                @change="handlePickLocalFile"
              />
              <UButton
                icon="i-lucide-upload"
                label="Gambar/PDF"
                size="xs"
                color="neutral"
                variant="outline"
                class="cursor-pointer"
                @click="localFileInputRef?.click()"
              />
              <UButton
                icon="i-lucide-search"
                size="xs"
                :color="showLoupe ? 'primary' : 'neutral'"
                :variant="showLoupe ? 'subtle' : 'ghost'"
                title="Kaca Pembesar Presisi Piksel"
                aria-label="Kaca Pembesar Presisi Piksel"
                class="cursor-pointer"
                @click="showLoupe = !showLoupe"
              />
              <UButton icon="i-lucide-zoom-in" size="xs" color="neutral" variant="ghost" aria-label="Perbesar sumber" @click="zoomLeftBy(1)" />
              <UButton icon="i-lucide-zoom-out" size="xs" color="neutral" variant="ghost" aria-label="Perkecil sumber" @click="zoomLeftBy(-1)" />
              <UButton icon="i-lucide-rotate-ccw" size="xs" color="neutral" variant="ghost" aria-label="Reset zoom" @click="resetLeftView" />
            </div>
          </div>

          <div
            ref="sourceViewportRef"
            class="relative flex-1 min-h-0 overflow-hidden select-none bg-slate-200/70 dark:bg-slate-900"
            :class="isPanningSrc && movedDuringDrag ? 'cursor-grabbing' : imagePreviewSrc ? 'cursor-crosshair' : ''"
            @wheel="handleLeftWheel"
            @mousedown="handleLeftMouseDown"
            @mousemove="handleLeftMouseMove"
            @mouseup="handleLeftMouseUp"
            @mouseleave="hoverPixel = null"
            @click="handleViewportClick"
          >
            <div v-if="rasterLoading" class="h-full flex flex-col items-center justify-center gap-2 text-xs text-slate-600 dark:text-slate-300">
              <UIcon name="i-lucide-loader-circle" class="size-6 animate-spin text-[#2B5B9E]" aria-hidden="true" />
              <span>Merender halaman peta PDF resolusi tinggi...</span>
            </div>

            <div v-else-if="rasterError" class="h-full flex flex-col items-center justify-center p-6 text-center space-y-2">
              <p class="text-xs font-medium text-red-700 dark:text-red-300 max-w-xs">{{ rasterError }}</p>
              <UButton
                icon="i-lucide-upload"
                label="Pilih Gambar PNG/JPG atau PDF Lokal"
                size="xs"
                color="primary"
                class="cursor-pointer"
                @click="localFileInputRef?.click()"
              />
            </div>

            <div
              v-else-if="!imagePreviewSrc"
              class="h-full flex flex-col items-center justify-center p-6 text-center space-y-2.5"
            >
              <UIcon name="i-lucide-file-image" class="size-8 mx-auto text-slate-400" aria-hidden="true" />
              <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 max-w-xs">
                Pilih gambar raster peta (PNG/JPG/WebP) atau berkas PDF lokal untuk mulai georeferencing.
              </p>
              <UButton
                icon="i-lucide-upload"
                label="Pilih File Peta"
                size="xs"
                color="primary"
                class="cursor-pointer"
                @click="localFileInputRef?.click()"
              />
            </div>

            <template v-else>
              <!-- Image Layer positioned strictly in top-left pixel space -->
              <div
                class="absolute top-0 left-0 origin-top-left pointer-events-none"
                :style="{
                  width: `${imageWidth}px`,
                  height: `${imageHeight}px`,
                  transform: `translate3d(${srcPan.x}px, ${srcPan.y}px, 0) scale(${srcZoom})`,
                }"
              >
                <img
                  ref="sourceImgRef"
                  :src="imagePreviewSrc"
                  alt="Kanvas Peta Digital Sumber"
                  draggable="false"
                  class="block w-full h-full shadow-md"
                  @load="handleSourceImgLoad"
                />
              </div>

              <!-- Fixed-size Red Dot GCP Markers in exact viewport screen space (never scaled!) -->
              <div
                v-for="(gcp, idx) in gcps"
                :key="gcp.id"
                class="absolute w-0 h-0 pointer-events-none z-10"
                :style="{
                  left: `${srcPan.x + gcp.px * srcZoom}px`,
                  top: `${srcPan.y + gcp.py * srcZoom}px`,
                }"
              >
                <span class="block -translate-x-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-red-600 ring-1 ring-white shadow-xs" />
                <span class="absolute -top-4 left-1.5 px-1 rounded bg-slate-900/85 text-white text-[9px] font-bold leading-tight whitespace-nowrap">
                  {{ idx + 1 }}
                </span>
              </div>

              <!-- Pending Red Dot Marker -->
              <div
                v-if="pendingPixelPoint"
                class="absolute w-0 h-0 pointer-events-none z-10"
                :style="{
                  left: `${srcPan.x + pendingPixelPoint.px * srcZoom}px`,
                  top: `${srcPan.y + pendingPixelPoint.py * srcZoom}px`,
                }"
              >
                <span class="block -translate-x-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-red-600 ring-2 ring-white shadow-sm" />
              </div>

              <!-- Live 4x Pixel Magnifier Loupe with Crosshair -->
              <div
                v-if="showLoupe && hoverPixel"
                class="absolute top-2.5 right-2.5 z-20 rounded-xl overflow-hidden border-2 border-white dark:border-slate-700 shadow-xl bg-slate-900 pointer-events-none"
              >
                <canvas ref="loupeCanvasRef" width="120" height="120" class="block w-[120px] h-[120px]" />
                <div class="px-1.5 py-0.5 bg-slate-950/90 text-white text-[10px] font-mono text-center tabular-nums">
                  {{ hoverPixel.x }}, {{ hoverPixel.y }}
                </div>
              </div>
            </template>

            <div
              v-if="hoverPixel"
              class="absolute bottom-2 left-2 px-2 py-1 rounded-md bg-slate-900/80 text-white text-[11px] font-mono tabular-nums pointer-events-none z-20"
            >
              Pixel: {{ hoverPixel.x }}, {{ hoverPixel.y }} · {{ Math.round(srcZoom * 100) }}% · Scroll untuk zoom ke kursor
            </div>
          </div>
        </div>

        <!-- RIGHT: MapLibre GL JS Map -->
        <div class="lg:col-span-7 min-h-[320px] flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-slate-100 dark:bg-slate-900">
          <div class="shrink-0 px-3 py-2 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-[#0b0f19] flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-map" class="size-3.5 text-[#2B5B9E]" aria-hidden="true" />
              <span class="font-semibold text-slate-800 dark:text-slate-200">Peta Referensi MapLibre GL JS</span>
              <UBadge v-if="pendingPixelPoint" color="info" variant="subtle" size="md" class="font-semibold">
                Klik titik pasangan pada peta
              </UBadge>
            </div>
            <div class="text-[11px] text-slate-600 dark:text-slate-400 tabular-nums">
              <span v-if="hasOverlayPlaced" class="font-semibold text-emerald-700 dark:text-emerald-300">
                Overlay aktif ({{ activeMethod }})
              </span>
              <span v-else>Belum ada overlay</span>
            </div>
          </div>

          <div class="relative flex-1 min-h-0 w-full">
            <div ref="mapContainerRef" class="w-full h-full" />

            <BasemapSwitcher
              v-model="activeBasemap"
              engine="maplibre"
              variant="compact"
              placement="top-right"
            />

            <!-- Coordinate Readout HUD -->
            <div
              class="absolute bottom-2.5 left-2.5 right-2.5 flex flex-wrap items-center justify-between gap-2 px-3 py-1.5 rounded-lg bg-slate-900/85 text-white text-[11px] backdrop-blur-xs pointer-events-auto"
            >
              <div v-if="hoverMapCoord" class="flex flex-wrap items-center gap-2 font-mono tabular-nums">
                <span>Lat: {{ hoverMapCoord.lat.toFixed(6) }}</span>
                <span>Lng: {{ hoverMapCoord.lng.toFixed(6) }}</span>
                <span class="text-sky-300 hidden md:inline">
                  UTM {{ lngLatToUtm(hoverMapCoord.lng, hoverMapCoord.lat).zone }}{{ lngLatToUtm(hoverMapCoord.lng, hoverMapCoord.lat).hemisphere }}:
                  {{ Math.round(lngLatToUtm(hoverMapCoord.lng, hoverMapCoord.lat).easting) }}E,
                  {{ Math.round(lngLatToUtm(hoverMapCoord.lng, hoverMapCoord.lat).northing) }}N
                </span>
                <span class="text-slate-300 hidden xl:inline">
                  ({{ formatDms(hoverMapCoord.lat, true) }}, {{ formatDms(hoverMapCoord.lng, false) }})
                </span>
                <UBadge v-if="hoverMapCoord.inside" color="success" variant="solid" size="md" class="font-semibold">
                  Di atas peta digital
                </UBadge>
              </div>
              <div v-else class="text-slate-300">
                Arahkan kursor pada peta untuk membaca koordinat WGS84 (EPSG:4326)
              </div>

              <div v-if="inspectedCoord" class="flex items-center gap-2 font-mono">
                <span class="text-amber-300">Pin: {{ inspectedCoord.lat }}, {{ inspectedCoord.lng }}</span>
                <button
                  type="button"
                  class="px-2 py-0.5 rounded bg-white/15 hover:bg-white/25 text-white font-sans font-semibold cursor-pointer"
                  @click="copyInspectedCoord"
                >
                  {{ inspectedCoord.copied ? 'Tersalin' : 'Salin' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Collapsible Fixed-Height Bottom Strip: GCP Form / List & 4 Corner Coordinates -->
      <div
        v-if="showBottomPanel"
        class="shrink-0 mx-3 mb-3 grid grid-cols-1 lg:grid-cols-12 gap-3 text-xs lg:h-[178px]"
      >
        <div class="lg:col-span-7 rounded-xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-[#0b0f19] flex flex-col justify-between overflow-hidden">
          <!-- Mode 1: Input Koordinat GCP (tanpa menggeser tinggi kanvas PDF) -->
          <div v-if="isGcpCoordPanelOpen" class="space-y-2 h-full flex flex-col justify-between">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-900 dark:text-white">
                  {{
                    editingGcpIndex !== null
                      ? `Ubah Koordinat GCP-${editingGcpIndex + 1}`
                      : `Isi Koordinat GCP-${gcps.length + 1}`
                  }}
                </span>
                <span
                  v-if="pendingPixelPoint"
                  class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-800 dark:text-sky-200 font-mono text-[10px]"
                >
                  px({{ Math.round(pendingPixelPoint.px) }}, {{ Math.round(pendingPixelPoint.py) }})
                </span>
              </div>

              <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-0.5">
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-[11px] font-semibold cursor-pointer transition-colors"
                  :class="gcpCoordFormat === 'utm'
                    ? 'bg-[#2B5B9E] text-white'
                    : 'text-slate-600 dark:text-slate-300'"
                  @click="gcpCoordFormat = 'utm'"
                >
                  UTM (49S Bojonegoro)
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-[11px] font-semibold cursor-pointer transition-colors"
                  :class="gcpCoordFormat === 'decimal'
                    ? 'bg-[#2B5B9E] text-white'
                    : 'text-slate-600 dark:text-slate-300'"
                  @click="gcpCoordFormat = 'decimal'"
                >
                  Desimal (WGS84)
                </button>
              </div>
            </div>

            <form class="space-y-1.5" @submit.prevent="commitGcpCoordForm">
              <div v-if="gcpCoordFormat === 'utm'" class="grid grid-cols-12 gap-2">
                <div class="col-span-5">
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-0.5">
                    Easting X (6 digit m)
                  </label>
                  <input
                    ref="gcpFirstInputRef"
                    v-model="gcpForm.utmEasting"
                    type="text"
                    inputmode="decimal"
                    placeholder="597167.15"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div class="col-span-4">
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-0.5">
                    Northing Y (7 digit m)
                  </label>
                  <input
                    v-model="gcpForm.utmNorthing"
                    type="text"
                    inputmode="decimal"
                    placeholder="9208470.62"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div class="col-span-3">
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-0.5">
                    Zona UTM
                  </label>
                  <div class="flex gap-1">
                    <select
                      v-model.number="gcpForm.utmZone"
                      aria-label="Zona UTM"
                      class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-1 py-1 text-[11px] font-mono text-slate-900 dark:text-white"
                    >
                      <option v-for="z in INDONESIA_UTM_ZONES" :key="z" :value="z">{{ z }}</option>
                    </select>
                    <select
                      v-model="gcpForm.utmHemisphere"
                      aria-label="Hemisfer UTM"
                      class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-1 py-1 text-[11px] font-mono text-slate-900 dark:text-white"
                    >
                      <option value="S">S</option>
                      <option value="N">N</option>
                    </select>
                  </div>
                </div>
              </div>

              <div v-else class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-0.5">
                    Latitude (Y)
                  </label>
                  <input
                    ref="gcpFirstInputRef"
                    v-model="gcpForm.decLat"
                    type="text"
                    inputmode="decimal"
                    placeholder="-7.160000"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div>
                  <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-0.5">
                    Longitude (X)
                  </label>
                  <input
                    v-model="gcpForm.decLng"
                    type="text"
                    inputmode="decimal"
                    placeholder="111.880000"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div v-if="gcpInputError" class="text-[11px] font-medium text-red-700 dark:text-red-300">
                {{ gcpInputError }}
              </div>

              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-[11px] font-mono text-slate-600 dark:text-slate-300 tabular-nums truncate max-w-md">
                  <span v-if="gcpResolvedPreview">
                    WGS84: {{ gcpResolvedPreview.lat }}, {{ gcpResolvedPreview.lng }}
                  </span>
                  <span v-else class="font-sans text-slate-500 dark:text-slate-400">
                    Ketik angka koordinat grid atau klik lokasi di MapLibre kanan.
                  </span>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                  <UButton label="Batal" size="xs" color="neutral" variant="ghost" @click="cancelGcpCoordPanel" />
                  <UButton
                    type="submit"
                    icon="i-lucide-plus"
                    :label="editingGcpIndex !== null ? 'Perbarui Titik' : 'Terapkan Titik'"
                    size="xs"
                    color="primary"
                    :disabled="!gcpResolvedPreview"
                    class="cursor-pointer"
                  />
                </div>
              </div>
            </form>
          </div>

          <!-- Mode 2: Daftar Titik Ikat GCP -->
          <div v-else class="space-y-1.5 h-full flex flex-col">
            <div class="flex items-center justify-between shrink-0">
              <h2 class="font-bold text-slate-900 dark:text-white">
                Titik Ikat / GCP ({{ gcps.length }})
              </h2>
              <UButton
                v-if="gcps.length > 0 && canManage"
                label="Reset Titik"
                size="xs"
                color="neutral"
                variant="ghost"
                class="cursor-pointer"
                @click="resetGcps"
              />
            </div>

            <p v-if="gcps.length === 0" class="text-slate-500 dark:text-slate-400">
              Arahkan kaca pembesar pada perpotongan garis grid di Kanvas Peta Digital (kiri), klik tepat di titik tengahnya, lalu isi koordinat UTM 49S / Desimal atau klik MapLibre kanan.
            </p>

            <div v-else class="flex-1 min-h-0 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
              <div v-for="(gcp, idx) in gcps" :key="gcp.id" class="py-1 flex items-center justify-between gap-2 tabular-nums">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="w-5 h-5 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0">
                    {{ idx + 1 }}
                  </span>
                  <span class="font-mono text-slate-700 dark:text-slate-300 truncate">
                    px({{ Math.round(gcp.px) }}, {{ Math.round(gcp.py) }}) -> ({{ gcp.lat.toFixed(6) }}, {{ gcp.lng.toFixed(6) }}) · {{ formatGcpUtmSummary(gcp) }}
                  </span>
                  <span
                    v-if="gcpPointErrors[gcp.id] !== undefined"
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold shrink-0"
                    :class="gcpPointErrors[gcp.id]! > 25
                      ? 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300'
                      : gcpPointErrors[gcp.id]! > 5
                        ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300'
                        : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
                  >
                    {{ gcpPointErrors[gcp.id] }} m
                  </span>
                </div>
                <div v-if="canManage" class="inline-flex items-center gap-0.5 shrink-0">
                  <UButton
                    icon="i-lucide-pencil"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    aria-label="Ubah koordinat titik ikat"
                    class="cursor-pointer"
                    @click="openEditGcpCoord(idx)"
                  />
                  <UButton
                    icon="i-lucide-trash-2"
                    size="xs"
                    color="error"
                    variant="ghost"
                    aria-label="Hapus titik ikat"
                    class="cursor-pointer"
                    @click="removeGcp(idx)"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="lg:col-span-5 rounded-xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-[#0b0f19] space-y-1.5">
          <h2 class="font-bold text-slate-900 dark:text-white">
            Koordinat 4 Sudut Overlay MapLibre
          </h2>
          <div v-if="corners" class="grid grid-cols-2 gap-1.5 font-mono text-[11px] tabular-nums text-slate-700 dark:text-slate-300">
            <div v-for="(c, idx) in corners" :key="idx" class="px-2 py-1 rounded bg-slate-50 dark:bg-white/[0.04] border border-slate-200/70 dark:border-slate-800">
              <span class="font-bold text-[#2B5B9E] dark:text-sky-300 mr-1">{{ CORNER_LABELS[idx] }}:</span>
              <span>{{ c[1].toFixed(5) }}, {{ c[0].toFixed(5) }}</span>
            </div>
          </div>
          <p v-else class="text-slate-500 dark:text-slate-400">
            Koordinat 4 sudut akan tampil setelah minimal 3 GCP atau Pasangkan ke Wilayah digunakan.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

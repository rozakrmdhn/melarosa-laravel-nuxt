<script setup lang="ts">
import type {
  BojonegoroIsometricDataset,
  IsoBasemapStyle,
  IsoThemeMode,
  LngLat,
} from "~/types/isometric-map";
import {
  getTileUrl,
  lngLatToTileCoords,
  tileCoordsToLngLatBounds,
} from "~/utils/isometric-city-renderer";

const props = defineProps<{
  dataset: BojonegoroIsometricDataset | null;
  viewportQuad: LngLat[];
  cameraCenterCoord: LngLat;
  basemapStyle: IsoBasemapStyle;
  themeMode: IsoThemeMode;
  activeLandmarkId?: string;
}>();

const emit = defineEmits<{
  (e: "navigate", coord: LngLat): void;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isMobileOpen = ref(false);
const isDesktopCollapsed = ref(false);
let isScrubbing = false;

const WIDTH = 208;
const HEIGHT = 148;

const tileCache = new Map<string, HTMLImageElement>();

function lngToMercX(lng: number): number {
  return (lng + 180) / 360;
}

function latToMercY(lat: number): number {
  const rad = (lat * Math.PI) / 180;
  return (1 - Math.log(Math.tan(rad) + 1 / Math.cos(rad)) / Math.PI) / 2;
}

function mercXToLng(mx: number): number {
  return mx * 360 - 180;
}

function mercYToLat(my: number): number {
  const n = Math.PI - 2 * Math.PI * my;
  return (180 / Math.PI) * Math.atan(Math.sinh(n));
}

function getBboxMerc() {
  const bbox = props.dataset?.bbox || [111.85425, -7.18571, 111.9334, -7.1317];
  const minX = lngToMercX(bbox[0]);
  const maxX = lngToMercX(bbox[2]);
  const minY = latToMercY(bbox[3]); // north
  const maxY = latToMercY(bbox[1]); // south
  const spanX = maxX - minX;
  const spanY = maxY - minY;
  // Tambah padding 6% di sekeliling batas Kecamatan Bojonegoro
  const padX = spanX * 0.06;
  const padY = spanY * 0.06;
  return {
    minX: minX - padX,
    maxX: maxX + padX,
    minY: minY - padY,
    maxY: maxY + padY,
  };
}

function coordToPixel(coord: LngLat, b: ReturnType<typeof getBboxMerc>): [number, number] {
  const mx = lngToMercX(coord[0]);
  const my = latToMercY(coord[1]);
  const px = ((mx - b.minX) / (b.maxX - b.minX)) * WIDTH;
  const py = ((my - b.minY) / (b.maxY - b.minY)) * HEIGHT;
  return [px, py];
}

function pixelToCoord(px: number, py: number, b: ReturnType<typeof getBboxMerc>): LngLat {
  const mx = b.minX + (Math.max(0, Math.min(WIDTH, px)) / WIDTH) * (b.maxX - b.minX);
  const my = b.minY + (Math.max(0, Math.min(HEIGHT, py)) / HEIGHT) * (b.maxY - b.minY);
  return [mercXToLng(mx), mercYToLat(my)];
}

function loadTile(style: IsoBasemapStyle, z: number, tx: number, ty: number): HTMLImageElement | null {
  const effectiveStyle: IsoBasemapStyle = style === "pixel" ? "satellite" : style;
  const key = `${effectiveStyle}/${z}/${tx}/${ty}`;
  const existing = tileCache.get(key);
  if (existing) {
    return existing.complete && existing.naturalWidth > 0 ? existing : null;
  }
  const img = new Image();
  img.crossOrigin = "anonymous";
  img.onload = () => drawMinimap();
  img.src = getTileUrl(effectiveStyle, z, tx, ty);
  tileCache.set(key, img);
  return null;
}

function drawMinimap() {
  const canvas = canvasRef.value;
  if (!canvas || !props.dataset) return;
  const dpr = Math.min(2, window.devicePixelRatio || 1);
  if (canvas.width !== WIDTH * dpr || canvas.height !== HEIGHT * dpr) {
    canvas.width = WIDTH * dpr;
    canvas.height = HEIGHT * dpr;
  }
  const ctx = canvas.getContext("2d");
  if (!ctx) return;

  ctx.save();
  ctx.scale(dpr, dpr);
  ctx.fillStyle = props.themeMode === "night" ? "#0b1220" : "#1e293b";
  ctx.fillRect(0, 0, WIDTH, HEIGHT);

  const bMerc = getBboxMerc();
  const bbox = props.dataset.bbox;
  const z = 13;
  const nw = lngLatToTileCoords(bbox[0], bbox[3], z);
  const se = lngLatToTileCoords(bbox[2], bbox[1], z);

  // 1. Tile peta bumi nyata (Satelit / Foto Udara / OSM)
  for (let tx = nw.tx; tx <= se.tx; tx++) {
    for (let ty = nw.ty; ty <= se.ty; ty++) {
      const tb = tileCoordsToLngLatBounds(z, tx, ty);
      const [x0, y0] = coordToPixel([tb.west, tb.north], bMerc);
      const [x1, y1] = coordToPixel([tb.east, tb.south], bMerc);
      const img = loadTile(props.basemapStyle, z, tx, ty);
      if (img) {
        ctx.drawImage(img, x0, y0, x1 - x0 + 0.5, y1 - y0 + 0.5);
      }
    }
  }

  if (props.themeMode === "night") {
    ctx.fillStyle = "rgba(7, 15, 32, 0.5)";
    ctx.fillRect(0, 0, WIDTH, HEIGHT);
  }

  // 2. Mask lembut area luar Kecamatan Bojonegoro agar batas kecamatan terbaca jelas
  ctx.save();
  ctx.fillStyle = "rgba(8, 15, 28, 0.34)";
  ctx.beginPath();
  ctx.rect(0, 0, WIDTH, HEIGHT);
  const boundaryRing = props.dataset.boundary.ring;
  if (boundaryRing.length >= 3) {
    boundaryRing.forEach((c, idx) => {
      const [px, py] = coordToPixel(c, bMerc);
      if (idx === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    });
    ctx.closePath();
    ctx.fill("evenodd");
  }
  ctx.restore();

  // 3. Batas Kelurahan (halus) & Batas Kecamatan (kuning keemasan)
  ctx.strokeStyle = "rgba(255, 255, 255, 0.45)";
  ctx.lineWidth = 0.7;
  for (const kel of props.dataset.kelurahanBoundaries || []) {
    if (kel.ring.length < 3) continue;
    ctx.beginPath();
    kel.ring.forEach((c, idx) => {
      const [px, py] = coordToPixel(c, bMerc);
      if (idx === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    });
    ctx.closePath();
    ctx.stroke();
  }

  if (boundaryRing.length >= 3) {
    ctx.strokeStyle = "#facc15";
    ctx.lineWidth = 1.4;
    ctx.beginPath();
    boundaryRing.forEach((c, idx) => {
      const [px, py] = coordToPixel(c, bMerc);
      if (idx === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    });
    ctx.closePath();
    ctx.stroke();
  }

  // 4. Sungai Bengawan Solo & koridor utama
  for (const w of props.dataset.waterways) {
    if (w.widthMeters < 30 || w.coords.length < 2) continue;
    ctx.strokeStyle = "rgba(56, 189, 248, 0.75)";
    ctx.lineWidth = 2;
    ctx.beginPath();
    w.coords.forEach((c, idx) => {
      const [px, py] = coordToPixel(c, bMerc);
      if (idx === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    });
    if (w.geometryType === "polygon") ctx.closePath();
    ctx.stroke();
  }

  // 5. Titik landmark
  for (const lm of props.dataset.landmarks) {
    const [px, py] = coordToPixel(lm.coordinate, bMerc);
    const active = lm.id === props.activeLandmarkId;
    ctx.fillStyle = active ? "#f59e0b" : "#ffffff";
    ctx.beginPath();
    ctx.arc(px, py, active ? 3.2 : 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = "#0f172a";
    ctx.lineWidth = 0.8;
    ctx.stroke();
  }

  // 6. Trapesium / poligon viewport kamera isometrik saat ini
  if (props.viewportQuad.length >= 4) {
    ctx.beginPath();
    props.viewportQuad.forEach((c, idx) => {
      const [px, py] = coordToPixel(c, bMerc);
      if (idx === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    });
    ctx.closePath();
    ctx.fillStyle = "rgba(56, 189, 248, 0.24)";
    ctx.fill();
    ctx.strokeStyle = "#38bdf8";
    ctx.lineWidth = 1.6;
    ctx.stroke();
  }

  // 7. Bidik pusat kamera
  const [cx, cy] = coordToPixel(props.cameraCenterCoord, bMerc);
  ctx.fillStyle = "#38bdf8";
  ctx.beginPath();
  ctx.arc(cx, cy, 3, 0, Math.PI * 2);
  ctx.fill();
  ctx.strokeStyle = "#ffffff";
  ctx.lineWidth = 1.2;
  ctx.stroke();

  ctx.restore();
}

function collapseMinimap() {
  if (typeof window !== "undefined" && window.innerWidth < 640) {
    isMobileOpen.value = false;
  } else {
    isDesktopCollapsed.value = true;
  }
}

function handleNavigateEvent(e: PointerEvent) {
  if (!canvasRef.value) return;
  const rect = canvasRef.value.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;
  const coord = pixelToCoord(x, y, getBboxMerc());
  emit("navigate", coord);
}

function onPointerDown(e: PointerEvent) {
  if (!canvasRef.value) return;
  canvasRef.value.setPointerCapture(e.pointerId);
  isScrubbing = true;
  handleNavigateEvent(e);
}

function onPointerMove(e: PointerEvent) {
  if (!isScrubbing) return;
  handleNavigateEvent(e);
}

function onPointerUp(e: PointerEvent) {
  isScrubbing = false;
  if (canvasRef.value?.hasPointerCapture(e.pointerId)) {
    canvasRef.value.releasePointerCapture(e.pointerId);
  }
}

watch(
  () => [props.dataset, props.viewportQuad, props.cameraCenterCoord, props.basemapStyle, props.themeMode, props.activeLandmarkId, isMobileOpen.value, isDesktopCollapsed.value],
  () => {
    nextTick(() => drawMinimap());
  },
  { deep: false }
);

onMounted(() => {
  nextTick(() => drawMinimap());
});
</script>

<template>
  <div class="pointer-events-auto flex flex-col items-end gap-1.5">
    <!-- Tombol toggle kompak untuk mobile (< sm) dan desktop saat diciutkan -->
    <button
      v-if="!isMobileOpen"
      type="button"
      class="sm:hidden inline-flex items-center gap-1.5 min-h-[44px] px-3 rounded-xl border text-xs font-semibold shadow-sm cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
      :class="themeMode === 'night'
        ? 'bg-[#0f172a] border-slate-700 text-slate-100'
        : 'bg-white border-slate-300 text-slate-900'"
      aria-label="Buka minimap real-world Kecamatan Bojonegoro"
      @click="isMobileOpen = true"
    >
      <UIcon
        name="i-lucide-radar"
        class="size-4"
        :class="themeMode === 'night' ? 'text-sky-400' : 'text-blue-700'"
      />
      <span>Minimap</span>
    </button>

    <button
      v-if="isDesktopCollapsed"
      type="button"
      class="hidden sm:inline-flex items-center gap-1.5 min-h-[44px] px-3 rounded-xl border text-xs font-semibold shadow-sm cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
      :class="themeMode === 'night'
        ? 'bg-[#0f172a] border-slate-700 text-slate-100'
        : 'bg-white border-slate-300 text-slate-900'"
      aria-label="Tampilkan minimap real-world"
      @click="isDesktopCollapsed = false"
    >
      <UIcon
        name="i-lucide-radar"
        class="size-4"
        :class="themeMode === 'night' ? 'text-sky-400' : 'text-blue-700'"
      />
      <span>Minimap Bumi</span>
    </button>

    <!-- Panel Radar Minimap Real-World Kecamatan Bojonegoro -->
    <div
      :class="[
        isMobileOpen ? 'flex' : 'hidden',
        isDesktopCollapsed ? 'sm:hidden' : 'sm:flex',
        themeMode === 'night'
          ? 'bg-[#0f172a] border-slate-700 text-slate-100'
          : 'bg-white border-slate-300 text-slate-900'
      ]"
      class="flex-col rounded-xl border shadow-lg overflow-hidden"
    >
      <div
        class="flex items-center justify-between gap-2 px-2.5 py-1.5 border-b text-[11px] font-semibold"
        :class="themeMode === 'night' ? 'border-slate-800 text-slate-200' : 'border-slate-200 text-slate-800'"
      >
        <span class="flex items-center gap-1.5">
          <span class="size-2 rounded-full bg-amber-400 shrink-0" />
          <span>Minimap Real-World</span>
        </span>
        <button
          type="button"
          aria-label="Sembunyikan minimap"
          class="relative inline-flex items-center justify-center size-7 rounded cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 after:content-[''] after:absolute after:-inset-2"
          :class="themeMode === 'night' ? 'hover:bg-slate-800 text-slate-300' : 'hover:bg-slate-200/70 text-slate-700'"
          @click="collapseMinimap"
        >
          <UIcon name="i-lucide-chevron-down" class="size-3.5" />
        </button>
      </div>

      <canvas
        ref="canvasRef"
        :style="{ width: `${WIDTH}px`, height: `${HEIGHT}px` }"
        aria-label="Minimap real-world Kecamatan Bojonegoro. Klik atau seret untuk melompat ke lokasi."
        class="block cursor-crosshair touch-none"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
      />

      <div
        class="flex items-center justify-between px-2.5 py-1 border-t text-[11px] font-mono"
        :class="themeMode === 'night' ? 'border-slate-800 bg-slate-900/70 text-slate-300' : 'border-slate-200 bg-slate-50 text-slate-700'"
      >
        <span>Kec. Bojonegoro</span>
        <span>{{ cameraCenterCoord[1].toFixed(4) }}, {{ cameraCenterCoord[0].toFixed(4) }}</span>
      </div>
    </div>
  </div>
</template>

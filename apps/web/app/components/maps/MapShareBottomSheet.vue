<script setup lang="ts">
// ─── Standalone Mobile Bottom Sheet for Map Sharing ──────────────────────────
// Features:
// - Height: opens at half-screen (~52% vh) by default, expandable to full (~92% vh)
// - Fluid touch & mouse drag gestures with inertia & velocity flick detection
// - Rubber-band bounce on over-drag, swipe down to dismiss
// - Full-surface drag up across all content when at half height
// - Instant mobile share actions: WhatsApp, Native Device Share, Copy Link, Download PNG
// - 5 Ergonomic social media aspect ratios (1:1, 4:5, 9:16, 16:9, 1.91:1)
// - WCAG compliant contrast, min 44x44px touch targets, full safe-area support

import type { BasemapKey, ActiveLayerItem } from "~/types/map-layers";

export type SheetSnap = "half" | "full";

export interface AspectRatioOption {
  id: string;
  label: string;
  sublabel: string;
  ratio: string;
  aspectWidth: number;
  aspectHeight: number;
  width: number;
  height: number;
  icon: string;
}

const props = withDefaults(
  defineProps<{
    open?: boolean;
    currentBasemap?: BasemapKey;
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    activeLayers?: ActiveLayerItem[];
    mapCenter?: [number, number] | null;
    mapZoom?: number | null;
    getMapSnapshot?: () => Promise<string | null>;
  }>(),
  {
    open: false,
    currentBasemap: "esri",
    clickedCoordinate: null,
    locationLabel: null,
    activeLayers: () => [],
    mapCenter: null,
    mapZoom: null,
    getMapSnapshot: undefined,
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
  (e: "after-close"): void;
}>();

// ─── Snap Constants & Dimensions ─────────────────────────────────────────────
const HALF_RATIO = 0.52; // Separuh layar (~52% viewport)
const FULL_RATIO = 0.92; // Layar penuh (~92% viewport)
const BOTTOM_BLEED_PX = 80;

const viewportH = ref(typeof window !== "undefined" ? window.innerHeight : 720);
const fullH = computed(() => Math.round(viewportH.value * FULL_RATIO));
const halfH = computed(() => Math.round(viewportH.value * HALF_RATIO));

const snapOffsets = computed<Record<SheetSnap, number>>(() => ({
  full: 0,
  half: Math.max(0, fullH.value - halfH.value),
}));

// ─── Reactive Snap & Drag State ──────────────────────────────────────────────
const snapLevel = ref<SheetSnap>("half");
const isDragging = ref(false);
const dragStartY = ref(0);
const dragStartOffset = ref(0);
const currentDragOffset = ref(0);

let dragLastY = 0;
let dragLastTime = 0;
let dragVelocity = 0;

const scrollContainerRef = ref<HTMLElement | null>(null);
let contentTouchStartX = 0;
let contentTouchStartY = 0;

// ─── TranslateY Calculation ──────────────────────────────────────────────────
const sheetTranslateY = computed(() => {
  if (!props.open) {
    return fullH.value + BOTTOM_BLEED_PX + 30; // completely hidden
  }

  if (isDragging.value) {
    const raw = currentDragOffset.value;
    const maxOffset = snapOffsets.value.half;

    // Upward rubber-band dampening beyond full (raw < 0)
    if (raw < 0) {
      return raw * 0.22;
    }
    // Downward rubber-band dampening beyond half
    if (raw > maxOffset) {
      const over = raw - maxOffset;
      return maxOffset + over * 0.45;
    }
    return raw;
  }

  return snapOffsets.value[snapLevel.value] ?? snapOffsets.value.half;
});

const sheetStyle = computed(() => ({
  height: `${fullH.value + BOTTOM_BLEED_PX}px`,
  bottom: `-${BOTTOM_BLEED_PX}px`,
  transform: `translate3d(0, ${sheetTranslateY.value}px, 0)`,
  transition: isDragging.value
    ? "none"
    : "transform 0.58s cubic-bezier(0.16, 1, 0.3, 1)",
}));

// ─── Drag Handling (Touch & Mouse) ───────────────────────────────────────────
function beginDrag(clientY: number) {
  isDragging.value = true;
  dragStartY.value = clientY;
  dragLastY = clientY;
  dragLastTime = performance.now();
  dragVelocity = 0;
  dragStartOffset.value = snapOffsets.value[snapLevel.value] ?? snapOffsets.value.half;
  currentDragOffset.value = dragStartOffset.value;

  if (typeof window !== "undefined") {
    window.addEventListener("touchmove", onWindowTouchMove, { passive: false });
    window.addEventListener("touchend", onWindowTouchEnd);
    window.addEventListener("touchcancel", onWindowTouchEnd);
    window.addEventListener("mousemove", onWindowMouseMove);
    window.addEventListener("mouseup", onWindowMouseUp);
  }
}

function updateDrag(clientY: number) {
  if (!isDragging.value) return;

  const now = performance.now();
  const dt = now - dragLastTime;
  if (dt > 8) {
    dragVelocity = (clientY - dragLastY) / dt;
    dragLastY = clientY;
    dragLastTime = now;
  }

  const delta = clientY - dragStartY.value;
  currentDragOffset.value = dragStartOffset.value + delta;
}

function finishDrag() {
  if (!isDragging.value) return;
  isDragging.value = false;

  if (typeof window !== "undefined") {
    window.removeEventListener("touchmove", onWindowTouchMove);
    window.removeEventListener("touchend", onWindowTouchEnd);
    window.removeEventListener("touchcancel", onWindowTouchEnd);
    window.removeEventListener("mousemove", onWindowMouseMove);
    window.removeEventListener("mouseup", onWindowMouseUp);
  }

  const offset = currentDragOffset.value;
  const v = dragVelocity;
  const offsets = snapOffsets.value;

  // 1. High Velocity Flick Swipes
  if (v < -0.45) {
    // Quick flick UP -> Expand to Full
    snapTo("full");
    return;
  }

  if (v > 0.45) {
    // Quick flick DOWN -> If at full go to half; if at half close sheet
    if (snapLevel.value === "full") {
      snapTo("half");
    } else {
      close();
    }
    return;
  }

  // 2. Slow Drag Threshold
  // If dragged down past half by > 65px, dismiss
  if (offset > offsets.half + 65) {
    close();
    return;
  }

  // Nearest snap calculation
  const distFull = Math.abs(offset - offsets.full);
  const distHalf = Math.abs(offset - offsets.half);

  if (distFull < distHalf) {
    snapTo("full");
  } else {
    snapTo("half");
  }
}

function onWindowTouchMove(e: TouchEvent) {
  if (!isDragging.value || !e.touches.length) return;
  if (e.cancelable) e.preventDefault();
  updateDrag(e.touches[0].clientY);
}

function onWindowTouchEnd() {
  finishDrag();
}

function onWindowMouseMove(e: MouseEvent) {
  if (!isDragging.value) return;
  updateDrag(e.clientY);
}

function onWindowMouseUp() {
  finishDrag();
}

function handleHeaderTouchStart(e: TouchEvent) {
  if (e.touches.length === 1) {
    beginDrag(e.touches[0].clientY);
  }
}

function handleHeaderMouseDown(e: MouseEvent) {
  if (e.button === 0) {
    beginDrag(e.clientY);
  }
}

// Content Touch: at half height, any upward swipe drags sheet to full
function handleContentTouchStart(e: TouchEvent) {
  if (e.touches.length !== 1) return;
  contentTouchStartX = e.touches[0].clientX;
  contentTouchStartY = e.touches[0].clientY;
}

function handleContentTouchMove(e: TouchEvent) {
  if (e.touches.length !== 1 || isDragging.value) return;

  const currentX = e.touches[0].clientX;
  const currentY = e.touches[0].clientY;
  const diffX = currentX - contentTouchStartX;
  const diffY = currentY - contentTouchStartY;
  const absDiffX = Math.abs(diffX);
  const absDiffY = Math.abs(diffY);

  if (absDiffY < 6 || absDiffY <= absDiffX) return;

  const scrollEl = scrollContainerRef.value;

  // When at 'half': any vertical drag expands to full
  if (snapLevel.value === "half") {
    if (diffY < -4) {
      if (e.cancelable) e.preventDefault();
      beginDrag(currentY);
      return;
    }
    // Pull down from half closes
    if (diffY > 8) {
      if (e.cancelable) e.preventDefault();
      beginDrag(currentY);
      return;
    }
  }

  // When at 'full': if scrolled to top and pulling DOWN, drag to half
  const isAtTop = !scrollEl || scrollEl.scrollTop <= 0;
  if (snapLevel.value === "full" && isAtTop && diffY > 8) {
    if (e.cancelable) e.preventDefault();
    beginDrag(currentY);
  }
}

function snapTo(level: SheetSnap) {
  snapLevel.value = level;
}

function toggleSnap() {
  snapTo(snapLevel.value === "half" ? "full" : "half");
}

function close() {
  emit("update:open", false);
}

// ─── Share State & Options ───────────────────────────────────────────────────
const RATIO_OPTIONS: AspectRatioOption[] = [
  {
    id: "square",
    label: "1:1",
    sublabel: "Instagram Feed",
    ratio: "1 / 1",
    aspectWidth: 1,
    aspectHeight: 1,
    width: 1080,
    height: 1080,
    icon: "i-lucide-square",
  },
  {
    id: "portrait",
    label: "4:5",
    sublabel: "Potret IG",
    ratio: "4 / 5",
    aspectWidth: 4,
    aspectHeight: 5,
    width: 1080,
    height: 1350,
    icon: "i-lucide-rectangle-vertical",
  },
  {
    id: "story",
    label: "9:16",
    sublabel: "Story & Reels",
    ratio: "9 / 16",
    aspectWidth: 9,
    aspectHeight: 16,
    width: 1080,
    height: 1920,
    icon: "i-lucide-smartphone",
  },
  {
    id: "landscape",
    label: "16:9",
    sublabel: "Lansekap",
    ratio: "16 / 9",
    aspectWidth: 16,
    aspectHeight: 9,
    width: 1200,
    height: 675,
    icon: "i-lucide-monitor",
  },
  {
    id: "banner",
    label: "1.91:1",
    sublabel: "X / FB Post",
    ratio: "1.91 / 1",
    aspectWidth: 1.91,
    aspectHeight: 1,
    width: 1200,
    height: 630,
    icon: "i-lucide-globe",
  },
];

const selectedRatioId = ref("square");
const selectedRatio = computed(() => {
  return RATIO_OPTIONS.find((r) => r.id === selectedRatioId.value) || RATIO_OPTIONS[0];
});

// ─── Penghitungan Dimensi Pratinjau Sesuai Rasio Aktif ─────────────────────
const previewCardStyle = computed(() => {
  const { aspectWidth, aspectHeight, ratio } = selectedRatio.value;
  const targetRatio = aspectWidth / aspectHeight;

  // Dimensi panggung adaptif untuk kenyamanan layar mobile smartphone
  const stageH = snapLevel.value === "full" ? 230 : 175;
  const stageW = 310;

  if (targetRatio <= 1) {
    // Rasio vertikal & persegi (9:16 Story, 4:5 Potret, 1:1 Persegi)
    const computedH = stageH;
    const computedW = Math.round(computedH * targetRatio);
    return {
      aspectRatio: ratio,
      height: `${computedH}px`,
      width: `${computedW}px`,
      maxWidth: "100%",
    };
  } else {
    // Rasio horizontal (16:9 Lansekap, 1.91:1 Banner)
    const maxW = Math.min(stageW, stageH * targetRatio);
    const computedH = Math.round(maxW / targetRatio);
    return {
      aspectRatio: ratio,
      width: `${Math.round(maxW)}px`,
      height: `${computedH}px`,
      maxWidth: "100%",
    };
  }
});

type ShareMode = "image_link" | "image_only";
const shareMode = ref<ShareMode>("image_link");

const customTitle = ref("");
const customDescription = ref("");
const captionText = ref("");

const isCopiedCaption = ref(false);
let copiedCaptionTimer: any = null;

async function handleCopyCaption() {
  try {
    await navigator.clipboard.writeText(captionText.value);
    isCopiedCaption.value = true;
    if (copiedCaptionTimer) clearTimeout(copiedCaptionTimer);
    copiedCaptionTimer = setTimeout(() => {
      isCopiedCaption.value = false;
    }, 2000);
  } catch (err) {
    console.error("Gagal menyalin caption:", err);
  }
}

const effectiveCenter = computed<[number, number] | null>(() => {
  if (props.clickedCoordinate) return props.clickedCoordinate;
  if (props.mapCenter) return props.mapCenter;
  return [111.8817, -7.1502]; // Bojonegoro Kota
});

const formattedCoordinateString = computed(() => {
  if (!effectiveCenter.value) return "-7.15000, 111.88000";
  const lat = effectiveCenter.value[1].toFixed(5);
  const lng = effectiveCenter.value[0].toFixed(5);
  return `${lat}, ${lng}`;
});

const currentMapUrl = computed(() => {
  if (!import.meta.client) return "https://melarosa.bojonegorokab.go.id/maps";
  return window.location.href;
});

function generateDefaultCaption(mode: ShareMode): string {
  const title = customTitle.value || (props.locationLabel ? `Wilayah ${props.locationLabel}` : "Peta Geospasial Bojonegoro");
  const desc = customDescription.value ? `${customDescription.value}\n` : "";
  const coords = effectiveCenter.value
    ? `Koordinat: ${effectiveCenter.value[1].toFixed(5)}, ${effectiveCenter.value[0].toFixed(5)} (WGS84)`
    : "Wilayah Kabupaten Bojonegoro, Jawa Timur";

  const layerInfo = props.activeLayers.length > 0
    ? `\nLapisan Peta:\n` + props.activeLayers.map((l) => `• ${l.name || l.title}${l.instansi || l.attribution || l.agency ? ` (${l.instansi || l.attribution || l.agency})` : ""}`).join("\n")
    : "";

  const linkInfo = mode === "image_link"
    ? `\n\nBuka peta interaktif:\n${currentMapUrl.value}`
    : "";

  return `${title}\n${desc}${coords}${layerInfo}${linkInfo}\n\n#Bojonegoro #MelarosaGIS #SatuDataSpasial #WebGIS`;
}

function resetAllTexts() {
  customTitle.value = props.locationLabel ? `Wilayah ${props.locationLabel}` : "Peta Spasial Bojonegoro";
  customDescription.value = "Informasi geospasial resmi Kabupaten Bojonegoro";
  captionText.value = generateDefaultCaption(shareMode.value);
}

// ─── Snapshot & Rendering ───────────────────────────────────────────────────
const snapshotDataUrl = ref<string | null>(null);
const isLoadingSnapshot = ref(false);

async function captureMap() {
  if (!props.getMapSnapshot) return;
  isLoadingSnapshot.value = true;
  try {
    const data = await props.getMapSnapshot();
    if (data) {
      snapshotDataUrl.value = data;
    }
  } catch (err) {
    console.warn("Gagal mengambil snapshot peta:", err);
  } finally {
    isLoadingSnapshot.value = false;
  }
}

watch(
  () => props.open,
  (opened) => {
    if (opened) {
      snapLevel.value = "half"; // Selalu buka di tinggi separuh layar
      resetAllTexts();
      captureMap();
    }
  }
);

watch(
  [selectedRatioId, shareMode, customTitle, customDescription],
  () => {
    captionText.value = generateDefaultCaption(shareMode.value);
  }
);

// ─── Native Canvas Renderer ─────────────────────────────────────────────────
function getWrappedLines(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string[] {
  if (!text) return [];
  const words = text.trim().split(/\s+/);
  const lines: string[] = [];
  let currentLine = "";

  for (let i = 0; i < words.length; i++) {
    const word = words[i];
    const testLine = currentLine ? `${currentLine} ${word}` : word;
    const testWidth = ctx.measureText(testLine).width;
    if (testWidth > maxWidth && currentLine) {
      lines.push(currentLine);
      currentLine = word;
    } else {
      currentLine = testLine;
    }
  }
  if (currentLine) {
    lines.push(currentLine);
  }
  return lines;
}

function drawFallbackGrid(ctx: CanvasRenderingContext2D, W: number, H: number) {
  ctx.fillStyle = "#0b1220";
  ctx.fillRect(0, 0, W, H);

  ctx.strokeStyle = "rgba(59, 130, 246, 0.08)";
  ctx.lineWidth = 1;
  const step = Math.round(W / 12);
  for (let x = 0; x < W; x += step) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x, H);
    ctx.stroke();
  }
  for (let y = 0; y < H; y += step) {
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(W, y);
    ctx.stroke();
  }

  const cx = W / 2;
  const cy = H / 2;
  ctx.strokeStyle = "rgba(96, 165, 250, 0.4)";
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.arc(cx, cy, 32, 0, Math.PI * 2);
  ctx.stroke();
  ctx.moveTo(cx - 48, cy);
  ctx.lineTo(cx + 48, cy);
  ctx.moveTo(cx, cy - 48);
  ctx.lineTo(cx, cy + 48);
  ctx.stroke();
}

async function drawCardToCanvas(): Promise<HTMLCanvasElement> {
  const target = selectedRatio.value;
  const canvas = document.createElement("canvas");
  canvas.width = target.width;
  canvas.height = target.height;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Canvas context not available");

  const W = target.width;
  const H = target.height;
  const isStory = target.id === "story";

  // Base canvas background
  ctx.fillStyle = "#070b14";
  ctx.fillRect(0, 0, W, H);

  // Load logo Bojonegoro
  let logoImg: HTMLImageElement | null = null;
  try {
    const img = new Image();
    img.crossOrigin = "anonymous";
    await new Promise<void>((resolve) => {
      img.onload = () => {
        logoImg = img;
        resolve();
      };
      img.onerror = () => resolve();
      img.src = "/logo-kabupaten.png";
    });
  } catch {
    logoImg = null;
  }

  // Load Background Citra Peta Konektivitas Bojonegoro
  let heroBgImg: HTMLImageElement | null = null;
  try {
    const img = new Image();
    img.crossOrigin = "anonymous";
    await new Promise<void>((resolve) => {
      img.onload = () => {
        heroBgImg = img;
        resolve();
      };
      img.onerror = () => resolve();
      img.src = "/images/hero-bojonegoro-connectivity.jpg";
    });
  } catch {
    heroBgImg = null;
  }

  // Load snapshot peta
  let mapImg: HTMLImageElement | null = null;
  if (snapshotDataUrl.value) {
    try {
      const img = new Image();
      img.crossOrigin = "anonymous";
      await new Promise<void>((resolve, reject) => {
        img.onload = () => {
          mapImg = img;
          resolve();
        };
        img.onerror = () => reject();
        img.src = snapshotDataUrl.value!;
      });
    } catch {
      mapImg = null;
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // 1. TATA LETAK SPLIT-LAYOUT (SIDE-BY-SIDE ATAU VERTICAL UNTUK STORY)
  // ═══════════════════════════════════════════════════════════════════════════
  if (!isStory) {
    // ─── SPLIT-LAYOUT HORISONTAL (16:9, 1.91:1, 1:1, 4:5) ───────────────────
    const panelW = Math.round(W * (W > H ? 0.52 : 0.54));
    const mapX = panelW;
    const mapW = W - panelW;
    const pad = Math.round(panelW * 0.08);

    // [A] SISI KANAN: AREA PETA
    if (mapImg) {
      const imgRatio = mapImg.width / mapImg.height;
      const targetMapRatio = mapW / H;
      let sWidth = mapImg.width;
      let sHeight = mapImg.height;
      let sX = 0;
      let sY = 0;

      if (imgRatio > targetMapRatio) {
        sWidth = mapImg.height * targetMapRatio;
        sX = (mapImg.width - sWidth) / 2;
      } else {
        sHeight = mapImg.width / targetMapRatio;
        sY = (mapImg.height - sHeight) / 2;
      }

      ctx.drawImage(mapImg, sX, sY, sWidth, sHeight, mapX, 0, mapW, H);

      // Vignette peta halus
      const mapVignette = ctx.createLinearGradient(mapX, 0, mapX + mapW, 0);
      mapVignette.addColorStop(0, "rgba(7, 14, 34, 0.45)");
      mapVignette.addColorStop(0.3, "rgba(7, 14, 34, 0.05)");
      mapVignette.addColorStop(1, "rgba(7, 14, 34, 0.35)");
      ctx.fillStyle = mapVignette;
      ctx.fillRect(mapX, 0, mapW, H);
    } else {
      drawFallbackGrid(ctx, mapW, H);
    }

    // Retikel Bidik Kartografis di Tengah Peta
    const mapCx = mapX + mapW / 2;
    const mapCy = H / 2;
    ctx.strokeStyle = "rgba(96, 165, 250, 0.6)";
    ctx.lineWidth = Math.max(1, Math.round(W * 0.0015));
    ctx.beginPath();
    ctx.arc(mapCx, mapCy, Math.round(panelW * 0.06), 0, Math.PI * 2);
    ctx.stroke();

    ctx.fillStyle = "#60a5fa";
    ctx.beginPath();
    ctx.arc(mapCx, mapCy, Math.round(panelW * 0.012), 0, Math.PI * 2);
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(mapCx - Math.round(panelW * 0.09), mapCy);
    ctx.lineTo(mapCx + Math.round(panelW * 0.09), mapCy);
    ctx.moveTo(mapCx, mapCy - Math.round(panelW * 0.09));
    ctx.lineTo(mapCx, mapCy + Math.round(panelW * 0.09));
    ctx.stroke();

    // Tag Rasio di Kanan Atas Peta
    const ratioTagH = Math.round(panelW * 0.05);
    const ratioTagPadX = Math.round(panelW * 0.025);
    ctx.font = `600 ${Math.round(panelW * 0.026)}px ui-monospace, monospace`;
    const ratioTextW = ctx.measureText(target.label).width;
    ctx.fillStyle = "rgba(0, 0, 0, 0.75)";
    ctx.strokeStyle = "rgba(255, 255, 255, 0.25)";
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.roundRect(W - pad - ratioTextW - ratioTagPadX * 2, pad, ratioTextW + ratioTagPadX * 2, ratioTagH, 6);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = "#ffffff";
    ctx.fillText(target.label, W - pad - ratioTextW - ratioTagPadX, pad + Math.round(ratioTagH * 0.68));

    // [B] SISI KIRI: PANEL DARK MODE GLASSMORPHISM DENGAN BACKGROUND HERO CONNECTIVITY
    if (heroBgImg) {
      const imgRatio = heroBgImg.width / heroBgImg.height;
      const panelRatio = panelW / H;
      let sWidth = heroBgImg.width;
      let sHeight = heroBgImg.height;
      let sX = 0;
      let sY = 0;
      if (imgRatio > panelRatio) {
        sWidth = heroBgImg.height * panelRatio;
        sX = (heroBgImg.width - sWidth) / 2;
      } else {
        sHeight = heroBgImg.width / panelRatio;
        sY = (heroBgImg.height - sHeight) / 2;
      }
      ctx.drawImage(heroBgImg, sX, sY, sWidth, sHeight, 0, 0, panelW, H);
    }

    // Dark glass gradient overlay agar teks berkontras tinggi dan terbaca tajam
    const panelGrad = ctx.createLinearGradient(0, 0, panelW, H);
    panelGrad.addColorStop(0, "rgba(6, 12, 29, 0.88)");
    panelGrad.addColorStop(0.5, "rgba(7, 17, 38, 0.84)");
    panelGrad.addColorStop(1, "rgba(4, 8, 20, 0.94)");
    ctx.fillStyle = panelGrad;
    ctx.fillRect(0, 0, panelW, H);

    // Radial glow biru di kiri atas
    const radialGlow = ctx.createRadialGradient(0, 0, 0, 0, 0, panelW * 0.95);
    radialGlow.addColorStop(0, "rgba(37, 99, 235, 0.26)");
    radialGlow.addColorStop(1, "rgba(37, 99, 235, 0)");
    ctx.fillStyle = radialGlow;
    ctx.fillRect(0, 0, panelW, H);

    // Garis batas vertikal pembagi kaca
    ctx.strokeStyle = "rgba(59, 130, 246, 0.35)";
    ctx.lineWidth = Math.max(1, Math.round(W * 0.002));
    ctx.beginPath();
    ctx.moveTo(panelW, 0);
    ctx.lineTo(panelW, H);
    ctx.stroke();

    // 1. Header Kiri Atas: Logo & Instansi (First Capital / Title Case)
    let headerTextX = pad;
    const logoH = Math.round(panelW * 0.13);
    if (logoImg) {
      const logoW = Math.round(logoH * (logoImg.width / logoImg.height));
      ctx.drawImage(logoImg, pad, pad, logoW, logoH);
      headerTextX = pad + logoW + Math.round(panelW * 0.035);
    }

    ctx.fillStyle = "#ffffff";
    ctx.font = `bold ${Math.round(panelW * 0.044)}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    ctx.fillText("Pemerintah Kabupaten Bojonegoro", headerTextX, pad + Math.round(logoH * 0.44));

    ctx.fillStyle = "#93c5fd";
    ctx.font = `500 ${Math.round(panelW * 0.031)}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    ctx.fillText("Badan Perencanaan Pembangunan Daerah", headerTextX, pad + Math.round(logoH * 0.88));

    // 2. Bagian Tengah Kiri: Judul Utama & Deskripsi (Plus Jakarta Sans - Modern & Tidak Kaku)
    const maxTextW = panelW - pad * 2;
    const title = customTitle.value || (props.locationLabel ? `Wilayah ${props.locationLabel}` : "Peta Spasial Bojonegoro");
    const titleFontSize = Math.round(panelW * 0.066);
    const titleLineHeight = Math.round(titleFontSize * 1.25);
    ctx.font = `bold ${titleFontSize}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    const titleLines = getWrappedLines(ctx, title, maxTextW);

    const desc = customDescription.value || "Informasi geospasial resmi Kabupaten Bojonegoro";
    const descFontSize = Math.round(panelW * 0.035);
    const descLineHeight = Math.round(descFontSize * 1.35);
    ctx.font = `400 ${descFontSize}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    const descLines = getWrappedLines(ctx, desc, maxTextW);

    const footerH = Math.round(panelW * 0.07);

    // Komputasi Vertikal Tengah
    const totalMiddleH = (titleLines.length * titleLineHeight)
      + Math.round(panelW * 0.02)
      + (descLines.length * descLineHeight);

    let currentY = Math.round(pad + logoH + (H - pad * 2 - logoH - footerH - totalMiddleH) * 0.48);

    // Gambar Judul Utama (Fluid, Modern, High Contrast)
    ctx.fillStyle = "#ffffff";
    ctx.font = `bold ${titleFontSize}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    for (const line of titleLines) {
      ctx.fillText(line, pad, currentY);
      currentY += titleLineHeight;
    }

    // Gambar Deskripsi
    currentY += Math.round(panelW * 0.015);
    ctx.fillStyle = "#cbd5e1";
    ctx.font = `400 ${descFontSize}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    for (const line of descLines) {
      ctx.fillText(line, pad, currentY);
      currentY += descLineHeight;
    }

    // 3. Bagian Bawah: Garis Tipis & Footer (Hanya domain melarosa, teks Bappeda dihilangkan)
    const footerLineY = H - pad - Math.round(panelW * 0.065);
    ctx.strokeStyle = "rgba(255, 255, 255, 0.16)";
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad, footerLineY);
    ctx.lineTo(panelW - pad, footerLineY);
    ctx.stroke();

    const footerTextY = H - pad;
    ctx.fillStyle = "#60a5fa";
    ctx.font = `bold ${Math.round(panelW * 0.032)}px ui-monospace, monospace`;
    ctx.textAlign = "right";
    ctx.fillText("melarosa.bojonegorokab.go.id", panelW - pad, footerTextY);
    ctx.textAlign = "left";
  } else {
    // ─── SPLIT-LAYOUT VERTIKAL KHUSUS STORY (9:16) ───────────────────────────
    const mapH = Math.round(H * 0.48);
    const panelY = mapH;
    const panelH = H - mapH;
    const pad = Math.round(W * 0.08);

    // [A] BAGIAN ATAS: AREA PETA
    if (mapImg) {
      const imgRatio = mapImg.width / mapImg.height;
      const targetMapRatio = W / mapH;
      let sWidth = mapImg.width;
      let sHeight = mapImg.height;
      let sX = 0;
      let sY = 0;

      if (imgRatio > targetMapRatio) {
        sWidth = mapImg.height * targetMapRatio;
        sX = (mapImg.width - sWidth) / 2;
      } else {
        sHeight = mapImg.width / targetMapRatio;
        sY = (mapImg.height - sHeight) / 2;
      }

      ctx.drawImage(mapImg, sX, sY, sWidth, sHeight, 0, 0, W, mapH);

      // Vignette bawah peta
      const mapVignette = ctx.createLinearGradient(0, mapH * 0.6, 0, mapH);
      mapVignette.addColorStop(0, "rgba(7, 14, 34, 0)");
      mapVignette.addColorStop(1, "rgba(7, 14, 34, 0.8)");
      ctx.fillStyle = mapVignette;
      ctx.fillRect(0, 0, W, mapH);
    } else {
      drawFallbackGrid(ctx, W, mapH);
    }

    // Retikel Bidik di Tengah Peta Story
    const mapCx = W / 2;
    const mapCy = mapH / 2;
    ctx.strokeStyle = "rgba(96, 165, 250, 0.6)";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(mapCx, mapCy, Math.round(W * 0.07), 0, Math.PI * 2);
    ctx.stroke();

    ctx.fillStyle = "#60a5fa";
    ctx.beginPath();
    ctx.arc(mapCx, mapCy, Math.round(W * 0.015), 0, Math.PI * 2);
    ctx.fill();

    // Tag Story di Kanan Atas
    ctx.fillStyle = "rgba(0, 0, 0, 0.75)";
    ctx.strokeStyle = "rgba(255, 255, 255, 0.25)";
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.roundRect(W - pad - 120, pad, 120, 44, 8);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = "#ffffff";
    ctx.font = `600 20px ui-monospace, monospace`;
    ctx.fillText("9:16 Story", W - pad - 105, pad + 28);

    // [B] BAGIAN BAWAH: PANEL DARK MODE GLASSMORPHISM DENGAN BACKGROUND HERO CONNECTIVITY
    if (heroBgImg) {
      const imgRatio = heroBgImg.width / heroBgImg.height;
      const panelRatio = W / panelH;
      let sWidth = heroBgImg.width;
      let sHeight = heroBgImg.height;
      let sX = 0;
      let sY = 0;
      if (imgRatio > panelRatio) {
        sWidth = heroBgImg.height * panelRatio;
        sX = (heroBgImg.width - sWidth) / 2;
      } else {
        sHeight = heroBgImg.width / panelRatio;
        sY = (heroBgImg.height - sHeight) / 2;
      }
      ctx.drawImage(heroBgImg, sX, sY, sWidth, sHeight, 0, panelY, W, panelH);
    }

    const panelGrad = ctx.createLinearGradient(0, panelY, 0, H);
    panelGrad.addColorStop(0, "rgba(6, 12, 29, 0.88)");
    panelGrad.addColorStop(0.5, "rgba(7, 17, 38, 0.84)");
    panelGrad.addColorStop(1, "rgba(4, 8, 20, 0.94)");
    ctx.fillStyle = panelGrad;
    ctx.fillRect(0, panelY, W, panelH);

    // Garis horizontal pembagi
    ctx.strokeStyle = "rgba(59, 130, 246, 0.35)";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, panelY);
    ctx.lineTo(W, panelY);
    ctx.stroke();

    // Header Panel: Logo Bojonegoro & Instansi (First Capital / Title Case)
    const logoH = Math.round(W * 0.12);
    let headerTextX = pad;
    if (logoImg) {
      const logoW = Math.round(logoH * (logoImg.width / logoImg.height));
      ctx.drawImage(logoImg, pad, panelY + pad, logoW, logoH);
      headerTextX = pad + logoW + Math.round(W * 0.035);
    }

    ctx.fillStyle = "#ffffff";
    ctx.font = `bold ${Math.round(W * 0.04)}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    ctx.fillText("Pemerintah Kabupaten Bojonegoro", headerTextX, panelY + pad + Math.round(logoH * 0.44));

    ctx.fillStyle = "#93c5fd";
    ctx.font = `500 ${Math.round(W * 0.029)}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    ctx.fillText("Badan Perencanaan Pembangunan Daerah", headerTextX, panelY + pad + Math.round(logoH * 0.88));

    // Judul & Deskripsi Story (Plus Jakarta Sans)
    const title = customTitle.value || (props.locationLabel ? `Wilayah ${props.locationLabel}` : "Peta Spasial Bojonegoro");
    const titleFontSize = Math.round(W * 0.064);
    ctx.font = `bold ${titleFontSize}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    const titleLines = getWrappedLines(ctx, title, W - pad * 2);

    let currentY = panelY + pad + logoH + Math.round(W * 0.08);
    ctx.fillStyle = "#ffffff";
    for (const line of titleLines) {
      ctx.fillText(line, pad, currentY);
      currentY += Math.round(titleFontSize * 1.25);
    }

    const desc = customDescription.value || "Informasi geospasial resmi Kabupaten Bojonegoro";
    ctx.fillStyle = "#cbd5e1";
    ctx.font = `400 ${Math.round(W * 0.035)}px "Plus Jakarta Sans", "Outfit", ui-sans-serif, system-ui, sans-serif`;
    const descLines = getWrappedLines(ctx, desc, W - pad * 2);
    for (const line of descLines) {
      ctx.fillText(line, pad, currentY);
      currentY += Math.round(W * 0.05);
    }

    // Footer Story (Hanya domain melarosa, teks Bappeda dihilangkan)
    const footerLineY = H - pad - Math.round(W * 0.06);
    ctx.strokeStyle = "rgba(255, 255, 255, 0.16)";
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad, footerLineY);
    ctx.lineTo(W - pad, footerLineY);
    ctx.stroke();

    ctx.fillStyle = "#60a5fa";
    ctx.font = `bold ${Math.round(W * 0.03)}px ui-monospace, monospace`;
    ctx.textAlign = "right";
    ctx.fillText("melarosa.bojonegorokab.go.id", W - pad, H - pad);
    ctx.textAlign = "left";
  }

  return canvas;
}

// ─── Actions: WhatsApp, Native Share, Download, Copy ─────────────────────────
const isCopiedLink = ref(false);
const isCopiedImage = ref(false);
const isDownloading = ref(false);

let copiedLinkTimer: any = null;
let copiedImageTimer: any = null;

async function handleCopyLink() {
  try {
    await navigator.clipboard.writeText(currentMapUrl.value);
    isCopiedLink.value = true;
    if (copiedLinkTimer) clearTimeout(copiedLinkTimer);
    copiedLinkTimer = setTimeout(() => {
      isCopiedLink.value = false;
    }, 2000);
  } catch (err) {
    console.error("Gagal menyalin tautan:", err);
  }
}

function handleWhatsAppShare() {
  const text = encodeURIComponent(captionText.value);
  const target = `https://api.whatsapp.com/send?text=${text}`;
  window.open(target, "_blank", "noopener,noreferrer");
}

const canNativeShare = computed(() => {
  return typeof navigator !== "undefined" && typeof navigator.share === "function";
});

async function handleNativeShare() {
  if (navigator.share) {
    try {
      await navigator.share({
        title: props.locationLabel ? `Peta ${props.locationLabel}` : "Peta Spasial Bojonegoro",
        text: captionText.value,
        url: shareMode.value === "image_link" ? currentMapUrl.value : undefined,
      });
    } catch (err: any) {
      if (err.name !== "AbortError") console.error("Native share ditolak:", err);
    }
  } else {
    handleCopyLink();
  }
}

async function handleDownloadImage() {
  isDownloading.value = true;
  try {
    const canvas = await drawCardToCanvas();
    canvas.toBlob((blob) => {
      if (!blob) {
        isDownloading.value = false;
        return;
      }
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `peta-bojonegoro-${selectedRatio.value.id}.png`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      isDownloading.value = false;
    }, "image/png");
  } catch (err) {
    console.error("Gagal mengunduh gambar:", err);
    isDownloading.value = false;
  }
}

async function handleCopyImage() {
  if (!navigator.clipboard || !window.ClipboardItem) {
    handleDownloadImage();
    return;
  }

  try {
    const canvas = await drawCardToCanvas();
    canvas.toBlob(async (blob) => {
      if (!blob) return;
      try {
        await navigator.clipboard.write([
          new ClipboardItem({ "image/png": blob }),
        ]);
        isCopiedImage.value = true;
        if (copiedImageTimer) clearTimeout(copiedImageTimer);
        copiedImageTimer = setTimeout(() => {
          isCopiedImage.value = false;
        }, 2000);
      } catch (err) {
        console.warn("ClipboardItem write gagal, mengunduh file:", err);
        handleDownloadImage();
      }
    }, "image/png");
  } catch (err) {
    console.error("Gagal menyalin gambar:", err);
  }
}

// ─── Keyboard Accessibility & Window Events ──────────────────────────────────
function onKeydown(e: KeyboardEvent) {
  if (!props.open) return;

  if (e.key === "Escape") {
    e.preventDefault();
    if (snapLevel.value === "full") snapTo("half");
    else close();
  } else if (e.key === "ArrowUp") {
    if (snapLevel.value === "half") {
      e.preventDefault();
      snapTo("full");
    }
  } else if (e.key === "ArrowDown") {
    if (snapLevel.value === "full") {
      e.preventDefault();
      snapTo("half");
    } else {
      e.preventDefault();
      close();
    }
  }
}

function onResize() {
  viewportH.value = window.innerHeight;
}

onMounted(() => {
  document.addEventListener("keydown", onKeydown);
  window.addEventListener("resize", onResize, { passive: true });
});

onUnmounted(() => {
  document.removeEventListener("keydown", onKeydown);
  window.removeEventListener("resize", onResize);
  if (typeof window !== "undefined") {
    window.removeEventListener("touchmove", onWindowTouchMove);
    window.removeEventListener("touchend", onWindowTouchEnd);
    window.removeEventListener("touchcancel", onWindowTouchEnd);
    window.removeEventListener("mousemove", onWindowMouseMove);
    window.removeEventListener("mouseup", onWindowMouseUp);
  }
});
</script>

<template>
  <Teleport to="body">
    <!-- ═══ BOTTOM SHEET WRAPPER (Fluid TranslateY, Default Half Height, No Backdrop) ═════ -->
    <Transition name="sheet-slide" @after-leave="emit('after-close')">
      <div
        v-if="open"
        role="dialog"
        aria-label="Bagikan Peta"
        :aria-modal="true"
        class="fixed inset-x-0 z-[46] will-change-transform md:hidden select-none pointer-events-auto"
        :style="sheetStyle"
      >
      <!-- Sheet Container -->
      <div
        class="flex flex-col h-full bg-white dark:bg-[#0b0f19] rounded-t-[24px] border-t border-l border-r border-slate-200/90 dark:border-white/10 shadow-[0_-12px_44px_rgba(15,23,42,0.22)] dark:shadow-[0_-14px_50px_rgba(0,0,0,0.6)] overflow-hidden"
      >
        <!-- ── Top Drag Header Area ── -->
        <div
          class="shrink-0 touch-none cursor-grab active:cursor-grabbing border-b border-slate-100 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02]"
          @touchstart.passive="handleHeaderTouchStart"
          @mousedown="handleHeaderMouseDown"
        >
          <!-- Grab Handle Pill -->
          <div
            class="flex items-center justify-center pt-2.5 pb-1.5"
            @click.stop="toggleSnap"
          >
            <div
              class="h-[4.5px] rounded-full transition-all duration-200"
              :class="[
                isDragging
                  ? 'w-12 bg-blue-600 dark:bg-blue-400 shadow-sm'
                  : 'w-10 bg-slate-300 dark:bg-slate-600 hover:bg-slate-400'
              ]"
            />
          </div>

          <!-- Header Bar -->
          <header class="flex items-center gap-2.5 px-3.5 pb-2.5 pt-0.5">
            <!-- Icon -->
            <div
              class="size-8.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 shadow-xs"
            >
              <UIcon name="i-lucide-share-2" class="size-4.5 stroke-[2.25]" />
            </div>

            <!-- Title & Subtitle -->
            <div class="flex-1 min-w-0 select-none py-0.5" @click="toggleSnap">
              <div class="flex items-center gap-1.5">
                <h2 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 truncate leading-none">
                  Bagikan Peta
                </h2>
                <span
                  class="px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300 shrink-0"
                >
                  {{ selectedRatio.label }}
                </span>
              </div>
              <p class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 truncate font-medium mt-1">
                Pilih format & bagikan ke media sosial
              </p>
            </div>

            <!-- Actions Controls (Min 44x44px target) -->
            <div class="shrink-0 flex items-center gap-0.5" @click.stop>
              <!-- Maximize / Restore Button -->
              <button
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 active:scale-95 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-blue-500 focus:outline-hidden"
                :aria-label="snapLevel === 'half' ? 'Perbesar layar penuh' : 'Perkecil ke setengah layar'"
                :title="snapLevel === 'half' ? 'Layar penuh' : 'Setengah layar'"
                @click="toggleSnap"
              >
                <UIcon
                  :name="snapLevel === 'half' ? 'i-lucide-maximize-2' : 'i-lucide-minimize-2'"
                  class="size-4"
                />
              </button>

              <!-- Close Button -->
              <button
                type="button"
                class="min-w-[44px] min-h-[44px] rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 active:scale-95 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-red-500 focus:outline-hidden"
                aria-label="Tutup panel bagikan"
                title="Tutup"
                @click="close"
              >
                <UIcon name="i-lucide-x" class="size-4.5" />
              </button>
            </div>
          </header>
        </div>

        <!-- ── Content Body (Scrollable, Full Surface Swipe Handler) ── -->
        <div
          ref="scrollContainerRef"
          class="flex-1 overscroll-contain flex flex-col p-3.5 space-y-3.5"
          :class="snapLevel === 'half' ? 'overflow-hidden' : 'overflow-y-auto min-h-0'"
          @touchstart.passive="handleContentTouchStart"
          @touchmove="handleContentTouchMove"
        >
          <!-- ═══ SECTION 1: ASPECT RATIO HORIZONTAL SELECTOR ═══ -->
          <div class="space-y-1.5 shrink-0">
            <div class="flex items-center justify-between px-0.5">
              <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                <UIcon name="i-lucide-aspect-ratio" class="size-3.5 text-blue-600 dark:text-blue-400" />
                Format Media Sosial
              </span>
              <span class="text-[10px] text-slate-400 font-medium tabular-nums">
                {{ selectedRatio.width }}x{{ selectedRatio.height }}px (HD)
              </span>
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar select-none">
              <button
                v-for="r in RATIO_OPTIONS"
                :key="r.id"
                type="button"
                class="flex items-center gap-1.5 py-1.5 px-2.5 rounded-xl text-xs font-semibold shrink-0 transition-all cursor-pointer min-h-[44px] active:scale-95 border"
                :class="selectedRatioId === r.id
                  ? 'bg-blue-600 text-white border-blue-600 shadow-xs font-bold'
                  : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border-slate-200/80 dark:border-white/5'"
                @click="selectedRatioId = r.id"
              >
                <UIcon :name="r.icon" class="size-3.5 shrink-0" />
                <span>{{ r.label }}</span>
                <span class="text-[9.5px] opacity-80 font-normal">({{ r.sublabel }})</span>
              </button>
            </div>
          </div>

          <!-- ═══ SECTION 2: ELEGAN SOCIAL PREVIEW CARD STAGE ═══ -->
          <div class="relative rounded-2xl bg-slate-900/50 dark:bg-black/60 border border-slate-200/80 dark:border-white/10 p-2.5 sm:p-3 flex flex-col items-center justify-center overflow-hidden shrink-0 transition-all duration-300">
            <!-- Panggung Kontrol Atas (Muat Ulang & Salin Gambar) -->
            <div class="w-full flex items-center justify-between pb-2 px-1 text-[10px] text-slate-400 border-b border-white/5 mb-2">
              <div class="flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse" />
                <span class="font-medium text-slate-300">{{ selectedRatio.sublabel }}</span>
                <span class="opacity-60 font-medium tabular-nums">({{ selectedRatio.ratio }})</span>
              </div>
              <div class="flex items-center gap-1.5">
                <!-- Tombol Segarkan Snapshot Peta -->
                <button
                  type="button"
                  :disabled="isLoadingSnapshot"
                  class="flex items-center gap-1 px-2 py-1 rounded-lg bg-white/10 hover:bg-white/15 text-slate-200 active:scale-95 transition-all cursor-pointer min-h-[32px] disabled:opacity-50"
                  title="Perbarui cuplikan peta"
                  @click="captureMap"
                >
                  <UIcon
                    name="i-lucide-refresh-cw"
                    class="size-3"
                    :class="isLoadingSnapshot ? 'animate-spin text-blue-400' : ''"
                  />
                  <span class="text-[9.5px]">Muat Ulang</span>
                </button>

                <!-- Tombol Cepat Salin Gambar -->
                <button
                  type="button"
                  class="flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-600/80 hover:bg-blue-600 text-white active:scale-95 transition-all cursor-pointer min-h-[32px]"
                  title="Salin gambar ke papan klip"
                  @click="handleCopyImage"
                >
                  <UIcon :name="isCopiedImage ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3" />
                  <span class="text-[9.5px] font-semibold">{{ isCopiedImage ? 'Tersalin' : 'Salin Kartu' }}</span>
                </button>
              </div>
            </div>

            <!-- Kartu Pratinjau Peta Sesuai Rasio (Split-Layout Dark Mode Glassmorphism) -->
            <div
              class="relative rounded-2xl overflow-hidden shadow-2xl border border-blue-500/25 bg-[#070b14] text-white flex transition-all duration-300 mx-auto select-none my-auto ring-1 ring-blue-500/30"
              :class="selectedRatioId === 'story' ? 'flex-col' : 'flex-row'"
              :style="previewCardStyle"
            >
              <!-- ═══ SISI KIRI (ATAU BAWAH PADA STORY): PANEL INFORMASI GLASSMORPHISM ═══ -->
              <div
                class="flex flex-col justify-between p-2 sm:p-2.5 bg-[#060c1d]/80 backdrop-blur-md relative z-10 select-none shadow-[4px_0_15px_rgba(0,0,0,0.5)] overflow-hidden"
                :class="selectedRatioId === 'story' ? 'w-full h-[52%] border-t border-blue-500/25 order-2' : 'w-[54%] h-full border-r border-blue-500/25 order-1'"
              >
                <!-- Background Citra Peta Konektivitas Bojonegoro & Ambient Dark Overlay -->
                <div class="absolute inset-0 pointer-events-none overflow-hidden -z-10">
                  <img
                    src="/images/hero-bojonegoro-connectivity.jpg"
                    alt="Latar Belakang Konektivitas Bojonegoro"
                    class="w-full h-full object-cover object-center scale-105 filter brightness-[0.55] contrast-[1.12]"
                  />
                  <!-- Gradient dark glass overlay agar teks berkontras tinggi dan terbaca tajam -->
                  <div class="absolute inset-0 bg-gradient-to-br from-[#060c1d]/90 via-[#071126]/85 to-[#040814]/94" />
                  <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.22),transparent_70%)]" />
                </div>

                <!-- 1. Header Kiri Atas: Logo Kabupaten Bojonegoro & Teks Instansi (First Capital) -->
                <div class="flex items-center gap-1.5 shrink-0">
                  <img
                    src="/logo-kabupaten.png"
                    alt="Logo Kabupaten Bojonegoro"
                    class="h-5 sm:h-6 w-auto object-contain shrink-0 drop-shadow-sm"
                  />
                  <div class="min-w-0 flex-1">
                    <h5 class="font-['Plus_Jakarta_Sans',sans-serif] text-[7.5px] sm:text-[8.5px] font-bold text-white tracking-tight leading-none truncate">
                      Pemerintah Kabupaten Bojonegoro
                    </h5>
                    <p class="font-['Plus_Jakarta_Sans',sans-serif] text-[6px] sm:text-[7px] font-medium text-blue-300 tracking-normal leading-none mt-0.5 truncate">
                      Badan Perencanaan Pembangunan Daerah
                    </p>
                  </div>
                </div>

                <!-- 2. Bagian Tengah Kiri: Judul Utama & Deskripsi (Plus Jakarta Sans - Fluid & Modern) -->
                <div class="space-y-1 my-auto py-1">
                  <h3
                    class="font-['Plus_Jakarta_Sans',sans-serif] font-bold text-white leading-snug tracking-tight drop-shadow-[0_2px_8px_rgba(0,0,0,0.65)] line-clamp-2"
                    :class="selectedRatioId === 'story' ? 'text-[11.5px] sm:text-xs' : 'text-[10px] sm:text-[11.5px]'"
                  >
                    {{ customTitle || (locationLabel ? `Wilayah ${locationLabel}` : 'Peta Spasial Bojonegoro') }}
                  </h3>
                  <p
                    class="font-['Plus_Jakarta_Sans',sans-serif] text-slate-300 font-normal leading-relaxed line-clamp-2 mt-0.5"
                    :class="selectedRatioId === 'story' ? 'text-[7.5px] sm:text-[8px]' : 'text-[7px] sm:text-[7.5px]'"
                  >
                    {{ customDescription || 'Informasi geospasial resmi Kabupaten Bojonegoro' }}
                  </p>
                </div>

                <!-- 3. Bagian Bawah: Garis Tipis & Footer Resmi (Teks Bappeda Dihilangkan) -->
                <div class="pt-1 border-t border-white/10 flex items-center justify-end text-[6px] sm:text-[7px] shrink-0">
                  <span class="text-blue-400 font-semibold tracking-tight truncate">
                    melarosa.bojonegorokab.go.id
                  </span>
                </div>
              </div>

              <!-- ═══ SISI KANAN (ATAU ATAS PADA STORY): AREA PETA INTERAKTIF ═══ -->
              <div
                class="relative overflow-hidden bg-[#070b14] flex items-center justify-center z-0"
                :class="selectedRatioId === 'story' ? 'w-full h-[48%] order-1' : 'w-[46%] h-full order-2'"
              >
                <!-- Gambar Snapshot Peta / Fallback -->
                <img
                  v-if="snapshotDataUrl"
                  :src="snapshotDataUrl"
                  alt="Cuplikan Peta"
                  class="w-full h-full object-cover select-none pointer-events-none"
                />
                <div v-else class="w-full h-full bg-[#0b1220] flex items-center justify-center relative overflow-hidden">
                  <div class="absolute inset-0 bg-[radial-gradient(#1e3a8a_1.5px,transparent_1.5px)] [background-size:12px_12px] opacity-40" />
                  <div class="size-9 rounded-full border border-blue-500/30 flex items-center justify-center animate-pulse">
                    <UIcon name="i-lucide-crosshair" class="size-4.5 text-blue-400/80" />
                  </div>
                </div>

                <!-- Retikel Bidik Kartografis Menyala di Tengah Peta -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                  <div class="size-6 border border-blue-400/60 shadow-[0_0_8px_rgba(59,130,246,0.6)] rounded-full flex items-center justify-center">
                    <div class="size-1.5 bg-blue-400 rounded-full animate-ping" />
                  </div>
                </div>

                <!-- Vignette Halus Map Viewport -->
                <div class="absolute inset-0 bg-gradient-to-t from-[#070b14]/60 via-transparent to-[#070b14]/20 pointer-events-none" />

                <!-- Format Ratio Pill di Kanan Atas Peta -->
                <div class="absolute top-1.5 right-1.5 z-10">
                  <span class="text-[6.5px] font-bold text-white bg-black/70 backdrop-blur-xs border border-white/20 px-1.5 py-0.5 rounded shadow-sm">
                    {{ selectedRatio.label }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- ═══ SECTION 3: QUICK SHARE ACTIONS (Primary Direct Tap Targets) ═══ -->
          <div class="grid grid-cols-4 gap-2 shrink-0">
            <!-- 1. WhatsApp Button -->
            <button
              type="button"
              class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/40 font-semibold text-[11px] min-h-[52px] transition-all active:scale-95 hover:bg-emerald-100 cursor-pointer shadow-xs"
              @click="handleWhatsAppShare"
            >
              <UIcon name="i-simple-icons-whatsapp" class="size-5 text-emerald-600 dark:text-emerald-400" />
              <span>WhatsApp</span>
            </button>

            <!-- 2. Native Share / Bagikan Sistem -->
            <button
              type="button"
              class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200/80 dark:border-blue-800/40 font-semibold text-[11px] min-h-[52px] transition-all active:scale-95 hover:bg-blue-100 cursor-pointer shadow-xs"
              @click="handleNativeShare"
            >
              <UIcon name="i-lucide-share-2" class="size-5 text-blue-600 dark:text-blue-400" />
              <span>Bagikan</span>
            </button>

            <!-- 3. Salin Tautan (Copy Link) -->
            <button
              type="button"
              class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-white/10 font-semibold text-[11px] min-h-[52px] transition-all active:scale-95 hover:bg-slate-200/70 cursor-pointer shadow-xs"
              @click="handleCopyLink"
            >
              <UIcon
                :name="isCopiedLink ? 'i-lucide-check' : 'i-lucide-link'"
                class="size-5"
                :class="isCopiedLink ? 'text-emerald-500' : 'text-slate-600 dark:text-slate-400'"
              />
              <span :class="isCopiedLink ? 'text-emerald-600 font-bold' : ''">
                {{ isCopiedLink ? 'Tersalin' : 'Salin URL' }}
              </span>
            </button>

            <!-- 4. Unduh Gambar PNG -->
            <button
              type="button"
              :disabled="isDownloading"
              class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-white/10 font-semibold text-[11px] min-h-[52px] transition-all active:scale-95 hover:bg-slate-200/70 cursor-pointer shadow-xs disabled:opacity-50"
              @click="handleDownloadImage"
            >
              <UIcon
                :name="isDownloading ? 'i-lucide-loader-2' : 'i-lucide-download'"
                class="size-5"
                :class="isDownloading ? 'animate-spin text-blue-500' : 'text-slate-600 dark:text-slate-400'"
              />
              <span>{{ isDownloading ? 'Mengunduh' : 'Unduh PNG' }}</span>
            </button>
          </div>

          <!-- ═══ SECTION 4: FORMAT BERBAGI (GAMBAR + TAUTAN / GAMBAR SAJA) ═══ -->
          <div class="space-y-1.5 shrink-0 pt-1">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">
              Format Tautan Peta
            </span>
            <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 rounded-xl">
              <!-- Tab 1: Image + Link -->
              <button
                type="button"
                class="min-h-[44px] py-1.5 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center justify-center gap-1.5"
                :class="shareMode === 'image_link'
                  ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs font-bold'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                @click="shareMode = 'image_link'"
              >
                <UIcon name="i-lucide-link" class="size-3.5" />
                <span>Gambar + Tautan</span>
              </button>

              <!-- Tab 2: Image Only -->
              <button
                type="button"
                class="min-h-[44px] py-1.5 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center justify-center gap-1.5"
                :class="shareMode === 'image_only'
                  ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs font-bold'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                @click="shareMode = 'image_only'"
              >
                <UIcon name="i-lucide-image" class="size-3.5" />
                <span>Gambar Saja</span>
              </button>
            </div>
          </div>

          <!-- ═══ SECTION 5: CUSTOMIZATION FIELDS (Accessible & Scrollable) ═══ -->
          <div class="space-y-3 pt-1">
            <div class="flex items-center justify-between">
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                Kustomisasi Postingan
              </label>
              <!-- Salin Caption Cepat -->
              <button
                type="button"
                class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 flex items-center gap-1 active:scale-95 cursor-pointer min-h-[44px] px-1"
                @click="handleCopyCaption"
              >
                <UIcon :name="isCopiedCaption ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
                <span>{{ isCopiedCaption ? 'Caption Tersalin' : 'Salin Teks Caption' }}</span>
              </button>
            </div>

            <div class="space-y-2">
              <div class="space-y-1">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Judul Kartu</span>
                <input
                  v-model="customTitle"
                  type="text"
                  placeholder="Judul peta (opsional)"
                  class="w-full text-xs py-2 px-3 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-800 dark:text-slate-100 focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div class="space-y-1">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Keterangan Tambahan</span>
                <input
                  v-model="customDescription"
                  type="text"
                  placeholder="Keterangan singkat (opsional)"
                  class="w-full text-xs py-2 px-3 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-800 dark:text-slate-100 focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div class="space-y-1">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Preview Teks Caption Media Sosial</span>
                <textarea
                  v-model="captionText"
                  rows="3"
                  class="w-full text-xs py-2 px-3 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-800 dark:text-slate-100 focus:outline-hidden focus:ring-2 focus:ring-blue-500 resize-none leading-relaxed"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Safe area spacer for mobile devices -->
        <div
          class="shrink-0"
          style="height: env(safe-area-inset-bottom, 0px)"
        />
      </div>
    </div>
  </Transition>
  </Teleport>
</template>

<style scoped>

/* Bottom Sheet Slide Up & Down Transition */
.sheet-slide-enter-active {
  transition: transform 0.62s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.sheet-slide-enter-from {
  transform: translate3d(0, calc(100% + 100px), 0) !important;
}

.sheet-slide-leave-active {
  transition: transform 0.52s cubic-bezier(0.25, 1, 0.5, 1) !important;
}
.sheet-slide-leave-to {
  transform: translate3d(0, calc(100% + 100px), 0) !important;
}

/* Hide scrollbar for ratio selector */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

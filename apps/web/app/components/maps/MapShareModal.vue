<script setup lang="ts">
import type { BasemapKey, ActiveLayerItem } from "~/types/map-layers";

export interface AspectRatioOption {
  id: string;
  label: string;
  sublabel: string;
  ratio: string; // CSS aspect-ratio value e.g. "1 / 1"
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
    getMapThumbnail?: (options?: any) => Promise<string | null>;
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
    getMapThumbnail: undefined,
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit("update:open", val),
});

// ─── 1. Pilihan Rasio Media Sosial ───────────────────────────────────────────
const RATIO_OPTIONS: AspectRatioOption[] = [
  {
    id: "landscape",
    label: "16:9",
    sublabel: "Lansekap",
    ratio: "16 / 9",
    aspectWidth: 16,
    aspectHeight: 9,
    width: 1280,
    height: 720,
    icon: "i-lucide-monitor",
  },
  {
    id: "square",
    label: "1:1",
    sublabel: "Persegi",
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
    sublabel: "Potret",
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
    sublabel: "Story",
    ratio: "9 / 16",
    aspectWidth: 9,
    aspectHeight: 16,
    width: 1080,
    height: 1920,
    icon: "i-lucide-smartphone",
  },
  {
    id: "banner",
    label: "1.91:1",
    sublabel: "Banner",
    ratio: "1.91 / 1",
    aspectWidth: 1.91,
    aspectHeight: 1,
    width: 1200,
    height: 630,
    icon: "i-lucide-globe",
  },
];

const selectedRatioId = ref("landscape");
const selectedRatio = computed(() => {
  return RATIO_OPTIONS.find((r) => r.id === selectedRatioId.value) || RATIO_OPTIONS[0];
});

// ─── Penghitungan Dimensi Pratinjau Sesuai Rasio Konfigurasi ─────────────────
const previewCardStyle = computed(() => {
  const { aspectWidth, aspectHeight, ratio } = selectedRatio.value;
  const maxStageH = 240;
  const maxStageW = 420;
  const targetRatio = aspectWidth / aspectHeight;

  // Rasio potret vertikal (4:5 dan 9:16)
  if (targetRatio < 1) {
    const computedH = maxStageH;
    const computedW = Math.round(computedH * targetRatio);
    return {
      aspectRatio: ratio,
      height: `${computedH}px`,
      width: `${computedW}px`,
      maxWidth: "100%",
    };
  }

  // Rasio persegi (1:1)
  if (aspectWidth === aspectHeight) {
    const size = 220;
    return {
      aspectRatio: "1 / 1",
      height: `${size}px`,
      width: `${size}px`,
      maxWidth: "100%",
    };
  }

  // Rasio lansekap horisontal (16:9 dan 1.91:1)
  const computedW = maxStageW;
  const computedH = Math.round(computedW / targetRatio);
  return {
    aspectRatio: ratio,
    width: `min(100%, ${computedW}px)`,
    height: "auto",
    maxHeight: `${computedH}px`,
  };
});

// ─── 2. Tab Menu: Image Only | Image + Link ──────────────────────────────────
type ShareMode = "image_only" | "image_link";
const shareMode = ref<ShareMode>("image_link");

// ─── 3. State & Caption Postingan ────────────────────────────────────────────
const customTitle = ref("");
const customDescription = ref("");
const captionText = ref("");

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

// Perbarui caption saat tab menu beralih
watch(shareMode, (newMode) => {
  captionText.value = generateDefaultCaption(newMode);
});

// ─── Snapshot Peta ───────────────────────────────────────────────────────────
const snapshotDataUrl = ref<string | null>(null);
const isLoadingSnapshot = ref(false);

async function captureMap() {
  if (!props.getMapThumbnail && !props.getMapSnapshot) return;
  isLoadingSnapshot.value = true;
  try {
    const data = props.getMapThumbnail
      ? await props.getMapThumbnail({ width: 1280, height: 720, crop16_9: true })
      : await props.getMapSnapshot!();
    if (data) {
      snapshotDataUrl.value = data;
    }
  } catch (err) {
    console.warn("Gagal mengambil snapshot peta:", err);
  } finally {
    isLoadingSnapshot.value = false;
  }
}

// Inisialisasi saat dialog terbuka
watch(
  () => props.open,
  (opened) => {
    if (opened) {
      resetAllTexts();
      captureMap();
    }
  }
);

// ─── Action Feedback States ──────────────────────────────────────────────────
const isCopiedImage = ref(false);
const isDownloading = ref(false);
const isCopiedText = ref(false);
const isCopiedLink = ref(false);

let copiedTextTimer: any = null;
let copiedImageTimer: any = null;
let copiedLinkTimer: any = null;

// ─── Helper Pemecah Teks Multiline Canvas ────────────────────────────────────
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

// ─── Native Canvas Rendering untuk Ekspor Kartu ──────────────────────────────
async function drawCardToCanvas(): Promise<HTMLCanvasElement> {
  const target = selectedRatio.value;
  const canvas = document.createElement("canvas");
  canvas.width = target.width;
  canvas.height = target.height;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Canvas 2D context not available");

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

// ─── 6. Baris Aksi Bawah ─────────────────────────────────────────────────────

// A. Copy Image (Salin Gambar ke Clipboard)
async function handleCopyImage() {
  if (!navigator.clipboard || !window.ClipboardItem) {
    handleDownload();
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
        handleDownload();
      }
    }, "image/png");
  } catch (err) {
    console.error("Gagal menyalin gambar:", err);
  }
}

// B. Download (Unduh Gambar Berkas PNG)
async function handleDownload() {
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

// C. Copy Text (Salin Teks Keterangan / Caption)
async function handleCopyText() {
  try {
    await navigator.clipboard.writeText(captionText.value);
    isCopiedText.value = true;
    if (copiedTextTimer) clearTimeout(copiedTextTimer);
    copiedTextTimer = setTimeout(() => {
      isCopiedText.value = false;
    }, 2000);
  } catch (err) {
    console.error("Gagal menyalin caption:", err);
  }
}

// D. Copy Map URL Link (Salin Tautan Peta untuk Berbagi)
async function handleCopyLink() {
  try {
    await navigator.clipboard.writeText(currentMapUrl.value);
    isCopiedLink.value = true;
    if (copiedLinkTimer) clearTimeout(copiedLinkTimer);
    copiedLinkTimer = setTimeout(() => {
      isCopiedLink.value = false;
    }, 2000);
  } catch (err) {
    console.error("Gagal menyalin tautan peta:", err);
  }
}

// ─── 5. Share Button Sosial Media ────────────────────────────────────────────
function openShare(platform: "whatsapp" | "x" | "facebook" | "telegram" | "linkedin") {
  const url = encodeURIComponent(currentMapUrl.value);
  const text = encodeURIComponent(captionText.value);

  let target = "";
  switch (platform) {
    case "whatsapp":
      target = `https://api.whatsapp.com/send?text=${text}`;
      break;
    case "x":
      target = `https://twitter.com/intent/tweet?text=${text}`;
      break;
    case "facebook":
      target = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
      break;
    case "telegram":
      target = `https://t.me/share/url?url=${url}&text=${text}`;
      break;
    case "linkedin":
      target = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
      break;
  }

  if (target) {
    window.open(target, "_blank", "noopener,noreferrer");
  }
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
    handleCopyText();
  }
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    title="Bagikan Peta"
    description="Sesuaikan format rasio postingan dan bagikan ke media sosial."
    :ui="{
      content: 'sm:max-w-xl w-[calc(100vw-2rem)] max-h-[92vh] bg-white dark:bg-[#09111e] border border-slate-200/90 dark:border-white/10 rounded-2xl md:rounded-3xl shadow-2xl flex flex-col overflow-hidden',
      header: 'px-5 py-3.5 border-b border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02]',
      body: 'p-4 sm:p-5 flex-1 overflow-y-auto space-y-4',
      footer: 'px-5 py-3 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02]',
    }"
  >
    <template #body>
      <div class="space-y-4">
        <!-- ═══ 1. PRATINJAU PETA DENGAN TOGGLE RASIO INTEGRASI ═══ -->
        <div class="relative w-full flex flex-col items-center justify-center p-3 pt-12 pb-3 rounded-2xl bg-slate-100/70 dark:bg-black/40 border border-slate-200/60 dark:border-white/5 overflow-hidden min-h-[300px]">
          <!-- Toggle Rasio Minimalis di Atas Kartu Pratinjau -->
          <div class="absolute top-2.5 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1 p-1 rounded-xl bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border border-slate-200/90 dark:border-white/10 shadow-xs max-w-[calc(100%-1.5rem)] overflow-x-auto select-none">
            <button
              v-for="r in RATIO_OPTIONS"
              :key="r.id"
              type="button"
              class="h-7.5 px-2.5 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
              :class="selectedRatioId === r.id
                ? 'bg-blue-600 text-white shadow-xs font-bold'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5'"
              :aria-label="`Pilih rasio ${r.label}`"
              @click="selectedRatioId = r.id"
            >
              <UIcon :name="r.icon" class="size-3.5 shrink-0" />
              <span>{{ r.label }}</span>
            </button>
          </div>

          <!-- Kartu Pratinjau Peta Sesuai Rasio (Split-Layout Dark Mode Glassmorphism) -->
          <div
            class="relative rounded-2xl overflow-hidden shadow-2xl border border-blue-500/25 bg-[#070b14] text-white flex transition-all duration-300 mx-auto select-none my-auto ring-1 ring-blue-500/30"
            :class="selectedRatioId === 'story' ? 'flex-col' : 'flex-row'"
            :style="previewCardStyle"
          >
            <!-- ═══ SISI KIRI (ATAU BAWAH PADA STORY): PANEL INFORMASI GLASSMORPHISM ═══ -->
            <div
              class="flex flex-col justify-between p-3 sm:p-4 bg-[#060c1d]/80 backdrop-blur-md relative z-10 select-none shadow-[4px_0_15px_rgba(0,0,0,0.5)] overflow-hidden"
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
              <div class="flex items-center gap-2 shrink-0">
                <img
                  src="/logo-kabupaten.png"
                  alt="Logo Kabupaten Bojonegoro"
                  class="h-6 sm:h-7 w-auto object-contain shrink-0 drop-shadow-sm"
                />
                <div class="min-w-0 flex-1">
                  <h5 class="font-['Plus_Jakarta_Sans',sans-serif] text-[8.5px] sm:text-[9.5px] font-bold text-white tracking-tight leading-none truncate">
                    Pemerintah Kabupaten Bojonegoro
                  </h5>
                  <p class="font-['Plus_Jakarta_Sans',sans-serif] text-[7px] sm:text-[8px] font-medium text-blue-300 tracking-normal leading-none mt-0.5 truncate">
                    Badan Perencanaan Pembangunan Daerah
                  </p>
                </div>
              </div>

              <!-- 2. Bagian Tengah Kiri: Judul Utama & Deskripsi (Plus Jakarta Sans - Fluid & Modern) -->
              <div class="space-y-1.5 my-auto py-2">
                <h3
                  class="font-['Plus_Jakarta_Sans',sans-serif] font-bold text-white leading-snug tracking-tight drop-shadow-[0_2px_8px_rgba(0,0,0,0.65)] line-clamp-2"
                  :class="selectedRatioId === 'story' ? 'text-sm' : 'text-xs sm:text-sm'"
                >
                  {{ customTitle || (locationLabel ? `Wilayah ${locationLabel}` : 'Peta Spasial Bojonegoro') }}
                </h3>
                <p
                  class="font-['Plus_Jakarta_Sans',sans-serif] text-slate-300 font-normal leading-relaxed line-clamp-2 mt-0.5"
                  :class="selectedRatioId === 'story' ? 'text-[8.5px]' : 'text-[8px] sm:text-[9px]'"
                >
                  {{ customDescription || 'Informasi geospasial resmi Kabupaten Bojonegoro' }}
                </p>
              </div>

              <!-- 3. Bagian Bawah: Garis Tipis & Footer Resmi (Teks Bappeda Dihilangkan) -->
              <div class="pt-1.5 border-t border-white/10 flex items-center justify-end text-[7px] sm:text-[8px] shrink-0">
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
                <div class="absolute inset-0 bg-[radial-gradient(#1e3a8a_1.5px,transparent_1.5px)] [background-size:14px_14px] opacity-40" />
                <div class="size-10 rounded-full border border-blue-500/30 flex items-center justify-center animate-pulse">
                  <UIcon name="i-lucide-crosshair" class="size-5 text-blue-400/80" />
                </div>
              </div>

              <!-- Retikel Bidik Kartografis Menyala di Tengah Peta -->
              <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="size-7 border border-blue-400/60 shadow-[0_0_8px_rgba(59,130,246,0.6)] rounded-full flex items-center justify-center">
                  <div class="size-2 bg-blue-400 rounded-full animate-ping" />
                </div>
              </div>

              <!-- Vignette Halus Map Viewport -->
              <div class="absolute inset-0 bg-gradient-to-t from-[#070b14]/60 via-transparent to-[#070b14]/20 pointer-events-none" />

              <!-- Format Ratio Pill di Kanan Atas Peta -->
              <div class="absolute top-2 right-2 z-10">
                <span class="text-[7.5px] font-bold text-white bg-black/70 backdrop-blur-xs border border-white/20 px-2 py-0.5 rounded-md shadow-sm">
                  {{ selectedRatio.label }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══ TAUTAN PETA UNTUK BERBAGI (SHARE LINK DENGAN PRATINJAU 16:9) ═══ -->
        <div class="space-y-1.5 p-3 rounded-2xl bg-blue-50/70 dark:bg-blue-950/25 border border-blue-200/80 dark:border-blue-900/40 shadow-xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-blue-700 dark:text-blue-300 uppercase tracking-wider flex items-center gap-1.5">
              <UIcon name="i-lucide-link-2" class="size-3.5 text-blue-600 dark:text-blue-400" />
              Tautan Peta (Share Link)
            </span>
            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold px-2 py-0.5 rounded-full bg-blue-100/80 dark:bg-blue-900/60">
              Pratinjau Thumbnail 16:9
            </span>
          </div>

          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <input
                :value="currentMapUrl"
                readonly
                aria-label="Tautan Peta yang Dibagikan"
                class="w-full h-11 pl-3 pr-8 rounded-xl bg-white dark:bg-[#070c18] border border-blue-200 dark:border-blue-800/60 text-xs font-mono text-slate-700 dark:text-slate-200 select-all focus:outline-hidden"
                @focus="($event.target as HTMLInputElement).select()"
              />
              <UIcon name="i-lucide-external-link" class="absolute right-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400 pointer-events-none" />
            </div>

            <button
              type="button"
              class="min-h-[44px] px-4 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all active:scale-95 cursor-pointer shrink-0 shadow-xs"
              :class="isCopiedLink
                ? 'bg-emerald-600 text-white shadow-emerald-500/20'
                : 'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-500/20'"
              @click="handleCopyLink"
            >
              <UIcon :name="isCopiedLink ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
              <span>{{ isCopiedLink ? 'Tersalin!' : 'Salin Link' }}</span>
            </button>
          </div>
        </div>

        <!-- ═══ 2. TAB MENU: IMAGE ONLY | IMAGE + LINK ═══ -->
        <div class="space-y-1.5">
          <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">
            Format Berbagi
          </span>
          <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 dark:bg-[#0b0f19] border border-slate-200/80 dark:border-white/10 rounded-xl">
            <!-- Tab 1: Image Only -->
            <button
              type="button"
              class="min-h-[44px] py-2 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center justify-center gap-2"
              :class="shareMode === 'image_only'
                ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="shareMode = 'image_only'"
            >
              <UIcon name="i-lucide-image" class="size-4" />
              <span>Image Only</span>
            </button>

            <!-- Tab 2: Image + Link -->
            <button
              type="button"
              class="min-h-[44px] py-2 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center justify-center gap-2"
              :class="shareMode === 'image_link'
                ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              @click="shareMode = 'image_link'"
            >
              <UIcon name="i-lucide-link" class="size-4" />
              <span>Image + Link</span>
            </button>
          </div>
        </div>

        <!-- ═══ 4. CAPTION & KUSTOMISASI GAMBAR ═══ -->
        <div class="space-y-3">
          <!-- Input Judul & Deskripsi Gambar -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div class="space-y-1">
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">
                Judul Gambar
              </label>
              <input
                v-model="customTitle"
                type="text"
                class="w-full h-10 px-3 rounded-xl bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                placeholder="Judul pada kartu gambar..."
              />
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">
                Deskripsi Gambar
              </label>
              <input
                v-model="customDescription"
                type="text"
                class="w-full h-10 px-3 rounded-xl bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                placeholder="Keterangan singkat pada gambar..."
              />
            </div>
          </div>

          <!-- Teks Keterangan (Caption) -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                Teks Keterangan (Caption)
              </label>
              <button
                type="button"
                class="text-[11px] font-medium text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                @click="resetAllTexts"
              >
                Atur Ulang
              </button>
            </div>
            <textarea
              v-model="captionText"
              rows="3"
              class="w-full p-2.5 rounded-xl bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 leading-relaxed font-sans"
              placeholder="Tulis keterangan postingan..."
            />
          </div>
        </div>

        <!-- ═══ 5. SHARE BUTTON ═══ -->
        <div class="space-y-1.5">
          <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">
            Bagikan ke Media Sosial
          </span>
          <div class="grid grid-cols-5 gap-2">
            <!-- WhatsApp -->
            <button
              type="button"
              class="min-h-[44px] flex flex-col items-center justify-center gap-1 p-2 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:border-emerald-300 dark:hover:border-emerald-800 text-slate-700 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400 transition-all cursor-pointer"
              aria-label="Bagikan ke WhatsApp"
              @click="openShare('whatsapp')"
            >
              <UIcon name="i-simple-icons-whatsapp" class="size-4 text-emerald-600 dark:text-emerald-400" />
              <span class="text-[10px] font-medium leading-none">WhatsApp</span>
            </button>

            <!-- X / Twitter -->
            <button
              type="button"
              class="min-h-[44px] flex flex-col items-center justify-center gap-1 p-2 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] hover:bg-slate-100 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-black dark:hover:text-white transition-all cursor-pointer"
              aria-label="Bagikan ke X"
              @click="openShare('x')"
            >
              <UIcon name="i-simple-icons-x" class="size-3.5 text-slate-900 dark:text-slate-100" />
              <span class="text-[10px] font-medium leading-none">X</span>
            </button>

            <!-- Facebook -->
            <button
              type="button"
              class="min-h-[44px] flex flex-col items-center justify-center gap-1 p-2 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] hover:bg-blue-50 dark:hover:bg-blue-950/30 hover:border-blue-300 dark:hover:border-blue-800 text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-all cursor-pointer"
              aria-label="Bagikan ke Facebook"
              @click="openShare('facebook')"
            >
              <UIcon name="i-simple-icons-facebook" class="size-4 text-blue-600 dark:text-blue-400" />
              <span class="text-[10px] font-medium leading-none">Facebook</span>
            </button>

            <!-- Telegram -->
            <button
              type="button"
              class="min-h-[44px] flex flex-col items-center justify-center gap-1 p-2 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] hover:bg-sky-50 dark:hover:bg-sky-950/30 hover:border-sky-300 dark:hover:border-sky-800 text-slate-700 dark:text-slate-300 hover:text-sky-500 transition-all cursor-pointer"
              aria-label="Bagikan ke Telegram"
              @click="openShare('telegram')"
            >
              <UIcon name="i-simple-icons-telegram" class="size-4 text-sky-500" />
              <span class="text-[10px] font-medium leading-none">Telegram</span>
            </button>

            <!-- Native Share / LinkedIn -->
            <button
              v-if="canNativeShare"
              type="button"
              class="min-h-[44px] flex flex-col items-center justify-center gap-1 p-2 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] hover:bg-blue-50 dark:hover:bg-blue-950/30 hover:border-blue-300 dark:hover:border-blue-800 text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-all cursor-pointer"
              aria-label="Menu bagikan perangkat"
              @click="handleNativeShare"
            >
              <UIcon name="i-lucide-share" class="size-4 text-blue-600 dark:text-blue-400" />
              <span class="text-[10px] font-medium leading-none">Lainnya</span>
            </button>
            <button
              v-else
              type="button"
              class="min-h-[44px] flex flex-col items-center justify-center gap-1 p-2 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] hover:bg-blue-50 dark:hover:bg-blue-950/30 hover:border-blue-400 dark:hover:border-blue-800 text-slate-700 dark:text-slate-300 hover:text-blue-700 transition-all cursor-pointer"
              aria-label="Bagikan ke LinkedIn"
              @click="openShare('linkedin')"
            >
              <UIcon name="i-simple-icons-linkedin" class="size-4 text-blue-700 dark:text-blue-400" />
              <span class="text-[10px] font-medium leading-none">LinkedIn</span>
            </button>
          </div>
        </div>

        <!-- ═══ 6. COPY IMAGE | DOWNLOAD | COPY TEXT ═══ -->
        <div class="pt-2 border-t border-slate-100 dark:border-white/5">
          <div class="grid grid-cols-3 gap-2">
            <!-- 1. Copy Image -->
            <button
              type="button"
              class="min-h-[44px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] active:scale-95 shadow-xs transition-all cursor-pointer"
              @click="handleCopyImage"
            >
              <UIcon
                :name="isCopiedImage ? 'i-lucide-check' : 'i-lucide-image'"
                class="size-4"
                :class="isCopiedImage ? 'text-green-500' : 'text-slate-500 dark:text-slate-400'"
              />
              <span class="truncate">{{ isCopiedImage ? 'Tersalin!' : 'Copy Image' }}</span>
            </button>

            <!-- 2. Download -->
            <button
              type="button"
              :disabled="isDownloading"
              class="min-h-[44px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 active:scale-95 shadow-xs transition-all cursor-pointer disabled:opacity-50"
              @click="handleDownload"
            >
              <UIcon
                :name="isDownloading ? 'i-lucide-loader-2' : 'i-lucide-download'"
                class="size-4"
                :class="isDownloading ? 'animate-spin' : ''"
              />
              <span class="truncate">{{ isDownloading ? 'Mengunduh...' : 'Download' }}</span>
            </button>

            <!-- 3. Copy Text -->
            <button
              type="button"
              class="min-h-[44px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/[0.04] active:scale-95 shadow-xs transition-all cursor-pointer"
              @click="handleCopyText"
            >
              <UIcon
                :name="isCopiedText ? 'i-lucide-check' : 'i-lucide-copy'"
                class="size-4"
                :class="isCopiedText ? 'text-green-500' : 'text-slate-500 dark:text-slate-400'"
              />
              <span class="truncate">{{ isCopiedText ? 'Tersalin!' : 'Copy Text' }}</span>
            </button>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

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
  }>(),
  {
    open: false,
    currentBasemap: "osm",
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
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit("update:open", val),
});

// ─── 1. Pilihan Rasio Media Sosial ───────────────────────────────────────────
const RATIO_OPTIONS: AspectRatioOption[] = [
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
    sublabel: "Banner",
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
    ? `\nLapisan Peta: ${props.activeLayers.map((l) => l.name || l.title).join(", ")}`
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

let copiedTextTimer: any = null;
let copiedImageTimer: any = null;

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

  // Latar belakang utama
  ctx.fillStyle = "#070b14";
  ctx.fillRect(0, 0, W, H);

  // Gambar Snapshot Peta atau Fallback
  if (snapshotDataUrl.value) {
    try {
      const img = new Image();
      img.crossOrigin = "anonymous";
      await new Promise<void>((resolve, reject) => {
        img.onload = () => resolve();
        img.onerror = () => reject();
        img.src = snapshotDataUrl.value!;
      });

      const imgRatio = img.width / img.height;
      const targetRatio = W / H;
      let sWidth = img.width;
      let sHeight = img.height;
      let sX = 0;
      let sY = 0;

      if (imgRatio > targetRatio) {
        sWidth = img.height * targetRatio;
        sX = (img.width - sWidth) / 2;
      } else {
        sHeight = img.width / targetRatio;
        sY = (img.height - sHeight) / 2;
      }

      ctx.drawImage(img, sX, sY, sWidth, sHeight, 0, 0, W, H);

      // Gradient overlay pelindung keterbacaan teks
      const grad = ctx.createLinearGradient(0, 0, 0, H);
      grad.addColorStop(0, "rgba(7, 11, 20, 0.85)");
      grad.addColorStop(0.25, "rgba(7, 11, 20, 0.2)");
      grad.addColorStop(0.68, "rgba(7, 11, 20, 0.45)");
      grad.addColorStop(1, "rgba(7, 11, 20, 0.95)");
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, W, H);
    } catch {
      drawFallbackGrid(ctx, W, H);
    }
  } else {
    drawFallbackGrid(ctx, W, H);
  }

  // Header Kartu
  const pad = Math.round(W * 0.05);
  ctx.fillStyle = "#ffffff";
  ctx.font = `bold ${Math.round(W * 0.026)}px ui-sans-serif, system-ui, sans-serif`;
  ctx.fillText("KABUPATEN BOJONEGORO", pad, pad + Math.round(W * 0.026));

  ctx.fillStyle = "#60a5fa"; // blue-400
  ctx.font = `600 ${Math.round(W * 0.02)}px ui-sans-serif, system-ui, sans-serif`;
  ctx.fillText("MELAROSA GIS • SATU DATA SPASIAL", pad, pad + Math.round(W * 0.056));

  // Tanggal saat ini
  const today = new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date());

  ctx.fillStyle = "#94a3b8";
  ctx.font = `500 ${Math.round(W * 0.018)}px ui-monospace, monospace`;
  ctx.textAlign = "right";
  ctx.fillText(today, W - pad, pad + Math.round(W * 0.026));
  ctx.textAlign = "left";

  // Konten Informasi Bawah (Responsif Unwrap & Multiline)
  const maxTextWidth = W - (pad * 2);

  // Ukur baris judul
  const title = customTitle.value || (props.locationLabel ? `Wilayah ${props.locationLabel}` : "Peta Spasial Kabupaten Bojonegoro");
  const titleFontSize = Math.round(W * 0.036);
  const titleLineHeight = Math.round(titleFontSize * 1.28);
  ctx.font = `bold ${titleFontSize}px ui-sans-serif, system-ui, sans-serif`;
  const titleLines = getWrappedLines(ctx, title, maxTextWidth);

  // Ukur baris deskripsi
  const descFontSize = Math.round(W * 0.021);
  const descLineHeight = Math.round(descFontSize * 1.34);
  ctx.font = `400 ${descFontSize}px ui-sans-serif, system-ui, sans-serif`;
  const descLines = customDescription.value
    ? getWrappedLines(ctx, customDescription.value, maxTextWidth)
    : [];

  // Ukuran Pill Koordinat & Watermark
  const pillH = Math.round(W * 0.036);
  const pillPadX = Math.round(W * 0.02);
  const pillSpacing = Math.round(W * 0.02);
  const watermarkSpacing = Math.round(W * 0.045);

  const totalContentHeight = (titleLines.length * titleLineHeight)
    + (descLines.length > 0 ? (descLines.length * descLineHeight + Math.round(W * 0.012)) : 0)
    + pillH
    + pillSpacing
    + watermarkSpacing;

  let currentY = H - pad - totalContentHeight;

  // Render Judul (Unwrapped Multiline)
  ctx.fillStyle = "#ffffff";
  ctx.font = `bold ${titleFontSize}px ui-sans-serif, system-ui, sans-serif`;
  for (const line of titleLines) {
    ctx.fillText(line, pad, currentY);
    currentY += titleLineHeight;
  }

  // Render Deskripsi (Unwrapped Multiline)
  if (descLines.length > 0) {
    currentY += Math.round(W * 0.01);
    ctx.fillStyle = "#cbd5e1"; // slate-300
    ctx.font = `400 ${descFontSize}px ui-sans-serif, system-ui, sans-serif`;
    for (const line of descLines) {
      ctx.fillText(line, pad, currentY);
      currentY += descLineHeight;
    }
  }

  // Render Pill Koordinat
  currentY += Math.round(W * 0.015);
  const coordStr = `WGS84: ${formattedCoordinateString.value}`;
  ctx.font = `600 ${Math.round(W * 0.02)}px ui-monospace, monospace`;
  const textWidth = ctx.measureText(coordStr).width;
  ctx.fillStyle = "rgba(30, 58, 138, 0.75)";
  ctx.strokeStyle = "rgba(96, 165, 250, 0.4)";
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.roundRect(pad, currentY, textWidth + pillPadX * 2, pillH, 8);
  ctx.fill();
  ctx.stroke();

  ctx.fillStyle = "#93c5fd";
  ctx.fillText(coordStr, pad + pillPadX, currentY + Math.round(pillH * 0.68));

  // Jika Image + Link aktif: tambahkan watermark link situs
  ctx.strokeStyle = "rgba(255, 255, 255, 0.12)";
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(pad, H - pad - Math.round(W * 0.035));
  ctx.lineTo(W - pad, H - pad - Math.round(W * 0.035));
  ctx.stroke();

  ctx.fillStyle = "#64748b";
  ctx.font = `600 ${Math.round(W * 0.018)}px ui-sans-serif, system-ui, sans-serif`;
  ctx.fillText("PEMERINTAH KABUPATEN BOJONEGORO", pad, H - pad);

  if (shareMode.value === "image_link") {
    ctx.fillStyle = "#3b82f6";
    ctx.font = `600 ${Math.round(W * 0.018)}px ui-monospace, monospace`;
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

          <!-- Kartu Pratinjau Peta Sesuai Rasio -->
          <div
            class="relative rounded-xl overflow-hidden shadow-lg border border-slate-800/80 bg-[#070b14] text-white flex flex-col justify-between transition-all duration-300 mx-auto select-none my-auto"
            :style="previewCardStyle"
          >
            <!-- Latar Snapshot / Fallback Peta -->
            <div class="absolute inset-0 z-0">
              <img
                v-if="snapshotDataUrl"
                :src="snapshotDataUrl"
                alt="Cuplikan Peta"
                class="w-full h-full object-cover"
              />
              <div v-else class="w-full h-full bg-[#0b1220] flex items-center justify-center relative overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(#1e3a8a_1px,transparent_1px)] [background-size:14px_14px] opacity-40" />
                <div class="size-12 rounded-full border border-blue-500/30 flex items-center justify-center">
                  <UIcon name="i-lucide-crosshair" class="size-6 text-blue-400/60" />
                </div>
              </div>
              <!-- Gradient Overlay Pelindung Teks -->
              <div class="absolute inset-0 bg-gradient-to-t from-[#070b14] via-[#070b14]/30 to-[#070b14]/80" />
            </div>

            <!-- Header Mini Kartu -->
            <div class="relative z-10 p-2.5 flex items-start justify-between">
              <div class="space-y-0.5">
                <p class="text-[8px] font-bold text-white tracking-wider uppercase">
                  KABUPATEN BOJONEGORO
                </p>
                <p class="text-[7px] font-semibold text-blue-400">
                  MELAROSA GIS • SATU DATA SPASIAL
                </p>
              </div>
              <span class="text-[8px] font-mono text-slate-300 bg-black/50 px-1.5 py-0.2 rounded backdrop-blur-xs">
                {{ selectedRatio.label }}
              </span>
            </div>

            <!-- Footer Mini Kartu (Responsif Unwrap & Multiline) -->
            <div class="relative z-10 p-2.5 space-y-1.5 w-full">
              <div class="space-y-0.5">
                <h4
                  class="font-bold text-white leading-snug whitespace-normal break-words"
                  :class="selectedRatioId === 'story' ? 'text-[9.5px]' : 'text-xs'"
                >
                  {{ customTitle || (locationLabel ? `Wilayah ${locationLabel}` : 'Peta Spasial Bojonegoro') }}
                </h4>
                <p
                  v-if="customDescription"
                  class="text-slate-300 font-normal leading-normal whitespace-normal break-words"
                  :class="selectedRatioId === 'story' ? 'text-[7.5px]' : 'text-[8.5px]'"
                >
                  {{ customDescription }}
                </p>
              </div>

              <!-- Pill Koordinat -->
              <div class="flex items-center gap-1 flex-wrap">
                <span
                  class="inline-flex items-center gap-1 rounded bg-blue-950/80 border border-blue-500/40 font-mono text-blue-200 shrink-0"
                  :class="selectedRatioId === 'story' ? 'text-[7px] px-1 py-0.2' : 'text-[8px] px-1.5 py-0.2'"
                >
                  <UIcon name="i-lucide-map-pin" class="size-2 text-blue-400" />
                  {{ formattedCoordinateString }}
                </span>
              </div>

              <!-- Watermark Tautan (Jika Tab Image + Link Aktif) -->
              <div
                v-if="shareMode === 'image_link'"
                class="pt-1 border-t border-white/10 flex items-center justify-between text-slate-400"
                :class="selectedRatioId === 'story' ? 'text-[6.5px]' : 'text-[7px]'"
              >
                <span>Pemkab Bojonegoro</span>
                <span class="font-mono text-blue-400 truncate ml-1">melarosa.bojonegorokab.go.id</span>
              </div>
            </div>
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

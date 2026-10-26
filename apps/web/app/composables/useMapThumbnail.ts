/**
 * Composable useMapThumbnail
 * Sistem penanganan thumbnail peta dinamis WebGIS Melarosa (Rasio 16:9).
 * Dilengkapi dengan in-memory LRU cache, debounce, penanganan CORS & transparansi layer,
 * serta generator fallback kartografis jika proses capture kanvas terhambat.
 */

export interface MapThumbnailOptions {
  width?: number; // Lebar thumbnail (default: 640)
  height?: number; // Tinggi thumbnail 16:9 (default: 360)
  quality?: number; // Kualitas kompresi (0.1 - 1.0, default: 0.92)
  format?: "image/png" | "image/jpeg" | "image/webp";
  includeWatermark?: boolean; // Tampilkan tanda air minimalis Melarosa
  crop16_9?: boolean; // Paksa crop ke rasio 16:9 dari tengah kanvas
  debounceMs?: number; // Jeda debounce sebelum eksekusi capture
}

export interface MapThumbnailMeta {
  center?: [number, number] | null;
  zoom?: number | null;
  extent?: [number, number, number, number] | null;
  basemap?: string | null;
  activeLayers?: { id: string; name?: string; visible?: boolean; opacity?: number }[];
  filterWilayah?: string | null;
  title?: string | null;
}

export interface MapThumbnailResult {
  dataUrl: string;
  isFallback: boolean;
  fallbackReason?: string;
  aspectRatio: number; // 16/9 = 1.7777777777777777
  width: number;
  height: number;
  timestamp: number;
  cacheKey: string;
}

// In-memory LRU Cache (Maksimal 30 thumbnail untuk menghemat memori browser)
const MAX_CACHE_SIZE = 30;
const thumbnailCache = new Map<string, MapThumbnailResult>();

/**
 * Menghasilkan kunci cache deterministik berdasarkan parameter viewport dan layer aktif
 */
export function buildThumbnailCacheKey(
  meta: MapThumbnailMeta,
  width: number,
  height: number,
  crop16_9 = true
): string {
  const c = meta.center || [111.89, -7.15];
  const z = typeof meta.zoom === "number" ? meta.zoom.toFixed(1) : "13.0";
  const ext = meta.extent ? meta.extent.map((v) => v.toFixed(3)).join(",") : "bjn";
  const bm = meta.basemap || "esri";
  const wil = meta.filterWilayah || "all";
  const activeLayersSignature = (meta.activeLayers || [])
    .filter((l) => l.visible !== false)
    .map((l) => `${l.id}:${(l.opacity ?? 1).toFixed(2)}`)
    .sort()
    .join(";");

  return `${bm}|${z}|${c[0].toFixed(3)},${c[1].toFixed(3)}|${ext}|${wil}|${activeLayersSignature}|${width}x${height}|${crop16_9 ? "16:9" : "raw"}`;
}

/**
 * Menguji apakah suatu elemen canvas terkena CORS taint (keamanan cross-origin)
 */
export function isCanvasTainted(canvas: HTMLCanvasElement): boolean {
  try {
    const ctx = canvas.getContext("2d");
    if (!ctx) return false;
    ctx.getImageData(0, 0, 1, 1);
    return false;
  } catch {
    return true;
  }
}

/**
 * Generator fallback kartografis rasio 16:9 jika kanvas peta utama ter-taint atau gagal diekspor
 */
export function generateFallbackMapCanvas(
  meta: MapThumbnailMeta,
  width = 640,
  height = 360,
  includeWatermark = true
): string {
  if (typeof document === "undefined") return "";

  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext("2d");
  if (!ctx) return "";

  // 1. Latar Belakang Gradasi Kartografis Gelap (Modern Deep Slate Navy)
  const bgGrad = ctx.createLinearGradient(0, 0, width, height);
  bgGrad.addColorStop(0, "#080e1e");
  bgGrad.addColorStop(0.5, "#0b162d");
  bgGrad.addColorStop(1, "#050914");
  ctx.fillStyle = bgGrad;
  ctx.fillRect(0, 0, width, height);

  // 2. Garis Grid Koordinat Spasial (Subtle Coordinate Mesh)
  ctx.strokeStyle = "rgba(59, 130, 246, 0.08)";
  ctx.lineWidth = 1;
  const gridSize = Math.round(width / 10);
  for (let x = gridSize; x < width; x += gridSize) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x, height);
    ctx.stroke();
  }
  for (let y = gridSize; y < height; y += gridSize) {
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(width, y);
    ctx.stroke();
  }

  // 3. Radial Glow Aksen Biru di Area Tengah Kanvas
  const cx = width / 2;
  const cy = height / 2;
  const glowGrad = ctx.createRadialGradient(cx, cy, 10, cx, cy, width * 0.45);
  glowGrad.addColorStop(0, "rgba(37, 99, 235, 0.16)");
  glowGrad.addColorStop(0.6, "rgba(59, 130, 246, 0.05)");
  glowGrad.addColorStop(1, "rgba(0, 0, 0, 0)");
  ctx.fillStyle = glowGrad;
  ctx.fillRect(0, 0, width, height);

  // 4. Retikel Bidik Kartografis di Tengah (Crosshair & Orbit Ring)
  ctx.strokeStyle = "rgba(96, 165, 250, 0.45)";
  ctx.lineWidth = 1.5;
  ctx.beginPath();
  ctx.arc(cx, cy, 32, 0, Math.PI * 2);
  ctx.stroke();

  ctx.strokeStyle = "rgba(96, 165, 250, 0.2)";
  ctx.beginPath();
  ctx.arc(cx, cy, 54, 0, Math.PI * 2);
  ctx.stroke();

  // Garis sumbu bidik
  ctx.strokeStyle = "rgba(96, 165, 250, 0.6)";
  ctx.beginPath();
  ctx.moveTo(cx - 42, cy);
  ctx.lineTo(cx + 42, cy);
  ctx.moveTo(cx, cy - 42);
  ctx.lineTo(cx, cy + 42);
  ctx.stroke();

  // Titik pusat
  ctx.fillStyle = "#60a5fa";
  ctx.beginPath();
  ctx.arc(cx, cy, 3.5, 0, Math.PI * 2);
  ctx.fill();

  // 5. Sudut Pembingkai Bounding Box (Corner Bracket Accents)
  const pad = 16;
  const cornerLen = 14;
  ctx.strokeStyle = "rgba(96, 165, 250, 0.5)";
  ctx.lineWidth = 2;

  // Kiri Atas
  ctx.beginPath();
  ctx.moveTo(pad, pad + cornerLen);
  ctx.lineTo(pad, pad);
  ctx.lineTo(pad + cornerLen, pad);
  ctx.stroke();

  // Kanan Atas
  ctx.beginPath();
  ctx.moveTo(width - pad - cornerLen, pad);
  ctx.lineTo(width - pad, pad);
  ctx.lineTo(width - pad, pad + cornerLen);
  ctx.stroke();

  // Kiri Bawah
  ctx.beginPath();
  ctx.moveTo(pad, height - pad - cornerLen);
  ctx.lineTo(pad, height - pad);
  ctx.lineTo(pad + cornerLen, height - pad);
  ctx.stroke();

  // Kanan Bawah
  ctx.beginPath();
  ctx.moveTo(width - pad - cornerLen, height - pad);
  ctx.lineTo(width - pad, height - pad);
  ctx.lineTo(width - pad, height - pad - cornerLen);
  ctx.stroke();

  // 6. Header Badge (Kiri Atas): "Pratinjau Kartografis Spasial (16:9)"
  ctx.font = "bold 11px system-ui, -apple-system, sans-serif";
  const tagText = "PRATINJAU KARTOGRAFIS (CORS FALLBACK)";
  const tagW = ctx.measureText(tagText).width + 18;
  const tagH = 22;

  ctx.fillStyle = "rgba(15, 23, 42, 0.85)";
  ctx.strokeStyle = "rgba(245, 158, 11, 0.4)";
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.roundRect(pad + 4, pad + 4, tagW, tagH, 6);
  ctx.fill();
  ctx.stroke();

  // Dot indikator amber
  ctx.fillStyle = "#f59e0b";
  ctx.beginPath();
  ctx.arc(pad + 13, pad + 15, 3.5, 0, Math.PI * 2);
  ctx.fill();

  ctx.fillStyle = "#fbbf24";
  ctx.fillText(tagText, pad + 22, pad + 19);

  // 7. Kompas / Arah Utara (Kanan Atas)
  const compassX = width - pad - 20;
  const compassY = pad + 18;
  ctx.fillStyle = "rgba(15, 23, 42, 0.75)";
  ctx.beginPath();
  ctx.arc(compassX, compassY, 14, 0, Math.PI * 2);
  ctx.fill();

  ctx.strokeStyle = "rgba(255, 255, 255, 0.25)";
  ctx.stroke();

  ctx.fillStyle = "#ef4444";
  ctx.beginPath();
  ctx.moveTo(compassX, compassY - 10);
  ctx.lineTo(compassX - 4, compassY);
  ctx.lineTo(compassX + 4, compassY);
  ctx.closePath();
  ctx.fill();

  ctx.fillStyle = "#ffffff";
  ctx.font = "bold 8px system-ui, sans-serif";
  ctx.textAlign = "center";
  ctx.fillText("U", compassX, compassY + 8);
  ctx.textAlign = "start";

  // 8. Informasi Wilayah & Koordinat (Kiri Bawah)
  const title = meta.filterWilayah
    ? `Kecamatan ${meta.filterWilayah}`
    : meta.title || "Peta Spasial Kabupaten Bojonegoro";
  ctx.font = "bold 16px system-ui, -apple-system, sans-serif";
  ctx.fillStyle = "#ffffff";
  ctx.fillText(title, pad + 8, height - pad - 34);

  const centerCoord = meta.center || [111.89, -7.15];
  const zoomText = meta.zoom ? `Z ${meta.zoom.toFixed(1)}` : "Z 13.5";
  const subText = `${centerCoord[1].toFixed(4)}°, ${centerCoord[0].toFixed(4)}° · ${zoomText} · Basemap ${meta.basemap || "Esri"}`;
  ctx.font = "500 11px ui-monospace, monospace";
  ctx.fillStyle = "rgba(203, 213, 225, 0.85)";
  ctx.fillText(subText, pad + 8, height - pad - 16);

  // 9. Watermark Minimalis Resmi Melarosa (Kanan Bawah)
  if (includeWatermark) {
    ctx.font = "600 10px system-ui, -apple-system, sans-serif";
    ctx.fillStyle = "rgba(148, 163, 184, 0.65)";
    const wmText = "MELAROSA WEBGIS · BOJONEGORO";
    const wmW = ctx.measureText(wmText).width;
    ctx.fillText(wmText, width - pad - wmW - 4, height - pad - 16);
  }

  return canvas.toDataURL("image/png");
}

/**
 * Memotong kanvas peta ke rasio 16:9 dan menskalakannya secara presisi
 */
export function cropCanvasTo16_9(
  sourceCanvas: HTMLCanvasElement,
  targetWidth = 640,
  targetHeight = 360,
  quality = 0.92,
  format: "image/png" | "image/jpeg" | "image/webp" = "image/png",
  includeWatermark = true,
  meta?: MapThumbnailMeta
): string {
  const targetRatio = 16 / 9;
  const srcW = sourceCanvas.width;
  const srcH = sourceCanvas.height;
  if (srcW === 0 || srcH === 0) return "";

  const srcRatio = srcW / srcH;
  let cropW = srcW;
  let cropH = srcH;
  let cropX = 0;
  let cropY = 0;

  if (srcRatio > targetRatio) {
    // Kanvas lebih lebar dari 16:9 (crop sisi kiri dan kanan)
    cropH = srcH;
    cropW = Math.round(srcH * targetRatio);
    cropX = Math.round((srcW - cropW) / 2);
    cropY = 0;
  } else {
    // Kanvas lebih tinggi dari 16:9 (crop sisi atas dan bawah)
    cropW = srcW;
    cropH = Math.round(srcW / targetRatio);
    cropX = 0;
    cropY = Math.round((srcH - cropH) / 2);
  }

  const outCanvas = document.createElement("canvas");
  outCanvas.width = targetWidth;
  outCanvas.height = targetHeight;
  const outCtx = outCanvas.getContext("2d");
  if (!outCtx) return "";

  outCtx.imageSmoothingEnabled = true;
  outCtx.imageSmoothingQuality = "high";

  // Salin area potongan 16:9 ke kanvas keluaran
  outCtx.drawImage(
    sourceCanvas,
    cropX,
    cropY,
    cropW,
    cropH,
    0,
    0,
    targetWidth,
    targetHeight
  );

  // Tanda air halus di sudut kanan bawah jika diminta
  if (includeWatermark) {
    outCtx.font = "600 10px system-ui, -apple-system, sans-serif";
    const label = "MELAROSA WEBGIS · 16:9";
    const labelW = outCtx.measureText(label).width;
    const pillW = labelW + 16;
    const pillH = 20;
    const pillX = targetWidth - pillW - 12;
    const pillY = targetHeight - pillH - 10;

    outCtx.fillStyle = "rgba(10, 15, 29, 0.72)";
    outCtx.strokeStyle = "rgba(255, 255, 255, 0.15)";
    outCtx.lineWidth = 1;
    outCtx.beginPath();
    outCtx.roundRect(pillX, pillY, pillW, pillH, 6);
    outCtx.fill();
    outCtx.stroke();

    outCtx.fillStyle = "rgba(255, 255, 255, 0.9)";
    outCtx.fillText(label, pillX + 8, pillY + 14);
  }

  return outCanvas.toDataURL(format, quality);
}

/**
 * Salin data URL gambar ke clipboard
 */
export async function copyThumbnailToClipboard(dataUrl: string): Promise<boolean> {
  if (typeof window === "undefined" || !navigator?.clipboard) return false;
  try {
    const res = await fetch(dataUrl);
    const blob = await res.blob();
    await navigator.clipboard.write([
      new ClipboardItem({
        [blob.type]: blob,
      }),
    ]);
    return true;
  } catch (err) {
    console.warn("Gagal menyalin gambar ke clipboard:", err);
    return false;
  }
}

/**
 * Unduh gambar data URL sebagai berkas PNG
 */
export function downloadThumbnailFile(
  dataUrl: string,
  filename = `melarosa-peta-${Date.now()}.png`
): void {
  if (typeof document === "undefined") return;
  const a = document.createElement("a");
  a.href = dataUrl;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

/**
 * Composable utama useMapThumbnail
 */
export function useMapThumbnail() {
  const currentThumbnail = ref<MapThumbnailResult | null>(null);
  const isLoading = ref(false);
  const isFallback = ref(false);
  const hasError = ref(false);

  let debounceTimer: ReturnType<typeof setTimeout> | null = null;

  /**
   * Mengambil thumbnail dari cache jika tersedia
   */
  function getCachedThumbnail(key: string): MapThumbnailResult | null {
    if (thumbnailCache.has(key)) {
      return thumbnailCache.get(key) || null;
    }
    return null;
  }

  /**
   * Menyimpan thumbnail ke cache LRU
   */
  function setCachedThumbnail(key: string, result: MapThumbnailResult): void {
    if (thumbnailCache.size >= MAX_CACHE_SIZE) {
      const oldestKey = thumbnailCache.keys().next().value;
      if (oldestKey) thumbnailCache.delete(oldestKey);
    }
    thumbnailCache.set(key, result);
  }

  /**
   * Bersihkan seluruh cache thumbnail
   */
  function clearCache(): void {
    thumbnailCache.clear();
  }

  /**
   * Generate thumbnail 16:9 secara langsung
   */
  async function generateThumbnail(
    captureFn: (options?: MapThumbnailOptions) => Promise<string | null>,
    meta: MapThumbnailMeta,
    options: MapThumbnailOptions = {}
  ): Promise<MapThumbnailResult> {
    const width = options.width || 640;
    const height = options.height || Math.round((width * 9) / 16);
    const crop16_9 = options.crop16_9 !== false;
    const cacheKey = buildThumbnailCacheKey(meta, width, height, crop16_9);

    // 1. Periksa Cache Terlebih Dahulu
    const cached = getCachedThumbnail(cacheKey);
    if (cached) {
      currentThumbnail.value = cached;
      isFallback.value = cached.isFallback;
      hasError.value = false;
      return cached;
    }

    isLoading.value = true;
    hasError.value = false;

    try {
      const rawDataUrl = await captureFn(options);

      let finalDataUrl = rawDataUrl;
      let usedFallback = false;
      let reason: string | undefined;

      if (!finalDataUrl) {
        // Mekanisme Fallback jika capture kanvas gagal (CORS tainted atau null)
        usedFallback = true;
        reason = "CORS Tainted Canvas atau Layer Rendering Timeout";
        finalDataUrl = generateFallbackMapCanvas(
          meta,
          width,
          height,
          options.includeWatermark !== false
        );
      }

      const result: MapThumbnailResult = {
        dataUrl: finalDataUrl,
        isFallback: usedFallback,
        fallbackReason: reason,
        aspectRatio: 16 / 9,
        width,
        height,
        timestamp: Date.now(),
        cacheKey,
      };

      setCachedThumbnail(cacheKey, result);
      currentThumbnail.value = result;
      isFallback.value = usedFallback;
      return result;
    } catch (err: any) {
      console.warn("Kesalahan saat menghasilkan thumbnail peta:", err);
      hasError.value = true;
      isFallback.value = true;

      const fallbackDataUrl = generateFallbackMapCanvas(
        meta,
        width,
        height,
        options.includeWatermark !== false
      );
      const result: MapThumbnailResult = {
        dataUrl: fallbackDataUrl,
        isFallback: true,
        fallbackReason: err?.message || "Kesalahan tak terduga",
        aspectRatio: 16 / 9,
        width,
        height,
        timestamp: Date.now(),
        cacheKey,
      };

      currentThumbnail.value = result;
      return result;
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Generate thumbnail dengan debounce (misal saat pergerakan kamera atau zoom selesai)
   */
  function debouncedGenerateThumbnail(
    captureFn: (options?: MapThumbnailOptions) => Promise<string | null>,
    meta: MapThumbnailMeta,
    options: MapThumbnailOptions = {}
  ): void {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }
    const delay = options.debounceMs ?? 380;
    debounceTimer = setTimeout(() => {
      generateThumbnail(captureFn, meta, options);
    }, delay);
  }

  return {
    currentThumbnail,
    isLoading,
    isFallback,
    hasError,
    generateThumbnail,
    debouncedGenerateThumbnail,
    clearCache,
    copyThumbnail: () => {
      if (currentThumbnail.value?.dataUrl) {
        return copyThumbnailToClipboard(currentThumbnail.value.dataUrl);
      }
      return Promise.resolve(false);
    },
    downloadThumbnail: (customName?: string) => {
      if (currentThumbnail.value?.dataUrl) {
        downloadThumbnailFile(currentThumbnail.value.dataUrl, customName);
      }
    },
  };
}

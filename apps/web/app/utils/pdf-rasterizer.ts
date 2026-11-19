export interface RasterizedPdfResult {
  blob: Blob;
  objectUrl: string;
  width: number;
  height: number;
  pageCount: number;
  pageNumber: number;
}

let pdfWorkerConfigured = false;

async function getPdfJs() {
  const [pdfjsLib, workerMod] = await Promise.all([
    import('pdfjs-dist'),
    import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
  ]);

  if (!pdfWorkerConfigured) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = workerMod.default;
    pdfWorkerConfigured = true;
  }

  return pdfjsLib;
}

/**
 * Render satu halaman berkas PDF menjadi gambar PNG raster langsung di browser (tanpa GDAL/Poppler di server).
 */
export async function rasterizePdfPageToPng(
  source: string | ArrayBuffer | Blob,
  pageNumber = 1,
  maxDimensionPx = 2400,
): Promise<RasterizedPdfResult> {
  const pdfjs = await getPdfJs();

  let data: ArrayBuffer | undefined;
  let url: string | undefined;

  if (typeof source === 'string') {
    url = source;
  } else if (source instanceof Blob) {
    data = await source.arrayBuffer();
  } else {
    data = source;
  }

  const loadingTask = pdfjs.getDocument(
    url ? { url, withCredentials: true } : { data },
  );
  const pdfDoc = await loadingTask.promise;
  let page: any = null;

  try {
    const safePage = Math.max(1, Math.min(pageNumber, pdfDoc.numPages));
    page = await pdfDoc.getPage(safePage);

    const baseViewport = page.getViewport({ scale: 1 });
    const longestSide = Math.max(baseViewport.width, baseViewport.height, 1);
    const scale = Math.min(3.5, Math.max(1.25, maxDimensionPx / longestSide));
    const viewport = page.getViewport({ scale });

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(viewport.width);
    canvas.height = Math.round(viewport.height);

    const ctx = canvas.getContext('2d');
    if (!ctx) {
      throw new Error('Canvas 2D context tidak tersedia.');
    }

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    await page.render({
      canvas,
      viewport,
    }).promise;

    const blob = await new Promise<Blob>((resolve, reject) => {
      canvas.toBlob(
        (b) => (b ? resolve(b) : reject(new Error('Gagal mengonversi halaman PDF ke PNG.'))),
        'image/png',
        0.95,
      );
    });

    const objectUrl = URL.createObjectURL(blob);

    return {
      blob,
      objectUrl,
      width: canvas.width,
      height: canvas.height,
      pageCount: pdfDoc.numPages,
      pageNumber: safePage,
    };
  } finally {
    try {
      page?.cleanup?.();
    } catch {
      // Abaikan error pembersihan halaman
    }
    try {
      await pdfDoc?.cleanup?.();
    } catch {
      // Abaikan error pembersihan dokumen
    }
    try {
      await loadingTask?.destroy?.();
    } catch {
      // Abaikan error pelepasan worker PDF.js
    }
  }
}

export async function loadImageDimensionsFromBlob(blobOrUrl: Blob | string): Promise<{ width: number; height: number }> {
  const url = typeof blobOrUrl === 'string' ? blobOrUrl : URL.createObjectURL(blobOrUrl);
  try {
    const img = await new Promise<HTMLImageElement>((resolve, reject) => {
      const el = new Image();
      el.onload = () => resolve(el);
      el.onerror = () => reject(new Error('Gagal memuat gambar peta raster.'));
      el.src = url;
    });
    return {
      width: img.naturalWidth || 1000,
      height: img.naturalHeight || 1000,
    };
  } finally {
    if (typeof blobOrUrl !== 'string') {
      URL.revokeObjectURL(url);
    }
  }
}

import type { ActiveLayerItem } from "~/types/map-layers";

export type MvtExtentTuple = [number, number, number, number];

/**
 * Bounding box Bojonegoro dengan margin aman agar segmen/batas di tepi kabupaten
 * tidak terpotong (berdasarkan gabungan 28 kecamatan + buffer ~5 km).
 */
export const BOJONEGORO_MVT_BBOX_4326: MvtExtentTuple = [111.38, -7.51, 112.21, -6.94];

export const DEFAULT_VIEWPORT_BUFFER_RATIO = 0.3;
export const DEFAULT_MIN_TILE_CACHE_SIZE = 16;
export const DEFAULT_MAX_TILE_CACHE_SIZE = 64;

const TILE_STATE = {
  IDLE: 0,
  LOADING: 1,
  LOADED: 2,
  ERROR: 3,
  EMPTY: 4,
} as const;

const KNOWN_BOJONEGORO_MVT_SOURCES = new Set([
  "infrastruktur_segmen",
  "jalan_porosdesa",
  "bataswilayah_kabupaten",
]);

const MERCATOR_MAX_LAT = 85.05112878;
const MERCATOR_RADIUS = 6378137;
const DEG_TO_RAD = Math.PI / 180;

const VIEW_HINT = {
  ANIMATING: 0,
  INTERACTING: 1,
} as const;

export type TransformExtentFn = (
  extent: number[],
  sourceProj: string,
  destinationProj: string
) => number[];

export interface AttachMvtLoaderOptions {
  mapInstance: any;
  source: any;
  olLayer?: any;
  layer: Partial<ActiveLayerItem> & Record<string, any>;
  getLayerRef?: () => any;
  allowedExtent3857: MvtExtentTuple | null;
  viewportBufferRatio?: number;
  minCacheSize?: number;
  maxCacheSize?: number;
  shouldDeferLabels?: () => boolean;
}

export interface PruneMvtCacheOptions {
  clearAll?: boolean;
  dropEmptyOnly?: boolean;
  viewportBufferRatio?: number;
}

export interface MvtExtentUpdateResult {
  changed: boolean;
  widened: boolean;
  narrowed: boolean;
}

export interface MvtLayerController {
  getAllowedExtent3857: () => MvtExtentTuple | null;
  setAllowedExtent3857: (next: MvtExtentTuple | null) => MvtExtentUpdateResult;
  abortOutOfExtentRequests: () => number;
  abortAll: () => number;
  pruneCache: (options?: PruneMvtCacheOptions) => number;
  getPendingCount: () => number;
  bindLayer: (olLayer: any) => void;
  hasDeferredLabels: () => boolean;
  dispose: () => void;
}

export function isValidExtent(ext: unknown): ext is MvtExtentTuple {
  if (!Array.isArray(ext) || ext.length !== 4) return false;
  for (let i = 0; i < 4; i++) {
    if (typeof ext[i] !== "number" || !Number.isFinite(ext[i])) return false;
  }
  return ext[0] < ext[2] && ext[1] < ext[3];
}

export function isLikelyLonLatExtent(ext: readonly number[]): boolean {
  return (
    Math.abs(ext[0]) <= 180.001 &&
    Math.abs(ext[2]) <= 180.001 &&
    Math.abs(ext[1]) <= 90.001 &&
    Math.abs(ext[3]) <= 90.001
  );
}

export function extentsIntersect(a: readonly number[], b: readonly number[]): boolean {
  return a[0] <= b[2] && a[2] >= b[0] && a[1] <= b[3] && a[3] >= b[1];
}

export function extentsEqual(
  a: MvtExtentTuple | null | undefined,
  b: MvtExtentTuple | null | undefined,
  epsilon = 1
): boolean {
  if (!a && !b) return true;
  if (!a || !b) return false;
  return (
    Math.abs(a[0] - b[0]) <= epsilon &&
    Math.abs(a[1] - b[1]) <= epsilon &&
    Math.abs(a[2] - b[2]) <= epsilon &&
    Math.abs(a[3] - b[3]) <= epsilon
  );
}

export function intersectExtents(
  a: readonly number[],
  b: readonly number[]
): MvtExtentTuple | null {
  if (!extentsIntersect(a, b)) return null;
  const minX = Math.max(a[0], b[0]);
  const minY = Math.max(a[1], b[1]);
  const maxX = Math.min(a[2], b[2]);
  const maxY = Math.min(a[3], b[3]);
  if (minX >= maxX || minY >= maxY) return null;
  return [minX, minY, maxX, maxY];
}

export function bufferExtentByRatio(
  ext: MvtExtentTuple,
  ratio: number,
  minPadUnits = 0
): MvtExtentTuple {
  const width = ext[2] - ext[0];
  const height = ext[3] - ext[1];
  const padX = Math.max(width * ratio, minPadUnits);
  const padY = Math.max(height * ratio, minPadUnits);
  return [ext[0] - padX, ext[1] - padY, ext[2] + padX, ext[3] + padY];
}

export function lonLatBboxToMercator(bbox: MvtExtentTuple): MvtExtentTuple {
  const clampLat = (lat: number) =>
    Math.max(-MERCATOR_MAX_LAT, Math.min(MERCATOR_MAX_LAT, lat));
  const toX = (lon: number) => MERCATOR_RADIUS * lon * DEG_TO_RAD;
  const toY = (lat: number) => {
    const clamped = clampLat(lat) * DEG_TO_RAD;
    return MERCATOR_RADIUS * Math.log(Math.tan(Math.PI / 4 + clamped / 2));
  };
  return [toX(bbox[0]), toY(bbox[1]), toX(bbox[2]), toY(bbox[3])];
}

function toMercatorExtent(
  raw: unknown,
  transformExtent?: TransformExtentFn
): MvtExtentTuple | null {
  if (!isValidExtent(raw)) return null;
  if (!isLikelyLonLatExtent(raw)) {
    return [raw[0], raw[1], raw[2], raw[3]];
  }
  if (typeof transformExtent === "function") {
    const transformed = transformExtent(raw, "EPSG:4326", "EPSG:3857");
    if (isValidExtent(transformed)) return transformed;
  }
  return lonLatBboxToMercator(raw);
}

function isBojonegoroLocalLayer(layer: Partial<ActiveLayerItem> & Record<string, any>): boolean {
  const sourceName = String(layer?.martinSource || layer?.layer_name || "").toLowerCase();
  if (KNOWN_BOJONEGORO_MVT_SOURCES.has(sourceName)) return true;
  if (layer?.source_type === "martin") return true;
  const rawUrl = String(layer?.mvtUrl || layer?.url || "");
  return rawUrl.startsWith("/martin/");
}

/**
 * Menentukan batas spatial EPSG:3857 yang diizinkan untuk layer MVT.
 * Prioritas: irisan filterExtent aktif dengan batas dataset (csw_metadata.bounds atau Bojonegoro).
 */
export function resolveLayerAllowedExtent3857(
  layer: Partial<ActiveLayerItem> & Record<string, any>,
  transformExtent?: TransformExtentFn
): MvtExtentTuple | null {
  let dataBounds3857: MvtExtentTuple | null = null;

  const metaBounds = toMercatorExtent(layer?.csw_metadata?.bounds, transformExtent);
  if (metaBounds) {
    dataBounds3857 = bufferExtentByRatio(metaBounds, 0.05, 1500);
  } else if (isBojonegoroLocalLayer(layer)) {
    dataBounds3857 = toMercatorExtent(BOJONEGORO_MVT_BBOX_4326, transformExtent);
  }

  const filter3857 = toMercatorExtent(layer?.filterExtent, transformExtent);
  if (filter3857) {
    // Beri sedikit margin di tepi bbox filter kecamatan agar ruas jalan lintas batas tidak terpotong
    const bufferedFilter = bufferExtentByRatio(filter3857, 0.08, 1500);
    if (dataBounds3857) {
      return intersectExtents(bufferedFilter, dataBounds3857) || bufferedFilter;
    }
    return bufferedFilter;
  }

  return dataBounds3857;
}

export function getBufferedViewportExtent3857(
  mapInstance: any,
  bufferRatio = DEFAULT_VIEWPORT_BUFFER_RATIO
): MvtExtentTuple | null {
  const view = mapInstance?.getView?.();
  const size = mapInstance?.getSize?.();
  if (!view || !Array.isArray(size) || size[0] <= 0 || size[1] <= 0) return null;
  const viewExtent = view.calculateExtent?.(size);
  if (!isValidExtent(viewExtent)) return null;
  return bufferRatio > 0 ? bufferExtentByRatio(viewExtent, bufferRatio) : viewExtent;
}

function releaseRenderTileResources(tile: any) {
  if (!tile) return;
  try {
    tile.dispose?.();
  } catch {
    // Abaikan jika tile sudah dirilis OpenLayers
  }
  if (tile.executorGroups) {
    tile.executorGroups = {};
  }
  if (tile.hitDetectionImageData) {
    tile.hitDetectionImageData = {};
  }
}

function getCurrentViewTileZ(mapInstance: any, source: any): number | null {
  const view = mapInstance?.getView?.();
  const resolution = view?.getResolution?.();
  if (typeof resolution === "number" && Number.isFinite(resolution) && resolution > 0) {
    const tileGrid = source?.getTileGrid?.();
    const z = tileGrid?.getZForResolution?.(resolution);
    if (typeof z === "number" && Number.isFinite(z)) return z;
  }
  const zoom = view?.getZoom?.();
  return typeof zoom === "number" && Number.isFinite(zoom) ? Math.round(zoom) : null;
}

/**
 * Memasang loader MVT sadar-extent, abortable fetch, serta cache pruning terikat pada VectorTileSource.
 * TileGrid standar WebMercator tetap dipertahankan pada source; pembatasan dilakukan di layer extent,
 * tileUrlFunction, tileLoadFunction (AbortController), dan renderer tileCache.
 */
export function attachExtentAwareMvtLoader(
  options: AttachMvtLoaderOptions
): MvtLayerController {
  const {
    mapInstance,
    source,
    viewportBufferRatio = DEFAULT_VIEWPORT_BUFFER_RATIO,
    minCacheSize = DEFAULT_MIN_TILE_CACHE_SIZE,
    maxCacheSize = DEFAULT_MAX_TILE_CACHE_SIZE,
    shouldDeferLabels = () => true,
  } = options;

  let boundLayer: any = options.olLayer ?? null;
  const getLayerRef = () => boundLayer ?? options.getLayerRef?.() ?? null;

  let labelsDeferred = false;
  let cleanupLayerDeferral: (() => void) | null = null;
  let allowedExtent3857 = options.allowedExtent3857;
  const baseTileUrlFunction = source.getTileUrlFunction?.();

  interface PendingTileRequest {
    controller: AbortController;
    extent: MvtExtentTuple | null;
    z: number;
    sourceTile: any;
  }
  const pendingRequests = new Map<string, PendingTileRequest>();

  const isZoomRelevant = (tileZ: number | undefined, marginDown = 2, marginUp = 1): boolean => {
    if (typeof tileZ !== "number" || !Number.isFinite(tileZ)) return true;
    const viewZ = getCurrentViewTileZ(mapInstance, source);
    if (viewZ === null) return true;
    return tileZ >= viewZ - marginDown && tileZ <= viewZ + marginUp;
  };

  const isTileCurrentlyRelevant = (
    tileExtent: MvtExtentTuple | null,
    tileZ: number | undefined,
    extraBufferRatio = viewportBufferRatio + 0.05
  ): boolean => {
    const olLayer = getLayerRef();
    if (!olLayer || olLayer.getVisible?.() === false) return false;
    if (!isZoomRelevant(tileZ)) return false;

    if (allowedExtent3857 && tileExtent && !extentsIntersect(tileExtent, allowedExtent3857)) {
      return false;
    }

    const bufferedView = getBufferedViewportExtent3857(mapInstance, extraBufferRatio);
    if (bufferedView && tileExtent && !extentsIntersect(tileExtent, bufferedView)) {
      return false;
    }
    return true;
  };

  const evictAbortedSourceTile = (sourceTile: any) => {
    // Jalankan setelah siklus sinkron TileQueue.loadMoreTiles agar tileCache & sourceTiles_ bersih
    queueMicrotask(() => {
      const olLayer = getLayerRef();
      const renderer = olLayer?.getRenderer?.();
      const tileCache = renderer?.getTileCache?.();
      if (!tileCache) return;

      const keys = tileCache.getKeys();
      for (let i = 0; i < keys.length; i++) {
        const key = keys[i];
        const renderTile = tileCache.peek(key);
        if (!renderTile) continue;
        const matchesSource = Array.isArray(renderTile.sourceTiles)
          ? renderTile.sourceTiles.includes(sourceTile)
          : false;
        if (matchesSource || renderTile.getState?.() === TILE_STATE.ERROR) {
          tileCache.remove(key);
          releaseRenderTileResources(renderTile);
        }
      }
    });
  };

  if (typeof baseTileUrlFunction === "function") {
    source.setTileUrlFunction((tileCoord: number[], pixelRatio: number, projection: any) => {
      if (allowedExtent3857) {
        const tileGrid = source.getTileGrid?.();
        const tileExtent = tileGrid?.getTileCoordExtent?.(tileCoord);
        if (isValidExtent(tileExtent) && !extentsIntersect(tileExtent, allowedExtent3857)) {
          return undefined;
        }
      }
      return baseTileUrlFunction(tileCoord, pixelRatio, projection);
    });
  }

  source.setTileLoadFunction((sourceTile: any, url: string) => {
    sourceTile.setLoader((extent: number[], _resolution: number, projection: any) => {
      const tileExtent = isValidExtent(extent) ? extent : null;
      const tileZ = Array.isArray(sourceTile.tileCoord) ? sourceTile.tileCoord[0] : undefined;

      if (!url || !isTileCurrentlyRelevant(tileExtent, tileZ)) {
        sourceTile.onError();
        evictAbortedSourceTile(sourceTile);
        return;
      }

      const requestKey = `${sourceTile.getKey?.() || ""}|${url}`;
      const existing = pendingRequests.get(requestKey);
      if (existing) {
        existing.controller.abort();
        pendingRequests.delete(requestKey);
      }

      const controller = new AbortController();
      pendingRequests.set(requestKey, {
        controller,
        extent: tileExtent,
        z: typeof tileZ === "number" ? tileZ : 0,
        sourceTile,
      });

      fetch(url, {
        signal: controller.signal,
        credentials: "same-origin",
        headers: {
          Accept: "application/vnd.mapbox-vector-tile, application/x-protobuf, */*",
        },
      })
        .then(async (response) => {
          if (controller.signal.aborted || !isTileCurrentlyRelevant(tileExtent, tileZ)) {
            controller.abort();
            sourceTile.onError();
            evictAbortedSourceTile(sourceTile);
            return;
          }

          // Martin mengembalikan HTTP 204 untuk tile kosong (valid tanpa fitur)
          if (response.status === 204) {
            sourceTile.setFeatures([]);
            return;
          }

          if (!response.ok) {
            sourceTile.onError();
            return;
          }

          const buffer = await response.arrayBuffer();
          if (controller.signal.aborted || !isTileCurrentlyRelevant(tileExtent, tileZ)) {
            controller.abort();
            sourceTile.onError();
            evictAbortedSourceTile(sourceTile);
            return;
          }

          if (!buffer || buffer.byteLength === 0) {
            sourceTile.setFeatures([]);
            return;
          }

          const format = sourceTile.getFormat();
          const features = format.readFeatures(buffer, {
            extent,
            featureProjection: projection,
          });
          sourceTile.setFeatures(features);
        })
        .catch((err) => {
          if (controller.signal.aborted || err?.name === "AbortError") {
            sourceTile.onError();
            evictAbortedSourceTile(sourceTile);
            return;
          }
          sourceTile.onError();
        })
        .finally(() => {
          const current = pendingRequests.get(requestKey);
          if (current?.controller === controller) {
            pendingRequests.delete(requestKey);
          }
        });
    });
  });

  const abortAll = (): number => {
    let aborted = 0;
    for (const [key, entry] of pendingRequests.entries()) {
      entry.controller.abort();
      entry.sourceTile.onError?.();
      evictAbortedSourceTile(entry.sourceTile);
      pendingRequests.delete(key);
      aborted++;
    }
    return aborted;
  };

  const abortOutOfExtentRequests = (): number => {
    const olLayer = getLayerRef();
    if (!olLayer || olLayer.getVisible?.() === false) {
      return abortAll();
    }

    let aborted = 0;
    for (const [key, entry] of pendingRequests.entries()) {
      if (!isTileCurrentlyRelevant(entry.extent, entry.z)) {
        entry.controller.abort();
        entry.sourceTile.onError?.();
        evictAbortedSourceTile(entry.sourceTile);
        pendingRequests.delete(key);
        aborted++;
      }
    }
    return aborted;
  };

  const pruneCache = (pruneOpts: PruneMvtCacheOptions = {}): number => {
    const olLayer = getLayerRef();
    const renderer = olLayer?.getRenderer?.();
    const tileCache = renderer?.getTileCache?.();
    if (!tileCache) return 0;

    if (pruneOpts.clearAll || olLayer?.getVisible?.() === false) {
      abortAll();
      const keys = tileCache.getKeys();
      for (let i = 0; i < keys.length; i++) {
        const tile = tileCache.remove(keys[i]);
        releaseRenderTileResources(tile);
      }
      if (Array.isArray(renderer.renderedTiles)) {
        renderer.renderedTiles.length = 0;
      }
      tileCache.highWaterMark = minCacheSize;
      return keys.length;
    }

    const tileGrid = source.getTileGrid?.();
    const ratio = pruneOpts.viewportBufferRatio ?? viewportBufferRatio;
    const bufferedView = getBufferedViewportExtent3857(mapInstance, ratio);
    const viewZ = getCurrentViewTileZ(mapInstance, source);
    const renderedTiles: any[] = Array.isArray(renderer.renderedTiles)
      ? renderer.renderedTiles
      : [];
    const activeRenderedSet = new Set<any>(renderedTiles);

    const isDisjointFromAllowed = Boolean(
      allowedExtent3857 &&
        bufferedView &&
        !extentsIntersect(allowedExtent3857, bufferedView)
    );

    const activeArea =
      isDisjointFromAllowed
        ? null
        : allowedExtent3857 && bufferedView
          ? intersectExtents(allowedExtent3857, bufferedView)
          : allowedExtent3857 || bufferedView;

    const keys = tileCache.getKeys();
    const evictKeys: string[] = [];

    for (let i = 0; i < keys.length; i++) {
      const key = keys[i];
      const tile = tileCache.peek(key);
      if (!tile) {
        evictKeys.push(key);
        continue;
      }

      const state = tile.getState?.();
      const tileCoord = tile.tileCoord || tile.getTileCoord?.();
      const tileZ = Array.isArray(tileCoord) ? tileCoord[0] : undefined;
      const tileExtent =
        tileGrid && Array.isArray(tileCoord) ? tileGrid.getTileCoordExtent?.(tileCoord) : null;

      if (state === TILE_STATE.ERROR) {
        evictKeys.push(key);
        continue;
      }

      if (
        allowedExtent3857 &&
        isValidExtent(tileExtent) &&
        !extentsIntersect(tileExtent, allowedExtent3857)
      ) {
        evictKeys.push(key);
        continue;
      }

      if (pruneOpts.dropEmptyOnly) {
        if (state === TILE_STATE.EMPTY) {
          evictKeys.push(key);
        }
        continue;
      }

      // Jangan hapus tile yang aktif tergambar di frame terakhir jika masih di dalam allowedExtent
      if (activeRenderedSet.has(tile)) {
        continue;
      }

      if (
        isDisjointFromAllowed ||
        (activeArea && isValidExtent(tileExtent) && !extentsIntersect(tileExtent, activeArea))
      ) {
        evictKeys.push(key);
        continue;
      }

      if (
        viewZ !== null &&
        typeof tileZ === "number" &&
        (tileZ < viewZ - 1 || tileZ > viewZ + 1)
      ) {
        evictKeys.push(key);
      }
    }

    for (let i = 0; i < evictKeys.length; i++) {
      const removed = tileCache.remove(evictKeys[i]);
      releaseRenderTileResources(removed);
    }

    // OpenLayers menaikkan highWaterMark secara monoton via Math.max(hwm, tileCount * 2).
    // Turunkan kembali batas atas agar memori tidak membengkak permanen setelah zoom/resize besar.
    const desiredCap = Math.min(
      maxCacheSize,
      Math.max(minCacheSize, renderedTiles.length * 2)
    );
    tileCache.highWaterMark = desiredCap;
    const beforeExpire = tileCache.getCount();
    tileCache.expireCache();
    const afterExpire = tileCache.getCount();

    return evictKeys.length + Math.max(0, beforeExpire - afterExpire);
  };

  const setAllowedExtent3857 = (next: MvtExtentTuple | null): MvtExtentUpdateResult => {
    const prev = allowedExtent3857;
    if (extentsEqual(prev, next)) {
      return { changed: false, widened: false, narrowed: false };
    }

    let widened = false;
    let narrowed = false;
    if (prev && !next) {
      widened = true;
    } else if (!prev && next) {
      narrowed = true;
    } else if (prev && next) {
      const prevArea = (prev[2] - prev[0]) * (prev[3] - prev[1]);
      const nextArea = (next[2] - next[0]) * (next[3] - next[1]);
      widened = nextArea > prevArea;
      narrowed = nextArea <= prevArea;
    }

    allowedExtent3857 = next;
    return { changed: true, widened, narrowed };
  };

  /**
   * Menunda pass teks/declutter saat peta sedang di-drag/animasi zoom tanpa mengubah revision layer
   * atau memicu rebuild tile canvas, namun tetap mempertahankan simbol titik ('Image') jika ada.
   */
  const bindLayer = (targetOlLayer: any) => {
    boundLayer = targetOlLayer ?? boundLayer;
    if (cleanupLayerDeferral) {
      cleanupLayerDeferral();
      cleanupLayerDeferral = null;
    }

    const layerObj = getLayerRef();
    if (!layerObj || typeof layerObj.renderDeclutter !== "function") {
      return;
    }

    const origRenderDeclutter = layerObj.renderDeclutter.bind(layerObj);
    layerObj.renderDeclutter = function (frameState: any, layerState: any) {
      const hints = frameState?.viewHints;
      const isMoving = Boolean(
        hints?.[VIEW_HINT.ANIMATING] || hints?.[VIEW_HINT.INTERACTING]
      );

      if (!isMoving || !shouldDeferLabels()) {
        labelsDeferred = false;
        return origRenderDeclutter(frameState, layerState);
      }

      labelsDeferred = true;
      const renderer = layerObj.getRenderer?.();
      const tiles = renderer?.renderedTiles;
      const layerUid = String(layerObj.ol_uid || "");
      const context = renderer?.context;

      if (!renderer || !context || !layerUid || !Array.isArray(tiles) || tiles.length === 0) {
        return;
      }

      const scaledCanvasSize = [context.canvas.width, context.canvas.height];
      const declutterGroup = layerObj.getDeclutter?.();
      const declutterTree = declutterGroup ? frameState.declutter?.[declutterGroup] : undefined;
      const prevAlpha = context.globalAlpha;
      let alphaApplied = false;

      for (let i = 0, ii = tiles.length; i < ii; ++i) {
        const tile = tiles[i];
        const executorGroups = tile.executorGroups?.[layerUid];
        if (!executorGroups) continue;

        for (let j = executorGroups.length - 1; j >= 0; --j) {
          const group = executorGroups[j];
          if (!group.hasExecutors?.(["Image"])) continue;

          if (!alphaApplied) {
            context.globalAlpha = layerState?.opacity ?? 1;
            alphaApplied = true;
          }
          group.execute(
            context,
            scaledCanvasSize,
            renderer.getTileRenderTransform(tile, frameState),
            frameState.viewState.rotation,
            false,
            ["Image"],
            declutterTree ?? null
          );
        }
      }

      if (alphaApplied) {
        context.globalAlpha = prevAlpha;
      }
    };

    cleanupLayerDeferral = () => {
      layerObj.renderDeclutter = origRenderDeclutter;
      labelsDeferred = false;
    };
  };

  if (options.olLayer) {
    bindLayer(options.olLayer);
  }

  return {
    getAllowedExtent3857: () => allowedExtent3857,
    setAllowedExtent3857,
    abortOutOfExtentRequests,
    abortAll,
    pruneCache,
    getPendingCount: () => pendingRequests.size,
    bindLayer,
    hasDeferredLabels: () => labelsDeferred,
    dispose: () => {
      if (cleanupLayerDeferral) {
        cleanupLayerDeferral();
        cleanupLayerDeferral = null;
      }
      abortAll();
      pruneCache({ clearAll: true });
    },
  };
}

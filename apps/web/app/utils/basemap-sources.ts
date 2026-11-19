import { useBasemapStore } from '~/stores/basemap';
import type { BasemapItem } from '~/types/basemap';

export function resolveAbsoluteTileUrl(template: string): string {
  const clean = String(template || '').trim();
  if (!clean) return '';
  if (clean.startsWith('http://') || clean.startsWith('https://')) {
    return clean;
  }
  if (typeof window !== 'undefined' && window.location?.origin) {
    return `${window.location.origin}${clean.startsWith('/') ? '' : '/'}${clean}`;
  }
  return clean;
}

export function resolveBasemapItem(
  basemapOrCode: BasemapItem | string | null | undefined,
  engine: 'openlayers' | 'maplibre' | 'all' = 'all',
): BasemapItem | null {
  if (!basemapOrCode) {
    const store = useBasemapStore();
    return store.getByCode(store.defaultCode, engine);
  }
  if (typeof basemapOrCode === 'string') {
    const raw = basemapOrCode.trim().toLowerCase();
    if (raw === 'none' || raw === 'kanvas') return null;
    return useBasemapStore().getByCode(raw, engine);
  }
  return basemapOrCode;
}

/**
 * Factory sumber basemap OpenLayers dinamis berdasarkan data dari database.
 * Mendukung multi-URL subdomain, fallback tile retry (mis. proxy /rbi-tiles/ ke BIG), dan mode kanvas polos ('none').
 */
export function createOpenLayersBasemapSource(
  basemapOrCode: BasemapItem | string | null | undefined,
  olModules: { XYZ: any; OSM?: any },
): any | null {
  const item = resolveBasemapItem(basemapOrCode, 'openlayers');
  if (!item) return null;

  const opts: Record<string, any> = {
    attributions: item.attribution || undefined,
    maxZoom: item.max_zoom ?? 19,
    minZoom: item.min_zoom ?? 0,
    crossOrigin: item.cross_origin ?? 'anonymous',
  };

  if (Array.isArray(item.tile_urls) && item.tile_urls.length > 0) {
    opts.urls = item.tile_urls;
  } else {
    opts.url = item.url_template;
  }

  // Jika ada fallback_url_template (mis. proxy lokal /rbi-tiles/ -> direct BIG endpoint)
  if (item.fallback_url_template) {
    const primaryPrefix = item.url_template.split('{')[0] || '';
    const fallbackPrefix = item.fallback_url_template.split('{')[0] || '';
    if (primaryPrefix && fallbackPrefix) {
      opts.tileLoadFunction = (imageTile: any, src: string) => {
        const img = imageTile.getImage() as HTMLImageElement;
        let hasRetried = false;
        img.onerror = () => {
          if (!hasRetried && src.includes(primaryPrefix)) {
            hasRetried = true;
            img.src = src.replace(primaryPrefix, fallbackPrefix);
          }
        };
        img.src = src;
      };
    }
  }

  return new olModules.XYZ(opts);
}

/**
 * Factory sumber & layer raster MapLibreGLJS dinamis berdasarkan data dari database.
 */
export function buildDynamicMaplibreBasemapSpec(
  basemapOrCode: BasemapItem | string | null | undefined,
  options: { baseOpacity?: number } = {},
): { sources: Record<string, any>; layers: any[] } {
  const baseOpacity = options.baseOpacity ?? 1;
  const item = resolveBasemapItem(basemapOrCode, 'maplibre');
  if (!item) {
    return { sources: {}, layers: [] };
  }

  const rawTiles =
    Array.isArray(item.tile_urls) && item.tile_urls.length > 0
      ? item.tile_urls
      : [item.url_template];

  const tiles = rawTiles
    .map((u) => {
      // Pada SSR/tanpa window.location, gunakan fallback absolut bila template relatif
      if (u.startsWith('/') && (typeof window === 'undefined' || !window.location?.origin) && item.fallback_url_template) {
        return item.fallback_url_template;
      }
      return resolveAbsoluteTileUrl(u);
    })
    .filter(Boolean);

  return {
    sources: {
      basemap: {
        type: 'raster',
        tiles,
        tileSize: item.tile_size ?? 256,
        maxzoom: item.max_zoom ?? 19,
        minzoom: item.min_zoom ?? 0,
        attribution: item.attribution || '',
      },
    },
    layers: [
      {
        id: 'basemap-layer',
        type: 'raster',
        source: 'basemap',
        minzoom: item.min_zoom ?? 0,
        maxzoom: Math.min(22, (item.max_zoom ?? 19) + 1),
        paint: { 'raster-opacity': baseOpacity },
      },
    ],
  };
}

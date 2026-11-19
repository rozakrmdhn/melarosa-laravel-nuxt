import { buildDynamicMaplibreBasemapSpec } from '~/utils/basemap-sources';

export type MaplibreBasemapKey = string;

export interface MaplibreBasemapOption {
  key: MaplibreBasemapKey;
  label: string;
}

export const MAPLIBRE_BASEMAP_OPTIONS: MaplibreBasemapOption[] = [
  { key: 'esri', label: 'Esri' },
  { key: 'rbi', label: 'RBI' },
  { key: 'hybrid', label: 'Satelit Hybrid' },
];

export function resolveRbiTileTemplate(): string {
  if (typeof window !== 'undefined' && window.location?.origin) {
    return `${window.location.origin}/rbi-tiles/{z}/{y}/{x}`;
  }
  return 'https://geoservices.big.go.id/rbi/rest/services/BASEMAP/Rupabumi_Indonesia/MapServer/tile/{z}/{y}/{x}';
}

export function buildMaplibreBasemapSpec(
  key: MaplibreBasemapKey,
  options: { baseOpacity?: number } = {},
): { sources: Record<string, any>; layers: any[] } {
  return buildDynamicMaplibreBasemapSpec(key, options);
}

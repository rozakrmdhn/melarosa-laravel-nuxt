export type BasemapPreviewStyle =
  | 'esri'
  | 'rbi'
  | 'osm'
  | 'satellite'
  | 'aerial'
  | 'light'
  | 'dark'
  | 'topo'
  | 'none';

export type BasemapVariant = 'mini' | 'medium' | 'interactive';
export type BasemapEngine = 'openlayers' | 'maplibre' | 'all';

export interface BasemapItem {
  id: string;
  kode: string;
  nama: string;
  label_singkat: string;
  tagline?: string | null;
  provider?: string | null;
  tipe: 'raster' | 'vector';
  url_template: string;
  fallback_url_template?: string | null;
  tile_urls?: string[] | null;
  attribution?: string | null;
  thumbnail_url?: string | null;
  preview_style: BasemapPreviewStyle;
  min_zoom: number;
  max_zoom: number;
  tile_size: number;
  cross_origin?: string | null;
  supports_openlayers: boolean;
  supports_maplibre: boolean;
  is_default: boolean;
  is_active: boolean;
  sort_order: number;
  updater?: { uuid: string; name: string } | null;
  updated_at?: string;
}

export const BASEMAP_PREVIEW_STYLE_OPTIONS: Array<{ label: string; value: BasemapPreviewStyle }> = [
  { label: 'Topografi (Esri)', value: 'esri' },
  { label: 'Rupabumi (RBI)', value: 'rbi' },
  { label: 'Peta Jalan (OSM)', value: 'osm' },
  { label: 'Citra Satelit', value: 'satellite' },
  { label: 'Foto Udara / Hybrid', value: 'aerial' },
  { label: 'Kanvas Terang (Light)', value: 'light' },
  { label: 'Kanvas Gelap (Dark)', value: 'dark' },
  { label: 'Kontur Topo', value: 'topo' },
];

export const DEFAULT_FALLBACK_BASEMAPS: BasemapItem[] = [
  {
    id: 'default-esri',
    kode: 'esri',
    nama: 'Esri World Topo',
    label_singkat: 'Esri',
    tagline: 'Topografi, elevasi & kontur muka bumi',
    provider: 'Esri / ArcGIS',
    tipe: 'raster',
    url_template:
      'https://services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
    fallback_url_template: null,
    tile_urls: null,
    attribution: '© Esri, HERE, Garmin, FAO, NOAA, USGS',
    thumbnail_url: null,
    preview_style: 'esri',
    min_zoom: 0,
    max_zoom: 19,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: true,
    is_active: true,
    sort_order: 1,
  },
  {
    id: 'default-rbi',
    kode: 'rbi',
    nama: 'Rupabumi Indonesia',
    label_singkat: 'RBI',
    tagline: 'Peta toponimi resmi standar nasional BIG',
    provider: 'Badan Informasi Geospasial',
    tipe: 'raster',
    url_template: '/rbi-tiles/{z}/{y}/{x}',
    fallback_url_template:
      'https://geoservices.big.go.id/rbi/rest/services/BASEMAP/Rupabumi_Indonesia/MapServer/tile/{z}/{y}/{x}',
    tile_urls: null,
    attribution: '© Badan Informasi Geospasial (BIG) - Peta RBI',
    thumbnail_url: null,
    preview_style: 'rbi',
    min_zoom: 0,
    max_zoom: 18,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: 2,
  },
  {
    id: 'default-osm',
    kode: 'osm',
    nama: 'OpenStreetMap',
    label_singkat: 'OSM',
    tagline: 'Peta jalan komunitas global detail',
    provider: 'OSM Community',
    tipe: 'vector',
    url_template: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    fallback_url_template: null,
    tile_urls: null,
    attribution: '© OpenStreetMap contributors',
    thumbnail_url: null,
    preview_style: 'osm',
    min_zoom: 0,
    max_zoom: 19,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: 3,
  },
  {
    id: 'default-satellite',
    kode: 'satellite',
    nama: 'Citra Satelit',
    label_singkat: 'Satelit',
    tagline: 'Citra satelit resolusi tinggi permukaan bumi',
    provider: 'Esri World Imagery',
    tipe: 'raster',
    url_template:
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    fallback_url_template: null,
    tile_urls: null,
    attribution: '© Esri, Maxar, Earthstar Geographics',
    thumbnail_url: null,
    preview_style: 'satellite',
    min_zoom: 0,
    max_zoom: 19,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: 4,
  },
  {
    id: 'default-hybrid',
    kode: 'hybrid',
    nama: 'Satelit Hybrid',
    label_singkat: 'Hybrid',
    tagline: 'Citra satelit disertai label jalan & wilayah',
    provider: 'Google Hybrid',
    tipe: 'raster',
    url_template: 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
    fallback_url_template: null,
    tile_urls: [
      'https://mt0.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
      'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
      'https://mt2.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
      'https://mt3.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
    ],
    attribution: '© Google Satellite Hybrid',
    thumbnail_url: null,
    preview_style: 'aerial',
    min_zoom: 0,
    max_zoom: 20,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: 5,
  },
  {
    id: 'default-carto-light',
    kode: 'carto_light',
    nama: 'Carto Positron Light',
    label_singkat: 'Terang',
    tagline: 'Kanvas terang netral untuk menonjolkan data tematik',
    provider: 'CARTO',
    tipe: 'raster',
    url_template: 'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
    fallback_url_template: null,
    tile_urls: [
      'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
      'https://b.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
      'https://c.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
    ],
    attribution: '© OpenStreetMap contributors © CARTO',
    thumbnail_url: null,
    preview_style: 'light',
    min_zoom: 0,
    max_zoom: 19,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: 6,
  },
  {
    id: 'default-dark',
    kode: 'dark',
    nama: 'Carto Dark Matter',
    label_singkat: 'Gelap',
    tagline: 'Peta dasar gelap kontras tinggi untuk analisis malam',
    provider: 'CARTO',
    tipe: 'raster',
    url_template: 'https://basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}.png',
    fallback_url_template: null,
    tile_urls: null,
    attribution: '© OpenStreetMap contributors © CARTO',
    thumbnail_url: null,
    preview_style: 'dark',
    min_zoom: 0,
    max_zoom: 19,
    tile_size: 256,
    cross_origin: 'anonymous',
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: 7,
  },
];

const LEGACY_CODE_ALIASES: Record<string, string> = {
  topo: 'esri',
  carto: 'carto_light',
  positron: 'carto_light',
  aerial: 'hybrid',
  kanvas: 'none',
};

export function normalizeBasemapAlias(code?: string | null): string {
  const clean = String(code || '').trim().toLowerCase();
  if (!clean) return 'esri';
  return LEGACY_CODE_ALIASES[clean] || clean;
}

export interface RuasProperties {
  id: string;
  kode_ruas: number | null;
  nama_ruas: string;
  desa: string | null;
  kecamatan: string | null;
  panjang: number;
  panjang_meter: number;
  lebar: number | null;
  perkerasan: string | null;
  kondisi: string | null;
  status_awal: string | null;
  status_eksisting: string | null;
  sumber_data: string | null;
  id_desa?: number | null;
  id_kecamatan?: number | null;
  centroid?: any;
  created_at?: string;
  updated_at?: string;
}

export interface SelectedFeature {
  id: string;
  properties: RuasProperties;
  geometry?: any;
}

export interface KondisiBreakdown {
  kondisi: string;
  jumlah: number;
  panjang_km?: number;
  panjang_meter?: number;
}

export interface SpatialSummary {
  ok: boolean;
  total_ruas: number;
  total_panjang_km?: number;
  total_panjang_meter?: number;
  max_kode_ruas?: number;
  next_kode_ruas?: number;
  kondisi: KondisiBreakdown[];
  bbox: [number, number, number, number];
}

export interface ConsoleLog {
  time: string;
  level: "INFO" | "WARN" | "ERROR";
  message: string;
}

export type BasemapType = "osm" | "dark" | "satellite" | "topo";

export interface KecamatanOption {
  id: number;
  nama: string;
}

export interface DesaOption {
  id: number;
  id_kecamatan: number;
  nama: string;
}

export interface DatasetFilterOptions {
  ok: boolean;
  kecamatan: KecamatanOption[];
  desa: DesaOption[];
  kondisi: string[];
  perkerasan: string[];
}

export type SymbologyColorMode = "single" | "kondisi" | "perkerasan";
export type SymbologyLineDash = "solid" | "dashed" | "dotted";
export type SymbologyLabelField = "nama_ruas" | "kode_ruas" | "kondisi" | "panjang";

export interface LayerSymbology {
  colorMode: SymbologyColorMode;
  lineColor: string;
  lineWidth: number;
  lineDash: SymbologyLineDash;
  labelEnabled: boolean;
  labelField: SymbologyLabelField;
  labelFontSize: number;
  labelColor: string;
  labelHaloColor: string;
  labelHaloWidth: number;
  labelMinZoom: number;
}

export const DEFAULT_SYMBOLOGY: LayerSymbology = {
  colorMode: "single",
  lineColor: "#059669",
  lineWidth: 2.5,
  lineDash: "solid",
  labelEnabled: false,
  labelField: "nama_ruas",
  labelFontSize: 11,
  labelColor: "#0f172a",
  labelHaloColor: "#ffffff",
  labelHaloWidth: 3,
  labelMinZoom: 13,
};

export type LayerId = 'infrastruktur-segmen' | 'jalan-poros-desa' | string;

export interface GisLayerItem {
  id: LayerId;
  title: string;
  subtitle?: string;
  sourceType: 'mvt' | 'geojson';
  visible: boolean;
  opacity: number;
  symbology: LayerSymbology;
  isPrimary?: boolean;
  canEditGeometry?: boolean;
  canDelete?: boolean;
  hasFilter?: boolean;
  activeFilterCount?: number;
  metadata?: {
    tipeData?: string;
    totalItem?: number;
    totalPanjangMeter?: number;
    sumberData?: string;
    srs?: string;
  };
  kondisiStats?: Array<{ kondisi: string; jumlah: number; color?: string; panjang_meter?: number }>;
}


export type BasemapKey = "esri" | "rbi" | "osm" | "satellite" | "aerial" | "none" | "dark" | "positron" | "topo";

export type MainTab = "layers" | "infrastruktur" | "geopark" | "dataset";

export type ActiveLayerTool = "none" | "opacity" | "filter" | "info";

export type GeometryType = "line" | "polygon" | "point" | "auto";
export type DashPreset =
  | "solid"
  | "dash"
  | "dot"
  | "dash-dot"
  | "batas-negara"
  | "batas-provinsi"
  | "batas-propinsi"
  | "batas-kabupaten"
  | "batas-kecamatan"
  | "batas-desa";

export interface StyleRule {
  id?: string;
  field: string;
  operator: "equals" | "contains" | "starts_with" | "gt" | "lt";
  value: string | number;
  color?: string;
  width?: number;
  fill_color?: string;
  dash?: string | number[];
}

export interface LayerStyleLabelConfig {
  enabled: boolean;
  field: string;
  min_zoom?: number;
  color?: string;
  halo_color?: string;
  halo_width?: number;
  font_size?: number;
  placement?: "line" | "point";
  orientation?: "horizontal" | "vertical" | "follow_line";
  rotation?: number;
  offset_x?: number;
  offset_y?: number;
  overflow?: boolean;
}

export interface LayerStyleConfig {
  geom_type?: GeometryType;
  color?: string;
  width?: number;
  dash?: DashPreset | number[] | string;
  line_cap?: "round" | "butt" | "square";
  line_join?: "round" | "bevel" | "miter";
  fill_color?: string;
  casing?: boolean;
  casing_color?: string;
  casing_width?: number;
  point_radius?: number;
  point_color?: string;
  point_border_color?: string;
  point_border_width?: number;
  label?: LayerStyleLabelConfig;
  rules?: StyleRule[];
  zoom_scale?: boolean;
  min_zoom?: number | null;
  custom_style_json?: any;
}

export interface ActiveLayerItem {
  id: string;
  title: string;
  name?: string;
  layer_name?: string | null;
  protocol?: string;
  url?: string;
  attribution?: string | null;
  instansi?: string | null;
  description?: string | null;
  source_type?: string | null;
  is_active?: boolean;
  default_visible?: boolean;
  order?: number;
  created_at?: string;
  updated_at?: string;

  // Layer rendering & tool state
  visible: boolean;
  opacity: number;
  expanded: boolean;
  activeTool: ActiveLayerTool;
  disableIdentify?: boolean;

  // WMS / OGC geospatial service parameters
  wmsUrl?: string;
  wmsLayerName?: string;

  // Martin / MVT vector tile parameters
  mvtUrl?: string;
  martinSource?: string;
  style_config?: LayerStyleConfig | null;

  // Filter state
  filterKecamatan?: string | null;
  filterDesa?: string | null;
  filterStatus?: string | null;
  filterExtent?: [number, number, number, number] | null;

  // Geoportal fields
  geoportal_id?: string | null;
  category?: string;
  opd_name?: string | null;
  legend_url?: string | null;
  csw_metadata?: Record<string, any> | null;
  last_synced_at?: string | null;

  // Optional legacy fields for backward compatibility
  format?: string[];
  crs?: string;
  featuresCount?: string;
  agency?: string;
  updateDate?: string;
  icon?: string;
  badgeColor?: string;
  coordinates?: [number, number];
  zoom?: number;
  apiEndpoint?: string;
}

export type BasemapKey = "esri" | "rbi" | "osm" | "satellite" | "aerial" | "none" | "dark" | "positron" | "topo";

export type MainTab = "layers" | "dataset" | "geopark";

export type ActiveLayerTool = "none" | "opacity" | "filter" | "info";

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

  // Filter state
  filterKecamatan?: string | null;
  filterStatus?: string | null;

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

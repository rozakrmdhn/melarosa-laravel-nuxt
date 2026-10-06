export type BasemapKey = "osm" | "satellite" | "positron" | "dark" | "topo";

export type ActiveLayerTool = "none" | "opacity" | "filter" | "info";

export interface ActiveLayerItem {
  id: string;
  title: string;
  name?: string;
  layer_name?: string | null;
  protocol?: string;
  url?: string;
  attribution?: string | null;
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

  // WMS / OGC geospatial service parameters
  wmsUrl?: string;
  wmsLayerName?: string;

  // Filter state
  filterKecamatan?: string | null;
  filterStatus?: string | null;

  // Optional legacy fields for backward compatibility
  category?: string;
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

import type { LayerStyleConfig } from "~/types/map-layers";

export interface LayerItem {
  id: string;
  name: string;
  protocol: string;
  url: string;
  layer_name: string | null;
  is_active: boolean;
  default_visible: boolean;
  opacity: number;
  order: number;
  attribution: string | null;
  description: string | null;
  source_type: string;
  is_synced: boolean;
  style_config?: LayerStyleConfig | null;
  geoportal_id?: string | null;
  category?: string | null;
  opd_name?: string | null;
  legend_url?: string | null;
  csw_metadata?: Record<string, any> | null;
  last_synced_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface SourceGroupInfo {
  key: "geoportal" | "internal" | "external";
  name: string;
  badgeLabel: string;
  badgeColor: "success" | "primary" | "warning" | "neutral" | "info";
  icon: string;
  subLabel: string;
}

export function getProtocolBadgeColor(protocol: string): "success" | "info" | "warning" | "primary" | "neutral" {
  switch ((protocol || "").toLowerCase()) {
    case "wms":
      return "success";
    case "wfs":
      return "info";
    case "xyz":
      return "warning";
    case "mvt":
      return "primary";
    default:
      return "neutral";
  }
}

export function getLayerSourceGroup(layer: LayerItem): SourceGroupInfo {
  const url = (layer.url || "").toLowerCase();
  const st = (layer.source_type || "").toLowerCase();
  const protocol = (layer.protocol || "").toLowerCase();

  if (st === "geoportal" || url.includes("geoportal.bojonegorokab.go.id")) {
    return {
      key: "geoportal",
      name: "Geoportal Bojonegoro",
      badgeLabel: "Geoportal WMS",
      badgeColor: "success",
      icon: "i-lucide-globe-2",
      subLabel: "WMS Service Geoportal",
    };
  }

  if (st === "internal" || st === "martin" || protocol === "mvt" || url.includes("/martin/")) {
    return {
      key: "internal",
      name: "Internal Melarosa",
      badgeLabel: "Martin MVT",
      badgeColor: "primary",
      icon: "i-lucide-server",
      subLabel: "Martin Tile Server MVT",
    };
  }

  return {
    key: "external",
    name: "External GIS",
    badgeLabel: "External WMS",
    badgeColor: "warning",
    icon: "i-lucide-cloud",
    subLabel: "WMS Service Luar Geoportal",
  };
}

export function getHostName(urlStr?: string | null): string {
  if (!urlStr) return "-";
  try {
    const parsed = new URL(urlStr);
    return parsed.hostname;
  } catch {
    return urlStr;
  }
}


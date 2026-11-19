import type { ActiveLayerItem, LayerStyleConfig, StyleRule } from "~/types/map-layers";
import { resolveLineDash } from "./mvt-styler";
import { buildWmsGetLegendUrl, hasWmsCustomStyle } from "./wms-sld";

export type LegendGeomType = "line" | "polygon" | "point";

export interface MartinLegendSwatch {
  geomType: LegendGeomType;
  strokeColor: string;
  strokeWidth: number;
  fillColor?: string;
  dashArray?: number[];
  casingColor?: string;
  pointBorderColor?: string;
  pointRadius?: number;
}

export interface MartinLegendRuleItem {
  key: string;
  label: string;
  field?: string;
  swatch: MartinLegendSwatch;
}

export interface MartinLegendLayerEntry {
  layerId: string;
  layerName: string;
  title: string;
  geomType: LegendGeomType;
  visible: boolean;
  opacity: number;
  stackIndex: number;
  items: MartinLegendRuleItem[];
}

export function isMartinMvtLayer(layer?: ActiveLayerItem | null): boolean {
  if (!layer) return false;
  return Boolean(
    layer.protocol?.toUpperCase() === "MVT" ||
      layer.source_type === "martin" ||
      (layer.source_type === "internal" && !layer.wmsUrl) ||
      layer.mvtUrl ||
      layer.martinSource ||
      (layer.url && layer.url.includes("/martin/"))
  );
}

export function resolveMartinSourceName(layer?: ActiveLayerItem | null): string {
  if (!layer) return "";
  const urlMatch = layer.url?.match(/\/martin\/([^/?#{]+)/i)?.[1];
  return String(layer.martinSource || layer.layer_name || urlMatch || layer.id || "")
    .trim()
    .toLowerCase();
}

export function resolveFriendlyMartinLayerTitle(layer: ActiveLayerItem): string {
  const key = resolveMartinSourceName(layer);
  if (key.includes("infrastruktur_segmen")) return "Infrastruktur Segmen";
  if (key.includes("jalan_porosdesa")) return "Jalan Poros Desa";
  if (key.includes("bataswilayah_kabupaten")) return "Batas Wilayah Kabupaten";
  if (key.includes("bataswilayah_kecamatan")) return "Batas Wilayah Kecamatan";
  if (key.includes("bataswilayah_desa")) return "Batas Wilayah Desa";
  return layer.title || layer.name || layer.layer_name || "Layer Vektor Martin";
}

export function inferLegendGeomType(layer: ActiveLayerItem): LegendGeomType {
  const explicit = layer.style_config?.geom_type;
  if (explicit === "line" || explicit === "polygon" || explicit === "point") {
    return explicit;
  }

  const key = resolveMartinSourceName(layer);
  if (
    key.includes("_ar_") ||
    key.includes("bataswilayah") ||
    key.includes("batas_") ||
    key.includes("area") ||
    key.includes("zona") ||
    key.includes("polaruang")
  ) {
    return "polygon";
  }
  if (
    key.includes("_pt_") ||
    key.includes("titik") ||
    key.includes("point") ||
    key.includes("poi") ||
    key.includes("kantor") ||
    key.includes("jembatan")
  ) {
    return "point";
  }
  return "line";
}

export function getLegendGeomLabel(geomType: LegendGeomType): string {
  if (geomType === "polygon") return "Poligon";
  if (geomType === "point") return "Titik";
  return "Garis";
}

function formatRuleValueLabel(rule: StyleRule): string {
  if (rule.label && String(rule.label).trim() !== "") {
    return String(rule.label).trim();
  }
  if (rule.operator === "between") {
    return `${rule.min_value ?? 0} - ${rule.max_value ?? 0}`;
  }
  const raw = String(rule.value ?? "").trim();
  if (!raw) return "Aturan Khusus";

  switch (rule.operator) {
    case "gt":
      return `> ${raw}`;
    case "lt":
      return `< ${raw}`;
    case "starts_with":
      return `${raw}*`;
    case "contains":
    case "equals":
    default:
      return raw.replace(/_/g, " ");
  }
}

function buildRuleSwatch(
  geomType: LegendGeomType,
  styleConfig: LayerStyleConfig,
  rule?: StyleRule
): MartinLegendSwatch {
  const strokeColor =
    rule?.color ||
    (geomType === "point" ? styleConfig.point_color : undefined) ||
    styleConfig.color ||
    (geomType === "line" ? "#2563eb" : "#475569");

  const strokeWidth = Number(rule?.width ?? styleConfig.width ?? (geomType === "line" ? 2.5 : 1.5));
  const fillColor =
    rule?.fill_color ||
    (rule?.color && geomType === "polygon" ? rule.color : undefined) ||
    styleConfig.fill_color ||
    (geomType === "polygon" ? "rgba(59, 130, 246, 0.14)" : undefined);

  const dashArray = resolveLineDash((rule?.dash as string | number[] | undefined) ?? styleConfig.dash);

  return {
    geomType,
    strokeColor,
    strokeWidth,
    fillColor,
    dashArray,
    casingColor: styleConfig.casing ? styleConfig.casing_color || "rgba(148, 163, 184, 0.35)" : undefined,
    pointBorderColor: styleConfig.point_border_color || "#ffffff",
    pointRadius: Number(styleConfig.point_radius ?? 5),
  };
}

function buildFallbackItems(layer: ActiveLayerItem, geomType: LegendGeomType): MartinLegendRuleItem[] {
  const sourceKey = resolveMartinSourceName(layer);

  if (sourceKey === "bataswilayah_kabupaten") {
    return [
      {
        key: `${layer.id}::fb-kab`,
        label: "Batas Kabupaten",
        swatch: {
          geomType: "polygon",
          strokeColor: "#475569",
          strokeWidth: 2,
          fillColor: "rgba(71, 85, 105, 0.04)",
          dashArray: [12, 4, 2, 4, 2, 4],
          casingColor: "rgba(148, 163, 184, 0.35)",
        },
      },
    ];
  }

  if (sourceKey.includes("infrastruktur_segmen") || sourceKey.includes("jalan_porosdesa")) {
    const isSegmen = sourceKey.includes("infrastruktur_segmen");
    return [
      {
        key: `${layer.id}::baik`,
        label: "Kondisi Baik",
        field: "kondisi",
        swatch: { geomType: "line", strokeColor: "#10b981", strokeWidth: isSegmen ? 3.5 : 2.5 },
      },
      {
        key: `${layer.id}::sedang`,
        label: "Sedang",
        field: "kondisi",
        swatch: { geomType: "line", strokeColor: "#f59e0b", strokeWidth: isSegmen ? 3.5 : 2.5 },
      },
      {
        key: `${layer.id}::rusak-ringan`,
        label: "Rusak Ringan",
        field: "kondisi",
        swatch: { geomType: "line", strokeColor: "#f97316", strokeWidth: isSegmen ? 3.5 : 2.5 },
      },
      {
        key: `${layer.id}::rusak-berat`,
        label: "Rusak Berat",
        field: "kondisi",
        swatch: { geomType: "line", strokeColor: "#ef4444", strokeWidth: isSegmen ? 3.5 : 2.5 },
      },
    ];
  }

  if (geomType === "polygon") {
    return [
      {
        key: `${layer.id}::fb-poly`,
        label: sourceKey.includes("batas") ? "Batas Administrasi" : "Area Wilayah",
        swatch: {
          geomType: "polygon",
          strokeColor: "#0284c7",
          strokeWidth: 1.75,
          fillColor: "rgba(2, 132, 199, 0.12)",
        },
      },
    ];
  }

  if (geomType === "point") {
    return [
      {
        key: `${layer.id}::fb-point`,
        label: "Titik Lokasi",
        swatch: {
          geomType: "point",
          strokeColor: "#0284c7",
          strokeWidth: 2,
          pointBorderColor: "#ffffff",
          pointRadius: 5,
        },
      },
    ];
  }

  return [
    {
      key: `${layer.id}::fb-line`,
      label: "Trase Garis",
      swatch: {
        geomType: "line",
        strokeColor: "#2563eb",
        strokeWidth: 2.5,
      },
    },
  ];
}

export function buildMartinLayerLegend(
  layer: ActiveLayerItem,
  stackIndex = 0
): MartinLegendLayerEntry {
  const geomType = inferLegendGeomType(layer);
  const styleConfig = layer.style_config;
  const sourceName = resolveMartinSourceName(layer);

  let items: MartinLegendRuleItem[] = [];
  if (
    styleConfig &&
    styleConfig.symbol_mode !== "single" &&
    Array.isArray(styleConfig.rules) &&
    styleConfig.rules.length > 0
  ) {
    items = styleConfig.rules.map((rule, idx) => ({
      key: `${layer.id}::rule::${rule.id || idx}`,
      label: formatRuleValueLabel(rule),
      field: rule.field,
      swatch: buildRuleSwatch(geomType, styleConfig, rule),
    }));
  } else if (styleConfig) {
    items = [
      {
        key: `${layer.id}::base`,
        label:
          sourceName === "bataswilayah_kabupaten"
            ? "Batas Kabupaten"
            : geomType === "polygon"
              ? "Area Poligon"
              : geomType === "point"
                ? "Titik Lokasi"
                : "Trase Garis",
        swatch: buildRuleSwatch(geomType, styleConfig),
      },
    ];
  } else {
    items = buildFallbackItems(layer, geomType);
  }

  return {
    layerId: layer.id,
    layerName: sourceName,
    title: resolveFriendlyMartinLayerTitle(layer),
    geomType,
    visible: layer.visible !== false,
    opacity: typeof layer.opacity === "number" ? layer.opacity : 1,
    stackIndex,
    items,
  };
}

export function resolveEffectiveWmsLegendUrl(layer?: {
  protocol?: string | null;
  source_type?: string | null;
  url?: string | null;
  wmsUrl?: string | null;
  mvtUrl?: string | null;
  martinSource?: string | null;
  layer_name?: string | null;
  wmsLayerName?: string | null;
  legend_url?: string | null;
  style_config?: LayerStyleConfig | null;
} | null): string | null {
  if (!layer) return null;
  const proto = (layer.protocol || "").toUpperCase();
  if (isMartinMvtLayer(layer as ActiveLayerItem) || (proto && !proto.includes("WMS"))) {
    return layer.legend_url || null;
  }
  return buildWmsGetLegendUrl(
    layer.wmsUrl || layer.url,
    layer.wmsLayerName || layer.layer_name,
    layer.style_config,
    layer.legend_url
  );
}

export function isCustomStyledWmsLayer(layer?: ActiveLayerItem | null): boolean {
  if (!layer || isMartinMvtLayer(layer)) return false;
  const proto = (layer.protocol || "").toUpperCase();
  if (proto && proto !== "WMS" && !proto.includes("WMS")) return false;
  return hasWmsCustomStyle(layer.style_config);
}

export function buildMartinLegendEntries(
  activeLayers: ActiveLayerItem[] | null | undefined,
  options: { includeHidden?: boolean } = {}
): MartinLegendLayerEntry[] {
  const list = activeLayers || [];
  const entries: MartinLegendLayerEntry[] = [];

  list.forEach((layer, idx) => {
    if (!isMartinMvtLayer(layer) && !isCustomStyledWmsLayer(layer)) return;
    if (!options.includeHidden && layer.visible === false) return;
    entries.push(buildMartinLayerLegend(layer, idx + 1));
  });

  return entries;
}

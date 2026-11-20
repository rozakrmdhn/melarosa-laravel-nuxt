import type { ActiveLayerItem, LabelStyleRule, LayerStyleConfig, StyleRule } from "~/types/map-layers";
import { resolveLineDash } from "~/utils/mvt-styler";

const MERCATOR_RADIUS = 6378137;
const MAX_MERCATOR_LAT = 85.05112878;

export interface MaplibreFilterExtent4326 {
  bbox4326: [number, number, number, number];
  polygonGeoJson: {
    type: "Feature";
    properties: Record<string, never>;
    geometry: {
      type: "Polygon";
      coordinates: number[][][];
    };
  };
}

/**
 * Convert legacy OpenLayers 256px-world zoom to MapLibre 512px-world zoom
 * so visual scale, URL @lat,lng,z bookmarks, and layer min_zoom match 1:1.
 */
export function toMaplibreZoom(z256: number): number {
  return Math.max(0, Math.min(22, Number(z256) - 1));
}

export function toStandardZoom(zMaplibre: number): number {
  return Math.max(0, Math.min(22, Number(zMaplibre) + 1));
}

export function lonLat4326ToMercator(coord: [number, number]): [number, number] {
  const lon = Number(coord[0]);
  const lat = Math.max(-MAX_MERCATOR_LAT, Math.min(MAX_MERCATOR_LAT, Number(coord[1])));
  const x = (lon * Math.PI * MERCATOR_RADIUS) / 180;
  const y = Math.log(Math.tan(Math.PI / 4 + (lat * Math.PI) / 360)) * MERCATOR_RADIUS;
  return [x, y];
}

export function mercator3857To4326Bbox(raw: readonly number[]): [number, number, number, number] | null {
  if (!Array.isArray(raw) || raw.length !== 4 || raw.some((n) => !Number.isFinite(Number(n)))) {
    return null;
  }
  const [a, b, c, d] = raw.map(Number) as [number, number, number, number];
  if (Math.abs(a) <= 180.001 && Math.abs(c) <= 180.001 && Math.abs(b) <= 90.001 && Math.abs(d) <= 90.001) {
    return [Math.min(a, c), Math.min(b, d), Math.max(a, c), Math.max(b, d)];
  }
  const toLon = (x: number) => Math.max(-180, Math.min(180, (x * 180) / (Math.PI * MERCATOR_RADIUS)));
  const toLat = (y: number) =>
    Math.max(
      -MAX_MERCATOR_LAT,
      Math.min(MAX_MERCATOR_LAT, ((2 * Math.atan(Math.exp(y / MERCATOR_RADIUS)) - Math.PI / 2) * 180) / Math.PI)
    );
  const minLon = toLon(Math.min(a, c));
  const maxLon = toLon(Math.max(a, c));
  const minLat = toLat(Math.min(b, d));
  const maxLat = toLat(Math.max(b, d));
  if (![minLon, minLat, maxLon, maxLat].every(Number.isFinite)) return null;
  return [minLon, minLat, maxLon, maxLat];
}

function buildFilterExtentInfo(rawExtent: unknown): MaplibreFilterExtent4326 | null {
  if (!Array.isArray(rawExtent) || rawExtent.length !== 4) return null;
  const bbox4326 = mercator3857To4326Bbox(rawExtent);
  if (!bbox4326) return null;
  const padLon = Math.max((bbox4326[2] - bbox4326[0]) * 0.12, 0.012);
  const padLat = Math.max((bbox4326[3] - bbox4326[1]) * 0.12, 0.012);
  const [minX, minY, maxX, maxY]: [number, number, number, number] = [
    bbox4326[0] - padLon,
    bbox4326[1] - padLat,
    bbox4326[2] + padLon,
    bbox4326[3] + padLat,
  ];
  return {
    bbox4326,
    polygonGeoJson: {
      type: "Feature",
      properties: {},
      geometry: {
        type: "Polygon",
        coordinates: [
          [
            [minX, minY],
            [maxX, minY],
            [maxX, maxY],
            [minX, maxY],
            [minX, minY],
          ],
        ],
      },
    },
  };
}

function sanitizeLayerId(raw: string): string {
  return String(raw || "layer").replace(/[^a-zA-Z0-9_-]/g, "_");
}

function getPropertyVariantGets(fieldName: string): any[][] {
  const clean = String(fieldName || "").trim();
  const variants = Array.from(new Set([clean, clean.toLowerCase(), clean.toUpperCase()])).filter(Boolean);
  return variants.map((v) => ["get", v]);
}

function getPropertyCoalesceExpr(fieldName: string): any[] {
  const gets = getPropertyVariantGets(fieldName);
  if (gets.length === 1) {
    return gets[0]!;
  }
  return ["coalesce", ...gets];
}

function buildMultiFieldLowerExpr(fields: readonly string[]): any[] {
  return ["downcase", ["to-string", ["coalesce", ...fields.map((f) => ["get", f]), ""]]];
}

function hasAnyFieldExpr(fields: readonly string[]): any[] {
  return [
    "any",
    ...fields.map((f) => ["all", ["has", f], ["!=", ["get", f], null], ["!=", ["to-string", ["get", f]], ""]]),
  ];
}

function normalizeWilayahToken(val: string, prefixRe: RegExp): string {
  return val
    .trim()
    .toLowerCase()
    .replace(prefixRe, "")
    .replace(/\s*\(kota\)\s*$/i, "")
    .replace(/\s+/g, " ")
    .trim();
}

/**
 * Translate OpenLayers passesClientFilters (kecamatan, desa, status, extent fallback)
 * into a single native MapLibre GL filter expression.
 */
export function buildMvtClientFilterExpr(
  layer: Partial<ActiveLayerItem>,
  geometryCondition?: any[]
): any[] | undefined {
  const clauses: any[] = [];
  if (geometryCondition) {
    clauses.push(geometryCondition);
  }

  const normLayerHint = String(layer.martinSource || layer.layer_name || layer.url || layer.id || "").toLowerCase();
  const isKabupaten =
    normLayerHint.includes("bataswilayah_kabupaten") ||
    String(layer.id || "") === "01a15e28-a042-71a0-b111-ed7d9256e5bf";

  const extentInfo = buildFilterExtentInfo(layer.filterExtent);
  const KEC_FIELDS = ["nama_kecamatan", "kecamatan", "WADMKC", "wadmkc", "KECAMATAN"] as const;
  const DESA_FIELDS = ["nama_desa", "desa", "NAMOBJ", "namobj", "DESA"] as const;
  const STATUS_FIELDS = ["kondisi", "status", "KONDISI", "STATUS"] as const;

  const targetKec = String(layer.filterKecamatan || "").trim().toLowerCase();
  const rawKecId = layer.filterKecamatanId;
  const targetKecId =
    rawKecId !== null && rawKecId !== undefined && Number.isFinite(Number(rawKecId))
      ? Number(rawKecId)
      : null;

  if (!isKabupaten && ((targetKec && targetKec !== "all") || targetKecId !== null)) {
    const cleanTarget = targetKec && targetKec !== "all"
      ? normalizeWilayahToken(targetKec, /^(kecamatan|kec\.)\s+/i)
      : "";
    const hasIdKecExpr: any[] = [
      "all",
      ["has", "id_kecamatan"],
      ["!=", ["get", "id_kecamatan"], null],
      ["!=", ["to-string", ["get", "id_kecamatan"]], ""],
    ];
    const kecIdMatchExpr: any[] | boolean =
      targetKecId !== null
        ? ["==", ["to-number", ["get", "id_kecamatan"], -999], targetKecId]
        : false;

    if (cleanTarget) {
      const kecStrExpr = buildMultiFieldLowerExpr(KEC_FIELDS);
      const kecMatchExpr: any[] = [
        "any",
        ["==", kecStrExpr, cleanTarget],
        ["==", kecStrExpr, `kec. ${cleanTarget}`],
        ["==", kecStrExpr, `kecamatan ${cleanTarget}`],
        ["==", kecStrExpr, `${cleanTarget} (kota)`],
        ["!=", ["index-of", cleanTarget, kecStrExpr], -1],
      ];

      //bataswilayah_desa uses integer id_kecamatan instead of a string kecamatan column
      const spatialFallback: any[] | boolean = extentInfo
        ? ["within", extentInfo.polygonGeoJson]
        : false;

      clauses.push([
        "case",
        hasAnyFieldExpr(KEC_FIELDS),
        kecMatchExpr,
        hasIdKecExpr,
        kecIdMatchExpr,
        spatialFallback,
      ]);
    } else if (targetKecId !== null) {
      clauses.push([
        "case",
        hasIdKecExpr,
        kecIdMatchExpr,
        extentInfo ? ["within", extentInfo.polygonGeoJson] : false,
      ]);
    }
  }

  const targetDesa = String(layer.filterDesa || "").trim().toLowerCase();
  if (!isKabupaten && targetDesa && targetDesa !== "all") {
    const cleanTarget = normalizeWilayahToken(targetDesa, /^(desa|kelurahan|kel\.)\s+/i);
    if (cleanTarget) {
      const desaStrExpr = buildMultiFieldLowerExpr(DESA_FIELDS);
      const desaMatchExpr: any[] = [
        "any",
        ["==", desaStrExpr, cleanTarget],
        ["==", desaStrExpr, `desa ${cleanTarget}`],
        ["==", desaStrExpr, `kelurahan ${cleanTarget}`],
        ["==", desaStrExpr, `kel. ${cleanTarget}`],
        ["!=", ["index-of", cleanTarget, desaStrExpr], -1],
      ];

      const spatialFallback: any[] | boolean = extentInfo
        ? ["within", extentInfo.polygonGeoJson]
        : false;

      clauses.push([
        "case",
        hasAnyFieldExpr(DESA_FIELDS),
        desaMatchExpr,
        spatialFallback,
      ]);
    }
  }

  const targetStatus = String(layer.filterStatus || "").trim().toLowerCase();
  if (targetStatus && targetStatus !== "all") {
    const statusStrExpr = buildMultiFieldLowerExpr(STATUS_FIELDS);
    clauses.push(["!=", ["index-of", targetStatus, statusStrExpr], -1]);
  }

  if (clauses.length === 0) return undefined;
  if (clauses.length === 1) return clauses[0];
  return ["all", ...clauses];
}

export function buildRuleConditionExpr(rule: {
  field: string;
  operator: StyleRule["operator"];
  value?: string | number;
  min_value?: number | null;
  max_value?: number | null;
}): any[] | null {
  if (!rule.field) return null;
  const propExpr = getPropertyCoalesceExpr(rule.field);

  if (rule.operator === "between") {
    const minVal = Number(rule.min_value);
    const maxVal = Number(rule.max_value);
    if (!Number.isFinite(minVal) || !Number.isFinite(maxVal)) return null;
    const low = Math.min(minVal, maxVal);
    const high = Math.max(minVal, maxVal);
    return [
      "all",
      ["!=", propExpr, null],
      ["!=", ["to-string", propExpr], ""],
      [">=", ["to-number", propExpr], low],
      ["<=", ["to-number", propExpr], high],
    ];
  }

  if (rule.value === undefined || rule.value === "") return null;
  const ruleStr = String(rule.value).trim().toLowerCase();
  const strExpr = ["downcase", ["to-string", ["coalesce", propExpr, ""]]];

  switch (rule.operator) {
    case "equals":
      return ["all", ["!=", propExpr, null], ["==", strExpr, ruleStr]];
    case "starts_with":
      return ["all", ["!=", propExpr, null], ["==", ["index-of", ruleStr, strExpr], 0]];
    case "gt": {
      const n = Number(rule.value);
      return Number.isFinite(n)
        ? ["all", ["!=", propExpr, null], ["!=", ["to-string", propExpr], ""], [">", ["to-number", propExpr], n]]
        : null;
    }
    case "lt": {
      const n = Number(rule.value);
      return Number.isFinite(n)
        ? ["all", ["!=", propExpr, null], ["!=", ["to-string", propExpr], ""], ["<", ["to-number", propExpr], n]]
        : null;
    }
    case "contains":
    default:
      return ["all", ["!=", propExpr, null], ["!=", ["index-of", ruleStr, strExpr], -1]];
  }
}

function resolveMaplibreDashArray(dash: string | number[] | undefined, strokeWidth: number): number[] | undefined {
  const pxDash = resolveLineDash(dash);
  if (!pxDash || pxDash.length === 0) return undefined;
  const divisor = Math.max(1.5, strokeWidth || 2);
  return pxDash.map((v) => Math.max(0.45, Number((v / divisor).toFixed(2))));
}

function buildCaseExprFromRules<T>(
  rules: StyleRule[],
  extractVal: (rule: StyleRule) => T | undefined,
  fallback: any
): any {
  const branches: any[] = [];
  for (const rule of rules) {
    const val = extractVal(rule);
    if (val === undefined) continue;
    const cond = buildRuleConditionExpr({
      field: rule.field,
      operator: rule.operator,
      value: rule.value,
      min_value: rule.min_value,
      max_value: rule.max_value,
    });
    if (cond) {
      branches.push(cond, val);
    }
  }
  if (branches.length === 0) return fallback;
  return ["case", ...branches, fallback];
}

function buildZoomScaledWidthExpr(baseWidthExpr: any, zoomScaleEnabled: boolean): any {
  if (!zoomScaleEnabled) return baseWidthExpr;
  return [
    "interpolate",
    ["linear"],
    ["zoom"],
    9,
    ["max", ["*", baseWidthExpr, 0.75], 1],
    12.5,
    ["*", baseWidthExpr, 1.15],
    14,
    ["*", baseWidthExpr, 1.4],
  ];
}

function buildLabelTextColor(hexOrCss: string, opacity: number): string {
  if (opacity >= 0.99 || !hexOrCss.startsWith("#") || hexOrCss.length !== 7) {
    return hexOrCss;
  }
  const r = parseInt(hexOrCss.slice(1, 3), 16);
  const g = parseInt(hexOrCss.slice(3, 5), 16);
  const b = parseInt(hexOrCss.slice(5, 7), 16);
  return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

function buildLabelSubLayer(
  id: string,
  sourceId: string,
  sourceLayerName: string,
  layer: Partial<ActiveLayerItem>,
  styleConfig: LayerStyleConfig | null | undefined,
  layerOpacity: number,
  isDark: boolean,
  isSegmenFallback: boolean
): any | null {
  if (!styleConfig) {
    // Fallback label for infrastructure / porous roads above standard zoom 13.5 (12.5 MapLibre)
    const isKabupaten =
      String(layer.layer_name || "").toLowerCase() === "bataswilayah_kabupaten" ||
      layer.id === "01a15e28-a042-71a0-b111-ed7d9256e5bf";
    if (isKabupaten) return null;

    const textColor = isSegmenFallback ? (isDark ? "#6ee7b7" : "#064e3b") : (isDark ? "#93c5fd" : "#1e3a8a");
    const haloColor = isDark ? "#0f172a" : "#ffffff";
    const fieldExpr: any[] = [
      "coalesce",
      ["get", isSegmenFallback ? "namobj" : "nama_ruas"],
      ["get", "nama_ruas"],
      ["get", "namobj"],
      "",
    ];
    const filterExpr = buildMvtClientFilterExpr(layer, ["all", ["!=", fieldExpr, ""], ["!=", fieldExpr, "-"]]);

    return {
      id,
      type: "symbol",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: 12.5,
      ...(filterExpr ? { filter: filterExpr } : {}),
      layout: {
        visibility: layer.visible !== false ? "visible" : "none",
        "symbol-placement": "line",
        "text-field": ["to-string", fieldExpr],
        "text-font": ["Inter", "sans-serif"],
        "text-size": 11,
        "text-allow-overlap": false,
      },
      paint: {
        "text-color": textColor,
        "text-halo-color": haloColor,
        "text-halo-width": 2,
        "text-opacity": layerOpacity,
      },
    };
  }

  const lbl = styleConfig.label;
  if (!lbl?.enabled) return null;

  const defaultField = (lbl.field || "").trim();
  const isRuleBased = lbl.mode === "rule_based";
  const lblRules: LabelStyleRule[] = isRuleBased ? (lbl.rules || []) : [];

  const ruleTextBranches: any[] = [];
  const ruleColorBranches: any[] = [];
  const ruleHaloBranches: any[] = [];
  const ruleHaloWidthBranches: any[] = [];
  const ruleSizeBranches: any[] = [];
  const ruleOpacityBranches: any[] = [];
  const matchedRuleAnyConds: any[] = [];
  let minRuleZoom = toMaplibreZoom(lbl.min_zoom ?? 13);

  const defaultTextColor = lbl.color || (isDark ? "#f1f5f9" : "#0f172a");
  const defaultHaloColor = lbl.halo_color || (isDark ? "#0f172a" : "#ffffff");
  const defaultAlpha = Number.isFinite(Number(lbl.opacity)) ? Math.max(0, Math.min(1, Number(lbl.opacity))) : 1;

  for (const lr of lblRules) {
    const cond = buildRuleConditionExpr({
      field: lr.filter_field,
      operator: lr.operator,
      value: lr.value,
      min_value: lr.min_value,
      max_value: lr.max_value,
    });
    const fieldToUse = (lr.label_field || defaultField).trim();
    if (!cond || !fieldToUse) continue;

    if (typeof lr.min_zoom === "number" && Number.isFinite(lr.min_zoom)) {
      minRuleZoom = Math.min(minRuleZoom, toMaplibreZoom(lr.min_zoom));
    }

    const alpha = Number.isFinite(Number(lr.opacity)) ? Math.max(0, Math.min(1, Number(lr.opacity))) : defaultAlpha;
    const ruleMinZ = toMaplibreZoom(lr.min_zoom ?? lbl.min_zoom ?? 13);

    matchedRuleAnyConds.push(["all", cond, [">=", ["zoom"], ruleMinZ]]);
    ruleTextBranches.push(
      cond,
      ["to-string", ["coalesce", ...getPropertyVariantGets(fieldToUse), ["get", "namobj"], ["get", "nama_ruas"], ""]]
    );
    ruleColorBranches.push(cond, buildLabelTextColor(lr.color || defaultTextColor, alpha));
    ruleHaloBranches.push(cond, buildLabelTextColor(lr.halo_color || defaultHaloColor, alpha));
    ruleHaloWidthBranches.push(cond, Number(lr.halo_width ?? lbl.halo_width ?? 2.5));
    ruleSizeBranches.push(cond, Number(lr.font_size ?? lbl.font_size ?? 11));
    ruleOpacityBranches.push(cond, layerOpacity * alpha);
  }

  if (isRuleBased && matchedRuleAnyConds.length === 0) return null;
  if (!isRuleBased && !defaultField) return null;

  const baseFieldExpr: any[] = defaultField
    ? [
        "to-string",
        [
          "coalesce",
          ["get", defaultField],
          ["get", defaultField.toLowerCase()],
          ["get", defaultField.toUpperCase()],
          ["get", "namobj"],
          ["get", "nama_ruas"],
          "",
        ],
      ]
    : ["literal", ""];

  const textFieldExpr = ruleTextBranches.length > 0 ? ["case", ...ruleTextBranches, baseFieldExpr] : baseFieldExpr;
  const textColorExpr =
    ruleColorBranches.length > 0
      ? ["case", ...ruleColorBranches, buildLabelTextColor(defaultTextColor, defaultAlpha)]
      : buildLabelTextColor(defaultTextColor, defaultAlpha);
  const haloColorExpr =
    ruleHaloBranches.length > 0
      ? ["case", ...ruleHaloBranches, buildLabelTextColor(defaultHaloColor, defaultAlpha)]
      : buildLabelTextColor(defaultHaloColor, defaultAlpha);
  const haloWidthExpr =
    ruleHaloWidthBranches.length > 0
      ? ["case", ...ruleHaloWidthBranches, Number(lbl.halo_width ?? 2.5)]
      : Number(lbl.halo_width ?? 2.5);
  const textSizeExpr =
    ruleSizeBranches.length > 0
      ? ["case", ...ruleSizeBranches, Number(lbl.font_size ?? 11)]
      : Number(lbl.font_size ?? 11);
  const textOpacityExpr =
    ruleOpacityBranches.length > 0
      ? ["case", ...ruleOpacityBranches, layerOpacity * defaultAlpha]
      : layerOpacity * defaultAlpha;

  const isLineGeom = styleConfig.geom_type === "line";
  const placement = lbl.placement || (isLineGeom ? "line" : "point");
  const familyRaw = lbl.font_family || "SansSerif";
  const fontStack =
    familyRaw === "Serif"
      ? ["Georgia", "serif"]
      : familyRaw === "Monospace"
        ? ["monospace"]
        : familyRaw === "Arial"
          ? ["Arial", "sans-serif"]
          : ["Inter", "sans-serif"];

  let textRotate = 0;
  if (placement === "point") {
    if (lbl.orientation === "vertical") {
      textRotate = 90;
    } else if (typeof lbl.rotation === "number" && Number.isFinite(lbl.rotation)) {
      textRotate = lbl.rotation;
    }
  }

  const labelValidityConds: any[] = [["!=", textFieldExpr, ""], ["!=", textFieldExpr, "-"]];
  if (isRuleBased && matchedRuleAnyConds.length > 0) {
    labelValidityConds.push(["any", ...matchedRuleAnyConds]);
  }
  const filterExpr = buildMvtClientFilterExpr(layer, ["all", ...labelValidityConds]);

  const offsetXEm = Number(lbl.offset_x ?? 0) / 11;
  const offsetYEm = Number(lbl.offset_y ?? 0) / 11;

  return {
    id,
    type: "symbol",
    source: sourceId,
    "source-layer": sourceLayerName,
    minzoom: Math.max(0, minRuleZoom),
    ...(filterExpr ? { filter: filterExpr } : {}),
    layout: {
      visibility: layer.visible !== false ? "visible" : "none",
      "symbol-placement": placement,
      "text-field": textFieldExpr,
      "text-font": fontStack,
      "text-size": textSizeExpr,
      ...(placement === "point" && lbl.align ? { "text-justify": lbl.align } : {}),
      ...(placement === "point" && textRotate !== 0 ? { "text-rotate": textRotate } : {}),
      ...(offsetXEm !== 0 || offsetYEm !== 0 ? { "text-offset": [offsetXEm, offsetYEm] } : {}),
      "text-allow-overlap": Boolean(lbl.overflow),
      "text-ignore-placement": Boolean(lbl.overflow),
    },
    paint: {
      "text-color": textColorExpr,
      "text-halo-color": haloColorExpr,
      "text-halo-width": haloWidthExpr,
      "text-opacity": textOpacityExpr,
    },
  };
}

export interface BuiltMvtLayerBundle {
  layers: any[];
  identifiableLayerIds: string[];
}

export function buildMvtSubLayersForActiveLayer(
  layer: ActiveLayerItem,
  sourceId: string,
  sourceLayerName: string,
  options: { isDark: boolean }
): BuiltMvtLayerBundle {
  const { isDark } = options;
  const prefix = `mr-mvt-${sanitizeLayerId(layer.id)}`;
  const opacity = typeof layer.opacity === "number" ? Math.max(0, Math.min(1, layer.opacity)) : 1;
  const visibility = layer.visible !== false ? "visible" : "none";
  const styleCfg = layer.style_config;

  const normSource = String(sourceLayerName || layer.layer_name || "").toLowerCase();
  const isKabupaten =
    normSource === "bataswilayah_kabupaten" || String(layer.id) === "01a15e28-a042-71a0-b111-ed7d9256e5bf";
  const isSegmen = normSource === "infrastruktur_segmen" || String(layer.id).includes("segmen");

  const rawMinZoom = Number(styleCfg?.min_zoom);
  const effectiveMinZoom =
    Number.isFinite(rawMinZoom) && rawMinZoom > 0
      ? toMaplibreZoom(rawMinZoom)
      : isKabupaten
        ? 4
        : 6;

  const outLayers: any[] = [];
  const identifiableIds: string[] = [];

  if (!styleCfg) {
    if (isKabupaten) {
      const filterExpr = buildMvtClientFilterExpr(layer);
      outLayers.push({
        id: `${prefix}-kab-fill`,
        type: "fill",
        source: sourceId,
        "source-layer": sourceLayerName,
        minzoom: effectiveMinZoom,
        ...(filterExpr ? { filter: filterExpr } : {}),
        layout: { visibility },
        paint: {
          "fill-color": isDark ? "rgba(148, 163, 184, 0.03)" : "rgba(71, 85, 105, 0.015)",
          "fill-opacity": opacity,
        },
      });
      outLayers.push({
        id: `${prefix}-kab-casing`,
        type: "line",
        source: sourceId,
        "source-layer": sourceLayerName,
        minzoom: effectiveMinZoom,
        ...(filterExpr ? { filter: filterExpr } : {}),
        layout: { visibility, "line-cap": "round", "line-join": "round" },
        paint: {
          "line-color": isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(71, 85, 105, 0.22)",
          "line-width": ["interpolate", ["linear"], ["zoom"], 10, 4, 12, 5.5, 14, 7.5],
          "line-opacity": opacity,
        },
      });
      outLayers.push({
        id: `${prefix}-kab-line`,
        type: "line",
        source: sourceId,
        "source-layer": sourceLayerName,
        minzoom: effectiveMinZoom,
        ...(filterExpr ? { filter: filterExpr } : {}),
        layout: { visibility, "line-cap": "round", "line-join": "round" },
        paint: {
          "line-color": isDark ? "#94a3b8" : "#475569",
          "line-width": ["interpolate", ["linear"], ["zoom"], 10, 1.75, 12, 2, 14, 2.5],
          "line-dasharray": [5, 1.75, 0.9, 1.75, 0.9, 1.75],
          "line-opacity": opacity,
        },
      });
      return { layers: outLayers, identifiableLayerIds: [] };
    }

    const condExpr = buildMultiFieldLowerExpr(["kondisi", "status"]);
    const fbColorExpr: any[] = [
      "case",
      ["!=", ["index-of", "rusak berat", condExpr], -1],
      "#ef4444",
      ["!=", ["index-of", "rusak ringan", condExpr], -1],
      "#f97316",
      ["!=", ["index-of", "sedang", condExpr], -1],
      "#f59e0b",
      ["!=", ["index-of", "rusak", condExpr], -1],
      "#f97316",
      ["!=", ["index-of", "baik", condExpr], -1],
      "#10b981",
      isSegmen ? "#10b981" : "#2563eb",
    ];
    const fbLineWidth = [
      "interpolate",
      ["linear"],
      ["zoom"],
      10,
      isSegmen ? 3 : 1.75,
      13,
      isSegmen ? 4 : 2.5,
    ];

    const polyFilter = buildMvtClientFilterExpr(layer, ["==", ["geometry-type"], "Polygon"]);
    outLayers.push({
      id: `${prefix}-fb-fill`,
      type: "fill",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: effectiveMinZoom,
      ...(polyFilter ? { filter: polyFilter } : {}),
      layout: { visibility },
      paint: {
        "fill-color": isDark ? "rgba(56, 189, 248, 0.05)" : "rgba(2, 132, 199, 0.04)",
        "fill-opacity": opacity,
      },
    });
    identifiableIds.push(`${prefix}-fb-fill`);

    const strokeFilter = buildMvtClientFilterExpr(layer, [
      "any",
      ["==", ["geometry-type"], "LineString"],
      ["==", ["geometry-type"], "Polygon"],
    ]);
    outLayers.push({
      id: `${prefix}-fb-line`,
      type: "line",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: effectiveMinZoom,
      ...(strokeFilter ? { filter: strokeFilter } : {}),
      layout: { visibility, "line-cap": "round", "line-join": "round" },
      paint: {
        "line-color": fbColorExpr,
        "line-width": fbLineWidth,
        "line-opacity": opacity,
      },
    });
    identifiableIds.push(`${prefix}-fb-line`);

    const labelSub = buildLabelSubLayer(
      `${prefix}-fb-label`,
      sourceId,
      sourceLayerName,
      layer,
      null,
      opacity,
      isDark,
      isSegmen
    );
    if (labelSub) outLayers.push(labelSub);

    return { layers: outLayers, identifiableLayerIds: identifiableIds };
  }

  // Custom LayerStyleConfig translation
  const geomMode = styleCfg.geom_type || "auto";
  const showPolygon = geomMode === "polygon" || geomMode === "auto";
  const showLine = geomMode === "line" || geomMode === "auto";
  const showPoint = geomMode === "point" || geomMode === "auto";

  const rules = styleCfg.symbol_mode !== "single" && Array.isArray(styleCfg.rules) ? styleCfg.rules : [];
  const defaultStrokeColor = styleCfg.color || (geomMode === "line" ? (isDark ? "#38bdf8" : "#2563eb") : "#475569");
  const defaultWidth = Number(styleCfg.width ?? (geomMode === "line" ? 2.5 : 1.5));
  const defaultFillColor = styleCfg.fill_color || (showPolygon ? "rgba(59, 130, 246, 0.12)" : "rgba(0, 0, 0, 0)");

  const strokeColorExpr = buildCaseExprFromRules(rules, (r) => r.color, defaultStrokeColor);
  const rawWidthExpr = buildCaseExprFromRules(
    rules,
    (r) => (typeof r.width === "number" ? r.width : undefined),
    defaultWidth
  );
  const fillColorExpr = buildCaseExprFromRules(
    rules,
    (r) => r.fill_color || (showPolygon ? r.color : undefined),
    defaultFillColor
  );
  const pointColorExpr = buildCaseExprFromRules(rules, (r) => r.color, styleCfg.point_color || defaultStrokeColor);

  const zoomScale = styleCfg.zoom_scale !== false;
  const lineWidthExpr = buildZoomScaledWidthExpr(rawWidthExpr, zoomScale);
  const lineCap = styleCfg.line_cap || "round";
  const lineJoin = styleCfg.line_join || "round";

  const baseDash = resolveMaplibreDashArray(styleCfg.dash, defaultWidth);
  const hasRuleDash = rules.some((r) => Boolean(r.dash && r.dash !== "solid"));
  const dashExpr = hasRuleDash
    ? buildCaseExprFromRules(
        rules,
        (r) => {
          if (!r.dash) return undefined;
          if (r.dash === "solid") return ["literal", [1000, 0]];
          const arr = resolveMaplibreDashArray(r.dash, typeof r.width === "number" ? r.width : defaultWidth);
          return arr ? ["literal", arr] : undefined;
        },
        baseDash ? ["literal", baseDash] : ["literal", [1000, 0]]
      )
    : baseDash;

  const polyGeoCond = showPolygon && geomMode === "auto" ? ["==", ["geometry-type"], "Polygon"] : undefined;
  const lineOrPolyGeoCond =
    geomMode === "auto"
      ? ["any", ["==", ["geometry-type"], "LineString"], ["==", ["geometry-type"], "Polygon"]]
      : geomMode === "line"
        ? ["==", ["geometry-type"], "LineString"]
        : geomMode === "polygon"
          ? ["==", ["geometry-type"], "Polygon"]
          : undefined;
  const pointGeoCond = showPoint && geomMode === "auto" ? ["==", ["geometry-type"], "Point"] : undefined;

  // 1. Optional casing line underlay
  if (styleCfg.casing && (showLine || showPolygon)) {
    const casingFilter = buildMvtClientFilterExpr(layer, lineOrPolyGeoCond);
    outLayers.push({
      id: `${prefix}-casing`,
      type: "line",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: effectiveMinZoom,
      ...(casingFilter ? { filter: casingFilter } : {}),
      layout: { visibility, "line-cap": lineCap, "line-join": lineJoin },
      paint: {
        "line-color":
          styleCfg.casing_color || (isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(71, 85, 105, 0.22)"),
        "line-width": Number(styleCfg.casing_width ?? defaultWidth * 2.5),
        "line-opacity": opacity,
      },
    });
  }

  // 2. Polygon fill sub-layer
  if (showPolygon) {
    const polyFilter = buildMvtClientFilterExpr(layer, polyGeoCond);
    outLayers.push({
      id: `${prefix}-fill`,
      type: "fill",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: effectiveMinZoom,
      ...(polyFilter ? { filter: polyFilter } : {}),
      layout: { visibility },
      paint: {
        "fill-color": fillColorExpr,
        "fill-opacity": opacity,
      },
    });
    identifiableIds.push(`${prefix}-fill`);
  }

  // 3. Stroke / boundary line sub-layer
  if (showLine || showPolygon) {
    const strokeFilter = buildMvtClientFilterExpr(layer, lineOrPolyGeoCond);
    outLayers.push({
      id: `${prefix}-line`,
      type: "line",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: effectiveMinZoom,
      ...(strokeFilter ? { filter: strokeFilter } : {}),
      layout: { visibility, "line-cap": lineCap, "line-join": lineJoin },
      paint: {
        "line-color": strokeColorExpr,
        "line-width": lineWidthExpr,
        ...(dashExpr ? { "line-dasharray": dashExpr } : {}),
        "line-opacity": opacity,
      },
    });
    identifiableIds.push(`${prefix}-line`);
  }

  // 4. Point circle sub-layer
  if (showPoint) {
    const pointFilter = buildMvtClientFilterExpr(layer, pointGeoCond);
    outLayers.push({
      id: `${prefix}-circle`,
      type: "circle",
      source: sourceId,
      "source-layer": sourceLayerName,
      minzoom: effectiveMinZoom,
      ...(pointFilter ? { filter: pointFilter } : {}),
      layout: { visibility },
      paint: {
        "circle-radius": Number(styleCfg.point_radius ?? 6),
        "circle-color": pointColorExpr,
        "circle-stroke-color": styleCfg.point_border_color || "#ffffff",
        "circle-stroke-width": Number(styleCfg.point_border_width ?? 2),
        "circle-opacity": opacity,
        "circle-stroke-opacity": opacity,
      },
    });
    identifiableIds.push(`${prefix}-circle`);
  }

  // 5. Labels sub-layer
  const labelLayer = buildLabelSubLayer(
    `${prefix}-label`,
    sourceId,
    sourceLayerName,
    layer,
    styleCfg,
    opacity,
    isDark,
    isSegmen
  );
  if (labelLayer) {
    outLayers.push(labelLayer);
  }

  return { layers: outLayers, identifiableLayerIds: identifiableIds };
}

/**
 * Compute EPSG:4326 bounding box [minLng, minLat, maxLng, maxLat] of any GeoJSON geometry.
 */
export function extractGeoJsonGeometryBbox(geom: any): [number, number, number, number] | null {
  if (!geom || !geom.coordinates) return null;
  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;

  const visit = (node: any) => {
    if (!Array.isArray(node)) return;
    if (node.length >= 2 && typeof node[0] === "number" && typeof node[1] === "number") {
      if (node[0] < minX) minX = node[0];
      if (node[0] > maxX) maxX = node[0];
      if (node[1] < minY) minY = node[1];
      if (node[1] > maxY) maxY = node[1];
      return;
    }
    for (let i = 0; i < node.length; i++) visit(node[i]);
  };
  visit(geom.coordinates);

  if (!Number.isFinite(minX) || !Number.isFinite(minY)) return null;
  return [minX, minY, maxX, maxY];
}

function joinConnectingGeoJsonLines(lines: number[][][], toleranceDeg = 0.0001): number[][][] {
  if (lines.length <= 1) return lines;
  const remaining = lines.map((l) => [...l]);
  const joined: number[][][] = [];
  const tolSq = toleranceDeg * toleranceDeg;

  const distSq = (p1: number[], p2: number[]) => {
    const dx = p1[0]! - p2[0]!;
    const dy = p1[1]! - p2[1]!;
    return dx * dx + dy * dy;
  };

  while (remaining.length > 0) {
    let current = remaining.pop()!;
    let found = true;

    while (found) {
      found = false;
      for (let i = 0; i < remaining.length; i++) {
        const candidate = remaining[i]!;
        const startCur = current[0]!;
        const endCur = current[current.length - 1]!;
        const startCand = candidate[0]!;
        const endCand = candidate[candidate.length - 1]!;

        if (distSq(endCur, startCand) <= tolSq) {
          current = current.concat(candidate.slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        }
        if (distSq(endCur, endCand) <= tolSq) {
          current = current.concat(candidate.slice().reverse().slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        }
        if (distSq(startCur, endCand) <= tolSq) {
          current = candidate.concat(current.slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        }
        if (distSq(startCur, startCand) <= tolSq) {
          current = candidate.slice().reverse().concat(current.slice(1));
          remaining.splice(i, 1);
          found = true;
          break;
        }
      }
    }
    joined.push(current);
  }

  return joined;
}

/**
 * Merge fragmented MVT vector-tile GeoJSON pieces belonging to the same feature.
 */
export function stitchGeoJsonFragments(geometries: any[]): any {
  const valid = geometries.filter((g) => g && g.type && g.coordinates);
  if (valid.length === 0) return null;
  if (valid.length === 1) return valid[0];

  const sampleType = String(valid[0].type);
  if (sampleType.includes("Line")) {
    const lineCoords: number[][][] = [];
    for (const g of valid) {
      if (g.type === "LineString") {
        lineCoords.push(g.coordinates);
      } else if (g.type === "MultiLineString") {
        lineCoords.push(...g.coordinates);
      }
    }
    if (lineCoords.length === 0) return valid[0];
    const joined = joinConnectingGeoJsonLines(lineCoords, 0.0001);
    return joined.length === 1
      ? { type: "LineString", coordinates: joined[0] }
      : { type: "MultiLineString", coordinates: joined };
  }

  if (sampleType.includes("Polygon")) {
    const polyCoords: number[][][][] = [];
    for (const g of valid) {
      if (g.type === "Polygon") {
        polyCoords.push(g.coordinates);
      } else if (g.type === "MultiPolygon") {
        polyCoords.push(...g.coordinates);
      }
    }
    if (polyCoords.length === 0) return valid[0];
    return polyCoords.length === 1
      ? { type: "Polygon", coordinates: polyCoords[0] }
      : { type: "MultiPolygon", coordinates: polyCoords };
  }

  return valid[0];
}

function pointToSegmentDistSq(px: number, py: number, ax: number, ay: number, bx: number, by: number): number {
  const dx = bx - ax;
  const dy = by - ay;
  const lenSq = dx * dx + dy * dy;
  if (lenSq === 0) return (px - ax) * (px - ax) + (py - ay) * (py - ay);
  const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lenSq));
  const projX = ax + t * dx;
  const projY = ay + t * dy;
  return (px - projX) * (px - projX) + (py - projY) * (py - projY);
}

/**
 * Compute approximate pixel distance between a GeoJSON point/line and a click screen pixel.
 */
export function computeGeoJsonClickPixelDistance(
  geom: any,
  clickPx: { x: number; y: number },
  mapInstance: any
): number {
  if (!geom || !geom.coordinates || !mapInstance || typeof mapInstance.project !== "function") {
    return 0;
  }

  const gt = String(geom.type || "").toLowerCase();
  if (gt.includes("polygon")) return 0;

  let minDistSq = Infinity;
  const checkPoint = (c: number[]) => {
    const projected = mapInstance.project([c[0], c[1]]);
    const dSq = (projected.x - clickPx.x) ** 2 + (projected.y - clickPx.y) ** 2;
    if (dSq < minDistSq) minDistSq = dSq;
  };
  const checkLine = (coords: number[][]) => {
    if (coords.length === 0) return;
    let prev = mapInstance.project([coords[0]![0], coords[0]![1]]);
    for (let i = 1; i < coords.length; i++) {
      const curr = mapInstance.project([coords[i]![0], coords[i]![1]]);
      const dSq = pointToSegmentDistSq(clickPx.x, clickPx.y, prev.x, prev.y, curr.x, curr.y);
      if (dSq < minDistSq) minDistSq = dSq;
      prev = curr;
    }
  };

  if (geom.type === "Point") checkPoint(geom.coordinates);
  else if (geom.type === "MultiPoint") geom.coordinates.forEach(checkPoint);
  else if (geom.type === "LineString") checkLine(geom.coordinates);
  else if (geom.type === "MultiLineString") geom.coordinates.forEach(checkLine);

  return Number.isFinite(minDistSq) ? Math.sqrt(minDistSq) : 0;
}

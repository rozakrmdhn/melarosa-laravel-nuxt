import type { LayerStyleConfig, StyleRule } from "~/types/map-layers";

export interface MvtStyleOptions {
  olModules: any;
  layer: any;
  mapInstance: any;
  targetOlLayer: any;
}

/**
 * Konversi nama preset garis putus-putus ke array lineDash OpenLayers
 */
export function resolveLineDash(dash: string | number[] | undefined): number[] | undefined {
  if (!dash || dash === "solid") return undefined;
  if (Array.isArray(dash)) return dash;
  switch (dash) {
    case "batas-negara":
      return [14, 5];
    case "batas-provinsi":
    case "batas-propinsi":
      return [14, 4, 2.5, 4];
    case "batas-kabupaten":
      return [14, 3.5, 2.5, 3, 2.5, 3.5];
    case "batas-kecamatan":
      return [14, 3.5, 2.5, 3, 2.5, 3, 2.5, 3.5];
    case "batas-desa":
      return [14, 3.5, 2.5, 3, 2.5, 3, 2.5, 3, 2.5, 3.5];
    case "dash":
      return [8, 5];
    case "dot":
      return [2, 4];
    case "dash-dot":
      return [14, 4, 2.5, 4];
    default:
      return undefined;
  }
}

/**
 * Evaluasi kecocokan aturan kondisional terhadap properti fitur spasial
 */
export function evaluateRuleMatch(feature: any, rule: StyleRule): boolean {
  if (!rule.field) return false;

  const rawVal = feature.get?.(rule.field) ?? feature.getProperties?.()?.[rule.field];
  if (rawVal === undefined || rawVal === null) return false;

  if (rule.operator === "between") {
    const numVal = Number(rawVal);
    const minVal = Number(rule.min_value);
    const maxVal = Number(rule.max_value);
    if (!Number.isFinite(numVal) || !Number.isFinite(minVal) || !Number.isFinite(maxVal)) return false;
    const low = Math.min(minVal, maxVal);
    const high = Math.max(minVal, maxVal);
    return numVal >= low && numVal <= high;
  }

  if (rule.value === undefined || rule.value === "") return false;

  const featStr = String(rawVal).trim().toLowerCase();
  const ruleStr = String(rule.value).trim().toLowerCase();

  switch (rule.operator) {
    case "equals":
      return featStr === ruleStr;
    case "contains":
      return featStr.includes(ruleStr);
    case "starts_with":
      return featStr.startsWith(ruleStr);
    case "gt":
      return Number(rawVal) > Number(rule.value);
    case "lt":
      return Number(rawVal) < Number(rule.value);
    default:
      return featStr.includes(ruleStr);
  }
}

/**
 * Cek apakah 2 bounding box/extent beririsan (EPSG:3857)
 */
function extentsIntersect(ext1: number[], ext2: number[]): boolean {
  return ext1[0] <= ext2[2] && ext1[2] >= ext2[0] && ext1[1] <= ext2[3] && ext1[3] >= ext2[1];
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

function matchesWilayahAttribute(rawFeatVal: string, targetVal: string, prefixRe: RegExp): boolean {
  const cleanTarget = normalizeWilayahToken(targetVal, prefixRe);
  if (!cleanTarget) return true;

  const tokens = rawFeatVal
    .split(/[,;|]+/)
    .map((part) => normalizeWilayahToken(part, prefixRe))
    .filter(Boolean);

  if (tokens.length === 0) return false;
  return tokens.some((token) => token === cleanTarget);
}

/**
 * Filter wilayah kecamatan, desa, kondisi, dan spatial extent secara client-side
 */
function passesClientFilters(feature: any, targetOlLayer: any): boolean {
  if (!targetOlLayer) return true;

  const filterExtent = targetOlLayer.get("filterExtent") as number[] | undefined;

  // 1. Filter Kecamatan
  const targetKec = (targetOlLayer.get("filterKecamatan") || "").trim().toLowerCase();
  if (targetKec && targetKec !== "all") {
    const rawFeatKec = String(
      feature.get("nama_kecamatan") ||
      feature.get("kecamatan") ||
      feature.get("WADMKC") ||
      feature.get("wadmkc") ||
      feature.get("KECAMATAN") ||
      ""
    ).trim();

    if (rawFeatKec) {
      if (!matchesWilayahAttribute(rawFeatKec, targetKec, /^(kecamatan|kec\.)\s+/i)) {
        return false;
      }
    } else if (filterExtent && Array.isArray(filterExtent) && filterExtent.length === 4) {
      // Fallback spatial extent jika layer tidak menyimpan field kecamatan
      const geom = feature.getGeometry?.();
      if (geom) {
        const featExt = geom.getExtent?.();
        if (featExt && !extentsIntersect(featExt, filterExtent)) {
          return false;
        }
      }
    } else {
      return false;
    }
  }

  // 2. Filter Desa
  const targetDesa = (targetOlLayer.get("filterDesa") || "").trim().toLowerCase();
  if (targetDesa && targetDesa !== "all") {
    const rawFeatDesa = String(
      feature.get("nama_desa") ||
      feature.get("desa") ||
      feature.get("NAMOBJ") ||
      feature.get("namobj") ||
      feature.get("DESA") ||
      ""
    ).trim();

    if (rawFeatDesa) {
      if (!matchesWilayahAttribute(rawFeatDesa, targetDesa, /^(desa|kelurahan|kel\.)\s+/i)) {
        return false;
      }
    } else if (filterExtent && Array.isArray(filterExtent) && filterExtent.length === 4) {
      // Fallback spatial extent untuk Desa
      const geom = feature.getGeometry?.();
      if (geom) {
        const featExt = geom.getExtent?.();
        if (featExt && !extentsIntersect(featExt, filterExtent)) {
          return false;
        }
      }
    } else {
      return false;
    }
  }

  // 3. Filter Status Kondisi
  const targetStatus = (targetOlLayer.get("filterStatus") || "").trim().toLowerCase();
  if (targetStatus && targetStatus !== "all") {
    const featKondisi = String(
      feature.get("kondisi") ||
      feature.get("status") ||
      feature.get("KONDISI") ||
      feature.get("STATUS") ||
      ""
    ).trim().toLowerCase();

    if (!featKondisi.includes(targetStatus)) {
      return false;
    }
  }

  return true;
}

const MAX_STYLE_CACHE_SIZE = 256;

function getOrSetCache<T>(cache: Map<string, T>, key: string, create: () => T): T {
  const cached = cache.get(key);
  if (cached !== undefined) return cached;
  if (cache.size >= MAX_STYLE_CACHE_SIZE) {
    cache.clear();
  }
  const value = create();
  cache.set(key, value);
  return value;
}

const BASE_ROW_PK_KEYS = [
  "id",
  "ID",
  "gid",
  "GID",
  "fid",
  "FID",
  "ogc_fid",
  "OGC_FID",
  "objectid",
  "OBJECTID",
] as const;

export interface MvtFeatureSelectionKey {
  layerName: string;
  uniqueId: string | null;
  /** Komposit kanonik `<layer_name>::<unique_id>` (null jika tidak ada unique ID). */
  key: string | null;
  /** @deprecated Alias ke `key` untuk kompatibilitas internal. */
  id: string | null;
}

function isSyntheticFeatureId(raw: string): boolean {
  if (!raw) return true;
  if (raw.startsWith("synthetic:")) return true;
  // Nilai float acak Math.random() berbentuk "0.12345...", sedangkan integer "0" tetap valid.
  return /^0\.\d+$/.test(raw);
}

function extractMartinSourceFromUrl(url?: string | null): string {
  const raw = String(url || "").trim();
  if (!raw) return "";
  const match = raw.match(/\/martin\/([^/?#{]+)/i);
  return match?.[1] ? decodeURIComponent(match[1]).trim() : "";
}

export function resolveMvtLayerSelectionHint(
  layer?: {
    id?: string;
    layer_name?: string;
    martinSource?: string;
    mvtUrl?: string;
    url?: string;
    name?: string;
  } | null,
  featureLayerName?: string | null
): string {
  const fromUrl = extractMartinSourceFromUrl(layer?.mvtUrl || layer?.url);
  return String(
    layer?.layer_name ||
      layer?.martinSource ||
      fromUrl ||
      featureLayerName ||
      layer?.id ||
      layer?.name ||
      ""
  )
    .trim()
    .toLowerCase();
}

function getLayerSpecificUniqueIdKeys(layerHint: string): readonly string[] {
  if (layerHint.includes("segmen") || layerHint.includes("infrastruktur")) {
    return ["id_segmen", "ID_SEGMEN", "kode_segmen", "KODE_SEGMEN"];
  }
  if (layerHint.includes("jalan") || layerHint.includes("poros")) {
    return ["id_ruas", "ID_RUAS", "kode_ruas", "KODE_RUAS", "no_ruas", "NO_RUAS"];
  }
  if (
    (layerHint.includes("desa") || layerHint.includes("kelurahan")) &&
    !layerHint.includes("jalan") &&
    !layerHint.includes("segmen") &&
    !layerHint.includes("infrastruktur")
  ) {
    return ["id_desa", "ID_DESA", "kdepum", "KDEPUM", "kode_desa", "KODE_DESA", "wiadkd", "WIADKD"];
  }
  if (
    layerHint.includes("kecamatan") &&
    !layerHint.includes("desa") &&
    !layerHint.includes("kelurahan") &&
    !layerHint.includes("jalan") &&
    !layerHint.includes("segmen") &&
    !layerHint.includes("infrastruktur")
  ) {
    return ["id_kecamatan", "ID_KECAMATAN", "kdcpum", "KDCPUM", "wiadkc", "WIADKC"];
  }
  return [];
}

function normalizeCandidateUniqueId(rawVal: unknown, layerKey: string): string | null {
  if (rawVal === undefined || rawVal === null) return null;
  let idStr = String(rawVal).trim();
  if (!idStr || isSyntheticFeatureId(idStr)) return null;

  // Lepaskan prefix "<layer_name>::" jika sudah dikompositkan sebelumnya.
  const sepIdx = idStr.indexOf("::");
  if (sepIdx !== -1) {
    const prefix = idStr.slice(0, sepIdx).trim().toLowerCase();
    const suffix = idStr.slice(sepIdx + 2).trim();
    if (!suffix || isSyntheticFeatureId(suffix)) return null;
    if (!layerKey || prefix === layerKey || layerKey.endsWith(`.${prefix}`) || prefix.endsWith(`.${layerKey}`)) {
      idStr = suffix;
    }
  }

  return idStr;
}

export function extractMvtFeatureSelectionKey(
  props: Record<string, any> | null | undefined,
  olFeatureId?: string | number | null,
  layerHint?: string | null
): MvtFeatureSelectionKey {
  const safeProps = props || {};
  const propLayer = String(safeProps.layer || "").trim().toLowerCase();
  const layerKey = String(layerHint || propLayer || "").trim().toLowerCase();

  // 1. Prioritaskan OpenLayers feature.getId() karena MVT({ idProperty: "id" }) memindahkan properti "id" ke feature.getId().
  const olId = normalizeCandidateUniqueId(olFeatureId, layerKey);
  if (olId !== null) {
    const key = layerKey ? `${layerKey}::${olId}` : olId;
    return { layerName: layerKey, uniqueId: olId, key, id: key };
  }

  // 2. Fallback ke kolom primary key baris (id, gid, fid, ogc_fid, objectid) di properties.
  for (const pkKey of BASE_ROW_PK_KEYS) {
    const candidate = normalizeCandidateUniqueId(safeProps[pkKey], layerKey);
    if (candidate !== null) {
      const key = layerKey ? `${layerKey}::${candidate}` : candidate;
      return { layerName: layerKey, uniqueId: candidate, key, id: key };
    }
  }

  // 3. Fallback terakhir ke identifier unik spesifik layer (tidak pernah memakai foreign key antar tabel / nama wilayah).
  const layerSpecificKeys = getLayerSpecificUniqueIdKeys(layerKey);
  for (const specificKey of layerSpecificKeys) {
    const candidate = normalizeCandidateUniqueId(safeProps[specificKey], layerKey);
    if (candidate !== null) {
      const key = layerKey ? `${layerKey}::${candidate}` : candidate;
      return { layerName: layerKey, uniqueId: candidate, key, id: key };
    }
  }

  return { layerName: layerKey, uniqueId: null, key: null, id: null };
}

function isLayerNameCompatible(featureLayer: string, selectedLayer: string): boolean {
  if (!featureLayer || !selectedLayer) return true;
  if (featureLayer === selectedLayer) return true;
  return featureLayer.endsWith(`.${selectedLayer}`) || selectedLayer.endsWith(`.${featureLayer}`);
}

/**
 * Mencocokkan fitur MVT terpilih secara ketat berdasarkan `(layer_name, unique_id)`.
 */
export function isMvtFeatureSelected(feature: any, targetOlLayer: any, layerHint?: string): boolean {
  if (!targetOlLayer || !feature) return false;
  const selKey = String(
    targetOlLayer.get?.("selectedFeatureKey") || targetOlLayer.get?.("selectedFeatureId") || ""
  ).trim();
  if (!selKey) return false;

  const selLayerName = String(targetOlLayer.get?.("selectedLayerName") || layerHint || "")
    .trim()
    .toLowerCase();
  const featureLayer = String(feature.get?.("layer") || "").trim().toLowerCase();
  if (selLayerName && featureLayer && !isLayerNameCompatible(featureLayer, selLayerName)) {
    return false;
  }

  const effectiveLayerHint = selLayerName || String(layerHint || featureLayer || "").trim().toLowerCase();
  const props = feature.getProperties?.() || {};
  const fKey = extractMvtFeatureSelectionKey(props, feature.getId?.(), effectiveLayerHint);
  return fKey.key !== null && fKey.key === selKey;
}

/**
 * Generator Style Function khusus overlay highlight MVT di layer teratas agar highlight layer bawah tidak tertimbun layer atas.
 */
export function createMvtSelectionOverlayStyleFn(
  olModules: any,
  overlayOlLayer: any
) {
  const OlStyle = olModules?.OlStyle || olModules?.Style;
  const Stroke = olModules?.Stroke;
  const Fill = olModules?.Fill;
  const OlCircle = olModules?.CircleStyle || olModules?.OlCircle || olModules?.Circle;

  const haloStroke = Stroke
    ? new Stroke({
        color: "rgba(0, 0, 0, 0.9)",
        width: 6,
        lineCap: "round",
        lineJoin: "round",
      })
    : null;
  const coreStroke = Stroke
    ? new Stroke({
        color: "#ffffff",
        width: 2.75,
        lineCap: "round",
        lineJoin: "round",
      })
    : null;
  const polyFill = Fill ? new Fill({ color: "rgba(0, 0, 0, 0)" }) : null;

  const polygonStyle =
    OlStyle && haloStroke && coreStroke && polyFill
      ? [
          new OlStyle({
            stroke: haloStroke,
            fill: polyFill,
            zIndex: 99,
          }),
          new OlStyle({
            stroke: coreStroke,
            zIndex: 100,
          }),
        ]
      : null;

  const lineStyle =
    OlStyle && haloStroke && coreStroke
      ? [
          new OlStyle({
            stroke: haloStroke,
            zIndex: 99,
          }),
          new OlStyle({
            stroke: coreStroke,
            zIndex: 100,
          }),
        ]
      : null;

  const pointStyle =
    OlStyle && OlCircle && Fill && Stroke
      ? new OlStyle({
          image: new OlCircle({
            radius: 7,
            fill: new Fill({ color: "#ffffff" }),
            stroke: new Stroke({ color: "#000000", width: 2.5 }),
          }),
          zIndex: 100,
        })
      : null;

  return (feature: any) => {
    if (!OlStyle || !Stroke || !Fill) return null;
    if (!passesClientFilters(feature, overlayOlLayer)) return null;

    const geomCategory = String(overlayOlLayer?.get?.("selectedGeomCategory") || "").toLowerCase();
    const geomType = String(feature.getGeometry?.()?.getType?.() || "").toLowerCase();
    if (geomCategory === "polygon" && !geomType.includes("polygon")) return null;
    if (geomCategory === "line" && !geomType.includes("line")) return null;
    if (geomCategory === "point" && !geomType.includes("point")) return null;

    const selLayerName = String(overlayOlLayer?.get?.("selectedLayerName") || "").trim().toLowerCase();
    if (!isMvtFeatureSelected(feature, overlayOlLayer, selLayerName)) {
      return null;
    }

    if (geomType.includes("polygon")) return polygonStyle;
    if (geomType.includes("line")) return lineStyle;
    if (geomType.includes("point")) return pointStyle;
    return lineStyle;
  };
}

/**
 * Generator Style Function OpenLayers dinamis berdasarkan LayerStyleConfig
 */
export function createDynamicMvtStyleFn(
  styleConfig: LayerStyleConfig | null | undefined,
  options: MvtStyleOptions
) {
  const { olModules, mapInstance, targetOlLayer, layer } = options;
  const OlStyle = olModules?.OlStyle || olModules?.Style;
  const Stroke = olModules?.Stroke;
  const Fill = olModules?.Fill;
  const OlText = olModules?.OlText || olModules?.Text;
  const OlCircle = olModules?.CircleStyle || olModules?.OlCircle || olModules?.Circle;

  const strokeCache = new Map<string, any>();
  const fillCache = new Map<string, any>();
  const circleImageCache = new Map<string, any>();
  const staticStyleCache = new Map<string, any>();

  const layerSelectionHint = resolveMvtLayerSelectionHint(layer);

  return (feature: any) => {
    if (!OlStyle || !Stroke || !Fill) {
      return null;
    }

    // Jalankan filter wilayah dan status
    if (!passesClientFilters(feature, targetOlLayer)) {
      return null;
    }

    const currentZoom = mapInstance?.getView()?.getZoom() || 0;
    const isDark = typeof document !== "undefined" && document.documentElement.classList.contains("dark");

    // Identifikasi jenis geometri fitur spasial
    const geomType = feature.getGeometry?.()?.getType?.() || "";
    const isLine = geomType.includes("Line") || styleConfig?.geom_type === "line";
    const isPolygon = geomType.includes("Polygon") || styleConfig?.geom_type === "polygon";
    const isPoint = geomType.includes("Point") || styleConfig?.geom_type === "point";

    // Highlight poligon MVT (terpotong rapi per-tile tanpa garis grid internal) jika diaktifkan pada layer ini
    if (isPolygon && isMvtFeatureSelected(feature, targetOlLayer, layerSelectionHint)) {
      return [
        new OlStyle({
          stroke: getOrSetCache(strokeCache, "selected_halo|rgba(0,0,0,0.9)|6", () =>
            new Stroke({
              color: "rgba(0, 0, 0, 0.9)",
              width: 6,
              lineCap: "round",
              lineJoin: "round",
            })
          ),
          fill: getOrSetCache(fillCache, "rgba(0, 0, 0, 0)", () => new Fill({ color: "rgba(0, 0, 0, 0)" })),
          zIndex: 99,
        }),
        new OlStyle({
          stroke: getOrSetCache(strokeCache, "selected_core|#ffffff|2.75", () =>
            new Stroke({
              color: "#ffffff",
              width: 2.75,
              lineCap: "round",
              lineJoin: "round",
            })
          ),
          zIndex: 100,
        }),
      ];
    }

    // Jika tidak ada konfigurasi kustom, terapkan fallback adaptif bawaan
    if (!styleConfig) {
      return getFallbackMvtStyle(feature, layer, olModules, currentZoom, isDark, {
        strokeCache,
        fillCache,
        staticStyleCache,
      });
    }

    // 1. Nilai Dasar Style
    let strokeColor = styleConfig.color || (isLine ? (isDark ? "#38bdf8" : "#2563eb") : "#475569");
    let strokeWidth = Number(styleConfig.width ?? (isLine ? 2.5 : 1.5));
    let lineDash = resolveLineDash(styleConfig.dash);
    let fillColor = styleConfig.fill_color || (isPolygon ? "rgba(59, 130, 246, 0.12)" : undefined);

    // 2. Evaluasi Aturan Kondisional (Rules / Categorized / Graduated)
    let matchedPointColor: string | undefined;
    if (
      styleConfig.symbol_mode !== "single" &&
      Array.isArray(styleConfig.rules) &&
      styleConfig.rules.length > 0
    ) {
      for (const rule of styleConfig.rules) {
        if (evaluateRuleMatch(feature, rule)) {
          if (rule.color) {
            strokeColor = rule.color;
            matchedPointColor = rule.color;
          }
          if (typeof rule.width === "number") strokeWidth = rule.width;
          if (rule.fill_color) {
            fillColor = rule.fill_color;
          } else if (isPolygon && rule.color) {
            fillColor = rule.color;
          }
          if (rule.dash) lineDash = resolveLineDash(rule.dash);
          break; // Aturan pertama yang cocok menang
        }
      }
    }

    // 3. Zoom Scaling (penskalaan ketebalan sesuai tingkat zoom)
    if (styleConfig.zoom_scale !== false && isLine) {
      if (currentZoom >= 15) {
        strokeWidth *= 1.4;
      } else if (currentZoom >= 13.5) {
        strokeWidth *= 1.15;
      } else if (currentZoom <= 10) {
        strokeWidth = Math.max(strokeWidth * 0.75, 1);
      }
    }

    const lineCap = styleConfig.line_cap || "round";
    const lineJoin = styleConfig.line_join || "round";
    const styles: any[] = [];

    // 4. Efek Casing / Underlay / Halo (jika diaktifkan)
    if (styleConfig.casing) {
      const casingColor = styleConfig.casing_color || (isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(71, 85, 105, 0.22)");
      const casingWidth = Number(styleConfig.casing_width ?? (strokeWidth * 2.5));
      const casingFill = isPolygon && fillColor ? fillColor : "";
      const casingKey = `casing|${casingColor}|${casingWidth}|${lineCap}|${lineJoin}|${casingFill}`;

      styles.push(
        getOrSetCache(staticStyleCache, casingKey, () =>
          new OlStyle({
            stroke: getOrSetCache(strokeCache, `${casingColor}|${casingWidth}|${lineCap}|${lineJoin}`, () =>
              new Stroke({
                color: casingColor,
                width: casingWidth,
                lineCap,
                lineJoin,
              })
            ),
            fill: casingFill
              ? getOrSetCache(fillCache, casingFill, () => new Fill({ color: casingFill }))
              : undefined,
            zIndex: 1,
          })
        )
      );
    }

    // 5. Label Fitur Spasial (Single Labels & Rule Based Labelling)
    let textStyle: any = undefined;
    if (OlText && styleConfig.label?.enabled) {
      let activeLbl = {
        field: styleConfig.label.field,
        min_zoom: styleConfig.label.min_zoom ?? 13,
        color: styleConfig.label.color,
        halo_color: styleConfig.label.halo_color,
        halo_width: styleConfig.label.halo_width,
        font_size: styleConfig.label.font_size,
        font_family: styleConfig.label.font_family,
        font_style: styleConfig.label.font_style,
        opacity: styleConfig.label.opacity,
      };
      let shouldRenderLabel = Boolean(activeLbl.field);

      if (styleConfig.label.mode === "rule_based") {
        shouldRenderLabel = false;
        const lblRules = styleConfig.label.rules || [];
        for (const lr of lblRules) {
          if (
            evaluateRuleMatch(feature, {
              field: lr.filter_field,
              operator: lr.operator,
              value: lr.value,
              min_value: lr.min_value,
              max_value: lr.max_value,
            })
          ) {
            activeLbl = {
              field: lr.label_field || styleConfig.label.field,
              min_zoom: lr.min_zoom ?? styleConfig.label.min_zoom ?? 13,
              color: lr.color || styleConfig.label.color,
              halo_color: lr.halo_color || styleConfig.label.halo_color,
              halo_width: lr.halo_width ?? styleConfig.label.halo_width,
              font_size: lr.font_size ?? styleConfig.label.font_size,
              font_family: lr.font_family || styleConfig.label.font_family,
              font_style: lr.font_style || styleConfig.label.font_style,
              opacity: lr.opacity ?? styleConfig.label.opacity,
            };
            shouldRenderLabel = Boolean(activeLbl.field);
            break;
          }
        }
      }

      if (shouldRenderLabel && currentZoom >= activeLbl.min_zoom) {
        const fieldName = activeLbl.field;
        const rawLabel = feature.get(fieldName) ||
          feature.get(fieldName.toLowerCase()) ||
          feature.get(fieldName.toUpperCase()) ||
          feature.get("namobj") ||
          feature.get("nama_ruas") ||
          "";

        const labelText = String(rawLabel).trim();
        if (labelText && labelText !== "-") {
          const fontSize = activeLbl.font_size ?? 11;
          const labelAlpha = Number.isFinite(Number(activeLbl.opacity))
            ? Math.max(0, Math.min(1, Number(activeLbl.opacity)))
            : 1;
          const rawTextColor = activeLbl.color || (isDark ? "#f1f5f9" : "#0f172a");
          const rawHaloColor = activeLbl.halo_color || (isDark ? "#0f172a" : "#ffffff");
          const applyLabelAlpha = (hexOrCss: string) => {
            if (labelAlpha >= 0.99 || !hexOrCss.startsWith("#") || hexOrCss.length !== 7) return hexOrCss;
            const r = parseInt(hexOrCss.slice(1, 3), 16);
            const g = parseInt(hexOrCss.slice(3, 5), 16);
            const b = parseInt(hexOrCss.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${labelAlpha})`;
          };
          const textColor = applyLabelAlpha(rawTextColor);
          const haloColor = applyLabelAlpha(rawHaloColor);
          const haloWidth = activeLbl.halo_width ?? 3;

          const fontStyleKey = activeLbl.font_style || "bold";
          const cssFontWeight = fontStyleKey === "bold" || fontStyleKey === "bold_italic" ? "600" : "400";
          const cssFontStyle = fontStyleKey === "italic" || fontStyleKey === "bold_italic" ? "italic" : "normal";
          const familyRaw = activeLbl.font_family || "SansSerif";
          const cssFontFamily =
            familyRaw === "Serif"
              ? "Georgia, serif"
              : familyRaw === "Monospace"
                ? "ui-monospace, monospace"
                : familyRaw === "Arial"
                  ? "Arial, sans-serif"
                  : "Inter, sans-serif";

          // Penentuan mode penempatan label (centroid point vs kontur line)
          const placement = styleConfig.label.placement || (isPolygon || isPoint ? "point" : isLine ? "line" : "point");

          // Penentuan orientasi dan rotasi teks (dalam radian)
          let rotation = 0;
          if (placement === "point") {
            if (styleConfig.label.orientation === "vertical") {
              rotation = Math.PI / 2; // 90 derajat vertikal
            } else if (typeof styleConfig.label.rotation === "number") {
              rotation = (styleConfig.label.rotation * Math.PI) / 180;
            }
          }

          textStyle = new OlText({
            text: labelText,
            font: `${cssFontStyle} ${cssFontWeight} ${fontSize}px ${cssFontFamily}`,
            fill: getOrSetCache(fillCache, textColor, () => new Fill({ color: textColor })),
            stroke: getOrSetCache(strokeCache, `${haloColor}|${haloWidth}`, () =>
              new Stroke({ color: haloColor, width: haloWidth })
            ),
            placement,
            textAlign: placement === "line" ? undefined : (styleConfig.label.align || "center"),
            rotation: placement === "line" ? 0 : rotation,
            offsetX: Number(styleConfig.label.offset_x ?? 0),
            offsetY: Number(styleConfig.label.offset_y ?? 0),
            overflow: Boolean(styleConfig.label.overflow),
          });
        }
      }
    }

    // 6. Main Style
    if (isPoint && OlCircle) {
      const pointRadius = Number(styleConfig.point_radius ?? 6);
      const pointColor = matchedPointColor || styleConfig.point_color || strokeColor;
      const pointBorderColor = styleConfig.point_border_color || "#ffffff";
      const pointBorderWidth = Number(styleConfig.point_border_width ?? 2);
      const imageKey = `pt|${pointRadius}|${pointColor}|${pointBorderColor}|${pointBorderWidth}`;
      const image = getOrSetCache(circleImageCache, imageKey, () =>
        new OlCircle({
          radius: pointRadius,
          fill: getOrSetCache(fillCache, pointColor, () => new Fill({ color: pointColor })),
          stroke: getOrSetCache(strokeCache, `${pointBorderColor}|${pointBorderWidth}`, () =>
            new Stroke({ color: pointBorderColor, width: pointBorderWidth })
          ),
        })
      );

      if (!textStyle) {
        styles.push(
          getOrSetCache(staticStyleCache, `main-pt|${imageKey}`, () =>
            new OlStyle({ image, zIndex: 10 })
          )
        );
      } else {
        styles.push(
          new OlStyle({
            image,
            text: textStyle,
            zIndex: 10,
          })
        );
      }
    } else {
      const dashKey = lineDash ? lineDash.join(",") : "";
      const strokeKey = `${strokeColor}|${strokeWidth}|${dashKey}|${lineCap}|${lineJoin}`;
      const mainFill = isPolygon && !styleConfig.casing && fillColor ? fillColor : "";

      const getMainStroke = () =>
        getOrSetCache(strokeCache, strokeKey, () =>
          new Stroke({
            color: strokeColor,
            width: strokeWidth,
            lineDash,
            lineCap,
            lineJoin,
          })
        );

      const getMainFill = () =>
        mainFill
          ? getOrSetCache(fillCache, mainFill, () => new Fill({ color: mainFill }))
          : undefined;

      if (!textStyle) {
        styles.push(
          getOrSetCache(staticStyleCache, `main|${strokeKey}|${mainFill}`, () =>
            new OlStyle({
              stroke: getMainStroke(),
              fill: getMainFill(),
              zIndex: 5,
            })
          )
        );
      } else {
        styles.push(
          new OlStyle({
            stroke: getMainStroke(),
            fill: getMainFill(),
            text: textStyle,
            zIndex: 5,
          })
        );
      }
    }

    return styles.length === 1 ? styles[0] : styles;
  };
}

interface FallbackStyleCaches {
  strokeCache: Map<string, any>;
  fillCache: Map<string, any>;
  staticStyleCache: Map<string, any>;
}

/**
 * Fallback style untuk layer MVT yang belum memiliki style_config kustom di database
 */
function getFallbackMvtStyle(
  feature: any,
  layer: any,
  olModules: any,
  currentZoom: number,
  isDark: boolean,
  caches: FallbackStyleCaches
) {
  const OlStyle = olModules?.OlStyle || olModules?.Style;
  const Stroke = olModules?.Stroke;
  const Fill = olModules?.Fill;
  const OlText = olModules?.OlText || olModules?.Text;

  if (!OlStyle || !Stroke || !Fill) {
    return null;
  }
  const { strokeCache, fillCache, staticStyleCache } = caches;
  const layerName = layer?.layer_name || "";
  const isKabupaten = layerName === "bataswilayah_kabupaten" || layer?.id === "01a15e28-a042-71a0-b111-ed7d9256e5bf";
  const isSegmen = layerName === "infrastruktur_segmen" || layer?.id?.includes("segmen");

  // A. Batas Wilayah Kabupaten
  if (isKabupaten) {
    const zoomBucket = currentZoom >= 13 ? 2 : currentZoom >= 11 ? 1 : 0;
    const kabKey = `fb-kab|${isDark ? 1 : 0}|${zoomBucket}`;
    return getOrSetCache(staticStyleCache, kabKey, () => [
      new OlStyle({
        stroke: new Stroke({
          color: isDark ? "rgba(148, 163, 184, 0.25)" : "rgba(71, 85, 105, 0.22)",
          width: zoomBucket === 2 ? 7.5 : zoomBucket === 1 ? 5.5 : 4,
          lineCap: "round",
          lineJoin: "round",
        }),
        fill: new Fill({
          color: isDark ? "rgba(148, 163, 184, 0.03)" : "rgba(71, 85, 105, 0.015)",
        }),
      }),
      new OlStyle({
        stroke: new Stroke({
          color: isDark ? "#94a3b8" : "#475569",
          width: zoomBucket === 2 ? 2.5 : zoomBucket === 1 ? 2 : 1.75,
          lineDash: [12, 4, 2, 4, 2, 4],
          lineCap: "round",
          lineJoin: "round",
        }),
        zIndex: 4,
      }),
    ]);
  }

  // B. Trase Jalan Berdasarkan Kondisi (Infrastruktur Segmen & Jalan Poros Desa)
  const kondisi = String(feature.get("kondisi") || feature.get("status") || "").toLowerCase();
  let strokeColor = isSegmen ? "#10b981" : "#2563eb";

  if (kondisi.includes("rusak berat")) strokeColor = "#ef4444";
  else if (kondisi.includes("rusak ringan")) strokeColor = "#f97316";
  else if (kondisi.includes("sedang")) strokeColor = "#f59e0b";
  else if (kondisi.includes("rusak")) strokeColor = "#f97316";
  else if (kondisi.includes("baik")) strokeColor = "#10b981";

  const strokeWidth = currentZoom >= 14 ? (isSegmen ? 4 : 2.5) : (isSegmen ? 3 : 1.75);
  const zIndex = isSegmen ? 100 : 10;

  let textStyle: any = undefined;
  if (OlText && currentZoom >= 13.5) {
    const labelText = String(
      feature.get(isSegmen ? "namobj" : "nama_ruas") ||
      feature.get("nama_ruas") ||
      feature.get("namobj") ||
      ""
    );
    if (labelText && labelText !== "-") {
      const labelColor = isSegmen ? "#064e3b" : "#1e3a8a";
      textStyle = new OlText({
        text: labelText,
        font: "600 11px Inter, sans-serif",
        fill: getOrSetCache(fillCache, labelColor, () => new Fill({ color: labelColor })),
        stroke: getOrSetCache(strokeCache, "#ffffff|3", () => new Stroke({ color: "#ffffff", width: 3 })),
        placement: "line",
        overflow: false,
      });
    }
  }

  const geomType = String(feature.getGeometry?.()?.getType?.() || "");
  const isPolygon = geomType.includes("Polygon");

  const lineStroke = getOrSetCache(strokeCache, `fb|${strokeColor}|${strokeWidth}|round|round`, () =>
    new Stroke({
      color: strokeColor,
      width: strokeWidth,
      lineCap: "round",
      lineJoin: "round",
    })
  );

  const polyFillColor = isDark ? "rgba(56, 189, 248, 0.05)" : "rgba(2, 132, 199, 0.04)";
  const polyFill = isPolygon
    ? getOrSetCache(fillCache, polyFillColor, () => new Fill({ color: polyFillColor }))
    : undefined;

  if (!textStyle) {
    return getOrSetCache(
      staticStyleCache,
      `fb-geom|${isPolygon ? 1 : 0}|${strokeColor}|${strokeWidth}|${zIndex}|${isDark ? 1 : 0}`,
      () =>
        new OlStyle({
          stroke: lineStroke,
          fill: polyFill,
          zIndex,
        })
    );
  }

  return new OlStyle({
    stroke: lineStroke,
    fill: polyFill,
    text: textStyle,
    zIndex,
  });
}


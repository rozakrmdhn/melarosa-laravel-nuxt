import type { LabelStyleRule, LayerStyleConfig, StyleRule } from "~/types/map-layers";
import { resolveLineDash } from "~/utils/mvt-styler";

const OGC_DEFAULT_SCALE_AT_Z0 = 559082264.028;
const MAX_SAFE_GET_URL_LENGTH = 6500;

export function escapeXml(value: unknown): string {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

/**
 * Pada endpoint workspace GeoServer (mis. /geoserver/palapa/wms), virtual service menghapus prefix
 * workspace dari NamedLayer SLD, sehingga <NamedLayer><Name> harus tanpa prefix "palapa:" agar tidak fallback ke SLD bawaan.
 */
export function resolveSldNamedLayerName(
  wmsLayerName: string,
  wmsUrl?: string | null
): string {
  const trimmed = String(wmsLayerName || "").trim();
  if (!trimmed.includes(":")) return trimmed;

  const [prefix, shortName] = trimmed.split(":", 2);
  if (!prefix || !shortName) return trimmed;

  const url = String(wmsUrl || "").toLowerCase();
  const escapedPrefix = prefix.replace(/[.*+?^${}()|[\]\\]/g, "\\$&").toLowerCase();
  const wsPathRegex = new RegExp(`/${escapedPrefix}/(?:wms|ows)(?:[/?#]|$)`, "i");
  if (wsPathRegex.test(url)) {
    return shortName;
  }

  return trimmed;
}

/**
 * Konversi CSS color (#rgb, #rrggbb, #rrggbbaa, rgb(...), rgba(...)) ke hex #rrggbb + opacity (0..1) untuk GeoServer SLD.
 */
export function parseCssColorToSld(
  input: string | undefined,
  fallbackHex = "#2563eb",
  fallbackOpacity = 1
): { hex: string; opacity: number } {
  const raw = String(input || "").trim();
  if (!raw) return { hex: fallbackHex, opacity: fallbackOpacity };

  if (/^[0-9a-f]{6}$/i.test(raw)) {
    return { hex: `#${raw.toLowerCase()}`, opacity: 1 };
  }

  if (raw.startsWith("#")) {
    const hex = raw.slice(1);
    if (hex.length === 3) {
      const expanded = hex
        .split("")
        .map((c) => c + c)
        .join("");
      return { hex: `#${expanded.toLowerCase()}`, opacity: 1 };
    }
    if (hex.length === 6) {
      return { hex: `#${hex.toLowerCase()}`, opacity: 1 };
    }
    if (hex.length === 8) {
      const alpha = parseInt(hex.slice(6, 8), 16);
      return {
        hex: `#${hex.slice(0, 6).toLowerCase()}`,
        opacity: Number.isFinite(alpha) ? Number((alpha / 255).toFixed(3)) : 1,
      };
    }
  }

  const rgbMatch = raw.match(
    /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9.]+))?\s*\)$/i
  );
  if (rgbMatch) {
    const toHex = (n: string) =>
      Math.max(0, Math.min(255, Number(n)))
        .toString(16)
        .padStart(2, "0");
    const hex = `#${toHex(rgbMatch[1]!)}${toHex(rgbMatch[2]!)}${toHex(rgbMatch[3]!)}`;
    const parsedAlpha = rgbMatch[4] !== undefined ? Number(rgbMatch[4]) : 1;
    return {
      hex,
      opacity: Number.isFinite(parsedAlpha) ? Math.max(0, Math.min(1, parsedAlpha)) : 1,
    };
  }

  return { hex: fallbackHex, opacity: fallbackOpacity };
}

/**
 * Tentukan apakah layer WMS benar-benar memiliki custom SLD (bukan sekadar min_zoom atau objek kosong).
 */
export function hasWmsCustomStyle(styleConfig?: LayerStyleConfig | null): boolean {
  if (!styleConfig || typeof styleConfig !== "object") return false;
  if (styleConfig.sld_mode === "xml" && Boolean(styleConfig.custom_sld_xml?.trim())) {
    return true;
  }
  if (typeof styleConfig.custom_sld_xml === "string" && styleConfig.custom_sld_xml.trim().length > 0) {
    return true;
  }

  return Boolean(
    styleConfig.color ||
      styleConfig.fill_color ||
      styleConfig.point_color ||
      styleConfig.point_border_color ||
      styleConfig.dash ||
      styleConfig.width !== undefined ||
      styleConfig.point_radius !== undefined ||
      styleConfig.casing ||
      styleConfig.label?.enabled ||
      (Array.isArray(styleConfig.rules) && styleConfig.rules.length > 0)
  );
}

export function zoomToMaxScaleDenominator(zoom?: number | null): number | null {
  const z = Number(zoom);
  if (!Number.isFinite(z) || z <= 0) return null;
  return Math.round(OGC_DEFAULT_SCALE_AT_Z0 / Math.pow(2, z));
}

function isRuleComplete(rule: StyleRule): boolean {
  if (!rule.field) return false;
  if (rule.operator === "between") {
    return (
      rule.min_value !== undefined &&
      rule.min_value !== null &&
      rule.max_value !== undefined &&
      rule.max_value !== null &&
      Number.isFinite(Number(rule.min_value)) &&
      Number.isFinite(Number(rule.max_value))
    );
  }
  return rule.value !== undefined && String(rule.value).trim() !== "";
}

function buildRuleFilterXml(rule: StyleRule): string {
  if (!isRuleComplete(rule)) return "";
  const prop = `<ogc:PropertyName>${escapeXml(rule.field)}</ogc:PropertyName>`;

  if (rule.operator === "between") {
    const minVal = Number(rule.min_value);
    const maxVal = Number(rule.max_value);
    const low = Math.min(minVal, maxVal);
    const high = Math.max(minVal, maxVal);
    return `<ogc:Filter><ogc:PropertyIsBetween>${prop}<ogc:LowerBoundary><ogc:Literal>${escapeXml(low)}</ogc:Literal></ogc:LowerBoundary><ogc:UpperBoundary><ogc:Literal>${escapeXml(high)}</ogc:Literal></ogc:UpperBoundary></ogc:PropertyIsBetween></ogc:Filter>`;
  }

  const valStr = String(rule.value);

  switch (rule.operator) {
    case "equals":
      return `<ogc:Filter><ogc:PropertyIsEqualTo matchCase="false">${prop}<ogc:Literal>${escapeXml(valStr)}</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>`;
    case "starts_with":
      return `<ogc:Filter><ogc:PropertyIsLike wildCard="*" singleChar="." escapeChar="\\" matchCase="false">${prop}<ogc:Literal>${escapeXml(valStr)}*</ogc:Literal></ogc:PropertyIsLike></ogc:Filter>`;
    case "gt":
      return `<ogc:Filter><ogc:PropertyIsGreaterThan>${prop}<ogc:Literal>${escapeXml(valStr)}</ogc:Literal></ogc:PropertyIsGreaterThan></ogc:Filter>`;
    case "lt":
      return `<ogc:Filter><ogc:PropertyIsLessThan>${prop}<ogc:Literal>${escapeXml(valStr)}</ogc:Literal></ogc:PropertyIsLessThan></ogc:Filter>`;
    case "contains":
    default:
      return `<ogc:Filter><ogc:PropertyIsLike wildCard="*" singleChar="." escapeChar="\\" matchCase="false">${prop}<ogc:Literal>*${escapeXml(valStr)}*</ogc:Literal></ogc:PropertyIsLike></ogc:Filter>`;
  }
}

function buildStrokeParamsXml(
  color: string | undefined,
  width: number | undefined,
  dash: string | number[] | undefined,
  lineCap?: string,
  lineJoin?: string
): string {
  const stroke = parseCssColorToSld(color, "#2563eb", 1);
  const w = Number.isFinite(Number(width)) ? Number(width) : 2;
  const dashArr = resolveLineDash(dash);

  let xml = `<CssParameter name="stroke">${stroke.hex}</CssParameter>`;
  if (stroke.opacity < 1) {
    xml += `<CssParameter name="stroke-opacity">${stroke.opacity}</CssParameter>`;
  }
  xml += `<CssParameter name="stroke-width">${w}</CssParameter>`;
  if (dashArr && dashArr.length > 0) {
    xml += `<CssParameter name="stroke-dasharray">${dashArr.join(" ")}</CssParameter>`;
  }
  if (lineCap) {
    xml += `<CssParameter name="stroke-linecap">${escapeXml(lineCap)}</CssParameter>`;
  }
  if (lineJoin) {
    xml += `<CssParameter name="stroke-linejoin">${escapeXml(lineJoin)}</CssParameter>`;
  }
  return xml;
}

function buildSymbolizerXml(
  config: LayerStyleConfig,
  ruleOverride?: StyleRule
): string {
  const geomType = config.geom_type || "auto";
  const strokeColor = ruleOverride?.color || config.color || (geomType === "line" ? "#2563eb" : "#475569");
  const strokeWidth = ruleOverride?.width ?? config.width ?? (geomType === "line" ? 2.5 : 1.5);
  const fillRaw = ruleOverride?.fill_color || config.fill_color || "rgba(59, 130, 246, 0.2)";
  const dash = (ruleOverride?.dash as string | number[] | undefined) ?? config.dash;

  const parts: string[] = [];

  if (geomType === "polygon" || geomType === "auto") {
    const baseFillParsed = parseCssColorToSld(config.fill_color || "rgba(59, 130, 246, 0.35)", "#3b82f6", 0.35);
    let fill = parseCssColorToSld(fillRaw, "#3b82f6", 0.35);
    if (ruleOverride && !ruleOverride.fill_color && ruleOverride.color) {
      const ruleColorParsed = parseCssColorToSld(ruleOverride.color, "#3b82f6", 1);
      fill = {
        hex: ruleColorParsed.hex,
        opacity: baseFillParsed.opacity > 0.05 ? baseFillParsed.opacity : 0.45,
      };
    }
    const strokeParams = buildStrokeParamsXml(strokeColor, strokeWidth, dash, config.line_cap, config.line_join);
    parts.push(
      `<PolygonSymbolizer><Fill><CssParameter name="fill">${fill.hex}</CssParameter><CssParameter name="fill-opacity">${fill.opacity}</CssParameter></Fill><Stroke>${strokeParams}</Stroke></PolygonSymbolizer>`
    );
  }

  if (geomType === "line" || geomType === "auto") {
    if (config.casing) {
      const casingParams = buildStrokeParamsXml(
        config.casing_color || "rgba(148, 163, 184, 0.45)",
        config.casing_width ?? Number(strokeWidth) + 3,
        undefined,
        config.line_cap || "round",
        config.line_join || "round"
      );
      parts.push(`<LineSymbolizer><Stroke>${casingParams}</Stroke></LineSymbolizer>`);
    }
    const lineParams = buildStrokeParamsXml(
      strokeColor,
      strokeWidth,
      dash,
      config.line_cap || "round",
      config.line_join || "round"
    );
    parts.push(`<LineSymbolizer><Stroke>${lineParams}</Stroke></LineSymbolizer>`);
  }

  if (geomType === "point" || geomType === "auto") {
    const ptFill = parseCssColorToSld(ruleOverride?.color || config.point_color || config.color, "#0284c7", 1);
    const ptBorder = parseCssColorToSld(config.point_border_color, "#ffffff", 1);
    const radius = Number.isFinite(Number(config.point_radius)) ? Number(config.point_radius) : 6;
    const borderWidth = Number.isFinite(Number(config.point_border_width)) ? Number(config.point_border_width) : 1.5;
    parts.push(
      `<PointSymbolizer><Graphic><Mark><WellKnownName>circle</WellKnownName><Fill><CssParameter name="fill">${ptFill.hex}</CssParameter><CssParameter name="fill-opacity">${ptFill.opacity}</CssParameter></Fill><Stroke><CssParameter name="stroke">${ptBorder.hex}</CssParameter><CssParameter name="stroke-width">${borderWidth}</CssParameter></Stroke></Mark><Size>${Math.max(4, radius * 2)}</Size></Graphic></PointSymbolizer>`
    );
  }

  return parts.join("");
}

function buildSingleTextSymbolizerRuleXml(
  config: LayerStyleConfig,
  ruleName: string,
  filterXml: string,
  override?: Partial<LabelStyleRule>
): string {
  const lbl = config.label;
  if (!lbl) return "";

  const labelField = (override?.label_field || lbl.field || "").trim();
  if (!labelField) return "";

  const textFill = parseCssColorToSld(override?.color || lbl.color, "#1e3a8a", 1);
  const haloFill = parseCssColorToSld(override?.halo_color || lbl.halo_color, "#ffffff", 1);
  const rawOpacity = override?.opacity !== undefined ? override.opacity : lbl.opacity;
  const labelOpacity = Number.isFinite(Number(rawOpacity))
    ? Math.max(0, Math.min(1, Number(rawOpacity)))
    : 1;
  const effectiveTextOpacity = Number((textFill.opacity * labelOpacity).toFixed(3));
  const effectiveHaloOpacity = Number((haloFill.opacity * labelOpacity).toFixed(3));

  const rawSize = override?.font_size !== undefined ? override.font_size : lbl.font_size;
  const fontSize = Number.isFinite(Number(rawSize)) ? Number(rawSize) : 11;
  const fontFamily = (override?.font_family || lbl.font_family || "SansSerif").trim();
  const fontStyleMode = override?.font_style || lbl.font_style || "bold";
  const sldFontStyle = fontStyleMode === "italic" || fontStyleMode === "bold_italic" ? "italic" : "normal";
  const sldFontWeight = fontStyleMode === "bold" || fontStyleMode === "bold_italic" ? "bold" : "normal";

  const rawHaloWidth = override?.halo_width !== undefined ? override.halo_width : lbl.halo_width;
  const haloWidth = Number.isFinite(Number(rawHaloWidth)) ? Number(rawHaloWidth) : 2.5;
  const maxScale = zoomToMaxScaleDenominator(
    override?.min_zoom !== undefined ? override.min_zoom : lbl.min_zoom
  );

  let placementXml = "";
  if (lbl.placement === "line" && config.geom_type !== "point") {
    placementXml = `<LabelPlacement><LinePlacement/></LabelPlacement>`;
  } else {
    const offsetX = Number(lbl.offset_x ?? 0);
    const offsetY = Number(lbl.offset_y ?? 0);
    const rotation = lbl.orientation === "vertical" ? 90 : Number(lbl.rotation ?? 0);
    const anchorX = lbl.align === "left" ? "0.0" : lbl.align === "right" ? "1.0" : "0.5";
    placementXml = `<LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>${anchorX}</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>${offsetX}</DisplacementX><DisplacementY>${-offsetY}</DisplacementY></Displacement><Rotation>${rotation}</Rotation></PointPlacement></LabelPlacement>`;
  }

  const shouldWrap = lbl.wrap !== false;
  const wrapWidth = Number.isFinite(Number(lbl.wrap_width))
    ? Math.max(30, Math.min(400, Math.round(Number(lbl.wrap_width))))
    : 90;

  const vendorOptions = [
    shouldWrap ? `<VendorOption name="autoWrap">${wrapWidth}</VendorOption>` : "",
    `<VendorOption name="maxDisplacement">36</VendorOption>`,
    lbl.placement === "line" && config.geom_type !== "point" ? `<VendorOption name="followLine">true</VendorOption>` : "",
    lbl.overflow ? `<VendorOption name="conflictResolution">false</VendorOption>` : "",
  ]
    .filter(Boolean)
    .join("");

  const textOpacityXml = effectiveTextOpacity < 1 ? `<CssParameter name="fill-opacity">${effectiveTextOpacity}</CssParameter>` : "";
  const haloOpacityXml = effectiveHaloOpacity < 1 ? `<CssParameter name="fill-opacity">${effectiveHaloOpacity}</CssParameter>` : "";

  return `<Rule><Name>${escapeXml(ruleName)}</Name>${filterXml}${maxScale ? `<MaxScaleDenominator>${maxScale}</MaxScaleDenominator>` : ""}<TextSymbolizer><Label><ogc:PropertyName>${escapeXml(labelField)}</ogc:PropertyName></Label><Font><CssParameter name="font-family">${escapeXml(fontFamily)}</CssParameter><CssParameter name="font-style">${sldFontStyle}</CssParameter><CssParameter name="font-weight">${sldFontWeight}</CssParameter><CssParameter name="font-size">${fontSize}</CssParameter></Font>${placementXml}<Halo><Radius>${Math.max(0, haloWidth)}</Radius><Fill><CssParameter name="fill">${haloFill.hex}</CssParameter>${haloOpacityXml}</Fill></Halo><Fill><CssParameter name="fill">${textFill.hex}</CssParameter>${textOpacityXml}</Fill>${vendorOptions}</TextSymbolizer></Rule>`;
}

function buildTextSymbolizerRuleXml(config: LayerStyleConfig): string {
  const lbl = config.label;
  if (!lbl?.enabled) return "";

  if (lbl.mode === "rule_based" && Array.isArray(lbl.rules) && lbl.rules.length > 0) {
    const out: string[] = [];
    lbl.rules.forEach((lr, idx) => {
      const asStyleRule: StyleRule = {
        field: lr.filter_field,
        operator: lr.operator,
        value: lr.value,
        min_value: lr.min_value,
        max_value: lr.max_value,
      };
      if (!isRuleComplete(asStyleRule)) return;
      const filterXml = buildRuleFilterXml(asStyleRule);
      const xml = buildSingleTextSymbolizerRuleXml(config, `Label_Rule_${idx + 1}`, filterXml, lr);
      if (xml) out.push(xml);
    });
    return out.join("");
  }

  return buildSingleTextSymbolizerRuleXml(config, "Label", "");
}

/**
 * Hasilkan XML SLD 1.0.0 lengkap dari LayerStyleConfig atau gunakan raw XML kustom jika mode XML aktif.
 */
export function buildWmsSldXml(
  wmsLayerName: string,
  styleConfig?: LayerStyleConfig | null,
  wmsUrl?: string | null
): string | null {
  if (!wmsLayerName || !hasWmsCustomStyle(styleConfig)) return null;

  const sldLayerName = resolveSldNamedLayerName(wmsLayerName, wmsUrl);

  const normalizeCustomXml = (rawXml: string): string => {
    const trimmed = rawXml.trim();
    if (sldLayerName !== wmsLayerName.trim()) {
      return trimmed.replace(
        new RegExp(`(<NamedLayer>\\s*<Name>)\\s*${escapeXml(wmsLayerName.trim())}\\s*(</Name>)`, "i"),
        `$1${escapeXml(sldLayerName)}$2`
      );
    }
    return trimmed;
  };

  if (styleConfig?.sld_mode === "xml" && styleConfig.custom_sld_xml?.trim()) {
    return normalizeCustomXml(styleConfig.custom_sld_xml);
  }
  if (!styleConfig?.sld_mode && styleConfig?.custom_sld_xml?.trim()) {
    return normalizeCustomXml(styleConfig.custom_sld_xml);
  }

  const cfg = styleConfig!;
  const layerScale = zoomToMaxScaleDenominator(cfg.min_zoom);
  const scaleTag = layerScale ? `<MaxScaleDenominator>${layerScale}</MaxScaleDenominator>` : "";
  const validRules =
    cfg.symbol_mode === "single"
      ? []
      : (cfg.rules || []).filter((r) => isRuleComplete(r));

  const rulesXml: string[] = [];

  if (validRules.length > 0) {
    validRules.forEach((rule, idx) => {
      const filterXml = buildRuleFilterXml(rule);
      const symXml = buildSymbolizerXml(cfg, rule);
      const titleText =
        rule.label?.trim() ||
        (rule.operator === "between"
          ? `${rule.field}: ${rule.min_value} - ${rule.max_value}`
          : `${rule.field}: ${rule.value}`);
      const title = escapeXml(titleText);
      rulesXml.push(
        `<Rule><Name>Rule_${idx + 1}</Name><Title>${title}</Title>${filterXml}${scaleTag}${symXml}</Rule>`
      );
    });
    // Fallback ElseFilter untuk fitur yang tidak cocok dengan rule mana pun
    rulesXml.push(
      `<Rule><Name>Lainnya</Name><Title>Lainnya</Title><ElseFilter/>${scaleTag}${buildSymbolizerXml(cfg)}</Rule>`
    );
  } else {
    rulesXml.push(
      `<Rule><Name>Default</Name><Title>Simbol Utama</Title>${scaleTag}${buildSymbolizerXml(cfg)}</Rule>`
    );
  }

  const labelRuleXml = buildTextSymbolizerRuleXml(cfg);
  if (labelRuleXml) {
    rulesXml.push(labelRuleXml);
  }

  return `<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><NamedLayer><Name>${escapeXml(sldLayerName)}</Name><UserStyle><Title>Custom Style Melarosa</Title><FeatureTypeStyle>${rulesXml.join("")}</FeatureTypeStyle></UserStyle></NamedLayer></StyledLayerDescriptor>`;
}

export function shouldUseWmsPostForSld(baseWmsUrl: string, sldXml: string): boolean {
  const encodedLen = encodeURIComponent(sldXml).length;
  // Tambah estimasi panjang parameter GetMap lainnya (~280 karakter)
  return baseWmsUrl.length + encodedLen + 280 > MAX_SAFE_GET_URL_LENGTH;
}

/**
 * Bangun XML body untuk WMS GetMap HTTP POST (fallback bila SLD melebihi batas URL GET 8 KB).
 */
export function buildWmsGetMapPostXml(
  sldXml: string,
  bbox: [number, number, number, number],
  width = 256,
  height = 256,
  srs = "EPSG:3857"
): string {
  const srsUrn = srs.includes("4326")
    ? "http://www.opengis.net/gml/srs/epsg.xml#4326"
    : "http://www.opengis.net/gml/srs/epsg.xml#3857";

  const cleanSld = sldXml.replace(/<\?xml[^>]*\?>/gi, "").trim();
  return `<?xml version="1.0" encoding="UTF-8"?><GetMap xmlns="http://www.opengis.net/ows" xmlns:sld="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:gml="http://www.opengis.net/gml" version="1.1.1" service="WMS">${cleanSld}<BoundingBox srsName="${srsUrn}"><gml:coord><gml:X>${bbox[0]}</gml:X><gml:Y>${bbox[1]}</gml:Y></gml:coord><gml:coord><gml:X>${bbox[2]}</gml:X><gml:Y>${bbox[3]}</gml:Y></gml:coord></BoundingBox><Output><Format>image/png</Format><Size><Width>${width}</Width><Height>${height}</Height></Size><Transparent>true</Transparent></Output></GetMap>`;
}

/**
 * Bangun URL GetLegendGraphic efektif (memasukkan SLD_BODY bila SLD aktif & muat di batas GET URL).
 */
export function buildWmsGetLegendUrl(
  wmsUrl?: string | null,
  wmsLayerName?: string | null,
  styleConfig?: LayerStyleConfig | null,
  fallbackLegendUrl?: string | null
): string | null {
  if (!hasWmsCustomStyle(styleConfig)) {
    return fallbackLegendUrl || null;
  }
  if (!wmsUrl || !wmsLayerName) {
    return fallbackLegendUrl || null;
  }

  const sldXml = buildWmsSldXml(wmsLayerName, styleConfig, wmsUrl);
  if (!sldXml) return fallbackLegendUrl || null;

  const base = wmsUrl.split("?")[0] || wmsUrl;
  const params = new URLSearchParams({
    SERVICE: "WMS",
    VERSION: "1.1.1",
    REQUEST: "GetLegendGraphic",
    FORMAT: "image/png",
    LAYER: wmsLayerName,
    SLD_BODY: sldXml,
  });
  const fullUrl = `${base}?${params.toString()}`;
  if (fullUrl.length > MAX_SAFE_GET_URL_LENGTH) {
    return null;
  }
  return fullUrl;
}

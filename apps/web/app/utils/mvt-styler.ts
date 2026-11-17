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
  if (!rule.field || rule.value === undefined || rule.value === "") return false;

  const rawVal = feature.get?.(rule.field) ?? feature.getProperties?.()?.[rule.field];
  if (rawVal === undefined || rawVal === null) return false;

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
    ).trim().toLowerCase();

    const cleanTarget = targetKec.replace(/^(kecamatan|kec\.)\s+/i, "").trim();
    const cleanFeat = rawFeatKec.replace(/^(kecamatan|kec\.)\s+/i, "").trim();

    if (cleanFeat) {
      // Jika memiliki atribut kecamatan, verifikasi apakah cocok
      if (!cleanFeat.includes(cleanTarget) && !cleanTarget.includes(cleanFeat)) {
        return false;
      }
    } else if (filterExtent && Array.isArray(filterExtent) && filterExtent.length === 4) {
      // Solusi 2: Spatial Extent fallback jika layer tidak menyimpan field kecamatan
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
    ).trim().toLowerCase();

    const cleanTargetDesa = targetDesa.replace(/^(desa|kelurahan|kel\.)\s+/i, "").trim();
    const cleanFeatDesa = rawFeatDesa.replace(/^(desa|kelurahan|kel\.)\s+/i, "").trim();

    if (cleanFeatDesa) {
      if (!cleanFeatDesa.includes(cleanTargetDesa) && !cleanTargetDesa.includes(cleanFeatDesa)) {
        return false;
      }
    } else if (filterExtent && Array.isArray(filterExtent) && filterExtent.length === 4) {
      // Solusi 2: Spatial Extent fallback untuk Desa
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

/**
 * Generator Style Function OpenLayers dinamis berdasarkan LayerStyleConfig
 */
function isMvtFeatureSelected(feature: any, targetOlLayer: any): boolean {
  if (!targetOlLayer || !feature) return false;
  const selId = targetOlLayer.get?.("selectedFeatureId");
  const selName = targetOlLayer.get?.("selectedFeatureName");
  if (!selId && !selName) return false;

  const props = feature.getProperties?.() || {};
  const fId = props.id ?? props.gid ?? props.objectid ?? props.OBJECTID ?? props.fid ?? props.id_kecamatan ?? props.id_desa ?? feature.getId?.();
  if (selId) {
    return fId !== undefined && fId !== null && String(fId) === String(selId);
  }

  if (selName) {
    const fName = String(
      props.nama_kecamatan ?? props.wadmkc ?? props.nama_desa ?? props.wadmkd ?? props.namobj ?? props.nama ?? ""
    ).trim().toLowerCase();
    if (fName && fName === String(selName).trim().toLowerCase()) {
      return true;
    }
  }
  return false;
}

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

    // Highlight poligon MVT langsung di VectorTileLayer (terpotong rapi per-tile oleh OpenLayers tanpa garis grid internal)
    if (isPolygon && isMvtFeatureSelected(feature, targetOlLayer)) {
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

    // 2. Evaluasi Aturan Kondisional (Rules)
    if (Array.isArray(styleConfig.rules) && styleConfig.rules.length > 0) {
      for (const rule of styleConfig.rules) {
        if (evaluateRuleMatch(feature, rule)) {
          if (rule.color) strokeColor = rule.color;
          if (typeof rule.width === "number") strokeWidth = rule.width;
          if (rule.fill_color) fillColor = rule.fill_color;
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

    // 5. Label Fitur Spasial
    let textStyle: any = undefined;
    if (OlText && styleConfig.label?.enabled && styleConfig.label.field) {
      const minZoom = styleConfig.label.min_zoom ?? 13;
      if (currentZoom >= minZoom) {
        const fieldName = styleConfig.label.field;
        const rawLabel = feature.get(fieldName) ||
          feature.get(fieldName.toLowerCase()) ||
          feature.get(fieldName.toUpperCase()) ||
          feature.get("namobj") ||
          feature.get("nama_ruas") ||
          "";

        const labelText = String(rawLabel).trim();
        if (labelText && labelText !== "-") {
          const fontSize = styleConfig.label.font_size ?? 11;
          const textColor = styleConfig.label.color || (isDark ? "#f1f5f9" : "#0f172a");
          const haloColor = styleConfig.label.halo_color || (isDark ? "#0f172a" : "#ffffff");
          const haloWidth = styleConfig.label.halo_width ?? 3;

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
            font: `600 ${fontSize}px Inter, sans-serif`,
            fill: getOrSetCache(fillCache, textColor, () => new Fill({ color: textColor })),
            stroke: getOrSetCache(strokeCache, `${haloColor}|${haloWidth}`, () =>
              new Stroke({ color: haloColor, width: haloWidth })
            ),
            placement,
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
      const pointColor = styleConfig.point_color || strokeColor;
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

  const lineStroke = getOrSetCache(strokeCache, `fb|${strokeColor}|${strokeWidth}|round|round`, () =>
    new Stroke({
      color: strokeColor,
      width: strokeWidth,
      lineCap: "round",
      lineJoin: "round",
    })
  );

  if (!textStyle) {
    return getOrSetCache(staticStyleCache, `fb-line|${strokeColor}|${strokeWidth}|${zIndex}`, () =>
      new OlStyle({
        stroke: lineStroke,
        zIndex,
      })
    );
  }

  return new OlStyle({
    stroke: lineStroke,
    text: textStyle,
    zIndex,
  });
}


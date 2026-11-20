export interface DesaFeatureProperties {
  id: number;
  nama_desa: string;
  id_kecamatan: number | null;
  nama_pimpinan: string | null;
  nama_jabatan: string | null;
  nip: string | null;
  pangkat_gol: string | null;
  luas_hektar: number;
  keliling_meter: number;
  centroid?: { type: string; coordinates: [number, number] } | null;
  created_at?: string;
  updated_at?: string;
}

export interface DesaFeature {
  type: "Feature";
  id: number;
  geometry: any;
  properties: DesaFeatureProperties;
}

export interface KecamatanSeriesItem {
  id: number;
  nama_kecamatan: string;
  nama_pimpinan: string | null;
  nama_jabatan: string | null;
  jumlah_desa: number;
  luas_hektar: number;
  bbox: [number, number, number, number];
}

export interface WilayahDesaSpatialSummary {
  ok: boolean;
  total_desa: number;
  total_luas_hektar: number;
  bbox: [number, number, number, number];
}

export type DesaRightPanelMode = "inspect" | "create" | "edit";

export interface DesaFormState {
  id: number | null;
  nama_desa: string;
  id_kecamatan: number | null;
  nama_pimpinan: string;
  nama_jabatan: string;
  nip: string;
  pangkat_gol: string;
  geometry: string;
}

export interface ParsedDesaGeometrySummary {
  valid: boolean;
  type: string;
  ringCount: number;
  vertexCount: number;
  geometry: any | null;
  error: string | null;
}

export const DEFAULT_DESA_POLYGON_GEOJSON = {
  type: "Polygon",
  coordinates: [
    [
      [111.85, -7.15],
      [111.87, -7.15],
      [111.87, -7.17],
      [111.85, -7.17],
      [111.85, -7.15],
    ],
  ],
};

function isValidCoordPair(pt: unknown): pt is [number, number] {
  if (!Array.isArray(pt) || pt.length < 2) return false;
  const lng = Number(pt[0]);
  const lat = Number(pt[1]);
  return (
    Number.isFinite(lng) &&
    Number.isFinite(lat) &&
    lng >= -180 &&
    lng <= 180 &&
    lat >= -90 &&
    lat <= 90
  );
}

export function parseDesaGeometryInput(raw: string): ParsedDesaGeometrySummary {
  const trimmed = String(raw || "").trim();
  if (!trimmed) {
    return {
      valid: false,
      type: "-",
      ringCount: 0,
      vertexCount: 0,
      geometry: null,
      error: "Geometri GeoJSON wajib diisi.",
    };
  }

  let parsed: any;
  try {
    parsed = JSON.parse(trimmed);
  } catch {
    return {
      valid: false,
      type: "JSON Tidak Valid",
      ringCount: 0,
      vertexCount: 0,
      geometry: null,
      error: "Format JSON tidak valid. Periksa kurung siku dan koma.",
    };
  }

  let geom = parsed;
  if (parsed?.type === "Feature") {
    geom = parsed.geometry;
  } else if (parsed?.type === "FeatureCollection") {
    const firstFeature = Array.isArray(parsed.features) ? parsed.features[0] : null;
    geom = firstFeature?.geometry ?? null;
  }
  if (!geom || typeof geom !== "object" || !geom.type || !Array.isArray(geom.coordinates)) {
    return {
      valid: false,
      type: "-",
      ringCount: 0,
      vertexCount: 0,
      geometry: null,
      error: "Objek harus memiliki properti type dan coordinates.",
    };
  }

  if (geom.type === "Polygon") {
    const rings = geom.coordinates;
    if (!Array.isArray(rings) || rings.length === 0) {
      return {
        valid: false,
        type: "Polygon",
        ringCount: 0,
        vertexCount: 0,
        geometry: null,
        error: "Polygon harus memiliki minimal satu ring koordinat.",
      };
    }
    const outerRing = rings[0];
    if (!Array.isArray(outerRing) || outerRing.length < 4 || !outerRing.every(isValidCoordPair)) {
      return {
        valid: false,
        type: "Polygon",
        ringCount: rings.length,
        vertexCount: Array.isArray(outerRing) ? outerRing.length : 0,
        geometry: null,
        error: "Ring luar Polygon membutuhkan minimal 4 titik [lng, lat] yang valid.",
      };
    }
    const vertexCount = rings.reduce(
      (sum: number, ring: unknown) => sum + (Array.isArray(ring) ? ring.length : 0),
      0
    );
    return {
      valid: true,
      type: "Polygon",
      ringCount: rings.length,
      vertexCount,
      geometry: geom,
      error: null,
    };
  }

  if (geom.type === "MultiPolygon") {
    const polys = geom.coordinates;
    if (!Array.isArray(polys) || polys.length === 0) {
      return {
        valid: false,
        type: "MultiPolygon",
        ringCount: 0,
        vertexCount: 0,
        geometry: null,
        error: "MultiPolygon harus memiliki minimal satu poligon.",
      };
    }
    let ringCount = 0;
    let vertexCount = 0;
    let hasValidOuter = false;
    for (const poly of polys) {
      if (!Array.isArray(poly) || poly.length === 0) continue;
      ringCount += poly.length;
      const outer = poly[0];
      if (Array.isArray(outer) && outer.length >= 4 && outer.every(isValidCoordPair)) {
        hasValidOuter = true;
      }
      for (const ring of poly) {
        if (Array.isArray(ring)) vertexCount += ring.length;
      }
    }
    if (!hasValidOuter) {
      return {
        valid: false,
        type: "MultiPolygon",
        ringCount,
        vertexCount,
        geometry: null,
        error: "Setiap poligon di MultiPolygon membutuhkan minimal 4 titik koordinat.",
      };
    }
    return {
      valid: true,
      type: "MultiPolygon",
      ringCount,
      vertexCount,
      geometry: geom,
      error: null,
    };
  }

  return {
    valid: false,
    type: String(geom.type),
    ringCount: 0,
    vertexCount: 0,
    geometry: null,
    error: `Tipe "${String(geom.type)}" tidak didukung. Gunakan Polygon atau MultiPolygon.`,
  };
}

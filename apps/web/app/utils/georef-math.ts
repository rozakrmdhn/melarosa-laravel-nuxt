import type {
  PublikasiGeorefCorners,
  PublikasiGeorefGcp,
  PublikasiGeorefMethod,
  PublikasiLngLat,
} from '~/types/publikasi-peta';

export interface GeorefSolveResult {
  method: PublikasiGeorefMethod;
  coordinates: PublikasiGeorefCorners;
  bbox: [number, number, number, number];
  rmsMeters: number;
  pointErrorsMeters: Array<{ id: string; errorMeters: number }>;
  projectPixel: (px: number, py: number) => PublikasiLngLat;
}

const EARTH_RADIUS_M = 6371008.8;

export function haversineMeters(a: PublikasiLngLat, b: PublikasiLngLat): number {
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = toRad(b[1] - a[1]);
  const dLng = toRad(b[0] - a[0]);
  const lat1 = toRad(a[1]);
  const lat2 = toRad(b[1]);

  const sinDLat = Math.sin(dLat / 2);
  const sinDLng = Math.sin(dLng / 2);
  const h = sinDLat * sinDLat + Math.cos(lat1) * Math.cos(lat2) * sinDLng * sinDLng;
  return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(Math.max(0, h))));
}

function solveLinearSystem(matrix: number[][], rhs: number[]): number[] | null {
  const n = matrix.length;
  const a = matrix.map((row, i) => [...row, rhs[i] ?? 0]);

  for (let col = 0; col < n; col++) {
    let pivotRow = col;
    let maxAbs = Math.abs(a[col]![col]!);
    for (let r = col + 1; r < n; r++) {
      const val = Math.abs(a[r]![col]!);
      if (val > maxAbs) {
        maxAbs = val;
        pivotRow = r;
      }
    }

    if (maxAbs < 1e-12 || !Number.isFinite(maxAbs)) {
      return null;
    }

    if (pivotRow !== col) {
      const tmp = a[col]!;
      a[col] = a[pivotRow]!;
      a[pivotRow] = tmp;
    }

    const pivot = a[col]![col]!;
    for (let c = col; c <= n; c++) {
      a[col]![c] = a[col]![c]! / pivot;
    }

    for (let r = 0; r < n; r++) {
      if (r === col) continue;
      const factor = a[r]![col]!;
      if (factor === 0) continue;
      for (let c = col; c <= n; c++) {
        a[r]![c] = a[r]![c]! - factor * a[col]![c]!;
      }
    }
  }

  return a.map((row) => row[n]!);
}

function solveLeastSquares(designRows: number[][], targets: number[]): number[] | null {
  const numRows = designRows.length;
  const numCols = designRows[0]?.length ?? 0;
  if (numRows < numCols || numCols === 0) return null;

  const ata: number[][] = Array.from({ length: numCols }, () => Array(numCols).fill(0));
  const atb: number[] = Array(numCols).fill(0);

  for (let r = 0; r < numRows; r++) {
    const row = designRows[r]!;
    const y = targets[r]!;
    for (let i = 0; i < numCols; i++) {
      atb[i] = atb[i]! + row[i]! * y;
      for (let j = 0; j < numCols; j++) {
        ata[i]![j] = ata[i]![j]! + row[i]! * row[j]!;
      }
    }
  }

  return solveLinearSystem(ata, atb);
}

function clampCoord(lng: number, lat: number): PublikasiLngLat {
  return [
    Math.max(-180, Math.min(180, Number(lng.toFixed(7)))),
    Math.max(-85.05, Math.min(85.05, Number(lat.toFixed(7)))),
  ];
}

export function computeBboxFromCorners(corners: PublikasiLngLat[]): [number, number, number, number] {
  const lngs = corners.map((c) => c[0]);
  const lats = corners.map((c) => c[1]);
  return [Math.min(...lngs), Math.min(...lats), Math.max(...lngs), Math.max(...lats)];
}

const MERCATOR_R = 6378137.0;
const MAX_MERCATOR_LAT = 85.051129;

export function lngLatToMercator(lng: number, lat: number): [number, number] {
  const clampedLat = Math.max(-MAX_MERCATOR_LAT, Math.min(MAX_MERCATOR_LAT, lat));
  const x = MERCATOR_R * ((lng * Math.PI) / 180);
  const y = MERCATOR_R * Math.log(Math.tan(Math.PI / 4 + ((clampedLat * Math.PI) / 180) / 2));
  return [x, y];
}

export function mercatorToLngLat(x: number, y: number): PublikasiLngLat {
  const lng = (x / MERCATOR_R) * (180 / Math.PI);
  const lat = (2 * Math.atan(Math.exp(y / MERCATOR_R)) - Math.PI / 2) * (180 / Math.PI);
  return clampCoord(lng, lat);
}

export function solveGeorefTransform(
  gcps: PublikasiGeorefGcp[],
  imageWidth: number,
  imageHeight: number,
  preferredMethod: 'affine' | 'homography' = 'affine',
): GeorefSolveResult | null {
  if (!gcps.length || imageWidth <= 0 || imageHeight <= 0) return null;
  if (gcps.length < 3) return null;

  // Proyeksi seluruh GCP ke ruang Web Mercator (EPSG:3857) agar cocok 1:1
  // dengan interpolasi kuad WebGL milik MapLibre GL JS image source.
  const mercPoints = gcps.map((g) => {
    const [mx, my] = lngLatToMercator(g.lng, g.lat);
    return { g, mx, my };
  });

  const meanMx = mercPoints.reduce((acc, p) => acc + p.mx, 0) / mercPoints.length;
  const meanMy = mercPoints.reduce((acc, p) => acc + p.my, 0) / mercPoints.length;

  const spanMx = Math.max(
    10,
    Math.max(...mercPoints.map((p) => p.mx)) - Math.min(...mercPoints.map((p) => p.mx)),
  );
  const spanMy = Math.max(
    10,
    Math.max(...mercPoints.map((p) => p.my)) - Math.min(...mercPoints.map((p) => p.my)),
  );

  // Opsional Homography jika diminta eksplisit dan minimal 4 titik
  if (preferredMethod === 'homography' && gcps.length >= 4) {
    const design: number[][] = [];
    const rhs: number[] = [];

    for (const p of mercPoints) {
      const u = p.g.px / imageWidth;
      const v = p.g.py / imageHeight;
      const x = (p.mx - meanMx) / spanMx;
      const y = (p.my - meanMy) / spanMy;

      design.push([u, v, 1, 0, 0, 0, -u * x, -v * x]);
      rhs.push(x);

      design.push([0, 0, 0, u, v, 1, -u * y, -v * y]);
      rhs.push(y);
    }

    const h = solveLeastSquares(design, rhs);
    if (h && h.length === 8) {
      const projectNorm = (u: number, v: number): PublikasiLngLat | null => {
        const denom = h[6]! * u + h[7]! * v + 1;
        if (Math.abs(denom) < 1e-4) return null;
        const x = (h[0]! * u + h[1]! * v + h[2]!) / denom;
        const y = (h[3]! * u + h[4]! * v + h[5]!) / denom;
        const mx = meanMx + x * spanMx;
        const my = meanMy + y * spanMy;
        if (!Number.isFinite(mx) || !Number.isFinite(my)) return null;
        return mercatorToLngLat(mx, my);
      };

      const tl = projectNorm(0, 0);
      const tr = projectNorm(1, 0);
      const br = projectNorm(1, 1);
      const bl = projectNorm(0, 1);

      if (tl && tr && br && bl) {
        const coordinates: PublikasiGeorefCorners = [tl, tr, br, bl];
        const pointErrorsMeters = mercPoints.map(({ g }) => {
          const pred = projectNorm(g.px / imageWidth, g.py / imageHeight) ?? [g.lng, g.lat];
          return { id: g.id, errorMeters: Number(haversineMeters([g.lng, g.lat], pred).toFixed(2)) };
        });
        const rmsMeters = Math.sqrt(
          pointErrorsMeters.reduce((sum, p) => sum + p.errorMeters * p.errorMeters, 0) /
            pointErrorsMeters.length,
        );

        if (rmsMeters < 50000) {
          return {
            method: 'homography',
            coordinates,
            bbox: computeBboxFromCorners(coordinates),
            rmsMeters: Number(rmsMeters.toFixed(2)),
            pointErrorsMeters,
            projectPixel: (px: number, py: number) =>
              projectNorm(px / imageWidth, py / imageHeight) ?? mercatorToLngLat(meanMx, meanMy),
          };
        }
      }
    }
  }

  // Transformasi standar Affine 6-parameter dalam koordinat EPSG:3857 (presisi untuk peta datar/PDF)
  const designAffine = mercPoints.map(({ g }) => [1, g.px / imageWidth, g.py / imageHeight]);
  const targetMx = mercPoints.map((p) => p.mx - meanMx);
  const targetMy = mercPoints.map((p) => p.my - meanMy);

  const coeffMx = solveLeastSquares(designAffine, targetMx);
  const coeffMy = solveLeastSquares(designAffine, targetMy);

  if (!coeffMx || !coeffMy) return null;

  const projectPixel = (px: number, py: number): PublikasiLngLat => {
    const u = px / imageWidth;
    const v = py / imageHeight;
    const mx = meanMx + coeffMx[0]! + coeffMx[1]! * u + coeffMx[2]! * v;
    const my = meanMy + coeffMy[0]! + coeffMy[1]! * u + coeffMy[2]! * v;
    return mercatorToLngLat(mx, my);
  };

  const coordinates: PublikasiGeorefCorners = [
    projectPixel(0, 0),
    projectPixel(imageWidth, 0),
    projectPixel(imageWidth, imageHeight),
    projectPixel(0, imageHeight),
  ];

  const pointErrorsMeters = mercPoints.map(({ g }) => ({
    id: g.id,
    errorMeters: Number(haversineMeters([g.lng, g.lat], projectPixel(g.px, g.py)).toFixed(2)),
  }));

  const rmsMeters = Math.sqrt(
    pointErrorsMeters.reduce((sum, p) => sum + p.errorMeters * p.errorMeters, 0) /
      pointErrorsMeters.length,
  );

  return {
    method: 'affine',
    coordinates,
    bbox: computeBboxFromCorners(coordinates),
    rmsMeters: Number(rmsMeters.toFixed(2)),
    pointErrorsMeters,
    projectPixel,
  };
}

export function cornersFromBbox(
  bbox: [number, number, number, number],
  imageWidth = 1000,
  imageHeight = 1000,
): PublikasiGeorefCorners {
  const [minLng, minLat, maxLng, maxLat] = bbox;
  const centerLng = (minLng + maxLng) / 2;
  const centerLat = (minLat + maxLat) / 2;
  const latSpan = Math.max(0.004, maxLat - minLat);
  const cosLat = Math.max(0.2, Math.cos((centerLat * Math.PI) / 180));
  const aspect = imageWidth > 0 && imageHeight > 0 ? imageWidth / imageHeight : 1;

  // Match image aspect ratio inside bbox cleanly without stretching
  const bboxLngMeters = Math.max(0.004, maxLng - minLng) * cosLat;
  let halfLat = latSpan / 2;
  let halfLng = (halfLat * aspect) / cosLat;

  if (halfLng * 2 * cosLat > bboxLngMeters) {
    halfLng = Math.max(0.004, maxLng - minLng) / 2;
    halfLat = (halfLng * cosLat) / aspect;
  }

  return [
    clampCoord(centerLng - halfLng, centerLat + halfLat), // topLeft
    clampCoord(centerLng + halfLng, centerLat + halfLat), // topRight
    clampCoord(centerLng + halfLng, centerLat - halfLat), // bottomRight
    clampCoord(centerLng - halfLng, centerLat - halfLat), // bottomLeft
  ];
}

export function formatDms(decimal: number, isLat: boolean): string {
  const dir = isLat ? (decimal >= 0 ? 'LU' : 'LS') : decimal >= 0 ? 'BT' : 'BB';
  const abs = Math.abs(decimal);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = ((minFloat - min) * 60).toFixed(2);
  return `${deg}° ${String(min).padStart(2, '0')}' ${sec.padStart(5, '0')}" ${dir}`;
}

export function isPointInQuad(point: PublikasiLngLat, quad: PublikasiGeorefCorners): boolean {
  const [x, y] = point;
  let inside = false;
  for (let i = 0, j = quad.length - 1; i < quad.length; j = i++) {
    const [xi, yi] = quad[i]!;
    const [xj, yj] = quad[j]!;
    const intersect =
      yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi || 1e-12) + xi;
    if (intersect) inside = !inside;
  }
  return inside;
}

export function extractGeoJsonBbox(geojson: any): [number, number, number, number] | null {
  if (!geojson) return null;
  let minLng = Infinity;
  let minLat = Infinity;
  let maxLng = -Infinity;
  let maxLat = -Infinity;

  const walk = (coords: any) => {
    if (!Array.isArray(coords)) return;
    if (typeof coords[0] === 'number' && typeof coords[1] === 'number') {
      const lng = Number(coords[0]);
      const lat = Number(coords[1]);
      if (Number.isFinite(lng) && Number.isFinite(lat)) {
        if (lng < minLng) minLng = lng;
        if (lng > maxLng) maxLng = lng;
        if (lat < minLat) minLat = lat;
        if (lat > maxLat) maxLat = lat;
      }
      return;
    }
    for (const child of coords) walk(child);
  };

  if (geojson.type === 'FeatureCollection' && Array.isArray(geojson.features)) {
    for (const f of geojson.features) {
      if (f?.geometry?.coordinates) walk(f.geometry.coordinates);
    }
  } else if (geojson.type === 'Feature' && geojson.geometry?.coordinates) {
    walk(geojson.geometry.coordinates);
  } else if (geojson.coordinates) {
    walk(geojson.coordinates);
  }

  if (!Number.isFinite(minLng)) return null;
  return [minLng, minLat, maxLng, maxLat];
}

export interface UtmCoord {
  easting: number;
  northing: number;
  zone: number;
  hemisphere: 'N' | 'S';
}

const WGS84_A = 6378137.0;
const WGS84_F = 1 / 298.257223563;
const WGS84_E2 = 2 * WGS84_F - WGS84_F * WGS84_F;
const WGS84_EP2 = WGS84_E2 / (1 - WGS84_E2);
const UTM_K0 = 0.9996;

/**
 * Konversi koordinat WGS84 (Longitude, Latitude) ke UTM (Easting, Northing, Zone, Hemisphere).
 */
export function lngLatToUtm(lng: number, lat: number, forcedZone?: number): UtmCoord {
  const zone = forcedZone ?? Math.max(1, Math.min(60, Math.floor((lng + 180) / 6) + 1));
  const hemisphere: 'N' | 'S' = lat < 0 ? 'S' : 'N';

  const latRad = (lat * Math.PI) / 180;
  const lngRad = (lng * Math.PI) / 180;
  const centralMeridian = ((zone - 1) * 6 - 180 + 3) * (Math.PI / 180);

  const sinLat = Math.sin(latRad);
  const cosLat = Math.cos(latRad);
  const tanLat = Math.tan(latRad);

  const n = WGS84_A / Math.sqrt(1 - WGS84_E2 * sinLat * sinLat);
  const t = tanLat * tanLat;
  const c = WGS84_EP2 * cosLat * cosLat;
  const aTerm = cosLat * (lngRad - centralMeridian);

  const e2 = WGS84_E2;
  const e4 = e2 * e2;
  const e6 = e4 * e2;

  const m =
    WGS84_A *
    ((1 - e2 / 4 - (3 * e4) / 64 - (5 * e6) / 256) * latRad -
      ((3 * e2) / 8 + (3 * e4) / 32 + (45 * e6) / 1024) * Math.sin(2 * latRad) +
      ((15 * e4) / 256 + (45 * e6) / 1024) * Math.sin(4 * latRad) -
      ((35 * e6) / 3072) * Math.sin(6 * latRad));

  const a2 = aTerm * aTerm;
  const a3 = a2 * aTerm;
  const a4 = a3 * aTerm;
  const a5 = a4 * aTerm;
  const a6 = a5 * aTerm;

  const easting =
    UTM_K0 *
      n *
      (aTerm +
        ((1 - t + c) * a3) / 6 +
        ((5 - 18 * t + t * t + 72 * c - 58 * WGS84_EP2) * a5) / 120) +
    500000.0;

  let northing =
    UTM_K0 *
    (m +
      n *
        tanLat *
        (a2 / 2 +
          ((5 - t + 9 * c + 4 * c * c) * a4) / 24 +
          ((61 - 58 * t + t * t + 600 * c - 330 * WGS84_EP2) * a6) / 720));

  if (hemisphere === 'S') {
    northing += 10000000.0;
  }

  return {
    easting: Number(easting.toFixed(2)),
    northing: Number(northing.toFixed(2)),
    zone,
    hemisphere,
  };
}

/**
 * Konversi koordinat UTM WGS84 (Easting, Northing, Zone, Hemisphere) ke [Longitude, Latitude] derajat desimal.
 */
export function utmToLngLat(
  easting: number,
  northing: number,
  zone: number,
  hemisphere: 'N' | 'S' = 'S',
): PublikasiLngLat | null {
  if (
    !Number.isFinite(easting) ||
    !Number.isFinite(northing) ||
    !Number.isFinite(zone) ||
    zone < 1 ||
    zone > 60
  ) {
    return null;
  }
  if (easting < 100000 || easting > 900000 || northing < 0 || northing > 10000000) {
    return null;
  }

  const x = easting - 500000.0;
  const y = hemisphere === 'S' ? northing - 10000000.0 : northing;

  const e2 = WGS84_E2;
  const e4 = e2 * e2;
  const e6 = e4 * e2;
  const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));
  const e1_2 = e1 * e1;
  const e1_3 = e1_2 * e1;
  const e1_4 = e1_3 * e1;

  const m = y / UTM_K0;
  const mu = m / (WGS84_A * (1 - e2 / 4 - (3 * e4) / 64 - (5 * e6) / 256));

  const phi1Rad =
    mu +
    ((3 * e1) / 2 - (27 * e1_3) / 32) * Math.sin(2 * mu) +
    ((21 * e1_2) / 16 - (55 * e1_4) / 32) * Math.sin(4 * mu) +
    ((151 * e1_3) / 96) * Math.sin(6 * mu) +
    ((1097 * e1_4) / 512) * Math.sin(8 * mu);

  const sinPhi1 = Math.sin(phi1Rad);
  const cosPhi1 = Math.cos(phi1Rad);
  const tanPhi1 = Math.tan(phi1Rad);

  const n1 = WGS84_A / Math.sqrt(1 - e2 * sinPhi1 * sinPhi1);
  const r1 = (WGS84_A * (1 - e2)) / Math.pow(1 - e2 * sinPhi1 * sinPhi1, 1.5);
  const t1 = tanPhi1 * tanPhi1;
  const c1 = WGS84_EP2 * cosPhi1 * cosPhi1;
  const d = x / (n1 * UTM_K0);

  const d2 = d * d;
  const d3 = d2 * d;
  const d4 = d3 * d;
  const d5 = d4 * d;
  const d6 = d5 * d;

  const latRad =
    phi1Rad -
    ((n1 * tanPhi1) / r1) *
      (d2 / 2 -
        ((5 + 3 * t1 + 10 * c1 - 4 * c1 * c1 - 9 * WGS84_EP2) * d4) / 24 +
        ((61 + 90 * t1 + 298 * c1 + 45 * t1 * t1 - 252 * WGS84_EP2 - 3 * c1 * c1) * d6) / 720);

  const centralMeridian = ((zone - 1) * 6 - 180 + 3) * (Math.PI / 180);
  const lngRad =
    centralMeridian +
    (d -
      ((1 + 2 * t1 + c1) * d3) / 6 +
      ((5 - 2 * c1 + 28 * t1 - 3 * c1 * c1 + 8 * WGS84_EP2 + 24 * t1 * t1) * d5) / 120) /
      cosPhi1;

  const lng = (lngRad * 180) / Math.PI;
  const lat = (latRad * 180) / Math.PI;

  if (!Number.isFinite(lng) || !Number.isFinite(lat) || lat < -85.05 || lat > 85.05) {
    return null;
  }

  return clampCoord(lng, lat);
}

export function parseCoordNumber(raw: string | number | null | undefined): number | null {
  if (raw === null || raw === undefined) return null;
  if (typeof raw === 'number') return Number.isFinite(raw) ? raw : null;
  const cleaned = raw.trim().replace(/\s+/g, '').replace(',', '.');
  if (!cleaned) return null;
  const n = Number(cleaned);
  return Number.isFinite(n) ? n : null;
}


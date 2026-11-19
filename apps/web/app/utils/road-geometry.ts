export type LngLat = [number, number];

export interface RoadProjectionResult {
  point: LngLat;
  distanceFromPointMeters: number;
  segmentIndex: number;
  t: number;
  alongMeters: number;
  totalMeters: number;
}

export interface SlicedRoadResult {
  coordinates: LngLat[];
  lengthMeters: number;
  startPoint: LngLat;
  endPoint: LngLat;
  startMeters: number;
  endMeters: number;
  reversed: boolean;
}

const EARTH_RADIUS_METERS = 6371008.8;

/**
 * Compute geodesic distance in meters between two WGS84 [lng, lat] points.
 */
export function haversineDistanceMeters(a: LngLat, b: LngLat): number {
  const lon1 = (a[0] * Math.PI) / 180;
  const lat1 = (a[1] * Math.PI) / 180;
  const lon2 = (b[0] * Math.PI) / 180;
  const lat2 = (b[1] * Math.PI) / 180;

  const dLat = lat2 - lat1;
  const dLon = lon2 - lon1;

  const sinDLat = Math.sin(dLat / 2);
  const sinDLon = Math.sin(dLon / 2);
  const h = sinDLat * sinDLat + Math.cos(lat1) * Math.cos(lat2) * (sinDLon * sinDLon);
  const c = 2 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)));

  return EARTH_RADIUS_METERS * c;
}

function roundCoord(val: number): number {
  return Math.round(val * 1e7) / 1e7;
}

/**
 * Remove consecutive duplicate coordinates closer than minDistanceMeters.
 */
export function deduplicateRoadCoordinates(coords: LngLat[], minDistanceMeters = 0.05): LngLat[] {
  const cleaned: LngLat[] = [];
  for (const pt of coords) {
    if (!Array.isArray(pt) || pt.length < 2) continue;
    const lng = Number(pt[0]);
    const lat = Number(pt[1]);
    if (!Number.isFinite(lng) || !Number.isFinite(lat)) continue;
    const formatted: LngLat = [roundCoord(lng), roundCoord(lat)];
    const prev = cleaned[cleaned.length - 1];
    if (!prev || haversineDistanceMeters(prev, formatted) >= minDistanceMeters) {
      cleaned.push(formatted);
    }
  }
  return cleaned;
}

/**
 * Extract and merge LineString / MultiLineString GeoJSON coordinates into a single ordered chain.
 */
export function extractMergedRoadCoordinates(geoJsonOrFeature: any): LngLat[] {
  if (!geoJsonOrFeature) return [];

  let raw = geoJsonOrFeature;
  if (typeof raw === 'string') {
    try {
      raw = JSON.parse(raw);
    } catch {
      return [];
    }
  }

  if (raw?.geometry) {
    raw = raw.geometry;
  } else if (raw?.geojson) {
    return extractMergedRoadCoordinates(raw.geojson);
  }

  const type = String(raw?.type || '').toLowerCase();
  const coords = raw?.coordinates;
  if (!Array.isArray(coords) || coords.length === 0) return [];

  if (type === 'linestring') {
    return deduplicateRoadCoordinates(coords as LngLat[]);
  }

  if (type !== 'multilinestring') {
    return [];
  }

  const parts: LngLat[][] = coords
    .filter((line: any) => Array.isArray(line) && line.length >= 2)
    .map((line: any) =>
      line
        .filter((pt: any) => Array.isArray(pt) && pt.length >= 2 && Number.isFinite(Number(pt[0])) && Number.isFinite(Number(pt[1])))
        .map((pt: any) => [Number(pt[0]), Number(pt[1])] as LngLat),
    )
    .filter((line: LngLat[]) => line.length >= 2);

  if (parts.length === 0) return [];
  if (parts.length === 1) return deduplicateRoadCoordinates(parts[0]!);

  // Merge MultiLineString parts greedily by nearest endpoint (mirrors ST_LineMerge behavior)
  const remaining = [...parts];
  let chain = [...remaining.shift()!];

  while (remaining.length > 0) {
    let bestIdx = 0;
    let bestMode: 'append' | 'append_rev' | 'prepend' | 'prepend_rev' = 'append';
    let bestDist = Infinity;

    const chainStart = chain[0]!;
    const chainEnd = chain[chain.length - 1]!;

    remaining.forEach((part, idx) => {
      const partStart = part[0]!;
      const partEnd = part[part.length - 1]!;

      const dEndStart = haversineDistanceMeters(chainEnd, partStart);
      if (dEndStart < bestDist) {
        bestDist = dEndStart;
        bestIdx = idx;
        bestMode = 'append';
      }

      const dEndEnd = haversineDistanceMeters(chainEnd, partEnd);
      if (dEndEnd < bestDist) {
        bestDist = dEndEnd;
        bestIdx = idx;
        bestMode = 'append_rev';
      }

      const dStartEnd = haversineDistanceMeters(chainStart, partEnd);
      if (dStartEnd < bestDist) {
        bestDist = dStartEnd;
        bestIdx = idx;
        bestMode = 'prepend';
      }

      const dStartStart = haversineDistanceMeters(chainStart, partStart);
      if (dStartStart < bestDist) {
        bestDist = dStartStart;
        bestIdx = idx;
        bestMode = 'prepend_rev';
      }
    });

    const chosen = remaining.splice(bestIdx, 1)[0]!;
    if (bestMode === 'append') {
      chain = [...chain, ...chosen];
    } else if (bestMode === 'append_rev') {
      chain = [...chain, ...[...chosen].reverse()];
    } else if (bestMode === 'prepend') {
      chain = [...chosen, ...chain];
    } else {
      chain = [...[...chosen].reverse(), ...chain];
    }
  }

  return deduplicateRoadCoordinates(chain);
}

/**
 * Compute cumulative geodesic distances (in meters) at each vertex along a polyline.
 */
export function computeCumulativeDistances(coords: LngLat[]): { cumulative: number[]; totalMeters: number } {
  if (coords.length === 0) return { cumulative: [], totalMeters: 0 };
  const cumulative = [0];
  let total = 0;
  for (let i = 0; i < coords.length - 1; i++) {
    total += haversineDistanceMeters(coords[i]!, coords[i + 1]!);
    cumulative.push(total);
  }
  return { cumulative, totalMeters: total };
}

/**
 * Project a [lng, lat] point onto the nearest point on a road polyline.
 */
export function projectPointOntoRoad(
  pt: LngLat,
  coords: LngLat[],
  precomputed?: { cumulative: number[]; totalMeters: number },
): RoadProjectionResult | null {
  if (coords.length < 2) return null;

  const { cumulative, totalMeters } = precomputed ?? computeCumulativeDistances(coords);
  const px = pt[0];
  const py = pt[1];

  let minDist = Infinity;
  let bestPoint: LngLat = [roundCoord(coords[0]![0]), roundCoord(coords[0]![1])];
  let bestSegIdx = 0;
  let bestT = 0;
  let bestAlong = 0;

  for (let i = 0; i < coords.length - 1; i++) {
    const a = coords[i]!;
    const b = coords[i + 1]!;
    const x1 = a[0];
    const y1 = a[1];
    const x2 = b[0];
    const y2 = b[1];

    const segLen = (cumulative[i + 1] ?? 0) - (cumulative[i] ?? 0);
    const cosLat = Math.cos((((y1 + y2) / 2) * Math.PI) / 180);

    const dx = (x2 - x1) * cosLat;
    const dy = y2 - y1;
    const lenSq = dx * dx + dy * dy;

    let t = 0;
    if (lenSq > 1e-16) {
      t = (((px - x1) * cosLat * dx) + ((py - y1) * dy)) / lenSq;
      t = Math.max(0, Math.min(1, t));
    }

    const projPt: LngLat = [
      roundCoord(x1 + t * (x2 - x1)),
      roundCoord(y1 + t * (y2 - y1)),
    ];
    const dist = haversineDistanceMeters(pt, projPt);

    if (dist < minDist) {
      minDist = dist;
      bestPoint = projPt;
      bestSegIdx = i;
      bestT = t;
      bestAlong = (cumulative[i] ?? 0) + t * segLen;
    }
  }

  return {
    point: bestPoint,
    distanceFromPointMeters: Math.round(minDist * 100) / 100,
    segmentIndex: bestSegIdx,
    t: bestT,
    alongMeters: Math.round(bestAlong * 100) / 100,
    totalMeters: Math.round(totalMeters * 100) / 100,
  };
}

/**
 * Interpolate the [lng, lat] point at a given chainage distance along the road polyline.
 */
export function getPointAtDistance(
  coords: LngLat[],
  distanceMeters: number,
  precomputed?: { cumulative: number[]; totalMeters: number },
): { point: LngLat; segmentIndex: number; t: number; clampedMeters: number } | null {
  if (coords.length < 2) return null;
  const { cumulative, totalMeters } = precomputed ?? computeCumulativeDistances(coords);
  const clamped = Math.max(0, Math.min(totalMeters, distanceMeters));

  if (clamped <= 0) {
    return {
      point: [roundCoord(coords[0]![0]), roundCoord(coords[0]![1])],
      segmentIndex: 0,
      t: 0,
      clampedMeters: 0,
    };
  }

  if (clamped >= totalMeters) {
    const lastIdx = coords.length - 1;
    return {
      point: [roundCoord(coords[lastIdx]![0]), roundCoord(coords[lastIdx]![1])],
      segmentIndex: Math.max(0, lastIdx - 1),
      t: 1,
      clampedMeters: totalMeters,
    };
  }

  for (let i = 0; i < coords.length - 1; i++) {
    const dStart = cumulative[i] ?? 0;
    const dEnd = cumulative[i + 1] ?? 0;
    if (clamped <= dEnd || i === coords.length - 2) {
      const segLen = dEnd - dStart;
      const t = segLen > 1e-9 ? Math.max(0, Math.min(1, (clamped - dStart) / segLen)) : 0;
      const a = coords[i]!;
      const b = coords[i + 1]!;
      return {
        point: [
          roundCoord(a[0] + t * (b[0] - a[0])),
          roundCoord(a[1] + t * (b[1] - a[1])),
        ],
        segmentIndex: i,
        t,
        clampedMeters: clamped,
      };
    }
  }

  return null;
}

/**
 * Slice a sub-LineString along a road polyline between startMeters and endMeters.
 * Preserves intermediate road vertices and direction (if startMeters > endMeters, output is reversed).
 */
export function sliceRoadByDistances(
  coords: LngLat[],
  startMeters: number,
  endMeters: number,
  minSegmentMeters = 1,
): SlicedRoadResult | null {
  if (coords.length < 2) return null;
  const stats = computeCumulativeDistances(coords);
  if (stats.totalMeters < minSegmentMeters) return null;

  const clampedStart = Math.max(0, Math.min(stats.totalMeters, startMeters));
  const clampedEnd = Math.max(0, Math.min(stats.totalMeters, endMeters));

  if (Math.abs(clampedEnd - clampedStart) < minSegmentMeters) {
    return null;
  }

  const reversed = clampedStart > clampedEnd;
  const lowM = reversed ? clampedEnd : clampedStart;
  const highM = reversed ? clampedStart : clampedEnd;

  const startLoc = getPointAtDistance(coords, lowM, stats);
  const endLoc = getPointAtDistance(coords, highM, stats);
  if (!startLoc || !endLoc) return null;

  const rawSliced: LngLat[] = [startLoc.point];
  for (let idx = startLoc.segmentIndex + 1; idx <= endLoc.segmentIndex; idx++) {
    const vertex = coords[idx];
    if (vertex) {
      rawSliced.push([roundCoord(vertex[0]), roundCoord(vertex[1])]);
    }
  }
  rawSliced.push(endLoc.point);

  const oriented = reversed ? rawSliced.reverse() : rawSliced;
  let cleaned = deduplicateRoadCoordinates(oriented);
  if (cleaned.length < 2) {
    cleaned = [oriented[0]!, oriented[oriented.length - 1]!];
  }

  const lengthMeters = Math.round(computeCumulativeDistances(cleaned).totalMeters * 100) / 100;
  if (lengthMeters < minSegmentMeters) return null;

  return {
    coordinates: cleaned,
    lengthMeters,
    startPoint: cleaned[0]!,
    endPoint: cleaned[cleaned.length - 1]!,
    startMeters: Math.round(clampedStart * 100) / 100,
    endMeters: Math.round(clampedEnd * 100) / 100,
    reversed,
  };
}

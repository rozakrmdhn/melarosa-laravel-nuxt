import type {
  IsometricBuildingFeature,
  IsometricDesaBoundaryFeature,
  IsometricInfraFeature,
  IsometricInspectResult,
  IsometricLandmarkFeature,
  IsometricParkFeature,
  IsometricRiverFeature,
  IsometricRoadFeature,
  IsometricSpatialIndexPayload,
  IsometricTileMetadata,
} from '~/types/isometric'
import {
  DEFAULT_TRANSFORM_PARAMS,
  isometricToProjected,
  pixelToIsometric,
  projectedToGeographic,
} from '~/composables/useGeographicTransform'

const METADATA_URL = '/tiles/bojonegoro-isometric/metadata.json'
const FEATURES_URL = '/tiles/bojonegoro-isometric/features.json'
const SPATIAL_CELL_SIZE = 220.0

let cachedMetadata: IsometricTileMetadata | null = null
let cachedSpatialIndex: IsometricSpatialIndexPayload | null = null
let metadataPromise: Promise<IsometricTileMetadata> | null = null
let spatialPromise: Promise<IsometricSpatialIndexPayload> | null = null
let buildingGrid: Map<string, IsometricBuildingFeature[]> | null = null

function cellKey(cx: number, cy: number): string {
  return `${cx}:${cy}`
}

export function unpackBuildingIsoRing(bldg: IsometricBuildingFeature): [number, number][] {
  const flat = bldg.flatIsoRing
  const ring: [number, number][] = []
  for (let i = 0; i + 1 < flat.length; i += 2) {
    ring.push([flat[i]!, flat[i + 1]!])
  }
  return ring
}

function ensureBuildingGrid(payload: IsometricSpatialIndexPayload): Map<string, IsometricBuildingFeature[]> {
  if (buildingGrid) return buildingGrid
  const grid = new Map<string, IsometricBuildingFeature[]>()
  for (const bldg of payload.buildings) {
    const [minX, minY, maxX, maxY] = bldg.isoBbox
    const x0 = Math.floor(minX / SPATIAL_CELL_SIZE)
    const x1 = Math.floor(maxX / SPATIAL_CELL_SIZE)
    const y0 = Math.floor((minY - bldg.heightM * 3.2) / SPATIAL_CELL_SIZE)
    const y1 = Math.floor(maxY / SPATIAL_CELL_SIZE)
    for (let cy = y0; cy <= y1; cy++) {
      for (let cx = x0; cx <= x1; cx++) {
        const key = cellKey(cx, cy)
        const bucket = grid.get(key)
        if (bucket) {
          bucket.push(bldg)
        } else {
          grid.set(key, [bldg])
        }
      }
    }
  }
  buildingGrid = grid
  return grid
}

function pointInRing(x: number, y: number, ring: [number, number][]): boolean {
  if (ring.length < 3) return false
  let inside = false
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i]![0]
    const yi = ring[i]![1]
    const xj = ring[j]![0]
    const yj = ring[j]![1]
    const intersect = yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi + 1e-12) + xi
    if (intersect) inside = !inside
  }
  return inside
}

function pointInFlatRing(x: number, y: number, flat: number[]): boolean {
  const len = flat.length
  if (len < 6) return false
  let inside = false
  let jx = flat[len - 2]!
  let jy = flat[len - 1]!
  for (let i = 0; i + 1 < len; i += 2) {
    const ix = flat[i]!
    const iy = flat[i + 1]!
    const intersect = iy > y !== jy > y && x < ((jx - ix) * (y - iy)) / (jy - iy + 1e-12) + ix
    if (intersect) inside = !inside
    jx = ix
    jy = iy
  }
  return inside
}

function pointToSegmentDist(
  px: number,
  py: number,
  ax: number,
  ay: number,
  bx: number,
  by: number,
): number {
  const vx = bx - ax
  const vy = by - ay
  const lenSq = vx * vx + vy * vy
  const t = lenSq === 0 ? 0 : Math.max(0, Math.min(1, ((px - ax) * vx + (py - ay) * vy) / lenSq))
  const cx = ax + t * vx
  const cy = ay + t * vy
  return Math.hypot(px - cx, py - cy)
}

function pointToPolylineDist(x: number, y: number, line: [number, number][]): number {
  let minD = Number.POSITIVE_INFINITY
  for (let i = 0; i < line.length - 1; i++) {
    const p0 = line[i]!
    const p1 = line[i + 1]!
    const d = pointToSegmentDist(x, y, p0[0], p0[1], p1[0], p1[1])
    if (d < minD) minD = d
  }
  return minD
}

export async function fetchIsometricMetadata(): Promise<IsometricTileMetadata> {
  if (cachedMetadata) return cachedMetadata
  if (!metadataPromise) {
    metadataPromise = fetch(METADATA_URL, { cache: 'no-cache' })
      .then(async (res) => {
        if (!res.ok) {
          throw new Error(`Gagal memuat metadata tile (${res.status})`)
        }
        const data = (await res.json()) as IsometricTileMetadata
        cachedMetadata = data
        return data
      })
      .finally(() => {
        metadataPromise = null
      })
  }
  return metadataPromise
}

export async function fetchIsometricSpatialIndex(
  cacheVersion?: string,
): Promise<IsometricSpatialIndexPayload> {
  if (cachedSpatialIndex) return cachedSpatialIndex
  if (!spatialPromise) {
    const url = cacheVersion
      ? `${FEATURES_URL}?v=${encodeURIComponent(cacheVersion)}`
      : FEATURES_URL
    spatialPromise = fetch(url)
      .then(async (res) => {
        if (!res.ok) {
          throw new Error(`Gagal memuat indeks spasial (${res.status})`)
        }
        const data = (await res.json()) as IsometricSpatialIndexPayload
        cachedSpatialIndex = data
        ensureBuildingGrid(data)
        return data
      })
      .finally(() => {
        spatialPromise = null
      })
  }
  return spatialPromise
}

export function resolveTileUrl(
  z: number,
  x: number,
  y: number,
  template = '/tiles/bojonegoro-isometric/{z}/{x}/{y}.png',
  cacheVersion?: string,
): string {
  const baseUrl = template
    .replace('{z}', String(z))
    .replace('{x}', String(x))
    .replace('{y}', String(y))
  if (!cacheVersion) return baseUrl
  const sep = baseUrl.includes('?') ? '&' : '?'
  return `${baseUrl}${sep}v=${encodeURIComponent(cacheVersion)}`
}

export function inspectIsometricPixel(
  px: number,
  py: number,
  zoom: number,
  metadata: IsometricTileMetadata | null,
  spatial: IsometricSpatialIndexPayload | null,
): IsometricInspectResult {
  const params = metadata?.coordinateTransformationParameters ?? DEFAULT_TRANSFORM_PARAMS
  const isometric = pixelToIsometric(px, py, zoom, params)
  const projected = isometricToProjected(isometric.isoX, isometric.isoY, params)
  const geographic = projectedToGeographic(projected.easting, projected.northing)

  if (!spatial) {
    return {
      pixel: { px, py },
      zoom,
      isometric,
      projected,
      geographic,
      desa: null,
      building: null,
      buildingIsoRing: null,
      road: null,
      infrastructure: null,
      park: null,
      river: null,
      landmark: null,
      distanceMeters: null,
    }
  }

  const ix = isometric.isoX
  const iy = isometric.isoY

  // 1. Administrative Desa/Kelurahan containment (PostGIS)
  let matchedDesa: IsometricDesaBoundaryFeature | null = null
  for (const desa of spatial.desaBoundaries) {
    if (pointInRing(ix, iy, desa.isoRing)) {
      matchedDesa = desa
      break
    }
  }

  // 2. Real OSM Building lookup (ground footprint + 3D extruded roof/facade hit test)
  const grid = ensureBuildingGrid(spatial)
  const cx = Math.floor(ix / SPATIAL_CELL_SIZE)
  const cy = Math.floor(iy / SPATIAL_CELL_SIZE)
  const candidates = new Map<string, IsometricBuildingFeature>()

  for (let dy = -1; dy <= 1; dy++) {
    for (let dx = -1; dx <= 1; dx++) {
      const list = grid.get(cellKey(cx + dx, cy + dy))
      if (!list) continue
      for (const b of list) {
        candidates.set(b.id, b)
      }
    }
  }

  const zoomBoost =
    ({ 0: 2.8, 1: 2.1, 2: 1.55, 3: 1.25, 4: 1.05 } as Record<number, number>)[zoom] ?? 1.05
  let bestBuilding: IsometricBuildingFeature | null = null
  let bestBldgDist = Number.POSITIVE_INFINITY

  for (const bldg of candidates.values()) {
    const [minX, minY, maxX, maxY] = bldg.isoBbox
    const extrusionShift = bldg.heightM * zoomBoost * params.verticalExaggeration
    // Exact ground footprint hit
    if (ix >= minX && ix <= maxX && iy >= minY && iy <= maxY && pointInFlatRing(ix, iy, bldg.flatIsoRing)) {
      bestBuilding = bldg
      bestBldgDist = 0
      break
    }
    // Extruded roof/wall hit -> resolves back to the building's Ground Anchor
    if (ix >= minX - 4 && ix <= maxX + 4 && iy >= minY - extrusionShift - 4 && iy <= maxY + 4) {
      const anchorIso = bldg.anchorIso
      const d = Math.hypot(ix - anchorIso[0], iy - (anchorIso[1] - extrusionShift * 0.45))
      if (d < bestBldgDist && d <= Math.max(22, (maxX - minX) * 0.85)) {
        bestBldgDist = d
        bestBuilding = bldg
      }
    }
  }

  // 3. Park / Pitch / Forest containment
  let matchedPark: IsometricParkFeature | null = null
  for (const park of spatial.parks) {
    const [minX, minY, maxX, maxY] = park.isoBbox
    if (ix >= minX && ix <= maxX && iy >= minY && iy <= maxY && pointInRing(ix, iy, park.isoRing)) {
      matchedPark = park
      break
    }
  }

  // 4. Nearest PostGIS Infrastructure segment
  let bestInfra: IsometricInfraFeature | null = null
  let bestInfraDist = 42.0
  for (const inf of spatial.infrastructure) {
    const [minX, minY, maxX, maxY] = inf.isoBbox
    if (ix < minX - 45 || ix > maxX + 45 || iy < minY - 45 || iy > maxY + 45) continue
    const d = pointToPolylineDist(ix, iy, inf.isoLine)
    if (d < bestInfraDist) {
      bestInfraDist = d
      bestInfra = inf
    }
  }

  // 5. Nearest OSM / PostGIS Road or Railway segment
  let bestRoad: IsometricRoadFeature | null = null
  let bestRoadDist = 36.0
  for (const road of spatial.roads) {
    const [minX, minY, maxX, maxY] = road.isoBbox
    if (ix < minX - 40 || ix > maxX + 40 || iy < minY - 40 || iy > maxY + 40) continue
    const d = pointToPolylineDist(ix, iy, road.isoLine)
    if (d < bestRoadDist) {
      bestRoadDist = d
      bestRoad = road
    }
  }

  // 6. Nearest OSM River / Waterway
  let bestRiver: IsometricRiverFeature | null = null
  let bestRiverDist = 68.0
  for (const rv of spatial.rivers) {
    const d = pointToPolylineDist(ix, iy, rv.isoCenterline)
    const threshold = Math.max(22.0, rv.widthM * 0.75)
    if (d <= threshold && d < bestRiverDist) {
      bestRiverDist = d
      bestRiver = rv
    }
  }

  // 7. Landmark match
  let matchedLandmark: IsometricLandmarkFeature | null = null
  for (const lm of spatial.landmarks) {
    if (bestBuilding && lm.building_id === bestBuilding.id) {
      matchedLandmark = lm
      break
    }
    if (matchedPark && lm.park_id === matchedPark.id) {
      matchedLandmark = lm
      break
    }
    const d = Math.hypot(ix - lm.ground_anchor.isometric[0], iy - lm.ground_anchor.isometric[1])
    if (d <= 55.0) {
      matchedLandmark = lm
      break
    }
  }

  // Always compute Ground Anchor coordinates dynamically from isometric space
  const effectiveIso = bestBuilding
    ? { isoX: bestBuilding.anchorIso[0], isoY: bestBuilding.anchorIso[1] }
    : isometric
  const effectiveProj = bestBuilding
    ? isometricToProjected(effectiveIso.isoX, effectiveIso.isoY, params)
    : projected
  const effectiveGeo = bestBuilding
    ? projectedToGeographic(effectiveProj.easting, effectiveProj.northing)
    : geographic

  return {
    pixel: { px, py },
    zoom,
    isometric: effectiveIso,
    projected: effectiveProj,
    geographic: effectiveGeo,
    desa: matchedDesa,
    building: bestBuilding,
    buildingIsoRing: bestBuilding ? unpackBuildingIsoRing(bestBuilding) : null,
    road: bestRoad,
    infrastructure: bestInfra,
    park: matchedPark,
    river: bestRiver,
    landmark: matchedLandmark,
    distanceMeters: bestBuilding
      ? Math.round(bestBldgDist * 10) / 10
      : bestRoad
        ? Math.round(bestRoadDist * 10) / 10
        : null,
  }
}

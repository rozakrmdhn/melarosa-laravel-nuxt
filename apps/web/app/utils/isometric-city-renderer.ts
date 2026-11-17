import type {
  BojonegoroIsometricDataset,
  IsoBasemapStyle,
  IsoBuildingKind,
  IsoBuildingRenderStyle,
  IsoInspectedFeature,
  IsoLandmark,
  IsoRoadKind,
  IsoRoofStyle,
  IsoRotation,
  IsoThemeMode,
  LngLat,
} from "~/types/isometric-map";

export interface WorldPoint {
  x: number;
  y: number;
}

interface PreparedBuilding {
  id: string;
  name: string;
  kind: IsoBuildingKind;
  roofStyle: IsoRoofStyle;
  ring: WorldPoint[];
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
  cx: number;
  cy: number;
  centroidCoord: LngLat;
  levels: number;
  heightMeters: number;
  address?: string;
  kelurahan?: string;
  landmarkId?: string;
  source: "osm" | "curated";
  seed: number;
}

interface PreparedRoad {
  id: string;
  name: string;
  kind: IsoRoadKind;
  points: WorldPoint[];
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
  widthMeters: number;
  totalLengthMeters: number;
  midpointCoord: LngLat;
  source: "osm" | "curated";
}

interface PreparedWater {
  id: string;
  name: string;
  geometryType: "polygon" | "line";
  points: WorldPoint[];
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
  widthMeters: number;
  midpointCoord: LngLat;
}

interface PreparedGreenZone {
  id: string;
  name: string;
  kind: "alun_alun" | "park" | "forest" | "field";
  ring: WorldPoint[];
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
  centroidCoord: LngLat;
}

interface PreparedTree {
  id: string;
  x: number;
  y: number;
  size: number;
  variant: number;
}

interface PreparedLamp {
  x: number;
  y: number;
}

interface TrafficTrack {
  road: PreparedRoad;
  cumLengths: number[];
  isRail: boolean;
}

interface PreparedVehicle {
  trackIdx: number;
  offsetFraction: number;
  speedMetersPerSec: number;
  direction: 1 | -1;
  colorDay: string;
  isTrain: boolean;
}

export interface IsoCameraState {
  centerX: number;
  centerY: number;
  zoom: number; // buffer pixels per meter
  rotation: IsoRotation;
  pixelScale: 1 | 2 | 3;
}

export interface ProjectedLandmarkPin {
  landmark: IsoLandmark;
  screenX: number;
  screenY: number;
  visible: boolean;
}

export interface PreparedIsoScene {
  dataset: BojonegoroIsometricDataset;
  cosCenterLat: number;
  boundary: WorldPoint[];
  kelurahanBoundaries: WorldPoint[][];
  waterways: PreparedWater[];
  greenZones: PreparedGreenZone[];
  roads: PreparedRoad[];
  buildings: PreparedBuilding[];
  trees: PreparedTree[];
  lamps: PreparedLamp[];
  tracks: TrafficTrack[];
  vehicles: PreparedVehicle[];
  landmarks: Array<{ landmark: IsoLandmark; wx: number; wy: number }>;
  boundsMeters: { minX: number; maxX: number; minY: number; maxY: number };
}

interface HitTargetBuilding {
  building: PreparedBuilding;
  hull: Array<[number, number]>;
}

const DEG_TO_M_LAT = 110540;
const DEG_TO_M_LNG = 111320;
const VERTICAL_EXAGGERATION = 1.35;

function hashString(input: string): number {
  let h = 2166136261 >>> 0;
  for (let i = 0; i < input.length; i++) {
    h ^= input.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

function detRand(seed: number): number {
  let x = (seed ^ 0x9e3779b9) >>> 0;
  x = Math.imul(x ^ (x >>> 15), 0x85ebca6b) >>> 0;
  x = Math.imul(x ^ (x >>> 13), 0xc2b2ae35) >>> 0;
  return (x ^ (x >>> 16)) / 4294967295;
}

export function lngLatToWorld(coord: LngLat, center: LngLat, cosLat: number): WorldPoint {
  return {
    x: (coord[0] - center[0]) * DEG_TO_M_LNG * cosLat,
    y: -(coord[1] - center[1]) * DEG_TO_M_LAT,
  };
}

export function worldToLngLat(pt: WorldPoint, center: LngLat, cosLat: number): LngLat {
  return [
    center[0] + pt.x / (DEG_TO_M_LNG * cosLat),
    center[1] - pt.y / DEG_TO_M_LAT,
  ];
}

function rotateDelta(dx: number, dy: number, rot: IsoRotation): [number, number] {
  if (rot === 1) return [dy, -dx];
  if (rot === 2) return [-dx, -dy];
  if (rot === 3) return [-dy, dx];
  return [dx, dy];
}

function unrotateDelta(dx: number, dy: number, rot: IsoRotation): [number, number] {
  if (rot === 1) return [-dy, dx];
  if (rot === 2) return [-dx, -dy];
  if (rot === 3) return [dy, -dx];
  return [dx, dy];
}

function worldToBuffer(
  wx: number,
  wy: number,
  wz: number,
  cam: IsoCameraState,
  halfBufW: number,
  halfBufH: number
): [number, number] {
  const [dx, dy] = rotateDelta(wx - cam.centerX, wy - cam.centerY, cam.rotation);
  const sx = halfBufW + (dx + dy) * cam.zoom;
  const sy = halfBufH + (dy - dx) * 0.5 * cam.zoom - wz * cam.zoom * VERTICAL_EXAGGERATION;
  return [sx, sy];
}

export function screenToWorld(
  cssX: number,
  cssY: number,
  cam: IsoCameraState,
  viewportW: number,
  viewportH: number
): WorldPoint {
  const bufW = Math.max(1, Math.ceil(viewportW / cam.pixelScale));
  const bufH = Math.max(1, Math.ceil(viewportH / cam.pixelScale));
  const bx = cssX / cam.pixelScale;
  const by = cssY / cam.pixelScale;
  const u = (bx - bufW * 0.5) / cam.zoom;
  const v = (by - bufH * 0.5) / (0.5 * cam.zoom);
  const rx = (u - v) * 0.5;
  const ry = (u + v) * 0.5;
  const [dx, dy] = unrotateDelta(rx, ry, cam.rotation);
  return {
    x: cam.centerX + dx,
    y: cam.centerY + dy,
  };
}

function computeBounds(points: WorldPoint[]) {
  let minX = Infinity;
  let maxX = -Infinity;
  let minY = Infinity;
  let maxY = -Infinity;
  for (const p of points) {
    if (p.x < minX) minX = p.x;
    if (p.x > maxX) maxX = p.x;
    if (p.y < minY) minY = p.y;
    if (p.y > maxY) maxY = p.y;
  }
  return { minX, maxX, minY, maxY };
}

// Pastikan ring terurut searah jarum jam pada sistem koordinat (x kanan, y bawah)
function normalizeClockwiseRing(ring: WorldPoint[]): WorldPoint[] {
  const open = ring.length > 1 && ring[0]!.x === ring[ring.length - 1]!.x && ring[0]!.y === ring[ring.length - 1]!.y
    ? ring.slice(0, -1)
    : [...ring];
  if (open.length < 3) return open;

  let area2 = 0;
  for (let i = 0; i < open.length; i++) {
    const a = open[i]!;
    const b = open[(i + 1) % open.length]!;
    area2 += a.x * b.y - b.x * a.y;
  }
  // Pada y-down, signed area positif = searah jarum jam (CW)
  if (area2 < 0) open.reverse();
  return open;
}

function pointInRing(x: number, y: number, ring: WorldPoint[]): boolean {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i]!.x;
    const yi = ring[i]!.y;
    const xj = ring[j]!.x;
    const yj = ring[j]!.y;
    const intersect = yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi + 1e-9) + xi;
    if (intersect) inside = !inside;
  }
  return inside;
}

function pointInScreenPoly(px: number, py: number, poly: Array<[number, number]>): boolean {
  let inside = false;
  for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    const [xi, yi] = poly[i]!;
    const [xj, yj] = poly[j]!;
    const intersect = yi > py !== yj > py && px < ((xj - xi) * (py - yi)) / (yj - yi + 1e-9) + xi;
    if (intersect) inside = !inside;
  }
  return inside;
}

function distanceToSegmentSq(px: number, py: number, ax: number, ay: number, bx: number, by: number): number {
  const vx = bx - ax;
  const vy = by - ay;
  const lenSq = vx * vx + vy * vy;
  if (lenSq === 0) return (px - ax) ** 2 + (py - ay) ** 2;
  const t = Math.max(0, Math.min(1, ((px - ax) * vx + (py - ay) * vy) / lenSq));
  const cx = ax + t * vx;
  const cy = ay + t * vy;
  return (px - cx) ** 2 + (py - cy) ** 2;
}

export function prepareIsoScene(dataset: BojonegoroIsometricDataset): PreparedIsoScene {
  const center = dataset.center;
  const cosLat = Math.cos((center[1] * Math.PI) / 180);

  const boundary = dataset.boundary.ring.map((c) => lngLatToWorld(c, center, cosLat));
  const kelurahanBoundaries = (dataset.kelurahanBoundaries || []).map((k) =>
    k.ring.map((c) => lngLatToWorld(c, center, cosLat))
  );

  const waterways: PreparedWater[] = dataset.waterways.map((w) => {
    const points = w.coords.map((c) => lngLatToWorld(c, center, cosLat));
    const b = computeBounds(points);
    const midIdx = Math.floor(w.coords.length / 2);
    return {
      id: w.id,
      name: w.name,
      geometryType: w.geometryType,
      points,
      ...b,
      widthMeters: w.widthMeters,
      midpointCoord: w.coords[midIdx] || center,
    };
  });

  const greenZones: PreparedGreenZone[] = dataset.greenZones.map((g) => {
    const ring = normalizeClockwiseRing(g.ring.map((c) => lngLatToWorld(c, center, cosLat)));
    const b = computeBounds(ring);
    const cx = (b.minX + b.maxX) * 0.5;
    const cy = (b.minY + b.maxY) * 0.5;
    return {
      id: g.id,
      name: g.name,
      kind: g.kind,
      ring,
      ...b,
      centroidCoord: worldToLngLat({ x: cx, y: cy }, center, cosLat),
    };
  });

  const roads: PreparedRoad[] = [];
  const tracks: TrafficTrack[] = [];
  const lamps: PreparedLamp[] = [];

  for (const r of dataset.roads) {
    const points = r.coords.map((c) => lngLatToWorld(c, center, cosLat));
    if (points.length < 2) continue;
    const b = computeBounds(points);
    const cumLengths: number[] = [0];
    let totalLen = 0;
    for (let i = 1; i < points.length; i++) {
      const segLen = Math.hypot(points[i]!.x - points[i - 1]!.x, points[i]!.y - points[i - 1]!.y);
      totalLen += segLen;
      cumLengths.push(totalLen);
    }
    const midIdx = Math.floor(r.coords.length / 2);
    const prepared: PreparedRoad = {
      id: r.id,
      name: r.name,
      kind: r.kind,
      points,
      ...b,
      widthMeters: r.widthMeters,
      totalLengthMeters: totalLen,
      midpointCoord: r.coords[midIdx] || center,
      source: r.source,
    };
    roads.push(prepared);

    if ((r.kind === "primary" || r.kind === "secondary" || r.kind === "bridge" || r.kind === "rail") && totalLen > 140) {
      tracks.push({
        road: prepared,
        cumLengths,
        isRail: r.kind === "rail",
      });
    }

    if ((r.kind === "primary" || r.kind === "bridge") && totalLen > 80) {
      const step = 68;
      let acc = 20;
      for (let i = 1; i < points.length; i++) {
        const ax = points[i - 1]!.x;
        const ay = points[i - 1]!.y;
        const bx = points[i]!.x;
        const by = points[i]!.y;
        const seg = Math.hypot(bx - ax, by - ay);
        while (acc <= seg) {
          const t = acc / seg;
          lamps.push({
            x: ax + (bx - ax) * t,
            y: ay + (by - ay) * t,
          });
          acc += step;
        }
        acc -= seg;
      }
    }
  }

  const buildings: PreparedBuilding[] = dataset.buildings
    .map((b) => {
      const ring = normalizeClockwiseRing(b.ring.map((c) => lngLatToWorld(c, center, cosLat)));
      if (ring.length < 3) return null;
      const bounds = computeBounds(ring);
      let sx = 0;
      let sy = 0;
      for (const p of ring) {
        sx += p.x;
        sy += p.y;
      }
      const cx = sx / ring.length;
      const cy = sy / ring.length;
      return {
        id: b.id,
        name: b.name,
        kind: b.kind,
        roofStyle: b.roofStyle,
        ring,
        ...bounds,
        cx,
        cy,
        centroidCoord: worldToLngLat({ x: cx, y: cy }, center, cosLat),
        levels: b.levels,
        heightMeters: b.heightMeters,
        address: b.address,
        kelurahan: b.kelurahan,
        landmarkId: b.landmarkId,
        source: b.source,
        seed: hashString(b.id),
      } as PreparedBuilding;
    })
    .filter((b): b is PreparedBuilding => b !== null);

  // Sebar pohon piksel deterministik di zona RTH (tanpa memadati lapangan bola)
  const trees: PreparedTree[] = [];
  for (const gz of dataset.greenZones) {
    const pZone = greenZones.find((z) => z.id === gz.id);
    if (!pZone) continue;
    const spacing = gz.kind === "field" ? 36 : 19;
    const seedBase = hashString(gz.id);
    let idx = 0;
    for (let x = pZone.minX + 6; x <= pZone.maxX - 6; x += spacing) {
      for (let y = pZone.minY + 6; y <= pZone.maxY - 6; y += spacing) {
        idx++;
        const r1 = detRand(seedBase + idx * 17);
        if (r1 > gz.treeDensity) continue;
        const jx = x + (detRand(seedBase + idx * 31) - 0.5) * spacing * 0.65;
        const jy = y + (detRand(seedBase + idx * 47) - 0.5) * spacing * 0.65;
        if (!pointInRing(jx, jy, pZone.ring)) continue;
        // Kosongkan bagian tengah Alun-Alun sebagai plaza upacara
        if (gz.kind === "alun_alun") {
          const cx = (pZone.minX + pZone.maxX) * 0.5;
          const cy = (pZone.minY + pZone.maxY) * 0.5;
          if (Math.hypot(jx - cx, jy - cy) < 28) continue;
        }
        trees.push({
          id: `tree-${gz.id}-${idx}`,
          x: jx,
          y: jy,
          size: gz.kind === "alun_alun" ? 5.5 + r1 * 3.0 : 4.2 + r1 * 2.5,
          variant: idx % 3,
        });
      }
    }
  }

  // Inisialisasi kendaraan & kereta api animasi sepanjang koridor utama
  const vehiclePalette = ["#1d4ed8", "#dc2626", "#f8fafc", "#0f766e", "#d97706", "#334155"];
  const vehicles: PreparedVehicle[] = [];
  tracks.forEach((track, tIdx) => {
    if (track.isRail) {
      const trainOffsets = [0.12, 0.31, 0.52, 0.68, 0.86];
      trainOffsets.forEach((offset, idx) => {
        vehicles.push({
          trackIdx: tIdx,
          offsetFraction: offset,
          speedMetersPerSec: 36 + (idx % 3) * 4,
          direction: idx % 2 === 0 ? 1 : -1,
          colorDay: "#58284c",
          isTrain: true,
        });
      });
      return;
    }

    const maxVehicles = track.road.kind === "primary" ? 5 : 2;
    const count = Math.min(maxVehicles, Math.max(1, Math.floor(track.road.totalLengthMeters / 520)));
    for (let v = 0; v < count; v++) {
      const seed = tIdx * 97 + v * 13;
      vehicles.push({
        trackIdx: tIdx,
        offsetFraction: (v + 0.35) / count,
        speedMetersPerSec: 18 + detRand(seed) * 14,
        direction: v % 2 === 0 ? 1 : -1,
        colorDay: vehiclePalette[(tIdx + v) % vehiclePalette.length]!,
        isTrain: false,
      });
    }
  });

  const landmarks = dataset.landmarks.map((lm) => {
    const pt = lngLatToWorld(lm.coordinate, center, cosLat);
    return { landmark: lm, wx: pt.x, wy: pt.y };
  });

  const bBounds = computeBounds(boundary);

  return {
    dataset,
    cosCenterLat: cosLat,
    boundary,
    kelurahanBoundaries,
    waterways,
    greenZones,
    roads,
    buildings,
    trees,
    lamps,
    tracks,
    vehicles,
    landmarks,
    boundsMeters: bBounds,
  };
}

interface BuildingPalette {
  litWall: string;
  shadeWall: string;
  roofTop: string;
  roofShade: string;
  outline: string;
}

const DAY_PALETTES: Record<IsoBuildingKind, BuildingPalette> = {
  heritage: {
    litWall: "#f5efe6",
    shadeWall: "#d4cbbd",
    roofTop: "#e65325",
    roofShade: "#bd3d16",
    outline: "#5c2310",
  },
  religious: {
    litWall: "#f8fafc",
    shadeWall: "#cbd5e1",
    roofTop: "#15803d",
    roofShade: "#14532d",
    outline: "#1e293b",
  },
  civic: {
    litWall: "#eef2f6",
    shadeWall: "#c5d0de",
    roofTop: "#e2e8f0",
    roofShade: "#cbd5e1",
    outline: "#475569",
  },
  health: {
    litWall: "#6cb8e6",
    shadeWall: "#428ab8",
    roofTop: "#84c7ee",
    roofShade: "#529ece",
    outline: "#1e4e6e",
  },
  commercial: {
    litWall: "#f8fafc",
    shadeWall: "#cbd5e1",
    roofTop: "#f1f5f9",
    roofShade: "#dce4ee",
    outline: "#64748b",
  },
  education: {
    litWall: "#f5efe6",
    shadeWall: "#d4cbbd",
    roofTop: "#e65325",
    roofShade: "#bd3d16",
    outline: "#5c2310",
  },
  industrial: {
    litWall: "#dce3ec",
    shadeWall: "#a6b3c4",
    roofTop: "#718096",
    roofShade: "#556377",
    outline: "#334155",
  },
  residential: {
    litWall: "#ebe4d8",
    shadeWall: "#c7bfb2",
    roofTop: "#df622c",
    roofShade: "#b8481b",
    outline: "#54301e",
  },
};

const RESIDENTIAL_ROOFS_DAY: Array<[string, string]> = [
  ["#df622c", "#b8481b"],
  ["#d25522", "#aa3e14"],
  ["#e57038", "#bf5222"],
  ["#c84b1e", "#9e3610"],
  ["#d96832", "#b04c1e"],
];

const NIGHT_PALETTES: Record<IsoBuildingKind, BuildingPalette> = {
  heritage: {
    litWall: "#303a52",
    shadeWall: "#1d2538",
    roofTop: "#5c2724",
    roofShade: "#3b1817",
    outline: "#0b1120",
  },
  religious: {
    litWall: "#36455e",
    shadeWall: "#202b3d",
    roofTop: "#134e3a",
    roofShade: "#0c3527",
    outline: "#0b1120",
  },
  civic: {
    litWall: "#2d3b54",
    shadeWall: "#1a2438",
    roofTop: "#334155",
    roofShade: "#1e293b",
    outline: "#090f1c",
  },
  health: {
    litWall: "#33425c",
    shadeWall: "#1e2a3f",
    roofTop: "#115e59",
    roofShade: "#0d4440",
    outline: "#090f1c",
  },
  commercial: {
    litWall: "#28354c",
    shadeWall: "#172133",
    roofTop: "#3b485e",
    roofShade: "#253042",
    outline: "#090f1c",
  },
  education: {
    litWall: "#2a364c",
    shadeWall: "#182133",
    roofTop: "#4e2a29",
    roofShade: "#331a1b",
    outline: "#090f1c",
  },
  industrial: {
    litWall: "#253145",
    shadeWall: "#151e2e",
    roofTop: "#334155",
    roofShade: "#1e293b",
    outline: "#080d18",
  },
  residential: {
    litWall: "#253147",
    shadeWall: "#151f30",
    roofTop: "#492929",
    roofShade: "#2e191a",
    outline: "#090f1c",
  },
};

function getBuildingColors(kind: IsoBuildingKind, seed: number, theme: IsoThemeMode): BuildingPalette {
  const base = theme === "day" ? DAY_PALETTES[kind] : NIGHT_PALETTES[kind];
  if (kind === "residential" && theme === "day") {
    const [top, shade] = RESIDENTIAL_ROOFS_DAY[seed % RESIDENTIAL_ROOFS_DAY.length]!;
    return { ...base, roofTop: top, roofShade: shade };
  }
  return base;
}

function sampleTrackPoint(track: TrafficTrack, fraction: number): { x: number; y: number; ux: number; uy: number } {
  const { road, cumLengths } = track;
  const total = road.totalLengthMeters;
  const target = Math.max(0, Math.min(total, fraction * total));

  let segIdx = 0;
  for (let i = 1; i < cumLengths.length; i++) {
    if (cumLengths[i]! >= target) {
      segIdx = i - 1;
      break;
    }
  }
  const segStart = cumLengths[segIdx]!;
  const segEnd = cumLengths[segIdx + 1] ?? total;
  const segLen = Math.max(0.01, segEnd - segStart);
  const t = (target - segStart) / segLen;
  const a = road.points[segIdx]!;
  const b = road.points[segIdx + 1] ?? a;
  return {
    x: a.x + (b.x - a.x) * t,
    y: a.y + (b.y - a.y) * t,
    ux: (b.x - a.x) / Math.max(0.01, Math.hypot(b.x - a.x, b.y - a.y)),
    uy: (b.y - a.y) / Math.max(0.01, Math.hypot(b.x - a.x, b.y - a.y)),
  };
}

interface VerticalRenderItem {
  type: "building" | "tree" | "vehicle" | "lamp";
  minRx: number;
  maxRx: number;
  minRy: number;
  maxRy: number;
  minSx: number;
  maxSx: number;
  depthHint: number;
  building?: PreparedBuilding;
  rotatedRing?: WorldPoint[];
  tree?: PreparedTree;
  treeRx?: number;
  treeRy?: number;
  vehiclePos?: { x: number; y: number; ux: number; uy: number; color: string; isTrain: boolean };
  vehicleRx?: number;
  vehicleRy?: number;
  lampRx?: number;
  lampRy?: number;
}

// Painter topological ordering pada interval Screen-X tumpang tindih agar bangunan besar & pohon tidak glitch
function sortIsometricQueue(items: VerticalRenderItem[]): VerticalRenderItem[] {
  const n = items.length;
  if (n <= 1) return items;

  // Langkah 1: urut awal menurut depthHint standar agar graf bersifat nyaris terurut
  items.sort((a, b) => a.depthHint - b.depthHint);
  if (n > 900) return items;

  // Langkah 2: sweep-and-prune berbasis minSx untuk menyelesaikan kasus poligon panjang
  const bySx = items.map((item, idx) => ({ item, idx })).sort((a, b) => a.item.minSx - b.item.minSx);
  const afterList: number[][] = Array.from({ length: n }, () => []);
  const inDegree = new Int32Array(n);
  const EPS = 0.6;

  for (let i = 0; i < bySx.length; i++) {
    const A = bySx[i]!.item;
    const idA = bySx[i]!.idx;
    for (let j = i + 1; j < bySx.length; j++) {
      const B = bySx[j]!.item;
      if (B.minSx > A.maxSx + 1) break;
      const idB = bySx[j]!.idx;

      // Uji apakah A mutlak berada di belakang B terhadap kamera di (-X, +Y)
      const aBehindB =
        (A.maxRy <= B.minRy + EPS && A.minRx >= B.minRx - 18) ||
        (A.minRx >= B.maxRx - EPS && A.maxRy <= B.maxRy + 18);
      const bBehindA =
        (B.maxRy <= A.minRy + EPS && B.minRx >= A.minRx - 18) ||
        (B.minRx >= A.maxRx - EPS && B.maxRy <= A.maxRy + 18);

      if (aBehindB && !bBehindA) {
        afterList[idA]!.push(idB);
        inDegree[idB]++;
      } else if (bBehindA && !aBehindB) {
        afterList[idB]!.push(idA);
        inDegree[idA]++;
      }
    }
  }

  const queue: number[] = [];
  for (let i = 0; i < n; i++) {
    if (inDegree[i] === 0) queue.push(i);
  }

  const ordered: VerticalRenderItem[] = [];
  let head = 0;
  while (head < queue.length) {
    const curr = queue[head++]!;
    ordered.push(items[curr]!);
    for (const nxt of afterList[curr]!) {
      inDegree[nxt]!--;
      if (inDegree[nxt] === 0) queue.push(nxt);
    }
  }

  // Fallback jika terdapat siklus geometris antar poligon bersinggungan
  if (ordered.length < n) {
    const placed = new Set(ordered);
    for (const item of items) {
      if (!placed.has(item)) ordered.push(item);
    }
  }

  return ordered;
}

export function getTileUrl(style: IsoBasemapStyle, z: number, x: number, y: number): string {
  switch (style) {
    case "aerial":
      return `https://petadasar.meritech.cloud/tile/${z}/${x}/${y}.jpg`;
    case "osm":
      return `https://tile.openstreetmap.org/${z}/${x}/${y}.png`;
    case "satellite":
    default:
      return `https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/${z}/${y}/${x}`;
  }
}

export function lngLatToTileCoords(lng: number, lat: number, z: number): { tx: number; ty: number } {
  const n = 2 ** z;
  const latRad = (lat * Math.PI) / 180;
  const tx = Math.floor(((lng + 180) / 360) * n);
  const ty = Math.floor(
    ((1 - Math.log(Math.tan(latRad) + 1 / Math.cos(latRad)) / Math.PI) / 2) * n
  );
  return {
    tx: Math.max(0, Math.min(n - 1, tx)),
    ty: Math.max(0, Math.min(n - 1, ty)),
  };
}

export function tileCoordsToLngLatBounds(
  z: number,
  tx: number,
  ty: number
): { west: number; east: number; north: number; south: number } {
  const n = 2 ** z;
  const west = (tx / n) * 360 - 180;
  const east = ((tx + 1) / n) * 360 - 180;
  const northRad = Math.atan(Math.sinh(Math.PI * (1 - (2 * ty) / n)));
  const southRad = Math.atan(Math.sinh(Math.PI * (1 - (2 * (ty + 1)) / n)));
  return {
    west,
    east,
    north: (northRad * 180) / Math.PI,
    south: (southRad * 180) / Math.PI,
  };
}

export function getViewportLngLatQuad(
  scene: PreparedIsoScene,
  cam: IsoCameraState,
  viewportW: number,
  viewportH: number
): LngLat[] {
  const corners: Array<[number, number]> = [
    [0, 0],
    [viewportW, 0],
    [viewportW, viewportH],
    [0, viewportH],
  ];
  return corners.map(([cx, cy]) => {
    const w = screenToWorld(cx, cy, cam, viewportW, viewportH);
    return worldToLngLat(w, scene.dataset.center, scene.cosCenterLat);
  });
}

interface TileCacheEntry {
  img: HTMLImageElement;
  status: "loading" | "ready" | "error";
  lastUsed: number;
}

export class IsoTileManager {
  private cache = new Map<string, TileCacheEntry>();
  private accessTick = 0;
  private maxTiles = 220;

  public getTile(
    style: IsoBasemapStyle,
    z: number,
    x: number,
    y: number,
    onLoad?: () => void
  ): HTMLImageElement | null {
    if (typeof Image === "undefined" || style === "pixel") return null;
    const key = `${style}/${z}/${x}/${y}`;
    const existing = this.cache.get(key);
    if (existing) {
      existing.lastUsed = ++this.accessTick;
      return existing.status === "ready" ? existing.img : null;
    }

    if (this.cache.size >= this.maxTiles) {
      let oldestKey: string | null = null;
      let oldestTick = Infinity;
      for (const [k, v] of this.cache) {
        if (v.lastUsed < oldestTick) {
          oldestTick = v.lastUsed;
          oldestKey = k;
        }
      }
      if (oldestKey) this.cache.delete(oldestKey);
    }

    const img = new Image();
    img.crossOrigin = "anonymous";
    const entry: TileCacheEntry = {
      img,
      status: "loading",
      lastUsed: ++this.accessTick,
    };
    this.cache.set(key, entry);

    img.onload = () => {
      entry.status = "ready";
      if (onLoad) onLoad();
    };
    img.onerror = () => {
      entry.status = "error";
    };
    img.src = getTileUrl(style, z, x, y);
    return null;
  }
}

export class IsoCityRenderer {
  private bufferCanvas: HTMLCanvasElement | null = null;
  private tileManager = new IsoTileManager();
  private lastHitBuildings: HitTargetBuilding[] = [];
  private lastHitScene: PreparedIsoScene | null = null;

  private ensureBuffer(bufW: number, bufH: number): CanvasRenderingContext2D | null {
    if (typeof document === "undefined") return null;
    if (!this.bufferCanvas) {
      this.bufferCanvas = document.createElement("canvas");
    }
    if (this.bufferCanvas.width !== bufW || this.bufferCanvas.height !== bufH) {
      this.bufferCanvas.width = bufW;
      this.bufferCanvas.height = bufH;
    }
    const ctx = this.bufferCanvas.getContext("2d", { alpha: false });
    if (ctx) ctx.imageSmoothingEnabled = false;
    return ctx;
  }

  public getProjectedLandmarks(
    scene: PreparedIsoScene,
    cam: IsoCameraState,
    viewportW: number,
    viewportH: number
  ): ProjectedLandmarkPin[] {
    const bufW = Math.max(1, Math.ceil(viewportW / cam.pixelScale));
    const bufH = Math.max(1, Math.ceil(viewportH / cam.pixelScale));
    const halfW = bufW * 0.5;
    const halfH = bufH * 0.5;

    return scene.landmarks.map(({ landmark, wx, wy }) => {
      const [bx, by] = worldToBuffer(wx, wy, 15, cam, halfW, halfH);
      const sx = bx * cam.pixelScale;
      const sy = by * cam.pixelScale;
      const visible = sx >= -40 && sx <= viewportW + 40 && sy >= -40 && sy <= viewportH + 40;
      return {
        landmark,
        screenX: Math.round(sx),
        screenY: Math.round(sy),
        visible,
      };
    });
  }

  public render(
    targetCanvas: HTMLCanvasElement,
    scene: PreparedIsoScene,
    cam: IsoCameraState,
    options: {
      viewportW: number;
      viewportH: number;
      theme: IsoThemeMode;
      basemapStyle?: IsoBasemapStyle;
      buildingStyle?: IsoBuildingRenderStyle;
      timeSec: number;
      selectedFeatureId: string | null;
      showBoundary: boolean;
      showRoadLabels?: boolean;
      onTileLoaded?: () => void;
    }
  ) {
    const {
      viewportW,
      viewportH,
      theme,
      basemapStyle = "satellite",
      buildingStyle = "solid",
      timeSec,
      selectedFeatureId,
      showBoundary,
      onTileLoaded,
    } = options;
    if (viewportW <= 0 || viewportH <= 0) return;

    const pixelScale = cam.pixelScale;
    const bufW = Math.max(1, Math.ceil(viewportW / pixelScale));
    const bufH = Math.max(1, Math.ceil(viewportH / pixelScale));
    const ctx = this.ensureBuffer(bufW, bufH);
    const outCtx = targetCanvas.getContext("2d", { alpha: false });
    if (!ctx || !outCtx) return;

    if (targetCanvas.width !== viewportW || targetCanvas.height !== viewportH) {
      targetCanvas.width = viewportW;
      targetCanvas.height = viewportH;
    }

    const halfW = bufW * 0.5;
    const halfH = bufH * 0.5;
    const pad = 110;
    const isRealEarth = basemapStyle !== "pixel";

    // 1. Latar terrain dasar (atau proyeksi tile bumi nyata satelit/aerial/OSM)
    ctx.fillStyle = theme === "day" ? (isRealEarth ? "#2b3828" : "#e6d5b8") : "#0b1220";
    ctx.fillRect(0, 0, bufW, bufH);

    if (isRealEarth) {
      this.drawIsometricEarthTiles(ctx, scene, cam, viewportW, viewportH, halfW, halfH, basemapStyle, onTileLoaded);
      if (theme === "night") {
        ctx.fillStyle = "rgba(6, 13, 28, 0.72)";
        ctx.fillRect(0, 0, bufW, bufH);
      }
    } else {
      if (theme === "night") {
        this.drawGroundGrid(ctx, cam, bufW, bufH, halfW, halfH, theme);
      }
      if (showBoundary && scene.boundary.length >= 3) {
        this.drawBoundaryFill(ctx, scene.boundary, cam, halfW, halfH, theme);
      }
    }

    // 2. Zona hijau (pada mode satelit nyata hanya diberi sapuan tipis & batas agar taman asli terlihat)
    for (const gz of scene.greenZones) {
      const pts = gz.ring.map((p) => worldToBuffer(p.x, p.y, 0, cam, halfW, halfH));
      if (!this.isPolyVisible(pts, bufW, bufH, pad)) continue;
      ctx.beginPath();
      pts.forEach(([x, y], idx) => {
        if (idx === 0) ctx.moveTo(Math.round(x), Math.round(y));
        else ctx.lineTo(Math.round(x), Math.round(y));
      });
      ctx.closePath();

      if (isRealEarth) {
        ctx.fillStyle = theme === "day" ? "rgba(34, 197, 94, 0.14)" : "rgba(20, 83, 45, 0.25)";
        ctx.fill();
        ctx.strokeStyle = theme === "day" ? "rgba(21, 128, 61, 0.55)" : "rgba(34, 197, 94, 0.4)";
        ctx.lineWidth = 1;
        ctx.stroke();
      } else {
        if (gz.kind === "field") {
          ctx.fillStyle = theme === "day" ? "#82cf58" : "#153629";
        } else if (gz.kind === "alun_alun") {
          ctx.fillStyle = theme === "day" ? "#74c64b" : "#183b2c";
        } else {
          ctx.fillStyle = theme === "day" ? "#64b63c" : "#163628";
        }
        ctx.fill();
        ctx.strokeStyle = theme === "day" ? "#4d942c" : "#22543d";
        ctx.lineWidth = 1;
        ctx.stroke();

        // Detail jalur diagonal silang khas Alun-Alun Bojonegoro di mode piksel
        if (gz.kind === "alun_alun" && cam.zoom >= 0.45) {
          const cx = (gz.minX + gz.maxX) * 0.5;
          const cy = (gz.minY + gz.maxY) * 0.5;
          const [p0x, p0y] = worldToBuffer(gz.minX + 6, gz.minY + 6, 0, cam, halfW, halfH);
          const [p1x, p1y] = worldToBuffer(gz.maxX - 6, gz.maxY - 6, 0, cam, halfW, halfH);
          const [p2x, p2y] = worldToBuffer(gz.maxX - 6, gz.minY + 6, 0, cam, halfW, halfH);
          const [p3x, p3y] = worldToBuffer(gz.minX + 6, gz.maxY - 6, 0, cam, halfW, halfH);
          const [pcx, pcy] = worldToBuffer(cx, cy, 0, cam, halfW, halfH);

          ctx.strokeStyle = theme === "day" ? "#dfd8c3" : "#334155";
          ctx.lineWidth = Math.max(1.5, Math.round(4 * cam.zoom));
          ctx.beginPath();
          ctx.moveTo(Math.round(p0x), Math.round(p0y));
          ctx.lineTo(Math.round(p1x), Math.round(p1y));
          ctx.moveTo(Math.round(p2x), Math.round(p2y));
          ctx.lineTo(Math.round(p3x), Math.round(p3y));
          ctx.stroke();

          const plazaR = Math.max(3, Math.round(12 * cam.zoom));
          ctx.fillStyle = theme === "day" ? "#e8e0cc" : "#3b4963";
          ctx.fillRect(Math.round(pcx - plazaR), Math.round(pcy - plazaR * 0.5), plazaR * 2, plazaR);
        }
      }
    }

    // 3. Sungai Bengawan Solo & saluran kota + animasi riak air piksel
    for (const w of scene.waterways) {
      const pts = w.points.map((p) => worldToBuffer(p.x, p.y, 0, cam, halfW, halfH));
      if (!this.isPolyVisible(pts, bufW, bufH, pad + 60)) continue;

      if (w.geometryType === "polygon") {
        ctx.beginPath();
        pts.forEach(([x, y], idx) => (idx === 0 ? ctx.moveTo(Math.round(x), Math.round(y)) : ctx.lineTo(Math.round(x), Math.round(y))));
        ctx.closePath();
        ctx.fillStyle = isRealEarth
          ? (theme === "day" ? "rgba(45, 131, 168, 0.34)" : "rgba(14, 43, 71, 0.55)")
          : (theme === "day" ? "#2aa3dc" : "#0e2b47");
        ctx.fill();
      } else {
        const pxWidth = Math.max(2, Math.round(w.widthMeters * cam.zoom));
        ctx.lineCap = "round";
        ctx.lineJoin = "round";

        if (!isRealEarth) {
          ctx.strokeStyle = theme === "day" ? "#1b7ca8" : "#1a2f3a";
          ctx.lineWidth = pxWidth + Math.max(2, Math.round(3 * cam.zoom));
          this.strokePolyline(ctx, pts);
        }

        ctx.strokeStyle = isRealEarth
          ? (theme === "day" ? "rgba(45, 131, 168, 0.32)" : "rgba(14, 41, 69, 0.45)")
          : (theme === "day" ? "#2aa3dc" : "#0e2945");
        ctx.lineWidth = pxWidth;
        this.strokePolyline(ctx, pts);

        if (pxWidth >= 5) {
          ctx.save();
          ctx.strokeStyle = theme === "day" ? "rgba(186, 230, 253, 0.78)" : "rgba(56, 189, 248, 0.55)";
          ctx.lineWidth = 1;
          ctx.setLineDash([4, 14]);
          ctx.lineDashOffset = -(timeSec * 18);
          this.strokePolyline(ctx, pts);
          ctx.restore();
        }
      }
    }

    // 4. Jaringan Jalan & Rel Kereta Api
    const roadOrder: Record<IsoRoadKind, number> = {
      residential: 0,
      tertiary: 1,
      secondary: 2,
      primary: 3,
      rail: 4,
      bridge: 5,
    };
    const sortedRoads = [...scene.roads].sort((a, b) => roadOrder[a.kind] - roadOrder[b.kind]);

    for (const r of sortedRoads) {
      if (cam.zoom < 0.35 && r.kind === "residential") continue;
      // Pada mode bumi nyata, jalan permukiman/lokal sudah terlihat di citra sehingga cukup gambarkan arteri & rel atau yang diseleksi
      if (isRealEarth && selectedFeatureId !== r.id && (r.kind === "residential" || r.kind === "tertiary")) {
        continue;
      }

      const pts = r.points.map((p) => worldToBuffer(p.x, p.y, r.kind === "bridge" ? 2.5 : 0, cam, halfW, halfH));
      if (!this.isPolyVisible(pts, bufW, bufH, pad)) continue;

      const wPx = Math.max(1, Math.round(r.widthMeters * cam.zoom));
      ctx.lineCap = "square";
      ctx.lineJoin = "miter";

      if (r.kind === "rail") {
        ctx.save();
        ctx.strokeStyle = isRealEarth ? "rgba(120, 80, 50, 0.75)" : (theme === "day" ? "#6b5444" : "#27272a");
        ctx.lineWidth = Math.max(2, wPx + 2);
        ctx.setLineDash([2, 3]);
        this.strokePolyline(ctx, pts);
        ctx.restore();

        ctx.strokeStyle = theme === "day" ? "#cbd5e1" : "#64748b";
        ctx.lineWidth = Math.max(1, Math.round(wPx * 0.4));
        this.strokePolyline(ctx, pts);
      } else if (isRealEarth && selectedFeatureId !== r.id) {
        ctx.strokeStyle = r.kind === "bridge"
          ? "rgba(245, 158, 11, 0.55)"
          : theme === "day"
            ? "rgba(255, 255, 255, 0.28)"
            : "rgba(56, 189, 248, 0.25)";
        ctx.lineWidth = Math.max(1, Math.round(wPx * 0.6));
        this.strokePolyline(ctx, pts);
      } else {
        if (wPx >= 4) {
          ctx.strokeStyle = r.kind === "bridge"
            ? (theme === "day" ? "#cbd5e1" : "#334155")
            : (theme === "day" ? "#98a2b3" : "#1e293b");
          ctx.lineWidth = wPx + 2;
          this.strokePolyline(ctx, pts);
        }

        if (selectedFeatureId === r.id) {
          ctx.strokeStyle = "#f59e0b";
        } else if (r.kind === "bridge") {
          ctx.strokeStyle = theme === "day" ? "#3b4758" : "#1c2638";
        } else if (r.kind === "primary") {
          ctx.strokeStyle = theme === "day" ? "#444d5c" : "#172133";
        } else if (r.kind === "secondary") {
          ctx.strokeStyle = theme === "day" ? "#576170" : "#1c273b";
        } else {
          ctx.strokeStyle = theme === "day" ? "#77808d" : "#212c40";
        }
        ctx.lineWidth = wPx;
        this.strokePolyline(ctx, pts);

        if ((r.kind === "primary" || r.kind === "bridge") && wPx >= 5) {
          ctx.save();
          ctx.strokeStyle = r.kind === "bridge"
            ? (theme === "day" ? "#f8fafc" : "#fbbf24")
            : (theme === "day" ? "#f1f5f9" : "#eab308");
          ctx.lineWidth = 1;
          ctx.setLineDash([3, 5]);
          this.strokePolyline(ctx, pts);
          ctx.restore();
        }
      }
    }

    // Batas kelurahan (halus) & batas Kecamatan Bojonegoro (tegas)
    if (showBoundary) {
      for (const kRing of scene.kelurahanBoundaries) {
        if (kRing.length < 3) continue;
        this.drawSubBoundaryOutline(ctx, kRing, cam, halfW, halfH, theme, isRealEarth);
      }
      if (scene.boundary.length >= 3) {
        this.drawBoundaryOutline(ctx, scene.boundary, cam, halfW, halfH, theme, isRealEarth);
      }
    }

    // 5. Kumpulkan semua objek 3D yang berada dalam frustum layar
    const queue: VerticalRenderItem[] = [];
    const rot = cam.rotation;

    for (const b of scene.buildings) {
      const [scx, scy] = worldToBuffer(b.cx, b.cy, b.heightMeters * 0.5, cam, halfW, halfH);
      const radPx = ((b.maxX - b.minX) + (b.maxY - b.minY)) * cam.zoom + b.heightMeters * cam.zoom * 1.6 + 20;
      if (scx + radPx < 0 || scx - radPx > bufW || scy + radPx < 0 || scy - radPx > bufH) continue;

      let minRx = Infinity;
      let maxRx = -Infinity;
      let minRy = Infinity;
      let maxRy = -Infinity;
      let minSx = Infinity;
      let maxSx = -Infinity;

      const rotatedRing: WorldPoint[] = [];
      for (const p of b.ring) {
        const [rx, ry] = rotateDelta(p.x - cam.centerX, p.y - cam.centerY, rot);
        rotatedRing.push({ x: rx, y: ry });
        if (rx < minRx) minRx = rx;
        if (rx > maxRx) maxRx = rx;
        if (ry < minRy) minRy = ry;
        if (ry > maxRy) maxRy = ry;
        const sx = halfW + (rx + ry) * cam.zoom;
        if (sx < minSx) minSx = sx;
        if (sx > maxSx) maxSx = sx;
      }

      const [cRx, cRy] = rotateDelta(b.cx - cam.centerX, b.cy - cam.centerY, rot);
      queue.push({
        type: "building",
        minRx,
        maxRx,
        minRy,
        maxRy,
        minSx,
        maxSx,
        depthHint: cRy - cRx,
        building: b,
        rotatedRing,
      });
    }

    // Pohon piksel (hanya pada mode piksel atau dekat landmark agar tidak menutupi citra pohon satelit nyata)
    if (!isRealEarth && cam.zoom >= 0.35) {
      for (const t of scene.trees) {
        const [stx, sty] = worldToBuffer(t.x, t.y, t.size, cam, halfW, halfH);
        if (stx < -16 || stx > bufW + 16 || sty < -16 || sty > bufH + 16) continue;
        const [rx, ry] = rotateDelta(t.x - cam.centerX, t.y - cam.centerY, rot);
        const halfM = t.size * 0.5;
        queue.push({
          type: "tree",
          minRx: rx - halfM,
          maxRx: rx + halfM,
          minRy: ry - halfM,
          maxRy: ry + halfM,
          minSx: stx - halfM * 2 * cam.zoom,
          maxSx: stx + halfM * 2 * cam.zoom,
          depthHint: ry - rx,
          tree: t,
          treeRx: rx,
          treeRy: ry,
        });
      }
    }

    if (theme === "night" && cam.zoom >= 0.55) {
      for (const lamp of scene.lamps) {
        const [slx, sly] = worldToBuffer(lamp.x, lamp.y, 4, cam, halfW, halfH);
        if (slx < -14 || slx > bufW + 14 || sly < -14 || sly > bufH + 14) continue;
        const [rx, ry] = rotateDelta(lamp.x - cam.centerX, lamp.y - cam.centerY, rot);
        queue.push({
          type: "lamp",
          minRx: rx - 1,
          maxRx: rx + 1,
          minRy: ry - 1,
          maxRy: ry + 1,
          minSx: slx - 6,
          maxSx: slx + 6,
          depthHint: ry - rx + 1,
          lampRx: rx,
          lampRy: ry,
        });
      }
    }

    if (cam.zoom >= 0.32) {
      for (const v of scene.vehicles) {
        const track = scene.tracks[v.trackIdx];
        if (!track || track.road.totalLengthMeters <= 0) continue;
        const deltaFrac = (timeSec * v.speedMetersPerSec) / track.road.totalLengthMeters;
        let frac = (v.offsetFraction + deltaFrac * v.direction) % 1;
        if (frac < 0) frac += 1;
        const pos = sampleTrackPoint(track, frac);
        const [svx, svy] = worldToBuffer(pos.x, pos.y, 2, cam, halfW, halfH);
        if (svx < -24 || svx > bufW + 24 || svy < -24 || svy > bufH + 24) continue;
        const [rx, ry] = rotateDelta(pos.x - cam.centerX, pos.y - cam.centerY, rot);
        queue.push({
          type: "vehicle",
          minRx: rx - 3,
          maxRx: rx + 3,
          minRy: ry - 3,
          maxRy: ry + 3,
          minSx: svx - 10,
          maxSx: svx + 10,
          depthHint: ry - rx + 2,
          vehiclePos: {
            x: pos.x,
            y: pos.y,
            ux: pos.ux * v.direction,
            uy: pos.uy * v.direction,
            color: v.colorDay,
            isTrain: v.isTrain,
          },
          vehicleRx: rx,
          vehicleRy: ry,
        });
      }
    }

    // 6. Painter's topological depth sort + gambar berurutan dari belakang ke depan
    const ordered = sortIsometricQueue(queue);
    const hitTargets: HitTargetBuilding[] = [];

    for (const item of ordered) {
      if (item.type === "building" && item.building && item.rotatedRing) {
        const hull = this.drawBuilding(
          ctx,
          item.building,
          item.rotatedRing,
          cam,
          halfW,
          halfH,
          theme,
          selectedFeatureId === item.building.id,
          buildingStyle
        );
        if (hull) {
          hitTargets.push({ building: item.building, hull });
        }
      } else if (item.type === "tree" && item.tree && item.treeRx !== undefined && item.treeRy !== undefined) {
        this.drawPixelTree(ctx, item.treeRx, item.treeRy, item.tree.size, cam, halfW, halfH, theme, item.tree.variant);
      } else if (item.type === "lamp" && item.lampRx !== undefined && item.lampRy !== undefined) {
        this.drawNightLamp(ctx, item.lampRx, item.lampRy, cam, halfW, halfH);
      } else if (item.type === "vehicle" && item.vehiclePos && item.vehicleRx !== undefined && item.vehicleRy !== undefined) {
        this.drawVehicle(ctx, item.vehiclePos, item.vehicleRx, item.vehicleRy, cam, halfW, halfH, theme);
      }
    }

    this.lastHitBuildings = hitTargets;
    this.lastHitScene = scene;

    // 7. Blit buffer ke canvas utama dengan pixel rendering tajam
    outCtx.imageSmoothingEnabled = false;
    outCtx.drawImage(this.bufferCanvas!, 0, 0, bufW, bufH, 0, 0, viewportW, viewportH);
  }

  public hitTestScreenPoint(
    cssX: number,
    cssY: number,
    cam: IsoCameraState,
    viewportW: number,
    viewportH: number
  ): IsoInspectedFeature | null {
    const bx = cssX / cam.pixelScale;
    const by = cssY / cam.pixelScale;

    // 1. Cek bangunan dari paling depan ke paling belakang menggunakan poligon atap/dinding nyata
    for (let i = this.lastHitBuildings.length - 1; i >= 0; i--) {
      const target = this.lastHitBuildings[i]!;
      if (pointInScreenPoly(bx, by, target.hull)) {
        const b = target.building;
        const kindMap: Record<IsoBuildingKind, string> = {
          heritage: "Cagar Budaya & Pendopo",
          religious: "Sarana Ibadah",
          civic: "Gedung Pemerintahan & Publik",
          health: "Fasilitas Kesehatan",
          commercial: "Kawasan Niaga & Ruko",
          education: "Fasilitas Pendidikan",
          industrial: "Kawasan Pergudangan & Industri",
          residential: "Bangunan Permukiman",
        };
        return {
          id: b.id,
          kind: "building",
          title: b.name,
          subtitle: b.kelurahan ? `Kel. ${b.kelurahan}, Kec. Bojonegoro` : "Kec. Bojonegoro (Kota)",
          categoryLabel: kindMap[b.kind],
          coordinate: b.centroidCoord,
          sourceLabel: b.source === "osm" ? "OpenStreetMap + Melarosa" : "Kurasi Perkotaan Bojonegoro",
          attributes: [
            { label: "Kategori", value: kindMap[b.kind] },
            { label: "Perkiraan Tinggi", value: `${b.heightMeters} m (${b.levels} lantai)` },
            ...(b.address ? [{ label: "Alamat / Ruas", value: b.address }] : []),
            ...(b.kelurahan ? [{ label: "Wilayah", value: b.kelurahan }] : []),
            { label: "Sumber Data", value: b.source === "osm" ? "Bangunan OSM Real" : "Model Koridor Kota" },
          ],
        };
      }
    }

    const scene = this.lastHitScene;
    if (!scene) return null;

    const worldPt = screenToWorld(cssX, cssY, cam, viewportW, viewportH);
    const clickCoord = worldToLngLat(worldPt, scene.dataset.center, scene.cosCenterLat);

    // 2. Cek Jalan & Jembatan terdekat
    let closestRoad: PreparedRoad | null = null;
    let closestDistSq = Infinity;
    for (const r of scene.roads) {
      const thresh = Math.max(12, r.widthMeters * 0.9 + 6 / cam.zoom);
      if (
        worldPt.x < r.minX - thresh ||
        worldPt.x > r.maxX + thresh ||
        worldPt.y < r.minY - thresh ||
        worldPt.y > r.maxY + thresh
      ) {
        continue;
      }
      for (let i = 1; i < r.points.length; i++) {
        const dSq = distanceToSegmentSq(
          worldPt.x,
          worldPt.y,
          r.points[i - 1]!.x,
          r.points[i - 1]!.y,
          r.points[i]!.x,
          r.points[i]!.y
        );
        if (dSq <= thresh * thresh && dSq < closestDistSq) {
          closestDistSq = dSq;
          closestRoad = r;
        }
      }
    }
    if (closestRoad) {
      const roadLabels: Record<IsoRoadKind, string> = {
        primary: "Jalan Arteri Primer",
        secondary: "Jalan Kolektor Sekunder",
        tertiary: "Jalan Lingkungan",
        residential: "Jalan Permukiman",
        bridge: "Jembatan Utama",
        rail: "Jalur Kereta Api",
      };
      return {
        id: closestRoad.id,
        kind: "road",
        title: closestRoad.name,
        subtitle: "Kecamatan Bojonegoro (Kota)",
        categoryLabel: roadLabels[closestRoad.kind],
        coordinate: clickCoord,
        sourceLabel: closestRoad.source === "osm" ? "OpenStreetMap" : "Jaringan Koridor Bojonegoro",
        attributes: [
          { label: "Klasifikasi", value: roadLabels[closestRoad.kind] },
          { label: "Lebar Efektif", value: `${closestRoad.widthMeters} m` },
          { label: "Panjang Segmen", value: `${Math.round(closestRoad.totalLengthMeters)} m` },
        ],
      };
    }

    // 3. Cek Taman & Ruang Terbuka Hijau
    for (const gz of scene.greenZones) {
      if (
        worldPt.x >= gz.minX &&
        worldPt.x <= gz.maxX &&
        worldPt.y >= gz.minY &&
        worldPt.y <= gz.maxY &&
        pointInRing(worldPt.x, worldPt.y, gz.ring)
      ) {
        return {
          id: gz.id,
          kind: "park",
          title: gz.name,
          subtitle: "Ruang Terbuka Hijau Perkotaan",
          categoryLabel: gz.kind === "alun_alun" ? "Alun-Alun Kota" : "Taman / RTH",
          coordinate: gz.centroidCoord,
          sourceLabel: "OpenStreetMap + Kurasi Kota",
          attributes: [
            { label: "Fungsi Ruang", value: gz.kind === "alun_alun" ? "Pusat Kota & RTH" : "Ruang Terbuka Hijau" },
            { label: "Lokasi", value: "Kecamatan Bojonegoro" },
          ],
        };
      }
    }

    // 4. Cek Sungai Bengawan Solo / Perairan
    for (const w of scene.waterways) {
      const thresh = w.widthMeters * 0.65 + 8 / cam.zoom;
      if (w.geometryType === "polygon") {
        if (pointInRing(worldPt.x, worldPt.y, w.points)) {
          return {
            id: w.id,
            kind: "water",
            title: w.name,
            subtitle: "Perairan Kecamatan Bojonegoro",
            categoryLabel: "Badan Air",
            coordinate: clickCoord,
            sourceLabel: "OpenStreetMap / Hidrologi",
            attributes: [{ label: "Jenis", value: "Perairan Kota" }],
          };
        }
      } else {
        for (let i = 1; i < w.points.length; i++) {
          const dSq = distanceToSegmentSq(
            worldPt.x,
            worldPt.y,
            w.points[i - 1]!.x,
            w.points[i - 1]!.y,
            w.points[i]!.x,
            w.points[i]!.y
          );
          if (dSq <= thresh * thresh) {
            return {
              id: w.id,
              kind: "water",
              title: w.name,
              subtitle: "Koridor Utara Kec. Bojonegoro",
              categoryLabel: w.widthMeters >= 45 ? "Sungai Besar" : "Saluran Kota",
              coordinate: clickCoord,
              sourceLabel: "Hidrologi Bojonegoro",
              attributes: [
                { label: "Lebar Alur", value: `~${w.widthMeters} m` },
                { label: "Arah Aliran", value: "Barat Daya -> Timur Laut" },
              ],
            };
          }
        }
      }
    }

    return null;
  }

  private drawGroundGrid(
    ctx: CanvasRenderingContext2D,
    cam: IsoCameraState,
    _bufW: number,
    _bufH: number,
    halfW: number,
    halfH: number,
    theme: IsoThemeMode
  ) {
    if (cam.zoom < 0.25) return;
    const step = cam.zoom >= 1.0 ? 80 : 160;
    const span = Math.max(halfW, halfH) * 2 / cam.zoom + 320;
    const startX = Math.floor((cam.centerX - span) / step) * step;
    const endX = Math.ceil((cam.centerX + span) / step) * step;
    const startY = Math.floor((cam.centerY - span) / step) * step;
    const endY = Math.ceil((cam.centerY + span) / step) * step;

    ctx.strokeStyle = theme === "day" ? "rgba(116, 134, 98, 0.14)" : "rgba(56, 189, 248, 0.06)";
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let x = startX; x <= endX; x += step) {
      const [x1, y1] = worldToBuffer(x, startY, 0, cam, halfW, halfH);
      const [x2, y2] = worldToBuffer(x, endY, 0, cam, halfW, halfH);
      ctx.moveTo(Math.round(x1), Math.round(y1));
      ctx.lineTo(Math.round(x2), Math.round(y2));
    }
    for (let y = startY; y <= endY; y += step) {
      const [x1, y1] = worldToBuffer(startX, y, 0, cam, halfW, halfH);
      const [x2, y2] = worldToBuffer(endX, y, 0, cam, halfW, halfH);
      ctx.moveTo(Math.round(x1), Math.round(y1));
      ctx.lineTo(Math.round(x2), Math.round(y2));
    }
    ctx.stroke();
  }

  private drawBoundaryFill(
    ctx: CanvasRenderingContext2D,
    boundary: WorldPoint[],
    cam: IsoCameraState,
    halfW: number,
    halfH: number,
    theme: IsoThemeMode
  ) {
    ctx.beginPath();
    boundary.forEach((p, idx) => {
      const [x, y] = worldToBuffer(p.x, p.y, 0, cam, halfW, halfH);
      if (idx === 0) ctx.moveTo(Math.round(x), Math.round(y));
      else ctx.lineTo(Math.round(x), Math.round(y));
    });
    ctx.closePath();
    ctx.fillStyle = theme === "day" ? "#f3e4c8" : "#0e1729";
    ctx.fill();
  }

  private drawIsometricEarthTiles(
    ctx: CanvasRenderingContext2D,
    scene: PreparedIsoScene,
    cam: IsoCameraState,
    viewportW: number,
    viewportH: number,
    halfW: number,
    halfH: number,
    basemapStyle: IsoBasemapStyle,
    onTileLoaded?: () => void
  ) {
    const quad = getViewportLngLatQuad(scene, cam, viewportW, viewportH);
    let minLng = Infinity;
    let maxLng = -Infinity;
    let minLat = Infinity;
    let maxLat = -Infinity;
    for (const [lng, lat] of quad) {
      if (lng < minLng) minLng = lng;
      if (lng > maxLng) maxLng = lng;
      if (lat < minLat) minLat = lat;
      if (lat > maxLat) maxLat = lat;
    }

    const maxTileZ = basemapStyle === "aerial" ? 19 : 18;
    let tileZ = Math.max(13, Math.min(maxTileZ, Math.round(Math.log2(155326 * cam.zoom))));

    let nwTile = lngLatToTileCoords(minLng, maxLat, tileZ);
    let seTile = lngLatToTileCoords(maxLng, minLat, tileZ);
    while ((seTile.tx - nwTile.tx + 1) * (seTile.ty - nwTile.ty + 1) > 49 && tileZ > 12) {
      tileZ--;
      nwTile = lngLatToTileCoords(minLng, maxLat, tileZ);
      seTile = lngLatToTileCoords(maxLng, minLat, tileZ);
    }

    ctx.save();
    for (let tx = nwTile.tx; tx <= seTile.tx; tx++) {
      for (let ty = nwTile.ty; ty <= seTile.ty; ty++) {
        const bounds = tileCoordsToLngLatBounds(tileZ, tx, ty);
        const wNW = lngLatToWorld([bounds.west, bounds.north], scene.dataset.center, scene.cosCenterLat);
        const wNE = lngLatToWorld([bounds.east, bounds.north], scene.dataset.center, scene.cosCenterLat);
        const wSW = lngLatToWorld([bounds.west, bounds.south], scene.dataset.center, scene.cosCenterLat);

        const pNW = worldToBuffer(wNW.x, wNW.y, 0, cam, halfW, halfH);
        const pNE = worldToBuffer(wNE.x, wNE.y, 0, cam, halfW, halfH);
        const pSW = worldToBuffer(wSW.x, wSW.y, 0, cam, halfW, halfH);

        const ux = (pNE[0] - pNW[0]) / 255.2;
        const uy = (pNE[1] - pNW[1]) / 255.2;
        const vx = (pSW[0] - pNW[0]) / 255.2;
        const vy = (pSW[1] - pNW[1]) / 255.2;

        const img = this.tileManager.getTile(basemapStyle, tileZ, tx, ty, onTileLoaded);
        if (img) {
          ctx.setTransform(ux, uy, vx, vy, pNW[0], pNW[1]);
          ctx.drawImage(img, 0, 0);
        } else if (tileZ > 12) {
          // Fallback sementara ke tile parent zoom - 1 agar tidak kosong saat menggeser cepat
          const parentImg = this.tileManager.getTile(basemapStyle, tileZ - 1, Math.floor(tx / 2), Math.floor(ty / 2), onTileLoaded);
          if (parentImg) {
            const sx = (tx % 2) * 128;
            const sy = (ty % 2) * 128;
            ctx.setTransform(ux, uy, vx, vy, pNW[0], pNW[1]);
            ctx.drawImage(parentImg, sx, sy, 128, 128, 0, 0, 256, 256);
          }
        }
      }
    }
    ctx.restore();
  }

  private drawSubBoundaryOutline(
    ctx: CanvasRenderingContext2D,
    boundary: WorldPoint[],
    cam: IsoCameraState,
    halfW: number,
    halfH: number,
    theme: IsoThemeMode,
    isRealEarth: boolean
  ) {
    ctx.save();
    ctx.beginPath();
    boundary.forEach((p, idx) => {
      const [x, y] = worldToBuffer(p.x, p.y, 0, cam, halfW, halfH);
      if (idx === 0) ctx.moveTo(Math.round(x), Math.round(y));
      else ctx.lineTo(Math.round(x), Math.round(y));
    });
    ctx.closePath();
    ctx.strokeStyle = isRealEarth
      ? (theme === "day" ? "rgba(255, 255, 255, 0.35)" : "rgba(148, 163, 184, 0.35)")
      : (theme === "day" ? "rgba(71, 85, 105, 0.25)" : "rgba(56, 189, 248, 0.18)");
    ctx.lineWidth = 1;
    ctx.setLineDash([2, 3]);
    ctx.stroke();
    ctx.restore();
  }

  private drawBoundaryOutline(
    ctx: CanvasRenderingContext2D,
    boundary: WorldPoint[],
    cam: IsoCameraState,
    halfW: number,
    halfH: number,
    theme: IsoThemeMode,
    isRealEarth = false
  ) {
    ctx.save();
    ctx.beginPath();
    boundary.forEach((p, idx) => {
      const [x, y] = worldToBuffer(p.x, p.y, 0, cam, halfW, halfH);
      if (idx === 0) ctx.moveTo(Math.round(x), Math.round(y));
      else ctx.lineTo(Math.round(x), Math.round(y));
    });
    ctx.closePath();
    ctx.strokeStyle = isRealEarth
      ? "rgba(250, 204, 21, 0.85)"
      : (theme === "day" ? "rgba(37, 99, 235, 0.55)" : "rgba(56, 189, 248, 0.55)");
    ctx.lineWidth = isRealEarth ? 2 : 1.5;
    ctx.setLineDash([4, 4]);
    ctx.stroke();
    ctx.restore();
  }

  private strokePolyline(ctx: CanvasRenderingContext2D, pts: Array<[number, number]>) {
    if (pts.length < 2) return;
    ctx.beginPath();
    ctx.moveTo(Math.round(pts[0]![0]), Math.round(pts[0]![1]));
    for (let i = 1; i < pts.length; i++) {
      ctx.lineTo(Math.round(pts[i]![0]), Math.round(pts[i]![1]));
    }
    ctx.stroke();
  }

  private isPolyVisible(pts: Array<[number, number]>, bufW: number, bufH: number, pad: number): boolean {
    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;
    for (const [x, y] of pts) {
      if (x < minX) minX = x;
      if (x > maxX) maxX = x;
      if (y < minY) minY = y;
      if (y > maxY) maxY = y;
    }
    return !(maxX < -pad || minX > bufW + pad || maxY < -pad || minY > bufH + pad);
  }

  private drawBuilding(
    ctx: CanvasRenderingContext2D,
    b: PreparedBuilding,
    rotatedRing: WorldPoint[],
    cam: IsoCameraState,
    halfBufW: number,
    halfBufH: number,
    theme: IsoThemeMode,
    isSelected: boolean,
    buildingStyle: IsoBuildingRenderStyle = "solid"
  ): Array<[number, number]> | null {
    const n = rotatedRing.length;
    if (n < 3) return null;

    const zScale = cam.zoom * VERTICAL_EXAGGERATION;
    const hPx = b.heightMeters * zScale;
    const palette = getBuildingColors(b.kind, b.seed, theme);

    const basePts: Array<[number, number]> = [];
    const roofPts: Array<[number, number]> = [];
    for (let i = 0; i < n; i++) {
      const p = rotatedRing[i]!;
      const sx = Math.round(halfBufW + (p.x + p.y) * cam.zoom);
      const sy = Math.round(halfBufH + (p.y - p.x) * 0.5 * cam.zoom);
      basePts.push([sx, sy]);
      roofPts.push([sx, Math.round(sy - hPx)]);
    }

    if (buildingStyle === "footprint" && !isSelected) {
      ctx.save();
      ctx.strokeStyle = theme === "day" ? "rgba(254, 240, 138, 0.85)" : "rgba(56, 189, 248, 0.82)";
      ctx.lineWidth = 1;
      ctx.beginPath();
      basePts.forEach(([x, y], idx) => (idx === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y)));
      ctx.closePath();
      ctx.stroke();

      ctx.strokeStyle = theme === "day" ? "rgba(255, 255, 255, 0.72)" : "rgba(125, 211, 252, 0.75)";
      ctx.beginPath();
      roofPts.forEach(([x, y], idx) => (idx === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y)));
      ctx.closePath();
      ctx.stroke();
      ctx.restore();
    } else {
      ctx.save();
      if (buildingStyle === "translucent" && !isSelected) {
        ctx.globalAlpha = 0.7;
      }

      // Bayangan jatuh bangunan di sisi kanan-bawah pada siang hari
      if (theme === "day" && hPx >= 3) {
        ctx.fillStyle = "rgba(30, 41, 59, 0.16)";
        ctx.beginPath();
        const sOffX = Math.max(2, Math.round(hPx * 0.48));
        const sOffY = Math.max(1, Math.round(hPx * 0.22));
        basePts.forEach(([x, y], idx) => {
          if (idx === 0) ctx.moveTo(x + sOffX, y + sOffY);
          else ctx.lineTo(x + sOffX, y + sOffY);
        });
        ctx.closePath();
        ctx.fill();
      }

      // Gambar dinding depan (front-facing edges saat CW: -(dx + dy) > 0, ekuivalen dengan sxB < sxA)
      const frontWalls: Array<{ i: number; next: number; depth: number; isLit: boolean; segLenPx: number }> = [];
      for (let i = 0; i < n; i++) {
        const next = (i + 1) % n;
        const a = rotatedRing[i]!;
        const c = rotatedRing[next]!;
        const dx = c.x - a.x;
        const dy = c.y - a.y;
        const facing = -(dx + dy);
        if (facing > 0.05) {
          const midX = (a.x + c.x) * 0.5;
          const midY = (a.y + c.y) * 0.5;
          const segLenPx = Math.hypot(basePts[next]![0] - basePts[i]![0], basePts[next]![1] - basePts[i]![1]);
          // Normal luar CW adalah (dy, -dx); proyeksi X layar = dy - dx (< 0 berarti menghadap kiri/terang)
          const isLit = dy - dx < 0;
          frontWalls.push({
            i,
            next,
            depth: midY - midX,
            isLit,
            segLenPx,
          });
        }
      }

      frontWalls.sort((a, bWall) => a.depth - bWall.depth);

      for (const wall of frontWalls) {
        const [bx0, by0] = basePts[wall.i]!;
        const [bx1, by1] = basePts[wall.next]!;
        const [rx0, ry0] = roofPts[wall.i]!;
        const [rx1, ry1] = roofPts[wall.next]!;

        ctx.beginPath();
        ctx.moveTo(bx0, by0);
        ctx.lineTo(bx1, by1);
        ctx.lineTo(rx1, ry1);
        ctx.lineTo(rx0, ry0);
        ctx.closePath();

        ctx.fillStyle = isSelected
          ? "#fbbf24"
          : wall.isLit
            ? palette.litWall
            : palette.shadeWall;
        ctx.fill();

        // Jendela piksel pada dinding yang cukup lebar
        if (!isSelected && wall.segLenPx >= 6 && hPx >= 5) {
          const cols = Math.min(8, Math.floor(wall.segLenPx / 3.5));
          const rows = Math.min(6, Math.max(1, Math.floor(b.levels)));
          for (let r = 0; r < rows; r++) {
            const vFrac = (r + 0.55) / (rows + 0.25);
            for (let col = 0; col < cols; col++) {
              const uFrac = (col + 0.5) / cols;
              const winHash = detRand(b.seed + wall.i * 53 + r * 19 + col * 7);
              if (theme === "day") {
                ctx.fillStyle = wall.isLit ? "rgba(71, 85, 105, 0.55)" : "rgba(30, 41, 59, 0.65)";
              } else {
                if (winHash < 0.34 && b.kind === "residential") continue;
                ctx.fillStyle = b.kind === "health"
                  ? (winHash > 0.5 ? "#7dd3fc" : "#fde68a")
                  : (winHash > 0.72 ? "#fde047" : "#f59e0b");
              }
              const wx = Math.round(bx0 + (bx1 - bx0) * uFrac);
              const wy = Math.round(by0 + (by1 - by0) * uFrac - hPx * vFrac);
              const winW = cam.zoom >= 1.3 ? 2 : 1;
              ctx.fillRect(wx, wy, winW, winW);
            }
          }
        }

        if (hPx >= 3) {
          ctx.strokeStyle = isSelected ? "#f59e0b" : palette.outline;
          ctx.lineWidth = 1;
          ctx.stroke();
        }
      }

      // Gambar Atap sesuai gaya arsitektur bangunan
      let cScreenX = 0;
      let cScreenY = 0;
      for (const [rx, ry] of roofPts) {
        cScreenX += rx;
        cScreenY += ry;
      }
      cScreenX = Math.round(cScreenX / n);
      cScreenY = Math.round(cScreenY / n);

      if ((b.roofStyle === "hip" || b.roofStyle === "gable" || b.roofStyle === "stepped") && cam.zoom >= 0.45 && n >= 4) {
        ctx.beginPath();
        roofPts.forEach(([rx, ry], idx) => {
          if (idx === 0) ctx.moveTo(rx, ry);
          else ctx.lineTo(rx, ry);
        });
        ctx.closePath();
        ctx.fillStyle = isSelected ? "#fde047" : palette.roofShade;
        ctx.fill();

        const peakRise = Math.max(2, Math.round((b.roofStyle === "stepped" ? 7 : 3.5) * cam.zoom));
        const peakX = cScreenX;
        const peakY = cScreenY - peakRise;

        for (let i = 0; i < n; i++) {
          const next = (i + 1) % n;
          const [rx0, ry0] = roofPts[i]!;
          const [rx1, ry1] = roofPts[next]!;
          const dx = rotatedRing[next]!.x - rotatedRing[i]!.x;
          const dy = rotatedRing[next]!.y - rotatedRing[i]!.y;
          const litRoof = dy - dx < 0;

          ctx.beginPath();
          ctx.moveTo(rx0, ry0);
          ctx.lineTo(rx1, ry1);
          ctx.lineTo(peakX, peakY);
          ctx.closePath();
          ctx.fillStyle = isSelected
            ? "#fde047"
            : litRoof
              ? palette.roofTop
              : palette.roofShade;
          ctx.fill();
          ctx.strokeStyle = palette.outline;
          ctx.lineWidth = 1;
          ctx.stroke();
        }

        if (b.roofStyle === "stepped") {
          ctx.fillStyle = theme === "day" ? "#eab308" : "#facc15";
          ctx.fillRect(peakX - 1, peakY - 3, 2, 3);
        }
      } else {
        ctx.beginPath();
        roofPts.forEach(([rx, ry], idx) => {
          if (idx === 0) ctx.moveTo(rx, ry);
          else ctx.lineTo(rx, ry);
        });
        ctx.closePath();
        ctx.fillStyle = isSelected ? "#fde047" : palette.roofTop;
        ctx.fill();
        ctx.strokeStyle = isSelected ? "#f59e0b" : palette.outline;
        ctx.lineWidth = 1;
        ctx.stroke();

        if (b.roofStyle === "dome") {
          const domeR = Math.max(3, Math.round(7 * cam.zoom));
          ctx.fillStyle = theme === "day" ? "#16a34a" : "#22c55e";
          ctx.fillRect(cScreenX - domeR, cScreenY - domeR, domeR * 2, domeR);
          ctx.fillStyle = theme === "day" ? "#eab308" : "#fde047";
          ctx.fillRect(cScreenX - Math.max(2, Math.round(domeR * 0.6)), cScreenY - domeR - 2, Math.max(4, Math.round(domeR * 1.2)), 3);
          ctx.fillRect(cScreenX - 1, cScreenY - domeR - 6, 2, 4);
        }

        if (b.kind === "health" && cam.zoom >= 0.55) {
          const padSize = Math.max(3, Math.round(4 * cam.zoom));
          ctx.fillStyle = "#ef4444";
          ctx.fillRect(cScreenX - padSize, cScreenY - 1, padSize * 2, 2);
          ctx.fillRect(cScreenX - 1, cScreenY - padSize, 2, padSize * 2);
        }
      }
      ctx.restore();
    }

    // Kembalikan hull segiempat luar piksel untuk hit-testing klik
    let minHx = Infinity;
    let maxHx = -Infinity;
    let minHy = Infinity;
    let maxHy = -Infinity;
    for (const [x, y] of basePts) {
      if (x < minHx) minHx = x;
      if (x > maxHx) maxHx = x;
      if (y < minHy) minHy = y;
      if (y > maxHy) maxHy = y;
    }
    for (const [x, y] of roofPts) {
      if (x < minHx) minHx = x;
      if (x > maxHx) maxHx = x;
      if (y < minHy) minHy = y;
      if (y > maxHy) maxHy = y;
    }
    return [
      [minHx, minHy],
      [maxHx, minHy],
      [maxHx, maxHy],
      [minHx, maxHy],
    ];
  }

  private drawPixelTree(
    ctx: CanvasRenderingContext2D,
    rx: number,
    ry: number,
    sizeMeters: number,
    cam: IsoCameraState,
    halfW: number,
    halfH: number,
    theme: IsoThemeMode,
    variant: number
  ) {
    const sx = Math.round(halfW + (rx + ry) * cam.zoom);
    const sy = Math.round(halfH + (ry - rx) * 0.5 * cam.zoom);
    const r = Math.max(2, Math.round(sizeMeters * cam.zoom * 0.65));

    // Bayangan tajuk
    ctx.fillStyle = theme === "day" ? "rgba(30, 58, 30, 0.22)" : "rgba(0, 0, 0, 0.35)";
    ctx.fillRect(sx - r + 1, sy - 1, r * 2, Math.max(2, Math.round(r * 0.6)));

    // Batang
    ctx.fillStyle = theme === "day" ? "#6b4423" : "#3a2514";
    ctx.fillRect(sx - 1, sy - Math.max(2, Math.round(r * 0.8)), 2, Math.max(2, Math.round(r * 0.8)));

    // Tajuk piksel berlapis
    const canopyDark = theme === "day"
      ? (variant === 0 ? "#2e6f34" : variant === 1 ? "#367b3b" : "#255d2b")
      : "#113322";
    const canopyLight = theme === "day"
      ? (variant === 0 ? "#48984e" : variant === 1 ? "#56a85a" : "#3e8645")
      : "#1b4d34";

    const topY = sy - Math.round(r * 2.1);
    ctx.fillStyle = canopyDark;
    ctx.fillRect(sx - r, topY + 1, r * 2, Math.max(3, Math.round(r * 1.4)));
    ctx.fillStyle = canopyLight;
    ctx.fillRect(sx - Math.max(1, r - 1), topY, Math.max(2, r * 2 - 2), Math.max(2, Math.round(r * 0.8)));
  }

  private drawNightLamp(
    ctx: CanvasRenderingContext2D,
    rx: number,
    ry: number,
    cam: IsoCameraState,
    halfW: number,
    halfH: number
  ) {
    const sx = Math.round(halfW + (rx + ry) * cam.zoom);
    const sy = Math.round(halfH + (ry - rx) * 0.5 * cam.zoom);
    const topY = sy - Math.max(3, Math.round(4.5 * cam.zoom));

    ctx.fillStyle = "rgba(251, 191, 36, 0.16)";
    ctx.fillRect(sx - 5, sy - 2, 10, 5);
    ctx.fillStyle = "#64748b";
    ctx.fillRect(sx, topY, 1, sy - topY);
    ctx.fillStyle = "#fde047";
    ctx.fillRect(sx - 1, topY - 1, 2, 2);
  }

  private drawVehicle(
    ctx: CanvasRenderingContext2D,
    v: { x: number; y: number; ux: number; uy: number; color: string; isTrain: boolean },
    rx: number,
    ry: number,
    cam: IsoCameraState,
    halfW: number,
    halfH: number,
    theme: IsoThemeMode
  ) {
    const sx = Math.round(halfW + (rx + ry) * cam.zoom);
    const sy = Math.round(halfH + (ry - rx) * 0.5 * cam.zoom);

    const [rux, ruy] = rotateDelta(v.ux, v.uy, cam.rotation);
    const dsx = rux + ruy;
    const dsy = (ruy - rux) * 0.5;
    const len = Math.max(0.01, Math.hypot(dsx, dsy));
    const nx = dsx / len;
    const ny = dsy / len;

    if (v.isTrain) {
      // Lokomotif + 3 gerbong KAI piksel
      const coachStep = Math.max(4, Math.round(6 * cam.zoom));
      for (let c = 0; c < 4; c++) {
        const cx = Math.round(sx - nx * c * coachStep);
        const cy = Math.round(sy - ny * c * coachStep);
        ctx.fillStyle = c === 0 ? "#ef4444" : "#f1f5f9";
        ctx.fillRect(cx - 2, cy - 3, 5, 4);
        ctx.fillStyle = c === 0 ? "#1e293b" : "#3b82f6";
        ctx.fillRect(cx - 2, cy - 1, 5, 1);
      }
      if (theme === "night") {
        ctx.fillStyle = "rgba(254, 240, 138, 0.5)";
        ctx.fillRect(Math.round(sx + nx * 4 - 2), Math.round(sy + ny * 4 - 2), 4, 3);
      }
      return;
    }

    const w = Math.max(2, Math.round(2.8 * cam.zoom));
    const h = Math.max(2, Math.round(2.4 * cam.zoom));
    ctx.fillStyle = v.color;
    ctx.fillRect(sx - Math.floor(w * 0.5), sy - h, w, h);

    if (theme === "night") {
      // Lampu depan putih-kuning & lampu belakang merah
      ctx.fillStyle = "#fef08a";
      ctx.fillRect(Math.round(sx + nx * 2), Math.round(sy + ny * 2 - 1), 2, 1);
      ctx.fillStyle = "#ef4444";
      ctx.fillRect(Math.round(sx - nx * 2), Math.round(sy - ny * 2 - 1), 1, 1);
    }
  }
}

import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import type {
  BojonegoroIsometricDataset,
  IsoBoundary,
  IsoBuilding,
  IsoBuildingKind,
  IsoGreenZone,
  IsoLandmark,
  IsoRoad,
  IsoRoadKind,
  IsoRoofStyle,
  IsoWaterArea,
  LngLat,
} from "~/types/isometric-map";

const BOJONEGORO_CENTER: LngLat = [111.8817, -7.1502];
const BOJONEGORO_BBOX: [number, number, number, number] = [111.85425, -7.18571, 111.9334, -7.1317];
const OVERPASS_QUERY_BBOX = "-7.18571,111.85425,-7.1317,111.9334";

const OVERPASS_ENDPOINTS = [
  "https://overpass-api.de/api/interpreter",
  "https://overpass.kumi.systems/api/interpreter",
  "https://lz4.overpass-api.de/api/interpreter",
  "https://z.overpass-api.de/api/interpreter",
];

const CACHE_TTL_MS = 12 * 60 * 60 * 1000;
const FALLBACK_CACHE_TTL_MS = 5 * 60 * 1000;
const MAX_OSM_BUILDINGS = 24000;
const DISK_CACHE_PATH = path.join(os.tmpdir(), "melarosa-bojonegoro-kota-osm-v3.json");

interface CachedEntry {
  data: BojonegoroIsometricDataset;
  expiresAt: number;
}

let memoryCache: CachedEntry | null = null;
let cachedUrbanFabric: {
  kelurahanBoundaries: IsoBoundary[];
  waterways: IsoWaterArea[];
  greenZones: IsoGreenZone[];
  roads: IsoRoad[];
  buildings: IsoBuilding[];
} | null = null;

interface OverpassGeomPoint {
  lat: number;
  lon: number;
}

interface OverpassMember {
  type: "way" | "node" | "relation";
  role: string;
  geometry?: OverpassGeomPoint[];
}

interface OverpassElement {
  type: "way" | "relation" | "node";
  id: number;
  tags?: Record<string, string>;
  geometry?: OverpassGeomPoint[];
  members?: OverpassMember[];
}

const DEG_TO_RAD = Math.PI / 180;
const COS_CENTER_LAT = Math.cos(BOJONEGORO_CENTER[1] * DEG_TO_RAD);
const KX = 111320 * COS_CENTER_LAT;
const KY = 110540;

function detRand(seed: number): number {
  let x = (seed ^ 0x9e3779b9) >>> 0;
  x = Math.imul(x ^ (x >>> 15), 0x85ebca6b) >>> 0;
  x = Math.imul(x ^ (x >>> 13), 0xc2b2ae35) >>> 0;
  return (x ^ (x >>> 16)) / 4294967295;
}

function approxRingAreaMeters(ring: LngLat[]): number {
  if (ring.length < 3) return 0;
  let area = 0;
  for (let i = 0; i < ring.length; i++) {
    const a = ring[i]!;
    const b = ring[(i + 1) % ring.length]!;
    const ax = (a[0] - BOJONEGORO_CENTER[0]) * KX;
    const ay = (a[1] - BOJONEGORO_CENTER[1]) * KY;
    const bx = (b[0] - BOJONEGORO_CENTER[0]) * KX;
    const by = (b[1] - BOJONEGORO_CENTER[1]) * KY;
    area += ax * by - bx * ay;
  }
  return Math.abs(area) * 0.5;
}

function ringCentroid(ring: LngLat[]): LngLat {
  let sx = 0;
  let sy = 0;
  const len =
    ring.length > 1 &&
    ring[0]![0] === ring[ring.length - 1]![0] &&
    ring[0]![1] === ring[ring.length - 1]![1]
      ? ring.length - 1
      : ring.length;
  for (let i = 0; i < len; i++) {
    sx += ring[i]![0];
    sy += ring[i]![1];
  }
  const n = Math.max(1, len);
  return [sx / n, sy / n];
}

function simplifyCoords(coords: LngLat[], minDistMeters: number, closeRing: boolean): LngLat[] {
  if (coords.length <= 4) return coords;
  const minSq = minDistMeters * minDistMeters;
  const out: LngLat[] = [coords[0]!];

  for (let i = 1; i < coords.length - 1; i++) {
    const prev = out[out.length - 1]!;
    const curr = coords[i]!;
    const dx = (curr[0] - prev[0]) * KX;
    const dy = (curr[1] - prev[1]) * KY;
    if (dx * dx + dy * dy >= minSq) {
      out.push(curr);
    }
  }
  out.push(coords[coords.length - 1]!);

  if (closeRing && out.length >= 3) {
    const first = out[0]!;
    const last = out[out.length - 1]!;
    if (first[0] !== last[0] || first[1] !== last[1]) {
      out.push([first[0], first[1]]);
    }
  }
  return out;
}

function makeRectRing(
  centerLng: number,
  centerLat: number,
  halfWM: number,
  halfHM: number,
  angleDeg = 0
): LngLat[] {
  const rad = angleDeg * DEG_TO_RAD;
  const cos = Math.cos(rad);
  const sin = Math.sin(rad);
  const corners: [number, number][] = [
    [-halfWM, -halfHM],
    [halfWM, -halfHM],
    [halfWM, halfHM],
    [-halfWM, halfHM],
    [-halfWM, -halfHM],
  ];
  return corners.map(([x, y]) => {
    const rx = x * cos - y * sin;
    const ry = x * sin + y * cos;
    return [
      Number((centerLng + rx / KX).toFixed(6)),
      Number((centerLat + ry / KY).toFixed(6)),
    ] as LngLat;
  });
}

function pointInRingLngLat(lng: number, lat: number, ring: LngLat[]): boolean {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i]![0];
    const yi = ring[i]![1];
    const xj = ring[j]![0];
    const yj = ring[j]![1];
    const intersect =
      yi > lat !== yj > lat &&
      lng < ((xj - xi) * (lat - yi)) / (yj - yi + 1e-12) + xi;
    if (intersect) inside = !inside;
  }
  return inside;
}

function pointToPolylineDistMeters(lng: number, lat: number, coords: LngLat[]): number {
  const px = (lng - BOJONEGORO_CENTER[0]) * KX;
  const py = (lat - BOJONEGORO_CENTER[1]) * KY;
  let minSq = Infinity;
  for (let i = 0; i < coords.length - 1; i++) {
    const ax = (coords[i]![0] - BOJONEGORO_CENTER[0]) * KX;
    const ay = (coords[i]![1] - BOJONEGORO_CENTER[1]) * KY;
    const bx = (coords[i + 1]![0] - BOJONEGORO_CENTER[0]) * KX;
    const by = (coords[i + 1]![1] - BOJONEGORO_CENTER[1]) * KY;
    const vx = bx - ax;
    const vy = by - ay;
    const lenSq = vx * vx + vy * vy;
    const t = lenSq === 0 ? 0 : Math.max(0, Math.min(1, ((px - ax) * vx + (py - ay) * vy) / lenSq));
    const cx = ax + t * vx;
    const cy = ay + t * vy;
    const dSq = (px - cx) ** 2 + (py - cy) ** 2;
    if (dSq < minSq) minSq = dSq;
  }
  return Math.sqrt(minSq);
}

function getBoundary(): IsoBoundary {
  return {
    id: "kecamatan-bojonegoro-kota",
    name: "Kecamatan Bojonegoro (Kota)",
    ring: [
      [111.8555, -7.1476],
      [111.8648, -7.1458],
      [111.8742, -7.1418],
      [111.8838, -7.1390],
      [111.8942, -7.1372],
      [111.9086, -7.1358],
      [111.9198, -7.1408],
      [111.9302, -7.1475],
      [111.9318, -7.1635],
      [111.9252, -7.1782],
      [111.9082, -7.1845],
      [111.8842, -7.1838],
      [111.8648, -7.1788],
      [111.8555, -7.1668],
      [111.8555, -7.1476],
    ],
  };
}

function getCuratedKelurahanBoundaries(): IsoBoundary[] {
  const kelurahanSpecs: Array<{ id: string; name: string; lng: number; lat: number; w: number; h: number; ang?: number }> = [
    { id: "kel-kauman", name: "Kelurahan Kauman", lng: 111.8805, lat: -7.1485, w: 480, h: 420, ang: 2 },
    { id: "kel-kepatihan", name: "Kelurahan Kepatihan", lng: 111.8835, lat: -7.1538, w: 460, h: 420, ang: 0 },
    { id: "kel-kadipaten", name: "Kelurahan Kadipaten", lng: 111.8902, lat: -7.1525, w: 500, h: 440, ang: -3 },
    { id: "kel-klangon", name: "Kelurahan Klangon", lng: 111.8888, lat: -7.1462, w: 520, h: 400, ang: 4 },
    { id: "kel-ledok-kulon", name: "Kelurahan Ledok Kulon", lng: 111.8695, lat: -7.1468, w: 680, h: 440, ang: -8 },
    { id: "kel-ledok-wetan", name: "Kelurahan Ledok Wetan", lng: 111.8768, lat: -7.1455, w: 480, h: 380, ang: -4 },
    { id: "kel-banjarejo", name: "Kelurahan Banjarejo", lng: 111.8988, lat: -7.1432, w: 650, h: 480, ang: 6 },
    { id: "kel-karangpacar", name: "Kelurahan Karangpacar", lng: 111.8975, lat: -7.1508, w: 560, h: 450, ang: 2 },
    { id: "kel-ngrowo", name: "Kelurahan Ngrowo", lng: 111.9035, lat: -7.1568, w: 680, h: 540, ang: -4 },
    { id: "kel-mojokampung", name: "Kelurahan Mojokampung", lng: 111.8858, lat: -7.1592, w: 480, h: 420, ang: 0 },
    { id: "kel-sumbang", name: "Kelurahan Sumbang", lng: 111.8772, lat: -7.1598, w: 580, h: 520, ang: 3 },
    { id: "kel-sukorejo", name: "Kelurahan Sukorejo", lng: 111.8915, lat: -7.1678, w: 780, h: 620, ang: -5 },
    { id: "kel-kalianyar", name: "Desa Kalianyar", lng: 111.8862, lat: -7.1745, w: 620, h: 540, ang: 4 },
    { id: "kel-pacul", name: "Desa Pacul", lng: 111.8765, lat: -7.1742, w: 680, h: 580, ang: -2 },
    { id: "kel-jetak", name: "Kelurahan Jetak", lng: 111.8648, lat: -7.1568, w: 720, h: 640, ang: -10 },
    { id: "kel-sukoharjo", name: "Desa Sukoharjo", lng: 111.8648, lat: -7.1705, w: 740, h: 620, ang: -6 },
    { id: "kel-campurejo", name: "Desa Campurejo", lng: 111.9145, lat: -7.1625, w: 820, h: 680, ang: 5 },
    { id: "kel-mulyoagung", name: "Desa Mulyoagung & Kalirejo", lng: 111.9165, lat: -7.1475, w: 860, h: 580, ang: 12 },
  ];
  return kelurahanSpecs.map((k) => ({
    id: k.id,
    name: k.name,
    ring: makeRectRing(k.lng, k.lat, k.w, k.h, k.ang || 0),
  }));
}

function getCuratedLandmarks(): IsoLandmark[] {
  return [
    {
      id: "lm-alun-alun",
      name: "Alun-Alun Bojonegoro",
      category: "Ruang Publik & Ikon Kota",
      description: "Pusat kota dan titik nol Kabupaten Bojonegoro yang dikelilingi bangunan pemerintahan dan Masjid Agung.",
      coordinate: [111.8817, -7.1502],
      kelurahan: "Kauman",
    },
    {
      id: "lm-pendopo",
      name: "Pendopo Malowopati & Gedung Pemkab",
      category: "Pemerintahan",
      description: "Kompleks Pendopo Malowopati dan Gedung Pemkab Bojonegoro 7 lantai di sisi utara-timur Alun-Alun.",
      coordinate: [111.8817, -7.1489],
      kelurahan: "Kauman",
    },
    {
      id: "lm-masjid-agung",
      name: "Masjid Agung Darussalam",
      category: "Tempat Ibadah",
      description: "Masjid agung kebanggaan Bojonegoro yang berdiri tepat di sisi barat Alun-Alun.",
      coordinate: [111.8801, -7.1502],
      kelurahan: "Kauman",
    },
    {
      id: "lm-sosrodilogo",
      name: "Jembatan Sosrodilogo",
      category: "Infrastruktur & Jembatan",
      description: "Jembatan pelengkung ikonik melintasi Sungai Bengawan Solo menuju Kecamatan Trucuk.",
      coordinate: [111.8746, -7.1434],
      kelurahan: "Ledok Kulon",
    },
    {
      id: "lm-pasar-kota",
      name: "Pasar Kota Bojonegoro",
      category: "Kawasan Niaga",
      description: "Pusat perdagangan tradisional utama dekat tepi Sungai Bengawan Solo.",
      coordinate: [111.8836, -7.1471],
      kelurahan: "Klangon / Kauman",
    },
    {
      id: "lm-stasiun",
      name: "Stasiun Bojonegoro",
      category: "Transportasi Rel",
      description: "Stasiun kereta api kelas besar di jalur utara Pulau Jawa koridor Surabaya - Semarang.",
      coordinate: [111.8863, -7.1636],
      kelurahan: "Sukorejo",
    },
    {
      id: "lm-taman-rajekwesi",
      name: "Taman Rajekwesi",
      category: "Ruang Terbuka Hijau",
      description: "Ruang terbuka hijau kota di kawasan Jalan Rajekwesi dekat Terminal Bojonegoro.",
      coordinate: [111.8965, -7.1618],
      kelurahan: "Klangon / Ngrowo",
    },
    {
      id: "lm-rsud",
      name: "RSUD Dr. R. Sosodoro Djatikoesoemo",
      category: "Fasilitas Kesehatan",
      description: "Kompleks menara rumah sakit umum daerah rujukan utama di ruas Jalan Veteran, Bojonegoro.",
      coordinate: [111.8942, -7.1678],
      kelurahan: "Sukorejo",
    },
    {
      id: "lm-stadion",
      name: "Stadion Letjen H. Soedirman",
      category: "Olahraga",
      description: "Stadion kebanggaan masyarakat Bojonegoro di sisi timur kawasan perkotaan.",
      coordinate: [111.8998, -7.1544],
      kelurahan: "Ngrowo",
    },
  ];
}

function getCuratedHeroBuildings(): IsoBuilding[] {
  return [
    {
      id: "hero-pendopo",
      name: "Pendopo Malowopati Pemkab Bojonegoro",
      kind: "heritage",
      roofStyle: "stepped",
      ring: makeRectRing(111.8817, -7.1489, 25, 19),
      levels: 2,
      heightMeters: 13,
      address: "Jl. Mas Tumapel No. 1, Kauman",
      kelurahan: "Kauman",
      landmarkId: "lm-pendopo",
      source: "curated",
    },
    {
      id: "hero-pemkab-menara",
      name: "Gedung Pemkab Bojonegoro 7 Lantai",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8825, -7.1481, 18, 16, 2),
      levels: 7,
      heightMeters: 28,
      address: "Jl. P. Mas Tumapel, Kauman",
      kelurahan: "Kauman",
      landmarkId: "lm-pendopo",
      source: "curated",
    },
    {
      id: "hero-pemkab-sayap",
      name: "Gedung Putih Sekretariat Daerah",
      kind: "civic",
      roofStyle: "flat",
      ring: makeRectRing(111.8812, -7.1481, 26, 11),
      levels: 4,
      heightMeters: 16,
      address: "Jl. P. Mas Tumapel, Kauman",
      kelurahan: "Kauman",
      source: "curated",
    },
    {
      id: "hero-masjid-agung",
      name: "Masjid Agung Darussalam Bojonegoro",
      kind: "religious",
      roofStyle: "dome",
      ring: makeRectRing(111.8801, -7.1502, 23, 23),
      levels: 3,
      heightMeters: 18,
      address: "Jl. Hasyim Asyari, Kauman",
      kelurahan: "Kauman",
      landmarkId: "lm-masjid-agung",
      source: "curated",
    },
    {
      id: "hero-pasar-kota",
      name: "Pasar Kota Bojonegoro",
      kind: "commercial",
      roofStyle: "gable",
      ring: makeRectRing(111.8836, -7.1471, 36, 24, 4),
      levels: 2,
      heightMeters: 11,
      address: "Jl. KH. Mansyur, Kauman",
      kelurahan: "Kauman",
      landmarkId: "lm-pasar-kota",
      source: "curated",
    },
    {
      id: "hero-stasiun",
      name: "Stasiun Besar Kereta Api Bojonegoro",
      kind: "civic",
      roofStyle: "gable",
      ring: makeRectRing(111.8863, -7.1636, 44, 14, -6),
      levels: 2,
      heightMeters: 11,
      address: "Jl. Gajah Mada No. 65, Sukorejo",
      kelurahan: "Sukorejo",
      landmarkId: "lm-stasiun",
      source: "curated",
    },
    // Kompleks 3 Menara Biru RSUD & Pusat Medis/Hotel (seperti referensi iso.trafik.live)
    {
      id: "hero-rsud",
      name: "RSUD Dr. R. Sosodoro Djatikoesoemo (Menara Utama)",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8942, -7.1678, 16, 15, -3),
      levels: 8,
      heightMeters: 31,
      address: "Jl. Veteran No. 36, Sukorejo",
      kelurahan: "Sukorejo",
      landmarkId: "lm-rsud",
      source: "curated",
    },
    {
      id: "hero-rsud-tower-b",
      name: "RSUD Sosodoro Djatikoesoemo (Menara Rawat Inap B)",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8946, -7.1674, 15, 14, -3),
      levels: 7,
      heightMeters: 27,
      address: "Jl. Veteran No. 36, Sukorejo",
      kelurahan: "Sukorejo",
      source: "curated",
    },
    {
      id: "hero-rsud-tower-c",
      name: "RSUD Sosodoro Djatikoesoemo (Menara Diagnostik C)",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8938, -7.1675, 14, 14, -3),
      levels: 6,
      heightMeters: 24,
      address: "Jl. Veteran No. 36, Sukorejo",
      kelurahan: "Sukorejo",
      source: "curated",
    },
    // Menara Biru Kembar Pusat Kota (sebelah utara koridor rel KA)
    {
      id: "hero-menara-biru-1",
      name: "Menara Griya Dharma Kusuma & Perkantoran Terpadu A",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8904, -7.1595, 14, 14, -4),
      levels: 8,
      heightMeters: 30,
      address: "Jl. Trunojoyo - Jl. Untung Suropati",
      kelurahan: "Mojokampung",
      source: "curated",
    },
    {
      id: "hero-menara-biru-2",
      name: "Menara Perkantoran Terpadu B",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8909, -7.1592, 13, 13, -4),
      levels: 7,
      heightMeters: 27,
      address: "Jl. Trunojoyo - Jl. Untung Suropati",
      kelurahan: "Mojokampung",
      source: "curated",
    },
    {
      id: "hero-menara-biru-3",
      name: "Menara Perkantoran Terpadu C",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8901, -7.1591, 13, 13, -4),
      levels: 6,
      heightMeters: 24,
      address: "Jl. Trunojoyo - Jl. Untung Suropati",
      kelurahan: "Mojokampung",
      source: "curated",
    },
    {
      id: "hero-hotel-aston",
      name: "Aston Bojonegoro City Hotel",
      kind: "health",
      roofStyle: "flat",
      ring: makeRectRing(111.8788, -7.1639, 16, 14, 4),
      levels: 6,
      heightMeters: 24,
      address: "Jl. MH. Thamrin / Jl. Untung Suropati",
      kelurahan: "Sumbang",
      source: "curated",
    },
    {
      id: "hero-stadion-tribun",
      name: "Tribun Stadion Letjen H. Soedirman",
      kind: "civic",
      roofStyle: "flat",
      ring: makeRectRing(111.8992, -7.1544, 16, 48),
      levels: 3,
      heightMeters: 15,
      address: "Jl. Lettu Suwolo, Ngrowo",
      kelurahan: "Ngrowo",
      landmarkId: "lm-stadion",
      source: "curated",
    },
  ];
}

function getCuratedGreenZones(): IsoGreenZone[] {
  return [
    {
      id: "gz-alun-alun",
      name: "Alun-Alun Bojonegoro",
      kind: "alun_alun",
      ring: makeRectRing(111.8817, -7.1502, 58, 52),
      treeDensity: 0.9,
    },
    {
      id: "gz-taman-rajekwesi",
      name: "Taman Rajekwesi",
      kind: "park",
      ring: makeRectRing(111.8965, -7.1618, 48, 36),
      treeDensity: 0.85,
    },
    {
      id: "gz-lapangan-stadion",
      name: "Lapangan Stadion Letjen H. Soedirman",
      kind: "field",
      ring: makeRectRing(111.9001, -7.1544, 46, 36),
      treeDensity: 0.35,
    },
    {
      id: "gz-bantaran-solo",
      name: "Taman Bengawan Solo & Taman Lokomotif",
      kind: "park",
      ring: makeRectRing(111.8749, -7.1456, 42, 24, -14),
      treeDensity: 0.8,
    },
    {
      id: "gz-hutan-kota-jetak",
      name: "RTH Hutan Kota Jetak & Taman Tirtawana",
      kind: "forest",
      ring: makeRectRing(111.8712, -7.1586, 54, 42, -12),
      treeDensity: 0.95,
    },
    {
      id: "gz-lapangan-sumbang",
      name: "Lapangan Hijau Sumbang",
      kind: "field",
      ring: makeRectRing(111.8832, -7.1552, 34, 24, -5),
      treeDensity: 0.4,
    },
    {
      id: "gz-lapangan-karangpacar",
      name: "Taman & Lapangan Karangpacar",
      kind: "park",
      ring: makeRectRing(111.8948, -7.1528, 36, 22, 4),
      treeDensity: 0.75,
    },
    {
      id: "gz-lapangan-kalianyar",
      name: "Lapangan Desa Kalianyar",
      kind: "field",
      ring: makeRectRing(111.8878, -7.1728, 42, 30, 2),
      treeDensity: 0.5,
    },
    {
      id: "gz-lapangan-pacul",
      name: "Taman & Lapangan Pacul",
      kind: "park",
      ring: makeRectRing(111.8752, -7.1732, 40, 28, -6),
      treeDensity: 0.75,
    },
    {
      id: "gz-lapangan-campurejo",
      name: "Lapangan Olahraga Campurejo",
      kind: "field",
      ring: makeRectRing(111.9125, -7.1638, 45, 32, 6),
      treeDensity: 0.55,
    },
  ];
}

function getCuratedWaterways(): IsoWaterArea[] {
  return [
    {
      id: "water-bengawan-solo",
      name: "Sungai Bengawan Solo",
      geometryType: "line",
      widthMeters: 88,
      coords: [
        [111.8535, -7.1492],
        [111.8582, -7.1484],
        [111.8635, -7.1468],
        [111.8692, -7.1448],
        [111.8746, -7.1434],
        [111.8798, -7.1418],
        [111.8855, -7.1404],
        [111.8915, -7.1391],
        [111.8982, -7.1378],
        [111.9045, -7.1366],
        [111.9098, -7.1364],
        [111.9152, -7.1382],
        [111.9208, -7.1412],
        [111.9268, -7.1452],
        [111.9325, -7.1508],
      ],
    },
    // Sungai / Kali Afvoer Kota yang berkelok membelah pusat kota (seperti sungai berkelok pada gambar referensi)
    {
      id: "water-kali-afvoer",
      name: "Kali Afvoer Ingas / Sungai Kota Bojonegoro",
      geometryType: "line",
      widthMeters: 28,
      coords: [
        [111.8918, -7.1848],
        [111.8895, -7.1802],
        [111.8862, -7.1765],
        [111.8835, -7.1728],
        [111.8838, -7.1688],
        [111.8812, -7.1656],
        [111.8796, -7.1631],
        [111.8808, -7.1604],
        [111.8838, -7.1592],
        [111.8826, -7.1562],
        [111.8792, -7.1558],
        [111.8778, -7.1582],
        [111.8758, -7.1565],
        [111.8772, -7.1532],
        [111.8762, -7.1502],
        [111.8782, -7.1476],
        [111.8764, -7.1452],
        [111.8746, -7.1434],
      ],
    },
    // Saluran Sekunder Timur (Kali Kening / Ngrowo - Karangpacar)
    {
      id: "water-kali-timur",
      name: "Kali Kening / Saluran Primer Ngrowo",
      geometryType: "line",
      widthMeters: 13,
      coords: [
        [111.9085, -7.1825],
        [111.9052, -7.1748],
        [111.9018, -7.1672],
        [111.8992, -7.1612],
        [111.8965, -7.1558],
        [111.8952, -7.1518],
        [111.8972, -7.1462],
        [111.8982, -7.1378],
      ],
    },
    // Saluran Barat (Jetak - Sukoharjo)
    {
      id: "water-kali-barat",
      name: "Kali Jetak - Sukoharjo",
      geometryType: "line",
      widthMeters: 14,
      coords: [
        [111.8625, -7.1795],
        [111.8638, -7.1712],
        [111.8622, -7.1635],
        [111.8638, -7.1555],
        [111.8635, -7.1468],
      ],
    },
  ];
}

function getCuratedCorridors(): IsoRoad[] {
  return [
    // Jembatan Utama
    {
      id: "road-sosrodilogo",
      name: "Jembatan Sosrodilogo",
      kind: "bridge",
      widthMeters: 13,
      source: "curated",
      coords: [
        [111.8742, -7.1452],
        [111.8746, -7.1434],
        [111.8749, -7.1414],
      ],
    },
    {
      id: "road-kaliketek-bridge",
      name: "Jembatan Kaliketek",
      kind: "bridge",
      widthMeters: 13,
      source: "curated",
      coords: [
        [111.9088, -7.1388],
        [111.9092, -7.1364],
        [111.9095, -7.1342],
      ],
    },
    // Jalan Arteri Kembar Barat Daya (seperti jalan raya kembar kiri-bawah pada gambar referensi)
    {
      id: "road-bypass-barat-a",
      name: "Jl. Raya Kalitidu - Bojonegoro (Jalur Utara)",
      kind: "primary",
      widthMeters: 12,
      source: "curated",
      coords: [
        [111.8555, -7.1605],
        [111.8615, -7.1642],
        [111.8668, -7.1695],
        [111.8718, -7.1762],
        [111.8765, -7.1832],
      ],
    },
    {
      id: "road-bypass-barat-b",
      name: "Jl. Raya Kalitidu - Bojonegoro (Jalur Selatan)",
      kind: "primary",
      widthMeters: 12,
      source: "curated",
      coords: [
        [111.8551, -7.1609],
        [111.8611, -7.1646],
        [111.8664, -7.1699],
        [111.8714, -7.1766],
        [111.8761, -7.1836],
      ],
    },
    // Koridor Barat-Timur Utama: Jl. Panglima Sudirman - Jl. Babat
    {
      id: "road-panglima-sudirman",
      name: "Jl. Panglima Sudirman",
      kind: "primary",
      widthMeters: 12,
      source: "curated",
      coords: [
        [111.8565, -7.1526],
        [111.8665, -7.1516],
        [111.8758, -7.1511],
        [111.8808, -7.1509],
        [111.8826, -7.1509],
        [111.8915, -7.1515],
        [111.9015, -7.1522],
        [111.9145, -7.1538],
        [111.9295, -7.1552],
      ],
    },
    // Koridor Utara: Jl. Mastrip - Jl. Imam Bonjol - Jl. Jaksa Agung Suprapto
    {
      id: "road-mastrip",
      name: "Jl. Mastrip - Jl. Imam Bonjol - Jl. Jaksa Agung Suprapto",
      kind: "primary",
      widthMeters: 11,
      source: "curated",
      coords: [
        [111.8585, -7.1488],
        [111.8695, -7.1482],
        [111.8785, -7.1494],
        [111.8826, -7.1494],
        [111.8886, -7.1492],
        [111.8962, -7.1482],
        [111.9088, -7.1388],
        [111.9225, -7.1445],
      ],
    },
    // Koridor Tengah Barat-Timur: Jl. Untung Suropati - Jl. Veteran - Jl. Lettu Suyitno
    {
      id: "road-veteran",
      name: "Jl. Untung Suropati - Jl. Veteran",
      kind: "primary",
      widthMeters: 12,
      source: "curated",
      coords: [
        [111.8615, -7.1595],
        [111.8748, -7.1618],
        [111.8817, -7.1622],
        [111.8882, -7.1638],
        [111.8942, -7.1665],
        [111.9025, -7.1695],
        [111.9168, -7.1742],
        [111.9265, -7.1765],
      ],
    },
    // Koridor Utara-Selatan Tengah: Jl. Teuku Umar - Jl. Gajah Mada - Jl. Ahmad Yani
    {
      id: "road-ahmad-yani",
      name: "Jl. Teuku Umar - Jl. Gajah Mada - Jl. A. Yani",
      kind: "primary",
      widthMeters: 12,
      source: "curated",
      coords: [
        [111.8826, -7.1408],
        [111.8826, -7.1494],
        [111.8826, -7.1509],
        [111.8834, -7.1564],
        [111.8863, -7.1636],
        [111.8895, -7.1695],
        [111.8942, -7.1825],
      ],
    },
    // Koridor Utara-Selatan Barat: Jl. Hasyim Asyari - Jl. Diponegoro - Jl. Raya Dander
    {
      id: "road-diponegoro",
      name: "Jl. Hasyim Asyari - Jl. Diponegoro",
      kind: "primary",
      widthMeters: 11,
      source: "curated",
      coords: [
        [111.8746, -7.1434],
        [111.8772, -7.1486],
        [111.8808, -7.1494],
        [111.8808, -7.1512],
        [111.8805, -7.1582],
        [111.8798, -7.1665],
        [111.8785, -7.1765],
        [111.8778, -7.1835],
      ],
    },
    // Koridor Jl. Dr. Wahidin - Jl. Pattimura - Jl. Panglima Polim
    {
      id: "road-wahidin-pattimura",
      name: "Jl. Dr. Wahidin - Jl. Pattimura",
      kind: "secondary",
      widthMeters: 10,
      source: "curated",
      coords: [
        [111.8878, -7.1415],
        [111.8878, -7.1512],
        [111.8882, -7.1585],
        [111.8895, -7.1665],
        [111.8908, -7.1775],
      ],
    },
    // Koridor Jl. Basuki Rahmat - Jl. Pemuda - Jl. Lisman
    {
      id: "road-basuki-rahmat",
      name: "Jl. Basuki Rahmat - Jl. Pemuda - Jl. Lisman",
      kind: "secondary",
      widthMeters: 10,
      source: "curated",
      coords: [
        [111.8585, -7.1558],
        [111.8698, -7.1558],
        [111.8805, -7.1564],
        [111.8912, -7.1568],
        [111.9035, -7.1575],
        [111.9175, -7.1588],
        [111.9295, -7.1602],
      ],
    },
    // Koridor Jl. Rajekwesi - Jl. Lettu Suyitno - Campurejo
    {
      id: "road-rajekwesi",
      name: "Jl. Rajekwesi - Jl. Lettu Suyitno",
      kind: "secondary",
      widthMeters: 10,
      source: "curated",
      coords: [
        [111.8942, -7.1395],
        [111.8948, -7.1485],
        [111.8955, -7.1568],
        [111.8968, -7.1628],
        [111.8985, -7.1715],
        [111.9012, -7.1815],
      ],
    },
    // Koridor Timur: Jl. Lettu Suwolo - Stadion - Kalirejo - Semanding
    {
      id: "road-lettu-suwolo",
      name: "Jl. Lettu Suwolo - Jl. Raya Kalirejo",
      kind: "secondary",
      widthMeters: 9,
      source: "curated",
      coords: [
        [111.9038, -7.1385],
        [111.9032, -7.1475],
        [111.9028, -7.1555],
        [111.9035, -7.1655],
        [111.9055, -7.1785],
      ],
    },
    // Koridor Timur Jauh: Campurejo - Mulyoagung
    {
      id: "road-campurejo",
      name: "Jl. Raya Campurejo - Mulyoagung",
      kind: "secondary",
      widthMeters: 9,
      source: "curated",
      coords: [
        [111.9142, -7.1415],
        [111.9138, -7.1525],
        [111.9135, -7.1635],
        [111.9148, -7.1755],
      ],
    },
    // Koridor Barat: Jl. MT Haryono - Jetak - Sukoharjo
    {
      id: "road-mt-haryono",
      name: "Jl. MT Haryono - Jl. Raya Jetak",
      kind: "secondary",
      widthMeters: 9,
      source: "curated",
      coords: [
        [111.8682, -7.1462],
        [111.8685, -7.1545],
        [111.8692, -7.1645],
        [111.8698, -7.1765],
      ],
    },
    // Koridor Selatan: Jl. HOS Cokroaminoto - Jl. KHR Moh. Rosyid - Pacul - Kalianyar
    {
      id: "road-cokroaminoto",
      name: "Jl. HOS Cokroaminoto - Jl. KHR Moh. Rosyid",
      kind: "secondary",
      widthMeters: 10,
      source: "curated",
      coords: [
        [111.8605, -7.1712],
        [111.8725, -7.1718],
        [111.8845, -7.1715],
        [111.8965, -7.1722],
        [111.9105, -7.1728],
        [111.9235, -7.1735],
      ],
    },
    // Koridor Selatan Bawah: Jl. Monginsidi - Sukorejo - Kalianyar
    {
      id: "road-monginsidi",
      name: "Jl. Wolter Monginsidi - Jl. Serma Maun",
      kind: "secondary",
      widthMeters: 9,
      source: "curated",
      coords: [
        [111.8655, -7.1778],
        [111.8785, -7.1782],
        [111.8915, -7.1785],
        [111.9045, -7.1792],
        [111.9185, -7.1798],
      ],
    },
    // Jembatan-jembatan Dalam Kota melintasi Kali Afvoer
    {
      id: "bridge-afvoer-sudirman",
      name: "Jembatan Kali Afvoer Sudirman",
      kind: "bridge",
      widthMeters: 12,
      source: "curated",
      coords: [
        [111.8752, -7.1511],
        [111.8772, -7.1511],
      ],
    },
    {
      id: "bridge-afvoer-pemuda",
      name: "Jembatan Kali Afvoer Pemuda",
      kind: "bridge",
      widthMeters: 11,
      source: "curated",
      coords: [
        [111.8752, -7.1563],
        [111.8775, -7.1564],
      ],
    },
    {
      id: "bridge-afvoer-rail",
      name: "Viaduk Kereta Api Kali Afvoer",
      kind: "bridge",
      widthMeters: 9,
      source: "curated",
      coords: [
        [111.8784, -7.1642],
        [111.8812, -7.1639],
      ],
    },
    // Jalur Kereta Api Double Track Lintas Utara (KAI Daop 8)
    {
      id: "rail-bojonegoro",
      name: "Jalur Kereta Api Lintas Utara (KAI Surabaya - Cepu)",
      kind: "rail",
      widthMeters: 6,
      source: "curated",
      coords: [
        [111.8550, -7.1648],
        [111.8615, -7.1656],
        [111.8685, -7.1652],
        [111.8765, -7.1644],
        [111.8863, -7.1634],
        [111.8975, -7.1620],
        [111.9095, -7.1606],
        [111.9215, -7.1592],
        [111.9325, -7.1608],
      ],
    },
  ];
}

interface UrbanZoneSpec {
  id: string;
  kelurahan: string;
  centerLng: number;
  centerLat: number;
  halfWM: number;
  halfHM: number;
  angleDeg: number;
  streetStepX: number;
  streetStepY: number;
  density: number;
  style: "urban_core" | "kampung" | "perumahan" | "suburban" | "commercial_strip";
}

function getUrbanZoneSpecs(): UrbanZoneSpec[] {
  return [
    { id: "z-kauman", kelurahan: "Kauman", centerLng: 111.8802, centerLat: -7.1476, halfWM: 480, halfHM: 380, angleDeg: -2, streetStepX: 68, streetStepY: 56, density: 0.92, style: "urban_core" },
    { id: "z-kepatihan", kelurahan: "Kepatihan", centerLng: 111.8842, centerLat: -7.1538, halfWM: 480, halfHM: 380, angleDeg: 1, streetStepX: 66, streetStepY: 54, density: 0.94, style: "urban_core" },
    { id: "z-kadipaten", kelurahan: "Kadipaten", centerLng: 111.8912, centerLat: -7.1525, halfWM: 480, halfHM: 400, angleDeg: -3, streetStepX: 68, streetStepY: 56, density: 0.92, style: "urban_core" },
    { id: "z-klangon", kelurahan: "Klangon", centerLng: 111.8895, centerLat: -7.1452, halfWM: 520, halfHM: 380, angleDeg: 4, streetStepX: 70, streetStepY: 56, density: 0.9, style: "urban_core" },
    { id: "z-ledok-wetan", kelurahan: "Ledok Wetan", centerLng: 111.8745, centerLat: -7.1478, halfWM: 460, halfHM: 350, angleDeg: -6, streetStepX: 68, streetStepY: 54, density: 0.88, style: "kampung" },
    { id: "z-ledok-kulon", kelurahan: "Ledok Kulon", centerLng: 111.8665, centerLat: -7.1495, halfWM: 560, halfHM: 380, angleDeg: -11, streetStepX: 74, streetStepY: 58, density: 0.86, style: "perumahan" },
    { id: "z-jetak-utara", kelurahan: "Jetak", centerLng: 111.8625, centerLat: -7.1558, halfWM: 580, halfHM: 420, angleDeg: -14, streetStepX: 74, streetStepY: 58, density: 0.85, style: "perumahan" },
    { id: "z-sumbang-utara", kelurahan: "Sumbang", centerLng: 111.8738, centerLat: -7.1558, halfWM: 480, halfHM: 380, angleDeg: 3, streetStepX: 68, streetStepY: 54, density: 0.9, style: "kampung" },
    { id: "z-sumbang-selatan", kelurahan: "Sumbang", centerLng: 111.8758, centerLat: -7.1628, halfWM: 520, halfHM: 420, angleDeg: -4, streetStepX: 70, streetStepY: 56, density: 0.9, style: "urban_core" },
    { id: "z-mojokampung", kelurahan: "Mojokampung", centerLng: 111.8858, centerLat: -7.1598, halfWM: 480, halfHM: 380, angleDeg: -2, streetStepX: 66, streetStepY: 54, density: 0.93, style: "urban_core" },
    { id: "z-karangpacar", kelurahan: "Karangpacar", centerLng: 111.8982, centerLat: -7.1508, halfWM: 520, halfHM: 420, angleDeg: 2, streetStepX: 70, streetStepY: 56, density: 0.9, style: "kampung" },
    { id: "z-banjarejo", kelurahan: "Banjarejo", centerLng: 111.8995, centerLat: -7.1428, halfWM: 580, halfHM: 400, angleDeg: 7, streetStepX: 72, streetStepY: 58, density: 0.87, style: "kampung" },
    { id: "z-ngrowo", kelurahan: "Ngrowo", centerLng: 111.9048, centerLat: -7.1578, halfWM: 560, halfHM: 460, angleDeg: -5, streetStepX: 72, streetStepY: 58, density: 0.88, style: "kampung" },
    { id: "z-sukorejo-barat", kelurahan: "Sukorejo", centerLng: 111.8868, centerLat: -7.1672, halfWM: 540, halfHM: 420, angleDeg: -4, streetStepX: 68, streetStepY: 54, density: 0.92, style: "commercial_strip" },
    { id: "z-sukorejo-timur", kelurahan: "Sukorejo", centerLng: 111.8965, centerLat: -7.1678, halfWM: 560, halfHM: 440, angleDeg: -3, streetStepX: 70, streetStepY: 56, density: 0.9, style: "urban_core" },
    { id: "z-sukoharjo", kelurahan: "Sukoharjo", centerLng: 111.8648, centerLat: -7.1675, halfWM: 620, halfHM: 480, angleDeg: -18, streetStepX: 76, streetStepY: 60, density: 0.84, style: "perumahan" },
    { id: "z-pacul", kelurahan: "Pacul", centerLng: 111.8758, centerLat: -7.1748, halfWM: 620, halfHM: 480, angleDeg: -3, streetStepX: 74, streetStepY: 58, density: 0.86, style: "kampung" },
    { id: "z-kalianyar", kelurahan: "Kalianyar", centerLng: 111.8882, centerLat: -7.1755, halfWM: 620, halfHM: 480, angleDeg: 3, streetStepX: 74, streetStepY: 58, density: 0.87, style: "kampung" },
    { id: "z-sukorejo-selatan", kelurahan: "Sukorejo", centerLng: 111.9008, centerLat: -7.1765, halfWM: 600, halfHM: 460, angleDeg: -4, streetStepX: 74, streetStepY: 58, density: 0.85, style: "perumahan" },
    { id: "z-campurejo-utara", kelurahan: "Campurejo", centerLng: 111.9158, centerLat: -7.1565, halfWM: 680, halfHM: 480, angleDeg: 4, streetStepX: 76, streetStepY: 60, density: 0.85, style: "kampung" },
    { id: "z-campurejo-selatan", kelurahan: "Campurejo", centerLng: 111.9145, centerLat: -7.1695, halfWM: 680, halfHM: 520, angleDeg: -6, streetStepX: 78, streetStepY: 62, density: 0.83, style: "suburban" },
    { id: "z-mulyoagung", kelurahan: "Mulyoagung", centerLng: 111.9148, centerLat: -7.1458, halfWM: 660, halfHM: 440, angleDeg: 11, streetStepX: 78, streetStepY: 60, density: 0.84, style: "kampung" },
    { id: "z-kalirejo", kelurahan: "Kalirejo & Semanding", centerLng: 111.9245, centerLat: -7.1545, halfWM: 540, halfHM: 460, angleDeg: 8, streetStepX: 80, streetStepY: 62, density: 0.8, style: "suburban" },
  ];
}

// Kompleks sekolah, kampus & kantor halaman dalam (courtyard) beratap terakota cerah seperti di kanan-bawah gambar referensi
function getCourtyardComplexBuildings(): IsoBuilding[] {
  const complexes: Array<{
    id: string;
    name: string;
    kelurahan: string;
    lng: number;
    lat: number;
    w: number;
    h: number;
    ang: number;
    kind: IsoBuildingKind;
  }> = [
    { id: "cmp-sman1", name: "SMA Negeri 1 Bojonegoro", kelurahan: "Kepatihan", lng: 111.8785, lat: -7.1522, w: 34, h: 26, ang: 0, kind: "education" },
    { id: "cmp-sman2", name: "SMA Negeri 2 Bojonegoro", kelurahan: "Sukorejo", lng: 111.8925, lat: -7.1648, w: 38, h: 28, ang: -4, kind: "education" },
    { id: "cmp-smkn1", name: "SMK Negeri 1 Bojonegoro", kelurahan: "Ngrowo", lng: 111.8978, lat: -7.1588, w: 42, h: 30, ang: 2, kind: "education" },
    { id: "cmp-smkn2", name: "SMK Negeri 2 Bojonegoro", kelurahan: "Sukorejo", lng: 111.9022, lat: -7.1665, w: 44, h: 32, ang: -3, kind: "education" },
    { id: "cmp-smpn1", name: "SMP Negeri 1 Bojonegoro", kelurahan: "Kauman", lng: 111.8848, lat: -7.1498, w: 32, h: 24, ang: 0, kind: "education" },
    { id: "cmp-unigoro", name: "Kampus Universitas Bojonegoro (Unigoro)", kelurahan: "Kalirejo", lng: 111.9085, lat: -7.1525, w: 48, h: 34, ang: 4, kind: "education" },
    { id: "cmp-dprd", name: "Kompleks Gedung DPRD & Dinas Bojonegoro", kelurahan: "Sukorejo", lng: 111.8968, lat: -7.1695, w: 42, h: 30, ang: -4, kind: "education" },
    { id: "cmp-kodim", name: "Markas Kodim 0813 & Polres Bojonegoro", kelurahan: "Kepatihan", lng: 111.8865, lat: -7.1522, w: 36, h: 26, ang: 0, kind: "heritage" },
    { id: "cmp-terminal", name: "Kompleks Terminal Rajekwesi Bojonegoro", kelurahan: "Ngrowo", lng: 111.8982, lat: -7.1628, w: 40, h: 28, ang: -2, kind: "civic" },
    { id: "cmp-depo-kai", name: "Balai Yasa & Gudang Logistik Stasiun Bojonegoro", kelurahan: "Sukorejo", lng: 111.8825, lat: -7.1636, w: 46, h: 18, ang: -6, kind: "commercial" },
  ];

  const out: IsoBuilding[] = [];
  for (const c of complexes) {
    const rad = c.ang * DEG_TO_RAD;
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);
    const offset = (dxM: number, dyM: number): LngLat => {
      const rx = dxM * cos - dyM * sin;
      const ry = dxM * sin + dyM * cos;
      return [c.lng + rx / KX, c.lat + ry / KY];
    };

    const wingT = 6.5;
    // Sayap Utara, Sayap Selatan, Sayap Barat, Sayap Timur membentuk halaman tengah
    const north = offset(0, c.h - wingT);
    const south = offset(0, -(c.h - wingT));
    const west = offset(-(c.w - wingT), 0);
    const east = offset(c.w - wingT, 0);

    out.push(
      {
        id: `${c.id}-n`,
        name: c.name,
        kind: c.kind,
        roofStyle: "hip",
        ring: makeRectRing(north[0], north[1], c.w, wingT, c.ang),
        levels: 2,
        heightMeters: 9.5,
        kelurahan: c.kelurahan,
        source: "curated",
      },
      {
        id: `${c.id}-s`,
        name: `${c.name} (Sayap Selatan)`,
        kind: c.kind,
        roofStyle: "hip",
        ring: makeRectRing(south[0], south[1], c.w, wingT, c.ang),
        levels: 2,
        heightMeters: 9.5,
        kelurahan: c.kelurahan,
        source: "curated",
      },
      {
        id: `${c.id}-w`,
        name: `${c.name} (Sayap Barat)`,
        kind: c.kind,
        roofStyle: "hip",
        ring: makeRectRing(west[0], west[1], wingT, Math.max(8, c.h - wingT * 1.6), c.ang),
        levels: 2,
        heightMeters: 9.0,
        kelurahan: c.kelurahan,
        source: "curated",
      },
      {
        id: `${c.id}-e`,
        name: `${c.name} (Sayap Timur)`,
        kind: "commercial",
        roofStyle: "flat",
        ring: makeRectRing(east[0], east[1], wingT, Math.max(8, c.h - wingT * 1.6), c.ang),
        levels: 2,
        heightMeters: 8.5,
        kelurahan: c.kelurahan,
        source: "curated",
      }
    );
  }
  return out;
}

function buildFullUrbanFabric() {
  if (cachedUrbanFabric) return cachedUrbanFabric;

  const boundary = getBoundary();
  const kelurahanBoundaries = getCuratedKelurahanBoundaries();
  const waterways = getCuratedWaterways();
  const greenZones = getCuratedGreenZones();
  const roads: IsoRoad[] = [...getCuratedCorridors()];
  const buildings: IsoBuilding[] = [
    ...getCuratedHeroBuildings(),
    ...getCourtyardComplexBuildings(),
  ];

  const CELL_M = 13;
  const occupiedCells = new Set<string>();
  const markOccupied = (lng: number, lat: number, radiusCells = 1) => {
    const gx = Math.round(((lng - BOJONEGORO_CENTER[0]) * KX) / CELL_M);
    const gy = Math.round(((lat - BOJONEGORO_CENTER[1]) * KY) / CELL_M);
    for (let dx = -radiusCells; dx <= radiusCells; dx++) {
      for (let dy = -radiusCells; dy <= radiusCells; dy++) {
        occupiedCells.add(`${gx + dx},${gy + dy}`);
      }
    }
  };
  const isOccupied = (lng: number, lat: number): boolean => {
    const gx = Math.round(((lng - BOJONEGORO_CENTER[0]) * KX) / CELL_M);
    const gy = Math.round(((lat - BOJONEGORO_CENTER[1]) * KY) / CELL_M);
    return occupiedCells.has(`${gx},${gy}`);
  };

  for (const b of buildings) {
    const c = ringCentroid(b.ring);
    markOccupied(c[0], c[1], 2);
  }

  const isInsideWaterwayBuffer = (lng: number, lat: number, extraBufferM = 8): boolean => {
    for (const w of waterways) {
      const dist = pointToPolylineDistMeters(lng, lat, w.coords);
      if (dist < w.widthMeters * 0.55 + extraBufferM) return true;
    }
    return false;
  };

  const isInsideGreenZone = (lng: number, lat: number): boolean => {
    for (const g of greenZones) {
      if (pointInRingLngLat(lng, lat, g.ring)) return true;
    }
    return false;
  };

  const majorCorridors = roads.slice();
  const isOnMajorCorridor = (lng: number, lat: number, marginM = 9): { near: boolean; onRoad: boolean } => {
    let near = false;
    for (const r of majorCorridors) {
      const dist = pointToPolylineDistMeters(lng, lat, r.coords);
      if (dist < r.widthMeters * 0.55 + marginM) {
        return { near: true, onRoad: true };
      }
      if (dist < r.widthMeters * 0.55 + 26) {
        near = true;
      }
    }
    return { near, onRoad: false };
  };

  const zones = getUrbanZoneSpecs();
  let roadSeq = 1;
  let bldgSeq = 1;

  for (let zIdx = 0; zIdx < zones.length; zIdx++) {
    const z = zones[zIdx]!;
    const rad = z.angleDeg * DEG_TO_RAD;
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);

    const toLngLat = (lx: number, ly: number): LngLat => {
      const rx = lx * cos - ly * sin;
      const ry = lx * sin + ly * cos;
      return [
        Number((z.centerLng + rx / KX).toFixed(6)),
        Number((z.centerLat + ry / KY).toFixed(6)),
      ];
    };

    // 1. Bangkitkan jaringan jalan lingkungan mengikuti orientasi blok kelurahan
    for (let y = -z.halfHM; y <= z.halfHM; y += z.streetStepY) {
      let currentSeg: LngLat[] = [];
      for (let x = -z.halfWM; x <= z.halfWM; x += 48) {
        const pt = toLngLat(x, y);
        if (
          pointInRingLngLat(pt[0], pt[1], boundary.ring) &&
          !isInsideWaterwayBuffer(pt[0], pt[1], 4) &&
          !isInsideGreenZone(pt[0], pt[1])
        ) {
          currentSeg.push(pt);
        } else {
          if (currentSeg.length >= 2) {
            roads.push({
              id: `street-h-${z.id}-${roadSeq++}`,
              name: `Jl. Lingkungan ${z.kelurahan}`,
              kind: Math.abs(y) < 15 ? "tertiary" : "residential",
              widthMeters: Math.abs(y) < 15 ? 7.5 : 5.5,
              coords: currentSeg,
              source: "curated",
            });
          }
          currentSeg = [];
        }
      }
      if (currentSeg.length >= 2) {
        roads.push({
          id: `street-h-${z.id}-${roadSeq++}`,
          name: `Jl. Lingkungan ${z.kelurahan}`,
          kind: Math.abs(y) < 15 ? "tertiary" : "residential",
          widthMeters: Math.abs(y) < 15 ? 7.5 : 5.5,
          coords: currentSeg,
          source: "curated",
        });
      }
    }

    for (let x = -z.halfWM; x <= z.halfWM; x += z.streetStepX) {
      let currentSeg: LngLat[] = [];
      for (let y = -z.halfHM; y <= z.halfHM; y += 48) {
        const pt = toLngLat(x, y);
        if (
          pointInRingLngLat(pt[0], pt[1], boundary.ring) &&
          !isInsideWaterwayBuffer(pt[0], pt[1], 4) &&
          !isInsideGreenZone(pt[0], pt[1])
        ) {
          currentSeg.push(pt);
        } else {
          if (currentSeg.length >= 2) {
            roads.push({
              id: `street-v-${z.id}-${roadSeq++}`,
              name: `Gg. ${z.kelurahan}`,
              kind: Math.abs(x) < 15 ? "tertiary" : "residential",
              widthMeters: Math.abs(x) < 15 ? 7.5 : 5.5,
              coords: currentSeg,
              source: "curated",
            });
          }
          currentSeg = [];
        }
      }
      if (currentSeg.length >= 2) {
        roads.push({
          id: `street-v-${z.id}-${roadSeq++}`,
          name: `Gg. ${z.kelurahan}`,
          kind: Math.abs(x) < 15 ? "tertiary" : "residential",
          widthMeters: Math.abs(x) < 15 ? 7.5 : 5.5,
          coords: currentSeg,
          source: "curated",
        });
      }
    }

    // 2. Isi setiap blok jalan dengan kaveling bangunan nyata (rumah genteng terakota, ruko, gudang/komersial putih)
    for (let by = -z.halfHM; by < z.halfHM; by += z.streetStepY) {
      for (let bx = -z.halfWM; bx < z.halfWM; bx += z.streetStepX) {
        const blockSeed = (zIdx + 1) * 9973 + Math.round(bx + 2000) * 37 + Math.round(by + 2000) * 101;
        const blockRoll = detRand(blockSeed);

        const padRoad = 7.5;
        const innerW = z.streetStepX - padRoad * 2;
        const innerH = z.streetStepY - padRoad * 2;
        if (innerW < 16 || innerH < 16) continue;

        // Kadang sebuah blok merupakan kompleks komersial besar atau lapangan kecil
        if (blockRoll > 0.94 && z.style !== "perumahan") {
          const midPt = toLngLat(bx + z.streetStepX * 0.5, by + z.streetStepY * 0.5);
          if (
            !pointInRingLngLat(midPt[0], midPt[1], boundary.ring) ||
            isInsideWaterwayBuffer(midPt[0], midPt[1], 14) ||
            isInsideGreenZone(midPt[0], midPt[1]) ||
            isOccupied(midPt[0], midPt[1])
          ) {
            continue;
          }
          const cCheck = isOnMajorCorridor(midPt[0], midPt[1], 12);
          if (cCheck.onRoad) continue;

          markOccupied(midPt[0], midPt[1], 1);
          const isOrangeHall = detRand(blockSeed + 5) > 0.55;
          buildings.push({
            id: `b-hall-${bldgSeq++}`,
            name: isOrangeHall ? `Gedung Serbaguna / Sekolah ${z.kelurahan}` : `Kompleks Niaga ${z.kelurahan}`,
            kind: isOrangeHall ? "education" : "commercial",
            roofStyle: isOrangeHall ? "hip" : "flat",
            ring: makeRectRing(midPt[0], midPt[1], innerW * 0.42, innerH * 0.38, z.angleDeg),
            levels: isOrangeHall ? 2 : 3,
            heightMeters: isOrangeHall ? 10 : 12.5,
            kelurahan: z.kelurahan,
            source: "curated",
          });
          continue;
        }

        // Baris kaveling bangunan menghadap jalan utara & selatan blok (serta tengah blok untuk kampung padat)
        const rows = z.style === "perumahan" ? 2 : 3;
        const cols = z.style === "perumahan" ? 4 : 3;
        const cellW = innerW / cols;
        const cellH = innerH / rows;

        for (let r = 0; r < rows; r++) {
          for (let c = 0; c < cols; c++) {
            const lotSeed = blockSeed + r * 31 + c * 17;
            if (detRand(lotSeed) > z.density) continue;
            // Sisakan halaman tengah pada sebagian blok
            if (rows === 3 && r === 1 && c === 1 && detRand(lotSeed + 3) < 0.55) continue;

            const jitterX = z.style === "perumahan" ? 0 : (detRand(lotSeed + 7) - 0.5) * 2.2;
            const jitterY = z.style === "perumahan" ? 0 : (detRand(lotSeed + 11) - 0.5) * 2.2;
            const lx = bx + padRoad + (c + 0.5) * cellW + jitterX;
            const ly = by + padRoad + (r + 0.5) * cellH + jitterY;
            const pt = toLngLat(lx, ly);

            if (!pointInRingLngLat(pt[0], pt[1], boundary.ring)) continue;
            if (isInsideWaterwayBuffer(pt[0], pt[1], 10)) continue;
            if (isInsideGreenZone(pt[0], pt[1])) continue;
            if (isOccupied(pt[0], pt[1])) continue;

            const roadCheck = isOnMajorCorridor(pt[0], pt[1], 8.5);
            if (roadCheck.onRoad) continue;

            markOccupied(pt[0], pt[1], 0);

            const typeRoll = detRand(lotSeed + 19);
            let kind: IsoBuildingKind = "residential";
            let roofStyle: IsoRoofStyle = detRand(lotSeed + 23) > 0.45 ? "gable" : "hip";
            let levels = detRand(lotSeed + 29) > 0.72 ? 2 : 1;
            let halfW = Math.min(cellW * 0.43, 5.2 + detRand(lotSeed + 31) * 3.8);
            let halfH = Math.min(cellH * 0.42, 4.8 + detRand(lotSeed + 37) * 3.4);

            if (roadCheck.near && typeRoll > 0.62) {
              // Ruko / bangunan komersial putih-abu di tepi jalan arteri
              kind = typeRoll > 0.86 ? "civic" : "commercial";
              roofStyle = typeRoll > 0.74 ? "flat" : "hip";
              levels = 2 + (detRand(lotSeed + 41) > 0.65 ? 1 : 0);
              halfW = Math.min(cellW * 0.46, 7.5 + detRand(lotSeed + 43) * 3.5);
              halfH = Math.min(cellH * 0.45, 6.5 + detRand(lotSeed + 47) * 3.0);
            } else if (z.style === "perumahan") {
              halfW = cellW * 0.40;
              halfH = cellH * 0.38;
              levels = 1;
              roofStyle = "gable";
            }

            const rotJitter = z.style === "perumahan" ? 0 : (detRand(lotSeed + 53) - 0.5) * 4;
            const heightMeters = Number((levels * 3.5 + (kind === "residential" ? 1.2 : 2.0)).toFixed(1));

            buildings.push({
              id: `b-urb-${bldgSeq++}`,
              name:
                kind === "commercial"
                  ? `Ruko & Niaga ${z.kelurahan}`
                  : kind === "civic"
                    ? `Gedung Layanan ${z.kelurahan}`
                    : `Rumah Warga ${z.kelurahan}`,
              kind,
              roofStyle,
              ring: makeRectRing(pt[0], pt[1], halfW, halfH, z.angleDeg + rotJitter),
              levels,
              heightMeters,
              kelurahan: z.kelurahan,
              source: "curated",
            });
          }
        }
      }
    }
  }

  cachedUrbanFabric = {
    kelurahanBoundaries,
    waterways,
    greenZones,
    roads,
    buildings,
  };
  return cachedUrbanFabric;
}

function buildCuratedFallbackDataset(): BojonegoroIsometricDataset {
  const boundary = getBoundary();
  const landmarks = getCuratedLandmarks();
  const fabric = buildFullUrbanFabric();

  return {
    ok: true,
    source: "curated_fallback",
    fetchedAt: new Date().toISOString(),
    center: BOJONEGORO_CENTER,
    bbox: BOJONEGORO_BBOX,
    boundary,
    kelurahanBoundaries: fabric.kelurahanBoundaries,
    waterways: fabric.waterways,
    greenZones: fabric.greenZones,
    roads: fabric.roads,
    buildings: fabric.buildings,
    landmarks,
    stats: {
      buildings: fabric.buildings.length,
      roads: fabric.roads.length,
      waterways: fabric.waterways.length,
      greenZones: fabric.greenZones.length,
      landmarks: landmarks.length,
      kelurahan: fabric.kelurahanBoundaries.length,
    },
  };
}

function classifyOsmBuilding(tags: Record<string, string>): {
  kind: IsoBuildingKind;
  roofStyle: IsoRoofStyle;
  levels: number;
  heightMeters: number;
} {
  const b = (tags.building || "").toLowerCase();
  const amenity = (tags.amenity || "").toLowerCase();
  const shop = (tags.shop || "").toLowerCase();
  const tourism = (tags.tourism || "").toLowerCase();
  const historic = (tags.historic || "").toLowerCase();

  let kind: IsoBuildingKind = "residential";
  let roofStyle: IsoRoofStyle = "gable";
  let defaultLevels = 1;

  if (amenity === "place_of_worship" || b === "mosque" || b === "church") {
    kind = "religious";
    roofStyle = "dome";
    defaultLevels = 2;
  } else if (amenity === "hospital" || amenity === "clinic" || b === "hospital") {
    kind = "health";
    roofStyle = "flat";
    defaultLevels = 4;
  } else if (amenity === "school" || amenity === "university" || amenity === "college" || b === "school") {
    kind = "education";
    roofStyle = "hip";
    defaultLevels = 2;
  } else if (
    amenity === "townhall" ||
    amenity === "public_building" ||
    b === "government" ||
    b === "civic" ||
    b === "public" ||
    tags.office === "government"
  ) {
    kind = historic ? "heritage" : "civic";
    roofStyle = historic ? "stepped" : "flat";
    defaultLevels = 2;
  } else if (b === "industrial" || b === "warehouse" || b === "manufacture") {
    kind = "industrial";
    roofStyle = "gable";
    defaultLevels = 1;
  } else if (
    b === "commercial" ||
    b === "retail" ||
    b === "office" ||
    b === "hotel" ||
    b === "supermarket" ||
    shop !== "" ||
    tourism === "hotel"
  ) {
    kind = "commercial";
    roofStyle = "flat";
    defaultLevels = 2;
  } else if (b === "apartments") {
    kind = "residential";
    roofStyle = "flat";
    defaultLevels = 3;
  }

  const parsedLevels = Number.parseFloat(tags["building:levels"] || "");
  const levels =
    Number.isFinite(parsedLevels) && parsedLevels > 0
      ? Math.min(14, Math.max(1, Math.round(parsedLevels)))
      : defaultLevels;

  const parsedHeight = Number.parseFloat((tags.height || "").replace(/[^0-9.]/g, ""));
  let heightMeters =
    Number.isFinite(parsedHeight) && parsedHeight >= 2.5
      ? Math.min(60, parsedHeight)
      : levels * 3.4 + (kind === "civic" || kind === "commercial" ? 1.4 : 0.8);

  if (tags["roof:shape"] === "flat") roofStyle = "flat";
  else if (tags["roof:shape"] === "dome") roofStyle = "dome";
  else if (tags["roof:shape"] === "pyramidal" || tags["roof:shape"] === "hipped") roofStyle = "hip";

  if (kind === "religious") heightMeters = Math.max(heightMeters, 9.5);

  return {
    kind,
    roofStyle,
    levels,
    heightMeters: Number(heightMeters.toFixed(1)),
  };
}

function coordsClose(a: LngLat, b: LngLat): boolean {
  return Math.abs(a[0] - b[0]) < 1e-5 && Math.abs(a[1] - b[1]) < 1e-5;
}

function stitchRelationOuterRings(members: OverpassMember[]): LngLat[][] {
  const chains: LngLat[][] = [];
  for (const m of members) {
    if (m.type !== "way" || !Array.isArray(m.geometry) || m.geometry.length < 2) continue;
    if (m.role && m.role !== "outer") continue;
    chains.push(
      m.geometry.map((pt) => [Number(pt.lon.toFixed(6)), Number(pt.lat.toFixed(6))] as LngLat)
    );
  }
  if (chains.length === 0) return [];

  const rings: LngLat[][] = [];
  const used = new Array(chains.length).fill(false);

  for (let i = 0; i < chains.length; i++) {
    if (used[i]) continue;
    used[i] = true;
    const current = [...chains[i]!];

    let extended = true;
    while (extended) {
      extended = false;
      const head = current[0]!;
      const tail = current[current.length - 1]!;
      if (current.length >= 4 && coordsClose(head, tail)) break;

      for (let j = 0; j < chains.length; j++) {
        if (used[j]) continue;
        const cand = chains[j]!;
        const cHead = cand[0]!;
        const cTail = cand[cand.length - 1]!;

        if (coordsClose(tail, cHead)) {
          current.push(...cand.slice(1));
          used[j] = true;
          extended = true;
          break;
        }
        if (coordsClose(tail, cTail)) {
          const rev = [...cand].reverse();
          current.push(...rev.slice(1));
          used[j] = true;
          extended = true;
          break;
        }
        if (coordsClose(head, cTail)) {
          current.unshift(...cand.slice(0, -1));
          used[j] = true;
          extended = true;
          break;
        }
        if (coordsClose(head, cHead)) {
          const rev = [...cand].reverse();
          current.unshift(...rev.slice(0, -1));
          used[j] = true;
          extended = true;
          break;
        }
      }
    }

    if (current.length >= 4) {
      rings.push(current);
    }
  }
  return rings;
}

function parseOverpassResponse(elements: OverpassElement[]): BojonegoroIsometricDataset {
  const buildings: IsoBuilding[] = [];
  const roads: IsoRoad[] = [];
  const waterways: IsoWaterArea[] = [];
  const greenZones: IsoGreenZone[] = [];
  const kelurahanBoundaries: IsoBoundary[] = [];
  let kecBoundary: IsoBoundary | null = null;

  for (const el of elements) {
    const tags = el.tags || {};

    if (el.type === "relation" && Array.isArray(el.members)) {
      const rings = stitchRelationOuterRings(el.members);
      if (rings.length === 0) continue;

      if (tags.boundary === "administrative") {
        const name = tags.name || tags["name:id"] || `Wilayah ${el.id}`;
        const lower = name.toLowerCase();
        const adminLvl = tags.admin_level || "";
        const mainRing = simplifyCoords(rings[0]!, 8.0, true);
        if (mainRing.length >= 4) {
          if (lower.includes("kecamatan bojonegoro") || (adminLvl === "7" && lower.includes("bojonegoro"))) {
            kecBoundary = { id: `osm-kec-${el.id}`, name, ring: mainRing };
          } else if (adminLvl === "8" || adminLvl === "9" || adminLvl === "10" || adminLvl === "7") {
            kelurahanBoundaries.push({ id: `osm-kel-${el.id}`, name, ring: mainRing });
          }
        }
        continue;
      }

      if (tags.building) {
        const meta = classifyOsmBuilding(tags);
        rings.forEach((rawRing, rIdx) => {
          const ring = simplifyCoords(rawRing, 1.4, true);
          if (ring.length >= 4 && approxRingAreaMeters(ring) >= 18) {
            buildings.push({
              id: `osm-rel-b-${el.id}-${rIdx}`,
              name: tags.name || tags["name:id"] || `Kompleks Bangunan ${el.id}`,
              kind: meta.kind,
              roofStyle: meta.roofStyle,
              ring,
              levels: meta.levels,
              heightMeters: meta.heightMeters,
              address: tags["addr:street"] || undefined,
              kelurahan: tags["addr:suburb"] || tags["addr:village"] || undefined,
              source: "osm",
            });
          }
        });
        continue;
      }

      if (tags.waterway === "riverbank" || tags.natural === "water" || tags.water === "river") {
        rings.forEach((rawRing, rIdx) => {
          const ring = simplifyCoords(rawRing, 4.0, true);
          if (ring.length >= 4) {
            waterways.push({
              id: `osm-rel-w-${el.id}-${rIdx}`,
              name: tags.name || "Sungai Bengawan Solo",
              geometryType: "polygon",
              coords: ring,
              widthMeters: 80,
            });
          }
        });
        continue;
      }

      if (
        tags.leisure === "park" ||
        tags.leisure === "stadium" ||
        tags.landuse === "forest" ||
        tags.landuse === "recreation_ground"
      ) {
        rings.forEach((rawRing, rIdx) => {
          const ring = simplifyCoords(rawRing, 3.0, true);
          if (ring.length >= 4 && approxRingAreaMeters(ring) >= 150) {
            greenZones.push({
              id: `osm-rel-g-${el.id}-${rIdx}`,
              name: tags.name || "Ruang Terbuka Hijau",
              kind: tags.landuse === "forest" ? "forest" : "park",
              ring,
              treeDensity: 0.7,
            });
          }
        });
      }
      continue;
    }

    if (el.type !== "way" || !Array.isArray(el.geometry) || el.geometry.length < 2) continue;
    const rawCoords: LngLat[] = el.geometry.map((pt) => [
      Number(pt.lon.toFixed(6)),
      Number(pt.lat.toFixed(6)),
    ]);

    if (tags.building && rawCoords.length >= 3) {
      const ring = simplifyCoords(rawCoords, 1.2, true);
      if (ring.length < 4) continue;
      const area = approxRingAreaMeters(ring);
      if (area < 14) continue;

      const meta = classifyOsmBuilding(tags);
      buildings.push({
        id: `osm-b-${el.id}`,
        name: tags.name || tags["name:id"] || `Bangunan ${tags.building !== "yes" ? tags.building : "Kota"}`,
        kind: meta.kind,
        roofStyle: meta.roofStyle,
        ring,
        levels: meta.levels,
        heightMeters: meta.heightMeters,
        address: tags["addr:street"] || undefined,
        kelurahan: tags["addr:suburb"] || tags["addr:village"] || undefined,
        source: "osm",
      });
      continue;
    }

    if (tags.railway === "rail" || tags.railway === "light_rail") {
      const coords = simplifyCoords(rawCoords, 3.0, false);
      if (coords.length >= 2) {
        roads.push({
          id: `osm-rail-${el.id}`,
          name: tags.name || "Jalur Rel Kereta Api Bojonegoro",
          kind: "rail",
          coords,
          widthMeters: 6,
          source: "osm",
        });
      }
      continue;
    }

    if (tags.highway) {
      const hw = tags.highway.toLowerCase();
      let kind: IsoRoadKind = "residential";
      let widthMeters = 6;
      if (tags.bridge === "yes" || tags.bridge === "viaduct") {
        kind = "bridge";
        widthMeters = 11;
      } else if (hw === "trunk" || hw === "primary") {
        kind = "primary";
        widthMeters = 12;
      } else if (hw === "secondary") {
        kind = "secondary";
        widthMeters = 10;
      } else if (hw === "tertiary") {
        kind = "tertiary";
        widthMeters = 8;
      } else if (hw === "residential" || hw === "unclassified" || hw === "living_street" || hw === "service") {
        kind = "residential";
        widthMeters = hw === "service" ? 4.5 : 6;
      } else {
        continue;
      }

      const coords = simplifyCoords(rawCoords, 2.2, false);
      if (coords.length >= 2) {
        roads.push({
          id: `osm-r-${el.id}`,
          name: tags.name || (kind === "primary" ? "Jalan Arteri Bojonegoro" : "Jalan Kota"),
          kind,
          coords,
          widthMeters,
          oneway: tags.oneway === "yes",
          source: "osm",
        });
      }
      continue;
    }

    if (tags.waterway || tags.natural === "water") {
      const isClosed =
        rawCoords.length >= 4 &&
        rawCoords[0]![0] === rawCoords[rawCoords.length - 1]![0] &&
        rawCoords[0]![1] === rawCoords[rawCoords.length - 1]![1];
      const ww = (tags.waterway || "").toLowerCase();
      const widthMeters = ww === "river" || ww === "riverbank" ? 84 : ww === "canal" ? 22 : 13;
      const coords = simplifyCoords(rawCoords, 3.0, isClosed);
      if (coords.length >= 2) {
        waterways.push({
          id: `osm-w-${el.id}`,
          name: tags.name || (ww === "river" ? "Sungai Bengawan Solo" : "Perairan Kota"),
          geometryType: isClosed && (tags.natural === "water" || ww === "riverbank") ? "polygon" : "line",
          coords,
          widthMeters,
        });
      }
      continue;
    }

    if (
      rawCoords.length >= 4 &&
      (tags.leisure === "park" ||
        tags.leisure === "pitch" ||
        tags.leisure === "garden" ||
        tags.landuse === "grass" ||
        tags.landuse === "forest" ||
        tags.landuse === "recreation_ground")
    ) {
      const ring = simplifyCoords(rawCoords, 2.5, true);
      if (ring.length >= 4 && approxRingAreaMeters(ring) >= 150) {
        greenZones.push({
          id: `osm-g-${el.id}`,
          name: tags.name || "Ruang Terbuka Hijau",
          kind: tags.leisure === "pitch" ? "field" : tags.landuse === "forest" ? "forest" : "park",
          ring,
          treeDensity: tags.leisure === "pitch" ? 0.25 : 0.75,
        });
      }
    }
  }

  // Gabungkan jaringan lengkap Kecamatan Bojonegoro (infill blok yang belum terpetakan di OSM)
  const fabric = buildFullUrbanFabric();
  const landmarks = getCuratedLandmarks();

  const hasBigRiver = waterways.some((w) => w.widthMeters >= 45 || w.name.toLowerCase().includes("bengawan"));
  if (!hasBigRiver) {
    waterways.unshift(...fabric.waterways);
  } else {
    for (const cw of fabric.waterways) {
      if (!waterways.some((w) => w.name.toLowerCase() === cw.name.toLowerCase())) {
        waterways.push(cw);
      }
    }
  }

  for (const gz of fabric.greenZones) {
    if (!greenZones.some((g) => g.name.toLowerCase() === gz.name.toLowerCase())) {
      greenZones.push(gz);
    }
  }

  const finalKelurahan =
    kelurahanBoundaries.length > 0
      ? [
          ...kelurahanBoundaries,
          ...fabric.kelurahanBoundaries.filter(
            (fk) => !kelurahanBoundaries.some((ok) => ok.name.toLowerCase().includes(fk.name.replace(/^Kelurahan\s+|^Desa\s+/i, "").toLowerCase()))
          ),
        ]
      : fabric.kelurahanBoundaries;

  // Spatial occupancy grid (15m) untuk menggabungkan bangunan OSM asli dengan infill blok kota tanpa tumpang tindih
  const GRID_M = 15;
  const osmGrid = new Set<string>();
  for (const b of buildings) {
    const c = ringCentroid(b.ring);
    const gx = Math.round(((c[0] - BOJONEGORO_CENTER[0]) * KX) / GRID_M);
    const gy = Math.round(((c[1] - BOJONEGORO_CENTER[1]) * KY) / GRID_M);
    for (let dx = -1; dx <= 1; dx++) {
      for (let dy = -1; dy <= 1; dy++) {
        osmGrid.add(`${gx + dx},${gy + dy}`);
      }
    }
  }

  const existingNames = new Set(buildings.map((b) => b.name.toLowerCase()));
  for (const hero of getCuratedHeroBuildings()) {
    const heroCentroid = ringCentroid(hero.ring);
    const nearbyDuplicate = buildings.findIndex((b) => {
      const c = ringCentroid(b.ring);
      return Math.abs(c[0] - heroCentroid[0]) < 0.00028 && Math.abs(c[1] - heroCentroid[1]) < 0.00028;
    });
    if (nearbyDuplicate >= 0 && hero.landmarkId) {
      buildings[nearbyDuplicate] = {
        ...buildings[nearbyDuplicate]!,
        name: hero.name,
        kind: hero.kind,
        roofStyle: hero.roofStyle,
        heightMeters: Math.max(buildings[nearbyDuplicate]!.heightMeters, hero.heightMeters),
        landmarkId: hero.landmarkId,
        address: buildings[nearbyDuplicate]!.address || hero.address,
      };
    } else if (!existingNames.has(hero.name.toLowerCase())) {
      buildings.push(hero);
    }
  }

  // Tambahkan infill bangunan untuk blok kelurahan yang kosong pada OSM
  for (const fb of fabric.buildings) {
    if (fb.id.startsWith("hero-")) continue;
    const c = ringCentroid(fb.ring);
    const gx = Math.round(((c[0] - BOJONEGORO_CENTER[0]) * KX) / GRID_M);
    const gy = Math.round(((c[1] - BOJONEGORO_CENTER[1]) * KY) / GRID_M);
    if (!osmGrid.has(`${gx},${gy}`)) {
      buildings.push(fb);
    }
  }

  const finalRoads = roads.length >= 80 ? [...roads, ...getCuratedCorridors()] : [...roads, ...fabric.roads];

  let finalBuildings = buildings;
  if (buildings.length > MAX_OSM_BUILDINGS) {
    finalBuildings = buildings.slice(0, MAX_OSM_BUILDINGS);
  }

  return {
    ok: true,
    source: "hybrid",
    fetchedAt: new Date().toISOString(),
    center: BOJONEGORO_CENTER,
    bbox: BOJONEGORO_BBOX,
    boundary: kecBoundary || getBoundary(),
    kelurahanBoundaries: finalKelurahan,
    waterways,
    greenZones,
    roads: finalRoads,
    buildings: finalBuildings,
    landmarks,
    stats: {
      buildings: finalBuildings.length,
      roads: finalRoads.length,
      waterways: waterways.length,
      greenZones: greenZones.length,
      landmarks: landmarks.length,
      kelurahan: finalKelurahan.length,
    },
  };
}

async function fetchFromOverpass(): Promise<OverpassElement[]> {
  const ql = `
[out:json][timeout:24];
(
  way["building"](${OVERPASS_QUERY_BBOX});
  relation["building"](${OVERPASS_QUERY_BBOX});
  way["highway"~"^(trunk|primary|secondary|tertiary|residential|unclassified|living_street|service)$"](${OVERPASS_QUERY_BBOX});
  way["railway"="rail"](${OVERPASS_QUERY_BBOX});
  way["waterway"~"^(river|canal|stream|drain|riverbank)$"](${OVERPASS_QUERY_BBOX});
  relation["waterway"="riverbank"](${OVERPASS_QUERY_BBOX});
  way["natural"="water"](${OVERPASS_QUERY_BBOX});
  relation["natural"="water"](${OVERPASS_QUERY_BBOX});
  way["leisure"~"^(park|pitch|garden|stadium)$"](${OVERPASS_QUERY_BBOX});
  way["landuse"~"^(grass|forest|recreation_ground)$"](${OVERPASS_QUERY_BBOX});
  relation["boundary"="administrative"]["admin_level"~"^(7|8|9|10)$"](${OVERPASS_QUERY_BBOX});
);
out geom qt;
`.trim();

  let lastError: Error | null = null;
  for (const endpoint of OVERPASS_ENDPOINTS) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 25000);
    try {
      const res = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
          "User-Agent": "MelarosaWebGIS/1.0 (+https://melarosa.bojonegorokab.go.id)",
        },
        body: `data=${encodeURIComponent(ql)}`,
        signal: controller.signal,
      });
      if (!res.ok) {
        throw new Error(`Overpass HTTP ${res.status}`);
      }
      const json = (await res.json()) as { elements?: OverpassElement[] };
      if (Array.isArray(json.elements) && json.elements.length > 20) {
        return json.elements;
      }
      throw new Error("Empty Overpass response");
    } catch (err) {
      lastError = err instanceof Error ? err : new Error(String(err));
    } finally {
      clearTimeout(timer);
    }
  }
  throw lastError || new Error("All Overpass mirrors failed");
}

function readDiskCache(): BojonegoroIsometricDataset | null {
  try {
    if (!fs.existsSync(DISK_CACHE_PATH)) return null;
    const raw = fs.readFileSync(DISK_CACHE_PATH, "utf8");
    const parsed = JSON.parse(raw) as CachedEntry;
    if (
      parsed?.data?.ok &&
      Date.now() < parsed.expiresAt &&
      Array.isArray(parsed.data.kelurahanBoundaries) &&
      parsed.data.buildings.length >= 1000
    ) {
      return parsed.data;
    }
  } catch {
    // Abaikan cache disk yang korup
  }
  return null;
}

function writeDiskCache(entry: CachedEntry): void {
  try {
    fs.writeFileSync(DISK_CACHE_PATH, JSON.stringify(entry), "utf8");
  } catch {
    // Abaikan gagal tulis ke tmp
  }
}

export default defineEventHandler(async (event): Promise<BojonegoroIsometricDataset> => {
  const query = getQuery(event);
  const forceRefresh = query.refresh === "1" || query.refresh === "true";

  setHeader(event, "Cache-Control", "public, max-age=300, s-maxage=900, stale-while-revalidate=3600");

  if (!forceRefresh && memoryCache && Date.now() < memoryCache.expiresAt) {
    return memoryCache.data;
  }

  if (!forceRefresh) {
    const diskCached = readDiskCache();
    if (diskCached) {
      memoryCache = { data: diskCached, expiresAt: Date.now() + CACHE_TTL_MS };
      return diskCached;
    }
  }

  try {
    const elements = await fetchFromOverpass();
    const dataset = parseOverpassResponse(elements);
    const entry = {
      data: dataset,
      expiresAt: Date.now() + CACHE_TTL_MS,
    };
    memoryCache = entry;
    // Hanya simpan ke disk cache jika respons berasal dari query Overpass penuh (bukan mock unit test)
    if (elements.length >= 250 && dataset.buildings.length >= 1000) {
      writeDiskCache(entry);
    }
    return dataset;
  } catch (err) {
    console.warn("[bojonegoro-kota-osm] Overpass unavailable, using complete Bojonegoro urban dataset:", err);
    const fallback = buildCuratedFallbackDataset();
    memoryCache = {
      data: fallback,
      expiresAt: Date.now() + FALLBACK_CACHE_TTL_MS,
    };
    return fallback;
  }
});

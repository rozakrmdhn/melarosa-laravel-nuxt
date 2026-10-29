<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch, type ComponentPublicInstance } from 'vue';
import { KECAMATAN_ENTRIES } from '~/constants/kecamatan';
import { useGeoparkSites } from '~/composables/useGeoparkSites';
import { useGeoportalLayers, type GeoportalLayerItem } from '~/composables/useGeoportalLayers';

interface ThematicLayerConfig {
  id: string;
  name: string;
  defaultAgency: string;
  color: string;
  glowRgba: string;
  surfaceRgba: string;
  borderRgba: string;
  type: string;
  protocolBadge: 'MARTIN MVT' | 'WMS GEOPORTAL' | 'HYBRID MVT + WMS' | 'GEOPARK API';
  active: boolean;
  desc: string;
  martinSources?: string[];
  defaultWmsLayer: string;
  matchKeywords: string[];
}

const config = useRuntimeConfig();
const martinBase = String(config.public?.martinUrl || '/martin').replace(/\/+$/, '');
const DEFAULT_GEOPORTAL_WMS_URL = 'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms';

// Bounding Box resmi Kabupaten Bojonegoro (WGS 84 / EPSG:4326)
const BOJONEGORO_BBOX: [number, number, number, number] = [
  111.4235, -7.4696, 112.1657, -6.9863,
];

const viewMode = ref<'3d' | '2d'>('3d');
const hoveredLayerId = ref<string | null>(null);
const draggedLayerIndex = ref<number | null>(null);
const dragOverLayerIndex = ref<number | null>(null);

// Integrasi data riil dari Katalog Geoportal Bojonegoro (CSW/WMS) dan Situs Geopark Bojonegoro
const { allLayers: geoportalCatalog } = useGeoportalLayers();
const { sites: geoparkSites } = useGeoparkSites();

// Status pemuatan tile riil per layer (WMS GetMap & OpenLayers MVT)
const wmsLoadedMap = ref<Record<string, boolean>>({});
const wmsErrorMap = ref<Record<string, boolean>>({});
const mvtTilesLoadedCount = ref(0);
const isOlReady = ref(false);

const INITIAL_LAYER_CONFIGS: ThematicLayerConfig[] = [
  {
    id: 'layer-geopark',
    name: 'Titik Situs Geopark',
    defaultAgency: 'Dinas Kebudayaan & Pariwisata',
    color: '#EC4899',
    glowRgba: 'rgba(236, 72, 153, 0.48)',
    surfaceRgba: 'rgba(236, 72, 153, 0.08)',
    borderRgba: 'rgba(236, 72, 153, 0.7)',
    type: 'Point · 31 Situs Terverifikasi',
    protocolBadge: 'GEOPARK API',
    active: true,
    desc: 'Sebaran 31 situs Geopark Nasional Bojonegoro (Wonocolo, Kayangan Api, Waduk Pacal).',
    defaultWmsLayer: 'palapa:CAKUPAN_GEOPARK',
    matchKeywords: ['geopark', 'geosite', 'pariwisata', 'wisata', 'cagar budaya'],
  },
  {
    id: 'layer-jalan',
    name: 'Jaringan Jalan Poros Desa',
    defaultAgency: 'Dinas PU Bina Marga · Bappeda',
    color: '#10B981',
    glowRgba: 'rgba(16, 185, 129, 0.45)',
    surfaceRgba: 'rgba(16, 185, 129, 0.08)',
    borderRgba: 'rgba(16, 185, 129, 0.65)',
    type: 'LineString (MVT)',
    protocolBadge: 'MARTIN MVT',
    active: true,
    desc: 'Jaringan 1.187 ruas jalan poros penghubung antardesa dan segmen konstruksi.',
    martinSources: ['jalan_porosdesa', 'infrastruktur_segmen'],
    defaultWmsLayer: 'palapa:JALAN_LN_25K',
    matchKeywords: ['jalan poros', 'jalan', 'bina marga', 'ruas jalan'],
  },
  {
    id: 'layer-pertanian',
    name: 'Kawasan Lahan Pertanian',
    defaultAgency: 'Dinas Ketahanan Pangan & Pertanian',
    color: '#F59E0B',
    glowRgba: 'rgba(245, 158, 11, 0.45)',
    surfaceRgba: 'rgba(245, 158, 11, 0.08)',
    borderRgba: 'rgba(245, 158, 11, 0.65)',
    type: 'Zona Tematik (OGC WMS)',
    protocolBadge: 'WMS GEOPORTAL',
    active: true,
    desc: 'Zona lahan sawah lestari (LP2B) dan kawasan pertanian pangan produktif.',
    defaultWmsLayer: 'palapa:PERTANIAN_AR_25K',
    matchKeywords: ['pertanian', 'sawah', 'lp2b', 'lahan', 'pangan', 'perkebunan'],
  },
  {
    id: 'layer-tataruang',
    name: 'Batas Administrasi & Kawasan',
    defaultAgency: 'Bappeda Bojonegoro',
    color: '#8B5CF6',
    glowRgba: 'rgba(139, 92, 246, 0.45)',
    surfaceRgba: 'rgba(139, 92, 246, 0.08)',
    borderRgba: 'rgba(139, 92, 246, 0.65)',
    type: 'Poligon Wilayah (MVT + WMS)',
    protocolBadge: 'HYBRID MVT + WMS',
    active: true,
    desc: 'Batas wilayah 28 kecamatan dan 430 desa/kelurahan se-Kabupaten Bojonegoro.',
    martinSources: ['bataswilayah_kabupaten'],
    defaultWmsLayer: 'palapa:BATAS_ADMINISTRASI_AR',
    matchKeywords: ['batas', 'administrasi', 'kecamatan', 'desa', 'wilayah'],
  },
  {
    id: 'layer-sungai',
    name: 'Sungai & Bengawan Solo',
    defaultAgency: 'Dinas PU Sumber Daya Air',
    color: '#3B82F6',
    glowRgba: 'rgba(59, 130, 246, 0.45)',
    surfaceRgba: 'rgba(59, 130, 246, 0.08)',
    borderRgba: 'rgba(59, 130, 246, 0.65)',
    type: 'River (OGC WMS)',
    protocolBadge: 'WMS GEOPORTAL',
    active: true,
    desc: 'Alur utama DAS Bengawan Solo dan jaringan anak sungai daerah.',
    defaultWmsLayer: 'palapa:SUNGAI_LN_25K',
    matchKeywords: ['sungai', 'bengawan', 'hidrologi', ' sumber daya air', 'das ', 'irigasi'],
  },
];

// Urutan di dalam array `layers` merepresentasikan urutan tumpukan dari ATAS (indeks 0) ke BAWAH (indeks terakhir)
const layers = ref<ThematicLayerConfig[]>(
  INITIAL_LAYER_CONFIGS.map((item) => ({ ...item }))
);

// Pencocokan dinamis dengan katalog aktif Geoportal Bojonegoro (/api/v1/layers/geoportal)
function findMatchedGeoportalLayer(cfg: ThematicLayerConfig): GeoportalLayerItem | null {
  const catalog = geoportalCatalog.value || [];
  if (catalog.length === 0) return null;

  for (const kw of cfg.matchKeywords) {
    const found = catalog.find((item) => {
      const hay = `${item.name || ''} ${item.layer_name || ''} ${item.category || ''} ${item.opd_name || ''}`.toLowerCase();
      return hay.includes(kw.toLowerCase());
    });
    if (found) return found;
  }
  return null;
}

// Daftar layer sesuai urutan panel kanan (Indeks 0 = Paling Atas, Indeks Terakhir = Paling Bawah)
const resolvedLayers = computed(() => {
  const activeIdsTopToBottom = layers.value.filter((l) => l.active).map((l) => l.id);
  const totalActive = activeIdsTopToBottom.length;

  return layers.value.map((cfg, listIndex) => {
    const matched = findMatchedGeoportalLayer(cfg);
    const wmsUrl = matched?.url || DEFAULT_GEOPORTAL_WMS_URL;
    const wmsLayerName = matched?.layer_name || cfg.defaultWmsLayer;
    const agency = matched?.opd_name || cfg.defaultAgency;

    // Bangun URL OGC WMS GetMap transparan menggunakan BBOX Kabupaten Bojonegoro
    const bboxStr = BOJONEGORO_BBOX.join(',');
    const wmsGetMapUrl = `${wmsUrl}${wmsUrl.includes('?') ? '&' : '?'}SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&FORMAT=image/png&TRANSPARENT=true&SRS=EPSG:4326&BBOX=${bboxStr}&WIDTH=800&HEIGHT=512&LAYERS=${encodeURIComponent(wmsLayerName)}`;

    const technicalSourceLabel = cfg.martinSources?.length
      ? `/martin/${cfg.martinSources.join(' + ')}`
      : wmsLayerName;

    // Hitung indeks tumpukan vertikal (0 = paling bawah di atas basemap, totalActive - 1 = paling atas)
    const activePosFromTop = activeIdsTopToBottom.indexOf(cfg.id);
    const stackElevationIndex =
      activePosFromTop >= 0 ? totalActive - 1 - activePosFromTop : 0;

    return {
      ...cfg,
      listIndex,
      orderNumber: listIndex + 1,
      activeOrderFromTop: activePosFromTop >= 0 ? activePosFromTop + 1 : null,
      stackElevationIndex,
      agency,
      wmsUrl,
      wmsLayerName,
      wmsGetMapUrl,
      technicalSourceLabel,
      hasLiveCatalogMatch: Boolean(matched),
    };
  });
});

// Urutan DOM stabil untuk kanvas 3D/2D agar instance OpenLayers & transisi CSS Z-axis tidak ter-reset saat urutan diubah
const STABLE_DOM_IDS = [
  'layer-sungai',
  'layer-tataruang',
  'layer-pertanian',
  'layer-jalan',
  'layer-geopark',
];

const domStablePlanes = computed(() => {
  return STABLE_DOM_IDS.map((id) => resolvedLayers.value.find((l) => l.id === id)).filter(
    (l): l is NonNullable<(typeof resolvedLayers.value)[number]> => Boolean(l)
  );
});

const activeLayers = computed(() => resolvedLayers.value.filter((l) => l.active));
const hasAnyActiveLayer = computed(() => activeLayers.value.length > 0);
const allLayersActive = computed(() => layers.value.every((l) => l.active));

function toggleLayer(id: string) {
  const target = layers.value.find((l) => l.id === id);
  if (target) {
    target.active = !target.active;
  }
}

function toggleAllLayers() {
  const nextState = !allLayersActive.value;
  for (const l of layers.value) {
    l.active = nextState;
  }
}

function activateDefaultLayers() {
  for (const l of layers.value) {
    const def = INITIAL_LAYER_CONFIGS.find((d) => d.id === l.id);
    l.active = def ? def.active : true;
  }
}

// Fungsi Simulasi Ordering Layer (Geser Naik, Geser Turun, Balik Urutan, Reset, & Drag-and-Drop)
function moveLayerOrder(index: number, direction: -1 | 1) {
  const targetIndex = index + direction;
  if (targetIndex < 0 || targetIndex >= layers.value.length) return;
  const list = [...layers.value];
  const [moved] = list.splice(index, 1);
  if (moved) {
    list.splice(targetIndex, 0, moved);
    layers.value = list;
  }
}

function reverseLayerOrder() {
  layers.value = [...layers.value].reverse();
}

function resetLayerOrder() {
  const currentActiveState = new Map(layers.value.map((l) => [l.id, l.active]));
  layers.value = INITIAL_LAYER_CONFIGS.map((item) => ({
    ...item,
    active: currentActiveState.get(item.id) ?? item.active,
  }));
}

function onDragStart(index: number, event: DragEvent) {
  draggedLayerIndex.value = index;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(index));
  }
}

function onDragOver(index: number, event: DragEvent) {
  event.preventDefault();
  if (draggedLayerIndex.value === null || draggedLayerIndex.value === index) return;
  dragOverLayerIndex.value = index;
}

function onDrop(index: number, event: DragEvent) {
  event.preventDefault();
  const fromIndex = draggedLayerIndex.value;
  draggedLayerIndex.value = null;
  dragOverLayerIndex.value = null;
  if (fromIndex === null || fromIndex === index) return;

  const list = [...layers.value];
  const [moved] = list.splice(fromIndex, 1);
  if (moved) {
    list.splice(index, 0, moved);
    layers.value = list;
  }
}

function onDragEnd() {
  draggedLayerIndex.value = null;
  dragOverLayerIndex.value = null;
}

// Proyeksi koordinat asli WGS 84 [lng, lat] ke ruang kanvas SVG 500x320 secara presisi
function projectWgs84ToSvg(coord: [number, number]): { x: number; y: number } {
  const [minLng, minLat, maxLng, maxLat] = BOJONEGORO_BBOX;
  const padX = 18;
  const padY = 16;
  const drawW = 500 - padX * 2;
  const drawH = 320 - padY * 2;
  const x = padX + ((coord[0] - minLng) / (maxLng - minLng)) * drawW;
  const y = padY + ((maxLat - coord[1]) / (maxLat - minLat)) * drawH;
  return {
    x: Number(x.toFixed(1)),
    y: Number(y.toFixed(1)),
  };
}

// 1. Data Riil Geopark Bojonegoro (31 Situs Terverifikasi + 3 Situs Utama Berlabel)
const projectedGeoparkSites = computed(() => {
  const validSites = (geoparkSites.value || []).filter((s) => s.hasCoordinate);
  return validSites.map((s) => {
    const pt = projectWgs84ToSvg(s.coordinate);
    const lowerName = s.name.toLowerCase();
    const isFeatured =
      lowerName.includes('wonocolo') ||
      lowerName.includes('kayangan api') ||
      lowerName.includes('waduk pacal');

    let shortLabel = s.name;
    if (lowerName.includes('wonocolo')) shortLabel = 'Wonocolo';
    else if (lowerName.includes('kayangan api')) shortLabel = 'Kayangan Api';
    else if (lowerName.includes('waduk pacal')) shortLabel = 'Waduk Pacal';

    return {
      id: s.id,
      code: s.code,
      name: s.name,
      shortLabel,
      coordinate: s.coordinate,
      x: pt.x,
      y: pt.y,
      isFeatured,
    };
  });
});

const featuredGeoparkSites = computed(() =>
  projectedGeoparkSites.value.filter((s) => s.isFeatured)
);

// 2. Data Riil 28 Kecamatan Kabupaten Bojonegoro (Batas BBOX & Titik Pemerintahan)
const projectedKecamatanRects = computed(() => {
  return KECAMATAN_ENTRIES.map((k) => {
    const [minLng, minLat, maxLng, maxLat] = k.bbox;
    const tl = projectWgs84ToSvg([minLng, maxLat]);
    const br = projectWgs84ToSvg([maxLng, minLat]);
    const center = projectWgs84ToSvg(k.coordinate);
    return {
      name: k.name,
      zona: k.zona,
      x: tl.x,
      y: tl.y,
      w: Math.max(12, Number((br.x - tl.x).toFixed(1))),
      h: Math.max(10, Number((br.y - tl.y).toFixed(1))),
      cx: center.x,
      cy: center.y,
    };
  });
});

// Poligon keliling Kabupaten Bojonegoro dalam koordinat WGS 84 asli
const BOJONEGORO_OUTLINE_WGS84: [number, number][] = [
  [111.432, -7.305],
  [111.495, -7.235],
  [111.575, -7.152],
  [111.615, -7.005],
  [111.692, -7.045],
  [111.775, -7.082],
  [111.882, -7.115],
  [112.025, -7.072],
  [112.155, -7.105],
  [112.138, -7.225],
  [112.065, -7.325],
  [111.965, -7.392],
  [111.825, -7.452],
  [111.695, -7.455],
  [111.585, -7.358],
  [111.462, -7.362],
];

const kabupatenPolygonPoints = computed(() =>
  BOJONEGORO_OUTLINE_WGS84.map((c) => {
    const p = projectWgs84ToSvg(c);
    return `${p.x},${p.y}`;
  }).join(' ')
);

// 3. Alur Geografis Sungai Bengawan Solo & Anak Sungai Utama (Koordinat WGS 84 Riil)
const BENGAWAN_SOLO_WGS84: [number, number][] = [
  [111.432, -7.306],
  [111.535, -7.265],
  [111.595, -7.195],
  [111.622, -7.148],
  [111.698, -7.118],
  [111.764, -7.155],
  [111.868, -7.136],
  [111.955, -7.142],
  [112.033, -7.098],
  [112.148, -7.115],
];

const TRIBUTARIES_WGS84: [number, number][][] = [
  [
    [111.871, -7.362],
    [111.855, -7.265],
    [111.878, -7.152],
  ],
  [
    [111.816, -7.411],
    [111.785, -7.285],
    [111.758, -7.152],
  ],
  [
    [111.652, -7.325],
    [111.573, -7.214],
  ],
  [
    [112.016, -7.315],
    [112.018, -7.185],
    [112.033, -7.102],
  ],
];

function coordsToSvgPath(coords: [number, number][]): string {
  return coords
    .map((c, idx) => {
      const p = projectWgs84ToSvg(c);
      return `${idx === 0 ? 'M' : 'L'} ${p.x},${p.y}`;
    })
    .join(' ');
}

const bengawanSoloSvgPath = computed(() => coordsToSvgPath(BENGAWAN_SOLO_WGS84));
const tributariesSvgPaths = computed(() => TRIBUTARIES_WGS84.map(coordsToSvgPath));

// 4. Koridor Jaringan Jalan Poros Desa Antarkecamatan (Koordinat WGS 84 Riil)
const ROAD_CORRIDORS_WGS84: [number, number][][] = [
  ['Margomulyo', 'Ngraho', 'Padangan', 'Purwosari', 'Gayam', 'Kalitidu', 'Trucuk', 'Bojonegoro', 'Kapas', 'Balen', 'Sumberrejo', 'Kanor', 'Baureno']
    .map((n) => KECAMATAN_ENTRIES.find((k) => k.name === n)?.coordinate)
    .filter((c): c is [number, number] => Boolean(c)),
  ['Kedewan', 'Kasiman', 'Padangan', 'Tambakrejo', 'Ngambon', 'Ngasem', 'Dander', 'Temayang', 'Gondang', 'Sekar']
    .map((n) => KECAMATAN_ENTRIES.find((k) => k.name === n)?.coordinate)
    .filter((c): c is [number, number] => Boolean(c)),
  ['Malo', 'Kalitidu', 'Ngasem', 'Bubulan', 'Temayang', 'Sugihwaras', 'Kedungadem', 'Kepohbaru', 'Baureno']
    .map((n) => KECAMATAN_ENTRIES.find((k) => k.name === n)?.coordinate)
    .filter((c): c is [number, number] => Boolean(c)),
  ['Bojonegoro', 'Dander', 'Sukosewu', 'Sugihwaras', 'Kedungadem']
    .map((n) => KECAMATAN_ENTRIES.find((k) => k.name === n)?.coordinate)
    .filter((c): c is [number, number] => Boolean(c)),
  ['Kalitidu', 'Gayam', 'Ngambon', 'Sekar']
    .map((n) => KECAMATAN_ENTRIES.find((k) => k.name === n)?.coordinate)
    .filter((c): c is [number, number] => Boolean(c)),
  ['Sumberrejo', 'Sukosewu', 'Kepohbaru']
    .map((n) => KECAMATAN_ENTRIES.find((k) => k.name === n)?.coordinate)
    .filter((c): c is [number, number] => Boolean(c)),
];

const roadCorridorSvgPaths = computed(() => ROAD_CORRIDORS_WGS84.map(coordsToSvgPath));

// 5. Zona Pertanian Pangan Berkelanjutan (Sentra Padi Lembah Bengawan & Irigasi Pacal)
const AGRICULTURE_ZONES_WGS84: { label: string; coords: [number, number][] }[] = [
  {
    label: 'LP2B Lembah Timur (Kapas - Balen - Sumberrejo - Kanor - Baureno)',
    coords: [
      [111.895, -7.135],
      [112.135, -7.095],
      [112.125, -7.235],
      [111.915, -7.245],
    ],
  },
  {
    label: 'LP2B Tengah & Irigasi Pacal (Dander - Sukosewu - Sugihwaras - Kedungadem)',
    coords: [
      [111.835, -7.215],
      [112.045, -7.225],
      [112.055, -7.335],
      [111.855, -7.315],
    ],
  },
  {
    label: 'LP2B Barat (Padangan - Purwosari - Gayam - Kalitidu)',
    coords: [
      [111.595, -7.135],
      [111.815, -7.125],
      [111.795, -7.215],
      [111.615, -7.225],
    ],
  },
];

const agricultureZonePolygons = computed(() =>
  AGRICULTURE_ZONES_WGS84.map((z) => ({
    label: z.label,
    points: z.coords
      .map((c) => {
        const p = projectWgs84ToSvg(c);
        return `${p.x},${p.y}`;
      })
      .join(' '),
  }))
);

// Tinggi kanvas visual isometrik: mengisi penuh (flex-1 / full height) tinggi card tumpang susun agar sejajar dengan card lapisan di kanan
const dynamicCanvasStyle = computed<Record<string, string>>(() => {
  const count = activeLayers.value.length;
  if (count === 0) {
    return {
      minHeight: '380px',
    };
  }
  if (viewMode.value === '2d') {
    return {
      minHeight: '460px',
    };
  }
  // Mode 3D Exploded: minHeight responsif mengikuti jumlah layer, dan di desktop otomatis meregang penuh (flex-1) mengikuti tinggi card
  const calculatedMinHeight = 420 + count * 44;
  return {
    minHeight: `${calculatedMinHeight}px`,
  };
});

// Gap vertikal antar layer (sumbu Z) menyesuaikan jumlah layer aktif dan memanfaatkan ruang vertikal kanvas full-height
const layerGap3D = computed<number>(() => {
  const count = activeLayers.value.length;
  if (count <= 1) return 56;
  if (count === 2) return 54;
  if (count === 3) return 50;
  if (count === 4) return 46;
  return 44; // 5 layer aktif: 44px per tingkat (total 220px) agar terpisah jelas di kanvas full-height
});

// Kompensasi titik tengah vertikal (.iso-stage) berdasarkan total tinggi tumpukan 3D maupun 2D agar tepat berada di tengah kanvas full-height
const isoStageDynamicStyle = computed<Record<string, string>>(() => {
  const count = activeLayers.value.length;
  if (viewMode.value === '2d') {
    const yOffset2D = count <= 1 ? 0 : Math.round(((count - 1) * 7) / 2);
    return {
      transform: `translateY(${yOffset2D}px) rotateX(0deg) rotateZ(0deg)`,
    };
  }
  if (count === 0) {
    return {
      transform: 'translateY(0px) rotateX(60deg) rotateZ(-45deg)',
    };
  }
  // Total elevasi Z tertinggi = count * layerGap3D; kompensasi setengah proyeksi vertikal ke bawah agar tumpukan terpusat di tengah kanvas
  const maxZ = count * layerGap3D.value;
  const yOffset3D = Math.round(maxZ * 0.44);
  return {
    transform: `translateY(${yOffset3D}px) rotateX(60deg) rotateZ(-45deg)`,
  };
});

// Style Basemap Topografi: otomatis menghilang (fade out + turun di sumbu Z) ketika seluruh layer nonaktif
const basemapPlaneStyle = computed<Record<string, string>>(() => {
  if (!hasAnyActiveLayer.value) {
    return {
      transform: 'translate3d(0px, 0px, -48px) scale(0.94)',
      opacity: '0',
      pointerEvents: 'none',
    };
  }
  return {
    transform: 'translate3d(0px, 0px, 0px) scale(1)',
    opacity: '1',
    pointerEvents: 'auto',
  };
});

// Hitung posisi Z & gap dinamis berdasarkan urutan layer (`stackElevationIndex`) pada mode 3D Exploded maupun 2D Flat
function getPlaneStyle(layer: NonNullable<(typeof resolvedLayers.value)[number]>): Record<string, string> {
  const isHovered = hoveredLayerId.value === layer.id;
  const isSomeOtherHovered = Boolean(hoveredLayerId.value && hoveredLayerId.value !== layer.id);
  const stackIndex = layer.stackElevationIndex;

  if (viewMode.value === '2d') {
    // Mode 2D Flat: berikan sedikit step vertikal (-7px per level) & z-offset terukur agar susunan kartu lapisan tetap terasa jelas dan rapi
    const stepY = layer.active ? -(stackIndex * 7) - (isHovered ? 5 : 0) : -22;
    const zOffset = layer.active ? (stackIndex + 1) * 4 + (isHovered ? 12 : 0) : 32;
    const scaleVal = isHovered && layer.active ? 1.018 : layer.active ? 1 : 0.96;
    return {
      transform: `translate3d(0px, ${stepY}px, ${zOffset}px) scale(${scaleVal})`,
      zIndex: String(10 + stackIndex * 5 + (isHovered ? 20 : 0)),
      opacity: layer.active ? (isSomeOtherHovered ? '0.68' : '1') : '0',
      borderColor: isHovered ? layer.color : layer.borderRgba,
      backgroundColor: isHovered ? 'rgba(15, 23, 42, 0.56)' : 'rgba(11, 17, 32, 0.28)',
      boxShadow:
        isHovered && layer.active
          ? `0 0 28px -4px ${layer.glowRgba}, 0 10px 20px -8px rgba(0, 0, 0, 0.6)`
          : `0 6px 16px -6px rgba(0, 0, 0, 0.45)`,
      pointerEvents: layer.active ? 'auto' : 'none',
    };
  }

  // Mode 3D Exploded: gap vertikal proporsional (`layerGap3D`) sesuai ruang kanvas full-height
  const stepZ = layerGap3D.value;
  const targetZ = stepZ + stackIndex * stepZ + (isHovered ? 16 : 0);
  const enterExitZ = targetZ + 64;
  const currentZ = layer.active ? targetZ : enterExitZ;

  return {
    transform: `translate3d(0px, 0px, ${currentZ}px)`,
    zIndex: String(10 + stackIndex * 5),
    opacity: layer.active ? (isSomeOtherHovered ? '0.62' : '1') : '0',
    borderColor: isHovered ? layer.color : layer.borderRgba,
    backgroundColor: isHovered ? 'rgba(15, 23, 42, 0.64)' : 'rgba(11, 17, 32, 0.44)',
    boxShadow: isHovered
      ? `0 0 34px -2px ${layer.glowRgba}, inset 0 0 18px ${layer.surfaceRgba}`
      : `0 16px 32px -10px rgba(0, 0, 0, 0.68), 0 0 16px -4px ${layer.glowRgba}`,
    pointerEvents: layer.active ? 'auto' : 'none',
  };
}

// OpenLayers Live Martin MVT & WMS Engine (2D Flat & Plane Canvases)
const mvtPlaneContainers: Record<string, HTMLElement | null> = {
  'layer-jalan': null,
  'layer-tataruang': null,
};

function setMvtPlaneRef(layerId: string, el: Element | ComponentPublicInstance | null) {
  if (layerId === 'layer-jalan' || layerId === 'layer-tataruang') {
    mvtPlaneContainers[layerId] = (el as HTMLElement) || null;
  }
}

let roadMapInstance: any = null;
let boundaryMapInstance: any = null;

async function initLiveMartinPlanes() {
  if (!import.meta.client) return;

  try {
    const [
      { default: Map },
      { default: View },
      { default: VectorTileLayer },
      { default: VectorTileSource },
      { default: MVT },
      stylePkg,
      projPkg,
    ] = await Promise.all([
      import('ol/Map.js'),
      import('ol/View.js'),
      import('ol/layer/VectorTile.js'),
      import('ol/source/VectorTile.js'),
      import('ol/format/MVT.js'),
      import('ol/style.js'),
      import('ol/proj.js'),
    ]);

    const extent3857 = projPkg.transformExtent(BOJONEGORO_BBOX, 'EPSG:4326', 'EPSG:3857');

    // 1. Live Martin MVT Plane: Jaringan Jalan Poros Desa + Infrastruktur Segmen
    const roadTarget = mvtPlaneContainers['layer-jalan'];
    if (roadTarget && !roadMapInstance) {
      const jalanSource = new VectorTileSource({
        format: new MVT({ idProperty: 'id' }),
        url: `${martinBase}/jalan_porosdesa/{z}/{x}/{y}`,
        maxZoom: 18,
        minZoom: 7,
      });

      const segmenSource = new VectorTileSource({
        format: new MVT({ idProperty: 'id' }),
        url: `${martinBase}/infrastruktur_segmen/{z}/{x}/{y}`,
        maxZoom: 18,
        minZoom: 7,
      });

      const onTileLoad = () => {
        mvtTilesLoadedCount.value++;
      };
      jalanSource.on('tileloadend', onTileLoad);
      segmenSource.on('tileloadend', onTileLoad);

      const jalanLayer = new VectorTileLayer({
        source: jalanSource,
        style: new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: '#10B981',
            width: 1.8,
            lineCap: 'round',
            lineJoin: 'round',
          }),
        }),
      });

      const segmenLayer = new VectorTileLayer({
        source: segmenSource,
        style: new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color: '#34D399',
            width: 2.4,
            lineCap: 'round',
            lineJoin: 'round',
          }),
        }),
      });

      roadMapInstance = new Map({
        target: roadTarget,
        layers: [jalanLayer, segmenLayer],
        view: new View({
          center: projPkg.fromLonLat([111.84, -7.22]),
          zoom: 9.8,
        }),
        controls: [],
        interactions: [],
      });

      roadMapInstance.getView().fit(extent3857, {
        padding: [10, 10, 10, 10],
      });
    }

    // 2. Live Martin MVT Plane: Batas Wilayah Kabupaten
    const boundaryTarget = mvtPlaneContainers['layer-tataruang'];
    if (boundaryTarget && !boundaryMapInstance) {
      const batasSource = new VectorTileSource({
        format: new MVT({ idProperty: 'id' }),
        url: `${martinBase}/bataswilayah_kabupaten/{z}/{x}/{y}`,
        maxZoom: 18,
        minZoom: 6,
      });

      batasSource.on('tileloadend', () => {
        mvtTilesLoadedCount.value++;
      });

      const batasLayer = new VectorTileLayer({
        source: batasSource,
        style: [
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: 'rgba(139, 92, 246, 0.35)',
              width: 5,
            }),
            fill: new stylePkg.Fill({
              color: 'rgba(139, 92, 246, 0.12)',
            }),
          }),
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: '#A78BFA',
              width: 2,
              lineDash: [6, 4],
            }),
          }),
        ],
      });

      boundaryMapInstance = new Map({
        target: boundaryTarget,
        layers: [batasLayer],
        view: new View({
          center: projPkg.fromLonLat([111.84, -7.22]),
          zoom: 9.8,
        }),
        controls: [],
        interactions: [],
      });

      boundaryMapInstance.getView().fit(extent3857, {
        padding: [10, 10, 10, 10],
      });
    }

    isOlReady.value = true;
  } catch (err) {
    console.warn('[SpatialDataStory] Gagal memuat OpenLayers MVT plane:', err);
  }
}

watch([viewMode, () => activeLayers.value.length], () => {
  setTimeout(() => {
    roadMapInstance?.updateSize?.();
    boundaryMapInstance?.updateSize?.();
  }, 350);
});

onMounted(() => {
  initLiveMartinPlanes();
});

onBeforeUnmount(() => {
  if (roadMapInstance) {
    roadMapInstance.setTarget(null);
    roadMapInstance = null;
  }
  if (boundaryMapInstance) {
    boundaryMapInstance.setTarget(null);
    boundaryMapInstance = null;
  }
});
</script>

<template>
  <section
    aria-labelledby="spatial-heading"
    class="relative w-full bg-[#F7F8F5] dark:bg-[#070b14] py-16 sm:py-20 lg:py-24 border-b border-[#DFE5DE] dark:border-white/10"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-[#DFE5DE] dark:border-white/10 pb-6">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-[#2563EB] dark:text-blue-400">
            KETERPADUAN DATA SPASIAL
          </p>
          <h2
            id="spatial-heading"
            class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[#17251F] dark:text-white"
          >
            Satu Peta, Banyak Lapisan.
          </h2>
          <p class="mt-1.5 text-sm sm:text-base text-[#647067] dark:text-slate-300 max-w-xl leading-relaxed">
            Padukan data jalan desa, sungai, batas wilayah, pertanian, dan Geopark Bojonegoro.
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <NuxtLink
            to="/dataset"
            class="inline-flex items-center gap-2 rounded-xl bg-[#2563EB] hover:bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
          >
            <span>Jelajahi Data Spasial</span>
            <UIcon name="i-lucide-database" class="size-4" />
          </NuxtLink>
        </div>
      </div>

      <div class="mt-8 grid grid-cols-1 lg:grid-cols-12 gap-8 lg:items-stretch">
        <!-- Kolom Kiri (Pratinjau 3D Isometrik & 2D Flat - Tinggi Sejajar Sama Persis dengan Card Lapisan di Kanan) -->
        <div class="lg:col-span-7 h-full flex flex-col rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] p-4 sm:p-6 shadow-xs gap-4 transition-all duration-500">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#DFE5DE] dark:border-white/10 pb-3.5">
            <div class="space-y-0.5">
              <h3 class="text-xs sm:text-sm font-bold tracking-wide text-[#17251F] dark:text-white">
                SIMULASI TUMPANG SUSUN LAPISAN
              </h3>
              <p class="text-xs text-[#647067] dark:text-slate-400">
                <template v-if="hasAnyActiveLayer">
                  {{ activeLayers.length }} dari {{ layers.length }} lapisan aktif · Sumber: Martin MVT ({{ mvtTilesLoadedCount > 0 ? `${mvtTilesLoadedCount} tile termuat` : 'Siap' }}) &amp; WMS Geoportal
                </template>
                <template v-else>
                  0 dari {{ layers.length }} lapisan aktif · Basemap topografi disembunyikan
                </template>
              </p>
            </div>

            <!-- Sakelar Mode Tumpuk (3D Exploded) vs Mode Gabung (2D Flat) -->
            <div
              role="group"
              aria-label="Mode tampilan simulasi peta"
              class="inline-flex items-center rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-[#070b14] p-1 shrink-0 self-start sm:self-auto"
            >
              <button
                type="button"
                :aria-pressed="viewMode === '3d'"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                :class="
                  viewMode === '3d'
                    ? 'bg-[#17251F] text-white dark:bg-blue-600 dark:text-white shadow-2xs'
                    : 'text-[#647067] dark:text-slate-400 hover:text-[#17251F] dark:hover:text-white'
                "
                @click="viewMode = '3d'"
              >
                <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" />
                <span>3D Exploded</span>
              </button>

              <button
                type="button"
                :aria-pressed="viewMode === '2d'"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                :class="
                  viewMode === '2d'
                    ? 'bg-[#17251F] text-white dark:bg-blue-600 dark:text-white shadow-2xs'
                    : 'text-[#647067] dark:text-slate-400 hover:text-[#17251F] dark:hover:text-white'
                "
                @click="viewMode = '2d'"
              >
                <UIcon name="i-lucide-square" class="size-3.5 shrink-0" />
                <span>2D Flat</span>
              </button>
            </div>
          </div>

          <!-- Kanvas Gelap (#0B1120) untuk Visualisasi 3D Isometric Planes (Full Height Mengisi Card Tumpang Susun) -->
          <div
            class="relative w-full flex-1 rounded-2xl bg-[#0B1120] border border-slate-800/90 overflow-hidden flex items-center justify-center select-none transition-[min-height,height] duration-500 ease-out"
            :style="dynamicCanvasStyle"
          >
            <!-- Grid Latar & Pencahayaan Halus Kanvas -->
            <div
              aria-hidden="true"
              class="pointer-events-none absolute inset-0 opacity-35"
              style="
                background-image:
                  radial-gradient(circle at 50% 42%, rgba(59, 130, 246, 0.16), transparent 65%),
                  linear-gradient(to right, rgba(148, 163, 184, 0.06) 1px, transparent 1px),
                  linear-gradient(to bottom, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
                background-size: 100% 100%, 32px 32px, 32px 32px;
              "
            />

            <!-- Indikator Proyeksi & Sumber Data Riil di Sudut Atas -->
            <div
              aria-hidden="true"
              class="pointer-events-none absolute top-3.5 left-4 right-4 z-20 flex flex-wrap items-center justify-between gap-2 text-[11px] font-mono text-slate-400"
            >
              <div class="flex items-center gap-2">
                <span
                  class="inline-block size-2 rounded-full"
                  :class="!hasAnyActiveLayer ? 'bg-slate-600' : viewMode === '3d' ? 'bg-emerald-400' : 'bg-blue-400'"
                />
                <span v-if="!hasAnyActiveLayer">KANVAS KOSONG · LAYER &amp; BASEMAP NONAKTIF</span>
                <span v-else-if="viewMode === '3d'">PROYEKSI ISOMETRIK 60° · Z-STACK ORDERING</span>
                <span v-else>PROYEKSI DATAR 2D · OVERLAY PETA GABUNGAN</span>
              </div>

              <div class="hidden sm:flex items-center gap-2 text-[10px] text-slate-400 bg-slate-900/80 border border-white/10 rounded-md px-2 py-0.5">
                <span>BBOX: 111.42°BT - 112.16°BT · 7.46°LS - 6.98°LS</span>
              </div>
            </div>

            <!-- State Kosong Ketika Seluruh Lapisan & Basemap Topografi Nonaktif (Tepat di Tengah Kanvas) -->
            <div
              v-if="!hasAnyActiveLayer"
              class="absolute inset-x-0 top-10 bottom-12 z-30 flex items-center justify-center px-4"
            >
              <div class="w-full max-w-sm rounded-2xl border border-slate-700/80 bg-slate-900/90 backdrop-blur-md px-6 py-5 text-center shadow-2xl">
                <div class="mx-auto mb-2.5 size-10 rounded-xl bg-slate-800/90 border border-white/10 flex items-center justify-center text-slate-300">
                  <UIcon name="i-lucide-layers" class="size-5" />
                </div>
                <p class="text-sm font-bold text-white">
                  Tidak Ada Lapisan yang Aktif
                </p>
                <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                  Basemap topografi ikut disembunyikan. Aktifkan lapisan di panel kanan atau muat ulang lapisan bawaan.
                </p>
                <button
                  type="button"
                  class="mt-3.5 inline-flex items-center justify-center gap-2 rounded-xl bg-[#2563EB] hover:bg-blue-500 px-4 py-2 text-xs font-semibold text-white transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                  @click="activateDefaultLayers"
                >
                  <UIcon name="i-lucide-rotate-ccw" class="size-3.5" />
                  <span>Tampilkan Lapisan Default</span>
                </button>
              </div>
            </div>

            <!-- Panggung 3D Isometrik -->
            <div
              class="relative w-full h-full flex items-center justify-center overflow-hidden py-10 transition-opacity duration-300"
              :class="!hasAnyActiveLayer ? 'opacity-0 pointer-events-none' : 'opacity-100'"
            >
              <div
                class="iso-stage relative w-[295px] h-[196px] sm:w-[395px] sm:h-[258px] md:w-[430px] md:h-[275px]"
                :style="isoStageDynamicStyle"
              >
                <!-- 0. BIDANG PALING BAWAH: Basemap Topografi Abu-abu Gelap (Otomatis hilang saat seluruh layer nonaktif) -->
                <div
                  class="iso-plane absolute inset-0 rounded-2xl border border-slate-700/80 bg-[#111827]/95 shadow-2xl overflow-hidden"
                  :style="basemapPlaneStyle"
                  :aria-hidden="!hasAnyActiveLayer"
                >
                  <svg
                    viewBox="0 0 500 320"
                    class="w-full h-full rounded-2xl"
                    role="img"
                    aria-label="Peta dasar topografi Kabupaten Bojonegoro"
                  >
                    <rect width="500" height="320" rx="14" fill="#0f172a" />

                    <!-- Garis Grid Geografis WGS 84 Basemap -->
                    <g stroke="rgba(148, 163, 184, 0.09)" stroke-width="1" stroke-dasharray="4 4">
                      <line x1="0" y1="80" x2="500" y2="80" />
                      <line x1="0" y1="160" x2="500" y2="160" />
                      <line x1="0" y1="240" x2="500" y2="240" />
                      <line x1="125" y1="0" x2="125" y2="320" />
                      <line x1="250" y1="0" x2="250" y2="320" />
                      <line x1="375" y1="0" x2="375" y2="320" />
                    </g>

                    <!-- Kontur Topografi Zona Rembang (Utara) & Zona Kendeng (Selatan) -->
                    <g fill="none" stroke="rgba(148, 163, 184, 0.14)" stroke-width="1.2">
                      <path d="M 20,260 Q 120,220 220,255 T 460,235" />
                      <path d="M 35,285 Q 140,248 240,275 T 475,265" />
                      <path d="M 45,65 Q 170,35 310,70 T 470,50" />
                      <ellipse cx="145" cy="235" rx="58" ry="24" />
                      <ellipse cx="345" cy="225" rx="64" ry="26" />
                    </g>

                    <!-- Poligon Siluet Wilayah Kabupaten Bojonegoro (Proyeksi Koordinat Riil) -->
                    <polygon
                      :points="kabupatenPolygonPoints"
                      fill="rgba(30, 41, 59, 0.78)"
                      stroke="rgba(148, 163, 184, 0.38)"
                      stroke-width="1.6"
                    />

                    <!-- Titik Pusat 28 Kecamatan pada Basemap -->
                    <g fill="rgba(148, 163, 184, 0.35)">
                      <circle
                        v-for="kec in projectedKecamatanRects"
                        :key="`base-kec-${kec.name}`"
                        :cx="kec.cx"
                        :cy="kec.cy"
                        r="1.8"
                      />
                    </g>

                    <text
                      x="22"
                      y="302"
                      fill="#64748b"
                      font-size="10.5"
                      font-family="monospace"
                      font-weight="600"
                    >
                      BASEMAP TOPOGRAFI · EPSG:4326 BOJONEGORO
                    </text>
                  </svg>
                </div>

                <!-- 1..5. BIDANG LAYER TEMATIK MELAYANG (GLASSMORPHISM PLANES DENGAN DATA RIIL & DYNAMIC Z-ORDER) -->
                <div
                  v-for="layer in domStablePlanes"
                  :key="layer.id"
                  class="iso-plane absolute inset-0 rounded-2xl border backdrop-blur-[2px]"
                  :style="getPlaneStyle(layer)"
                  @mouseenter="hoveredLayerId = layer.id"
                  @mouseleave="hoveredLayerId = null"
                >
                  <!-- Label Sudut Bidang Layer, Posisi Z-Order, & Protokol pada Mode 3D -->
                  <div
                    v-if="viewMode === '3d'"
                    class="absolute -top-2.5 left-3 z-20 flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[9px] font-mono font-bold tracking-wider uppercase text-white shadow-xs"
                    :style="{ backgroundColor: layer.color }"
                  >
                    <span v-if="layer.activeOrderFromTop !== null" class="px-1 rounded bg-black/25">
                      #{{ layer.activeOrderFromTop }}
                    </span>
                    <span>{{ layer.name }}</span>
                    <span class="opacity-80 text-[8px]">· {{ layer.protocolBadge }}</span>
                  </div>

                  <!-- A. Render Tile WMS Geoportal Bojonegoro Asli (GetMap BBOX Bojonegoro) -->
                  <img
                    v-if="
                      (layer.id === 'layer-sungai' ||
                        layer.id === 'layer-tataruang' ||
                        layer.id === 'layer-pertanian') &&
                      !wmsErrorMap[layer.id]
                    "
                    :src="layer.wmsGetMapUrl"
                    :alt="`Lapisan WMS ${layer.name}`"
                    crossorigin="anonymous"
                    loading="lazy"
                    class="pointer-events-none absolute inset-0 w-full h-full object-fill rounded-2xl opacity-85 mix-blend-screen"
                    @load="wmsLoadedMap[layer.id] = true"
                    @error="wmsErrorMap[layer.id] = true"
                  />

                  <!-- B. Render Kanvas OpenLayers Martin Vector Tile (MVT) Asli -->
                  <div
                    v-if="layer.id === 'layer-jalan' || layer.id === 'layer-tataruang'"
                    :ref="(el) => setMvtPlaneRef(layer.id, el)"
                    class="pointer-events-none absolute inset-0 w-full h-full rounded-2xl overflow-hidden opacity-95"
                  />

                  <!-- C. Representasi Vektor Geografis Presisi WGS 84 (Menjamin Visualisasi Tajam di Atas Tile) -->
                  <svg
                    viewBox="0 0 500 320"
                    class="relative z-10 w-full h-full rounded-2xl overflow-visible"
                    role="img"
                    :aria-label="layer.name"
                  >
                    <!-- 1. Layer: Sungai & Bengawan Solo (#3B82F6 - Koordinat WGS 84 DAS Bengawan Solo) -->
                    <g v-if="layer.id === 'layer-sungai'">
                      <path
                        :d="bengawanSoloSvgPath"
                        fill="none"
                        stroke="rgba(59, 130, 246, 0.28)"
                        stroke-width="13"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                      <path
                        :d="bengawanSoloSvgPath"
                        fill="none"
                        stroke="#3B82F6"
                        stroke-width="4.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                      <path
                        v-for="(tribPath, tIdx) in tributariesSvgPaths"
                        :key="`trib-${tIdx}`"
                        :d="tribPath"
                        fill="none"
                        stroke="#60A5FA"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-dasharray="4 3"
                      />
                      <text
                        x="310"
                        y="64"
                        fill="#93C5FD"
                        font-size="10.5"
                        font-family="monospace"
                        font-weight="bold"
                      >
                        DAS Bengawan Solo ({{ layer.wmsLayerName }})
                      </text>
                    </g>

                    <!-- 2. Layer: Batas Administrasi & Kawasan (#8B5CF6 - BBOX 28 Kecamatan + Martin MVT) -->
                    <g v-if="layer.id === 'layer-tataruang'">
                      <polygon
                        :points="kabupatenPolygonPoints"
                        fill="rgba(139, 92, 246, 0.14)"
                        stroke="#8B5CF6"
                        stroke-width="2.4"
                        stroke-dasharray="7 4"
                      />
                      <rect
                        v-for="kec in projectedKecamatanRects"
                        :key="`kec-bbox-${kec.name}`"
                        :x="kec.x"
                        :y="kec.y"
                        :width="kec.w"
                        :height="kec.h"
                        rx="2"
                        fill="rgba(139, 92, 246, 0.04)"
                        stroke="#A78BFA"
                        stroke-width="0.9"
                        stroke-dasharray="3 2"
                        opacity="0.75"
                      />
                      <text
                        x="152"
                        y="48"
                        fill="#DDD6FE"
                        font-size="10"
                        font-family="monospace"
                        font-weight="bold"
                      >
                        MVT bataswilayah_kabupaten · 28 Kecamatan
                      </text>
                    </g>

                    <!-- 3. Layer: Kawasan Lahan Pertanian (#F59E0B - Zona LP2B & Sawah Irigasi) -->
                    <g v-if="layer.id === 'layer-pertanian'">
                      <polygon
                        v-for="(zone, zIdx) in agricultureZonePolygons"
                        :key="`agri-zone-${zIdx}`"
                        :points="zone.points"
                        fill="rgba(245, 158, 11, 0.24)"
                        stroke="#F59E0B"
                        stroke-width="2"
                      />
                      <text
                        x="168"
                        y="116"
                        fill="#FDE68A"
                        font-size="10.5"
                        font-family="monospace"
                        font-weight="bold"
                      >
                        WMS Zona Pertanian &amp; LP2B ({{ layer.wmsLayerName }})
                      </text>
                    </g>

                    <!-- 4. Layer: Jaringan Jalan Poros Desa (#10B981 - Martin MVT jalan_porosdesa + Koridor Riil) -->
                    <g v-if="layer.id === 'layer-jalan'">
                      <path
                        v-for="(corridorPath, cIdx) in roadCorridorSvgPaths"
                        :key="`halo-road-${cIdx}`"
                        :d="corridorPath"
                        fill="none"
                        stroke="rgba(16, 185, 129, 0.24)"
                        stroke-width="7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                      <path
                        v-for="(corridorPath, cIdx) in roadCorridorSvgPaths"
                        :key="`line-road-${cIdx}`"
                        :d="corridorPath"
                        fill="none"
                        stroke="#10B981"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                      <circle
                        v-for="kec in projectedKecamatanRects"
                        :key="`road-node-${kec.name}`"
                        :cx="kec.cx"
                        :cy="kec.cy"
                        r="2.4"
                        fill="#34D399"
                      />
                      <text
                        x="215"
                        y="108"
                        fill="#A7F3D0"
                        font-size="10"
                        font-family="monospace"
                        font-weight="bold"
                      >
                        MVT /martin/jalan_porosdesa
                      </text>
                    </g>

                    <!-- 5. Layer: Titik Situs Geopark (#EC4899 - 31 Situs Asli useGeoparkSites) -->
                    <g v-if="layer.id === 'layer-geopark'">
                      <circle
                        v-for="site in projectedGeoparkSites"
                        :key="`geosite-dot-${site.id}`"
                        :cx="site.x"
                        :cy="site.y"
                        :r="site.isFeatured ? 5.5 : 3.2"
                        :fill="site.isFeatured ? '#EC4899' : '#F472B6'"
                        stroke="#FFFFFF"
                        :stroke-width="site.isFeatured ? 2 : 1"
                        :opacity="site.isFeatured ? 1 : 0.85"
                      />

                      <g
                        v-for="feat in featuredGeoparkSites"
                        :key="`featured-geo-${feat.id}`"
                      >
                        <line
                          v-if="viewMode === '3d'"
                          :x1="feat.x"
                          :y1="feat.y"
                          :x2="feat.x"
                          :y2="feat.y + 55"
                          stroke="#EC4899"
                          stroke-width="1.4"
                          stroke-dasharray="3 3"
                          opacity="0.8"
                        />
                        <circle
                          :cx="feat.x"
                          :cy="feat.y"
                          r="11"
                          fill="rgba(236, 72, 153, 0.26)"
                        />
                        <rect
                          :x="feat.x + 8"
                          :y="feat.y - 12"
                          :width="feat.shortLabel.length * 6.5 + 16"
                          height="20"
                          rx="5"
                          fill="rgba(15, 23, 42, 0.9)"
                          stroke="#EC4899"
                          stroke-width="1.2"
                        />
                        <text
                          :x="feat.x + 15"
                          :y="feat.y + 2"
                          fill="#FBCFE8"
                          font-size="10"
                          font-weight="bold"
                        >
                          {{ feat.shortLabel }}
                        </text>
                      </g>
                    </g>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Bar Informasi Layer Aktif & Urutan Tumpukan di Bagian Bawah Kanvas Gelap -->
            <div class="absolute bottom-3 left-3 right-3 z-20 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-white/10 bg-slate-950/85 backdrop-blur-md px-3.5 py-2 text-xs text-slate-300">
              <div class="flex items-center gap-2 min-w-0 flex-1">
                <div v-if="hasAnyActiveLayer" class="flex items-center -space-x-1 shrink-0">
                  <span
                    v-for="l in activeLayers"
                    :key="`dot-${l.id}`"
                    class="inline-block size-3 rounded-full ring-2 ring-slate-950"
                    :style="{ backgroundColor: l.color }"
                  />
                </div>
                <span class="truncate">
                  <template v-if="hasAnyActiveLayer">
                    Urutan Atas ke Bawah: {{ activeLayers.map((l, idx) => `${idx + 1}. ${l.name}`).join(' · ') }}
                  </template>
                  <template v-else>
                    Semua lapisan tematik dan basemap topografi dinonaktifkan
                  </template>
                </span>
              </div>

              <NuxtLink
                to="/maps?tab=dataset"
                class="inline-flex items-center gap-1 text-blue-400 hover:text-blue-300 hover:underline font-semibold shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 rounded px-1"
              >
                <span>Buka di WebGIS</span>
                <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
              </NuxtLink>
            </div>
          </div>

          <p class="text-xs text-[#647067] dark:text-slate-400">
            *Gunakan tombol panah naik/turun atau seret kartu pada panel kanan untuk menyimulasikan perubahan urutan lapisan (layer ordering) pada sumbu vertikal Z.
          </p>
        </div>

        <!-- Kolom Kanan (Panel Kontrol & Ordering Lapisan Tematik) -->
        <div class="lg:col-span-5 flex flex-col">
          <div class="h-full flex flex-col justify-between rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] p-5 shadow-xs">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h3 class="text-base font-bold text-[#17251F] dark:text-white">
                  Pilih &amp; Urutkan Lapisan Data Tematik
                </h3>
                <p class="mt-0.5 text-xs text-[#647067] dark:text-slate-400">
                  Urutan kartu dari atas ke bawah menentukan posisi ketinggian layer (Atas -> Bawah):
                </p>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  title="Balik urutan tumpukan layer (Atas ke Bawah)"
                  class="inline-flex items-center gap-1 rounded-lg border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-white/5 hover:border-[#2563EB] px-2.5 py-2 text-xs font-semibold text-[#17251F] dark:text-slate-200 transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  @click="reverseLayerOrder"
                >
                  <UIcon name="i-lucide-arrow-up-down" class="size-3.5 text-[#2563EB] dark:text-blue-400" />
                  <span>Balik Urutan</span>
                </button>

                <button
                  type="button"
                  class="rounded-lg border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-white/5 hover:border-[#2563EB] px-3 py-2 text-xs font-semibold text-[#17251F] dark:text-slate-200 transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  @click="toggleAllLayers"
                >
                  {{ allLayersActive ? 'Matikan Semua' : 'Aktifkan Semua' }}
                </button>
              </div>
            </div>

            <!-- Bar Info Simulasi Layer Ordering -->
            <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.04] border border-[#DFE5DE]/80 dark:border-white/10 px-3 py-2 text-[11px] text-[#647067] dark:text-slate-400">
              <span class="font-medium">
                Seret kartu atau klik panah <strong class="text-[#17251F] dark:text-white">↑ / ↓</strong> untuk mengubah Z-Order
              </span>
              <button
                type="button"
                class="text-[#2563EB] dark:text-blue-400 hover:underline font-semibold cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] rounded px-1"
                @click="resetLayerOrder"
              >
                Reset Urutan
              </button>
            </div>

            <div class="mt-3.5 space-y-2.5" role="group" aria-label="Daftar dan urutan lapisan data tematik">
              <div
                v-for="(item, index) in resolvedLayers"
                :key="item.id"
                draggable="true"
                class="group relative rounded-xl border p-3.5 transition-all"
                :class="[
                  item.active
                    ? 'border-[#2563EB] dark:border-blue-500/80 bg-blue-50/40 dark:bg-blue-950/25 shadow-2xs'
                    : 'border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5]/70 dark:bg-white/[0.03] opacity-75 hover:opacity-100',
                  dragOverLayerIndex === index ? 'ring-2 ring-[#2563EB] scale-[1.01]' : '',
                ]"
                @mouseenter="hoveredLayerId = item.id"
                @mouseleave="hoveredLayerId = null"
                @dragstart="onDragStart(index, $event)"
                @dragover="onDragOver(index, $event)"
                @drop="onDrop(index, $event)"
                @dragend="onDragEnd"
              >
                <div class="flex items-start justify-between gap-2">
                  <!-- Tombol Utama Toggle Aktif/Nonaktif Layer -->
                  <button
                    type="button"
                    role="switch"
                    :aria-checked="item.active"
                    class="flex-1 text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] rounded-lg min-w-0"
                    @click="toggleLayer(item.id)"
                    @focus="hoveredLayerId = item.id"
                    @blur="hoveredLayerId = null"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <UIcon
                        name="i-lucide-grip-vertical"
                        class="size-4 text-slate-400 dark:text-slate-500 shrink-0 cursor-grab active:cursor-grabbing"
                        aria-hidden="true"
                      />
                      <span
                        class="size-4 rounded-md flex items-center justify-center text-white text-[10px] font-bold shrink-0 transition-transform"
                        :class="hoveredLayerId === item.id ? 'scale-110' : ''"
                        :style="{
                          backgroundColor: item.color,
                          boxShadow: item.active ? `0 0 10px ${item.glowRgba}` : 'none',
                        }"
                      >
                        <span v-if="item.active">✓</span>
                      </span>
                      <span class="text-sm font-bold text-[#17251F] dark:text-white truncate">
                        {{ item.name }}
                      </span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs text-[#647067] dark:text-slate-400">
                      <span class="font-medium truncate">{{ item.agency }}</span>
                      <span
                        class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-200/70 dark:bg-white/10"
                        :style="{ color: item.active ? item.color : undefined }"
                      >
                        {{ item.protocolBadge }}
                      </span>
                    </div>

                    <p class="mt-1.5 text-xs text-[#647067] dark:text-slate-300 leading-relaxed">
                      {{ item.desc }}
                    </p>
                  </button>

                  <!-- Kontrol Kanan: Badge Status + Tombol Ordering Naik/Turun -->
                  <div class="flex flex-col items-end gap-1.5 shrink-0">
                    <div class="flex items-center gap-1.5">
                      <span
                        class="font-mono text-[10px] font-bold px-2 py-0.5 rounded-md border"
                        :class="
                          item.active
                            ? 'border-blue-300 dark:border-blue-700 bg-blue-100/70 dark:bg-blue-900/40 text-[#2563EB] dark:text-blue-300'
                            : 'border-slate-300 dark:border-white/10 bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400'
                        "
                      >
                        {{ index === 0 ? `Urutan #1 (Atas)` : index === resolvedLayers.length - 1 ? `Urutan #${index + 1} (Bawah)` : `Urutan #${index + 1}` }}
                      </span>

                      <button
                        type="button"
                        class="text-[11px] font-semibold px-2.5 py-0.5 rounded-md transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                        :class="
                          item.active
                            ? 'bg-[#2563EB] text-white'
                            : 'bg-slate-200 dark:bg-white/10 text-[#647067] dark:text-slate-300'
                        "
                        @click="toggleLayer(item.id)"
                      >
                        {{ item.active ? 'Aktif' : 'Nonaktif' }}
                      </button>
                    </div>

                    <!-- Tombol Geser Urutan Layer (Move Up / Move Down) -->
                    <div class="inline-flex items-center gap-1 rounded-lg border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#070b14] p-0.5">
                      <button
                        type="button"
                        :disabled="index === 0"
                        :aria-label="`Naikkan urutan ${item.name}`"
                        title="Naikkan ke lapisan lebih atas (Z+)"
                        class="inline-flex items-center justify-center min-w-[38px] min-h-[38px] rounded-md text-xs font-bold transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed hover:bg-blue-50 dark:hover:bg-white/10 text-[#17251F] dark:text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                        @click.stop="moveLayerOrder(index, -1)"
                      >
                        <UIcon name="i-lucide-chevron-up" class="size-4" />
                      </button>
                      <button
                        type="button"
                        :disabled="index === resolvedLayers.length - 1"
                        :aria-label="`Turunkan urutan ${item.name}`"
                        title="Turunkan ke lapisan lebih bawah (Z-)"
                        class="inline-flex items-center justify-center min-w-[38px] min-h-[38px] rounded-md text-xs font-bold transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed hover:bg-blue-50 dark:hover:bg-white/10 text-[#17251F] dark:text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                        @click.stop="moveLayerOrder(index, 1)"
                      >
                        <UIcon name="i-lucide-chevron-down" class="size-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Baris Endpoint Teknis Layer (Martin MVT / GeoServer WMS) -->
                <div class="mt-2 flex items-center justify-between gap-2 pt-1.5 border-t border-[#DFE5DE]/70 dark:border-white/10 text-[11px] font-mono text-[#647067] dark:text-slate-400">
                  <span class="truncate">{{ item.technicalSourceLabel }}</span>
                  <span class="shrink-0">
                    <template v-if="item.active && item.activeOrderFromTop !== null">
                      Z-Level {{ item.stackElevationIndex + 1 }} · {{ item.type }}
                    </template>
                    <template v-else>
                      {{ item.type }}
                    </template>
                  </span>
                </div>
              </div>
            </div>

            <div class="mt-5 pt-4 border-t border-[#DFE5DE] dark:border-white/10">
              <NuxtLink
                to="/dataset"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-white/5 hover:border-[#2563EB] hover:text-[#2563EB] dark:hover:border-blue-400 dark:hover:text-blue-400 py-2.5 px-4 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-white transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
              >
                <UIcon name="i-lucide-layers" class="size-4 text-[#2563EB] dark:text-blue-400" />
                <span>
                  Lihat Seluruh Katalog Geoportal JIGN
                  <template v-if="geoportalCatalog.length > 0">
                    ({{ geoportalCatalog.length }} Layer)
                  </template>
                </span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Transformasi 3D Isometrik (rotateX 60deg, rotateZ -45deg) dengan transisi pegas (spring physics) */
.iso-stage {
  transform-style: preserve-3d;
  transition: transform 650ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.iso-plane {
  transform-style: preserve-3d;
  backface-visibility: hidden;
  will-change: transform, opacity, box-shadow;
  transition:
    transform 580ms cubic-bezier(0.34, 1.56, 0.64, 1),
    opacity 360ms cubic-bezier(0.16, 1, 0.3, 1),
    box-shadow 320ms ease,
    border-color 280ms ease,
    background-color 280ms ease;
}

@media (prefers-reduced-motion: reduce) {
  .iso-stage,
  .iso-plane {
    transition-duration: 0.01ms !important;
  }
}
</style>

<script setup lang="ts">
import 'ol/ol.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { parseCoordinateString } from '~/utils/gis-helpers';
import { KECAMATAN_ENTRIES, type KecamatanEntry } from '~/constants/kecamatan';
import type { GeoparkSite } from '~/composables/useGeoparkSites';

const props = defineProps<{
  geoparkSites: GeoparkSite[];
}>();

const emit = defineEmits<{
  (e: 'selectKecamatan', kec: KecamatanEntry): void;
}>();

const config = useRuntimeConfig();
const martinBase = (config.public?.martinUrl as string) || '/martin';

const heroSearchQuery = ref('');
const debouncedSearchQuery = ref('');
const isSearchFocused = ref(false);
const highlightedIndex = ref(-1);
const searchContainerRef = ref<HTMLElement | null>(null);
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const activeInspectorKecamatan = ref<KecamatanEntry>(
  KECAMATAN_ENTRIES.find((k) => k.name === 'Bojonegoro') || KECAMATAN_ENTRIES[0]!
);

const parsedHeroCoordinate = computed(() => {
  const q = debouncedSearchQuery.value.trim() || heroSearchQuery.value.trim();
  if (!q) return null;
  return parseCoordinateString(q);
});

interface HeroSuggestion {
  id: string;
  type: 'koordinat' | 'kecamatan' | 'geosite';
  title: string;
  subtitle: string;
  badge: string;
  coordinate: [number, number];
  zoom: number;
  kecamatanEntry?: KecamatanEntry;
  geositeEntry?: GeoparkSite;
}

const heroSuggestions = computed<HeroSuggestion[]>(() => {
  const q = debouncedSearchQuery.value.trim().toLowerCase();
  if (!q) return [];

  const list: HeroSuggestion[] = [];

  if (parsedHeroCoordinate.value) {
    list.push({
      id: 'coord-match',
      type: 'koordinat',
      title: `Titik Koordinat ${parsedHeroCoordinate.value.formatted}`,
      subtitle: 'Buka langsung titik lokasi ini di peta interaktif',
      badge: 'Koordinat WGS 84',
      coordinate: [parsedHeroCoordinate.value.lng, parsedHeroCoordinate.value.lat],
      zoom: 16,
    });
  }

  const matchedKec = KECAMATAN_ENTRIES.filter(
    (k) =>
      k.name.toLowerCase().includes(q) ||
      k.desc.toLowerCase().includes(q) ||
      k.zona.toLowerCase().includes(q)
  ).slice(0, 4);

  for (const kec of matchedKec) {
    list.push({
      id: `kec-${kec.name}`,
      type: 'kecamatan',
      title: `Kecamatan ${kec.name}`,
      subtitle: `${kec.desc} · Wilayah ${kec.zona}`,
      badge: `Zona ${kec.zona}`,
      coordinate: kec.coordinate,
      zoom: kec.zoom,
      kecamatanEntry: kec,
    });
  }

  const matchedGeo = props.geoparkSites
    .filter(
      (s) =>
        s.name.toLowerCase().includes(q) ||
        s.code.toLowerCase().includes(q) ||
        s.desc.toLowerCase().includes(q)
    )
    .slice(0, 3);

  for (const site of matchedGeo) {
    list.push({
      id: `geo-${site.id}`,
      type: 'geosite',
      title: site.name,
      subtitle: site.desc,
      badge: site.category,
      coordinate: site.coordinate,
      zoom: site.zoom || 14,
      geositeEntry: site,
    });
  }

  return list;
});

watch(heroSearchQuery, (val) => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  const trimmed = val.trim();
  if (!trimmed) {
    debouncedSearchQuery.value = '';
    highlightedIndex.value = -1;
    return;
  }

  searchDebounceTimer = setTimeout(() => {
    debouncedSearchQuery.value = trimmed;
    highlightedIndex.value = heroSuggestions.value.length > 0 ? 0 : -1;

    const clean = trimmed.replace(/^kecamatan\s+/i, '').trim().toLowerCase();
    if (!clean) return;
    const found = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase().includes(clean));
    if (found && found.name !== activeInspectorKecamatan.value.name) {
      selectInspectorKecamatan(found);
    }
  }, 180);
});

function selectInspectorKecamatan(kec: KecamatanEntry) {
  activeInspectorKecamatan.value = kec;
  emit('selectKecamatan', kec);
  animateMapToKecamatan(kec);
}

function openKecamatanInMap(kec: KecamatanEntry) {
  navigateTo(
    `/maps/@${kec.coordinate[1].toFixed(5)},${kec.coordinate[0].toFixed(5)},${kec.zoom}z?kecamatan=${encodeURIComponent(kec.name)}&search=${encodeURIComponent('Kecamatan ' + kec.name)}`
  );
}

function handleSelectHeroSuggestion(item: HeroSuggestion) {
  isSearchFocused.value = false;
  highlightedIndex.value = -1;

  if (item.type === 'koordinat') {
    navigateTo(
      `/maps/@${item.coordinate[1].toFixed(5)},${item.coordinate[0].toFixed(5)},${item.zoom}z?search=${encodeURIComponent(item.title)}&pin=1`
    );
    return;
  }
  if (item.type === 'kecamatan' && item.kecamatanEntry) {
    selectInspectorKecamatan(item.kecamatanEntry);
    openKecamatanInMap(item.kecamatanEntry);
    return;
  }
  if (item.type === 'geosite' && item.geositeEntry) {
    if (item.geositeEntry.hasCoordinate) {
      navigateTo(`/maps?geosite=${encodeURIComponent(item.geositeEntry.slug)}`);
    } else {
      navigateTo(`/geopark/${item.geositeEntry.slug}`);
    }
  }
}

function onSearchKeydown(e: KeyboardEvent) {
  if (!isSearchFocused.value || heroSuggestions.value.length === 0) {
    if (e.key === 'Escape') isSearchFocused.value = false;
    return;
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlightedIndex.value = (highlightedIndex.value + 1) % heroSuggestions.value.length;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightedIndex.value =
      (highlightedIndex.value - 1 + heroSuggestions.value.length) % heroSuggestions.value.length;
  } else if (e.key === 'Enter' && highlightedIndex.value >= 0) {
    const selected = heroSuggestions.value[highlightedIndex.value];
    if (selected) {
      e.preventDefault();
      handleSelectHeroSuggestion(selected);
    }
  } else if (e.key === 'Escape') {
    isSearchFocused.value = false;
  }
}

function onHeroSearchSubmit() {
  const q = heroSearchQuery.value.trim();
  if (!q) {
    openKecamatanInMap(activeInspectorKecamatan.value);
    return;
  }

  const coord = parseCoordinateString(q);
  if (coord) {
    navigateTo(
      `/maps/@${coord.lat.toFixed(5)},${coord.lng.toFixed(5)},16z?search=${encodeURIComponent(coord.formatted)}&pin=1`
    );
    return;
  }

  const cleanQ = q.replace(/^kecamatan\s+/i, '').replace(/\s*\(kota\)$/i, '').trim().toLowerCase();
  const matchedKec = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === cleanQ);
  if (matchedKec) {
    openKecamatanInMap(matchedKec);
    return;
  }

  if (heroSuggestions.value.length > 0 && heroSuggestions.value[0]) {
    handleSelectHeroSuggestion(heroSuggestions.value[0]);
    return;
  }

  navigateTo(`/maps?search=${encodeURIComponent(q)}`);
}

function clearHeroSearch() {
  heroSearchQuery.value = '';
  debouncedSearchQuery.value = '';
  highlightedIndex.value = -1;
}

function formatCoordPair(coord: [number, number]): string {
  const [lng, lat] = coord;
  const latDir = lat < 0 ? 'LS' : 'LU';
  const lngDir = lng >= 0 ? 'BT' : 'BB';
  return `${Math.abs(lat).toFixed(4)}° ${latDir}, ${Math.abs(lng).toFixed(4)}° ${lngDir}`;
}

const heroMapContainer = ref<HTMLElement | null>(null);
const isMapLoading = ref(true);
const isMapFallback = ref(false);
const isUsingVectorFallbackTiles = ref(false);
const liveCursorCoords = ref<[number, number] | null>(null);

let mapInstance: any = null;
let olModules: any = null;
let overlayVectorSource: any = null;
let overlayVectorLayer: any = null;
let roadMvtLayer: any = null;
let segmenMvtLayer: any = null;
let resizeObserver: ResizeObserver | null = null;

const BOJONEGORO_BBOX: [number, number, number, number] = [
  111.4235, -7.4696, 112.1657, -6.9863,
];

const displayCoordinates = computed(() => {
  if (liveCursorCoords.value) {
    return formatCoordPair(liveCursorCoords.value);
  }
  return formatCoordPair(activeInspectorKecamatan.value.coordinate);
});

// Proyeksi koordinat WGS 84 ke ruang kanvas SVG (480x260) agar fallback mencerminkan posisi geografis sebenarnya
function projectToFallbackSvg(coord: [number, number]): { x: number; y: number } {
  const [minLng, minLat, maxLng, maxLat] = BOJONEGORO_BBOX;
  const padX = 28;
  const padY = 24;
  const width = 480 - padX * 2;
  const height = 260 - padY * 2;
  const x = padX + ((coord[0] - minLng) / (maxLng - minLng)) * width;
  const y = padY + ((maxLat - coord[1]) / (maxLat - minLat)) * height;
  return {
    x: Number(x.toFixed(1)),
    y: Number(y.toFixed(1)),
  };
}

const fallbackActiveBox = computed(() => {
  const [minLng, minLat, maxLng, maxLat] = activeInspectorKecamatan.value.bbox;
  const topLeft = projectToFallbackSvg([minLng, maxLat]);
  const bottomRight = projectToFallbackSvg([maxLng, minLat]);
  return {
    x: topLeft.x,
    y: topLeft.y,
    width: Math.max(22, Number((bottomRight.x - topLeft.x).toFixed(1))),
    height: Math.max(18, Number((bottomRight.y - topLeft.y).toFixed(1))),
  };
});

const fallbackDistrictNodes = computed(() =>
  KECAMATAN_ENTRIES.map((k) => ({
    entry: k,
    ...projectToFallbackSvg(k.coordinate),
    isActive: k.name === activeInspectorKecamatan.value.name,
  }))
);

const CORRIDOR_CHAINS: string[][] = [
  ['Margomulyo', 'Ngraho', 'Padangan', 'Purwosari', 'Gayam', 'Kalitidu', 'Trucuk', 'Bojonegoro', 'Kapas', 'Balen', 'Sumberrejo', 'Kanor', 'Baureno'],
  ['Kedewan', 'Kasiman', 'Padangan', 'Tambakrejo', 'Ngambon', 'Ngasem', 'Dander', 'Temayang', 'Gondang', 'Sekar'],
  ['Malo', 'Kalitidu', 'Ngasem', 'Bubulan'],
  ['Bojonegoro', 'Dander', 'Sukosewu', 'Sugihwaras', 'Kedungadem', 'Kepohbaru', 'Baureno'],
];

const fallbackCorridorPaths = computed(() => {
  return CORRIDOR_CHAINS.map((chain) => {
    const pts = chain
      .map((name) => KECAMATAN_ENTRIES.find((k) => k.name === name))
      .filter((k): k is KecamatanEntry => Boolean(k))
      .map((k) => projectToFallbackSvg(k.coordinate));
    return pts.map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${p.x},${p.y}`).join(' ');
  });
});

function buildOverlayFeatures() {
  if (!olModules || !overlayVectorSource) return;
  const { Feature, Point, Polygon, LineString, fromLonLat, Style, Stroke, Fill, Circle, Text } = olModules;
  const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');

  overlayVectorSource.clear();

  for (const chain of CORRIDOR_CHAINS) {
    const coords = chain
      .map((name) => KECAMATAN_ENTRIES.find((k) => k.name === name)?.coordinate)
      .filter((c): c is [number, number] => Boolean(c))
      .map((c) => fromLonLat(c));

    if (coords.length >= 2) {
      const corridorFeature = new Feature({
        geometry: new LineString(coords),
        featureRole: 'corridor',
      });
      corridorFeature.setStyle(
        new Style({
          stroke: new Stroke({
            color: isDark ? 'rgba(52, 211, 153, 0.32)' : 'rgba(23, 107, 77, 0.28)',
            width: 2.25,
            lineCap: 'round',
            lineJoin: 'round',
          }),
          zIndex: 5,
        })
      );
      overlayVectorSource.addFeature(corridorFeature);
    }
  }

  const active = activeInspectorKecamatan.value;
  const [minLng, minLat, maxLng, maxLat] = active.bbox;
  const ring = [
    fromLonLat([minLng, minLat]),
    fromLonLat([maxLng, minLat]),
    fromLonLat([maxLng, maxLat]),
    fromLonLat([minLng, maxLat]),
    fromLonLat([minLng, minLat]),
  ];
  const bboxFeature = new Feature({
    geometry: new Polygon([ring]),
    featureRole: 'active-bbox',
  });
  bboxFeature.setStyle([
    new Style({
      stroke: new Stroke({
        color: isDark ? 'rgba(7, 11, 20, 0.85)' : 'rgba(255, 255, 255, 0.85)',
        width: 5,
      }),
      fill: new Fill({
        color: isDark ? 'rgba(37, 99, 235, 0.12)' : 'rgba(37, 99, 235, 0.06)',
      }),
      zIndex: 15,
    }),
    new Style({
      stroke: new Stroke({
        color: '#2563EB',
        width: 2,
        lineDash: [7, 5],
      }),
      zIndex: 16,
    }),
  ]);
  overlayVectorSource.addFeature(bboxFeature);

  const geoSites = (props.geoparkSites || []).filter((s) => s.hasCoordinate).slice(0, 20);
  for (const site of geoSites) {
    const geoFeat = new Feature({
      geometry: new Point(fromLonLat(site.coordinate)),
      featureRole: 'geosite',
      siteData: site,
    });
    geoFeat.setStyle(
      new Style({
        image: new Circle({
          radius: 4.5,
          fill: new Fill({ color: '#176B4D' }),
          stroke: new Stroke({ color: '#FFFFFF', width: 1.5 }),
        }),
        zIndex: 20,
      })
    );
    overlayVectorSource.addFeature(geoFeat);
  }

  for (const kec of KECAMATAN_ENTRIES) {
    const isSelected = kec.name === active.name;
    const kecFeat = new Feature({
      geometry: new Point(fromLonLat(kec.coordinate)),
      featureRole: 'kecamatan',
      kecamatanData: kec,
    });

    if (isSelected) {
      kecFeat.setStyle([
        new Style({
          image: new Circle({
            radius: 13,
            fill: new Fill({ color: 'rgba(37, 99, 235, 0.18)' }),
            stroke: new Stroke({ color: 'rgba(37, 99, 235, 0.45)', width: 1.5 }),
          }),
          zIndex: 30,
        }),
        new Style({
          image: new Circle({
            radius: 6.5,
            fill: new Fill({ color: '#2563EB' }),
            stroke: new Stroke({ color: '#FFFFFF', width: 2.25 }),
          }),
          text: new Text({
            text: `Kec. ${kec.name}`,
            offsetY: -18,
            font: '700 11.5px "Plus Jakarta Sans", system-ui, sans-serif',
            fill: new Fill({ color: isDark ? '#F8FAFC' : '#17251F' }),
            stroke: new Stroke({
              color: isDark ? 'rgba(12, 21, 34, 0.95)' : 'rgba(255, 255, 255, 0.95)',
              width: 3.5,
            }),
          }),
          zIndex: 31,
        }),
      ]);
    } else {
      kecFeat.setStyle(
        new Style({
          image: new Circle({
            radius: 4,
            fill: new Fill({ color: isDark ? '#64748B' : '#475569' }),
            stroke: new Stroke({ color: isDark ? '#0F172A' : '#FFFFFF', width: 1.25 }),
          }),
          zIndex: 18,
        })
      );
    }

    overlayVectorSource.addFeature(kecFeat);
  }
}

function animateMapToKecamatan(kec: KecamatanEntry) {
  buildOverlayFeatures();
  if (roadMvtLayer) roadMvtLayer.changed();
  if (segmenMvtLayer) segmenMvtLayer.changed();

  if (!mapInstance || !olModules) return;
  const view = mapInstance.getView();
  if (!view) return;

  view.animate({
    center: olModules.fromLonLat(kec.coordinate),
    zoom: Math.max(kec.zoom - 0.6, 11.8),
    duration: 450,
  });
}

function handleHeroMapZoom(delta: number) {
  if (!mapInstance) return;
  const view = mapInstance.getView();
  if (!view) return;
  const currentZoom = view.getZoom() || 12;
  view.animate({
    zoom: Math.min(Math.max(currentZoom + delta, 9), 18),
    duration: 220,
  });
}

function handleResetKabupatenView() {
  if (!mapInstance || !olModules) return;
  const extent3857 = olModules.transformExtent(BOJONEGORO_BBOX, 'EPSG:4326', 'EPSG:3857');
  mapInstance.getView().fit(extent3857, {
    padding: [28, 28, 28, 28],
    maxZoom: 11.2,
    duration: 450,
  });
}

async function initHeroMap() {
  if (!import.meta.client || !heroMapContainer.value) return;

  isMapLoading.value = true;
  isMapFallback.value = false;

  try {
    const [
      { default: Map },
      { default: View },
      { default: TileLayer },
      { default: VectorTileLayer },
      { default: VectorLayer },
      { default: XYZ },
      { default: OSM },
      { default: VectorTileSource },
      { default: VectorSource },
      { default: MVT },
      stylePkg,
      projPkg,
      { default: Feature },
      { default: Point },
      { default: Polygon },
      { default: LineString },
      { defaults: defaultInteractions },
    ] = await Promise.all([
      import('ol/Map.js'),
      import('ol/View.js'),
      import('ol/layer/Tile.js'),
      import('ol/layer/VectorTile.js'),
      import('ol/layer/Vector.js'),
      import('ol/source/XYZ.js'),
      import('ol/source/OSM.js'),
      import('ol/source/VectorTile.js'),
      import('ol/source/Vector.js'),
      import('ol/format/MVT.js'),
      import('ol/style.js'),
      import('ol/proj.js'),
      import('ol/Feature.js'),
      import('ol/geom/Point.js'),
      import('ol/geom/Polygon.js'),
      import('ol/geom/LineString.js'),
      import('ol/interaction.js'),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      VectorTileLayer,
      VectorLayer,
      XYZ,
      OSM,
      VectorTileSource,
      VectorSource,
      MVT,
      Style: stylePkg.Style,
      Stroke: stylePkg.Stroke,
      Fill: stylePkg.Fill,
      Circle: stylePkg.Circle,
      Text: stylePkg.Text,
      fromLonLat: projPkg.fromLonLat,
      toLonLat: projPkg.toLonLat,
      transformExtent: projPkg.transformExtent,
      Feature,
      Point,
      Polygon,
      LineString,
    };

    if (mapInstance) {
      mapInstance.setTarget(null);
      mapInstance = null;
    }

    let tileErrorCount = 0;
    const baseSource = new XYZ({
      url: 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
      maxZoom: 19,
      crossOrigin: 'anonymous',
    });

    const baseTileLayer = new TileLayer({
      source: baseSource,
      zIndex: 0,
    });

    baseSource.on('tileloadend', () => {
      isMapLoading.value = false;
      isUsingVectorFallbackTiles.value = false;
    });

    // Beralih ke OSM atau kanvas vektor dasar apabila tile eksternal tidak dapat dijangkau
    baseSource.on('tileloaderror', () => {
      tileErrorCount++;
      if (tileErrorCount === 3) {
        const osmFallback = new OSM({ crossOrigin: 'anonymous' });
        osmFallback.on('tileloaderror', () => {
          isUsingVectorFallbackTiles.value = true;
          isMapLoading.value = false;
        });
        osmFallback.on('tileloadend', () => {
          isUsingVectorFallbackTiles.value = false;
          isMapLoading.value = false;
        });
        baseTileLayer.setSource(osmFallback);
      } else if (tileErrorCount > 6) {
        isUsingVectorFallbackTiles.value = true;
        isMapLoading.value = false;
      }
    });

    const batasKabLayer = new VectorTileLayer({
      source: new VectorTileSource({
        format: new MVT({ idProperty: 'id' }),
        url: `${martinBase}/bataswilayah_kabupaten/{z}/{x}/{y}`,
        maxZoom: 18,
        minZoom: 6,
      }),
      zIndex: 4,
      style: () => {
        const isDark = document.documentElement.classList.contains('dark');
        return [
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: isDark ? 'rgba(148, 163, 184, 0.22)' : 'rgba(71, 85, 105, 0.18)',
              width: 5,
            }),
          }),
          new stylePkg.Style({
            stroke: new stylePkg.Stroke({
              color: isDark ? '#94A3B8' : '#475569',
              width: 1.8,
              lineDash: [10, 4, 2, 4],
            }),
          }),
        ];
      },
    });

    const createRoadMvtStyle = (isSegmenLayer: boolean) => {
      return (feature: any) => {
        const rawKec = String(
          feature.get('kecamatan') ||
            feature.get('WADMKC') ||
            feature.get('wadmkc') ||
            feature.get('nama_kecamatan') ||
            ''
        )
          .trim()
          .toLowerCase();

        const targetKec = activeInspectorKecamatan.value.name.toLowerCase();
        const isInSelectedKec = !rawKec || rawKec.includes(targetKec);

        const kondisi = String(feature.get('kondisi') || '').toLowerCase();
        let color = isSegmenLayer ? '#10B981' : '#2563EB';
        if (kondisi.includes('rusak berat')) color = '#EF4444';
        else if (kondisi.includes('rusak')) color = '#F97316';
        else if (kondisi.includes('sedang')) color = '#F59E0B';
        else if (kondisi.includes('baik')) color = '#10B981';

        return new stylePkg.Style({
          stroke: new stylePkg.Stroke({
            color,
            width: isInSelectedKec ? (isSegmenLayer ? 2.8 : 2.1) : 1.15,
            lineCap: 'round',
            lineJoin: 'round',
          }),
          zIndex: isInSelectedKec ? 12 : 8,
        });
      };
    };

    roadMvtLayer = new VectorTileLayer({
      source: new VectorTileSource({
        format: new MVT({ idProperty: 'id' }),
        url: `${martinBase}/jalan_porosdesa/{z}/{x}/{y}`,
        maxZoom: 19,
        minZoom: 8,
      }),
      zIndex: 10,
      opacity: 0.9,
      style: createRoadMvtStyle(false),
    });

    segmenMvtLayer = new VectorTileLayer({
      source: new VectorTileSource({
        format: new MVT({ idProperty: 'id' }),
        url: `${martinBase}/infrastruktur_segmen/{z}/{x}/{y}`,
        maxZoom: 19,
        minZoom: 8,
      }),
      zIndex: 12,
      opacity: 0.95,
      style: createRoadMvtStyle(true),
    });

    overlayVectorSource = new VectorSource();
    overlayVectorLayer = new VectorLayer({
      source: overlayVectorSource,
      zIndex: 25,
    });

    buildOverlayFeatures();

    // Nonaktifkan mouseWheelZoom agar scroll halaman beranda tidak terjebak di dalam kanvas peta
    const interactions = defaultInteractions({
      mouseWheelZoom: false,
      onFocusOnly: false,
    });

    const initialKec = activeInspectorKecamatan.value;
    mapInstance = new Map({
      target: heroMapContainer.value,
      layers: [baseTileLayer, batasKabLayer, roadMvtLayer, segmenMvtLayer, overlayVectorLayer],
      view: new View({
        center: projPkg.fromLonLat(initialKec.coordinate),
        zoom: Math.max(initialKec.zoom - 0.6, 11.8),
        minZoom: 9,
        maxZoom: 18,
      }),
      controls: [],
      interactions,
    });

    mapInstance.on('pointermove', (evt: any) => {
      if (evt.dragging || !olModules) return;
      const lonLat = olModules.toLonLat(evt.coordinate);
      if (lonLat && lonLat.length >= 2) {
        liveCursorCoords.value = [Number(lonLat[0]), Number(lonLat[1])];
      }

      const hit = mapInstance.hasFeatureAtPixel(evt.pixel, {
        layerFilter: (l: any) => l === overlayVectorLayer,
        hitTolerance: 6,
      });
      const targetEl = mapInstance.getTargetElement();
      if (targetEl) {
        targetEl.style.cursor = hit ? 'pointer' : '';
      }
    });

    mapInstance.on('singleclick', (evt: any) => {
      let clickedKec: KecamatanEntry | null = null;
      let clickedGeo: GeoparkSite | null = null;

      mapInstance.forEachFeatureAtPixel(
        evt.pixel,
        (feature: any) => {
          const role = feature.get('featureRole');
          if (role === 'kecamatan' && feature.get('kecamatanData')) {
            clickedKec = feature.get('kecamatanData');
            return true;
          }
          if (role === 'geosite' && feature.get('siteData')) {
            clickedGeo = feature.get('siteData');
            return true;
          }
          return false;
        },
        {
          layerFilter: (l: any) => l === overlayVectorLayer,
          hitTolerance: 8,
        }
      );

      if (clickedKec) {
        selectInspectorKecamatan(clickedKec);
      } else if (clickedGeo) {
        navigateTo(`/maps?geosite=${encodeURIComponent((clickedGeo as GeoparkSite).slug)}`);
      }
    });

    mapInstance.once('rendercomplete', () => {
      isMapLoading.value = false;
    });

    setTimeout(() => {
      isMapLoading.value = false;
    }, 900);

    if (typeof ResizeObserver !== 'undefined' && heroMapContainer.value) {
      resizeObserver = new ResizeObserver(() => {
        if (mapInstance) mapInstance.updateSize();
      });
      resizeObserver.observe(heroMapContainer.value);
    }
  } catch (err) {
    console.warn('Gagal memuat pratinjau peta OpenLayers pada Hero:', err);
    isMapFallback.value = true;
    isMapLoading.value = false;
  }
}

watch(
  () => props.geoparkSites,
  () => {
    buildOverlayFeatures();
  }
);

function handleOutsideClick(event: MouseEvent) {
  if (!searchContainerRef.value) return;
  if (!searchContainerRef.value.contains(event.target as Node)) {
    isSearchFocused.value = false;
  }
}

onMounted(() => {
  window.addEventListener('pointerdown', handleOutsideClick);
  initHeroMap();
});

onBeforeUnmount(() => {
  window.removeEventListener('pointerdown', handleOutsideClick);
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (mapInstance) {
    mapInstance.setTarget(null);
    mapInstance = null;
  }
});
</script>

<template>
  <header
    aria-labelledby="hero-title"
    class="relative w-full overflow-x-clip bg-[#F7F8F5] dark:bg-[#070b14] pt-24 pb-10 sm:pt-28 sm:pb-12 lg:pt-28 lg:pb-14 border-b border-[#DFE5DE] dark:border-white/10"
  >
    <!-- Grid kartografis dua tingkat (mayor 96px & sub-grid 24px) sesuai identitas lembar peta MELAROSA (R-07) -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 dark:hidden"
      style="
        background-image:
          linear-gradient(to right, rgba(23, 107, 77, 0.11) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(23, 107, 77, 0.11) 1px, transparent 1px),
          linear-gradient(to right, rgba(23, 37, 31, 0.045) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(23, 37, 31, 0.045) 1px, transparent 1px);
        background-size: 96px 96px, 96px 96px, 24px 24px, 24px 24px;
        mask-image: radial-gradient(ellipse 90% 85% at 50% 45%, #000 65%, transparent 100%);
        -webkit-mask-image: radial-gradient(ellipse 90% 85% at 50% 45%, #000 65%, transparent 100%);
      "
    />
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 hidden dark:block"
      style="
        background-image:
          linear-gradient(to right, rgba(52, 211, 153, 0.11) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(52, 211, 153, 0.11) 1px, transparent 1px),
          linear-gradient(to right, rgba(148, 163, 184, 0.045) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(148, 163, 184, 0.045) 1px, transparent 1px);
        background-size: 96px 96px, 96px 96px, 24px 24px, 24px 24px;
        mask-image: radial-gradient(ellipse 90% 85% at 50% 45%, #000 65%, transparent 100%);
        -webkit-mask-image: radial-gradient(ellipse 90% 85% at 50% 45%, #000 65%, transparent 100%);
      "
    />

    <div class="relative z-10 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div
        class="grid grid-cols-1 md:grid-cols-[minmax(0,48fr)_minmax(0,52fr)] lg:grid-cols-[minmax(0,44fr)_minmax(0,56fr)] gap-8 md:gap-7 lg:gap-9 xl:gap-10 items-center lg:items-stretch"
      >
        <div class="flex flex-col justify-between gap-6 lg:gap-5 py-0.5 lg:py-1">
          <!-- Bagian Atas: Identitas Melarosa -->
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold tracking-wide text-[#176B4D] dark:text-emerald-400">
            <span class="font-bold tracking-wider uppercase">MELAROSA</span>
            <span class="text-[#647067]/60 dark:text-white/30" aria-hidden="true">·</span>
            <span class="text-[#17251F] dark:text-slate-100 font-semibold">
              Monitoring Evaluasi Data Spasial Desa
            </span>
            <span class="hidden sm:inline text-[#647067]/50 dark:text-white/25" aria-hidden="true">|</span>
            <span class="text-[#647067] dark:text-slate-400 font-normal">
              Pemerintah Kabupaten Bojonegoro
            </span>
          </div>

          <!-- Bagian Tengah: Headline, Deskripsi, dan Tombol Navigasi -->
          <div class="space-y-4 sm:space-y-5 my-auto">
            <h1
              id="hero-title"
              class="max-w-xl text-3xl sm:text-4xl lg:text-[2.65rem] xl:text-[2.85rem] font-bold leading-[1.12] tracking-tight text-[#17251F] dark:text-white"
            >
              Jelajahi Bojonegoro, dalam Satu Peta.
            </h1>

            <p class="max-w-lg text-sm sm:text-base lg:text-[1.0625rem] leading-relaxed text-[#647067] dark:text-slate-300">
              Temukan infrastruktur pembangunan, dan potensi Kabupaten Bojonegoro.
            </p>

            <div class="flex flex-wrap items-center gap-3 pt-0.5">
              <NuxtLink
                to="/maps"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2563EB] hover:bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-xs transition-colors min-h-[46px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] focus-visible:ring-offset-2"
              >
                <UIcon name="i-lucide-map" class="size-4 shrink-0" />
                <span>Jelajahi Peta</span>
              </NuxtLink>

              <a
                href="#cerita-geopark"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#B8C4BC] dark:border-white/20 bg-white dark:bg-[#0c1522] hover:border-[#176B4D] dark:hover:border-emerald-400 px-5 py-3 text-sm font-semibold text-[#17251F] dark:text-white transition-colors min-h-[46px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
              >
                <UIcon name="i-lucide-mountain" class="size-4 text-[#176B4D] dark:text-emerald-400 shrink-0" />
                <span>Kenali Geopark</span>
              </a>
            </div>
          </div>

          <!-- Bagian Bawah: Fitur Search -->
          <div ref="searchContainerRef" class="relative w-full max-w-xl">
            <form
              class="flex flex-col sm:flex-row md:flex-col xl:flex-row items-stretch gap-2 rounded-2xl border border-[#B8C4BC] dark:border-white/20 bg-white dark:bg-[#0c1522] p-1.5 shadow-xs focus-within:border-[#2563EB] dark:focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-[#2563EB]/20 transition-all"
              role="search"
              aria-label="Pencarian Kecamatan, Desa, Ruas Jalan, dan Situs Geopark"
              @submit.prevent="onHeroSearchSubmit"
            >
              <div class="relative flex flex-1 items-center gap-2.5 px-3 min-h-[44px]">
                <UIcon name="i-lucide-search" class="size-5 text-[#2563EB] dark:text-blue-400 shrink-0" />
                <input
                  v-model="heroSearchQuery"
                  type="text"
                  role="combobox"
                  aria-autocomplete="list"
                  aria-controls="hero-search-listbox"
                  :aria-expanded="isSearchFocused && heroSearchQuery.trim().length > 0"
                  placeholder="Cari kecamatan, desa, ruas jalan, atau Geopark..."
                  class="w-full bg-transparent text-sm sm:text-base text-[#17251F] dark:text-white placeholder-[#647067] dark:placeholder-slate-400 focus:outline-none border-0 p-0"
                  aria-label="Ketik nama kecamatan, desa, ruas jalan, situs geopark, atau koordinat"
                  @focus="isSearchFocused = true"
                  @keydown="onSearchKeydown"
                />
                <button
                  v-if="heroSearchQuery"
                  type="button"
                  class="size-11 -mr-1 rounded-xl flex items-center justify-center text-[#647067] hover:text-[#17251F] dark:hover:text-white hover:bg-[#F7F8F5] dark:hover:bg-white/10 transition-colors cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  aria-label="Hapus teks pencarian"
                  @click="clearHeroSearch"
                >
                  <UIcon name="i-lucide-x" class="size-4" />
                </button>
              </div>

              <button
                type="submit"
                class="w-full sm:w-auto md:w-full xl:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-[#17251F] hover:bg-[#102B22] dark:bg-slate-800 dark:hover:bg-slate-700 px-5 py-2.5 text-sm font-semibold text-white transition-colors cursor-pointer min-h-[44px] shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
              >
                <span>Cari Lokasi</span>
              </button>
            </form>

            <div
              v-if="isSearchFocused && heroSearchQuery.trim().length > 0"
              id="hero-search-listbox"
              role="listbox"
              class="absolute left-0 right-0 top-full mt-2 lg:top-auto lg:bottom-full lg:mt-0 lg:mb-2 z-40 rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] p-2 shadow-xl"
            >
              <div class="flex items-center justify-between px-3 py-1.5 text-xs text-[#647067] dark:text-slate-400 border-b border-[#DFE5DE] dark:border-white/10">
                <span>Saran lokasi terhubung</span>
                <button
                  type="button"
                  class="min-h-[36px] px-2 text-[#2563EB] dark:text-blue-400 hover:underline cursor-pointer font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] rounded"
                  @click="isSearchFocused = false"
                >
                  Tutup
                </button>
              </div>

              <div
                v-if="heroSuggestions.length > 0"
                class="divide-y divide-[#DFE5DE]/60 dark:divide-white/5 max-h-64 overflow-y-auto pt-1"
              >
                <button
                  v-for="(item, idx) in heroSuggestions"
                  :key="item.id"
                  type="button"
                  role="option"
                  :aria-selected="highlightedIndex === idx"
                  class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-left transition-colors cursor-pointer min-h-[48px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  :class="
                    highlightedIndex === idx
                      ? 'bg-[#F7F8F5] dark:bg-white/10'
                      : 'hover:bg-[#F7F8F5] dark:hover:bg-white/5'
                  "
                  @mouseenter="highlightedIndex = idx"
                  @click="handleSelectHeroSuggestion(item)"
                >
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-[#17251F] dark:text-white truncate">
                      {{ item.title }}
                    </p>
                    <p class="text-xs text-[#647067] dark:text-slate-400 truncate">
                      {{ item.subtitle }}
                    </p>
                  </div>
                  <span class="shrink-0 rounded-md bg-blue-50 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-800/60 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-300">
                    {{ item.badge }}
                  </span>
                </button>
              </div>

              <div v-else class="p-4 text-center">
                <p class="text-xs text-[#647067] dark:text-slate-300">
                  Tekan <strong class="text-[#17251F] dark:text-white font-semibold">Cari Lokasi</strong> untuk menelusuri "{{ heroSearchQuery }}" pada peta penuh Bojonegoro.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="w-full flex flex-col justify-center">
          <div
            class="w-full lg:max-h-[560px] flex flex-col rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] overflow-hidden shadow-xs"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-b border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5]/60 dark:bg-[#09111d]">
              <div class="flex items-center gap-2 text-xs">
                <UIcon name="i-lucide-compass" class="size-4 text-[#2563EB] dark:text-blue-400 shrink-0" />
                <span class="font-semibold text-[#17251F] dark:text-white">
                  Observatorium Peta Wilayah Bojonegoro
                </span>
                <span class="text-[#647067] dark:text-slate-400 hidden sm:inline">
                  · {{ isMapFallback ? 'Pratinjau Kartografis' : 'Peta Langsung' }}
                </span>
              </div>

              <div class="font-mono text-xs text-[#647067] dark:text-slate-300 tabular-nums">
                WGS 84 · {{ displayCoordinates }}
              </div>
            </div>

            <div
              class="relative w-full aspect-[4/3] sm:aspect-[16/10] md:aspect-[5/4] lg:aspect-auto lg:h-[395px] xl:h-[415px] bg-[#E9EFEA] dark:bg-[#08101c] overflow-hidden"
              @mouseleave="liveCursorCoords = null"
            >
              <!-- Kanvas fallback berbasis proyeksi koordinat aktual apabila tile eksternal tidak dapat dimuat -->
              <svg
                v-if="isMapFallback || isUsingVectorFallbackTiles || isMapLoading"
                viewBox="0 0 480 260"
                class="absolute inset-0 w-full h-full object-cover"
                role="img"
                :aria-label="`Peta vektor koridor wilayah dan jaringan jalan Kecamatan ${activeInspectorKecamatan.name}, Kabupaten Bojonegoro`"
              >
                <rect width="480" height="260" class="fill-[#EEF2EE] dark:fill-[#08101c]" />

                <g class="stroke-[#D5DFD6] dark:stroke-white/5" stroke-width="1" stroke-dasharray="3 3">
                  <line x1="0" y1="65" x2="480" y2="65" />
                  <line x1="0" y1="130" x2="480" y2="130" />
                  <line x1="0" y1="195" x2="480" y2="195" />
                  <line x1="120" y1="0" x2="120" y2="260" />
                  <line x1="240" y1="0" x2="240" y2="260" />
                  <line x1="360" y1="0" x2="360" y2="260" />
                </g>

                <polygon
                  points="24,185 68,118 115,42 185,36 255,68 345,58 446,44 456,108 408,162 340,205 245,222 162,240 96,214"
                  class="fill-white/75 dark:fill-[#0d192b]/80 stroke-[#475569] dark:stroke-slate-400"
                  stroke-width="1.75"
                  stroke-dasharray="8 4 2 4"
                />

                <path
                  v-for="(dPath, pIdx) in fallbackCorridorPaths"
                  :key="`corridor-${pIdx}`"
                  :d="dPath"
                  fill="none"
                  stroke="#10B981"
                  stroke-width="2.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  opacity="0.8"
                />

                <rect
                  :x="fallbackActiveBox.x"
                  :y="fallbackActiveBox.y"
                  :width="fallbackActiveBox.width"
                  :height="fallbackActiveBox.height"
                  rx="4"
                  fill="rgba(37, 99, 235, 0.12)"
                  stroke="#2563EB"
                  stroke-width="1.8"
                  stroke-dasharray="5 3"
                />

                <g
                  v-for="node in fallbackDistrictNodes"
                  :key="node.entry.name"
                  class="cursor-pointer"
                  @click="selectInspectorKecamatan(node.entry)"
                >
                  <circle
                    v-if="node.isActive"
                    :cx="node.x"
                    :cy="node.y"
                    r="12"
                    fill="rgba(37, 99, 235, 0.2)"
                  />
                  <circle
                    :cx="node.x"
                    :cy="node.y"
                    :r="node.isActive ? 5.5 : 3.2"
                    :fill="node.isActive ? '#2563EB' : '#475569'"
                    stroke="#FFFFFF"
                    :stroke-width="node.isActive ? 2 : 1"
                  />
                  <text
                    v-if="node.isActive"
                    :x="node.x"
                    :y="Math.max(16, node.y - 11)"
                    text-anchor="middle"
                    class="fill-[#17251F] dark:fill-white font-bold text-[11px]"
                  >
                    Kec. {{ node.entry.name }}
                  </text>
                </g>
              </svg>

              <div
                v-show="!isMapFallback"
                ref="heroMapContainer"
                class="relative z-10 w-full h-full"
                role="region"
                :aria-label="`Peta interaktif Kecamatan ${activeInspectorKecamatan.name}, Kabupaten Bojonegoro`"
              />

              <div
                v-if="isMapLoading"
                class="absolute inset-0 z-20 flex items-center justify-center bg-[#F7F8F5]/75 dark:bg-[#070b14]/75 backdrop-blur-xs pointer-events-none"
              >
                <div class="inline-flex items-center gap-2.5 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] px-4 py-2.5 text-xs font-semibold text-[#17251F] dark:text-white shadow-sm">
                  <UIcon name="i-lucide-loader-2" class="size-4 animate-spin text-[#2563EB] dark:text-blue-400" />
                  <span>Memuat layer geospasial Bojonegoro...</span>
                </div>
              </div>

              <div
                v-if="!isMapFallback"
                class="absolute top-3 right-3 z-20 flex flex-col gap-1.5"
              >
                <button
                  type="button"
                  class="size-11 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white/95 dark:bg-[#0c1522]/95 hover:border-[#2563EB] text-[#17251F] dark:text-white flex items-center justify-center shadow-xs transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  aria-label="Perbesar tampilan peta"
                  title="Perbesar peta"
                  @click="handleHeroMapZoom(1)"
                >
                  <UIcon name="i-lucide-plus" class="size-4" />
                </button>
                <button
                  type="button"
                  class="size-11 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white/95 dark:bg-[#0c1522]/95 hover:border-[#2563EB] text-[#17251F] dark:text-white flex items-center justify-center shadow-xs transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  aria-label="Perkecil tampilan peta"
                  title="Perkecil peta"
                  @click="handleHeroMapZoom(-1)"
                >
                  <UIcon name="i-lucide-minus" class="size-4" />
                </button>
                <button
                  type="button"
                  class="size-11 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white/95 dark:bg-[#0c1522]/95 hover:border-[#2563EB] text-[#17251F] dark:text-white flex items-center justify-center shadow-xs transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  aria-label="Tampilkan seluruh cakupan Kabupaten Bojonegoro"
                  title="Cakupan Kabupaten Bojonegoro"
                  @click="handleResetKabupatenView"
                >
                  <UIcon name="i-lucide-maximize-2" class="size-4" />
                </button>
              </div>

              <div
                class="absolute bottom-3 left-3 z-20 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-[#DFE5DE]/90 dark:border-white/15 bg-white/90 dark:bg-[#0c1522]/90 backdrop-blur-xs px-3 py-1.5 text-[11px] font-medium text-[#17251F] dark:text-slate-200"
              >
                <span class="inline-flex items-center gap-1.5">
                  <span class="inline-block w-3.5 h-0.5 border-b-2 border-dashed border-[#2563EB]" />
                  <span>Batas Kecamatan</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <span class="inline-block w-3.5 h-0.5 bg-[#10B981]" />
                  <span>Jalan Poros</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <span class="inline-block size-2 rounded-full bg-[#176B4D] border border-white" />
                  <span>Titik Geopark</span>
                </span>
              </div>
            </div>

            <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-t border-[#DFE5DE] dark:border-white/10 bg-white dark:bg-[#0c1522] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="min-w-0 space-y-0.5">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                  <h2 class="text-sm sm:text-base font-bold text-[#17251F] dark:text-white">
                    Kecamatan {{ activeInspectorKecamatan.name }}
                  </h2>
                  <span class="text-xs font-semibold text-[#176B4D] dark:text-emerald-400">
                    · Zona {{ activeInspectorKecamatan.zona }}
                  </span>
                </div>
                <p class="text-xs text-[#647067] dark:text-slate-300 truncate">
                  {{ activeInspectorKecamatan.desc }} · Pusat {{ formatCoordPair(activeInspectorKecamatan.coordinate) }}
                </p>
              </div>

              <div class="flex items-center shrink-0">
                <button
                  type="button"
                  class="inline-flex w-full sm:w-auto items-center justify-center gap-1.5 rounded-xl border border-blue-200 dark:border-blue-800/70 bg-blue-50/80 dark:bg-blue-950/50 hover:bg-[#2563EB] hover:text-white dark:hover:bg-blue-600 dark:hover:text-white px-4 py-2 text-xs sm:text-sm font-semibold text-blue-700 dark:text-blue-300 transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
                  @click="openKecamatanInMap(activeInspectorKecamatan)"
                >
                  <UIcon name="i-lucide-locate-fixed" class="size-4 shrink-0" />
                  <span>Buka Peta {{ activeInspectorKecamatan.name }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

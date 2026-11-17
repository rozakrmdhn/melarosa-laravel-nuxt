<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import * as maplibregl from 'maplibre-gl'
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import 'maplibre-gl/dist/maplibre-gl.css'
import type {
  RdtrCameraPreset,
  RdtrSelectedFeature,
  RdtrSummary,
} from '~/types/rdtr-map'

interface Props {
  showPixelSwitch?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showPixelSwitch: false,
})

const emit = defineEmits<{
  (e: 'switch-pixel'): void
}>()

interface LandmarkItem {
  id: string
  nm: string
  desc: string
  kz: string
  zn: string
  kd: string
  coords: [number, number]
}

const DEFAULT_CENTER: [number, number] = [111.881, -7.152]
const DEFAULT_ZOOM = 15.4
const DEFAULT_PITCH = 58
const DEFAULT_BEARING = 22

const CAMERA_PRESETS: RdtrCameraPreset[] = [
  {
    id: 'alun-alun',
    label: 'Pusat Kota',
    subtitle: 'Alun-Alun, Pendopo & K-1',
    center: [111.881, -7.152],
    zoom: 15.4,
    pitch: 58,
    bearing: 22,
  },
  {
    id: 'bengawan',
    label: 'Bengawan Solo',
    subtitle: 'Lebar sungai BA & jembatan',
    center: [111.878, -7.1432],
    zoom: 15.7,
    pitch: 58,
    bearing: 22,
  },
  {
    id: 'stasiun-kai',
    label: 'Stasiun & Rel',
    subtitle: 'Jalur kereta & koridor K-2',
    center: [111.8868, -7.1636],
    zoom: 15.9,
    pitch: 58,
    bearing: 22,
  },
  {
    id: 'ahmad-yani',
    label: 'Jl. Ahmad Yani',
    subtitle: 'Arteri, RTH taman & RSUD',
    center: [111.8945, -7.1558],
    zoom: 15.8,
    pitch: 58,
    bearing: 22,
  },
  {
    id: 'pertanian-kapas',
    label: 'Sawah & Kapas',
    subtitle: 'P-1 tanaman pangan & R-3',
    center: [111.914, -7.166],
    zoom: 14.9,
    pitch: 55,
    bearing: 22,
  },
]

const ROAD_CLASS_LABELS: Record<string, string> = {
  'Jalan Tol': 'Jalan Tol',
  'Jalan Arteri Primer': 'Arteri Primer',
  'Jalan Arteri Sekunder': 'Arteri Sekunder',
  'Jalan Kolektor Primer': 'Kolektor Primer',
  'Jalan Kolektor Sekunder': 'Kolektor Sekunder',
  'Jalan Lokal Primer': 'Lokal Primer',
  'Jalan Lokal Sekunder': 'Lokal Sekunder',
  'Jalan Lingkungan Sekunder': 'Lingkungan Sekunder',
  'Jaringan Jalur Kereta Api Antarkota': 'Jalur Kereta Api Antarkota',
}

const mapContainerRef = ref<HTMLElement | null>(null)
let map: maplibregl.Map | null = null
const activeMarkers: maplibregl.Marker[] = []
let initEpoch = 0

const isLoading = ref(true)
const isGeneratedLoading = ref(true)
const loadingStageLabel = ref('Memuat pola ruang, jalan, & sungai RDTR...')
const loadError = ref<string | null>(null)
const summary = ref<RdtrSummary | null>(null)
const landmarks = ref<LandmarkItem[]>([])

const cameraZoom = ref(DEFAULT_ZOOM)
const cameraPitch = ref(DEFAULT_PITCH)
const cameraBearing = ref(DEFAULT_BEARING)
const cameraCenter = ref<[number, number]>([...DEFAULT_CENTER])

const isDrawerOpen = ref(false)
const searchQuery = ref('')
const selectedFeature = ref<RdtrSelectedFeature | null>(null)
const activePresetId = ref<string>('alun-alun')

const showOsmBuildings = ref(true)
const showGeneratedBuildings = ref(true)
const showPolaruang = ref(true)
const useOfficialRdtrColors = ref(false)
const showLiveWms = ref(false)
const showRoads = ref(true)
const showEnvironment = ref(true)
const showLandmarks = ref(true)

const wmsTileTemplate =
  'https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&FORMAT=image/png&TRANSPARENT=true&SRS=EPSG:3857&STYLES=&LAYERS=palapa:POLARUANG_AR_5K_2022_BOJONEGORO&WIDTH=256&HEIGHT=256&BBOX={bbox-epsg-3857}'

const zoneNameByCode = computed<Record<string, string>>(() => {
  const rec: Record<string, string> = {}
  for (const z of summary.value?.zoneStats ?? []) {
    rec[z.kz] = z.namobj
  }
  return rec
})

const totalBuildingsCount = computed(() => {
  if (!summary.value) return 0
  return (
    summary.value.counts.osmRdtrBuildings +
    summary.value.counts.rdtrGeneratedBuildings
  )
})

const filteredLandmarks = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return landmarks.value
  return landmarks.value.filter(
    (item) =>
      item.nm.toLowerCase().includes(q) ||
      item.desc.toLowerCase().includes(q) ||
      item.kz.toLowerCase().includes(q) ||
      item.zn.toLowerCase().includes(q) ||
      item.kd.toLowerCase().includes(q)
  )
})

const filteredZoneStats = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const rows = summary.value?.zoneStats ?? []
  if (!q) return rows.slice(0, 14)
  return rows.filter(
    (z) =>
      z.kz.toLowerCase().includes(q) ||
      z.namobj.toLowerCase().includes(q) ||
      z.cat.toLowerCase().includes(q)
  )
})

const roadClassEntries = computed(() => {
  const raw = summary.value?.roadKmByClass ?? {}
  return Object.entries(raw)
    .map(([key, km]) => ({
      key,
      label: ROAD_CLASS_LABELS[key] ?? key,
      km,
    }))
    .sort((a, b) => b.km - a.km)
})

const northOrientationLabel = computed(() => {
  const raw = ((-cameraBearing.value % 360) + 360) % 360
  const signed = raw > 180 ? raw - 360 : raw
  if (Math.abs(signed) < 2) return 'Utara lurus di atas'
  if (signed < 0) return `Utara miring kiri ${Math.abs(Math.round(signed))}°`
  return `Utara miring kanan ${Math.round(signed)}°`
})

const formattedScaleHint = computed(() => {
  const mPerPx = 77663 / Math.pow(2, cameraZoom.value)
  const barMeters = Math.max(10, Math.round((mPerPx * 72) / 10) * 10)
  return barMeters >= 1000
    ? `${(barMeters / 1000).toFixed(1)} km`
    : `${barMeters} m`
})

function setLayerGroupVisibility(layerIds: string[], visible: boolean) {
  if (!map) return
  const val = visible ? 'visible' : 'none'
  for (const id of layerIds) {
    if (map.getLayer(id)) {
      map.setLayoutProperty(id, 'visibility', val)
    }
  }
}

function toggleOsmBuildings() {
  showOsmBuildings.value = !showOsmBuildings.value
  setLayerGroupVisibility(['buildings-osm-3d'], showOsmBuildings.value)
}

function toggleGeneratedBuildings() {
  showGeneratedBuildings.value = !showGeneratedBuildings.value
  setLayerGroupVisibility(['buildings-generated-3d'], showGeneratedBuildings.value)
}

function togglePolaruang() {
  showPolaruang.value = !showPolaruang.value
  setLayerGroupVisibility(
    ['rdtr-polaruang-fill', 'rdtr-polaruang-outline'],
    showPolaruang.value
  )
}

function toggleOfficialRdtrColors() {
  useOfficialRdtrColors.value = !useOfficialRdtrColors.value
  if (!map || !map.getLayer('rdtr-polaruang-fill')) return
  map.setPaintProperty(
    'rdtr-polaruang-fill',
    'fill-color',
    useOfficialRdtrColors.value ? ['get', 'col'] : ['get', 'mcol']
  )
  map.setPaintProperty(
    'rdtr-polaruang-fill',
    'fill-opacity',
    useOfficialRdtrColors.value ? 0.72 : 0.94
  )
}

function toggleLiveWms() {
  showLiveWms.value = !showLiveWms.value
  setLayerGroupVisibility(['rdtr-wms-layer'], showLiveWms.value)
}

function toggleRoads() {
  showRoads.value = !showRoads.value
  setLayerGroupVisibility(
    [
      'road-corridor-bj-fill',
      'road-corridor-bj-curb',
      'roads-casing',
      'roads-fill',
      'roads-centerline',
      'rail-bed',
      'rail-ties',
      'rail-tracks',
    ],
    showRoads.value
  )
}

function toggleEnvironment() {
  showEnvironment.value = !showEnvironment.value
  setLayerGroupVisibility(
    [
      'sawah-grid-lines',
      'badan-air-fill',
      'badan-air-bank',
      'waterways-line-casing',
      'waterways-line-fill',
      'trees-3d',
    ],
    showEnvironment.value
  )
}

function toggleLandmarks() {
  showLandmarks.value = !showLandmarks.value
  for (const marker of activeMarkers) {
    const el = marker.getElement()
    el.style.display = showLandmarks.value ? 'flex' : 'none'
  }
}

function resetNorth3DView() {
  if (!map) return
  activePresetId.value = 'alun-alun'
  map.easeTo({
    center: DEFAULT_CENTER,
    zoom: DEFAULT_ZOOM,
    pitch: DEFAULT_PITCH,
    bearing: DEFAULT_BEARING,
    duration: 700,
  })
}

function resetOrientationOnly() {
  if (!map) return
  map.easeTo({
    pitch: DEFAULT_PITCH,
    bearing: DEFAULT_BEARING,
    duration: 500,
  })
}

function togglePitch2D3D() {
  if (!map) return
  const nextPitch = cameraPitch.value > 25 ? 0 : DEFAULT_PITCH
  map.easeTo({
    pitch: nextPitch,
    bearing: DEFAULT_BEARING,
    duration: 550,
  })
}

function rotateBearingBy(deltaDeg: number) {
  if (!map) return
  map.easeTo({
    bearing: cameraBearing.value + deltaDeg,
    duration: 300,
  })
}

function zoomBy(delta: number) {
  if (!map) return
  map.easeTo({
    zoom: Math.max(12.2, Math.min(18.6, cameraZoom.value + delta)),
    duration: 280,
  })
}

function flyToPreset(preset: RdtrCameraPreset) {
  if (!map) return
  activePresetId.value = preset.id
  map.flyTo({
    center: preset.center,
    zoom: preset.zoom,
    pitch: preset.pitch,
    bearing: preset.bearing,
    duration: 900,
  })
}

function flyToLandmark(item: LandmarkItem) {
  if (!map) return
  activePresetId.value = ''
  map.flyTo({
    center: item.coords,
    zoom: Math.max(cameraZoom.value, 16.3),
    pitch: DEFAULT_PITCH,
    bearing: DEFAULT_BEARING,
    duration: 850,
  })
  selectedFeature.value = {
    kind: 'landmark',
    title: item.nm,
    subtitle: item.desc,
    badgeCode: item.kz || 'RDTR',
    badgeColor: '#047857',
    source: 'Landmark Bojonegoro',
    metrics: [
      { label: 'Zona RDTR', value: item.zn || '-' },
      { label: 'Desa/Kelurahan', value: item.kd || 'Bojonegoro' },
    ],
    coords: item.coords,
  }
}

function clearSelection() {
  selectedFeature.value = null
  if (map && map.getLayer('buildings-selected-highlight')) {
    map.setFilter('buildings-selected-highlight', ['==', ['get', 'id'], '__none__'])
  }
  if (map && map.getLayer('buildings-generated-highlight')) {
    map.setFilter('buildings-generated-highlight', ['==', ['get', 'id'], '__none__'])
  }
}

function buildWidthExpression(extraMeters: number): maplibregl.ExpressionSpecification {
  return [
    'interpolate',
    ['exponential', 2],
    ['zoom'],
    12,
    ['max', 1.1, ['*', ['+', ['get', 'w'], extraMeters], 0.08]],
    15,
    ['max', 1.8, ['*', ['+', ['get', 'w'], extraMeters], 0.422]],
    16,
    ['*', ['+', ['get', 'w'], extraMeters], 0.844],
    17,
    ['*', ['+', ['get', 'w'], extraMeters], 1.688],
    19,
    ['*', ['+', ['get', 'w'], extraMeters], 6.75],
  ]
}

function attachLandmarkMarkers(items: LandmarkItem[]) {
  if (!map) return
  for (const m of activeMarkers) m.remove()
  activeMarkers.length = 0

  for (const item of items) {
    const btn = document.createElement('button')
    btn.type = 'button'
    btn.className =
      'rdtr-landmark-pin flex items-center gap-1 rounded-full border border-slate-700 bg-white/95 px-2 py-0.5 text-[11px] font-semibold text-slate-900 shadow-sm transition hover:border-emerald-700 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700'
    btn.style.display = showLandmarks.value ? 'flex' : 'none'
    btn.setAttribute('aria-label', `Lokasi ${item.nm}`)

    const dot = document.createElement('span')
    dot.className = 'h-2 w-2 shrink-0 rounded-full bg-red-700'
    const text = document.createElement('span')
    text.textContent = item.nm
    btn.appendChild(dot)
    btn.appendChild(text)

    btn.addEventListener('click', (ev) => {
      ev.stopPropagation()
      flyToLandmark(item)
    })

    const markerAnchor: maplibregl.PositionAnchor = item.id.includes('masjid')
      ? 'right'
      : item.id.includes('pendopo')
        ? 'bottom-left'
        : 'bottom'

    const marker = new maplibregl.Marker({
      element: btn,
      anchor: markerAnchor,
    })
      .setLngLat(item.coords)
      .addTo(map)
    activeMarkers.push(marker)
  }
}

async function fetchJsonSafe<T>(url: string): Promise<T> {
  const res = await fetch(url, { cache: 'default' })
  if (!res.ok) {
    throw new Error(`HTTP ${res.status} saat memuat ${url}`)
  }
  return (await res.json()) as T
}

async function initMap() {
  if (!mapContainerRef.value) return
  const epoch = ++initEpoch
  isLoading.value = true
  isGeneratedLoading.value = true
  loadingStageLabel.value = 'Memuat pola ruang, jalan, & sungai RDTR...'
  loadError.value = null

  if (map) {
    for (const m of activeMarkers) m.remove()
    activeMarkers.length = 0
    map.remove()
    map = null
  }

  try {
    const [summaryData, lmRaw] = await Promise.all([
      fetchJsonSafe<RdtrSummary>('/tiles/bojonegoro-rdtr/summary.json'),
      fetchJsonSafe<GeoJSON.FeatureCollection>('/tiles/bojonegoro-rdtr/landmarks.geojson'),
    ])
    if (epoch !== initEpoch) return
    summary.value = summaryData
    landmarks.value = (lmRaw.features ?? []).map((f) => {
      const p = (f.properties ?? {}) as Record<string, string>
      const pt = f.geometry as GeoJSON.Point
      return {
        id: p.id || '',
        nm: p.nm || 'Lokasi',
        desc: p.desc || '',
        kz: p.kz || '-',
        zn: p.zn || '-',
        kd: p.kd || 'Bojonegoro',
        coords: [pt.coordinates[0] ?? 111.881, pt.coordinates[1] ?? -7.152],
      }
    })

    const emptyFc: GeoJSON.FeatureCollection = {
      type: 'FeatureCollection',
      features: [],
    }
    const cam = summaryData.defaultCamera
    const roadWidthOuter = buildWidthExpression(2.2)
    const roadWidthInner = buildWidthExpression(0)
    const waterLineOuter = buildWidthExpression(2.4)
    const waterLineInner = buildWidthExpression(0)

    const initialStyle: maplibregl.StyleSpecification = {
      version: 8,
      light: {
        anchor: 'map',
        color: '#FFF7E8',
        intensity: 0.42,
        position: [1.35, 210, 42],
      },
      sources: {
        polaruang: {
          type: 'geojson',
          data: emptyFc,
          tolerance: 0.45,
        },
        roads: {
          type: 'geojson',
          data: emptyFc,
          tolerance: 0.35,
        },
        waterways: {
          type: 'geojson',
          data: emptyFc,
          tolerance: 0.35,
        },
        environment: {
          type: 'geojson',
          data: emptyFc,
          tolerance: 0.4,
        },
        'buildings-osm': {
          type: 'geojson',
          data: emptyFc,
          tolerance: 0.45,
          maxzoom: 17,
        },
        'buildings-generated': {
          type: 'geojson',
          data: emptyFc,
          tolerance: 0.5,
          maxzoom: 17,
        },
        'rdtr-wms': {
          type: 'raster',
          tiles: [wmsTileTemplate],
          tileSize: 256,
        },
      },
      layers: [
        {
          id: 'rdtr-bg',
          type: 'background',
          paint: {
            'background-color': '#EFEAE0',
          },
        },
        {
          id: 'rdtr-polaruang-fill',
          type: 'fill',
          source: 'polaruang',
          paint: {
            'fill-color': ['get', 'mcol'],
            'fill-opacity': 0.94,
          },
        },
        {
          id: 'rdtr-polaruang-outline',
          type: 'line',
          source: 'polaruang',
          minzoom: 13.5,
          paint: {
            'line-color': '#958C7C',
            'line-width': 0.75,
            'line-opacity': 0.42,
          },
        },
        {
          id: 'rdtr-wms-layer',
          type: 'raster',
          source: 'rdtr-wms',
          layout: {
            visibility: 'none',
          },
          paint: {
            'raster-opacity': 0.68,
          },
        },
        {
          id: 'sawah-grid-lines',
          type: 'line',
          source: 'environment',
          filter: ['==', ['get', 'ft'], 'sawah_grid'],
          minzoom: 13,
          paint: {
            'line-color': '#8DB140',
            'line-width': 0.9,
            'line-opacity': 0.78,
          },
        },
        {
          id: 'badan-air-fill',
          type: 'fill',
          source: 'waterways',
          filter: ['==', ['get', 'ft'], 'water_poly'],
          paint: {
            'fill-color': [
              'match',
              ['get', 'cls'],
              'pond',
              '#3CAEE0',
              '#269AD2',
            ],
            'fill-opacity': 0.95,
          },
        },
        {
          id: 'badan-air-bank',
          type: 'line',
          source: 'waterways',
          filter: ['==', ['get', 'ft'], 'water_poly'],
          paint: {
            'line-color': '#B6E5FA',
            'line-width': 1.8,
            'line-opacity': 0.85,
          },
        },
        {
          id: 'waterways-line-casing',
          type: 'line',
          source: 'waterways',
          filter: ['==', ['get', 'ft'], 'water_line'],
          layout: {
            'line-cap': 'round',
            'line-join': 'round',
          },
          paint: {
            'line-color': '#BAE6FD',
            'line-width': waterLineOuter,
            'line-opacity': 0.75,
          },
        },
        {
          id: 'waterways-line-fill',
          type: 'line',
          source: 'waterways',
          filter: ['==', ['get', 'ft'], 'water_line'],
          layout: {
            'line-cap': 'round',
            'line-join': 'round',
          },
          paint: {
            'line-color': '#2597D2',
            'line-width': waterLineInner,
          },
        },
        {
          id: 'road-corridor-bj-fill',
          type: 'fill',
          source: 'roads',
          filter: ['==', ['get', 'ft'], 'corridor'],
          paint: {
            'fill-color': '#646F7D',
            'fill-opacity': 0.85,
          },
        },
        {
          id: 'road-corridor-bj-curb',
          type: 'line',
          source: 'roads',
          filter: ['==', ['get', 'ft'], 'corridor'],
          paint: {
            'line-color': '#3A4350',
            'line-width': 1.1,
            'line-opacity': 0.75,
          },
        },
        {
          id: 'roads-casing',
          type: 'line',
          source: 'roads',
          filter: [
            'all',
            ['!=', ['get', 'ft'], 'corridor'],
            ['!=', ['get', 'ft'], 'rail'],
          ],
          layout: {
            'line-cap': 'round',
            'line-join': 'round',
          },
          paint: {
            'line-color': '#353F4B',
            'line-width': roadWidthOuter,
            'line-opacity': 0.85,
          },
        },
        {
          id: 'roads-fill',
          type: 'line',
          source: 'roads',
          filter: [
            'all',
            ['!=', ['get', 'ft'], 'corridor'],
            ['!=', ['get', 'ft'], 'rail'],
          ],
          layout: {
            'line-cap': 'round',
            'line-join': 'round',
          },
          paint: {
            'line-color': [
              'match',
              ['get', 'cls'],
              'Jalan Tol',
              '#D97706',
              ['Jalan Arteri Primer', 'Jalan Arteri Sekunder', 'arteri'],
              '#495360',
              ['Jalan Kolektor Primer', 'Jalan Kolektor Sekunder', 'kolektor'],
              '#55606E',
              ['Jalan Lokal Primer', 'Jalan Lokal Sekunder', 'poros'],
              '#687484',
              '#788494',
            ],
            'line-width': roadWidthInner,
          },
        },
        {
          id: 'roads-centerline',
          type: 'line',
          source: 'roads',
          minzoom: 14.5,
          filter: [
            'in',
            ['get', 'cls'],
            [
              'literal',
              [
                'Jalan Tol',
                'Jalan Arteri Primer',
                'Jalan Arteri Sekunder',
                'Jalan Kolektor Primer',
                'arteri',
                'kolektor',
              ],
            ],
          ],
          paint: {
            'line-color': '#F8FAFC',
            'line-width': 1.15,
            'line-dasharray': [2.5, 2.8],
            'line-opacity': 0.72,
          },
        },
        {
          id: 'rail-bed',
          type: 'line',
          source: 'roads',
          filter: ['==', ['get', 'ft'], 'rail'],
          paint: {
            'line-color': '#6E6155',
            'line-width': 7.5,
          },
        },
        {
          id: 'rail-ties',
          type: 'line',
          source: 'roads',
          filter: ['==', ['get', 'ft'], 'rail'],
          paint: {
            'line-color': '#3C322A',
            'line-width': 5.2,
            'line-dasharray': [0.8, 1.4],
          },
        },
        {
          id: 'rail-tracks',
          type: 'line',
          source: 'roads',
          filter: ['==', ['get', 'ft'], 'rail'],
          paint: {
            'line-color': '#E2E8F0',
            'line-width': 1.4,
          },
        },
        {
          id: 'trees-3d',
          type: 'fill-extrusion',
          source: 'environment',
          minzoom: 13.6,
          filter: ['==', ['get', 'ft'], 'tree'],
          paint: {
            'fill-extrusion-color': ['get', 'col'],
            'fill-extrusion-base': ['get', 'bh'],
            'fill-extrusion-height': ['get', 'h'],
            'fill-extrusion-vertical-gradient': true,
            'fill-extrusion-opacity': 0.94,
          },
        },
        {
          id: 'buildings-generated-3d',
          type: 'fill-extrusion',
          source: 'buildings-generated',
          minzoom: 12.6,
          paint: {
            'fill-extrusion-color': ['get', 'col'],
            'fill-extrusion-base': ['get', 'bh'],
            'fill-extrusion-height': ['get', 'h'],
            'fill-extrusion-vertical-gradient': true,
            'fill-extrusion-opacity': 0.96,
          },
        },
        {
          id: 'buildings-osm-3d',
          type: 'fill-extrusion',
          source: 'buildings-osm',
          minzoom: 12.2,
          paint: {
            'fill-extrusion-color': ['get', 'col'],
            'fill-extrusion-base': ['get', 'bh'],
            'fill-extrusion-height': ['get', 'h'],
            'fill-extrusion-vertical-gradient': true,
            'fill-extrusion-opacity': 0.98,
          },
        },
        {
          id: 'buildings-selected-highlight',
          type: 'fill-extrusion',
          source: 'buildings-osm',
          filter: ['==', ['get', 'id'], '__none__'],
          paint: {
            'fill-extrusion-color': '#1D4ED8',
            'fill-extrusion-base': ['get', 'bh'],
            'fill-extrusion-height': ['+', ['get', 'h'], 0.8],
            'fill-extrusion-vertical-gradient': true,
          },
        },
        {
          id: 'buildings-generated-highlight',
          type: 'fill-extrusion',
          source: 'buildings-generated',
          filter: ['==', ['get', 'id'], '__none__'],
          paint: {
            'fill-extrusion-color': '#1D4ED8',
            'fill-extrusion-base': ['get', 'bh'],
            'fill-extrusion-height': ['+', ['get', 'h'], 0.8],
            'fill-extrusion-vertical-gradient': true,
          },
        },
      ],
    }

    maplibregl.setWorkerUrl(maplibreWorkerUrl)

    map = new maplibregl.Map({
      container: mapContainerRef.value,
      style: initialStyle,
      center: cam.center,
      zoom: cam.zoom,
      pitch: cam.pitch,
      bearing: cam.bearing,
      minZoom: 12,
      maxZoom: 18.8,
      maxPitch: 75,
      antialias: true,
      dragRotate: true,
      pitchWithRotate: true,
      attributionControl: false,
    })

    map.on('error', (ev) => {
      const msg = ev?.error?.message || ''
      if (msg && msg.toLowerCase().includes('webgl')) {
        isLoading.value = false
        loadError.value =
          'Peramban tidak dapat menginisialisasi akselerasi 3D WebGL untuk MapLibre GL JS.'
      } else if (isLoading.value && msg) {
        isLoading.value = false
        loadError.value = `Gagal menginisialisasi kanvas MapLibre: ${msg}`
      }
    })

    const syncCameraState = () => {
      if (!map) return
      const c = map.getCenter()
      cameraCenter.value = [
        Number(c.lng.toFixed(5)),
        Number(c.lat.toFixed(5)),
      ]
      cameraZoom.value = Number(map.getZoom().toFixed(2))
      cameraPitch.value = Number(map.getPitch().toFixed(1))
      cameraBearing.value = Number(map.getBearing().toFixed(1))
    }

    map.on('move', syncCameraState)

    const setSourceGeoJson = (sourceId: string, fc: GeoJSON.FeatureCollection) => {
      if (!map || epoch !== initEpoch) return
      const src = map.getSource(sourceId) as maplibregl.GeoJSONSource | undefined
      if (src) {
        src.setData(fc)
      }
    }

    map.on('load', async () => {
      if (epoch !== initEpoch) return
      isLoading.value = false
      attachLandmarkMarkers(landmarks.value)

      try {
        loadingStageLabel.value = 'Memuat pola ruang, jalan, & sungai...'
        const [waterFc, polFc, roadFc, envFc] = await Promise.all([
          fetchJsonSafe<GeoJSON.FeatureCollection>('/tiles/bojonegoro-rdtr/waterways.geojson'),
          fetchJsonSafe<GeoJSON.FeatureCollection>('/tiles/bojonegoro-rdtr/polaruang.geojson'),
          fetchJsonSafe<GeoJSON.FeatureCollection>('/tiles/bojonegoro-rdtr/roads.geojson'),
          fetchJsonSafe<GeoJSON.FeatureCollection>('/tiles/bojonegoro-rdtr/environment.geojson'),
        ])
        if (epoch !== initEpoch) return
        setSourceGeoJson('waterways', waterFc)
        setSourceGeoJson('polaruang', polFc)
        setSourceGeoJson('roads', roadFc)
        setSourceGeoJson('environment', envFc)

        loadingStageLabel.value = 'Memuat 48.080 bangunan OSM + RDTR...'
        const osmFc = await fetchJsonSafe<GeoJSON.FeatureCollection>(
          '/tiles/bojonegoro-rdtr/buildings_osm_rdtr.geojson'
        )
        if (epoch !== initEpoch) return
        setSourceGeoJson('buildings-osm', osmFc)

        loadingStageLabel.value = 'Memuat 31.004 bangunan generate RDTR...'
        const genFc = await fetchJsonSafe<GeoJSON.FeatureCollection>(
          '/tiles/bojonegoro-rdtr/buildings_generated_rdtr.geojson'
        )
        if (epoch !== initEpoch) return
        setSourceGeoJson('buildings-generated', genFc)
      } catch (err) {
        if (epoch === initEpoch) {
          loadError.value =
            err instanceof Error
              ? err.message
              : 'Gagal memuat data GeoJSON RDTR Bojonegoro.'
        }
      } finally {
        if (epoch === initEpoch) {
          isGeneratedLoading.value = false
          loadingStageLabel.value = ''
        }
      }
    })

    const interactiveLayers = [
      'buildings-osm-3d',
      'buildings-generated-3d',
      'roads-fill',
      'rail-bed',
      'road-corridor-bj-fill',
      'badan-air-fill',
      'waterways-line-fill',
      'rdtr-polaruang-fill',
    ]

    for (const layerId of interactiveLayers) {
      map.on('mouseenter', layerId, () => {
        if (map) map.getCanvas().style.cursor = 'pointer'
      })
      map.on('mouseleave', layerId, () => {
        if (map) map.getCanvas().style.cursor = ''
      })
    }

    map.on('click', (ev) => {
      if (!map) return
      const existingLayers = interactiveLayers.filter((id) => map?.getLayer(id))
      const hits = map.queryRenderedFeatures(ev.point, {
        layers: existingLayers,
      })

      if (!hits.length) {
        clearSelection()
        return
      }

      const top = hits[0]
      const layerId = top.layer.id
      const p = (top.properties ?? {}) as Record<string, unknown>
      const lngLat: [number, number] = [
        Number(ev.lngLat.lng.toFixed(5)),
        Number(ev.lngLat.lat.toFixed(5)),
      ]

      if (
        layerId === 'buildings-osm-3d' ||
        layerId === 'buildings-generated-3d'
      ) {
        const bId = String(p.id ?? '')
        if (layerId === 'buildings-osm-3d') {
          if (map.getLayer('buildings-selected-highlight')) {
            map.setFilter('buildings-selected-highlight', ['==', ['get', 'id'], bId])
          }
          if (map.getLayer('buildings-generated-highlight')) {
            map.setFilter('buildings-generated-highlight', ['==', ['get', 'id'], '__none__'])
          }
        } else {
          if (map.getLayer('buildings-selected-highlight')) {
            map.setFilter('buildings-selected-highlight', ['==', ['get', 'id'], '__none__'])
          }
          if (map.getLayer('buildings-generated-highlight')) {
            map.setFilter('buildings-generated-highlight', ['==', ['get', 'id'], bId])
          }
        }
        selectedFeature.value = {
          kind: 'building',
          title: String(p.nm || 'Bangunan RDTR'),
          subtitle: `${String(p.zn || zoneNameByCode.value[String(p.kz || '')] || 'Zona RDTR')} (${String(p.kz || '-')})`,
          badgeCode: String(p.kz || 'BLDG'),
          badgeColor: '#9A3412',
          source: String(p.src || 'RDTR 5K'),
          metrics: [
            { label: 'Ketinggian 3D', value: `${p.h ?? '-'} m` },
            { label: 'Perkiraan Lantai', value: `${p.lv ?? '-'} lantai` },
            { label: 'Luas Tapak', value: `${p.area ?? '-'} m2` },
            { label: 'Desa / Kel.', value: String(p.kd || 'Kota Bojonegoro') },
          ],
          coords: lngLat,
        }
        return
      }

      if (map.getLayer('buildings-selected-highlight')) {
        map.setFilter('buildings-selected-highlight', ['==', ['get', 'id'], '__none__'])
      }
      if (map.getLayer('buildings-generated-highlight')) {
        map.setFilter('buildings-generated-highlight', ['==', ['get', 'id'], '__none__'])
      }

      if (
        layerId === 'roads-fill' ||
        layerId === 'road-corridor-bj-fill' ||
        layerId === 'rail-bed'
      ) {
        selectedFeature.value = {
          kind: 'road',
          title: String(p.nm || 'Ruas Jalan Bojonegoro'),
          subtitle: String(p.cls || 'Jaringan Transportasi RDTR'),
          badgeCode: p.cls === 'rail' ? 'KAI' : `${p.w ?? '-'}m`,
          badgeColor: '#353F4B',
          source: String(p.src || 'RDTR 5K'),
          metrics: [
            { label: 'Lebar Koridor', value: `${p.w ?? '-'} meter` },
            { label: 'Hirarki', value: String(p.cls || '-') },
            { label: 'Jembatan', value: p.br ? 'Ya' : 'Tidak' },
          ],
          coords: lngLat,
        }
        return
      }

      if (layerId === 'badan-air-fill' || layerId === 'waterways-line-fill') {
        selectedFeature.value = {
          kind: 'water',
          title: String(p.nm || 'Badan Air Bojonegoro'),
          subtitle: 'Sistem Sungai & Drainase RDTR',
          badgeCode: 'BA',
          badgeColor: '#0369A1',
          source: String(p.src || 'RDTR 5K'),
          metrics: [
            {
              label: p.w ? 'Lebar Saluran' : 'Luas Zona Air',
              value: p.w ? `${p.w} meter` : `${p.ha ?? '-'} ha`,
            },
            { label: 'Desa / Kel.', value: String(p.kd || 'Bojonegoro') },
          ],
          coords: lngLat,
        }
        return
      }

      selectedFeature.value = {
        kind: 'zone',
        title: String(p.nm || 'Zona Pola Ruang RDTR'),
        subtitle: `Kode Zona ${String(p.kz || '-')}`,
        badgeCode: String(p.kz || 'RDTR'),
        badgeColor: '#047857',
        source: 'RDTR Pola Ruang 1:5.000',
        metrics: [
          { label: 'Kategori', value: String(p.cat || '-') },
          { label: 'Luas Poligon', value: `${p.ha ?? '-'} ha` },
          { label: 'Desa / Kel.', value: String(p.kd || '-') },
          { label: 'Kecamatan', value: String(p.kc || '-') },
        ],
        coords: lngLat,
      }
    })
  } catch (err) {
    loadError.value =
      err instanceof Error ? err.message : 'Gagal memuat data spasial RDTR.'
    isLoading.value = false
    isGeneratedLoading.value = false
  }
}

function handleKeyDown(ev: KeyboardEvent) {
  const tag = (ev.target as HTMLElement | null)?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA') return
  if (ev.key === '+' || ev.key === '=') {
    zoomBy(0.6)
  } else if (ev.key === '-') {
    zoomBy(-0.6)
  } else if (ev.key === 'r' || ev.key === 'R') {
    resetNorth3DView()
  } else if (ev.key === 'Escape') {
    clearSelection()
    isDrawerOpen.value = false
  }
}

onMounted(() => {
  initMap()
})

onBeforeUnmount(() => {
  initEpoch += 1
  for (const m of activeMarkers) m.remove()
  activeMarkers.length = 0
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <div
    class="relative h-full w-full overflow-hidden bg-[#EFEAE0] text-slate-900 select-none"
    tabindex="0"
    role="application"
    aria-label="Peta 3D RDTR Kota Bojonegoro dengan MapLibre GL JS"
    @keydown="handleKeyDown"
  >
    <!-- MapLibre WebGL Canvas -->
    <div ref="mapContainerRef" class="h-full w-full" />

    <!-- Top Navigation & Quick Mode Bar (positioned below fixed PublicNavbar) -->
    <header
      class="pointer-events-none absolute inset-x-0 top-[62px] z-20 flex flex-wrap items-start justify-between gap-2.5 p-3 md:p-4 lg:top-[76px]"
    >
      <div
        class="pointer-events-auto flex max-w-full flex-wrap items-center gap-2 rounded-xl border border-slate-300 bg-white/95 px-2.5 py-2 shadow-lg backdrop-blur-sm"
      >
        <NuxtLink
          to="/maps"
          class="inline-flex min-h-[44px] min-w-[44px] items-center gap-1.5 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 transition hover:border-emerald-700 hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          aria-label="Kembali ke WebGIS 2D MELAROSA"
        >
          <span aria-hidden="true">&larr;</span>
          <span>WebGIS 2D</span>
        </NuxtLink>

        <div class="min-w-0 px-1">
          <h1 class="truncate text-sm font-bold tracking-tight text-slate-900 md:text-base">
            Peta 3D RDTR Bojonegoro
          </h1>
          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 tabular-nums">
            <span>MapLibre GL JS (OSM + RDTR 1:5K)</span>
            <span
              v-if="totalBuildingsCount > 0"
              class="rounded bg-emerald-800 px-1.5 py-0.5 text-[11px] font-semibold text-white"
            >
              {{ totalBuildingsCount.toLocaleString('id-ID') }} bangunan
            </span>
            <span
              v-if="isGeneratedLoading && loadingStageLabel"
              class="rounded border border-amber-700 bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900"
            >
              {{ loadingStageLabel }}
            </span>
          </div>
        </div>

        <button
          v-if="props.showPixelSwitch"
          type="button"
          class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 transition hover:border-slate-500 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          @click="emit('switch-pixel')"
        >
          <span>Piksel Tile</span>
        </button>

        <button
          type="button"
          class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-900 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          :aria-expanded="isDrawerOpen"
          aria-controls="rdtr-control-drawer"
          @click="isDrawerOpen = !isDrawerOpen"
        >
          <span>{{ isDrawerOpen ? 'Tutup Layer' : 'Layer & Legenda RDTR' }}</span>
        </button>
      </div>

      <!-- Desktop Compass & Camera Orientation Badge -->
      <div
        class="pointer-events-auto flex items-center gap-2 rounded-xl border border-slate-300 bg-white/95 px-2.5 py-1.5 shadow-lg backdrop-blur-sm"
      >
        <button
          type="button"
          class="flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-slate-200 bg-slate-50 transition hover:border-emerald-700 hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          :title="`Reset arah utara (${northOrientationLabel})`"
          aria-label="Reset orientasi arah utara miring kiri 3D"
          @click="resetOrientationOnly"
        >
          <svg
            viewBox="0 0 36 36"
            class="h-7 w-7 transition-transform duration-150"
            :style="{ transform: `rotate(${-cameraBearing}deg)` }"
            aria-hidden="true"
          >
            <circle cx="18" cy="18" r="16" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.5" />
            <polygon points="18,4 23,18 18,15 13,18" fill="#B91C1C" />
            <polygon points="18,32 23,18 18,21 13,18" fill="#64748B" />
            <text
              x="18"
              y="14"
              text-anchor="middle"
              font-size="7"
              font-weight="700"
              fill="#FFFFFF"
            >
              U
            </text>
          </svg>
        </button>

        <div class="hidden pr-1 text-xs md:block">
          <div class="font-semibold text-slate-900 tabular-nums">
            {{ northOrientationLabel }}
          </div>
          <div class="text-[11px] text-slate-600 tabular-nums">
            Sudut 3D {{ Math.round(cameraPitch) }}° · Skala {{ formattedScaleHint }}
          </div>
        </div>
      </div>
    </header>

    <!-- Collapsible Layer, Search & RDTR Legend Panel -->
    <aside
      v-if="isDrawerOpen"
      id="rdtr-control-drawer"
      class="pointer-events-auto absolute top-[154px] left-3 z-30 flex max-h-[calc(100%-235px)] w-[calc(100vw-24px)] max-w-sm flex-col overflow-hidden rounded-xl border border-slate-300 bg-white/95 shadow-2xl backdrop-blur-sm md:left-4 lg:top-[168px]"
      aria-label="Kontrol Layer dan Legenda RDTR"
    >
      <div class="border-b border-slate-200 p-3">
        <label for="rdtr-search-input" class="sr-only">
          Cari lokasi penting atau kode zona RDTR
        </label>
        <input
          id="rdtr-search-input"
          v-model="searchQuery"
          type="search"
          placeholder="Cari gedung, stasiun, atau kode zona (K-1, R-2, P-1)..."
          class="min-h-[44px] w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-900 placeholder:text-slate-500 focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20 focus:outline-none"
        />
      </div>

      <div class="flex-1 space-y-4 overflow-y-auto p-3 text-xs">
        <!-- Quick Layers -->
        <section>
          <h2 class="mb-2 text-[11px] font-bold tracking-wide text-slate-600 uppercase">
            Lapisan Peta 3D
          </h2>
          <div class="grid grid-cols-1 gap-1.5">
            <button
              type="button"
              class="flex min-h-[44px] items-center justify-between rounded-lg border px-3 py-2 text-left font-medium transition"
              :class="
                showOsmBuildings
                  ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                  : 'border-slate-200 bg-white text-slate-700'
              "
              @click="toggleOsmBuildings"
            >
              <span>Bangunan Eksisting OSM + RDTR</span>
              <span class="font-bold">{{ showOsmBuildings ? 'Aktif' : 'Mati' }}</span>
            </button>

            <button
              type="button"
              class="flex min-h-[44px] items-center justify-between rounded-lg border px-3 py-2 text-left font-medium transition"
              :class="
                showGeneratedBuildings
                  ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                  : 'border-slate-200 bg-white text-slate-700'
              "
              @click="toggleGeneratedBuildings"
            >
              <span>Bangunan Generate RDTR 5K</span>
              <span class="font-bold">{{ showGeneratedBuildings ? 'Aktif' : 'Mati' }}</span>
            </button>

            <button
              type="button"
              class="flex min-h-[44px] items-center justify-between rounded-lg border px-3 py-2 text-left font-medium transition"
              :class="
                useOfficialRdtrColors
                  ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                  : 'border-slate-200 bg-white text-slate-700'
              "
              @click="toggleOfficialRdtrColors"
            >
              <span>Warna Resmi Pola Ruang SLD RDTR</span>
              <span class="font-bold">{{ useOfficialRdtrColors ? 'Resmi SLD' : 'Natural' }}</span>
            </button>

            <button
              type="button"
              class="flex min-h-[44px] items-center justify-between rounded-lg border px-3 py-2 text-left font-medium transition"
              :class="
                showLiveWms
                  ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                  : 'border-slate-200 bg-white text-slate-700'
              "
              @click="toggleLiveWms"
            >
              <span>Overlay WMS Live Geoportal</span>
              <span class="font-bold">{{ showLiveWms ? 'Aktif' : 'Mati' }}</span>
            </button>

            <div class="grid grid-cols-2 gap-1.5">
              <button
                type="button"
                class="flex min-h-[44px] items-center justify-between rounded-lg border px-2.5 py-2 font-medium transition"
                :class="
                  showPolaruang
                    ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                    : 'border-slate-200 bg-white text-slate-700'
                "
                @click="togglePolaruang"
              >
                <span>Zonasi</span>
                <span class="font-bold">{{ showPolaruang ? 'On' : 'Off' }}</span>
              </button>

              <button
                type="button"
                class="flex min-h-[44px] items-center justify-between rounded-lg border px-2.5 py-2 font-medium transition"
                :class="
                  showRoads
                    ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                    : 'border-slate-200 bg-white text-slate-700'
                "
                @click="toggleRoads"
              >
                <span>Jalan & Rel</span>
                <span class="font-bold">{{ showRoads ? 'On' : 'Off' }}</span>
              </button>

              <button
                type="button"
                class="flex min-h-[44px] items-center justify-between rounded-lg border px-2.5 py-2 font-medium transition"
                :class="
                  showEnvironment
                    ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                    : 'border-slate-200 bg-white text-slate-700'
                "
                @click="toggleEnvironment"
              >
                <span>Sungai & RTH</span>
                <span class="font-bold">{{ showEnvironment ? 'On' : 'Off' }}</span>
              </button>

              <button
                type="button"
                class="flex min-h-[44px] items-center justify-between rounded-lg border px-2.5 py-2 font-medium transition"
                :class="
                  showLandmarks
                    ? 'border-emerald-700 bg-emerald-50 text-slate-900'
                    : 'border-slate-200 bg-white text-slate-700'
                "
                @click="toggleLandmarks"
              >
                <span>Pin Lokasi</span>
                <span class="font-bold">{{ showLandmarks ? 'On' : 'Off' }}</span>
              </button>
            </div>
          </div>
        </section>

        <!-- Landmarks Filter List -->
        <section>
          <h2 class="mb-2 text-[11px] font-bold tracking-wide text-slate-600 uppercase">
            Titik Fokus & Landmark ({{ filteredLandmarks.length }})
          </h2>
          <p
            v-if="filteredLandmarks.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-600"
          >
            Tidak ada landmark yang cocok dengan pencarian Anda.
          </p>
          <div v-else class="space-y-1">
            <button
              v-for="item in filteredLandmarks"
              :key="item.id"
              type="button"
              class="flex min-h-[44px] w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-left transition hover:border-emerald-700 hover:bg-emerald-50"
              @click="flyToLandmark(item)"
            >
              <div class="min-w-0 pr-2">
                <div class="truncate font-semibold text-slate-900">{{ item.nm }}</div>
                <div class="truncate text-[11px] text-slate-600">{{ item.desc }}</div>
              </div>
              <span class="shrink-0 rounded bg-slate-800 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                {{ item.kz }}
              </span>
            </button>
          </div>
        </section>

        <!-- RDTR Polaruang Summary -->
        <section v-if="summary">
          <h2 class="mb-2 text-[11px] font-bold tracking-wide text-slate-600 uppercase">
            Statistik Pola Ruang 1:5K ({{ summary.counts.rdtrPolygons.toLocaleString('id-ID') }} poligon)
          </h2>
          <p
            v-if="filteredZoneStats.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-600"
          >
            Tidak ada kode zona RDTR yang cocok.
          </p>
          <div v-else class="space-y-1">
            <div
              v-for="z in filteredZoneStats"
              :key="z.kz"
              class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5"
            >
              <div class="flex min-w-0 items-center gap-2 pr-2">
                <span
                  class="h-3.5 w-3.5 shrink-0 rounded-sm border border-slate-400"
                  :style="{ backgroundColor: z.col }"
                  aria-hidden="true"
                />
                <span class="truncate font-medium text-slate-900">{{ z.namobj }}</span>
              </div>
              <span class="shrink-0 text-[11px] font-semibold text-slate-700 tabular-nums">
                {{ z.kz }} · {{ z.areaHa.toLocaleString('id-ID') }} ha
              </span>
            </div>
          </div>
        </section>

        <!-- Road Width & Network Stats -->
        <section v-if="roadClassEntries.length">
          <h2 class="mb-2 text-[11px] font-bold tracking-wide text-slate-600 uppercase">
            Panjang Jaringan Jalan & Rel RDTR
          </h2>
          <div class="space-y-1">
            <div
              v-for="r in roadClassEntries"
              :key="r.key"
              class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5"
            >
              <span class="text-slate-800">{{ r.label }}</span>
              <span class="font-semibold text-slate-900 tabular-nums">{{ r.km }} km</span>
            </div>
          </div>
        </section>
      </div>
    </aside>

    <!-- Bottom Camera Presets & Zoom/Rotate Toolbar -->
    <nav
      class="pointer-events-none absolute inset-x-0 bottom-3 z-20 flex flex-wrap items-end justify-between gap-2.5 px-3 md:bottom-4 md:px-4"
      aria-label="Navigasi kamera dan fokus wilayah RDTR"
    >
      <!-- Area Presets -->
      <div
        class="pointer-events-auto flex max-w-full items-center gap-1.5 overflow-x-auto rounded-xl border border-slate-300 bg-white/95 p-1.5 shadow-lg backdrop-blur-sm"
      >
        <button
          v-for="preset in CAMERA_PRESETS"
          :key="preset.id"
          type="button"
          class="min-h-[44px] shrink-0 rounded-lg px-3 py-1.5 text-left text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          :class="
            activePresetId === preset.id
              ? 'bg-emerald-800 text-white'
              : 'bg-slate-100 text-slate-800 hover:bg-slate-200'
          "
          @click="flyToPreset(preset)"
        >
          <div>{{ preset.label }}</div>
          <div
            class="hidden text-[10px] font-normal md:block"
            :class="activePresetId === preset.id ? 'text-emerald-100' : 'text-slate-600'"
          >
            {{ preset.subtitle }}
          </div>
        </button>
      </div>

      <!-- Camera Controls -->
      <div
        class="pointer-events-auto flex items-center gap-1 rounded-xl border border-slate-300 bg-white/95 p-1 shadow-lg backdrop-blur-sm"
      >
        <button
          type="button"
          class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg px-2.5 text-xs font-bold text-slate-900 transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          aria-label="Perbesar peta"
          @click="zoomBy(0.7)"
        >
          +
        </button>
        <button
          type="button"
          class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg px-2.5 text-xs font-bold text-slate-900 transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          aria-label="Perkecil peta"
          @click="zoomBy(-0.7)"
        >
          -
        </button>
        <button
          type="button"
          class="inline-flex min-h-[44px] items-center justify-center rounded-lg px-2.5 text-xs font-semibold text-slate-900 transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          aria-label="Putar kiri 10 derajat"
          @click="rotateBearingBy(-10)"
        >
          Putar Kiri
        </button>
        <button
          type="button"
          class="inline-flex min-h-[44px] items-center justify-center rounded-lg px-2.5 text-xs font-semibold text-slate-900 transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          aria-label="Putar kanan 10 derajat"
          @click="rotateBearingBy(10)"
        >
          Putar Kanan
        </button>
        <button
          type="button"
          class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          @click="togglePitch2D3D"
        >
          {{ cameraPitch > 25 ? 'Mode 2D' : 'Mode 3D' }}
        </button>
      </div>
    </nav>

    <!-- Selected Feature Inspector Card -->
    <section
      v-if="selectedFeature"
      class="pointer-events-auto absolute right-3 bottom-20 z-30 w-[calc(100vw-24px)] max-w-xs rounded-xl border border-slate-300 bg-white/95 p-3.5 shadow-2xl backdrop-blur-sm md:right-4 md:bottom-auto md:top-[168px]"
      aria-label="Detail objek RDTR terpilih"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-1.5">
            <span
              class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold text-white"
              :style="{ backgroundColor: selectedFeature.badgeColor }"
            >
              {{ selectedFeature.badgeCode }}
            </span>
            <span class="text-[11px] font-semibold text-slate-600">
              {{ selectedFeature.source }}
            </span>
          </div>
          <h2 class="mt-1 text-sm font-bold text-slate-900">
            {{ selectedFeature.title }}
          </h2>
          <p class="text-xs text-slate-700">
            {{ selectedFeature.subtitle }}
          </p>
        </div>

        <button
          type="button"
          class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-slate-200 text-xs font-bold text-slate-700 transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:outline-none"
          aria-label="Tutup panel detail"
          @click="clearSelection"
        >
          Tutup
        </button>
      </div>

      <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-200 pt-2.5 text-xs">
        <div v-for="m in selectedFeature.metrics" :key="m.label">
          <dt class="text-[11px] text-slate-600">{{ m.label }}</dt>
          <dd class="font-semibold text-slate-900 tabular-nums">{{ m.value }}</dd>
        </div>
      </dl>

      <div class="mt-2.5 border-t border-slate-200 pt-2 text-[11px] text-slate-600 tabular-nums">
        Koordinat: {{ selectedFeature.coords[1].toFixed(5) }}, {{ selectedFeature.coords[0].toFixed(5) }}
      </div>
    </section>

    <!-- Initial Loading State -->
    <div
      v-if="isLoading"
      class="absolute inset-0 z-40 flex items-center justify-center bg-[#EFEAE0]/90 backdrop-blur-sm"
      role="status"
      aria-live="polite"
    >
      <div class="rounded-xl border border-slate-300 bg-white px-5 py-4 text-center shadow-lg">
        <p class="text-sm font-bold text-slate-900">Memuat Model 3D RDTR Bojonegoro</p>
        <p class="mt-1 text-xs text-slate-600">
          Menyiapkan bangunan, jaringan jalan, sungai, RTH, dan pertanian 1:5.000...
        </p>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="loadError"
      class="absolute inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
      role="alert"
    >
      <div class="max-w-sm rounded-xl border border-red-300 bg-white p-5 shadow-2xl">
        <h2 class="text-sm font-bold text-red-800">Peta 3D RDTR Gagal Dimuat</h2>
        <p class="mt-1 text-xs text-slate-700">{{ loadError }}</p>
        <button
          type="button"
          class="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-lg bg-emerald-800 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-900"
          @click="initMap"
        >
          Muat Ulang Peta
        </button>
      </div>
    </div>
  </div>
</template>

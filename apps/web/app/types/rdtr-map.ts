export type RdtrZoneCategory =
  | 'perumahan'
  | 'komersial'
  | 'perkantoran'
  | 'fasum'
  | 'industri'
  | 'hankam'
  | 'rth'
  | 'pertanian'
  | 'perikanan'
  | 'sempadan'
  | 'air'
  | 'jalan'
  | 'khusus'

export interface RdtrZoneStat {
  kz: string
  namobj: string
  cat: RdtrZoneCategory
  col: string
  mcol: string
  polygons: number
  areaHa: number
}

export interface RdtrCameraPreset {
  id: string
  label: string
  subtitle: string
  center: [number, number]
  zoom: number
  pitch: number
  bearing: number
}

export interface RdtrSummary {
  generatedAt: string
  dataSource: {
    wmsUrl: string
    wmsLayer: string
    transportLayer: string
    osmSource: string
    postgisSource: string
  }
  defaultCamera: {
    center: [number, number]
    zoom: number
    pitch: number
    bearing: number
    orientationNote: string
  }
  extent: {
    rdtrBbox: [number, number, number, number]
    osmCoreBbox: [number, number, number, number]
  }
  counts: {
    rdtrPolygons: number
    badanJalanPolygons: number
    badanAirPolygons: number
    roadAndRailLines: number
    waterLinesAndPonds: number
    sawahGridLines: number
    trees3d: number
    osmRdtrBuildings: number
    rdtrGeneratedBuildings: number
    osmFilteredInBadanAir: number
    generatedByKecamatan: Record<string, number>
  }
  roadKmByClass: Record<string, number>
  zoneStats: RdtrZoneStat[]
  files: Record<string, { features: number; sizeKB: number }>
  elapsedSeconds: number
}

export interface RdtrSelectedFeature {
  kind: 'building' | 'road' | 'water' | 'zone' | 'landmark'
  title: string
  subtitle: string
  badgeCode: string
  badgeColor: string
  source: string
  metrics: Array<{ label: string; value: string }>
  coords: [number, number]
}

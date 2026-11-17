export interface GeographicCoord {
  lng: number
  lat: number
}

export interface ProjectedCoord {
  easting: number
  northing: number
}

export interface IsometricCoord {
  isoX: number
  isoY: number
}

export interface PixelCoord {
  px: number
  py: number
}

export interface IsometricGroundAnchor {
  geographic: [number, number]
  projected: [number, number]
  isometric: [number, number]
}

export interface IsometricZoomSpec {
  z: number
  cols: number
  rows: number
  widthPx: number
  heightPx: number
  pixelResolution: number
  scale: number
}

export interface IsometricTransformationParameters {
  originEasting: number
  originNorthing: number
  originLng: number
  originLat: number
  scaleFactor: number
  isoRatio: number
  rotationAngleDeg: number
  minIsoX: number
  minIsoY: number
  maxIsoX: number
  maxIsoY: number
  basePixelResolution: number
  verticalExaggeration: number
}

export interface IsometricCoordinateSample {
  name: string
  geographic: GeographicCoord
  projectedEpsg32749: ProjectedCoord
  isometric: IsometricCoord
  pixelZ4?: PixelCoord
  pixelZ3?: PixelCoord
  inverseGeographic: GeographicCoord
  meterError: number
  degreeError: number
}

export interface IsometricTileMetadata {
  projection: {
    geographicSRS: string
    projectedSRS: string
    name: string
    utmZone: string
    centralMeridian: number
    falseEasting: number
    falseNorthing: number
    scaleFactor: number
  }
  originCoordinate: {
    geographic: [number, number]
    projected: [number, number]
  }
  scaleFactor: number
  isometricTileWidth: number
  isometricTileHeight: number
  rotationAngle: number
  verticalExaggeration: number
  pixelResolution: number
  resolutionPerZoom: Record<string, number>
  scalePerZoom: Record<string, number>
  tileSize: number
  minZoom: number
  maxZoom: number
  zoomLevels: IsometricZoomSpec[]
  geographicExtent: {
    minLng: number
    minLat: number
    maxLng: number
    maxLat: number
  }
  projectedExtent: {
    minEasting: number
    minNorthing: number
    maxEasting: number
    maxNorthing: number
  }
  isometricExtent: {
    minIsoX: number
    minIsoY: number
    maxIsoX: number
    maxIsoY: number
  }
  coordinateTransformationParameters: IsometricTransformationParameters
  tileIndexingScheme: {
    type: string
    origin: string
    urlTemplate: string
    format: string
    metatileOverlapPx: number
    description: string
  }
  generatedAt: string
  masterPreviewUrl: string
  featuresIndexUrl: string
  datasetStats: {
    data_source?: string
    desa_count: number
    landcover_count?: number
    water_polygon_count?: number
    river_count: number
    park_count: number
    road_count: number
    rail_count?: number
    postgis_poros_count: number
    postgis_lingkungan_count: number
    infrastructure_count: number
    tree_count?: number
    building_count: number
    landmark_count: number
  }
  coordinateVerification: {
    passed: boolean
    maxMeterError: number
    maxDegreeError: number
    samples: IsometricCoordinateSample[]
  }
  colorPalette: Record<string, string>
}

export interface IsometricLandmarkFeature {
  landmark_id: string
  name: string
  category: string
  desa: string
  building_id?: string
  park_id?: string
  road_id?: string
  ground_anchor: IsometricGroundAnchor
}

export interface IsometricDesaBoundaryFeature {
  id: string
  desaId: number
  name: string
  luasHektar: number
  anchor: IsometricGroundAnchor
  isoRing: [number, number][]
}

export interface IsometricRiverFeature {
  id: string
  name: string
  kind: string
  widthM: number
  anchor: IsometricGroundAnchor
  isoBbox?: [number, number, number, number]
  isoCenterline: [number, number][]
}

export interface IsometricParkFeature {
  id: string
  name: string
  kind: string
  desa: string
  anchor: IsometricGroundAnchor
  isoBbox: [number, number, number, number]
  isoRing: [number, number][]
}

export interface IsometricRoadFeature {
  id: string
  name: string
  roadClass: string
  surface: string
  condition: string
  widthM: number
  desa: string
  source: string
  anchor: IsometricGroundAnchor
  isoBbox: [number, number, number, number]
  isoLine: [number, number][]
}

export interface IsometricInfraFeature {
  id: string
  code: string
  name: string
  category: string
  condition: string
  desa: string
  sourceTable: string
  tahun?: number | string | null
  sumberDana?: string | null
  anchor: IsometricGroundAnchor
  isoBbox: [number, number, number, number]
  isoLine: [number, number][]
}

export interface IsometricBuildingFeature {
  id: string
  name: string
  kind: string
  roofStyle: string
  levels: number
  heightM: number
  desa: string
  address?: string | null
  anchorIso: [number, number]
  isoBbox: [number, number, number, number]
  flatIsoRing: number[]
}

export interface IsometricSpatialIndexPayload {
  region: string
  crs: string
  stats: IsometricTileMetadata['datasetStats']
  landmarks: IsometricLandmarkFeature[]
  desaBoundaries: IsometricDesaBoundaryFeature[]
  rivers: IsometricRiverFeature[]
  parks: IsometricParkFeature[]
  roads: IsometricRoadFeature[]
  infrastructure: IsometricInfraFeature[]
  buildings: IsometricBuildingFeature[]
}

export interface VisibleTileDescriptor {
  key: string
  z: number
  x: number
  y: number
  url: string
  screenX: number
  screenY: number
  screenWidth: number
  screenHeight: number
}

export interface IsometricInspectResult {
  pixel: PixelCoord
  zoom: number
  isometric: IsometricCoord
  projected: ProjectedCoord
  geographic: GeographicCoord
  desa: IsometricDesaBoundaryFeature | null
  building: IsometricBuildingFeature | null
  buildingIsoRing: [number, number][] | null
  road: IsometricRoadFeature | null
  infrastructure: IsometricInfraFeature | null
  park: IsometricParkFeature | null
  river: IsometricRiverFeature | null
  landmark: IsometricLandmarkFeature | null
  distanceMeters: number | null
}

export interface IsometricOverlayOptions {
  showLandmarks: boolean
  showDesaLabels: boolean
  showPostgisInfra: boolean
  showTileGrid: boolean
  showSelectionHighlight: boolean
}

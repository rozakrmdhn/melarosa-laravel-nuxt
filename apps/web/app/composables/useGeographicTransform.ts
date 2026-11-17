import type {
  GeographicCoord,
  IsometricCoord,
  IsometricTransformationParameters,
  PixelCoord,
  ProjectedCoord,
} from '~/types/isometric'

const WGS84_A = 6378137.0
const WGS84_F = 1.0 / 298.257223563
const WGS84_K0 = 0.9996
const UTM49S_LON0_DEG = 111.0
const UTM49S_LON0_RAD = (UTM49S_LON0_DEG * Math.PI) / 180.0
const UTM49S_FALSE_EASTING = 500000.0
const UTM49S_FALSE_NORTHING = 10000000.0

// Krueger 4th-order Transverse Mercator coefficients for EPSG:32749
const N = WGS84_F / (2.0 - WGS84_F)
const N2 = N * N
const N3 = N2 * N
const N4 = N3 * N
const RECT_A = (WGS84_A / (1.0 + N)) * (1.0 + N2 / 4.0 + N4 / 64.0)

const ALPHA1 = 0.5 * N - (2.0 / 3.0) * N2 + (5.0 / 16.0) * N3 + (41.0 / 180.0) * N4
const ALPHA2 = (13.0 / 48.0) * N2 - (3.0 / 5.0) * N3 + (557.0 / 1440.0) * N4
const ALPHA3 = (61.0 / 240.0) * N3 - (103.0 / 140.0) * N4
const ALPHA4 = (49561.0 / 161280.0) * N4

const BETA1 = 0.5 * N - (2.0 / 3.0) * N2 + (37.0 / 96.0) * N3 - (1.0 / 360.0) * N4
const BETA2 = (1.0 / 48.0) * N2 + (1.0 / 15.0) * N3 - (437.0 / 1440.0) * N4
const BETA3 = (17.0 / 480.0) * N3 - (37.0 / 840.0) * N4
const BETA4 = (4397.0 / 161280.0) * N4

const DELTA1 = 2.0 * N - (2.0 / 3.0) * N2 - 2.0 * N3 + (116.0 / 45.0) * N4
const DELTA2 = (7.0 / 3.0) * N2 - (8.0 / 5.0) * N3 - (227.0 / 45.0) * N4
const DELTA3 = (56.0 / 15.0) * N3 - (136.0 / 35.0) * N4
const DELTA4 = (4279.0 / 630.0) * N4

export const DEFAULT_TRANSFORM_PARAMS: IsometricTransformationParameters = {
  originEasting: 598692.59,
  originNorthing: 9208609.62,
  originLng: 111.893824,
  originLat: -7.158704,
  scaleFactor: 1.0,
  isoRatio: 0.5,
  rotationAngleDeg: 0.0,
  minIsoX: -7680.0,
  minIsoY: -3840.0,
  maxIsoX: 7680.0,
  maxIsoY: 3840.0,
  basePixelResolution: 30.0,
  verticalExaggeration: 1.45,
}

export function scaleForZoom(
  zoom: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
): number {
  const resolution = params.basePixelResolution / Math.pow(2.0, zoom)
  return 1.0 / resolution
}

export function geographicToProjected(lng: number, lat: number): ProjectedCoord {
  const phi = (lat * Math.PI) / 180.0
  const lam = (lng * Math.PI) / 180.0 - UTM49S_LON0_RAD

  const sinPhi = Math.sin(phi)
  const k = (2.0 * Math.sqrt(N)) / (1.0 + N)
  const t = Math.sinh(Math.atanh(sinPhi) - k * Math.atanh(k * sinPhi))
  const xiP = Math.atan2(t, Math.cos(lam))
  const etaP = Math.atanh(Math.sin(lam) / Math.sqrt(1.0 + t * t))

  const xi =
    xiP +
    ALPHA1 * Math.sin(2.0 * xiP) * Math.cosh(2.0 * etaP) +
    ALPHA2 * Math.sin(4.0 * xiP) * Math.cosh(4.0 * etaP) +
    ALPHA3 * Math.sin(6.0 * xiP) * Math.cosh(6.0 * etaP) +
    ALPHA4 * Math.sin(8.0 * xiP) * Math.cosh(8.0 * etaP)

  const eta =
    etaP +
    ALPHA1 * Math.cos(2.0 * xiP) * Math.sinh(2.0 * etaP) +
    ALPHA2 * Math.cos(4.0 * xiP) * Math.sinh(4.0 * etaP) +
    ALPHA3 * Math.cos(6.0 * xiP) * Math.sinh(6.0 * etaP) +
    ALPHA4 * Math.cos(8.0 * xiP) * Math.sinh(8.0 * etaP)

  return {
    easting: UTM49S_FALSE_EASTING + WGS84_K0 * RECT_A * eta,
    northing: UTM49S_FALSE_NORTHING + WGS84_K0 * RECT_A * xi,
  }
}

export function projectedToGeographic(easting: number, northing: number): GeographicCoord {
  const xi = (northing - UTM49S_FALSE_NORTHING) / (WGS84_K0 * RECT_A)
  const eta = (easting - UTM49S_FALSE_EASTING) / (WGS84_K0 * RECT_A)

  const xiP =
    xi -
    BETA1 * Math.sin(2.0 * xi) * Math.cosh(2.0 * eta) -
    BETA2 * Math.sin(4.0 * xi) * Math.cosh(4.0 * eta) -
    BETA3 * Math.sin(6.0 * xi) * Math.cosh(6.0 * eta) -
    BETA4 * Math.sin(8.0 * xi) * Math.cosh(8.0 * eta)

  const etaP =
    eta -
    BETA1 * Math.cos(2.0 * xi) * Math.sinh(2.0 * eta) -
    BETA2 * Math.cos(4.0 * xi) * Math.sinh(4.0 * eta) -
    BETA3 * Math.cos(6.0 * xi) * Math.sinh(6.0 * eta) -
    BETA4 * Math.cos(8.0 * xi) * Math.sinh(8.0 * eta)

  const chi = Math.asin(Math.sin(xiP) / Math.cosh(etaP))
  const phi =
    chi +
    DELTA1 * Math.sin(2.0 * chi) +
    DELTA2 * Math.sin(4.0 * chi) +
    DELTA3 * Math.sin(6.0 * chi) +
    DELTA4 * Math.sin(8.0 * chi)

  const lam = UTM49S_LON0_RAD + Math.atan2(Math.sinh(etaP), Math.cos(xiP))

  return {
    lng: (lam * 180.0) / Math.PI,
    lat: (phi * 180.0) / Math.PI,
  }
}

export function projectedToIsometric(
  easting: number,
  northing: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
): IsometricCoord {
  const de = easting - params.originEasting
  const dn = northing - params.originNorthing

  const theta = (params.rotationAngleDeg * Math.PI) / 180.0
  const cosT = Math.cos(theta)
  const sinT = Math.sin(theta)

  const u = de * cosT - dn * sinT
  const v = de * sinT + dn * cosT

  return {
    isoX: (u + v) * params.scaleFactor,
    isoY: (u - v) * params.isoRatio * params.scaleFactor,
  }
}

export function isometricToProjected(
  isoX: number,
  isoY: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
): ProjectedCoord {
  const a = isoX / params.scaleFactor
  const b = isoY / (params.isoRatio * params.scaleFactor)

  const u = 0.5 * (a + b)
  const v = 0.5 * (a - b)

  const theta = (params.rotationAngleDeg * Math.PI) / 180.0
  const cosT = Math.cos(theta)
  const sinT = Math.sin(theta)

  const de = u * cosT + v * sinT
  const dn = -u * sinT + v * cosT

  return {
    easting: params.originEasting + de,
    northing: params.originNorthing + dn,
  }
}

export function isometricToPixel(
  isoX: number,
  isoY: number,
  zoom: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
  elevationM: number = 0.0,
): PixelCoord {
  const scale = scaleForZoom(zoom, params)
  return {
    px: (isoX - params.minIsoX) * scale,
    py: (isoY - params.minIsoY - elevationM * params.verticalExaggeration) * scale,
  }
}

export function pixelToIsometric(
  px: number,
  py: number,
  zoom: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
): IsometricCoord {
  const scale = scaleForZoom(zoom, params)
  return {
    isoX: params.minIsoX + px / scale,
    isoY: params.minIsoY + py / scale,
  }
}

export function pixelToGeographic(
  px: number,
  py: number,
  zoom: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
): GeographicCoord {
  const { isoX, isoY } = pixelToIsometric(px, py, zoom, params)
  const { easting, northing } = isometricToProjected(isoX, isoY, params)
  return projectedToGeographic(easting, northing)
}

export function geographicToPixel(
  lng: number,
  lat: number,
  zoom: number,
  params: IsometricTransformationParameters = DEFAULT_TRANSFORM_PARAMS,
  elevationM: number = 0.0,
): PixelCoord {
  const { easting, northing } = geographicToProjected(lng, lat)
  const { isoX, isoY } = projectedToIsometric(easting, northing, params)
  return isometricToPixel(isoX, isoY, zoom, params, elevationM)
}

export function useGeographicTransform(
  getParams?: () => IsometricTransformationParameters,
) {
  const resolveParams = () => (getParams ? getParams() : DEFAULT_TRANSFORM_PARAMS)

  return {
    DEFAULT_TRANSFORM_PARAMS,
    scaleForZoom: (zoom: number) => scaleForZoom(zoom, resolveParams()),
    geographicToProjected,
    projectedToGeographic,
    projectedToIsometric: (easting: number, northing: number) =>
      projectedToIsometric(easting, northing, resolveParams()),
    isometricToProjected: (isoX: number, isoY: number) =>
      isometricToProjected(isoX, isoY, resolveParams()),
    isometricToPixel: (isoX: number, isoY: number, zoom: number, elevationM = 0.0) =>
      isometricToPixel(isoX, isoY, zoom, resolveParams(), elevationM),
    pixelToIsometric: (px: number, py: number, zoom: number) =>
      pixelToIsometric(px, py, zoom, resolveParams()),
    pixelToGeographic: (px: number, py: number, zoom: number) =>
      pixelToGeographic(px, py, zoom, resolveParams()),
    geographicToPixel: (lng: number, lat: number, zoom: number, elevationM = 0.0) =>
      geographicToPixel(lng, lat, zoom, resolveParams(), elevationM),
  }
}

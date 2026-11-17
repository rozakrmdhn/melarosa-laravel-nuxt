import { computed, reactive, ref } from 'vue'
import type {
  GeographicCoord,
  IsometricInspectResult,
  IsometricOverlayOptions,
  IsometricSpatialIndexPayload,
  IsometricTileMetadata,
  VisibleTileDescriptor,
} from '~/types/isometric'
import {
  DEFAULT_TRANSFORM_PARAMS,
  geographicToProjected,
  isometricToPixel,
  pixelToGeographic,
  pixelToIsometric,
  projectedToIsometric,
  scaleForZoom,
} from '~/composables/useGeographicTransform'
import {
  fetchIsometricMetadata,
  fetchIsometricSpatialIndex,
  inspectIsometricPixel,
  resolveTileUrl,
} from '~/services/isometricTileService'

// Default initial focus at Alun-Alun Bojonegoro (real OSM polygon 371909910)
const INITIAL_FOCUS_GEO: GeographicCoord = { lng: 111.880507, lat: -7.149721 }

export function useIsometricTiles(onRequestRedraw?: () => void) {
  const metadata = ref<IsometricTileMetadata | null>(null)
  const spatialIndex = ref<IsometricSpatialIndexPayload | null>(null)
  const isLoading = ref(true)
  const isSpatialLoading = ref(false)
  const loadError = ref<string | null>(null)
  const spatialError = ref<string | null>(null)

  const initialProj = geographicToProjected(INITIAL_FOCUS_GEO.lng, INITIAL_FOCUS_GEO.lat)
  const initialIso = projectedToIsometric(initialProj.easting, initialProj.northing)

  const zoom = ref<number>(4)
  const pixelScale = ref<number>(1)
  const centerIsoX = ref<number>(initialIso.isoX)
  const centerIsoY = ref<number>(initialIso.isoY)
  const viewportWidth = ref<number>(1280)
  const viewportHeight = ref<number>(720)

  const hoveredCoord = ref<GeographicCoord>({ ...INITIAL_FOCUS_GEO })
  const hoveredPixel = ref<{ px: number; py: number }>({ px: 0, py: 0 })
  const selectedInspect = ref<IsometricInspectResult | null>(null)

  const overlays = reactive<IsometricOverlayOptions>({
    showLandmarks: true,
    showDesaLabels: true,
    showPostgisInfra: true,
    showTileGrid: false,
    showSelectionHighlight: true,
  })

  const transformParams = computed(
    () => metadata.value?.coordinateTransformationParameters ?? DEFAULT_TRANSFORM_PARAMS,
  )

  const currentZoomSpec = computed(() => {
    const z = zoom.value
    const found = metadata.value?.zoomLevels.find((item) => item.z === z)
    if (found) return found
    const scale = scaleForZoom(z, transformParams.value)
    const isoW = transformParams.value.maxIsoX - transformParams.value.minIsoX
    const isoH = transformParams.value.maxIsoY - transformParams.value.minIsoY
    const widthPx = Math.round(isoW * scale)
    const heightPx = Math.round(isoH * scale)
    const tileSize = metadata.value?.tileSize ?? 256
    return {
      z,
      cols: Math.max(1, Math.ceil(widthPx / tileSize)),
      rows: Math.max(1, Math.ceil(heightPx / tileSize)),
      widthPx,
      heightPx,
      pixelResolution: transformParams.value.basePixelResolution / Math.pow(2, z),
      scale,
    }
  })

  const centerGeographic = computed(() => {
    const centerPx = isometricToPixel(
      centerIsoX.value,
      centerIsoY.value,
      zoom.value,
      transformParams.value,
    )
    return pixelToGeographic(centerPx.px, centerPx.py, zoom.value, transformParams.value)
  })

  function clampCameraToExtent() {
    const p = transformParams.value
    const marginX = 900
    const marginY = 450
    centerIsoX.value = Math.max(p.minIsoX + marginX, Math.min(p.maxIsoX - marginX, centerIsoX.value))
    centerIsoY.value = Math.max(p.minIsoY + marginY, Math.min(p.maxIsoY - marginY, centerIsoY.value))
  }

  function screenToGlobalPixel(screenX: number, screenY: number): { px: number; py: number } {
    const centerPx = isometricToPixel(
      centerIsoX.value,
      centerIsoY.value,
      zoom.value,
      transformParams.value,
    )
    const scale = pixelScale.value
    const dx = (screenX - viewportWidth.value * 0.5) / scale
    const dy = (screenY - viewportHeight.value * 0.5) / scale
    return {
      px: centerPx.px + dx,
      py: centerPx.py + dy,
    }
  }

  function globalPixelToScreen(px: number, py: number): { screenX: number; screenY: number } {
    const centerPx = isometricToPixel(
      centerIsoX.value,
      centerIsoY.value,
      zoom.value,
      transformParams.value,
    )
    const scale = pixelScale.value
    return {
      screenX: viewportWidth.value * 0.5 + (px - centerPx.px) * scale,
      screenY: viewportHeight.value * 0.5 + (py - centerPx.py) * scale,
    }
  }

  function isometricToScreen(
    isoX: number,
    isoY: number,
    elevationM = 0.0,
  ): { screenX: number; screenY: number } {
    const ptPx = isometricToPixel(isoX, isoY, zoom.value, transformParams.value, elevationM)
    return globalPixelToScreen(ptPx.px, ptPx.py)
  }

  const cacheVersion = computed(() => metadata.value?.generatedAt)

  const viewportIsoBounds = computed(() => {
    const tlPx = screenToGlobalPixel(0, 0)
    const brPx = screenToGlobalPixel(viewportWidth.value, viewportHeight.value)
    const tlIso = pixelToIsometric(tlPx.px, tlPx.py, zoom.value, transformParams.value)
    const brIso = pixelToIsometric(brPx.px, brPx.py, zoom.value, transformParams.value)
    return {
      minIsoX: tlIso.isoX,
      minIsoY: tlIso.isoY,
      maxIsoX: brIso.isoX,
      maxIsoY: brIso.isoY,
    }
  })

  function computeVisibleTiles(): VisibleTileDescriptor[] {
    const spec = currentZoomSpec.value
    const tileSize = metadata.value?.tileSize ?? 256
    const scale = pixelScale.value
    const topLeft = screenToGlobalPixel(0, 0)
    const bottomRight = screenToGlobalPixel(viewportWidth.value, viewportHeight.value)

    const minTileX = Math.max(0, Math.floor(topLeft.px / tileSize) - 1)
    const maxTileX = Math.min(spec.cols - 1, Math.floor(bottomRight.px / tileSize) + 1)
    const minTileY = Math.max(0, Math.floor(topLeft.py / tileSize) - 1)
    const maxTileY = Math.min(spec.rows - 1, Math.floor(bottomRight.py / tileSize) + 1)

    const template =
      metadata.value?.tileIndexingScheme.urlTemplate ??
      '/tiles/bojonegoro-isometric/{z}/{x}/{y}.png'
    const version = metadata.value?.generatedAt

    const originScreen = globalPixelToScreen(0, 0)
    const baseScreenX = Math.round(originScreen.screenX)
    const baseScreenY = Math.round(originScreen.screenY)
    const tilePx = Math.round(tileSize * scale)

    const list: VisibleTileDescriptor[] = []
    for (let ty = minTileY; ty <= maxTileY; ty++) {
      for (let tx = minTileX; tx <= maxTileX; tx++) {
        list.push({
          key: `${spec.z}/${tx}/${ty}`,
          z: spec.z,
          x: tx,
          y: ty,
          url: resolveTileUrl(spec.z, tx, ty, template, version),
          screenX: baseScreenX + tx * tilePx,
          screenY: baseScreenY + ty * tilePx,
          screenWidth: tilePx,
          screenHeight: tilePx,
        })
      }
    }
    return list
  }

  function panByScreenDelta(deltaScreenX: number, deltaScreenY: number) {
    const zScale = scaleForZoom(zoom.value, transformParams.value) * pixelScale.value
    centerIsoX.value -= deltaScreenX / zScale
    centerIsoY.value -= deltaScreenY / zScale
    clampCameraToExtent()
    if (onRequestRedraw) onRequestRedraw()
  }

  function setZoomAndScale(
    targetZ: number,
    targetPixelScale: number,
    pivotScreenX?: number,
    pivotScreenY?: number,
  ) {
    const minZ = metadata.value?.minZoom ?? 0
    const maxZ = metadata.value?.maxZoom ?? 4
    const clampedZ = Math.max(minZ, Math.min(maxZ, Math.round(targetZ)))
    const clampedScale = Math.max(1, Math.min(3, Math.round(targetPixelScale)))

    if (clampedZ === zoom.value && clampedScale === pixelScale.value) return

    const pivotX = pivotScreenX ?? viewportWidth.value * 0.5
    const pivotY = pivotScreenY ?? viewportHeight.value * 0.5
    const beforePx = screenToGlobalPixel(pivotX, pivotY)
    const pivotIso = pixelToIsometric(beforePx.px, beforePx.py, zoom.value, transformParams.value)

    zoom.value = clampedZ
    pixelScale.value = clampedScale

    // Keep the geographic/isometric point under cursor stationary across zoom or scale changes
    const newScale = scaleForZoom(clampedZ, transformParams.value) * clampedScale
    const offsetScreenX = pivotX - viewportWidth.value * 0.5
    const offsetScreenY = pivotY - viewportHeight.value * 0.5
    centerIsoX.value = pivotIso.isoX - offsetScreenX / newScale
    centerIsoY.value = pivotIso.isoY - offsetScreenY / newScale
    clampCameraToExtent()

    if (selectedInspect.value) {
      const anchorPx = isometricToPixel(
        selectedInspect.value.isometric.isoX,
        selectedInspect.value.isometric.isoY,
        clampedZ,
        transformParams.value,
      )
      selectedInspect.value = inspectIsometricPixel(
        anchorPx.px,
        anchorPx.py,
        clampedZ,
        metadata.value,
        spatialIndex.value,
      )
    }

    if (onRequestRedraw) onRequestRedraw()
  }

  function setZoomLevel(targetZ: number, pivotScreenX?: number, pivotScreenY?: number) {
    setZoomAndScale(targetZ, 1, pivotScreenX, pivotScreenY)
  }

  function zoomIn(pivotScreenX?: number, pivotScreenY?: number) {
    const maxZ = metadata.value?.maxZoom ?? 4
    if (zoom.value < maxZ) {
      setZoomAndScale(zoom.value + 1, 1, pivotScreenX, pivotScreenY)
    } else if (pixelScale.value < 3) {
      setZoomAndScale(maxZ, pixelScale.value + 1, pivotScreenX, pivotScreenY)
    }
  }

  function zoomOut(pivotScreenX?: number, pivotScreenY?: number) {
    const minZ = metadata.value?.minZoom ?? 0
    if (pixelScale.value > 1) {
      setZoomAndScale(zoom.value, pixelScale.value - 1, pivotScreenX, pivotScreenY)
    } else if (zoom.value > minZ) {
      setZoomAndScale(zoom.value - 1, 1, pivotScreenX, pivotScreenY)
    }
  }

  function flyToIsometric(isoX: number, isoY: number, targetZoom?: number) {
    if (typeof targetZoom === 'number') {
      const minZ = metadata.value?.minZoom ?? 0
      const maxZ = metadata.value?.maxZoom ?? 4
      zoom.value = Math.max(minZ, Math.min(maxZ, Math.round(targetZoom)))
    }
    centerIsoX.value = isoX
    centerIsoY.value = isoY
    clampCameraToExtent()

    const ptPx = isometricToPixel(isoX, isoY, zoom.value, transformParams.value)
    selectedInspect.value = inspectIsometricPixel(
      ptPx.px,
      ptPx.py,
      zoom.value,
      metadata.value,
      spatialIndex.value,
    )
    if (onRequestRedraw) onRequestRedraw()
  }

  function flyToGeographic(lng: number, lat: number, targetZoom?: number) {
    const proj = geographicToProjected(lng, lat)
    const iso = projectedToIsometric(proj.easting, proj.northing, transformParams.value)
    flyToIsometric(iso.isoX, iso.isoY, targetZoom)
  }

  function resetView() {
    pixelScale.value = 1
    flyToGeographic(INITIAL_FOCUS_GEO.lng, INITIAL_FOCUS_GEO.lat, 4)
  }

  function updateHoverAtScreen(screenX: number, screenY: number) {
    const pt = screenToGlobalPixel(screenX, screenY)
    hoveredPixel.value = { px: Math.round(pt.px * 10) / 10, py: Math.round(pt.py * 10) / 10 }
    hoveredCoord.value = pixelToGeographic(pt.px, pt.py, zoom.value, transformParams.value)
  }

  function inspectAtScreenPoint(screenX: number, screenY: number): IsometricInspectResult {
    const pt = screenToGlobalPixel(screenX, screenY)
    const res = inspectIsometricPixel(
      pt.px,
      pt.py,
      zoom.value,
      metadata.value,
      spatialIndex.value,
    )
    selectedInspect.value = res
    if (onRequestRedraw) onRequestRedraw()
    return res
  }

  let initEpoch = 0

  async function loadSpatialIndexAsync(meta: IsometricTileMetadata, epoch: number) {
    try {
      const spatial = await fetchIsometricSpatialIndex(meta.generatedAt)
      if (epoch !== initEpoch) return
      spatialIndex.value = spatial
      const targetIso = selectedInspect.value
        ? selectedInspect.value.isometric
        : { isoX: centerIsoX.value, isoY: centerIsoY.value }
      const targetPx = isometricToPixel(
        targetIso.isoX,
        targetIso.isoY,
        zoom.value,
        meta.coordinateTransformationParameters,
      )
      selectedInspect.value = inspectIsometricPixel(
        targetPx.px,
        targetPx.py,
        zoom.value,
        meta,
        spatial,
      )
    } catch (spErr: unknown) {
      if (epoch !== initEpoch) return
      spatialError.value =
        spErr instanceof Error ? spErr.message : 'Indeks pencarian fitur tidak tersedia.'
    } finally {
      if (epoch === initEpoch) {
        isSpatialLoading.value = false
        if (onRequestRedraw) onRequestRedraw()
      }
    }
  }

  async function initializeEngine() {
    const epoch = ++initEpoch
    isLoading.value = true
    isSpatialLoading.value = true
    loadError.value = null
    spatialError.value = null
    try {
      // Load metadata first (~12 KB) so z=0..4 raster tiles appear immediately without waiting for 16 MB features.json
      const meta = await fetchIsometricMetadata()
      if (epoch !== initEpoch) return
      metadata.value = meta
      isLoading.value = false
      if (onRequestRedraw) onRequestRedraw()

      // Kick off 16 MB vector/landmark spatial index in background so canvas tiles never wait on it
      void loadSpatialIndexAsync(meta, epoch)
    } catch (err: unknown) {
      if (epoch !== initEpoch) return
      loadError.value =
        err instanceof Error ? err.message : 'Gagal memuat metadata tile isometrik Bojonegoro.'
      isLoading.value = false
      isSpatialLoading.value = false
      if (onRequestRedraw) onRequestRedraw()
    }
  }

  return {
    metadata,
    spatialIndex,
    isLoading,
    isSpatialLoading,
    loadError,
    spatialError,
    cacheVersion,
    zoom,
    pixelScale,
    centerIsoX,
    centerIsoY,
    viewportWidth,
    viewportHeight,
    viewportIsoBounds,
    hoveredCoord,
    hoveredPixel,
    selectedInspect,
    overlays,
    transformParams,
    currentZoomSpec,
    centerGeographic,
    screenToGlobalPixel,
    globalPixelToScreen,
    isometricToScreen,
    computeVisibleTiles,
    panByScreenDelta,
    setZoomLevel,
    zoomIn,
    zoomOut,
    flyToIsometric,
    flyToGeographic,
    resetView,
    updateHoverAtScreen,
    inspectAtScreenPoint,
    initializeEngine,
  }
}

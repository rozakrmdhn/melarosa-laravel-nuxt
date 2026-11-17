<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useIsometricTiles } from '~/composables/useIsometricTiles'
import { useTileLoader } from '~/composables/useTileLoader'
import type { IsometricLandmarkFeature } from '~/types/isometric'

const containerRef = ref<HTMLElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const searchQuery = ref('')
const showCoordVerifyModal = ref(false)
const showMobileInspector = ref(true)
const showMinimap = ref(true)
const copyFeedback = ref(false)
let isMinimapDragging = false

let rafId: number | null = null
let resizeObserver: ResizeObserver | null = null

const isDragging = ref(false)
let dragStartX = 0
let dragStartY = 0
let pointerDownX = 0
let pointerDownY = 0
let lastWheelAt = 0

function scheduleRedraw() {
  if (typeof window === 'undefined') return
  if (rafId !== null) return
  rafId = window.requestAnimationFrame(() => {
    rafId = null
    renderCanvas()
  })
}

const {
  metadata,
  spatialIndex,
  isLoading,
  isSpatialLoading,
  loadError,
  spatialError,
  cacheVersion,
  zoom,
  pixelScale,
  viewportWidth,
  viewportHeight,
  viewportIsoBounds,
  hoveredCoord,
  hoveredPixel,
  selectedInspect,
  overlays,
  currentZoomSpec,
  transformParams,
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
} = useIsometricTiles(scheduleRedraw)

const masterPreviewSrc = computed(() => {
  const base = metadata.value?.masterPreviewUrl || '/tiles/bojonegoro-isometric/master_preview.png'
  if (!cacheVersion.value) return base
  const sep = base.includes('?') ? '&' : '?'
  return `${base}${sep}v=${encodeURIComponent(cacheVersion.value)}`
})

const minimapViewportStyle = computed(() => {
  const p = transformParams.value
  const spanX = Math.max(1, p.maxIsoX - p.minIsoX)
  const spanY = Math.max(1, p.maxIsoY - p.minIsoY)
  const vb = viewportIsoBounds.value
  const left = Math.max(0, Math.min(98, ((vb.minIsoX - p.minIsoX) / spanX) * 100))
  const right = Math.max(left + 2, Math.min(100, ((vb.maxIsoX - p.minIsoX) / spanX) * 100))
  const top = Math.max(0, Math.min(98, ((vb.minIsoY - p.minIsoY) / spanY) * 100))
  const bottom = Math.max(top + 2, Math.min(100, ((vb.maxIsoY - p.minIsoY) / spanY) * 100))
  return {
    left: `${left.toFixed(2)}%`,
    top: `${top.toFixed(2)}%`,
    width: `${(right - left).toFixed(2)}%`,
    height: `${(bottom - top).toFixed(2)}%`,
  }
})

const {
  loadedCount,
  pendingCount,
  errorCount,
  requestTile,
  getFallbackTileCrop,
  preloadBaseZoom,
} = useTileLoader(scheduleRedraw)

const filteredLandmarks = computed<IsometricLandmarkFeature[]>(() => {
  const list = spatialIndex.value?.landmarks ?? []
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return list
  return list.filter(
    (item) =>
      item.name.toLowerCase().includes(q) ||
      item.desa.toLowerCase().includes(q) ||
      item.category.toLowerCase().includes(q),
  )
})

const searchMatchingBuildings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q || !spatialIndex.value) return []
  return spatialIndex.value.buildings
    .filter(
      (b) =>
        b.name.toLowerCase().includes(q) ||
        b.id.toLowerCase().includes(q) ||
        b.desa.toLowerCase().includes(q),
    )
    .slice(0, 6)
})

const activeFeatureSummary = computed(() => {
  const info = selectedInspect.value
  if (!info) return null
  if (info.building) {
    return {
      badge: 'Bangunan 3D (Ground Anchor)',
      badgeClass: 'bg-amber-950/90 text-amber-200 border-amber-700/70',
      id: info.building.id,
      title: info.building.name,
      subtitle: `Kelurahan/Desa ${info.building.desa} (${info.building.levels} Lantai, ${info.building.heightM} m)`,
      meta: [
        { label: 'ID Objek', value: info.building.id },
        { label: 'Kategori', value: info.building.kind.toUpperCase() },
        { label: 'Tipe Atap', value: info.building.roofStyle.toUpperCase() },
        { label: 'Tinggi / Lantai', value: `${info.building.heightM} m (${info.building.levels} lt)` },
      ],
    }
  }
  if (info.infrastructure) {
    return {
      badge: 'Segmen Infrastruktur PostGIS',
      badgeClass: 'bg-emerald-950/90 text-emerald-200 border-emerald-700/70',
      id: info.infrastructure.id,
      title: info.infrastructure.name,
      subtitle: `Tabel ${info.infrastructure.sourceTable} - Desa ${info.infrastructure.desa}`,
      meta: [
        { label: 'ID Segmen', value: info.infrastructure.id },
        { label: 'Perkerasan', value: info.infrastructure.category },
        { label: 'Kondisi', value: info.infrastructure.condition },
        { label: 'Sumber Dana', value: String(info.infrastructure.sumberDana || 'APBD Bojonegoro') },
      ],
    }
  }
  if (info.road) {
    return {
      badge: 'Jaringan Jalan & Jembatan',
      badgeClass: 'bg-sky-950/90 text-sky-200 border-sky-700/70',
      id: info.road.id,
      title: info.road.name,
      subtitle: `Desa/Kelurahan ${info.road.desa} (${info.road.surface})`,
      meta: [
        { label: 'ID Ruas', value: info.road.id },
        { label: 'Kelas Jalan', value: info.road.roadClass.toUpperCase() },
        { label: 'Lebar Ruas', value: `${info.road.widthM} m` },
        { label: 'Sumber GIS', value: info.road.source },
      ],
    }
  }
  if (info.park) {
    return {
      badge: 'Ruang Terbuka Hijau (RTH)',
      badgeClass: 'bg-emerald-950/90 text-emerald-200 border-emerald-700/70',
      id: info.park.id,
      title: info.park.name,
      subtitle: `Desa/Kelurahan ${info.park.desa}`,
      meta: [
        { label: 'ID RTH', value: info.park.id },
        { label: 'Fungsi', value: info.park.kind.toUpperCase() },
        { label: 'Desa/Kelurahan', value: info.park.desa },
        { label: 'Proyeksi', value: 'EPSG:32749' },
      ],
    }
  }
  if (info.river) {
    return {
      badge: 'Hidrologi & Sungai',
      badgeClass: 'bg-cyan-950/90 text-cyan-200 border-cyan-700/70',
      id: info.river.id,
      title: info.river.name,
      subtitle: `Lebar koridor rata-rata ${info.river.widthM} m`,
      meta: [
        { label: 'ID Sungai', value: info.river.id },
        { label: 'Tipe Aliran', value: info.river.kind.toUpperCase() },
        { label: 'Lebar Koridor', value: `${info.river.widthM} m` },
        { label: 'Wilayah', value: info.desa?.name ?? 'Kecamatan Bojonegoro' },
      ],
    }
  }
  if (info.desa) {
    return {
      badge: 'Wilayah Administrasi PostGIS',
      badgeClass: 'bg-slate-800 text-slate-200 border-slate-600',
      id: info.desa.id,
      title: `Desa / Kelurahan ${info.desa.name}`,
      subtitle: `Kecamatan Bojonegoro (${info.desa.luasHektar.toFixed(1)} Ha)`,
      meta: [
        { label: 'Kode Wilayah', value: String(info.desa.desaId) },
        { label: 'Luas PostGIS', value: `${info.desa.luasHektar.toFixed(2)} Ha` },
        { label: 'Kecamatan', value: 'Bojonegoro' },
        { label: 'SRS', value: 'EPSG:32749' },
      ],
    }
  }
  return {
    badge: 'Titik Koordinat Tanah',
    badgeClass: 'bg-slate-800 text-slate-200 border-slate-600',
    id: 'ground-point',
    title: 'Area Terbuka Kecamatan Bojonegoro',
    subtitle: 'Klik bangunan, jalan, taman, atau sungai untuk rincian atribut GIS',
    meta: [
      { label: 'Zoom Tile', value: `z=${info.zoom}` },
      { label: 'Resolusi', value: `${currentZoomSpec.value.pixelResolution} m/px` },
      { label: 'Piksel Global', value: `${Math.round(info.pixel.px)}, ${Math.round(info.pixel.py)}` },
      { label: 'SRS', value: 'EPSG:32749' },
    ],
  }
})

function drawPolygonScreen(
  ctx: CanvasRenderingContext2D,
  isoRing: [number, number][],
  strokeStyle: string,
  fillStyle?: string,
  lineWidth = 2,
) {
  if (isoRing.length < 3) return
  ctx.beginPath()
  for (let i = 0; i < isoRing.length; i++) {
    const pt = isoRing[i]!
    const scr = isometricToScreen(pt[0], pt[1], 0)
    if (i === 0) ctx.moveTo(scr.screenX, scr.screenY)
    else ctx.lineTo(scr.screenX, scr.screenY)
  }
  ctx.closePath()
  if (fillStyle) {
    ctx.fillStyle = fillStyle
    ctx.fill()
  }
  ctx.strokeStyle = strokeStyle
  ctx.lineWidth = lineWidth
  ctx.stroke()
}

function drawPolylineScreen(
  ctx: CanvasRenderingContext2D,
  isoLine: [number, number][],
  strokeStyle: string,
  lineWidth = 3,
  dash: number[] = [],
) {
  if (isoLine.length < 2) return
  ctx.save()
  ctx.beginPath()
  ctx.setLineDash(dash)
  for (let i = 0; i < isoLine.length; i++) {
    const pt = isoLine[i]!
    const scr = isometricToScreen(pt[0], pt[1], 0)
    if (i === 0) ctx.moveTo(scr.screenX, scr.screenY)
    else ctx.lineTo(scr.screenX, scr.screenY)
  }
  ctx.strokeStyle = strokeStyle
  ctx.lineWidth = lineWidth
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.stroke()
  ctx.restore()
}

function renderCanvas() {
  const canvas = canvasRef.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const w = viewportWidth.value
  const h = viewportHeight.value
  ctx.save()
  ctx.imageSmoothingEnabled = false
  ctx.fillStyle = '#E4DDCE'
  ctx.fillRect(0, 0, w, h)

  // Wait for metadata (~12 KB) before requesting tiles so URLs include the cacheVersion token
  if (!metadata.value) {
    ctx.restore()
    return
  }

  const visibleTiles = computeVisibleTiles()
  const tileSize = metadata.value.tileSize ?? 256
  const template =
    metadata.value.tileIndexingScheme.urlTemplate ??
    '/tiles/bojonegoro-isometric/{z}/{x}/{y}.png'
  const version = cacheVersion.value

  // 1. Draw visible 256x256 PNG raster tiles with parent-zoom fallback
  for (const tile of visibleTiles) {
    const entry = requestTile(tile.z, tile.x, tile.y, template, version)
    if (entry && entry.status === 'loaded' && entry.image) {
      ctx.drawImage(
        entry.image,
        0,
        0,
        tileSize,
        tileSize,
        tile.screenX,
        tile.screenY,
        tile.screenWidth,
        tile.screenHeight,
      )
    } else {
      const fallback = getFallbackTileCrop(tile.z, tile.x, tile.y, tileSize, version)
      if (fallback) {
        ctx.drawImage(
          fallback.image,
          fallback.sx,
          fallback.sy,
          fallback.sw,
          fallback.sh,
          tile.screenX,
          tile.screenY,
          tile.screenWidth,
          tile.screenHeight,
        )
      } else {
        ctx.fillStyle = '#E4DDCE'
        ctx.fillRect(tile.screenX, tile.screenY, tile.screenWidth, tile.screenHeight)
      }
    }

    if (overlays.showTileGrid) {
      ctx.strokeStyle = 'rgba(30, 41, 59, 0.35)'
      ctx.lineWidth = 1
      ctx.strokeRect(tile.screenX + 0.5, tile.screenY + 0.5, tile.screenWidth, tile.screenHeight)
      ctx.fillStyle = 'rgba(15, 23, 42, 0.82)'
      ctx.fillRect(tile.screenX + 6, tile.screenY + 6, 72, 20)
      ctx.fillStyle = '#F8FAFC'
      ctx.font = '600 11px Inter, system-ui, sans-serif'
      ctx.fillText(`z${tile.z} (${tile.x},${tile.y})`, tile.screenX + 11, tile.screenY + 19)
    }
  }

  // 2. Optional PostGIS Infrastructure overlay vector highlight
  if (overlays.showPostgisInfra && spatialIndex.value) {
    for (const inf of spatialIndex.value.infrastructure) {
      const cond = inf.condition.toUpperCase()
      const color = cond.includes('BAIK')
        ? 'rgba(16, 185, 129, 0.85)'
        : cond.includes('BERAT')
          ? 'rgba(239, 68, 68, 0.88)'
          : 'rgba(245, 158, 11, 0.88)'
      drawPolylineScreen(ctx, inf.isoLine, color, zoom.value >= 2 ? 3 : 2, [5, 4])
    }
  }

  // 3. Optional Desa/Kelurahan name badges anchored to PostGIS centroids
  if (overlays.showDesaLabels && spatialIndex.value) {
    ctx.font = '700 11px Inter, system-ui, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    for (const desa of spatialIndex.value.desaBoundaries) {
      const pos = isometricToScreen(desa.anchor.isometric[0], desa.anchor.isometric[1], 0)
      if (pos.screenX < -60 || pos.screenX > w + 60 || pos.screenY < -30 || pos.screenY > h + 30) {
        continue
      }
      const label = desa.name.toUpperCase()
      const textW = ctx.measureText(label).width
      const boxW = Math.round(textW + 14)
      const boxH = 20
      ctx.fillStyle = 'rgba(15, 23, 42, 0.78)'
      ctx.fillRect(Math.round(pos.screenX - boxW * 0.5), Math.round(pos.screenY - boxH * 0.5), boxW, boxH)
      ctx.strokeStyle = 'rgba(148, 163, 184, 0.45)'
      ctx.lineWidth = 1
      ctx.strokeRect(
        Math.round(pos.screenX - boxW * 0.5) + 0.5,
        Math.round(pos.screenY - boxH * 0.5) + 0.5,
        boxW,
        boxH,
      )
      ctx.fillStyle = '#E2E8F0'
      ctx.fillText(label, Math.round(pos.screenX), Math.round(pos.screenY) + 0.5)
    }
  }

  // 4. Selected spatial object highlight & Ground Anchor pin
  if (overlays.showSelectionHighlight && selectedInspect.value) {
    const sel = selectedInspect.value
    if (sel.building && sel.buildingIsoRing) {
      drawPolygonScreen(
        ctx,
        sel.buildingIsoRing,
        '#F59E0B',
        'rgba(245, 158, 11, 0.28)',
        2.5,
      )
    } else if (sel.infrastructure) {
      drawPolylineScreen(ctx, sel.infrastructure.isoLine, '#10B981', 5)
    } else if (sel.road) {
      drawPolylineScreen(ctx, sel.road.isoLine, '#38BDF8', 4)
    } else if (sel.park) {
      drawPolygonScreen(ctx, sel.park.isoRing, '#22C55E', 'rgba(34, 197, 94, 0.22)', 2)
    } else if (sel.river) {
      drawPolylineScreen(ctx, sel.river.isoCenterline, '#06B6D4', 4)
    }

    // Draw Ground Anchor target reticle at exact (isoX, isoY, elevation=0)
    const anchorScreen = isometricToScreen(sel.isometric.isoX, sel.isometric.isoY, 0)
    ctx.beginPath()
    ctx.arc(anchorScreen.screenX, anchorScreen.screenY, 9, 0, Math.PI * 2)
    ctx.fillStyle = 'rgba(245, 158, 11, 0.25)'
    ctx.fill()
    ctx.lineWidth = 2
    ctx.strokeStyle = '#F59E0B'
    ctx.stroke()

    ctx.beginPath()
    ctx.arc(anchorScreen.screenX, anchorScreen.screenY, 3.5, 0, Math.PI * 2)
    ctx.fillStyle = '#FEF08A'
    ctx.fill()
  }

  // 5. Optional Landmark pins
  if (overlays.showLandmarks && spatialIndex.value) {
    ctx.font = '600 11px Inter, system-ui, sans-serif'
    ctx.textAlign = 'left'
    ctx.textBaseline = 'middle'
    for (const lm of spatialIndex.value.landmarks) {
      const pos = isometricToScreen(
        lm.ground_anchor.isometric[0],
        lm.ground_anchor.isometric[1],
        12,
      )
      if (pos.screenX < -80 || pos.screenX > w + 80 || pos.screenY < -40 || pos.screenY > h + 40) {
        continue
      }
      ctx.beginPath()
      ctx.arc(pos.screenX, pos.screenY, 5, 0, Math.PI * 2)
      ctx.fillStyle = '#2563EB'
      ctx.fill()
      ctx.lineWidth = 2
      ctx.strokeStyle = '#F8FAFC'
      ctx.stroke()

      if (zoom.value >= 1) {
        const textW = ctx.measureText(lm.name).width
        const bx = Math.round(pos.screenX + 9)
        const by = Math.round(pos.screenY - 10)
        ctx.fillStyle = 'rgba(15, 23, 42, 0.88)'
        ctx.fillRect(bx, by, Math.round(textW + 12), 20)
        ctx.strokeStyle = 'rgba(59, 130, 246, 0.7)'
        ctx.lineWidth = 1
        ctx.strokeRect(bx + 0.5, by + 0.5, Math.round(textW + 12), 20)
        ctx.fillStyle = '#F8FAFC'
        ctx.fillText(lm.name, bx + 6, pos.screenY)
      }
    }
  }

  ctx.restore()
}

function syncCanvasSize() {
  const container = containerRef.value
  const canvas = canvasRef.value
  if (!container || !canvas) return
  const rect = container.getBoundingClientRect()
  const w = Math.max(320, Math.floor(rect.width))
  const h = Math.max(320, Math.floor(rect.height))
  viewportWidth.value = w
  viewportHeight.value = h
  canvas.width = w
  canvas.height = h
  scheduleRedraw()
}

function handlePointerDown(ev: PointerEvent) {
  if (ev.button !== 0 && ev.pointerType === 'mouse') return
  const canvas = canvasRef.value
  if (!canvas) return
  canvas.setPointerCapture(ev.pointerId)
  isDragging.value = true
  dragStartX = ev.clientX
  dragStartY = ev.clientY
  pointerDownX = ev.clientX
  pointerDownY = ev.clientY
}

function handlePointerMove(ev: PointerEvent) {
  const canvas = canvasRef.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  const sx = ev.clientX - rect.left
  const sy = ev.clientY - rect.top
  updateHoverAtScreen(sx, sy)

  if (!isDragging.value) return
  const dx = ev.clientX - dragStartX
  const dy = ev.clientY - dragStartY
  dragStartX = ev.clientX
  dragStartY = ev.clientY
  panByScreenDelta(dx, dy)
}

function handlePointerUp(ev: PointerEvent) {
  const canvas = canvasRef.value
  if (canvas && canvas.hasPointerCapture(ev.pointerId)) {
    canvas.releasePointerCapture(ev.pointerId)
  }
  const movedDist = Math.hypot(ev.clientX - pointerDownX, ev.clientY - pointerDownY)
  isDragging.value = false

  if (movedDist < 6 && canvas) {
    const rect = canvas.getBoundingClientRect()
    const sx = ev.clientX - rect.left
    const sy = ev.clientY - rect.top
    inspectAtScreenPoint(sx, sy)
    showMobileInspector.value = true
  }
}

function handleWheel(ev: WheelEvent) {
  ev.preventDefault()
  const now = performance.now()
  if (now - lastWheelAt < 140) return
  lastWheelAt = now

  const canvas = canvasRef.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  const sx = ev.clientX - rect.left
  const sy = ev.clientY - rect.top

  if (ev.deltaY < 0) {
    zoomIn(sx, sy)
  } else if (ev.deltaY > 0) {
    zoomOut(sx, sy)
  }
}

function handleKeyDown(ev: KeyboardEvent) {
  const step = 96
  if (ev.key === 'ArrowLeft') {
    ev.preventDefault()
    panByScreenDelta(step, 0)
  } else if (ev.key === 'ArrowRight') {
    ev.preventDefault()
    panByScreenDelta(-step, 0)
  } else if (ev.key === 'ArrowUp') {
    ev.preventDefault()
    panByScreenDelta(0, step)
  } else if (ev.key === 'ArrowDown') {
    ev.preventDefault()
    panByScreenDelta(0, -step)
  } else if (ev.key === '+' || ev.key === '=') {
    ev.preventDefault()
    zoomIn()
  } else if (ev.key === '-' || ev.key === '_') {
    ev.preventDefault()
    zoomOut()
  } else if (ev.key === '0') {
    ev.preventDefault()
    resetView()
  } else if (ev.key === 'Escape') {
    showCoordVerifyModal.value = false
  }
}

function handleDoubleClick(ev: MouseEvent) {
  const canvas = canvasRef.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  zoomIn(ev.clientX - rect.left, ev.clientY - rect.top)
}

function jumpFromMinimapEvent(ev: PointerEvent) {
  const el = ev.currentTarget as HTMLElement | null
  if (!el) return
  const rect = el.getBoundingClientRect()
  if (rect.width <= 0 || rect.height <= 0) return
  const rx = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width))
  const ry = Math.max(0, Math.min(1, (ev.clientY - rect.top) / rect.height))
  const p = transformParams.value
  const targetIsoX = p.minIsoX + rx * (p.maxIsoX - p.minIsoX)
  const targetIsoY = p.minIsoY + ry * (p.maxIsoY - p.minIsoY)
  flyToIsometric(targetIsoX, targetIsoY, zoom.value)
}

function handleMinimapPointerDown(ev: PointerEvent) {
  const el = ev.currentTarget as HTMLElement | null
  if (el && el.setPointerCapture) {
    el.setPointerCapture(ev.pointerId)
  }
  isMinimapDragging = true
  jumpFromMinimapEvent(ev)
}

function handleMinimapPointerMove(ev: PointerEvent) {
  if (!isMinimapDragging) return
  jumpFromMinimapEvent(ev)
}

function handleMinimapPointerUp(ev: PointerEvent) {
  const el = ev.currentTarget as HTMLElement | null
  if (el && el.hasPointerCapture?.(ev.pointerId)) {
    el.releasePointerCapture(ev.pointerId)
  }
  isMinimapDragging = false
}

function selectLandmark(lm: IsometricLandmarkFeature) {
  flyToIsometric(lm.ground_anchor.isometric[0], lm.ground_anchor.isometric[1], 4)
  showMobileInspector.value = true
}

function selectBuildingFromSearch(isoX: number, isoY: number) {
  flyToIsometric(isoX, isoY, 4)
  searchQuery.value = ''
  showMobileInspector.value = true
}

async function copySelectedCoordinates() {
  if (!selectedInspect.value || typeof navigator === 'undefined') return
  const g = selectedInspect.value.geographic
  const p = selectedInspect.value.projected
  const text = `${g.lat.toFixed(6)}, ${g.lng.toFixed(6)} (EPSG:32749 E:${p.easting.toFixed(2)} N:${p.northing.toFixed(2)})`
  try {
    await navigator.clipboard.writeText(text)
    copyFeedback.value = true
    window.setTimeout(() => {
      copyFeedback.value = false
    }, 1800)
  } catch {
    copyFeedback.value = false
  }
}

watch(
  () => [
    overlays.showLandmarks,
    overlays.showDesaLabels,
    overlays.showPostgisInfra,
    overlays.showTileGrid,
    overlays.showSelectionHighlight,
  ],
  () => {
    scheduleRedraw()
  },
)

onMounted(async () => {
  syncCanvasSize()
  if (typeof ResizeObserver !== 'undefined' && containerRef.value) {
    resizeObserver = new ResizeObserver(() => {
      syncCanvasSize()
    })
    resizeObserver.observe(containerRef.value)
  }
  await initializeEngine()
  if (metadata.value) {
    preloadBaseZoom(metadata.value.tileIndexingScheme.urlTemplate, cacheVersion.value)
  }
  scheduleRedraw()
})

onBeforeUnmount(() => {
  if (rafId !== null && typeof window !== 'undefined') {
    window.cancelAnimationFrame(rafId)
  }
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
})
</script>

<template>
  <div
    ref="containerRef"
    class="relative h-full w-full overflow-hidden bg-slate-950 text-slate-100 select-none"
    tabindex="0"
    role="application"
    aria-label="Peta Pixel Isometrik Geospasial Kecamatan Bojonegoro"
    @keydown="handleKeyDown"
  >
    <!-- Nearest-Neighbor Isometric Tile Canvas -->
    <canvas
      ref="canvasRef"
      class="block h-full w-full touch-none"
      :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
      style="image-rendering: pixelated; image-rendering: crisp-edges"
      @pointerdown="handlePointerDown"
      @pointermove="handlePointerMove"
      @pointerup="handlePointerUp"
      @pointercancel="handlePointerUp"
      @dblclick="handleDoubleClick"
      @wheel.passive="false"
      @wheel="handleWheel"
    />

    <!-- Top Navigation & Layer Bar (positioned below fixed PublicNavbar) -->
    <header
      class="pointer-events-none absolute inset-x-0 top-[62px] z-20 flex flex-wrap items-start justify-between gap-3 p-3 md:p-4 lg:top-[76px]"
    >
      <!-- Left: Brand, Title & Back Navigation -->
      <div
        class="pointer-events-auto flex flex-wrap items-center gap-2.5 rounded-xl border border-slate-700/80 bg-slate-900/95 px-3 py-2 shadow-xl backdrop-blur-md"
      >
        <NuxtLink
          to="/maps"
          class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center gap-2 rounded-lg border border-slate-700 bg-slate-800/90 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:border-blue-400 hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          aria-label="Kembali ke WebGIS Utama MELAROSA"
        >
          <span aria-hidden="true">&larr;</span>
          <span>WebGIS 2D</span>
        </NuxtLink>

        <div class="px-1">
          <div class="flex items-center gap-2">
            <span
              class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400"
              aria-hidden="true"
            />
            <h1 class="text-sm font-bold tracking-tight text-slate-100 md:text-base">
              Peta Pixel Isometrik Kecamatan Bojonegoro
            </h1>
          </div>
          <div class="mt-0.5 flex flex-wrap items-center gap-2">
            <p class="text-xs text-slate-300 tabular-nums">
              OpenStreetMap (OSM) + PostGIS MELAROSA (EPSG:32749, Tile 256x256 px)
            </p>
            <span
              v-if="isSpatialLoading"
              class="inline-flex items-center rounded-md border border-blue-700/70 bg-blue-950/90 px-2 py-0.5 text-[11px] font-semibold text-blue-200"
            >
              Memuat indeks klik...
            </span>
            <span
              v-else-if="spatialError"
              class="inline-flex items-center rounded-md border border-amber-700/70 bg-amber-950/90 px-2 py-0.5 text-[11px] font-semibold text-amber-200"
            >
              Indeks klik belum siap
            </span>
          </div>
        </div>

        <slot name="mode-switch" />
      </div>

      <!-- Right: Search, Layer Toggles & Coordinate Accuracy Modal Button -->
      <div
        class="pointer-events-auto flex flex-wrap items-center gap-2 rounded-xl border border-slate-700/80 bg-slate-900/95 p-2 shadow-xl backdrop-blur-md"
      >
        <!-- Quick Search Input -->
        <div class="relative">
          <label for="iso-landmark-search" class="sr-only">
            Cari bangunan, desa, atau titik penting di Kecamatan Bojonegoro
          </label>
          <input
            id="iso-landmark-search"
            v-model="searchQuery"
            type="search"
            placeholder="Cari Alun-Alun, desa, gedung..."
            class="min-h-[44px] w-48 rounded-lg border border-slate-700 bg-slate-800/95 px-3 py-2 text-xs text-slate-100 placeholder-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-400 focus:outline-none md:w-56"
          />
          <!-- Search Dropdown for Buildings -->
          <div
            v-if="searchQuery.trim().length > 1 && searchMatchingBuildings.length > 0"
            class="absolute top-full left-0 z-30 mt-1.5 w-72 rounded-xl border border-slate-700 bg-slate-900/98 p-1.5 shadow-2xl"
          >
            <p class="px-2.5 py-1 text-[11px] font-semibold text-slate-300">
              Hasil Pencarian Objek Spasial
            </p>
            <button
              v-for="item in searchMatchingBuildings"
              :key="item.id"
              type="button"
              class="flex min-h-[44px] w-full cursor-pointer flex-col justify-center rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
              @click="selectBuildingFromSearch(item.anchorIso[0], item.anchorIso[1])"
            >
              <span class="font-semibold text-slate-100">{{ item.name }}</span>
              <span class="text-[11px] text-slate-300 tabular-nums">
                Desa {{ item.desa }} ({{ item.id }})
              </span>
            </button>
          </div>
        </div>

        <!-- Layer Toggle Buttons (44x44px minimum touch target) -->
        <button
          type="button"
          class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          :class="
            overlays.showLandmarks
              ? 'border-blue-500 bg-blue-950/90 text-blue-200'
              : 'border-slate-700 bg-slate-800/90 text-slate-300 hover:bg-slate-800'
          "
          :aria-pressed="overlays.showLandmarks"
          @click="overlays.showLandmarks = !overlays.showLandmarks"
        >
          <span>Landmark</span>
        </button>

        <button
          type="button"
          class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          :class="
            overlays.showDesaLabels
              ? 'border-emerald-500 bg-emerald-950/90 text-emerald-200'
              : 'border-slate-700 bg-slate-800/90 text-slate-300 hover:bg-slate-800'
          "
          :aria-pressed="overlays.showDesaLabels"
          @click="overlays.showDesaLabels = !overlays.showDesaLabels"
        >
          <span>18 Desa</span>
        </button>

        <button
          type="button"
          class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          :class="
            overlays.showPostgisInfra
              ? 'border-amber-500 bg-amber-950/90 text-amber-200'
              : 'border-slate-700 bg-slate-800/90 text-slate-300 hover:bg-slate-800'
          "
          :aria-pressed="overlays.showPostgisInfra"
          @click="overlays.showPostgisInfra = !overlays.showPostgisInfra"
        >
          <span>Segmen PostGIS</span>
        </button>

        <button
          type="button"
          class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          :class="
            overlays.showTileGrid
              ? 'border-purple-500 bg-purple-950/90 text-purple-200'
              : 'border-slate-700 bg-slate-800/90 text-slate-300 hover:bg-slate-800'
          "
          :aria-pressed="overlays.showTileGrid"
          @click="overlays.showTileGrid = !overlays.showTileGrid"
        >
          <span>Grid Tile</span>
        </button>

        <button
          type="button"
          class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:border-blue-400 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          @click="showCoordVerifyModal = true"
        >
          <span>Uji Akurasi Koordinat</span>
        </button>
      </div>
    </header>

    <!-- Loading Overlay State -->
    <div
      v-if="isLoading"
      class="pointer-events-auto absolute inset-0 z-30 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
      role="status"
      aria-live="polite"
    >
      <div
        class="max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 text-center shadow-2xl"
      >
        <div
          class="mx-auto mb-3 h-9 w-9 animate-spin rounded-full border-3 border-slate-600 border-t-blue-400"
          aria-hidden="true"
        />
        <h2 class="text-base font-bold text-slate-100">
          Memuat Engine Peta Pixel Isometrik Bojonegoro
        </h2>
        <p class="mt-1 text-xs text-slate-300">
          Menyiapkan indeks geospasial EPSG:32749, 18 desa/kelurahan, dan jaringan jalan PostGIS...
        </p>
      </div>
    </div>

    <!-- Error Overlay State -->
    <div
      v-else-if="loadError"
      class="pointer-events-auto absolute inset-0 z-30 flex items-center justify-center bg-slate-950/85 p-4"
      role="alert"
    >
      <div
        class="max-w-md rounded-2xl border border-red-700/80 bg-slate-900 p-6 text-center shadow-2xl"
      >
        <h2 class="text-base font-bold text-red-300">Gagal Memuat Data Peta Isometrik</h2>
        <p class="mt-2 text-xs text-slate-200">{{ loadError }}</p>
        <button
          type="button"
          class="mt-4 inline-flex min-h-[44px] cursor-pointer items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-blue-500 focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:outline-none"
          @click="initializeEngine"
        >
          Muat Ulang Peta
        </button>
      </div>
    </div>

    <!-- Left Quick Landmark & Zoom Controls -->
    <aside
      aria-label="Navigasi Titik Penting dan Kontrol Zoom"
      class="pointer-events-none absolute bottom-4 left-3 z-20 flex flex-col gap-3 md:top-24 md:bottom-auto md:left-4"
    >
      <!-- Zoom & Reset Controls -->
      <div
        class="pointer-events-auto flex flex-row items-center gap-1.5 rounded-xl border border-slate-700/80 bg-slate-900/95 p-1.5 shadow-xl backdrop-blur-md md:flex-col"
      >
        <button
          type="button"
          class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-base font-bold text-slate-100 transition hover:border-blue-400 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          aria-label="Perbesar tampilan peta (Zoom In)"
          @click="zoomIn()"
        >
          +
        </button>

        <div class="px-2 py-1 text-center text-xs font-bold text-slate-200 tabular-nums">
          z{{ zoom }}{{ pixelScale > 1 ? ` (${pixelScale}x)` : '' }}
        </div>

        <button
          type="button"
          class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-base font-bold text-slate-100 transition hover:border-blue-400 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          aria-label="Perkecil tampilan peta (Zoom Out)"
          @click="zoomOut()"
        >
          -
        </button>

        <button
          type="button"
          class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-2.5 text-xs font-semibold text-slate-200 transition hover:border-blue-400 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
          aria-label="Kembalikan kamera ke Alun-Alun Bojonegoro"
          @click="resetView()"
        >
          Reset
        </button>
      </div>

      <!-- Direct Zoom Level Selector Pills (Desktop) -->
      <div
        class="pointer-events-auto hidden flex-col gap-1.5 rounded-xl border border-slate-700/80 bg-slate-900/95 p-2.5 shadow-xl backdrop-blur-md md:flex"
      >
        <p class="text-[11px] font-semibold tracking-wider text-slate-300 uppercase">
          Level Zoom Tile
        </p>
        <div class="grid grid-cols-2 gap-1.5">
          <button
            v-for="zSpec in metadata?.zoomLevels ?? []"
            :key="zSpec.z"
            type="button"
            class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer flex-col items-center justify-center rounded-lg border px-2 py-1 text-xs font-semibold transition tabular-nums focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
            :class="
              zoom === zSpec.z
                ? 'border-blue-500 bg-blue-950/90 text-blue-200'
                : 'border-slate-700 bg-slate-800/90 text-slate-300 hover:bg-slate-800'
            "
            @click="setZoomLevel(zSpec.z)"
          >
            <span>z={{ zSpec.z }}</span>
            <span class="text-[10px] text-slate-300">{{ zSpec.pixelResolution }}m/px</span>
          </button>
        </div>
      </div>

      <!-- Landmark Quick Jump List (Desktop) -->
      <div
        class="pointer-events-auto hidden max-h-80 w-64 flex-col rounded-xl border border-slate-700/80 bg-slate-900/95 p-3 shadow-xl backdrop-blur-md lg:flex"
      >
        <div class="mb-2 flex items-center justify-between">
          <h2 class="text-xs font-bold tracking-wider text-slate-200 uppercase">
            Titik Penting Bojonegoro
          </h2>
          <span class="text-[11px] text-slate-300 tabular-nums">
            {{ filteredLandmarks.length }} lokasi
          </span>
        </div>

        <div
          v-if="isSpatialLoading"
          class="rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-center text-xs text-slate-300"
        >
          Memuat daftar titik penting...
        </div>

        <div
          v-else-if="spatialError"
          class="rounded-lg border border-amber-800/70 bg-amber-950/40 p-3 text-center text-xs text-amber-200"
        >
          Daftar titik penting gagal dimuat.
        </div>

        <div
          v-else-if="filteredLandmarks.length === 0"
          class="rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-center text-xs text-slate-300"
        >
          Tidak ada titik penting yang cocok dengan pencarian.
        </div>

        <div v-else class="flex flex-col gap-1 overflow-y-auto pr-1">
          <button
            v-for="lm in filteredLandmarks"
            :key="lm.landmark_id"
            type="button"
            class="flex min-h-[44px] w-full cursor-pointer flex-col justify-center rounded-lg border border-transparent px-2.5 py-1.5 text-left transition hover:border-slate-700 hover:bg-slate-800/90 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
            @click="selectLandmark(lm)"
          >
            <span class="text-xs font-semibold text-slate-100">{{ lm.name }}</span>
            <span class="text-[11px] text-slate-300">
              Desa {{ lm.desa }} - {{ lm.category }}
            </span>
          </button>
        </div>
      </div>
    </aside>

    <!-- Right Coordinate & Ground-Anchor Spatial Inspector Panel -->
    <section
      aria-label="Inspeksi Koordinat dan Objek Spasial"
      class="pointer-events-none absolute right-3 bottom-3 left-3 z-20 flex flex-col items-end md:top-24 md:right-4 md:bottom-auto md:left-auto md:w-88"
    >
      <!-- Mobile Toggle Pill -->
      <button
        type="button"
        class="pointer-events-auto mb-2 inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-xl border border-slate-700 bg-slate-900/95 px-3.5 py-2 text-xs font-semibold text-slate-100 shadow-lg md:hidden"
        @click="showMobileInspector = !showMobileInspector"
      >
        <span>{{ showMobileInspector ? 'Sembunyikan Detail Klik' : 'Tampilkan Detail Klik' }}</span>
      </button>

      <div
        v-show="showMobileInspector"
        class="pointer-events-auto w-full rounded-2xl border border-slate-700/85 bg-slate-900/95 p-4 shadow-2xl backdrop-blur-md"
      >
        <!-- Active Spatial Feature Header -->
        <div v-if="activeFeatureSummary" class="border-b border-slate-800 pb-3">
          <div class="flex items-center justify-between gap-2">
            <span
              class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold"
              :class="activeFeatureSummary.badgeClass"
            >
              {{ activeFeatureSummary.badge }}
            </span>
            <button
              type="button"
              class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-2.5 text-xs font-semibold text-slate-200 transition hover:border-blue-400 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
              aria-label="Salin koordinat geografis dan UTM titik terpilih"
              @click="copySelectedCoordinates"
            >
              {{ copyFeedback ? 'Tersalin' : 'Salin Koordinat' }}
            </button>
          </div>

          <h2 class="mt-1.5 text-sm font-bold text-slate-100">
            {{ activeFeatureSummary.title }}
          </h2>
          <p class="mt-0.5 text-xs text-slate-300">
            {{ activeFeatureSummary.subtitle }}
          </p>

          <!-- Feature Attribute Grid -->
          <dl class="mt-3 grid grid-cols-2 gap-2">
            <div
              v-for="row in activeFeatureSummary.meta"
              :key="row.label"
              class="rounded-lg border border-slate-800 bg-slate-950/70 px-2.5 py-1.5"
            >
              <dt class="text-[10px] font-medium text-slate-400 uppercase">{{ row.label }}</dt>
              <dd class="mt-0.5 truncate text-xs font-semibold text-slate-100 tabular-nums">
                {{ row.value }}
              </dd>
            </div>
          </dl>
        </div>

        <!-- Bi-Directional Coordinate Chain Readout (Geographic <-> Projected <-> Isometric <-> Pixel) -->
        <div v-if="selectedInspect" class="mt-3 space-y-2">
          <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold tracking-wider text-slate-200 uppercase">
              Rantai Konversi Koordinat (Ground Anchor)
            </h3>
            <span class="text-[11px] font-semibold text-emerald-300 tabular-nums">
              EPSG:4326 &harr; 32749
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs tabular-nums">
            <div class="rounded-lg border border-slate-800 bg-slate-950/75 p-2">
              <p class="text-[10px] font-semibold text-slate-400 uppercase">
                1. Geografis (EPSG:4326)
              </p>
              <p class="mt-0.5 font-semibold text-slate-100">
                {{ selectedInspect.geographic.lat.toFixed(6) }},
                {{ selectedInspect.geographic.lng.toFixed(6) }}
              </p>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-950/75 p-2">
              <p class="text-[10px] font-semibold text-slate-400 uppercase">
                2. Terproyeksi (UTM 49S)
              </p>
              <p class="mt-0.5 font-semibold text-slate-100">
                E {{ selectedInspect.projected.easting.toFixed(1) }} m
              </p>
              <p class="text-[11px] text-slate-300">
                N {{ selectedInspect.projected.northing.toFixed(1) }} m
              </p>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-950/75 p-2">
              <p class="text-[10px] font-semibold text-slate-400 uppercase">
                3. Ruang Isometrik (2:1)
              </p>
              <p class="mt-0.5 font-semibold text-slate-100">
                X {{ selectedInspect.isometric.isoX.toFixed(1) }} m
              </p>
              <p class="text-[11px] text-slate-300">
                Y {{ selectedInspect.isometric.isoY.toFixed(1) }} m
              </p>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-950/75 p-2">
              <p class="text-[10px] font-semibold text-slate-400 uppercase">
                4. Piksel Global (z={{ selectedInspect.zoom }})
              </p>
              <p class="mt-0.5 font-semibold text-slate-100">
                px {{ Math.round(selectedInspect.pixel.px) }}, py
                {{ Math.round(selectedInspect.pixel.py) }}
              </p>
              <p class="text-[11px] text-slate-300">
                Tile ({{ Math.floor(selectedInspect.pixel.px / 256) }},
                {{ Math.floor(selectedInspect.pixel.py / 256) }})
              </p>
            </div>
          </div>
        </div>

        <!-- Live Cursor & Tile Engine Telemetry Footer -->
        <div
          class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 pt-2.5 text-[11px] text-slate-300 tabular-nums"
        >
          <span>
            Kursor: {{ hoveredCoord.lat.toFixed(5) }}, {{ hoveredCoord.lng.toFixed(5) }}
          </span>
          <span>
            Tile Cache: {{ loadedCount }} aktif
            <template v-if="pendingCount > 0"> ({{ pendingCount }} memuat)</template>
            <template v-if="errorCount > 0"> ({{ errorCount }} gagal)</template>
          </span>
        </div>
      </div>
    </section>

    <!-- Interactive Minimap Radar (Desktop & Tablet) -->
    <div
      v-if="metadata"
      class="pointer-events-none absolute bottom-4 right-[23.5rem] z-20 hidden flex-col items-end gap-2 md:flex"
    >
      <div
        v-if="showMinimap"
        class="pointer-events-auto w-60 rounded-xl border border-slate-700/85 bg-slate-900/95 p-2 shadow-2xl backdrop-blur-md"
      >
        <div class="mb-1.5 flex items-center justify-between gap-2">
          <span class="text-[11px] font-semibold tracking-wider text-slate-200 uppercase tabular-nums">
            Radar Peta (z={{ zoom }})
          </span>
          <button
            type="button"
            class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-2.5 text-xs font-semibold text-slate-200 transition hover:border-blue-400 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
            aria-label="Sembunyikan minimap radar peta"
            @click="showMinimap = false"
          >
            Tutup
          </button>
        </div>

        <div
          role="group"
          aria-label="Minimap radar peta isometrik Kecamatan Bojonegoro"
          class="relative aspect-[2/1] w-full cursor-crosshair overflow-hidden rounded-lg border border-slate-700 bg-slate-950 touch-none"
          @pointerdown="handleMinimapPointerDown"
          @pointermove="handleMinimapPointerMove"
          @pointerup="handleMinimapPointerUp"
          @pointercancel="handleMinimapPointerUp"
        >
          <img
            :src="masterPreviewSrc"
            alt="Minimap overview seluruh Kecamatan Bojonegoro"
            class="h-full w-full object-cover"
            style="image-rendering: pixelated"
            draggable="false"
          />
          <div
            class="pointer-events-none absolute border-2 border-blue-400 bg-blue-500/20 shadow-[0_0_0_1px_rgba(15,23,42,0.85)]"
            :style="minimapViewportStyle"
          />
        </div>
      </div>

      <button
        v-else
        type="button"
        class="pointer-events-auto inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-xl border border-slate-700/85 bg-slate-900/95 px-3.5 py-2 text-xs font-semibold text-slate-100 shadow-xl backdrop-blur-md transition hover:border-blue-400 hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
        aria-label="Tampilkan minimap radar peta"
        @click="showMinimap = true"
      >
        Radar Minimap
      </button>
    </div>

    <!-- Coordinate Transformation Verification Modal -->
    <div
      v-if="showCoordVerifyModal"
      class="pointer-events-auto fixed inset-0 z-40 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="coord-verify-modal-title"
    >
      <div
        class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl"
      >
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 pb-3">
          <div>
            <h2 id="coord-verify-modal-title" class="text-base font-bold text-slate-100">
              Verifikasi Konversi Koordinat Dua Arah &amp; Master Isometric Preview
            </h2>
            <p class="mt-0.5 text-xs text-slate-300">
              Bukti matematis rantai transformasi Geographic (EPSG:4326) &harr; Projected
              (EPSG:32749) &harr; Isometric (2:1) &harr; Pixel (256x256 tile)
            </p>
          </div>
          <button
            type="button"
            class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-3 text-xs font-semibold text-slate-200 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
            aria-label="Tutup jendela verifikasi koordinat"
            @click="showCoordVerifyModal = false"
          >
            Tutup
          </button>
        </div>

        <!-- Accuracy Metrics Banner -->
        <div
          v-if="metadata?.coordinateVerification"
          class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3"
        >
          <div class="rounded-xl border border-emerald-700/60 bg-emerald-950/50 p-3">
            <p class="text-xs font-semibold text-emerald-300">Status Uji Round-Trip</p>
            <p class="mt-1 text-sm font-bold text-slate-100">
              {{ metadata.coordinateVerification.passed ? 'LULUS AKURASI TINGGI' : 'PERLU CEK' }}
            </p>
          </div>
          <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3 tabular-nums">
            <p class="text-xs font-semibold text-slate-300">Galat Maksimum Meter (UTM 49S)</p>
            <p class="mt-1 text-sm font-bold text-slate-100">
              {{ metadata.coordinateVerification.maxMeterError.toFixed(6) }} meter
            </p>
          </div>
          <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3 tabular-nums">
            <p class="text-xs font-semibold text-slate-300">Galat Maksimum Derajat (WGS84)</p>
            <p class="mt-1 text-sm font-bold text-slate-100">
              {{ metadata.coordinateVerification.maxDegreeError.toFixed(9) }}&deg;
            </p>
          </div>
        </div>

        <!-- Sample Reference Coordinate Table -->
        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-800">
          <table class="w-full text-left text-xs tabular-nums">
            <thead class="bg-slate-950 text-slate-300">
              <tr>
                <th class="px-3 py-2.5 font-semibold">Titik Uji Bojonegoro</th>
                <th class="px-3 py-2.5 font-semibold">EPSG:4326 (Lng, Lat)</th>
                <th class="px-3 py-2.5 font-semibold">EPSG:32749 (E, N)</th>
                <th class="px-3 py-2.5 font-semibold">Isometrik (isoX, isoY)</th>
                <th class="px-3 py-2.5 font-semibold">Piksel z=4 (px, py)</th>
                <th class="px-3 py-2.5 font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 bg-slate-900/80">
              <tr
                v-for="sample in metadata?.coordinateVerification.samples ?? []"
                :key="sample.name"
              >
                <td class="px-3 py-2 font-semibold text-slate-100">{{ sample.name }}</td>
                <td class="px-3 py-2 text-slate-200">
                  {{ sample.geographic.lng.toFixed(5) }}, {{ sample.geographic.lat.toFixed(5) }}
                </td>
                <td class="px-3 py-2 text-slate-200">
                  {{ sample.projectedEpsg32749.easting.toFixed(1) }},
                  {{ sample.projectedEpsg32749.northing.toFixed(1) }}
                </td>
                <td class="px-3 py-2 text-slate-200">
                  {{ sample.isometric.isoX.toFixed(1) }}, {{ sample.isometric.isoY.toFixed(1) }}
                </td>
                <td class="px-3 py-2 text-slate-200">
                  {{ (sample.pixelZ4 ?? sample.pixelZ3)?.px.toFixed(0) }},
                  {{ (sample.pixelZ4 ?? sample.pixelZ3)?.py.toFixed(0) }}
                </td>
                <td class="px-3 py-1.5">
                  <button
                    type="button"
                    class="inline-flex min-h-[44px] cursor-pointer items-center rounded-lg border border-blue-600/70 bg-blue-950/80 px-2.5 py-1 text-xs font-semibold text-blue-200 hover:bg-blue-900 focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:outline-none"
                    @click="
                      () => {
                        flyToGeographic(sample.geographic.lng, sample.geographic.lat, 4)
                        showCoordVerifyModal = false
                      }
                    "
                  >
                    Lompat
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Master Preview Image -->
        <div class="mt-4 rounded-xl border border-slate-800 bg-slate-950 p-3">
          <p class="mb-2 text-xs font-semibold text-slate-200">
            Sample Master Isometric Preview (z=2, 2048x1024 px, Metatile Overlap 64px)
          </p>
          <img
            :src="masterPreviewSrc"
            alt="Master Preview Peta Pixel Isometrik Kecamatan Bojonegoro"
            class="h-auto w-full rounded-lg border border-slate-800"
            style="image-rendering: pixelated"
            loading="lazy"
          />
        </div>
      </div>
    </div>
  </div>
</template>

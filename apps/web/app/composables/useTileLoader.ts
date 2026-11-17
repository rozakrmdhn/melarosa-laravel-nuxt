import { ref } from 'vue'
import { resolveTileUrl } from '~/services/isometricTileService'

export type TileStatus = 'loading' | 'loaded' | 'error'

export interface CachedTileEntry {
  key: string
  z: number
  x: number
  y: number
  url: string
  status: TileStatus
  image: HTMLImageElement | null
  lastUsedAt: number
}

export interface FallbackTileCrop {
  image: HTMLImageElement
  sx: number
  sy: number
  sw: number
  sh: number
}

const MAX_CACHE_ENTRIES = 420
const sharedTileCache = new Map<string, CachedTileEntry>()

function makeKey(z: number, x: number, y: number, cacheVersion?: string): string {
  return cacheVersion ? `${z}/${x}/${y}@${cacheVersion}` : `${z}/${x}/${y}`
}

export function useTileLoader(onTileReady?: () => void) {
  const loadedCount = ref(0)
  const pendingCount = ref(0)
  const errorCount = ref(0)

  function syncCounters() {
    let loaded = 0
    let pending = 0
    let errors = 0
    for (const entry of sharedTileCache.values()) {
      if (entry.status === 'loaded') loaded++
      else if (entry.status === 'loading') pending++
      else if (entry.status === 'error') errors++
    }
    loadedCount.value = loaded
    pendingCount.value = pending
    errorCount.value = errors
  }

  function pruneCacheIfNeeded() {
    if (sharedTileCache.size <= MAX_CACHE_ENTRIES) return
    const entries = Array.from(sharedTileCache.values()).sort(
      (a, b) => a.lastUsedAt - b.lastUsedAt,
    )
    const toRemove = entries.slice(0, sharedTileCache.size - MAX_CACHE_ENTRIES)
    for (const item of toRemove) {
      if (item.status !== 'loading' && item.z > 2) {
        sharedTileCache.delete(item.key)
      }
    }
  }

  function requestTile(
    z: number,
    x: number,
    y: number,
    urlTemplate = '/tiles/bojonegoro-isometric/{z}/{x}/{y}.png',
    cacheVersion?: string,
  ): CachedTileEntry | null {
    if (typeof window === 'undefined') return null
    const key = makeKey(z, x, y, cacheVersion)
    const now = performance.now()
    const existing = sharedTileCache.get(key)

    if (existing) {
      existing.lastUsedAt = now
      return existing
    }

    const url = resolveTileUrl(z, x, y, urlTemplate, cacheVersion)
    const img = new Image()
    img.decoding = 'async'

    const entry: CachedTileEntry = {
      key,
      z,
      x,
      y,
      url,
      status: 'loading',
      image: null,
      lastUsedAt: now,
    }

    sharedTileCache.set(key, entry)
    pruneCacheIfNeeded()
    syncCounters()

    img.onload = () => {
      entry.status = 'loaded'
      entry.image = img
      entry.lastUsedAt = performance.now()
      syncCounters()
      if (onTileReady) onTileReady()
    }

    img.onerror = () => {
      entry.status = 'error'
      entry.image = null
      syncCounters()
      if (onTileReady) onTileReady()
    }

    img.src = url
    return entry
  }

  function getFallbackTileCrop(
    z: number,
    x: number,
    y: number,
    tileSize = 256,
    cacheVersion?: string,
  ): FallbackTileCrop | null {
    for (let parentZ = z - 1; parentZ >= 0; parentZ--) {
      const scaleDiff = 1 << (z - parentZ)
      const parentX = Math.floor(x / scaleDiff)
      const parentY = Math.floor(y / scaleDiff)
      const parentEntry =
        sharedTileCache.get(makeKey(parentZ, parentX, parentY, cacheVersion)) ??
        sharedTileCache.get(makeKey(parentZ, parentX, parentY))
      if (parentEntry && parentEntry.status === 'loaded' && parentEntry.image) {
        const subSize = tileSize / scaleDiff
        const subX = (x % scaleDiff) * subSize
        const subY = (y % scaleDiff) * subSize
        return {
          image: parentEntry.image,
          sx: subX,
          sy: subY,
          sw: subSize,
          sh: subSize,
        }
      }
    }
    return null
  }

  function preloadBaseZoom(
    urlTemplate = '/tiles/bojonegoro-isometric/{z}/{x}/{y}.png',
    cacheVersion?: string,
  ) {
    if (typeof window === 'undefined') return
    for (let x = 0; x < 2; x++) {
      requestTile(0, x, 0, urlTemplate, cacheVersion)
    }
    for (let y = 0; y < 2; y++) {
      for (let x = 0; x < 4; x++) {
        requestTile(1, x, y, urlTemplate, cacheVersion)
      }
    }
  }

  return {
    loadedCount,
    pendingCount,
    errorCount,
    requestTile,
    getFallbackTileCrop,
    preloadBaseZoom,
  }
}

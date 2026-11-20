import { useToast as useNuxtUiToast } from '@nuxt/ui/runtime/composables/useToast'
import type { Toast } from '@nuxt/ui/runtime/composables/useToast'

const DEFAULT_COOLDOWN_MS = 2500
const GLOBAL_BURST_COOLDOWN_MS = 350
const MAX_COOLDOWN_ENTRIES = 100

interface CooldownRecord {
  toastId: string
  lastTriggeredAt: number
}

const recentToastMap = new Map<string, CooldownRecord>()
let lastAnyErrorToastAt = 0

function normalizeToastField(val: unknown): string {
  if (typeof val === 'string') {
    return val.trim().replace(/\s+/g, ' ').toLowerCase()
  }
  return ''
}

function buildToastSignature(toast: Partial<Toast> & Record<string, any>): string {
  if (toast.id !== undefined && toast.id !== null && String(toast.id).trim() !== '') {
    return `id:${String(toast.id).trim()}`
  }
  const color = normalizeToastField(toast.color || 'primary')
  const title = normalizeToastField(toast.title)
  const description = normalizeToastField(toast.description)
  return `sig:${color}|${title}|${description}`
}

function pruneCooldownMap(now: number, maxAgeMs: number) {
  if (recentToastMap.size <= MAX_COOLDOWN_ENTRIES) return
  for (const [key, entry] of recentToastMap.entries()) {
    if (now - entry.lastTriggeredAt > maxAgeMs) {
      recentToastMap.delete(key)
    }
  }
}

export function useToast() {
  const base = useNuxtUiToast()

  function add(toast: Partial<Toast> & { cooldownMs?: number; bypassCooldown?: boolean }): Toast {
    if (import.meta.server || toast?.bypassCooldown) {
      return base.add(toast as Toast)
    }

    const now = Date.now()
    const cooldownMs =
      typeof toast.cooldownMs === 'number' && Number.isFinite(toast.cooldownMs) && toast.cooldownMs >= 0
        ? toast.cooldownMs
        : DEFAULT_COOLDOWN_MS

    pruneCooldownMap(now, Math.max(cooldownMs * 2, 10000))

    const signature = buildToastSignature(toast)
    const previous = recentToastMap.get(signature)

    // 1. Cooldown untuk notifikasi dengan isi (signature) yang sama persis
    if (previous && now - previous.lastTriggeredAt < cooldownMs) {
      const activeToast = base.toasts.value.find((t: any) => String(t.id) === previous.toastId)
      if (activeToast) {
        return activeToast
      }
      return {
        id: previous.toastId,
        open: false,
        ...toast,
      } as Toast
    }

    // 2. Cooldown burst untuk dua error toast beruntun dalam waktu sangat singkat
    //    (misal: error otomatis dari $http onResponseError + catch block di komponen)
    const isErrorToast = toast.color === 'error'
    if (isErrorToast && now - lastAnyErrorToastAt < GLOBAL_BURST_COOLDOWN_MS) {
      const latestErrorToast = [...base.toasts.value]
        .reverse()
        .find((t: any) => t.color === 'error' && t.open !== false)
      if (latestErrorToast) {
        return latestErrorToast
      }
    }

    // 3. Jika toast dengan signature yang sama masih tampil di layar walaupun lewat cooldown,
    //    gunakan ID yang sama supaya diperbarui di tempat tanpa menumpuk kartu baru
    let resolvedId = toast.id
    if (!resolvedId && previous) {
      const stillOpen = base.toasts.value.find(
        (t: any) => String(t.id) === previous.toastId && t.open !== false
      )
      if (stillOpen) {
        resolvedId = stillOpen.id
      }
    }

    const { cooldownMs: _omittedCooldown, bypassCooldown: _omittedBypass, ...cleanToast } = toast
    const created = base.add({
      ...cleanToast,
      ...(resolvedId ? { id: resolvedId } : {}),
    } as Toast)

    recentToastMap.set(signature, {
      toastId: String(created.id),
      lastTriggeredAt: now,
    })

    if (isErrorToast) {
      lastAnyErrorToastAt = now
    }

    return created
  }

  return {
    ...base,
    add,
  }
}

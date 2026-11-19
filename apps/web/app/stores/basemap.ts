import { defineStore } from 'pinia';
import {
  DEFAULT_FALLBACK_BASEMAPS,
  normalizeBasemapAlias,
  type BasemapEngine,
  type BasemapItem,
} from '~/types/basemap';

let inflightFetch: Promise<void> | null = null;

export const useBasemapStore = defineStore('basemap', () => {
  const items = ref<BasemapItem[]>([...DEFAULT_FALLBACK_BASEMAPS]);
  const defaultCode = ref<string>('esri');
  const loaded = ref(false);
  const loading = ref(false);

  async function ensureLoaded(force = false): Promise<void> {
    if (loaded.value && !force) return;
    if (inflightFetch && !force) return inflightFetch;

    loading.value = true;
    inflightFetch = (async () => {
      try {
        const res = await $http<{
          ok: boolean;
          default_kode?: string;
          data?: BasemapItem[];
        }>('basemaps', {
          method: 'GET',
        });

        if (res?.ok && Array.isArray(res.data) && res.data.length > 0) {
          items.value = res.data;
          const def = res.data.find((b) => b.is_default) || res.data[0];
          defaultCode.value = res.default_kode || def?.kode || 'esri';
        }
        loaded.value = true;
      } catch {
        // Pertahankan fallback default jika endpoint publik belum terjangkau
      } finally {
        loading.value = false;
        inflightFetch = null;
      }
    })();

    return inflightFetch;
  }

  function listForEngine(engine: BasemapEngine = 'all'): BasemapItem[] {
    return items.value.filter((b) => {
      if (!b.is_active) return false;
      if (engine === 'openlayers') return b.supports_openlayers;
      if (engine === 'maplibre') return b.supports_maplibre;
      return true;
    });
  }

  function getByCode(code?: string | null, engine: BasemapEngine = 'all'): BasemapItem | null {
    const raw = String(code || '').trim().toLowerCase();
    if (raw === 'none' || raw === 'kanvas') return null;

    const pool = listForEngine(engine);
    const exact = pool.find((b) => b.kode.toLowerCase() === raw);
    if (exact) return exact;

    const alias = normalizeBasemapAlias(raw);
    const byAlias = pool.find((b) => b.kode.toLowerCase() === alias);
    if (byAlias) return byAlias;

    return pool.find((b) => b.is_default) || pool[0] || DEFAULT_FALLBACK_BASEMAPS[0] || null;
  }

  function isValidCode(code?: string | null, allowNone = true): boolean {
    const raw = String(code || '').trim().toLowerCase();
    if (!raw) return false;
    if (allowNone && (raw === 'none' || raw === 'kanvas')) return true;
    const alias = normalizeBasemapAlias(raw);
    return items.value.some((b) => b.kode.toLowerCase() === raw || b.kode.toLowerCase() === alias);
  }

  // ─── Admin CRUD API ────────────────────────────────────────────────────────
  async function fetchAdminBasemaps(search?: string) {
    return $http<{ ok: boolean; data: BasemapItem[] }>('admin/basemaps', {
      method: 'GET',
      query: search?.trim() ? { search: search.trim() } : undefined,
    });
  }

  async function createBasemap(payload: Partial<BasemapItem>) {
    const res = await $http<{ ok: boolean; message?: string; data: BasemapItem }>('admin/basemaps', {
      method: 'POST',
      body: payload,
    });
    await ensureLoaded(true);
    return res;
  }

  async function updateBasemap(id: string, payload: Partial<BasemapItem>) {
    const res = await $http<{ ok: boolean; message?: string; data: BasemapItem }>(`admin/basemaps/${id}`, {
      method: 'PUT',
      body: payload,
    });
    await ensureLoaded(true);
    return res;
  }

  async function setDefaultBasemap(id: string) {
    const res = await $http<{ ok: boolean; message?: string; data: BasemapItem }>(
      `admin/basemaps/${id}/set-default`,
      { method: 'PATCH' },
    );
    await ensureLoaded(true);
    return res;
  }

  async function deleteBasemap(id: string) {
    const res = await $http<{ ok: boolean; message?: string }>(`admin/basemaps/${id}`, {
      method: 'DELETE',
    });
    await ensureLoaded(true);
    return res;
  }

  return {
    items,
    defaultCode,
    loaded,
    loading,
    ensureLoaded,
    listForEngine,
    getByCode,
    isValidCode,
    fetchAdminBasemaps,
    createBasemap,
    updateBasemap,
    setDefaultBasemap,
    deleteBasemap,
  };
});

import { computed } from "vue";
import { useRekapJalanApi } from "~/composables/useRekapJalanApi";
import type { RekapKecamatanItem, RekapDesaItem, RekapRuasItem } from "~/types/rekap-jalan";

export interface CountyRoadSummary {
  totalKecamatan: number;
  totalDesa: number;
  totalRuas: number;
  totalSegmen: number;
  totalPanjangMeter: number;
  panjangDibangunMeter: number;
  panjangBelumMeter: number;
  progress: number;
}

let inFlightKecamatanPromise: Promise<RekapKecamatanItem[]> | null = null;
const inFlightDesaPromises = new Map<number, Promise<RekapDesaItem[]>>();
const inFlightRuasPromises = new Map<string, Promise<RekapRuasItem[]>>();

export function normalizeWilayahKey(name?: string | null): string {
  if (!name) return "";
  return String(name)
    .replace(/^(kecamatan|kec\.|desa|kelurahan|kel\.)\s+/i, "")
    .replace(/\s*\(kota\)$/i, "")
    .trim()
    .toLowerCase();
}

export function formatDistanceMeters(meters?: number | null): string {
  if (meters === null || meters === undefined || Number.isNaN(Number(meters))) return "-";
  const val = Number(meters);
  if (Math.abs(val) >= 1000) {
    const km = val / 1000;
    const digits = km >= 100 ? 1 : 2;
    return `${km.toLocaleString("id-ID", {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    })} km`;
  }
  return `${Math.round(val).toLocaleString("id-ID")} m`;
}

export function formatPercentId(val?: number | null): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return "0%";
  const clamped = Math.min(Math.max(Number(val), 0), 100);
  return `${clamped.toLocaleString("id-ID", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })}%`;
}

export function formatCountId(val?: number | null): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return "0";
  return Math.round(Number(val)).toLocaleString("id-ID");
}

/**
 * Shared consumer-friendly state for Bojonegoro road infrastructure summaries.
 */
export function useMapRekapOverview() {
  const api = useRekapJalanApi();

  const kecamatanStats = useState<RekapKecamatanItem[]>("melarosa_map_rekap_kec", () => []);
  const loadingKecamatan = useState<boolean>("melarosa_map_rekap_kec_loading", () => false);
  const errorKecamatan = useState<string | null>("melarosa_map_rekap_kec_error", () => null);
  const hasLoadedKecamatan = useState<boolean>("melarosa_map_rekap_kec_loaded", () => false);

  const desaCache = useState<Record<number, RekapDesaItem[]>>("melarosa_map_rekap_desa_cache", () => ({}));
  const loadingDesaId = useState<number | null>("melarosa_map_rekap_desa_loading", () => null);
  const errorDesa = useState<string | null>("melarosa_map_rekap_desa_error", () => null);

  const ruasCache = useState<Record<string, RekapRuasItem[]>>("melarosa_map_rekap_ruas_cache", () => ({}));
  const loadingRuasDesaId = useState<string | null>("melarosa_map_rekap_ruas_loading", () => null);
  const errorRuas = useState<string | null>("melarosa_map_rekap_ruas_error", () => null);

  async function ensureKecamatanStats(force = false): Promise<RekapKecamatanItem[]> {
    if (hasLoadedKecamatan.value && kecamatanStats.value.length > 0 && !force) {
      return kecamatanStats.value;
    }
    if (inFlightKecamatanPromise && !force) {
      return inFlightKecamatanPromise;
    }

    loadingKecamatan.value = true;
    errorKecamatan.value = null;

    inFlightKecamatanPromise = (async () => {
      try {
        const res = await api.fetchRekapKecamatan("ruas");
        const list = Array.isArray(res) ? res : [];
        kecamatanStats.value = list;
        hasLoadedKecamatan.value = true;
        return list;
      } catch (err: any) {
        errorKecamatan.value = err?.message || "Data ringkasan infrastruktur belum dapat dimuat.";
        return kecamatanStats.value;
      } finally {
        loadingKecamatan.value = false;
        inFlightKecamatanPromise = null;
      }
    })();

    return inFlightKecamatanPromise;
  }

  async function fetchDesaForKecamatan(idKecamatan: number, force = false): Promise<RekapDesaItem[]> {
    if (!idKecamatan) return [];
    if (!force && Array.isArray(desaCache.value[idKecamatan])) {
      return desaCache.value[idKecamatan];
    }
    if (!force && inFlightDesaPromises.has(idKecamatan)) {
      return inFlightDesaPromises.get(idKecamatan)!;
    }

    loadingDesaId.value = idKecamatan;
    errorDesa.value = null;

    const promise = (async () => {
      try {
        const res = await api.fetchRekapDesa(idKecamatan, "ruas");
        const list = Array.isArray(res) ? res : [];
        desaCache.value = {
          ...desaCache.value,
          [idKecamatan]: list,
        };
        return list;
      } catch (err: any) {
        errorDesa.value = err?.message || "Daftar desa belum dapat dimuat.";
        return desaCache.value[idKecamatan] || [];
      } finally {
        if (loadingDesaId.value === idKecamatan) {
          loadingDesaId.value = null;
        }
        inFlightDesaPromises.delete(idKecamatan);
      }
    })();

    inFlightDesaPromises.set(idKecamatan, promise);
    return promise;
  }

  async function fetchRuasForDesa(idDesa: string | number, force = false): Promise<RekapRuasItem[]> {
    const key = String(idDesa || "");
    if (!key) return [];
    if (!force && Array.isArray(ruasCache.value[key])) {
      return ruasCache.value[key];
    }
    if (!force && inFlightRuasPromises.has(key)) {
      return inFlightRuasPromises.get(key)!;
    }

    loadingRuasDesaId.value = key;
    errorRuas.value = null;

    const promise = (async () => {
      try {
        const res = await api.fetchRekapRuasByDesa(idDesa, "ruas");
        const list = Array.isArray(res) ? res : [];
        ruasCache.value = {
          ...ruasCache.value,
          [key]: list,
        };
        return list;
      } catch (err: any) {
        errorRuas.value = err?.message || "Daftar ruas jalan belum dapat dimuat.";
        return ruasCache.value[key] || [];
      } finally {
        if (loadingRuasDesaId.value === key) {
          loadingRuasDesaId.value = null;
        }
        inFlightRuasPromises.delete(key);
      }
    })();

    inFlightRuasPromises.set(key, promise);
    return promise;
  }

  const countySummary = computed<CountyRoadSummary | null>(() => {
    const list = kecamatanStats.value;
    if (!list || list.length === 0) return null;

    let totalDesa = 0;
    let totalRuas = 0;
    let totalSegmen = 0;
    let totalPanjangMeter = 0;
    let panjangDibangunMeter = 0;
    let panjangBelumMeter = 0;

    for (const item of list) {
      totalDesa += Number(item.jumlah_desa) || 0;
      totalRuas += Number(item.jumlah_ruas) || 0;
      totalSegmen += Number(item.jumlah_segmen) || 0;
      totalPanjangMeter += Number(item.total_panjang) || 0;
      panjangDibangunMeter += Number(item.panjang_dibangun) || 0;
      panjangBelumMeter += Number(item.panjang_belum) || 0;
    }

    const progress = totalPanjangMeter > 0
      ? Math.min(Math.max((panjangDibangunMeter / totalPanjangMeter) * 100, 0), 100)
      : 0;

    return {
      totalKecamatan: list.length,
      totalDesa,
      totalRuas,
      totalSegmen,
      totalPanjangMeter,
      panjangDibangunMeter,
      panjangBelumMeter,
      progress,
    };
  });

  function findKecamatanStatByName(name?: string | null): RekapKecamatanItem | null {
    const key = normalizeWilayahKey(name);
    if (!key) return null;
    return (
      kecamatanStats.value.find((item) => normalizeWilayahKey(item.nama_kecamatan) === key) ||
      null
    );
  }

  return {
    kecamatanStats,
    loadingKecamatan,
    errorKecamatan,
    hasLoadedKecamatan,
    desaCache,
    loadingDesaId,
    errorDesa,
    ruasCache,
    loadingRuasDesaId,
    errorRuas,
    countySummary,
    ensureKecamatanStats,
    fetchDesaForKecamatan,
    fetchRuasForDesa,
    findKecamatanStatByName,
  };
}

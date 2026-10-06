import { defineStore } from 'pinia';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';

export interface KecamatanItem {
  id: number;
  nama_kecamatan: string;
  [key: string]: any;
}

export interface DesaItem {
  id: number;
  nama_desa: string;
  id_kecamatan: number;
  [key: string]: any;
}

export const useWilayahStore = defineStore('wilayah', () => {
  const api = useInfrastrukturApi();

  const kecamatanList = ref<KecamatanItem[]>([]);
  const desaByKecamatan = ref<Record<number, DesaItem[]>>({});
  
  const loadingKecamatan = ref(false);
  const isKecamatanLoaded = ref(false);
  const loadingDesaMap = ref<Record<number, boolean>>({});

  // Promise inflight deduplication to prevent duplicate concurrent network requests
  let kecamatanPromise: Promise<KecamatanItem[]> | null = null;
  const desaPromises: Record<number, Promise<DesaItem[]>> = {};

  /**
   * Fetch daftar kecamatan dengan in-memory cache.
   * Hanya memanggil API satu kali selama aplikasi aktif.
   */
  async function getKecamatanList(forceRefresh = false): Promise<KecamatanItem[]> {
    if (!forceRefresh && isKecamatanLoaded.value && kecamatanList.value.length > 0) {
      return kecamatanList.value;
    }

    if (kecamatanPromise) {
      return kecamatanPromise;
    }

    loadingKecamatan.value = true;
    kecamatanPromise = (async () => {
      try {
        const res = await api.fetchKecamatanList();
        const data = res?.data || (Array.isArray(res) ? res : []);
        kecamatanList.value = data;
        isKecamatanLoaded.value = true;
        return data;
      } catch (err) {
        console.error('Gagal memuat data wilayah kecamatan:', err);
        return [];
      } finally {
        loadingKecamatan.value = false;
        kecamatanPromise = null;
      }
    })();

    return kecamatanPromise;
  }

  /**
   * Fetch daftar desa berdasarkan ID kecamatan dengan in-memory cache.
   * Hanya memanggil API satu kali untuk setiap kecamatan.
   */
  async function getDesaList(idKecamatan?: number | null, forceRefresh = false): Promise<DesaItem[]> {
    if (!idKecamatan) {
      return [];
    }

    const kecId = Number(idKecamatan);
    if (isNaN(kecId) || kecId <= 0) {
      return [];
    }

    if (!forceRefresh && desaByKecamatan.value[kecId] && desaByKecamatan.value[kecId].length > 0) {
      return desaByKecamatan.value[kecId];
    }

    if (desaPromises[kecId]) {
      return desaPromises[kecId];
    }

    loadingDesaMap.value[kecId] = true;
    desaPromises[kecId] = (async () => {
      try {
        const res = await api.fetchDesaList(kecId);
        const data = res?.data || (Array.isArray(res) ? res : []);
        desaByKecamatan.value = {
          ...desaByKecamatan.value,
          [kecId]: data,
        };
        return data;
      } catch (err) {
        console.error(`Gagal memuat data wilayah desa untuk kecamatan ${kecId}:`, err);
        return [];
      } finally {
        loadingDesaMap.value[kecId] = false;
        delete desaPromises[kecId];
      }
    })();

    return desaPromises[kecId];
  }

  function isDesaLoading(idKecamatan?: number | null): boolean {
    if (!idKecamatan) return false;
    return !!loadingDesaMap.value[Number(idKecamatan)];
  }

  function getKecamatanName(id?: number | null): string | null {
    if (!id) return null;
    const match = kecamatanList.value.find((k) => k.id === Number(id));
    return match ? match.nama_kecamatan : null;
  }

  function getDesaName(id?: number | null, idKecamatan?: number | null): string | null {
    if (!id) return null;
    const numId = Number(id);

    if (idKecamatan && desaByKecamatan.value[Number(idKecamatan)]) {
      const match = desaByKecamatan.value[Number(idKecamatan)].find((d) => d.id === numId);
      if (match) return match.nama_desa;
    }

    for (const list of Object.values(desaByKecamatan.value)) {
      const match = list.find((d) => d.id === numId);
      if (match) return match.nama_desa;
    }

    return null;
  }

  function invalidateCache() {
    kecamatanList.value = [];
    desaByKecamatan.value = {};
    isKecamatanLoaded.value = false;
  }

  return {
    kecamatanList,
    desaByKecamatan,
    loadingKecamatan,
    isKecamatanLoaded,
    getKecamatanList,
    getDesaList,
    isDesaLoading,
    getKecamatanName,
    getDesaName,
    invalidateCache,
  };
});

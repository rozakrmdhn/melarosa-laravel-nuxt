import type {
  KategoriJalan,
  RekapKecamatanItem,
  RekapDesaItem,
  RekapRuasItem,
  RekapSegmenItem,
  RekapFilterParams,
} from '~/types/rekap-jalan';

interface ApiResponse<T> {
  status: string;
  message: string;
  result: T;
}

export function useRekapJalanApi() {
  const config = useRuntimeConfig();
  const baseUrl = (config.public?.rekapApiUrl as string) || 'https://api-melarosa.saggaserv.my.id/api/v1';

  async function requestApi<T>(path: string, queryParams: Record<string, any> = {}): Promise<T> {
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    const url = `${baseUrl.replace(/\/$/, '')}${cleanPath}`;

    // Clean undefined/null/empty string query params
    const cleanQuery: Record<string, any> = {};
    for (const [key, val] of Object.entries(queryParams)) {
      if (val !== null && val !== undefined && val !== '' && val !== 'semua' && val !== 'all') {
        cleanQuery[key] = val;
      }
    }

    const res = await $fetch<ApiResponse<T>>(url, {
      method: 'GET',
      query: cleanQuery,
      credentials: 'omit',
      headers: {
        Accept: 'application/json',
      },
    });

    return res.result;
  }

  /**
   * Level 1: Agregat Tingkat Kecamatan
   */
  async function fetchRekapKecamatan(
    kategori: KategoriJalan = 'ruas',
    params: RekapFilterParams = {}
  ): Promise<RekapKecamatanItem[]> {
    const endpoint = kategori === 'non_ruas' ? '/rekap/non-ruas/kecamatan' : '/rekap/kecamatan';
    return requestApi<RekapKecamatanItem[]>(endpoint, params);
  }

  /**
   * Level 2: Agregat Tingkat Desa dalam Kecamatan
   */
  async function fetchRekapDesa(
    idKecamatan?: number | null,
    kategori: KategoriJalan = 'ruas',
    params: RekapFilterParams = {}
  ): Promise<RekapDesaItem[]> {
    const endpoint = kategori === 'non_ruas' ? '/rekap/non-ruas/desa' : '/rekap/desa';
    const query: Record<string, any> = { ...params };
    if (idKecamatan) query.id_kecamatan = idKecamatan;
    return requestApi<RekapDesaItem[]>(endpoint, query);
  }

  /**
   * Level 3: Daftar Ruas atau Segmen Mandiri per Desa
   */
  async function fetchRekapRuasByDesa(
    idDesa: number | string,
    kategori: KategoriJalan = 'ruas',
    params: RekapFilterParams = {}
  ): Promise<RekapRuasItem[]> {
    const endpoint =
      kategori === 'non_ruas'
        ? `/rekap/non-ruas/desa/${idDesa}/ruas`
        : `/rekap/desa/${idDesa}/ruas`;
    return requestApi<RekapRuasItem[]>(endpoint, params);
  }

  /**
   * Level 4: Rincian Segmen Fisik Hasil Pembangunan
   */
  async function fetchDetailSegmen(idRuasOrSegmen: string | number): Promise<RekapSegmenItem[]> {
    return requestApi<RekapSegmenItem[]>(`/rekap/ruas/${idRuasOrSegmen}/segmen`);
  }

  return {
    fetchRekapKecamatan,
    fetchRekapDesa,
    fetchRekapRuasByDesa,
    fetchDetailSegmen,
  };
}

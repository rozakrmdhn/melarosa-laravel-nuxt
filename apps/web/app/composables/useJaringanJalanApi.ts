import type {
  CakupanDesaItem,
  CakupanDesaSummary,
  HierarkiJalan,
  JalanKabupatenItem,
  JaringanJalanRuasFeature,
  RtrwAnalisaDetail,
  RtrwAnalisaItem,
  RtrwAnalisaMapIndex,
  RtrwAnalisaSummary,
} from '~/types/jaringan-jalan';

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface ApiResponse<T> {
  ok: boolean;
  data: T;
  message?: string;
  meta?: PaginationMeta;
  summary?: any;
}

export function useJaringanJalanApi() {
  async function fetchAnalisaSummary(params?: Record<string, any>) {
    return $http<{ ok: boolean } & RtrwAnalisaSummary>('admin/jaringan-jalan/analisa-spasial/summary', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchAnalisaList(params?: Record<string, any>) {
    return $http<ApiResponse<RtrwAnalisaItem[]>>('admin/jaringan-jalan/analisa-spasial', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchAnalisaMapIndex(params?: Record<string, any>) {
    return $http<{ ok: boolean } & RtrwAnalisaMapIndex>('admin/jaringan-jalan/analisa-spasial/map-index', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchAnalisaDetail(rtrwId: number) {
    return $http<{ ok: boolean } & RtrwAnalisaDetail>(`admin/jaringan-jalan/analisa-spasial/${rtrwId}`, {
      method: 'GET',
    });
  }

  async function runAnalisaSpasial(payload?: { id_kecamatan?: number | null }) {
    return $http<
      ApiResponse<{
        processed_count: number;
        duration_seconds: number;
        status_counts: Record<string, number>;
      }>
    >('admin/jaringan-jalan/analisa-spasial/run', {
      method: 'POST',
      body: payload || {},
    });
  }

  async function fetchRuasList(params?: Record<string, any>) {
    return $http<
      ApiResponse<JaringanJalanRuasFeature[]> & {
        geojson?: { type: 'FeatureCollection'; features: JaringanJalanRuasFeature[] };
        summary?: {
          total_ruas: number;
          total_panjang_m: number;
          jumlah_poros_desa: number;
          jumlah_lingkungan: number;
          jumlah_usaha_tani: number;
          jumlah_kabupaten: number;
        };
      }
    >('admin/jaringan-jalan', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchRuasDetail(ruasId: string) {
    return $http<ApiResponse<JaringanJalanRuasFeature>>(`admin/jaringan-jalan/${ruasId}`, {
      method: 'GET',
    });
  }

  async function fetchKabupatenDetail(kabId: number) {
    return $http<ApiResponse<JalanKabupatenItem>>(`admin/jaringan-jalan/kabupaten/${kabId}`, {
      method: 'GET',
    });
  }

  async function updateRuas(
    ruasId: string,
    payload: {
      kode_ruas?: string | null;
      nama_ruas: string;
      hierarki_jalan: HierarkiJalan | string;
      lebar_m?: number | null;
      perkerasan?: string | null;
      kondisi?: string | null;
      catatan_validasi?: string | null;
    },
  ) {
    return $http<ApiResponse<JaringanJalanRuasFeature>>(`admin/jaringan-jalan/${ruasId}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteRuas(ruasId: string) {
    return $http<ApiResponse<null>>(`admin/jaringan-jalan/${ruasId}`, {
      method: 'DELETE',
    });
  }

  async function fetchKabupatenList(params?: Record<string, any>) {
    return $http<
      ApiResponse<JalanKabupatenItem[]> & {
        summary?: {
          total_ruas: number;
          total_panjang_m: number;
        };
      }
    >('admin/jaringan-jalan/kabupaten', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchCakupanDesa(params?: Record<string, any>) {
    return $http<ApiResponse<CakupanDesaItem[]> & { summary?: CakupanDesaSummary }>(
      'admin/jaringan-jalan/cakupan-desa',
      {
        method: 'GET',
        query: params,
      },
    );
  }

  async function adopsiRtrw(payload: {
    rtrw_ids?: number[];
    id_desa?: number | null;
    orde02?: string;
    hierarki_jalan?: HierarkiJalan | string;
    nama_ruas?: string;
    tahun_survey?: number;
    skip_spatial_duplicate?: boolean;
  }) {
    return $http<
      ApiResponse<{
        adopted_count: number;
        skipped_count: number;
        skipped: Array<{ rtrw_id: number; reason: string }>;
        created_ids: string[];
      }>
    >('admin/jaringan-jalan/rtrw/adopsi', {
      method: 'POST',
      body: payload,
    });
  }

  return {
    fetchAnalisaSummary,
    fetchAnalisaList,
    fetchAnalisaMapIndex,
    fetchAnalisaDetail,
    runAnalisaSpasial,
    fetchRuasList,
    fetchRuasDetail,
    updateRuas,
    deleteRuas,
    fetchKabupatenList,
    fetchKabupatenDetail,
    fetchCakupanDesa,
    adopsiRtrw,
  };
}

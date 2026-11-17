import type {
  BkkdProposalLokasi,
  HierarkiJalanBkkd,
  JaringanJalanRuasFeature,
} from '~/types/bkkd';

interface ApiResponse<T> {
  ok: boolean;
  data: T;
  message?: string;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  summary?: any;
}

export interface JaringanJalanKandidatItem extends BkkdProposalLokasi {
  proposal?: {
    id: string;
    nomor_registrasi: string;
    tahun_anggaran: number;
    tagging_usulan: 'PRKPD' | 'RKPD';
    judul_kegiatan: string;
    id_kecamatan: number;
    nama_kecamatan?: string | null;
    id_desa: number;
    nama_desa?: string | null;
  } | null;
}

export function useJaringanJalanApi() {
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

  async function fetchKandidatList(params?: Record<string, any>) {
    return $http<ApiResponse<JaringanJalanKandidatItem[]>>('admin/jaringan-jalan/kandidat', {
      method: 'GET',
      query: params,
    });
  }

  async function ajukanPromosi(
    proposalId: string,
    lokasiId: string,
    payload: {
      nama_ruas_usulan?: string;
      hierarki_jalan_usulan?: HierarkiJalanBkkd;
      catatan?: string;
    }
  ) {
    return $http<ApiResponse<BkkdProposalLokasi>>(
      `admin/jaringan-jalan/promosi/${proposalId}/lokasi/${lokasiId}/ajukan`,
      {
        method: 'POST',
        body: payload,
      }
    );
  }

  async function validasiPromosi(
    proposalId: string,
    lokasiId: string,
    payload: {
      keputusan: 'setujui_baru' | 'ekstensi_potong_unik' | 'tolak';
      nama_ruas?: string;
      kode_ruas?: string;
      hierarki_jalan?: HierarkiJalanBkkd;
      lebar_m?: number | null;
      perkerasan?: string | null;
      kondisi?: string | null;
      catatan?: string | null;
    }
  ) {
    return $http<
      ApiResponse<BkkdProposalLokasi> & {
        jaringan_ruas?: JaringanJalanRuasFeature | null;
      }
    >(`admin/jaringan-jalan/promosi/${proposalId}/lokasi/${lokasiId}/validasi`, {
      method: 'POST',
      body: payload,
    });
  }

  async function updateRuas(
    ruasId: string,
    payload: {
      kode_ruas?: string | null;
      nama_ruas: string;
      hierarki_jalan: HierarkiJalanBkkd;
      lebar_m?: number | null;
      perkerasan?: string | null;
      kondisi?: string | null;
      catatan_validasi?: string | null;
    }
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

  return {
    fetchRuasList,
    fetchKandidatList,
    ajukanPromosi,
    validasiPromosi,
    updateRuas,
    deleteRuas,
  };
}

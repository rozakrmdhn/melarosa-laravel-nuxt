import type {
  BkkdAgregasiDesaItem,
  BkkdAgregasiDesaSummary,
  BkkdJalanPorosOption,
  BkkdProposal,
  BkkdProposalLokasi,
  BkkdProposalSummary,
  BkkdRiwayat,
  BkkdRoutePreviewItem,
  HierarkiJalanBkkd,
  JaringanJalanRuasFeature,
  RefBkkdBidang,
  RefOpd,
  StatusAnalisaSpasialBkkd,
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

export function useBkkdApi() {
  const config = useRuntimeConfig();

  // ─── Referensi Master ──────────────────────────────────────────────────────
  async function fetchRefOpd() {
    return $http<ApiResponse<RefOpd[]>>('admin/ref-bkkd/opd', {
      method: 'GET',
    });
  }

  async function fetchRefBidang() {
    return $http<ApiResponse<RefBkkdBidang[]>>('admin/ref-bkkd/bidang', {
      method: 'GET',
    });
  }

  // ─── Proposal BKKD ─────────────────────────────────────────────────────────
  async function fetchProposals(params?: Record<string, any>) {
    return $http<ApiResponse<BkkdProposal[]> & { summary?: BkkdProposalSummary }>('admin/bkkd/proposal', {
      method: 'GET',
      query: params,
    });
  }

  async function getProposal(id: string) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/proposal/${id}`, {
      method: 'GET',
    });
  }

  async function createProposal(payload: Record<string, any>) {
    return $http<ApiResponse<BkkdProposal>>('admin/bkkd/proposal', {
      method: 'POST',
      body: payload,
    });
  }

  async function updateProposal(id: string, payload: Record<string, any>) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/proposal/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteProposal(id: string) {
    return $http<ApiResponse<null>>(`admin/bkkd/proposal/${id}`, {
      method: 'DELETE',
    });
  }

  async function restoreProposal(id: string) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/proposal/${id}/restore`, {
      method: 'POST',
    });
  }

  // ─── Import Excel Proposal ──────────────────────────────────────────────────
  async function importProposalExcel(payload: { rows?: any[]; file?: File }) {
    if (payload.file) {
      const formData = new FormData();
      formData.append('file', payload.file);
      return $http<ApiResponse<any[]> & { imported_count: number; failed_count: number; errors: any[] }>('admin/bkkd/proposal/import', {
        method: 'POST',
        body: formData,
      });
    }
    return $http<ApiResponse<any[]> & { imported_count: number; failed_count: number; errors: any[] }>('admin/bkkd/proposal/import', {
      method: 'POST',
      body: { rows: payload.rows },
    });
  }

  function getProposalImportTemplateUrl(): string {
    const apiBase = config.public.apiBaseUrl || '/api/v1';
    return `${apiBase}/admin/bkkd/proposal/import/template`;
  }

  async function getProposalRiwayat(proposalId: string) {
    return $http<ApiResponse<BkkdRiwayat[]>>(`admin/bkkd/proposal/${proposalId}/riwayat`, {
      method: 'GET',
    });
  }

  // ─── Agregasi Wilayah Desa ────────────────────────────────────────────────
  async function fetchAgregasiDesa(params?: Record<string, any>) {
    return $http<{
      ok: boolean;
      tahun_anggaran: number;
      summary: BkkdAgregasiDesaSummary;
      meta: { current_page: number; per_page: number; total: number; last_page: number };
      data: BkkdAgregasiDesaItem[];
    }>('admin/bkkd/agregasi-desa', {
      method: 'GET',
      query: params,
    });
  }

  function getAgregasiDesaExportUrl(params?: Record<string, any>): string {
    const apiBase = config.public.apiBaseUrl || '/api/v1';
    const query = new URLSearchParams(params).toString();
    return `${apiBase}/admin/bkkd/agregasi-desa/export${query ? '?' + query : ''}`;
  }

  // ─── Global Riwayat Audit ──────────────────────────────────────────────────
  async function fetchGlobalRiwayat(params?: Record<string, any>) {
    return $http<ApiResponse<BkkdRiwayat[]>>('admin/bkkd/riwayat', {
      method: 'GET',
      query: params,
    });
  }

  // ─── Temporary Document Upload (for verifikasi PDF/XLS) ────────────────────
  async function uploadTemporaryDocument(file: File, entity: 'bkkd-lampiran' | 'bkkd-verifikasi') {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('entity', entity);

    return $http<ApiResponse<{ path: string }>>('upload/document', {
      method: 'POST',
      body: formData,
    });
  }

  // ─── Multi-Lokasi LineString & Analisa Spasial ─────────────────────────────
  async function fetchRefJalanPoros(idDesa: number, mode?: 'hybrid' | 'full') {
    return $http<
      ApiResponse<BkkdJalanPorosOption[]> & {
        layers?: {
          mode?: string;
          martin_sources?: Record<string, string>;
          batas_desa_bbox?: number[] | null;
          jalan_poros_desa?: any;
          jaringan_jalan_ruas?: any;
          jalan_kabupaten?: any;
          infrastruktur_segmen?: any;
          batas_desa?: any;
        };
      }
    >('admin/bkkd/ref-jalan-poros', {
      method: 'GET',
      query: mode ? { id_desa: idDesa, mode } : { id_desa: idDesa },
    });
  }

  async function generateRoutePreview(payload: {
    id_desa: number;
    id_proposal?: string | null;
    tahun_anggaran?: number;
    tagging_usulan?: 'RKPD' | 'PRKPD';
    nama_lokasi?: string;
    latitude?: number | null;
    longitude?: number | null;
    latitude_akhir?: number | null;
    longitude_akhir?: number | null;
    id_jalan_poros?: string | null;
    id_lokasi?: string | null;
    single_location_mode?: boolean;
    panjang_rencana_m?: number | null;
    lebar_m?: number | null;
    sumber_rute?: string;
    preserve_manual_geometry?: boolean;
    skip_ors?: boolean;
    geojson?: any;
    waypoints?: { lat: number; lng: number; label?: string; is_route: boolean; use_ors: boolean }[];
  }) {
    return $http<
      ApiResponse<BkkdRoutePreviewItem | null> & {
        lokasi_list: BkkdRoutePreviewItem[];
        jumlah_lokasi: number;
        total_panjang_gis: number;
        status_analisa_spasial: StatusAnalisaSpasialBkkd;
      }
    >('admin/bkkd/generate-route', {
      method: 'POST',
      body: payload,
    });
  }

  async function fetchProposalLokasi(proposalId: string, mode?: 'hybrid' | 'full') {
    return $http<
      ApiResponse<BkkdProposalLokasi[]> & {
        geojson: any;
        layers?: {
          mode?: string;
          martin_sources?: Record<string, string>;
          batas_desa_bbox?: number[] | null;
          usulan_tahun_lalu?: any;
          infrastruktur_segmen?: any;
          jalan_poros_desa?: any;
          jaringan_jalan_ruas?: any;
          jalan_kabupaten?: any;
          batas_desa?: any;
        };
        jalan_poros_options?: BkkdJalanPorosOption[];
        summary: any;
      }
    >(`admin/bkkd/proposal/${proposalId}/lokasi`, {
      method: 'GET',
      query: mode ? { mode } : undefined,
    });
  }

  async function createProposalLokasi(proposalId: string, payload: Record<string, any>) {
    return $http<ApiResponse<BkkdProposalLokasi>>(`admin/bkkd/proposal/${proposalId}/lokasi`, {
      method: 'POST',
      body: payload,
    });
  }

  async function updateProposalLokasi(proposalId: string, lokasiId: string, payload: Record<string, any>) {
    return $http<ApiResponse<BkkdProposalLokasi>>(`admin/bkkd/proposal/${proposalId}/lokasi/${lokasiId}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteProposalLokasi(proposalId: string, lokasiId: string) {
    return $http<ApiResponse<null>>(`admin/bkkd/proposal/${proposalId}/lokasi/${lokasiId}`, {
      method: 'DELETE',
    });
  }

  async function reanalyzeProposalSpatial(proposalId: string) {
    return $http<ApiResponse<{ status_analisa_spasial: string; jumlah_lokasi: number; total_panjang_gis: number }>>(
      `admin/bkkd/proposal/${proposalId}/analisa-spasial`,
      { method: 'POST' }
    );
  }

  async function syncPendingSpatialAnalysis(payload?: {
    tahun_anggaran?: number;
    only_pending?: boolean;
    limit?: number;
  }) {
    return $http<ApiResponse<{ processed_proposals: number; processed_locations: number }>>(
      'admin/bkkd/analisa-spasial/sync-pending',
      {
        method: 'POST',
        body: payload || {},
      }
    );
  }

  async function simpanKonfirmasiDesa(proposalId: string, payload: { catatan_konfirmasi_desa: string }) {
    return $http<ApiResponse<{ catatan_konfirmasi_desa: string; dikonfirmasi_oleh?: string; dikonfirmasi_pada?: string }>>(
      `admin/bkkd/proposal/${proposalId}/konfirmasi-desa`,
      {
        method: 'POST',
        body: payload,
      }
    );
  }

  async function fetchAnalisaSpasialList(params?: Record<string, any>) {
    return $http<ApiResponse<BkkdProposal[]>>('admin/bkkd/analisa-spasial', {
      method: 'GET',
      query: params,
    });
  }

  // ─── Master Jaringan Jalan Kabupaten & Validasi Promosi Proposal ──────────
  async function fetchJaringanJalanRuas(params?: Record<string, any>) {
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
    >('admin/bkkd/jaringan-jalan', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchKandidatRuas(params?: Record<string, any>) {
    return $http<
      ApiResponse<
        Array<
          BkkdProposalLokasi & {
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
        >
      >
    >('admin/bkkd/kandidat-ruas', {
      method: 'GET',
      query: params,
    });
  }

  async function ajukanPromosiRuas(
    proposalId: string,
    lokasiId: string,
    payload: {
      nama_ruas_usulan?: string;
      hierarki_jalan_usulan?: HierarkiJalanBkkd;
      catatan?: string;
    }
  ) {
    return $http<ApiResponse<BkkdProposalLokasi>>(
      `admin/bkkd/proposal/${proposalId}/lokasi/${lokasiId}/ajukan-ruas`,
      {
        method: 'POST',
        body: payload,
      }
    );
  }

  async function validasiPromosiRuas(
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
    >(`admin/bkkd/proposal/${proposalId}/lokasi/${lokasiId}/validasi-ruas`, {
      method: 'POST',
      body: payload,
    });
  }

  return {
    fetchRefOpd,
    fetchRefBidang,
    fetchProposals,
    getProposal,
    createProposal,
    updateProposal,
    deleteProposal,
    restoreProposal,
    getProposalRiwayat,
    fetchAgregasiDesa,
    getAgregasiDesaExportUrl,
    fetchGlobalRiwayat,
    uploadTemporaryDocument,
    importProposalExcel,
    getProposalImportTemplateUrl,
    fetchRefJalanPoros,
    generateRoutePreview,
    fetchProposalLokasi,
    createProposalLokasi,
    updateProposalLokasi,
    deleteProposalLokasi,
    reanalyzeProposalSpatial,
    runProposalSpatialAnalysis: reanalyzeProposalSpatial,
    syncPendingSpatialAnalysis,
    simpanKonfirmasiDesa,
    fetchAnalisaSpasialList,
    fetchJaringanJalanRuas,
    fetchKandidatRuas,
    ajukanPromosiRuas,
    validasiPromosiRuas,
  };
}

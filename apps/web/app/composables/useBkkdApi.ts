import type {
  BkkdAgregasiDesaItem,
  BkkdAgregasiDesaSummary,
  BkkdProposal,
  BkkdProposalSummary,
  BkkdRekapitulasiDesaItem,
  BkkdRekapitulasiSummary,
  BkkdRiwayat,
  BkkdVerifikasi,
  RefBkkdBidang,
  RefOpd,
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

  async function ajukanOpd(id: string) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/proposal/${id}/ajukan`, {
      method: 'POST',
    });
  }

  async function batalkanProposal(id: string, alasan: string) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/proposal/${id}/batalkan`, {
      method: 'POST',
      body: { alasan, alasan_pembatalan: alasan },
    });
  }

  async function gantiProposalPapbd(proposalId: string, payload: Record<string, any>) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/proposal/${proposalId}/ganti-papbd`, {
      method: 'POST',
      body: payload,
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

  // ─── Lampiran Berkas ───────────────────────────────────────────────────────
  async function uploadLampiran(proposalId: string, file: File, jenisDokumen: string) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('jenis_dokumen', jenisDokumen);

    return $http<ApiResponse<any>>(`admin/bkkd/proposal/${proposalId}/lampiran`, {
      method: 'POST',
      body: formData,
    });
  }

  async function tambahLampiranLink(
    proposalId: string,
    payload: { jenis_dokumen: string; url_link: string; nama_asli?: string }
  ) {
    return $http<ApiResponse<any>>(`admin/bkkd/proposal/${proposalId}/lampiran`, {
      method: 'POST',
      body: {
        tipe_sumber: 'link',
        jenis_dokumen: payload.jenis_dokumen,
        url_link: payload.url_link,
        nama_asli: payload.nama_asli || undefined,
      },
    });
  }

  async function deleteLampiran(proposalId: string, lampiranId: string) {
    return $http<ApiResponse<null>>(`admin/bkkd/proposal/${proposalId}/lampiran/${lampiranId}`, {
      method: 'DELETE',
    });
  }

  function getLampiranDownloadUrl(proposalId: string, lampiranId: string): string {
    const apiBase = config.public.apiBaseUrl || '/api/v1';
    return `${apiBase}/admin/bkkd/proposal/${proposalId}/lampiran/${lampiranId}/download`;
  }

  async function getProposalRiwayat(proposalId: string) {
    return $http<ApiResponse<BkkdRiwayat[]>>(`admin/bkkd/proposal/${proposalId}/riwayat`, {
      method: 'GET',
    });
  }

  // ─── Verifikasi OPD ────────────────────────────────────────────────────────
  async function fetchVerifikasiList(params?: Record<string, any>) {
    return $http<ApiResponse<BkkdVerifikasi[]>>('admin/bkkd/verifikasi', {
      method: 'GET',
      query: params,
    });
  }

  async function submitVerifikasi(proposalId: string, payload: Record<string, any>) {
    return $http<ApiResponse<BkkdVerifikasi>>(`admin/bkkd/proposal/${proposalId}/verifikasi`, {
      method: 'POST',
      body: payload,
    });
  }

  async function submitVerifikasiKolektif(payload: Record<string, any>) {
    return $http<ApiResponse<{ dokumen: BkkdVerifikasiDokumen; total_desa: number }>>('admin/bkkd/verifikasi/kolektif', {
      method: 'POST',
      body: payload,
    });
  }

  async function fetchDokumenVerifikasiList(params?: Record<string, any>) {
    return $http<ApiResponse<BkkdVerifikasiDokumen[]>>('admin/bkkd/verifikasi/dokumen', {
      method: 'GET',
      query: params,
    });
  }

  function getDokumenVerifikasiDownloadUrl(dokumenId: string): string {
    const apiBase = config.public.apiBaseUrl || '/api/v1';
    return `${apiBase}/admin/bkkd/verifikasi/dokumen/${dokumenId}/download`;
  }

  async function updateVerifikasi(id: string, payload: Record<string, any>) {
    return $http<ApiResponse<BkkdVerifikasi>>(`admin/bkkd/verifikasi/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  function getVerifikasiDownloadUrl(verifikasiId: string): string {
    const apiBase = config.public.apiBaseUrl || '/api/v1';
    return `${apiBase}/admin/bkkd/verifikasi/${verifikasiId}/download`;
  }

  async function getVerifikasiRiwayat(verifikasiId: string) {
    return $http<ApiResponse<BkkdRiwayat[]>>(`admin/bkkd/verifikasi/${verifikasiId}/riwayat`, {
      method: 'GET',
    });
  }

  // ─── Rekapitulasi Desa & Penetapan TAPD ────────────────────────────────────
  async function fetchRekapitulasiDesa(params?: Record<string, any>) {
    return $http<ApiResponse<BkkdRekapitulasiDesaItem[]> & { summary?: BkkdRekapitulasiSummary }>(
      'admin/bkkd/rekapitulasi-desa',
      {
        method: 'GET',
        query: params,
      }
    );
  }

  async function tetapkanTapd(proposalId: string, payload: { nilai_disetujui_tapd: number; catatan_tapd?: string; tanggal_penetapan_tapd?: string }) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/rekapitulasi-desa/${proposalId}/tetapkan-tapd`, {
      method: 'POST',
      body: payload,
    });
  }

  async function batalkanTapd(proposalId: string, alasan?: string) {
    return $http<ApiResponse<BkkdProposal>>(`admin/bkkd/rekapitulasi-desa/${proposalId}/batalkan-tapd`, {
      method: 'POST',
      body: { alasan },
    });
  }

  function getRekapitulasiExportUrl(params?: Record<string, any>): string {
    const apiBase = config.public.apiBaseUrl || '/api/v1';
    const query = new URLSearchParams(params).toString();
    return `${apiBase}/admin/bkkd/rekapitulasi-desa/export${query ? '?' + query : ''}`;
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

  return {
    fetchRefOpd,
    fetchRefBidang,
    fetchProposals,
    getProposal,
    createProposal,
    updateProposal,
    deleteProposal,
    restoreProposal,
    ajukanOpd,
    batalkanProposal,
    uploadLampiran,
    tambahLampiranLink,
    deleteLampiran,
    getLampiranDownloadUrl,
    getProposalRiwayat,
    fetchVerifikasiList,
    submitVerifikasi,
    submitVerifikasiKolektif,
    fetchDokumenVerifikasiList,
    getDokumenVerifikasiDownloadUrl,
    updateVerifikasi,
    getVerifikasiDownloadUrl,
    getVerifikasiRiwayat,
    fetchRekapitulasiDesa,
    tetapkanTapd,
    batalkanTapd,
    getRekapitulasiExportUrl,
    fetchAgregasiDesa,
    getAgregasiDesaExportUrl,
    fetchGlobalRiwayat,
    uploadTemporaryDocument,
    importProposalExcel,
    getProposalImportTemplateUrl,
    gantiProposalPapbd,
  };
}

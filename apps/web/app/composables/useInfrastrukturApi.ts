import type {
  ActiveJadwalPeriod,
  ApiResponse,
  GeoJsonFeatureCollection,
  InfrastrukturSegmen,
  InfrastrukturSegmenFoto,
  InfrastrukturTipe,
  JadwalTahunAnggaran,
  JadwalTahunAnggaranWilayah,
  JalanPorosRef,
  MonitoringRealisasi,
  PlottingAnggaran,
  RefSumberDana,
} from '~/types/infrastruktur';

export function useInfrastrukturApi() {
  // ─── Master Tipe Infrastruktur ─────────────────────────────────────────────
  async function fetchTipeList(params?: { active_only?: boolean }) {
    return $http<ApiResponse<InfrastrukturTipe[]>>('admin/infrastruktur-tipe', {
      method: 'GET',
      query: params,
    });
  }

  async function getTipe(id: string) {
    return $http<ApiResponse<InfrastrukturTipe>>(`admin/infrastruktur-tipe/${id}`, {
      method: 'GET',
    });
  }

  async function createTipe(payload: Partial<InfrastrukturTipe>) {
    return $http<ApiResponse<InfrastrukturTipe>>('admin/infrastruktur-tipe', {
      method: 'POST',
      body: payload,
    });
  }

  async function updateTipe(id: string, payload: Partial<InfrastrukturTipe>) {
    return $http<ApiResponse<InfrastrukturTipe>>(`admin/infrastruktur-tipe/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteTipe(id: string) {
    return $http<ApiResponse<null>>(`admin/infrastruktur-tipe/${id}`, {
      method: 'DELETE',
    });
  }

  // ─── Master Referensi Sumber Dana ──────────────────────────────────────────
  async function fetchSumberDanaList(params?: { active_only?: boolean }) {
    return $http<ApiResponse<RefSumberDana[]>>('admin/ref-sumber-dana', {
      method: 'GET',
      query: params,
    });
  }

  async function getSumberDana(id: string) {
    return $http<ApiResponse<RefSumberDana>>(`admin/ref-sumber-dana/${id}`, {
      method: 'GET',
    });
  }

  async function createSumberDana(payload: Partial<RefSumberDana>) {
    return $http<ApiResponse<RefSumberDana>>('admin/ref-sumber-dana', {
      method: 'POST',
      body: payload,
    });
  }

  async function updateSumberDana(id: string, payload: Partial<RefSumberDana>) {
    return $http<ApiResponse<RefSumberDana>>(`admin/ref-sumber-dana/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteSumberDana(id: string) {
    return $http<ApiResponse<null>>(`admin/ref-sumber-dana/${id}`, {
      method: 'DELETE',
    });
  }

  // ─── Perencanaan Plotting Anggaran ─────────────────────────────────────────
  async function fetchPlottingList(params?: Record<string, any>) {
    return $http<ApiResponse<PlottingAnggaran[]>>('admin/plotting-anggaran', {
      method: 'GET',
      query: params,
    });
  }

  async function getPlotting(id: string) {
    return $http<ApiResponse<PlottingAnggaran>>(`admin/plotting-anggaran/${id}`, {
      method: 'GET',
    });
  }

  async function createPlotting(payload: Partial<PlottingAnggaran>) {
    return $http<ApiResponse<PlottingAnggaran>>('admin/plotting-anggaran', {
      method: 'POST',
      body: payload,
    });
  }

  async function updatePlotting(id: string, payload: Partial<PlottingAnggaran>) {
    return $http<ApiResponse<PlottingAnggaran>>(`admin/plotting-anggaran/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deletePlotting(id: string) {
    return $http<ApiResponse<null>>(`admin/plotting-anggaran/${id}`, {
      method: 'DELETE',
    });
  }

  // ─── Infrastruktur Segmen Spasial ──────────────────────────────────────────
  async function fetchSegmenList(params?: Record<string, any>) {
    return $http<ApiResponse<InfrastrukturSegmen[]>>('admin/infrastruktur-segmen', {
      method: 'GET',
      query: params,
    });
  }

  async function fetchSegmenSummary(params?: Record<string, any>) {
    return $http<{
      ok: boolean;
      total_segmen: number;
      total_panjang_meter: number;
      total_panjang_km: number;
      kondisi: Array<{ kondisi: string; jumlah: number; panjang_meter?: number }>;
      bbox: [number, number, number, number] | null;
    }>('admin/infrastruktur-segmen', {
      method: 'GET',
      query: { ...params, format: 'summary' },
    });
  }

  async function fetchSegmenGeoJson(params?: Record<string, any>) {
    return $http<GeoJsonFeatureCollection>('admin/infrastruktur-segmen', {
      method: 'GET',
      query: { ...params, format: 'geojson' },
    });
  }

  async function getSegmen(id: string) {
    return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}`, {
      method: 'GET',
    });
  }

  async function createSegmen(payload: Record<string, any>) {
    return $http<ApiResponse<InfrastrukturSegmen>>('admin/infrastruktur-segmen', {
      method: 'POST',
      body: payload,
    });
  }

  async function updateSegmen(id: string, payload: Record<string, any>) {
    return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteSegmen(id: string) {
    return $http<ApiResponse<null>>(`admin/infrastruktur-segmen/${id}`, {
      method: 'DELETE',
    });
  }

  async function patchSegmenGeometry(id: string, geometry: any) {
    return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}/geometry`, {
      method: 'PATCH',
      body: { geometry },
    });
  }

  async function splitSegmen(
    id: string,
    payload: {
      point?: [number, number];
      part1?: { namobj?: string };
      part2?: { namobj?: string };
      parts?: Array<{ namobj: string; geometry: any }>;
    }
  ) {
    return $http<ApiResponse<{ part1: InfrastrukturSegmen; part2: InfrastrukturSegmen }>>(
      `admin/infrastruktur-segmen/${id}/split`,
      {
        method: 'POST',
        body: payload,
      }
    );
  }

  // ─── Attachment Foto Dokumentasi Lapangan ──────────────────────────────────
  async function uploadSegmenFoto(
    segmenId: string,
    file: File,
    meta?: { tahap_label?: string; keterangan?: string },
  ) {
    const formData = new FormData();
    formData.append('foto', file);
    if (meta?.tahap_label) formData.append('tahap_label', meta.tahap_label);
    if (meta?.keterangan?.trim()) formData.append('keterangan', meta.keterangan.trim());

    return $http<{
      ok: boolean;
      message?: string;
      data: InfrastrukturSegmenFoto;
      fotos: InfrastrukturSegmenFoto[];
    }>(`admin/infrastruktur-segmen/${segmenId}/fotos`, {
      method: 'POST',
      body: formData,
    });
  }

  async function deleteSegmenFoto(segmenId: string, fotoId: string) {
    return $http<{
      ok: boolean;
      message?: string;
      fotos: InfrastrukturSegmenFoto[];
    }>(`admin/infrastruktur-segmen/${segmenId}/fotos/${fotoId}`, {
      method: 'DELETE',
    });
  }

  async function fetchSegmenFotoBlobUrl(fileEndpoint: string): Promise<string> {
    const blob = await $http<Blob>(fileEndpoint, {
      method: 'GET',
      responseType: 'blob' as any,
    });
    return URL.createObjectURL(blob);
  }

  // ─── State Machine Verifikasi ──────────────────────────────────────────────
  async function submitSegmen(id: string) {
    return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}/submit`, {
      method: 'POST',
    });
  }

  async function verifyKecamatan(id: string, action: 'approve' | 'reject', catatan?: string) {
    return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}/verify-kecamatan`, {
      method: 'POST',
      body: { action, catatan },
    });
  }

  async function verifyBappeda(id: string, action: 'approve' | 'reject', catatan?: string) {
    return $http<ApiResponse<InfrastrukturSegmen>>(`admin/infrastruktur-segmen/${id}/verify-bappeda`, {
      method: 'POST',
      body: { action, catatan },
    });
  }

  // ─── Monitoring Realisasi & Berita Acara ───────────────────────────────────
  async function fetchMonitoringList(params?: Record<string, any>) {
    return $http<ApiResponse<MonitoringRealisasi[]>>('admin/monitoring-realisasi', {
      method: 'GET',
      query: params,
    });
  }

  async function getMonitoring(id: string) {
    return $http<ApiResponse<MonitoringRealisasi>>(`admin/monitoring-realisasi/${id}`, {
      method: 'GET',
    });
  }

  async function createMonitoring(payload: Record<string, any>) {
    return $http<ApiResponse<MonitoringRealisasi>>('admin/monitoring-realisasi', {
      method: 'POST',
      body: payload,
    });
  }

  async function updateMonitoring(id: string, payload: Record<string, any>) {
    return $http<ApiResponse<MonitoringRealisasi>>(`admin/monitoring-realisasi/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteMonitoring(id: string) {
    return $http<ApiResponse<null>>(`admin/monitoring-realisasi/${id}`, {
      method: 'DELETE',
    });
  }

  async function revisiMonitoring(id: string, catatan_revisi: string) {
    return $http<ApiResponse<MonitoringRealisasi>>(`admin/monitoring-realisasi/${id}/revisi`, {
      method: 'POST',
      body: { catatan_revisi },
    });
  }

  // ─── Referensi Wilayah Helper ──────────────────────────────────────────────
  async function fetchKecamatanList() {
    return $http<any>('admin/batas-wilayah-kecamatan', {
      method: 'GET',
      query: { format: 'options', limit: 100 },
    });
  }

  async function fetchDesaList(id_kecamatan?: number) {
    return $http<any>('admin/batas-wilayah-desa', {
      method: 'GET',
      query: { id_kecamatan, format: 'options', limit: 1000 },
    });
  }

  // ─── Referensi Jalan Poros Desa ────────────────────────────────────────────
  async function fetchJalanPorosLookup(params?: {
    id_kecamatan?: number | null;
    id_desa?: number | null;
    search?: string;
    limit?: number;
  }) {
    return $http<ApiResponse<JalanPorosRef[]>>('admin/jalan-poros-desa', {
      method: 'GET',
      query: {
        format: 'lookup',
        limit: params?.limit ?? 500,
        ...(params?.id_kecamatan ? { id_kecamatan: params.id_kecamatan } : {}),
        ...(params?.id_desa ? { id_desa: params.id_desa } : {}),
        ...(params?.search?.trim() ? { search: params.search.trim() } : {}),
      },
    });
  }

  async function getJalanPoros(id: string) {
    return $http<{ ok: boolean; data: JalanPorosRef; feature: any }>(`admin/jalan-poros-desa/${id}`, {
      method: 'GET',
    });
  }

  // ─── Penjadwalan Tahun Anggaran & Override Desa ──────────────────────────
  async function fetchActiveJadwal(params?: { id_kecamatan?: number | null; id_desa?: number | null }) {
    return $http<{
      ok: boolean;
      configured: boolean;
      is_bypass: boolean;
      data: ActiveJadwalPeriod[];
    }>('admin/jadwal-anggaran/active', {
      method: 'GET',
      query: {
        ...(params?.id_kecamatan ? { id_kecamatan: params.id_kecamatan } : {}),
        ...(params?.id_desa ? { id_desa: params.id_desa } : {}),
      },
    });
  }

  async function fetchJadwalList() {
    return $http<ApiResponse<JadwalTahunAnggaran[]>>('admin/jadwal-anggaran', {
      method: 'GET',
    });
  }

  async function createJadwal(payload: Partial<JadwalTahunAnggaran>) {
    return $http<ApiResponse<JadwalTahunAnggaran>>('admin/jadwal-anggaran', {
      method: 'POST',
      body: payload,
    });
  }

  async function updateJadwal(id: string, payload: Partial<JadwalTahunAnggaran>) {
    return $http<ApiResponse<JadwalTahunAnggaran>>(`admin/jadwal-anggaran/${id}`, {
      method: 'PUT',
      body: payload,
    });
  }

  async function deleteJadwal(id: string) {
    return $http<ApiResponse<null>>(`admin/jadwal-anggaran/${id}`, {
      method: 'DELETE',
    });
  }

  async function createJadwalOverride(jadwalId: string, payload: Partial<JadwalTahunAnggaranWilayah>) {
    return $http<ApiResponse<JadwalTahunAnggaranWilayah>>(`admin/jadwal-anggaran/${jadwalId}/overrides`, {
      method: 'POST',
      body: payload,
    });
  }

  async function deleteJadwalOverride(jadwalId: string, overrideId: string) {
    return $http<ApiResponse<null>>(`admin/jadwal-anggaran/${jadwalId}/overrides/${overrideId}`, {
      method: 'DELETE',
    });
  }

  return {
    // Master
    fetchTipeList,
    getTipe,
    createTipe,
    updateTipe,
    deleteTipe,
    fetchSumberDanaList,
    getSumberDana,
    createSumberDana,
    updateSumberDana,
    deleteSumberDana,
    // Plotting
    fetchPlottingList,
    getPlotting,
    createPlotting,
    updatePlotting,
    deletePlotting,
    // Segmen
    fetchSegmenList,
    fetchSegmenSummary,
    fetchSegmenGeoJson,
    getSegmen,
    createSegmen,
    updateSegmen,
    deleteSegmen,
    patchSegmenGeometry,
    splitSegmen,
    uploadSegmenFoto,
    deleteSegmenFoto,
    fetchSegmenFotoBlobUrl,
    // Verifikasi
    submitSegmen,
    verifyKecamatan,
    verifyBappeda,
    // Monitoring
    fetchMonitoringList,
    getMonitoring,
    createMonitoring,
    updateMonitoring,
    deleteMonitoring,
    revisiMonitoring,
    // Wilayah
    fetchKecamatanList,
    fetchDesaList,
    // Referensi Jalan Poros Desa
    fetchJalanPorosLookup,
    getJalanPoros,
    // Jadwal Tahun Anggaran
    fetchActiveJadwal,
    fetchJadwalList,
    createJadwal,
    updateJadwal,
    deleteJadwal,
    createJadwalOverride,
    deleteJadwalOverride,
  };
}

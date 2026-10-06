import type {
  ApiResponse,
  GeoJsonFeatureCollection,
  InfrastrukturSegmen,
  InfrastrukturTipe,
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
  };
}

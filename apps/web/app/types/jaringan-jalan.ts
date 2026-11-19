export type StatusAnalisaRtrw =
  | 'belum_terdata'
  | 'irisan_sebagian'
  | 'terdata_poros_desa'
  | 'terdata_jalan_kabupaten'
  | 'tumpang_tindih_keduanya';

export type HierarkiJalan =
  | 'kabupaten'
  | 'kabupaten_usulan'
  | 'poros_desa'
  | 'lingkungan'
  | 'usaha_tani';

export interface RtrwAnalisaItem {
  id: number;
  display_name: string;
  orde01: string | null;
  orde02: string | null;
  orde03: string | null;
  orde04: string | null;
  id_kecamatan: number | null;
  nama_kecamatan: string | null;
  id_desa: number | null;
  nama_desa: string | null;
  panjang_m: number;
  poros_overlap_m: number;
  poros_overlap_pct: number;
  matched_poros_id: string | null;
  matched_poros_kode: number | null;
  matched_poros_nama: string | null;
  matched_poros_hierarki: string | null;
  kab_overlap_m: number;
  kab_overlap_pct: number;
  matched_kab_id: number | null;
  matched_kab_no_ruas: string | null;
  matched_kab_nama: string | null;
  matched_kab_klas: string | null;
  combined_overlap_m: number;
  combined_overlap_pct: number;
  uncovered_m: number;
  status_analisa: StatusAnalisaRtrw;
  already_adopted: boolean;
  recommended_hierarki: HierarkiJalan;
  bbox: [number, number, number, number] | null;
  analyzed_at: string | null;
}

export interface RtrwAnalisaStatusStat {
  count: number;
  panjang_m: number;
}

export interface RtrwAnalisaByOrde {
  orde02: string;
  total_segmen: number;
  total_panjang_m: number;
  coverage_pct: number;
  belum_terdata: number;
  irisan_sebagian: number;
  terdata_poros_desa: number;
  terdata_jalan_kabupaten: number;
  tumpang_tindih_keduanya: number;
}

export interface RtrwAnalisaByWilayah {
  id: number;
  nama: string;
  total_segmen: number;
  total_panjang_m: number;
  uncovered_m: number;
  coverage_pct: number;
  belum_terdata: number;
  terdata_poros_desa: number;
  terdata_jalan_kabupaten: number;
  tumpang_tindih_keduanya: number;
}

export interface RtrwAnalisaSummary {
  totals: {
    total_segmen: number;
    total_panjang_m: number;
    total_tercover_m: number;
    total_uncovered_m: number;
    avg_coverage_pct: number;
    already_adopted_count: number;
    last_analyzed_at: string | null;
    statuses: Record<StatusAnalisaRtrw, RtrwAnalisaStatusStat>;
  };
  dataset_overview: {
    rtrw_segmen: number;
    rtrw_panjang_m: number;
    jalan_poros_ruas: number;
    jalan_poros_panjang_m: number;
    jalan_kabupaten_ruas: number;
    jalan_kabupaten_panjang_m: number;
  };
  desa_coverage: {
    total_desa: number;
    desa_terdata: number;
    desa_belum_terdata: number;
  };
  by_orde: RtrwAnalisaByOrde[];
  wilayah_level: 'kecamatan' | 'desa';
  by_wilayah: RtrwAnalisaByWilayah[];
  orde_options: Array<{ value: string; label: string; total: number }>;
}

export interface RtrwAnalisaMapIndex {
  ids_by_status: Record<StatusAnalisaRtrw, number[]>;
  has_active_filter: boolean;
  filtered_ids: number[] | null;
  bounds: [number, number, number, number] | null;
}

export interface RtrwMatchedPorosItem {
  id: string;
  kode_ruas: number | null;
  nama_ruas: string;
  hierarki_jalan: string;
  sumber_data: string | null;
  tahun_survey: number | null;
  perkerasan: string | null;
  kondisi: string | null;
  panjang_m: number;
  lebar_m: number | null;
  nama_desa: string | null;
  nama_kecamatan: string | null;
  is_rtrw_origin: boolean;
  overlap_m: number;
  overlap_pct: number;
  geojson: any;
}

export interface RtrwMatchedKabupatenItem {
  id: number;
  no_ruas: string | null;
  nama_ruas: string;
  pangkal: string | null;
  ujung: string | null;
  klas_ruas: string | null;
  status_ruas: string | null;
  nama_kecamatan: string | null;
  nama_desa: string | null;
  panjang_m: number;
  overlap_m: number;
  overlap_pct: number;
  geojson: any;
}

export interface RtrwAnalisaDetail {
  segment: RtrwAnalisaItem & {
    nama_ruas: string | null;
    namobj: string | null;
    stsjrn: string | null;
    sbdata: string | null;
    geojson: any;
  };
  matched_poros: RtrwMatchedPorosItem[];
  matched_kabupaten: RtrwMatchedKabupatenItem[];
}

export interface JaringanJalanRuasFeature {
  type: 'Feature';
  id: string;
  geometry: any;
  properties: {
    id: string;
    kode_ruas?: string | null;
    nama_ruas: string;
    hierarki_jalan: HierarkiJalan | string;
    id_kecamatan?: number | null;
    id_desa?: number | null;
    nama_kecamatan?: string | null;
    nama_desa?: string | null;
    panjang_m: number;
    lebar_m?: number | null;
    perkerasan?: string | null;
    kondisi?: string | null;
    sumber_data: string;
    tahun_survey?: number | null;
    rtrw_source_id?: number | null;
    status_validasi: string;
    validated_at?: string | null;
  };
}

export interface JalanKabupatenItem {
  id: number;
  no_ruas: string | null;
  nama_ruas: string;
  pangkal: string | null;
  ujung: string | null;
  klas_ruas: string | null;
  status_ruas: string | null;
  nama_kecamatan: string | null;
  nama_desa: string | null;
  panjang_m: number;
  rtrw_match_count: number;
  bbox: [number, number, number, number] | null;
  geometry: any;
}

export interface CakupanDesaItem {
  id: number;
  nama_desa: string;
  id_kecamatan?: number | null;
  nama_kecamatan?: string | null;
  poros_count: number;
  poros_panjang_m: number;
  rtrw_jalan_desa_count: number;
  rtrw_total_count: number;
  rtrw_belum_terdata_count?: number;
  status_cakupan: 'sudah_terdata' | 'belum_terdata';
}

export interface CakupanDesaSummary {
  total_desa: number;
  desa_terdata: number;
  desa_belum_terdata: number;
}

export interface JaringanMapLayersState {
  rtrw: boolean;
  poros: boolean;
  kabupaten: boolean;
  kecamatan: boolean;
  desa: boolean;
}

export type RtrwColorMode = 'status' | 'orde';

export type JaringanLayerKey = 'rtrw' | 'poros' | 'kabupaten' | 'kecamatan' | 'desa';

export type JaringanMapOpacityState = Record<JaringanLayerKey, number>;

export type JaringanWorkspaceTab =
  | 'analisa'
  | 'cakupan'
  | 'master'
  | 'kabupaten'
  | 'breakdown'
  | 'console';

export type JaringanMobileDockTab = 'layers' | 'table' | 'rekap' | 'detail' | 'filter';

export interface JaringanConsoleEntry {
  id: string;
  timestamp: string;
  level: 'info' | 'success' | 'warn' | 'error';
  message: string;
}

export type JaringanMapSelection =
  | {
      kind: 'rtrw';
      id: number;
      title: string;
      subtitle?: string;
      badge?: string;
      bbox?: [number, number, number, number] | null;
      geojson?: any;
      matchedPorosGeojson?: any[];
      matchedKabupatenGeojson?: any[];
    }
  | {
      kind: 'poros';
      id: string;
      title: string;
      subtitle?: string;
      badge?: string;
      bbox?: [number, number, number, number] | null;
      geojson?: any;
    }
  | {
      kind: 'kabupaten';
      id: number;
      title: string;
      subtitle?: string;
      badge?: string;
      bbox?: [number, number, number, number] | null;
      geojson?: any;
    };

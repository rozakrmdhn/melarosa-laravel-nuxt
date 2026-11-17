export type KondisiSegmen = 'Baik' | 'Sedang' | 'Rusak Ringan' | 'Rusak Berat';

export type StatusKondisi = 'Eksisting' | 'Riwayat';

export type StatusVerifikasi =
  | 'draft'
  | 'submitted_desa'
  | 'verified_kecamatan'
  | 'rejected_kecamatan'
  | 'verified_bappeda'
  | 'rejected_bappeda';

export type StatusMonitoring = 'draft' | 'submitted' | 'approved' | 'rejected' | 'reverted';

export interface ApiResponse<T = any> {
  ok: boolean;
  message?: string;
  data: T;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  summary?: {
    total_kegiatan: number;
    total_pagu_anggaran: number;
    total_target_panjang: number;
  };
}

export interface InfrastrukturTipe {
  id: string;
  kode: string;
  nama: string;
  deskripsi?: string | null;
  ikon?: string | null;
  warna?: string | null;
  geom_type?: string | null;
  table_name?: string | null;
  has_segmen: boolean;
  is_active: boolean;
  sort_order: number;
  config?: Record<string, any> | null;
  created_at?: string;
  updated_at?: string;
}

export interface RefSumberDana {
  id: string;
  kode: string;
  nama: string;
  kategori?: string | null;
  is_active: boolean;
  sort_order: number;
  created_at?: string;
  updated_at?: string;
}

export interface PlottingAnggaran {
  id: string;
  tahun_anggaran: number;
  id_kecamatan: number;
  id_desa: number;
  jenis_bantuan: string;
  nama_kegiatan: string;
  lokasi_kegiatan?: string | null;
  sumber_dana: string;
  target_pagu_anggaran: number;
  target_panjang_m: number;
  user_id?: string | null;
  kecamatan?: { id: number; nama_kecamatan: string };
  desa?: { id: number; nama_desa: string };
  author?: { uuid: string; name: string; email: string };
  segmen?: Partial<InfrastrukturSegmen>[];
  monitoring_realisasi?: Partial<MonitoringRealisasi>[];
  created_at?: string;
  updated_at?: string;
}

export interface JalanPorosRef {
  id: string;
  kode_ruas?: number | string | null;
  nama_ruas?: string | null;
  desa?: string | null;
  kecamatan?: string | null;
  id_desa?: number | null;
  id_kecamatan?: number | null;
  panjang?: number | null;
  kondisi?: string | null;
  perkerasan?: string | null;
  lebar?: number | null;
}

export interface InfrastrukturSegmen {
  id: string;
  tipe_kode: string;
  namobj?: string | null;
  panjang?: number | null;
  panjang_meter_gis?: number | null;
  lebar?: number | null;
  jenis_perkerasan?: string | null;
  status_jalan?: string | null;
  kondisi?: KondisiSegmen | null;
  status_kondisi?: StatusKondisi | null;
  tahun_pembangunan?: number | null;
  sumber_dana?: string | null;
  keterangan?: string | null;
  foto_url?: string | null;
  atribut?: Record<string, any> | null;
  desa?: string | Record<string, any> | any[] | null;
  kecamatan?: string | Record<string, any> | any[] | null;
  id_desa: number;
  id_kecamatan: number;
  plotting_id?: string | null;
  parent_id?: string | null;
  parent?: JalanPorosRef | null;
  status_aset?: string | null;
  status_verifikasi: StatusVerifikasi;
  sumber_data?: string | null;
  created_by?: string | null;
  created_by_role?: string | null;
  submitted_desa_at?: string | null;
  verified_kecamatan_by?: string | null;
  verified_kecamatan_at?: string | null;
  catatan_kecamatan?: string | null;
  verified_bappeda_by?: string | null;
  verified_bappeda_at?: string | null;
  catatan_bappeda?: string | null;
  geojson?: any;
  centroid?: any;
  tipe?: InfrastrukturTipe;
  plotting?: PlottingAnggaran;
  creator?: { uuid: string; name: string; email: string };
  verifier_kecamatan?: { uuid: string; name: string; email: string };
  verifier_bappeda?: { uuid: string; name: string; email: string };
  created_at?: string;
  updated_at?: string;
}

export interface MonitoringRealisasi {
  id: string;
  nomor_ba: string;
  id_plotting: string;
  id_kecamatan: number;
  id_desa: number;
  tahun_anggaran: number;
  sumber_dana: string;
  rencana_panjang: number;
  realisasi_panjang: number;
  persentase?: number;
  status: StatusMonitoring;
  keterangan?: string | null;
  user_id?: string | null;
  plotting?: PlottingAnggaran;
  segmen?: InfrastrukturSegmen[];
  kecamatan?: { id: number; nama_kecamatan: string };
  desa?: { id: number; nama_desa: string };
  author?: { uuid: string; name: string; email: string };
  items?: MonitoringRealisasiItem[];
  revisions?: MonitoringRealisasiRevision[];
  latest_revision?: MonitoringRealisasiRevision;
  created_at?: string;
  updated_at?: string;
}

export interface MonitoringRealisasiItem {
  id: string;
  id_monitoring: string;
  id_segmen: string;
  segmen?: InfrastrukturSegmen;
  created_at?: string;
  updated_at?: string;
}

export interface MonitoringRealisasiRevision {
  id: string;
  id_monitoring: string;
  catatan_revisi: string;
  status_sebelum: string;
  data_snapshot?: Record<string, any> | null;
  revised_by?: string | null;
  reverted_at?: string | null;
  author?: { uuid: string; name: string; email: string };
  created_at?: string;
}

export interface GeoJsonFeatureCollection {
  type: 'FeatureCollection';
  features: Array<{
    type: 'Feature';
    id: string;
    geometry: any;
    properties: Record<string, any>;
  }>;
}

export interface SelectOptionItem<T extends string = string> {
  label: string;
  value: T;
}

export const KONDISI_OPTIONS: SelectOptionItem<KondisiSegmen>[] = [
  { label: 'Baik', value: 'Baik' },
  { label: 'Sedang', value: 'Sedang' },
  { label: 'Rusak Ringan', value: 'Rusak Ringan' },
  { label: 'Rusak Berat', value: 'Rusak Berat' },
];

export const JALAN_POROS_KONDISI_OPTIONS: SelectOptionItem[] = [
  { label: 'Baik', value: 'Baik' },
  { label: 'Rusak Sedang', value: 'Rusak Sedang' },
  { label: 'Rusak Ringan', value: 'Rusak Ringan' },
  { label: 'Rusak Berat', value: 'Rusak Berat' },
];

export interface KondisiLegendItem {
  label: string;
  color: string;
}

const KONDISI_COLOR_MAP: Record<string, string> = {
  Baik: '#10b981',
  Sedang: '#f59e0b',
  'Rusak Sedang': '#f59e0b',
  'Rusak Ringan': '#f97316',
  'Rusak Berat': '#ef4444',
};

/** Returns condition legend items matching each layer's domain labels. */
export function getKondisiLegendItems(layerId?: string): KondisiLegendItem[] {
  const options = layerId === 'jalan-poros-desa' ? JALAN_POROS_KONDISI_OPTIONS : KONDISI_OPTIONS;
  return options.map((opt) => ({
    label: opt.label,
    color: KONDISI_COLOR_MAP[opt.value] || '#64748b',
  }));
}

export const STATUS_KONDISI_OPTIONS: SelectOptionItem<StatusKondisi>[] = [
  { label: 'Eksisting', value: 'Eksisting' },
  { label: 'Riwayat', value: 'Riwayat' },
];

export const PERKERASAN_OPTIONS: SelectOptionItem[] = [
  { label: 'Aspal', value: 'Aspal' },
  { label: 'Beton Cor', value: 'Beton Cor' },
  { label: 'Beton', value: 'Beton' },
  { label: 'Paving', value: 'Paving' },
  { label: 'Makadam', value: 'Makadam' },
  { label: 'Kerikil', value: 'Kerikil' },
  { label: 'Tanah', value: 'Tanah' },
  { label: 'Lainnya', value: 'Lainnya' },
];

export const STATUS_JALAN_OPTIONS: SelectOptionItem[] = [
  { label: 'Jalan Desa', value: 'Jalan Desa' },
  { label: 'Jalan Poros Desa', value: 'Jalan Poros Desa' },
  { label: 'Jalan Kabupaten', value: 'Jalan Kabupaten' },
];

export const STATUS_ASET_OPTIONS: SelectOptionItem[] = [
  { label: 'Pemerintah Desa', value: 'Pemerintah Desa' },
  { label: 'Pemerintah Kabupaten', value: 'Pemerintah Kabupaten' },
  { label: 'Aset Desa', value: 'Aset Desa' },
  { label: 'Lainnya', value: 'Lainnya' },
];

export const SUMBER_DANA_OPTIONS: SelectOptionItem[] = [
  { label: 'APBD', value: 'APBD' },
  { label: 'BKK', value: 'BKK' },
  { label: 'Dana Desa (DD)', value: 'DD' },
  { label: 'Sektoral', value: 'Sektoral' },
  { label: 'Lainnya', value: 'Lainnya' },
];

export const STATUS_VERIFIKASI_OPTIONS: SelectOptionItem<StatusVerifikasi>[] = [
  { label: 'Draft', value: 'draft' },
  { label: 'Diajukan ke Kecamatan', value: 'submitted_desa' },
  { label: 'Terverifikasi Kecamatan', value: 'verified_kecamatan' },
  { label: 'Ditolak Kecamatan', value: 'rejected_kecamatan' },
  { label: 'Disahkan Bappeda', value: 'verified_bappeda' },
  { label: 'Ditolak Bappeda', value: 'rejected_bappeda' },
];

/** Preserves an existing custom/legacy value in dropdown options when editing saved rows. */
export function withCurrentOption(
  options: SelectOptionItem[],
  currentValue?: string | null,
): SelectOptionItem[] {
  const val = currentValue?.trim();
  if (!val || options.some((opt) => opt.value === val)) {
    return options;
  }
  return [{ label: val, value: val }, ...options];
}


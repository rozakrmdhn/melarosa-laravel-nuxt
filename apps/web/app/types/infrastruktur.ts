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

export interface InfrastrukturSegmen {
  id: string;
  tipe_kode: string;
  namobj?: string | null;
  panjang?: number | null;
  panjang_meter_gis?: number | null;
  lebar?: number | null;
  kondisi?: KondisiSegmen | null;
  status_kondisi?: StatusKondisi | null;
  tahun_pembangunan?: number | null;
  sumber_dana?: string | null;
  keterangan?: string | null;
  foto_url?: string | null;
  atribut?: Record<string, any> | null;
  desa?: string | null;
  kecamatan?: string | null;
  id_desa: number;
  id_kecamatan: number;
  plotting_id?: string | null;
  parent_id?: string | null;
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

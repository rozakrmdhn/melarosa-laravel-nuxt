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

export interface JadwalTahunAnggaranWilayah {
  id: string;
  jadwal_id: string;
  id_kecamatan?: number | null;
  id_desa?: number | null;
  mode_akses: 'buka_paksa' | 'tutup_paksa';
  mulai_at?: string | null;
  selesai_at?: string | null;
  allow_plotting?: boolean | null;
  allow_digitasi?: boolean | null;
  allow_non_pagu?: boolean | null;
  alasan?: string | null;
  kecamatan?: { id: number; nama_kecamatan: string } | null;
  desa?: { id: number; nama_desa: string } | null;
  creator?: { uuid: string; name: string; email: string } | null;
  created_at?: string;
  updated_at?: string;
}

export interface JadwalTahunAnggaran {
  id: string;
  tahun_anggaran: number;
  nama_periode: string;
  status_jadwal: 'terjadwal' | 'dibuka' | 'ditutup';
  mulai_at?: string | null;
  selesai_at?: string | null;
  allow_plotting: boolean;
  allow_digitasi: boolean;
  allow_non_pagu: boolean;
  catatan?: string | null;
  updated_by?: string | null;
  updater?: { uuid: string; name: string; email: string } | null;
  wilayah_overrides?: JadwalTahunAnggaranWilayah[];
  created_at?: string;
  updated_at?: string;
}

export interface ActiveJadwalPeriod {
  id: string;
  tahun_anggaran: number;
  nama_periode: string;
  status_jadwal: 'terjadwal' | 'dibuka' | 'ditutup';
  is_open: boolean;
  effective_open: boolean;
  source: 'global' | 'desa_override' | 'kecamatan_override';
  mode_akses: string;
  allow_plotting: boolean;
  allow_digitasi: boolean;
  allow_non_pagu: boolean;
  mulai_at?: string | null;
  selesai_at?: string | null;
  reason?: string | null;
  is_desa_override: boolean;
  open_desa_override_ids?: number[];
  open_plotting_desa_ids?: number[];
  closed_desa_override_ids?: number[];
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
  nomor_sk?: string | null;
  tanggal_sk?: string | null;
  status_penetapan?: 'draft' | 'ditetapkan' | 'selesai';
  bkkd_proposal_id?: string | null;
  target_pagu_anggaran: number;
  target_panjang_m: number;
  used_panjang_m?: number | null;
  segmen_count?: number;
  user_id?: string | null;
  kecamatan?: { id: number; nama_kecamatan: string };
  desa?: { id: number; nama_desa: string };
  author?: { uuid: string; name: string; email: string };
  proposal?: { id: string; nomor_registrasi?: string | null; judul_kegiatan?: string | null; tahun_anggaran?: number };
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
  is_non_pagu?: boolean;
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
  fotos?: InfrastrukturSegmenFoto[];
  creator?: { uuid: string; name: string; email: string };
  verifier_kecamatan?: { uuid: string; name: string; email: string };
  verifier_bappeda?: { uuid: string; name: string; email: string };
  created_at?: string;
  updated_at?: string;
}

export const MAX_SEGMEN_FOTOS = 4;
export const IDEAL_MIN_SEGMEN_FOTOS = 3;

export const FOTO_TAHAP_OPTIONS: SelectOptionItem[] = [
  { label: 'Titik Awal (0%)', value: 'Titik Awal (0%)' },
  { label: 'Bentang Tengah (50%)', value: 'Bentang Tengah (50%)' },
  { label: 'Titik Akhir (100%)', value: 'Titik Akhir (100%)' },
  { label: 'Detail Kondisi', value: 'Detail Kondisi' },
];

export interface InfrastrukturSegmenFoto {
  id: string;
  segmen_id: string;
  urutan: number;
  tahap_label: string;
  keterangan?: string | null;
  mime_type: string;
  ukuran_byte: number;
  lebar_px?: number | null;
  tinggi_px?: number | null;
  uploaded_by?: string | null;
  uploader?: { uuid: string; name: string } | null;
  file_endpoint: string;
  created_at?: string;
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

const LEGACY_SUMBER_DANA_ALIAS_MAP: Record<string, string> = {
  BKK: 'BKK_DESA',
  BKKD: 'BKK_DESA',
  APBD: 'APBD_KAB',
  DD: 'DANA_DESA',
  DDS: 'DANA_DESA',
  DAK: 'DAK_FISIK',
};

/**
 * Resolves a stored sumber_dana code (including historical values like BKK / APBD)
 * to its human-readable RefSumberDana label when available.
 */
export function resolveSumberDanaLabel(
  sumberDanaList: RefSumberDana[] = [],
  value?: string | null,
): string {
  const raw = value?.trim();
  if (!raw) return '-';

  const upper = raw.toUpperCase();
  const mappedCode = LEGACY_SUMBER_DANA_ALIAS_MAP[upper];

  const exactMatch = sumberDanaList.find((sd) => sd.kode.toUpperCase() === upper);
  if (exactMatch) {
    return `${exactMatch.nama} (${exactMatch.kode})`;
  }

  if (mappedCode) {
    const aliasMatch = sumberDanaList.find((sd) => sd.kode.toUpperCase() === mappedCode);
    if (aliasMatch) {
      return `${aliasMatch.nama} (${raw})`;
    }
  }

  const fallback = SUMBER_DANA_OPTIONS.find((opt) => opt.value.toUpperCase() === upper);
  return fallback?.label ?? raw;
}

/**
 * Builds dropdown options from master RefSumberDana while preserving any legacy stored value.
 */
export function buildSumberDanaSelectItems(
  sumberDanaList: RefSumberDana[] = [],
  currentValue?: string | null,
  fallbackOptions: SelectOptionItem[] = SUMBER_DANA_OPTIONS,
): SelectOptionItem[] {
  const baseItems: SelectOptionItem[] =
    sumberDanaList.length > 0
      ? sumberDanaList.map((sd) => ({
          label: `${sd.nama} (${sd.kode})`,
          value: sd.kode,
        }))
      : fallbackOptions;

  const val = currentValue?.trim();
  if (!val || baseItems.some((item) => item.value.toUpperCase() === val.toUpperCase())) {
    return baseItems;
  }

  const label = resolveSumberDanaLabel(sumberDanaList, val);
  return [{ label: label === val ? val : `${label}`, value: val }, ...baseItems];
}

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


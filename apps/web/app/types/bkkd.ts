export type StatusAnalisaSpasialBkkd =
  | 'belum_dianalisa'
  | 'aman'
  | 'lanjutan_ruas'
  | 'konfirmasi_belum_tuntas'
  | 'kewenangan_kabupaten'
  | 'duplikasi_tuntas';

export type SumberRuteBkkd =
  | 'jalan_porosdesa'
  | 'openrouteservice'
  | 'garis_lurus'
  | 'manual';

export type TaggingUsulanBkkd = 'RKPD' | 'PRKPD';

export interface BkkdReferensiOverlapTahunLalu {
  id_proposal_lama: string;
  nomor_registrasi: string;
  tahun_anggaran: number;
  tagging_usulan?: TaggingUsulanBkkd;
  pasangan_tagging?: 'prkpd_vs_rkpd' | 'rkpd_vs_prkpd' | 'lintas_tahun';
  relasi_tahun?: 'sebelumnya' | 'tahun_sama' | 'perencanaan_berikutnya';
  judul_kegiatan: string;
  id_lokasi_lama: string;
  nama_lokasi_lama: string;
  panjang_usulan_lama_m: number;
  panjang_realisasi_m: number;
  sisa_belum_tuntas_m: number;
  persen_realisasi: number;
  panjang_irisan_usulan_m: number;
}

export interface BkkdReferensiInfrastrukturSegmen {
  id_segmen: string;
  tipe_kode?: string | null;
  namobj: string;
  kondisi: string;
  status_kondisi?: string | null;
  tahun_pembangunan?: number | null;
  sumber_dana?: string | null;
  panjang_segmen_m: number;
  panjang_irisan_m: number;
  persen_irisan_usulan: number;
  irisan_geojson?: any;
}

export interface BkkdReferensiJalanPoros {
  id_jalan_poros: string;
  kode_ruas?: number | null;
  nama_ruas: string;
  panjang_ruas_m: number;
  lebar_ruas_m?: number | null;
  perkerasan?: string | null;
  kondisi?: string | null;
  status_eksisting?: string | null;
  panjang_irisan_m: number;
  persen_cakupan_ruas: number;
  persen_masuk_koridor?: number;
  irisan_geojson?: any;
}

export interface BkkdReferensiJalanKabupaten {
  id_jalan_kabupaten: string;
  no_ruas?: string | null;
  nama_ruas: string;
  pangkal?: string | null;
  ujung?: string | null;
  klas_ruas?: string | null;
  status_ruas?: string | null;
  kecamatan?: string | null;
  panjang_ruas_m: number;
  panjang_irisan_m: number;
  persen_masuk_koridor: number;
  irisan_geojson?: any;
}

export interface BkkdJalanPorosOption {
  id: string;
  kode_ruas?: number | null;
  nama_ruas: string;
  panjang: number;
  lebar: number;
  kondisi?: string | null;
  perkerasan?: string | null;
  geojson?: any;
}

export interface BkkdTitikSebaran {
  urutan?: number;
  label?: string | null;
  lat: number;
  lng: number;
  is_route: boolean;
  use_ors: boolean;
}

export interface BkkdRingkasanAnalisaSpasial {
  status: StatusAnalisaSpasialBkkd;
  titik_sebaran?: BkkdTitikSebaran[];
  sumber_rute?: SumberRuteBkkd;
  panjang_usulan_gis_m?: number;
  total_irisan_jalan_poros_m?: number;
  persen_koridor_jalan_poros?: number;
  total_irisan_jalan_kabupaten_m?: number;
  persen_koridor_jalan_kabupaten?: number;
  total_irisan_segmen_m?: number;
  total_irisan_segmen_baik_m?: number;
  sisa_belum_terbangun_m?: number;
  persen_irisan_segmen?: number;
  terdeteksi_overlap_usulan: boolean;
  terdeteksi_overlap_segmen?: boolean;
  terhubung_jalan_poros?: boolean;
  terdeteksi_overlap_kabupaten?: boolean;
  rekomendasi_bappeda: string;
  referensi_tahun_lalu: BkkdReferensiOverlapTahunLalu[];
  referensi_infrastruktur_segmen?: BkkdReferensiInfrastrukturSegmen[];
  referensi_jalan_poros?: BkkdReferensiJalanPoros[];
  referensi_jalan_kabupaten?: BkkdReferensiJalanKabupaten[];
  analyzed_at?: string;
}

export interface BkkdRoutePreviewItem {
  id?: string;
  urutan: number;
  nama_lokasi: string;
  id_jalan_poros?: string | null;
  nama_ruas?: string | null;
  sumber_rute: SumberRuteBkkd;
  titik_awal?: { lat: number; lng: number } | null;
  titik_akhir?: { lat: number; lng: number } | null;
  titik_sebaran?: BkkdTitikSebaran[] | null;
  panjang_gis_m: number;
  panjang_rencana_m?: number | null;
  lebar_m?: number | null;
  geojson: any;
  status_analisa_spasial: StatusAnalisaSpasialBkkd;
  ringkasan_analisa: BkkdRingkasanAnalisaSpasial;
  snapshot_data_url?: string | null;
}

export type StatusPromosiMasterBkkd =
  | 'belum_dievaluasi'
  | 'kandidat'
  | 'tidak_perlu'
  | 'diajukan'
  | 'disetujui'
  | 'ditolak';

export type HierarkiJalanBkkd = 'kabupaten' | 'poros_desa' | 'lingkungan' | 'usaha_tani';

export interface BkkdValidasiPromosiCheck {
  kode: string;
  label: string;
  lolos: boolean;
  detail: string;
}

export interface BkkdValidasiPromosiSpasial {
  lolos_validasi: boolean;
  rekomendasi_aksi: string;
  panjang_valid_m: number;
  jumlah_vertex: number;
  persen_overlap_master: number;
  indikasi_jalan_kabupaten?: boolean;
  nearest_ruas?: {
    id?: string | null;
    nama_ruas: string;
    hierarki_jalan?: string;
    persen_overlap: number;
  } | null;
  checks: BkkdValidasiPromosiCheck[];
}

export interface JaringanJalanRuasFeature {
  type: 'Feature';
  id: string;
  geometry: any;
  properties: {
    id: string;
    kode_ruas?: string | null;
    nama_ruas: string;
    hierarki_jalan: HierarkiJalanBkkd;
    id_kecamatan?: number | null;
    id_desa?: number | null;
    nama_kecamatan?: string | null;
    nama_desa?: string | null;
    panjang_m: number;
    lebar_m?: number | null;
    perkerasan?: string | null;
    kondisi?: string | null;
    sumber_data: string;
    status_validasi: string;
    sumber_proposal_id?: string | null;
    sumber_lokasi_id?: string | null;
    validated_at?: string | null;
  };
}

export interface BkkdProposalLokasi {
  id: string;
  id_proposal: string;
  id_jalan_poros?: string | null;
  id_jaringan_ruas?: string | null;
  urutan: number;
  nama_lokasi: string;
  nama_ruas_usulan?: string | null;
  hierarki_jalan_usulan?: HierarkiJalanBkkd | string | null;
  panjang_rencana_m?: number | null;
  panjang_gis_m: number;
  lebar_m?: number | null;
  status_analisa_spasial: StatusAnalisaSpasialBkkd;
  status_promosi_master?: StatusPromosiMasterBkkd;
  ringkasan_analisa?: BkkdRingkasanAnalisaSpasial | null;
  validasi_promosi_spasial?: BkkdValidasiPromosiSpasial | null;
  catatan_promosi_master?: string | null;
  diajukan_promosi_pada?: string | null;
  divalidasi_promosi_pada?: string | null;
  geojson?: any;
  centroid?: any;
  jalan_poros?: {
    id: string;
    kode_ruas?: number;
    nama_ruas: string;
  } | null;
  jaringan_ruas?: {
    id: string;
    kode_ruas?: string | null;
    nama_ruas: string;
    hierarki_jalan: HierarkiJalanBkkd;
    status_validasi: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface RefOpd {
  id: string;
  kode: string;
  nama: string;
  nama_singkat?: string | null;
  bidang_tanggung_jawab?: string | null;
  is_aktif: boolean;
}

export interface RefBkkdBidang {
  id: string;
  kode?: string;
  nama: string;
  id_opd_default?: string | null;
  opd_default?: RefOpd | null;
  deskripsi?: string | null;
  is_aktif?: boolean;
}

export interface BkkdProposal {
  id: string;
  nomor_registrasi: string;
  tahun_anggaran: number;
  tagging_usulan?: TaggingUsulanBkkd;
  id_kecamatan: number;
  nama_kecamatan?: string | null;
  id_desa: number;
  nama_desa?: string | null;
  id_bidang: string;
  nama_bidang?: string | null;
  id_opd_default?: string | null;
  bidang?: RefBkkdBidang | null;
  judul_kegiatan: string;
  uraian_kegiatan?: string | null;
  lokasi_kegiatan: string;
  nilai_usulan?: number | null;
  status?: string;
  versi: number;
  jumlah_lokasi?: number;
  total_panjang_gis?: number;
  status_analisa_spasial?: StatusAnalisaSpasialBkkd;
  catatan_konfirmasi_desa?: string | null;
  dikonfirmasi_oleh?: string | null;
  dikonfirmasi_pada?: string | null;
  lokasi?: BkkdProposalLokasi[];
  creator_name?: string | null;
  updater_name?: string | null;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
  is_deleted?: boolean;
}

export interface BkkdProposalSummary {
  total_proposal: number;
  total_nilai_usulan: number;
  total_lokasi?: number;
  total_panjang_gis?: number;
  jumlah_aman?: number;
  jumlah_lanjutan_ruas?: number;
  jumlah_konfirmasi_belum_tuntas?: number;
  jumlah_duplikasi_tuntas?: number;
  jumlah_kewenangan_kabupaten?: number;
  jumlah_belum_dianalisa?: number;
}

export interface BkkdRiwayat {
  id: string;
  id_proposal: string;
  versi: number;
  aksi: string;
  field_berubah?: string[] | null;
  data_sebelum?: Record<string, any> | null;
  data_sesudah?: Record<string, any> | null;
  alasan_perubahan?: string | null;
  changed_by?: string | null;
  changed_by_name?: string | null;
  proposal_nomor?: string | null;
  proposal_judul?: string | null;
  nama_desa?: string | null;
  nama_kecamatan?: string | null;
  created_at: string;
}

export interface BkkdAgregasiProposalItem {
  id: string;
  nomor_registrasi: string;
  judul_kegiatan: string;
  id_bidang?: string | null;
  bidang?: string | null;
  nilai_usulan?: number | null;
  status_analisa_spasial?: StatusAnalisaSpasialBkkd | string;
  status?: string;
}

export interface BkkdAgregasiDesaItem {
  id_desa: number;
  nama_desa: string;
  id_kecamatan: number;
  nama_kecamatan: string;
  id_bidang?: string | null;
  nama_bidang: string;
  jumlah_usulan: number;
  total_nilai_usulan?: number;
  proposals: BkkdAgregasiProposalItem[];
}

export interface BkkdAgregasiDesaSummary {
  total_desa: number;
  desa_ada_usulan: number;
  desa_belum_ada_usulan: number;
  total_usulan: number;
  total_nilai_usulan: number;
}

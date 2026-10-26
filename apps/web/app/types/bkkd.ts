export type StatusBkkdProposal =
  | 'draft'
  | 'diajukan_opd'
  | 'dikembalikan'
  | 'layak'
  | 'layak_bersyarat'
  | 'tidak_layak'
  | 'ditetapkan_tapd'
  | 'tidak_terpilih'
  | 'digantikan_papbd'
  | 'dibatalkan';

export type HasilVerifikasiBkkd =
  | 'layak'
  | 'layak_bersyarat'
  | 'tidak_layak'
  | 'dikembalikan';

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

export interface BkkdProposalLampiran {
  id: string;
  id_proposal: string;
  tipe_sumber?: 'file' | 'link';
  url_link?: string | null;
  jenis_dokumen: 'proposal_desa' | 'rab' | 'gambar_rencana' | 'foto_kondisi_0' | 'surat_pengantar_kecamatan' | 'lainnya';
  nama_asli: string;
  mime_type?: string | null;
  ukuran_byte?: number | null;
  uploaded_by_name?: string | null;
  created_at: string;
}

export interface BkkdVerifikasi {
  id: string;
  id_proposal: string;
  id_opd: string;
  kode_opd?: string | null;
  nama_opd?: string | null;
  nomor_surat_hasil: string;
  tanggal_verifikasi?: string;
  tanggal_surat_hasil?: string | null;
  tanggal_diterima?: string | null;
  hasil: HasilVerifikasiBkkd;
  nilai_rekomendasi?: number | null;
  rekomendasi_anggaran?: number | null;
  volume_rekomendasi?: number | null;
  panjang_rekomendasi?: number | null;
  lebar_rekomendasi?: number | null;
  tinggi_rekomendasi?: number | null;
  catatan_opd?: string | null;
  nama_dokumen_asli?: string | null;
  dokumen_hasil_nama?: string | null;
  has_dokumen?: boolean;
  is_aktif: boolean;
  versi: number;
  input_by_name?: string | null;
  updated_by_name?: string | null;
  diverifikasi_oleh_nama?: string | null;
  created_at: string;
  updated_at: string;
  opd?: RefOpd | null;
  proposal?: BkkdProposal | null;
  is_proposal_deleted?: boolean;
}

export interface BkkdProposal {
  id: string;
  nomor_registrasi: string;
  tahun_anggaran: number;
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
  latitude?: number | null;
  longitude?: number | null;
  panjang?: number | null;
  lebar?: number | null;
  tinggi?: number | null;
  volume?: number | null;
  volume_target?: number | null;
  satuan?: string | null;
  satuan_target?: string | null;
  nilai_usulan?: number | null;
  nomor_surat?: string | null;
  nomor_surat_desa?: string | null;
  tanggal_surat_desa?: string | null;
  tanggal_diterima?: string | null;
  status: StatusBkkdProposal;
  tahap_anggaran?: 'induk' | 'perubahan' | string | null;
  id_proposal_induk?: string | null;
  alasan_pergantian_papbd?: string | null;
  proposal_induk?: Partial<BkkdProposal> | null;
  proposal_pengganti?: Partial<BkkdProposal>[];
  versi: number;
  nilai_disetujui_tapd?: number | null;
  catatan_tapd?: string | null;
  tanggal_penetapan_tapd?: string | null;
  ditetapkan_oleh_nama?: string | null;
  verifikasi_aktif?: BkkdVerifikasi | null;
  lampiran?: BkkdProposalLampiran[];
  total_lampiran?: number;
  total_lampiran_file?: number;
  total_lampiran_link?: number;
  has_lampiran?: boolean;
  cloud_links?: { id: string; nama: string; url: string }[];
  semua_verifikasi?: BkkdVerifikasi[];
  geom?: any;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
  is_deleted?: boolean;
}

export interface BkkdProposalSummary {
  total_proposal: number;
  total_nilai_usulan: number;
  draft: number;
  diajukan_opd: number;
  layak: number;
  layak_bersyarat: number;
  tidak_layak: number;
  ditetapkan_tapd: number;
  belum_upload?: number;
  jumlah_belum_upload?: number;
}

export interface BkkdRekapitulasiDesaItem {
  id_desa: number;
  nama_desa: string;
  id_kecamatan: number;
  nama_kecamatan: string;
  jumlah_usulan: number;
  total_nilai_usulan: number;
  ada_usulan_ditetapkan: boolean;
  usulan_terpilih_id?: string | null;
  usulan_terpilih_judul?: string | null;
  nilai_disetujui_tapd?: number | null;
  proposals: BkkdProposal[];
}

export interface BkkdRekapitulasiSummary {
  total_desa_pengusul: number;
  total_usulan_masuk: number;
  desa_terakomodir_tapd: number;
  total_pagu_rancangan_apbd: number;
}

export interface BkkdRiwayat {
  id: string;
  id_proposal: string;
  id_verifikasi?: string | null;
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
  opd_nama?: string | null;
  created_at: string;
}

export interface BkkdAgregasiProposalItem {
  id: string;
  nomor_registrasi: string;
  nomor_surat_desa?: string | null;
  judul_kegiatan: string;
  id_bidang?: string | null;
  bidang?: string | null;
  opd?: string | null;
  nilai_usulan?: number | null;
  nilai_rekomendasi?: number | null;
  nilai_disetujui_tapd?: number | null;
  status: string;
  is_diverifikasi: boolean;
  is_dipilih: boolean;
}

export interface BkkdAgregasiDesaItem {
  id_desa: number;
  nama_desa: string;
  id_kecamatan: number;
  nama_kecamatan: string;
  id_bidang?: string | null;
  nama_bidang: string;
  id_opd?: string | null;
  nama_opd: string;
  kode_opd?: string;
  jumlah_usulan: number;
  usulan_diverifikasi: number;
  usulan_dipilih: number;
  total_nilai_usulan?: number;
  total_rekomendasi_opd?: number;
  total_disetujui_tapd?: number;
  proposals: BkkdAgregasiProposalItem[];
}

export interface BkkdAgregasiDesaSummary {
  total_desa: number;
  desa_ada_usulan: number;
  desa_belum_ada_usulan: number;
  desa_diverifikasi: number;
  desa_dipilih_tapd: number;
  total_usulan: number;
  total_usulan_diverifikasi: number;
  total_usulan_dipilih: number;
}

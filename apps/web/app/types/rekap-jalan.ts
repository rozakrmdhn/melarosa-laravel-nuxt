export type KategoriJalan = 'ruas' | 'non_ruas';

export interface RekapKecamatanItem {
  id_kecamatan: number;
  nama_kecamatan: string;
  jumlah_desa: number;
  jumlah_desa_terbangun?: number;
  jumlah_ruas: number;
  total_panjang: number;
  panjang_dibangun: number;
  panjang_belum: number;
  progress: number;
  jumlah_segmen: number;
}

export interface RekapDesaItem {
  id_desa: number | string;
  nama_desa: string;
  kecamatan: string;
  jumlah_ruas: number;
  total_panjang: number;
  panjang_dibangun: number;
  panjang_belum: number;
  progress: number;
  jumlah_segmen: number;
}

export interface RekapRuasItem {
  id: number | string;
  kode_ruas?: string | number;
  nama_ruas: string;
  panjang_master: number;
  panjang_dibangun: number;
  panjang_tersisa: number;
  progress: number;
  jumlah_segmen: number;
  is_poros?: boolean;
  panjang?: number;
  lebar?: number;
  kondisi?: string;
  status_kondisi?: string;
  tahun_pembangunan?: number;
  sumber_dana?: string;
  status_aset?: string;
  status_verifikasi?: string;
  keterangan?: string;
  atribut?: {
    status_jalan?: string;
    jenis_perkerasan?: string;
    [key: string]: any;
  };
  geom?: {
    type: string;
    coordinates: number[][] | number[][][];
  };
}

export interface RekapSegmenItem {
  id: string;
  feature_type?: string;
  tahun?: number;
  panjang: number;
  lebar: number;
  kondisi: string;
  sumber_dana?: string;
  status_kondisi?: string;
  keterangan?: string;
  geom?: {
    type: string;
    coordinates: number[][] | number[][][];
  };
}

export interface RekapFilterParams {
  id_kecamatan?: number;
  id_desa?: number | string;
  kecamatan?: string;
  desa?: string;
  tahun?: number;
  kondisi?: string;
  sumber_dana?: string;
  status_verifikasi?: string;
  kategori?: KategoriJalan;
  [key: string]: any;
}

export interface RekapSummaryCards {
  totalKecamatan: number;
  totalDesa: number;
  totalRuas: number;
  totalPanjangMeter: number;
  totalPanjangDibangun: number;
  totalPanjangBelum: number;
  rataRataProgress: number;
  totalSegmen: number;
}

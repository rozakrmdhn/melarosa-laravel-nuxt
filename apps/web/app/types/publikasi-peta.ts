export type PublikasiTingkatWilayah = 'kabupaten' | 'kecamatan' | 'desa';
export type PublikasiSumberTipe = 'file_pdf' | 'external_url';
export type PublikasiGeorefMethod = 'affine' | 'homography' | 'corners';
export type PublikasiLngLat = [number, number];
export type PublikasiGeorefCorners = [PublikasiLngLat, PublikasiLngLat, PublikasiLngLat, PublikasiLngLat];

export interface PublikasiGeorefGcp {
  id: string;
  label: string;
  px: number;
  py: number;
  lng: number;
  lat: number;
}

export interface PublikasiPetaDigitalItem {
  id: string;
  judul: string;
  slug: string;
  nomor_dokumen?: string | null;
  kategori: string;
  tingkat_wilayah: PublikasiTingkatWilayah;
  id_kecamatan?: number | null;
  nama_kecamatan?: string | null;
  id_desa?: number | null;
  nama_desa?: string | null;
  wilayah_label?: string;
  tahun_terbit: number;
  edisi?: string | null;
  skala_peta?: string | null;
  ringkasan?: string | null;
  sumber_tipe: PublikasiSumberTipe;
  external_url?: string | null;
  external_domain?: string | null;
  original_filename?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  file_size_label?: string | null;
  has_file?: boolean;
  has_external_url?: boolean;
  download_path?: string;
  preview_path?: string | null;
  download_count: number;
  is_georeferenced?: boolean;
  georef_method?: PublikasiGeorefMethod | null;
  georef_coordinates?: PublikasiGeorefCorners | null;
  georef_bbox?: [number, number, number, number] | null;
  georef_gcps?: PublikasiGeorefGcp[] | null;
  georef_rms_meters?: number | null;
  georef_opacity?: number | null;
  georef_image_mime?: string | null;
  georef_image_width?: number | null;
  georef_image_height?: number | null;
  georeferenced_at?: string | null;
  has_georef_image?: boolean;
  georef_image_path_url?: string | null;
  is_published: boolean;
  published_at?: string | null;
  created_at?: string;
  updated_at?: string;
  creator?: { uuid: string; name: string } | null;
  updater?: { uuid: string; name: string } | null;
}

export interface PublikasiWilayahKecamatan {
  id: number;
  nama_kecamatan: string;
}

export interface PublikasiWilayahDesa {
  id: number;
  id_kecamatan: number;
  nama_desa: string;
}

export const PUBLIKASI_KATEGORI_OPTIONS = [
  'Peta Jalan Poros Desa',
  'Peta Infrastruktur Desa',
  'Peta Batas Administrasi',
  'Peta Rencana Pembangunan',
  'Atlas & Peta Dasar Wilayah',
  'Peta Tematik Lainnya',
] as const;

export const PUBLIKASI_TINGKAT_OPTIONS: Array<{ label: string; value: PublikasiTingkatWilayah }> = [
  { label: 'Tingkat Desa / Kelurahan', value: 'desa' },
  { label: 'Tingkat Kecamatan', value: 'kecamatan' },
  { label: 'Tingkat Kabupaten', value: 'kabupaten' },
];

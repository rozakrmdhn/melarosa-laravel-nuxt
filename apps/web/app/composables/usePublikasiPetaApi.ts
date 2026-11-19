import type {
  PublikasiPetaDigitalItem,
  PublikasiWilayahDesa,
  PublikasiWilayahKecamatan,
} from '~/types/publikasi-peta';

export interface PublikasiListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  available_years?: number[];
  categories?: string[];
}

export interface PublikasiListParams {
  page?: number;
  per_page?: number;
  search?: string;
  tingkat_wilayah?: string;
  id_kecamatan?: number | string | null;
  id_desa?: number | string | null;
  tahun_terbit?: number | string | null;
  kategori?: string;
  sumber_tipe?: string;
  is_published?: boolean | string | null;
}

export function resolvePublikasiDownloadUrl(idOrSlug: string, opts?: { inline?: boolean }): string {
  const config = useRuntimeConfig();
  const prefix = config.public.apiPrefix || '/api/v1';
  const query = opts?.inline ? '?inline=1' : '';
  const rel = `/publikasi-peta/${encodeURIComponent(idOrSlug)}/download${query}`;

  // Gunakan path same-origin (/api/v1/...) yang diproksi Nitro ke backend agar iframe & unduhan
  // tidak terkena mismatch localhost/127.0.0.1 atau pemblokiran CSP/X-Frame-Options.
  if (import.meta.client) {
    return `${prefix}${rel}`;
  }

  const base = config.public.apiBase || 'http://127.0.0.1:9000';
  return `${base}${prefix}${rel}`;
}

export function resolvePublikasiGeorefImageUrl(idOrSlug: string, version?: string | number | null): string {
  const config = useRuntimeConfig();
  const prefix = config.public.apiPrefix || '/api/v1';
  const vQuery = version ? `?v=${encodeURIComponent(String(version))}` : '';
  const rel = `/publikasi-peta/${encodeURIComponent(idOrSlug)}/georef-image${vQuery}`;

  if (import.meta.client) {
    return `${prefix}${rel}`;
  }

  const base = config.public.apiBase || 'http://127.0.0.1:9000';
  return `${base}${prefix}${rel}`;
}

export function usePublikasiPetaApi() {
  async function fetchPublicList(params?: PublikasiListParams) {
    return $http<{ ok: boolean; data: PublikasiPetaDigitalItem[]; meta: PublikasiListMeta }>(
      'publikasi-peta',
      { method: 'GET', query: params },
    );
  }

  async function fetchPublicDetail(idOrSlug: string) {
    return $http<{ ok: boolean; data: PublikasiPetaDigitalItem }>(
      `publikasi-peta/${encodeURIComponent(idOrSlug)}`,
      { method: 'GET' },
    );
  }

  async function fetchPublicWilayahOptions(idKecamatan?: number | null) {
    return $http<{
      ok: boolean;
      kecamatan: PublikasiWilayahKecamatan[];
      desa: PublikasiWilayahDesa[];
    }>('publikasi-peta/wilayah-options', {
      method: 'GET',
      query: idKecamatan ? { id_kecamatan: idKecamatan } : undefined,
    });
  }

  async function fetchAdminList(params?: PublikasiListParams) {
    return $http<{ ok: boolean; data: PublikasiPetaDigitalItem[]; meta: PublikasiListMeta }>(
      'admin/publikasi-peta',
      { method: 'GET', query: params },
    );
  }

  async function fetchAdminDetail(id: string) {
    return $http<{ ok: boolean; data: PublikasiPetaDigitalItem }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}`,
      { method: 'GET' },
    );
  }

  async function createItem(formData: FormData) {
    return $http<{ ok: boolean; message?: string; data: PublikasiPetaDigitalItem }>(
      'admin/publikasi-peta',
      { method: 'POST', body: formData },
    );
  }

  async function updateItem(id: string, formData: FormData) {
    return $http<{ ok: boolean; message?: string; data: PublikasiPetaDigitalItem }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}`,
      { method: 'POST', body: formData },
    );
  }

  async function togglePublish(id: string) {
    return $http<{ ok: boolean; message?: string; data: PublikasiPetaDigitalItem }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}/toggle-publish`,
      { method: 'PATCH' },
    );
  }

  async function deleteItem(id: string) {
    return $http<{ ok: boolean; message?: string }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}`,
      { method: 'DELETE' },
    );
  }

  async function fetchWilayahBoundary(id: string) {
    return $http<{ ok: boolean; feature: any | null }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}/wilayah-boundary`,
      { method: 'GET' },
    );
  }

  async function saveGeoreference(id: string, formData: FormData) {
    return $http<{ ok: boolean; message?: string; data: PublikasiPetaDigitalItem }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}/georeference`,
      { method: 'POST', body: formData },
    );
  }

  async function clearGeoreference(id: string) {
    return $http<{ ok: boolean; message?: string; data: PublikasiPetaDigitalItem }>(
      `admin/publikasi-peta/${encodeURIComponent(id)}/georeference`,
      { method: 'DELETE' },
    );
  }

  return {
    fetchPublicList,
    fetchPublicDetail,
    fetchPublicWilayahOptions,
    fetchAdminList,
    fetchAdminDetail,
    createItem,
    updateItem,
    togglePublish,
    deleteItem,
    fetchWilayahBoundary,
    saveGeoreference,
    clearGeoreference,
  };
}

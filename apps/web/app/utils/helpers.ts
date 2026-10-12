import type { NitroFetchRequest } from 'nitropack/types';
import type { HttpFetchOptions } from '~~';

export function $http<T = unknown>(request: NitroFetchRequest, opts?: HttpFetchOptions): Promise<T> {
  const { $http: http } = useNuxtApp();

  return http(request, opts);
}

export function $storage(path: string): string {
  if (!path) return '';

  const config = useRuntimeConfig();

  return path.startsWith('http://') || path.startsWith('https://')
    ? path
    : config.public.storageBase + path;
}

/**
 * Format nama wilayah (Desa / Kecamatan) dari berbagai format:
 * - String biasa: "Nglarangan"
 * - Object relasi: { id: 3522112024, nama_desa: "Nglarangan" }
 * - Array: [{ id: ..., nama_desa: "Nglarangan" }]
 * - JSON string: '{"id": 3522112024, "nama_desa": "Nglarangan"}'
 */
export function formatWilayah(val: any): string {
  if (val === null || val === undefined || val === '') return '-';

  if (typeof val === 'string') {
    const trimmed = val.trim();
    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
      try {
        const parsed = JSON.parse(trimmed);
        return formatWilayah(parsed);
      } catch {
        return trimmed;
      }
    }
    return trimmed;
  }

  if (Array.isArray(val)) {
    const names = val
      .map((item) => formatWilayah(item))
      .filter((name) => name && name !== '-');
    return names.length > 0 ? names.join(', ') : '-';
  }

  if (typeof val === 'object') {
    const name =
      val.nama_desa ??
      val.nama_kecamatan ??
      val.nama ??
      val.name ??
      val.label ??
      val.nama_wilayah ??
      (val.id !== undefined && val.id !== null ? String(val.id) : null);
    return name !== null && name !== undefined ? String(name) : '-';
  }

  return String(val);
}


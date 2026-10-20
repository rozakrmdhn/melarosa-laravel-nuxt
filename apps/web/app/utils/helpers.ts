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

/**
 * Menghasilkan opsi filter tahun anggaran dinamis:
 * Berdasarkan tahun berjalan saat ini, dimulai dari N+1 (misal: 2027 jika tahun ini 2026)
 * sampai 5 tahun ke belakang (2022).
 */
export function getTahunAnggaranOptions(includeAll = true, prefix = ''): { label: string; value: number | 'ALL' }[] {
  const currentYear = new Date().getFullYear();
  const startYear = currentYear + 1;
  const options: { label: string; value: number | 'ALL' }[] = [];

  if (includeAll) {
    options.push({ label: 'Semua Tahun', value: 'ALL' });
  }

  for (let y = startYear; y >= startYear - 5; y--) {
    options.push({
      label: prefix ? `${prefix} ${y}` : String(y),
      value: y,
    });
  }

  return options;
}


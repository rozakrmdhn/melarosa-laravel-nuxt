import type { HierarkiJalan, StatusAnalisaRtrw } from '~/types/jaringan-jalan';

export interface StatusAnalisaMeta {
  key: StatusAnalisaRtrw;
  label: string;
  shortLabel: string;
  swatchHex: string;
  dotClass: string;
  barClass: string;
  badgeClass: string;
  activePillClass: string;
}

export const STATUS_ANALISA_META: Record<StatusAnalisaRtrw, StatusAnalisaMeta> = {
  belum_terdata: {
    key: 'belum_terdata',
    label: 'Belum Terdata',
    shortLabel: 'Belum Terdata',
    swatchHex: '#d97706',
    dotClass: 'bg-amber-600 dark:bg-amber-500',
    barClass: 'bg-amber-600 dark:bg-amber-500',
    badgeClass:
      'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/80 dark:text-amber-200 dark:border-amber-700',
    activePillClass:
      'border-amber-700 bg-amber-700 text-white dark:border-amber-500 dark:bg-amber-600 dark:text-white',
  },
  irisan_sebagian: {
    key: 'irisan_sebagian',
    label: 'Irisan Sebagian',
    shortLabel: 'Sebagian',
    swatchHex: '#7c3aed',
    dotClass: 'bg-violet-600 dark:bg-violet-500',
    barClass: 'bg-violet-600 dark:bg-violet-500',
    badgeClass:
      'bg-violet-50 text-violet-900 border-violet-300 dark:bg-violet-950/80 dark:text-violet-200 dark:border-violet-700',
    activePillClass:
      'border-violet-700 bg-violet-700 text-white dark:border-violet-500 dark:bg-violet-600 dark:text-white',
  },
  terdata_poros_desa: {
    key: 'terdata_poros_desa',
    label: 'Terdata Poros Desa',
    shortLabel: 'Poros Desa',
    swatchHex: '#059669',
    dotClass: 'bg-emerald-600 dark:bg-emerald-500',
    barClass: 'bg-emerald-600 dark:bg-emerald-500',
    badgeClass:
      'bg-emerald-50 text-emerald-900 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-200 dark:border-emerald-700',
    activePillClass:
      'border-[#176B4D] bg-[#176B4D] text-white dark:border-emerald-600 dark:bg-emerald-700 dark:text-white',
  },
  terdata_jalan_kabupaten: {
    key: 'terdata_jalan_kabupaten',
    label: 'Terdata Jalan Kabupaten',
    shortLabel: 'Jalan Kab.',
    swatchHex: '#0284c7',
    dotClass: 'bg-sky-600 dark:bg-sky-500',
    barClass: 'bg-sky-600 dark:bg-sky-500',
    badgeClass:
      'bg-sky-50 text-sky-900 border-sky-300 dark:bg-sky-950/80 dark:text-sky-200 dark:border-sky-700',
    activePillClass:
      'border-sky-700 bg-sky-700 text-white dark:border-sky-500 dark:bg-sky-600 dark:text-white',
  },
  tumpang_tindih_keduanya: {
    key: 'tumpang_tindih_keduanya',
    label: 'Beririsan Keduanya',
    shortLabel: 'Overlap 2 Sumber',
    swatchHex: '#e11d48',
    dotClass: 'bg-rose-600 dark:bg-rose-500',
    barClass: 'bg-rose-600 dark:bg-rose-500',
    badgeClass:
      'bg-rose-50 text-rose-900 border-rose-300 dark:bg-rose-950/80 dark:text-rose-200 dark:border-rose-700',
    activePillClass:
      'border-rose-700 bg-rose-700 text-white dark:border-rose-500 dark:bg-rose-600 dark:text-white',
  },
};

export const STATUS_ANALISA_LIST: StatusAnalisaMeta[] = [
  STATUS_ANALISA_META.belum_terdata,
  STATUS_ANALISA_META.irisan_sebagian,
  STATUS_ANALISA_META.terdata_poros_desa,
  STATUS_ANALISA_META.terdata_jalan_kabupaten,
  STATUS_ANALISA_META.tumpang_tindih_keduanya,
];

export const ORDE_LEGEND_ITEMS = [
  { key: 'Jalan Desa', label: 'Jalan Desa RTRW', color: '#d97706' },
  { key: 'Jaringan Jalan Kabupaten', label: 'Jaringan Jalan Kabupaten', color: '#0284c7' },
  { key: 'Jaringan Jalan Provinsi', label: 'Jaringan Jalan Provinsi', color: '#7c3aed' },
  { key: 'Sistem Jaringan Jalan Nasional', label: 'Sistem Jalan Nasional', color: '#dc2626' },
] as const;

export function getStatusAnalisaMeta(status?: StatusAnalisaRtrw | string | null): StatusAnalisaMeta {
  if (status && status in STATUS_ANALISA_META) {
    return STATUS_ANALISA_META[status as StatusAnalisaRtrw];
  }
  return STATUS_ANALISA_META.belum_terdata;
}

export function formatAngka(val?: number | null, maxDecimals = 1): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return '0';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maxDecimals }).format(Number(val));
}

export function formatPanjangKm(meters?: number | null): string {
  const m = Number(meters ?? 0);
  if (!Number.isFinite(m) || m <= 0) return '0 km';
  if (m < 1000) return `${formatAngka(m, 0)} m`;
  return `${formatAngka(m / 1000, 1)} km`;
}

export function getHierarkiJalanLabel(hierarki?: HierarkiJalan | string | null): string {
  switch (hierarki) {
    case 'kabupaten':
      return 'Jalan Kabupaten';
    case 'kabupaten_usulan':
      return 'Kabupaten Usulan';
    case 'poros_desa':
      return 'Jalan Poros Desa';
    case 'usaha_tani':
      return 'Jalan Usaha Tani';
    case 'lingkungan':
      return 'Jalan Lingkungan';
    default:
      return hierarki ? String(hierarki) : 'Jalan Poros Desa';
  }
}

export function computeGeometryBbox(geom: any): [number, number, number, number] | null {
  if (!geom) return null;
  const g = typeof geom === 'string' ? JSON.parse(geom) : geom;
  const target = g?.type === 'Feature' ? g.geometry : g;
  if (!target?.coordinates) return null;

  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;

  const walk = (node: any) => {
    if (!Array.isArray(node)) return;
    if (typeof node[0] === 'number' && typeof node[1] === 'number') {
      if (node[0] < minX) minX = node[0];
      if (node[0] > maxX) maxX = node[0];
      if (node[1] < minY) minY = node[1];
      if (node[1] > maxY) maxY = node[1];
      return;
    }
    for (const child of node) walk(child);
  };

  walk(target.coordinates);
  if (!Number.isFinite(minX)) return null;
  return [minX, minY, maxX, maxY];
}

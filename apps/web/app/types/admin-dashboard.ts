export interface KecamatanSeriesItem {
  id: number;
  nama_kecamatan: string;
  nama_pimpinan?: string | null;
  nama_jabatan?: string | null;
  jumlah_desa: number;
  luas_hektar: number;
  bbox?: [number, number, number, number] | number[] | null;
}

export interface KondisiBreakdownItem {
  kondisi: string;
  jumlah: number;
  panjang_meter?: number;
  panjang_km?: number;
}

export interface PerkerasanBreakdownItem {
  perkerasan: string;
  jumlah: number;
  panjang_meter?: number;
  panjang_km?: number;
}

export interface VerifikasiBreakdownItem {
  status_verifikasi: string;
  jumlah: number;
  panjang_meter?: number;
}

export interface InfrastructureSummaryResponse {
  ok?: boolean;
  total_segmen?: number;
  total_panjang_meter?: number;
  total_panjang_km?: number;
  kondisi?: KondisiBreakdownItem[];
  perkerasan?: PerkerasanBreakdownItem[];
  verifikasi?: VerifikasiBreakdownItem[];
  bbox?: number[];
}

export interface JalanPorosSummaryResponse {
  ok?: boolean;
  total_ruas?: number;
  total_panjang_meter?: number;
  total_panjang_km?: number;
  max_kode_ruas?: number;
  next_kode_ruas?: number;
  kondisi?: KondisiBreakdownItem[];
  perkerasan?: PerkerasanBreakdownItem[];
  bbox?: number[];
}

export interface MartinStatusData {
  connected?: boolean;
  status?: "online" | "fallback" | "offline" | string;
  message?: string;
  service_name?: string;
  remote_sources?: number | null;
  total_local_layers?: number;
  active_local_layers?: number;
  last_synced_at?: string | null;
  response_time_ms?: number | null;
  error_detail?: string | null;
  checked_at?: string | null;
}

export interface BkkdSummaryData {
  total_proposal?: number;
  total_nilai_usulan?: number;
  total_lokasi?: number;
  total_panjang_gis?: number;
  jumlah_aman?: number;
  jumlah_lanjutan_ruas?: number;
  jumlah_konfirmasi_belum_tuntas?: number;
  jumlah_duplikasi_tuntas?: number;
  jumlah_belum_dianalisa?: number;
}

export const BOJONEGORO_DEFAULT_BBOX: [number, number, number, number] = [
  111.445099,
  -7.451684,
  112.163338,
  -6.981707,
];

export function formatIntId(val: number | null | undefined): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return "-";
  return Number(val).toLocaleString("id-ID");
}

export function formatDecimalId(
  val: number | null | undefined,
  decimals = 2,
): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return "-";
  return Number(val).toLocaleString("id-ID", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

export function formatRupiahCompact(val: number | null | undefined): string {
  if (val === null || val === undefined || Number.isNaN(Number(val))) return "-";
  const num = Number(val);
  if (num >= 1_000_000_000) {
    return `Rp ${formatDecimalId(num / 1_000_000_000, 2)} M`;
  }
  if (num >= 1_000_000) {
    return `Rp ${formatDecimalId(num / 1_000_000, 1)} Jt`;
  }
  return `Rp ${formatIntId(Math.round(num))}`;
}

export function formatRelativeTimeId(dateInput?: string | null): string {
  if (!dateInput) return "Belum ada data";
  const dt = new Date(dateInput);
  if (Number.isNaN(dt.getTime())) return "Waktu tidak valid";

  const diffSeconds = Math.floor((Date.now() - dt.getTime()) / 1000);
  if (diffSeconds < 60) return "Baru saja";
  const diffMinutes = Math.floor(diffSeconds / 60);
  if (diffMinutes < 60) return `${diffMinutes} menit lalu`;
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours} jam lalu`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 30) return `${diffDays} hari lalu`;

  return dt.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export interface VisualToneMeta {
  label: string;
  hex: string;
  barClass: string;
  dotClass: string;
  badgeClass: string;
}

export function getKondisiMeta(kondisi: string): VisualToneMeta {
  const key = (kondisi || "").toLowerCase().trim();
  if (key.includes("baik")) {
    return {
      label: kondisi || "Baik",
      hex: "#059669",
      barClass: "bg-emerald-600 dark:bg-emerald-500",
      dotClass: "bg-emerald-600 dark:bg-emerald-400",
      badgeClass:
        "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/70",
    };
  }
  if (key.includes("sedang") && !key.includes("rusak")) {
    return {
      label: kondisi || "Sedang",
      hex: "#2563eb",
      barClass: "bg-blue-600 dark:bg-blue-500",
      dotClass: "bg-blue-600 dark:bg-blue-400",
      badgeClass:
        "bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/70",
    };
  }
  if (key.includes("ringan")) {
    return {
      label: kondisi || "Rusak Ringan",
      hex: "#d97706",
      barClass: "bg-amber-600 dark:bg-amber-500",
      dotClass: "bg-amber-600 dark:bg-amber-400",
      badgeClass:
        "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/70",
    };
  }
  if (key.includes("berat") || key.includes("rusak")) {
    return {
      label: kondisi || "Rusak Berat",
      hex: "#dc2626",
      barClass: "bg-red-600 dark:bg-red-500",
      dotClass: "bg-red-600 dark:bg-red-400",
      badgeClass:
        "bg-red-50 text-red-700 border-red-200 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800/70",
    };
  }
  return {
    label: kondisi || "Belum Dinilai",
    hex: "#64748b",
    barClass: "bg-slate-500 dark:bg-slate-400",
    dotClass: "bg-slate-500 dark:bg-slate-400",
    badgeClass:
      "bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700",
  };
}

export function getVerifikasiMeta(status: string): VisualToneMeta {
  switch (status) {
    case "verified_bappeda":
      return {
        label: "Disetujui Bappeda",
        hex: "#059669",
        barClass: "bg-emerald-600 dark:bg-emerald-500",
        dotClass: "bg-emerald-600 dark:bg-emerald-400",
        badgeClass:
          "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/70",
      };
    case "verified_kecamatan":
      return {
        label: "Menunggu Bappeda",
        hex: "#2563eb",
        barClass: "bg-blue-600 dark:bg-blue-500",
        dotClass: "bg-blue-600 dark:bg-blue-400",
        badgeClass:
          "bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/70",
      };
    case "submitted_desa":
      return {
        label: "Ajuan Desa (Antrean Kec.)",
        hex: "#d97706",
        barClass: "bg-amber-600 dark:bg-amber-500",
        dotClass: "bg-amber-600 dark:bg-amber-400",
        badgeClass:
          "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/70",
      };
    case "rejected_kecamatan":
    case "rejected_bappeda":
      return {
        label: status === "rejected_kecamatan" ? "Perlu Revisi (Kecamatan)" : "Perlu Revisi (Bappeda)",
        hex: "#dc2626",
        barClass: "bg-red-600 dark:bg-red-500",
        dotClass: "bg-red-600 dark:bg-red-400",
        badgeClass:
          "bg-red-50 text-red-700 border-red-200 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800/70",
      };
    default:
      return {
        label: "Draft Internal",
        hex: "#64748b",
        barClass: "bg-slate-500 dark:bg-slate-400",
        dotClass: "bg-slate-500 dark:bg-slate-400",
        badgeClass:
          "bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700",
      };
  }
}

export function getRoleDisplayLabel(roles?: string[]): string {
  const list = roles ?? [];
  if (list.includes("admin")) return "Super Administrator";
  if (list.includes("verifierBappeda") || list.includes("verifikator-bappeda")) {
    return "Verifikator Bappeda";
  }
  if (list.includes("verifierKecamatan") || list.includes("verifikator-kecamatan")) {
    return "Verifikator Kecamatan";
  }
  if (list.includes("operatorDesa") || list.includes("operator-desa")) {
    return "Operator Desa";
  }
  if (list.length > 0) return list[0];
  return "Operator Sistem";
}

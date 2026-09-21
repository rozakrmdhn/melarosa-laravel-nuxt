export function getKondisiColor(kondisi: string | null | undefined): string {
  if (!kondisi) return "#71717a"; // zinc-500
  const k = kondisi.toLowerCase();
  if (k.includes("baik"))        return "#10b981"; // emerald-500
  if (k.includes("sedang"))      return "#f59e0b"; // amber-400
  if (k.includes("rusak berat")) return "#ef4444"; // red-500
  if (k.includes("rusak"))       return "#f97316"; // orange-500
  return "#71717a";
}

export function getKondisiBg(kondisi: string | null | undefined): string {
  if (!kondisi) return "bg-zinc-700/60 text-zinc-300 border border-zinc-600/40";
  const k = kondisi.toLowerCase();
  if (k.includes("baik"))        return "bg-emerald-950/60 text-emerald-300 border border-emerald-600/40";
  if (k.includes("sedang"))      return "bg-amber-950/60 text-amber-300 border border-amber-600/40";
  if (k.includes("rusak berat")) return "bg-red-950/60 text-red-300 border border-red-600/40";
  if (k.includes("rusak"))       return "bg-orange-950/60 text-orange-300 border border-orange-600/40";
  return "bg-zinc-800 text-zinc-400 border border-zinc-700";
}

export function formatKm(val: number | string | null | undefined): string {
  if (val === null || val === undefined || val === "") return "-";
  const num = typeof val === "string" ? parseFloat(val) : val;
  if (isNaN(num)) return "-";
  return num.toLocaleString("id-ID", { minimumFractionDigits: 2, maximumFractionDigits: 3 }) + " km";
}

export function getKondisiColor(kondisi: string | null | undefined): string {
  if (!kondisi) return "#71717a"; // slate-500
  const k = kondisi.toLowerCase();
  if (k.includes("baik"))        return "#10b981"; // blue-500
  if (k.includes("sedang"))      return "#f59e0b"; // amber-400
  if (k.includes("rusak berat")) return "#ef4444"; // red-500
  if (k.includes("rusak"))       return "#f97316"; // orange-500
  return "#71717a";
}

export function getKondisiBg(kondisi: string | null | undefined): string {
  if (!kondisi) return "bg-slate-700/60 text-slate-300 border border-slate-600/40";
  const k = kondisi.toLowerCase();
  if (k.includes("baik"))        return "bg-blue-950/60 text-blue-300 border border-blue-600/40";
  if (k.includes("sedang"))      return "bg-amber-950/60 text-amber-300 border border-amber-600/40";
  if (k.includes("rusak berat")) return "bg-red-950/60 text-red-300 border border-red-600/40";
  if (k.includes("rusak"))       return "bg-orange-950/60 text-orange-300 border border-orange-600/40";
  return "bg-slate-800 text-slate-400 border border-slate-700";
}

export function formatKm(val: number | string | null | undefined): string {
  if (val === null || val === undefined || val === "") return "-";
  const num = typeof val === "string" ? parseFloat(val) : val;
  if (isNaN(num)) return "-";
  return num.toLocaleString("id-ID", { minimumFractionDigits: 2, maximumFractionDigits: 3 }) + " km";
}

export interface ParsedCoordinate {
  lat: number;
  lng: number;
  formatted: string;
}

/**
 * Parsing input koordinat dari pengguna baik dalam format Desimal (DD) maupun DMS (Degrees Minutes Seconds).
 * Mendukung format:
 * - Desimal: `-7.1552, 111.9071` atau `111.9071, -7.1552`
 * - DMS Internasional: `7°09'14.6"S 111°53'33.4"E` atau `7° 9' 14.6" S, 111° 53' 33.4" E`
 * - DMS Indonesia: `7°09'14.6"LS 111°53'33.4"BT`
 */
export function parseCoordinateString(input: string): ParsedCoordinate | null {
  if (!input) return null;
  const q = input.trim();
  if (!q) return null;

  // 1. Cek format desimal standar: -7.12345, 111.12345 atau 111.12345, -7.12345
  const decimalRegex = /^(-?\d{1,3}(?:\.\d+)?)[,\s]+(-?\d{1,3}(?:\.\d+)?)$/;
  const decMatch = q.match(decimalRegex);
  if (decMatch) {
    const num1 = parseFloat(decMatch[1]);
    const num2 = parseFloat(decMatch[2]);
    let lat: number | null = null;
    let lng: number | null = null;

    if (Math.abs(num1) <= 90 && Math.abs(num2) <= 180) {
      lat = num1;
      lng = num2;
    } else if (Math.abs(num2) <= 90 && Math.abs(num1) <= 180) {
      lat = num2;
      lng = num1;
    }

    if (lat !== null && lng !== null) {
      return {
        lat,
        lng,
        formatted: `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
      };
    }
  }

  // 2. Cek format DMS (Degrees Minutes Seconds)
  const dmsTokenRegex = /([NSEWLUlsbt]{1,2})?\s*(\d+(?:\.\d+)?)\s*[°ºd\s]\s*(?:(\d+(?:\.\d+)?)\s*['′m\s]\s*)?(?:(\d+(?:\.\d+)?)\s*["″s\s]?)?\s*([NSEWLUlsbt]{1,2})?/gi;
  const matches = [...q.matchAll(dmsTokenRegex)];
  const validTokens = matches.filter((m) => m[2] !== undefined && m[2] !== "");

  if (validTokens.length >= 2) {
    let lat: number | null = null;
    let lng: number | null = null;

    for (let i = 0; i < 2; i++) {
      const token = validTokens[i];
      const prefixHemi = (token[1] || "").toUpperCase();
      const deg = parseFloat(token[2]);
      const min = token[3] ? parseFloat(token[3]) : 0;
      const sec = token[4] ? parseFloat(token[4]) : 0;
      const suffixHemi = (token[5] || "").toUpperCase();
      const hemi = suffixHemi || prefixHemi;

      const decVal = deg + min / 60 + sec / 3600;

      if (hemi === "S" || hemi === "LS") {
        lat = -decVal;
      } else if (hemi === "N" || hemi === "LU") {
        lat = decVal;
      } else if (hemi === "W" || hemi === "BB") {
        lng = -decVal;
      } else if (hemi === "E" || hemi === "BT") {
        lng = decVal;
      } else {
        if (lat === null && decVal <= 90) {
          lat = decVal;
        } else if (lng === null && decVal <= 180) {
          lng = decVal;
        }
      }
    }

    if (lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
      return {
        lat,
        lng,
        formatted: `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
      };
    }
  }

  return null;
}

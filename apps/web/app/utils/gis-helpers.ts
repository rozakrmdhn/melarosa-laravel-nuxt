import { KECAMATAN_ENTRIES, type KecamatanEntry } from "~/constants/kecamatan";

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
 * Format koordinat desimal ke format derajat menit detik (DMS) standar WGS 84.
 * Contoh: -7.15406, 111.89261 -> 7°09'14.6"S 111°53'33.4"E
 */
export function formatDMS(lat: number, lng: number): string {
  function toDmsStr(val: number, posHemi: string, negHemi: string): string {
    const isNeg = val < 0;
    const absVal = Math.abs(val);
    const deg = Math.floor(absVal);
    const minFloat = (absVal - deg) * 60;
    const min = Math.floor(minFloat);
    const sec = ((minFloat - min) * 60).toFixed(1);
    const hemi = isNeg ? negHemi : posHemi;
    const minPad = String(min).padStart(2, "0");
    const secPad = parseFloat(sec) < 10 ? "0" + sec : sec;
    return `${deg}°${minPad}'${secPad}"${hemi}`;
  }
  return `${toDmsStr(lat, "N", "S")} ${toDmsStr(lng, "E", "W")}`;
}

/**
 * Mencari kecamatan Bojonegoro yang melingkupi titik koordinat (berdasarkan bounding box spasial).
 */
export function getKecamatanFromCoordinate(lat: number, lng: number): KecamatanEntry | null {
  for (const k of KECAMATAN_ENTRIES) {
    const [minLng, minLat, maxLng, maxLat] = k.bbox;
    if (lng >= minLng && lng <= maxLng && lat >= minLat && lat <= maxLat) {
      return k;
    }
  }
  return null;
}

/**
 * Parsing input koordinat dari pengguna baik dalam format Desimal (DD) maupun DMS (Degrees Minutes Seconds).
 * Mendukung format:
 * - Desimal: `-7.1552, 111.9071`, `-7.1552 111.9071`, `111.9071, -7.1552`, `-7.1552; 111.9071`
 * - Desimal Kardinal: `7.1552° S, 111.9071° E`
 * - DMS Internasional: `7°09'14.6"S 111°53'33.4"E`, `7° 9' 14.6" S, 111° 53' 33.4" E`, `-7° 9' 14.6", 111° 53' 33.4"`
 * - DMS Indonesia: `7°09'14.6"LS 111°53'33.4"BT`
 * - DDM (Degrees Decimal Minutes): `7° 9.243' S 111° 53.556' E`
 */
export function parseCoordinateString(input: string): ParsedCoordinate | null {
  if (!input) return null;
  let q = input.trim();
  if (!q) return null;

  // Bersihkan label teks awalan umum
  q = q.replace(/(?:latitude|lat|lintang)\s*[:=]?\s*/gi, "")
       .replace(/(?:longitude|long|lon|lng|bujur)\s*[:=]?\s*/gi, " ")
       .trim();

  // 1. Cek format desimal standar: -7.12345, 111.12345 atau 111.12345, -7.12345 (pemisah koma, spasi, titik koma, slash)
  const decimalRegex = /^(-?\d{1,3}(?:\.\d+)?)[,\s;/]+(-?\d{1,3}(?:\.\d+)?)$/;
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

  // 2. Cek format DMS (Degrees Minutes Seconds) & DDM (Degrees Decimal Minutes)
  const dmsTokenRegex = /([NSEWLUlsbt]{1,2})?\s*(-?\d+(?:\.\d+)?)\s*[°ºd\s]\s*(?:(\d+(?:\.\d+)?)\s*['′m\s]\s*)?(?:(\d+(?:\.\d+)?)\s*["″s\s]?)?\s*([NSEWLUlsbt]{1,2})?/gi;
  const matches = [...q.matchAll(dmsTokenRegex)];
  const validTokens = matches.filter((m) => m[2] !== undefined && m[2] !== "");

  if (validTokens.length >= 2) {
    let lat: number | null = null;
    let lng: number | null = null;

    for (let i = 0; i < 2; i++) {
      const token = validTokens[i];
      const prefixHemi = (token[1] || "").toUpperCase();
      const rawDeg = parseFloat(token[2]);
      const isNeg = rawDeg < 0;
      const deg = Math.abs(rawDeg);
      const min = token[3] ? parseFloat(token[3]) : 0;
      const sec = token[4] ? parseFloat(token[4]) : 0;
      const suffixHemi = (token[5] || "").toUpperCase();
      const hemi = suffixHemi || prefixHemi;

      let decVal = deg + min / 60 + sec / 3600;
      if (isNeg) decVal = -decVal;

      if (hemi === "S" || hemi === "LS") {
        lat = -Math.abs(decVal);
      } else if (hemi === "N" || hemi === "LU") {
        lat = Math.abs(decVal);
      } else if (hemi === "W" || hemi === "BB") {
        lng = -Math.abs(decVal);
      } else if (hemi === "E" || hemi === "BT") {
        lng = Math.abs(decVal);
      } else {
        if (lat === null && Math.abs(decVal) <= 90) {
          lat = decVal;
        } else if (lng === null && Math.abs(decVal) <= 180) {
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

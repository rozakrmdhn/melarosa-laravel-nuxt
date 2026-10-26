import https from "node:https";

const GEOPARK_ENDPOINT = "https://geopark.bojonegorokab.go.id/api/diversity/list-diversity-site-list";

interface RawGeoparkSite {
  id: string;
  slug?: string;
  tag_id?: string;
  informasi_id?: string;
  judul_id?: string;
  narasi_id?: string;
  gambar?: string;
  gambarUrl?: string;
  gambar_pendukung?: string | string[];
  gambarPendukungUrl?: string[];
  latitude?: number | string;
  longitude?: number | string;
  ordering?: number | string;
  diversity_slug?: string;
  diversity_nama_id?: string;
  diversity_informasi_id?: string;
  diversity_narasi_id?: string;
  diversity_judul_id?: string;
  diversityLogoUrl?: string[];
}

export default defineEventHandler(async (event) => {
  // Cache response for 10 minutes on browser/proxy, stale-while-revalidate 1 hour
  setHeader(event, "Cache-Control", "public, max-age=600, s-maxage=600, stale-while-revalidate=3600");

  try {
    const agent = new https.Agent({
      rejectUnauthorized: false,
    });

    const responseText = await new Promise<string>((resolve, reject) => {
      const req = https.get(
        GEOPARK_ENDPOINT,
        {
          agent,
          headers: {
            Accept: "application/json",
            "User-Agent": "MelarosaWebGIS/1.0",
          },
          timeout: 12000,
        },
        (res) => {
          let data = "";
          res.on("data", (chunk) => (data += chunk));
          res.on("end", () => {
            if (res.statusCode && res.statusCode >= 200 && res.statusCode < 300) {
              resolve(data);
            } else {
              reject(new Error(`Upstream returned HTTP ${res.statusCode}`));
            }
          });
        }
      );

      req.on("error", (err) => reject(err));
      req.on("timeout", () => {
        req.destroy();
        reject(new Error("Timeout connecting to Geopark Bojonegoro server"));
      });
    });

    const parsed = JSON.parse(responseText);
    const rawList: RawGeoparkSite[] = Array.isArray(parsed?.data) ? parsed.data : [];

    const mapped = rawList.map((x) => {
      const rawTitle = String(x.judul_id || "").trim();
      const codeMatch = rawTitle.match(/^([A-Z]-[0-9]+)/);
      const code = codeMatch ? codeMatch[1] : (x.tag_id || "SITE");
      const name = rawTitle.replace(/^[A-Z]-[0-9]+\s*/, "").trim() || rawTitle;

      const lat = Number(x.latitude) || 0;
      const lng = Number(x.longitude) || 0;
      const cleanDesc = (x.informasi_id || "").replace(/<[^>]*>/g, "").trim();

      // Extract supporting images safely
      let supportingImages: string[] = [];
      if (Array.isArray(x.gambarPendukungUrl)) {
        supportingImages = x.gambarPendukungUrl.filter(Boolean);
      } else if (typeof x.gambar_pendukung === "string") {
        try {
          const parsedGallery = JSON.parse(x.gambar_pendukung);
          if (Array.isArray(parsedGallery)) supportingImages = parsedGallery.filter(Boolean);
        } catch {
          // ignore parsing error
        }
      }

      const rawSlug = String(x.slug || "").trim();
      const fallbackSlug = name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
      const slug = rawSlug || fallbackSlug || code.toLowerCase();

      return {
        id: String(x.id),
        code,
        title: rawTitle,
        name,
        slug,
        category: x.diversity_nama_id || "Keragaman Geologi",
        categorySlug: (x.diversity_slug || "geo-diversity") as string,
        tag: x.tag_id || "",
        desc: cleanDesc,
        narasiHtml: x.narasi_id || "",
        image: x.gambarUrl || x.gambar || "",
        additionalImages: supportingImages,
        latitude: lat,
        longitude: lng,
        coordinate: [lng, lat] as [number, number],
        hasCoordinate: Boolean(lat !== 0 && lng !== 0),
        zoom: lat !== 0 ? 14 : 11,
        ordering: Number(x.ordering) || 99,
        diversityInfo: x.diversity_informasi_id || "",
        diversityNarasi: x.diversity_narasi_id || "",
        diversityTitle: x.diversity_judul_id || "",
      };
    });

    return {
      success: true,
      count: mapped.length,
      data: mapped,
    };
  } catch (error: any) {
    console.error("[Geopark API Server Proxy Error]:", error?.message);
    throw createError({
      statusCode: 502,
      statusMessage: `Gagal mengambil data dari geopark.bojonegorokab.go.id: ${error?.message || "Kesalahan jaringan"}`,
    });
  }
});

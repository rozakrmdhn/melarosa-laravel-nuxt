export interface GeoparkSite {
  id: string;
  code: string;
  title: string;
  name: string;
  category: "Keragaman Geologi" | "Keragaman Hayati" | "Keragaman Budaya" | string;
  categorySlug: "geo-diversity" | "bio-diversity" | "culture-diversity" | string;
  tag: string;
  desc: string;
  narasiHtml: string;
  image: string;
  additionalImages: string[];
  latitude: number;
  longitude: number;
  coordinate: [number, number];
  hasCoordinate: boolean;
  zoom: number;
  ordering: number;
  diversityInfo?: string;
  diversityNarasi?: string;
  diversityTitle?: string;
}

const GEOPARK_DIRECT_ENDPOINT = "https://geopark.bojonegorokab.go.id/api/diversity/list-diversity-site-list";

// Transform raw API record from official Geopark Bojonegoro endpoint
export function mapRawGeoparkSite(x: any): GeoparkSite {
  const rawTitle = String(x.judul_id || "").trim();
  const codeMatch = rawTitle.match(/^([A-Z]-[0-9]+)/);
  const code = codeMatch ? codeMatch[1] : (x.tag_id || "SITE");
  const name = rawTitle.replace(/^[A-Z]-[0-9]+\s*/, "").trim() || rawTitle;

  const lat = Number(x.latitude) || 0;
  const lng = Number(x.longitude) || 0;
  const cleanDesc = (x.informasi_id || "").replace(/<[^>]*>/g, "").trim();

  let supportingImages: string[] = [];
  if (Array.isArray(x.gambarPendukungUrl)) {
    supportingImages = x.gambarPendukungUrl.filter(Boolean);
  } else if (typeof x.gambar_pendukung === "string") {
    try {
      const parsed = JSON.parse(x.gambar_pendukung);
      if (Array.isArray(parsed)) supportingImages = parsed.filter(Boolean);
    } catch {
      // ignore JSON parse errors
    }
  }

  return {
    id: String(x.id),
    code,
    title: rawTitle,
    name,
    category: x.diversity_nama_id || "Keragaman Geologi",
    categorySlug: x.diversity_slug || "geo-diversity",
    tag: x.tag_id || "",
    desc: cleanDesc,
    narasiHtml: x.narasi_id || "",
    image: x.gambarUrl || x.gambar || "",
    additionalImages: supportingImages,
    latitude: lat,
    longitude: lng,
    coordinate: [lng, lat],
    hasCoordinate: Boolean(lat !== 0 && lng !== 0),
    zoom: lat !== 0 ? 14 : 11,
    ordering: Number(x.ordering) || 99,
    diversityInfo: x.diversity_informasi_id || "",
    diversityNarasi: x.diversity_narasi_id || "",
    diversityTitle: x.diversity_judul_id || "",
  };
}

// Global shared state across panel components
const sites = ref<GeoparkSite[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const isInitialized = ref(false);
const selectedSite = ref<GeoparkSite | null>(null);

export function useGeoparkSites() {
  async function fetchLiveSites(forceRefresh = false) {
    loading.value = true;
    error.value = null;

    try {
      // 1. Direct hit via Melarosa API proxy (which calls official geopark.bojonegorokab.go.id server-to-server)
      const res = await $http<{ ok: boolean; count: number; data: any[] }>("geopark/sites", {
        query: forceRefresh ? { refresh: 1 } : {},
      });

      if (Array.isArray(res?.data) && res.data.length > 0) {
        sites.value = res.data.map(mapRawGeoparkSite);
        isInitialized.value = true;
        return;
      }

      // 2. Direct browser fetch fallback if proxy returns empty
      if (import.meta.client) {
        const directRes = await fetch(GEOPARK_DIRECT_ENDPOINT, {
          headers: { Accept: "application/json" },
        });
        if (directRes.ok) {
          const directJson = await directRes.json();
          if (Array.isArray(directJson?.data) && directJson.data.length > 0) {
            sites.value = directJson.data.map(mapRawGeoparkSite);
            isInitialized.value = true;
            return;
          }
        }
      }
    } catch (err: any) {
      // 3. Fallback to direct client fetch if proxy has network error
      if (import.meta.client) {
        try {
          const directRes = await fetch(GEOPARK_DIRECT_ENDPOINT, {
            headers: { Accept: "application/json" },
          });
          if (directRes.ok) {
            const directJson = await directRes.json();
            if (Array.isArray(directJson?.data) && directJson.data.length > 0) {
              sites.value = directJson.data.map(mapRawGeoparkSite);
              isInitialized.value = true;
              return;
            }
          }
        } catch {
          // direct fetch also failed
        }
      }

      console.warn("Geopark live API fetch error:", err);
      error.value = err?.message || "Gagal memuat data resmi Geopark Bojonegoro";
    } finally {
      loading.value = false;
      isInitialized.value = true;
    }
  }

  // Auto-fetch on mount if not yet initialized
  onMounted(() => {
    if (!isInitialized.value || sites.value.length === 0) {
      fetchLiveSites();
    }
  });

  const geologicalSites = computed(() =>
    sites.value.filter((s) => s.categorySlug === "geo-diversity" || s.category.includes("Geologi"))
  );

  const biologicalSites = computed(() =>
    sites.value.filter((s) => s.categorySlug === "bio-diversity" || s.category.includes("Hayati"))
  );

  const culturalSites = computed(() =>
    sites.value.filter((s) => s.categorySlug === "culture-diversity" || s.category.includes("Budaya"))
  );

  function searchSites(query: string) {
    const q = query.trim().toLowerCase();
    if (!q) return sites.value;
    return sites.value.filter(
      (s) =>
        s.title.toLowerCase().includes(q) ||
        s.name.toLowerCase().includes(q) ||
        s.code.toLowerCase().includes(q) ||
        s.tag.toLowerCase().includes(q) ||
        s.category.toLowerCase().includes(q) ||
        s.desc.toLowerCase().includes(q)
    );
  }

  function selectSite(site: GeoparkSite | null) {
    selectedSite.value = site;
  }

  return {
    sites,
    loading,
    error,
    selectedSite,
    selectSite,
    geologicalSites,
    biologicalSites,
    culturalSites,
    searchSites,
    fetchLiveSites,
  };
}

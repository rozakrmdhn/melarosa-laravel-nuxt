export type RecentMapItemKind = "kecamatan" | "desa" | "ruas" | "wisata";

export interface RecentMapItem {
  id: string;
  kind: RecentMapItemKind;
  label: string;
  sublabel?: string;
  kecamatanName?: string;
  desaName?: string;
  ruasId?: string | number;
  kodeRuas?: string | number;
  ruasSnapshot?: Record<string, any>;
  coordinate?: [number, number];
  zoom?: number;
  bbox?: [number, number, number, number];
  siteSlug?: string;
  visitedAt: number;
}

const STORAGE_KEY = "melarosa_map_recent_places_v1";
const MAX_HISTORY_ITEMS = 8;

/**
 * Persist recently inspected locations, districts, roads, or geopark spots locally.
 */
export function useMapHistory() {
  const recentItems = useState<RecentMapItem[]>("melarosa_map_recent_items", () => []);
  const hasLoaded = useState<boolean>("melarosa_map_recent_loaded", () => false);

  function ensureLoaded() {
    if (hasLoaded.value || !import.meta.client) return;
    hasLoaded.value = true;
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        recentItems.value = parsed
          .filter((item) => item && typeof item.id === "string" && typeof item.label === "string")
          .slice(0, MAX_HISTORY_ITEMS);
      }
    } catch {
      recentItems.value = [];
    }
  }

  function persist() {
    if (!import.meta.client) return;
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(recentItems.value));
    } catch {
      // Ignore storage quota or private-mode restrictions
    }
  }

  function recordRecent(item: Omit<RecentMapItem, "visitedAt">) {
    ensureLoaded();
    const nextItem: RecentMapItem = {
      ...item,
      visitedAt: Date.now(),
    };
    const filtered = recentItems.value.filter((existing) => existing.id !== item.id);
    recentItems.value = [nextItem, ...filtered].slice(0, MAX_HISTORY_ITEMS);
    persist();
  }

  function clearHistory() {
    recentItems.value = [];
    if (import.meta.client) {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {
        // Ignore storage errors
      }
    }
  }

  return {
    recentItems,
    ensureLoaded,
    recordRecent,
    clearHistory,
  };
}

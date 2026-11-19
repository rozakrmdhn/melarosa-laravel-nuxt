<script setup lang="ts">
import type { BasemapKey } from "~/components/maps/MapCanvas.vue";
import type { ActiveLayerItem, MainTab } from "~/types/map-layers";
import { KECAMATAN_ENTRIES, type KecamatanEntry } from "~/constants/kecamatan";
import { useGeoportalLayers, type GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import type { IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";
import type { RekapRuasItem } from "~/types/rekap-jalan";
import SearchListView from "./left-panel/SearchListView.vue";
import SearchItemAboutView from "./left-panel/SearchItemAboutView.vue";
import JelajahHomeView from "./left-panel/JelajahHomeView.vue";
import InfrastrukturExplorerView from "./left-panel/InfrastrukturExplorerView.vue";
import OtherLayersView from "./left-panel/OtherLayersView.vue";
import LayerDetailView from "./left-panel/LayerDetailView.vue";
import IdentifyView from "./left-panel/IdentifyView.vue";
import GeoparkListView from "./left-panel/GeoparkListView.vue";
import GeoparkDetailView from "./left-panel/GeoparkDetailView.vue";
import { useGeoparkSites, type GeoparkSite, type GeoparkCategoryFilter } from "~/composables/useGeoparkSites";
import { useMapHistory, type RecentMapItem } from "~/composables/useMapHistory";
import { normalizeWilayahKey } from "~/composables/useMapRekapOverview";
import {
  parseCoordinateString,
  formatDMS,
  getKecamatanFromCoordinate,
  type ParsedCoordinate,
} from "~/utils/gis-helpers";

const { setActivePillarFilter, sites: geoparkSites } = useGeoparkSites();
const { recordRecent } = useMapHistory();

const props = withDefaults(
  defineProps<{
    isMobileDrawer?: boolean;
    activeLayers?: ActiveLayerItem[];
    currentBasemap?: BasemapKey;
    activeTab?: "layers" | "infrastruktur" | "geoportal" | "dataset" | "geopark" | "geosite";
    selectedKecamatanFilter?: string | null;
    selectedDesaFilter?: string | null;
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    identifiedFeatures?: IdentifiedFeature[];
    isIdentifying?: boolean;
    inspectorOpen?: boolean;
    geoparkOverlayVisible?: boolean;
  }>(),
  {
    isMobileDrawer: false,
    activeLayers: () => [],
    currentBasemap: "esri",
    activeTab: "layers",
    selectedKecamatanFilter: null,
    selectedDesaFilter: null,
    clickedCoordinate: null,
    locationLabel: null,
    identifiedFeatures: () => [],
    isIdentifying: false,
    inspectorOpen: false,
    geoparkOverlayVisible: false,
  }
);

const emit = defineEmits<{
  (e: "close"): void;
  (e: "zoom-to-home"): void;
  (e: "select-kecamatan", payload: {
    name: string;
    coordinate: [number, number];
    zoom?: number;
    bbox?: [number, number, number, number];
    isKecamatan?: boolean;
  }): void;
  (e: "filter-wilayah", kecamatan: string | null, customBbox?: [number, number, number, number] | null, desa?: string | null): void;
  (e: "reorder-layers", layers: ActiveLayerItem[]): void;
  (e: "zoom-to-layer", layer: ActiveLayerItem): void;
  (e: "toggle-layer-visibility", layerId: string): void;
  (e: "update-layer-opacity", payload: { layerId: string; opacity: number }): void;
  (e: "update-layer-filter", payload: { layerId: string; kecamatan: string | null; status: string | null }): void;
  (e: "remove-layer", layerId: string): void;
  (e: "add-layer", layer: ActiveLayerItem): void;
  (e: "update:currentBasemap", val: BasemapKey): void;
  (e: "close-inspector"): void;
  (e: "clear-pin"): void;
  (e: "select-feature", feature: IdentifiedFeature): void;
  (e: "search-focus"): void;
  (e: "search-close"): void;
  (e: "tab-change", tab: MainTab): void;
  (e: "open-layer-detail", layerId: string): void;
  (e: "close-layer-detail"): void;
  (e: "open-geopark-detail", site: GeoparkSite): void;
  (e: "close-geopark-detail"): void;
  (e: "select-coordinate", payload: {
    coordinate: [number, number];
    formatted: string;
    zoom?: number;
  }): void;
  (e: "toggle-roads-visibility"): void;
  (e: "toggle-geopark-overlay"): void;
  (e: "locate-user"): void;
  (e: "select-road", payload: {
    ruas: RekapRuasItem;
    desaName: string;
    kecamatanName: string;
  }): void;
}>();

function normalizeIncomingTab(val?: string): MainTab {
  if (val === "geopark" || val === "geosite") return "geopark";
  if (val === "infrastruktur") return "infrastruktur";
  if (val === "geoportal" || val === "dataset") return "dataset";
  return "layers";
}

const localActiveTab = ref<MainTab>(normalizeIncomingTab(props.activeTab));
const pendingOtherLayers = ref(false);

watch(
  () => props.activeTab,
  (val) => {
    const target = normalizeIncomingTab(val);
    if (pendingOtherLayers.value && target === "layers") {
      pendingOtherLayers.value = false;
      localActiveTab.value = "layers";
      petaSubView.value = "other-layers";
      return;
    }
    pendingOtherLayers.value = false;
    if (props.inspectorOpen && target === "layers") {
      localActiveTab.value = "layers";
      petaSubView.value = "identify";
      return;
    }
    resetSubViews(target);
  }
);

const currentActiveTab = computed<MainTab>(() => {
  if (props.inspectorOpen) return "layers";
  if (props.isMobileDrawer) {
    return normalizeIncomingTab(props.activeTab);
  }
  return localActiveTab.value;
});

function getMainTabIndex(tab: MainTab): number {
  if (tab === "infrastruktur") return 1;
  if (tab === "geopark") return 2;
  return 0;
}

const activeDesktopTabIndex = computed(() => getMainTabIndex(currentActiveTab.value));
const tabTransitionName = ref<"tab-slide-forward" | "tab-slide-backward">("tab-slide-forward");

watch(
  currentActiveTab,
  (nextTab, prevTab) => {
    const nextIdx = getMainTabIndex(nextTab);
    const prevIdx = prevTab ? getMainTabIndex(prevTab) : 0;
    tabTransitionName.value = nextIdx >= prevIdx ? "tab-slide-forward" : "tab-slide-backward";
  }
);

export type PetaSubView = "home" | "other-layers" | "layer-detail" | "identify";
const petaSubView = ref<PetaSubView>("home");
const selectedLayerId = ref<string | null>(null);

const infraSortPriority = ref(false);
const infraInitialCategory = ref<"jalan" | "jembatan" | "kantor">("jalan");

export type GeoparkSubView = "list" | "detail";
const geoparkSubView = ref<GeoparkSubView>("list");
const selectedGeoparkSite = ref<GeoparkSite | null>(null);

const selectedLayer = computed(() =>
  props.activeLayers.find((l) => l.id === selectedLayerId.value) || null
);

export type SearchSubView = "list" | "detail";
const searchSubView = ref<SearchSubView>("list");

export type SearchItemType = "kecamatan" | "dataset" | "geopark" | "landmark" | "coordinate";
export interface SelectedSearchItem {
  type: SearchItemType;
  data: any;
}
const selectedSearchItem = ref<SelectedSearchItem | null>(null);

const universalSearchQuery = ref("");
const isSearchOpen = ref(false);
const searchWrapperRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

watch(
  () => props.selectedKecamatanFilter,
  (next, prev) => {
    if (!next && prev && isSearchOpen.value && selectedSearchItem.value?.type === "kecamatan") {
      handleCloseSearch();
    }
  }
);

watch(
  () => props.inspectorOpen,
  (open) => {
    if (open) {
      isSearchOpen.value = false;
      searchSubView.value = "list";
      selectedSearchItem.value = null;
      localActiveTab.value = "layers";
      petaSubView.value = "identify";
    } else if (petaSubView.value === "identify") {
      petaSubView.value = "home";
    }
  },
  { immediate: true }
);

const { allLayers, toActiveLayerItem } = useGeoportalLayers();

function handleGeoportalToggle(payload: { layer: GeoportalLayerItem; active: boolean; opacity?: number }) {
  if (payload.active) {
    const item = toActiveLayerItem(payload.layer, payload.opacity ?? 0.85);
    emit("add-layer", item);
  } else {
    emit("remove-layer", payload.layer.id);
  }
}

function handleKecamatanClick(k: KecamatanEntry) {
  recordRecent({
    id: `kec-${k.name.toLowerCase()}`,
    kind: "kecamatan",
    label: `Kec. ${k.name}`,
    sublabel: k.zona,
    kecamatanName: k.name,
    coordinate: k.coordinate,
    zoom: k.zoom,
    bbox: k.bbox,
  });

  const isCurrentlySelected = props.selectedKecamatanFilter?.trim().toLowerCase() === k.name.toLowerCase();
  if (isCurrentlySelected) {
    emit("filter-wilayah", null);
  } else {
    emit("filter-wilayah", k.name);
    emit("select-kecamatan", {
      name: `Kecamatan ${k.name}`,
      coordinate: k.coordinate,
      zoom: k.zoom,
      bbox: k.bbox,
      isKecamatan: true,
    });
  }
}

function handleResetWilayahFilter() {
  emit("filter-wilayah", null);
  emit("zoom-to-home");
}

export type { GeoparkSite };
export type { KecamatanEntry };
export type LandmarkEntry = GeoparkSite;

export interface DatasetResultItem {
  id: string;
  slug: string;
  title: string;
  subtitle: string;
  protocol: string;
  isActive: boolean;
  category?: string;
  raw: any;
  isGeoportal: boolean;
}

const filteredKecamatanResults = computed(() => {
  const q = universalSearchQuery.value.trim().toLowerCase();
  if (!q) {
    return KECAMATAN_ENTRIES.slice(0, 6);
  }
  return KECAMATAN_ENTRIES.filter((k) => {
    return (
      k.name.toLowerCase().includes(q) ||
      k.zona.toLowerCase().includes(q) ||
      k.desc.toLowerCase().includes(q)
    );
  });
});

function toFriendlySearchTitle(name: string, layerName?: string | null): string {
  const key = String(layerName || name || "").toLowerCase();
  if (key.includes("infrastruktur_segmen")) return "Kondisi Jalan Dibangun";
  if (key.includes("jalan_porosdesa")) return "Jaringan Jalan Desa";
  if (key.includes("bataswilayah_kabupaten")) return "Batas Wilayah Bojonegoro";
  return name;
}

const filteredDatasetResults = computed<DatasetResultItem[]>(() => {
  const q = universalSearchQuery.value.trim().toLowerCase();
  const combined: DatasetResultItem[] = [];
  const seenIds = new Set<string>();

  for (const al of props.activeLayers) {
    if (seenIds.has(al.id)) continue;
    seenIds.add(al.id);
    const title = toFriendlySearchTitle(al.title || al.name || "", al.layer_name);
    const instansi = al.instansi || al.attribution || al.agency || "Pemkab Bojonegoro";
    const protocol = al.protocol || "Peta";
    const desc = al.description || "";
    const category = al.category || "Peta Utama";
    const slug = al.layer_name
      ? String(al.layer_name).toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "")
      : (al.name || al.title || al.id).toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "");

    if (
      !q ||
      title.toLowerCase().includes(q) ||
      slug.toLowerCase().includes(q) ||
      instansi.toLowerCase().includes(q) ||
      desc.toLowerCase().includes(q) ||
      category.toLowerCase().includes(q)
    ) {
      combined.push({
        id: al.id,
        slug,
        title,
        subtitle: instansi,
        protocol,
        isActive: true,
        category,
        raw: al,
        isGeoportal: false,
      });
    }
  }

  const geoList = allLayers.value || [];
  for (const gl of geoList) {
    if (seenIds.has(gl.id)) continue;
    seenIds.add(gl.id);
    const title = gl.name || gl.layer_name || "";
    const instansi = gl.opd_name || gl.attribution || "Pemkab Bojonegoro";
    const protocol = gl.protocol || "Peta";
    const desc = gl.description || "";
    const category = gl.category || "Peta Pendukung";
    const slug = gl.layer_name
      ? String(gl.layer_name).toLowerCase().replace(/[^a-z0-9_:-]+/g, "-").replace(/^-+|-+$/g, "")
      : (gl.name || gl.id).toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "");

    if (
      !q ||
      title.toLowerCase().includes(q) ||
      slug.toLowerCase().includes(q) ||
      instansi.toLowerCase().includes(q) ||
      desc.toLowerCase().includes(q) ||
      category.toLowerCase().includes(q)
    ) {
      combined.push({
        id: gl.id,
        slug,
        title,
        subtitle: instansi,
        protocol,
        isActive: false,
        category,
        raw: gl,
        isGeoportal: true,
      });
    }
  }

  return !q ? combined.slice(0, 4) : combined.slice(0, 8);
});

const filteredGeoparkResults = computed(() => {
  const q = universalSearchQuery.value.trim().toLowerCase();
  if (!q) return geoparkSites.value.slice(0, 4);
  return geoparkSites.value.filter((site) => {
    return (
      site.title.toLowerCase().includes(q) ||
      site.name.toLowerCase().includes(q) ||
      (site.slug && site.slug.toLowerCase().includes(q)) ||
      site.code.toLowerCase().includes(q) ||
      site.tag.toLowerCase().includes(q) ||
      site.category.toLowerCase().includes(q) ||
      site.desc.toLowerCase().includes(q)
    );
  });
});

const parsedCoordinateResult = computed<ParsedCoordinate | null>(() => {
  const q = universalSearchQuery.value.trim();
  if (!q) return null;
  return parseCoordinateString(q);
});

const totalSearchResultsCount = computed(() => {
  return (
    (parsedCoordinateResult.value ? 1 : 0) +
    filteredKecamatanResults.value.length +
    filteredDatasetResults.value.length +
    filteredGeoparkResults.value.length
  );
});

const previousTabBeforeSearch = ref<MainTab>("layers");

const searchPlaceholder = computed(() => {
  if (currentActiveTab.value === "geopark") {
    return "Cari wisata geopark, budaya, atau kecamatannya...";
  }
  if (currentActiveTab.value === "infrastruktur") {
    return "Cari kecamatan, desa, atau ruas jalan...";
  }
  return "Cari kecamatan, jalan desa, atau wisata...";
});

function handleSearchFocus() {
  previousTabBeforeSearch.value = localActiveTab.value;
  isSearchOpen.value = true;
  searchSubView.value = "list";
  emit("search-focus");
  if (typeof window !== "undefined") {
    window.scrollTo(0, 0);
  }
}

function resetSubViews(targetTab?: MainTab) {
  if (isSearchOpen.value) {
    isSearchOpen.value = false;
    searchSubView.value = "list";
    selectedSearchItem.value = null;
    universalSearchQuery.value = "";
    if (searchInputRef.value) {
      searchInputRef.value.blur();
    }
    emit("search-close");
  }

  petaSubView.value = targetTab === "dataset" ? "other-layers" : "home";
  selectedLayerId.value = null;

  geoparkSubView.value = "list";
  selectedGeoparkSite.value = null;

  if (props.inspectorOpen) {
    emit("clear-pin");
  }

  if (targetTab) {
    localActiveTab.value = targetTab === "dataset" ? "layers" : targetTab;
  }
}

function handleTabClick(tab: MainTab) {
  if (tab === "infrastruktur") {
    infraSortPriority.value = false;
    infraInitialCategory.value = "jalan";
  }
  resetSubViews(tab);
  emit("tab-change", tab);
}

function handleGoToTabFromHome(
  tab: MainTab,
  opts?: { sortPriority?: boolean; category?: "jalan" | "jembatan" | "kantor" }
) {
  if (tab === "infrastruktur") {
    infraSortPriority.value = Boolean(opts?.sortPriority);
    infraInitialCategory.value = opts?.category || "jalan";
  }
  resetSubViews(tab);
  emit("tab-change", tab);
}

function handleCloseLayerDetail() {
  petaSubView.value = "home";
  selectedLayerId.value = null;
  emit("close-layer-detail");
}

function handleGoToMap() {
  resetSubViews("layers");
  emit("tab-change", "layers");
}

function handleGoToGeopark(category?: GeoparkCategoryFilter) {
  if (category) {
    setActivePillarFilter(category);
  }
  resetSubViews("geopark");
  emit("tab-change", "geopark");
}

function handleCloseSearch() {
  if (selectedSearchItem.value?.type === "geopark") {
    emit("close-geopark-detail");
  }
  isSearchOpen.value = false;
  searchSubView.value = "list";
  selectedSearchItem.value = null;
  universalSearchQuery.value = "";
  if (searchInputRef.value) {
    searchInputRef.value.blur();
  }
  localActiveTab.value = previousTabBeforeSearch.value || "layers";
  if (localActiveTab.value === "layers") {
    petaSubView.value = "home";
  }
  emit("search-close");
  if (typeof window !== "undefined") {
    window.scrollTo(0, 0);
  }
}

function handleBackToSearchList() {
  if (selectedSearchItem.value?.type === "geopark") {
    emit("close-geopark-detail");
  }
  searchSubView.value = "list";
  selectedSearchItem.value = null;
}

function handleSelectKecamatanFromSearch(k: KecamatanEntry) {
  handleKecamatanClick(k);
  selectedSearchItem.value = { type: "kecamatan", data: k };
  searchSubView.value = "detail";
}

function handleSelectDatasetFromSearch(item: DatasetResultItem) {
  if (item.isActive) {
    emit("zoom-to-layer", item.raw);
  }
  selectedSearchItem.value = { type: "dataset", data: item };
  searchSubView.value = "detail";
}

function handleSelectGeoparkFromSearch(site: GeoparkSite) {
  recordRecent({
    id: `gp-${site.id}`,
    kind: "wisata",
    label: site.name,
    sublabel: site.category,
    coordinate: site.coordinate,
    zoom: site.zoom,
    siteSlug: site.slug,
  });

  if (site.hasCoordinate) {
    emit("select-kecamatan", {
      name: site.name,
      coordinate: site.coordinate,
      zoom: site.zoom,
    });
  }
  selectedSearchItem.value = { type: "geopark", data: site };
  searchSubView.value = "detail";
}

function handleSelectCoordinateFromSearch(coord: ParsedCoordinate) {
  const nearbyKec = getKecamatanFromCoordinate(coord.lat, coord.lng);
  const dmsStr = formatDMS(coord.lat, coord.lng);

  emit("select-coordinate", {
    coordinate: [coord.lng, coord.lat],
    formatted: coord.formatted,
    zoom: 16,
  });

  selectedSearchItem.value = {
    type: "coordinate",
    data: {
      lat: coord.lat,
      lng: coord.lng,
      formatted: coord.formatted,
      dms: dmsStr,
      kecamatan: nearbyKec,
    },
  };
  searchSubView.value = "detail";
}

function handleZoomCoordinateInDetail(coord: any) {
  emit("select-coordinate", {
    coordinate: [coord.lng, coord.lat],
    formatted: coord.formatted,
    zoom: 16,
  });
}

function handleSearchSubmit() {
  if (parsedCoordinateResult.value) {
    handleSelectCoordinateFromSearch(parsedCoordinateResult.value);
    return;
  }
  if (filteredKecamatanResults.value.length > 0) {
    handleSelectKecamatanFromSearch(filteredKecamatanResults.value[0]);
    return;
  }
  if (filteredDatasetResults.value.length > 0) {
    handleSelectDatasetFromSearch(filteredDatasetResults.value[0]);
    return;
  }
  if (filteredGeoparkResults.value.length > 0) {
    handleSelectGeoparkFromSearch(filteredGeoparkResults.value[0]);
    return;
  }
}

function handleOpenGeoparkTabFromAbout(site: GeoparkSite) {
  isSearchOpen.value = false;
  searchSubView.value = "list";
  selectedSearchItem.value = null;
  universalSearchQuery.value = "";
  if (searchInputRef.value) {
    searchInputRef.value.blur();
  }
  localActiveTab.value = "geopark";
  handleOpenGeoparkDetail(site);
  emit("open-geopark-detail", site);
}

function handleOpenGeoparkDetail(site: GeoparkSite) {
  recordRecent({
    id: `gp-${site.id}`,
    kind: "wisata",
    label: site.name,
    sublabel: site.category,
    coordinate: site.coordinate,
    zoom: site.zoom,
    siteSlug: site.slug,
  });

  selectedGeoparkSite.value = site;
  geoparkSubView.value = "detail";
  if (site.hasCoordinate) {
    emit("select-kecamatan", {
      name: site.name,
      coordinate: site.coordinate,
      zoom: site.zoom,
    });
  }
  emit("open-geopark-detail", site);
}

function handleCloseGeoparkDetail() {
  geoparkSubView.value = "list";
  selectedGeoparkSite.value = null;
  emit("close-geopark-detail");
}

function handleSelectGeositeFromList(payload: { name: string; coordinate: [number, number]; zoom: number; site?: GeoparkSite }) {
  emit("select-kecamatan", payload);
  if (payload.site) {
    handleOpenGeoparkDetail(payload.site);
  }
}

function handleZoomGeoparkSite(site: GeoparkSite) {
  if (site.hasCoordinate) {
    emit("select-kecamatan", {
      name: site.name,
      coordinate: site.coordinate,
      zoom: site.zoom,
    });
  }
}

function handleToggleDatasetInDetail(item: DatasetResultItem) {
  const isCurrentlyActive = props.activeLayers.some((l) => l.id === item.id);
  if (isCurrentlyActive) {
    emit("remove-layer", item.id);
  } else {
    handleGeoportalToggle({ layer: item.raw, active: true });
  }
}

function handleZoomToDatasetInDetail(item: DatasetResultItem) {
  const activeL = props.activeLayers.find((l) => l.id === item.id);
  if (activeL) {
    emit("zoom-to-layer", activeL);
  } else {
    handleGeoportalToggle({ layer: item.raw, active: true });
  }
}

function handleZoomToKecamatanInDetail(k: KecamatanEntry) {
  handleKecamatanClick(k);
}

function handleZoomToGeoparkInDetail(site: GeoparkSite) {
  if (site.hasCoordinate) {
    emit("select-kecamatan", {
      name: site.name,
      coordinate: site.coordinate,
      zoom: site.zoom,
    });
  }
}

function handleCloseInspector() {
  emit("close-inspector");
  petaSubView.value = "home";
}

function handleSelectRoadFromExplorer(payload: {
  ruas: RekapRuasItem;
  desaName: string;
  kecamatanName: string;
}) {
  recordRecent({
    id: `ruas-${payload.ruas.id}`,
    kind: "ruas",
    label: payload.ruas.nama_ruas,
    sublabel: `${payload.desaName}, Kec. ${payload.kecamatanName}`,
    kecamatanName: payload.kecamatanName,
    desaName: payload.desaName,
    ruasId: payload.ruas.id,
    kodeRuas: payload.ruas.kode_ruas,
    ruasSnapshot: { ...payload.ruas },
  });
  emit("select-road", payload);
}

function handleSelectKecamatanFromExplorer(payload: {
  name: string;
  coordinate: [number, number];
  zoom?: number;
  bbox?: [number, number, number, number];
  isKecamatan?: boolean;
}) {
  const cleanName = payload.name.replace(/^Kecamatan\s+/i, "").trim();
  recordRecent({
    id: `kec-${cleanName.toLowerCase()}`,
    kind: "kecamatan",
    label: `Kec. ${cleanName}`,
    sublabel: "Wilayah Kecamatan",
    kecamatanName: cleanName,
    coordinate: payload.coordinate,
    zoom: payload.zoom,
    bbox: payload.bbox,
  });
  emit("select-kecamatan", payload);
}

function handleOpenRecentChip(item: RecentMapItem) {
  if (item.kind === "ruas" && (item.ruasId !== undefined || item.ruasSnapshot)) {
    const snap = (item.ruasSnapshot || {}) as Partial<RekapRuasItem>;
    const ruasPayload: RekapRuasItem = {
      id: item.ruasId ?? snap.id ?? item.id.replace(/^ruas-/, ""),
      kode_ruas: item.kodeRuas ?? snap.kode_ruas,
      nama_ruas: snap.nama_ruas || item.label,
      panjang_master: Number(snap.panjang_master ?? snap.panjang ?? 0),
      panjang_dibangun: Number(snap.panjang_dibangun ?? 0),
      panjang_tersisa: Number(snap.panjang_tersisa ?? 0),
      progress: Number(snap.progress ?? 0),
      jumlah_segmen: Number(snap.jumlah_segmen ?? 0),
      ...snap,
    };
    emit("select-road", {
      ruas: ruasPayload,
      desaName: item.desaName || "",
      kecamatanName: item.kecamatanName || "",
    });
    return;
  }

  if (item.kind === "wisata") {
    const matchedSite = geoparkSites.value.find(
      (s) =>
        `gp-${s.id}` === item.id ||
        (item.siteSlug && s.slug === item.siteSlug) ||
        normalizeWilayahKey(s.name) === normalizeWilayahKey(item.label)
    );
    if (matchedSite) {
      handleGoToGeopark();
      handleOpenGeoparkDetail(matchedSite);
      return;
    }
  }

  if (item.kecamatanName) {
    const matchedEntry = KECAMATAN_ENTRIES.find(
      (k) => normalizeWilayahKey(k.name) === normalizeWilayahKey(item.kecamatanName)
    );
    if (matchedEntry) {
      emit("filter-wilayah", matchedEntry.name);
      emit("select-kecamatan", {
        name: `Kecamatan ${matchedEntry.name}`,
        coordinate: matchedEntry.coordinate,
        zoom: matchedEntry.zoom,
        bbox: matchedEntry.bbox,
        isKecamatan: true,
      });
      return;
    }
  }

  if (item.coordinate) {
    emit("select-kecamatan", {
      name: item.label,
      coordinate: item.coordinate,
      zoom: item.zoom || 14,
      bbox: item.bbox,
    });
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === "Escape") {
    if (isSearchOpen.value) {
      if (searchSubView.value === "detail") {
        handleBackToSearchList();
      } else {
        handleCloseSearch();
      }
    }
  }
}

onMounted(() => {
  if (import.meta.client) {
    document.addEventListener("keydown", handleKeydown);
  }
});

onUnmounted(() => {
  if (import.meta.client) {
    document.removeEventListener("keydown", handleKeydown);
  }
});

defineExpose({
  resetSubViews,
  handleCloseSearch,
  openOtherLayers: () => {
    const needsTabSwitch = normalizeIncomingTab(props.activeTab) !== "layers";
    resetSubViews("layers");
    petaSubView.value = "other-layers";
    if (needsTabSwitch) {
      pendingOtherLayers.value = true;
      emit("tab-change", "layers");
    }
  },
  isOtherLayersOpen: () => {
    return (
      (normalizeIncomingTab(props.activeTab) === "layers" && petaSubView.value === "other-layers") ||
      currentActiveTab.value === "dataset"
    );
  },
  closeOtherLayers: () => {
    pendingOtherLayers.value = false;
    petaSubView.value = "home";
    selectedLayerId.value = null;
    if (normalizeIncomingTab(props.activeTab) !== "layers" || currentActiveTab.value === "dataset") {
      localActiveTab.value = "layers";
      emit("tab-change", "layers");
    }
  },
  openGeoparkDetail: (site: GeoparkSite) => {
    selectedGeoparkSite.value = site;
    geoparkSubView.value = "detail";
  },
  closeGeoparkDetail: () => {
    geoparkSubView.value = "list";
    selectedGeoparkSite.value = null;
  },
  openLayerDetail: (id: string) => {
    selectedLayerId.value = id;
    petaSubView.value = "layer-detail";
  },
  closeLayerDetail: () => {
    selectedLayerId.value = null;
    if (petaSubView.value === "layer-detail") {
      petaSubView.value = props.inspectorOpen ? "identify" : "home";
    }
  },
});
</script>

<template>
  <div
    :class="[
      'flex flex-col select-none transition-all duration-300 ease-out',
      isMobileDrawer ? 'w-full h-full bg-transparent' : 'w-full h-full bg-white dark:bg-[#09111e]'
    ]"
  >
    <!-- Header Desktop (Judul + Avatar Ionicons) -->
    <div
      v-if="!isMobileDrawer"
      class="px-4 sm:px-5 pt-5 pb-2.5 shrink-0 flex items-center justify-between gap-2.5"
    >
      <div class="flex items-center gap-2 min-w-0">
        <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100 truncate">
          Peta Bojonegoro
        </h1>
      </div>

      <div class="flex items-center gap-1.5 shrink-0">
        <button
          type="button"
          class="size-11 rounded-full bg-blue-100/90 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 border border-blue-200/80 dark:border-blue-800/60 flex items-center justify-center transition-transform hover:scale-105 cursor-pointer active:scale-95"
          title="Pusatkan peta Bojonegoro"
          aria-label="Pusatkan peta Bojonegoro"
          @click="handleResetWilayahFilter(); handleTabClick('layers')"
        >
          <UIcon name="i-ion-map" class="size-5" />
        </button>
      </div>
    </div>

    <!-- Top Segmented Pill Tabs: Jelajah | Infrastruktur | Wisata -->
    <div
      v-if="!isMobileDrawer"
      class="px-4 sm:px-5 pt-1 pb-2 shrink-0"
    >
      <div
        role="tablist"
        aria-label="Navigasi Peta Utama"
        class="relative grid grid-cols-3 p-1 bg-slate-100 dark:bg-white/[0.06] rounded-full tracking-tight overflow-hidden"
      >
        <span
          aria-hidden="true"
          class="pointer-events-none absolute top-1 bottom-1 left-1 w-[calc((100%-8px)/3)] rounded-full bg-white dark:bg-[#0f172a] shadow-xs transition-transform duration-300 [transition-timing-function:cubic-bezier(0.22,1,0.36,1)] will-change-transform motion-reduce:transition-none"
          :style="{ transform: `translate3d(${activeDesktopTabIndex * 100}%, 0, 0)` }"
        />

        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'layers' || currentActiveTab === 'dataset'"
          class="relative z-10 rounded-full px-2.5 py-1.5 min-h-[36px] inline-flex items-center justify-center text-[13px] tracking-tight transition-colors duration-200 cursor-pointer select-none text-center whitespace-nowrap truncate focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-[0.98]"
          :class="currentActiveTab === 'layers' || currentActiveTab === 'dataset'
            ? 'text-slate-900 dark:text-white font-bold'
            : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold'"
          @click="handleTabClick('layers')"
        >
          Jelajah
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'infrastruktur'"
          class="relative z-10 rounded-full px-2.5 py-1.5 min-h-[36px] inline-flex items-center justify-center text-[13px] tracking-tight transition-colors duration-200 cursor-pointer select-none text-center whitespace-nowrap truncate focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-[0.98]"
          :class="currentActiveTab === 'infrastruktur'
            ? 'text-slate-900 dark:text-white font-bold'
            : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold'"
          @click="handleTabClick('infrastruktur')"
        >
          Infrastruktur
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'geopark'"
          class="relative z-10 rounded-full px-2.5 py-1.5 min-h-[36px] inline-flex items-center justify-center text-[13px] tracking-tight transition-colors duration-200 cursor-pointer select-none text-center whitespace-nowrap truncate focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-[0.98]"
          :class="currentActiveTab === 'geopark'
            ? 'text-slate-900 dark:text-white font-bold'
            : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold'"
          @click="handleTabClick('geopark')"
        >
          Wisata
        </button>
      </div>
    </div>

    <!-- Wrapper Pencarian & Konten Sub Halaman -->
    <div
      ref="searchWrapperRef"
      class="flex-1 flex flex-col min-h-0"
    >
      <!-- Pill Search Input (dengan tombol Kembali ke Jelajah di kiri saat mode pencarian aktif) -->
      <div
        v-if="!(isSearchOpen && searchSubView === 'detail') && petaSubView !== 'layer-detail' && petaSubView !== 'identify' && petaSubView !== 'other-layers' && geoparkSubView !== 'detail'"
        class="px-4 sm:px-5 pt-1 pb-2.5 shrink-0"
      >
        <div class="flex items-center gap-2">
          <button
            v-if="isSearchOpen"
            type="button"
            class="size-11 min-w-[44px] min-h-[44px] rounded-full border border-slate-200/80 dark:border-white/10 bg-slate-100/95 dark:bg-white/[0.06] hover:bg-slate-200/80 dark:hover:bg-white/10 flex items-center justify-center text-slate-700 dark:text-slate-200 transition-all cursor-pointer shrink-0 active:scale-95"
            aria-label="Kembali ke Jelajah"
            title="Kembali ke Jelajah"
            @click.stop="handleCloseSearch"
          >
            <UIcon name="i-ion-arrow-back" class="size-4.5" />
          </button>

          <div class="relative flex-1 min-w-0">
            <UIcon
              name="i-ion-search-outline"
              class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 pointer-events-none transition-colors"
              :class="isSearchOpen ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500'"
            />
            <input
              ref="searchInputRef"
              v-model="universalSearchQuery"
              type="text"
              :placeholder="searchPlaceholder"
              class="w-full bg-slate-100/95 dark:bg-white/[0.06] border border-transparent rounded-full pl-10 pr-10 py-2 text-base sm:text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:bg-white dark:focus:bg-[#0f172a] focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all min-h-[44px]"
              @focus="handleSearchFocus"
              @click="handleSearchFocus"
              @input="handleSearchFocus"
              @keydown.enter="handleSearchSubmit"
            />
            <button
              v-if="universalSearchQuery"
              type="button"
              class="absolute right-1.5 top-1/2 -translate-y-1/2 size-8 rounded-full after:absolute after:-inset-1.5 after:content-[''] bg-slate-200/90 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 flex items-center justify-center transition-all cursor-pointer active:scale-95"
              aria-label="Hapus kata kunci pencarian"
              title="Hapus kata kunci"
              @click.stop="universalSearchQuery = ''; searchInputRef?.focus()"
            >
              <UIcon name="i-ion-close" class="size-4" />
            </button>
          </div>
        </div>

        <!-- Active Wilayah Filter Chip di bawah form pencarian -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <div
            v-if="!isSearchOpen && (props.selectedKecamatanFilter || props.selectedDesaFilter)"
            class="mt-2 flex items-center justify-between gap-2 px-3 py-1.5 rounded-full bg-sky-50/90 dark:bg-sky-950/35 border border-sky-200/80 dark:border-sky-800/50"
          >
            <div class="flex items-center gap-2 min-w-0">
              <span class="relative flex size-2 shrink-0">
                <span class="animate-ping motion-reduce:animate-none absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75" />
                <span class="relative inline-flex rounded-full size-2 bg-sky-600 dark:bg-sky-400" />
              </span>
              <span class="text-[11px] font-semibold text-slate-800 dark:text-slate-100 truncate">
                Filter:
                {{
                  [
                    props.selectedDesaFilter ? `Desa ${props.selectedDesaFilter}` : null,
                    props.selectedKecamatanFilter ? `Kec. ${props.selectedKecamatanFilter}` : null,
                  ]
                    .filter(Boolean)
                    .join(", ")
                }}
              </span>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/90 dark:bg-slate-900/80 hover:bg-sky-600 hover:text-white dark:hover:bg-sky-600 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 text-[11px] font-bold transition-all active:scale-95 shrink-0 cursor-pointer"
              title="Reset filter wilayah"
              @click="emit('filter-wilayah', null, undefined, null)"
            >
              <UIcon name="i-lucide-x" class="size-3" />
              <span>Reset</span>
            </button>
          </div>
        </Transition>
      </div>

      <!-- Body Content Area -->
      <div class="min-h-0 flex-1 flex flex-col relative overflow-hidden">
        <Transition :name="tabTransitionName" mode="out-in">
        <!-- 1. Pencarian: Daftar Hasil -->
        <SearchListView
          v-if="isSearchOpen && searchSubView === 'list'"
          key="search-recommendations-list"
          :filtered-kecamatan-results="filteredKecamatanResults"
          :filtered-dataset-results="filteredDatasetResults"
          :filtered-geopark-results="filteredGeoparkResults"
          :total-search-results-count="totalSearchResultsCount"
          :universal-search-query="universalSearchQuery"
          :parsed-coordinate-result="parsedCoordinateResult"
          @select-kecamatan="handleSelectKecamatanFromSearch"
          @select-dataset="handleSelectDatasetFromSearch"
          @select-geopark="handleSelectGeoparkFromSearch"
          @select-coordinate="handleSelectCoordinateFromSearch"
          @close-search="handleCloseSearch"
        />

        <!-- 2. Pencarian: Detail Item -->
        <SearchItemAboutView
          v-else-if="isSearchOpen && searchSubView === 'detail' && selectedSearchItem"
          key="search-item-detail"
          :selected-item="selectedSearchItem"
          :selected-kecamatan-filter="props.selectedKecamatanFilter"
          :active-layers="props.activeLayers"
          @close="handleBackToSearchList"
          @back-to-jelajah="handleCloseSearch"
          @zoom-kecamatan="handleZoomToKecamatanInDetail"
          @zoom-dataset="handleZoomToDatasetInDetail"
          @toggle-dataset="handleToggleDatasetInDetail"
          @zoom-geopark="handleZoomToGeoparkInDetail"
          @zoom-coordinate="handleZoomCoordinateInDetail"
          @open-geopark-tab="handleOpenGeoparkTabFromAbout"
        />

        <!-- 3. Tab Jelajah (Beranda Interaktif + Klik Peta + Peta Lainnya) -->
        <div
          v-else-if="currentActiveTab === 'layers' || currentActiveTab === 'dataset'"
          key="tab-jelajah"
          class="flex-1 flex flex-col min-h-0"
        >
          <IdentifyView
            v-if="petaSubView === 'identify'"
            key="sv-identify"
            :clicked-coordinate="props.clickedCoordinate"
            :location-label="props.locationLabel"
            :identified-features="props.identifiedFeatures"
            :is-identifying="props.isIdentifying"
            @close="handleCloseInspector"
            @select-feature="(f) => emit('select-feature', f)"
          />

          <OtherLayersView
            v-else-if="petaSubView === 'other-layers' || currentActiveTab === 'dataset'"
            key="sv-other-layers"
            :active-layers="props.activeLayers"
            @back="handleGoToMap"
            @toggle-layer-visibility="(id) => emit('toggle-layer-visibility', id)"
            @update-layer-opacity="(payload) => emit('update-layer-opacity', payload)"
            @toggle-geoportal="(payload) => handleGeoportalToggle({ layer: payload.layer, active: payload.active })"
            @reorder-layers="(layers) => emit('reorder-layers', layers)"
          />

          <LayerDetailView
            v-else-if="petaSubView === 'layer-detail'"
            key="sv-detail"
            :selected-layer="selectedLayer"
            :selected-kecamatan-filter="props.selectedKecamatanFilter"
            :kecamatan-entries="KECAMATAN_ENTRIES"
            @close="handleCloseLayerDetail"
            @back="handleCloseLayerDetail"
            @toggle-visibility="(id) => emit('toggle-layer-visibility', id)"
            @update-opacity="(payload) => emit('update-layer-opacity', payload)"
            @update-filter="(payload) => emit('update-layer-filter', payload)"
            @zoom-to-layer="(layer) => emit('zoom-to-layer', layer)"
            @remove-layer="(id) => { emit('remove-layer', id); handleCloseLayerDetail(); }"
          />

          <JelajahHomeView
            v-else
            key="sv-home"
            :active-layers="props.activeLayers"
            :selected-kecamatan-filter="props.selectedKecamatanFilter"
            :selected-desa-filter="props.selectedDesaFilter"
            :geopark-overlay-visible="props.geoparkOverlayVisible"
            @toggle-roads-visibility="emit('toggle-roads-visibility')"
            @toggle-geopark-overlay="emit('toggle-geopark-overlay')"
            @locate-user="emit('locate-user')"
            @zoom-to-home="emit('zoom-to-home')"
            @go-to-tab="handleGoToTabFromHome"
            @open-other-layers="petaSubView = 'other-layers'"
            @select-kecamatan="handleSelectKecamatanFromExplorer"
            @filter-wilayah="(kec, bbox, desa) => emit('filter-wilayah', kec, bbox, desa)"
            @select-geopark="handleOpenGeoparkDetail"
            @open-recent="handleOpenRecentChip"
          />
        </div>

        <!-- 4. Tab Infrastruktur (Telusur Jalan Desa, Jembatan Rencana, Kantor Desa Rencana) -->
        <div
          v-else-if="currentActiveTab === 'infrastruktur'"
          key="tab-infrastruktur"
          class="flex-1 flex flex-col min-h-0"
        >
          <InfrastrukturExplorerView
            :selected-kecamatan-filter="props.selectedKecamatanFilter"
            :selected-desa-filter="props.selectedDesaFilter"
            :initial-sort-priority="infraSortPriority"
            :initial-category="infraInitialCategory"
            @select-kecamatan="handleSelectKecamatanFromExplorer"
            @filter-wilayah="(kec, bbox, desa) => emit('filter-wilayah', kec, bbox, desa)"
            @select-road="handleSelectRoadFromExplorer"
          />
        </div>

        <!-- 5. Tab Wisata (Geopark Bojonegoro) -->
        <div
          v-else-if="currentActiveTab === 'geopark'"
          key="tab-geopark"
          class="flex-1 flex flex-col min-h-0"
        >
          <GeoparkListView
            v-if="geoparkSubView === 'list'"
            key="gp-list"
            @select-geosite="handleSelectGeositeFromList"
            @view-site-detail="handleOpenGeoparkDetail"
            @go-to-map="handleGoToMap"
          />

          <GeoparkDetailView
            v-else-if="geoparkSubView === 'detail' && selectedGeoparkSite"
            key="gp-detail"
            :site="selectedGeoparkSite"
            @close="handleCloseGeoparkDetail"
            @back="handleCloseGeoparkDetail"
            @zoom="handleZoomGeoparkSite"
          />
        </div>
        </Transition>
      </div>
    </div>

    <!-- Minimal Footer Attribution (Desktop) -->
    <div
      v-if="!isMobileDrawer"
      class="border-t border-slate-100 dark:border-white/8 bg-slate-50/70 dark:bg-[#070c16]/80 px-4 sm:px-5 py-2.5 shrink-0 text-[11px] text-slate-500 dark:text-slate-400 select-text"
    >
      <p class="truncate">
        © {{ new Date().getFullYear() }} MELAROSA · Pemkab Bojonegoro ·
        <a
          href="https://www.openstreetmap.org/copyright"
          target="_blank"
          rel="noopener noreferrer"
          class="underline hover:text-slate-700 dark:hover:text-slate-200"
        >© OpenStreetMap</a>
      </p>
    </div>
  </div>
</template>

<style scoped>
.tab-slide-forward-enter-active,
.tab-slide-forward-leave-active,
.tab-slide-backward-enter-active,
.tab-slide-backward-leave-active {
  transition:
    opacity 190ms cubic-bezier(0.22, 1, 0.36, 1),
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
  will-change: opacity, transform;
}

.tab-slide-forward-enter-from {
  opacity: 0;
  transform: translate3d(18px, 0, 0);
}
.tab-slide-forward-leave-to {
  opacity: 0;
  transform: translate3d(-14px, 0, 0);
}

.tab-slide-backward-enter-from {
  opacity: 0;
  transform: translate3d(-18px, 0, 0);
}
.tab-slide-backward-leave-to {
  opacity: 0;
  transform: translate3d(14px, 0, 0);
}

@media (prefers-reduced-motion: reduce) {
  .tab-slide-forward-enter-active,
  .tab-slide-forward-leave-active,
  .tab-slide-backward-enter-active,
  .tab-slide-backward-leave-active {
    transition: none;
  }
}

::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.35);
  border-radius: 9999px;
}
::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.55);
}
</style>

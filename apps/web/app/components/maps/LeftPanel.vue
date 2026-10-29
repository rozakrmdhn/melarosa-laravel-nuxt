<script setup lang="ts">
import type { BasemapKey } from "~/components/maps/MapCanvas.vue";
import type { ActiveLayerItem, MainTab } from "~/types/map-layers";
import { KECAMATAN_ENTRIES, type KecamatanEntry } from "~/constants/kecamatan";
import GeoportalLayerSection from "./GeoportalLayerSection.vue";
import { useGeoportalLayers, type GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import type { IdentifiedFeature } from "~/components/maps/IdentifyFeatures.vue";
import SearchListView from "./left-panel/SearchListView.vue";
import SearchItemAboutView from "./left-panel/SearchItemAboutView.vue";
import ActiveLayersView from "./left-panel/ActiveLayersView.vue";
import LayerDetailView from "./left-panel/LayerDetailView.vue";
import IdentifyView from "./left-panel/IdentifyView.vue";
import GeoparkListView from "./left-panel/GeoparkListView.vue";
import GeoparkDetailView from "./left-panel/GeoparkDetailView.vue";
import { useGeoparkSites, type GeoparkSite, type GeoparkCategoryFilter } from "~/composables/useGeoparkSites";
import {
  parseCoordinateString,
  formatDMS,
  getKecamatanFromCoordinate,
  type ParsedCoordinate,
} from "~/utils/gis-helpers";

const { setActivePillarFilter } = useGeoparkSites();

const props = withDefaults(
  defineProps<{
    isMobileDrawer?: boolean;
    activeLayers?: ActiveLayerItem[];
    currentBasemap?: BasemapKey;
    activeTab?: "layers" | "geoportal" | "dataset" | "geopark" | "geosite";
    selectedKecamatanFilter?: string | null;
    clickedCoordinate?: [number, number] | null;
    locationLabel?: string | null;
    identifiedFeatures?: IdentifiedFeature[];
    isIdentifying?: boolean;
    inspectorOpen?: boolean;
  }>(),
  {
    isMobileDrawer: false,
    activeLayers: () => [],
    currentBasemap: "esri",
    activeTab: "layers",
    selectedKecamatanFilter: null,
    clickedCoordinate: null,
    locationLabel: null,
    identifiedFeatures: () => [],
    isIdentifying: false,
    inspectorOpen: false,
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
  (e: "filter-wilayah", kecamatan: string | null): void;
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
  (e: "share"): void;
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
}>();

const localActiveTab = ref<MainTab>(
  props.activeTab === "geopark" || props.activeTab === "geosite"
    ? "geopark"
    : props.activeTab === "geoportal" || props.activeTab === "dataset"
      ? "dataset"
      : "layers"
);

watch(
  () => props.activeTab,
  (val) => {
    const target: MainTab =
      val === "geopark" || val === "geosite"
        ? "geopark"
        : val === "geoportal" || val === "dataset"
          ? "dataset"
          : "layers";
    resetSubViews(target);
  }
);

const currentActiveTab = computed<MainTab>(() => {
  if (props.inspectorOpen) return "layers";
  if (props.isMobileDrawer) {
    if (props.activeTab === "geopark" || props.activeTab === "geosite") return "geopark";
    if (props.activeTab === "geoportal" || props.activeTab === "dataset") return "dataset";
    return "layers";
  }
  return localActiveTab.value;
});

export type PetaSubView = "layers" | "layer-detail" | "identify";
const petaSubView = ref<PetaSubView>("layers");
const selectedLayerId = ref<string | null>(null);

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
  () => props.inspectorOpen,
  (open) => {
    if (open) {
      isSearchOpen.value = false;
      searchSubView.value = "list";
      selectedSearchItem.value = null;
      localActiveTab.value = "layers";
      petaSubView.value = "identify";
    } else if (petaSubView.value === "identify") {
      petaSubView.value = "layers";
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

function handleGeoportalOpacity(payload: { layerId: string; opacity: number }) {
  emit("update-layer-opacity", payload);
}



function handleKecamatanClick(k: KecamatanEntry) {
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

const { sites: geoparkSites } = useGeoparkSites();

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

const filteredDatasetResults = computed<DatasetResultItem[]>(() => {
  const q = universalSearchQuery.value.trim().toLowerCase();
  const combined: DatasetResultItem[] = [];
  const seenIds = new Set<string>();

  for (const al of props.activeLayers) {
    if (seenIds.has(al.id)) continue;
    seenIds.add(al.id);
    const title = al.title || al.name || "";
    const instansi = al.instansi || al.attribution || al.agency || "Dinas Pemkab Bojonegoro";
    const protocol = al.protocol || "MVT / WMS";
    const desc = al.description || "";
    const category = al.category || "";
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
        subtitle: `${instansi} · ${protocol}`,
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
    const instansi = gl.opd_name || gl.attribution || "Geoportal JIGN Bojonegoro";
    const protocol = gl.protocol || "WMS";
    const desc = gl.description || "";
    const category = gl.category || "";
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
        subtitle: `${instansi} · ${protocol}`,
        protocol,
        isActive: false,
        category,
        raw: gl,
        isGeoportal: true,
      });
    }
  }

  if (!q) {
    return combined.slice(0, 5);
  }
  return combined.slice(0, 8);
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

const detailSubPageTitle = computed(() => {
  if (!selectedSearchItem.value) return "Tentang";
  switch (selectedSearchItem.value.type) {
    case "kecamatan":
      return "Tentang Wilayah";
    case "dataset":
      return "Tentang Dataset";
    case "geopark":
    case "landmark":
      return "Tentang Geosite";
    case "coordinate":
      return "Tentang Titik Koordinat";
    default:
      return "Tentang";
  }
});

const previousTabBeforeSearch = ref<MainTab>("layers");

const searchPlaceholder = computed(() => {
  if (currentActiveTab.value === "geopark") {
    return "Cari nama geosite, fosil, api abadi, koordinat...";
  }
  if (currentActiveTab.value === "dataset") {
    return "Cari dataset tematik, OPD, layer, koordinat...";
  }
  return "Cari wilayah, koordinat desimal/derajat, layer...";
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

  petaSubView.value = "layers";
  selectedLayerId.value = null;

  geoparkSubView.value = "list";
  selectedGeoparkSite.value = null;

  if (props.inspectorOpen) {
    emit("close-inspector");
    emit("clear-pin");
  }

  if (targetTab) {
    localActiveTab.value = targetTab;
  }
}

function handleTabClick(tab: MainTab) {
  resetSubViews(tab);
  emit("tab-change", tab);
}

function handleSelectLayer(id: string) {
  selectedLayerId.value = id;
  petaSubView.value = "layer-detail";
  emit("open-layer-detail", id);
}

function handleCloseLayerDetail() {
  petaSubView.value = "layers";
  selectedLayerId.value = null;
  emit("close-layer-detail");
}

function handleGoToMap() {
  resetSubViews("layers");
  emit("tab-change", "layers");
}

function handleGoToDataset() {
  resetSubViews("dataset");
  emit("tab-change", "dataset");
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
    petaSubView.value = "layers";
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
  if (site.hasCoordinate) {
    emit("select-kecamatan", {
      name: site.name,
      coordinate: site.coordinate,
      zoom: site.zoom,
    });
  }
  selectedSearchItem.value = { type: "geopark", data: site };
  searchSubView.value = "detail";
  emit("open-geopark-detail", site);
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
}

function handleOpenGeoparkDetail(site: GeoparkSite) {
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

function handleClearSearch() {
  handleCloseSearch();
}

function handleCloseInspector() {
  emit("close-inspector");
  petaSubView.value = "layers";
}

function handleClearPin() {
  emit("clear-pin");
  emit("close-inspector");
  petaSubView.value = "layers";
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

const filteredActiveLayers = computed(() => {
  const q = universalSearchQuery.value.trim().toLowerCase();
  if (!q) return props.activeLayers;
  return props.activeLayers.filter((l) => {
    const title = (l.title || l.name || "").toLowerCase();
    const instansi = (l.instansi || l.attribution || l.agency || l.protocol || "").toLowerCase();
    return title.includes(q) || instansi.includes(q);
  });
});

defineExpose({
  resetSubViews,
  handleCloseSearch,
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
    petaSubView.value = "layers";
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
    <!-- Header: Peta Interaktif & Tombol Bagikan Peta Desktop -->
    <div
      v-if="!isMobileDrawer"
      class="px-4 sm:px-5 pt-6 sm:pt-6 pb-2 shrink-0 flex items-center justify-between gap-3"
    >
      <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">
        Peta Interaktif
      </h1>
      <!-- Tombol Aksi Tunggal Bagikan Peta Desktop -->
      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-200 dark:hover:border-blue-800 transition-all cursor-pointer shadow-2xs active:scale-95"
        title="Bagikan Tautan Peta Interaktif dengan Pratinjau Thumbnail OG"
        aria-label="Bagikan peta"
        @click="emit('share')"
      >
        <UIcon name="i-lucide-share-2" class="size-3.5" />
        <span>Bagikan</span>
      </button>
    </div>

    <!-- Tab Menu: Peta / Dataset / Geopark (Segmented Control Pill 100% Radius - Teks Proporsional) -->
    <div
      v-if="!isMobileDrawer"
      class="px-4 sm:px-5 pt-1.5 pb-2 shrink-0"
    >
      <div
        role="tablist"
        aria-label="Navigasi Panel Layer"
        class="grid grid-cols-3 gap-1 p-1 bg-slate-200/60 dark:bg-slate-800/60 rounded-full text-sm font-bold tracking-tight"
      >
        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'layers'"
          class="py-2 px-2 sm:px-3 min-h-[44px] rounded-full flex items-center justify-center transition-all cursor-pointer select-none text-center text-sm font-bold tracking-tight"
          :class="currentActiveTab === 'layers'
            ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 shadow-xs'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          @click="handleTabClick('layers')"
        >
          Peta
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'dataset'"
          class="py-2 px-2 sm:px-3 min-h-[44px] rounded-full flex items-center justify-center transition-all cursor-pointer select-none text-center text-sm font-bold tracking-tight"
          :class="currentActiveTab === 'dataset'
            ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 shadow-xs'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          @click="handleTabClick('dataset')"
        >
          Dataset
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="currentActiveTab === 'geopark'"
          class="py-2 px-2 sm:px-3 min-h-[44px] rounded-full flex items-center justify-center transition-all cursor-pointer select-none text-center text-sm font-bold tracking-tight"
          :class="currentActiveTab === 'geopark'
            ? 'bg-white dark:bg-[#0c1424] text-blue-600 dark:text-blue-400 shadow-xs'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          @click="handleTabClick('geopark')"
        >
          Geosite
        </button>
      </div>
    </div>

    <!-- Wrapper Pencarian & Konten Sub Halaman -->
    <div
      ref="searchWrapperRef"
      class="flex-1 flex flex-col min-h-0"
    >
      <!-- Top Bar: Universal Search Input (selaras di semua tab: Peta, Dataset, Geopark; tersembunyi hanya saat mode detail) -->
      <div
        v-if="!(isSearchOpen && searchSubView === 'detail') && petaSubView !== 'layer-detail' && petaSubView !== 'identify' && geoparkSubView !== 'detail'"
        class="px-4 sm:px-5 pt-1 pb-2.5 shrink-0"
      >
        <div class="relative">
          <UIcon
            name="i-lucide-search"
            class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 pointer-events-none transition-colors"
            :class="isSearchOpen ? 'text-blue-500' : 'text-slate-500'"
          />
          <input
            ref="searchInputRef"
            v-model="universalSearchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full bg-slate-100/90 dark:bg-white/[0.05] border border-slate-200/90 dark:border-white/10 rounded-full pl-9 sm:pl-10 pr-9 py-2 text-base sm:text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all min-h-[42px] sm:min-h-[44px]"
            @focus="handleSearchFocus"
            @click="handleSearchFocus"
            @input="handleSearchFocus"
            @keydown.enter="handleSearchSubmit"
          />
          <button
            v-if="isSearchOpen || universalSearchQuery"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 size-7 rounded-full after:absolute after:-inset-2 after:content-[''] bg-slate-200/90 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-95"
            aria-label="Tutup pencarian dan kembali ke halaman utama peta"
            title="Tutup pencarian dan kembali ke halaman utama peta"
            @click.stop="handleCloseSearch"
          >
            <UIcon name="i-lucide-x" class="size-3.5" />
          </button>
        </div>
      </div>

      <!-- Body Content Area (Router Sub-View Shell) -->
      <div :class="['min-h-0 flex-1 flex flex-col', isMobileDrawer ? '' : 'overflow-y-auto']">
        <!-- Router Case 1: Search List View -->
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

        <!-- Router Case 2: Search Item Detail View -->
        <SearchItemAboutView
          v-else-if="isSearchOpen && searchSubView === 'detail' && selectedSearchItem"
          key="search-item-detail"
          :selected-item="selectedSearchItem"
          :selected-kecamatan-filter="props.selectedKecamatanFilter"
          :active-layers="props.activeLayers"
          @close="handleBackToSearchList"
          @zoom-kecamatan="handleZoomToKecamatanInDetail"
          @zoom-dataset="handleZoomToDatasetInDetail"
          @toggle-dataset="handleToggleDatasetInDetail"
          @zoom-geopark="handleZoomToGeoparkInDetail"
          @zoom-coordinate="handleZoomCoordinateInDetail"
          @open-geopark-tab="handleOpenGeoparkTabFromAbout"
        />

        <!-- Router Case 3: Tab Peta (Layers / Layer-Detail / Identify) -->
        <div
          v-else-if="currentActiveTab === 'layers'"
          key="tab-layers"
          class="flex-1 flex flex-col min-h-0"
        >
          <!-- Sub-View 3A: Active Layers List -->
          <ActiveLayersView
            v-if="petaSubView === 'layers'"
            key="sv-layers"
            :active-layers="props.activeLayers"
            :selected-kecamatan-filter="props.selectedKecamatanFilter"
            :universal-search-query="universalSearchQuery"
            :filtered-active-layers="filteredActiveLayers"
            @select-layer="handleSelectLayer"
            @toggle-layer-visibility="(id) => emit('toggle-layer-visibility', id)"
            @reorder-layers="(list) => emit('reorder-layers', list)"
            @update-filter="(payload) => emit('update-layer-filter', payload)"
            @reset-wilayah="handleResetWilayahFilter"
            @go-to-geoportal="handleGoToDataset"
            @go-to-geopark="handleGoToGeopark"
            @clear-search="handleClearSearch"
            @share="emit('share')"
          />

          <!-- Sub-View 3B: Layer Detail & Settings -->
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

          <!-- Sub-View 3C: Fitur & Koordinat Identify Inspector (Hero Header & Arsitektur Selaras Detail About) -->
          <IdentifyView
            v-else-if="petaSubView === 'identify'"
            key="sv-identify"
            :clicked-coordinate="props.clickedCoordinate"
            :location-label="props.locationLabel"
            :identified-features="props.identifiedFeatures"
            :is-identifying="props.isIdentifying"
            @close="handleCloseInspector"
            @clear-pin="handleClearPin"
            @select-feature="(f) => emit('select-feature', f)"
            @share="emit('share')"
          />
        </div>

        <!-- Router Case 4: Tab Dataset (Katalog Layer Simpul Geoportal JIGN) -->
        <div
          v-else-if="currentActiveTab === 'dataset'"
          key="tab-dataset"
          class="flex-1 overflow-y-auto px-4 sm:px-5 py-3"
        >
          <GeoportalLayerSection
            :active-layers="props.activeLayers"
            @toggle-layer="handleGeoportalToggle"
            @update-opacity="handleGeoportalOpacity"
          />

          <!-- Atribusi Mobile Drawer (Sisi Bawah Konten) -->
          <div
            v-if="isMobileDrawer"
            class="px-4 sm:px-5 py-4 mt-6 border-t border-slate-200/60 dark:border-white/5 text-xs sm:text-xs leading-relaxed text-slate-500 dark:text-slate-400 text-center select-text"
          >
            <p>
              Data peta © kontributor <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer" class="underline hover:text-slate-600 dark:hover:text-slate-300">OpenStreetMap</a> · BAPPEDA Kab. Bojonegoro
            </p>
          </div>
        </div>

        <!-- Router Case 5: Tab Geopark (Katalog Resmi Geopark Bojonegoro) -->
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
      </div>
    </div>

    <!-- Footer Bottom: Panel Kiri (Desktop: Sisi Bawah Panel Permanen) -->
    <div
      v-if="!isMobileDrawer"
      class="border-t border-slate-200/90 dark:border-white/8 bg-slate-50/80 dark:bg-[#070c16]/80 px-4 sm:px-5 py-2.5 sm:py-3 shrink-0 flex flex-col gap-2.5 text-xs select-none"
    >
      <!-- Baris 1: Standar Atribusi & Hak Cipta GIS Melarosa -->
      <div class="border-slate-200/60 dark:border-white/5 text-xs sm:text-xs leading-relaxed text-slate-500 dark:text-slate-400 select-text">
        <p class="leading-normal">
          Satu peta © by <a href="#" target="_blank" rel="noopener noreferrer" class="underline hover:text-slate-800 dark:hover:text-slate-200 font-medium transition-colors">Melarosa</a> · BAPPEDA Kab. Bojonegoro
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>

::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.4);
  border-radius: 9999px;
}
::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.6);
}
</style>

<script setup lang="ts">
import "ol/ol.css";

definePageMeta({
  middleware: ["auth", "role-admin"],
  fullBleed: true,
});

useSeoMeta({
  title: "Wilayah Kecamatan - Map Series & Dataset Spasial",
});

interface KecamatanProperties {
  id: number;
  nama_kecamatan: string;
  nama_pimpinan: string | null;
  nama_jabatan: string | null;
  nip: string | null;
  pangkat_gol: string | null;
  luas_hektar: number;
  keliling_meter: number;
  centroid?: { type: string; coordinates: [number, number] } | null;
  created_at?: string;
  updated_at?: string;
}

interface KecamatanFeature {
  type: "Feature";
  id: number;
  geometry: any;
  properties: KecamatanProperties;
}

interface KecamatanSeriesItem {
  id: number;
  nama_kecamatan: string;
  nama_pimpinan: string | null;
  nama_jabatan: string | null;
  jumlah_desa: number;
  luas_hektar: number;
  bbox: [number, number, number, number]; // [minX, minY, maxX, maxY]
}

const colorMode = useColorMode();
const toast = useToast();

// View Mode: 'map' | 'table'
const viewMode = ref<"map" | "table">("map");

// Runtime Config for API URL
const config = useRuntimeConfig();
const apiBase = config.public.apiBase || "http://localhost:9000";

// 1. Lightweight Summary Data Fetching
interface SpatialSummary {
  ok: boolean;
  total_kecamatan: number;
  total_luas_hektar: number;
  bbox: [number, number, number, number];
}

const { data: summaryData, status: summaryStatus, refresh: refreshSummary, error } = useHttp<SpatialSummary>(
  "admin/batas-wilayah-kecamatan",
  {
    query: { format: "summary" },
    onFetchResponseError: (ctx) => {
      console.error("[WilayahKecamatan] API error:", ctx.response?.status, ctx.response?._data);
    },
  }
);

// 2. Fetch Kecamatan Series for Map Series Navigation & Filter
const { data: kecamatanResponse, status: kecamatanStatus, refresh: refreshKecamatan } = useHttp<{
  ok: boolean;
  data: KecamatanSeriesItem[];
}>("admin/batas-wilayah-kecamatan", {
  query: { format: "series" },
});

const kecamatanList = computed(() => kecamatanResponse.value?.data || []);

// Map Series Navigation State
const selectedKecamatanId = ref<number | null>(null);

const selectedKecamatan = computed(() => {
  if (!selectedKecamatanId.value) return null;
  return kecamatanList.value.find((k) => k.id === selectedKecamatanId.value) || null;
});

const currentSeriesIndex = computed(() => {
  if (!selectedKecamatanId.value) return -1;
  return kecamatanList.value.findIndex((k) => k.id === selectedKecamatanId.value);
});

function getJumlahDesa(kecId: number | null | undefined): number {
  if (!kecId) return 0;
  const found = kecamatanList.value.find((k) => k.id === Number(kecId));
  return found ? found.jumlah_desa : 0;
}

// 3. Tabular Data Fetching (Paginated & on-demand for Table View)
const tableSearch = ref("");
const { data: tableDataResponse, status: tableStatus, refresh: refreshTable } = useHttp<{
  ok: boolean;
  data: any[];
  total: number;
  current_page: number;
  last_page: number;
}>("admin/batas-wilayah-kecamatan", {
  query: computed(() => ({
    format: "table",
    per_page: 50,
    search: tableSearch.value,
  })),
  lazy: true,
});

const loading = computed(
  () =>
    summaryStatus.value === "pending" ||
    kecamatanStatus.value === "pending" ||
    tableStatus.value === "pending"
);
const fetchError = computed(() => (summaryStatus.value === "error" ? error.value : null));

// Totals
const totalKecamatan = computed(() => summaryData.value?.total_kecamatan || 28);
const totalLuasHektar = computed(() => {
  return (summaryData.value?.total_luas_hektar || 0).toLocaleString("id-ID", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
});

const tableFeatures = computed(() => tableDataResponse.value?.data || []);

const formattedTableFeatures = computed(() => {
  return tableFeatures.value.map((item: any) => {
    const props = item.properties || item;
    return {
      id: item.id || props.id,
      nama_kecamatan: props.nama_kecamatan || item.nama_kecamatan || "",
      luas_hektar: props.luas_hektar || item.luas_hektar || 0,
      nama_pimpinan: props.nama_pimpinan || item.nama_pimpinan || "-",
      nama_jabatan: props.nama_jabatan || item.nama_jabatan || "Camat",
      nip: props.nip || item.nip || "-",
      pangkat_gol: props.pangkat_gol || item.pangkat_gol || "-",
      raw: item,
    };
  });
});

// Selected Feature for Inspector Drawer
const selectedFeature = ref<any | null>(null);

// OpenLayers Reference & State
const mapContainer = ref<HTMLElement | null>(null);
const tooltipContainer = ref<HTMLElement | null>(null);
let mapInstance: any = null;
let vectorTileSource: any = null;
let vectorTileLayer: any = null;
let tileLayer: any = null;
let tooltipOverlay: any = null;
let olModules: any = null;

const mapLoaded = ref(false);
const currentBasemap = ref<"dark" | "light" | "satellite">("light");
const hoveredFeature = ref<any>(null);
const tooltipContent = ref<{ nama_kecamatan: string; luas_hektar: number; pimpinan: string; jumlah_desa: number } | null>(null);

function getBasemapSource(type: "dark" | "light" | "satellite") {
  if (!olModules) return null;
  const { OSM, XYZ } = olModules;

  if (type === "satellite") {
    return new XYZ({
      url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
      maxZoom: 19,
    });
  }

  if (type === "dark") {
    return new XYZ({
      url: "https://basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png",
      attributions: '&copy; <a href="https://carto.com/">CARTO</a>',
      maxZoom: 19,
    });
  }

  return new OSM();
}

function switchBasemap(type: "dark" | "light" | "satellite") {
  currentBasemap.value = type;
  if (tileLayer && olModules) {
    tileLayer.setSource(getBasemapSource(type));
  }
}

// Fit map to county bounds or summary bbox
function fitMapToBounds() {
  if (!mapInstance || !olModules) return;
  const { fromLonLat, transformExtent } = olModules;

  if (summaryData.value?.bbox) {
    const [minX, minY, maxX, maxY] = summaryData.value.bbox;
    const olExtent = transformExtent([minX, minY, maxX, maxY], "EPSG:4326", "EPSG:3857");
    mapInstance.getView().fit(olExtent, {
      duration: 800,
      padding: [80, 50, 50, 50],
      maxZoom: 14,
    });
  } else {
    mapInstance.getView().animate({
      center: fromLonLat([111.88, -7.2]),
      zoom: 10,
      duration: 600,
    });
  }
}

// Select Kecamatan & fly to its bbox (Map Series action)
function selectKecamatan(id: number | null) {
  selectedKecamatanId.value = id;

  if (!id) {
    fitMapToBounds();
    if (vectorTileLayer) {
      vectorTileLayer.changed();
    }
    return;
  }

  const kec = kecamatanList.value.find((k) => k.id === id);
  if (kec && kec.bbox && mapInstance && olModules) {
    const { transformExtent } = olModules;
    const olExtent = transformExtent(kec.bbox, "EPSG:4326", "EPSG:3857");
    mapInstance.getView().fit(olExtent, {
      duration: 800,
      padding: [110, 60, 60, 60],
      maxZoom: 14,
    });
  }

  if (vectorTileLayer) {
    vectorTileLayer.changed();
  }
}

function prevKecamatan() {
  if (kecamatanList.value.length === 0) return;
  const idx = currentSeriesIndex.value;
  if (idx <= 0) {
    selectKecamatan(kecamatanList.value[kecamatanList.value.length - 1].id);
  } else {
    selectKecamatan(kecamatanList.value[idx - 1].id);
  }
}

function nextKecamatan() {
  if (kecamatanList.value.length === 0) return;
  const idx = currentSeriesIndex.value;
  if (idx === -1 || idx >= kecamatanList.value.length - 1) {
    selectKecamatan(kecamatanList.value[0].id);
  } else {
    selectKecamatan(kecamatanList.value[idx + 1].id);
  }
}

function resetToAllBojonegoro() {
  selectedKecamatanId.value = null;
  fitMapToBounds();
  if (vectorTileLayer) {
    vectorTileLayer.changed();
  }
}

// Dynamic Vector Tile Styler with Map Series Focus (Indigo Theme)
function getKecamatanStyle(feature: any, resolution: number) {
  if (!olModules) return null;
  const { Style, Fill, Stroke, Text } = olModules;

  const fId = feature.get("id") || feature.getId();
  const isSelected =
    selectedFeature.value &&
    (selectedFeature.value.id === fId || selectedFeature.value.properties?.id === fId);
  const isHovered =
    hoveredFeature.value &&
    (hoveredFeature.value.get("id") === fId || hoveredFeature.value.getId() === fId);

  // Check Map Series filtering
  const isKecamatanFiltered = selectedKecamatanId.value !== null;
  const isMatchingKecamatan = !isKecamatanFiltered || (fId && Number(fId) === selectedKecamatanId.value);

  // 1. Selected Style (Cyan / Sky)
  if (isSelected) {
    return new Style({
      fill: new Fill({
        color: "rgba(14, 165, 233, 0.42)",
      }),
      stroke: new Stroke({
        color: "#0284c7",
        width: 3.5,
      }),
      text: new Text({
        text: feature.get("nama_kecamatan") || "",
        font: "bold 13px Inter, sans-serif",
        fill: new Fill({ color: "#ffffff" }),
        stroke: new Stroke({ color: "#0369a1", width: 4 }),
        overflow: true,
      }),
      zIndex: 100,
    });
  }

  // 2. Hover Style (Indigo Bright)
  if (isHovered) {
    return new Style({
      fill: new Fill({
        color: "rgba(129, 140, 248, 0.50)",
      }),
      stroke: new Stroke({
        color: "#6366f1",
        width: 3,
      }),
      text: new Text({
        text: feature.get("nama_kecamatan") || "",
        font: "bold 13px Inter, sans-serif",
        fill: new Fill({ color: "#ffffff" }),
        stroke: new Stroke({ color: "#4338ca", width: 4 }),
        overflow: true,
      }),
      zIndex: 50,
    });
  }

  // 3. Map Series Dimmed State (Outside selected kecamatan)
  if (isKecamatanFiltered && !isMatchingKecamatan) {
    return new Style({
      fill: new Fill({
        color:
          colorMode.value === "dark"
            ? "rgba(15, 23, 42, 0.15)"
            : "rgba(226, 232, 240, 0.20)",
      }),
      stroke: new Stroke({
        color:
          colorMode.value === "dark"
            ? "rgba(255, 255, 255, 0.08)"
            : "rgba(148, 163, 184, 0.35)",
        width: 0.8,
      }),
      zIndex: 1,
    });
  }

  // 4. Default / Active Kecamatan Style (Indigo Royal)
  const showText = isKecamatanFiltered ? resolution < 180 : resolution < 140;

  return new Style({
    fill: new Fill({
      color: isKecamatanFiltered ? "rgba(99, 102, 241, 0.30)" : "rgba(99, 102, 241, 0.20)",
    }),
    stroke: new Stroke({
      color: isKecamatanFiltered ? "rgba(79, 70, 229, 0.95)" : "rgba(79, 70, 229, 0.85)",
      width: isKecamatanFiltered ? 2.5 : 2,
    }),
    text: showText
      ? new Text({
          text: feature.get("nama_kecamatan") || "",
          font: isKecamatanFiltered ? "bold 13px Inter, sans-serif" : "600 12px Inter, sans-serif",
          fill: new Fill({ color: colorMode.value === "dark" ? "#f3f4f6" : "#1e1b4b" }),
          stroke: new Stroke({
            color:
              colorMode.value === "dark"
                ? "rgba(15, 23, 42, 0.9)"
                : "rgba(255, 255, 255, 0.95)",
            width: 3.5,
          }),
          overflow: false,
        })
      : undefined,
    zIndex: isKecamatanFiltered ? 10 : 2,
  });
}

// Initialize OpenLayers Map with PostGIS Vector Tiles (MVT)
async function initMap() {
  if (!import.meta.client) return;

  await nextTick();

  if (!mapContainer.value) {
    console.error("[OpenLayers] mapContainer ref is null");
    return;
  }

  try {
    const [
      { default: Map },
      { default: View },
      { default: TileLayer },
      { default: VectorTileLayer },
      { default: VectorTileSource },
      { default: OSM },
      { default: XYZ },
      { default: MVT },
      stylePkg,
      projPkg,
      { default: Overlay },
    ] = await Promise.all([
      import("ol/Map.js"),
      import("ol/View.js"),
      import("ol/layer/Tile.js"),
      import("ol/layer/VectorTile.js"),
      import("ol/source/VectorTile.js"),
      import("ol/source/OSM.js"),
      import("ol/source/XYZ.js"),
      import("ol/format/MVT.js"),
      import("ol/style.js"),
      import("ol/proj.js"),
      import("ol/Overlay.js"),
    ]);

    olModules = {
      Map,
      View,
      TileLayer,
      VectorTileLayer,
      VectorTileSource,
      OSM,
      XYZ,
      MVT,
      Style: stylePkg.Style,
      Fill: stylePkg.Fill,
      Stroke: stylePkg.Stroke,
      Text: stylePkg.Text,
      fromLonLat: projPkg.fromLonLat,
      transformExtent: projPkg.transformExtent,
      Overlay,
    };

    // 1. Basemap Layer
    const baseSource = getBasemapSource(currentBasemap.value);
    tileLayer = new TileLayer({
      source: baseSource,
      zIndex: 0,
    });

    // 2. PostGIS MVT Vector Tile Source & Layer
    const mvtUrl = `${apiBase}/api/v1/dataset/mvt/batas-wilayah-kecamatan/{z}/{x}/{y}.pbf`;
    vectorTileSource = new VectorTileSource({
      format: new MVT({
        idProperty: "id",
      }),
      url: mvtUrl,
      maxZoom: 18,
    });

    vectorTileLayer = new VectorTileLayer({
      source: vectorTileSource,
      style: getKecamatanStyle,
      renderMode: "hybrid",
      zIndex: 10,
    });

    // 3. Hover Tooltip Overlay
    if (tooltipContainer.value) {
      tooltipOverlay = new Overlay({
        element: tooltipContainer.value,
        offset: [0, -10],
        positioning: "bottom-center",
        stopEvent: false,
      });
    }

    // 4. Instantiate Map
    mapInstance = new Map({
      target: mapContainer.value,
      layers: [tileLayer, vectorTileLayer],
      view: new View({
        center: olModules.fromLonLat([111.88, -7.2]),
        zoom: 10,
        minZoom: 8,
        maxZoom: 18,
      }),
      overlays: tooltipOverlay ? [tooltipOverlay] : [],
      controls: [],
    });

    // 5. Hover Interaction (Debounced Tooltip & Highlighting)
    mapInstance.on("pointermove", (e: any) => {
      if (e.dragging) {
        if (tooltipOverlay) tooltipOverlay.setPosition(undefined);
        return;
      }

      const pixel = mapInstance.getEventPixel(e.originalEvent);
      let hitFeature: any = null;

      mapInstance.forEachFeatureAtPixel(
        pixel,
        (feat: any) => {
          hitFeature = feat;
          return true;
        },
        {
          layerFilter: (layer: any) => layer === vectorTileLayer,
          hitTolerance: 3,
        }
      );

      if (hitFeature) {
        mapContainer.value!.style.cursor = "pointer";
        hoveredFeature.value = hitFeature;
        const props = hitFeature.getProperties ? hitFeature.getProperties() : {};
        const id = hitFeature.get("id") || hitFeature.getId() || props.id;

        tooltipContent.value = {
          nama_kecamatan: props.nama_kecamatan || hitFeature.get("nama_kecamatan") || "Kecamatan",
          luas_hektar: parseFloat(props.luas_hektar || hitFeature.get("luas_hektar") || "0"),
          pimpinan: props.nama_pimpinan || hitFeature.get("nama_pimpinan") || "-",
          jumlah_desa: getJumlahDesa(id),
        };
        if (tooltipOverlay) {
          tooltipOverlay.setPosition(e.coordinate);
        }
      } else {
        mapContainer.value!.style.cursor = "";
        hoveredFeature.value = null;
        tooltipContent.value = null;
        if (tooltipOverlay) {
          tooltipOverlay.setPosition(undefined);
        }
      }

      vectorTileLayer.changed();
    });

    // 6. Click Interaction: Select Feature & Open Inspector Drawer
    mapInstance.on("click", (e: any) => {
      const pixel = mapInstance.getEventPixel(e.originalEvent);
      let clickedFeature: any = null;

      mapInstance.forEachFeatureAtPixel(
        pixel,
        (feat: any) => {
          clickedFeature = feat;
          return true;
        },
        {
          layerFilter: (layer: any) => layer === vectorTileLayer,
          hitTolerance: 4,
        }
      );

      if (clickedFeature) {
        const props = clickedFeature.getProperties ? clickedFeature.getProperties() : {};
        const id = clickedFeature.get("id") || clickedFeature.getId() || props.id;

        selectedFeature.value = {
          type: "Feature",
          id: id,
          properties: {
            id: id,
            nama_kecamatan: props.nama_kecamatan || clickedFeature.get("nama_kecamatan") || "Kecamatan",
            nama_pimpinan: props.nama_pimpinan || clickedFeature.get("nama_pimpinan") || "-",
            nama_jabatan: props.nama_jabatan || clickedFeature.get("nama_jabatan") || "Camat",
            nip: props.nip || clickedFeature.get("nip") || "-",
            pangkat_gol: props.pangkat_gol || clickedFeature.get("pangkat_gol") || "-",
            luas_hektar: parseFloat(props.luas_hektar || clickedFeature.get("luas_hektar") || "0"),
            keliling_meter: parseFloat(props.keliling_meter || clickedFeature.get("keliling_meter") || "0"),
            jumlah_desa: getJumlahDesa(id),
          },
          geometry: null,
        };
      } else {
        selectedFeature.value = null;
      }

      vectorTileLayer.changed();
    });

    mapLoaded.value = true;

    // Fit map bounds if summary data is already available
    if (summaryData.value?.bbox) {
      fitMapToBounds();
    }
  } catch (err) {
    console.error("[OpenLayers] Initialization error:", err);
  }
}

// Refresh vector tile layer & all data
async function handleRefreshAll() {
  await Promise.all([refreshSummary(), refreshKecamatan(), refreshTable()]);
  if (vectorTileSource) {
    vectorTileSource.refresh();
  }
  toast.add({
    title: "Data Diperbarui",
    description: "Tile spasial & data wilayah kecamatan telah disinkronkan.",
    color: "success",
  });
}

// Zoom directly to a subdistrict feature from Table View
function zoomToFeature(feat: any) {
  viewMode.value = "map";
  selectedFeature.value = {
    id: feat.id,
    properties: {
      id: feat.id,
      nama_kecamatan: feat.nama_kecamatan,
      nama_pimpinan: feat.nama_pimpinan,
      nama_jabatan: feat.nama_jabatan,
      nip: feat.nip,
      pangkat_gol: feat.pangkat_gol,
      luas_hektar: feat.luas_hektar,
      keliling_meter: feat.keliling_meter,
      jumlah_desa: getJumlahDesa(feat.id),
    },
  };

  selectKecamatan(feat.id);
}

// Modal State for Add / Edit
const isFormModalOpen = ref(false);
const isEditing = ref(false);
const submitting = ref(false);
const formState = reactive({
  id: null as number | null,
  nama_kecamatan: "",
  nama_pimpinan: "",
  nama_jabatan: "Camat",
  nip: "",
  pangkat_gol: "",
  geometry: "",
});

function openCreateModal() {
  isEditing.value = false;
  formState.id = null;
  formState.nama_kecamatan = "";
  formState.nama_pimpinan = "";
  formState.nama_jabatan = "Camat";
  formState.nip = "";
  formState.pangkat_gol = "";
  formState.geometry = JSON.stringify(
    {
      type: "Polygon",
      coordinates: [
        [
          [111.8, -7.1],
          [111.9, -7.1],
          [111.9, -7.2],
          [111.8, -7.2],
          [111.8, -7.1],
        ],
      ],
    },
    null,
    2
  );
  isFormModalOpen.value = true;
}

function openEditModal(feat: any) {
  isEditing.value = true;
  formState.id = feat.id;
  formState.nama_kecamatan = feat.properties?.nama_kecamatan || feat.nama_kecamatan || "";
  formState.nama_pimpinan = feat.properties?.nama_pimpinan || feat.nama_pimpinan || "";
  formState.nama_jabatan = feat.properties?.nama_jabatan || feat.nama_jabatan || "Camat";
  formState.nip = feat.properties?.nip || feat.nip || "";
  formState.pangkat_gol = feat.properties?.pangkat_gol || feat.pangkat_gol || "";
  formState.geometry = JSON.stringify(feat.geometry || {}, null, 2);
  isFormModalOpen.value = true;
}

async function handleSaveForm() {
  if (!formState.nama_kecamatan.trim()) {
    toast.add({ title: "Validasi Gagal", description: "Nama kecamatan wajib diisi.", color: "error" });
    return;
  }

  let parsedGeom: any;
  try {
    parsedGeom = JSON.parse(formState.geometry);
  } catch (err) {
    toast.add({
      title: "Format Geometri Salah",
      description: "Geometry wajib berupa string GeoJSON yang valid.",
      color: "error",
    });
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      nama_kecamatan: formState.nama_kecamatan,
      nama_pimpinan: formState.nama_pimpinan || null,
      nama_jabatan: formState.nama_jabatan || null,
      nip: formState.nip || null,
      pangkat_gol: formState.pangkat_gol || null,
      geometry: parsedGeom,
    };

    if (isEditing.value && formState.id) {
      await $http(`admin/batas-wilayah-kecamatan/${formState.id}`, {
        method: "PUT",
        body: payload,
      });
      toast.add({ title: "Berhasil", description: "Data kecamatan berhasil diperbarui.", color: "success" });
    } else {
      await $http("admin/batas-wilayah-kecamatan", {
        method: "POST",
        body: payload,
      });
      toast.add({ title: "Berhasil", description: "Batas kecamatan baru berhasil disimpan.", color: "success" });
    }

    isFormModalOpen.value = false;
    await handleRefreshAll();
  } catch (err: any) {
    toast.add({
      title: "Gagal Menyimpan",
      description: err?.data?.message || "Terjadi kesalahan saat menyimpan data batas wilayah kecamatan.",
      color: "error",
    });
  } finally {
    submitting.value = false;
  }
}

// Modal Delete
const isDeleteModalOpen = ref(false);
const featureToDelete = ref<any | null>(null);
const deleting = ref(false);

function confirmDelete(feat: any) {
  featureToDelete.value = feat;
  isDeleteModalOpen.value = true;
}

async function handleDelete() {
  if (!featureToDelete.value) return;
  deleting.value = true;

  try {
    const id = featureToDelete.value.id || featureToDelete.value.properties?.id;
    await $http(`admin/batas-wilayah-kecamatan/${id}`, {
      method: "DELETE",
    });
    toast.add({ title: "Terhapus", description: "Data wilayah kecamatan berhasil dihapus.", color: "success" });

    if (selectedFeature.value && selectedFeature.value.id === id) {
      selectedFeature.value = null;
    }
    isDeleteModalOpen.value = false;
    await handleRefreshAll();
  } catch (err: any) {
    toast.add({
      title: "Gagal Menghapus",
      description: err?.data?.message || "Gagal menghapus data wilayah kecamatan.",
      color: "error",
    });
  } finally {
    deleting.value = false;
  }
}

// Watch summary data to auto-fit map bounds
watch(summaryData, (newSummary) => {
  if (newSummary?.bbox && mapLoaded.value && !selectedKecamatanId.value) {
    fitMapToBounds();
  }
});

// Re-render vector tile layer on color mode change
watch(
  () => colorMode.value,
  () => {
    if (vectorTileLayer) {
      vectorTileLayer.changed();
    }
  }
);

onMounted(() => {
  initMap();
});

onUnmounted(() => {
  if (mapInstance) {
    mapInstance.setTarget(null as any);
    mapInstance = null;
    vectorTileSource = null;
    vectorTileLayer = null;
    tileLayer = null;
    tooltipOverlay = null;
  }
});
</script>

<template>
  <div class="relative w-full h-[calc(100vh-3.5rem)] overflow-hidden bg-gray-100 dark:bg-[#070b14]">
    <!-- VIEW 1: FULLSCREEN MAP CANVAS VIEW -->
    <div v-show="viewMode === 'map'" class="absolute inset-0 w-full h-full">
      
      <!-- OpenLayers Map Canvas Container (Full Bleed) -->
      <div ref="mapContainer" class="absolute inset-0 w-full h-full bg-gray-100 dark:bg-[#070b14]" />

      <!-- Floating Map Tooltip for Feature Hover -->
      <div
        ref="tooltipContainer"
        class="pointer-events-none -translate-x-1/2 -translate-y-full pb-2 transition-opacity duration-150"
        :class="tooltipContent ? 'opacity-100' : 'opacity-0'"
      >
        <div
          v-if="tooltipContent"
          class="px-3 py-2 rounded-xl border border-gray-200/90 dark:border-white/[0.12] bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md shadow-xl text-xs space-y-1 min-w-[160px]"
        >
          <div class="flex items-center justify-between gap-2">
            <p class="font-bold text-gray-900 dark:text-white">Kec. {{ tooltipContent.nama_kecamatan }}</p>
            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
              Kecamatan
            </span>
          </div>
          <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 gap-2">
            <span>Jumlah Desa:</span>
            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ tooltipContent.jumlah_desa }} Desa</span>
          </div>
          <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 gap-2">
            <span>Luas:</span>
            <span class="font-semibold text-indigo-600 dark:text-indigo-400">
              {{ tooltipContent.luas_hektar.toLocaleString('id-ID') }} Ha
            </span>
          </div>
          <div v-if="tooltipContent.pimpinan && tooltipContent.pimpinan !== '-'" class="text-[10px] text-gray-400 dark:text-gray-500 pt-0.5 border-t border-gray-100 dark:border-white/[0.06] truncate max-w-[170px]">
            Camat: {{ tooltipContent.pimpinan }}
          </div>
        </div>
      </div>

      <!-- FLOATING TOOLBAR: Map Series & Kecamatan Filter -->
      <div class="absolute top-3 left-3 right-3 z-20 pointer-events-none flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2.5">
        
        <!-- Left Side: Series Stepper + Kecamatan Filter Dropdown -->
        <div class="pointer-events-auto flex items-center flex-wrap gap-1.5 p-1.5 rounded-xl border border-gray-200/80 dark:border-white/[0.1] bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md shadow-lg shadow-black/5 dark:shadow-black/25">
          
          <!-- Badge -->
          <div class="flex items-center gap-1.5 px-2 py-1">
            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300">
              {{ totalKecamatan }} Kecamatan
            </span>
          </div>

          <div class="h-4 w-px bg-gray-200 dark:bg-white/[0.1] hidden sm:block" />

          <!-- Map Series Stepper Controls [◀] [Series Index] [▶] -->
          <div class="flex items-center gap-0.5 bg-gray-100 dark:bg-white/[0.06] rounded-lg p-0.5">
            <UButton
              icon="i-lucide-chevron-left"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="kecamatanList.length === 0"
              title="Kecamatan Sebelumnya (Map Series)"
              @click="prevKecamatan"
            />
            
            <span class="text-[11px] font-medium text-gray-700 dark:text-gray-200 px-1.5 min-w-[70px] text-center select-none truncate">
              <template v-if="selectedKecamatanId !== null && currentSeriesIndex >= 0">
                Series {{ currentSeriesIndex + 1 }}/{{ kecamatanList.length }}
              </template>
              <template v-else>
                Bojonegoro
              </template>
            </span>

            <UButton
              icon="i-lucide-chevron-right"
              size="xs"
              color="neutral"
              variant="ghost"
              :disabled="kecamatanList.length === 0"
              title="Kecamatan Berikutnya (Map Series)"
              @click="nextKecamatan"
            />
          </div>

          <!-- Kecamatan Filter Dropdown -->
          <div class="w-44 sm:w-56">
            <select
              :value="selectedKecamatanId ?? ''"
              class="w-full text-xs rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#131926] text-gray-900 dark:text-white px-2.5 py-1.5 focus:outline-hidden focus:ring-1 focus:ring-indigo-500 transition-colors"
              @change="(e: any) => selectKecamatan(e.target.value ? Number(e.target.value) : null)"
            >
              <option value="">Semua Kecamatan (28)</option>
              <option
                v-for="kec in kecamatanList"
                :key="kec.id"
                :value="kec.id"
              >
                Kec. {{ kec.nama_kecamatan }} ({{ kec.jumlah_desa }} Desa)
              </option>
            </select>
          </div>

          <!-- Reset Button (Visible when filtered) -->
          <UButton
            v-if="selectedKecamatanId !== null"
            icon="i-lucide-rotate-ccw"
            label="Reset"
            size="xs"
            color="neutral"
            variant="subtle"
            class="text-[11px]"
            title="Reset ke Seluruh Wilayah Bojonegoro"
            @click="resetToAllBojonegoro"
          />

          <!-- Active Kecamatan Stats Pill -->
          <div
            v-if="selectedKecamatan"
            class="hidden xl:flex items-center gap-2 px-2.5 py-1 rounded-lg bg-indigo-500/10 dark:bg-indigo-500/15 border border-indigo-500/20 text-xs text-indigo-700 dark:text-indigo-300"
          >
            <span class="font-semibold">{{ selectedKecamatan.nama_kecamatan }}</span>
            <span class="size-1 rounded-full bg-indigo-500" />
            <span>{{ selectedKecamatan.jumlah_desa }} Desa</span>
            <span class="size-1 rounded-full bg-indigo-500" />
            <span>{{ selectedKecamatan.luas_hektar.toLocaleString('id-ID') }} Ha</span>
          </div>
        </div>

        <!-- Right Side: Basemap Switcher + View Mode + Actions -->
        <div class="pointer-events-auto flex items-center flex-wrap gap-1.5 p-1.5 rounded-xl border border-gray-200/80 dark:border-white/[0.1] bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md shadow-lg shadow-black/5 dark:shadow-black/25">
          
          <!-- Basemap Switcher -->
          <div class="flex items-center p-0.5 rounded-lg bg-gray-100 dark:bg-white/[0.06]">
            <button
              type="button"
              :class="[
                'px-2 py-1 rounded-md text-[11px] font-medium transition-colors',
                currentBasemap === 'dark' ? 'bg-white dark:bg-[#1e293b] text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white',
              ]"
              @click="switchBasemap('dark')"
            >
              Dark
            </button>
            <button
              type="button"
              :class="[
                'px-2 py-1 rounded-md text-[11px] font-medium transition-colors',
                currentBasemap === 'light' ? 'bg-white dark:bg-[#1e293b] text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white',
              ]"
              @click="switchBasemap('light')"
            >
              Light
            </button>
            <button
              type="button"
              :class="[
                'px-2 py-1 rounded-md text-[11px] font-medium transition-colors',
                currentBasemap === 'satellite' ? 'bg-white dark:bg-[#1e293b] text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white',
              ]"
              @click="switchBasemap('satellite')"
            >
              Satelit
            </button>
          </div>

          <div class="h-4 w-px bg-gray-200 dark:bg-white/[0.1]" />

          <!-- Mode Toggle: Peta / Tabel -->
          <div class="flex items-center p-0.5 rounded-lg bg-gray-100 dark:bg-white/[0.06]">
            <UButton
              icon="i-lucide-map"
              label="Peta"
              size="xs"
              :color="viewMode === 'map' ? 'primary' : 'neutral'"
              :variant="viewMode === 'map' ? 'solid' : 'ghost'"
              class="text-xs"
              @click="viewMode = 'map'"
            />
            <UButton
              icon="i-lucide-table"
              label="Tabel"
              size="xs"
              :color="viewMode === 'table' ? 'primary' : 'neutral'"
              :variant="viewMode === 'table' ? 'solid' : 'ghost'"
              class="text-xs"
              @click="viewMode = 'table'"
            />
          </div>

          <!-- Refresh -->
          <UButton
            icon="i-lucide-refresh-cw"
            size="xs"
            color="neutral"
            variant="ghost"
            :loading="loading"
            title="Muat Ulang Data Spasial"
            @click="handleRefreshAll"
          />

          <!-- Tambah Kecamatan -->
          <UButton
            icon="i-lucide-plus"
            label="Tambah"
            size="xs"
            color="primary"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- Map Legend Card (Bottom Left) -->
      <div class="absolute bottom-4 start-4 z-10 p-3 rounded-xl border border-gray-200/80 dark:border-white/[0.1] bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md shadow-lg text-xs space-y-1.5 pointer-events-auto">
        <div class="flex items-center justify-between gap-4">
          <p class="font-semibold text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
            Legenda Layer
          </p>
          <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-mono">PostGIS MVT</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="size-3.5 rounded-xs border border-indigo-600 bg-indigo-500/25"></div>
          <span class="text-gray-700 dark:text-gray-300 text-[11px]">Batas Wilayah Kecamatan</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="size-3.5 rounded-xs border border-sky-500 bg-sky-400/35"></div>
          <span class="text-gray-700 dark:text-gray-300 text-[11px]">Kecamatan Terpilih</span>
        </div>
        <div v-if="selectedKecamatanId !== null" class="flex items-center gap-2">
          <div class="size-3.5 rounded-xs border border-gray-400/40 bg-gray-400/10"></div>
          <span class="text-gray-500 dark:text-gray-400 text-[11px]">Di Luar Kecamatan Aktif</span>
        </div>
        <p class="text-[10px] text-gray-400 dark:text-gray-500 pt-0.5 border-t border-gray-100 dark:border-white/[0.06]">
          Klik poligon untuk inspeksi detail
        </p>
      </div>

      <!-- Quick Zoom Navigation (Bottom Right) -->
      <div class="absolute bottom-4 end-4 z-10 flex flex-col gap-1 pointer-events-auto">
        <UButton
          icon="i-lucide-maximize-2"
          size="xs"
          color="neutral"
          variant="subtle"
          class="rounded-lg shadow-md"
          title="Fit Seluruh Bojonegoro"
          @click="fitMapToBounds"
        />
        <UButton
          v-if="selectedKecamatan"
          icon="i-lucide-focus"
          size="xs"
          color="primary"
          variant="solid"
          class="rounded-lg shadow-md"
          :title="'Fokus ke Kec. ' + selectedKecamatan.nama_kecamatan"
          @click="selectKecamatan(selectedKecamatanId)"
        />
      </div>

      <!-- SIDE FEATURE INSPECTOR DRAWER -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
      >
        <div
          v-if="selectedFeature"
          class="absolute top-16 end-4 bottom-4 w-84 max-w-[calc(100%-2rem)] z-20 p-4 rounded-2xl border border-gray-200/90 dark:border-white/[0.12] bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-xl shadow-2xl flex flex-col justify-between overflow-y-auto"
        >
          <div class="space-y-3.5">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/[0.08] pb-2.5">
              <div class="flex items-center gap-2 min-w-0">
                <div class="size-7 rounded-lg bg-indigo-500/15 flex items-center justify-center shrink-0">
                  <UIcon name="i-lucide-map" class="size-4 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div class="min-w-0">
                  <h3 class="font-bold text-sm text-gray-900 dark:text-white truncate">
                    Kec. {{ selectedFeature.properties.nama_kecamatan }}
                  </h3>
                  <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    {{ selectedFeature.properties.jumlah_desa || getJumlahDesa(selectedFeature.id) }} Wilayah Desa
                  </p>
                </div>
              </div>
              <UButton
                icon="i-lucide-x"
                color="neutral"
                variant="ghost"
                size="xs"
                @click="selectedFeature = null"
              />
            </div>

            <!-- Spatial Stats Metric Box -->
            <div class="p-3 rounded-xl bg-gray-50 dark:bg-[#070b14]/70 border border-gray-200/60 dark:border-white/[0.06] space-y-2 text-xs">
              <div class="flex justify-between items-center">
                <span class="text-gray-500 dark:text-gray-400">Luas Wilayah</span>
                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                  {{ selectedFeature.properties.luas_hektar }} Ha
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-500 dark:text-gray-400">Keliling Batas</span>
                <span class="font-medium text-gray-800 dark:text-gray-200">
                  {{ selectedFeature.properties.keliling_meter?.toLocaleString('id-ID') }} m
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-500 dark:text-gray-400">Jumlah Desa</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ selectedFeature.properties.jumlah_desa || getJumlahDesa(selectedFeature.id) }} Desa
                </span>
              </div>
            </div>

            <!-- Quick Action: Navigate to Wilayah Desa in this Kecamatan -->
            <div>
              <UButton
                icon="i-lucide-external-link"
                :label="'Buka Peta Desa Kec. ' + selectedFeature.properties.nama_kecamatan"
                size="xs"
                color="primary"
                variant="subtle"
                class="w-full justify-center text-xs"
                :to="'/admin/dataset/wilayah-desa'"
              />
            </div>

            <!-- Leadership Information -->
            <div class="space-y-2 pt-1 text-xs">
              <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-500">
                <UIcon name="i-lucide-user" class="size-3.5" />
                <span class="font-semibold uppercase tracking-wider text-[10px]">
                  Pimpinan Kecamatan
                </span>
              </div>
              <div class="space-y-1.5 p-2.5 rounded-xl bg-gray-50/70 dark:bg-[#070b14]/50 border border-gray-100 dark:border-white/[0.04]">
                <div>
                  <span class="text-[10px] text-gray-400 block">Nama Camat:</span>
                  <p class="font-semibold text-gray-900 dark:text-white">
                    {{ selectedFeature.properties.nama_pimpinan || '-' }}
                  </p>
                </div>
                <div>
                  <span class="text-[10px] text-gray-400 block">Jabatan:</span>
                  <p class="text-gray-700 dark:text-gray-300">
                    {{ selectedFeature.properties.nama_jabatan || 'Camat' }}
                  </p>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-1 border-t border-gray-100 dark:border-white/[0.04]">
                  <div>
                    <span class="text-[10px] text-gray-400 block">NIP:</span>
                    <p class="font-mono text-gray-700 dark:text-gray-300">
                      {{ selectedFeature.properties.nip || '-' }}
                    </p>
                  </div>
                  <div>
                    <span class="text-[10px] text-gray-400 block">Pangkat / Gol:</span>
                    <p class="text-gray-700 dark:text-gray-300">
                      {{ selectedFeature.properties.pangkat_gol || '-' }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Inspector Action Buttons -->
          <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-white/[0.08]">
            <UButton
              icon="i-lucide-pencil"
              label="Edit Kecamatan"
              size="xs"
              color="neutral"
              variant="outline"
              class="flex-1 justify-center"
              @click="openEditModal(selectedFeature)"
            />
            <UButton
              icon="i-lucide-trash-2"
              size="xs"
              color="error"
              variant="subtle"
              title="Hapus Wilayah Kecamatan"
              @click="confirmDelete(selectedFeature)"
            />
          </div>
        </div>
      </Transition>
    </div>

    <!-- VIEW 2: FULL DATA TABLE VIEW -->
    <div v-show="viewMode === 'table'" class="absolute inset-0 z-10 p-5 overflow-y-auto bg-gray-50/90 dark:bg-[#070b14] space-y-4">
      <!-- Table Top Toolbar -->
      <div class="p-4 rounded-xl border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-3">
          <UButton
            icon="i-lucide-arrow-left"
            size="xs"
            color="neutral"
            variant="ghost"
            label="Kembali ke Peta"
            @click="viewMode = 'map'"
          />
          <div class="h-4 w-px bg-gray-200 dark:bg-white/[0.08] hidden sm:block" />
          <h2 class="font-bold text-sm text-gray-900 dark:text-white">
            Daftar Wilayah Administrasi Kecamatan
          </h2>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <!-- Search input -->
          <UInput
            v-model="tableSearch"
            icon="i-heroicons-magnifying-glass"
            placeholder="Cari kecamatan, camat, NIP..."
            size="sm"
            class="w-56"
          />

          <UButton
            icon="i-lucide-refresh-cw"
            size="xs"
            color="neutral"
            variant="outline"
            :loading="loading"
            @click="refreshTable"
          />

          <UButton
            icon="i-lucide-plus"
            label="Tambah Kecamatan"
            size="xs"
            color="primary"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- Table Card -->
      <UCard
        :ui="{
          root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-xl overflow-hidden shadow-xs',
          body: 'p-0 sm:p-0',
        }"
      >
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-[#070b14]/60 border-b border-gray-200/70 dark:border-white/[0.08] text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider">
              <tr>
                <th class="px-4 py-3">Nama Kecamatan</th>
                <th class="px-4 py-3">Jumlah Desa</th>
                <th class="px-4 py-3">Luas Wilayah</th>
                <th class="px-4 py-3">Pimpinan & Jabatan</th>
                <th class="px-4 py-3">NIP / Pangkat</th>
                <th class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200/70 dark:divide-white/[0.08] text-gray-800 dark:text-gray-200">
              <tr v-if="formattedTableFeatures.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-xs">
                  Tidak ada data batas wilayah kecamatan yang cocok.
                </td>
              </tr>
              <tr
                v-for="feat in formattedTableFeatures"
                :key="feat.id"
                class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors"
              >
                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                  Kec. {{ feat.nama_kecamatan }}
                </td>
                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300 font-medium">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">
                    {{ getJumlahDesa(feat.id) }} Desa
                  </span>
                </td>
                <td class="px-4 py-3 text-indigo-600 dark:text-indigo-400 font-medium">
                  {{ feat.luas_hektar }} Ha
                </td>
                <td class="px-4 py-3">
                  <div class="text-xs font-medium text-gray-900 dark:text-gray-100">
                    {{ feat.nama_pimpinan }}
                  </div>
                  <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    {{ feat.nama_jabatan }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-xs font-mono text-gray-800 dark:text-gray-200">
                    {{ feat.nip }}
                  </div>
                  <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    {{ feat.pangkat_gol }}
                  </div>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <UButton
                      icon="i-lucide-map"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      title="Lihat di Peta"
                      @click="zoomToFeature(feat.raw)"
                    />
                    <UButton
                      icon="i-lucide-pencil"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      title="Edit Data"
                      @click="openEditModal(feat.raw)"
                    />
                    <UButton
                      icon="i-lucide-trash-2"
                      size="xs"
                      color="error"
                      variant="ghost"
                      title="Hapus Data"
                      @click="confirmDelete(feat.raw)"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>
    </div>

    <!-- MODAL 1: FORM TAMBAH / EDIT KECAMATAN -->
    <UModal
      v-model:open="isFormModalOpen"
      :title="isEditing ? 'Edit Batas Wilayah Kecamatan' : 'Tambah Batas Wilayah Kecamatan Baru'"
      :description="isEditing ? 'Perbarui informasi pimpinan dan geometri wilayah kecamatan.' : 'Masukkan parameter administrasi dan koordinat batas wilayah kecamatan.'"
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <form class="space-y-3.5" @submit.prevent="handleSaveForm">
          <UFormField label="Nama Kecamatan" required>
            <UInput
              v-model="formState.nama_kecamatan"
              placeholder="e.g. Bojonegoro"
              class="w-full"
              autofocus
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Nama Pimpinan (Camat)">
              <UInput
                v-model="formState.nama_pimpinan"
                placeholder="e.g. Bpk. Ahmad Yani"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Nama Jabatan">
              <UInput
                v-model="formState.nama_jabatan"
                placeholder="e.g. Camat"
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="NIP">
              <UInput
                v-model="formState.nip"
                placeholder="e.g. 19810427..."
                class="w-full"
              />
            </UFormField>

            <UFormField label="Pangkat / Golongan">
              <UInput
                v-model="formState.pangkat_gol"
                placeholder="e.g. Pembina (IV/a)"
                class="w-full"
              />
            </UFormField>
          </div>

          <UFormField label="GeoJSON Geometry (Polygon / MultiPolygon)" required>
            <UTextarea
              v-model="formState.geometry"
              :rows="5"
              placeholder="{ 'type': 'Polygon', 'coordinates': [...] }"
              class="w-full font-mono text-xs"
            />
            <template #help>
              <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                Format standar GeoJSON EPSG:4326 yang akan disimpan ke kolom PostGIS <code class="text-indigo-500">geometry</code>.
              </p>
            </template>
          </UFormField>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isFormModalOpen = false"
          />
          <UButton
            :label="isEditing ? 'Perbarui Kecamatan' : 'Simpan Kecamatan'"
            color="primary"
            :loading="submitting"
            @click="handleSaveForm"
          />
        </div>
      </template>
    </UModal>

    <!-- MODAL 2: KONFIRMASI HAPUS -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Hapus Batas Wilayah Kecamatan"
      description="Apakah Anda yakin ingin menghapus data wilayah kecamatan ini dari database PostGIS?"
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isDeleteModalOpen = false"
          />
          <UButton
            label="Hapus Permanen"
            color="error"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

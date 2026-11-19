<script setup lang="ts">
import { computed, ref } from "vue";
import type { GeoportalLayerItem } from "~/composables/useGeoportalLayers";

export type InspectorTab = "summary" | "standards" | "spatial" | "attributes";

const props = withDefaults(
  defineProps<{
    layer: GeoportalLayerItem;
    activeTab: InspectorTab;
    loadingInspector?: boolean;
    fullMetadata?: any;
    attributesData?: any;
    kugiFeatureInfo?: any;
    isMobile?: boolean;
  }>(),
  {
    loadingInspector: false,
    fullMetadata: null,
    attributesData: null,
    kugiFeatureInfo: null,
    isMobile: false,
  }
);

const emit = defineEmits<{
  (e: "update:activeTab", tab: InspectorTab): void;
  (e: "close"): void;
  (e: "refresh"): void;
  (e: "open-minimap"): void;
}>();

const config = useRuntimeConfig();
const pendingWebgisDataset = usePendingWebgisDataset();
const legendLoadError = ref(false);

const handleOpenInWebgis = () => {
  pendingWebgisDataset.value = {
    ...props.layer,
    pycsw: props.fullMetadata?.pycsw || props.layer.pycsw || null,
  };
  emit("close");
};
const attributeViewMode = ref<"schema" | "sample">("schema");
const attributeSearch = ref("");
const copiedKey = ref<string | null>(null);

const KUGI_ATTRIBUTE_DICTIONARY: Record<string, string> = {
  NAMOBJ: "Nama Objek Geospasial (KUGI BIG)",
  FCODE: "Kode Unsur Geospasial Indonesia (Feature Code BIG)",
  REMARK: "Catatan atau Keterangan Tambahan Unsur",
  METADATA: "Tautan atau Identifier Dokumen Metadata",
  SRS_ID: "Kode Sistem Referensi Spasial (SRGI 2013 / EPSG)",
  LCODE: "Kode Klasifikasi Layer Unsur KUGI",
  WADMKD: "Nama Wilayah Administrasi Kelurahan atau Desa",
  WADMKC: "Nama Wilayah Administrasi Kecamatan",
  WADMKK: "Nama Wilayah Administrasi Kabupaten atau Kota",
  WADMPR: "Nama Wilayah Administrasi Provinsi",
  WIADKC: "Kode Wilayah Administrasi Kecamatan",
  WIADKK: "Kode Wilayah Administrasi Kabupaten atau Kota",
  WIADPR: "Kode Wilayah Administrasi Provinsi",
  WIADKD: "Kode Wilayah Administrasi Kelurahan atau Desa",
  KDCPUM: "Kode Cakupan Pemerintahan Umum (Kemendagri / BPS)",
  KDEPUM: "Kode Desa Pemerintahan Umum",
  LUASWH: "Luas Wilayah Administrasi atau Poligon (Hektare / m²)",
  SHAPE_LENG: "Panjang Geometri Garis atau Keliling Poligon",
  SHAPE_LEN: "Panjang Geometri Garis atau Keliling Poligon",
  SHAPE_AREA: "Luas Geometri Poligon Spasial",
  PANJANG: "Panjang Ruas Infrastruktur (Meter / Kilometer)",
  LEBAR: "Lebar Ruas Penampang Infrastruktur (Meter)",
  KONDISI: "Klasifikasi Kondisi Fisik Infrastruktur",
  STATUS: "Status Kewenangan atau Penyelenggaraan Objek",
  TAHUN: "Tahun Survei atau Tahun Pelaksanaan Pembangunan",
  SUMBER: "Instansi Produsen atau Sumber Akuisisi Data",
};

const dcRecord = computed(() => props.fullMetadata?.pycsw?.dublin_core || null);
const isoRecord = computed(() => props.fullMetadata?.pycsw?.iso_19139 || null);

const formatDateFull = (isoDate?: string | null) => {
  if (!isoDate) return "Terverifikasi di Simpul JIGN";
  try {
    const d = new Date(isoDate);
    if (Number.isNaN(d.getTime())) return isoDate;
    return d.toLocaleDateString("id-ID", {
      day: "2-digit",
      month: "long",
      year: "numeric",
    });
  } catch {
    return isoDate;
  }
};

const activeBbox = computed(() => {
  const raw = isoRecord.value?.bbox || dcRecord.value?.bbox;
  const west = Number(raw?.west ?? 111.416);
  const east = Number(raw?.east ?? 112.152);
  const south = Number(raw?.south ?? -7.468);
  const north = Number(raw?.north ?? -6.985);

  const centerLat = (south + north) / 2;
  const centerLon = (west + east) / 2;
  const widthKm = Math.abs(east - west) * 111.32 * Math.cos((centerLat * Math.PI) / 180);
  const heightKm = Math.abs(north - south) * 110.57;

  return {
    west,
    east,
    south,
    north,
    centerLat,
    centerLon,
    widthKm,
    heightKm,
  };
});

const formatDms = (decimal: number, axis: "lat" | "lon") => {
  const abs = Math.abs(decimal);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = ((minFloat - min) * 60).toFixed(1);
  const dir = axis === "lat" ? (decimal < 0 ? "LS" : "LU") : decimal < 0 ? "BB" : "BT";
  return `${deg}° ${min}' ${sec}" ${dir}`;
};

const bboxWktString = computed(() => {
  const b = activeBbox.value;
  return `POLYGON((${b.west} ${b.south}, ${b.east} ${b.south}, ${b.east} ${b.north}, ${b.west} ${b.north}, ${b.west} ${b.south}))`;
});

const bboxGeoJsonString = computed(() => {
  const b = activeBbox.value;
  return JSON.stringify({
    type: "Feature",
    properties: {
      name: props.layer.name,
      layer_name: props.layer.layer_name,
      crs: "EPSG:4326",
    },
    geometry: {
      type: "Polygon",
      coordinates: [
        [
          [b.west, b.south],
          [b.east, b.south],
          [b.east, b.north],
          [b.west, b.north],
          [b.west, b.south],
        ],
      ],
    },
  });
});

const ogcEndpointsList = computed(() => {
  const layer = props.layer;
  const wmsBase = layer.url || "https://geoportal.bojonegorokab.go.id/geoserver/wms";
  const wfsBase = props.attributesData?.wfs_url || wmsBase.replace(/\/wms$/i, "/wfs");
  const id = encodeURIComponent(layer.geoportal_id || isoRecord.value?.file_identifier || layer.layer_name);

  return [
    {
      badge: "WMS 1.3.0",
      label: "Web Map Service (GetCapabilities)",
      url: `${wmsBase}?service=WMS&version=1.3.0&request=GetCapabilities`,
    },
    {
      badge: "WFS 2.0.0",
      label: "Web Feature Service (GeoJSON)",
      url: `${wfsBase}?service=WFS&version=1.0.0&request=GetFeature&typeName=${encodeURIComponent(layer.layer_name)}&maxFeatures=50&outputFormat=application/json`,
    },
    {
      badge: "CSW 2.0.2",
      label: "Catalog Service (ISO 19139 XML)",
      url: `https://geoportal.bojonegorokab.go.id/csw/?service=CSW&version=2.0.2&request=GetRecordById&id=${id}&outputSchema=http://www.isotc211.org/2005/gmd&elementSetName=full`,
    },
  ];
});

const getLocalXmlMirrorUrl = (schema: "iso" | "dc", download = false) => {
  const prefix = String(config.public.apiPrefix || "/api/v1").replace(/\/+$/, "");
  const configuredBase = String(config.public.apiBase || "").replace(/\/+$/, "");
  const isLocalConfigured = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i.test(configuredBase);

  let base = "";
  if (configuredBase && !isLocalConfigured) {
    base = configuredBase;
  } else if (import.meta.client && typeof window !== "undefined") {
    base = window.location.origin;
  }

  return `${base}${prefix}/layers/geoportal/${props.layer.id}/csw-xml?schema=${schema}${download ? "&download=1" : ""}`;
};

const nonGeometryAttributes = computed(() => {
  const list = props.attributesData?.attributes || [];
  return list.filter((a: any) => !a.is_geometry);
});

const getKugiAttributeDefinition = (colName: string) => {
  const upper = (colName || "").toUpperCase();
  if (props.kugiFeatureInfo?.attributes?.[upper]) {
    return props.kugiFeatureInfo.attributes[upper];
  }
  if (KUGI_ATTRIBUTE_DICTIONARY[upper]) {
    return KUGI_ATTRIBUTE_DICTIONARY[upper];
  }
  if (upper.startsWith("ID") || upper === "GID" || upper === "FID" || upper === "OBJECTID") {
    return "Identifikator Unik Rekaman Fitur Spasial (Primary Key)";
  }
  if (upper.includes("NAMA") || upper.includes("NAME")) {
    return "Atribut Penamaan Objek atau Entitas Spasial";
  }
  if (upper.includes("KEC")) {
    return "Atribut Wilayah Administrasi Tingkat Kecamatan";
  }
  if (upper.includes("DESA") || upper.includes("KEL")) {
    return "Atribut Wilayah Administrasi Tingkat Desa atau Kelurahan";
  }
  return "Atribut tematik spesifik dari skema wali data perangkat daerah";
};

const filteredSchemaAttributes = computed(() => {
  const q = attributeSearch.value.trim().toLowerCase();
  if (!q) return nonGeometryAttributes.value;
  return nonGeometryAttributes.value.filter(
    (a: any) =>
      a.name.toLowerCase().includes(q) ||
      getKugiAttributeDefinition(a.name).toLowerCase().includes(q)
  );
});

const sampleRows = computed<Record<string, any>[]>(() => props.attributesData?.sample_data || []);

const sampleColumns = computed(() => {
  const firstRow = sampleRows.value[0];
  if (firstRow) {
    return Object.keys(firstRow);
  }
  return nonGeometryAttributes.value.map((a: any) => a.name);
});

const getSampleValueForColumn = (colName: string) => {
  for (const row of sampleRows.value) {
    const val = row?.[colName];
    if (val !== null && val !== undefined && String(val).trim() !== "") {
      return String(val);
    }
  }
  return "-";
};

const formatCellVal = (val: any) => {
  if (val === null || val === undefined || val === "") return "-";
  if (typeof val === "number") return val.toLocaleString("id-ID");
  return String(val);
};

const copyText = async (text: string, key: string) => {
  try {
    await navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => {
      if (copiedKey.value === key) copiedKey.value = null;
    }, 2000);
  } catch {
    // Fallback ignored
  }
};

const tabs = computed(() => [
  { id: "summary" as const, label: props.isMobile ? "Ringkasan" : "Ringkasan & Layanan OGC", icon: "i-lucide-layers" },
  { id: "standards" as const, label: props.isMobile ? "ISO & Dublin Core" : "Metadata ISO 19139 & Dublin Core", icon: "i-lucide-file-badge" },
  { id: "spatial" as const, label: props.isMobile ? "Spasial & BBOX" : "Cakupan Spasial & BBOX", icon: "i-lucide-compass" },
  { id: "attributes" as const, label: props.isMobile ? "Atribut & KUGI" : "Skema Atribut WFS & KUGI BIG", icon: "i-lucide-table-properties", count: props.attributesData?.attributes_count },
]);
</script>

<template>
  <div
    class="flex flex-col flex-1 min-h-0 overflow-hidden font-sans bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100"
  >
    <!-- Header -->
    <div
      class="bg-white dark:bg-[#0c1522] border-b border-slate-200/80 dark:border-white/[0.08] shrink-0"
      :class="isMobile ? 'px-4 pt-2 pb-3' : 'px-6 py-4'"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300">
              {{ layer.category || "Umum" }}
            </span>
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-white/[0.06] text-slate-700 dark:text-slate-300">
              OGC {{ (layer.protocol || "WMS").toUpperCase() }}
            </span>
            <span
              v-if="kugiFeatureInfo?.code || attributesData?.detected_fcode"
              class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-800 dark:text-amber-300 tabular-nums"
            >
              KUGI: {{ kugiFeatureInfo?.code || attributesData?.detected_fcode }}
            </span>
          </div>

          <h2
            class="font-bold text-slate-900 dark:text-white tracking-tight leading-snug"
            :class="isMobile ? 'text-[17px] line-clamp-2' : 'text-xl truncate'"
          >
            {{ layer.name }}
          </h2>
          <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
            Wali Data:
            <strong class="font-semibold text-slate-800 dark:text-slate-200">
              {{ layer.opd_name || dcRecord?.publisher || isoRecord?.contact?.organisation || "Pemerintah Kabupaten Bojonegoro" }}
            </strong>
          </p>
        </div>

        <button
          type="button"
          aria-label="Tutup panel metadata"
          class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white transition-colors cursor-pointer shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
          @click="emit('close')"
        >
          <UIcon name="i-lucide-x" class="w-5 h-5" aria-hidden="true" />
        </button>
      </div>

      <!-- 4-Tab Navigation Bar -->
      <div
        data-vaul-no-drag
        class="mt-3 flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5"
        role="tablist"
      >
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.id"
          class="min-h-[40px] inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer shrink-0"
          :class="
            activeTab === tab.id
              ? 'bg-[#2B5B9E] text-white dark:bg-[#2B5B9E] dark:text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/[0.05] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          "
          @click="emit('update:activeTab', tab.id)"
        >
          <UIcon :name="tab.icon" class="w-3.5 h-3.5 shrink-0" />
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.count"
            class="px-1.5 py-0.5 rounded-full text-[10px] tabular-nums"
            :class="activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-[#2B5B9E]/10 text-[#2B5B9E] dark:text-sky-300'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>
    </div>

    <!-- Body (Scrollable) -->
    <div
      class="flex-1 overflow-y-auto overscroll-contain space-y-5"
      :class="isMobile ? 'p-4' : 'p-6'"
    >
      <!-- Loading Indicator -->
      <div
        v-if="loadingInspector && !fullMetadata && !attributesData"
        class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-10 text-center space-y-3"
      >
        <UIcon name="i-lucide-loader-2" class="w-7 h-7 text-[#2B5B9E] dark:text-sky-400 animate-spin mx-auto" />
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
          Mengambil rekaman metadata dari Simpul JIGN...
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Memuat katalog pyCSW 2.6.0, WFS DescribeFeatureType, dan kamus KUGI BIG.
        </p>
      </div>

      <!-- TAB 1: RINGKASAN & LAYANAN OGC -->
      <div v-if="activeTab === 'summary'" class="space-y-5">
        <div class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5">
          <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400 mb-2">
            Abstrak Dataset
          </h3>
          <p class="text-[13px] sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
            {{ dcRecord?.abstract || layer.description || "Dataset geospasial tematik yang dipublikasikan melalui Simpul Jaringan Informasi Geospasial Nasional (JIGN) Pemerintah Kabupaten Bojonegoro." }}
          </p>

          <div v-if="dcRecord?.subjects && dcRecord.subjects.length > 0" class="mt-4 pt-4 border-t border-slate-200/70 dark:border-white/[0.06]">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
              Kata Kunci Tematik
            </p>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="kw in dcRecord.subjects"
                :key="kw"
                class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-white/[0.05] text-xs font-medium text-slate-700 dark:text-slate-300"
              >
                {{ kw }}
              </span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <div class="lg:col-span-8 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5">
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400 mb-4">
              Identitas &amp; Kepemilikan Data
            </h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-xs">
              <div class="pb-3 border-b border-slate-200/70 dark:border-white/[0.06]">
                <dt class="text-slate-500 dark:text-slate-400 mb-1">Produsen / Wali Data OPD</dt>
                <dd class="font-semibold text-slate-900 dark:text-white">
                  {{ layer.opd_name || dcRecord?.publisher || isoRecord?.contact?.organisation || "Pemerintah Kabupaten Bojonegoro" }}
                </dd>
              </div>
              <div class="pb-3 border-b border-slate-200/70 dark:border-white/[0.06]">
                <dt class="text-slate-500 dark:text-slate-400 mb-1">Kategori Urusan</dt>
                <dd class="font-semibold text-slate-900 dark:text-white">
                  {{ layer.category || isoRecord?.topic_category || "Geospasial Daerah" }}
                </dd>
              </div>
              <div class="pb-3 border-b border-slate-200/70 dark:border-white/[0.06]">
                <dt class="text-slate-500 dark:text-slate-400 mb-1">Nama Teknis Layer</dt>
                <dd class="font-mono text-[11px] font-medium text-slate-900 dark:text-white break-all">
                  {{ layer.layer_name }}
                </dd>
              </div>
              <div class="pb-3 border-b border-slate-200/70 dark:border-white/[0.06]">
                <dt class="text-slate-500 dark:text-slate-400 mb-1">Identifier Katalog JIGN</dt>
                <dd class="font-mono text-[11px] font-medium text-slate-900 dark:text-white break-all">
                  {{ layer.geoportal_id || isoRecord?.file_identifier || dcRecord?.identifier || layer.layer_name }}
                </dd>
              </div>
              <div class="pb-3 sm:pb-0 border-b sm:border-b-0 border-slate-200/70 dark:border-white/[0.06]">
                <dt class="text-slate-500 dark:text-slate-400 mb-1">Sistem Referensi Koordinat</dt>
                <dd class="font-semibold text-[#2B5B9E] dark:text-sky-400 tabular-nums">
                  {{ isoRecord?.crs || dcRecord?.crs || "EPSG:4326 (WGS 84 / SRGI 2013)" }}
                </dd>
              </div>
              <div>
                <dt class="text-slate-500 dark:text-slate-400 mb-1">Sinkronisasi Terakhir</dt>
                <dd class="font-semibold text-slate-900 dark:text-white tabular-nums">
                  {{ formatDateFull(layer.last_synced_at || isoRecord?.date_stamp || dcRecord?.date) }}
                </dd>
              </div>
            </dl>
          </div>

          <div class="lg:col-span-4 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 flex flex-col justify-between">
            <div>
              <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400 mb-1.5">
                Simbologi Legenda WMS
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Representasi kartografi resmi GeoServer JIGN.
              </p>
            </div>

            <div class="flex-1 flex items-center justify-center rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] p-4 min-h-[120px]">
              <img
                v-if="layer.legend_url && !legendLoadError"
                :src="layer.legend_url"
                :alt="`Legenda ${layer.name}`"
                class="max-h-36 object-contain rounded bg-white p-2 shadow-xs"
                @error="legendLoadError = true"
              />
              <div v-else class="text-center text-xs text-slate-400 dark:text-slate-500">
                <UIcon name="i-lucide-image-off" class="w-5 h-5 mx-auto mb-1 opacity-60" />
                <span>Pratinjau simbol tersedia saat layer dimuat di peta</span>
              </div>
            </div>
          </div>
        </div>

        <!-- OGC Endpoints -->
        <div class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 space-y-4">
          <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400">
              Titik Akses Layanan OGC (Endpoints)
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Hubungkan dataset ke QGIS, ArcGIS, atau aplikasi WebGIS eksternal melalui URL berikut.
            </p>
          </div>

          <div class="space-y-2.5">
            <div
              v-for="ep in ogcEndpointsList"
              :key="ep.label"
              class="rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300">
                    {{ ep.badge }}
                  </span>
                  <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                    {{ ep.label }}
                  </span>
                </div>
                <p class="font-mono text-[11px] text-slate-500 dark:text-slate-400 break-all">
                  {{ ep.url }}
                </p>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <button
                  type="button"
                  class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] hover:bg-[#2B5B9E] hover:text-white hover:border-[#2B5B9E] text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                  @click="copyText(ep.url, ep.label)"
                >
                  <UIcon :name="copiedKey === ep.label ? 'i-lucide-check' : 'i-lucide-copy'" class="w-3.5 h-3.5" />
                  <span>{{ copiedKey === ep.label ? "Tersalin" : "Salin URL" }}</span>
                </button>

                <a
                  :href="ep.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  :aria-label="`Buka ${ep.label} di tab baru`"
                  class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-slate-600 dark:text-slate-300 hover:text-[#2B5B9E] dark:hover:text-sky-400 transition-colors"
                >
                  <UIcon name="i-lucide-external-link" class="w-4 h-4" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: METADATA STANDAR ISO 19139 & DUBLIN CORE -->
      <div v-else-if="activeTab === 'standards'" class="space-y-5">
        <div class="rounded-2xl bg-slate-100/90 dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
              <span class="text-xs font-bold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400">
                Katalog Standar OGC CSW 2.0.2 (pyCSW 2.6.0)
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400">
              Dokumen metadata XML resmi sesuai Peraturan BIG tentang Standar Metadata Spasial Nasional.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <a
              :href="getLocalXmlMirrorUrl('iso', false)"
              target="_blank"
              rel="noopener noreferrer"
              class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] border border-slate-200/80 dark:border-white/[0.08] text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-[#2B5B9E] hover:text-white hover:border-[#2B5B9E] transition-colors"
            >
              <UIcon name="i-lucide-file-code" class="w-4 h-4" />
              <span>Lihat XML ISO</span>
            </a>
            <a
              :href="getLocalXmlMirrorUrl('iso', true)"
              class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-xs font-semibold text-white transition-colors"
            >
              <UIcon name="i-lucide-download" class="w-4 h-4" />
              <span>Unduh ISO 19139 (.xml)</span>
            </a>
            <a
              :href="getLocalXmlMirrorUrl('dc', true)"
              class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] border border-slate-200/80 dark:border-white/[0.08] text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-900 hover:text-white transition-colors"
            >
              <UIcon name="i-lucide-download" class="w-4 h-4" />
              <span>Unduh Dublin Core (.xml)</span>
            </a>
          </div>
        </div>

        <!-- ISO 19115 / 19139 -->
        <div class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5">
          <div class="flex items-center justify-between gap-2 pb-4 mb-5 border-b border-slate-200/70 dark:border-white/[0.06]">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                1. Skema ISO 19115 / ISO 19139
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Profil metadata geospasial nasional untuk pertukaran data antar Simpul Jaringan JIGN.
              </p>
            </div>
            <span class="px-2.5 py-1 rounded-lg bg-[#2B5B9E]/10 text-[#2B5B9E] dark:bg-sky-500/15 dark:text-sky-300 text-[11px] font-semibold shrink-0">
              SNI ISO 19115
            </span>
          </div>

          <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">File Identifier</dt>
              <dd class="font-mono text-[11px] font-semibold text-slate-900 dark:text-white break-all">
                {{ isoRecord?.file_identifier || layer.geoportal_id || layer.layer_name }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Tanggal Publikasi</dt>
              <dd class="font-semibold text-slate-900 dark:text-white tabular-nums">
                {{ isoRecord?.date_stamp || dcRecord?.date || formatDateFull(layer.last_synced_at) }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Kategori Topik ISO</dt>
              <dd class="font-semibold text-slate-900 dark:text-white capitalize">
                {{ isoRecord?.topic_category || layer.category || "geoscientificInformation / planningCadastre" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Sistem Referensi Spasial</dt>
              <dd class="font-semibold text-[#2B5B9E] dark:text-sky-400 tabular-nums">
                {{ isoRecord?.crs || dcRecord?.crs || "EPSG:4326 (WGS 84)" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Skala Resolusi Spasial</dt>
              <dd class="font-semibold text-slate-900 dark:text-white tabular-nums">
                {{ isoRecord?.scale_denominator ? `1 : ${Number(isoRecord.scale_denominator).toLocaleString("id-ID")}` : "1 : 25.000 (Skala Daerah)" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Bahasa Metadata</dt>
              <dd class="font-semibold text-slate-900 dark:text-white uppercase">
                {{ isoRecord?.language || "ind / eng (Bahasa Indonesia)" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Organisasi Penanggung Jawab</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ isoRecord?.contact?.organisation || layer.opd_name || "Pemerintah Kab. Bojonegoro" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Walidata Teknis</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ isoRecord?.contact?.individual || dcRecord?.creator || "Tim Simpul Jaringan Geospasial Bojonegoro" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">Peran Kontak</dt>
              <dd class="font-semibold text-slate-900 dark:text-white capitalize">
                {{ isoRecord?.contact?.role || "pointOfContact / custodian" }}
              </dd>
            </div>
          </dl>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                Silsilah &amp; Kualitas Data (Lineage)
              </p>
              <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                {{ isoRecord?.lineage || "Data diproduksi dan diverifikasi oleh Wali Data Perangkat Daerah Kabupaten Bojonegoro serta disinkronisasikan melalui infrastruktur GeoServer & pyCSW JIGN." }}
              </p>
            </div>
            <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                Informasi Tambahan
              </p>
              <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                {{ isoRecord?.supplemental_info || "Dapat diakses publik sesuai kebijakan Satu Data Indonesia dan Keterbukaan Informasi Geospasial Kabupaten Bojonegoro." }}
              </p>
            </div>
          </div>
        </div>

        <!-- Dublin Core -->
        <div class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5">
          <div class="flex items-center justify-between gap-2 pb-4 mb-5 border-b border-slate-200/70 dark:border-white/[0.06]">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                2. Skema Dublin Core (csw:Record)
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Elemen metadata inti dari katalog OGC CSW 2.0.2 untuk penemuan lintas portal.
              </p>
            </div>
            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-white/[0.06] text-slate-700 dark:text-slate-300 text-[11px] font-semibold shrink-0">
              CSW 2.0.2
            </span>
          </div>

          <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:identifier</dt>
              <dd class="font-mono text-[11px] font-semibold text-slate-900 dark:text-white break-all">
                {{ dcRecord?.identifier || layer.geoportal_id || layer.layer_name }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:title</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ dcRecord?.title || layer.name }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:type</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ dcRecord?.type || "dataset / geospatial-vector" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:publisher</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ dcRecord?.publisher || layer.opd_name || "Pemerintah Kabupaten Bojonegoro" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:creator</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ dcRecord?.creator || layer.opd_name || "Simpul JIGN Kabupaten Bojonegoro" }}
              </dd>
            </div>
            <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:rights (Lisensi)</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ dcRecord?.rights || "Terbuka untuk Publik (Satu Data Indonesia)" }}
              </dd>
            </div>
          </dl>

          <div v-if="dcRecord?.references && dcRecord.references.length > 0" class="mt-5 pt-4 border-t border-slate-200/70 dark:border-white/[0.06]">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
              Referensi Skema Terkait
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <a
                v-for="(refItem, idx) in dcRecord.references"
                :key="idx"
                :href="refItem.url"
                target="_blank"
                rel="noopener noreferrer"
                class="p-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] hover:bg-slate-200/60 dark:hover:bg-white/[0.07] flex items-center justify-between gap-3 transition-colors"
              >
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-[#2B5B9E] dark:text-sky-400 truncate">
                    {{ refItem.scheme || "OGC Reference" }}
                  </p>
                  <p class="font-mono text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    {{ refItem.url }}
                  </p>
                </div>
                <UIcon name="i-lucide-external-link" class="w-4 h-4 text-slate-400 shrink-0" />
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: CAKUPAN SPASIAL & BBOX -->
      <div v-else-if="activeTab === 'spatial'" class="space-y-5">
        <div class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5">
          <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-5 border-b border-slate-200/70 dark:border-white/[0.06]">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Batas Geografis Dataset (WGS 84)
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Koordinat cakupan ekstrem dalam sistem proyeksi EPSG:4326.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-white/[0.05] hover:bg-[#2B5B9E] hover:text-white text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                @click="copyText(bboxWktString, 'WKT BBOX')"
              >
                <UIcon :name="copiedKey === 'WKT BBOX' ? 'i-lucide-check' : 'i-lucide-copy'" class="w-3.5 h-3.5" />
                <span>{{ copiedKey === "WKT BBOX" ? "WKT Tersalin" : "Salin WKT" }}</span>
              </button>
              <button
                type="button"
                class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-white/[0.05] hover:bg-[#2B5B9E] hover:text-white text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                @click="copyText(bboxGeoJsonString, 'GeoJSON BBOX')"
              >
                <UIcon :name="copiedKey === 'GeoJSON BBOX' ? 'i-lucide-check' : 'i-lucide-copy'" class="w-3.5 h-3.5" />
                <span>{{ copiedKey === "GeoJSON BBOX" ? "GeoJSON Tersalin" : "Salin GeoJSON" }}</span>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Batas Barat
              </span>
              <p class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                {{ activeBbox.west.toFixed(6) }}° BT
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                {{ formatDms(activeBbox.west, "lon") }}
              </p>
            </div>
            <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Batas Timur
              </span>
              <p class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                {{ activeBbox.east.toFixed(6) }}° BT
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                {{ formatDms(activeBbox.east, "lon") }}
              </p>
            </div>
            <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Batas Selatan
              </span>
              <p class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                {{ activeBbox.south.toFixed(6) }}° LS
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                {{ formatDms(activeBbox.south, "lat") }}
              </p>
            </div>
            <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Batas Utara
              </span>
              <p class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                {{ activeBbox.north.toFixed(6) }}° LS
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                {{ formatDms(activeBbox.north, "lat") }}
              </p>
            </div>
          </div>

          <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4 pt-5 border-t border-slate-200/70 dark:border-white/[0.06]">
            <div>
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Titik Pusat (Centroid)
              </span>
              <p class="text-sm font-bold text-[#2B5B9E] dark:text-sky-400 tabular-nums">
                {{ activeBbox.centerLat.toFixed(5) }}°, {{ activeBbox.centerLon.toFixed(5) }}°
              </p>
            </div>
            <div>
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Bentang Barat – Timur
              </span>
              <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">
                ± {{ activeBbox.widthKm.toFixed(2) }} km
              </p>
            </div>
            <div>
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                Bentang Utara – Selatan
              </span>
              <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">
                ± {{ activeBbox.heightKm.toFixed(2) }} km
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 4: SKEMA ATRIBUT WFS & KUGI BIG -->
      <div v-else-if="activeTab === 'attributes'" class="space-y-5">
        <div class="rounded-2xl bg-slate-100/90 dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
                <span class="text-xs font-bold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400">
                  Katalog Unsur Geospasial Indonesia (KUGI v5 · BIG)
                </span>
              </div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">
                {{ kugiFeatureInfo?.typeName || layer.name }}
              </h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 max-w-3xl">
                {{ kugiFeatureInfo?.definition || "Skema atribut diekstrak melalui layanan OGC Web Feature Service (WFS) Simpul JIGN Kabupaten Bojonegoro." }}
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
              <span
                v-if="kugiFeatureInfo?.code || attributesData?.detected_fcode"
                class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#132033] font-bold text-[#2B5B9E] dark:text-sky-400 tabular-nums"
              >
                FCODE: {{ kugiFeatureInfo?.code || attributesData?.detected_fcode }}
              </span>
              <span
                v-if="attributesData?.geom_type"
                class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#132033] font-semibold text-slate-700 dark:text-slate-200"
              >
                Geometri: {{ attributesData.geom_type }}
              </span>
              <span
                v-if="attributesData?.total_features !== null && attributesData?.total_features !== undefined"
                class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#132033] font-semibold text-slate-700 dark:text-slate-200 tabular-nums"
              >
                Total Objek: {{ Number(attributesData.total_features).toLocaleString("id-ID") }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="inline-flex items-center gap-1.5 p-1 rounded-xl bg-slate-200/70 dark:bg-[#0c1522]">
            <button
              type="button"
              class="min-h-[40px] px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
              :class="
                attributeViewMode === 'schema'
                  ? 'bg-white dark:bg-[#2B5B9E] text-slate-900 dark:text-white shadow-xs'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              "
              @click="attributeViewMode = 'schema'"
            >
              Skema Atribut ({{ nonGeometryAttributes.length }})
            </button>
            <button
              type="button"
              class="min-h-[40px] px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
              :class="
                attributeViewMode === 'sample'
                  ? 'bg-white dark:bg-[#2B5B9E] text-slate-900 dark:text-white shadow-xs'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              "
              @click="attributeViewMode = 'sample'"
            >
              Sampel Data WFS ({{ sampleRows.length }})
            </button>
          </div>

          <div v-if="attributeViewMode === 'schema'" class="relative w-full sm:w-72">
            <UIcon
              name="i-lucide-search"
              class="w-3.5 h-3.5 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
            />
            <input
              v-model="attributeSearch"
              type="search"
              placeholder="Cari nama kolom atribut..."
              class="w-full min-h-[40px] pl-9 pr-4 py-1.5 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#2B5B9E]/30"
            />
          </div>
        </div>

        <div
          v-if="attributeViewMode === 'schema'"
          class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] overflow-hidden"
        >
          <div v-if="filteredSchemaAttributes.length > 0" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-[#F7F8F5] dark:bg-white/[0.04] text-slate-600 dark:text-slate-300">
                  <th class="py-3 px-4 font-semibold">Nama Atribut</th>
                  <th class="py-3 px-4 font-semibold">Definisi KUGI / Deskripsi</th>
                  <th class="py-3 px-4 font-semibold">Tipe Data</th>
                  <th class="py-3 px-4 font-semibold">Contoh Nilai</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200/70 dark:divide-white/[0.06]">
                <tr
                  v-for="attr in filteredSchemaAttributes"
                  :key="attr.name"
                  class="hover:bg-[#F7F8F5] dark:hover:bg-white/[0.02]"
                >
                  <td class="py-3 px-4 font-mono font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                    {{ attr.name }}
                  </td>
                  <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                    {{ getKugiAttributeDefinition(attr.name) }}
                  </td>
                  <td class="py-3 px-4 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-white/[0.06] text-slate-700 dark:text-slate-300 font-mono text-[11px]">
                      {{ attr.local_type || attr.type }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-slate-600 dark:text-slate-400 tabular-nums max-w-xs truncate">
                    {{ getSampleValueForColumn(attr.name) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
            Skema atribut WFS sedang dimuat atau tidak ada kolom yang cocok.
          </div>
        </div>

        <div
          v-else
          class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] overflow-hidden"
        >
          <div v-if="sampleRows.length > 0" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-[#F7F8F5] dark:bg-white/[0.04] text-slate-600 dark:text-slate-300">
                  <th class="py-3 px-4 font-semibold whitespace-nowrap">#</th>
                  <th
                    v-for="col in sampleColumns"
                    :key="col"
                    class="py-3 px-4 font-semibold whitespace-nowrap"
                  >
                    {{ col }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200/70 dark:divide-white/[0.06]">
                <tr
                  v-for="(row, rIdx) in sampleRows"
                  :key="rIdx"
                  class="hover:bg-[#F7F8F5] dark:hover:bg-white/[0.02]"
                >
                  <td class="py-3 px-4 font-semibold text-slate-400 tabular-nums">
                    {{ rIdx + 1 }}
                  </td>
                  <td
                    v-for="col in sampleColumns"
                    :key="col"
                    class="py-3 px-4 text-slate-700 dark:text-slate-300 whitespace-nowrap max-w-[240px] truncate tabular-nums"
                  >
                    {{ formatCellVal(row[col]) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
            Sampel fitur WFS belum tersedia untuk layer ini.
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Actions -->
    <div
      class="bg-white dark:bg-[#0c1522] border-t border-slate-200/80 dark:border-white/[0.08] flex items-center justify-between gap-2 shrink-0"
      :class="isMobile ? 'px-4 pt-3 pb-[max(0.875rem,env(safe-area-inset-bottom))]' : 'px-6 py-3.5'"
    >
      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          :disabled="loadingInspector"
          :aria-label="isMobile ? 'Segarkan metadata server' : undefined"
          :title="isMobile ? 'Segarkan Metadata Server' : undefined"
          class="min-h-[44px] inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
          :class="isMobile ? 'w-[44px] px-0' : 'px-3.5 py-2'"
          @click="emit('refresh')"
        >
          <UIcon
            name="i-lucide-refresh-cw"
            class="w-4 h-4 shrink-0"
            :class="{ 'animate-spin': loadingInspector }"
            aria-hidden="true"
          />
          <span v-if="!isMobile">Segarkan Metadata</span>
        </button>

        <button
          type="button"
          class="min-h-[44px] inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.06] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
          @click="emit('open-minimap')"
        >
          <UIcon name="i-lucide-map-pinned" class="w-4 h-4 shrink-0 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
          <span>{{ isMobile ? "Minimap" : "Pratinjau Minimap" }}</span>
        </button>
      </div>

      <div class="flex items-center gap-2" :class="isMobile ? 'flex-1 min-w-0' : 'ml-auto'">
        <button
          v-if="!isMobile"
          type="button"
          class="min-h-[44px] px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-white/[0.05] dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
          @click="emit('close')"
        >
          Tutup
        </button>

        <NuxtLink
          :to="buildWebgisDatasetRoute(layer)"
          class="min-h-[44px] inline-flex items-center justify-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] active:bg-[#1C3D6E] text-white text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B5B9E] dark:focus-visible:outline-sky-400"
          :class="isMobile ? 'flex-1 min-w-0 truncate' : ''"
          @click="handleOpenInWebgis"
        >
          <UIcon name="i-lucide-map" class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span class="truncate">Buka di Peta WebGIS</span>
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

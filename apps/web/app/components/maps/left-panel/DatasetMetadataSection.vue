<script setup lang="ts">
import { ref, computed, watch } from "vue";
import {
  useGeoportalLayers,
  type GeoportalLayerItem,
  type LayerAttributesData,
  type PycswMetadataRecord,
} from "~/composables/useGeoportalLayers";

const props = withDefaults(
  defineProps<{
    layer?: GeoportalLayerItem | null;
    isActive?: boolean;
  }>(),
  {
    layer: null,
    isActive: false,
  }
);

const emit = defineEmits<{
  (e: "toggle"): void;
  (e: "zoom"): void;
  (e: "open-modal"): void;
}>();

const config = useRuntimeConfig();
const { fetchLayerMetadata, fetchLayerAttributes } = useGeoportalLayers();

type MetadataTab = "overview" | "attributes" | "csw";
const activeTab = ref<MetadataTab>("overview");

const loadingMeta = ref(false);
const loadingAttrs = ref(false);
const metadataDetail = ref<Record<string, any> | null>(null);
const attributesData = ref<LayerAttributesData | null>(null);
const attrSearch = ref("");
const attrViewMode = ref<"schema" | "sample">("schema");
const copiedField = ref<string | null>(null);

export interface KugiMeta {
  fcode: string;
  typeName?: string;
  aliases?: string;
  definition?: string;
  producer?: string;
  category?: string;
  attributes: Record<string, string>;
}

const kugiCache = new Map<string, KugiMeta>();

const defaultKugiDictionary: Record<string, string> = {
  FCODE: "Kode Unsur Geografi Indonesia (BIG)",
  NAMOBJ: "Nama Objek Unsur Geospasial",
  REMARK: "Catatan Khusus / Deskripsi Tambahan",
  METADATA: "Nomor / Identifikasi Rekaman Metadata",
  SRS_ID: "Sistem Referensi Spasial (CRS/SRS)",
  KDBBPS: "Kode Blok Sensus BPS",
  KDCBPS: "Kode Kecamatan BPS",
  KDCPUM: "Kode Kecamatan Kemendagri",
  KDEBPS: "Kode Desa / Kelurahan BPS",
  KDEPUM: "Kode Desa / Kelurahan Kemendagri",
  KDPBPS: "Kode Provinsi BPS",
  KDPKAB: "Kode Kabupaten / Kota Kemendagri",
  KDPPUM: "Kode Provinsi Kemendagri",
  LUASWH: "Luas Wilayah Administrasi (Hektar / m²)",
  TIPADM: "Tipe Wilayah Administrasi",
  WADMKC: "Nama Wilayah Administrasi Kecamatan",
  WADMKD: "Nama Wilayah Administrasi Kelurahan / Desa",
  WADMKK: "Nama Wilayah Administrasi Kabupaten / Kota",
  WADMPR: "Nama Wilayah Administrasi Provinsi",
  WIADKC: "Wilayah Induk Administrasi Kecamatan",
  WIADKK: "Wilayah Induk Administrasi Kabupaten / Kota",
  WIADPR: "Wilayah Induk Administrasi Provinsi",
  WIADKD: "Wilayah Induk Administrasi Kelurahan / Desa",
  UUPP: "Dasar Hukum / Peraturan Pembentukan",
  STATUS_BAT: "Status Penegasan Batas Wilayah",
  "SHAPE.AREA": "Luas Poligon Geometri (Satuan Proyeksi)",
  SHAPE_AREA: "Luas Poligon Geometri (Satuan Proyeksi)",
  "SHAPE.LEN": "Panjang Garis / Keliling Geometri",
  SHAPE_LENGTH: "Panjang Garis / Keliling Geometri",
  SHAPE_LEN: "Panjang Garis / Keliling Geometri",
  GEOM: "Bentuk Spasial Geometri Unsur",
  GEOMETRY: "Bentuk Spasial Geometri Unsur",
  OBJECTID: "Nomor Indeks Unik Objek Geodatabase",
  FID: "Feature Identifier (FID)",
  ID: "Pengidentifikasi Unik Fitur",
  KONDISI: "Status / Kondisi Fisik Infrastruktur",
  LEBAR: "Lebar Penampang Ruas (Meter)",
  PANJANG: "Panjang Segmen / Ruas Jalan (Meter)",
  PERKERASAN: "Jenis Konstruksi Perkerasan",
};

const currentFcode = computed<string | null>(() => {
  if (attributesData.value?.detected_fcode) {
    return attributesData.value.detected_fcode;
  }
  const rows = attributesData.value?.sample_data || [];
  for (const row of rows) {
    for (const [k, v] of Object.entries(row)) {
      if (k.toUpperCase() === "FCODE" && v) {
        const val = String(v).trim();
        if (val && val !== "-") return val;
      }
    }
  }
  return null;
});

const activeKugiMeta = ref<KugiMeta | null>(null);
const isLoadingKugi = ref(false);

watch(
  currentFcode,
  async (fcode) => {
    if (!fcode) {
      activeKugiMeta.value = null;
      return;
    }

    const upperCode = fcode.toUpperCase();
    if (kugiCache.has(upperCode)) {
      activeKugiMeta.value = kugiCache.get(upperCode) || null;
      return;
    }

    isLoadingKugi.value = true;
    try {
      const res = await $http<{ ok: boolean; code: string; data: any[] }>(
        "kugi/feature-type",
        { query: { code: upperCode } }
      );

      const items = Array.isArray(res?.data) ? res.data : [];
      if (items.length > 0) {
        const first = items[0];
        const attributes: Record<string, string> = {};

        for (const item of items) {
          if (item?.faCode && item?.ptDefinition) {
            attributes[String(item.faCode).toUpperCase()] = String(item.ptDefinition).trim();
          }
        }

        const meta: KugiMeta = {
          fcode: upperCode,
          typeName: first.typeName || undefined,
          aliases: first.aliases || undefined,
          definition: first.definition || undefined,
          producer: first.fcProducer || undefined,
          category: first.featureCatalogue || first.fcName || undefined,
          attributes,
        };

        kugiCache.set(upperCode, meta);
        activeKugiMeta.value = meta;
      } else {
        activeKugiMeta.value = null;
      }
    } catch {
      activeKugiMeta.value = null;
    } finally {
      isLoadingKugi.value = false;
    }
  },
  { immediate: true }
);

function resolveKugiLabel(name: string): { label: string; isKugi: boolean } {
  const upper = name.toUpperCase();
  if (activeKugiMeta.value?.attributes[upper]) {
    return {
      label: activeKugiMeta.value.attributes[upper],
      isKugi: true,
    };
  }
  if (defaultKugiDictionary[upper]) {
    return {
      label: defaultKugiDictionary[upper],
      isKugi: true,
    };
  }
  return {
    label: name,
    isKugi: false,
  };
}

function copyText(text: string, fieldId: string) {
  if (!text) return;
  if (import.meta.client && navigator?.clipboard) {
    navigator.clipboard.writeText(text);
    copiedField.value = fieldId;
    setTimeout(() => {
      if (copiedField.value === fieldId) {
        copiedField.value = null;
      }
    }, 2000);
  }
}

function getDirectCswUrl(schema: "iso" | "dc"): string {
  const cswBase = pycsw.value?.csw_url || "https://geoportal.bojonegorokab.go.id/csw/";
  const id = props.layer?.geoportal_id || pycsw.value?.identifier || "";
  const params = new URLSearchParams({
    service: "CSW",
    version: "2.0.2",
    request: "GetRecordById",
    id,
    elementSetName: "full",
  });
  if (schema === "iso") {
    params.set("outputSchema", "http://www.isotc211.org/2005/gmd");
  }
  return `${cswBase.replace(/\/+$/, "")}/?${params.toString()}`;
}

function getMelarosaXmlUrl(schema: "iso" | "dc", download = false): string {
  if (!props.layer?.id) return "";
  const base = String(config.public.apiBase || "http://localhost:9000").replace(/\/+$/, "");
  const prefix = config.public.apiPrefix || "/api/v1";
  return `${base}${prefix}/layers/geoportal/${props.layer.id}/csw-xml?schema=${schema}${download ? "&download=1" : ""}`;
}

function openDirectCswUrl(schema: "iso" | "dc") {
  const url = getDirectCswUrl(schema);
  if (url && import.meta.client) window.open(url, "_blank", "noopener,noreferrer");
}

function openMelarosaXmlUrl(schema: "iso" | "dc") {
  const url = getMelarosaXmlUrl(schema, false);
  if (url && import.meta.client) window.open(url, "_blank", "noopener,noreferrer");
}

function downloadXml(schema: "iso" | "dc") {
  const url = getMelarosaXmlUrl(schema, true);
  if (!url || !import.meta.client) return;
  const link = document.createElement("a");
  link.href = url;
  link.setAttribute("download", "");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

async function loadData(refresh = false) {
  if (!props.layer?.id) return;

  loadingMeta.value = true;
  loadingAttrs.value = true;

  try {
    const metaRes = await fetchLayerMetadata(props.layer.id, refresh);
    if (metaRes?.data) {
      metadataDetail.value = metaRes.data;
    }
  } catch (e) {
    console.warn("Gagal memuat metadata layer:", e);
  } finally {
    loadingMeta.value = false;
  }

  try {
    const attrRes = await fetchLayerAttributes(props.layer.id, refresh);
    if (attrRes?.data) {
      attributesData.value = attrRes.data;
    }
  } catch (e) {
    console.warn("Gagal memuat atribut layer:", e);
  } finally {
    loadingAttrs.value = false;
  }
}

watch(
  () => props.layer?.id,
  (id) => {
    if (id) {
      loadData();
    }
  },
  { immediate: true }
);

const filteredAttributes = computed(() => {
  const list = attributesData.value?.attributes || [];
  const q = attrSearch.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter((a) => {
    const kugiLabel = resolveKugiLabel(a.name).label.toLowerCase();
    return (
      a.name.toLowerCase().includes(q) ||
      a.type.toLowerCase().includes(q) ||
      a.local_type.toLowerCase().includes(q) ||
      kugiLabel.includes(q)
    );
  });
});

const sampleColumns = computed(() => {
  const rows = attributesData.value?.sample_data || [];
  if (rows.length === 0) return [];
  return Object.keys(rows[0]).filter((k) => k !== "geom" && k !== "geometry");
});

const pycsw = computed<PycswMetadataRecord | null>(() => {
  return metadataDetail.value?.pycsw || null;
});

const dcRecord = computed(() => pycsw.value?.dublin_core || null);
const isoRecord = computed(() => pycsw.value?.iso_19139 || null);

function formatCoord(val?: number | null, precision = 6): string {
  if (val === null || val === undefined || isNaN(val)) return "-";
  return Number(val).toFixed(precision);
}

function formatDms(val?: number | null, isLat = true): string {
  if (val === null || val === undefined || isNaN(val)) return "-";
  const abs = Math.abs(val);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = ((minFloat - min) * 60).toFixed(1);
  const dir = isLat ? (val >= 0 ? "LU" : "LS") : (val >= 0 ? "BT" : "BB");
  return `${deg}° ${String(min).padStart(2, "0")}' ${sec}" ${dir}`;
}

const spatialExtent = computed(() => {
  const isoBbox = isoRecord.value?.bbox;
  const dcBbox = dcRecord.value?.bbox;

  const west = isoBbox?.west ?? dcBbox?.west ?? (dcBbox?.lower_corner ? Number(dcBbox.lower_corner.split(" ")[0]) : null) ?? 111.445102;
  const east = isoBbox?.east ?? dcBbox?.east ?? (dcBbox?.upper_corner ? Number(dcBbox.upper_corner.split(" ")[0]) : null) ?? 112.163338;
  const south = isoBbox?.south ?? dcBbox?.south ?? (dcBbox?.lower_corner ? Number(dcBbox.lower_corner.split(" ")[1]) : null) ?? -7.451683;
  const north = isoBbox?.north ?? dcBbox?.north ?? (dcBbox?.upper_corner ? Number(dcBbox.upper_corner.split(" ")[1]) : null) ?? -6.981707;

  const centerLon = isoBbox?.center?.[0] ?? Number(((west + east) / 2).toFixed(6));
  const centerLat = isoBbox?.center?.[1] ?? Number(((south + north) / 2).toFixed(6));

  const spanLonDeg = Number(Math.abs(east - west).toFixed(6));
  const spanLatDeg = Number(Math.abs(north - south).toFixed(6));

  const spanLonKm = (spanLonDeg * 110.5).toFixed(1);
  const spanLatKm = (spanLatDeg * 110.6).toFixed(1);

  const crs = isoRecord.value?.crs || dcRecord.value?.crs || "EPSG:4326";
  const scale = isoRecord.value?.scale_denominator
    ? `1:${isoRecord.value.scale_denominator.toLocaleString("id-ID")}`
    : "1:5.000";

  const wktBbox = `BBOX (${west.toFixed(6)}, ${south.toFixed(6)}, ${east.toFixed(6)}, ${north.toFixed(6)})`;
  const geojsonBbox = `[${west.toFixed(6)}, ${south.toFixed(6)}, ${east.toFixed(6)}, ${north.toFixed(6)}]`;
  const centerStr = `[${centerLon.toFixed(6)}, ${centerLat.toFixed(6)}]`;

  return {
    west,
    east,
    south,
    north,
    centerLon,
    centerLat,
    centerStr,
    spanLonDeg,
    spanLatDeg,
    spanLonKm,
    spanLatKm,
    crs,
    scale,
    wktBbox,
    geojsonBbox,
  };
});
</script>

<template>
  <div v-if="props.layer" class="space-y-4 pt-1">
    <!-- Segmented Navigation Pill Tabs (Radius 100% Sesuai DESIGN.md) -->
    <div class="flex items-center justify-between gap-1.5 p-1 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 overflow-x-auto no-scrollbar">
      <button
        type="button"
        class="flex-1 py-1.5 px-2.5 rounded-full text-[11px] font-semibold transition-all cursor-pointer whitespace-nowrap min-h-[34px] flex items-center justify-center gap-1"
        :class="activeTab === 'overview'
          ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activeTab = 'overview'"
      >
        <span>Ringkasan</span>
      </button>

      <button
        type="button"
        class="flex-1 py-1.5 px-2.5 rounded-full text-[11px] font-semibold transition-all cursor-pointer whitespace-nowrap min-h-[34px] flex items-center justify-center gap-1"
        :class="activeTab === 'attributes'
          ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activeTab = 'attributes'"
      >
        <span>Atribut</span>
        <span
          v-if="attributesData?.attributes_count"
          class="px-1.5 py-0.2 rounded-full text-[9px] font-mono tabular-nums"
          :class="activeTab === 'attributes' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-slate-200/80 dark:bg-white/10 text-slate-600 dark:text-slate-400'"
        >
          {{ attributesData.attributes_count }}
        </span>
      </button>

      <button
        type="button"
        class="flex-1 py-1.5 px-2.5 rounded-full text-[11px] font-semibold transition-all cursor-pointer whitespace-nowrap min-h-[34px] flex items-center justify-center gap-1"
        :class="activeTab === 'csw'
          ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activeTab = 'csw'"
      >
        <span>Katalog CSW</span>
      </button>

      <!-- Tombol Segarkan Metadata Cepat -->
      <button
        type="button"
        class="size-8 rounded-full flex items-center justify-center text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-white dark:hover:bg-white/10 transition-colors cursor-pointer shrink-0"
        title="Muat ulang data metadata dari server Geoportal"
        :disabled="loadingMeta || loadingAttrs"
        @click="loadData(true)"
      >
        <UIcon
          name="i-lucide-refresh-cw"
          class="size-3.5"
          :class="{ 'animate-spin text-blue-500': loadingMeta || loadingAttrs }"
        />
      </button>
    </div>

    <!-- ═══ TAB 1: RINGKASAN & SPESIFIKASI OGC ═══ -->
    <div v-if="activeTab === 'overview'" class="space-y-3">
      <!-- Kategori & Wali Data -->
      <div class="grid grid-cols-1 gap-2.5">
        <div class="p-3 rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] space-y-1">
          <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Kategori / Urusan</span>
          <div class="flex items-center gap-1.5 font-semibold text-xs text-slate-900 dark:text-white">
            <UIcon name="i-lucide-folder-git-2" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <span>{{ props.layer.category || 'Tematik Umum' }}</span>
          </div>
        </div>

        <div class="p-3 rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] space-y-1">
          <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Wali Data / OPD</span>
          <div class="flex items-center gap-1.5 font-semibold text-xs text-slate-900 dark:text-white">
            <UIcon name="i-lucide-landmark" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <span class="truncate" :title="props.layer.opd_name || props.layer.attribution || ''">
              {{ props.layer.opd_name || props.layer.attribution || 'Pemerintah Kabupaten Bojonegoro' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Abstrak / Deskripsi Dataset Lengkap -->
      <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-1.5">
        <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-semibold text-xs">
          <UIcon name="i-lucide-file-text" class="size-4 text-slate-400" />
          <span>Abstrak / Deskripsi Dataset</span>
        </div>
        <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-xs">
          {{ props.layer.description || dcRecord?.abstract || 'Dataset tematik resmi simpul JIGN Kabupaten Bojonegoro.' }}
        </p>
      </div>

      <!-- Spesifikasi Layanan OGC -->
      <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2.5">
        <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-semibold text-xs">
          <UIcon name="i-lucide-server" class="size-4 text-slate-400" />
          <span>Spesifikasi Layanan OGC Service</span>
        </div>
        <div class="space-y-2 text-[11px]">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-1.5">
            <span class="text-slate-500">Protokol:</span>
            <span class="font-bold text-blue-600 dark:text-blue-400 uppercase">
              {{ props.layer.protocol }} (WMS 1.1.1 & WFS 1.1.0)
            </span>
          </div>

          <div class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-1.5">
            <span class="text-slate-500">Nama Layer Teknis:</span>
            <span class="text-slate-900 dark:text-slate-100 font-mono font-semibold truncate max-w-[200px]" :title="props.layer.layer_name">
              {{ props.layer.layer_name }}
            </span>
          </div>

          <div v-if="props.layer.geoportal_id" class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-1.5">
            <span class="text-slate-500">CSW Identifier:</span>
            <span class="text-slate-700 dark:text-slate-300 font-mono truncate max-w-[190px]" :title="props.layer.geoportal_id">
              {{ props.layer.geoportal_id }}
            </span>
          </div>

          <div class="flex items-start justify-between gap-2 border-b border-slate-100 dark:border-white/5 pb-1.5">
            <span class="text-slate-500 shrink-0">WMS Endpoint:</span>
            <span class="text-slate-600 dark:text-slate-400 text-right font-mono truncate max-w-[200px]" :title="props.layer.url">
              {{ props.layer.url }}
            </span>
          </div>

          <div v-if="props.layer.last_synced_at" class="flex items-center justify-between pt-0.5">
            <span class="text-slate-500">Terakhir Disinkronkan:</span>
            <span class="text-slate-700 dark:text-slate-300">
              {{ new Date(props.layer.last_synced_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Simbol Legenda Resmi (GetLegendGraphic) -->
      <div v-if="props.layer.legend_url" class="p-3.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2">
        <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-semibold text-xs">
          <UIcon name="i-lucide-list" class="size-4 text-slate-400" />
          <span>Simbol Legenda Resmi (GetLegendGraphic)</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/80 dark:border-white/5 inline-block">
          <img
            :src="props.layer.legend_url"
            alt="Legenda Resmi Geoportal Bojonegoro"
            class="max-h-32 object-contain"
            loading="lazy"
          />
        </div>
      </div>
    </div>

    <!-- ═══ TAB 2: ATRIBUT DATA LAYER ═══ -->
    <div v-else-if="activeTab === 'attributes'" class="space-y-3">
      <!-- Loading State -->
      <div v-if="loadingAttrs" class="flex flex-col items-center justify-center p-6 space-y-2">
        <UIcon name="i-lucide-refresh-cw" class="size-5 animate-spin text-blue-600" />
        <span class="font-medium text-slate-500 text-xs">Mengambil skema atribut & sampel data WFS...</span>
      </div>

      <!-- Error / Non-Vector State -->
      <div
        v-else-if="!attributesData?.ok && (!attributesData?.attributes || attributesData.attributes.length === 0)"
        class="p-4 rounded-xl border border-dashed border-amber-300 dark:border-amber-800/40 bg-amber-50/50 dark:bg-amber-950/20 space-y-1.5"
      >
        <div class="flex items-center gap-1.5 text-amber-800 dark:text-amber-300 font-semibold text-xs">
          <UIcon name="i-lucide-alert-circle" class="size-4" />
          <span>Layanan WFS Tidak Menghasilkan Atribut</span>
        </div>
        <p class="text-[11px] text-amber-700 dark:text-amber-400 leading-relaxed">
          {{ attributesData?.message || 'Layer ini mungkin berupa raster (citra/peta dasar) atau tidak mengekspos endpoint OGC WFS DescribeFeatureType.' }}
        </p>
      </div>

      <!-- Attributes Content -->
      <div v-else class="space-y-3">
        <!-- Banner Identifikasi KUGI (Jika FCODE Terdeteksi) -->
        <div
          v-if="activeKugiMeta"
          class="p-3 rounded-xl border border-emerald-200/80 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/20 space-y-1.5 shadow-xs"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="space-y-0.5 min-w-0">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-emerald-600 text-white shadow-xs">
                  <UIcon name="i-lucide-award" class="size-3" />
                  FCODE: {{ activeKugiMeta.fcode }}
                </span>
                <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                  {{ activeKugiMeta.aliases || activeKugiMeta.typeName }}
                </span>
              </div>
              <p v-if="activeKugiMeta.definition" class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed">
                {{ activeKugiMeta.definition }}
              </p>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1 text-[9px] font-mono font-semibold text-emerald-700 dark:text-emerald-300 px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/80 border border-emerald-300/60 dark:border-emerald-800/60">
              <UIcon name="i-lucide-check-circle" class="size-2.5" />
              KUGI BIG
            </span>
          </div>
          <div v-if="activeKugiMeta.producer" class="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-emerald-100 dark:border-white/5 flex items-center gap-1">
            <span class="font-semibold text-slate-700 dark:text-slate-300">Wali Data:</span>
            <span>{{ activeKugiMeta.producer }}</span>
          </div>
        </div>

        <div
          v-else-if="isLoadingKugi"
          class="p-2.5 rounded-xl border border-blue-200/60 bg-blue-50/50 dark:bg-blue-950/20 flex items-center gap-2 text-xs text-blue-700 dark:text-blue-300"
        >
          <UIcon name="i-lucide-refresh-cw" class="size-3.5 animate-spin" />
          <span>Mengidentifikasi definisi unsur geospasial pada KUGI BIG...</span>
        </div>

        <!-- Header Toolbar Atribut -->
        <div class="flex items-center justify-between gap-2 p-2 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/80 dark:border-white/10 flex-wrap">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
              <UIcon name="i-lucide-columns-3" class="size-3" />
              <span>{{ attributesData?.attributes_count || 0 }} Kolom</span>
            </span>

            <span
              v-if="attributesData?.geom_type"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-mono font-medium bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300"
            >
              <UIcon name="i-lucide-shapes" class="size-3" />
              <span>{{ attributesData.geom_type }}</span>
            </span>
          </div>

          <!-- Switcher: Skema vs Data Sampel -->
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="px-2 py-1 rounded-md text-[10px] font-semibold transition-colors cursor-pointer"
              :class="attrViewMode === 'schema'
                ? 'bg-blue-600 text-white'
                : 'bg-slate-200/70 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70'"
              @click="attrViewMode = 'schema'"
            >
              Skema
            </button>
            <button
              type="button"
              class="px-2 py-1 rounded-md text-[10px] font-semibold transition-colors cursor-pointer flex items-center gap-1"
              :class="attrViewMode === 'sample'
                ? 'bg-blue-600 text-white'
                : 'bg-slate-200/70 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70'"
              @click="attrViewMode = 'sample'"
            >
              <span>Sampel</span>
              <span v-if="attributesData?.sample_count" class="font-mono opacity-80">({{ attributesData.sample_count }})</span>
            </button>
          </div>
        </div>

        <!-- 1. Schema View Table -->
        <div v-if="attrViewMode === 'schema'" class="space-y-2">
          <div class="relative">
            <UIcon name="i-lucide-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
            <input
              v-model="attrSearch"
              type="text"
              placeholder="Cari kolom atau arti KUGI..."
              class="w-full h-8 pl-8 pr-7 rounded-lg bg-white dark:bg-[#0b0f19] border border-slate-200/80 dark:border-white/10 text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-hidden focus:border-blue-500"
            />
          </div>

          <div class="border border-slate-200/80 dark:border-white/10 rounded-xl overflow-y-auto max-h-[300px] bg-white dark:bg-[#0b0f19] shadow-xs">
            <table class="w-full text-left border-collapse text-[11px]">
              <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900 border-b border-slate-200 dark:border-white/10 z-10 font-mono shadow-xs">
                <tr>
                  <th class="p-2 font-semibold text-slate-700 dark:text-slate-300">Kolom</th>
                  <th class="p-2 font-semibold text-slate-700 dark:text-slate-300 font-sans">Definisi KUGI</th>
                  <th class="p-2 font-semibold text-slate-700 dark:text-slate-300">Tipe</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-white/5 bg-white dark:bg-[#0b0f19]">
                <tr v-for="attr in filteredAttributes" :key="attr.name" class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                  <td class="p-2 font-mono font-semibold text-slate-900 dark:text-slate-100">
                    <div class="flex items-center gap-1">
                      <UIcon
                        :name="attr.is_geometry ? 'i-lucide-shapes' : (attr.local_type.includes('int') || attr.local_type.includes('double') ? 'i-lucide-hash' : 'i-lucide-type')"
                        class="size-3 shrink-0"
                        :class="attr.is_geometry ? 'text-emerald-500' : 'text-slate-400'"
                      />
                      <span class="truncate max-w-[90px]" :title="attr.name">{{ attr.name }}</span>
                    </div>
                  </td>
                  <td class="p-2">
                    <div v-if="resolveKugiLabel(attr.name).isKugi" class="space-y-0.5">
                      <span class="text-slate-800 dark:text-slate-200 font-medium leading-tight block text-[10px]">
                        {{ resolveKugiLabel(attr.name).label }}
                      </span>
                    </div>
                    <span v-else class="text-slate-400 italic text-[9px]">
                      Atribut Lokal
                    </span>
                  </td>
                  <td class="p-2 font-mono text-[9px]">
                    <span
                      class="px-1 py-0.5 rounded-md font-medium"
                      :class="attr.is_geometry
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300'"
                    >
                      {{ attr.local_type || attr.type }}
                    </span>
                  </td>
                </tr>
                <tr v-if="filteredAttributes.length === 0">
                  <td colspan="3" class="p-3 text-center text-slate-400 italic text-xs">
                    Tidak ada atribut yang cocok.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 2. Sample Data View Table -->
        <div v-else-if="attrViewMode === 'sample'" class="space-y-2">
          <div v-if="attributesData?.sample_data.length === 0" class="p-4 text-center text-slate-400 italic text-xs">
            Sampel data tidak tersedia dari WFS GetFeature.
          </div>
          <div v-else class="border border-slate-200/80 dark:border-white/10 rounded-xl overflow-x-auto max-h-[280px] bg-white dark:bg-[#0b0f19] shadow-xs">
            <table class="w-full text-left border-collapse text-[10px] whitespace-nowrap">
              <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900 border-b border-slate-200 dark:border-white/10 z-10">
                <tr>
                  <th class="p-2 font-semibold text-slate-500 bg-slate-100 dark:bg-slate-900 font-mono sticky left-0 z-20 border-r border-slate-200 dark:border-white/10">#</th>
                  <th v-for="col in sampleColumns" :key="col" class="p-2 font-semibold text-slate-700 dark:text-slate-300">
                    <span class="font-mono">{{ col }}</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-white/5 bg-white dark:bg-[#0b0f19] font-mono">
                <tr v-for="(row, idx) in attributesData?.sample_data" :key="idx" class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                  <td class="p-2 font-bold text-slate-400 bg-slate-50 dark:bg-slate-900 sticky left-0 z-10 border-r border-slate-200 dark:border-white/10">{{ idx + 1 }}</td>
                  <td v-for="col in sampleColumns" :key="col" class="p-2 text-slate-700 dark:text-slate-300 max-w-[120px] truncate" :title="String(row[col] ?? '')">
                    {{ row[col] !== null && row[col] !== undefined && row[col] !== '' ? row[col] : '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-[10px] text-slate-400 italic">
            * Menampilkan {{ attributesData?.sample_count }} baris sampel riil melalui WFS GetFeature.
          </p>
        </div>
      </div>
    </div>

    <!-- ═══ TAB 3: KATALOG pyCSW ═══ -->
    <div v-else-if="activeTab === 'csw'" class="space-y-3">
      <!-- Loading pyCSW -->
      <div v-if="loadingMeta" class="flex flex-col items-center justify-center p-6 space-y-2">
        <UIcon name="i-lucide-refresh-cw" class="size-5 animate-spin text-blue-600" />
        <span class="font-medium text-slate-500 text-xs">Mengambil rekaman pyCSW 2.6.0...</span>
      </div>

      <div v-else class="space-y-3">
        <!-- Identitas Rekaman CSW -->
        <div class="p-3 rounded-xl border border-blue-200/80 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/20 space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="font-bold text-xs text-slate-900 dark:text-slate-100">
              {{ dcRecord?.title || props.layer.name }}
            </span>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
              pyCSW 2.6.0
            </span>
          </div>
          <div class="font-mono text-[10px] text-slate-500">
            ID: {{ dcRecord?.identifier || props.layer.geoportal_id || '-' }}
          </div>
          <div v-if="dcRecord?.subjects && dcRecord.subjects.length > 0" class="flex items-center gap-1 flex-wrap pt-1 border-t border-blue-100 dark:border-white/5">
            <span
              v-for="(sub, i) in dcRecord.subjects"
              :key="i"
              class="px-1.5 py-0.5 rounded-md bg-white dark:bg-slate-900 text-[9px] font-medium text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10"
            >
              {{ sub }}
            </span>
          </div>
        </div>

        <!-- Cakupan Spasial & CRS -->
        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
              <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400" />
              <span>Cakupan Spasial (BBOX)</span>
            </span>
            <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-bold bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
              {{ spatialExtent.crs }}
            </span>
          </div>

          <!-- 4 Titik Batas BBOX Grid -->
          <div class="grid grid-cols-2 gap-1.5 font-mono text-[10px]">
            <div class="p-2 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 space-y-0.5">
              <span class="text-slate-400 font-sans block text-[9px]">Barat (West)</span>
              <span class="font-bold text-slate-800 dark:text-slate-100 block">{{ formatCoord(spatialExtent.west, 5) }}°</span>
              <span class="text-[9px] text-slate-500 font-sans block">{{ formatDms(spatialExtent.west, false) }}</span>
            </div>

            <div class="p-2 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 space-y-0.5">
              <span class="text-slate-400 font-sans block text-[9px]">Timur (East)</span>
              <span class="font-bold text-slate-800 dark:text-slate-100 block">{{ formatCoord(spatialExtent.east, 5) }}°</span>
              <span class="text-[9px] text-slate-500 font-sans block">{{ formatDms(spatialExtent.east, false) }}</span>
            </div>

            <div class="p-2 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 space-y-0.5">
              <span class="text-slate-400 font-sans block text-[9px]">Selatan (South)</span>
              <span class="font-bold text-slate-800 dark:text-slate-100 block">{{ formatCoord(spatialExtent.south, 5) }}°</span>
              <span class="text-[9px] text-slate-500 font-sans block">{{ formatDms(spatialExtent.south, true) }}</span>
            </div>

            <div class="p-2 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 space-y-0.5">
              <span class="text-slate-400 font-sans block text-[9px]">Utara (North)</span>
              <span class="font-bold text-slate-800 dark:text-slate-100 block">{{ formatCoord(spatialExtent.north, 5) }}°</span>
              <span class="text-[9px] text-slate-500 font-sans block">{{ formatDms(spatialExtent.north, true) }}</span>
            </div>
          </div>

          <!-- Titik Pusat Centroid & Tombol Salin -->
          <div class="p-2 rounded-lg bg-slate-50/70 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 flex items-center justify-between gap-2 font-mono text-[10px]">
            <div class="min-w-0">
              <span class="text-slate-400 font-sans block text-[9px]">Titik Pusat (Centroid):</span>
              <span class="font-bold text-slate-800 dark:text-slate-200 truncate block">{{ spatialExtent.centerStr }}</span>
            </div>
            <button
              type="button"
              class="shrink-0 p-1.5 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center gap-1 transition-colors cursor-pointer"
              title="Salin koordinat titik pusat"
              @click="copyText(spatialExtent.centerStr, 'bbox_center')"
            >
              <UIcon :name="copiedField === 'bbox_center' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3" :class="{ 'text-emerald-500': copiedField === 'bbox_center' }" />
              <span class="text-[9px] font-sans font-medium">{{ copiedField === 'bbox_center' ? 'Disalin' : 'Salin' }}</span>
            </button>
          </div>
        </div>

        <!-- Lembaga Kontak & Tanggal -->
        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2 text-[11px]">
          <div>
            <span class="text-slate-400 text-[10px] block">Lembaga Pembuat / Kontak:</span>
            <span class="font-semibold text-slate-800 dark:text-slate-200">
              {{ isoRecord?.contact?.organisation || dcRecord?.publisher || props.layer.opd_name || 'Bappeda Bojonegoro' }}
            </span>
          </div>
          <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-white/5 text-[10px]">
            <span class="text-slate-400">Tanggal Terbit:</span>
            <span class="text-slate-700 dark:text-slate-300 font-mono">{{ dcRecord?.date || isoRecord?.date_stamp || '2021-04-29' }}</span>
          </div>
        </div>

        <!-- Endpoint Layanan OGC Terkait -->
        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2">
          <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
            <UIcon name="i-lucide-link-2" class="size-3.5 text-blue-600" />
            <span>Endpoint Layanan OGC</span>
          </span>

          <div class="space-y-1.5 font-mono text-[10px]">
            <!-- WMS -->
            <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/50 dark:border-white/5">
              <div class="min-w-0 flex-1 truncate">
                <span class="text-slate-400 font-sans mr-1">WMS:</span>
                <span class="text-slate-700 dark:text-slate-300">{{ props.layer.url }}</span>
              </div>
              <button
                type="button"
                class="shrink-0 p-1 rounded hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 cursor-pointer"
                title="Salin URL WMS"
                @click="copyText(props.layer.url, 'wms')"
              >
                <UIcon :name="copiedField === 'wms' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3" :class="{ 'text-emerald-500': copiedField === 'wms' }" />
              </button>
            </div>

            <!-- WFS -->
            <div v-if="attributesData?.wfs_url" class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/50 dark:border-white/5">
              <div class="min-w-0 flex-1 truncate">
                <span class="text-slate-400 font-sans mr-1">WFS:</span>
                <span class="text-slate-700 dark:text-slate-300">{{ attributesData.wfs_url }}</span>
              </div>
              <button
                type="button"
                class="shrink-0 p-1 rounded hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 cursor-pointer"
                title="Salin URL WFS"
                @click="copyText(attributesData.wfs_url, 'wfs')"
              >
                <UIcon :name="copiedField === 'wfs' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3" :class="{ 'text-emerald-500': copiedField === 'wfs' }" />
              </button>
            </div>

            <!-- CSW -->
            <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/50 dark:border-white/5">
              <div class="min-w-0 flex-1 truncate">
                <span class="text-slate-400 font-sans mr-1">CSW:</span>
                <span class="text-slate-700 dark:text-slate-300">{{ pycsw?.csw_url || 'https://geoportal.bojonegorokab.go.id/csw/' }}</span>
              </div>
              <button
                type="button"
                class="shrink-0 p-1 rounded hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 cursor-pointer"
                title="Salin URL CSW"
                @click="copyText(pycsw?.csw_url || 'https://geoportal.bojonegorokab.go.id/csw/', 'csw')"
              >
                <UIcon :name="copiedField === 'csw' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3" :class="{ 'text-emerald-500': copiedField === 'csw' }" />
              </button>
            </div>
          </div>
        </div>

        <!-- Dokumen Standar ISO 19139 & Dublin Core XML -->
        <div class="space-y-2">
          <!-- ISO 19139 Card -->
          <div class="p-3 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-800 dark:text-slate-100">ISO 19139 (GMD)</span>
              <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
                SNI / JIGN
              </span>
            </div>
            <div class="grid grid-cols-3 gap-1.5 pt-1 border-t border-slate-200/60 dark:border-white/5">
              <button
                type="button"
                class="px-2 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium text-[10px] flex items-center justify-center gap-1 transition-colors cursor-pointer shadow-xs"
                title="Buka langsung dari server pyCSW resmi"
                @click="openDirectCswUrl('iso')"
              >
                <UIcon name="i-lucide-external-link" class="size-3" />
                <span>Resmi</span>
              </button>
              <button
                type="button"
                class="px-2 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-[10px] flex items-center justify-center gap-1 transition-colors cursor-pointer"
                title="Buka melalui proxy mirror internal"
                @click="openMelarosaXmlUrl('iso')"
              >
                <UIcon name="i-lucide-globe" class="size-3 text-slate-400" />
                <span>Mirror</span>
              </button>
              <button
                type="button"
                class="px-2 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-[10px] flex items-center justify-center gap-1 transition-colors cursor-pointer"
                title="Unduh XML ISO 19139"
                @click="downloadXml('iso')"
              >
                <UIcon name="i-lucide-download" class="size-3 text-emerald-600 dark:text-emerald-400" />
                <span>Unduh</span>
              </button>
            </div>
          </div>

          <!-- Dublin Core Card -->
          <div class="p-3 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Dublin Core Record</span>
              <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300">
                CSW 2.0.2
              </span>
            </div>
            <div class="grid grid-cols-3 gap-1.5 pt-1 border-t border-slate-200/60 dark:border-white/5">
              <button
                type="button"
                class="px-2 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-[10px] flex items-center justify-center gap-1 transition-colors cursor-pointer shadow-xs"
                title="Buka langsung dari server pyCSW resmi"
                @click="openDirectCswUrl('dc')"
              >
                <UIcon name="i-lucide-external-link" class="size-3" />
                <span>Resmi</span>
              </button>
              <button
                type="button"
                class="px-2 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-[10px] flex items-center justify-center gap-1 transition-colors cursor-pointer"
                title="Buka melalui proxy mirror internal"
                @click="openMelarosaXmlUrl('dc')"
              >
                <UIcon name="i-lucide-globe" class="size-3 text-slate-400" />
                <span>Mirror</span>
              </button>
              <button
                type="button"
                class="px-2 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-[10px] flex items-center justify-center gap-1 transition-colors cursor-pointer"
                title="Unduh XML Dublin Core"
                @click="downloadXml('dc')"
              >
                <UIcon name="i-lucide-download" class="size-3 text-emerald-600 dark:text-emerald-400" />
                <span>Unduh</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tombol Buka Dialog Modal Penuh -->
    <div class="pt-1">
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 min-h-[38px] px-3 py-2 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/20 dark:hover:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-semibold text-xs transition-colors cursor-pointer"
        @click="emit('open-modal')"
      >
        <UIcon name="i-lucide-maximize-2" class="size-3.5" />
        <span>Buka Dialog Metadata Layar Penuh</span>
      </button>
    </div>
  </div>
</template>

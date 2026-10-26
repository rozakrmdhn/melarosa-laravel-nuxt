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
    open?: boolean;
    layer?: GeoportalLayerItem | null;
  }>(),
  {
    open: false,
    layer: null,
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit("update:open", val),
});

const config = useRuntimeConfig();
const { fetchLayerMetadata, fetchLayerAttributes } = useGeoportalLayers();

type TabType = "overview" | "attributes" | "csw";
const activeTab = ref<TabType>("overview");

// Metadata & Attributes states
const loadingMeta = ref(false);
const loadingAttrs = ref(false);
const metadataDetail = ref<Record<string, any> | null>(null);
const attributesData = ref<LayerAttributesData | null>(null);
const attrSearch = ref("");
const attrViewMode = ref<"schema" | "sample">("schema");
const copiedField = ref<string | null>(null);

// ─── KUGI (Katalog Unsur Geografi Indonesia) Definitions ──────────────────────
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

// Deteksi FCODE dari layer (prioritas dari backend detected_fcode, lalu scan sampel data)
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
    } catch (err) {
      console.warn("Gagal memuat kamus KUGI:", err);
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
  navigator.clipboard.writeText(text);
  copiedField.value = fieldId;
  setTimeout(() => {
    if (copiedField.value === fieldId) {
      copiedField.value = null;
    }
  }, 2000);
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
  if (url) window.open(url, "_blank", "noopener,noreferrer");
}

function openMelarosaXmlUrl(schema: "iso" | "dc") {
  const url = getMelarosaXmlUrl(schema, false);
  if (url) window.open(url, "_blank", "noopener,noreferrer");
}

function downloadXml(schema: "iso" | "dc") {
  const url = getMelarosaXmlUrl(schema, true);
  if (!url) return;
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
    console.error("Gagal memuat metadata lengkap:", e);
  } finally {
    loadingMeta.value = false;
  }

  try {
    const attrRes = await fetchLayerAttributes(props.layer.id, refresh);
    if (attrRes?.data) {
      attributesData.value = attrRes.data;
    }
  } catch (e) {
    console.error("Gagal memuat atribut layer:", e);
  } finally {
    loadingAttrs.value = false;
  }
}

watch(
  () => [isOpen.value, props.layer?.id],
  ([open, id]) => {
    if (open && id) {
      loadData();
    }
  },
  { immediate: true }
);

// Filtered properties for schema table (mendukung pencarian nama field, tipe, atau definisi KUGI)
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

// Sample records headers (exclude geometry objects for clean table viewing)
const sampleColumns = computed(() => {
  const rows = attributesData.value?.sample_data || [];
  if (rows.length === 0) return [];
  return Object.keys(rows[0]).filter((k) => k !== "geom" && k !== "geometry");
});

// Pycsw metadata helpers
const pycsw = computed<PycswMetadataRecord | null>(() => {
  return metadataDetail.value?.pycsw || null;
});

const dcRecord = computed(() => pycsw.value?.dublin_core || null);
const isoRecord = computed(() => pycsw.value?.iso_19139 || null);

function formatCoord(val?: number | null, precision = 7): string {
  if (val === null || val === undefined || isNaN(val)) return "-";
  return Number(val).toFixed(precision);
}

function formatDms(val?: number | null, isLat = true): string {
  if (val === null || val === undefined || isNaN(val)) return "-";
  const abs = Math.abs(val);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = ((minFloat - min) * 60).toFixed(2);
  const dir = isLat ? (val >= 0 ? "LU" : "LS") : (val >= 0 ? "BT" : "BB");
  return `${deg}° ${String(min).padStart(2, "0")}' ${String(sec).padStart(5, "0")}" ${dir}`;
}

const spatialExtent = computed(() => {
  const isoBbox = isoRecord.value?.bbox;
  const dcBbox = dcRecord.value?.bbox;

  const west = isoBbox?.west ?? dcBbox?.west ?? (dcBbox?.lower_corner ? Number(dcBbox.lower_corner.split(" ")[0]) : null) ?? 111.445102;
  const east = isoBbox?.east ?? dcBbox?.east ?? (dcBbox?.upper_corner ? Number(dcBbox.upper_corner.split(" ")[0]) : null) ?? 112.163338;
  const south = isoBbox?.south ?? dcBbox?.south ?? (dcBbox?.lower_corner ? Number(dcBbox.lower_corner.split(" ")[1]) : null) ?? -7.451683;
  const north = isoBbox?.north ?? dcBbox?.north ?? (dcBbox?.upper_corner ? Number(dcBbox.upper_corner.split(" ")[1]) : null) ?? -6.981707;

  const centerLon = isoBbox?.center?.[0] ?? Number(((west + east) / 2).toFixed(7));
  const centerLat = isoBbox?.center?.[1] ?? Number(((south + north) / 2).toFixed(7));

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
  <UModal
    v-model:open="isOpen"
    :title="props.layer?.name || 'Informasi Metadata Geoportal'"
    description="Metadata standar pyCSW 2.6.0 & OGC WFS/WMS Simpul Jaringan Geoportal Bojonegoro."
    :ui="{
      content: 'sm:max-w-4xl w-[calc(100vw-2rem)] max-h-[90vh] h-[85vh] bg-white dark:bg-[#09111e] border border-slate-200/90 dark:border-white/10 rounded-2xl shadow-2xl flex flex-col overflow-hidden p-0 sm:p-0',
      header: 'px-5 py-3.5 border-b border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02] shrink-0',
      body: 'p-0 sm:p-0 flex-1 min-h-0 flex flex-col overflow-hidden',
      footer: 'px-5 py-3 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02] shrink-0',
    }"
  >
    <template #body>
      <div v-if="props.layer" class="flex flex-col flex-1 min-h-0 h-full">
        <!-- ═══ 1. SEGMENTED NAVIGATION TABS (FIXED TOP) ═══ -->
        <div class="shrink-0 px-4 sm:px-5 py-2.5 bg-white dark:bg-[#0b0f19] border-b border-slate-200 dark:border-white/10 flex items-center justify-between gap-2 z-10 shadow-xs">
          <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 overflow-x-auto no-scrollbar">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap"
              :class="activeTab === 'overview'
                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
              @click="activeTab = 'overview'"
            >
              <UIcon name="i-lucide-info" class="size-3.5" />
              <span>Ringkasan & OGC</span>
            </button>

            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap"
              :class="activeTab === 'attributes'
                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
              @click="activeTab = 'attributes'"
            >
              <UIcon name="i-lucide-table-2" class="size-3.5" />
              <span>Atribut Data</span>
              <span
                v-if="attributesData?.attributes_count"
                class="px-1.5 py-0.2 rounded-full text-xs font-mono"
                :class="activeTab === 'attributes' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-slate-200/80 dark:bg-white/10 text-slate-600 dark:text-slate-400'"
              >
                {{ attributesData.attributes_count }}
              </span>
            </button>

            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap"
              :class="activeTab === 'csw'
                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
              @click="activeTab = 'csw'"
            >
              <UIcon name="i-lucide-globe" class="size-3.5" />
              <span>Katalog pyCSW</span>
              <span class="px-1.5 py-0.2 rounded-full text-xs font-mono bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                2.6.0
              </span>
            </button>
          </div>

          <button
            type="button"
            class="px-2.5 py-1.5 rounded-lg text-slate-500 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/5 flex items-center gap-1.5 text-xs font-medium transition-colors cursor-pointer shrink-0"
            title="Muat ulang data metadata dari server Geoportal"
            :disabled="loadingMeta || loadingAttrs"
            @click="loadData(true)"
          >
            <UIcon name="i-heroicons-arrow-path" class="size-3.5" :class="{ 'animate-spin': loadingMeta || loadingAttrs }" />
            <span class="hidden sm:inline">Segarkan</span>
          </button>
        </div>

        <!-- ═══ 2. TAB CONTENT ═══ -->
        <div class="flex-1 min-h-0 flex flex-col">
          <!-- ════════════════════════════════════════════════════════════════════ -->
          <!-- TAB 1: RINGKASAN & SPESIFIKASI OGC                                   -->
          <!-- ════════════════════════════════════════════════════════════════════ -->
          <div v-if="activeTab === 'overview'" class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-5 space-y-3.5 text-xs">
          <!-- Overview Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] space-y-1">
              <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kategori / Urusan</span>
              <div class="flex items-center gap-1.5 font-semibold text-slate-900 dark:text-white">
                <UIcon name="i-lucide-folder-git-2" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
                <span>{{ props.layer.category || 'Tematik Umum' }}</span>
              </div>
            </div>

            <div class="p-3 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] space-y-1">
              <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Wali Data / OPD</span>
              <div class="flex items-center gap-1.5 font-semibold text-slate-900 dark:text-white">
                <UIcon name="i-lucide-landmark" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
                <span class="truncate" :title="props.layer.opd_name || props.layer.attribution || ''">
                  {{ props.layer.opd_name || props.layer.attribution || 'Pemerintah Kabupaten Bojonegoro' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Description / Abstract -->
          <div class="p-3.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-1.5">
            <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-semibold text-xs">
              <UIcon name="i-lucide-file-text" class="size-4 text-slate-400" />
              <span>Abstrak / Deskripsi Dataset</span>
            </div>
            <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-xs">
              {{ props.layer.description || dcRecord?.abstract || 'Tidak ada deskripsi yang disertakan dalam rekaman katalog CSW ini.' }}
            </p>
          </div>

          <!-- Technical OGC Specification -->
          <div class="p-3.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2.5">
            <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-semibold text-xs">
              <UIcon name="i-lucide-server" class="size-4 text-slate-400" />
              <span>Spesifikasi Layanan OGC Service</span>
            </div>
            <div class="space-y-2 font-mono text-xs">
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-1.5">
                <span class="text-slate-500">Protokol & Format:</span>
                <span class="font-bold text-blue-600 dark:text-blue-400 uppercase">
                  {{ props.layer.protocol }} (TileWMS / GetMap 1.1.1 & WFS 1.1.0)
                </span>
              </div>

              <div class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-1.5">
                <span class="text-slate-500">Nama Layer Teknis:</span>
                <span class="text-slate-900 dark:text-slate-100 font-bold truncate max-w-xs" :title="props.layer.layer_name">
                  {{ props.layer.layer_name }}
                </span>
              </div>

              <div v-if="props.layer.geoportal_id" class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 pb-1.5">
                <span class="text-slate-500">CSW Identifier:</span>
                <span class="text-slate-700 dark:text-slate-300 truncate max-w-xs" :title="props.layer.geoportal_id">
                  {{ props.layer.geoportal_id }}
                </span>
              </div>

              <div class="flex items-start justify-between gap-2 border-b border-slate-100 dark:border-white/5 pb-1.5">
                <span class="text-slate-500 shrink-0">WMS Endpoint:</span>
                <span class="text-slate-600 dark:text-slate-400 text-right truncate max-w-sm" :title="props.layer.url">
                  {{ props.layer.url }}
                </span>
              </div>

              <div v-if="props.layer.last_synced_at" class="flex items-center justify-between pt-0.5">
                <span class="text-slate-500">Terakhir Disinkronkan:</span>
                <span class="text-slate-700 dark:text-slate-300">
                  {{ new Date(props.layer.last_synced_at).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Official Legend Preview -->
          <div v-if="props.layer.legend_url" class="p-3.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2">
            <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-semibold text-xs">
              <UIcon name="i-lucide-list" class="size-4 text-slate-400" />
              <span>Simbol Legenda Resmi (GetLegendGraphic)</span>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-slate-200/80 dark:border-white/5 inline-block">
              <img
                :src="props.layer.legend_url"
                alt="Legenda Resmi Geoportal Bojonegoro"
                class="max-h-36 object-contain"
                loading="lazy"
              />
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 2: ATRIBUT DATA LAYER DARI SIMPUL JARINGAN                       -->
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <div v-else-if="activeTab === 'attributes'" class="flex-1 min-h-0 flex flex-col p-4 sm:p-5 gap-3 text-xs overflow-hidden">
          <!-- Loading State -->
          <div v-if="loadingAttrs" class="flex-1 flex flex-col items-center justify-center p-8 space-y-3">
            <UIcon name="i-heroicons-arrow-path" class="size-6 animate-spin text-blue-600" />
            <span class="font-medium text-slate-500 text-xs">Mengambil skema atribut & data sampel dari layanan WFS...</span>
          </div>

          <!-- Error / Non-Vector State -->
          <div
            v-else-if="!attributesData?.ok && attributesData?.attributes.length === 0"
            class="p-4 rounded-xl border border-dashed border-amber-300 dark:border-amber-800/40 bg-amber-50/50 dark:bg-amber-950/20 space-y-2"
          >
            <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300 font-semibold">
              <UIcon name="i-lucide-alert-circle" class="size-4" />
              <span>Layanan WFS Tidak Menghasilkan Atribut</span>
            </div>
            <p class="text-xs text-amber-700 dark:text-amber-400 leading-relaxed">
              {{ attributesData?.message || 'Layer ini mungkin berupa raster (citra/peta dasar) atau tidak mengekspos endpoint OGC WFS DescribeFeatureType.' }}
            </p>
          </div>

          <!-- Attributes Content (Full Height Flex Container) -->
          <div v-else class="flex-1 min-h-0 flex flex-col gap-2.5">
            <!-- ─── Banner Identifikasi KUGI (Jika FCODE Terdeteksi) ─── -->
            <div
              v-if="activeKugiMeta"
              class="shrink-0 p-3 rounded-xl border border-emerald-200/80 dark:border-emerald-900/50 bg-gradient-to-br from-emerald-50/70 via-slate-50 to-teal-50/40 dark:from-emerald-950/30 dark:via-[#0b0f19] dark:to-teal-950/20 space-y-1.5 shadow-xs"
            >
              <div class="flex items-start justify-between gap-2.5">
                <div class="space-y-0.5 min-w-0">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-mono font-bold bg-emerald-600 text-white shadow-xs">
                      <UIcon name="i-lucide-award" class="size-3" />
                      FCODE: {{ activeKugiMeta.fcode }}
                    </span>
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                      {{ activeKugiMeta.aliases || activeKugiMeta.typeName }}
                    </span>
                    <span v-if="activeKugiMeta.category" class="px-1.5 py-0.2 rounded text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300">
                      {{ activeKugiMeta.category }}
                    </span>
                  </div>
                  <p v-if="activeKugiMeta.definition" class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed line-clamp-2" :title="activeKugiMeta.definition">
                    {{ activeKugiMeta.definition }}
                  </p>
                </div>
                <span class="shrink-0 inline-flex items-center gap-1 text-xs font-mono font-semibold text-emerald-700 dark:text-emerald-300 px-2 py-0.5 rounded-full bg-emerald-100/90 dark:bg-emerald-950/60 border border-emerald-300/60 dark:border-emerald-800/60">
                  <UIcon name="i-lucide-check-circle" class="size-3" />
                  KUGI BIG Resmi
                </span>
              </div>
              <div v-if="activeKugiMeta.producer" class="text-xs text-slate-500 dark:text-slate-400 pt-1 border-t border-emerald-100 dark:border-white/5 flex items-center gap-1 flex-wrap">
                <span class="font-semibold text-slate-700 dark:text-slate-300">Produsen / Wali Data:</span>
                <span>{{ activeKugiMeta.producer }}</span>
              </div>
            </div>

            <!-- Loading status kamus KUGI -->
            <div
              v-else-if="isLoadingKugi"
              class="shrink-0 p-2.5 rounded-xl border border-blue-200/60 bg-blue-50/50 dark:bg-blue-950/20 flex items-center gap-2 text-xs text-blue-700 dark:text-blue-300"
            >
              <UIcon name="i-heroicons-arrow-path" class="size-3.5 animate-spin" />
              <span>Mengidentifikasi definisi unsur geospasial pada Katalog Unsur Geografi Indonesia (KUGI)...</span>
            </div>

            <!-- Header Toolbar -->
            <div class="shrink-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2 sm:p-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/80 dark:border-white/10">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
                  <UIcon name="i-lucide-columns-3" class="size-3.5" />
                  <span>{{ attributesData?.attributes_count || 0 }} Kolom Atribut</span>
                </span>

                <span
                  v-if="attributesData?.geom_type"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-mono font-medium bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300"
                >
                  <UIcon name="i-lucide-shapes" class="size-3.5" />
                  <span>Geometri: {{ attributesData.geom_type }}</span>
                </span>

                <span
                  v-if="currentFcode"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-mono font-semibold bg-slate-200/80 dark:bg-white/10 text-slate-700 dark:text-slate-300"
                >
                  <UIcon name="i-lucide-tag" class="size-3 text-slate-400" />
                  <span>{{ currentFcode }}</span>
                </span>
              </div>

              <!-- View Switcher: Schema Table vs Sample Data Table -->
              <div class="flex items-center gap-1 self-end sm:self-auto">
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-xs font-semibold transition-colors cursor-pointer"
                  :class="attrViewMode === 'schema'
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-200/70 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70'"
                  @click="attrViewMode = 'schema'"
                >
                  Struktur Skema
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-md text-xs font-semibold transition-colors cursor-pointer flex items-center gap-1"
                  :class="attrViewMode === 'sample'
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-200/70 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70'"
                  @click="attrViewMode = 'sample'"
                >
                  <span>Data Sampel</span>
                  <span v-if="attributesData?.sample_count" class="opacity-75 font-mono">({{ attributesData.sample_count }})</span>
                </button>
              </div>
            </div>

            <!-- 1. Schema View: Full-Height Vertical Scroll Table -->
            <div v-if="attrViewMode === 'schema'" class="flex-1 min-h-0 flex flex-col gap-2">
              <div class="relative shrink-0">
                <UIcon name="i-heroicons-magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                <input
                  v-model="attrSearch"
                  type="text"
                  placeholder="Cari nama kolom, tipe, atau arti KUGI..."
                  class="w-full h-8 pl-8 pr-7 rounded-lg bg-white dark:bg-[#0b0f19] border border-slate-200/80 dark:border-white/10 text-base sm:text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-hidden focus:border-blue-500"
                />
              </div>

              <div class="flex-1 min-h-0 border border-slate-200/80 dark:border-white/10 rounded-xl overflow-y-auto bg-white dark:bg-[#0b0f19] shadow-xs">
                <table class="w-full text-left border-collapse text-xs">
                  <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900 border-b border-slate-200 dark:border-white/10 z-10 font-mono shadow-xs">
                    <tr>
                      <th class="p-2 font-semibold text-slate-700 dark:text-slate-300">Nama Kolom</th>
                      <th class="p-2 font-semibold text-slate-700 dark:text-slate-300 font-sans">Definisi KUGI (BIG)</th>
                      <th class="p-2 font-semibold text-slate-700 dark:text-slate-300">Tipe Data</th>
                      <th class="p-2 font-semibold text-slate-700 dark:text-slate-300 font-sans">Sifat</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-white/5 bg-white dark:bg-[#0b0f19]">
                    <tr v-for="attr in filteredAttributes" :key="attr.name" class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                      <!-- Nama Kolom -->
                      <td class="p-2 font-mono font-semibold text-slate-900 dark:text-slate-100">
                        <div class="flex items-center gap-1.5">
                          <UIcon
                            :name="attr.is_geometry ? 'i-lucide-shapes' : (attr.local_type.includes('int') || attr.local_type.includes('float') || attr.local_type.includes('double') ? 'i-lucide-hash' : 'i-lucide-type')"
                            class="size-3.5"
                            :class="attr.is_geometry ? 'text-emerald-500' : 'text-slate-400'"
                          />
                          <span>{{ attr.name }}</span>
                        </div>
                      </td>

                      <!-- Definisi KUGI -->
                      <td class="p-2">
                        <div v-if="resolveKugiLabel(attr.name).isKugi" class="space-y-0.5">
                          <span class="text-slate-800 dark:text-slate-200 font-medium leading-tight block">
                            {{ resolveKugiLabel(attr.name).label }}
                          </span>
                          <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-xs font-mono font-semibold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                            <UIcon name="i-lucide-check-circle" class="size-2.5" />
                            Standar KUGI
                          </span>
                        </div>
                        <span v-else class="text-slate-400 italic text-xs">
                          Atribut Lokal / Terkait
                        </span>
                      </td>

                      <!-- Tipe Data -->
                      <td class="p-2 font-mono text-xs">
                        <span
                          class="px-1.5 py-0.5 rounded-md font-medium"
                          :class="attr.is_geometry
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                            : (attr.local_type.includes('int') || attr.local_type.includes('double') ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300')"
                        >
                          {{ attr.local_type || attr.type }}
                        </span>
                      </td>

                      <!-- Sifat -->
                      <td class="p-2 text-slate-500 text-xs">
                        <span v-if="attr.nillable" class="text-slate-400">Dapat Kosong</span>
                        <span v-else class="text-rose-500 font-semibold">Wajib Diisi</span>
                      </td>
                    </tr>
                    <tr v-if="filteredAttributes.length === 0">
                      <td colspan="4" class="p-4 text-center text-slate-400 italic">
                        Tidak ada atribut yang cocok dengan pencarian "{{ attrSearch }}".
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- 2. Sample Data View: Full-Height Horizontal & Vertical Scroll Table -->
            <div v-else-if="attrViewMode === 'sample'" class="flex-1 min-h-0 flex flex-col gap-2">
              <div v-if="attributesData?.sample_data.length === 0" class="p-4 text-center text-slate-400 italic">
                Sampel data baris tidak tersedia dari WFS GetFeature.
              </div>
              <div v-else class="flex-1 min-h-0 border border-slate-200/80 dark:border-white/10 rounded-xl overflow-auto bg-white dark:bg-[#0b0f19] shadow-xs">
                <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                  <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900 border-b border-slate-200 dark:border-white/10 z-20">
                    <tr>
                      <th class="p-2 font-semibold text-slate-500 bg-slate-100 dark:bg-slate-900 font-mono sticky left-0 top-0 z-30 border-r border-slate-200 dark:border-white/10 shadow-xs">#</th>
                      <th v-for="col in sampleColumns" :key="col" class="p-2 font-semibold text-slate-700 dark:text-slate-300">
                        <div class="space-y-0.5">
                          <span class="font-mono">{{ col }}</span>
                          <span
                            v-if="resolveKugiLabel(col).isKugi"
                            class="block text-xs font-sans font-normal text-emerald-600 dark:text-emerald-400 truncate max-w-[130px]"
                            :title="resolveKugiLabel(col).label"
                          >
                            {{ resolveKugiLabel(col).label }}
                          </span>
                        </div>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-white/5 bg-white dark:bg-[#0b0f19] font-mono">
                    <tr v-for="(row, idx) in attributesData?.sample_data" :key="idx" class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                      <td class="p-2 font-bold text-slate-400 bg-slate-50 dark:bg-slate-900 sticky left-0 z-10 border-r border-slate-200 dark:border-white/10">{{ idx + 1 }}</td>
                      <td v-for="col in sampleColumns" :key="col" class="p-2 text-slate-700 dark:text-slate-300 max-w-xs truncate" :title="String(row[col] ?? '')">
                        {{ row[col] !== null && row[col] !== undefined && row[col] !== '' ? row[col] : '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="shrink-0 flex items-center justify-between text-xs text-slate-400 italic">
                <span>* Menampilkan {{ attributesData?.sample_count }} baris sampel riil yang diambil melalui WFS GetFeature.</span>
                <span class="font-mono font-normal not-italic text-slate-400 hidden sm:inline">Scroll horizontal & vertikal untuk menjelajah tabel</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 3: METADATA KATALOG MELALUI pyCSW                                -->
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <div v-else-if="activeTab === 'csw'" class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-5 space-y-3.5 text-xs">
          <!-- Loading pyCSW -->
          <div v-if="loadingMeta" class="space-y-2 py-4">
            <div class="flex items-center gap-2 text-slate-500">
              <UIcon name="i-heroicons-arrow-path" class="size-4 animate-spin text-blue-600" />
              <span class="font-medium">Mengambil rekaman katalog dari server pyCSW 2.6.0...</span>
            </div>
            <div class="h-24 rounded-xl bg-slate-100 dark:bg-white/5 animate-pulse"></div>
          </div>

          <div v-else class="space-y-3">
            <!-- pyCSW Catalog Identity Grid -->
            <div class="p-3 rounded-xl border border-blue-200/80 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/20 space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="size-7 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                    CSW
                  </div>
                  <div>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">
                      {{ dcRecord?.title || props.layer.name }}
                    </h4>
                    <span class="font-mono text-xs text-slate-500">
                      ID: {{ dcRecord?.identifier || props.layer.geoportal_id || '-' }}
                    </span>
                  </div>
                </div>

                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                  pyCSW 2.6.0 (ISO 19139 / Dublin Core)
                </span>
              </div>

              <!-- Keywords / Subjects -->
              <div v-if="dcRecord?.subjects && dcRecord.subjects.length > 0" class="flex items-center gap-1 flex-wrap pt-1 border-t border-blue-100 dark:border-white/5">
                <span class="text-xs font-semibold text-slate-500 mr-1">Kata Kunci:</span>
                <span
                  v-for="(sub, i) in dcRecord.subjects"
                  :key="i"
                  class="px-1.5 py-0.5 rounded-md bg-white dark:bg-slate-900 text-xs font-medium text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10"
                >
                  {{ sub }}
                </span>
              </div>
            </div>

            <!-- Spatial Bounding Box & CRS Details -->
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-3">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-1.5">
                  <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400" />
                  <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                    Cakupan Spasial & Sistem Koordinat (CRS)
                  </span>
                </div>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="px-2 py-0.5 rounded-md font-mono text-xs font-bold bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/40">
                    {{ spatialExtent.crs }} (SRGI 2013)
                  </span>
                  <span class="px-2 py-0.5 rounded-md font-mono text-xs font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/40">
                    Skala {{ spatialExtent.scale }}
                  </span>
                </div>
              </div>

              <!-- 4-Point High-Precision Cardinal Bounding Box Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 tabular-nums">
                <!-- Batas Barat -->
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/70 dark:border-white/5 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-sans font-medium flex items-center gap-1">
                      <UIcon name="i-lucide-arrow-left" class="size-3 text-blue-500" />
                      <span>Batas Barat (West)</span>
                    </span>
                    <span class="text-slate-400 font-bold">BT</span>
                  </div>
                  <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" :title="String(spatialExtent.west)">
                    {{ formatCoord(spatialExtent.west, 7) }}°
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 font-sans">
                    {{ formatDms(spatialExtent.west, false) }}
                  </div>
                </div>

                <!-- Batas Timur -->
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/70 dark:border-white/5 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-sans font-medium flex items-center gap-1">
                      <UIcon name="i-lucide-arrow-right" class="size-3 text-blue-500" />
                      <span>Batas Timur (East)</span>
                    </span>
                    <span class="text-slate-400 font-bold">BT</span>
                  </div>
                  <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" :title="String(spatialExtent.east)">
                    {{ formatCoord(spatialExtent.east, 7) }}°
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 font-sans">
                    {{ formatDms(spatialExtent.east, false) }}
                  </div>
                </div>

                <!-- Batas Selatan -->
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/70 dark:border-white/5 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-sans font-medium flex items-center gap-1">
                      <UIcon name="i-lucide-arrow-down" class="size-3 text-amber-500" />
                      <span>Batas Selatan (South)</span>
                    </span>
                    <span class="text-slate-400 font-bold">LS</span>
                  </div>
                  <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" :title="String(spatialExtent.south)">
                    {{ formatCoord(spatialExtent.south, 7) }}°
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 font-sans">
                    {{ formatDms(spatialExtent.south, true) }}
                  </div>
                </div>

                <!-- Batas Utara -->
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/70 dark:border-white/5 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-sans font-medium flex items-center gap-1">
                      <UIcon name="i-lucide-arrow-up" class="size-3 text-amber-500" />
                      <span>Batas Utara (North)</span>
                    </span>
                    <span class="text-slate-400 font-bold">LS</span>
                  </div>
                  <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" :title="String(spatialExtent.north)">
                    {{ formatCoord(spatialExtent.north, 7) }}°
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 font-sans">
                    {{ formatDms(spatialExtent.north, true) }}
                  </div>
                </div>
              </div>

              <!-- Spatial Diagnostics & Quick Copy Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 pt-1 tabular-nums text-xs">
                <!-- Centroid & Dimensions -->
                <div class="p-2.5 rounded-xl bg-slate-50/70 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 flex items-center justify-between gap-2">
                  <div class="min-w-0 space-y-0.5">
                    <span class="text-slate-400 font-sans block text-xs">Titik Pusat (Centroid / Center):</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 truncate block">
                      {{ spatialExtent.centerStr }}
                    </span>
                    <span class="text-xs text-slate-500 font-sans block">
                      Rentang: ~{{ spatialExtent.spanLonKm }} km (Bujur) × ~{{ spatialExtent.spanLatKm }} km (Lintang)
                    </span>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 p-1.5 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center gap-1 transition-colors cursor-pointer"
                    title="Salin koordinat titik pusat"
                    @click="copyText(spatialExtent.centerStr, 'bbox_center')"
                  >
                    <UIcon :name="copiedField === 'bbox_center' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" :class="{ 'text-emerald-500': copiedField === 'bbox_center' }" />
                    <span class="text-xs font-sans font-medium">{{ copiedField === 'bbox_center' ? 'Disalin' : 'Salin' }}</span>
                  </button>
                </div>

                <!-- BBOX GeoJSON / WMS Array -->
                <div class="p-2.5 rounded-xl bg-slate-50/70 dark:bg-white/[0.02] border border-slate-200/60 dark:border-white/5 flex items-center justify-between gap-2">
                  <div class="min-w-0 space-y-0.5">
                    <span class="text-slate-400 font-sans block text-xs">GeoJSON BBOX [W, S, E, N]:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 truncate block font-mono" :title="spatialExtent.geojsonBbox">
                      {{ spatialExtent.geojsonBbox }}
                    </span>
                    <span class="text-xs text-slate-500 font-sans block">
                      Cakupan: Wilayah Kabupaten Bojonegoro
                    </span>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 p-1.5 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center gap-1 transition-colors cursor-pointer"
                    title="Salin bounding box GeoJSON [W, S, E, N]"
                    @click="copyText(spatialExtent.geojsonBbox, 'bbox_json')"
                  >
                    <UIcon :name="copiedField === 'bbox_json' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" :class="{ 'text-emerald-500': copiedField === 'bbox_json' }" />
                    <span class="text-xs font-sans font-medium">{{ copiedField === 'bbox_json' ? 'Disalin' : 'Salin' }}</span>
                  </button>
                </div>
              </div>

              <!-- Supplemental Information (jika ada catatan spasial resmi) -->
              <div v-if="isoRecord?.supplemental_info" class="p-2.5 rounded-lg bg-blue-50/40 dark:bg-blue-950/20 border border-blue-200/60 dark:border-blue-900/30 text-xs space-y-1">
                <span class="font-semibold text-blue-800 dark:text-blue-300 flex items-center gap-1">
                  <UIcon name="i-lucide-info" class="size-3" />
                  <span>Keterangan Cakupan Spasial Tambahan (ISO 19139):</span>
                </span>
                <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-xs">
                  {{ isoRecord.supplemental_info }}
                </p>
              </div>
            </div>

            <!-- Responsible Party & Dates -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-sans text-xs">
              <div class="p-3 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-1">
                <span class="text-slate-400 text-xs font-sans">Lembaga Pembuat / Kontak:</span>
                <p class="font-bold text-slate-800 dark:text-slate-200">
                  {{ isoRecord?.contact?.organisation || dcRecord?.publisher || props.layer.opd_name || 'Bappeda Bojonegoro' }}
                </p>
                <span class="text-xs text-slate-500">Peran: {{ isoRecord?.contact?.role || 'pointOfContact' }}</span>
              </div>

              <div class="p-3 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-1">
                <span class="text-slate-400 text-xs font-sans">Tanggal Terbit & Modifikasi:</span>
                <p class="font-bold text-slate-800 dark:text-slate-200">
                  {{ dcRecord?.date || isoRecord?.date_stamp || '2021-04-29' }}
                </p>
                <span class="text-xs text-slate-500">Hak Cipta: {{ dcRecord?.rights || 'JIGN Kab. Bojonegoro' }}</span>
              </div>
            </div>

            <!-- OGC Service Endpoints with Copy Button -->
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2">
              <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                <UIcon name="i-lucide-link-2" class="size-3.5 text-blue-600" />
                <span>Endpoint Layanan OGC Terkait (JIGN Bojonegoro)</span>
              </span>

              <div class="space-y-1.5 font-mono text-xs">
                <!-- WMS URL -->
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

                <!-- WFS URL -->
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

                <!-- CSW URL -->
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

            <!-- Dokumen Metadata Standar (pyCSW XML - Opsi 3: Kombinasi Resmi + Mirror + Unduh) -->
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                  <UIcon name="i-lucide-file-code-2" class="size-3.5 text-blue-600 dark:text-blue-400" />
                  <span>Dokumen Metadata Standar (pyCSW XML)</span>
                </span>
                <span class="text-xs text-slate-400 font-mono hidden sm:inline">
                  pyCSW 2.6.0 • CSW 2.0.2
                </span>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Card 1: ISO 19139 -->
                <div class="p-3 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] flex flex-col justify-between gap-3 hover:border-blue-200 dark:hover:border-blue-900/40 transition-colors">
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/40">
                        ISO 19139 (GMD)
                      </span>
                      <span class="text-xs text-slate-400 font-mono">
                        SNI / JIGN
                      </span>
                    </div>
                    <div>
                      <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100">
                        Metadata Geospasial ISO 19139
                      </h4>
                      <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mt-0.5">
                        Standar skema metadata spasial ISO 19115/19139 SNI resmi untuk pertukaran dan integrasi simpul jaringan nasional.
                      </p>
                    </div>
                  </div>

                  <!-- Action Buttons -->
                  <div class="space-y-1.5 pt-2 border-t border-slate-200/60 dark:border-white/5">
                    <!-- Secondary Actions Row -->
                    <div class="grid grid-cols-3 gap-1.5">
                      <!-- Primary Button: Buka Sumber pyCSW Resmi -->
                      <button
                        type="button"
                        class="w-full px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium text-xs flex items-center justify-between gap-2 transition-colors cursor-pointer shadow-xs"
                        title="Buka langsung dari server pyCSW Geoportal Resmi Bojonegoro di tab baru"
                        @click="openDirectCswUrl('iso')"
                      >
                        <span class="flex items-center gap-1.5 min-w-0">
                          <UIcon name="i-lucide-external-link" class="size-3.5 shrink-0" />
                          <span class="font-semibold truncate">pyCSW Resmi</span>
                        </span>
                      </button>
                      <button
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        title="Buka XML melalui proxy/cache internal Melarosa (cadangan jika akses portal langsung terkendala)"
                        @click="openMelarosaXmlUrl('iso')"
                      >
                        <UIcon name="i-lucide-globe" class="size-3 text-slate-400" />
                        <span class="truncate">Mirror</span>
                      </button>

                      <button
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        title="Unduh file XML ISO 19139 ke komputer lokal"
                        @click="downloadXml('iso')"
                      >
                        <UIcon name="i-lucide-download" class="size-3 text-emerald-600 dark:text-emerald-400" />
                        <span class="truncate">Unduh XML</span>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Card 2: Dublin Core -->
                <div class="p-3 rounded-xl border border-slate-200/90 dark:border-white/10 bg-slate-50/60 dark:bg-white/[0.02] flex flex-col justify-between gap-3 hover:border-indigo-200 dark:hover:border-indigo-900/40 transition-colors">
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                      <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/40">
                        Dublin Core (Record)
                      </span>
                      <span class="text-xs text-slate-400 font-mono">
                        CSW 2.0.2
                      </span>
                    </div>
                    <div>
                      <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100">
                        Metadata Rekaman Dublin Core
                      </h4>
                      <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mt-0.5">
                        Ringkasan rekaman metadata standar OGC CSW 2.0.2 Dublin Core untuk penemuan cepat dan harvesting katalog.
                      </p>
                    </div>
                  </div>

                  <!-- Action Buttons -->
                  <div class="space-y-1.5 pt-2 border-t border-slate-200/60 dark:border-white/5">
                    <!-- Secondary Actions Row -->
                    <div class="grid grid-cols-3 gap-1.5">
                      <!-- Primary Button: Buka Sumber pyCSW Resmi -->
                      <button
                        type="button"
                        class="w-full px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-medium text-xs flex items-center justify-between gap-2 transition-colors cursor-pointer shadow-xs"
                        title="Buka langsung dari server pyCSW Geoportal Resmi Bojonegoro di tab baru"
                        @click="openDirectCswUrl('dc')"
                      >
                        <span class="flex items-center gap-1.5 min-w-0">
                          <UIcon name="i-lucide-external-link" class="size-3.5 shrink-0" />
                          <span class="font-semibold truncate">pyCSW Resmi</span>
                        </span>
                      </button>
                      <button
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        title="Buka XML melalui proxy/cache internal Melarosa (cadangan jika akses portal langsung terkendala)"
                        @click="openMelarosaXmlUrl('dc')"
                      >
                        <UIcon name="i-lucide-globe" class="size-3 text-slate-400" />
                        <span class="truncate">Mirror</span>
                      </button>

                      <button
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        title="Unduh file XML Dublin Core ke komputer lokal"
                        @click="downloadXml('dc')"
                      >
                        <UIcon name="i-lucide-download" class="size-3 text-emerald-600 dark:text-emerald-400" />
                        <span class="truncate">Unduh XML</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Tutup single scrollable content container -->
        </div>
      </div>
      <div v-else class="flex-1 flex items-center justify-center p-8 text-slate-400 text-xs">
        Pilih layer simpul jaringan untuk melihat informasi metadata.
      </div>
    </template>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <span class="text-xs text-slate-400 font-mono">
          JIGN Bojonegoro • Simpul Jaringan Nasional
        </span>
        <UButton
          label="Tutup"
          color="neutral"
          variant="ghost"
          class="cursor-pointer"
          @click="isOpen = false"
        />
      </div>
    </template>
  </UModal>
</template>

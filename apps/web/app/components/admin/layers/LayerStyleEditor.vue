<script setup lang="ts">
import type { LayerStyleConfig, StyleRule, GeometryType, DashPreset, SymbolRenderMode, LabelRenderMode } from "~/types/map-layers";
import {
  buildWmsGetLegendUrl,
  buildWmsSldXml,
  hasWmsCustomStyle,
  parseCssColorToSld,
  resolveSldNamedLayerName,
  shouldUseWmsPostForSld,
} from "~/utils/wms-sld";

const props = withDefaults(
  defineProps<{
    modelValue?: LayerStyleConfig | null;
    protocol?: string;
    layerId?: string | null;
    layerName?: string | null;
    wmsUrl?: string | null;
    legendUrl?: string | null;
  }>(),
  {
    modelValue: null,
    protocol: "mvt",
    layerId: null,
    layerName: null,
    wmsUrl: null,
    legendUrl: null,
  }
);

const emit = defineEmits<{
  (e: "update:modelValue", value: LayerStyleConfig | null): void;
}>();

const isWmsProtocol = computed(() => String(props.protocol || "").toLowerCase().includes("wms"));
const isWmsCustomActive = computed(() => isWmsProtocol.value && hasWmsCustomStyle(props.modelValue));

// Palette warna cepat kartografi
const quickColors = [
  { name: "Biru Melarosa", hex: "#2563eb" },
  { name: "Hijau Emerald", hex: "#10b981" },
  { name: "Langit Cerah", hex: "#0284c7" },
  { name: "Kuning Sedang", hex: "#f59e0b" },
  { name: "Oranye Rusak Ringan", hex: "#f97316" },
  { name: "Merah Rusak Berat", hex: "#ef4444" },
  { name: "Abu Batas Wilayah", hex: "#475569" },
  { name: "Ungu Teritorial", hex: "#8b5cf6" },
  { name: "Gelap Pekat", hex: "#1e293b" },
];

// Presets standar
const presets = [
  {
    id: "road_condition",
    name: "Kondisi Jalan (Baik, Sedang, Rusak)",
    desc: "Warna adaptif sesuai nilai kondisi: Baik (Hijau), Sedang (Kuning), Rusak (Merah)",
    geom_type: "line" as GeometryType,
    config: {
      geom_type: "line" as GeometryType,
      color: "#10b981",
      width: 3,
      dash: "solid" as DashPreset,
      casing: false,
      casing_color: "rgba(16, 185, 129, 0.25)",
      casing_width: 6,
      fill_color: "rgba(16, 185, 129, 0.1)",
      label: {
        enabled: true,
        field: "namobj",
        min_zoom: 13.5,
        color: "#064e3b",
        halo_color: "#ffffff",
        halo_width: 3,
        font_size: 11,
        placement: "line",
      },
      rules: [
        { id: "r1", field: "kondisi", operator: "contains", value: "rusak berat", color: "#ef4444", width: 3.5 },
        { id: "r2", field: "kondisi", operator: "contains", value: "rusak ringan", color: "#f97316", width: 3 },
        { id: "r3", field: "kondisi", operator: "contains", value: "sedang", color: "#f59e0b", width: 3 },
        { id: "r4", field: "kondisi", operator: "contains", value: "rusak", color: "#f97316", width: 3 },
        { id: "r5", field: "kondisi", operator: "contains", value: "baik", color: "#10b981", width: 3 },
      ],
      zoom_scale: true,
    },
  },
  {
    id: "admin_boundary",
    name: "Batas Wilayah / Administrasi",
    desc: "Garis batas strip-titik (Dash-Dot) dengan casing halo kartografi resmi",
    geom_type: "polygon" as GeometryType,
    config: {
      geom_type: "polygon" as GeometryType,
      color: "#475569",
      width: 2,
      dash: "dash-dot" as DashPreset,
      fill_color: "rgba(71, 85, 105, 0.03)",
      casing: true,
      casing_color: "rgba(148, 163, 184, 0.25)",
      casing_width: 5.5,
      label: {
        enabled: true,
        field: "namobj",
        min_zoom: 12,
        color: "#334155",
        halo_color: "#ffffff",
        halo_width: 2,
        font_size: 11,
        placement: "point",
        orientation: "horizontal",
      },
      rules: [],
      zoom_scale: true,
    },
  },
  {
    id: "standard_road",
    name: "Trase Jalan Standar",
    desc: "Garis solid tegas warna biru kartografi dengan teks label ruas",
    geom_type: "line" as GeometryType,
    config: {
      geom_type: "line" as GeometryType,
      color: "#2563eb",
      width: 2.5,
      dash: "solid" as DashPreset,
      casing: false,
      fill_color: "rgba(37, 99, 235, 0.1)",
      label: {
        enabled: true,
        field: "nama_ruas",
        min_zoom: 13,
        color: "#1e3a8a",
        halo_color: "#ffffff",
        halo_width: 3,
        font_size: 11,
        placement: "line",
      },
      rules: [],
      zoom_scale: true,
    },
  },
  {
    id: "polygon_area",
    name: "Area Zonasi / Poligon Wilayah",
    desc: "Poligon transparan dengan garis tepi tegas untuk zonasi atau tata ruang",
    geom_type: "polygon" as GeometryType,
    config: {
      geom_type: "polygon" as GeometryType,
      color: "#8b5cf6",
      width: 1.75,
      dash: "solid" as DashPreset,
      fill_color: "rgba(139, 92, 246, 0.15)",
      casing: false,
      label: {
        enabled: true,
        field: "namobj",
        min_zoom: 12,
        color: "#5b21b6",
        halo_color: "#ffffff",
        halo_width: 2,
        font_size: 11,
        placement: "point",
        orientation: "horizontal",
      },
      rules: [],
      zoom_scale: false,
    },
  },
  {
    id: "point_marker",
    name: "Titik / Fasilitas Spasial",
    desc: "Simbol titik lingkaran (Point of Interest) dengan border kontras",
    geom_type: "point" as GeometryType,
    config: {
      geom_type: "point" as GeometryType,
      color: "#0284c7",
      width: 2,
      point_radius: 6,
      point_color: "#0284c7",
      point_border_color: "#ffffff",
      point_border_width: 2,
      label: {
        enabled: true,
        field: "namobj",
        min_zoom: 14,
        color: "#0369a1",
        halo_color: "#ffffff",
        halo_width: 3,
      },
      rules: [],
      zoom_scale: false,
    },
  },
];

function inferGeomFromLayerName(name?: string | null): GeometryType {
  const upper = String(name || "").toUpperCase();
  if (upper.includes("_AR_") || upper.includes("_AR") || upper.includes("POLARUANG") || upper.includes("ADMINISTRASIDESA") || upper.includes("BATAS")) {
    return "polygon";
  }
  if (
    upper.includes("_PT_") ||
    upper.endsWith("_PT") ||
    upper.includes("PT_") ||
    upper.includes("POINT") ||
    upper.includes("JEMBATAN") ||
    upper.includes("TITIK") ||
    upper.includes("KESEHATAN") ||
    upper.includes("FASKES") ||
    upper.includes("PUSKESMAS") ||
    upper.includes("SEKOLAH") ||
    upper.includes("PENDIDIKAN") ||
    upper.includes("KANTOR") ||
    upper.includes("PASAR") ||
    upper.includes("MENARA") ||
    upper.includes("TOWER") ||
    upper.includes("WISATA") ||
    upper.includes("HALTE") ||
    upper.includes("STASIUN") ||
    upper.includes("TERMINAL") ||
    upper.includes("SPBU")
  ) {
    return "point";
  }
  return "line";
}

// Helper normalisasi konfigurasi style
function getInitialConfig(source?: LayerStyleConfig | null): LayerStyleConfig {
  const defaultGeom = source?.geom_type || inferGeomFromLayerName(props.layerName);
  const inferredSymbolMode: SymbolRenderMode =
    source?.symbol_mode ||
    (Array.isArray(source?.rules) && source.rules.length > 0 ? "rule_based" : "single");

  return {
    geom_type: defaultGeom,
    symbol_mode: inferredSymbolMode,
    classify_field: source?.classify_field || "",
    color_ramp: source?.color_ramp || "spectral",
    graduated_classes: source?.graduated_classes ?? 5,
    color: source?.color || "#2563eb",
    width: source?.width ?? 2.5,
    dash: source?.dash || "solid",
    line_cap: source?.line_cap || "round",
    line_join: source?.line_join || "round",
    fill_color: source?.fill_color || "rgba(37, 99, 235, 0.15)",
    casing: Boolean(source?.casing),
    casing_color: source?.casing_color || "rgba(37, 99, 235, 0.25)",
    casing_width: source?.casing_width ?? 5,
    point_radius: source?.point_radius ?? 6,
    point_color: source?.point_color || "#2563eb",
    point_border_color: source?.point_border_color || "#ffffff",
    point_border_width: source?.point_border_width ?? 2,
    label: {
      enabled: Boolean(source?.label?.enabled),
      mode:
        source?.label?.mode ||
        (Array.isArray(source?.label?.rules) && source.label.rules.length > 0 ? "rule_based" : "single"),
      field: source?.label?.field || "namobj",
      rules: source?.label?.rules ? JSON.parse(JSON.stringify(source.label.rules)) : [],
      min_zoom: source?.label?.min_zoom ?? 13,
      color: source?.label?.color || "#1e3a8a",
      halo_color: source?.label?.halo_color || "#ffffff",
      halo_width: source?.label?.halo_width ?? 3,
      font_size: source?.label?.font_size ?? 11,
      font_family: source?.label?.font_family || "SansSerif",
      font_style: source?.label?.font_style || "bold",
      opacity: source?.label?.opacity ?? 1,
      placement: source?.label?.placement || (defaultGeom === "line" ? "line" : "point"),
      orientation: source?.label?.orientation || "horizontal",
      align: source?.label?.align || "center",
      rotation: source?.label?.rotation ?? 0,
      offset_x: source?.label?.offset_x ?? 0,
      offset_y: source?.label?.offset_y ?? 0,
      overflow: Boolean(source?.label?.overflow),
      wrap: source?.label?.wrap !== false,
      wrap_width: source?.label?.wrap_width ?? 90,
    },
    rules: source?.rules ? JSON.parse(JSON.stringify(source.rules)) : [],
    zoom_scale: source?.zoom_scale !== false,
    min_zoom: source?.min_zoom ?? null,
    sld_mode: source?.sld_mode || "visual",
    custom_sld_xml: source?.custom_sld_xml ?? null,
  };
}

// Inisialisasi state lokal
const localState = reactive<LayerStyleConfig>(getInitialConfig(props.modelValue));

let isInternalEmit = false;
let isUpdatingFromProps = false;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Emit update secara debounced untuk mencegah frame-drop saat drag slider atau mengetik
function emitUpdate() {
  if (isUpdatingFromProps) return;
  if (debounceTimer) clearTimeout(debounceTimer);

  debounceTimer = setTimeout(() => {
    if (!isRawJsonMode.value) {
      isInternalEmit = true;
      emit("update:modelValue", JSON.parse(JSON.stringify(localState)));
    }
  }, 100);
}

// Sinkronisasi saat props.modelValue berubah dari luar
watch(
  () => props.modelValue,
  (newVal) => {
    if (isInternalEmit) {
      isInternalEmit = false;
      return;
    }
    if (!newVal) {
      isUpdatingFromProps = true;
      Object.assign(localState, getInitialConfig(null));
      if (isRawJsonMode.value) {
        syncRawJson();
      }
      nextTick(() => {
        isUpdatingFromProps = false;
      });
      return;
    }

    isUpdatingFromProps = true;
    const fresh = getInitialConfig(newVal);
    Object.assign(localState, fresh);
    if (isRawJsonMode.value) {
      syncRawJson();
    }
    nextTick(() => {
      isUpdatingFromProps = false;
    });
  },
  { deep: true }
);

// Sinkronisasi point_color <-> color saat mode Titik aktif & pastikan placement label sesuai geometri
function applyPrimaryColor(hex: string) {
  const prevColor = localState.color;
  if (localState.geom_type === "point") {
    localState.point_color = hex;
    localState.color = hex;
    return;
  }
  localState.color = hex;
  if (localState.geom_type === "auto" && (!localState.point_color || localState.point_color === prevColor)) {
    localState.point_color = hex;
  }
}

watch(
  () => localState.point_color,
  (val) => {
    if (!isUpdatingFromProps && localState.geom_type === "point" && val) {
      localState.color = val;
    }
  }
);

watch(
  () => localState.geom_type,
  (g) => {
    if (!isUpdatingFromProps && g === "point" && localState.label && localState.label.placement === "line") {
      localState.label.placement = "point";
    }
  }
);

// Emit update saat localState berubah dari interaksi pengguna
watch(
  localState,
  () => {
    if (!isUpdatingFromProps) {
      emitUpdate();
    }
  },
  { deep: true }
);

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
    if (!isRawJsonMode.value && !isUpdatingFromProps) {
      emit("update:modelValue", JSON.parse(JSON.stringify(localState)));
    }
  }
});

// Mode Raw JSON & Mode XML SLD (khusus WMS)
const isRawJsonMode = ref(false);
const rawJsonText = ref("");
const jsonError = ref<string | null>(null);
const showWmsSldPreview = ref(false);
const wmsLegendPreviewFailed = ref(false);

const effectiveLayerNameForSld = computed(() =>
  resolveSldNamedLayerName(props.layerName?.trim() || "palapa:layer", props.wmsUrl)
);

const generatedSldXml = computed(() => {
  if (!isWmsProtocol.value) return "";
  if (localState.sld_mode === "xml" && localState.custom_sld_xml?.trim()) {
    return localState.custom_sld_xml.trim();
  }
  const previewCopy: LayerStyleConfig = {
    ...JSON.parse(JSON.stringify(localState)),
    sld_mode: "visual",
    custom_sld_xml: null,
  };
  return buildWmsSldXml(effectiveLayerNameForSld.value, previewCopy, props.wmsUrl) || "";
});

const sldByteLength = computed(() => generatedSldXml.value.length);
const sldUsesPostTransport = computed(() => {
  if (!generatedSldXml.value) return false;
  return shouldUseWmsPostForSld(props.wmsUrl || "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms", generatedSldXml.value);
});

const wmsLiveLegendUrl = computed(() => {
  if (!isWmsProtocol.value || !props.wmsUrl || !props.layerName) return null;
  if (!hasWmsCustomStyle(localState) && !isWmsCustomActive.value) {
    return props.legendUrl || null;
  }
  return buildWmsGetLegendUrl(props.wmsUrl, props.layerName, localState, props.legendUrl);
});

watch(wmsLiveLegendUrl, () => {
  wmsLegendPreviewFailed.value = false;
});

function handleSwitchToSldXmlMode() {
  isRawJsonMode.value = false;
  activeStyleGroup.value = "kode";
  if (!localState.custom_sld_xml?.trim()) {
    const previewCopy: LayerStyleConfig = {
      ...JSON.parse(JSON.stringify(localState)),
      sld_mode: "visual",
      custom_sld_xml: null,
    };
    localState.custom_sld_xml = buildWmsSldXml(effectiveLayerNameForSld.value, previewCopy, props.wmsUrl) || "";
  }
  localState.sld_mode = "xml";
}

function handleSwitchToVisualFromXml() {
  localState.sld_mode = "visual";
  activeStyleGroup.value = "simbol";
}

function handleRegenerateXmlFromVisual() {
  const previewCopy: LayerStyleConfig = {
    ...JSON.parse(JSON.stringify(localState)),
    sld_mode: "visual",
    custom_sld_xml: null,
  };
  localState.custom_sld_xml = buildWmsSldXml(effectiveLayerNameForSld.value, previewCopy, props.wmsUrl) || "";
}

function handleResetWmsToDefaultSld() {
  if (debounceTimer) clearTimeout(debounceTimer);
  isUpdatingFromProps = true;
  Object.assign(localState, getInitialConfig(null));
  isRawJsonMode.value = false;
  isInternalEmit = true;
  emit("update:modelValue", null);
  nextTick(() => {
    isUpdatingFromProps = false;
  });
}

function syncRawJson() {
  rawJsonText.value = JSON.stringify(localState, null, 2);
  jsonError.value = null;
}

function handleToggleMode() {
  if (isRawJsonMode.value) {
    // Berpindah dari Raw JSON ke Form Visual -> parse JSON
    try {
      const parsed = JSON.parse(rawJsonText.value);
      isUpdatingFromProps = true;
      Object.assign(localState, parsed);
      jsonError.value = null;
      isRawJsonMode.value = false;
      isInternalEmit = true;
      emit("update:modelValue", JSON.parse(JSON.stringify(localState)));
      nextTick(() => {
        isUpdatingFromProps = false;
      });
    } catch (e: any) {
      jsonError.value = `Format JSON tidak valid: ${e.message}`;
    }
  } else {
    // Berpindah dari Form Visual ke Raw JSON
    syncRawJson();
    isRawJsonMode.value = true;
    activeStyleGroup.value = "kode";
  }
}

function handleFormatJson() {
  try {
    const parsed = JSON.parse(rawJsonText.value);
    rawJsonText.value = JSON.stringify(parsed, null, 2);
    jsonError.value = null;
    isInternalEmit = true;
    emit("update:modelValue", parsed);
  } catch (e: any) {
    jsonError.value = `Format JSON tidak valid: ${e.message}`;
  }
}

function applyPreset(preset: typeof presets[0]) {
  isInternalEmit = true;
  Object.assign(localState, getInitialConfig(JSON.parse(JSON.stringify(preset.config))));
  if (isRawJsonMode.value) {
    syncRawJson();
  }
  emit("update:modelValue", JSON.parse(JSON.stringify(localState)));
}

// Manajemen Aturan Kondisional (Rules)
const customRuleFieldMap = reactive<Record<string, boolean>>({});

function isGeometryColumn(attr: { name: string; type?: string }): boolean {
  const nameLower = attr.name.toLowerCase();
  const typeLower = String(attr.type || "").toLowerCase();
  return (
    nameLower === "geom" ||
    nameLower === "the_geom" ||
    nameLower === "wkb_geometry" ||
    typeLower.includes("gml:") ||
    typeLower.includes("geometry")
  );
}

const nonGeometryAttributeOptions = computed(() => {
  if (layerAttributes.value.length === 0) {
    return attributeOptions.value;
  }
  const filtered = layerAttributes.value.filter((a) => !isGeometryColumn(a));
  const source = filtered.length > 0 ? filtered : layerAttributes.value;
  return source.map((attr) => ({
    label: `${attr.name} (${attr.type})`,
    value: attr.name,
    data_type: attr.type,
    sample_value: attr.sample_value,
  }));
});

function getRuleAttributeOptions(currentField?: string) {
  const opts = nonGeometryAttributeOptions.value;
  const trimmed = String(currentField || "").trim();
  if (trimmed && !opts.some((o) => o.value === trimmed)) {
    return [
      { label: `${trimmed} (tersimpan)`, value: trimmed, data_type: "string", sample_value: null },
      ...opts,
    ];
  }
  return opts;
}

function getRuleAttributeSample(fieldName?: string): string | null {
  const trimmed = String(fieldName || "").trim().toLowerCase();
  if (!trimmed) return null;
  const found = layerAttributes.value.find((a) => a.name.toLowerCase() === trimmed);
  return found?.sample_value ? String(found.sample_value) : null;
}

function resolveDefaultRuleField(): string {
  const candidates = layerAttributes.value.filter((a) => !isGeometryColumn(a));
  const list = candidates.length > 0 ? candidates : layerAttributes.value;
  if (list.length > 0) {
    const preferred =
      list.find((a) =>
        ["kondisi", "status", "kelas", "kategori", "jenis", "fungsi", "fcode", "namobj", "nama"].includes(
          a.name.toLowerCase()
        )
      ) || list[0];
    if (preferred) return preferred.name;
  }
  return attributeOptions.value[0]?.value || "kondisi";
}

function addRule() {
  if (!localState.rules) localState.rules = [];
  localState.rules.push({
    id: `rule_${Date.now()}`,
    field: resolveDefaultRuleField(),
    operator: "contains",
    value: "",
    color: "#ef4444",
    width: localState.width,
  });
}

function removeRule(index: number) {
  if (!localState.rules) return;
  localState.rules.splice(index, 1);
}

// Opsi dropdown geometri
const geomOptions = [
  { label: "Garis (LineString)", value: "line" },
  { label: "Poligon (Polygon / Area)", value: "polygon" },
  { label: "Titik (Point / Titik Lokasi)", value: "point" },
  { label: "Otomatis (Sesuai Fitur)", value: "auto" },
];

// Opsi bentuk garis sesuai standar kartografi (BIG / Peta RBI) & bentuk umum
const dashOptions = [
  [
    { type: "label", label: "Standar Batas Administrasi" },
    {
      label: "Batas Negara (— — — —)",
      value: "batas-negara",
      description: "Garis putus-putus tebal",
      dasharray: "14 5",
      strokeWidth: 2.8,
    },
    {
      label: "Batas Propinsi (— • — • —)",
      value: "batas-provinsi",
      description: "Garis diselingi 1 titik",
      dasharray: "14 4 2.5 4",
      strokeWidth: 2.2,
    },
    {
      label: "Batas Kabupaten (— • • — • • —)",
      value: "batas-kabupaten",
      description: "Garis diselingi 2 titik",
      dasharray: "14 3.5 2.5 3 2.5 3.5",
      strokeWidth: 2,
    },
    {
      label: "Batas Kecamatan (— • • • — • • • —)",
      value: "batas-kecamatan",
      description: "Garis diselingi 3 titik",
      dasharray: "14 3.5 2.5 3 2.5 3 2.5 3.5",
      strokeWidth: 1.8,
    },
    {
      label: "Batas Desa (— • • • • —)",
      value: "batas-desa",
      description: "Garis diselingi 4 titik",
      dasharray: "14 3.5 2.5 3 2.5 3 2.5 3.5",
      strokeWidth: 1.5,
    },
  ],
  [
    { type: "label", label: "Bentuk Garis Umum" },
    {
      label: "Solid (Garis Lurus Penuh)",
      value: "solid",
      description: "Garis utuh tanpa jeda",
      dasharray: undefined,
      strokeWidth: 2,
    },
    {
      label: "Dash (Putus-putus Standar)",
      value: "dash",
      description: "Garis putus-putus renggang",
      dasharray: "8 5",
      strokeWidth: 2,
    },
    {
      label: "Dot (Titik-titik Rapat)",
      value: "dot",
      description: "Rangkaian titik rapat",
      dasharray: "2 4",
      strokeWidth: 2,
    },
  ],
];

const currentDashItem = computed(() => {
  const d = localState.dash;
  const flat = dashOptions.flat();
  return flat.find((o) => (o as any).value === d) || flat.find((o) => (o as any).value === "solid");
});



const operatorOptions = [
  { label: "Mengandung (Contains)", value: "contains" },
  { label: "Sama dengan (Equals)", value: "equals" },
  { label: "Diawali (Starts with)", value: "starts_with" },
  { label: "Lebih besar ( > )", value: "gt" },
  { label: "Lebih kecil ( < )", value: "lt" },
  { label: "Rentang Nilai (Between)", value: "between" },
];

const symbolModeTabs: Array<{ key: SymbolRenderMode; label: string; desc: string; icon: string }> = [
  { key: "single", label: "Single Symbol", desc: "Satu gaya seragam", icon: "i-lucide-circle-dot" },
  { key: "categorized", label: "Categorized", desc: "Berdasarkan kategori unik", icon: "i-lucide-tags" },
  { key: "graduated", label: "Graduated", desc: "Rentang angka bergradasi", icon: "i-lucide-bar-chart-3" },
  { key: "rule_based", label: "Rule Based", desc: "Aturan kondisi kustom", icon: "i-lucide-git-branch" },
];

const CATEGORIZED_PALETTES: Record<string, { label: string; colors: string[] }> = {
  spectral: {
    label: "Klasik GIS (Spectral)",
    colors: ["#2563eb", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#06b6d4", "#ec4899", "#84cc16", "#f97316", "#64748b"],
  },
  vibrant: {
    label: "Kontras Tajam",
    colors: ["#e11d48", "#0284c7", "#16a34a", "#d97706", "#7c3aed", "#0d9488", "#ea580c", "#4f46e5", "#65a30d", "#db2777"],
  },
  pastel: {
    label: "Lembut Kartografi",
    colors: ["#60a5fa", "#34d399", "#fbbf24", "#f87171", "#a78bfa", "#22d3ee", "#f472b6", "#a3e635", "#fb923c", "#94a3b8"],
  },
  earth: {
    label: "Tata Ruang & Alam",
    colors: ["#15803d", "#ca8a04", "#9a3412", "#0369a1", "#4d7c0f", "#b45309", "#1d4ed8", "#0f766e", "#7e22ce", "#57534e"],
  },
};

const GRADUATED_RAMPS: Record<string, { label: string; stops: string[] }> = {
  blue_red: {
    label: "Biru -> Kuning -> Merah",
    stops: ["#2563eb", "#06b6d4", "#eab308", "#f97316", "#dc2626"],
  },
  green_red: {
    label: "Hijau -> Kuning -> Merah",
    stops: ["#16a34a", "#84cc16", "#eab308", "#f97316", "#dc2626"],
  },
  blues: {
    label: "Gradasi Biru",
    stops: ["#dbeafe", "#93c5fd", "#3b82f6", "#1d4ed8", "#1e3a8a"],
  },
  purples: {
    label: "Gradasi Ungu",
    stops: ["#ede9fe", "#c4b5fd", "#8b5cf6", "#6d28d9", "#4c1d95"],
  },
  reds: {
    label: "Gradasi Merah",
    stops: ["#fee2e2", "#fca5a5", "#ef4444", "#b91c1c", "#7f1d1d"],
  },
};

const categorizedPaletteItems = Object.entries(CATEGORIZED_PALETTES).map(([value, cfg]) => ({
  label: cfg.label,
  value,
}));

const graduatedRampItems = Object.entries(GRADUATED_RAMPS).map(([value, cfg]) => ({
  label: cfg.label,
  value,
}));

const graduatedClassCountOptions = [3, 4, 5, 6, 7].map((n) => ({
  label: `${n} Kelas`,
  value: n,
}));

const labelPlacementOptions = [
  { label: "Titik Tengah / Centroid (Rekomendasi Area & Titik)", value: "point" },
  { label: "Mengikuti Kontur Garis / Keliling Batas", value: "line" },
];

const labelOrientationOptions = [
  { label: "Horizontal (Mendatar / 0°)", value: "horizontal" },
  { label: "Vertikal (Tegak Lurus / 90°)", value: "vertical" },
];

// Perhitungan SVG Dash untuk Live Preview
const previewStrokeDasharray = computed(() => {
  const d = localState.dash;
  if (!d || d === "solid") return undefined;
  if (d === "batas-negara") return "14 5";
  if (d === "batas-provinsi" || d === "batas-propinsi") return "14 4 2.5 4";
  if (d === "batas-kabupaten") return "14 3.5 2.5 3 2.5 3.5";
  if (d === "batas-kecamatan") return "14 3.5 2.5 3 2.5 3 2.5 3.5";
  if (d === "batas-desa") return "14 3.5 2.5 3 2.5 3 2.5 3.5";
  if (d === "dash") return "8 5";
  if (d === "dot") return "2 4";
  if (d === "dash-dot") return "14 4 2.5 4";
  if (Array.isArray(d)) return d.join(" ");
  return undefined;
});

// ─── Manajemen Atribut Kolom Layer Dinamis ─────────────────────────────────────
interface AttributeItem {
  name: string;
  type: string;
  sample_value: string | null;
  unique_values?: string[];
  min_value?: number | null;
  max_value?: number | null;
}

const layerAttributes = ref<AttributeItem[]>([]);
const isLoadingAttributes = ref(false);
const isCustomFieldMode = ref(false);

async function fetchAttributes(id?: string | null) {
  if (!id) return;
  isLoadingAttributes.value = true;
  try {
    const res = await $http<{ ok: boolean; data: AttributeItem[] }>(`admin/layers/${id}/attributes`);
    if (res?.ok && Array.isArray(res.data)) {
      layerAttributes.value = res.data;
      // Cocokkan case nama kolom dari database/WFS (mis. namobj -> NAMOBJ pada shapefile GeoServer)
      if (res.data.length > 0) {
        let mutated = false;
        if (localState.label) {
          const currentField = localState.label.field?.trim() || "";
          const exactCaseMatch = currentField
            ? res.data.find((a) => a.name.toLowerCase() === currentField.toLowerCase())
            : null;
          if (exactCaseMatch && exactCaseMatch.name !== currentField) {
            isUpdatingFromProps = true;
            localState.label.field = exactCaseMatch.name;
            mutated = true;
          } else if (!currentField) {
            const preferred =
              res.data.find((a) =>
                ["nama_desa", "nama_ruas", "namobj", "nama", "name", "desa", "kecamatan"].includes(
                  a.name.toLowerCase()
                )
              ) || res.data[0];
            if (preferred) {
              isUpdatingFromProps = true;
              localState.label.field = preferred.name;
              mutated = true;
            }
          }
        }
        if (Array.isArray(localState.rules) && localState.rules.length > 0) {
          for (const rule of localState.rules) {
            const rf = String(rule.field || "").trim();
            if (!rf) continue;
            const match = res.data.find((a) => a.name.toLowerCase() === rf.toLowerCase());
            if (match && match.name !== rf) {
              if (!mutated) isUpdatingFromProps = true;
              rule.field = match.name;
              mutated = true;
            }
          }
        }
        if (mutated) {
          nextTick(() => {
            isUpdatingFromProps = false;
          });
        }
      }
    }
  } catch (err) {
    console.warn("Gagal memuat atribut layer:", err);
  } finally {
    isLoadingAttributes.value = false;
  }
}

watch(
  () => props.layerId,
  (newId) => {
    if (newId) {
      fetchAttributes(newId);
    }
  },
  { immediate: true }
);

// Opsi atribut untuk dropdown select
const attributeOptions = computed(() => {
  if (layerAttributes.value.length === 0) {
    return [
      { label: "namobj (Nama Objek)", value: "namobj", data_type: "string", sample_value: "Bojonegoro" },
      { label: "nama_desa (Nama Desa)", value: "nama_desa", data_type: "string", sample_value: "Karangmangu" },
      { label: "nama_ruas (Nama Ruas)", value: "nama_ruas", data_type: "string", sample_value: "Ruas Poros Kanten - Mlaten" },
      { label: "wadmkc (Kecamatan)", value: "wadmkc", data_type: "string", sample_value: "Trucuk" },
      { label: "kondisi (Kondisi)", value: "kondisi", data_type: "string", sample_value: "Baik" },
    ];
  }
  return layerAttributes.value.map((attr) => ({
    label: `${attr.name} (${attr.type})`,
    value: attr.name,
    data_type: attr.type,
    sample_value: attr.sample_value,
  }));
});

// Atribut yang sedang aktif dipilih
const selectedAttribute = computed(() => {
  const currentField = localState.label?.field?.trim();
  if (!currentField) return null;
  return (
    layerAttributes.value.find((a) => a.name.toLowerCase() === currentField.toLowerCase()) ||
    null
  );
});

// Teks contoh nilai nyata untuk pratinjau label (menampilkan salah satu nilainya sebagai contoh, bukan nama field)
const previewLabelValue = computed(() => {
  const field = localState.label?.field?.trim();
  if (!field) return "Nama Objek";

  // 1. Cek nilai nyata yang diambil dari tabel database layer
  if (selectedAttribute.value?.sample_value) {
    return String(selectedAttribute.value.sample_value);
  }

  const found = layerAttributes.value.find(
    (a) => a.name.toLowerCase() === field.toLowerCase()
  );
  if (found?.sample_value) {
    return String(found.sample_value);
  }

  // 2. Nilai contoh kontekstual kartografi (bukan nama field)
  const fLower = field.toLowerCase();
  if (fLower.includes("desa")) return "Karangmangu";
  if (fLower.includes("ruas") || fLower.includes("jalan")) return "Ruas Poros Kanten - Mlaten";
  if (fLower.includes("kecamatan") || fLower === "wadmkc") return "Kecamatan Trucuk";
  if (fLower.includes("kabupaten") || fLower === "wadmkk" || fLower === "namobj") return "Bojonegoro";
  if (fLower.includes("kondisi")) return "RUSAK SEDANG";
  if (fLower.includes("perkerasan")) return "Paving; Aspal";
  if (fLower.includes("panjang")) return "2.700 m";
  if (fLower.includes("lebar")) return "3,5 m";
  if (fLower.includes("sumber")) return "APBD Kabupaten";
  if (fLower.includes("status")) return "Jalan Poros Desa";
  if (fLower === "id") return "3522032002";

  // Fallback nama contoh terformat (tanpa kurung siku)
  const cleanName = field.replace(/^id_|^kd_|_id$/i, "").replace(/_/g, " ");
  return cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
});

const sampleLabelText = previewLabelValue;

const fontFamilyOptions = [
  { label: "SansSerif (Standar Peta)", value: "SansSerif" },
  { label: "Inter (Modern UI)", value: "Inter" },
  { label: "Arial (Klasik)", value: "Arial" },
  { label: "Serif (Kartografi)", value: "Serif" },
  { label: "Monospace (Teknis/Kode)", value: "Monospace" },
];

const fontStyleOptions: Array<{ key: "regular" | "italic" | "bold" | "bold_italic"; label: string }> = [
  { key: "regular", label: "Regular" },
  { key: "italic", label: "Italic" },
  { key: "bold", label: "Bold" },
  { key: "bold_italic", label: "Bold Italic" },
];

const fontStyleSelectItems = fontStyleOptions.map((o) => ({ label: o.label, value: o.key }));

const labelModeTabs: Array<{ key: LabelRenderMode; label: string; icon: string }> = [
  { key: "single", label: "Single Labels", icon: "i-lucide-type" },
  { key: "rule_based", label: "Rule Based Labelling", icon: "i-lucide-git-branch" },
];

const activeLabelMode = computed<LabelRenderMode>({
  get() {
    if (!localState.label) return "single";
    if (localState.label.mode) return localState.label.mode;
    return localState.label.rules && localState.label.rules.length > 0 ? "rule_based" : "single";
  },
  set(mode: LabelRenderMode) {
    if (!localState.label) return;
    localState.label.mode = mode;
  },
});

const customLabelFilterFieldMap = reactive<Record<string, boolean>>({});

function addLabelRule() {
  if (!localState.label) return;
  if (!localState.label.rules) localState.label.rules = [];
  localState.label.mode = "rule_based";
  localState.label.rules.push({
    id: `lrule_${Date.now()}`,
    filter_field: resolveDefaultRuleField(),
    operator: "contains",
    value: "",
    label_field: localState.label.field || "namobj",
    color: localState.label.color || "#1e3a8a",
    halo_color: localState.label.halo_color || "#ffffff",
    halo_width: localState.label.halo_width ?? 3,
    font_size: localState.label.font_size ?? 11,
    font_family: localState.label.font_family || "SansSerif",
    font_style: localState.label.font_style || "bold",
    opacity: localState.label.opacity ?? 1,
    min_zoom: localState.label.min_zoom ?? 13,
  });
}

function removeLabelRule(idx: number) {
  if (!localState.label?.rules) return;
  localState.label.rules.splice(idx, 1);
}

const labelOpacityPct = computed<number>({
  get() {
    const raw = Number(localState.label?.opacity ?? 1);
    return Math.round(Math.max(0.1, Math.min(1, Number.isFinite(raw) ? raw : 1)) * 100);
  },
  set(pct: number) {
    if (!localState.label) return;
    const clamped = Math.max(10, Math.min(100, Number(pct) || 100));
    localState.label.opacity = Number((clamped / 100).toFixed(2));
  },
});

const previewSvgFontFamily = computed(() => {
  const f = localState.label?.font_family || "SansSerif";
  if (f === "Serif") return "Georgia, serif";
  if (f === "Monospace") return "ui-monospace, monospace";
  if (f === "Arial") return "Arial, sans-serif";
  return "Inter, sans-serif";
});

const previewSvgFontWeight = computed(() => {
  const s = localState.label?.font_style || "bold";
  return s === "bold" || s === "bold_italic" ? "700" : "400";
});

const previewSvgFontStyle = computed(() => {
  const s = localState.label?.font_style || "bold";
  return s === "italic" || s === "bold_italic" ? "italic" : "normal";
});

const previewSymbolModeLabel = computed(() => {
  return symbolModeTabs.find((m) => m.key === activeSymbolMode.value)?.label || "Single Symbol";
});

const previewSymbolItems = computed(() => {
  if (activeSymbolMode.value === "single" || !localState.rules || localState.rules.length === 0) {
    return [];
  }
  return localState.rules.slice(0, 5).map((r, idx) => {
    const labelText =
      r.label?.trim() ||
      (r.operator === "between" ? `${r.min_value ?? 0}-${r.max_value ?? 0}` : String(r.value || `Kelas ${idx + 1}`));
    return {
      key: r.id || `prev_${idx}`,
      color: r.color || localState.color || "#2563eb",
      fillColor: r.fill_color || r.color || localState.fill_color || "rgba(59, 130, 246, 0.25)",
      label: labelText,
      dash: r.dash ? resolveDashPreview(r.dash) : previewStrokeDasharray.value,
      width: Number(r.width ?? localState.width ?? 2.5),
    };
  });
});

function resolveDashPreview(d?: string | number[]): string | undefined {
  if (!d || d === "solid") return undefined;
  if (Array.isArray(d)) return d.join(" ");
  if (d === "dash") return "8 5";
  if (d === "dot") return "2 4";
  if (d === "dash-dot" || d === "batas-provinsi") return "14 4 2.5 4";
  return previewStrokeDasharray.value;
}

function previewSlotCenterX(idx: number, total: number): number {
  if (total <= 1) return 250;
  const span = Math.min(360, total * 82);
  const start = 250 - span / 2;
  const step = span / (total - 1);
  return Math.round(start + idx * step);
}

const previewRuleLabelSamples = computed(() => {
  if (!localState.label?.enabled || activeLabelMode.value !== "rule_based") return [];
  const rules = localState.label.rules || [];
  return rules.slice(0, 4).map((lr, idx) => {
    const f = lr.font_family || localState.label?.font_family || "SansSerif";
    const s = lr.font_style || localState.label?.font_style || "bold";
    const colField = lr.label_field || localState.label?.field || "namobj";
    const attrSample = getRuleAttributeSample(colField);
    const text = attrSample || String(lr.value || `Label ${idx + 1}`);
    return {
      key: lr.id || `plbl_${idx}`,
      text,
      color: lr.color || localState.label?.color || "#1e3a8a",
      haloColor: lr.halo_color || localState.label?.halo_color || "#ffffff",
      haloWidth: Number(lr.halo_width ?? localState.label?.halo_width ?? 3),
      fontSize: Number(lr.font_size ?? localState.label?.font_size ?? 11),
      fontFamily: f === "Serif" ? "Georgia, serif" : f === "Monospace" ? "ui-monospace, monospace" : f === "Arial" ? "Arial, sans-serif" : "Inter, sans-serif",
      fontWeight: s === "bold" || s === "bold_italic" ? "700" : "400",
      fontStyle: s === "italic" || s === "bold_italic" ? "italic" : "normal",
      opacity: Number(lr.opacity ?? localState.label?.opacity ?? 1),
    };
  });
});

const previewSvgTextAnchor = computed(() => {
  if (localState.label?.placement === "line" && localState.geom_type !== "point") {
    return "middle";
  }
  const align = localState.label?.align || "center";
  if (align === "left") return "start";
  if (align === "right") return "end";
  return "middle";
});

const previewLabelLines = computed<string[]>(() => {
  const text = String(sampleLabelText.value || "").trim();
  if (!text) return [];
  const lbl = localState.label;
  if (!lbl || lbl.wrap === false) return [text];

  const wrapPx = Math.max(40, Math.min(300, Number(lbl.wrap_width ?? 90)));
  const fontSize = Math.max(8, Number(lbl.font_size ?? 11));
  const maxChars = Math.max(6, Math.floor(wrapPx / (fontSize * 0.58)));

  const words = text.split(/\s+/);
  const lines: string[] = [];
  let current = "";
  for (const w of words) {
    if (!current) {
      current = w;
    } else if ((current.length + 1 + w.length) <= maxChars) {
      current += ` ${w}`;
    } else {
      lines.push(current);
      current = w;
    }
  }
  if (current) lines.push(current);
  return lines.length > 0 ? lines : [text];
});

function getItemSampleValue(item: unknown): string | null {
  if (item && typeof item === "object" && "sample_value" in item) {
    const val = (item as { sample_value?: unknown }).sample_value;
    return val !== undefined && val !== null && val !== "" ? String(val) : null;
  }
  return null;
}

const dashModelValue = computed<string>({
  get() {
    return typeof localState.dash === "string" ? localState.dash : "solid";
  },
  set(val: string) {
    localState.dash = val;
  },
});

type StyleGroupKey = "dasar" | "simbol" | "label" | "kode";

const activeStyleGroup = ref<StyleGroupKey>(
  props.modelValue?.sld_mode === "xml" ? "kode" : "simbol"
);

const activeSymbolMode = computed<SymbolRenderMode>({
  get() {
    if (localState.symbol_mode) return localState.symbol_mode;
    return localState.rules && localState.rules.length > 0 ? "rule_based" : "single";
  },
  set(mode: SymbolRenderMode) {
    localState.symbol_mode = mode;
    if ((mode === "categorized" || mode === "graduated") && !localState.classify_field) {
      localState.classify_field = resolveDefaultRuleField();
    }
  },
});

const graduatedMinInput = ref<number>(0);
const graduatedMaxInput = ref<number>(100);

function interpolateHexColor(hex1: string, hex2: string, t: number): string {
  const parse = (h: string) => {
    const clean = h.replace("#", "");
    return [
      parseInt(clean.slice(0, 2), 16),
      parseInt(clean.slice(2, 4), 16),
      parseInt(clean.slice(4, 6), 16),
    ];
  };
  const [r1, g1, b1] = parse(hex1);
  const [r2, g2, b2] = parse(hex2);
  const r = Math.round(r1 + (r2 - r1) * t);
  const g = Math.round(g1 + (g2 - g1) * t);
  const b = Math.round(b1 + (b2 - b1) * t);
  return `#${[r, g, b].map((v) => Math.max(0, Math.min(255, v)).toString(16).padStart(2, "0")).join("")}`;
}

function sampleRampColor(stops: string[], ratio: number): string {
  if (stops.length === 0) return "#2563eb";
  if (stops.length === 1) return stops[0]!;
  const clamped = Math.max(0, Math.min(1, ratio));
  const scaled = clamped * (stops.length - 1);
  const idx = Math.min(stops.length - 2, Math.floor(scaled));
  const localT = scaled - idx;
  return interpolateHexColor(stops[idx]!, stops[idx + 1]!, localT);
}

watch(
  () => localState.classify_field,
  (field) => {
    if (!field) return;
    const attr = layerAttributes.value.find((a) => a.name.toLowerCase() === field.toLowerCase());
    if (attr && attr.min_value !== null && attr.min_value !== undefined && attr.max_value !== null && attr.max_value !== undefined) {
      graduatedMinInput.value = Number(attr.min_value);
      graduatedMaxInput.value =
        Number(attr.max_value) > Number(attr.min_value)
          ? Number(attr.max_value)
          : Number(attr.min_value) + 100;
    }
  },
  { immediate: true }
);

function classifyCategorized() {
  const field = localState.classify_field || resolveDefaultRuleField();
  localState.classify_field = field;
  localState.symbol_mode = "categorized";

  const attr = layerAttributes.value.find((a) => a.name.toLowerCase() === field.toLowerCase());
  const paletteKey = localState.color_ramp && CATEGORIZED_PALETTES[localState.color_ramp] ? localState.color_ramp : "spectral";
  const palette = CATEGORIZED_PALETTES[paletteKey]!.colors;

  const uniqueVals =
    attr?.unique_values && attr.unique_values.length > 0
      ? attr.unique_values
      : attr?.sample_value
        ? [String(attr.sample_value)]
        : ["Kategori 1", "Kategori 2", "Kategori 3"];

  localState.rules = uniqueVals.map((val, idx) => {
    const color = palette[idx % palette.length]!;
    return {
      id: `cat_${Date.now()}_${idx}`,
      field,
      operator: "equals" as const,
      value: val,
      label: val,
      color,
      width: localState.width,
    };
  });
}

function addCategorizedRow() {
  const field = localState.classify_field || resolveDefaultRuleField();
  localState.classify_field = field;
  localState.symbol_mode = "categorized";
  if (!localState.rules) localState.rules = [];
  const paletteKey = localState.color_ramp && CATEGORIZED_PALETTES[localState.color_ramp] ? localState.color_ramp : "spectral";
  const palette = CATEGORIZED_PALETTES[paletteKey]!.colors;
  const color = palette[localState.rules.length % palette.length]!;
  localState.rules.push({
    id: `cat_${Date.now()}`,
    field,
    operator: "equals",
    value: "",
    label: "",
    color,
    width: localState.width,
  });
}

function classifyGraduated() {
  const field = localState.classify_field || resolveDefaultRuleField();
  localState.classify_field = field;
  localState.symbol_mode = "graduated";

  const count = Math.max(2, Math.min(10, Number(localState.graduated_classes ?? 5)));
  const minVal = Number.isFinite(Number(graduatedMinInput.value)) ? Number(graduatedMinInput.value) : 0;
  let maxVal = Number.isFinite(Number(graduatedMaxInput.value)) ? Number(graduatedMaxInput.value) : 100;
  if (maxVal <= minVal) maxVal = minVal + count * 10;

  const rampKey = localState.color_ramp && GRADUATED_RAMPS[localState.color_ramp] ? localState.color_ramp : "blue_red";
  const stops = GRADUATED_RAMPS[rampKey]!.stops;
  const step = (maxVal - minVal) / count;

  const formatNum = (n: number) => (Number.isInteger(n) ? String(n) : String(Number(n.toFixed(2))));
  const rows: StyleRule[] = [];
  for (let i = 0; i < count; i++) {
    const lo = Number((minVal + step * i).toFixed(2));
    const hi = i === count - 1 ? maxVal : Number((minVal + step * (i + 1)).toFixed(2));
    const color = sampleRampColor(stops, count === 1 ? 0.5 : i / (count - 1));
    rows.push({
      id: `grad_${Date.now()}_${i}`,
      field,
      operator: "between",
      value: `${lo}-${hi}`,
      min_value: lo,
      max_value: hi,
      label: `${formatNum(lo)} - ${formatNum(hi)}`,
      color,
      width: localState.width,
    });
  }
  localState.rules = rows;
}

function addGraduatedRow() {
  const field = localState.classify_field || resolveDefaultRuleField();
  localState.classify_field = field;
  localState.symbol_mode = "graduated";
  if (!localState.rules) localState.rules = [];
  const lastMax = Number(localState.rules[localState.rules.length - 1]?.max_value ?? 0);
  const nextLo = Number.isFinite(lastMax) ? lastMax : 0;
  const nextHi = nextLo + 50;
  localState.rules.push({
    id: `grad_${Date.now()}`,
    field,
    operator: "between",
    value: `${nextLo}-${nextHi}`,
    min_value: nextLo,
    max_value: nextHi,
    label: `${nextLo} - ${nextHi}`,
    color: "#2563eb",
    width: localState.width,
  });
}

const symbolGroupBadge = computed(() => {
  if (activeSymbolMode.value === "single") return null;
  const cnt = localState.rules?.length || 0;
  return cnt > 0 ? String(cnt) : null;
});

const styleGroupItems = computed(() => [
  {
    key: "dasar" as StyleGroupKey,
    label: "Geometri & Preset",
    desc: "Tipe, zoom & template",
    icon: "i-lucide-layers",
    badge: localState.min_zoom && localState.min_zoom > 0 ? `Z≥${localState.min_zoom}` : null,
  },
  {
    key: "simbol" as StyleGroupKey,
    label: "Simbol & Warna",
    desc: "Single, kategori & rule",
    icon: "i-lucide-paintbrush",
    badge: symbolGroupBadge.value,
  },
  {
    key: "label" as StyleGroupKey,
    label: "Label Teks",
    desc: "Teks, font & posisi",
    icon: "i-lucide-type",
    badge: localState.label?.enabled ? "Aktif" : null,
  },
  {
    key: "kode" as StyleGroupKey,
    label: isWmsProtocol.value ? "XML SLD & JSON" : "Kode JSON",
    desc: isWmsProtocol.value ? "XML GeoServer & legenda" : "Editor style_config",
    icon: "i-lucide-code-2",
    badge: isWmsProtocol.value && localState.sld_mode === "xml" ? "XML" : null,
  },
]);

function selectStyleGroup(group: StyleGroupKey) {
  activeStyleGroup.value = group;
  if (group !== "kode" && isRawJsonMode.value) {
    // Tetap sinkron saat kembali dari editor JSON
    handleToggleMode();
  }
  if (group === "kode" && isRawJsonMode.value) {
    syncRawJson();
  }
}

function handleStyleGroupKeydown(event: KeyboardEvent, index: number) {
  if (!["ArrowDown", "ArrowUp", "ArrowRight", "ArrowLeft"].includes(event.key)) return;
  event.preventDefault();
  const list = styleGroupItems.value;
  const delta = event.key === "ArrowDown" || event.key === "ArrowRight" ? 1 : -1;
  const next = list[(index + delta + list.length) % list.length]!;
  selectStyleGroup(next.key);
  nextTick(() => {
    document.getElementById(`style-group-tab-${next.key}`)?.focus();
  });
}

// Sinkronisasi Color Picker + Opacity Slider untuk Fill Poligon (RGBA)
const parsedFillColor = computed(() => parseCssColorToSld(localState.fill_color, "#2563eb", 0.25));

function updateFillFromHexAndOpacity(hex: string, opacity: number) {
  const cleanHex = hex.startsWith("#") && hex.length === 7 ? hex : "#2563eb";
  const r = parseInt(cleanHex.slice(1, 3), 16);
  const g = parseInt(cleanHex.slice(3, 5), 16);
  const b = parseInt(cleanHex.slice(5, 7), 16);
  const a = Math.max(0, Math.min(1, Number(opacity.toFixed(2))));
  localState.fill_color = `rgba(${r}, ${g}, ${b}, ${a})`;
}

const fillHexModel = computed<string>({
  get: () => parsedFillColor.value.hex,
  set: (hex: string) => updateFillFromHexAndOpacity(hex, parsedFillColor.value.opacity),
});

const fillOpacityPctModel = computed<number>({
  get: () => Math.round(parsedFillColor.value.opacity * 100),
  set: (pct: number) => updateFillFromHexAndOpacity(parsedFillColor.value.hex, Number(pct) / 100),
});
</script>

<template>
  <div class="space-y-4">
    <!-- Header Controls -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 pb-3 border-b border-gray-200 dark:border-white/[0.08]">
      <div class="flex items-center gap-2">
        <UIcon name="i-lucide-palette" class="size-4.5 text-blue-600 dark:text-blue-400" aria-hidden="true" />
        <div>
          <h4 class="text-xs font-semibold text-gray-900 dark:text-white">
            {{ isWmsProtocol ? 'Konfigurasi Simbologi & Custom SLD (WMS)' : 'Konfigurasi Simbologi & Style MVT' }}
          </h4>
          <p class="text-[11px] text-gray-600 dark:text-gray-300">
            {{
              isWmsProtocol
                ? 'Pilih grup konfigurasi di sebelah kiri untuk mengatur geometri, simbol, aturan atribut, label, atau XML SLD.'
                : 'Pilih grup konfigurasi di sebelah kiri untuk mengatur geometri, simbol, aturan atribut, dan label vektor.'
            }}
          </p>
        </div>
      </div>

      <div v-if="isWmsProtocol && isWmsCustomActive" class="flex items-center gap-2">
        <UButton
          size="xs"
          color="neutral"
          variant="outline"
          icon="i-lucide-rotate-ccw"
          label="Reset ke SLD Bawaan"
          class="cursor-pointer min-h-[36px] sm:min-h-0"
          @click="handleResetWmsToDefaultSld"
        />
      </div>
    </div>

    <!-- Status Bar Khusus WMS Custom SLD -->
    <div
      v-if="isWmsProtocol"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-xl border text-xs"
      :class="
        isWmsCustomActive
          ? 'border-blue-200 dark:border-blue-800/50 bg-blue-50/70 dark:bg-blue-950/25 text-blue-900 dark:text-blue-100'
          : 'border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-white/[0.02] text-gray-700 dark:text-gray-300'
      "
    >
      <div class="flex items-start gap-2.5 min-w-0">
        <UIcon
          :name="isWmsCustomActive ? 'i-lucide-sparkles' : 'i-lucide-server'"
          class="size-4 mt-0.5 shrink-0"
          :class="isWmsCustomActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300'"
          aria-hidden="true"
        />
        <div class="space-y-0.5 min-w-0">
          <div class="font-semibold">
            {{ isWmsCustomActive ? 'Custom SLD Aktif (Menggantikan SLD Internal GeoServer)' : 'Menggunakan SLD Internal Bawaan GeoServer' }}
          </div>
          <p class="text-[11px] opacity-90 tabular-nums">
            {{
              isWmsCustomActive
                ? `Ukuran XML SLD: ${sldByteLength.toLocaleString('id-ID')} karakter (${sldUsesPostTransport ? 'mode HTTP POST GetMap' : 'mode HTTP GET SLD_BODY'}).`
                : 'Ubah warna, aturan, label, atau XML SLD pada grup di bawah untuk menerapkan custom SLD pada peta dan legenda.'
            }}
          </p>
        </div>
      </div>
    </div>

    <!-- 2-Pane Grouped Sub-Menu Layout -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
      <!-- Kiri: Navigasi Sub-Menu Per Group -->
      <aside class="md:col-span-4 lg:col-span-3">
        <div
          role="tablist"
          aria-label="Grup konfigurasi style layer"
          class="flex md:flex-col gap-1.5 overflow-x-auto md:overflow-visible pb-1 md:pb-0 md:p-1.5 md:rounded-xl md:border md:border-gray-200 md:dark:border-white/[0.08] md:bg-gray-50/70 md:dark:bg-[#070b14]"
        >
          <button
            v-for="(grp, idx) in styleGroupItems"
            :id="`style-group-tab-${grp.key}`"
            :key="grp.key"
            type="button"
            role="tab"
            :aria-selected="activeStyleGroup === grp.key"
            :aria-controls="`style-group-panel-${grp.key}`"
            :tabindex="activeStyleGroup === grp.key ? 0 : -1"
            class="shrink-0 md:w-full text-left flex items-center justify-between gap-2 px-3 py-2.5 min-h-[44px] rounded-lg border transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            :class="
              activeStyleGroup === grp.key
                ? 'bg-white dark:bg-[#0c1424] border-blue-500/60 dark:border-blue-500/50 text-blue-700 dark:text-blue-300 shadow-xs'
                : 'bg-white/60 md:bg-transparent dark:bg-white/[0.02] md:dark:bg-transparent border-gray-200 md:border-transparent dark:border-white/[0.08] md:dark:border-transparent text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-white/[0.05]'
            "
            @click="selectStyleGroup(grp.key)"
            @keydown="handleStyleGroupKeydown($event, idx)"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <UIcon
                :name="grp.icon"
                class="size-4 shrink-0"
                :class="activeStyleGroup === grp.key ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'"
                aria-hidden="true"
              />
              <div class="min-w-0">
                <div class="text-xs font-semibold truncate">
                  {{ grp.label }}
                </div>
                <div class="hidden md:block text-[10px] text-gray-600 dark:text-gray-400 truncate">
                  {{ grp.desc }}
                </div>
              </div>
            </div>

            <span
              v-if="grp.badge"
              class="px-1.5 py-0.5 rounded text-[10px] font-semibold tabular-nums shrink-0"
              :class="
                activeStyleGroup === grp.key
                  ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-200'
                  : 'bg-gray-200/80 dark:bg-white/10 text-gray-700 dark:text-gray-300'
              "
            >
              {{ grp.badge }}
            </span>
          </button>
        </div>
      </aside>

      <!-- Kanan: Interaksi Styling Sesuai Group Terpilih -->
      <div class="md:col-span-8 lg:col-span-9 space-y-4 min-w-0">
        <!-- LIVE PREVIEW SVG BOX (Tampil pada grup visual) -->
        <div
          v-if="activeStyleGroup !== 'kode'"
          class="p-3 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-slate-50/70 dark:bg-[#070b14] space-y-2.5"
        >
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold text-gray-800 dark:text-gray-200">
            <span class="flex items-center gap-1.5">
              <UIcon name="i-lucide-eye" class="size-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
              <span>Pratinjau Simbol Kartografi</span>
            </span>
            <span class="text-[11px] font-normal text-gray-600 dark:text-gray-300 tabular-nums">
              {{ localState.geom_type?.toUpperCase() }} · {{ previewSymbolModeLabel }}
              <template v-if="activeSymbolMode !== 'single'">
                ({{ localState.rules?.length || 0 }} kelas)
              </template>
              <template v-if="localState.label?.enabled">
                · {{ activeLabelMode === 'rule_based' ? `${localState.label?.rules?.length || 0} Aturan Label` : `Label: "${previewLabelValue}"` }}
              </template>
            </span>
          </div>

          <div class="relative h-28 w-full rounded-lg border border-dashed border-gray-300 dark:border-white/10 bg-white dark:bg-[#0b0f19] flex items-center justify-center overflow-hidden">
            <svg class="w-full h-full" viewBox="0 0 500 116" preserveAspectRatio="xMidYMid meet">
              <defs>
                <pattern id="gridPattern" width="20" height="20" patternUnits="userSpaceOnUse">
                  <path d="M 20 0 L 0 0 0 20" fill="none" stroke="currentColor" class="text-gray-200/50 dark:text-white/[0.03]" stroke-width="1" />
                </pattern>
              </defs>
              <rect width="500" height="116" fill="url(#gridPattern)" />

              <!-- MULTI-CLASS PREVIEW (Categorized / Graduated / Rule Based) -->
              <template v-if="previewSymbolItems.length > 0">
                <g v-if="localState.geom_type === 'polygon' || localState.geom_type === 'auto'">
                  <polygon
                    v-for="(item, idx) in previewSymbolItems"
                    :key="`poly-${item.key}`"
                    :points="`${previewSlotCenterX(idx, previewSymbolItems.length) - 34},22 ${previewSlotCenterX(idx, previewSymbolItems.length) + 28},16 ${previewSlotCenterX(idx, previewSymbolItems.length) + 36},92 ${previewSlotCenterX(idx, previewSymbolItems.length) - 24},96`"
                    :fill="item.fillColor"
                    :fill-opacity="0.65"
                    :stroke="item.color"
                    :stroke-width="item.width"
                    :stroke-dasharray="item.dash"
                    stroke-linejoin="round"
                  />
                </g>

                <g v-if="localState.geom_type === 'line'">
                  <line
                    v-for="(item, idx) in previewSymbolItems"
                    :key="`line-${item.key}`"
                    :x1="previewSlotCenterX(idx, previewSymbolItems.length) - 34"
                    y1="72"
                    :x2="previewSlotCenterX(idx, previewSymbolItems.length) + 34"
                    y2="40"
                    :stroke="item.color"
                    :stroke-width="item.width"
                    :stroke-dasharray="item.dash"
                    stroke-linecap="round"
                  />
                </g>

                <g v-if="localState.geom_type === 'point' || localState.geom_type === 'auto'">
                  <circle
                    v-for="(item, idx) in previewSymbolItems"
                    :key="`pt-${item.key}`"
                    :cx="previewSlotCenterX(idx, previewSymbolItems.length)"
                    :cy="localState.label?.enabled ? 46 : 58"
                    :r="Math.min(16, Number(localState.point_radius ?? 7))"
                    :fill="item.color"
                    :stroke="localState.point_border_color || '#ffffff'"
                    :stroke-width="Number(localState.point_border_width ?? 2)"
                  />
                </g>
              </template>

              <!-- SINGLE SYMBOL PREVIEW -->
              <template v-else>
                <g v-if="localState.geom_type === 'polygon' || localState.geom_type === 'auto'">
                  <polygon
                    v-if="localState.casing"
                    points="150,20 345,14 365,96 172,98"
                    :fill="localState.fill_color || 'transparent'"
                    :stroke="localState.casing_color || 'rgba(148, 163, 184, 0.3)'"
                    :stroke-width="Number(localState.casing_width ?? 6)"
                    stroke-linejoin="round"
                  />
                  <polygon
                    points="150,20 345,14 365,96 172,98"
                    :fill="localState.fill_color || 'rgba(59, 130, 246, 0.15)'"
                    :stroke="localState.color || '#475569'"
                    :stroke-width="Number(localState.width ?? 2)"
                    :stroke-dasharray="previewStrokeDasharray"
                    stroke-linejoin="round"
                  />
                </g>

                <g v-if="localState.geom_type === 'line' || localState.geom_type === 'auto'">
                  <path
                    v-if="localState.casing"
                    d="M 40,58 Q 150,20 250,58 T 460,58"
                    fill="none"
                    :stroke="localState.casing_color || 'rgba(148, 163, 184, 0.3)'"
                    :stroke-width="Number(localState.casing_width ?? 6)"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M 40,58 Q 150,20 250,58 T 460,58"
                    fill="none"
                    :stroke="localState.color || '#2563eb'"
                    :stroke-width="Number(localState.width ?? 3)"
                    :stroke-dasharray="previewStrokeDasharray"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </g>

                <g v-if="localState.geom_type === 'point' || localState.geom_type === 'auto'">
                  <circle
                    cx="250"
                    :cy="localState.label?.enabled ? 44 : 58"
                    :r="Math.min(18, Number(localState.point_radius ?? 7))"
                    :fill="localState.point_color || localState.color || '#0284c7'"
                    :stroke="localState.point_border_color || '#ffffff'"
                    :stroke-width="Number(localState.point_border_width ?? 2)"
                  />
                </g>
              </template>

              <!-- LABEL PREVIEW: RULE BASED LABELLING -->
              <g v-if="localState.label?.enabled && activeLabelMode === 'rule_based' && previewRuleLabelSamples.length > 0">
                <text
                  v-for="(rl, idx) in previewRuleLabelSamples"
                  :key="rl.key"
                  :x="previewSlotCenterX(idx, previewRuleLabelSamples.length) + Number(localState.label.offset_x ?? 0)"
                  :y="(localState.geom_type === 'point' ? 76 : 58) + Number(localState.label.offset_y ?? 0)"
                  :text-anchor="previewSvgTextAnchor"
                  :fill="rl.color"
                  :stroke="rl.haloColor"
                  :stroke-width="rl.haloWidth"
                  paint-order="stroke fill"
                  :font-size="rl.fontSize"
                  :font-family="rl.fontFamily"
                  :font-weight="rl.fontWeight"
                  :font-style="rl.fontStyle"
                  :opacity="rl.opacity"
                >
                  {{ rl.text }}
                </text>
              </g>

              <!-- LABEL PREVIEW: SINGLE LABELS -->
              <g v-else-if="localState.label?.enabled">
                <text
                  :x="250 + Number(localState.label.offset_x ?? 0)"
                  :y="(localState.geom_type === 'point' ? 74 : 58) + Number(localState.label.offset_y ?? 0)"
                  :transform="localState.label.orientation === 'vertical' && localState.label.placement !== 'line'
                    ? `rotate(90, ${250 + Number(localState.label.offset_x ?? 0)}, ${(localState.geom_type === 'point' ? 74 : 58) + Number(localState.label.offset_y ?? 0)})`
                    : undefined"
                  :text-anchor="previewSvgTextAnchor"
                  :fill="localState.label.color || '#1e3a8a'"
                  :stroke="localState.label.halo_color || '#ffffff'"
                  :stroke-width="Number(localState.label.halo_width ?? 3)"
                  paint-order="stroke fill"
                  :font-size="Number(localState.label.font_size ?? 11)"
                  :font-family="previewSvgFontFamily"
                  :font-weight="previewSvgFontWeight"
                  :font-style="previewSvgFontStyle"
                  :opacity="Number(localState.label.opacity ?? 1)"
                >
                  <tspan
                    v-for="(lblLine, idx) in previewLabelLines"
                    :key="idx"
                    :x="250 + Number(localState.label.offset_x ?? 0)"
                    :dy="idx === 0 ? 0 : '1.2em'"
                  >{{ lblLine }}</tspan>
                </text>
              </g>
            </svg>
          </div>

          <!-- Ringkasan Swatch Kelas Aktif pada Pratinjau -->
          <div
            v-if="previewSymbolItems.length > 0"
            class="flex flex-wrap items-center gap-2 pt-0.5 text-[11px] text-gray-700 dark:text-gray-300"
          >
            <span
              v-for="item in previewSymbolItems"
              :key="`chip-${item.key}`"
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08]"
            >
              <span class="size-2.5 rounded-xs shrink-0 border border-black/15" :style="{ backgroundColor: item.color }" />
              <span class="truncate max-w-[110px]">{{ item.label }}</span>
            </span>
            <span
              v-if="(localState.rules?.length || 0) > previewSymbolItems.length"
              class="text-[10px] text-gray-500 dark:text-gray-400 tabular-nums"
            >
              +{{ (localState.rules?.length || 0) - previewSymbolItems.length }} lainnya
            </span>
          </div>
        </div>

        <!-- GROUP 1: GEOMETRI & PRESET -->
        <div
          v-show="activeStyleGroup === 'dasar'"
          id="style-group-panel-dasar"
          role="tabpanel"
          aria-labelledby="style-group-tab-dasar"
          class="space-y-4"
        >
          <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-4">
            <UFormField label="Tipe Geometri Peta">
              <USelect
                v-model="localState.geom_type"
                :items="geomOptions"
                :portal="false"
                class="w-full"
              />
            </UFormField>

            <div class="pt-3 border-t border-gray-100 dark:border-white/[0.06] space-y-2">
              <div class="flex items-center justify-between gap-2">
                <div>
                  <label for="style-min-zoom-range" class="text-xs font-semibold text-gray-900 dark:text-white block">
                    {{ isWmsProtocol ? 'Minimal Zoom Tampil Layer (WMS)' : 'Minimal Zoom Tampil Layer (MVT)' }}
                  </label>
                  <span class="text-[11px] text-gray-600 dark:text-gray-300">
                    Batas zoom minimal agar geometri layer tampil di peta (terpisah dari minimal zoom label).
                  </span>
                </div>
                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-white/10 text-xs font-mono font-semibold text-gray-900 dark:text-white shrink-0 tabular-nums">
                  {{ localState.min_zoom && localState.min_zoom > 0 ? `Zoom >= ${localState.min_zoom}` : 'Semua Zoom' }}
                </span>
              </div>
              <div class="flex items-center gap-3">
                <input
                  id="style-min-zoom-range"
                  :value="localState.min_zoom ?? 0"
                  type="range"
                  min="0"
                  max="18"
                  step="0.5"
                  class="flex-1 accent-blue-600 cursor-pointer"
                  @input="localState.min_zoom = Number(($event.target as HTMLInputElement).value) > 0 ? Number(($event.target as HTMLInputElement).value) : null"
                />
                <button
                  v-if="localState.min_zoom && localState.min_zoom > 0"
                  type="button"
                  class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                  @click="localState.min_zoom = null"
                >
                  Reset
                </button>
              </div>
            </div>
          </div>

          <!-- Template / Preset Cepat -->
          <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3">
            <div>
              <h5 class="text-xs font-semibold text-gray-900 dark:text-white">
                Template / Preset Cepat
              </h5>
              <p class="text-[11px] text-gray-600 dark:text-gray-300">
                Terapkan kombinasi simbol dan aturan siap pakai lalu sesuaikan seperlunya.
              </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <button
                v-for="p in presets"
                :key="p.id"
                type="button"
                class="flex flex-col text-left p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] hover:border-blue-500/60 hover:bg-blue-50/40 dark:hover:bg-blue-950/20 transition-colors cursor-pointer group min-h-[44px]"
                @click="applyPreset(p)"
              >
                <span class="text-xs font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                  {{ p.name }}
                </span>
                <span class="text-[11px] text-gray-600 dark:text-gray-300 line-clamp-2 mt-0.5">
                  {{ p.desc }}
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- GROUP 2: SIMBOL & WARNA -->
        <div
          v-show="activeStyleGroup === 'simbol'"
          id="style-group-panel-simbol"
          role="tabpanel"
          aria-labelledby="style-group-tab-simbol"
          class="space-y-4"
        >
          <!-- Sub-Menu Metode Simbologi: Single Symbol, Categorized, Graduated, Rule Based -->
          <div class="p-2 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-2">
            <div
              role="tablist"
              aria-label="Mode simbologi dan pewarnaan"
              class="grid grid-cols-2 sm:grid-cols-4 gap-1.5"
            >
              <button
                v-for="modeTab in symbolModeTabs"
                :key="modeTab.key"
                type="button"
                role="tab"
                :aria-selected="activeSymbolMode === modeTab.key"
                class="flex items-center justify-center sm:justify-start gap-2 px-2.5 py-2 min-h-[42px] rounded-lg text-left transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                :class="activeSymbolMode === modeTab.key
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'bg-gray-50 dark:bg-white/[0.03] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.07]'"
                @click="activeSymbolMode = modeTab.key"
              >
                <UIcon :name="modeTab.icon" class="size-4 shrink-0" aria-hidden="true" />
                <div class="min-w-0">
                  <span class="block text-xs font-semibold leading-tight truncate">
                    {{ modeTab.label }}
                  </span>
                </div>
              </button>
            </div>
          </div>

          <template v-if="activeSymbolMode === 'single'">
          <div
            v-if="localState.geom_type !== 'point'"
            class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19]"
          >
            <UFormField label="Bentuk Garis (Line Dash)">
              <USelect
                v-model="dashModelValue"
                :items="dashOptions"
                :portal="false"
                class="w-full"
              >
                <template #leading>
                  <div v-if="currentDashItem" class="w-10 h-3 flex items-center shrink-0 rounded px-1 bg-gray-100 dark:bg-white/[0.04]">
                    <svg class="w-full h-2 overflow-visible" viewBox="0 0 50 10">
                      <line
                        x1="1"
                        y1="5"
                        x2="49"
                        y2="5"
                        class="stroke-gray-800 dark:stroke-gray-200"
                        :stroke-width="(currentDashItem as any).strokeWidth || 2"
                        :stroke-dasharray="(currentDashItem as any).dasharray"
                        stroke-linecap="butt"
                      />
                    </svg>
                  </div>
                </template>

                <template #item-leading="{ item }">
                  <div v-if="item.dasharray !== undefined || item.value === 'solid'" class="w-12 h-3.5 flex items-center shrink-0 rounded px-1 bg-gray-100 dark:bg-white/[0.04]">
                    <svg class="w-full h-2.5 overflow-visible" viewBox="0 0 50 12">
                      <line
                        x1="1"
                        y1="6"
                        x2="49"
                        y2="6"
                        class="stroke-gray-800 dark:stroke-gray-200"
                        :stroke-width="item.strokeWidth || 2"
                        :stroke-dasharray="item.dasharray"
                        stroke-linecap="butt"
                      />
                    </svg>
                  </div>
                </template>
              </USelect>
            </UFormField>
          </div>

          <!-- Section 2: Warna & Ketebalan Garis (Hanya Garis/Poligon/Auto) -->
          <div
            v-if="localState.geom_type !== 'point'"
            class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
          >
            <div class="text-xs font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
              <UIcon name="i-lucide-paintbrush" class="size-4 text-blue-500" aria-hidden="true" />
              <span>Warna &amp; Garis Tepi (Stroke &amp; Border)</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Color Picker with Swatches -->
          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1.5">Warna Utama Garis / Stroke</label>
            <div class="flex items-center gap-2">
              <input
                v-model="localState.color"
                type="color"
                class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent"
              />
              <UInput
                v-model="localState.color"
                placeholder="#2563eb"
                class="w-32 font-mono text-xs"
              />
            </div>
            <!-- Quick Swatches -->
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
              <button
                v-for="c in quickColors"
                :key="c.hex"
                type="button"
                :style="{ backgroundColor: c.hex }"
                class="size-5 rounded-full border border-black/10 dark:border-white/20 cursor-pointer transition-transform hover:scale-115 active:scale-95"
                :title="`${c.name} (${c.hex})`"
                @click="applyPrimaryColor(c.hex)"
              />
            </div>
          </div>

          <!-- Stroke Width Slider -->
          <div>
            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
              <span>Ketebalan Garis (Stroke Width)</span>
              <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ localState.width }} px</span>
            </div>
            <input
              v-model.number="localState.width"
              type="range"
              min="0.5"
              max="10"
              step="0.5"
              class="w-full accent-blue-600 cursor-pointer mt-2"
            />
            <div class="flex items-center justify-between text-[10px] text-gray-400 mt-1">
              <span>0.5 px (Tipis)</span>
              <span>5 px</span>
              <span>10 px (Tebal)</span>
            </div>
          </div>
        </div>

        <!-- Casing / Halo Underlay Toggle -->
        <div class="pt-3 border-t border-gray-100 dark:border-white/[0.05] space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                Efek Casing / Underlay (Halo Tepi)
              </span>
              <span class="text-[11px] text-gray-500 dark:text-gray-400">
                Memberikan bayangan garis luar kartografi agar kontras di atas basemap satelit/terang.
              </span>
            </div>
            <USwitch v-model="localState.casing" />
          </div>

          <div v-if="localState.casing" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pl-2 border-l-2 border-blue-500/40">
            <div>
              <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Warna Casing</label>
              <div class="flex items-center gap-2">
                <input
                  v-model="localState.casing_color"
                  type="text"
                  placeholder="rgba(148, 163, 184, 0.25)"
                  class="w-full rounded-md border border-gray-300 dark:border-white/10 bg-transparent px-2.5 py-1 text-xs font-mono text-gray-900 dark:text-white"
                />
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                <span>Lebar Casing</span>
                <span class="font-mono text-gray-900 dark:text-white">{{ localState.casing_width }} px</span>
              </div>
              <input
                v-model.number="localState.casing_width"
                type="range"
                min="2"
                max="16"
                step="0.5"
                class="w-full accent-blue-600 cursor-pointer"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Fill (Untuk Poligon) -->
      <div
        v-if="localState.geom_type === 'polygon' || localState.geom_type === 'auto'"
        class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
      >
        <div class="text-xs font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
          <UIcon name="i-lucide-square" class="size-4 text-blue-500" aria-hidden="true" />
          <span>Warna Isian Area (Polygon Fill)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1.5">Warna &amp; Kode Fill (Hex / RGBA)</label>
            <div class="flex items-center gap-2">
              <input
                v-model="fillHexModel"
                type="color"
                aria-label="Pilih warna isian poligon"
                class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
              />
              <UInput
                v-model="localState.fill_color"
                placeholder="rgba(37, 99, 235, 0.15)"
                class="w-full font-mono text-xs"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1.5">
              <label for="fill-opacity-slider">Transparansi Isian (Fill Opacity)</label>
              <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ fillOpacityPctModel }}%</span>
            </div>
            <input
              id="fill-opacity-slider"
              v-model.number="fillOpacityPctModel"
              type="range"
              min="0"
              max="100"
              step="5"
              class="w-full accent-blue-600 cursor-pointer mt-1.5"
            />
          </div>
        </div>
      </div>

      <!-- Section 4: Point Marker (Untuk Titik) -->
      <div
        v-if="localState.geom_type === 'point' || localState.geom_type === 'auto'"
        class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-4"
      >
        <div class="text-xs font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
          <UIcon name="i-lucide-map-pin" class="size-4 text-sky-500" aria-hidden="true" />
          <span>Pengaturan Simbol Titik (Circle Marker)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1.5">Warna Titik Utama</label>
            <div class="flex items-center gap-2">
              <input
                v-model="localState.point_color"
                type="color"
                aria-label="Warna titik"
                class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
              />
              <UInput
                v-model="localState.point_color"
                placeholder="#0284c7"
                class="w-32 font-mono text-xs"
              />
            </div>
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
              <button
                v-for="c in quickColors"
                :key="c.hex"
                type="button"
                :style="{ backgroundColor: c.hex }"
                class="size-5 rounded-full border border-black/10 dark:border-white/20 cursor-pointer transition-transform hover:scale-115 active:scale-95"
                :title="`${c.name} (${c.hex})`"
                @click="applyPrimaryColor(c.hex)"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1.5">Warna Border Titik</label>
            <div class="flex items-center gap-2">
              <input
                v-model="localState.point_border_color"
                type="color"
                aria-label="Warna border titik"
                class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
              />
              <UInput
                v-model="localState.point_border_color"
                placeholder="#ffffff"
                class="w-32 font-mono text-xs"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
              <label for="point-radius-slider">Radius Titik (px)</label>
              <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ localState.point_radius }} px</span>
            </div>
            <input
              id="point-radius-slider"
              v-model.number="localState.point_radius"
              type="range"
              min="2"
              max="20"
              step="1"
              class="w-full accent-blue-600 cursor-pointer mt-1.5"
            />
          </div>

          <div>
            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
              <label for="point-border-width-slider">Tebal Border Titik (px)</label>
              <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ localState.point_border_width ?? 1.5 }} px</span>
            </div>
            <input
              id="point-border-width-slider"
              v-model.number="localState.point_border_width"
              type="range"
              min="0"
              max="6"
              step="0.5"
              class="w-full accent-blue-600 cursor-pointer mt-1.5"
            />
          </div>
        </div>
      </div>
          </template>

          <!-- MODE 2: CATEGORIZED -->
          <template v-else-if="activeSymbolMode === 'categorized'">
            <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                    Categorized (Klasifikasi Nilai Unik)
                  </h3>
                  <p class="text-[11px] text-gray-600 dark:text-gray-300">
                    Bedakan warna otomatis berdasarkan setiap nilai kategori unik pada kolom atribut.
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <UButton
                    v-if="props.layerId"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-refresh-cw"
                    label="Segarkan Kolom"
                    :loading="isLoadingAttributes"
                    class="cursor-pointer min-h-[34px]"
                    @click="fetchAttributes(props.layerId)"
                  />
                  <UButton
                    size="xs"
                    color="primary"
                    icon="i-lucide-sparkles"
                    label="Klasifikasi Nilai"
                    class="cursor-pointer min-h-[34px] font-semibold"
                    @click="classifyCategorized"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-lg bg-gray-50/70 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05]">
                <div>
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Kolom Kategori
                  </label>
                  <USelect
                    v-model="localState.classify_field"
                    :items="getRuleAttributeOptions(localState.classify_field)"
                    :portal="false"
                    :loading="isLoadingAttributes"
                    placeholder="Pilih kolom..."
                    class="w-full font-mono text-xs"
                  />
                </div>
                <div>
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Palet Warna Kategori
                  </label>
                  <USelect
                    v-model="localState.color_ramp"
                    :items="categorizedPaletteItems"
                    :portal="false"
                    class="w-full text-xs"
                  />
                </div>
              </div>

              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                  Daftar Kategori ({{ localState.rules?.length || 0 }})
                </span>
                <UButton
                  size="xs"
                  color="neutral"
                  variant="outline"
                  icon="i-lucide-plus"
                  label="Tambah Kategori"
                  class="cursor-pointer min-h-[32px]"
                  @click="addCategorizedRow"
                />
              </div>

              <div v-if="!localState.rules || localState.rules.length === 0" class="py-5 text-center text-xs text-gray-600 dark:text-gray-300 border border-dashed border-gray-200 dark:border-white/[0.08] rounded-lg">
                Pilih kolom lalu klik "Klasifikasi Nilai" atau "Tambah Kategori" untuk mulai mewarnai kategori.
              </div>

              <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-0.5">
                <div
                  v-for="(rule, idx) in localState.rules"
                  :key="rule.id || idx"
                  class="p-2.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02] grid grid-cols-1 sm:grid-cols-12 gap-2 items-center"
                >
                  <div class="sm:col-span-3 flex items-center gap-2">
                    <input
                      v-model="rule.color"
                      type="color"
                      aria-label="Warna kategori"
                      class="size-8 rounded cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
                    />
                    <input
                      v-model="rule.color"
                      type="text"
                      class="w-20 font-mono text-[11px] rounded border border-gray-300 dark:border-white/10 bg-transparent px-1.5 py-1 text-gray-900 dark:text-white"
                    />
                  </div>
                  <div class="sm:col-span-4">
                    <label class="block text-[10px] text-gray-600 dark:text-gray-400 uppercase mb-0.5">Nilai Atribut (=)</label>
                    <UInput
                      v-model="rule.value"
                      placeholder="Mis. Jalan Kabupaten"
                      class="w-full text-xs"
                    />
                  </div>
                  <div class="sm:col-span-4">
                    <label class="block text-[10px] text-gray-600 dark:text-gray-400 uppercase mb-0.5">Label Legenda</label>
                    <UInput
                      v-model="rule.label"
                      :placeholder="String(rule.value || 'Label tampil')"
                      class="w-full text-xs"
                    />
                  </div>
                  <div class="sm:col-span-1 flex justify-end pt-3 sm:pt-0">
                    <UButton
                      icon="i-lucide-trash-2"
                      size="xs"
                      color="error"
                      variant="ghost"
                      class="cursor-pointer"
                      aria-label="Hapus kategori"
                      @click="removeRule(idx)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- MODE 3: GRADUATED -->
          <template v-else-if="activeSymbolMode === 'graduated'">
            <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                    Graduated (Rentang Nilai Numerik Bergradasi)
                  </h3>
                  <p class="text-[11px] text-gray-600 dark:text-gray-300">
                    Kelompokkan kolom angka ke beberapa kelas interval warna secara bertingkat.
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <UButton
                    v-if="props.layerId"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-refresh-cw"
                    label="Segarkan Kolom"
                    :loading="isLoadingAttributes"
                    class="cursor-pointer min-h-[34px]"
                    @click="fetchAttributes(props.layerId)"
                  />
                  <UButton
                    size="xs"
                    color="primary"
                    icon="i-lucide-sliders"
                    label="Buat Rentang Kelas"
                    class="cursor-pointer min-h-[34px] font-semibold"
                    @click="classifyGraduated"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-3 rounded-lg bg-gray-50/70 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05]">
                <div class="sm:col-span-2">
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Kolom Numerik
                  </label>
                  <USelect
                    v-model="localState.classify_field"
                    :items="getRuleAttributeOptions(localState.classify_field)"
                    :portal="false"
                    :loading="isLoadingAttributes"
                    placeholder="Pilih kolom angka..."
                    class="w-full font-mono text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Jumlah Kelas
                  </label>
                  <USelect
                    v-model="localState.graduated_classes"
                    :items="graduatedClassCountOptions"
                    :portal="false"
                    class="w-full text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Skema Gradasi
                  </label>
                  <USelect
                    v-model="localState.color_ramp"
                    :items="graduatedRampItems"
                    :portal="false"
                    class="w-full text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Nilai Minimum
                  </label>
                  <UInput
                    v-model.number="graduatedMinInput"
                    type="number"
                    class="w-full font-mono text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Nilai Maksimum
                  </label>
                  <UInput
                    v-model.number="graduatedMaxInput"
                    type="number"
                    class="w-full font-mono text-xs"
                  />
                </div>
              </div>

              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                  Kelas Interval ({{ localState.rules?.length || 0 }})
                </span>
                <UButton
                  size="xs"
                  color="neutral"
                  variant="outline"
                  icon="i-lucide-plus"
                  label="Tambah Rentang"
                  class="cursor-pointer min-h-[32px]"
                  @click="addGraduatedRow"
                />
              </div>

              <div v-if="!localState.rules || localState.rules.length === 0" class="py-5 text-center text-xs text-gray-600 dark:text-gray-300 border border-dashed border-gray-200 dark:border-white/[0.08] rounded-lg">
                Tentukan nilai Min/Max lalu klik "Buat Rentang Kelas" untuk menghasilkan kelas gradasi.
              </div>

              <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-0.5">
                <div
                  v-for="(rule, idx) in localState.rules"
                  :key="rule.id || idx"
                  class="p-2.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02] grid grid-cols-1 sm:grid-cols-12 gap-2 items-center"
                >
                  <div class="sm:col-span-3 flex items-center gap-2">
                    <input
                      v-model="rule.color"
                      type="color"
                      aria-label="Warna kelas"
                      class="size-8 rounded cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
                    />
                    <input
                      v-model="rule.color"
                      type="text"
                      class="w-20 font-mono text-[11px] rounded border border-gray-300 dark:border-white/10 bg-transparent px-1.5 py-1 text-gray-900 dark:text-white"
                    />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-[10px] text-gray-600 dark:text-gray-400 uppercase mb-0.5">Min</label>
                    <UInput
                      v-model.number="rule.min_value"
                      type="number"
                      class="w-full font-mono text-xs"
                    />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-[10px] text-gray-600 dark:text-gray-400 uppercase mb-0.5">Max</label>
                    <UInput
                      v-model.number="rule.max_value"
                      type="number"
                      class="w-full font-mono text-xs"
                    />
                  </div>
                  <div class="sm:col-span-4">
                    <label class="block text-[10px] text-gray-600 dark:text-gray-400 uppercase mb-0.5">Label Legenda</label>
                    <UInput
                      v-model="rule.label"
                      placeholder="Mis. Tinggi / 50 - 100"
                      class="w-full text-xs"
                    />
                  </div>
                  <div class="sm:col-span-1 flex justify-end pt-3 sm:pt-0">
                    <UButton
                      icon="i-lucide-trash-2"
                      size="xs"
                      color="error"
                      variant="ghost"
                      class="cursor-pointer"
                      aria-label="Hapus kelas"
                      @click="removeRule(idx)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- MODE 4: RULE BASED -->
          <template v-else-if="activeSymbolMode === 'rule_based'">
            <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                    Rule Based (Aturan Kondisi Atribut Kustom)
                  </h3>
                  <p class="text-[11px] text-gray-600 dark:text-gray-300 tabular-nums">
                    {{
                      layerAttributes.length > 0
                        ? `${nonGeometryAttributeOptions.length} kolom atribut terdeteksi dari layanan layer.`
                        : isLoadingAttributes
                          ? "Memuat daftar kolom atribut layer..."
                          : "Kombinasikan filter atribut untuk pewarnaan spesifik."
                    }}
                  </p>
                </div>

                <div class="flex items-center gap-2">
                  <UButton
                    v-if="props.layerId"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-refresh-cw"
                    label="Segarkan Kolom"
                    :loading="isLoadingAttributes"
                    class="cursor-pointer min-h-[34px]"
                    @click="fetchAttributes(props.layerId)"
                  />
                  <UButton
                    size="xs"
                    color="primary"
                    variant="soft"
                    icon="i-lucide-plus"
                    label="Tambah Aturan"
                    class="cursor-pointer min-h-[34px]"
                    @click="addRule"
                  />
                </div>
              </div>

              <div v-if="!localState.rules || localState.rules.length === 0" class="py-5 text-center text-xs text-gray-600 dark:text-gray-300 border border-dashed border-gray-200 dark:border-white/[0.08] rounded-lg">
                Belum ada aturan kondisional. Klik "Tambah Aturan" untuk menambahkan aturan warna.
              </div>

              <div v-else class="space-y-2">
                <div
                  v-for="(rule, idx) in localState.rules"
                  :key="rule.id || idx"
                  class="p-2.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02] grid grid-cols-1 sm:grid-cols-12 gap-2 items-center"
                >
                  <!-- Atribut Field (Dinamis) -->
                  <div class="sm:col-span-4">
                    <div class="flex items-center justify-between mb-0.5">
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Atribut Field
                      </label>
                      <button
                        type="button"
                        class="text-[10px] text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                        @click="customRuleFieldMap[rule.id || String(idx)] = !customRuleFieldMap[rule.id || String(idx)]"
                      >
                        {{ customRuleFieldMap[rule.id || String(idx)] ? "Dropdown" : "Manual" }}
                      </button>
                    </div>
                    <USelect
                      v-if="!customRuleFieldMap[rule.id || String(idx)]"
                      v-model="rule.field"
                      :items="getRuleAttributeOptions(rule.field)"
                      :portal="false"
                      :loading="isLoadingAttributes"
                      placeholder="Pilih kolom..."
                      class="w-full font-mono text-xs"
                    >
                      <template #item-leading>
                        <UIcon name="i-lucide-tag" class="size-3.5 text-blue-500 shrink-0" aria-hidden="true" />
                      </template>
                      <template #item-trailing="{ item }">
                        <span v-if="getItemSampleValue(item)" class="text-[10px] text-gray-500 dark:text-gray-400 truncate max-w-[100px]">
                          "{{ getItemSampleValue(item) }}"
                        </span>
                      </template>
                    </USelect>
                    <UInput
                      v-else
                      v-model="rule.field"
                      placeholder="nama kolom..."
                      class="w-full font-mono text-xs"
                    />
                  </div>

                  <!-- Operator -->
                  <div class="sm:col-span-2">
                    <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">Operator</label>
                    <USelect
                      v-model="rule.operator"
                      :items="operatorOptions"
                      :portal="false"
                      class="w-full"
                    />
                  </div>

                  <!-- Nilai Pencocokan -->
                  <div class="sm:col-span-3">
                    <template v-if="rule.operator === 'between'">
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">Min &amp; Max</label>
                      <div class="grid grid-cols-2 gap-1.5">
                        <UInput v-model.number="rule.min_value" type="number" placeholder="Min" class="w-full font-mono text-xs" />
                        <UInput v-model.number="rule.max_value" type="number" placeholder="Max" class="w-full font-mono text-xs" />
                      </div>
                    </template>
                    <template v-else>
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">Nilai Target</label>
                      <UInput
                        v-model="rule.value"
                        :placeholder="getRuleAttributeSample(rule.field) ? `Contoh: ${getRuleAttributeSample(rule.field)}` : 'Nilai target...'"
                        class="w-full text-xs"
                      />
                    </template>
                  </div>

                  <!-- Warna Hasil -->
                  <div class="sm:col-span-2">
                    <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">Warna</label>
                    <div class="flex items-center gap-1.5">
                      <input
                        v-model="rule.color"
                        type="color"
                        aria-label="Warna aturan"
                        class="size-7 rounded cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
                      />
                      <input
                        v-model="rule.color"
                        type="text"
                        class="w-16 font-mono text-[11px] rounded border border-gray-300 dark:border-white/10 bg-transparent px-1 py-1 text-gray-900 dark:text-white"
                      />
                    </div>
                  </div>

                  <!-- Action Delete -->
                  <div class="sm:col-span-1 flex justify-end pt-3 sm:pt-0">
                    <UButton
                      icon="i-lucide-trash-2"
                      size="xs"
                      color="error"
                      variant="ghost"
                      class="cursor-pointer"
                      aria-label="Hapus aturan"
                      @click="removeRule(idx)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- GROUP 3: LABEL TEKS -->
        <div
          v-show="activeStyleGroup === 'label'"
          id="style-group-panel-label"
          role="tabpanel"
          aria-labelledby="style-group-tab-label"
          class="space-y-4"
        >
          <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <UIcon name="i-lucide-type" class="size-4 text-amber-500 shrink-0" aria-hidden="true" />
            <div>
              <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                Label Teks Fitur Peta
              </span>
              <span class="text-[11px] text-gray-600 dark:text-gray-300">
                Tampilkan teks label nama jalan, nama desa, atau objek saat peta diperbesar.
              </span>
            </div>
          </div>

          <USwitch v-if="localState.label" v-model="localState.label.enabled" />
        </div>

        <div v-if="localState.label?.enabled" class="space-y-4 pt-3 border-t border-gray-100 dark:border-white/[0.06]">
          <!-- Mode Label: Single Labels vs Rule Based Labelling -->
          <div
            role="tablist"
            aria-label="Mode pelabelan teks"
            class="grid grid-cols-2 gap-1.5 p-1 bg-gray-100 dark:bg-white/[0.04] rounded-xl border border-gray-200 dark:border-white/[0.08]"
          >
            <button
              v-for="lt in labelModeTabs"
              :key="lt.key"
              type="button"
              role="tab"
              :aria-selected="activeLabelMode === lt.key"
              class="py-2 px-3 rounded-lg text-xs font-semibold transition-colors cursor-pointer flex items-center justify-center gap-2 min-h-[40px]"
              :class="activeLabelMode === lt.key
                ? 'bg-blue-600 text-white shadow-xs'
                : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-white/[0.06]'"
              @click="activeLabelMode = lt.key"
            >
              <UIcon :name="lt.icon" class="size-4 shrink-0" aria-hidden="true" />
              <span>{{ lt.label }}</span>
            </button>
          </div>

          <!-- MODE 1: SINGLE LABELS -->
          <template v-if="activeLabelMode === 'single'">
          <!-- Kolom Atribut & Minimal Zoom -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                  <UIcon name="i-lucide-database" class="size-3.5 text-blue-500" aria-hidden="true" />
                  <span>Kolom Atribut Label</span>
                </label>
                <button
                  type="button"
                  class="text-[10px] text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                  @click="isCustomFieldMode = !isCustomFieldMode"
                >
                  {{ isCustomFieldMode ? 'Pilih dari Dropdown' : 'Ketik Manual' }}
                </button>
              </div>

              <div v-if="!isCustomFieldMode">
                <USelect
                  v-model="localState.label.field"
                  :items="attributeOptions"
                  :portal="false"
                  :loading="isLoadingAttributes"
                  placeholder="Pilih kolom label..."
                  class="w-full font-mono text-xs"
                >
                  <template #item-leading>
                    <UIcon name="i-lucide-tag" class="size-3.5 text-blue-500 shrink-0" aria-hidden="true" />
                  </template>
                  <template #item-trailing="{ item }">
                    <span v-if="getItemSampleValue(item)" class="text-[10px] text-gray-500 dark:text-gray-400 truncate max-w-[120px]">
                      "{{ getItemSampleValue(item) }}"
                    </span>
                  </template>
                </USelect>
              </div>
              <div v-else>
                <UInput
                  v-model="localState.label.field"
                  placeholder="namobj / nama_desa / nama_ruas"
                  class="w-full font-mono text-xs"
                />
              </div>

              <div class="flex items-center justify-between mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                <span>{{ layerAttributes.length > 0 ? `${layerAttributes.length} kolom terdeteksi` : (isLoadingAttributes ? 'Memuat kolom...' : 'Kolom bawaan') }}</span>
                <button
                  v-if="props.layerId"
                  type="button"
                  class="hover:text-blue-500 flex items-center gap-1 cursor-pointer transition-colors"
                  title="Segarkan daftar kolom dari database"
                  @click="fetchAttributes(props.layerId)"
                >
                  <UIcon name="i-lucide-refresh-cw" class="size-3" :class="isLoadingAttributes ? 'animate-spin text-blue-500' : ''" aria-hidden="true" />
                  <span>Segarkan</span>
                </button>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                <span>Minimal Zoom Tampil Label</span>
                <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">Zoom {{ localState.label.min_zoom }}</span>
              </div>
              <input
                v-model.number="localState.label.min_zoom"
                type="range"
                min="8"
                max="18"
                step="0.5"
                class="w-full accent-blue-600 cursor-pointer mt-2"
              />
            </div>
          </div>

          <!-- Bagian 1: Font Style, Style, Size, Color & Opacity -->
          <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05] space-y-3">
            <div class="text-xs font-bold text-gray-900 dark:text-white">
              Tipografi, Warna &amp; Opacity
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Font Style (Family) -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Font Style (Jenis Huruf)
                </label>
                <USelect
                  v-model="localState.label.font_family"
                  :items="fontFamilyOptions"
                  :portal="false"
                  class="w-full text-xs"
                />
              </div>

              <!-- Style: Regular, Italic, Bold, Bold Italic -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Style (Gaya Teks)
                </label>
                <div class="grid grid-cols-4 gap-1 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                  <button
                    v-for="fs in fontStyleOptions"
                    :key="fs.key"
                    type="button"
                    class="py-1.5 px-1.5 rounded-md text-[11px] font-medium transition-all cursor-pointer min-h-[34px] text-center"
                    :class="[
                      (localState.label.font_style || 'bold') === fs.key
                        ? 'bg-blue-600 text-white font-semibold shadow-xs'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50',
                      fs.key === 'italic' ? 'italic' : fs.key === 'bold' ? 'font-bold' : fs.key === 'bold_italic' ? 'font-bold italic' : '',
                    ]"
                    @click="localState.label.font_style = fs.key"
                  >
                    {{ fs.label }}
                  </button>
                </div>
              </div>

              <!-- Size -->
              <div>
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                  <label for="label-font-size-slider" class="font-semibold text-gray-700 dark:text-gray-300">Size (Ukuran)</label>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ localState.label.font_size ?? 11 }} px</span>
                </div>
                <input
                  id="label-font-size-slider"
                  v-model.number="localState.label.font_size"
                  type="range"
                  min="8"
                  max="24"
                  step="1"
                  class="w-full accent-blue-600 cursor-pointer mt-1.5"
                />
              </div>

              <!-- Opacity -->
              <div>
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                  <label for="label-opacity-slider" class="font-semibold text-gray-700 dark:text-gray-300">Opacity (Transparansi)</label>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ labelOpacityPct }}%</span>
                </div>
                <input
                  id="label-opacity-slider"
                  v-model.number="labelOpacityPct"
                  type="range"
                  min="10"
                  max="100"
                  step="5"
                  class="w-full accent-blue-600 cursor-pointer mt-1.5"
                />
              </div>

              <!-- Color & Halo -->
              <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <div>
                  <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Color (Warna Teks)</label>
                  <div class="flex items-center gap-2">
                    <input
                      v-model="localState.label.color"
                      type="color"
                      aria-label="Warna teks label"
                      class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
                    />
                    <UInput
                      v-model="localState.label.color"
                      placeholder="#1e3a8a"
                      class="w-32 font-mono text-xs"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Halo Outline (Garis Luar)</label>
                  <div class="flex items-center gap-2">
                    <input
                      v-model="localState.label.halo_color"
                      type="color"
                      aria-label="Warna halo label"
                      class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
                    />
                    <input
                      v-model.number="localState.label.halo_width"
                      type="range"
                      min="0"
                      max="6"
                      step="0.5"
                      aria-label="Ketebalan halo label"
                      class="flex-1 accent-blue-600 cursor-pointer"
                    />
                    <span class="text-[11px] font-mono text-gray-700 dark:text-gray-300 tabular-nums shrink-0">
                      {{ localState.label.halo_width ?? 3 }}px
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </template>

          <!-- MODE 2: RULE BASED LABELLING -->
          <template v-else>
            <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05] space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <div class="text-xs font-bold text-gray-900 dark:text-white">
                    Rule Based Labelling (Label Berdasarkan Kondisi Atribut)
                  </div>
                  <p class="text-[11px] text-gray-600 dark:text-gray-300">
                    Tampilkan label dengan ukuran, warna, dan kolom teks berbeda untuk tiap kriteria fitur.
                  </p>
                </div>

                <div class="flex items-center gap-2">
                  <UButton
                    v-if="props.layerId"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-refresh-cw"
                    label="Segarkan Kolom"
                    :loading="isLoadingAttributes"
                    class="cursor-pointer min-h-[34px]"
                    @click="fetchAttributes(props.layerId)"
                  />
                  <UButton
                    size="xs"
                    color="primary"
                    variant="soft"
                    icon="i-lucide-plus"
                    label="Tambah Aturan Label"
                    class="cursor-pointer min-h-[34px]"
                    @click="addLabelRule"
                  />
                </div>
              </div>

              <div
                v-if="!localState.label.rules || localState.label.rules.length === 0"
                class="py-5 text-center text-xs text-gray-600 dark:text-gray-300 border border-dashed border-gray-200 dark:border-white/[0.08] rounded-lg"
              >
                Belum ada aturan pelabelan. Klik "Tambah Aturan Label" untuk menentukan kondisi label fitur.
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="(lrule, idx) in localState.label.rules"
                  :key="lrule.id || idx"
                  class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
                >
                  <div class="flex items-center justify-between gap-2 border-b border-gray-100 dark:border-white/[0.06] pb-2">
                    <span class="text-[11px] font-bold text-gray-800 dark:text-gray-200 tabular-nums">
                      Aturan Label #{{ idx + 1 }}
                    </span>
                    <UButton
                      icon="i-lucide-trash-2"
                      size="xs"
                      color="error"
                      variant="ghost"
                      label="Hapus"
                      class="cursor-pointer"
                      @click="removeLabelRule(idx)"
                    />
                  </div>

                  <!-- Kondisi Filter -->
                  <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                    <div class="sm:col-span-5">
                      <div class="flex items-center justify-between mb-0.5">
                        <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                          Atribut Filter
                        </label>
                        <button
                          type="button"
                          class="text-[10px] text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                          @click="customLabelFilterFieldMap[lrule.id || String(idx)] = !customLabelFilterFieldMap[lrule.id || String(idx)]"
                        >
                          {{ customLabelFilterFieldMap[lrule.id || String(idx)] ? "Dropdown" : "Manual" }}
                        </button>
                      </div>
                      <USelect
                        v-if="!customLabelFilterFieldMap[lrule.id || String(idx)]"
                        v-model="lrule.filter_field"
                        :items="getRuleAttributeOptions(lrule.filter_field)"
                        :portal="false"
                        :loading="isLoadingAttributes"
                        class="w-full font-mono text-xs"
                      />
                      <UInput
                        v-else
                        v-model="lrule.filter_field"
                        placeholder="nama kolom..."
                        class="w-full font-mono text-xs"
                      />
                    </div>

                    <div class="sm:col-span-3">
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                        Operator
                      </label>
                      <USelect
                        v-model="lrule.operator"
                        :items="operatorOptions"
                        :portal="false"
                        class="w-full"
                      />
                    </div>

                    <div class="sm:col-span-4">
                      <template v-if="lrule.operator === 'between'">
                        <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                          Min &amp; Max
                        </label>
                        <div class="grid grid-cols-2 gap-1.5">
                          <UInput v-model.number="lrule.min_value" type="number" placeholder="Min" class="w-full font-mono text-xs" />
                          <UInput v-model.number="lrule.max_value" type="number" placeholder="Max" class="w-full font-mono text-xs" />
                        </div>
                      </template>
                      <template v-else>
                        <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                          Nilai Target
                        </label>
                        <UInput
                          v-model="lrule.value"
                          :placeholder="getRuleAttributeSample(lrule.filter_field) ? `Contoh: ${getRuleAttributeSample(lrule.filter_field)}` : 'Nilai target...'"
                          class="w-full text-xs"
                        />
                      </template>
                    </div>
                  </div>

                  <!-- Gaya Teks untuk Aturan -->
                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5 pt-2 border-t border-gray-100 dark:border-white/[0.06] items-end">
                    <div class="lg:col-span-4">
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                        Kolom Teks Label
                      </label>
                      <USelect
                        v-model="lrule.label_field"
                        :items="getRuleAttributeOptions(lrule.label_field)"
                        :portal="false"
                        :loading="isLoadingAttributes"
                        class="w-full font-mono text-xs"
                      />
                    </div>

                    <div class="lg:col-span-3">
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                        Style Teks
                      </label>
                      <USelect
                        v-model="lrule.font_style"
                        :items="fontStyleSelectItems"
                        :portal="false"
                        class="w-full text-xs"
                      />
                    </div>

                    <div class="lg:col-span-2">
                      <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                        Size (px)
                      </label>
                      <UInput
                        v-model.number="lrule.font_size"
                        type="number"
                        min="8"
                        max="24"
                        class="w-full font-mono text-xs"
                      />
                    </div>

                    <div class="lg:col-span-3 flex items-center gap-2">
                      <div class="flex-1">
                        <label class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-0.5">
                          Color
                        </label>
                        <div class="flex items-center gap-1.5">
                          <input
                            v-model="lrule.color"
                            type="color"
                            aria-label="Warna teks aturan"
                            class="size-7 rounded cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent shrink-0"
                          />
                          <input
                            v-model="lrule.color"
                            type="text"
                            class="w-full font-mono text-[11px] rounded border border-gray-300 dark:border-white/10 bg-transparent px-1 py-1 text-gray-900 dark:text-white"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Bagian 2: Formating (Placement, Orientasi, Alignment, Wrap/Unwrap, autoWrap, Offset Y, Offset X, Overflow) -->
          <div class="p-3 rounded-lg bg-gray-50/70 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05] space-y-3">
            <div class="text-xs font-bold text-gray-900 dark:text-white">
              Formating
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Placement -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                  Placement (Peletakan)
                </label>
                <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                  <button
                    type="button"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer flex items-center justify-center gap-1.5 min-h-[36px]"
                    :class="localState.label.placement === 'point'
                      ? 'bg-blue-600 text-white font-semibold shadow-xs'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50'"
                    @click="localState.label.placement = 'point'"
                  >
                    <UIcon name="i-lucide-map-pin" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Centroid</span>
                  </button>

                  <button
                    type="button"
                    :disabled="localState.geom_type === 'point'"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1.5 min-h-[36px]"
                    :class="[
                      localState.geom_type === 'point'
                        ? 'opacity-40 cursor-not-allowed text-gray-400'
                        : localState.label.placement === 'line'
                        ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                    ]"
                    @click="localState.label.placement = 'line'"
                  >
                    <UIcon name="i-lucide-spline" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Kontur Garis</span>
                  </button>
                </div>
              </div>

              <!-- Orientasi -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 flex items-center justify-between">
                  <span>Orientasi</span>
                  <span v-if="localState.label.placement === 'line'" class="text-[10px] text-amber-500 font-normal">
                    (Ikuti garis)
                  </span>
                </label>
                <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                  <button
                    type="button"
                    :disabled="localState.label.placement === 'line'"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1.5 min-h-[36px]"
                    :class="[
                      localState.label.placement === 'line'
                        ? 'opacity-40 cursor-not-allowed text-gray-400'
                        : localState.label.orientation !== 'vertical'
                        ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                    ]"
                    @click="localState.label.orientation = 'horizontal'"
                  >
                    <UIcon name="i-lucide-arrow-right" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Horizontal</span>
                  </button>

                  <button
                    type="button"
                    :disabled="localState.label.placement === 'line'"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1.5 min-h-[36px]"
                    :class="[
                      localState.label.placement === 'line'
                        ? 'opacity-40 cursor-not-allowed text-gray-400'
                        : localState.label.orientation === 'vertical'
                        ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                    ]"
                    @click="localState.label.orientation = 'vertical'"
                  >
                    <UIcon name="i-lucide-arrow-down" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Vertikal</span>
                  </button>
                </div>
              </div>

              <!-- Alignment -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 flex items-center justify-between">
                  <span>Alignment</span>
                  <span v-if="localState.label.placement === 'line' && localState.geom_type !== 'point'" class="text-[10px] text-amber-500 font-normal">
                    (Khusus centroid/titik)
                  </span>
                </label>
                <div class="grid grid-cols-3 gap-1 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                  <button
                    type="button"
                    :disabled="localState.label.placement === 'line' && localState.geom_type !== 'point'"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1 min-h-[36px]"
                    :class="[
                      localState.label.placement === 'line' && localState.geom_type !== 'point'
                        ? 'opacity-40 cursor-not-allowed text-gray-400'
                        : localState.label.align === 'left'
                        ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                    ]"
                    @click="localState.label.align = 'left'"
                  >
                    <UIcon name="i-lucide-align-left" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Kiri</span>
                  </button>

                  <button
                    type="button"
                    :disabled="localState.label.placement === 'line' && localState.geom_type !== 'point'"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1 min-h-[36px]"
                    :class="[
                      localState.label.placement === 'line' && localState.geom_type !== 'point'
                        ? 'opacity-40 cursor-not-allowed text-gray-400'
                        : !localState.label.align || localState.label.align === 'center'
                        ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                    ]"
                    @click="localState.label.align = 'center'"
                  >
                    <UIcon name="i-lucide-align-center" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Tengah</span>
                  </button>

                  <button
                    type="button"
                    :disabled="localState.label.placement === 'line' && localState.geom_type !== 'point'"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1 min-h-[36px]"
                    :class="[
                      localState.label.placement === 'line' && localState.geom_type !== 'point'
                        ? 'opacity-40 cursor-not-allowed text-gray-400'
                        : localState.label.align === 'right'
                        ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                    ]"
                    @click="localState.label.align = 'right'"
                  >
                    <UIcon name="i-lucide-align-right" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Kanan</span>
                  </button>
                </div>
              </div>

              <!-- Wrap / Unwrap -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                  Wrap / Unwrap
                </label>
                <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                  <button
                    type="button"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer flex items-center justify-center gap-1.5 min-h-[36px]"
                    :class="localState.label.wrap !== false
                      ? 'bg-blue-600 text-white font-semibold shadow-xs'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50'"
                    @click="localState.label.wrap = true"
                  >
                    <UIcon name="i-lucide-wrap-text" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Wrap</span>
                  </button>

                  <button
                    type="button"
                    class="py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer flex items-center justify-center gap-1.5 min-h-[36px]"
                    :class="localState.label.wrap === false
                      ? 'bg-blue-600 text-white font-semibold shadow-xs'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50'"
                    @click="localState.label.wrap = false"
                  >
                    <UIcon name="i-lucide-minus" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Unwrap</span>
                  </button>
                </div>
              </div>

              <!-- autoWrap slider -->
              <div v-if="localState.label.wrap !== false" class="sm:col-span-2 pt-1 border-t border-gray-200/60 dark:border-white/[0.05]">
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                  <label for="label-wrap-width-slider" class="font-semibold text-gray-700 dark:text-gray-300">autoWrap (Lebar Maksimal Baris)</label>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">
                    {{ localState.label.wrap_width ?? 90 }} px
                  </span>
                </div>
                <input
                  id="label-wrap-width-slider"
                  v-model.number="localState.label.wrap_width"
                  type="range"
                  min="40"
                  max="240"
                  step="10"
                  class="w-full accent-blue-600 cursor-pointer"
                />
              </div>

              <!-- Offset X & Offset Y -->
              <div>
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                  <label for="label-offset-x-slider" class="font-semibold text-gray-700 dark:text-gray-300">Offset X (Horizontal)</label>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ localState.label.offset_x ?? 0 }} px</span>
                </div>
                <input
                  id="label-offset-x-slider"
                  v-model.number="localState.label.offset_x"
                  type="range"
                  min="-40"
                  max="40"
                  step="2"
                  class="w-full accent-blue-600 cursor-pointer"
                />
              </div>

              <div>
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                  <label for="label-offset-y-slider" class="font-semibold text-gray-700 dark:text-gray-300">Offset Y (Vertikal)</label>
                  <span class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums">{{ localState.label.offset_y ?? 0 }} px</span>
                </div>
                <input
                  id="label-offset-y-slider"
                  v-model.number="localState.label.offset_y"
                  type="range"
                  min="-40"
                  max="40"
                  step="2"
                  class="w-full accent-blue-600 cursor-pointer"
                />
              </div>

              <!-- Overflow -->
              <div class="sm:col-span-2 flex items-center justify-between gap-3 pt-2 border-t border-gray-200/60 dark:border-white/[0.05]">
                <div>
                  <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                    Overflow (Luapan Batas)
                  </span>
                  <span class="text-[10px] text-gray-500 dark:text-gray-400">
                    Tetap tampil walau teks melebihi area poligon atau saling berdekatan.
                  </span>
                </div>
                <USwitch v-model="localState.label.overflow" />
              </div>
            </div>
          </div>
        </div>
          </div>
        </div>

        <!-- GROUP 4: XML SLD & JSON -->
        <div
          v-show="activeStyleGroup === 'kode'"
          id="style-group-panel-kode"
          role="tabpanel"
          aria-labelledby="style-group-tab-kode"
          class="space-y-4"
        >
          <!-- Mode Switcher dalam Group Kode -->
          <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19]">
            <div class="text-xs font-semibold text-gray-900 dark:text-white">
              Mode Penyuntingan Kode
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <UButton
                v-if="isWmsProtocol"
                size="xs"
                :color="!isRawJsonMode && localState.sld_mode === 'xml' ? 'primary' : 'neutral'"
                :variant="!isRawJsonMode && localState.sld_mode === 'xml' ? 'solid' : 'outline'"
                icon="i-lucide-file-code-2"
                :label="localState.sld_mode === 'xml' && !isRawJsonMode ? 'XML SLD Aktif' : 'Gunakan XML SLD Kustom'"
                class="cursor-pointer min-h-[36px]"
                @click="handleSwitchToSldXmlMode"
              />
              <UButton
                v-if="isWmsProtocol && localState.sld_mode === 'xml' && !isRawJsonMode"
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-sliders-horizontal"
                label="Kembali ke Mode Visual"
                class="cursor-pointer min-h-[36px]"
                @click="handleSwitchToVisualFromXml"
              />
              <UButton
                size="xs"
                :color="isRawJsonMode ? 'primary' : 'neutral'"
                :variant="isRawJsonMode ? 'solid' : 'outline'"
                icon="i-lucide-braces"
                :label="isRawJsonMode ? 'Simpan & Keluar JSON' : 'Edit JSON style_config'"
                class="cursor-pointer min-h-[36px]"
                @click="handleToggleMode"
              />
            </div>
          </div>

          <!-- Editor Raw SLD XML (Khusus WMS) -->
          <div
            v-if="!isRawJsonMode && isWmsProtocol && localState.sld_mode === 'xml'"
            class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="space-y-0.5">
                <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                  Editor XML OGC SLD 1.0.0 (Langsung ke GeoServer)
                </span>
                <span class="text-[11px] text-gray-600 dark:text-gray-300">
                  Tag &lt;NamedLayer&gt;&lt;Name&gt;: <code class="font-mono">{{ effectiveLayerNameForSld }}</code>
                </span>
              </div>
              <UButton
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-wand-2"
                label="Generate dari Form Visual"
                class="cursor-pointer"
                @click="handleRegenerateXmlFromVisual"
              />
            </div>

            <textarea
              v-model="localState.custom_sld_xml"
              rows="14"
              aria-label="Kode XML SLD 1.0.0"
              class="w-full font-mono text-xs rounded-lg border border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-black/40 p-3 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="<StyledLayerDescriptor version=&quot;1.0.0&quot; xmlns=&quot;http://www.opengis.net/sld&quot;>...</StyledLayerDescriptor>"
            />

            <div class="text-[11px] text-gray-600 dark:text-gray-300 tabular-nums">
              Panjang XML: {{ (localState.custom_sld_xml || '').length.toLocaleString('id-ID') }} karakter
              ({{ sldUsesPostTransport ? 'akan dikirim via HTTP POST GetMap' : 'akan dikirim via HTTP GET SLD_BODY' }})
            </div>
          </div>

          <!-- Editor Raw JSON -->
          <div
            v-else-if="isRawJsonMode"
            class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-mono text-gray-600 dark:text-gray-300">
                format: style_config (JSON Object)
              </span>
              <UButton
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-check-check"
                label="Rapikan JSON"
                class="cursor-pointer"
                @click="handleFormatJson"
              />
            </div>

            <textarea
              v-model="rawJsonText"
              rows="14"
              aria-label="Kode JSON style_config"
              class="w-full font-mono text-xs rounded-lg border border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-black/40 p-3 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />

            <div v-if="jsonError" class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-xs text-red-700 dark:text-red-300 flex items-center gap-1.5">
              <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" aria-hidden="true" />
              <span>{{ jsonError }}</span>
            </div>
          </div>

          <!-- Pratinjau Legenda GeoServer & XML SLD (Khusus WMS) -->
          <div
            v-if="isWmsProtocol"
            class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-white/[0.02] space-y-3"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-image" class="size-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                <span class="text-xs font-semibold text-gray-900 dark:text-white">
                  Pratinjau Legenda GeoServer &amp; Output SLD
                </span>
              </div>

              <button
                type="button"
                class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                @click="showWmsSldPreview = !showWmsSldPreview"
              >
                {{ showWmsSldPreview ? 'Sembunyikan XML SLD' : 'Lihat XML SLD Terbentuk' }}
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-4">
              <div
                v-if="wmsLiveLegendUrl && !wmsLegendPreviewFailed"
                class="p-2 rounded-lg bg-white dark:bg-[#0c1424] border border-gray-200 dark:border-white/10 inline-flex items-center justify-center"
              >
                <img
                  :src="wmsLiveLegendUrl"
                  alt="Pratinjau GetLegendGraphic GeoServer"
                  class="max-h-28 object-contain"
                  loading="lazy"
                  @error="wmsLegendPreviewFailed = true"
                />
              </div>
              <div v-else class="text-xs text-gray-600 dark:text-gray-300">
                {{
                  sldUsesPostTransport
                    ? 'SLD berukuran besar (> 6,5 KB), sehingga legenda di peta memakai swatch aturan dan GetMap menggunakan HTTP POST.'
                    : 'Pratinjau gambar GetLegendGraphic belum tersedia untuk konfigurasi ini.'
                }}
              </div>
            </div>

            <pre
              v-if="showWmsSldPreview && generatedSldXml"
              class="p-3 rounded-lg bg-gray-900 text-gray-100 text-[11px] font-mono overflow-x-auto max-h-56 whitespace-pre-wrap break-all"
            >{{ generatedSldXml }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


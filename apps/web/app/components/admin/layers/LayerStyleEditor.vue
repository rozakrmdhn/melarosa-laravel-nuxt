<script setup lang="ts">
import type { LayerStyleConfig, StyleRule, GeometryType, DashPreset } from "~/types/map-layers";

const props = withDefaults(
  defineProps<{
    modelValue?: LayerStyleConfig | null;
    protocol?: string;
    layerId?: string | null;
    layerName?: string | null;
  }>(),
  {
    modelValue: null,
    protocol: "mvt",
    layerId: null,
    layerName: null,
  }
);

const emit = defineEmits<{
  (e: "update:modelValue", value: LayerStyleConfig): void;
}>();

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

// Helper normalisasi konfigurasi style
function getInitialConfig(source?: LayerStyleConfig | null): LayerStyleConfig {
  return {
    geom_type: source?.geom_type || "line",
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
      field: source?.label?.field || "namobj",
      min_zoom: source?.label?.min_zoom ?? 13,
      color: source?.label?.color || "#1e3a8a",
      halo_color: source?.label?.halo_color || "#ffffff",
      halo_width: source?.label?.halo_width ?? 3,
      font_size: source?.label?.font_size ?? 11,
      placement: source?.label?.placement || (source?.geom_type === "line" ? "line" : "point"),
      orientation: source?.label?.orientation || "horizontal",
      rotation: source?.label?.rotation ?? 0,
      offset_x: source?.label?.offset_x ?? 0,
      offset_y: source?.label?.offset_y ?? 0,
      overflow: Boolean(source?.label?.overflow),
    },
    rules: source?.rules ? JSON.parse(JSON.stringify(source.rules)) : [],
    zoom_scale: source?.zoom_scale !== false,
    min_zoom: source?.min_zoom ?? null,
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
    if (!newVal) return;

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

// Mode Raw JSON
const isRawJsonMode = ref(false);
const rawJsonText = ref("");
const jsonError = ref<string | null>(null);

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
  Object.assign(localState, JSON.parse(JSON.stringify(preset.config)));
  if (isRawJsonMode.value) {
    syncRawJson();
  }
  emit("update:modelValue", JSON.parse(JSON.stringify(localState)));
}

// Manajemen Aturan Kondisional (Rules)
function addRule() {
  if (!localState.rules) localState.rules = [];
  localState.rules.push({
    id: `rule_${Date.now()}`,
    field: "kondisi",
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
];

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
      // Jika field label belum diisi dan ada rekomendasi kolom nama, pilihkan otomatis
      if (!localState.label?.field && res.data.length > 0) {
        const preferred = res.data.find((a) =>
          ["nama_desa", "nama_ruas", "namobj", "nama", "name", "desa", "kecamatan"].includes(a.name.toLowerCase())
        ) || res.data[0];
        if (preferred && localState.label) {
          localState.label.field = preferred.name;
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
</script>

<template>
  <div class="space-y-5">
    <!-- Header Controls: Mode Toggle & Presets -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 pb-3 border-b border-gray-200 dark:border-white/[0.08]">
      <div class="flex items-center gap-2">
        <UIcon name="i-lucide-palette" class="size-4.5 text-blue-600 dark:text-blue-400" />
        <div>
          <h4 class="text-xs font-semibold text-gray-900 dark:text-white">
            Konfigurasi Simbologi & Style MVT
          </h4>
          <p class="text-[11px] text-gray-500 dark:text-gray-400">
            Kustomisasi warna, ketebalan, pola garis, label, dan aturan kondisi layer vektor.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          size="xs"
          :color="isRawJsonMode ? 'primary' : 'neutral'"
          :variant="isRawJsonMode ? 'solid' : 'outline'"
          :icon="isRawJsonMode ? 'i-lucide-sliders-horizontal' : 'i-lucide-code-2'"
          :label="isRawJsonMode ? 'Beralih ke Form Visual' : 'Edit Kode JSON'"
          class="cursor-pointer"
          @click="handleToggleMode"
        />
      </div>
    </div>

    <!-- MINIMAL ZOOM TAMPIL LAYER (TERPISAH DARI LABEL) -->
    <div
      v-if="!isRawJsonMode"
      class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/60 dark:bg-white/[0.02] space-y-2"
    >
      <div class="flex items-center justify-between gap-2">
        <div>
          <span class="text-xs font-semibold text-gray-900 dark:text-white block">
            Minimal Zoom Tampil Layer (MVT)
          </span>
          <span class="text-[11px] text-gray-500 dark:text-gray-400">
            Atur batas zoom minimal agar geometri layer tampil pada peta (terpisah dari minimal zoom label)
          </span>
        </div>
        <span class="px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-gray-200 dark:border-white/10 text-xs font-mono font-semibold text-gray-900 dark:text-white shrink-0">
          {{ localState.min_zoom && localState.min_zoom > 0 ? `Zoom >= ${localState.min_zoom}` : 'Semua Zoom' }}
        </span>
      </div>
      <div class="flex items-center gap-3">
        <input
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
          class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white cursor-pointer"
          @click="localState.min_zoom = null"
        >
          Reset
        </button>
      </div>
    </div>

    <!-- PRESETS QUICK SELECTOR -->
    <div v-if="!isRawJsonMode" class="space-y-2">
      <div class="text-[11px] font-semibold tracking-wider text-gray-500 dark:text-gray-400 uppercase">
        Template / Preset Cepat:
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <button
          v-for="p in presets"
          :key="p.id"
          type="button"
          class="flex flex-col text-left p-2.5 rounded-lg border border-gray-200 dark:border-white/[0.08] hover:border-blue-500/50 hover:bg-blue-50/40 dark:hover:bg-blue-950/20 transition-all cursor-pointer group"
          @click="applyPreset(p)"
        >
          <div class="flex items-center justify-between w-full">
            <span class="text-xs font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
              {{ p.name }}
            </span>
            <UIcon name="i-lucide-arrow-right" class="size-3 text-gray-400 group-hover:text-blue-500 transition-transform group-hover:translate-x-0.5" />
          </div>
          <span class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">
            {{ p.desc }}
          </span>
        </button>
      </div>
    </div>

    <!-- LIVE PREVIEW BOX -->
    <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-slate-50/70 dark:bg-[#070b14] space-y-2">
      <div class="flex items-center justify-between text-xs font-semibold text-gray-700 dark:text-gray-300">
        <span class="flex items-center gap-1.5">
          <UIcon name="i-lucide-eye" class="size-4 text-blue-500" />
          Pratinjau Simbol Kartografi (Live Preview)
        </span>
        <span class="text-[11px] font-normal text-gray-500 dark:text-gray-400">
          Tipe: {{ localState.geom_type?.toUpperCase() }} · {{ localState.rules?.length || 0 }} Aturan
          <template v-if="localState.label?.enabled && localState.label?.field">
            · Nilai Label: "{{ previewLabelValue }}"
          </template>
        </span>
      </div>

      <!-- Canvas SVG Preview -->
      <div class="relative h-24 w-full rounded-lg border border-dashed border-gray-300 dark:border-white/10 bg-white dark:bg-[#0b0f19] flex items-center justify-center overflow-hidden">
        <svg class="w-full h-full" viewBox="0 0 500 100" preserveAspectRatio="none">
          <!-- Background grid halus untuk konteks kartografi -->
          <defs>
            <pattern id="gridPattern" width="20" height="20" patternUnits="userSpaceOnUse">
              <path d="M 20 0 L 0 0 0 20" fill="none" stroke="currentColor" class="text-gray-200/50 dark:text-white/[0.03]" stroke-width="1" />
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#gridPattern)" />

          <!-- A. Polygon Preview -->
          <g v-if="localState.geom_type === 'polygon' || localState.geom_type === 'auto'">
            <!-- Underlay Casing (Halo) jika aktif -->
            <polygon
              v-if="localState.casing"
              points="60,20 220,15 250,85 100,80"
              :fill="localState.fill_color || 'transparent'"
              :stroke="localState.casing_color || 'rgba(148, 163, 184, 0.3)'"
              :stroke-width="Number(localState.casing_width ?? 6)"
              stroke-linejoin="round"
            />
            <!-- Main Polygon Border & Fill -->
            <polygon
              points="60,20 220,15 250,85 100,80"
              :fill="localState.fill_color || 'rgba(59, 130, 246, 0.15)'"
              :stroke="localState.color || '#475569'"
              :stroke-width="Number(localState.width ?? 2)"
              :stroke-dasharray="previewStrokeDasharray"
              stroke-linejoin="round"
            />
          </g>

          <!-- B. LineString Preview -->
          <g v-if="localState.geom_type === 'line' || localState.geom_type === 'auto'">
            <!-- Underlay Casing (Halo) jika aktif -->
            <path
              v-if="localState.casing"
              d="M 40,50 Q 150,15 250,50 T 460,50"
              fill="none"
              :stroke="localState.casing_color || 'rgba(148, 163, 184, 0.3)'"
              :stroke-width="Number(localState.casing_width ?? 6)"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <!-- Main Line -->
            <path
              d="M 40,50 Q 150,15 250,50 T 460,50"
              fill="none"
              :stroke="localState.color || '#2563eb'"
              :stroke-width="Number(localState.width ?? 3)"
              :stroke-dasharray="previewStrokeDasharray"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </g>

          <!-- C. Point / Circle Preview -->
          <g v-if="localState.geom_type === 'point' || localState.geom_type === 'auto'">
            <circle
              cx="250"
              cy="50"
              :r="Number(localState.point_radius ?? 7)"
              :fill="localState.point_color || localState.color || '#0284c7'"
              :stroke="localState.point_border_color || '#ffffff'"
              :stroke-width="Number(localState.point_border_width ?? 2)"
            />
          </g>

          <!-- D. Label Text Preview -->
          <g v-if="localState.label?.enabled">
            <text
              :x="250 + Number(localState.label.offset_x ?? 0)"
              :y="(localState.geom_type === 'point' ? 76 : 50) + Number(localState.label.offset_y ?? 0)"
              :transform="localState.label.orientation === 'vertical' && localState.label.placement !== 'line'
                ? `rotate(90, ${250 + Number(localState.label.offset_x ?? 0)}, ${(localState.geom_type === 'point' ? 76 : 50) + Number(localState.label.offset_y ?? 0)})`
                : undefined"
              text-anchor="middle"
              :fill="localState.label.color || '#1e3a8a'"
              :stroke="localState.label.halo_color || '#ffffff'"
              :stroke-width="Number(localState.label.halo_width ?? 3)"
              paint-order="stroke fill"
              :font-size="Number(localState.label.font_size ?? 11)"
              font-family="Inter, sans-serif"
              font-weight="600"
            >
              {{ sampleLabelText }}
            </text>
          </g>
        </svg>
      </div>
    </div>

    <!-- RAW JSON CODE MODE -->
    <div v-if="isRawJsonMode" class="space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
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
        class="w-full font-mono text-xs rounded-lg border border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-black/40 p-3 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
      />

      <div v-if="jsonError" class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5">
        <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" />
        <span>{{ jsonError }}</span>
      </div>
    </div>

    <!-- FORM VISUAL MODE -->
    <div v-else class="space-y-4">
      <!-- Section 1: Geometri & Warna Dasar -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <UFormField label="Tipe Geometri Peta">
          <USelect
            v-model="localState.geom_type"
            :items="geomOptions"
            :portal="false"
            class="w-full"
          />
        </UFormField>

        <UFormField label="Bentuk Garis (Line Dash)">
          <USelect
            v-model="dashModelValue"
            :items="dashOptions"
            :portal="false"
            class="w-full"
          >
            <!-- Mini SVG line preview pada trigger -->
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

            <!-- Mini SVG line preview pada setiap item dropdown -->
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

      <!-- Section 2: Warna & Ketebalan Garis -->
      <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3">
        <div class="text-xs font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
          <UIcon name="i-lucide-paintbrush" class="size-4 text-blue-500" />
          <span>Warna & Garis Tepi (Stroke & Border)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Color Picker with Swatches -->
          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1.5">Warna Utama Garis / Stroke</label>
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
                @click="localState.color = c.hex"
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
          <UIcon name="i-lucide-square" class="size-4 text-purple-500" />
          <span>Warna Isian Area (Polygon Fill)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Warna Isian (Hex / RGBA)</label>
            <UInput
              v-model="localState.fill_color"
              placeholder="rgba(139, 92, 246, 0.15)"
              class="w-full font-mono text-xs"
            />
            <span class="text-[11px] text-gray-400 mt-1 block">
              Gunakan RGBA untuk transparansi, misal: <code class="font-mono">rgba(59, 130, 246, 0.15)</code>
            </span>
          </div>
        </div>
      </div>

      <!-- Section 4: Point Marker (Untuk Titik) -->
      <div
        v-if="localState.geom_type === 'point' || localState.geom_type === 'auto'"
        class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
      >
        <div class="text-xs font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
          <UIcon name="i-lucide-map-pin" class="size-4 text-sky-500" />
          <span>Pengaturan Simbol Titik (Circle Marker)</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Radius Titik (px)</label>
            <UInput
              v-model.number="localState.point_radius"
              type="number"
              min="2"
              max="20"
              class="w-full font-mono text-xs"
            />
          </div>

          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Warna Titik</label>
            <div class="flex items-center gap-2">
              <input
                v-model="localState.point_color"
                type="color"
                class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent"
              />
              <UInput
                v-model="localState.point_color"
                placeholder="#0284c7"
                class="w-28 font-mono text-xs"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Border Titik</label>
            <div class="flex items-center gap-2">
              <input
                v-model="localState.point_border_color"
                type="color"
                class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent"
              />
              <UInput
                v-model="localState.point_border_color"
                placeholder="#ffffff"
                class="w-28 font-mono text-xs"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Section 5: Aturan Kondisional (Rules) -->
      <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <UIcon name="i-lucide-git-branch" class="size-4 text-emerald-500" />
            <div>
              <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                Aturan Warna Berdasarkan Nilai Atribut (Conditional Styling)
              </span>
              <span class="text-[11px] text-gray-500 dark:text-gray-400">
                Contoh: Warna merah untuk jalan rusak berat, hijau untuk kondisi baik.
              </span>
            </div>
          </div>

          <UButton
            size="xs"
            color="primary"
            variant="soft"
            icon="i-lucide-plus"
            label="Tambah Aturan"
            class="cursor-pointer"
            @click="addRule"
          />
        </div>

        <div v-if="!localState.rules || localState.rules.length === 0" class="py-4 text-center text-xs text-gray-400 border border-dashed border-gray-200 dark:border-white/[0.08] rounded-lg">
          Belum ada aturan kondisional. Seluruh fitur menggunakan warna dasar di atas.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(rule, idx) in localState.rules"
            :key="rule.id || idx"
            class="p-2.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02] grid grid-cols-1 sm:grid-cols-12 gap-2 items-center"
          >
            <!-- Atribut Field -->
            <div class="sm:col-span-3">
              <label class="block text-[10px] text-gray-500 uppercase tracking-wider mb-0.5">Atribut Field</label>
              <UInput
                v-model="rule.field"
                list="rule-attributes-list"
                placeholder="kondisi / status"
                class="w-full font-mono text-xs"
              />
              <datalist id="rule-attributes-list">
                <option v-for="a in layerAttributes" :key="a.name" :value="a.name">{{ a.name }} ({{ a.type }})</option>
              </datalist>
            </div>

            <!-- Operator -->
            <div class="sm:col-span-3">
              <label class="block text-[10px] text-gray-500 uppercase tracking-wider mb-0.5">Operator</label>
              <USelect
                v-model="rule.operator"
                :items="operatorOptions"
                :portal="false"
                class="w-full"
              />
            </div>

            <!-- Nilai Pencocokan -->
            <div class="sm:col-span-3">
              <label class="block text-[10px] text-gray-500 uppercase tracking-wider mb-0.5">Nilai Target</label>
              <UInput
                v-model="rule.value"
                placeholder="rusak berat / baik"
                class="w-full text-xs"
              />
            </div>

            <!-- Warna Hasil -->
            <div class="sm:col-span-2">
              <label class="block text-[10px] text-gray-500 uppercase tracking-wider mb-0.5">Warna</label>
              <div class="flex items-center gap-1.5">
                <input
                  v-model="rule.color"
                  type="color"
                  class="size-7 rounded cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent"
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
                title="Hapus Aturan"
                @click="removeRule(idx)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Section 6: Label Peta -->
      <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <UIcon name="i-lucide-type" class="size-4 text-amber-500" />
            <div>
              <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                Label Teks Fitur Peta
              </span>
              <span class="text-[11px] text-gray-500 dark:text-gray-400">
                Tampilkan teks label nama jalan, nama desa, atau ID ruas saat diperbesar.
              </span>
            </div>
          </div>

          <USwitch v-if="localState.label" v-model="localState.label.enabled" />
        </div>

        <div v-if="localState.label?.enabled" class="space-y-3 pt-2 border-t border-gray-100 dark:border-white/[0.05]">
          <!-- Baris 1: Kolom Atribut, Ukuran Font, dan Minimal Zoom -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                  <UIcon name="i-lucide-database" class="size-3.5 text-blue-500" />
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

              <!-- Pilihan Dropdown Dinamis -->
              <div v-if="!isCustomFieldMode">
                <USelect
                  v-model="localState.label.field"
                  :items="attributeOptions"
                  :portal="false"
                  :loading="isLoadingAttributes"
                  placeholder="Pilih kolom label..."
                  class="w-full font-mono text-xs"
                >
                  <template #item-leading="{ item }">
                    <UIcon name="i-lucide-tag" class="size-3.5 text-blue-500 shrink-0" />
                  </template>
                  <template #item-trailing="{ item }">
                    <span v-if="getItemSampleValue(item)" class="text-[10px] text-gray-400 truncate max-w-[120px]">
                      "{{ getItemSampleValue(item) }}"
                    </span>
                  </template>
                </USelect>
              </div>

              <!-- Input Manual -->
              <div v-else>
                <UInput
                  v-model="localState.label.field"
                  placeholder="namobj / nama_desa / nama_ruas"
                  class="w-full font-mono text-xs"
                />
              </div>

              <div class="flex items-center justify-between mt-1 text-[10px] text-gray-400">
                <span>{{ layerAttributes.length > 0 ? `${layerAttributes.length} kolom terdeteksi` : (isLoadingAttributes ? 'Memuat kolom...' : 'Kolom bawaan') }}</span>
                <button
                  v-if="props.layerId"
                  type="button"
                  class="hover:text-blue-500 flex items-center gap-1 cursor-pointer transition-colors"
                  title="Segarkan daftar kolom dari database"
                  @click="fetchAttributes(props.layerId)"
                >
                  <UIcon name="i-lucide-refresh-cw" class="size-3" :class="isLoadingAttributes ? 'animate-spin text-blue-500' : ''" />
                  <span>Segarkan</span>
                </button>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                <span>Ukuran Font</span>
                <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ localState.label.font_size ?? 11 }} px</span>
              </div>
              <input
                v-model.number="localState.label.font_size"
                type="range"
                min="9"
                max="20"
                step="1"
                class="w-full accent-blue-600 cursor-pointer"
              />
            </div>

            <div>
              <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                <span>Minimal Zoom Tampil Label</span>
                <span class="font-mono font-semibold text-gray-900 dark:text-white">Zoom {{ localState.label.min_zoom }}</span>
              </div>
              <input
                v-model.number="localState.label.min_zoom"
                type="range"
                min="8"
                max="18"
                step="0.5"
                class="w-full accent-blue-600 cursor-pointer"
              />
            </div>
          </div>



          <!-- Baris 2: Mode Peletakan & Orientasi Posisi (Horizontal / Vertikal) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-lg bg-gray-50/70 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05]">
            <!-- Metode Peletakan (Placement) -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                Metode Peletakan (Placement)
              </label>
              <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                <button
                  type="button"
                  class="py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer flex items-center justify-center gap-1.5"
                  :class="localState.label.placement === 'point'
                    ? 'bg-blue-600 text-white font-semibold shadow-xs'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50'"
                  @click="localState.label.placement = 'point'"
                >
                  <UIcon name="i-lucide-map-pin" class="size-3.5" />
                  <span>Centroid (Tengah)</span>
                </button>

                <button
                  type="button"
                  class="py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer flex items-center justify-center gap-1.5"
                  :class="localState.label.placement === 'line'
                    ? 'bg-blue-600 text-white font-semibold shadow-xs'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50'"
                  @click="localState.label.placement = 'line'"
                >
                  <UIcon name="i-lucide-spline" class="size-3.5" />
                  <span>Kontur Garis</span>
                </button>
              </div>
              <span class="text-[10px] text-gray-400 mt-1 block">
                {{ localState.label.placement === 'point' ? 'Label diposisikan di titik tengah/centroid area poligon.' : 'Label menelusuri kontur keliling batas luar poligon.' }}
              </span>
            </div>

            <!-- Orientasi Posisi Label -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 flex items-center justify-between">
                <span>Orientasi Posisi Label</span>
                <span v-if="localState.label.placement === 'line'" class="text-[10px] text-amber-500 font-normal">
                  (Otomatis ikuti garis)
                </span>
              </label>
              <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-200/60 dark:bg-gray-800/80 rounded-lg">
                <button
                  type="button"
                  :disabled="localState.label.placement === 'line'"
                  class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1.5"
                  :class="[
                    localState.label.placement === 'line'
                      ? 'opacity-40 cursor-not-allowed text-gray-400'
                      : localState.label.orientation !== 'vertical'
                      ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                  ]"
                  @click="localState.label.orientation = 'horizontal'"
                >
                  <UIcon name="i-lucide-arrow-right" class="size-3.5" />
                  <span>Horizontal (0°)</span>
                </button>

                <button
                  type="button"
                  :disabled="localState.label.placement === 'line'"
                  class="py-1.5 px-2 rounded-md text-xs font-medium transition-all flex items-center justify-center gap-1.5"
                  :class="[
                    localState.label.placement === 'line'
                      ? 'opacity-40 cursor-not-allowed text-gray-400'
                      : localState.label.orientation === 'vertical'
                      ? 'bg-blue-600 text-white font-semibold shadow-xs cursor-pointer'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50 cursor-pointer'
                  ]"
                  @click="localState.label.orientation = 'vertical'"
                >
                  <UIcon name="i-lucide-arrow-down" class="size-3.5" />
                  <span>Vertikal (90°)</span>
                </button>
              </div>
              <span class="text-[10px] text-gray-400 mt-1 block">
                {{ localState.label.placement === 'line' ? 'Pada mode kontur, teks otomatis mengikuti sudut kemiringan garis batas.' : (localState.label.orientation === 'vertical' ? 'Teks diputar tegak 90° (cocok untuk wilayah memanjang Utara-Selatan).' : 'Teks mendatar horizontal 0° standar.') }}
              </span>
            </div>
          </div>

          <!-- Baris 3: Warna Teks, Outline (Halo), Geser Posisi & Luapan -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Warna Teks & Halo Outline</label>
              <div class="flex items-center gap-2">
                <input
                  v-model="localState.label.color"
                  type="color"
                  class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent"
                  title="Warna Teks"
                />
                <input
                  v-model="localState.label.halo_color"
                  type="color"
                  class="size-8 rounded-lg cursor-pointer border border-gray-200 dark:border-white/10 p-0.5 bg-transparent"
                  title="Warna Halo Outline"
                />
                <span class="text-[11px] font-mono text-gray-400">Outline: {{ localState.label.halo_width ?? 3 }}px</span>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                <span>Pergeseran Vertikal (Offset Y)</span>
                <span class="font-mono text-gray-900 dark:text-white">{{ localState.label.offset_y ?? 0 }} px</span>
              </div>
              <input
                v-model.number="localState.label.offset_y"
                type="range"
                min="-40"
                max="40"
                step="2"
                class="w-full accent-blue-600 cursor-pointer"
              />
            </div>

            <div class="flex flex-col justify-between">
              <div>
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                  Luapan Batas (Overflow)
                </span>
                <span class="text-[10px] text-gray-400">
                  Tetap tampil walau teks lebih besar dari poligon
                </span>
              </div>
              <USwitch v-model="localState.label.overflow" class="mt-1" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


<script lang="ts" setup>
import type { LayerStyleConfig } from "~/types/map-layers";
import LayerStyleEditor from "~/components/admin/layers/LayerStyleEditor.vue";
import WmsSldLivePreview from "~/components/admin/layers/WmsSldLivePreview.vue";
import MartinSymbolSwatch from "~/components/maps/left-panel/MartinSymbolSwatch.vue";
import {
  type LayerItem,
  getProtocolBadgeColor,
  getLayerSourceGroup,
} from "~/utils/layer-meta";
import {
  buildMartinLayerLegend,
  getLegendGeomLabel,
  resolveEffectiveWmsLegendUrl,
} from "~/utils/mvt-legend";
import { hasWmsCustomStyle } from "~/utils/wms-sld";

definePageMeta({
  middleware: ["auth", "permission"],
  permission: "layers-view",
});

type DetailTab = "edit" | "style" | "metadata";

interface LayerShowResponse {
  ok: boolean;
  data: LayerItem;
}

interface AttributeItem {
  name: string;
  type: string;
  sample_value: string | null;
}

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { can } = usePermission();

const layerId = computed(() => String(route.params.id || ""));
const canEdit = computed(() => can("layers-update") || can("layers-manage"));

const validTabs: DetailTab[] = ["edit", "style", "metadata"];
function parseInitialTab(raw: unknown): DetailTab {
  const s = String(raw || "").toLowerCase();
  return (validTabs.includes(s as DetailTab) ? s : "edit") as DetailTab;
}

const activeTab = ref<DetailTab>(parseInitialTab(route.query.tab));

watch(
  () => route.query.tab,
  (val) => {
    const parsed = parseInitialTab(val);
    if (parsed !== activeTab.value) {
      activeTab.value = parsed;
    }
  }
);

function selectTab(tab: DetailTab) {
  activeTab.value = tab;
  router.replace({
    query: {
      ...route.query,
      tab,
    },
  });
}

function handleTabKeydown(event: KeyboardEvent, index: number) {
  if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") return;
  event.preventDefault();
  const nextIndex =
    event.key === "ArrowRight"
      ? (index + 1) % tabItems.length
      : (index - 1 + tabItems.length) % tabItems.length;
  const nextTab = tabItems[nextIndex]!;
  selectTab(nextTab.key);
  nextTick(() => {
    document.getElementById(`layer-tab-${nextTab.key}`)?.focus();
  });
}

const {
  data: layerResponse,
  status: layerStatus,
  error: layerError,
  refresh: refreshLayer,
} = useHttp<LayerShowResponse>(() => `admin/layers/${layerId.value}`, {
  watch: [layerId],
});

const layer = computed<LayerItem | null>(() => layerResponse.value?.data ?? null);
const isLoading = computed(() => layerStatus.value === "pending");

useSeoMeta({
  title: () => (layer.value ? `${layer.value.name} | Manajemen Layer` : "Detail Layer | Melarosa GIS"),
});

// Form state untuk Edit Layer & Custom Style
const formState = reactive({
  name: "",
  category: "",
  opd_name: "",
  protocol: "wms",
  source_type: "geoportal",
  url: "",
  layer_name: "",
  legend_url: "",
  opacity: 1.0,
  order: 0,
  attribution: "",
  description: "",
  is_active: true,
  default_visible: false,
  style_config: null as LayerStyleConfig | null,
});

const initialSnapshot = ref("");
const formErrors = reactive<Record<string, string>>({});
const saving = ref(false);

function hydrateFormFromLayer(item: LayerItem) {
  formState.name = item.name || "";
  formState.category = item.category || "";
  formState.opd_name = item.opd_name || "";
  formState.protocol = (item.protocol || "wms").toLowerCase();
  formState.source_type = item.source_type || "geoportal";
  formState.url = item.url || "";
  formState.layer_name = item.layer_name || "";
  formState.legend_url = item.legend_url || "";
  formState.opacity = typeof item.opacity === "number" ? item.opacity : 1.0;
  formState.order = typeof item.order === "number" ? item.order : 0;
  formState.attribution = item.attribution || "";
  formState.description = item.description || "";
  formState.is_active = Boolean(item.is_active);
  formState.default_visible = Boolean(item.default_visible);
  formState.style_config = item.style_config
    ? JSON.parse(JSON.stringify(item.style_config))
    : null;

  initialSnapshot.value = JSON.stringify(toRawSnapshot());
}

function toRawSnapshot() {
  return {
    name: formState.name,
    category: formState.category,
    opd_name: formState.opd_name,
    protocol: formState.protocol,
    source_type: formState.source_type,
    url: formState.url,
    layer_name: formState.layer_name,
    legend_url: formState.legend_url,
    opacity: formState.opacity,
    order: formState.order,
    attribution: formState.attribution,
    description: formState.description,
    is_active: formState.is_active,
    default_visible: formState.default_visible,
    style_config: formState.style_config,
  };
}

watch(
  layer,
  (val) => {
    if (val) {
      hydrateFormFromLayer(val);
    }
  },
  { immediate: true }
);

const isDirty = computed(() => {
  if (!layer.value || !initialSnapshot.value) return false;
  return JSON.stringify(toRawSnapshot()) !== initialSnapshot.value;
});

function handleDiscardChanges() {
  if (!layer.value) return;
  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  hydrateFormFromLayer(layer.value);
}

const formProtocolOptions = [
  { label: "WMS (Web Map Service)", value: "wms" },
  { label: "WFS (Web Feature Service)", value: "wfs" },
  { label: "XYZ Tiles", value: "xyz" },
  { label: "Vector Tiles (MVT)", value: "mvt" },
  { label: "GeoJSON", value: "geojson" },
];

const formSourceTypeOptions = [
  { label: "Geoportal Bojonegoro (WMS GeoServer Palapa)", value: "geoportal" },
  { label: "Internal Martin Server (Vector Tiles MVT)", value: "internal" },
  { label: "External WMS Service (Luar Geoportal)", value: "external" },
  { label: "GeoServer Palapa (Spesifik)", value: "geoserver" },
  { label: "XYZ Tiles Provider", value: "xyz_tiles" },
  { label: "Local GeoJSON Vector", value: "local_geojson" },
  { label: "Basemap Provider", value: "basemap" },
];

const isWmsLayer = computed(() => formState.protocol.toLowerCase().includes("wms"));
const isMvtLayer = computed(() => formState.protocol.toLowerCase() === "mvt");
const isStyleSupported = computed(() => isWmsLayer.value || isMvtLayer.value);
const isWmsCustomActive = computed(() => isWmsLayer.value && hasWmsCustomStyle(formState.style_config));

const layerMinZoom = computed<number>({
  get() {
    const val = Number(formState.style_config?.min_zoom);
    return Number.isFinite(val) && val > 0 ? val : 0;
  },
  set(val: number) {
    const num = Number(val);
    const normalized = Number.isFinite(num) && num > 0 ? Math.min(18, Math.max(0, num)) : null;
    if (!formState.style_config) {
      formState.style_config = normalized !== null ? { min_zoom: normalized } : null;
    } else {
      formState.style_config = {
        ...formState.style_config,
        min_zoom: normalized,
      };
    }
  },
});

const tabItems = computed(() => [
  {
    key: "edit" as DetailTab,
    label: "Edit Layer",
    icon: "i-lucide-sliders-horizontal",
  },
  {
    key: "style" as DetailTab,
    label: isWmsLayer.value ? "Custom Style (SLD)" : "Custom Style",
    icon: "i-lucide-palette",
  },
  {
    key: "metadata" as DetailTab,
    label: "Rincian Metadata",
    icon: "i-lucide-file-text",
  },
]);

const sourceGroup = computed(() => {
  if (!layer.value) return null;
  return getLayerSourceGroup({
    ...layer.value,
    protocol: formState.protocol,
    source_type: formState.source_type,
    url: formState.url,
  });
});

// Preview legend items untuk panel samping di tab Style
const stylePreviewLegend = computed(() => {
  if (!layer.value) return null;
  return buildMartinLayerLegend({
    id: layer.value.id,
    title: formState.name || layer.value.name,
    name: formState.name || layer.value.name,
    layer_name: formState.layer_name || layer.value.layer_name,
    protocol: formState.protocol.toUpperCase(),
    url: formState.url,
    visible: true,
    opacity: formState.opacity,
    expanded: true,
    activeTool: "none",
    style_config: formState.style_config,
  });
});

const effectiveMetaLegendUrl = computed(() => {
  if (!layer.value) return null;
  if (!isWmsLayer.value) {
    return formState.legend_url || layer.value.legend_url || null;
  }
  return resolveEffectiveWmsLegendUrl({
    protocol: formState.protocol,
    source_type: formState.source_type,
    url: formState.url,
    layer_name: formState.layer_name,
    legend_url: formState.legend_url || layer.value.legend_url,
    style_config: formState.style_config,
  });
});

const metaLegendImgError = ref(false);
watch(
  [effectiveMetaLegendUrl, () => layer.value?.legend_url],
  () => {
    metaLegendImgError.value = false;
  }
);

const hasValidMetaLegendImg = computed(() =>
  Boolean((effectiveMetaLegendUrl.value || layer.value?.legend_url) && !metaLegendImgError.value)
);

const showMvtMetadataLegend = computed(() =>
  isMvtLayer.value && Boolean(stylePreviewLegend.value?.items?.length)
);

// Fetch atribut kolom layer untuk Tab Rincian Metadata
const attributesList = ref<AttributeItem[]>([]);
const attributesLoading = ref(false);
const attributesError = ref<string | null>(null);
const attributesSourceTable = ref<string | null>(null);
const hasFetchedAttributes = ref(false);

async function fetchLayerAttributes() {
  if (!layerId.value) return;
  attributesLoading.value = true;
  attributesError.value = null;
  try {
    const res = await $http<{
      ok: boolean;
      data: AttributeItem[];
      source_table?: string | null;
    }>(`admin/layers/${layerId.value}/attributes`);
    attributesList.value = Array.isArray(res?.data) ? res.data : [];
    attributesSourceTable.value = res?.source_table || null;
    hasFetchedAttributes.value = true;
  } catch (err: any) {
    attributesError.value = err?.data?.message || "Gagal memuat skema atribut dari server.";
  } finally {
    attributesLoading.value = false;
  }
}

watch(
  activeTab,
  (tab) => {
    if (tab === "metadata" && !hasFetchedAttributes.value && !attributesLoading.value) {
      fetchLayerAttributes();
    }
  },
  { immediate: true }
);

const showRawCswJson = ref(false);
const copiedField = ref<string | null>(null);

async function copyValue(val: string, fieldKey: string) {
  if (!val) return;
  try {
    await navigator.clipboard.writeText(val);
    copiedField.value = fieldKey;
    setTimeout(() => {
      if (copiedField.value === fieldKey) copiedField.value = null;
    }, 1800);
  } catch {
    toast.add({
      title: "Gagal menyalin",
      description: "Browser memblokir akses clipboard.",
      color: "warning",
    });
  }
}

async function handleSave() {
  if (!canEdit.value || !layerId.value) return;
  Object.keys(formErrors).forEach((k) => delete formErrors[k]);

  if (!formState.name.trim()) formErrors.name = "Nama layer wajib diisi.";
  if (!formState.protocol.trim()) formErrors.protocol = "Protokol wajib dipilih.";
  if (!formState.url.trim()) formErrors.url = "URL service / endpoint wajib diisi.";
  if (["wms", "wfs"].includes(formState.protocol) && !formState.layer_name.trim()) {
    formErrors.layer_name = "Nama layer teknis GeoServer wajib diisi untuk protokol WMS/WFS.";
  }

  if (Object.keys(formErrors).length > 0) {
    selectTab("edit");
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Validasi Gagal",
      description: "Periksa kembali kolom wajib pada tab Edit Layer.",
      color: "warning",
    });
    return;
  }

  saving.value = true;
  try {
    const payload: Record<string, any> = {
      name: formState.name.trim(),
      category: formState.category.trim() || null,
      opd_name: formState.opd_name.trim() || null,
      protocol: formState.protocol,
      source_type: formState.source_type,
      url: formState.url.trim(),
      layer_name: formState.layer_name.trim() || null,
      legend_url: formState.legend_url.trim() || null,
      opacity: Number(formState.opacity),
      order: Number(formState.order),
      attribution: formState.attribution.trim() || null,
      description: formState.description.trim() || null,
      is_active: formState.is_active,
      default_visible: formState.default_visible,
      style_config: formState.style_config || null,
    };

    const res = await $http<{ ok: boolean; message: string; data: LayerItem }>(
      `admin/layers/${layerId.value}`,
      {
        method: "PUT",
        body: payload,
      }
    );

    if (res?.data) {
      hydrateFormFromLayer(res.data);
    }
    await refreshLayer();

    toast.add({
      icon: "i-lucide-check-circle-2",
      title: "Perubahan Disimpan",
      description: res?.message || "Konfigurasi layer dan style berhasil diperbarui.",
      color: "success",
    });
  } catch (err: any) {
    const serverErrors = err?.data?.errors;
    if (serverErrors && typeof serverErrors === "object") {
      Object.entries(serverErrors).forEach(([k, v]) => {
        formErrors[k] = Array.isArray(v) ? String(v[0]) : String(v);
      });
    }
    toast.add({
      icon: "i-lucide-x-circle",
      title: "Gagal Menyimpan",
      description: err?.data?.message || "Terjadi kesalahan saat menyimpan konfigurasi layer.",
      color: "error",
    });
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Top Back Link -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <NuxtLink
        to="/admin/layers"
        class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 min-h-[44px] sm:min-h-0 py-1.5"
      >
        <UIcon name="i-lucide-arrow-left" class="size-4 shrink-0" aria-hidden="true" />
        <span>Kembali ke Katalog Layer</span>
      </NuxtLink>

      <div v-if="layer" class="flex flex-wrap items-center gap-2">
        <NuxtLink
          :to="`/maps?dataset=${layer.id}`"
          target="_blank"
          class="inline-flex items-center gap-1.5 px-3 min-h-[40px] rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-[#0c1424] text-xs font-semibold text-gray-700 dark:text-gray-200 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
        >
          <UIcon name="i-lucide-map" class="size-3.5 shrink-0" aria-hidden="true" />
          <span>Lihat di Peta</span>
        </NuxtLink>
      </div>
    </div>

    <!-- 1. LOADING STATE -->
    <div v-if="isLoading" class="space-y-4">
      <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-5 space-y-3 animate-pulse">
        <div class="h-5 w-48 rounded bg-gray-200 dark:bg-white/10" />
        <div class="h-4 w-80 max-w-full rounded bg-gray-100 dark:bg-white/[0.06]" />
      </div>
      <div class="h-11 w-full max-w-md rounded-xl bg-gray-200/70 dark:bg-white/[0.06] animate-pulse" />
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 h-72 rounded-xl bg-white dark:bg-[#0c1424] border border-gray-200 dark:border-white/[0.08] animate-pulse" />
        <div class="lg:col-span-5 h-72 rounded-xl bg-white dark:bg-[#0c1424] border border-gray-200 dark:border-white/[0.08] animate-pulse" />
      </div>
    </div>

    <!-- 2. ERROR STATE -->
    <div
      v-else-if="layerError"
      class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/70 dark:bg-red-950/20 p-6 sm:p-8 text-center space-y-3"
    >
      <div class="size-11 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-alert-triangle" class="size-5" aria-hidden="true" />
      </div>
      <h1 class="text-base font-bold text-gray-900 dark:text-white">
        Gagal Memuat Konfigurasi Layer
      </h1>
      <p class="text-xs text-gray-700 dark:text-gray-300 max-w-md mx-auto">
        {{ (layerError as any)?.data?.message || "Terjadi kendala saat mengambil data layer dari server." }}
      </p>
      <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
        <UButton
          label="Coba Lagi"
          icon="i-lucide-refresh-cw"
          color="primary"
          size="sm"
          class="cursor-pointer min-h-[40px]"
          @click="() => refreshLayer()"
        />
        <NuxtLink
          to="/admin/layers"
          class="inline-flex items-center px-3.5 min-h-[40px] rounded-lg border border-gray-300 dark:border-white/15 bg-white dark:bg-[#0c1424] text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.04]"
        >
          Kembali ke Katalog
        </NuxtLink>
      </div>
    </div>

    <!-- 3. EMPTY / NOT FOUND STATE -->
    <div
      v-else-if="!layer"
      class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-8 text-center space-y-3"
    >
      <div class="size-11 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-layers" class="size-5" aria-hidden="true" />
      </div>
      <h1 class="text-base font-bold text-gray-900 dark:text-white">
        Layer Tidak Ditemukan
      </h1>
      <p class="text-xs text-gray-600 dark:text-gray-300 max-w-md mx-auto">
        Data layer dengan ID tersebut tidak tersedia atau telah dihapus dari katalog.
      </p>
      <div class="pt-2">
        <NuxtLink
          to="/admin/layers"
          class="inline-flex items-center gap-1.5 px-4 min-h-[44px] rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700"
        >
          Kembali ke Katalog Layer
        </NuxtLink>
      </div>
    </div>

    <!-- 4. MAIN WORKSPACE -->
    <template v-else>
      <!-- Header Summary Card -->
      <header class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-1.5 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <UBadge
                v-if="sourceGroup"
                :label="sourceGroup.badgeLabel"
                :color="sourceGroup.badgeColor"
                variant="subtle"
                size="xs"
              />
              <UBadge
                :label="formState.protocol.toUpperCase()"
                :color="getProtocolBadgeColor(formState.protocol)"
                variant="outline"
                size="xs"
                class="font-mono"
              />
              <UBadge
                :label="formState.is_active ? 'Aktif' : 'Nonaktif'"
                :color="formState.is_active ? 'success' : 'neutral'"
                variant="subtle"
                size="xs"
              />
              <UBadge
                v-if="isWmsCustomActive"
                label="Custom SLD Aktif"
                color="primary"
                variant="subtle"
                size="xs"
              />
              <UBadge
                v-else-if="isMvtLayer && formState.style_config"
                label="MVT Styled"
                color="primary"
                variant="subtle"
                size="xs"
              />
            </div>

            <h1 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white tracking-tight break-words">
              {{ formState.name || layer.name }}
            </h1>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
              <span v-if="formState.layer_name" class="font-mono text-[11px] break-all">
                {{ formState.layer_name }}
              </span>
              <span v-if="formState.opd_name || formState.attribution">
                Wali Data: {{ formState.opd_name || formState.attribution }}
              </span>
              <span class="tabular-nums">
                Urutan #{{ formState.order }} · Opacity {{ Math.round(formState.opacity * 100) }}%
              </span>
            </div>
          </div>

          <div v-if="canEdit" class="flex flex-wrap items-center gap-2.5 shrink-0">
            <UButton
              v-if="isDirty"
              label="Batalkan"
              color="neutral"
              variant="outline"
              size="sm"
              class="cursor-pointer min-h-[44px] sm:min-h-[38px]"
              :disabled="saving"
              @click="handleDiscardChanges"
            />
            <UButton
              :label="saving ? 'Menyimpan...' : 'Simpan Perubahan'"
              icon="i-lucide-save"
              color="primary"
              size="sm"
              class="cursor-pointer min-h-[44px] sm:min-h-[38px]"
              :loading="saving"
              :disabled="!isDirty && !saving"
              @click="handleSave"
            />
          </div>
        </div>

        <div
          v-if="isDirty"
          class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.06] flex items-center gap-2 text-xs font-medium text-amber-700 dark:text-amber-300"
        >
          <UIcon name="i-lucide-circle-dot" class="size-3.5 shrink-0" aria-hidden="true" />
          <span>Ada perubahan yang belum disimpan. Tekan "Simpan Perubahan" untuk menerapkan ke peta.</span>
        </div>
      </header>

      <!-- Tab Menu Navigation -->
      <nav aria-label="Menu konfigurasi layer">
        <div
          role="tablist"
          aria-label="Tab konfigurasi layer"
          class="flex flex-wrap sm:inline-flex items-center gap-1 p-1 rounded-xl bg-gray-100 dark:bg-[#0c1424] border border-gray-200 dark:border-white/[0.08] w-full sm:w-auto"
        >
          <button
            v-for="(tab, idx) in tabItems"
            :id="`layer-tab-${tab.key}`"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.key"
            :aria-controls="`layer-panel-${tab.key}`"
            :tabindex="activeTab === tab.key ? 0 : -1"
            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-3.5 py-2 min-h-[44px] sm:min-h-[38px] rounded-lg text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            :class="
              activeTab === tab.key
                ? 'bg-white dark:bg-blue-600 text-blue-700 dark:text-white shadow-xs'
                : 'text-gray-700 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-white/[0.06]'
            "
            @click="selectTab(tab.key)"
            @keydown="handleTabKeydown($event, idx)"
          >
            <UIcon :name="tab.icon" class="size-4 shrink-0" aria-hidden="true" />
            <span>{{ tab.label }}</span>
          </button>
        </div>
      </nav>

      <!-- TAB 1: EDIT LAYER -->
      <section
        v-show="activeTab === 'edit'"
        id="layer-panel-edit"
        role="tabpanel"
        aria-labelledby="layer-tab-edit"
        class="grid grid-cols-1 lg:grid-cols-12 gap-5"
      >
        <!-- Identitas & Teknis Service -->
        <div class="lg:col-span-7 space-y-5">
          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-4">
            <div class="border-b border-gray-100 dark:border-white/[0.06] pb-3">
              <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                Identitas &amp; Wali Data Layer
              </h2>
              <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                Informasi utama yang tampil pada katalog dataset, peta publik, dan daftar layer aktif.
              </p>
            </div>

            <UFormField label="Nama Tampilan Layer" required :error="formErrors.name">
              <UInput
                v-model="formState.name"
                :disabled="!canEdit"
                placeholder="Contoh: Batas Administrasi Desa Bojonegoro"
                class="w-full"
              />
            </UFormField>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <UFormField label="Kategori / Urusan">
                <UInput
                  v-model="formState.category"
                  :disabled="!canEdit"
                  placeholder="Contoh: Batas Wilayah / Tata Ruang"
                  class="w-full"
                />
              </UFormField>

              <UFormField label="OPD / Wali Data">
                <UInput
                  v-model="formState.opd_name"
                  :disabled="!canEdit"
                  placeholder="Contoh: Bappeda Kab. Bojonegoro"
                  class="w-full"
                />
              </UFormField>
            </div>

            <UFormField label="Atribusi Sumber Data">
              <UInput
                v-model="formState.attribution"
                :disabled="!canEdit"
                placeholder="Contoh: Pemerintah Kabupaten Bojonegoro"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Deskripsi / Abstrak Layer">
              <UTextarea
                v-model="formState.description"
                :disabled="!canEdit"
                :rows="4"
                placeholder="Penjelasan ringkas mengenai cakupan data, tahun pemutakhiran, dan kegunaan layer."
                class="w-full"
              />
            </UFormField>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-4">
            <div class="border-b border-gray-100 dark:border-white/[0.06] pb-3">
              <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                Endpoint &amp; Protokol Geospasial
              </h2>
              <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                Konfigurasi koneksi layanan OGC WMS/WFS atau tile server internal.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <UFormField label="Protokol Akses" required :error="formErrors.protocol">
                <USelect
                  v-model="formState.protocol"
                  :items="formProtocolOptions"
                  :disabled="!canEdit"
                  class="w-full"
                />
              </UFormField>

              <UFormField label="Kelompok Sumber" required>
                <USelect
                  v-model="formState.source_type"
                  :items="formSourceTypeOptions"
                  :disabled="!canEdit"
                  class="w-full"
                />
              </UFormField>
            </div>

            <UFormField label="Endpoint URL Layanan" required :error="formErrors.url">
              <UInput
                v-model="formState.url"
                :disabled="!canEdit"
                placeholder="https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms"
                class="w-full font-mono text-xs"
              />
            </UFormField>

            <UFormField
              label="Nama Layer Teknis"
              :required="['wms', 'wfs'].includes(formState.protocol)"
              :error="formErrors.layer_name"
              description="Contoh WMS GeoServer: palapa:ADMINISTRASIDESA_AR_10K_2019_BOJONEGORO"
            >
              <UInput
                v-model="formState.layer_name"
                :disabled="!canEdit"
                placeholder="palapa:NAMA_LAYER"
                class="w-full font-mono text-xs"
              />
            </UFormField>

            <UFormField
              label="URL Legenda Bawaan (GetLegendGraphic)"
              description="Opsional. Jika Custom SLD aktif, legenda peta otomatis mengikuti SLD kustom."
            >
              <UInput
                v-model="formState.legend_url"
                :disabled="!canEdit"
                placeholder="https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms?..."
                class="w-full font-mono text-xs"
              />
            </UFormField>
          </div>
        </div>

        <!-- Perilaku Peta & Visibilitas -->
        <div class="lg:col-span-5 space-y-5">
          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-4">
            <div class="border-b border-gray-100 dark:border-white/[0.06] pb-3">
              <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                Visibilitas &amp; Tumpukan Peta
              </h2>
              <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                Pengaturan tampilan awal layer ketika pengguna membuka halaman peta.
              </p>
            </div>

            <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-white/[0.02]">
              <div>
                <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                  Layer Aktif di Sistem
                </span>
                <span class="text-[11px] text-gray-600 dark:text-gray-300">
                  Jika nonaktif, layer disembunyikan dari katalog peta publik.
                </span>
              </div>
              <USwitch v-model="formState.is_active" :disabled="!canEdit" aria-label="Layer aktif di sistem" />
            </div>

            <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-white/[0.02]">
              <div>
                <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                  Tampil Otomatis Saat Peta Dibuka
                </span>
                <span class="text-[11px] text-gray-600 dark:text-gray-300">
                  Layer langsung aktif tercentang saat pengunjung membuka /maps.
                </span>
              </div>
              <USwitch v-model="formState.default_visible" :disabled="!canEdit" aria-label="Tampil otomatis saat peta dibuka" />
            </div>

            <div class="space-y-2 pt-1">
              <div class="flex items-center justify-between text-xs">
                <label for="layer-opacity-range" class="font-semibold text-gray-800 dark:text-gray-200">
                  Tingkat Opacity (Transparansi)
                </label>
                <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                  {{ Math.round(formState.opacity * 100) }}%
                </span>
              </div>
              <input
                id="layer-opacity-range"
                v-model.number="formState.opacity"
                :disabled="!canEdit"
                type="range"
                min="0"
                max="1"
                step="0.05"
                class="w-full accent-blue-600 cursor-pointer"
              />
            </div>

            <UFormField
              label="Urutan Tumpukan (Order)"
              description="Nilai lebih besar tampil di lapisan atas (z-index)."
            >
              <UInput
                v-model.number="formState.order"
                :disabled="!canEdit"
                type="number"
                min="0"
                class="w-full tabular-nums"
              />
            </UFormField>

            <div class="space-y-2 pt-1 border-t border-gray-100 dark:border-white/[0.06]">
              <div class="flex items-center justify-between text-xs">
                <label for="layer-min-zoom-range" class="font-semibold text-gray-800 dark:text-gray-200">
                  Minimal Zoom Tampil Layer
                </label>
                <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                  {{ layerMinZoom > 0 ? `Zoom >= ${layerMinZoom}` : 'Semua Zoom' }}
                </span>
              </div>
              <input
                id="layer-min-zoom-range"
                v-model.number="layerMinZoom"
                :disabled="!canEdit"
                type="range"
                min="0"
                max="18"
                step="0.5"
                class="w-full accent-blue-600 cursor-pointer"
              />
            </div>
          </div>

          <!-- Shortcut menuju Tab Style -->
          <div
            v-if="isStyleSupported"
            class="rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50/60 dark:bg-blue-950/20 p-4 space-y-2.5"
          >
            <div class="flex items-start gap-2.5">
              <UIcon name="i-lucide-palette" class="size-4 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" aria-hidden="true" />
              <div class="space-y-1">
                <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                  {{ isWmsLayer ? 'Kustomisasi SLD WMS Tersedia' : 'Kustomisasi Simbologi MVT' }}
                </h3>
                <p class="text-[11px] text-gray-700 dark:text-gray-300">
                  {{
                    isWmsLayer
                      ? 'Ubah warna poligon, garis, titik, label atribut, atau XML SLD tanpa mengubah GeoServer Bojonegoro.'
                      : 'Atur warna, garis batas, aturan kondisi, dan label layer vektor Martin.'
                  }}
                </p>
              </div>
            </div>
            <UButton
              label="Buka Tab Custom Style"
              icon="i-lucide-palette"
              color="primary"
              variant="outline"
              size="xs"
              class="cursor-pointer min-h-[36px]"
              @click="selectTab('style')"
            />
          </div>
        </div>
      </section>

      <!-- TAB 2: CUSTOM STYLE -->
      <section
        v-show="activeTab === 'style'"
        id="layer-panel-style"
        role="tabpanel"
        aria-labelledby="layer-tab-style"
        class="space-y-5"
      >
        <div
          v-if="!isStyleSupported"
          class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-6 text-center space-y-2"
        >
          <p class="text-sm font-semibold text-gray-900 dark:text-white">
            Custom Style Tidak Tersedia untuk Protokol {{ formState.protocol.toUpperCase() }}
          </p>
          <p class="text-xs text-gray-600 dark:text-gray-300 max-w-md mx-auto">
            Editor style mendukung layer berprotokol WMS (Custom SLD GeoServer) dan MVT (Martin Vector Tiles).
          </p>
        </div>

        <div v-else class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
          <div class="xl:col-span-8 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-4">
            <LayerStyleEditor
              v-model="formState.style_config"
              :protocol="formState.protocol"
              :layer-id="layer.id"
              :layer-name="formState.layer_name || formState.name"
              :wms-url="formState.url"
              :legend-url="formState.legend_url || layer.legend_url"
            />

            <!-- Action Footer pada Card Konfigurasi Style -->
            <div
              v-if="canEdit"
              class="pt-4 border-t border-gray-100 dark:border-white/[0.08] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
              <div class="text-xs">
                <span v-if="isDirty" class="inline-flex items-center gap-1.5 font-medium text-amber-700 dark:text-amber-300">
                  <UIcon name="i-lucide-circle-dot" class="size-3.5 shrink-0" aria-hidden="true" />
                  <span>Ada perubahan style belum disimpan.</span>
                </span>
                <span v-else class="text-gray-600 dark:text-gray-300">
                  Konfigurasi style tersinkron dengan pratinjau peta.
                </span>
              </div>

              <div class="flex flex-wrap items-center justify-end gap-2.5 shrink-0">
                <UButton
                  v-if="isDirty"
                  label="Batalkan"
                  color="neutral"
                  variant="outline"
                  size="sm"
                  class="cursor-pointer min-h-[44px] sm:min-h-[38px]"
                  :disabled="saving"
                  @click="handleDiscardChanges"
                />
                <UButton
                  :label="saving ? 'Menyimpan...' : 'Simpan Style'"
                  icon="i-lucide-save"
                  color="primary"
                  size="sm"
                  class="cursor-pointer min-h-[44px] sm:min-h-[38px] font-semibold"
                  :loading="saving"
                  :disabled="!isDirty && !saving"
                  @click="handleSave"
                />
              </div>
            </div>
          </div>

          <div class="xl:col-span-4 space-y-4 xl:sticky xl:top-4">
            <!-- Live Mini Map Preview untuk WMS -->
            <WmsSldLivePreview
              v-if="isWmsLayer && activeTab === 'style'"
              :wms-url="formState.url"
              :layer-name="formState.layer_name"
              :style-config="formState.style_config"
              :opacity="formState.opacity"
            />

            <!-- Ringkasan Item Legenda Terbentuk -->
            <div
              v-if="stylePreviewLegend"
              class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 space-y-3"
            >
              <div class="flex items-center justify-between gap-2 border-b border-gray-100 dark:border-white/[0.06] pb-2.5">
                <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                  Ringkasan Legenda di Peta
                </h3>
                <span class="text-[11px] font-medium text-gray-600 dark:text-gray-300 tabular-nums">
                  {{ stylePreviewLegend.items.length }} Simbol
                </span>
              </div>

              <div class="space-y-2 text-xs">
                <div
                  v-for="item in stylePreviewLegend.items"
                  :key="item.key"
                  class="flex items-center gap-2.5"
                >
                  <MartinSymbolSwatch :swatch="item.swatch" />
                  <span class="text-gray-800 dark:text-gray-200 font-medium capitalize">
                    {{ item.label }}
                  </span>
                  <span v-if="item.field" class="text-[11px] font-mono text-gray-600 dark:text-gray-400">
                    ({{ item.field }})
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 3: RINCIAN METADATA -->
      <section
        v-show="activeTab === 'metadata'"
        id="layer-panel-metadata"
        role="tabpanel"
        aria-labelledby="layer-tab-metadata"
        class="space-y-5"
      >
        <!-- Ringkasan Identitas Katalog -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 space-y-1">
            <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase">Kategori / Urusan</span>
            <p class="text-sm font-bold text-gray-900 dark:text-white">
              {{ layer.category || "Belum Terkategori" }}
            </p>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 space-y-1">
            <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase">Wali Data / OPD</span>
            <p class="text-sm font-bold text-gray-900 dark:text-white">
              {{ layer.opd_name || layer.attribution || "-" }}
            </p>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 space-y-1">
            <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase">ID CSW Geoportal</span>
            <p class="text-xs font-mono font-semibold text-gray-900 dark:text-white break-all">
              {{ layer.geoportal_id || "-" }}
            </p>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 space-y-1">
            <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase">Sinkronisasi Terakhir</span>
            <p class="text-xs font-semibold text-gray-900 dark:text-white tabular-nums">
              {{
                layer.last_synced_at
                  ? new Date(layer.last_synced_at).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" })
                  : "Input Manual / Belum Sync"
              }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <!-- Detail Teknis & Deskripsi -->
          <div class="lg:col-span-6 space-y-5">
            <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-2">
              <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                Abstrak / Deskripsi Dataset
              </h2>
              <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                {{ layer.description || "Tidak ada deskripsi atau abstrak metadata yang tersimpan." }}
              </p>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-3">
              <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                Identitas Teknis Service
              </h2>

              <dl class="divide-y divide-gray-100 dark:divide-white/[0.06] text-xs">
                <div class="py-2.5 flex items-start justify-between gap-3">
                  <dt class="text-gray-600 dark:text-gray-400 shrink-0">Protokol</dt>
                  <dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ layer.protocol.toUpperCase() }}</dd>
                </div>
                <div class="py-2.5 flex items-start justify-between gap-3">
                  <dt class="text-gray-600 dark:text-gray-400 shrink-0">Source Type</dt>
                  <dd class="font-mono text-gray-900 dark:text-white">{{ layer.source_type }}</dd>
                </div>
                <div class="py-2.5 flex items-start justify-between gap-3">
                  <dt class="text-gray-600 dark:text-gray-400 shrink-0">Layer Name</dt>
                  <dd class="font-mono text-gray-900 dark:text-white text-right break-all flex items-center gap-1.5">
                    <span>{{ layer.layer_name || "-" }}</span>
                    <button
                      v-if="layer.layer_name"
                      type="button"
                      aria-label="Salin nama layer teknis"
                      class="p-1 rounded hover:bg-gray-100 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 cursor-pointer"
                      @click="copyValue(layer.layer_name, 'layer_name')"
                    >
                      <UIcon :name="copiedField === 'layer_name' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" aria-hidden="true" />
                    </button>
                  </dd>
                </div>
                <div class="py-2.5 flex items-start justify-between gap-3">
                  <dt class="text-gray-600 dark:text-gray-400 shrink-0">Endpoint URL</dt>
                  <dd class="font-mono text-[11px] text-gray-800 dark:text-gray-200 text-right break-all flex items-center gap-1.5">
                    <span>{{ layer.url }}</span>
                    <button
                      type="button"
                      aria-label="Salin URL endpoint layanan"
                      class="p-1 rounded hover:bg-gray-100 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 cursor-pointer shrink-0"
                      @click="copyValue(layer.url, 'url')"
                    >
                      <UIcon :name="copiedField === 'url' ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" aria-hidden="true" />
                    </button>
                  </dd>
                </div>
              </dl>
            </div>

            <!-- Legenda Resmi, MVT Vector Swatch, & Custom SLD -->
            <div
              v-if="hasValidMetaLegendImg || showMvtMetadataLegend"
              class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-3"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                  {{ isWmsCustomActive ? "Pratinjau Simbol Legenda (Custom SLD)" : "Pratinjau Simbol Legenda Resmi" }}
                </h2>
                <span
                  v-if="showMvtMetadataLegend && stylePreviewLegend"
                  class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 tabular-nums"
                >
                  {{ getLegendGeomLabel(stylePreviewLegend.geomType) }} · {{ stylePreviewLegend.items.length }} Simbol
                </span>
              </div>

              <div
                v-if="hasValidMetaLegendImg"
                class="p-3 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02] inline-block"
              >
                <img
                  :src="effectiveMetaLegendUrl || layer.legend_url || ''"
                  alt="Simbol legenda layer"
                  class="max-h-36 object-contain"
                  loading="lazy"
                  @error="metaLegendImgError = true"
                />
              </div>

              <div
                v-if="(showMvtMetadataLegend || metaLegendImgError) && stylePreviewLegend?.items?.length"
                class="rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02] p-3 space-y-2 text-xs"
              >
                <div
                  v-for="item in stylePreviewLegend.items"
                  :key="item.key"
                  class="flex items-center gap-2.5"
                >
                  <MartinSymbolSwatch :swatch="item.swatch" />
                  <span class="text-gray-800 dark:text-gray-200 font-medium capitalize">
                    {{ item.label }}
                  </span>
                  <span v-if="item.field" class="text-[11px] font-mono text-gray-600 dark:text-gray-400">
                    ({{ item.field }})
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Skema Atribut & Sampel Data -->
          <div class="lg:col-span-6 space-y-5">
            <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                    Skema Atribut Kolom
                  </h2>
                  <p class="text-[11px] text-gray-600 dark:text-gray-300">
                    {{
                      attributesSourceTable
                        ? `Sumber tabel: ${attributesSourceTable}`
                        : "Diambil dari layanan WFS DescribeFeatureType / skema internal."
                    }}
                  </p>
                </div>

                <UButton
                  icon="i-lucide-refresh-cw"
                  label="Muat Ulang"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  :loading="attributesLoading"
                  class="cursor-pointer min-h-[36px]"
                  @click="fetchLayerAttributes"
                />
              </div>

              <!-- Loading Attributes -->
              <div v-if="attributesLoading" class="py-8 text-center space-y-2">
                <UIcon name="i-lucide-loader-2" class="size-5 animate-spin mx-auto text-blue-600" aria-hidden="true" />
                <p class="text-xs text-gray-600 dark:text-gray-300">Memuat skema atribut layer...</p>
              </div>

              <!-- Error Attributes -->
              <div
                v-else-if="attributesError"
                class="p-4 rounded-lg border border-red-200 dark:border-red-900/40 bg-red-50/70 dark:bg-red-950/20 text-xs text-red-700 dark:text-red-300 space-y-2"
              >
                <p>{{ attributesError }}</p>
                <UButton
                  label="Coba Lagi"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  class="cursor-pointer"
                  @click="fetchLayerAttributes"
                />
              </div>

              <!-- Empty Attributes -->
              <div
                v-else-if="attributesList.length === 0"
                class="py-6 text-center text-xs text-gray-600 dark:text-gray-300"
              >
                Belum ada skema atribut kolom yang terdeteksi untuk layer ini.
              </div>

              <!-- Attributes Table -->
              <div v-else class="overflow-x-auto border border-gray-200 dark:border-white/[0.08] rounded-lg">
                <table class="w-full text-left text-xs">
                  <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/[0.08]">
                    <tr>
                      <th scope="col" class="py-2.5 px-3">Nama Kolom</th>
                      <th scope="col" class="py-2.5 px-3">Tipe</th>
                      <th scope="col" class="py-2.5 px-3">Contoh Nilai</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 dark:divide-white/[0.05]">
                    <tr v-for="attr in attributesList" :key="attr.name">
                      <td class="py-2 px-3 font-mono font-semibold text-gray-900 dark:text-white">{{ attr.name }}</td>
                      <td class="py-2 px-3 font-mono text-[11px] text-gray-600 dark:text-gray-300">{{ attr.type }}</td>
                      <td class="py-2 px-3 text-gray-700 dark:text-gray-200 break-words max-w-[220px]">
                        {{ attr.sample_value || "-" }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Raw CSW Metadata (jika tersedia) -->
            <div
              v-if="layer.csw_metadata"
              class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] p-4 sm:p-5 space-y-3"
            >
              <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                  Payload Metadata CSW / TileJSON
                </h2>
                <button
                  type="button"
                  class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
                  @click="showRawCswJson = !showRawCswJson"
                >
                  {{ showRawCswJson ? "Sembunyikan JSON" : "Lihat JSON" }}
                </button>
              </div>
              <pre
                v-if="showRawCswJson"
                class="p-3 rounded-lg bg-gray-900 text-gray-100 text-[11px] font-mono overflow-x-auto max-h-64"
              >{{ JSON.stringify(layer.csw_metadata, null, 2) }}</pre>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

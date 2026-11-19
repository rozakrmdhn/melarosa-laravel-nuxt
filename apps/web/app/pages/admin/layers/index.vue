<script lang="ts" setup>
import type { LayerStyleConfig } from "~/types/map-layers";
import LayerStyleEditor from "~/components/admin/layers/LayerStyleEditor.vue";
import ActiveLayersConfigPanel from "~/components/admin/layers/ActiveLayersConfigPanel.vue";
import {
  type LayerItem,
  getProtocolBadgeColor,
  getLayerSourceGroup,
  getHostName,
} from "~/utils/layer-meta";
import { hasWmsCustomStyle } from "~/utils/wms-sld";

definePageMeta({
  middleware: ["auth", "permission"],
  permission: "layers-view",
});

useSeoMeta({
  title: "Manajemen Layer | Melarosa GIS",
});

interface LayersResponse {
  ok: boolean;
  summary: {
    total: number;
    active: number;
    default_visible: number;
    geoportal?: number;
    internal?: number;
    external?: number;
  };
  data: LayerItem[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

const toast = useToast();
const { can } = usePermission();

const canCreate = computed(() => can("layers-create") || can("layers-manage"));
const canEdit = computed(() => can("layers-update") || can("layers-manage"));
const canDelete = computed(() => can("layers-delete") || can("layers-manage"));
const canSyncGeoportal = computed(() => can("layers-manage") || can("layers-create") || can("layers-update"));
const hasAnyAction = computed(() => canEdit.value || canDelete.value);

// ─── Workspace Mode (Katalog vs Konfigurasi Layer Aktif & Urutan) ──────────
const route = useRoute();
const router = useRouter();
const workspaceMode = ref<"catalog" | "stack">(
  route.query.view === "stack" ? "stack" : "catalog",
);
const hasMountedStackPanel = ref(workspaceMode.value === "stack");
const stackPanelRef = ref<InstanceType<typeof ActiveLayersConfigPanel> | null>(null);
const stackHasUnsavedChanges = ref(false);

function switchWorkspaceMode(mode: "catalog" | "stack") {
  workspaceMode.value = mode;
  if (mode === "stack") {
    hasMountedStackPanel.value = true;
    if (!stackHasUnsavedChanges.value) {
      nextTick(() => {
        stackPanelRef.value?.refreshStack();
      });
    }
  }
  router.replace({
    query: {
      ...route.query,
      view: mode === "stack" ? "stack" : undefined,
    },
  });
}

async function handleRefreshPage() {
  await refresh();
  if (workspaceMode.value === "stack" && !stackHasUnsavedChanges.value) {
    await stackPanelRef.value?.refreshStack();
  }
}

async function handleStackSaved() {
  await refresh();
}

// ─── Filter & State ─────────────────────────────────────────────────────────
const search = ref("");
const protocolFilter = ref<string>("ALL");
const sourceTypeFilter = ref<string>("ALL");
const statusFilter = ref<string>("ALL");
const defaultVisibleFilter = ref<string>("ALL");
const page = ref(1);
const perPage = ref(15);

// Quick segmented source tabs: "Semua", "Geoportal Bojonegoro", "Internal Melarosa", "External WMS"
type SourceTabValue = "ALL" | "geoportal" | "internal" | "external";

interface SourceTabItem {
  label: string;
  value: SourceTabValue;
  icon: string;
  countKey: "total" | "geoportal" | "internal" | "external";
  description: string;
}

const sourceTabs = computed<SourceTabItem[]>(() => [
  {
    label: "Semua Sumber",
    value: "ALL",
    icon: "i-lucide-layers",
    countKey: "total",
    description: "Semua katalog layer spasial yang terdaftar di Melarosa GIS.",
  },
  {
    label: "Geoportal Bojonegoro",
    value: "geoportal",
    icon: "i-lucide-globe-2",
    countKey: "geoportal",
    description: "WMS Service resmi pada Simpul Jaringan Geoportal Kabupaten Bojonegoro.",
  },
  {
    label: "Internal Melarosa",
    value: "internal",
    icon: "i-lucide-server",
    countKey: "internal",
    description: "Martin Tile Server MVT (Vector Tiles dinamis trase ruas & batas wilayah).",
  },
  {
    label: "External WMS",
    value: "external",
    icon: "i-lucide-cloud",
    countKey: "external",
    description: "Web Map Service (WMS) tematik dari penyedia eksternal di luar Geoportal Bojonegoro.",
  },
]);
const activeSourceTab = ref<SourceTabValue>("ALL");

function handleSourceTabChange(val: SourceTabValue) {
  activeSourceTab.value = val;
  sourceTypeFilter.value = val;
  if (workspaceMode.value === "stack") {
    switchWorkspaceMode("catalog");
  }
}

watch(sourceTypeFilter, (newVal) => {
  if (["geoportal", "internal", "external", "ALL"].includes(newVal)) {
    activeSourceTab.value = newVal as SourceTabValue;
  } else {
    activeSourceTab.value = "ALL";
  }
});

const queryParams = computed(() => ({
  page: page.value,
  per_page: perPage.value,
  search: search.value.trim() || undefined,
  protocol: protocolFilter.value !== "ALL" ? protocolFilter.value : undefined,
  source_type: sourceTypeFilter.value !== "ALL" ? sourceTypeFilter.value : undefined,
  is_active:
    statusFilter.value === "active"
      ? true
      : statusFilter.value === "inactive"
      ? false
      : undefined,
  default_visible:
    defaultVisibleFilter.value === "yes"
      ? true
      : defaultVisibleFilter.value === "no"
      ? false
      : undefined,
}));

const { data, status, refresh, error } = useHttp<LayersResponse>("admin/layers", {
  query: queryParams,
  watch: [queryParams],
});

const loading = computed(() => status.value === "pending");

watch([search, protocolFilter, sourceTypeFilter, statusFilter, defaultVisibleFilter], () => {
  page.value = 1;
});

const hasActiveCatalogFilters = computed(() =>
  Boolean(
    search.value.trim() ||
      protocolFilter.value !== "ALL" ||
      activeSourceTab.value !== "ALL" ||
      statusFilter.value !== "ALL" ||
      defaultVisibleFilter.value !== "ALL"
  )
);

function resetCatalogFilters() {
  search.value = "";
  protocolFilter.value = "ALL";
  activeSourceTab.value = "ALL";
  sourceTypeFilter.value = "ALL";
  statusFilter.value = "ALL";
  defaultVisibleFilter.value = "ALL";
}

// Dropdown options
const protocolOptions = [
  { label: "Semua Protokol", value: "ALL" },
  { label: "WMS (Web Map Service)", value: "wms" },
  { label: "WFS (Web Feature Service)", value: "wfs" },
  { label: "XYZ Tiles", value: "xyz" },
  { label: "Vector Tiles (MVT)", value: "mvt" },
  { label: "GeoJSON", value: "geojson" },
];

const formProtocolOptions = [
  { label: "WMS (Web Map Service)", value: "wms" },
  { label: "WFS (Web Feature Service)", value: "wfs" },
  { label: "XYZ Tiles", value: "xyz" },
  { label: "Vector Tiles (MVT)", value: "mvt" },
  { label: "GeoJSON", value: "geojson" },
];

const sourceTypeOptions = [
  { label: "Semua Kelompok Sumber", value: "ALL" },
  { label: "Geoportal Bojonegoro (WMS)", value: "geoportal" },
  { label: "Internal Melarosa (Martin MVT)", value: "internal" },
  { label: "External GIS / WMS (Luar Geoportal)", value: "external" },
  { label: "GeoServer Palapa (Spesifik)", value: "geoserver" },
  { label: "XYZ Tiles Provider", value: "xyz_tiles" },
  { label: "Local GeoJSON", value: "local_geojson" },
  { label: "Basemap Provider", value: "basemap" },
];

const formSourceTypeOptions = [
  { label: "Geoportal Bojonegoro (WMS GeoServer Palapa)", value: "geoportal" },
  { label: "Internal Martin Server (Vector Tiles MVT)", value: "internal" },
  { label: "External WMS Service (Luar Geoportal)", value: "external" },
  { label: "XYZ Tiles Provider (OpenStreetMap / Google / Custom)", value: "xyz_tiles" },
  { label: "Local GeoJSON Vector", value: "local_geojson" },
  { label: "Basemap Provider", value: "basemap" },
];

const statusOptions = [
  { label: "Semua Status", value: "ALL" },
  { label: "Hanya Aktif", value: "active" },
  { label: "Hanya Nonaktif", value: "inactive" },
];

const defaultVisibleOptions = [
  { label: "Semua Tampilan", value: "ALL" },
  { label: "Default Tampil", value: "yes" },
  { label: "Default Tersembunyi", value: "no" },
];

// ─── Modal Create / Edit State ──────────────────────────────────────────────
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingLayerId = ref<string | null>(null);
const submitting = ref(false);
const activeModalTab = ref<"info" | "style">("info");
const hasOpenedStyleTab = ref(false);

watch(isModalOpen, (open) => {
  if (!open) {
    hasOpenedStyleTab.value = false;
  }
});

const formState = reactive({
  name: "",
  protocol: "wms",
  source_type: "geoserver",
  url: "",
  layer_name: "",
  opacity: 1.0,
  order: 0,
  attribution: "",
  description: "",
  is_active: true,
  default_visible: false,
  style_config: null as LayerStyleConfig | null,
});

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

const formErrors = reactive<Record<string, string>>({});

function openCreateModal() {
  if (!canCreate.value) return;
  isEditing.value = false;
  editingLayerId.value = null;
  activeModalTab.value = "info";
  hasOpenedStyleTab.value = false;

  // Next order calculation
  const currentLayers = data.value?.data || [];
  const maxOrder = currentLayers.reduce((max, l) => Math.max(max, l.order ?? 0), -1);

  if (activeSourceTab.value === "internal") {
    formState.name = "";
    formState.protocol = "mvt";
    formState.source_type = "internal";
    formState.url = "/martin/nama_layer/{z}/{x}/{y}";
    formState.layer_name = "";
    formState.opacity = 1.0;
    formState.order = maxOrder + 1;
    formState.attribution = "Pemerintah Kabupaten Bojonegoro";
    formState.description = "";
    formState.is_active = true;
    formState.default_visible = false;
    formState.style_config = null;
  } else if (activeSourceTab.value === "external") {
    formState.name = "";
    formState.protocol = "wms";
    formState.source_type = "external";
    formState.url = "https://";
    formState.layer_name = "";
    formState.opacity = 1.0;
    formState.order = maxOrder + 1;
    formState.attribution = "Eksternal GIS";
    formState.description = "";
    formState.is_active = true;
    formState.default_visible = false;
    formState.style_config = null;
  } else {
    formState.name = "";
    formState.protocol = "wms";
    formState.source_type = "geoportal";
    formState.url = "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms";
    formState.layer_name = "";
    formState.opacity = 1.0;
    formState.order = maxOrder + 1;
    formState.attribution = "Geoportal Bojonegoro";
    formState.description = "";
    formState.is_active = true;
    formState.default_visible = false;
    formState.style_config = null;
  }

  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  isModalOpen.value = true;
}

function openEditModal(layer: LayerItem, initialTab: "info" | "style" = "info") {
  if (!canEdit.value) return;
  isEditing.value = true;
  editingLayerId.value = layer.id;
  activeModalTab.value = initialTab;
  hasOpenedStyleTab.value = initialTab === "style";

  formState.name = layer.name;
  formState.protocol = layer.protocol;
  formState.source_type = layer.source_type;
  formState.url = layer.url;
  formState.layer_name = layer.layer_name || "";
  formState.opacity = Number(layer.opacity ?? 1.0);
  formState.order = Number(layer.order ?? 0);
  formState.attribution = layer.attribution || "";
  formState.description = layer.description || "";
  formState.is_active = Boolean(layer.is_active);
  formState.default_visible = Boolean(layer.default_visible);
  formState.style_config = layer.style_config ? JSON.parse(JSON.stringify(layer.style_config)) : null;

  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  isModalOpen.value = true;
}

function openStyleModal(layer: LayerItem) {
  openEditModal(layer, "style");
}

function validateForm(): boolean {
  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  let isValid = true;

  if (!formState.name.trim()) {
    formErrors.name = "Nama tampilan layer wajib diisi.";
    isValid = false;
  }
  if (!formState.protocol.trim()) {
    formErrors.protocol = "Protokol layer wajib dipilih.";
    isValid = false;
  }
  if (!formState.source_type.trim()) {
    formErrors.source_type = "Tipe sumber data wajib dipilih.";
    isValid = false;
  }
  if (!formState.url.trim()) {
    formErrors.url = "URL Base Geoserver atau Service wajib diisi.";
    isValid = false;
  }
  if (["wms", "wfs"].includes(formState.protocol) && !formState.layer_name.trim()) {
    formErrors.layer_name = "Nama layer teknis wajib diisi untuk protokol WMS/WFS.";
    isValid = false;
  }
  if (formState.opacity < 0 || formState.opacity > 1) {
    formErrors.opacity = "Tingkat transparansi harus berada di antara 0.0 dan 1.0.";
    isValid = false;
  }
  if (formState.order < 0) {
    formErrors.order = "Urutan z-index tidak boleh bernilai negatif.";
    isValid = false;
  }

  return isValid;
}

async function handleSaveLayer() {
  if (!validateForm()) return;

  submitting.value = true;
  try {
    const url = isEditing.value
      ? `admin/layers/${editingLayerId.value}`
      : "admin/layers";
    const method = isEditing.value ? "PUT" : "POST";

    const payload = {
      name: formState.name.trim(),
      protocol: formState.protocol,
      source_type: formState.source_type,
      url: formState.url.trim(),
      layer_name: formState.layer_name.trim() || null,
      opacity: Number(formState.opacity),
      order: Number(formState.order),
      attribution: formState.attribution.trim() || null,
      description: formState.description.trim() || null,
      is_active: formState.is_active,
      default_visible: formState.default_visible,
      style_config: formState.style_config || null,
    };

    const res = await $http<{ ok: boolean; message: string }>(url, {
      method,
      body: payload,
    });

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Data layer berhasil disimpan.",
        color: "success",
      });
      isModalOpen.value = false;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Gagal menyimpan konfigurasi layer.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    submitting.value = false;
  }
}

// ─── Instant Toggle Active Status ───────────────────────────────────────────
const togglingIds = ref<Set<string>>(new Set());

async function handleToggleActive(layer: LayerItem) {
  if (!canEdit.value) return;

  togglingIds.value.add(layer.id);
  const previousState = layer.is_active;
  layer.is_active = !previousState;

  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/layers/${layer.id}/toggle-active`,
      { method: "PATCH" }
    );

    toast.add({
      icon: "i-heroicons-check-circle",
      title: res.message || (layer.is_active ? "Layer diaktifkan." : "Layer dinonaktifkan."),
      color: "success",
    });
  } catch (err: any) {
    layer.is_active = previousState;
    const msg = err?.response?._data?.message || "Gagal memperbarui status layer.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    togglingIds.value.delete(layer.id);
  }
}

// ─── Quick Reorder (Move Up / Down) ─────────────────────────────────────────
const reordering = ref(false);

async function moveLayer(layer: LayerItem, direction: "up" | "down") {
  if (!canEdit.value || reordering.value) return;

  const list = [...(data.value?.data || [])];
  const index = list.findIndex((l) => l.id === layer.id);
  if (index === -1) return;

  const targetIndex = direction === "up" ? index - 1 : index + 1;
  if (targetIndex < 0 || targetIndex >= list.length) return;

  const targetLayer = list[targetIndex];

  // Swap order values
  const currentOrder = layer.order;
  const targetOrder = targetLayer.order;
  const newOrderForCurrent = currentOrder === targetOrder ? (direction === "up" ? targetOrder - 1 : targetOrder + 1) : targetOrder;

  reordering.value = true;
  try {
    await $http<{ ok: boolean }>("admin/layers/reorder", {
      method: "POST",
      body: {
        items: [
          { id: layer.id, order: newOrderForCurrent },
          { id: targetLayer.id, order: currentOrder },
        ],
      },
    });

    toast.add({
      icon: "i-heroicons-check-circle",
      title: "Urutan z-index layer diperbarui.",
      color: "success",
    });
    await refresh();
  } catch (err: any) {
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Gagal memperbarui urutan layer.",
      color: "error",
    });
  } finally {
    reordering.value = false;
  }
}

// ─── Delete Modal State ─────────────────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const layerToDelete = ref<LayerItem | null>(null);
const deleting = ref(false);

function confirmDelete(layer: LayerItem) {
  if (!canDelete.value) return;
  layerToDelete.value = layer;
  isDeleteModalOpen.value = true;
}

async function handleDeleteLayer() {
  if (!layerToDelete.value || !canDelete.value) return;

  deleting.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/layers/${layerToDelete.value.id}`,
      { method: "DELETE" }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Layer berhasil dihapus dari sistem.",
        color: "success",
      });
      isDeleteModalOpen.value = false;
      layerToDelete.value = null;
      await refresh();
    }
  } catch (err: any) {
    const msg = err?.response?._data?.message || "Gagal menghapus layer.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    deleting.value = false;
  }
}

// Layer badge & source helpers live in ~/utils/layer-meta.ts

function getSourceTypeLabel(type: string): string {
  switch (type) {
    case "geoportal":
      return "Geoportal Bojonegoro";
    case "internal":
      return "Internal Martin";
    case "geoserver":
      return "GeoServer";
    case "external":
    case "external_wms":
      return "Eksternal WMS";
    case "xyz_tiles":
      return "XYZ Tiles";
    case "local_geojson":
      return "Local GeoJSON";
    case "basemap":
      return "Basemap";
    default:
      return type;
  }
}

import { useGeoportalStatus } from "~/composables/useGeoportalLayers";
import { useMartinStatus, type MartinCatalogItem } from "~/composables/useMartinLayers";

// ─── Geoportal CSW & Martin Sync State ───────────────────────────────────────
const isSyncModalOpen = ref(false);
const isMartinSyncModalOpen = ref(false);
const isSyncHistoryModalOpen = ref(false);
const isMetadataModalOpen = ref(false);
const selectedGeoportalLayer = ref<LayerItem | null>(null);
const syncHistorySourceFilter = ref<"all" | "geoportal" | "martin">("all");

const syncingGeoportal = ref(false);
const syncResult = ref<{
  records_found: number;
  records_inserted: number;
  records_updated: number;
  records_skipped: number;
  duration_seconds: number;
} | null>(null);

const syncingMartin = ref(false);
const loadingMartinCatalog = ref(false);
const martinCatalogError = ref<string | null>(null);
const martinCatalogItems = ref<MartinCatalogItem[]>([]);
const selectedMartinSources = ref<string[]>([]);
const martinDeactivateMissing = ref(false);
const martinSyncResult = ref<{
  records_found: number;
  records_inserted: number;
  records_updated: number;
  records_skipped: number;
  records_deactivated?: number;
  duration_seconds: number;
} | null>(null);

const syncHistoryQuery = computed(() => ({
  source: syncHistorySourceFilter.value,
}));

const {
  data: syncHistoryData,
  status: syncHistoryStatus,
  refresh: refreshSyncHistory,
} = useHttp<{
  ok: boolean;
  data: Array<{
    id: string;
    triggered_by: string;
    source_service?: "geoportal" | "martin";
    status: string;
    records_found: number;
    records_inserted: number;
    records_updated: number;
    records_skipped: number;
    error_message: string | null;
    duration_seconds: number;
    created_at: string;
    user?: { name: string } | null;
  }>;
}>("admin/layers/geoportal/sync-history", {
  query: syncHistoryQuery,
  watch: [syncHistoryQuery],
  lazy: true,
  immediate: false,
});

function openSyncModal() {
  syncResult.value = null;
  isSyncModalOpen.value = true;
}

async function loadMartinCatalog() {
  loadingMartinCatalog.value = true;
  martinCatalogError.value = null;
  try {
    const res = await $http<{
      ok: boolean;
      data: MartinCatalogItem[];
      message?: string;
    }>("admin/layers/martin/catalog");
    if (res?.ok) {
      const items = res.data || [];
      martinCatalogItems.value = items;
      selectedMartinSources.value = items
        .filter((item) => !item.is_legacy)
        .map((item) => item.source_id);
    } else {
      martinCatalogItems.value = [];
      martinCatalogError.value = res?.message || "Gagal memuat katalog dari Martin Tile Server.";
    }
  } catch (err: any) {
    martinCatalogItems.value = [];
    martinCatalogError.value =
      err?.response?._data?.message ||
      err?.message ||
      "Tidak dapat terhubung ke Martin Tile Server.";
  } finally {
    loadingMartinCatalog.value = false;
  }
}

function openMartinSyncModal() {
  martinSyncResult.value = null;
  martinDeactivateMissing.value = false;
  isMartinSyncModalOpen.value = true;
  loadMartinCatalog();
  refreshMartinStatus(true);
}

function toggleMartinSource(sourceId: string) {
  if (selectedMartinSources.value.includes(sourceId)) {
    selectedMartinSources.value = selectedMartinSources.value.filter((id) => id !== sourceId);
  } else {
    selectedMartinSources.value = [...selectedMartinSources.value, sourceId];
  }
}

function selectMartinPreset(mode: "all" | "primary" | "unsynced" | "none") {
  if (mode === "all") {
    selectedMartinSources.value = martinCatalogItems.value.map((item) => item.source_id);
  } else if (mode === "primary") {
    selectedMartinSources.value = martinCatalogItems.value
      .filter((item) => !item.is_legacy)
      .map((item) => item.source_id);
  } else if (mode === "unsynced") {
    selectedMartinSources.value = martinCatalogItems.value
      .filter((item) => !item.synced)
      .map((item) => item.source_id);
  } else {
    selectedMartinSources.value = [];
  }
}

function openSyncHistoryModal(source: "all" | "geoportal" | "martin" = "all") {
  const prev = syncHistorySourceFilter.value;
  syncHistorySourceFilter.value = source;
  if (prev === source) {
    refreshSyncHistory();
  }
  isSyncHistoryModalOpen.value = true;
}

function openMetadataModal(layer: LayerItem) {
  selectedGeoportalLayer.value = layer;
  isMetadataModalOpen.value = true;
}

function isMartinSyncLog(log: { triggered_by: string; source_service?: string }): boolean {
  return log.source_service === "martin" || log.triggered_by?.startsWith("martin_");
}

function formatTriggerLabel(triggeredBy: string): string {
  const normalized = (triggeredBy || "").replace(/^martin_/, "");
  if (normalized === "manual_admin") return "Admin (Manual)";
  if (normalized === "scheduler") return "Jadwal Otomatis";
  if (normalized === "cli") return "CLI Artisan";
  return normalized.replace(/_/g, " ") || "Sistem";
}

function isMartinLayerItem(layer: LayerItem | null): boolean {
  if (!layer) return false;
  const st = (layer.source_type || "").toLowerCase();
  const proto = (layer.protocol || "").toLowerCase();
  return (
    st === "internal" ||
    st === "martin" ||
    proto === "mvt" ||
    (layer.url || "").includes("/martin/") ||
    layer.csw_metadata?.source_service === "martin"
  );
}

const {
  connectionStatus: geoportalStatus,
  checking: checkingGeoportalStatus,
  checkConnection: refreshGeoportalStatus,
} = useGeoportalStatus();

const {
  connectionStatus: martinStatus,
  checking: checkingMartinStatus,
  checkConnection: refreshMartinStatus,
} = useMartinStatus();

const isServiceStatusModalOpen = ref(false);

const overallServiceIndicatorClass = computed(() => {
  const geo = geoportalStatus.value?.status;
  const mar = martinStatus.value?.status;
  if (geo === "online" && mar === "online") return "bg-emerald-500";
  if (geo === "offline" || mar === "offline") return "bg-red-500";
  return "bg-amber-500";
});

function formatLastSync(iso?: string | null): string {
  if (!iso) return "Belum pernah disinkronkan";
  const date = new Date(iso);
  if (isNaN(date.getTime())) return "-";
  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function timeAgo(iso?: string | null): string {
  if (!iso) return "Belum pernah";
  const date = new Date(iso);
  if (isNaN(date.getTime())) return "-";
  const diffSec = Math.floor((Date.now() - date.getTime()) / 1000);
  if (diffSec < 60) return "Baru saja";
  const diffMin = Math.floor(diffSec / 60);
  if (diffMin < 60) return `${diffMin} menit lalu`;
  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours} jam lalu`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays} hari lalu`;
}

async function handleSyncGeoportal() {
  syncingGeoportal.value = true;
  syncResult.value = null;
  try {
    const res = await $http<{
      ok: boolean;
      message: string;
      data?: {
        records_found: number;
        records_inserted: number;
        records_updated: number;
        records_skipped: number;
        duration_seconds: number;
      };
      summary?: {
        found: number;
        inserted: number;
        updated: number;
        skipped: number;
        duration_seconds: number;
      };
    }>("admin/layers/geoportal/sync", {
      method: "POST",
    });

    if (res?.ok) {
      const summaryData = res.data ?? {
        records_found: res.summary?.found ?? 0,
        records_inserted: res.summary?.inserted ?? 0,
        records_updated: res.summary?.updated ?? 0,
        records_skipped: res.summary?.skipped ?? 0,
        duration_seconds: res.summary?.duration_seconds ?? 0,
      };

      syncResult.value = summaryData;
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Sinkronisasi Geoportal berhasil.",
        description: `${summaryData.records_found} layer dipanen (${summaryData.records_inserted} baru, ${summaryData.records_updated} diperbarui) dalam ${summaryData.duration_seconds}s.`,
        color: "success",
      });
      await refresh();
      await refreshGeoportalStatus(true);
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message ||
      err?.message ||
      "Gagal menyinkronkan data katalog Geoportal.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Sinkronisasi Gagal",
      description: msg,
      color: "error",
    });
  } finally {
    syncingGeoportal.value = false;
  }
}

async function handleSyncMartin() {
  syncingMartin.value = true;
  martinSyncResult.value = null;
  try {
    const payload: {
      sources?: string[];
      deactivate_missing: boolean;
      include_tilejson: boolean;
    } = {
      deactivate_missing: martinDeactivateMissing.value,
      include_tilejson: true,
    };

    if (
      martinCatalogItems.value.length > 0 &&
      selectedMartinSources.value.length > 0 &&
      selectedMartinSources.value.length < martinCatalogItems.value.length
    ) {
      payload.sources = selectedMartinSources.value;
    }

    const res = await $http<{
      ok: boolean;
      message: string;
      data?: {
        records_found: number;
        records_inserted: number;
        records_updated: number;
        records_skipped: number;
        records_deactivated?: number;
        duration_seconds: number;
      };
      summary?: {
        found: number;
        inserted: number;
        updated: number;
        skipped: number;
        deactivated?: number;
        duration_seconds: number;
      };
    }>("admin/layers/martin/sync", {
      method: "POST",
      body: payload,
    });

    if (res?.ok) {
      const summaryData = res.data ?? {
        records_found: res.summary?.found ?? 0,
        records_inserted: res.summary?.inserted ?? 0,
        records_updated: res.summary?.updated ?? 0,
        records_skipped: res.summary?.skipped ?? 0,
        records_deactivated: res.summary?.deactivated ?? 0,
        duration_seconds: res.summary?.duration_seconds ?? 0,
      };

      martinSyncResult.value = summaryData;
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Sinkronisasi Martin Tile Server berhasil.",
        description: `${summaryData.records_found} sumber diproses (${summaryData.records_inserted} baru, ${summaryData.records_updated} diperbarui) dalam ${summaryData.duration_seconds}s.`,
        color: "success",
      });
      await refresh();
      await refreshMartinStatus(true);
      await loadMartinCatalog();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message ||
      err?.message ||
      "Gagal menyinkronkan katalog dari Martin Tile Server.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Sinkronisasi Martin Gagal",
      description: msg,
      color: "error",
    });
  } finally {
    syncingMartin.value = false;
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header Section: Bersih & Proporsional -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <UIcon name="i-lucide-layers" class="size-5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
            Manajemen Layer Peta
          </h1>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300">
          Kelola katalog layer Geoportal WMS, Martin MVT, custom style SLD, dan urutan tumpukan peta.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          aria-label="Info Status Layanan Geoportal dan Martin"
          class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          @click="isServiceStatusModalOpen = true"
        >
          <template #leading>
            <span class="size-2 rounded-full shrink-0" :class="overallServiceIndicatorClass" />
          </template>
          <span>Info Layanan</span>
        </UButton>
        <UButton
          v-if="canSyncGeoportal"
          label="Riwayat Sync"
          icon="i-lucide-history"
          color="neutral"
          variant="outline"
          size="sm"
          class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          @click="openSyncHistoryModal('all')"
        />
        <UButton
          icon="i-heroicons-arrow-path"
          label="Refresh"
          aria-label="Muat ulang data layer"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          @click="handleRefreshPage"
        />
        <UButton
          v-if="canCreate"
          label="Tambah"
          aria-label="Tambah layer baru"
          icon="i-heroicons-plus"
          color="primary"
          size="sm"
          class="min-h-[44px] sm:min-h-[38px] px-3.5 whitespace-nowrap cursor-pointer font-semibold"
          @click="openCreateModal"
        />
      </div>
    </header>

    <!-- Workspace Tab Menu: Semua Katalog vs Konfigurasi Layer Aktif -->
    <div class="border-b border-gray-200 dark:border-white/[0.08] flex flex-col sm:flex-row sm:items-end justify-between gap-2">
      <div
        class="flex items-center gap-5 sm:gap-6 overflow-x-auto -mb-px"
        role="tablist"
        aria-label="Mode Pengelolaan Layer"
      >
        <button
          type="button"
          role="tab"
          :aria-selected="workspaceMode === 'catalog'"
          class="min-h-[44px] px-1 py-2.5 border-b-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer inline-flex items-center gap-2 whitespace-nowrap shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:focus-visible:outline-blue-400"
          :class="workspaceMode === 'catalog'
            ? 'border-blue-600 dark:border-blue-400 text-blue-600 dark:text-blue-400'
            : 'border-transparent text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:border-gray-300 dark:hover:border-white/20'"
          @click="switchWorkspaceMode('catalog')"
        >
          <UIcon name="i-lucide-table-properties" class="size-4 shrink-0" aria-hidden="true" />
          <span>Semua Katalog</span>
          <span
            class="px-1.5 py-0.5 rounded-md text-[11px] font-bold tabular-nums"
            :class="workspaceMode === 'catalog'
              ? 'bg-blue-50 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300'
              : 'bg-gray-100 dark:bg-white/[0.06] text-gray-700 dark:text-gray-300'"
          >
            {{ data?.summary?.total ?? 0 }}
          </span>
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="workspaceMode === 'stack'"
          class="min-h-[44px] px-1 py-2.5 border-b-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer inline-flex items-center gap-2 whitespace-nowrap shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:focus-visible:outline-blue-400"
          :class="workspaceMode === 'stack'
            ? 'border-blue-600 dark:border-blue-400 text-blue-600 dark:text-blue-400'
            : 'border-transparent text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:border-gray-300 dark:hover:border-white/20'"
          @click="switchWorkspaceMode('stack')"
        >
          <UIcon name="i-lucide-list-ordered" class="size-4 shrink-0" aria-hidden="true" />
          <span>Konfigurasi Layer Aktif</span>
          <span
            class="px-1.5 py-0.5 rounded-md text-[11px] font-bold tabular-nums"
            :class="workspaceMode === 'stack'
              ? 'bg-blue-50 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300'
              : 'bg-gray-100 dark:bg-white/[0.06] text-gray-700 dark:text-gray-300'"
          >
            {{ data?.summary?.active ?? 0 }} aktif
          </span>
          <span
            v-if="stackHasUnsavedChanges"
            class="size-2 rounded-full bg-amber-500 shrink-0"
            title="Ada perubahan konfigurasi belum disimpan"
            aria-label="Ada perubahan konfigurasi belum disimpan"
          />
        </button>
      </div>

      <p class="hidden md:block text-xs text-gray-600 dark:text-gray-300 pb-2.5 shrink-0 tabular-nums">
        {{ data?.summary?.default_visible ?? 0 }} tampil otomatis di /maps
      </p>
    </div>

    <!-- Active Layers & Stacking Configuration Workspace -->
    <ActiveLayersConfigPanel
      v-if="hasMountedStackPanel"
      v-show="workspaceMode === 'stack'"
      ref="stackPanelRef"
      :can-edit="canEdit"
      @saved="handleStackSaved"
      @open-catalog="switchWorkspaceMode('catalog')"
      @dirty-change="stackHasUnsavedChanges = $event"
    />

    <!-- Catalog Workspace -->
    <div v-show="workspaceMode === 'catalog'" class="space-y-4">
      <!-- Error Alert -->
      <div
        v-if="error"
        class="p-4 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 flex items-center justify-between gap-3"
      >
        <div class="flex items-center gap-3 text-xs sm:text-sm">
          <UIcon name="i-heroicons-exclamation-triangle" class="size-5 shrink-0" aria-hidden="true" />
          <span>Gagal memuat katalog layer. Pastikan koneksi backend tersedia.</span>
        </div>
        <UButton
          label="Coba Lagi"
          size="xs"
          color="error"
          variant="subtle"
          class="cursor-pointer shrink-0"
          @click="() => refresh()"
        />
      </div>

      <!-- Unified Source Filter & Search Toolbar (Tanpa Duplikasi KPI Card / Dropdown Sumber) -->
      <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100 dark:border-white/[0.06]">
          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 text-xs">
            <button
              v-for="tab in sourceTabs"
              :key="tab.value"
              type="button"
              class="px-3 py-1.5 min-h-[38px] rounded-lg text-xs font-medium transition-colors cursor-pointer flex items-center gap-2 shrink-0 border"
              :class="activeSourceTab === tab.value
                ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                : 'bg-gray-50 dark:bg-white/[0.03] border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]'"
              @click="handleSourceTabChange(tab.value)"
            >
              <UIcon :name="tab.icon" class="size-3.5 shrink-0" aria-hidden="true" />
              <span>{{ tab.label }}</span>
              <span
                class="text-[11px] tabular-nums px-1.5 py-0.5 rounded font-bold"
                :class="activeSourceTab === tab.value
                  ? 'bg-white/20 text-white'
                  : 'bg-gray-200/80 dark:bg-white/10 text-gray-700 dark:text-gray-200'"
              >
                {{ data?.summary?.[tab.countKey] ?? 0 }}
              </span>
            </button>
          </div>

          <button
            v-if="hasActiveCatalogFilters"
            type="button"
            class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer shrink-0 self-start sm:self-center"
            @click="resetCatalogFilters"
          >
            Reset Filter
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <div class="sm:col-span-2">
            <UInput
              v-model="search"
              icon="i-heroicons-magnifying-glass"
              placeholder="Cari nama layer, kode layer, atau instansi..."
              aria-label="Cari layer"
              class="w-full"
              clearable
            />
          </div>

          <div>
            <USelect
              v-model="protocolFilter"
              :items="protocolOptions"
              aria-label="Filter protokol"
              class="w-full"
            />
          </div>

          <div>
            <USelect
              v-model="statusFilter"
              :items="statusOptions"
              aria-label="Filter status aktif"
              class="w-full"
            />
          </div>

          <div>
            <USelect
              v-model="defaultVisibleFilter"
              :items="defaultVisibleOptions"
              aria-label="Filter tampil awal"
              class="w-full"
            />
          </div>
        </div>
      </div>

    <!-- Layer Catalog Table -->
    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <!-- Loading Skeleton State -->
      <div v-if="loading" class="p-8 text-center space-y-3">
        <UIcon name="i-lucide-loader-2" class="size-6 animate-spin mx-auto text-primary-500" />
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data katalog layer...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="!data?.data || data.data.length === 0"
        class="p-12 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
          <UIcon name="i-lucide-layers" class="size-6" />
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">
          {{ activeSourceTab !== 'ALL' ? `Tidak ada data layer untuk sumber ${sourceTabs.find(t => t.value === activeSourceTab)?.label}` : 'Tidak ada data layer' }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          Belum ada data layer yang terdaftar atau kriteria pencarian tidak menemukan hasil.
        </p>
        <div v-if="canCreate" class="pt-2">
          <UButton
            label="Tambah Layer Baru"
            icon="i-heroicons-plus"
            size="sm"
            color="primary"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- Mobile Card View -->
      <div v-else class="divide-y divide-gray-200 dark:divide-white/[0.06] md:hidden">
        <div
          v-for="layer in data.data"
          :key="`mob-${layer.id}`"
          class="p-4 space-y-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
              <NuxtLink
                :to="`/admin/layers/${layer.id}`"
                class="font-semibold text-sm text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 block break-words"
              >
                {{ layer.name }}
              </NuxtLink>
              <div v-if="layer.layer_name" class="font-mono text-[11px] text-gray-600 dark:text-gray-300 break-all">
                {{ layer.layer_name }}
              </div>
            </div>
            <USwitch
              :model-value="layer.is_active"
              :disabled="!canEdit || togglingIds.has(layer.id)"
              :aria-label="`Aktifkan layer ${layer.name}`"
              @update:model-value="() => handleToggleActive(layer)"
            />
          </div>

          <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
            <UBadge
              :label="getLayerSourceGroup(layer).badgeLabel"
              :color="getLayerSourceGroup(layer).badgeColor"
              variant="subtle"
              size="xs"
            />
            <UBadge
              :label="layer.protocol.toUpperCase()"
              :color="getProtocolBadgeColor(layer.protocol)"
              variant="outline"
              size="xs"
              class="font-mono"
            />
            <UBadge
              v-if="layer.protocol?.toLowerCase() === 'wms' && hasWmsCustomStyle(layer.style_config)"
              label="Custom SLD"
              color="primary"
              variant="outline"
              size="xs"
            />
            <span class="text-gray-600 dark:text-gray-300 tabular-nums">
              #{{ layer.order }} · {{ Math.round((layer.opacity ?? 1) * 100) }}%
            </span>
          </div>

          <div v-if="hasAnyAction" class="flex items-center gap-2 pt-1">
            <UButton
              v-if="canEdit"
              :to="`/admin/layers/${layer.id}`"
              label="Edit Layer"
              icon="i-heroicons-pencil"
              size="xs"
              color="neutral"
              variant="outline"
              class="flex-1 justify-center min-h-[44px] cursor-pointer"
            />
            <UButton
              v-if="canDelete"
              label="Hapus"
              icon="i-heroicons-trash"
              size="xs"
              color="error"
              variant="outline"
              class="min-h-[44px] cursor-pointer"
              @click="confirmDelete(layer)"
            />
          </div>
        </div>
      </div>

      <!-- Desktop Table Content -->
      <div v-if="data?.data && data.data.length > 0 && !loading" class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-3 w-16 text-center">Urutan</th>
              <th scope="col" class="py-3 px-4">Nama & Identitas Teknis</th>
              <th scope="col" class="py-3 px-3">Kelompok Sumber</th>
              <th scope="col" class="py-3 px-3">Protokol</th>
              <th scope="col" class="py-3 px-3 w-28">Transparansi</th>
              <th scope="col" class="py-3 px-3 text-center">Tampil Awal</th>
              <th scope="col" class="py-3 px-3 text-center">Status</th>
              <th v-if="hasAnyAction" scope="col" class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="(layer, index) in data.data"
              :key="layer.id"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <!-- Order Column with Quick Up/Down -->
              <td class="py-3 px-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <div class="text-center">
                    <span class="tabular-nums font-semibold text-gray-800 dark:text-gray-200">
                      #{{ layer.order }}
                    </span>
                    <div class="text-[10px] tabular-nums text-gray-500 dark:text-gray-400">
                      z:{{ 10 + Number(layer.order ?? 0) * 10 }}
                    </div>
                  </div>
                  <div v-if="canEdit" class="flex flex-col">
                    <button
                      type="button"
                      :disabled="index === 0 || reordering"
                      :aria-label="`Naikkan baris ${layer.name}`"
                      class="text-gray-500 hover:text-gray-800 dark:hover:text-white disabled:opacity-25 cursor-pointer p-1"
                      title="Pindah ke baris lebih atas di tabel"
                      @click="moveLayer(layer, 'up')"
                    >
                      <UIcon name="i-heroicons-chevron-up" class="size-3.5" />
                    </button>
                    <button
                      type="button"
                      :disabled="index === data.data.length - 1 || reordering"
                      :aria-label="`Turunkan baris ${layer.name}`"
                      class="text-gray-500 hover:text-gray-800 dark:hover:text-white disabled:opacity-25 cursor-pointer p-1"
                      title="Pindah ke baris lebih bawah di tabel"
                      @click="moveLayer(layer, 'down')"
                    >
                      <UIcon name="i-heroicons-chevron-down" class="size-3.5" />
                    </button>
                  </div>
                </div>
              </td>

              <!-- Name & Technical layer_name -->
              <td class="py-3 px-4">
                <div class="space-y-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <NuxtLink
                      :to="`/admin/layers/${layer.id}`"
                      class="font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 text-sm transition-colors"
                    >
                      {{ layer.name }}
                    </NuxtLink>
                    <UBadge
                      v-if="layer.category"
                      :label="layer.category"
                      color="neutral"
                      variant="outline"
                      size="xs"
                      class="text-[10px]"
                    />
                  </div>
                  <div v-if="layer.layer_name" class="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-md">
                    {{ layer.layer_name }}
                  </div>
                  <div class="flex items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400 flex-wrap">
                    <span v-if="layer.opd_name || layer.attribution" class="flex items-center gap-1">
                      <UIcon name="i-lucide-building-2" class="size-3 text-gray-400 shrink-0" />
                      <span>{{ layer.opd_name || layer.attribution }}</span>
                    </span>
                    <span v-if="layer.last_synced_at" class="flex items-center gap-1 text-[10px] text-gray-400 font-mono">
                      <UIcon name="i-lucide-refresh-cw" class="size-2.5" />
                      <span>Sync: {{ new Date(layer.last_synced_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                    </span>
                  </div>
                </div>
              </td>

              <!-- Kelompok Sumber Data -->
              <td class="py-3 px-3">
                <div class="flex flex-col items-start gap-1">
                  <UBadge
                    :color="getLayerSourceGroup(layer).badgeColor"
                    variant="subtle"
                    size="xs"
                    class="font-semibold text-[11px] flex items-center gap-1.5"
                  >
                    <UIcon :name="getLayerSourceGroup(layer).icon" class="size-3.5 shrink-0" />
                    <span>{{ getLayerSourceGroup(layer).badgeLabel }}</span>
                  </UBadge>
                  <span
                    class="text-[10px] text-gray-500 dark:text-gray-400 font-mono truncate max-w-[170px]"
                    :title="layer.url"
                  >
                    {{ getHostName(layer.url) }}
                  </span>
                </div>
              </td>

              <!-- Protokol -->
              <td class="py-3 px-3">
                <div class="flex flex-col items-start gap-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <UBadge
                      :label="layer.protocol.toUpperCase()"
                      :color="getProtocolBadgeColor(layer.protocol)"
                      variant="subtle"
                      size="xs"
                      class="font-mono uppercase font-bold text-[10px]"
                    />
                    <UBadge
                      v-if="layer.protocol?.toLowerCase() === 'mvt' && layer.style_config"
                      label="Styled"
                      color="primary"
                      variant="outline"
                      size="xs"
                      class="text-[9px] px-1 py-0 h-4 font-mono"
                      :title="`Geometri: ${layer.style_config.geom_type || 'auto'}`"
                    />
                    <UBadge
                      v-else-if="layer.protocol?.toLowerCase() === 'wms' && hasWmsCustomStyle(layer.style_config)"
                      label="Custom SLD"
                      color="primary"
                      variant="outline"
                      size="xs"
                      class="text-[9px] px-1 py-0 h-4 font-mono"
                      title="Menggunakan Custom SLD di luar bawaan GeoServer"
                    />
                  </div>
                  <span v-if="layer.protocol?.toLowerCase() === 'mvt'" class="text-[10px] text-blue-600 dark:text-blue-400 font-medium">
                    Vector Tile
                  </span>
                  <span v-else-if="layer.protocol?.toLowerCase() === 'wms'" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">
                    Raster WMS
                  </span>
                </div>
              </td>

              <!-- Opacity Indicator -->
              <td class="py-3 px-3">
                <div class="space-y-1">
                  <div class="flex items-center justify-between text-[11px] font-mono">
                    <span class="text-gray-500 dark:text-gray-400">Opacity</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">
                      {{ Math.round((layer.opacity ?? 1.0) * 100) }}%
                    </span>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                    <div
                      class="bg-blue-500 h-full rounded-full transition-all duration-300"
                      :style="{ width: `${Math.round((layer.opacity ?? 1.0) * 100)}%` }"
                    />
                  </div>
                </div>
              </td>

              <!-- Default Visible -->
              <td class="py-3 px-3 text-center">
                <UBadge
                  v-if="layer.default_visible"
                  label="Aktif"
                  color="info"
                  variant="subtle"
                  size="xs"
                />
                <span v-else class="text-gray-600 dark:text-gray-400 text-[11px]">Tidak</span>
              </td>

              <!-- Instant Toggle Active Switch -->
              <td class="py-3 px-3 text-center">
                <div class="inline-flex items-center justify-center">
                  <USwitch
                    :model-value="layer.is_active"
                    :disabled="!canEdit || togglingIds.has(layer.id)"
                    :aria-label="`Aktifkan layer ${layer.name}`"
                    @update:model-value="() => handleToggleActive(layer)"
                  />
                </div>
              </td>

              <!-- Actions -->
              <td v-if="hasAnyAction" class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <UTooltip v-if="canEdit" text="Edit, Custom Style & Metadata Layer">
                    <UButton
                      :to="`/admin/layers/${layer.id}`"
                      icon="i-heroicons-pencil"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      aria-label="Edit Layer"
                      class="hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
                    />
                  </UTooltip>
                  <UTooltip v-if="canDelete" text="Hapus Layer">
                    <UButton
                      icon="i-heroicons-trash"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      aria-label="Hapus Layer"
                      class="hover:text-red-600 dark:hover:text-red-400 cursor-pointer"
                      @click="confirmDelete(layer)"
                    />
                  </UTooltip>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="data?.meta && data.meta.total > 0"
        class="p-4 border-t border-gray-200 dark:border-white/[0.08] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400"
      >
        <div>
          Menampilkan baris {{ (data.meta.current_page - 1) * data.meta.per_page + 1 }} sampai
          {{ Math.min(data.meta.current_page * data.meta.per_page, data.meta.total) }} dari total
          <strong class="text-gray-800 dark:text-gray-200">{{ data.meta.total }}</strong> layer
        </div>
        <UPagination
          v-model:page="page"
          :total="data.meta.total"
          :items-per-page="data.meta.per_page"
          size="sm"
        />
      </div>
    </div>
    </div>

    <!-- Create / Edit Layer Modal -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? `Edit Layer: ${formState.name}` : 'Tambah Katalog Layer Baru'"
      :description="isEditing ? 'Perbarui informasi endpoint dan parameter rendering layer GIS.' : 'Daftarkan endpoint layer spasial baru ke dalam sistem katalog peta.'"
      :ui="{
        content: 'sm:max-w-3xl dark:bg-[#0b0f19] dark:border-white/[0.08]',
      }"
    >
      <template #body>
        <!-- Modal Tab Switcher -->
        <div class="flex items-center gap-1 p-1 bg-gray-100 dark:bg-white/[0.04] rounded-lg mb-4 text-xs font-semibold">
          <button
            type="button"
            class="flex-1 py-1.5 px-3 rounded-md transition-all cursor-pointer text-center flex items-center justify-center gap-1.5"
            :class="activeModalTab === 'info' ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
            @click="activeModalTab = 'info'"
          >
            <UIcon name="i-lucide-settings-2" class="size-4" />
            <span>Informasi & Parameter</span>
          </button>
          <button
            type="button"
            class="flex-1 py-1.5 px-3 rounded-md transition-all cursor-pointer text-center flex items-center justify-center gap-1.5"
            :class="activeModalTab === 'style' ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
            @click="activeModalTab = 'style'; hasOpenedStyleTab = true"
          >
            <UIcon name="i-lucide-palette" class="size-4" aria-hidden="true" />
            <span>{{ formState.protocol === 'wms' ? 'Custom Style (SLD)' : 'Styling Vektor (MVT)' }}</span>
          </button>
        </div>

        <div class="max-h-[72vh] overflow-y-auto pr-1">
          <!-- TAB 1: INFORMASI & ENDPOINT -->
          <div v-show="activeModalTab === 'info'" class="space-y-4">
            <!-- Layer Name Field -->
            <UFormField label="Nama Tampilan Layer" required :error="formErrors.name">
              <UInput
                v-model="formState.name"
                placeholder="Contoh: Batas Administrasi Desa Bojonegoro"
                class="w-full"
              />
            </UFormField>

            <!-- Protocol & Source Type Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <UFormField label="Protokol Layer" required :error="formErrors.protocol">
                <USelect
                  v-model="formState.protocol"
                  :items="formProtocolOptions"
                  class="w-full"
                />
              </UFormField>

              <UFormField label="Tipe Sumber Data" required :error="formErrors.source_type">
                <USelect
                  v-model="formState.source_type"
                  :items="formSourceTypeOptions"
                  class="w-full"
                />
              </UFormField>
            </div>

            <!-- Base URL Field -->
            <UFormField
              label="Base URL Service / Endpoint"
              required
              :error="formErrors.url"
              description="URL service GeoServer WMS, WFS, XYZ endpoint, atau berkas GeoJSON."
            >
              <UInput
                v-model="formState.url"
                placeholder="https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms"
                class="w-full font-mono text-xs"
              />
            </UFormField>

            <!-- Technical Layer Name -->
            <UFormField
              label="Nama Layer Teknis (Workspace:Layer)"
              :required="['wms', 'wfs'].includes(formState.protocol)"
              :error="formErrors.layer_name"
              description="Nama layer teknis pada GeoServer (contoh: palapa:ADMINISTRASIDESA_AR_10K_2019_BOJONEGORO)."
            >
              <UInput
                v-model="formState.layer_name"
                placeholder="palapa:ADMINISTRASIDESA_AR_10K_2019_BOJONEGORO"
                class="w-full font-mono text-xs"
              />
            </UFormField>

            <!-- Opacity Slider & Order Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <UFormField label="Transparansi Default (Opacity)" :error="formErrors.opacity">
                <div class="space-y-1.5 pt-1">
                  <div class="flex items-center justify-between text-xs font-mono">
                    <span>{{ Math.round(formState.opacity * 100) }}%</span>
                    <span class="text-gray-400">({{ formState.opacity.toFixed(2) }})</span>
                  </div>
                  <input
                    v-model.number="formState.opacity"
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    class="w-full accent-blue-600 cursor-pointer"
                  />
                </div>
              </UFormField>

              <UFormField
                label="Urutan Z-Index (Order)"
                :error="formErrors.order"
                description="Angka lebih tinggi ditampilkan di atas layer lain."
              >
                <UInput
                  v-model.number="formState.order"
                  type="number"
                  min="0"
                  class="w-full font-mono"
                />
              </UFormField>
            </div>

            <!-- Attribution & Description -->
            <UFormField label="Instansi / Atribusi Sumber Data">
              <UInput
                v-model="formState.attribution"
                placeholder="Contoh: Bappeda / Geoportal Kabupaten Bojonegoro"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Deskripsi Layer">
              <textarea
                v-model="formState.description"
                rows="2"
                placeholder="Deskripsi singkat konten atau batas wilayah layer..."
                class="w-full rounded-md border border-gray-300 dark:border-white/[0.1] bg-transparent p-2 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500"
              />
            </UFormField>

            <!-- Toggles: Active & Default Visible -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-gray-200 dark:border-white/[0.08]">
              <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02]">
                <div>
                  <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                    Aktifkan di Sistem
                  </span>
                  <span class="text-[11px] text-gray-500 dark:text-gray-400">
                    Layer tersedia untuk digunakan di peta
                  </span>
                </div>
                <USwitch v-model="formState.is_active" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02]">
                <div>
                  <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                    Centang Otomatis (Default)
                  </span>
                  <span class="text-[11px] text-gray-500 dark:text-gray-400">
                    Langsung aktif saat peta pertama kali dibuka
                  </span>
                </div>
                <USwitch v-model="formState.default_visible" />
              </div>

              <div
                v-if="String(formState.protocol || '').toUpperCase() === 'MVT' || formState.source_type === 'martin'"
                class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50/50 dark:bg-white/[0.02] space-y-2"
              >
                <div class="flex items-center justify-between gap-2">
                  <div>
                    <span class="text-xs font-semibold text-gray-900 dark:text-white block">
                      Minimal Zoom Tampil Layer (MVT)
                    </span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                      Layer hanya dirender dan dimuat pada halaman /maps saat zoom mencapai batas ini
                    </span>
                  </div>
                  <span class="px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-gray-200 dark:border-white/10 text-xs font-mono font-semibold text-gray-900 dark:text-white shrink-0">
                    {{ layerMinZoom > 0 ? `Zoom >= ${layerMinZoom}` : 'Semua Zoom' }}
                  </span>
                </div>
                <div class="flex items-center gap-3">
                  <input
                    v-model.number="layerMinZoom"
                    type="range"
                    min="0"
                    max="18"
                    step="0.5"
                    class="flex-1 accent-blue-600 cursor-pointer"
                  />
                  <button
                    v-if="layerMinZoom > 0"
                    type="button"
                    class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white cursor-pointer"
                    @click="layerMinZoom = 0"
                  >
                    Reset
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: STYLING VEKTOR (MVT) -->
          <div v-show="activeModalTab === 'style'" class="space-y-4">
            <LayerStyleEditor
              v-if="hasOpenedStyleTab"
              v-model="formState.style_config"
              :protocol="formState.protocol"
              :layer-id="editingLayerId"
              :layer-name="formState.layer_name || formState.name"
              :wms-url="formState.url"
            />
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="isModalOpen = false"
          />
          <UButton
            :label="isEditing ? 'Simpan Perubahan' : 'Tambah Layer'"
            color="primary"
            :loading="submitting"
            class="cursor-pointer"
            @click="handleSaveLayer"
          />
        </div>
      </template>
    </UModal>

    <!-- Delete Confirmation Modal -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Hapus Layer Peta"
      description="Apakah Anda yakin ingin menghapus layer ini dari sistem? Tindakan ini tidak dapat dibatalkan."
    >
      <template #body>
        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
          <p>
            Anda akan menghapus layer:
            <strong class="text-gray-900 dark:text-white font-semibold">
              {{ layerToDelete?.name }}
            </strong>
          </p>
          <div
            v-if="layerToDelete?.is_active"
            class="p-2.5 rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 text-amber-700 dark:text-amber-400 text-xs font-medium"
          >
            Peringatan: Layer ini saat ini berstatus aktif di dalam sistem. Peta yang mengandalkan layer ini tidak akan dapat memuatnya lagi.
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="isDeleteModalOpen = false"
          />
          <UButton
            label="Hapus Layer"
            color="error"
            :loading="deleting"
            class="cursor-pointer"
            @click="handleDeleteLayer"
          />
        </div>
      </template>
    </UModal>
    <!-- Geoportal CSW Sync Modal -->
    <UModal
      v-model:open="isSyncModalOpen"
      title="Sinkronisasi Katalog Geoportal Bojonegoro"
      description="Pemanenan metadata spasial resmi langsung dari layanan OGC CSW Geoportal Kabupaten Bojonegoro."
      :ui="{ content: 'sm:max-w-xl' }"
    >
      <template #body>
        <div class="space-y-4 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
          <div class="p-3.5 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/60 dark:bg-blue-950/20 text-blue-800 dark:text-blue-300 space-y-1.5">
            <div class="flex items-center gap-2 font-semibold">
              <UIcon name="i-lucide-globe-2" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
              <span>Simpul Jaringan Geoportal Daerah</span>
            </div>
            <p class="text-xs text-blue-700/90 dark:text-blue-300/80 leading-relaxed">
              Endpoint CSW: <code class="font-mono text-[11px] bg-blue-100 dark:bg-blue-900/40 px-1 py-0.5 rounded">https://geoportal.bojonegorokab.go.id/csw/</code>
            </p>
            <p class="text-xs text-blue-700/80 dark:text-blue-300/70 leading-relaxed">
              Sistem akan membaca seluruh metadata standar Dublin Core OGC CSW 2.0.2 dan memperbarui daftar layer tematik secara idempoten tanpa menghapus layer internal.
            </p>
          </div>

          <!-- Loading state -->
          <div v-if="syncingGeoportal" class="py-6 flex flex-col items-center justify-center space-y-3">
            <UIcon name="i-lucide-loader-2" class="size-8 text-blue-600 animate-spin" />
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
              Menghubungi endpoint CSW Geoportal & menguraikan rekaman XML...
            </p>
            <p class="text-[11px] text-gray-400">Proses ini membutuhkan waktu beberapa detik.</p>
          </div>

          <!-- Result KPI summary if completed -->
          <div v-else-if="syncResult" class="space-y-3">
            <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-semibold text-xs">
              <UIcon name="i-lucide-check-circle" class="size-4" />
              <span>Sinkronisasi Terakhir Berhasil Selesai!</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="p-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02] text-center">
                <span class="text-[10px] text-gray-500 block">Total Ditemukan</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ syncResult.records_found }}</span>
              </div>
              <div class="p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20 text-center">
                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 block">Baru Masuk</span>
                <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ syncResult.records_inserted }}</span>
              </div>
              <div class="p-2.5 rounded-lg border border-blue-200 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-950/20 text-center">
                <span class="text-[10px] text-blue-600 dark:text-blue-400 block">Diperbarui</span>
                <span class="text-lg font-bold text-blue-600 dark:text-blue-400 font-mono">{{ syncResult.records_updated }}</span>
              </div>
              <div class="p-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02] text-center">
                <span class="text-[10px] text-gray-500 block">Durasi</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ syncResult.duration_seconds }}s</span>
              </div>
            </div>
          </div>

          <div v-else class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <p>Jadwal sinkronisasi otomatis harian aktif pada pukul <strong>02:00 WIB</strong>.</p>
            <p>Klik tombol di bawah untuk menjalankan sinkronisasi manual sekarang.</p>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <UButton
            label="Riwayat Log"
            icon="i-lucide-history"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer"
            @click="() => { isSyncModalOpen = false; openSyncHistoryModal('geoportal'); }"
          />
          <div class="flex items-center gap-2">
            <UButton
              label="Batal"
              color="neutral"
              variant="ghost"
              :disabled="syncingGeoportal"
              class="cursor-pointer"
              @click="isSyncModalOpen = false"
            />
            <UButton
              label="Mulai Sinkronisasi"
              icon="i-lucide-refresh-cw"
              color="primary"
              :loading="syncingGeoportal"
              class="cursor-pointer"
              @click="handleSyncGeoportal"
            />
          </div>
        </div>
      </template>
    </UModal>

    <!-- Martin Tile Server Sync Modal -->
    <UModal
      v-model:open="isMartinSyncModalOpen"
      title="Sinkronisasi Katalog Martin Tile Server"
      description="Indeks otomatis sumber Vector Tiles (MVT) langsung dari katalog PostGIS Martin ke dalam daftar layer internal."
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div class="space-y-4 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
          <!-- Martin Info Banner -->
          <div class="p-3.5 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/60 dark:bg-blue-950/20 text-blue-800 dark:text-blue-300 space-y-1.5">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <div class="flex items-center gap-2 font-semibold">
                <UIcon name="i-lucide-server" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                <span>Endpoint Martin Vector Tile Server</span>
              </div>
              <UBadge
                :label="martinStatus?.status === 'online' ? 'Terhubung' : martinStatus?.status === 'fallback' ? 'Lokal Tersimpan' : 'Offline'"
                :color="martinStatus?.status === 'online' ? 'success' : martinStatus?.status === 'fallback' ? 'warning' : 'error'"
                variant="subtle"
                size="xs"
              />
            </div>
            <p class="text-xs text-blue-700/90 dark:text-blue-300/80 leading-relaxed">
              Katalog URL:
              <code class="font-mono text-[11px] bg-blue-100 dark:bg-blue-900/40 px-1.5 py-0.5 rounded">
                {{ martinStatus?.martin_url || 'http://localhost:9090' }}/catalog
              </code>
            </p>
            <p class="text-xs text-blue-700/80 dark:text-blue-300/70 leading-relaxed">
              Sinkronisasi berjalan idempoten: tabel spasial baru didaftarkan sebagai layer MVT dengan preset gaya awal, sementara kustomisasi nama, status aktif, urutan, dan style yang sudah diatur admin tetap dipertahankan.
            </p>
          </div>

          <!-- Catalog Preview Loading -->
          <div v-if="loadingMartinCatalog" class="py-8 flex flex-col items-center justify-center space-y-2.5">
            <UIcon name="i-lucide-loader-2" class="size-7 text-blue-600 animate-spin" />
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
              Membaca daftar sumber vektor dari Martin Tile Server...
            </p>
          </div>

          <!-- Catalog Preview Error -->
          <div
            v-else-if="martinCatalogError"
            class="p-4 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/70 dark:bg-red-950/20 text-xs text-red-700 dark:text-red-300 space-y-3"
          >
            <div class="flex items-start gap-2.5">
              <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0 mt-0.5 text-red-600 dark:text-red-400" />
              <div class="space-y-1">
                <p class="font-semibold text-red-800 dark:text-red-200">Katalog Martin Tidak Dapat Diakses</p>
                <p>{{ martinCatalogError }}</p>
              </div>
            </div>
            <div class="flex justify-end">
              <UButton
                label="Coba Lagi"
                icon="i-heroicons-arrow-path"
                size="xs"
                color="error"
                variant="outline"
                class="cursor-pointer"
                @click="loadMartinCatalog"
              />
            </div>
          </div>

          <!-- Catalog Empty State -->
          <div
            v-else-if="martinCatalogItems.length === 0"
            class="py-8 text-center text-xs text-gray-500 dark:text-gray-400 border border-dashed border-gray-200 dark:border-white/10 rounded-xl"
          >
            Tidak ada sumber tile vektor ditemukan pada katalog Martin saat ini.
          </div>

          <!-- Catalog Interactive Checklist -->
          <div v-else class="space-y-2.5">
            <div class="flex items-center justify-between gap-2 flex-wrap text-xs">
              <span class="font-semibold text-gray-800 dark:text-gray-200">
                Sumber Vektor Terdeteksi ({{ selectedMartinSources.length }} / {{ martinCatalogItems.length }} dipilih)
              </span>
              <div class="flex items-center gap-1.5 flex-wrap">
                <button
                  type="button"
                  class="px-2 py-1 rounded border border-gray-200 dark:border-white/10 text-[11px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
                  @click="selectMartinPreset('primary')"
                >
                  Utama
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded border border-gray-200 dark:border-white/10 text-[11px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
                  @click="selectMartinPreset('unsynced')"
                >
                  Belum Sinkron
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded border border-gray-200 dark:border-white/10 text-[11px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
                  @click="selectMartinPreset('all')"
                >
                  Semua
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded border border-gray-200 dark:border-white/10 text-[11px] font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] cursor-pointer"
                  @click="selectMartinPreset('none')"
                >
                  Kosongkan
                </button>
              </div>
            </div>

            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-white/[0.06] rounded-xl border border-gray-200 dark:border-white/[0.08]">
              <label
                v-for="item in martinCatalogItems"
                :key="item.source_id"
                class="flex items-start justify-between gap-3 p-2.5 text-xs hover:bg-gray-50/70 dark:hover:bg-white/[0.03] cursor-pointer"
              >
                <div class="flex items-start gap-2.5 min-w-0">
                  <input
                    type="checkbox"
                    :checked="selectedMartinSources.includes(item.source_id)"
                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    @change="toggleMartinSource(item.source_id)"
                  />
                  <div class="min-w-0 space-y-0.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="font-semibold text-gray-900 dark:text-white">
                        {{ item.local_name || item.suggested_name }}
                      </span>
                      <UBadge
                        :label="item.geom_type.toUpperCase()"
                        color="neutral"
                        variant="outline"
                        size="xs"
                        class="font-mono text-[9px]"
                      />
                      <UBadge
                        v-if="item.is_legacy"
                        label="Arsip"
                        color="warning"
                        variant="subtle"
                        size="xs"
                        class="text-[9px]"
                      />
                    </div>
                    <div class="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate">
                      {{ item.source_id }}
                      <span v-if="Object.keys(item.fields || {}).length > 0">
                        ({{ Object.keys(item.fields).length }} kolom)
                      </span>
                    </div>
                  </div>
                </div>

                <div class="shrink-0 pt-0.5">
                  <UBadge
                    :label="item.synced ? 'Tersinkron' : 'Baru'"
                    :color="item.synced ? 'success' : 'info'"
                    variant="subtle"
                    size="xs"
                    class="text-[10px]"
                  />
                </div>
              </label>
            </div>

            <label class="flex items-center gap-2 pt-1 text-xs text-gray-600 dark:text-gray-300 cursor-pointer select-none">
              <input
                v-model="martinDeactivateMissing"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
              />
              <span>Nonaktifkan layer Martin lokal yang sudah dihapus dari server (saat sinkron penuh)</span>
            </label>
          </div>

          <!-- Running Sync Progress -->
          <div v-if="syncingMartin" class="py-5 flex flex-col items-center justify-center space-y-2.5 border-t border-gray-200 dark:border-white/[0.08]">
            <UIcon name="i-lucide-loader-2" class="size-7 text-blue-600 animate-spin" />
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
              Menyinkronkan katalog sumber vektor & metadata TileJSON...
            </p>
          </div>

          <!-- Sync Result KPI Summary -->
          <div v-else-if="martinSyncResult" class="space-y-2.5 pt-2 border-t border-gray-200 dark:border-white/[0.08]">
            <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-semibold text-xs">
              <UIcon name="i-lucide-check-circle" class="size-4" />
              <span>Sinkronisasi Martin Tile Server Selesai</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="p-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02] text-center">
                <span class="text-[10px] text-gray-500 block">Diproses</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ martinSyncResult.records_found }}</span>
              </div>
              <div class="p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20 text-center">
                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 block">Baru Masuk</span>
                <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ martinSyncResult.records_inserted }}</span>
              </div>
              <div class="p-2.5 rounded-lg border border-blue-200 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-950/20 text-center">
                <span class="text-[10px] text-blue-600 dark:text-blue-400 block">Diperbarui</span>
                <span class="text-lg font-bold text-blue-600 dark:text-blue-400 font-mono">{{ martinSyncResult.records_updated }}</span>
              </div>
              <div class="p-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02] text-center">
                <span class="text-[10px] text-gray-500 block">Durasi</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ martinSyncResult.duration_seconds }}s</span>
              </div>
            </div>
          </div>

          <div v-else class="text-[11px] text-gray-500 dark:text-gray-400">
            Jadwal sinkronisasi otomatis Martin Tile Server aktif setiap hari pukul <strong>02:15 WIB</strong>.
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between w-full gap-2 flex-wrap">
          <UButton
            label="Riwayat Log"
            icon="i-lucide-history"
            size="xs"
            color="neutral"
            variant="outline"
            class="cursor-pointer"
            @click="() => { isMartinSyncModalOpen = false; openSyncHistoryModal('martin'); }"
          />
          <div class="flex items-center gap-2">
            <UButton
              label="Tutup"
              color="neutral"
              variant="ghost"
              :disabled="syncingMartin"
              class="cursor-pointer"
              @click="isMartinSyncModalOpen = false"
            />
            <UButton
              :label="selectedMartinSources.length > 0 ? `Sinkronkan (${selectedMartinSources.length})` : 'Mulai Sinkronisasi'"
              icon="i-lucide-database-zap"
              color="primary"
              :loading="syncingMartin"
              :disabled="syncingMartin || Boolean(martinCatalogError) || (!loadingMartinCatalog && martinCatalogItems.length > 0 && selectedMartinSources.length === 0)"
              class="cursor-pointer"
              @click="handleSyncMartin"
            />
          </div>
        </div>
      </template>
    </UModal>

    <!-- Unified Sync History Modal -->
    <UModal
      v-model:open="isSyncHistoryModalOpen"
      title="Riwayat Eksekusi Sinkronisasi Katalog"
      description="Audit log sinkronisasi otomatis dan manual dari Geoportal CSW maupun Martin Tile Server."
      :ui="{ content: 'sm:max-w-3xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div class="space-y-3">
          <div class="flex items-center gap-1.5 flex-wrap">
            <button
              v-for="opt in [
                { value: 'all', label: 'Semua Sumber' },
                { value: 'geoportal', label: 'Geoportal CSW' },
                { value: 'martin', label: 'Martin Tile Server' },
              ]"
              :key="opt.value"
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer border"
              :class="syncHistorySourceFilter === opt.value
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-gray-50 dark:bg-white/[0.03] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08] hover:bg-gray-100 dark:hover:bg-white/[0.06]'"
              @click="syncHistorySourceFilter = opt.value as 'all' | 'geoportal' | 'martin'"
            >
              {{ opt.label }}
            </button>
          </div>

          <div v-if="syncHistoryStatus === 'pending'" class="py-8 text-center text-xs text-gray-500">
            <UIcon name="i-lucide-loader-2" class="size-6 text-blue-600 animate-spin mx-auto mb-2" />
            <span>Memuat data riwayat sinkronisasi...</span>
          </div>

          <div
            v-else-if="!syncHistoryData?.data || syncHistoryData.data.length === 0"
            class="py-8 text-center text-xs text-gray-500 dark:text-gray-400"
          >
            Belum ada riwayat sinkronisasi yang tercatat untuk sumber ini.
          </div>

          <div v-else class="overflow-x-auto max-h-[55vh]">
            <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
              <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
                <tr>
                  <th scope="col" class="py-2.5 px-3">Waktu</th>
                  <th scope="col" class="py-2.5 px-3">Sumber</th>
                  <th scope="col" class="py-2.5 px-3">Pemicu</th>
                  <th scope="col" class="py-2.5 px-3 text-center">Status</th>
                  <th scope="col" class="py-2.5 px-3 text-center">Rekaman</th>
                  <th scope="col" class="py-2.5 px-3 text-right">Durasi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                <tr
                  v-for="log in syncHistoryData.data"
                  :key="log.id"
                  class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02]"
                >
                  <td class="py-2.5 px-3 font-mono text-[11px]">
                    {{ new Date(log.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) }}
                  </td>
                  <td class="py-2.5 px-3">
                    <UBadge
                      :label="isMartinSyncLog(log) ? 'Martin MVT' : 'Geoportal CSW'"
                      :color="isMartinSyncLog(log) ? 'primary' : 'info'"
                      size="xs"
                      variant="subtle"
                      class="text-[10px]"
                    />
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="font-medium">{{ formatTriggerLabel(log.triggered_by) }}</span>
                    <span v-if="log.user?.name" class="text-[10px] text-gray-500 dark:text-gray-400 block">oleh {{ log.user.name }}</span>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <UBadge
                      :label="log.status.toUpperCase()"
                      :color="log.status === 'success' ? 'success' : log.status === 'failed' ? 'error' : 'warning'"
                      size="xs"
                      variant="subtle"
                      class="font-mono text-[10px]"
                      :title="log.error_message || undefined"
                    />
                  </td>
                  <td class="py-2.5 px-3 text-center font-mono text-[11px]">
                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">+{{ log.records_inserted }}</span> /
                    <span class="text-blue-600 dark:text-blue-400 font-semibold">~{{ log.records_updated }}</span>
                    <span class="text-gray-500 dark:text-gray-400 block text-[10px]">Total: {{ log.records_found }}</span>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono text-[11px]">
                    {{ log.duration_seconds }}s
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-between items-center w-full">
          <UButton
            icon="i-heroicons-arrow-path"
            size="xs"
            color="neutral"
            variant="outline"
            :loading="syncHistoryStatus === 'pending'"
            class="cursor-pointer"
            @click="() => refreshSyncHistory()"
          >
            Muat Ulang
          </UButton>
          <UButton
            label="Tutup"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="isSyncHistoryModalOpen = false"
          />
        </div>
      </template>
    </UModal>

    <!-- Layer Metadata Modal (Geoportal CSW & Martin TileJSON) -->
    <UModal
      v-model:open="isMetadataModalOpen"
      :title="selectedGeoportalLayer?.name || 'Metadata Layer Spasial'"
      :description="isMartinLayerItem(selectedGeoportalLayer) ? 'Rincian identitas Vector Tiles (MVT) dan skema kolom dari Martin Tile Server.' : 'Rincian informasi Dublin Core OGC CSW resmi dari Geoportal Bojonegoro.'"
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div v-if="selectedGeoportalLayer" class="space-y-4 max-h-[70vh] overflow-y-auto text-xs pr-1">
          <!-- Overview Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] space-y-1">
              <span class="text-[10px] font-semibold text-gray-500 uppercase">Kategori / Urusan</span>
              <p class="font-medium text-gray-900 dark:text-white">
                {{ selectedGeoportalLayer.category || 'Belum Terkategori' }}
              </p>
            </div>
            <div class="p-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] space-y-1">
              <span class="text-[10px] font-semibold text-gray-500 uppercase">Wali Data / Instansi</span>
              <p class="font-medium text-gray-900 dark:text-white">
                {{ selectedGeoportalLayer.opd_name || selectedGeoportalLayer.attribution || '-' }}
              </p>
            </div>
          </div>

          <!-- Description / Abstract -->
          <div class="p-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-1">
            <span class="text-[10px] font-semibold text-gray-500 uppercase">Abstrak / Deskripsi</span>
            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
              {{ selectedGeoportalLayer.description || 'Tidak ada abstrak yang disertakan dalam rekaman katalog.' }}
            </p>
          </div>

          <!-- Technical Details -->
          <div class="p-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2">
            <span class="text-[10px] font-semibold text-gray-500 uppercase block">Identitas Teknis Service</span>
            <div class="space-y-1.5 font-mono text-[11px]">
              <div class="flex items-start justify-between gap-2 border-b border-gray-100 dark:border-white/5 pb-1">
                <span class="text-gray-500 dark:text-gray-400">Protokol:</span>
                <span class="font-bold text-blue-600 dark:text-blue-400">{{ selectedGeoportalLayer.protocol.toUpperCase() }}</span>
              </div>
              <div class="flex items-start justify-between gap-2 border-b border-gray-100 dark:border-white/5 pb-1">
                <span class="text-gray-500 dark:text-gray-400">{{ isMartinLayerItem(selectedGeoportalLayer) ? 'Source Table:' : 'Layer Name:' }}</span>
                <span class="text-gray-800 dark:text-gray-200 truncate max-w-sm" :title="selectedGeoportalLayer.layer_name || ''">
                  {{ selectedGeoportalLayer.layer_name || '-' }}
                </span>
              </div>
              <div
                v-if="isMartinLayerItem(selectedGeoportalLayer)"
                class="flex items-start justify-between gap-2 border-b border-gray-100 dark:border-white/5 pb-1"
              >
                <span class="text-gray-500 dark:text-gray-400">Geometri:</span>
                <span class="text-gray-800 dark:text-gray-200 uppercase">
                  {{ selectedGeoportalLayer.csw_metadata?.geom_type || selectedGeoportalLayer.style_config?.geom_type || 'vektor' }}
                </span>
              </div>
              <div
                v-else
                class="flex items-start justify-between gap-2 border-b border-gray-100 dark:border-white/5 pb-1"
              >
                <span class="text-gray-500 dark:text-gray-400">CSW Identifier:</span>
                <span class="text-gray-800 dark:text-gray-200 truncate max-w-sm">
                  {{ selectedGeoportalLayer.geoportal_id || '-' }}
                </span>
              </div>
              <div class="flex items-start justify-between gap-2 pt-0.5">
                <span class="text-gray-500 dark:text-gray-400">{{ isMartinLayerItem(selectedGeoportalLayer) ? 'Tile Endpoint:' : 'WMS Endpoint:' }}</span>
                <span class="text-gray-600 dark:text-gray-300 truncate max-w-sm" :title="selectedGeoportalLayer.url">
                  {{ selectedGeoportalLayer.url }}
                </span>
              </div>
            </div>
          </div>

          <!-- Detected TileJSON Fields (Martin MVT) -->
          <div
            v-if="isMartinLayerItem(selectedGeoportalLayer) && selectedGeoportalLayer.csw_metadata?.fields && Object.keys(selectedGeoportalLayer.csw_metadata.fields).length > 0"
            class="p-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2"
          >
            <span class="text-[10px] font-semibold text-gray-500 uppercase block">
              Atribut Vektor Terindeks ({{ Object.keys(selectedGeoportalLayer.csw_metadata.fields).length }})
            </span>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="(colType, colName) in selectedGeoportalLayer.csw_metadata.fields"
                :key="colName"
                class="px-2 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06] border border-gray-200 dark:border-white/10 font-mono text-[10px] text-gray-700 dark:text-gray-200"
              >
                {{ colName }} <span class="text-gray-500 dark:text-gray-400">({{ colType }})</span>
              </span>
            </div>
          </div>

          <!-- Legend Preview -->
          <div v-if="selectedGeoportalLayer.legend_url" class="p-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#0b0f19] space-y-2">
            <span class="text-[10px] font-semibold text-gray-500 uppercase block">Pratinjau Simbol Legenda Resmi</span>
            <div class="p-2 rounded-lg bg-gray-50 dark:bg-white/[0.02] border border-gray-100 dark:border-white/5 inline-block">
              <img
                :src="selectedGeoportalLayer.legend_url"
                alt="Legenda Resmi Geoportal"
                class="max-h-32 object-contain"
                loading="lazy"
                onerror="this.style.display='none'"
              />
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end w-full">
          <UButton
            label="Tutup"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="isMetadataModalOpen = false"
          />
        </div>
      </template>
    </UModal>

    <!-- Dialog Status Layanan Simpul Geoportal & Martin Tile Server -->
    <UModal
      v-model:open="isServiceStatusModalOpen"
      title="Status Layanan Simpul JIGN &amp; Martin"
      description="Pantau konektivitas layanan CSW/WMS Geoportal Bojonegoro dan Martin Vector Tile Server serta jalankan sinkronisasi."
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div class="space-y-4">
          <!-- Geoportal Bojonegoro -->
          <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-white/[0.02] space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
              <div class="space-y-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="size-2.5 rounded-full shrink-0"
                    :class="[
                      geoportalStatus?.status === 'online'
                        ? 'bg-emerald-500'
                        : geoportalStatus?.status === 'fallback'
                        ? 'bg-amber-500'
                        : 'bg-red-500'
                    ]"
                  />
                  <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                    Simpul Geoportal Bojonegoro (JIGN)
                  </h3>
                  <span
                    class="text-[11px] font-semibold px-2 py-0.5 rounded-md tabular-nums"
                    :class="[
                      geoportalStatus?.status === 'online'
                        ? 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300'
                        : geoportalStatus?.status === 'fallback'
                        ? 'bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300'
                        : 'bg-red-100 dark:bg-red-950/70 text-red-800 dark:text-red-300'
                    ]"
                  >
                    {{
                      checkingGeoportalStatus
                        ? 'Memeriksa...'
                        : geoportalStatus?.status === 'online'
                        ? `Terhubung${geoportalStatus?.response_time_ms ? ` · ${geoportalStatus.response_time_ms} ms` : ''}`
                        : geoportalStatus?.status === 'fallback'
                        ? 'Data Cadangan Aktif'
                        : 'Koneksi Terputus'
                    }}
                  </span>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-300 tabular-nums">
                  {{ geoportalStatus?.remote_matched ?? geoportalStatus?.total_local_layers ?? 0 }} layer terindeks · Sinkron terakhir: {{ formatLastSync(geoportalStatus?.last_synced_at) }} ({{ timeAgo(geoportalStatus?.last_synced_at) }})
                </p>
                <a
                  :href="geoportalStatus?.csw_url || 'https://geoportal.bojonegorokab.go.id/csw/'"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 font-mono text-[11px] text-blue-600 dark:text-blue-400 hover:underline break-all"
                >
                  <span>{{ geoportalStatus?.csw_url || 'https://geoportal.bojonegorokab.go.id/csw/' }}</span>
                  <UIcon name="i-lucide-external-link" class="size-3 shrink-0" aria-hidden="true" />
                </a>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <UButton
                  label="Cek Status"
                  icon="i-heroicons-arrow-path"
                  color="neutral"
                  variant="outline"
                  size="xs"
                  :loading="checkingGeoportalStatus"
                  class="min-h-[36px] cursor-pointer"
                  @click="refreshGeoportalStatus(true)"
                />
                <UButton
                  v-if="canSyncGeoportal"
                  label="Sync CSW"
                  icon="i-lucide-refresh-cw"
                  color="primary"
                  size="xs"
                  :loading="syncingGeoportal"
                  class="min-h-[36px] cursor-pointer font-semibold"
                  @click="isServiceStatusModalOpen = false; openSyncModal()"
                />
              </div>
            </div>

            <div
              v-if="geoportalStatus?.status === 'fallback'"
              class="pt-2 border-t border-amber-200/70 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2"
            >
              <UIcon name="i-lucide-alert-triangle" class="size-4 shrink-0" aria-hidden="true" />
              <span>Server Geoportal sedang lambat; menampilkan {{ geoportalStatus?.total_local_layers ?? 0 }} layer dari data cadangan lokal.</span>
            </div>
          </div>

          <!-- Martin Vector Tile Server -->
          <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-white/[0.02] space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
              <div class="space-y-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="size-2.5 rounded-full shrink-0"
                    :class="[
                      martinStatus?.status === 'online'
                        ? 'bg-blue-500'
                        : martinStatus?.status === 'fallback'
                        ? 'bg-amber-500'
                        : 'bg-red-500'
                    ]"
                  />
                  <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                    Martin Tile Server (Vector Tiles MVT)
                  </h3>
                  <span
                    class="text-[11px] font-semibold px-2 py-0.5 rounded-md tabular-nums"
                    :class="[
                      martinStatus?.status === 'online'
                        ? 'bg-blue-100 dark:bg-blue-950/70 text-blue-800 dark:text-blue-300'
                        : martinStatus?.status === 'fallback'
                        ? 'bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300'
                        : 'bg-red-100 dark:bg-red-950/70 text-red-800 dark:text-red-300'
                    ]"
                  >
                    {{
                      checkingMartinStatus
                        ? 'Memeriksa...'
                        : martinStatus?.status === 'online'
                        ? `Terhubung${martinStatus?.response_time_ms !== null && martinStatus?.response_time_ms !== undefined ? ` · ${martinStatus.response_time_ms} ms` : ''}`
                        : martinStatus?.status === 'fallback'
                        ? 'Tersimpan Lokal'
                        : 'Server Offline'
                    }}
                  </span>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-300 tabular-nums">
                  {{ martinStatus?.remote_sources ?? martinStatus?.total_local_layers ?? 0 }} sumber MVT · Sinkron terakhir: {{ formatLastSync(martinStatus?.last_synced_at) }} ({{ timeAgo(martinStatus?.last_synced_at) }})
                </p>
                <code class="inline-block font-mono text-[11px] text-gray-700 dark:text-gray-300 bg-white dark:bg-white/[0.06] px-2 py-0.5 rounded border border-gray-200 dark:border-white/[0.08] break-all">
                  {{ martinStatus?.martin_url || 'http://localhost:9090' }}/catalog
                </code>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <UButton
                  label="Cek Status"
                  icon="i-heroicons-arrow-path"
                  color="neutral"
                  variant="outline"
                  size="xs"
                  :loading="checkingMartinStatus"
                  class="min-h-[36px] cursor-pointer"
                  @click="refreshMartinStatus(true)"
                />
                <UButton
                  v-if="canSyncGeoportal"
                  label="Sync MVT"
                  icon="i-lucide-database-zap"
                  color="primary"
                  size="xs"
                  :loading="syncingMartin"
                  class="min-h-[36px] cursor-pointer font-semibold"
                  @click="isServiceStatusModalOpen = false; openMartinSyncModal()"
                />
              </div>
            </div>

            <div
              v-if="martinStatus?.status === 'fallback'"
              class="pt-2 border-t border-amber-200/70 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2"
            >
              <UIcon name="i-lucide-alert-triangle" class="size-4 shrink-0" aria-hidden="true" />
              <span>Martin Tile Server tidak merespons; tersimpan {{ martinStatus?.total_local_layers ?? 0 }} layer internal di basis data.</span>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between w-full gap-2">
          <UButton
            v-if="canSyncGeoportal"
            label="Lihat Riwayat Sync"
            icon="i-lucide-history"
            color="neutral"
            variant="outline"
            size="xs"
            class="cursor-pointer"
            @click="isServiceStatusModalOpen = false; openSyncHistoryModal('all')"
          />
          <UButton
            label="Tutup"
            color="neutral"
            variant="ghost"
            class="cursor-pointer ml-auto"
            @click="isServiceStatusModalOpen = false"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

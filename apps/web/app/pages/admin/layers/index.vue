<script lang="ts" setup>
definePageMeta({
  middleware: ["auth", "permission"],
  permission: "layers-view",
});

useSeoMeta({
  title: "Manajemen Layer | Melarosa GIS",
});

interface LayerItem {
  id: string;
  name: string;
  protocol: string;
  url: string;
  layer_name: string | null;
  is_active: boolean;
  default_visible: boolean;
  opacity: number;
  order: number;
  attribution: string | null;
  description: string | null;
  source_type: string;
  is_synced: boolean;
  created_at: string;
  updated_at: string;
}

interface LayersResponse {
  ok: boolean;
  summary: {
    total: number;
    active: number;
    default_visible: number;
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
const hasAnyAction = computed(() => canEdit.value || canDelete.value);

// ─── Filter & State ─────────────────────────────────────────────────────────
const search = ref("");
const protocolFilter = ref<string>("ALL");
const sourceTypeFilter = ref<string>("ALL");
const statusFilter = ref<string>("ALL");
const defaultVisibleFilter = ref<string>("ALL");
const page = ref(1);
const perPage = ref(15);

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
  { label: "Semua Sumber Data", value: "ALL" },
  { label: "GeoServer", value: "geoserver" },
  { label: "External WMS / GIS", value: "external_wms" },
  { label: "XYZ Tiles Provider", value: "xyz_tiles" },
  { label: "Local GeoJSON", value: "local_geojson" },
  { label: "Basemap Provider", value: "basemap" },
];

const formSourceTypeOptions = [
  { label: "GeoServer (Internal / Palapa)", value: "geoserver" },
  { label: "External WMS / GIS Server", value: "external_wms" },
  { label: "XYZ Tiles Provider", value: "xyz_tiles" },
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
});

const formErrors = reactive<Record<string, string>>({});

function openCreateModal() {
  if (!canCreate.value) return;
  isEditing.value = false;
  editingLayerId.value = null;

  // Next order calculation
  const currentLayers = data.value?.data || [];
  const maxOrder = currentLayers.reduce((max, l) => Math.max(max, l.order ?? 0), -1);

  formState.name = "";
  formState.protocol = "wms";
  formState.source_type = "geoserver";
  formState.url = "https://geoportal.bojonegorokab.go.id/geoserver/palapa/wms";
  formState.layer_name = "";
  formState.opacity = 1.0;
  formState.order = maxOrder + 1;
  formState.attribution = "Geoportal Bojonegoro";
  formState.description = "";
  formState.is_active = true;
  formState.default_visible = false;

  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  isModalOpen.value = true;
}

function openEditModal(layer: LayerItem) {
  if (!canEdit.value) return;
  isEditing.value = true;
  editingLayerId.value = layer.id;

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

  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  isModalOpen.value = true;
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

// Helpers for badges and labels
function getProtocolBadgeColor(protocol: string): "success" | "info" | "warning" | "primary" | "neutral" {
  switch (protocol.toLowerCase()) {
    case "wms":
      return "success";
    case "wfs":
      return "info";
    case "xyz":
      return "warning";
    case "mvt":
      return "primary";
    default:
      return "neutral";
  }
}

function getSourceTypeLabel(type: string): string {
  switch (type) {
    case "geoserver":
      return "GeoServer";
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
</script>

<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-layers" class="size-6 text-blue-600 dark:text-blue-400" />
          <h1 class="text-xl font-bold text-gray-900 dark:text-white">
            Manajemen Layer Peta
          </h1>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          Katalog data spasial WMS, WFS, XYZ, dan Vector Tiles untuk visualisasi peta sistem.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          v-if="canCreate"
          label="Tambah Layer"
          icon="i-heroicons-plus"
          color="primary"
          class="cursor-pointer"
          @click="openCreateModal"
        />
        <UButton
          icon="i-heroicons-arrow-path"
          color="neutral"
          variant="outline"
          :loading="loading"
          class="cursor-pointer"
          @click="() => refresh()"
        />
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Katalog Layer</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
            {{ data?.summary?.total ?? 0 }}
          </p>
        </div>
        <div class="p-2.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
          <UIcon name="i-lucide-layers" class="size-5" />
        </div>
      </div>

      <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Layer Aktif di Sistem</p>
          <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
            {{ data?.summary?.active ?? 0 }}
          </p>
        </div>
        <div class="p-2.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
          <UIcon name="i-lucide-check-circle" class="size-5" />
        </div>
      </div>

      <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Default Aktif saat Peta Dibuka</p>
          <p class="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
            {{ data?.summary?.default_visible ?? 0 }}
          </p>
        </div>
        <div class="p-2.5 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400">
          <UIcon name="i-lucide-eye" class="size-5" />
        </div>
      </div>
    </div>

    <!-- Error Alert -->
    <div
      v-if="error"
      class="p-4 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 flex items-center justify-between"
    >
      <div class="flex items-center gap-3 text-sm">
        <UIcon name="i-heroicons-exclamation-triangle" class="size-5 flex-shrink-0" />
        <span>Gagal memuat katalog layer. Pastikan koneksi backend tersedia.</span>
      </div>
      <UButton
        label="Coba Lagi"
        size="xs"
        color="error"
        variant="subtle"
        @click="() => refresh()"
      />
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        <!-- Search Input -->
        <div class="sm:col-span-2 md:col-span-1">
          <UInput
            v-model="search"
            icon="i-heroicons-magnifying-glass"
            placeholder="Cari nama layer atau atribusi..."
            class="w-full"
            clearable
          />
        </div>

        <!-- Protocol Filter -->
        <div>
          <USelect
            v-model="protocolFilter"
            :items="protocolOptions"
            class="w-full"
          />
        </div>

        <!-- Source Type Filter -->
        <div>
          <USelect
            v-model="sourceTypeFilter"
            :items="sourceTypeOptions"
            class="w-full"
          />
        </div>

        <!-- Status Filter -->
        <div>
          <USelect
            v-model="statusFilter"
            :items="statusOptions"
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
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Tidak ada data layer</p>
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

      <!-- Table Content -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-3 w-16 text-center">Urutan</th>
              <th scope="col" class="py-3 px-4">Nama & Identitas Teknis</th>
              <th scope="col" class="py-3 px-3">Protokol & Sumber</th>
              <th scope="col" class="py-3 px-3 w-32">Transparansi</th>
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
                <div class="flex items-center justify-center gap-1">
                  <span class="font-mono font-medium text-gray-700 dark:text-gray-300">
                    #{{ layer.order }}
                  </span>
                  <div v-if="canEdit" class="flex flex-col">
                    <button
                      type="button"
                      :disabled="index === 0 || reordering"
                      class="text-gray-400 hover:text-gray-700 dark:hover:text-white disabled:opacity-20 cursor-pointer"
                      title="Naikkan urutan z-index"
                      @click="moveLayer(layer, 'up')"
                    >
                      <UIcon name="i-heroicons-chevron-up" class="size-3.5" />
                    </button>
                    <button
                      type="button"
                      :disabled="index === data.data.length - 1 || reordering"
                      class="text-gray-400 hover:text-gray-700 dark:hover:text-white disabled:opacity-20 cursor-pointer"
                      title="Turunkan urutan z-index"
                      @click="moveLayer(layer, 'down')"
                    >
                      <UIcon name="i-heroicons-chevron-down" class="size-3.5" />
                    </button>
                  </div>
                </div>
              </td>

              <!-- Name & Technical layer_name -->
              <td class="py-3 px-4">
                <div class="space-y-0.5">
                  <div class="font-semibold text-gray-900 dark:text-white text-sm">
                    {{ layer.name }}
                  </div>
                  <div v-if="layer.layer_name" class="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-md">
                    {{ layer.layer_name }}
                  </div>
                  <div v-if="layer.attribution" class="text-[11px] text-gray-400 flex items-center gap-1">
                    <UIcon name="i-lucide-copyright" class="size-3" />
                    <span>{{ layer.attribution }}</span>
                  </div>
                </div>
              </td>

              <!-- Protocol & Source -->
              <td class="py-3 px-3">
                <div class="flex flex-col items-start gap-1">
                  <UBadge
                    :label="layer.protocol.toUpperCase()"
                    :color="getProtocolBadgeColor(layer.protocol)"
                    variant="subtle"
                    size="xs"
                    class="font-mono uppercase font-bold"
                  />
                  <span class="text-[10px] text-gray-400">
                    {{ getSourceTypeLabel(layer.source_type) }}
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
                <span v-else class="text-gray-400 text-[11px]">Tidak</span>
              </td>

              <!-- Instant Toggle Active Switch -->
              <td class="py-3 px-3 text-center">
                <div class="inline-flex items-center justify-center">
                  <USwitch
                    :model-value="layer.is_active"
                    :disabled="!canEdit || togglingIds.has(layer.id)"
                    @update:model-value="() => handleToggleActive(layer)"
                  />
                </div>
              </td>

              <!-- Actions -->
              <td v-if="hasAnyAction" class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <UTooltip v-if="canEdit" text="Edit Konfigurasi Layer">
                    <UButton
                      icon="i-heroicons-pencil"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      class="hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
                      @click="openEditModal(layer)"
                    />
                  </UTooltip>
                  <UTooltip v-if="canDelete" text="Hapus Layer">
                    <UButton
                      icon="i-heroicons-trash"
                      size="xs"
                      color="neutral"
                      variant="ghost"
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

    <!-- Create / Edit Layer Modal -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? `Edit Layer: ${formState.name}` : 'Tambah Katalog Layer Baru'"
      :description="isEditing ? 'Perbarui informasi endpoint dan parameter rendering layer GIS.' : 'Daftarkan endpoint layer spasial baru ke dalam sistem katalog peta.'"
      :ui="{
        content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]',
      }"
    >
      <template #body>
        <div class="space-y-4 max-h-[75vh] overflow-y-auto pr-1">
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
          <UFormField label="Hak Cipta / Atribusi Sumber Data">
            <UInput
              v-model="formState.attribution"
              placeholder="Contoh: Geoportal Kabupaten Bojonegoro"
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
  </div>
</template>

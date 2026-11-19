<script lang="ts" setup>
import {
  type LayerItem,
  getProtocolBadgeColor,
  getLayerSourceGroup,
} from "~/utils/layer-meta";

interface StackResponse {
  ok: boolean;
  summary: {
    total: number;
    active: number;
    inactive: number;
    default_visible: number;
  };
  data: LayerItem[];
}

interface BatchLayerPayloadItem {
  id: string;
  order: number;
  is_active: boolean;
  default_visible: boolean;
  opacity: number;
}

const props = defineProps<{
  canEdit: boolean;
}>();

const emit = defineEmits<{
  (e: "saved"): void;
  (e: "open-catalog"): void;
  (e: "dirty-change", isDirty: boolean): void;
}>();

const toast = useToast();

const pending = ref(true);
const loadError = ref<string | null>(null);
const saving = ref(false);

const masterLayers = ref<LayerItem[]>([]);
// Strictly layers currently active AND visible on /maps (ActiveLayersView.vue)
const activeStack = ref<LayerItem[]>([]);
// Remaining layers in catalog/system (either catalog-only default_visible=false or inactive)
const availableCatalogLayers = ref<LayerItem[]>([]);

// Dual-list selection states
const selectedLeftIds = ref<string[]>([]);
const selectedRightIds = ref<string[]>([]);
const expandedLayerIds = ref<string[]>([]);

// Left panel filters (All layers list)
const catalogSearch = ref("");
const catalogSourceFilter = ref<"ALL" | "geoportal" | "internal" | "external">("ALL");
const catalogAvailabilityFilter = ref<"all" | "not_in_maps" | "in_maps">("all");

// Right panel filter (Active /maps stack)
const stackSearch = ref("");

// Mobile/tablet segmented pane switcher (< lg)
const mobilePane = ref<"active" | "all">("active");

const catalogAvailabilityOptions = computed(() => [
  { label: `Semua (${catalogCounts.value.all})`, value: "all" },
  { label: `Belum di /maps (${catalogCounts.value.notInMaps})`, value: "not_in_maps" },
  { label: `Aktif /maps (${catalogCounts.value.inMaps})`, value: "in_maps" },
]);

const catalogSourceOptions = [
  { label: "Semua Sumber", value: "ALL" },
  { label: "Geoportal WMS", value: "geoportal" },
  { label: "Internal MVT", value: "internal" },
  { label: "External WMS", value: "external" },
];

// Drag and touch reorder states (aligned with /maps ActiveLayersView.vue)
const draggedLayerIndex = ref<number | null>(null);
const recentlyMovedLayerId = ref<string | null>(null);
const isTouchDevice = ref(false);
const isTouchDragging = ref(false);
let touchCurrentIndex = -1;

onMounted(() => {
  if (typeof window !== "undefined") {
    isTouchDevice.value =
      "ontouchstart" in window ||
      navigator.maxTouchPoints > 0 ||
      (window.matchMedia && window.matchMedia("(pointer: coarse)").matches);
  }
  fetchStack();
});

function roundOpacity(val?: number | null): number {
  const num = typeof val === "number" && !Number.isNaN(val) ? val : 1;
  return Math.max(0, Math.min(1, Math.round(num * 100) / 100));
}

// /maps ActiveLayersView.vue only loads layers where BOTH is_active and default_visible are true
function isMapsActive(layer: LayerItem): boolean {
  return Boolean(layer.is_active) && Boolean(layer.default_visible);
}

function hydrateDraftFromMaster(list: LayerItem[]) {
  const cloned = list.map((item) => ({
    ...item,
    is_active: Boolean(item.is_active),
    default_visible: Boolean(item.is_active) ? Boolean(item.default_visible) : false,
    opacity: roundOpacity(item.opacity),
    order: Number(item.order ?? 0),
  }));

  const mapsActives = cloned
    .filter((item) => isMapsActive(item))
    .sort((a, b) => {
      if (b.order !== a.order) return b.order - a.order;
      return a.name.localeCompare(b.name, "id");
    });

  const nonMapsActives = cloned
    .filter((item) => !isMapsActive(item))
    .sort((a, b) => a.name.localeCompare(b.name, "id"));

  activeStack.value = mapsActives;
  availableCatalogLayers.value = nonMapsActives;
  selectedLeftIds.value = [];
  selectedRightIds.value = [];
}

async function fetchStack() {
  pending.value = true;
  loadError.value = null;
  try {
    const res = await $http<StackResponse>("admin/layers/stack");
    const list = Array.isArray(res?.data) ? res.data : [];
    masterLayers.value = list;
    hydrateDraftFromMaster(list);
  } catch (err: any) {
    loadError.value =
      err?.data?.message ||
      err?.message ||
      "Gagal memuat daftar layer aktif dan urutan tumpukan peta.";
  } finally {
    pending.value = false;
  }
}

// Active lookup for badges in left panel ("Semua Layer")
const activeRankById = computed(() => {
  const map = new Map<string, number>();
  activeStack.value.forEach((layer, idx) => {
    map.set(layer.id, idx + 1);
  });
  return map;
});

const activeIdSet = computed(() => new Set(activeStack.value.map((layer) => layer.id)));
const catalogOnlyIdSet = computed(() => new Set(availableCatalogLayers.value.map((layer) => layer.id)));

function getAssignedOrder(stackIndex: number): number {
  return Math.max(1, activeStack.value.length - stackIndex);
}

function getAssignedZIndex(stackIndex: number): number {
  return 10 + getAssignedOrder(stackIndex) * 10;
}

// Compute exact diff against fetched master snapshot
const changedPayloadItems = computed<BatchLayerPayloadItem[]>(() => {
  const originalMap = new Map(masterLayers.value.map((item) => [item.id, item]));
  const totalActive = activeStack.value.length;
  const changes: BatchLayerPayloadItem[] = [];

  activeStack.value.forEach((layer, idx) => {
    const targetOrder = totalActive - idx;
    const targetOpacity = roundOpacity(layer.opacity);
    const orig = originalMap.get(layer.id);

    if (
      !orig ||
      Boolean(orig.is_active) !== true ||
      Boolean(orig.default_visible) !== true ||
      Number(orig.order ?? 0) !== targetOrder ||
      roundOpacity(orig.opacity) !== targetOpacity
    ) {
      changes.push({
        id: layer.id,
        order: targetOrder,
        is_active: true,
        default_visible: true,
        opacity: targetOpacity,
      });
    }
  });

  availableCatalogLayers.value.forEach((layer) => {
    const orig = originalMap.get(layer.id);
    const targetActive = Boolean(layer.is_active);
    const targetVisible = false;
    const targetOrder = Number(layer.order ?? 0);
    const targetOpacity = roundOpacity(layer.opacity);

    if (
      !orig ||
      Boolean(orig.is_active) !== targetActive ||
      Boolean(orig.default_visible) !== targetVisible ||
      Number(orig.order ?? 0) !== targetOrder ||
      roundOpacity(orig.opacity) !== targetOpacity
    ) {
      changes.push({
        id: layer.id,
        order: targetOrder,
        is_active: targetActive,
        default_visible: false,
        opacity: targetOpacity,
      });
    }
  });

  return changes;
});

const changedLayerIdSet = computed(
  () => new Set(changedPayloadItems.value.map((item) => item.id)),
);

const hasUnsavedChanges = computed(() => changedPayloadItems.value.length > 0);

watch(
  hasUnsavedChanges,
  (val) => {
    emit("dirty-change", val);
  },
  { immediate: true },
);

// Left Panel: All layers sorted alphabetically
const allDraftLayersSorted = computed(() => {
  return [...activeStack.value, ...availableCatalogLayers.value].sort((a, b) =>
    a.name.localeCompare(b.name, "id"),
  );
});

const catalogCounts = computed(() => {
  const all = allDraftLayersSorted.value;
  return {
    all: all.length,
    notInMaps: availableCatalogLayers.value.length,
    inMaps: activeStack.value.length,
    geoportal: all.filter((l) => getLayerSourceGroup(l).key === "geoportal").length,
    internal: all.filter((l) => getLayerSourceGroup(l).key === "internal").length,
    external: all.filter((l) => getLayerSourceGroup(l).key === "external").length,
  };
});

const filteredCatalogLayers = computed(() => {
  const q = catalogSearch.value.trim().toLowerCase();
  return allDraftLayersSorted.value.filter((layer) => {
    const inMaps = activeIdSet.value.has(layer.id);
    if (catalogAvailabilityFilter.value === "in_maps" && !inMaps) return false;
    if (catalogAvailabilityFilter.value === "not_in_maps" && inMaps) return false;

    if (
      catalogSourceFilter.value !== "ALL" &&
      getLayerSourceGroup(layer).key !== catalogSourceFilter.value
    ) {
      return false;
    }

    if (!q) return true;
    return (
      layer.name.toLowerCase().includes(q) ||
      (layer.layer_name || "").toLowerCase().includes(q) ||
      (layer.opd_name || layer.attribution || "").toLowerCase().includes(q) ||
      (layer.category || "").toLowerCase().includes(q)
    );
  });
});

// Right Panel: Filtered /maps active stack
const isStackFiltered = computed(() => stackSearch.value.trim().length > 0);

const filteredActiveStack = computed(() => {
  const q = stackSearch.value.trim().toLowerCase();
  return activeStack.value
    .map((layer, stackIndex) => ({ layer, stackIndex }))
    .filter(({ layer }) => {
      if (!q) return true;
      return (
        layer.name.toLowerCase().includes(q) ||
        (layer.layer_name || "").toLowerCase().includes(q) ||
        (layer.opd_name || layer.attribution || "").toLowerCase().includes(q)
      );
    });
});

// Selection helpers for Dual-List Transfer Arrows
const selectedLeftSet = computed(() => new Set(selectedLeftIds.value));
const selectedRightSet = computed(() => new Set(selectedRightIds.value));
const expandedLayerSet = computed(() => new Set(expandedLayerIds.value));

const selectedLeftCandidatesForMaps = computed(() => {
  return selectedLeftIds.value.filter((id) => catalogOnlyIdSet.value.has(id));
});

const selectedActiveIdsForRemoval = computed(() => {
  const combined = new Set<string>([
    ...selectedRightIds.value.filter((id) => activeIdSet.value.has(id)),
    ...selectedLeftIds.value.filter((id) => activeIdSet.value.has(id)),
  ]);
  return Array.from(combined);
});

const selectableNotInMapsFilteredIds = computed(() =>
  filteredCatalogLayers.value.filter((l) => !activeIdSet.value.has(l.id)).map((l) => l.id),
);

const allFilteredNotInMapsSelected = computed(() => {
  const candidates = selectableNotInMapsFilteredIds.value;
  return candidates.length > 0 && candidates.every((id) => selectedLeftSet.value.has(id));
});

const allFilteredActiveSelected = computed(() => {
  const visible = filteredActiveStack.value.map((x) => x.layer.id);
  return visible.length > 0 && visible.every((id) => selectedRightSet.value.has(id));
});

function toggleLeftSelection(layerId: string) {
  if (!props.canEdit) return;
  if (selectedLeftSet.value.has(layerId)) {
    selectedLeftIds.value = selectedLeftIds.value.filter((id) => id !== layerId);
  } else {
    selectedLeftIds.value = [...selectedLeftIds.value, layerId];
  }
}

function toggleRightSelection(layerId: string) {
  if (!props.canEdit) return;
  if (selectedRightSet.value.has(layerId)) {
    selectedRightIds.value = selectedRightIds.value.filter((id) => id !== layerId);
  } else {
    selectedRightIds.value = [...selectedRightIds.value, layerId];
  }
}

function toggleSelectAllFilteredNotInMaps() {
  if (!props.canEdit) return;
  const candidates = selectableNotInMapsFilteredIds.value;
  if (candidates.length === 0) return;

  if (allFilteredNotInMapsSelected.value) {
    const candidateSet = new Set(candidates);
    selectedLeftIds.value = selectedLeftIds.value.filter((id) => !candidateSet.has(id));
  } else {
    const merged = new Set([...selectedLeftIds.value, ...candidates]);
    selectedLeftIds.value = Array.from(merged);
  }
}

function toggleSelectAllFilteredActive() {
  if (!props.canEdit) return;
  const visible = filteredActiveStack.value.map((x) => x.layer.id);
  if (visible.length === 0) return;

  if (allFilteredActiveSelected.value) {
    const visibleSet = new Set(visible);
    selectedRightIds.value = selectedRightIds.value.filter((id) => !visibleSet.has(id));
  } else {
    const merged = new Set([...selectedRightIds.value, ...visible]);
    selectedRightIds.value = Array.from(merged);
  }
}

function toggleCardExpand(layerId: string) {
  if (expandedLayerSet.value.has(layerId)) {
    expandedLayerIds.value = expandedLayerIds.value.filter((id) => id !== layerId);
  } else {
    expandedLayerIds.value = [...expandedLayerIds.value, layerId];
  }
}

function triggerHighlight(layerId: string) {
  recentlyMovedLayerId.value = layerId;
  setTimeout(() => {
    if (recentlyMovedLayerId.value === layerId) {
      recentlyMovedLayerId.value = null;
    }
  }, 900);
}

// Transfer Right (->): Move selected layers into Active Stack (/maps) with default_visible = true
function moveSelectedRight() {
  if (!props.canEdit || selectedLeftCandidatesForMaps.value.length === 0) return;
  const targetSet = new Set(selectedLeftCandidatesForMaps.value);

  const toActivate: LayerItem[] = [];
  const remainingCatalog: LayerItem[] = [];

  for (const layer of availableCatalogLayers.value) {
    if (targetSet.has(layer.id)) {
      layer.is_active = true;
      layer.default_visible = true;
      toActivate.push(layer);
    } else {
      remainingCatalog.push(layer);
    }
  }

  if (toActivate.length === 0) return;

  availableCatalogLayers.value = remainingCatalog;
  // Insert newly added layers at top of /maps active stack
  activeStack.value = [...toActivate, ...activeStack.value];
  selectedLeftIds.value = selectedLeftIds.value.filter((id) => !targetSet.has(id));

  if (toActivate[0]) {
    triggerHighlight(toActivate[0].id);
  }
}

// Single-layer quick transfer right (e.g. double click row in Left Panel)
function addSingleLayerToMaps(layerId: string) {
  if (!props.canEdit) return;
  const idx = availableCatalogLayers.value.findIndex((l) => l.id === layerId);
  if (idx === -1) return;

  const [picked] = availableCatalogLayers.value.splice(idx, 1);
  if (!picked) return;

  picked.is_active = true;
  picked.default_visible = true;
  activeStack.value.unshift(picked);
  selectedLeftIds.value = selectedLeftIds.value.filter((id) => id !== layerId);
  triggerHighlight(picked.id);
}

// Transfer Left (<-): Remove selected active layers from /maps active stack
function moveSelectedLeft() {
  if (!props.canEdit || selectedActiveIdsForRemoval.value.length === 0) return;
  const targetSet = new Set(selectedActiveIdsForRemoval.value);
  const masterById = new Map(masterLayers.value.map((m) => [m.id, m]));

  const removed: LayerItem[] = [];
  const remainingActive: LayerItem[] = [];

  for (const layer of activeStack.value) {
    if (targetSet.has(layer.id)) {
      const orig = masterById.get(layer.id);
      layer.is_active = orig ? Boolean(orig.is_active) : true;
      layer.default_visible = false;
      layer.order = orig && !Boolean(orig.default_visible) ? Number(orig.order ?? 0) : 0;
      removed.push(layer);
    } else {
      remainingActive.push(layer);
    }
  }

  if (removed.length === 0) return;

  activeStack.value = remainingActive;
  availableCatalogLayers.value = [...availableCatalogLayers.value, ...removed].sort((a, b) =>
    a.name.localeCompare(b.name, "id"),
  );
  selectedRightIds.value = selectedRightIds.value.filter((id) => !targetSet.has(id));
  selectedLeftIds.value = selectedLeftIds.value.filter((id) => !targetSet.has(id));
  expandedLayerIds.value = expandedLayerIds.value.filter((id) => !targetSet.has(id));
}

function removeSingleLayerFromMaps(layerId: string) {
  if (!props.canEdit) return;
  selectedRightIds.value = [layerId];
  moveSelectedLeft();
}

// Stack Reorder Logic (identical semantics to /maps ActiveLayersView.vue)
function moveStackItem(fromIndex: number, toIndex: number) {
  if (!props.canEdit || isStackFiltered.value) return;
  if (
    fromIndex < 0 ||
    fromIndex >= activeStack.value.length ||
    toIndex < 0 ||
    toIndex >= activeStack.value.length ||
    fromIndex === toIndex
  ) {
    return;
  }

  const next = [...activeStack.value];
  const [moved] = next.splice(fromIndex, 1);
  if (!moved) return;

  next.splice(toIndex, 0, moved);
  activeStack.value = next;
  triggerHighlight(moved.id);
}

function handleRankSelectChange(fromIndex: number, event: Event) {
  const target = event.target as HTMLSelectElement | null;
  if (!target) return;
  const targetRank = Number.parseInt(target.value, 10);
  if (Number.isNaN(targetRank)) return;
  moveStackItem(fromIndex, targetRank - 1);
}

// Desktop HTML5 drag handlers
function onDragStart(stackIndex: number, event: DragEvent) {
  if (!props.canEdit || isTouchDevice.value || isStackFiltered.value) {
    event.preventDefault();
    return;
  }
  draggedLayerIndex.value = stackIndex;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(stackIndex));
  }
}

function onDragOver(event: DragEvent) {
  if (!props.canEdit || isStackFiltered.value) return;
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = "move";
  }
}

function onDrop(dropIndex: number) {
  if (draggedLayerIndex.value === null || draggedLayerIndex.value === dropIndex) {
    draggedLayerIndex.value = null;
    return;
  }
  moveStackItem(draggedLayerIndex.value, dropIndex);
  draggedLayerIndex.value = null;
}

// Mobile touch-drag handlers (mirrors /maps ActiveLayersView.vue)
function onGripTouchStart(stackIndex: number, event: TouchEvent) {
  if (!props.canEdit || isStackFiltered.value) return;
  if (event.cancelable) {
    event.preventDefault();
  }
  event.stopPropagation();

  draggedLayerIndex.value = stackIndex;
  touchCurrentIndex = stackIndex;
  isTouchDragging.value = true;
}

function onGripTouchMove(event: TouchEvent) {
  if (!isTouchDragging.value || draggedLayerIndex.value === null || isStackFiltered.value) return;
  if (event.cancelable) {
    event.preventDefault();
  }
  event.stopPropagation();

  const touch = event.touches[0];
  if (!touch) return;

  const targetEl = document.elementFromPoint(touch.clientX, touch.clientY);
  if (!targetEl) return;

  const cardEl = targetEl.closest("[data-stack-card-index]") as HTMLElement | null;
  if (cardEl && cardEl.dataset.stackCardIndex !== undefined) {
    const targetIdx = Number(cardEl.dataset.stackCardIndex);
    if (
      !Number.isNaN(targetIdx) &&
      targetIdx !== touchCurrentIndex &&
      targetIdx >= 0 &&
      targetIdx < activeStack.value.length
    ) {
      moveStackItem(touchCurrentIndex, targetIdx);
      touchCurrentIndex = targetIdx;
      draggedLayerIndex.value = targetIdx;
    }
  }
}

function onGripTouchEnd(event?: TouchEvent) {
  if (event && event.cancelable) {
    event.preventDefault();
  }
  draggedLayerIndex.value = null;
  touchCurrentIndex = -1;
  isTouchDragging.value = false;
}

function updateOpacity(layer: LayerItem, rawPercent: string) {
  if (!props.canEdit) return;
  const parsed = Number.parseInt(rawPercent, 10);
  if (Number.isNaN(parsed)) return;
  layer.opacity = Math.max(0, Math.min(1, Math.round(parsed) / 100));
}

function resetCatalogFilters() {
  catalogSearch.value = "";
  catalogSourceFilter.value = "ALL";
  catalogAvailabilityFilter.value = "all";
}

function resetDraft() {
  hydrateDraftFromMaster(masterLayers.value);
  toast.add({
    icon: "i-lucide-rotate-ccw",
    title: "Perubahan draft dibatalkan.",
    description: "Susunan dan status layer dikembalikan ke konfigurasi tersimpan terakhir.",
    color: "neutral",
  });
}

async function saveConfiguration() {
  if (!props.canEdit || saving.value || !hasUnsavedChanges.value) return;

  saving.value = true;
  try {
    const itemsToSave = changedPayloadItems.value;
    const res = await $http<{ ok: boolean; updated_count?: number }>(
      "admin/layers/reorder",
      {
        method: "POST",
        body: {
          items: itemsToSave,
        },
      },
    );

    toast.add({
      icon: "i-heroicons-check-circle",
      title: "Konfigurasi layer aktif disimpan.",
      description: `${res?.updated_count ?? itemsToSave.length} layer diperbarui dan selaras dengan tampilan /maps.`,
      color: "success",
    });

    await fetchStack();
    emit("saved");
  } catch (err: any) {
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Gagal menyimpan konfigurasi layer.",
      description: err?.data?.message || err?.message || "Terjadi kesalahan saat menyimpan urutan layer.",
      color: "error",
    });
  } finally {
    saving.value = false;
  }
}

defineExpose({
  refreshStack: fetchStack,
  hasUnsavedChanges,
});
</script>

<template>
  <section class="space-y-4" aria-label="Konfigurasi Layer Aktif dan Urutan Tumpukan Peta">
    <!-- Loading State: Dual-list transfer skeleton -->
    <div
      v-if="pending"
      class="rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-4 sm:p-6 space-y-5"
      role="status"
      aria-live="polite"
    >
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200 dark:border-white/[0.08]">
        <div class="space-y-2 flex-1">
          <div class="h-5 w-64 rounded-md bg-slate-200 dark:bg-slate-800 animate-pulse" />
          <div class="h-3.5 w-full max-w-lg rounded-md bg-slate-100 dark:bg-slate-800/60 animate-pulse" />
        </div>
        <div class="flex items-center gap-2">
          <div class="h-11 w-28 rounded-lg bg-slate-100 dark:bg-slate-800/60 animate-pulse" />
          <div class="h-11 w-40 rounded-lg bg-blue-100 dark:bg-blue-950/40 animate-pulse" />
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1.15fr)] gap-4 items-center">
        <div class="space-y-2.5">
          <div class="h-10 w-full rounded-lg bg-slate-100 dark:bg-slate-800/60 animate-pulse" />
          <div v-for="n in 5" :key="`skel-left-${n}`" class="h-14 rounded-xl bg-slate-100 dark:bg-slate-800/40 animate-pulse" />
        </div>
        <div class="flex lg:flex-col items-center justify-center gap-2 py-2">
          <div class="size-11 rounded-xl bg-slate-100 dark:bg-slate-800/60 animate-pulse" />
          <div class="size-11 rounded-xl bg-slate-100 dark:bg-slate-800/60 animate-pulse" />
        </div>
        <div class="space-y-2.5">
          <div class="h-10 w-full rounded-lg bg-slate-100 dark:bg-slate-800/60 animate-pulse" />
          <div v-for="n in 4" :key="`skel-right-${n}`" class="h-16 rounded-xl bg-slate-100 dark:bg-slate-800/40 animate-pulse" />
        </div>
      </div>

      <p class="text-xs font-medium text-slate-600 dark:text-slate-300 text-center">
        Memuat daftar semua layer dan tumpukan layer aktif peta...
      </p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="loadError"
      class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
      role="alert"
    >
      <div class="flex items-start gap-3">
        <div class="size-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 flex items-center justify-center shrink-0">
          <UIcon name="i-heroicons-exclamation-triangle" class="size-5" />
        </div>
        <div class="space-y-1">
          <h3 class="text-sm font-bold text-red-900 dark:text-red-100">
            Gagal Memuat Konfigurasi Tumpukan Layer
          </h3>
          <p class="text-xs text-red-800 dark:text-red-200 leading-relaxed">
            {{ loadError }}
          </p>
        </div>
      </div>
      <UButton
        label="Coba Lagi"
        icon="i-heroicons-arrow-path"
        color="error"
        variant="solid"
        class="min-h-[44px] cursor-pointer shrink-0"
        @click="fetchStack"
      />
    </div>

    <!-- Global Empty State: Zero layers registered in catalog -->
    <div
      v-else-if="masterLayers.length === 0"
      class="rounded-xl border border-dashed border-slate-300 dark:border-white/[0.12] bg-white dark:bg-[#0b0f19] p-10 text-center space-y-3"
    >
      <div class="size-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-layers" class="size-6" />
      </div>
      <div class="space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
          Belum Ada Layer Terdaftar di Katalog
        </h3>
        <p class="text-xs text-slate-600 dark:text-slate-300 max-w-md mx-auto leading-relaxed">
          Tambahkan layer baru atau lakukan sinkronisasi Geoportal/Martin pada tab Katalog untuk mulai menyusun layer aktif /maps.
        </p>
      </div>
      <div class="pt-1">
        <UButton
          label="Buka Katalog Layer"
          icon="i-lucide-list"
          color="primary"
          class="min-h-[44px] cursor-pointer"
          @click="emit('open-catalog')"
        />
      </div>
    </div>

    <!-- Main Transfer Studio -->
    <template v-else>
      <!-- Top Studio Command Strip -->
      <div class="rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] px-4 py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="space-y-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-bold text-slate-900 dark:text-white">
              Tumpukan Layer Aktif di /maps
            </span>
            <span class="px-2 py-0.5 rounded-md text-xs font-semibold tabular-nums bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300">
              {{ activeStack.length }} aktif
            </span>
            <span
              v-if="hasUnsavedChanges"
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold tabular-nums bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300"
            >
              <span class="size-1.5 rounded-full bg-amber-600 dark:bg-amber-400" aria-hidden="true" />
              {{ changedPayloadItems.length }} belum disimpan
            </span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-300">
            Atur layer yang otomatis tampil di peta publik beserta urutan lapisannya (urutan #1 tampil paling depan).
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <UButton
            label="Muat Ulang"
            icon="i-heroicons-arrow-path"
            color="neutral"
            variant="outline"
            size="sm"
            :disabled="saving"
            class="min-h-[44px] sm:min-h-[38px] cursor-pointer"
            @click="fetchStack"
          />
          <UButton
            v-if="canEdit && hasUnsavedChanges"
            label="Batalkan"
            icon="i-lucide-rotate-ccw"
            color="neutral"
            variant="subtle"
            size="sm"
            :disabled="saving"
            class="min-h-[44px] sm:min-h-[38px] cursor-pointer"
            @click="resetDraft"
          />
          <UButton
            v-if="canEdit"
            :label="hasUnsavedChanges ? `Simpan (${changedPayloadItems.length})` : 'Tersimpan'"
            :icon="hasUnsavedChanges ? 'i-heroicons-check' : 'i-lucide-check-check'"
            color="primary"
            size="sm"
            :variant="hasUnsavedChanges ? 'solid' : 'subtle'"
            :disabled="!hasUnsavedChanges || saving"
            :loading="saving"
            class="min-h-[44px] sm:min-h-[38px] cursor-pointer font-semibold"
            @click="saveConfiguration"
          />
        </div>
      </div>

      <div
        v-if="!canEdit"
        class="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-slate-50 dark:bg-white/[0.03] flex items-center gap-2.5 text-xs text-slate-700 dark:text-slate-300"
      >
        <UIcon name="i-lucide-lock" class="size-4 text-slate-500 dark:text-slate-400 shrink-0" aria-hidden="true" />
        <span>Mode lihat saja: akun Anda dapat melihat susunan layer, namun tidak memiliki izin mengubah keaktifan atau urutan layer.</span>
      </div>

      <!-- Mobile / Tablet Pane Switcher (< lg) -->
      <div
        class="lg:hidden grid grid-cols-2 gap-1 p-1 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-slate-100/80 dark:bg-[#0c1424]"
        role="tablist"
        aria-label="Pilih daftar layer"
      >
        <button
          type="button"
          role="tab"
          :aria-selected="mobilePane === 'active'"
          class="min-h-[44px] px-3 py-2 rounded-lg text-xs font-semibold transition-colors cursor-pointer flex items-center justify-center gap-2"
          :class="mobilePane === 'active'
            ? 'bg-blue-600 text-white shadow-xs'
            : 'text-slate-700 dark:text-slate-300'"
          @click="mobilePane = 'active'"
        >
          <span>Aktif di /maps</span>
          <span
            class="px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums"
            :class="mobilePane === 'active' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-white/10 text-slate-800 dark:text-slate-200'"
          >
            {{ activeStack.length }}
          </span>
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="mobilePane === 'all'"
          class="min-h-[44px] px-3 py-2 rounded-lg text-xs font-semibold transition-colors cursor-pointer flex items-center justify-center gap-2"
          :class="mobilePane === 'all'
            ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs'
            : 'text-slate-700 dark:text-slate-300'"
          @click="mobilePane = 'all'"
        >
          <span>Semua Layer</span>
          <span class="px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums bg-slate-200/80 dark:bg-white/10 text-slate-800 dark:text-slate-200">
            {{ allDraftLayersSorted.length }}
          </span>
        </button>
      </div>

      <!-- 3-Track Dual-List Transfer Workspace (Desktop) / Tabbed Single-Pane (Mobile) -->
      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1.15fr)] gap-4 items-stretch">
        <!-- LEFT PANEL: Semua Layer -->
        <div
          class="rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] overflow-hidden flex-col"
          :class="mobilePane === 'all' ? 'flex' : 'hidden lg:flex'"
        >
          <div class="p-3.5 sm:p-4 border-b border-slate-200 dark:border-white/[0.08] space-y-2.5 bg-slate-50/70 dark:bg-[#070b14]/60">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                  Semua Layer
                </span>
                <span class="px-2 py-0.5 rounded-md text-xs font-bold tabular-nums bg-slate-200/80 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                  {{ filteredCatalogLayers.length }}
                </span>
              </div>

              <button
                v-if="canEdit && selectableNotInMapsFilteredIds.length > 0"
                type="button"
                class="min-h-[36px] px-2.5 py-1 rounded-lg text-xs font-semibold text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors cursor-pointer shrink-0"
                @click="toggleSelectAllFilteredNotInMaps"
              >
                {{ allFilteredNotInMapsSelected ? 'Batal Pilih' : 'Pilih Belum Aktif' }}
              </button>
            </div>

            <UInput
              v-model="catalogSearch"
              icon="i-heroicons-magnifying-glass"
              placeholder="Cari nama layer atau instansi..."
              aria-label="Cari nama semua layer"
              clearable
              class="w-full"
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <USelect
                v-model="catalogAvailabilityFilter"
                :items="catalogAvailabilityOptions"
                aria-label="Filter status pada peta"
                class="w-full"
              />
              <USelect
                v-model="catalogSourceFilter"
                :items="catalogSourceOptions"
                aria-label="Filter sumber data"
                class="w-full"
              />
            </div>

            <!-- Mobile Batch Action Bar saat ada item terpilih -->
            <div
              v-if="canEdit && selectedLeftCandidatesForMaps.length > 0"
              class="lg:hidden flex items-center justify-between gap-2 pt-1"
            >
              <span class="text-xs font-medium text-blue-700 dark:text-blue-300 tabular-nums">
                {{ selectedLeftCandidatesForMaps.length }} layer dipilih
              </span>
              <UButton
                label="Masukkan ke /maps"
                icon="i-lucide-plus"
                size="xs"
                color="primary"
                class="min-h-[40px] cursor-pointer font-semibold"
                @click="moveSelectedRight(); mobilePane = 'active'"
              />
            </div>
          </div>

          <!-- Filtered Empty State in Left Panel -->
          <div
            v-if="filteredCatalogLayers.length === 0"
            class="p-8 text-center space-y-3 flex-1 flex flex-col items-center justify-center"
          >
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">
              Tidak ada layer yang cocok dengan filter
            </p>
            <p class="text-xs text-slate-600 dark:text-slate-300">
              Ubah kata kunci pencarian atau tampilkan semua status.
            </p>
            <UButton
              label="Reset Filter"
              size="sm"
              color="neutral"
              variant="outline"
              class="min-h-[44px] cursor-pointer"
              @click="resetCatalogFilters"
            />
          </div>

          <!-- Left List -->
          <ul
            v-else
            class="divide-y divide-slate-100 dark:divide-white/[0.06] max-h-[520px] lg:max-h-[640px] overflow-y-auto flex-1"
          >
            <li
              v-for="layer in filteredCatalogLayers"
              :key="`all-layer-${layer.id}`"
              class="transition-colors"
              :class="[
                selectedLeftSet.has(layer.id)
                  ? 'bg-blue-50 dark:bg-blue-950/35'
                  : activeIdSet.has(layer.id)
                  ? 'bg-slate-50/70 dark:bg-white/[0.02]'
                  : 'hover:bg-slate-50 dark:hover:bg-white/[0.02]',
              ]"
            >
              <div
                class="w-full min-h-[56px] px-3 py-2.5 flex items-center justify-between gap-2"
                :class="canEdit ? 'cursor-pointer' : ''"
                @click="toggleLeftSelection(layer.id)"
              >
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                  <div
                    v-if="canEdit"
                    class="flex items-center justify-center min-w-[36px] min-h-[36px] shrink-0"
                    @click.stop="toggleLeftSelection(layer.id)"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedLeftSet.has(layer.id)"
                      :aria-label="`Pilih layer ${layer.name}`"
                      class="size-4.5 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 cursor-pointer"
                      @click.stop
                      @change="toggleLeftSelection(layer.id)"
                    />
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 min-w-0">
                      <span
                        class="text-xs sm:text-sm font-semibold truncate"
                        :class="activeIdSet.has(layer.id) ? 'text-slate-700 dark:text-slate-200' : 'text-slate-900 dark:text-white'"
                      >
                        {{ layer.name }}
                      </span>
                      <span
                        v-if="changedLayerIdSet.has(layer.id)"
                        class="size-2 rounded-full bg-amber-500 shrink-0"
                        title="Perubahan belum disimpan"
                      />
                    </div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-300 truncate mt-0.5">
                      {{ layer.opd_name || layer.attribution || getLayerSourceGroup(layer).name }} · {{ layer.protocol.toUpperCase() }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0" @click.stop>
                  <span
                    v-if="activeRankById.get(layer.id)"
                    class="px-2 py-0.5 rounded-md text-[11px] font-bold tabular-nums bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300"
                  >
                    Aktif #{{ activeRankById.get(layer.id) }}
                  </span>
                  <button
                    v-else-if="canEdit"
                    type="button"
                    :aria-label="`Tambahkan ${layer.name} ke /maps`"
                    class="min-h-[38px] px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60 bg-blue-50/70 dark:bg-blue-950/30 text-xs font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors cursor-pointer inline-flex items-center gap-1"
                    @click="addSingleLayerToMaps(layer.id)"
                  >
                    <UIcon name="i-lucide-plus" class="size-3.5" aria-hidden="true" />
                    <span>Aktifkan</span>
                  </button>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
                  >
                    {{ layer.is_active ? 'Katalog' : 'Nonaktif' }}
                  </span>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <!-- CENTER TRANSFER ARROWS (Desktop lg+) -->
        <div
          class="hidden lg:flex flex-col items-center justify-center gap-2.5 px-1 shrink-0"
          role="group"
          aria-label="Kontrol panah pemindah layer ke daftar aktif peta"
        >
          <UTooltip :text="`Masukkan ${selectedLeftCandidatesForMaps.length} layer terpilih ke Layer Aktif (/maps)`">
            <button
              type="button"
              :disabled="!canEdit || selectedLeftCandidatesForMaps.length === 0"
              :aria-label="`Masukkan layer terpilih ke layer aktif peta (${selectedLeftCandidatesForMaps.length} dipilih)`"
              class="min-h-[44px] min-w-[44px] px-3 py-2.5 rounded-xl border font-semibold text-xs inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer disabled:opacity-35 disabled:cursor-not-allowed"
              :class="canEdit && selectedLeftCandidatesForMaps.length > 0
                ? 'bg-blue-600 hover:bg-blue-700 text-white border-blue-600 shadow-xs'
                : 'bg-white dark:bg-[#0c1424] border-slate-200 dark:border-white/[0.1] text-slate-600 dark:text-slate-300'"
              @click="moveSelectedRight"
            >
              <UIcon name="i-lucide-arrow-right" class="size-4.5 shrink-0" aria-hidden="true" />
              <span
                v-if="selectedLeftCandidatesForMaps.length > 0"
                class="tabular-nums px-1.5 py-0.5 rounded text-[11px] font-bold bg-white/20 text-white"
              >
                {{ selectedLeftCandidatesForMaps.length }}
              </span>
            </button>
          </UTooltip>

          <UTooltip :text="`Keluarkan ${selectedActiveIdsForRemoval.length} layer terpilih dari Layer Aktif (/maps)`">
            <button
              type="button"
              :disabled="!canEdit || selectedActiveIdsForRemoval.length === 0"
              :aria-label="`Keluarkan layer terpilih dari layer aktif peta (${selectedActiveIdsForRemoval.length} dipilih)`"
              class="min-h-[44px] min-w-[44px] px-3 py-2.5 rounded-xl border font-semibold text-xs inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer disabled:opacity-35 disabled:cursor-not-allowed"
              :class="canEdit && selectedActiveIdsForRemoval.length > 0
                ? 'bg-red-600 hover:bg-red-700 text-white border-red-600 shadow-xs'
                : 'bg-white dark:bg-[#0c1424] border-slate-200 dark:border-white/[0.1] text-slate-600 dark:text-slate-300'"
              @click="moveSelectedLeft"
            >
              <UIcon name="i-lucide-arrow-left" class="size-4.5 shrink-0" aria-hidden="true" />
              <span
                v-if="selectedActiveIdsForRemoval.length > 0"
                class="tabular-nums px-1.5 py-0.5 rounded text-[11px] font-bold bg-white/20 text-white"
              >
                {{ selectedActiveIdsForRemoval.length }}
              </span>
            </button>
          </UTooltip>
        </div>

        <!-- RIGHT PANEL: Layer Aktif (/maps) -->
        <div
          class="rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] overflow-hidden flex-col"
          :class="mobilePane === 'active' ? 'flex' : 'hidden lg:flex'"
        >
          <div class="p-3.5 sm:p-4 border-b border-slate-200 dark:border-white/[0.08] space-y-2.5 bg-slate-50/70 dark:bg-[#070b14]/60">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                  Layer Aktif (/maps)
                </span>
                <span class="px-2 py-0.5 rounded-md text-xs font-bold tabular-nums bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
                  {{ activeStack.length }}
                </span>
              </div>

              <div class="flex items-center gap-2">
                <button
                  v-if="canEdit && filteredActiveStack.length > 0"
                  type="button"
                  class="min-h-[36px] px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-200/70 dark:hover:bg-white/[0.08] transition-colors cursor-pointer shrink-0"
                  @click="toggleSelectAllFilteredActive"
                >
                  {{ allFilteredActiveSelected ? 'Batal Pilih' : 'Pilih Semua' }}
                </button>
              </div>
            </div>

            <UInput
              v-model="stackSearch"
              icon="i-heroicons-magnifying-glass"
              placeholder="Saring urutan layer aktif..."
              aria-label="Saring layer aktif di /maps"
              clearable
              class="w-full"
            />

            <!-- Mobile Batch Remove Bar -->
            <div
              v-if="canEdit && selectedActiveIdsForRemoval.length > 0"
              class="lg:hidden flex items-center justify-between gap-2 pt-0.5"
            >
              <span class="text-xs font-medium text-red-700 dark:text-red-300 tabular-nums">
                {{ selectedActiveIdsForRemoval.length }} layer dipilih
              </span>
              <UButton
                label="Keluarkan Terpilih"
                icon="i-lucide-trash-2"
                size="xs"
                color="error"
                variant="subtle"
                class="min-h-[40px] cursor-pointer font-semibold"
                @click="moveSelectedLeft"
              />
            </div>

            <div
              v-if="isStackFiltered"
              class="px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/40 text-xs text-blue-900 dark:text-blue-200 flex items-center justify-between gap-2"
            >
              <span>Kosongkan kolom saring untuk mengubah urutan layer.</span>
              <button
                type="button"
                class="font-semibold underline cursor-pointer shrink-0 min-h-[36px] px-2"
                @click="stackSearch = ''"
              >
                Bersihkan
              </button>
            </div>
          </div>

          <!-- Empty State: No active layers in /maps -->
          <div
            v-if="activeStack.length === 0"
            class="p-8 sm:p-10 text-center space-y-3 flex-1 flex flex-col items-center justify-center"
          >
            <div class="size-11 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center">
              <UIcon name="i-lucide-layers-2" class="size-5" aria-hidden="true" />
            </div>
            <div class="space-y-1">
              <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                Belum Ada Layer Aktif di /maps
              </h4>
              <p class="text-xs text-slate-600 dark:text-slate-300 max-w-sm mx-auto leading-relaxed">
                Tambahkan layer dari daftar Semua Layer untuk menampilkannya otomatis pada peta publik.
              </p>
            </div>
            <UButton
              label="Pilih dari Semua Layer"
              icon="i-lucide-plus"
              size="sm"
              color="primary"
              variant="outline"
              class="lg:hidden min-h-[44px] cursor-pointer"
              @click="mobilePane = 'all'"
            />
          </div>

          <!-- Search Empty State in Right Panel -->
          <div
            v-else-if="filteredActiveStack.length === 0"
            class="p-8 text-center space-y-3 flex-1 flex flex-col items-center justify-center"
          >
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">
              Tidak ada layer aktif yang cocok dengan "{{ stackSearch }}"
            </p>
            <UButton
              label="Bersihkan Saringan"
              size="sm"
              color="neutral"
              variant="outline"
              class="min-h-[44px] cursor-pointer"
              @click="stackSearch = ''"
            />
          </div>

          <!-- Active Layer Stack List -->
          <div v-else class="p-3 sm:p-4 space-y-2.5 max-h-[520px] lg:max-h-[640px] overflow-y-auto flex-1">
            <p class="text-[11px] font-medium text-slate-600 dark:text-slate-300 px-1">
              Urutan #1 berada di lapisan paling depan. Klik baris untuk atur posisi &amp; opasitas.
            </p>

            <TransitionGroup
              name="layer-card"
              tag="div"
              class="space-y-2 relative"
            >
              <div
                v-for="{ layer, stackIndex } in filteredActiveStack"
                :key="layer.id"
                :data-stack-card-index="stackIndex"
                :draggable="canEdit && !isTouchDevice && !isStackFiltered"
                class="rounded-xl border border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0c1424] transition-colors overflow-hidden select-none"
                :class="[
                  draggedLayerIndex === stackIndex ? 'opacity-40 border-dashed border-blue-500 ring-2 ring-blue-500/30' : '',
                  recentlyMovedLayerId === layer.id ? 'ring-2 ring-blue-500/70 border-blue-500 bg-blue-50/40 dark:bg-blue-950/30' : '',
                  selectedRightSet.has(layer.id) ? 'ring-2 ring-blue-600/40 border-blue-500' : '',
                ]"
                @dragstart="onDragStart(stackIndex, $event)"
                @dragover="onDragOver($event)"
                @drop="onDrop(stackIndex)"
                @dragend="draggedLayerIndex = null"
              >
                <!-- Compact Stack Row -->
                <div
                  class="flex items-center justify-between px-2.5 py-2 gap-1.5 hover:bg-slate-50/80 dark:hover:bg-white/[0.03] transition-colors cursor-pointer"
                  @click="toggleCardExpand(layer.id)"
                >
                  <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 flex-1">
                    <div
                      v-if="canEdit"
                      class="hidden sm:flex items-center justify-center min-w-[36px] min-h-[36px] shrink-0 cursor-pointer"
                      @click.stop="toggleRightSelection(layer.id)"
                    >
                      <input
                        type="checkbox"
                        :checked="selectedRightSet.has(layer.id)"
                        :aria-label="`Pilih layer aktif ${layer.name} untuk dikeluarkan`"
                        class="size-4.5 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        @click.stop
                        @change="toggleRightSelection(layer.id)"
                      />
                    </div>

                    <!-- Grip Handle -->
                    <div
                      v-if="canEdit && !isStackFiltered"
                      class="hidden sm:flex items-center shrink-0 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 p-1 select-none touch-none cursor-grab active:cursor-grabbing"
                      title="Seret untuk menggeser urutan"
                      @touchstart.passive="false"
                      @touchstart="onGripTouchStart(stackIndex, $event)"
                      @touchmove.passive="false"
                      @touchmove="onGripTouchMove($event)"
                      @touchend="onGripTouchEnd($event)"
                      @touchcancel="onGripTouchEnd($event)"
                      @click.stop
                    >
                      <UIcon name="i-lucide-grip-vertical" class="size-4" aria-hidden="true" />
                    </div>

                    <!-- Order Rank + Identity -->
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center gap-1.5 min-w-0">
                        <span
                          class="px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums shrink-0"
                          :class="stackIndex === 0
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200'"
                        >
                          #{{ stackIndex + 1 }}
                        </span>
                        <span class="block text-xs sm:text-sm font-semibold truncate text-slate-900 dark:text-slate-100">
                          {{ layer.name }}
                        </span>
                        <span
                          v-if="changedLayerIdSet.has(layer.id)"
                          class="size-2 rounded-full bg-amber-500 shrink-0"
                          title="Memiliki perubahan belum disimpan"
                        />
                      </div>
                      <span class="block text-[11px] text-slate-600 dark:text-slate-300 truncate mt-0.5 tabular-nums">
                        {{ layer.opd_name || layer.attribution || getLayerSourceGroup(layer).name }} · {{ layer.protocol.toUpperCase() }} · {{ Math.round((layer.opacity ?? 1) * 100) }}%
                      </span>
                    </div>
                  </div>

                  <!-- Reorder & Expand Controls (min 44px touch targets) -->
                  <div class="flex items-center gap-0.5 shrink-0" @click.stop>
                    <button
                      v-if="canEdit"
                      type="button"
                      class="size-10 sm:size-8 rounded-lg flex items-center justify-center transition-colors text-slate-600 hover:text-blue-600 dark:text-slate-300 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-25 disabled:pointer-events-none cursor-pointer"
                      :disabled="stackIndex === 0 || isStackFiltered"
                      :aria-label="`Naikkan urutan layer ${layer.name}`"
                      title="Naikkan urutan"
                      @click.stop="moveStackItem(stackIndex, stackIndex - 1)"
                    >
                      <UIcon name="i-lucide-chevron-up" class="size-4" aria-hidden="true" />
                    </button>
                    <button
                      v-if="canEdit"
                      type="button"
                      class="size-10 sm:size-8 rounded-lg flex items-center justify-center transition-colors text-slate-600 hover:text-blue-600 dark:text-slate-300 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-25 disabled:pointer-events-none cursor-pointer"
                      :disabled="stackIndex === activeStack.length - 1 || isStackFiltered"
                      :aria-label="`Turunkan urutan layer ${layer.name}`"
                      title="Turunkan urutan"
                      @click.stop="moveStackItem(stackIndex, stackIndex + 1)"
                    >
                      <UIcon name="i-lucide-chevron-down" class="size-4" aria-hidden="true" />
                    </button>
                    <button
                      type="button"
                      class="size-10 sm:size-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                      :aria-label="`Pengaturan detail layer ${layer.name}`"
                      :aria-expanded="expandedLayerSet.has(layer.id)"
                      @click="toggleCardExpand(layer.id)"
                    >
                      <UIcon
                        name="i-lucide-sliders-horizontal"
                        class="size-3.5 transition-colors"
                        :class="expandedLayerSet.has(layer.id) ? 'text-blue-600 dark:text-blue-400' : ''"
                        aria-hidden="true"
                      />
                    </button>
                  </div>
                </div>

                <!-- Expanded Controls Drawer -->
                <div
                  v-if="expandedLayerSet.has(layer.id)"
                  class="px-3.5 py-3 border-t border-slate-100 dark:border-white/[0.06] bg-slate-50/70 dark:bg-white/[0.02] space-y-3"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-1.5 flex-wrap">
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
                      />
                      <span class="tabular-nums text-slate-600 dark:text-slate-300 font-medium">
                        order {{ getAssignedOrder(stackIndex) }} · z-index {{ getAssignedZIndex(stackIndex) }}
                      </span>
                    </div>

                    <span v-if="layer.layer_name" class="text-[11px] font-mono text-slate-600 dark:text-slate-300 truncate max-w-xs">
                      {{ layer.layer_name }}
                    </span>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                    <div v-if="canEdit" class="flex items-center gap-2">
                      <label
                        :for="`rank-select-${layer.id}`"
                        class="text-xs font-medium text-slate-700 dark:text-slate-200 shrink-0"
                      >
                        Pindah Urutan:
                      </label>
                      <select
                        :id="`rank-select-${layer.id}`"
                        :value="stackIndex + 1"
                        :disabled="isStackFiltered || activeStack.length <= 1"
                        class="min-h-[44px] sm:min-h-[38px] flex-1 px-2.5 rounded-lg border border-slate-200 dark:border-white/[0.1] bg-white dark:bg-slate-900 text-xs font-semibold tabular-nums text-slate-800 dark:text-slate-100 disabled:opacity-45 cursor-pointer"
                        @change="handleRankSelectChange(stackIndex, $event)"
                      >
                        <option
                          v-for="rank in activeStack.length"
                          :key="`rank-opt-${layer.id}-${rank}`"
                          :value="rank"
                        >
                          #{{ rank }} {{ rank === 1 ? '(Teratas)' : rank === activeStack.length ? '(Dasar)' : '' }}
                        </option>
                      </select>
                    </div>

                    <div class="flex items-center gap-2.5">
                      <label
                        :for="`opacity-range-${layer.id}`"
                        class="text-xs font-medium text-slate-700 dark:text-slate-200 shrink-0"
                      >
                        Opasitas
                      </label>
                      <input
                        :id="`opacity-range-${layer.id}`"
                        type="range"
                        min="0"
                        max="100"
                        step="5"
                        :value="Math.round((layer.opacity ?? 1) * 100)"
                        :disabled="!canEdit"
                        class="flex-1 accent-blue-600 h-2 rounded-lg cursor-pointer disabled:opacity-50"
                        @input="updateOpacity(layer, ($event.target as HTMLInputElement).value)"
                      />
                      <span class="text-xs font-semibold tabular-nums text-slate-800 dark:text-slate-100 w-10 text-right shrink-0">
                        {{ Math.round((layer.opacity ?? 1) * 100) }}%
                      </span>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <NuxtLink
                      :to="`/admin/layers/${layer.id}?tab=style`"
                      class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline min-h-[36px] inline-flex items-center gap-1"
                    >
                      <UIcon name="i-lucide-palette" class="size-3.5" aria-hidden="true" />
                      <span>Atur Style Layer</span>
                    </NuxtLink>

                    <UButton
                      v-if="canEdit"
                      label="Keluarkan dari /maps"
                      icon="i-lucide-arrow-left"
                      size="xs"
                      color="error"
                      variant="subtle"
                      class="min-h-[40px] cursor-pointer"
                      @click="removeSingleLayerFromMaps(layer.id)"
                    />
                  </div>
                </div>
              </div>
            </TransitionGroup>
          </div>
        </div>
      </div>

      <!-- Sticky Bottom Save Reminder Bar -->
      <div
        v-if="canEdit && hasUnsavedChanges"
        class="sticky bottom-3 z-20 rounded-xl border border-blue-300 dark:border-blue-700/60 bg-white dark:bg-[#0c1424] shadow-lg p-3.5 sm:px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      >
        <div class="flex items-center gap-2.5 text-xs text-slate-800 dark:text-slate-100">
          <UIcon name="i-lucide-layers-2" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <span>
            <strong class="tabular-nums">{{ changedPayloadItems.length }}</strong> perubahan konfigurasi layer aktif belum disimpan ke halaman /maps.
          </span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <UButton
            label="Batalkan"
            color="neutral"
            variant="outline"
            :disabled="saving"
            class="min-h-[44px] sm:min-h-[38px] cursor-pointer"
            @click="resetDraft"
          />
          <UButton
            label="Simpan Konfigurasi"
            icon="i-heroicons-check"
            color="primary"
            :loading="saving"
            class="min-h-[44px] sm:min-h-[38px] cursor-pointer font-semibold"
            @click="saveConfiguration"
          />
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.layer-card-move {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.layer-card-enter-active,
.layer-card-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.layer-card-enter-from,
.layer-card-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

@media (prefers-reduced-motion: reduce) {
  .layer-card-move,
  .layer-card-enter-active,
  .layer-card-leave-active {
    transition: none;
  }
}
</style>

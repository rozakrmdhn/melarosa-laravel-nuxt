<script setup lang="ts">
import BasemapThumbnail from '~/components/common/BasemapThumbnail.vue';
import { useBasemapStore } from '~/stores/basemap';
import { usePermission } from '~/composables/usePermission';
import {
  BASEMAP_PREVIEW_STYLE_OPTIONS,
  type BasemapItem,
  type BasemapPreviewStyle,
} from '~/types/basemap';

definePageMeta({
  middleware: ['auth'],
});

useSeoMeta({
  title: 'Pengaturan Basemap Peta',
});

const basemapStore = useBasemapStore();
const { can, isAdmin } = usePermission();

const canManage = computed(
  () => isAdmin() || can('layers-manage') || can('settings-manage'),
);

const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const engineFilter = ref<'all' | 'openlayers' | 'maplibre'>('all');
const viewMode = ref<'grid' | 'table'>('grid');
const basemaps = ref<BasemapItem[]>([]);
const pageFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null);

const statusOptions = [
  { label: 'Semua Status', value: 'all' },
  { label: 'Aktif Saja', value: 'active' },
  { label: 'Nonaktif Saja', value: 'inactive' },
];

const engineOptions = [
  { label: 'Semua Engine Peta', value: 'all' },
  { label: 'OpenLayers', value: 'openlayers' },
  { label: 'MapLibre GL JS', value: 'maplibre' },
];

const quickFilterTabs = [
  { key: 'all', label: 'Semua Basemap', icon: 'i-lucide-globe' },
  { key: 'active', label: 'Aktif', icon: 'i-lucide-check-circle-2' },
  { key: 'openlayers', label: 'OpenLayers', icon: 'i-lucide-layers' },
  { key: 'maplibre', label: 'MapLibre GL', icon: 'i-lucide-compass' },
] as const;

const activeQuickTab = computed<string>(() => {
  if (engineFilter.value === 'openlayers') return 'openlayers';
  if (engineFilter.value === 'maplibre') return 'maplibre';
  if (statusFilter.value === 'active') return 'active';
  return 'all';
});

function selectQuickTab(key: (typeof quickFilterTabs)[number]['key']) {
  if (key === 'all') {
    statusFilter.value = 'all';
    engineFilter.value = 'all';
  } else if (key === 'active') {
    statusFilter.value = 'active';
    engineFilter.value = 'all';
  } else if (key === 'openlayers') {
    statusFilter.value = 'all';
    engineFilter.value = 'openlayers';
  } else if (key === 'maplibre') {
    statusFilter.value = 'all';
    engineFilter.value = 'maplibre';
  }
}

const filteredBasemaps = computed(() => {
  return basemaps.value.filter((bm) => {
    if (statusFilter.value === 'active' && !bm.is_active) return false;
    if (statusFilter.value === 'inactive' && bm.is_active) return false;
    if (engineFilter.value === 'openlayers' && !bm.supports_openlayers) return false;
    if (engineFilter.value === 'maplibre' && !bm.supports_maplibre) return false;
    return true;
  });
});

const hasActiveFilters = computed(
  () => Boolean(searchQuery.value.trim()) || statusFilter.value !== 'all' || engineFilter.value !== 'all',
);

function resetFilters() {
  searchQuery.value = '';
  statusFilter.value = 'all';
  engineFilter.value = 'all';
}

// Modal Tambah / Edit
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref<string | null>(null);
const saving = ref(false);
const modalFeedback = ref<{ type: 'error'; message: string } | null>(null);

const formState = reactive({
  kode: '',
  nama: '',
  label_singkat: '',
  provider: '',
  tagline: '',
  tipe: 'raster' as 'raster' | 'vector',
  url_template: '',
  fallback_url_template: '',
  tile_urls_text: '',
  attribution: '',
  thumbnail_url: '',
  preview_style: 'esri' as BasemapPreviewStyle,
  min_zoom: 0,
  max_zoom: 19,
  tile_size: 256,
  supports_openlayers: true,
  supports_maplibre: true,
  is_default: false,
  is_active: true,
  sort_order: 1,
});

// Modal Hapus
const isDeleteOpen = ref(false);
const itemToDelete = ref<BasemapItem | null>(null);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

const tipeOptions = [
  { label: 'Raster (XYZ / Tile)', value: 'raster' },
  { label: 'Vector / Peta Jalan', value: 'vector' },
];

const tileSizeOptions = [
  { label: '256 px (Standar)', value: 256 },
  { label: '512 px (HiDPI / Retina)', value: 512 },
];

async function loadBasemaps() {
  loading.value = true;
  try {
    const res = await basemapStore.fetchAdminBasemaps(searchQuery.value);
    basemaps.value = res?.data || [];
  } catch (err: any) {
    pageFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal memuat daftar basemap.',
    };
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  modalFeedback.value = null;
  Object.assign(formState, {
    kode: '',
    nama: '',
    label_singkat: '',
    provider: '',
    tagline: '',
    tipe: 'raster',
    url_template: '',
    fallback_url_template: '',
    tile_urls_text: '',
    attribution: '',
    thumbnail_url: '',
    preview_style: 'esri',
    min_zoom: 0,
    max_zoom: 19,
    tile_size: 256,
    supports_openlayers: true,
    supports_maplibre: true,
    is_default: false,
    is_active: true,
    sort_order: basemaps.value.length + 1,
  });
  isModalOpen.value = true;
}

function openEditModal(item: BasemapItem) {
  isEditing.value = true;
  editingId.value = item.id;
  modalFeedback.value = null;
  Object.assign(formState, {
    kode: item.kode,
    nama: item.nama,
    label_singkat: item.label_singkat,
    provider: item.provider || '',
    tagline: item.tagline || '',
    tipe: item.tipe || 'raster',
    url_template: item.url_template,
    fallback_url_template: item.fallback_url_template || '',
    tile_urls_text: Array.isArray(item.tile_urls) ? item.tile_urls.join('\n') : '',
    attribution: item.attribution || '',
    thumbnail_url: item.thumbnail_url || '',
    preview_style: (item.preview_style || 'esri') as BasemapPreviewStyle,
    min_zoom: item.min_zoom ?? 0,
    max_zoom: item.max_zoom ?? 19,
    tile_size: item.tile_size ?? 256,
    supports_openlayers: Boolean(item.supports_openlayers),
    supports_maplibre: Boolean(item.supports_maplibre),
    is_default: Boolean(item.is_default),
    is_active: Boolean(item.is_active),
    sort_order: item.sort_order ?? 1,
  });
  isModalOpen.value = true;
}

async function handleSave() {
  modalFeedback.value = null;
  if (!formState.kode.trim() || !formState.nama.trim() || !formState.label_singkat.trim() || !formState.url_template.trim()) {
    modalFeedback.value = { type: 'error', message: 'Kode, Nama, Label Singkat, dan URL Tile wajib diisi.' };
    return;
  }

  const parsedTileUrls = formState.tile_urls_text
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

  const payload: Partial<BasemapItem> = {
    kode: formState.kode.trim().toLowerCase(),
    nama: formState.nama.trim(),
    label_singkat: formState.label_singkat.trim(),
    provider: formState.provider.trim() || null,
    tagline: formState.tagline.trim() || null,
    tipe: formState.tipe,
    url_template: formState.url_template.trim(),
    fallback_url_template: formState.fallback_url_template.trim() || null,
    tile_urls: parsedTileUrls.length > 0 ? parsedTileUrls : null,
    attribution: formState.attribution.trim() || null,
    thumbnail_url: formState.thumbnail_url.trim() || null,
    preview_style: formState.preview_style,
    min_zoom: Number(formState.min_zoom) || 0,
    max_zoom: Number(formState.max_zoom) || 19,
    tile_size: Number(formState.tile_size) || 256,
    supports_openlayers: formState.supports_openlayers,
    supports_maplibre: formState.supports_maplibre,
    is_default: formState.is_default,
    is_active: formState.is_active,
    sort_order: Number(formState.sort_order) || 0,
  };

  saving.value = true;
  try {
    if (isEditing.value && editingId.value) {
      const res = await basemapStore.updateBasemap(editingId.value, payload);
      pageFeedback.value = { type: 'success', message: res?.message || 'Basemap diperbarui.' };
    } else {
      const res = await basemapStore.createBasemap(payload);
      pageFeedback.value = { type: 'success', message: res?.message || 'Basemap ditambahkan.' };
    }
    isModalOpen.value = false;
    await loadBasemaps();
  } catch (err: any) {
    modalFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menyimpan pengaturan basemap.',
    };
  } finally {
    saving.value = false;
  }
}

async function handleSetDefault(item: BasemapItem) {
  try {
    const res = await basemapStore.setDefaultBasemap(item.id);
    pageFeedback.value = { type: 'success', message: res?.message || `${item.nama} menjadi default.` };
    await loadBasemaps();
  } catch (err: any) {
    pageFeedback.value = { type: 'error', message: err?.data?.message || 'Gagal mengubah basemap default.' };
  }
}

async function handleToggleActive(item: BasemapItem) {
  if (item.is_default && item.is_active) {
    pageFeedback.value = { type: 'error', message: 'Basemap default tidak dapat dinonaktifkan.' };
    return;
  }
  try {
    await basemapStore.updateBasemap(item.id, { is_active: !item.is_active });
    pageFeedback.value = {
      type: 'success',
      message: `Basemap ${item.nama} ${!item.is_active ? 'diaktifkan' : 'dinonaktifkan'}.`,
    };
    await loadBasemaps();
  } catch (err: any) {
    pageFeedback.value = { type: 'error', message: err?.data?.message || 'Gagal mengubah status aktif.' };
  }
}

function openDeleteModal(item: BasemapItem) {
  itemToDelete.value = item;
  deleteError.value = null;
  isDeleteOpen.value = true;
}

async function handleConfirmDelete() {
  if (!itemToDelete.value || deleting.value) return;
  deleting.value = true;
  deleteError.value = null;
  try {
    const res = await basemapStore.deleteBasemap(itemToDelete.value.id);
    isDeleteOpen.value = false;
    pageFeedback.value = { type: 'success', message: res?.message || 'Basemap dihapus.' };
    itemToDelete.value = null;
    await loadBasemaps();
  } catch (err: any) {
    deleteError.value = err?.data?.message || 'Gagal menghapus basemap.';
  } finally {
    deleting.value = false;
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    loadBasemaps();
  }, 300);
});

onMounted(() => {
  loadBasemaps();
});
</script>

<template>
  <div class="space-y-5">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <UIcon name="i-lucide-globe" class="size-5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
            Pengaturan Basemap Peta
          </h1>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Kelola daftar peta dasar (basemap) secara terpusat untuk seluruh halaman OpenLayers dan MapLibre GL JS (/maps, MiniMap, Dialog Peta, Georeferencing, dan /admin/infrastruktur-segmen).
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <UButton
          icon="i-heroicons-arrow-path"
          label="Refresh"
          aria-label="Muat ulang daftar basemap"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          @click="loadBasemaps"
        />
        <UButton
          v-if="canManage"
          icon="i-heroicons-plus"
          label="Tambah Basemap"
          aria-label="Tambah basemap peta baru"
          color="primary"
          size="sm"
          class="min-h-[44px] sm:min-h-[38px] px-3.5 whitespace-nowrap cursor-pointer font-semibold"
          @click="openCreateModal"
        />
      </div>
    </header>

    <!-- Filter & Toolbar Card -->
    <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0c1424] space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100 dark:border-white/[0.06]">
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 text-xs">
          <button
            v-for="tab in quickFilterTabs"
            :key="tab.key"
            type="button"
            class="px-3 py-1.5 min-h-[38px] rounded-lg text-xs font-medium transition-colors cursor-pointer flex items-center gap-2 shrink-0 border"
            :class="activeQuickTab === tab.key
              ? 'bg-blue-600 text-white border-blue-600 font-semibold'
              : 'bg-gray-50 dark:bg-white/[0.03] border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]'"
            @click="selectQuickTab(tab.key)"
          >
            <UIcon :name="tab.icon" class="size-3.5 shrink-0" aria-hidden="true" />
            <span>{{ tab.label }}</span>
          </button>
        </div>

        <div class="flex items-center gap-3 self-start sm:self-center shrink-0">
          <span class="text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">
            Menampilkan {{ filteredBasemaps.length }} dari {{ basemaps.length }} basemap
          </span>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer shrink-0"
            @click="resetFilters"
          >
            Reset Filter
          </button>
          <div class="inline-flex items-center rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.03] p-0.5">
            <button
              type="button"
              aria-label="Tampilan grid kartu"
              class="px-2 py-1 rounded-md text-xs font-medium flex items-center gap-1 transition-colors cursor-pointer"
              :class="viewMode === 'grid' ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="viewMode = 'grid'"
            >
              <UIcon name="i-lucide-layout-grid" class="size-3.5" aria-hidden="true" />
              <span class="hidden sm:inline">Grid</span>
            </button>
            <button
              type="button"
              aria-label="Tampilan tabel full width"
              class="px-2 py-1 rounded-md text-xs font-medium flex items-center gap-1 transition-colors cursor-pointer"
              :class="viewMode === 'table' ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              @click="viewMode = 'table'"
            >
              <UIcon name="i-lucide-table" class="size-3.5" aria-hidden="true" />
              <span class="hidden sm:inline">Tabel</span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
          <UInput
            v-model="searchQuery"
            icon="i-lucide-search"
            placeholder="Cari kode, nama basemap, atau provider..."
            aria-label="Cari basemap"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="statusFilter"
            :items="statusOptions"
            value-key="value"
            label-key="label"
            aria-label="Filter status aktif basemap"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="engineFilter"
            :items="engineOptions"
            value-key="value"
            label-key="label"
            aria-label="Filter engine peta"
            class="w-full"
            size="sm"
          />
        </div>
      </div>
    </div>

    <!-- Inline Page Feedback -->
    <div
      v-if="pageFeedback"
      role="status"
      :class="[
        'px-4 py-2.5 rounded-xl border text-xs flex items-center justify-between gap-2',
        pageFeedback.type === 'success'
          ? 'bg-emerald-50/90 dark:bg-emerald-950/35 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100'
          : 'bg-red-50/90 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100',
      ]"
    >
      <span class="font-medium">{{ pageFeedback.message }}</span>
      <button
        type="button"
        aria-label="Tutup pesan"
        class="p-0.5 rounded opacity-75 hover:opacity-100 cursor-pointer"
        @click="pageFeedback = null"
      >
        <UIcon name="i-lucide-x" class="size-3.5" aria-hidden="true" />
      </button>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && basemaps.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <USkeleton v-for="i in 8" :key="i" class="h-56 rounded-xl" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredBasemaps.length === 0"
      class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-12 text-center space-y-3"
    >
      <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-globe" class="size-6" aria-hidden="true" />
      </div>
      <p class="text-sm font-semibold text-gray-900 dark:text-white">
        Tidak ada basemap yang cocok
      </p>
      <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
        Ubah kata kunci pencarian atau reset filter untuk melihat seluruh daftar basemap peta.
      </p>
      <div class="pt-2 flex items-center justify-center gap-2">
        <UButton
          v-if="hasActiveFilters"
          label="Reset Filter"
          size="sm"
          color="neutral"
          variant="outline"
          class="cursor-pointer"
          @click="resetFilters"
        />
        <UButton
          v-if="canManage"
          label="Tambah Basemap"
          icon="i-heroicons-plus"
          size="sm"
          color="primary"
          class="cursor-pointer"
          @click="openCreateModal"
        />
      </div>
    </div>

    <!-- Full-Width Grid Basemap Cards -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <article
        v-for="bm in filteredBasemaps"
        :key="bm.id"
        class="rounded-xl border bg-white dark:bg-[#0b0f19] overflow-hidden flex flex-col justify-between transition-all"
        :class="
          bm.is_default
            ? 'border-blue-500 dark:border-blue-500 ring-1 ring-blue-500/25'
            : bm.is_active
              ? 'border-gray-200 dark:border-white/[0.08]'
              : 'border-gray-200 dark:border-white/[0.08] opacity-75'
        "
      >
        <div>
          <!-- Live Thumbnail Banner -->
          <div class="relative h-32 bg-gray-100 dark:bg-gray-900 border-b border-gray-200 dark:border-white/[0.08]">
            <BasemapThumbnail
              :preview-style="bm.preview_style"
              :thumbnail-url="bm.thumbnail_url"
              :alt="bm.nama"
            />
            <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5">
              <span class="px-2.5 py-0.5 rounded-md bg-slate-950/80 text-white text-xs font-mono font-semibold">
                #{{ bm.sort_order }} · {{ bm.kode }}
              </span>
              <span
                v-if="bm.is_default"
                class="px-2.5 py-0.5 rounded-md bg-blue-600 text-white text-xs font-semibold"
              >
                Default
              </span>
            </div>
            <div class="absolute top-2.5 right-2.5">
              <UBadge
                :label="bm.is_active ? 'Aktif' : 'Nonaktif'"
                :color="bm.is_active ? 'success' : 'neutral'"
                variant="solid"
                size="md"
                class="font-semibold"
              />
            </div>
          </div>

          <div class="p-4 space-y-2.5 text-xs">
            <div>
              <h2 class="font-bold text-sm text-gray-900 dark:text-white leading-tight">
                {{ bm.nama }}
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ bm.label_singkat }})</span>
              </h2>
              <p v-if="bm.provider" class="text-[11px] text-gray-600 dark:text-gray-400 mt-0.5">
                {{ bm.provider }}
              </p>
              <p v-if="bm.tagline" class="text-xs text-gray-600 dark:text-gray-300 mt-1.5 line-clamp-2">
                {{ bm.tagline }}
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 pt-1">
              <UBadge
                v-if="bm.supports_openlayers"
                label="OpenLayers"
                color="info"
                variant="subtle"
                size="md"
                class="font-semibold"
              />
              <UBadge
                v-if="bm.supports_maplibre"
                label="MapLibre GL"
                color="primary"
                variant="subtle"
                size="md"
                class="font-semibold"
              />
              <UBadge
                :label="`Zoom ${bm.min_zoom}-${bm.max_zoom}`"
                color="neutral"
                variant="subtle"
                size="md"
                class="tabular-nums font-medium"
              />
            </div>

            <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 truncate" :title="bm.url_template">
              {{ bm.url_template }}
            </p>
          </div>
        </div>

        <div
          v-if="canManage"
          class="px-4 py-2.5 border-t border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-[#070b14]/60 flex items-center justify-between gap-2"
        >
          <div class="flex items-center gap-1">
            <UButton
              v-if="!bm.is_default"
              label="Jadikan Default"
              icon="i-lucide-star"
              size="xs"
              color="primary"
              variant="subtle"
              class="cursor-pointer"
              @click="handleSetDefault(bm)"
            />
            <UButton
              v-if="!bm.is_default"
              :label="bm.is_active ? 'Nonaktifkan' : 'Aktifkan'"
              size="xs"
              color="neutral"
              variant="ghost"
              class="cursor-pointer"
              @click="handleToggleActive(bm)"
            />
          </div>

          <div class="flex items-center gap-1">
            <UButton
              icon="i-lucide-pencil"
              size="xs"
              color="neutral"
              variant="ghost"
              aria-label="Edit basemap"
              class="cursor-pointer"
              @click="openEditModal(bm)"
            />
            <UButton
              v-if="!bm.is_default"
              icon="i-lucide-trash-2"
              size="xs"
              color="error"
              variant="ghost"
              aria-label="Hapus basemap"
              class="cursor-pointer"
              @click="openDeleteModal(bm)"
            />
          </div>
        </div>
      </article>
    </div>

    <!-- Full-Width Table View -->
    <div v-else class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-3 w-16 text-center">Urutan</th>
              <th scope="col" class="py-3 px-4">Pratinjau &amp; Nama Basemap</th>
              <th scope="col" class="py-3 px-3">Provider &amp; Tipe</th>
              <th scope="col" class="py-3 px-3">Dukungan Engine</th>
              <th scope="col" class="py-3 px-3 text-center">Zoom</th>
              <th scope="col" class="py-3 px-3 text-center">Status</th>
              <th v-if="canManage" scope="col" class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="bm in filteredBasemaps"
              :key="`tbl-${bm.id}`"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <td class="py-3 px-3 text-center tabular-nums font-semibold text-gray-800 dark:text-gray-200">
                #{{ bm.sort_order }}
              </td>
              <td class="py-3 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-16 h-11 rounded-lg overflow-hidden border border-gray-200 dark:border-white/[0.08] shrink-0 bg-gray-100 dark:bg-gray-900">
                    <BasemapThumbnail
                      :preview-style="bm.preview_style"
                      :thumbnail-url="bm.thumbnail_url"
                      :alt="bm.nama"
                    />
                  </div>
                  <div class="min-w-0 space-y-0.5">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ bm.nama }}</span>
                      <span class="text-xs text-gray-500 dark:text-gray-400">({{ bm.label_singkat }})</span>
                      <UBadge v-if="bm.is_default" label="Default" color="primary" variant="solid" size="md" class="font-semibold" />
                    </div>
                    <p class="font-mono text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-md" :title="bm.url_template">
                      {{ bm.kode }} · {{ bm.url_template }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="py-3 px-3">
                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ bm.provider || '-' }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase">{{ bm.tipe }}</p>
              </td>
              <td class="py-3 px-3">
                <div class="flex flex-wrap items-center gap-1.5">
                  <UBadge v-if="bm.supports_openlayers" label="OpenLayers" color="info" variant="subtle" size="md" class="font-semibold" />
                  <UBadge v-if="bm.supports_maplibre" label="MapLibre GL" color="primary" variant="subtle" size="md" class="font-semibold" />
                </div>
              </td>
              <td class="py-3 px-3 text-center tabular-nums font-medium">
                {{ bm.min_zoom }} - {{ bm.max_zoom }}
              </td>
              <td class="py-3 px-3 text-center">
                <UBadge
                  :label="bm.is_active ? 'Aktif' : 'Nonaktif'"
                  :color="bm.is_active ? 'success' : 'neutral'"
                  variant="subtle"
                  size="md"
                  class="font-semibold"
                />
              </td>
              <td v-if="canManage" class="py-3 px-4 text-right">
                <div class="inline-flex items-center gap-1">
                  <UButton
                    v-if="!bm.is_default"
                    label="Default"
                    icon="i-lucide-star"
                    size="xs"
                    color="primary"
                    variant="subtle"
                    class="cursor-pointer"
                    @click="handleSetDefault(bm)"
                  />
                  <UButton
                    v-if="!bm.is_default"
                    :label="bm.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    class="cursor-pointer"
                    @click="handleToggleActive(bm)"
                  />
                  <UButton
                    icon="i-lucide-pencil"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    aria-label="Edit basemap"
                    class="cursor-pointer"
                    @click="openEditModal(bm)"
                  />
                  <UButton
                    v-if="!bm.is_default"
                    icon="i-lucide-trash-2"
                    size="xs"
                    color="error"
                    variant="ghost"
                    aria-label="Hapus basemap"
                    class="cursor-pointer"
                    @click="openDeleteModal(bm)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Tambah / Edit Basemap -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? `Edit Basemap: ${formState.nama}` : 'Tambah Basemap Peta Baru'"
      description="Konfigurasi sumber tile XYZ untuk digunakan di seluruh peta OpenLayers dan MapLibre GL JS."
      :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-gray-800' }"
    >
      <template #body>
        <form class="space-y-3.5 text-xs" @submit.prevent="handleSave">
          <div
            v-if="modalFeedback"
            role="alert"
            class="p-2.5 rounded-lg bg-red-50/90 dark:bg-red-950/35 border border-red-200 dark:border-red-800/80 font-medium text-red-900 dark:text-red-100"
          >
            {{ modalFeedback.message }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <UFormField label="Kode Unik" required size="sm">
              <UInput
                v-model="formState.kode"
                placeholder="mis. esri_street"
                :disabled="isEditing"
                class="w-full font-mono"
              />
            </UFormField>
            <UFormField label="Nama Lengkap" required size="sm">
              <UInput v-model="formState.nama" placeholder="mis. Esri World Street" class="w-full" />
            </UFormField>
            <UFormField label="Label Singkat (Mini)" required size="sm">
              <UInput v-model="formState.label_singkat" placeholder="mis. Street" class="w-full" />
            </UFormField>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <UFormField label="Provider" size="sm">
              <UInput v-model="formState.provider" placeholder="Esri / BIG / Google / CARTO" class="w-full" />
            </UFormField>
            <UFormField label="Tipe Layer" size="sm">
              <USelectMenu
                v-model="formState.tipe"
                :items="tipeOptions"
                value-key="value"
                label-key="label"
                :search-input="false"
                class="w-full"
              />
            </UFormField>
            <UFormField label="Gaya Pratinjau Thumbnail" size="sm">
              <USelectMenu
                v-model="formState.preview_style"
                :items="BASEMAP_PREVIEW_STYLE_OPTIONS"
                value-key="value"
                label-key="label"
                :search-input="false"
                class="w-full"
              />
            </UFormField>
          </div>

          <UFormField label="Tagline / Deskripsi Singkat" size="sm">
            <UInput v-model="formState.tagline" placeholder="Penjelasan singkat kegunaan basemap..." class="w-full" />
          </UFormField>

          <UFormField label="URL Tile Template Utama" required size="sm">
            <UInput
              v-model="formState.url_template"
              placeholder="https://tile.openstreetmap.org/{z}/{x}/{y}.png atau /rbi-tiles/{z}/{y}/{x}"
              class="w-full font-mono"
            />
          </UFormField>

          <UFormField label="URL Fallback (Opsional, bila proxy lokal gagal)" size="sm">
            <UInput
              v-model="formState.fallback_url_template"
              placeholder="https://geoservices.big.go.id/..."
              class="w-full font-mono"
            />
          </UFormField>

          <UFormField label="Multi-Subdomain Tile URLs (Opsional, 1 URL per baris)" size="sm">
            <UTextarea
              v-model="formState.tile_urls_text"
              :rows="2"
              placeholder="https://mt0.google.com/vt/lyrs=y&x={x}&y={y}&z={z}"
              class="w-full font-mono"
            />
          </UFormField>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <UFormField label="Min Zoom" size="sm">
              <UInput v-model.number="formState.min_zoom" type="number" min="0" max="22" class="w-full font-mono" />
            </UFormField>
            <UFormField label="Max Zoom" size="sm">
              <UInput v-model.number="formState.max_zoom" type="number" min="1" max="22" class="w-full font-mono" />
            </UFormField>
            <UFormField label="Tile Size" size="sm">
              <USelectMenu
                v-model="formState.tile_size"
                :items="tileSizeOptions"
                value-key="value"
                label-key="label"
                :search-input="false"
                class="w-full"
              />
            </UFormField>
            <UFormField label="Urutan" size="sm">
              <UInput v-model.number="formState.sort_order" type="number" min="0" class="w-full font-mono" />
            </UFormField>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Attribution" size="sm">
              <UInput v-model="formState.attribution" placeholder="© Penyedia Peta" class="w-full" />
            </UFormField>
            <UFormField label="URL Thumbnail Kustom (Opsional)" size="sm">
              <UInput v-model="formState.thumbnail_url" placeholder="https://..." class="w-full font-mono" />
            </UFormField>
          </div>

          <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 grid grid-cols-2 gap-2.5">
            <UCheckbox v-model="formState.supports_openlayers" label="Aktif di OpenLayers" />
            <UCheckbox v-model="formState.supports_maplibre" label="Aktif di MapLibre GL JS" />
            <UCheckbox v-model="formState.is_active" label="Status Aktif" />
            <UCheckbox v-model="formState.is_default" label="Jadikan Basemap Default" />
          </div>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton label="Batal" color="neutral" variant="ghost" :disabled="saving" @click="isModalOpen = false" />
          <UButton
            :label="isEditing ? 'Simpan Perubahan' : 'Buat Basemap'"
            color="primary"
            :loading="saving"
            @click="handleSave"
          />
        </div>
      </template>
    </UModal>

    <!-- Modal Hapus Basemap -->
    <UModal
      v-model:open="isDeleteOpen"
      :dismissible="!deleting"
      title="Hapus Basemap"
      description="Konfirmasi penghapusan basemap dari database."
      :ui="{ content: 'max-w-sm dark:bg-[#0b0f19] dark:border-gray-800' }"
    >
      <template #body>
        <div class="space-y-3 text-xs">
          <div
            v-if="deleteError"
            role="alert"
            class="p-2.5 rounded-lg bg-red-50/90 dark:bg-red-950/35 border border-red-200 dark:border-red-800/80 font-medium text-red-900 dark:text-red-100"
          >
            {{ deleteError }}
          </div>
          <p class="text-gray-600 dark:text-gray-300">
            Apakah Anda yakin ingin menghapus basemap
            <strong class="text-gray-900 dark:text-white">{{ itemToDelete?.nama }}</strong>?
          </p>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton label="Batal" color="neutral" variant="ghost" :disabled="deleting" @click="isDeleteOpen = false" />
          <UButton label="Hapus" color="error" :loading="deleting" @click="handleConfirmDelete" />
        </div>
      </template>
    </UModal>
  </div>
</template>

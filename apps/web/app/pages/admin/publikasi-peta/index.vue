<script setup lang="ts">
import { resolvePublikasiDownloadUrl, usePublikasiPetaApi } from "~/composables/usePublikasiPetaApi";
import { usePermission } from "~/composables/usePermission";
import PublikasiMapViewerModal from "~/components/publikasi-peta/PublikasiMapViewerModal.vue";
import {
  PUBLIKASI_KATEGORI_OPTIONS,
  PUBLIKASI_TINGKAT_OPTIONS,
  type PublikasiPetaDigitalItem,
  type PublikasiSumberTipe,
  type PublikasiTingkatWilayah,
  type PublikasiWilayahDesa,
  type PublikasiWilayahKecamatan,
} from "~/types/publikasi-peta";

definePageMeta({
  middleware: ["auth"],
});

useSeoMeta({
  title: "Kelola Publikasi Peta Digital",
});

const api = usePublikasiPetaApi();
const { can, isAdmin } = usePermission();

const canManage = computed(
  () => isAdmin() || can("layers-manage") || can("batas-desa-manage") || can("settings-manage"),
);

const loading = ref(false);
const items = ref<PublikasiPetaDigitalItem[]>([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const pageFeedback = ref<{ type: "success" | "error"; message: string } | null>(null);

const kecamatanList = ref<PublikasiWilayahKecamatan[]>([]);
const filterDesaList = ref<PublikasiWilayahDesa[]>([]);
const formDesaList = ref<PublikasiWilayahDesa[]>([]);

const filters = reactive({
  search: "",
  tingkat_wilayah: "",
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  is_published: "",
  page: 1,
});

// Create / Edit Modal
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingItem = ref<PublikasiPetaDigitalItem | null>(null);
const saving = ref(false);
const modalFeedback = ref<string | null>(null);

const selectedPdfFile = ref<File | null>(null);
const pdfInputRef = ref<HTMLInputElement | null>(null);

const formState = reactive({
  judul: "",
  nomor_dokumen: "",
  kategori: PUBLIKASI_KATEGORI_OPTIONS[0] as string,
  tingkat_wilayah: "desa" as PublikasiTingkatWilayah,
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  tahun_terbit: new Date().getFullYear(),
  edisi: "",
  skala_peta: "",
  ringkasan: "",
  sumber_tipe: "file_pdf" as PublikasiSumberTipe,
  external_url: "",
  is_published: true,
});

// Delete Modal
const isDeleteOpen = ref(false);
const itemToDelete = ref<PublikasiPetaDigitalItem | null>(null);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

// MapLibre GL JS Coordinate Viewer Modal
const isMapViewerOpen = ref(false);
const mapViewerTargetItem = ref<PublikasiPetaDigitalItem | null>(null);

const ALL_VALUE = "__ALL__";

const kecamatanSelectItems = computed(() => [
  { label: "Semua Kecamatan", value: ALL_VALUE },
  ...kecamatanList.value.map((k) => ({ label: `Kec. ${k.nama_kecamatan}`, value: String(k.id) })),
]);

const formKecamatanItems = computed(() =>
  kecamatanList.value.map((k) => ({ label: `Kec. ${k.nama_kecamatan}`, value: String(k.id) })),
);

const filterDesaItems = computed(() => [
  { label: filters.id_kecamatan ? "Semua Desa" : "Pilih Kecamatan", value: ALL_VALUE },
  ...filterDesaList.value.map((d) => ({ label: d.nama_desa, value: String(d.id) })),
]);

const formDesaItems = computed(() =>
  formDesaList.value.map((d) => ({ label: d.nama_desa, value: String(d.id) })),
);

const publishStatusFilterItems = [
  { label: "Semua Status", value: ALL_VALUE },
  { label: "Dipublikasikan", value: "1" },
  { label: "Arsip / Draft", value: "0" },
];

const tingkatFilterItems = [
  { label: "Semua Tingkat", value: ALL_VALUE },
  ...PUBLIKASI_TINGKAT_OPTIONS,
];

const filterTingkatModel = computed<string>({
  get: () => (filters.tingkat_wilayah ? filters.tingkat_wilayah : ALL_VALUE),
  set: (v) => {
    filters.tingkat_wilayah = v === ALL_VALUE ? "" : v;
  },
});

const filterKecamatanModel = computed<string>({
  get: () => (filters.id_kecamatan ? String(filters.id_kecamatan) : ALL_VALUE),
  set: (v) => {
    const next = v === ALL_VALUE ? null : Number(v);
    handleFilterKecamatanChange(Number.isFinite(next) ? next : null);
  },
});

const filterDesaModel = computed<string>({
  get: () => (filters.id_desa ? String(filters.id_desa) : ALL_VALUE),
  set: (v) => {
    const next = v === ALL_VALUE ? null : Number(v);
    filters.id_desa = Number.isFinite(next) ? next : null;
  },
});

const filterPublishedModel = computed<string>({
  get: () => (filters.is_published !== "" ? filters.is_published : ALL_VALUE),
  set: (v) => {
    filters.is_published = v === ALL_VALUE ? "" : v;
  },
});

const tingkatTabs = [
  { value: "", label: "Semua Tingkat", icon: "i-lucide-layers" },
  { value: "desa", label: "Desa", icon: "i-lucide-map-pin" },
  { value: "kecamatan", label: "Kecamatan", icon: "i-lucide-map" },
  { value: "kabupaten", label: "Kabupaten", icon: "i-lucide-globe" },
] as const;

const hasActiveFilters = computed(
  () =>
    Boolean(filters.search.trim()) ||
    Boolean(filters.tingkat_wilayah) ||
    Boolean(filters.id_kecamatan) ||
    Boolean(filters.id_desa) ||
    filters.is_published !== "",
);

function resetFilters() {
  if (searchTimer) {
    clearTimeout(searchTimer);
    searchTimer = null;
  }
  const willTriggerWatch =
    filters.tingkat_wilayah !== "" ||
    filters.id_desa !== null ||
    filters.is_published !== "";
  filters.search = "";
  filters.tingkat_wilayah = "";
  filters.id_kecamatan = null;
  filters.id_desa = null;
  filters.is_published = "";
  filters.page = 1;
  filterDesaList.value = [];
  if (!willTriggerWatch) {
    loadList();
  }
}

const formKecamatanModel = computed<string | undefined>({
  get: () => (formState.id_kecamatan ? String(formState.id_kecamatan) : undefined),
  set: (v) => {
    const next = v ? Number(v) : null;
    handleFormKecamatanChange(Number.isFinite(next) ? next : null);
  },
});

const formDesaModel = computed<string | undefined>({
  get: () => (formState.id_desa ? String(formState.id_desa) : undefined),
  set: (v) => {
    const next = v ? Number(v) : null;
    formState.id_desa = Number.isFinite(next) ? next : null;
  },
});

const kategoriItems = PUBLIKASI_KATEGORI_OPTIONS.map((k) => ({ label: k, value: k }));

async function loadWilayahFor(target: "filter" | "form", idKecamatan?: number | null) {
  try {
    const res = await api.fetchPublicWilayahOptions(idKecamatan);
    if (res?.kecamatan) {
      kecamatanList.value = res.kecamatan;
    }
    if (target === "filter") {
      filterDesaList.value = idKecamatan ? (res?.desa || []) : [];
    } else {
      formDesaList.value = idKecamatan ? (res?.desa || []) : [];
    }
  } catch {}
}

async function loadList() {
  loading.value = true;
  try {
    const res = await api.fetchAdminList({
      page: filters.page,
      per_page: 15,
      search: filters.search.trim() || undefined,
      tingkat_wilayah: filters.tingkat_wilayah || undefined,
      id_kecamatan: filters.id_kecamatan || undefined,
      id_desa: filters.id_desa || undefined,
      is_published: filters.is_published !== "" ? filters.is_published : undefined,
    });
    items.value = res?.data || [];
    if (res?.meta) {
      meta.value = { ...meta.value, ...res.meta };
    }
  } catch (err: any) {
    pageFeedback.value = {
      type: "error",
      message: err?.data?.message || "Gagal memuat daftar publikasi peta digital.",
    };
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  isEditing.value = false;
  editingItem.value = null;
  modalFeedback.value = null;
  selectedPdfFile.value = null;
  Object.assign(formState, {
    judul: "",
    nomor_dokumen: "",
    kategori: PUBLIKASI_KATEGORI_OPTIONS[0],
    tingkat_wilayah: "desa",
    id_kecamatan: null,
    id_desa: null,
    tahun_terbit: new Date().getFullYear(),
    edisi: "",
    skala_peta: "",
    ringkasan: "",
    sumber_tipe: "file_pdf",
    external_url: "",
    is_published: true,
  });
  formDesaList.value = [];
  isModalOpen.value = true;
}

async function openEditModal(item: PublikasiPetaDigitalItem) {
  isEditing.value = true;
  editingItem.value = item;
  modalFeedback.value = null;
  selectedPdfFile.value = null;
  Object.assign(formState, {
    judul: item.judul,
    nomor_dokumen: item.nomor_dokumen || "",
    kategori: item.kategori,
    tingkat_wilayah: item.tingkat_wilayah,
    id_kecamatan: item.id_kecamatan || null,
    id_desa: item.id_desa || null,
    tahun_terbit: item.tahun_terbit,
    edisi: item.edisi || "",
    skala_peta: item.skala_peta || "",
    ringkasan: item.ringkasan || "",
    sumber_tipe: item.sumber_tipe,
    external_url: item.external_url || "",
    is_published: item.is_published,
  });
  if (item.id_kecamatan) {
    await loadWilayahFor("form", item.id_kecamatan);
  } else {
    formDesaList.value = [];
  }
  isModalOpen.value = true;
}

function handlePdfFileChange(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] || null;
  modalFeedback.value = null;

  if (!file) {
    selectedPdfFile.value = null;
    return;
  }

  const isPdf = file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf");
  if (!isPdf) {
    modalFeedback.value = "Hanya berkas PDF (.pdf) yang diperbolehkan.";
    selectedPdfFile.value = null;
    input.value = "";
    return;
  }

  if (file.size > 26214400) {
    modalFeedback.value = "Ukuran berkas PDF melebihi batas maksimal 25 MB.";
    selectedPdfFile.value = null;
    input.value = "";
    return;
  }

  selectedPdfFile.value = file;
}

function handleFormKecamatanChange(val: number | null) {
  formState.id_kecamatan = val;
  formState.id_desa = null;
  if (val) {
    loadWilayahFor("form", val);
  } else {
    formDesaList.value = [];
  }
}

async function handleSave() {
  if (!canManage.value || saving.value) return;
  modalFeedback.value = null;

  if (!formState.judul.trim()) {
    modalFeedback.value = "Judul peta digital wajib diisi.";
    return;
  }
  if (formState.tingkat_wilayah === "kecamatan" && !formState.id_kecamatan) {
    modalFeedback.value = "Kecamatan wajib dipilih untuk tingkat kecamatan.";
    return;
  }
  if (formState.tingkat_wilayah === "desa" && (!formState.id_kecamatan || !formState.id_desa)) {
    modalFeedback.value = "Kecamatan dan Desa wajib dipilih untuk terbitan peta desa.";
    return;
  }
  if (formState.sumber_tipe === "file_pdf") {
    const hasExistingFile = isEditing.value && editingItem.value?.has_file;
    if (!selectedPdfFile.value && !hasExistingFile) {
      modalFeedback.value = "Silakan pilih berkas PDF terlebih dahulu.";
      return;
    }
  } else if (!formState.external_url.trim()) {
    modalFeedback.value = "Silakan masukkan Link URL peta digital.";
    return;
  }

  const fd = new FormData();
  fd.append("judul", formState.judul.trim());
  fd.append("nomor_dokumen", formState.nomor_dokumen.trim());
  fd.append("kategori", formState.kategori);
  fd.append("tingkat_wilayah", formState.tingkat_wilayah);
  if (formState.tingkat_wilayah !== "kabupaten" && formState.id_kecamatan) {
    fd.append("id_kecamatan", String(formState.id_kecamatan));
  }
  if (formState.tingkat_wilayah === "desa" && formState.id_desa) {
    fd.append("id_desa", String(formState.id_desa));
  }
  fd.append("tahun_terbit", String(formState.tahun_terbit));
  fd.append("edisi", formState.edisi.trim());
  fd.append("skala_peta", formState.skala_peta.trim());
  fd.append("ringkasan", formState.ringkasan.trim());
  fd.append("sumber_tipe", formState.sumber_tipe);
  if (formState.sumber_tipe === "external_url") {
    fd.append("external_url", formState.external_url.trim());
  } else if (selectedPdfFile.value) {
    fd.append("file_pdf", selectedPdfFile.value);
  }
  fd.append("is_published", formState.is_published ? "1" : "0");

  saving.value = true;
  try {
    if (isEditing.value && editingItem.value) {
      await api.updateItem(editingItem.value.id, fd);
      pageFeedback.value = { type: "success", message: "Terbitan peta digital berhasil diperbarui." };
    } else {
      await api.createItem(fd);
      pageFeedback.value = { type: "success", message: "Terbitan peta digital baru berhasil diterbitkan." };
    }
    isModalOpen.value = false;
    await loadList();
  } catch (err: any) {
    const errorsObj = err?.data?.errors;
    const firstErr = errorsObj ? Object.values(errorsObj).flat()[0] : null;
    modalFeedback.value = String(firstErr || err?.data?.message || "Gagal menyimpan publikasi peta digital.");
  } finally {
    saving.value = false;
  }
}

async function handleTogglePublish(item: PublikasiPetaDigitalItem) {
  if (!canManage.value) return;
  try {
    const res = await api.togglePublish(item.id);
    pageFeedback.value = { type: "success", message: res?.message || "Status publikasi diperbarui." };
    await loadList();
  } catch (err: any) {
    pageFeedback.value = { type: "error", message: err?.data?.message || "Gagal mengubah status publikasi." };
  }
}

function openDeleteModal(item: PublikasiPetaDigitalItem) {
  itemToDelete.value = item;
  deleteError.value = null;
  isDeleteOpen.value = true;
}

async function handleConfirmDelete() {
  if (!itemToDelete.value || deleting.value) return;
  deleting.value = true;
  deleteError.value = null;
  try {
    const res = await api.deleteItem(itemToDelete.value.id);
    isDeleteOpen.value = false;
    pageFeedback.value = { type: "success", message: res?.message || "Terbitan dihapus." };
    itemToDelete.value = null;
    await loadList();
  } catch (err: any) {
    deleteError.value = err?.data?.message || "Gagal menghapus terbitan.";
  } finally {
    deleting.value = false;
  }
}

function openItemDownload(item: PublikasiPetaDigitalItem) {
  const url = resolvePublikasiDownloadUrl(item.id);
  if (import.meta.client) {
    window.open(url, "_blank", "noopener,noreferrer");
  }
}

function openMapViewerModal(item: PublikasiPetaDigitalItem) {
  mapViewerTargetItem.value = item;
  isMapViewerOpen.value = true;
}

function handleFilterKecamatanChange(val: number | null) {
  filters.id_kecamatan = val;
  filters.id_desa = null;
  filters.page = 1;
  loadWilayahFor("filter", val);
  loadList();
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(
  () => filters.search,
  () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      filters.page = 1;
      loadList();
    }, 300);
  },
);

watch(
  () => [filters.tingkat_wilayah, filters.id_desa, filters.is_published],
  () => {
    filters.page = 1;
    loadList();
  },
);

watch(
  () => filters.page,
  () => loadList(),
);

onMounted(async () => {
  await Promise.all([loadWilayahFor("filter", null), loadList()]);
});
</script>

<template>
  <div class="space-y-5">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <UIcon name="i-lucide-map-pinned" class="size-5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
            Kelola Publikasi Peta Digital
          </h1>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Terbitkan Peta Digital Desa, Kecamatan, maupun Kabupaten (upload PDF aman atau Link URL resmi) untuk diunduh desa sebagai lampiran pengajuan pembangunan infrastruktur.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <UButton
          icon="i-heroicons-arrow-path"
          label="Refresh"
          aria-label="Muat ulang daftar publikasi peta"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          @click="loadList"
        />
        <NuxtLink to="/peta-digital" target="_blank">
          <UButton
            icon="i-lucide-external-link"
            label="Lihat Halaman Publik"
            color="neutral"
            variant="outline"
            size="sm"
            class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          />
        </NuxtLink>
        <UButton
          v-if="canManage"
          icon="i-heroicons-plus"
          label="Tambah Terbitan"
          aria-label="Tambah terbitan peta digital baru"
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
            v-for="tab in tingkatTabs"
            :key="tab.value || 'all'"
            type="button"
            class="px-3 py-1.5 min-h-[38px] rounded-lg text-xs font-medium transition-colors cursor-pointer flex items-center gap-2 shrink-0 border"
            :class="filters.tingkat_wilayah === tab.value
              ? 'bg-blue-600 text-white border-blue-600 font-semibold'
              : 'bg-gray-50 dark:bg-white/[0.03] border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]'"
            @click="filters.tingkat_wilayah = tab.value"
          >
            <UIcon :name="tab.icon" class="size-3.5 shrink-0" aria-hidden="true" />
            <span>{{ tab.label }}</span>
          </button>
        </div>

        <div class="flex items-center gap-3 self-start sm:self-center shrink-0">
          <span class="text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">
            Total {{ meta.total }} terbitan
          </span>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer shrink-0"
            @click="resetFilters"
          >
            Reset Filter
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="sm:col-span-2">
          <UInput
            v-model="filters.search"
            icon="i-lucide-search"
            placeholder="Cari judul, desa, atau nomor dokumen..."
            aria-label="Cari publikasi peta"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="filterTingkatModel"
            :items="tingkatFilterItems"
            value-key="value"
            label-key="label"
            aria-label="Filter tingkat wilayah"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="filterKecamatanModel"
            :items="kecamatanSelectItems"
            value-key="value"
            label-key="label"
            aria-label="Filter kecamatan"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="filterDesaModel"
            :items="filterDesaItems"
            :disabled="!filters.id_kecamatan"
            value-key="value"
            label-key="label"
            aria-label="Filter desa"
            class="w-full"
            size="sm"
          />
        </div>
        <div>
          <USelect
            v-model="filterPublishedModel"
            :items="publishStatusFilterItems"
            value-key="value"
            label-key="label"
            aria-label="Filter status publikasi"
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
      <button type="button" aria-label="Tutup pesan" class="p-0.5 cursor-pointer" @click="pageFeedback = null">
        <UIcon name="i-lucide-x" class="size-3.5" aria-hidden="true" />
      </button>
    </div>

    <!-- Main Catalog Card -->
    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading && items.length === 0" class="p-8 text-center space-y-3">
        <UIcon name="i-lucide-loader-2" class="size-6 animate-spin mx-auto text-blue-600 dark:text-blue-400" />
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data publikasi peta digital...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="items.length === 0"
        class="p-12 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
          <UIcon name="i-lucide-map-pinned" class="size-6" aria-hidden="true" />
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">
          Belum ada publikasi peta digital
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          Belum ada terbitan yang sesuai filter saat ini, atau klik Tambah Terbitan untuk mengunggah PDF / URL resmi.
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
            label="Tambah Terbitan"
            icon="i-heroicons-plus"
            size="sm"
            color="primary"
            class="cursor-pointer"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- Mobile Card View -->
      <div v-else class="divide-y divide-gray-200 dark:divide-white/[0.06] md:hidden">
        <div
          v-for="item in items"
          :key="`mob-${item.id}`"
          class="p-4 space-y-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
              <p class="font-semibold text-sm text-gray-900 dark:text-white break-words">
                {{ item.judul }}
              </p>
              <p class="text-[11px] text-gray-500 dark:text-gray-400">
                {{ item.kategori }} {{ item.nomor_dokumen ? `· No: ${item.nomor_dokumen}` : '' }}
              </p>
              <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                {{ item.wilayah_label }} <span class="text-gray-500 dark:text-gray-400 capitalize">({{ item.tingkat_wilayah }})</span>
              </p>
            </div>
            <button
              type="button"
              :disabled="!canManage"
              class="shrink-0 cursor-pointer disabled:cursor-default"
              @click="handleTogglePublish(item)"
            >
              <UBadge :color="item.is_published ? 'success' : 'neutral'" variant="subtle" size="md" class="font-semibold">
                {{ item.is_published ? 'Publik' : 'Arsip' }}
              </UBadge>
            </button>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs">
            <UBadge
              :color="item.sumber_tipe === 'file_pdf' ? 'error' : 'info'"
              variant="subtle"
              size="md"
              class="font-semibold"
            >
              {{ item.sumber_tipe === 'file_pdf' ? `PDF ${item.file_size_label ? `(${item.file_size_label})` : ''}` : 'Link URL' }}
            </UBadge>
            <UBadge
              v-if="item.is_georeferenced"
              color="success"
              variant="subtle"
              size="md"
              class="font-semibold"
            >
              Ter-georef ({{ item.georef_method || 'affine' }})
            </UBadge>
            <span v-else class="inline-flex px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-400">
              Belum georef
            </span>
            <span class="text-gray-600 dark:text-gray-300 tabular-nums">
              {{ item.tahun_terbit }} · {{ item.download_count }}x unduh
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-1">
            <NuxtLink
              v-if="canManage"
              :to="`/admin/publikasi-peta/${item.id}`"
              class="flex-1 min-w-[140px]"
            >
              <UButton
                label="Georef"
                icon="i-lucide-crosshair"
                size="xs"
                :color="item.is_georeferenced ? 'primary' : 'neutral'"
                variant="outline"
                class="w-full justify-center min-h-[44px] cursor-pointer"
              />
            </NuxtLink>
            <UButton
              v-if="item.is_georeferenced"
              label="Koordinat"
              icon="i-lucide-map"
              size="xs"
              color="success"
              variant="outline"
              class="min-h-[44px] cursor-pointer"
              @click="openMapViewerModal(item)"
            />
            <UButton
              icon="i-lucide-download"
              size="xs"
              color="neutral"
              variant="outline"
              aria-label="Unduh / buka sumber peta"
              class="min-h-[44px] cursor-pointer"
              @click="openItemDownload(item)"
            />
            <UButton
              v-if="canManage"
              icon="i-lucide-pencil"
              size="xs"
              color="neutral"
              variant="outline"
              aria-label="Edit publikasi"
              class="min-h-[44px] cursor-pointer"
              @click="openEditModal(item)"
            />
            <UButton
              v-if="canManage"
              icon="i-lucide-trash-2"
              size="xs"
              color="error"
              variant="outline"
              aria-label="Hapus publikasi"
              class="min-h-[44px] cursor-pointer"
              @click="openDeleteModal(item)"
            />
          </div>
        </div>
      </div>

      <!-- Desktop Table Content -->
      <div v-if="items.length > 0 && !(loading && items.length === 0)" class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-4">Judul &amp; Kategori</th>
              <th scope="col" class="py-3 px-3">Cakupan Wilayah</th>
              <th scope="col" class="py-3 px-3">Sumber</th>
              <th scope="col" class="py-3 px-3">Georef MapLibre</th>
              <th scope="col" class="py-3 px-3">Tahun / Unduhan</th>
              <th scope="col" class="py-3 px-3 text-center">Status</th>
              <th scope="col" class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="item in items"
              :key="item.id"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <td class="py-3 px-4 max-w-md">
                <p class="font-semibold text-sm text-gray-900 dark:text-white line-clamp-1">{{ item.judul }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                  {{ item.kategori }} {{ item.nomor_dokumen ? `· No: ${item.nomor_dokumen}` : '' }}
                </p>
              </td>
              <td class="py-3 px-3">
                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ item.wilayah_label }}</span>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 capitalize">{{ item.tingkat_wilayah }}</p>
              </td>
              <td class="py-3 px-3">
                <UBadge
                  :color="item.sumber_tipe === 'file_pdf' ? 'error' : 'info'"
                  variant="subtle"
                  size="md"
                  class="font-semibold"
                >
                  {{ item.sumber_tipe === 'file_pdf' ? `PDF ${item.file_size_label ? `(${item.file_size_label})` : ''}` : 'Link URL' }}
                </UBadge>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-[200px] mt-1">
                  {{ item.sumber_tipe === 'file_pdf' ? (item.original_filename || 'Dokumen PDF') : (item.external_domain || item.external_url) }}
                </p>
              </td>
              <td class="py-3 px-3">
                <div v-if="item.is_georeferenced" class="space-y-1">
                  <UBadge color="success" variant="subtle" size="md" class="font-semibold">
                    Ter-georef ({{ item.georef_method || 'affine' }})
                  </UBadge>
                  <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <span v-if="item.georef_rms_meters !== null && item.georef_rms_meters !== undefined" class="tabular-nums">
                      RMS {{ item.georef_rms_meters }} m
                    </span>
                    <button
                      type="button"
                      class="text-[#2B5B9E] dark:text-sky-300 font-semibold hover:underline cursor-pointer"
                      @click="openMapViewerModal(item)"
                    >
                      Baca Koordinat
                    </button>
                  </div>
                </div>
                <span v-else class="inline-flex px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-400">
                  Belum georef
                </span>
              </td>
              <td class="py-3 px-3 tabular-nums">
                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ item.tahun_terbit }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ item.download_count }}x diunduh</p>
              </td>
              <td class="py-3 px-3 text-center">
                <button
                  type="button"
                  :disabled="!canManage"
                  class="cursor-pointer disabled:cursor-default"
                  @click="handleTogglePublish(item)"
                >
                  <UBadge :color="item.is_published ? 'success' : 'neutral'" variant="subtle" size="md" class="font-semibold">
                    {{ item.is_published ? 'Publik' : 'Arsip' }}
                  </UBadge>
                </button>
              </td>
              <td class="py-3 px-4 text-right">
                <div class="inline-flex items-center gap-1">
                  <NuxtLink v-if="canManage" :to="`/admin/publikasi-peta/${item.id}`">
                    <UButton
                      icon="i-lucide-crosshair"
                      size="xs"
                      :color="item.is_georeferenced ? 'primary' : 'neutral'"
                      variant="ghost"
                      title="Georeferencing MapLibre GL JS"
                      aria-label="Georeferencing MapLibre GL JS"
                      class="cursor-pointer"
                    />
                  </NuxtLink>
                  <UButton
                    v-if="item.is_georeferenced"
                    icon="i-lucide-map"
                    size="xs"
                    color="success"
                    variant="ghost"
                    title="Baca Koordinat di MapLibre"
                    aria-label="Baca Koordinat di MapLibre"
                    class="cursor-pointer"
                    @click="openMapViewerModal(item)"
                  />
                  <UButton
                    icon="i-lucide-download"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    aria-label="Unduh / buka sumber peta"
                    class="cursor-pointer"
                    @click="openItemDownload(item)"
                  />
                  <UButton
                    v-if="canManage"
                    icon="i-lucide-pencil"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    aria-label="Edit publikasi"
                    class="cursor-pointer"
                    @click="openEditModal(item)"
                  />
                  <UButton
                    v-if="canManage"
                    icon="i-lucide-trash-2"
                    size="xs"
                    color="error"
                    variant="ghost"
                    aria-label="Hapus publikasi"
                    class="cursor-pointer"
                    @click="openDeleteModal(item)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="meta.last_page > 1"
        class="px-4 py-3 border-t border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-[#070b14]/60 flex items-center justify-between text-xs text-gray-600 dark:text-gray-400"
      >
        <span>Total {{ meta.total }} publikasi · Halaman {{ meta.current_page }} / {{ meta.last_page }}</span>
        <div class="flex items-center gap-2">
          <UButton label="Sebelumnya" size="xs" color="neutral" variant="outline" :disabled="filters.page <= 1" class="cursor-pointer" @click="filters.page--" />
          <UButton label="Berikutnya" size="xs" color="neutral" variant="outline" :disabled="filters.page >= meta.last_page" class="cursor-pointer" @click="filters.page++" />
        </div>
      </div>
    </div>

    <!-- Modal Tambah / Edit -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? 'Edit Publikasi Peta Digital' : 'Tambah Publikasi Peta Digital'"
      description="Gunakan berkas PDF tervalidasi (maks 25 MB) atau tautan URL resmi agar dapat diunduh desa/publik."
      :ui="{ content: 'sm:max-w-2xl' }"
    >
      <template #body>
        <form class="space-y-4" @submit.prevent="handleSave">
          <div
            v-if="modalFeedback"
            role="alert"
            class="px-3 py-2 rounded-lg border border-red-200 dark:border-red-800 bg-red-50/90 dark:bg-red-950/40 text-xs text-red-900 dark:text-red-100"
          >
            {{ modalFeedback }}
          </div>

          <!-- Sumber Terbitan Toggle -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
              Sumber Terbitan Peta Digital
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="min-h-[42px] px-3 py-2 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 cursor-pointer transition-colors"
                :class="formState.sumber_tipe === 'file_pdf'
                  ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300'
                  : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300'"
                @click="formState.sumber_tipe = 'file_pdf'"
              >
                <UIcon name="i-lucide-file-up" class="size-4" aria-hidden="true" />
                <span>Upload File PDF (Maks 25 MB)</span>
              </button>
              <button
                type="button"
                class="min-h-[42px] px-3 py-2 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 cursor-pointer transition-colors"
                :class="formState.sumber_tipe === 'external_url'
                  ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300'
                  : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300'"
                @click="formState.sumber_tipe = 'external_url'"
              >
                <UIcon name="i-lucide-link" class="size-4" aria-hidden="true" />
                <span>Link URL Eksternal</span>
              </button>
            </div>
          </div>

          <!-- Upload PDF atau External URL Input -->
          <div v-if="formState.sumber_tipe === 'file_pdf'" class="space-y-1.5">
            <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
              Berkas Peta PDF <span v-if="!isEditing || !editingItem?.has_file" class="text-red-500">*</span>
            </label>
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-3.5 bg-gray-50/70 dark:bg-white/[0.02] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="min-w-0 text-xs">
                <p v-if="selectedPdfFile" class="font-semibold text-emerald-700 dark:text-emerald-300 truncate">
                  File baru dipilih: {{ selectedPdfFile.name }} ({{ (selectedPdfFile.size / 1048576).toFixed(2) }} MB)
                </p>
                <p v-else-if="isEditing && editingItem?.has_file" class="font-medium text-gray-700 dark:text-gray-300 truncate">
                  File saat ini: {{ editingItem.original_filename || 'Dokumen PDF' }} ({{ editingItem.file_size_label || '' }})
                </p>
                <p v-else class="text-gray-500 dark:text-gray-400">
                  Pilih berkas PDF valid (.pdf, maksimal 25 MB). Disimpan terenkripsi path di penyimpanan privat.
                </p>
              </div>
              <input
                ref="pdfInputRef"
                type="file"
                accept="application/pdf,.pdf"
                class="hidden"
                @change="handlePdfFileChange"
              />
              <UButton
                type="button"
                label="Pilih File PDF"
                icon="i-lucide-upload"
                size="xs"
                color="neutral"
                variant="outline"
                class="cursor-pointer shrink-0"
                @click="pdfInputRef?.click()"
              />
            </div>
          </div>

          <div v-else class="space-y-1.5">
            <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
              Link URL Peta Digital <span class="text-red-500">*</span>
            </label>
            <UInput
              v-model="formState.external_url"
              type="url"
              placeholder="https://geoportal.bojonegorokab.go.id/files/peta-desa.pdf"
              size="sm"
              class="w-full"
            />
            <p class="text-[11px] text-gray-500 dark:text-gray-400">
              Wajib menggunakan URL publik yang diawali https:// atau http://.
            </p>
          </div>

          <!-- Metadata Judul & Kategori -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2 space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                Judul Publikasi Peta <span class="text-red-500">*</span>
              </label>
              <UInput
                v-model="formState.judul"
                placeholder="Misal: Peta Jaringan Jalan & Infrastruktur Desa Ngumpakdalem"
                size="sm"
                class="w-full"
              />
            </div>

            <div class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                Kategori <span class="text-red-500">*</span>
              </label>
              <USelect
                v-model="formState.kategori"
                :items="kategoriItems"
                value-key="value"
                label-key="label"
                size="sm"
                class="w-full"
              />
            </div>

            <div class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">
                Nomor Dokumen / Surat (Opsional)
              </label>
              <UInput
                v-model="formState.nomor_dokumen"
                placeholder="050/123/412.201/2026"
                size="sm"
                class="w-full"
              />
            </div>
          </div>

          <!-- Cakupan Wilayah -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-gray-50/80 dark:bg-white/[0.02] border border-gray-200/80 dark:border-gray-800">
            <div class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Tingkat Wilayah</label>
              <USelect
                v-model="formState.tingkat_wilayah"
                :items="PUBLIKASI_TINGKAT_OPTIONS"
                value-key="value"
                label-key="label"
                size="sm"
                class="w-full"
              />
            </div>

            <div v-if="formState.tingkat_wilayah !== 'kabupaten'" class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Kecamatan *</label>
              <USelect
                v-model="formKecamatanModel"
                :items="formKecamatanItems"
                value-key="value"
                label-key="label"
                placeholder="Pilih Kecamatan"
                size="sm"
                class="w-full"
              />
            </div>

            <div v-if="formState.tingkat_wilayah === 'desa'" class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Desa / Kelurahan *</label>
              <USelect
                v-model="formDesaModel"
                :items="formDesaItems"
                :disabled="!formState.id_kecamatan"
                value-key="value"
                label-key="label"
                placeholder="Pilih Desa"
                size="sm"
                class="w-full"
              />
            </div>
          </div>

          <!-- Tahun, Skala, Edisi -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Tahun Terbit *</label>
              <UInput v-model.number="formState.tahun_terbit" type="number" min="2000" max="2100" size="sm" class="w-full" />
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Skala Peta</label>
              <UInput v-model="formState.skala_peta" placeholder="1 : 5.000" size="sm" class="w-full" />
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Edisi / Versi</label>
              <UInput v-model="formState.edisi" placeholder="Edisi I / 2026" size="sm" class="w-full" />
            </div>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-semibold text-gray-800 dark:text-gray-200">Ringkasan / Keterangan</label>
            <UTextarea
              v-model="formState.ringkasan"
              :rows="2"
              placeholder="Catatan cakupan peta, kegunaan lampiran, atau pembaruan terbaru..."
              size="sm"
              class="w-full"
            />
          </div>

          <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-800 dark:text-gray-200 cursor-pointer">
            <input v-model="formState.is_published" type="checkbox" class="rounded border-gray-300" />
            <span>Publikasikan langsung agar tampil di halaman publik (/peta-digital)</span>
          </label>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-800">
            <UButton label="Batal" color="neutral" variant="ghost" size="sm" @click="isModalOpen = false" />
            <UButton
              type="submit"
              :label="saving ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Terbitkan Peta Digital')"
              color="primary"
              size="sm"
              :loading="saving"
              class="cursor-pointer"
            />
          </div>
        </form>
      </template>
    </UModal>

    <!-- Modal Konfirmasi Hapus -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Hapus Publikasi Peta Digital"
      :description="`Konfirmasi penghapusan ${itemToDelete?.judul || ''}`"
    >
      <template #body>
        <div class="space-y-4">
          <div v-if="deleteError" class="px-3 py-2 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-xs text-red-900 dark:text-red-100">
            {{ deleteError }}
          </div>
          <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
            Apakah Anda yakin ingin menghapus terbitan <strong>{{ itemToDelete?.judul }}</strong>? Berkas PDF privat (jika ada) juga akan dihapus permanen.
          </p>
          <div class="flex justify-end gap-2">
            <UButton label="Batal" color="neutral" variant="ghost" size="sm" @click="isDeleteOpen = false" />
            <UButton label="Hapus" color="error" size="sm" :loading="deleting" class="cursor-pointer" @click="handleConfirmDelete" />
          </div>
        </div>
      </template>
    </UModal>

    <!-- Modal Pembaca Koordinat MapLibre GL JS -->
    <PublikasiMapViewerModal
      v-model:open="isMapViewerOpen"
      :item="mapViewerTargetItem"
    />
  </div>
</template>

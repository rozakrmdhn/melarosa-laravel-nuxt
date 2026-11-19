<script setup lang="ts">
import { resolvePublikasiDownloadUrl, usePublikasiPetaApi } from "~/composables/usePublikasiPetaApi";
import PublikasiMapViewerModal from "~/components/publikasi-peta/PublikasiMapViewerModal.vue";
import {
  PUBLIKASI_KATEGORI_OPTIONS,
  type PublikasiPetaDigitalItem,
  type PublikasiWilayahDesa,
  type PublikasiWilayahKecamatan,
} from "~/types/publikasi-peta";

usePublicSeo({
  title: "Peta Digital Desa · MELAROSA Bojonegoro",
  description: "Kumpulan peta digital desa dan wilayah yang siap diakses dan diunduh.",
  path: "/peta-digital",
});

const ALL_VALUE = "__ALL__";
const route = useRoute();
const api = usePublikasiPetaApi();

const loading = ref(false);
const loadError = ref<string | null>(null);
const items = ref<PublikasiPetaDigitalItem[]>([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  available_years: [] as number[],
  categories: [...PUBLIKASI_KATEGORI_OPTIONS] as string[],
});

const kecamatanList = ref<PublikasiWilayahKecamatan[]>([]);
const desaList = ref<PublikasiWilayahDesa[]>([]);
const loadingDesa = ref(false);

const filters = reactive({
  search: typeof route.query.search === "string" ? route.query.search : "",
  tingkat_wilayah: typeof route.query.tingkat_wilayah === "string" ? route.query.tingkat_wilayah : "",
  id_kecamatan: route.query.id_kecamatan ? Number(route.query.id_kecamatan) : (null as number | null),
  id_desa: route.query.id_desa ? Number(route.query.id_desa) : (null as number | null),
  tahun_terbit: route.query.tahun_terbit ? Number(route.query.tahun_terbit) : (null as number | null),
  kategori: typeof route.query.kategori === "string" ? route.query.kategori : "",
  page: 1,
});

const previewModalOpen = ref(false);
const previewItem = ref<PublikasiPetaDigitalItem | null>(null);
const mapViewerOpen = ref(false);
const mapViewerItem = ref<PublikasiPetaDigitalItem | null>(null);
const copiedItemId = ref<string | null>(null);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

const tingkatFilterOptions = [
  { label: "Semua Wilayah", value: ALL_VALUE },
  { label: "Desa / Kelurahan", value: "desa" },
  { label: "Kecamatan", value: "kecamatan" },
  { label: "Kabupaten", value: "kabupaten" },
];

const kecamatanOptions = computed(() => [
  { label: "Semua Kecamatan", value: ALL_VALUE },
  ...kecamatanList.value.map((k) => ({
    label: `Kec. ${k.nama_kecamatan}`,
    value: String(k.id),
  })),
]);

const desaOptions = computed(() => [
  { label: filters.id_kecamatan ? "Semua Desa" : "Pilih Kecamatan Dahulu", value: ALL_VALUE },
  ...desaList.value.map((d) => ({
    label: d.nama_desa,
    value: String(d.id),
  })),
]);

const yearOptions = computed(() => [
  { label: "Semua Tahun", value: ALL_VALUE },
  ...(meta.value.available_years || []).map((y) => ({
    label: String(y),
    value: String(y),
  })),
]);

const selectedTingkatModel = computed<string>({
  get: () => (filters.tingkat_wilayah ? filters.tingkat_wilayah : ALL_VALUE),
  set: (val) => {
    filters.tingkat_wilayah = val === ALL_VALUE ? "" : val;
  },
});

const selectedKecamatanModel = computed<string>({
  get: () => (filters.id_kecamatan ? String(filters.id_kecamatan) : ALL_VALUE),
  set: (val) => {
    const nextKec = val === ALL_VALUE ? null : Number(val);
    handleKecamatanChange(Number.isFinite(nextKec) ? nextKec : null);
  },
});

const selectedDesaModel = computed<string>({
  get: () => (filters.id_desa ? String(filters.id_desa) : ALL_VALUE),
  set: (val) => {
    const nextDesa = val === ALL_VALUE ? null : Number(val);
    filters.id_desa = Number.isFinite(nextDesa) ? nextDesa : null;
  },
});

const selectedYearModel = computed<string>({
  get: () => (filters.tahun_terbit ? String(filters.tahun_terbit) : ALL_VALUE),
  set: (val) => {
    const nextYear = val === ALL_VALUE ? null : Number(val);
    filters.tahun_terbit = Number.isFinite(nextYear) ? nextYear : null;
  },
});

const hasActiveFilter = computed(() =>
  Boolean(
    filters.search.trim() ||
    filters.tingkat_wilayah ||
    filters.id_kecamatan ||
    filters.id_desa ||
    filters.tahun_terbit ||
    filters.kategori
  )
);

function formatPublishedDate(iso?: string | null): string {
  if (!iso) return "";
  try {
    return new Intl.DateTimeFormat("id-ID", {
      day: "numeric",
      month: "long",
      year: "numeric",
    }).format(new Date(iso));
  } catch {
    return "";
  }
}

async function loadWilayahOptions(idKecamatan?: number | null) {
  if (idKecamatan) {
    loadingDesa.value = true;
  }
  try {
    const res = await api.fetchPublicWilayahOptions(idKecamatan);
    if (res?.kecamatan) {
      kecamatanList.value = res.kecamatan;
    }
    desaList.value = idKecamatan ? (res?.desa || []) : [];
  } catch {
    // Abaikan jika dropdown wilayah gagal dimuat
  } finally {
    loadingDesa.value = false;
  }
}

async function loadPublications() {
  loading.value = true;
  loadError.value = null;
  try {
    const res = await api.fetchPublicList({
      page: filters.page,
      per_page: 10,
      search: filters.search.trim() || undefined,
      tingkat_wilayah: filters.tingkat_wilayah || undefined,
      id_kecamatan: filters.id_kecamatan || undefined,
      id_desa: filters.id_desa || undefined,
      tahun_terbit: filters.tahun_terbit || undefined,
      kategori: filters.kategori || undefined,
    });
    items.value = res?.data || [];
    if (res?.meta) {
      meta.value = {
        ...meta.value,
        ...res.meta,
        available_years: res.meta.available_years || meta.value.available_years,
        categories: res.meta.categories?.length ? res.meta.categories : meta.value.categories,
      };
    }
  } catch (err: any) {
    loadError.value = err?.data?.message || "Gagal memuat terbitan Peta Digital.";
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.search = "";
  filters.tingkat_wilayah = "";
  filters.id_kecamatan = null;
  filters.id_desa = null;
  filters.tahun_terbit = null;
  filters.kategori = "";
  filters.page = 1;
  desaList.value = [];
  loadPublications();
}

function handleKecamatanChange(val: number | null) {
  filters.id_kecamatan = val;
  filters.id_desa = null;
  filters.page = 1;
  if (val) {
    loadWilayahOptions(val);
  } else {
    desaList.value = [];
  }
  loadPublications();
}

function openPreview(item: PublikasiPetaDigitalItem) {
  previewItem.value = item;
  previewModalOpen.value = true;
}

function openMapViewer(item: PublikasiPetaDigitalItem) {
  mapViewerItem.value = item;
  mapViewerOpen.value = true;
}

function triggerDownload(item: PublikasiPetaDigitalItem) {
  const url = resolvePublikasiDownloadUrl(item.id);
  if (import.meta.client) {
    window.open(url, "_blank", "noopener,noreferrer");
    item.download_count = (item.download_count || 0) + 1;
  }
}

function copyShareLink(item: PublikasiPetaDigitalItem) {
  if (!import.meta.client) return;
  const target = `${window.location.origin}/peta-digital/${encodeURIComponent(item.slug || item.id)}`;

  navigator?.clipboard?.writeText(target).catch(() => {});
  copiedItemId.value = item.id;
  if (copyTimer) clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    copiedItemId.value = null;
  }, 2000);
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(
  () => filters.search,
  () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      filters.page = 1;
      loadPublications();
    }, 320);
  }
);

watch(
  () => [filters.tingkat_wilayah, filters.id_desa, filters.tahun_terbit, filters.kategori],
  () => {
    filters.page = 1;
    loadPublications();
  }
);

watch(
  () => filters.page,
  () => {
    loadPublications();
  }
);

onMounted(async () => {
  await Promise.all([
    loadWilayahOptions(filters.id_kecamatan),
    loadPublications(),
  ]);
});

onBeforeUnmount(() => {
  if (copyTimer) clearTimeout(copyTimer);
  if (searchTimer) clearTimeout(searchTimer);
});

const previewIframeUrl = computed(() => {
  if (!previewItem.value || previewItem.value.sumber_tipe !== "file_pdf") return "";
  return resolvePublikasiDownloadUrl(previewItem.value.id, { inline: true });
});
</script>

<template>
  <div class="min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300">
    <main class="flex-1 pt-6 sm:pt-10 pb-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <!-- Blog Editorial Header -->
        <header class="pb-7 border-b border-slate-200/85 dark:border-white/[0.08] space-y-4">
          <nav aria-label="Jejak navigasi" class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <NuxtLink to="/" class="hover:text-[#2B5B9E] dark:hover:text-sky-400 font-medium">
              Beranda
            </NuxtLink>
            <UIcon name="i-lucide-chevron-right" class="size-3.5" aria-hidden="true" />
            <span class="font-medium text-slate-500 dark:text-slate-400">Publikasi</span>
            <UIcon name="i-lucide-chevron-right" class="size-3.5" aria-hidden="true" />
            <span class="font-semibold text-slate-900 dark:text-white">Peta Digital</span>
          </nav>

          <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div class="max-w-2xl space-y-2">
              <div class="inline-flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-[#2B5B9E] dark:text-sky-400">
                  Publikasi Resmi · Kabupaten Bojonegoro
                </span>
              </div>
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white tracking-tight leading-[1.15]">
                Peta Digital Desa
              </h1>
              <p class="text-sm sm:text-[15px] text-slate-600 dark:text-slate-400 leading-relaxed">
                Kumpulan peta digital desa dan wilayah yang siap diakses dan diunduh.
              </p>
            </div>

            <div class="flex items-center gap-2 shrink-0 text-xs font-semibold px-3.5 py-2 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/90 dark:border-white/10">
              <UIcon name="i-lucide-book-marked" class="size-4 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
              <span class="tabular-nums">{{ meta.total }} Terbitan Tersedia</span>
            </div>
          </div>
        </header>

        <!-- Classic 2-Column Blog Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          <!-- Main Publication Feed (8 Columns) -->
          <div class="lg:col-span-8 space-y-5">
            <!-- Active Filter Notice -->
            <div
              v-if="hasActiveFilter"
              class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/10 text-xs"
            >
              <span class="text-slate-600 dark:text-slate-300">
                Menampilkan <strong class="text-slate-900 dark:text-white">{{ meta.total }}</strong> terbitan sesuai filter
                <template v-if="filters.kategori"> · Kategori <strong>{{ filters.kategori }}</strong></template>
              </span>
              <button
                type="button"
                class="font-semibold text-rose-700 dark:text-rose-400 hover:underline cursor-pointer"
                @click="resetFilters"
              >
                Reset Filter
              </button>
            </div>

            <!-- Error State -->
            <div
              v-if="loadError"
              role="alert"
              class="rounded-2xl border border-red-200 dark:border-red-800/70 bg-red-50/90 dark:bg-red-950/35 p-4 flex items-center justify-between gap-3 text-sm text-red-900 dark:text-red-100"
            >
              <span>{{ loadError }}</span>
              <UButton label="Coba Lagi" size="xs" color="error" variant="subtle" @click="loadPublications" />
            </div>

            <!-- Loading Skeleton -->
            <div v-else-if="loading && items.length === 0" class="space-y-4">
              <USkeleton v-for="i in 4" :key="i" class="h-44 rounded-2xl" />
            </div>

            <!-- Empty State -->
            <div
              v-else-if="items.length === 0"
              class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] py-14 px-6 text-center space-y-3"
            >
              <div class="size-12 mx-auto rounded-2xl bg-slate-100 dark:bg-white/[0.06] flex items-center justify-center text-slate-500">
                <UIcon name="i-lucide-file-search" class="size-6" aria-hidden="true" />
              </div>
              <h2 class="text-base font-bold text-slate-900 dark:text-white">
                Belum ada terbitan peta digital ditemukan
              </h2>
              <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md mx-auto">
                Coba ubah kata kunci pencarian atau pilih wilayah kecamatan dan desa lain pada panel filter di samping.
              </p>
              <UButton
                v-if="hasActiveFilter"
                label="Tampilkan Semua Terbitan"
                color="primary"
                variant="soft"
                size="sm"
                class="cursor-pointer"
                @click="resetFilters"
              />
            </div>

            <!-- Article / Publication Feed -->
            <div
              v-else
              class="bg-white dark:bg-[#0c1522] rounded-2xl border border-slate-200/80 dark:border-white/[0.08] divide-y divide-slate-200/80 dark:divide-white/[0.08] overflow-hidden shadow-2xs"
            >
              <article
                v-for="item in items"
                :key="item.id"
                class="p-5 sm:p-6 space-y-3.5 hover:bg-slate-50/70 dark:hover:bg-white/[0.02] transition-colors"
              >
                <!-- Blog Eyebrow Row -->
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/50 text-[#1e4378] dark:text-sky-200 border border-blue-200 dark:border-blue-800/60">
                      {{ item.kategori }}
                    </span>
                    <span class="inline-flex items-center gap-1 font-medium text-slate-700 dark:text-slate-300">
                      <UIcon name="i-lucide-map-pin" class="size-3.5 text-[#2B5B9E] dark:text-sky-400" aria-hidden="true" />
                      <span>{{ item.wilayah_label }}</span>
                    </span>
                  </div>

                  <div class="flex flex-wrap items-center gap-2">
                    <span
                      v-if="item.is_georeferenced && item.has_georef_image"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100/80 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-200 border border-emerald-300/80 dark:border-emerald-700/60"
                    >
                      <UIcon name="i-lucide-crosshair" class="size-3.5" aria-hidden="true" />
                      <span>Ter-georeference</span>
                    </span>
                    <span
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border"
                      :class="item.sumber_tipe === 'file_pdf'
                        ? 'bg-rose-100/80 dark:bg-rose-950/50 text-rose-800 dark:text-rose-200 border-rose-300/80 dark:border-rose-700/60'
                        : 'bg-sky-100/80 dark:bg-sky-950/50 text-sky-800 dark:text-sky-200 border-sky-300/80 dark:border-sky-700/60'"
                    >
                      <UIcon :name="item.sumber_tipe === 'file_pdf' ? 'i-lucide-file-text' : 'i-lucide-link'" class="size-3.5" aria-hidden="true" />
                      <span>{{ item.sumber_tipe === 'file_pdf' ? `PDF${item.file_size_label ? ` · ${item.file_size_label}` : ''}` : 'Tautan Eksternal' }}</span>
                    </span>
                  </div>
                </div>

                <!-- Headline & Excerpt -->
                <div class="space-y-1.5">
                  <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                    <NuxtLink
                      :to="`/peta-digital/${item.slug || item.id}`"
                      class="hover:text-[#2B5B9E] dark:hover:text-sky-300 transition-colors"
                    >
                      {{ item.judul }}
                    </NuxtLink>
                  </h2>
                  <p v-if="item.ringkasan" class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed line-clamp-2">
                    {{ item.ringkasan }}
                  </p>
                </div>

                <!-- Editorial Metadata Line -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                  <span class="inline-flex items-center gap-1">
                    <UIcon name="i-lucide-calendar" class="size-3.5" aria-hidden="true" />
                    <span>{{ formatPublishedDate(item.published_at) || `Tahun ${item.tahun_terbit}` }}</span>
                  </span>
                  <span v-if="item.nomor_dokumen" class="font-mono text-[11px]">
                    No. {{ item.nomor_dokumen }}
                  </span>
                  <span v-if="item.skala_peta">
                    Skala {{ item.skala_peta }}
                  </span>
                  <span v-if="item.edisi">
                    {{ item.edisi }}
                  </span>
                  <span v-if="item.external_domain" class="inline-flex items-center gap-1 truncate max-w-[200px]">
                    <UIcon name="i-lucide-globe" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ item.external_domain }}</span>
                  </span>
                  <span class="inline-flex items-center gap-1 tabular-nums">
                    <UIcon name="i-lucide-download" class="size-3.5" aria-hidden="true" />
                    <span>{{ item.download_count }} unduhan</span>
                  </span>
                </div>

                <!-- Action Row -->
                <div class="flex flex-wrap items-center gap-2 pt-1">
                  <NuxtLink
                    :to="`/peta-digital/${item.slug || item.id}`"
                    class="min-h-[40px] px-4 py-2 rounded-xl bg-[#2B5B9E] hover:bg-[#1e4378] text-white text-xs font-semibold inline-flex items-center gap-1.5 shadow-2xs transition-colors"
                  >
                    <UIcon name="i-lucide-maximize-2" class="size-4" aria-hidden="true" />
                    <span>Buka Peta Penuh</span>
                  </NuxtLink>

                  <button
                    type="button"
                    class="min-h-[40px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/10 text-slate-800 dark:text-slate-100 text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                    @click="triggerDownload(item)"
                  >
                    <UIcon :name="item.sumber_tipe === 'file_pdf' ? 'i-lucide-download' : 'i-lucide-external-link'" class="size-4" aria-hidden="true" />
                    <span>{{ item.sumber_tipe === 'file_pdf' ? 'Unduh PDF' : 'Buka Tautan' }}</span>
                  </button>

                  <button
                    v-if="item.is_georeferenced && item.has_georef_image"
                    type="button"
                    class="min-h-[40px] px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold inline-flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer"
                    @click="openMapViewer(item)"
                  >
                    <UIcon name="i-lucide-map" class="size-4" aria-hidden="true" />
                    <span>Baca Koordinat</span>
                  </button>

                  <button
                    v-if="item.sumber_tipe === 'file_pdf' && item.has_file"
                    type="button"
                    class="min-h-[40px] px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/[0.06] dark:hover:bg-white/10 text-slate-700 dark:text-slate-200 text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                    @click="openPreview(item)"
                  >
                    <UIcon name="i-lucide-eye" class="size-4" aria-hidden="true" />
                    <span>Pratinjau PDF</span>
                  </button>

                  <button
                    type="button"
                    class="min-h-[40px] px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/[0.06] text-slate-600 dark:text-slate-300 text-xs font-medium inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                    @click="copyShareLink(item)"
                  >
                    <UIcon
                      :name="copiedItemId === item.id ? 'i-lucide-check' : 'i-lucide-link-2'"
                      :class="copiedItemId === item.id ? 'text-emerald-600 dark:text-emerald-400' : ''"
                      class="size-3.5"
                      aria-hidden="true"
                    />
                    <span>{{ copiedItemId === item.id ? 'Tersalin' : 'Salin Tautan' }}</span>
                  </button>
                </div>
              </article>
            </div>

            <!-- Blog Pagination -->
            <div
              v-if="meta.last_page > 1"
              class="flex items-center justify-between gap-3 pt-2"
            >
              <p class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
                Halaman {{ meta.current_page }} dari {{ meta.last_page }}
              </p>
              <div class="flex items-center gap-2">
                <UButton
                  label="Sebelumnya"
                  icon="i-lucide-chevron-left"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  :disabled="filters.page <= 1"
                  @click="filters.page = Math.max(1, filters.page - 1)"
                />
                <UButton
                  label="Berikutnya"
                  trailing-icon="i-lucide-chevron-right"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  :disabled="filters.page >= meta.last_page"
                  @click="filters.page = Math.min(meta.last_page, filters.page + 1)"
                />
              </div>
            </div>
          </div>

          <!-- Editorial Sidebar (4 Columns) -->
          <aside aria-label="Filter dan kategori publikasi" class="lg:col-span-4 space-y-5 lg:sticky lg:top-24">
            <!-- Widget 1: Pencarian & Filter Wilayah -->
            <section class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 space-y-4 shadow-2xs">
              <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
                  Cari &amp; Filter Wilayah
                </h2>
                <button
                  v-if="hasActiveFilter"
                  type="button"
                  class="text-xs font-semibold text-[#2B5B9E] dark:text-sky-400 hover:underline cursor-pointer"
                  @click="resetFilters"
                >
                  Reset
                </button>
              </div>

              <div class="space-y-3">
                <div>
                  <label for="blog-search" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Kata Kunci
                  </label>
                  <UInput
                    id="blog-search"
                    v-model="filters.search"
                    icon="i-lucide-search"
                    placeholder="Judul peta, nama desa, atau nomor..."
                    size="md"
                    class="w-full"
                  />
                </div>

                <div>
                  <label for="blog-tingkat" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tingkat Wilayah
                  </label>
                  <USelect
                    id="blog-tingkat"
                    v-model="selectedTingkatModel"
                    :items="tingkatFilterOptions"
                    value-key="value"
                    label-key="label"
                    size="md"
                    class="w-full"
                  />
                </div>

                <div>
                  <label for="blog-kecamatan" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Kecamatan
                  </label>
                  <USelect
                    id="blog-kecamatan"
                    v-model="selectedKecamatanModel"
                    :items="kecamatanOptions"
                    value-key="value"
                    label-key="label"
                    size="md"
                    class="w-full"
                  />
                </div>

                <div>
                  <label for="blog-desa" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Desa / Kelurahan
                  </label>
                  <USelect
                    id="blog-desa"
                    v-model="selectedDesaModel"
                    :items="desaOptions"
                    :disabled="!filters.id_kecamatan || loadingDesa"
                    :loading="loadingDesa"
                    value-key="value"
                    label-key="label"
                    size="md"
                    class="w-full"
                  />
                </div>

                <div>
                  <label for="blog-tahun" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tahun Terbit
                  </label>
                  <USelect
                    id="blog-tahun"
                    v-model="selectedYearModel"
                    :items="yearOptions"
                    value-key="value"
                    label-key="label"
                    size="md"
                    class="w-full"
                  />
                </div>
              </div>
            </section>

            <!-- Widget 2: Daftar Kategori -->
            <section class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 space-y-3 shadow-2xs">
              <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
                Kategori Publikasi
              </h2>
              <div class="space-y-1">
                <button
                  type="button"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold transition-colors cursor-pointer flex items-center justify-between"
                  :class="filters.kategori === ''
                    ? 'bg-blue-50 dark:bg-blue-950/40 text-[#2B5B9E] dark:text-sky-300'
                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/[0.05]'"
                  @click="filters.kategori = ''"
                >
                  <span>Semua Kategori</span>
                  <UIcon v-if="filters.kategori === ''" name="i-lucide-check" class="size-3.5" aria-hidden="true" />
                </button>
                <button
                  v-for="kat in meta.categories"
                  :key="kat"
                  type="button"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-medium transition-colors cursor-pointer flex items-center justify-between"
                  :class="filters.kategori === kat
                    ? 'bg-blue-50 dark:bg-blue-950/40 text-[#2B5B9E] dark:text-sky-300 font-semibold'
                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/[0.05]'"
                  @click="filters.kategori = filters.kategori === kat ? '' : kat"
                >
                  <span class="truncate">{{ kat }}</span>
                  <UIcon v-if="filters.kategori === kat" name="i-lucide-check" class="size-3.5 shrink-0" aria-hidden="true" />
                </button>
              </div>
            </section>

            <!-- Widget 3: Info Lampiran -->
            <section class="rounded-2xl bg-blue-50/70 dark:bg-blue-950/25 border border-blue-200/80 dark:border-blue-800/50 p-5 space-y-2">
              <div class="flex items-center gap-2 text-[#2B5B9E] dark:text-sky-300">
                <UIcon name="i-lucide-info" class="size-4 shrink-0" aria-hidden="true" />
                <h2 class="text-xs font-bold uppercase tracking-wide">
                  Catatan Penggunaan Desa
                </h2>
              </div>
              <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                Perangkat desa dapat mengunduh Peta Digital Desa dalam format PDF untuk digunakan langsung sebagai lampiran peta lokasi pada berkas pengajuan pembangunan infrastruktur.
              </p>
              <NuxtLink
                to="/panduan"
                class="inline-flex items-center gap-1 text-xs font-semibold text-[#2B5B9E] dark:text-sky-300 hover:underline pt-1"
              >
                <span>Lihat Panduan Portal</span>
                <UIcon name="i-lucide-arrow-right" class="size-3.5" aria-hidden="true" />
              </NuxtLink>
            </section>
          </aside>
        </div>
      </div>
    </main>

    <!-- PDF Preview Modal -->
    <UModal
      v-model:open="previewModalOpen"
      :title="previewItem?.judul || 'Pratinjau Peta Digital'"
      :description="previewItem?.wilayah_label || 'Dokumen PDF Peta Digital'"
      :ui="{ content: 'sm:max-w-5xl' }"
    >
      <template #body>
        <div v-if="previewItem" class="space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-white/[0.04] px-3 py-2 rounded-xl border border-slate-200/80 dark:border-white/10">
            <span>{{ previewItem.kategori }} · {{ previewItem.tahun_terbit }} {{ previewItem.file_size_label ? `(${previewItem.file_size_label})` : '' }}</span>
            <UButton
              icon="i-lucide-download"
              label="Unduh PDF"
              color="primary"
              size="xs"
              class="cursor-pointer"
              @click="triggerDownload(previewItem)"
            />
          </div>
          <div class="w-full h-[70vh] rounded-xl overflow-hidden border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-slate-900">
            <iframe
              v-if="previewIframeUrl"
              :src="previewIframeUrl"
              title="Pratinjau PDF Peta Digital"
              class="w-full h-full border-0"
            />
          </div>
        </div>
      </template>
    </UModal>

    <!-- MapLibre GL JS Coordinate Reader Modal -->
    <PublikasiMapViewerModal
      v-model:open="mapViewerOpen"
      :item="mapViewerItem"
    />
  </div>
</template>

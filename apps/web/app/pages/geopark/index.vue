<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  useGeoparkSites,
  type GeoparkCategoryFilter,
  type GeoparkSite,
} from '~/composables/useGeoparkSites';

useSeoMeta({
  title: 'Katalog 31 Situs Geopark Nasional Bojonegoro',
  description:
    'Jurnal dan direktori 31 situs resmi Geopark Nasional Bojonegoro mencakup Keragaman Geologi, Keragaman Hayati, dan Keragaman Budaya.',
});

const {
  sites,
  loading,
  error,
  geologicalSites,
  biologicalSites,
  culturalSites,
  fetchLiveSites,
} = useGeoparkSites();

const searchQuery = ref('');
const selectedPillar = ref<GeoparkCategoryFilter>('ALL');
const failedImageIds = ref<Set<string>>(new Set());

const pillarTabs = computed(() => [
  { key: 'ALL' as GeoparkCategoryFilter, label: 'Semua Situs', count: sites.value.length },
  {
    key: 'geo-diversity' as GeoparkCategoryFilter,
    label: 'Warisan Geologi',
    count: geologicalSites.value.length,
  },
  {
    key: 'bio-diversity' as GeoparkCategoryFilter,
    label: 'Kekayaan Hayati',
    count: biologicalSites.value.length,
  },
  {
    key: 'culture-diversity' as GeoparkCategoryFilter,
    label: 'Warisan Budaya',
    count: culturalSites.value.length,
  },
]);

const filteredSites = computed<GeoparkSite[]>(() => {
  let list = sites.value;

  if (selectedPillar.value === 'geo-diversity') {
    list = geologicalSites.value;
  } else if (selectedPillar.value === 'bio-diversity') {
    list = biologicalSites.value;
  } else if (selectedPillar.value === 'culture-diversity') {
    list = culturalSites.value;
  }

  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter((s) => {
    const name = (s.name || s.title || '').toLowerCase();
    const code = (s.code || '').toLowerCase();
    const desc = (s.desc || '').toLowerCase();
    const tag = (s.tag || '').toLowerCase();
    const category = (s.category || '').toLowerCase();
    return (
      name.includes(q) ||
      code.includes(q) ||
      desc.includes(q) ||
      tag.includes(q) ||
      category.includes(q)
    );
  });
});

// Show a full-bleed lead cover story when browsing a pillar without a search filter.
const isSearching = computed(() => searchQuery.value.trim().length > 0);

const spotlightSite = computed<GeoparkSite | null>(() => {
  if (isSearching.value || filteredSites.value.length === 0) return null;
  if (selectedPillar.value === 'ALL') {
    return (
      filteredSites.value.find(
        (s) => s.slug.includes('kayangan-api') || s.code === 'G-04'
      ) ||
      filteredSites.value[0] ||
      null
    );
  }
  return filteredSites.value[0] || null;
});

const archiveSites = computed<GeoparkSite[]>(() => {
  if (!spotlightSite.value) return filteredSites.value;
  return filteredSites.value.filter((s) => s.id !== spotlightSite.value?.id);
});

const activePillarOverview = computed(() => {
  if (selectedPillar.value === 'ALL') return null;
  const sample = filteredSites.value[0];
  if (!sample?.diversityTitle && !sample?.diversityInfo) return null;
  return {
    title: sample.diversityTitle || sample.category,
    info: sample.diversityInfo || '',
  };
});

function hasValidImage(site: GeoparkSite): boolean {
  return Boolean(site.image && !failedImageIds.value.has(site.id));
}

function handleImageError(siteId: string) {
  const next = new Set(failedImageIds.value);
  next.add(siteId);
  failedImageIds.value = next;
}

function stripHtmlText(html?: string): string {
  if (!html) return '';
  return html
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&deg;/gi, '°')
    .replace(/\s+/g, ' ')
    .trim();
}

function getExcerpt(site: GeoparkSite): string {
  const plain = stripHtmlText(site.narasiHtml);
  if (!plain) return site.desc;
  return plain.length > 220 ? `${plain.slice(0, 217).trim()}...` : plain;
}

function getReadingMinutes(site: GeoparkSite): number {
  const plain = stripHtmlText(site.narasiHtml);
  if (!plain) return 1;
  const words = plain.split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.round(words / 160));
}

function formatCoord(site: GeoparkSite): string {
  if (!site.hasCoordinate) return 'Kabupaten Bojonegoro';
  const lat = Math.abs(site.latitude).toLocaleString('id-ID', {
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
  });
  const lng = Math.abs(site.longitude).toLocaleString('id-ID', {
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
  });
  return `${lat}° LS · ${lng}° BT`;
}

function getCategoryTextClass(categorySlug?: string): string {
  if (categorySlug === 'geo-diversity') {
    return 'text-slate-700 dark:text-slate-300';
  }
  if (categorySlug === 'bio-diversity') {
    return 'text-[#176B4D] dark:text-emerald-400';
  }
  if (categorySlug === 'culture-diversity') {
    return 'text-amber-800 dark:text-amber-400';
  }
  return 'text-[#17251F] dark:text-slate-200';
}

function getCategoryPillClass(categorySlug?: string): string {
  if (categorySlug === 'geo-diversity') {
    return 'bg-slate-800/90 text-white dark:bg-slate-800 dark:text-slate-100';
  }
  if (categorySlug === 'bio-diversity') {
    return 'bg-[#176B4D]/95 text-white dark:bg-emerald-900/90 dark:text-emerald-100';
  }
  if (categorySlug === 'culture-diversity') {
    return 'bg-amber-800/95 text-white dark:bg-amber-900/90 dark:text-amber-100';
  }
  return 'bg-[#17251F]/90 text-white';
}

function resetFilters() {
  searchQuery.value = '';
  selectedPillar.value = 'ALL';
}
</script>

<template>
  <main class="w-full min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-[#17251F] dark:text-slate-100 pb-16 sm:pb-24">
    <!-- Editorial Masthead -->
    <header class="w-full pt-6 sm:pt-10 pb-8 sm:pb-10">
      <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
          <div class="max-w-3xl">
            <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400 tabular-nums">
              Geopark Nasional Bojonegoro · {{ sites.length }} Situs Terverifikasi
            </p>
            <h1 class="mt-1.5 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-[#17251F] dark:text-slate-50 leading-[1.12]">
              Jurnal Warisan Geologi, Hayati & Budaya
            </h1>
            <p class="mt-3 text-sm sm:text-base lg:text-lg text-[#4F5D54] dark:text-slate-300 leading-relaxed max-w-2xl">
              Dokumentasi ilmiah dan cerita lapangan dari 31 situs warisan bumi, keanekaragaman hayati, serta tradisi masyarakat di Kabupaten Bojonegoro.
            </p>
          </div>

          <div class="shrink-0">
            <NuxtLink
              to="/maps?tab=geosite"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-3 text-xs sm:text-sm font-semibold text-white transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
            >
              <UIcon name="i-lucide-map" class="size-4" />
              <span>Eksplorasi Peta Geosite</span>
            </NuxtLink>
          </div>
        </div>

        <!-- Toolbar: Segmented Pillar Tabs + Search Input -->
        <div class="mt-8 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div
            role="tablist"
            aria-label="Filter pilar Geopark Bojonegoro"
            class="inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-2xl bg-[#EAEFE9] dark:bg-[#0c1522] p-1.5 scrollbar-none"
          >
            <button
              v-for="tab in pillarTabs"
              :key="tab.key"
              type="button"
              role="tab"
              :aria-selected="selectedPillar === tab.key"
              class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs sm:text-sm font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
              :class="
                selectedPillar === tab.key
                  ? 'bg-white dark:bg-[#172436] text-[#17251F] dark:text-white shadow-xs'
                  : 'text-[#4F5D54] dark:text-slate-300 hover:text-[#17251F] dark:hover:text-white hover:bg-white/50 dark:hover:bg-white/5'
              "
              @click="selectedPillar = tab.key"
            >
              <span>{{ tab.label }}</span>
              <span
                class="rounded-md px-1.5 py-0.5 text-[11px] tabular-nums"
                :class="
                  selectedPillar === tab.key
                    ? 'bg-[#EEF2ED] dark:bg-white/10 text-[#17251F] dark:text-white font-bold'
                    : 'bg-black/[0.04] dark:bg-white/[0.06] text-[#4F5D54] dark:text-slate-300'
                "
              >
                {{ tab.count }}
              </span>
            </button>
          </div>

          <div class="w-full lg:w-80 shrink-0">
            <div class="relative flex items-center">
              <UIcon
                name="i-lucide-search"
                class="pointer-events-none absolute left-3.5 size-4 text-[#4F5D54] dark:text-slate-400"
              />
              <input
                v-model="searchQuery"
                type="text"
                inputmode="search"
                placeholder="Cari situs, kode G-01, atau lokasi..."
                aria-label="Cari situs Geopark Bojonegoro"
                class="w-full rounded-xl border border-[#DCE3DB] dark:border-white/10 bg-white dark:bg-[#0c1522] pl-10 pr-11 py-2.5 text-sm text-[#17251F] dark:text-slate-50 placeholder-[#4F5D54] dark:placeholder-slate-400 transition-colors focus:border-blue-600 dark:focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:focus:ring-blue-400/25 min-h-[44px]"
              />
              <button
                v-if="searchQuery"
                type="button"
                class="absolute right-0.5 size-11 rounded-lg flex items-center justify-center text-[#4F5D54] dark:text-slate-300 hover:text-[#17251F] dark:hover:text-white cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                aria-label="Hapus kata kunci pencarian"
                @click="searchQuery = ''"
              >
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Pillar Thematic Context Banner -->
        <div
          v-if="activePillarOverview && !isSearching"
          class="mt-5 rounded-2xl bg-[#EAEFE9]/80 dark:bg-[#0c1522] p-5 sm:p-6"
        >
          <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
            Tema Pilar Geopark
          </p>
          <h2 class="mt-1 text-lg sm:text-xl font-bold text-[#17251F] dark:text-slate-50">
            {{ activePillarOverview.title }}
          </h2>
          <p class="mt-1 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 leading-relaxed max-w-4xl">
            {{ activePillarOverview.info }}
          </p>
        </div>
      </div>
    </header>

    <section aria-label="Daftar artikel situs Geopark" class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <!-- Loading State (R-27) -->
      <div
        v-if="loading && sites.length === 0"
        role="status"
        class="rounded-3xl bg-white dark:bg-[#0c1522] p-12 text-center space-y-3"
      >
        <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-[#176B4D] dark:text-emerald-400 mx-auto" />
        <p class="text-sm font-semibold text-[#17251F] dark:text-slate-100">
          Memuat direktori 31 situs Geopark Bojonegoro...
        </p>
      </div>

      <!-- Error State (R-27) -->
      <div
        v-else-if="error && sites.length === 0"
        role="alert"
        class="rounded-3xl bg-amber-50 dark:bg-amber-950/40 p-8 sm:p-10 text-center space-y-3"
      >
        <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">
          Katalog situs Geopark Bojonegoro belum dapat dimuat saat ini.
        </p>
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-900 hover:bg-amber-950 dark:bg-amber-700 dark:hover:bg-amber-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white min-h-[44px] cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
          @click="fetchLiveSites(true)"
        >
          <UIcon name="i-lucide-refresh-cw" class="size-4" />
          <span>Muat Ulang Direktori</span>
        </button>
      </div>

      <!-- Empty Search State (R-27) -->
      <div
        v-else-if="filteredSites.length === 0"
        class="rounded-3xl bg-white dark:bg-[#0c1522] p-10 sm:p-12 text-center space-y-3"
      >
        <h2 class="text-lg font-bold text-[#17251F] dark:text-slate-50">
          Situs tidak ditemukan
        </h2>
        <p class="text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 max-w-md mx-auto">
          Tidak ada situs Geopark yang cocok dengan kata kunci "{{ searchQuery }}". Coba gunakan nama kecamatan, kode situs (seperti G-01), atau riset semua kategori.
        </p>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white min-h-[44px] cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
          @click="resetFilters"
        >
          Reset Pencarian & Filter
        </button>
      </div>

      <!-- Editorial Content -->
      <div v-else class="space-y-8 sm:space-y-10">
        <!-- Lead Cover Story (Featured 12-Col Spread when not searching) -->
        <article
          v-if="spotlightSite"
          class="rounded-3xl bg-white dark:bg-[#0c1522] overflow-hidden grid grid-cols-1 lg:grid-cols-12 items-stretch"
        >
          <NuxtLink
            :to="`/geopark/${spotlightSite.slug}`"
            class="lg:col-span-7 relative aspect-[16/10] lg:aspect-auto lg:min-h-[380px] bg-[#E8EDE7] dark:bg-slate-900 overflow-hidden block group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600"
          >
            <img
              v-if="hasValidImage(spotlightSite)"
              :src="spotlightSite.image"
              :alt="`Dokumentasi situs ${spotlightSite.name}`"
              class="w-full h-full object-cover object-center group-hover:scale-[1.02] transition-transform duration-500"
              @error="handleImageError(spotlightSite.id)"
            />
            <div
              v-else
              class="w-full h-full flex flex-col items-center justify-center p-6 text-center text-[#4F5D54] dark:text-slate-300"
            >
              <UIcon name="i-lucide-mountain" class="size-10 mb-2 text-[#176B4D] dark:text-emerald-400" />
              <span class="text-xs font-semibold">Dokumentasi Situs {{ spotlightSite.name }}</span>
            </div>

            <div class="absolute top-4 left-4 right-4 flex flex-wrap items-center justify-between gap-2">
              <div
                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold backdrop-blur-md"
                :class="getCategoryPillClass(spotlightSite.categorySlug)"
              >
                <span class="font-bold tabular-nums">{{ spotlightSite.code }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ spotlightSite.category }}</span>
              </div>

              <span
                v-if="spotlightSite.hasCoordinate"
                class="rounded-xl bg-black/65 backdrop-blur-md px-3 py-1.5 text-[11px] font-medium text-white tabular-nums"
              >
                {{ formatCoord(spotlightSite) }}
              </span>
            </div>
          </NuxtLink>

          <div class="lg:col-span-5 p-6 sm:p-8 lg:p-10 flex flex-col justify-between space-y-6">
            <div>
              <div class="flex items-center gap-2 text-xs font-semibold text-[#176B4D] dark:text-emerald-400 tabular-nums">
                <span>Sorotan Utama</span>
                <span aria-hidden="true">·</span>
                <span>{{ getReadingMinutes(spotlightSite) }} menit baca</span>
              </div>

              <h2 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight text-[#17251F] dark:text-slate-50 leading-snug">
                <NuxtLink
                  :to="`/geopark/${spotlightSite.slug}`"
                  class="hover:text-blue-700 dark:hover:text-blue-400 transition-colors focus-visible:outline-none focus-visible:underline"
                >
                  {{ spotlightSite.name }}
                </NuxtLink>
              </h2>

              <p class="mt-2 text-sm sm:text-base font-semibold text-[#17251F]/90 dark:text-slate-200 leading-snug">
                {{ spotlightSite.desc }}
              </p>

              <p class="mt-3 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 leading-relaxed line-clamp-4">
                {{ getExcerpt(spotlightSite) }}
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
              <NuxtLink
                :to="`/geopark/${spotlightSite.slug}`"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#17251F] hover:bg-[#0f1915] text-white dark:bg-white dark:hover:bg-slate-200 dark:text-slate-900 px-5 py-2.5 text-xs sm:text-sm font-semibold transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
              >
                <span>Baca Artikel Lengkap</span>
                <UIcon name="i-lucide-arrow-right" class="size-4" />
              </NuxtLink>

              <NuxtLink
                v-if="spotlightSite.hasCoordinate"
                :to="`/maps?geosite=${encodeURIComponent(spotlightSite.slug)}`"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#F2F5F1] dark:bg-white/[0.06] hover:bg-[#E4EAE3] dark:hover:bg-white/[0.12] px-4 py-2.5 text-xs sm:text-sm font-semibold text-blue-700 dark:text-blue-300 transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
              >
                <UIcon name="i-lucide-map-pin" class="size-4 text-blue-600 dark:text-blue-400" />
                <span>Tunjuk di Peta</span>
              </NuxtLink>
            </div>
          </div>
        </article>

        <!-- Search Result Count Banner -->
        <div
          v-if="isSearching"
          class="flex items-center justify-between gap-3 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 tabular-nums"
        >
          <span>
            Menampilkan <strong class="text-[#17251F] dark:text-white">{{ filteredSites.length }}</strong> situs untuk pencarian "{{ searchQuery }}"
          </span>
          <button
            type="button"
            class="text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline cursor-pointer min-h-[44px] inline-flex items-center"
            @click="resetFilters"
          >
            Reset Pencarian
          </button>
        </div>

        <!-- Editorial Archive Grid -->
        <div
          v-if="archiveSites.length > 0"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
        >
          <article
            v-for="site in archiveSites"
            :key="site.id"
            class="group rounded-2xl bg-white dark:bg-[#0c1522] overflow-hidden flex flex-col justify-between transition-colors"
          >
            <div>
              <NuxtLink
                :to="`/geopark/${site.slug}`"
                class="relative aspect-[16/10] w-full bg-[#E8EDE7] dark:bg-slate-900 overflow-hidden block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600"
              >
                <img
                  v-if="hasValidImage(site)"
                  :src="site.image"
                  :alt="`Dokumentasi situs ${site.name}`"
                  class="w-full h-full object-cover object-center group-hover:scale-[1.03] transition-transform duration-500"
                  loading="lazy"
                  @error="handleImageError(site.id)"
                />
                <div
                  v-else
                  class="w-full h-full flex flex-col items-center justify-center p-4 text-center text-[#4F5D54] dark:text-slate-400"
                >
                  <UIcon name="i-lucide-mountain" class="size-8 mb-1.5 text-[#176B4D] dark:text-emerald-400" />
                  <span class="text-xs font-semibold">{{ site.name }}</span>
                </div>

                <div class="absolute top-3 left-3 right-3 flex items-center justify-between gap-2">
                  <span
                    class="rounded-lg px-2.5 py-1 text-[11px] font-bold tabular-nums backdrop-blur-md"
                    :class="getCategoryPillClass(site.categorySlug)"
                  >
                    {{ site.code }}
                  </span>

                  <span
                    v-if="site.additionalImages && site.additionalImages.length > 0"
                    class="rounded-lg bg-black/65 backdrop-blur-md px-2 py-1 text-[11px] font-medium text-white tabular-nums inline-flex items-center gap-1"
                  >
                    <UIcon name="i-lucide-images" class="size-3" />
                    <span>{{ site.additionalImages.length + 1 }} foto</span>
                  </span>
                </div>
              </NuxtLink>

              <div class="p-5 sm:p-6 space-y-2.5">
                <div class="flex items-center justify-between gap-2 text-xs tabular-nums">
                  <span class="font-semibold" :class="getCategoryTextClass(site.categorySlug)">
                    {{ site.category }}
                  </span>
                  <span class="text-[#4F5D54] dark:text-slate-400">
                    {{ getReadingMinutes(site) }} menit baca
                  </span>
                </div>

                <h2 class="text-lg sm:text-xl font-bold text-[#17251F] dark:text-slate-50 leading-snug">
                  <NuxtLink
                    :to="`/geopark/${site.slug}`"
                    class="hover:text-blue-700 dark:hover:text-blue-400 transition-colors focus-visible:outline-none focus-visible:underline"
                  >
                    {{ site.name }}
                  </NuxtLink>
                </h2>

                <p class="text-xs sm:text-sm font-medium text-[#17251F]/85 dark:text-slate-200 line-clamp-2 leading-snug">
                  {{ site.desc }}
                </p>

                <p class="text-xs text-[#4F5D54] dark:text-slate-400 line-clamp-2 leading-relaxed">
                  {{ getExcerpt(site) }}
                </p>
              </div>
            </div>

            <div class="px-5 sm:px-6 pb-4 pt-2 border-t border-[#ECEFEB] dark:border-white/[0.06] flex items-center justify-between gap-3 text-xs font-semibold">
              <NuxtLink
                :to="`/geopark/${site.slug}`"
                class="inline-flex items-center gap-1.5 text-[#17251F] dark:text-slate-100 hover:text-blue-700 dark:hover:text-blue-400 min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg px-1"
              >
                <span>Baca Artikel</span>
                <UIcon name="i-lucide-arrow-right" class="size-3.5" />
              </NuxtLink>

              <NuxtLink
                v-if="site.hasCoordinate"
                :to="`/maps?geosite=${encodeURIComponent(site.slug)}`"
                class="inline-flex items-center gap-1.5 text-blue-700 dark:text-blue-400 hover:underline min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg px-1 tabular-nums"
              >
                <UIcon name="i-lucide-map-pin" class="size-3.5" />
                <span>Peta</span>
              </NuxtLink>
            </div>
          </article>
        </div>
      </div>
    </section>
  </main>
</template>

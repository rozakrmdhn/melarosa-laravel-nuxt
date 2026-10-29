<script setup lang="ts">
import { computed, ref } from 'vue';
import type { GeoparkCategoryFilter, GeoparkSite } from '~/composables/useGeoparkSites';

const props = defineProps<{
  sites: GeoparkSite[];
  geologicalSites: GeoparkSite[];
  biologicalSites: GeoparkSite[];
  culturalSites: GeoparkSite[];
  loading: boolean;
  error: string | null;
}>();

const emit = defineEmits<{
  (e: 'refresh'): void;
}>();

const activeTab = ref<GeoparkCategoryFilter>('ALL');

const tabs = computed(() => [
  { key: 'ALL' as GeoparkCategoryFilter, label: 'Semua Situs Resmi', count: props.sites.length },
  { key: 'geo-diversity' as GeoparkCategoryFilter, label: 'Warisan Geologi & Bumi', count: props.geologicalSites.length },
  { key: 'bio-diversity' as GeoparkCategoryFilter, label: 'Kekayaan Alam & Hayati', count: props.biologicalSites.length },
  { key: 'culture-diversity' as GeoparkCategoryFilter, label: 'Warisan Budaya & Sejarah', count: props.culturalSites.length },
]);

const currentSiteList = computed<GeoparkSite[]>(() => {
  if (activeTab.value === 'geo-diversity') return props.geologicalSites;
  if (activeTab.value === 'bio-diversity') return props.biologicalSites;
  if (activeTab.value === 'culture-diversity') return props.culturalSites;
  return props.sites;
});

// Situs unggulan galeri editorial (misal: Kayangan Api atau item pertama)
const featuredSite = computed<GeoparkSite | null>(() => {
  if (currentSiteList.value.length === 0) return null;
  const found = currentSiteList.value.find((s) => s.slug.includes('kayangan-api') || s.code.includes('G-01'));
  return found || currentSiteList.value[0] || null;
});

// Dua situs pendamping
const companionSites = computed<GeoparkSite[]>(() => {
  if (!featuredSite.value) return [];
  return currentSiteList.value
    .filter((s) => s.id !== featuredSite.value?.id)
    .slice(0, 2);
});

// Koleksi galeri lanjutan
const remainingGallerySites = computed<GeoparkSite[]>(() => {
  if (!featuredSite.value) return [];
  const excludedIds = new Set([featuredSite.value.id, ...companionSites.value.map((s) => s.id)]);
  return currentSiteList.value.filter((s) => !excludedIds.has(s.id)).slice(0, 3);
});

function handleImageError(event: Event) {
  const target = event.target as HTMLImageElement;
  if (target) {
    target.style.display = 'none';
  }
}

function getCategoryColor(categorySlug: string) {
  if (categorySlug === 'geo-diversity') return 'bg-slate-700 text-white';
  if (categorySlug === 'bio-diversity') return 'bg-[#176B4D] text-white';
  if (categorySlug === 'culture-diversity') return 'bg-amber-700 text-white';
  return 'bg-[#17251F] text-white';
}
</script>

<template>
  <section
    id="cerita-geopark"
    aria-labelledby="geopark-heading"
    class="relative w-full bg-white dark:bg-[#070b14] py-16 sm:py-20 lg:py-24 border-b border-[#DFE5DE] dark:border-white/10 scroll-mt-24"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <!-- Section Header Editorial -->
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-[#DFE5DE] dark:border-white/10 pb-6">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
            Warisan Alam & Budaya Bojonegoro
          </p>
          <h2
            id="geopark-heading"
            class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[#17251F] dark:text-white"
          >
            Cerita Geopark Bojonegoro
          </h2>
          <p class="mt-2 text-sm sm:text-base text-[#647067] dark:text-slate-300 max-w-2xl leading-relaxed">
            Dari fenomena api abadi Kayangan Api, sumur minyak tua Wonocolo, hingga tradisi luhur Masyarakat Samin. Jelajahi 31 situs warisan geologi, hayati, dan budaya yang tersebar di wilayah Bojonegoro.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <NuxtLink
            to="/geopark"
            class="inline-flex items-center gap-2 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-white/5 hover:border-[#17251F] dark:hover:border-white px-4 py-2.5 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-white transition-all min-h-[44px]"
          >
            <span>Katalog Lengkap 31 Situs</span>
            <UIcon name="i-lucide-arrow-right" class="size-4" />
          </NuxtLink>
        </div>
      </div>

      <!-- Filter Kategori Geopark: Warna Menyesuaikan Pilar Tematik -->
      <div
        role="tablist"
        aria-label="Filter Kategori Geopark"
        class="mt-6 flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none"
      >
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.key"
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs sm:text-sm font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          :class="
            activeTab === tab.key
              ? tab.key === 'geo-diversity'
                ? 'bg-slate-800 text-white shadow-xs'
                : tab.key === 'bio-diversity'
                  ? 'bg-[#176B4D] text-white shadow-xs'
                  : tab.key === 'culture-diversity'
                    ? 'bg-amber-700 text-white shadow-xs'
                    : 'bg-[#17251F] text-white dark:bg-white dark:text-slate-900 shadow-xs'
              : 'border border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5] dark:bg-[#0c1522] text-[#17251F] dark:text-slate-300 hover:border-slate-400 dark:hover:border-slate-500'
          "
          @click="activeTab = tab.key"
        >
          <span>{{ tab.label }}</span>
          <span
            class="rounded-full px-2 py-0.5 text-[11px] tabular-nums"
            :class="
              activeTab === tab.key
                ? 'bg-white/20 text-white font-bold'
                : 'bg-white dark:bg-white/10 text-[#647067] dark:text-slate-400'
            "
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Loading State (R-27) -->
      <div
        v-if="loading && currentSiteList.length === 0"
        class="mt-8 rounded-2xl border border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5] dark:bg-[#0c1522] p-12 text-center"
      >
        <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-blue-600 dark:text-blue-400 mx-auto mb-3" />
        <p class="text-sm font-medium text-[#17251F] dark:text-white">
          Memuat data situs Geopark Bojonegoro...
        </p>
      </div>

      <!-- Error State (R-27) -->
      <div
        v-else-if="error && currentSiteList.length === 0"
        class="mt-8 rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/20 p-8 text-center space-y-3"
      >
        <UIcon name="i-lucide-alert-circle" class="size-8 text-rose-600 dark:text-rose-400 mx-auto" />
        <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">
          Gagal memuat katalog Geopark
        </p>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-500 px-4 py-2 text-xs font-semibold text-white min-h-[44px] cursor-pointer"
          @click="emit('refresh')"
        >
          <span>Coba Muat Ulang</span>
        </button>
      </div>

      <!-- Galeri Asimetris Editorial -->
      <div v-else class="mt-8 space-y-8">
        <!-- Komposisi Utama: 1 Situs Unggulan Besar (Kiri 7) + 2 Situs Pendamping Vertikal (Kanan 5) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
          <!-- Situs Unggulan (Featured) -->
          <article
            v-if="featuredSite"
            class="lg:col-span-7 rounded-3xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-[#0c1522] overflow-hidden flex flex-col justify-between shadow-xs hover:border-slate-400 dark:hover:border-slate-600 transition-colors"
          >
            <!-- Foto Lebar Situs Unggulan -->
            <div class="relative w-full h-64 sm:h-80 bg-slate-200 dark:bg-slate-800 overflow-hidden">
              <img
                v-if="featuredSite.image"
                :src="featuredSite.image"
                :alt="`Dokumentasi situs ${featuredSite.name}`"
                class="w-full h-full object-cover object-center"
                loading="lazy"
                @error="handleImageError"
              />
              <div
                v-else
                class="w-full h-full flex flex-col items-center justify-center p-6 text-center text-[#647067] dark:text-slate-400 bg-linear-to-b from-slate-100 to-slate-200 dark:from-slate-900 dark:to-slate-800"
              >
                <UIcon name="i-lucide-mountain" class="size-10 mb-2 opacity-60 text-[#176B4D] dark:text-emerald-400" />
                <span class="text-xs font-semibold">Arsip Geopark Bojonegoro</span>
              </div>

              <!-- Badge Kategori & Kode -->
              <div class="absolute top-4 left-4 flex items-center gap-2">
                <span class="rounded-lg bg-black/75 backdrop-blur-md px-3 py-1 text-xs font-bold text-white font-mono">
                  {{ featuredSite.code }}
                </span>
                <span
                  class="rounded-lg px-3 py-1 text-xs font-semibold shadow-xs"
                  :class="getCategoryColor(featuredSite.categorySlug)"
                >
                  {{ featuredSite.category }}
                </span>
              </div>
            </div>

            <!-- Konten Narasi Editorial Situs Unggulan -->
            <div class="p-6 sm:p-8 flex-1 flex flex-col justify-between space-y-4">
              <div>
                <h3 class="text-xl sm:text-2xl font-bold text-[#17251F] dark:text-white leading-snug">
                  {{ featuredSite.name }}
                </h3>
                <p class="mt-2.5 text-sm sm:text-base text-[#647067] dark:text-slate-300 leading-relaxed">
                  {{ featuredSite.desc }}
                </p>
              </div>

              <div class="pt-4 border-t border-[#DFE5DE] dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
                <NuxtLink
                  :to="`/geopark/${featuredSite.slug}`"
                  class="inline-flex items-center gap-2 rounded-xl bg-[#17251F] hover:bg-black text-white dark:bg-white dark:hover:bg-slate-200 dark:text-slate-900 px-5 py-2.5 text-sm font-semibold transition-all min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                >
                  <UIcon name="i-lucide-book-open" class="size-4" />
                  <span>Baca Cerita Lengkap</span>
                </NuxtLink>

                <NuxtLink
                  v-if="featuredSite.hasCoordinate"
                  :to="`/maps?geosite=${encodeURIComponent(featuredSite.slug)}`"
                  class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 dark:border-blue-800/60 bg-blue-50/60 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/60 px-4 py-2.5 text-sm font-semibold text-blue-700 dark:text-blue-300 transition-all min-h-[44px]"
                >
                  <UIcon name="i-lucide-map-pin" class="size-4 text-blue-600 dark:text-blue-400" />
                  <span>Tunjuk di Peta</span>
                </NuxtLink>
              </div>
            </div>
          </article>

          <!-- 2 Situs Pendamping Vertikal (5 Kolom lg) -->
          <div class="lg:col-span-5 flex flex-col gap-6">
            <article
              v-for="site in companionSites"
              :key="site.id"
              class="flex-1 rounded-3xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-[#0c1522] overflow-hidden flex flex-col justify-between shadow-xs hover:border-slate-400 dark:hover:border-slate-600 transition-colors"
            >
              <div class="relative w-full h-40 bg-slate-200 dark:bg-slate-800 overflow-hidden">
                <img
                  v-if="site.image"
                  :src="site.image"
                  :alt="`Dokumentasi situs ${site.name}`"
                  class="w-full h-full object-cover object-center"
                  loading="lazy"
                  @error="handleImageError"
                />
                <div
                  v-else
                  class="w-full h-full flex flex-col items-center justify-center p-4 text-center text-[#647067] dark:text-slate-400 bg-slate-100 dark:bg-slate-800"
                >
                  <UIcon name="i-lucide-mountain" class="size-7 mb-1 text-[#176B4D] dark:text-emerald-400" />
                  <span class="text-xs">Geopark Bojonegoro</span>
                </div>

                <div class="absolute top-3 left-3 flex items-center gap-2">
                  <span class="rounded-md bg-black/75 backdrop-blur-md px-2 py-0.5 text-xs font-mono font-bold text-white">
                    {{ site.code }}
                  </span>
                  <span
                    class="rounded-md px-2 py-0.5 text-xs font-semibold shadow-xs"
                    :class="getCategoryColor(site.categorySlug)"
                  >
                    {{ site.category }}
                  </span>
                </div>
              </div>

              <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
                <div>
                  <h4 class="text-base font-bold text-[#17251F] dark:text-white leading-snug">
                    {{ site.name }}
                  </h4>
                  <p class="mt-1 text-xs text-[#647067] dark:text-slate-300 line-clamp-2 leading-relaxed">
                    {{ site.desc }}
                  </p>
                </div>

                <div class="pt-3 border-t border-[#DFE5DE] dark:border-white/10 flex items-center justify-between text-xs font-semibold">
                  <NuxtLink
                    :to="`/geopark/${site.slug}`"
                    class="text-[#17251F] dark:text-white hover:text-blue-600 dark:hover:text-blue-400 inline-flex items-center gap-1 min-h-[38px]"
                  >
                    <span>Baca Cerita</span>
                    <UIcon name="i-lucide-arrow-right" class="size-3.5" />
                  </NuxtLink>

                  <NuxtLink
                    v-if="site.hasCoordinate"
                    :to="`/maps?geosite=${encodeURIComponent(site.slug)}`"
                    class="text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1 min-h-[38px]"
                  >
                    <UIcon name="i-lucide-map-pin" class="size-3.5" />
                    <span>Lihat di Peta</span>
                  </NuxtLink>
                </div>
              </div>
            </article>
          </div>
        </div>

        <!-- 3 Situs Lanjutan dalam Galeri Baris -->
        <div v-if="remainingGallerySites.length > 0" class="pt-4 border-t border-[#DFE5DE] dark:border-white/10">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <article
              v-for="site in remainingGallerySites"
              :key="site.id"
              class="rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] p-4 flex flex-col justify-between hover:border-slate-400 dark:hover:border-slate-600 transition-colors"
            >
              <div>
                <div class="flex items-center justify-between gap-2 text-xs">
                  <span class="font-mono font-bold text-slate-700 dark:text-slate-300">
                    {{ site.code }}
                  </span>
                  <span
                    class="text-[11px] font-semibold px-2 py-0.5 rounded"
                    :class="
                      site.categorySlug === 'geo-diversity'
                        ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        : site.categorySlug === 'bio-diversity'
                          ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                          : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                    "
                  >
                    {{ site.category }}
                  </span>
                </div>
                <h4 class="mt-2 text-sm font-bold text-[#17251F] dark:text-white leading-snug">
                  {{ site.name }}
                </h4>
                <p class="mt-1 text-xs text-[#647067] dark:text-slate-300 line-clamp-2">
                  {{ site.desc }}
                </p>
              </div>

              <div class="mt-3 pt-2.5 border-t border-[#DFE5DE] dark:border-white/10 flex items-center justify-between text-xs font-semibold">
                <NuxtLink
                  :to="`/geopark/${site.slug}`"
                  class="text-[#17251F] dark:text-white hover:text-blue-600 dark:hover:text-blue-400 min-h-[36px] inline-flex items-center"
                >
                  <span>Detail Situs</span>
                </NuxtLink>
                <NuxtLink
                  v-if="site.hasCoordinate"
                  :to="`/maps?geosite=${encodeURIComponent(site.slug)}`"
                  class="text-blue-600 dark:text-blue-400 hover:underline min-h-[36px] inline-flex items-center gap-1"
                >
                  <UIcon name="i-lucide-map-pin" class="size-3" />
                  <span>Peta</span>
                </NuxtLink>
              </div>
            </article>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

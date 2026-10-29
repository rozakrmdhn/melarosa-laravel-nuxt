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
const failedImageIds = ref<Set<string>>(new Set());

const tabs = computed(() => [
  { key: 'ALL' as GeoparkCategoryFilter, label: 'Semua Situs', count: props.sites.length },
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

const featuredSite = computed<GeoparkSite | null>(() => {
  if (currentSiteList.value.length === 0) return null;
  const found = currentSiteList.value.find(
    (s) => s.slug.includes('kayangan-api') || s.code.includes('G-01')
  );
  return found || currentSiteList.value[0] || null;
});

const companionSites = computed<GeoparkSite[]>(() => {
  if (!featuredSite.value) return [];
  return currentSiteList.value
    .filter((s) => s.id !== featuredSite.value?.id)
    .slice(0, 2);
});

const remainingGallerySites = computed<GeoparkSite[]>(() => {
  if (!featuredSite.value) return [];
  const excludedIds = new Set([
    featuredSite.value.id,
    ...companionSites.value.map((s) => s.id),
  ]);
  return currentSiteList.value.filter((s) => !excludedIds.has(s.id)).slice(0, 3);
});

function hasValidImage(site: GeoparkSite): boolean {
  return Boolean(site.image && !failedImageIds.value.has(site.id));
}

function handleImageError(siteId: string) {
  const next = new Set(failedImageIds.value);
  next.add(siteId);
  failedImageIds.value = next;
}

function formatSiteCoord(site: GeoparkSite): string {
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

function getCategoryPillClass(categorySlug: string): string {
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

function getCategoryTextClass(categorySlug: string): string {
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
</script>

<template>
  <section
    id="cerita-geopark"
    aria-labelledby="geopark-heading"
    class="relative w-full bg-white dark:bg-[#070b14] py-14 sm:py-20 lg:py-24 border-b border-[#E4EAE3] dark:border-white/[0.07] scroll-mt-24"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <!-- Editorial Header -->
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div class="max-w-2xl">
          <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
            Warisan Alam & Budaya Bojonegoro
          </p>
          <h2
            id="geopark-heading"
            class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[#17251F] dark:text-slate-50 leading-tight"
          >
            Cerita Geopark Bojonegoro
          </h2>
          <p class="mt-2 text-sm sm:text-base text-[#4F5D54] dark:text-slate-300 leading-relaxed">
            Dari fenomena api abadi Kayangan Api, sumur minyak tradisional Wonocolo, hingga kearifan Masyarakat Samin. Jelajahi 31 situs warisan geologi, hayati, dan budaya di Kabupaten Bojonegoro.
          </p>
        </div>

        <div class="shrink-0">
          <NuxtLink
            to="/geopark"
            class="inline-flex items-center gap-2 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.06] hover:bg-[#E8EDE7] dark:hover:bg-white/[0.1] px-4 py-2.5 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-slate-100 transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
          >
            <span>Buka Katalog 31 Situs</span>
            <UIcon name="i-lucide-arrow-right" class="size-4" />
          </NuxtLink>
        </div>
      </div>

      <!-- Segmented Tonal Filter Bar -->
      <div
        role="tablist"
        aria-label="Filter kategori Geopark Bojonegoro"
        class="mt-6 inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-2xl bg-[#F2F5F1] dark:bg-[#0c1522] p-1.5 scrollbar-none"
      >
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.key"
          class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs sm:text-sm font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
          :class="
            activeTab === tab.key
              ? 'bg-white dark:bg-[#172436] text-[#17251F] dark:text-white shadow-xs'
              : 'text-[#4F5D54] dark:text-slate-300 hover:text-[#17251F] dark:hover:text-white hover:bg-white/60 dark:hover:bg-white/5'
          "
          @click="activeTab = tab.key"
        >
          <span>{{ tab.label }}</span>
          <span
            class="rounded-md px-1.5 py-0.5 text-[11px] tabular-nums"
            :class="
              activeTab === tab.key
                ? 'bg-[#EEF2ED] dark:bg-white/10 text-[#17251F] dark:text-white font-bold'
                : 'bg-black/[0.04] dark:bg-white/[0.06] text-[#4F5D54] dark:text-slate-300'
            "
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Loading State (R-27) -->
      <div
        v-if="loading && currentSiteList.length === 0"
        role="status"
        class="mt-8 rounded-2xl bg-[#F7F8F5] dark:bg-[#0c1522] p-10 sm:p-12 text-center space-y-3"
      >
        <UIcon name="i-lucide-loader-2" class="size-7 animate-spin text-[#176B4D] dark:text-emerald-400 mx-auto" />
        <p class="text-sm font-semibold text-[#17251F] dark:text-slate-100">
          Memuat katalog situs Geopark Bojonegoro...
        </p>
      </div>

      <!-- Error State (R-27) -->
      <div
        v-else-if="error && currentSiteList.length === 0"
        role="alert"
        class="mt-8 rounded-2xl bg-amber-50 dark:bg-amber-950/40 p-8 text-center space-y-3"
      >
        <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">
          Katalog situs Geopark Bojonegoro belum dapat dimuat saat ini.
        </p>
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-900 hover:bg-amber-950 dark:bg-amber-700 dark:hover:bg-amber-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white min-h-[44px] cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
          @click="emit('refresh')"
        >
          <UIcon name="i-lucide-refresh-cw" class="size-4" />
          <span>Muat Ulang Katalog</span>
        </button>
      </div>

      <!-- Empty State (R-27) -->
      <div
        v-else-if="currentSiteList.length === 0"
        class="mt-8 rounded-2xl bg-[#F7F8F5] dark:bg-[#0c1522] p-8 sm:p-10 text-center space-y-3"
      >
        <h3 class="text-base font-bold text-[#17251F] dark:text-slate-50">
          Belum ada situs pada kategori ini
        </h3>
        <p class="text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 max-w-md mx-auto">
          Pilih kategori lain untuk melihat daftar situs warisan geologi, hayati, dan budaya Bojonegoro.
        </p>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white min-h-[44px] cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
          @click="activeTab = 'ALL'"
        >
          Tampilkan Semua Situs
        </button>
      </div>

      <!-- Editorial Gallery Spread -->
      <div v-else class="mt-8 space-y-6 lg:space-y-8">
        <!-- Main Spread: 7-Column Lead Story + 5-Column Companion Stories -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-stretch">
          <!-- Featured Lead Story (7 Columns) -->
          <article
            v-if="featuredSite"
            class="lg:col-span-7 rounded-3xl bg-[#F7F8F5] dark:bg-[#0c1522] overflow-hidden flex flex-col justify-between"
          >
            <div class="relative w-full aspect-[16/10] bg-[#E8EDE7] dark:bg-slate-900 overflow-hidden">
              <img
                v-if="hasValidImage(featuredSite)"
                :src="featuredSite.image"
                :alt="`Dokumentasi situs ${featuredSite.name}`"
                class="w-full h-full object-cover object-center"
                loading="lazy"
                @error="handleImageError(featuredSite.id)"
              />
              <div
                v-else
                class="w-full h-full flex flex-col items-center justify-center p-6 text-center text-[#4F5D54] dark:text-slate-300 bg-[#EAEFE9] dark:bg-slate-900"
              >
                <UIcon name="i-lucide-mountain" class="size-10 mb-2 text-[#176B4D] dark:text-emerald-400" />
                <span class="text-xs font-semibold">Dokumentasi Situs {{ featuredSite.name }}</span>
                <span class="mt-1 text-[11px] tabular-nums opacity-80">{{ formatSiteCoord(featuredSite) }}</span>
              </div>

              <div class="absolute top-4 left-4 right-4 flex flex-wrap items-center justify-between gap-2">
                <div class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold shadow-xs backdrop-blur-md" :class="getCategoryPillClass(featuredSite.categorySlug)">
                  <span class="font-bold tabular-nums">{{ featuredSite.code }}</span>
                  <span aria-hidden="true">·</span>
                  <span>{{ featuredSite.category }}</span>
                </div>

                <span
                  v-if="featuredSite.hasCoordinate"
                  class="rounded-xl bg-black/65 backdrop-blur-md px-3 py-1.5 text-[11px] font-medium text-white tabular-nums"
                >
                  {{ formatSiteCoord(featuredSite) }}
                </span>
              </div>
            </div>

            <div class="p-6 sm:p-8 flex-1 flex flex-col justify-between space-y-6">
              <div>
                <h3 class="text-xl sm:text-2xl lg:text-[26px] font-bold tracking-tight text-[#17251F] dark:text-slate-50 leading-snug">
                  {{ featuredSite.name }}
                </h3>
                <p class="mt-2.5 text-sm sm:text-base text-[#4F5D54] dark:text-slate-300 leading-relaxed">
                  {{ featuredSite.desc }}
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-3 pt-2">
                <NuxtLink
                  :to="`/geopark/${featuredSite.slug}`"
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#17251F] hover:bg-[#0f1915] text-white dark:bg-white dark:hover:bg-slate-200 dark:text-slate-900 px-5 py-2.5 text-xs sm:text-sm font-semibold transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                >
                  <span>Baca Cerita Situs</span>
                  <UIcon name="i-lucide-arrow-right" class="size-4" />
                </NuxtLink>

                <NuxtLink
                  v-if="featuredSite.hasCoordinate"
                  :to="`/maps?geosite=${encodeURIComponent(featuredSite.slug)}`"
                  class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white dark:bg-white/[0.06] hover:bg-[#EAEFE9] dark:hover:bg-white/[0.12] px-4 py-2.5 text-xs sm:text-sm font-semibold text-blue-700 dark:text-blue-300 transition-colors min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                >
                  <UIcon name="i-lucide-map-pin" class="size-4 text-blue-600 dark:text-blue-400" />
                  <span>Tunjuk Lokasi di Peta</span>
                </NuxtLink>
              </div>
            </div>
          </article>

          <!-- Companion Stories (5 Columns, Stacked Editorial Featurettes) -->
          <div class="lg:col-span-5 flex flex-col gap-5">
            <article
              v-for="site in companionSites"
              :key="site.id"
              class="flex-1 rounded-3xl bg-[#F7F8F5] dark:bg-[#0c1522] p-4 sm:p-5 flex flex-col justify-between gap-4"
            >
              <div class="flex items-start gap-4">
                <div class="relative w-28 sm:w-36 aspect-[4/3] rounded-2xl bg-[#E8EDE7] dark:bg-slate-900 overflow-hidden shrink-0">
                  <img
                    v-if="hasValidImage(site)"
                    :src="site.image"
                    :alt="`Dokumentasi situs ${site.name}`"
                    class="w-full h-full object-cover object-center"
                    loading="lazy"
                    @error="handleImageError(site.id)"
                  />
                  <div
                    v-else
                    class="w-full h-full flex flex-col items-center justify-center p-2 text-center text-[#4F5D54] dark:text-slate-400"
                  >
                    <UIcon name="i-lucide-mountain" class="size-6 text-[#176B4D] dark:text-emerald-400" />
                    <span class="mt-1 text-[10px] font-bold tabular-nums">{{ site.code }}</span>
                  </div>
                </div>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="font-bold tabular-nums text-[#17251F] dark:text-slate-200">
                      {{ site.code }}
                    </span>
                    <span class="text-[#7A877E] dark:text-slate-500" aria-hidden="true">·</span>
                    <span class="font-semibold" :class="getCategoryTextClass(site.categorySlug)">
                      {{ site.category }}
                    </span>
                  </div>

                  <h3 class="mt-1 text-base sm:text-lg font-bold text-[#17251F] dark:text-slate-50 leading-snug">
                    {{ site.name }}
                  </h3>

                  <p class="mt-1.5 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 line-clamp-2 leading-relaxed">
                    {{ site.desc }}
                  </p>
                </div>
              </div>

              <div class="flex items-center justify-between gap-3 pt-2 border-t border-[#E6ECE5] dark:border-white/[0.06] text-xs sm:text-sm font-semibold">
                <NuxtLink
                  :to="`/geopark/${site.slug}`"
                  class="inline-flex items-center gap-1.5 text-[#17251F] dark:text-slate-100 hover:text-blue-700 dark:hover:text-blue-400 min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg px-1"
                >
                  <span>Baca Cerita</span>
                  <UIcon name="i-lucide-arrow-right" class="size-3.5" />
                </NuxtLink>

                <NuxtLink
                  v-if="site.hasCoordinate"
                  :to="`/maps?geosite=${encodeURIComponent(site.slug)}`"
                  class="inline-flex items-center gap-1.5 text-blue-700 dark:text-blue-400 hover:underline min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg px-1"
                >
                  <UIcon name="i-lucide-map-pin" class="size-3.5" />
                  <span>Lihat di Peta</span>
                </NuxtLink>
              </div>
            </article>
          </div>
        </div>

        <!-- Secondary Editorial Ledger (3 Additional Sites, Hairline Divided, Zero Heavy Box Borders) -->
        <div
          v-if="remainingGallerySites.length > 0"
          class="rounded-2xl bg-[#F7F8F5] dark:bg-[#0c1522] p-5 sm:p-6"
        >
          <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#E4EAE3] dark:divide-white/[0.07]">
            <article
              v-for="(site, idx) in remainingGallerySites"
              :key="site.id"
              class="flex flex-col justify-between"
              :class="[
                idx === 0 ? 'pb-4 md:pb-0 md:pr-6' : '',
                idx === 1 ? 'py-4 md:py-0 md:px-6' : '',
                idx === 2 ? 'pt-4 md:pt-0 md:pl-6' : '',
              ]"
            >
              <div>
                <div class="flex items-center justify-between gap-2 text-xs">
                  <div class="flex items-center gap-1.5">
                    <span class="font-bold tabular-nums text-[#17251F] dark:text-slate-200">
                      {{ site.code }}
                    </span>
                    <span class="text-[#7A877E] dark:text-slate-500" aria-hidden="true">·</span>
                    <span class="font-semibold" :class="getCategoryTextClass(site.categorySlug)">
                      {{ site.category }}
                    </span>
                  </div>
                </div>

                <h3 class="mt-1.5 text-base font-bold text-[#17251F] dark:text-slate-50 leading-snug">
                  {{ site.name }}
                </h3>

                <p class="mt-1 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 line-clamp-2 leading-relaxed">
                  {{ site.desc }}
                </p>
              </div>

              <div class="mt-3 flex items-center justify-between gap-3 text-xs font-semibold">
                <NuxtLink
                  :to="`/geopark/${site.slug}`"
                  class="inline-flex items-center gap-1 text-[#17251F] dark:text-slate-100 hover:text-blue-700 dark:hover:text-blue-400 min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg px-1"
                >
                  <span>Detail Situs</span>
                  <UIcon name="i-lucide-arrow-right" class="size-3.5" />
                </NuxtLink>

                <NuxtLink
                  v-if="site.hasCoordinate"
                  :to="`/maps?geosite=${encodeURIComponent(site.slug)}`"
                  class="inline-flex items-center gap-1 text-blue-700 dark:text-blue-400 hover:underline min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg px-1"
                >
                  <UIcon name="i-lucide-map-pin" class="size-3.5" />
                  <span>Lokasi Peta</span>
                </NuxtLink>
              </div>
            </article>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

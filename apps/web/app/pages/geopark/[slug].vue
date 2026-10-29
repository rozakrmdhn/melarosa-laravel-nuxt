<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import DOMPurify from 'dompurify';
import { useGeoparkSites, type GeoparkSite } from '~/composables/useGeoparkSites';

const route = useRoute();
const slug = computed(() => String(route.params.slug || ''));

const { sites, loading, fetchLiveSites } = useGeoparkSites();

onMounted(() => {
  if (sites.value.length === 0) {
    fetchLiveSites();
  }
});

const currentIndex = computed<number>(() =>
  sites.value.findIndex((s) => s.slug === slug.value)
);

const site = computed<GeoparkSite | null>(() =>
  currentIndex.value >= 0 ? sites.value[currentIndex.value] || null : null
);

const prevSite = computed<GeoparkSite | null>(() =>
  currentIndex.value > 0 ? sites.value[currentIndex.value - 1] || null : null
);

const nextSite = computed<GeoparkSite | null>(() =>
  currentIndex.value >= 0 && currentIndex.value < sites.value.length - 1
    ? sites.value[currentIndex.value + 1] || null
    : null
);

const relatedSites = computed<GeoparkSite[]>(() => {
  if (!site.value) return [];
  const sameCategory = sites.value.filter(
    (s) => s.id !== site.value?.id && s.categorySlug === site.value?.categorySlug
  );
  if (sameCategory.length >= 3) return sameCategory.slice(0, 3);
  const others = sites.value.filter((s) => s.id !== site.value?.id);
  return others.slice(0, 3);
});

const pageTitle = computed(() => site.value?.name || 'Artikel Situs Geopark');
const pageDescription = computed(
  () => site.value?.desc || 'Ulasan resmi situs Geopark Nasional Bojonegoro.'
);

useSeoMeta({
  title: () => `${pageTitle.value} (${site.value?.code || 'Geosite'})`,
  description: () => pageDescription.value,
  ogImage: () => site.value?.image || undefined,
});

const activePhotoIndex = ref(0);
const failedPhotoUrls = ref<Set<string>>(new Set());

watch(slug, () => {
  activePhotoIndex.value = 0;
});

const allPhotos = computed<string[]>(() => {
  if (!site.value) return [];
  const raw = [site.value.image, ...(site.value.additionalImages || [])].filter(
    (url): url is string => Boolean(url && !failedPhotoUrls.value.has(url))
  );
  return Array.from(new Set(raw));
});

const activePhotoUrl = computed<string | null>(
  () => allPhotos.value[activePhotoIndex.value] || allPhotos.value[0] || null
);

function handlePhotoError(url: string) {
  const next = new Set(failedPhotoUrls.value);
  next.add(url);
  failedPhotoUrls.value = next;
  if (activePhotoIndex.value >= allPhotos.value.length) {
    activePhotoIndex.value = 0;
  }
}

function selectPhoto(idx: number) {
  activePhotoIndex.value = idx;
}

function stepPhoto(delta: number) {
  const len = allPhotos.value.length;
  if (len <= 1) return;
  activePhotoIndex.value = (activePhotoIndex.value + delta + len) % len;
}

const sanitizedNarasi = computed(() => {
  if (!site.value?.narasiHtml) return '';
  if (import.meta.server) return site.value.narasiHtml;
  return DOMPurify.sanitize(site.value.narasiHtml);
});

function stripHtmlText(html?: string): string {
  if (!html) return '';
  return html
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&deg;/gi, '°')
    .replace(/\s+/g, ' ')
    .trim();
}

const readingMinutes = computed<number>(() => {
  if (!site.value) return 1;
  const plain = stripHtmlText(site.value.narasiHtml);
  const words = plain.split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.round(words / 160));
});

const wordCount = computed<number>(() => {
  if (!site.value) return 0;
  const plain = stripHtmlText(site.value.narasiHtml);
  return plain ? plain.split(/\s+/).filter(Boolean).length : 0;
});

function formatSiteCoord(item: GeoparkSite): string {
  if (!item.hasCoordinate) return 'Kabupaten Bojonegoro';
  const lat = Math.abs(item.latitude).toLocaleString('id-ID', {
    minimumFractionDigits: 5,
    maximumFractionDigits: 5,
  });
  const lng = Math.abs(item.longitude).toLocaleString('id-ID', {
    minimumFractionDigits: 5,
    maximumFractionDigits: 5,
  });
  return `${lat}° LS, ${lng}° BT`;
}

function getCategoryPillClass(categorySlug?: string): string {
  if (categorySlug === 'geo-diversity') {
    return 'bg-slate-800 text-white dark:bg-slate-800 dark:text-slate-100';
  }
  if (categorySlug === 'bio-diversity') {
    return 'bg-[#176B4D] text-white dark:bg-emerald-900 dark:text-emerald-100';
  }
  if (categorySlug === 'culture-diversity') {
    return 'bg-amber-800 text-white dark:bg-amber-900 dark:text-amber-100';
  }
  return 'bg-[#17251F] text-white';
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

const isCoordCopied = ref(false);
function copyCoordinates() {
  if (!site.value?.hasCoordinate || !import.meta.client) return;
  const text = `${site.value.coordinate[1].toFixed(6)}, ${site.value.coordinate[0].toFixed(6)}`;
  navigator.clipboard.writeText(text);
  isCoordCopied.value = true;
  setTimeout(() => {
    isCoordCopied.value = false;
  }, 2000);
}

const isLinkCopied = ref(false);
function copyArticleLink() {
  if (!import.meta.client) return;
  navigator.clipboard.writeText(window.location.href);
  isLinkCopied.value = true;
  setTimeout(() => {
    isLinkCopied.value = false;
  }, 2000);
}

function openSiteInMap(item: GeoparkSite) {
  if (item.hasCoordinate) {
    navigateTo(`/maps?geosite=${encodeURIComponent(item.slug)}`);
  } else {
    navigateTo(`/maps?search=${encodeURIComponent(item.name)}`);
  }
}
</script>

<template>
  <main class="w-full min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-[#17251F] dark:text-slate-100 pb-16 sm:pb-24">
    <!-- Loading State (R-27) -->
    <div
      v-if="loading && !site"
      role="status"
      class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 pt-12 pb-20 text-center space-y-3"
    >
      <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-[#176B4D] dark:text-emerald-400 mx-auto" />
      <p class="text-sm font-semibold text-[#17251F] dark:text-slate-100">
        Memuat artikel situs Geopark Bojonegoro...
      </p>
    </div>

    <!-- Article Content -->
    <article v-else-if="site" class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-6 sm:pt-10">
      <!-- Top Breadcrumb & Back Action -->
      <nav aria-label="Breadcrumb Geopark" class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300">
          <NuxtLink
            to="/geopark"
            class="inline-flex items-center gap-1.5 font-semibold text-[#17251F] dark:text-slate-100 hover:text-blue-700 dark:hover:text-blue-400 min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg pr-2"
          >
            <UIcon name="i-lucide-arrow-left" class="size-4" />
            <span>Katalog Geopark</span>
          </NuxtLink>
          <span aria-hidden="true">/</span>
          <span>{{ site.category }}</span>
          <span aria-hidden="true">/</span>
          <span class="font-bold text-[#17251F] dark:text-white tabular-nums">{{ site.code }}</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-[#EAEFE9] dark:bg-[#0c1522] hover:bg-[#DFE6DD] dark:hover:bg-white/[0.08] px-3.5 py-2 text-xs font-semibold text-[#17251F] dark:text-slate-200 cursor-pointer min-h-[44px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
            @click="copyArticleLink"
          >
            <UIcon :name="isLinkCopied ? 'i-lucide-check' : 'i-lucide-link'" class="size-3.5" />
            <span>{{ isLinkCopied ? 'Tautan Tersalin' : 'Salin Tautan' }}</span>
          </button>
        </div>
      </nav>

      <!-- Editorial Article Header -->
      <header class="mt-4 sm:mt-6 max-w-4xl">
        <div class="flex flex-wrap items-center gap-2.5 text-xs tabular-nums">
          <span
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1 font-semibold"
            :class="getCategoryPillClass(site.categorySlug)"
          >
            <span class="font-bold">{{ site.code }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ site.category }}</span>
          </span>

          <span class="text-[#4F5D54] dark:text-slate-300 font-medium">
            {{ readingMinutes }} menit baca ({{ wordCount.toLocaleString('id-ID') }} kata)
          </span>

          <span
            v-if="allPhotos.length > 0"
            class="text-[#4F5D54] dark:text-slate-300 font-medium"
          >
            · {{ allPhotos.length }} foto dokumentasi
          </span>
        </div>

        <h1 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-[#17251F] dark:text-slate-50 leading-[1.12]">
          {{ site.name }}
        </h1>

        <p class="mt-3 text-lg sm:text-xl text-[#4F5D54] dark:text-slate-300 leading-relaxed">
          {{ site.desc }}
        </p>
      </header>

      <!-- Cinematic Hero Figure & Interactive Photo Switcher -->
      <figure class="mt-8 sm:mt-10">
        <div class="relative w-full aspect-[16/10] sm:aspect-[16/9] max-h-[560px] rounded-3xl bg-[#E8EDE7] dark:bg-[#0c1522] overflow-hidden">
          <img
            v-if="activePhotoUrl"
            :src="activePhotoUrl"
            :alt="`Dokumentasi ${site.name} - Foto ${activePhotoIndex + 1}`"
            class="w-full h-full object-cover object-center"
            @error="handlePhotoUrl => activePhotoUrl && handlePhotoError(activePhotoUrl)"
          />
          <div
            v-else
            class="w-full h-full flex flex-col items-center justify-center p-8 text-center text-[#4F5D54] dark:text-slate-300"
          >
            <UIcon name="i-lucide-mountain" class="size-12 mb-2 text-[#176B4D] dark:text-emerald-400" />
            <span class="text-sm font-semibold">Dokumentasi Situs {{ site.name }}</span>
            <span class="mt-1 text-xs tabular-nums">{{ formatSiteCoord(site) }}</span>
          </div>

          <!-- Prev / Next Photo Controls if Multiple Photos Exist -->
          <template v-if="allPhotos.length > 1">
            <button
              type="button"
              aria-label="Foto sebelumnya"
              class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 size-11 rounded-full bg-black/60 hover:bg-black/80 backdrop-blur-md text-white flex items-center justify-center cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
              @click="stepPhoto(-1)"
            >
              <UIcon name="i-lucide-chevron-left" class="size-5" />
            </button>
            <button
              type="button"
              aria-label="Foto berikutnya"
              class="absolute right-3 sm:right-4 top-1/2 -translate-y-1/2 size-11 rounded-full bg-black/60 hover:bg-black/80 backdrop-blur-md text-white flex items-center justify-center cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
              @click="stepPhoto(1)"
            >
              <UIcon name="i-lucide-chevron-right" class="size-5" />
            </button>
          </template>
        </div>

        <figcaption class="mt-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-[#4F5D54] dark:text-slate-400 tabular-nums">
          <span>
            Dokumentasi lapangan Situs {{ site.code }} {{ site.name }}, Kabupaten Bojonegoro
            <template v-if="allPhotos.length > 1">
              (Foto {{ activePhotoIndex + 1 }} dari {{ allPhotos.length }})
            </template>
          </span>

          <!-- Thumbnail Filmstrip Selector -->
          <div
            v-if="allPhotos.length > 1"
            role="group"
            aria-label="Pilih foto dokumentasi"
            class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none"
          >
            <button
              v-for="(photo, idx) in allPhotos"
              :key="photo"
              type="button"
              :aria-label="`Tampilkan foto dokumentasi ${idx + 1}`"
              :aria-pressed="activePhotoIndex === idx"
              class="relative h-12 w-18 rounded-xl overflow-hidden shrink-0 cursor-pointer transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
              :class="
                activePhotoIndex === idx
                  ? 'ring-2 ring-blue-600 dark:ring-blue-400 opacity-100'
                  : 'opacity-60 hover:opacity-90'
              "
              @click="selectPhoto(idx)"
            >
              <img
                :src="photo"
                :alt="`Thumbnail ${idx + 1} ${site.name}`"
                class="w-full h-full object-cover"
                loading="lazy"
                @error="handlePhotoError(photo)"
              />
            </button>
          </div>
        </figcaption>
      </figure>

      <!-- Main Blog Body (8 Columns) + Sticky Spatial Dossier (4 Columns) -->
      <div class="mt-10 sm:mt-12 grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
        <!-- Longform Editorial Body -->
        <div class="lg:col-span-8 space-y-8">
          <div class="rounded-3xl bg-white dark:bg-[#0c1522] p-6 sm:p-10 lg:p-12">
            <div
              v-if="sanitizedNarasi"
              class="prose prose-slate dark:prose-invert max-w-[68ch] text-[#22312A] dark:text-slate-200 text-base sm:text-[17px] leading-[1.85] space-y-5 [&>p:first-of-type]:text-lg sm:[&>p:first-of-type]:text-[19px] [&>p:first-of-type]:font-medium [&>p:first-of-type]:text-[#17251F] dark:[&>p:first-of-type]:text-white [&>p:first-of-type]:leading-relaxed"
              v-html="sanitizedNarasi"
            />
            <p
              v-else
              class="text-base text-[#4F5D54] dark:text-slate-300 leading-relaxed"
            >
              {{ site.desc }}
            </p>
          </div>

          <!-- Pillar Thematic Context Callout -->
          <section
            v-if="site.diversityTitle || site.diversityInfo"
            aria-label="Konteks pilar Geopark"
            class="rounded-3xl bg-[#EAEFE9]/90 dark:bg-[#0c1522] p-6 sm:p-8 space-y-2"
          >
            <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
              Konteks {{ site.category }}
            </p>
            <h2
              v-if="site.diversityTitle"
              class="text-xl sm:text-2xl font-bold text-[#17251F] dark:text-slate-50"
            >
              {{ site.diversityTitle }}
            </h2>
            <p
              v-if="site.diversityInfo"
              class="text-sm sm:text-base text-[#4F5D54] dark:text-slate-300 leading-relaxed"
            >
              {{ site.diversityInfo }}
            </p>
          </section>

          <!-- Sequential Prev / Next Article Navigation -->
          <nav
            aria-label="Navigasi situs sebelumnya dan berikutnya"
            class="grid grid-cols-1 sm:grid-cols-2 gap-4"
          >
            <NuxtLink
              v-if="prevSite"
              :to="`/geopark/${prevSite.slug}`"
              class="group rounded-2xl bg-white dark:bg-[#0c1522] hover:bg-[#F0F5EE] dark:hover:bg-white/[0.04] p-5 flex flex-col justify-between gap-2 transition-colors min-h-[88px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
            >
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#4F5D54] dark:text-slate-400">
                <UIcon name="i-lucide-arrow-left" class="size-3.5" />
                <span>Situs Sebelumnya</span>
              </span>
              <span class="text-sm sm:text-base font-bold text-[#17251F] dark:text-slate-50 group-hover:text-blue-700 dark:group-hover:text-blue-400 tabular-nums">
                {{ prevSite.code }} · {{ prevSite.name }}
              </span>
            </NuxtLink>
            <div v-else class="hidden sm:block" />

            <NuxtLink
              v-if="nextSite"
              :to="`/geopark/${nextSite.slug}`"
              class="group rounded-2xl bg-white dark:bg-[#0c1522] hover:bg-[#F0F5EE] dark:hover:bg-white/[0.04] p-5 flex flex-col justify-between sm:items-end sm:text-right gap-2 transition-colors min-h-[88px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
            >
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#4F5D54] dark:text-slate-400">
                <span>Situs Berikutnya</span>
                <UIcon name="i-lucide-arrow-right" class="size-3.5" />
              </span>
              <span class="text-sm sm:text-base font-bold text-[#17251F] dark:text-slate-50 group-hover:text-blue-700 dark:group-hover:text-blue-400 tabular-nums">
                {{ nextSite.code }} · {{ nextSite.name }}
              </span>
            </NuxtLink>
          </nav>
        </div>

        <!-- Sticky Spatial Dossier Sidebar (4 Columns) -->
        <aside
          aria-label="Identitas dan lokasi spasial situs"
          class="lg:col-span-4 rounded-3xl bg-white dark:bg-[#0c1522] p-6 sm:p-7 lg:sticky lg:top-28 space-y-6"
        >
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
              Lembar Data Situs
            </p>
            <h2 class="mt-1 text-lg font-bold text-[#17251F] dark:text-slate-50">
              Identitas & Koordinat Spasial
            </h2>
          </div>

          <dl class="divide-y divide-[#ECEFEB] dark:divide-white/[0.06] text-xs sm:text-sm tabular-nums">
            <div class="py-3 flex items-center justify-between gap-4">
              <dt class="text-[#4F5D54] dark:text-slate-300">Kode Situs</dt>
              <dd class="font-bold text-[#17251F] dark:text-slate-50">{{ site.code }}</dd>
            </div>
            <div class="py-3 flex items-center justify-between gap-4">
              <dt class="text-[#4F5D54] dark:text-slate-300">Pilar Geopark</dt>
              <dd class="font-bold text-right" :class="getCategoryTextClass(site.categorySlug)">
                {{ site.category }}
              </dd>
            </div>
            <div class="py-3 flex items-center justify-between gap-4">
              <dt class="text-[#4F5D54] dark:text-slate-300">Urutan Direktori</dt>
              <dd class="font-bold text-[#17251F] dark:text-slate-50">
                Situs {{ currentIndex + 1 }} dari {{ sites.length }}
              </dd>
            </div>
            <div v-if="site.hasCoordinate" class="py-3 flex flex-col gap-1">
              <dt class="text-[#4F5D54] dark:text-slate-300">Koordinat WGS 84</dt>
              <dd class="font-bold text-[#17251F] dark:text-slate-50">
                {{ formatSiteCoord(site) }}
              </dd>
            </div>
          </dl>

          <div class="space-y-2.5">
            <button
              type="button"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-3 text-xs sm:text-sm font-semibold text-white transition-colors cursor-pointer min-h-[46px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
              @click="openSiteInMap(site)"
            >
              <UIcon name="i-lucide-map-pin" class="size-4" />
              <span>Buka Lokasi di Peta WebGIS</span>
            </button>

            <button
              v-if="site.hasCoordinate"
              type="button"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.05] hover:bg-[#EAEFE9] dark:hover:bg-white/[0.1] px-4 py-2.5 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-slate-200 transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
              @click="copyCoordinates"
            >
              <UIcon :name="isCoordCopied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
              <span>{{ isCoordCopied ? 'Koordinat Tersalin!' : 'Salin Koordinat WGS 84' }}</span>
            </button>
          </div>
        </aside>
      </div>

      <!-- Related Sites Section -->
      <section
        v-if="relatedSites.length > 0"
        aria-labelledby="related-geopark-heading"
        class="mt-14 sm:mt-16 pt-10 border-t border-[#E4EAE3] dark:border-white/[0.07]"
      >
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
              Eksplorasi Lanjutan
            </p>
            <h2
              id="related-geopark-heading"
              class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight text-[#17251F] dark:text-slate-50"
            >
              Situs Lain dalam {{ site.category }}
            </h2>
          </div>

          <NuxtLink
            to="/geopark"
            class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-slate-200 hover:text-blue-700 dark:hover:text-blue-400 min-h-[44px]"
          >
            <span>Lihat Semua {{ sites.length }} Situs</span>
            <UIcon name="i-lucide-arrow-right" class="size-4" />
          </NuxtLink>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <article
            v-for="item in relatedSites"
            :key="item.id"
            class="group rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-6 flex flex-col justify-between gap-4"
          >
            <div>
              <div class="flex items-center gap-1.5 text-xs tabular-nums">
                <span class="font-bold text-[#17251F] dark:text-slate-200">{{ item.code }}</span>
                <span class="text-[#7A877E]" aria-hidden="true">·</span>
                <span class="font-semibold" :class="getCategoryTextClass(item.categorySlug)">
                  {{ item.category }}
                </span>
              </div>

              <h3 class="mt-2 text-lg font-bold text-[#17251F] dark:text-slate-50 leading-snug">
                <NuxtLink
                  :to="`/geopark/${item.slug}`"
                  class="hover:text-blue-700 dark:hover:text-blue-400 transition-colors focus-visible:outline-none focus-visible:underline"
                >
                  {{ item.name }}
                </NuxtLink>
              </h3>

              <p class="mt-1.5 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 line-clamp-2 leading-relaxed">
                {{ item.desc }}
              </p>
            </div>

            <div class="pt-3 border-t border-[#ECEFEB] dark:border-white/[0.06] flex items-center justify-between gap-3 text-xs font-semibold">
              <NuxtLink
                :to="`/geopark/${item.slug}`"
                class="inline-flex items-center gap-1.5 text-[#17251F] dark:text-slate-100 hover:text-blue-700 dark:hover:text-blue-400 min-h-[44px]"
              >
                <span>Baca Artikel</span>
                <UIcon name="i-lucide-arrow-right" class="size-3.5" />
              </NuxtLink>

              <NuxtLink
                v-if="item.hasCoordinate"
                :to="`/maps?geosite=${encodeURIComponent(item.slug)}`"
                class="inline-flex items-center gap-1 text-blue-700 dark:text-blue-400 hover:underline min-h-[44px]"
              >
                <UIcon name="i-lucide-map-pin" class="size-3.5" />
                <span>Peta</span>
              </NuxtLink>
            </div>
          </article>
        </div>
      </section>
    </article>

    <!-- Not Found State (R-27) -->
    <div
      v-else
      class="mx-auto max-w-xl px-4 sm:px-6 pt-16 pb-24 text-center space-y-4"
    >
      <div class="rounded-3xl bg-white dark:bg-[#0c1522] p-8 sm:p-10 space-y-4">
        <h1 class="text-xl sm:text-2xl font-bold text-[#17251F] dark:text-slate-50">
          Situs Geopark Tidak Ditemukan
        </h1>
        <p class="text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 leading-relaxed">
          Situs dengan pengenal "{{ slug }}" tidak terdaftar dalam direktori 31 situs resmi Geopark Nasional Bojonegoro.
        </p>
        <NuxtLink
          to="/geopark"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white min-h-[44px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
        >
          <UIcon name="i-lucide-arrow-left" class="size-4" />
          <span>Kembali ke Katalog 31 Situs</span>
        </NuxtLink>
      </div>
    </div>
  </main>
</template>

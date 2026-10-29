<script setup lang="ts">
import { useGeoparkSites, type GeoparkCategoryFilter, type GeoparkSite } from "~/composables/useGeoparkSites";

useSeoMeta({
  title: "Geopark Bojonegoro | Warisan Geologi, Hayati & Budaya",
  description: "Direktori 31 situs resmi Geopark Nasional Bojonegoro mencakup Keragaman Geologi, Keragaman Hayati, dan Keragaman Budaya.",
});

const {
  sites,
  loading,
  geologicalSites,
  biologicalSites,
  culturalSites,
} = useGeoparkSites();

const searchQuery = ref("");
const selectedPillar = ref<GeoparkCategoryFilter>("ALL");

const filteredSites = computed(() => {
  let list = sites.value;

  if (selectedPillar.value === "geo-diversity") {
    list = geologicalSites.value;
  } else if (selectedPillar.value === "bio-diversity") {
    list = biologicalSites.value;
  } else if (selectedPillar.value === "culture-diversity") {
    list = culturalSites.value;
  }

  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter((s) => {
    const name = (s.name || s.title || "").toLowerCase();
    const desc = (s.desc || "").toLowerCase();
    const tag = (s.tag || "").toLowerCase();
    return name.includes(q) || desc.includes(q) || tag.includes(q);
  });
});

function getPillarBadgeClass(categorySlug?: string) {
  if (categorySlug === "geo-diversity") {
    return "bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200/60 dark:border-blue-800/40";
  }
  if (categorySlug === "bio-diversity") {
    return "bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-800/40";
  }
  return "bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200/60 dark:border-amber-800/40";
}

function handleOpenInMap(site: GeoparkSite) {
  if (site.hasCoordinate) {
    navigateTo(`/maps?geosite=${site.slug}`);
  } else {
    navigateTo(`/maps?search=${encodeURIComponent(site.name)}`);
  }
}
</script>

<template>
  <PublicPageTemplate
    title="Geopark Bojonegoro"
    description="Eksplorasi 31 situs resmi Geopark Nasional Bojonegoro: Warisan Geologi, Keragaman Hayati, dan Keragaman Budaya."
    icon="i-lucide-mountain-snow"
    container-size="default"
  >
    <template #actions>
      <UButton
        to="/maps?tab=geosite"
        icon="i-lucide-map"
        color="primary"
        variant="solid"
        size="sm"
        class="rounded-xl font-medium"
      >
        Eksplorasi Peta Geosite
      </UButton>
    </template>

    <div class="py-6 space-y-6">
      <!-- Bilah Pencarian & Filter 3 Pilar -->
      <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <!-- Pencarian -->
        <div class="relative flex-1 max-w-md">
          <UIcon
            name="i-lucide-search"
            class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari situs (contoh: Kayangan Api, Wonocolo)..."
            class="w-full bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/10 rounded-xl pl-10 pr-9 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all min-h-[44px]"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 size-6 rounded-full bg-slate-200 dark:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center justify-center hover:bg-slate-300 dark:hover:bg-white/20 transition-all"
            aria-label="Bersihkan pencarian"
            @click="searchQuery = ''"
          >
            <UIcon name="i-lucide-x" class="size-3.5" />
          </button>
        </div>

        <!-- Filter 3 Pilar -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
          <button
            type="button"
            class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[36px]"
            :class="selectedPillar === 'ALL'
              ? 'bg-blue-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
            @click="selectedPillar = 'ALL'"
          >
            Semua Situs ({{ sites.length }})
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[36px]"
            :class="selectedPillar === 'geo-diversity'
              ? 'bg-blue-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
            @click="selectedPillar = 'geo-diversity'"
          >
            Geologi ({{ geologicalSites.length }})
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[36px]"
            :class="selectedPillar === 'bio-diversity'
              ? 'bg-emerald-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
            @click="selectedPillar = 'bio-diversity'"
          >
            Hayati ({{ biologicalSites.length }})
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[36px]"
            :class="selectedPillar === 'culture-diversity'
              ? 'bg-amber-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
            @click="selectedPillar = 'culture-diversity'"
          >
            Budaya ({{ culturalSites.length }})
          </button>
        </div>
      </div>

      <!-- State: Memuat Data -->
      <div v-if="loading && sites.length === 0" class="py-16 text-center">
        <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-blue-600 dark:text-blue-400 mx-auto mb-3" />
        <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">
          Menghubungkan ke direktori Geopark Bojonegoro...
        </p>
      </div>

      <!-- State: Kosong -->
      <div
        v-else-if="filteredSites.length === 0"
        class="rounded-2xl border border-dashed border-slate-300 dark:border-white/10 p-10 text-center bg-slate-50/50 dark:bg-white/[0.02]"
      >
        <UIcon name="i-lucide-mountain-snow" class="size-10 text-slate-400 mx-auto mb-3" />
        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1">
          Situs Tidak Ditemukan
        </h3>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto mb-4">
          Tidak ditemukan situs geopark untuk pencarian "{{ searchQuery }}".
        </p>
        <UButton
          color="neutral"
          variant="soft"
          size="sm"
          class="rounded-xl font-medium"
          @click="searchQuery = ''; selectedPillar = 'ALL'"
        >
          Reset Filter Pencarian
        </UButton>
      </div>

      <!-- Grid Kartu Geosite -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <article
          v-for="site in filteredSites"
          :key="site.id"
          class="group rounded-2xl border border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0c1424] overflow-hidden flex flex-col justify-between hover:border-blue-300 dark:hover:border-blue-700/60 transition-all shadow-xs hover:shadow-lg"
        >
          <div>
            <!-- Banner Foto Situs -->
            <div class="relative h-48 w-full overflow-hidden bg-slate-100 dark:bg-slate-900">
              <img
                v-if="site.image"
                :src="site.image"
                :alt="site.name"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                loading="lazy"
                onerror="this.style.display='none'"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center text-slate-400 dark:text-slate-600"
              >
                <UIcon name="i-lucide-image" class="size-8" />
              </div>

              <!-- Badge Pilar Kategori -->
              <div class="absolute top-3 left-3">
                <span
                  class="text-[10px] font-bold tracking-tight px-2.5 py-1 rounded-full border backdrop-blur-md shadow-xs"
                  :class="getPillarBadgeClass(site.categorySlug)"
                >
                  {{ site.category }}
                </span>
              </div>
            </div>

            <!-- Konten Kartu -->
            <div class="p-4 sm:p-5 space-y-2">
              <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span class="font-mono font-semibold">{{ site.code }}</span>
                <span>·</span>
                <span v-if="site.hasCoordinate">Tersedia di Peta</span>
                <span v-else>Cagar Warisan</span>
              </div>

              <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors line-clamp-1">
                {{ site.name }}
              </h3>

              <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">
                {{ site.desc || "Situs geowisata dan warisan alam resmi Kabupaten Bojonegoro." }}
              </p>
            </div>
          </div>

          <!-- Aksi Navigasi Bawah -->
          <div class="px-4 sm:px-5 pb-4 sm:pb-5 pt-2 flex items-center justify-between gap-2 border-t border-slate-100 dark:border-white/5">
            <NuxtLink
              :to="`/geopark/${site.slug}`"
              class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline min-h-[36px]"
            >
              <span>Ulasan Lengkap</span>
              <UIcon name="i-lucide-arrow-right" class="size-3" />
            </NuxtLink>

            <UButton
              v-if="site.hasCoordinate"
              color="primary"
              variant="soft"
              size="xs"
              icon="i-lucide-map-pin"
              class="rounded-lg font-medium"
              @click="handleOpenInMap(site)"
            >
              Lihat di Peta
            </UButton>
          </div>
        </article>
      </div>
    </div>
  </PublicPageTemplate>
</template>

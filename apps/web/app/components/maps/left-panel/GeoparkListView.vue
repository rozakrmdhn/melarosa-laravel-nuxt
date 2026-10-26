<script setup lang="ts">
import { useGeoparkSites, type GeoparkSite } from "~/composables/useGeoparkSites";

const emit = defineEmits<{
  (e: "select-geosite", payload: { name: string; coordinate: [number, number]; zoom: number; site?: GeoparkSite }): void;
  (e: "go-to-map"): void;
  (e: "view-site-detail", site: GeoparkSite): void;
}>();

const { sites, loading, error, searchSites, fetchLiveSites, activePillarFilter: activePillar } = useGeoparkSites();

const filteredSites = computed(() => {
  if (activePillar.value === "ALL") {
    return sites.value;
  }
  return sites.value.filter((s) => s.categorySlug === activePillar.value);
});

function handleSiteCardClick(site: GeoparkSite) {
  emit("view-site-detail", site);
}

function handleZoomToSite(site: GeoparkSite) {
  if (site.hasCoordinate) {
    emit("select-geosite", {
      name: site.name,
      coordinate: site.coordinate,
      zoom: site.zoom,
      site,
    });
  } else {
    emit("view-site-detail", site);
  }
}
</script>

<template>
  <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-3.5 space-y-3.5 select-none">
    <!-- ═══ 1. FILTER PILAR (SEGMENTED PILL RADIUS 100%, BEBAS ANGKA BADGE) ═════════════════════ -->
    <div class="flex items-center gap-1 p-1 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 overflow-x-auto">
      <button
        type="button"
        class="flex-1 min-w-[70px] py-1.5 px-2 rounded-full text-xs font-semibold transition-all cursor-pointer min-h-[44px] select-none text-center"
        :class="activePillar === 'ALL'
          ? 'bg-white dark:bg-[#070b14] text-emerald-700 dark:text-emerald-300 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activePillar = 'ALL'"
      >
        <span>Semua</span>
      </button>

      <button
        type="button"
        class="flex-1 min-w-[70px] py-1.5 px-2 rounded-full text-xs font-semibold transition-all cursor-pointer min-h-[44px] select-none text-center"
        :class="activePillar === 'geo-diversity'
          ? 'bg-white dark:bg-[#070b14] text-blue-600 dark:text-blue-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activePillar = 'geo-diversity'"
      >
        <span>Geologi</span>
      </button>

      <button
        type="button"
        class="flex-1 min-w-[70px] py-1.5 px-2 rounded-full text-xs font-semibold transition-all cursor-pointer min-h-[44px] select-none text-center"
        :class="activePillar === 'bio-diversity'
          ? 'bg-white dark:bg-[#070b14] text-emerald-600 dark:text-emerald-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activePillar = 'bio-diversity'"
      >
        <span>Hayati</span>
      </button>

      <button
        type="button"
        class="flex-1 min-w-[70px] py-1.5 px-2 rounded-full text-xs font-semibold transition-all cursor-pointer min-h-[44px] select-none text-center"
        :class="activePillar === 'culture-diversity'
          ? 'bg-white dark:bg-[#070b14] text-amber-600 dark:text-amber-400 font-bold shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
        @click="activePillar = 'culture-diversity'"
      >
        <span>Budaya</span>
      </button>
    </div>

    <!-- ═══ 2. DAFTAR KARTU GEOSITE LENGKAP ═══════════════════════════════════ -->
    <div class="space-y-2.5">
      <div class="flex items-center justify-between px-1">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Daftar Geosite
        </h3>
        <div class="flex items-center gap-2">
          <!-- Tombol Segarkan Data Ringkas -->
          <button
            type="button"
            class="relative after:absolute after:-inset-2.5 after:content-[''] size-6 rounded-md flex items-center justify-center text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors cursor-pointer"
            :title="loading ? 'Memuat data geopark...' : 'Segarkan data dari geopark.bojonegorokab.go.id'"
            :disabled="loading"
            @click="fetchLiveSites(true)"
          >
            <UIcon
              name="i-lucide-refresh-cw"
              class="size-3.5"
              :class="loading ? 'animate-spin text-emerald-500' : ''"
            />
          </button>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400 tabular-nums">
            {{ filteredSites.length }} Situs
          </span>
        </div>
      </div>

      <!-- State Kosong jika filter/pencarian nihil -->
      <div
        v-if="filteredSites.length === 0"
        class="p-6 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] text-center space-y-2"
      >
        <UIcon name="i-lucide-search-x" class="size-8 text-slate-500 dark:text-slate-400 mx-auto" />
        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
          Tidak ada geosite yang cocok
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Coba kata kunci lain atau pilih pilar keragaman yang berbeda.
        </p>
      </div>

      <!-- List Kartu Geosite -->
      <div
        v-for="site in filteredSites"
        :key="site.id"
        class="geosite-card p-3 sm:p-3.5 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/80 dark:border-white/10 hover:border-emerald-400/80 dark:hover:border-emerald-600/60 shadow-2xs space-y-2.5 transition-all group cursor-pointer"
        @click="handleSiteCardClick(site)"
      >
        <div class="flex items-start gap-3">
          <!-- Thumbnail Gambar dengan Fallback Avatar Instan (Bebas Lag & Async Decoded) -->
          <div class="relative size-14 sm:size-16 rounded-xl overflow-hidden bg-slate-100 dark:bg-white/5 shrink-0 border border-slate-200/60 dark:border-white/10 flex items-center justify-center">
            <!-- Fallback Badge Avatar: Instan 0ms tanpa menunggu download jaringan -->
            <div
              class="absolute inset-0 flex items-center justify-center font-bold text-xs select-none"
              :class="site.categorySlug === 'geo-diversity'
                ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300'
                : site.categorySlug === 'bio-diversity'
                  ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                  : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'"
            >
              <span class="tabular-nums font-semibold">{{ site.code }}</span>
            </div>

            <!-- Foto Asli: Lazy-load + decoding async di background thread (tidak freeze UI) -->
            <img
              v-if="site.image"
              :src="site.image"
              :alt="site.name"
              loading="lazy"
              decoding="async"
              class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-all duration-300 opacity-0"
              @load="(e) => ((e.target as HTMLElement).classList.remove('opacity-0'))"
              @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
            />

            <!-- Pill Kode Mini Transparan di sudut foto saat gambar tampil -->
            <span
              v-if="site.image"
              class="absolute bottom-1 right-1 px-1 py-0.5 rounded text-[9px] font-bold tracking-tighter bg-black/60 text-white backdrop-blur-xs select-none pointer-events-none"
            >
              {{ site.code }}
            </span>
          </div>

          <!-- Informasi Situs -->
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span
                class="px-2 py-0.5 rounded-md text-xs font-bold tracking-tight"
                :class="site.categorySlug === 'geo-diversity'
                  ? 'bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300'
                  : site.categorySlug === 'bio-diversity'
                    ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300'
                    : 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300'"
              >
                {{ site.category }}
              </span>
            </div>

            <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 leading-tight group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
              {{ site.title }}
            </h4>

            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed font-normal">
              {{ site.desc }}
            </p>
          </div>
        </div>

        <!-- Tombol Aksi di Bawah Kartu -->
        <div
          class="pt-1 flex items-center justify-between border-t border-slate-100 dark:border-white/5"
          @click.stop
        >
          <span
            v-if="site.hasCoordinate"
            class="text-xs text-slate-500 dark:text-slate-400 tabular-nums font-medium"
          >
            {{ site.latitude.toFixed(4) }}, {{ site.longitude.toFixed(4) }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-medium"
          >
            <UIcon name="i-lucide-sparkles" class="size-3" />
            <span>Budaya Takbenda</span>
          </span>

          <div class="flex items-center gap-1.5">
            <button
              type="button"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 border border-slate-200/80 dark:border-white/10 transition-all cursor-pointer min-h-[44px] active:scale-95"
              :aria-label="`Buka detail informasi lengkap ${site.name}`"
              @click.stop="handleSiteCardClick(site)"
            >
              <UIcon name="i-lucide-info" class="size-3.5 text-slate-500 dark:text-slate-400 shrink-0" />
              <span>Detail</span>
            </button>

            <button
              v-if="site.hasCoordinate"
              type="button"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-900/50 transition-all cursor-pointer min-h-[44px] active:scale-95"
              :aria-label="`Arahkan peta ke ${site.name}`"
              @click.stop="handleZoomToSite(site)"
            >
              <UIcon name="i-lucide-map-pin" class="size-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
              <span>Peta</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.geosite-card {
  content-visibility: auto;
  contain-intrinsic-size: auto 130px;
}
</style>


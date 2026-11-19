<script lang="ts" setup>
const { navTree } = usePublicNavigation();
const currentYear = new Date().getFullYear();

const portalLinks = computed(() => {
  return (navTree.value || []).filter((item) => item?.label && item?.to);
});

const quickExploreLinks = [
  { label: "Peta Interaktif WebGIS", to: "/maps", icon: "i-lucide-map" },
  { label: "Peta 3D RDTR & Isometrik", to: "/maps/isometric", icon: "i-lucide-boxes" },
  { label: "Katalog Dataset JIGN", to: "/dataset", icon: "i-lucide-database" },
  { label: "Statistik Jalan Desa", to: "/statistik", icon: "i-lucide-bar-chart-3" },
];
</script>

<template>
  <footer
    aria-label="Footer Portal MELAROSA"
    class="w-full bg-[#F7F8F5] dark:bg-[#070b14] text-slate-700 dark:text-slate-300 border-t border-slate-200/80 dark:border-white/[0.08] font-sans"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
      <!-- Top Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-10 pb-8 sm:pb-10">
        <!-- 1. Brand & Instansi -->
        <div class="lg:col-span-5 space-y-4">
          <NuxtLink
            to="/"
            class="inline-flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2B5B9E] rounded-xl"
          >
            <img
              src="/logo-kabupaten.png"
              alt="Logo Kabupaten Bojonegoro"
              class="h-9 w-auto object-contain shrink-0 select-none"
            />
            <div class="min-w-0">
              <span class="block text-sm font-bold tracking-tight text-slate-900 dark:text-white group-hover:text-[#2B5B9E] dark:group-hover:text-sky-400 transition-colors">
                MELAROSA Bojonegoro
              </span>
              <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">
                BAPPEDA Kabupaten Bojonegoro
              </span>
            </div>
          </NuxtLink>

          <p class="text-xs sm:text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed max-w-md">
            Observatorium spasial pembangunan desa, jaringan jalan poros, dan katalog situs Geopark Nasional Kabupaten Bojonegoro dalam satu portal terbuka.
          </p>

          <div>
            <a
              href="https://geoportal.bojonegorokab.go.id"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] hover:border-[#2B5B9E]/40 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-[#2B5B9E] dark:hover:text-sky-400 transition-colors min-h-[40px]"
            >
              <UIcon name="i-lucide-globe" class="w-3.5 h-3.5 text-[#2B5B9E] dark:text-sky-400 shrink-0" />
              <span>Geoportal JIGN Bojonegoro</span>
              <UIcon name="i-lucide-arrow-up-right" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
            </a>
          </div>
        </div>

        <!-- 2. Navigasi Portal -->
        <div class="lg:col-span-3 space-y-3">
          <h3 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
            Navigasi Portal
          </h3>
          <ul class="space-y-1.5">
            <li v-for="item in portalLinks" :key="item.to || item.label">
              <NuxtLink
                :to="item.to"
                :target="item.target"
                class="inline-flex items-center gap-1.5 py-1 text-xs sm:text-[13px] font-medium text-slate-700 dark:text-slate-300 hover:text-[#2B5B9E] dark:hover:text-sky-400 transition-colors"
              >
                <span>{{ item.label }}</span>
              </NuxtLink>
            </li>
          </ul>
        </div>

        <!-- 3. Eksplorasi Cepat (Compact Square Grid) -->
        <div class="lg:col-span-4 space-y-2.5">
          <h3 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
            Eksplorasi Layanan Spasial
          </h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 gap-2 max-w-[196px] sm:max-w-[360px] lg:max-w-[196px]">
            <NuxtLink
              v-for="link in quickExploreLinks"
              :key="link.to"
              :to="link.to"
              class="aspect-square flex flex-col items-start justify-between p-2.5 rounded-lg bg-white/80 dark:bg-[#0c1522] border border-slate-200/70 dark:border-white/[0.06] hover:border-[#2B5B9E]/40 hover:bg-white dark:hover:bg-[#101c2e] text-[11px] font-semibold text-slate-700 dark:text-slate-200 hover:text-[#2B5B9E] dark:hover:text-sky-400 transition-colors"
            >
              <span class="w-6 h-6 rounded-md bg-[#2B5B9E]/10 dark:bg-sky-500/15 flex items-center justify-center shrink-0">
                <UIcon :name="link.icon" class="w-3.5 h-3.5 text-[#2B5B9E] dark:text-sky-400" />
              </span>
              <span class="leading-tight line-clamp-2">{{ link.label }}</span>
            </NuxtLink>
          </div>
        </div>
      </div>

      <!-- Bottom Attribution & Copyright -->
      <div class="pt-6 border-t border-slate-200/80 dark:border-white/[0.08] flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-slate-500 dark:text-slate-400">
        <p>
          © {{ currentYear }} MELAROSA · Pemerintah Kabupaten Bojonegoro
        </p>

        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span>
            Peta dasar © kontributor
            <a
              href="https://www.openstreetmap.org/copyright"
              target="_blank"
              rel="noopener noreferrer"
              class="underline hover:text-slate-800 dark:hover:text-slate-200"
            >
              OpenStreetMap
            </a>
          </span>
          <span class="text-slate-300 dark:text-slate-700">·</span>
          <span>Standar OGC WMS / WFS / CSW</span>
        </p>
      </div>
    </div>
  </footer>
</template>

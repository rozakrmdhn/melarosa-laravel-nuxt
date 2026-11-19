<script setup lang="ts">
usePublicSeo({
  title: "Sumber Data & Infrastruktur Spasial",
  description:
    "Penjelasan lengkap sumber data geospasial portal MELAROSA Bojonegoro: Simpul Jaringan Geoportal (JIGN), Martin Tile Server (MVT/PostGIS), Geopark Bojonegoro, dan layanan basemap.",
  path: "/sumber-data",
  jsonLd: {
    "@context": "https://schema.org",
    "@type": "WebPage",
    name: "Sumber Data & Infrastruktur Spasial MELAROSA Bojonegoro",
    url: "https://melarosa.saggaserv.my.id/sumber-data",
    inLanguage: "id-ID",
  },
});

const primarySources = [
  {
    id: "jign",
    badge: "OGC WMS · WFS · CSW",
    title: "Simpul Jaringan Geoportal (JIGN)",
    subtitle: "geoportal.bojonegorokab.go.id",
    icon: "i-lucide-globe",
    description:
      "Menyediakan layer peta tematik resmi lintas Perangkat Daerah (OPD) Kabupaten Bojonegoro beserta metadata katalog standar nasional.",
    highlights: [
      "Layanan peta raster OGC WMS & data vektor WFS",
      "Katalog metadata pyCSW (ISO 19115/19139 & Dublin Core)",
      "Penyelarasan skema atribut dengan KUGI v5 BIG",
    ],
    ctaLabel: "Lihat Katalog Dataset",
    ctaTo: "/dataset",
  },
  {
    id: "martin",
    badge: "Vector Tiles (MVT) · PostGIS",
    title: "Martin Tile Server",
    subtitle: "EPSG:3857 · Protobuf (.pbf)",
    icon: "i-lucide-layers",
    description:
      "Server tile vektor berkinerja tinggi yang menyajikan data langsung dari PostGIS untuk rendering cepat di OpenLayers dan MapLibre GL JS.",
    highlights: [
      "Jaringan jalan poros desa & segmen pembangunan fisik",
      "Batas administrasi kabupaten, 28 kecamatan & 430 desa",
      "Mendukung filter wilayah dan identify fitur secara instan",
    ],
    ctaLabel: "Buka Peta Interaktif",
    ctaTo: "/maps",
  },
  {
    id: "others",
    badge: "Geopark · Basemap · Citra",
    title: "Sumber Data Lainnya",
    subtitle: "Portal Geopark & Penyedia Basemap",
    icon: "i-lucide-compass",
    description:
      "Integrasi data situs Geopark Nasional Bojonegoro dan layanan peta dasar terpercaya untuk konteks topografi serta citra permukaan bumi.",
    highlights: [
      "31 Situs Geopark Nasional Bojonegoro (Geologi, Hayati, Budaya)",
      "Peta Rupabumi Indonesia (RBI) — Badan Informasi Geospasial",
      "Esri World Topo, Citra Satelit, OpenStreetMap & Foto Udara",
    ],
    ctaLabel: "Jelajahi Katalog Geopark",
    ctaTo: "/geopark",
  },
];

const standardsList = [
  {
    title: "Sistem Referensi Koordinat",
    value: "EPSG:4326 (WGS 84) & EPSG:3857",
    desc: "Pertukaran koordinat dan BBOX memakai standar WGS 84, sedangkan penyajian kanvas peta WebGIS memakai proyeksi Web Mercator EPSG:3857.",
    icon: "i-lucide-locate-fixed",
  },
  {
    title: "Standar Metadata & KUGI BIG",
    value: "SNI ISO 19115/19139 · KUGI v5",
    desc: "Setiap dataset di katalog JIGN diselaraskan dengan kamus unsur geospasial nasional (FCODE) dan skema Dublin Core untuk interoperabilitas Satu Data.",
    icon: "i-lucide-file-badge",
  },
];
</script>

<template>
  <div class="min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300">
    <main class="flex-1 pt-6 sm:pt-10 pb-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- 1. Editorial Masthead (Selaras dengan /dataset, /geopark, /statistik) -->
        <header class="pb-8 border-b border-slate-200/80 dark:border-white/[0.08]">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div class="inline-flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
              <span class="text-xs font-semibold uppercase tracking-[0.14em] text-[#2B5B9E] dark:text-sky-400">
                Arsitektur &amp; Transparansi Data · Kab. Bojonegoro
              </span>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-xs font-medium text-slate-700 dark:text-slate-300">
              <UIcon name="i-lucide-shield-check" class="w-3.5 h-3.5 text-[#2B5B9E] dark:text-sky-400" />
              <span>Standar OGC &amp; Satu Data</span>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8 items-end">
            <div class="lg:col-span-8">
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white tracking-tight leading-[1.15] mb-2.5">
                Sumber Data &amp; Infrastruktur Spasial
              </h1>
              <p class="text-sm sm:text-[15px] text-slate-600 dark:text-slate-400 leading-relaxed max-w-2xl">
                Penjelasan sumber data geospasial, server tile vektor, dan layanan peta dasar yang menggerakkan portal WebGIS MELAROSA Bojonegoro.
              </p>
            </div>

            <div class="lg:col-span-4 flex flex-wrap lg:justify-end items-center gap-2.5">
              <NuxtLink
                to="/dataset"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] hover:bg-slate-50 dark:hover:bg-[#132033] text-slate-800 dark:text-slate-200 text-xs font-semibold transition-colors"
              >
                <UIcon name="i-lucide-database" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400" />
                <span>Katalog Dataset</span>
              </NuxtLink>

              <NuxtLink
                to="/maps"
                class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-white text-xs font-semibold transition-colors shadow-xs"
              >
                <UIcon name="i-lucide-map" class="w-4 h-4" />
                <span>Buka Peta WebGIS</span>
              </NuxtLink>
            </div>
          </div>
        </header>

        <!-- 2. Tiga Pilar Sumber Data Utama -->
        <section aria-label="Daftar Sumber Data Utama" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <article
            v-for="src in primarySources"
            :key="src.id"
            class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-6 flex flex-col justify-between transition-all duration-200 hover:border-[#2B5B9E]/40 hover:shadow-md"
          >
            <div>
              <div class="flex items-center justify-between gap-3 mb-4">
                <span class="w-10 h-10 rounded-xl bg-[#2B5B9E]/10 dark:bg-sky-500/15 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 shrink-0">
                  <UIcon :name="src.icon" class="w-5 h-5" />
                </span>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-300">
                  {{ src.badge }}
                </span>
              </div>

              <h2 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">
                {{ src.title }}
              </h2>
              <p class="font-mono text-[11px] text-[#2B5B9E] dark:text-sky-400 mt-0.5 mb-3">
                {{ src.subtitle }}
              </p>
              <p class="text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed mb-5">
                {{ src.description }}
              </p>

              <ul class="space-y-2.5 mb-6">
                <li
                  v-for="item in src.highlights"
                  :key="item"
                  class="flex items-start gap-2 text-xs text-slate-700 dark:text-slate-300 leading-relaxed"
                >
                  <UIcon name="i-lucide-check-circle-2" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400 shrink-0 mt-0.5" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-white/[0.06]">
              <NuxtLink
                :to="src.ctaTo"
                class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-white/[0.06] hover:bg-[#2B5B9E] text-slate-800 dark:text-slate-200 hover:text-white text-xs font-semibold transition-colors"
              >
                <span>{{ src.ctaLabel }}</span>
                <UIcon name="i-lucide-arrow-right" class="w-4 h-4" />
              </NuxtLink>
            </div>
          </article>
        </section>

        <!-- 3. Standar Teknis & Proyeksi -->
        <section aria-label="Standar Teknis dan Proyeksi" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div
            v-for="std in standardsList"
            :key="std.title"
            class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-6 flex items-start gap-4"
          >
            <span class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-white/[0.06] flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 shrink-0">
              <UIcon :name="std.icon" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ std.title }}
              </p>
              <h3 class="text-base font-bold text-slate-900 dark:text-white mt-0.5 mb-1.5">
                {{ std.value }}
              </h3>
              <p class="text-xs sm:text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ std.desc }}
              </p>
            </div>
          </div>
        </section>

      </div>
    </main>
  </div>
</template>

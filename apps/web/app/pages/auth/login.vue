<script lang="ts" setup>
definePageMeta({
  middleware: ["guest"],
});

useSeoMeta({
  title: "Masuk Portal",
  description:
    "Portal masuk Melarosa GIS Kabupaten Bojonegoro untuk pengelolaan batas wilayah administrasi dan jaringan jalan poros desa.",
});

type PreviewKey = "rdtr" | "network";

interface MapSpecimen {
  key: PreviewKey;
  tabLabel: string;
  title: string;
  description: string;
  imageSrc: string;
  imageWidth: number;
  imageHeight: number;
  imageAlt: string;
  ctaLabel: string;
  ctaTo: string;
}

const mapSpecimens: MapSpecimen[] = [
  {
    key: "rdtr",
    tabLabel: "Peta Isometrik",
    title: "Peta Piksel Isometrik & Model 3D RDTR Bojonegoro",
    description:
      "Visualisasi bangunan, koridor jalan, Sungai Bengawan Solo, dan zona RTH skala detail 1:5.000.",
    imageSrc: "/tiles/bojonegoro-isometric/master_preview.png",
    imageWidth: 2048,
    imageHeight: 1024,
    imageAlt:
      "Pratinjau peta piksel isometrik Kota Bojonegoro dan Sungai Bengawan Solo",
    ctaLabel: "Buka Peta 3D",
    ctaTo: "/maps/isometric",
  },
  {
    key: "network",
    tabLabel: "Jaringan Jalan",
    title: "WebGIS Infrastruktur Jalan Poros & Batas Wilayah",
    description:
      "Penelusuran status ruas jalan penghubung antardesa, batas kecamatan, dan sebaran situs Geopark.",
    imageSrc: "/images/hero-bojonegoro-connectivity.jpg",
    imageWidth: 1376,
    imageHeight: 768,
    imageAlt:
      "Peta topografi jaringan konektivitas jalan dan aliran sungai Kabupaten Bojonegoro",
    ctaLabel: "Buka WebGIS 2D",
    ctaTo: "/maps",
  },
];

const activePreviewKey = ref<PreviewKey>("rdtr");

const activeSpecimen = computed<MapSpecimen>(
  () =>
    mapSpecimens.find((item) => item.key === activePreviewKey.value) ??
    mapSpecimens[0]!
);
</script>

<template>
  <div
    class="min-h-dvh w-full flex flex-col lg:grid lg:grid-cols-[minmax(0,46fr)_minmax(0,54fr)] bg-[#F7F8F5] dark:bg-[#070b14] text-[#17251F] dark:text-slate-100 selection:bg-[#176B4D] selection:text-white"
  >
    <!-- Panel Kiri: Ruang Masuk Portal Operator -->
    <section
      class="w-full flex flex-col justify-between bg-white dark:bg-[#0c1522] border-b lg:border-b-0 lg:border-r border-[#DFE5DE] dark:border-white/10"
    >
      <header
        class="px-5 sm:px-8 lg:px-12 pt-5 pb-4 flex items-center justify-between gap-3"
      >
        <NuxtLink
          to="/"
          class="inline-flex items-center gap-3 min-h-[44px] min-w-0 rounded-xl pr-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] group"
        >
          <img
            src="/logo-kabupaten.png"
            alt="Lambang Pemerintah Kabupaten Bojonegoro"
            width="403"
            height="599"
            class="h-10 w-auto shrink-0 object-contain"
          />
          <div class="min-w-0">
            <div
              class="text-sm sm:text-base font-bold tracking-tight text-[#17251F] dark:text-white leading-tight truncate"
            >
              Melarosa GIS
            </div>
            <div
              class="text-xs font-medium text-[#4E5D55] dark:text-[#CBD5E1] truncate"
            >
              Kabupaten Bojonegoro
            </div>
          </div>
        </NuxtLink>

        <div class="flex items-center gap-2 shrink-0">
          <NuxtLink
            to="/"
            class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-3.5 rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-white/5 hover:border-[#839188] dark:hover:border-white/30 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-slate-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
          >
            <UIcon
              name="i-lucide-arrow-left"
              class="size-4 shrink-0"
              aria-hidden="true"
            />
            <span>Beranda</span>
          </NuxtLink>

          <UColorModeButton
            aria-label="Ubah mode warna tampilan"
            class="min-h-[44px] min-w-[44px] rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-white/5 text-[#17251F] dark:text-slate-100 hover:border-[#839188] dark:hover:border-white/30 flex items-center justify-center cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
          />
        </div>
      </header>

      <main
        class="flex-1 flex flex-col justify-center px-5 sm:px-8 lg:px-12 py-5 sm:py-7"
      >
        <div class="w-full max-w-[420px] mx-auto">
          <div class="mb-5">
            <p
              class="text-xs sm:text-sm font-semibold text-[#176B4D] dark:text-[#34D399]"
            >
              Portal Pengelola Data Spasial
            </p>
            <h1
              class="mt-1 text-2xl sm:text-[1.875rem] font-bold tracking-tight text-[#17251F] dark:text-white leading-[1.18]"
            >
              Masuk ke Portal Melarosa
            </h1>
            <p
              class="mt-2 text-xs sm:text-sm text-[#4E5D55] dark:text-[#CBD5E1] leading-relaxed"
            >
              Gunakan akun perangkat daerah atau kecamatan untuk mengelola batas
              wilayah administrasi, ruas jalan poros desa, dan lapisan data
              Kabupaten Bojonegoro.
            </p>
          </div>

          <AuthLogin />
        </div>
      </main>

      <footer
        class="px-5 sm:px-8 lg:px-12 py-4 border-t border-[#DFE5DE] dark:border-white/10 text-xs text-[#4E5D55] dark:text-[#94A3B8]"
      >
        <span>© 2026 Pemerintah Kabupaten Bojonegoro</span>
      </footer>
    </section>

    <!-- Panel Kanan: Pratinjau Kartografis Bojonegoro & Akses Peta Publik -->
    <aside
      aria-label="Pratinjau Peta Bojonegoro"
      class="relative w-full flex flex-col justify-between px-5 sm:px-8 lg:px-12 xl:px-14 py-6 sm:py-8 lg:py-8 overflow-hidden bg-[#F7F8F5] dark:bg-[#070b14]"
    >
      <!-- Grid kartografis dua tingkat (96px & 24px) selaras dengan identitas lembar peta Melarosa -->
      <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 dark:hidden"
        style="
          background-image:
            linear-gradient(to right, rgba(23, 107, 77, 0.11) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(23, 107, 77, 0.11) 1px, transparent 1px),
            linear-gradient(to right, rgba(23, 37, 31, 0.045) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(23, 37, 31, 0.045) 1px, transparent 1px);
          background-size: 96px 96px, 96px 96px, 24px 24px, 24px 24px;
        "
      />
      <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 hidden dark:block"
        style="
          background-image:
            linear-gradient(to right, rgba(52, 211, 153, 0.09) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(52, 211, 153, 0.09) 1px, transparent 1px),
            linear-gradient(to right, rgba(148, 163, 184, 0.04) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(148, 163, 184, 0.04) 1px, transparent 1px);
          background-size: 96px 96px, 96px 96px, 24px 24px, 24px 24px;
        "
      />

      <div
        class="relative z-10 w-full max-w-2xl mx-auto my-auto flex flex-col gap-5"
      >
        <div
          class="flex flex-col sm:flex-row sm:items-end justify-between gap-3"
        >
          <div class="min-w-0">
            <p
              class="text-xs font-semibold text-[#176B4D] dark:text-[#34D399]"
            >
              Akses Terbuka Publik
            </p>
            <h2
              class="mt-1 text-xl sm:text-2xl font-bold tracking-tight text-[#17251F] dark:text-white leading-snug"
            >
              Jelajahi Peta Bojonegoro
            </h2>
          </div>

          <div
            role="tablist"
            aria-label="Pilih pratinjau peta Bojonegoro"
            class="inline-flex p-1 rounded-xl border border-[#B8C4BC] dark:border-white/15 bg-white dark:bg-[#0c1522] self-start sm:self-auto shrink-0"
          >
            <button
              v-for="specimen in mapSpecimens"
              :key="specimen.key"
              type="button"
              role="tab"
              :aria-selected="activePreviewKey === specimen.key"
              :class="
                activePreviewKey === specimen.key
                  ? 'bg-[#17251F] text-white dark:bg-[#F8FAFC] dark:text-[#0c1522]'
                  : 'text-[#4E5D55] dark:text-[#CBD5E1] hover:text-[#17251F] dark:hover:text-white'
              "
              class="min-h-[44px] px-3.5 py-2 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
              @click="activePreviewKey = specimen.key"
            >
              {{ specimen.tabLabel }}
            </button>
          </div>
        </div>

        <article
          role="tabpanel"
          class="rounded-2xl overflow-hidden border border-[#B8C4BC] dark:border-white/15 bg-white dark:bg-[#0c1522] shadow-sm"
        >
          <div class="relative w-full aspect-[16/9] bg-[#E8EDE6] dark:bg-[#08101c] overflow-hidden">
            <img
              :src="activeSpecimen.imageSrc"
              :alt="activeSpecimen.imageAlt"
              :width="activeSpecimen.imageWidth"
              :height="activeSpecimen.imageHeight"
              fetchpriority="high"
              class="w-full h-full object-cover transition-transform duration-300 motion-reduce:transition-none"
            />
          </div>

          <div
            class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-t border-[#DFE5DE] dark:border-white/10"
          >
            <div class="min-w-0 space-y-1">
              <h3
                class="text-sm sm:text-base font-bold text-[#17251F] dark:text-white"
              >
                {{ activeSpecimen.title }}
              </h3>
              <p
                class="text-xs sm:text-sm text-[#4E5D55] dark:text-[#CBD5E1] leading-relaxed"
              >
                {{ activeSpecimen.description }}
              </p>
            </div>

            <NuxtLink
              :to="activeSpecimen.ctaTo"
              class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2.5 rounded-xl border border-[#839188] dark:border-[#64748B] bg-[#F7F8F5] dark:bg-white/5 hover:border-[#176B4D] dark:hover:border-[#34D399] text-xs sm:text-sm font-semibold text-[#17251F] dark:text-white transition-colors shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
            >
              <UIcon
                name="i-lucide-map"
                class="size-4 text-[#176B4D] dark:text-[#34D399] shrink-0"
                aria-hidden="true"
              />
              <span>{{ activeSpecimen.ctaLabel }}</span>
            </NuxtLink>
          </div>
        </article>

        <dl
          class="rounded-2xl border border-[#DFE5DE] dark:border-white/10 bg-white dark:bg-[#0c1522] divide-y divide-[#DFE5DE] dark:divide-white/10"
        >
          <div
            class="px-4 sm:px-5 py-3.5 flex flex-col sm:flex-row sm:items-baseline justify-between gap-1 sm:gap-4"
          >
            <dt class="text-xs sm:text-sm font-semibold text-[#17251F] dark:text-white">
              Cakupan Administrasi
            </dt>
            <dd class="text-xs sm:text-sm text-[#4E5D55] dark:text-[#CBD5E1]">
              28 kecamatan dan 430 desa serta kelurahan di Kabupaten Bojonegoro.
            </dd>
          </div>

          <div
            class="px-4 sm:px-5 py-3.5 flex flex-col sm:flex-row sm:items-baseline justify-between gap-1 sm:gap-4"
          >
            <dt class="text-xs sm:text-sm font-semibold text-[#17251F] dark:text-white">
              Dataset Jalan & Geopark
            </dt>
            <dd class="text-xs sm:text-sm text-[#4E5D55] dark:text-[#CBD5E1]">
              1.187 ruas jalan poros desa serta 31 situs Geopark Nasional terdata.
            </dd>
          </div>
        </dl>
      </div>
    </aside>
  </div>
</template>

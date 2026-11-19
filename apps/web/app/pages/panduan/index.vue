<script setup lang="ts">
usePublicSeo({
  title: "Panduan Penggunaan Portal MELAROSA",
  description:
    "Panduan lengkap penggunaan WebGIS MELAROSA Bojonegoro: pencarian wilayah dan koordinat, pengelolaan layer MVT/WMS, inspeksi fitur peta, katalog dataset JIGN, Geopark, serta statistik jalan.",
  path: "/panduan",
  jsonLd: {
    "@context": "https://schema.org",
    "@type": "WebPage",
    name: "Panduan Penggunaan MELAROSA Bojonegoro",
    url: "https://melarosa.saggaserv.my.id/panduan",
    inLanguage: "id-ID",
  },
});

const quickSteps = [
  {
    step: "01",
    title: "Cari Wilayah atau Koordinat",
    desc: "Ketik nama kecamatan, desa, ruas jalan, situs Geopark, atau tempel koordinat Desimal/DMS pada kolom pencarian di navigasi maupun panel kiri peta.",
    icon: "i-lucide-search",
    to: "/maps",
  },
  {
    step: "02",
    title: "Atur & Urutkan Layer Peta",
    desc: "Klik ikon Layers di kanvas peta atau tab Jelajah untuk mengaktifkan layer MVT/WMS, mengatur opasitas, dan menggeser urutan tumpukan layer.",
    icon: "i-lucide-layers",
    to: "/maps",
  },
  {
    step: "03",
    title: "Klik Peta untuk Info Fitur",
    desc: "Ketuk poligon desa, kecamatan, atau garis ruas jalan pada peta untuk melihat atribut detail dan highlight batas wilayah secara presisi.",
    icon: "i-lucide-mouse-pointer-click",
    to: "/maps",
  },
  {
    step: "04",
    title: "Unduh Metadata & Statistik",
    desc: "Akses katalog metadata OGC/ISO 19139 di halaman Dataset atau unduh rekapitulasi pembangunan jalan per kecamatan di halaman Statistik.",
    icon: "i-lucide-file-spreadsheet",
    to: "/statistik",
  },
];

const featureGuides = [
  {
    badge: "Peta WebGIS",
    title: "Navigasi Peta & Filter Wilayah",
    icon: "i-lucide-map",
    items: [
      "Pilih kecamatan atau desa dari daftar cepat di panel kiri untuk memfokuskan peta dan menyaring layer MVT secara otomatis.",
      "Gunakan tombol Basemap di sudut kiri bawah untuk beralih antara Esri World Topo, Rupabumi Indonesia (BIG), OpenStreetMap, Citra Satelit, dan Foto Udara.",
      "Klik tombol Reset Filter atau ikon Pusatkan Kabupaten untuk mengembalikan tampilan ke seluruh wilayah Kabupaten Bojonegoro.",
    ],
    ctaLabel: "Buka Peta Interaktif",
    ctaTo: "/maps",
  },
  {
    badge: "Katalog Dataset",
    title: "Inspeksi Metadata & Minimap",
    icon: "i-lucide-database",
    items: [
      "Gunakan filter Wali Data OPD dan kategori tematik untuk menemukan dataset geospasial resmi dari Simpul JIGN Bojonegoro.",
      "Klik Lihat Metadata untuk memeriksa abstrak, URL layanan OGC (WMS/WFS/CSW), XML ISO 19139, BBOX, dan skema atribut KUGI BIG.",
      "Klik ikon peta pada kartu untuk pratinjau cepat melalui Minimap MapLibre atau buka langsung layer tersebut ke dalam WebGIS.",
    ],
    ctaLabel: "Jelajahi Dataset",
    ctaTo: "/dataset",
  },
  {
    badge: "Geopark & 3D RDTR",
    title: "Eksplorasi Wisata & Peta 3D",
    icon: "i-lucide-mountain-snow",
    items: [
      "Telusuri 31 situs Geopark Nasional Bojonegoro berdasarkan tiga pilar: Keragaman Geologi, Keragaman Hayati, dan Keragaman Budaya.",
      "Buka halaman detail situs untuk melihat pratinjau lokasi di Minimap interaktif maupun langsung melompat ke koordinatnya di WebGIS.",
      "Gunakan halaman Peta 3D RDTR & Isometrik untuk visualisasi bangunan 3D kawasan perkotaan Bojonegoro.",
    ],
    ctaLabel: "Lihat Katalog Geopark",
    ctaTo: "/geopark",
  },
  {
    badge: "Statistik Jalan",
    title: "Rekapitulasi & Ekspor Laporan",
    icon: "i-lucide-bar-chart-3",
    items: [
      "Bandingkan capaian panjang jalan terbangun dan sisa belum terbangun antar 28 kecamatan melalui grafik interaktif.",
      "Beralih antara kategori Ruas Jalan Poros Desa dan Jalan Desa Non-Ruas pada tab di bagian atas halaman.",
      "Telusuri data secara berjenjang dari tingkat kecamatan, desa, ruas jalan, hingga segmen fisik serta unduh laporan dalam format Excel (.xlsx) atau CSV.",
    ],
    ctaLabel: "Buka Statistik Jalan",
    ctaTo: "/statistik",
  },
];

const coordinateExamples = [
  { label: "Desimal (Lat, Lng)", value: "-7.1502, 111.8817" },
  { label: "DMS Internasional", value: "7°09'00.7\"S 111°52'54.1\"E" },
  { label: "DMS Indonesia", value: "7°09'00.7\"LS 111°52'54.1\"BT" },
];
</script>

<template>
  <div class="min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300">
    <main class="flex-1 pt-6 sm:pt-10 pb-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- 1. Editorial Masthead (Selaras dengan /dataset, /geopark, /statistik, /sumber-data) -->
        <header class="pb-8 border-b border-slate-200/80 dark:border-white/[0.08]">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div class="inline-flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
              <span class="text-xs font-semibold uppercase tracking-[0.14em] text-[#2B5B9E] dark:text-sky-400">
                Pusat Bantuan · Portal MELAROSA
              </span>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-xs font-medium text-slate-700 dark:text-slate-300">
              <UIcon name="i-lucide-book-open" class="w-3.5 h-3.5 text-[#2B5B9E] dark:text-sky-400" />
              <span>Panduan Pengguna</span>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8 items-end">
            <div class="lg:col-span-8">
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white tracking-tight leading-[1.15] mb-2.5">
                Panduan Penggunaan MELAROSA
              </h1>
              <p class="text-sm sm:text-[15px] text-slate-600 dark:text-slate-400 leading-relaxed max-w-2xl">
                Petunjuk ringkas untuk mencari wilayah, mengelola layer peta, memeriksa atribut fitur, dan mengakses data spasial Kabupaten Bojonegoro.
              </p>
            </div>

            <div class="lg:col-span-4 flex flex-wrap lg:justify-end items-center gap-2.5">
              <NuxtLink
                to="/sumber-data"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] hover:bg-slate-50 dark:hover:bg-[#132033] text-slate-800 dark:text-slate-200 text-xs font-semibold transition-colors"
              >
                <UIcon name="i-lucide-server" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400" />
                <span>Sumber Data</span>
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

        <!-- 2. Empat Langkah Cepat -->
        <section aria-label="Langkah Cepat Penggunaan">
          <div class="mb-5">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
              Mulai Cepat dalam 4 Langkah
            </h2>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
              Alur dasar eksplorasi peta dan data spasial di portal MELAROSA.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <NuxtLink
              v-for="item in quickSteps"
              :key="item.step"
              :to="item.to"
              class="group rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 flex flex-col justify-between transition-all duration-200 hover:border-[#2B5B9E]/40 hover:shadow-md"
            >
              <div>
                <div class="flex items-center justify-between gap-3 mb-4">
                  <span class="w-9 h-9 rounded-xl bg-[#2B5B9E]/10 dark:bg-sky-500/15 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400">
                    <UIcon :name="item.icon" class="w-4 h-4" />
                  </span>
                  <span class="font-mono text-xs font-bold text-slate-400 dark:text-slate-500 tabular-nums">
                    {{ item.step }}
                  </span>
                </div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white group-hover:text-[#2B5B9E] dark:group-hover:text-sky-400 transition-colors mb-1.5">
                  {{ item.title }}
                </h3>
                <p class="text-xs sm:text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed">
                  {{ item.desc }}
                </p>
              </div>
            </NuxtLink>
          </div>
        </section>

        <!-- 3. Panduan Fitur Utama -->
        <section aria-label="Panduan Fitur Utama" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <article
            v-for="guide in featureGuides"
            :key="guide.title"
            class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-6 flex flex-col justify-between"
          >
            <div>
              <div class="flex items-center justify-between gap-3 mb-4">
                <div class="inline-flex items-center gap-2.5">
                  <span class="w-9 h-9 rounded-xl bg-[#2B5B9E]/10 dark:bg-sky-500/15 flex items-center justify-center text-[#2B5B9E] dark:text-sky-400 shrink-0">
                    <UIcon :name="guide.icon" class="w-4 h-4" />
                  </span>
                  <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ guide.title }}
                  </h2>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-300 shrink-0">
                  {{ guide.badge }}
                </span>
              </div>

              <ul class="space-y-2.5 mb-6">
                <li
                  v-for="point in guide.items"
                  :key="point"
                  class="flex items-start gap-2.5 text-xs sm:text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed"
                >
                  <UIcon name="i-lucide-check-circle-2" class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400 shrink-0 mt-0.5" />
                  <span>{{ point }}</span>
                </li>
              </ul>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-white/[0.06]">
              <NuxtLink
                :to="guide.ctaTo"
                class="inline-flex items-center gap-2 text-xs font-semibold text-[#2B5B9E] dark:text-sky-400 hover:underline min-h-[36px]"
              >
                <span>{{ guide.ctaLabel }}</span>
                <UIcon name="i-lucide-arrow-right" class="w-3.5 h-3.5" />
              </NuxtLink>
            </div>
          </article>
        </section>

        <!-- 4. Format Pencarian Koordinat yang Didukung -->
        <section
          aria-label="Format Pencarian Koordinat"
          class="rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-6"
        >
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
              <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tracking-tight">
                Format Pencarian Koordinat yang Didukung
              </h2>
              <p class="text-xs sm:text-[13px] text-slate-600 dark:text-slate-400 mt-1">
                Masukkan salah satu format berikut pada kotak pencarian peta untuk langsung menuju titik koordinat.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            <div
              v-for="ex in coordinateExamples"
              :key="ex.label"
              class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] border border-slate-200/60 dark:border-white/[0.06]"
            >
              <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                {{ ex.label }}
              </p>
              <p class="font-mono text-xs sm:text-[13px] font-semibold text-slate-900 dark:text-white tabular-nums">
                {{ ex.value }}
              </p>
            </div>
          </div>
        </section>

      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
// Kondisi jalan dengan simbol dan teks jelas (bukan hanya warna, sesuai antislop-human)
const roadConditions = [
  {
    status: 'Baik',
    symbol: '●',
    colorClass: 'text-emerald-400 bg-emerald-950/80 border-emerald-500/40',
    dotClass: 'bg-emerald-400',
    desc: 'Permukaan rata, nyaman dilalui kendaraan warga dan distribusi logistik.',
  },
  {
    status: 'Sedang',
    symbol: '▲',
    colorClass: 'text-amber-300 bg-amber-950/80 border-amber-500/40',
    dotClass: 'bg-amber-400',
    desc: 'Terdapat retak rambut atau aus ringan, masuk jadwal pemeliharaan berkala.',
  },
  {
    status: 'Rusak',
    symbol: '■',
    colorClass: 'text-rose-400 bg-rose-950/80 border-rose-500/40',
    dotClass: 'bg-rose-500',
    desc: 'Kerusakan struktural atau lubang, menjadi prioritas usulan perbaikan desa.',
  },
];

const roadSurfaces = [
  {
    type: 'Cor Beton & Aspal',
    role: 'Jalur Poros Utama Antarkecamatan',
    note: 'Struktur perkerasan kaku dan lentur untuk mobilitas tinggi warga.',
  },
  {
    type: 'Paving Stone & Makadam',
    role: 'Penghubung Antardusun & Permukiman',
    note: 'Akses permukiman padat dan jalan lingkungan desa.',
  },
  {
    type: 'Tanah & Agregat',
    role: 'Jalur Akses Pertanian & Hutan',
    note: 'Ruas jalan yang diusulkan untuk peningkatan struktur perkerasan.',
  },
];
</script>

<template>
  <section
    aria-labelledby="infra-heading"
    class="relative w-full bg-[#102B22] text-white py-16 sm:py-20 lg:py-24 overflow-hidden"
  >
    <!-- Background topographical contour accent -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 opacity-10"
      style="background-image: radial-gradient(#34d399 0.75px, transparent 0.75px); background-size: 32px 32px;"
    />

    <div class="relative z-10 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-start">
        <!-- Bagian Utama: Narasi & Peta Jaringan Jalan (7 Kolom lg) -->
        <div class="lg:col-span-7 space-y-6">
          <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-400">
            <span class="size-2 rounded-full bg-emerald-400" />
            <span>Konektivitas Wilayah Bojonegoro</span>
          </div>

          <h2
            id="infra-heading"
            class="text-2xl sm:text-3xl lg:text-4xl font-bold leading-tight tracking-tight text-white"
          >
            Jalan yang menghubungkan desa-desa Bojonegoro.
          </h2>

          <div class="space-y-4 text-base sm:text-lg leading-relaxed text-emerald-100/90 max-w-2xl">
            <p>
              Di Kabupaten Bojonegoro, jalan poros desa bukan sekadar perkerasan aspal atau cor beton. Ruas-ruas inilah yang menghubungkan para petani padi dengan pasar kecamatan, mengantar anak-anak ke sekolah, serta mempercepat akses layanan kesehatan masyarakat.
            </p>
            <p>
              Melalui basis data spasial MELAROSA, pemerintah daerah memantau secara berkala kondisi fisik dari <strong class="text-white font-semibold tabular-nums">1.187 ruas jalan poros</strong> dengan total panjang jaringan mencapai <strong class="text-white font-semibold tabular-nums">1.424,8 kilometer</strong> yang tersebar di 28 kecamatan.
            </p>
          </div>

          <!-- Visual Sketsa Jaringan Jalan Poros Sebenarnya -->
          <div class="rounded-2xl border border-emerald-800/60 bg-[#091a14] p-5 space-y-4">
            <div class="flex items-center justify-between text-xs text-emerald-300/80 font-mono">
              <span>SKETSA KORIDOR JALAN POROS KABUPATEN</span>
              <span>1.187 RUAS TERDATA</span>
            </div>

            <!-- SVG Visualisasi Garis Jaringan Jalan -->
            <svg
              viewBox="0 0 540 180"
              class="w-full h-40 sm:h-44 rounded-xl bg-[#06120d] border border-emerald-900/50"
              role="img"
              aria-label="Visualisasi struktur garis jalan poros Bojonegoro membentang dari barat hingga timur"
            >
              <!-- Bengawan Solo Waterway -->
              <path
                d="M 20,135 C 120,130 180,95 270,75 S 440,55 520,30"
                fill="none"
                stroke="#1d4ed8"
                stroke-width="3"
                stroke-linecap="round"
                opacity="0.6"
              />
              <text x="360" y="48" fill="#60a5fa" font-size="10" font-family="monospace">Bengawan Solo</text>

              <!-- Koridor Jalan Utama Padangan - Bojonegoro - Baureno -->
              <path
                d="M 35,115 Q 160,105 270,78 T 505,45"
                fill="none"
                stroke="#34d399"
                stroke-width="3"
                stroke-linecap="round"
              />

              <!-- Jalur Poros Cabang Utara - Selatan -->
              <path
                d="M 120,35 L 160,105 L 140,165"
                fill="none"
                stroke="#10b981"
                stroke-width="2"
                stroke-linecap="round"
              />
              <path
                d="M 270,25 L 270,78 L 290,165"
                fill="none"
                stroke="#34d399"
                stroke-width="2.5"
                stroke-linecap="round"
              />
              <path
                d="M 410,40 L 410,95 L 435,160"
                fill="none"
                stroke="#10b981"
                stroke-width="2"
                stroke-linecap="round"
              />

              <!-- Segmen Perlu Pemeliharaan & Prioritas (Kuning & Merah) -->
              <path
                d="M 270,78 L 340,120 L 370,155"
                fill="none"
                stroke="#fbbf24"
                stroke-width="2"
                stroke-dasharray="4 3"
                stroke-linecap="round"
              />
              <path
                d="M 160,105 L 220,140"
                fill="none"
                stroke="#f87171"
                stroke-width="2"
                stroke-dasharray="4 3"
                stroke-linecap="round"
              />

              <!-- Simpul Kecamatan Utama -->
              <g fill="#ffffff">
                <circle cx="65" cy="110" r="4" />
                <circle cx="160" cy="105" r="4" />
                <circle cx="270" cy="78" r="6" fill="#34d399" stroke="#ffffff" stroke-width="1.5" />
                <circle cx="410" cy="60" r="4" />
                <circle cx="490" cy="48" r="4" />
              </g>

              <text x="260" y="68" fill="#ffffff" font-size="11" font-weight="bold">Kota Bojonegoro</text>
              <text x="45" y="125" fill="#a7f3d0" font-size="9">Padangan</text>
              <text x="480" y="38" fill="#a7f3d0" font-size="9">Baureno</text>
            </svg>

            <!-- Aksi Utama Section Infrastruktur: Biru Peta #2563EB -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
              <span class="text-xs text-emerald-200/80">
                Peta jalan terintegrasi dengan data usulan BKKD
              </span>
              <NuxtLink
                to="/maps"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-600/30 transition-all min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
              >
                <span>Lihat Jaringan Jalan</span>
                <UIcon name="i-lucide-arrow-right" class="size-4" />
              </NuxtLink>
            </div>
          </div>
        </div>

        <!-- Bagian Pendamping: Legenda Kondisi & Jenis Permukaan (5 Kolom lg) -->
        <div class="lg:col-span-5 space-y-6">
          <!-- Panel Legenda Kondisi Jalan Resmi -->
          <div class="rounded-2xl border border-emerald-800/60 bg-[#0c231b] p-6 shadow-md">
            <h3 class="text-base font-bold text-white flex items-center justify-between">
              <span>Legenda Kondisi Jalan</span>
              <span class="text-xs font-normal text-emerald-300/80 font-mono">Standar Bina Marga</span>
            </h3>

            <p class="mt-1 text-xs text-emerald-200/70">
              Klasifikasi fisik jalan dengan penanda teks dan simbol visual:
            </p>

            <ul class="mt-4 space-y-3">
              <li
                v-for="cond in roadConditions"
                :key="cond.status"
                class="rounded-xl border border-emerald-800/40 bg-[#081813] p-3.5"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2.5">
                    <span class="size-3 rounded-full shrink-0" :class="cond.dotClass" />
                    <span class="text-sm font-bold text-white">Kondisi {{ cond.status }}</span>
                  </div>
                  <span class="text-xs font-mono font-bold px-2 py-0.5 rounded border" :class="cond.colorClass">
                    {{ cond.symbol }} {{ cond.status }}
                  </span>
                </div>
                <p class="mt-1.5 text-xs text-emerald-100/75 leading-relaxed">
                  {{ cond.desc }}
                </p>
              </li>
            </ul>
          </div>

          <!-- Panel Jenis Permukaan Jalan -->
          <div class="rounded-2xl border border-emerald-800/60 bg-[#0c231b] p-6 shadow-md">
            <h3 class="text-base font-bold text-white">
              Tipe Permukaan Jalan Poros
            </h3>
            <p class="mt-1 text-xs text-emerald-200/70">
              Karakter perkerasan jalan poros desa di Kabupaten Bojonegoro:
            </p>

            <dl class="mt-4 space-y-3 divide-y divide-emerald-800/40">
              <div
                v-for="surf in roadSurfaces"
                :key="surf.type"
                class="pt-3 first:pt-0"
              >
                <dt class="flex items-center justify-between text-xs">
                  <span class="font-bold text-white">{{ surf.type }}</span>
                  <span class="text-emerald-300 font-medium">{{ surf.role }}</span>
                </dt>
                <dd class="mt-1 text-xs text-emerald-100/70">
                  {{ surf.note }}
                </dd>
              </div>
            </dl>

            <div class="mt-5 pt-4 border-t border-emerald-800/60 flex items-center justify-between">
              <NuxtLink
                to="/statistik"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-300 hover:text-white transition-colors"
              >
                <UIcon name="i-lucide-bar-chart-2" class="size-4" />
                <span>Buka Rekap Panjang Jalan Lengkap</span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';

interface GuideStep {
  step: string;
  title: string;
  desc: string;
  actionNote: string;
}

const activeStepIndex = ref(0);

const steps: GuideStep[] = [
  {
    step: '01',
    title: 'Temukan lokasi',
    desc: 'Cari nama desa, kecamatan, atau ruas jalan melalui bilah pencarian cerdas di bagian atas halaman atau peta.',
    actionNote: 'Pencarian mendukung nama wilayah, ruas jalan, dan titik koordinat WGS 84.',
  },
  {
    step: '02',
    title: 'Pilih informasi',
    desc: 'Buka panel lapisan data untuk menyalakan informasi yang Anda butuhkan, seperti jalan poros, batas desa, atau situs Geopark.',
    actionNote: 'Lapisan data resmi bersumber langsung dari dinas dan instansi terkait.',
  },
  {
    step: '03',
    title: 'Buka detail objek',
    desc: 'Klik garis jalan atau titik lokasi di peta untuk membaca informasi spesifik: panjang jalan, kondisi fisik, dan riwayat penanganan.',
    actionNote: 'Informasi disajikan dalam bahasa yang mudah dimengerti warga.',
  },
  {
    step: '04',
    title: 'Ubah tampilan peta',
    desc: 'Ganti peta dasar sesuai kenyamanan Anda, mulai dari peta vektor jalan standar, citra satelit resolusi tinggi, hingga peta Rupabumi Indonesia (RBI).',
    actionNote: 'Dapat digunakan dengan nyaman di layar ponsel maupun komputer.',
  },
];
</script>

<template>
  <section
    id="panduan-peta"
    aria-labelledby="guide-heading"
    class="relative w-full bg-white dark:bg-[#070b14] py-16 sm:py-20 lg:py-24 border-b border-[#DFE5DE] dark:border-white/10 scroll-mt-24"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="max-w-2xl border-b border-[#DFE5DE] dark:border-white/10 pb-6 mb-8">
        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
          Panduan Eksplorasi Interaktif
        </p>
        <h2
          id="guide-heading"
          class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[#17251F] dark:text-white"
        >
          Mudah Digunakan Seluruh Warga
        </h2>
        <p class="mt-2 text-sm sm:text-base text-[#647067] dark:text-slate-300 leading-relaxed">
          Dirancang praktis tanpa perlu keahlian sistem informasi geografis. Ikuti empat langkah mudah untuk mulai menjelajahi data pembangunan Bojonegoro.
        </p>
      </div>

      <!-- Demonstrasi Visual: Langkah Bernomor di Kiri (5 Kolom lg) + Panel Simulasi Peta di Kanan (7 Kolom lg) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
        <!-- Kolom Kiri: 4 Langkah Bernomor yang Dapat Dipilih -->
        <div class="lg:col-span-5 space-y-3">
          <button
            v-for="(item, idx) in steps"
            :key="item.step"
            type="button"
            class="w-full text-left rounded-2xl p-4 sm:p-5 border transition-all cursor-pointer min-h-[88px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            :class="
              activeStepIndex === idx
                ? 'border-blue-600 bg-blue-50/40 dark:border-blue-400 dark:bg-blue-950/20 shadow-xs'
                : 'border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5] dark:bg-[#0c1522] hover:border-slate-400 dark:hover:border-slate-600'
            "
            @click="activeStepIndex = idx"
          >
            <div class="flex items-start gap-3.5">
              <span
                class="font-mono text-sm font-bold px-2 py-0.5 rounded-md tabular-nums shrink-0"
                :class="
                  activeStepIndex === idx
                    ? 'bg-blue-600 text-white'
                    : 'bg-white dark:bg-white/10 text-[#647067] dark:text-slate-400 border border-[#DFE5DE] dark:border-white/10'
                "
              >
                {{ item.step }}
              </span>

              <div class="min-w-0">
                <h3
                  class="text-base font-bold transition-colors"
                  :class="activeStepIndex === idx ? 'text-blue-700 dark:text-blue-400' : 'text-[#17251F] dark:text-white'"
                >
                  {{ item.title }}
                </h3>
                <p class="mt-1 text-xs text-[#647067] dark:text-slate-300 leading-relaxed">
                  {{ item.desc }}
                </p>
              </div>
            </div>
          </button>
        </div>

        <!-- Kolom Kanan: Panel Simulasi Peta yang Menggambarkan Langkah Terpilih -->
        <div class="lg:col-span-7">
          <div class="rounded-3xl border border-[#DFE5DE] dark:border-white/15 bg-[#F7F8F5] dark:bg-[#0c1522] p-6 sm:p-8 shadow-md space-y-5">
            <div class="flex items-center justify-between text-xs text-[#647067] dark:text-slate-400 border-b border-[#DFE5DE] dark:border-white/10 pb-3">
              <span class="font-bold text-[#17251F] dark:text-white">
                SIMULASI LANGKAH {{ steps[activeStepIndex]?.step }}: {{ steps[activeStepIndex]?.title?.toUpperCase() }}
              </span>
              <span class="font-mono text-[11px] text-blue-600 dark:text-blue-400 font-bold">
                LANGKAH {{ activeStepIndex + 1 }} DARI 4
              </span>
            </div>

            <!-- Frame Simulasi Tampilan Peta Sesuai Langkah -->
            <div class="relative w-full h-64 sm:h-76 rounded-2xl bg-white dark:bg-[#060b13] border border-[#DFE5DE] dark:border-white/10 overflow-hidden p-4 flex flex-col justify-between">
              <!-- Visual Langkah 01: Temukan Lokasi -->
              <template v-if="activeStepIndex === 0">
                <div class="mx-auto w-full max-w-sm rounded-xl border border-blue-500 bg-white dark:bg-[#0c1522] p-2.5 shadow-md flex items-center gap-2">
                  <UIcon name="i-lucide-search" class="size-4 text-blue-600" />
                  <span class="text-xs font-semibold text-[#17251F] dark:text-white">Dander, Bojonegoro</span>
                  <span class="ml-auto text-[10px] font-mono px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                    Kecamatan
                  </span>
                </div>

                <div class="my-auto text-center space-y-2">
                  <UIcon name="i-lucide-map-pin" class="size-10 text-blue-600 dark:text-blue-400 mx-auto animate-bounce" />
                  <p class="text-xs font-semibold text-[#17251F] dark:text-white">
                    Peta otomatis terbang ke koordinat pusat Kecamatan Dander
                  </p>
                  <p class="text-[11px] text-[#647067] dark:text-slate-400">
                    Zoom level 13x · Batas administrasi desa & ruas jalan ditampilkan
                  </p>
                </div>
              </template>

              <!-- Visual Langkah 02: Pilih Informasi Lapisan -->
              <template v-else-if="activeStepIndex === 1">
                <div class="grid grid-cols-2 gap-3 max-w-md mx-auto my-auto w-full">
                  <div class="rounded-xl border border-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/40 p-3 text-xs">
                    <div class="flex items-center justify-between font-bold text-emerald-800 dark:text-emerald-300">
                      <span>Jalan Poros Desa</span>
                      <span>✓</span>
                    </div>
                    <p class="mt-1 text-[11px] text-emerald-700 dark:text-emerald-400">1.187 ruas aktif</p>
                  </div>
                  <div class="rounded-xl border border-blue-500 bg-blue-50/80 dark:bg-blue-950/40 p-3 text-xs">
                    <div class="flex items-center justify-between font-bold text-blue-800 dark:text-blue-300">
                      <span>Bengawan Solo</span>
                      <span>✓</span>
                    </div>
                    <p class="mt-1 text-[11px] text-blue-700 dark:text-blue-400">Aliran sungai aktif</p>
                  </div>
                  <div class="rounded-xl border border-pink-500 bg-pink-50/80 dark:bg-pink-950/40 p-3 text-xs">
                    <div class="flex items-center justify-between font-bold text-pink-800 dark:text-pink-300">
                      <span>Situs Geopark</span>
                      <span>✓</span>
                    </div>
                    <p class="mt-1 text-[11px] text-pink-700 dark:text-pink-400">31 lokasi aktif</p>
                  </div>
                  <div class="rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-white/5 p-3 text-xs opacity-60">
                    <div class="flex items-center justify-between font-bold text-slate-600 dark:text-slate-300">
                      <span>Batas Administrasi</span>
                      <span>○</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Nonaktif</p>
                  </div>
                </div>
              </template>

              <!-- Visual Langkah 03: Buka Detail Objek -->
              <template v-else-if="activeStepIndex === 2">
                <div class="max-w-sm mx-auto my-auto w-full rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] p-4 shadow-lg space-y-2">
                  <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-blue-600 dark:text-blue-400">KARTU DETAIL RUAS JALAN</span>
                    <span class="rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 px-1.5 py-0.5 text-[10px] font-bold">
                      Kondisi Baik
                    </span>
                  </div>
                  <h4 class="text-sm font-bold text-[#17251F] dark:text-white">
                    Ruas Jl. Dander - Ngunut
                  </h4>
                  <div class="grid grid-cols-2 gap-2 text-[11px] tabular-nums pt-1 text-[#647067] dark:text-slate-300 border-t border-[#DFE5DE] dark:border-white/10">
                    <div>Panjang: <strong class="text-[#17251F] dark:text-white">3.450 meter</strong></div>
                    <div>Permukaan: <strong class="text-[#17251F] dark:text-white">Cor Beton</strong></div>
                  </div>
                </div>
              </template>

              <!-- Visual Langkah 04: Ubah Tampilan Peta -->
              <template v-else>
                <div class="flex items-center justify-center gap-3 my-auto">
                  <div class="rounded-xl border-2 border-blue-600 bg-blue-50/50 dark:bg-blue-950/40 p-3 text-center space-y-1.5 shadow-sm">
                    <UIcon name="i-lucide-map" class="size-6 text-blue-600 dark:text-blue-400 mx-auto" />
                    <p class="text-xs font-bold text-[#17251F] dark:text-white">Peta Jalan Vektor</p>
                    <span class="text-[10px] text-blue-700 dark:text-blue-300 font-semibold">Aktif</span>
                  </div>
                  <div class="rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-white/5 p-3 text-center space-y-1.5 opacity-80">
                    <UIcon name="i-lucide-globe" class="size-6 text-blue-500 mx-auto" />
                    <p class="text-xs font-bold text-[#17251F] dark:text-white">Foto Satelit</p>
                    <span class="text-[10px] text-[#647067] dark:text-slate-400">Tersedia</span>
                  </div>
                  <div class="rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-white/5 p-3 text-center space-y-1.5 opacity-80">
                    <UIcon name="i-lucide-layers" class="size-6 text-purple-500 mx-auto" />
                    <p class="text-xs font-bold text-[#17251F] dark:text-white">Rupabumi (RBI)</p>
                    <span class="text-[10px] text-[#647067] dark:text-slate-400">Tersedia</span>
                  </div>
                </div>
              </template>

              <div class="text-center text-[11px] text-[#647067] dark:text-slate-400">
                {{ steps[activeStepIndex]?.actionNote }}
              </div>
            </div>

            <!-- Tombol Navigasi ke Peta Nyata: Biru Peta #2563EB -->
            <div class="pt-2 flex items-center justify-between gap-3">
              <span class="text-xs text-[#647067] dark:text-slate-400">
                Praktis di layar smartphone maupun komputer
              </span>

              <NuxtLink
                to="/maps"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition-all min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
              >
                <span>Buka Peta Interaktif Sekarang</span>
                <UIcon name="i-lucide-arrow-right" class="size-4" />
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

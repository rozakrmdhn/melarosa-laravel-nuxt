<script setup lang="ts">
interface MetricItem {
  value: string;
  unit: string;
  label: string;
  detail: string;
  to: string;
  isFlagship?: boolean;
  unitColorClass?: string;
}

const metrics: MetricItem[] = [
  {
    value: '28',
    unit: 'Kecamatan',
    label: 'Cakupan Wilayah Kecamatan',
    detail: 'Pusat pemerintahan dan pelayanan masyarakat',
    to: '#jelajah-wilayah',
    unitColorClass: 'text-[#176B4D] dark:text-emerald-400',
  },
  {
    value: '430',
    unit: 'Desa & Kelurahan',
    label: 'Pemerintahan Desa',
    detail: 'Sebaran titik usulan dan hasil pembangunan',
    to: '/maps',
    unitColorClass: 'text-blue-600 dark:text-blue-400',
  },
  {
    value: '1.187',
    unit: 'Ruas Jalan',
    label: 'Jalan Poros Desa',
    detail: 'Jalur penghubung antardesa dan permukiman',
    to: '/maps',
    isFlagship: true,
    unitColorClass: 'text-blue-600 dark:text-blue-400',
  },
  {
    value: '1.424,8',
    unit: 'km',
    label: 'Panjang Jaringan Jalan',
    detail: 'Total jaringan jalan poros desa terdata',
    to: '/maps',
    isFlagship: true,
    unitColorClass: 'text-blue-600 dark:text-blue-400',
  },
  {
    value: '31',
    unit: 'Situs Resmi',
    label: 'Geopark Nasional',
    detail: '17 situs geologi, 5 hayati, dan 9 budaya',
    to: '#cerita-geopark',
    unitColorClass: 'text-[#176B4D] dark:text-emerald-400',
  },
  {
    value: '2.307',
    unit: 'km²',
    label: 'Luas Wilayah',
    detail: 'Lembah Bengawan Solo hingga kawasan hutan',
    to: '/maps',
    unitColorClass: 'text-blue-600 dark:text-blue-400',
  },
];
</script>

<template>
  <section
    aria-labelledby="metrics-heading"
    class="relative w-full border-b border-[#DFE5DE] dark:border-white/10 bg-white dark:bg-[#080e17] py-8 sm:py-10"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-baseline justify-between gap-2 border-b border-[#DFE5DE] dark:border-white/10 pb-4 mb-6">
        <div>
          <h2
            id="metrics-heading"
            class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400"
          >
            Angka Pembangunan & Geospasial Kabupaten Bojonegoro
          </h2>
          <p class="text-xs text-[#647067] dark:text-slate-400 mt-0.5">
            Agregasi data jaringan jalan dan wilayah terverifikasi Bappeda Kabupaten Bojonegoro
          </p>
        </div>
        <div class="text-xs text-[#647067] dark:text-slate-400">
          Pembaruan Basis Data 2026
        </div>
      </div>

      <!-- Satu Baris Informasi Responsif (Bukan 6 Kotak Fotokopi) -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-x-6 gap-y-8 divide-y sm:divide-y-0 sm:divide-x divide-[#DFE5DE] dark:divide-white/10">
        <NuxtLink
          v-for="(item, idx) in metrics"
          :key="item.label"
          :to="item.to"
          class="group flex flex-col justify-between pt-4 sm:pt-0"
          :class="idx > 0 ? 'sm:pl-6' : ''"
        >
          <div>
            <div class="flex items-baseline gap-1.5 tabular-nums">
              <span
                class="font-bold tracking-tight text-[#17251F] dark:text-white transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"
                :class="item.isFlagship ? 'text-3xl sm:text-4xl' : 'text-2xl sm:text-3xl'"
              >
                {{ item.value }}
              </span>
              <span class="text-xs font-semibold" :class="item.unitColorClass || 'text-blue-600 dark:text-blue-400'">
                {{ item.unit }}
              </span>
            </div>

            <p class="mt-1 text-xs font-semibold text-[#17251F] dark:text-slate-200 leading-snug">
              {{ item.label }}
            </p>
          </div>

          <p class="mt-2 text-[11px] text-[#647067] dark:text-slate-400 leading-normal">
            {{ item.detail }}
          </p>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

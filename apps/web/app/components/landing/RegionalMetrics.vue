<script setup lang="ts">
import { computed } from 'vue';
import { useRekapJalanApi } from '~/composables/useRekapJalanApi';
import type { RekapKecamatanItem } from '~/types/rekap-jalan';

// Shares the same Nuxt asyncData key with DistrictExplorer so the API is requested only once.
const api = useRekapJalanApi();
const {
  data: rawRekapData,
  status,
  error: fetchError,
  refresh,
} = useAsyncData<RekapKecamatanItem[]>(
  'landing-rekap-kecamatan',
  () => api.fetchRekapKecamatan('ruas'),
  {
    server: false,
    lazy: true,
    default: () => [],
  }
);

const isDataLoading = computed(
  () =>
    status.value === 'pending' ||
    (status.value === 'idle' && !fetchError.value && (rawRekapData.value?.length ?? 0) === 0)
);

const globalRoadStats = computed(() => {
  const list = rawRekapData.value || [];
  if (list.length === 0) return null;

  let totalRuas = 0;
  let totalPanjang = 0;
  let panjangDibangun = 0;
  let panjangBelum = 0;

  for (const item of list) {
    totalRuas += Number(item.jumlah_ruas) || 0;
    totalPanjang += Number(item.total_panjang) || 0;
    panjangDibangun += Number(item.panjang_dibangun) || 0;
    panjangBelum += Number(item.panjang_belum) || 0;
  }

  const progress = totalPanjang > 0 ? (panjangDibangun / totalPanjang) * 100 : 0;

  return {
    totalRuas: totalRuas || 1187,
    totalPanjang: totalPanjang || 1424800,
    panjangDibangun,
    panjangBelum,
    progress: Math.min(Math.max(progress, 0), 100),
  };
});

function formatPercent(val: number): string {
  return `${val.toLocaleString('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })}%`;
}

function formatLength(meters: number): string {
  if (Math.abs(meters) >= 1000) {
    return `${(meters / 1000).toLocaleString('id-ID', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} km`;
  }
  return `${Math.round(meters).toLocaleString('id-ID')} m`;
}

function formatKmNumber(meters: number): string {
  return (meters / 1000).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatCount(val: number): string {
  return Math.round(val).toLocaleString('id-ID');
}
</script>

<template>
  <section
    aria-labelledby="metrics-heading"
    class="relative w-full border-b border-[#DFE5DE] dark:border-white/10 bg-white dark:bg-[#080e17] py-7 sm:py-9"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row sm:items-baseline justify-between gap-1.5 border-b border-[#DFE5DE] dark:border-white/10 pb-3.5 mb-5">
        <h2
          id="metrics-heading"
          class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400"
        >
          Skala Wilayah & Infrastruktur
        </h2>
        <p class="text-xs text-[#4F5D54] dark:text-slate-300 tabular-nums">
          Data Terverifikasi Bappeda Kabupaten Bojonegoro · 2026
        </p>
      </div>

      <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-[1.25fr_1.65fr_1fr_1.1fr_0.9fr_0.9fr]">
        <div class="pb-4 sm:pb-5 lg:pb-0 pr-4 sm:pr-6 lg:pr-4 xl:pr-5">
          <dd class="flex flex-wrap items-baseline gap-x-2 gap-y-1 tabular-nums">
            <span class="inline-flex items-baseline gap-1">
              <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none text-[#17251F] dark:text-slate-50">
                28
              </span>
              <span class="text-[11px] sm:text-xs font-semibold text-[#4F5D54] dark:text-slate-300">
                Kec
              </span>
            </span>
            <span class="text-sm font-medium text-[#7A877E] dark:text-slate-500" aria-hidden="true">
              /
            </span>
            <span class="inline-flex items-baseline gap-1">
              <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none text-[#17251F] dark:text-slate-50">
                430
              </span>
              <span class="text-[11px] sm:text-xs font-semibold text-[#4F5D54] dark:text-slate-300">
                Desa
              </span>
            </span>
          </dd>
          <dt class="mt-1.5 text-xs sm:text-[13px] font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
            Kecamatan, Desa & Kelurahan
          </dt>
        </div>

        <div class="pb-4 sm:pb-5 lg:pb-0 pl-4 sm:px-6 lg:px-4 xl:px-5 border-l border-[#DFE5DE] dark:border-white/10">
          <template v-if="globalRoadStats">
            <dd class="tabular-nums">
              <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                <div class="flex items-baseline gap-1.5">
                  <span class="text-2xl sm:text-3xl xl:text-[2rem] font-bold tracking-tight leading-none text-[#176B4D] dark:text-emerald-400">
                    {{ formatPercent(globalRoadStats.progress) }}
                  </span>
                  <span class="text-xs font-bold text-[#176B4D] dark:text-emerald-400">
                    Terbangun
                  </span>
                </div>
              </div>
              <div
                class="mt-2 w-full h-1.5 rounded-full bg-[#D5DDD4] dark:bg-slate-800 overflow-hidden"
                aria-hidden="true"
              >
                <div
                  class="h-full rounded-full bg-[#176B4D] dark:bg-emerald-400 transition-all duration-300"
                  :style="{ width: `${globalRoadStats.progress}%` }"
                ></div>
              </div>
            </dd>
            <dt class="mt-1.5 text-[11px] sm:text-xs font-medium text-[#4F5D54] dark:text-slate-300 tabular-nums leading-snug">
              {{ formatLength(globalRoadStats.panjangDibangun) }} terbangun · {{ formatLength(globalRoadStats.panjangBelum) }} belum
            </dt>
          </template>

          <template v-else-if="isDataLoading">
            <dd role="status" class="space-y-2">
              <div class="h-7 w-24 rounded bg-[#DFE5DE] dark:bg-slate-800" aria-hidden="true"></div>
              <div class="h-1.5 w-full rounded-full bg-[#DFE5DE] dark:bg-slate-800" aria-hidden="true"></div>
              <span class="sr-only">Memuat statistik jalan global seluruh kecamatan...</span>
            </dd>
            <dt class="mt-1.5 text-xs font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
              Memuat realisasi jalan global...
            </dt>
          </template>

          <template v-else>
            <dd>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#7A877E] dark:border-slate-500 bg-[#F7F8F5] dark:bg-slate-800 hover:bg-[#EDF1EC] dark:hover:bg-slate-700 px-3 py-2 text-xs font-semibold text-[#17251F] dark:text-slate-100 cursor-pointer min-h-[44px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                @click="() => refresh()"
              >
                <UIcon name="i-lucide-refresh-cw" class="size-3.5" />
                <span>Muat Realisasi Global</span>
              </button>
            </dd>
            <dt class="mt-1.5 text-xs font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
              Realisasi Jalan Semua Kecamatan
            </dt>
          </template>
        </div>

        <div class="py-4 sm:pt-0 sm:pb-5 lg:py-0 pr-4 sm:pl-6 sm:pr-0 lg:px-4 xl:px-5 border-t sm:border-t-0 sm:border-l border-[#DFE5DE] dark:border-white/10">
          <dd class="flex items-baseline gap-1.5 tabular-nums">
            <span class="text-2xl sm:text-3xl xl:text-[2rem] font-bold tracking-tight leading-none text-[#176B4D] dark:text-emerald-400">
              {{ globalRoadStats ? formatCount(globalRoadStats.totalRuas) : '1.187' }}
            </span>
          </dd>
          <dt class="mt-1.5 text-xs sm:text-[13px] font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
            Ruas Jalan Poros Desa
          </dt>
        </div>

        <div class="py-4 sm:pt-5 sm:pb-0 lg:py-0 pl-4 sm:pl-0 sm:pr-6 lg:px-4 xl:px-5 border-t border-l sm:border-l-0 lg:border-t-0 lg:border-l border-[#DFE5DE] dark:border-white/10">
          <dd class="flex items-baseline gap-1.5 tabular-nums">
            <span class="text-2xl sm:text-3xl xl:text-[2rem] font-bold tracking-tight leading-none text-[#176B4D] dark:text-emerald-400">
              {{ globalRoadStats ? formatKmNumber(globalRoadStats.totalPanjang) : '1.424,80' }}
            </span>
            <span class="text-xs sm:text-sm font-bold text-[#176B4D] dark:text-emerald-400">
              km
            </span>
          </dd>
          <dt class="mt-1.5 text-xs sm:text-[13px] font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
            Panjang Jaringan Jalan
          </dt>
        </div>

        <div class="pt-4 sm:pt-5 lg:pt-0 pr-4 sm:px-6 lg:px-4 xl:px-5 border-t lg:border-t-0 sm:border-l border-[#DFE5DE] dark:border-white/10">
          <dd class="flex items-baseline gap-1.5 tabular-nums">
            <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none text-[#17251F] dark:text-slate-50">
              31
            </span>
          </dd>
          <dt class="mt-1.5 text-xs sm:text-[13px] font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
            Situs Geopark Nasional
          </dt>
        </div>

        <div class="pt-4 sm:pt-5 lg:pt-0 pl-4 sm:pl-6 sm:pr-0 lg:pl-4 xl:pl-5 border-t border-l lg:border-t-0 border-[#DFE5DE] dark:border-white/10">
          <dd class="flex items-baseline gap-1.5 tabular-nums">
            <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none text-[#17251F] dark:text-slate-50">
              2.307
            </span>
            <span class="text-xs sm:text-sm font-bold text-[#176B4D] dark:text-emerald-400">
              km²
            </span>
          </dd>
          <dt class="mt-1.5 text-xs sm:text-[13px] font-medium text-[#4F5D54] dark:text-slate-300 leading-snug">
            Luas Wilayah
          </dt>
        </div>
      </dl>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { KECAMATAN_ENTRIES, type KecamatanEntry } from '~/constants/kecamatan';
import { useRekapJalanApi } from '~/composables/useRekapJalanApi';
import type { RekapKecamatanItem } from '~/types/rekap-jalan';

type ZonaKey = 'SEMUA' | 'Kota' | 'Timur' | 'Tengah' | 'Selatan' | 'Barat';

const MOBILE_INITIAL_COUNT = 8;

const selectedZona = ref<ZonaKey>('SEMUA');
const searchQuery = ref('');
const showAllMobile = ref(false);

const defaultDistrict =
  KECAMATAN_ENTRIES.find((k) => k.name === 'Bojonegoro') || KECAMATAN_ENTRIES[0]!;

const selectedKecamatan = ref<KecamatanEntry>(defaultDistrict);
const mobileExpandedName = ref<string | null>(defaultDistrict.name);

// Client-only lazy fetch keeps SSR fast and avoids stale server-side network errors.
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

// API returns uppercase district names sometimes prefixed with "Kecamatan ".
const rekapMap = computed(() => {
  const map = new Map<string, RekapKecamatanItem>();
  for (const item of rawRekapData.value || []) {
    if (item?.nama_kecamatan) {
      const key = item.nama_kecamatan
        .replace(/^kecamatan\s+/i, '')
        .trim()
        .toLowerCase();
      map.set(key, item);
    }
  }
  return map;
});

function getDistrictStats(name: string): RekapKecamatanItem | undefined {
  return rekapMap.value.get(name.trim().toLowerCase());
}

const selectedStats = computed(() => getDistrictStats(selectedKecamatan.value.name));

const zonaList: Array<{ key: ZonaKey; label: string; count: number }> = [
  { key: 'SEMUA', label: 'Semua', count: KECAMATAN_ENTRIES.length },
  {
    key: 'Kota',
    label: 'Perkotaan',
    count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Kota').length,
  },
  {
    key: 'Timur',
    label: 'Timur & Tenggara',
    count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Timur' || k.zona === 'Tenggara').length,
  },
  {
    key: 'Tengah',
    label: 'Tengah',
    count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Tengah').length,
  },
  {
    key: 'Selatan',
    label: 'Selatan',
    count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Selatan').length,
  },
  {
    key: 'Barat',
    label: 'Barat',
    count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Barat').length,
  },
];

const activeZonaLabel = computed(
  () => zonaList.find((z) => z.key === selectedZona.value)?.label || 'Semua'
);

const filteredList = computed(() => {
  let list = KECAMATAN_ENTRIES;

  if (selectedZona.value === 'Timur') {
    list = list.filter((k) => k.zona === 'Timur' || k.zona === 'Tenggara');
  } else if (selectedZona.value !== 'SEMUA') {
    list = list.filter((k) => k.zona === selectedZona.value);
  }

  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter(
    (k) =>
      k.name.toLowerCase().includes(q) ||
      k.desc.toLowerCase().includes(q) ||
      k.zona.toLowerCase().includes(q)
  );
});

const canCollapseMobile = computed(
  () =>
    selectedZona.value === 'SEMUA' &&
    !searchQuery.value.trim() &&
    filteredList.value.length > MOBILE_INITIAL_COUNT
);

// Keep the detail panel synchronized when a filter excludes the current selection.
watch(filteredList, (nextList) => {
  if (nextList.length === 0) return;
  const stillVisible = nextList.some((k) => k.name === selectedKecamatan.value.name);
  if (!stillVisible) {
    selectedKecamatan.value = nextList[0]!;
    mobileExpandedName.value = nextList[0]!.name;
  }
});

function clampPercent(val?: number): number {
  const num = Number(val);
  if (val === undefined || val === null || Number.isNaN(num)) return 0;
  return Math.min(Math.max(num, 0), 100);
}

function formatPercent(val?: number): string {
  const num = Number(val);
  if (val === undefined || val === null || Number.isNaN(num)) return '-';
  const clamped = Math.min(Math.max(num, 0), 100);
  return `${clamped.toLocaleString('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })}%`;
}

function formatLength(meters?: number): string {
  const num = Number(meters);
  if (meters === undefined || meters === null || Number.isNaN(num)) return '-';
  if (Math.abs(num) >= 1000) {
    return `${(num / 1000).toLocaleString('id-ID', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} km`;
  }
  return `${Math.round(num).toLocaleString('id-ID')} m`;
}

function formatCount(val?: number): string {
  const num = Number(val);
  if (val === undefined || val === null || Number.isNaN(num)) return '-';
  return Math.round(num).toLocaleString('id-ID');
}

function selectDistrict(kec: KecamatanEntry, index: number) {
  selectedKecamatan.value = kec;
  if (index >= MOBILE_INITIAL_COUNT) {
    showAllMobile.value = true;
  }
  mobileExpandedName.value = mobileExpandedName.value === kec.name ? null : kec.name;
}

function resetFilters() {
  selectedZona.value = 'SEMUA';
  searchQuery.value = '';
}

function openDistrictInMap(kec: KecamatanEntry) {
  navigateTo(
    `/maps/@${kec.coordinate[1].toFixed(5)},${kec.coordinate[0].toFixed(5)},${kec.zoom}z?kecamatan=${encodeURIComponent(kec.name)}&search=${encodeURIComponent('Kecamatan ' + kec.name)}`
  );
}
</script>

<template>
  <section
    id="jelajah-wilayah"
    aria-labelledby="explorer-heading"
    class="relative w-full bg-[#F7F8F5] dark:bg-[#070b14] py-12 sm:py-16 lg:py-20 border-b border-[#E4EAE3] dark:border-white/[0.07] scroll-mt-24"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-5">
        <div class="max-w-2xl">
          <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
            Data Infrastruktur
          </p>
          <h2
            id="explorer-heading"
            class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[#17251F] dark:text-slate-50 leading-tight"
          >
            Statistik Infrastruktur Wilayah
          </h2>
          <p class="mt-2 text-sm sm:text-base text-[#4F5D54] dark:text-slate-300 leading-relaxed">
            Ringkasan data pembangunan jalan poros desa, realisasi fisik, dan sebaran wilayah di Kabupaten Bojonegoro.
          </p>
        </div>

        <div class="w-full md:w-72 lg:w-80 shrink-0">
          <div class="relative flex items-center">
            <UIcon
              name="i-lucide-search"
              class="pointer-events-none absolute left-3.5 size-4 text-[#4F5D54] dark:text-slate-400"
            />
            <input
              v-model="searchQuery"
              type="text"
              inputmode="search"
              placeholder="Cari kecamatan..."
              aria-label="Cari nama kecamatan"
              class="w-full rounded-xl border border-[#DCE3DB] dark:border-white/10 bg-white dark:bg-[#0c1522] pl-10 pr-11 py-2.5 text-sm text-[#17251F] dark:text-slate-50 placeholder-[#4F5D54] dark:placeholder-slate-400 transition-colors focus:border-blue-600 dark:focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:focus:ring-blue-400/25 min-h-[44px]"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-0.5 size-11 rounded-lg flex items-center justify-center text-[#4F5D54] dark:text-slate-300 hover:text-[#17251F] dark:hover:text-white cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
              aria-label="Hapus kata kunci pencarian"
              @click="searchQuery = ''"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>
        </div>
      </div>

      <div
        role="group"
        aria-label="Filter wilayah kecamatan"
        class="mt-6 inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-2xl bg-[#EAEFE9] dark:bg-[#0c1522] p-1.5 scrollbar-none"
      >
        <button
          v-for="zona in zonaList"
          :key="zona.key"
          type="button"
          :aria-pressed="selectedZona === zona.key"
          class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs sm:text-sm font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
          :class="
            selectedZona === zona.key
              ? 'bg-white dark:bg-[#172436] text-[#17251F] dark:text-white shadow-xs'
              : 'text-[#4F5D54] dark:text-slate-300 hover:text-[#17251F] dark:hover:text-white hover:bg-white/50 dark:hover:bg-white/5'
          "
          @click="selectedZona = zona.key"
        >
          <span>{{ zona.label }}</span>
          <span
            class="rounded-md px-1.5 py-0.5 text-[11px] tabular-nums"
            :class="
              selectedZona === zona.key
                ? 'bg-[#EEF2ED] dark:bg-white/10 text-[#17251F] dark:text-white font-bold'
                : 'bg-black/[0.04] dark:bg-white/[0.06] text-[#4F5D54] dark:text-slate-300'
            "
          >
            {{ zona.count }}
          </span>
        </button>
      </div>

      <div class="mt-6 grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-8 items-start">
        <div class="md:col-span-6 lg:col-span-7">
          <div
            v-if="filteredList.length > 0"
            class="rounded-2xl bg-white dark:bg-[#0c1522] border border-[#E4EAE3] dark:border-white/[0.07] p-2 sm:p-3"
          >
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-1.5">
              <div
                v-for="(kec, idx) in filteredList"
                :key="kec.name"
                :class="[
                  canCollapseMobile && !showAllMobile && idx >= MOBILE_INITIAL_COUNT
                    ? 'hidden lg:block'
                    : 'block',
                ]"
              >
                <div
                  class="rounded-xl transition-colors overflow-hidden"
                  :class="
                    selectedKecamatan.name === kec.name
                      ? 'bg-[#F0F6FF] dark:bg-blue-950/35 ring-1 ring-blue-600/25 dark:ring-blue-400/30'
                      : 'hover:bg-[#F5F7F4] dark:hover:bg-white/[0.04]'
                  "
                >
                  <button
                    type="button"
                    :aria-pressed="selectedKecamatan.name === kec.name"
                    :aria-expanded="mobileExpandedName === kec.name"
                    class="w-full text-left px-3.5 py-3 cursor-pointer min-h-[70px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                    @click="selectDistrict(kec, idx)"
                  >
                    <div class="flex items-baseline justify-between gap-2">
                      <div class="flex items-baseline gap-2.5 min-w-0">
                        <span class="text-xs font-semibold text-[#4F5D54] dark:text-slate-400 tabular-nums shrink-0">
                          {{ String(idx + 1).padStart(2, '0') }}
                        </span>
                        <span
                          class="text-[15px] font-bold truncate"
                          :class="
                            selectedKecamatan.name === kec.name
                              ? 'text-blue-700 dark:text-blue-400'
                              : 'text-[#17251F] dark:text-slate-50'
                          "
                        >
                          {{ kec.name }}
                        </span>
                      </div>

                      <div class="flex items-center gap-2 shrink-0">
                        <span
                          v-if="getDistrictStats(kec.name)"
                          class="text-xs font-bold tabular-nums text-[#176B4D] dark:text-emerald-400"
                        >
                          {{ formatPercent(getDistrictStats(kec.name)?.progress) }}
                        </span>
                        <span
                          v-else-if="isDataLoading"
                          class="inline-block w-10 h-3.5 rounded bg-[#E6ECE5] dark:bg-slate-800"
                          aria-hidden="true"
                        ></span>

                        <UIcon
                          name="i-lucide-chevron-down"
                          class="size-4 text-[#4F5D54] dark:text-slate-300 transition-transform md:hidden"
                          :class="mobileExpandedName === kec.name ? 'rotate-180 text-blue-700 dark:text-blue-400' : ''"
                        />
                      </div>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs text-[#4F5D54] dark:text-slate-300 tabular-nums">
                      <template v-if="getDistrictStats(kec.name)">
                        <span>
                          {{ formatCount(getDistrictStats(kec.name)?.jumlah_desa) }} desa · {{ formatLength(getDistrictStats(kec.name)?.total_panjang) }}
                        </span>
                      </template>
                      <template v-else-if="isDataLoading">
                        <span>Memuat data...</span>
                      </template>
                      <template v-else>
                        <span>Zona {{ kec.zona }}</span>
                      </template>

                      <span
                        v-if="getDistrictStats(kec.name) || isDataLoading"
                        class="text-[11px] text-[#4F5D54] dark:text-slate-400"
                      >
                        Zona {{ kec.zona }}
                      </span>
                    </div>

                    <div
                      v-if="getDistrictStats(kec.name)"
                      class="mt-2.5 w-full h-1 rounded-full bg-[#E2E8E1] dark:bg-slate-800 overflow-hidden"
                      aria-hidden="true"
                    >
                      <div
                        class="h-full rounded-full bg-[#176B4D] dark:bg-emerald-400 transition-all duration-300"
                        :style="{ width: `${clampPercent(getDistrictStats(kec.name)?.progress)}%` }"
                      ></div>
                    </div>
                  </button>

                  <div
                    v-if="mobileExpandedName === kec.name"
                    class="md:hidden border-t border-blue-600/10 dark:border-blue-400/15 px-3.5 py-4 bg-white/80 dark:bg-[#0a121e] space-y-4"
                  >
                    <p class="text-xs text-[#4F5D54] dark:text-slate-300">
                      {{ kec.desc }}
                    </p>

                    <div
                      v-if="isDataLoading && !getDistrictStats(kec.name)"
                      role="status"
                      class="py-3 text-xs text-[#4F5D54] dark:text-slate-300"
                    >
                      Memuat statistik Kecamatan {{ kec.name }}...
                    </div>

                    <div
                      v-else-if="fetchError && !getDistrictStats(kec.name)"
                      role="alert"
                      class="rounded-xl bg-amber-50 dark:bg-amber-950/40 p-3.5 space-y-2.5"
                    >
                      <p class="text-xs text-amber-950 dark:text-amber-100">
                        Statistik jalan untuk Kecamatan {{ kec.name }} belum dapat ditampilkan.
                      </p>
                      <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-amber-900/40 hover:bg-amber-100 dark:hover:bg-amber-800/50 text-xs font-semibold text-amber-950 dark:text-amber-100 cursor-pointer min-h-[44px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
                        @click="() => refresh()"
                      >
                        <UIcon name="i-lucide-refresh-cw" class="size-3.5" />
                        <span>Muat Ulang Data</span>
                      </button>
                    </div>

                    <div
                      v-else-if="getDistrictStats(kec.name)"
                      class="space-y-4 tabular-nums"
                    >
                      <div class="rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] p-3.5">
                        <div class="flex items-baseline justify-between gap-2">
                          <span class="text-xs font-semibold text-[#4F5D54] dark:text-slate-300">
                            Jalan Poros Terbangun
                          </span>
                          <span class="text-2xl font-bold text-[#176B4D] dark:text-emerald-400">
                            {{ formatPercent(getDistrictStats(kec.name)?.progress) }}
                          </span>
                        </div>

                        <div class="mt-2 w-full h-2 rounded-full bg-[#DCE3DB] dark:bg-slate-800 overflow-hidden">
                          <div
                            class="h-full rounded-full bg-[#176B4D] dark:bg-emerald-400"
                            :style="{ width: `${clampPercent(getDistrictStats(kec.name)?.progress)}%` }"
                          ></div>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs text-[#4F5D54] dark:text-slate-300">
                          <span>
                            Sudah terbangun:
                            <strong class="text-[#17251F] dark:text-slate-50">
                              {{ formatLength(getDistrictStats(kec.name)?.panjang_dibangun) }}
                            </strong>
                          </span>
                          <span>
                            Belum:
                            <strong class="text-[#17251F] dark:text-slate-50">
                              {{ formatLength(getDistrictStats(kec.name)?.panjang_belum) }}
                            </strong>
                          </span>
                        </div>
                      </div>

                      <dl class="divide-y divide-[#ECEFEB] dark:divide-white/[0.06] text-xs">
                        <div class="py-2 flex items-center justify-between gap-3">
                          <dt class="text-[#4F5D54] dark:text-slate-300">Total panjang jalan</dt>
                          <dd class="font-bold text-[#17251F] dark:text-slate-50">
                            {{ formatLength(getDistrictStats(kec.name)?.total_panjang) }}
                          </dd>
                        </div>
                        <div class="py-2 flex items-center justify-between gap-3">
                          <dt class="text-[#4F5D54] dark:text-slate-300">Ruas jalan poros</dt>
                          <dd class="font-bold text-[#17251F] dark:text-slate-50">
                            {{ formatCount(getDistrictStats(kec.name)?.jumlah_ruas) }} ruas
                          </dd>
                        </div>
                        <div class="py-2 flex items-center justify-between gap-3">
                          <dt class="text-[#4F5D54] dark:text-slate-300">Desa & kelurahan</dt>
                          <dd class="font-bold text-[#17251F] dark:text-slate-50">
                            {{ formatCount(getDistrictStats(kec.name)?.jumlah_desa) }} desa
                          </dd>
                        </div>
                        <div class="py-2 flex items-center justify-between gap-3">
                          <dt class="text-[#4F5D54] dark:text-slate-300">Segmen penanganan</dt>
                          <dd class="font-bold text-[#17251F] dark:text-slate-50">
                            {{ formatCount(getDistrictStats(kec.name)?.jumlah_segmen) }} segmen
                          </dd>
                        </div>
                      </dl>
                    </div>

                    <div
                      v-else-if="!fetchError"
                      class="py-2 text-xs text-[#4F5D54] dark:text-slate-300"
                    >
                      Belum ada data jalan poros tercatat untuk kecamatan ini.
                    </div>

                    <button
                      type="button"
                      class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2.5 text-xs font-semibold text-white transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                      @click="openDistrictInMap(kec)"
                    >
                      <UIcon name="i-lucide-map" class="size-4" />
                      <span>Buka Peta Kecamatan {{ kec.name }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="canCollapseMobile"
              class="mt-2 pt-2 border-t border-[#ECEFEB] dark:border-white/[0.06] lg:hidden"
            >
              <button
                type="button"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] hover:bg-[#EAEFE9] dark:hover:bg-white/[0.08] px-4 py-2.5 text-xs sm:text-sm font-semibold text-[#17251F] dark:text-slate-100 transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
                @click="showAllMobile = !showAllMobile"
              >
                <span>
                  {{
                    showAllMobile
                      ? 'Ringkas daftar kecamatan'
                      : `Tampilkan seluruh ${filteredList.length} kecamatan`
                  }}
                </span>
                <UIcon
                  name="i-lucide-chevron-down"
                  class="size-4 transition-transform"
                  :class="showAllMobile ? 'rotate-180' : ''"
                />
              </button>
            </div>
          </div>

          <div
            v-if="filteredList.length === 0"
            class="rounded-2xl border border-[#E4EAE3] dark:border-white/[0.07] bg-white dark:bg-[#0c1522] p-8 sm:p-10 text-center space-y-3"
          >
            <h3 class="text-base font-bold text-[#17251F] dark:text-slate-50">
              Kecamatan tidak ditemukan
            </h3>
            <p class="text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300 max-w-sm mx-auto">
              {{
                selectedZona === 'SEMUA'
                  ? 'Tidak ada nama kecamatan yang cocok dengan kata kunci tersebut.'
                  : `Tidak ada kecamatan yang cocok di wilayah ${activeZonaLabel}.`
              }}
            </p>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white transition-colors cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400"
              @click="resetFilters"
            >
              Reset Pencarian & Filter
            </button>
          </div>
        </div>

        <aside
          aria-label="Detail statistik kecamatan terpilih"
          class="hidden md:block md:col-span-6 lg:col-span-5 rounded-2xl border border-[#E4EAE3] dark:border-white/[0.07] bg-white dark:bg-[#0c1522] p-6 lg:p-7 sticky top-28 space-y-6"
        >
          <div>
            <div class="flex items-start justify-between gap-3">
              <h3 class="text-2xl font-bold text-[#17251F] dark:text-slate-50 leading-snug">
                Kecamatan {{ selectedKecamatan.name }}
              </h3>
              <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-[#F2F5F1] dark:bg-white/[0.06] text-[#17251F] dark:text-slate-200 shrink-0">
                Zona {{ selectedKecamatan.zona }}
              </span>
            </div>
            <p class="mt-1.5 text-sm text-[#4F5D54] dark:text-slate-300 leading-relaxed">
              {{ selectedKecamatan.desc }}
            </p>
          </div>

          <div
            v-if="isDataLoading && !selectedStats"
            role="status"
            class="rounded-xl bg-[#F4F7F3] dark:bg-white/[0.03] p-5 space-y-4"
          >
            <p class="text-sm text-[#4F5D54] dark:text-slate-300">
              Memuat statistik Kecamatan {{ selectedKecamatan.name }}...
            </p>
            <div class="h-2.5 w-full rounded-full bg-[#DCE3DB] dark:bg-slate-800" aria-hidden="true"></div>
            <div class="space-y-2.5 pt-2" aria-hidden="true">
              <div class="h-4 w-3/4 rounded bg-[#DCE3DB] dark:bg-slate-800"></div>
              <div class="h-4 w-2/3 rounded bg-[#DCE3DB] dark:bg-slate-800"></div>
              <div class="h-4 w-1/2 rounded bg-[#DCE3DB] dark:bg-slate-800"></div>
            </div>
          </div>

          <div
            v-else-if="fetchError && !selectedStats"
            role="alert"
            class="rounded-xl bg-amber-50 dark:bg-amber-950/40 p-4 space-y-3"
          >
            <p class="text-sm text-amber-950 dark:text-amber-100">
              Statistik jalan untuk Kecamatan {{ selectedKecamatan.name }} belum dapat ditampilkan.
            </p>
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg bg-white dark:bg-amber-900/40 hover:bg-amber-100 dark:hover:bg-amber-800/50 text-xs font-semibold text-amber-950 dark:text-amber-100 cursor-pointer min-h-[44px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
              @click="() => refresh()"
            >
              <UIcon name="i-lucide-refresh-cw" class="size-3.5" />
              <span>Muat Ulang Data</span>
            </button>
          </div>

          <div
            v-else-if="selectedStats"
            class="space-y-5 tabular-nums"
          >
            <div class="rounded-xl bg-[#F4F7F3] dark:bg-white/[0.03] p-4 sm:p-5">
              <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-semibold text-[#17251F] dark:text-slate-200">
                  Jalan Poros Terbangun
                </span>
                <span class="text-3xl sm:text-4xl font-bold tracking-tight text-[#176B4D] dark:text-emerald-400">
                  {{ formatPercent(selectedStats.progress) }}
                </span>
              </div>

              <div class="mt-3 w-full h-2.5 rounded-full bg-[#DCE3DB] dark:bg-slate-800 overflow-hidden">
                <div
                  class="h-full rounded-full bg-[#176B4D] dark:bg-emerald-400 transition-all duration-300"
                  :style="{ width: `${clampPercent(selectedStats.progress)}%` }"
                ></div>
              </div>

              <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs sm:text-sm text-[#4F5D54] dark:text-slate-300">
                <span>
                  Sudah terbangun:
                  <strong class="font-bold text-[#17251F] dark:text-slate-50">
                    {{ formatLength(selectedStats.panjang_dibangun) }}
                  </strong>
                </span>
                <span>
                  Belum terbangun:
                  <strong class="font-bold text-[#17251F] dark:text-slate-50">
                    {{ formatLength(selectedStats.panjang_belum) }}
                  </strong>
                </span>
              </div>
            </div>

            <dl class="divide-y divide-[#ECEFEB] dark:divide-white/[0.06] text-sm">
              <div class="py-3 flex items-center justify-between gap-4">
                <dt class="text-[#4F5D54] dark:text-slate-300">Total panjang jalan</dt>
                <dd class="font-bold text-[#17251F] dark:text-slate-50">
                  {{ formatLength(selectedStats.total_panjang) }}
                </dd>
              </div>
              <div class="py-3 flex items-center justify-between gap-4">
                <dt class="text-[#4F5D54] dark:text-slate-300">Ruas jalan poros</dt>
                <dd class="font-bold text-[#17251F] dark:text-slate-50">
                  {{ formatCount(selectedStats.jumlah_ruas) }} ruas
                </dd>
              </div>
              <div class="py-3 flex items-center justify-between gap-4">
                <dt class="text-[#4F5D54] dark:text-slate-300">Desa & kelurahan</dt>
                <dd class="font-bold text-[#17251F] dark:text-slate-50">
                  {{ formatCount(selectedStats.jumlah_desa) }} desa
                </dd>
              </div>
              <div class="py-3 flex items-center justify-between gap-4">
                <dt class="text-[#4F5D54] dark:text-slate-300">Segmen penanganan</dt>
                <dd class="font-bold text-[#17251F] dark:text-slate-50">
                  {{ formatCount(selectedStats.jumlah_segmen) }} segmen
                </dd>
              </div>
            </dl>
          </div>

          <div
            v-else
            class="rounded-xl bg-[#F4F7F3] dark:bg-white/[0.03] p-4 text-sm text-[#4F5D54] dark:text-slate-300"
          >
            Belum ada data jalan poros tercatat untuk Kecamatan {{ selectedKecamatan.name }}.
          </div>

          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition-colors cursor-pointer min-h-[46px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 dark:focus-visible:ring-blue-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#0c1522]"
            @click="openDistrictInMap(selectedKecamatan)"
          >
            <UIcon name="i-lucide-map" class="size-4" />
            <span>Buka Peta Kecamatan {{ selectedKecamatan.name }}</span>
          </button>
        </aside>
      </div>
    </div>
  </section>
</template>

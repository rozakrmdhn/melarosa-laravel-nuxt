<script setup lang="ts">
import { computed, ref } from 'vue';
import { KECAMATAN_ENTRIES, type KecamatanEntry } from '~/constants/kecamatan';

type ZonaKey = 'SEMUA' | 'Kota' | 'Timur' | 'Tengah' | 'Selatan' | 'Barat';

const selectedZona = ref<ZonaKey>('SEMUA');
const searchQuery = ref('');
const selectedKecamatan = ref<KecamatanEntry>(
  KECAMATAN_ENTRIES.find((k) => k.name === 'Bojonegoro') || KECAMATAN_ENTRIES[0]!
);

const zonaList: Array<{ key: ZonaKey; label: string; count: number }> = [
  { key: 'SEMUA', label: 'Semua Wilayah', count: KECAMATAN_ENTRIES.length },
  { key: 'Kota', label: 'Kawasan Perkotaan', count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Kota').length },
  { key: 'Timur', label: 'Timur & Tenggara', count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Timur' || k.zona === 'Tenggara').length },
  { key: 'Tengah', label: 'Tengah', count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Tengah').length },
  { key: 'Selatan', label: 'Selatan', count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Selatan').length },
  { key: 'Barat', label: 'Barat', count: KECAMATAN_ENTRIES.filter((k) => k.zona === 'Barat').length },
];

const filteredList = computed(() => {
  let list = KECAMATAN_ENTRIES;

  if (selectedZona.value === 'Timur') {
    list = list.filter((k) => k.zona === 'Timur' || k.zona === 'Tenggara');
  } else if (selectedZona.value !== 'SEMUA') {
    list = list.filter((k) => k.zona === selectedZona.value);
  }

  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter((k) =>
    k.name.toLowerCase().includes(q) ||
    k.desc.toLowerCase().includes(q) ||
    k.zona.toLowerCase().includes(q)
  );
});

function selectDistrict(kec: KecamatanEntry) {
  selectedKecamatan.value = kec;
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

function formatCoordinates(coord: [number, number]): string {
  const [lng, lat] = coord;
  const latDir = lat < 0 ? 'LS' : 'LU';
  const lngDir = lng >= 0 ? 'BT' : 'BB';
  return `${Math.abs(lat).toFixed(4)}° ${latDir}, ${Math.abs(lng).toFixed(4)}° ${lngDir}`;
}
</script>

<template>
  <section
    id="jelajah-wilayah"
    aria-labelledby="explorer-heading"
    class="relative w-full bg-[#F7F8F5] dark:bg-[#070b14] py-16 sm:py-20 lg:py-24 border-b border-[#DFE5DE] dark:border-white/10 scroll-mt-24"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <!-- Section Header Editorial -->
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-[#DFE5DE] dark:border-white/10 pb-6">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-[#176B4D] dark:text-emerald-400">
            Direktori Atlas Wilayah
          </p>
          <h2
            id="explorer-heading"
            class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[#17251F] dark:text-white"
          >
            Telusuri 28 Kecamatan Bojonegoro
          </h2>
          <p class="mt-2 text-sm sm:text-base text-[#647067] dark:text-slate-300 max-w-2xl leading-relaxed">
            Kenali batas wilayah dan jaringan jalan di sekitar tempat tinggal Anda. Pilih kecamatan untuk meninjau titik koordinat dan langsung menjelajahinya di peta.
          </p>
        </div>

        <!-- Kolom Pencarian Kecamatan -->
        <div class="w-full lg:w-80">
          <div class="relative flex items-center">
            <UIcon
              name="i-lucide-search"
              class="pointer-events-none absolute left-3.5 size-4 text-slate-400"
            />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari kecamatan..."
              aria-label="Cari nama kecamatan di Bojonegoro"
              class="w-full rounded-xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] pl-10 pr-9 py-2.5 text-sm text-[#17251F] dark:text-white placeholder-[#647067] dark:placeholder-slate-400 focus:border-blue-600 dark:focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-600/15 min-h-[44px]"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2.5 size-7 rounded-lg flex items-center justify-center text-[#647067] hover:text-[#17251F] dark:hover:text-white cursor-pointer"
              aria-label="Bersihkan pencarian"
              @click="searchQuery = ''"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Filter Kategori Zona Wilayah: Gaya Netral Editorial (Bukan Blok Hijau Pekat) -->
      <div
        role="tablist"
        aria-label="Filter Wilayah Zona Bojonegoro"
        class="mt-6 flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none"
      >
        <button
          v-for="zona in zonaList"
          :key="zona.key"
          type="button"
          role="tab"
          :aria-selected="selectedZona === zona.key"
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs sm:text-sm font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          :class="
            selectedZona === zona.key
              ? 'bg-[#17251F] text-white dark:bg-white dark:text-slate-900 shadow-xs'
              : 'border border-[#DFE5DE] dark:border-white/10 bg-white dark:bg-[#0c1522] text-[#17251F] dark:text-slate-300 hover:border-slate-400 dark:hover:border-slate-500'
          "
          @click="selectedZona = zona.key"
        >
          <span>{{ zona.label }}</span>
          <span
            class="rounded-full px-2 py-0.5 text-[11px] tabular-nums"
            :class="
              selectedZona === zona.key
                ? 'bg-white/20 dark:bg-slate-200 text-white dark:text-slate-900 font-bold'
                : 'bg-[#F7F8F5] dark:bg-white/10 text-[#647067] dark:text-slate-400'
            "
          >
            {{ zona.count }}
          </span>
        </button>
      </div>

      <!-- Layout Dua Kolom Editorial: Daftar Atlas (Kiri 7 Kolom) vs Panel Pratinjau (Kanan 5 Kolom) -->
      <div class="mt-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Kolom Kiri: Daftar Kecamatan Bernomor -->
        <div class="lg:col-span-7">
          <div v-if="filteredList.length > 0" class="divide-y divide-[#DFE5DE] dark:divide-white/10 rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] overflow-hidden shadow-xs">
            <button
              v-for="(kec, idx) in filteredList"
              :key="kec.name"
              type="button"
              class="w-full text-left p-4 sm:px-5 sm:py-4 flex items-center justify-between gap-4 transition-colors cursor-pointer min-h-[64px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
              :class="
                selectedKecamatan.name === kec.name
                  ? 'bg-blue-50/70 dark:bg-blue-950/30 border-l-3 border-blue-600 dark:border-blue-400'
                  : 'hover:bg-[#F7F8F5] dark:hover:bg-white/5'
              "
              @click="selectDistrict(kec)"
            >
              <div class="flex items-center gap-3.5 min-w-0">
                <span class="w-6 text-xs font-mono font-bold text-[#647067] dark:text-slate-400 tabular-nums shrink-0">
                  {{ String(idx + 1).padStart(2, '0') }}
                </span>
                <div class="min-w-0">
                  <h3
                    class="text-base font-bold truncate transition-colors"
                    :class="selectedKecamatan.name === kec.name ? 'text-blue-700 dark:text-blue-400' : 'text-[#17251F] dark:text-white'"
                  >
                    Kecamatan {{ kec.name }}
                  </h3>
                  <p class="text-xs text-[#647067] dark:text-slate-400 truncate">
                    {{ kec.desc }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-md border border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5] dark:bg-white/5 text-[#17251F] dark:text-slate-300">
                  Zona {{ kec.zona }}
                </span>
                <UIcon
                  name="i-lucide-chevron-right"
                  class="size-4 text-[#647067] dark:text-slate-400"
                  :class="selectedKecamatan.name === kec.name ? 'text-blue-600 dark:text-blue-400' : ''"
                />
              </div>
            </button>
          </div>

          <!-- Empty State (R-27) -->
          <div
            v-else
            class="rounded-2xl border border-dashed border-[#DFE5DE] dark:border-white/20 bg-white dark:bg-[#0c1522] p-10 text-center space-y-3"
          >
            <UIcon name="i-lucide-map-pin-off" class="size-8 text-[#647067] dark:text-slate-400 mx-auto" />
            <h3 class="text-base font-bold text-[#17251F] dark:text-white">
              Kecamatan Tidak Ditemukan
            </h3>
            <p class="text-xs text-[#647067] dark:text-slate-400 max-w-sm mx-auto">
              Tidak ada hasil yang sesuai dengan pencarian "{{ searchQuery }}" pada zona {{ selectedZona }}.
            </p>
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-4 py-2.5 text-xs font-semibold text-white transition-all cursor-pointer min-h-[44px]"
              @click="resetFilters"
            >
              <span>Tampilkan Semua 28 Kecamatan</span>
            </button>
          </div>
        </div>

        <!-- Kolom Kanan: Panel Pratinjau Atlas Interaktif (5 Kolom) -->
        <aside
          aria-label="Pratinjau Atlas Kecamatan Terpilih"
          class="lg:col-span-5 rounded-2xl border border-[#DFE5DE] dark:border-white/15 bg-white dark:bg-[#0c1522] p-6 shadow-md sticky top-28 space-y-5"
        >
          <div class="border-b border-[#DFE5DE] dark:border-white/10 pb-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                Wilayah Terpilih
              </span>
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300">
                Zona {{ selectedKecamatan.zona }}
              </span>
            </div>
            <h3 class="mt-2 text-2xl font-bold text-[#17251F] dark:text-white">
              Kecamatan {{ selectedKecamatan.name }}
            </h3>
            <p class="mt-1 text-xs text-[#647067] dark:text-slate-400 leading-relaxed">
              {{ selectedKecamatan.desc }} (Kabupaten Bojonegoro)
            </p>
          </div>

          <!-- Pratinjau Kartografis Mini Kecamatan -->
          <div class="rounded-xl border border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5] dark:bg-[#080e18] p-4 text-center">
            <svg
              viewBox="0 0 320 120"
              class="w-full h-28 rounded-lg bg-white dark:bg-[#060b13] border border-[#DFE5DE] dark:border-white/5"
              role="img"
              :aria-label="`Sketsa batas wilayah dan jaringan jalan Kecamatan ${selectedKecamatan.name}`"
            >
              <polygon
                points="50,25 140,15 260,30 290,95 210,105 90,105 30,65"
                fill="rgba(37, 99, 235, 0.06)"
                stroke="#2563EB"
                stroke-width="1.8"
                stroke-dasharray="4 2"
              />
              <path
                d="M 25,65 Q 120,60 170,55 T 295,45"
                fill="none"
                stroke="#2563EB"
                stroke-width="2.5"
                stroke-linecap="round"
              />
              <path
                d="M 120,20 L 170,55 L 210,105"
                fill="none"
                stroke="#10B981"
                stroke-width="2"
                stroke-linecap="round"
              />
              <circle cx="170" cy="55" r="7" fill="rgba(37, 99, 235, 0.2)" />
              <circle cx="170" cy="55" r="4" fill="#2563EB" stroke="#FFFFFF" stroke-width="1.5" />
            </svg>

            <dl class="mt-3.5 grid grid-cols-2 gap-2 text-left text-xs tabular-nums">
              <div class="rounded-lg bg-white dark:bg-white/5 p-2.5 border border-[#DFE5DE] dark:border-white/5">
                <dt class="text-[11px] text-[#647067] dark:text-slate-400">Titik Pusat Koordinat</dt>
                <dd class="mt-0.5 font-semibold text-[#17251F] dark:text-white">
                  {{ formatCoordinates(selectedKecamatan.coordinate) }}
                </dd>
              </div>
              <div class="rounded-lg bg-white dark:bg-white/5 p-2.5 border border-[#DFE5DE] dark:border-white/5">
                <dt class="text-[11px] text-[#647067] dark:text-slate-400">Skala Peta Default</dt>
                <dd class="mt-0.5 font-semibold text-blue-600 dark:text-blue-400">
                  Level Zoom {{ selectedKecamatan.zoom }}x
                </dd>
              </div>
            </dl>
          </div>

          <!-- Tindakan Nyata Membuka Peta Kecamatan: Biru Peta #2563EB -->
          <div class="space-y-2 pt-1">
            <button
              type="button"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition-all cursor-pointer min-h-[46px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
              @click="openDistrictInMap(selectedKecamatan)"
            >
              <UIcon name="i-lucide-map" class="size-4" />
              <span>Lihat di Peta: Kec. {{ selectedKecamatan.name }}</span>
            </button>

            <p class="text-center text-[11px] text-[#647067] dark:text-slate-400">
              Membuka peta dengan batas desa dan ruas jalan poros kecamatan terpilih.
            </p>
          </div>
        </aside>
      </div>
    </div>
  </section>
</template>

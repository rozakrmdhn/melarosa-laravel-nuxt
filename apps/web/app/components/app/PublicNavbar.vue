<script lang="ts" setup>
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const isMobileMenuOpen = ref(false);

const userItems = computed(() => [
  [{ slot: "overview" }],
  [{ label: "Dashboard", to: "/admin", icon: "i-heroicons-squares-2x2" }],
  [
    { label: "Account", to: "/admin/account/general", icon: "i-heroicons-user" },
    { label: "Devices", to: "/admin/account/devices", icon: "i-heroicons-device-phone-mobile" },
  ],
  [{ label: "Sign out", onSelect() { auth.logout(); }, class: "cursor-pointer", icon: "i-heroicons-arrow-left-on-rectangle" }],
]);

const { navTree, isPathActive } = usePublicNavigation();

// Scroll state for glass intensity
const scrollY = ref(0);
const isScrolled = computed(() => scrollY.value > 40);

function handleScroll() {
  scrollY.value = window.scrollY;
}

// ═══ SEARCH FEATURE WITH ELONGATING NAVBAR ANIMATION ════════════════════════
const isSearchOpen = ref(false);
const searchQuery = ref("");
const selectedIndex = ref(0);
const desktopSearchInputRef = ref<HTMLInputElement | null>(null);
const mobileSearchInputRef = ref<HTMLInputElement | null>(null);
const desktopNavRef = ref<HTMLElement | null>(null);
const mobileSearchRef = ref<HTMLElement | null>(null);

interface SearchItem {
  id: string;
  title: string;
  category: "kecamatan" | "halaman" | "koordinat";
  desc: string;
  icon: string;
  to?: string;
  coordinate?: [number, number]; // [lng, lat]
  zoom?: number;
}

// 28 Kecamatan resmi Kabupaten Bojonegoro dengan centroid WGS 84
const KECAMATAN_ITEMS: SearchItem[] = [
  { id: "k-1", title: "Kecamatan Bojonegoro (Kota)", category: "kecamatan", desc: "Pusat Pemerintahan & Perkotaan", icon: "i-lucide-map-pin", coordinate: [111.8817, -7.1502], zoom: 14 },
  { id: "k-2", title: "Kecamatan Kapas", category: "kecamatan", desc: "Wilayah Timur Bojonegoro", icon: "i-lucide-map-pin", coordinate: [111.9167, -7.1833], zoom: 13 },
  { id: "k-3", title: "Kecamatan Balen", category: "kecamatan", desc: "Wilayah Jalur Utama Timur", icon: "i-lucide-map-pin", coordinate: [111.9667, -7.1667], zoom: 13 },
  { id: "k-4", title: "Kecamatan Sumberrejo", category: "kecamatan", desc: "Kawasan Sentra Padi & Perdagangan", icon: "i-lucide-map-pin", coordinate: [112.0167, -7.1500], zoom: 13 },
  { id: "k-5", title: "Kecamatan Kanor", category: "kecamatan", desc: "Kawasan Bantaran Bengawan Solo", icon: "i-lucide-map-pin", coordinate: [112.0333, -7.1000], zoom: 13 },
  { id: "k-6", title: "Kecamatan Baureno", category: "kecamatan", desc: "Gerbang Timur Perbatasan Lamongan", icon: "i-lucide-map-pin", coordinate: [112.1000, -7.1333], zoom: 13 },
  { id: "k-7", title: "Kecamatan Kepohbaru", category: "kecamatan", desc: "Wilayah Tenggara Bojonegoro", icon: "i-lucide-map-pin", coordinate: [112.0667, -7.2167], zoom: 13 },
  { id: "k-8", title: "Kecamatan Kedungadem", category: "kecamatan", desc: "Wilayah Selatan Perbatasan Nganjuk", icon: "i-lucide-map-pin", coordinate: [112.0167, -7.2833], zoom: 13 },
  { id: "k-9", title: "Kecamatan Sugihwaras", category: "kecamatan", desc: "Wilayah Pertanian & Perkebunan", icon: "i-lucide-map-pin", coordinate: [111.9500, -7.3000], zoom: 13 },
  { id: "k-10", title: "Kecamatan Temayang", category: "kecamatan", desc: "Wilayah Waduk Pacal & Hutan", icon: "i-lucide-map-pin", coordinate: [111.8833, -7.3167], zoom: 13 },
  { id: "k-11", title: "Kecamatan Dander", category: "kecamatan", desc: "Kawasan Wisata Kayangan Api & Hutan", icon: "i-lucide-map-pin", coordinate: [111.8500, -7.2333], zoom: 13 },
  { id: "k-12", title: "Kecamatan Bubulan", category: "kecamatan", desc: "Wilayah Dataran Tinggi Selatan", icon: "i-lucide-map-pin", coordinate: [111.8000, -7.3333], zoom: 13 },
  { id: "k-13", title: "Kecamatan Gondang", category: "kecamatan", desc: "Kawasan Hutan Jati Perbatasan", icon: "i-lucide-map-pin", coordinate: [111.8167, -7.4167], zoom: 13 },
  { id: "k-14", title: "Kecamatan Sekar", category: "kecamatan", desc: "Wilayah Pegunungan Kendeng Selatan", icon: "i-lucide-map-pin", coordinate: [111.7500, -7.4500], zoom: 13 },
  { id: "k-15", title: "Kecamatan Ngasem", category: "kecamatan", desc: "Wilayah Hutan & Wisata Geopark", icon: "i-lucide-map-pin", coordinate: [111.7667, -7.2333], zoom: 13 },
  { id: "k-16", title: "Kecamatan Ngambon", category: "kecamatan", desc: "Kawasan Hijau Perbukitan", icon: "i-lucide-map-pin", coordinate: [111.7167, -7.2833], zoom: 13 },
  { id: "k-17", title: "Kecamatan Tambakrejo", category: "kecamatan", desc: "Wilayah Hutan Barat Daya", icon: "i-lucide-map-pin", coordinate: [111.6500, -7.2333], zoom: 13 },
  { id: "k-18", title: "Kecamatan Purwosari", category: "kecamatan", desc: "Kawasan Minyak & Pertanian", icon: "i-lucide-map-pin", coordinate: [111.6667, -7.1667], zoom: 13 },
  { id: "k-19", title: "Kecamatan Padangan", category: "kecamatan", desc: "Kawasan Perkotaan Perbatasan Cepu", icon: "i-lucide-map-pin", coordinate: [111.6167, -7.1500], zoom: 13 },
  { id: "k-20", title: "Kecamatan Kasiman", category: "kecamatan", desc: "Wilayah Barat Bengawan Solo", icon: "i-lucide-map-pin", coordinate: [111.6000, -7.1000], zoom: 13 },
  { id: "k-21", title: "Kecamatan Kedewan", category: "kecamatan", desc: "Wisata Geopark Sumur Minyak Tua", icon: "i-lucide-map-pin", coordinate: [111.5833, -7.0333], zoom: 13 },
  { id: "k-22", title: "Kecamatan Malo", category: "kecamatan", desc: "Kawasan Kerajinan Gerabah & Hutan", icon: "i-lucide-map-pin", coordinate: [111.7000, -7.1000], zoom: 13 },
  { id: "k-23", title: "Kecamatan Kalitidu", category: "kecamatan", desc: "Kawasan Industri Migas Banyu Urip", icon: "i-lucide-map-pin", coordinate: [111.7667, -7.1333], zoom: 13 },
  { id: "k-24", title: "Kecamatan Trucuk", category: "kecamatan", desc: "Wilayah Utara Jembatan Sosrodilogo", icon: "i-lucide-map-pin", coordinate: [111.8667, -7.1333], zoom: 13 },
  { id: "k-25", title: "Kecamatan Gayam", category: "kecamatan", desc: "Pusat Blok Cepu ExxonMobil", icon: "i-lucide-map-pin", coordinate: [111.7167, -7.1833], zoom: 13 },
  { id: "k-26", title: "Kecamatan Margomulyo", category: "kecamatan", desc: "Kawasan Samin & Perbatasan Ngawi", icon: "i-lucide-map-pin", coordinate: [111.5333, -7.2833], zoom: 13 },
  { id: "k-27", title: "Kecamatan Ngraho", category: "kecamatan", desc: "Wilayah Selatan Jalur Ngawi-Bojonegoro", icon: "i-lucide-map-pin", coordinate: [111.5667, -7.2333], zoom: 13 },
  { id: "k-28", title: "Kecamatan Sukosewu", category: "kecamatan", desc: "Wilayah Pertanian Tengah Bojonegoro", icon: "i-lucide-map-pin", coordinate: [111.9333, -7.2333], zoom: 13 },
];

// Navigasi halaman publik portal
const PAGE_ITEMS: SearchItem[] = [
  { id: "page-beranda", title: "Beranda Utama", category: "halaman", desc: "Halaman pengantar portal geospasial", icon: "i-lucide-compass", to: "/" },
  { id: "page-maps", title: "Peta Interaktif", category: "halaman", desc: "Eksplorasi peta spasial, basemap, dan layer jalan poros", icon: "i-lucide-map", to: "/maps" },
  { id: "page-geostory", title: "GeoStory Bojonegoro", category: "halaman", desc: "Narasi spasial tematik sejarah dan wilayah", icon: "i-lucide-book-open", to: "/geostory" },
  { id: "page-katalog", title: "Katalog Data Spasial", category: "halaman", desc: "Daftar dataset shapefile, batas desa, dan jaringan jalan", icon: "i-lucide-database", to: "/katalog-data" },
];

import { parseCoordinateString } from "~/utils/gis-helpers";

// Deteksi koordinat WGS 84 (format Desimal DD atau DMS)
const coordinateMatch = computed<SearchItem | null>(() => {
  const parsed = parseCoordinateString(searchQuery.value);
  if (!parsed) return null;

  return {
    id: "coord-custom",
    title: `Koordinat: ${parsed.formatted}`,
    category: "koordinat",
    desc: "Lompat langsung ke titik koordinat WGS 84",
    icon: "i-lucide-crosshair",
    coordinate: [parsed.lng, parsed.lat],
    zoom: 16,
  };
});

// Rekomendasi awal saat kotak pencarian masih kosong
const defaultSuggestions = computed<SearchItem[]>(() => [
  KECAMATAN_ITEMS[0], // Bojonegoro Kota
  KECAMATAN_ITEMS[10], // Dander
  KECAMATAN_ITEMS[9], // Temayang
  PAGE_ITEMS[1], // Peta Interaktif
  PAGE_ITEMS[3], // Katalog Data
]);

// Hasil pencarian terfilter
const searchResults = computed<SearchItem[]>(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) {
    return defaultSuggestions.value;
  }

  const results: SearchItem[] = [];

  // Jika input sesuai format koordinat, prioritaskan di urutan teratas
  if (coordinateMatch.value) {
    results.push(coordinateMatch.value);
  }

  // Filter Kecamatan Bojonegoro
  const filteredKecamatan = KECAMATAN_ITEMS.filter((item) =>
    item.title.toLowerCase().includes(q) || item.desc.toLowerCase().includes(q)
  );
  results.push(...filteredKecamatan);

  // Filter Halaman Portal
  const filteredPages = PAGE_ITEMS.filter((item) =>
    item.title.toLowerCase().includes(q) || item.desc.toLowerCase().includes(q)
  );
  results.push(...filteredPages);

  return results;
});

function openSearch() {
  isSearchOpen.value = true;
  selectedIndex.value = 0;
  nextTick(() => {
    if (typeof window !== "undefined" && window.innerWidth >= 1024) {
      desktopSearchInputRef.value?.focus();
    } else {
      mobileSearchInputRef.value?.focus();
    }
  });
}

function closeSearch() {
  isSearchOpen.value = false;
  searchQuery.value = "";
  selectedIndex.value = 0;
}

function handleSelectResult(item: SearchItem) {
  closeSearch();
  if (item.category === "halaman" && item.to) {
    navigateTo(item.to);
  } else if (item.coordinate) {
    const [lng, lat] = item.coordinate;
    const z = item.zoom || 14;
    const encodedSearch = encodeURIComponent(item.title);

    let basemapParam = "";
    if (import.meta.client) {
      try {
        const saved = localStorage.getItem("melarosa_active_basemap");
        if (saved && saved !== "osm") {
          basemapParam = `&basemap=${saved}`;
        }
      } catch {}
    }

    navigateTo(`/maps/@${lat.toFixed(5)},${lng.toFixed(5)},${z}z?search=${encodedSearch}${basemapParam}`);
  }
}

function navigateResults(direction: 1 | -1) {
  const len = searchResults.value.length;
  if (len === 0) return;
  selectedIndex.value = (selectedIndex.value + direction + len) % len;
}

function selectActiveResult() {
  const item = searchResults.value[selectedIndex.value];
  if (item) {
    handleSelectResult(item);
  }
}

// Reset selected index saat query berubah
watch(searchQuery, () => {
  selectedIndex.value = 0;
});

// Shortcut keyboard global: Ctrl+K / Cmd+K untuk buka pencarian, Escape untuk tutup
function handleGlobalKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
    e.preventDefault();
    if (isSearchOpen.value) {
      closeSearch();
    } else {
      openSearch();
    }
  } else if (e.key === "Escape" && isSearchOpen.value) {
    e.preventDefault();
    closeSearch();
  }
}

// Deteksi klik di luar navbar untuk menutup pencarian secara otomatis
function handleClickOutside(e: MouseEvent) {
  if (!isSearchOpen.value) return;
  const target = e.target as Node;
  const inDesktop = desktopNavRef.value?.contains(target);
  const inMobile = mobileSearchRef.value?.contains(target);
  if (!inDesktop && !inMobile) {
    closeSearch();
  }
}

onMounted(() => {
  window.addEventListener("scroll", handleScroll, { passive: true });
  window.addEventListener("keydown", handleGlobalKeydown);
  document.addEventListener("pointerdown", handleClickOutside);
});

onUnmounted(() => {
  window.removeEventListener("scroll", handleScroll);
  window.removeEventListener("keydown", handleGlobalKeydown);
  document.removeEventListener("pointerdown", handleClickOutside);
});
</script>

<template>
  <!-- ═══ DESKTOP FLOATING NAV (lg+) ═══════════════════════════════════════ -->
  <div class="pointer-events-none fixed inset-x-0 top-0 z-50 hidden justify-center pt-5 lg:flex">
    <nav
      ref="desktopNavRef"
      aria-label="Navigasi Utama"
      class="pointer-events-auto relative flex items-center rounded-2xl transition-all duration-500 ease-out"
      :class="[
        isScrolled
          ? 'bg-white border border-slate-300/80 shadow-lg shadow-slate-900/8 dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none'
          : 'bg-white border border-slate-200/80 shadow-md shadow-slate-900/5 dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none',
        isSearchOpen
          ? 'px-4 py-2.5 shadow-2xl ring-1 ring-blue-500/20 dark:ring-white/20'
          : 'px-5 py-2.5 gap-1.5'
      ]"
    >
      <!-- Logo -->
      <NuxtLink
        to="/"
        class="shrink-0 flex items-center mr-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-xl"
        aria-label="Melarosa GIS"
        @click="closeSearch"
      >
        <AppLogo />
      </NuxtLink>

      <!-- Divider kiri -->
      <span class="mx-2 h-5 w-px bg-slate-200 dark:bg-white/15 shrink-0" aria-hidden="true" />

      <!-- ─── NAV LINKS (Selalu tampil, tidak pernah tertutup oleh pencarian) ─── -->
      <div class="flex items-center gap-1 shrink-0">
        <NuxtLink
          v-for="link in navTree"
          :key="link.to"
          :to="link.to"
          class="rounded-xl px-3.5 py-2 text-[0.88rem] transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 shrink-0"
          :class="isPathActive(link.to, link.exact)
            ? 'text-blue-600 bg-blue-50 font-semibold dark:text-white dark:bg-transparent dark:shadow-none dark:font-semibold'
            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-normal dark:text-slate-400 dark:hover:text-white dark:hover:bg-transparent'"
        >
          {{ link.label }}
        </NuxtLink>
      </div>

      <!-- Divider tengah pemisah menu dan search -->
      <span class="mx-2 h-5 w-px bg-slate-200 dark:bg-white/15 shrink-0" aria-hidden="true" />

      <!-- ─── SEARCH CONTAINER (Melebar secara animasi dan memperlebar navbar) ─── -->
      <div
        class="relative flex items-center transition-[width,background-color,border-color,box-shadow] duration-500 ease-out rounded-xl overflow-hidden"
        :class="isSearchOpen
          ? 'w-72 sm:w-80 lg:w-[340px] xl:w-[380px] bg-slate-100/90 dark:bg-white/10 px-3 py-1.5 ring-1 ring-blue-500/30 dark:ring-white/20'
          : 'w-28 sm:w-32 bg-slate-100 hover:bg-slate-200/80 dark:bg-white/5 dark:hover:bg-white/10 px-3 py-1.5 cursor-pointer border border-slate-200/60 dark:border-white/5'"
      >
        <!-- Expanded search box -->
        <div
          v-if="isSearchOpen"
          class="w-full flex items-center gap-2 animate-in fade-in duration-200"
        >
          <UIcon name="i-lucide-search" class="size-4 text-blue-500 shrink-0" />
          <input
            ref="desktopSearchInputRef"
            v-model="searchQuery"
            type="text"
            placeholder="Cari kecamatan, koordinat..."
            class="w-full bg-transparent text-xs sm:text-sm text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-0 border-0 p-0"
            @keydown.down.prevent="navigateResults(1)"
            @keydown.up.prevent="navigateResults(-1)"
            @keydown.enter.prevent="selectActiveResult"
            @keydown.esc.prevent="closeSearch"
          />

          <!-- Reset query button -->
          <button
            v-if="searchQuery"
            type="button"
            class="size-5 flex items-center justify-center rounded-md text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-200/60 dark:hover:bg-white/10 transition-colors cursor-pointer shrink-0"
            aria-label="Hapus teks pencarian"
            @click="searchQuery = ''; desktopSearchInputRef?.focus()"
          >
            <UIcon name="i-lucide-x" class="size-3" />
          </button>

          <!-- Close / Escape button -->
          <button
            type="button"
            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md text-[11px] font-medium text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-200/70 hover:bg-slate-300/80 dark:bg-white/10 dark:hover:bg-white/20 transition-all cursor-pointer shrink-0"
            aria-label="Tutup pencarian"
            @click.stop="closeSearch"
          >
            <span>Esc</span>
            <UIcon name="i-lucide-x" class="size-2.5" />
          </button>
        </div>

        <!-- Collapsed search trigger button -->
        <button
          v-else
          type="button"
          class="w-full flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer focus-visible:outline-none"
          aria-label="Buka pencarian fitur dan wilayah (Ctrl K)"
          @click="openSearch"
        >
          <div class="flex items-center gap-1.5">
            <UIcon name="i-lucide-search" class="size-3.5 text-slate-400" />
            <span class="font-medium">Cari...</span>
          </div>
          <kbd class="hidden xl:inline-flex items-center px-1.5 py-0.5 text-[10px] font-mono rounded bg-white dark:bg-white/10 text-slate-500 dark:text-slate-300 border border-slate-200 dark:border-white/10 shadow-2xs">Ctrl K</kbd>
        </button>
      </div>

      <!-- Divider kanan sebelum controls -->
      <span class="mx-2 h-5 w-px bg-slate-200 dark:bg-white/15 shrink-0" aria-hidden="true" />

      <!-- Right controls -->
      <div class="flex items-center gap-2 ml-auto shrink-0">
        <UColorModeButton
          size="sm"
          class="rounded-xl border border-transparent dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:text-white dark:hover:bg-white/10 transition-all"
        />

        <UDropdownMenu
          v-if="auth.logged"
          :items="userItems"
          :content="{ side: 'bottom', align: 'end' }"
        >
          <ULink class="cursor-pointer">
            <UAvatar
              icon="i-heroicons-user"
              class="rounded-lg"
              size="sm"
              :src="$storage(auth.user.avatar)"
              :alt="auth.user.name"
            />
          </ULink>
          <template #overview>
            <div class="text-left">
              <p class="text-xs text-gray-500">Masuk sebagai</p>
              <p class="truncate font-semibold text-neutral-900 dark:text-white">{{ auth.user.email }}</p>
            </div>
          </template>
        </UDropdownMenu>

        <NuxtLink
          v-else
          to="/auth/login"
          class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-[0.9rem] font-semibold transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-sm shadow-blue-600/25 dark:bg-blue-500/20 dark:border dark:border-blue-400/30 dark:text-blue-300 dark:hover:bg-blue-500/30 dark:hover:text-white dark:shadow-none"
        >
          Masuk
        </NuxtLink>
      </div>

      <!-- ─── DESKTOP SEARCH DROPDOWN SUGGESTIONS ─────────────────────── -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2 scale-[0.99]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-2 scale-[0.99]"
      >
        <div
          v-if="isSearchOpen"
          class="absolute top-[calc(100%+8px)] right-0 w-[480px] sm:w-[540px] max-w-[92vw] rounded-2xl overflow-hidden border shadow-2xl backdrop-blur-xl z-50 bg-white/95 border-slate-200/90 shadow-slate-900/10 dark:bg-[#09111e]/95 dark:border-white/15 dark:shadow-black/70"
        >
          <!-- Header info / hint -->
          <div class="flex items-center justify-between px-4 py-2 border-b border-slate-100 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 text-xs text-slate-500 dark:text-slate-400">
            <span class="font-medium">
              {{ searchQuery ? `Hasil untuk "${searchQuery}" (${searchResults.length})` : 'Rekomendasi pencarian geospasial' }}
            </span>
            <span class="text-[11px] text-slate-400 dark:text-slate-500">Gunakan panah ↑↓ untuk navigasi</span>
          </div>

          <!-- Results list -->
          <div class="max-h-[360px] overflow-y-auto overscroll-contain p-2 space-y-1">
            <template v-if="searchResults.length > 0">
              <button
                v-for="(item, idx) in searchResults"
                :key="item.id"
                type="button"
                class="w-full flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl text-left transition-colors cursor-pointer"
                :class="selectedIndex === idx
                  ? 'bg-blue-50 text-blue-900 dark:bg-white/15 dark:text-white font-medium ring-1 ring-blue-500/20 dark:ring-white/20'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10'"
                @click="handleSelectResult(item)"
                @mouseenter="selectedIndex = idx"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <div
                    class="size-8 rounded-lg flex items-center justify-center shrink-0"
                    :class="item.category === 'kecamatan'
                      ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300'
                      : item.category === 'koordinat'
                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'"
                  >
                    <UIcon :name="item.icon" class="size-4" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold truncate">{{ item.title }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ item.desc }}</p>
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <span
                    class="text-[10px] uppercase font-mono px-2 py-0.5 rounded-md border"
                    :class="item.category === 'kecamatan'
                      ? 'bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-500/10 dark:border-blue-400/20 dark:text-blue-300'
                      : item.category === 'koordinat'
                        ? 'bg-emerald-50 border-emerald-200 text-emerald-600 dark:bg-emerald-500/10 dark:border-emerald-400/20 dark:text-emerald-300'
                        : 'bg-slate-100 border-slate-200 text-slate-600 dark:bg-white/10 dark:border-white/10 dark:text-slate-300'"
                  >
                    {{ item.category }}
                  </span>
                  <UIcon name="i-lucide-corner-down-left" class="size-3.5 text-slate-400" />
                </div>
              </button>
            </template>

            <!-- Empty state -->
            <div v-else class="py-8 px-4 text-center">
              <div class="size-10 rounded-full bg-slate-100 dark:bg-white/5 flex items-center justify-center mx-auto mb-2 text-slate-400">
                <UIcon name="i-lucide-search-x" class="size-5" />
              </div>
              <p class="text-sm font-semibold text-slate-800 dark:text-white">Tidak ditemukan hasil</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Coba cari nama kecamatan di Bojonegoro (contoh: Kapas, Dander, Temayang) atau masukkan format koordinat WGS 84.
              </p>
            </div>
          </div>

          <!-- Footer Bar -->
          <div class="flex items-center justify-between px-4 py-2 border-t border-slate-100 dark:border-white/10 bg-slate-50/50 dark:bg-white/5 text-[11px] text-slate-400 dark:text-slate-500">
            <span>28 Kecamatan terindeks</span>
            <div class="flex items-center gap-3">
              <span>↵ Pilih</span>
              <span>Esc Tutup</span>
            </div>
          </div>
        </div>
      </Transition>
    </nav>
  </div>

  <!-- ═══ MOBILE FLOATING NAV (below lg) ═══════════════════════════════════ -->
  <div class="pointer-events-none fixed inset-x-0 top-0 z-50 lg:hidden">
    <!-- Backdrop Overlay on Mobile when Search is Open -->
    <Transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isSearchOpen"
        class="pointer-events-auto fixed inset-0 bg-slate-900/40 dark:bg-black/60 backdrop-blur-xs z-10"
        aria-hidden="true"
        @click="closeSearch"
      />
    </Transition>

    <!-- Nav container with proper padding -->
    <div class="relative z-20 px-4 pt-4">
      <!-- Default Collapsed Mobile Bar -->
      <Transition
        enter-active-class="transition-all duration-250 ease-out delay-75"
        enter-from-class="opacity-0 -translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-2 scale-95"
      >
        <div
          v-if="!isSearchOpen"
          class="flex items-center justify-between"
        >
          <!-- Logo pill (left) -->
          <NuxtLink
            to="/"
            aria-label="Melarosa GIS"
            class="pointer-events-auto flex items-center rounded-2xl px-4 py-2.5 transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            :class="isScrolled
              ? 'bg-white border border-slate-300/80 shadow-lg dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none'
              : 'bg-white border border-slate-200/80 shadow-md dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none'"
          >
            <AppLogo />
          </NuxtLink>

          <!-- Right controls (Search + Hamburger) -->
          <div class="pointer-events-auto flex items-center gap-2">
            <!-- Search Trigger Button on Mobile -->
            <button
              type="button"
              aria-label="Buka pencarian"
              class="flex items-center justify-center size-11 rounded-2xl transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
              :class="isScrolled
                ? 'bg-white border border-slate-300/80 shadow-lg text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none dark:text-slate-200 dark:hover:text-white'
                : 'bg-white border border-slate-200/80 shadow-md text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none dark:text-slate-200 dark:hover:text-white'"
              @click="openSearch"
            >
              <UIcon name="i-lucide-search" class="size-5" />
            </button>

            <!-- Hamburger button -->
            <button
              type="button"
              :aria-label="isMobileMenuOpen ? 'Tutup menu' : 'Buka menu navigasi'"
              :aria-expanded="isMobileMenuOpen"
              class="flex items-center justify-center size-11 rounded-2xl transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
              :class="isScrolled
                ? 'bg-white border border-slate-300/80 shadow-lg text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none dark:text-slate-200 dark:hover:text-white'
                : 'bg-white border border-slate-200/80 shadow-md text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none dark:text-slate-200 dark:hover:text-white'"
              @click="isMobileMenuOpen = !isMobileMenuOpen"
            >
              <UIcon
                :name="isMobileMenuOpen ? 'i-lucide-x' : 'i-lucide-menu'"
                class="size-5 transition-transform duration-200"
                :class="isMobileMenuOpen ? 'rotate-90' : 'rotate-0'"
              />
            </button>
          </div>
        </div>
      </Transition>

      <!-- Expanded Mobile Search Bar -->
      <Transition
        enter-active-class="transition-all duration-300 cubic-bezier(0.16, 1, 0.3, 1)"
        enter-from-class="opacity-0 -translate-y-4 scale-[0.98]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-4 scale-[0.98]"
      >
        <div
          v-if="isSearchOpen"
          ref="mobileSearchRef"
          class="pointer-events-auto absolute inset-x-4 top-4 rounded-2xl transition-all duration-300 ease-out border shadow-2xl overflow-hidden"
          :class="isScrolled
            ? 'bg-white/95 border-slate-300/80 shadow-xl dark:bg-[#09111e]/95 dark:backdrop-blur-2xl dark:border-white/15'
            : 'bg-white/95 border-slate-200/80 shadow-lg dark:bg-[#09111e]/95 dark:backdrop-blur-2xl dark:border-white/10'"
        >
          <div class="flex items-center gap-2 px-3 py-2.5">
            <UIcon name="i-lucide-search" class="size-4 text-blue-500 shrink-0 ml-1" />
            <input
              ref="mobileSearchInputRef"
              v-model="searchQuery"
              type="text"
              placeholder="Cari kecamatan, koordinat, atau halaman..."
              class="w-full bg-transparent text-sm text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-0 border-0 p-0"
              @keydown.enter.prevent="selectActiveResult"
              @keydown.esc.prevent="closeSearch"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="size-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white active:scale-95 transition-transform"
              aria-label="Hapus teks pencarian"
              @click="searchQuery = ''; mobileSearchInputRef?.focus()"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
            <button
              type="button"
              class="size-8 flex items-center justify-center rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-slate-100 dark:bg-white/10 active:scale-95 transition-transform"
              aria-label="Tutup pencarian"
              @click="closeSearch"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <!-- Mobile Suggestions Dropdown -->
          <div class="max-h-[60vh] overflow-y-auto overscroll-contain border-t border-slate-100 dark:border-white/10 p-2 space-y-1">
            <template v-if="searchResults.length > 0">
              <button
                v-for="(item, idx) in searchResults"
                :key="item.id"
                type="button"
                class="w-full min-h-[44px] flex items-center justify-between gap-3 px-3 py-2 rounded-xl text-left transition-colors cursor-pointer active:scale-[0.99]"
                :class="selectedIndex === idx
                  ? 'bg-blue-50 text-blue-900 dark:bg-white/15 dark:text-white font-medium'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10'"
                @click="handleSelectResult(item)"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <div
                    class="size-8 rounded-lg flex items-center justify-center shrink-0"
                    :class="item.category === 'kecamatan'
                      ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300'
                      : item.category === 'koordinat'
                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'"
                  >
                    <UIcon :name="item.icon" class="size-4" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold truncate">{{ item.title }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ item.desc }}</p>
                  </div>
                </div>
                <span
                  class="text-[10px] uppercase font-mono px-2 py-0.5 rounded-md border shrink-0"
                  :class="item.category === 'kecamatan'
                    ? 'bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-500/10 dark:border-blue-400/20 dark:text-blue-300'
                    : item.category === 'koordinat'
                      ? 'bg-emerald-50 border-emerald-200 text-emerald-600 dark:bg-emerald-500/10 dark:border-emerald-400/20 dark:text-emerald-300'
                      : 'bg-slate-100 border-slate-200 text-slate-600 dark:bg-white/10 dark:border-white/10 dark:text-slate-300'"
                >
                  {{ item.category }}
                </span>
              </button>
            </template>
            <div v-else class="py-6 px-3 text-center text-xs text-slate-500 dark:text-slate-400">
              Tidak ditemukan hasil untuk "{{ searchQuery }}"
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </div>

  <!-- ═══ MOBILE DRAWER ════════════════════════════════════════════════════ -->
  <USlideover
    v-model:open="isMobileMenuOpen"
    side="right"
    title="Menu Navigasi"
    description="Navigasi portal publik"
  >
    <template #body>
      <div class="flex flex-col gap-1.5 p-2">
        <!-- Quick Search Link in Drawer -->
        <button
          type="button"
          class="px-4 py-3 min-h-[44px] flex items-center gap-3 rounded-xl text-sm font-medium bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-white transition-all text-left cursor-pointer mb-1"
          @click="isMobileMenuOpen = false; openSearch()"
        >
          <UIcon name="i-lucide-search" class="size-4 text-blue-500" />
          <span>Pencarian Wilayah & Data...</span>
        </button>

        <NuxtLink
          v-for="link in navTree"
          :key="link.to"
          :to="link.to"
          class="px-4 py-3 min-h-[44px] flex items-center rounded-xl text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          :class="isPathActive(link.to, link.exact)
            ? 'text-blue-600 bg-blue-50/70 font-semibold dark:text-white dark:bg-white/10'
            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-white'"
          @click="isMobileMenuOpen = false"
        >
          {{ link.label }}
        </NuxtLink>

        <div class="mt-3 pt-3 border-t border-slate-200/70 dark:border-slate-800">
          <div v-if="auth.logged" class="px-4 py-2 space-y-1">
            <p class="text-xs text-slate-500">Masuk sebagai</p>
            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ auth.user?.email }}</p>
          </div>
          <NuxtLink
            v-else
            to="/auth/login"
            class="px-4 py-3 min-h-[44px] flex items-center justify-center rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-sm shadow-blue-600/25 transition-all"
            @click="isMobileMenuOpen = false"
          >
            Masuk sebagai Staf
          </NuxtLink>
        </div>
      </div>
    </template>
  </USlideover>
</template>


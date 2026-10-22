<script lang="ts" setup>
import { parseCoordinateString } from "~/utils/gis-helpers";

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

// Fitur pencarian navbar hanya aktif pada halaman utama beranda (/)
const isHomePage = computed(() => route.path === "/");

// ═══ SEARCH FEATURE (HANYA AKTIF PADA BERANDA /) ═════════════════════════════
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

// Deteksi koordinat WGS 84 (format Desimal DD atau DMS)
const coordinateMatch = computed<SearchItem | null>(() => {
  if (!isHomePage.value || !searchQuery.value) return null;
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
  if (!isHomePage.value) return [];
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
  if (!isHomePage.value) return;
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

const colorMode = useColorMode();

const mobileNavItems = [
  {
    label: "Beranda",
    desc: "Portal geospasial Kabupaten Bojonegoro",
    icon: "i-lucide-home",
    to: "/",
    exact: true,
  },
  {
    label: "Peta Interaktif",
    desc: "Batas wilayah, basemap, dan layer jalan poros",
    icon: "i-lucide-map",
    to: "/maps",
  },
  {
    label: "GeoStory",
    desc: "Narasi spasial tematik sejarah dan wilayah",
    icon: "i-lucide-book-open",
    to: "/geostory",
  },
  {
    label: "Katalog Data",
    desc: "Pusat direktori dataset resmi JIGN",
    icon: "i-lucide-database",
    to: "/katalog-data",
  },
];

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

    let kecamatanParam = "";
    if (item.category === "kecamatan") {
      const cleanKecamatan = item.title
        .replace(/^Kecamatan\s+/i, "")
        .replace(/\s*\(Kota\)$/i, "")
        .trim();
      kecamatanParam = `&kecamatan=${encodeURIComponent(cleanKecamatan)}`;
    }

    navigateTo(`/maps/@${lat.toFixed(5)},${lng.toFixed(5)},${z}z?search=${encodedSearch}${kecamatanParam}${basemapParam}`);
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

// Tutup pencarian otomatis saat rute berpindah keluar dari beranda
watch(
  () => route.path,
  () => {
    closeSearch();
  }
);

// Shortcut keyboard global: Ctrl+K / Cmd+K untuk buka pencarian (Hanya di Beranda)
function handleGlobalKeydown(e: KeyboardEvent) {
  if (!isHomePage.value) return;
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
  if (!isHomePage.value || !isSearchOpen.value) return;
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
  <div
    class="pointer-events-none fixed top-0 z-50 hidden justify-center pt-5 lg:flex transition-[left] duration-300"
    :class="route.path.startsWith('/maps') ? 'left-0 md:left-[350px] lg:left-[400px] right-0' : 'inset-x-0'"
  >
    <nav
      ref="desktopNavRef"
      aria-label="Navigasi Utama"
      class="pointer-events-auto relative flex items-center rounded-2xl transition-all duration-500 ease-out"
      :class="[
        isScrolled
          ? 'bg-white border border-slate-300/80 shadow-lg shadow-slate-900/8 dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none'
          : 'bg-white border border-slate-200/80 shadow-md shadow-slate-900/5 dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none',
        isHomePage && isSearchOpen
          ? 'px-4 py-2.5 shadow-2xl ring-1 ring-blue-500/20 dark:ring-white/20'
          : 'px-5 py-2.5 gap-1.5'
      ]"
    >
      <!-- Logo -->
      <NuxtLink
        to="/"
        class="shrink-0 flex items-center mr-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-xl"
        aria-label="Melarosa"
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

      <!-- Divider tengah pemisah menu dan search (Hanya tampil di Halaman Beranda) -->
      <span
        v-if="isHomePage"
        class="mx-2 h-5 w-px bg-slate-200 dark:bg-white/15 shrink-0"
        aria-hidden="true"
      />

      <!-- ─── SEARCH CONTAINER (Hanya ditampilkan pada Halaman Utama Beranda) ─── -->
      <div
        v-if="isHomePage"
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

      <!-- ─── DESKTOP SEARCH DROPDOWN SUGGESTIONS (Hanya di Beranda) ──── -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2 scale-[0.99]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-2 scale-[0.99]"
      >
        <div
          v-if="isHomePage && isSearchOpen"
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
  <div class="pointer-events-none fixed inset-x-0 top-0 z-[60] lg:hidden">
    <!-- Backdrop Overlay on Mobile when Search is Open (Hanya di Beranda) -->
    <Transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isHomePage && isSearchOpen"
        class="pointer-events-auto fixed inset-0 z-[65] bg-black/40 dark:bg-black/60 transition-opacity duration-300"
        aria-hidden="true"
        @click="closeSearch"
      />
    </Transition>

    <!-- Nav container with safe padding (z-[66] above search backdrop) -->
    <div class="relative z-[66] px-3 sm:px-4 pt-3 sm:pt-4">
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
          class="flex items-center justify-between gap-2"
        >
          <!-- Logo Pill (Left): Professional & Compact with Logo Bojonegoro -->
          <NuxtLink
            to="/"
            aria-label="Melarosa WebGIS Bojonegoro"
            class="pointer-events-auto flex items-center gap-2 rounded-2xl px-3 py-2 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 min-h-[44px] shrink-0 active:scale-[0.98] cursor-pointer"
            :class="isScrolled
              ? 'bg-white border border-slate-300/80 shadow-lg dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none'
              : 'bg-white border border-slate-200/80 shadow-md dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none'"
          >
            <!-- Logo Kabupaten Bojonegoro -->
            <img
              src="/logo-kabupaten.png"
              alt="Logo Kabupaten Bojonegoro"
              class="size-7 w-auto h-7 object-contain drop-shadow-xs shrink-0 select-none"
            />
            <!-- Brand Text & GIS Badge -->
            <div class="flex items-center gap-1 select-none leading-none">
              <span class="font-black text-[15px] sm:text-base tracking-tight text-slate-900 dark:text-white">
                MELAROSA
              </span>
              <span class="text-[9px] font-bold font-mono tracking-wider text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/70 border border-blue-200/60 dark:border-blue-800/40 px-1 py-0.5 rounded uppercase leading-none">
                GIS
              </span>
            </div>
          </NuxtLink>

          <!-- Right Action Controls (Search + Theme + Hamburger) -->
          <div class="pointer-events-auto flex items-center gap-1.5 sm:gap-2">
            <!-- 1. Search Trigger Button on Mobile (Hanya tampil di Halaman Beranda) -->
            <button
              v-if="isHomePage"
              type="button"
              aria-label="Buka pencarian kecamatan dan wilayah"
              class="relative flex items-center justify-center size-11 rounded-2xl transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
              :class="isScrolled
                ? 'bg-white border border-slate-300/80 shadow-lg text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none dark:text-slate-200 dark:hover:text-white'
                : 'bg-white border border-slate-200/80 shadow-md text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none dark:text-slate-200 dark:hover:text-white'"
              @click="openSearch"
            >
              <UIcon name="i-lucide-search" class="size-5" />
              <!-- Dot indicator if route has active search or kecamatan filter -->
              <span
                v-if="route.query.search || route.query.kecamatan"
                class="absolute top-2 right-2 size-2 rounded-full bg-blue-600 dark:bg-blue-400 ring-2 ring-white dark:ring-[#09111e]"
              />
            </button>

            <!-- 2. Theme Toggle Button on Mobile (min 44x44px) -->
            <button
              type="button"
              :aria-label="colorMode.value === 'dark' ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
              class="flex items-center justify-center size-11 rounded-2xl transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
              :class="isScrolled
                ? 'bg-white border border-slate-300/80 shadow-lg text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/90 dark:backdrop-blur-xl dark:border-white/15 dark:shadow-none dark:text-slate-200 dark:hover:text-white'
                : 'bg-white border border-slate-200/80 shadow-md text-slate-700 hover:text-slate-900 dark:bg-[#09111e]/75 dark:backdrop-blur-xl dark:border-white/10 dark:shadow-none dark:text-slate-200 dark:hover:text-white'"
              @click="colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'"
            >
              <UIcon
                :name="colorMode.value === 'dark' ? 'i-lucide-sun' : 'i-lucide-moon'"
                class="size-5 transition-transform duration-300"
                :class="colorMode.value === 'dark' ? 'rotate-90 text-amber-400' : 'rotate-0 text-slate-700 dark:text-slate-200'"
              />
            </button>

            <!-- 3. Hamburger Navigation Menu Button (min 44x44px) -->
            <button
              type="button"
              :aria-label="isMobileMenuOpen ? 'Tutup menu' : 'Buka menu navigasi'"
              :aria-expanded="isMobileMenuOpen"
              class="relative flex items-center justify-center size-11 rounded-2xl transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
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
              <!-- Auth indicator dot -->
              <span
                v-if="auth.logged"
                class="absolute top-2 right-2 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#09111e]"
                title="Staf Terautentikasi"
              />
            </button>
          </div>
        </div>
      </Transition>

      <!-- Expanded Mobile Search Bar (Hanya tampil di Beranda) -->
      <Transition
        enter-active-class="transition-all duration-300 cubic-bezier(0.16, 1, 0.3, 1)"
        enter-from-class="opacity-0 -translate-y-4 scale-[0.98]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-4 scale-[0.98]"
      >
        <div
          v-if="isHomePage && isSearchOpen"
          ref="mobileSearchRef"
          class="pointer-events-auto absolute inset-x-3 sm:inset-x-4 top-3 sm:top-4 rounded-2xl transition-all duration-300 ease-out border shadow-2xl overflow-hidden"
          :class="isScrolled
            ? 'bg-white/95 border-slate-300/80 shadow-xl dark:bg-[#09111e]/95 dark:backdrop-blur-2xl dark:border-white/15'
            : 'bg-white/95 border-slate-200/80 shadow-lg dark:bg-[#09111e]/95 dark:backdrop-blur-2xl dark:border-white/10'"
        >
          <div class="flex items-center gap-2 px-3 py-2.5">
            <UIcon name="i-lucide-search" class="size-5 text-blue-500 shrink-0 ml-0.5" />
            <input
              ref="mobileSearchInputRef"
              v-model="searchQuery"
              type="text"
              placeholder="Cari 28 kecamatan Bojonegoro..."
              class="w-full bg-transparent text-sm text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-0 border-0 p-0"
              @keydown.enter.prevent="selectActiveResult"
              @keydown.esc.prevent="closeSearch"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="size-11 min-w-[44px] flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white active:scale-95 transition-transform cursor-pointer"
              aria-label="Hapus teks pencarian"
              @click="searchQuery = ''; mobileSearchInputRef?.focus()"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
            <button
              type="button"
              class="size-11 min-w-[44px] flex items-center justify-center rounded-xl text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-slate-100 hover:bg-slate-200/70 dark:bg-white/10 dark:hover:bg-white/20 active:scale-95 transition-all cursor-pointer"
              aria-label="Tutup pencarian"
              @click="closeSearch"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <!-- Mobile Suggestions Dropdown -->
          <div class="max-h-[60vh] overflow-y-auto overscroll-contain border-t border-slate-100 dark:border-white/10 p-2 space-y-1">
            <div class="px-2 py-1 flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-500">
              <span>{{ searchQuery ? `Hasil: ${searchResults.length}` : 'Daftar Kecamatan Bojonegoro' }}</span>
              <span>Ketuk untuk filter layer MVT</span>
            </div>

            <template v-if="searchResults.length > 0">
              <button
                v-for="(item, idx) in searchResults"
                :key="item.id"
                type="button"
                class="w-full min-h-[44px] flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-xl text-left transition-colors cursor-pointer active:scale-[0.99]"
                :class="selectedIndex === idx
                  ? 'bg-blue-50 text-blue-900 dark:bg-white/15 dark:text-white font-medium'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10'"
                @click="handleSelectResult(item)"
              >
                <div class="flex items-center gap-2.5 min-w-0">
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
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ item.desc }}</p>
                  </div>
                </div>
                <span
                  class="text-[9px] uppercase font-mono px-2 py-0.5 rounded-md border shrink-0 font-medium"
                  :class="item.category === 'kecamatan'
                    ? 'bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-500/10 dark:border-blue-400/20 dark:text-blue-300'
                    : item.category === 'koordinat'
                      ? 'bg-emerald-50 border-emerald-200 text-emerald-600 dark:bg-emerald-500/10 dark:border-emerald-400/20 dark:text-emerald-300'
                      : 'bg-slate-100 border-slate-200 text-slate-600 dark:bg-white/10 dark:border-white/10 dark:text-slate-300'"
                >
                  {{ item.category === 'kecamatan' ? 'Kecamatan' : item.category }}
                </span>
              </button>
            </template>
            <div v-else class="py-6 px-3 text-center">
              <div class="size-9 rounded-full bg-slate-100 dark:bg-white/5 flex items-center justify-center mx-auto mb-2 text-slate-400">
                <UIcon name="i-lucide-search-x" class="size-4" />
              </div>
              <p class="text-xs font-semibold text-slate-800 dark:text-white">Tidak ada kecamatan yang cocok</p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                Ketik nama kecamatan di Kabupaten Bojonegoro (misal: Kapas, Dander, Temayang).
              </p>
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
    description="Portal WebGIS Bojonegoro"
    :ui="{
      overlay: 'fixed inset-0 z-[70] bg-black/40 dark:bg-black/60 transition-opacity duration-300',
      content: 'z-[80] max-w-[340px] sm:max-w-sm bg-white dark:bg-[#09111e] border-l border-slate-200/80 dark:border-white/10 flex flex-col shadow-2xl focus:outline-none',
      header: 'p-4 border-b border-slate-100 dark:border-white/10',
      body: 'p-4 flex-1 flex flex-col gap-4 overflow-y-auto overscroll-contain',
      footer: 'p-4 border-t border-slate-100 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02]'
    }"
  >
    <!-- Header with Brand & Close -->
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-2.5">
          <!-- Logo Kabupaten Bojonegoro -->
          <img
            src="/logo-kabupaten.png"
            alt="Logo Kabupaten Bojonegoro"
            class="size-8 w-auto h-8 object-contain drop-shadow-xs shrink-0 select-none"
          />
          <div>
            <h2 class="font-black text-base text-slate-900 dark:text-white tracking-tight leading-none">
              MELAROSA GIS
            </h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium mt-0.5">
              Kabupaten Bojonegoro
            </p>
          </div>
        </div>

        <button
          type="button"
          class="size-11 flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer"
          aria-label="Tutup menu"
          @click="isMobileMenuOpen = false"
        >
          <UIcon name="i-lucide-x" class="size-5" />
        </button>
      </div>
    </template>

    <!-- Body Content -->
    <template #body>
      <!-- Quick Search Bar in Drawer (Hanya tampil di Beranda) -->
      <button
        v-if="isHomePage"
        type="button"
        class="w-full px-3.5 py-2.5 min-h-[44px] flex items-center gap-2.5 rounded-xl text-xs sm:text-sm font-medium bg-slate-100/90 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-white hover:border-blue-400/50 transition-all text-left cursor-pointer"
        @click="isMobileMenuOpen = false; openSearch()"
      >
        <UIcon name="i-lucide-search" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
        <span class="truncate">Cari 28 Kecamatan Bojonegoro...</span>
        <span class="ml-auto text-[10px] font-mono px-1.5 py-0.5 rounded bg-white dark:bg-white/10 text-slate-400 border border-slate-200 dark:border-white/10">Cari</span>
      </button>

      <!-- Primary Navigation Links (Beranda, Geostory, Katalog Data, Peta) -->
      <div class="space-y-1">
        <p class="px-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
          Navigasi Utama
        </p>

        <!-- Nav Item Component / Links -->
        <NuxtLink
          v-for="item in mobileNavItems"
          :key="item.to"
          :to="item.to"
          class="group flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all cursor-pointer min-h-[48px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          :class="isPathActive(item.to, item.exact)
            ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-300 font-semibold ring-1 ring-blue-500/20'
            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-white/5'"
          @click="isMobileMenuOpen = false"
        >
          <div
            class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :class="isPathActive(item.to, item.exact)
              ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/30'
              : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white'"
          >
            <UIcon :name="item.icon" class="size-4.5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold truncate">{{ item.label }}</span>
              <UIcon
                name="i-lucide-chevron-right"
                class="size-4 text-slate-400 dark:text-slate-500 transition-transform group-hover:translate-x-0.5"
                :class="isPathActive(item.to, item.exact) ? 'text-blue-600 dark:text-blue-400' : ''"
              />
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ item.desc }}</p>
          </div>
        </NuxtLink>
      </div>

      <!-- Theme Switcher Segment in Drawer -->
      <div class="mt-2 pt-3 border-t border-slate-100 dark:border-white/10">
        <p class="px-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
          Mode Tampilan (Tema)
        </p>
        <div class="grid grid-cols-2 gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200/70 dark:border-white/10">
          <button
            type="button"
            class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold min-h-[44px] transition-all cursor-pointer"
            :class="colorMode.value === 'light'
              ? 'bg-white text-slate-900 shadow-xs ring-1 ring-slate-900/5'
              : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
            @click="colorMode.preference = 'light'"
          >
            <UIcon name="i-lucide-sun" class="size-4 text-amber-500" />
            <span>Terang</span>
          </button>
          <button
            type="button"
            class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold min-h-[44px] transition-all cursor-pointer"
            :class="colorMode.value === 'dark'
              ? 'bg-[#0b0f19] text-white shadow-xs ring-1 ring-white/15'
              : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
            @click="colorMode.preference = 'dark'"
          >
            <UIcon name="i-lucide-moon" class="size-4 text-blue-400" />
            <span>Gelap</span>
          </button>
        </div>
      </div>
    </template>

    <!-- Drawer Footer: Auth Status / Profile or Login -->
    <template #footer>
      <div class="w-full">
        <!-- Logged in state -->
        <div v-if="auth.logged" class="space-y-3">
          <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-100/70 dark:bg-white/5 border border-slate-200/60 dark:border-white/10">
            <UAvatar
              icon="i-heroicons-user"
              class="rounded-lg shrink-0"
              size="md"
              :src="$storage(auth.user.avatar)"
              :alt="auth.user.name"
            />
            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                {{ auth.user.name || 'Staf Geoportal' }}
              </p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                {{ auth.user.email }}
              </p>
            </div>
            <span class="size-2 rounded-full bg-emerald-500 shrink-0" title="Aktif" />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <NuxtLink
              to="/admin"
              class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition-all min-h-[44px] cursor-pointer"
              @click="isMobileMenuOpen = false"
            >
              <UIcon name="i-heroicons-squares-2x2" class="size-4" />
              <span>Dashboard</span>
            </NuxtLink>
            <button
              type="button"
              class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold bg-slate-200 hover:bg-slate-300/80 dark:bg-white/10 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 transition-all min-h-[44px] cursor-pointer"
              @click="auth.logout(); isMobileMenuOpen = false"
            >
              <UIcon name="i-heroicons-arrow-left-on-rectangle" class="size-4" />
              <span>Keluar</span>
            </button>
          </div>
        </div>

        <!-- Not logged in state -->
        <div v-else class="space-y-2">
          <NuxtLink
            to="/auth/login"
            class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white shadow-sm shadow-blue-600/25 transition-all min-h-[44px] cursor-pointer"
            @click="isMobileMenuOpen = false"
          >
            <UIcon name="i-lucide-log-in" class="size-4.5" />
            <span>Masuk ke Portal</span>
          </NuxtLink>
          <p class="text-center text-[10px] text-slate-400 dark:text-slate-500">
            Akses khusus staf pengelola dan administrator GIS
          </p>
        </div>
      </div>
    </template>
  </USlideover>
</template>

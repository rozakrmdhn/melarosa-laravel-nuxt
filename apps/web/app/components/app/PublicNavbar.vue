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
const isMaps = computed(() => route.path.startsWith("/maps"));

// Shared state dengan pages/maps/index.vue: aktif HANYA setelah peta selesai memuat
const isMapsReady = useState<boolean>("melarosa_maps_ready", () => false);
const isMapsExpanded = computed(() => isMaps.value && isMapsReady.value);

// ═══ SEARCH FEATURE (HANYA AKTIF PADA BERANDA /) ═════════════════════════════
const isSearchOpen = ref(false);
const searchQuery = ref("");
const selectedIndex = ref(0);
const desktopSearchInputRef = ref<HTMLInputElement | null>(null);
const desktopNavRef = ref<HTMLElement | null>(null);

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
  { id: "page-maps", title: "Peta", category: "halaman", desc: "Eksplorasi peta spasial, basemap, dan layer jalan poros", icon: "i-lucide-map", to: "/maps" },
  { id: "page-dataset", title: "Katalog Dataset JIGN", category: "halaman", desc: "Pusat direktori dataset geospasial resmi JIGN Bojonegoro", icon: "i-lucide-database", to: "/dataset" },
  { id: "page-geopark", title: "Geopark Bojonegoro", category: "halaman", desc: "20 situs geodiversity dan warisan alam Bojonegoro", icon: "i-lucide-mountain-snow", to: "/geopark" },
  { id: "page-statistik", title: "Statistik Geospasial", category: "halaman", desc: "Dashboard analitik dan statistik data spasial Bojonegoro", icon: "i-lucide-bar-chart-3", to: "/statistik" },
];

// Deteksi koordinat WGS 84 (format Desimal DD atau DMS)
const coordinateMatch = computed<SearchItem | null>(() => {
  if (!searchQuery.value) return null;
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
  PAGE_ITEMS[1], // Peta
  PAGE_ITEMS[2], // Katalog Dataset
  PAGE_ITEMS[3], // Geopark Bojonegoro
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
    desktopSearchInputRef.value?.focus();
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
    label: "Peta",
    desc: "Batas wilayah, basemap, dan layer jalan poros",
    icon: "i-lucide-map",
    to: "/maps",
    exact: true,
  },
  {
    label: "Katalog Dataset",
    desc: "Pusat direktori dataset geospasial resmi JIGN",
    icon: "i-lucide-database",
    to: "/dataset",
  },
  {
    label: "Geopark Bojonegoro",
    desc: "20 situs geodiversity dan warisan alam",
    icon: "i-lucide-mountain-snow",
    to: "/geopark",
  },
  {
    label: "Statistik",
    desc: "Dashboard analitik dan statistik data spasial",
    icon: "i-lucide-bar-chart-3",
    to: "/statistik",
    exact: true,
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
      kecamatanParam = `&kecamatan=${encodeURIComponent(cleanKecamatan)}&wilayah=${encodeURIComponent(cleanKecamatan)}`;
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

// Shortcut keyboard global: Ctrl+K / Cmd+K untuk buka pencarian
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

const compactNavWidth = ref<string>("min(calc(100vw - 64px), 1216px)");

function measureCompactWidth() {
  if (desktopNavRef.value && !isMaps.value) {
    const w = desktopNavRef.value.offsetWidth;
    if (w > 600) {
      compactNavWidth.value = `${w}px`;
    }
  }
}

onMounted(() => {
  window.addEventListener("scroll", handleScroll, { passive: true });
  window.addEventListener("keydown", handleGlobalKeydown);
  nextTick(() => {
    measureCompactWidth();
  });
});

watch(
  () => route.path,
  (newPath) => {
    if (!newPath.startsWith("/maps")) {
      isMapsReady.value = false;
    }
    nextTick(() => {
      measureCompactWidth();
    });
  }
);

onUnmounted(() => {
  window.removeEventListener("scroll", handleScroll);
  window.removeEventListener("keydown", handleGlobalKeydown);
});
</script>

<template>
  <Teleport to="body">
    <!-- ═══ DESKTOP FLOATING NAV (lg+) ═══════════════════════════════════════ -->
    <div
      class="pointer-events-none fixed top-0 z-[70] hidden justify-center pt-3.5 sm:pt-4 lg:flex inset-x-0 transition-all duration-300"
    >
      <div
        class="w-full flex justify-center transition-all duration-300"
        :class="isMaps ? 'px-4' : 'max-w-7xl px-4 sm:px-6 lg:px-8 mx-auto'"
      >
        <nav
          ref="desktopNavRef"
          aria-label="Navigasi Utama"
          class="pointer-events-auto relative flex items-center rounded-2xl py-2.5 transition-all duration-[1500ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] overflow-hidden will-change-[width]"
          :style="isMaps ? {
            width: isMapsExpanded ? 'calc(100vw - 32px)' : compactNavWidth
          } : {}"
          :class="[
            isScrolled
              ? 'bg-white/90 backdrop-blur-xl border border-slate-300/80 shadow-lg shadow-slate-900/8 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50'
              : isMaps
                ? 'bg-white/90 backdrop-blur-xl border border-slate-200/80 shadow-md shadow-slate-900/5 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50'
                : 'bg-white/85 backdrop-blur-xl border border-slate-200/80 shadow-md shadow-slate-900/5 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50',
            isMaps
              ? isMapsExpanded
                ? 'px-5 sm:px-6 shadow-xl shadow-slate-900/5 dark:shadow-black/50'
                : 'px-5 sm:px-6 shadow-md shadow-slate-900/5 dark:shadow-black/40'
              : 'w-full px-5 sm:px-6 shadow-md shadow-slate-900/5 dark:shadow-black/40'
          ]"
        >
          <!-- 1. Bagian Kiri: Brand Logo -->
          <div
            class="flex items-center shrink-0 justify-start transition-all duration-[1500ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] flex-1 min-w-0"
          >
            <NuxtLink
              to="/"
              class="shrink-0 flex items-center mr-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-xl"
              aria-label="Melarosa"
              @click="closeSearch"
            >
              <AppLogo />
              <span
                v-if="isMaps"
                class="ml-2 text-[10px] font-bold font-mono tracking-wider text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/70 border border-blue-200/60 dark:border-blue-800/40 px-1.5 py-0.5 rounded uppercase leading-none select-none shrink-0"
              >
                GIS
              </span>
            </NuxtLink>

            <!-- Divider kiri (khusus mode peta dengan badge GIS) -->
            <span
              v-if="isMaps"
              class="mx-2 h-5 w-px bg-slate-200 dark:bg-white/15 shrink-0"
              aria-hidden="true"
            />
          </div>

          <!-- 2. Bagian Tengah: Navigasi Utama -->
          <div class="flex items-center gap-1 shrink-0 justify-center">
            <NuxtLink
              v-for="link in navTree"
              :key="link.to"
              :to="link.to"
              class="rounded-xl px-3.5 py-2 text-[0.88rem] transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 shrink-0 whitespace-nowrap"
              :class="isPathActive(link.to, link.exact)
                ? 'text-blue-600 bg-blue-50/90 font-bold border border-blue-200/70 dark:text-blue-400 dark:bg-blue-950/70 dark:border-blue-800/60 dark:shadow-2xs'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium dark:text-slate-300 dark:hover:text-white dark:hover:bg-white/5 border border-transparent'"
            >
              {{ link.label }}
            </NuxtLink>
          </div>

          <!-- 3. Bagian Kanan: Search Icon Button + Theme Mode & Masuk / User -->
          <div
            class="flex items-center gap-1.5 sm:gap-2 shrink-0 justify-end transition-all duration-[1500ms] [transition-timing-function:cubic-bezier(0.16,1,0.3,1)] flex-1 min-w-0"
          >
          <!-- Divider sebelum aksi -->
          <span
            class="mx-1 h-5 w-px bg-slate-200 dark:bg-white/15 shrink-0"
            aria-hidden="true"
          />

          <!-- SEARCH TRIGGER ICON BUTTON (Hanya icon pencarian, hemat ruang navigasi) -->
          <button
            type="button"
            class="size-9 flex items-center justify-center rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100/90 dark:text-slate-300 dark:hover:text-white dark:hover:bg-white/10 border border-slate-200/70 dark:border-white/10 dark:bg-white/5 transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 active:scale-95 shrink-0 relative"
            :class="isSearchOpen ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 border-blue-200/80 dark:border-blue-800/50' : ''"
            aria-label="Buka pencarian fitur dan wilayah (Ctrl K)"
            title="Pencarian Cepat (Ctrl+K)"
            @click="openSearch"
          >
            <UIcon name="i-lucide-search" class="size-4" />
            <!-- Dot indicator jika terdapat filter/pencarian aktif -->
            <span
              v-if="route.query.search || route.query.geosite || route.query.kecamatan"
              class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-blue-600 dark:bg-blue-400 ring-2 ring-white dark:ring-[#09111e]"
            />
          </button>

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
            class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-[0.9rem] font-semibold transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-sm shadow-blue-600/25 dark:bg-blue-600 dark:hover:bg-blue-500 dark:text-white dark:border-transparent dark:shadow-xs shrink-0"
          >
            Masuk
          </NuxtLink>
        </div>
      </nav>
    </div>
  </div>

    <!-- ═══ MOBILE FLOATING NAV (below lg) ═══════════════════════════════════ -->
    <div class="pointer-events-none fixed inset-x-0 top-0 z-[70] lg:hidden">
      <!-- Nav container with safe-area padding -->
      <div class="relative px-3 sm:px-4 pt-[calc(env(safe-area-inset-top,0px)+8px)]">
        <div class="flex items-center justify-between gap-2">
          <!-- Logo Pill (Left): Professional & Compact with Logo Bojonegoro (min 44px) -->
          <NuxtLink
            to="/"
            aria-label="Melarosa WebGIS Bojonegoro"
            class="pointer-events-auto flex items-center gap-2 rounded-2xl px-3 py-2 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 min-h-[44px] shrink-0 active:scale-[0.98] cursor-pointer"
            :class="isScrolled
              ? 'bg-white/90 backdrop-blur-xl border border-slate-300/80 shadow-lg dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50'
              : 'bg-white/85 backdrop-blur-xl border border-slate-200/80 shadow-md dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50'"
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

          <!-- Right Action Controls (Search + Hamburger, both min 44x44px) -->
          <div class="pointer-events-auto flex items-center gap-1.5 sm:gap-2">
            <!-- 1. Search Trigger Button on Mobile (Tampil di semua halaman) -->
            <button
              type="button"
              aria-label="Buka pencarian kecamatan dan wilayah"
              class="relative flex items-center justify-center size-11 rounded-2xl transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
              :class="isScrolled
                ? 'bg-white/88 backdrop-blur-xl border border-slate-300/80 shadow-lg text-slate-700 hover:text-slate-900 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50 dark:text-slate-200 dark:hover:text-white'
                : 'bg-white/85 backdrop-blur-xl border border-slate-200/80 shadow-md text-slate-700 hover:text-slate-900 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50 dark:text-slate-200 dark:hover:text-white'"
              @click="openSearch"
            >
              <UIcon name="i-lucide-search" class="size-5" />
              <!-- Dot indicator if route has active search or kecamatan filter -->
              <span
                v-if="route.query.search || route.query.geosite || route.query.kecamatan"
                class="absolute top-2 right-2 size-2 rounded-full bg-blue-600 dark:bg-blue-400 ring-2 ring-white dark:ring-[#09111e]"
              />
            </button>

            <!-- 2. Hamburger Navigation Menu Button (min 44x44px) -->
            <button
              type="button"
              :aria-label="isMobileMenuOpen ? 'Tutup menu' : 'Buka menu navigasi'"
              :aria-expanded="isMobileMenuOpen"
              class="relative flex items-center justify-center size-11 rounded-2xl transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer active:scale-95"
              :class="isScrolled
                ? 'bg-white/88 backdrop-blur-xl border border-slate-300/80 shadow-lg text-slate-700 hover:text-slate-900 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50 dark:text-slate-200 dark:hover:text-white'
                : 'bg-white/85 backdrop-blur-xl border border-slate-200/80 shadow-md text-slate-700 hover:text-slate-900 dark:bg-[#09111e] dark:border-white/10 dark:shadow-2xl dark:shadow-black/50 dark:text-slate-200 dark:hover:text-white'"
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
      </div>
    </div>

    <!-- ═══ SPOTLIGHT COMMAND PALETTE SEARCH MODAL (Desktop & Mobile) ════ -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-[0.98]"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-[0.98]"
    >
      <div
        v-if="isSearchOpen"
        class="fixed inset-0 z-[100] bg-black/50 backdrop-blur-sm p-3 sm:p-6 md:p-10 flex justify-center items-start overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-label="Pencarian Cepat Melarosa"
        @click.self="closeSearch"
      >
        <div
          class="w-full max-w-2xl bg-white/95 dark:bg-[#0b121e]/95 backdrop-blur-2xl border border-slate-200/90 dark:border-white/15 rounded-2xl shadow-2xl overflow-hidden flex flex-col transition-all mt-4 sm:mt-12"
          @click.stop
        >
          <!-- Search Header Input -->
          <div class="relative flex items-center px-4 py-3 sm:py-3.5 border-b border-slate-100 dark:border-white/10 gap-3">
            <UIcon name="i-lucide-search" class="size-5 text-blue-600 dark:text-blue-400 shrink-0 ml-0.5" />
            <input
              ref="desktopSearchInputRef"
              v-model="searchQuery"
              type="text"
              placeholder="Cari kecamatan, koordinat WGS 84, atau dataset..."
              class="w-full bg-transparent text-base sm:text-lg text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none border-0 p-0"
              @keydown.down.prevent="navigateResults(1)"
              @keydown.up.prevent="navigateResults(-1)"
              @keydown.enter.prevent="selectActiveResult"
              @keydown.esc.prevent="closeSearch"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="size-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer shrink-0"
              aria-label="Hapus teks pencarian"
              @click="searchQuery = ''; desktopSearchInputRef?.focus()"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
            <button
              type="button"
              class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 transition-all cursor-pointer shrink-0"
              aria-label="Tutup pencarian"
              @click="closeSearch"
            >
              <span class="text-[11px] font-mono">ESC</span>
            </button>
          </div>

          <!-- Result Info Bar -->
          <div class="flex items-center justify-between px-4 py-2 bg-slate-50/80 dark:bg-white/[0.03] border-b border-slate-100 dark:border-white/10 text-xs text-slate-500 dark:text-slate-400">
            <span class="font-medium truncate">
              {{ searchQuery ? `Ditemukan ${searchResults.length} hasil untuk "${searchQuery}"` : 'Rekomendasi Geospasial Bojonegoro' }}
            </span>
            <span class="hidden sm:inline text-[11px] text-slate-400 dark:text-slate-500 shrink-0">
              Gunakan panah ↑↓ untuk memilih
            </span>
          </div>

          <!-- Result Items -->
          <div class="max-h-[380px] sm:max-h-[420px] overflow-y-auto overscroll-contain p-2 space-y-1">
            <template v-if="searchResults.length > 0">
              <button
                v-for="(item, idx) in searchResults"
                :key="item.id"
                type="button"
                class="w-full flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl text-left transition-all cursor-pointer min-h-[48px]"
                :class="selectedIndex === idx
                  ? 'bg-blue-50 text-blue-900 dark:bg-blue-950/40 dark:text-white font-medium ring-1 ring-blue-500/30'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5'"
                @click="handleSelectResult(item)"
                @mouseenter="selectedIndex = idx"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <div
                    class="size-9 rounded-xl flex items-center justify-center shrink-0"
                    :class="item.category === 'kecamatan'
                      ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300'
                      : item.category === 'koordinat'
                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'"
                  >
                    <UIcon :name="item.icon" class="size-4.5" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold truncate">{{ item.title }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ item.desc }}</p>
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <span
                    class="text-[10px] uppercase font-mono px-2 py-0.5 rounded-md border font-medium"
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

            <!-- Empty State -->
            <div v-else class="py-10 px-4 text-center">
              <div class="size-11 rounded-full bg-slate-100 dark:bg-white/5 flex items-center justify-center mx-auto mb-2 text-slate-400">
                <UIcon name="i-lucide-search-x" class="size-5" />
              </div>
              <p class="text-sm font-semibold text-slate-800 dark:text-white">Tidak ditemukan hasil yang cocok</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Cari 28 nama kecamatan di Bojonegoro (seperti Kapas, Dander, Temayang) atau format koordinat desimal WGS 84.
              </p>
            </div>
          </div>

          <!-- Spotlight Footer Bar -->
          <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-100 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] text-xs text-slate-400 dark:text-slate-500">
            <div class="flex items-center gap-2">
              <span class="font-medium text-slate-600 dark:text-slate-400">28 Kecamatan Terindeks</span>
              <span>·</span>
              <span>WGS 84</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-white/10 border border-slate-200 dark:border-white/10 font-mono text-[10px]">↵</kbd>
                <span>Buka</span>
              </span>
              <span class="inline-flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-white/10 border border-slate-200 dark:border-white/10 font-mono text-[10px]">Esc</kbd>
                <span>Tutup</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- ═══ MOBILE DRAWER ════════════════════════════════════════════════════ -->
  <USlideover
    v-model:open="isMobileMenuOpen"
    side="right"
    title="Menu Navigasi"
    description="Portal WebGIS Bojonegoro"
    :ui="{
      overlay: 'fixed inset-0 z-[80] bg-black/40 dark:bg-black/60 transition-opacity duration-300',
      content: 'z-[90] max-w-[340px] sm:max-w-sm bg-white dark:bg-[#09111e] border-l border-slate-200/80 dark:border-white/10 flex flex-col shadow-2xl focus:outline-none',
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

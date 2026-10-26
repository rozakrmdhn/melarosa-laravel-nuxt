<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { parseCoordinateString } from '~/utils/gis-helpers';
import { KECAMATAN_ENTRIES } from '~/constants/kecamatan';

useSeoMeta({
  title: 'Melarosa GIS: Portal Geospasial Kabupaten Bojonegoro',
  description: 'Peta interaktif, batas wilayah, dan data spasial resmi Kabupaten Bojonegoro. Jelajahi data geospasial kecamatan, jaringan jalan, dan infrastruktur wilayah.',
});

const heroMapRef = ref<HTMLElement | null>(null);
const scrollY = ref(0);

function onScroll() {
  scrollY.value = window.scrollY;
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true });

  // Scroll reveal
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );
  document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
});

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll);
});

const heroParallaxStyle = computed(() => ({
  transform: `translateY(${scrollY.value * 0.35}px) scale(1.1)`,
}));

const stats = [
  { value: '28', label: 'Kecamatan', suffix: '' },
  { value: '430', label: 'Desa & Kelurahan', suffix: '+' },
  { value: '2.307', label: 'km² Luas Wilayah', suffix: '' },
  { value: 'WGS 84', label: 'Standar Geodesi Nasional', suffix: '' },
];

// Quick jump & pencarian langsung di Hero
const heroSearchQuery = ref('');
const popularKecamatan = [
  { name: 'Bojonegoro Kota', param: 'Bojonegoro' },
  { name: 'Dander', param: 'Dander' },
  { name: 'Temayang', param: 'Temayang' },
  { name: 'Wonocolo', param: 'Kedewan' },
  { name: 'Padangan', param: 'Padangan' },
];

function onHeroSearchSubmit() {
  const q = heroSearchQuery.value.trim();
  if (!q) {
    navigateTo('/maps');
    return;
  }

  // 1. Koordinat spesifik (DD atau DMS)
  const parsedCoord = parseCoordinateString(q);
  if (parsedCoord) {
    navigateTo(`/maps/@${parsedCoord.lat.toFixed(5)},${parsedCoord.lng.toFixed(5)},16z?search=${encodeURIComponent(parsedCoord.formatted)}&pin=1`);
    return;
  }

  // 2. Kecamatan spesifik
  const cleanQ = q.replace(/^kecamatan\s+/i, '').replace(/\s*\(kota\)$/i, '').trim().toLowerCase();
  const matchedKec = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === cleanQ);
  if (matchedKec) {
    navigateTo(`/maps/@${matchedKec.coordinate[1].toFixed(5)},${matchedKec.coordinate[0].toFixed(5)},${matchedKec.zoom}z?kecamatan=${encodeURIComponent(matchedKec.name)}&search=${encodeURIComponent('Kecamatan ' + matchedKec.name)}`);
    return;
  }

  // 3. Fallback pencarian teks umum
  navigateTo(`/maps?search=${encodeURIComponent(q)}`);
}

function jumpToKecamatan(param: string) {
  const matched = KECAMATAN_ENTRIES.find((k) => k.name.toLowerCase() === param.toLowerCase());
  if (matched) {
    navigateTo(`/maps/@${matched.coordinate[1].toFixed(5)},${matched.coordinate[0].toFixed(5)},${matched.zoom}z?kecamatan=${encodeURIComponent(matched.name)}&search=${encodeURIComponent('Kecamatan ' + matched.name)}`);
  } else {
    navigateTo(`/maps?kecamatan=${encodeURIComponent(param)}`);
  }
}
</script>

<template>
  <!-- ═══ HERO: Full Viewport Map Background ═══════════════════════════════ -->
  <section class="landing-hero relative min-h-screen min-h-[100dvh] w-full flex flex-col justify-end overflow-hidden bg-[#03080f]">
    <!-- Map background container with parallax -->
    <div class="absolute inset-0 will-change-transform" :style="heroParallaxStyle">
      <img
        src="/images/hero-bojonegoro-connectivity.jpg"
        alt="Peta Jaringan Konektivitas Wilayah Kabupaten Bojonegoro"
        class="w-full h-full object-cover object-center brightness-[0.72] contrast-[1.2]"
      />
      <div class="absolute inset-0 bg-[#03080f]/40 mix-blend-multiply" />
    </div>

    <!-- Vignette & darkening gradients for crisp readability -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(3,8,15,0.75)_0%,rgba(3,8,15,0.3)_30%,transparent_50%,rgba(3,8,15,0.65)_75%,rgba(7,11,20,1)_100%)]" />
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_30%,rgba(3,8,15,0.65)_100%)]" />

    <!-- Hero content container -->
    <div class="relative z-10 w-full max-w-[1080px] mx-auto px-5 pt-32 pb-20 sm:pb-24 lg:pb-28 will-change-transform">
      <div class="hero-content max-w-[48rem]">
        <!-- Eyebrow label -->
        <div class="hero-item" style="--delay: 0ms">
          <span class="inline-flex items-center gap-2 rounded-full border border-blue-400/30 bg-blue-950/40 px-3 py-1 text-[0.72rem] font-semibold uppercase tracking-[0.16em] text-blue-300 backdrop-blur-md">
            <span class="inline-block size-1.5 rounded-full bg-blue-400" />
            Melarosa GIS: Kabupaten Bojonegoro
          </span>
        </div>

        <!-- Headline -->
        <h1
          class="hero-item mt-5 text-[2.2rem] sm:text-[3.2rem] lg:text-[4rem] font-bold leading-[1.04] tracking-[-0.03em] text-white [text-shadow:0_2px_30px_rgba(0,0,0,0.7)] [text-wrap:balance]"
          style="--delay: 90ms"
        >
          Satu Peta Geospasial untuk Konektivitas dan Pembangunan Bojonegoro
        </h1>

        <!-- Subheadline -->
        <p
          class="hero-item mt-5 max-w-[42rem] text-[0.98rem] sm:text-[1.12rem] leading-relaxed text-slate-200 [text-shadow:0_1px_12px_rgba(0,0,0,0.6)]"
          style="--delay: 180ms"
        >
          Portal resmi pemetaan batas 28 kecamatan, kondisi fisik jaringan jalan poros desa, cagar geologi geopark, dan sebaran pembangunan daerah berbasis proyeksi standar WGS 84.
        </p>

        <!-- Interactive Quick Search Bar & Actions -->
        <div class="hero-item mt-7 sm:mt-8 max-w-[44rem]" style="--delay: 270ms">
          <form
            class="flex flex-col sm:flex-row items-stretch gap-2.5 rounded-2xl border border-white/20 bg-slate-950/80 backdrop-blur-xl p-2 shadow-2xl ring-1 ring-white/10"
            @submit.prevent="onHeroSearchSubmit"
          >
            <div class="flex flex-1 items-center gap-3 px-3 min-h-[46px]">
              <UIcon name="i-lucide-search" class="size-5 text-blue-400 shrink-0" />
              <input
                v-model="heroSearchQuery"
                type="text"
                placeholder="Ketik nama kecamatan, desa, atau koordinat WGS 84..."
                class="w-full bg-transparent text-sm sm:text-base text-white placeholder-slate-400 focus:outline-none focus:ring-0 border-0 p-0"
                aria-label="Cari kecamatan atau lokasi di peta"
              />
            </div>
            <div class="flex items-center gap-2">
              <button
                type="submit"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 min-h-[44px] cursor-pointer"
              >
                <span>Cari di Peta</span>
                <UIcon name="i-lucide-arrow-right" class="size-4" />
              </button>
              <NuxtLink
                to="/maps"
                class="hidden md:inline-flex items-center justify-center gap-1.5 rounded-xl border border-white/20 hover:border-white/40 bg-white/5 hover:bg-white/10 px-4 py-2.5 text-sm font-medium text-slate-200 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 min-h-[44px]"
                title="Buka peta interaktif penuh"
              >
                <span>Buka Penuh</span>
                <UIcon name="i-lucide-maximize-2" class="size-3.5" />
              </NuxtLink>
            </div>
          </form>

          <!-- Quick Jump Chips -->
          <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-400 mr-1">Jelajahi Cepat:</span>
            <button
              v-for="item in popularKecamatan"
              :key="item.param"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-white/8 hover:bg-white/15 hover:border-blue-400/50 px-2.5 py-1 text-xs text-slate-200 hover:text-white backdrop-blur-sm transition-all cursor-pointer min-h-[32px]"
              @click="jumpToKecamatan(item.param)"
            >
              <UIcon name="i-lucide-map-pin" class="size-3 text-blue-400" />
              <span>{{ item.name }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Scroll cue without endless aggressive loop -->
    <div class="pointer-events-none absolute inset-x-0 bottom-4 sm:bottom-6 z-10 flex flex-col items-center gap-1">
      <span class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400/80">Gulir untuk melihat pilar wilayah</span>
      <UIcon name="i-lucide-chevron-down" class="size-4 text-slate-400/80" />
    </div>
  </section>

  <!-- ═══ MAIN CONTENT ═══════════════════════════════════════════════════ -->
  <main class="relative z-10 mx-auto max-w-[1080px] px-5">

    <!-- Stats row -->
    <section class="reveal -mt-8 sm:-mt-10 relative z-20 grid grid-cols-2 lg:grid-cols-4 gap-3.5 pb-8">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="rounded-2xl border border-slate-200/90 dark:border-slate-800/80 bg-white/95 dark:bg-[#0c121e]/90 shadow-sm backdrop-blur-md px-5 py-4.5 transition-transform hover:-translate-y-0.5"
      >
        <div class="text-[1.75rem] font-bold leading-none tracking-tight text-slate-900 dark:text-white">
          {{ stat.value }}<span v-if="stat.suffix" class="text-blue-600 dark:text-blue-400">{{ stat.suffix }}</span>
        </div>
        <div class="mt-2 text-[0.8rem] font-medium text-slate-600 dark:text-slate-400 leading-tight">{{ stat.label }}</div>
      </div>
    </section>

    <!-- ─── 4 Pilar Spasial & Pembangunan ──────────────────────────────── -->
    <section class="reveal pt-10 pb-6">
      <div class="max-w-[48rem]">
        <span class="inline-flex items-center gap-2 rounded-full border border-blue-200/80 dark:border-blue-900/60 bg-blue-50/80 dark:bg-blue-950/40 px-3 py-1 text-[0.72rem] font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">
          <span class="inline-block size-1.5 rounded-full bg-blue-600 dark:bg-blue-400" />
          Pilar Spasial & Pembangunan
        </span>
        <h2 class="mt-4 text-[1.95rem] sm:text-[2.4rem] font-bold leading-[1.12] tracking-[-0.025em] text-slate-900 dark:text-white [text-wrap:balance]">
          Konektivitas Wilayah, Cagar Geologi, dan Pemerataan Pembangunan
        </h2>
        <p class="mt-3.5 text-[0.98rem] sm:text-[1.05rem] leading-relaxed text-slate-600 dark:text-slate-300">
          Data spasial terpadu Kabupaten Bojonegoro: pemetaan kondisi jaringan jalan poros, cagar alam geopark, sebaran pembangunan fisik daerah, serta batas resmi 28 kecamatan.
        </p>
      </div>

      <div class="mt-9 grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Kartu 1: Jalan Poros Desa (Flagship / 7 Kolom) -->
        <div class="lg:col-span-7 flex flex-col justify-between rounded-3xl border border-slate-200/90 dark:border-slate-800 bg-gradient-to-br from-white via-slate-50/60 to-blue-50/30 dark:from-[#0c121e] dark:via-[#090e17] dark:to-[#071324] p-6 sm:p-8 transition-[border-color,transform] hover:border-blue-500/50 dark:hover:border-blue-500/40 hover:-translate-y-0.5 shadow-sm">
          <div>
            <div class="flex items-center justify-between gap-3">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100/70 dark:bg-blue-950/80 px-2.5 py-1 text-[0.72rem] font-semibold text-blue-700 dark:text-blue-300">
                <UIcon name="i-lucide-git-branch" class="size-3.5" />
                Konektivitas Wilayah
              </span>
              <span class="text-[0.75rem] font-medium text-slate-600 dark:text-slate-300">
                Ruas Jalan Poros
              </span>
            </div>
            <h3 class="mt-4 text-[1.35rem] sm:text-[1.55rem] font-bold text-slate-900 dark:text-white leading-snug">
              Jaringan Jalan Poros Desa
            </h3>
            <p class="mt-2.5 text-[0.92rem] sm:text-[0.98rem] leading-relaxed text-slate-600 dark:text-slate-300">
              Pemetaan kondisi fisik perkerasan jalan poros desa dan penghubung antar-kecamatan (Rigid Beton, Aspal, Makadam). Membantu memantau kelancaran mobilitas warga dan konektivitas antardesa secara visual.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Rigid Beton & Aspal
              </span>
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Pemotongan Segmen Ruas
              </span>
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Akses 430+ Desa
              </span>
            </div>
          </div>

          <div class="mt-7 pt-5 border-t border-slate-200/60 dark:border-slate-800/80">
            <NuxtLink
              to="/maps"
              class="inline-flex items-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
            >
              Lihat Peta Jalan Poros
              <UIcon name="i-lucide-arrow-right" class="size-4" />
            </NuxtLink>
          </div>
        </div>

        <!-- Kartu 2: Cagar Geologi & Geopark (5 Kolom) -->
        <div class="lg:col-span-5 flex flex-col justify-between rounded-3xl border border-slate-200/90 dark:border-slate-800 bg-gradient-to-br from-white via-slate-50/60 to-emerald-50/30 dark:from-[#0c121e] dark:via-[#090e17] dark:to-[#06140e] p-6 sm:p-8 transition-[border-color,transform] hover:border-emerald-500/50 dark:hover:border-emerald-500/40 hover:-translate-y-0.5 shadow-sm">
          <div>
            <div class="flex items-center justify-between gap-3">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100/70 dark:bg-emerald-950/80 px-2.5 py-1 text-[0.72rem] font-semibold text-emerald-700 dark:text-emerald-300">
                <UIcon name="i-lucide-mountain" class="size-3.5" />
                Warisan Geologi
              </span>
              <span class="text-[0.75rem] font-medium text-slate-600 dark:text-slate-300">
                Cagar Alam
              </span>
            </div>
            <h3 class="mt-4 text-[1.35rem] sm:text-[1.55rem] font-bold text-slate-900 dark:text-white leading-snug">
              Cagar Geologi & Geopark Nasional
            </h3>
            <p class="mt-2.5 text-[0.92rem] sm:text-[0.98rem] leading-relaxed text-slate-600 dark:text-slate-300">
              Eksplorasi cagar geologi dan bentang alam khas Bojonegoro: Api Abadi Kayangan Api, Negeri Minyak Teksas Wonocolo, dan Waduk Pacal langsung di peta tematik.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Kayangan Api
              </span>
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Teksas Wonocolo
              </span>
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Waduk Pacal
              </span>
            </div>
          </div>

          <div class="mt-7 pt-5 border-t border-slate-200/60 dark:border-slate-800/80">
            <NuxtLink
              to="/maps"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-5 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-200 hover:border-emerald-500 hover:text-emerald-600 dark:hover:text-emerald-400 transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
            >
              Jelajahi Situs Geopark
              <UIcon name="i-lucide-arrow-right" class="size-4" />
            </NuxtLink>
          </div>
        </div>

        <!-- Kartu 3: Pembangunan Daerah (6 Kolom) -->
        <div class="col-span-12 md:col-span-6 flex flex-col justify-between rounded-3xl border border-slate-200/90 dark:border-slate-800 bg-gradient-to-br from-white via-slate-50/60 to-amber-50/20 dark:from-[#0c121e] dark:via-[#090e17] dark:to-[#120e06] p-6 sm:p-8 transition-[border-color,transform] hover:border-amber-500/50 dark:hover:border-amber-500/40 hover:-translate-y-0.5 shadow-sm">
          <div>
            <div class="flex items-center justify-between gap-3">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100/70 dark:bg-amber-950/80 px-2.5 py-1 text-[0.72rem] font-semibold text-amber-700 dark:text-amber-300">
                <UIcon name="i-lucide-activity" class="size-3.5" />
                Pembangunan Daerah
              </span>
              <span class="text-[0.75rem] font-medium text-slate-600 dark:text-slate-300">
                Infrastruktur Fisik
              </span>
            </div>
            <h3 class="mt-4 text-[1.25rem] sm:text-[1.4rem] font-bold text-slate-900 dark:text-white leading-snug">
              Pemerataan Pembangunan Infrastruktur
            </h3>
            <p class="mt-2.5 text-[0.92rem] leading-relaxed text-slate-600 dark:text-slate-300">
              Informasi spasial mengenai sebaran proyek pekerjaan fisik, peningkatan sarana prasarana publik, dan pemantauan realisasi infrastruktur daerah di seluruh penjuru Kabupaten Bojonegoro.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Sebaran Proyek Daerah
              </span>
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Monitoring Realisasi Fisik
              </span>
            </div>
          </div>

          <div class="mt-7 pt-5 border-t border-slate-200/60 dark:border-slate-800/80">
            <NuxtLink
              to="/maps"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-5 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-200 hover:border-amber-500 hover:text-amber-600 dark:hover:text-amber-400 transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
            >
              Pantau Sebaran Pembangunan
              <UIcon name="i-lucide-arrow-right" class="size-4" />
            </NuxtLink>
          </div>
        </div>

        <!-- Kartu 4: Batas Wilayah Administratif (6 Kolom) -->
        <div class="col-span-12 md:col-span-6 flex flex-col justify-between rounded-3xl border border-slate-200/90 dark:border-slate-800 bg-gradient-to-br from-white via-slate-50/60 to-indigo-50/20 dark:from-[#0c121e] dark:via-[#090e17] dark:to-[#0a0c18] p-6 sm:p-8 transition-[border-color,transform] hover:border-indigo-500/50 dark:hover:border-indigo-500/40 hover:-translate-y-0.5 shadow-sm">
          <div>
            <div class="flex items-center justify-between gap-3">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100/70 dark:bg-indigo-950/80 px-2.5 py-1 text-[0.72rem] font-semibold text-indigo-700 dark:text-indigo-300">
                <UIcon name="i-lucide-map-pin" class="size-3.5" />
                Satu Data Wilayah
              </span>
              <span class="text-[0.75rem] font-medium text-slate-600 dark:text-slate-300">
                28 Kecamatan
              </span>
            </div>
            <h3 class="mt-4 text-[1.25rem] sm:text-[1.4rem] font-bold text-slate-900 dark:text-white leading-snug">
              Batas Resmi 28 Kecamatan & 430 Desa
            </h3>
            <p class="mt-2.5 text-[0.92rem] leading-relaxed text-slate-600 dark:text-slate-300">
              Standardisasi batas poligon administratif resmi dari BPS dan Badan Informasi Geospasial (BIG) berbasis proyeksi WGS 84, menjamin kepastian tata ruang dan batas wilayah daerah.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Batas Kecamatan & Desa
              </span>
              <span class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 px-2.5 py-1 text-xs text-slate-600 dark:text-slate-300">
                Standar Proyeksi WGS 84
              </span>
            </div>
          </div>

          <div class="mt-7 pt-5 border-t border-slate-200/60 dark:border-slate-800/80">
            <NuxtLink
              to="/maps"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-5 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-200 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
            >
              Telusuri Batas Wilayah
              <UIcon name="i-lucide-arrow-right" class="size-4" />
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Map CTA banner ────────────────────────────────────────────── -->
    <section class="reveal relative my-16 overflow-hidden rounded-3xl border border-slate-200/90 dark:border-slate-800 bg-[#070b14] text-white">
      <div class="absolute inset-0 bg-[radial-gradient(80%_110%_at_52%_-20%,rgba(37,99,235,0.22),transparent_60%)]" />
      <div class="relative z-10 px-6 py-14 sm:px-10 sm:py-16 text-center">
        <h2 class="mx-auto max-w-[26ch] text-[1.8rem] sm:text-[2.2rem] font-bold tracking-tight text-white [text-wrap:balance]">
          Pahami Wilayah Bojonegoro Melalui Satu Peta
        </h2>
        <p class="mx-auto mt-4 mb-8 max-w-[38rem] leading-relaxed text-slate-300 text-[0.98rem] sm:text-[1.05rem]">
          Buka peta interaktif untuk memeriksa nama kecamatan, koordinat geografis, kondisi ruas jalan, dan informasi spasial di kawasan Anda.
        </p>
        <NuxtLink
          to="/maps"
          class="inline-flex items-center gap-2 rounded-full bg-blue-600 hover:bg-blue-500 px-7 py-3.5 text-[0.98rem] font-semibold text-white shadow-lg shadow-blue-600/30 transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          Buka Peta Interaktif
          <UIcon name="i-lucide-arrow-right" class="size-4" />
        </NuxtLink>
      </div>
    </section>

    <!-- ─── About / Context block ─────────────────────────────────────── -->
    <section class="reveal my-16 max-w-[54rem]">
      <span class="inline-flex items-center gap-2 text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-blue-600 dark:text-blue-400">
        <span class="inline-block size-1.5 rounded-full bg-blue-600 dark:bg-blue-400" />
        Tentang Melarosa GIS
      </span>
      <h2 class="mt-4 text-[1.65rem] sm:text-[2rem] font-bold leading-[1.15] tracking-tight text-slate-900 dark:text-white [text-wrap:balance]">
        Sistem Informasi Geospasial Kabupaten Bojonegoro
      </h2>
      <p class="mt-4 text-[1rem] sm:text-[1.05rem] leading-relaxed text-slate-600 dark:text-slate-300">
        Melarosa menyajikan data batas wilayah administratif dan jaringan jalan poros Kabupaten Bojonegoro dalam satu platform peta yang dapat diakses publik. Data dikompilasi dari BPS, BIG, dan sumber resmi pemerintah daerah, disajikan dalam proyeksi WGS 84 agar kompatibel dengan standar geospasial nasional.
      </p>
      <div class="mt-6 flex flex-wrap gap-3">
        <NuxtLink
          to="/maps"
          class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-[0.95rem] font-medium bg-blue-600 text-white transition hover:bg-blue-500 hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          Buka Peta
        </NuxtLink>
        <NuxtLink
          to="/auth/login"
          class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-[0.95rem] font-medium border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 bg-white dark:bg-[#0b0f19] transition hover:border-blue-500 hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          Masuk sebagai Staf
        </NuxtLink>
      </div>
    </section>

  </main>
</template>

<style scoped>
/* ─── Hero map canvas: SVG topo map of Bojonegoro area ─── */
.hero-map-canvas {
  background-color: #0a1628;
  background-image:
    /* Subtle terrain contour lines */
    repeating-linear-gradient(
      0deg,
      transparent,
      transparent 38px,
      rgba(16, 185, 129, 0.04) 38px,
      rgba(16, 185, 129, 0.04) 39px
    ),
    repeating-linear-gradient(
      90deg,
      transparent,
      transparent 38px,
      rgba(16, 185, 129, 0.04) 38px,
      rgba(16, 185, 129, 0.04) 39px
    ),
    /* River-like diagonal flow */
    linear-gradient(125deg, rgba(6, 78, 59, 0.35) 0%, rgba(4, 47, 46, 0.3) 30%, rgba(3, 7, 18, 0.6) 60%, rgba(2, 6, 23, 0.8) 100%),
    /* Deep terrain base */
    radial-gradient(ellipse 180% 80% at 45% 55%, rgba(5, 46, 22, 0.7) 0%, rgba(3, 15, 30, 0.85) 55%, rgba(1, 4, 14, 0.95) 100%);
  background-size: 60px 60px, 60px 60px, 100% 100%, 100% 100%;
}

/* Terrain detail SVG overlay via pseudo element */
.hero-map-canvas::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='500' viewBox='0 0 800 500'%3E%3Cdefs%3E%3Cfilter id='blur'%3E%3CfeGaussianBlur stdDeviation='1.5'/%3E%3C/filter%3E%3C/defs%3E%3Cg opacity='0.45' filter='url(%23blur)'%3E%3Cpath d='M0 280 C80 260 160 290 240 270 C320 250 400 280 480 260 C560 240 640 270 720 250 L800 240 L800 500 L0 500 Z' fill='%230d3d2a'/%3E%3Cpath d='M0 310 C100 295 200 315 300 300 C400 285 500 310 600 295 L800 280 L800 500 L0 500 Z' fill='%23062318'/%3E%3C/g%3E%3Cg opacity='0.3'%3E%3Cellipse cx='240' cy='200' rx='180' ry='90' fill='none' stroke='%2310b981' stroke-width='0.6' opacity='0.4'/%3E%3Cellipse cx='240' cy='200' rx='140' ry='65' fill='none' stroke='%2310b981' stroke-width='0.5' opacity='0.3'/%3E%3Cellipse cx='240' cy='200' rx='100' ry='42' fill='none' stroke='%2310b981' stroke-width='0.4' opacity='0.25'/%3E%3Cellipse cx='550' cy='160' rx='130' ry='70' fill='none' stroke='%2310b981' stroke-width='0.5' opacity='0.3'/%3E%3Cellipse cx='550' cy='160' rx='90' ry='46' fill='none' stroke='%2310b981' stroke-width='0.4' opacity='0.22'/%3E%3C/g%3E%3Cg opacity='0.35'%3E%3Cpath d='M380 80 C400 120 390 180 420 230 C445 270 460 300 450 350' stroke='%2334d399' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3Cpath d='M420 230 C460 240 510 220 560 240 C600 255 640 245 680 260' stroke='%2334d399' stroke-width='1.4' fill='none' stroke-linecap='round'/%3E%3Cpath d='M380 80 C330 100 280 120 240 140 C190 165 150 180 100 200' stroke='%2334d399' stroke-width='1.2' fill='none' stroke-linecap='round'/%3E%3C/g%3E%3Cg opacity='0.18'%3E%3Ccircle cx='420' cy='240' r='3' fill='%2334d399'/%3E%3Ccircle cx='240' cy='200' r='2.5' fill='%2334d399'/%3E%3Ccircle cx='560' cy='175' r='2' fill='%2334d399'/%3E%3Ccircle cx='650' cy='260' r='2' fill='%2310b981'/%3E%3Ccircle cx='150' cy='210' r='2' fill='%2310b981'/%3E%3C/g%3E%3C/svg%3E");
  background-size: cover;
  background-position: center;
}

/* ─── Hero content staggered entrance ─── */
.hero-item {
  opacity: 0;
  transform: translateY(18px);
  animation: hero-in 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  animation-delay: var(--delay, 0ms);
}

@keyframes hero-in {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ─── Scroll reveal sections ─── */
.reveal {
  opacity: 0;
  transform: translateY(28px);
  animation: reveal-in 0.65s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  animation-play-state: paused;
}

.reveal.visible {
  animation-play-state: running;
}

/* Map grid pattern for CTA section */
.map-grid-pattern {
  background-image:
    repeating-linear-gradient(0deg, rgba(16,185,129,0.06) 0px, rgba(16,185,129,0.06) 1px, transparent 1px, transparent 44px),
    repeating-linear-gradient(90deg, rgba(16,185,129,0.06) 0px, rgba(16,185,129,0.06) 1px, transparent 1px, transparent 44px);
}

@keyframes reveal-in {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

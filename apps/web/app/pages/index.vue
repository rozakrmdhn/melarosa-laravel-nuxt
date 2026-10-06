<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

useSeoMeta({
  title: 'Melarosa GIS — Portal Geospasial Kabupaten Bojonegoro',
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
  { value: 'WGS 84', label: 'Sistem Koordinat', suffix: '' },
];

const features = [
  {
    icon: 'i-lucide-map',
    title: 'Peta Batas Wilayah',
    desc: 'Batas administratif kecamatan dan desa secara akurat berdasarkan data resmi BPS Bojonegoro.',
  },
  {
    icon: 'i-lucide-git-branch',
    title: 'Jaringan Jalan Poros',
    desc: 'Pemetaan jaringan jalan kabupaten, jalan poros, dan akses antar wilayah.',
  },
  {
    icon: 'i-lucide-layers',
    title: 'Layer Data Tematik',
    desc: 'Layer spasial tematik WMS/WFS yang terhubung ke sumber data resmi pemerintah daerah.',
  },
];
</script>

<template>
  <!-- ═══ HERO: Full Viewport Map Background ═══════════════════════════════ -->
  <section class="landing-hero relative min-h-screen min-h-[100dvh] w-full flex flex-col justify-end overflow-hidden bg-[#03080f]">
    <!-- Map background container with parallax -->
    <div class="absolute inset-0 will-change-transform" :style="heroParallaxStyle">
      <img
        src="/images/hero-bojonegoro-connectivity.jpg"
        alt="Peta Jaringan Konektivitas Wilayah Kabupaten Bojonegoro"
        class="w-full h-full object-cover object-center brightness-[0.78] contrast-[1.15]"
      />
      <div class="absolute inset-0 bg-blue-950/25 mix-blend-color" />
    </div>

    <!-- Atmospheric overlays — layered ambient glow in blue/cyan -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
      <div
        class="aurora-glow absolute inset-0"
        style="background: linear-gradient(110deg, transparent 8%, rgba(37,99,235,0.22) 30%, rgba(56,189,248,0.16) 52%, rgba(30,58,138,0.25) 70%, transparent 92%)"
      />
      <div
        class="aurora-glow aurora-glow-2 absolute inset-0"
        style="background: linear-gradient(76deg, transparent 18%, rgba(2,132,199,0.18) 44%, rgba(59,130,246,0.14) 68%, transparent 88%)"
      />
    </div>

    <!-- Horizon glow -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(110%_65%_at_52%_-10%,rgba(59,130,246,0.22),transparent_55%)]" />

    <!-- Top darkening gradient -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(3,8,15,0.72)_0%,rgba(3,8,15,0.35)_25%,transparent_48%,rgba(3,8,15,0.52)_68%,rgba(3,8,15,0)_82%)]" />

    <!-- Bottom fade to page bg -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,transparent_45%,rgba(3,8,15,0.2)_58%,rgba(3,8,15,0.55)_72%,rgba(7,11,20,0.85)_86%,rgba(7,11,20,1)_100%)]" />

    <!-- Hero content: bottom-anchored like mandumrimba -->
    <div class="relative z-10 w-full max-w-[1080px] mx-auto px-5 pt-28 pb-16 sm:pb-24 lg:pb-28 will-change-transform">
      <div class="hero-content max-w-[46rem]">
        <!-- Eyebrow label -->
        <div class="hero-item" style="--delay: 0ms">
          <span class="inline-flex items-center gap-2 text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-white/80">
            <span class="inline-block size-1.5 rounded-full bg-blue-400" />
            Melarosa GIS · Kabupaten Bojonegoro
          </span>
        </div>

        <!-- Headline -->
        <h1
          class="hero-item mt-4 sm:mt-5 text-[2.2rem] sm:text-[3.2rem] lg:text-[4.2rem] font-bold leading-[1.02] sm:leading-[0.98] tracking-[-0.03em] text-white [text-shadow:0_2px_40px_rgba(0,0,0,0.55)] [text-wrap:balance]"
          style="--delay: 90ms"
        >
          Wilayah Bojonegoro, dipetakan untuk publik.
        </h1>

        <!-- Subheadline -->
        <p
          class="hero-item mt-4 sm:mt-6 max-w-[40rem] text-[0.98rem] sm:text-[1.15rem] leading-relaxed text-white/82 [text-shadow:0_1px_16px_rgba(0,0,0,0.5)]"
          style="--delay: 180ms"
        >
          Portal informasi geospasial resmi Kabupaten Bojonegoro: batas wilayah administratif, jaringan jalan poros, dan data spasial yang dapat dijelajahi oleh siapa saja.
        </p>

        <!-- CTAs -->
        <div class="hero-item mt-6 sm:mt-8 flex flex-wrap gap-3" style="--delay: 270ms">
          <NuxtLink
            to="/maps"
            class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-[0.98rem] font-semibold transition-[transform,filter,box-shadow] hover:-translate-y-px bg-blue-500 text-white shadow-[0_10px_30px_-8px_rgba(59,130,246,0.55)] hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#03080f]"
          >
            Buka Peta Interaktif
            <UIcon name="i-lucide-arrow-right" class="size-4" />
          </NuxtLink>
          <NuxtLink
            to="/maps"
            class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-[0.98rem] font-semibold transition-[transform,filter] hover:-translate-y-px border border-white/30 bg-white/10 text-white backdrop-blur-md hover:bg-white/18 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
          >
            Jelajahi Kecamatan
          </NuxtLink>
        </div>

        <!-- Tags -->
        <div class="hero-item mt-6 sm:mt-8 flex flex-wrap gap-2" style="--delay: 360ms">
          <span class="rounded-full border border-white/22 bg-white/5 px-3 py-1 text-[0.74rem] text-white/82 backdrop-blur-sm">Data Resmi</span>
          <span class="rounded-full border border-white/22 bg-white/5 px-3 py-1 text-[0.74rem] text-white/82 backdrop-blur-sm">Akses Publik</span>
          <span class="rounded-full border border-white/22 bg-white/5 px-3 py-1 text-[0.74rem] text-white/82 backdrop-blur-sm">WGS 84</span>
          <span class="rounded-full border border-white/22 bg-white/5 px-3 py-1 text-[0.74rem] text-white/82 backdrop-blur-sm">28 Kecamatan</span>
        </div>
      </div>
    </div>

    <!-- Scroll cue -->
    <div class="pointer-events-none absolute inset-x-0 bottom-4 sm:bottom-6 z-10 flex flex-col items-center gap-1.5">
      <span class="text-[0.63rem] font-semibold uppercase tracking-[0.18em] text-white/45">Gulir untuk menjelajahi</span>
      <UIcon name="i-lucide-chevron-down" class="scroll-cue size-4 text-white/45" />
    </div>
  </section>

  <!-- ═══ MAIN CONTENT ═══════════════════════════════════════════════════ -->
  <main class="relative z-10 mx-auto max-w-[1080px] px-5">

    <!-- Stats row -->
    <section class="reveal -mt-1 grid grid-cols-2 sm:grid-cols-4 gap-3 pt-10 pb-8">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="rounded-2xl border border-white/8 dark:border-slate-800/60 bg-white/5 dark:bg-[#0b0f19]/60 backdrop-blur-sm px-5 py-4"
      >
        <div class="text-[1.65rem] font-bold leading-none tracking-tight text-white dark:text-slate-100">
          {{ stat.value }}<span v-if="stat.suffix" class="text-blue-400">{{ stat.suffix }}</span>
        </div>
        <div class="mt-1.5 text-[0.78rem] text-white/58 dark:text-slate-500 leading-tight">{{ stat.label }}</div>
      </div>
    </section>

    <!-- ─── Features ──────────────────────────────────────────────────── -->
    <section class="reveal pt-12">
      <span class="inline-flex items-center gap-2 text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-blue-400">
        <span class="inline-block size-1.5 rounded-full bg-blue-400" />
        Fitur Portal
      </span>
      <h2 class="mt-3 text-[2rem] sm:text-[2.4rem] font-bold leading-[1.06] tracking-[-0.02em] text-slate-900 dark:text-white [text-wrap:balance]">
        Satu portal, semua data wilayah.
      </h2>
      <p class="mt-4 max-w-[46rem] text-[1.02rem] leading-relaxed text-slate-500 dark:text-slate-400">
        Data geospasial Bojonegoro dikompilasi dari sumber resmi dan dapat dijelajahi langsung di browser — tanpa perlu perangkat lunak GIS.
      </p>

      <div class="mt-9 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div
          v-for="feat in features"
          :key="feat.title"
          class="group flex flex-col rounded-2xl border border-slate-200/80 dark:border-slate-800/70 bg-white dark:bg-[#0b0f19] p-5 transition-[transform,border-color] hover:-translate-y-0.5 hover:border-blue-400/60 dark:hover:border-blue-500/40"
        >
          <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 transition-transform group-hover:scale-105">
            <UIcon :name="feat.icon" class="size-5" />
          </span>
          <h3 class="mt-4 text-[0.98rem] font-semibold text-slate-900 dark:text-slate-100 leading-tight">
            {{ feat.title }}
          </h3>
          <p class="mt-2 text-[0.88rem] leading-relaxed text-slate-500 dark:text-slate-400">
            {{ feat.desc }}
          </p>
        </div>
      </div>
    </section>

    <!-- ─── Map CTA banner ────────────────────────────────────────────── -->
    <section class="reveal relative my-16 overflow-hidden rounded-3xl border border-slate-200/80 dark:border-slate-800/70">
      <!-- background map pattern -->
      <div class="absolute inset-0 bg-[#070b14]">
        <div class="map-grid-pattern absolute inset-0 opacity-25" />
      </div>
      <div class="absolute inset-0 bg-[radial-gradient(80%_110%_at_52%_-20%,rgba(16,185,129,0.2),transparent_56%)]" />
      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(7,11,20,0.7),rgba(7,11,20,0.88))]" />

      <div class="relative z-10 px-8 py-16 text-center">
        <h2 class="mx-auto max-w-[22ch] text-[1.8rem] sm:text-[2.2rem] font-bold tracking-tight text-white [text-wrap:balance]">
          Mulai dari peta, pahami wilayahnya.
        </h2>
        <p class="mx-auto mt-4 mb-8 max-w-[36rem] leading-relaxed text-white/75">
          Klik titik manapun di peta Bojonegoro untuk melihat nama kecamatan, koordinat, dan informasi spasial kawasan tersebut.
        </p>
        <NuxtLink
          to="/maps"
          class="inline-flex items-center gap-2 rounded-full bg-blue-500 px-7 py-3.5 text-[1rem] font-semibold text-white shadow-[0_14px_38px_-14px_rgba(59,130,246,0.6)] transition hover:brightness-110 hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          Buka Peta Interaktif
          <UIcon name="i-lucide-arrow-right" class="size-4" />
        </NuxtLink>
      </div>
    </section>

    <!-- ─── About / Context block ─────────────────────────────────────── -->
    <section class="reveal my-16 max-w-[52rem]">
      <span class="inline-flex items-center gap-2 text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-blue-500 dark:text-blue-400">
        <span class="inline-block size-1.5 rounded-full bg-blue-500 dark:bg-blue-400" />
        Tentang Melarosa
      </span>
      <h2 class="mt-4 text-[1.65rem] sm:text-[2rem] font-bold leading-[1.15] tracking-tight text-slate-900 dark:text-white [text-wrap:balance]">
        Sistem informasi geospasial Kabupaten Bojonegoro
      </h2>
      <p class="mt-5 text-[1.05rem] leading-relaxed text-slate-500 dark:text-slate-400">
        Melarosa menyajikan data batas wilayah administratif dan jaringan jalan poros Kabupaten Bojonegoro dalam satu platform peta yang dapat diakses publik. Data dikompilasi dari BPS, BIG, dan sumber resmi pemerintah daerah, disajikan dalam proyeksi WGS 84 agar kompatibel dengan standar geospasial nasional.
      </p>
      <div class="mt-7 flex flex-wrap gap-3">
        <NuxtLink
          to="/maps"
          class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-[0.95rem] font-medium bg-blue-500 text-white transition hover:brightness-110 hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          Buka Peta
        </NuxtLink>
        <NuxtLink
          to="/auth/login"
          class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-[0.95rem] font-medium border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-white dark:bg-[#0b0f19] transition hover:border-blue-400/60 hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
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

/* ─── Aurora ambient animation ─── */
.aurora-glow {
  animation: aurora-drift 18s ease-in-out infinite alternate;
}
.aurora-glow-2 {
  animation: aurora-drift 24s ease-in-out infinite alternate-reverse;
}

@keyframes aurora-drift {
  0% { opacity: 0.7; transform: translateX(-3%) translateY(2%); }
  100% { opacity: 1; transform: translateX(3%) translateY(-2%); }
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

/* ─── Scroll cue bounce ─── */
.scroll-cue {
  animation: cue-bounce 2.2s ease-in-out infinite;
}

@keyframes cue-bounce {
  0%, 100% { transform: translateY(0); opacity: 0.45; }
  50% { transform: translateY(5px); opacity: 0.7; }
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

<script lang="ts" setup>
definePageMeta({
  middleware: ["guest"],
});

useSeoMeta({
  title: "Masuk | Sistem Informasi Geospasial Kabupaten Bojonegoro",
  description: "Portal masuk Sistem Informasi Geospasial (WebGIS) Kabupaten Bojonegoro untuk pengelolaan batas wilayah dan infrastruktur jalan.",
});
</script>

<template>
  <div class="min-h-screen w-full flex flex-col lg:flex-row bg-white dark:bg-[#070b14]">
    <!-- ══════════════════════════════════════════════════════════════════════════
         PANEL KIRI: FORM LOGIN
         ══════════════════════════════════════════════════════════════════════════ -->
    <div
      class="w-full lg:w-1/2 xl:w-[48%] min-h-screen flex flex-col justify-between p-6 sm:p-10 lg:p-12 xl:p-14 bg-white dark:bg-[#0b0f19] border-r border-gray-100 dark:border-white/[0.06] z-10"
    >
      <!-- Top Bar Form: Logo & Navigasi -->
      <div class="flex items-center justify-between gap-4">
        <!-- Logo Branding -->
        <NuxtLink to="/" class="flex items-center gap-3 group">
          <img
            src="/logo-kabupaten.png"
            alt="Logo Kabupaten Bojonegoro"
            class="h-10 w-auto object-contain drop-shadow-sm group-hover:scale-105 transition-transform"
          />
          <div class="min-w-0">
            <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight flex items-center gap-1.5">
              <span>SIGD Bojonegoro</span>
            </div>
            <div class="text-[11px] text-gray-500 dark:text-gray-400 font-medium truncate">
              Pemerintah Kabupaten Bojonegoro
            </div>
          </div>
        </NuxtLink>

        <!-- Right Action: Beranda & Theme Toggle -->
        <div class="flex items-center gap-1.5">
          <UButton
            to="/"
            icon="i-heroicons-arrow-left"
            label="Beranda"
            variant="ghost"
            color="neutral"
            size="xs"
            class="text-gray-500 hover:text-gray-900 dark:hover:text-white"
          />
          <UColorModeButton size="xs" />
        </div>
      </div>

      <!-- Main Login Container (Centered) -->
      <div class="max-w-sm w-full mx-auto my-auto py-8 sm:py-12">
        <div class="mb-7">
          <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white">
            Masuk ke Akun
          </h1>
          <p class="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
            Masukkan kredensial akun Anda untuk mengakses portal manajemen data geospasial Kabupaten Bojonegoro.
          </p>
        </div>

        <!-- Form Login Component -->
        <AuthLogin />
      </div>

      <!-- Bottom Footer: Hak Cipta -->
      <div class="pt-6 border-t border-gray-100 dark:border-white/[0.05] text-[11px] text-gray-400 dark:text-gray-500 flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>© 2026 Pemerintah Kabupaten Bojonegoro</span>
        <span class="font-mono text-[10px]">Sistem Informasi Geospasial v1.0</span>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         PANEL KANAN: DESKRIPSI SISTEM INFORMASI GEOSPASIAL KABUPATEN BOJONEGORO
         ══════════════════════════════════════════════════════════════════════════ -->
    <div
      class="hidden lg:flex lg:w-1/2 xl:w-[52%] min-h-screen flex-col justify-between p-10 lg:p-14 xl:p-16 relative overflow-hidden bg-gradient-to-br from-[#061816] via-[#081f1c] to-[#040e0c]"
    >
      <!-- Background Cartographic Grid Accent (Subtle & Authentic GIS) -->
      <div class="absolute inset-0 opacity-15 pointer-events-none">
        <!-- SVG Grid & Coordinate Pattern -->
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <pattern id="gis-grid" width="48" height="48" patternUnits="userSpaceOnUse">
              <path d="M 48 0 L 0 0 0 48" fill="none" stroke="currentColor" stroke-width="0.75" class="text-blue-400" />
              <circle cx="0" cy="0" r="1.5" fill="currentColor" class="text-blue-400" />
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#gis-grid)" />
        </svg>
      </div>

      <!-- Top Header Badge -->
      <div class="relative z-10 flex items-center justify-between">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/25 text-blue-300 text-xs font-medium backdrop-blur-sm">
          <img src="/logo-kabupaten.png" alt="Logo Bojonegoro" class="h-4 w-auto object-contain" />
          <span>Sistem Informasi Geospasial Daerah (SIGD)</span>
        </div>

        <div class="font-mono text-[11px] text-blue-400/80 bg-blue-950/40 px-2.5 py-1 rounded border border-blue-500/20">
          7°09′S 111°53′E
        </div>
      </div>

      <!-- Center Hero Overview -->
      <div class="relative z-10 my-auto py-8 space-y-7 max-w-xl">
        <div class="space-y-3">
          <h2 class="text-2xl sm:text-3xl xl:text-4xl font-extrabold text-white tracking-tight leading-tight">
            Sistem Informasi Geospasial Kabupaten Bojonegoro
          </h2>
          <p class="text-sm xl:text-base text-gray-300/90 leading-relaxed">
            Platform berbasis WebGIS untuk pemetaan digital, pengelolaan batas administrasi wilayah, serta inventarisasi jaringan jalan poros desa secara terpadu, presisi, dan transparan di Kabupaten Bojonegoro.
          </p>
        </div>

        <!-- 3 Pilar Data Geospasial (Ringkas & Sederhana) -->
        <div class="grid grid-cols-1 gap-3.5 pt-2">
          <!-- Pilar 1: Batas Wilayah -->
          <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-white/[0.04] border border-white/[0.08] backdrop-blur-sm">
            <div class="size-9 rounded-lg bg-blue-500/15 border border-blue-500/30 text-blue-400 flex items-center justify-center shrink-0 mt-0.5">
              <UIcon name="i-heroicons-building-office-2" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-sm font-bold text-white">
                Analisa Spasial
              </h3>
              <p class="text-xs text-gray-300/80 mt-0.5 leading-relaxed">
                Pemetaan spasial terverifikasi mencakup 28 wilayah kecamatan dan 430 desa/kelurahan di Kabupaten Bojonegoro.
              </p>
            </div>
          </div>

          <!-- Pilar 2: Jalan Poros Desa -->
          <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-white/[0.04] border border-white/[0.08] backdrop-blur-sm">
            <div class="size-9 rounded-lg bg-blue-500/15 border border-blue-500/30 text-blue-400 flex items-center justify-center shrink-0 mt-0.5">
              <UIcon name="i-heroicons-arrows-pointing-out" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-sm font-bold text-white">
                Peta Interaktif
              </h3>
              <p class="text-xs text-gray-300/80 mt-0.5 leading-relaxed">
                Inventarisasi ruas, status kelayakan, dan konektivitas infrastruktur jalan poros penghubung antar desa.
              </p>
            </div>
          </div>

          <!-- Pilar 3: Satu Data Spasial -->
          <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-white/[0.04] border border-white/[0.08] backdrop-blur-sm">
            <div class="size-9 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
              <UIcon name="i-heroicons-circle-stack" class="size-4.5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-sm font-bold text-white">
                Satu Data Geospasial Terpadu
              </h3>
              <p class="text-xs text-gray-300/80 mt-0.5 leading-relaxed">
                Standardisasi data spasial tematik untuk percepatan perencanaan pembangunan dan pengambilan kebijakan daerah.
              </p>
            </div>
          </div>
        </div>

        <!-- Ringkasan Statistik Spasial -->
        <div class="pt-3 flex flex-wrap items-center gap-6 border-t border-white/[0.08] text-xs text-gray-300">
          <div class="flex items-center gap-2">
            <span class="size-2 rounded-full bg-blue-400" />
            <span class="font-bold text-white">28</span>
            <span class="text-gray-400">Kecamatan</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="size-2 rounded-full bg-blue-400" />
            <span class="font-bold text-white">430</span>
            <span class="text-gray-400">Desa & Kelurahan</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="size-2 rounded-full bg-blue-400" />
            <span class="font-bold text-white">WebGIS</span>
            <span class="text-gray-400">Interaktif</span>
          </div>
        </div>
      </div>

      <!-- Bottom Motto / Kutipan Pemkab -->
      <div class="relative z-10 pt-4 border-t border-white/[0.08] text-xs text-gray-400/80 italic flex items-center justify-between">
        <span>"Mewujudkan Satu Data Geospasial Kabupaten Bojonegoro."</span>
        <UIcon name="i-heroicons-shield-check" class="size-4 text-blue-400/60" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Section 08: Ajakan Penutup Sederhana Latar Hijau Gelap dengan Aksi Biru Peta
</script>

<template>
  <section
    aria-labelledby="cta-heading"
    class="relative w-full bg-[#102B22] text-white py-16 sm:py-20 lg:py-24 overflow-hidden"
  >
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 opacity-10"
      style="background-image: radial-gradient(#34d399 0.75px, transparent 0.75px); background-size: 32px 32px;"
    />

    <div class="relative z-10 mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8 text-center space-y-6">
      <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-400">
        <span class="size-2 rounded-full bg-emerald-400" />
        <span>Sistem Informasi Spasial Terbuka</span>
      </div>

      <h2
        id="cta-heading"
        class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-white leading-tight"
      >
        Kenali wilayah Anda melalui peta.
      </h2>

      <p class="text-base sm:text-lg text-emerald-100/90 max-w-2xl mx-auto leading-relaxed">
        Temukan jaringan jalan, informasi pembangunan, dan data spasial Kabupaten Bojonegoro dalam satu tempat.
      </p>

      <div class="pt-4 flex flex-wrap items-center justify-center gap-4">
        <NuxtLink
          to="/maps"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-black/30 transition-all min-h-[48px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          <UIcon name="i-lucide-map" class="size-5" />
          <span>Buka Peta MELAROSA</span>
        </NuxtLink>

        <NuxtLink
          to="/dataset"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 hover:border-white/40 bg-white/10 hover:bg-white/15 px-5 py-3.5 text-base font-semibold text-white transition-all min-h-[48px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
          <UIcon name="i-lucide-database" class="size-5" />
          <span>Katalog Dataset JIGN</span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

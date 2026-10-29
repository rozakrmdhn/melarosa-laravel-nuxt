<script setup lang="ts">
useSeoMeta({
  title: 'Statistik Geospasial | Melarosa GIS Bojonegoro',
  description: 'Dashboard analitik dan statistik data geospasial Kabupaten Bojonegoro.',
});
</script>

<template>
  <PublicPageTemplate
    title="Statistik Geospasial"
    description="Pusat dashboard analitik, sebaran infrastruktur, dan rekapitulasi data spasial Kabupaten Bojonegoro."
    icon="i-lucide-bar-chart-3"
    container-size="default"
  >
    <template #actions>
      <UButton
        to="/maps"
        icon="i-lucide-map"
        color="neutral"
        variant="outline"
        size="sm"
        class="rounded-xl font-medium"
      >
        Buka Peta Interaktif
      </UButton>
    </template>

    <!-- Placeholder Konten Halaman Statistik (Sedang Disiapkan) -->
    <div class="py-8">
      <div
        class="rounded-2xl border border-dashed border-slate-300 dark:border-white/10 p-8 sm:p-12 text-center bg-slate-50/50 dark:bg-white/[0.02]"
      >
        <div
          class="size-14 mx-auto rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center border border-blue-200/60 dark:border-blue-900/40 shadow-xs mb-4"
        >
          <UIcon name="i-lucide-bar-chart-3" class="size-7" />
        </div>

        <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mb-2">
          Halaman Statistik Sedang Disiapkan
        </h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed mb-6">
          Modul visualisasi data, grafik analitik jalan, dan agregasi spasial wilayah Kabupaten Bojonegoro akan segera hadir pada halaman ini.
        </p>

        <div class="flex items-center justify-center gap-3">
          <UButton
            to="/maps"
            color="primary"
            variant="solid"
            icon="i-lucide-map"
            class="rounded-xl font-medium"
          >
            Eksplorasi Peta
          </UButton>
          <UButton
            to="/"
            color="neutral"
            variant="soft"
            icon="i-lucide-home"
            class="rounded-xl font-medium"
          >
            Kembali ke Beranda
          </UButton>
        </div>
      </div>
    </div>
  </PublicPageTemplate>
</template>


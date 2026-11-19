<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'permission'],
  permission: ['infrastruktur-segmen-verify-kecamatan', 'infrastruktur-segmen-verify-bappeda'],
});

useSeoMeta({
  title: 'Verifikasi Lapangan Segmen | Admin',
});
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <UIcon name="i-lucide-check-check" class="size-5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
            Verifikasi Lapangan Segmen
          </h1>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Pemeriksaan berjenjang usulan segmen infrastruktur fisik tingkat Kecamatan dan Bappeda.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <NuxtLink to="/admin/infrastruktur-segmen">
          <UButton
            icon="i-lucide-git-commit"
            label="Ke Segmen Fisik"
            color="neutral"
            variant="outline"
            size="sm"
            class="min-h-[44px] sm:min-h-[38px] px-3 whitespace-nowrap cursor-pointer"
          />
        </NuxtLink>
      </div>
    </header>

    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-12 text-center space-y-4">
      <div class="size-14 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-check-check" class="size-7" aria-hidden="true" />
      </div>

      <div class="space-y-2 max-w-md mx-auto">
        <UBadge label="Segera Hadir" color="info" variant="subtle" size="md" class="font-semibold" />
        <h2 class="text-base font-bold text-gray-900 dark:text-white">
          Halaman Verifikasi Lapangan Sedang Disiapkan
        </h2>
        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
          Antarmuka verifikasi segmen tingkat Kecamatan dan Bappeda belum tersedia pada rilis saat ini. Anda masih dapat meninjau daftar segmen pada menu Segmen Fisik.
        </p>
      </div>

      <div class="pt-1 flex flex-wrap items-center justify-center gap-2">
        <NuxtLink to="/admin/infrastruktur-segmen">
          <UButton
            label="Buka Segmen Fisik"
            icon="i-lucide-git-commit"
            color="primary"
            size="sm"
            class="min-h-[44px] sm:min-h-[38px] px-4 cursor-pointer font-semibold"
          />
        </NuxtLink>
        <NuxtLink to="/admin">
          <UButton
            label="Kembali ke Dashboard"
            color="neutral"
            variant="outline"
            size="sm"
            class="min-h-[44px] sm:min-h-[38px] px-4 cursor-pointer"
          />
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

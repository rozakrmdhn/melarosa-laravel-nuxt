<script setup lang="ts">
const colorMode = useColorMode();
const auth = useAuthStore();
const { breadcrumbs } = useAdminNavigation();

const wilayahScopeLabel = computed(() => {
  if (auth.user?.desa?.nama_desa) {
    return `Desa ${auth.user.desa.nama_desa}`;
  }
  if (auth.user?.kecamatan?.nama_kecamatan) {
    return `Kec. ${auth.user.kecamatan.nama_kecamatan}`;
  }
  return "Kab. Bojonegoro";
});
</script>

<template>
  <header class="h-14 min-h-14 shrink-0 flex items-center justify-between px-2.5 sm:px-4 border-b border-slate-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] z-10">
    <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
      <!-- Kontrol lipat bilah sisi desktop -->
      <UDashboardSidebarCollapse
        icon="i-lucide-panel-left"
        color="neutral"
        variant="ghost"
        aria-label="Alihkan lebar bilah sisi"
        class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/70 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
      />

      <!-- Tombol buka laci navigasi seluler -->
      <UDashboardSidebarToggle
        icon="i-lucide-panel-left"
        color="neutral"
        variant="ghost"
        aria-label="Buka menu navigasi"
        class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/70 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
      />

      <div class="h-4 w-px bg-slate-200 dark:bg-white/[0.08] shrink-0 hidden sm:block" />

      <!-- Breadcrumb dinamis sesuai menu aktif -->
      <UBreadcrumb
        :items="breadcrumbs"
        separator-icon="i-lucide-chevron-right"
        :ui="{
          root: 'min-w-0',
          list: 'flex items-center gap-1 sm:gap-1.5 text-xs',
          item: 'flex items-center min-w-0',
          link: 'flex items-center gap-1.5 text-xs font-medium transition-colors text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white focus-visible:outline-2 focus-visible:outline-blue-600 rounded',
          linkLeadingIcon: 'size-3.5 shrink-0',
          separator: 'flex items-center text-slate-400 dark:text-slate-500',
          separatorIcon: 'size-3 shrink-0',
        }"
      >
        <template #item="{ item, active }">
          <div class="flex items-center gap-1.5 min-w-0">
            <UIcon
              v-if="item.icon"
              :name="item.icon"
              :class="[
                'size-3.5 shrink-0 transition-colors',
                active
                  ? 'text-blue-700 dark:text-blue-400'
                  : 'text-slate-500 dark:text-slate-400'
              ]"
            />
            <span
              :class="[
                'truncate max-w-[140px] sm:max-w-[260px]',
                active
                  ? 'font-semibold text-slate-900 dark:text-white'
                  : 'font-medium text-slate-600 dark:text-slate-300'
              ]"
            >
              {{ item.label }}
            </span>
          </div>
        </template>
      </UBreadcrumb>
    </div>

    <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
      <ClientOnly>
        <span
          class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/80 dark:bg-slate-800/80 dark:text-slate-300 dark:border-slate-700/70"
        >
          <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
          {{ wilayahScopeLabel }}
        </span>
      </ClientOnly>

      <ClientOnly>
        <UButton
          :icon="colorMode.value === 'dark' ? 'i-lucide-moon' : 'i-lucide-sun'"
          color="neutral"
          variant="ghost"
          size="sm"
          :aria-label="colorMode.value === 'dark' ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
          class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/70 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
          @click="colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'"
        />
        <template #fallback>
          <div class="size-11 sm:size-9 shrink-0" />
        </template>
      </ClientOnly>

      <UButton
        icon="i-lucide-external-link"
        color="neutral"
        variant="ghost"
        size="sm"
        to="/"
        target="_blank"
        aria-label="Buka portal publik di tab baru"
        class="min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/70 focus-visible:outline-2 focus-visible:outline-blue-600"
      />
    </div>
  </header>
</template>


<script lang="ts" setup>
import type { NavigationMenuItem } from '@nuxt/ui';

const { navTree } = usePublicNavigation();

const footerNavItems = computed<NavigationMenuItem[]>(() => {
  return navTree.value.map(item => ({
    label: item.label,
    to: item.to,
    target: item.target,
  }));
});
</script>
<template>
  <UFooter>
    <template #left>
      <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 text-xs text-slate-500 dark:text-slate-400">
        <p>Copyright © {{ new Date().getFullYear() }} Melarosa · Dinas PUBMPR Kab. Bojonegoro</p>
        <span class="hidden sm:inline text-slate-300 dark:text-slate-700">·</span>
        <p>
          Data peta © kontributor
          <a
            href="https://www.openstreetmap.org/copyright"
            target="_blank"
            rel="noopener noreferrer"
            class="underline hover:text-slate-700 dark:hover:text-slate-200"
          >
            OpenStreetMap
          </a>
        </p>
      </div>
    </template>

    <UNavigationMenu :items="footerNavItems" variant="link" />

    <template #right>
      <UButton
        icon="i-lucide-globe"
        color="neutral"
        variant="ghost"
        to="https://geoportal.bojonegorokab.go.id"
        target="_blank"
        aria-label="Geoportal Kabupaten Bojonegoro"
        title="Geoportal Kabupaten Bojonegoro"
      />
    </template>
  </UFooter>
</template>

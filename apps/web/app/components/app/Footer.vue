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
      <p class="text-muted text-sm">Copyright © {{ new Date().getFullYear() }} Melarosa GIS</p>
    </template>

    <UNavigationMenu :items="footerNavItems" variant="link" />

    <template #right>
      <UButton
        icon="i-simple-icons-github"
        color="neutral"
        variant="ghost"
        to="https://github.com/k2so-dev/laravel-nuxt"
        target="_blank"
        aria-label="GitHub"
      />
    </template>
  </UFooter>
</template>

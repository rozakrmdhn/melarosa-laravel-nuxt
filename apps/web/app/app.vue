<script lang="ts" setup>
const route = useRoute();
const isAdmin = computed(() => route.path.startsWith("/admin"));
</script>

<template>
  <UApp>
    <!-- Public Site Layout (with Header and Footer) -->
    <template v-if="!isAdmin">
      <AppHeader />

      <UContainer as="main" class="flex-grow py-4 sm:py-7 flex flex-col">
        <NuxtPage />
      </UContainer>

      <AppFooter />
    </template>

    <!-- Dedicated Admin Panel Layout (Standalone Full-Screen without Default Header & Footer) -->
    <template v-else>
      <div class="h-screen w-screen overflow-hidden flex flex-col bg-gray-50 dark:bg-gray-950">
        <NuxtPage />
      </div>
    </template>

    <NuxtLoadingIndicator class="!opacity-100" :throttle="0" />
  </UApp>
</template>

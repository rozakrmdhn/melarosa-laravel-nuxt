<script lang="ts" setup>
const route = useRoute();
const isAdmin = computed(() => route.path.startsWith("/admin"));
const isAuth = computed(() => route.path.startsWith("/auth"));
const isMaps = computed(() => route.path.startsWith("/maps"));
const isHomepage = computed(() => route.path === '/');
</script>

<template>
  <UApp>
    <!-- Dedicated Admin Panel Layout (Standalone Full-Screen without Default Header & Footer) -->
    <template v-if="isAdmin">
      <div class="h-screen w-screen overflow-hidden flex flex-col bg-gray-50 dark:bg-gray-950">
        <NuxtPage />
      </div>
    </template>

    <!-- Dedicated Auth Layout (Standalone Full-Height Split Screen without Header/Footer) -->
    <template v-else-if="isAuth">
      <div class="min-h-screen w-full flex flex-col bg-white dark:bg-[#070b14]">
        <NuxtPage />
      </div>
    </template>

    <!-- Dedicated Maps Layout (Full Map Canvas, Full Height Full Width with Public Navbar) -->
    <template v-else-if="isMaps">
      <div class="fixed inset-0 overflow-hidden flex flex-col bg-white dark:bg-[#070b14]">
        <AppPublicNavbar class="shrink-0 z-40" />
        <main class="flex-1 w-full h-full relative overflow-hidden">
          <NuxtPage />
        </main>
      </div>
    </template>

    <!-- Public Site Layout (with Header and Footer) -->
    <template v-else>
      <div class="min-h-screen flex flex-col bg-white dark:bg-[#070b14] text-slate-900 dark:text-slate-100">
        <!-- Fixed floating navbar (does not take space in flow) -->
        <AppPublicNavbar />

        <!-- Content: no top padding on homepage (hero overlaps navbar), pad on other pages -->
        <div class="flex-1 flex flex-col w-full" :class="isHomepage ? '' : 'pt-[88px]'">
          <NuxtPage />
        </div>

        <AppFooter class="shrink-0" />
      </div>
    </template>

    <NuxtLoadingIndicator class="!opacity-100" :throttle="0" />
  </UApp>
</template>

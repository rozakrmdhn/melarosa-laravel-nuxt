<script lang="ts" setup>
const route = useRoute();
const isAdmin = computed(() => route.path.startsWith("/admin"));
const isAuth = computed(() => route.path.startsWith("/auth"));
const isMaps = computed(() => route.path.startsWith("/maps"));
const isHomepage = computed(() => route.path === '/');

// Shared state: diisi oleh pages/maps/index.vue saat sidebar terbuka/tertutup di desktop
const mapsSidebarWidth = useState<number>("maps_sidebar_width", () => 0);

onMounted(() => {
  if (import.meta.client) {
    // 1. Cegah double tap to zoom pada iOS Safari saat pengguna mengetuk layar 2 kali dengan cepat
    let lastTouchEnd = 0;
    document.addEventListener(
      "touchend",
      (event: TouchEvent) => {
        const now = Date.now();
        if (now - lastTouchEnd <= 300) {
          const target = event.target as HTMLElement | null;
          const isTextInput = target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable);
          if (!isTextInput) {
            event.preventDefault();
            if (target && typeof target.click === "function") {
              target.click();
            }
          }
        }
        lastTouchEnd = now;
      },
      { passive: false }
    );

    // 2. Cegah pinch to zoom pada viewport halaman HTML (biarkan OpenLayers menangani zoom pada kanvas peta)
    document.addEventListener(
      "gesturestart",
      (event: Event) => {
        const target = event.target as HTMLElement | null;
        if (!target?.closest(".ol-viewport")) {
          event.preventDefault();
        }
      },
      { passive: false }
    );
  }
});
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

    <!-- Dedicated Maps Layout (Wrapper Maps Split Layout with Public Navbar) -->
    <template v-else-if="isMaps">
      <div class="fixed inset-0 overflow-hidden flex flex-col bg-white dark:bg-[#070b14]">
        <AppPublicNavbar />
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

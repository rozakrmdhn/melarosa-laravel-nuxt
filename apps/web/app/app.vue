<script lang="ts" setup>
const route = useRoute();
const nuxtApp = useNuxtApp();
const { toasts: globalToasts, remove: removeGlobalToast } = useToast();
const toasterConfig = {
  duration: 4000,
  progress: true,
  expand: true,
};
let cleanupToastWatchdog: (() => void) | null = null;
onBeforeUnmount(() => {
  cleanupToastWatchdog?.();
  cleanupToastWatchdog = null;
});
const isAdmin = computed(() => route.path.startsWith("/admin"));
const isAuth = computed(() => route.path.startsWith("/auth"));
const isMaps = computed(() => route.path.startsWith("/maps"));
const isHomepage = computed(() => route.path === '/');

// Shared state: diisi oleh pages/maps/index.vue saat sidebar terbuka/tertutup di desktop
const mapsSidebarWidth = useState<number>("maps_sidebar_width", () => 0);

function scrollToPageTop() {
  if (!import.meta.client || route.hash) return;
  window.scrollTo({ top: 0, left: 0, behavior: "instant" });
  document.documentElement.scrollTop = 0;
  document.body.scrollTop = 0;
}

watch(
  () => route.path,
  (newPath, oldPath) => {
    if (newPath !== oldPath) {
      scrollToPageTop();
      nextTick(() => {
        scrollToPageTop();
      });
    }
  }
);

nuxtApp.hook("page:finish", () => {
  scrollToPageTop();
});

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

    // 3. Watchdog notifikasi toast agar selalu menutup otomatis tepat waktu
    //    walaupun kursor di luar browser atau jendela kehilangan fokus (window.blur).
    const DEFAULT_TOAST_MS = 4000;
    const WATCHDOG_GRACE_MS = 350;
    const trackedToasts = new Map<string, { sig: string; expiresAt: number }>();
    let toastPollTimer: ReturnType<typeof setInterval> | null = null;

    const getToastViewportEl = () =>
      document.querySelector<HTMLElement>('[role="region"] ol[data-slot="viewport"]') ||
      document.querySelector<HTMLElement>('ol[data-slot="viewport"]');

    const isHoveringToastCard = () => {
      if (document.hidden || !document.hasFocus()) return false;
      return Boolean(
        document.querySelector('[role="region"] ol[data-slot="viewport"] li[data-slot="root"]:hover') ||
        document.querySelector('ol[data-slot="viewport"] li[data-slot="root"]:hover')
      );
    };

    const resumeRekaToastViewport = () => {
      const viewport = getToastViewportEl();
      if (!viewport || isHoveringToastCard()) return;
      const activeEl = document.activeElement as HTMLElement | null;
      if (activeEl && viewport.contains(activeEl)) {
        activeEl.blur();
      }
      if (typeof PointerEvent !== "undefined") {
        viewport.dispatchEvent(new PointerEvent("pointerleave", { bubbles: false }));
      } else {
        viewport.dispatchEvent(new Event("pointerleave", { bubbles: false }));
      }
    };

    // Cegah window.blur menghentikan (pause) timer progres toast ketika tidak sedang di-hover
    const handleWindowBlurCapture = (event: FocusEvent) => {
      if (globalToasts.value.length > 0 && !isHoveringToastCard()) {
        event.stopImmediatePropagation();
      }
    };

    const checkAndDismissExpiredToasts = () => {
      if (globalToasts.value.length === 0) {
        if (toastPollTimer !== null) {
          clearInterval(toastPollTimer);
          toastPollTimer = null;
        }
        return;
      }

      if (isHoveringToastCard()) {
        // Perpanjang sedikit selama pengguna benar-benar menyorot kartu toast di jendela aktif
        for (const entry of trackedToasts.values()) {
          entry.expiresAt = Math.max(entry.expiresAt, Date.now() + 500);
        }
        return;
      }

      resumeRekaToastViewport();

      const now = Date.now();
      for (const [id, entry] of trackedToasts.entries()) {
        if (now >= entry.expiresAt) {
          trackedToasts.delete(id);
          removeGlobalToast(id);
        }
      }
    };

    watch(
      globalToasts,
      (list) => {
        const activeIds = new Set<string>();

        for (const item of list as Array<Record<string, any>>) {
          if (!item?.id || item.open === false) continue;
          activeIds.add(item.id);

          const rawDuration = item.duration;
          const durationMs = typeof rawDuration === "number" ? rawDuration : DEFAULT_TOAST_MS;
          if (durationMs <= 0 || !Number.isFinite(durationMs)) {
            trackedToasts.delete(item.id);
            continue;
          }

          const sig = `${durationMs}|${item._duplicate ?? 0}|${item._updated ? 1 : 0}`;
          const prev = trackedToasts.get(item.id);
          if (!prev || prev.sig !== sig) {
            trackedToasts.set(item.id, {
              sig,
              expiresAt: Date.now() + durationMs + WATCHDOG_GRACE_MS,
            });
          }
        }

        for (const id of trackedToasts.keys()) {
          if (!activeIds.has(id)) {
            trackedToasts.delete(id);
          }
        }

        if (activeIds.size > 0 && toastPollTimer === null) {
          toastPollTimer = setInterval(checkAndDismissExpiredToasts, 250);
        } else if (activeIds.size === 0 && toastPollTimer !== null) {
          clearInterval(toastPollTimer);
          toastPollTimer = null;
        }

        nextTick(() => {
          setTimeout(resumeRekaToastViewport, 0);
        });
      },
      { deep: true, immediate: true }
    );

    const handleToastWindowEvent = () => {
      setTimeout(checkAndDismissExpiredToasts, 0);
    };

    window.addEventListener("blur", handleWindowBlurCapture, true);
    window.addEventListener("blur", handleToastWindowEvent);
    window.addEventListener("focus", handleToastWindowEvent);
    document.addEventListener("visibilitychange", handleToastWindowEvent);
    document.documentElement.addEventListener("mouseleave", handleToastWindowEvent);

    cleanupToastWatchdog = () => {
      if (toastPollTimer !== null) {
        clearInterval(toastPollTimer);
        toastPollTimer = null;
      }
      window.removeEventListener("blur", handleWindowBlurCapture, true);
      window.removeEventListener("blur", handleToastWindowEvent);
      window.removeEventListener("focus", handleToastWindowEvent);
      document.removeEventListener("visibilitychange", handleToastWindowEvent);
      document.documentElement.removeEventListener("mouseleave", handleToastWindowEvent);
    };
  }
});
</script>

<template>
  <UApp :toaster="toasterConfig">
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
      <div class="min-h-screen flex flex-col bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100">
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

<script lang="ts" setup>
import type { PublicBreadcrumbItem } from '~/types/public-navigation';

interface Props {
  title?: string;
  description?: string;
  icon?: string;
  badge?: string;
  breadcrumbs?: PublicBreadcrumbItem[];
  showBreadcrumb?: boolean;
  showHeader?: boolean;
  containerSize?: 'narrow' | 'default' | 'wide' | 'full';
  padded?: boolean;
  loading?: boolean;
  loadingText?: string;
  error?: Error | string | null;
  empty?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
}

const props = withDefaults(defineProps<Props>(), {
  title: undefined,
  description: undefined,
  icon: undefined,
  badge: undefined,
  breadcrumbs: undefined,
  showBreadcrumb: true,
  showHeader: true,
  containerSize: 'default',
  padded: true,
  loading: false,
  loadingText: 'Memuat data...',
  error: null,
  empty: false,
  emptyTitle: 'Belum ada data',
  emptyDescription: 'Data untuk bagian ini belum tersedia saat ini.',
});

const emit = defineEmits<{
  (e: 'retry'): void;
}>();

const { currentHeader, breadcrumbs: navBreadcrumbs } = usePublicNavigation();

const displayTitle = computed(() => props.title ?? currentHeader.value.title);
const displayDescription = computed(() => props.description ?? currentHeader.value.description);
const displayBreadcrumbs = computed(() => props.breadcrumbs ?? navBreadcrumbs.value);

const containerClass = computed(() => {
  switch (props.containerSize) {
    case 'narrow':
      return 'max-w-4xl mx-auto px-4 sm:px-6';
    case 'wide':
      return 'max-w-7xl 2xl:max-w-[1536px] mx-auto px-4 sm:px-6 lg:px-8';
    case 'full':
      return 'w-full px-4 sm:px-6';
    case 'default':
    default:
      return 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8';
  }
});

const errorMessage = computed(() => {
  if (!props.error) return '';
  if (typeof props.error === 'string') return props.error;
  return props.error.message || 'Terjadi kendala saat memuat data.';
});
</script>

<template>
  <div class="w-full flex-1 flex flex-col">
    <!-- Header Hero Section -->
    <header
      v-if="showHeader"
      class="border-b border-slate-200/80 dark:border-slate-800 bg-white/70 dark:bg-slate-900/40 backdrop-blur-xs transition-colors"
    >
      <div :class="containerClass" class="py-5 sm:py-7">
        <slot name="header">
          <!-- Breadcrumb Trail -->
          <nav
            v-if="showBreadcrumb && displayBreadcrumbs.length > 0"
            aria-label="Jejak Navigasi"
            class="mb-3"
          >
            <ol class="flex items-center flex-wrap gap-1.5 text-xs text-slate-500 dark:text-slate-400">
              <li
                v-for="(crumb, idx) in displayBreadcrumbs"
                :key="crumb.label + idx"
                class="inline-flex items-center gap-1.5"
              >
                <NuxtLink
                  v-if="crumb.to && idx < displayBreadcrumbs.length - 1"
                  :to="crumb.to"
                  class="font-medium text-slate-600 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 rounded-sm"
                >
                  <span class="inline-flex items-center gap-1">
                    <UIcon v-if="crumb.icon" :name="crumb.icon" class="size-3.5 shrink-0" />
                    <span>{{ crumb.label }}</span>
                  </span>
                </NuxtLink>
                <span
                  v-else
                  class="font-semibold text-slate-900 dark:text-slate-100 truncate max-w-[200px] sm:max-w-md"
                  aria-current="page"
                >
                  <span class="inline-flex items-center gap-1">
                    <UIcon v-if="crumb.icon" :name="crumb.icon" class="size-3.5 shrink-0" />
                    <span>{{ crumb.label }}</span>
                  </span>
                </span>

                <!-- Separator -->
                <UIcon
                  v-if="idx < displayBreadcrumbs.length - 1"
                  name="i-lucide-chevron-right"
                  class="size-3.5 text-slate-400 dark:text-slate-600 shrink-0"
                  aria-hidden="true"
                />
              </li>
            </ol>
          </nav>

          <!-- Main Title, Badge & Action Area -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2.5 flex-wrap">
                <UIcon
                  v-if="icon"
                  :name="icon"
                  class="size-6 sm:size-7 text-blue-600 dark:text-blue-400 shrink-0"
                  aria-hidden="true"
                />
                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
                  {{ displayTitle }}
                </h1>
                <slot name="badge">
                  <UBadge
                    v-if="badge"
                    color="primary"
                    variant="subtle"
                    size="sm"
                    class="font-medium"
                  >
                    {{ badge }}
                  </UBadge>
                </slot>
              </div>

              <p
                v-if="displayDescription"
                class="mt-1.5 text-sm sm:text-base text-slate-600 dark:text-slate-400 max-w-3xl leading-relaxed"
              >
                {{ displayDescription }}
              </p>
            </div>

            <!-- Actions Slot -->
            <div v-if="$slots.actions" class="flex items-center gap-2.5 shrink-0 flex-wrap">
              <slot name="actions" />
            </div>
          </div>
        </slot>
      </div>
    </header>

    <!-- Main Content Container with Optional Sidebar -->
    <main
      class="flex-1 w-full"
      :class="[
        padded ? 'py-6 sm:py-8' : '',
        containerClass
      ]"
    >
      <!-- Error State (R-27) -->
      <section
        v-if="error"
        role="alert"
        aria-live="assertive"
        class="rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/20 p-5 sm:p-6"
      >
        <slot name="error" :error="error" :retry="() => emit('retry')">
          <div class="flex items-start gap-3.5">
            <UIcon
              name="i-lucide-alert-circle"
              class="size-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5"
              aria-hidden="true"
            />
            <div class="flex-1">
              <h2 class="text-base font-semibold text-red-900 dark:text-red-200">
                Gagal memuat konten
              </h2>
              <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                {{ errorMessage }}
              </p>
              <div class="mt-4 flex items-center gap-3">
                <UButton
                  label="Muat Ulang"
                  color="error"
                  variant="solid"
                  size="sm"
                  icon="i-lucide-refresh-cw"
                  class="cursor-pointer min-h-[44px] min-w-[44px]"
                  @click="emit('retry')"
                />
                <UButton
                  label="Kembali ke Beranda"
                  to="/"
                  color="neutral"
                  variant="outline"
                  size="sm"
                  class="cursor-pointer min-h-[44px]"
                />
              </div>
            </div>
          </div>
        </slot>
      </section>

      <!-- Loading State (R-27) -->
      <section
        v-else-if="loading"
        role="status"
        aria-live="polite"
        class="py-12 sm:py-16 flex flex-col items-center justify-center text-center"
      >
        <slot name="loading">
          <UIcon
            name="i-lucide-loader-2"
            class="size-8 text-blue-600 dark:text-blue-400 animate-spin"
            aria-hidden="true"
          />
          <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-400">
            {{ loadingText }}
          </p>
          <span class="sr-only">Sedang memproses data</span>
        </slot>
      </section>

      <!-- Empty State (R-27) -->
      <section
        v-else-if="empty"
        role="status"
        class="py-12 sm:py-16 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 p-6 sm:p-8 text-center"
      >
        <slot name="empty">
          <UIcon
            name="i-lucide-inbox"
            class="size-10 text-slate-400 dark:text-slate-600 mx-auto"
            aria-hidden="true"
          />
          <h2 class="mt-3 text-base font-semibold text-slate-900 dark:text-slate-100">
            {{ emptyTitle }}
          </h2>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto">
            {{ emptyDescription }}
          </p>
          <div class="mt-5">
            <UButton
              label="Eksplorasi Peta"
              to="/maps"
              color="primary"
              variant="outline"
              size="sm"
              class="cursor-pointer min-h-[44px]"
            />
          </div>
        </slot>
      </section>

      <!-- Content Layout with Optional Sidebar -->
      <div v-else class="w-full">
        <div v-if="$slots.sidebar" class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
          <aside class="lg:col-span-3 w-full order-last lg:order-first">
            <slot name="sidebar" />
          </aside>
          <div class="lg:col-span-9 w-full min-w-0">
            <slot />
          </div>
        </div>

        <div v-else class="w-full">
          <slot />
        </div>
      </div>
    </main>

    <!-- Page Footer Slot -->
    <footer v-if="$slots.footer" :class="containerClass" class="py-4 border-t border-slate-200 dark:border-slate-800">
      <slot name="footer" />
    </footer>
  </div>
</template>

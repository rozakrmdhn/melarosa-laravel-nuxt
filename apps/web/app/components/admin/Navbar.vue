<script setup lang="ts">
const colorMode = useColorMode();
const { breadcrumbs } = useAdminNavigation();
</script>

<template>
  <header class="h-14 min-h-14 shrink-0 flex items-center justify-between px-3 sm:px-4 border-b border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] z-10">
    <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
      <!-- Desktop sidebar collapse control -->
      <UDashboardSidebarCollapse
        icon="i-lucide-panel-left"
        color="neutral"
        variant="ghost"
        aria-label="Toggle sidebar collapse"
        class="text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/60 shrink-0 cursor-pointer"
      />

      <!-- Mobile navigation drawer toggle -->
      <UDashboardSidebarToggle
        icon="i-lucide-panel-left"
        color="neutral"
        variant="ghost"
        aria-label="Toggle navigation drawer"
        class="text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/60 shrink-0 cursor-pointer"
      />

      <div class="h-4 w-px bg-gray-200/70 dark:bg-white/[0.08] shrink-0 hidden sm:block" />

      <!-- Dynamic Breadcrumb based on Sidebar Menu -->
      <UBreadcrumb
        :items="breadcrumbs"
        separator-icon="i-lucide-chevron-right"
        :ui="{
          root: 'min-w-0',
          list: 'flex items-center gap-1 sm:gap-1.5 text-xs',
          item: 'flex items-center min-w-0',
          link: 'flex items-center gap-1.5 text-xs font-medium transition-colors text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white',
          linkLeadingIcon: 'size-3.5 shrink-0',
          separator: 'flex items-center text-gray-300 dark:text-gray-600',
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
                  ? 'text-blue-600 dark:text-blue-400'
                  : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-600 dark:group-hover:text-gray-300'
              ]"
            />
            <span
              :class="[
                'truncate max-w-[140px] sm:max-w-[240px]',
                active
                  ? 'font-semibold text-gray-900 dark:text-white'
                  : 'font-medium text-gray-500 dark:text-gray-400 group-hover:text-gray-800 dark:group-hover:text-gray-200'
              ]"
            >
              {{ item.label }}
            </span>
          </div>
        </template>
      </UBreadcrumb>
    </div>

    <div class="flex items-center gap-1.5">
      <UButton
        :icon="colorMode.value === 'dark' ? 'i-lucide-moon' : 'i-lucide-sun'"
        color="neutral"
        variant="ghost"
        size="sm"
        aria-label="Toggle theme appearance"
        class="text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/60"
        @click="colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'"
      />

      <UButton
        icon="i-lucide-external-link"
        color="neutral"
        variant="ghost"
        size="sm"
        to="/"
        target="_blank"
        aria-label="View public site"
        class="text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/60"
      />
    </div>
  </header>
</template>


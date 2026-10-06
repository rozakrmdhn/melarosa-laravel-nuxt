<script setup lang="ts">
// UDashboardSidebar reads from UDashboardGroup context automatically.
// - Desktop: fixed width 16rem (256px), collapses to icon strip 4rem (64px)
// - Mobile: slideover drawer with backdrop, close button, and autoClose on route change
</script>

<template>
  <UDashboardSidebar
    id="admin-sidebar"
    collapsible
    :resizable="false"
    :default-size="256"
    :collapsed-size="64"
    :min-size="256"
    :max-size="256"
    toggle-side="right"
    :ui="{
      root: 'w-64 data-[collapsed=true]:w-16 min-w-16 transition-[width] duration-300 ease-in-out overflow-hidden will-change-[width] bg-white dark:bg-[#0b0f19] border-e border-gray-200/70 dark:border-white/[0.08]',
      header: 'h-14 min-h-14 flex items-center px-3 border-b border-gray-200/70 dark:border-white/[0.08]',
      body: 'flex-1 flex flex-col px-3 py-2 gap-1 overflow-y-auto overflow-x-hidden',
      footer: 'h-14 min-h-14 flex items-center px-3 border-t border-gray-200/70 dark:border-white/[0.08]',
      content: 'lg:hidden bg-white dark:bg-[#0b0f19] w-[280px] max-w-[85vw] flex flex-col border-e border-gray-200/70 dark:border-white/[0.08] shadow-2xl',
      overlay: 'lg:hidden backdrop-blur-sm bg-gray-900/50 dark:bg-black/60',
      toggle: 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white shrink-0 size-8 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/60',
    }"
  >
    <template #header="{ collapsed }">
      <AdminSidebarHeader :state="collapsed ? 'collapsed' : 'expanded'" />
    </template>

    <template #default="{ collapsed }">
      <AdminSidebarNav :state="collapsed ? 'collapsed' : 'expanded'" />
    </template>

    <template #footer="{ collapsed }">
      <AdminSidebarFooter :state="collapsed ? 'collapsed' : 'expanded'" />
    </template>
  </UDashboardSidebar>
</template>

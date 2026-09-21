<script setup lang="ts">
const open = defineModel<boolean>("open", { default: true });
</script>

<template>
  <USidebar
    v-model:open="open"
    collapsible="icon"
    :ui="{
      root: '[--sidebar-width:16rem] [--sidebar-width-icon:4.25rem] [--ui-header-height:3.5rem]',
      container: 'bg-white dark:bg-[#0b0f19] border-e border-gray-200/70 dark:border-white/[0.08] shadow-none',
      inner: 'bg-transparent border-0 divide-y-0 shadow-none',
      header: 'h-14 min-h-14 flex items-center px-3 border-0',
      body: 'flex-1 flex flex-col px-3 py-2 gap-1 overflow-y-auto overflow-x-hidden',
      footer: 'h-14 min-h-14 flex items-center px-3 border-0',
      rail: 'hidden',
    }"
  >
    <template #header="{ state }">
      <AdminSidebarHeader :state="state" />
    </template>

    <template #default="{ state }">
      <AdminSidebarNav :state="state" />
    </template>

    <template #footer="{ state }">
      <AdminSidebarFooter :state="state" />
    </template>
  </USidebar>
</template>

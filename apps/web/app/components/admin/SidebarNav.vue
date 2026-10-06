<script setup lang="ts">
interface Props {
  state?: "collapsed" | "expanded";
}

const props = withDefaults(defineProps<Props>(), {
  state: "expanded",
});

const isCollapsed = computed(() => props.state === "collapsed");
const { getNavItems } = useAdminNavigation();
const navItems = computed(() => getNavItems(props.state));
</script>

<template>
  <UNavigationMenu
    :items="navItems"
    :collapsed="isCollapsed"
    orientation="vertical"
    :ui="{
      root: 'w-full',
      list: isCollapsed ? 'flex flex-col items-center gap-1 w-full' : 'flex flex-col gap-0.5 w-full',
      item: isCollapsed ? 'w-full flex justify-center' : 'w-full',
      link: isCollapsed
        ? 'size-9 p-0 flex items-center justify-center rounded-md transition-colors text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/60 data-[active=true]:bg-blue-500/10 dark:data-[active=true]:bg-blue-500/15 data-[active=true]:text-blue-600 dark:data-[active=true]:text-blue-400'
        : 'w-full flex items-center px-3 py-2 rounded-md transition-colors text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/60 data-[active=true]:bg-blue-500/10 dark:data-[active=true]:bg-blue-500/15 data-[active=true]:text-blue-600 dark:data-[active=true]:text-blue-400 font-medium text-sm',
      linkLeadingIcon: 'size-4.5 shrink-0',
      linkLabel: isCollapsed ? 'hidden' : 'truncate text-sm font-medium ms-2.5',
      linkTrailing: isCollapsed ? 'hidden' : 'ms-auto inline-flex items-center text-gray-400 dark:text-gray-500 text-xs',
      childList: 'ms-3.5 ps-3 border-s border-gray-200/70 dark:border-white/[0.08] space-y-0.5 my-1',
      childLink: 'w-full flex items-center px-2.5 py-1.5 rounded-md transition-colors text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/70 dark:hover:bg-gray-800/50 text-sm data-[active=true]:bg-blue-500/10 dark:data-[active=true]:bg-blue-500/15 data-[active=true]:text-blue-600 dark:data-[active=true]:text-blue-400 data-[active=true]:font-medium',
      childLinkIcon: 'size-4 shrink-0 me-2 text-gray-400 dark:text-gray-500 data-[active=true]:text-blue-600 dark:data-[active=true]:text-blue-400',
    }"
  />
</template>

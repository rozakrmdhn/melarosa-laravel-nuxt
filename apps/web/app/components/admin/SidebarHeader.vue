<script setup lang="ts">
interface Props {
  state?: "collapsed" | "expanded";
}

const props = withDefaults(defineProps<Props>(), {
  state: "expanded",
});

const isCollapsed = computed(() => props.state === "collapsed");
</script>

<template>
  <NuxtLink
    to="/admin"
    :aria-label="isCollapsed ? 'Beranda Admin Melarosa' : undefined"
    :class="[
      'group flex items-center rounded-lg transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:focus-visible:outline-blue-400',
      isCollapsed
        ? 'size-11 mx-auto justify-center hover:bg-slate-100 dark:hover:bg-slate-800/70'
        : 'w-full min-h-11 gap-2.5 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800/70',
    ]"
  >
    <div class="size-8 rounded-md bg-blue-50 dark:bg-blue-950/60 border border-blue-200/70 dark:border-blue-800/60 flex items-center justify-center shrink-0 p-1">
      <img
        src="/logo-kabupaten.png"
        alt="Lambang Kabupaten Bojonegoro"
        class="size-full object-contain"
      >
    </div>

    <div v-if="!isCollapsed" class="flex flex-col text-left min-w-0">
      <span class="truncate font-bold text-sm tracking-tight text-slate-900 dark:text-slate-50 leading-tight">
        Melarosa
      </span>
      <span class="truncate text-xs font-medium text-slate-600 dark:text-slate-400 leading-tight mt-0.5">
        Satu Data Spasial Bojonegoro
      </span>
    </div>
  </NuxtLink>
</template>

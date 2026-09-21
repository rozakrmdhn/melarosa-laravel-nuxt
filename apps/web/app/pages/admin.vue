<script setup lang="ts">
definePageMeta({
  middleware: ["auth", "role-admin"],
});

const open = ref(true);
</script>

<template>
  <div class="flex flex-1 h-screen w-screen overflow-hidden bg-gray-100 dark:bg-[#070b14] text-gray-900 dark:text-gray-100 antialiased">
    <AdminSidebar v-model:open="open" />

    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-gray-50/70 dark:bg-[#070b14]">
      <AdminNavbar v-model:open="open" />

      <main
        :class="[
          'flex-1 min-w-0 text-gray-900 dark:text-gray-100',
          $route.meta.fullBleed
            ? 'p-0 overflow-hidden relative'
            : 'p-6 overflow-y-auto bg-gray-50/70 dark:bg-[#070b14]',
        ]"
      >
        <NuxtPage />
      </main>
    </div>
  </div>
</template>

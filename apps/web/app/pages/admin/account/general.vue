<script lang="ts" setup>
useSeoMeta({
  title: "Account Profile - Admin",
});
</script>

<template>
  <UCard
    :ui="{
      root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg overflow-hidden shadow-none',
      body: 'grid grid-cols-12 gap-6 md:gap-8 p-6',
    }"
  >
    <div class="col-span-12 lg:col-span-4">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">
        Profile Information
      </h3>
      <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
        Update your account's public name, avatar photo, and primary contact email address.
      </p>
    </div>

    <div class="col-span-12 lg:col-span-8">
      <AccountUpdateProfile />
    </div>

    <USeparator class="col-span-12 border-gray-200/70 dark:border-white/[0.08]" />

    <div class="col-span-12 lg:col-span-4">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">
        Update Password
      </h3>
      <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
        Ensure your administrative access credentials use a long, secure passphrase.
      </p>
    </div>

    <div class="col-span-12 lg:col-span-8">
      <AccountUpdatePassword />
    </div>
  </UCard>
</template>

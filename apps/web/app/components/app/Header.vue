<script lang="ts" setup>
const auth = useAuthStore();

const userItems = computed(() => [
  [
    {
      slot: "overview",
    },
  ],
  [
    {
      label: "Dashboard",
      to: "/admin",
      icon: "i-heroicons-squares-2x2",
    },
  ],
  [
    {
      label: "Account",
      to: "/admin/account/general",
      icon: "i-heroicons-user",
    },
    {
      label: "Devices",
      to: "/admin/account/devices",
      icon: "i-heroicons-device-phone-mobile",
    },
  ],
  [
    {
      label: "Sign out",
      onSelect() {
        auth.logout();
      },
      class: 'cursor-pointer',
      icon: "i-heroicons-arrow-left-on-rectangle",
    },
  ],
]);

const items = computed(() => [
  {
    label: 'Home',
    icon: 'i-lucide-house',
    to: '/',
  },
  {
    label: 'Maps',
    icon: 'i-lucide-map',
    to: '/maps',
  },
]);

const isSideOpen = ref(false);
</script>
<template>
  <UHeader toggle-side="left">
    <template #title>
      <AppLogo />
    </template>

    <UNavigationMenu :items="items" />

    <template #right>
      <UColorModeButton />

      <UDropdownMenu
        v-if="auth.logged"
        :items="userItems"
        :content="{ side: 'bottom', align: 'end' }"
      >
        <ULink class="cursor-pointer">
          <UAvatar
            icon="i-heroicons-user"
            class="rounded-lg"
            size="md"
            :src="$storage(auth.user.avatar)"
            :alt="auth.user.name"
          />
        </ULink>

        <template #overview>
          <div class="text-left">
            <p>Signed in as</p>
            <p class="truncate font-medium text-neutral-900 dark:text-white">
              {{ auth.user.email }}
            </p>
          </div>
        </template>
      </UDropdownMenu>
      <UButton v-else label="Log In" to="/auth/login" variant="ghost" color="neutral" />
    </template>

    <template #body>
      <UNavigationMenu :items="items" orientation="vertical" class="-mx-2.5" />
    </template>
  </UHeader>
</template>

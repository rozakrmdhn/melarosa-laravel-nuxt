<script lang="ts" setup>
const auth = useAuthStore();

useSeoMeta({
  title: "Admin Dashboard",
});

interface RolesData {
  ok: boolean;
  roles: Array<{ id: number; name: string; users_count: number }>;
  permissions: Array<{ id: number; name: string }>;
}

interface UsersData {
  ok: boolean;
  users: {
    total: number;
  };
}

const { data: rolesData, status: rolesStatus } = useHttp<RolesData>("admin/roles");
const { data: usersData, status: usersStatus } = useHttp<UsersData>("admin/users");

const loading = computed(
  () => rolesStatus.value === "pending" || usersStatus.value === "pending"
);

const totalRoles = computed(() => rolesData.value?.roles?.length ?? 0);
const totalPermissions = computed(() => rolesData.value?.permissions?.length ?? 0);
const totalUsers = computed(() => usersData.value?.users?.total ?? 0);

const stats = computed(() => [
  {
    title: "Total Roles",
    value: totalRoles.value,
    icon: "i-heroicons-shield-check",
    to: "/admin/roles",
    description: "Configured access roles",
  },
  {
    title: "Permissions",
    value: totalPermissions.value,
    icon: "i-heroicons-key",
    to: "/admin/permissions",
    description: "Discrete system capabilities",
  },
  {
    title: "User Accounts",
    value: totalUsers.value,
    icon: "i-heroicons-users",
    to: "/admin/users",
    description: "Registered members",
  },
]);

const quickLinks = [
  {
    title: "Role Configuration",
    description: "Create new roles, customize permission bundles, or manage role assignments.",
    icon: "i-heroicons-shield-check",
    to: "/admin/roles",
    cta: "Manage Roles",
  },
  {
    title: "System Permissions",
    description: "Define granular capability tokens required for route and action authorization.",
    icon: "i-heroicons-key",
    to: "/admin/permissions",
    cta: "View Permissions",
  },
  {
    title: "User Access Control",
    description: "Inspect registered user accounts and grant or revoke security privileges.",
    icon: "i-heroicons-users",
    to: "/admin/users",
    cta: "Assign User Roles",
  },
];
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome Header Banner -->
    <div class="p-5 rounded-lg border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
          Welcome back, {{ auth.user.name || "Administrator" }}
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Admin dashboard for application role management and security permissions.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UBadge label="Admin Access Verified" color="primary" variant="subtle" size="md" />
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <NuxtLink
        v-for="stat in stats"
        :key="stat.title"
        :to="stat.to"
        class="block p-4 sm:p-5 rounded-lg border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] hover:border-emerald-500/50 dark:hover:border-emerald-500/40 transition-colors shadow-none group"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            {{ stat.title }}
          </span>
          <div class="size-8 rounded-md bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-300 flex items-center justify-center group-hover:bg-emerald-500/15 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
            <UIcon :name="stat.icon" class="size-4" />
          </div>
        </div>

        <div class="mt-3">
          <div v-if="loading" class="h-8 w-16 bg-gray-200 dark:bg-gray-800 animate-pulse rounded" />
          <div v-else class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ stat.value }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ stat.description }}
          </div>
        </div>
      </NuxtLink>
    </div>

    <!-- Quick Navigation Modules -->
    <div class="space-y-3">
      <h2 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">
        Management Modules
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div
          v-for="item in quickLinks"
          :key="item.title"
          class="p-4 sm:p-5 rounded-lg border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex flex-col justify-between space-y-4 shadow-none"
        >
          <div class="space-y-2.5">
            <div class="size-8 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <UIcon :name="item.icon" class="size-4.5" />
            </div>
            <h3 class="font-semibold text-sm text-gray-900 dark:text-white">
              {{ item.title }}
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
              {{ item.description }}
            </p>
          </div>

          <div>
            <UButton
              :label="item.cta"
              :to="item.to"
              size="xs"
              color="neutral"
              variant="subtle"
              class="w-full justify-center dark:bg-gray-800/80 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

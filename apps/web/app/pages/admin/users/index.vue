<script lang="ts" setup>
interface Role {
  id: number;
  name: string;
}

interface UserItem {
  id: string;
  name: string;
  email: string;
  avatar: string | null;
  created_at: string;
  roles: Role[];
}

interface UsersResponse {
  ok: boolean;
  users: {
    data: UserItem[];
    current_page: number;
    last_page: number;
    total: number;
  };
  roles: Role[];
}

const auth = useAuthStore();
const toast = useToast();
const dayjs = useDayjs();

useSeoMeta({
  title: "User Access Management",
});

const search = ref("");
const page = ref(1);

const queryParams = computed(() => ({
  page: page.value,
  search: search.value || undefined,
}));

const { data, status, refresh, error } = useHttp<UsersResponse>("admin/users", {
  query: queryParams,
  watch: [queryParams],
});

const loading = computed(() => status.value === "pending");

// Manage Roles Modal state
const isModalOpen = ref(false);
const selectedUser = ref<UserItem | null>(null);
const selectedRoles = ref<string[]>([]);
const submitting = ref(false);

function openEditUserRoles(user: UserItem) {
  selectedUser.value = user;
  selectedRoles.value = user.roles.map((r) => r.name);
  isModalOpen.value = true;
}

async function handleUpdateRoles() {
  if (!selectedUser.value) return;

  if (selectedRoles.value.length === 0) {
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "At least one role must be selected.",
      color: "error",
    });
    return;
  }

  submitting.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/users/${selectedUser.value.id}/roles`,
      {
        method: "PUT",
        body: {
          roles: selectedRoles.value,
        },
      }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "User roles updated successfully.",
        color: "success",
      });
      isModalOpen.value = false;
      await refresh();

      // If updating currently logged in user, refresh auth state
      if (selectedUser.value.email === auth.user.email) {
        await auth.fetchUser();
      }
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Failed to update user roles.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    submitting.value = false;
  }
}

const columns = [
  {
    accessorKey: "name",
    header: "User",
  },
  {
    accessorKey: "roles",
    header: "Assigned Roles",
  },
  {
    accessorKey: "created_at",
    header: "Joined",
    class: "w-36 text-center",
  },
  {
    id: "actions",
    header: "Action",
    class: "w-28 text-right",
  },
];
</script>

<template>
  <div class="space-y-4">
    <!-- Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          User Access Control
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Assign and modify security roles for registered users.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UInput
          v-model="search"
          icon="i-heroicons-magnifying-glass"
          placeholder="Search name or email..."
          size="sm"
          class="w-64"
        />
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="p-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 flex items-center justify-between"
    >
      <div class="flex items-center gap-2 text-sm">
        <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5 flex-shrink-0" />
        <span>Failed to load user access data. Please try again.</span>
      </div>
      <UButton
        label="Retry"
        size="xs"
        color="error"
        variant="subtle"
        @click="refresh"
      />
    </div>

    <!-- Users Table Card -->
    <UCard
      :ui="{
        root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg overflow-hidden shadow-none',
        body: 'p-0 sm:p-0',
      }"
    >
      <UTable
        :data="data?.users?.data || []"
        :columns="columns"
        :loading="loading"
        loading-color="primary"
      >
        <!-- User Info Column -->
        <template #name-cell="{ row }">
          <div class="flex items-center gap-3 py-1">
            <div
              class="size-8 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs flex-shrink-0"
            >
              {{ row.original.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <div class="font-medium text-gray-900 dark:text-white">
                {{ row.original.name }}
                <UBadge
                  v-if="row.original.email === auth.user.email"
                  label="You"
                  color="neutral"
                  variant="subtle"
                  size="xs"
                  class="ms-1 dark:bg-gray-800 dark:text-gray-300"
                />
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ row.original.email }}
              </div>
            </div>
          </div>
        </template>

        <!-- Roles Column -->
        <template #roles-cell="{ row }">
          <div class="flex flex-wrap gap-1">
            <UBadge
              v-for="role in row.original.roles"
              :key="role.id"
              :label="role.name"
              :color="role.name === 'admin' ? 'primary' : 'neutral'"
              variant="subtle"
              size="xs"
              class="dark:bg-gray-800/80 dark:text-gray-300"
            />
            <span
              v-if="!row.original.roles?.length"
              class="text-xs text-gray-400 italic"
            >
              No roles assigned
            </span>
          </div>
        </template>

        <!-- Joined Column -->
        <template #created_at-cell="{ row }">
          <div class="text-center text-xs text-gray-500 dark:text-gray-400">
            {{ dayjs(row.original.created_at).format("MMM D, YYYY") }}
          </div>
        </template>

        <!-- Action Column -->
        <template #actions-cell="{ row }">
          <div class="flex items-center justify-end">
            <UButton
              label="Edit Roles"
              size="xs"
              color="neutral"
              variant="subtle"
              icon="i-heroicons-shield-check"
              class="dark:bg-gray-800/80 dark:hover:bg-gray-800 dark:text-gray-200"
              @click="openEditUserRoles(row.original)"
            />
          </div>
        </template>

        <!-- Empty State -->
        <template #empty>
          <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            <UIcon name="i-heroicons-user-group" class="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p class="font-medium">No users found</p>
            <p class="text-xs mt-1">Try adjusting your search query.</p>
          </div>
        </template>
      </UTable>

      <!-- Pagination Footer -->
      <template v-if="(data?.users?.last_page ?? 1) > 1" #footer>
        <div class="flex items-center justify-between px-4 py-3">
          <span class="text-xs text-gray-500 dark:text-gray-400">
            Showing page {{ page }} of {{ data?.users?.last_page }} ({{ data?.users?.total }} users)
          </span>
          <UPagination
            v-model:page="page"
            :total="data?.users?.total || 0"
            :items-per-page="data?.users?.per_page || 10"
            size="xs"
          />
        </div>
      </template>
    </UCard>

    <!-- Assign Roles Modal -->
    <UModal
      v-model:open="isModalOpen"
      :title="`Assign Roles: ${selectedUser?.name}`"
      description="Update security roles assigned to this user."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div class="space-y-4">
          <div class="p-3 bg-gray-50 dark:bg-[#070b14]/60 rounded-md border border-gray-200/70 dark:border-white/[0.08] flex items-center gap-3">
            <div class="size-8 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs flex-shrink-0">
              {{ selectedUser?.name?.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ selectedUser?.name }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ selectedUser?.email }}
              </p>
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-900 dark:text-white">
              Available Roles
            </label>

            <div class="border border-gray-200/70 dark:border-white/[0.08] rounded-md p-3 space-y-1 bg-gray-50/50 dark:bg-[#070b14]/50">
              <div
                v-for="role in data?.roles ?? []"
                :key="role.id"
                class="flex items-center gap-2 hover:bg-gray-100/70 dark:hover:bg-gray-800/60 p-2 rounded-md transition-colors"
              >
                <UCheckbox
                  v-model="selectedRoles"
                  :value="role.name"
                  :label="role.name"
                  :disabled="selectedUser?.email === auth.user.email && role.name === 'admin'"
                  class="text-gray-800 dark:text-gray-200 text-xs"
                />
              </div>
            </div>

            <p
              v-if="selectedUser?.email === auth.user.email"
              class="text-xs text-amber-600 dark:text-amber-400 mt-1"
            >
              Note: You cannot revoke the admin role from your current active session.
            </p>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Cancel"
            color="neutral"
            variant="ghost"
            @click="isModalOpen = false"
          />
          <UButton
            label="Update Roles"
            color="primary"
            :loading="submitting"
            @click="handleUpdateRoles"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

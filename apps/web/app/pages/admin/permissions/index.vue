<script lang="ts" setup>
interface Permission {
  id: number;
  name: string;
  guard_name: string;
  roles_count?: number;
}

interface PermissionsResponse {
  ok: boolean;
  permissions: Permission[];
}

const toast = useToast();
const { data, status, refresh, error } = useHttp<PermissionsResponse>("admin/permissions");
const loading = computed(() => status.value === "pending");

useSeoMeta({
  title: "Permissions Management",
});

// Search filter
const search = ref("");
const filteredPermissions = computed(() => {
  const all = data.value?.permissions ?? [];
  if (!search.value.trim()) return all;
  const q = search.value.toLowerCase();
  return all.filter((p) => p.name.toLowerCase().includes(q));
});

// Create Modal state
const isModalOpen = ref(false);
const submitting = ref(false);
const formState = reactive({
  name: "",
});

// Delete Modal state
const isDeleteModalOpen = ref(false);
const permissionToDelete = ref<Permission | null>(null);
const deleting = ref(false);

function openCreateModal() {
  formState.name = "";
  isModalOpen.value = true;
}

async function handleCreatePermission() {
  const trimmed = formState.name.trim().toLowerCase();
  if (!trimmed) {
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Permission name is required.",
      color: "error",
    });
    return;
  }

  submitting.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>("admin/permissions", {
      method: "POST",
      body: { name: trimmed },
    });

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Permission created successfully.",
        color: "success",
      });
      isModalOpen.value = false;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Failed to create permission.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    submitting.value = false;
  }
}

function confirmDelete(perm: Permission) {
  permissionToDelete.value = perm;
  isDeleteModalOpen.value = true;
}

async function handleDeletePermission() {
  if (!permissionToDelete.value) return;

  deleting.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/permissions/${permissionToDelete.value.id}`,
      {
        method: "DELETE",
      }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Permission deleted successfully.",
        color: "success",
      });
      isDeleteModalOpen.value = false;
      permissionToDelete.value = null;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Failed to delete permission.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    deleting.value = false;
  }
}

const columns = [
  {
    accessorKey: "name",
    header: "Permission Name",
  },
  {
    accessorKey: "roles_count",
    header: "Assigned to Roles",
    class: "w-44 text-center",
  },
  {
    id: "actions",
    header: "Actions",
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
          System Permissions
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          List of discrete privileges available across application modules.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UInput
          v-model="search"
          icon="i-heroicons-magnifying-glass"
          placeholder="Search permissions..."
          size="sm"
          class="w-48 sm:w-64"
        />

        <UButton
          label="New Permission"
          icon="i-heroicons-plus"
          color="primary"
          size="sm"
          @click="openCreateModal"
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
        <span>Failed to load permissions. Please check backend connection.</span>
      </div>
      <UButton
        label="Retry"
        size="xs"
        color="error"
        variant="subtle"
        @click="refresh"
      />
    </div>

    <!-- Permissions Table -->
    <UCard
      :ui="{
        root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg overflow-hidden shadow-none',
        body: 'p-0 sm:p-0',
      }"
    >
      <UTable
        :data="filteredPermissions"
        :columns="columns"
        :loading="loading"
        loading-color="primary"
      >
        <!-- Permission Name Cell -->
        <template #name-cell="{ row }">
          <div class="flex items-center gap-2 font-mono text-xs">
            <UIcon name="i-heroicons-key" class="w-4 h-4 text-emerald-500/70 dark:text-emerald-400/80" />
            <span class="text-gray-900 dark:text-white font-medium">
              {{ row.original.name }}
            </span>
          </div>
        </template>

        <!-- Roles Count Cell -->
        <template #roles_count-cell="{ row }">
          <div class="text-center font-medium text-sm text-gray-600 dark:text-gray-300">
            {{ row.original.roles_count ?? 0 }} roles
          </div>
        </template>

        <!-- Actions Cell -->
        <template #actions-cell="{ row }">
          <div class="flex items-center justify-end">
            <UButton
              icon="i-heroicons-trash"
              size="xs"
              color="error"
              variant="ghost"
              aria-label="Delete permission"
              @click="confirmDelete(row.original)"
            />
          </div>
        </template>

        <!-- Empty State -->
        <template #empty>
          <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            <UIcon name="i-heroicons-key" class="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p class="font-medium">No permissions found</p>
            <p class="text-xs mt-1">Create permissions to assign them to roles.</p>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- Create Permission Modal -->
    <UModal
      v-model:open="isModalOpen"
      title="Create New Permission"
      description="Define a granular capability key (e.g. posts.publish, orders.refund)."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <form class="space-y-4" @submit.prevent="handleCreatePermission">
          <UFormField label="Permission Identifier" required>
            <UInput
              v-model="formState.name"
              placeholder="e.g. reports.export, users.ban"
              class="w-full font-mono text-sm"
              autofocus
            />
            <template #help>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Format: module.action (lowercase with dots or hyphens)
              </p>
            </template>
          </UFormField>
        </form>
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
            label="Create Permission"
            color="primary"
            :loading="submitting"
            @click="handleCreatePermission"
          />
        </div>
      </template>
    </UModal>

    <!-- Delete Confirmation Modal -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Delete Permission"
      description="Are you sure you want to delete this permission? It will be removed from all roles."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          You are about to delete permission <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">{{ permissionToDelete?.name }}</code>.
        </p>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Cancel"
            color="neutral"
            variant="ghost"
            @click="isDeleteModalOpen = false"
          />
          <UButton
            label="Delete Permission"
            color="error"
            :loading="deleting"
            @click="handleDeletePermission"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

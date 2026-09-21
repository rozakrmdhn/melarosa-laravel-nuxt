<script lang="ts" setup>
interface Permission {
  id: number;
  name: string;
  guard_name: string;
}

interface Role {
  id: number;
  name: string;
  guard_name: string;
  users_count: number;
  permissions: Permission[];
}

interface RolesResponse {
  ok: boolean;
  roles: Role[];
  permissions: Permission[];
}

const toast = useToast();
const { data, status, refresh, error } = useHttp<RolesResponse>("admin/roles");
const loading = computed(() => status.value === "pending");

useSeoMeta({
  title: "Roles Management",
});

// Modal state
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingRoleId = ref<number | null>(null);
const submitting = ref(false);

const formState = reactive({
  name: "",
  selectedPermissions: [] as string[],
});

// Delete modal state
const isDeleteModalOpen = ref(false);
const roleToDelete = ref<Role | null>(null);
const deleting = ref(false);

// Filter permissions in modal
const permissionSearch = ref("");
const filteredPermissions = computed(() => {
  const all = data.value?.permissions ?? [];
  if (!permissionSearch.value.trim()) return all;
  const q = permissionSearch.value.toLowerCase();
  return all.filter((p) => p.name.toLowerCase().includes(q));
});

function openCreateModal() {
  isEditing.value = false;
  editingRoleId.value = null;
  formState.name = "";
  formState.selectedPermissions = [];
  permissionSearch.value = "";
  isModalOpen.value = true;
}

function openEditModal(role: Role) {
  isEditing.value = true;
  editingRoleId.value = role.id;
  formState.name = role.name;
  formState.selectedPermissions = role.permissions.map((p) => p.name);
  permissionSearch.value = "";
  isModalOpen.value = true;
}

function toggleAllPermissions() {
  const available = filteredPermissions.value.map((p) => p.name);
  const allSelected = available.every((name) =>
    formState.selectedPermissions.includes(name)
  );

  if (allSelected) {
    formState.selectedPermissions = formState.selectedPermissions.filter(
      (name) => !available.includes(name)
    );
  } else {
    const combined = new Set([...formState.selectedPermissions, ...available]);
    formState.selectedPermissions = Array.from(combined);
  }
}

async function handleSaveRole() {
  if (!formState.name.trim()) {
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Role name is required.",
      color: "error",
    });
    return;
  }

  submitting.value = true;
  try {
    const url = isEditing.value
      ? `admin/roles/${editingRoleId.value}`
      : "admin/roles";
    const method = isEditing.value ? "PUT" : "POST";

    const res = await $http<{ ok: boolean; message: string }>(url, {
      method,
      body: {
        name: formState.name.trim(),
        permissions: formState.selectedPermissions,
      },
    });

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Role saved successfully.",
        color: "success",
      });
      isModalOpen.value = false;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Failed to save role. Please check input.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    submitting.value = false;
  }
}

function confirmDelete(role: Role) {
  roleToDelete.value = role;
  isDeleteModalOpen.value = true;
}

async function handleDeleteRole() {
  if (!roleToDelete.value) return;

  deleting.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/roles/${roleToDelete.value.id}`,
      {
        method: "DELETE",
      }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: res.message || "Role deleted successfully.",
        color: "success",
      });
      isDeleteModalOpen.value = false;
      roleToDelete.value = null;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Failed to delete role.";
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
    header: "Role Name",
  },
  {
    accessorKey: "permissions",
    header: "Permissions",
  },
  {
    accessorKey: "users_count",
    header: "Users",
    class: "w-28 text-center",
  },
  {
    id: "actions",
    header: "Actions",
    class: "w-36 text-right",
  },
];
</script>

<template>
  <div class="space-y-4">
    <!-- Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          Roles List
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Manage application roles and assigned system privileges.
        </p>
      </div>

      <UButton
        label="Create Role"
        icon="i-heroicons-plus"
        color="primary"
        @click="openCreateModal"
      />
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="p-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 flex items-center justify-between"
    >
      <div class="flex items-center gap-2 text-sm">
        <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5 flex-shrink-0" />
        <span>Failed to load roles list. Please ensure your backend is accessible.</span>
      </div>
      <UButton
        label="Retry"
        size="xs"
        color="error"
        variant="subtle"
        @click="refresh"
      />
    </div>

    <!-- Table Card -->
    <UCard
      :ui="{
        root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg overflow-hidden shadow-none',
        body: 'p-0 sm:p-0',
      }"
    >
      <UTable
        :data="data?.roles || []"
        :columns="columns"
        :loading="loading"
        loading-color="primary"
      >
        <!-- Role Name Column -->
        <template #name-cell="{ row }">
          <div class="flex items-center gap-2 font-medium">
            <span class="capitalize text-gray-900 dark:text-white font-semibold">
              {{ row.original.name }}
            </span>
            <UBadge
              v-if="row.original.name === 'admin'"
              label="System"
              color="primary"
              variant="subtle"
              size="xs"
            />
          </div>
        </template>

        <!-- Permissions Column -->
        <template #permissions-cell="{ row }">
          <div class="flex flex-wrap gap-1 max-w-lg py-1">
            <template v-if="row.original.permissions?.length > 0">
              <UBadge
                v-for="perm in row.original.permissions.slice(0, 4)"
                :key="perm.id"
                :label="perm.name"
                color="neutral"
                variant="subtle"
                size="xs"
                class="dark:bg-gray-800/80 dark:text-gray-300"
              />
              <UBadge
                v-if="row.original.permissions.length > 4"
                :label="`+${row.original.permissions.length - 4} more`"
                color="neutral"
                variant="outline"
                size="xs"
                class="dark:border-gray-700 dark:text-gray-400"
              />
            </template>
            <span v-else class="text-xs text-gray-400 italic">
              No permissions assigned
            </span>
          </div>
        </template>

        <!-- Users Count Column -->
        <template #users_count-cell="{ row }">
          <div class="text-center font-medium text-sm text-gray-600 dark:text-gray-300">
            {{ row.original.users_count ?? 0 }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #actions-cell="{ row }">
          <div class="flex items-center justify-end gap-1">
            <UButton
              icon="i-heroicons-pencil-square"
              size="xs"
              color="neutral"
              variant="ghost"
              aria-label="Edit role"
              class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
              @click="openEditModal(row.original)"
            />
            <UButton
              icon="i-heroicons-trash"
              size="xs"
              color="error"
              variant="ghost"
              :disabled="row.original.name === 'admin'"
              aria-label="Delete role"
              @click="confirmDelete(row.original)"
            />
          </div>
        </template>

        <!-- Empty State -->
        <template #empty>
          <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            <UIcon name="i-heroicons-shield-exclamation" class="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p class="font-medium">No roles found</p>
            <p class="text-xs mt-1">Get started by creating your first role.</p>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- Create / Edit Role Modal -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? `Edit Role: ${formState.name}` : 'Create New Role'"
      :description="isEditing ? 'Update role details and assign permissions.' : 'Enter role details and assign initial permissions.'"
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div class="space-y-4">
          <!-- Role Name Field -->
          <UFormField label="Role Name" required>
            <UInput
              v-model="formState.name"
              placeholder="e.g. editor, moderator, manager"
              class="w-full"
              :disabled="isEditing && formState.name === 'admin'"
            />
          </UFormField>

          <!-- Permissions Section -->
          <div class="space-y-2 pt-2 border-t border-gray-200/70 dark:border-white/[0.08]">
            <div class="flex items-center justify-between">
              <label class="text-sm font-medium text-gray-900 dark:text-white">
                Assigned Permissions ({{ formState.selectedPermissions.length }} selected)
              </label>
              <UButton
                label="Toggle All"
                size="xs"
                color="neutral"
                variant="ghost"
                class="dark:text-gray-300 dark:hover:text-white"
                @click="toggleAllPermissions"
              />
            </div>

            <UInput
              v-model="permissionSearch"
              icon="i-heroicons-magnifying-glass"
              placeholder="Filter permissions..."
              size="sm"
              class="w-full mb-2"
            />

            <!-- Permission Checklist Matrix -->
            <div
              class="max-h-60 overflow-y-auto border border-gray-200/70 dark:border-white/[0.08] rounded-md p-3 space-y-1 bg-gray-50/50 dark:bg-[#070b14]/50"
            >
              <div
                v-if="filteredPermissions.length === 0"
                class="text-xs text-gray-500 dark:text-gray-400 text-center py-4"
              >
                No permissions match your filter.
              </div>

              <div
                v-for="perm in filteredPermissions"
                :key="perm.id"
                class="flex items-center gap-2 hover:bg-gray-100/70 dark:hover:bg-gray-800/60 p-2 rounded-md transition-colors"
              >
                <UCheckbox
                  v-model="formState.selectedPermissions"
                  :value="perm.name"
                  :label="perm.name"
                  class="text-gray-800 dark:text-gray-200 text-xs"
                />
              </div>
            </div>
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
            :label="isEditing ? 'Save Changes' : 'Create Role'"
            color="primary"
            :loading="submitting"
            @click="handleSaveRole"
          />
        </div>
      </template>
    </UModal>

    <!-- Delete Confirmation Modal -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Delete Role"
      description="Are you sure you want to delete this role? This action cannot be undone."
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          You are about to delete role <strong class="text-gray-900 dark:text-white font-semibold">{{ roleToDelete?.name }}</strong>.
          <span v-if="(roleToDelete?.users_count ?? 0) > 0" class="block text-red-600 dark:text-red-400 font-medium mt-2">
            Warning: This role currently has {{ roleToDelete?.users_count }} assigned users. You must reassign those users before deleting.
          </span>
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
            label="Delete Role"
            color="error"
            :loading="deleting"
            @click="handleDeleteRole"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

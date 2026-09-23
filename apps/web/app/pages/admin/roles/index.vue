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

interface PermissionGroup {
  key: string;
  label: string;
  icon: string;
  permissions: Permission[];
}

const toast = useToast();
const { data, status, refresh, error } = useHttp<RolesResponse>("admin/roles");
const loading = computed(() => status.value === "pending");
const { can } = usePermission();

definePageMeta({
  middleware: ["auth", "permission"],
  permission: "roles.view",
});

const canCreate = computed(() => can('roles.create') || can('roles.manage'));
const canEdit = computed(() => can('roles.edit') || can('roles.manage'));
const canDelete = computed(() => can('roles.delete') || can('roles.manage'));
const hasAnyAction = computed(() => canEdit.value || canDelete.value);

useSeoMeta({
  title: "Akses Grup",
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

// Module group metadata
const groupMetaMap: Record<string, { label: string; icon: string }> = {
  users: { label: "Manajemen Pengguna", icon: "i-heroicons-users" },
  roles: { label: "Akses Grup (Roles)", icon: "i-heroicons-shield-check" },
  permissions: { label: "Hak Akses (Permissions)", icon: "i-heroicons-key" },
  settings: { label: "Pengaturan Sistem", icon: "i-heroicons-cog-6-tooth" },
  "batas-kecamatan": { label: "Batas Wilayah Kecamatan", icon: "i-heroicons-map" },
  "batas-desa": { label: "Batas Wilayah Desa", icon: "i-heroicons-map" },
  "jalan-poros-desa": { label: "Jalan Poros Desa", icon: "i-heroicons-arrows-pointing-out" },
  dataset: { label: "Dataset Spasial", icon: "i-heroicons-circle-stack" },
};

function getPermissionGroupKey(name: string): string {
  if (name.includes(".")) {
    return name.split(".")[0];
  }
  if (name.includes("-")) {
    const lastDash = name.lastIndexOf("-");
    return name.slice(0, lastDash);
  }
  return "general";
}

function getPermissionAction(name: string): string {
  if (name.includes(".")) {
    return name.split(".").slice(1).join(".");
  }
  if (name.includes("-")) {
    const parts = name.split("-");
    return parts[parts.length - 1];
  }
  return name;
}

function getActionBadgeColor(action: string): "primary" | "secondary" | "success" | "info" | "warning" | "error" | "neutral" {
  const act = action.toLowerCase();
  if (act.includes("create") || act.includes("tambah")) return "success";
  if (act.includes("edit") || act.includes("ubah") || act.includes("manage")) return "warning";
  if (act.includes("split") || act.includes("potong") || act.includes("pecah")) return "secondary";
  if (act.includes("delete") || act.includes("hapus")) return "error";
  if (act.includes("view") || act.includes("access") || act.includes("lihat")) return "info";
  return "neutral";
}

// Grouped and filtered permissions
const groupedPermissions = computed<PermissionGroup[]>(() => {
  const query = permissionSearch.value.trim().toLowerCase();
  const all = data.value?.permissions ?? [];

  const groupsMap = new Map<string, Permission[]>();

  for (const perm of all) {
    if (query && !perm.name.toLowerCase().includes(query)) {
      continue;
    }
    const key = getPermissionGroupKey(perm.name);
    if (!groupsMap.has(key)) {
      groupsMap.set(key, []);
    }
    groupsMap.get(key)!.push(perm);
  }

  const result: PermissionGroup[] = [];
  groupsMap.forEach((perms, key) => {
    const meta = groupMetaMap[key] || {
      label: key.charAt(0).toUpperCase() + key.slice(1).replace(/-/g, " "),
      icon: "i-heroicons-folder",
    };
    result.push({
      key,
      label: meta.label,
      icon: meta.icon,
      permissions: perms,
    });
  });

  return result;
});

// All permissions matching current search
const allFilteredPermissionNames = computed<string[]>(() => {
  return groupedPermissions.value.flatMap((g) => g.permissions.map((p) => p.name));
});

// Permission selection helpers
function isPermissionSelected(name: string): boolean {
  return formState.selectedPermissions.includes(name);
}

function togglePermission(name: string) {
  const idx = formState.selectedPermissions.indexOf(name);
  if (idx > -1) {
    formState.selectedPermissions.splice(idx, 1);
  } else {
    formState.selectedPermissions.push(name);
  }
}

function isGroupAllSelected(group: PermissionGroup): boolean {
  if (group.permissions.length === 0) return false;
  return group.permissions.every((p) => formState.selectedPermissions.includes(p.name));
}

function isGroupSomeSelected(group: PermissionGroup): boolean {
  const hasSome = group.permissions.some((p) => formState.selectedPermissions.includes(p.name));
  return hasSome && !isGroupAllSelected(group);
}

function selectedInGroupCount(group: PermissionGroup): number {
  return group.permissions.filter((p) => formState.selectedPermissions.includes(p.name)).length;
}

function toggleGroup(group: PermissionGroup) {
  const groupNames = group.permissions.map((p) => p.name);
  const allSelected = isGroupAllSelected(group);

  if (allSelected) {
    formState.selectedPermissions = formState.selectedPermissions.filter(
      (name) => !groupNames.includes(name)
    );
  } else {
    const set = new Set([...formState.selectedPermissions, ...groupNames]);
    formState.selectedPermissions = Array.from(set);
  }
}

const isAllFilteredSelected = computed<boolean>(() => {
  const names = allFilteredPermissionNames.value;
  if (names.length === 0) return false;
  return names.every((n) => formState.selectedPermissions.includes(n));
});

function toggleAllFiltered() {
  const names = allFilteredPermissionNames.value;
  if (isAllFilteredSelected.value) {
    formState.selectedPermissions = formState.selectedPermissions.filter(
      (n) => !names.includes(n)
    );
  } else {
    const set = new Set([...formState.selectedPermissions, ...names]);
    formState.selectedPermissions = Array.from(set);
  }
}

function clearAllPermissions() {
  formState.selectedPermissions = [];
}

// Modal open handlers
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
  formState.selectedPermissions = (role.permissions || []).map((p) => p.name);
  permissionSearch.value = "";
  isModalOpen.value = true;
}

async function handleSaveRole() {
  if (!formState.name.trim()) {
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Nama akses grup wajib diisi.",
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
        title: res.message || "Akses grup berhasil disimpan.",
        color: "success",
      });
      isModalOpen.value = false;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Gagal menyimpan akses grup. Silakan periksa kembali.";
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
        title: res.message || "Akses grup berhasil dihapus.",
        color: "success",
      });
      isDeleteModalOpen.value = false;
      roleToDelete.value = null;
      await refresh();
    }
  } catch (err: any) {
    const msg =
      err?.response?._data?.message || "Gagal menghapus akses grup.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: msg,
      color: "error",
    });
  } finally {
    deleting.value = false;
  }
}

const columns = computed(() => [
  {
    accessorKey: "name",
    header: "Nama Akses Grup",
  },
  {
    accessorKey: "permissions",
    header: "Hak Akses Terdaftar",
  },
  {
    accessorKey: "users_count",
    header: "Pengguna",
    class: "w-28 text-center",
  },
  ...(hasAnyAction.value ? [{
    id: "actions",
    header: "Aksi",
    class: "w-40 text-right",
  }] : []),
]);
</script>

<template>
  <div class="space-y-4">
    <!-- Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          Akses Grup (Roles)
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Kelola grup peran pengguna dan konfigurasi pendaftaran hak akses (permissions).
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          v-if="canCreate"
          label="Tambah Akses Grup"
          icon="i-heroicons-plus"
          color="primary"
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
        <span>Gagal memuat daftar akses grup. Pastikan server backend sedang aktif.</span>
      </div>
      <UButton
        label="Coba Lagi"
        size="xs"
        color="error"
        variant="subtle"
        @click="() => refresh()"
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
              label="Sistem"
              color="primary"
              variant="subtle"
              size="xs"
            />
          </div>
        </template>

        <!-- Permissions Column -->
        <template #permissions-cell="{ row }">
          <div class="flex flex-wrap items-center gap-1.5 max-w-xl py-1">
            <template v-if="row.original.permissions?.length > 0">
              <UBadge
                v-for="perm in row.original.permissions.slice(0, 4)"
                :key="perm.id"
                :label="perm.name"
                color="neutral"
                variant="subtle"
                size="xs"
                class="font-mono text-[11px] dark:bg-gray-800/80 dark:text-gray-300"
              />
              <UButton
                v-if="row.original.permissions.length > 4"
                :label="`+${row.original.permissions.length - 4} lainnya`"
                size="xs"
                color="neutral"
                variant="outline"
                class="text-[11px] h-5 px-1.5 dark:border-gray-700 dark:text-gray-400"
                @click="openEditModal(row.original)"
              />
            </template>
            <span v-else class="text-xs text-gray-400 italic">
              Belum ada hak akses terdaftar
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
            <!-- Manage Permissions Direct Action -->
            <UButton
              v-if="canEdit"
              icon="i-heroicons-key"
              size="xs"
              color="primary"
              variant="ghost"
              title="Atur Hak Akses"
              aria-label="Atur Hak Akses"
              class="hover:bg-primary-50 dark:hover:bg-primary-950/40"
              @click="openEditModal(row.original)"
            />
            <!-- Edit Role Name Action -->
            <UButton
              v-if="canEdit"
              icon="i-heroicons-pencil-square"
              size="xs"
              color="neutral"
              variant="ghost"
              title="Ubah Akses Grup"
              aria-label="Ubah Akses Grup"
              class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
              @click="openEditModal(row.original)"
            />
            <!-- Delete Role Action -->
            <UButton
              v-if="canDelete"
              icon="i-heroicons-trash"
              size="xs"
              color="error"
              variant="ghost"
              :disabled="row.original.name === 'admin'"
              title="Hapus Akses Grup"
              aria-label="Hapus Akses Grup"
              @click="confirmDelete(row.original)"
            />
          </div>
        </template>

        <!-- Empty State -->
        <template #empty>
          <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            <UIcon name="i-heroicons-shield-exclamation" class="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p class="font-medium">Tidak ada akses grup ditemukan</p>
            <p class="text-xs mt-1">Mulai dengan menambahkan akses grup pertama.</p>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- Create / Edit Role & Permissions Modal -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? `Kelola Akses Grup: ${formState.name}` : 'Tambah Akses Grup Baru'"
      :description="isEditing ? 'Perbarui nama grup dan konfigurasi pendaftaran hak akses yang diizinkan.' : 'Masukkan nama akses grup dan centang hak akses yang ingin diberikan.'"
      :ui="{
        content: 'sm:max-w-3xl dark:bg-[#0b0f19] dark:border-white/[0.08]',
      }"
    >
      <template #body>
        <div class="space-y-4">
          <!-- Role Name Field -->
          <UFormField label="Nama Akses Grup" required>
            <UInput
              v-model="formState.name"
              placeholder="Contoh: surveyor, verifikator, operator"
              class="w-full"
              :disabled="isEditing && formState.name === 'admin'"
            />
            <p
              v-if="isEditing && formState.name === 'admin'"
              class="text-[11px] text-amber-600 dark:text-amber-400 mt-1"
            >
              Catatan: Nama akses grup bawaan sistem (admin) tidak dapat diubah, namun hak akses dapat disesuaikan.
            </p>
          </UFormField>

          <!-- Permissions Section -->
          <div class="pt-3 border-t border-gray-200/70 dark:border-white/[0.08] space-y-3">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div>
                <label class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <span>Pendaftaran Hak Akses</span>
                  <UBadge
                    :label="`${formState.selectedPermissions.length} dari ${data?.permissions?.length || 0} dipilih`"
                    color="primary"
                    variant="subtle"
                    size="xs"
                  />
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Centang modul dan aksi yang boleh dilakukan oleh akses grup ini.
                </p>
              </div>

              <!-- Quick Bulk Actions -->
              <div class="flex items-center gap-1.5">
                <UButton
                  :label="isAllFilteredSelected ? 'Batal Semua' : 'Pilih Semua'"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  icon="i-heroicons-check-circle"
                  class="text-xs"
                  @click="toggleAllFiltered"
                />
                <UButton
                  v-if="formState.selectedPermissions.length > 0"
                  label="Kosongkan"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  icon="i-heroicons-x-mark"
                  class="text-xs text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                  @click="clearAllPermissions"
                />
              </div>
            </div>

            <!-- Search Filter Bar -->
            <div class="relative">
              <UInput
                v-model="permissionSearch"
                icon="i-heroicons-magnifying-glass"
                placeholder="Cari hak akses berdasarkan nama atau modul..."
                size="sm"
                class="w-full"
              />
              <button
                v-if="permissionSearch"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                @click="permissionSearch = ''"
              >
                <UIcon name="i-heroicons-x-mark" class="w-4 h-4" />
              </button>
            </div>

            <!-- Permissions Group Matrix -->
            <div
              class="max-h-80 overflow-y-auto border border-gray-200/70 dark:border-white/[0.08] rounded-lg p-3 space-y-3 bg-gray-50/50 dark:bg-[#070b14]/50"
            >
              <div
                v-if="groupedPermissions.length === 0"
                class="text-xs text-gray-500 dark:text-gray-400 text-center py-6"
              >
                Tidak ada hak akses yang cocok dengan pencarian "<strong>{{ permissionSearch }}</strong>".
              </div>

              <!-- Loop Groups -->
              <div
                v-for="group in groupedPermissions"
                :key="group.key"
                class="border border-gray-200/60 dark:border-white/[0.06] rounded-md bg-white dark:bg-[#0b0f19] p-2.5 transition-colors"
              >
                <!-- Group Header -->
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-100 dark:border-white/[0.04]">
                  <div class="flex items-center gap-2">
                    <UIcon :name="group.icon" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-xs font-semibold text-gray-900 dark:text-white">
                      {{ group.label }}
                    </span>
                    <UBadge
                      :label="`${selectedInGroupCount(group)}/${group.permissions.length}`"
                      color="neutral"
                      variant="subtle"
                      size="xs"
                      class="text-[10px]"
                    />
                  </div>

                  <!-- Group Select Toggle -->
                  <UButton
                    :label="isGroupAllSelected(group) ? 'Batal Grup' : 'Pilih Semua Grup'"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    class="text-[11px] h-6 px-2 dark:text-gray-300 dark:hover:text-white"
                    @click="toggleGroup(group)"
                  />
                </div>

                <!-- Group Permissions Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                  <div
                    v-for="perm in group.permissions"
                    :key="perm.id"
                    class="flex items-center justify-between p-2 rounded-md transition-colors cursor-pointer border select-none"
                    :class="[
                      isPermissionSelected(perm.name)
                        ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-300/60 dark:border-emerald-800/40 text-gray-900 dark:text-white'
                        : 'hover:bg-gray-100/70 dark:hover:bg-gray-800/50 border-transparent text-gray-700 dark:text-gray-300'
                    ]"
                    @click="togglePermission(perm.name)"
                  >
                    <div class="flex items-center gap-2 min-w-0 pr-2">
                      <UCheckbox
                        :model-value="isPermissionSelected(perm.name)"
                        @update:model-value="() => togglePermission(perm.name)"
                        @click.stop
                      />
                      <div class="min-w-0">
                        <div class="text-xs font-medium truncate">
                          {{ perm.name }}
                        </div>
                      </div>
                    </div>

                    <UBadge
                      :label="getPermissionAction(perm.name)"
                      :color="getActionBadgeColor(getPermissionAction(perm.name))"
                      variant="subtle"
                      size="xs"
                      class="text-[10px] uppercase tracking-wider flex-shrink-0"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <div class="text-xs text-gray-500 dark:text-gray-400">
            <span>{{ formState.selectedPermissions.length }} hak akses akan disimpan</span>
          </div>

          <div class="flex items-center gap-2">
            <UButton
              label="Batal"
              color="neutral"
              variant="ghost"
              @click="isModalOpen = false"
            />
            <UButton
              :label="isEditing ? 'Simpan Perubahan' : 'Buat Akses Grup'"
              color="primary"
              :loading="submitting"
              @click="handleSaveRole"
            />
          </div>
        </div>
      </template>
    </UModal>

    <!-- Delete Confirmation Modal -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Hapus Akses Grup"
      description="Apakah Anda yakin ingin menghapus akses grup ini? Tindakan ini tidak dapat dibatalkan."
    >
      <template #body>
        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
          <p>
            Anda akan menghapus akses grup:
            <strong class="text-gray-900 dark:text-white font-semibold">{{ roleToDelete?.name }}</strong>.
          </p>
          <div
            v-if="(roleToDelete?.users_count ?? 0) > 0"
            class="p-2.5 rounded-md bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 text-xs font-medium"
          >
            Peringatan: Akses grup ini sedang digunakan oleh {{ roleToDelete?.users_count }} pengguna. Anda harus memindahkan pengguna tersebut ke akses grup lain sebelum dapat menghapusnya.
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isDeleteModalOpen = false"
          />
          <UButton
            label="Hapus Akses Grup"
            color="error"
            :loading="deleting"
            @click="handleDeleteRole"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

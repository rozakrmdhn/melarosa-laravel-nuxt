<script lang="ts" setup>
definePageMeta({
  middleware: ["auth", "permission"],
  permission: "users-view",
});

interface Role {
  id: number;
  name: string;
}

interface UserItem {
  id: number | string;
  uuid?: string;
  name: string;
  email: string;
  avatar: string | null;
  email_verified_at: string | null;
  created_at: string;
  roles: Role[];
}

interface UsersResponse {
  ok: boolean;
  users: {
    data: UserItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  roles: Role[];
}

const auth = useAuthStore();
const toast = useToast();
const dayjs = useDayjs();
const { can } = usePermission();

const canCreate = computed(() => can('users-create'));
const canUpdate = computed(() => can('users-update'));
const canDelete = computed(() => can('users-delete'));
const canManageRoles = computed(() => can('users-update'));
const hasAnyAction = computed(() => canUpdate.value || canDelete.value || canManageRoles.value);

useSeoMeta({
  title: "Pengguna | User Management",
});

// ─── Filter & Query State ───────────────────────────────────────────────────
const search = ref("");
const roleFilter = ref<string>("ALL");
const page = ref(1);

const queryParams = computed(() => ({
  page: page.value,
  search: search.value.trim() || undefined,
  role: roleFilter.value !== "ALL" ? roleFilter.value : undefined,
}));

const { data, status, refresh, error } = useHttp<UsersResponse>("admin/users", {
  query: queryParams,
  watch: [queryParams],
});

const loading = computed(() => status.value === "pending");

const roleFilterItems = computed(() => {
  const allRoles = (data.value?.roles || []).map((r) => ({
    label: `Role: ${r.name}`,
    value: r.name,
  }));
  return [{ label: "Semua Role", value: "ALL" }, ...allRoles];
});

// Reset page on search or filter change
watch([search, roleFilter], () => {
  page.value = 1;
});

// ─── Create User State ──────────────────────────────────────────────────────
const isCreateOpen = ref(false);
const creating = ref(false);
const showCreatePassword = ref(false);

const createForm = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  roles: [] as string[],
});

const createErrors = reactive<Record<string, string>>({});

function openCreateModal() {
  if (!canCreate.value) return;
  createForm.name = "";
  createForm.email = "";
  createForm.password = "";
  createForm.password_confirmation = "";
  createForm.roles = data.value?.roles?.length ? [data.value.roles[0].name] : ["user"];
  showCreatePassword.value = false;
  Object.keys(createErrors).forEach((k) => delete createErrors[k]);
  isCreateOpen.value = true;
}

async function handleCreateUser() {
  if (!canCreate.value) return;
  Object.keys(createErrors).forEach((k) => delete createErrors[k]);

  if (!createForm.name.trim()) {
    createErrors.name = "Nama lengkap wajib diisi.";
  }
  if (!createForm.email.trim()) {
    createErrors.email = "Email wajib diisi.";
  }
  if (!createForm.password) {
    createErrors.password = "Password wajib diisi.";
  } else if (createForm.password.length < 8) {
    createErrors.password = "Password minimal 8 karakter.";
  }
  if (createForm.password !== createForm.password_confirmation) {
    createErrors.password_confirmation = "Konfirmasi password tidak cocok.";
  }
  if (createForm.roles.length === 0) {
    createErrors.roles = "Pilih setidaknya satu role.";
  }

  if (Object.keys(createErrors).length > 0) return;

  creating.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>("admin/users", {
      method: "POST",
      body: {
        name: createForm.name.trim(),
        email: createForm.email.trim(),
        password: createForm.password,
        password_confirmation: createForm.password_confirmation,
        roles: createForm.roles,
      },
    });

    if (res?.ok) {
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Pengguna baru berhasil ditambahkan.",
        color: "success",
      });
      isCreateOpen.value = false;
      await refresh();
    }
  } catch (err: any) {
    const apiErrors = err?.data?.errors || err?.response?._data?.errors;
    if (apiErrors) {
      Object.entries(apiErrors).forEach(([field, msgs]) => {
        createErrors[field] = Array.isArray(msgs) ? msgs[0] : String(msgs);
      });
    }
    const msg = err?.data?.message || err?.response?._data?.message || "Gagal menambahkan pengguna.";
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Gagal Menambahkan Pengguna",
      description: msg,
      color: "error",
    });
  } finally {
    creating.value = false;
  }
}

// ─── Edit User State ────────────────────────────────────────────────────────
const isEditOpen = ref(false);
const updating = ref(false);
const showEditPassword = ref(false);

const editForm = reactive({
  id: "" as number | string,
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  roles: [] as string[],
});

const editErrors = reactive<Record<string, string>>({});

function openEditUser(user: UserItem) {
  if (!canUpdate.value) return;
  editForm.id = user.id;
  editForm.name = user.name;
  editForm.email = user.email;
  editForm.password = "";
  editForm.password_confirmation = "";
  editForm.roles = user.roles.map((r) => r.name);
  showEditPassword.value = false;
  Object.keys(editErrors).forEach((k) => delete editErrors[k]);
  isEditOpen.value = true;
}

async function handleUpdateUser() {
  if (!canUpdate.value) return;
  Object.keys(editErrors).forEach((k) => delete editErrors[k]);

  if (!editForm.name.trim()) {
    editErrors.name = "Nama lengkap wajib diisi.";
  }
  if (!editForm.email.trim()) {
    editErrors.email = "Email wajib diisi.";
  }
  if (editForm.password) {
    if (editForm.password.length < 8) {
      editErrors.password = "Password minimal 8 karakter.";
    }
    if (editForm.password !== editForm.password_confirmation) {
      editErrors.password_confirmation = "Konfirmasi password tidak cocok.";
    }
  }
  if (editForm.roles.length === 0) {
    editErrors.roles = "Pilih setidaknya satu role.";
  }

  if (Object.keys(editErrors).length > 0) return;

  updating.value = true;
  try {
    const payload: Record<string, any> = {
      name: editForm.name.trim(),
      email: editForm.email.trim(),
      roles: editForm.roles,
    };

    if (editForm.password) {
      payload.password = editForm.password;
      payload.password_confirmation = editForm.password_confirmation;
    }

    const res = await $http<{ ok: boolean; message: string }>(`admin/users/${editForm.id}`, {
      method: "PUT",
      body: payload,
    });

    if (res?.ok) {
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Data pengguna berhasil diperbarui.",
        color: "success",
      });
      isEditOpen.value = false;
      await refresh();

      if (editForm.email === auth.user?.email) {
        await auth.fetchUser();
      }
    }
  } catch (err: any) {
    const apiErrors = err?.data?.errors || err?.response?._data?.errors;
    if (apiErrors) {
      Object.entries(apiErrors).forEach(([field, msgs]) => {
        editErrors[field] = Array.isArray(msgs) ? msgs[0] : String(msgs);
      });
    }
    const msg = err?.data?.message || err?.response?._data?.message || "Gagal memperbarui data pengguna.";
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Gagal Update",
      description: msg,
      color: "error",
    });
  } finally {
    updating.value = false;
  }
}

// ─── Quick Role Assignment Modal State ──────────────────────────────────────
const isRoleModalOpen = ref(false);
const selectedUserForRole = ref<UserItem | null>(null);
const selectedRolesQuick = ref<string[]>([]);
const updatingRoles = ref(false);

function openEditUserRoles(user: UserItem) {
  if (!canManageRoles.value) return;
  selectedUserForRole.value = user;
  selectedRolesQuick.value = user.roles.map((r) => r.name);
  isRoleModalOpen.value = true;
}

function toggleCreateRole(name: string) {
  const idx = createForm.roles.indexOf(name);
  if (idx > -1) createForm.roles.splice(idx, 1);
  else createForm.roles.push(name);
}

function toggleEditRole(name: string) {
  const idx = editForm.roles.indexOf(name);
  if (idx > -1) editForm.roles.splice(idx, 1);
  else editForm.roles.push(name);
}

function toggleQuickRole(name: string) {
  const idx = selectedRolesQuick.value.indexOf(name);
  if (idx > -1) selectedRolesQuick.value.splice(idx, 1);
  else selectedRolesQuick.value.push(name);
}

async function handleUpdateRolesQuick() {
  if (!canManageRoles.value || !selectedUserForRole.value) return;

  if (selectedRolesQuick.value.length === 0) {
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Peringatan",
      description: "Pilih setidaknya satu role.",
      color: "error",
    });
    return;
  }

  updatingRoles.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/users/${selectedUserForRole.value.id}/roles`,
      {
        method: "PUT",
        body: {
          roles: selectedRolesQuick.value,
        },
      }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Role pengguna berhasil diperbarui.",
        color: "success",
      });
      isRoleModalOpen.value = false;
      await refresh();

      if (selectedUserForRole.value.email === auth.user?.email) {
        await auth.fetchUser();
      }
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.response?._data?.message || "Gagal memperbarui role.";
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Gagal",
      description: msg,
      color: "error",
    });
  } finally {
    updatingRoles.value = false;
  }
}

// ─── Delete User State ──────────────────────────────────────────────────────
const isDeleteOpen = ref(false);
const userToDelete = ref<UserItem | null>(null);
const deleting = ref(false);

function openDeleteConfirm(user: UserItem) {
  if (!canDelete.value || isCurrentUser(user)) return;
  userToDelete.value = user;
  isDeleteOpen.value = true;
}

async function handleDeleteUser() {
  if (!canDelete.value || !userToDelete.value) return;

  deleting.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(`admin/users/${userToDelete.value.id}`, {
      method: "DELETE",
    });

    if (res?.ok) {
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Pengguna berhasil dihapus.",
        color: "success",
      });
      isDeleteOpen.value = false;
      userToDelete.value = null;
      await refresh();
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.response?._data?.message || "Gagal menghapus pengguna.";
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Gagal Menghapus",
      description: msg,
      color: "error",
    });
  } finally {
    deleting.value = false;
  }
}

function isCurrentUser(user: UserItem | null): boolean {
  if (!user || !auth.user) return false;
  return user.email === auth.user.email || (auth.user.uuid && user.uuid === auth.user.uuid);
}

// ─── Table Columns ──────────────────────────────────────────────────────────
const columns = computed(() => [
  {
    accessorKey: "name",
    header: "Pengguna",
  },
  {
    accessorKey: "roles",
    header: "Hak Akses Grup",
  },
  {
    accessorKey: "status",
    header: "Status Akun",
    class: "w-32 text-center",
  },
  {
    accessorKey: "created_at",
    header: "Terdaftar",
    class: "w-36 text-center",
  },
  ...(hasAnyAction.value ? [{
    id: "actions",
    header: "Aksi",
    class: "w-44 text-right",
  }] : []),
]);
</script>

<template>
  <div class="space-y-4">
    <!-- Header Page & Actions Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <UIcon name="i-lucide-users" class="size-5 text-emerald-600 dark:text-emerald-400" />
          <span>Manajemen Pengguna</span>
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Kelola data akun pengguna sistem, pengaturan peran grup, dan hak akses aplikasi.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <!-- Search Bar -->
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Cari nama atau email..."
          size="sm"
          class="w-56"
        />

        <!-- Role Filter Dropdown -->
        <USelectMenu
          v-model="roleFilter"
          :items="roleFilterItems"
          value-key="value"
          label-key="label"
          size="sm"
          class="w-40"
        />

        <!-- Button Tambah Pengguna -->
        <UButton
          v-if="canCreate"
          label="Tambah Pengguna"
          icon="i-lucide-user-plus"
          size="sm"
          color="primary"
          variant="solid"
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
        <UIcon name="i-lucide-alert-triangle" class="size-5 shrink-0" />
        <span>Gagal memuat data pengguna. Silakan coba kembali.</span>
      </div>
      <UButton
        label="Coba Lagi"
        size="xs"
        color="error"
        variant="subtle"
        @click="() => refresh()"
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
          <div class="flex items-center gap-3 py-1.5">
            <div
              class="size-8 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-xs shrink-0"
            >
              {{ row.original.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <div class="min-w-0">
              <div class="font-medium text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                <span>{{ row.original.name }}</span>
                <UBadge
                  v-if="isCurrentUser(row.original)"
                  label="Anda"
                  color="primary"
                  variant="subtle"
                  size="xs"
                  class="text-[9px] px-1 py-0 h-4 leading-none"
                />
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
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
              class="capitalize text-[11px]"
            />
            <span
              v-if="!row.original.roles?.length"
              class="text-xs text-gray-400 italic"
            >
              Tanpa role
            </span>
          </div>
        </template>

        <!-- Status Column -->
        <template #status-cell="{ row }">
          <div class="flex justify-center">
            <UBadge
              v-if="row.original.email_verified_at"
              label="Terverifikasi"
              color="primary"
              variant="soft"
              size="xs"
              class="text-[10px]"
            />
            <UBadge
              v-else
              label="Belum Verifikasi"
              color="neutral"
              variant="outline"
              size="xs"
              class="text-[10px] text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-800"
            />
          </div>
        </template>

        <!-- Joined Column -->
        <template #created_at-cell="{ row }">
          <div class="text-center text-xs text-gray-500 dark:text-gray-400 font-mono">
            {{ dayjs(row.original.created_at).format("D MMM YYYY") }}
          </div>
        </template>

        <!-- Action Column -->
        <template #actions-cell="{ row }">
          <div class="flex items-center justify-end gap-1">
            <!-- Tombol Edit Data Lengkap -->
            <UTooltip v-if="canUpdate" text="Edit Pengguna">
              <UButton
                icon="i-lucide-pencil"
                size="xs"
                color="neutral"
                variant="ghost"
                class="hover:text-emerald-600 dark:hover:text-emerald-400 cursor-pointer"
                @click="openEditUser(row.original)"
              />
            </UTooltip>

            <!-- Tombol Cepat Atur Role -->
            <UTooltip v-if="canManageRoles" text="Atur Role">
              <UButton
                icon="i-lucide-shield-check"
                size="xs"
                color="neutral"
                variant="ghost"
                class="hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
                @click="openEditUserRoles(row.original)"
              />
            </UTooltip>

            <!-- Tombol Hapus -->
            <UTooltip v-if="canDelete" :text="isCurrentUser(row.original) ? 'Tidak dapat menghapus akun sendiri' : 'Hapus Pengguna'">
              <UButton
                icon="i-lucide-trash-2"
                size="xs"
                color="neutral"
                variant="ghost"
                :disabled="isCurrentUser(row.original)"
                class="hover:text-red-600 dark:hover:text-red-400 disabled:opacity-30 cursor-pointer"
                @click="openDeleteConfirm(row.original)"
              />
            </UTooltip>
          </div>
        </template>

        <!-- Empty State -->
        <template #empty>
          <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            <UIcon name="i-lucide-users" class="size-8 mx-auto mb-2 opacity-40" />
            <p class="font-medium text-gray-700 dark:text-gray-300">Tidak ada pengguna ditemukan</p>
            <p class="text-xs mt-1 text-gray-400">Coba ubah kata kunci pencarian atau filter role.</p>
          </div>
        </template>
      </UTable>

      <!-- Pagination Footer -->
      <template v-if="(data?.users?.last_page ?? 1) > 1" #footer>
        <div class="flex flex-col sm:flex-row items-center justify-between px-4 py-3 gap-2">
          <span class="text-xs text-gray-500 dark:text-gray-400">
            Menampilkan halaman {{ page }} dari {{ data?.users?.last_page }} (Total {{ data?.users?.total }} pengguna)
          </span>
          <UPagination
            v-model:page="page"
            :total="data?.users?.total || 0"
            :items-per-page="data?.users?.per_page || 15"
            size="xs"
          />
        </div>
      </template>
    </UCard>

    <!-- ═══ MODAL: TAMBAH PENGGUNA ═════════════════════════════════════════════ -->
    <UModal
      v-model:open="isCreateOpen"
      title="Tambah Pengguna Baru"
      description="Buat akun pengguna baru dan tentukan hak akses peran sistem."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-lg' }"
    >
      <template #body>
        <form class="space-y-3.5" @submit.prevent="handleCreateUser">
          <UFormField label="Nama Lengkap" required :error="createErrors.name" size="sm">
            <UInput
              v-model="createForm.name"
              placeholder="Contoh: Budi Santoso"
              size="sm"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Alamat Email" required :error="createErrors.email" size="sm">
            <UInput
              v-model="createForm.email"
              type="email"
              placeholder="nama@domain.com"
              size="sm"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Password" required :error="createErrors.password" size="sm">
              <div class="relative w-full">
                <UInput
                  v-model="createForm.password"
                  :type="showCreatePassword ? 'text' : 'password'"
                  placeholder="Minimal 8 karakter"
                  size="sm"
                  class="w-full"
                />
                <button
                  type="button"
                  tabindex="-1"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  @click="showCreatePassword = !showCreatePassword"
                >
                  <UIcon :name="showCreatePassword ? 'i-lucide-eye-off' : 'i-lucide-eye'" class="size-3.5" />
                </button>
              </div>
            </UFormField>

            <UFormField label="Konfirmasi Password" required :error="createErrors.password_confirmation" size="sm">
              <UInput
                v-model="createForm.password_confirmation"
                :type="showCreatePassword ? 'text' : 'password'"
                placeholder="Ulangi password"
                size="sm"
                class="w-full"
              />
            </UFormField>
          </div>

          <!-- Pilihan Role -->
          <UFormField label="Hak Akses Grup (Role)" required :error="createErrors.roles" size="sm">
            <div class="border border-gray-200/70 dark:border-white/[0.08] rounded-md p-2.5 space-y-1.5 bg-gray-50/50 dark:bg-[#070b14]/50">
              <div
                v-for="role in data?.roles ?? []"
                :key="role.id"
                class="flex items-center gap-2 hover:bg-gray-100/60 dark:hover:bg-gray-800/50 p-1.5 rounded-md transition-colors cursor-pointer select-none"
                @click="toggleCreateRole(role.name)"
              >
                <UCheckbox
                  :model-value="createForm.roles.includes(role.name)"
                  @update:model-value="() => toggleCreateRole(role.name)"
                  :label="role.name"
                  class="text-gray-800 dark:text-gray-200 text-xs capitalize"
                  @click.stop
                />
              </div>
            </div>
          </UFormField>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isCreateOpen = false"
          />
          <UButton
            v-if="canCreate"
            label="Simpan Pengguna"
            color="primary"
            variant="solid"
            :loading="creating"
            @click="handleCreateUser"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: EDIT DATA PENGGUNA ══════════════════════════════════════════ -->
    <UModal
      v-model:open="isEditOpen"
      title="Edit Data Pengguna"
      description="Perbarui informasi profil, hak akses role, atau atur ulang password akun."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-lg' }"
    >
      <template #body>
        <form class="space-y-3.5" @submit.prevent="handleUpdateUser">
          <UFormField label="Nama Lengkap" required :error="editErrors.name" size="sm">
            <UInput
              v-model="editForm.name"
              placeholder="Nama lengkap"
              size="sm"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Alamat Email" required :error="editErrors.email" size="sm">
            <UInput
              v-model="editForm.email"
              type="email"
              placeholder="nama@domain.com"
              size="sm"
              class="w-full"
            />
          </UFormField>

          <div class="p-3 bg-gray-50/70 dark:bg-gray-900/40 rounded-md border border-gray-200/70 dark:border-white/[0.08] space-y-2.5">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Ganti Password (Opsional)</span>
              <span class="text-[11px] text-gray-400">Kosongkan jika tidak diubah</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <UFormField label="Password Baru" :error="editErrors.password" size="sm">
                <div class="relative w-full">
                  <UInput
                    v-model="editForm.password"
                    :type="showEditPassword ? 'text' : 'password'"
                    placeholder="Password baru..."
                    size="sm"
                    class="w-full"
                  />
                  <button
                    type="button"
                    tabindex="-1"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    @click="showEditPassword = !showEditPassword"
                  >
                    <UIcon :name="showEditPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'" class="size-3.5" />
                  </button>
                </div>
              </UFormField>

              <UFormField label="Ulangi Password" :error="editErrors.password_confirmation" size="sm">
                <UInput
                  v-model="editForm.password_confirmation"
                  :type="showEditPassword ? 'text' : 'password'"
                  placeholder="Konfirmasi password..."
                  size="sm"
                  class="w-full"
                />
              </UFormField>
            </div>
          </div>

          <!-- Pilihan Role -->
          <UFormField label="Hak Akses Grup (Role)" required :error="editErrors.roles" size="sm">
            <div class="border border-gray-200/70 dark:border-white/[0.08] rounded-md p-2.5 space-y-1.5 bg-gray-50/50 dark:bg-[#070b14]/50">
              <div
                v-for="role in data?.roles ?? []"
                :key="role.id"
                class="flex items-center gap-2 hover:bg-gray-100/60 dark:hover:bg-gray-800/50 p-1.5 rounded-md transition-colors cursor-pointer select-none"
                @click="!(editForm.email === auth.user?.email && role.name === 'admin') && toggleEditRole(role.name)"
              >
                <UCheckbox
                  :model-value="editForm.roles.includes(role.name)"
                  @update:model-value="() => toggleEditRole(role.name)"
                  :label="role.name"
                  :disabled="editForm.email === auth.user?.email && role.name === 'admin'"
                  class="text-gray-800 dark:text-gray-200 text-xs capitalize"
                  @click.stop
                />
              </div>
            </div>
            <p
              v-if="editForm.email === auth.user?.email"
              class="text-[11px] text-amber-600 dark:text-amber-400 mt-1"
            >
              Catatan: Anda tidak dapat mencabut role admin dari akun Anda sendiri yang sedang aktif.
            </p>
          </UFormField>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isEditOpen = false"
          />
          <UButton
            v-if="canUpdate"
            label="Simpan Perubahan"
            color="primary"
            variant="solid"
            :loading="updating"
            @click="handleUpdateUser"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: CEPAT ATUR ROLE ═════════════════════════════════════════════ -->
    <UModal
      v-model:open="isRoleModalOpen"
      :title="`Atur Role: ${selectedUserForRole?.name}`"
      description="Perbarui penugasan grup hak akses untuk pengguna ini."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <div class="space-y-3.5">
          <div class="p-3 bg-gray-50 dark:bg-[#070b14]/60 rounded-md border border-gray-200/70 dark:border-white/[0.08] flex items-center gap-3">
            <div class="size-8 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-xs shrink-0">
              {{ selectedUserForRole?.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                {{ selectedUserForRole?.name }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ selectedUserForRole?.email }}
              </p>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
              Daftar Role Tersedia
            </label>

            <div class="border border-gray-200/70 dark:border-white/[0.08] rounded-md p-2.5 space-y-1 bg-gray-50/50 dark:bg-[#070b14]/50">
              <div
                v-for="role in data?.roles ?? []"
                :key="role.id"
                class="flex items-center gap-2 hover:bg-gray-100/70 dark:hover:bg-gray-800/60 p-2 rounded-md transition-colors cursor-pointer select-none"
                @click="!(selectedUserForRole?.email === auth.user?.email && role.name === 'admin') && toggleQuickRole(role.name)"
              >
                <UCheckbox
                  :model-value="selectedRolesQuick.includes(role.name)"
                  @update:model-value="() => toggleQuickRole(role.name)"
                  :label="role.name"
                  :disabled="selectedUserForRole?.email === auth.user?.email && role.name === 'admin'"
                  class="text-gray-800 dark:text-gray-200 text-xs capitalize"
                  @click.stop
                />
              </div>
            </div>

            <p
              v-if="selectedUserForRole?.email === auth.user?.email"
              class="text-xs text-amber-600 dark:text-amber-400 mt-1"
            >
              Catatan: Anda tidak dapat mencabut role admin dari sesi akun yang sedang aktif.
            </p>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isRoleModalOpen = false"
          />
          <UButton
            v-if="canManageRoles"
            label="Perbarui Role"
            color="primary"
            variant="solid"
            :loading="updatingRoles"
            @click="handleUpdateRolesQuick"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: KONFIRMASI HAPUS PENGGUNA ════════════════════════════════════ -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Hapus Pengguna"
      description="Tindakan ini tidak dapat dibatalkan. Akun pengguna akan dihapus permanen dari sistem."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-md' }"
    >
      <template #body>
        <div class="space-y-3">
          <div class="p-3 bg-red-50 dark:bg-red-950/30 rounded-md border border-red-200 dark:border-red-900/50 flex items-start gap-2.5 text-xs text-red-700 dark:text-red-300">
            <UIcon name="i-lucide-alert-triangle" class="size-4 shrink-0 mt-0.5" />
            <p>
              Apakah Anda yakin ingin menghapus akun pengguna berikut?
            </p>
          </div>

          <div class="p-3 bg-gray-50 dark:bg-[#070b14]/60 rounded-md border border-gray-200/70 dark:border-white/[0.08] flex items-center gap-3">
            <div class="size-8 rounded-full bg-red-500/10 text-red-600 dark:text-red-400 flex items-center justify-center font-bold text-xs shrink-0">
              {{ userToDelete?.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                {{ userToDelete?.name }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ userToDelete?.email }}
              </p>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isDeleteOpen = false"
          />
          <UButton
            v-if="canDelete"
            label="Hapus Permanen"
            color="error"
            variant="solid"
            :loading="deleting"
            @click="handleDeleteUser"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

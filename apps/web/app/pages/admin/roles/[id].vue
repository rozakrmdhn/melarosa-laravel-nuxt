<script lang="ts" setup>
definePageMeta({
  middleware: ["auth", "permission"],
  permission: "roles.view",
});

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
  created_at?: string;
  updated_at?: string;
}

interface RoleDetailResponse {
  ok: boolean;
  role: Role;
  permissions: Permission[];
}

interface PermissionGroup {
  key: string;
  label: string;
  icon: string;
  permissions: Permission[];
}

const route = useRoute();
const toast = useToast();
const dayjs = useDayjs();
const { can } = usePermission();

const canEdit = computed(() => can("roles.edit") || can("roles.manage"));
const roleId = computed(() => (route.params.id as string) || (route.query.id as string) || "");

useSeoMeta({
  title: "Edit Akses Grup | Role Management",
});

const {
  data: roleData,
  status: roleStatus,
  refresh: refreshRole,
  error: roleError,
} = useHttp<RoleDetailResponse>(
  () => `admin/roles/${roleId.value || 0}`,
  {
    watch: [roleId],
  }
);

const loading = computed(() => roleStatus.value === "pending");
const role = computed<Role | null>(() => roleData.value?.role ?? null);
const allPermissions = computed<Permission[]>(() => roleData.value?.permissions ?? []);

const formState = reactive({
  name: "",
  selectedPermissions: [] as string[],
});

const errors = reactive<Record<string, string>>({});
const saving = ref(false);
const permissionSearch = ref("");

const isSystemRole = computed(() => role.value?.name === "admin");

watch(
  role,
  (val) => {
    if (val) {
      formState.name = val.name || "";
      formState.selectedPermissions = (val.permissions || []).map((p) => p.name);
    }
  },
  { immediate: true }
);

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

function getActionBadgeColor(
  action: string
): "primary" | "secondary" | "success" | "info" | "warning" | "error" | "neutral" {
  const act = action.toLowerCase();
  if (act.includes("create") || act.includes("tambah")) return "success";
  if (act.includes("edit") || act.includes("ubah") || act.includes("manage")) return "warning";
  if (act.includes("split") || act.includes("potong") || act.includes("pecah")) return "secondary";
  if (act.includes("delete") || act.includes("hapus")) return "error";
  if (act.includes("view") || act.includes("access") || act.includes("lihat")) return "info";
  return "neutral";
}

const groupedPermissions = computed<PermissionGroup[]>(() => {
  const query = permissionSearch.value.trim().toLowerCase();
  const all = allPermissions.value;

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

const allFilteredPermissionNames = computed<string[]>(() => {
  return groupedPermissions.value.flatMap((g) => g.permissions.map((p) => p.name));
});

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

async function handleSaveRole() {
  if (!canEdit.value) return;
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!formState.name.trim()) {
    errors.name = "Nama akses grup wajib diisi.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Validasi Gagal",
      description: "Nama akses grup wajib diisi.",
      color: "error",
    });
    return;
  }

  saving.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string; role?: Role }>(
      `admin/roles/${roleId.value}`,
      {
        method: "PUT",
        body: {
          name: formState.name.trim(),
          permissions: formState.selectedPermissions,
        },
      }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-heroicons-check-circle",
        title: "Berhasil",
        description: res.message || "Akses grup berhasil diperbarui.",
        color: "success",
      });
      await refreshRole();
    }
  } catch (err: any) {
    const apiErrors = err?.data?.errors || err?.response?._data?.errors;
    if (apiErrors) {
      Object.entries(apiErrors).forEach(([field, msgs]) => {
        errors[field] = Array.isArray(msgs) ? msgs[0] : String(msgs);
      });
    }
    const msg =
      err?.data?.message ||
      err?.response?._data?.message ||
      "Gagal memperbarui akses grup. Silakan periksa kembali.";
    toast.add({
      icon: "i-heroicons-exclamation-circle",
      title: "Gagal Menyimpan",
      description: msg,
      color: "error",
    });
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="space-y-5 max-w-5xl mx-auto">
    <!-- Header Page Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex items-center gap-3">
        <UButton
          to="/admin/roles"
          icon="i-heroicons-arrow-left"
          color="neutral"
          variant="ghost"
          size="sm"
          aria-label="Kembali ke Akses Grup"
          class="shrink-0 text-gray-500 hover:text-gray-900 dark:hover:text-white"
        />
        <div>
          <h1 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span>Edit Akses Grup</span>
            <UBadge
              v-if="role"
              :label="`ID: #${role.id}`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="font-mono text-[10px]"
            />
            <UBadge
              v-if="isSystemRole"
              label="Sistem"
              color="primary"
              variant="subtle"
              size="xs"
              class="text-[10px]"
            />
          </h1>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Kelola konfigurasi nama peran dan alokasi hak akses spesifik untuk grup ini.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          label="Batal"
          color="neutral"
          variant="outline"
          size="sm"
          to="/admin/roles"
        />
        <UButton
          v-if="canEdit && role"
          label="Simpan Perubahan"
          icon="i-heroicons-check"
          color="primary"
          variant="solid"
          size="sm"
          :loading="saving"
          @click="handleSaveRole"
        />
      </div>
    </div>

    <!-- Loading Skeleton State -->
    <div v-if="loading && !role" class="space-y-4">
      <UCard
        :ui="{
          root: 'bg-white dark:bg-[#0b0f19] ring-1 ring-gray-200/70 dark:ring-white/[0.07] rounded-xl shadow-none',
          body: 'p-6 space-y-4',
        }"
      >
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-gray-200 dark:bg-gray-800 animate-pulse" />
          <div class="space-y-1.5 flex-1">
            <div class="h-4 w-48 bg-gray-200 dark:bg-gray-800 rounded animate-pulse" />
            <div class="h-3 w-64 bg-gray-200 dark:bg-gray-800 rounded animate-pulse" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
          <div class="h-10 bg-gray-200 dark:bg-gray-800 rounded animate-pulse" />
          <div class="h-10 bg-gray-200 dark:bg-gray-800 rounded animate-pulse" />
        </div>
      </UCard>
    </div>

    <!-- Error State -->
    <UAlert
      v-else-if="roleError || (!role && !loading)"
      icon="i-heroicons-exclamation-triangle"
      title="Akses Grup Tidak Ditemukan"
      description="Data akses grup dengan ID tersebut tidak dapat dimuat atau telah dihapus."
      color="error"
      variant="soft"
      :actions="[
        {
          label: 'Kembali ke Akses Grup',
          color: 'neutral',
          variant: 'outline',
          to: '/admin/roles',
        },
      ]"
    />

    <!-- Main Content Form -->
    <div v-else-if="role" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Left Column: Role Details & Permissions Matrix (2/3) -->
      <div class="lg:col-span-2 space-y-5">
        <!-- Card 1: Informasi Akses Grup -->
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] ring-1 ring-gray-200/70 dark:ring-white/[0.07] rounded-xl shadow-none',
            header: 'px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]',
            body: 'p-5 space-y-4',
          }"
        >
          <template #header>
            <div class="flex items-center gap-2">
              <UIcon name="i-heroicons-shield-check" class="size-4 text-primary-500" />
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                Informasi Akses Grup
              </h2>
            </div>
          </template>

          <form class="space-y-4" @submit.prevent="handleSaveRole">
            <UFormField
              label="Nama Akses Grup"
              required
              :error="errors.name"
              help="Nama pengenal unik peran dalam sistem."
            >
              <UInput
                v-model="formState.name"
                :disabled="isSystemRole"
                placeholder="Contoh: operator, editor, bendahara"
                size="sm"
                class="w-full font-mono"
              />
            </UFormField>

            <div
              v-if="isSystemRole"
              class="px-3.5 py-2.5 bg-amber-500/10 text-amber-800 dark:text-amber-300 rounded-lg text-xs flex items-center gap-2.5"
            >
              <UIcon name="i-heroicons-information-circle" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
              <p>
                Akses grup <strong>admin</strong> merupakan grup sistem utama. Nama peran ini dilindungi dan tidak dapat diubah namanya.
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-6 pt-1 text-xs text-gray-500 dark:text-gray-400">
              <div class="flex items-center gap-1.5">
                <span>Guard:</span>
                <span class="font-mono text-gray-800 dark:text-gray-200 font-medium">{{ role.guard_name || 'web' }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span>Status:</span>
                <UBadge
                  :label="isSystemRole ? 'Sistem (Protected)' : 'Kustom'"
                  :color="isSystemRole ? 'primary' : 'neutral'"
                  variant="subtle"
                  size="xs"
                  class="text-[10px]"
                />
              </div>
            </div>
          </form>
        </UCard>

        <!-- Card 2: Permissions Matrix -->
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] ring-1 ring-gray-200/70 dark:ring-white/[0.07] rounded-xl shadow-none',
            header: 'px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]',
            body: 'p-5 space-y-5',
          }"
        >
          <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div class="flex items-center gap-2">
                <UIcon name="i-heroicons-key" class="size-4 text-primary-500" />
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <span>Daftar Hak Akses</span>
                  <UBadge
                    :label="`${formState.selectedPermissions.length} / ${allPermissions.length} Aktif`"
                    :color="formState.selectedPermissions.length > 0 ? 'primary' : 'neutral'"
                    variant="subtle"
                    size="xs"
                    class="text-[11px]"
                  />
                </h2>
              </div>

              <!-- Quick Bulk Actions -->
              <div class="flex items-center gap-1.5 self-start sm:self-auto">
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
          </template>

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

          <!-- Empty Search State -->
          <div
            v-if="groupedPermissions.length === 0"
            class="text-xs text-gray-500 dark:text-gray-400 text-center py-8"
          >
            Tidak ada hak akses yang cocok dengan pencarian "<strong>{{ permissionSearch }}</strong>".
          </div>

          <!-- Permissions Group List -->
          <div v-else class="space-y-6">
            <div
              v-for="group in groupedPermissions"
              :key="group.key"
              class="space-y-2.5"
            >
              <!-- Group Header -->
              <div class="flex items-center justify-between pb-1.5 border-b border-gray-100 dark:border-white/[0.05]">
                <div class="flex items-center gap-2">
                  <UIcon :name="group.icon" class="size-4 text-gray-400" />
                  <span class="text-xs font-semibold text-gray-900 dark:text-white">
                    {{ group.label }}
                  </span>
                  <span class="text-[11px] text-gray-400 font-mono">
                    ({{ selectedInGroupCount(group) }}/{{ group.permissions.length }})
                  </span>
                </div>

                <UButton
                  :label="isGroupAllSelected(group) ? 'Batal Grup' : 'Pilih Semua Grup'"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  class="text-[11px] h-6 px-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                  @click="toggleGroup(group)"
                />
              </div>

              <!-- Group Permissions Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                <label
                  v-for="perm in group.permissions"
                  :key="perm.id"
                  class="flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer transition-colors select-none text-xs"
                  :class="[
                    isPermissionSelected(perm.name)
                      ? 'bg-primary-50/60 dark:bg-primary-950/30 text-gray-900 dark:text-white font-medium'
                      : 'hover:bg-gray-50 dark:hover:bg-white/[0.03] text-gray-600 dark:text-gray-300'
                  ]"
                  @click.prevent="togglePermission(perm.name)"
                >
                  <div class="flex items-center gap-2.5 min-w-0 pr-2">
                    <UCheckbox
                      :model-value="isPermissionSelected(perm.name)"
                      class="pointer-events-none"
                    />
                    <span class="font-mono truncate text-[11px]">{{ perm.name }}</span>
                  </div>

                  <UBadge
                    :label="getPermissionAction(perm.name)"
                    :color="getActionBadgeColor(getPermissionAction(perm.name))"
                    variant="subtle"
                    size="xs"
                    class="text-[9px] uppercase font-mono tracking-tight shrink-0"
                  />
                </label>
              </div>
            </div>
          </div>
        </UCard>
      </div>

      <!-- Right Column: Role Metadata & Quick Actions (1/3) -->
      <div class="space-y-5">
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] ring-1 ring-gray-200/70 dark:ring-white/[0.07] rounded-xl shadow-none',
            header: 'px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]',
            body: 'p-5 space-y-4',
          }"
        >
          <template #header>
            <div class="flex items-center gap-2">
              <UIcon name="i-heroicons-information-circle" class="size-4 text-gray-400" />
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                Ringkasan Akses Grup
              </h2>
            </div>
          </template>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-gray-500 dark:text-gray-400">Total Pengguna:</span>
              <span class="font-bold text-gray-900 dark:text-white">
                {{ role.users_count ?? 0 }} pengguna
              </span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-gray-500 dark:text-gray-400">Total Hak Akses:</span>
              <span class="font-bold text-gray-900 dark:text-white">
                {{ formState.selectedPermissions.length }} dari {{ allPermissions.length }}
              </span>
            </div>

            <div class="space-y-1.5 pt-1">
              <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                <span>Cakupan Hak Akses</span>
                <span class="font-mono">
                  {{ allPermissions.length ? Math.round((formState.selectedPermissions.length / allPermissions.length) * 100) : 0 }}%
                </span>
              </div>
              <div class="w-full bg-gray-100 dark:bg-gray-800/80 h-1.5 rounded-full overflow-hidden">
                <div
                  class="bg-primary-500 h-full rounded-full transition-all duration-300"
                  :style="{
                    width: `${allPermissions.length ? Math.round((formState.selectedPermissions.length / allPermissions.length) * 100) : 0}%`,
                  }"
                />
              </div>
            </div>

            <div v-if="role.created_at" class="pt-2 border-t border-gray-100 dark:border-white/[0.05] flex items-center justify-between">
              <span class="text-gray-400">Dibuat pada:</span>
              <span class="font-mono text-gray-600 dark:text-gray-300">
                {{ dayjs(role.created_at).format("D MMM YYYY") }}
              </span>
            </div>

            <div v-if="role.updated_at" class="flex items-center justify-between">
              <span class="text-gray-400">Diperbarui:</span>
              <span class="font-mono text-gray-600 dark:text-gray-300">
                {{ dayjs(role.updated_at).format("D MMM YYYY") }}
              </span>
            </div>
          </div>

          <div class="pt-3 border-t border-gray-100 dark:border-white/[0.05] space-y-2">
            <UButton
              :to="`/admin/users?role=${role.name}`"
              icon="i-heroicons-users"
              label="Lihat Pengguna dengan Role ini"
              color="neutral"
              variant="subtle"
              size="xs"
              class="w-full justify-center"
            />
            <UButton
              to="/admin/audit-logs?module=roles"
              icon="i-heroicons-clock"
              label="Lihat Riwayat Audit Role"
              color="neutral"
              variant="ghost"
              size="xs"
              class="w-full justify-center"
            />
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

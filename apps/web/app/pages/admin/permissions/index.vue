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
const { can } = usePermission();

definePageMeta({
  middleware: ["auth", "permission"],
  permission: "permissions-view",
});

const canCreate = computed(() => can('permissions-create') || can('permissions-manage'));
const canUpdate = computed(() => can('permissions-update') || can('permissions-manage'));
const canDelete = computed(() => can('permissions-delete') || can('permissions-manage'));
const hasAnyAction = computed(() => canUpdate.value || canDelete.value);

useSeoMeta({
  title: "Hak Akses | User Management",
});

// ─── Search & Pagination State ──────────────────────────────────────────────
const search = ref("");
const page = ref(1);
const perPage = ref(15);

const filteredPermissions = computed(() => {
  const all = data.value?.permissions ?? [];
  if (!search.value.trim()) return all;
  const q = search.value.toLowerCase().trim();
  return all.filter((p) => p.name.toLowerCase().includes(q));
});

const paginatedPermissions = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filteredPermissions.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => Math.ceil(filteredPermissions.value.length / perPage.value) || 1);

watch(search, () => {
  page.value = 1;
});

// ─── Create Permission Modal State ──────────────────────────────────────────
const isCreateModalOpen = ref(false);
const creating = ref(false);
const createName = ref("");
const createError = ref("");

function openCreateModal() {
  createName.value = "";
  createError.value = "";
  isCreateModalOpen.value = true;
}

async function handleCreatePermission() {
  createError.value = "";
  const trimmed = createName.value.trim().toLowerCase();

  if (!trimmed) {
    createError.value = "Nama hak akses wajib diisi.";
    return;
  }

  creating.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>("admin/permissions", {
      method: "POST",
      body: { name: trimmed },
    });

    if (res?.ok) {
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Hak akses baru berhasil ditambahkan.",
        color: "success",
      });
      isCreateModalOpen.value = false;
      await refresh();
    }
  } catch (err: any) {
    const apiErrors = err?.data?.errors || err?.response?._data?.errors;
    if (apiErrors?.name) {
      createError.value = Array.isArray(apiErrors.name) ? apiErrors.name[0] : String(apiErrors.name);
    } else {
      const msg = err?.data?.message || err?.response?._data?.message || "Gagal menambahkan hak akses.";
      createError.value = msg;
    }
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Gagal Menambahkan",
      description: createError.value,
      color: "error",
    });
  } finally {
    creating.value = false;
  }
}

// ─── Edit Permission Modal State ────────────────────────────────────────────
const isEditModalOpen = ref(false);
const updating = ref(false);
const editingPermission = ref<Permission | null>(null);
const editName = ref("");
const editError = ref("");

function openEditModal(perm: Permission) {
  editingPermission.value = perm;
  editName.value = perm.name;
  editError.value = "";
  isEditModalOpen.value = true;
}

async function handleUpdatePermission() {
  if (!editingPermission.value) return;
  editError.value = "";
  const trimmed = editName.value.trim().toLowerCase();

  if (!trimmed) {
    editError.value = "Nama hak akses wajib diisi.";
    return;
  }

  updating.value = true;
  try {
    const res = await $http<{ ok: boolean; message: string }>(
      `admin/permissions/${editingPermission.value.id}`,
      {
        method: "PUT",
        body: { name: trimmed },
      }
    );

    if (res?.ok) {
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Hak akses berhasil diperbarui.",
        color: "success",
      });
      isEditModalOpen.value = false;
      editingPermission.value = null;
      await refresh();
    }
  } catch (err: any) {
    const apiErrors = err?.data?.errors || err?.response?._data?.errors;
    if (apiErrors?.name) {
      editError.value = Array.isArray(apiErrors.name) ? apiErrors.name[0] : String(apiErrors.name);
    } else {
      const msg = err?.data?.message || err?.response?._data?.message || "Gagal memperbarui hak akses.";
      editError.value = msg;
    }
    toast.add({
      icon: "i-lucide-alert-circle",
      title: "Gagal Update",
      description: editError.value,
      color: "error",
    });
  } finally {
    updating.value = false;
  }
}

// ─── Delete Permission Modal State ──────────────────────────────────────────
const isDeleteModalOpen = ref(false);
const permissionToDelete = ref<Permission | null>(null);
const deleting = ref(false);

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
        icon: "i-lucide-check-circle",
        title: "Berhasil",
        description: res.message || "Hak akses berhasil dihapus.",
        color: "success",
      });
      isDeleteModalOpen.value = false;
      permissionToDelete.value = null;
      await refresh();
    }
  } catch (err: any) {
    const msg = err?.data?.message || err?.response?._data?.message || "Gagal menghapus hak akses.";
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

// ─── Table Columns ──────────────────────────────────────────────────────────
const columns = computed(() => [
  {
    accessorKey: "name",
    header: "Nama Hak Akses (Identifier)",
  },
  {
    accessorKey: "roles_count",
    header: "Terhubung ke Role",
    class: "w-44 text-center",
  },
  ...(hasAnyAction.value ? [{
    id: "actions",
    header: "Aksi",
    class: "w-28 text-right",
  }] : []),
]);
</script>

<template>
  <div class="space-y-4">
    <!-- Header Page & Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <UIcon name="i-lucide-key" class="size-5 text-emerald-600 dark:text-emerald-400" />
          <span>Hak Akses (Permissions)</span>
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Daftar kunci hak akses granular yang dapat diberikan kepada grup peran pengguna.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Cari hak akses..."
          size="sm"
          class="w-52 sm:w-64"
        />

        <UButton
          v-if="canCreate"
          label="Tambah Hak Akses"
          icon="i-lucide-plus"
          color="primary"
          size="sm"
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
        <span>Gagal memuat daftar hak akses. Silakan periksa koneksi backend.</span>
      </div>
      <UButton
        label="Coba Lagi"
        size="xs"
        color="error"
        variant="subtle"
        @click="() => refresh()"
      />
    </div>

    <!-- Permissions Table Card -->
    <UCard
      :ui="{
        root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg overflow-hidden shadow-none',
        body: 'p-0 sm:p-0',
      }"
    >
      <UTable
        :data="paginatedPermissions"
        :columns="columns"
        :loading="loading"
        loading-color="primary"
      >
        <!-- Permission Name Cell -->
        <template #name-cell="{ row }">
          <div class="flex items-center gap-2 font-mono text-xs py-1.5">
            <div class="size-6 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-key" class="size-3.5" />
            </div>
            <span class="text-gray-900 dark:text-white font-medium">
              {{ row.original.name }}
            </span>
          </div>
        </template>

        <!-- Roles Count Cell -->
        <template #roles_count-cell="{ row }">
          <div class="text-center font-mono text-xs">
            <UBadge
              v-if="row.original.roles_count && row.original.roles_count > 0"
              :label="`${row.original.roles_count} role`"
              color="primary"
              variant="soft"
              size="xs"
              class="text-[11px]"
            />
            <span v-else class="text-gray-400 dark:text-gray-500 text-[11px] italic">
              Belum digunakan
            </span>
          </div>
        </template>

        <!-- Actions Cell -->
        <template #actions-cell="{ row }">
          <div class="flex items-center justify-end gap-1">
            <!-- Edit Button -->
            <UTooltip v-if="canUpdate" text="Edit Hak Akses">
              <UButton
                icon="i-lucide-pencil"
                size="xs"
                color="neutral"
                variant="ghost"
                class="hover:text-emerald-600 dark:hover:text-emerald-400 cursor-pointer"
                @click="openEditModal(row.original)"
              />
            </UTooltip>

            <!-- Delete Button -->
            <UTooltip v-if="canDelete" text="Hapus Hak Akses">
              <UButton
                icon="i-lucide-trash-2"
                size="xs"
                color="neutral"
                variant="ghost"
                class="hover:text-red-600 dark:hover:text-red-400 cursor-pointer"
                @click="confirmDelete(row.original)"
              />
            </UTooltip>
          </div>
        </template>

        <!-- Empty State -->
        <template #empty>
          <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            <UIcon name="i-lucide-key" class="size-8 mx-auto mb-2 opacity-40" />
            <p class="font-medium text-gray-700 dark:text-gray-300">Tidak ada hak akses ditemukan</p>
            <p class="text-xs mt-1 text-gray-400">Buat hak akses baru untuk ditugaskan ke grup peran.</p>
          </div>
        </template>
      </UTable>

      <!-- Pagination & Counter Footer -->
      <template v-if="filteredPermissions.length > 0" #footer>
        <div class="flex flex-col sm:flex-row items-center justify-between px-4 py-3 gap-2">
          <span class="text-xs text-gray-500 dark:text-gray-400">
            Menampilkan {{ paginatedPermissions.length }} dari total {{ filteredPermissions.length }} hak akses
          </span>
          <UPagination
            v-if="totalPages > 1"
            v-model:page="page"
            :total="filteredPermissions.length"
            :items-per-page="perPage"
            size="xs"
          />
        </div>
      </template>
    </UCard>

    <!-- ═══ MODAL: TAMBAH HAK AKSES ════════════════════════════════════════════ -->
    <UModal
      v-model:open="isCreateModalOpen"
      title="Tambah Hak Akses Baru"
      description="Tentukan kunci hak akses granular (contoh: users.create, dataset.export)."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-md' }"
    >
      <template #body>
        <form class="space-y-3.5" @submit.prevent="handleCreatePermission">
          <UFormField
            label="Kunci Identifier Hak Akses"
            required
            :error="createError"
            size="sm"
          >
            <UInput
              v-model="createName"
              placeholder="Contoh: jalan-desa.edit, users.create"
              class="w-full font-mono text-xs"
              autofocus
            />
            <template #help>
              <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                Format: <code class="font-mono text-emerald-600 dark:text-emerald-400">modul.aksi</code> (huruf kecil, dapat menggunakan titik, strip, atau garis bawah).
              </p>
            </template>
          </UFormField>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isCreateModalOpen = false"
          />
          <UButton
            label="Simpan Hak Akses"
            color="primary"
            variant="solid"
            :loading="creating"
            @click="handleCreatePermission"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: EDIT HAK AKSES ══════════════════════════════════════════════ -->
    <UModal
      v-model:open="isEditModalOpen"
      title="Edit Hak Akses"
      description="Perbarui nama kunci identifier hak akses sistem."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-md' }"
    >
      <template #body>
        <form class="space-y-3.5" @submit.prevent="handleUpdatePermission">
          <UFormField
            label="Kunci Identifier Hak Akses"
            required
            :error="editError"
            size="sm"
          >
            <UInput
              v-model="editName"
              placeholder="Contoh: jalan-desa.edit"
              class="w-full font-mono text-xs"
              autofocus
            />
            <template #help>
              <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                Catatan: Mengubah nama identifier akan memengaruhi verifikasi izin pada seluruh role yang menggunakannya.
              </p>
            </template>
          </UFormField>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isEditModalOpen = false"
          />
          <UButton
            label="Simpan Perubahan"
            color="primary"
            variant="solid"
            :loading="updating"
            @click="handleUpdatePermission"
          />
        </div>
      </template>
    </UModal>

    <!-- ═══ MODAL: KONFIRMASI HAPUS HAK AKSES ══════════════════════════════════ -->
    <UModal
      v-model:open="isDeleteModalOpen"
      title="Hapus Hak Akses"
      description="Tindakan ini akan menghapus hak akses secara permanen dari sistem."
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-md' }"
    >
      <template #body>
        <div class="space-y-3">
          <div class="p-3 bg-red-50 dark:bg-red-950/30 rounded-md border border-red-200 dark:border-red-900/50 flex items-start gap-2.5 text-xs text-red-700 dark:text-red-300">
            <UIcon name="i-lucide-alert-triangle" class="size-4 shrink-0 mt-0.5" />
            <p>
              Apakah Anda yakin ingin menghapus hak akses <code class="font-mono font-semibold">{{ permissionToDelete?.name }}</code>?
            </p>
          </div>

          <div
            v-if="permissionToDelete?.roles_count && permissionToDelete.roles_count > 0"
            class="p-2.5 bg-amber-50 dark:bg-amber-950/30 rounded-md border border-amber-200 dark:border-amber-900/50 text-xs text-amber-700 dark:text-amber-300 flex items-center gap-2"
          >
            <UIcon name="i-lucide-info" class="size-4 shrink-0" />
            <span>Hak akses ini saat ini terhubung ke <strong>{{ permissionToDelete.roles_count }} role</strong> dan akan otomatis dicabut.</span>
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
            label="Hapus Permanen"
            color="error"
            variant="solid"
            :loading="deleting"
            @click="handleDeletePermission"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

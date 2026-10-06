<script lang="ts" setup>
definePageMeta({
  middleware: ["auth", "permission"],
  permission: "users-update",
});

useSeoMeta({
  title: "Edit Pengguna | User Management",
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
  status?: boolean;
  email_verified_at: string | null;
  created_at: string;
  roles: Role[];
  kecamatan?: { id: number; nama_kecamatan: string } | null;
  desa?: { id: number; nama_desa: string } | null;
}

interface RolesResponse {
  ok: boolean;
  roles: Role[];
}

const route = useRoute();
const toast = useToast();
const auth = useAuthStore();
const dayjs = useDayjs();
const { can } = usePermission();

const canUpdate = computed(() => can("users-update"));
const userId = computed(() => (route.params.id as string) || (route.query.id as string) || "");

// ─── Fetch User & Roles Data ────────────────────────────────────────────────
const {
  data: userData,
  status: userStatus,
  refresh: refreshUser,
  error: userError,
} = useHttp<{ ok: boolean; user: UserItem }>(
  () => `admin/users/${userId.value || 0}`,
  {
    watch: [userId],
  }
);

const {
  data: rolesData,
  status: rolesStatus,
} = useHttp<RolesResponse>("admin/roles");

const loading = computed(() => {
  if (!userId.value) return false;
  return userStatus.value === "pending" || rolesStatus.value === "pending";
});

const user = computed<UserItem | null>(() => userData.value?.user ?? null);
const rolesList = computed<Role[]>(() => rolesData.value?.roles ?? []);

// ─── Form State ─────────────────────────────────────────────────────────────
const form = reactive({
  name: "",
  email: "",
  status: true,
  roles: [] as string[],
  password: "",
  password_confirmation: "",
});

const errors = reactive<Record<string, string>>({});
const saving = ref(false);
const showPassword = ref(false);

const isSelf = computed(() => {
  if (!user.value || !auth.user) return false;
  return user.value.email === auth.user.email || (auth.user.uuid && user.value.uuid === auth.user.uuid);
});

// Populate form when user data loads
watch(
  user,
  (val) => {
    if (val) {
      form.name = val.name || "";
      form.email = val.email || "";
      form.status = val.status !== undefined ? Boolean(val.status) : true;
      form.roles = (val.roles || []).map((r) => r.name);
      form.password = "";
      form.password_confirmation = "";
    }
  },
  { immediate: true }
);

function toggleRole(roleName: string) {
  // Prevent admin from removing their own admin role
  if (isSelf.value && roleName === "admin" && form.roles.includes("admin")) {
    toast.add({
      icon: "i-lucide-alert-triangle",
      title: "Perhatian",
      description: "Anda tidak dapat mencabut role admin dari akun Anda sendiri.",
      color: "warning",
    });
    return;
  }

  const idx = form.roles.indexOf(roleName);
  if (idx > -1) {
    form.roles.splice(idx, 1);
  } else {
    form.roles.push(roleName);
  }
}

async function handleSave() {
  if (!userId.value || !canUpdate.value) return;
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!form.name.trim()) {
    errors.name = "Nama lengkap wajib diisi.";
  }
  if (!form.email.trim()) {
    errors.email = "Alamat email wajib diisi.";
  }
  if (form.password) {
    if (form.password.length < 8) {
      errors.password = "Password minimal 8 karakter.";
    }
    if (form.password !== form.password_confirmation) {
      errors.password_confirmation = "Konfirmasi password tidak cocok.";
    }
  }
  if (form.roles.length === 0) {
    errors.roles = "Pilih setidaknya satu role.";
  }

  if (Object.keys(errors).length > 0) return;

  saving.value = true;
  try {
    const payload: Record<string, any> = {
      name: form.name.trim(),
      email: form.email.trim(),
      roles: form.roles,
      status: form.status,
    };

    if (form.password) {
      payload.password = form.password;
      payload.password_confirmation = form.password_confirmation;
    }

    const res = await $http<{ ok: boolean; message: string; user?: any }>(`admin/users/${userId.value}`, {
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
      await refreshUser();

      if (isSelf.value) {
        await auth.fetchUser();
      }
    }
  } catch (err: any) {
    const apiErrors = err?.data?.errors || err?.response?._data?.errors;
    if (apiErrors) {
      Object.entries(apiErrors).forEach(([field, msgs]) => {
        errors[field] = Array.isArray(msgs) ? msgs[0] : String(msgs);
      });
    }
    const msg = err?.data?.message || err?.response?._data?.message || "Gagal memperbarui data pengguna.";
    toast.add({
      icon: "i-lucide-alert-circle",
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
          to="/admin/users"
          icon="i-lucide-arrow-left"
          color="neutral"
          variant="ghost"
          size="sm"
          aria-label="Kembali ke Manajemen Pengguna"
          class="shrink-0 text-gray-500 hover:text-gray-900 dark:hover:text-white"
        />
        <div>
          <h1 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span>Edit Pengguna</span>
            <UBadge
              v-if="user"
              :label="`ID: ${user.id}`"
              color="neutral"
              variant="subtle"
              size="xs"
              class="font-mono text-[10px]"
            />
          </h1>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Perbarui informasi profil pengguna, hak akses role, dan status akun sistem.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <UButton
          label="Batal"
          color="neutral"
          variant="outline"
          size="sm"
          to="/admin/users"
        />
        <UButton
          v-if="canUpdate && user"
          label="Simpan Perubahan"
          icon="i-lucide-save"
          color="primary"
          variant="solid"
          size="sm"
          :loading="saving"
          @click="handleSave"
        />
      </div>
    </div>

    <!-- ═══ STATE: LOADING ════════════════════════════════════════════════════ -->
    <div
      v-if="loading"
      class="p-16 text-center text-sm text-gray-500 dark:text-gray-400 space-y-3 bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] rounded-lg shadow-sm"
    >
      <UIcon name="i-lucide-loader-2" class="size-6 animate-spin mx-auto text-primary-500" />
      <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data pengguna...</p>
    </div>

    <!-- ═══ STATE: INVALID PARAMETER OR NOT FOUND ═════════════════════════════ -->
    <div
      v-else-if="!userId || userError || !user"
      class="p-12 text-center text-sm text-gray-500 dark:text-gray-400 space-y-3 bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] rounded-lg shadow-sm"
    >
      <div class="size-12 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center mx-auto">
        <UIcon name="i-lucide-user-x" class="size-6" />
      </div>
      <p class="font-semibold text-gray-800 dark:text-gray-200 text-base">
        {{ !userId ? "Parameter ID Pengguna Tidak Ditemukan" : "Pengguna Tidak Ditemukan" }}
      </p>
      <p class="text-xs text-gray-400 max-w-md mx-auto">
        {{
          !userId
            ? "URL halaman edit harus menyertakan parameter ID pengguna (/admin/users/{id})."
            : "Data pengguna dengan ID tersebut tidak dapat dimuat atau mungkin telah dihapus."
        }}
      </p>
      <div class="pt-2">
        <UButton
          label="Kembali ke Manajemen Pengguna"
          icon="i-lucide-arrow-left"
          color="neutral"
          variant="outline"
          size="sm"
          to="/admin/users"
        />
      </div>
    </div>

    <!-- ═══ STATE: READY (EDIT FORM) ═══════════════════════════════════════════ -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Main Form Column (2 Cols) -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Informasi Profil Pengguna -->
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg shadow-none',
            body: 'p-4 sm:p-5 space-y-4',
          }"
        >
          <div class="border-b border-gray-100 dark:border-white/[0.06] pb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <UIcon name="i-lucide-user" class="size-4 text-blue-600 dark:text-blue-400" />
              <span>Profil Pengguna</span>
            </h2>
            <UBadge
              v-if="isSelf"
              label="Akun Anda Sendiri"
              color="primary"
              variant="subtle"
              size="xs"
              class="text-[10px]"
            />
          </div>

          <form class="space-y-4" @submit.prevent="handleSave">
            <UFormField label="Nama Lengkap" required :error="errors.name" size="sm">
              <UInput
                v-model="form.name"
                placeholder="Masukkan nama lengkap pengguna"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Alamat Email" required :error="errors.email" size="sm">
              <UInput
                v-model="form.email"
                type="email"
                placeholder="nama@domain.com"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Status Akun" size="sm">
              <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200/70 dark:border-white/[0.08] bg-gray-50/50 dark:bg-gray-900/30">
                <div class="space-y-0.5">
                  <span class="text-xs font-medium text-gray-900 dark:text-white block">
                    {{ form.status ? "Akun Aktif" : "Akun Nonaktif" }}
                  </span>
                  <span class="text-[11px] text-gray-500 dark:text-gray-400 block">
                    {{ form.status ? "Pengguna dapat masuk dan menggunakan sistem sesuai hak akses." : "Pengguna dinonaktifkan dan tidak dapat masuk ke sistem." }}
                  </span>
                </div>
                <USwitch
                  v-model="form.status"
                  color="primary"
                  :disabled="isSelf"
                />
              </div>
            </UFormField>
          </form>
        </UCard>

        <!-- Hak Akses Grup (Roles) -->
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg shadow-none',
            body: 'p-4 sm:p-5 space-y-4',
          }"
        >
          <div class="border-b border-gray-100 dark:border-white/[0.06] pb-3">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <UIcon name="i-lucide-shield-check" class="size-4 text-blue-600 dark:text-blue-400" />
              <span>Hak Akses Grup (Role)</span>
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Pilih satu atau lebih role untuk menentukan wewenang pengguna dalam sistem.
            </p>
          </div>

          <UFormField required :error="errors.roles" size="sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div
                v-for="role in rolesList"
                :key="role.id"
                :class="[
                  'flex items-center justify-between p-2.5 rounded-lg border transition-colors cursor-pointer select-none',
                  form.roles.includes(role.name)
                    ? 'border-blue-500/40 bg-blue-50/50 dark:bg-blue-950/20 dark:border-blue-500/30'
                    : 'border-gray-200/70 dark:border-white/[0.08] hover:bg-gray-50 dark:hover:bg-gray-800/40',
                ]"
                @click="toggleRole(role.name)"
              >
                <div class="flex items-center gap-2.5 min-w-0">
                  <UCheckbox
                    :model-value="form.roles.includes(role.name)"
                    :disabled="isSelf && role.name === 'admin'"
                    class="text-xs capitalize"
                    @click.stop
                    @update:model-value="() => toggleRole(role.name)"
                  />
                  <div class="min-w-0">
                    <span class="text-xs font-semibold text-gray-900 dark:text-white capitalize block truncate">
                      {{ role.name }}
                    </span>
                    <span class="text-[10px] text-gray-400 block truncate">
                      {{ role.name === "admin" ? "Akses menyeluruh sistem" : "Akses operasional modul" }}
                    </span>
                  </div>
                </div>

                <UBadge
                  v-if="role.name === 'admin'"
                  label="Super"
                  color="primary"
                  variant="subtle"
                  size="xs"
                  class="text-[9px] px-1 py-0 h-4"
                />
              </div>
            </div>
          </UFormField>
        </UCard>

        <!-- Ganti Kata Sandi (Opsional) -->
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg shadow-none',
            body: 'p-4 sm:p-5 space-y-4',
          }"
        >
          <div class="border-b border-gray-100 dark:border-white/[0.06] pb-3 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <UIcon name="i-lucide-lock" class="size-4 text-blue-600 dark:text-blue-400" />
                <span>Atur Ulang Password</span>
              </h2>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Kosongkan kedua kolom di bawah jika tidak ingin mengganti kata sandi.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <UFormField label="Password Baru" :error="errors.password" size="sm">
              <div class="relative w-full">
                <UInput
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Minimal 8 karakter"
                  class="w-full"
                />
                <button
                  type="button"
                  tabindex="-1"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  @click="showPassword = !showPassword"
                >
                  <UIcon :name="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'" class="size-3.5" />
                </button>
              </div>
            </UFormField>

            <UFormField label="Ulangi Password Baru" :error="errors.password_confirmation" size="sm">
              <UInput
                v-model="form.password_confirmation"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Konfirmasi password baru"
                class="w-full"
              />
            </UFormField>
          </div>
        </UCard>

        <!-- Submit Button Toolbar -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            to="/admin/users"
          />
          <UButton
            label="Simpan Perubahan"
            icon="i-lucide-save"
            color="primary"
            variant="solid"
            :loading="saving"
            @click="handleSave"
          />
        </div>
      </div>

      <!-- Right Column: User Metadata Overview (1 Col) -->
      <div class="lg:col-span-1 space-y-4">
        <UCard
          :ui="{
            root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg shadow-none',
            body: 'p-4 sm:p-5 space-y-4',
          }"
        >
          <!-- User Profile Brief -->
          <div class="flex items-center gap-3">
            <div class="size-11 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 flex items-center justify-center font-bold text-base shrink-0">
              {{ user.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <div class="min-w-0">
              <span class="text-sm font-bold text-gray-900 dark:text-white truncate block">
                {{ user.name }}
              </span>
              <span class="text-xs text-gray-500 dark:text-gray-400 truncate block">
                {{ user.email }}
              </span>
            </div>
          </div>

          <div class="h-px bg-gray-100 dark:bg-white/[0.06]" />

          <!-- Metadata Details -->
          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-gray-400">Status Akun:</span>
              <UBadge
                :label="form.status ? 'Aktif' : 'Nonaktif'"
                :color="form.status ? 'primary' : 'neutral'"
                variant="subtle"
                size="xs"
                class="text-[10px]"
              />
            </div>

            <div class="flex items-center justify-between">
              <span class="text-gray-400">Verifikasi Email:</span>
              <UBadge
                v-if="user.email_verified_at"
                label="Terverifikasi"
                color="primary"
                variant="soft"
                size="xs"
                class="text-[10px]"
              />
              <span v-else class="text-gray-400 italic">Belum Verifikasi</span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-gray-400">Tanggal Terdaftar:</span>
              <span class="font-mono text-gray-700 dark:text-gray-300">
                {{ dayjs(user.created_at).format("DD MMM YYYY") }}
              </span>
            </div>

            <div v-if="user.id" class="flex items-center justify-between">
              <span class="text-gray-400">User ID:</span>
              <span class="font-mono text-gray-700 dark:text-gray-300">#{{ user.id }}</span>
            </div>

            <div v-if="user.uuid" class="space-y-1">
              <span class="text-gray-400 block">UUID:</span>
              <span class="font-mono text-[10px] text-gray-500 dark:text-gray-400 block truncate" :title="user.uuid">
                {{ user.uuid }}
              </span>
            </div>

            <div v-if="user.kecamatan || user.desa" class="pt-2 border-t border-gray-100 dark:border-white/[0.06] space-y-1.5">
              <span class="text-gray-400 block font-medium">Wilayah Penugasan:</span>
              <div v-if="user.kecamatan" class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                <UIcon name="i-lucide-map-pin" class="size-3 text-blue-500 shrink-0" />
                <span class="truncate">Kec. {{ user.kecamatan.nama_kecamatan }}</span>
              </div>
              <div v-if="user.desa" class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                <UIcon name="i-lucide-map" class="size-3 text-blue-500 shrink-0" />
                <span class="truncate">Desa {{ user.desa.nama_desa }}</span>
              </div>
            </div>
          </div>

          <div class="pt-2">
            <UButton
              :to="`/admin/audit-logs?user_id=${user.id}`"
              icon="i-lucide-history"
              label="Lihat Audit Log Pengguna"
              color="neutral"
              variant="subtle"
              size="xs"
              class="w-full justify-center"
            />
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

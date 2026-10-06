<script setup lang="ts">
import type { RefSumberDana } from '~/types/infrastruktur';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { usePermission } from '~/composables/usePermission';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'ref-sumber-dana-view',
});

useSeoMeta({
  title: 'Master Referensi Sumber Dana',
});

const toast = useToast();
const { can } = usePermission();
const api = useInfrastrukturApi();

const canManage = computed(() => can('ref-sumber-dana-manage'));

const items = ref<RefSumberDana[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const filterActiveOnly = ref(false);

const formKategoriOptions = [
  { label: 'Daerah', value: 'Daerah' },
  { label: 'Pusat', value: 'Pusat' },
  { label: 'Bantuan', value: 'Bantuan' },
  { label: 'Desa', value: 'Desa' },
  { label: 'Provinsi', value: 'Provinsi' }
];

async function loadData() {
  loading.value = true;
  try {
    const res = await api.fetchSumberDanaList({ active_only: filterActiveOnly.value });
    items.value = res.data || [];
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Data',
      description: err?.data?.message || 'Terjadi kesalahan saat memuat data sumber dana.',
      color: 'error',
    });
  } finally {
    loading.value = false;
  }
}

const filteredItems = computed(() => {
  let list = items.value;

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(
      (i) =>
        i.kode.toLowerCase().includes(q) ||
        i.nama.toLowerCase().includes(q) ||
        (i.kategori && i.kategori.toLowerCase().includes(q))
    );
  }

  return list;
});

// ─── Modal Form State ────────────────────────────────────────────────────────
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref<string | null>(null);
const submitting = ref(false);

const formState = reactive({
  kode: '',
  nama: '',
  kategori: 'APBD',
  is_active: true,
  sort_order: 1,
});

function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  Object.assign(formState, {
    kode: '',
    nama: '',
    kategori: 'APBD',
    is_active: true,
    sort_order: (items.value.length || 0) + 1,
  });
  isModalOpen.value = true;
}

function openEditModal(sd: RefSumberDana) {
  isEditing.value = true;
  editingId.value = sd.id;
  Object.assign(formState, {
    kode: sd.kode,
    nama: sd.nama,
    kategori: sd.kategori || 'APBD',
    is_active: sd.is_active ?? true,
    sort_order: sd.sort_order ?? 1,
  });
  isModalOpen.value = true;
}

async function handleSubmit() {
  if (!formState.kode.trim() || !formState.nama.trim()) {
    toast.add({
      icon: 'i-lucide-alert-triangle',
      title: 'Validasi Gagal',
      description: 'Kode dan nama sumber dana wajib diisi.',
      color: 'error',
    });
    return;
  }

  submitting.value = true;
  try {
    if (isEditing.value && editingId.value) {
      await api.updateSumberDana(editingId.value, formState);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Diperbarui',
        description: `Sumber dana '${formState.nama}' berhasil disimpan.`,
        color: 'success',
      });
    } else {
      await api.createSumberDana(formState);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Ditambahkan',
        description: `Sumber dana '${formState.nama}' berhasil ditambahkan.`,
        color: 'success',
      });
    }
    isModalOpen.value = false;
    await loadData();
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menyimpan',
      description: err?.data?.message || 'Periksa kembali data input.',
      color: 'error',
    });
  } finally {
    submitting.value = false;
  }
}

// ─── Modal Delete State ──────────────────────────────────────────────────────
const isDeleteOpen = ref(false);
const itemToDelete = ref<RefSumberDana | null>(null);
const deleting = ref(false);

function confirmDelete(sd: RefSumberDana) {
  itemToDelete.value = sd;
  isDeleteOpen.value = true;
}

async function handleDelete() {
  if (!itemToDelete.value) return;
  deleting.value = true;
  try {
    await api.deleteSumberDana(itemToDelete.value.id);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Berhasil Dihapus',
      description: `Sumber dana '${itemToDelete.value.nama}' telah dihapus.`,
      color: 'success',
    });
    isDeleteOpen.value = false;
    await loadData();
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menghapus',
      description: err?.data?.message || 'Data tidak dapat dihapus karena digunakan pada plotting anggaran.',
      color: 'error',
    });
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  loadData();
});
</script>

<template>
  <div class="space-y-4">
    <!-- Header Page & Actions Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <UIcon name="i-lucide-coins" class="size-5 text-primary-500" />
          <span>Master Referensi Sumber Dana</span>
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Katalog nomenklatur sumber pendanaan anggaran pembangunan infrastruktur (APBD, BKK, DDS, dll).
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <UInput
          v-model="searchQuery"
          icon="i-lucide-search"
          placeholder="Cari kode atau nama..."
          size="sm"
          class="w-56"
        />

        <div class="flex items-center gap-2 px-2 py-1 rounded-md bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-white/[0.08]">
          <UCheckbox
            v-model="filterActiveOnly"
            label="Hanya Aktif"
            size="sm"
            @update:model-value="loadData"
          />
        </div>

        <UButton
          icon="i-lucide-refresh-cw"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          @click="loadData"
        />

        <UButton
          v-if="canManage"
          label="Tambah Sumber Dana"
          icon="i-lucide-plus"
          size="sm"
          color="primary"
          variant="solid"
          @click="openCreateModal"
        />
      </div>
    </div>

    <!-- Table Card Container -->
    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <!-- Loading Skeleton State -->
      <div v-if="loading" class="p-8 text-center space-y-3">
        <UIcon name="i-lucide-loader-2" class="size-6 animate-spin mx-auto text-primary-500" />
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data sumber dana...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="filteredItems.length === 0"
        class="p-12 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
          <UIcon name="i-lucide-coins" class="size-6 opacity-60" />
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Tidak ada data sumber dana</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          Belum ada sumber dana yang cocok dengan filter atau kata kunci pencarian.
        </p>
        <div v-if="canManage && !searchQuery.trim()" class="pt-2">
          <UButton
            label="Tambah Sumber Dana Baru"
            icon="i-lucide-plus"
            size="sm"
            color="primary"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- Table Content -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-3 text-center w-16">Urutan</th>
              <th scope="col" class="py-3 px-4 w-44">Kode</th>
              <th scope="col" class="py-3 px-4">Nama Sumber Dana</th>
              <th scope="col" class="py-3 px-3 text-center w-36">Kategori</th>
              <th scope="col" class="py-3 px-3 text-center w-28">Status</th>
              <th v-if="canManage" scope="col" class="py-3 px-4 text-right w-24">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="row in filteredItems"
              :key="row.id"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <!-- Urutan -->
              <td class="py-3 px-3 text-center font-mono font-semibold text-gray-500 dark:text-gray-400">
                {{ row.sort_order }}
              </td>

              <!-- Kode -->
              <td class="py-3 px-4">
                <code class="text-[11px] px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded font-mono text-gray-700 dark:text-gray-300">
                  {{ row.kode }}
                </code>
              </td>

              <!-- Nama -->
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900 dark:text-white">{{ row.nama }}</div>
              </td>

              <!-- Kategori -->
              <td class="py-3 px-3 text-center">
                <UBadge
                  v-if="row.kategori"
                  :label="row.kategori"
                  color="primary"
                  variant="subtle"
                  size="xs"
                />
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- Status -->
              <td class="py-3 px-3 text-center">
                <UBadge
                  :label="row.is_active ? 'Aktif' : 'Non-aktif'"
                  :color="row.is_active ? 'success' : 'neutral'"
                  variant="subtle"
                  size="xs"
                />
              </td>

              <!-- Aksi -->
              <td v-if="canManage" class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <UButton
                    icon="i-lucide-pencil"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    @click="openEditModal(row)"
                  />
                  <UButton
                    icon="i-lucide-trash-2"
                    size="xs"
                    color="error"
                    variant="ghost"
                    @click="confirmDelete(row)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form Tambah / Edit -->
    <UModal
      v-model:open="isModalOpen"
      :title="isEditing ? `Edit Sumber Dana: ${formState.nama}` : 'Tambah Sumber Dana Baru'"
      :description="isEditing ? 'Perbarui informasi referensi sumber pendanaan.' : 'Daftarkan klasifikasi sumber anggaran pembangunan baru.'"
      :ui="{ content: 'sm:max-w-lg dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <form class="space-y-3.5 py-1" @submit.prevent="handleSubmit">
          <UFormField label="Kode Unik" required help="Gunakan snake_case huruf kecil, contoh: apbd_kab, bkk_desa">
            <UInput
              v-model="formState.kode"
              placeholder="misal: bkk_provinsi"
              class="w-full"
              :disabled="isEditing"
              required
            />
          </UFormField>

          <UFormField label="Nama Sumber Dana" required>
            <UInput
              v-model="formState.nama"
              placeholder="misal: Bantuan Keuangan Khusus Provinsi"
              class="w-full"
              required
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Kategori" help="Pengelompokan jenis anggaran">
              <USelectMenu
                v-model="formState.kategori"
                :items="formKategoriOptions"
                value-key="value"
                label-key="label"
                class="w-full"
              />
            </UFormField>

            <UFormField label="Urutan Sortir">
              <UInput v-model.number="formState.sort_order" type="number" min="1" class="w-full" />
            </UFormField>
          </div>

          <div class="pt-2">
            <UCheckbox v-model="formState.is_active" label="Aktif (Dapat dipilih pada plotting perencanaan)" />
          </div>
        </form>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            @click="isModalOpen = false"
          />
          <UButton
            :label="isEditing ? 'Simpan Perubahan' : 'Buat Sumber Dana'"
            color="primary"
            :loading="submitting"
            @click="handleSubmit"
          />
        </div>
      </template>
    </UModal>

    <!-- Modal Konfirmasi Hapus -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Konfirmasi Hapus"
      description="Apakah Anda yakin ingin menghapus referensi sumber dana ini? Tindakan ini tidak dapat dibatalkan jika belum terkait dengan plotting anggaran."
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Anda akan menghapus sumber dana:
          <strong class="text-gray-900 dark:text-white font-semibold">{{ itemToDelete?.nama }}</strong>.
        </p>
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
            label="Hapus Sumber Dana"
            color="error"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

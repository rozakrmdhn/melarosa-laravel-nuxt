<script setup lang="ts">
import type { InfrastrukturTipe } from '~/types/infrastruktur';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { usePermission } from '~/composables/usePermission';

definePageMeta({
  middleware: ['auth', 'permission'],
  permission: 'infrastruktur-tipe-view',
});

useSeoMeta({
  title: 'Master Tipe Infrastruktur',
});

const toast = useToast();
const { can } = usePermission();
const api = useInfrastrukturApi();

const canManage = computed(() => can('infrastruktur-tipe-manage'));

const items = ref<InfrastrukturTipe[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const filterActiveOnly = ref(false);

async function loadData() {
  loading.value = true;
  try {
    const res = await api.fetchTipeList({ active_only: filterActiveOnly.value });
    items.value = res.data || [];
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Data',
      description: err?.data?.message || 'Terjadi kesalahan saat memuat data tipe infrastruktur.',
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
      (item) =>
        item.nama.toLowerCase().includes(q) ||
        item.kode.toLowerCase().includes(q) ||
        (item.deskripsi && item.deskripsi.toLowerCase().includes(q))
    );
  }
  return list;
});

// ─── Modal Form State ────────────────────────────────────────────────────────
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref<string | null>(null);
const submitting = ref(false);

const geomOptions = [
  { label: 'LineString (Garis Jalan/Drainase)', value: 'LineString' },
  { label: 'MultiLineString (Jaringan Garis)', value: 'MultiLineString' },
  { label: 'Point (Titik Fasilitas/Bangunan)', value: 'Point' },
  { label: 'Polygon (Area/Kawasan)', value: 'Polygon' },
];

const formState = reactive({
  kode: '',
  nama: '',
  deskripsi: '',
  ikon: 'i-lucide-route',
  warna: '#10b981',
  geom_type: 'LineString',
  table_name: 'infrastruktur_segmen',
  has_segmen: true,
  is_active: true,
  sort_order: 1,
});

function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  Object.assign(formState, {
    kode: '',
    nama: '',
    deskripsi: '',
    ikon: 'i-lucide-route',
    warna: '#10b981',
    geom_type: 'LineString',
    table_name: 'infrastruktur_segmen',
    has_segmen: true,
    is_active: true,
    sort_order: (items.value.length || 0) + 1,
  });
  isModalOpen.value = true;
}

function openEditModal(tipe: InfrastrukturTipe) {
  isEditing.value = true;
  editingId.value = tipe.id;
  Object.assign(formState, {
    kode: tipe.kode,
    nama: tipe.nama,
    deskripsi: tipe.deskripsi || '',
    ikon: tipe.ikon || 'i-lucide-route',
    warna: tipe.warna || '#10b981',
    geom_type: tipe.geom_type || 'LineString',
    table_name: tipe.table_name || 'infrastruktur_segmen',
    has_segmen: tipe.has_segmen ?? true,
    is_active: tipe.is_active ?? true,
    sort_order: tipe.sort_order ?? 1,
  });
  isModalOpen.value = true;
}

async function handleSubmit() {
  if (!formState.kode.trim() || !formState.nama.trim()) {
    toast.add({
      icon: 'i-lucide-alert-triangle',
      title: 'Validasi Gagal',
      description: 'Kode dan nama tipe infrastruktur wajib diisi.',
      color: 'error',
    });
    return;
  }

  submitting.value = true;
  try {
    if (isEditing.value && editingId.value) {
      await api.updateTipe(editingId.value, formState);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Diperbarui',
        description: `Tipe infrastruktur '${formState.nama}' berhasil disimpan.`,
        color: 'success',
      });
    } else {
      await api.createTipe(formState);
      toast.add({
        icon: 'i-lucide-check-circle',
        title: 'Berhasil Ditambahkan',
        description: `Tipe infrastruktur '${formState.nama}' berhasil dibuat.`,
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
const itemToDelete = ref<InfrastrukturTipe | null>(null);
const deleting = ref(false);

function confirmDelete(tipe: InfrastrukturTipe) {
  itemToDelete.value = tipe;
  isDeleteOpen.value = true;
}

async function handleDelete() {
  if (!itemToDelete.value) return;
  deleting.value = true;
  try {
    await api.deleteTipe(itemToDelete.value.id);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Berhasil Dihapus',
      description: `Tipe '${itemToDelete.value.nama}' telah dihapus.`,
      color: 'success',
    });
    isDeleteOpen.value = false;
    await loadData();
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menghapus',
      description: err?.data?.message || 'Data tidak dapat dihapus karena memiliki segmen terkait.',
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
          <UIcon name="i-lucide-layers-2" class="size-5 text-primary-500" />
          <span>Master Tipe Infrastruktur</span>
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Katalog jenis infrastruktur, tipe spasial PostGIS, dan konfigurasi warna render peta.
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
          label="Tambah Tipe"
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
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data tipe infrastruktur...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="filteredItems.length === 0"
        class="p-12 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
          <UIcon name="i-lucide-layers-2" class="size-6 opacity-60" />
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Tidak ada tipe infrastruktur</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          Belum ada tipe infrastruktur yang sesuai dengan filter atau kata kunci pencarian.
        </p>
        <div v-if="canManage && !searchQuery.trim()" class="pt-2">
          <UButton
            label="Tambah Tipe Baru"
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
              <th scope="col" class="py-3 px-4 w-36">Simbologi Peta</th>
              <th scope="col" class="py-3 px-4 w-40">Kode</th>
              <th scope="col" class="py-3 px-4">Nama Tipe & Deskripsi</th>
              <th scope="col" class="py-3 px-3 text-center w-32">Geometri</th>
              <th scope="col" class="py-3 px-3 text-center w-28">Status</th>
              <th scope="col" class="py-3 px-3 text-center w-20">Urutan</th>
              <th v-if="canManage" scope="col" class="py-3 px-4 text-right w-24">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="row in filteredItems"
              :key="row.id"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <!-- Simbologi -->
              <td class="py-3 px-4">
                <div class="flex items-center gap-2.5">
                  <span
                    class="size-6 rounded-md flex items-center justify-center text-white shadow-sm shrink-0"
                    :style="{ backgroundColor: row.warna || '#10b981' }"
                  >
                    <UIcon :name="row.ikon || 'i-lucide-route'" class="size-3.5" />
                  </span>
                  <span
                    class="w-6 h-1 rounded-full shrink-0"
                    :style="{ backgroundColor: row.warna || '#10b981' }"
                  />
                  <span class="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {{ row.warna }}
                  </span>
                </div>
              </td>

              <!-- Kode -->
              <td class="py-3 px-4">
                <code class="text-[11px] px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded font-mono text-gray-700 dark:text-gray-300">
                  {{ row.kode }}
                </code>
              </td>

              <!-- Nama & Deskripsi -->
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900 dark:text-white">{{ row.nama }}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-1">
                  {{ row.deskripsi || '-' }}
                </div>
              </td>

              <!-- Geometri -->
              <td class="py-3 px-3 text-center">
                <UBadge
                  :label="row.geom_type || 'LineString'"
                  color="neutral"
                  variant="subtle"
                  size="xs"
                />
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

              <!-- Urutan -->
              <td class="py-3 px-3 text-center font-mono font-semibold text-gray-500 dark:text-gray-400">
                {{ row.sort_order }}
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
      :title="isEditing ? `Edit Tipe: ${formState.nama}` : 'Tambah Tipe Infrastruktur Baru'"
      :description="isEditing ? 'Perbarui informasi konfigurasi tipe dan visualisasi render peta.' : 'Daftarkan tipe infrastruktur fisik baru beserta ketentuan spasialnya.'"
      :ui="{ content: 'sm:max-w-xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
    >
      <template #body>
        <form class="space-y-3.5 py-1" @submit.prevent="handleSubmit">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Kode Unik" required help="Gunakan snake_case, contoh: jalan_desa, drainase">
              <UInput
                v-model="formState.kode"
                placeholder="misal: drainase_tpt"
                class="w-full"
                :disabled="isEditing"
                required
              />
            </UFormField>

            <UFormField label="Nama Tipe" required>
              <UInput
                v-model="formState.nama"
                placeholder="misal: Drainase & TPT"
                class="w-full"
                required
              />
            </UFormField>
          </div>

          <UFormField label="Deskripsi">
            <UTextarea
              v-model="formState.deskripsi"
              placeholder="Keterangan singkat mengenai tipe infrastruktur..."
              rows="2"
              class="w-full"
            />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <UFormField label="Warna Peta" required>
              <div class="flex items-center gap-2">
                <input
                  v-model="formState.warna"
                  type="color"
                  class="size-8 p-0.5 rounded border border-gray-300 dark:border-gray-700 bg-transparent cursor-pointer"
                />
                <UInput v-model="formState.warna" class="flex-1 font-mono text-xs" />
              </div>
            </UFormField>

            <UFormField label="Ikon Lucide" required>
              <UInput v-model="formState.ikon" placeholder="i-lucide-route" class="w-full">
                <template #leading>
                  <UIcon :name="formState.ikon || 'i-lucide-box'" class="size-4 text-primary-500" />
                </template>
              </UInput>
            </UFormField>

            <UFormField label="Urutan Sortir">
              <UInput v-model.number="formState.sort_order" type="number" min="1" class="w-full" />
            </UFormField>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <UFormField label="Tipe Geometri Spasial">
              <USelectMenu
                v-model="formState.geom_type"
                :items="geomOptions"
                value-key="value"
                label-key="label"
                class="w-full"
              />
            </UFormField>

            <div class="flex items-center gap-4 pt-5">
              <UCheckbox v-model="formState.is_active" label="Aktif" />
              <UCheckbox v-model="formState.has_segmen" label="Memiliki Segmen" />
            </div>
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
            :label="isEditing ? 'Simpan Perubahan' : 'Buat Tipe'"
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
      description="Tindakan ini tidak dapat dibatalkan jika data belum terhubung dengan segmen fisik."
    >
      <template #body>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Apakah Anda yakin ingin menghapus tipe infrastruktur
          <strong class="text-gray-900 dark:text-white">{{ itemToDelete?.nama }}</strong>?
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
            label="Hapus Tipe"
            color="error"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

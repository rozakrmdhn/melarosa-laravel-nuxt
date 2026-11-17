<script setup lang="ts">
import type { BackupHistory, BackupStatus, BackupType, PaginatedPayload } from '~/types/backup';
import {
  extractApiErrorMessage,
  formatBackupBytes,
  formatBackupDateTime,
  formatBackupDuration,
  getBackupStatusMeta,
  getBackupTriggerLabel,
  getBackupTypeMeta,
  useBackupManagement,
} from '~/composables/useBackupManagement';

interface Props {
  refreshTrigger: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  restoreRequested: [backup: BackupHistory];
  createRequested: [];
  deleted: [];
}>();

const toast = useToast();
const { listBackups, getBackupDetail, requestDownloadUrl, deleteBackup } = useBackupManagement();

const search = ref('');
const selectedType = ref<string>('all');
const selectedStatus = ref<string>('all');
const page = ref(1);
const perPage = 10;

const loading = ref(true);
const error = ref<string | null>(null);
const paginated = ref<PaginatedPayload<BackupHistory> | null>(null);

const detailModalOpen = ref(false);
const activeBackup = ref<BackupHistory | null>(null);
const loadingDetail = ref(false);

const deleteModalOpen = ref(false);
const backupToDelete = ref<BackupHistory | null>(null);
const deleting = ref(false);
const downloadingId = ref<string | null>(null);

const typeOptions = [
  { label: 'Semua Tipe', value: 'all' },
  { label: 'Database', value: 'database' },
  { label: 'File Storage', value: 'file' },
  { label: 'Full System', value: 'full' },
];

const statusOptions = [
  { label: 'Semua Status', value: 'all' },
  { label: 'Selesai', value: 'completed' },
  { label: 'Diproses', value: 'processing' },
  { label: 'Menunggu', value: 'pending' },
  { label: 'Gagal', value: 'failed' },
];

async function fetchList() {
  loading.value = true;
  error.value = null;

  try {
    paginated.value = await listBackups({
      page: page.value,
      per_page: perPage,
      search: search.value.trim() || undefined,
      backup_type: selectedType.value !== 'all' ? (selectedType.value as BackupType) : undefined,
      status: selectedStatus.value !== 'all' ? (selectedStatus.value as BackupStatus) : undefined,
    });
  } catch (err: any) {
    error.value = extractApiErrorMessage(err, 'Gagal memuat daftar riwayat backup.');
  } finally {
    loading.value = false;
  }
}

watch([page, selectedType, selectedStatus], () => {
  fetchList();
});

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    page.value = 1;
    fetchList();
  }, 300);
});

watch(() => props.refreshTrigger, () => {
  fetchList();
});

function resetFilters() {
  search.value = '';
  selectedType.value = 'all';
  selectedStatus.value = 'all';
  page.value = 1;
  fetchList();
}

async function openDetail(backup: BackupHistory) {
  detailModalOpen.value = true;
  loadingDetail.value = true;
  activeBackup.value = backup;
  try {
    activeBackup.value = await getBackupDetail(backup.id);
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: extractApiErrorMessage(err, 'Gagal memuat detail backup.'),
      color: 'error',
    });
  } finally {
    loadingDetail.value = false;
  }
}

async function handleDownload(backup: BackupHistory) {
  downloadingId.value = backup.id;
  try {
    const { url, file_name } = await requestDownloadUrl(backup.id);
    const link = document.createElement('a');
    link.href = url;
    link.download = file_name;
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.add({
      icon: 'i-lucide-download',
      title: `Mengunduh ${file_name} (arsip tetap terenkripsi).`,
      color: 'info',
    });
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: extractApiErrorMessage(err, 'Gagal membuat tautan unduhan arsip backup.'),
      color: 'error',
    });
  } finally {
    downloadingId.value = null;
  }
}

function confirmDelete(backup: BackupHistory) {
  backupToDelete.value = backup;
  deleteModalOpen.value = true;
}

async function handleDeleteConfirmed() {
  if (!backupToDelete.value) return;
  deleting.value = true;
  try {
    const res = await deleteBackup(backupToDelete.value.id);
    toast.add({
      icon: 'i-lucide-trash-2',
      title: res.message || 'Arsip backup berhasil dihapus.',
      color: 'success',
    });
    deleteModalOpen.value = false;
    backupToDelete.value = null;
    await fetchList();
    emit('deleted');
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: extractApiErrorMessage(err, 'Gagal menghapus arsip backup.'),
      color: 'error',
    });
  } finally {
    deleting.value = false;
  }
}

onMounted(fetchList);
onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
    <!-- Filter Bar -->
    <div class="p-4 border-b border-gray-200 dark:border-white/[0.08] flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-0">
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Cari nama backup, file, atau SHA-256..."
          class="w-full sm:w-72"
        />

        <USelect
          v-model="selectedType"
          :items="typeOptions"
          value-key="value"
          label-key="label"
          class="w-full sm:w-40"
        />

        <USelect
          v-model="selectedStatus"
          :items="statusOptions"
          value-key="value"
          label-key="label"
          class="w-full sm:w-40"
        />
      </div>

      <div class="flex items-center gap-2">
        <UButton
          label="Reset"
          color="neutral"
          variant="ghost"
          size="sm"
          class="cursor-pointer min-h-[38px]"
          @click="resetFilters"
        />
        <UButton
          icon="i-lucide-refresh-cw"
          label="Segarkan"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="loading"
          class="cursor-pointer min-h-[38px]"
          @click="fetchList"
        />
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="p-6 flex flex-wrap items-center justify-between gap-3 text-sm text-red-700 dark:text-red-300 bg-red-50/70 dark:bg-red-950/20"
    >
      <div class="flex items-center gap-2">
        <UIcon name="i-lucide-alert-triangle" class="size-5 shrink-0" />
        <span>{{ error }}</span>
      </div>
      <UButton label="Coba Lagi" size="sm" color="error" variant="subtle" class="cursor-pointer" @click="fetchList" />
    </div>

    <!-- Loading State -->
    <div v-else-if="loading && !paginated" class="p-6 space-y-3">
      <USkeleton v-for="n in 4" :key="n" class="h-12 w-full" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!paginated || paginated.data.length === 0"
      class="p-12 text-center space-y-3"
    >
      <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-database-backup" class="size-6" />
      </div>
      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Belum ada riwayat backup ditemukan</p>
        <p class="text-xs text-slate-600 dark:text-slate-400">
          Buat backup pertama Anda atau ubah filter pencarian untuk melihat arsip tersimpan.
        </p>
      </div>
      <UButton
        label="Jalankan Backup Manual"
        icon="i-lucide-plus"
        color="primary"
        size="sm"
        class="cursor-pointer min-h-[40px]"
        @click="emit('createRequested')"
      />
    </div>

    <!-- Desktop Table + Mobile Card Reflow -->
    <template v-else>
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
          <thead class="bg-slate-50 dark:bg-[#070b14] text-xs font-semibold text-slate-600 dark:text-slate-300 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-4">Snapshot & File</th>
              <th scope="col" class="py-3 px-3">Tipe & Pemicu</th>
              <th scope="col" class="py-3 px-3">Status</th>
              <th scope="col" class="py-3 px-3">Ukuran</th>
              <th scope="col" class="py-3 px-3">Waktu</th>
              <th scope="col" class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
            <tr
              v-for="item in paginated.data"
              :key="item.id"
              class="hover:bg-slate-50/70 dark:hover:bg-white/[0.02] transition-colors"
            >
              <td class="py-3 px-4 max-w-xs">
                <p class="font-semibold text-slate-900 dark:text-white truncate">{{ item.backup_name }}</p>
                <div class="flex items-center gap-1.5 mt-0.5 text-slate-600 dark:text-slate-400">
                  <UIcon
                    :name="item.is_encrypted ? 'i-lucide-lock' : 'i-lucide-file-archive'"
                    class="size-3.5 shrink-0"
                  />
                  <span class="truncate">{{ item.file_name }}</span>
                </div>
              </td>

              <td class="py-3 px-3">
                <div class="flex flex-col items-start gap-1">
                  <UBadge
                    :label="getBackupTypeMeta(item.backup_type).label"
                    :color="getBackupTypeMeta(item.backup_type).color"
                    variant="subtle"
                    size="xs"
                  />
                  <span class="text-[11px] text-slate-600 dark:text-slate-400">
                    {{ getBackupTriggerLabel(item.trigger_type) }}
                  </span>
                </div>
              </td>

              <td class="py-3 px-3">
                <UBadge
                  :label="getBackupStatusMeta(item.status).label"
                  :color="getBackupStatusMeta(item.status).color"
                  variant="subtle"
                  size="xs"
                />
              </td>

              <td class="py-3 px-3 tabular-nums">
                <p class="font-medium text-slate-900 dark:text-white">{{ formatBackupBytes(item.file_size) }}</p>
                <p class="text-[11px] text-slate-600 dark:text-slate-400">
                  {{ formatBackupDuration(item.duration_seconds) }}
                </p>
              </td>

              <td class="py-3 px-3 tabular-nums">
                <p class="text-slate-900 dark:text-white">{{ formatBackupDateTime(item.completed_at || item.created_at) }}</p>
                <p v-if="item.expires_at" class="text-[11px] text-slate-600 dark:text-slate-400">
                  Retensi s.d. {{ formatBackupDateTime(item.expires_at) }}
                </p>
              </td>

              <td class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <UTooltip text="Lihat Detail & Checksum">
                    <UButton
                      icon="i-lucide-eye"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      class="cursor-pointer"
                      aria-label="Lihat detail backup"
                      @click="openDetail(item)"
                    />
                  </UTooltip>

                  <UTooltip v-if="item.status === 'completed'" text="Unduh Arsip Aman">
                    <UButton
                      icon="i-lucide-download"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      :loading="downloadingId === item.id"
                      class="cursor-pointer"
                      aria-label="Unduh arsip backup"
                      @click="handleDownload(item)"
                    />
                  </UTooltip>

                  <UTooltip v-if="item.status === 'completed'" text="Validasi & Restore">
                    <UButton
                      icon="i-lucide-rotate-ccw"
                      size="xs"
                      color="primary"
                      variant="ghost"
                      class="cursor-pointer"
                      aria-label="Restore dari backup ini"
                      @click="emit('restoreRequested', item)"
                    />
                  </UTooltip>

                  <UTooltip text="Hapus Arsip">
                    <UButton
                      icon="i-lucide-trash-2"
                      size="xs"
                      color="error"
                      variant="ghost"
                      class="cursor-pointer"
                      aria-label="Hapus arsip backup"
                      @click="confirmDelete(item)"
                    />
                  </UTooltip>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards -->
      <div class="md:hidden divide-y divide-gray-200 dark:divide-white/[0.08]">
        <div
          v-for="item in paginated.data"
          :key="item.id"
          class="p-4 space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ item.backup_name }}</p>
              <p class="text-xs text-slate-600 dark:text-slate-400 truncate">{{ item.file_name }}</p>
            </div>
            <UBadge
              :label="getBackupStatusMeta(item.status).label"
              :color="getBackupStatusMeta(item.status).color"
              variant="subtle"
              size="xs"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400 tabular-nums">
            <UBadge
              :label="getBackupTypeMeta(item.backup_type).label"
              :color="getBackupTypeMeta(item.backup_type).color"
              variant="subtle"
              size="xs"
            />
            <span>{{ formatBackupBytes(item.file_size) }}</span>
            <span>{{ formatBackupDateTime(item.completed_at || item.created_at) }}</span>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-1">
            <UButton
              label="Detail"
              icon="i-lucide-eye"
              size="sm"
              color="neutral"
              variant="outline"
              class="cursor-pointer min-h-[44px]"
              @click="openDetail(item)"
            />
            <UButton
              v-if="item.status === 'completed'"
              label="Unduh"
              icon="i-lucide-download"
              size="sm"
              color="neutral"
              variant="outline"
              :loading="downloadingId === item.id"
              class="cursor-pointer min-h-[44px]"
              @click="handleDownload(item)"
            />
            <UButton
              v-if="item.status === 'completed'"
              label="Restore"
              icon="i-lucide-rotate-ccw"
              size="sm"
              color="primary"
              variant="subtle"
              class="cursor-pointer min-h-[44px]"
              @click="emit('restoreRequested', item)"
            />
            <UButton
              label="Hapus"
              icon="i-lucide-trash-2"
              size="sm"
              color="error"
              variant="ghost"
              class="cursor-pointer min-h-[44px]"
              @click="confirmDelete(item)"
            />
          </div>
        </div>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="paginated.total > paginated.per_page"
        class="p-4 border-t border-gray-200 dark:border-white/[0.08] flex flex-wrap items-center justify-between gap-3"
      >
        <span class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
          Total {{ paginated.total }} arsip backup
        </span>
        <UPagination
          v-model:page="page"
          :total="paginated.total"
          :items-per-page="paginated.per_page"
          size="sm"
        />
      </div>
    </template>

    <!-- Modal Detail Arsip -->
    <UModal
      v-model:open="detailModalOpen"
      :title="activeBackup?.backup_name || 'Detail Arsip Backup'"
      description="Informasi teknis integritas, metadata PostgreSQL/PostGIS, dan enkripsi."
      :ui="{ content: 'bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08] max-w-2xl' }"
    >
      <template #body>
        <div v-if="loadingDetail" class="space-y-3 py-4">
          <USkeleton class="h-6 w-2/3" />
          <USkeleton class="h-24 w-full" />
        </div>
        <div v-else-if="activeBackup" class="space-y-4 text-xs">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.08]">
            <div>
              <span class="text-slate-600 dark:text-slate-400 block">Nama File</span>
              <span class="font-semibold text-slate-900 dark:text-white break-all">{{ activeBackup.file_name }}</span>
            </div>
            <div>
              <span class="text-slate-600 dark:text-slate-400 block">Ukuran & Durasi</span>
              <span class="font-semibold text-slate-900 dark:text-white tabular-nums">
                {{ formatBackupBytes(activeBackup.file_size) }} ({{ formatBackupDuration(activeBackup.duration_seconds) }})
              </span>
            </div>
            <div>
              <span class="text-slate-600 dark:text-slate-400 block">PostgreSQL & PostGIS</span>
              <span class="font-semibold text-slate-900 dark:text-white tabular-nums">
                PG {{ activeBackup.database_version || '-' }} / PostGIS {{ activeBackup.postgis_version || '-' }}
              </span>
            </div>
            <div>
              <span class="text-slate-600 dark:text-slate-400 block">Enkripsi & Remote</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                {{ activeBackup.is_encrypted ? (activeBackup.encryption_algorithm || 'aes-256-gcm') : 'Tidak terenkripsi' }}
                / {{ activeBackup.remote_disk ? `Remote: ${activeBackup.remote_disk}` : 'Lokal saja' }}
              </span>
            </div>
            <div>
              <span class="text-slate-600 dark:text-slate-400 block">Cakupan Isi</span>
              <span class="font-semibold text-slate-900 dark:text-white tabular-nums">
                {{ activeBackup.metadata?.tables_count ?? 0 }} tabel / {{ activeBackup.metadata?.files_count ?? 0 }} file storage
              </span>
            </div>
            <div>
              <span class="text-slate-600 dark:text-slate-400 block">Dibuat Oleh</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                {{ activeBackup.creator?.name || 'Scheduler / Sistem' }}
              </span>
            </div>
          </div>

          <div v-if="activeBackup.checksum_sha256" class="space-y-1">
            <span class="text-slate-600 dark:text-slate-400 font-medium block">Checksum SHA-256</span>
            <code class="block p-2.5 rounded-lg bg-slate-100 dark:bg-black/40 border border-gray-200 dark:border-white/[0.08] text-slate-900 dark:text-slate-200 break-all font-mono text-[11px]">
              {{ activeBackup.checksum_sha256 }}
            </code>
          </div>

          <UAlert
            v-if="activeBackup.error_message"
            color="error"
            variant="subtle"
            icon="i-lucide-alert-circle"
            title="Catatan Kegagalan"
            :description="activeBackup.error_message"
          />
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end w-full">
          <UButton label="Tutup" color="neutral" variant="ghost" class="cursor-pointer" @click="detailModalOpen = false" />
        </div>
      </template>
    </UModal>

    <!-- Modal Hapus Arsip -->
    <UModal
      v-model:open="deleteModalOpen"
      title="Hapus Arsip Backup"
      description="Arsip backup akan dihapus permanen dari storage lokal dan remote."
      :ui="{ content: 'bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08] max-w-md' }"
    >
      <template #body>
        <p class="text-sm text-slate-700 dark:text-slate-300">
          Yakin ingin menghapus arsip <strong class="text-slate-900 dark:text-white">{{ backupToDelete?.file_name }}</strong>?
        </p>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <UButton label="Batal" color="neutral" variant="ghost" class="cursor-pointer" @click="deleteModalOpen = false" />
          <UButton
            label="Hapus Permanen"
            color="error"
            :loading="deleting"
            class="cursor-pointer"
            @click="handleDeleteConfirmed"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>


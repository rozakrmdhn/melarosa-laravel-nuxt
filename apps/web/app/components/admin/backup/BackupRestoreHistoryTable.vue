<script setup lang="ts">
import type { BackupRestoreHistory, PaginatedPayload } from '~/types/backup';
import {
  extractApiErrorMessage,
  formatBackupDateTime,
  formatBackupDuration,
  getBackupStatusMeta,
  getBackupTypeMeta,
  useBackupManagement,
} from '~/composables/useBackupManagement';

interface Props {
  refreshTrigger: number;
}

const props = defineProps<Props>();

const { listRestores } = useBackupManagement();

const page = ref(1);
const loading = ref(true);
const error = ref<string | null>(null);
const paginated = ref<PaginatedPayload<BackupRestoreHistory> | null>(null);

const reportModalOpen = ref(false);
const activeRestore = ref<BackupRestoreHistory | null>(null);

async function fetchRestores() {
  loading.value = true;
  error.value = null;
  try {
    paginated.value = await listRestores({ page: page.value, per_page: 10 });
  } catch (err: any) {
    error.value = extractApiErrorMessage(err, 'Gagal memuat riwayat eksekusi restore.');
  } finally {
    loading.value = false;
  }
}

watch(page, fetchRestores);
watch(() => props.refreshTrigger, fetchRestores);

function openReport(item: BackupRestoreHistory) {
  activeRestore.value = item;
  reportModalOpen.value = true;
}

onMounted(fetchRestores);
</script>

<template>
  <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
    <div class="p-4 border-b border-gray-200 dark:border-white/[0.08] flex items-center justify-between gap-3">
      <div>
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Riwayat Pemulihan (Restore)</h2>
        <p class="text-xs text-slate-600 dark:text-slate-400">
          Catatan audit validasi checksum, target database, pre-restore safety backup, dan verifikasi PostGIS.
        </p>
      </div>
      <UButton
        icon="i-lucide-refresh-cw"
        label="Segarkan"
        color="neutral"
        variant="outline"
        size="sm"
        :loading="loading"
        class="cursor-pointer min-h-[38px]"
        @click="fetchRestores"
      />
    </div>

    <div
      v-if="error"
      class="p-6 flex flex-wrap items-center justify-between gap-3 text-sm text-red-700 dark:text-red-300 bg-red-50/70 dark:bg-red-950/20"
    >
      <span>{{ error }}</span>
      <UButton label="Coba Lagi" size="sm" color="error" variant="subtle" class="cursor-pointer" @click="fetchRestores" />
    </div>

    <div v-else-if="loading && !paginated" class="p-6 space-y-3">
      <USkeleton v-for="n in 3" :key="n" class="h-12 w-full" />
    </div>

    <div v-else-if="!paginated || paginated.data.length === 0" class="p-12 text-center space-y-2">
      <div class="size-11 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 mx-auto flex items-center justify-center">
        <UIcon name="i-lucide-rotate-ccw" class="size-5" />
      </div>
      <p class="text-sm font-semibold text-slate-900 dark:text-white">Belum ada riwayat restore</p>
      <p class="text-xs text-slate-600 dark:text-slate-400">
        Setiap pengujian atau eksekusi restore akan tercatat lengkap beserta laporan verifikasi spasialnya.
      </p>
    </div>

    <template v-else>
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
          <thead class="bg-slate-50 dark:bg-[#070b14] text-xs font-semibold text-slate-600 dark:text-slate-300 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-4">Sumber Arsip</th>
              <th scope="col" class="py-3 px-3">Scope & Target</th>
              <th scope="col" class="py-3 px-3">Status</th>
              <th scope="col" class="py-3 px-3">Pelaku</th>
              <th scope="col" class="py-3 px-3">Waktu & Durasi</th>
              <th scope="col" class="py-3 px-4 text-right">Laporan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
            <tr
              v-for="row in paginated.data"
              :key="row.id"
              class="hover:bg-slate-50/70 dark:hover:bg-white/[0.02]"
            >
              <td class="py-3 px-4 max-w-xs">
                <p class="font-semibold text-slate-900 dark:text-white truncate">{{ row.source_file_name }}</p>
                <p v-if="row.pre_restore_backup" class="text-[11px] text-slate-600 dark:text-slate-400 truncate">
                  Safety backup: {{ row.pre_restore_backup.file_name }}
                </p>
              </td>
              <td class="py-3 px-3">
                <div class="flex flex-col items-start gap-1">
                  <UBadge
                    :label="getBackupTypeMeta(row.restore_scope).label"
                    :color="getBackupTypeMeta(row.restore_scope).color"
                    variant="subtle"
                    size="xs"
                  />
                  <span class="text-[11px] text-slate-600 dark:text-slate-400">
                    {{ row.target_mode === 'isolated' ? `Terisolasi (${row.target_database || '-'})` : `Aktif (${row.target_database || 'storage'})` }}
                  </span>
                </div>
              </td>
              <td class="py-3 px-3">
                <UBadge
                  :label="getBackupStatusMeta(row.status).label"
                  :color="getBackupStatusMeta(row.status).color"
                  variant="subtle"
                  size="xs"
                />
              </td>
              <td class="py-3 px-3">
                {{ row.executor?.name || '-' }}
              </td>
              <td class="py-3 px-3 tabular-nums">
                <p>{{ formatBackupDateTime(row.completed_at || row.created_at) }}</p>
                <p class="text-[11px] text-slate-600 dark:text-slate-400">
                  {{ formatBackupDuration(row.duration_seconds) }}
                </p>
              </td>
              <td class="py-3 px-4 text-right">
                <UButton
                  label="Detail"
                  icon="i-lucide-file-text"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  class="cursor-pointer"
                  @click="openReport(row)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="md:hidden divide-y divide-gray-200 dark:divide-white/[0.08]">
        <div v-for="row in paginated.data" :key="row.id" class="p-4 space-y-2.5">
          <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ row.source_file_name }}</p>
            <UBadge
              :label="getBackupStatusMeta(row.status).label"
              :color="getBackupStatusMeta(row.status).color"
              variant="subtle"
              size="xs"
            />
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
            {{ getBackupTypeMeta(row.restore_scope).label }} / {{ row.target_database || row.target_mode }} / {{ formatBackupDateTime(row.completed_at || row.created_at) }}
          </p>
          <UButton
            label="Lihat Laporan Verifikasi"
            size="sm"
            color="neutral"
            variant="outline"
            class="cursor-pointer min-h-[42px]"
            @click="openReport(row)"
          />
        </div>
      </div>

      <div
        v-if="paginated.total > paginated.per_page"
        class="p-4 border-t border-gray-200 dark:border-white/[0.08] flex items-center justify-between"
      >
        <span class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
          Total {{ paginated.total }} riwayat restore
        </span>
        <UPagination v-model:page="page" :total="paginated.total" :items-per-page="paginated.per_page" size="sm" />
      </div>
    </template>

    <UModal
      v-model:open="reportModalOpen"
      title="Laporan Verifikasi Restore"
      description="Hasil verifikasi pasca-restore terhadap struktur database, file, dan geometri PostGIS."
      :ui="{ content: 'bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08] max-w-2xl' }"
    >
      <template #body>
        <div v-if="activeRestore" class="space-y-4 text-xs">
          <UAlert
            v-if="activeRestore.error_message"
            color="error"
            variant="subtle"
            icon="i-lucide-alert-circle"
            title="Penyebab Kegagalan"
            :description="activeRestore.error_message"
          />

          <div
            v-if="activeRestore.validation_report?.database_verification"
            class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-slate-50 dark:bg-white/[0.02] space-y-2"
          >
            <p class="font-semibold text-slate-900 dark:text-white">Verifikasi Database & PostGIS</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 tabular-nums">
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">Target DB</span>
                <strong>{{ activeRestore.validation_report.database_verification.target_database }}</strong>
              </div>
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">Jumlah Tabel</span>
                <strong>{{ activeRestore.validation_report.database_verification.tables_count }}</strong>
              </div>
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">PostgreSQL</span>
                <strong>{{ activeRestore.validation_report.database_verification.database_version || '-' }}</strong>
              </div>
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">PostGIS</span>
                <strong>{{ activeRestore.validation_report.database_verification.postgis_version || '-' }}</strong>
              </div>
            </div>

            <div v-if="activeRestore.validation_report.database_verification.geometry_validation?.length > 0" class="pt-2">
              <p class="text-slate-600 dark:text-slate-400 font-medium mb-1">Pemeriksaan Tabel Geometri (ST_IsValid):</p>
              <div class="space-y-1 max-h-40 overflow-y-auto">
                <div
                  v-for="(g, idx) in activeRestore.validation_report.database_verification.geometry_validation"
                  :key="idx"
                  class="flex items-center justify-between py-1 px-2 rounded bg-white dark:bg-[#070b14] border border-gray-200/80 dark:border-white/[0.06] tabular-nums"
                >
                  <span>{{ g.table }} ({{ g.column }})</span>
                  <span>{{ g.total_rows }} baris / {{ g.invalid_geometries }} invalid</span>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="activeRestore.validation_report?.files_verification"
            class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-slate-50 dark:bg-white/[0.02] space-y-1 tabular-nums"
          >
            <p class="font-semibold text-slate-900 dark:text-white">Verifikasi File Storage</p>
            <p class="text-slate-700 dark:text-slate-300">
              Mode: {{ activeRestore.validation_report.files_verification.mode }}
              / File dipulihkan: {{ activeRestore.validation_report.files_verification.restored_files }}
              / Dilewati: {{ activeRestore.validation_report.files_verification.skipped_files }}
            </p>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end w-full">
          <UButton label="Tutup" color="neutral" variant="ghost" class="cursor-pointer" @click="reportModalOpen = false" />
        </div>
      </template>
    </UModal>
  </div>
</template>


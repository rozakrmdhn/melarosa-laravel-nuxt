<script setup lang="ts">
import type { BackupStatisticsData } from '~/types/backup';
import { formatBackupBytes, formatBackupDateTime, getBackupTypeMeta } from '~/composables/useBackupManagement';

interface Props {
  stats: BackupStatisticsData | null;
  loading: boolean;
  error: string | null;
}

defineProps<Props>();

const emit = defineEmits<{
  retry: [];
}>();
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="error"
      class="p-4 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 flex flex-wrap items-center justify-between gap-3"
    >
      <div class="flex items-center gap-2.5 text-sm">
        <UIcon name="i-lucide-alert-triangle" class="size-5 shrink-0" />
        <span>{{ error }}</span>
      </div>
      <UButton
        label="Muat Ulang"
        size="sm"
        color="error"
        variant="subtle"
        class="cursor-pointer min-h-[36px]"
        @click="emit('retry')"
      />
    </div>

    <div v-else-if="loading && !stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="n in 4"
        :key="n"
        class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
      >
        <USkeleton class="h-4 w-28" />
        <USkeleton class="h-7 w-24" />
        <USkeleton class="h-3 w-40" />
      </div>
    </div>

    <template v-else-if="stats">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Arsip & Ukuran -->
        <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Total Arsip Tersimpan</span>
            <div class="size-8 rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-archive" class="size-4" />
            </div>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums">
              {{ stats.summary.completed_backups }}
            </span>
            <span class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
              arsip ({{ formatBackupBytes(stats.summary.total_archive_size_bytes) }})
            </span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
            Gagal: {{ stats.summary.failed_backups }} / Antrean aktif: {{ stats.summary.active_jobs }}
          </p>
        </div>

        <!-- Backup Terakhir -->
        <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Backup Berhasil Terakhir</span>
            <div class="size-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-check-circle-2" class="size-4" />
            </div>
          </div>
          <template v-if="stats.summary.last_backup">
            <div class="flex items-center gap-2 min-w-0">
              <UBadge
                :label="getBackupTypeMeta(stats.summary.last_backup.backup_type).label"
                :color="getBackupTypeMeta(stats.summary.last_backup.backup_type).color"
                variant="subtle"
                size="xs"
              />
              <span class="text-sm font-semibold text-slate-900 dark:text-white tabular-nums truncate">
                {{ formatBackupBytes(stats.summary.last_backup.file_size) }}
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 tabular-nums truncate">
              {{ formatBackupDateTime(stats.summary.last_backup.completed_at) }}
            </p>
          </template>
          <template v-else>
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Belum ada backup</p>
            <p class="text-xs text-slate-600 dark:text-slate-400">Jalankan backup manual atau tunggu jadwal</p>
          </template>
        </div>

        <!-- Jadwal Berikutnya -->
        <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Jadwal Otomatis Terdekat</span>
            <div class="size-8 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-calendar-clock" class="size-4" />
            </div>
          </div>
          <template v-if="stats.summary.next_schedule">
            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
              {{ stats.summary.next_schedule.name }}
            </p>
            <p class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
              {{ formatBackupDateTime(stats.summary.next_schedule.next_run_at) }} WIB
            </p>
          </template>
          <template v-else>
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Tidak ada jadwal aktif</p>
            <p class="text-xs text-slate-600 dark:text-slate-400">Aktifkan pada tab Jadwal Otomatis</p>
          </template>
        </div>

        <!-- Storage Capacity -->
        <div class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Kapasitas Disk Storage</span>
            <div
              class="size-8 rounded-lg flex items-center justify-center shrink-0"
              :class="stats.storage.low_disk_warning
                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                : 'bg-slate-500/10 text-slate-700 dark:text-slate-300'"
            >
              <UIcon name="i-lucide-hard-drive" class="size-4" />
            </div>
          </div>
          <div class="flex items-baseline justify-between gap-2">
            <span class="text-base font-bold text-slate-900 dark:text-white tabular-nums">
              {{ stats.storage.free_bytes !== null ? `${formatBackupBytes(stats.storage.free_bytes)} bebas` : 'Tidak terbaca' }}
            </span>
            <span
              v-if="stats.storage.used_percent !== null"
              class="text-xs font-medium tabular-nums"
              :class="stats.storage.low_disk_warning ? 'text-amber-700 dark:text-amber-300' : 'text-slate-600 dark:text-slate-400'"
            >
              {{ stats.storage.used_percent }}% terpakai
            </span>
          </div>
          <UProgress
            v-if="stats.storage.used_percent !== null"
            :model-value="stats.storage.used_percent"
            :color="stats.storage.low_disk_warning ? 'warning' : 'primary'"
            size="sm"
          />
          <p class="text-xs text-slate-600 dark:text-slate-400">
            Target remote: {{ stats.storage.remote_disk ? stats.storage.remote_disk : 'Belum dikonfigurasi' }}
          </p>
        </div>
      </div>

      <!-- System Readiness Bar -->
      <div class="p-3.5 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
          <div class="flex items-center gap-1.5">
            <UIcon
              :name="stats.health.database_connected ? 'i-lucide-check-circle-2' : 'i-lucide-x-circle'"
              :class="stats.health.database_connected ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
              class="size-4 shrink-0"
            />
            <span class="text-slate-700 dark:text-slate-300">
              DB {{ stats.health.database_driver?.toUpperCase() || '-' }}
              <span v-if="stats.health.database_version" class="tabular-nums">({{ stats.health.database_version }})</span>
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <UIcon
              :name="stats.health.postgis_version ? 'i-lucide-map-pin' : 'i-lucide-minus-circle'"
              :class="stats.health.postgis_version ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'"
              class="size-4 shrink-0"
            />
            <span class="text-slate-700 dark:text-slate-300 tabular-nums">
              PostGIS {{ stats.health.postgis_version || 'Tidak terdeteksi' }}
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <UIcon
              :name="stats.health.pg_dump_available && stats.health.pg_restore_available ? 'i-lucide-terminal' : 'i-lucide-alert-circle'"
              :class="stats.health.pg_dump_available && stats.health.pg_restore_available ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
              class="size-4 shrink-0"
            />
            <span class="text-slate-700 dark:text-slate-300">
              pg_dump / pg_restore: {{ stats.health.pg_dump_available && stats.health.pg_restore_available ? 'Siap' : 'Perlu cek binary' }}
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <UIcon
              :name="stats.health.encryption_key_configured ? 'i-lucide-lock' : 'i-lucide-lock-open'"
              :class="stats.health.encryption_key_configured ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
              class="size-4 shrink-0"
            />
            <span class="text-slate-700 dark:text-slate-300">
              Enkripsi AES-256-GCM: {{ stats.health.encryption_enabled ? (stats.health.encryption_key_configured ? 'Aktif' : 'Kunci belum diset') : 'Nonaktif' }}
            </span>
          </div>
        </div>

        <UBadge
          :label="stats.health.overall_status === 'healthy' ? 'Sistem Sehat' : 'Perlu Perhatian'"
          :color="stats.health.overall_status === 'healthy' ? 'success' : 'warning'"
          variant="subtle"
          size="xs"
        />
      </div>
    </template>
  </div>
</template>


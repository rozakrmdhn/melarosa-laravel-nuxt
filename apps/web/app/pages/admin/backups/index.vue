<script setup lang="ts">
import type { BackupHistory, BackupRestoreHistory, BackupStatisticsData } from '~/types/backup';
import {
  extractApiErrorMessage,
  getBackupStatusMeta,
  useBackupManagement,
} from '~/composables/useBackupManagement';

definePageMeta({
  middleware: ['auth', 'role-admin'],
});

useSeoMeta({
  title: 'Backup & Restore Management',
  description: 'Pengelolaan cadangan database PostgreSQL/PostGIS, file storage terenkripsi, jadwal otomatis, dan pemulihan sistem.',
});

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const { getStatistics, pollJobStatus } = useBackupManagement();

const stats = ref<BackupStatisticsData | null>(null);
const loadingStats = ref(true);
const statsError = ref<string | null>(null);

const activeTab = ref('backups');
const tabItems = [
  { label: 'Riwayat Backup', icon: 'i-lucide-database-backup', value: 'backups' },
  { label: 'Jadwal & Retensi', icon: 'i-lucide-calendar-clock', value: 'schedules' },
  { label: 'Riwayat Restore', icon: 'i-lucide-rotate-ccw', value: 'restores' },
];

const manualModalOpen = ref(false);
const restoreModalOpen = ref(false);
const selectedBackupForRestore = ref<BackupHistory | null>(null);
const refreshTrigger = ref(0);

const trackedJob = ref<{
  id: string;
  kind: 'backup' | 'restore';
  title: string;
  status: string;
  progress: number;
  step: string;
} | null>(null);

let pollInterval: ReturnType<typeof setInterval> | null = null;

async function fetchStats() {
  loadingStats.value = true;
  statsError.value = null;
  try {
    stats.value = await getStatistics();
  } catch (err: any) {
    statsError.value = extractApiErrorMessage(err, 'Gagal memuat ringkasan kesehatan sistem backup.');
  } finally {
    loadingStats.value = false;
  }
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
}

function startPolling(jobId: string, kind: 'backup' | 'restore', title: string, initialStatus: string) {
  stopPolling();

  trackedJob.value = {
    id: jobId,
    kind,
    title,
    status: initialStatus,
    progress: initialStatus === 'completed' ? 100 : 15,
    step: initialStatus === 'completed' ? 'Selesai' : 'Memproses antrean...',
  };

  if (initialStatus === 'completed' || initialStatus === 'failed') {
    refreshTrigger.value++;
    fetchStats();
    return;
  }

  pollInterval = setInterval(async () => {
    try {
      const res = await pollJobStatus(jobId);
      const record = res.data as any;
      const meta = record.metadata || {};
      const status = record.status;

      trackedJob.value = {
        id: jobId,
        kind: res.kind,
        title,
        status,
        progress: typeof meta.progress === 'number' ? meta.progress : (status === 'completed' ? 100 : 50),
        step: meta.current_step || (status === 'completed' ? 'Selesai' : 'Menjalankan proses...'),
      };

      if (status === 'completed' || status === 'failed') {
        stopPolling();
        refreshTrigger.value++;
        await fetchStats();

        toast.add({
          icon: status === 'completed' ? 'i-lucide-check-circle-2' : 'i-lucide-alert-circle',
          title: status === 'completed' ? `${title} selesai.` : `${title} gagal.`,
          color: status === 'completed' ? 'success' : 'error',
        });
      }
    } catch {
      stopPolling();
    }
  }, 2000);
}

function handleManualStarted(backup: BackupHistory) {
  startPolling(backup.id, 'backup', backup.backup_name, backup.status);
}

function handleOpenRestore(backup: BackupHistory | null) {
  selectedBackupForRestore.value = backup;
  restoreModalOpen.value = true;
}

function handleRestoreStarted(restore: BackupRestoreHistory) {
  activeTab.value = 'restores';
  startPolling(restore.id, 'restore', `Restore ${restore.source_file_name}`, restore.status);
}

function handleUploaded() {
  refreshTrigger.value++;
  fetchStats();
}

function handleRefreshAll() {
  refreshTrigger.value++;
  fetchStats();
}

onMounted(() => {
  if (!auth.hasRole('admin')) {
    router.replace('/admin');
    return;
  }
  fetchStats();
});

onBeforeUnmount(stopPolling);
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Backup & Restore Management
          </h1>
          <UBadge label="Super Admin" color="primary" variant="subtle" size="xs" />
        </div>
        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
          Kelola snapshot database PostgreSQL/PostGIS, arsip file storage terenkripsi AES-256-GCM, jadwal otomatis, dan pemulihan aman.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <UButton
          label="Upload & Restore"
          icon="i-lucide-upload"
          color="neutral"
          variant="outline"
          class="cursor-pointer min-h-[44px]"
          @click="handleOpenRestore(null)"
        />
        <UButton
          label="Backup Sekarang"
          icon="i-lucide-database-backup"
          color="primary"
          class="cursor-pointer min-h-[44px]"
          @click="manualModalOpen = true"
        />
      </div>
    </div>

    <!-- Tracked Active Job Banner -->
    <div
      v-if="trackedJob && (trackedJob.status === 'pending' || trackedJob.status === 'processing')"
      class="p-4 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/70 dark:bg-blue-950/30 space-y-2.5"
    >
      <div class="flex items-center justify-between gap-2 text-xs">
        <div class="flex items-center gap-2 font-semibold text-blue-900 dark:text-blue-200">
          <UIcon name="i-lucide-loader-2" class="size-4 animate-spin shrink-0" />
          <span>{{ trackedJob.title }}</span>
        </div>
        <UBadge
          :label="getBackupStatusMeta(trackedJob.status as any).label"
          :color="getBackupStatusMeta(trackedJob.status as any).color"
          variant="subtle"
          size="xs"
        />
      </div>
      <UProgress :model-value="trackedJob.progress" color="info" size="sm" />
      <p class="text-xs text-blue-800 dark:text-blue-300 tabular-nums">
        {{ trackedJob.step }} ({{ trackedJob.progress }}%)
      </p>
    </div>

    <!-- Dashboard KPIs & Health -->
    <AdminBackupDashboardCards
      :stats="stats"
      :loading="loadingStats"
      :error="statsError"
      @retry="fetchStats"
    />

    <!-- Navigation Tabs -->
    <UTabs
      v-model="activeTab"
      :items="tabItems"
      variant="link"
      class="w-full"
    />

    <div v-if="activeTab === 'backups'">
      <AdminBackupHistoryTable
        :refresh-trigger="refreshTrigger"
        @restore-requested="handleOpenRestore($event)"
        @create-requested="manualModalOpen = true"
        @deleted="handleRefreshAll"
      />
    </div>

    <div v-else-if="activeTab === 'schedules'">
      <AdminBackupSchedulePanel
        :remote-disk-configured="Boolean(stats?.storage?.remote_disk)"
        @updated="fetchStats"
      />
    </div>

    <div v-else-if="activeTab === 'restores'">
      <AdminBackupRestoreHistoryTable :refresh-trigger="refreshTrigger" />
    </div>

    <!-- Modals -->
    <AdminBackupManualModal
      v-model:open="manualModalOpen"
      :remote-disk-configured="Boolean(stats?.storage?.remote_disk)"
      :has-active-job="Boolean((stats?.summary?.active_jobs ?? 0) > 0 || (trackedJob && (trackedJob.status === 'pending' || trackedJob.status === 'processing')))"
      @started="handleManualStarted"
    />

    <AdminBackupRestoreModal
      v-model:open="restoreModalOpen"
      :initial-backup="selectedBackupForRestore"
      @uploaded="handleUploaded"
      @restore-started="handleRestoreStarted"
    />
  </div>
</template>


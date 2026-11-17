import dayjs from 'dayjs';
import type {
  BackupFrequency,
  BackupHistory,
  BackupRestoreHistory,
  BackupSchedule,
  BackupStatisticsData,
  BackupStatus,
  BackupTriggerType,
  BackupType,
  BackupValidationReport,
  PaginatedPayload,
  RestoreStatus,
} from '~/types/backup';

export function formatBackupBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || Number.isNaN(bytes)) {
    return '-';
  }
  if (bytes === 0) {
    return '0 B';
  }
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
  const value = bytes / Math.pow(1024, i);

  return `${value.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
}

export function formatBackupDuration(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) {
    return '-';
  }
  if (seconds < 60) {
    return `${seconds} dtk`;
  }
  const mins = Math.floor(seconds / 60);
  const rem = seconds % 60;

  return rem > 0 ? `${mins} mnt ${rem} dtk` : `${mins} mnt`;
}

export function formatBackupDateTime(isoString: string | null | undefined): string {
  if (!isoString) {
    return '-';
  }
  const parsed = dayjs(isoString);

  return parsed.isValid() ? parsed.format('DD/MM/YYYY HH:mm:ss') : '-';
}

export function getBackupTypeMeta(type: BackupType): { label: string; icon: string; color: 'primary' | 'info' | 'secondary' } {
  switch (type) {
    case 'database':
      return { label: 'Database', icon: 'i-lucide-database', color: 'info' };
    case 'file':
      return { label: 'File Storage', icon: 'i-lucide-folder-archive', color: 'secondary' };
    case 'full':
      return { label: 'Full System', icon: 'i-lucide-hard-drive', color: 'primary' };
  }
}

export function getBackupTriggerLabel(trigger: BackupTriggerType): string {
  switch (trigger) {
    case 'manual':
      return 'Manual';
    case 'scheduled':
      return 'Terjadwal';
    case 'pre_restore':
      return 'Pre-Restore';
    case 'uploaded':
      return 'Upload';
  }
}

export function getBackupStatusMeta(status: BackupStatus | RestoreStatus): {
  label: string;
  color: 'success' | 'warning' | 'error' | 'info' | 'neutral';
  icon: string;
} {
  switch (status) {
    case 'completed':
      return { label: 'Selesai', color: 'success', icon: 'i-lucide-check-circle-2' };
    case 'processing':
      return { label: 'Diproses', color: 'info', icon: 'i-lucide-loader-2' };
    case 'pending':
      return { label: 'Menunggu', color: 'warning', icon: 'i-lucide-clock' };
    case 'failed':
      return { label: 'Gagal', color: 'error', icon: 'i-lucide-x-circle' };
    case 'rolled_back':
      return { label: 'Rollback', color: 'neutral', icon: 'i-lucide-rotate-ccw' };
  }
}

export function getFrequencyLabel(freq: BackupFrequency): string {
  switch (freq) {
    case 'hourly':
      return 'Per Jam';
    case 'daily':
      return 'Harian';
    case 'weekly':
      return 'Mingguan';
    case 'monthly':
      return 'Bulanan';
    case 'cron':
      return 'Cron Kustom';
  }
}

export function extractApiErrorMessage(err: any, fallback = 'Terjadi kesalahan sistem.'): string {
  const data = err?.data || err?.response?._data;
  if (data?.errors && typeof data.errors === 'object') {
    const firstKey = Object.keys(data.errors)[0];
    if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
      return String(data.errors[firstKey][0]);
    }
  }
  if (typeof data?.message === 'string' && data.message.trim() !== '') {
    return data.message;
  }

  return fallback;
}

export function useBackupManagement() {
  async function getStatistics(): Promise<BackupStatisticsData> {
    const res = await $http<{ ok: boolean; data: BackupStatisticsData }>('admin/backups/statistics');

    return res.data;
  }

  async function listBackups(params: Record<string, any>): Promise<PaginatedPayload<BackupHistory>> {
    const res = await $http<{ ok: boolean; data: PaginatedPayload<BackupHistory> }>('admin/backups', {
      query: params,
    });

    return res.data;
  }

  async function getBackupDetail(id: string): Promise<BackupHistory> {
    const res = await $http<{ ok: boolean; data: BackupHistory }>(`admin/backups/${id}`);

    return res.data;
  }

  async function createManualBackup(payload: {
    backup_type: BackupType;
    backup_name?: string;
    encrypt: boolean;
    retention_days?: number | null;
    replicate_to_remote: boolean;
    notes?: string;
  }): Promise<{ message: string; data: BackupHistory }> {
    return await $http<{ ok: boolean; message: string; data: BackupHistory }>('admin/backups', {
      method: 'POST',
      body: payload,
    });
  }

  async function requestDownloadUrl(id: string): Promise<{ url: string; expires_in_minutes: number; file_name: string }> {
    const res = await $http<{
      ok: boolean;
      data: { url: string; expires_in_minutes: number; file_name: string };
    }>(`admin/backups/${id}/download-url`);

    return res.data;
  }

  async function deleteBackup(id: string): Promise<{ message: string }> {
    return await $http<{ ok: boolean; message: string }>(`admin/backups/${id}`, {
      method: 'DELETE',
    });
  }

  async function listSchedules(): Promise<BackupSchedule[]> {
    const res = await $http<{ ok: boolean; data: BackupSchedule[] }>('admin/backups/schedules');

    return res.data;
  }

  async function saveSchedules(schedules: BackupSchedule[]): Promise<{ message: string; data: BackupSchedule[] }> {
    return await $http<{ ok: boolean; message: string; data: BackupSchedule[] }>('admin/backups/schedules', {
      method: 'PUT',
      body: { schedules },
    });
  }

  async function listRestores(params: Record<string, any> = {}): Promise<PaginatedPayload<BackupRestoreHistory>> {
    const res = await $http<{ ok: boolean; data: PaginatedPayload<BackupRestoreHistory> }>('admin/backups/restores', {
      query: params,
    });

    return res.data;
  }

  async function validateBackup(backupId: string): Promise<BackupValidationReport> {
    const res = await $http<{ ok: boolean; data: BackupValidationReport }>('admin/backups/validate', {
      method: 'POST',
      body: { backup_id: backupId },
    });

    return res.data;
  }

  async function executeRestore(payload: {
    backup_id: string;
    restore_scope: BackupType;
    target_mode: 'isolated' | 'active';
    target_database?: string | null;
    create_safety_backup: boolean;
    confirm_understood: boolean;
    confirmation_phrase: string;
  }): Promise<{ message: string; data: BackupRestoreHistory }> {
    return await $http<{ ok: boolean; message: string; data: BackupRestoreHistory }>('admin/backups/restore', {
      method: 'POST',
      body: payload,
    });
  }

  async function uploadExternalBackup(file: File, notes?: string): Promise<{ message: string; data: BackupHistory }> {
    const formData = new FormData();
    formData.append('backup_file', file);
    if (notes && notes.trim() !== '') {
      formData.append('notes', notes.trim());
    }

    return await $http<{ ok: boolean; message: string; data: BackupHistory }>('admin/backups/upload', {
      method: 'POST',
      body: formData,
    });
  }

  async function pollJobStatus(jobId: string): Promise<{
    kind: 'backup' | 'restore';
    data: BackupHistory | BackupRestoreHistory;
  }> {
    const res = await $http<{
      ok: boolean;
      kind: 'backup' | 'restore';
      data: BackupHistory | BackupRestoreHistory;
    }>(`admin/backups/jobs/${jobId}`);

    return { kind: res.kind, data: res.data };
  }

  return {
    getStatistics,
    listBackups,
    getBackupDetail,
    createManualBackup,
    requestDownloadUrl,
    deleteBackup,
    listSchedules,
    saveSchedules,
    listRestores,
    validateBackup,
    executeRestore,
    uploadExternalBackup,
    pollJobStatus,
  };
}


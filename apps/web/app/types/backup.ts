export type BackupType = 'database' | 'file' | 'full';

export type BackupTriggerType = 'manual' | 'scheduled' | 'pre_restore' | 'uploaded';

export type BackupStatus = 'pending' | 'processing' | 'completed' | 'failed';

export type RestoreStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'rolled_back';

export type BackupFrequency = 'hourly' | 'daily' | 'weekly' | 'monthly' | 'cron';

export interface BackupCreator {
  id: number;
  name: string;
  email: string;
}

export interface BackupRestoreSummary {
  id: string;
  source_file_name: string;
  restore_scope: BackupType;
  target_database: string | null;
  target_mode: 'isolated' | 'active';
  status: RestoreStatus;
  completed_at: string | null;
}

export interface BackupHistory {
  id: string;
  backup_name: string;
  backup_type: BackupType;
  trigger_type: BackupTriggerType;
  status: BackupStatus;
  disk: string;
  remote_disk: string | null;
  remote_path: string | null;
  file_path: string | null;
  file_name: string;
  file_size: number;
  checksum_sha256: string | null;
  is_encrypted: boolean;
  encryption_algorithm: string | null;
  database_name: string | null;
  database_version: string | null;
  postgis_version: string | null;
  includes_files: boolean;
  started_at: string | null;
  completed_at: string | null;
  duration_seconds: number | null;
  retention_days: number | null;
  expires_at: string | null;
  error_message: string | null;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
  creator?: BackupCreator | null;
  restore_histories?: BackupRestoreSummary[];
}

export interface BackupSchedule {
  id: string;
  name: string;
  backup_type: BackupType;
  is_active: boolean;
  frequency: BackupFrequency;
  cron_expression: string | null;
  run_time: string;
  day_of_week: number | null;
  day_of_month: number | null;
  timezone: string;
  retention_days: number;
  encryption_enabled: boolean;
  replicate_to_remote: boolean;
  last_run_at: string | null;
  last_status: string | null;
  next_run_at: string | null;
  updated_by?: number | null;
  updater?: { id: number; name: string } | null;
}

export interface BackupRestoreHistory {
  id: string;
  backup_history_id: string | null;
  source_file_name: string;
  restore_scope: BackupType;
  target_database: string | null;
  target_mode: 'isolated' | 'active';
  status: RestoreStatus;
  pre_restore_backup_id: string | null;
  validation_report: Record<string, any> | null;
  started_at: string | null;
  completed_at: string | null;
  duration_seconds: number | null;
  error_message: string | null;
  created_at: string;
  backup_history?: { id: string; backup_name: string; backup_type: BackupType } | null;
  pre_restore_backup?: { id: string; backup_name: string; file_name: string } | null;
  executor?: BackupCreator | null;
}

export interface BackupStatisticsData {
  summary: {
    total_backups: number;
    completed_backups: number;
    failed_backups: number;
    active_jobs: number;
    total_archive_size_bytes: number;
    last_backup: {
      id: string;
      backup_name: string;
      backup_type: BackupType;
      status: BackupStatus;
      file_size: number;
      completed_at: string | null;
      is_encrypted: boolean;
    } | null;
    next_schedule: {
      id: string;
      name: string;
      backup_type: BackupType;
      frequency: BackupFrequency;
      run_time: string;
      timezone: string;
      next_run_at: string | null;
    } | null;
  };
  storage: {
    disk: string;
    root_exists: boolean;
    is_writable: boolean;
    free_bytes: number | null;
    total_bytes: number | null;
    used_bytes: number | null;
    used_percent: number | null;
    low_disk_warning: boolean;
    remote_disk: string | null;
  };
  health: {
    database_connected: boolean;
    database_driver: string | null;
    database_name: string | null;
    database_version: string | null;
    postgis_version: string | null;
    database_error: string | null;
    pg_dump_available: boolean;
    pg_restore_available: boolean;
    encryption_enabled: boolean;
    encryption_key_configured: boolean;
    overdue_schedules_count: number;
    overdue_schedules: Array<{
      id: string;
      name: string;
      backup_type: BackupType;
      next_run_at: string | null;
    }>;
    overall_status: 'healthy' | 'warning';
  };
  recent_restores_count?: number;
  last_restore?: BackupRestoreSummary | null;
}

export interface BackupValidationReport {
  valid: boolean;
  compatible: boolean;
  checksum_verified: boolean;
  actual_checksum_sha256: string;
  decryption_verified: boolean | null;
  internal_checksums_verified: boolean | null;
  archive_format: string | null;
  backup_type: BackupType;
  has_database_dump: boolean;
  has_files_zip: boolean;
  source_pg_version: string | null;
  source_postgis_version: string | null;
  server_pg_version: string | null;
  server_postgis_version: string | null;
  tables_count: number | null;
  files_count: number | null;
  warnings: string[];
  errors: string[];
}

export interface PaginatedPayload<T> {
  current_page: number;
  data: T[];
  last_page: number;
  per_page: number;
  total: number;
}


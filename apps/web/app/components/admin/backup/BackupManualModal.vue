<script setup lang="ts">
import type { BackupHistory, BackupType } from '~/types/backup';
import { extractApiErrorMessage, useBackupManagement } from '~/composables/useBackupManagement';

interface Props {
  open: boolean;
  remoteDiskConfigured: boolean;
  hasActiveJob: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  started: [backup: BackupHistory];
}>();

const toast = useToast();
const { createManualBackup } = useBackupManagement();

const backupType = ref<BackupType>('database');
const backupName = ref('');
const encrypt = ref(true);
const retentionDays = ref<number | undefined>(14);
const replicateToRemote = ref(false);
const notes = ref('');
const confirmed = ref(false);
const submitting = ref(false);
const formError = ref<string | null>(null);

const typeOptions = [
  {
    value: 'database' as BackupType,
    title: 'Database Backup',
    description: 'Dump penuh PostgreSQL & PostGIS (-Fc) termasuk tabel spasial, sequences, indeks, dan constraints.',
    defaultRetention: 14,
    icon: 'i-lucide-database',
  },
  {
    value: 'file' as BackupType,
    title: 'File Storage Backup',
    description: 'Arsip terkompresi dokumen, lampiran BKKD, avatar, dan storage aplikasi (cache/temp dikecualikan).',
    defaultRetention: 7,
    icon: 'i-lucide-folder-archive',
  },
  {
    value: 'full' as BackupType,
    title: 'Full Backup',
    description: 'Paket konsisten gabungan dump database PostgreSQL/PostGIS dan seluruh file storage aplikasi.',
    defaultRetention: 56,
    icon: 'i-lucide-hard-drive',
  },
];

watch(backupType, (newType) => {
  const found = typeOptions.find((t) => t.value === newType);
  if (found) {
    retentionDays.value = found.defaultRetention;
  }
});

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    formError.value = null;
    confirmed.value = false;
  }
});

function closeModal() {
  emit('update:open', false);
}

async function handleSubmit() {
  if (!confirmed.value || props.hasActiveJob) {
    return;
  }

  submitting.value = true;
  formError.value = null;

  try {
    const res = await createManualBackup({
      backup_type: backupType.value,
      backup_name: backupName.value.trim() || undefined,
      encrypt: encrypt.value,
      retention_days: retentionDays.value ?? null,
      replicate_to_remote: props.remoteDiskConfigured ? replicateToRemote.value : false,
      notes: notes.value.trim() || undefined,
    });

    toast.add({
      icon: 'i-lucide-check-circle-2',
      title: res.message || 'Proses backup berhasil dijalankan.',
      color: 'success',
    });

    backupName.value = '';
    notes.value = '';
    confirmed.value = false;
    closeModal();
    emit('started', res.data);
  } catch (err: any) {
    formError.value = extractApiErrorMessage(err, 'Gagal memulai proses backup manual.');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <UModal
    :open="open"
    title="Jalankan Backup Manual"
    description="Pilih tipe backup dan opsi keamanan sebelum menjalankan snapshot sistem."
    :ui="{ content: 'bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08] max-w-xl' }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <div class="space-y-4">
        <UAlert
          v-if="hasActiveJob"
          color="warning"
          variant="subtle"
          icon="i-lucide-clock"
          title="Antrean Backup Sedang Berjalan"
          description="Tunggu hingga proses backup saat ini selesai sebelum memulai backup manual baru."
        />

        <UAlert
          v-if="formError"
          color="error"
          variant="subtle"
          icon="i-lucide-alert-circle"
          :description="formError"
        />

        <!-- Pilihan Tipe Backup -->
        <div class="space-y-2">
          <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 block">
            Tipe Backup
          </label>
          <div class="grid grid-cols-1 gap-2.5">
            <button
              v-for="opt in typeOptions"
              :key="opt.value"
              type="button"
              class="w-full text-left p-3 rounded-lg border transition-colors cursor-pointer min-h-[44px] flex items-start gap-3"
              :class="backupType === opt.value
                ? 'border-primary-500 bg-primary-50/60 dark:bg-primary-950/30'
                : 'border-gray-200 dark:border-white/[0.08] hover:bg-gray-50 dark:hover:bg-white/[0.02]'"
              @click="backupType = opt.value"
            >
              <div
                class="size-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                :class="backupType === opt.value
                  ? 'bg-primary-600 text-white'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
              >
                <UIcon :name="opt.icon" class="size-4" />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ opt.title }}</p>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ opt.description }}</p>
              </div>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="manual-backup-name" class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Nama Snapshot (Opsional)
            </label>
            <UInput
              id="manual-backup-name"
              v-model="backupName"
              placeholder="Contoh: Sebelum migrasi data BKKD"
              class="w-full"
            />
          </div>

          <div>
            <label for="manual-backup-retention" class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Masa Retensi (Hari)
            </label>
            <UInput
              id="manual-backup-retention"
              v-model.number="retentionDays"
              type="number"
              :min="1"
              :max="3650"
              class="w-full tabular-nums"
            />
          </div>
        </div>

        <div>
          <label for="manual-backup-notes" class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
            Catatan Operasional (Opsional)
          </label>
          <UTextarea
            id="manual-backup-notes"
            v-model="notes"
            :rows="2"
            placeholder="Catat alasan atau konteks pembuatan backup..."
            class="w-full"
          />
        </div>

        <div class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-slate-50/70 dark:bg-white/[0.02] space-y-2.5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-xs font-semibold text-slate-900 dark:text-white">Enkripsi Arsip (AES-256-GCM)</p>
              <p class="text-xs text-slate-600 dark:text-slate-400">Direkomendasikan aktif untuk melindungi data sensitif.</p>
            </div>
            <USwitch v-model="encrypt" aria-label="Aktifkan enkripsi arsip AES-256" />
          </div>

          <div class="flex items-center justify-between gap-3 pt-2 border-t border-gray-200/80 dark:border-white/[0.06]">
            <div>
              <p class="text-xs font-semibold text-slate-900 dark:text-white">Salin ke Remote Storage</p>
              <p class="text-xs text-slate-600 dark:text-slate-400">
                {{ remoteDiskConfigured ? 'Salin arsip secara otomatis ke disk remote (S3).' : 'Remote disk belum dikonfigurasi pada BACKUP_REMOTE_DISK.' }}
              </p>
            </div>
            <USwitch
              v-model="replicateToRemote"
              :disabled="!remoteDiskConfigured"
              aria-label="Aktifkan salinan ke remote storage"
            />
          </div>
        </div>

        <div class="pt-1">
          <UCheckbox
            v-model="confirmed"
            label="Saya mengonfirmasi untuk menjalankan proses backup sekarang."
          />
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full">
        <UButton
          label="Batal"
          color="neutral"
          variant="ghost"
          class="cursor-pointer min-h-[40px]"
          @click="closeModal"
        />
        <UButton
          label="Mulai Backup"
          icon="i-lucide-play"
          color="primary"
          :loading="submitting"
          :disabled="!confirmed || hasActiveJob"
          class="cursor-pointer min-h-[40px]"
          @click="handleSubmit"
        />
      </div>
    </template>
  </UModal>
</template>


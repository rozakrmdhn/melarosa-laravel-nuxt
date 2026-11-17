<script setup lang="ts">
import type { BackupHistory, BackupRestoreHistory, BackupType, BackupValidationReport } from '~/types/backup';
import {
  extractApiErrorMessage,
  formatBackupBytes,
  getBackupTypeMeta,
  useBackupManagement,
} from '~/composables/useBackupManagement';

interface Props {
  open: boolean;
  initialBackup: BackupHistory | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  uploaded: [backup: BackupHistory];
  restoreStarted: [restore: BackupRestoreHistory];
}>();

const toast = useToast();
const { validateBackup, executeRestore, uploadExternalBackup } = useBackupManagement();

const sourceTab = ref<'existing' | 'upload'>('existing');
const selectedBackup = ref<BackupHistory | null>(null);

const uploadingFile = ref<File | null>(null);
const uploadNotes = ref('');
const uploading = ref(false);
const uploadError = ref<string | null>(null);

const validating = ref(false);
const validationReport = ref<BackupValidationReport | null>(null);
const validationError = ref<string | null>(null);

const restoreScope = ref<BackupType>('database');
const targetMode = ref<'isolated' | 'active'>('isolated');
const targetDatabase = ref('melarosa_restore_verify');
const createSafetyBackup = ref(true);
const confirmUnderstood = ref(false);
const confirmationPhrase = ref('');
const submittingRestore = ref(false);
const restoreError = ref<string | null>(null);

const EXPECTED_PHRASE = 'KONFIRMASI RESTORE';

const availableScopeItems = computed(() => {
  const type = selectedBackup.value?.backup_type || 'database';
  if (type === 'full') {
    return [
      { label: 'Full System (Database + Files)', value: 'full' },
      { label: 'Database PostgreSQL/PostGIS saja', value: 'database' },
      { label: 'File Storage saja', value: 'file' },
    ];
  }
  if (type === 'file') {
    return [{ label: 'File Storage', value: 'file' }];
  }

  return [{ label: 'Database PostgreSQL/PostGIS', value: 'database' }];
});

const needsTargetDbName = computed(
  () => targetMode.value === 'isolated' && (restoreScope.value === 'database' || restoreScope.value === 'full')
);

const canExecuteRestore = computed(() => {
  return Boolean(
    selectedBackup.value
      && validationReport.value?.valid
      && confirmUnderstood.value
      && confirmationPhrase.value.trim() === EXPECTED_PHRASE
      && (!needsTargetDbName.value || /^[A-Za-z_][A-Za-z0-9_]*$/.test(targetDatabase.value.trim()))
  );
});

watch(
  () => [props.open, props.initialBackup] as const,
  ([isOpen, backup]) => {
    if (isOpen) {
      validationReport.value = null;
      validationError.value = null;
      restoreError.value = null;
      uploadError.value = null;
      confirmUnderstood.value = false;
      confirmationPhrase.value = '';
      targetMode.value = 'isolated';
      targetDatabase.value = 'melarosa_restore_verify';

      if (backup) {
        sourceTab.value = 'existing';
        selectedBackup.value = backup;
        restoreScope.value = backup.backup_type;
      } else {
        sourceTab.value = 'upload';
        selectedBackup.value = null;
      }
    }
  }
);

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement;
  uploadingFile.value = input.files?.[0] ?? null;
  uploadError.value = null;
}

async function handleUpload() {
  if (!uploadingFile.value) {
    uploadError.value = 'Pilih file arsip backup (.zip.enc, .enc, .zip, atau .dump).';
    return;
  }

  uploading.value = true;
  uploadError.value = null;

  try {
    const res = await uploadExternalBackup(uploadingFile.value, uploadNotes.value);
    selectedBackup.value = res.data;
    restoreScope.value = res.data.backup_type;
    sourceTab.value = 'existing';
    uploadingFile.value = null;
    uploadNotes.value = '';

    toast.add({
      icon: 'i-lucide-check-circle-2',
      title: res.message || 'Arsip backup berhasil diunggah dan divalidasi.',
      color: 'success',
    });

    emit('uploaded', res.data);
    await handleValidate();
  } catch (err: any) {
    uploadError.value = extractApiErrorMessage(err, 'Gagal memvalidasi file backup yang diunggah.');
  } finally {
    uploading.value = false;
  }
}

async function handleValidate() {
  if (!selectedBackup.value) return;
  validating.value = true;
  validationError.value = null;
  validationReport.value = null;

  try {
    validationReport.value = await validateBackup(selectedBackup.value.id);
  } catch (err: any) {
    validationError.value = extractApiErrorMessage(err, 'Validasi arsip backup gagal dijalankan.');
  } finally {
    validating.value = false;
  }
}

async function handleRestore() {
  if (!selectedBackup.value || !canExecuteRestore.value) return;

  submittingRestore.value = true;
  restoreError.value = null;

  try {
    const res = await executeRestore({
      backup_id: selectedBackup.value.id,
      restore_scope: restoreScope.value,
      target_mode: targetMode.value,
      target_database: needsTargetDbName.value ? targetDatabase.value.trim() : null,
      create_safety_backup: createSafetyBackup.value,
      confirm_understood: confirmUnderstood.value,
      confirmation_phrase: confirmationPhrase.value.trim(),
    });

    toast.add({
      icon: 'i-lucide-check-circle-2',
      title: res.message || 'Proses restore berhasil dijadwalkan.',
      color: 'success',
    });

    emit('update:open', false);
    emit('restoreStarted', res.data);
  } catch (err: any) {
    restoreError.value = extractApiErrorMessage(err, 'Gagal memulai proses restore.');
  } finally {
    submittingRestore.value = false;
  }
}
</script>

<template>
  <UModal
    :open="open"
    title="Validasi & Restore Backup"
    description="Periksa integritas arsip SHA-256, kompatibilitas PostgreSQL/PostGIS, dan pilih target pemulihan yang aman."
    :ui="{ content: 'bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-white/[0.08] max-w-2xl' }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <div class="space-y-5">
        <!-- Switch Sumber -->
        <div class="flex gap-2 border-b border-gray-200 dark:border-white/[0.08] pb-3">
          <UButton
            label="Gunakan Arsip Terpilih"
            :color="sourceTab === 'existing' ? 'primary' : 'neutral'"
            :variant="sourceTab === 'existing' ? 'subtle' : 'ghost'"
            size="sm"
            :disabled="!selectedBackup"
            class="cursor-pointer min-h-[38px]"
            @click="sourceTab = 'existing'"
          />
          <UButton
            label="Unggah File Backup Eksternal"
            icon="i-lucide-upload"
            :color="sourceTab === 'upload' ? 'primary' : 'neutral'"
            :variant="sourceTab === 'upload' ? 'subtle' : 'ghost'"
            size="sm"
            class="cursor-pointer min-h-[38px]"
            @click="sourceTab = 'upload'"
          />
        </div>

        <!-- Upload Panel -->
        <div v-if="sourceTab === 'upload'" class="space-y-3">
          <UAlert
            v-if="uploadError"
            color="error"
            variant="subtle"
            icon="i-lucide-alert-circle"
            :description="uploadError"
          />

          <div>
            <label for="upload-backup-input" class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-1.5">
              Pilih File Backup (.zip.enc, .enc, .zip, atau .dump)
            </label>
            <input
              id="upload-backup-input"
              type="file"
              accept=".enc,.zip,.dump"
              class="block w-full text-xs text-slate-700 dark:text-slate-300 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 dark:file:bg-primary-950/40 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 border border-gray-200 dark:border-white/[0.08] rounded-lg p-1.5 cursor-pointer"
              @change="onFileChange"
            >
          </div>

          <div>
            <label for="upload-backup-notes" class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Catatan Upload (Opsional)
            </label>
            <UInput
              id="upload-backup-notes"
              v-model="uploadNotes"
              placeholder="Sumber arsip eksternal atau catatan verifikasi"
              class="w-full"
            />
          </div>

          <UButton
            label="Unggah & Verifikasi Arsip"
            icon="i-lucide-shield-check"
            color="primary"
            :loading="uploading"
            :disabled="!uploadingFile"
            class="cursor-pointer min-h-[42px]"
            @click="handleUpload"
          />
        </div>

        <!-- Preflight & Execution Panel -->
        <div v-else-if="selectedBackup" class="space-y-4">
          <div class="p-3 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-slate-50/70 dark:bg-white/[0.02] flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-xs text-slate-600 dark:text-slate-400">Sumber Arsip Terpilih</p>
              <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ selectedBackup.backup_name }}</p>
              <p class="text-xs text-slate-600 dark:text-slate-400 tabular-nums">
                {{ getBackupTypeMeta(selectedBackup.backup_type).label }} / {{ formatBackupBytes(selectedBackup.file_size) }}
              </p>
            </div>
            <UButton
              label="Periksa Integritas"
              icon="i-lucide-shield-check"
              color="primary"
              variant="outline"
              size="sm"
              :loading="validating"
              class="cursor-pointer min-h-[40px]"
              @click="handleValidate"
            />
          </div>

          <UAlert
            v-if="validationError"
            color="error"
            variant="subtle"
            icon="i-lucide-alert-circle"
            :description="validationError"
          />

          <!-- Hasil Preflight -->
          <div
            v-if="validationReport"
            class="p-3.5 rounded-lg border space-y-2.5 text-xs"
            :class="validationReport.valid
              ? 'border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-950/20'
              : 'border-red-200 dark:border-red-900/50 bg-red-50/60 dark:bg-red-950/20'"
          >
            <div class="flex items-center justify-between">
              <span class="font-semibold text-slate-900 dark:text-white">
                Hasil Pemeriksaan Integritas & Kompatibilitas
              </span>
              <UBadge
                :label="validationReport.valid ? 'Siap Direstore' : 'Tidak Valid'"
                :color="validationReport.valid ? 'success' : 'error'"
                variant="subtle"
                size="xs"
              />
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-slate-700 dark:text-slate-300 tabular-nums">
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">SHA-256</span>
                <strong>{{ validationReport.checksum_verified ? 'Cocok' : 'Gagal' }}</strong>
              </div>
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">Dekripsi</span>
                <strong>{{ validationReport.decryption_verified === null ? 'Non-enkripsi' : (validationReport.decryption_verified ? 'Valid' : 'Gagal') }}</strong>
              </div>
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">Versi PG Sumber</span>
                <strong>{{ validationReport.source_pg_version || '-' }}</strong>
              </div>
              <div>
                <span class="text-slate-600 dark:text-slate-400 block">PostGIS</span>
                <strong>{{ validationReport.source_postgis_version || '-' }}</strong>
              </div>
            </div>

            <ul v-if="validationReport.errors.length > 0" class="list-disc list-inside text-red-700 dark:text-red-300 space-y-0.5">
              <li v-for="(errMsg, idx) in validationReport.errors" :key="idx">{{ errMsg }}</li>
            </ul>
            <ul v-if="validationReport.warnings.length > 0" class="list-disc list-inside text-amber-700 dark:text-amber-300 space-y-0.5">
              <li v-for="(warnMsg, idx) in validationReport.warnings" :key="idx">{{ warnMsg }}</li>
            </ul>
          </div>

          <!-- Pengaturan Restore (tampil setelah validasi lulus) -->
          <div v-if="validationReport?.valid" class="space-y-4 pt-2 border-t border-gray-200 dark:border-white/[0.08]">
            <UAlert
              v-if="restoreError"
              color="error"
              variant="subtle"
              icon="i-lucide-alert-circle"
              :description="restoreError"
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                  Cakupan Restore
                </label>
                <USelect
                  v-model="restoreScope"
                  :items="availableScopeItems"
                  value-key="value"
                  label-key="label"
                  class="w-full"
                />
              </div>

              <div>
                <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                  Target Pemulihan
                </label>
                <USelect
                  v-model="targetMode"
                  :items="[
                    { label: 'Database Terisolasi / Verifikasi Aman (Disarankan)', value: 'isolated' },
                    { label: 'Target Aktif Aplikasi (Hanya Maintenance)', value: 'active' },
                  ]"
                  value-key="value"
                  label-key="label"
                  class="w-full"
                />
              </div>
            </div>

            <div v-if="needsTargetDbName">
              <label for="restore-target-db" class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                Nama Database Terisolasi Tujuan
              </label>
              <UInput
                id="restore-target-db"
                v-model="targetDatabase"
                placeholder="melarosa_restore_verify"
                class="w-full"
              />
              <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1">
                Database baru akan dibuat otomatis untuk uji restore tanpa mengubah database utama yang sedang berjalan.
              </p>
            </div>

            <UAlert
              v-if="targetMode === 'active'"
              color="warning"
              variant="subtle"
              icon="i-lucide-alert-triangle"
              title="Peringatan Risiko Tinggi"
              description="Restore ke target aktif hanya diizinkan saat BACKUP_RESTORE_ALLOW_OVERWRITE_ACTIVE_DB=true dan akan menimpa data saat ini."
            />

            <UCheckbox
              v-if="targetMode === 'active'"
              v-model="createSafetyBackup"
              label="Buat backup pengaman otomatis (pre-restore safety backup) sebelum restore dieksekusi."
            />

            <div class="p-3 rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/60 dark:bg-amber-950/20 space-y-3">
              <UCheckbox
                v-model="confirmUnderstood"
                label="Saya memahami dampak restore terhadap data dan telah memverifikasi hasil preflight di atas."
              />

              <div>
                <label for="restore-confirm-phrase" class="text-xs font-medium text-slate-800 dark:text-slate-200 block mb-1">
                  Ketik <strong>{{ EXPECTED_PHRASE }}</strong> untuk mengonfirmasi:
                </label>
                <UInput
                  id="restore-confirm-phrase"
                  v-model="confirmationPhrase"
                  :placeholder="EXPECTED_PHRASE"
                  class="w-full"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <UButton
          label="Tutup"
          color="neutral"
          variant="ghost"
          class="cursor-pointer min-h-[40px]"
          @click="emit('update:open', false)"
        />
        <UButton
          v-if="sourceTab === 'existing' && validationReport?.valid"
          label="Eksekusi Restore"
          icon="i-lucide-rotate-ccw"
          color="error"
          :loading="submittingRestore"
          :disabled="!canExecuteRestore"
          class="cursor-pointer min-h-[40px]"
          @click="handleRestore"
        />
      </div>
    </template>
  </UModal>
</template>


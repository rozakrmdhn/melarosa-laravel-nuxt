<script setup lang="ts">
import type { BackupFrequency, BackupSchedule } from '~/types/backup';
import {
  extractApiErrorMessage,
  formatBackupDateTime,
  getBackupTypeMeta,
  useBackupManagement,
} from '~/composables/useBackupManagement';

interface Props {
  remoteDiskConfigured: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  updated: [];
}>();

const toast = useToast();
const { listSchedules, saveSchedules } = useBackupManagement();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const drafts = ref<BackupSchedule[]>([]);

const frequencyItems: Array<{ label: string; value: BackupFrequency }> = [
  { label: 'Harian', value: 'daily' },
  { label: 'Mingguan', value: 'weekly' },
  { label: 'Bulanan', value: 'monthly' },
  { label: 'Per Jam', value: 'hourly' },
  { label: 'Cron Kustom', value: 'cron' },
];

const dayOfWeekItems = [
  { label: 'Minggu', value: 0 },
  { label: 'Senin', value: 1 },
  { label: 'Selasa', value: 2 },
  { label: 'Rabu', value: 3 },
  { label: 'Kamis', value: 4 },
  { label: 'Jumat', value: 5 },
  { label: 'Sabtu', value: 6 },
];

async function fetchData() {
  loading.value = true;
  error.value = null;
  try {
    const items = await listSchedules();
    drafts.value = items.map((item) => ({
      ...item,
      day_of_week: item.day_of_week ?? 0,
      day_of_month: item.day_of_month ?? 1,
      cron_expression: item.cron_expression ?? '0 1 * * *',
    }));
  } catch (err: any) {
    error.value = extractApiErrorMessage(err, 'Gagal memuat konfigurasi jadwal backup.');
  } finally {
    loading.value = false;
  }
}

async function handleSave() {
  saving.value = true;
  error.value = null;
  try {
    const payload = drafts.value.map((d) => ({
      ...d,
      day_of_week: d.frequency === 'weekly' ? (d.day_of_week ?? 0) : null,
      day_of_month: d.frequency === 'monthly' ? (d.day_of_month ?? 1) : null,
      cron_expression: d.frequency === 'cron' ? d.cron_expression : null,
    }));

    const res = await saveSchedules(payload);
    drafts.value = res.data.map((item) => ({
      ...item,
      day_of_week: item.day_of_week ?? 0,
      day_of_month: item.day_of_month ?? 1,
      cron_expression: item.cron_expression ?? '0 1 * * *',
    }));

    toast.add({
      icon: 'i-lucide-check-circle-2',
      title: res.message || 'Jadwal backup otomatis berhasil diperbarui.',
      color: 'success',
    });

    emit('updated');
  } catch (err: any) {
    error.value = extractApiErrorMessage(err, 'Gagal menyimpan konfigurasi jadwal.');
  } finally {
    saving.value = false;
  }
}

onMounted(fetchData);
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">
          Penjadwalan & Kebijakan Retensi Otomatis
        </h2>
        <p class="text-xs text-slate-600 dark:text-slate-400">
          Zona waktu standar server Asia/Jakarta (WIB). Backup yang melewati masa retensi dibersihkan otomatis pukul 04:00 WIB.
        </p>
      </div>

      <UButton
        label="Simpan Perubahan Jadwal"
        icon="i-lucide-save"
        color="primary"
        :loading="saving"
        :disabled="loading || drafts.length === 0"
        class="cursor-pointer min-h-[44px]"
        @click="handleSave"
      />
    </div>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-alert-circle"
      :description="error"
    />

    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div
        v-for="n in 3"
        :key="n"
        class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-3"
      >
        <USkeleton class="h-5 w-40" />
        <USkeleton class="h-9 w-full" />
        <USkeleton class="h-9 w-full" />
      </div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div
        v-for="schedule in drafts"
        :key="schedule.id"
        class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] space-y-4 flex flex-col justify-between"
      >
        <div class="space-y-4">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="size-9 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
                <UIcon
                  :name="getBackupTypeMeta(schedule.backup_type).icon"
                  class="size-4 text-primary-600 dark:text-primary-400"
                />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ schedule.name }}</p>
                <UBadge
                  :label="getBackupTypeMeta(schedule.backup_type).label"
                  :color="getBackupTypeMeta(schedule.backup_type).color"
                  variant="subtle"
                  size="xs"
                />
              </div>
            </div>

            <USwitch
              v-model="schedule.is_active"
              :aria-label="`Aktifkan jadwal ${schedule.name}`"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
                Frekuensi
              </label>
              <USelect
                v-model="schedule.frequency"
                :items="frequencyItems"
                value-key="value"
                label-key="label"
                class="w-full"
              />
            </div>

            <div>
              <label class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
                Waktu (WIB)
              </label>
              <UInput
                v-model="schedule.run_time"
                type="time"
                class="w-full tabular-nums"
              />
            </div>
          </div>

          <div v-if="schedule.frequency === 'weekly'">
            <label class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Hari Eksekusi Mingguan
            </label>
            <USelect
              v-model="schedule.day_of_week"
              :items="dayOfWeekItems"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </div>

          <div v-else-if="schedule.frequency === 'monthly'">
            <label class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Tanggal Eksekusi (1-28)
            </label>
            <UInput
              v-model.number="schedule.day_of_month"
              type="number"
              :min="1"
              :max="28"
              class="w-full tabular-nums"
            />
          </div>

          <div v-else-if="schedule.frequency === 'cron'">
            <label class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Ekspresi Cron (5-field)
            </label>
            <UInput
              v-model="schedule.cron_expression"
              placeholder="0 1 * * *"
              class="w-full tabular-nums"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-700 dark:text-slate-300 block mb-1">
              Retensi Arsip (Hari)
            </label>
            <UInput
              v-model.number="schedule.retention_days"
              type="number"
              :min="1"
              :max="3650"
              class="w-full tabular-nums"
            />
          </div>

          <div class="space-y-2 pt-2 border-t border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs text-slate-700 dark:text-slate-300">Enkripsi AES-256-GCM</span>
              <USwitch v-model="schedule.encryption_enabled" size="sm" />
            </div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs text-slate-700 dark:text-slate-300">Replikasi Remote</span>
              <USwitch
                v-model="schedule.replicate_to_remote"
                :disabled="!props.remoteDiskConfigured"
                size="sm"
              />
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-gray-100 dark:border-white/[0.06] text-xs space-y-1 text-slate-600 dark:text-slate-400">
          <div class="flex items-center justify-between">
            <span>Jadwal berikutnya:</span>
            <span class="font-medium text-slate-900 dark:text-white tabular-nums">
              {{ schedule.is_active ? formatBackupDateTime(schedule.next_run_at) : 'Nonaktif' }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span>Eksekusi terakhir:</span>
            <span class="tabular-nums">{{ formatBackupDateTime(schedule.last_run_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


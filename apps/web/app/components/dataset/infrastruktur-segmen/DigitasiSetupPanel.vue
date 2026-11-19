<script setup lang="ts">
import {
  resolveSumberDanaLabel,
  type ActiveJadwalPeriod,
  type JalanPorosRef,
  type PlottingAnggaran,
  type RefSumberDana,
} from '~/types/infrastruktur';

interface DigitasiScheduleInfo {
  configured: boolean;
  isBypass: boolean;
  loading?: boolean;
  openPeriods: ActiveJadwalPeriod[];
  selectedPeriod?: ActiveJadwalPeriod | null;
  isBlocked: boolean;
  blockReason?: string | null;
}

interface Props {
  collapsed?: boolean;
  collapsible?: boolean;
  isMobileDrawer?: boolean;
  budgetMode?: 'pagu' | 'non_pagu';
  plottingItems?: Array<{ label: string; value: string }>;
  selectedPlottingId?: string | null;
  selectedPlottingDetail?: PlottingAnggaran | null;
  plottingLoading?: boolean;
  canCreatePlotting?: boolean;
  sumberDanaList?: RefSumberDana[];
  digitasiMethod?: 'manual' | 'auto';
  refMode?: 'with_ref' | 'without_ref';
  roadItems?: Array<{ label: string; value: string }>;
  selectedRoadId?: string | null;
  selectedRoadDetail?: { data: JalanPorosRef; feature: any } | null;
  roadLoading?: boolean;
  zoomLoading?: boolean;
  roadError?: string | null;
  canConfirm?: boolean;
  autoScope?: 'partial' | 'full';
  autoStartMeter?: number;
  autoEndMeter?: number;
  autoRoadTotalMeters?: number;
  autoPreviewLength?: number;
  autoPreviewVertexCount?: number;
  autoGeometryError?: string | null;
  isPickingAutoPoints?: boolean;
  autoPickStep?: 'start' | 'end';
  scheduleInfo?: DigitasiScheduleInfo | null;
  showClosedPlottings?: boolean;
  hiddenClosedPlottingCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  collapsible: true,
  isMobileDrawer: false,
  budgetMode: 'pagu',
  plottingItems: () => [],
  selectedPlottingId: null,
  selectedPlottingDetail: null,
  plottingLoading: false,
  canCreatePlotting: false,
  sumberDanaList: () => [],
  digitasiMethod: 'manual',
  refMode: 'with_ref',
  roadItems: () => [],
  selectedRoadId: null,
  selectedRoadDetail: null,
  roadLoading: false,
  zoomLoading: false,
  roadError: null,
  canConfirm: false,
  autoScope: 'partial',
  autoStartMeter: 0,
  autoEndMeter: 0,
  autoRoadTotalMeters: 0,
  autoPreviewLength: 0,
  autoPreviewVertexCount: 0,
  autoGeometryError: null,
  isPickingAutoPoints: false,
  autoPickStep: 'start',
  scheduleInfo: null,
  showClosedPlottings: false,
  hiddenClosedPlottingCount: 0,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'update:budgetMode', val: 'pagu' | 'non_pagu'): void;
  (e: 'update:showClosedPlottings', val: boolean): void;
  (e: 'plottingChange', val: string | null): void;
  (e: 'openCreatePlotting'): void;
  (e: 'update:digitasiMethod', val: 'manual' | 'auto'): void;
  (e: 'switchRefMode', val: 'with_ref' | 'without_ref'): void;
  (e: 'roadChange', val: string | null): void;
  (e: 'reloadRoads'): void;
  (e: 'refocusRoad'): void;
  (e: 'update:autoScope', val: 'partial' | 'full'): void;
  (e: 'update:autoStartMeter', val: number): void;
  (e: 'update:autoEndMeter', val: number): void;
  (e: 'togglePickAutoPoints'): void;
  (e: 'swapAutoDirection'): void;
  (e: 'resetAutoRange'): void;
  (e: 'editAutoOnMap'): void;
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();

function formatSelectedRoadLabel(road: JalanPorosRef): string {
  const kode = road.kode_ruas != null && String(road.kode_ruas).trim() !== ''
    ? `[#${road.kode_ruas}] `
    : '';
  const nama = road.nama_ruas || 'Ruas Tanpa Nama';
  const desa = road.desa ? ` · Ds. ${road.desa}` : '';
  return `${kode}${nama}${desa}`;
}

function handleStartMeterInput(event: Event) {
  const raw = Number((event.target as HTMLInputElement).value);
  if (Number.isFinite(raw)) {
    emit('update:autoStartMeter', raw);
  }
}

function handleEndMeterInput(event: Event) {
  const raw = Number((event.target as HTMLInputElement).value);
  if (Number.isFinite(raw)) {
    emit('update:autoEndMeter', raw);
  }
}

function handleStartRangeInput(event: Event) {
  const raw = Number((event.target as HTMLInputElement).value);
  if (Number.isFinite(raw)) {
    emit('update:autoStartMeter', Math.round(raw * 10) / 10);
  }
}

function handleEndRangeInput(event: Event) {
  const raw = Number((event.target as HTMLInputElement).value);
  if (Number.isFinite(raw)) {
    emit('update:autoEndMeter', Math.round(raw * 10) / 10);
  }
}

const safeTotalMeters = computed(() => Math.max(0, Number(props.autoRoadTotalMeters) || 0));

const clampedStartMeter = computed(() =>
  Math.max(0, Math.min(safeTotalMeters.value, Number(props.autoStartMeter) || 0)),
);

const clampedEndMeter = computed(() =>
  Math.max(0, Math.min(safeTotalMeters.value, Number(props.autoEndMeter) || 0)),
);

const staMinPercent = computed(() => {
  if (safeTotalMeters.value <= 0) return 0;
  return (Math.min(clampedStartMeter.value, clampedEndMeter.value) / safeTotalMeters.value) * 100;
});

const staWidthPercent = computed(() => {
  if (safeTotalMeters.value <= 0) return 0;
  const span = Math.abs(clampedEndMeter.value - clampedStartMeter.value);
  return Math.max(0, Math.min(100, (span / safeTotalMeters.value) * 100));
});

const staStartPinPercent = computed(() => {
  if (safeTotalMeters.value <= 0) return 0;
  return (clampedStartMeter.value / safeTotalMeters.value) * 100;
});

const staEndPinPercent = computed(() => {
  if (safeTotalMeters.value <= 0) return 0;
  return (clampedEndMeter.value / safeTotalMeters.value) * 100;
});

const isReversedDirection = computed(
  () => clampedStartMeter.value > clampedEndMeter.value,
);

const isStep2Ready = computed(() =>
  props.refMode === 'without_ref' || Boolean(props.selectedRoadDetail),
);

const isStep1Ready = computed(() =>
  props.budgetMode === 'non_pagu' || Boolean(props.selectedPlottingDetail),
);

const plottingTargetMeters = computed(() =>
  Math.max(0, Number(props.selectedPlottingDetail?.target_panjang_m ?? 0)),
);

const plottingUsedMeters = computed(() =>
  Math.max(0, Number(props.selectedPlottingDetail?.used_panjang_m ?? 0)),
);

const plottingProjectedMeters = computed(() => {
  const addMeters = props.digitasiMethod === 'auto' ? Number(props.autoPreviewLength || 0) : 0;
  return plottingUsedMeters.value + addMeters;
});

const plottingRemainingMeters = computed(() =>
  Math.max(0, plottingTargetMeters.value - plottingUsedMeters.value),
);

const plottingMaxAllowedMeters = computed(() =>
  plottingTargetMeters.value * 1.1,
);

const isQuotaOverMax = computed(() =>
  props.budgetMode === 'pagu'
  && plottingTargetMeters.value > 0
  && plottingProjectedMeters.value > plottingMaxAllowedMeters.value + 0.01,
);

const isQuotaInToleranceWarning = computed(() =>
  props.budgetMode === 'pagu'
  && plottingTargetMeters.value > 0
  && !isQuotaOverMax.value
  && plottingProjectedMeters.value > plottingTargetMeters.value + 0.01,
);

const plottingUsagePercent = computed(() => {
  if (plottingTargetMeters.value <= 0) return 0;
  return Math.min(100, Math.round((plottingProjectedMeters.value / plottingTargetMeters.value) * 100));
});

const footerHintText = computed(() => {
  if (props.canConfirm) return '';
  if (props.budgetMode === 'pagu' && !props.selectedPlottingDetail) {
    return 'Pilih atau daftarkan Pagu Resmi terlebih dahulu pada langkah 1.';
  }
  if (isQuotaOverMax.value) {
    return 'Panjang segmen melebihi batas kuota + toleransi 10%. Kurangi rentang potongan.';
  }
  if (props.zoomLoading) return 'Memuat geometri ruas jalan poros...';
  if (props.refMode === 'with_ref' && !props.selectedRoadDetail) {
    return 'Pilih ruas jalan poros referensi pada langkah 3.';
  }
  if (props.digitasiMethod === 'auto' && props.autoGeometryError) {
    return 'Perbaiki rentang potongan agar minimal 1 meter.';
  }
  return '';
});

function applyDistancePreset(deltaMeters: number) {
  const total = safeTotalMeters.value;
  if (total <= 0) return;
  let start = clampedStartMeter.value;
  let nextEnd = start + deltaMeters;
  if (nextEnd > total && start > 0) {
    start = 0;
    nextEnd = Math.min(total, deltaMeters);
  } else {
    nextEnd = Math.min(total, nextEnd);
  }
  emit('update:autoStartMeter', Math.round(start * 100) / 100);
  emit('update:autoEndMeter', Math.round(nextEnd * 100) / 100);
}

function applyHalfRoadPreset() {
  const total = safeTotalMeters.value;
  if (total <= 0) return;
  emit('update:autoStartMeter', 0);
  emit('update:autoEndMeter', Math.round((total / 2) * 100) / 100);
}
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]',
      isMobileDrawer ? '' : 'select-none'
    ]"
  >
    <!-- Desktop Header: Title & Collapse Controls -->
    <div
      v-if="!isMobileDrawer"
      class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 gap-2 justify-between select-none"
    >
      <Transition name="panel-subtle-fade">
        <div v-if="!collapsed" class="flex items-center gap-2 overflow-hidden min-w-0">
          <UIcon
            :name="digitasiMethod === 'auto' ? 'i-lucide-waypoints' : 'i-lucide-pen-tool'"
            :class="[
              'size-4 shrink-0',
              digitasiMethod === 'auto' ? 'text-sky-600 dark:text-sky-400' : 'text-amber-600 dark:text-amber-400'
            ]"
          />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            Mulai Digitasi
          </span>
          <UBadge
            :label="digitasiMethod === 'auto' ? 'Otomatis' : 'Manual'"
            size="xs"
            :color="digitasiMethod === 'auto' ? 'info' : 'warning'"
            variant="subtle"
            class="text-[9px] px-1.5 py-0 h-4 leading-none shrink-0"
          />
        </div>
      </Transition>

      <div class="flex items-center gap-0.5 shrink-0">
        <UTooltip v-if="!collapsed" text="Batalkan dan tutup panel digitasi">
          <UButton
            icon="i-lucide-x"
            size="xs"
            color="neutral"
            variant="ghost"
            aria-label="Batalkan dan tutup pengaturan digitasi"
            class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer"
            @click="emit('cancel')"
          />
        </UTooltip>

        <UTooltip v-if="collapsible" :text="collapsed ? 'Buka panel digitasi' : 'Lipat panel digitasi'">
          <UButton
            icon="i-lucide-chevron-right"
            size="xs"
            color="neutral"
            variant="ghost"
            :aria-label="collapsed ? 'Buka panel digitasi' : 'Lipat panel digitasi'"
            :class="[
              'shrink-0 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
              collapsed ? 'rotate-180' : 'rotate-0'
            ]"
            @click="emit('update:collapsed', !collapsed)"
          />
        </UTooltip>
      </div>
    </div>

    <Transition name="panel-fade" mode="out-in">
      <!-- Desktop Collapsed Rail -->
      <button
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
        type="button"
        :aria-expanded="false"
        aria-label="Buka panel pengaturan digitasi"
        class="flex-1 flex flex-col items-center py-4 gap-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors w-full"
        @click="emit('update:collapsed', false)"
      >
        <UIcon
          :name="digitasiMethod === 'auto' ? 'i-lucide-waypoints' : 'i-lucide-pen-tool'"
          :class="[
            'size-4 shrink-0',
            digitasiMethod === 'auto' ? 'text-sky-600 dark:text-sky-400' : 'text-amber-600 dark:text-amber-400'
          ]"
        />
        <span
          class="text-[11px] font-medium text-gray-500 dark:text-gray-400 tracking-wide whitespace-nowrap"
          style="writing-mode: vertical-rl;"
        >
          Pengaturan Digitasi
        </span>
      </button>

      <!-- Main Scrollable Content -->
      <div v-else key="body" class="flex-1 flex flex-col min-h-0 overflow-hidden">
        <div
          class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-4 text-xs min-h-0 overscroll-contain"
          style="-webkit-overflow-scrolling: touch; touch-action: pan-y;"
        >
          <!-- Status Jadwal Tahun Anggaran -->
          <div
            v-if="scheduleInfo?.configured"
            :class="[
              'p-2.5 rounded-lg border text-[11px] leading-snug space-y-1',
              scheduleInfo.isBypass
                ? 'bg-sky-50/80 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800/80 text-sky-900 dark:text-sky-100'
                : scheduleInfo.isBlocked
                  ? 'bg-red-50/85 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100'
                  : scheduleInfo.selectedPeriod?.is_desa_override
                    ? 'bg-sky-50/85 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800/80 text-sky-900 dark:text-sky-100'
                    : 'bg-emerald-50/75 dark:bg-emerald-950/25 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100',
            ]"
          >
            <div class="flex items-center justify-between gap-1.5 font-semibold">
              <span class="inline-flex items-center gap-1.5">
                <UIcon
                  :name="scheduleInfo.isBypass ? 'i-lucide-shield-check' : scheduleInfo.isBlocked ? 'i-lucide-lock' : 'i-lucide-calendar-check-2'"
                  class="size-3.5 shrink-0"
                  aria-hidden="true"
                />
                <span v-if="scheduleInfo.isBypass">Bypass Bappeda / Admin Aktif</span>
                <span v-else-if="scheduleInfo.isBlocked">Jadwal Digitasi Terkunci</span>
                <span v-else-if="scheduleInfo.selectedPeriod?.is_desa_override">
                  Dispensasi Khusus Desa (TA {{ scheduleInfo.selectedPeriod.tahun_anggaran }})
                </span>
                <span v-else>
                  Jadwal Aktif: TA {{ scheduleInfo.openPeriods.map((p) => p.tahun_anggaran).join(', ') }}
                </span>
              </span>
            </div>
            <p v-if="scheduleInfo.isBypass" class="text-[10px] opacity-90">
              Admin dan Bappeda tetap dapat menginput atau mengoreksi data di luar jadwal.
            </p>
            <p v-else-if="scheduleInfo.isBlocked" class="text-[10px]">
              {{ scheduleInfo.blockReason || 'Tahun anggaran untuk mode atau wilayah ini sedang ditutup oleh Bappeda.' }}
            </p>
            <p v-else-if="scheduleInfo.selectedPeriod?.reason" class="text-[10px] opacity-90">
              {{ scheduleInfo.selectedPeriod.reason }}
            </p>
          </div>

          <!-- 1. Dasar Anggaran / Pagu Resmi (Wajib sebelum digitasi) -->
          <div class="space-y-2.5">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <span
                  :class="[
                    'size-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 tabular-nums transition-colors',
                    isStep1Ready ? 'bg-emerald-700 text-white' : 'bg-blue-600 text-white'
                  ]"
                >
                  <UIcon v-if="isStep1Ready" name="i-lucide-check" class="size-3" aria-hidden="true" />
                  <span v-else>1</span>
                </span>
                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                  Dasar Pagu / SK
                </p>
              </div>
              <button
                v-if="budgetMode === 'pagu' && canCreatePlotting"
                type="button"
                class="text-[11px] font-semibold text-blue-700 dark:text-blue-300 hover:underline cursor-pointer shrink-0 inline-flex items-center gap-0.5"
                @click="emit('openCreatePlotting')"
              >
                <UIcon name="i-lucide-plus" class="size-3" aria-hidden="true" />
                <span>Buat Pagu</span>
              </button>
            </div>

            <div
              role="radiogroup"
              aria-label="Dasar anggaran segmen"
              class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800"
            >
              <button
                type="button"
                role="radio"
                :aria-checked="budgetMode === 'pagu'"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[38px]"
                :class="budgetMode === 'pagu'
                  ? 'bg-white dark:bg-[#0b0f19] text-emerald-700 dark:text-emerald-300 font-semibold shadow-xs ring-1 ring-emerald-500/40'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('update:budgetMode', 'pagu')"
              >
                <UIcon name="i-lucide-file-check-2" class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate">Pagu Resmi</span>
              </button>

              <button
                type="button"
                role="radio"
                :aria-checked="budgetMode === 'non_pagu'"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[38px]"
                :class="budgetMode === 'non_pagu'
                  ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white font-semibold shadow-xs ring-1 ring-gray-300 dark:ring-gray-700'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('update:budgetMode', 'non_pagu')"
              >
                <UIcon name="i-lucide-archive" class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate">Non-Pagu</span>
              </button>
            </div>

            <template v-if="budgetMode === 'pagu'">
              <UFormField
                label="Pilih Plotting Anggaran (SK BKKD / Sumber Lain)"
                required
                size="sm"
              >
                <USelectMenu
                  :model-value="selectedPlottingId ?? undefined"
                  :items="plottingItems"
                  :loading="plottingLoading"
                  value-key="value"
                  label-key="label"
                  placeholder="Pilih pagu kegiatan ditetapkan..."
                  size="sm"
                  class="w-full"
                  @update:model-value="(val) => emit('plottingChange', (val as string | null) ?? null)"
                />
              </UFormField>

              <!-- Khusus Admin / Bappeda: toggle koreksi plotting tahun jadwal yang ditutup -->
              <label
                v-if="scheduleInfo?.configured && scheduleInfo?.isBypass && (hiddenClosedPlottingCount > 0 || showClosedPlottings)"
                class="flex items-center gap-2 text-[11px] text-gray-700 dark:text-gray-300 cursor-pointer select-none"
              >
                <input
                  type="checkbox"
                  :checked="showClosedPlottings"
                  class="size-3.5 rounded border-gray-300 dark:border-gray-700 accent-sky-600 cursor-pointer"
                  @change="(e) => emit('update:showClosedPlottings', (e.target as HTMLInputElement).checked)"
                >
                <span>
                  Tampilkan tahun jadwal tertutup (Koreksi{{ hiddenClosedPlottingCount > 0 && !showClosedPlottings ? `: +${hiddenClosedPlottingCount}` : '' }})
                </span>
              </label>

              <div
                v-if="!plottingLoading && plottingItems.length === 0"
                class="p-2.5 rounded-lg bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/70 space-y-2 text-[11px] text-amber-900 dark:text-amber-100"
              >
                <p>
                  <template v-if="scheduleInfo?.configured && !scheduleInfo?.isBypass">
                    Belum ada plotting anggaran untuk tahun jadwal aktif pada wilayah kerja ini.
                  </template>
                  <template v-else-if="scheduleInfo?.configured && scheduleInfo?.isBypass && hiddenClosedPlottingCount > 0 && !showClosedPlottings">
                    Tidak ada plotting pada tahun jadwal terbuka ({{ hiddenClosedPlottingCount }} plotting tahun ditutup disembunyikan).
                  </template>
                  <template v-else>
                    Belum ada plotting anggaran ditetapkan pada wilayah kerja ini.
                  </template>
                </p>
                <UButton
                  v-if="canCreatePlotting"
                  icon="i-lucide-plus"
                  label="Daftarkan Pagu Baru"
                  size="xs"
                  color="warning"
                  variant="subtle"
                  class="w-full justify-center cursor-pointer"
                  @click="emit('openCreatePlotting')"
                />
              </div>

              <div
                v-else-if="selectedPlottingDetail"
                class="p-2.5 rounded-lg bg-emerald-50/75 dark:bg-emerald-950/25 border border-emerald-200 dark:border-emerald-800/80 space-y-2 text-[11px]"
              >
                <div class="space-y-0.5">
                  <div class="flex items-center justify-between gap-1.5">
                    <span class="font-semibold text-emerald-950 dark:text-emerald-100 truncate">
                      {{ selectedPlottingDetail.nomor_sk || 'Pagu Resmi Terdaftar' }}
                    </span>
                    <UBadge
                      :label="String(selectedPlottingDetail.tahun_anggaran)"
                      size="xs"
                      color="success"
                      variant="subtle"
                      class="text-[9px] px-1.5 py-0 h-4 tabular-nums shrink-0"
                    />
                  </div>
                  <p class="text-emerald-900 dark:text-emerald-200 font-medium break-words">
                    {{ resolveSumberDanaLabel(sumberDanaList, selectedPlottingDetail.sumber_dana) }}
                  </p>
                  <p class="text-emerald-800/90 dark:text-emerald-300 tabular-nums">
                    Pagu:
                    <strong>
                      {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(selectedPlottingDetail.target_pagu_anggaran || 0)) }}
                    </strong>
                  </p>
                </div>

                <!-- Kuota Realisasi Panjang + Toleransi 10% -->
                <div v-if="plottingTargetMeters > 0" class="space-y-1 pt-1.5 border-t border-emerald-200/70 dark:border-emerald-800/60">
                  <div class="flex items-center justify-between text-[10px] text-emerald-900 dark:text-emerald-200 tabular-nums">
                    <span>
                      Terpakai: {{ plottingProjectedMeters.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} / {{ plottingTargetMeters.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m
                    </span>
                    <span class="font-semibold">
                      Sisa: {{ plottingRemainingMeters.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m
                    </span>
                  </div>
                  <div class="h-1.5 w-full rounded-full bg-emerald-200/80 dark:bg-emerald-950 overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all duration-150"
                      :class="isQuotaOverMax ? 'bg-red-600' : isQuotaInToleranceWarning ? 'bg-amber-600' : 'bg-emerald-600'"
                      :style="{ width: `${plottingUsagePercent}%` }"
                    />
                  </div>
                  <p v-if="isQuotaOverMax" class="text-[10px] font-semibold text-red-700 dark:text-red-300">
                    Melebihi target + batas toleransi 10% (maks {{ plottingMaxAllowedMeters.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m).
                  </p>
                  <p v-else-if="isQuotaInToleranceWarning" class="text-[10px] font-medium text-amber-800 dark:text-amber-300">
                    Melampaui target awal, masih dalam batas toleransi pengukuran +10%.
                  </p>
                </div>
                <div v-else class="space-y-1 pt-1.5 border-t border-emerald-200/70 dark:border-emerald-800/60">
                  <div class="flex items-center justify-between text-[10px] text-emerald-900 dark:text-emerald-200 tabular-nums">
                    <span>Target panjang belum ditetapkan di SK</span>
                    <span class="font-semibold">
                      Terdigitasi: {{ plottingUsedMeters.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m
                    </span>
                  </div>
                  <p class="text-[10px] text-emerald-800/85 dark:text-emerald-300/85">
                    Mengacu ke Pagu Rupiah; panjang fisik dilengkapi saat digitasi oleh OPD / Desa / Kecamatan.
                  </p>
                </div>
              </div>
            </template>

            <div
              v-else
              class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 text-[11px] leading-relaxed"
            >
              Khusus inventarisasi kondisi jalan eksisting lama tanpa SK / dokumen pagu baru (<code class="font-mono">is_non_pagu = true</code>).
            </div>
          </div>

          <!-- 2. Metode Digitasi -->
          <div class="space-y-2 pt-3 border-t border-gray-200/80 dark:border-gray-800">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="size-5 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0 tabular-nums">
                  2
                </span>
                <p class="font-semibold text-gray-900 dark:text-gray-100">
                  Metode Digitasi
                </p>
              </div>
              <span class="text-[10px] font-medium text-gray-600 dark:text-gray-400">
                {{ digitasiMethod === 'auto' ? 'Ikut Jalur Poros' : 'Gambar Bebas' }}
              </span>
            </div>

            <div
              role="radiogroup"
              aria-label="Metode digitasi segmen"
              class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800"
            >
              <button
                type="button"
                role="radio"
                :aria-checked="digitasiMethod === 'manual'"
                aria-label="Digitasi Manual"
                class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                :class="digitasiMethod === 'manual'
                  ? 'bg-white dark:bg-[#0b0f19] text-blue-700 dark:text-blue-300 font-semibold shadow-xs ring-1 ring-blue-500/40 dark:ring-blue-500/50'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('update:digitasiMethod', 'manual')"
              >
                <UIcon name="i-lucide-pen-tool" class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate">Manual</span>
              </button>

              <button
                type="button"
                role="radio"
                :aria-checked="digitasiMethod === 'auto'"
                aria-label="Digitasi Otomatis mengikuti geometri Jalan Poros Desa"
                class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                :class="digitasiMethod === 'auto'
                  ? 'bg-white dark:bg-[#0b0f19] text-sky-700 dark:text-sky-300 font-semibold shadow-xs ring-1 ring-sky-500/40 dark:ring-sky-500/50'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('update:digitasiMethod', 'auto')"
              >
                <UIcon name="i-lucide-waypoints" class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate">Otomatis</span>
              </button>
            </div>

            <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed">
              {{
                digitasiMethod === 'auto'
                  ? 'Membentuk segmen otomatis mengikuti jalur geometri Jalan Poros Desa tanpa perlu menggambar titik satu per satu.'
                  : 'Gambar titik koordinat ruas segmen secara manual di peta dengan dukungan snapping jalan.'
              }}
            </p>
          </div>

          <!-- 2. Referensi Jalan Poros Desa -->
          <div class="space-y-2.5 pt-3 border-t border-gray-200/80 dark:border-gray-800">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <span
                  :class="[
                    'size-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 tabular-nums transition-colors',
                    isStep2Ready
                      ? 'bg-emerald-700 text-white'
                      : 'bg-blue-600 text-white'
                  ]"
                >
                  <UIcon v-if="isStep2Ready" name="i-lucide-check" class="size-3" aria-hidden="true" />
                  <span v-else>3</span>
                </span>
                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                  Referensi Jalan Poros
                </p>
              </div>
              <UBadge
                v-if="digitasiMethod === 'auto'"
                label="Wajib"
                size="xs"
                color="info"
                variant="subtle"
                class="text-[9px] px-1.5 py-0 h-4 shrink-0"
              />
            </div>

            <div
              role="radiogroup"
              aria-label="Mode referensi jalan poros desa"
              class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800"
            >
              <button
                type="button"
                role="radio"
                :aria-checked="refMode === 'with_ref'"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                :class="refMode === 'with_ref'
                  ? 'bg-white dark:bg-[#0b0f19] text-sky-700 dark:text-sky-300 font-semibold shadow-xs ring-1 ring-sky-200 dark:ring-sky-800'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('switchRefMode', 'with_ref')"
              >
                <UIcon name="i-lucide-route" class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate">Jalan Poros</span>
              </button>

              <button
                type="button"
                role="radio"
                :aria-checked="refMode === 'without_ref'"
                :disabled="digitasiMethod === 'auto'"
                :aria-disabled="digitasiMethod === 'auto'"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors min-h-[40px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                :class="[
                  digitasiMethod === 'auto'
                    ? 'text-gray-500 dark:text-gray-500 opacity-55 cursor-not-allowed'
                    : refMode === 'without_ref'
                      ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white font-semibold shadow-xs ring-1 ring-gray-200 dark:ring-gray-700 cursor-pointer'
                      : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer'
                ]"
                @click="digitasiMethod !== 'auto' && emit('switchRefMode', 'without_ref')"
              >
                <UIcon name="i-lucide-git-commit-horizontal" class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate">Tanpa Ref</span>
              </button>
            </div>

            <p
              v-if="digitasiMethod === 'auto'"
              class="text-[11px] text-sky-700 dark:text-sky-300 flex items-center gap-1.5"
            >
              <UIcon name="i-lucide-lock" class="size-3 shrink-0" aria-hidden="true" />
              <span>Tanpa Ref dinonaktifkan pada mode Otomatis.</span>
            </p>

            <template v-if="refMode === 'with_ref'">
              <UFormField
                label="Pilih Ruas Jalan Poros Desa"
                required
                size="sm"
              >
                <USelectMenu
                  :model-value="selectedRoadId ?? undefined"
                  :items="roadItems"
                  :loading="roadLoading || zoomLoading"
                  value-key="value"
                  label-key="label"
                  placeholder="Cari kode atau nama ruas..."
                  size="sm"
                  class="w-full"
                  @update:model-value="(val) => emit('roadChange', (val as string | null) ?? null)"
                />
              </UFormField>

              <div
                v-if="roadError"
                class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/60 space-y-2 text-red-700 dark:text-red-300"
              >
                <p class="text-[11px]">{{ roadError }}</p>
                <UButton
                  label="Muat Ulang"
                  size="xs"
                  color="error"
                  variant="subtle"
                  class="cursor-pointer"
                  @click="emit('reloadRoads')"
                />
              </div>

              <div
                v-else-if="!roadLoading && roadItems.length === 0"
                class="p-2.5 rounded-lg bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-200 text-[11px] leading-relaxed"
              >
                Tidak ditemukan data ruas jalan poros desa untuk filter wilayah aktif. Sesuaikan filter wilayah di topbar{{ digitasiMethod === 'manual' ? ' atau pilih mode Tanpa Ref' : '' }}.
              </div>

              <div
                v-else-if="!selectedRoadDetail && !zoomLoading"
                class="p-2.5 rounded-lg bg-sky-50/70 dark:bg-sky-950/25 border border-sky-200/80 dark:border-sky-800/70 flex items-start gap-2 text-[11px] text-sky-900 dark:text-sky-100 leading-relaxed"
              >
                <UIcon name="i-lucide-mouse-pointer-click" class="size-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" aria-hidden="true" />
                <span>Klik langsung garis ruas biru di peta, atau cari ruas pada kolom di atas.</span>
              </div>

              <div
                v-if="selectedRoadDetail"
                class="p-2.5 rounded-lg bg-sky-50/80 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/80 space-y-2"
              >
                <div class="min-w-0 space-y-1">
                  <div class="flex items-start justify-between gap-1.5">
                    <p class="font-semibold text-sky-900 dark:text-sky-100 break-words leading-snug">
                      {{ formatSelectedRoadLabel(selectedRoadDetail.data) }}
                    </p>
                  </div>
                  <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-sky-800 dark:text-sky-300">
                    <span>Kec. {{ selectedRoadDetail.data.kecamatan || '-' }}</span>
                    <span class="opacity-60">|</span>
                    <span>
                      Panjang DB:
                      <strong class="font-semibold tabular-nums">
                        {{ Number(selectedRoadDetail.data.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m
                      </strong>
                    </span>
                  </div>
                </div>

                <UButton
                  icon="i-lucide-crosshair"
                  label="Fokus ke Ruas di Peta"
                  size="xs"
                  color="info"
                  variant="subtle"
                  class="cursor-pointer w-full justify-center min-h-[34px]"
                  @click="emit('refocusRoad')"
                />
              </div>
            </template>

            <div
              v-else
              class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 text-[11px] leading-relaxed"
            >
              Segmen disimpan sebagai segmen mandiri tanpa induk jalan poros desa (<code class="font-mono">parent_id = null</code>). Cocok untuk gang permukiman baru, drainase, atau jembatan di luar koridor poros.
            </div>
          </div>

          <!-- 3. Pengaturan Potongan Otomatis (Khusus Metode Otomatis) -->
          <div
            v-if="digitasiMethod === 'auto'"
            class="space-y-3 pt-3 border-t border-gray-200/80 dark:border-gray-800"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <span
                  :class="[
                    'size-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 tabular-nums transition-colors',
                    selectedRoadDetail && autoPreviewLength > 0
                      ? 'bg-emerald-700 text-white'
                      : selectedRoadDetail
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                  ]"
                >
                  4
                </span>
                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                  Cakupan Potongan
                </p>
              </div>
              <span
                v-if="selectedRoadDetail"
                class="text-[11px] font-medium text-gray-600 dark:text-gray-300 tabular-nums shrink-0"
              >
                GIS: {{ autoRoadTotalMeters.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} m
              </span>
            </div>

            <div
              v-if="!selectedRoadDetail"
              class="p-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/40 text-[11px] text-gray-600 dark:text-gray-400 text-center leading-relaxed"
            >
              Pilih ruas Jalan Poros pada langkah 3 untuk memilih titik awal/akhir (A-B) atau mengambil seluruh panjang ruas.
            </div>

            <template v-else>
              <div
                role="radiogroup"
                aria-label="Cakupan potongan ruas otomatis"
                class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800"
              >
                <button
                  type="button"
                  role="radio"
                  :aria-checked="autoScope === 'partial'"
                  class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                  :class="autoScope === 'partial'
                    ? 'bg-white dark:bg-[#0b0f19] text-sky-700 dark:text-sky-300 font-semibold shadow-xs ring-1 ring-sky-200 dark:ring-sky-800'
                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                  @click="emit('update:autoScope', 'partial')"
                >
                  <UIcon name="i-lucide-scissors" class="size-3.5 shrink-0" aria-hidden="true" />
                  <span class="truncate">Sebagian (A-B)</span>
                </button>

                <button
                  type="button"
                  role="radio"
                  :aria-checked="autoScope === 'full'"
                  class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                  :class="autoScope === 'full'
                    ? 'bg-white dark:bg-[#0b0f19] text-sky-700 dark:text-sky-300 font-semibold shadow-xs ring-1 ring-sky-200 dark:ring-sky-800'
                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                  @click="emit('update:autoScope', 'full')"
                >
                  <UIcon name="i-lucide-route" class="size-3.5 shrink-0" aria-hidden="true" />
                  <span class="truncate">Seluruh Ruas</span>
                </button>
              </div>

              <!-- Sub-panel: Sebagian Ruas (Titik A-B / STA Meter) -->
              <div v-if="autoScope === 'partial'" class="space-y-2.5">
                <UButton
                  :icon="isPickingAutoPoints ? 'i-lucide-check' : 'i-lucide-map-pin'"
                  :label="isPickingAutoPoints ? 'Selesai Pilih Titik di Peta' : 'Pilih Titik A & B di Peta'"
                  size="xs"
                  :color="isPickingAutoPoints ? 'warning' : 'info'"
                  :variant="isPickingAutoPoints ? 'solid' : 'subtle'"
                  class="cursor-pointer w-full justify-center min-h-[40px]"
                  @click="emit('togglePickAutoPoints')"
                />

                <p
                  v-if="isPickingAutoPoints"
                  class="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/80 text-[11px] text-amber-900 dark:text-amber-100 leading-relaxed"
                >
                  {{
                    autoPickStep === 'start'
                      ? 'Klik ruas Jalan Poros pada peta untuk menentukan Titik Awal (A).'
                      : `Titik A terpilih di STA ${clampedStartMeter.toLocaleString('id-ID')} m. Klik peta untuk Titik Akhir (B).`
                  }}
                </p>

                <!-- Visual STA Span Track -->
                <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800 space-y-1.5">
                  <div class="flex items-center justify-between text-[10px] text-gray-600 dark:text-gray-300">
                    <span class="font-medium">
                      Posisi Potongan ({{ Math.round(staWidthPercent) }}% ruas)
                    </span>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">
                      {{ isReversedDirection ? 'Arah: B <- A (Balik)' : 'Arah: A -> B' }}
                    </span>
                  </div>
                  <div class="relative h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-800 overflow-visible">
                    <div
                      class="absolute top-0 h-full rounded-full bg-amber-500 transition-all duration-150"
                      :style="{ left: `${staMinPercent}%`, width: `${Math.max(2, staWidthPercent)}%` }"
                    />
                    <span
                      class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 size-3 rounded-full bg-emerald-600 ring-2 ring-white dark:ring-gray-950 shadow-xs"
                      :style="{ left: `${staStartPinPercent}%` }"
                      :title="`Titik A: ${clampedStartMeter} m`"
                    />
                    <span
                      class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 size-3 rounded-full bg-amber-600 ring-2 ring-white dark:ring-gray-950 shadow-xs"
                      :style="{ left: `${staEndPinPercent}%` }"
                      :title="`Titik B: ${clampedEndMeter} m`"
                    />
                  </div>
                  <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 tabular-nums">
                    <span>0 m</span>
                    <span>{{ Math.round(safeTotalMeters / 2).toLocaleString('id-ID') }} m</span>
                    <span>{{ Math.round(safeTotalMeters).toLocaleString('id-ID') }} m</span>
                  </div>
                </div>

                <!-- Dari (m) & Sampai (m) Inputs + Sliders -->
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-1">
                    <label
                      for="auto-start-meter"
                      class="flex items-center gap-1 text-[11px] font-medium text-gray-700 dark:text-gray-300"
                    >
                      <span class="size-2 rounded-full bg-emerald-600 shrink-0" aria-hidden="true" />
                      <span>Dari A (m)</span>
                    </label>
                    <input
                      id="auto-start-meter"
                      type="number"
                      step="0.5"
                      min="0"
                      :max="autoRoadTotalMeters"
                      :value="autoStartMeter"
                      class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-2.5 py-1.5 text-xs text-gray-900 dark:text-gray-100 tabular-nums focus:outline-2 focus:outline-blue-600"
                      @input="handleStartMeterInput"
                    >
                    <input
                      type="range"
                      min="0"
                      :max="safeTotalMeters"
                      step="1"
                      :value="clampedStartMeter"
                      aria-label="Geser jarak titik awal A dalam meter"
                      class="w-full accent-emerald-600 cursor-pointer h-1.5"
                      @input="handleStartRangeInput"
                    >
                  </div>

                  <div class="space-y-1">
                    <label
                      for="auto-end-meter"
                      class="flex items-center gap-1 text-[11px] font-medium text-gray-700 dark:text-gray-300"
                    >
                      <span class="size-2 rounded-full bg-amber-600 shrink-0" aria-hidden="true" />
                      <span>Sampai B (m)</span>
                    </label>
                    <input
                      id="auto-end-meter"
                      type="number"
                      step="0.5"
                      min="0"
                      :max="autoRoadTotalMeters"
                      :value="autoEndMeter"
                      class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-2.5 py-1.5 text-xs text-gray-900 dark:text-gray-100 tabular-nums focus:outline-2 focus:outline-blue-600"
                      @input="handleEndMeterInput"
                    >
                    <input
                      type="range"
                      min="0"
                      :max="safeTotalMeters"
                      step="1"
                      :value="clampedEndMeter"
                      aria-label="Geser jarak titik akhir B dalam meter"
                      class="w-full accent-amber-600 cursor-pointer h-1.5"
                      @input="handleEndRangeInput"
                    >
                  </div>
                </div>

                <!-- Preset Panjang Cepat -->
                <div class="flex items-center gap-1 flex-wrap">
                  <span class="text-[10px] text-gray-600 dark:text-gray-400 mr-0.5">Cepat:</span>
                  <button
                    v-for="preset in [50, 100, 250]"
                    :key="preset"
                    type="button"
                    class="px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 text-[10px] font-medium text-gray-700 dark:text-gray-200 tabular-nums cursor-pointer transition-colors"
                    @click="applyDistancePreset(preset)"
                  >
                    +{{ preset }}m
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 text-[10px] font-medium text-gray-700 dark:text-gray-200 tabular-nums cursor-pointer transition-colors"
                    @click="applyHalfRoadPreset"
                  >
                    50% Ruas
                  </button>
                </div>

                <div class="flex items-center gap-1.5">
                  <UButton
                    icon="i-lucide-arrow-left-right"
                    label="Tukar Arah"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    class="cursor-pointer flex-1 justify-center min-h-[34px]"
                    @click="emit('swapAutoDirection')"
                  />
                  <UButton
                    icon="i-lucide-rotate-ccw"
                    label="Reset Penuh"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    class="cursor-pointer flex-1 justify-center min-h-[34px]"
                    @click="emit('resetAutoRange')"
                  />
                </div>
              </div>

              <!-- Live Preview Summary Card -->
              <div
                v-if="autoGeometryError"
                class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/60 text-red-800 dark:text-red-200 text-[11px] leading-relaxed flex items-start gap-2"
              >
                <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0 mt-0.5" aria-hidden="true" />
                <span>{{ autoGeometryError }}</span>
              </div>

              <div
                v-else-if="autoPreviewLength > 0 && autoPreviewVertexCount >= 2"
                class="p-2.5 rounded-lg bg-emerald-50/85 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/80 space-y-2"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-900 dark:text-emerald-100">
                    <UIcon name="i-lucide-check-circle-2" class="size-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" aria-hidden="true" />
                    <span>Pratinjau Siap</span>
                  </span>
                  <span class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-200 tabular-nums">
                    {{ autoPreviewVertexCount }} titik
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-emerald-200/70 dark:border-emerald-800/60 text-[11px]">
                  <div>
                    <span class="block text-[10px] text-emerald-800/85 dark:text-emerald-300">Panjang Segmen</span>
                    <span class="font-bold text-emerald-950 dark:text-emerald-100 tabular-nums">
                      {{ autoPreviewLength.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m
                    </span>
                  </div>
                  <div class="text-right">
                    <span class="block text-[10px] text-emerald-800/85 dark:text-emerald-300">Rentang STA</span>
                    <span class="font-semibold text-emerald-950 dark:text-emerald-100 tabular-nums">
                      {{
                        autoScope === 'full'
                          ? `0 s.d. ${Math.round(safeTotalMeters).toLocaleString('id-ID')} m`
                          : `${Math.round(clampedStartMeter).toLocaleString('id-ID')} - ${Math.round(clampedEndMeter).toLocaleString('id-ID')} m`
                      }}
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Sticky Footer CTA -->
        <div
          class="shrink-0 p-3 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b0f19] space-y-2"
          :class="isMobileDrawer ? 'pb-[max(0.75rem,env(safe-area-inset-bottom))]' : ''"
        >
          <p
            v-if="footerHintText"
            class="text-[11px] text-gray-600 dark:text-gray-400 text-center"
          >
            {{ footerHintText }}
          </p>

          <template v-if="digitasiMethod === 'auto'">
            <UButton
              label="Gunakan Geometri"
              icon="i-lucide-check"
              color="primary"
              variant="solid"
              size="sm"
              class="cursor-pointer w-full justify-center min-h-[40px]"
              :disabled="!canConfirm"
              @click="emit('confirm')"
            />
            <div class="grid grid-cols-2 gap-1.5">
              <UTooltip text="Sesuaikan titik vertex hasil otomatis pada peta sebelum disimpan">
                <UButton
                  icon="i-lucide-sliders-horizontal"
                  label="Sesuaikan di Peta"
                  color="neutral"
                  variant="outline"
                  size="xs"
                  class="cursor-pointer w-full justify-center min-h-[36px]"
                  :disabled="!canConfirm"
                  @click="emit('editAutoOnMap')"
                />
              </UTooltip>
              <UButton
                label="Batal"
                color="neutral"
                variant="ghost"
                size="xs"
                class="cursor-pointer w-full justify-center min-h-[36px]"
                @click="emit('cancel')"
              />
            </div>
          </template>

          <div v-else class="flex items-center gap-2">
            <UButton
              label="Batal"
              color="neutral"
              variant="ghost"
              size="sm"
              class="cursor-pointer min-h-[40px]"
              @click="emit('cancel')"
            />
            <UButton
              label="Mulai Digitasi Manual"
              icon="i-lucide-pen-tool"
              color="primary"
              variant="solid"
              size="sm"
              class="cursor-pointer flex-1 justify-center min-h-[40px]"
              :disabled="!canConfirm"
              @click="emit('confirm')"
            />
          </div>
        </div>
      </div>
    </Transition>
  </component>
</template>

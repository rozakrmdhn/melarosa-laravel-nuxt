<script setup lang="ts">
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import { useAuthStore } from '~/stores/auth';
import { useWilayahStore } from '~/stores/wilayah';
import {
  JALAN_POROS_KONDISI_OPTIONS,
  KONDISI_OPTIONS,
  PERKERASAN_OPTIONS,
  STATUS_ASET_OPTIONS,
  STATUS_JALAN_OPTIONS,
  STATUS_KONDISI_OPTIONS,
  STATUS_VERIFIKASI_OPTIONS,
  SUMBER_DANA_OPTIONS,
  type InfrastrukturTipe,
  type SelectOptionItem,
} from '~/types/infrastruktur';

interface Props {
  layerId?: string;
  layerTitle?: string;
  tipeList?: InfrastrukturTipe[];
  selectedTipe?: string | null;
  selectedTahunPembangunan?: number | string | null;
  selectedTahun?: number | string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  selectedPerkerasan?: string | null;
  selectedStatusJalan?: string | null;
  selectedStatusKondisi?: string | null;
  selectedStatusAset?: string | null;
  selectedSumberDana?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  isKecamatanLocked?: boolean;
  isDesaLocked?: boolean;
  isMobile?: boolean;
  hideHeader?: boolean;
  hideFooterActions?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  layerId: 'infrastruktur-segmen',
  layerTitle: 'Segmen Fisik Infrastruktur',
  tipeList: () => [],
  selectedTipe: null,
  selectedTahunPembangunan: null,
  selectedTahun: null,
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  selectedPerkerasan: null,
  selectedStatusJalan: null,
  selectedStatusKondisi: null,
  selectedStatusAset: null,
  selectedSumberDana: null,
  modelKecamatan: null,
  modelDesa: null,
  isKecamatanLocked: false,
  isDesaLocked: false,
  isMobile: false,
  hideHeader: false,
  hideFooterActions: false,
});

const emit = defineEmits<{
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selected-tipe', val: string | null): void;
  (e: 'update:selectedTahunPembangunan', val: number | string | null): void;
  (e: 'update:selected-tahun-pembangunan', val: number | string | null): void;
  (e: 'update:selectedTahun', val: number | string | null): void;
  (e: 'update:selected-tahun', val: number | string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selected-kondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:selected-status-verifikasi', val: string | null): void;
  (e: 'update:selectedPerkerasan', val: string | null): void;
  (e: 'update:selected-perkerasan', val: string | null): void;
  (e: 'update:selectedStatusJalan', val: string | null): void;
  (e: 'update:selected-status-jalan', val: string | null): void;
  (e: 'update:selectedStatusKondisi', val: string | null): void;
  (e: 'update:selected-status-kondisi', val: string | null): void;
  (e: 'update:selectedStatusAset', val: string | null): void;
  (e: 'update:selected-status-aset', val: string | null): void;
  (e: 'update:selectedSumberDana', val: string | null): void;
  (e: 'update:selected-sumber-dana', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:model-kecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'update:model-desa', val: number | null): void;
  (e: 'apply'): void;
  (e: 'reset'): void;
}>();

const uid = useId();
const auth = useAuthStore();
const wilayahStore = useWilayahStore();
const { isAdmin } = usePermission();

const isUnrestrictedRole = computed(
  () => isAdmin() || auth.hasRole('admin') || auth.hasRole('verifierBappeda'),
);

const effectiveKecamatanLocked = computed(
  () => props.isKecamatanLocked || (!isUnrestrictedRole.value && !!auth.user?.id_kecamatan),
);

const effectiveDesaLocked = computed(
  () => props.isDesaLocked || (!isUnrestrictedRole.value && !!auth.user?.id_desa),
);

function withAllOption(allLabel: string, items: SelectOptionItem[]): SelectOptionItem[] {
  return [{ label: allLabel, value: 'ALL' }, ...items];
}

function extractValue(val: any): string | null {
  if (val === null || val === undefined) return null;
  const resolved = typeof val === 'object' && val !== null ? (val.value ?? val.id ?? null) : val;
  if (!resolved || resolved === 'ALL') return null;
  return String(resolved);
}

function extractNumber(val: any): number | null {
  if (val === null || val === undefined) return null;
  const resolved = typeof val === 'object' && val !== null ? (val.id ?? val.value ?? null) : val;
  if (resolved === null || resolved === undefined || resolved === '' || resolved === 'ALL') return null;
  const num = Number(resolved);
  return isNaN(num) ? null : num;
}

function handleTipeChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedTipe', v);
  emit('update:selected-tipe', v);
}

function handleTahunPembangunanChange(val: any) {
  const v = extractValue(val);
  const resolved = v && v !== 'ALL' ? Number(v) : null;
  emit('update:selectedTahunPembangunan', resolved);
  emit('update:selected-tahun-pembangunan', resolved);
  emit('update:selectedTahun', resolved);
  emit('update:selected-tahun', resolved);
}

function handleKondisiChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedKondisi', v);
  emit('update:selected-kondisi', v);
}

function handleStatusVerifikasiChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedStatusVerifikasi', v);
  emit('update:selected-status-verifikasi', v);
}

function handlePerkerasanChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedPerkerasan', v);
  emit('update:selected-perkerasan', v);
}

function handleStatusJalanChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedStatusJalan', v);
  emit('update:selected-status-jalan', v);
}

function handleStatusKondisiChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedStatusKondisi', v);
  emit('update:selected-status-kondisi', v);
}

function handleStatusAsetChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedStatusAset', v);
  emit('update:selected-status-aset', v);
}

function handleSumberDanaChange(val: any) {
  const v = extractValue(val);
  emit('update:selectedSumberDana', v);
  emit('update:selected-sumber-dana', v);
}

function handleKecamatanChange(val: any) {
  const n = extractNumber(val);
  emit('update:modelKecamatan', n);
  emit('update:model-kecamatan', n);
}

function handleDesaChange(val: any) {
  const n = extractNumber(val);
  emit('update:modelDesa', n);
  emit('update:model-desa', n);
}

const tipeSelectItems = computed<SelectOptionItem[]>(() => [
  { label: 'Semua Tipe Infrastruktur', value: 'ALL' },
  ...props.tipeList.map((t) => ({
    label: t.nama,
    value: t.kode,
  })),
]);

const tahunPembangunanSelectItems = computed(() => {
  const currentYear = new Date().getFullYear();
  const items: { label: string; value: string | number }[] = [
    { label: 'Semua Tahun', value: 'ALL' },
  ];
  for (let y = currentYear + 1; y >= 2015; y--) {
    items.push({
      label: `Tahun ${y}`,
      value: y,
    });
  }
  return items;
});

const kondisiOptions = withAllOption('Semua Kondisi', KONDISI_OPTIONS);
const jalanKondisiOptions = withAllOption('Semua Kondisi', JALAN_POROS_KONDISI_OPTIONS);
const perkerasanOptions = withAllOption('Semua Perkerasan', PERKERASAN_OPTIONS);
const statusJalanOptions = withAllOption('Semua Status Jalan', STATUS_JALAN_OPTIONS);
const statusKondisiOptions = withAllOption('Semua Status Kondisi', STATUS_KONDISI_OPTIONS);
const statusAsetOptions = withAllOption('Semua Status Aset', STATUS_ASET_OPTIONS);
const sumberDanaOptions = withAllOption('Semua Sumber Dana', SUMBER_DANA_OPTIONS);
const verifikasiOptions = withAllOption('Semua Status', STATUS_VERIFIKASI_OPTIONS);

interface FilterFieldDescriptor {
  key: string;
  label: string;
  placeholder: string;
  items: { label: string; value: string | number }[];
  modelValue: string | number;
  activeLabel: string | null;
  searchInput: boolean | { placeholder: string };
  fullWidth?: boolean;
  onUpdate: (val: any) => void;
  onClear: () => void;
}

function findOptionLabel(
  items: { label: string; value: string | number }[],
  val: string | number | null | undefined,
): string | null {
  if (val === null || val === undefined || val === '' || val === 'ALL') return null;
  const match = items.find((item) => String(item.value) === String(val));
  return match?.label ?? String(val);
}

const resolvedTahunValue = computed(() => props.selectedTahunPembangunan ?? props.selectedTahun ?? null);

// Parametrisasi field fisik & tata kelola menjaga keteraturan visual dan menghindari duplikasi kontrol
const physicalFields = computed<FilterFieldDescriptor[]>(() => {
  if (props.layerId === 'jalan-poros-desa') {
    return [
      {
        key: 'jalan-perkerasan',
        label: 'Jenis Perkerasan',
        placeholder: 'Pilih perkerasan',
        items: perkerasanOptions,
        modelValue: props.selectedPerkerasan || 'ALL',
        activeLabel: findOptionLabel(PERKERASAN_OPTIONS, props.selectedPerkerasan),
        searchInput: false,
        onUpdate: handlePerkerasanChange,
        onClear: () => handlePerkerasanChange(null),
      },
      {
        key: 'jalan-kondisi',
        label: 'Kondisi Jalan',
        placeholder: 'Pilih kondisi',
        items: jalanKondisiOptions,
        modelValue: props.selectedKondisi || 'ALL',
        activeLabel: findOptionLabel(JALAN_POROS_KONDISI_OPTIONS, props.selectedKondisi),
        searchInput: false,
        onUpdate: handleKondisiChange,
        onClear: () => handleKondisiChange(null),
      },
    ];
  }

  const fields: FilterFieldDescriptor[] = [];
  if (props.tipeList.length > 0) {
    fields.push({
      key: 'tipe',
      label: 'Tipe Infrastruktur',
      placeholder: 'Pilih tipe infrastruktur',
      items: tipeSelectItems.value,
      modelValue: props.selectedTipe || 'ALL',
      activeLabel: findOptionLabel(tipeSelectItems.value, props.selectedTipe),
      searchInput: { placeholder: 'Cari tipe infrastruktur...' },
      fullWidth: true,
      onUpdate: handleTipeChange,
      onClear: () => handleTipeChange(null),
    });
  }

  fields.push(
    {
      key: 'kondisi',
      label: 'Kondisi Fisik',
      placeholder: 'Pilih kondisi fisik',
      items: kondisiOptions,
      modelValue: props.selectedKondisi || 'ALL',
      activeLabel: findOptionLabel(KONDISI_OPTIONS, props.selectedKondisi),
      searchInput: false,
      onUpdate: handleKondisiChange,
      onClear: () => handleKondisiChange(null),
    },
    {
      key: 'perkerasan',
      label: 'Jenis Perkerasan',
      placeholder: 'Pilih perkerasan',
      items: perkerasanOptions,
      modelValue: props.selectedPerkerasan || 'ALL',
      activeLabel: findOptionLabel(PERKERASAN_OPTIONS, props.selectedPerkerasan),
      searchInput: false,
      onUpdate: handlePerkerasanChange,
      onClear: () => handlePerkerasanChange(null),
    },
    {
      key: 'status-kondisi',
      label: 'Status Kondisi',
      placeholder: 'Pilih status kondisi',
      items: statusKondisiOptions,
      modelValue: props.selectedStatusKondisi || 'ALL',
      activeLabel: findOptionLabel(STATUS_KONDISI_OPTIONS, props.selectedStatusKondisi),
      searchInput: false,
      onUpdate: handleStatusKondisiChange,
      onClear: () => handleStatusKondisiChange(null),
    },
    {
      key: 'tahun',
      label: 'Tahun Pembangunan',
      placeholder: 'Pilih tahun bangun',
      items: tahunPembangunanSelectItems.value,
      modelValue: resolvedTahunValue.value ?? 'ALL',
      activeLabel: resolvedTahunValue.value ? String(resolvedTahunValue.value) : null,
      searchInput: { placeholder: 'Cari tahun...' },
      onUpdate: handleTahunPembangunanChange,
      onClear: () => handleTahunPembangunanChange(null),
    },
  );

  return fields;
});

const governanceFields = computed<FilterFieldDescriptor[]>(() => {
  if (props.layerId !== 'infrastruktur-segmen') return [];
  return [
    {
      key: 'verifikasi',
      label: 'Status Verifikasi',
      placeholder: 'Pilih status verifikasi',
      items: verifikasiOptions,
      modelValue: props.selectedStatusVerifikasi || 'ALL',
      activeLabel: findOptionLabel(STATUS_VERIFIKASI_OPTIONS, props.selectedStatusVerifikasi),
      searchInput: false,
      onUpdate: handleStatusVerifikasiChange,
      onClear: () => handleStatusVerifikasiChange(null),
    },
    {
      key: 'status-jalan',
      label: 'Status Jalan',
      placeholder: 'Pilih status jalan',
      items: statusJalanOptions,
      modelValue: props.selectedStatusJalan || 'ALL',
      activeLabel: findOptionLabel(STATUS_JALAN_OPTIONS, props.selectedStatusJalan),
      searchInput: false,
      onUpdate: handleStatusJalanChange,
      onClear: () => handleStatusJalanChange(null),
    },
    {
      key: 'status-aset',
      label: 'Status Aset',
      placeholder: 'Pilih status aset',
      items: statusAsetOptions,
      modelValue: props.selectedStatusAset || 'ALL',
      activeLabel: findOptionLabel(STATUS_ASET_OPTIONS, props.selectedStatusAset),
      searchInput: false,
      onUpdate: handleStatusAsetChange,
      onClear: () => handleStatusAsetChange(null),
    },
    {
      key: 'sumber-dana',
      label: 'Sumber Dana',
      placeholder: 'Pilih sumber dana',
      items: sumberDanaOptions,
      modelValue: props.selectedSumberDana || 'ALL',
      activeLabel: findOptionLabel(SUMBER_DANA_OPTIONS, props.selectedSumberDana),
      searchInput: { placeholder: 'Cari sumber dana...' },
      onUpdate: handleSumberDanaChange,
      onClear: () => handleSumberDanaChange(null),
    },
  ];
});

interface ActiveFilterChip {
  key: string;
  label: string;
  valueLabel: string;
  onClear: () => void;
}

const activeFilterChips = computed<ActiveFilterChip[]>(() => {
  const chips: ActiveFilterChip[] = [];

  if (!effectiveKecamatanLocked.value && props.modelKecamatan) {
    const kecName =
      wilayahStore.kecamatanList.find((k) => Number(k.id) === Number(props.modelKecamatan))?.nama_kecamatan ??
      `Kecamatan #${props.modelKecamatan}`;
    chips.push({
      key: 'kecamatan',
      label: 'Kecamatan',
      valueLabel: kecName,
      onClear: () => {
        handleKecamatanChange(null);
        if (!effectiveDesaLocked.value) {
          handleDesaChange(null);
        }
      },
    });
  }

  if (!effectiveDesaLocked.value && props.modelDesa) {
    const desaList = props.modelKecamatan
      ? wilayahStore.desaByKecamatan[Number(props.modelKecamatan)] || []
      : [];
    const desaName =
      desaList.find((d) => Number(d.id) === Number(props.modelDesa))?.nama_desa ??
      `Desa #${props.modelDesa}`;
    chips.push({
      key: 'desa',
      label: 'Desa',
      valueLabel: desaName,
      onClear: () => handleDesaChange(null),
    });
  }

  for (const field of [...physicalFields.value, ...governanceFields.value]) {
    if (field.activeLabel) {
      chips.push({
        key: field.key,
        label: field.label,
        valueLabel: field.activeLabel,
        onClear: field.onClear,
      });
    }
  }

  return chips;
});

const activeFiltersCount = computed(() => activeFilterChips.value.length);
const hasActiveFilter = computed(() => activeFiltersCount.value > 0);

const fieldGroups = computed(() => {
  const groups = [
    {
      id: 'physical',
      title: 'Karakteristik & Kondisi Fisik',
      icon: 'i-lucide-route',
      ariaLabel: 'Karakteristik dan Kondisi Fisik',
      fields: physicalFields.value,
    },
  ];
  if (governanceFields.value.length > 0) {
    groups.push({
      id: 'governance',
      title: 'Administrasi, Aset & Pendanaan',
      icon: 'i-lucide-landmark',
      ariaLabel: 'Administrasi, Aset dan Pendanaan',
      fields: governanceFields.value,
    });
  }
  return groups;
});

const selectContentProps = {
  align: 'start' as const,
  sideOffset: 4,
};

const selectUiConfig = computed(() => ({
  base: props.isMobile
    ? 'min-h-[44px] justify-start text-left'
    : 'min-h-[44px] sm:min-h-[36px] justify-start text-left',
  content: 'w-(--reka-combobox-trigger-width)',
}));
</script>

<template>
  <div class="space-y-4">
    <!-- Ringkasan Filter Aktif (dapat dihapus per kriteria tanpa membuka ulang dropdown) -->
    <div
      v-if="hasActiveFilter"
      class="rounded-xl bg-blue-50/80 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-800/50 p-3 space-y-2"
    >
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-1.5 text-xs font-semibold text-blue-800 dark:text-blue-200">
          <UIcon name="i-lucide-filter" class="size-3.5 shrink-0" aria-hidden="true" />
          <span class="tabular-nums">{{ activeFiltersCount }} filter aktif</span>
        </div>
        <button
          v-if="!hideHeader"
          type="button"
          class="text-xs font-medium text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-100 underline underline-offset-2 cursor-pointer min-h-[36px] sm:min-h-0 px-1.5"
          @click="emit('reset')"
        >
          Atur ulang semua
        </button>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="chip in activeFilterChips"
          :key="chip.key"
          type="button"
          :aria-label="`Hapus filter ${chip.label}: ${chip.valueLabel}`"
          class="inline-flex items-center gap-1.5 rounded-lg bg-white dark:bg-[#0e1526] border border-blue-200 dark:border-blue-800/70 px-2.5 py-1.5 sm:py-1 min-h-[44px] sm:min-h-[28px] text-xs text-slate-700 dark:text-slate-200 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/60 dark:hover:bg-blue-900/20 cursor-pointer transition-colors"
          @click="chip.onClear"
        >
          <span class="text-slate-500 dark:text-slate-400">{{ chip.label }}:</span>
          <span class="font-semibold text-slate-900 dark:text-white">{{ chip.valueLabel }}</span>
          <UIcon name="i-lucide-x" class="size-3.5 text-slate-400 shrink-0" aria-hidden="true" />
        </button>
      </div>
    </div>

    <!-- Kelompok 1: Cakupan Wilayah -->
    <section
      aria-label="Cakupan Wilayah Administratif"
      class="rounded-xl border border-slate-200/90 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-3 sm:p-3.5 space-y-2.5"
    >
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-800 dark:text-slate-100">
          <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
          <span>Cakupan Wilayah</span>
        </div>
        <span
          v-if="effectiveKecamatanLocked || effectiveDesaLocked"
          class="inline-flex items-center gap-1 rounded-md bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/50 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:text-amber-200"
        >
          <UIcon name="i-lucide-lock" class="size-3 shrink-0" aria-hidden="true" />
          <span>Dikunci sesuai hak akses</span>
        </span>
      </div>

      <WilayahSelector
        :model-kecamatan="modelKecamatan"
        :model-desa="modelDesa"
        layout="row"
        :allow-all="true"
        :size="isMobile ? 'md' : 'sm'"
        @update:model-kecamatan="handleKecamatanChange"
        @update:model-desa="handleDesaChange"
      />
    </section>

    <!-- Kelompok 2 & 3: Atribut Layer (Karakteristik Fisik & Administrasi/Pendanaan) -->
    <section
      v-for="group in fieldGroups"
      :key="group.id"
      :aria-label="group.ariaLabel"
      class="rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#0e1424]/70 p-3 sm:p-3.5 space-y-3"
    >
      <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-800 dark:text-slate-100">
        <UIcon :name="group.icon" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" aria-hidden="true" />
        <span>{{ group.title }}</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div
          v-for="field in group.fields"
          :key="field.key"
          :class="['space-y-1.5', field.fullWidth ? 'sm:col-span-2' : '']"
        >
          <div class="flex items-center justify-between gap-2">
            <label
              :for="`${uid}-${field.key}`"
              class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer"
            >
              {{ field.label }}
            </label>
            <button
              v-if="field.activeLabel"
              type="button"
              :aria-label="`Reset ${field.label}`"
              class="text-[11px] font-medium text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
              @click="field.onClear"
            >
              Reset
            </button>
          </div>
          <USelectMenu
            :id="`${uid}-${field.key}`"
            :model-value="field.modelValue"
            :items="field.items"
            value-key="value"
            label-key="label"
            :search-input="field.searchInput"
            :highlight="Boolean(field.activeLabel)"
            :color="field.activeLabel ? 'primary' : undefined"
            :size="isMobile ? 'md' : 'sm'"
            :content="selectContentProps"
            :ui="selectUiConfig"
            :placeholder="field.placeholder"
            :aria-label="`Filter ${field.label}`"
            class="w-full"
            @update:model-value="field.onUpdate"
          />
        </div>
      </div>
    </section>

    <!-- Action Buttons Footer (untuk konteks drawer/mobile saat DialogFilter footer tidak dipakai) -->
    <div
      v-if="!hideFooterActions"
      class="pt-3 border-t border-slate-200/80 dark:border-slate-800 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2.5"
    >
      <UButton
        v-if="hasActiveFilter"
        icon="i-lucide-rotate-ccw"
        label="Atur Ulang"
        color="neutral"
        variant="subtle"
        :size="isMobile ? 'md' : 'sm'"
        class="cursor-pointer justify-center"
        :class="isMobile ? 'min-h-[44px]' : ''"
        @click="emit('reset')"
      />
      <UButton
        icon="i-lucide-check"
        :label="isMobile ? 'Terapkan & Lihat Peta' : 'Terapkan Filter'"
        color="primary"
        variant="solid"
        :size="isMobile ? 'md' : 'sm'"
        class="cursor-pointer font-semibold justify-center sm:flex-1"
        :class="isMobile ? 'min-h-[44px]' : ''"
        @click="emit('apply')"
      />
    </div>
  </div>
</template>

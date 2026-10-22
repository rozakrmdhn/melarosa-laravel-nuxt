<script setup lang="ts">
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import type { InfrastrukturTipe } from '~/types/infrastruktur';

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

const tipeSelectItems = computed(() => [
  { label: 'Semua Tipe', value: 'ALL' },
  ...props.tipeList.map((t) => ({
    label: t.nama,
    value: t.kode,
  })),
]);

const tahunPembangunanSelectItems = computed(() => {
  const currentYear = new Date().getFullYear();
  const items: { label: string; value: string | number }[] = [
    { label: 'Semua Tahun Pembangunan', value: 'ALL' },
  ];
  for (let y = currentYear + 1; y >= 2015; y--) {
    items.push({
      label: `Tahun ${y}`,
      value: y,
    });
  }
  return items;
});

const kondisiOptions = [
  { label: 'Semua Kondisi', value: 'ALL' },
  { label: 'Baik', value: 'Baik' },
  { label: 'Sedang', value: 'Sedang' },
  { label: 'Rusak Ringan', value: 'Rusak Ringan' },
  { label: 'Rusak Berat', value: 'Rusak Berat' },
];

const perkerasanOptions = [
  { label: 'Semua Perkerasan', value: 'ALL' },
  { label: 'Aspal', value: 'Aspal' },
  { label: 'Beton', value: 'Beton' },
  { label: 'Paving', value: 'Paving' },
  { label: 'Kerikil', value: 'Kerikil' },
  { label: 'Tanah', value: 'Tanah' },
];

const statusJalanOptions = [
  { label: 'Semua Status Jalan', value: 'ALL' },
  { label: 'Jalan Desa', value: 'Jalan Desa' },
  { label: 'Jalan Poros Desa', value: 'Jalan Poros Desa' },
  { label: 'Jalan Kabupaten', value: 'Jalan Kabupaten' },
];

const statusKondisiOptions = [
  { label: 'Semua Status Kondisi', value: 'ALL' },
  { label: 'Eksisting', value: 'Eksisting' },
  { label: 'Riwayat', value: 'Riwayat' },
];

const statusAsetOptions = [
  { label: 'Semua Status Aset', value: 'ALL' },
  { label: 'Pemerintah Desa', value: 'Pemerintah Desa' },
  { label: 'Pemerintah Kabupaten', value: 'Pemerintah Kabupaten' },
  { label: 'Aset Desa', value: 'Aset Desa' },
  { label: 'Lainnya', value: 'Lainnya' },
];

const sumberDanaOptions = [
  { label: 'Semua Sumber Dana', value: 'ALL' },
  { label: 'APBD', value: 'APBD' },
  { label: 'BKK', value: 'BKK' },
  { label: 'Dana Desa (DD)', value: 'DD' },
  { label: 'Sektoral', value: 'Sektoral' },
  { label: 'Lainnya', value: 'Lainnya' },
];

const verifikasiOptions = [
  { label: 'Semua Status', value: 'ALL' },
  { label: 'Draft', value: 'draft' },
  { label: 'Diajukan ke Kecamatan', value: 'submitted_desa' },
  { label: 'Terverifikasi Kecamatan', value: 'verified_kecamatan' },
  { label: 'Ditolak Kecamatan', value: 'rejected_kecamatan' },
  { label: 'Disahkan Bappeda', value: 'verified_bappeda' },
  { label: 'Ditolak Bappeda', value: 'rejected_bappeda' },
];

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.layerId === 'jalan-poros-desa') {
    if (props.selectedPerkerasan) count++;
    if (props.selectedKondisi) count++;
  } else {
    if (props.selectedTahunPembangunan || props.selectedTahun) count++;
    else if (props.selectedTipe) count++;
    if (props.selectedPerkerasan) count++;
    if (props.selectedKondisi) count++;
    if (props.selectedStatusVerifikasi) count++;
    if (props.selectedStatusJalan) count++;
    if (props.selectedStatusKondisi) count++;
    if (props.selectedStatusAset) count++;
    if (props.selectedSumberDana) count++;
  }
  if (props.modelKecamatan) count++;
  if (props.modelDesa) count++;
  return count;
});

const hasActiveFilter = computed(() => activeFiltersCount.value > 0);
</script>

<template>
  <div class="space-y-4">
    <!-- Active Filter Indicator Banner -->
    <div
      v-if="!hideHeader && hasActiveFilter"
      class="flex items-center justify-between px-3 py-2 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 text-xs"
    >
      <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
        <UIcon name="i-lucide-filter" class="size-3.5" />
        <span class="font-medium">{{ activeFiltersCount }} kriteria filter aktif</span>
      </div>
      <button
        type="button"
        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium underline underline-offset-2 cursor-pointer transition-colors"
        @click="emit('reset')"
      >
        Atur ulang
      </button>
    </div>

    <!-- Form Fields Container -->
    <div class="space-y-3.5">
      <!-- Wilayah Administratif (Row 2 Kolom) -->
      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
          Wilayah Administratif
        </label>
        <WilayahSelector
          :model-kecamatan="modelKecamatan"
          :model-desa="modelDesa"
          layout="row"
          :allow-all="true"
          :size="isMobile ? 'sm' : 'xs'"
          @update:model-kecamatan="handleKecamatanChange"
          @update:model-desa="handleDesaChange"
        />
      </div>

      <!-- Kriteria Layer 1: Segmen Fisik Infrastruktur -->
      <template v-if="layerId === 'infrastruktur-segmen'">
        <!-- Baris 1: Tahun Pembangunan & Jenis Perkerasan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Tahun Pembangunan
            </label>
            <USelectMenu
              :model-value="selectedTahunPembangunan ?? selectedTahun ?? 'ALL'"
              :items="tahunPembangunanSelectItems"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih tahun pembangunan"
              class="w-full"
              @update:model-value="handleTahunPembangunanChange"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Jenis Perkerasan
            </label>
            <USelectMenu
              :model-value="selectedPerkerasan || 'ALL'"
              :items="perkerasanOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih perkerasan"
              class="w-full"
              @update:model-value="handlePerkerasanChange"
            />
          </div>
        </div>

        <!-- Baris 2: Kondisi Fisik & Status Kondisi -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Kondisi Fisik
            </label>
            <USelectMenu
              :model-value="selectedKondisi || 'ALL'"
              :items="kondisiOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih kondisi"
              class="w-full"
              @update:model-value="handleKondisiChange"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Status Kondisi
            </label>
            <USelectMenu
              :model-value="selectedStatusKondisi || 'ALL'"
              :items="statusKondisiOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih status kondisi"
              class="w-full"
              @update:model-value="handleStatusKondisiChange"
            />
          </div>
        </div>

        <!-- Baris 3: Status Jalan & Status Aset -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Status Jalan
            </label>
            <USelectMenu
              :model-value="selectedStatusJalan || 'ALL'"
              :items="statusJalanOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih status jalan"
              class="w-full"
              @update:model-value="handleStatusJalanChange"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Status Aset
            </label>
            <USelectMenu
              :model-value="selectedStatusAset || 'ALL'"
              :items="statusAsetOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih status aset"
              class="w-full"
              @update:model-value="handleStatusAsetChange"
            />
          </div>
        </div>

        <!-- Baris 4: Sumber Dana & Status Verifikasi -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Sumber Dana
            </label>
            <USelectMenu
              :model-value="selectedSumberDana || 'ALL'"
              :items="sumberDanaOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih sumber dana"
              class="w-full"
              @update:model-value="handleSumberDanaChange"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Status Verifikasi
            </label>
            <USelectMenu
              :model-value="selectedStatusVerifikasi || 'ALL'"
              :items="verifikasiOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih status verifikasi"
              class="w-full"
              @update:model-value="handleStatusVerifikasiChange"
            />
          </div>
        </div>
      </template>

      <!-- Kriteria Layer 2: Jalan Poros Desa -->
      <template v-else-if="layerId === 'jalan-poros-desa'">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Jenis Perkerasan -->
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Jenis Perkerasan
            </label>
            <USelectMenu
              :model-value="selectedPerkerasan || 'ALL'"
              :items="perkerasanOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih perkerasan"
              class="w-full"
              @update:model-value="handlePerkerasanChange"
            />
          </div>

          <!-- Kondisi Jalan -->
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
              Kondisi Jalan
            </label>
            <USelectMenu
              :model-value="selectedKondisi || 'ALL'"
              :items="kondisiOptions"
              value-key="value"
              label-key="label"
              :size="isMobile ? 'sm' : 'xs'"
              :ui="{ content: 'z-[100]' }"
              placeholder="Pilih kondisi"
              class="w-full"
              @update:model-value="handleKondisiChange"
            />
          </div>
        </div>
      </template>
    </div>

    <!-- Action Buttons Footer (opsional bila diletakkan dalam modal sendiri) -->
    <div
      v-if="!hideFooterActions"
      class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2"
    >
      <UButton
        v-if="hasActiveFilter"
        icon="i-lucide-rotate-ccw"
        label="Atur Ulang"
        color="neutral"
        variant="ghost"
        :size="isMobile ? 'sm' : 'xs'"
        class="cursor-pointer"
        :class="isMobile ? 'min-h-[44px] px-3' : ''"
        @click="emit('reset')"
      />
      <UButton
        icon="i-lucide-check"
        :label="isMobile ? 'Terapkan & Lihat Peta' : 'Terapkan Filter'"
        color="primary"
        variant="solid"
        :size="isMobile ? 'sm' : 'xs'"
        class="cursor-pointer font-medium"
        :class="isMobile ? 'flex-1 justify-center min-h-[44px]' : ''"
        @click="emit('apply')"
      />
    </div>
  </div>
</template>

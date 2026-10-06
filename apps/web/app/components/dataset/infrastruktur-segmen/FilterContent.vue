<script setup lang="ts">
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import type { InfrastrukturTipe } from '~/types/infrastruktur';

interface Props {
  layerId?: string;
  layerTitle?: string;
  tipeList?: InfrastrukturTipe[];
  selectedTipe?: string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  selectedPerkerasan?: string | null;
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
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  selectedPerkerasan: null,
  modelKecamatan: null,
  modelDesa: null,
  isMobile: false,
  hideHeader: false,
  hideFooterActions: false,
});

const emit = defineEmits<{
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:selectedPerkerasan', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
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
  emit('update:selectedTipe', extractValue(val));
}

function handleKondisiChange(val: any) {
  emit('update:selectedKondisi', extractValue(val));
}

function handleStatusVerifikasiChange(val: any) {
  emit('update:selectedStatusVerifikasi', extractValue(val));
}

function handlePerkerasanChange(val: any) {
  emit('update:selectedPerkerasan', extractValue(val));
}

function handleKecamatanChange(val: any) {
  emit('update:modelKecamatan', extractNumber(val));
}

function handleDesaChange(val: any) {
  emit('update:modelDesa', extractNumber(val));
}

const tipeSelectItems = computed(() => [
  { label: 'Semua Tipe', value: 'ALL' },
  ...props.tipeList.map((t) => ({
    label: t.nama,
    value: t.kode,
  })),
]);

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
  { label: 'Kerikil', value: 'Kerikil' },
  { label: 'Tanah', value: 'Tanah' },
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
    if (props.selectedTipe) count++;
    if (props.selectedKondisi) count++;
    if (props.selectedStatusVerifikasi) count++;
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
        <!-- Tipe Infrastruktur -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
            Tipe Infrastruktur
          </label>
          <USelectMenu
            :model-value="selectedTipe || 'ALL'"
            :items="tipeSelectItems"
            value-key="value"
            label-key="label"
            :size="isMobile ? 'sm' : 'xs'"
            placeholder="Pilih tipe infrastruktur"
            class="w-full"
            @update:model-value="handleTipeChange"
          />
        </div>

        <!-- 2 Kolom: Kondisi Fisik & Status Verifikasi -->
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
              placeholder="Pilih kondisi"
              class="w-full"
              @update:model-value="handleKondisiChange"
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
              placeholder="Pilih status"
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

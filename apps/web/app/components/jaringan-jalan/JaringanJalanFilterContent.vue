<script setup lang="ts">
import WilayahSelector from '~/components/common/WilayahSelector.vue';
import { STATUS_ANALISA_LIST } from '~/utils/jaringan-jalan-format';
import type { HierarkiJalan, StatusAnalisaRtrw } from '~/types/jaringan-jalan';

interface FilterDraft {
  status_analisa: '' | StatusAnalisaRtrw;
  orde02: string;
  only_unadopted: boolean;
  only_missing_desa: boolean;
  sort_by: string;
  hierarki_jalan: '' | HierarkiJalan;
  klas_ruas: string;
  id_kecamatan: number | null;
  id_desa: number | null;
}

interface Props {
  filters: FilterDraft;
  ordeOptions: Array<{ value: string; label: string; total: number }>;
  isKecamatanLocked?: boolean;
  isDesaLocked?: boolean;
  isMobileDrawer?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isKecamatanLocked: false,
  isDesaLocked: false,
  isMobileDrawer: false,
});

const emit = defineEmits<{
  (e: 'update-filter', payload: Partial<FilterDraft>): void;
  (e: 'reset-filters'): void;
  (e: 'close'): void;
}>();

const sortOptions = [
  { value: 'uncovered_desc', label: 'Gap Belum Terdata Terpanjang' },
  { value: 'panjang_desc', label: 'Panjang Segmen Terbesar' },
  { value: 'coverage_asc', label: 'Cakupan Irisan Terendah' },
  { value: 'coverage_desc', label: 'Cakupan Irisan Tertinggi' },
];

const hierarkiFilterOptions: Array<{ value: '' | HierarkiJalan; label: string }> = [
  { value: '', label: 'Semua Hierarki Jalan Poros' },
  { value: 'poros_desa', label: 'Jalan Poros Desa' },
  { value: 'lingkungan', label: 'Jalan Lingkungan' },
  { value: 'usaha_tani', label: 'Jalan Usaha Tani' },
  { value: 'kabupaten_usulan', label: 'Kabupaten Usulan' },
  { value: 'kabupaten', label: 'Jalan Kabupaten' },
];
</script>

<template>
  <div class="space-y-4 text-xs">
    <!-- Wilayah Administrasi -->
    <div v-if="!isDesaLocked" class="space-y-1.5">
      <h3 class="text-xs font-semibold text-gray-900 dark:text-white">
        Cakupan Wilayah Administrasi
      </h3>
      <WilayahSelector
        :model-kecamatan="filters.id_kecamatan"
        :model-desa="filters.id_desa"
        layout="col"
        :allow-all="true"
        :show-labels="true"
        :size="isMobileDrawer ? 'sm' : 'xs'"
        @update:model-kecamatan="(val) => emit('update-filter', { id_kecamatan: val, id_desa: null })"
        @update:model-desa="(val) => emit('update-filter', { id_desa: val })"
      />
    </div>

    <!-- Status Irisan Spasial RTRW -->
    <div class="space-y-1.5">
      <label for="flt-status-analisa" class="block text-xs font-semibold text-gray-900 dark:text-white">
        Status Irisan Spasial RTRW
      </label>
      <select
        id="flt-status-analisa"
        :value="filters.status_analisa"
        :class="[
          'w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600',
          isMobileDrawer ? 'min-h-[44px]' : 'h-9',
        ]"
        @change="(e) => emit('update-filter', { status_analisa: (e.target as HTMLSelectElement).value as '' | StatusAnalisaRtrw })"
      >
        <option value="">Semua Status Analisa</option>
        <option v-for="st in STATUS_ANALISA_LIST" :key="st.key" :value="st.key">
          {{ st.label }}
        </option>
      </select>
    </div>

    <!-- Orde & Urutan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div class="space-y-1.5">
        <label for="flt-orde" class="block text-xs font-semibold text-gray-900 dark:text-white">
          Orde Jaringan RTRW
        </label>
        <select
          id="flt-orde"
          :value="filters.orde02"
          :class="[
            'w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600',
            isMobileDrawer ? 'min-h-[44px]' : 'h-9',
          ]"
          @change="(e) => emit('update-filter', { orde02: (e.target as HTMLSelectElement).value })"
        >
          <option value="">Semua Orde Jalan</option>
          <option v-for="opt in ordeOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }} ({{ opt.total }})
          </option>
        </select>
      </div>

      <div class="space-y-1.5">
        <label for="flt-sort" class="block text-xs font-semibold text-gray-900 dark:text-white">
          Urutkan Hasil Analisa
        </label>
        <select
          id="flt-sort"
          :value="filters.sort_by"
          :class="[
            'w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600',
            isMobileDrawer ? 'min-h-[44px]' : 'h-9',
          ]"
          @change="(e) => emit('update-filter', { sort_by: (e.target as HTMLSelectElement).value })"
        >
          <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Hierarki Poros & Klas Kabupaten -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div class="space-y-1.5">
        <label for="flt-hierarki" class="block text-xs font-semibold text-gray-900 dark:text-white">
          Hierarki Master Poros
        </label>
        <select
          id="flt-hierarki"
          :value="filters.hierarki_jalan"
          :class="[
            'w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600',
            isMobileDrawer ? 'min-h-[44px]' : 'h-9',
          ]"
          @change="(e) => emit('update-filter', { hierarki_jalan: (e.target as HTMLSelectElement).value as '' | HierarkiJalan })"
        >
          <option v-for="opt in hierarkiFilterOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div class="space-y-1.5">
        <label for="flt-klas-kab" class="block text-xs font-semibold text-gray-900 dark:text-white">
          Klasifikasi Ruas Kabupaten
        </label>
        <input
          id="flt-klas-kab"
          :value="filters.klas_ruas"
          type="text"
          placeholder="Contoh: JKP, JLP..."
          :class="[
            'w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600',
            isMobileDrawer ? 'min-h-[44px]' : 'h-9',
          ]"
          @input="(e) => emit('update-filter', { klas_ruas: (e.target as HTMLInputElement).value })"
        />
      </div>
    </div>

    <!-- Opsi Prioritas Gap -->
    <div class="space-y-1.5 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-[#0e1424]">
      <label
        :class="[
          'flex items-center gap-2.5 text-xs font-medium text-gray-800 dark:text-gray-200 cursor-pointer',
          isMobileDrawer ? 'min-h-[44px]' : 'py-1',
        ]"
      >
        <input
          type="checkbox"
          :checked="filters.only_unadopted"
          class="size-4 rounded border-gray-300 accent-[#176B4D]"
          @change="(e) => emit('update-filter', { only_unadopted: (e.target as HTMLInputElement).checked })"
        />
        <span>Hanya tampilkan segmen RTRW yang belum diadopsi ke Master Poros</span>
      </label>

      <label
        :class="[
          'flex items-center gap-2.5 text-xs font-medium text-gray-800 dark:text-gray-200 cursor-pointer',
          isMobileDrawer ? 'min-h-[44px]' : 'py-1',
        ]"
      >
        <input
          type="checkbox"
          :checked="filters.only_missing_desa"
          class="size-4 rounded border-gray-300 accent-[#176B4D]"
          @change="(e) => emit('update-filter', { only_missing_desa: (e.target as HTMLInputElement).checked })"
        />
        <span>Prioritaskan desa yang belum memiliki ruas di Master Poros</span>
      </label>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-2 pt-1">
      <button
        type="button"
        :class="[
          'rounded-md border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
          isMobileDrawer ? 'min-h-[44px]' : 'h-9',
        ]"
        @click="emit('reset-filters')"
      >
        Reset Semua Filter
      </button>
      <button
        type="button"
        :class="[
          'rounded-md bg-[#176B4D] px-4 text-xs font-semibold text-white hover:bg-[#12543c] cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
          isMobileDrawer ? 'min-h-[44px]' : 'h-9',
        ]"
        @click="emit('close')"
      >
        Terapkan & Tutup
      </button>
    </div>
  </div>
</template>

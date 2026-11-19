<script setup lang="ts">
import FilterContent from '~/components/dataset/infrastruktur-segmen/FilterContent.vue';
import { useAuthStore } from '~/stores/auth';
import type { InfrastrukturTipe, RefSumberDana } from '~/types/infrastruktur';

interface Props {
  open?: boolean;
  activeLayerId?: string;
  tipeList?: InfrastrukturTipe[];
  sumberDanaList?: RefSumberDana[];
  // Filter Layer Segmen Fisik
  selectedTipe?: string | null;
  selectedTahunPembangunan?: number | string | null;
  selectedKondisi?: string | null;
  selectedStatusVerifikasi?: string | null;
  selectedPerkerasan?: string | null;
  selectedStatusJalan?: string | null;
  selectedStatusKondisi?: string | null;
  selectedStatusAset?: string | null;
  selectedSumberDana?: string | null;
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  segmenFilterCount?: number;

  // Filter Layer Jalan Poros Desa
  jalanPerkerasan?: string | null;
  jalanKondisi?: string | null;
  jalanKecamatan?: number | null;
  jalanDesa?: number | null;
  jalanFilterCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  activeLayerId: 'infrastruktur-segmen',
  tipeList: () => [],
  sumberDanaList: () => [],
  selectedTipe: null,
  selectedTahunPembangunan: null,
  selectedKondisi: null,
  selectedStatusVerifikasi: null,
  selectedPerkerasan: null,
  selectedStatusJalan: null,
  selectedStatusKondisi: null,
  selectedStatusAset: null,
  selectedSumberDana: null,
  modelKecamatan: null,
  modelDesa: null,
  segmenFilterCount: 0,
  jalanPerkerasan: null,
  jalanKondisi: null,
  jalanKecamatan: null,
  jalanDesa: null,
  jalanFilterCount: 0,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'update:activeLayerId', val: string): void;
  // Segmen Filter Emits
  (e: 'update:selectedTipe', val: string | null): void;
  (e: 'update:selectedTahunPembangunan', val: number | string | null): void;
  (e: 'update:selected-tahun-pembangunan', val: number | string | null): void;
  (e: 'update:selectedKondisi', val: string | null): void;
  (e: 'update:selectedStatusVerifikasi', val: string | null): void;
  (e: 'update:selectedPerkerasan', val: string | null): void;
  (e: 'update:selectedStatusJalan', val: string | null): void;
  (e: 'update:selected-status-jalan', val: string | null): void;
  (e: 'update:selectedStatusKondisi', val: string | null): void;
  (e: 'update:selected-status-kondisi', val: string | null): void;
  (e: 'update:selectedStatusAset', val: string | null): void;
  (e: 'update:selected-status-aset', val: string | null): void;
  (e: 'update:selectedSumberDana', val: string | null): void;
  (e: 'update:selected-sumber-dana', val: string | null): void;
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'reset-segmen'): void;
  (e: 'apply-segmen'): void;

  // Jalan Poros Filter Emits
  (e: 'update:jalanPerkerasan', val: string | null): void;
  (e: 'update:jalanKondisi', val: string | null): void;
  (e: 'update:jalanKecamatan', val: number | null): void;
  (e: 'update:jalanDesa', val: number | null): void;
  (e: 'reset-jalan'): void;
  (e: 'apply-jalan'): void;

  (e: 'apply'): void;
}>();

const auth = useAuthStore();
const { isAdmin } = usePermission();

const isUnrestrictedRole = computed(
  () => isAdmin() || auth.hasRole('admin') || auth.hasRole('verifierBappeda'),
);
const isKecamatanLocked = computed(() => !isUnrestrictedRole.value && !!auth.user?.id_kecamatan);
const isDesaLocked = computed(() => !isUnrestrictedRole.value && !!auth.user?.id_desa);

// Draft filter state agar tombol Tutup membuang perubahan sebelum Terapkan diklik
const draft = reactive({
  selectedTipe: null as string | null,
  selectedTahunPembangunan: null as number | string | null,
  selectedKondisi: null as string | null,
  selectedStatusVerifikasi: null as string | null,
  selectedPerkerasan: null as string | null,
  selectedStatusJalan: null as string | null,
  selectedStatusKondisi: null as string | null,
  selectedStatusAset: null as string | null,
  selectedSumberDana: null as string | null,
  modelKecamatan: null as number | null,
  modelDesa: null as number | null,
  jalanPerkerasan: null as string | null,
  jalanKondisi: null as string | null,
  jalanKecamatan: null as number | null,
  jalanDesa: null as number | null,
});

function syncDraftFromProps() {
  draft.selectedTipe = props.selectedTipe ?? null;
  draft.selectedTahunPembangunan = props.selectedTahunPembangunan ?? null;
  draft.selectedKondisi = props.selectedKondisi ?? null;
  draft.selectedStatusVerifikasi = props.selectedStatusVerifikasi ?? null;
  draft.selectedPerkerasan = props.selectedPerkerasan ?? null;
  draft.selectedStatusJalan = props.selectedStatusJalan ?? null;
  draft.selectedStatusKondisi = props.selectedStatusKondisi ?? null;
  draft.selectedStatusAset = props.selectedStatusAset ?? null;
  draft.selectedSumberDana = props.selectedSumberDana ?? null;
  const resolvedKec = props.modelKecamatan ?? props.jalanKecamatan ?? null;
  const resolvedDesa = props.modelDesa ?? props.jalanDesa ?? null;
  draft.modelKecamatan = resolvedKec;
  draft.modelDesa = resolvedDesa;
  draft.jalanPerkerasan = props.jalanPerkerasan ?? null;
  draft.jalanKondisi = props.jalanKondisi ?? null;
  draft.jalanKecamatan = resolvedKec;
  draft.jalanDesa = resolvedDesa;
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      syncDraftFromProps();
    }
  },
  { immediate: true },
);

function setDraftKecamatan(val: number | null) {
  draft.modelKecamatan = val;
  draft.jalanKecamatan = val;
}

function setDraftDesa(val: number | null) {
  draft.modelDesa = val;
  draft.jalanDesa = val;
}

const draftSegmenFilterCount = computed(() => {
  let count = 0;
  if (draft.selectedTahunPembangunan) count++;
  if (draft.selectedTipe) count++;
  if (draft.selectedPerkerasan) count++;
  if (draft.selectedKondisi) count++;
  if (draft.selectedStatusVerifikasi) count++;
  if (draft.selectedStatusJalan) count++;
  if (draft.selectedStatusKondisi) count++;
  if (draft.selectedStatusAset) count++;
  if (draft.selectedSumberDana) count++;
  if (!isKecamatanLocked.value && draft.modelKecamatan) count++;
  if (!isDesaLocked.value && draft.modelDesa) count++;
  return count;
});

const draftJalanFilterCount = computed(() => {
  let count = 0;
  if (draft.jalanPerkerasan) count++;
  if (draft.jalanKondisi) count++;
  if (!isKecamatanLocked.value && draft.jalanKecamatan) count++;
  if (!isDesaLocked.value && draft.jalanDesa) count++;
  return count;
});

const currentActiveCount = computed(() => {
  if (props.activeLayerId === 'jalan-poros-desa') {
    return draftJalanFilterCount.value;
  }
  return draftSegmenFilterCount.value;
});

const currentHasActiveFilter = computed(() => currentActiveCount.value > 0);

const hasPendingChanges = computed(() => {
  if (props.activeLayerId === 'jalan-poros-desa') {
    return (
      draft.jalanPerkerasan !== (props.jalanPerkerasan ?? null) ||
      draft.jalanKondisi !== (props.jalanKondisi ?? null) ||
      draft.jalanKecamatan !== (props.jalanKecamatan ?? props.modelKecamatan ?? null) ||
      draft.jalanDesa !== (props.jalanDesa ?? props.modelDesa ?? null)
    );
  }
  return (
    draft.selectedTipe !== (props.selectedTipe ?? null) ||
    draft.selectedTahunPembangunan !== (props.selectedTahunPembangunan ?? null) ||
    draft.selectedKondisi !== (props.selectedKondisi ?? null) ||
    draft.selectedStatusVerifikasi !== (props.selectedStatusVerifikasi ?? null) ||
    draft.selectedPerkerasan !== (props.selectedPerkerasan ?? null) ||
    draft.selectedStatusJalan !== (props.selectedStatusJalan ?? null) ||
    draft.selectedStatusKondisi !== (props.selectedStatusKondisi ?? null) ||
    draft.selectedStatusAset !== (props.selectedStatusAset ?? null) ||
    draft.selectedSumberDana !== (props.selectedSumberDana ?? null) ||
    draft.modelKecamatan !== (props.modelKecamatan ?? null) ||
    draft.modelDesa !== (props.modelDesa ?? null)
  );
});

function handleResetCurrent() {
  if (props.activeLayerId === 'jalan-poros-desa') {
    draft.jalanPerkerasan = null;
    draft.jalanKondisi = null;
  } else {
    draft.selectedTipe = null;
    draft.selectedTahunPembangunan = null;
    draft.selectedKondisi = null;
    draft.selectedStatusVerifikasi = null;
    draft.selectedPerkerasan = null;
    draft.selectedStatusJalan = null;
    draft.selectedStatusKondisi = null;
    draft.selectedStatusAset = null;
    draft.selectedSumberDana = null;
  }

  if (!isKecamatanLocked.value) {
    setDraftKecamatan(null);
  }
  if (!isDesaLocked.value) {
    setDraftDesa(null);
  }
}

function handleApply() {
  emit('update:selectedTipe', draft.selectedTipe);
  emit('update:selectedTahunPembangunan', draft.selectedTahunPembangunan);
  emit('update:selected-tahun-pembangunan', draft.selectedTahunPembangunan);
  emit('update:selectedKondisi', draft.selectedKondisi);
  emit('update:selectedStatusVerifikasi', draft.selectedStatusVerifikasi);
  emit('update:selectedPerkerasan', draft.selectedPerkerasan);
  emit('update:selectedStatusJalan', draft.selectedStatusJalan);
  emit('update:selected-status-jalan', draft.selectedStatusJalan);
  emit('update:selectedStatusKondisi', draft.selectedStatusKondisi);
  emit('update:selected-status-kondisi', draft.selectedStatusKondisi);
  emit('update:selectedStatusAset', draft.selectedStatusAset);
  emit('update:selected-status-aset', draft.selectedStatusAset);
  emit('update:selectedSumberDana', draft.selectedSumberDana);
  emit('update:selected-sumber-dana', draft.selectedSumberDana);
  emit('update:modelKecamatan', draft.modelKecamatan);
  emit('update:modelDesa', draft.modelDesa);

  emit('update:jalanPerkerasan', draft.jalanPerkerasan);
  emit('update:jalanKondisi', draft.jalanKondisi);
  emit('update:jalanKecamatan', draft.jalanKecamatan);
  emit('update:jalanDesa', draft.jalanDesa);

  if (props.activeLayerId === 'jalan-poros-desa') {
    emit('apply-jalan');
  } else {
    emit('apply-segmen');
  }
  emit('apply');
  emit('update:open', false);
}
</script>

<template>
  <UModal
    :open="open"
    :overlay="false"
    :modal="false"
    title="Filter Data Peta"
    description="Pilih layer dan atur kriteria wilayah atau atribut sebelum diterapkan ke peta dan tabel."
    :ui="{
      content: 'w-[calc(100vw-1rem)] sm:w-[calc(100vw-2rem)] max-w-2xl bg-white dark:bg-[#0b0f19] border border-slate-300/90 dark:border-slate-700/90 ring-1 ring-slate-900/10 dark:ring-white/10 shadow-2xl rounded-2xl overflow-hidden',
      header: 'px-4 py-3.5 sm:px-5 sm:py-4',
      body: 'p-4 sm:p-5 overflow-y-auto overscroll-contain',
      footer: 'px-4 py-3 sm:px-5 sm:py-3.5 bg-slate-50/90 dark:bg-[#090d16] flex items-center justify-between gap-3 flex-wrap'
    }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <!-- Layer Segmented Switcher -->
      <div
        role="tablist"
        aria-label="Pilih layer data peta"
        class="mb-4 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl flex items-center gap-1 border border-slate-200/70 dark:border-slate-800"
      >
        <button
          type="button"
          role="tab"
          :aria-selected="activeLayerId === 'infrastruktur-segmen'"
          :class="[
            'flex-1 py-2 px-3 rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer min-h-[44px] sm:min-h-[38px]',
            activeLayerId === 'infrastruktur-segmen'
              ? 'bg-white dark:bg-[#0e1526] text-blue-600 dark:text-blue-400 font-semibold shadow-xs ring-1 ring-slate-200/80 dark:ring-slate-700/80'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
          @click="emit('update:activeLayerId', 'infrastruktur-segmen')"
        >
          <UIcon name="i-lucide-route" class="size-3.5 shrink-0" aria-hidden="true" />
          <span class="truncate">Segmen Fisik</span>
          <UBadge
            v-if="draftSegmenFilterCount > 0"
            :label="String(draftSegmenFilterCount)"
            size="xs"
            color="primary"
            variant="subtle"
            class="text-[10px] px-1.5 py-0 h-4 tabular-nums"
          />
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="activeLayerId === 'jalan-poros-desa'"
          :class="[
            'flex-1 py-2 px-3 rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer min-h-[44px] sm:min-h-[38px]',
            activeLayerId === 'jalan-poros-desa'
              ? 'bg-white dark:bg-[#0e1526] text-blue-600 dark:text-blue-400 font-semibold shadow-xs ring-1 ring-slate-200/80 dark:ring-slate-700/80'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
          @click="emit('update:activeLayerId', 'jalan-poros-desa')"
        >
          <UIcon name="i-lucide-milestone" class="size-3.5 shrink-0" aria-hidden="true" />
          <span class="truncate">Jalan Poros Desa</span>
          <UBadge
            v-if="draftJalanFilterCount > 0"
            :label="String(draftJalanFilterCount)"
            size="xs"
            color="info"
            variant="subtle"
            class="text-[10px] px-1.5 py-0 h-4 tabular-nums"
          />
        </button>
      </div>

      <!-- Layer 1 Content: Segmen Fisik -->
      <FilterContent
        v-if="activeLayerId === 'infrastruktur-segmen'"
        layer-id="infrastruktur-segmen"
        layer-title="Segmen Fisik Infrastruktur"
        :tipe-list="tipeList"
        :sumber-dana-list="sumberDanaList"
        :selected-tipe="draft.selectedTipe"
        :selected-tahun-pembangunan="draft.selectedTahunPembangunan"
        :selected-kondisi="draft.selectedKondisi"
        :selected-status-verifikasi="draft.selectedStatusVerifikasi"
        :selected-perkerasan="draft.selectedPerkerasan"
        :selected-status-jalan="draft.selectedStatusJalan"
        :selected-status-kondisi="draft.selectedStatusKondisi"
        :selected-status-aset="draft.selectedStatusAset"
        :selected-sumber-dana="draft.selectedSumberDana"
        :model-kecamatan="draft.modelKecamatan"
        :model-desa="draft.modelDesa"
        :is-kecamatan-locked="isKecamatanLocked"
        :is-desa-locked="isDesaLocked"
        :is-mobile="false"
        :hide-header="true"
        :hide-footer-actions="true"
        @update:selected-tipe="draft.selectedTipe = $event"
        @update:selected-tahun-pembangunan="draft.selectedTahunPembangunan = $event"
        @update:selected-kondisi="draft.selectedKondisi = $event"
        @update:selected-status-verifikasi="draft.selectedStatusVerifikasi = $event"
        @update:selected-perkerasan="draft.selectedPerkerasan = $event"
        @update:selected-status-jalan="draft.selectedStatusJalan = $event"
        @update:selected-status-kondisi="draft.selectedStatusKondisi = $event"
        @update:selected-status-aset="draft.selectedStatusAset = $event"
        @update:selected-sumber-dana="draft.selectedSumberDana = $event"
        @update:model-kecamatan="setDraftKecamatan($event)"
        @update:model-desa="setDraftDesa($event)"
      />

      <!-- Layer 2 Content: Jalan Poros Desa -->
      <FilterContent
        v-else-if="activeLayerId === 'jalan-poros-desa'"
        layer-id="jalan-poros-desa"
        layer-title="Jalan Poros Desa"
        :selected-perkerasan="draft.jalanPerkerasan"
        :selected-kondisi="draft.jalanKondisi"
        :model-kecamatan="draft.jalanKecamatan"
        :model-desa="draft.jalanDesa"
        :is-kecamatan-locked="isKecamatanLocked"
        :is-desa-locked="isDesaLocked"
        :is-mobile="false"
        :hide-header="true"
        :hide-footer-actions="true"
        @update:selected-perkerasan="draft.jalanPerkerasan = $event"
        @update:selected-kondisi="draft.jalanKondisi = $event"
        @update:model-kecamatan="setDraftKecamatan($event)"
        @update:model-desa="setDraftDesa($event)"
      />
    </template>

    <template #footer>
      <div class="flex items-center gap-2 min-w-0">
        <UButton
          v-if="currentHasActiveFilter"
          icon="i-lucide-rotate-ccw"
          label="Atur Ulang"
          color="neutral"
          variant="ghost"
          size="sm"
          class="cursor-pointer min-h-[44px] sm:min-h-[36px] text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
          @click="handleResetCurrent"
        />
        <span
          v-if="hasPendingChanges"
          class="text-xs font-medium text-amber-700 dark:text-amber-300"
        >
          Perubahan belum diterapkan
        </span>
      </div>

      <div class="flex items-center gap-2 ms-auto w-full sm:w-auto justify-end">
        <UButton
          label="Batal"
          color="neutral"
          variant="subtle"
          size="sm"
          class="cursor-pointer min-h-[44px] sm:min-h-[36px] px-3.5"
          @click="emit('update:open', false)"
        />
        <UButton
          icon="i-lucide-check"
          :label="currentActiveCount > 0 ? `Terapkan Filter (${currentActiveCount})` : 'Terapkan Filter'"
          color="primary"
          variant="solid"
          size="sm"
          class="cursor-pointer font-semibold min-h-[44px] sm:min-h-[36px] px-4 flex-1 sm:flex-none justify-center"
          @click="handleApply"
        />
      </div>
    </template>
  </UModal>
</template>

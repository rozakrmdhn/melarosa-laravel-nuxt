<script setup lang="ts">
import type {
  DesaOption,
  KecamatanOption,
  SelectedFeature,
} from "~/types/dataset-editor";

const props = withDefaults(
  defineProps<{
    collapsed?: boolean;
    width?: number;
    selectedFeature?: SelectedFeature | null;
    loading?: boolean;
    isMobileDrawer?: boolean;
    collapsible?: boolean;
    activeTab?: "info" | "filter";
    kecamatanFilter?: string | null;
    desaFilter?: string | null;
    kondisiFilter?: string | null;
    perkerasanFilter?: string | null;
    kecamatanOptions?: KecamatanOption[];
    desaOptions?: DesaOption[];
    kondisiOptions?: string[];
    perkerasanOptions?: string[];
  }>(),
  {
    collapsed: false,
    width: 380,
    selectedFeature: null,
    loading: false,
    isMobileDrawer: false,
    collapsible: true,
    activeTab: "info",
    kecamatanFilter: null,
    desaFilter: null,
    kondisiFilter: null,
    perkerasanFilter: null,
    kecamatanOptions: () => [],
    desaOptions: () => [],
    kondisiOptions: () => ["BAIK", "SEDANG", "RUSAK", "RUSAK BERAT"],
    perkerasanOptions: () => ["Aspal", "Beton", "Kerikil", "Tanah", "Lainnya"],
  }
);

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "update:activeTab", tab: "info" | "filter"): void;
  (e: "expandToTab", tab: "info" | "filter"): void;
  (e: "zoomToFeature", feature: SelectedFeature): void;
  (e: "editFeature", feature: SelectedFeature): void;
  (e: "splitFeature", feature: SelectedFeature): void;
  (e: "deleteFeature", feature: SelectedFeature): void;
  (e: "viewInTable", feature?: SelectedFeature): void;
  (e: "update:kecamatanFilter", val: string | null): void;
  (e: "update:desaFilter", val: string | null): void;
  (e: "update:kondisiFilter", val: string | null): void;
  (e: "update:perkerasanFilter", val: string | null): void;
  (e: "resetFilters"): void;
  (e: "applyFilter"): void;
}>();

const currentTab = computed({
  get: () => props.activeTab,
  set: (val: "info" | "filter") => emit("update:activeTab", val),
});

function handleCollapsedClick(tab: "info" | "filter") {
  emit("update:activeTab", tab);
  emit("update:collapsed", false);
  emit("expandToTab", tab);
}

// ─── Filter Logic ─────────────────────────────────────────────────────────────
const selectedKecamatanObj = computed(() => {
  if (!props.kecamatanFilter) return null;
  const target = props.kecamatanFilter.toLowerCase();
  return (
    props.kecamatanOptions.find(
      (k) =>
        k.nama.toLowerCase() === target || String(k.id) === props.kecamatanFilter
    ) || null
  );
});

const kecamatanSelectItems = computed(() => [
  { label: "Semua Kecamatan", value: "ALL" },
  ...props.kecamatanOptions.map((k) => ({
    label: k.nama,
    value: k.nama,
  })),
]);

const filteredDesaList = computed(() => {
  if (!selectedKecamatanObj.value) {
    return props.desaOptions;
  }
  return props.desaOptions.filter(
    (d) => d.id_kecamatan === selectedKecamatanObj.value?.id
  );
});

const desaSelectItems = computed(() => [
  {
    label: selectedKecamatanObj.value
      ? `Semua Desa (${selectedKecamatanObj.value.nama})`
      : "Semua Desa",
    value: "ALL",
  },
  ...filteredDesaList.value.map((d) => ({
    label: d.nama,
    value: d.nama,
  })),
]);

function handleKecamatanChange(val: any) {
  const newVal = !val || val === "ALL" ? null : String(val);
  emit("update:kecamatanFilter", newVal);

  if (props.desaFilter && newVal) {
    const targetKec = props.kecamatanOptions.find(
      (k) => k.nama.toLowerCase() === newVal.toLowerCase()
    );
    if (targetKec) {
      const exists = props.desaOptions.some(
        (d) =>
          d.id_kecamatan === targetKec.id &&
          d.nama.toLowerCase() === props.desaFilter?.toLowerCase()
      );
      if (!exists) {
        emit("update:desaFilter", null);
      }
    }
  }
}

function handleDesaChange(val: any) {
  const newVal = !val || val === "ALL" ? null : String(val);
  emit("update:desaFilter", newVal);
}

const kondisiSelectItems = computed(() => [
  { label: "Semua Kondisi", value: "ALL" },
  ...props.kondisiOptions.map((k) => ({ label: k, value: k })),
]);

const perkerasanSelectItems = computed(() => [
  { label: "Semua Perkerasan", value: "ALL" },
  ...props.perkerasanOptions.map((p) => ({ label: p, value: p })),
]);

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.kecamatanFilter) count++;
  if (props.desaFilter) count++;
  if (props.kondisiFilter) count++;
  if (props.perkerasanFilter) count++;
  return count;
});

const hasActiveFilter = computed(() => activeFiltersCount.value > 0);
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col select-none overflow-hidden w-full h-full',
      'bg-white dark:bg-[#0b0f19]'
    ]"
  >
    <!-- Header: Tabs (Info & Filter) + Collapse Button -->
    <div class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-2.5 sm:px-3 gap-2 justify-between">
      <!-- Expanded or Mobile Header: Two Segmented Tabs -->
      <Transition name="panel-subtle-fade">
        <div v-if="isMobileDrawer || !collapsed" class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800/70 p-0.5 rounded-lg">
          <!-- Tab 1: Info / Properties -->
          <button
            type="button"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer',
              currentTab === 'info'
                ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs font-semibold'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            ]"
            @click="currentTab = 'info'"
          >
            <UIcon
              name="i-lucide-info"
              :class="[
                'size-3.5 shrink-0 transition-colors',
                currentTab === 'info' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'
              ]"
            />
            <span>Properties</span>
            <span
              v-if="selectedFeature"
              class="size-1.5 rounded-full bg-emerald-500 shrink-0"
              title="Ada ruas terpilih"
            />
          </button>

          <!-- Tab 2: Filter Atribut -->
          <button
            type="button"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer',
              currentTab === 'filter'
                ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white shadow-xs font-semibold'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            ]"
            @click="currentTab = 'filter'"
          >
            <UIcon
              name="i-lucide-filter"
              :class="[
                'size-3.5 shrink-0 transition-colors',
                currentTab === 'filter' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'
              ]"
            />
            <span>Filter</span>
            <UBadge
              v-if="hasActiveFilter"
              :label="String(activeFiltersCount)"
              size="xs"
              color="primary"
              variant="subtle"
              class="font-mono text-[9px] px-1 py-0 h-3.5 leading-none shrink-0"
            />
          </button>
        </div>
      </Transition>

      <!-- Desktop Collapse Toggle Button -->
      <UTooltip v-if="!isMobileDrawer && collapsible" :text="collapsed ? 'Buka panel kanan' : 'Tutup panel kanan'">
        <UButton
          icon="i-lucide-chevron-right"
          size="xs"
          color="neutral"
          variant="ghost"
          :class="[
            'shrink-0 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
            collapsed ? 'rotate-180' : 'rotate-0'
          ]"
          @click="emit('update:collapsed', !collapsed)"
        />
      </UTooltip>
    </div>

    <Transition name="panel-fade" mode="out-in">
      <!-- Desktop Collapsed Rail: Info & Filter Quick Icons -->
      <div
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
      class="flex flex-col items-center pt-2.5 gap-2 flex-1 w-full"
    >
      <!-- Icon Tab Info -->
      <UTooltip text="Buka Properties" :content="{ side: 'left' }">
        <button
          type="button"
          class="relative p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          @click="handleCollapsedClick('info')"
        >
          <UIcon name="i-lucide-info" class="size-4" />
          <span
            v-if="selectedFeature"
            class="absolute top-1 right-1 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#0b0f19]"
            title="Ruas terpilih"
          />
        </button>
      </UTooltip>

      <!-- Icon Tab Filter -->
      <UTooltip text="Buka Filter Atribut" :content="{ side: 'left' }">
        <button
          type="button"
          class="relative p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          @click="handleCollapsedClick('filter')"
        >
          <UIcon name="i-lucide-filter" class="size-4" />
          <span
            v-if="hasActiveFilter"
            class="absolute top-1 right-1 size-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#0b0f19]"
            title="Filter aktif"
          />
        </button>
      </UTooltip>
    </div>

    <!-- Content: Body Area -->
    <div
      v-else
      key="body"
      class="flex-1 overflow-y-auto"
    >
      <Transition name="tab-fade" mode="out-in">
        <!-- TAB 1: PROPERTIES / INFO CONTENT -->
        <div v-if="currentTab === 'info'" key="info" class="h-full flex flex-col">
        <!-- Empty State: Belum ada feature terpilih -->
        <div
          v-if="!selectedFeature"
          class="flex flex-col items-center justify-center h-full gap-2.5 px-4 py-8 text-center text-gray-400"
        >
          <div class="size-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500">
            <UIcon name="i-lucide-mouse-pointer-click" class="size-5" />
          </div>
          <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Tidak Ada Data Terpilih</p>
          <p class="text-[11px] text-gray-400 max-w-[220px] leading-relaxed">
            Pilih data pada peta atau baris di tabel atribut untuk melihat detail properties.
          </p>
        </div>

        <!-- Selected Feature Detail Properties -->
        <div v-else class="p-3 space-y-3">
          <!-- Action Buttons -->
          <div class="flex items-center gap-1.5">
            <UButton
              icon="i-lucide-map-pin"
              label="Zoom"
              size="xs"
              color="primary"
              variant="subtle"
              class="flex-1 justify-center cursor-pointer"
              @click="emit('zoomToFeature', selectedFeature)"
            />
            <UButton
              icon="i-lucide-pencil"
              label="Edit"
              size="xs"
              color="neutral"
              variant="subtle"
              class="flex-1 justify-center cursor-pointer"
              @click="emit('editFeature', selectedFeature)"
            />
            <UButton
              icon="i-lucide-scissors"
              size="xs"
              color="neutral"
              variant="subtle"
              title="Split / Pecah Ruas"
              class="cursor-pointer text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40"
              @click="emit('splitFeature', selectedFeature)"
            />
            <UButton
              icon="i-lucide-trash-2"
              size="xs"
              color="error"
              variant="subtle"
              title="Hapus ruas"
              class="cursor-pointer"
              @click="emit('deleteFeature', selectedFeature)"
            />
            <UButton
              icon="i-lucide-table"
              size="xs"
              color="neutral"
              variant="ghost"
              title="Lihat di tabel"
              class="cursor-pointer"
              @click="emit('viewInTable', selectedFeature)"
            />
          </div>

          <!-- Detail Properties List -->
          <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 overflow-hidden divide-y divide-gray-100 dark:divide-gray-800/60">
            <div
              v-for="[key, val] in [
                ['Nama Ruas', selectedFeature.properties.nama_ruas],
                ['Kode Ruas', selectedFeature.properties.kode_ruas],
                ['Desa', selectedFeature.properties.desa],
                ['Kecamatan', selectedFeature.properties.kecamatan],
                ['Panjang', (selectedFeature.properties.panjang_meter != null && selectedFeature.properties.panjang_meter !== '') ? `${Number(selectedFeature.properties.panjang_meter).toLocaleString('id-ID', { maximumFractionDigits: 2 })} m` : (selectedFeature.properties.panjang != null && selectedFeature.properties.panjang !== '' ? `${Number(selectedFeature.properties.panjang).toLocaleString('id-ID', { maximumFractionDigits: 2 })} m` : '-')],
                ['Lebar', selectedFeature.properties.lebar != null ? `${selectedFeature.properties.lebar} m` : '-'],
                ['Perkerasan', selectedFeature.properties.perkerasan],
                ['Kondisi', selectedFeature.properties.kondisi],
                ['Status Awal', selectedFeature.properties.status_awal],
                ['Status Eksisting', selectedFeature.properties.status_eksisting],
                ['Sumber Data', selectedFeature.properties.sumber_data],
                ['ID Ruas', selectedFeature.properties.id],
              ]"
              :key="key"
              class="flex items-start justify-between px-3 py-1.5 text-xs"
            >
              <span class="text-gray-500 dark:text-gray-400 font-mono w-[38%] shrink-0">{{ key }}</span>
              <span class="text-gray-800 dark:text-gray-200 font-mono text-right break-all">{{ val !== null && val !== undefined && val !== '' ? val : '-' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: FILTER ATRIBUT CONTENT -->
      <div v-else-if="currentTab === 'filter'" key="filter" class="p-3 space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Kriteria Filter
            </span>
            <UBadge
              v-if="hasActiveFilter"
              :label="activeFiltersCount + ' aktif'"
              size="xs"
              color="primary"
              variant="subtle"
              class="font-mono text-[9px] px-1 py-0 h-4 leading-none"
            />
          </div>
          <UButton
            v-if="hasActiveFilter"
            label="Reset Semua"
            size="xs"
            color="primary"
            variant="link"
            class="p-0 text-xs h-auto cursor-pointer"
            @click="emit('resetFilters')"
          />
        </div>

        <!-- Sub-group 1: Wilayah Administratif -->
        <div class="space-y-2.5 p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800/60">
          <div class="flex items-center gap-1.5 text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-0.5">
            <UIcon name="i-lucide-map-pin" class="size-3 text-emerald-600 dark:text-emerald-400" />
            <span>Wilayah Administratif</span>
          </div>

          <UFormField label="Kecamatan" size="sm">
            <USelectMenu
              :model-value="kecamatanFilter || 'ALL'"
              :items="kecamatanSelectItems"
              value-key="value"
              label-key="label"
              placeholder="Pilih atau cari kecamatan..."
              :ui="{ content: 'z-[100]' }"
              size="xs"
              class="w-full"
              @update:model-value="handleKecamatanChange"
            />
          </UFormField>

          <UFormField
            :label="selectedKecamatanObj ? `Desa (${selectedKecamatanObj.nama})` : 'Desa / Kelurahan'"
            size="sm"
          >
            <USelectMenu
              :model-value="desaFilter || 'ALL'"
              :items="desaSelectItems"
              value-key="value"
              label-key="label"
              :placeholder="selectedKecamatanObj ? `Cari desa di ${selectedKecamatanObj.nama}...` : 'Cari desa...'"
              :ui="{ content: 'z-[100]' }"
              size="xs"
              class="w-full"
              @update:model-value="handleDesaChange"
            />
          </UFormField>
        </div>

        <!-- Sub-group 2: Kondisi & Perkerasan -->
        <div class="space-y-2.5 p-2.5 rounded-lg bg-gray-50/70 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800/60">
          <div class="flex items-center gap-1.5 text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-0.5">
            <UIcon name="i-lucide-activity" class="size-3 text-emerald-600 dark:text-emerald-400" />
            <span>Kondisi & Perkerasan</span>
          </div>

          <UFormField label="Kondisi Jalan" size="sm">
            <USelectMenu
              :model-value="kondisiFilter || 'ALL'"
              :items="kondisiSelectItems"
              value-key="value"
              label-key="label"
              placeholder="Semua Kondisi"
              :ui="{ content: 'z-[100]' }"
              size="xs"
              class="w-full"
              @update:model-value="emit('update:kondisiFilter', !$event || $event === 'ALL' ? null : String($event))"
            />
          </UFormField>

          <UFormField label="Tipe Perkerasan" size="sm">
            <USelectMenu
              :model-value="perkerasanFilter || 'ALL'"
              :items="perkerasanSelectItems"
              value-key="value"
              label-key="label"
              placeholder="Semua Perkerasan"
              :ui="{ content: 'z-[100]' }"
              size="xs"
              class="w-full"
              @update:model-value="emit('update:perkerasanFilter', !$event || $event === 'ALL' ? null : String($event))"
            />
          </UFormField>
        </div>

        <!-- Action Buttons for Filter -->
        <div class="pt-1 flex items-center gap-2">
          <UButton
            label="Fokus Wilayah"
            icon="i-lucide-maximize-2"
            size="xs"
            color="primary"
            variant="subtle"
            class="flex-1 justify-center cursor-pointer"
            @click="emit('applyFilter')"
          />
          <UButton
            label="Buka di Tabel"
            icon="i-lucide-table"
            size="xs"
            color="neutral"
            variant="subtle"
            class="flex-1 justify-center cursor-pointer"
            @click="emit('viewInTable')"
          />
        </div>
      </div>
        </Transition>
      </div>
    </Transition>
  </component>
</template>

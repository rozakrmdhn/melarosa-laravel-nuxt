<script lang="ts" setup>
interface OptionItem {
  label: string;
  value: string;
}

interface ActiveFilterChip {
  id: string;
  label: string;
  clear: () => void;
}

const search = defineModel<string>("search", { default: "" });
const selectedModule = defineModel<string>("module", { default: "ALL" });
const selectedEvent = defineModel<string>("event", { default: "ALL" });
const selectedUserId = defineModel<string>("userId", { default: "ALL" });
const dateFrom = defineModel<string>("dateFrom", { default: "" });
const dateTo = defineModel<string>("dateTo", { default: "" });
const selectedDatePreset = defineModel<"all" | "today" | "7days" | "30days">("datePreset", { default: "all" });

const props = withDefaults(
  defineProps<{
    moduleOptions: OptionItem[];
    eventOptions: OptionItem[];
    userOptions: OptionItem[];
    loading?: boolean;
    activeFiltersCount?: number;
    activeFilterChips?: ActiveFilterChip[];
  }>(),
  {
    loading: false,
    activeFiltersCount: 0,
    activeFilterChips: () => [],
  }
);

const emit = defineEmits<{
  (e: "refresh"): void;
  (e: "reset"): void;
}>();

const dayjs = useDayjs();
const showMobileFilters = ref(false);

function setDatePreset(type: "today" | "7days" | "30days" | "all") {
  selectedDatePreset.value = type;
  if (type === "today") {
    dateFrom.value = dayjs().format("YYYY-MM-DD");
    dateTo.value = dayjs().format("YYYY-MM-DD");
  } else if (type === "7days") {
    dateFrom.value = dayjs().subtract(7, "day").format("YYYY-MM-DD");
    dateTo.value = dayjs().format("YYYY-MM-DD");
  } else if (type === "30days") {
    dateFrom.value = dayjs().subtract(30, "day").format("YYYY-MM-DD");
    dateTo.value = dayjs().format("YYYY-MM-DD");
  } else {
    dateFrom.value = "";
    dateTo.value = "";
  }
}

function clearDateRange() {
  dateFrom.value = "";
  dateTo.value = "";
}

function handleReset() {
  emit("reset");
}
</script>

<template>
  <div class="space-y-2.5">
    <div class="bg-white dark:bg-[#0b0f19] border border-gray-200/80 dark:border-white/[0.08] rounded-xl p-3 shadow-xs space-y-3">
      <!-- Top Toolbar Row: Search + Quick Date Pills + Actions -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <!-- Search Bar -->
        <div class="flex-1 min-w-0">
          <UInput
            v-model="search"
            icon="i-lucide-search"
            placeholder="Cari target data, deskripsi, atau IP..."
            size="sm"
            class="w-full h-8"
            :ui="{ base: 'h-8 text-xs' }"
          />
        </div>

        <!-- Quick Date Presets (Segmented Pill Buttons on tablet/desktop, synced to h-8) -->
        <div class="hidden sm:inline-flex items-center h-8 p-0.5 rounded-lg bg-gray-100 dark:bg-gray-800/80 border border-gray-200/80 dark:border-gray-700/60 text-xs shrink-0">
          <button
            type="button"
            class="h-full px-2.5 inline-flex items-center justify-center rounded-md text-[11px] font-medium transition-colors cursor-pointer"
            :class="selectedDatePreset === 'all' && !dateFrom && !dateTo ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setDatePreset('all')"
          >
            Semua
          </button>
          <button
            type="button"
            class="h-full px-2.5 inline-flex items-center justify-center rounded-md text-[11px] font-medium transition-colors cursor-pointer"
            :class="selectedDatePreset === 'today' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setDatePreset('today')"
          >
            Hari Ini
          </button>
          <button
            type="button"
            class="h-full px-2.5 inline-flex items-center justify-center rounded-md text-[11px] font-medium transition-colors cursor-pointer"
            :class="selectedDatePreset === '7days' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setDatePreset('7days')"
          >
            7 Hari
          </button>
          <button
            type="button"
            class="h-full px-2.5 inline-flex items-center justify-center rounded-md text-[11px] font-medium transition-colors cursor-pointer"
            :class="selectedDatePreset === '30days' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setDatePreset('30days')"
          >
            30 Hari
          </button>
        </div>

        <!-- Action Buttons (Height synced to h-8) -->
        <div class="flex items-center gap-1.5 shrink-0 justify-end">
          <!-- Mobile Filter Toggle (Visible on < sm) -->
          <UButton
            class="sm:hidden h-8"
            icon="i-lucide-sliders-horizontal"
            :label="showMobileFilters ? 'Sembunyikan' : 'Filter'"
            color="neutral"
            variant="outline"
            size="sm"
            @click="showMobileFilters = !showMobileFilters"
          >
            <template v-if="activeFiltersCount > 0" #trailing>
              <span class="size-4.5 rounded-full bg-primary-500 text-white text-[10px] font-bold flex items-center justify-center">
                {{ activeFiltersCount }}
              </span>
            </template>
          </UButton>

          <!-- Refresh Button -->
          <UButton
            class="h-8"
            icon="i-lucide-rotate-cw"
            color="neutral"
            variant="outline"
            size="sm"
            :loading="loading"
            title="Segarkan data"
            @click="emit('refresh')"
          />

          <!-- Reset Button -->
          <UButton
            v-if="activeFiltersCount > 0"
            class="h-8 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
            icon="i-lucide-filter-x"
            label="Reset"
            color="neutral"
            variant="ghost"
            size="sm"
            @click="handleReset"
          />
        </div>
      </div>

      <!-- Filter Grid: Always visible on tablet & desktop (sm:block), toggleable on mobile (< sm) -->
      <div
        :class="[
          'pt-2.5 border-t border-gray-100 dark:border-gray-800/80 transition-all',
          showMobileFilters || activeFiltersCount > 0 ? 'block' : 'hidden sm:block'
        ]"
      >
        <!-- Mobile Date Presets (visible only on < sm) -->
        <div class="sm:hidden mb-2.5 flex items-center gap-1 overflow-x-auto pb-1 scrollbar-none">
          <span class="text-[11px] text-gray-500 dark:text-gray-400 shrink-0 mr-1">Waktu:</span>
          <button
            type="button"
            class="h-7 px-2.5 inline-flex items-center justify-center rounded text-[11px] font-medium border shrink-0 transition-colors"
            :class="selectedDatePreset === 'all' && !dateFrom && !dateTo ? 'bg-primary-500 text-white border-primary-500' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
            @click="setDatePreset('all')"
          >
            Semua
          </button>
          <button
            type="button"
            class="h-7 px-2.5 inline-flex items-center justify-center rounded text-[11px] font-medium border shrink-0 transition-colors"
            :class="selectedDatePreset === 'today' ? 'bg-primary-500 text-white border-primary-500' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
            @click="setDatePreset('today')"
          >
            Hari Ini
          </button>
          <button
            type="button"
            class="h-7 px-2.5 inline-flex items-center justify-center rounded text-[11px] font-medium border shrink-0 transition-colors"
            :class="selectedDatePreset === '7days' ? 'bg-primary-500 text-white border-primary-500' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
            @click="setDatePreset('7days')"
          >
            7 Hari
          </button>
          <button
            type="button"
            class="h-7 px-2.5 inline-flex items-center justify-center rounded text-[11px] font-medium border shrink-0 transition-colors"
            :class="selectedDatePreset === '30days' ? 'bg-primary-500 text-white border-primary-500' : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
            @click="setDatePreset('30days')"
          >
            30 Hari
          </button>
        </div>

        <!-- Grid: 1 col on mobile, 2 cols on tablet (sm:), 4 cols on desktop (lg:), all form inputs strictly h-8 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
          <!-- Modul Dropdown -->
          <div>
            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Modul</label>
            <USelectMenu
              v-model="selectedModule"
              :items="moduleOptions"
              value-key="value"
              label-key="label"
              size="sm"
              icon="i-lucide-layers"
              class="w-full h-8"
              :ui="{ base: 'h-8 text-xs' }"
            />
          </div>

          <!-- Event / Aksi Dropdown -->
          <div>
            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Aksi / Event</label>
            <USelectMenu
              v-model="selectedEvent"
              :items="eventOptions"
              value-key="value"
              label-key="label"
              size="sm"
              icon="i-lucide-shield-alert"
              class="w-full h-8"
              :ui="{ base: 'h-8 text-xs' }"
            />
          </div>

          <!-- Pelaku Dropdown -->
          <div>
            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Pelaku (User)</label>
            <USelectMenu
              v-model="selectedUserId"
              :items="userOptions"
              value-key="value"
              label-key="label"
              size="sm"
              icon="i-lucide-user"
              class="w-full h-8"
              :ui="{ base: 'h-8 text-xs' }"
            />
          </div>

          <!-- Rentang Tanggal (Height matched to h-8) -->
          <div>
            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Rentang Tanggal</label>
            <div class="h-8 flex items-center justify-between gap-1.5 bg-gray-50 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800 rounded-md px-2.5 text-xs focus-within:ring-2 focus-within:ring-primary-500/20 focus-within:border-primary-500 transition-all">
              <UIcon name="i-lucide-calendar" class="size-3.5 text-gray-400 shrink-0" />
              <input
                v-model="dateFrom"
                type="date"
                class="h-full bg-transparent border-0 p-0 text-xs text-gray-900 dark:text-gray-100 outline-none w-full min-w-0"
                title="Tanggal Mulai"
              />
              <span class="text-gray-400 text-xs shrink-0 px-0.5">-</span>
              <input
                v-model="dateTo"
                type="date"
                class="h-full bg-transparent border-0 p-0 text-xs text-gray-900 dark:text-gray-100 outline-none w-full min-w-0 text-right"
                title="Tanggal Akhir"
              />
              <button
                v-if="dateFrom || dateTo"
                type="button"
                class="h-full flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors shrink-0 ml-1 cursor-pointer"
                title="Hapus filter tanggal"
                @click="clearDateRange"
              >
                <UIcon name="i-lucide-x" class="size-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Filter Chips Row (Reflows on Desktop & Mobile) -->
    <div
      v-if="activeFilterChips && activeFilterChips.length > 0"
      class="flex items-center gap-1.5 overflow-x-auto pb-1 px-1 scrollbar-thin flex-wrap sm:flex-nowrap"
    >
      <span class="text-[11px] text-gray-400 font-medium shrink-0 flex items-center gap-1">
        <UIcon name="i-lucide-filter" class="size-3" />
        <span>Filter Aktif ({{ activeFilterChips.length }}):</span>
      </span>

      <UBadge
        v-for="chip in activeFilterChips"
        :key="chip.id"
        color="neutral"
        variant="subtle"
        size="xs"
        class="pl-2 pr-1 py-0.5 flex items-center gap-1 font-mono text-[11px] shrink-0 border border-gray-200 dark:border-gray-800"
      >
        <span>{{ chip.label }}</span>
        <button
          type="button"
          class="hover:text-red-500 rounded p-0.5 cursor-pointer outline-none focus:ring-1 focus:ring-red-400"
          :title="`Hapus filter ${chip.label}`"
          @click="chip.clear"
        >
          <UIcon name="i-lucide-x" class="size-3" />
        </button>
      </UBadge>

      <button
        type="button"
        class="text-[11px] text-red-500 hover:text-red-600 dark:text-red-400 font-medium shrink-0 ml-1 underline cursor-pointer"
        @click="handleReset"
      >
        Reset Semua
      </button>
    </div>
  </div>
</template>

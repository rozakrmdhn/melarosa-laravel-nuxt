<script setup lang="ts">
import type { JalanPorosRef } from '~/types/infrastruktur';

interface Props {
  collapsed?: boolean;
  collapsible?: boolean;
  isMobileDrawer?: boolean;
  digitasiMethod?: 'manual' | 'auto';
  refMode?: 'with_ref' | 'without_ref';
  roadItems?: Array<{ label: string; value: string }>;
  selectedRoadId?: string | null;
  selectedRoadDetail?: { data: JalanPorosRef; feature: any } | null;
  roadLoading?: boolean;
  zoomLoading?: boolean;
  roadError?: string | null;
  canConfirm?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  collapsible: true,
  isMobileDrawer: false,
  digitasiMethod: 'manual',
  refMode: 'with_ref',
  roadItems: () => [],
  selectedRoadId: null,
  selectedRoadDetail: null,
  roadLoading: false,
  zoomLoading: false,
  roadError: null,
  canConfirm: false,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'update:digitasiMethod', val: 'manual' | 'auto'): void;
  (e: 'switchRefMode', val: 'with_ref' | 'without_ref'): void;
  (e: 'roadChange', val: string | null): void;
  (e: 'reloadRoads'): void;
  (e: 'refocusRoad'): void;
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
          <UIcon name="i-lucide-pen-tool" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            Mulai Digitasi
          </span>
          <UBadge
            label="Manual"
            size="xs"
            color="warning"
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
        <UIcon name="i-lucide-pen-tool" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
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
          <!-- 1. Metode Digitasi -->
          <div class="space-y-1.5">
            <p class="font-semibold text-gray-800 dark:text-gray-200">
              Metode Digitasi
            </p>
            <div
              role="radiogroup"
              aria-label="Metode digitasi segmen"
              class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/70 dark:border-gray-800"
            >
              <button
                type="button"
                role="radio"
                :aria-checked="digitasiMethod === 'manual'"
                aria-label="Digitasi Manual"
                class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[44px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-600"
                :class="digitasiMethod === 'manual'
                  ? 'bg-white dark:bg-[#0b0f19] text-blue-700 dark:text-blue-300 font-semibold shadow-xs ring-1 ring-blue-500/40 dark:ring-blue-500/50'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('update:digitasiMethod', 'manual')"
              >
                <UIcon name="i-lucide-pen-tool" class="size-3.5 shrink-0" />
                <span class="truncate">Manual</span>
              </button>

              <!-- TODO: Implementasi digitasi otomatis mengikuti geometri Jalan Poros Desa -->
              <button
                type="button"
                role="radio"
                :aria-checked="digitasiMethod === 'auto'"
                disabled
                aria-disabled="true"
                aria-label="Digitasi Otomatis, segera hadir"
                title="Digitasi otomatis segera hadir"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium min-h-[44px] text-gray-500 dark:text-gray-400 opacity-80 cursor-not-allowed select-none"
              >
                <UIcon name="i-lucide-wand-sparkles" class="size-3.5 shrink-0" />
                <span class="truncate">Otomatis</span>
                <span class="text-[9px] px-1 py-0.5 rounded bg-gray-200/80 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium leading-none shrink-0">
                  Segera
                </span>
              </button>
            </div>
          </div>

          <!-- 2. Referensi Jalan Poros Desa -->
          <div class="space-y-3 pt-2.5 border-t border-gray-100 dark:border-gray-800">
            <p class="font-semibold text-gray-800 dark:text-gray-200">
              Referensi Jalan Poros Desa
            </p>

            <div
              role="radiogroup"
              aria-label="Mode referensi jalan poros desa"
              class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/70 dark:border-gray-800"
            >
              <button
                type="button"
                role="radio"
                :aria-checked="refMode === 'with_ref'"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px]"
                :class="refMode === 'with_ref'
                  ? 'bg-white dark:bg-[#0b0f19] text-sky-700 dark:text-sky-300 font-semibold shadow-xs ring-1 ring-sky-200 dark:ring-sky-800'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('switchRefMode', 'with_ref')"
              >
                <UIcon name="i-lucide-route" class="size-3.5 shrink-0" />
                <span class="truncate">Jalan Poros</span>
              </button>

              <button
                type="button"
                role="radio"
                :aria-checked="refMode === 'without_ref'"
                class="flex items-center justify-center gap-1 py-2 px-2 rounded-lg text-[11px] font-medium transition-colors cursor-pointer min-h-[40px]"
                :class="refMode === 'without_ref'
                  ? 'bg-white dark:bg-[#0b0f19] text-gray-900 dark:text-white font-semibold shadow-xs ring-1 ring-gray-200 dark:ring-gray-700'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="emit('switchRefMode', 'without_ref')"
              >
                <UIcon name="i-lucide-git-commit-horizontal" class="size-3.5 shrink-0" />
                <span class="truncate">Tanpa Ref</span>
              </button>
            </div>

            <template v-if="refMode === 'with_ref'">
              <UFormField
                label="Pilih Ruas Jalan Poros Desa"
                required
                hint="Cari dari daftar atau klik langsung ruas jalan poros di peta."
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
                Tidak ditemukan data ruas jalan poros desa untuk filter wilayah aktif. Sesuaikan filter wilayah di topbar atau pilih mode Tanpa Ref.
              </div>

              <div
                v-if="selectedRoadDetail"
                class="p-2.5 rounded-lg bg-sky-50/80 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/80 space-y-2"
              >
                <div class="min-w-0 space-y-0.5">
                  <p class="font-semibold text-sky-900 dark:text-sky-100 break-words">
                    {{ formatSelectedRoadLabel(selectedRoadDetail.data) }}
                  </p>
                  <p class="text-[11px] text-sky-700 dark:text-sky-300">
                    Kec. {{ selectedRoadDetail.data.kecamatan || '-' }} · Panjang:
                    <span class="font-mono font-semibold tabular-nums">
                      {{ Number(selectedRoadDetail.data.panjang ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} m
                    </span>
                  </p>
                </div>

                <UButton
                  icon="i-lucide-crosshair"
                  label="Fokus Ulang di Peta"
                  size="xs"
                  color="info"
                  variant="subtle"
                  class="cursor-pointer w-full justify-center"
                  @click="emit('refocusRoad')"
                />
              </div>
            </template>

            <div
              v-else
              class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 text-[11px] leading-relaxed"
            >
              Segmen akan disimpan sebagai segmen mandiri tanpa induk jalan poros desa (<code class="font-mono">parent_id = null</code>). Cocok untuk gang permukiman baru, drainase, atau jembatan di luar koridor poros.
            </div>
          </div>
        </div>

        <!-- Sticky Footer CTA -->
        <div
          class="shrink-0 p-3 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b0f19] flex items-center gap-2"
          :class="isMobileDrawer ? 'pb-[max(0.75rem,env(safe-area-inset-bottom))]' : ''"
        >
          <UButton
            label="Batal"
            color="neutral"
            variant="ghost"
            size="sm"
            class="cursor-pointer min-h-[38px]"
            @click="emit('cancel')"
          />
          <UButton
            label="Mulai Digitasi Manual"
            icon="i-lucide-pen-tool"
            color="primary"
            variant="solid"
            size="sm"
            class="cursor-pointer flex-1 justify-center min-h-[38px]"
            :disabled="!canConfirm"
            @click="emit('confirm')"
          />
        </div>
      </div>
    </Transition>
  </component>
</template>

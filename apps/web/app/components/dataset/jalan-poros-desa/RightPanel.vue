<script setup lang="ts">
import type { SelectedFeature } from "~/types/dataset-editor";

const props = withDefaults(
  defineProps<{
    collapsed?: boolean;
    width?: number;
    selectedFeature?: SelectedFeature | null;
    loading?: boolean;
    isMobileDrawer?: boolean;
    collapsible?: boolean;
    clickedCoordinate?: [number, number] | null;
    canEdit?: boolean;
    canSplit?: boolean;
    canDelete?: boolean;
  }>(),
  {
    collapsed: false,
    width: 380,
    selectedFeature: null,
    loading: false,
    isMobileDrawer: false,
    collapsible: true,
    clickedCoordinate: null,
    canEdit: true,
    canSplit: true,
    canDelete: true,
  }
);

const copied = ref(false);
let copyTimer: any = null;

function fallbackCopy(text: string) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.opacity = "0";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand("copy");
  } catch (err) {
    console.error("Gagal menyalin:", err);
  }
  document.body.removeChild(textArea);
}

function copyCoords() {
  if (!props.clickedCoordinate) return;
  const [lng, lat] = props.clickedCoordinate;
  const text = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }
  copied.value = true;
  clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    copied.value = false;
  }, 1800);
}

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "zoomToFeature", feature: SelectedFeature): void;
  (e: "editFeature", feature: SelectedFeature): void;
  (e: "splitFeature", feature: SelectedFeature): void;
  (e: "deleteFeature", feature: SelectedFeature): void;
  (e: "viewInTable", feature?: SelectedFeature): void;
}>();
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col overflow-hidden w-full h-full',
      'bg-white dark:bg-[#0b0f19]'
    ]"
  >
    <!-- Header: Title & Collapse Button -->
    <div class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 gap-2 justify-between select-none">
      <Transition name="panel-subtle-fade">
        <div v-if="isMobileDrawer || !collapsed" class="flex items-center gap-2 overflow-hidden">
          <UIcon name="i-lucide-info" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            Detail Data
          </span>
          <UBadge
            v-if="selectedFeature"
            :label="selectedFeature.properties?.kode_ruas ? `Kode: ${selectedFeature.properties.kode_ruas}` : 'Terpilih'"
            size="xs"
            color="primary"
            variant="subtle"
            class="text-[9px] px-1 py-0 h-4 leading-none shrink-0 font-mono"
          />
        </div>
      </Transition>

      <!-- Desktop Collapse Toggle Button -->
      <UTooltip v-if="!isMobileDrawer && collapsible" :text="collapsed ? 'Buka panel detail' : 'Tutup panel detail'">
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
      <!-- Desktop Collapsed Rail: Detail Icon -->
      <div
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
        class="flex flex-col items-center pt-3 gap-3 flex-1 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors"
        title="Klik untuk membuka detail ruas"
        @click="emit('update:collapsed', false)"
      >
        <UTooltip text="Detail Ruas Jalan" :content="{ side: 'left' }">
          <div class="relative p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon
              name="i-lucide-info"
              :class="[
                'size-4 transition-colors',
                selectedFeature ? 'text-blue-500' : 'text-gray-400 dark:text-gray-500'
              ]"
            />
            <span
              v-if="selectedFeature"
              class="absolute top-1 right-1 size-1.5 rounded-full bg-blue-500 ring-1 ring-white dark:ring-[#0b0f19]"
            />
          </div>
        </UTooltip>
      </div>

      <!-- Content: Body Area -->
      <div
        v-else
        key="body"
        class="flex-1 flex flex-col min-h-0 overflow-hidden"
      >
        <div class="flex-1 overflow-y-auto min-h-0">
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
            Pilih garis ruas jalan pada peta atau baris di tabel atribut untuk menginspeksi detail properties.
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
              v-if="canEdit"
              icon="i-lucide-pencil"
              label="Edit"
              size="xs"
              color="neutral"
              variant="subtle"
              class="flex-1 justify-center cursor-pointer"
              @click="emit('editFeature', selectedFeature)"
            />
            <UButton
              v-if="canSplit"
              icon="i-lucide-scissors"
              size="xs"
              color="neutral"
              variant="subtle"
              title="Split / Pecah Ruas"
              class="cursor-pointer text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40"
              @click="emit('splitFeature', selectedFeature)"
            />
            <UButton
              v-if="canDelete"
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

          <!-- Info banner if feature cannot be edited or deleted by user -->
          <div
            v-if="!canEdit && !canSplit && !canDelete"
            class="text-[11px] text-gray-500 dark:text-gray-400 italic p-2 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-800/80 flex items-center gap-1.5"
          >
            <UIcon name="i-lucide-info" class="size-3.5 text-amber-500 shrink-0" />
            <span>Mode baca: Ruas jalan ini di luar wewenang wilayah Anda.</span>
          </div>

          <!-- Detail Properties List -->
          <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 overflow-hidden divide-y divide-gray-100 dark:divide-gray-800/60 border border-gray-100 dark:border-gray-800/80">
            <div
              v-for="[key, val] in [
                ['Nama Ruas', selectedFeature.properties.nama_ruas],
                ['Kode Ruas', selectedFeature.properties.kode_ruas],
                ['Desa', selectedFeature.properties.desa],
                ['Kecamatan', selectedFeature.properties.kecamatan],
                ['Panjang', (Number(selectedFeature.properties.panjang_meter || selectedFeature.properties.panjang) > 0) ? `${Number(selectedFeature.properties.panjang_meter || selectedFeature.properties.panjang).toLocaleString('id-ID', { maximumFractionDigits: 1 })} m` : '-'],
                ['Lebar', (Number(selectedFeature.properties.lebar) > 0) ? `${Number(selectedFeature.properties.lebar).toLocaleString('id-ID', { maximumFractionDigits: 1 })} m` : '-'],
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

        <!-- Footer: Koordinat Klik Map Canvas (Simple Minimalist) -->
        <div class="border-t border-gray-200 dark:border-gray-800 shrink-0 px-3 py-2 bg-gray-50/50 dark:bg-gray-900/30">
          <div
            v-if="clickedCoordinate"
            class="flex items-center justify-between gap-2 text-xs"
          >
            <div class="flex items-center gap-1.5 font-mono text-[11px] text-gray-700 dark:text-gray-300 min-w-0">
              <span class="truncate">
                <span class="text-gray-400 dark:text-gray-500 font-sans text-[10px] mr-1">Lat</span>{{ clickedCoordinate[1].toFixed(6) }},
                <span class="text-gray-400 dark:text-gray-500 font-sans text-[10px] mr-1 ml-1.5">Long</span>{{ clickedCoordinate[0].toFixed(6) }}
              </span>
            </div>

            <UTooltip :text="copied ? 'Tersalin!' : 'Salin koordinat'">
              <UButton
                :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'"
                size="xs"
                :color="copied ? 'primary' : 'neutral'"
                variant="ghost"
                class="cursor-pointer shrink-0 h-6 px-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                @click="copyCoords"
              />
            </UTooltip>
          </div>

          <div
            v-else
            class="flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500 py-0.5"
          >
            <UIcon name="i-lucide-mouse-pointer-click" class="size-3.5 shrink-0 opacity-50" />
            <span class="italic text-[11px]">Klik peta untuk mengambil koordinat</span>
          </div>
        </div>
      </div>
    </Transition>
  </component>
</template>

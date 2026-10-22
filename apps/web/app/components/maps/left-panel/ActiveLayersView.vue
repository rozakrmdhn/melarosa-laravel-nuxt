<script setup lang="ts">
import type { ActiveLayerItem } from "~/types/map-layers";

const props = defineProps<{
  activeLayers: ActiveLayerItem[];
  clickedCoordinate?: [number, number] | null;
  inspectorOpen?: boolean;
  universalSearchQuery?: string;
  filteredActiveLayers: ActiveLayerItem[];
}>();

const emit = defineEmits<{
  (e: "select-layer", layerId: string): void;
  (e: "toggle-layer-visibility", layerId: string): void;
  (e: "reorder-layers", layers: ActiveLayerItem[]): void;
  (e: "reset-wilayah"): void;
  (e: "go-to-geoportal"): void;
  (e: "go-to-identify"): void;
  (e: "clear-search"): void;
}>();

const draggedLayerIndex = ref<number | null>(null);
const recentlyMovedLayerId = ref<string | null>(null);

function onDragStart(index: number, event: DragEvent) {
  draggedLayerIndex.value = index;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(index));
  }
}

function onDragOver(event: DragEvent) {
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = "move";
  }
}

function onDrop(dropIndex: number) {
  if (draggedLayerIndex.value === null || draggedLayerIndex.value === dropIndex) return;
  const list = [...props.activeLayers];
  const [removed] = list.splice(draggedLayerIndex.value, 1);
  if (!removed) return;
  list.splice(dropIndex, 0, removed);

  recentlyMovedLayerId.value = removed.id;
  setTimeout(() => {
    recentlyMovedLayerId.value = null;
  }, 900);

  draggedLayerIndex.value = null;
  emit("reorder-layers", list);
}
</script>

<template>
  <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-3 space-y-3">
    <!-- Subheader urutan layer -->
    <div v-if="props.activeLayers.length > 0" class="flex items-center justify-between px-1">
      <div class="flex items-center gap-1.5">
        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Urutan Layer</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold tabular-nums bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300">
          {{ props.activeLayers.length }}
        </span>
      </div>
      <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium flex items-center gap-1">
        <UIcon name="i-lucide-arrow-up-down" class="size-3" />
        <span>Atas = Paling Depan</span>
      </span>
    </div>

    <!-- State belum ada layer aktif -->
    <div
      v-if="props.activeLayers.length === 0"
      class="p-5 rounded-2xl border border-dashed border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] text-center space-y-3.5"
    >
      <div class="size-11 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center shadow-xs">
        <UIcon name="i-lucide-layers-2" class="size-5.5" />
      </div>
      <div class="space-y-1">
        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Belum Ada Layer Aktif</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed px-1">
          Tambahkan layer tematik dari katalog data spasial untuk dianalisis di atas peta Bojonegoro.
        </p>
      </div>
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer"
        @click="emit('go-to-geoportal')"
      >
        Jelajahi Katalog Geoportal
      </button>
    </div>

    <!-- State hasil filter kueri tidak ditemukan -->
    <div
      v-else-if="props.universalSearchQuery && props.filteredActiveLayers.length === 0"
      class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] text-center space-y-2"
    >
      <p class="text-xs text-slate-600 dark:text-slate-400">
        Tidak ada layer aktif yang cocok dengan "{{ props.universalSearchQuery }}"
      </p>
      <button
        type="button"
        class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline cursor-pointer min-h-[32px] px-2"
        @click="emit('clear-search')"
      >
        Bersihkan pencarian
      </button>
    </div>

    <!-- Daftar kartu layer aktif -->
    <TransitionGroup
      v-else
      name="layer-card"
      tag="div"
      class="space-y-2 relative"
    >
      <div
        v-for="(layer, index) in props.filteredActiveLayers"
        :key="layer.id"
        :draggable="!props.universalSearchQuery"
        class="rounded-xl border transition-all duration-200 overflow-hidden"
        :class="[
          layer.visible
            ? 'border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0c1424] shadow-xs'
            : 'border-slate-200/50 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] opacity-75',
          draggedLayerIndex === index ? 'opacity-40 border-dashed border-blue-500 ring-2 ring-blue-500/30' : '',
          recentlyMovedLayerId === layer.id ? 'ring-2 ring-blue-500/70 border-blue-500 bg-blue-50/40 dark:bg-blue-950/30 shadow-md shadow-blue-500/10' : ''
        ]"
        @dragstart="onDragStart(index, $event)"
        @dragover="onDragOver($event)"
        @drop="onDrop(index)"
        @dragend="draggedLayerIndex = null"
      >
        <div
          class="flex items-center justify-between px-2.5 py-2 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-white/[0.03] transition-colors"
          @click="emit('select-layer', layer.id)"
        >
          <div class="flex items-center gap-2 min-w-0 pr-1 flex-1">
            <div
              class="flex items-center gap-1 shrink-0 text-slate-400 cursor-grab active:cursor-grabbing"
              @click.stop
            >
              <UIcon name="i-lucide-grip-vertical" class="size-3 text-slate-300 dark:text-slate-600" />
              <span class="text-[9px] font-semibold tabular-nums px-1 py-0.2 rounded bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400">
                #{{ index + 1 }}
              </span>
            </div>

            <span
              class="size-2 rounded-full shrink-0 transition-colors"
              :class="layer.visible ? 'bg-blue-500 ring-2 ring-blue-500/20' : 'bg-slate-300 dark:bg-slate-600'"
            />

            <div class="min-w-0 flex-1">
              <span
                class="block text-xs font-semibold truncate tracking-tight"
                :class="layer.visible ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500'"
              >
                {{ layer.title || layer.name }}
              </span>
              <span class="block text-[10px] text-slate-400 dark:text-slate-500 truncate">
                {{ layer.instansi || layer.attribution || layer.agency || layer.protocol || 'WMS' }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-0.5 shrink-0" @click.stop>
            <UTooltip :text="layer.visible ? 'Sembunyikan' : 'Tampilkan'">
              <button
                type="button"
                class="size-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
                :class="layer.visible
                  ? 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40'
                  : 'text-slate-400 dark:text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-white/5'"
                aria-label="Toggle visibilitas layer"
                @click="emit('toggle-layer-visibility', layer.id)"
              >
                <UIcon :name="layer.visible ? 'i-lucide-eye' : 'i-lucide-eye-off'" class="size-4" />
              </button>
            </UTooltip>
            <UIcon name="i-lucide-chevron-right" class="size-3.5 text-slate-400 dark:text-slate-500" />
          </div>
        </div>
      </div>
    </TransitionGroup>

    <!-- Pintasan tematik & eksplorasi spasial -->
    <div class="pt-2 border-t border-slate-100 dark:border-white/5 space-y-2">
      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-1">
        Eksplorasi Spasial
      </p>

      <!-- Card 1: Katalog Geoportal -->
      <div
        class="p-2.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-gradient-to-br from-blue-50/60 to-indigo-50/30 dark:from-blue-950/20 dark:to-indigo-950/10 hover:border-blue-400/60 dark:hover:border-blue-500/40 transition-all cursor-pointer group shadow-xs"
        @click="emit('go-to-geoportal')"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="size-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
              <UIcon name="i-lucide-globe-2" class="size-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                  Katalog Dataset
                </span>
                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300">
                  Resmi
                </span>
              </div>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
                Akses 50+ layer spasial tematik Bojonegoro
              </p>
            </div>
          </div>
          <UIcon name="i-lucide-chevron-right" class="size-4 text-slate-400 group-hover:text-blue-600 dark:hover:text-blue-400 transition-colors shrink-0" />
        </div>
      </div>

      <!-- Card 2: Pusatkan Peta Bojonegoro -->
      <button
        type="button"
        class="w-full flex items-center justify-between p-2.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0c1424] hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-all cursor-pointer group text-left min-h-[44px]"
        @click="emit('reset-wilayah')"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="size-8 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 flex items-center justify-center shrink-0 group-hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
            <UIcon name="i-lucide-crosshair" class="size-4" />
          </div>
          <div class="min-w-0">
            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 block truncate">
              Pusatkan Peta ke Bojonegoro
            </span>
            <span class="text-[10px] text-slate-400 dark:text-slate-500 block truncate">
              Reset zoom & filter kecamatan
            </span>
          </div>
        </div>
        <UIcon name="i-lucide-arrow-right" class="size-3.5 text-slate-400 group-hover:translate-x-0.5 transition-transform shrink-0" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.layer-card-move {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.layer-card-enter-active,
.layer-card-leave-active {
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.layer-card-enter-from,
.layer-card-leave-to {
  opacity: 0;
  transform: translateY(8px) scale(0.98);
}
</style>

<script setup lang="ts">
import { computed, ref } from "vue";
import type { ActiveLayerItem } from "~/types/map-layers";
import { useGeoportalLayers, type GeoportalLayerItem } from "~/composables/useGeoportalLayers";

const props = withDefaults(
  defineProps<{
    activeLayers?: ActiveLayerItem[];
    show3dBuildings?: boolean;
    show3dTerrain?: boolean;
    terrainExaggeration?: number;
  }>(),
  {
    activeLayers: () => [],
    show3dBuildings: false,
    show3dTerrain: false,
    terrainExaggeration: 1.5,
  }
);

const emit = defineEmits<{
  (e: "back"): void;
  (e: "toggle-layer-visibility", layerId: string): void;
  (e: "update-layer-opacity", payload: { layerId: string; opacity: number }): void;
  (e: "toggle-geoportal", payload: { layer: GeoportalLayerItem; active: boolean }): void;
  (e: "reorder-layers", layers: ActiveLayerItem[]): void;
  (e: "toggle-3d-buildings"): void;
  (e: "toggle-3d-terrain"): void;
  (e: "update-terrain-exaggeration", exaggeration: number): void;
}>();

const {
  allLayers: geoportalLayers,
  loading: loadingGeoportal,
  error: errorGeoportal,
  refresh: refreshGeoportal,
} = useGeoportalLayers();

const filterText = ref("");
const isReorderMode = ref(false);
const dragHandleIndex = ref<number | null>(null);
const draggedIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

function enableDragHandle(index: number) {
  dragHandleIndex.value = index;
}

function disableDragHandle() {
  dragHandleIndex.value = null;
}

function moveLayer(index: number, delta: -1 | 1) {
  const list = [...(props.activeLayers || [])];
  const target = index + delta;
  if (target < 0 || target >= list.length) return;
  const [moved] = list.splice(index, 1);
  if (!moved) return;
  list.splice(target, 0, moved);
  emit("reorder-layers", list);
}

function handleDragStart(index: number, event: DragEvent) {
  if (dragHandleIndex.value !== index) {
    event.preventDefault();
    return;
  }
  draggedIndex.value = index;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(index));
  }
}

function handleDragOver(index: number, event: DragEvent) {
  event.preventDefault();
  if (draggedIndex.value === null || draggedIndex.value === index) return;
  dragOverIndex.value = index;
}

function handleDrop(index: number, event: DragEvent) {
  event.preventDefault();
  const from = draggedIndex.value;
  draggedIndex.value = null;
  dragOverIndex.value = null;
  if (from === null || from === index) return;
  const list = [...(props.activeLayers || [])];
  const [moved] = list.splice(from, 1);
  if (!moved) return;
  list.splice(index, 0, moved);
  emit("reorder-layers", list);
}

function handleDragEnd() {
  dragHandleIndex.value = null;
  draggedIndex.value = null;
  dragOverIndex.value = null;
}

function toFriendlyLayerName(layer: ActiveLayerItem): string {
  const key = String(layer.layer_name || layer.martinSource || "").toLowerCase();
  if (key.includes("infrastruktur_segmen")) return "Kondisi Jalan Dibangun";
  if (key.includes("jalan_porosdesa")) return "Jaringan Jalan Desa";
  if (key.includes("bataswilayah_kabupaten")) return "Batas Wilayah Bojonegoro";
  if (key.includes("bataswilayah_kecamatan")) return "Batas Kecamatan";
  if (key.includes("bataswilayah_desa")) return "Batas Desa";
  return layer.title || layer.name || "Lapisan Peta";
}

function toFriendlyDescription(layer: ActiveLayerItem): string {
  const key = String(layer.layer_name || layer.martinSource || "").toLowerCase();
  if (key.includes("infrastruktur_segmen")) {
    return "Menampilkan ruas jalan yang sudah dibangun beserta status kondisinya.";
  }
  if (key.includes("jalan_porosdesa")) {
    return "Menampilkan jalur utama penghubung antar desa se-Bojonegoro.";
  }
  if (key.includes("bataswilayah")) {
    return "Garis batas administrasi wilayah resmi Kabupaten Bojonegoro.";
  }
  return layer.description || layer.opd_name || layer.attribution || "Informasi peta pendukung resmi.";
}

function toFriendlyIcon(layer: ActiveLayerItem): string {
  const key = String(layer.layer_name || layer.martinSource || "").toLowerCase();
  if (key.includes("infrastruktur_segmen") || key.includes("jalan")) return "i-ion-trail-sign";
  if (key.includes("bataswilayah")) return "i-ion-map";
  return "i-ion-layers";
}

const primaryMapLayers = computed(() => {
  return (props.activeLayers || []).map((layer) => ({
    raw: layer,
    id: layer.id,
    title: toFriendlyLayerName(layer),
    desc: toFriendlyDescription(layer),
    icon: toFriendlyIcon(layer),
    visible: Boolean(layer.visible),
    opacity: typeof layer.opacity === "number" ? layer.opacity : 1,
  }));
});

const extraCatalogLayers = computed(() => {
  const activeIds = new Set((props.activeLayers || []).map((l) => l.id));
  const q = filterText.value.trim().toLowerCase();
  return (geoportalLayers.value || [])
    .filter((item) => {
      if (!q) return true;
      return (
        (item.name || "").toLowerCase().includes(q) ||
        (item.opd_name || "").toLowerCase().includes(q) ||
        (item.category || "").toLowerCase().includes(q)
      );
    })
    .map((item) => ({
      raw: item,
      id: item.id,
      title: item.name || item.layer_name,
      category: item.category || "Peta Pendukung",
      agency: item.opd_name || item.attribution || "Pemkab Bojonegoro",
      active: activeIds.has(item.id),
    }));
});
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 overflow-y-auto px-4 sm:px-5 pt-1 pb-5 space-y-4 select-none">
    <!-- Bar Kembali -->
    <div class="flex items-center justify-between gap-2 pt-1 shrink-0">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-white/[0.06] hover:bg-slate-200/80 dark:hover:bg-white/10 text-xs font-bold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer min-h-[44px]"
        @click="emit('back')"
      >
        <UIcon name="i-ion-arrow-back" class="size-4" />
        <span>Kembali ke Jelajah</span>
      </button>

      <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
        Tampilan Peta
      </span>
    </div>

    <!-- 1. Lapisan Utama (Grouped Soft Gray Container) -->
    <section class="space-y-2">
      <div class="flex items-center justify-between gap-2 px-0.5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
          Tampilkan di Peta
        </h2>
        <button
          v-if="primaryMapLayers.length > 0"
          type="button"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 min-h-[34px] rounded-full text-xs font-semibold transition-all cursor-pointer active:scale-95"
          :class="
            isReorderMode
              ? 'bg-blue-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/[0.06] text-slate-700 dark:text-slate-200 hover:bg-slate-200/80 dark:hover:bg-white/10'
          "
          @click="isReorderMode = !isReorderMode"
        >
          <UIcon :name="isReorderMode ? 'i-lucide-check' : 'i-lucide-sliders-horizontal'" class="size-3.5" />
          <span>{{ isReorderMode ? 'Selesai' : 'Sesuaikan' }}</span>
        </button>
      </div>

      <p
        v-if="isReorderMode"
        class="text-[11px] text-slate-500 dark:text-slate-400 px-0.5"
      >
        Atur urutan tumpukan dan transparansi (opacity) layer pada peta.
      </p>

      <div
        v-if="!isReorderMode"
        class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden"
      >
        <button
          v-for="item in primaryMapLayers"
          :key="item.id"
          type="button"
          role="switch"
          :aria-checked="item.visible"
          class="w-full px-3.5 py-3 text-left flex items-center justify-between gap-3 transition-colors cursor-pointer min-h-[60px] hover:bg-slate-200/45 dark:hover:bg-white/[0.04]"
          @click="emit('toggle-layer-visibility', item.id)"
        >
          <span
            class="size-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs transition-colors"
            :class="item.visible
              ? 'bg-blue-600 text-white'
              : 'bg-white dark:bg-[#0f172a] text-slate-700 dark:text-slate-200'"
          >
            <UIcon :name="item.icon" class="size-5" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
              {{ item.title }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2 leading-snug">
              {{ item.desc }}
            </p>
          </div>

          <span
            class="w-11 h-6 rounded-full p-0.5 transition-colors flex items-center shrink-0"
            :class="item.visible ? 'bg-blue-600 justify-end' : 'bg-slate-300 dark:bg-white/20 justify-start'"
          >
            <span class="size-5 rounded-full bg-white shadow-xs" />
          </span>
        </button>
      </div>

      <!-- Mode Sesuaikan Layer (Urutan & Opacity) -->
      <div
        v-else
        class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden"
      >
        <div
          v-for="(item, idx) in primaryMapLayers"
          :key="item.id"
          :draggable="dragHandleIndex === idx"
          class="w-full px-3.5 py-3 space-y-2 transition-colors"
          :class="[
            dragOverIndex === idx
              ? 'bg-blue-100/80 dark:bg-blue-950/40'
              : 'hover:bg-slate-200/40 dark:hover:bg-white/[0.03]',
            draggedIndex === idx ? 'opacity-50' : '',
          ]"
          @dragstart="handleDragStart(idx, $event)"
          @dragover="handleDragOver(idx, $event)"
          @drop="handleDrop(idx, $event)"
          @dragend="handleDragEnd"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
              <span
                role="button"
                tabindex="-1"
                aria-label="Seret untuk mengubah urutan layer"
                title="Seret untuk mengubah urutan"
                class="size-8 -ml-1 inline-flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:bg-white/80 dark:hover:bg-white/10 cursor-grab active:cursor-grabbing shrink-0 touch-none"
                @pointerdown="enableDragHandle(idx)"
                @mousedown="enableDragHandle(idx)"
                @pointerup="disableDragHandle"
                @mouseup="disableDragHandle"
              >
                <UIcon name="i-lucide-grip-vertical" class="size-4" aria-hidden="true" />
              </span>
              <span class="size-7 rounded-lg bg-white dark:bg-[#0f172a] shadow-2xs text-xs font-bold text-slate-700 dark:text-slate-200 inline-flex items-center justify-center shrink-0 tabular-nums">
                {{ idx + 1 }}
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
                  {{ item.title }}
                </p>
                <span class="text-[11px] font-medium" :class="item.visible ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                  {{ item.visible ? 'Aktif di peta' : 'Disembunyikan' }}
                </span>
              </div>
            </div>

            <div v-if="primaryMapLayers.length > 1" class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                :disabled="idx === 0"
                aria-label="Naikkan urutan layer"
                class="size-9 inline-flex items-center justify-center rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0f172a] hover:bg-slate-50 dark:hover:bg-white/10 text-slate-700 dark:text-slate-200 disabled:opacity-35 cursor-pointer"
                @click="moveLayer(idx, -1)"
              >
                <UIcon name="i-lucide-chevron-up" class="size-4" aria-hidden="true" />
              </button>
              <button
                type="button"
                :disabled="idx === primaryMapLayers.length - 1"
                aria-label="Turunkan urutan layer"
                class="size-9 inline-flex items-center justify-center rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0f172a] hover:bg-slate-50 dark:hover:bg-white/10 text-slate-700 dark:text-slate-200 disabled:opacity-35 cursor-pointer"
                @click="moveLayer(idx, 1)"
              >
                <UIcon name="i-lucide-chevron-down" class="size-4" aria-hidden="true" />
              </button>
            </div>
          </div>

          <!-- Slider Opacity Layer (terisolasi dari drag row & bottom-sheet) -->
          <div
            data-sheet-no-drag
            draggable="false"
            class="flex items-center gap-2.5 pl-7"
            @pointerdown.stop
            @mousedown.stop
            @touchstart.stop
            @touchmove.stop
            @dragstart.prevent.stop
          >
            <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300 shrink-0">
              Opacity
            </span>
            <input
              :value="item.opacity"
              type="range"
              draggable="false"
              min="0"
              max="1"
              step="0.05"
              :aria-label="`Atur opacity ${item.title}`"
              class="flex-1 h-8 py-2 accent-blue-600 cursor-pointer touch-none"
              @pointerdown.stop
              @mousedown.stop
              @touchstart.stop
              @touchmove.stop
              @dragstart.prevent.stop
              @input="emit('update-layer-opacity', { layerId: item.id, opacity: Number(($event.target as HTMLInputElement).value) })"
              @change="emit('update-layer-opacity', { layerId: item.id, opacity: Number(($event.target as HTMLInputElement).value) })"
            />
            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200 tabular-nums w-9 text-right shrink-0">
              {{ Math.round(item.opacity * 100) }}%
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. Visualisasi 3D MapLibre GL JS -->
    <section class="space-y-2 pt-1">
      <div class="flex items-center justify-between gap-2 px-0.5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
          Visualisasi 3D Peta
        </h2>
        <span class="text-xs font-medium text-slate-600 dark:text-slate-300 tabular-nums">
          MapLibre GL
        </span>
      </div>

      <div class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden">
        <!-- Bangunan 3D -->
        <button
          type="button"
          role="switch"
          :aria-checked="props.show3dBuildings"
          class="w-full px-3.5 py-3 text-left flex items-center justify-between gap-3 transition-colors cursor-pointer min-h-[60px] hover:bg-slate-200/45 dark:hover:bg-white/[0.04]"
          @click="emit('toggle-3d-buildings')"
        >
          <span
            class="size-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs transition-colors"
            :class="props.show3dBuildings
              ? 'bg-blue-600 text-white'
              : 'bg-white dark:bg-[#0f172a] text-slate-700 dark:text-slate-200'"
          >
            <UIcon name="i-lucide-building-2" class="size-5" aria-hidden="true" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
              Bangunan 3D
            </p>
            <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2 leading-snug">
              Ekstrusi tinggi gedung 3D pada skala kota hingga desa.
            </p>
          </div>

          <span
            class="w-11 h-6 rounded-full p-0.5 transition-colors flex items-center shrink-0"
            :class="props.show3dBuildings ? 'bg-blue-600 justify-end' : 'bg-slate-300 dark:bg-white/20 justify-start'"
          >
            <span class="size-5 rounded-full bg-white shadow-xs" />
          </span>
        </button>

        <!-- Medan 3D (Terrain) -->
        <div>
          <button
            type="button"
            role="switch"
            :aria-checked="props.show3dTerrain"
            class="w-full px-3.5 py-3 text-left flex items-center justify-between gap-3 transition-colors cursor-pointer min-h-[60px] hover:bg-slate-200/45 dark:hover:bg-white/[0.04]"
            @click="emit('toggle-3d-terrain')"
          >
            <span
              class="size-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs transition-colors"
              :class="props.show3dTerrain
                ? 'bg-blue-600 text-white'
                : 'bg-white dark:bg-[#0f172a] text-slate-700 dark:text-slate-200'"
            >
              <UIcon name="i-lucide-mountain" class="size-5" aria-hidden="true" />
            </span>

            <div class="min-w-0 flex-1">
              <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
                Medan 3D (Terrain)
              </p>
              <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2 leading-snug">
                Bentuk elevasi bukit, lembah, dan bayangan topografi.
              </p>
            </div>

            <span
              class="w-11 h-6 rounded-full p-0.5 transition-colors flex items-center shrink-0"
              :class="props.show3dTerrain ? 'bg-blue-600 justify-end' : 'bg-slate-300 dark:bg-white/20 justify-start'"
            >
              <span class="size-5 rounded-full bg-white shadow-xs" />
            </span>
          </button>

          <!-- Slider Skala Elevasi Medan 3D -->
          <div
            v-if="props.show3dTerrain"
            data-sheet-no-drag
            draggable="false"
            class="flex items-center gap-2.5 px-3.5 pb-3 pt-1 pl-16"
            @pointerdown.stop
            @mousedown.stop
            @touchstart.stop
            @touchmove.stop
            @dragstart.prevent.stop
          >
            <span class="text-xs font-medium text-slate-700 dark:text-slate-200 shrink-0">
              Elevasi
            </span>
            <input
              :value="props.terrainExaggeration"
              type="range"
              draggable="false"
              min="0.5"
              max="3"
              step="0.25"
              aria-label="Atur skala ketinggian medan 3D"
              class="flex-1 h-8 py-2 accent-blue-600 cursor-pointer touch-none"
              @pointerdown.stop
              @mousedown.stop
              @touchstart.stop
              @touchmove.stop
              @dragstart.prevent.stop
              @input="emit('update-terrain-exaggeration', Number(($event.target as HTMLInputElement).value))"
              @change="emit('update-terrain-exaggeration', Number(($event.target as HTMLInputElement).value))"
            />
            <span class="text-xs font-bold text-slate-800 dark:text-slate-100 tabular-nums w-10 text-right shrink-0">
              {{ props.terrainExaggeration.toFixed(1) }}x
            </span>
          </div>
        </div>
      </div>

      <p
        v-if="props.show3dBuildings || props.show3dTerrain"
        class="text-xs font-medium text-slate-600 dark:text-slate-300 px-0.5 leading-snug"
      >
        Tahan klik kanan atau Ctrl + geser untuk memiringkan sudut pandang peta 3D.
      </p>
    </section>

    <!-- 3. Peta Pendukung dari Instansi Lain -->
    <section class="space-y-2.5 pt-1">
      <div class="flex items-center justify-between px-0.5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
          Peta Pendukung Daerah
        </h2>
        <button
          type="button"
          class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer min-h-[44px] px-2 -mr-2"
          :disabled="loadingGeoportal"
          @click="refreshGeoportal()"
        >
          <UIcon name="i-ion-refresh-outline" class="size-3.5" :class="{ 'animate-spin': loadingGeoportal }" />
          <span>Muat Ulang</span>
        </button>
      </div>

      <div class="relative">
        <UIcon
          name="i-ion-search-outline"
          class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none"
        />
        <input
          v-model="filterText"
          type="text"
          placeholder="Cari peta tata ruang, sungai, fasilitas..."
          class="w-full h-10 pl-9 pr-4 rounded-full bg-slate-100/90 dark:bg-white/[0.06] border border-slate-200/80 dark:border-white/10 text-base sm:text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30"
        />
      </div>

      <div v-if="loadingGeoportal && extraCatalogLayers.length === 0" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-14 rounded-2xl bg-slate-100 dark:bg-white/5 animate-pulse" />
      </div>

      <div
        v-else-if="errorGeoportal && extraCatalogLayers.length === 0"
        class="p-4 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center space-y-2"
      >
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Daftar peta pendukung instansi belum tersedia saat ini.
        </p>
      </div>

      <div
        v-else-if="extraCatalogLayers.length === 0"
        class="p-4 rounded-2xl bg-slate-50 dark:bg-white/[0.03] text-center text-xs text-slate-500 dark:text-slate-400"
      >
        Tidak ada peta pendukung tambahan yang cocok.
      </div>

      <div
        v-else
        class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden"
      >
        <button
          v-for="ext in extraCatalogLayers"
          :key="ext.id"
          type="button"
          role="switch"
          :aria-checked="ext.active"
          class="w-full px-3.5 py-3 text-left flex items-center justify-between gap-3 transition-colors cursor-pointer min-h-[58px] hover:bg-slate-200/45 dark:hover:bg-white/[0.04]"
          @click="emit('toggle-geoportal', { layer: ext.raw, active: !ext.active })"
        >
          <span
            class="size-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs transition-colors"
            :class="ext.active
              ? 'bg-emerald-600 text-white'
              : 'bg-white dark:bg-[#0f172a] text-slate-700 dark:text-slate-200'"
          >
            <UIcon name="i-ion-earth" class="size-5" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-bold text-slate-900 dark:text-white truncate">
              {{ ext.title }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
              {{ ext.category }} · {{ ext.agency }}
            </p>
          </div>

          <span
            class="w-11 h-6 rounded-full p-0.5 transition-colors flex items-center shrink-0"
            :class="ext.active ? 'bg-emerald-600 justify-end' : 'bg-slate-300 dark:bg-white/20 justify-start'"
          >
            <span class="size-5 rounded-full bg-white shadow-xs" />
          </span>
        </button>
      </div>
    </section>
  </div>
</template>
